/**
 * FoldPress code editor (core CodeMirror via wp.CodeMirror).
 */
( function () {
	'use strict';

	const UI = window.FoldPressUI;
	const D = window.FoldPressCode || {};
	const T = D.i18n || {};
	const root = document.getElementById( 'foldpress-code' );
	const CM = window.wp && window.wp.CodeMirror;
	if ( ! root || ! UI ) {
		return;
	}
	const { esc, icon, ajax, toast, modal, alertBox } = UI;
	const $ = ( s, c = root ) => c.querySelector( s );
	const fmt = ( s, v ) => String( s ).replace( /%[sd]/, v );
	const sizeH = ( b ) => ( b < 1024 ? b + ' B' : b < 1048576 ? ( b / 1024 ).toFixed( 1 ) + ' KB' : ( b / 1048576 ).toFixed( 1 ) + ' MB' );
	const pref = ( k, d ) => {
		try {
			const v = window.localStorage.getItem( 'foldpress_code_' + k );
			return v === null ? d : JSON.parse( v );
		} catch ( e ) {
			return d;
		}
	};
	const setPref = ( k, v ) => {
		try {
			window.localStorage.setItem( 'foldpress_code_' + k, JSON.stringify( v ) );
		} catch ( e ) {}
	};

	if ( D.disabled ) {
		root.innerHTML = `<div class="afp-empty"><span class="afp-empty__ic">${ icon( 'alert' ) }</span><strong>${ esc( T.disabled ) }</strong></div>`;
		return;
	}

	/* ---------- Languages ---------- */
	const LANG = {
		php: [ 'PHP', 'application/x-httpd-php', '#7c3aed' ], phtml: [ 'PHP', 'application/x-httpd-php', '#7c3aed' ], inc: [ 'PHP', 'application/x-httpd-php', '#7c3aed' ],
		css: [ 'CSS', 'text/css', '#2563eb' ], scss: [ 'SCSS', 'text/x-scss', '#db2777' ], less: [ 'LESS', 'text/x-less', '#1d4ed8' ], sass: [ 'Sass', 'text/x-sass', '#db2777' ],
		js: [ 'JavaScript', 'text/javascript', '#ca8a04' ], mjs: [ 'JavaScript', 'text/javascript', '#ca8a04' ], jsx: [ 'JSX', 'text/jsx', '#0891b2' ],
		ts: [ 'TypeScript', 'application/typescript', '#2563eb' ], tsx: [ 'TSX', 'text/typescript-jsx', '#2563eb' ],
		json: [ 'JSON', 'application/json', '#16a34a' ], html: [ 'HTML', 'text/html', '#ea580c' ], htm: [ 'HTML', 'text/html', '#ea580c' ], vue: [ 'Vue', 'text/html', '#10b981' ], twig: [ 'Twig', 'text/html', '#65a30d' ],
		xml: [ 'XML', 'application/xml', '#ea580c' ], svg: [ 'SVG', 'application/xml', '#f97316' ],
		md: [ 'Markdown', 'text/x-markdown', '#475569' ], markdown: [ 'Markdown', 'text/x-markdown', '#475569' ],
		sql: [ 'SQL', 'text/x-sql', '#0369a1' ], yml: [ 'YAML', 'text/x-yaml', '#be123c' ], yaml: [ 'YAML', 'text/x-yaml', '#be123c' ],
		sh: [ 'Shell', 'text/x-sh', '#334155' ], bash: [ 'Shell', 'text/x-sh', '#334155' ],
		java: [ 'Java', 'text/x-java', '#b45309' ], cs: [ 'C#', 'text/x-csharp', '#7e22ce' ], c: [ 'C', 'text/x-csrc', '#1e40af' ], h: [ 'C header', 'text/x-csrc', '#1e40af' ],
		cpp: [ 'C++', 'text/x-c++src', '#1d4ed8' ], hpp: [ 'C++', 'text/x-c++src', '#1d4ed8' ], cc: [ 'C++', 'text/x-c++src', '#1d4ed8' ], kt: [ 'Kotlin', 'text/x-kotlin', '#7c3aed' ],
		py: [ 'Python', 'text/x-python', '#0284c7' ], rb: [ 'Ruby', 'text/x-ruby', '#dc2626' ], go: [ 'Go', 'text/x-go', '#0891b2' ], rs: [ 'Rust', 'text/x-rustsrc', '#b45309' ], swift: [ 'Swift', 'text/x-swift', '#ea580c' ],
		diff: [ 'Diff', 'text/x-diff', '#64748b' ], patch: [ 'Diff', 'text/x-diff', '#64748b' ], conf: [ 'Config', 'text/x-nginx-conf', '#64748b' ], ini: [ 'INI', 'text/plain', '#64748b' ],
		csv: [ 'CSV', 'text/plain', '#16a34a' ], txt: [ 'Text', 'text/plain', '#64748b' ], text: [ 'Text', 'text/plain', '#64748b' ], log: [ 'Log', 'text/plain', '#64748b' ],
	};
	const lang = ( ext ) => LANG[ ext ] || [ ( ext || 'TXT' ).toUpperCase(), 'text/plain', '#64748b' ];
	// Only use a mode CodeMirror actually has; otherwise plain text.
	const modeFor = ( ext ) => {
		const mime = lang( ext )[ 1 ];
		if ( ! CM ) {
			return 'text/plain';
		}
		if ( CM.mimeModes && CM.mimeModes[ mime ] ) {
			const spec = CM.mimeModes[ mime ];
			const name = typeof spec === 'string' ? spec : spec.name;
			return CM.modes && CM.modes[ name ] ? mime : 'text/plain';
		}
		return 'text/plain';
	};
	const lintFor = ( ext ) => {
		const map = { css: 'css', scss: 'css', js: 'javascript', mjs: 'javascript', json: 'json', html: 'html', htm: 'html' };
		const name = map[ ext ];
		return !! ( name && CM && CM.helpers && CM.helpers.lint && CM.helpers.lint[ name ] );
	};
	const badge = ( ext ) => {
		const l = lang( ext );
		return `<span class="afp-code__ext" style="--c:${ l[ 2 ] }">${ esc( ( ext || 'txt' ).slice( 0, 4 ).toUpperCase() ) }</span>`;
	};

	/* ---------- State ---------- */
	const S = {
		type: D.type,
		target: D.target,
		files: [],
		tabs: [],
		active: null,
		open: new Set( pref( 'open', [] ) ),
		dark: pref( 'dark', true ),
		wrap: pref( 'wrap', false ),
		font: pref( 'font', 14 ),
		full: pref( 'full', false ),
		find: { q: '', caseS: false, marks: [], hits: [], cur: -1 },
	};
	const tabByFile = ( f ) => S.tabs.find( ( t ) => t.file === f );
	const dirtyTabs = () => S.tabs.filter( ( t ) => ! t.doc.isClean( t.gen ) );

	/* ---------- Layout ---------- */
	const targetOptions = () => {
		if ( S.type === 'media' ) {
			return '';
		}
		const list = S.type === 'theme' ? D.themes : D.plugins;
		return list.map( ( x ) => `<option value="${ esc( x.value ) }" ${ x.value === S.target ? 'selected' : '' }>${ esc( x.label ) }${ x.active ? ' (' + esc( T.active ) + ')' : '' }</option>` ).join( '' );
	};
	root.innerHTML = `
	<header class="afp-code__bar">
		<div class="afp-code__scope">
			${
				S.type === 'media'
					? `<a class="afp-btn" href="${ esc( D.libraryUrl ) }">${ icon( 'arrowLeft' ) }<span>${ esc( T.library ) }</span></a><span class="afp-code__media">${ icon( 'files' ) }<b>${ esc( D.mediaTitle ) }</b></span>`
					: `<div class="afp-code__seg" role="tablist">
					${ D.themes.length ? `<button type="button" data-scope="theme" class="${ S.type === 'theme' ? 'is-on' : '' }">${ icon( 'layers' ) }<span>${ esc( T.theme ) }</span></button>` : '' }
					${ D.plugins.length ? `<button type="button" data-scope="plugin" class="${ S.type === 'plugin' ? 'is-on' : '' }">${ icon( 'zap' ) }<span>${ esc( T.plugin ) }</span></button>` : '' }
				</div>
				<select class="afp-select afp-code__target" data-target aria-label="${ esc( T.theme ) }">${ targetOptions() }</select>`
			}
		</div>
		<div class="afp-code__docs" data-docs hidden>
			<select class="afp-select" data-fn aria-label="${ esc( T.docs ) }"><option value="">${ esc( T.fnName ) }</option></select>
			<button type="button" class="afp-btn" data-act="lookup" title="${ esc( T.lookUpHint ) }">${ icon( 'search' ) }<span>${ esc( T.lookUp ) }</span></button>
		</div>
		<div class="afp-code__tools">
			<button type="button" class="afp-btn-ic" data-act="find" title="${ esc( T.find ) } (Ctrl+F)" aria-label="${ esc( T.find ) }">${ icon( 'search' ) }</button>
			<button type="button" class="afp-btn-ic" data-act="goto" title="${ esc( T.goToLine ) } (Ctrl+G)" aria-label="${ esc( T.goToLine ) }">${ icon( 'list' ) }</button>
			<button type="button" class="afp-btn-ic ${ S.wrap ? 'is-on' : '' }" data-act="wrap" title="${ esc( T.wrap ) }" aria-label="${ esc( T.wrap ) }">${ icon( 'menu' ) }</button>
			<button type="button" class="afp-btn-ic ${ S.dark ? 'is-on' : '' }" data-act="theme" title="${ esc( T.darkTheme ) }" aria-label="${ esc( T.darkTheme ) }">${ icon( 'eye' ) }</button>
			<span class="afp-code__font"><button type="button" data-act="font-" aria-label="${ esc( T.fontSmaller ) }">A-</button><b data-font>${ S.font }</b><button type="button" data-act="font+" aria-label="${ esc( T.fontLarger ) }">A+</button></span>
			<button type="button" class="afp-btn-ic" data-act="full" title="${ esc( T.fullscreen ) } (F11)" aria-label="${ esc( T.fullscreen ) }">${ icon( S.full ? 'shrink' : 'expand' ) }</button>
			<button type="button" class="afp-btn afp-btn--primary" data-act="save" disabled title="Ctrl+S">${ icon( 'save' ) }<span>${ esc( T.save ) }</span><i class="afp-code__dirtyn" data-dirtyn hidden></i></button>
		</div>
	</header>
	<div class="afp-code__body">
		<aside class="afp-code__side">
			<div class="afp-code__side-head"><strong>${ icon( 'folderOpen' ) }${ esc( T.files ) }</strong><small data-count></small></div>
			<label class="afp-search afp-code__filter">${ icon( 'search' ) }<input type="search" data-filter placeholder="${ esc( T.filterFiles ) }"></label>
			<nav class="afp-code__tree" data-tree><div class="afp-boot"><span class="afp-spin"></span></div></nav>
			${ S.type !== 'media' ? `<a class="afp-code__classic" href="${ esc( D.classicUrl ) }">${ icon( 'external' ) }<span>${ esc( T.classic ) }</span></a>` : '' }
		</aside>
		<section class="afp-code__main">
			<div class="afp-code__tabs" data-tabs role="tablist"></div>
			<div class="afp-code__crumbs" data-crumbs hidden></div>
			<div class="afp-code__ro" data-ro hidden>${ icon( 'alert' ) }<span>${ esc( T.readOnly ) }</span><a href="https://developer.wordpress.org/advanced-administration/server/file-permissions/" target="_blank" rel="noopener">${ esc( T.permHelp ) }</a></div>
			<div class="afp-code__find" data-findbar hidden>
				<div class="afp-code__find-row">
					<label class="afp-search">${ icon( 'search' ) }<input type="text" data-fq placeholder="${ esc( T.find ) }" spellcheck="false"></label>
					<span class="afp-code__find-n" data-fn-count></span>
					<button type="button" class="afp-btn-ic afp-btn-ic--sm" data-act="find-prev" aria-label="Previous">${ icon( 'arrowUp' ) }</button>
					<button type="button" class="afp-btn-ic afp-btn-ic--sm" data-act="find-next" aria-label="Next">${ icon( 'arrowDown' ) }</button>
					<label class="afp-code__case" title="${ esc( T.matchCase ) }"><input type="checkbox" data-fcase><span>Aa</span></label>
					<button type="button" class="afp-btn-ic afp-btn-ic--sm" data-act="find-close" aria-label="${ esc( T.close ) }">${ icon( 'x' ) }</button>
				</div>
				<div class="afp-code__find-row">
					<label class="afp-search">${ icon( 'pencil' ) }<input type="text" data-fr placeholder="${ esc( T.replace ) }" spellcheck="false"></label>
					<button type="button" class="afp-btn" data-act="replace-one">${ esc( T.replace ) }</button>
					<button type="button" class="afp-btn" data-act="replace-all">${ esc( T.replaceAll ) }</button>
				</div>
			</div>
			<div class="afp-code__editor ${ S.dark ? 'is-dark' : 'is-light' }" data-editor></div>
			<div class="afp-code__empty" data-empty><span class="afp-empty__ic">${ icon( 'files' ) }</span><strong>${ esc( T.pickFile ) }</strong><small>${ esc( T.shortcuts ) }</small></div>
			<footer class="afp-code__status" data-status></footer>
		</section>
	</div>`;
	UI.enhanceSelects( root );

	/* ---------- CodeMirror ---------- */
	if ( ! CM ) {
		$( '[data-editor]' ).innerHTML = `<div class="afp-empty"><strong>CodeMirror</strong></div>`;
		return;
	}
	const save = () => saveActive();
	const cm = CM( $( '[data-editor]' ), {
		value: '',
		mode: 'text/plain',
		theme: 'afp',
		lineNumbers: true,
		lineWrapping: S.wrap,
		indentUnit: 4,
		tabSize: 4,
		indentWithTabs: true,
		matchBrackets: true,
		autoCloseBrackets: true,
		autoCloseTags: true,
		matchTags: { bothTags: true },
		styleActiveLine: true,
		showTrailingSpace: true,
		foldGutter: !! ( CM.fold ),
		gutters: [ 'CodeMirror-lint-markers', 'CodeMirror-linenumbers' ].concat( CM.fold ? [ 'CodeMirror-foldgutter' ] : [] ),
		extraKeys: {
			'Ctrl-S': save,
			'Cmd-S': save,
			'Ctrl-F': () => openFind(),
			'Cmd-F': () => openFind(),
			'Ctrl-H': () => openFind( true ),
			'Ctrl-G': () => gotoLine(),
			'Cmd-G': () => gotoLine(),
			'Ctrl-/': 'toggleComment',
			'Cmd-/': 'toggleComment',
			Esc: () => closeFind(),
		},
	} );
	const applyView = () => {
		root.style.setProperty( '--afp-code-font', S.font + 'px' );
		$( '[data-editor]' ).className = 'afp-code__editor ' + ( S.dark ? 'is-dark' : 'is-light' );
		root.classList.toggle( 'is-dark', S.dark );
		root.classList.toggle( 'is-full', S.full );
		document.documentElement.classList.toggle( 'afp-code-full', S.full );
		const fb = $( '[data-act="full"]' );
		if ( fb ) {
			fb.innerHTML = icon( S.full ? 'shrink' : 'expand' );
			fb.classList.toggle( 'is-on', S.full );
		}
		$( '[data-font]' ).textContent = S.font;
		cm.setOption( 'lineWrapping', S.wrap );
		cm.refresh();
	};
	applyView();

	/* ---------- Tree ---------- */
	const buildTree = ( files ) => {
		const tree = { dirs: {}, files: [] };
		files.forEach( ( f ) => {
			const parts = f.path.split( '/' );
			let node = tree;
			parts.slice( 0, -1 ).forEach( ( p, i ) => {
				const key = parts.slice( 0, i + 1 ).join( '/' );
				node.dirs[ p ] = node.dirs[ p ] || { dirs: {}, files: [], key };
				node = node.dirs[ p ];
			} );
			node.files.push( f );
		} );
		return tree;
	};
	const fileRow = ( f, depth, label ) => {
		const t = tabByFile( f.file );
		const dirty = t && ! t.doc.isClean( t.gen );
		return `<button type="button" class="afp-code__file ${ S.active && S.active.file === f.file ? 'is-active' : '' } ${ dirty ? 'is-dirty' : '' }" data-file="${ esc( f.file ) }" style="--d:${ depth }" title="${ esc( f.path ) }">${ badge( f.ext ) }<span>${ esc( label || f.path.split( '/' ).pop() ) }</span>${ f.writable ? '' : `<i class="afp-code__lock" title="${ esc( T.readOnlyShort ) }">${ icon( 'alert' ) }</i>` }</button>`;
	};
	const dirHtml = ( node, depth ) =>
		Object.keys( node.dirs )
			.sort( ( a, b ) => a.localeCompare( b ) )
			.map( ( name ) => {
				const d = node.dirs[ name ];
				const open = S.open.has( d.key );
				return `<div class="afp-code__dir ${ open ? 'is-open' : '' }"><button type="button" class="afp-code__dirbtn" data-dir="${ esc( d.key ) }" style="--d:${ depth }">${ icon( 'chevron', 'afp-code__chev' ) }${ icon( open ? 'folderOpen' : 'folder', 'afp-code__fic' ) }<span>${ esc( name ) }</span></button>${ open ? `<div class="afp-code__kids">${ dirHtml( d, depth + 1 ) }</div>` : '' }</div>`;
			} )
			.join( '' ) + node.files.map( ( f ) => fileRow( f, depth ) ).join( '' );
	const renderTree = () => {
		const q = $( '[data-filter]' ).value.trim().toLowerCase();
		const el = $( '[data-tree]' );
		$( '[data-count]' ).textContent = S.files.length;
		if ( ! S.files.length ) {
			el.innerHTML = `<p class="afp-muted afp-code__none">${ esc( T.noFiles ) }</p>`;
			return;
		}
		el.innerHTML = q
			? S.files.filter( ( f ) => f.path.toLowerCase().includes( q ) ).map( ( f ) => fileRow( f, 0, f.path ) ).join( '' ) || `<p class="afp-muted afp-code__none">${ esc( T.noFiles ) }</p>`
			: dirHtml( buildTree( S.files ), 0 );
	};
	const revealInTree = ( file ) => {
		const f = S.files.find( ( x ) => x.file === file );
		if ( ! f ) {
			return;
		}
		const parts = f.path.split( '/' );
		parts.slice( 0, -1 ).forEach( ( p, i ) => S.open.add( parts.slice( 0, i + 1 ).join( '/' ) ) );
		setPref( 'open', Array.from( S.open ) );
	};

	/* ---------- Tabs ---------- */
	const renderTabs = () => {
		$( '[data-tabs]' ).innerHTML = S.tabs
			.map( ( t ) => {
				const dirty = ! t.doc.isClean( t.gen );
				return `<div class="afp-code__tab ${ S.active === t ? 'is-active' : '' } ${ dirty ? 'is-dirty' : '' }" role="tab" aria-selected="${ S.active === t }" data-tab="${ esc( t.file ) }" title="${ esc( t.path ) }">${ badge( t.ext ) }<span>${ esc( t.path.split( '/' ).pop() ) }</span><button type="button" class="afp-code__tabx" data-close="${ esc( t.file ) }" aria-label="${ esc( T.close ) }"><i></i>${ icon( 'x' ) }</button></div>`;
			} )
			.join( '' );
		const n = dirtyTabs().length;
		const sb = $( '[data-act="save"]' );
		sb.disabled = ! S.active || S.active.doc.isClean( S.active.gen ) || ! S.active.writable;
		const dn = $( '[data-dirtyn]' );
		dn.hidden = n < 1;
		dn.textContent = n;
		$( '[data-empty]' ).hidden = !! S.active;
		$( '[data-editor]' ).hidden = ! S.active;
		$( '[data-status]' ).hidden = ! S.active;
		const at = $( '.afp-code__tab.is-active' );
		if ( at ) {
			at.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
		}
	};
	const renderStatus = () => {
		const t = S.active;
		if ( ! t ) {
			return;
		}
		const c = cm.getCursor();
		const sel = cm.getSelection();
		$( '[data-status]' ).innerHTML = `
			<span>${ esc( T.line ) } ${ c.line + 1 }, ${ esc( T.col ) } ${ c.ch + 1 }${ sel ? ` <em>(${ sel.length } ${ esc( T.selected ) })</em>` : '' }</span>
			<span>${ cm.lineCount() } ${ esc( T.lines ) }</span>
			<span>${ sizeH( new Blob( [ cm.getValue() ] ).size ) }</span>
			<span class="afp-code__sp"></span>
			<span>${ esc( T.modified ) }: ${ esc( t.modified ) }</span>
			<span>UTF-8</span><span>${ esc( T.tabs ) }: 4</span>
			<span class="afp-code__lang" style="--c:${ lang( t.ext )[ 2 ] }">${ esc( lang( t.ext )[ 0 ] ) }</span>
			<span class="${ t.writable ? 'is-ok' : 'is-ro' }">${ esc( t.writable ? T.writable : T.readOnlyShort ) }</span>`;
	};
	const renderDocs = () => {
		const t = S.active;
		const box = $( '[data-docs]' );
		const sel = $( '[data-fn]' );
		const fns = t && t.functions ? t.functions : [];
		box.hidden = ! t || ! /^(php|phtml|inc)$/.test( t.ext );
		sel.innerHTML = `<option value="">${ esc( T.fnName ) }</option>` + fns.map( ( f ) => `<option value="${ esc( f ) }">${ esc( f ) }()</option>` ).join( '' );
		UI.syncSelect( sel );
	};
	const activate = ( t ) => {
		S.active = t;
		cm.swapDoc( t.doc );
		cm.setOption( 'readOnly', ! t.writable );
		cm.setOption( 'lint', t.lint ? { esversion: 11, options: { esversion: 11 } } : false );
		$( '[data-ro]' ).hidden = t.writable;
		const scopeLabel = S.type === 'theme' ? T.theme : S.type === 'plugin' ? T.plugin : T.media;
		const tgt = S.type === 'media' ? D.mediaTitle : ( ( S.type === 'theme' ? D.themes : D.plugins ).find( ( x ) => x.value === S.target ) || {} ).label || S.target;
		const segs = t.path.split( '/' );
		const sep = icon( 'chevron' );
		$( '[data-crumbs]' ).hidden = false;
		$( '[data-crumbs]' ).innerHTML = [ esc( scopeLabel ), esc( tgt ) ].concat( segs.slice( 0, -1 ).map( esc ) ).join( sep ) + sep + badge( t.ext ) + `<b>${ esc( segs[ segs.length - 1 ] ) }</b>`;
		revealInTree( t.file );
		renderTabs();
		renderTree();
		renderStatus();
		renderDocs();
		clearMarks();
		if ( ! $( '[data-findbar]' ).hidden ) {
			runFind();
		}
		cm.refresh();
		cm.focus();
		const url = new URL( window.location.href );
		url.searchParams.set( 'type', S.type );
		url.searchParams.set( 'target', S.target );
		url.searchParams.set( 'file', t.file );
		window.history.replaceState( null, '', url );
	};
	const openFile = ( file ) => {
		const ex = tabByFile( file );
		if ( ex ) {
			return activate( ex );
		}
		const f = S.files.find( ( x ) => x.file === file );
		if ( ! f ) {
			return;
		}
		root.classList.add( 'is-busy' );
		return ajax( 'code_read', { type: S.type, target: S.target, file } )
			.then( ( d ) => {
				const t = {
					file,
					path: f.path,
					ext: f.ext,
					doc: CM.Doc( d.content, modeFor( f.ext ) ),
					hash: d.hash,
					writable: d.writable,
					modified: d.modified,
					functions: d.functions,
					coreNonce: d.coreNonce,
					lint: lintFor( f.ext ),
				};
				t.gen = t.doc.changeGeneration( true );
				S.tabs.push( t );
				activate( t );
			} )
			.catch( ( e ) => alertBox( e.message ) )
			.finally( () => root.classList.remove( 'is-busy' ) );
	};
	const closeTab = ( file ) => {
		const t = tabByFile( file );
		if ( ! t ) {
			return;
		}
		const go = () => {
			const i = S.tabs.indexOf( t );
			S.tabs.splice( i, 1 );
			if ( S.active === t ) {
				const next = S.tabs[ i ] || S.tabs[ i - 1 ];
				if ( next ) {
					activate( next );
				} else {
					S.active = null;
					cm.swapDoc( CM.Doc( '', 'text/plain' ) );
					$( '[data-crumbs]' ).hidden = true;
					renderTabs();
					renderTree();
					renderDocs();
				}
			} else {
				renderTabs();
			}
		};
		if ( t.doc.isClean( t.gen ) ) {
			return go();
		}
		modal( { title: T.unsavedTitle, subtitle: t.path, body: `<p class="afp-modal__text">${ esc( T.unsavedClose ) }</p>`, confirmText: T.closeAnyway, danger: true, iconName: 'alert' } ).then( ( ok ) => ok && go() );
	};

	/* ---------- Save ---------- */
	let saving = false;
	const saveActive = ( force = false ) => {
		const t = S.active;
		if ( ! t || saving ) {
			return;
		}
		if ( ! t.writable ) {
			return modal( { title: T.notWritable, subtitle: t.path, body: `<p class="afp-modal__text">${ esc( T.readOnly ) }</p>`, cancelText: '', iconName: 'alert', danger: true } );
		}
		if ( t.doc.isClean( t.gen ) && ! force ) {
			return;
		}
		saving = true;
		const btn = $( '[data-act="save"]' );
		btn.classList.add( 'is-loading' );
		const gen = t.doc.changeGeneration();
		const content = t.doc.getValue();
		return ajax( 'code_save', { type: S.type, target: S.target, file: t.file, content, hash: t.hash, coreNonce: t.coreNonce, force: force ? 1 : 0 } )
			.then( ( d ) => {
				t.hash = d.hash;
				t.gen = gen;
				t.modified = d.modified;
				clearErrorLine();
				renderTabs();
				renderTree();
				renderStatus();
				return modal( {
					title: T.saved,
					subtitle: t.path,
					body: `<p class="afp-modal__text">${ esc( T.savedBody ) }</p>${ d.warning ? `<div class="afp-field__hint afp-field__hint--warn">${ icon( 'info' ) }<span>${ esc( d.warning ) }</span></div>` : '' }<p class="afp-muted">${ esc( T.modified ) }: ${ esc( d.modified ) }</p>`,
					cancelText: '',
					iconName: 'success',
					tone: d.warning ? 'warn' : 'ok',
				} ).then( () => cm.focus() );
			} )
			.catch( ( e ) => {
				// ajax() rejects with Error(message); details come from the raw response we re-fetch below.
				return handleSaveError( t, e );
			} )
			.finally( () => {
				saving = false;
				btn.classList.remove( 'is-loading' );
			} );
	};
	let errLine = null;
	const clearErrorLine = () => {
		if ( errLine !== null ) {
			cm.removeLineClass( errLine, 'background', 'afp-cm-errline' );
			errLine = null;
		}
	};
	const handleSaveError = ( t, e ) => {
		const info = e.data || {};
		if ( info.code === 'conflict' ) {
			let choice = null;
			return modal( {
				title: T.conflict,
				subtitle: t.path,
				body: `<p class="afp-modal__text">${ esc( e.message ) }</p><div class="afp-code__choices"><button type="button" class="afp-btn" data-choice="reload">${ icon( 'reset' ) }<span>${ esc( T.reload ) }</span></button></div>`,
				confirmText: T.overwrite,
				danger: true,
				iconName: 'alert',
				onOpen: ( ov, done ) =>
					ov.querySelector( '[data-choice="reload"]' ).addEventListener( 'click', () => {
						choice = 'reload';
						done( 'reload' );
					} ),
			} ).then( ( r ) => {
				if ( r === 'reload' || choice === 'reload' ) {
					S.tabs.splice( S.tabs.indexOf( t ), 1 );
					S.active = null;
					return openFile( t.file );
				}
				if ( r ) {
					saving = false;
					return saveActive( true );
				}
			} );
		}
		const php = info.code && /php|fatal|error|syntax/i.test( info.code ) && info.line;
		if ( info.line ) {
			clearErrorLine();
			errLine = info.line - 1;
			cm.addLineClass( errLine, 'background', 'afp-cm-errline' );
			cm.scrollIntoView( { line: errLine, ch: 0 }, 120 );
			cm.setCursor( { line: errLine, ch: 0 } );
		}
		return modal( {
			title: php ? T.phpError : info.code === 'not_writable' ? T.notWritable : T.saveError,
			subtitle: t.path + ( info.line ? ' · ' + T.line + ' ' + info.line : '' ),
			body: `${ php ? `<p class="afp-modal__text">${ esc( T.phpErrorBody ) }</p>` : '' }<pre class="afp-code__err">${ esc( e.message ) }</pre>`,
			cancelText: '',
			iconName: 'alert',
			danger: true,
			size: 'md',
		} );
	};

	/* ---------- Find / replace ---------- */
	const clearMarks = () => {
		S.find.marks.forEach( ( m ) => m.clear() );
		S.find.marks = [];
		S.find.hits = [];
		S.find.cur = -1;
		$( '[data-fn-count]' ).textContent = '';
	};
	const runFind = ( keep ) => {
		clearMarks();
		const q = $( '[data-fq]' ).value;
		S.find.q = q;
		S.find.caseS = $( '[data-fcase]' ).checked;
		if ( ! q || ! S.active ) {
			return;
		}
		const text = cm.getValue();
		const hay = S.find.caseS ? text : text.toLowerCase();
		const needle = S.find.caseS ? q : q.toLowerCase();
		let i = hay.indexOf( needle );
		while ( i !== -1 && S.find.hits.length < 5000 ) {
			S.find.hits.push( i );
			i = hay.indexOf( needle, i + Math.max( 1, needle.length ) );
		}
		cm.operation( () => {
			S.find.hits.forEach( ( h ) => S.find.marks.push( cm.markText( cm.posFromIndex( h ), cm.posFromIndex( h + q.length ), { className: 'afp-cm-match' } ) ) );
		} );
		if ( S.find.hits.length ) {
			const from = cm.indexFromPos( cm.getCursor( 'from' ) );
			const idx = keep ? Math.max( 0, S.find.hits.findIndex( ( h ) => h >= from ) ) : 0;
			go( idx === -1 ? 0 : idx );
		} else {
			$( '[data-fn-count]' ).textContent = T.noMatches;
		}
	};
	let curMark = null;
	const go = ( n ) => {
		const hits = S.find.hits;
		if ( ! hits.length ) {
			return;
		}
		S.find.cur = ( n + hits.length ) % hits.length;
		const a = cm.posFromIndex( hits[ S.find.cur ] );
		const b = cm.posFromIndex( hits[ S.find.cur ] + S.find.q.length );
		if ( curMark ) {
			curMark.clear();
		}
		curMark = cm.markText( a, b, { className: 'afp-cm-match is-cur' } );
		S.find.marks.push( curMark );
		cm.setSelection( a, b );
		cm.scrollIntoView( { from: a, to: b }, 120 );
		$( '[data-fn-count]' ).textContent = `${ S.find.cur + 1 } / ${ hits.length }`;
	};
	const openFind = ( replace ) => {
		const bar = $( '[data-findbar]' );
		bar.hidden = false;
		bar.classList.toggle( 'is-replace', !! replace || bar.classList.contains( 'is-replace' ) );
		const sel = cm.getSelection();
		if ( sel && ! sel.includes( '\n' ) ) {
			$( '[data-fq]' ).value = sel;
		}
		$( replace ? '[data-fr]' : '[data-fq]' ).focus();
		$( '[data-fq]' ).select();
		runFind( true );
	};
	const closeFind = () => {
		if ( $( '[data-findbar]' ).hidden ) {
			return CM.Pass;
		}
		$( '[data-findbar]' ).hidden = true;
		clearMarks();
		cm.focus();
	};
	const replaceOne = () => {
		if ( ! S.active || ! S.active.writable || S.find.cur < 0 ) {
			return;
		}
		const h = S.find.hits[ S.find.cur ];
		cm.replaceRange( $( '[data-fr]' ).value, cm.posFromIndex( h ), cm.posFromIndex( h + S.find.q.length ) );
		const next = S.find.cur;
		runFind();
		go( next );
	};
	const replaceAll = () => {
		if ( ! S.active || ! S.active.writable || ! S.find.hits.length ) {
			return;
		}
		const n = S.find.hits.length;
		const rep = $( '[data-fr]' ).value;
		const q = S.find.q;
		cm.operation( () => {
			S.find.hits
				.slice()
				.reverse()
				.forEach( ( h ) => cm.replaceRange( rep, cm.posFromIndex( h ), cm.posFromIndex( h + q.length ) ) );
		} );
		runFind();
		toast( fmt( T.replaced, n ) );
	};
	const gotoLine = () => {
		if ( ! S.active ) {
			return;
		}
		modal( { title: T.goToLine, input: String( cm.getCursor().line + 1 ), inputLabel: T.lineNumber + ' (1 - ' + cm.lineCount() + ')', iconName: 'list', confirmText: T.goToLine } ).then( ( v ) => {
			const n = parseInt( v, 10 );
			if ( n > 0 ) {
				const line = Math.min( cm.lineCount(), n ) - 1;
				cm.setCursor( { line, ch: 0 } );
				cm.scrollIntoView( { line, ch: 0 }, 160 );
				cm.focus();
			}
		} );
	};

	/* ---------- Docs lookup ---------- */
	const lookup = () => {
		let fn = $( '[data-fn]' ).value;
		if ( ! fn ) {
			const w = cm.findWordAt( cm.getCursor() );
			fn = cm.getRange( w.anchor, w.head ).trim();
		}
		if ( ! /^[A-Za-z_][A-Za-z0-9_]*$/.test( fn ) ) {
			return toast( T.lookUpHint, 'info' );
		}
		window.open( D.docsUrl + ( D.docsUrl.includes( '?' ) ? '&' : '?' ) + 'function=' + encodeURIComponent( fn ), '_blank', 'noopener' );
	};

	/* ---------- Load scope ---------- */
	const loadScope = ( type, target, file ) => {
		S.type = type;
		S.target = target;
		S.tabs = [];
		S.active = null;
		cm.swapDoc( CM.Doc( '', 'text/plain' ) );
		renderTabs();
		$( '[data-tree]' ).innerHTML = '<div class="afp-boot"><span class="afp-spin"></span></div>';
		return ajax( 'code_tree', { type, target } )
			.then( ( d ) => {
				S.files = d.files;
				renderTree();
				const want = file && S.files.find( ( f ) => f.file === file ) ? file : d.main;
				if ( want ) {
					openFile( want );
				}
			} )
			.catch( ( e ) => {
				S.files = [];
				renderTree();
				alertBox( e.message );
			} );
	};
	const confirmDiscardAll = () =>
		dirtyTabs().length ? modal( { title: T.unsavedTitle, body: `<p class="afp-modal__text">${ esc( T.unsavedSwitch ) }</p><ul class="afp-modal__changes">${ dirtyTabs().map( ( t ) => `<li>${ icon( 'pencil' ) }<span>${ esc( t.path ) }</span></li>` ).join( '' ) }</ul>`, confirmText: T.switchAnyway, danger: true, iconName: 'alert' } ) : Promise.resolve( true );

	/* ---------- Events ---------- */
	cm.on( 'changes', () => {
		renderTabs();
		const f = S.active && root.querySelector( `.afp-code__file[data-file="${ CSS.escape( S.active.file ) }"]` );
		if ( f ) {
			f.classList.toggle( 'is-dirty', ! S.active.doc.isClean( S.active.gen ) );
		}
		renderStatus();
	} );
	cm.on( 'cursorActivity', renderStatus );

	root.addEventListener( 'click', ( e ) => {
		const a = e.target.closest( '[data-act]' );
		if ( a ) {
			const act = a.dataset.act;
			const map = {
				save: () => saveActive(),
				find: () => openFind(),
				goto: gotoLine,
				lookup,
				'find-next': () => go( S.find.cur + 1 ),
				'find-prev': () => go( S.find.cur - 1 ),
				'find-close': closeFind,
				'replace-one': replaceOne,
				'replace-all': replaceAll,
				wrap: () => {
					S.wrap = ! S.wrap;
					setPref( 'wrap', S.wrap );
					a.classList.toggle( 'is-on', S.wrap );
					applyView();
				},
				full: () => {
					S.full = ! S.full;
					setPref( 'full', S.full );
					applyView();
					cm.focus();
				},
				theme: () => {
					S.dark = ! S.dark;
					setPref( 'dark', S.dark );
					a.classList.toggle( 'is-on', S.dark );
					applyView();
				},
				'font-': () => {
					S.font = Math.max( 11, S.font - 1 );
					setPref( 'font', S.font );
					applyView();
				},
				'font+': () => {
					S.font = Math.min( 24, S.font + 1 );
					setPref( 'font', S.font );
					applyView();
				},
			};
			if ( map[ act ] ) {
				map[ act ]();
			}
			return;
		}
		const x = e.target.closest( '[data-close]' );
		if ( x ) {
			e.stopPropagation();
			closeTab( x.dataset.close );
			return;
		}
		const tab = e.target.closest( '[data-tab]' );
		if ( tab ) {
			activate( tabByFile( tab.dataset.tab ) );
			return;
		}
		const dir = e.target.closest( '[data-dir]' );
		if ( dir ) {
			const k = dir.dataset.dir;
			if ( S.open.has( k ) ) {
				S.open.delete( k );
			} else {
				S.open.add( k );
			}
			setPref( 'open', Array.from( S.open ) );
			renderTree();
			return;
		}
		const f = e.target.closest( '[data-file]' );
		if ( f ) {
			openFile( f.dataset.file );
			return;
		}
		const sc = e.target.closest( '[data-scope]' );
		if ( sc && sc.dataset.scope !== S.type ) {
			confirmDiscardAll().then( ( ok ) => {
				if ( ! ok ) {
					return;
				}
				root.querySelectorAll( '[data-scope]' ).forEach( ( b ) => b.classList.toggle( 'is-on', b === sc ) );
				const type = sc.dataset.scope;
				const list = type === 'theme' ? D.themes : D.plugins;
				const def = ( list.find( ( x ) => x.active ) || list[ 0 ] || {} ).value || '';
				S.type = type;
				S.target = def;
				const sel = $( '[data-target]' );
				sel.innerHTML = targetOptions();
				UI.syncSelect( sel );
				loadScope( type, def );
			} );
		}
	} );
	root.addEventListener( 'auxclick', ( e ) => {
		const tab = e.target.closest( '[data-tab]' );
		if ( tab && e.button === 1 ) {
			closeTab( tab.dataset.tab );
		}
	} );
	const targetSel = $( '[data-target]' );
	if ( targetSel ) {
		targetSel.addEventListener( 'change', () => {
			const v = targetSel.value;
			confirmDiscardAll().then( ( ok ) => {
				if ( ok ) {
					loadScope( S.type, v );
				} else {
					targetSel.value = S.target;
					UI.syncSelect( targetSel );
				}
			} );
		} );
	}
	$( '[data-filter]' ).addEventListener( 'input', renderTree );
	$( '[data-fq]' ).addEventListener( 'input', () => runFind( true ) );
	$( '[data-fcase]' ).addEventListener( 'change', () => runFind( true ) );
	$( '[data-fq]' ).addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			go( S.find.cur + ( e.shiftKey ? -1 : 1 ) );
		} else if ( e.key === 'Escape' ) {
			closeFind();
		}
	} );
	$( '[data-fr]' ).addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			replaceOne();
		} else if ( e.key === 'Escape' ) {
			closeFind();
		}
	} );
	document.addEventListener( 'keydown', ( e ) => {
		const modalOpen = document.querySelector( '.afp-modal-ov, .afp-dd__menu' );
		if ( e.key === 'F11' && ! modalOpen ) {
			e.preventDefault();
			$( '[data-act="full"]' ).click();
			return;
		}
		if ( e.key === 'Escape' && S.full && ! modalOpen && $( '[data-findbar]' ).hidden ) {
			S.full = false;
			setPref( 'full', false );
			applyView();
			return;
		}
		if ( ( e.ctrlKey || e.metaKey ) && e.key.toLowerCase() === 's' && ! document.querySelector( '.afp-modal-ov' ) ) {
			e.preventDefault();
			saveActive();
		}
	} );
	window.addEventListener( 'beforeunload', ( e ) => {
		if ( dirtyTabs().length ) {
			e.preventDefault();
			e.returnValue = T.leave;
		}
	} );

	loadScope( S.type, S.target, D.file );
} )();
