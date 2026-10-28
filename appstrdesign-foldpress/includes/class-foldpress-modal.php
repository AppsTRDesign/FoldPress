<?php
/**
 * Integration with the wp.media modal (post editor "Add Media", featured image, blocks).
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Modal
 */
class FoldPress_Modal {

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_media', array( $this, 'assets' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_query' ) );
		add_action( 'add_attachment', array( $this, 'assign_uploaded' ) );
	}

	/**
	 * Enqueue modal extension wherever wp.media is loaded.
	 */
	public function assets() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		if ( FoldPress_Settings::get( 'modal_theme' ) ) {
			wp_enqueue_style( 'foldpress-modal-theme', FOLDPRESS_URL . 'assets/css/foldpress-modal-theme.css', array(), FOLDPRESS_VERSION );
		}
		if ( ! FoldPress_Settings::get( 'modal_integration' ) ) {
			return;
		}
		// Shared FoldPress UI (icons, previews, toasts) so the modal gets the same viewer as the manager.
		FoldPress_Admin::enqueue_ui();
		wp_enqueue_style( 'foldpress-modal', FOLDPRESS_URL . 'assets/css/foldpress-modal.css', array( 'foldpress' ), FOLDPRESS_VERSION );
		wp_enqueue_script( 'foldpress-modal', FOLDPRESS_URL . 'assets/js/foldpress-modal.js', array( 'media-views', 'foldpress-ui' ), FOLDPRESS_VERSION, true );
		$cloud = FoldPress_Cloud::client_config();
		wp_localize_script(
			'foldpress-modal',
			'FoldPressModal',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( FoldPress_Ajax::NONCE ),
				'manageUrl' => FoldPress_Admin::url(),
				'urlImport' => ! empty( $cloud['url'] ),
				'i18n'      => array(
					'folders'    => __( 'Folders', 'appstrdesign-foldpress' ),
					'allFiles'   => __( 'All Files', 'appstrdesign-foldpress' ),
					'unassigned' => __( 'Uncategorized', 'appstrdesign-foldpress' ),
					'newFolder'  => __( 'New Folder', 'appstrdesign-foldpress' ),
					'folderName' => __( 'Folder name', 'appstrdesign-foldpress' ),
					'filter'     => __( 'Filter by folder', 'appstrdesign-foldpress' ),
					'manage'     => __( 'Manage folders', 'appstrdesign-foldpress' ),
					'error'      => __( 'Error', 'appstrdesign-foldpress' ),
					'searchFolders' => __( 'Search folders...', 'appstrdesign-foldpress' ),
					'noFolders'  => __( 'No folders found.', 'appstrdesign-foldpress' ),
					'uploadsTo'  => __( 'New uploads go to', 'appstrdesign-foldpress' ),
					'toggle'     => __( 'Show / hide sub folders', 'appstrdesign-foldpress' ),
					'preview'    => __( 'Preview', 'appstrdesign-foldpress' ),
					'edit'       => __( 'Advanced edit', 'appstrdesign-foldpress' ),
					'copyUrl'    => __( 'Copy URL', 'appstrdesign-foldpress' ),
					'moved'      => __( 'Moved to folder.', 'appstrdesign-foldpress' ),
					'created'    => __( 'Folder created.', 'appstrdesign-foldpress' ),
					'urlHint'    => __( 'Paste a link to embed it (YouTube, Vimeo, X, Spotify, SoundCloud...) or to insert an image or file by its address.', 'appstrdesign-foldpress' ),
					'urlImport'  => __( 'Download to media library', 'appstrdesign-foldpress' ),
					'urlImportHint' => __( 'Copies the file to your site so it does not depend on the other server.', 'appstrdesign-foldpress' ),
					'importing'  => __( 'Downloading to the media library...', 'appstrdesign-foldpress' ),
					'imported'   => __( 'File added to the media library.', 'appstrdesign-foldpress' ),
					'showInLib'  => __( 'Show in library', 'appstrdesign-foldpress' ),
					'invalidUrl' => __( 'Enter a valid http(s) address first.', 'appstrdesign-foldpress' ),
					'processing' => __( 'Adding to the library...', 'appstrdesign-foldpress' ),
					'cancel'     => __( 'Cancel', 'appstrdesign-foldpress' ),
					/* translators: %s: remaining time */
					'left'       => __( '%s left', 'appstrdesign-foldpress' ),
				),
			)
		);
	}

	/**
	 * Requested folder from the query-attachments request (-1 = not filtered).
	 *
	 * @return int
	 */
	private function requested_folder() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter; core checks upload_files.
		$query = isset( $_REQUEST['query'] ) && is_array( $_REQUEST['query'] ) ? wp_unslash( $_REQUEST['query'] ) : array();
		return isset( $query['foldpress_folder'] ) ? (int) $query['foldpress_folder'] : -1;
	}

	/**
	 * Apply folder filter to the media modal query.
	 *
	 * @param array $args WP_Query args.
	 * @return array
	 */
	public function filter_query( $args ) {
		$folder = $this->requested_folder();
		if ( $folder < 0 ) {
			return $args;
		}
		$clause = 0 === $folder
			? array(
				'taxonomy' => FOLDPRESS_TAX,
				'operator' => 'NOT EXISTS',
			)
			: array(
				'taxonomy'         => FOLDPRESS_TAX,
				'terms'            => array( $folder ),
				'include_children' => false,
			);
		$tax   = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array();
		$tax[] = $clause;
		$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		return $args;
	}

	/**
	 * Put files uploaded from the modal into the folder being viewed.
	 * Core verifies the upload nonce before the attachment is inserted.
	 *
	 * @param int $id Attachment id.
	 */
	public function assign_uploaded( $id ) {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! wp_doing_ajax() || ! isset( $_REQUEST['action'], $_REQUEST['foldpress_folder'] ) || 'upload-attachment' !== $_REQUEST['action'] ) {
			return;
		}
		$folder = absint( wp_unslash( $_REQUEST['foldpress_folder'] ) );
		// phpcs:enable
		if ( $folder && current_user_can( 'upload_files' ) && term_exists( $folder, FOLDPRESS_TAX ) ) {
			wp_set_object_terms( $id, array( $folder ), FOLDPRESS_TAX, false );
		}
	}
}
