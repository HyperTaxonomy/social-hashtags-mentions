/**
 * Social Hashtags & Mentions — block editor integration.
 *
 * Hand-authored against the wp.* globals (no build step). Provides:
 *  - Rich-text format types for inline #hashtag and @mention anchors.
 *  - Autocompleters: # (hashtag terms), @ (mention terms + local users in one
 *    list).
 *  - A toolbar popover for per-instance network selection: which networks a
 *    hashtag links to, and which of a mention's configured profiles to show.
 *    (Mention handles themselves are configured on the term's edit screen.)
 *
 * Tokens are anchors carrying data-sh-tax / data-sh-term / data-sh-name /
 * data-sh-networks. The server reconciles a post's terms from these on save and
 * renders the network links/badges on the front end.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.richText || ! wp.element || ! wp.blockEditor || ! wp.components ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var registerFormatType = wp.richText.registerFormatType;
	var applyFormat = wp.richText.applyFormat;
	var getActiveFormat = wp.richText.getActiveFormat;
	var sliceValue = wp.richText.slice;
	var getTextContent = wp.richText.getTextContent;
	var useAnchor = wp.richText.useAnchor;
	var insert = wp.richText.insert;
	var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;
	var Popover = wp.components.Popover;
	var CheckboxControl = wp.components.CheckboxControl;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;
	var addFilter = wp.hooks.addFilter;

	var data = window.SHEditor || {};
	var NETWORKS = data.networks || [];

	var HASHTAG_FORMAT = 'social-hashtags-mentions/hashtag';
	var MENTION_FORMAT = 'social-hashtags-mentions/mention';

	// Shared format settings — reused for registration and for useAnchor(), which
	// positions each popover against the token's element (works inside the editor
	// iframe, where a raw window selection would not).
	var TOKEN_ATTRIBUTES = {
		url: 'href',
		tax: 'data-sh-tax',
		termId: 'data-sh-term',
		name: 'data-sh-name',
		networks: 'data-sh-networks',
	};
	var HASHTAG_SETTINGS = { tagName: 'a', className: 'sh-hashtag', attributes: TOKEN_ATTRIBUTES };
	var MENTION_SETTINGS = { tagName: 'a', className: 'sh-mention', attributes: TOKEN_ATTRIBUTES };

	// Resolve a Popover anchor at the active token. useAnchor is a hook, so it is
	// always called when available (the availability is constant per environment);
	// it returns undefined-safe values when there is no active format.
	function tokenAnchor( contentRef, settings ) {
		if ( ! useAnchor ) {
			return undefined;
		}
		return useAnchor( {
			editableContentElement: contentRef ? contentRef.current : null,
			settings: settings,
		} );
	}

	// ---------------------------------------------------------------------
	// Icons
	// ---------------------------------------------------------------------

	// A 24x24 SVG glyph (#, @) so the toolbar button occupies the same icon box
	// as Dashicons and stays aligned with the other format buttons. There is no
	// Dashicon for # or @, so an SVG is the only consistent option.
	function glyphIcon( ch ) {
		return el( 'svg', {
			width: 24,
			height: 24,
			viewBox: '0 0 24 24',
			xmlns: 'http://www.w3.org/2000/svg',
			focusable: 'false',
			'aria-hidden': 'true',
		}, el( 'text', {
			x: 12,
			y: 13,
			'text-anchor': 'middle',
			'dominant-baseline': 'central',
			'font-size': 18,
			'font-weight': 700,
			fill: 'currentColor',
		}, ch ) );
	}

	// A network's icon for the popover: the bundled brand SVG tinted with its brand
	// color when available, else the Dashicon (also tinted). Mirrors the front end.
	function netIconEl( n ) {
		var style = ( n && n.color ) ? { color: n.color } : undefined;
		if ( n && n.svg ) {
			return el( 'span', {
				className: 'sh-icon',
				style: style,
				'aria-hidden': 'true',
				dangerouslySetInnerHTML: { __html: n.svg },
			} );
		}
		return el( 'span', {
			className: 'dashicons ' + ( ( n && n.icon ) || 'dashicons-admin-links' ),
			style: style,
			'aria-hidden': 'true',
		} );
	}

	// A popover row: checkbox first, then the network icon, then the label text
	// (a handle/"handle (Name)"/"Name @ Site" for mentions, the network name for
	// hashtags). Clicking the icon/text toggles too.
	function netCheckRow( n, checked, onToggle ) {
		return el( 'div', { key: n.slug, className: 'sh-net-row' },
			el( CheckboxControl, {
				label: '',
				checked: checked,
				onChange: function ( c ) { onToggle( c ); },
			} ),
			el( 'span', {
				className: 'sh-net-click',
				onClick: function () { onToggle( ! checked ); },
			},
				netIconEl( n ),
				el( 'span', { className: 'sh-net-text' }, n.label )
			)
		);
	}

	// ---------------------------------------------------------------------
	// Data helpers
	// ---------------------------------------------------------------------

	function networksFor( tax ) {
		return NETWORKS.filter( function ( n ) {
			return n.supports.indexOf( tax ) >= 0;
		} );
	}

	function api( path, opts ) {
		opts = opts || {};
		return apiFetch( {
			url: data.rest + path,
			method: opts.method || 'GET',
			data: opts.data,
			headers: { 'X-WP-Nonce': data.nonce },
		} );
	}

	function searchTerms( tax, q ) {
		return api( 'terms?tax=' + tax + '&q=' + encodeURIComponent( q || '' ) );
	}
	function searchUsers( q ) {
		return api( 'users?q=' + encodeURIComponent( q || '' ) );
	}
	function getHandles( id ) {
		return api( 'handles?term_id=' + id );
	}

	// Find the character range of the active format of $type around the caret.
	function activeRange( value, type ) {
		var formats = value.formats || [];
		var pos = ( null !== value.start && undefined !== value.start ) ? value.start : 0;

		function hasType( i ) {
			var f = formats[ i ] || [];
			for ( var k = 0; k < f.length; k++ ) {
				if ( f[ k ].type === type ) {
					return true;
				}
			}
			return false;
		}

		var i = pos;
		if ( ! hasType( i ) && hasType( i - 1 ) ) {
			i = i - 1;
		}
		if ( ! hasType( i ) ) {
			return null;
		}
		var s = i;
		while ( s > 0 && hasType( s - 1 ) ) {
			s--;
		}
		var e = i;
		while ( e < formats.length && hasType( e ) ) {
			e++;
		}
		return { start: s, end: e };
	}

	function selectedNetworkList( attrs, tax ) {
		var all = networksFor( tax ).map( function ( n ) { return n.slug; } );
		var raw = ( attrs && attrs.networks ) || '*';
		if ( '' === raw || '*' === raw ) {
			return all;
		}
		return raw.split( ',' ).filter( Boolean );
	}

	function networksToAttr( list, tax ) {
		var all = networksFor( tax ).map( function ( n ) { return n.slug; } );
		return ( list.length === all.length ) ? '*' : list.join( ',' );
	}

	// ---------------------------------------------------------------------
	// Selection → token helpers
	// ---------------------------------------------------------------------

	// True when the current selection is non-collapsed (some text is highlighted).
	function hasSelection( value ) {
		return !! value && null != value.start && null != value.end && value.start !== value.end;
	}

	// True when any of `types` appears anywhere across the selection, or is active at
	// a collapsed caret. Used to keep our tools out of plain links and off other tokens.
	function formatInSelection( value, types ) {
		if ( ! value ) {
			return false;
		}
		var s = ( null != value.start ) ? value.start : 0;
		var e = ( null != value.end ) ? value.end : s;
		if ( s === e ) {
			for ( var t = 0; t < types.length; t++ ) {
				if ( getActiveFormat( value, types[ t ] ) ) {
					return true;
				}
			}
			return false;
		}
		var formats = value.formats || [];
		for ( var i = s; i < e; i++ ) {
			var fs = formats[ i ] || [];
			for ( var k = 0; k < fs.length; k++ ) {
				if ( types.indexOf( fs[ k ].type ) >= 0 ) {
					return true;
				}
			}
		}
		return false;
	}

	// A "word char" for token purposes: a Unicode letter, digit, or underscore — so
	// scanning stops at whitespace, punctuation, brackets, shortcode delimiters, and the
	// start/end of the body, not just at spaces.
	function isWordChar( ch ) {
		return '' !== ch && /[\p{L}\p{N}_]/u.test( ch );
	}

	// Resolve the token range for a selection: trim selected whitespace, snap both edges
	// out to the surrounding word boundary (stopping at whitespace/punctuation/body ends),
	// drop leading punctuation, and detect a matching trigger (#/@) already there so it is
	// reused, not duplicated. `singleWord` caps the token to the first word (hashtags may
	// not contain spaces or punctuation); mentions may span words. Returns
	// { start, end, name, insert } or null when there is nothing to wrap.
	function resolveTokenRange( text, selStart, selEnd, trigger, singleWord ) {
		var len = text.length;
		var s = selStart, e = selEnd;
		// Trim whitespace at the selection edges.
		while ( s < e && /\s/.test( text.charAt( s ) ) ) { s++; }
		while ( e > s && /\s/.test( text.charAt( e - 1 ) ) ) { e--; }
		// Snap out to the surrounding word.
		while ( s > 0 && isWordChar( text.charAt( s - 1 ) ) ) { s--; }
		while ( e < len && isWordChar( text.charAt( e ) ) ) { e++; }
		// Drop selected leading punctuation (e.g. '(', ',') that is neither a word char
		// nor a trigger. Trailing characters are kept — a mention may legitimately end in
		// punctuation ("C#") or contain "@" ("@user@host"); for hashtags the single-word
		// cap below excludes anything past the first word anyway.
		while ( s < e && ! isWordChar( text.charAt( s ) ) && '#' !== text.charAt( s ) && '@' !== text.charAt( s ) ) { s++; }
		if ( s >= e ) {
			return null;
		}

		var tokenStart, wordStart, needInsert;
		if ( text.charAt( s ) === trigger ) {
			// Selection already includes the matching trigger.
			tokenStart = s;
			wordStart = s + 1;
			needInsert = false;
		} else if ( s > 0 && text.charAt( s - 1 ) === trigger ) {
			// A matching trigger sits immediately before the word — reuse it.
			tokenStart = s - 1;
			wordStart = s;
			needInsert = false;
		} else if ( '#' === text.charAt( s ) || '@' === text.charAt( s ) ||
			( s > 0 && ( '#' === text.charAt( s - 1 ) || '@' === text.charAt( s - 1 ) ) ) ) {
			// The other tool's trigger is adjacent (e.g. @ before the word while using the
			// # tool): refuse rather than produce "@#word". The button is disabled here too.
			return null;
		} else {
			tokenStart = s;
			wordStart = s;
			needInsert = true;
		}

		var wordEnd = e;
		if ( singleWord ) {
			// Cap to the first word: stop at the first non-word char after the word start.
			var w = wordStart;
			while ( w < len && isWordChar( text.charAt( w ) ) ) { w++; }
			wordEnd = w;
		}
		var name = text.slice( wordStart, wordEnd ).trim();
		if ( '' === name ) {
			return null;
		}
		return { start: tokenStart, end: wordEnd, name: name, insert: needInsert };
	}

	// The trigger (#/@) adjacent to the word a selection resolves to, or '' if none.
	// Used to disable one tool when the other's trigger is already present, preventing
	// "@#word" / "#@word".
	function precedingTrigger( text, selStart, selEnd ) {
		var s = selStart, e = selEnd;
		while ( s < e && /\s/.test( text.charAt( s ) ) ) { s++; }
		while ( e > s && /\s/.test( text.charAt( e - 1 ) ) ) { e--; }
		while ( s > 0 && isWordChar( text.charAt( s - 1 ) ) ) { s--; }
		while ( s < e && ! isWordChar( text.charAt( s ) ) && '#' !== text.charAt( s ) && '@' !== text.charAt( s ) ) { s++; }
		if ( s >= e ) {
			return '';
		}
		if ( '#' === text.charAt( s ) || '@' === text.charAt( s ) ) {
			return text.charAt( s );
		}
		if ( s > 0 && ( '#' === text.charAt( s - 1 ) || '@' === text.charAt( s - 1 ) ) ) {
			return text.charAt( s - 1 );
		}
		return '';
	}

	// Build the token value for the current plain-text selection using the resolved word
	// range: reuse a trigger already present or insert one, and wrap the range as our
	// format. The term is resolved/created server-side on save. Returns null when there
	// is nothing to wrap.
	function buildStagedToken( value, format, tax, trigger, singleWord ) {
		if ( ! hasSelection( value ) ) {
			return null;
		}
		var r = resolveTokenRange( value.text || '', value.start, value.end, trigger, singleWord );
		if ( ! r ) {
			return null;
		}
		var working = value;
		var tokenEnd = r.end;
		if ( r.insert ) {
			working = insert( value, trigger, r.start, r.start );
			tokenEnd = r.end + 1;
		}
		var applied = applyFormat( working, {
			type: format,
			attributes: { tax: tax, termId: '', name: r.name, networks: '*', url: '#' },
		}, r.start, tokenEnd );
		// Select the whole token so its format becomes active and the popover opens.
		return Object.assign( {}, applied, { start: r.start, end: tokenEnd } );
	}

	// ---------------------------------------------------------------------
	// Hashtag format: per-instance network selection
	// ---------------------------------------------------------------------

	function HashtagEdit( props ) {
		var isActive = props.isActive;
		var value = props.value;
		var onChange = props.onChange;
		var attrs = props.activeAttributes || {};
		var dismissedState = useState( false );
		var dismissed = dismissedState[ 0 ];
		var setDismissed = dismissedState[ 1 ];
		// While staging a token created from a selection, stagingRef holds the
		// pre-staging value so Escape/click-away can revert (insert + wrap + selection)
		// in one shot; isStaging drives the Apply button. A ref (not just state) makes
		// the commit/cancel check race-free against the Popover's onClose.
		var stagingRef = useRef( null );
		var stagingState = useState( false );
		var isStaging = stagingState[ 0 ];
		var setIsStaging = stagingState[ 1 ];
		// Keeps the latest onChange reachable from the unmount-cleanup effect
		// below without retriggering it (that effect's deps must stay empty —
		// it has to run its cleanup only once, on actual unmount).
		var onChangeRef = useRef( onChange );
		onChangeRef.current = onChange;

		// Auto-open the settings popover when the caret is inside a hashtag token
		// (like the core Link UI); reset the dismissed flag on leaving the token.
		useEffect( function () {
			if ( ! isActive ) {
				setDismissed( false );
			}
		}, [ isActive ] );
		var open = isActive && ! dismissed;

		// The Popover below is only rendered while `open` is true (`open ? el(
		// Popover, ...) : null`); moving the caret off the staged token — even to
		// elsewhere in the very same paragraph — makes `isActive`, and so `open`,
		// false, which removes the Popover element directly, WITHOUT its own
		// onClose ever running. Catch that transition here instead; this fires on
		// every path that closes the popover while this component stays mounted.
		useEffect( function () {
			if ( ! open && null !== stagingRef.current ) {
				cancelStage();
			}
		}, [ open ] );

		// If the selection leaves this block entirely while a token is still
		// staged (e.g. the caret moves to a different block or the title), the
		// toolbar button — and with it this whole component — unmounts, so the
		// effect above never gets to run either. Catch that case on unmount the
		// same way, but via the ref (setIsStaging would warn on an unmounted
		// component; onChange alone is enough to revert the content).
		useEffect( function () {
			return function () {
				if ( null !== stagingRef.current ) {
					onChangeRef.current( stagingRef.current );
					stagingRef.current = null;
				}
			};
		}, [] );

		var nets = networksFor( 'hashtag' );

		// Disabled when the selection sits in a plain link (our anchor can't coexist —
		// remove the link first) or any other existing anchor, when a mention trigger (@)
		// already prefixes the word (avoid "@#word"), and when there's nothing to act on.
		// Enabled to EDIT our token (isActive) or STAGE one from plain text.
		var linkActive = formatInSelection( value, [ 'core/link' ] );
		var overlapsAnchor = formatInSelection( value, [ 'core/link', HASHTAG_FORMAT, MENTION_FORMAT ] );
		var otherPrefix = hasSelection( value ) && '@' === precedingTrigger( value.text || '', value.start, value.end );
		var disabled = linkActive || ( ! isActive && ( ! hasSelection( value ) || overlapsAnchor || otherPrefix ) );

		function commitStage() {
			stagingRef.current = null;
			setIsStaging( false );
			setDismissed( true );
		}
		function cancelStage() {
			var original = stagingRef.current;
			if ( null === original ) {
				return;
			}
			stagingRef.current = null;
			setIsStaging( false );
			onChange( original );
		}
		function onButton() {
			if ( disabled ) {
				return;
			}
			if ( isActive ) {
				setDismissed( ! dismissed );
				return;
			}
			var staged = buildStagedToken( value, HASHTAG_FORMAT, 'hashtag', '#', true );
			if ( ! staged ) {
				return;
			}
			stagingRef.current = value;
			setIsStaging( true );
			setDismissed( false );
			onChange( staged );
		}

		function applyNetworks( list ) {
			var range = activeRange( value, HASHTAG_FORMAT );
			if ( ! range ) {
				return;
			}
			var active = getActiveFormat( value, HASHTAG_FORMAT );
			var current = ( active && active.attributes ) || attrs || {};
			var next = Object.assign( {}, current, { networks: networksToAttr( list, 'hashtag' ) } );
			onChange( applyFormat( value, { type: HASHTAG_FORMAT, attributes: next }, range.start, range.end ) );
		}

		function toggle( slug, checked ) {
			var cur = selectedNetworkList( attrs, 'hashtag' );
			var next = checked
				? cur.concat( [ slug ] )
				: cur.filter( function ( s ) { return s !== slug; } );
			applyNetworks( next );
		}

		var selected = selectedNetworkList( attrs, 'hashtag' );
		var anchor = tokenAnchor( props.contentRef, HASHTAG_SETTINGS );

		return el( Fragment, null,
			el( RichTextToolbarButton, {
				icon: glyphIcon( '#' ),
				title: __( 'Hashtag', 'social-hashtags-mentions' ),
				isActive: isActive,
				disabled: disabled,
				onClick: onButton,
			} ),
			open ? el( Popover, {
				className: 'sh-popover',
				anchor: anchor,
				placement: 'bottom-start',
				// Don't move focus into the panel on open (it auto-opens when the caret
				// enters a token); otherwise the first checkbox shows a focus ring.
				focusOnMount: false,
				onClose: function () {
					if ( null !== stagingRef.current ) {
						cancelStage();
					} else {
						setDismissed( true );
					}
				},
			},
				el( 'div', { className: 'sh-popover__inner' },
					el( 'strong', null, __( 'Link this hashtag to:', 'social-hashtags-mentions' ) ),
					nets.length ? nets.map( function ( n ) {
						return netCheckRow( n, selected.indexOf( n.slug ) >= 0, function ( c ) { toggle( n.slug, c ); } );
					} ) : el( 'p', null, __( 'No networks enabled.', 'social-hashtags-mentions' ) ),
					isStaging ? el( 'div', { className: 'sh-popover__actions', style: { marginTop: '10px', textAlign: 'right' } },
						el( Button, { variant: 'primary', onClick: commitStage }, __( 'Apply', 'social-hashtags-mentions' ) )
					) : null
				)
			) : null
		);
	}

	// ---------------------------------------------------------------------
	// Mention format: per-instance profile selection (like hashtag networks)
	// ---------------------------------------------------------------------
	//
	// The popover lists the enabled mention networks for which this term already
	// has a configured handle, each with a checkbox, so an instance can be limited
	// to a subset — exactly like the hashtag popover. Handles themselves are set on
	// the term's edit screen (TermConnect External Links), not here.

	function MentionEdit( props ) {
		var isActive = props.isActive;
		var value = props.value;
		var onChange = props.onChange;
		var attrs = props.activeAttributes || {};
		var dismissedState = useState( false );
		var dismissed = dismissedState[ 0 ];
		var setDismissed = dismissedState[ 1 ];
		// Staging state for a token created from a selection (see HashtagEdit).
		var stagingRef = useRef( null );
		var stagingState = useState( false );
		var isStaging = stagingState[ 0 ];
		var setIsStaging = stagingState[ 1 ];
		// See HashtagEdit's identical onChangeRef/unmount-cleanup pair.
		var onChangeRef = useRef( onChange );
		onChangeRef.current = onChange;
		// Auto-open the popover when the caret is inside a mention token; reset the
		// dismissed flag on leaving the token.
		useEffect( function () {
			if ( ! isActive ) {
				setDismissed( false );
			}
		}, [ isActive ] );
		useEffect( function () {
			return function () {
				if ( null !== stagingRef.current ) {
					onChangeRef.current( stagingRef.current );
					stagingRef.current = null;
				}
			};
		}, [] );
		var open = isActive && ! dismissed;
		// See HashtagEdit's identical, more fully-commented version of this
		// effect: the Popover below only renders while `open` is true, so moving
		// the caret off the staged token — even within the same paragraph —
		// removes it directly without its own onClose ever running.
		useEffect( function () {
			if ( ! open && null !== stagingRef.current ) {
				cancelStage();
			}
		}, [ open ] );
		// null = loading; otherwise an array of { slug, label, icon } for the
		// enabled mention networks that have a handle configured on this term.
		var netState = useState( null );
		var netList = netState[ 0 ];
		var setNetList = netState[ 1 ];
		var tidState = useState( attrs.termId ? parseInt( attrs.termId, 10 ) : 0 );
		var tid = tidState[ 0 ];
		var setTid = tidState[ 1 ];

		var nets = networksFor( 'mention' );

		// Disabled in plain links / on other anchors, when a hashtag trigger (#) already
		// prefixes the word (avoid "#@word"), and when there's nothing to act on; enabled
		// to EDIT our token (isActive) or STAGE one from a plain-text selection.
		var linkActive = formatInSelection( value, [ 'core/link' ] );
		var overlapsAnchor = formatInSelection( value, [ 'core/link', HASHTAG_FORMAT, MENTION_FORMAT ] );
		var otherPrefix = hasSelection( value ) && '#' === precedingTrigger( value.text || '', value.start, value.end );
		var disabled = linkActive || ( ! isActive && ( ! hasSelection( value ) || overlapsAnchor || otherPrefix ) );

		function commitStage() {
			stagingRef.current = null;
			setIsStaging( false );
			setDismissed( true );
		}
		function cancelStage() {
			var original = stagingRef.current;
			if ( null === original ) {
				return;
			}
			stagingRef.current = null;
			setIsStaging( false );
			onChange( original );
		}
		function onButton() {
			if ( disabled ) {
				return;
			}
			if ( isActive ) {
				setDismissed( ! dismissed );
				return;
			}
			var staged = buildStagedToken( value, MENTION_FORMAT, 'mention', '@', false );
			if ( ! staged ) {
				return;
			}
			stagingRef.current = value;
			setIsStaging( true );
			setDismissed( false );
			onChange( staged );
		}

		function tokenName() {
			if ( attrs && attrs.name ) {
				return attrs.name;
			}
			try {
				var r = activeRange( value, MENTION_FORMAT );
				if ( r ) {
					return getTextContent( sliceValue( value, r.start, r.end ) ).replace( /^@/, '' );
				}
			} catch ( e ) {}
			return '';
		}

		// Bind the resolved term ID into the anchor so the instance stays linked by
		// stable ID, not just by display name.
		function writeTermId( id ) {
			if ( ! onChange ) {
				return;
			}
			try {
				var range = activeRange( value, MENTION_FORMAT );
				if ( ! range ) {
					return;
				}
				var active = getActiveFormat( value, MENTION_FORMAT );
				var current = ( active && active.attributes ) || attrs || {};
				onChange( applyFormat( value, {
					type: MENTION_FORMAT,
					attributes: Object.assign( {}, current, { termId: String( id ) } ),
				}, range.start, range.end ) );
			} catch ( e ) {}
		}

		// Load the term's configured handles and reduce them to selectable rows.
		useEffect( function () {
			if ( ! open ) {
				return;
			}
			setNetList( null );
			var name = tokenName();
			// Resolve from the CURRENT token's attributes, not the (possibly stale)
			// tid state — the caret may have moved to a different mention token
			// without this format component remounting.
			var attrId = attrs.termId ? parseInt( attrs.termId, 10 ) : 0;
			var idPromise;
			if ( attrId > 0 ) {
				idPromise = Promise.resolve( attrId );
			} else if ( name ) {
				idPromise = searchTerms( 'mention', name ).then( function ( terms ) {
					var match = ( terms || [] ).filter( function ( t ) {
						return ( t.name || '' ).toLowerCase() === name.toLowerCase();
					} )[ 0 ];
					return match ? match.id : 0;
				} );
			} else {
				idPromise = Promise.resolve( 0 );
			}
			idPromise.then( function ( id ) {
				if ( ! id ) {
					return [];
				}
				setTid( id );
				writeTermId( id );
				return getHandles( id );
			} ).then( function ( handles ) {
				var iconBy = {};
				nets.forEach( function ( n ) { iconBy[ n.slug ] = n; } );
				var list = ( handles || [] ).filter( function ( h ) {
					return h && h.handle;
				} ).map( function ( h ) {
					var def = iconBy[ h.network ] || {};
					return {
						slug: h.network,
						label: h.label || def.label || h.network,
						icon: h.icon || def.icon,
						svg: def.svg,
						color: def.color,
					};
				} );
				setNetList( list );
			} ).catch( function () { setNetList( [] ); } );
		}, [ open, attrs.termId, attrs.name ] );

		var allSlugs = ( netList || [] ).map( function ( n ) { return n.slug; } );

		function currentSelection() {
			var raw = ( attrs && attrs.networks ) || '*';
			if ( '' === raw || '*' === raw ) {
				return allSlugs.slice();
			}
			return raw.split( ',' ).filter( function ( s ) { return allSlugs.indexOf( s ) >= 0; } );
		}

		function writeSelection( list ) {
			var range = activeRange( value, MENTION_FORMAT );
			if ( ! range ) {
				return;
			}
			var active = getActiveFormat( value, MENTION_FORMAT );
			var current = ( active && active.attributes ) || attrs || {};
			var attrVal = ( list.length === allSlugs.length ) ? '*' : list.join( ',' );
			onChange( applyFormat( value, {
				type: MENTION_FORMAT,
				attributes: Object.assign( {}, current, { networks: attrVal } ),
			}, range.start, range.end ) );
		}

		function toggle( slug, checked ) {
			var cur = currentSelection();
			var next = checked
				? cur.concat( [ slug ] )
				: cur.filter( function ( s ) { return s !== slug; } );
			// Keep the admin-defined order.
			writeSelection( allSlugs.filter( function ( s ) { return next.indexOf( s ) >= 0; } ) );
		}

		var selected = currentSelection();
		var anchor = tokenAnchor( props.contentRef, MENTION_SETTINGS );

		return el( Fragment, null,
			el( RichTextToolbarButton, {
				icon: glyphIcon( '@' ),
				title: __( 'Mention', 'social-hashtags-mentions' ),
				isActive: isActive,
				disabled: disabled,
				onClick: onButton,
			} ),
			open ? el( Popover, {
				className: 'sh-popover',
				anchor: anchor,
				placement: 'bottom-start',
				// Don't move focus into the panel on open (it auto-opens when the caret
				// enters a token); otherwise the first checkbox shows a focus ring.
				focusOnMount: false,
				onClose: function () {
					if ( null !== stagingRef.current ) {
						cancelStage();
					} else {
						setDismissed( true );
					}
				},
			},
				el( 'div', { className: 'sh-popover__inner' },
					el( 'strong', null, __( 'Link this mention to:', 'social-hashtags-mentions' ) ),
					( null === netList ) ? el( Spinner ) : null,
					( netList && netList.length ) ? netList.map( function ( n ) {
						return netCheckRow( n, selected.indexOf( n.slug ) >= 0, function ( c ) { toggle( n.slug, c ); } );
					} ) : null,
					( netList && ! netList.length ) ? el( 'p', { className: 'sh-popover__hint' },
						__( 'No profiles are configured for this mention yet. Add them on the mention’s edit screen.', 'social-hashtags-mentions' )
					) : null,
					isStaging ? el( 'div', { className: 'sh-popover__actions', style: { marginTop: '10px', textAlign: 'right' } },
						el( Button, { variant: 'primary', onClick: commitStage }, __( 'Apply', 'social-hashtags-mentions' ) )
					) : null
				)
			) : null
		);
	}

	// ---------------------------------------------------------------------
	// Format registration
	// ---------------------------------------------------------------------

	registerFormatType( HASHTAG_FORMAT, Object.assign( {}, HASHTAG_SETTINGS, {
		title: __( 'Hashtag', 'social-hashtags-mentions' ),
		edit: HashtagEdit,
	} ) );

	registerFormatType( MENTION_FORMAT, Object.assign( {}, MENTION_SETTINGS, {
		title: __( 'Mention', 'social-hashtags-mentions' ),
		edit: MentionEdit,
	} ) );

	// ---------------------------------------------------------------------
	// Autocompleters
	// ---------------------------------------------------------------------

	function hashtagElement( opt ) {
		var display = '#' + ( opt.slug || opt.name );
		return el( 'a', {
			className: 'sh-hashtag',
			href: opt.url || '#',
			'data-sh-tax': 'hashtag',
			'data-sh-term': opt.id ? String( opt.id ) : '',
			'data-sh-name': opt.id ? '' : opt.name,
			'data-sh-networks': '*',
		}, display );
	}

	function mentionElement( opt ) {
		var display = '@' + ( opt.name || opt.slug );
		return el( 'a', {
			className: 'sh-mention',
			href: opt.url || '#',
			'data-sh-tax': 'mention',
			'data-sh-term': opt.id ? String( opt.id ) : '',
			'data-sh-name': opt.id ? '' : ( opt.name || opt.slug ),
			'data-sh-networks': '*',
		}, display );
	}

	function hashtagCompleter() {
		return {
			name: 'social-hashtags-mentions/hashtag',
			className: 'sh-autocompleter',
			triggerPrefix: '#',
			options: function ( query ) {
				return searchTerms( 'hashtag', query ).then( function ( terms ) {
					var opts = ( terms || [] ).map( function ( t ) {
						return { id: t.id, name: t.name, slug: t.slug, url: t.url };
					} );
					if ( query && ! opts.some( function ( o ) { return o.slug === query.toLowerCase(); } ) ) {
						opts.push( { id: 0, name: query, slug: '', url: '', isCreate: true } );
					}
					return opts;
				} );
			},
			getOptionLabel: function ( opt ) {
				return opt.isCreate
					? el( 'span', null, __( 'Create', 'social-hashtags-mentions' ) + ' #' + opt.name )
					: el( 'span', null, '#' + ( opt.slug || opt.name ) );
			},
			getOptionKeywords: function ( opt ) { return [ opt.name, opt.slug ]; },
			getOptionCompletion: function ( opt ) { return hashtagElement( opt ); },
			isDebounced: true,
		};
	}

	function mentionCompleter() {
		return {
			name: 'social-hashtags-mentions/mention',
			className: 'sh-autocompleter',
			triggerPrefix: '@',
			options: function ( query ) {
				// One @ list combining our mention terms and local site users.
				return Promise.all( [
					searchTerms( 'mention', query ),
					searchUsers( query ),
				] ).then( function ( res ) {
					var terms = ( res[ 0 ] || [] ).map( function ( t ) {
						return { id: t.id, name: t.name, slug: t.slug, url: t.url };
					} );
					var users = ( res[ 1 ] || [] ).map( function ( u ) {
						return { id: 0, name: u.name, slug: u.slug, url: u.url, isUser: true };
					} );
					var opts = terms.concat( users );
					if ( query && ! opts.length ) {
						opts.push( { id: 0, name: query, slug: '', url: '', isCreate: true } );
					}
					return opts;
				} );
			},
			getOptionLabel: function ( opt ) {
				if ( opt.isCreate ) {
					return el( 'span', null, __( 'Create', 'social-hashtags-mentions' ) + ' @' + opt.name );
				}
				var suffix = opt.isUser ? ' · ' + __( 'user', 'social-hashtags-mentions' ) : '';
				return el( 'span', null, '@' + ( opt.name || opt.slug ) + suffix );
			},
			getOptionKeywords: function ( opt ) { return [ opt.name, opt.slug ]; },
			getOptionCompletion: function ( opt ) { return mentionElement( opt ); },
			isDebounced: true,
		};
	}

	addFilter(
		'editor.Autocomplete.completers',
		'social-hashtags-mentions/completers',
		function ( completers ) {
			// Drop any existing @-trigger completer (WordPress core ships a user
			// mention completer that otherwise intercepts @, shows only users, and
			// inserts plain text). Ours inserts a linked mention format and also
			// suggests existing mention terms. Put ours first so it owns # and @.
			var others = ( completers || [] ).filter( function ( c ) {
				return ! c || c.triggerPrefix !== '@';
			} );
			return [ hashtagCompleter(), mentionCompleter() ].concat( others );
		}
	);
}( window.wp ) );
