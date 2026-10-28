<?php
/**
 * FoldPress attachment editor (replaces post.php?action=edit for attachments).
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Editor
 */
class FoldPress_Editor {

	const SLUG = 'foldpress-edit';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 100 );
		add_filter( 'parent_file', array( $this, 'parent_file' ) );
		add_filter( 'submenu_file', array( $this, 'submenu_file' ) );
		add_action( 'load-post.php', array( $this, 'redirect' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_foldpress_item', array( $this, 'ajax_item' ) );
		add_action( 'wp_ajax_foldpress_save_item', array( $this, 'ajax_save' ) );
	}

	/**
	 * Editor URL (falls back to core when the editor is disabled).
	 *
	 * @param int $id Attachment id.
	 * @return string
	 */
	public static function url( $id ) {
		if ( ! FoldPress_Settings::get( 'replace_editor' ) ) {
			return (string) get_edit_post_link( $id, 'raw' );
		}
		return add_query_arg(
			array(
				'page' => self::SLUG,
				'id'   => (int) $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Hidden page. Registered under options.php so it gets no menu item but stays accessible
	 * (removing a submenu entry would make WordPress refuse access to the page).
	 */
	public function menu() {
		add_submenu_page( 'options.php', __( 'Edit Media', 'appstrdesign-foldpress' ), __( 'Edit Media', 'appstrdesign-foldpress' ), 'upload_files', self::SLUG, array( $this, 'render' ) );
	}

	/**
	 * Is the editor screen.
	 *
	 * @return bool
	 */
	private function is_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return is_admin() && isset( $_GET['page'] ) && self::SLUG === $_GET['page'];
	}

	/**
	 * Keep "Media" highlighted in the admin menu.
	 *
	 * @param string $file Parent file.
	 * @return string
	 */
	public function parent_file( $file ) {
		return $this->is_screen() ? 'upload.php' : $file;
	}

	/**
	 * Keep "Library" highlighted.
	 *
	 * @param string|null $file Submenu file.
	 * @return string|null
	 */
	public function submenu_file( $file ) {
		return $this->is_screen() ? FoldPress_Admin::SLUG : $file;
	}

	/**
	 * Redirect core attachment edit screen.
	 */
	public function redirect() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- routing only.
		if ( ! FoldPress_Settings::get( 'replace_editor' ) || isset( $_GET['foldpress_classic'] ) || isset( $_GET['image-editor'] ) ) {
			return;
		}
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( $id && isset( $_GET['action'] ) && 'edit' === $_GET['action'] && 'attachment' === get_post_type( $id ) ) {
			wp_safe_redirect( self::url( $id ) );
			exit;
		}
		// phpcs:enable
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( $hook ) {
		if ( 'admin_page_' . self::SLUG !== $hook ) {
			return;
		}
		FoldPress_Admin::enqueue_ui();
		wp_enqueue_script( 'foldpress-editor', FOLDPRESS_URL . 'assets/js/foldpress-editor.js', array( 'foldpress-ui' ), FOLDPRESS_VERSION, true );
		wp_localize_script(
			'foldpress-editor',
			'FoldPressEditor',
			array(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'id'         => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0,
				'libraryUrl' => FoldPress_Admin::url(),
				'i18n'       => array(
					'back'          => __( 'Back to library', 'appstrdesign-foldpress' ),
					'save'          => __( 'Save Changes', 'appstrdesign-foldpress' ),
					'saving'        => __( 'Saving...', 'appstrdesign-foldpress' ),
					'unsaved'       => __( 'Unsaved changes', 'appstrdesign-foldpress' ),
					'general'       => __( 'General', 'appstrdesign-foldpress' ),
					'title'         => __( 'Title', 'appstrdesign-foldpress' ),
					'fileName'      => __( 'File name', 'appstrdesign-foldpress' ),
					'fileNameHint'  => __( 'Changing the file name renames every image size on the server and updates all references in the database: posts, pages, Elementor, WooCommerce, widgets, options and other plugins.', 'appstrdesign-foldpress' ),
					'alt'           => __( 'Alternative text', 'appstrdesign-foldpress' ),
					'altHint'       => __( 'Describe the image for screen readers and search engines.', 'appstrdesign-foldpress' ),
					'caption'       => __( 'Caption', 'appstrdesign-foldpress' ),
					'description'   => __( 'Description', 'appstrdesign-foldpress' ),
					'folder'        => __( 'Folder', 'appstrdesign-foldpress' ),
					'info'          => __( 'File information', 'appstrdesign-foldpress' ),
					'sizes'         => __( 'Generated sizes', 'appstrdesign-foldpress' ),
					'usage'         => __( 'Where it is used', 'appstrdesign-foldpress' ),
					'notUsed'       => __( 'Not used anywhere: no post, page, product, user, term or setting references this file.', 'appstrdesign-foldpress' ),
					'usageHint'     => __( 'Scanned by media ID and file URL in content, custom fields, users, categories and site settings.', 'appstrdesign-foldpress' ),
					'groups'        => array(
						'content' => __( 'Posts, pages and products', 'appstrdesign-foldpress' ),
						'users'   => __( 'Users', 'appstrdesign-foldpress' ),
						'terms'   => __( 'Categories and terms', 'appstrdesign-foldpress' ),
						'options' => __( 'Site settings', 'appstrdesign-foldpress' ),
					),
					'edit'          => __( 'Edit', 'appstrdesign-foldpress' ),
					'all'           => __( 'All', 'appstrdesign-foldpress' ),
					'places'        => __( 'places', 'appstrdesign-foldpress' ),
					'notUsedTitle'  => __( 'Safe to change', 'appstrdesign-foldpress' ),
					'view'          => __( 'View', 'appstrdesign-foldpress' ),
					'cancel'        => __( 'Cancel', 'appstrdesign-foldpress' ),
					'discardTitle'  => __( 'Discard unsaved changes?', 'appstrdesign-foldpress' ),
					'discardBody'   => __( 'These changes have not been saved and will be lost:', 'appstrdesign-foldpress' ),
					'discard'       => __( 'Discard changes', 'appstrdesign-foldpress' ),
					'discarded'     => __( 'Changes discarded.', 'appstrdesign-foldpress' ),
					'attachedTo'    => __( 'Attached to', 'appstrdesign-foldpress' ),
					'uploadedBy'    => __( 'Uploaded by', 'appstrdesign-foldpress' ),
					'uploadedOn'    => __( 'Uploaded on', 'appstrdesign-foldpress' ),
					'imageEditor'   => __( 'Crop & rotate', 'appstrdesign-foldpress' ),
					'preview'       => __( 'Preview', 'appstrdesign-foldpress' ),
					'dangerZone'    => __( 'Danger zone', 'appstrdesign-foldpress' ),
					'deleteHint'    => __( 'Deletes the file and all of its sizes permanently.', 'appstrdesign-foldpress' ),
					'renameTitle'   => __( 'Rename the file on the server?', 'appstrdesign-foldpress' ),
					'renameBody'    => __( 'All image sizes will be renamed and every reference in the database will be updated automatically. Take a backup first on large sites.', 'appstrdesign-foldpress' ),
					'renameConfirm' => __( 'Rename and update', 'appstrdesign-foldpress' ),
					'leave'         => __( 'You have unsaved changes. Leave anyway?', 'appstrdesign-foldpress' ),
					'notFound'      => __( 'Media item not found.', 'appstrdesign-foldpress' ),
					'how'           => array(
						'featured' => __( 'Featured image', 'appstrdesign-foldpress' ),
						'gallery'  => __( 'Product gallery', 'appstrdesign-foldpress' ),
						'content'  => __( 'In content', 'appstrdesign-foldpress' ),
						'builder'  => __( 'Page builder', 'appstrdesign-foldpress' ),
					),
				),
			)
		);
	}

	/**
	 * Shell.
	 */
	public function render() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage media.', 'appstrdesign-foldpress' ) );
		}
		echo '<div class="wrap foldpress-wrap"><h1 class="screen-reader-text">' . esc_html__( 'Edit Media', 'appstrdesign-foldpress' ) . '</h1><div id="foldpress-editor" class="afp-app afp-ed"><div class="afp-boot"><span class="afp-spin"></span></div></div></div>';
	}

	/**
	 * Guard + attachment.
	 *
	 * @param string $cap Capability on the post.
	 * @return WP_Post
	 */
	private function attachment( $cap ) {
		check_ajax_referer( FoldPress_Ajax::NONCE, 'nonce' );
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type || ! current_user_can( $cap, $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Media item not found.', 'appstrdesign-foldpress' ) ), 404 );
		}
		return $post;
	}

	/**
	 * Full item payload.
	 *
	 * @param WP_Post $post Attachment.
	 * @return array
	 */
	private function payload( $post ) {
		$item          = FoldPress_Files::prepare( $post );
		$meta          = wp_get_attachment_metadata( $post->ID );
		$item['full']  = wp_get_attachment_url( $post->ID );
		$item['stem']  = pathinfo( $item['filename'], PATHINFO_FILENAME );
		$item['caption']     = $post->post_excerpt;
		$item['description'] = $post->post_content;
		$item['author']      = get_the_author_meta( 'display_name', $post->post_author );
		$item['dateFull']    = get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post );
		$item['imageEditor'] = 0 === strpos( $item['mime'], 'image/' ) && wp_image_editor_supports( array( 'mime_type' => $item['mime'] ) )
			? add_query_arg( array( 'post' => $post->ID, 'action' => 'edit', 'foldpress_classic' => 1, 'image-editor' => 1 ), admin_url( 'post.php' ) )
			: '';
		$item['parent'] = $post->post_parent ? array(
			'title' => get_the_title( $post->post_parent ),
			'url'   => (string) get_edit_post_link( $post->post_parent, 'raw' ),
		) : null;

		$item['sizes'] = array();
		if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
			$base = trailingslashit( dirname( $item['full'] ) );
			foreach ( $meta['sizes'] as $name => $s ) {
				$item['sizes'][] = array(
					'name' => $name,
					'w'    => (int) $s['width'],
					'h'    => (int) $s['height'],
					'url'  => $base . $s['file'],
				);
			}
		}
		$item['usage']   = FoldPress_Usage::find( $post->ID );
		$item['folders'] = FoldPress_Files::folders();
		return $item;
	}

	/**
	 * Get item.
	 */
	public function ajax_item() {
		$post = $this->attachment( 'edit_post' );
		wp_send_json_success( array( 'item' => $this->payload( $post ) ) );
	}

	/**
	 * Save item.
	 */
	public function ajax_save() {
		$post = $this->attachment( 'edit_post' );
		$id   = $post->ID;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in attachment().
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $post->post_title;
		$caption = isset( $_POST['caption'] ) ? wp_kses_post( wp_unslash( $_POST['caption'] ) ) : $post->post_excerpt;
		$desc    = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : $post->post_content;
		$alt     = isset( $_POST['alt'] ) ? sanitize_text_field( wp_unslash( $_POST['alt'] ) ) : null;
		$folder  = isset( $_POST['folder'] ) ? absint( $_POST['folder'] ) : null;
		$stem    = isset( $_POST['stem'] ) ? sanitize_file_name( wp_unslash( $_POST['stem'] ) ) : '';
		// phpcs:enable

		$before = FoldPress_Files::state( $id );
		$msg = __( 'Changes saved.', 'appstrdesign-foldpress' );
		if ( $stem && pathinfo( wp_basename( (string) get_attached_file( $id ) ), PATHINFO_FILENAME ) !== $stem ) {
			$res = FoldPress_Files::rename_file( $id, $stem );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			/* translators: %d: number of updated database rows */
			$msg = sprintf( __( 'Changes saved. File renamed and %d database references updated.', 'appstrdesign-foldpress' ), (int) $res );
		}

		wp_update_post(
			array(
				'ID'           => $id,
				'post_title'   => $title,
				'post_excerpt' => $caption,
				'post_content' => $desc,
			)
		);
		if ( null !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}
		if ( null !== $folder && ( 0 === $folder || term_exists( $folder, FOLDPRESS_TAX ) ) ) {
			FoldPress_Files::move( array( $id ), $folder );
		}
		FoldPress_Files::log_change( $id, $before, 'editor' );
		wp_send_json_success(
			array(
				'message' => $msg,
				'item'    => $this->payload( get_post( $id ) ),
			)
		);
	}
}
