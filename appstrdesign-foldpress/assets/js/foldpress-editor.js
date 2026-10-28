/**
 * FoldPress attachment editor.
 */
( function () {
	'use strict';

	const UI = window.FoldPressUI;
	const D = window.FoldPressEditor || {};
	const T = D.i18n || {};
	const root = document.getElementById( 'foldpress-editor' );
	if ( ! root || ! UI ) {
		return;
	}
	const { esc, icon, fileIcon, ajax, toast, modal, alertBox, GROUP_COLOR } = UI;
	let item = null;
	const USE_ICON = { content: 'files', users: 'user', terms: 'layers', options: 'gear' };
	const KIND_ICON = { post: 'pencil', page: 'files', product: 'zap', user: 'user', term: 'layers', option: 'gear' };
	let dirty = false;

	const folderOptions = ( folders, current ) => {
		const out = [ `<option value="0">${ esc( UI.T.root || '-' ) }</option>` ];
		const walk = ( pid, depth ) =>
			folders
				.filter( ( f ) => f.parent === pid )
				.sort( ( a, b ) => a.name.localeCompare( b.name ) )
				.forEach( ( f ) => {
					out.push( `<option value="${ f.id }" ${ f.id === current ? 'selected' : '' }>${ '   '.repeat( depth ) + ( depth ? '└ ' : '' ) + esc( f.name ) }</option>` );
					walk( f.id, depth + 1 );
				} );
		walk( 0, 0 );
		return out.join( '' );
	};

	const previewHtml = ( it ) => {
		if ( it.group === 'image' ) {
			return `<img src="${ esc( it.full ) }" alt="${ esc( it.alt ) }">`;
		}
		if ( it.group === 'video' ) {
			return `<video src="${ esc( it.full ) }" controls preload="metadata"></video>`;
		}
		if ( it.group === 'audio' ) {
			return `${ fileIcon( it ) }<audio src="${ esc( it.full ) }" controls preload="metadata"></audio>`;
		}
		return fileIcon( it );
	};

	const render = () => {
		const it = item;
		const isImg = it.group === 'image';
		const canZoom = UI.previewable( it ) && it.group !== 'video';
		root.innerHTML = `
		<header class="afp-ed__bar">
			<a class="afp-btn" href="${ esc( D.libraryUrl ) }">${ icon( 'arrowLeft' ) }<span>${ esc( T.back ) }</span></a>
			<div class="afp-ed__title"><strong>${ esc( it.title ) }</strong><small><i class="afp-dot" style="background:${ GROUP_COLOR[ it.group ] }"></i>${ esc( it.filename ) }</small></div>
			${ it.renamed ? `<button type="button" class="afp-btn" data-history>${ icon( 'clock' ) }<span>${ esc( UI.T.history ) }</span><b class="afp-count">${ it.renamed }</b></button>` : '' }
			<span class="afp-ed__dirty" hidden>${ icon( 'info' ) }<span>${ esc( T.unsaved ) }</span></span>
			<button type="button" class="afp-btn afp-btn--ghost" data-discard hidden>${ icon( 'x' ) }<span>${ esc( T.cancel ) }</span></button>
			<button type="submit" form="afp-ed-form" class="afp-btn afp-btn--primary" data-save>${ icon( 'save' ) }<span>${ esc( T.save ) }</span></button>
		</header>
		<div class="afp-ed__grid">
			<div class="afp-ed__col">
				<section class="afp-panel afp-ed__preview">
					<div class="afp-ed__stage ${ canZoom ? 'is-zoom' : '' }" ${ canZoom ? 'data-zoom' : '' }>${ previewHtml( it ) }${ canZoom ? `<span class="afp-ed__zoom">${ icon( 'eye' ) }${ esc( T.preview ) }</span>` : '' }</div>
					<div class="afp-ed__tools">
						${ canZoom ? `<button type="button" class="afp-btn" data-zoom>${ icon( 'eye' ) }<span>${ esc( T.preview ) }</span></button>` : '' }
						${ it.imageEditor ? `<a class="afp-btn" href="${ esc( it.imageEditor ) }">${ icon( 'crop' ) }<span>${ esc( T.imageEditor ) }</span></a>` : '' }
						<a class="afp-btn" href="${ esc( it.full ) }" download="${ esc( it.filename ) }">${ icon( 'download' ) }<span>${ esc( UI.T.download || 'Download' ) }</span></a>
						<button type="button" class="afp-btn" data-copy="${ esc( it.full ) }">${ icon( 'link' ) }<span>URL</span></button>
					</div>
				</section>
				${
					it.sizes.length
						? `<section class="afp-panel"><h2>${ icon( 'ruler' ) }${ esc( T.sizes ) }<span class="afp-badge">${ it.sizes.length }</span></h2><div class="afp-sizes">${ it.sizes
								.map( ( s ) => `<div class="afp-sizes__row"><strong>${ esc( s.name ) }</strong><span>${ s.w } x ${ s.h }</span><button type="button" class="afp-btn-ic afp-btn-ic--sm" data-copy="${ esc( s.url ) }" aria-label="URL">${ icon( 'link' ) }</button><a class="afp-btn-ic afp-btn-ic--sm" href="${ esc( s.url ) }" target="_blank" rel="noopener" aria-label="${ esc( T.preview ) }">${ icon( 'external' ) }</a></div>` )
								.join( '' ) }</div></section>`
						: ''
				}
				<section class="afp-panel afp-use">
					<div class="afp-use__head">
						<h2>${ icon( 'link' ) }${ esc( T.usage ) }</h2>
						<span class="afp-use__total ${ it.usage.total ? '' : 'is-zero' }"><b>${ it.usage.total }</b>${ esc( T.places ) }</span>
					</div>
					<p class="afp-use__hint">${ icon( 'search' ) }<span>${ esc( T.usageHint ) }</span></p>
					${
						it.usage.total
							? `<div class="afp-use__filters" role="tablist"><button type="button" class="is-on" data-use="all">${ esc( T.all ) }<i>${ it.usage.total }</i></button>${ it.usage.groups
									.filter( ( g ) => g.items.length )
									.map( ( g ) => `<button type="button" data-use="${ esc( g.key ) }">${ icon( USE_ICON[ g.key ] ) }${ esc( T.groups[ g.key ] || g.key ) }<i>${ g.items.length }</i></button>` )
									.join( '' ) }</div>
								<div class="afp-use__list">${ it.usage.groups
									.map( ( g ) =>
										g.items
											.map(
												( u ) => `<article class="afp-use__item" data-use-group="${ esc( g.key ) }">
													<span class="afp-use__ic is-${ esc( u.kind || 'post' ) }">${ icon( KIND_ICON[ u.kind ] || 'files' ) }</span>
													<div class="afp-use__body">
														<div class="afp-use__title">${ u.url ? `<a href="${ esc( u.url ) }">${ esc( u.title ) }</a>` : `<span>${ esc( u.title ) }</span>` }</div>
														<div class="afp-use__meta"><span class="afp-use__type">${ esc( u.type || '' ) }</span>${ u.id ? `<span>#${ u.id }</span>` : '' }${ u.statusLabel ? `<span class="afp-use__status is-${ esc( u.status || 'x' ) }">${ esc( u.statusLabel ) }</span>` : '' }</div>
														<div class="afp-use__how">${ u.how.map( ( h ) => `<span>${ icon( 'check' ) }${ esc( h ) }</span>` ).join( '' ) }</div>
													</div>
													<div class="afp-use__acts">${ u.url ? `<a class="afp-btn" href="${ esc( u.url ) }">${ icon( 'pencil' ) }<span>${ esc( T.edit ) }</span></a>` : '' }${ u.view ? `<a class="afp-btn" href="${ esc( u.view ) }" target="_blank" rel="noopener">${ icon( 'external' ) }<span>${ esc( T.view ) }</span></a>` : '' }</div>
												</article>`
											)
											.join( '' )
									)
									.join( '' ) }</div>`
							: `<div class="afp-use__none">${ icon( 'success' ) }<div><strong>${ esc( T.notUsedTitle ) }</strong><span>${ esc( T.notUsed ) }</span></div></div>`
					}
				</section>
			</div>
			<div class="afp-ed__col">
				<form id="afp-ed-form" class="afp-panel afp-ed__form">
					<h2>${ icon( 'pencil' ) }${ esc( T.general ) }</h2>
					<label class="afp-field"><span class="afp-field__label">${ esc( T.title ) }</span><input class="afp-input" name="title" value="${ esc( it.title ) }"></label>
					<label class="afp-field"><span class="afp-field__label">${ esc( T.fileName ) }</span><span class="afp-suffix"><input class="afp-input" name="stem" value="${ esc( it.stem ) }" spellcheck="false"><em>.${ esc( it.ext ) }</em></span><span class="afp-field__hint afp-field__hint--warn">${ icon( 'info' ) }${ esc( T.fileNameHint ) }</span></label>
					${ isImg ? `<label class="afp-field"><span class="afp-field__label">${ esc( T.alt ) }</span><input class="afp-input" name="alt" value="${ esc( it.alt ) }"><span class="afp-field__hint">${ esc( T.altHint ) }</span></label>` : '' }
					<label class="afp-field"><span class="afp-field__label">${ esc( T.caption ) }</span><textarea class="afp-input" name="caption" rows="2">${ esc( it.caption ) }</textarea></label>
					<label class="afp-field"><span class="afp-field__label">${ esc( T.description ) }</span><textarea class="afp-input" name="description" rows="4">${ esc( it.description ) }</textarea></label>
					<label class="afp-field"><span class="afp-field__label">${ esc( T.folder ) }</span><select class="afp-select" name="folder">${ folderOptions( it.folders, it.folder ) }</select></label>
				</form>
				<section class="afp-panel">
					<h2>${ icon( 'info' ) }${ esc( T.info ) }</h2>
					<div class="afp-facts">
						<div><span>${ icon( 'files' ) }</span><small>MIME</small><strong>${ esc( it.mime ) }</strong></div>
						<div><span>${ icon( 'weight' ) }</span><small>${ esc( UI.T.size || 'Size' ) }</small><strong>${ esc( it.sizeH ) }</strong></div>
						${ it.dims ? `<div><span>${ icon( 'ruler' ) }</span><small>px</small><strong>${ esc( it.dims ) }</strong></div>` : '' }
						<div><span>${ icon( 'calendar' ) }</span><small>${ esc( T.uploadedOn ) }</small><strong>${ esc( it.dateFull ) }</strong></div>
						<div><span>${ icon( 'user' ) }</span><small>${ esc( T.uploadedBy ) }</small><strong>${ esc( it.author ) }</strong></div>
						${ it.parent ? `<div><span>${ icon( 'link' ) }</span><small>${ esc( T.attachedTo ) }</small><strong>${ it.parent.url ? `<a href="${ esc( it.parent.url ) }">${ esc( it.parent.title ) }</a>` : esc( it.parent.title ) }</strong></div>` : '' }
					</div>
					<span class="afp-copy"><input class="afp-input" value="${ esc( it.full ) }" readonly data-selectall><button type="button" class="afp-btn-ic" data-copy="${ esc( it.full ) }" aria-label="URL">${ icon( 'link' ) }</button></span>
				</section>
				<section class="afp-panel afp-danger">
					<div><strong>${ esc( T.dangerZone ) }</strong><small>${ esc( T.deleteHint ) }</small></div>
					<button type="button" class="afp-btn afp-btn--danger" data-delete>${ icon( 'trash' ) }<span>${ esc( UI.T.delete || 'Delete' ) }</span></button>
				</section>
			</div>
		</div>`;
		UI.enhanceSelects( root );
		setDirty( false );
	};

	/* Changed fields compared with the loaded item. */
	const changedFields = () => {
		const form = root.querySelector( '#afp-ed-form' );
		if ( ! form || ! item ) {
			return [];
		}
		const orig = { title: item.title, stem: item.stem, alt: item.alt, caption: item.caption, description: item.description, folder: String( item.folder ) };
		const out = [];
		Array.from( form.elements ).forEach( ( el ) => {
			if ( el.name && el.name in orig && String( el.value ) !== String( orig[ el.name ] ?? '' ) ) {
				const lbl = el.closest( '.afp-field' ).querySelector( '.afp-field__label' ).textContent;
				const nv = el.tagName === 'SELECT' ? el.options[ el.selectedIndex ].textContent.replace( /^[\s\u00a0\u2514]+/, '' ) : el.value;
				out.push( lbl + ': ' + ( nv.length > 40 ? nv.slice( 0, 40 ) + '...' : nv ) );
			}
		} );
		return out;
	};
	const setDirty = ( v ) => {
		dirty = v && changedFields().length > 0;
		const d = root.querySelector( '.afp-ed__dirty' );
		if ( d ) {
			d.hidden = ! dirty;
		}
		const c = root.querySelector( '[data-discard]' );
		if ( c ) {
			c.hidden = ! dirty;
		}
	};

	const save = () => {
		const form = root.querySelector( '#afp-ed-form' );
		const data = Object.fromEntries( new FormData( form ).entries() );
		data.id = item.id;
		const renamed = data.stem && data.stem !== item.stem;
		const go = () => {
			const btn = root.querySelector( '[data-save]' );
			btn.disabled = true;
			btn.classList.add( 'is-loading' );
			return ajax( 'save_item', data )
				.then( ( d ) => {
					item = d.item;
					render();
					toast( d.message );
				} )
				.catch( ( e ) => alertBox( e.message ) )
				.finally( () => {
					btn.disabled = false;
					btn.classList.remove( 'is-loading' );
				} );
		};
		if ( ! renamed ) {
			return go();
		}
		return modal( {
			title: T.renameTitle,
			subtitle: `${ item.stem }.${ item.ext }  →  ${ data.stem }.${ item.ext }`,
			body: `<p class="afp-modal__text">${ esc( T.renameBody ) }</p>`,
			confirmText: T.renameConfirm,
			iconName: 'pencil',
			tone: 'warn',
		} ).then( ( ok ) => ok && go() );
	};

	root.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		save();
	} );
	root.addEventListener( 'input', ( e ) => e.target.closest( '#afp-ed-form' ) && setDirty( true ) );
	root.addEventListener( 'change', ( e ) => e.target.closest( '#afp-ed-form' ) && setDirty( true ) );
	root.addEventListener( 'focusin', ( e ) => e.target.matches( '[data-selectall]' ) && e.target.select() );
	root.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '[data-discard]' ) ) {
			const list = changedFields();
			modal( {
				title: T.discardTitle,
				body: `<p class="afp-modal__text">${ esc( T.discardBody ) }</p><ul class="afp-modal__changes">${ list.map( ( l ) => `<li>${ icon( 'pencil' ) }<span>${ esc( l ) }</span></li>` ).join( '' ) }</ul>`,
				confirmText: T.discard,
				danger: true,
				iconName: 'reset',
				size: 'md',
			} ).then( ( ok ) => {
				if ( ok ) {
					render();
					toast( T.discarded, 'info' );
				}
			} );
			return;
		}
		if ( e.target.closest( '[data-history]' ) ) {
			UI.historyModal( item.id ).then( ( it ) => it && ajax( 'item', { id: item.id } ).then( ( d ) => {
				item = d.item;
				render();
			} ) );
			return;
		}
		const uf = e.target.closest( '[data-use]' );
		if ( uf ) {
			root.querySelectorAll( '[data-use]' ).forEach( ( b ) => b.classList.toggle( 'is-on', b === uf ) );
			root.querySelectorAll( '[data-use-group]' ).forEach( ( r ) => ( r.hidden = uf.dataset.use !== 'all' && r.dataset.useGroup !== uf.dataset.use ) );
			return;
		}
		const c = e.target.closest( '[data-copy]' );
		if ( c ) {
			UI.copy( c.dataset.copy );
			return;
		}
		if ( e.target.closest( '[data-zoom]' ) ) {
			UI.lightbox( [ Object.assign( {}, item, { url: item.full } ) ], 0 );
			return;
		}
		if ( e.target.closest( '[data-delete]' ) ) {
			modal( { title: UI.T.deleteTitle || T.dangerZone, subtitle: item.filename, body: `<p class="afp-modal__text">${ esc( T.deleteHint ) }</p>`, danger: true, iconName: 'trash', confirmText: UI.T.delete || 'Delete' } ).then( ( ok ) => {
				if ( ok ) {
					ajax( 'delete', { ids: [ item.id ] } )
						.then( () => {
							dirty = false;
							window.location.href = D.libraryUrl;
						} )
						.catch( ( er ) => alertBox( er.message ) );
				}
			} );
		}
	} );
	document.addEventListener( 'keydown', ( e ) => {
		if ( ( e.ctrlKey || e.metaKey ) && e.key.toLowerCase() === 's' && item ) {
			e.preventDefault();
			save();
		}
	} );
	window.addEventListener( 'beforeunload', ( e ) => {
		if ( dirty ) {
			e.preventDefault();
			e.returnValue = T.leave;
		}
	} );

	ajax( 'item', { id: D.id } )
		.then( ( d ) => {
			item = d.item;
			render();
		} )
		.catch( ( e ) => {
			root.innerHTML = `<div class="afp-empty"><span class="afp-empty__ic">${ icon( 'alert' ) }</span><strong>${ esc( e.message || T.notFound ) }</strong><a class="afp-btn" href="${ esc( D.libraryUrl ) }">${ icon( 'arrowLeft' ) }<span>${ esc( T.back ) }</span></a></div>`;
		} );
} )();
