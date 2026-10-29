<?php
/**
 * FoldPress settings admin (top-level menu). Fully AJAX.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Settings_Page
 */
class FoldPress_Settings_Page {

	const SLUG = 'foldpress-settings';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_foldpress_save_settings', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_foldpress_reset_settings', array( $this, 'ajax_reset' ) );
	}

	/**
	 * Settings URL.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		$svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path fill="black" d="M1.5 3A1.5 1.5 0 0 1 3 1.5h3.17a1.5 1.5 0 0 1 1.06.44l.83.83a.5.5 0 0 0 .35.15H13A1.5 1.5 0 0 1 14.5 4.5v8A1.5 1.5 0 0 1 13 14H3a1.5 1.5 0 0 1-1.5-1.5z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		add_menu_page( __( 'FoldPress Settings', 'appstrdesign-foldpress' ), 'FoldPress', 'manage_options', self::SLUG, array( $this, 'render' ), $svg, 11 );
		add_submenu_page( self::SLUG, __( 'FoldPress Settings', 'appstrdesign-foldpress' ), __( 'Settings', 'appstrdesign-foldpress' ), 'manage_options', self::SLUG, array( $this, 'render' ) );
		add_submenu_page( self::SLUG, __( 'File Manager', 'appstrdesign-foldpress' ), __( 'File Manager', 'appstrdesign-foldpress' ), 'upload_files', 'upload.php?page=' . FoldPress_Admin::SLUG );
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		FoldPress_Admin::enqueue_ui();
		wp_enqueue_script( 'foldpress-settings', FOLDPRESS_URL . 'assets/js/foldpress-settings.js', array( 'foldpress-ui' ), FOLDPRESS_VERSION, true );
		wp_localize_script(
			'foldpress-settings',
			'FoldPressSettings',
			array(
				'i18n' => array(
					'saved'       => __( 'Settings saved.', 'appstrdesign-foldpress' ),
					'unsaved'     => __( 'You have unsaved changes.', 'appstrdesign-foldpress' ),
					'resetTitle'  => __( 'Reset all settings?', 'appstrdesign-foldpress' ),
					'resetBody'   => __( 'All FoldPress settings return to their defaults. Folders and files are not affected.', 'appstrdesign-foldpress' ),
					'reset'       => __( 'Reset', 'appstrdesign-foldpress' ),
					'addType'     => __( 'Add file type', 'appstrdesign-foldpress' ),
					'extension'   => __( 'Extension', 'appstrdesign-foldpress' ),
					'mime'        => __( 'MIME type', 'appstrdesign-foldpress' ),
					'mimeHint'    => __( 'Example: application/x-figma. The uploaded file content must match this type.', 'appstrdesign-foldpress' ),
					'add'         => __( 'Add', 'appstrdesign-foldpress' ),
					'remove'      => __( 'Remove', 'appstrdesign-foldpress' ),
					'blocked'     => __( 'This extension is blocked for security reasons.', 'appstrdesign-foldpress' ),
					'invalidType' => __( 'Enter a valid extension and MIME type.', 'appstrdesign-foldpress' ),
					'exists'      => __( 'This extension is already in the list.', 'appstrdesign-foldpress' ),
					'custom'      => __( 'Custom', 'appstrdesign-foldpress' ),
					'blockedList' => FoldPress_Settings::BLOCKED,
					'scanning'    => __( 'Scanning library...', 'appstrdesign-foldpress' ),
					'scanDone'    => __( 'Scan complete.', 'appstrdesign-foldpress' ),
					'nothing'     => __( 'Nothing to convert. All images are already optimized.', 'appstrdesign-foldpress' ),
					'needFormat'  => __( 'Choose WebP or AVIF and save the settings first.', 'appstrdesign-foldpress' ),
					'convTitle'   => __( 'Convert existing images?', 'appstrdesign-foldpress' ),
					/* translators: 1: number of images, 2: format name */
					'convBody'    => __( '%1$d images will be converted to %2$s. Originals are backed up first and every link in the database is updated. Keep this page open until it finishes.', 'appstrdesign-foldpress' ),
					'convert'     => __( 'Convert', 'appstrdesign-foldpress' ),
					'converting'  => __( 'Converting images', 'appstrdesign-foldpress' ),
					'convDone'    => __( 'Conversion finished.', 'appstrdesign-foldpress' ),
					'saved'       => __( 'Saved', 'appstrdesign-foldpress' ),
					'restTitle'   => __( 'Restore all originals?', 'appstrdesign-foldpress' ),
					/* translators: %d: number of images */
					'restBody'    => __( '%d converted images will be replaced by their original files. Converted files are deleted, links are updated back and the backup folder is removed when finished.', 'appstrdesign-foldpress' ),
					'restore'     => __( 'Restore', 'appstrdesign-foldpress' ),
					'restoring'   => __( 'Restoring originals', 'appstrdesign-foldpress' ),
					'stopped'     => __( 'Stopped. You can continue later.', 'appstrdesign-foldpress' ),
					'errors'      => __( 'Some files could not be processed:', 'appstrdesign-foldpress' ),
					'saveBeforeTest' => __( 'Settings were saved. Testing the connection...', 'appstrdesign-foldpress' ),
					'discardTitle' => __( 'Discard unsaved changes?', 'appstrdesign-foldpress' ),
					'discardBody'  => __( 'These changes have not been saved and will be lost:', 'appstrdesign-foldpress' ),
					'discard'      => __( 'Discard changes', 'appstrdesign-foldpress' ),
					'discarded'    => __( 'Changes discarded.', 'appstrdesign-foldpress' ),
					/* translators: %d: number of changed settings */
					'changesCount' => __( '%d settings changed', 'appstrdesign-foldpress' ),
					'typeAdded'    => __( 'File type added', 'appstrdesign-foldpress' ),
					'typeRemoved'  => __( 'File type removed', 'appstrdesign-foldpress' ),
					'enabledWord'  => __( 'On', 'appstrdesign-foldpress' ),
					'disabledWord' => __( 'Off', 'appstrdesign-foldpress' ),
					'emptyWord'    => __( 'Empty', 'appstrdesign-foldpress' ),
					'changedWord'  => __( 'Changed', 'appstrdesign-foldpress' ),
					'wasWord'      => __( 'was', 'appstrdesign-foldpress' ),
					'migTitle'     => __( 'Import folders?', 'appstrdesign-foldpress' ),
					'copyTitle'    => __( 'Copy folders?', 'appstrdesign-foldpress' ),
					/* translators: 1: source, 2: target */
					'migFromTo'    => __( '%1$s to %2$s', 'appstrdesign-foldpress' ),
					'migFolders'   => __( 'Folders', 'appstrdesign-foldpress' ),
					'migFiles'     => __( 'Files to place', 'appstrdesign-foldpress' ),
					'migSkipped'   => __( 'Not found on this site', 'appstrdesign-foldpress' ),
					'migSkipHint'  => __( 'Files that are not in this media library are skipped. Upload them first, then import again.', 'appstrdesign-foldpress' ),
					'migSafe'      => __( 'Media files are not moved or changed. Only folder assignments are written.', 'appstrdesign-foldpress' ),
					'migStart'     => __( 'Start', 'appstrdesign-foldpress' ),
					'migRunning'   => __( 'Importing folders', 'appstrdesign-foldpress' ),
					'migCopying'   => __( 'Copying folders', 'appstrdesign-foldpress' ),
					'migReading'   => __( 'Reading data...', 'appstrdesign-foldpress' ),
					/* translators: 1: files placed, 2: folders */
					'migDone'      => __( 'Finished: %1$d files placed in %2$d folders.', 'appstrdesign-foldpress' ),
					/* translators: 1: done, 2: total */
					'migCount'     => __( '%1$d / %2$d files', 'appstrdesign-foldpress' ),
					'openManager'  => __( 'Open the file manager', 'appstrdesign-foldpress' ),
					'managerUrl'   => FoldPress_Admin::url(),
				),
			)
		);
	}

	/**
	 * Guard.
	 */
	private function guard() {
		check_ajax_referer( FoldPress_Ajax::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change these settings.', 'appstrdesign-foldpress' ) ), 403 );
		}
	}

	/**
	 * Save.
	 */
	public function ajax_save() {
		$this->guard();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field in FoldPress_Settings::save().
		$in = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array();
		FoldPress_Settings::save( $in );
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'appstrdesign-foldpress' ) ) );
	}

	/**
	 * Reset.
	 */
	public function ajax_reset() {
		$this->guard();
		delete_option( FoldPress_Settings::OPTION );
		wp_send_json_success( array( 'message' => __( 'Settings were reset.', 'appstrdesign-foldpress' ) ) );
	}

	/**
	 * Toggle row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Help.
	 */
	private function toggle( $key, $label, $help = '' ) {
		$on = (int) FoldPress_Settings::get( $key );
		printf(
			'<label class="afp-set-row afp-set-row--toggle"><span class="afp-set-row__txt"><strong>%1$s</strong>%2$s</span><span class="afp-switch"><input type="checkbox" name="s[%3$s]" value="1" %4$s><i></i></span></label>',
			esc_html( $label ),
			$help ? '<small>' . esc_html( $help ) . '</small>' : '',
			esc_attr( $key ),
			checked( $on, 1, false )
		);
	}

	/**
	 * Generic field row wrapper.
	 *
	 * @param string $label Label.
	 * @param string $help  Help.
	 * @param string $html  Control html (already escaped).
	 */
	private function row( $label, $help, $html ) {
		echo '<div class="afp-set-row"><span class="afp-set-row__txt"><strong>' . esc_html( $label ) . '</strong>' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</span><span class="afp-set-row__ctl">' . $html . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html escaped by callers.
	}

	/**
	 * Select control html.
	 *
	 * @param string $key  Key.
	 * @param array  $opts Value => label.
	 * @return string
	 */
	private function select( $key, $opts ) {
		$cur  = (string) FoldPress_Settings::get( $key );
		$html = '<select class="afp-select" name="s[' . esc_attr( $key ) . ']">';
		foreach ( $opts as $v => $l ) {
			$html .= '<option value="' . esc_attr( $v ) . '"' . selected( $cur, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $html . '</select>';
	}

	/**
	 * Number control html.
	 *
	 * @param string $name  Field name (inside s[]).
	 * @param int    $value Value.
	 * @param string $unit  Unit label.
	 * @param int    $min   Min.
	 * @param int    $max   Max.
	 * @return string
	 */
	private function number( $name, $value, $unit = '', $min = 0, $max = 100000 ) {
		return '<span class="afp-num"><input class="afp-input" type="number" name="s[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '">' . ( $unit ? '<em>' . esc_html( $unit ) . '</em>' : '' ) . '</span>';
	}

	/**
	 * Human readable size description. 0 means "no limit" (proportional) in WordPress.
	 *
	 * @param array $d width, height, crop.
	 * @return string Escaped HTML.
	 */
	private static function size_badges( $d ) {
		$w     = (int) $d['width'];
		$h     = (int) $d['height'];
		$parts = array();
		if ( $w && $h ) {
			/* translators: 1: width, 2: height */
			$parts[] = sprintf( __( 'Max %1$d x %2$d px', 'appstrdesign-foldpress' ), $w, $h );
		} elseif ( $w ) {
			/* translators: %d: width in pixels */
			$parts[] = sprintf( __( '%d px wide, height proportional', 'appstrdesign-foldpress' ), $w );
		} elseif ( $h ) {
			/* translators: %d: height in pixels */
			$parts[] = sprintf( __( '%d px tall, width proportional', 'appstrdesign-foldpress' ), $h );
		} else {
			$parts[] = __( 'Original size', 'appstrdesign-foldpress' );
		}
		$html = '<small class="afp-size-badges"><span>' . esc_html( $parts[0] ) . '</span>';
		$html .= '<em class="' . ( ! empty( $d['crop'] ) ? 'is-crop' : 'is-fit' ) . '">' . esc_html( ! empty( $d['crop'] ) ? __( 'Cropped to exact size', 'appstrdesign-foldpress' ) : __( 'Scaled, not cropped', 'appstrdesign-foldpress' ) ) . '</em></small>';
		return $html;
	}

	/**
	 * Render page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = FoldPress_Settings::get();
		$tabs  = array(
			'general' => array( __( 'General', 'appstrdesign-foldpress' ), 'sliders' ),
			'uploads' => array( __( 'Uploads', 'appstrdesign-foldpress' ), 'upload' ),
			'types'   => array( __( 'File Types', 'appstrdesign-foldpress' ), 'files' ),
			'images'  => array( __( 'Image Sizes', 'appstrdesign-foldpress' ), 'image' ),
			'optimize' => array( __( 'WebP / AVIF', 'appstrdesign-foldpress' ), 'zap' ),
			'cloud'   => array( __( 'Cloud', 'appstrdesign-foldpress' ), 'cloud' ),
			'transfer' => array( __( 'Import / Export', 'appstrdesign-foldpress' ), 'download' ),
			'system'  => array( __( 'System', 'appstrdesign-foldpress' ), 'cpu' ),
		);
		$upload = wp_get_upload_dir();
		?>
		<div class="wrap foldpress-wrap afp-settings-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'FoldPress Settings', 'appstrdesign-foldpress' ); ?></h1>
			<form id="afp-settings" class="afp-app afp-set" autocomplete="off">
				<header class="afp-set__head">
					<div class="afp-set__brand"><span class="afp-set__logo" data-icon="folder"></span><div><strong>FoldPress</strong><small><?php esc_html_e( 'Media settings', 'appstrdesign-foldpress' ); ?> &middot; v<?php echo esc_html( FOLDPRESS_VERSION ); ?></small></div></div>
					<div class="afp-set__acts">
						<a class="afp-btn" href="<?php echo esc_url( FoldPress_Admin::url() ); ?>"><span data-icon="files"></span><span><?php esc_html_e( 'File Manager', 'appstrdesign-foldpress' ); ?></span></a>
						<button type="button" class="afp-btn" data-reset><span data-icon="reset"></span><span><?php esc_html_e( 'Reset', 'appstrdesign-foldpress' ); ?></span></button>
						<button type="submit" class="afp-btn afp-btn--primary"><span data-icon="check"></span><span><?php esc_html_e( 'Save Changes', 'appstrdesign-foldpress' ); ?></span></button>
					</div>
				</header>
				<div class="afp-set__body">
					<nav class="afp-set__nav" role="tablist">
						<?php foreach ( $tabs as $k => $t ) : ?>
							<button type="button" role="tab" class="afp-set__tab" data-tab="<?php echo esc_attr( $k ); ?>"><span data-icon="<?php echo esc_attr( $t[1] ); ?>"></span><span><?php echo esc_html( $t[0] ); ?></span></button>
						<?php endforeach; ?>
					</nav>
					<div class="afp-set__panels">

						<section class="afp-set__panel" data-panel="general">
							<h2><?php esc_html_e( 'General', 'appstrdesign-foldpress' ); ?></h2>
							<div class="afp-card-box">
								<?php
								$this->toggle( 'replace_library', __( 'Replace the Media Library', 'appstrdesign-foldpress' ), __( 'Media > Library and Add New open the FoldPress file manager.', 'appstrdesign-foldpress' ) );
								$this->toggle( 'replace_editor', __( 'Replace the attachment edit screen', 'appstrdesign-foldpress' ), __( 'Use the FoldPress editor for media details and advanced editing.', 'appstrdesign-foldpress' ) );
								$this->toggle( 'replace_code_editor', __( 'Replace the Theme and Plugin File Editors', 'appstrdesign-foldpress' ), __( 'Use the FoldPress code editor: file tree, tabs, syntax highlighting for PHP, CSS, JS, HTML, Java, C#, C/C++ and more, find & replace, documentation lookup.', 'appstrdesign-foldpress' ) );
								$this->toggle( 'disable_file_edit', __( 'Turn off the code editors', 'appstrdesign-foldpress' ), __( 'Works like DISALLOW_FILE_EDIT: the Theme and Plugin File Editors, the FoldPress code editor and their menus are removed for every user. Recommended for live sites.', 'appstrdesign-foldpress' ) );
								$this->toggle( 'disable_file_mods', __( 'Lock theme and plugin management', 'appstrdesign-foldpress' ), __( 'Works like DISALLOW_FILE_MODS: installing, uploading, updating and deleting themes and plugins (and core updates) is turned off and the "Add New" menus disappear. Activating and deactivating still works. Switch it off here before updating.', 'appstrdesign-foldpress' ) );
								$this->toggle( 'modal_integration', __( 'Folders in the media modal', 'appstrdesign-foldpress' ), __( 'Adds the folder panel to Add Media, featured image and block editor dialogs.', 'appstrdesign-foldpress' ) );
								$this->toggle( 'modal_theme', __( 'FoldPress design for the media modal', 'appstrdesign-foldpress' ), __( 'Restyles the WordPress media dialogs (classic, Gutenberg and front-end) to match FoldPress.', 'appstrdesign-foldpress' ) );
								$this->toggle( 'lightbox', __( 'Lightbox preview', 'appstrdesign-foldpress' ), __( 'Preview images, videos, audio, PDF, Word, Excel, PowerPoint, text, Markdown, CSV and ZIP files in a full-screen viewer.', 'appstrdesign-foldpress' ) );
								$this->toggle( 'office_viewer', __( 'Office full-layout view (Microsoft)', 'appstrdesign-foldpress' ), __( 'Adds a "Full layout" button for Word, Excel and PowerPoint files using Microsoft Office Online. The file URL is sent to Microsoft and the site must be publicly reachable. The built-in quick preview always works without it.', 'appstrdesign-foldpress' ) );
								$this->row(
									__( 'Notification position', 'appstrdesign-foldpress' ),
									__( 'Where success and error notifications appear.', 'appstrdesign-foldpress' ),
									$this->select(
										'toast_position',
										array(
											'top-right'     => __( 'Top right', 'appstrdesign-foldpress' ),
											'top-center'    => __( 'Top center', 'appstrdesign-foldpress' ),
											'top-left'      => __( 'Top left', 'appstrdesign-foldpress' ),
											'bottom-right'  => __( 'Bottom right', 'appstrdesign-foldpress' ),
											'bottom-center' => __( 'Bottom center', 'appstrdesign-foldpress' ),
											'bottom-left'   => __( 'Bottom left', 'appstrdesign-foldpress' ),
										)
									)
								);
								?>
							</div>
							<h3><?php esc_html_e( 'Listing', 'appstrdesign-foldpress' ); ?></h3>
							<div class="afp-card-box">
								<?php
								$this->row( __( 'Pagination', 'appstrdesign-foldpress' ), __( 'How further files are loaded in the file manager.', 'appstrdesign-foldpress' ), $this->select( 'pagination', array( 'loadmore' => __( 'Load more button', 'appstrdesign-foldpress' ), 'numbers' => __( 'Numbered pages', 'appstrdesign-foldpress' ) ) ) );
								$this->row( __( 'Files per page', 'appstrdesign-foldpress' ), __( 'Between 12 and 200.', 'appstrdesign-foldpress' ), $this->number( 'per_page', $s['per_page'], '', 12, 200 ) );
								$this->row( __( 'Default view', 'appstrdesign-foldpress' ), '', $this->select( 'default_view', array( 'grid' => __( 'Grid view', 'appstrdesign-foldpress' ), 'list' => __( 'List view', 'appstrdesign-foldpress' ) ) ) );
								?>
							</div>
						</section>

						<section class="afp-set__panel" data-panel="uploads">
							<h2><?php esc_html_e( 'Uploads', 'appstrdesign-foldpress' ); ?></h2>
							<div class="afp-card-box">
								<?php
								$this->row(
									__( 'Upload folder structure', 'appstrdesign-foldpress' ),
									__( 'Where new files are stored inside the uploads directory. Existing files are not moved and keep working.', 'appstrdesign-foldpress' ),
									$this->select(
										'subdir_mode',
										array(
											'yearmonth' => __( 'Year / month (WordPress default)', 'appstrdesign-foldpress' ),
											'flat'      => __( 'Single folder (no sub-folders)', 'appstrdesign-foldpress' ),
											'custom'    => __( 'Custom folder + year / month', 'appstrdesign-foldpress' ),
											'folder'    => __( 'Mirror FoldPress folders', 'appstrdesign-foldpress' ),
										)
									)
								);
								$this->row( __( 'Custom folder name', 'appstrdesign-foldpress' ), __( 'Used with "Custom folder + year / month". Letters, numbers, dashes and slashes.', 'appstrdesign-foldpress' ), '<span class="afp-prefix"><em>' . esc_html( wp_basename( $upload['basedir'] ) ) . '/</em><input class="afp-input" type="text" name="s[subdir_custom]" value="' . esc_attr( $s['subdir_custom'] ) . '"></span>' );
								$this->row( __( 'Maximum upload size', 'appstrdesign-foldpress' ), sprintf( /* translators: %s: server limit */ __( '0 = server limit (%s). A lower value can be set; the server limit cannot be exceeded.', 'appstrdesign-foldpress' ), size_format( wp_max_upload_size() ) ), $this->number( 'max_upload_mb', $s['max_upload_mb'], 'MB', 0, 100000 ) );
								$this->toggle( 'sanitize_names', __( 'Clean file names', 'appstrdesign-foldpress' ), __( 'Converts Turkish and accented characters (ş, ğ, ı, ü...) and spaces into URL-safe names.', 'appstrdesign-foldpress' ) );
								$this->toggle( 'lowercase_names', __( 'Lowercase file names', 'appstrdesign-foldpress' ) );
								?>
							</div>
						</section>

						<section class="afp-set__panel" data-panel="types">
							<h2><?php esc_html_e( 'File Types', 'appstrdesign-foldpress' ); ?></h2>
							<p class="afp-set__lead"><?php esc_html_e( 'This list decides what can be uploaded: enabled types are accepted, disabled types are refused everywhere (media library, editors, front-end). Executable and script types are always blocked.', 'appstrdesign-foldpress' ); ?></p>
							<div class="afp-types-bar">
								<label class="afp-search"><span data-icon="search"></span><input type="search" data-type-search placeholder="<?php esc_attr_e( 'Search extensions...', 'appstrdesign-foldpress' ); ?>"></label>
								<button type="button" class="afp-btn" data-types-all="1"><?php esc_html_e( 'Enable all', 'appstrdesign-foldpress' ); ?></button>
								<button type="button" class="afp-btn" data-types-all="0"><?php esc_html_e( 'Disable all', 'appstrdesign-foldpress' ); ?></button>
								<button type="button" class="afp-btn afp-btn--primary" data-add-type><span data-icon="plus"></span><span><?php esc_html_e( 'Add file type', 'appstrdesign-foldpress' ); ?></span></button>
							</div>
							<div class="afp-card-box afp-types" data-types>
								<?php
								$disabled = (array) $s['disabled_types'];
								foreach ( FoldPress_Settings::core_types() as $exts => $mime ) {
									$first = explode( '|', $exts );
									if ( array_intersect( $first, FoldPress_Settings::BLOCKED ) ) {
										continue;
									}
									$group = FoldPress_Files::group( $mime, $first[0] );
									printf(
										'<label class="afp-type" data-search="%1$s"><span class="afp-type__ext" data-group="%2$s">%3$s</span><span class="afp-type__mime">%4$s</span><span class="afp-switch"><input type="checkbox" name="s[enabled_types][]" value="%5$s" %6$s><i></i></span></label>',
										esc_attr( str_replace( '|', ' ', $exts ) . ' ' . $mime ),
										esc_attr( $group ),
										esc_html( str_replace( '|', ', ', $exts ) ),
										esc_html( $mime ),
										esc_attr( $exts ),
										checked( ! in_array( $exts, $disabled, true ), true, false )
									);
								}
								?>
							</div>
							<h3><?php esc_html_e( 'Custom file types', 'appstrdesign-foldpress' ); ?></h3>
							<div class="afp-card-box afp-types" data-custom-types>
								<?php
								$i = 0;
								foreach ( FoldPress_Settings::custom_map( false ) as $ext => $row ) {
									printf(
										'<label class="afp-type" data-search="%1$s"><span class="afp-type__ext" data-group="other">%2$s</span><span class="afp-type__mime">%3$s</span><input type="hidden" name="s[custom_types][%4$d][ext]" value="%2$s"><input type="hidden" name="s[custom_types][%4$d][mime]" value="%3$s"><span class="afp-type__ctl"><span class="afp-switch"><input type="checkbox" name="s[custom_types][%4$d][on]" value="1" %6$s><i></i></span><button type="button" class="afp-btn-ic afp-btn-ic--sm" data-remove-type aria-label="%5$s"><span data-icon="trash"></span></button></span></label>',
										esc_attr( $ext . ' ' . $row['mime'] ),
										esc_attr( $ext ),
										esc_attr( $row['mime'] ),
										(int) $i,
										esc_attr__( 'Remove', 'appstrdesign-foldpress' ),
										checked( $row['on'], 1, false )
									);
									++$i;
								}
								?>
								<p class="afp-types__empty"><?php esc_html_e( 'No custom file types yet.', 'appstrdesign-foldpress' ); ?></p>
							</div>
						</section>

						<section class="afp-set__panel" data-panel="images">
							<h2><?php esc_html_e( 'Image Sizes', 'appstrdesign-foldpress' ); ?></h2>
							<div class="afp-card-box">
								<?php
								$core = array(
									'thumbnail' => __( 'Thumbnail', 'appstrdesign-foldpress' ),
									'medium'    => __( 'Medium', 'appstrdesign-foldpress' ),
									'large'     => __( 'Large', 'appstrdesign-foldpress' ),
								);
								foreach ( $core as $k => $label ) {
									$this->row(
										$label,
										__( 'Maximum width x height in pixels.', 'appstrdesign-foldpress' ),
										'<span class="afp-dim">' . $this->number( $k . '_w', (int) get_option( $k . '_size_w' ), 'px' ) . '<b>x</b>' . $this->number( $k . '_h', (int) get_option( $k . '_size_h' ), 'px' ) . '</span>'
									);
								}
								echo '<label class="afp-set-row afp-set-row--toggle"><span class="afp-set-row__txt"><strong>' . esc_html__( 'Crop thumbnails to exact size', 'appstrdesign-foldpress' ) . '</strong></span><span class="afp-switch"><input type="checkbox" name="s[thumbnail_crop]" value="1" ' . checked( (int) get_option( 'thumbnail_crop' ), 1, false ) . '><i></i></span></label>';
								$this->row( __( 'Big image threshold', 'appstrdesign-foldpress' ), __( 'Larger uploads are scaled down to this size. 0 keeps the original size.', 'appstrdesign-foldpress' ), $this->number( 'big_image_threshold', $s['big_image_threshold'], 'px', 0, 20000 ) );
								$this->row( __( 'JPEG / WebP quality', 'appstrdesign-foldpress' ), __( 'Compression quality for generated images (10-100).', 'appstrdesign-foldpress' ), $this->number( 'jpeg_quality', $s['jpeg_quality'], '%', 10, 100 ) );
								?>
							</div>
							<?php $extra = FoldPress_Settings::extra_sizes(); ?>
							<?php if ( $extra ) : ?>
								<h3><?php esc_html_e( 'Additional sizes (themes and plugins)', 'appstrdesign-foldpress' ); ?></h3>
								<p class="afp-set__lead"><?php esc_html_e( 'Turn off sizes you do not need to save disk space. Applies to new uploads.', 'appstrdesign-foldpress' ); ?></p>
								<div class="afp-card-box">
									<?php
									$all = wp_get_registered_image_subsizes();
									foreach ( $extra as $size ) {
										$d = isset( $all[ $size ] ) ? $all[ $size ] : array( 'width' => 0, 'height' => 0, 'crop' => false );
										echo '<label class="afp-set-row afp-set-row--toggle"><span class="afp-set-row__txt"><strong>' . esc_html( $size ) . '</strong>' . self::size_badges( $d ) . '</span><span class="afp-switch"><input type="checkbox" name="s[enabled_sizes][]" value="' . esc_attr( $size ) . '" ' . checked( ! in_array( $size, (array) $s['disabled_sizes'], true ), true, false ) . '><i></i></span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- size_badges() escapes.
									}
									?>
								</div>
							<?php endif; ?>
						</section>

						<section class="afp-set__panel" data-panel="optimize">
							<h2><?php esc_html_e( 'WebP / AVIF conversion', 'appstrdesign-foldpress' ); ?></h2>
							<p class="afp-set__lead"><?php esc_html_e( 'JPEG and PNG images are converted automatically on upload. Existing media can be converted below. Originals are always backed up and can be restored at any time. Files that already are WebP or AVIF are never touched.', 'appstrdesign-foldpress' ); ?></p>
							<div class="afp-card-box">
								<?php
								$webp_ok = FoldPress_Converter::supports( 'webp' );
								$avif_ok = FoldPress_Converter::supports( 'avif' );
								$fmt     = (string) $s['convert_format'];
								$opts    = array(
									''     => array( __( 'Off', 'appstrdesign-foldpress' ), __( 'Keep original formats', 'appstrdesign-foldpress' ), true ),
									'webp' => array( 'WebP', $webp_ok ? __( 'Best compatibility, about 30% smaller', 'appstrdesign-foldpress' ) : __( 'Not supported by this server', 'appstrdesign-foldpress' ), $webp_ok ),
									'avif' => array( 'AVIF', $avif_ok ? __( 'Smallest files, about 50% smaller', 'appstrdesign-foldpress' ) : __( 'Not supported by this server', 'appstrdesign-foldpress' ), $avif_ok ),
								);
								$cards = '<div class="afp-choice" role="radiogroup">';
								foreach ( $opts as $v => $o ) {
									$cards .= '<label class="afp-choice__item' . ( $o[2] ? '' : ' is-disabled' ) . '"><input type="radio" name="s[convert_format]" value="' . esc_attr( $v ) . '" ' . checked( $fmt, $v, false ) . disabled( ! $o[2], true, false ) . '><span><strong>' . esc_html( $o[0] ) . '</strong><small>' . esc_html( $o[1] ) . '</small></span></label>';
								}
								$cards .= '</div>';
								$this->row( __( 'Output format', 'appstrdesign-foldpress' ), '', $cards );

								$q     = (string) $s['convert_quality'];
								$qopts = array(
									'low'    => __( 'Low', 'appstrdesign-foldpress' ),
									'medium' => __( 'Medium', 'appstrdesign-foldpress' ),
									'high'   => __( 'High', 'appstrdesign-foldpress' ),
									'custom' => __( 'Custom', 'appstrdesign-foldpress' ),
								);
								$seg = '<div class="afp-segq" role="radiogroup">';
								foreach ( $qopts as $v => $l ) {
									$hint = 'custom' === $v ? '1-100' : 'WebP ' . FoldPress_Converter::PRESETS['webp'][ $v ] . ' / AVIF ' . FoldPress_Converter::PRESETS['avif'][ $v ];
									$seg .= '<label><input type="radio" name="s[convert_quality]" value="' . esc_attr( $v ) . '" ' . checked( $q, $v, false ) . '><span><b>' . esc_html( $l ) . '</b><small>' . esc_html( $hint ) . '</small></span></label>';
								}
								$seg .= '</div>';
								$this->row( __( 'Quality', 'appstrdesign-foldpress' ), __( 'Higher quality means larger files. Medium is recommended for most sites.', 'appstrdesign-foldpress' ), $seg );
								echo '<div class="afp-set-row" data-custom-q' . ( 'custom' === $q ? '' : ' hidden' ) . '><span class="afp-set-row__txt"><strong>' . esc_html__( 'Custom quality', 'appstrdesign-foldpress' ) . '</strong><small>' . esc_html__( 'Lower values give smaller files with more visible compression.', 'appstrdesign-foldpress' ) . '</small></span><span class="afp-range"><input type="range" min="1" max="100" name="s[convert_custom]" value="' . esc_attr( $s['convert_custom'] ) . '"><output>' . esc_html( $s['convert_custom'] ) . '</output></span></div>';
								?>
							</div>
							<h3><?php esc_html_e( 'Existing media', 'appstrdesign-foldpress' ); ?></h3>
							<div class="afp-conv" data-conv>
								<div class="afp-conv__stats">
									<div><span data-icon="image"></span><small><?php esc_html_e( 'Images', 'appstrdesign-foldpress' ); ?></small><strong data-stat="total">-</strong></div>
									<div class="is-warn"><span data-icon="zap"></span><small><?php esc_html_e( 'Can be converted', 'appstrdesign-foldpress' ); ?></small><strong data-stat="pending">-</strong></div>
									<div class="is-ok"><span data-icon="check"></span><small><?php esc_html_e( 'Converted (backed up)', 'appstrdesign-foldpress' ); ?></small><strong data-stat="converted">-</strong></div>
									<div><span data-icon="layers"></span><small><?php esc_html_e( 'Already WebP / AVIF', 'appstrdesign-foldpress' ); ?></small><strong data-stat="modern">-</strong></div>
								</div>
								<div class="afp-conv__run" hidden>
									<div class="afp-conv__top"><strong data-run-title></strong><span data-run-count></span></div>
									<div class="afp-progress"><i data-run-bar></i></div>
									<div class="afp-conv__meta"><span data-run-pct>0%</span><span data-run-saved></span></div>
									<ul class="afp-conv__errors" hidden></ul>
								</div>
								<div class="afp-conv__acts">
									<button type="button" class="afp-btn" data-conv-scan><span data-icon="search"></span><span><?php esc_html_e( 'Scan library', 'appstrdesign-foldpress' ); ?></span></button>
									<button type="button" class="afp-btn afp-btn--primary" data-conv-start disabled><span data-icon="zap"></span><span><?php esc_html_e( 'Convert existing images', 'appstrdesign-foldpress' ); ?></span></button>
									<button type="button" class="afp-btn" data-conv-stop hidden><span data-icon="x"></span><span><?php esc_html_e( 'Stop', 'appstrdesign-foldpress' ); ?></span></button>
									<button type="button" class="afp-btn afp-btn--danger" data-conv-restore disabled><span data-icon="reset"></span><span><?php esc_html_e( 'Restore originals', 'appstrdesign-foldpress' ); ?></span></button>
								</div>
							</div>
						</section>

						<section class="afp-set__panel" data-panel="cloud">
							<h2><?php esc_html_e( 'Cloud import', 'appstrdesign-foldpress' ); ?></h2>
							<p class="afp-set__lead"><?php esc_html_e( 'Let editors pick files from cloud storage and add them to the media library. Files are downloaded by your server and go through the same type, size and conversion rules as normal uploads.', 'appstrdesign-foldpress' ); ?></p>
							<?php
							$origin = untrailingslashit( home_url() );
							$origin = preg_replace( '#^(https?://[^/]+).*$#', '$1', admin_url() );
							$page   = admin_url( 'upload.php?page=foldpress' );
							$field  = function ( $key, $label, $hint = '', $type = 'text', $ph = '' ) use ( $s ) {
								return '<label class="afp-field"><span class="afp-field__label">' . esc_html( $label ) . '</span><input class="afp-input" type="' . esc_attr( $type ) . '" name="s[' . esc_attr( $key ) . ']" value="' . esc_attr( $s[ $key ] ) . '" placeholder="' . esc_attr( $ph ) . '" autocomplete="off" spellcheck="false">' . ( $hint ? '<span class="afp-field__hint">' . esc_html( $hint ) . '</span>' : '' ) . '</label>';
							};
							$copy = function ( $label, $value ) {
								return '<div class="afp-cloud__copy"><small>' . esc_html( $label ) . '</small><span class="afp-copy"><input class="afp-input" value="' . esc_attr( $value ) . '" readonly data-selectall><button type="button" class="afp-btn-ic" data-copy-val="' . esc_attr( $value ) . '" aria-label="' . esc_attr__( 'Copy', 'appstrdesign-foldpress' ) . '"><span data-icon="link"></span></button></span></div>';
							};
							$card = function ( $key, $name, $desc, $color, $icon, $body ) use ( $s ) {
								echo '<div class="afp-cloud" style="--c:' . esc_attr( $color ) . '"><label class="afp-cloud__head"><span class="afp-cloud__logo"><span data-icon="' . esc_attr( $icon ) . '"></span></span><span class="afp-cloud__txt"><strong>' . esc_html( $name ) . '</strong><small>' . esc_html( $desc ) . '</small></span><span class="afp-switch"><input type="checkbox" name="s[' . esc_attr( $key ) . ']" value="1" ' . checked( (int) $s[ $key ], 1, false ) . ' data-cloud-toggle><i></i></span></label><div class="afp-cloud__body">' . $body . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $body is escaped by its builders.
							};
							?>
							<div class="afp-cloud-grid">
								<?php
								$card(
									'gdrive_on',
									'Google Drive',
									__( 'Pick files and Google Docs / Sheets / Slides (exported as DOCX, XLSX, PPTX).', 'appstrdesign-foldpress' ),
									'#1a73e8',
									'cloud',
									'<ol class="afp-cloud__steps"><li>' . esc_html__( 'Google Cloud Console: create a project and enable "Google Drive API" and "Google Picker API".', 'appstrdesign-foldpress' ) . '</li><li>' . esc_html__( 'Create an API key and an OAuth client ID (Web application). Add the origin below to "Authorized JavaScript origins".', 'appstrdesign-foldpress' ) . '</li><li>' . esc_html__( 'Paste the values here. The project number is the App ID.', 'appstrdesign-foldpress' ) . '</li></ol>'
									. $copy( __( 'Authorized JavaScript origin', 'appstrdesign-foldpress' ), $origin )
									. '<div class="afp-grid2">' . $field( 'gdrive_client_id', __( 'OAuth client ID', 'appstrdesign-foldpress' ), '', 'text', 'xxxx.apps.googleusercontent.com' ) . $field( 'gdrive_api_key', __( 'API key', 'appstrdesign-foldpress' ), '', 'text', 'AIza...' ) . '</div>'
									. $field( 'gdrive_app_id', __( 'App ID (project number)', 'appstrdesign-foldpress' ), '', 'text', '123456789012' )
								);
								$card(
									'dropbox_on',
									'Dropbox',
									__( 'Choose files with the official Dropbox Chooser.', 'appstrdesign-foldpress' ),
									'#0061ff',
									'cloud',
									'<ol class="afp-cloud__steps"><li>' . esc_html__( 'Dropbox App Console: create an app (Scoped access).', 'appstrdesign-foldpress' ) . '</li><li>' . esc_html__( 'Under "Chooser / Saver / Embedder domains" add your domain below.', 'appstrdesign-foldpress' ) . '</li><li>' . esc_html__( 'Paste the App key.', 'appstrdesign-foldpress' ) . '</li></ol>'
									. $copy( __( 'Domain', 'appstrdesign-foldpress' ), (string) wp_parse_url( admin_url(), PHP_URL_HOST ) )
									. $field( 'dropbox_key', __( 'App key', 'appstrdesign-foldpress' ) )
								);
								$card(
									'onedrive_on',
									'Microsoft OneDrive',
									__( 'Personal OneDrive and OneDrive for Business / SharePoint.', 'appstrdesign-foldpress' ),
									'#0078d4',
									'cloud',
									'<ol class="afp-cloud__steps"><li>' . esc_html__( 'Azure portal > App registrations: register an app (any account type).', 'appstrdesign-foldpress' ) . '</li><li>' . esc_html__( 'Authentication > add platform "Single-page application" with the redirect URI below. Enable access and ID tokens.', 'appstrdesign-foldpress' ) . '</li><li>' . esc_html__( 'Paste the Application (client) ID.', 'appstrdesign-foldpress' ) . '</li></ol>'
									. $copy( __( 'Redirect URI', 'appstrdesign-foldpress' ), $page )
									. $field( 'onedrive_client_id', __( 'Application (client) ID', 'appstrdesign-foldpress' ), '', 'text', '00000000-0000-0000-0000-000000000000' )
								);
								$card(
									'dav_on',
									__( 'WebDAV / own cloud', 'appstrdesign-foldpress' ),
									__( 'Nextcloud, ownCloud, Synology, QNAP, pCloud, Yandex Disk, Seafile and any WebDAV server, including servers on your own network.', 'appstrdesign-foldpress' ),
									'#0082c9',
									'cpu',
									'<p class="afp-field__hint">' . esc_html__( 'Nextcloud / ownCloud address example: https://cloud.example.com/remote.php/dav/files/USERNAME/ . Use an app password instead of your main password.', 'appstrdesign-foldpress' ) . '</p>'
									. $field( 'dav_label', __( 'Display name', 'appstrdesign-foldpress' ), '', 'text', 'Nextcloud' )
									. $field( 'dav_url', __( 'WebDAV address', 'appstrdesign-foldpress' ), '', 'url', 'https://' )
									. '<div class="afp-grid2">' . $field( 'dav_user', __( 'User name', 'appstrdesign-foldpress' ) )
									. '<label class="afp-field"><span class="afp-field__label">' . esc_html__( 'Password / app password', 'appstrdesign-foldpress' ) . '</span><input class="afp-input" type="password" name="s[dav_pass]" value="" placeholder="' . esc_attr( get_option( FoldPress_Cloud::DAV_PASS ) ? __( 'Saved - leave empty to keep', 'appstrdesign-foldpress' ) : '' ) . '" autocomplete="new-password"></label></div>'
									. '<button type="button" class="afp-btn" data-dav-test><span data-icon="zap"></span><span>' . esc_html__( 'Test connection', 'appstrdesign-foldpress' ) . '</span></button>'
								);
								$card(
									'cloud_url',
									__( 'Import from URL', 'appstrdesign-foldpress' ),
									__( 'Paste one or more public file links (http / https). Links to private networks are refused.', 'appstrdesign-foldpress' ),
									'#7c3aed',
									'link',
									'<p class="afp-field__hint">' . esc_html__( 'Works with any public download link: shared links, CDN files, other websites.', 'appstrdesign-foldpress' ) . '</p>'
								);
								?>
							</div>
							<div class="afp-cloud__note"><span data-icon="info"></span><div><strong><?php esc_html_e( 'iCloud Drive', 'appstrdesign-foldpress' ); ?></strong><span><?php esc_html_e( 'Apple does not offer a public web API for iCloud Drive files, so a direct integration is not possible. Download the files on your device and upload them, or sync them to a WebDAV-capable service.', 'appstrdesign-foldpress' ); ?></span></div></div>
						</section>

						<section class="afp-set__panel" data-panel="transfer">
							<h2><?php esc_html_e( 'Import / Export', 'appstrdesign-foldpress' ); ?></h2>
							<p class="afp-set__lead"><?php esc_html_e( 'Bring your folders over from another media folder plugin, move them between sites, or hand them to another plugin. Only folder assignments are written; media files are never moved, renamed or deleted.', 'appstrdesign-foldpress' ); ?></p>
							<div class="afp-mig" data-mig>
								<h3><?php esc_html_e( 'Import from another plugin', 'appstrdesign-foldpress' ); ?></h3>
								<?php $sources = FoldPress_Migrate::sources(); ?>
								<?php if ( $sources ) : ?>
									<div class="afp-mig__grid">
										<?php foreach ( $sources as $k => $src ) : ?>
											<div class="afp-mig__card">
												<span class="afp-mig__logo"><span data-icon="folder"></span></span>
												<span class="afp-mig__txt"><strong><?php echo esc_html( $src['name'] ); ?></strong><small><?php echo esc_html( $src['author'] ); ?> &middot; <?php echo esc_html( sprintf( /* translators: 1: folders, 2: files */ __( '%1$d folders, %2$d files', 'appstrdesign-foldpress' ), $src['folders'], $src['files'] ) ); ?></small></span>
												<button type="button" class="afp-btn afp-btn--primary" data-mig-source="<?php echo esc_attr( $k ); ?>"><span data-icon="download"></span><span><?php esc_html_e( 'Import', 'appstrdesign-foldpress' ); ?></span></button>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<div class="afp-mig__empty"><span data-icon="info"></span><div><strong><?php esc_html_e( 'No folders from other plugins were found on this site.', 'appstrdesign-foldpress' ); ?></strong><span><?php esc_html_e( 'Supported: FileBird, Real Media Library, HappyFiles, Folders (Premio), WP Media Folder, Enhanced Media Library, Media Library Folders, Wicked Folders, Media Library Assistant, Mediamatic, WP Media Folders. Their data is read even when the plugin is deactivated.', 'appstrdesign-foldpress' ); ?></span></div></div>
								<?php endif; ?>

								<h3><?php esc_html_e( 'Import from a file', 'appstrdesign-foldpress' ); ?></h3>
								<div class="afp-card-box">
									<div class="afp-set-row"><span class="afp-set-row__txt"><strong><?php esc_html_e( 'Export file', 'appstrdesign-foldpress' ); ?></strong><small><?php esc_html_e( 'FoldPress JSON / CSV, FileBird CSV or any JSON / CSV with folder names and file paths. Files are matched by ID on the same site, otherwise by file path and name.', 'appstrdesign-foldpress' ); ?></small></span>
										<span class="afp-set-row__ctl afp-mig__file"><label class="afp-btn"><span data-icon="upload"></span><span data-mig-fname><?php esc_html_e( 'Choose file', 'appstrdesign-foldpress' ); ?></span><input type="file" form="afp-mig-none" accept=".json,.csv,.txt,application/json,text/csv" data-mig-file hidden></label><button type="button" class="afp-btn afp-btn--primary" data-mig-import disabled><span data-icon="download"></span><span><?php esc_html_e( 'Import', 'appstrdesign-foldpress' ); ?></span></button></span>
									</div>
								</div>

								<h3><?php esc_html_e( 'Import options', 'appstrdesign-foldpress' ); ?></h3>
								<div class="afp-card-box">
									<?php
									$this->row(
										__( 'Folders', 'appstrdesign-foldpress' ),
										'',
										'<div class="afp-segq afp-segq--2" role="radiogroup"><label><input type="radio" name="afp_mig_mode" form="afp-mig-none" data-mig-mode value="merge" checked><span><b>' . esc_html__( 'Merge', 'appstrdesign-foldpress' ) . '</b><small>' . esc_html__( 'Reuse folders with the same name', 'appstrdesign-foldpress' ) . '</small></span></label><label><input type="radio" name="afp_mig_mode" form="afp-mig-none" data-mig-mode value="new"><span><b>' . esc_html__( 'Separate', 'appstrdesign-foldpress' ) . '</b><small>' . esc_html__( 'Inside a new "import" folder', 'appstrdesign-foldpress' ) . '</small></span></label></div>'
									);
									echo '<label class="afp-set-row afp-set-row--toggle"><span class="afp-set-row__txt"><strong>' . esc_html__( 'Move files that already have a folder', 'appstrdesign-foldpress' ) . '</strong><small>' . esc_html__( 'Off: only files without a folder are placed. On: the imported folder wins.', 'appstrdesign-foldpress' ) . '</small></span><span class="afp-switch"><input type="checkbox" form="afp-mig-none" data-mig-replace><i></i></span></label>';
									?>
								</div>

								<h3><?php esc_html_e( 'Export', 'appstrdesign-foldpress' ); ?></h3>
								<div class="afp-card-box">
									<?php
									$base = admin_url( 'admin-post.php?action=foldpress_export' );
									$exp  = array(
										'json'     => array( __( 'FoldPress JSON', 'appstrdesign-foldpress' ), __( 'Complete backup: folder tree, file IDs and paths. Best for another FoldPress site.', 'appstrdesign-foldpress' ) ),
										'csv'      => array( __( 'CSV with folder paths', 'appstrdesign-foldpress' ), __( 'One row per file: folder path, file path, ID and URL. Opens in Excel and Google Sheets.', 'appstrdesign-foldpress' ) ),
										'filebird' => array( __( 'FileBird compatible CSV', 'appstrdesign-foldpress' ), __( 'The column layout of the FileBird export (id, name, parent, attachment_ids...), for the FileBird CSV import on this site or a copy of it.', 'appstrdesign-foldpress' ) ),
									);
									foreach ( $exp as $f => $e ) {
										$this->row( $e[0], $e[1], '<a class="afp-btn" href="' . esc_url( wp_nonce_url( add_query_arg( 'format', $f, $base ), 'foldpress_export' ) ) . '"><span data-icon="download"></span><span>' . esc_html__( 'Download', 'appstrdesign-foldpress' ) . '</span></a>' );
									}
									?>
								</div>

								<h3><?php esc_html_e( 'Copy FoldPress folders into another plugin', 'appstrdesign-foldpress' ); ?></h3>
								<p class="afp-set__lead"><?php esc_html_e( 'Writes your FoldPress folders and file assignments into the structure of the other plugin, so it shows them as soon as it is activated. FoldPress folders stay as they are.', 'appstrdesign-foldpress' ); ?></p>
								<div class="afp-card-box">
									<?php
									$opts = '';
									foreach ( FoldPress_Migrate::targets() as $k => $n ) {
										$opts .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $n ) . '</option>';
									}
									$this->row( __( 'Target plugin', 'appstrdesign-foldpress' ), __( 'The same options apply: merge or separate folder, move files that already have a folder.', 'appstrdesign-foldpress' ), '<span class="afp-mig__file"><select class="afp-select" form="afp-mig-none" data-mig-target>' . $opts . '</select><button type="button" class="afp-btn afp-btn--primary" data-mig-copy><span data-icon="upload"></span><span>' . esc_html__( 'Copy folders', 'appstrdesign-foldpress' ) . '</span></button></span>' );
									?>
								</div>

								<div class="afp-conv__run afp-mig__run" data-mig-run hidden>
									<div class="afp-conv__top"><strong data-mig-title></strong><span data-mig-count></span></div>
									<div class="afp-progress"><i data-mig-bar></i></div>
									<div class="afp-conv__meta"><span data-mig-pct>0%</span><span data-mig-meta></span></div>
								</div>
							</div>
						</section>

						<section class="afp-set__panel" data-panel="system">
							<h2><?php esc_html_e( 'System', 'appstrdesign-foldpress' ); ?></h2>
							<div class="afp-sys">
								<?php
								$checks = array(
									array( __( 'Upload size limit', 'appstrdesign-foldpress' ), size_format( wp_max_upload_size() ), true ),
									array( 'upload_max_filesize', ini_get( 'upload_max_filesize' ), true ),
									array( 'post_max_size', ini_get( 'post_max_size' ), true ),
									array( 'memory_limit', ini_get( 'memory_limit' ), true ),
									array( 'ZipArchive', class_exists( 'ZipArchive' ) ? __( 'Available', 'appstrdesign-foldpress' ) : __( 'Missing', 'appstrdesign-foldpress' ), class_exists( 'ZipArchive' ) ),
									array( __( 'Image library', 'appstrdesign-foldpress' ), extension_loaded( 'imagick' ) ? 'Imagick' : ( extension_loaded( 'gd' ) ? 'GD' : __( 'Missing', 'appstrdesign-foldpress' ) ), extension_loaded( 'imagick' ) || extension_loaded( 'gd' ) ),
									array( __( 'Uploads directory', 'appstrdesign-foldpress' ), $upload['basedir'], wp_is_writable( $upload['basedir'] ) ),
									array( __( 'Uploads writable', 'appstrdesign-foldpress' ), wp_is_writable( $upload['basedir'] ) ? __( 'Yes', 'appstrdesign-foldpress' ) : __( 'No', 'appstrdesign-foldpress' ), wp_is_writable( $upload['basedir'] ) ),
									array( __( 'Direct file access (physical rename)', 'appstrdesign-foldpress' ), 'direct' === get_filesystem_method() ? __( 'Yes', 'appstrdesign-foldpress' ) : __( 'No', 'appstrdesign-foldpress' ), 'direct' === get_filesystem_method() ),
									array( 'PHP', PHP_VERSION, version_compare( PHP_VERSION, '7.4', '>=' ) ),
								);
								foreach ( $checks as $c ) {
									printf( '<div class="afp-sys__item %4$s"><span data-icon="%3$s"></span><div><small>%1$s</small><strong>%2$s</strong></div></div>', esc_html( $c[0] ), esc_html( $c[1] ), $c[2] ? 'success' : 'alert', $c[2] ? 'is-ok' : 'is-bad' );
								}
								?>
							</div>
						</section>
					</div>
				</div>
				<div class="afp-set__dirty" hidden role="status">
					<span class="afp-set__dirty-ic"><span data-icon="info"></span></span>
					<span class="afp-set__dirty-txt"><strong><?php esc_html_e( 'You have unsaved changes.', 'appstrdesign-foldpress' ); ?></strong><small data-dirty-count></small></span>
					<button type="button" class="afp-btn afp-btn--ghost" data-discard><span data-icon="x"></span><span><?php esc_html_e( 'Cancel', 'appstrdesign-foldpress' ); ?></span></button>
					<button type="submit" class="afp-btn afp-btn--primary"><span data-icon="check"></span><span><?php esc_html_e( 'Save Changes', 'appstrdesign-foldpress' ); ?></span></button>
				</div>
			</form>
		</div>
		<?php
	}
}
