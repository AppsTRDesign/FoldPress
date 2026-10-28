/**
 * FoldPress - AJAX media file manager.
 * Vanilla ES6. Depends on foldpress-ui.js (window.FoldPressUI).
 */
( function () {
	'use strict';

	const UI = window.FoldPressUI;
	const D = window.FoldPressData || {};
	const T = D.i18n || {};
	const root = document.getElementById( 'foldpress-app' );
	if ( ! root || ! UI ) {
		return;
	}
	const { esc, icon, fileIcon, ajax, toast, modal, alertBox, GROUP_COLOR } = UI;

	const $ = ( sel, ctx = root ) => ctx.querySelector( sel );
	const $$ = ( sel, ctx = root ) => Array.from( ctx.querySelectorAll( sel ) );
	const isTouch = window.matchMedia( '(pointer: coarse)' ).matches;
	const fmt = ( s, ...a ) => a.reduce( ( out, v, i ) => ( out.includes( '%' + ( i + 1 ) + '$' ) ? out.replace( '%' + ( i + 1 ) + '$d', v ).replace( '%' + ( i + 1 ) + '$s', v ) : out.replace( /%[sd]/, v ) ), String( s || '' ) );
	const sizeH = ( b ) => {
		const u = [ 'B', 'KB', 'MB', 'GB' ];
		let i = 0;
		while ( b >= 1024 && i < u.length - 1 ) {
			b /= 1024;
			i++;
		}
		return ( i ? b.toFixed( 1 ) : Math.round( b ) ) + ' ' + u[ i ];
	};
	const EXT_GROUP = { pdf: 'pdf', zip: 'archive', rar: 'archive', '7z': 'archive', gz: 'archive', tar: 'archive', doc: 'document', docx: 'document', odt: 'document', rtf: 'document', txt: 'document', xls: 'spreadsheet', xlsx: 'spreadsheet', csv: 'spreadsheet', ods: 'spreadsheet', ppt: 'presentation', pptx: 'presentation', odp: 'presentation', json: 'code', xml: 'code', css: 'code', ttf: 'font', otf: 'font', woff: 'font', woff2: 'font' };
	const guessGroup = ( file ) => {
		const t = file.type || '';
		if ( t.startsWith( 'image/' ) ) {
			return 'image';
		}
		if ( t.startsWith( 'video/' ) ) {
			return 'video';
		}
		if ( t.startsWith( 'audio/' ) ) {
			return 'audio';
		}
		return EXT_GROUP[ ( file.name.split( '.' ).pop() || '' ).toLowerCase() ] || 'other';
	};

	/* ---------- Preferences (per viewer convenience only) ---------- */
	const pref = ( k, d ) => {
		try {
			const v = window.localStorage.getItem( 'foldpress_' + k );
			return v === null ? d : JSON.parse( v );
		} catch ( e ) {
			return d;
		}
	};
	const setPref = ( k, v ) => {
		try {
			window.localStorage.setItem( 'foldpress_' + k, JSON.stringify( v ) );
		} catch ( e ) {}
	};

	/* ---------- State ---------- */
	const S = {
		folders: [],
		items: [],
		folder: pref( 'folder', -1 ),
		group: 'all',
		search: '',
		sort: pref( 'sort', 'date-DESC' ),
		view: pref( 'view', D.defaultView || 'grid' ),
		grouped: pref( 'grouped', false ),
		open: new Set( pref( 'openFolders', [] ) ),
		selected: new Set(),
		fsel: new Set(),
		clip: [],
		lastClicked: null,
		page: 1,
		pages: 1,
		total: 0,
		countAll: 0,
		countNone: 0,
		loading: true,
		detail: null,
	};
	const perPage = D.perPage || 60;
	const numbered = D.pagination === 'numbers';

	const busy = ( on ) => root.classList.toggle( 'is-busy', on );
	const run = ( promise, reload = true ) => {
		busy( true );
		return promise
			.then( ( d ) => {
				if ( d && d.message ) {
					toast( d.message );
				}
				if ( d && d.folders ) {
					S.folders = d.folders;
				}
				return reload ? load().then( () => d ) : ( renderTree(), renderItems(), d );
			} )
			.catch( ( e ) => {
				alertBox( e.message );
				return null;
			} )
			.finally( () => busy( false ) );
	};

	/* ---------- Folder helpers ---------- */
	const children = ( pid ) => S.folders.filter( ( f ) => f.parent === pid ).sort( ( a, b ) => a.name.localeCompare( b.name ) );
	const folderById = ( id ) => S.folders.find( ( f ) => f.id === id );
	const folderPath = ( id ) => {
		const out = [];
		let f = folderById( id );
		while ( f ) {
			out.unshift( f );
			f = folderById( f.parent );
		}
		return out;
	};
	const isDesc = ( cand, of ) => folderPath( cand ).some( ( f ) => f.id === of );
	const folderLabel = ( id ) => ( id === -1 || id === 0 ? T.unassigned : ( folderById( id ) || {} ).name || T.unassigned );

	/* ---------- Layout ---------- */
	const groupOpts = Object.keys( T.groups || {} ).map( ( k ) => `<option value="${ k }">${ esc( T.groups[ k ] ) }</option>` ).join( '' );
	root.innerHTML = `
	<div class="afp-shell">
		<aside class="afp-side" aria-label="${ esc( T.folders ) }">
			<div class="afp-side__head">
				<span class="afp-side__title">${ icon( 'folderOpen' ) }<strong>${ esc( T.folders ) }</strong></span>
				<button type="button" class="afp-btn-ic afp-btn-ic--sm" data-act="new-folder" title="${ esc( T.newFolder ) } (N)" aria-label="${ esc( T.newFolder ) }">${ icon( 'folderPlus' ) }</button>
				<button type="button" class="afp-btn-ic afp-btn-ic--sm afp-side__close" data-act="side-close" aria-label="${ esc( T.close ) }">${ icon( 'x' ) }</button>
			</div>
			<div class="afp-side__tools">
				<button type="button" class="afp-side-sel" data-act="select-folders" title="${ esc( T.selectAllFolders ) } (Ctrl+Click)" aria-pressed="false" disabled><span class="afp-tick" data-tick-folders>${ icon( 'check' ) }<i></i></span><span class="afp-side-sel__txt">${ esc( T.selectAllFolders ) }</span><kbd>Ctrl+Click</kbd></button>
			</div>
			<nav class="afp-tree" role="tree"></nav>
			<div class="afp-side__foot">
				${ D.settingsUrl ? `<a href="${ esc( D.settingsUrl ) }">${ icon( 'gear' ) }<span>${ esc( T.settings ) }</span></a>` : '' }
				<a href="${ esc( D.classicUrl ) }">${ icon( 'external' ) }<span>${ esc( T.classic ) }</span></a>
			</div>
		</aside>
		<div class="afp-side-ov" data-act="side-close"></div>
		<section class="afp-main">
			<header class="afp-bar">
				<button type="button" class="afp-btn-ic afp-menu-btn" data-act="side-open" aria-label="${ esc( T.menu ) }">${ icon( 'menu' ) }</button>
				<div class="afp-crumbs"></div>
				<div class="afp-bar__actions">
					<button type="button" class="afp-btn-ic" data-act="help" title="${ esc( T.help ) } (?)" aria-label="${ esc( T.help ) }">${ icon( 'keyboard' ) }</button>
					<button type="button" class="afp-btn" data-act="new-folder">${ icon( 'folderPlus' ) }<span>${ esc( T.newFolder ) }</span></button>
					<span class="afp-split">
						<button type="button" class="afp-btn afp-btn--primary" data-act="upload">${ icon( 'cloud' ) }<span>${ esc( T.upload ) }</span></button>
						<button type="button" class="afp-btn afp-btn--primary afp-split__more" data-act="cloud-menu" title="${ esc( T.fromCloud ) }" aria-label="${ esc( T.fromCloud ) }">${ icon( 'chevronDown' ) }</button>
					</span>
				</div>
			</header>
			<div class="afp-tools">
				<label class="afp-search">${ icon( 'search' ) }<input type="search" placeholder="${ esc( T.search ) }" aria-label="${ esc( T.search ) }" data-search><kbd>/</kbd></label>
				<select class="afp-select" data-group aria-label="${ esc( T.type ) }">${ groupOpts }</select>
				<select class="afp-select" data-sort aria-label="${ esc( T.sortNewest ) }">
					<option value="date-DESC">${ esc( T.sortNewest ) }</option><option value="date-ASC">${ esc( T.sortOldest ) }</option>
					<option value="title-ASC">${ esc( T.sortName ) }</option><option value="title-DESC">${ esc( T.sortNameDesc ) }</option></select>
				<div class="afp-seg" role="group">
					<button type="button" class="afp-btn-ic" data-act="view-grid" title="${ esc( T.grid ) }" aria-label="${ esc( T.grid ) }">${ icon( 'grid' ) }</button>
					<button type="button" class="afp-btn-ic" data-act="view-list" title="${ esc( T.list ) }" aria-label="${ esc( T.list ) }">${ icon( 'list' ) }</button>
					<button type="button" class="afp-btn-ic" data-act="grouped" title="${ esc( T.groupBy ) }" aria-label="${ esc( T.groupBy ) }">${ icon( 'layers' ) }</button>
				</div>
				<div class="afp-selgroup" role="group">
					<button type="button" class="afp-selbtn" data-act="select-all" title="${ esc( T.selectAll ) } (Ctrl+A)" aria-pressed="false"><span class="afp-tick" data-tick-files>${ icon( 'check' ) }<i></i></span><span class="afp-selbtn__txt">${ esc( T.selectAll ) }</span></button>
				</div>
			</div>
			<div class="afp-upl" hidden aria-live="polite">
				<div class="afp-upl__row">
					<span class="afp-upl__ic">${ icon( 'cloud' ) }<i></i></span>
					<div class="afp-upl__txt"><strong data-upl-title></strong><small data-upl-sub></small></div>
					<div class="afp-upl__stats">
						<span class="afp-upl__stat" title="Speed">${ icon( 'speed' ) }<b data-upl-speed>-</b></span>
						<span class="afp-upl__stat" title="ETA">${ icon( 'clock' ) }<b data-upl-eta>-</b></span>
					</div>
					<span class="afp-upl__pct"><b data-upl-pct>0</b><i>%</i></span>
					<button type="button" class="afp-upl__cancel" data-upl-cancel>${ icon( 'x' ) }<span>${ esc( T.cancelAll ) }</span></button>
					<button type="button" class="afp-btn-ic afp-btn-ic--sm" data-upl-min aria-label="${ esc( T.showFiles ) }" title="${ esc( T.showFiles ) }">${ icon( 'chevronDown' ) }</button>
					<button type="button" class="afp-btn-ic afp-btn-ic--sm" data-upl-close aria-label="${ esc( T.close ) }">${ icon( 'x' ) }</button>
				</div>
				<div class="afp-upl__bar"><i data-upl-bar><em></em></i></div>
				<div class="afp-upl__list"></div>
			</div>
			<div class="afp-bulk afp-fbulk" hidden>
				<button type="button" class="afp-btn-ic afp-btn-ic--sm" data-act="fclear" aria-label="${ esc( T.clear ) }">${ icon( 'x' ) }</button>
				<span class="afp-bulk__n" data-fbulk-n></span>
				<div class="afp-bulk__acts">
					<button type="button" class="afp-btn" data-act="fbulk-move">${ icon( 'move' ) }<span>${ esc( T.move ) }</span></button>
					<button type="button" class="afp-btn" data-act="fbulk-zip">${ icon( 'zip' ) }<span>${ esc( T.zip ) }</span></button>
					<button type="button" class="afp-btn afp-btn--danger" data-act="fbulk-delete" title="Delete">${ icon( 'trash' ) }<span>${ esc( T.delete ) }</span></button>
				</div>
			</div>
			<div class="afp-bulk" data-file-bulk hidden>
				<button type="button" class="afp-btn-ic afp-btn-ic--sm" data-act="clear" aria-label="${ esc( T.clear ) }">${ icon( 'x' ) }</button>
				<span class="afp-bulk__n"></span>
				<div class="afp-bulk__acts">
					<button type="button" class="afp-btn" data-act="bulk-move" title="M">${ icon( 'move' ) }<span>${ esc( T.move ) }</span></button>
					<button type="button" class="afp-btn" data-act="bulk-cut" title="Ctrl+X">${ icon( 'scissors' ) }<span>${ esc( T.cut ) }</span></button>
					<button type="button" class="afp-btn" data-act="bulk-zip">${ icon( 'zip' ) }<span>${ esc( T.zip ) }</span></button>
					<button type="button" class="afp-btn" data-act="bulk-download">${ icon( 'download' ) }<span>${ esc( T.download ) }</span></button>
					<button type="button" class="afp-btn afp-btn--danger" data-act="bulk-delete" title="Delete">${ icon( 'trash' ) }<span>${ esc( T.delete ) }</span></button>
				</div>
			</div>
			<div class="afp-content" tabindex="-1">
				<div class="afp-items"></div>
				<div class="afp-pager"></div>
				<div class="afp-drop"><div class="afp-drop__card"><span class="afp-drop__ic">${ icon( 'cloud' ) }</span><strong>${ esc( T.dropHere ) }</strong><small></small></div></div>
			</div>
		</section>
		<aside class="afp-detail" hidden></aside>
	</div>
	<input type="file" multiple hidden data-file>`;

	const el = {
		tree: $( '.afp-tree' ),
		crumbs: $( '.afp-crumbs' ),
		items: $( '.afp-items' ),
		pager: $( '.afp-pager' ),
		content: $( '.afp-content' ),
		bulk: $( '[data-file-bulk]' ),
		fbulk: $( '.afp-fbulk' ),
		upl: $( '.afp-upl' ),
		uplList: $( '.afp-upl__list' ),
		detail: $( '.afp-detail' ),
		file: $( '[data-file]' ),
		search: $( '[data-search]' ),
	};
	$( '[data-sort]' ).value = S.sort;
	UI.enhanceSelects( root );

	/* ---------- Render: tree ---------- */
	const treeNode = ( f, depth ) => {
		const kids = children( f.id );
		const open = S.open.has( f.id );
		const active = S.folder === f.id;
		return `<div class="afp-node ${ depth ? 'is-child' : '' }" role="treeitem" aria-expanded="${ kids.length ? open : 'false' }" aria-selected="${ active }">
			<div class="afp-node__row ${ active ? 'is-active' : '' } ${ S.fsel.has( f.id ) ? 'is-fsel' : '' }" data-folder="${ f.id }" draggable="true" tabindex="0">
				<button type="button" class="afp-node__tog ${ kids.length ? '' : 'is-leaf' } ${ open ? 'is-open' : '' }" data-toggle="${ f.id }" tabindex="-1" aria-hidden="true">${ icon( 'chevron' ) }</button>
				${ depth ? icon( 'subdir', 'afp-node__sub' ) : '' }
				${ icon( active || ( open && kids.length ) ? 'folderOpen' : 'folder', 'afp-node__ic' ) }
				<span class="afp-node__name">${ esc( f.name ) }</span>
				<span class="afp-count">${ f.count }</span>
				<button type="button" class="afp-node__more" data-fmenu="${ f.id }" aria-label="${ esc( T.menu ) }" tabindex="-1">${ icon( 'dots' ) }</button>
			</div>
			${ kids.length && open ? `<div class="afp-node__kids" role="group">${ kids.map( ( k ) => treeNode( k, depth + 1 ) ).join( '' ) }</div>` : '' }
		</div>`;
	};
	const renderTree = () => {
		const sys = ( id, ic, label, count ) =>
			`<div class="afp-node__row afp-node__row--sys ${ S.folder === id ? 'is-active' : '' }" data-folder="${ id }" tabindex="0">${ icon( ic, 'afp-node__ic' ) }<span class="afp-node__name">${ esc( label ) }</span><span class="afp-count">${ count }</span></div>`;
		el.tree.innerHTML = sys( -1, 'files', T.allFiles, S.countAll ) + sys( 0, 'inbox', T.unassigned, S.countNone ) + '<div class="afp-tree__sep"></div>' + children( 0 ).map( ( f ) => treeNode( f, 0 ) ).join( '' );
		renderCrumbs();
		renderFolderSel();
	};
	const renderCrumbs = () => {
		let html = `<button type="button" class="afp-crumb" data-folder="-1">${ icon( 'files' ) }<span>${ esc( T.allFiles ) }</span></button>`;
		if ( S.folder === 0 ) {
			html += icon( 'chevron', 'afp-crumb__sep' ) + `<span class="afp-crumb is-cur">${ esc( T.unassigned ) }</span>`;
		} else if ( S.folder > 0 ) {
			const path = folderPath( S.folder );
			path.forEach( ( f, i ) => {
				html += icon( 'chevron', 'afp-crumb__sep' ) + ( i === path.length - 1 ? `<span class="afp-crumb is-cur">${ esc( f.name ) }</span>` : `<button type="button" class="afp-crumb" data-folder="${ f.id }">${ esc( f.name ) }</button>` );
			} );
		}
		html += `<span class="afp-crumb__total">${ S.total } ${ esc( T.files ) }</span>`;
		el.crumbs.innerHTML = html;
	};

	/* ---------- Render: items ---------- */
	const media = ( it ) => ( it.thumb ? `<img src="${ esc( it.thumb ) }" alt="${ esc( it.alt ) }" loading="lazy" draggable="false">` : fileIcon( it ) );
	const histBadge = ( it, cls = '' ) =>
		it.renamed ? `<button type="button" class="afp-hbadge ${ cls }" data-history title="${ esc( UI.T.renamedBadge ) }" aria-label="${ esc( UI.T.renamedBadge ) }">${ icon( 'clock' ) }<b>${ it.renamed }</b></button>` : '';
	const card = ( it ) => {
		const sel = S.selected.has( it.id );
		const cut = S.clip.includes( it.id ) ? 'is-cut' : '';
		if ( S.view === 'list' ) {
			return `<div class="afp-row ${ sel ? 'is-sel' : '' } ${ cut }" data-id="${ it.id }" draggable="true" tabindex="0" role="option" aria-selected="${ sel }">
				<span class="afp-check" data-check>${ icon( 'check' ) }</span>
				<span class="afp-row__thumb">${ media( it ) }</span>
				<span class="afp-row__name"><strong>${ esc( it.title ) }${ histBadge( it, 'is-row' ) }</strong><small>${ esc( it.filename ) }</small></span>
				<span class="afp-row__meta"><i class="afp-dot" style="background:${ GROUP_COLOR[ it.group ] }"></i>${ esc( ( T.groups && T.groups[ it.group ] ) || it.group ) }</span>
				<span class="afp-row__meta">${ esc( it.sizeH ) }</span>
				<span class="afp-row__meta afp-hide-sm">${ esc( it.date ) }</span>
				<button type="button" class="afp-btn-ic afp-btn-ic--sm afp-row__more" data-imenu aria-label="${ esc( T.menu ) }">${ icon( 'dots' ) }</button>
			</div>`;
		}
		return `<div class="afp-card ${ sel ? 'is-sel' : '' } ${ cut } ${ it.thumb ? '' : 'is-file' }" data-id="${ it.id }" draggable="true" tabindex="0" role="option" aria-selected="${ sel }">
			<div class="afp-card__media">${ media( it ) }${ histBadge( it, 'is-card' ) }${ UI.previewable( it ) ? `<button type="button" class="afp-card__zoom" data-preview aria-label="${ esc( T.preview ) }">${ icon( 'eye' ) }</button>` : '' }</div>
			<span class="afp-check" data-check>${ icon( 'check' ) }</span>
			<button type="button" class="afp-btn-ic afp-btn-ic--sm afp-card__more" data-imenu aria-label="${ esc( T.menu ) }">${ icon( 'dots' ) }</button>
			<div class="afp-card__foot"><span class="afp-card__name" title="${ esc( it.filename ) }">${ esc( it.title ) }</span><small><i class="afp-dot" style="background:${ GROUP_COLOR[ it.group ] }"></i>${ esc( ( it.ext || '' ).toUpperCase() ) } &middot; ${ esc( it.sizeH ) }</small></div>
		</div>`;
	};
	const folderCard = ( f ) => {
		const st = treeStats( [ f.id ] );
		return `<div class="afp-fcard ${ S.fsel.has( f.id ) ? 'is-fsel' : '' }" data-folder="${ f.id }" draggable="true" tabindex="0"><span class="afp-check afp-fcard__check" data-fcheck>${ icon( 'check' ) }</span>${ icon( 'folder', 'afp-fcard__ic' ) }<span><strong>${ esc( f.name ) }</strong><small>${ st.files } ${ esc( T.files ) }${ st.folders > 1 ? ` &middot; ${ st.folders - 1 } ${ esc( T.subfolders ) }` : '' }</small></span></div>`;
	};
	const skeleton = () => `<div class="afp-list">${ Array.from( { length: 12 }, () => '<div class="afp-skel"><i></i><b></b><b></b></div>' ).join( '' ) }</div>`;

	const renderItems = () => {
		el.items.className = 'afp-items afp-items--' + S.view;
		let html = '';
		const subs = S.folder > 0 ? children( S.folder ) : S.folder === -1 ? children( 0 ) : [];
		if ( subs.length && ! S.search && S.group === 'all' && S.page === 1 ) {
			html += `<div class="afp-fgrid">${ subs.map( folderCard ).join( '' ) }</div>`;
		}
		if ( S.view === 'list' && S.items.length ) {
			html += `<div class="afp-lhead"><button type="button" class="afp-tick afp-lhead__tick" data-act="select-all" data-tick-files aria-label="${ esc( T.selectAll ) }">${ icon( 'check' ) }<i></i></button><span></span><span>${ esc( T.name ) }</span><span>${ esc( T.type ) }</span><span>${ esc( T.size ) }</span><span class="afp-hide-sm">${ esc( T.date ) }</span><span></span></div>`;
		}
		if ( ! S.items.length ) {
			html += S.loading ? skeleton() : `<div class="afp-empty"><span class="afp-empty__ic">${ icon( 'folderOpen' ) }</span><strong>${ esc( T.empty ) }</strong><span>${ esc( T.emptyHint ) }</span><button type="button" class="afp-btn afp-btn--primary" data-act="upload">${ icon( 'cloud' ) }<span>${ esc( T.upload ) }</span></button></div>`;
		} else if ( S.grouped ) {
			const by = {};
			S.items.forEach( ( it ) => ( by[ it.group ] = by[ it.group ] || [] ).push( it ) );
			Object.keys( T.groups ).forEach( ( g ) => {
				if ( by[ g ] ) {
					html += `<h3 class="afp-ghead"><i style="background:${ GROUP_COLOR[ g ] }"></i>${ esc( T.groups[ g ] ) }<span>${ by[ g ].length }</span></h3><div class="afp-list" role="listbox" aria-multiselectable="true">${ by[ g ].map( card ).join( '' ) }</div>`;
				}
			} );
		} else {
			html += `<div class="afp-list" role="listbox" aria-multiselectable="true">${ S.items.map( card ).join( '' ) }</div>`;
		}
		el.items.innerHTML = html;
		renderPager();
		renderFolderSel();
		$( '[data-act="view-grid"]' ).classList.toggle( 'is-on', S.view === 'grid' );
		$( '[data-act="view-list"]' ).classList.toggle( 'is-on', S.view === 'list' );
		$( '[data-act="grouped"]' ).classList.toggle( 'is-on', S.grouped );
		renderBulk();
	};

	const renderPager = () => {
		if ( ! S.items.length || S.pages <= 1 ) {
			el.pager.innerHTML = '';
			return;
		}
		if ( ! numbered ) {
			el.pager.innerHTML = `<div class="afp-pager__more"><small>${ esc( fmt( T.showing, S.items.length, S.total ) ) }</small><i class="afp-pager__track"><em style="width:${ ( S.items.length / S.total ) * 100 }%"></em></i>${ S.page < S.pages ? `<button type="button" class="afp-btn" data-act="more">${ icon( 'chevronDown' ) }<span>${ esc( T.loadMore ) }</span></button>` : '' }</div>`;
			return;
		}
		const p = S.page;
		const n = S.pages;
		const nums = [];
		for ( let i = 1; i <= n; i++ ) {
			if ( i === 1 || i === n || Math.abs( i - p ) <= 1 ) {
				nums.push( i );
			} else if ( nums[ nums.length - 1 ] !== '...' ) {
				nums.push( '...' );
			}
		}
		el.pager.innerHTML = `<nav class="afp-pages" aria-label="${ esc( fmt( T.pageOf, p, n ) ) }">
			<button type="button" class="afp-page" data-page="${ p - 1 }" ${ p <= 1 ? 'disabled' : '' } aria-label="${ esc( T.prev ) }">${ icon( 'chevronL' ) }</button>
			${ nums.map( ( x ) => ( x === '...' ? '<span class="afp-page is-gap">...</span>' : `<button type="button" class="afp-page ${ x === p ? 'is-cur' : '' }" data-page="${ x }" ${ x === p ? 'aria-current="page"' : '' }>${ x }</button>` ) ).join( '' ) }
			<button type="button" class="afp-page" data-page="${ p + 1 }" ${ p >= n ? 'disabled' : '' } aria-label="${ esc( T.next ) }">${ icon( 'chevron' ) }</button>
		</nav><small class="afp-pages__info">${ esc( fmt( T.pageOf, p, n ) ) } &middot; ${ S.total } ${ esc( T.files ) }</small>`;
	};

	/** Folders + files contained in the given folders (recursive). */
	const treeStats = ( ids ) => {
		const seen = new Set();
		const walk = ( id ) => {
			if ( seen.has( id ) ) {
				return;
			}
			seen.add( id );
			children( id ).forEach( ( c ) => walk( c.id ) );
		};
		ids.forEach( walk );
		let files = 0;
		seen.forEach( ( id ) => ( files += ( folderById( id ) || {} ).count || 0 ) );
		return { folders: seen.size, files };
	};
	const renderFolderSel = () => {
		S.fsel.forEach( ( id ) => ! folderById( id ) && S.fsel.delete( id ) );
		const n = S.fsel.size;
		el.fbulk.hidden = ! n;
		if ( n ) {
			const st = treeStats( Array.from( S.fsel ) );
			$( '[data-fbulk-n]' ).innerHTML = `<b>${ n }</b> ${ esc( T.foldersSelected ) } <em>${ st.files } ${ esc( T.files ) }</em>`;
		}
		$$( '[data-folder]' ).forEach( ( x ) => x.classList.toggle( 'is-fsel', S.fsel.has( +x.dataset.folder ) ) );
		const selBtn = $( '[data-act="select-folders"]' );
		selBtn.disabled = ! S.folders.length;
		selBtn.classList.toggle( 'is-on', n > 0 );
		setTick( $( '[data-tick-folders]' ), n, S.folders.length );
	};
	const toggleFolderSel = ( id ) => {
		if ( S.fsel.has( id ) ) {
			S.fsel.delete( id );
		} else {
			S.fsel.add( id );
		}
		renderFolderSel();
	};
	const setTick = ( tick, n, total ) => {
		if ( ! tick ) {
			return;
		}
		tick.classList.toggle( 'is-on', total > 0 && n >= total );
		tick.classList.toggle( 'is-mixed', n > 0 && n < total );
		tick.closest( 'button' ).setAttribute( 'aria-pressed', n > 0 && n >= total ? 'true' : n > 0 ? 'mixed' : 'false' );
	};
	const renderBulk = () => {
		const n = S.selected.size;
		el.bulk.hidden = ! n;
		const onPage = S.items.filter( ( i ) => S.selected.has( i.id ) ).length;
		$$( '[data-tick-files]' ).forEach( ( t ) => setTick( t, onPage, S.items.length ) );
		el.bulk.querySelector( '.afp-bulk__n' ).innerHTML = `<b>${ n }</b> ${ esc( T.selected ) }`;
		$( '[data-act="bulk-zip"]' ).hidden = ! D.zipOk;
		$$( '[data-id]' ).forEach( ( c ) => {
			const s = S.selected.has( +c.dataset.id );
			c.classList.toggle( 'is-sel', s );
			c.setAttribute( 'aria-selected', s );
		} );
	};

	/* ---------- Load ---------- */
	let reqId = 0;
	const load = ( append = false ) => {
		const my = ++reqId;
		S.loading = true;
		if ( ! append ) {
			root.classList.add( 'is-loading' );
		}
		const [ orderby, order ] = S.sort.split( '-' );
		return ajax( 'list', { folder: S.folder, group: S.group, search: S.search, page: S.page, per_page: perPage, orderby, order } )
			.then( ( d ) => {
				if ( my !== reqId ) {
					return;
				}
				S.items = append ? S.items.concat( d.items ) : d.items;
				S.pages = d.pages;
				S.total = d.total;
				S.folders = d.folders;
				S.countAll = d.countAll;
				S.countNone = d.countNone;
				if ( S.folder > 0 && ! folderById( S.folder ) ) {
					S.folder = -1;
					return load();
				}
				if ( ! append && S.page > 1 && ! d.items.length ) {
					S.page = Math.max( 1, d.pages );
					return load();
				}
				if ( ! numbered ) {
					const ids = new Set( S.items.map( ( i ) => i.id ) );
					S.selected.forEach( ( id ) => ! ids.has( id ) && S.selected.delete( id ) );
				}
			} )
			.catch( ( e ) => alertBox( e.message ) )
			.finally( () => {
				if ( my !== reqId ) {
					return;
				}
				S.loading = false;
				root.classList.remove( 'is-loading' );
				renderTree();
				renderItems();
				if ( S.detail ) {
					if ( itemById( S.detail ) ) {
						openDetail( S.detail );
					} else {
						closeDetail();
					}
				}
			} );
	};
	const reload = () => {
		S.page = 1;
		return load();
	};

	const goFolder = ( id ) => {
		S.folder = id;
		S.page = 1;
		setPref( 'folder', id );
		S.selected.clear();
		folderPath( id ).forEach( ( f ) => f.id !== id && S.open.add( f.id ) );
		root.classList.remove( 'side-open' );
		el.content.scrollTop = 0;
		load();
	};

	/* ---------- Selection ---------- */
	const itemById = ( id ) => S.items.find( ( i ) => i.id === id );
	const selectClick = ( id, e ) => {
		const additive = e.ctrlKey || e.metaKey || ( e.target && e.target.closest && e.target.closest( '[data-check]' ) ) || ( isTouch && S.selected.size );
		if ( e.shiftKey && S.lastClicked !== null ) {
			const ids = S.items.map( ( i ) => i.id );
			const a = ids.indexOf( S.lastClicked );
			const b = ids.indexOf( id );
			if ( ! ( e.ctrlKey || e.metaKey ) ) {
				S.selected.clear();
			}
			ids.slice( Math.min( a, b ), Math.max( a, b ) + 1 ).forEach( ( x ) => S.selected.add( x ) );
		} else if ( additive ) {
			if ( S.selected.has( id ) ) {
				S.selected.delete( id );
			} else {
				S.selected.add( id );
			}
			S.lastClicked = id;
		} else {
			S.selected.clear();
			S.selected.add( id );
			S.lastClicked = id;
		}
		renderBulk();
	};
	const targetIds = ( id ) => ( S.selected.has( id ) ? Array.from( S.selected ) : [ id ] );

	/* ---------- Folder picker ---------- */
	const pickFolder = ( exclude = null, title = T.chooseFolder ) => {
		const opt = ( f, d ) =>
			( exclude && isDesc( f.id, exclude )
				? ''
				: `<button type="button" class="afp-pick__row ${ d ? 'is-child' : '' }" data-pick="${ f.id }" style="--d:${ d }">${ d ? icon( 'subdir', 'afp-pick__sub' ) : '' }${ icon( 'folder' ) }<span>${ esc( f.name ) }</span><small>${ f.count }</small></button>` ) + children( f.id ).map( ( c ) => opt( c, d + 1 ) ).join( '' );
		let chosen = null;
		return modal( {
			title,
			iconName: 'move',
			confirmText: T.moveHere,
			size: 'md',
			body: `<label class="afp-search afp-pick__search">${ icon( 'search' ) }<input type="search" placeholder="${ esc( T.search ) }" data-pick-search></label><div class="afp-pick"><button type="button" class="afp-pick__row" data-pick="0" style="--d:0">${ icon( 'inbox' ) }<span>${ esc( T.root ) }</span></button>${ children( 0 ).map( ( f ) => opt( f, 0 ) ).join( '' ) }</div>`,
			onOpen: ( ov, done ) => {
				const list = ov.querySelector( '.afp-pick' );
				list.addEventListener( 'click', ( e ) => {
					const b = e.target.closest( '[data-pick]' );
					if ( b ) {
						list.querySelectorAll( '.is-on' ).forEach( ( x ) => x.classList.remove( 'is-on' ) );
						b.classList.add( 'is-on' );
						chosen = +b.dataset.pick;
					}
				} );
				list.addEventListener( 'dblclick', ( e ) => {
					const b = e.target.closest( '[data-pick]' );
					if ( b ) {
						done( +b.dataset.pick );
					}
				} );
				ov.querySelector( '[data-pick-search]' ).addEventListener( 'input', ( e ) => {
					const q = e.target.value.toLowerCase();
					list.querySelectorAll( '[data-pick]' ).forEach( ( b ) => ( b.hidden = q && ! b.textContent.toLowerCase().includes( q ) ) );
				} );
			},
			collect: () => chosen,
		} );
	};

	/* ---------- Actions ---------- */
	const A = {
		newFolder( parent = S.folder > 0 ? S.folder : 0 ) {
			return modal( {
				title: parent ? T.newSubfolder : T.newFolder,
				subtitle: parent ? folderLabel( parent ) : '',
				input: '',
				inputLabel: T.folderName,
				inputHint: T.folderHint,
				confirmText: T.create,
				iconName: 'folderPlus',
			} ).then( ( name ) => {
				if ( name ) {
					if ( parent ) {
						S.open.add( parent );
					}
					return run( ajax( 'folder_create', { name, parent } ), false );
				}
			} );
		},
		renameFolder( id ) {
			const f = folderById( id );
			return modal( { title: T.rename, subtitle: f.name, input: f.name, inputLabel: T.folderName, confirmText: T.save, iconName: 'pencil' } ).then( ( name ) => name && name !== f.name && run( ajax( 'folder_rename', { id, name } ), false ) );
		},
		deleteFolder( id ) {
			return A.deleteFolders( [ id ] );
		},
		deleteFolders( ids ) {
			ids = ids.filter( ( id ) => folderById( id ) );
			if ( ! ids.length ) {
				return;
			}
			const st = treeStats( ids );
			const names = ids.slice( 0, 5 ).map( ( id ) => folderById( id ).name );
			return modal( {
				title: ids.length > 1 ? T.deleteFolders : T.deleteFolder,
				subtitle: fmt( T.folderTreeInfo, st.folders, st.files ),
				body: `<p class="afp-modal__text">${ esc( T.confirmDelF ) }</p>
					<ul class="afp-modal__files">${ names.map( ( n ) => `<li>${ icon( 'folder' ) }${ esc( n ) }</li>` ).join( '' ) }${ ids.length > 5 ? `<li class="is-more">+${ ids.length - 5 }</li>` : '' }</ul>
					<div class="afp-modal__stats"><span><b>${ st.folders }</b>${ esc( T.foldersWord ) }</span><span><b>${ st.files }</b>${ esc( T.files ) }</span></div>
					<label class="afp-checkrow is-danger"><span class="afp-switch"><input type="checkbox" data-agree><i></i></span><span><strong>${ esc( T.understand ) }</strong><small>${ esc( T.cannotUndo ) }</small></span></label>`,
				confirmText: T.deleteAll,
				danger: true,
				iconName: 'trash',
				size: 'md',
				onOpen: ( ov ) => {
					const btn = ov.querySelector( '[data-ok]' );
					const cb = ov.querySelector( '[data-agree]' );
					btn.disabled = true;
					cb.addEventListener( 'change', () => ( btn.disabled = ! cb.checked ) );
				},
				collect: ( ov ) => ( ov.querySelector( '[data-agree]' ).checked ? true : null ),
			} ).then( ( ok ) => {
				if ( ok ) {
					if ( S.folder > 0 && ids.some( ( id ) => isDesc( S.folder, id ) ) ) {
						S.folder = -1;
					}
					S.fsel.clear();
					return run( ajax( 'folder_delete', { ids } ) );
				}
			} );
		},
		moveFolder( id, parent ) {
			return A.moveFolders( [ id ], parent );
		},
		moveFolders( ids, parent ) {
			ids = ids.filter( ( id ) => id !== parent && ! ( parent && isDesc( parent, id ) ) );
			if ( ! ids.length || parent === null || parent < 0 ) {
				return;
			}
			if ( parent ) {
				S.open.add( parent );
			}
			S.fsel.clear();
			return run( ajax( 'folder_move', { ids, parent } ), false );
		},
		pickMoveFolders( ids ) {
			return pickFolder( ids.length === 1 ? ids[ 0 ] : null ).then( ( p ) => p !== null && A.moveFolders( ids, p ) );
		},
		zipFolders( ids ) {
			const def = ids.length === 1 ? ( folderById( ids[ 0 ] ) || {} ).name : 'folders-' + new Date().toISOString().slice( 0, 10 );
			const st = treeStats( ids );
			return modal( { title: T.zip, subtitle: fmt( T.folderTreeInfo, st.folders, st.files ), input: def, inputLabel: T.archiveName, inputHint: T.zipFoldersHint, confirmText: T.create, iconName: 'zip' } ).then(
				( name ) => name && run( ajax( 'folder_zip', { ids, name, folder: Math.max( 0, S.folder ) } ) )
			);
		},
		moveFiles( ids, folder ) {
			if ( ! ids.length || folder === null || folder < 0 ) {
				return;
			}
			S.selected.clear();
			S.clip = S.clip.filter( ( x ) => ! ids.includes( x ) );
			return run( ajax( 'move', { ids, folder } ) );
		},
		pickMove( ids ) {
			if ( ids.length ) {
				return pickFolder().then( ( f ) => f !== null && A.moveFiles( ids, f ) );
			}
		},
		cut( ids ) {
			if ( ! ids.length ) {
				return;
			}
			S.clip = ids.slice();
			renderItems();
			toast( ids.length + ' ' + T.cutDone, 'info' );
		},
		paste() {
			if ( ! S.clip.length ) {
				return;
			}
			const ids = S.clip.slice();
			if ( S.folder < 0 ) {
				return pickFolder().then( ( f ) => f !== null && A.moveFiles( ids, f ) );
			}
			return A.moveFiles( ids, S.folder );
		},
		rename( id ) {
			const it = itemById( id );
			return modal( {
				title: T.rename,
				subtitle: it.filename,
				input: it.title,
				inputLabel: T.newName,
				confirmText: T.save,
				iconName: 'pencil',
				size: 'md',
				onOpen: ( ov ) => {
					const lbl = document.createElement( 'label' );
					lbl.className = 'afp-checkrow';
					lbl.innerHTML = `<span class="afp-switch"><input type="checkbox" data-phys><i></i></span><span><strong>${ esc( T.renamePhysical ) }</strong><small>${ esc( T.renamePhysicalHint ) }</small></span>`;
					ov.querySelector( '.afp-modal__body' ).appendChild( lbl );
				},
				collect: ( ov ) => {
					const name = ov.querySelector( '[data-in]' ).value.trim();
					return name ? { name, physical: ov.querySelector( '[data-phys]' ).checked } : null;
				},
			} ).then( ( r ) => r && ( r.name !== it.title || r.physical ) && run( ajax( 'rename', { id, name: r.name, physical: r.physical ? 1 : 0 } ) ) );
		},
		del( ids ) {
			if ( ! ids.length ) {
				return;
			}
			const names = ids.slice( 0, 4 ).map( ( id ) => ( itemById( id ) || {} ).filename ).filter( Boolean );
			return modal( {
				title: T.deleteTitle,
				subtitle: ids.length + ' ' + T.selected,
				body: `<p class="afp-modal__text">${ esc( T.confirmDel ) }</p><ul class="afp-modal__files">${ names.map( ( n ) => `<li>${ icon( 'files' ) }${ esc( n ) }</li>` ).join( '' ) }${ ids.length > 4 ? `<li class="is-more">+${ ids.length - 4 }</li>` : '' }</ul>`,
				confirmText: T.delete,
				danger: true,
				iconName: 'trash',
			} ).then( ( ok ) => {
				if ( ok ) {
					S.selected.clear();
					return run( ajax( 'delete', { ids } ) );
				}
			} );
		},
		zip( ids ) {
			const def = ids.length === 1 ? ( itemById( ids[ 0 ] ) || {} ).title : 'archive-' + new Date().toISOString().slice( 0, 10 );
			return modal( { title: T.zip, subtitle: ids.length + ' ' + T.selected, input: def, inputLabel: T.archiveName, inputHint: T.zipHint, confirmText: T.create, iconName: 'zip' } ).then( ( name ) => name && run( ajax( 'zip', { ids, name, folder: Math.max( 0, S.folder ) } ) ) );
		},
		unzip( id ) {
			return run( ajax( 'unzip', { id, folder: Math.max( 0, S.folder ) } ) ).then( ( d ) => d && d.folder && goFolder( d.folder ) );
		},
		preview( id ) {
			const it = itemById( id );
			if ( ! UI.lightbox( S.items, S.items.indexOf( it ) ) ) {
				openDetail( id );
			}
		},
		download( ids ) {
			ids.forEach( ( id, i ) => {
				const it = itemById( id );
				if ( ! it ) {
					return;
				}
				setTimeout( () => {
					const a = document.createElement( 'a' );
					a.href = it.url;
					a.download = it.filename;
					document.body.appendChild( a );
					a.click();
					a.remove();
				}, i * 300 );
			} );
		},
		newFile() {
			const create = D.createExt || [];
			const allowed = D.allowedExt || [];
			let info = null;
			const verdict = ( v ) => {
				const ext = ( v.includes( '.' ) ? v.split( '.' ).pop() : '' ).toLowerCase();
				if ( ! ext || v.endsWith( '.' ) ) {
					return [ 'warn', T.extMissing ];
				}
				if ( ! allowed.includes( ext ) ) {
					return [ 'bad', fmt( T.extNotAllowed, ext ) ];
				}
				if ( ! create.includes( ext ) ) {
					return [ 'bad', fmt( T.extNotText, ext ) ];
				}
				return [ 'ok', T.extOk ];
			};
			return modal( {
				title: T.newFile,
				subtitle: T.createIn + ' ' + ( S.folder > 0 ? folderLabel( S.folder ) : T.unassigned ),
				iconName: 'plus',
				size: 'md',
				input: 'yeni-dosya.txt',
				inputLabel: T.fileNameLabel,
				inputHint: T.newFileHint,
				confirmText: T.create,
				onOpen: ( ov ) => {
					const inp = ov.querySelector( '[data-in]' );
					const box = document.createElement( 'div' );
					box.innerHTML = `<p class="afp-newfile__msg" data-msg></p><div class="afp-newfile__exts">${ create.map( ( e ) => `<button type="button" data-ext="${ esc( e ) }">.${ esc( e ) }</button>` ).join( '' ) }</div><label class="afp-checkrow"><span class="afp-switch"><input type="checkbox" data-open checked><i></i></span><span><strong>${ esc( T.openInEditor ) }</strong></span></label>`;
					ov.querySelector( '.afp-modal__body' ).appendChild( box );
					info = box.querySelector( '[data-msg]' );
					const update = () => {
						const [ st, msg ] = verdict( inp.value.trim() );
						info.className = 'afp-newfile__msg is-' + st;
						info.innerHTML = icon( st === 'ok' ? 'check' : 'alert' ) + `<span>${ esc( msg ) }</span>`;
						box.querySelectorAll( '[data-ext]' ).forEach( ( b ) => b.classList.toggle( 'is-on', inp.value.toLowerCase().endsWith( '.' + b.dataset.ext ) ) );
					};
					box.addEventListener( 'click', ( e ) => {
						const b = e.target.closest( '[data-ext]' );
						if ( b ) {
							const stem = inp.value.includes( '.' ) ? inp.value.slice( 0, inp.value.lastIndexOf( '.' ) ) : inp.value || 'yeni-dosya';
							inp.value = stem + '.' + b.dataset.ext;
							inp.focus();
							inp.setSelectionRange( 0, stem.length );
							update();
						}
					} );
					inp.addEventListener( 'input', update );
					update();
				},
				collect: ( ov ) => {
					const v = ov.querySelector( '[data-in]' ).value.trim();
					return verdict( v )[ 0 ] === 'ok' ? { name: v, open: ov.querySelector( '[data-open]' ).checked } : null;
				},
			} ).then( ( r ) => {
				if ( ! r ) {
					return;
				}
				busy( true );
				return ajax( 'create_file', { name: r.name, folder: Math.max( 0, S.folder ) } )
					.then( ( d ) => {
						toast( d.message );
						if ( r.open ) {
							window.location.href = d.editUrl;
						} else {
							reload();
						}
					} )
					.catch( ( e ) => alertBox( e.message ) )
					.finally( () => busy( false ) );
			} );
		},
		history( id ) {
			return UI.historyModal( id ).then( ( item ) => item && load() );
		},
		help() {
			const keyHtml = ( k ) => ( k === ':arrows' ? [ 'arrowUp', 'arrowDown', 'chevronL', 'arrowRight' ].map( ( n ) => `<kbd class="afp-kbd-ic">${ icon( n ) }</kbd>` ).join( '' ) : `<kbd>${ esc( k ) }</kbd>` );
			const sc = T.shortcuts || [];
			const groups = [ [ T.helpGroups.select, sc.slice( 0, 3 ).concat( [ sc[ 10 ] ] ) ], [ T.helpGroups.files, sc.slice( 3, 10 ) ], [ T.helpGroups.nav, sc.slice( 11 ) ] ];
			const mIcons = { click: 'mouse', dbl: 'mouseDbl', menu: 'ctxmenu', move: 'move', cloud: 'cloud', touch: 'touch' };
			const keys = groups
				.map( ( g ) => `<div class="afp-help__group"><h4>${ esc( g[ 0 ] ) }</h4>${ g[ 1 ].filter( Boolean ).map( ( r ) => `<div class="afp-help__row"><span class="afp-help__keys">${ r[ 0 ].map( keyHtml ).join( '<i class="afp-help__plus">+</i>' ) }</span><span class="afp-help__desc">${ esc( r[ 1 ] ) }</span></div>` ).join( '' ) }</div>` )
				.join( '' );
			const mouse = ( T.mouse || [] ).map( ( r ) => `<div class="afp-help__card"><span class="afp-help__mi">${ icon( mIcons[ r[ 0 ] ] || 'mouse' ) }</span><div><strong>${ esc( r[ 1 ] ) }</strong><span>${ esc( r[ 2 ] ) }</span></div></div>` ).join( '' );
			return modal( {
				title: T.help,
				iconName: 'keyboard',
				size: 'lg',
				cancelText: '',
				confirmText: T.close,
				body: `<div class="afp-help"><section class="afp-help__col"><h3>${ icon( 'keyboard' ) }${ esc( T.helpKeyboard ) }</h3>${ keys }</section><section class="afp-help__col"><h3>${ icon( 'mouse' ) }${ esc( T.helpMouse ) }</h3><div class="afp-help__cards">${ mouse }</div></section></div>`,
			} );
		},
	};

	/* ---------- Detail drawer ---------- */
	const openDetail = ( id ) => {
		const it = itemById( id );
		if ( ! it ) {
			return;
		}
		S.detail = id;
		let prev = fileIcon( it );
		if ( it.group === 'image' ) {
			prev = `<img src="${ esc( it.thumb || it.url ) }" alt="">`;
		} else if ( it.group === 'video' ) {
			prev = `<video src="${ esc( it.url ) }" controls preload="metadata"></video>`;
		} else if ( it.group === 'audio' ) {
			prev = `${ fileIcon( it ) }<audio src="${ esc( it.url ) }" controls preload="none"></audio>`;
		}
		el.detail.innerHTML = `
			<div class="afp-detail__head"><strong>${ esc( T.details ) }</strong><button type="button" class="afp-btn-ic afp-btn-ic--sm" data-act="detail-close" aria-label="${ esc( T.close ) }">${ icon( 'x' ) }</button></div>
			<div class="afp-detail__prev ${ UI.previewable( it ) && it.group !== 'video' ? 'is-zoom' : '' }" ${ UI.previewable( it ) && it.group !== 'video' ? 'data-act="d-preview"' : '' }>${ prev }</div>
			<div class="afp-detail__name"><strong>${ esc( it.title ) }${ histBadge( it, 'is-row' ) }</strong><small>${ esc( it.filename ) }</small></div>
			<dl class="afp-detail__meta">
				<div><dt>${ esc( T.uploadedOn ) }</dt><dd>${ esc( it.dateFull ) }</dd></div>
				<div><dt>${ esc( T.uploadedBy ) }</dt><dd>${ it.authorUrl ? `<a href="${ esc( it.authorUrl ) }">${ esc( it.author ) }</a>` : esc( it.author ) }</dd></div>
				${ it.parent ? `<div class="is-wide"><dt>${ esc( T.attachedTo ) }</dt><dd><a href="${ esc( it.parent.url ) }">${ esc( it.parent.title ) }</a></dd></div>` : '' }
				<div class="is-wide"><dt>${ esc( T.fileName ) }</dt><dd>${ esc( it.filename ) }</dd></div>
				<div><dt>${ esc( T.type ) }</dt><dd>${ esc( it.mime ) }</dd></div>
				<div><dt>${ esc( T.size ) }</dt><dd>${ esc( it.sizeH ) }</dd></div>
				${ it.dims ? `<div><dt>${ esc( T.dimensions ) }</dt><dd>${ esc( it.dims ) } px</dd></div>` : '' }
			</dl>
			<form class="afp-detail__form" data-meta="${ it.id }">
				${ it.group === 'image' ? `<label class="afp-field"><span class="afp-field__label">${ esc( T.alt ) }</span><textarea class="afp-input" name="alt" rows="2">${ esc( it.alt ) }</textarea><span class="afp-field__hint">${ esc( T.altHint ) }</span></label>` : '' }
				<label class="afp-field"><span class="afp-field__label">${ esc( T.title ) }</span><input class="afp-input" name="title" value="${ esc( it.title ) }"></label>
				<label class="afp-field"><span class="afp-field__label">${ esc( T.caption ) }</span><textarea class="afp-input" name="caption" rows="2">${ esc( it.caption ) }</textarea></label>
				<label class="afp-field"><span class="afp-field__label">${ esc( T.description ) }</span><textarea class="afp-input" name="description" rows="3">${ esc( it.desc ) }</textarea></label>
				<label class="afp-field"><span class="afp-field__label">${ esc( T.fileUrl ) }</span><span class="afp-copy"><input class="afp-input" value="${ esc( it.url ) }" readonly data-selectall><button type="button" class="afp-btn-ic" data-act="d-copy" aria-label="${ esc( T.copyUrl ) }">${ icon( 'link' ) }</button></span></label>
				<button type="submit" class="afp-btn afp-btn--primary afp-btn--block">${ icon( 'save' ) }<span>${ esc( T.save ) }</span></button>
			</form>
			<div class="afp-detail__acts">
				${ UI.previewable( it ) ? `<button type="button" class="afp-btn" data-act="d-preview">${ icon( 'eye' ) }<span>${ esc( T.preview ) }</span></button>` : '' }
				<a class="afp-btn" href="${ esc( it.viewUrl ) }" target="_blank" rel="noopener">${ icon( 'external' ) }<span>${ esc( T.viewPage ) }</span></a>
				${ it.editUrl ? `<a class="afp-btn" href="${ esc( it.editUrl ) }">${ icon( 'sliders' ) }<span>${ esc( T.editFull ) }</span></a>` : '' }
				${ ( D.codeExt || [] ).includes( it.ext ) ? `<a class="afp-btn" href="${ esc( D.codeUrl + '&target=' + it.id ) }">${ icon( 'code' ) }<span>${ esc( T.editCode ) }</span></a>` : '' }
				${ it.renamed ? `<button type="button" class="afp-btn" data-act="d-history">${ icon( 'clock' ) }<span>${ esc( UI.T.history ) }</span></button>` : '' }
				<a class="afp-btn" href="${ esc( it.url ) }" download="${ esc( it.filename ) }">${ icon( 'download' ) }<span>${ esc( T.download ) }</span></a>
				<button type="button" class="afp-btn afp-btn--danger" data-act="d-delete">${ icon( 'trash' ) }<span>${ esc( T.delete ) }</span></button>
			</div>`;
		el.detail.hidden = false;
		root.classList.add( 'has-detail' );
	};
	const closeDetail = () => {
		S.detail = null;
		el.detail.hidden = true;
		root.classList.remove( 'has-detail' );
	};
	el.detail.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		const f = e.target;
		const fe = f.elements;
		const data = { id: f.dataset.meta, title: fe.title.value, caption: fe.caption.value, description: fe.description.value };
		if ( fe.alt ) {
			data.alt = fe.alt.value;
		}
		run( ajax( 'update_meta', data ) );
	} );
	el.detail.addEventListener( 'focusin', ( e ) => e.target.matches( '[data-selectall]' ) && e.target.select() );

	/* ---------- Context menu ---------- */
	let ctx = null;
	const closeCtx = () => {
		if ( ctx ) {
			ctx.remove();
			ctx = null;
		}
	};
	const showCtx = ( x, y, entries, head = '' ) => {
		closeCtx();
		const list = entries.filter( Boolean );
		ctx = document.createElement( 'div' );
		ctx.className = 'afp-ctx';
		ctx.setAttribute( 'role', 'menu' );
		ctx.innerHTML =
			( head ? `<div class="afp-ctx__head">${ esc( head ) }</div>` : '' ) +
			list.map( ( e, i ) => ( e === '-' ? '<hr>' : `<button type="button" role="menuitem" class="${ e.danger ? 'is-danger' : '' }" data-i="${ i }">${ icon( e.icon ) }<span>${ esc( e.label ) }</span>${ e.key ? `<kbd>${ esc( e.key ) }</kbd>` : '' }</button>` ) ).join( '' );
		document.body.appendChild( ctx );
		const r = ctx.getBoundingClientRect();
		ctx.style.left = Math.max( 8, Math.min( x, window.innerWidth - r.width - 8 ) ) + 'px';
		ctx.style.top = Math.max( 8, Math.min( y, window.innerHeight - r.height - 8 ) ) + 'px';
		ctx.addEventListener( 'click', ( e ) => {
			const b = e.target.closest( '[data-i]' );
			if ( b ) {
				const fn = list[ +b.dataset.i ].fn;
				closeCtx();
				fn();
			}
		} );
		ctx.addEventListener( 'keydown', ( e ) => {
			const btns = Array.from( ctx.querySelectorAll( 'button' ) );
			const i = btns.indexOf( document.activeElement );
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				btns[ ( i + ( e.key === 'ArrowDown' ? 1 : -1 ) + btns.length ) % btns.length ].focus();
			}
		} );
		const first = ctx.querySelector( 'button' );
		if ( first ) {
			first.focus( { preventScroll: true } );
		}
	};
	const itemMenu = ( id, x, y ) => {
		if ( ! S.selected.has( id ) ) {
			S.selected.clear();
			S.selected.add( id );
			renderBulk();
		}
		const ids = targetIds( id );
		const it = itemById( id );
		const multi = ids.length > 1;
		showCtx(
			x,
			y,
			[
				! multi && UI.previewable( it ) && { icon: 'eye', label: T.preview, key: 'P', fn: () => A.preview( id ) },
				! multi && { icon: 'info', label: T.details, key: 'Enter', fn: () => openDetail( id ) },
				! multi && it.editUrl && { icon: 'sliders', label: T.editFull, fn: () => ( window.location.href = it.editUrl ) },
				! multi && { icon: 'external', label: T.openNewTab, fn: () => window.open( it.url, '_blank', 'noopener' ) },
				! multi && ( D.codeExt || [] ).includes( it.ext ) && { icon: 'code', label: T.editCode, fn: () => ( window.location.href = D.codeUrl + '&target=' + it.id ) },
				'-',
				! multi && { icon: 'pencil', label: T.rename, key: 'F2', fn: () => A.rename( id ) },
				! multi && it.renamed && { icon: 'clock', label: UI.T.history + ' (' + it.renamed + ')', fn: () => A.history( id ) },
				{ icon: 'move', label: T.move, key: 'M', fn: () => A.pickMove( ids ) },
				{ icon: 'scissors', label: T.cut, key: 'Ctrl+X', fn: () => A.cut( ids ) },
				'-',
				D.zipOk && { icon: 'zip', label: T.zip, fn: () => A.zip( ids ) },
				D.zipOk && ! multi && it.ext === 'zip' && { icon: 'unzip', label: T.unzip, fn: () => A.unzip( id ) },
				{ icon: 'download', label: T.download, fn: () => A.download( ids ) },
				! multi && { icon: 'link', label: T.copyUrl, fn: () => UI.copy( it.url ) },
				'-',
				{ icon: 'trash', label: T.delete + ( multi ? ` (${ ids.length })` : '' ), key: 'Del', danger: true, fn: () => A.del( ids ) },
			],
			multi ? ids.length + ' ' + T.selected : it.filename
		);
	};
	const folderMenu = ( id, x, y ) => {
		const multi = S.fsel.has( id ) && S.fsel.size > 1;
		const ids = multi ? Array.from( S.fsel ) : [ id ];
		if ( multi ) {
			return showCtx(
				x,
				y,
				[
					{ icon: 'move', label: T.move, fn: () => A.pickMoveFolders( ids ) },
					D.zipOk && { icon: 'zip', label: T.zip, fn: () => A.zipFolders( ids ) },
					'-',
					{ icon: 'x', label: T.clear, fn: () => actions.fclear() },
					{ icon: 'trash', label: T.delete + ` (${ ids.length })`, key: 'Del', danger: true, fn: () => A.deleteFolders( ids ) },
				],
				ids.length + ' ' + T.foldersSelected
			);
		}
		return showCtx(
			x,
			y,
			[
				{ icon: 'folderOpen', label: T.open, fn: () => goFolder( id ) },
				{ icon: 'folderPlus', label: T.newSubfolder, fn: () => A.newFolder( id ) },
				( D.createExt || [] ).length && { icon: 'plus', label: T.newFile, fn: () => ( goFolder( id ), A.newFile() ) },
				S.clip.length && { icon: 'clipboard', label: T.paste, fn: () => A.moveFiles( S.clip.slice(), id ) },
				{ icon: 'folderCheck', label: S.fsel.has( id ) ? T.deselect : T.selectFolder, key: 'Ctrl+Click', fn: () => toggleFolderSel( id ) },
				'-',
				{ icon: 'pencil', label: T.rename, fn: () => A.renameFolder( id ) },
				{ icon: 'move', label: T.move, fn: () => A.pickMoveFolders( [ id ] ) },
				D.zipOk && { icon: 'zip', label: T.zip, fn: () => A.zipFolders( [ id ] ) },
				'-',
				{ icon: 'trash', label: T.delete, danger: true, fn: () => A.deleteFolders( [ id ] ) },
			],
			( folderById( id ) || {} ).name
		);
	};
	const blankMenu = ( x, y ) =>
		showCtx( x, y, [
			{ icon: 'cloud', label: T.upload, key: 'U', fn: () => el.file.click() },
			{ icon: 'link', label: T.fromCloud, fn: () => cloudMenu( x, y ) },
			{ icon: 'folderPlus', label: T.newFolder, key: 'N', fn: () => A.newFolder() },
			( D.createExt || [] ).length && { icon: 'plus', label: T.newFile, fn: () => A.newFile() },
			S.clip.length && { icon: 'clipboard', label: T.paste, key: 'Ctrl+V', fn: () => A.paste() },
			'-',
			{ icon: 'checkAll', label: T.selectAll, key: 'Ctrl+A', fn: () => actions[ 'select-all' ]() },
			{ icon: 'keyboard', label: T.help, key: '?', fn: () => A.help() },
		] );

	/* ---------- Cloud import ---------- */
	const C = D.cloud || {};
	const scripts = {};
	const loadScript = ( src, attrs = {} ) => {
		if ( scripts[ src ] ) {
			return scripts[ src ];
		}
		scripts[ src ] = new Promise( ( resolve, reject ) => {
			const s = document.createElement( 'script' );
			s.src = src;
			s.async = true;
			Object.keys( attrs ).forEach( ( k ) => s.setAttribute( k, attrs[ k ] ) );
			s.onload = resolve;
			s.onerror = () => {
				delete scripts[ src ];
				reject( new Error( T.cloudLoadFail ) );
			};
			document.head.appendChild( s );
		} );
		return scripts[ src ];
	};
	/* Cloud / URL imports go through the same queue card as uploads (one request per file). */
	const importFiles = ( provider, files, token ) => {
		if ( files && files.length ) {
			// eslint-disable-next-line no-use-before-define
			queueImports( provider, files.slice( 0, 50 ), token || '' );
		}
	};
	const cloud = {
		google() {
			const g = C.google;
			return loadScript( 'https://apis.google.com/js/api.js' )
				.then( () => new Promise( ( r ) => window.gapi.load( 'picker', r ) ) )
				.then( () => loadScript( 'https://accounts.google.com/gsi/client' ) )
				.then(
					() =>
						new Promise( ( resolve, reject ) => {
							const tc = window.google.accounts.oauth2.initTokenClient( {
								client_id: g.clientId,
								scope: 'https://www.googleapis.com/auth/drive.readonly',
								callback: ( r ) => ( r && r.access_token ? resolve( r.access_token ) : reject( new Error( ( r && r.error ) || T.cloudLoadFail ) ) ),
								error_callback: () => reject( new Error( T.cloudLoadFail ) ),
							} );
							tc.requestAccessToken();
						} )
				)
				.then( ( token ) => {
					const P = window.google.picker;
					const view = new P.DocsView( P.ViewId.DOCS ).setIncludeFolders( true ).setSelectFolderEnabled( false );
					const picker = new P.PickerBuilder()
						.addView( view )
						.addView( new P.DocsUploadView() )
						.enableFeature( P.Feature.MULTISELECT_ENABLED )
						.setOAuthToken( token )
						.setDeveloperKey( g.apiKey )
						.setLocale( document.documentElement.lang || 'en' )
						.setCallback( ( data ) => {
							if ( data[ P.Response.ACTION ] === P.Action.PICKED ) {
								importFiles(
									'google',
									data[ P.Response.DOCUMENTS ].map( ( d ) => ( { id: d[ P.Document.ID ], name: d[ P.Document.NAME ], mime: d[ P.Document.MIME_TYPE ], size: +d.sizeBytes || 0 } ) ),
									token
								);
							}
						} );
					if ( g.appId ) {
						picker.setAppId( g.appId );
					}
					picker.build().setVisible( true );
				} )
				.catch( ( e ) => alertBox( e.message || T.cloudLoadFail ) );
		},
		dropbox() {
			return loadScript( 'https://www.dropbox.com/static/api/2/dropins.js', { id: 'dropboxjs', 'data-app-key': C.dropbox.appKey } )
				.then( () =>
					window.Dropbox.choose( {
						linkType: 'direct',
						multiselect: true,
						success: ( files ) => importFiles( 'dropbox', files.map( ( f ) => ( { url: f.link, name: f.name, size: f.bytes || 0 } ) ) ),
					} )
				)
				.catch( ( e ) => alertBox( e.message ) );
		},
		onedrive() {
			return loadScript( 'https://js.live.net/v7.2/OneDrive.js' )
				.then( () =>
					window.OneDrive.open( {
						clientId: C.onedrive.clientId,
						action: 'download',
						multiSelect: true,
						advanced: { redirectUri: window.location.origin + window.location.pathname + '?page=foldpress' },
						success: ( r ) => importFiles( 'onedrive', ( r.value || [] ).map( ( f ) => ( { url: f[ '@microsoft.graph.downloadUrl' ], name: f.name, size: f.size || 0 } ) ) ),
						error: () => alertBox( T.cloudLoadFail ),
					} )
				)
				.catch( ( e ) => alertBox( e.message ) );
		},
		dav() {
			const picked = new Map();
			let path = '';
			return modal( {
				title: C.dav.label,
				subtitle: T.davTitle,
				iconName: 'cloud',
				size: 'lg',
				confirmText: fmt( T.importN, 0 ),
				body: '<div class="afp-dav"><div class="afp-dav__bar" data-dav-bar></div><div class="afp-dav__list" data-dav-list><div class="afp-boot"><span class="afp-spin"></span></div></div></div>',
				onOpen: ( ov ) => {
					const list = ov.querySelector( '[data-dav-list]' );
					const bar = ov.querySelector( '[data-dav-bar]' );
					const ok = ov.querySelector( '[data-ok]' );
					const sync = () => {
						ok.textContent = fmt( T.importN, picked.size );
						ok.disabled = ! picked.size;
					};
					const open = ( p ) => {
						list.innerHTML = '<div class="afp-boot"><span class="afp-spin"></span></div>';
						ajax( 'cloud_dav_list', { path: p } )
							.then( ( d ) => {
								path = d.path;
								const segs = path ? path.split( '/' ) : [];
								bar.innerHTML =
									`<button type="button" data-dav-go="">${ icon( 'cloud' ) }<span>${ esc( T.davRoot ) }</span></button>` +
									segs.map( ( s, i ) => icon( 'chevron' ) + `<button type="button" data-dav-go="${ esc( segs.slice( 0, i + 1 ).join( '/' ) ) }">${ esc( s ) }</button>` ).join( '' );
								list.innerHTML = d.items.length
									? d.items
											.map( ( x ) =>
												x.dir
													? `<button type="button" class="afp-dav__row is-dir" data-dav-go="${ esc( x.path ) }">${ icon( 'folder', 'afp-dav__dir' ) }<span>${ esc( x.name ) }</span>${ icon( 'chevron' ) }</button>`
													: `<label class="afp-dav__row"><input type="checkbox" data-dav-file="${ esc( x.path ) }" data-name="${ esc( x.name ) }" data-size="${ esc( x.size || 0 ) }" ${ picked.has( x.path ) ? 'checked' : '' }><span class="afp-dav__ext">${ esc( ( x.ext || '?' ).slice( 0, 4 ).toUpperCase() ) }</span><span>${ esc( x.name ) }</span><small>${ esc( sizeH( x.size ) ) }</small></label>`
											)
											.join( '' )
									: `<p class="afp-muted afp-dav__empty">${ esc( T.davEmpty ) }</p>`;
							} )
							.catch( ( e ) => ( list.innerHTML = `<p class="afp-field__err">${ esc( e.message ) }</p>` ) );
					};
					ov.addEventListener( 'click', ( e ) => {
						const g = e.target.closest( '[data-dav-go]' );
						if ( g ) {
							open( g.dataset.davGo );
						}
					} );
					ov.addEventListener( 'change', ( e ) => {
						const c = e.target.closest( '[data-dav-file]' );
						if ( c ) {
							if ( c.checked ) {
								picked.set( c.dataset.davFile, { name: c.dataset.name, size: +c.dataset.size || 0 } );
							} else {
								picked.delete( c.dataset.davFile );
							}
							sync();
						}
					} );
					sync();
					open( '' );
				},
				collect: () => ( picked.size ? Array.from( picked ).map( ( [ p, n ] ) => ( { path: p, name: n.name, size: n.size } ) ) : null ),
			} ).then( ( files ) => files && importFiles( 'dav', files ) );
		},
		url() {
			return modal( {
				title: T.fromUrl,
				iconName: 'link',
				size: 'md',
				confirmText: T.import,
				body: `<label class="afp-field"><span class="afp-field__label">${ esc( T.urlLabel ) }</span><textarea class="afp-input" rows="5" data-urls placeholder="https://example.com/file.pdf" spellcheck="false"></textarea><span class="afp-field__hint">${ esc( T.urlHint ) }</span></label>`,
				onOpen: ( ov ) => setTimeout( () => ov.querySelector( '[data-urls]' ).focus(), 60 ),
				collect: ( ov ) => {
					const urls = ov
						.querySelector( '[data-urls]' )
						.value.split( /\s+/ )
						.map( ( u ) => u.trim() )
						.filter( ( u ) => /^https?:\/\//i.test( u ) );
					return urls.length ? urls.slice( 0, 20 ) : null;
				},
			} ).then( ( urls ) => urls && importFiles( 'url', urls.map( ( u ) => ( { url: u } ) ) ) );
		},
	};
	const cloudMenu = ( x, y ) => {
		const items = [
			C.google && { icon: 'cloud', label: 'Google Drive', fn: () => cloud.google() },
			C.dropbox && { icon: 'cloud', label: 'Dropbox', fn: () => cloud.dropbox() },
			C.onedrive && { icon: 'cloud', label: 'OneDrive', fn: () => cloud.onedrive() },
			C.dav && { icon: 'cpu', label: C.dav.label, fn: () => cloud.dav() },
			C.url && { icon: 'link', label: T.fromUrl, fn: () => cloud.url() },
		].filter( Boolean );
		if ( D.cloudSetup ) {
			items.push( '-', { icon: 'gear', label: T.cloudSetupTxt, fn: () => ( window.location.href = D.cloudSetup ) } );
		}
		if ( ! items.length ) {
			return toast( T.cloudNone, 'info' );
		}
		showCtx( x, y, items, T.fromCloud );
	};

	/* ---------- Upload / import queue ---------- */
	const jobs = [];
	let active = 0;
	let hideTimer = null;
	let shownPct = 0;
	let targetPct = 0;
	let rafId = null;
	let tickId = null;
	let speedSample = { t: 0, b: 0, v: 0, start: 0, end: 0 };
	const isEnd = ( j ) => j.state === 'done' || j.state === 'error' || j.state === 'cancelled';
	const fmtTime = ( sec ) => {
		if ( ! isFinite( sec ) || sec <= 0 ) {
			return '-';
		}
		sec = Math.round( sec );
		return sec < 60 ? sec + 's' : Math.floor( sec / 60 ) + 'm ' + ( sec % 60 ) + 's';
	};
	const tweenPct = () => {
		shownPct += ( targetPct - shownPct ) * 0.18;
		if ( Math.abs( targetPct - shownPct ) < 0.4 ) {
			shownPct = targetPct;
		}
		$( '[data-upl-pct]' ).textContent = Math.round( shownPct );
		$( '[data-upl-bar]' ).style.width = shownPct + '%';
		rafId = shownPct === targetPct ? null : document.hidden ? ( shownPct = targetPct, tweenPct(), null ) : requestAnimationFrame( tweenPct );
	};
	/* Server-side downloads report no progress: estimate it from the measured rate or the average time per file. */
	const importStats = () => {
		const fin = jobs.filter( ( j ) => j.kind === 'import' && j.state === 'done' && j.secs );
		const secs = fin.reduce( ( s, j ) => s + j.secs, 0 );
		const bytes = fin.reduce( ( s, j ) => s + j.file.size, 0 );
		return { perFile: fin.length ? secs / fin.length : 4, rate: secs > 0 && bytes > 0 ? bytes / secs : 0 };
	};
	const fracOf = ( j, st ) => {
		if ( isEnd( j ) ) {
			return 1;
		}
		if ( j.state !== 'uploading' ) {
			return 0;
		}
		if ( j.kind !== 'import' ) {
			return j.file.size ? Math.min( 1, j.loaded / j.file.size ) : 0;
		}
		// Server progress: bytes written by the download, last few percent for the media processing.
		if ( j.phase === 'process' ) {
			return 0.97;
		}
		if ( j.total > 0 ) {
			return Math.min( 0.96, ( j.loaded / j.total ) * 0.96 );
		}
		const expect = j.file.size && st.rate ? j.file.size / st.rate : st.perFile;
		return Math.min( 0.95, 1 - Math.exp( -( ( performance.now() - j.t0 ) / 1000 ) / Math.max( 0.5, expect ) ) );
	};
	const renderUploadHead = () => {
		const now = performance.now();
		const st = importStats();
		const n = jobs.length || 1;
		const known = jobs.every( ( j ) => j.file.size > 0 );
		const total = jobs.reduce( ( s, j ) => s + j.file.size, 0 );
		const fracs = jobs.map( ( j ) => fracOf( j, st ) );
		const loaded = jobs.reduce( ( s, j, i ) => s + j.file.size * fracs[ i ], 0 );
		const count = ( state ) => jobs.filter( ( j ) => j.state === state ).length;
		const done = count( 'done' );
		const failed = count( 'error' );
		const cancelled = count( 'cancelled' );
		const finished = done + failed + cancelled;
		const allDone = finished === jobs.length;
		const imports = jobs.some( ( j ) => j.kind === 'import' );
		targetPct = Math.min( 100, known && total ? ( loaded / total ) * 100 : ( fracs.reduce( ( a, b ) => a + b, 0 ) / n ) * 100 );
		if ( ! rafId ) {
			rafId = requestAnimationFrame( tweenPct );
		}
		jobs.forEach( ( j, i ) => j.kind === 'import' && j.state === 'uploading' && ( j.bar.style.width = ( fracs[ i ] * 100 ).toFixed( 1 ) + '%' ) );
		// Real bytes moved: browser upload progress, server download progress for imports.
		const sent = jobs.reduce( ( x, j ) => x + ( j.state === 'done' ? j.file.size : j.state === 'uploading' ? j.loaded || 0 : 0 ), 0 );
		if ( ! speedSample.start && jobs.some( ( j ) => j.state === 'uploading' || j.state === 'done' ) ) {
			speedSample.start = now;
			speedSample.t = now;
			speedSample.b = sent;
		}
		if ( speedSample.start ) {
			const elapsed = Math.max( 0.2, ( now - speedSample.start ) / 1000 );
			const avg = sent / elapsed;
			if ( now - speedSample.t > 300 ) {
				const inst = ( ( sent - speedSample.b ) / ( now - speedSample.t ) ) * 1000;
				speedSample.v = speedSample.v ? speedSample.v * 0.6 + inst * 0.4 : inst;
				speedSample.t = now;
				speedSample.b = sent;
			}
			speedSample.rate = speedSample.v > 0 ? ( speedSample.v + avg ) / 2 : avg;
		}
		if ( allDone && speedSample.start && ! speedSample.end ) {
			speedSample.end = now;
		}
		el.upl.classList.toggle( 'is-done', allDone && ! failed );
		el.upl.classList.toggle( 'is-failed', allDone && !! failed );
		el.upl.classList.toggle( 'is-active', ! allDone );
		el.upl.classList.toggle( 'is-import', imports );
		if ( allDone ) {
			setTimeout( () => {
				shownPct = targetPct;
				$( '[data-upl-pct]' ).textContent = Math.round( shownPct );
				$( '[data-upl-bar]' ).style.width = shownPct + '%';
			}, 700 );
		}
		const cur = Math.min( finished + 1, jobs.length );
		$( '[data-upl-title]' ).textContent = allDone
			? failed ? ( imports ? T.importErrors : T.uploadFailed ) : imports ? T.importDone : T.uploadDone
			: fmt( imports ? T.importingOf : T.uploadingOf, cur, jobs.length );
		const chip = ( cls, ic, txt ) => `<span class="afp-upl__chip is-${ cls }">${ icon( ic ) }${ esc( txt ) }</span>`;
		const bytesTxt = total ? ( known ? `${ sizeH( Math.min( total, sent || loaded ) ) } / ${ sizeH( total ) }` : sizeH( sent ) ) : '';
		$( '[data-upl-sub]' ).innerHTML =
			chip( 'files', 'files', fmt( T.filesDone, done, jobs.length ) ) +
			( failed ? chip( 'err', 'alert', fmt( T.nFailed, failed ) ) : '' ) +
			( cancelled ? chip( 'off', 'x', fmt( T.nCancelled, cancelled ) ) : '' ) +
			( bytesTxt ? `<span class="afp-upl__bytes">${ esc( bytesTxt ) }</span>` : '' );
		let rate = speedSample.rate || 0;
		if ( imports && st.rate ) {
			rate = Math.max( rate, st.rate );
		}
		if ( allDone && speedSample.start ) {
			const secs = Math.max( 0.1, ( ( speedSample.end || now ) - speedSample.start ) / 1000 );
			$( '[data-upl-speed]' ).textContent = sent ? fmt( T.speed, sizeH( Math.round( sent / secs ) ) ) : '-';
			$( '[data-upl-eta]' ).textContent = fmt( T.took, secs < 1 ? secs.toFixed( 1 ) + 's' : fmtTime( secs ) );
		} else if ( imports ) {
			const left = known && rate ? ( total - loaded ) / rate : ( ( n - fracs.reduce( ( a, b ) => a + b, 0 ) ) * st.perFile ) / Math.max( 1, Math.min( 3, active ) );
			const measured = jobs.some( ( j ) => j.kind === 'import' && ( j.state === 'done' || j.loaded > 0 ) );
			$( '[data-upl-speed]' ).textContent = rate > 0 ? fmt( T.speed, sizeH( Math.round( rate ) ) ) : T.serverFetching;
			$( '[data-upl-eta]' ).textContent = measured || ( known && rate ) ? fmt( T.remaining, fmtTime( Math.max( 1, left ) ) ) : T.measuring;
		} else {
			$( '[data-upl-speed]' ).textContent = rate > 0 ? fmt( T.speed, sizeH( Math.round( rate ) ) ) : T.measuring;
			$( '[data-upl-eta]' ).textContent = rate > 0 ? fmt( T.remaining, fmtTime( Math.max( 1, ( total - loaded ) / rate ) ) ) : T.measuring;
		}
		$( '[data-upl-cancel]' ).hidden = allDone;
	};
	const STATUS_IC = { waiting: 'clock', uploading: 'upload', done: 'check', error: 'alert', cancelled: 'x' };
	const setJob = ( j, state, msg ) => {
		j.state = state;
		j.row.className = 'afp-up is-' + state;
		const text = msg || { waiting: T.waiting, uploading: j.kind === 'import' ? T.serverFetching : T.uploading, done: T.done, cancelled: T.cancelled }[ state ] || '';
		const s = j.row.querySelector( '.afp-up__status' );
		s.innerHTML = icon( STATUS_IC[ state ] || 'info' ) + `<span>${ esc( text ) }</span>`;
		s.title = text;
		const a = j.row.querySelector( '.afp-up__act' );
		a.innerHTML = icon( state === 'done' ? 'check' : state === 'error' ? 'alert' : 'x' );
		a.disabled = isEnd( j );
		renderUploadHead();
	};
	const finishAll = () => {
		if ( jobs.some( ( j ) => ! isEnd( j ) ) ) {
			return;
		}
		clearInterval( tickId );
		tickId = null;
		const ok = jobs.filter( ( j ) => j.state === 'done' ).length;
		if ( ok ) {
			toast( ( jobs.some( ( j ) => j.kind === 'import' ) ? T.importDone : T.uploadDone ) + ' (' + ok + ')' );
			reload();
		}
		if ( ! jobs.some( ( j ) => j.state === 'error' ) ) {
			hideTimer = setTimeout( closeUploads, 4500 );
		} else {
			el.upl.classList.add( 'is-open' );
		}
	};
	const doneMsg = ( j, it ) => ( it.ext && it.ext !== ( j.file.name.split( '.' ).pop() || '' ).toLowerCase() && [ 'webp', 'avif' ].includes( it.ext ) ? T.converted + ' ' + it.ext.toUpperCase() : '' );
	/* Live server download progress: one request for all running imports. */
	let pollOn = false;
	const importText = ( j ) => {
		if ( j.phase === 'process' ) {
			return T.serverProcessing;
		}
		if ( j.loaded > 0 ) {
			return j.total ? fmt( T.downloadedOf, sizeH( j.loaded ), sizeH( j.total ) ) : fmt( T.downloadedBytes, sizeH( j.loaded ) );
		}
		return T.serverFetching;
	};
	const pollImports = () => {
		const run = jobs.filter( ( j ) => j.kind === 'import' && j.state === 'uploading' );
		if ( ! run.length ) {
			pollOn = false;
			return;
		}
		ajax( 'import_progress', { jobs: run.map( ( j ) => j.job ) } )
			.then( ( d ) =>
				run.forEach( ( j ) => {
					const p = d && d[ j.job ];
					if ( ! p || j.state !== 'uploading' ) {
						return;
					}
					j.loaded = +p.got || 0;
					j.total = +p.total || 0;
					j.phase = p.phase;
					if ( j.total && j.file.size !== j.total ) {
						j.file.size = j.total;
						j.row.querySelector( '.afp-up__size' ).textContent = sizeH( j.total );
					}
					const s = j.row.querySelector( '.afp-up__status span' );
					if ( s ) {
						s.textContent = importText( j );
					}
				} )
			)
			.catch( () => {} )
			.then( () => setTimeout( pollImports, 700 ) );
	};
	const runImport = ( j ) => {
		j.ctrl = window.AbortController ? new AbortController() : null;
		const fd = new FormData();
		fd.append( 'action', 'foldpress_cloud_import' );
		fd.append( 'nonce', window.FoldPressUIData.nonce );
		fd.append( 'provider', j.provider );
		fd.append( 'token', j.token );
		fd.append( 'folder', j.folder );
		fd.append( 'job', j.job );
		fd.append( 'files', JSON.stringify( [ j.src ] ) );
		if ( ! tickId ) {
			tickId = setInterval( renderUploadHead, 400 );
		}
		if ( ! pollOn ) {
			pollOn = true;
			setTimeout( pollImports, 500 );
		}
		fetch( window.FoldPressUIData.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin', signal: j.ctrl ? j.ctrl.signal : undefined } )
			.then( ( r ) => r.json() )
			.then( ( r ) => {
				if ( j.state === 'cancelled' ) {
					return;
				}
				j.bar.style.width = '100%';
				const res = r && r.success && r.data.results ? r.data.results[ 0 ] : null;
				if ( res && res.ok ) {
					j.secs = ( performance.now() - j.t0 ) / 1000;
					j.file.size = res.size || j.file.size;
					j.row.querySelector( '.afp-up__size' ).textContent = sizeH( j.file.size );
					if ( res.name ) {
						const b = j.row.querySelector( '.afp-up__head b' );
						b.textContent = res.name;
						b.title = res.name;
					}
					setJob( j, 'done', doneMsg( j, res ) );
				} else {
					setJob( j, 'error', ( res && res.error ) || ( r && r.data && r.data.message ) || T.networkError );
				}
			} )
			.catch( () => j.state !== 'cancelled' && setJob( j, 'error', T.networkError ) )
			.finally( () => {
				active--;
				pump();
				finishAll();
			} );
	};
	const runUpload = ( j ) => {
		const fd = new FormData();
		fd.append( 'action', 'foldpress_upload' );
		fd.append( 'nonce', window.FoldPressUIData.nonce );
		fd.append( 'folder', j.folder );
		fd.append( 'file', j.file );
		const xhr = new XMLHttpRequest();
		j.xhr = xhr;
		xhr.open( 'POST', window.FoldPressUIData.ajaxUrl );
		xhr.upload.onprogress = ( e ) => {
			if ( e.lengthComputable ) {
				j.loaded = Math.min( e.loaded, j.file.size );
				j.bar.style.width = ( ( e.loaded / e.total ) * 100 ).toFixed( 1 ) + '%';
				renderUploadHead();
			}
		};
		xhr.onloadend = () => {
			active--;
			if ( j.state === 'cancelled' ) {
				pump();
				finishAll();
				return;
			}
			let r = null;
			try {
				r = JSON.parse( xhr.responseText );
			} catch ( e ) {}
			j.bar.style.width = '100%';
			if ( r && r.success ) {
				setJob( j, 'done', doneMsg( j, r.data.item || {} ) );
			} else {
				setJob( j, 'error', ( r && r.data && r.data.message ) || T.networkError );
			}
			pump();
			finishAll();
		};
		xhr.send( fd );
	};
	const pump = () => {
		jobs.filter( ( j ) => j.state === 'waiting' )
			.slice( 0, Math.max( 0, 3 - active ) )
			.forEach( ( j ) => {
				active++;
				j.t0 = performance.now();
				setJob( j, 'uploading' );
				( j.kind === 'import' ? runImport : runUpload )( j );
			} );
	};
	const cancelJob = ( j ) => {
		if ( isEnd( j ) ) {
			return;
		}
		const running = j.state === 'uploading';
		if ( running && j.kind === 'import' ) {
			// Aborting the request does not stop PHP: ask the server to drop the download too.
			ajax( 'import_cancel', { job: j.job } ).catch( () => {} );
		}
		setJob( j, 'cancelled' );
		if ( running && j.xhr ) {
			j.xhr.abort();
		} else if ( running && j.ctrl ) {
			j.ctrl.abort();
		} else {
			finishAll();
		}
	};
	const resetUploads = () => {
		jobs.forEach( ( j ) => j.thumbUrl && URL.revokeObjectURL( j.thumbUrl ) );
		jobs.length = 0;
		el.uplList.innerHTML = '';
		shownPct = 0;
		targetPct = 0;
		speedSample = { t: 0, b: 0, v: 0, start: 0, end: 0 };
	};
	const closeUploads = () => {
		clearTimeout( hideTimer );
		if ( jobs.some( ( j ) => ! isEnd( j ) ) ) {
			el.upl.classList.remove( 'is-open' );
			return;
		}
		el.upl.classList.add( 'is-hiding' );
		setTimeout( () => {
			el.upl.hidden = true;
			el.upl.classList.remove( 'is-hiding', 'is-open', 'is-done', 'is-failed', 'is-import' );
			resetUploads();
		}, 300 );
	};
	/* Adds rows to the card. Each entry: { name, size, group, thumbUrl?, extra job props }. */
	const enqueue = ( entries ) => {
		clearTimeout( hideTimer );
		if ( jobs.length && jobs.every( isEnd ) ) {
			resetUploads();
		}
		el.upl.hidden = false;
		el.upl.classList.remove( 'is-hiding', 'is-done', 'is-failed' );
		el.upl.classList.toggle( 'is-open', jobs.length + entries.length <= 8 );
		entries.forEach( ( j ) => {
			j.loaded = 0;
			j.state = 'waiting';
			j.job = Math.random().toString( 36 ).slice( 2, 10 ) + Date.now().toString( 36 );
			const ext = ( j.file.name.split( '.' ).pop() || '' ).toLowerCase();
			const row = document.createElement( 'div' );
			row.innerHTML = `<span class="afp-up__thumb">${ j.thumbUrl ? `<img src="${ esc( j.thumbUrl ) }" alt="">` : fileIcon( { group: j.group, ext } ) }${ j.kind === 'import' ? `<i class="afp-up__src">${ icon( j.provider === 'url' ? 'link' : 'cloud' ) }</i>` : '' }</span>
				<span class="afp-up__body"><span class="afp-up__head"><b title="${ esc( j.file.name ) }">${ esc( j.file.name ) }</b><span class="afp-up__size">${ j.file.size ? esc( sizeH( j.file.size ) ) : '' }</span></span><span class="afp-up__status"></span><i class="afp-up__bar"><em></em></i></span>
				<button type="button" class="afp-up__act" aria-label="${ esc( T.cancel ) }" title="${ esc( T.cancel ) }"></button>`;
			j.row = row;
			j.bar = row.querySelector( 'em' );
			row.querySelector( '.afp-up__act' ).addEventListener( 'click', () => cancelJob( j ) );
			el.uplList.appendChild( row );
			jobs.push( j );
			if ( j.tooBig ) {
				setJob( j, 'error', T.tooBig );
			} else {
				setJob( j, 'waiting' );
			}
		} );
		$( '[data-upl-title]' ).textContent = T.preparing;
		renderUploadHead();
		el.upl.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
		pump();
		finishAll();
	};
	const uploadFiles = ( files ) => {
		files = Array.from( files || [] );
		if ( ! files.length ) {
			return;
		}
		const folder = Math.max( 0, S.folder );
		enqueue(
			files.map( ( file ) => {
				const group = guessGroup( file );
				return {
					file,
					folder,
					group,
					thumbUrl: group === 'image' && file.size < 15 * 1024 * 1024 ? URL.createObjectURL( file ) : '',
					tooBig: !! ( D.maxUpload && file.size > D.maxUpload ),
				};
			} )
		);
	};
	/* Display name until the server reports the real one (links like /download?id=5 have none). */
	const urlName = ( u ) => {
		let n = u.split( /[?#]/ )[ 0 ].replace( /\/+$/, '' ).split( '/' ).pop() || '';
		try {
			n = decodeURIComponent( n );
		} catch ( e ) {}
		if ( ! /\.[a-z0-9]{2,5}$/i.test( n ) ) {
			try {
				n = new URL( u ).hostname + ( n ? ' / ' + n : '' );
			} catch ( e ) {}
		}
		return n;
	};
	const queueImports = ( provider, files, token ) => {
		const folder = Math.max( 0, S.folder );
		enqueue(
			files.map( ( f ) => {
				const name = f.name || ( f.url ? urlName( f.url ) : '' ) || 'file';
				const size = +f.size || 0;
				const src = Object.assign( {}, f );
				delete src.size;
				return {
					kind: 'import',
					provider,
					token,
					src,
					folder,
					file: { name, size },
					group: guessGroup( { name, type: f.mime || '' } ),
					tooBig: !! ( D.maxUpload && size > D.maxUpload ),
				};
			} )
		);
	};
	$( '[data-upl-cancel]' ).addEventListener( 'click', () => {
		const left = jobs.filter( ( j ) => ! isEnd( j ) );
		if ( ! left.length ) {
			return;
		}
		modal( { title: T.cancelAllTitle, body: `<p class="afp-modal__text">${ esc( fmt( T.cancelAllBody, left.length ) ) }</p>`, confirmText: T.cancelAll, danger: true, iconName: 'x' } ).then( ( ok ) => ok && left.forEach( cancelJob ) );
	} );
	$( '[data-upl-min]' ).addEventListener( 'click', () => el.upl.classList.toggle( 'is-open' ) );
	$( '[data-upl-close]' ).addEventListener( 'click', closeUploads );
	el.file.addEventListener( 'change', () => {
		uploadFiles( el.file.files );
		el.file.value = '';
	} );

	/* ---------- Drag & drop ---------- */
	let drag = null;
	const dragGhost = ( n ) => {
		const g = document.createElement( 'div' );
		g.className = 'afp-ghost';
		g.innerHTML = icon( 'files' ) + `<span>${ n }</span>`;
		document.body.appendChild( g );
		setTimeout( () => g.remove(), 0 );
		return g;
	};
	root.addEventListener( 'dragstart', ( e ) => {
		const c = e.target.closest( '[data-id]' );
		const fold = e.target.closest( '[data-folder]' );
		if ( c ) {
			const id = +c.dataset.id;
			if ( ! S.selected.has( id ) ) {
				S.selected.clear();
				S.selected.add( id );
				renderBulk();
			}
			drag = { type: 'items', ids: Array.from( S.selected ) };
			e.dataTransfer.setDragImage( dragGhost( drag.ids.length ), 20, 20 );
		} else if ( fold && +fold.dataset.folder > 0 ) {
			const fid = +fold.dataset.folder;
			drag = { type: 'folder', id: fid, ids: S.fsel.has( fid ) ? Array.from( S.fsel ) : [ fid ] };
		} else {
			return;
		}
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData( 'text/plain', 'foldpress' );
		root.classList.add( 'is-dragging' );
	} );
	root.addEventListener( 'dragend', () => {
		drag = null;
		root.classList.remove( 'is-dragging' );
		$$( '.is-drop' ).forEach( ( x ) => x.classList.remove( 'is-drop' ) );
	} );
	const isExternal = ( e ) => ! drag && e.dataTransfer && Array.from( e.dataTransfer.types || [] ).includes( 'Files' );
	let extDepth = 0;
	root.addEventListener( 'dragenter', ( e ) => {
		if ( isExternal( e ) ) {
			extDepth++;
			$( '.afp-drop small' ).textContent = T.dropInto + ' ' + ( S.folder > 0 ? folderLabel( S.folder ) : T.unassigned );
			root.classList.add( 'is-external' );
		}
	} );
	root.addEventListener( 'dragleave', ( e ) => {
		if ( isExternal( e ) && --extDepth <= 0 ) {
			extDepth = 0;
			root.classList.remove( 'is-external' );
		}
		const t = e.target.closest && e.target.closest( '[data-folder]' );
		if ( t && ! t.contains( e.relatedTarget ) ) {
			t.classList.remove( 'is-drop' );
		}
	} );
	root.addEventListener( 'dragover', ( e ) => {
		const t = e.target.closest( '[data-folder]' );
		if ( isExternal( e ) ) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'copy';
			$$( '.is-drop' ).forEach( ( x ) => x !== t && x.classList.remove( 'is-drop' ) );
			if ( t && +t.dataset.folder >= 0 ) {
				t.classList.add( 'is-drop' );
			}
			return;
		}
		if ( ! drag || ! t ) {
			return;
		}
		const fid = +t.dataset.folder;
		const okItems = drag.type === 'items' && fid >= 0;
		const okFolder = drag.type === 'folder' && fid >= 0 && drag.ids.every( ( x ) => fid !== x && ! ( fid > 0 && isDesc( fid, x ) ) );
		if ( okItems || okFolder ) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			t.classList.add( 'is-drop' );
		}
	} );
	root.addEventListener( 'drop', ( e ) => {
		const t = e.target.closest( '[data-folder]' );
		if ( isExternal( e ) ) {
			e.preventDefault();
			extDepth = 0;
			root.classList.remove( 'is-external' );
			$$( '.is-drop' ).forEach( ( x ) => x.classList.remove( 'is-drop' ) );
			if ( t && +t.dataset.folder >= 0 && +t.dataset.folder !== S.folder ) {
				S.folder = +t.dataset.folder;
				renderTree();
			}
			uploadFiles( e.dataTransfer.files );
			return;
		}
		if ( ! drag || ! t ) {
			return;
		}
		e.preventDefault();
		const fid = +t.dataset.folder;
		const d = drag;
		drag = null;
		if ( d.type === 'items' ) {
			A.moveFiles( d.ids, fid );
		} else {
			A.moveFolders( d.ids, fid );
		}
	} );

	/* ---------- Click / keyboard ---------- */
	const actions = {
		upload: () => el.file.click(),
		'cloud-menu': () => {
			const r = $( '[data-act="cloud-menu"]' ).getBoundingClientRect();
			cloudMenu( r.right - 240, r.bottom + 6 );
		},
		'new-folder': () => A.newFolder(),
		help: () => A.help(),
		'side-open': () => root.classList.add( 'side-open' ),
		'side-close': () => root.classList.remove( 'side-open' ),
		'view-grid': () => {
			S.view = 'grid';
			setPref( 'view', 'grid' );
			renderItems();
		},
		'view-list': () => {
			S.view = 'list';
			setPref( 'view', 'list' );
			renderItems();
		},
		grouped: () => {
			S.grouped = ! S.grouped;
			setPref( 'grouped', S.grouped );
			renderItems();
		},
		'select-folders': () => {
			const all = S.folders.length && S.folders.every( ( f ) => S.fsel.has( f.id ) );
			S.fsel.clear();
			if ( ! all ) {
				S.folders.forEach( ( f ) => S.fsel.add( f.id ) );
				// Show every folder so the selection is visible in the tree.
				S.folders.forEach( ( f ) => children( f.id ).length && S.open.add( f.id ) );
				renderTree();
			}
			renderFolderSel();
		},
		fclear: () => {
			S.fsel.clear();
			renderFolderSel();
		},
		'fbulk-move': () => A.pickMoveFolders( Array.from( S.fsel ) ),
		'fbulk-zip': () => A.zipFolders( Array.from( S.fsel ) ),
		'fbulk-delete': () => A.deleteFolders( Array.from( S.fsel ) ),
		'select-all': () => {
			const all = S.items.length && S.items.every( ( i ) => S.selected.has( i.id ) );
			S.selected.clear();
			if ( ! all ) {
				S.items.forEach( ( i ) => S.selected.add( i.id ) );
			}
			renderBulk();
		},
		clear: () => {
			S.selected.clear();
			renderBulk();
		},
		more: () => {
			S.page++;
			load( true );
		},
		'bulk-move': () => A.pickMove( Array.from( S.selected ) ),
		'bulk-cut': () => A.cut( Array.from( S.selected ) ),
		'bulk-zip': () => A.zip( Array.from( S.selected ) ),
		'bulk-download': () => A.download( Array.from( S.selected ) ),
		'bulk-delete': () => A.del( Array.from( S.selected ) ),
		'detail-close': closeDetail,
		'd-copy': () => UI.copy( itemById( S.detail ).url ),
		'd-preview': () => A.preview( S.detail ),
		'd-history': () => A.history( S.detail ),
		'd-delete': () => A.del( [ S.detail ] ),
	};

	root.addEventListener( 'click', ( e ) => {
		const act = e.target.closest( '[data-act]' );
		if ( act && actions[ act.dataset.act ] ) {
			actions[ act.dataset.act ]();
			return;
		}
		const pg = e.target.closest( '[data-page]' );
		if ( pg && ! pg.disabled ) {
			S.page = +pg.dataset.page;
			el.content.scrollTop = 0;
			load();
			return;
		}
		const tog = e.target.closest( '[data-toggle]' );
		if ( tog ) {
			const id = +tog.dataset.toggle;
			if ( S.open.has( id ) ) {
				S.open.delete( id );
			} else {
				S.open.add( id );
			}
			setPref( 'openFolders', Array.from( S.open ) );
			renderTree();
			return;
		}
		const fm = e.target.closest( '[data-fmenu]' );
		if ( fm ) {
			const r = fm.getBoundingClientRect();
			folderMenu( +fm.dataset.fmenu, r.left, r.bottom + 4 );
			return;
		}
		const im = e.target.closest( '[data-imenu]' );
		if ( im ) {
			const r = im.getBoundingClientRect();
			itemMenu( +im.closest( '[data-id]' ).dataset.id, r.left, r.bottom + 4 );
			return;
		}
		const hb = e.target.closest( '[data-history]' );
		if ( hb && hb.closest( '[data-id]' ) ) {
			A.history( +hb.closest( '[data-id]' ).dataset.id );
			return;
		}
		const pv = e.target.closest( '[data-preview]' );
		if ( pv ) {
			A.preview( +pv.closest( '[data-id]' ).dataset.id );
			return;
		}
		const f = e.target.closest( '[data-folder]' );
		if ( f ) {
			const fid = +f.dataset.folder;
			if ( fid > 0 && ( e.ctrlKey || e.metaKey || e.target.closest( '[data-fcheck]' ) || ( S.fsel.size && ( isTouch || f.classList.contains( 'afp-fcard' ) ) ) ) ) {
				toggleFolderSel( fid );
				return;
			}
			S.fsel.clear();
			goFolder( fid );
			return;
		}
		const c = e.target.closest( '[data-id]' );
		if ( c ) {
			selectClick( +c.dataset.id, e );
			return;
		}
		if ( e.target.closest( '.afp-content' ) && ! e.target.closest( 'button,a,input' ) ) {
			S.selected.clear();
			renderBulk();
		}
	} );
	root.addEventListener( 'dblclick', ( e ) => {
		const c = e.target.closest( '[data-id]' );
		if ( c && ! e.target.closest( 'button' ) ) {
			openDetail( +c.dataset.id );
		}
	} );
	root.addEventListener( 'contextmenu', ( e ) => {
		const c = e.target.closest( '[data-id]' );
		const f = e.target.closest( '[data-folder]' );
		if ( c ) {
			e.preventDefault();
			itemMenu( +c.dataset.id, e.clientX, e.clientY );
		} else if ( f && +f.dataset.folder > 0 ) {
			e.preventDefault();
			folderMenu( +f.dataset.folder, e.clientX, e.clientY );
		} else if ( e.target.closest( '.afp-content' ) ) {
			e.preventDefault();
			blankMenu( e.clientX, e.clientY );
		}
	} );

	// Long-press = context menu on touch devices.
	let lp = null;
	root.addEventListener(
		'touchstart',
		( e ) => {
			const c = e.target.closest( '[data-id],[data-folder]' );
			if ( ! c ) {
				return;
			}
			const t = e.touches[ 0 ];
			lp = setTimeout( () => {
				lp = 'fired';
				if ( navigator.vibrate ) {
					navigator.vibrate( 15 );
				}
				if ( c.dataset.id ) {
					itemMenu( +c.dataset.id, t.clientX, t.clientY );
				} else if ( +c.dataset.folder > 0 ) {
					folderMenu( +c.dataset.folder, t.clientX, t.clientY );
				}
			}, 550 );
		},
		{ passive: true }
	);
	const lpCancel = ( e ) => {
		if ( lp === 'fired' && e.type === 'touchend' ) {
			e.preventDefault();
		}
		clearTimeout( lp );
		lp = null;
	};
	root.addEventListener( 'touchend', lpCancel );
	root.addEventListener( 'touchmove', lpCancel, { passive: true } );

	document.addEventListener( 'mousedown', ( e ) => ctx && ! ctx.contains( e.target ) && ! e.target.closest( '[data-fmenu],[data-imenu]' ) && closeCtx() );
	window.addEventListener( 'resize', closeCtx );
	window.addEventListener( 'scroll', closeCtx, true );

	const moveFocus = ( focused, key ) => {
		const cards = $$( '[data-id]' );
		const i = cards.indexOf( focused );
		if ( i < 0 ) {
			if ( cards[ 0 ] ) {
				cards[ 0 ].focus();
			}
			return;
		}
		let step = 1;
		if ( key === 'ArrowUp' || key === 'ArrowDown' ) {
			const top = cards[ 0 ].offsetTop;
			step = Math.max( 1, cards.filter( ( c ) => c.offsetTop === top ).length );
		}
		const next = cards[ i + ( key === 'ArrowLeft' || key === 'ArrowUp' ? -step : step ) ];
		if ( next ) {
			next.focus();
		}
	};

	document.addEventListener( 'keydown', ( e ) => {
		if ( ctx && e.key === 'Escape' ) {
			closeCtx();
			return;
		}
		if ( document.querySelector( '.afp-modal-ov' ) || document.querySelector( '.afp-lb, .afp-dd__menu' ) ) {
			return;
		}
		const tag = document.activeElement.tagName;
		if ( /INPUT|TEXTAREA|SELECT/.test( tag ) ) {
			if ( e.key === 'Escape' && document.activeElement === el.search ) {
				el.search.blur();
			}
			return;
		}
		const mod = e.ctrlKey || e.metaKey;
		const k = e.key.length === 1 ? e.key.toLowerCase() : e.key;
		const focused = document.activeElement.closest && document.activeElement.closest( '[data-id]' );
		const sel = Array.from( S.selected );
		if ( mod && k === 'a' ) {
			e.preventDefault();
			actions[ 'select-all' ]();
		} else if ( mod && k === 'x' && sel.length ) {
			e.preventDefault();
			A.cut( sel );
		} else if ( mod && k === 'v' && S.clip.length ) {
			e.preventDefault();
			A.paste();
		} else if ( mod ) {
			return;
		} else if ( e.key === 'Delete' && S.fsel.size && ! sel.length ) {
			A.deleteFolders( Array.from( S.fsel ) );
		} else if ( e.key === 'Delete' && sel.length ) {
			A.del( sel );
		} else if ( e.key === 'F2' && sel.length === 1 ) {
			e.preventDefault();
			A.rename( sel[ 0 ] );
		} else if ( k === 'm' && sel.length ) {
			A.pickMove( sel );
		} else if ( k === 'p' && ( focused || sel.length === 1 ) ) {
			A.preview( focused ? +focused.dataset.id : sel[ 0 ] );
		} else if ( k === 'n' ) {
			e.preventDefault();
			A.newFolder();
		} else if ( k === 'u' ) {
			el.file.click();
		} else if ( k === '/' ) {
			e.preventDefault();
			el.search.focus();
		} else if ( k === '?' ) {
			A.help();
		} else if ( e.key === 'Escape' ) {
			if ( S.fsel.size ) {
				actions.fclear();
			} else if ( S.clip.length ) {
				S.clip = [];
				renderItems();
			} else if ( S.selected.size ) {
				actions.clear();
			} else {
				closeDetail();
				root.classList.remove( 'side-open' );
			}
		} else if ( /^Arrow/.test( e.key ) && ( focused || root.contains( document.activeElement ) ) ) {
			e.preventDefault();
			moveFocus( focused, e.key );
		} else if ( ( e.key === 'Enter' || e.key === ' ' ) && focused ) {
			e.preventDefault();
			if ( e.key === 'Enter' ) {
				openDetail( +focused.dataset.id );
			} else {
				selectClick( +focused.dataset.id, { ctrlKey: true, target: focused } );
			}
		} else if ( e.key === 'Enter' && document.activeElement.dataset && document.activeElement.dataset.folder ) {
			goFolder( +document.activeElement.dataset.folder );
		} else if ( e.key === 'ContextMenu' && focused ) {
			e.preventDefault();
			const r = focused.getBoundingClientRect();
			itemMenu( +focused.dataset.id, r.left + 20, r.top + 20 );
		}
	} );

	/* ---------- Filters ---------- */
	let st;
	el.search.addEventListener( 'input', ( e ) => {
		clearTimeout( st );
		st = setTimeout( () => {
			S.search = e.target.value.trim();
			reload();
		}, 300 );
	} );
	$( '[data-group]' ).addEventListener( 'change', ( e ) => {
		S.group = e.target.value;
		reload();
	} );
	$( '[data-sort]' ).addEventListener( 'change', ( e ) => {
		S.sort = e.target.value;
		setPref( 'sort', S.sort );
		reload();
	} );

	/* ---------- Boot ---------- */
	renderTree();
	renderItems();
	load().then( () => D.openUpload && el.file.click() );
} )();
