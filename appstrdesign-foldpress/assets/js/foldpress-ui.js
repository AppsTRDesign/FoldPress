/**
 * FoldPress shared UI: icons, AJAX, modal, toast, lightbox, clipboard.
 * Exposed as window.FoldPressUI.
 */
( function () {
	'use strict';

	const D = window.FoldPressUIData || {};
	const T = D.i18n || {};

	const esc = ( s ) => String( s == null ? '' : s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );

	/* ---------- Icons (local SVG paths, Bootstrap Icons style) ---------- */
	const P = {
		folder: 'M1.5 3A1.5 1.5 0 0 1 3 1.5h3.17a1.5 1.5 0 0 1 1.06.44l.83.83a.5.5 0 0 0 .35.15H13A1.5 1.5 0 0 1 14.5 4.5v8A1.5 1.5 0 0 1 13 14H3a1.5 1.5 0 0 1-1.5-1.5z',
		folderOpen: 'M1.5 12.5V3A1.5 1.5 0 0 1 3 1.5h3.17l1.33 1.33H12A1.5 1.5 0 0 1 13.5 4.33V6M1.5 12.5 3.3 7.2A1 1 0 0 1 4.25 6.5h10a.5.5 0 0 1 .47.67l-1.8 5.6a1 1 0 0 1-.95.73H2.5a1 1 0 0 1-1-1z',
		folderPlus: 'M1.5 3A1.5 1.5 0 0 1 3 1.5h3.17a1.5 1.5 0 0 1 1.06.44l.83.83a.5.5 0 0 0 .35.15H13A1.5 1.5 0 0 1 14.5 4.5v8A1.5 1.5 0 0 1 13 14H3a1.5 1.5 0 0 1-1.5-1.5zM8 6.5v4M6 8.5h4',
		subdir: 'M3 2v6.5A1.5 1.5 0 0 0 4.5 10H13M10 7l3 3-3 3',
		files: 'M4 1.5h5l3.5 3.5V13a1.5 1.5 0 0 1-1.5 1.5H4A1.5 1.5 0 0 1 2.5 13V3A1.5 1.5 0 0 1 4 1.5zM9 1.5V5h3.5',
		inbox: 'M1.5 9.5 3.5 3h9l2 6.5V13a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1zM1.5 9.5h4l1 1.5h3l1-1.5h4',
		upload: 'M8 11V2M4.5 5.5 8 2l3.5 3.5M2 10v3a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-3',
		cloud: 'M4.5 13A3.5 3.5 0 0 1 4 6.04 4.5 4.5 0 0 1 12.7 5.5 3.75 3.75 0 0 1 12.25 13M8 14.5V8.5M5.75 10.5 8 8.25l2.25 2.25',
		download: 'M8 2v9M4.5 7.5 8 11l3.5-3.5M2 10v3a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-3',
		grid: 'M2 2h5v5H2zM9 2h5v5H9zM2 9h5v5H2zM9 9h5v5H9z',
		list: 'M5 3.5h9M5 8h9M5 12.5h9M2 3.5h.5M2 8h.5M2 12.5h.5',
		layers: 'M8 1.5 14.5 5 8 8.5 1.5 5zM1.5 8 8 11.5 14.5 8M1.5 11 8 14.5 14.5 11',
		search: 'M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM14 14l-3.5-3.5',
		trash: 'M2.5 4h11M6 4V2.5h4V4M4 4l.7 9.5a1 1 0 0 0 1 .9h4.6a1 1 0 0 0 1-.9L12 4M6.5 6.5v5M9.5 6.5v5',
		pencil: 'M11 2.5 13.5 5 5.5 13H3v-2.5zM9.5 4l2.5 2.5',
		move: 'M1.5 3A1.5 1.5 0 0 1 3 1.5h3.17l1.33 1.33H13a1.5 1.5 0 0 1 1.5 1.5V12.5A1.5 1.5 0 0 1 13 14H3a1.5 1.5 0 0 1-1.5-1.5zM6 8.5h4.5M8.5 6.5l2 2-2 2',
		zip: 'M4 1.5h5l3.5 3.5V13a1.5 1.5 0 0 1-1.5 1.5H4A1.5 1.5 0 0 1 2.5 13V3A1.5 1.5 0 0 1 4 1.5zM7 2v1.5M7 4.5V6M7 7v1.5M6 9.5h2v2.5H6z',
		unzip: 'M2 5.5h12v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zM1.5 2.5h13v3h-13zM6.5 8.5h3',
		eye: 'M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5zM8 10.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
		info: 'M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zM8 7v4.5M8 4.5v.5',
		link: 'M6.5 9.5a3 3 0 0 0 4.2 0l2.3-2.3a3 3 0 0 0-4.2-4.2l-.8.8M9.5 6.5a3 3 0 0 0-4.2 0L3 8.8a3 3 0 0 0 4.2 4.2l.8-.8',
		check: 'M3 8.5 6.5 12 13 4.5',
		checkAll: 'M1.5 8.5 4.5 11.5 10 5M7 11.5l.5.5 7-7.5',
		x: 'M3.5 3.5l9 9M12.5 3.5l-9 9',
		chevron: 'M6 3.5 10.5 8 6 12.5',
		chevronL: 'M10 3.5 5.5 8 10 12.5',
		chevronDown: 'M3.5 6 8 10.5 12.5 6',
		arrowLeft: 'M13 8H3M7 4 3 8l4 4',
		dots: 'M8 3.5v.5M8 8v.5M8 12.5v.5',
		menu: 'M2 4h12M2 8h12M2 12h12',
		alert: 'M8 1.5 15 14H1zM8 6v4M8 12v.5',
		success: 'M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zM5 8.3l2 2 4-4.3',
		external: 'M9 2h5v5M14 2 7.5 8.5M12 9.5V13a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3.5',
		scissors: 'M4 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM4 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM5.6 5.2 14 12.5M5.6 10.8 14 3.5',
		clipboard: 'M5.5 2.5h-1A1.5 1.5 0 0 0 3 4v9.5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5V4a1.5 1.5 0 0 0-1.5-1.5h-1M6 1.5h4v2H6z',
		keyboard: 'M1.5 4h13v8h-13zM4 6.5h.5M6.5 6.5H7M9 6.5h.5M11.5 6.5h.5M4.5 9.5h7',
		help: 'M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zM6 6a2 2 0 1 1 2.8 1.8c-.5.3-.8.7-.8 1.2v.5M8 11.5v.5',
		gear: 'M8 10.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM13.4 9.5l1.1.9-1.3 2.2-1.4-.5a5 5 0 0 1-1.3.8L10.3 14H7.7l-.2-1.1a5 5 0 0 1-1.3-.8l-1.4.5-1.3-2.2 1.1-.9a5 5 0 0 1 0-1.5l-1.1-.9 1.3-2.2 1.4.5a5 5 0 0 1 1.3-.8L7.7 2h2.6l.2 1.1a5 5 0 0 1 1.3.8l1.4-.5 1.3 2.2-1.1.9a5 5 0 0 1 0 1.5z',
		sliders: 'M2 4h7M12 4h2M2 12h2M7 12h7M2 8h3M8 8h6M10.5 2.5v3M5.5 10.5v3M6.5 6.5v3',
		image: 'M2 3h12v10H2zM2 11l3.5-3.5 3 3 2-2L14 12M10.5 6.5a1 1 0 1 0 0-.01',
		cpu: 'M4 4h8v8H4zM6.5 6.5h3v3h-3zM6 1.5V4M10 1.5V4M6 12v2.5M10 12v2.5M1.5 6H4M1.5 10H4M12 6h2.5M12 10h2.5',
		reset: 'M2.5 8a5.5 5.5 0 1 0 1.6-3.9M2.5 2v3.5H6',
		plus: 'M8 3v10M3 8h10',
		save: 'M2.5 2.5h9l2 2v9h-11zM5 2.5v3.5h5V2.5M5 13.5V9.5h6v4',
		user: 'M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2.5 14.5a5.5 5.5 0 0 1 11 0',
		calendar: 'M2 3.5h12v10.5H2zM2 6.5h12M5 2v3M11 2v3',
		crop: 'M4 1v10h10M1 4h10v11',
		ruler: 'M1.5 10.5 10.5 1.5l4 4-9 9zM4 8l1.5 1.5M6 6l1.5 1.5M8 4l1.5 1.5',
		weight: 'M3 6h10l1 8H2zM6 6a2 2 0 1 1 4 0',
		zap: 'M9 1.5 3 9h4.5L7 14.5 13 7H8.5z',
		code: 'M5.5 4 1.5 8l4 4M10.5 4l4 4-4 4M9.5 2.5l-3 11',
		play: 'M5 3.2v9.6a.6.6 0 0 0 .9.5l7.6-4.8a.6.6 0 0 0 0-1L5.9 2.7a.6.6 0 0 0-.9.5z',
		pause: 'M5 3h1.8v10H5zM9.2 3H11v10H9.2z',
		volume: 'M2.5 6h2.5L8.5 3v10L5 10H2.5zM11 5.5a3.5 3.5 0 0 1 0 5M12.8 3.8a6 6 0 0 1 0 8.4',
		mute: 'M2.5 6h2.5L8.5 3v10L5 10H2.5zM11 6l3 4M14 6l-3 4',
		back10: 'M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5',
		fwd10: 'M13 8a5 5 0 1 1-1.5-3.5M13 2.5V5h-2.5',
		loop: 'M3 7V6a2 2 0 0 1 2-2h8M11 2l2 2-2 2M13 9v1a2 2 0 0 1-2 2H3M5 14l-2-2 2-2',
		pip: 'M1.5 3h13v10h-13zM8.5 8h4.5v4H8.5z',
		expand: 'M2 6V2h4M10 2h4v4M14 10v4h-4M6 14H2v-4',
		shrink: 'M6 2v4H2M14 6h-4V2M10 14v-4h4M2 10h4v4',
		folderCheck: 'M1.5 3A1.5 1.5 0 0 1 3 1.5h3.17a1.5 1.5 0 0 1 1.06.44l.83.83a.5.5 0 0 0 .35.15H13A1.5 1.5 0 0 1 14.5 4.5v8A1.5 1.5 0 0 1 13 14H3a1.5 1.5 0 0 1-1.5-1.5zM5.5 8.5l1.8 1.8 3.2-3.6',
		mouse: 'M8 1.5a4 4 0 0 1 4 4v5a4 4 0 0 1-8 0v-5a4 4 0 0 1 4-4zM8 4v2.5',
		mouseDbl: 'M8 1.5a4 4 0 0 1 4 4v5a4 4 0 0 1-8 0v-5a4 4 0 0 1 4-4zM7 4v2.5M9 4v2.5',
		ctxmenu: 'M2.5 2.5h11v11h-11zM5 5.5h6M5 8h6M5 10.5h3.5',
		touch: 'M6.5 8V3a1.5 1.5 0 0 1 3 0v4.5l3 .8a1.5 1.5 0 0 1 1.1 1.7L13 14H7l-2.8-3.4a1.2 1.2 0 0 1 1.8-1.6L6.5 10',
		arrowUp: 'M8 13V3M4 7l4-4 4 4',
		arrowDown: 'M8 3v10M4 9l4 4 4-4',
		arrowRight: 'M3 8h10M9 4l4 4-4 4',
		speed: 'M8 14.5a6 6 0 1 1 6-6M8 8.5l3-3',
		clock: 'M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zM8 4v4l2.5 2',
	};
	const icon = ( name, cls = '' ) => `<svg class="afp-i ${ cls }" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="${ P[ name ] || P.files }"/></svg>`;

	const GROUP_COLOR = {
		image: '#10b981', video: '#ef4444', audio: '#a855f7', pdf: '#dc2626', document: '#2563eb', spreadsheet: '#16a34a',
		presentation: '#ea580c', archive: '#ca8a04', code: '#0891b2', font: '#64748b', other: '#6b7280',
	};
	const fileIcon = ( item ) => {
		const c = GROUP_COLOR[ item.group ] || GROUP_COLOR.other;
		const ext = esc( ( item.ext || '?' ).slice( 0, 4 ).toUpperCase() );
		return `<svg class="afp-ficon" viewBox="0 0 48 60" aria-hidden="true"><path d="M6 1h26l15 15v38a5 5 0 0 1-5 5H6a5 5 0 0 1-5-5V6a5 5 0 0 1 5-5z" fill="#fff" stroke="${ c }" stroke-width="2"/><path d="M32 1v10a5 5 0 0 0 5 5h10" fill="${ c }" fill-opacity=".18" stroke="${ c }" stroke-width="2"/><rect x="5" y="36" width="38" height="15" rx="3" fill="${ c }"/><text x="24" y="47.5" text-anchor="middle" font-family="system-ui,sans-serif" font-size="10" font-weight="700" fill="#fff">${ ext }</text></svg>`;
	};

	/** Replace <span data-icon="name"> placeholders rendered by PHP. */
	const hydrateIcons = ( ctx = document ) => {
		ctx.querySelectorAll( '[data-icon]' ).forEach( ( el ) => {
			el.outerHTML = icon( el.dataset.icon );
		} );
	};

	/* ---------- AJAX ---------- */
	const ajax = ( action, data = {} ) => {
		const fd = new FormData();
		const add = ( k, v ) => {
			if ( Array.isArray( v ) ) {
				v.forEach( ( x ) => fd.append( k + '[]', x ) );
			} else if ( v !== undefined && v !== null ) {
				fd.append( k, v );
			}
		};
		if ( data instanceof FormData ) {
			data.forEach( ( v, k ) => fd.append( k, v ) );
		} else {
			Object.keys( data ).forEach( ( k ) => add( k, data[ k ] ) );
		}
		fd.append( 'action', 'foldpress_' + action );
		fd.append( 'nonce', D.nonce );
		return fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
			.then( ( r ) => r.json().catch( () => ( { success: false, data: { message: T.networkError } } ) ) )
			.catch( () => ( { success: false, data: { message: T.networkError } } ) )
			.then( ( r ) => {
				if ( ! r || ! r.success ) {
					const err = new Error( ( r && r.data && r.data.message ) || T.error );
					err.data = ( r && r.data ) || {};
					throw err;
				}
				return r.data;
			} );
	};

	/* ---------- Toasts ---------- */
	const toast = ( msg, type = 'success' ) => {
		let wrap = document.querySelector( '.afp-toasts' );
		if ( ! wrap ) {
			wrap = document.createElement( 'div' );
			wrap.className = 'afp-toasts afp-toasts--' + ( D.toastPos || 'top-right' );
			wrap.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( wrap );
		}
		const el = document.createElement( 'div' );
		el.className = 'afp-toast afp-toast--' + type;
		el.innerHTML = `<span class="afp-toast__ic">${ icon( type === 'error' ? 'alert' : type === 'info' ? 'info' : 'check' ) }</span><span class="afp-toast__msg">${ esc( msg ) }</span><button type="button" class="afp-toast__x" aria-label="${ esc( T.close ) }">${ icon( 'x' ) }</button><i class="afp-toast__bar"></i>`;
		const kill = () => {
			el.classList.add( 'is-out' );
			setTimeout( () => el.remove(), 300 );
		};
		el.querySelector( '.afp-toast__x' ).addEventListener( 'click', kill );
		wrap.appendChild( el );
		setTimeout( kill, 4200 );
	};

	/* ---------- Modal (replaces alert / confirm / prompt) ---------- */
	const modal = ( opts ) =>
		new Promise( ( resolve ) => {
			const {
				title,
				subtitle = '',
				body = '',
				input = null,
				inputLabel = '',
				inputHint = '',
				confirmText = T.ok,
				cancelText = T.cancel,
				danger = false,
				iconName = 'info',
				tone = '',
				size = '',
				onOpen = null,
				collect = null,
				footer = true,
			} = opts;
			const ov = document.createElement( 'div' );
			ov.className = 'afp-modal-ov';
			const toneCls = danger ? 'is-danger' : tone ? 'is-' + tone : '';
			ov.innerHTML = `<div class="afp-modal ${ size ? 'afp-modal--' + size : '' }" role="dialog" aria-modal="true" aria-labelledby="afp-modal-t">
				<div class="afp-modal__head">
					<span class="afp-modal__ic ${ toneCls }">${ icon( iconName ) }</span>
					<div class="afp-modal__titles"><h2 id="afp-modal-t">${ esc( title ) }</h2>${ subtitle ? `<p>${ esc( subtitle ) }</p>` : '' }</div>
					<button type="button" class="afp-modal__x" data-x aria-label="${ esc( T.close ) }">${ icon( 'x' ) }</button>
				</div>
				<div class="afp-modal__body">${ body }${
					input !== null
						? `<label class="afp-field">${ inputLabel ? `<span class="afp-field__label">${ esc( inputLabel ) }</span>` : '' }<input type="text" class="afp-input afp-input--lg" data-in value="${ esc( input ) }" spellcheck="false">${ inputHint ? `<span class="afp-field__hint">${ esc( inputHint ) }</span>` : '' }</label>`
						: ''
				}</div>
				${
					footer
						? `<div class="afp-modal__foot"><span class="afp-modal__kbd">${ esc( T.enterHint ) }</span>${ cancelText ? `<button type="button" class="afp-btn" data-x>${ esc( cancelText ) }</button>` : '' }
				<button type="button" class="afp-btn ${ danger ? 'afp-btn--danger-solid' : 'afp-btn--primary' }" data-ok>${ esc( confirmText ) }</button></div>`
						: ''
				}</div>`;
			document.body.appendChild( ov );
			document.documentElement.classList.add( 'afp-modal-open' );
			const prev = document.activeElement;
			const inp = ov.querySelector( '[data-in]' );
			const done = ( val ) => {
				ov.classList.remove( 'is-in' );
				ov.classList.add( 'is-out' );
				document.removeEventListener( 'keydown', key, true );
				setTimeout( () => {
					ov.remove();
					if ( ! document.querySelector( '.afp-modal-ov' ) ) {
						document.documentElement.classList.remove( 'afp-modal-open' );
					}
				}, 180 );
				if ( prev && prev.focus ) {
					prev.focus( { preventScroll: true } );
				}
				resolve( val );
			};
			const shake = () => {
				const m = ov.querySelector( '.afp-modal' );
				m.classList.remove( 'is-shake' );
				void m.offsetWidth; // eslint-disable-line no-void
				m.classList.add( 'is-shake' );
			};
			const ok = () => {
				if ( collect ) {
					const v = collect( ov );
					if ( v === null || v === undefined ) {
						shake();
						return;
					}
					return done( v );
				}
				if ( inp ) {
					const v = inp.value.trim();
					if ( ! v ) {
						inp.focus();
						shake();
						return;
					}
					return done( v );
				}
				done( true );
			};
			const key = ( e ) => {
				const open = document.querySelectorAll( '.afp-modal-ov:not(.is-out)' );
				if ( ov !== open[ open.length - 1 ] ) {
					return;
				}
				if ( e.key === 'Escape' ) {
					e.stopPropagation();
					e.preventDefault();
					done( null );
				} else if ( e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && ! ( e.target.tagName === 'BUTTON' && ! e.target.hasAttribute( 'data-ok' ) ) ) {
					e.preventDefault();
					ok();
				} else if ( e.key === 'Tab' ) {
					const f = ov.querySelectorAll( 'button,input,select,textarea,a[href]' );
					const first = f[ 0 ];
					const last = f[ f.length - 1 ];
					if ( e.shiftKey && document.activeElement === first ) {
						e.preventDefault();
						last.focus();
					} else if ( ! e.shiftKey && document.activeElement === last ) {
						e.preventDefault();
						first.focus();
					}
				}
			};
			document.addEventListener( 'keydown', key, true );
			ov.addEventListener( 'mousedown', ( e ) => {
				if ( e.target === ov ) {
					done( null );
				}
			} );
			ov.addEventListener( 'click', ( e ) => {
				if ( e.target.closest( '[data-x]' ) ) {
					done( null );
				} else if ( e.target.closest( '[data-ok]' ) ) {
					ok();
				}
			} );
			if ( onOpen ) {
				onOpen( ov, done );
			}
			setTimeout( () => ov.classList.add( 'is-in' ), 40 );
			requestAnimationFrame( () => {
				ov.classList.add( 'is-in' );
				if ( inp ) {
					inp.focus();
					const dot = inp.value.lastIndexOf( '.' );
					inp.setSelectionRange( 0, dot > 0 ? dot : inp.value.length );
				} else {
					const okBtn = ov.querySelector( '[data-ok]' ) || ov.querySelector( 'button' );
					if ( okBtn ) {
						okBtn.focus();
					}
				}
			} );
		} );

	const alertBox = ( msg, type = 'error' ) =>
		modal( { title: type === 'error' ? T.error : T.success, body: `<p class="afp-modal__text">${ esc( msg ) }</p>`, cancelText: '', iconName: type === 'error' ? 'alert' : 'success', danger: type === 'error', tone: type === 'error' ? '' : 'ok' } );

	/* ---------- Clipboard ---------- */
	const copy = ( text ) => {
		const done = () => toast( T.copied );
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).then( done );
		}
		const t = document.createElement( 'textarea' );
		t.value = text;
		t.style.position = 'fixed';
		t.style.opacity = '0';
		document.body.appendChild( t );
		t.select();
		document.execCommand( 'copy' );
		t.remove();
		done();
		return Promise.resolve();
	};

	/* ---------- Document rendering for the viewer ---------- */
	const DOC_EXT = D.docExt || [];
	const isDoc = ( it ) => it && it.group !== 'image' && it.group !== 'video' && it.group !== 'audio' && it.ext !== 'pdf' && DOC_EXT.includes( it.ext );
	const colName = ( n ) => {
		let s = '';
		n += 1;
		while ( n > 0 ) {
			const m = ( n - 1 ) % 26;
			s = String.fromCharCode( 65 + m ) + s;
			n = Math.floor( ( n - 1 ) / 26 );
		}
		return s;
	};
	const md = ( src ) => {
		const inline = ( s ) =>
			s
				.replace( /`([^`]+)`/g, '<code>$1</code>' )
				.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' )
				.replace( /(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>' )
				.replace( /(^|\W)_([^_]+)_(?=\W|$)/g, '$1<em>$2</em>' )
				.replace( /\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener nofollow">$1</a>' );
		const lines = esc( src ).split( /\r?\n/ );
		let out = '';
		let list = '';
		let code = false;
		let para = [];
		const flush = () => {
			if ( para.length ) {
				out += `<p>${ inline( para.join( ' ' ) ) }</p>`;
				para = [];
			}
			if ( list ) {
				out += `</${ list }>`;
				list = '';
			}
		};
		lines.forEach( ( l ) => {
			if ( /^```/.test( l ) ) {
				flush();
				out += code ? '</code></pre>' : '<pre><code>';
				code = ! code;
				return;
			}
			if ( code ) {
				out += l + '\n';
				return;
			}
			let m;
			if ( ( m = l.match( /^(#{1,6})\s+(.*)$/ ) ) ) {
				flush();
				out += `<h${ m[ 1 ].length }>${ inline( m[ 2 ] ) }</h${ m[ 1 ].length }>`;
			} else if ( /^\s*([-*_])\s*\1\s*\1[\s\-*_]*$/.test( l ) ) {
				flush();
				out += '<hr>';
			} else if ( ( m = l.match( /^\s*[-*+]\s+(.*)$/ ) ) || ( m = l.match( /^\s*\d+[.)]\s+(.*)$/ ) ) ) {
				const tag = /^\s*\d/.test( l ) ? 'ol' : 'ul';
				if ( para.length ) {
					out += `<p>${ inline( para.join( ' ' ) ) }</p>`;
					para = [];
				}
				if ( list !== tag ) {
					if ( list ) {
						out += `</${ list }>`;
					}
					out += `<${ tag }>`;
					list = tag;
				}
				out += `<li>${ inline( m[ 1 ] ) }</li>`;
			} else if ( ( m = l.match( /^&gt;\s?(.*)$/ ) ) ) {
				flush();
				out += `<blockquote>${ inline( m[ 1 ] ) }</blockquote>`;
			} else if ( ! l.trim() ) {
				flush();
			} else {
				if ( list ) {
					out += `</${ list }>`;
					list = '';
				}
				para.push( l );
			}
		} );
		if ( code ) {
			out += '</code></pre>';
		}
		flush();
		return out;
	};
	const renderPreview = ( d, it ) => {
		const note = ( t ) => `<div class="afp-pv__note">${ icon( 'info' ) }<span>${ esc( t ) }</span></div>`;
		if ( d.kind === 'doc' ) {
			return `<div class="afp-pv afp-pv--doc"><article class="afp-pv__page">${ d.html || `<p class="afp-muted">${ esc( T.noPreview ) }</p>` }</article>${ note( T.quickNote ) }</div>`;
		}
		if ( d.kind === 'markdown' ) {
			return `<div class="afp-pv afp-pv--doc"><article class="afp-pv__page afp-pv__md">${ md( d.text || '' ) }</article>${ d.truncated ? note( T.truncated ) : '' }</div>`;
		}
		if ( d.kind === 'text' ) {
			const lines = String( d.text || '' ).split( /\r?\n/ );
			return `<div class="afp-pv afp-pv--text"><pre class="afp-pv__code">${ lines.map( ( l ) => `<span>${ esc( l ) || ' ' }</span>` ).join( '' ) }</pre>${ d.truncated ? note( T.truncated ) : '' }</div>`;
		}
		if ( d.kind === 'sheets' ) {
			const sheets = d.sheets || [];
			const table = ( s ) => {
				const cols = s.rows.reduce( ( m, r ) => Math.max( m, r.length ), 0 );
				return `<table class="afp-pv__table"><thead><tr><th></th>${ Array.from( { length: cols }, ( _, i ) => `<th>${ colName( i ) }</th>` ).join( '' ) }</tr></thead><tbody>${ s.rows
					.map( ( r, ri ) => `<tr><th>${ ri + 1 }</th>${ Array.from( { length: cols }, ( _, ci ) => `<td>${ esc( r[ ci ] ?? '' ) }</td>` ).join( '' ) }</tr>` )
					.join( '' ) }</tbody></table>`;
			};
			return `<div class="afp-pv afp-pv--sheet">${
				sheets.length > 1 ? `<div class="afp-pv__sheets">${ sheets.map( ( s, i ) => `<button type="button" class="${ i === 0 ? 'is-on' : '' }" data-sheet="${ i }">${ esc( s.name ) }</button>` ).join( '' ) }</div>` : ''
			}${ sheets.map( ( s, i ) => `<div class="afp-pv__scroll" data-sheet-body="${ i }" ${ i ? 'hidden' : '' }>${ table( s ) }</div>` ).join( '' ) }${ sheets.some( ( s ) => s.rows.length >= 300 ) ? note( fmtS( T.rowsShown, 300 ) ) : '' }</div>`;
		}
		if ( d.kind === 'slides' ) {
			const slides = d.slides || [];
			return `<div class="afp-pv afp-pv--slides">${ slides
				.map(
					( s, i ) => `<section class="afp-pv__slide"><span class="afp-pv__num">${ esc( T.slide ) } ${ i + 1 }</span>${
						s.length ? `<h3>${ esc( s[ 0 ] ) }</h3>${ s.length > 1 ? `<ul>${ s.slice( 1 ).map( ( p ) => `<li>${ esc( p ) }</li>` ).join( '' ) }</ul>` : '' }` : `<p class="afp-muted">${ esc( T.emptySlide ) }</p>`
					}</section>`
				)
				.join( '' ) }${ note( T.quickNote ) }</div>`;
		}
		if ( d.kind === 'zip' ) {
			const e = d.entries || [];
			return `<div class="afp-pv afp-pv--zip"><div class="afp-pv__ziphead">${ fileIcon( it ) }<div><strong>${ esc( it.filename ) }</strong><small>${ esc( fmtS( T.zipItems, e.length ) ) }</small></div></div><div class="afp-pv__ziplist">${ e
				.map( ( x ) => {
					const depth = ( x.name.replace( /\/$/, '' ).match( /\//g ) || [] ).length;
					const name = x.name.replace( /\/$/, '' ).split( '/' ).pop();
					const ext = name.includes( '.' ) ? name.split( '.' ).pop().toLowerCase() : '';
					return `<div class="afp-pv__zipitem" style="--d:${ depth }">${ x.dir ? icon( 'folder', 'afp-pv__zipdir' ) : `<span class="afp-pv__zipext">${ esc( ( ext || '?' ).slice( 0, 4 ).toUpperCase() ) }</span>` }<span>${ esc( name ) }</span><small>${ x.dir ? '' : esc( sizeHuman( x.size ) ) }</small></div>`;
				} )
				.join( '' ) }</div></div>`;
		}
		return null;
	};
	const sizeHuman = ( b ) => ( b < 1024 ? b + ' B' : b < 1048576 ? ( b / 1024 ).toFixed( 1 ) + ' KB' : ( b / 1048576 ).toFixed( 1 ) + ' MB' );
	const fmtS = ( s, v ) => String( s || '' ).replace( /%[sd]/, v );

	/* ---------- Media player (video + audio, custom controls, audio visualizer) ---------- */
	const tFmt = ( s ) => {
		if ( ! isFinite( s ) || s < 0 ) {
			return '0:00';
		}
		s = Math.floor( s );
		const h = Math.floor( s / 3600 );
		const m = Math.floor( ( s % 3600 ) / 60 );
		const sec = String( s % 60 ).padStart( 2, '0' );
		return h ? `${ h }:${ String( m ).padStart( 2, '0' ) }:${ sec }` : `${ m }:${ sec }`;
	};
	const SPEEDS = [ 0.5, 0.75, 1, 1.25, 1.5, 2 ];
	const createPlayer = ( it, src ) => {
		const video = it.group === 'video';
		const wrap = document.createElement( 'div' );
		wrap.className = 'afp-player ' + ( video ? 'afp-player--video' : 'afp-player--audio' );
		wrap.tabIndex = -1;
		wrap.innerHTML = `
			${
				video
					? `<video class="afp-player__media" src="${ esc( src ) }" playsinline preload="metadata"></video><button type="button" class="afp-player__big" data-p="toggle" aria-label="Play">${ icon( 'play' ) }</button><span class="afp-player__spin"></span>`
					: `<div class="afp-player__art"><div class="afp-player__cover">${ fileIcon( it ) }<span class="afp-player__pulse"></span></div><div class="afp-player__meta"><strong>${ esc( it.title ) }</strong><small>${ esc( [ it.filename, it.sizeH ].filter( Boolean ).join( '  ·  ' ) ) }</small></div><canvas class="afp-player__viz" width="720" height="140"></canvas></div><audio class="afp-player__media" src="${ esc( src ) }" preload="metadata"></audio>`
			}
			<div class="afp-player__bar">
				<div class="afp-player__seek" data-p="seek" role="slider" aria-label="Seek" tabindex="0"><i class="afp-player__buf"></i><i class="afp-player__fill"></i><i class="afp-player__knob"></i><span class="afp-player__tip">0:00</span></div>
				<div class="afp-player__row">
					<button type="button" data-p="toggle" class="afp-player__play" aria-label="Play">${ icon( 'play' ) }</button>
					<button type="button" data-p="back" aria-label="-10s" title="-10s (J)">${ icon( 'back10' ) }</button>
					<button type="button" data-p="fwd" aria-label="+10s" title="+10s (L)">${ icon( 'fwd10' ) }</button>
					<span class="afp-player__time"><b data-p-cur>0:00</b> / <span data-p-dur>0:00</span></span>
					<span class="afp-player__sp"></span>
					<span class="afp-player__vol"><button type="button" data-p="mute" aria-label="Mute" title="M">${ icon( 'volume' ) }</button><input type="range" min="0" max="1" step="0.01" value="1" data-p-vol aria-label="Volume"></span>
					<button type="button" data-p="speed" class="afp-player__speed" title="Speed">1×</button>
					<button type="button" data-p="loop" aria-label="Loop" title="Loop">${ icon( 'loop' ) }</button>
					${ video && document.pictureInPictureEnabled ? `<button type="button" data-p="pip" aria-label="Picture in picture" title="PiP">${ icon( 'pip' ) }</button>` : '' }
					${ video ? `<button type="button" data-p="full" aria-label="Full screen" title="F">${ icon( 'expand' ) }</button>` : '' }
				</div>
			</div>`;
		const m = wrap.querySelector( '.afp-player__media' );
		const seek = wrap.querySelector( '[data-p="seek"]' );
		const q = ( s ) => wrap.querySelector( s );
		const setPlayIcon = () => {
			const ic = icon( m.paused ? 'play' : 'pause' );
			wrap.querySelectorAll( '[data-p="toggle"]' ).forEach( ( b ) => ( b.innerHTML = ic ) );
			wrap.classList.toggle( 'is-playing', ! m.paused );
		};
		const paint = () => {
			const d = m.duration || 0;
			const p = d ? ( m.currentTime / d ) * 100 : 0;
			q( '.afp-player__fill' ).style.width = p + '%';
			q( '.afp-player__knob' ).style.left = p + '%';
			q( '[data-p-cur]' ).textContent = tFmt( m.currentTime );
			q( '[data-p-dur]' ).textContent = tFmt( d );
			seek.setAttribute( 'aria-valuenow', Math.round( p ) );
		};
		const buffered = () => {
			try {
				const b = m.buffered;
				if ( b.length && m.duration ) {
					q( '.afp-player__buf' ).style.width = ( b.end( b.length - 1 ) / m.duration ) * 100 + '%';
				}
			} catch ( e ) {}
		};
		const toggle = () => ( m.paused ? m.play().catch( () => {} ) : m.pause() );
		const jump = ( s ) => {
			m.currentTime = Math.max( 0, Math.min( m.duration || 0, m.currentTime + s ) );
		};
		const setVol = ( v ) => {
			m.volume = Math.max( 0, Math.min( 1, v ) );
			m.muted = m.volume === 0;
			q( '[data-p-vol]' ).value = m.muted ? 0 : m.volume;
			q( '[data-p="mute"]' ).innerHTML = icon( m.muted ? 'mute' : 'volume' );
		};
		const full = () => {
			if ( document.fullscreenElement ) {
				document.exitFullscreen();
			} else if ( wrap.requestFullscreen ) {
				wrap.requestFullscreen();
			}
		};
		// Seek bar dragging + hover tooltip.
		const posToTime = ( x ) => {
			const r = seek.getBoundingClientRect();
			return Math.max( 0, Math.min( 1, ( x - r.left ) / r.width ) ) * ( m.duration || 0 );
		};
		let dragging = false;
		seek.addEventListener( 'pointerdown', ( e ) => {
			dragging = true;
			seek.setPointerCapture( e.pointerId );
			m.currentTime = posToTime( e.clientX );
		} );
		seek.addEventListener( 'pointermove', ( e ) => {
			const tip = q( '.afp-player__tip' );
			const r = seek.getBoundingClientRect();
			tip.textContent = tFmt( posToTime( e.clientX ) );
			tip.style.left = Math.max( 0, Math.min( r.width, e.clientX - r.left ) ) + 'px';
			if ( dragging ) {
				m.currentTime = posToTime( e.clientX );
			}
		} );
		seek.addEventListener( 'pointerup', () => ( dragging = false ) );
		seek.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'ArrowLeft' || e.key === 'ArrowRight' ) {
				e.preventDefault();
				e.stopPropagation();
				jump( e.key === 'ArrowLeft' ? -5 : 5 );
			}
		} );
		wrap.addEventListener( 'click', ( e ) => {
			const b = e.target.closest( '[data-p]' );
			if ( ! b ) {
				if ( video && e.target === m ) {
					toggle();
				}
				return;
			}
			const a = b.dataset.p;
			if ( a === 'toggle' ) {
				toggle();
			} else if ( a === 'back' ) {
				jump( -10 );
			} else if ( a === 'fwd' ) {
				jump( 10 );
			} else if ( a === 'mute' ) {
				if ( m.muted || m.volume === 0 ) {
					setVol( wrap.lastVol || 1 );
				} else {
					wrap.lastVol = m.volume;
					setVol( 0 );
				}
			} else if ( a === 'speed' ) {
				const next = SPEEDS[ ( SPEEDS.indexOf( m.playbackRate ) + 1 ) % SPEEDS.length ];
				m.playbackRate = next;
				b.textContent = next + '×';
				b.classList.toggle( 'is-on', next !== 1 );
			} else if ( a === 'loop' ) {
				m.loop = ! m.loop;
				b.classList.toggle( 'is-on', m.loop );
			} else if ( a === 'pip' && m.requestPictureInPicture ) {
				( document.pictureInPictureElement ? document.exitPictureInPicture() : m.requestPictureInPicture() ).catch( () => {} );
			} else if ( a === 'full' ) {
				full();
			}
		} );
		if ( video ) {
			m.addEventListener( 'dblclick', full );
		}
		q( '[data-p-vol]' ).addEventListener( 'input', ( e ) => setVol( +e.target.value ) );
		[ 'timeupdate', 'durationchange', 'loadedmetadata', 'seeked' ].forEach( ( ev ) => m.addEventListener( ev, paint ) );
		m.addEventListener( 'progress', buffered );
		m.addEventListener( 'play', setPlayIcon );
		m.addEventListener( 'pause', setPlayIcon );
		m.addEventListener( 'ended', setPlayIcon );
		m.addEventListener( 'waiting', () => wrap.classList.add( 'is-waiting' ) );
		m.addEventListener( 'playing', () => wrap.classList.remove( 'is-waiting' ) );
		// Auto-hide controls on video while playing.
		let idle;
		const wake = () => {
			wrap.classList.remove( 'is-idle' );
			clearTimeout( idle );
			idle = setTimeout( () => ! m.paused && wrap.classList.add( 'is-idle' ), 2500 );
		};
		if ( video ) {
			wrap.addEventListener( 'pointermove', wake );
		}

		// Audio spectrum visualizer (Web Audio). Falls back to the animated cover if unavailable.
		let actx = null;
		let raf = null;
		const startViz = () => {
			if ( video || actx ) {
				return;
			}
			const Ctx = window.AudioContext || window.webkitAudioContext;
			const cv = q( '.afp-player__viz' );
			if ( ! Ctx || ! cv ) {
				return;
			}
			try {
				actx = new Ctx();
				const srcNode = actx.createMediaElementSource( m );
				const an = actx.createAnalyser();
				an.fftSize = 128;
				srcNode.connect( an );
				an.connect( actx.destination );
				const data = new Uint8Array( an.frequencyBinCount );
				const g = cv.getContext( '2d' );
				const draw = () => {
					raf = requestAnimationFrame( draw );
					an.getByteFrequencyData( data );
					const w = cv.width;
					const h = cv.height;
					g.clearRect( 0, 0, w, h );
					const n = 48;
					const bw = w / n;
					const grad = g.createLinearGradient( 0, h, 0, 0 );
					grad.addColorStop( 0, '#6366f1' );
					grad.addColorStop( 1, '#ec4899' );
					g.fillStyle = grad;
					for ( let i = 0; i < n; i++ ) {
						const v = data[ Math.floor( ( i / n ) * data.length * 0.8 ) ] / 255;
						const bh = Math.max( 3, v * h );
						const x = i * bw + 2;
						const r = Math.min( 4, ( bw - 4 ) / 2 );
						g.beginPath();
						if ( g.roundRect ) {
							g.roundRect( x, h - bh, bw - 4, bh, r );
						} else {
							g.rect( x, h - bh, bw - 4, bh );
						}
						g.fill();
					}
				};
				draw();
				wrap.classList.add( 'has-viz' );
			} catch ( e ) {
				actx = null;
			}
		};
		m.addEventListener( 'play', () => {
			startViz();
			if ( actx && actx.state === 'suspended' ) {
				actx.resume();
			}
		} );
		wrap.afpKey = ( e ) => {
			const k = e.key.toLowerCase();
			if ( k === ' ' || k === 'k' ) {
				toggle();
			} else if ( k === 'j' ) {
				jump( -10 );
			} else if ( k === 'l' ) {
				jump( 10 );
			} else if ( k === 'arrowup' ) {
				setVol( m.volume + 0.1 );
			} else if ( k === 'arrowdown' ) {
				setVol( m.volume - 0.1 );
			} else if ( k === 'm' ) {
				q( '[data-p="mute"]' ).click();
			} else if ( k === 'f' && video ) {
				full();
			} else {
				return false;
			}
			e.preventDefault();
			return true;
		};
		wrap.afpDestroy = () => {
			m.pause();
			cancelAnimationFrame( raf );
			clearTimeout( idle );
			if ( actx ) {
				actx.close().catch( () => {} );
			}
			m.removeAttribute( 'src' );
			m.load();
		};
		m.addEventListener( 'error', () => wrap.afpError && wrap.afpError() );
		setTimeout( () => m.play().catch( () => setPlayIcon() ), 60 );
		return wrap;
	};

	/* ---------- Lightbox (FoldPress own viewer, no third-party code) ---------- */
	const previewable = ( it ) => it && ( [ 'image', 'video', 'audio', 'pdf' ].includes( it.group ) || it.ext === 'pdf' || isDoc( it ) );
	let lb = null;
	const lightbox = ( items, index = 0 ) => {
		const list = items.filter( previewable );
		if ( ! list.length ) {
			return false;
		}
		let cur = Math.max( 0, list.indexOf( items[ index ] ) );
		if ( ! D.lightbox ) {
			window.open( list[ cur ].full || list[ cur ].url, '_blank', 'noopener' );
			return true;
		}
		if ( lb ) {
			lb.close( true );
		}
		const prevFocus = document.activeElement;
		const el = document.createElement( 'div' );
		el.className = 'afp-lb';
		el.setAttribute( 'role', 'dialog' );
		el.setAttribute( 'aria-modal', 'true' );
		el.setAttribute( 'aria-label', T.preview || 'Preview' );
		el.innerHTML = `
			<div class="afp-lb__top">
				<span class="afp-lb__count" data-lb-count></span>
				<div class="afp-lb__title"><strong data-lb-title></strong><small data-lb-meta></small></div>
				<div class="afp-lb__acts">
					<span class="afp-lb__seg" data-lb-mode hidden><button type="button" data-lb="quick" class="is-on">${ esc( T.quickView ) }</button><button type="button" data-lb="office">${ esc( T.fullLayout ) }</button></span>
					<button type="button" class="afp-lb__btn" data-lb="zoom" aria-label="Zoom" title="Zoom (Z)">${ icon( 'search' ) }</button>
					<a class="afp-lb__btn" data-lb-dl download aria-label="${ esc( T.download || 'Download' ) }" title="${ esc( T.download || 'Download' ) }">${ icon( 'download' ) }</a>
					<a class="afp-lb__btn" data-lb-open target="_blank" rel="noopener" aria-label="${ esc( T.openNewTab || 'Open' ) }" title="${ esc( T.openNewTab || 'Open' ) }">${ icon( 'external' ) }</a>
					<button type="button" class="afp-lb__btn" data-lb="close" aria-label="${ esc( T.close ) }" title="${ esc( T.close ) } (Esc)">${ icon( 'x' ) }</button>
				</div>
			</div>
			<div class="afp-lb__main">
				<button type="button" class="afp-lb__nav is-prev" data-lb="prev" aria-label="Previous">${ icon( 'chevronL' ) }</button>
				<div class="afp-lb__stage" data-lb-stage></div>
				<button type="button" class="afp-lb__nav is-next" data-lb="next" aria-label="Next">${ icon( 'chevron' ) }</button>
			</div>
			<div class="afp-lb__thumbs" data-lb-thumbs>${
				list.length > 1
					? list
							.map(
								( it, i ) =>
									`<button type="button" class="afp-lb__thumb" data-lb-go="${ i }" aria-label="${ esc( it.title ) }">${ it.thumb ? `<img src="${ esc( it.thumb ) }" alt="" loading="lazy">` : fileIcon( it ) }</button>`
							)
							.join( '' )
					: ''
			}</div>`;
		document.body.appendChild( el );
		document.documentElement.classList.add( 'afp-modal-open' );
		const stage = el.querySelector( '[data-lb-stage]' );

		const show = ( i ) => {
			cur = ( i + list.length ) % list.length;
			const it = list[ cur ];
			const src = it.full || it.url;
			el.querySelector( '[data-lb-count]' ).textContent = list.length > 1 ? `${ cur + 1 } / ${ list.length }` : '';
			el.querySelector( '[data-lb-title]' ).textContent = it.title || it.filename || '';
			el.querySelector( '[data-lb-meta]' ).textContent = [ it.filename, it.dims, it.sizeH ].filter( Boolean ).join( '  ·  ' );
			el.querySelector( '[data-lb-dl]' ).href = src;
			el.querySelector( '[data-lb-dl]' ).setAttribute( 'download', it.filename || '' );
			el.querySelector( '[data-lb-open]' ).href = src;
			el.querySelector( '[data-lb="zoom"]' ).hidden = it.group !== 'image';
			el.querySelector( '[data-lb-mode]' ).hidden = true;
			el.classList.toggle( 'is-single', list.length < 2 );
			el.classList.remove( 'is-zoom' );
			el.querySelectorAll( '[data-lb-go]' ).forEach( ( b ) => b.classList.toggle( 'is-cur', +b.dataset.lbGo === cur ) );
			const active = el.querySelector( '.afp-lb__thumb.is-cur' );
			if ( active ) {
				active.scrollIntoView( { block: 'nearest', inline: 'center' } );
			}
			if ( stage.afpPlayer ) {
				stage.afpPlayer.afpDestroy();
				stage.afpPlayer = null;
			}
			stage.afpViews = null;
			stage.className = 'afp-lb__stage is-loading';
			stage.innerHTML = '<span class="afp-lb__spin"></span>';
			const fail = () => {
				stage.className = 'afp-lb__stage is-error';
				stage.innerHTML = `<div class="afp-lb__err">${ icon( 'alert' ) }<strong>${ esc( T.previewError || 'Preview could not be loaded.' ) }</strong><a class="afp-btn" href="${ esc( src ) }" target="_blank" rel="noopener">${ icon( 'external' ) }<span>${ esc( T.openNewTab || 'Open' ) }</span></a></div>`;
			};
			if ( it.group === 'image' ) {
				const img = new Image();
				img.decoding = 'async';
				img.alt = it.alt || '';
				img.className = 'afp-lb__media';
				img.onload = () => {
					if ( list[ cur ] !== it ) {
						return;
					}
					stage.className = 'afp-lb__stage is-ready';
					stage.innerHTML = '';
					stage.appendChild( img );
				};
				img.onerror = fail;
				img.src = src;
			} else if ( it.group === 'video' || it.group === 'audio' ) {
				stage.className = 'afp-lb__stage is-ready is-player';
				stage.innerHTML = '';
				const pl = createPlayer( it, src );
				pl.afpError = fail;
				stage.appendChild( pl );
				stage.afpPlayer = pl;
			} else if ( isDoc( it ) ) {
				const mode = el.querySelector( '[data-lb-mode]' );
				ajax( 'preview', { id: it.id } )
					.then( ( d ) => {
						if ( list[ cur ] !== it ) {
							return;
						}
						const quick = renderPreview( d, it );
						const office = d.office ? `<iframe class="afp-lb__doc afp-lb__office" src="${ esc( d.office ) }" title="${ esc( it.title ) }"></iframe>` : '';
						if ( ! quick && ! office ) {
							return fail();
						}
						mode.hidden = ! ( quick && office );
						mode.querySelectorAll( 'button' ).forEach( ( b ) => b.classList.toggle( 'is-on', b.dataset.lb === ( quick ? 'quick' : 'office' ) ) );
						stage.className = 'afp-lb__stage is-ready is-docview';
						stage.innerHTML = quick || office;
						stage.afpViews = { quick, office };
					} )
					.catch( fail );
			} else {
				stage.className = 'afp-lb__stage is-ready is-doc';
				stage.innerHTML = `<iframe class="afp-lb__doc" src="${ esc( src ) }" title="${ esc( it.title ) }"></iframe>`;
			}
			// Preload neighbours.
			[ cur + 1, cur - 1 ].forEach( ( n ) => {
				const nb = list[ ( n + list.length ) % list.length ];
				if ( nb && nb.group === 'image' ) {
					new Image().src = nb.full || nb.url;
				}
			} );
		};
		const close = ( instant ) => {
			document.removeEventListener( 'keydown', key, true );
			if ( stage.afpPlayer ) {
				stage.afpPlayer.afpDestroy();
			}
			if ( document.fullscreenElement && el.contains( document.fullscreenElement ) ) {
				document.exitFullscreen().catch( () => {} );
			}
			el.classList.add( 'is-out' );
			setTimeout( () => el.remove(), instant ? 0 : 200 );
			if ( ! document.querySelector( '.afp-modal-ov' ) ) {
				document.documentElement.classList.remove( 'afp-modal-open' );
			}
			if ( prevFocus && prevFocus.focus ) {
				prevFocus.focus( { preventScroll: true } );
			}
			lb = null;
		};
		const key = ( e ) => {
			if ( stage.afpPlayer && ! e.ctrlKey && ! e.metaKey && ! /INPUT|TEXTAREA/.test( ( e.target || {} ).tagName || '' ) && stage.afpPlayer.afpKey( e ) ) {
				e.stopPropagation();
				return;
			}
			if ( e.key === 'Escape' && document.fullscreenElement ) {
				return;
			}
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				e.stopPropagation();
				close();
			} else if ( e.key === 'ArrowRight' ) {
				e.preventDefault();
				show( cur + 1 );
			} else if ( e.key === 'ArrowLeft' ) {
				e.preventDefault();
				show( cur - 1 );
			} else if ( ( e.key.toLowerCase() === 'z' || e.key === '+' || e.key === '-' ) && list[ cur ].group === 'image' ) {
				el.classList.toggle( 'is-zoom', e.key === '-' ? false : e.key === '+' ? true : undefined );
			}
		};
		document.addEventListener( 'keydown', key, true );
		el.addEventListener( 'click', ( e ) => {
			const b = e.target.closest( '[data-lb]' );
			const go = e.target.closest( '[data-lb-go]' );
			if ( b ) {
				const a = b.dataset.lb;
				if ( a === 'close' ) {
					close();
				} else if ( a === 'next' ) {
					show( cur + 1 );
				} else if ( a === 'prev' ) {
					show( cur - 1 );
				} else if ( a === 'zoom' ) {
					el.classList.toggle( 'is-zoom' );
				} else if ( ( a === 'quick' || a === 'office' ) && stage.afpViews && stage.afpViews[ a ] ) {
					stage.innerHTML = stage.afpViews[ a ];
					el.querySelectorAll( '[data-lb-mode] button' ).forEach( ( x ) => x.classList.toggle( 'is-on', x === b ) );
				}
			} else if ( e.target.closest( '[data-sheet]' ) ) {
				const sb = e.target.closest( '[data-sheet]' );
				stage.querySelectorAll( '[data-sheet]' ).forEach( ( x ) => x.classList.toggle( 'is-on', x === sb ) );
				stage.querySelectorAll( '[data-sheet-body]' ).forEach( ( x ) => ( x.hidden = x.dataset.sheetBody !== sb.dataset.sheet ) );
			} else if ( go ) {
				show( +go.dataset.lbGo );
			} else if ( e.target.classList.contains( 'afp-lb__media' ) && list[ cur ].group === 'image' ) {
				el.classList.toggle( 'is-zoom' );
			} else if ( e.target === stage || e.target.classList.contains( 'afp-lb__main' ) ) {
				close();
			}
		} );
		// Swipe on touch devices.
		let tx = null;
		el.addEventListener( 'touchstart', ( e ) => ( tx = e.touches[ 0 ].clientX ), { passive: true } );
		el.addEventListener( 'touchend', ( e ) => {
			if ( tx === null || el.classList.contains( 'is-zoom' ) ) {
				return;
			}
			const dx = e.changedTouches[ 0 ].clientX - tx;
			if ( Math.abs( dx ) > 50 ) {
				show( cur + ( dx < 0 ? 1 : -1 ) );
			}
			tx = null;
		} );
		lb = { close };
		requestAnimationFrame( () => el.classList.add( 'is-in' ) );
		setTimeout( () => el.classList.add( 'is-in' ), 40 );
		show( cur );
		el.querySelector( '[data-lb="close"]' ).focus( { preventScroll: true } );
		return true;
	};

	/* ---------- Rename history (shared by manager + editor) ---------- */
	const historyModal = ( id ) =>
		ajax( 'history', { id } )
			.then( ( d ) => {
				const via = T.via || {};
				const list = d.history || [];
				const cur = d.current || {};
				let chosen = null;
				const body = list.length
					? `<div class="afp-hist">
						<div class="afp-hist__now">${ icon( 'check' ) }<div><small>${ esc( T.currentVersion ) }</small><strong>${ esc( cur.title ) }</strong><span>${ esc( cur.stem + '.' + cur.ext ) }</span></div></div>
						<ol class="afp-hist__list">${ list
							.map(
								( e ) => `<li><button type="button" class="afp-hist__item" data-hist="${ e.index }">
									<span class="afp-hist__dot"></span>
									<span class="afp-hist__body">
										<span class="afp-hist__meta"><b>${ esc( e.date ) }</b><em>${ esc( e.user ) }</em><i class="afp-hist__via">${ esc( via[ e.via ] || e.via ) }</i></span>
										${ e.oldTitle !== e.newTitle ? `<span class="afp-hist__row"><small>${ esc( T.title ) }</small><s>${ esc( e.oldTitle ) }</s>${ icon( 'arrowRight' ) }<span>${ esc( e.newTitle ) }</span></span>` : '' }
										${ e.physical ? `<span class="afp-hist__row is-file"><small>${ esc( T.fileName ) }</small><s>${ esc( e.oldFile ) }</s>${ icon( 'arrowRight' ) }<span>${ esc( e.newFile ) }</span></span>` : '' }
									</span>
									<span class="afp-hist__go">${ icon( 'reset' ) }<span>${ esc( T.restoreThis ) }</span></span>
								</button></li>`
							)
							.join( '' ) }</ol>
						<p class="afp-field__hint">${ icon( 'info' ) }${ esc( T.historyHint ) }</p>
					</div>`
					: `<div class="afp-hist__empty">${ icon( 'clock' ) }<strong>${ esc( T.historyEmpty ) }</strong></div>`;
				return modal( {
					title: T.history,
					subtitle: cur.stem ? cur.stem + '.' + cur.ext : '',
					iconName: 'clock',
					size: 'md',
					body,
					confirmText: list.length ? T.restoreThis : T.close,
					cancelText: list.length ? T.cancel : '',
					onOpen: ( ov ) => {
						const ok = ov.querySelector( '[data-ok]' );
						if ( list.length ) {
							ok.disabled = true;
						}
						ov.querySelectorAll( '[data-hist]' ).forEach( ( b ) =>
							b.addEventListener( 'click', () => {
								ov.querySelectorAll( '.is-on' ).forEach( ( x ) => x.classList.remove( 'is-on' ) );
								b.classList.add( 'is-on' );
								chosen = +b.dataset.hist;
								ok.disabled = false;
							} )
						);
					},
					collect: () => ( list.length ? chosen : true ),
				} ).then( ( index ) => {
					if ( index === null || index === true || index === undefined ) {
						return null;
					}
					return ajax( 'history_restore', { id, index } ).then( ( r ) => {
						toast( r.message );
						return r.item;
					} );
				} );
			} )
			.catch( ( e ) => {
				alertBox( e.message );
				return null;
			} );

	/* ---------- Dropdown (replaces native <select> look, keeps the select for forms) ---------- */
	let ddOpen = null;
	const ddClose = () => {
		if ( ddOpen ) {
			ddOpen.menu.remove();
			ddOpen.btn.setAttribute( 'aria-expanded', 'false' );
			ddOpen.wrap.classList.remove( 'is-open' );
			ddOpen = null;
		}
	};
	const ddLabel = ( sel ) => {
		const o = sel.options[ sel.selectedIndex ];
		return o ? o.textContent.replace( /^[\s └]+/, '' ) : '';
	};
	const syncSelect = ( sel ) => {
		const w = sel && sel.afpDd;
		if ( w ) {
			w.querySelector( '.afp-dd__val' ).textContent = ddLabel( sel );
			w.classList.toggle( 'is-disabled', sel.disabled );
		}
	};
	const enhanceSelect = ( sel ) => {
		if ( ! sel || sel.afpDd || sel.multiple ) {
			return;
		}
		const wrap = document.createElement( 'span' );
		wrap.className = 'afp-dd';
		const w = sel.getBoundingClientRect().width;
		if ( w && ! sel.closest( '.afp-field' ) ) {
			wrap.style.minWidth = Math.ceil( w ) + 'px';
		}
		wrap.innerHTML = `<button type="button" class="afp-dd__btn" aria-haspopup="listbox" aria-expanded="false"><span class="afp-dd__val"></span>${ icon( 'chevronDown', 'afp-dd__chev' ) }</button>`;
		sel.parentNode.insertBefore( wrap, sel );
		wrap.appendChild( sel );
		sel.classList.add( 'afp-dd__native' );
		sel.tabIndex = -1;
		sel.setAttribute( 'aria-hidden', 'true' );
		sel.afpDd = wrap;
		const btn = wrap.querySelector( '.afp-dd__btn' );
		if ( sel.getAttribute( 'aria-label' ) ) {
			btn.setAttribute( 'aria-label', sel.getAttribute( 'aria-label' ) );
		}
		syncSelect( sel );
		sel.addEventListener( 'change', () => syncSelect( sel ) );

		const open = () => {
			if ( sel.disabled ) {
				return;
			}
			ddClose();
			const menu = document.createElement( 'div' );
			menu.className = 'afp-dd__menu';
			menu.setAttribute( 'role', 'listbox' );
			const opts = Array.from( sel.options );
			const many = opts.length > 9;
			menu.innerHTML =
				( many ? `<label class="afp-dd__search">${ icon( 'search' ) }<input type="search" autocomplete="off" aria-label="Search"></label>` : '' ) +
				'<div class="afp-dd__list">' +
				opts
					.map( ( o, i ) => {
						const raw = o.textContent;
						const depth = ( raw.match( /^[ \s]*/ )[ 0 ].length / 3 ) | 0;
						const sub = /└/.test( raw );
						return `<button type="button" role="option" class="afp-dd__opt ${ o.selected ? 'is-on' : '' }" data-i="${ i }" ${ o.disabled ? 'disabled' : '' } aria-selected="${ o.selected }" style="--d:${ depth }">${ sub ? icon( 'subdir', 'afp-dd__sub' ) : '' }<span>${ esc( raw.replace( /^[\s └]+/, '' ) ) }</span>${ icon( 'check', 'afp-dd__tick' ) }</button>`;
					} )
					.join( '' ) +
				'</div>';
			( wrap.closest( '.afp-modal-ov' ) || document.body ).appendChild( menu );
			const r = btn.getBoundingClientRect();
			menu.style.minWidth = r.width + 'px';
			const mh = Math.min( menu.offsetHeight, 320 );
			const below = window.innerHeight - r.bottom - 8;
			menu.style.left = Math.max( 8, Math.min( r.left, window.innerWidth - menu.offsetWidth - 8 ) ) + 'px';
			if ( below < mh && r.top > below ) {
				menu.style.top = Math.max( 8, r.top - mh - 6 ) + 'px';
				menu.classList.add( 'is-up' );
			} else {
				menu.style.top = r.bottom + 6 + 'px';
			}
			ddOpen = { menu, btn, wrap };
			wrap.classList.add( 'is-open' );
			btn.setAttribute( 'aria-expanded', 'true' );
			const items = () => Array.from( menu.querySelectorAll( '.afp-dd__opt:not([hidden]):not([disabled])' ) );
			const choose = ( i ) => {
				sel.selectedIndex = i;
				sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				ddClose();
				btn.focus();
			};
			menu.addEventListener( 'click', ( e ) => {
				const o = e.target.closest( '.afp-dd__opt' );
				if ( o ) {
					choose( +o.dataset.i );
				}
			} );
			menu.addEventListener( 'keydown', ( e ) => {
				const list = items();
				const at = list.indexOf( document.activeElement );
				if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
					e.preventDefault();
					const n = list[ Math.max( 0, Math.min( list.length - 1, at + ( e.key === 'ArrowDown' ? 1 : -1 ) ) ) ];
					if ( n ) {
						n.focus();
					}
				} else if ( e.key === 'Escape' ) {
					e.preventDefault();
					e.stopPropagation();
					ddClose();
					btn.focus();
				} else if ( e.key === 'Tab' ) {
					ddClose();
				}
			} );
			const search = menu.querySelector( 'input' );
			if ( search ) {
				search.addEventListener( 'input', () => {
					const q = search.value.trim().toLowerCase();
					menu.querySelectorAll( '.afp-dd__opt' ).forEach( ( o ) => ( o.hidden = q && ! o.textContent.toLowerCase().includes( q ) ) );
				} );
				search.addEventListener( 'keydown', ( e ) => {
					if ( e.key === 'ArrowDown' ) {
						e.preventDefault();
						const f = items()[ 0 ];
						if ( f ) {
							f.focus();
						}
					} else if ( e.key === 'Enter' ) {
						e.preventDefault();
						const f = items()[ 0 ];
						if ( f ) {
							choose( +f.dataset.i );
						}
					}
				} );
				search.focus();
			} else {
				const on = menu.querySelector( '.afp-dd__opt.is-on' ) || items()[ 0 ];
				if ( on ) {
					on.focus();
					on.scrollIntoView( { block: 'nearest' } );
				}
			}
		};
		btn.addEventListener( 'click', () => ( ddOpen && ddOpen.wrap === wrap ? ddClose() : open() ) );
		btn.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				open();
			}
		} );
	};
	const enhanceSelects = ( ctx = document ) => ctx.querySelectorAll( 'select.afp-select' ).forEach( enhanceSelect );
	document.addEventListener( 'mousedown', ( e ) => ddOpen && ! ddOpen.menu.contains( e.target ) && ! ddOpen.wrap.contains( e.target ) && ddClose() );
	window.addEventListener( 'resize', ddClose );
	document.addEventListener( 'scroll', ( e ) => ddOpen && ! ddOpen.menu.contains( e.target ) && ddClose(), true );

	window.FoldPressUI = { esc, icon, P, GROUP_COLOR, fileIcon, hydrateIcons, ajax, toast, modal, alertBox, copy, lightbox, previewable, enhanceSelects, syncSelect, historyModal, T };
} )();
