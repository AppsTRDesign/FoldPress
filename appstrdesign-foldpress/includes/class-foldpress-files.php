<?php
/**
 * File / folder service layer.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Files
 */
class FoldPress_Files {

	const MAX_UNZIP_ENTRIES = 500;
	const MAX_UNZIP_BYTES   = 536870912; // 512 MB uncompressed.

	/**
	 * Map a mime type to a file group.
	 *
	 * @param string $mime Mime type.
	 * @param string $ext  Extension.
	 * @return string
	 */
	public static function group( $mime, $ext = '' ) {
		$ext = strtolower( (string) $ext );
		if ( 0 === strpos( $mime, 'image/' ) ) {
			return 'image';
		}
		if ( 0 === strpos( $mime, 'video/' ) ) {
			return 'video';
		}
		if ( 0 === strpos( $mime, 'audio/' ) ) {
			return 'audio';
		}
		$map = array(
			'pdf'          => array( 'pdf' ),
			'archive'      => array( 'zip', 'rar', '7z', 'gz', 'tar', 'tgz', 'bz2' ),
			'spreadsheet'  => array( 'xls', 'xlsx', 'xlsm', 'ods', 'csv', 'numbers' ),
			'presentation' => array( 'ppt', 'pptx', 'pps', 'ppsx', 'odp', 'key' ),
			'document'     => array( 'doc', 'docx', 'odt', 'rtf', 'txt', 'pages', 'md' ),
			'code'         => array( 'json', 'xml', 'css', 'js', 'html', 'htm', 'php', 'svg' ),
			'font'         => array( 'ttf', 'otf', 'woff', 'woff2', 'eot' ),
		);
		foreach ( $map as $group => $exts ) {
			if ( in_array( $ext, $exts, true ) ) {
				return $group;
			}
		}
		return 'other';
	}

	/**
	 * Group => mime LIKE patterns / extensions used for filtering.
	 *
	 * @param string $group Group key.
	 * @return array
	 */
	public static function group_mimes( $group ) {
		$all = get_allowed_mime_types();
		$out = array();
		foreach ( $all as $exts => $mime ) {
			$first = explode( '|', $exts );
			if ( self::group( $mime, $first[0] ) === $group ) {
				$out[] = $mime;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Format an attachment for the client.
	 *
	 * @param WP_Post $post Attachment.
	 * @return array
	 */
	public static function prepare( $post ) {
		$file  = get_attached_file( $post->ID );
		$name  = $file ? wp_basename( $file ) : '';
		$ext   = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$mime  = (string) $post->post_mime_type;
		$thumb = '';
		if ( 0 === strpos( $mime, 'image/' ) ) {
			$src   = wp_get_attachment_image_src( $post->ID, 'medium' );
			$thumb = $src ? $src[0] : wp_get_attachment_url( $post->ID );
		}
		$terms  = wp_get_object_terms( $post->ID, FOLDPRESS_TAX, array( 'fields' => 'ids' ) );
		$meta   = wp_get_attachment_metadata( $post->ID );
		$size   = ( $file && file_exists( $file ) ) ? filesize( $file ) : 0;
		$dims   = ( is_array( $meta ) && ! empty( $meta['width'] ) ) ? (int) $meta['width'] . ' x ' . (int) $meta['height'] : '';

		return array(
			'id'       => (int) $post->ID,
			'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'filename' => $name,
			'ext'      => $ext,
			'mime'     => $mime,
			'group'    => self::group( $mime, $ext ),
			'url'      => wp_get_attachment_url( $post->ID ),
			'thumb'    => $thumb,
			'size'     => (int) $size,
			'sizeH'    => size_format( $size ),
			'dims'     => $dims,
			'date'     => get_the_date( get_option( 'date_format' ), $post ),
			'alt'      => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'folder'   => ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0] : 0,
			'editUrl'  => FoldPress_Editor::url( $post->ID ),
			'caption'  => (string) $post->post_excerpt,
			'desc'     => (string) $post->post_content,
			'dateFull' => get_the_date( get_option( 'date_format' ), $post ),
			'author'   => get_the_author_meta( 'display_name', $post->post_author ),
			'authorUrl' => current_user_can( 'edit_user', $post->post_author ) ? get_edit_user_link( $post->post_author ) : '',
			'parent'   => $post->post_parent ? array(
				'title' => html_entity_decode( get_the_title( $post->post_parent ), ENT_QUOTES, 'UTF-8' ),
				'url'   => current_user_can( 'edit_post', $post->post_parent ) ? (string) get_edit_post_link( $post->post_parent, 'raw' ) : (string) get_permalink( $post->post_parent ),
			) : null,
			'viewUrl'  => get_attachment_link( $post->ID ),
			'renamed'  => count( self::history( $post->ID ) ),
		);
	}

	const HISTORY = '_foldpress_rename_history';

	/**
	 * Current name state of an attachment.
	 *
	 * @param int $id Attachment id.
	 * @return array{title:string,stem:string,ext:string}
	 */
	public static function state( $id ) {
		$file = wp_basename( (string) get_attached_file( $id ) );
		return array(
			'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
			'stem'  => pathinfo( $file, PATHINFO_FILENAME ),
			'ext'   => strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ),
		);
	}

	/**
	 * Rename history (newest first).
	 *
	 * @param int $id Attachment id.
	 * @return array
	 */
	public static function history( $id ) {
		$h = get_post_meta( $id, self::HISTORY, true );
		return is_array( $h ) ? $h : array();
	}

	/**
	 * Record a change when title or physical file name differs from $before.
	 *
	 * @param int    $id     Attachment id.
	 * @param array  $before State before the change.
	 * @param string $via    rename|editor|restore|details.
	 */
	public static function log_change( $id, $before, $via = 'rename' ) {
		$after = self::state( $id );
		if ( $after['title'] === $before['title'] && $after['stem'] === $before['stem'] ) {
			return;
		}
		$h = self::history( $id );
		array_unshift(
			$h,
			array(
				'time'      => time(),
				'user'      => get_current_user_id(),
				'via'       => $via,
				'old_title' => $before['title'],
				'new_title' => $after['title'],
				'old_stem'  => $before['stem'],
				'new_stem'  => $after['stem'],
				'ext'       => $after['ext'],
			)
		);
		update_post_meta( $id, self::HISTORY, array_slice( $h, 0, 50 ) );
	}

	/**
	 * History prepared for the client.
	 *
	 * @param int $id Attachment id.
	 * @return array
	 */
	public static function history_for_client( $id ) {
		$out = array();
		foreach ( self::history( $id ) as $i => $e ) {
			$u     = get_userdata( (int) $e['user'] );
			$out[] = array(
				'index'    => $i,
				'date'     => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $e['time'] ),
				'ago'      => human_time_diff( (int) $e['time'] ),
				'user'     => $u ? $u->display_name : '-',
				'via'      => $e['via'],
				'oldTitle' => $e['old_title'],
				'newTitle' => $e['new_title'],
				'oldFile'  => $e['old_stem'] . '.' . $e['ext'],
				'newFile'  => $e['new_stem'] . '.' . $e['ext'],
				'physical' => $e['old_stem'] !== $e['new_stem'],
			);
		}
		return $out;
	}

	/**
	 * Go back to the state before history entry $index (title and, when needed, physical name + DB links).
	 *
	 * @param int $id    Attachment id.
	 * @param int $index Entry index.
	 * @return true|WP_Error
	 */
	public static function restore_history( $id, $index ) {
		$h = self::history( $id );
		if ( ! isset( $h[ $index ] ) ) {
			return new WP_Error( 'nohistory', __( 'This version no longer exists.', 'appstrdesign-foldpress' ) );
		}
		$target = $h[ $index ];
		$before = self::state( $id );
		if ( $target['old_stem'] !== $before['stem'] ) {
			$res = self::rename_file( $id, $target['old_stem'] );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}
		if ( $target['old_title'] !== $before['title'] ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $target['old_title'],
				)
			);
		}
		self::log_change( $id, $before, 'restore' );
		return true;
	}

	/**
	 * Folder tree (flat list with parent ids).
	 *
	 * @return array
	 */
	public static function folders() {
		$terms = get_terms(
			array(
				'taxonomy'   => FOLDPRESS_TAX,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$out = array();
		if ( is_wp_error( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $t ) {
			$out[] = array(
				'id'     => (int) $t->term_id,
				'name'   => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ),
				'parent' => (int) $t->parent,
				'count'  => (int) $t->count,
			);
		}
		return $out;
	}

	/**
	 * Assign attachment(s) to a folder (0 = unassigned).
	 *
	 * @param int[] $ids    Attachment ids.
	 * @param int   $folder Folder term id.
	 * @return int Moved count.
	 */
	public static function move( $ids, $folder ) {
		$n = 0;
		foreach ( $ids as $id ) {
			if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			wp_set_object_terms( $id, $folder ? array( (int) $folder ) : array(), FOLDPRESS_TAX, false );
			++$n;
		}
		return $n;
	}

	/**
	 * Is $candidate a descendant of (or equal to) $folder.
	 *
	 * @param int $candidate Term id.
	 * @param int $folder    Term id.
	 * @return bool
	 */
	public static function is_descendant( $candidate, $folder ) {
		if ( (int) $candidate === (int) $folder ) {
			return true;
		}
		$anc = get_ancestors( (int) $candidate, FOLDPRESS_TAX, 'taxonomy' );
		return in_array( (int) $folder, array_map( 'intval', $anc ), true );
	}

	/**
	 * All descendant folder ids (deepest first) including the folder itself last.
	 *
	 * @param int $id Folder.
	 * @return int[]
	 */
	public static function folder_tree( $id ) {
		$kids = get_term_children( (int) $id, FOLDPRESS_TAX );
		$kids = is_wp_error( $kids ) ? array() : array_map( 'intval', $kids );
		usort(
			$kids,
			function ( $a, $b ) {
				return count( get_ancestors( $b, FOLDPRESS_TAX, 'taxonomy' ) ) - count( get_ancestors( $a, FOLDPRESS_TAX, 'taxonomy' ) );
			}
		);
		$kids[] = (int) $id;
		return $kids;
	}

	/**
	 * Delete a folder, its sub folders and every file inside them permanently.
	 * Files the user cannot delete are moved to the parent of the deleted folder.
	 *
	 * @param int $id Folder.
	 * @return array{folders:int,files:int,skipped:int}
	 */
	public static function delete_folder_tree( $id ) {
		$term   = get_term( $id, FOLDPRESS_TAX );
		$parent = ( $term && ! is_wp_error( $term ) ) ? (int) $term->parent : 0;
		$out    = array(
			'folders' => 0,
			'files'   => 0,
			'skipped' => 0,
		);
		foreach ( self::folder_tree( $id ) as $fid ) {
			$objs = get_objects_in_term( $fid, FOLDPRESS_TAX );
			foreach ( is_wp_error( $objs ) ? array() : $objs as $aid ) {
				$aid = (int) $aid;
				if ( 'attachment' === get_post_type( $aid ) && current_user_can( 'delete_post', $aid ) && wp_delete_attachment( $aid, true ) ) {
					++$out['files'];
				} else {
					wp_set_object_terms( $aid, $parent ? array( $parent ) : array(), FOLDPRESS_TAX, false );
					++$out['skipped'];
				}
			}
			if ( ! is_wp_error( wp_delete_term( $fid, FOLDPRESS_TAX ) ) ) {
				++$out['folders'];
			}
		}
		return $out;
	}

	/**
	 * ZIP whole folders (recursive, keeps the folder structure).
	 *
	 * @param int[]  $ids    Folder ids.
	 * @param int    $target Folder for the new archive.
	 * @param string $name   Archive name.
	 * @return int|WP_Error
	 */
	public static function zip_folders( $ids, $target, $name ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'nozip', __( 'ZipArchive PHP extension is not available on this server.', 'appstrdesign-foldpress' ) );
		}
		$tmp = wp_tempnam( 'foldpress-zip' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'zipopen', __( 'Could not create archive.', 'appstrdesign-foldpress' ) );
		}
		$added = 0;
		$used  = array();
		foreach ( $ids as $root ) {
			$root_term = get_term( $root, FOLDPRESS_TAX );
			if ( ! $root_term || is_wp_error( $root_term ) ) {
				continue;
			}
			$root_depth = count( get_ancestors( $root, FOLDPRESS_TAX, 'taxonomy' ) );
			foreach ( self::folder_tree( $root ) as $fid ) {
				// Path inside the archive: names from the selected root down to this folder.
				$chain = array_reverse( array_slice( get_ancestors( $fid, FOLDPRESS_TAX, 'taxonomy' ), 0, max( 0, count( get_ancestors( $fid, FOLDPRESS_TAX, 'taxonomy' ) ) - $root_depth ) ) );
				$chain[] = $fid;
				$parts   = array();
				foreach ( $chain as $cid ) {
					$t       = get_term( $cid, FOLDPRESS_TAX );
					$parts[] = sanitize_file_name( $t ? $t->name : 'folder' );
				}
				$dir = implode( '/', $parts );
				$zip->addEmptyDir( $dir );
				$objs = get_objects_in_term( $fid, FOLDPRESS_TAX );
				foreach ( is_wp_error( $objs ) ? array() : $objs as $aid ) {
					$path = get_attached_file( (int) $aid );
					if ( ! $path || ! is_readable( $path ) || ! current_user_can( 'read_post', (int) $aid ) ) {
						continue;
					}
					$fn = $dir . '/' . wp_basename( $path );
					$i  = 1;
					while ( isset( $used[ strtolower( $fn ) ] ) ) {
						$fn = $dir . '/' . pathinfo( $path, PATHINFO_FILENAME ) . '-' . $i . '.' . pathinfo( $path, PATHINFO_EXTENSION );
						++$i;
					}
					$used[ strtolower( $fn ) ] = true;
					$zip->addFile( $path, $fn );
					++$added;
				}
			}
		}
		$zip->close();
		if ( ! $added ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'empty', __( 'No files could be added to the archive.', 'appstrdesign-foldpress' ) );
		}
		$name = sanitize_file_name( $name ? $name : 'folders-' . gmdate( 'Ymd-His' ) );
		$name = preg_replace( '/\.zip$/i', '', $name ) . '.zip';
		return self::sideload( $tmp, $name, $target );
	}

	/**
	 * Create a ZIP attachment from attachments.
	 *
	 * @param int[]  $ids    Attachment ids.
	 * @param int    $folder Target folder.
	 * @param string $name   Archive base name.
	 * @return int|WP_Error New attachment id.
	 */
	public static function zip( $ids, $folder, $name ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'nozip', __( 'ZipArchive PHP extension is not available on this server.', 'appstrdesign-foldpress' ) );
		}
		$tmp = wp_tempnam( 'foldpress-zip' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'zipopen', __( 'Could not create archive.', 'appstrdesign-foldpress' ) );
		}
		$used  = array();
		$added = 0;
		foreach ( $ids as $id ) {
			if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'read_post', $id ) ) {
				continue;
			}
			$path = get_attached_file( $id );
			if ( ! $path || ! is_readable( $path ) ) {
				continue;
			}
			$base = wp_basename( $path );
			$i    = 1;
			$fn   = $base;
			while ( isset( $used[ strtolower( $fn ) ] ) ) {
				$fn = pathinfo( $base, PATHINFO_FILENAME ) . '-' . $i . '.' . pathinfo( $base, PATHINFO_EXTENSION );
				++$i;
			}
			$used[ strtolower( $fn ) ] = true;
			$zip->addFile( $path, $fn );
			++$added;
		}
		$zip->close();
		if ( ! $added ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'empty', __( 'No files could be added to the archive.', 'appstrdesign-foldpress' ) );
		}
		$name = sanitize_file_name( $name ? $name : 'archive-' . gmdate( 'Ymd-His' ) );
		$name = preg_replace( '/\.zip$/i', '', $name ) . '.zip';
		return self::sideload( $tmp, $name, $folder );
	}

	/**
	 * Extract a ZIP attachment into the media library.
	 *
	 * @param int $id     Zip attachment id.
	 * @param int $folder Target parent folder.
	 * @return array|WP_Error
	 */
	public static function unzip( $id, $folder ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'nozip', __( 'ZipArchive PHP extension is not available on this server.', 'appstrdesign-foldpress' ) );
		}
		$path = get_attached_file( $id );
		if ( ! $path || 'zip' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) || ! is_readable( $path ) ) {
			return new WP_Error( 'notzip', __( 'Selected file is not a ZIP archive.', 'appstrdesign-foldpress' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'zipopen', __( 'Could not open archive.', 'appstrdesign-foldpress' ) );
		}
		if ( $zip->numFiles > self::MAX_UNZIP_ENTRIES ) {
			$zip->close();
			/* translators: %d: max entries */
			return new WP_Error( 'toomany', sprintf( __( 'Archive contains more than %d entries.', 'appstrdesign-foldpress' ), self::MAX_UNZIP_ENTRIES ) );
		}
		$total = 0;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$st     = $zip->statIndex( $i );
			$total += $st ? (int) $st['size'] : 0;
		}
		if ( $total > self::MAX_UNZIP_BYTES ) {
			$zip->close();
			return new WP_Error( 'toobig', __( 'Archive is too large to extract.', 'appstrdesign-foldpress' ) );
		}

		// Root folder named after the archive.
		$root = self::ensure_folder( pathinfo( $path, PATHINFO_FILENAME ), $folder );
		if ( is_wp_error( $root ) ) {
			$zip->close();
			return $root;
		}

		$allowed  = get_allowed_mime_types();
		$imported = 0;
		$skipped  = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = (string) $zip->getNameIndex( $i );
			$entry = str_replace( '\\', '/', $entry );
			if ( '/' === substr( $entry, -1 ) || false !== strpos( $entry, '__MACOSX' ) ) {
				continue;
			}
			$parts = array_values( array_filter( explode( '/', $entry ), 'strlen' ) );
			$parts = array_values( array_diff( $parts, array( '.', '..' ) ) );
			if ( ! $parts ) {
				continue;
			}
			$base = sanitize_file_name( array_pop( $parts ) );
			if ( '' === $base || '.' === $base[0] ) {
				continue;
			}
			$check = wp_check_filetype( $base, $allowed );
			if ( ! $check['type'] ) {
				$skipped[] = $base;
				continue;
			}
			// Sub folders.
			$target = $root;
			foreach ( $parts as $dir ) {
				$target = self::ensure_folder( $dir, $target );
				if ( is_wp_error( $target ) ) {
					$target = $root;
					break;
				}
			}
			$stream = $zip->getStream( $zip->getNameIndex( $i ) );
			if ( ! $stream ) {
				$skipped[] = $base;
				continue;
			}
			$tmp = wp_tempnam( $base );
			$out = fopen( $tmp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			stream_copy_to_stream( $stream, $out );
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$res = self::sideload( $tmp, $base, $target );
			if ( is_wp_error( $res ) ) {
				$skipped[] = $base;
			} else {
				++$imported;
			}
		}
		$zip->close();
		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'folder'   => $root,
		);
	}

	/**
	 * Get or create a folder by name under parent.
	 *
	 * @param string $name   Name.
	 * @param int    $parent Parent id.
	 * @return int|WP_Error
	 */
	public static function ensure_folder( $name, $parent ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			$name = __( 'Untitled', 'appstrdesign-foldpress' );
		}
		$exists = term_exists( $name, FOLDPRESS_TAX, (int) $parent );
		if ( $exists ) {
			return (int) $exists['term_id'];
		}
		$res = wp_insert_term( $name, FOLDPRESS_TAX, array( 'parent' => (int) $parent, 'slug' => sanitize_title( $name ) . '-' . strtolower( wp_generate_password( 6, false ) ) ) );
		return is_wp_error( $res ) ? $res : (int) $res['term_id'];
	}

	/**
	 * Physically rename an attachment file and all its generated sizes,
	 * then update metadata and links in post content.
	 *
	 * @param int    $id   Attachment id.
	 * @param string $name New base name (extension is kept).
	 * @return int|WP_Error Updated database rows.
	 */
	public static function rename_file( $id, $name ) {
		global $wp_filesystem;

		$path = get_attached_file( $id );
		if ( ! $path || ! file_exists( $path ) ) {
			return new WP_Error( 'nofile', __( 'File not found on disk.', 'appstrdesign-foldpress' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() || 'direct' !== $wp_filesystem->method ) {
			return new WP_Error( 'fs', __( 'Direct file system access is required to rename files.', 'appstrdesign-foldpress' ) );
		}

		$dir  = dirname( $path );
		$meta = wp_get_attachment_metadata( $id );
		$meta = is_array( $meta ) ? $meta : array();

		// The "stem" is the original file name without extension; every generated size starts with it.
		$orig     = ! empty( $meta['original_image'] ) ? $meta['original_image'] : wp_basename( $path );
		$stem     = pathinfo( $orig, PATHINFO_FILENAME );
		$ext      = pathinfo( $orig, PATHINFO_EXTENSION );
		$new_stem = pathinfo( sanitize_file_name( preg_replace( '/\.[a-z0-9]{1,5}$/i', '', $name ) . '.' . $ext ), PATHINFO_FILENAME );
		if ( '' === $new_stem ) {
			return new WP_Error( 'name', __( 'Invalid file name.', 'appstrdesign-foldpress' ) );
		}
		if ( $new_stem === $stem ) {
			return 0;
		}

		// Collect every file belonging to the attachment.
		$files = array( wp_basename( $path ) );
		if ( ! empty( $meta['original_image'] ) ) {
			$files[] = $meta['original_image'];
		}
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$files[] = $size['file'];
				}
			}
		}
		$files = array_unique( $files );

		// Find a stem whose targets are all free.
		$base_stem = $new_stem;
		for ( $n = 1; $n < 100; $n++ ) {
			$free = true;
			foreach ( $files as $f ) {
				if ( 0 === strpos( $f, $stem ) && file_exists( $dir . '/' . $new_stem . substr( $f, strlen( $stem ) ) ) ) {
					$free = false;
					break;
				}
			}
			if ( $free ) {
				break;
			}
			$new_stem = $base_stem . '-' . $n;
		}
		if ( ! $free ) {
			return new WP_Error( 'exists', __( 'A file with this name already exists.', 'appstrdesign-foldpress' ) );
		}

		$map  = array(); // old basename => new basename.
		$done = array();
		foreach ( $files as $f ) {
			if ( 0 !== strpos( $f, $stem ) || ! file_exists( $dir . '/' . $f ) ) {
				continue;
			}
			$to = $new_stem . substr( $f, strlen( $stem ) );
			if ( ! $wp_filesystem->move( $dir . '/' . $f, $dir . '/' . $to ) ) {
				foreach ( $done as $from => $back ) {
					$wp_filesystem->move( $dir . '/' . $back, $dir . '/' . $from );
				}
				return new WP_Error( 'move', __( 'Could not rename the file on disk.', 'appstrdesign-foldpress' ) );
			}
			$done[ $f ] = $to;
			$map[ $f ]  = $to;
		}

		$old_urls = array();
		$base_url = trailingslashit( dirname( wp_get_attachment_url( $id ) ) );
		$rel_dir  = dirname( (string) get_post_meta( $id, '_wp_attached_file', true ) );
		$rel_dir  = ( '.' === $rel_dir || '' === $rel_dir ) ? '' : trailingslashit( $rel_dir );
		foreach ( $map as $from => $to ) {
			$old_urls[ $base_url . $from ] = $base_url . $to;
		}
		// Upload-relative paths (some builders and themes store these). Only when a sub directory makes them unique.
		if ( $rel_dir ) {
			foreach ( $map as $from => $to ) {
				$old_urls[ $rel_dir . $from ] = $rel_dir . $to;
			}
		}

		// Metadata.
		$main = wp_basename( $path );
		if ( isset( $map[ $main ] ) ) {
			update_attached_file( $id, $dir . '/' . $map[ $main ] );
			if ( ! empty( $meta['file'] ) ) {
				$rel          = dirname( $meta['file'] );
				$meta['file'] = ( '.' === $rel ? '' : $rel . '/' ) . $map[ $main ];
			}
		}
		if ( ! empty( $meta['original_image'] ) && isset( $map[ $meta['original_image'] ] ) ) {
			$meta['original_image'] = $map[ $meta['original_image'] ];
		}
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $k => $size ) {
				if ( ! empty( $size['file'] ) && isset( $map[ $size['file'] ] ) ) {
					$meta['sizes'][ $k ]['file'] = $map[ $size['file'] ];
				}
			}
		}
		if ( $meta ) {
			wp_update_attachment_metadata( $id, $meta );
		}

		// Update every reference in the database (posts, meta, Elementor, WooCommerce, options, term meta).
		$rows = FoldPress_Replacer::run( $old_urls );
		clean_post_cache( $id );
		return $rows;
	}

	/**
	 * Sideload a temp file as attachment (validates type through core).
	 *
	 * @param string $tmp    Temp file.
	 * @param string $name   File name.
	 * @param int    $folder Folder id.
	 * @return int|WP_Error
	 */
	public static function sideload( $tmp, $name, $folder ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$file = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);
		FoldPress_Settings::$upload_folder = (int) $folder;
		$id = media_handle_sideload( $file, 0 );
		FoldPress_Settings::$upload_folder = 0;
		if ( is_wp_error( $id ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return $id;
		}
		if ( $folder ) {
			wp_set_object_terms( $id, array( (int) $folder ), FOLDPRESS_TAX, false );
		}
		return (int) $id;
	}
}
