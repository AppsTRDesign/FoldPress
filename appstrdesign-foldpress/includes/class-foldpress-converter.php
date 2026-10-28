<?php
/**
 * WebP / AVIF conversion with backups and full restore.
 *
 * - New uploads (JPEG/PNG) are converted before the attachment is created; the original is backed up.
 * - Existing media can be scanned and converted in batches; every file (original + sizes) is backed up.
 * - Restore puts every original back, deletes the converted files, rewrites URLs and empties the backup folder.
 * - Files that already are WebP / AVIF are never touched.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Converter
 */
class FoldPress_Converter {

	const META       = '_foldpress_backup';
	const BACKUP_DIR = 'foldpress-backup';
	const SOURCES    = array( 'image/jpeg', 'image/png' );

	/**
	 * Quality presets per format (AVIF uses a different scale than WebP).
	 *
	 * @var array
	 */
	const PRESETS = array(
		'webp' => array(
			'low'    => 60,
			'medium' => 78,
			'high'   => 90,
		),
		'avif' => array(
			'low'    => 40,
			'medium' => 55,
			'high'   => 70,
		),
	);

	/**
	 * Pending backups from wp_handle_upload keyed by new file path.
	 *
	 * @var array
	 */
	private static $pending = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_handle_upload', array( __CLASS__, 'on_upload' ), 20, 2 );
		add_action( 'add_attachment', array( __CLASS__, 'on_attachment' ) );
		add_action( 'delete_attachment', array( __CLASS__, 'on_delete' ) );
		add_action( 'wp_ajax_foldpress_convert_scan', array( __CLASS__, 'ajax_scan' ) );
		add_action( 'wp_ajax_foldpress_convert_batch', array( __CLASS__, 'ajax_convert' ) );
		add_action( 'wp_ajax_foldpress_restore_batch', array( __CLASS__, 'ajax_restore' ) );
		add_action( 'wp_ajax_foldpress_restore_cleanup', array( __CLASS__, 'ajax_cleanup' ) );
	}

	/**
	 * Server support for a format.
	 *
	 * @param string $format webp|avif.
	 * @return bool
	 */
	public static function supports( $format ) {
		if ( ! in_array( $format, array( 'webp', 'avif' ), true ) ) {
			return false;
		}
		require_once ABSPATH . 'wp-includes/class-wp-image-editor.php';
		return (bool) wp_image_editor_supports( array( 'mime_type' => 'image/' . $format ) );
	}

	/**
	 * Active format ('' when off or unsupported).
	 *
	 * @return string
	 */
	public static function format() {
		$f = (string) FoldPress_Settings::get( 'convert_format' );
		return $f && self::supports( $f ) ? $f : '';
	}

	/**
	 * Quality for a mime.
	 *
	 * @param string $mime image/webp|image/avif.
	 * @return int
	 */
	public static function quality( $mime ) {
		$format = str_replace( 'image/', '', $mime );
		$preset = FoldPress_Settings::get( 'convert_quality' );
		if ( 'custom' === $preset ) {
			return (int) FoldPress_Settings::get( 'convert_custom' );
		}
		return isset( self::PRESETS[ $format ][ $preset ] ) ? self::PRESETS[ $format ][ $preset ] : 75;
	}

	/**
	 * Backup base dir / url.
	 *
	 * @return array{dir:string,base:string}
	 */
	private static function backup_root() {
		$u = wp_get_upload_dir();
		return array(
			'dir'  => trailingslashit( $u['basedir'] ) . self::BACKUP_DIR,
			'base' => trailingslashit( $u['basedir'] ),
		);
	}

	/**
	 * Make the backup dir and protect it from direct listing / execution.
	 *
	 * @return string
	 */
	private static function ensure_backup_dir() {
		$root = self::backup_root();
		if ( ! is_dir( $root['dir'] ) ) {
			wp_mkdir_p( $root['dir'] );
		}
		if ( ! file_exists( $root['dir'] . '/index.php' ) ) {
			file_put_contents( $root['dir'] . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $root['dir'] . '/.htaccess' ) ) {
			file_put_contents( $root['dir'] . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\nRequire all denied\n</FilesMatch>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $root['dir'];
	}

	/**
	 * Path relative to uploads basedir.
	 *
	 * @param string $abs Absolute path.
	 * @return string
	 */
	private static function rel( $abs ) {
		$base = self::backup_root()['base'];
		$abs  = wp_normalize_path( $abs );
		$base = wp_normalize_path( $base );
		return 0 === strpos( $abs, $base ) ? substr( $abs, strlen( $base ) ) : ltrim( $abs, '/' );
	}

	/**
	 * Copy a file into the backup tree.
	 *
	 * @param string $abs Absolute source.
	 * @return bool
	 */
	private static function backup_file( $abs ) {
		$dest = self::ensure_backup_dir() . '/' . self::rel( $abs );
		wp_mkdir_p( dirname( $dest ) );
		return file_exists( $abs ) && copy( $abs, $dest );
	}

	/**
	 * Encode one image into the target format.
	 *
	 * @param string $src    Source path.
	 * @param string $format webp|avif.
	 * @return string|WP_Error New path.
	 */
	private static function encode( $src, $format ) {
		$editor = wp_get_image_editor( $src );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		$mime = 'image/' . $format;
		$editor->set_quality( self::quality( $mime ) );
		$dir  = dirname( $src );
		$name = wp_unique_filename( $dir, pathinfo( $src, PATHINFO_FILENAME ) . '.' . $format );
		$res  = $editor->save( $dir . '/' . $name, $mime );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return $res['path'];
	}

	/* ===================== New uploads ===================== */

	/**
	 * Convert JPEG / PNG uploads before the attachment is created.
	 *
	 * @param array  $upload  file, url, type.
	 * @param string $context upload|sideload.
	 * @return array
	 */
	public static function on_upload( $upload, $context = 'upload' ) {
		$format = self::format();
		if ( ! $format || empty( $upload['file'] ) || ! in_array( $upload['type'], self::SOURCES, true ) || ! empty( $upload['error'] ) ) {
			return $upload;
		}
		$src = $upload['file'];
		$new = self::encode( $src, $format );
		if ( is_wp_error( $new ) || ! file_exists( $new ) ) {
			return $upload;
		}
		// Keep the converted file only when it is actually smaller.
		if ( filesize( $new ) >= filesize( $src ) ) {
			wp_delete_file( $new );
			return $upload;
		}
		if ( ! self::backup_file( $src ) ) {
			wp_delete_file( $new );
			return $upload;
		}
		self::$pending[ wp_normalize_path( $new ) ] = array(
			'mode'  => 'upload',
			'file'  => self::rel( $src ),
			'mime'  => $upload['type'],
			'files' => array( self::rel( $src ) ),
			'saved' => filesize( $src ) - filesize( $new ),
		);
		wp_delete_file( $src );
		$upload['file'] = $new;
		$upload['url']  = trailingslashit( dirname( $upload['url'] ) ) . wp_basename( $new );
		$upload['type'] = 'image/' . $format;
		return $upload;
	}

	/**
	 * Attach the pending backup record once the attachment exists.
	 *
	 * @param int $id Attachment id.
	 */
	public static function on_attachment( $id ) {
		if ( ! self::$pending ) {
			return;
		}
		$file = wp_normalize_path( (string) get_attached_file( $id ) );
		if ( isset( self::$pending[ $file ] ) ) {
			update_post_meta( $id, self::META, self::$pending[ $file ] );
			unset( self::$pending[ $file ] );
		}
	}

	/**
	 * Remove backups when an attachment is deleted.
	 *
	 * @param int $id Attachment id.
	 */
	public static function on_delete( $id ) {
		$b = get_post_meta( $id, self::META, true );
		if ( is_array( $b ) && ! empty( $b['files'] ) ) {
			$dir = self::backup_root()['dir'];
			foreach ( $b['files'] as $rel ) {
				wp_delete_file( $dir . '/' . $rel );
			}
		}
	}

	/* ===================== Existing media ===================== */

	/**
	 * All files of an attachment (absolute) + their URLs.
	 *
	 * @param int $id Attachment id.
	 * @return array{files:array,urls:array,meta:array}
	 */
	private static function files_of( $id ) {
		$main = (string) get_attached_file( $id );
		$meta = wp_get_attachment_metadata( $id );
		$meta = is_array( $meta ) ? $meta : array();
		$dir  = dirname( $main );
		$url  = trailingslashit( dirname( (string) wp_get_attachment_url( $id ) ) );
		$out  = array(
			'files' => array( 'full' => $main ),
			'urls'  => array( 'full' => $url . wp_basename( $main ) ),
			'meta'  => $meta,
		);
		if ( ! empty( $meta['original_image'] ) ) {
			$out['files']['original'] = $dir . '/' . $meta['original_image'];
			$out['urls']['original']  = $url . $meta['original_image'];
		}
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $name => $s ) {
				if ( ! empty( $s['file'] ) ) {
					$out['files'][ 'size:' . $name ] = $dir . '/' . $s['file'];
					$out['urls'][ 'size:' . $name ]  = $url . $s['file'];
				}
			}
		}
		return $out;
	}

	/**
	 * Convert one existing attachment.
	 *
	 * @param int    $id     Attachment id.
	 * @param string $format webp|avif.
	 * @return int|WP_Error Bytes saved.
	 */
	public static function convert( $id, $format ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$mime = get_post_mime_type( $id );
		if ( ! in_array( $mime, self::SOURCES, true ) || get_post_meta( $id, self::META, true ) ) {
			return 0;
		}
		$old = self::files_of( $id );
		$src = isset( $old['files']['original'] ) && file_exists( $old['files']['original'] ) ? $old['files']['original'] : $old['files']['full'];
		if ( ! file_exists( $src ) ) {
			return new WP_Error( 'missing', __( 'File not found on disk.', 'appstrdesign-foldpress' ) );
		}
		$before = 0;
		foreach ( $old['files'] as $f ) {
			$before += file_exists( $f ) ? filesize( $f ) : 0;
		}

		// 1) Backup every file.
		$backed = array();
		foreach ( array_unique( $old['files'] ) as $f ) {
			if ( file_exists( $f ) ) {
				if ( ! self::backup_file( $f ) ) {
					return new WP_Error( 'backup', __( 'Backup could not be written. Check that the uploads folder is writable.', 'appstrdesign-foldpress' ) );
				}
				$backed[] = self::rel( $f );
			}
		}

		// 2) Encode new master.
		$new = self::encode( $src, $format );
		if ( is_wp_error( $new ) ) {
			return $new;
		}

		// 3) Remove old files, switch attachment to the new file, regenerate sizes.
		foreach ( array_unique( $old['files'] ) as $f ) {
			if ( file_exists( $f ) ) {
				wp_delete_file( $f );
			}
		}
		update_attached_file( $id, $new );
		wp_update_post(
			array(
				'ID'             => $id,
				'post_mime_type' => 'image/' . $format,
			)
		);
		$meta = wp_generate_attachment_metadata( $id, $new );
		wp_update_attachment_metadata( $id, $meta );

		update_post_meta(
			$id,
			self::META,
			array(
				'mode'  => 'existing',
				'file'  => self::rel( $old['files']['full'] ),
				'mime'  => $mime,
				'meta'  => $old['meta'],
				'files' => $backed,
			)
		);

		// 4) Rewrite URLs everywhere: same size name when it exists, otherwise the full image.
		$now = self::files_of( $id );
		$map = array();
		foreach ( $old['urls'] as $k => $u ) {
			$map[ $u ] = isset( $now['urls'][ $k ] ) ? $now['urls'][ $k ] : $now['urls']['full'];
		}
		FoldPress_Replacer::run( $map );

		$after = 0;
		foreach ( $now['files'] as $f ) {
			$after += file_exists( $f ) ? filesize( $f ) : 0;
		}
		return max( 0, $before - $after );
	}

	/**
	 * Restore one converted attachment from its backup.
	 *
	 * @param int $id Attachment id.
	 * @return true|WP_Error
	 */
	public static function restore( $id ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$b = get_post_meta( $id, self::META, true );
		if ( ! is_array( $b ) || empty( $b['file'] ) ) {
			return new WP_Error( 'nobackup', __( 'No backup found for this file.', 'appstrdesign-foldpress' ) );
		}
		$root = self::backup_root();
		foreach ( (array) $b['files'] as $rel ) {
			if ( ! file_exists( $root['dir'] . '/' . $rel ) ) {
				return new WP_Error( 'nobackup', __( 'Backup file is missing.', 'appstrdesign-foldpress' ) );
			}
		}
		$now = self::files_of( $id );

		// Put originals back.
		foreach ( (array) $b['files'] as $rel ) {
			$dest = $root['base'] . $rel;
			wp_mkdir_p( dirname( $dest ) );
			if ( ! copy( $root['dir'] . '/' . $rel, $dest ) ) {
				return new WP_Error( 'restore', __( 'Could not restore the original file.', 'appstrdesign-foldpress' ) );
			}
		}
		// Delete converted files.
		foreach ( array_unique( $now['files'] ) as $f ) {
			if ( file_exists( $f ) && ! in_array( self::rel( $f ), (array) $b['files'], true ) ) {
				wp_delete_file( $f );
			}
		}
		update_attached_file( $id, $root['base'] . $b['file'] );
		wp_update_post(
			array(
				'ID'             => $id,
				'post_mime_type' => $b['mime'],
			)
		);
		if ( ! empty( $b['meta'] ) ) {
			wp_update_attachment_metadata( $id, $b['meta'] );
		} else {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $root['base'] . $b['file'] ) );
		}
		$old = self::files_of( $id );
		$map = array();
		foreach ( $now['urls'] as $k => $u ) {
			$map[ $u ] = isset( $old['urls'][ $k ] ) ? $old['urls'][ $k ] : $old['urls']['full'];
		}
		FoldPress_Replacer::run( $map );

		foreach ( (array) $b['files'] as $rel ) {
			wp_delete_file( $root['dir'] . '/' . $rel );
		}
		delete_post_meta( $id, self::META );
		return true;
	}

	/**
	 * Remove the backup folder when no backups remain.
	 *
	 * @return bool
	 */
	public static function cleanup() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META ) );
		if ( $left ) {
			return false;
		}
		$dir = self::backup_root()['dir'];
		if ( ! is_dir( $dir ) ) {
			return true;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		if ( WP_Filesystem() && $wp_filesystem ) {
			return (bool) $wp_filesystem->delete( $dir, true );
		}
		return false;
	}

	/* ===================== AJAX ===================== */

	/**
	 * Guard.
	 */
	private static function guard() {
		check_ajax_referer( FoldPress_Ajax::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change these settings.', 'appstrdesign-foldpress' ) ), 403 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/**
	 * Scan the library.
	 */
	public static function ajax_scan() {
		self::guard();
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT post_mime_type AS m, COUNT(*) AS c FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' GROUP BY post_mime_type" );
		$conv = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META ) );
		$ids  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/jpeg','image/png') AND m.meta_id IS NULL ORDER BY p.ID ASC",
				self::META
			)
		);
		// phpcs:enable
		$total  = 0;
		$modern = 0;
		foreach ( (array) $rows as $r ) {
			$total += (int) $r->c;
			if ( in_array( $r->m, array( 'image/webp', 'image/avif' ), true ) ) {
				$modern += (int) $r->c;
			}
		}
		$root = self::backup_root();
		wp_send_json_success(
			array(
				'total'     => $total,
				'pending'   => count( $ids ),
				'modern'    => max( 0, $modern - count( $conv ) ),
				'converted' => count( $conv ),
				'ids'       => array_map( 'intval', $ids ),
				'restore'   => array_map( 'intval', $conv ),
				'backupDir' => is_dir( $root['dir'] ),
				'format'    => self::format(),
			)
		);
	}

	/**
	 * Ids from request.
	 *
	 * @return int[]
	 */
	private static function ids() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$raw = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
		return array_slice( array_values( array_filter( array_map( 'absint', $raw ) ) ), 0, 10 );
	}

	/**
	 * Convert a batch.
	 */
	public static function ajax_convert() {
		self::guard();
		$format = self::format();
		if ( ! $format ) {
			wp_send_json_error( array( 'message' => __( 'Choose WebP or AVIF and save the settings first.', 'appstrdesign-foldpress' ) ) );
		}
		$done   = 0;
		$saved  = 0;
		$errors = array();
		foreach ( self::ids() as $id ) {
			$r = self::convert( $id, $format );
			if ( is_wp_error( $r ) ) {
				$errors[] = get_the_title( $id ) . ': ' . $r->get_error_message();
			} else {
				++$done;
				$saved += (int) $r;
			}
		}
		wp_send_json_success( compact( 'done', 'saved', 'errors' ) );
	}

	/**
	 * Restore a batch.
	 */
	public static function ajax_restore() {
		self::guard();
		$done   = 0;
		$errors = array();
		foreach ( self::ids() as $id ) {
			$r = self::restore( $id );
			if ( is_wp_error( $r ) ) {
				$errors[] = get_the_title( $id ) . ': ' . $r->get_error_message();
			} else {
				++$done;
			}
		}
		wp_send_json_success( compact( 'done', 'errors' ) );
	}

	/**
	 * Delete the backup folder after a full restore.
	 */
	public static function ajax_cleanup() {
		self::guard();
		$ok = self::cleanup();
		wp_send_json_success(
			array(
				'cleaned' => $ok,
				'message' => $ok ? __( 'Originals restored and the backup folder was removed.', 'appstrdesign-foldpress' ) : __( 'Some backups are still in use, the backup folder was kept.', 'appstrdesign-foldpress' ),
			)
		);
	}
}
