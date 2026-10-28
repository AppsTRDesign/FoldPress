<?php
/**
 * Cloud import: Google Drive, Dropbox, OneDrive (official pickers), WebDAV (Nextcloud, ownCloud,
 * Synology, pCloud, Yandex Disk...) and direct URLs. Files are downloaded on the server and added
 * to the media library through the normal upload checks (allowed types, size, conversion).
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Cloud
 */
class FoldPress_Cloud {

	const DAV_PASS = 'foldpress_dav_pass';
	const MAX      = 20;

	/**
	 * Google native document export formats.
	 *
	 * @var array
	 */
	const GOOGLE_EXPORT = array(
		'application/vnd.google-apps.document'     => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx' ),
		'application/vnd.google-apps.spreadsheet'  => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx' ),
		'application/vnd.google-apps.presentation' => array( 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx' ),
		'application/vnd.google-apps.drawing'      => array( 'image/png', 'png' ),
	);

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_foldpress_cloud_import', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_foldpress_cloud_dav_list', array( $this, 'ajax_dav_list' ) );
		add_action( 'wp_ajax_foldpress_cloud_dav_test', array( $this, 'ajax_dav_test' ) );
		add_action( 'wp_ajax_foldpress_import_progress', array( $this, 'ajax_progress' ) );
		add_action( 'wp_ajax_foldpress_import_cancel', array( $this, 'ajax_cancel' ) );
	}

	/**
	 * Public client configuration (never contains secrets).
	 *
	 * @return array
	 */
	public static function client_config() {
		$s = FoldPress_Settings::get();
		return array(
			'google'   => $s['gdrive_on'] && $s['gdrive_client_id'] && $s['gdrive_api_key'] ? array(
				'clientId' => $s['gdrive_client_id'],
				'apiKey'   => $s['gdrive_api_key'],
				'appId'    => $s['gdrive_app_id'],
			) : null,
			'dropbox'  => $s['dropbox_on'] && $s['dropbox_key'] ? array( 'appKey' => $s['dropbox_key'] ) : null,
			'onedrive' => $s['onedrive_on'] && $s['onedrive_client_id'] ? array( 'clientId' => $s['onedrive_client_id'] ) : null,
			'dav'      => $s['dav_on'] && $s['dav_url'] ? array( 'label' => $s['dav_label'] ? $s['dav_label'] : 'WebDAV' ) : null,
			'url'      => (bool) $s['cloud_url'],
		);
	}

	/**
	 * Guard for upload screens.
	 */
	private function guard() {
		check_ajax_referer( FoldPress_Ajax::NONCE, 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage media.', 'appstrdesign-foldpress' ) ), 403 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/**
	 * Host ends with one of the allowed suffixes.
	 *
	 * @param string   $url      URL.
	 * @param string[] $suffixes Suffixes.
	 * @return bool
	 */
	private static function host_ok( $url, $suffixes ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! $host ) {
			return false;
		}
		foreach ( $suffixes as $s ) {
			if ( $host === $s || substr( $host, -strlen( '.' . $s ) ) === '.' . $s ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Running import job (progress + cancel), set only while a download is in flight.
	 *
	 * @var array|null
	 */
	private static $job = null;

	/**
	 * Transient key of a job (scoped to the current user).
	 *
	 * @param string $id Job id.
	 * @return string
	 */
	private static function job_key( $id ) {
		return 'foldpress_imp_' . get_current_user_id() . '_' . $id;
	}

	/**
	 * Sanitised client job id.
	 *
	 * @param mixed $id Raw id.
	 * @return string
	 */
	private static function job_id( $id ) {
		$id = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $id ) );
		return strlen( $id ) >= 8 && strlen( $id ) <= 40 ? $id : '';
	}

	/**
	 * Store job state for the progress endpoint.
	 *
	 * @param string $phase download|process.
	 */
	private static function job_save( $phase ) {
		if ( self::$job ) {
			set_transient(
				self::job_key( self::$job['id'] ),
				array(
					'tmp'   => self::$job['tmp'],
					'total' => self::$job['total'],
					'phase' => $phase,
				),
				HOUR_IN_SECONDS
			);
		}
	}

	/**
	 * Clear the job state.
	 */
	private static function job_end() {
		if ( self::$job ) {
			delete_transient( self::job_key( self::$job['id'] ) );
			self::$job = null;
		}
	}

	/**
	 * Enable cURL progress reporting for the running job.
	 *
	 * @param resource $handle cURL handle.
	 */
	public static function curl_hook( $handle ) {
		if ( self::$job && defined( 'CURLOPT_XFERINFOFUNCTION' ) ) {
			curl_setopt( $handle, CURLOPT_NOPROGRESS, false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
			curl_setopt( $handle, CURLOPT_XFERINFOFUNCTION, array( __CLASS__, 'curl_tick' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
		}
	}

	/**
	 * cURL progress callback: records the expected size, aborts when the user cancelled.
	 *
	 * @param resource $ch      Handle.
	 * @param int      $dltotal Expected bytes.
	 * @return int Non-zero aborts the transfer.
	 */
	public static function curl_tick( $ch, $dltotal ) {
		if ( ! self::$job ) {
			return 0;
		}
		$total = (int) $dltotal;
		if ( $total > 0 && $total !== self::$job['total'] ) {
			self::$job['total'] = $total;
			self::job_save( 'download' );
		}
		$now = microtime( true );
		if ( $now - self::$job['check'] > 0.7 ) {
			self::$job['check'] = $now;
			clearstatcache();
			if ( file_exists( self::$job['tmp'] . '.cancel' ) ) {
				return 1;
			}
		}
		return 0;
	}

	/**
	 * File name from a Content-Disposition header.
	 *
	 * @param string $cd Header value.
	 * @return string
	 */
	private static function disposition_name( $cd ) {
		if ( preg_match( "/filename\*\s*=\s*[\w\-]+'[^']*'([^;]+)/i", $cd, $m ) ) {
			return rawurldecode( trim( $m[1], " \t\"'" ) );
		}
		if ( preg_match( '/filename\s*=\s*"([^"]+)"/i', $cd, $m ) || preg_match( '/filename\s*=\s*([^;]+)/i', $cd, $m ) ) {
			return trim( $m[1], " \t\"'" );
		}
		return '';
	}

	/**
	 * Single header value (last one wins when a header repeats).
	 *
	 * @param array  $res  Response.
	 * @param string $name Header.
	 * @return string
	 */
	private static function header( $res, $name ) {
		$v = wp_remote_retrieve_header( $res, $name );
		return is_array( $v ) ? (string) end( $v ) : (string) $v;
	}

	/**
	 * Readable error for a failed HTTP status.
	 *
	 * @param int $code Status.
	 * @return WP_Error
	 */
	private static function http_error( $code ) {
		if ( 404 === $code || 410 === $code ) {
			/* translators: %d: HTTP status */
			$msg = __( 'No file was found at this address (HTTP %d).', 'appstrdesign-foldpress' );
		} elseif ( in_array( $code, array( 401, 403, 407 ), true ) ) {
			/* translators: %d: HTTP status */
			$msg = __( 'The server refused access (HTTP %d). The link may be private or expired.', 'appstrdesign-foldpress' );
		} elseif ( 429 === $code ) {
			/* translators: %d: HTTP status */
			$msg = __( 'The server is limiting requests (HTTP %d). Try again in a few minutes.', 'appstrdesign-foldpress' );
		} else {
			/* translators: %d: HTTP status */
			$msg = __( 'The remote server answered with HTTP %d.', 'appstrdesign-foldpress' );
		}
		return new WP_Error( 'http', sprintf( $msg, $code ) );
	}

	/**
	 * Download to a temp file and add it to the media library. Follows redirects and HTML
	 * meta refresh pages and takes the real file name from Content-Disposition or the final URL.
	 *
	 * @param string $url     URL.
	 * @param string $name    Suggested file name.
	 * @param int    $folder  FoldPress folder.
	 * @param array  $headers Request headers.
	 * @param bool   $safe    Use wp_safe_remote_get (blocks private networks).
	 * @param array  $opt     weak (name is only a guess), browser (browser-like headers), job (progress id).
	 * @return int|WP_Error Attachment id.
	 */
	private static function fetch( $url, $name, $folder, $headers = array(), $safe = true, $opt = array() ) {
		$opt = wp_parse_args(
			$opt,
			array(
				'weak'    => false,
				'browser' => false,
				'job'     => '',
			)
		);
		$tmp = wp_tempnam( 'foldpress-import' );
		if ( $opt['job'] ) {
			self::$job = array(
				'id'    => $opt['job'],
				'tmp'   => $tmp,
				'total' => 0,
				'check' => 0,
			);
			self::job_save( 'download' );
			add_action( 'http_api_curl', array( __CLASS__, 'curl_hook' ) );
		}
		$res = self::download( $url, $tmp, $headers, $safe, $opt['browser'] );
		if ( $opt['job'] ) {
			remove_action( 'http_api_curl', array( __CLASS__, 'curl_hook' ) );
			clearstatcache();
			if ( file_exists( $tmp . '.cancel' ) ) {
				wp_delete_file( $tmp . '.cancel' );
				$res = new WP_Error( 'cancelled', __( 'Cancelled.', 'appstrdesign-foldpress' ) );
			}
		}
		if ( is_wp_error( $res ) ) {
			wp_delete_file( $tmp );
			self::job_end();
			return $res;
		}
		clearstatcache();
		$size = (int) filesize( $tmp );
		if ( $size >= wp_max_upload_size() ) {
			wp_delete_file( $tmp );
			self::job_end();
			return new WP_Error( 'size', __( 'File exceeds the maximum upload size.', 'appstrdesign-foldpress' ) );
		}
		if ( self::$job ) {
			self::$job['total'] = $size;
			self::job_save( 'process' );
		}

		// Real name: Content-Disposition, then the final URL after redirects, then the suggestion.
		$ctype   = strtolower( trim( explode( ';', self::header( $res, 'content-type' ) )[0] ) );
		$from_cd = sanitize_file_name( self::disposition_name( self::header( $res, 'content-disposition' ) ) );
		$final   = $url;
		if ( isset( $res['http_response'] ) && is_object( $res['http_response'] ) && method_exists( $res['http_response'], 'get_response_object' ) ) {
			$final = (string) $res['http_response']->get_response_object()->url;
		}
		$from_url = sanitize_file_name( rawurldecode( wp_basename( (string) wp_parse_url( $final, PHP_URL_PATH ) ) ) );
		$name     = sanitize_file_name( (string) $name );
		$has_ext  = function ( $n ) {
			return '' !== $n && '' !== pathinfo( $n, PATHINFO_EXTENSION );
		};
		if ( $from_cd ) {
			$name = $from_cd;
		} elseif ( ( $opt['weak'] || ! $has_ext( $name ) ) && $has_ext( $from_url ) ) {
			$name = $from_url;
		} elseif ( '' === $name ) {
			$name = $from_url;
		}
		if ( ! $has_ext( $name ) ) {
			$mime = $ctype;
			if ( ( ! $mime || in_array( $mime, array( 'application/octet-stream', 'binary/octet-stream', 'application/force-download', 'application/download' ), true ) ) && function_exists( 'finfo_open' ) ) {
				$fi   = finfo_open( FILEINFO_MIME_TYPE );
				$mime = $fi ? (string) finfo_file( $fi, $tmp ) : '';
			}
			$ext  = $mime && function_exists( 'wp_get_default_extension_for_mime_type' ) ? wp_get_default_extension_for_mime_type( $mime ) : '';
			$name = ( $name ? $name : 'imported-file' ) . ( $ext ? '.' . $ext : '' );
		}
		$id = FoldPress_Files::sideload( $tmp, $name, $folder );
		self::job_end();
		return $id;
	}

	/**
	 * GET a URL into $tmp. Retries requests a firewall refused, follows meta refresh pages.
	 *
	 * @param string $url     URL.
	 * @param string $tmp     Target file.
	 * @param array  $headers Headers.
	 * @param bool   $safe    Safe request.
	 * @param bool   $browser Browser-like headers.
	 * @param int    $hops    Retry / meta refresh depth.
	 * @return array|WP_Error Response.
	 */
	private static function download( $url, $tmp, $headers, $safe, $browser, $hops = 0 ) {
		$args = array(
			'timeout'             => 300,
			'stream'              => true,
			'filename'            => $tmp,
			'headers'             => $headers,
			'redirection'         => 10,
			'limit_response_size' => wp_max_upload_size(),
		);
		if ( $browser ) {
			$args['user-agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
			$args['headers']    = array_merge(
				array(
					'Accept'          => '*/*',
					'Accept-Language' => 'en-US,en;q=0.9',
				),
				$headers
			);
		}
		$res = $safe ? wp_safe_remote_get( $url, $args ) : wp_remote_get( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $browser && ! $hops && in_array( $code, array( 403, 406, 412 ), true ) ) {
			// Some firewalls refuse browser-like requests coming from servers: retry as a plain client with a referer.
			$parts = wp_parse_url( $url );
			return self::download( $url, $tmp, array_merge( $headers, array( 'Referer' => $parts['scheme'] . '://' . $parts['host'] . '/' ) ), $safe, false, 1 );
		}
		if ( $code < 200 || $code >= 300 ) {
			return self::http_error( $code );
		}
		clearstatcache();
		if ( 0 === strpos( strtolower( self::header( $res, 'content-type' ) ), 'text/html' ) && ! self::header( $res, 'content-disposition' ) ) {
			$html = filesize( $tmp ) < 1048576 ? (string) file_get_contents( $tmp ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( $hops < 3 && preg_match( '/<meta[^>]+http-equiv\s*=\s*["\']?refresh["\']?[^>]*content\s*=\s*["\']?\s*\d*\s*;?\s*url\s*=\s*([^"\'>\s]+)/i', $html, $m ) ) {
				$next = WP_Http::make_absolute_url( html_entity_decode( $m[1], ENT_QUOTES ), $url );
				if ( preg_match( '#^https?://#i', $next ) ) {
					return self::download( $next, $tmp, $headers, $safe, $browser, $hops + 1 );
				}
			}
			return new WP_Error( 'html', __( 'This address opens a web page, not a file. Use the direct download link of the file.', 'appstrdesign-foldpress' ) );
		}
		return $res;
	}

	/**
	 * Progress of running imports (bytes on disk / expected size).
	 */
	public function ajax_progress() {
		check_ajax_referer( FoldPress_Ajax::NONCE, 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage media.', 'appstrdesign-foldpress' ) ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ids = isset( $_POST['jobs'] ) ? (array) wp_unslash( $_POST['jobs'] ) : array();
		$out = array();
		clearstatcache();
		foreach ( array_slice( $ids, 0, 20 ) as $raw ) {
			$id = self::job_id( $raw );
			if ( ! $id ) {
				continue;
			}
			$t          = get_transient( self::job_key( $id ) );
			$out[ $id ] = is_array( $t ) ? array(
				'got'   => 'process' === $t['phase'] ? (int) $t['total'] : ( file_exists( $t['tmp'] ) ? (int) filesize( $t['tmp'] ) : 0 ),
				'total' => (int) $t['total'],
				'phase' => $t['phase'],
			) : null;
		}
		wp_send_json_success( (object) $out );
	}

	/**
	 * Stop a running server download.
	 */
	public function ajax_cancel() {
		check_ajax_referer( FoldPress_Ajax::NONCE, 'nonce' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = self::job_id( isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '' );
		$t  = $id && current_user_can( 'upload_files' ) ? get_transient( self::job_key( $id ) ) : false;
		if ( is_array( $t ) && 'download' === $t['phase'] && file_exists( $t['tmp'] ) ) {
			file_put_contents( $t['tmp'] . '.cancel', '1' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		wp_send_json_success();
	}

	/**
	 * Import selected files.
	 */
	public function ajax_import() {
		$this->guard();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$folder   = isset( $_POST['folder'] ) ? absint( $_POST['folder'] ) : 0;
		$token    = isset( $_POST['token'] ) ? preg_replace( '/[^A-Za-z0-9._\-~+\/=]/', '', wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$files    = isset( $_POST['files'] ) ? json_decode( wp_unslash( $_POST['files'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$job      = self::job_id( isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '' );
		// phpcs:enable
		if ( $folder && ! term_exists( $folder, FOLDPRESS_TAX ) ) {
			$folder = 0;
		}
		$cfg = self::client_config();
		if ( ! is_array( $files ) || ! $files || empty( $cfg[ 'dav' === $provider ? 'dav' : $provider ] ) ) {
			wp_send_json_error( array( 'message' => __( 'This cloud service is not enabled.', 'appstrdesign-foldpress' ) ) );
		}
		$files  = array_slice( $files, 0, self::MAX );
		$ok     = 0;
		$errors  = array();
		$results = array();
		$opt     = array( 'job' => 1 === count( $files ) ? $job : '' );
		foreach ( $files as $f ) {
			$name = isset( $f['name'] ) ? sanitize_file_name( (string) $f['name'] ) : '';
			$res  = new WP_Error( 'bad', __( 'Invalid request.', 'appstrdesign-foldpress' ) );
			if ( 'google' === $provider && ! empty( $f['id'] ) && $token ) {
				$id   = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $f['id'] );
				$mime = isset( $f['mime'] ) ? (string) $f['mime'] : '';
				if ( isset( self::GOOGLE_EXPORT[ $mime ] ) ) {
					list( $to, $ext ) = self::GOOGLE_EXPORT[ $mime ];
					$url              = 'https://www.googleapis.com/drive/v3/files/' . $id . '/export?mimeType=' . rawurlencode( $to );
					$name             = preg_replace( '/\.' . $ext . '$/i', '', $name ) . '.' . $ext;
				} elseif ( 0 === strpos( $mime, 'application/vnd.google-apps.' ) ) {
					$res = new WP_Error( 'gtype', __( 'This Google file type cannot be downloaded.', 'appstrdesign-foldpress' ) );
					$url = '';
				} else {
					$url = 'https://www.googleapis.com/drive/v3/files/' . $id . '?alt=media&supportsAllDrives=true';
				}
				if ( $url ) {
					$res = self::fetch( $url, $name, $folder, array( 'Authorization' => 'Bearer ' . $token ), true, $opt );
				}
			} elseif ( 'dropbox' === $provider && ! empty( $f['url'] ) && self::host_ok( (string) $f['url'], array( 'dropboxusercontent.com', 'dropbox.com' ) ) ) {
				$res = self::fetch( esc_url_raw( $f['url'] ), $name, $folder, array(), true, $opt );
			} elseif ( 'onedrive' === $provider && ! empty( $f['url'] ) && self::host_ok( (string) $f['url'], array( '1drv.com', 'sharepoint.com', 'onedrive.live.com', 'livefilestore.com', 'microsoftpersonalcontent.com', 'onedrive.com' ) ) ) {
				$res = self::fetch( esc_url_raw( $f['url'] ), $name, $folder, array(), true, $opt );
			} elseif ( 'dav' === $provider && isset( $f['path'] ) ) {
				$path = self::clean_dav_path( (string) $f['path'] );
				$res  = self::fetch( self::dav_url( $path ), $name ? $name : wp_basename( $path ), $folder, self::dav_headers(), false, $opt );
			} elseif ( 'url' === $provider && ! empty( $f['url'] ) && preg_match( '#^https?://#i', (string) $f['url'] ) ) {
				$res = self::fetch( esc_url_raw( trim( (string) $f['url'] ) ), $name, $folder, array(), true, array_merge( $opt, array( 'weak' => true, 'browser' => true ) ) );
			}
			if ( is_wp_error( $res ) ) {
				$errors[]  = ( $name ? $name : __( 'File', 'appstrdesign-foldpress' ) ) . ': ' . $res->get_error_message();
				$results[] = array(
					'ok'    => false,
					'name'  => $name,
					'error' => $res->get_error_message(),
				);
			} else {
				++$ok;
				$path      = get_attached_file( $res );
				$results[] = array(
					'ok'   => true,
					'id'   => (int) $res,
					'name' => $path ? wp_basename( $path ) : $name,
					'ext'  => $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '',
					'size' => $path && file_exists( $path ) ? (int) filesize( $path ) : 0,
				);
			}
		}
		wp_send_json_success(
			array(
				'imported' => $ok,
				'errors'   => $errors,
				'results'  => $results,
				/* translators: %d: number of files */
				'message'  => sprintf( _n( '%d file imported from the cloud.', '%d files imported from the cloud.', $ok, 'appstrdesign-foldpress' ), $ok ),
			)
		);
	}

	/* ===================== WebDAV ===================== */

	/**
	 * Strip traversal from a relative path.
	 *
	 * @param string $p Path.
	 * @return string
	 */
	private static function clean_dav_path( $p ) {
		$parts = array_filter( explode( '/', str_replace( '\\', '/', $p ) ), 'strlen' );
		$parts = array_filter(
			$parts,
			function ( $x ) {
				return '.' !== $x && '..' !== $x;
			}
		);
		return implode( '/', $parts );
	}

	/**
	 * Absolute DAV URL for a relative path.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	private static function dav_url( $path ) {
		$base = trailingslashit( (string) FoldPress_Settings::get( 'dav_url' ) );
		return $base . implode( '/', array_map( 'rawurlencode', array_filter( explode( '/', $path ), 'strlen' ) ) );
	}

	/**
	 * Auth headers.
	 *
	 * @return array
	 */
	private static function dav_headers() {
		$user = (string) FoldPress_Settings::get( 'dav_user' );
		$pass = (string) get_option( self::DAV_PASS, '' );
		return $user ? array( 'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ) ) : array(); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * PROPFIND listing.
	 *
	 * @param string $path Relative path.
	 * @return array|WP_Error
	 */
	private static function dav_list( $path ) {
		$path = self::clean_dav_path( $path );
		$url  = self::dav_url( $path );
		$url  = '' === $path ? $url : trailingslashit( $url );
		$res  = wp_remote_request(
			$url,
			array(
				'method'  => 'PROPFIND',
				'timeout' => 30,
				'headers' => array_merge(
					self::dav_headers(),
					array(
						'Depth'        => '1',
						'Content-Type' => 'application/xml; charset=utf-8',
					)
				),
				'body'    => '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname/><d:getcontentlength/><d:getcontenttype/><d:resourcetype/><d:getlastmodified/></d:prop></d:propfind>',
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'auth', __( 'The WebDAV server refused the user name or password.', 'appstrdesign-foldpress' ) );
		}
		if ( 207 !== $code ) {
			/* translators: %d: HTTP status */
			return new WP_Error( 'dav', sprintf( __( 'The WebDAV server answered with HTTP %d. Check the address.', 'appstrdesign-foldpress' ), $code ) );
		}
		$xml = (string) wp_remote_retrieve_body( $res );
		if ( false !== stripos( $xml, '<!DOCTYPE' ) ) {
			return new WP_Error( 'dav', __( 'Invalid WebDAV response.', 'appstrdesign-foldpress' ) );
		}
		$dom  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $dom->loadXML( $xml, LIBXML_NONET );
		libxml_use_internal_errors( $prev );
		if ( ! $ok ) {
			return new WP_Error( 'dav', __( 'Invalid WebDAV response.', 'appstrdesign-foldpress' ) );
		}
		$base_path = rtrim( (string) wp_parse_url( trailingslashit( (string) FoldPress_Settings::get( 'dav_url' ) ), PHP_URL_PATH ), '/' );
		$out       = array();
		foreach ( $dom->getElementsByTagNameNS( 'DAV:', 'response' ) as $r ) {
			$href = rawurldecode( (string) $r->getElementsByTagNameNS( 'DAV:', 'href' )->item( 0 )->textContent );
			$hp   = (string) wp_parse_url( $href, PHP_URL_PATH );
			$hp   = $hp ? $hp : $href;
			$rel  = trim( 0 === strpos( $hp, $base_path ) ? substr( $hp, strlen( $base_path ) ) : $hp, '/' );
			if ( $rel === $path ) {
				continue;
			}
			$is_dir = $r->getElementsByTagNameNS( 'DAV:', 'collection' )->length > 0;
			$len    = $r->getElementsByTagNameNS( 'DAV:', 'getcontentlength' )->item( 0 );
			$out[]  = array(
				'path' => $rel,
				'name' => wp_basename( $rel ),
				'dir'  => $is_dir,
				'size' => $len ? (int) $len->textContent : 0,
				'ext'  => $is_dir ? '' : strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) ),
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $a['dir'] === $b['dir'] ? strnatcasecmp( $a['name'], $b['name'] ) : ( $a['dir'] ? -1 : 1 );
			}
		);
		return $out;
	}

	/**
	 * List a WebDAV folder.
	 */
	public function ajax_dav_list() {
		$this->guard();
		$cfg = self::client_config();
		if ( empty( $cfg['dav'] ) ) {
			wp_send_json_error( array( 'message' => __( 'This cloud service is not enabled.', 'appstrdesign-foldpress' ) ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$list = self::dav_list( $path );
		if ( is_wp_error( $list ) ) {
			wp_send_json_error( array( 'message' => $list->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'path'  => self::clean_dav_path( $path ),
				'items' => $list,
			)
		);
	}

	/**
	 * Test the saved WebDAV connection (settings page).
	 */
	public function ajax_dav_test() {
		check_ajax_referer( FoldPress_Ajax::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change these settings.', 'appstrdesign-foldpress' ) ), 403 );
		}
		if ( ! FoldPress_Settings::get( 'dav_url' ) ) {
			wp_send_json_error( array( 'message' => __( 'Save a WebDAV address first.', 'appstrdesign-foldpress' ) ) );
		}
		$list = self::dav_list( '' );
		if ( is_wp_error( $list ) ) {
			wp_send_json_error( array( 'message' => $list->get_error_message() ) );
		}
		/* translators: %d: number of items */
		wp_send_json_success( array( 'message' => sprintf( __( 'Connected. %d items found in the root folder.', 'appstrdesign-foldpress' ), count( $list ) ) ) );
	}
}
