<?php
/**
 * AJAX controller. Every endpoint: nonce + capability + sanitized input.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Ajax
 */
class FoldPress_Ajax {

	const NONCE = 'foldpress_nonce';

	/**
	 * Register endpoints.
	 */
	public function __construct() {
		$actions = array( 'list', 'folders', 'folder_create', 'folder_rename', 'folder_delete', 'folder_move', 'folder_zip', 'create_file', 'history', 'history_restore', 'move', 'rename', 'delete', 'upload', 'zip', 'unzip', 'update_meta' );
		foreach ( $actions as $a ) {
			add_action( 'wp_ajax_foldpress_' . $a, array( $this, 'handle_' . $a ) );
		}
	}

	/**
	 * Common guard.
	 */
	private function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage media.', 'appstrdesign-foldpress' ) ), 403 );
		}
	}

	/**
	 * Sanitized int ids from request.
	 *
	 * @return int[]
	 */
	private function ids() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$raw = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
		return array_values( array_filter( array_map( 'absint', $raw ) ) );
	}

	/**
	 * POST int.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	private function int( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST[ $key ] ) ? (int) wp_unslash( $_POST[ $key ] ) : 0;
	}

	/**
	 * POST text.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private function text( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Validate folder id exists (0 allowed).
	 *
	 * @param int $id Term id.
	 * @return int
	 */
	private function folder_or_fail( $id ) {
		$id = absint( $id );
		if ( $id && ! term_exists( $id, FOLDPRESS_TAX ) ) {
			wp_send_json_error( array( 'message' => __( 'Folder not found.', 'appstrdesign-foldpress' ) ), 404 );
		}
		return $id;
	}

	/**
	 * List attachments.
	 */
	public function handle_list() {
		$this->guard();
		$folder   = $this->int( 'folder' ); // -1 all, 0 unassigned.
		$group    = sanitize_key( $this->text( 'group' ) );
		$search   = $this->text( 'search' );
		$page     = max( 1, $this->int( 'page' ) );
		$per      = min( 200, max( 10, $this->int( 'per_page' ) ) );
		$orderby  = in_array( $this->text( 'orderby' ), array( 'date', 'title', 'modified' ), true ) ? $this->text( 'orderby' ) : 'date';
		$order    = 'ASC' === strtoupper( $this->text( 'order' ) ) ? 'ASC' : 'DESC';

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( $group && 'all' !== $group ) {
			$mimes = FoldPress_Files::group_mimes( $group );
			$args['post_mime_type'] = $mimes ? $mimes : array( 'foldpress/none' );
		}
		if ( 0 === $folder ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$args['tax_query'] = array(
				array(
					'taxonomy' => FOLDPRESS_TAX,
					'operator' => 'NOT EXISTS',
				),
			);
		} elseif ( $folder > 0 ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$args['tax_query'] = array(
				array(
					'taxonomy'         => FOLDPRESS_TAX,
					'terms'            => array( $folder ),
					'include_children' => false,
				),
			);
		}
		$q     = new WP_Query( $args );
		$items = array_map( array( 'FoldPress_Files', 'prepare' ), $q->posts );

		$unassigned = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'      => array(
					array(
						'taxonomy' => FOLDPRESS_TAX,
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);
		$all = wp_count_attachments();
		$sum = 0;
		foreach ( (array) $all as $k => $v ) {
			if ( 'trash' !== $k ) {
				$sum += (int) $v;
			}
		}

		wp_send_json_success(
			array(
				'items'      => $items,
				'total'      => (int) $q->found_posts,
				'pages'      => (int) $q->max_num_pages,
				'folders'    => FoldPress_Files::folders(),
				'countAll'   => $sum,
				'countNone'  => (int) $unassigned->found_posts,
			)
		);
	}

	/**
	 * Folder list.
	 */
	public function handle_folders() {
		$this->guard();
		wp_send_json_success( array( 'folders' => FoldPress_Files::folders() ) );
	}

	/**
	 * Create folder.
	 */
	public function handle_folder_create() {
		$this->guard();
		$name   = $this->text( 'name' );
		$parent = $this->folder_or_fail( $this->int( 'parent' ) );
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Folder name is required.', 'appstrdesign-foldpress' ) ) );
		}
		if ( term_exists( $name, FOLDPRESS_TAX, $parent ) ) {
			wp_send_json_error( array( 'message' => __( 'A folder with this name already exists here.', 'appstrdesign-foldpress' ) ) );
		}
		$id = FoldPress_Files::ensure_folder( $name, $parent );
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'id'      => $id,
				'folders' => FoldPress_Files::folders(),
				'message' => __( 'Folder created.', 'appstrdesign-foldpress' ),
			)
		);
	}

	/**
	 * Rename folder.
	 */
	public function handle_folder_rename() {
		$this->guard();
		$id   = $this->folder_or_fail( $this->int( 'id' ) );
		$name = $this->text( 'name' );
		if ( ! $id || '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'appstrdesign-foldpress' ) ) );
		}
		$res = wp_update_term( $id, FOLDPRESS_TAX, array( 'name' => $name ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'folders' => FoldPress_Files::folders(),
				'message' => __( 'Folder renamed.', 'appstrdesign-foldpress' ),
			)
		);
	}

	/**
	 * Folder ids from request ("ids[]" or a single "id"), validated, without ids nested in other selected ids.
	 *
	 * @return int[]
	 */
	private function folder_ids() {
		$ids = $this->ids();
		if ( ! $ids && $this->int( 'id' ) ) {
			$ids = array( $this->int( 'id' ) );
		}
		$ids = array_values( array_filter( $ids, function ( $id ) {
			return (bool) term_exists( (int) $id, FOLDPRESS_TAX );
		} ) );
		// Drop folders whose ancestor is also selected (handled by the ancestor).
		return array_values( array_filter( $ids, function ( $id ) use ( $ids ) {
			foreach ( $ids as $other ) {
				if ( $other !== $id && FoldPress_Files::is_descendant( $id, $other ) ) {
					return false;
				}
			}
			return true;
		} ) );
	}

	/**
	 * Delete folders with everything inside (sub folders and their files, permanently).
	 */
	public function handle_folder_delete() {
		$this->guard();
		$ids = $this->folder_ids();
		if ( ! $ids ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'appstrdesign-foldpress' ) ) );
		}
		$folders = 0;
		$files   = 0;
		$skipped = 0;
		foreach ( $ids as $id ) {
			$r        = FoldPress_Files::delete_folder_tree( $id );
			$folders += $r['folders'];
			$files   += $r['files'];
			$skipped += $r['skipped'];
		}
		/* translators: 1: folders, 2: files */
		$msg = sprintf( __( '%1$d folders and %2$d files deleted.', 'appstrdesign-foldpress' ), $folders, $files );
		if ( $skipped ) {
			/* translators: %d: files kept */
			$msg .= ' ' . sprintf( __( '%d files could not be deleted (no permission) and were moved out.', 'appstrdesign-foldpress' ), $skipped );
		}
		wp_send_json_success(
			array(
				'folders' => FoldPress_Files::folders(),
				'message' => $msg,
			)
		);
	}

	/**
	 * Move folder(s) under another folder.
	 */
	public function handle_folder_move() {
		$this->guard();
		$ids    = $this->folder_ids();
		$parent = $this->folder_or_fail( $this->int( 'parent' ) );
		$moved  = 0;
		foreach ( $ids as $id ) {
			if ( $parent && FoldPress_Files::is_descendant( $parent, $id ) ) {
				continue;
			}
			if ( ! is_wp_error( wp_update_term( $id, FOLDPRESS_TAX, array( 'parent' => $parent ) ) ) ) {
				++$moved;
			}
		}
		if ( ! $moved ) {
			wp_send_json_error( array( 'message' => __( 'A folder cannot be moved into itself.', 'appstrdesign-foldpress' ) ) );
		}
		wp_send_json_success(
			array(
				'folders' => FoldPress_Files::folders(),
				/* translators: %d: number of folders */
				'message' => sprintf( _n( '%d folder moved.', '%d folders moved.', $moved, 'appstrdesign-foldpress' ), $moved ),
			)
		);
	}

	/**
	 * Compress folder(s) with their sub folders into one ZIP (structure kept).
	 */
	public function handle_folder_zip() {
		$this->guard();
		$ids    = $this->folder_ids();
		$target = $this->folder_or_fail( $this->int( 'folder' ) );
		$res    = FoldPress_Files::zip_folders( $ids, $target, $this->text( 'name' ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Archive created.', 'appstrdesign-foldpress' ) ) );
	}

	/**
	 * Move files to folder.
	 */
	public function handle_move() {
		$this->guard();
		$folder = $this->folder_or_fail( $this->int( 'folder' ) );
		$n      = FoldPress_Files::move( $this->ids(), $folder );
		wp_send_json_success(
			array(
				/* translators: %d: number of files */
				'message' => sprintf( _n( '%d file moved.', '%d files moved.', $n, 'appstrdesign-foldpress' ), $n ),
			)
		);
	}

	/**
	 * Rename file title.
	 */
	public function handle_rename() {
		$this->guard();
		$id   = $this->int( 'id' );
		$name = $this->text( 'name' );
		if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) || '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'appstrdesign-foldpress' ) ), 403 );
		}
		$before = FoldPress_Files::state( $id );
		if ( $this->int( 'physical' ) ) {
			$res = FoldPress_Files::rename_file( $id, $name );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
		}
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => $name,
			)
		);
		FoldPress_Files::log_change( $id, $before, $this->int( 'physical' ) ? 'rename' : 'title' );
		wp_send_json_success(
			array(
				'item'    => FoldPress_Files::prepare( get_post( $id ) ),
				'message' => $this->int( 'physical' ) ? __( 'File renamed. All references in the database were updated.', 'appstrdesign-foldpress' ) : __( 'File renamed.', 'appstrdesign-foldpress' ),
			)
		);
	}

	/**
	 * Update title / alt / caption.
	 */
	public function handle_update_meta() {
		$this->guard();
		$id = $this->int( 'id' );
		if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'appstrdesign-foldpress' ) ), 403 );
		}
		$title = $this->text( 'title' );
		$args  = array( 'ID' => $id );
		if ( '' !== $title ) {
			$args['post_title'] = $title;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		if ( isset( $_POST['caption'] ) ) {
			$args['post_excerpt'] = wp_kses_post( wp_unslash( $_POST['caption'] ) );
		}
		if ( isset( $_POST['description'] ) ) {
			$args['post_content'] = wp_kses_post( wp_unslash( $_POST['description'] ) );
		}
		$has_alt = isset( $_POST['alt'] );
		// phpcs:enable
		$before = FoldPress_Files::state( $id );
		wp_update_post( $args );
		if ( $has_alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $this->text( 'alt' ) );
		}
		FoldPress_Files::log_change( $id, $before, 'details' );
		wp_send_json_success(
			array(
				'item'    => FoldPress_Files::prepare( get_post( $id ) ),
				'message' => __( 'Changes saved.', 'appstrdesign-foldpress' ),
			)
		);
	}

	/**
	 * Permanently delete files.
	 */
	public function handle_delete() {
		$this->guard();
		$n = 0;
		foreach ( $this->ids() as $id ) {
			if ( 'attachment' === get_post_type( $id ) && current_user_can( 'delete_post', $id ) && wp_delete_attachment( $id, true ) ) {
				++$n;
			}
		}
		wp_send_json_success(
			array(
				/* translators: %d: number of files */
				'message' => sprintf( _n( '%d file deleted.', '%d files deleted.', $n, 'appstrdesign-foldpress' ), $n ),
			)
		);
	}

	/**
	 * Upload one file (client sends files one by one).
	 */
	public function handle_upload() {
		$this->guard();
		$folder = $this->folder_or_fail( $this->int( 'folder' ) );
		if ( empty( $_FILES['file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error( array( 'message' => __( 'No file received.', 'appstrdesign-foldpress' ) ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		FoldPress_Settings::$upload_folder = $folder;
		$id = media_handle_upload( 'file', 0 );
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ) );
		}
		if ( $folder ) {
			wp_set_object_terms( $id, array( $folder ), FOLDPRESS_TAX, false );
		}
		wp_send_json_success( array( 'item' => FoldPress_Files::prepare( get_post( $id ) ) ) );
	}

	/**
	 * Allowed upload extensions for the current user.
	 *
	 * @return string[]
	 */
	public static function allowed_ext() {
		$out = array();
		foreach ( array_keys( get_allowed_mime_types() ) as $k ) {
			$out = array_merge( $out, explode( '|', $k ) );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Extensions that can be created as a new empty / template file.
	 *
	 * @return string[]
	 */
	public static function creatable_ext() {
		return array_values( array_intersect( FoldPress_Code::MEDIA_EXT, self::allowed_ext() ) );
	}

	/**
	 * Create a new text file in a folder.
	 */
	public function handle_create_file() {
		$this->guard();
		$folder = $this->folder_or_fail( $this->int( 'folder' ) );
		$name   = sanitize_file_name( $this->text( 'name' ) );
		$ext    = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$stem   = pathinfo( $name, PATHINFO_FILENAME );
		if ( '' === $ext || '' === $stem ) {
			wp_send_json_error( array( 'message' => __( 'Enter a file name with an extension, for example notes.txt.', 'appstrdesign-foldpress' ) ) );
		}
		if ( ! in_array( $ext, self::allowed_ext(), true ) ) {
			/* translators: %s: extension */
			wp_send_json_error( array( 'message' => sprintf( __( 'The .%s extension is not allowed on this site. Enable it in FoldPress > Settings > File Types first.', 'appstrdesign-foldpress' ), $ext ) ) );
		}
		if ( ! in_array( $ext, self::creatable_ext(), true ) ) {
			/* translators: %s: extension */
			wp_send_json_error( array( 'message' => sprintf( __( '.%s files cannot be created empty. Upload an existing file of this type instead.', 'appstrdesign-foldpress' ), $ext ) ) );
		}
		$templates = array(
			'md'   => "# {$stem}\n\n",
			'json' => "{\n\t\n}\n",
			'css'  => "/* {$stem} */\n",
			'scss' => "// {$stem}\n",
			'less' => "// {$stem}\n",
			'js'   => "/* {$stem} */\n",
			'csv'  => '',
			'xml'  => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<root>\n</root>\n",
			'yaml' => "# {$stem}\n",
			'yml'  => "# {$stem}\n",
		);
		$content = isset( $templates[ $ext ] ) ? $templates[ $ext ] : '';
		$tmp     = wp_tempnam( $name );
		file_put_contents( $tmp, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$id = FoldPress_Files::sideload( $tmp, $name, $folder );
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'id'      => (int) $id,
				'editUrl' => FoldPress_Code::url( 'media', (string) $id ),
				'message' => __( 'File created.', 'appstrdesign-foldpress' ),
			)
		);
	}

	/**
	 * Rename history of one file.
	 */
	public function handle_history() {
		$this->guard();
		$id = $this->int( 'id' );
		if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'appstrdesign-foldpress' ) ), 403 );
		}
		wp_send_json_success(
			array(
				'history' => FoldPress_Files::history_for_client( $id ),
				'current' => FoldPress_Files::state( $id ),
			)
		);
	}

	/**
	 * Go back to a version from the history.
	 */
	public function handle_history_restore() {
		$this->guard();
		$id = $this->int( 'id' );
		if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'appstrdesign-foldpress' ) ), 403 );
		}
		$res = FoldPress_Files::restore_history( $id, $this->int( 'index' ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'item'    => FoldPress_Files::prepare( get_post( $id ) ),
				'message' => __( 'Previous version restored. All references were updated.', 'appstrdesign-foldpress' ),
			)
		);
	}

	/**
	 * Zip selected files.
	 */
	public function handle_zip() {
		$this->guard();
		$folder = $this->folder_or_fail( $this->int( 'folder' ) );
		$res    = FoldPress_Files::zip( $this->ids(), $folder, $this->text( 'name' ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Archive created.', 'appstrdesign-foldpress' ) ) );
	}

	/**
	 * Unzip archive.
	 */
	public function handle_unzip() {
		$this->guard();
		$id     = $this->int( 'id' );
		$folder = $this->folder_or_fail( $this->int( 'folder' ) );
		if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'appstrdesign-foldpress' ) ), 403 );
		}
		$res = FoldPress_Files::unzip( $id, $folder );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		/* translators: %d: number of files */
		$msg = sprintf( __( '%d files extracted.', 'appstrdesign-foldpress' ), $res['imported'] );
		if ( $res['skipped'] ) {
			/* translators: %d: number of skipped files */
			$msg .= ' ' . sprintf( __( '%d files skipped (unsupported type).', 'appstrdesign-foldpress' ), count( $res['skipped'] ) );
		}
		wp_send_json_success(
			array(
				'message' => $msg,
				'folder'  => $res['folder'],
				'folders' => FoldPress_Files::folders(),
			)
		);
	}
}
