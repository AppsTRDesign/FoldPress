<?php
/**
 * Admin screen: replaces Media Library + Add New screens.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Admin
 */
class FoldPress_Admin {

	const SLUG = 'foldpress';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 99 );
		add_action( 'load-upload.php', array( $this, 'redirect_library' ) );
		add_action( 'load-media-new.php', array( $this, 'redirect_new' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FOLDPRESS_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Page URL.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'upload.php' ) );
	}

	/**
	 * Shared UI library (icons, modal, toast, ajax, lightbox) for every FoldPress screen.
	 */
	public static function enqueue_ui() {
		wp_enqueue_style( 'foldpress', FOLDPRESS_URL . 'assets/css/foldpress.css', array(), FOLDPRESS_VERSION );
		wp_enqueue_script( 'foldpress-ui', FOLDPRESS_URL . 'assets/js/foldpress-ui.js', array(), FOLDPRESS_VERSION, true );
		wp_localize_script(
			'foldpress-ui',
			'FoldPressUIData',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( FoldPress_Ajax::NONCE ),
				'lightbox' => (bool) FoldPress_Settings::get( 'lightbox' ),
				'toastPos' => FoldPress_Settings::get( 'toast_position' ),
				'docExt'   => FoldPress_Preview::extensions(),
				'i18n'     => array(
					'ok'           => __( 'OK', 'appstrdesign-foldpress' ),
					'cancel'       => __( 'Cancel', 'appstrdesign-foldpress' ),
					'close'        => __( 'Close', 'appstrdesign-foldpress' ),
					'error'        => __( 'Error', 'appstrdesign-foldpress' ),
					'success'      => __( 'Success', 'appstrdesign-foldpress' ),
					'networkError' => __( 'Network error. Please try again.', 'appstrdesign-foldpress' ),
					'copied'       => __( 'URL copied to clipboard.', 'appstrdesign-foldpress' ),
					'enterHint'    => __( 'Press Enter to confirm, Esc to cancel', 'appstrdesign-foldpress' ),
					'download'     => __( 'Download', 'appstrdesign-foldpress' ),
					'delete'       => __( 'Delete', 'appstrdesign-foldpress' ),
					'deleteTitle'  => __( 'Delete files permanently?', 'appstrdesign-foldpress' ),
					'size'         => __( 'Size', 'appstrdesign-foldpress' ),
					'root'         => __( 'Root (no folder)', 'appstrdesign-foldpress' ),
					'preview'      => __( 'Preview', 'appstrdesign-foldpress' ),
					'openNewTab'   => __( 'Open in new tab', 'appstrdesign-foldpress' ),
					'previewError' => __( 'Preview could not be loaded.', 'appstrdesign-foldpress' ),
					'quickView'    => __( 'Quick view', 'appstrdesign-foldpress' ),
					'fullLayout'   => __( 'Full layout', 'appstrdesign-foldpress' ),
					'slide'        => __( 'Slide', 'appstrdesign-foldpress' ),
					'emptySlide'   => __( 'This slide has no text.', 'appstrdesign-foldpress' ),
					'truncated'    => __( 'Only the beginning of this file is shown.', 'appstrdesign-foldpress' ),
					'rowsShown'    => __( 'First %d rows are shown.', 'appstrdesign-foldpress' ),
					'zipItems'     => __( '%d items in this archive', 'appstrdesign-foldpress' ),
					'noPreview'    => __( 'No preview available for this file type.', 'appstrdesign-foldpress' ),
					'quickNote'    => __( 'Quick view shows the text and tables. Layout, images and fonts may differ from the original.', 'appstrdesign-foldpress' ),
					'history'        => __( 'Change history', 'appstrdesign-foldpress' ),
					'historyEmpty'   => __( 'This file has not been renamed yet.', 'appstrdesign-foldpress' ),
					'historyHint'    => __( 'Choose a version to go back to. If the file was renamed on the server, it is renamed back and every link in the database is updated.', 'appstrdesign-foldpress' ),
					'currentVersion' => __( 'Current version', 'appstrdesign-foldpress' ),
					'restoreThis'    => __( 'Go back to this version', 'appstrdesign-foldpress' ),
					'title'          => __( 'Title', 'appstrdesign-foldpress' ),
					'fileName'       => __( 'File name', 'appstrdesign-foldpress' ),
					'renamedBadge'   => __( 'Renamed - show history', 'appstrdesign-foldpress' ),
					'via'            => array(
						'rename'  => __( 'Renamed', 'appstrdesign-foldpress' ),
						'title'   => __( 'Title changed', 'appstrdesign-foldpress' ),
						'details' => __( 'Details panel', 'appstrdesign-foldpress' ),
						'editor'  => __( 'Advanced edit', 'appstrdesign-foldpress' ),
						'restore' => __( 'Restored', 'appstrdesign-foldpress' ),
					),
				),
			)
		);
	}

	/**
	 * Menu: our page becomes the Library entry.
	 */
	public function menu() {
		add_submenu_page( 'upload.php', __( 'Media Library', 'appstrdesign-foldpress' ), __( 'Library', 'appstrdesign-foldpress' ), 'upload_files', self::SLUG, array( $this, 'render' ), 0 );
		if ( FoldPress_Settings::get( 'replace_library' ) ) {
			remove_submenu_page( 'upload.php', 'upload.php' );
			remove_submenu_page( 'upload.php', 'media-new.php' );
		}
	}

	/**
	 * Is classic screen requested.
	 *
	 * @return bool
	 */
	private function classic() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing flags.
		return ! FoldPress_Settings::get( 'replace_library' ) || isset( $_GET['foldpress_classic'] ) || isset( $_GET['item'] ) || isset( $_GET['page'] ) || isset( $_GET['action'] );
	}

	/**
	 * Redirect upload.php.
	 */
	public function redirect_library() {
		if ( ! $this->classic() ) {
			wp_safe_redirect( self::url() );
			exit;
		}
	}

	/**
	 * Redirect media-new.php.
	 */
	public function redirect_new() {
		if ( ! $this->classic() ) {
			wp_safe_redirect( self::url( array( 'upload' => 1 ) ) );
			exit;
		}
	}

	/**
	 * Plugin list links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open File Manager', 'appstrdesign-foldpress' ) . '</a>',
			'<a href="' . esc_url( FoldPress_Settings_Page::url() ) . '">' . esc_html__( 'Settings', 'appstrdesign-foldpress' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Enqueue on our page only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function assets( $hook ) {
		if ( 'media_page_' . self::SLUG !== $hook ) {
			return;
		}
		self::enqueue_ui();
		wp_enqueue_script( 'foldpress', FOLDPRESS_URL . 'assets/js/foldpress.js', array( 'foldpress-ui' ), FOLDPRESS_VERSION, true );
		wp_localize_script(
			'foldpress',
			'FoldPressData',
			array(
				'maxUpload'   => wp_max_upload_size(),
				'zipOk'       => class_exists( 'ZipArchive' ),
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'openUpload'  => isset( $_GET['upload'] ),
				'classicUrl'  => add_query_arg( 'foldpress_classic', 1, admin_url( 'upload.php' ) ),
				'settingsUrl' => current_user_can( 'manage_options' ) ? FoldPress_Settings_Page::url() : '',
				'pagination'  => FoldPress_Settings::get( 'pagination' ),
				'perPage'     => (int) FoldPress_Settings::get( 'per_page' ),
				'defaultView' => FoldPress_Settings::get( 'default_view' ),
				'codeExt'     => FoldPress_Code::MEDIA_EXT,
				'createExt'   => FoldPress_Ajax::creatable_ext(),
				'cloud'       => FoldPress_Cloud::client_config(),
				'cloudSetup'  => current_user_can( 'manage_options' ) ? FoldPress_Settings_Page::url() . '#cloud' : '',
				'allowedExt'  => FoldPress_Ajax::allowed_ext(),
				'codeUrl'     => FoldPress_Code::url( 'media' ),
				'i18n'        => $this->strings(),
			)
		);
	}

	/**
	 * Client strings.
	 *
	 * @return array
	 */
	private function strings() {
		return array(
			'allFiles'      => __( 'All Files', 'appstrdesign-foldpress' ),
			'unassigned'    => __( 'Uncategorized', 'appstrdesign-foldpress' ),
			'folders'       => __( 'Folders', 'appstrdesign-foldpress' ),
			'newFolder'     => __( 'New Folder', 'appstrdesign-foldpress' ),
			'newSubfolder'  => __( 'New Subfolder', 'appstrdesign-foldpress' ),
			'folderName'    => __( 'Folder name', 'appstrdesign-foldpress' ),
			'folderHint'    => __( 'The folder is created inside the folder you are viewing.', 'appstrdesign-foldpress' ),
			'create'        => __( 'Create', 'appstrdesign-foldpress' ),
			'cancel'        => __( 'Cancel', 'appstrdesign-foldpress' ),
			'save'          => __( 'Save', 'appstrdesign-foldpress' ),
			'close'         => __( 'Close', 'appstrdesign-foldpress' ),
			'delete'        => __( 'Delete', 'appstrdesign-foldpress' ),
			'rename'        => __( 'Rename', 'appstrdesign-foldpress' ),
			'move'          => __( 'Move to', 'appstrdesign-foldpress' ),
			'moveHere'      => __( 'Move here', 'appstrdesign-foldpress' ),
			'zip'           => __( 'Compress (ZIP)', 'appstrdesign-foldpress' ),
			'unzip'         => __( 'Extract here', 'appstrdesign-foldpress' ),
			'open'          => __( 'Open', 'appstrdesign-foldpress' ),
			'openNewTab'    => __( 'Open in new tab', 'appstrdesign-foldpress' ),
			'preview'       => __( 'Preview', 'appstrdesign-foldpress' ),
			'details'       => __( 'Details', 'appstrdesign-foldpress' ),
			'download'      => __( 'Download', 'appstrdesign-foldpress' ),
			'copyUrl'       => __( 'Copy URL', 'appstrdesign-foldpress' ),
			'cut'           => __( 'Cut', 'appstrdesign-foldpress' ),
			'paste'         => __( 'Paste here', 'appstrdesign-foldpress' ),
			'cutDone'       => __( 'files cut. Open a folder and press Ctrl+V to move them.', 'appstrdesign-foldpress' ),
			'upload'        => __( 'Upload Files', 'appstrdesign-foldpress' ),
			'dropHere'      => __( 'Drop files to upload', 'appstrdesign-foldpress' ),
			'dropInto'      => __( 'Files will be uploaded to:', 'appstrdesign-foldpress' ),
			'uploading'     => __( 'Uploading', 'appstrdesign-foldpress' ),
			'uploadingOf'   => __( 'Uploading %1$d of %2$d', 'appstrdesign-foldpress' ),
			'uploadDone'    => __( 'Upload finished.', 'appstrdesign-foldpress' ),
			'uploadFailed'  => __( 'Some files could not be uploaded.', 'appstrdesign-foldpress' ),
			'waiting'       => __( 'Waiting', 'appstrdesign-foldpress' ),
			'done'          => __( 'Done', 'appstrdesign-foldpress' ),
			'cancelled'     => __( 'Cancelled', 'appstrdesign-foldpress' ),
			'minimize'      => __( 'Minimize', 'appstrdesign-foldpress' ),
			'search'        => __( 'Search media...', 'appstrdesign-foldpress' ),
			'selectAll'     => __( 'Select all', 'appstrdesign-foldpress' ),
			'selectFolders' => __( 'Select folders', 'appstrdesign-foldpress' ),
			'selectAllFolders' => __( 'Select all folders', 'appstrdesign-foldpress' ),
			'selectFolder'  => __( 'Select folder', 'appstrdesign-foldpress' ),
			'deselect'      => __( 'Deselect', 'appstrdesign-foldpress' ),
			'foldersSelected' => __( 'folders selected', 'appstrdesign-foldpress' ),
			'foldersWord'   => __( 'folders', 'appstrdesign-foldpress' ),
			'subfolders'    => __( 'sub folders', 'appstrdesign-foldpress' ),
			'deleteFolders' => __( 'Delete folders?', 'appstrdesign-foldpress' ),
			/* translators: 1: folders, 2: files */
			'folderTreeInfo' => __( '%1$d folders, %2$d files', 'appstrdesign-foldpress' ),
			'understand'    => __( 'I understand that everything inside will be deleted', 'appstrdesign-foldpress' ),
			'cannotUndo'    => __( 'Sub folders and all their files are removed permanently. This cannot be undone.', 'appstrdesign-foldpress' ),
			'deleteAll'     => __( 'Delete everything', 'appstrdesign-foldpress' ),
			'zipFoldersHint' => __( 'The folder structure is kept inside the archive.', 'appstrdesign-foldpress' ),
			'name'          => __( 'Name', 'appstrdesign-foldpress' ),
			'clear'         => __( 'Clear selection', 'appstrdesign-foldpress' ),
			'selected'      => __( 'selected', 'appstrdesign-foldpress' ),
			'files'         => __( 'files', 'appstrdesign-foldpress' ),
			'empty'         => __( 'This folder is empty.', 'appstrdesign-foldpress' ),
			'emptyHint'     => __( 'Drag files here or use the upload button.', 'appstrdesign-foldpress' ),
			'loading'       => __( 'Loading...', 'appstrdesign-foldpress' ),
			'loadMore'      => __( 'Load more', 'appstrdesign-foldpress' ),
			'prev'          => __( 'Previous', 'appstrdesign-foldpress' ),
			'next'          => __( 'Next', 'appstrdesign-foldpress' ),
			'pageOf'        => __( 'Page %1$d of %2$d', 'appstrdesign-foldpress' ),
			'showing'       => __( 'Showing %1$d of %2$d', 'appstrdesign-foldpress' ),
			'confirm'       => __( 'Are you sure?', 'appstrdesign-foldpress' ),
			'deleteTitle'   => __( 'Delete files permanently?', 'appstrdesign-foldpress' ),
			'confirmDel'    => __( 'Selected files will be permanently deleted. This cannot be undone.', 'appstrdesign-foldpress' ),
			'deleteFolder'  => __( 'Delete folder?', 'appstrdesign-foldpress' ),
			'confirmDelF'   => __( 'The folder, all of its sub folders and every file inside them will be deleted permanently.', 'appstrdesign-foldpress' ),
			'archiveName'   => __( 'Archive name', 'appstrdesign-foldpress' ),
			'zipHint'       => __( 'A new ZIP file is created in the current folder.', 'appstrdesign-foldpress' ),
			'newName'       => __( 'New name', 'appstrdesign-foldpress' ),
			'renamePhysical'     => __( 'Also rename the file on the server', 'appstrdesign-foldpress' ),
			'renamePhysicalHint' => __( 'All image sizes are renamed and every reference in the database (posts, Elementor, WooCommerce, options) is updated.', 'appstrdesign-foldpress' ),
			'chooseFolder'  => __( 'Choose destination folder', 'appstrdesign-foldpress' ),
			'root'          => __( 'Root (no folder)', 'appstrdesign-foldpress' ),
			'type'          => __( 'Type', 'appstrdesign-foldpress' ),
			'size'          => __( 'Size', 'appstrdesign-foldpress' ),
			'dimensions'    => __( 'Dimensions', 'appstrdesign-foldpress' ),
			'date'          => __( 'Date', 'appstrdesign-foldpress' ),
			'title'         => __( 'Title', 'appstrdesign-foldpress' ),
			'alt'           => __( 'Alternative text', 'appstrdesign-foldpress' ),
			'fileUrl'       => __( 'File URL', 'appstrdesign-foldpress' ),
			'fileName'      => __( 'File name', 'appstrdesign-foldpress' ),
			'caption'       => __( 'Caption', 'appstrdesign-foldpress' ),
			'description'   => __( 'Description', 'appstrdesign-foldpress' ),
			'uploadedOn'    => __( 'Uploaded on', 'appstrdesign-foldpress' ),
			'uploadedBy'    => __( 'Uploaded by', 'appstrdesign-foldpress' ),
			'attachedTo'    => __( 'Attached to', 'appstrdesign-foldpress' ),
			'altHint'       => __( 'Describe the image for screen readers and search engines. Leave empty if purely decorative.', 'appstrdesign-foldpress' ),
			'viewPage'      => __( 'View attachment page', 'appstrdesign-foldpress' ),
			'editFull'      => __( 'Advanced edit', 'appstrdesign-foldpress' ),
			'editCode'      => __( 'Edit code', 'appstrdesign-foldpress' ),
			'newFile'       => __( 'New File', 'appstrdesign-foldpress' ),
			'fromCloud'     => __( 'Add from cloud', 'appstrdesign-foldpress' ),
			'fromUrl'       => __( 'Import from URL', 'appstrdesign-foldpress' ),
			'urlHint'       => __( 'One link per line (max 20). The files are downloaded by your server.', 'appstrdesign-foldpress' ),
			'urlLabel'      => __( 'File links', 'appstrdesign-foldpress' ),
			'importing'     => __( 'Importing files...', 'appstrdesign-foldpress' ),
			'import'        => __( 'Import', 'appstrdesign-foldpress' ),
			'importErrors'  => __( 'Some files could not be imported', 'appstrdesign-foldpress' ),
			'cloudSetupTxt' => __( 'Set up cloud services', 'appstrdesign-foldpress' ),
			'cloudNone'     => __( 'No cloud service is enabled yet.', 'appstrdesign-foldpress' ),
			'cloudLoadFail' => __( 'The cloud service could not be loaded. Check the keys in FoldPress > Settings > Cloud and that this site is allowed in the service console.', 'appstrdesign-foldpress' ),
			'davTitle'      => __( 'Browse files', 'appstrdesign-foldpress' ),
			'davEmpty'      => __( 'This folder is empty.', 'appstrdesign-foldpress' ),
			'davUp'         => __( 'Up one level', 'appstrdesign-foldpress' ),
			'davRoot'       => __( 'Root', 'appstrdesign-foldpress' ),
			/* translators: %d: number of selected files */
			'importN'       => __( 'Import %d files', 'appstrdesign-foldpress' ),
			'newFileHint'   => __( 'Type a name with its extension, or pick an extension below.', 'appstrdesign-foldpress' ),
			'fileNameLabel' => __( 'File name', 'appstrdesign-foldpress' ),
			'extOk'         => __( 'This extension is allowed.', 'appstrdesign-foldpress' ),
			'extMissing'    => __( 'Add an extension, for example .txt', 'appstrdesign-foldpress' ),
			/* translators: %s: extension */
			'extNotAllowed' => __( '.%s is not an allowed file type on this site.', 'appstrdesign-foldpress' ),
			/* translators: %s: extension */
			'extNotText'    => __( '.%s is allowed but cannot be created empty. Upload a file instead.', 'appstrdesign-foldpress' ),
			'openInEditor'  => __( 'Open in the code editor after creating', 'appstrdesign-foldpress' ),
			'createIn'      => __( 'Created in:', 'appstrdesign-foldpress' ),
			'sortNewest'    => __( 'Newest first', 'appstrdesign-foldpress' ),
			'sortOldest'    => __( 'Oldest first', 'appstrdesign-foldpress' ),
			'sortName'      => __( 'Name A-Z', 'appstrdesign-foldpress' ),
			'sortNameDesc'  => __( 'Name Z-A', 'appstrdesign-foldpress' ),
			'grid'          => __( 'Grid view', 'appstrdesign-foldpress' ),
			'list'          => __( 'List view', 'appstrdesign-foldpress' ),
			'groupBy'       => __( 'Group by type', 'appstrdesign-foldpress' ),
			'classic'       => __( 'Classic library', 'appstrdesign-foldpress' ),
			'settings'      => __( 'Settings', 'appstrdesign-foldpress' ),
			'menu'          => __( 'Menu', 'appstrdesign-foldpress' ),
			'tooBig'        => __( 'File exceeds the maximum upload size.', 'appstrdesign-foldpress' ),
			'help'          => __( 'Shortcuts & help', 'appstrdesign-foldpress' ),
			'helpKeyboard'  => __( 'Keyboard shortcuts', 'appstrdesign-foldpress' ),
			'helpMouse'     => __( 'Mouse & touch', 'appstrdesign-foldpress' ),
			'shortcuts'     => array(
				array( array( 'Ctrl', 'A' ), __( 'Select all files on the page', 'appstrdesign-foldpress' ) ),
				array( array( 'Ctrl', __( 'Click', 'appstrdesign-foldpress' ) ), __( 'Add or remove a file from the selection', 'appstrdesign-foldpress' ) ),
				array( array( 'Shift', __( 'Click', 'appstrdesign-foldpress' ) ), __( 'Select a range of files', 'appstrdesign-foldpress' ) ),
				array( array( 'Ctrl', 'X' ), __( 'Cut selected files', 'appstrdesign-foldpress' ) ),
				array( array( 'Ctrl', 'V' ), __( 'Paste (move) cut files into the open folder', 'appstrdesign-foldpress' ) ),
				array( array( 'M' ), __( 'Move selected files to a folder', 'appstrdesign-foldpress' ) ),
				array( array( 'F2' ), __( 'Rename the selected file', 'appstrdesign-foldpress' ) ),
				array( array( 'Del' ), __( 'Delete selected files', 'appstrdesign-foldpress' ) ),
				array( array( 'P' ), __( 'Preview the selected file', 'appstrdesign-foldpress' ) ),
				array( array( 'Enter' ), __( 'Open file details', 'appstrdesign-foldpress' ) ),
				array( array( __( 'Space', 'appstrdesign-foldpress' ) ), __( 'Toggle selection of the focused file', 'appstrdesign-foldpress' ) ),
				array( array( ':arrows' ), __( 'Move focus between files', 'appstrdesign-foldpress' ) ),
				array( array( 'N' ), __( 'Create a new folder', 'appstrdesign-foldpress' ) ),
				array( array( 'U' ), __( 'Upload files', 'appstrdesign-foldpress' ) ),
				array( array( '/' ), __( 'Focus the search box', 'appstrdesign-foldpress' ) ),
				array( array( 'Esc' ), __( 'Clear selection / close panels', 'appstrdesign-foldpress' ) ),
				array( array( '?' ), __( 'Show this help', 'appstrdesign-foldpress' ) ),
			),
			'helpGroups'    => array(
				'select' => __( 'Selection', 'appstrdesign-foldpress' ),
				'files'  => __( 'File actions', 'appstrdesign-foldpress' ),
				'nav'    => __( 'Navigation', 'appstrdesign-foldpress' ),
			),
			'mouse'         => array(
				array( 'click', __( 'Click', 'appstrdesign-foldpress' ), __( 'Select a file', 'appstrdesign-foldpress' ) ),
				array( 'dbl', __( 'Double-click', 'appstrdesign-foldpress' ), __( 'Open file details', 'appstrdesign-foldpress' ) ),
				array( 'menu', __( 'Right-click', 'appstrdesign-foldpress' ), __( 'Open the actions menu (preview, rename, move, zip, unzip, download, delete)', 'appstrdesign-foldpress' ) ),
				array( 'move', __( 'Drag to folder', 'appstrdesign-foldpress' ), __( 'Move files or folders into another folder', 'appstrdesign-foldpress' ) ),
				array( 'cloud', __( 'Drop from computer', 'appstrdesign-foldpress' ), __( 'Upload files into the open folder (or onto a folder in the sidebar)', 'appstrdesign-foldpress' ) ),
				array( 'touch', __( 'Long press', 'appstrdesign-foldpress' ), __( 'Open the actions menu on touch devices', 'appstrdesign-foldpress' ) ),
			),
			'speed'         => __( '%s/s', 'appstrdesign-foldpress' ),
			'remaining'     => __( '%s left', 'appstrdesign-foldpress' ),
			/* translators: %s: duration */
			'took'          => __( 'Took %s', 'appstrdesign-foldpress' ),
			'measuring'     => __( 'Measuring...', 'appstrdesign-foldpress' ),
			'importDone'    => __( 'Import finished.', 'appstrdesign-foldpress' ),
			/* translators: 1: current file, 2: total files */
			'importingOf'   => __( 'Importing %1$d of %2$d', 'appstrdesign-foldpress' ),
			/* translators: %d: number of files */
			'nFailed'       => __( '%d failed', 'appstrdesign-foldpress' ),
			/* translators: %d: number of files */
			'nCancelled'    => __( '%d cancelled', 'appstrdesign-foldpress' ),
			'serverFetching' => __( 'Server downloading...', 'appstrdesign-foldpress' ),
			'serverProcessing' => __( 'Adding to the library...', 'appstrdesign-foldpress' ),
			/* translators: 1: downloaded size, 2: total size */
			'downloadedOf'  => __( '%1$s / %2$s downloaded', 'appstrdesign-foldpress' ),
			/* translators: %s: downloaded size */
			'downloadedBytes' => __( '%s downloaded', 'appstrdesign-foldpress' ),
			'cancelAll'     => __( 'Cancel all', 'appstrdesign-foldpress' ),
			'cancelAllTitle' => __( 'Cancel the queue?', 'appstrdesign-foldpress' ),
			/* translators: %d: number of files */
			'cancelAllBody' => __( '%d files that are not finished will be cancelled. A file the server is already downloading may still be added.', 'appstrdesign-foldpress' ),
			'filesDone'     => __( '%1$d of %2$d files', 'appstrdesign-foldpress' ),
			'showFiles'     => __( 'Show files', 'appstrdesign-foldpress' ),
			'hideFiles'     => __( 'Hide files', 'appstrdesign-foldpress' ),
			'preparing'     => __( 'Preparing upload...', 'appstrdesign-foldpress' ),
			'converted'     => __( 'Converted to', 'appstrdesign-foldpress' ),
			'groups'        => array(
				'all'          => __( 'All types', 'appstrdesign-foldpress' ),
				'image'        => __( 'Images', 'appstrdesign-foldpress' ),
				'video'        => __( 'Videos', 'appstrdesign-foldpress' ),
				'audio'        => __( 'Audio', 'appstrdesign-foldpress' ),
				'pdf'          => __( 'PDF', 'appstrdesign-foldpress' ),
				'document'     => __( 'Documents', 'appstrdesign-foldpress' ),
				'spreadsheet'  => __( 'Spreadsheets', 'appstrdesign-foldpress' ),
				'presentation' => __( 'Presentations', 'appstrdesign-foldpress' ),
				'archive'      => __( 'Archives', 'appstrdesign-foldpress' ),
				'code'         => __( 'Code', 'appstrdesign-foldpress' ),
				'font'         => __( 'Fonts', 'appstrdesign-foldpress' ),
				'other'        => __( 'Other', 'appstrdesign-foldpress' ),
			),
		);
	}

	/**
	 * App shell; JS renders everything.
	 */
	public function render() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage media.', 'appstrdesign-foldpress' ) );
		}
		echo '<div class="wrap foldpress-wrap"><h1 class="screen-reader-text">' . esc_html__( 'Media Library', 'appstrdesign-foldpress' ) . '</h1><div id="foldpress-app" class="afp-app"><div class="afp-boot"><span class="afp-spin"></span></div></div></div>';
	}
}
