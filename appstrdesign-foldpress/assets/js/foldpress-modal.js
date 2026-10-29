/**
 * FoldPress - wp.media modal integration.
 * Folder panel (desktop) / folder select (mobile), folder search + collapse, drag to folder,
 * upload into the current folder, FoldPress file icons, quick preview / edit / copy on every file,
 * and "download to media library" in Insert from URL.
 */
( function ( wp, $ ) {
	'use strict';

	if ( ! wp || ! wp.media || ! wp.media.view || ! wp.media.view.AttachmentsBrowser ) {
		return;
	}

	const D = window.FoldPressModal || {};
	const T = D.i18n || {};
	const UI = window.FoldPressUI || null;
	const esc = ( s ) => String( s == null ? '' : s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );
	const fmt = ( str, ...a ) => String( str || '' ).replace( /%(\d+\$)?[sd]/g, () => a.shift() );
	const sizeH = ( b ) => {
		const u = [ 'B', 'KB', 'MB', 'GB' ];
		let i = 0;
		while ( b >= 1024 && i < u.length - 1 ) {
			b /= 1024;
			i++;
		}
		return ( i ? b.toFixed( 1 ) : Math.round( b ) ) + ' ' + u[ i ];
	};
	const svg = ( d, cls = '' ) => `<svg class="afp-mm-i ${ cls }" viewBox="0 0 16 16" aria-hidden="true"><path d="${ d }"/></svg>`;
	const ICON = {
		folder: 'M1.5 3A1.5 1.5 0 0 1 3 1.5h3.17a1.5 1.5 0 0 1 1.06.44l.83.83a.5.5 0 0 0 .35.15H13A1.5 1.5 0 0 1 14.5 4.5v8A1.5 1.5 0 0 1 13 14H3a1.5 1.5 0 0 1-1.5-1.5z',
		files: 'M5 1.5h5.5L14 5v8.5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1zM10 1.5V5h4M2 4v10a1 1 0 0 0 1 1h8',
		inbox: 'M1.5 9.5 3.5 3h9l2 6.5V13a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1zM1.5 9.5h4l1 1.5h3l1-1.5h4',
		plus: 'M8 3v10M3 8h10',
		search: 'M7 12.5a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11zM14.5 14.5 11 11',
		chevron: 'M6 3.5 10.5 8 6 12.5',
		upload: 'M8 11V2M4.5 5.5 8 2l3.5 3.5M2 10v3a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-3',
		external: 'M9 2.5h4.5V7M13.5 2.5 7 9M11.5 9.5v3a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h3',
		eye: 'M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5zM8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
		pencil: 'M11 2.5 13.5 5 5.5 13H3v-2.5zM9.5 4l2.5 2.5',
		link: 'M6.5 9.5a3 3 0 0 0 4.2 0l2.3-2.3a3 3 0 0 0-4.2-4.2l-.8.8M9.5 6.5a3 3 0 0 0-4.2 0L3 8.8a3 3 0 0 0 4.2 4.2l.8-.8',
		download: 'M8 2v9M4.5 7.5 8 11l3.5-3.5M2 13.5h12',
	};
	const LS = {
		get( k, d ) {
			try {
				const v = window.localStorage.getItem( 'foldpress_mm_' + k );
				return v === null ? d : JSON.parse( v );
			} catch ( e ) {
				return d;
			}
		},
		set( k, v ) {
			try {
				window.localStorage.setItem( 'foldpress_mm_' + k, JSON.stringify( v ) );
			} catch ( e ) {}
		},
	};
	const toast = ( msg, type ) => ( UI ? UI.toast( msg, type ) : window.wp.a11y && window.wp.a11y.speak( msg ) );

	/* ---------- Shared state ---------- */
	const store = {
		folders: [],
		loaded: false,
		current: -1,
		query: '',
		closed: new Set( LS.get( 'closed', [] ) ),
		views: new Set(),
	};

	const ajax = ( action, data ) =>
		$.post( D.ajaxUrl, Object.assign( { action: 'foldpress_' + action, nonce: D.nonce }, data ) ).then( ( r ) => {
			if ( ! r || ! r.success ) {
				return $.Deferred().reject( ( r && r.data && r.data.message ) || T.error );
			}
			return r.data;
		} );

	const renderAll = () => store.views.forEach( ( v ) => v.fpRender() );
	const refreshFolders = () =>
		ajax( 'folders', {} )
			.then( ( d ) => {
				store.folders = d.folders || [];
				store.loaded = true;
				renderAll();
			} )
			.fail( () => {
				store.loaded = true;
				renderAll();
			} );

	const hasKids = ( id ) => store.folders.some( ( f ) => f.parent === id );
	const ordered = ( flat ) => {
		const out = [];
		const walk = ( pid, depth ) =>
			store.folders
				.filter( ( f ) => f.parent === pid )
				.sort( ( a, b ) => a.name.localeCompare( b.name ) )
				.forEach( ( f ) => {
					out.push( { f, depth } );
					if ( flat || ! store.closed.has( f.id ) ) {
						walk( f.id, depth + 1 );
					}
				} );
		walk( 0, 0 );
		return out;
	};
	const pathOf = ( id ) => {
		const parts = [];
		let f = store.folders.find( ( x ) => x.id === id );
		while ( f ) {
			parts.unshift( f.name );
			const pid = f.parent;
			f = pid ? store.folders.find( ( x ) => x.id === pid ) : null;
		}
		return parts;
	};

	/* ---------- Upload into current folder ---------- */
	if ( wp.Uploader && wp.Uploader.prototype ) {
		const origInit = wp.Uploader.prototype.init;
		wp.Uploader.prototype.init = function () {
			if ( origInit ) {
				origInit.apply( this, arguments );
			}
			if ( this.uploader && this.uploader.bind ) {
				this.uploader.bind( 'BeforeUpload', ( up ) => {
					up.settings.multipart_params = up.settings.multipart_params || {};
					up.settings.multipart_params.foldpress_folder = store.current > 0 ? store.current : 0;
				} );
				this.uploader.bind( 'UploadComplete', () => refreshFolders() );
			}
		};
	}

	/*
	 * Live uploads. Core Query only watches wp.Uploader.queue when every query arg is one it knows
	 * (see Query.initialize in media-models.js). The folder filter added a foreign arg, so new uploads
	 * never reached the grid, looked unselected and could not be picked until the library reloaded.
	 * Each upload is stamped with its target folder and the queue is observed again with a folder filter.
	 */
	const QUERY = wp.media.model && wp.media.model.Query;
	const CORE_ARGS = [ 's', 'order', 'orderby', 'posts_per_page', 'post_mime_type', 'post_parent', 'author' ];
	const OUR_ARGS = [ 'foldpress_folder', 'ignore' ];
	if ( QUERY && wp.Uploader && wp.Uploader.queue ) {
		wp.Uploader.queue.on( 'add', ( m ) => m.set( { foldpress_folder: store.current > 0 ? store.current : 0 }, { silent: true } ) );
		const origQuery = QUERY.prototype.initialize;
		QUERY.prototype.initialize = function () {
			origQuery.apply( this, arguments );
			const extra = Object.keys( this.args || {} ).filter( ( k ) => ! CORE_ARGS.includes( k ) );
			if ( ! extra.length || extra.some( ( k ) => ! OUR_ARGS.includes( k ) ) ) {
				return; // Core already observes, or another plugin's arg: leave as is.
			}
			const folder = this.args.foldpress_folder;
			this.filters.foldpress = ( a ) => folder === undefined || +folder < 0 || +a.get( 'foldpress_folder' ) === +folder;
			this.observe( wp.Uploader.queue );
		};
	}

	/* ---------- Mobile select (lives in core toolbar) ---------- */
	const FolderSelect = wp.media.View.extend( {
		tagName: 'select',
		className: 'attachment-filters afp-mm-select',
		events: { change: 'change' },
		initialize() {
			this.$el.attr( 'aria-label', T.filter );
		},
		render() {
			let html = `<option value="-1">${ esc( T.allFiles ) }</option><option value="0">${ esc( T.unassigned ) }</option>`;
			ordered( true ).forEach( ( { f, depth } ) => {
				html += `<option value="${ f.id }">${ '  '.repeat( depth ) + esc( f.name ) } (${ f.count })</option>`;
			} );
			this.$el.html( html ).val( String( store.current ) );
			return this;
		},
		change() {
			this.options.browser.fpGo( parseInt( this.$el.val(), 10 ) );
		},
	} );

	/* ---------- Extend AttachmentsBrowser ---------- */
	const Orig = wp.media.view.AttachmentsBrowser;
	wp.media.view.AttachmentsBrowser = Orig.extend( {
		initialize() {
			Orig.prototype.initialize.apply( this, arguments );
			this.fpInit();
		},

		createToolbar() {
			Orig.prototype.createToolbar.apply( this, arguments );
			this.fpSelect = new FolderSelect( { controller: this.controller, browser: this, priority: -80 } );
			this.toolbar.set( 'foldpressFolder', this.fpSelect.render() );
		},

		fpInit() {
			this.$el.addClass( 'afp-mm' );
			// Panel is a real subview so core re-renders keep it.
			this.fpView = new wp.media.View( { className: 'afp-mm-panel' } );
			this.fpView.$el.attr( { role: 'navigation', 'aria-label': T.folders } );
			this.fpView.el.fpBrowser = this;
			this.fpPanel = this.fpView.$el;
			this.views.add( this.fpView, { at: 0 } );
			store.views.add( this );
			this.on( 'remove', () => store.views.delete( this ) );

			// Keep current filter when the browser is rebuilt.
			if ( store.current !== -1 && this.collection && this.collection.props ) {
				this.collection.props.set( { foldpress_folder: store.current } );
			}
			// Render the shell at once (never an empty column), then fill it.
			this.fpRender();
			if ( ! store.loaded ) {
				refreshFolders();
			}
		},

		fpRender() {
			const q = store.query.trim().toLocaleLowerCase();
			const row = ( id, name, count, depth, sys, ic ) => {
				const kids = ! sys && hasKids( id );
				const open = q || ! store.closed.has( id );
				return `<div class="afp-mm-row ${ sys ? 'is-sys' : '' } ${ store.current === id ? 'is-active' : '' }" style="--d:${ depth }">
					${ kids ? `<button type="button" class="afp-mm-tog ${ open ? 'is-open' : '' }" data-afp-tog="${ id }" aria-label="${ esc( T.toggle ) }" aria-expanded="${ open }">${ svg( ICON.chevron ) }</button>` : '<span class="afp-mm-tog is-leaf"></span>' }
					<button type="button" class="afp-mm-go" data-afp-folder="${ id }" title="${ esc( name ) }">${ svg( ICON[ ic ], ic === 'folder' ? 'is-folder' : '' ) }<span>${ esc( name ) }</span>${ count === null ? '' : `<small>${ count }</small>` }</button>
				</div>`;
			};
			let tree = row( -1, T.allFiles, null, 0, true, 'files' ) + row( 0, T.unassigned, null, 0, true, 'inbox' );
			let list = q ? ordered( true ).filter( ( { f } ) => f.name.toLocaleLowerCase().includes( q ) ).map( ( x ) => ( { f: x.f, depth: 0 } ) ) : ordered( false );
			if ( ! store.loaded ) {
				tree += '<div class="afp-mm-sep"></div>' + '<div class="afp-mm-skel"></div>'.repeat( 4 );
			} else {
				tree += '<div class="afp-mm-sep"></div>';
				tree += list.length ? list.map( ( { f, depth } ) => row( f.id, f.name, f.count, depth, false, 'folder' ) ).join( '' ) : `<p class="afp-mm-empty">${ esc( T.noFolders ) }</p>`;
			}
			const target = store.current > 0 ? pathOf( store.current ).join( ' › ' ) : T.unassigned;
			const panel = this.fpPanel;
			const focused = document.activeElement && panel[ 0 ].contains( document.activeElement ) && document.activeElement.matches( '.afp-mm-search input' );
			panel.html(
				`<div class="afp-mm-head"><strong>${ esc( T.folders ) }</strong><button type="button" class="afp-mm-btn" data-afp-new aria-label="${ esc( T.newFolder ) }" title="${ esc( T.newFolder ) }">${ svg( ICON.plus ) }</button></div>
				<div class="afp-mm-new" hidden><input type="text" placeholder="${ esc( T.folderName ) }" aria-label="${ esc( T.folderName ) }"></div>
				${ store.folders.length > 6 ? `<label class="afp-mm-search">${ svg( ICON.search ) }<input type="search" value="${ esc( store.query ) }" placeholder="${ esc( T.searchFolders ) }" aria-label="${ esc( T.searchFolders ) }"></label>` : '' }
				<div class="afp-mm-tree">${ tree }</div>
				<div class="afp-mm-foot">
					<div class="afp-mm-target" title="${ esc( T.uploadsTo + ': ' + target ) }">${ svg( ICON.upload ) }<span><small>${ esc( T.uploadsTo ) }</small><b>${ esc( target ) }</b></span></div>
					<a class="afp-mm-manage" href="${ esc( D.manageUrl ) }" target="_blank" rel="noopener">${ svg( ICON.external ) }<span>${ esc( T.manage ) }</span></a>
				</div>`
			);
			if ( focused ) {
				const inp = panel.find( '.afp-mm-search input' )[ 0 ];
				inp.focus();
				inp.setSelectionRange( inp.value.length, inp.value.length );
			}
			if ( this.fpSelect ) {
				this.fpSelect.render();
			}
		},

		fpGo( id ) {
			store.current = id;
			if ( id === -1 ) {
				this.collection.props.unset( 'foldpress_folder' );
			} else {
				this.collection.props.set( { foldpress_folder: id } );
			}
			renderAll();
		},

		fpToggleNew( force ) {
			const box = this.fpPanel.find( '.afp-mm-new' );
			const show = typeof force === 'boolean' ? force : box.prop( 'hidden' );
			box.prop( 'hidden', ! show ).removeAttr( 'data-error' );
			if ( show ) {
				box.find( 'input' ).val( '' ).trigger( 'focus' );
			}
		},

		fpCreate( name ) {
			if ( ! name ) {
				return;
			}
			const input = this.fpPanel.find( '.afp-mm-new input' ).prop( 'disabled', true );
			const parent = store.current > 0 ? store.current : 0;
			ajax( 'folder_create', { name, parent } )
				.then( ( d ) => {
					store.folders = d.folders;
					store.closed.delete( parent );
					renderAll();
					toast( T.created );
				} )
				.fail( ( msg ) => {
					input.prop( 'disabled', false );
					this.fpPanel.find( '.afp-mm-new' ).attr( 'data-error', msg );
					toast( msg, 'error' );
				} );
		},

		fpRequery() {
			// Core builds a fresh Query on every _requery(); a throwaway prop would also stop live uploads.
			if ( this.collection.props.get( 'query' ) ) {
				this.collection.props.unset( 'ignore', { silent: true } );
				this.collection._requery();
			}
		},
	} );

	/* ---------- FoldPress look + quick actions on every library file ---------- */
	const GROUPS = {
		document: [ 'doc', 'docx', 'odt', 'rtf', 'txt', 'md', 'pages' ],
		spreadsheet: [ 'xls', 'xlsx', 'ods', 'csv', 'tsv', 'numbers' ],
		presentation: [ 'ppt', 'pptx', 'odp', 'key' ],
		archive: [ 'zip', 'rar', '7z', 'gz', 'tar', 'tgz', 'bz2' ],
		code: [ 'css', 'js', 'mjs', 'json', 'xml', 'html', 'php', 'scss', 'less', 'yml', 'yaml', 'sql' ],
		font: [ 'ttf', 'otf', 'woff', 'woff2', 'eot' ],
	};
	const toItem = ( m ) => {
		const a = m.toJSON ? m.toJSON() : m;
		const ext = String( a.filename || a.url || '' ).split( '?' )[ 0 ].split( '.' ).pop().toLowerCase();
		let group = [ 'image', 'video', 'audio' ].includes( a.type ) ? a.type : ext === 'pdf' ? 'pdf' : 'other';
		if ( group === 'other' ) {
			Object.keys( GROUPS ).some( ( g ) => GROUPS[ g ].includes( ext ) && ( group = g ) );
		}
		const sz = a.sizes || {};
		return {
			id: a.id,
			url: a.url,
			full: a.url,
			// Only real images (or generated PDF previews) have a picture; other types use the file icon.
			thumb: ( sz.medium || sz.thumbnail || sz.full || {} ).url || ( a.type === 'image' ? a.url : '' ),
			mime: a.mime || '',
			title: a.title || a.filename,
			filename: a.filename,
			alt: a.alt || '',
			ext,
			group,
			sizeH: a.filesizeHumanReadable || '',
			dims: a.width && a.height ? a.width + ' × ' + a.height : '',
		};
	};
	const AP = wp.media.view.Attachment && wp.media.view.Attachment.prototype;
	if ( AP && UI ) {
		const origRender = AP.render;
		AP.render = function () {
			const out = origRender.apply( this, arguments );
			const Lib = wp.media.view.Attachment.Library;
			if ( Lib && this instanceof Lib && this.model && this.model.get( 'id' ) && ! this.model.get( 'uploading' ) ) {
				const it = toItem( this.model );
				const ic = this.el.querySelector( '.thumbnail img.icon' );
				if ( ic && it.group !== 'image' ) {
					const box = document.createElement( 'span' );
					box.className = 'afp-mm-ficon';
					box.innerHTML = UI.fileIcon( it );
					ic.replaceWith( box );
					this.el.classList.add( 'afp-mm-hasicon' );
				}
				const acts = document.createElement( 'span' );
				acts.className = 'afp-mm-acts';
				acts.innerHTML =
					( UI.previewable( it ) ? `<button type="button" class="afp-mm-act" data-afp-act="preview" title="${ esc( T.preview ) }" aria-label="${ esc( T.preview ) }">${ svg( ICON.eye ) }</button>` : '' ) +
					`<button type="button" class="afp-mm-act" data-afp-act="copy" title="${ esc( T.copyUrl ) }" aria-label="${ esc( T.copyUrl ) }">${ svg( ICON.link ) }</button>` +
					( this.model.get( 'editLink' ) ? `<button type="button" class="afp-mm-act" data-afp-act="edit" title="${ esc( T.edit ) }" aria-label="${ esc( T.edit ) }">${ svg( ICON.pencil ) }</button>` : '' );
				const prev = this.el.querySelector( '.attachment-preview' );
				( prev || this.el ).appendChild( acts );
				this.el.fpView = this;
			}
			return out;
		};
		// Capture phase: the click never reaches core's select handler.
		document.addEventListener(
			'click',
			( e ) => {
				const b = e.target.closest && e.target.closest( '.afp-mm-act' );
				if ( ! b ) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				const li = b.closest( 'li.attachment' );
				const view = li && li.fpView;
				if ( ! view ) {
					return;
				}
				const m = view.model;
				const act = b.dataset.afpAct;
				if ( act === 'copy' ) {
					UI.copy( m.get( 'url' ) );
				} else if ( act === 'edit' ) {
					window.open( m.get( 'editLink' ), '_blank', 'noopener' );
				} else if ( act === 'preview' ) {
					const models = m.collection ? m.collection.models : [ m ];
					const items = models.filter( ( x ) => x.get( 'id' ) ).map( toItem );
					UI.lightbox( items, Math.max( 0, models.indexOf( m ) ) );
				}
			},
			true
		);
	}

	/* ---------- Insert from URL: hint + "download to media library" ---------- */
	const EU = wp.media.view.EmbedUrl;
	const addUrlBar = ( el ) => {
		if ( ! el || el.querySelector( '.afp-mm-urlbar' ) ) {
			return;
		}
		const bar = document.createElement( 'div' );
		bar.className = 'afp-mm-urlbar';
		bar.innerHTML =
			`<p class="afp-mm-urlhint">${ esc( T.urlHint ) }</p>` +
			( D.urlImport
				? `<div class="afp-mm-urlimp"><button type="button" class="afp-mm-impbtn" data-afp-import>${ svg( ICON.download ) }<span>${ esc( T.urlImport ) }</span></button><small>${ esc( T.urlImportHint ) }</small><output class="afp-mm-impmsg" aria-live="polite"></output></div>`
				: '' );
		el.appendChild( bar );
	};
	if ( EU ) {
		[ 'initialize', 'render' ].forEach( ( fn ) => {
			const orig = EU.prototype[ fn ];
			EU.prototype[ fn ] = function () {
				const out = orig.apply( this, arguments );
				addUrlBar( this.el );
				return out;
			};
		} );
	}
	$( document ).on( 'click', '.media-embed [data-afp-import]', function () {
		const btn = this;
		const wrap = btn.closest( '.media-embed' );
		const input = wrap && wrap.querySelector( '.embed-url input, #embed-url-field' );
		const url = input ? input.value.trim() : '';
		const out = wrap.querySelector( '.afp-mm-impmsg' );
		const say = ( msg, cls, html ) => {
			out.className = 'afp-mm-impmsg ' + ( cls || '' );
			out.innerHTML = html || esc( msg );
		};
		if ( ! /^https?:\/\/\S+$/i.test( url ) ) {
			say( T.invalidUrl, 'is-err' );
			input && input.focus();
			return;
		}
		btn.disabled = true;
		btn.classList.add( 'is-busy' );
		const job = Math.random().toString( 36 ).slice( 2, 10 ) + Date.now().toString( 36 );
		const t0 = performance.now();
		let running = true;
		const progress = ( p ) => {
			const got = p ? +p.got || 0 : 0;
			const total = p ? +p.total || 0 : 0;
			const secs = ( performance.now() - t0 ) / 1000;
			const rate = got && secs > 0.3 ? got / secs : 0;
			const pct = total ? Math.min( 100, Math.round( ( got / total ) * 100 ) ) : 0;
			const parts = [ p && p.phase === 'process' ? T.processing : T.importing ];
			if ( got && p.phase !== 'process' ) {
				parts.push( total ? sizeH( got ) + ' / ' + sizeH( total ) : sizeH( got ) );
				rate && parts.push( sizeH( rate ) + '/s' );
				total && rate && parts.push( fmt( T.left, Math.max( 1, Math.round( ( total - got ) / rate ) ) + 's' ) );
			}
			say(
				'',
				'is-busy',
				`<span class="afp-mm-imptxt">${ esc( parts.join( ' · ' ) ) }</span><button type="button" class="afp-mm-impstop" data-afp-impstop="${ job }">${ esc( T.cancel ) }</button>` +
					`<i class="afp-mm-impbar${ total || ( p && p.phase === 'process' ) ? '' : ' is-indet' }"><em style="width:${ p && p.phase === 'process' ? 100 : pct }%"></em></i>`
			);
		};
		const poll = () => {
			if ( ! running ) {
				return;
			}
			ajax( 'import_progress', { jobs: [ job ] } )
				.then( ( d ) => running && d && d[ job ] && progress( d[ job ] ) )
				.always( () => running && setTimeout( poll, 700 ) );
		};
		progress( null );
		setTimeout( poll, 500 );
		ajax( 'cloud_import', { provider: 'url', job, files: JSON.stringify( [ { url } ] ), folder: store.current > 0 ? store.current : 0 } )
			.then( ( d ) => {
				const r = ( d.results || [] )[ 0 ] || {};
				if ( ! r.ok ) {
					say( r.error || ( d.errors || [] )[ 0 ] || T.error, 'is-err' );
					return;
				}
				refreshFolders();
				say( '', 'is-ok', `<span class="afp-mm-imptxt">${ esc( T.imported ) } <b>${ esc( r.name || '' ) }</b></span><button type="button" class="afp-mm-showlib" data-afp-show="${ r.id }">${ esc( T.showInLib ) }</button>` );
				toast( T.imported );
			} )
			.fail( ( msg ) => say( msg, 'is-err' ) )
			.always( () => {
				running = false;
				btn.disabled = false;
				btn.classList.remove( 'is-busy' );
			} );
	} );
	$( document ).on( 'click', '.media-embed [data-afp-impstop]', function () {
		this.disabled = true;
		ajax( 'import_cancel', { job: this.dataset.afpImpstop } );
	} );
	$( document ).on( 'click', '.media-embed [data-afp-show]', function () {
		const id = parseInt( this.dataset.afpShow, 10 );
		const frame = wp.media.frame;
		const att = wp.media.attachment( id );
		att.fetch().done( () => {
			try {
				if ( frame && frame.state ) {
					const states = [ 'insert', 'library', 'featured-image', 'gallery' ];
					const target = states.find( ( s ) => frame.states && frame.states.get( s ) );
					if ( target ) {
						frame.setState( target );
					}
					const st = frame.state();
					const lib = st.get( 'library' );
					if ( lib ) {
						lib.add( att, { at: 0 } );
					}
					if ( st.get( 'selection' ) ) {
						st.get( 'selection' ).reset( [ att ] );
					}
					if ( frame.content && frame.content.mode ) {
						frame.content.mode( 'browse' );
					}
				}
			} catch ( e ) {}
		} );
	} );

	/* ---------- Document-level delegation (survives subview re-renders) ---------- */
	const browserOf = ( node ) => {
		const panel = node.closest( '.afp-mm-panel' );
		if ( panel && panel.fpBrowser ) {
			return panel.fpBrowser;
		}
		const root = node.closest( '.attachments-browser' );
		const p = root && root.querySelector( '.afp-mm-panel' );
		return p ? p.fpBrowser : null;
	};
	const $doc = $( document );
	$doc.on( 'click', '.afp-mm-panel [data-afp-folder]', function () {
		const b = browserOf( this );
		if ( b ) {
			b.fpGo( parseInt( this.dataset.afpFolder, 10 ) );
		}
	} );
	$doc.on( 'click', '.afp-mm-panel [data-afp-tog]', function () {
		const id = parseInt( this.dataset.afpTog, 10 );
		if ( store.closed.has( id ) ) {
			store.closed.delete( id );
		} else {
			store.closed.add( id );
		}
		LS.set( 'closed', Array.from( store.closed ) );
		renderAll();
	} );
	$doc.on( 'input', '.afp-mm-panel .afp-mm-search input', function () {
		store.query = this.value;
		renderAll();
	} );
	$doc.on( 'keydown', '.afp-mm-panel .afp-mm-search input', ( e ) => e.key === 'Escape' && e.stopPropagation() );
	$doc.on( 'click', '.afp-mm-panel [data-afp-new]', function () {
		const b = browserOf( this );
		if ( b ) {
			b.fpToggleNew();
		}
	} );
	$doc.on( 'keydown', '.afp-mm-panel .afp-mm-new input', function ( e ) {
		const b = browserOf( this );
		if ( ! b ) {
			return;
		}
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			b.fpCreate( this.value.trim() );
		} else if ( e.key === 'Escape' ) {
			e.stopPropagation();
			b.fpToggleNew( false );
		}
	} );

	let dragIds = null;
	$doc.on( 'mouseenter touchstart', '.afp-mm li.attachment', function () {
		this.setAttribute( 'draggable', 'true' );
	} );
	$doc.on( 'dragstart', '.afp-mm li.attachment', function ( e ) {
		const b = browserOf( this );
		if ( ! b ) {
			return;
		}
		const id = parseInt( this.dataset.id, 10 );
		const sel = b.options.selection;
		const selIds = sel ? sel.pluck( 'id' ) : [];
		dragIds = selIds.includes( id ) ? selIds : [ id ];
		e.originalEvent.dataTransfer.effectAllowed = 'move';
		e.originalEvent.dataTransfer.setData( 'text/plain', 'foldpress' );
		b.$el.addClass( 'afp-mm-dragging' );
	} );
	$doc.on( 'dragend', '.afp-mm li.attachment', function () {
		dragIds = null;
		$( '.afp-mm-dragging' ).removeClass( 'afp-mm-dragging' );
		$( '.afp-mm-panel .is-drop' ).removeClass( 'is-drop' );
	} );
	$doc.on( 'dragover', '.afp-mm-panel [data-afp-folder]', function ( e ) {
		if ( dragIds && parseInt( this.dataset.afpFolder, 10 ) >= 0 ) {
			e.preventDefault();
			this.classList.add( 'is-drop' );
		}
	} );
	$doc.on( 'dragleave', '.afp-mm-panel [data-afp-folder]', function () {
		this.classList.remove( 'is-drop' );
	} );
	$doc.on( 'drop', '.afp-mm-panel [data-afp-folder]', function ( e ) {
		e.preventDefault();
		const b = browserOf( this );
		const folder = parseInt( this.dataset.afpFolder, 10 );
		const moving = dragIds;
		dragIds = null;
		this.classList.remove( 'is-drop' );
		if ( ! b || ! moving || folder < 0 ) {
			return;
		}
		b.fpPanel.addClass( 'is-busy' );
		ajax( 'move', { ids: moving, folder } )
			.then( () => {
				refreshFolders();
				toast( T.moved );
				if ( store.current !== -1 ) {
					b.fpRequery();
				}
			} )
			.fail( ( msg ) => toast( msg, 'error' ) )
			.always( () => b.fpPanel.removeClass( 'is-busy' ) );
	} );
} )( window.wp, window.jQuery );
