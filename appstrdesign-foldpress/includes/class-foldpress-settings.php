<?php
/**
 * Settings storage + media behaviour filters driven by settings.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Settings
 */
class FoldPress_Settings {

	const OPTION = 'foldpress_settings';

	/**
	 * Extensions that can never be enabled (code execution / XSS vectors).
	 *
	 * @var string[]
	 */
	const BLOCKED = array( 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'pl', 'py', 'cgi', 'sh', 'bash', 'exe', 'msi', 'bat', 'cmd', 'com', 'dll', 'so', 'vbs', 'ps1', 'jar', 'asp', 'aspx', 'jsp', 'html', 'htm', 'shtml', 'xhtml', 'svg', 'svgz', 'htaccess', 'htpasswd', 'ini', 'swf' );

	/**
	 * Folder used for the upload currently being processed (for "folder" sub-directory mode).
	 *
	 * @var int
	 */
	public static $upload_folder = 0;

	/**
	 * Cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'replace_library'     => 1,
			'replace_editor'      => 1,
			'replace_code_editor' => 1,
			'disable_file_edit'   => 0,
			'disable_file_mods'   => 0,
			'modal_integration'   => 1,
			'modal_theme'         => 1,
			'lightbox'            => 1,
			'office_viewer'       => 1,
			'gdrive_on'           => 0,
			'gdrive_client_id'    => '',
			'gdrive_api_key'      => '',
			'gdrive_app_id'       => '',
			'dropbox_on'          => 0,
			'dropbox_key'         => '',
			'onedrive_on'         => 0,
			'onedrive_client_id'  => '',
			'dav_on'              => 0,
			'dav_label'           => '',
			'dav_url'             => '',
			'dav_user'            => '',
			'cloud_url'           => 1,
			'pagination'          => 'loadmore',
			'per_page'            => 60,
			'default_view'        => 'grid',
			'subdir_mode'         => 'yearmonth',
			'subdir_custom'       => 'media',
			'max_upload_mb'       => 0,
			'sanitize_names'      => 1,
			'lowercase_names'     => 1,
			'big_image_threshold' => 2560,
			'jpeg_quality'        => 82,
			'toast_position'      => 'top-right',
			'convert_format'      => '',
			'convert_quality'     => 'medium',
			'convert_custom'      => 75,
			'disabled_types'      => array(),
			'custom_types'        => array(),
			'disabled_sizes'      => array(),
		);
	}

	/**
	 * Get all settings or one key.
	 *
	 * @param string|null $key Key.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		if ( null === $key ) {
			return self::$cache;
		}
		return isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : null;
	}

	/**
	 * Sanitize and store.
	 *
	 * @param array $in Raw input (already unslashed).
	 * @return array
	 */
	public static function save( $in ) {
		$d   = self::defaults();
		$out = array();
		foreach ( array( 'replace_library', 'replace_editor', 'replace_code_editor', 'disable_file_edit', 'disable_file_mods', 'modal_integration', 'modal_theme', 'lightbox', 'office_viewer', 'gdrive_on', 'dropbox_on', 'onedrive_on', 'dav_on', 'cloud_url', 'sanitize_names', 'lowercase_names' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$out['pagination']          = isset( $in['pagination'] ) && 'numbers' === $in['pagination'] ? 'numbers' : 'loadmore';
		$out['per_page']            = isset( $in['per_page'] ) ? min( 200, max( 12, absint( $in['per_page'] ) ) ) : $d['per_page'];
		$out['default_view']        = isset( $in['default_view'] ) && 'list' === $in['default_view'] ? 'list' : 'grid';
		$out['subdir_mode']         = isset( $in['subdir_mode'] ) && in_array( $in['subdir_mode'], array( 'yearmonth', 'flat', 'custom', 'folder' ), true ) ? $in['subdir_mode'] : 'yearmonth';
		$out['subdir_custom']       = isset( $in['subdir_custom'] ) ? self::clean_path( $in['subdir_custom'] ) : $d['subdir_custom'];
		$out['max_upload_mb']       = isset( $in['max_upload_mb'] ) ? absint( $in['max_upload_mb'] ) : 0;
		$out['big_image_threshold'] = isset( $in['big_image_threshold'] ) ? absint( $in['big_image_threshold'] ) : $d['big_image_threshold'];
		$out['jpeg_quality']        = isset( $in['jpeg_quality'] ) ? min( 100, max( 10, absint( $in['jpeg_quality'] ) ) ) : $d['jpeg_quality'];
		foreach ( array( 'gdrive_client_id', 'gdrive_api_key', 'gdrive_app_id', 'dropbox_key', 'onedrive_client_id', 'dav_label', 'dav_user' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		}
		$out['dav_url'] = isset( $in['dav_url'] ) ? esc_url_raw( trim( $in['dav_url'] ), array( 'http', 'https' ) ) : '';
		if ( ! empty( $in['dav_pass'] ) ) {
			// Kept out of the main (autoloaded, client visible) settings.
			update_option( 'foldpress_dav_pass', (string) $in['dav_pass'], false );
		}
		$out['toast_position']      = isset( $in['toast_position'] ) && in_array( $in['toast_position'], array( 'top-right', 'top-left', 'top-center', 'bottom-right', 'bottom-left', 'bottom-center' ), true ) ? $in['toast_position'] : 'top-right';
		$out['convert_format']      = isset( $in['convert_format'] ) && in_array( $in['convert_format'], array( 'webp', 'avif' ), true ) && FoldPress_Converter::supports( $in['convert_format'] ) ? $in['convert_format'] : '';
		$out['convert_quality']     = isset( $in['convert_quality'] ) && in_array( $in['convert_quality'], array( 'low', 'medium', 'high', 'custom' ), true ) ? $in['convert_quality'] : 'medium';
		$out['convert_custom']      = isset( $in['convert_custom'] ) ? min( 100, max( 1, absint( $in['convert_custom'] ) ) ) : $d['convert_custom'];

		// File types: anything in the core list that is not ticked becomes disabled.
		$core    = array_keys( self::core_types() );
		$enabled = isset( $in['enabled_types'] ) ? array_map( 'sanitize_text_field', (array) $in['enabled_types'] ) : array();
		$out['disabled_types'] = array_values( array_diff( $core, $enabled ) );

		$out['custom_types'] = array();
		if ( ! empty( $in['custom_types'] ) && is_array( $in['custom_types'] ) ) {
			foreach ( $in['custom_types'] as $row ) {
				$ext  = isset( $row['ext'] ) ? strtolower( preg_replace( '/[^a-z0-9]/i', '', $row['ext'] ) ) : '';
				$mime = isset( $row['mime'] ) ? strtolower( trim( sanitize_text_field( $row['mime'] ) ) ) : '';
				if ( $ext && preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $mime ) && ! in_array( $ext, self::BLOCKED, true ) ) {
					$out['custom_types'][ $ext ] = array(
						'mime' => $mime,
						'on'   => empty( $row['on'] ) ? 0 : 1,
					);
				}
			}
		}

		$sizes   = self::extra_sizes();
		$enabled = isset( $in['enabled_sizes'] ) ? array_map( 'sanitize_key', (array) $in['enabled_sizes'] ) : array();
		$out['disabled_sizes'] = array_values( array_diff( $sizes, $enabled ) );

		// Core image size options.
		foreach ( array( 'thumbnail', 'medium', 'large' ) as $s ) {
			if ( isset( $in[ $s . '_w' ] ) ) {
				update_option( $s . '_size_w', absint( $in[ $s . '_w' ] ) );
			}
			if ( isset( $in[ $s . '_h' ] ) ) {
				update_option( $s . '_size_h', absint( $in[ $s . '_h' ] ) );
			}
		}
		update_option( 'thumbnail_crop', empty( $in['thumbnail_crop'] ) ? 0 : 1 );

		update_option( self::OPTION, $out );
		self::$cache = null;
		return self::get();
	}

	/**
	 * Clean a relative path segment list.
	 *
	 * @param string $p Path.
	 * @return string
	 */
	public static function clean_path( $p ) {
		$parts = array_filter( explode( '/', str_replace( '\\', '/', (string) $p ) ), 'strlen' );
		$parts = array_map( 'sanitize_title', $parts );
		$parts = array_filter( $parts, 'strlen' );
		return implode( '/', $parts );
	}

	/**
	 * Core mime list (allowed types before our filter).
	 *
	 * @return array
	 */
	public static function core_types() {
		remove_filter( 'upload_mimes', array( __CLASS__, 'filter_mimes' ), 99 );
		$types = get_allowed_mime_types();
		add_filter( 'upload_mimes', array( __CLASS__, 'filter_mimes' ), 99 );
		return $types;
	}

	/**
	 * Registered sizes other than the core three.
	 *
	 * @return string[]
	 */
	public static function extra_sizes() {
		return array_values( array_diff( get_intermediate_image_sizes(), array( 'thumbnail', 'medium', 'large' ) ) );
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'upload_mimes', array( __CLASS__, 'filter_mimes' ), 99 );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'check_custom_type' ), 10, 5 );
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'enforce_types' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( __CLASS__, 'enforce_types' ) );
		add_filter( 'upload_size_limit', array( __CLASS__, 'size_limit' ) );
		add_filter( 'big_image_size_threshold', array( __CLASS__, 'big_threshold' ) );
		add_filter( 'wp_editor_set_quality', array( __CLASS__, 'quality' ), 10, 2 );
		add_filter( 'intermediate_image_sizes_advanced', array( __CLASS__, 'sizes' ) );
		add_filter( 'sanitize_file_name', array( __CLASS__, 'file_name' ), 5 );
		add_filter( 'upload_dir', array( __CLASS__, 'upload_dir' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'lock_caps' ), 10, 2 );
		add_filter( 'file_mod_allowed', array( __CLASS__, 'file_mods' ), 10, 2 );
	}

	/**
	 * Code editors switched off in FoldPress or by DISALLOW_FILE_EDIT / DISALLOW_FILE_MODS.
	 *
	 * @return bool
	 */
	public static function file_edit_locked() {
		return ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) || ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS )
			|| self::get( 'disable_file_edit' ) || self::get( 'disable_file_mods' );
	}

	/**
	 * Same rules as core map_meta_cap() for DISALLOW_FILE_EDIT / DISALLOW_FILE_MODS: the capabilities are
	 * removed for everybody, so WordPress hides the menus and refuses the screens itself.
	 * The FoldPress settings page (manage_options) stays available to switch it back on.
	 *
	 * @param string[] $caps Primitive caps.
	 * @param string   $cap  Requested cap.
	 * @return string[]
	 */
	public static function lock_caps( $caps, $cap ) {
		$edit = array( 'edit_themes', 'edit_plugins', 'edit_files' );
		$mods = array( 'install_plugins', 'install_themes', 'update_plugins', 'update_themes', 'delete_plugins', 'delete_themes', 'upload_plugins', 'upload_themes', 'update_core', 'install_languages', 'update_languages' );
		if ( ( in_array( $cap, $edit, true ) && ( self::get( 'disable_file_edit' ) || self::get( 'disable_file_mods' ) ) ) || ( in_array( $cap, $mods, true ) && self::get( 'disable_file_mods' ) ) ) {
			$caps[] = 'do_not_allow';
		}
		return $caps;
	}

	/**
	 * Core checks this for updates, auto-updates and the file editors (wp_is_file_mod_allowed()).
	 *
	 * @param bool   $allowed Allowed.
	 * @param string $context Context.
	 * @return bool
	 */
	public static function file_mods( $allowed, $context ) {
		if ( self::get( 'disable_file_mods' ) ) {
			return false;
		}
		return 'capability_edit_themes' === $context && self::get( 'disable_file_edit' ) ? false : $allowed;
	}

	/**
	 * Allowed mimes.
	 *
	 * @param array $mimes Mimes.
	 * @return array
	 */
	public static function filter_mimes( $mimes ) {
		foreach ( (array) self::get( 'disabled_types' ) as $k ) {
			unset( $mimes[ $k ] );
		}
		// Custom types switched off: also remove the extension if some other list contains it.
		foreach ( self::custom_map( false ) as $ext => $row ) {
			if ( ! $row['on'] ) {
				foreach ( array_keys( $mimes ) as $k ) {
					if ( in_array( $ext, explode( '|', $k ), true ) ) {
						unset( $mimes[ $k ] );
					}
				}
			}
		}
		foreach ( self::custom_map() as $ext => $mime ) {
			$mimes[ $ext ] = $mime;
		}
		foreach ( array_keys( $mimes ) as $k ) {
			if ( array_intersect( explode( '|', $k ), self::BLOCKED ) ) {
				unset( $mimes[ $k ] );
			}
		}
		return $mimes;
	}

	/**
	 * Custom types. Old installs stored ext => mime; new ones ext => [mime, on].
	 *
	 * @param bool $only_on Only enabled types, as ext => mime.
	 * @return array
	 */
	public static function custom_map( $only_on = true ) {
		$out = array();
		foreach ( (array) self::get( 'custom_types' ) as $ext => $row ) {
			$row = is_array( $row ) ? $row : array(
				'mime' => (string) $row,
				'on'   => 1,
			);
			if ( $only_on ) {
				if ( ! empty( $row['on'] ) ) {
					$out[ $ext ] = $row['mime'];
				}
			} else {
				$out[ $ext ] = array(
					'mime' => $row['mime'],
					'on'   => empty( $row['on'] ) ? 0 : 1,
				);
			}
		}
		return $out;
	}

	/**
	 * Hard rule: only extensions enabled in FoldPress > File Types can be uploaded,
	 * also for users who could otherwise bypass the list (unfiltered_upload).
	 *
	 * @param array $file Upload array (name, ...).
	 * @return array
	 */
	public static function enforce_types( $file ) {
		if ( empty( $file['name'] ) || ! empty( $file['error'] ) ) {
			return $file;
		}
		$ext     = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		$allowed = array();
		foreach ( array_keys( get_allowed_mime_types() ) as $k ) {
			$allowed = array_merge( $allowed, explode( '|', $k ) );
		}
		if ( '' === $ext || ! in_array( $ext, $allowed, true ) ) {
			/* translators: %s: file extension */
			$file['error'] = sprintf( __( 'The .%s file type is disabled. An administrator can enable it in FoldPress > Settings > File Types.', 'appstrdesign-foldpress' ), $ext ? $ext : '?' );
		}
		return $file;
	}

	/**
	 * Make every type enabled in FoldPress > File Types actually uploadable.
	 * Core refuses text formats whose content is detected as text/plain (css, js, md, yaml...)
	 * and some containers detected as zip / octet-stream. We accept them when the detected
	 * content family is compatible with the declared type and the file contains no server-side code.
	 *
	 * @param array      $data     Result from core.
	 * @param string     $file     Full path.
	 * @param string     $filename Name.
	 * @param array|null $mimes    Allowed mimes.
	 * @param string     $real     Real mime detected by core.
	 * @return array
	 */
	public static function check_custom_type( $data, $file, $filename, $mimes, $real = '' ) {
		if ( ! empty( $data['type'] ) ) {
			return $data;
		}
		$ext     = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$allowed = $mimes ? $mimes : get_allowed_mime_types();
		$check   = wp_check_filetype( $filename, $allowed );
		$custom  = self::custom_map();
		$declared = $check['type'] ? $check['type'] : ( isset( $custom[ $ext ] ) ? $custom[ $ext ] : '' );
		if ( ! $declared || ! $ext ) {
			return $data;
		}
		if ( ! $real && function_exists( 'finfo_open' ) && is_readable( $file ) ) {
			$fi   = finfo_open( FILEINFO_MIME_TYPE );
			$real = finfo_file( $fi, $file );
			finfo_close( $fi );
		}
		$text_real     = ! $real || 0 === strpos( $real, 'text/' ) || in_array( $real, array( 'application/javascript', 'application/x-javascript', 'application/json', 'application/xml', 'inode/x-empty', 'application/x-empty' ), true );
		$text_declared = 0 === strpos( $declared, 'text/' ) || (bool) preg_match( '#(javascript|json|xml|yaml|toml|markdown|csv|subrip|vtt|plain|x-httpd-php-source|ttaf)#', $declared );
		$bin_real      = in_array( $real, array( 'application/octet-stream', 'application/zip', 'application/x-zip-compressed', 'application/x-empty', 'inode/x-empty' ), true );
		$bin_declared  = 0 === strpos( $declared, 'application/' ) || 0 === strpos( $declared, 'model/' ) || 0 === strpos( $declared, 'font/' ) || 0 === strpos( $declared, 'audio/' ) || 0 === strpos( $declared, 'video/' );

		$ok = $real === $declared || ( $text_real && $text_declared ) || ( $bin_real && $bin_declared );
		if ( $ok && self::has_code( $file, $ext ) ) {
			$ok = false;
		}
		if ( $ok ) {
			$data['ext']             = $ext;
			$data['type']            = $declared;
			$data['proper_filename'] = false;
		}
		return $data;
	}

	/**
	 * Detect executable code in an uploaded file (first 256 KB).
	 *
	 * @param string $file Path.
	 * @return bool
	 */
	private static function has_code( $file, $ext = '' ) {
		if ( ! is_readable( $file ) ) {
			return true;
		}
		$head = (string) file_get_contents( $file, false, null, 0, 262144 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( preg_match( '/<\\?php|<\\?=|<%|^#!\\/(usr\\/)?bin\\//i', $head ) ) {
			return true;
		}
		// Script tags only matter for formats a browser could render as a page.
		return ! in_array( $ext, array( 'js', 'mjs', 'json', 'css', 'scss', 'less', 'map', 'ts' ), true ) && (bool) preg_match( '/<script[\\s>]/i', $head );
	}

	/**
	 * Upload size cap.
	 *
	 * @param int $size Bytes.
	 * @return int
	 */
	public static function size_limit( $size ) {
		$mb = (int) self::get( 'max_upload_mb' );
		return $mb > 0 ? min( $size, $mb * MB_IN_BYTES ) : $size;
	}

	/**
	 * Big image threshold (0 disables scaling).
	 *
	 * @return int|false
	 */
	public static function big_threshold() {
		$t = (int) self::get( 'big_image_threshold' );
		return $t > 0 ? $t : false;
	}

	/**
	 * JPEG / WebP quality.
	 *
	 * @param int    $q    Quality.
	 * @param string $mime Mime.
	 * @return int
	 */
	public static function quality( $q, $mime = '' ) {
		if ( in_array( $mime, array( 'image/webp', 'image/avif' ), true ) && self::get( 'convert_format' ) ) {
			return FoldPress_Converter::quality( $mime );
		}
		return in_array( $mime, array( 'image/jpeg', 'image/webp', '' ), true ) ? (int) self::get( 'jpeg_quality' ) : $q;
	}

	/**
	 * Disable selected generated sizes.
	 *
	 * @param array $sizes Sizes.
	 * @return array
	 */
	public static function sizes( $sizes ) {
		foreach ( (array) self::get( 'disabled_sizes' ) as $s ) {
			unset( $sizes[ $s ] );
		}
		return $sizes;
	}

	/**
	 * Transliterate (Turkish etc.) and lowercase file names.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function file_name( $name ) {
		if ( self::get( 'sanitize_names' ) ) {
			$name = remove_accents( $name );
			$name = preg_replace( '/[^A-Za-z0-9._-]+/', '-', $name );
			$name = preg_replace( '/-{2,}/', '-', $name );
		}
		if ( self::get( 'lowercase_names' ) ) {
			$name = strtolower( $name );
		}
		return $name;
	}

	/**
	 * Sub-directory organisation. Base dir is never changed, so existing files keep working.
	 *
	 * @param array $dirs Upload dirs.
	 * @return array
	 */
	public static function upload_dir( $dirs ) {
		$mode = self::get( 'subdir_mode' );
		if ( 'yearmonth' === $mode ) {
			return $dirs;
		}
		$time = current_time( 'mysql' );
		$ym   = '/' . substr( $time, 0, 4 ) . '/' . substr( $time, 5, 2 );
		$sub  = '';
		if ( 'custom' === $mode ) {
			$c   = self::get( 'subdir_custom' );
			$sub = ( $c ? '/' . $c : '' ) . $ym;
		} elseif ( 'folder' === $mode ) {
			$folder = self::$upload_folder;
			// phpcs:ignore WordPress.Security.NonceVerification -- only used to pick a directory; core verified the upload nonce.
			if ( ! $folder && isset( $_REQUEST['foldpress_folder'] ) ) {
				$folder = absint( wp_unslash( $_REQUEST['foldpress_folder'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			$sub = $folder ? '/' . self::folder_path( $folder ) : $ym;
		}
		$dirs['subdir'] = $sub;
		$dirs['path']   = $dirs['basedir'] . $sub;
		$dirs['url']    = $dirs['baseurl'] . $sub;
		return $dirs;
	}

	/**
	 * Folder slug path ("parent/child").
	 *
	 * @param int $folder Term id.
	 * @return string
	 */
	public static function folder_path( $folder ) {
		$ids   = array_reverse( get_ancestors( $folder, FOLDPRESS_TAX, 'taxonomy' ) );
		$ids[] = $folder;
		$parts = array();
		foreach ( $ids as $id ) {
			$t = get_term( $id, FOLDPRESS_TAX );
			if ( $t && ! is_wp_error( $t ) ) {
				$parts[] = sanitize_title( remove_accents( $t->name ) );
			}
		}
		$path = implode( '/', array_filter( $parts, 'strlen' ) );
		return $path ? $path : 'foldpress';
	}
}
