/**
 * FoldPress settings page (AJAX).
 */
( function () {
	'use strict';

	const UI = window.FoldPressUI;
	const D = window.FoldPressSettings || {};
	const T = D.i18n || {};
	const form = document.getElementById( 'afp-settings' );
	if ( ! form || ! UI ) {
		return;
	}
	const { esc, icon, ajax, toast, modal, alertBox } = UI;
	UI.hydrateIcons( form );
	UI.enhanceSelects( form );

	const dirtyBar = form.querySelector( '.afp-set__dirty' );
	let dirty = false;
	/* Snapshot of every named control, used for "what changed" and Cancel. */
	let removedTypes = [];
	const snapshot = () => {
		const m = new Map();
		Array.from( form.elements ).forEach( ( el ) => {
			if ( ! el.name ) {
				return;
			}
			const key = el.type === 'checkbox' || el.type === 'radio' ? el.name + '=' + el.value : el.name;
			m.set( key, { el, v: el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value } );
		} );
		return m;
	};
	let saved = snapshot();
	const txtOf = ( n ) => ( n ? n.textContent.replace( /\s+/g, ' ' ).trim() : '' );
	const cut = ( s ) => ( s.length > 48 ? s.slice( 0, 47 ) + '\u2026' : s );
	/* Human label for a control: "Tab \u203a Card / row \u203a Field", never the raw input name. */
	const labelOf = ( el ) => {
		const panel = el.closest( '.afp-set__panel' );
		const sec = panel ? txtOf( panel.querySelector( 'h2' ) ) : '';
		const type = el.closest( '.afp-type' );
		const cloud = el.closest( '.afp-cloud' );
		const row = el.closest( '.afp-set-row' );
		const field = el.closest( '.afp-field' );
		let name = '';
		if ( type ) {
			name = '.' + txtOf( type.querySelector( '.afp-type__ext' ) ).split( ',' )[ 0 ];
		} else if ( cloud ) {
			const c = txtOf( cloud.querySelector( '.afp-cloud__txt strong' ) );
			name = field ? c + ' \u203a ' + txtOf( field.querySelector( '.afp-field__label' ) ) : c;
		} else if ( row ) {
			name = txtOf( row.querySelector( '.afp-set-row__txt strong' ) );
		} else if ( field ) {
			name = txtOf( field.querySelector( '.afp-field__label' ) );
		} else {
			const l = el.closest( 'label' ) || ( el.id && form.querySelector( `label[for="${ el.id }"]` ) );
			name = l ? cut( txtOf( l ) ) : '';
		}
		if ( ! name ) {
			name = el.getAttribute( 'aria-label' ) || el.getAttribute( 'placeholder' ) || T.changedWord;
		}
		return sec && sec !== name ? sec + ' \u203a ' + name : name;
	};
	const valueOf = ( el, v ) => {
		if ( el.type === 'checkbox' ) {
			return v ? T.enabledWord : T.disabledWord;
		}
		if ( el.type === 'password' ) {
			return T.changedWord;
		}
		if ( el.type === 'radio' ) {
			const l = el.closest( 'label' );
			const strong = l && l.querySelector( 'strong, b' );
			return cut( strong ? txtOf( strong ) : txtOf( l ) || el.value );
		}
		if ( el.tagName === 'SELECT' ) {
			const o = Array.from( el.options ).find( ( x ) => x.value === v );
			return o ? txtOf( o ) : v;
		}
		return v === '' ? T.emptyWord : cut( String( v ) );
	};
	/* Each change: { k: label, v: new value, o: old value (optional) }. */
	const changes = () => {
		const now = snapshot();
		const out = [];
		const seen = new Set();
		const push = ( c ) => {
			const id = c.k + '|' + c.v;
			if ( ! seen.has( id ) ) {
				seen.add( id );
				out.push( c );
			}
		};
		now.forEach( ( cur, key ) => {
			const before = saved.get( key );
			const el = cur.el;
			if ( ! before ) {
				if ( el.closest( '.afp-type.is-new' ) && /\[ext\]$/.test( el.name ) ) {
					push( { k: T.typeAdded, v: '.' + el.value } );
				}
				return;
			}
			if ( before.v === cur.v || ( el.type === 'radio' && ! cur.v ) ) {
				return;
			}
			const c = { k: labelOf( el ), v: valueOf( el, cur.v ) };
			if ( el.type !== 'checkbox' && el.type !== 'radio' && el.type !== 'password' ) {
				c.o = valueOf( el, before.v );
			}
			push( c );
		} );
		removedTypes.forEach( ( ext ) => push( { k: T.typeRemoved, v: '.' + ext } ) );
		return out;
	};
	const changeItem = ( c ) => `<li>${ icon( 'pencil' ) }<span class="afp-chg"><span class="afp-chg__k">${ esc( c.k ) }</span><span class="afp-chg__v">${ c.o !== undefined ? `<s title="${ esc( T.wasWord ) }">${ esc( c.o ) }</s>${ icon( 'arrowRight' ) }` : '' }<b>${ esc( c.v ) }</b></span></span></li>`;
	const setDirty = ( v ) => {
		if ( v ) {
			const list = changes();
			v = list.length > 0;
			const c = dirtyBar.querySelector( '[data-dirty-count]' );
			if ( c ) {
				c.textContent = T.changesCount.replace( '%d', list.length );
			}
		}
		dirty = v;
		dirtyBar.hidden = ! v;
	};
	const discard = () => {
		const list = changes();
		if ( ! list.length ) {
			return setDirty( false );
		}
		return modal( {
			title: T.discardTitle,
			body: `<p class="afp-modal__text">${ esc( T.discardBody ) }</p><ul class="afp-modal__changes">${ list.slice( 0, 12 ).map( changeItem ).join( '' ) }${ list.length > 12 ? `<li class="is-more">+${ list.length - 12 }</li>` : '' }</ul>`,
			confirmText: T.discard,
			danger: true,
			iconName: 'reset',
			size: 'md',
		} ).then( ( ok ) => {
			if ( ! ok ) {
				return;
			}
			// Added / removed custom rows cannot be restored in place: reload from the server.
			if ( removedTypes.length || form.querySelector( '.afp-type.is-new' ) ) {
				dirty = false;
				window.location.reload();
				return;
			}
			saved.forEach( ( st ) => {
				const el = st.el;
				if ( el.type === 'checkbox' || el.type === 'radio' ) {
					el.checked = st.v;
				} else {
					el.value = st.v;
				}
				if ( el.tagName === 'SELECT' ) {
					UI.syncSelect( el );
				}
				if ( el.type === 'range' && el.nextElementSibling ) {
					el.nextElementSibling.textContent = el.value;
				}
			} );
			const cq = form.querySelector( 'input[name="s[convert_quality]"]:checked' );
			const customRow = form.querySelector( '[data-custom-q]' );
			if ( customRow ) {
				customRow.hidden = ! cq || cq.value !== 'custom';
			}
			setDirty( false );
			toast( T.discarded, 'info' );
		} );
	};
	dirtyBar.querySelector( '[data-discard]' ).addEventListener( 'click', discard );

	/* Tabs */
	const tabs = Array.from( form.querySelectorAll( '[data-tab]' ) );
	const show = ( key ) => {
		if ( ! form.querySelector( `[data-panel="${ key }"]` ) ) {
			key = 'general';
		}
		tabs.forEach( ( t ) => {
			const on = t.dataset.tab === key;
			t.classList.toggle( 'is-on', on );
			t.setAttribute( 'aria-selected', on );
		} );
		form.querySelectorAll( '[data-panel]' ).forEach( ( p ) => ( p.hidden = p.dataset.panel !== key ) );
		try {
			window.history.replaceState( null, '', '#' + key );
		} catch ( e ) {}
	};
	tabs.forEach( ( t ) => t.addEventListener( 'click', () => show( t.dataset.tab ) ) );
	show( window.location.hash.slice( 1 ) || 'general' );

	/* Dirty tracking */
	form.addEventListener( 'input', () => setDirty( true ) );
	form.addEventListener( 'change', () => setDirty( true ) );
	window.addEventListener( 'beforeunload', ( e ) => {
		if ( dirty ) {
			e.preventDefault();
			e.returnValue = T.unsaved;
		}
	} );

	/* Save */
	const save = () => {
		const btns = form.querySelectorAll( 'button[type="submit"]' );
		btns.forEach( ( b ) => {
			b.disabled = true;
			b.classList.add( 'is-loading' );
		} );
		return ajax( 'save_settings', new FormData( form ) )
			.then( ( d ) => {
				saved = snapshot();
				removedTypes = [];
				form.querySelectorAll( '.afp-type.is-new' ).forEach( ( r ) => r.classList.remove( 'is-new' ) );
				setDirty( false );
				toast( d.message || T.saved );
			} )
			.catch( ( e ) => alertBox( e.message ) )
			.finally( () =>
				btns.forEach( ( b ) => {
					b.disabled = false;
					b.classList.remove( 'is-loading' );
				} )
			);
	};
	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		save();
	} );
	document.addEventListener( 'keydown', ( e ) => {
		if ( ( e.ctrlKey || e.metaKey ) && e.key.toLowerCase() === 's' ) {
			e.preventDefault();
			save();
		}
	} );

	/* Reset */
	form.querySelector( '[data-reset]' ).addEventListener( 'click', () =>
		modal( { title: T.resetTitle, body: `<p class="afp-modal__text">${ esc( T.resetBody ) }</p>`, confirmText: T.reset, danger: true, iconName: 'reset' } ).then( ( ok ) => {
			if ( ok ) {
				ajax( 'reset_settings' )
					.then( () => {
						dirty = false;
						window.location.reload();
					} )
					.catch( ( e ) => alertBox( e.message ) );
			}
		} )
	);

	/* File types */
	const coreBox = form.querySelector( '[data-types]' );
	const customBox = form.querySelector( '[data-custom-types]' );
	const syncEmpty = () => {
		customBox.querySelector( '.afp-types__empty' ).hidden = !! customBox.querySelector( '.afp-type' );
	};
	syncEmpty();
	form.querySelector( '[data-type-search]' ).addEventListener( 'input', ( e ) => {
		const q = e.target.value.trim().toLowerCase();
		form.querySelectorAll( '.afp-type' ).forEach( ( r ) => ( r.hidden = q && ! r.dataset.search.toLowerCase().includes( q ) ) );
	} );
	form.querySelectorAll( '[data-types-all]' ).forEach( ( b ) =>
		b.addEventListener( 'click', () => {
			form.querySelectorAll( '[data-panel="types"] .afp-type:not([hidden]) input[type="checkbox"]' ).forEach( ( c ) => ( c.checked = b.dataset.typesAll === '1' ) );
			setDirty( true );
		} )
	);
	customBox.addEventListener( 'click', ( e ) => {
		const b = e.target.closest( '[data-remove-type]' );
		if ( b ) {
			e.preventDefault();
			const row = b.closest( '.afp-type' );
			if ( ! row.classList.contains( 'is-new' ) ) {
				removedTypes.push( row.querySelector( '.afp-type__ext' ).textContent.trim() );
			}
			row.remove();
			syncEmpty();
			setDirty( true );
		}
	} );
	const existing = () => Array.from( form.querySelectorAll( '.afp-type__ext' ) ).flatMap( ( x ) => x.textContent.split( ',' ).map( ( s ) => s.trim().toLowerCase() ) );
	form.querySelector( '[data-add-type]' ).addEventListener( 'click', () => {
		let err = null;
		modal( {
			title: T.addType,
			iconName: 'plus',
			confirmText: T.add,
			size: 'md',
			body: `<div class="afp-grid2"><label class="afp-field"><span class="afp-field__label">${ esc( T.extension ) }</span><span class="afp-prefix"><em>.</em><input class="afp-input" data-ext placeholder="fig" maxlength="12"></span></label>
				<label class="afp-field"><span class="afp-field__label">${ esc( T.mime ) }</span><input class="afp-input" data-mime placeholder="application/octet-stream"></label></div>
				<p class="afp-field__hint">${ esc( T.mimeHint ) }</p><p class="afp-field__err" hidden></p>`,
			onOpen: ( ov ) => {
				err = ov.querySelector( '.afp-field__err' );
				setTimeout( () => ov.querySelector( '[data-ext]' ).focus(), 50 );
			},
			collect: ( ov ) => {
				const ext = ov.querySelector( '[data-ext]' ).value.trim().toLowerCase().replace( /[^a-z0-9]/g, '' );
				const mime = ov.querySelector( '[data-mime]' ).value.trim().toLowerCase();
				let msg = '';
				if ( ! ext || ! /^[a-z0-9.+-]+\/[a-z0-9.+-]+$/.test( mime ) ) {
					msg = T.invalidType;
				} else if ( ( T.blockedList || [] ).includes( ext ) ) {
					msg = T.blocked;
				} else if ( existing().includes( ext ) ) {
					msg = T.exists;
				}
				if ( msg ) {
					err.textContent = msg;
					err.hidden = false;
					return null;
				}
				return { ext, mime };
			},
		} ).then( ( r ) => {
			if ( ! r ) {
				return;
			}
			const i = Date.now();
			const row = document.createElement( 'label' );
			row.className = 'afp-type is-new';
			row.dataset.search = r.ext + ' ' + r.mime;
			row.innerHTML = `<span class="afp-type__ext" data-group="other">${ esc( r.ext ) }</span><span class="afp-type__mime">${ esc( r.mime ) }</span>
				<input type="hidden" name="s[custom_types][${ i }][ext]" value="${ esc( r.ext ) }"><input type="hidden" name="s[custom_types][${ i }][mime]" value="${ esc( r.mime ) }">
				<span class="afp-type__ctl"><span class="afp-switch"><input type="checkbox" name="s[custom_types][${ i }][on]" value="1" checked><i></i></span>
				<button type="button" class="afp-btn-ic afp-btn-ic--sm" data-remove-type aria-label="${ esc( T.remove ) }">${ icon( 'trash' ) }</button></span>`;
			customBox.insertBefore( row, customBox.querySelector( '.afp-types__empty' ) );
			syncEmpty();
			setDirty( true );
		} );
	} );

	/* Cloud settings helpers */
	form.addEventListener( 'click', ( e ) => {
		const c = e.target.closest( '[data-copy-val]' );
		if ( c ) {
			UI.copy( c.dataset.copyVal );
		}
		const t = e.target.closest( '[data-dav-test]' );
		if ( t ) {
			t.classList.add( 'is-loading' );
			( dirty ? save() : Promise.resolve() )
				.then( () => ajax( 'cloud_dav_test' ) )
				.then( ( d ) => modal( { title: 'WebDAV', body: `<p class="afp-modal__text">${ esc( d.message ) }</p>`, cancelText: '', iconName: 'success', tone: 'ok' } ) )
				.catch( ( er ) => alertBox( er.message ) )
				.finally( () => t.classList.remove( 'is-loading' ) );
		}
	} );
	form.addEventListener( 'focusin', ( e ) => e.target.matches( '[data-selectall]' ) && e.target.select() );
	const syncCloud = () => form.querySelectorAll( '.afp-cloud' ).forEach( ( c ) => c.classList.toggle( 'is-on', c.querySelector( '[data-cloud-toggle]' ).checked ) );
	form.addEventListener( 'change', ( e ) => e.target.matches( '[data-cloud-toggle]' ) && syncCloud() );
	syncCloud();

	/* Quality controls */
	const customRow = form.querySelector( '[data-custom-q]' );
	form.querySelectorAll( 'input[name="s[convert_quality]"]' ).forEach( ( r ) =>
		r.addEventListener( 'change', () => {
			if ( customRow ) {
				customRow.hidden = r.value !== 'custom' || ! r.checked;
			}
		} )
	);
	form.querySelectorAll( '.afp-range input' ).forEach( ( r ) => r.addEventListener( 'input', () => ( r.nextElementSibling.textContent = r.value ) ) );

	/* WebP / AVIF conversion tools */
	const conv = form.querySelector( '[data-conv]' );
	if ( conv ) {
		const q = ( sel ) => conv.querySelector( sel );
		const sizeH = ( b ) => {
			const u = [ 'B', 'KB', 'MB', 'GB' ];
			let i = 0;
			while ( b >= 1024 && i < u.length - 1 ) {
				b /= 1024;
				i++;
			}
			return ( i ? b.toFixed( 1 ) : b ) + ' ' + u[ i ];
		};
		const fmtS = ( s, ...a ) => a.reduce( ( out, v, i ) => out.replace( '%' + ( i + 1 ) + '$d', v ).replace( '%' + ( i + 1 ) + '$s', v ).replace( '%d', v ), s );
		let scan = null;
		let stop = false;
		let running = false;

		const setStats = ( d ) => {
			[ 'total', 'pending', 'converted', 'modern' ].forEach( ( k ) => {
				const el = q( `[data-stat="${ k }"]` );
				el.textContent = d[ k ];
				el.parentElement.classList.add( 'is-pop' );
				setTimeout( () => el.parentElement.classList.remove( 'is-pop' ), 400 );
			} );
			q( '[data-conv-start]' ).disabled = ! d.pending || running;
			q( '[data-conv-restore]' ).disabled = ! d.converted || running;
		};
		const doScan = () =>
			ajax( 'convert_scan' )
				.then( ( d ) => {
					scan = d;
					setStats( d );
					return d;
				} )
				.catch( ( e ) => alertBox( e.message ) );

		const runBatch = ( action, ids, title, done ) => {
			running = true;
			stop = false;
			const run = q( '.afp-conv__run' );
			const errs = q( '.afp-conv__errors' );
			run.hidden = false;
			run.classList.remove( 'is-done' );
			errs.hidden = true;
			errs.innerHTML = '';
			q( '[data-run-title]' ).textContent = title;
			q( '[data-conv-stop]' ).hidden = false;
			[ '[data-conv-start]', '[data-conv-restore]', '[data-conv-scan]' ].forEach( ( s ) => ( q( s ).disabled = true ) );
			let i = 0;
			let ok = 0;
			let saved = 0;
			const total = ids.length;
			const paint = () => {
				const pct = total ? Math.round( ( i / total ) * 100 ) : 100;
				q( '[data-run-bar]' ).style.width = pct + '%';
				q( '[data-run-pct]' ).textContent = pct + '%';
				q( '[data-run-count]' ).textContent = i + ' / ' + total;
				q( '[data-run-saved]' ).textContent = saved ? T.saved + ': ' + sizeH( saved ) : '';
			};
			paint();
			const step = () => {
				if ( stop || i >= total ) {
					running = false;
					q( '[data-conv-stop]' ).hidden = true;
					q( '[data-conv-scan]' ).disabled = false;
					run.classList.add( 'is-done' );
					if ( stop ) {
						toast( T.stopped, 'info' );
					}
					return Promise.resolve( done && ! stop ? done( ok ) : null ).then( doScan );
				}
				const chunk = ids.slice( i, i + 3 );
				return ajax( action, { ids: chunk } )
					.then( ( d ) => {
						ok += d.done || 0;
						saved += d.saved || 0;
						( d.errors || [] ).forEach( ( m ) => {
							errs.hidden = false;
							errs.insertAdjacentHTML( 'beforeend', `<li>${ esc( m ) }</li>` );
						} );
					} )
					.catch( ( e ) => {
						errs.hidden = false;
						errs.insertAdjacentHTML( 'beforeend', `<li>${ esc( e.message ) }</li>` );
					} )
					.then( () => {
						i += chunk.length;
						paint();
						return step();
					} );
			};
			return step();
		};

		const ensureSaved = () => ( dirty ? save() : Promise.resolve() );

		q( '[data-conv-scan]' ).addEventListener( 'click', () => {
			const b = q( '[data-conv-scan]' );
			b.classList.add( 'is-loading' );
			doScan().finally( () => b.classList.remove( 'is-loading' ) );
		} );
		q( '[data-conv-stop]' ).addEventListener( 'click', () => ( stop = true ) );
		q( '[data-conv-start]' ).addEventListener( 'click', () =>
			ensureSaved()
				.then( doScan )
				.then( ( d ) => {
					if ( ! d ) {
						return;
					}
					if ( ! d.format ) {
						return alertBox( T.needFormat );
					}
					if ( ! d.pending ) {
						return toast( T.nothing, 'info' );
					}
					return modal( { title: T.convTitle, body: `<p class="afp-modal__text">${ esc( fmtS( T.convBody, d.pending, d.format.toUpperCase() ) ) }</p>`, confirmText: T.convert, iconName: 'zap', tone: 'warn' } ).then(
						( ok ) => ok && runBatch( 'convert_batch', d.ids, T.converting, () => toast( T.convDone ) )
					);
				} )
		);
		q( '[data-conv-restore]' ).addEventListener( 'click', () =>
			doScan().then( ( d ) => {
				if ( ! d || ! d.converted ) {
					return;
				}
				return modal( { title: T.restTitle, body: `<p class="afp-modal__text">${ esc( fmtS( T.restBody, d.converted ) ) }</p>`, confirmText: T.restore, danger: true, iconName: 'reset' } ).then(
					( ok ) =>
						ok &&
						runBatch( 'restore_batch', d.restore, T.restoring, () =>
							ajax( 'restore_cleanup' )
								.then( ( r ) => toast( r.message, r.cleaned ? 'success' : 'info' ) )
								.catch( ( e ) => alertBox( e.message ) )
						)
				);
			} )
		);
		window.addEventListener( 'beforeunload', ( e ) => {
			if ( running ) {
				e.preventDefault();
				e.returnValue = T.converting;
			}
		} );
		if ( window.location.hash === '#optimize' ) {
			doScan();
		}
		tabs.forEach( ( t ) => t.dataset.tab === 'optimize' && t.addEventListener( 'click', () => ! scan && doScan() ) );
	}
} )();
