<?php
/**
 * FoldPress code editor: replaces the Theme / Plugin File Editors and edits text media files.
 * Theme and plugin saves go through core wp_edit_theme_plugin_file() so WordPress keeps its own
 * capability, nonce, extension and PHP fatal-error (loopback) checks.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Code
 */
class FoldPress_Code {

	const SLUG     = 'foldpress-code';
	const MAX_SIZE = 3145728; // 3 MB.

	/**
	 * Extra extensions made editable in themes / plugins (text only).
	 *
	 * @var string[]
	 */
	const EXTRA_EXT = array( 'java', 'cs', 'c', 'h', 'cpp', 'hpp', 'cc', 'py', 'rb', 'go', 'ts', 'tsx', 'vue', 'twig', 'kt', 'swift', 'rs', 'scss', 'less', 'sass', 'yml', 'yaml', 'json', 'md', 'ini', 'csv', 'mjs' );

	/**
	 * Text media types editable from the media library (never executable server-side).
	 *
	 * @var string[]
	 */
	const MEDIA_EXT = array( 'js', 'mjs', 'txt', 'md', 'markdown', 'csv', 'tsv', 'json', 'xml', 'css', 'scss', 'less', 'yaml', 'yml', 'ini', 'log', 'sql', 'srt', 'vtt', 'java', 'cs', 'c', 'cpp', 'h', 'py', 'rb', 'go', 'ts' );

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 100 );
		add_action( 'load-theme-editor.php', array( $this, 'redirect_theme' ) );
		add_action( 'load-plugin-editor.php', array( $this, 'redirect_plugin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'parent_file', array( $this, 'parent_file' ) );
		add_filter( 'submenu_file', array( $this, 'submenu_file' ) );
		add_filter( 'wp_theme_editor_filetypes', array( $this, 'theme_types' ) );
		add_filter( 'editable_extensions', array( $this, 'plugin_types' ) );
		foreach ( array( 'code_tree', 'code_read', 'code_save' ) as $a ) {
			add_action( 'wp_ajax_foldpress_' . $a, array( $this, 'ajax_' . $a ) );
		}
	}

	/**
	 * Editor URL.
	 *
	 * @param string $type   theme|plugin|media.
	 * @param string $target Stylesheet, plugin file or attachment id.
	 * @param string $file   Relative file.
	 * @return string
	 */
	public static function url( $type, $target = '', $file = '' ) {
		return add_query_arg( array_filter( array( 'page' => self::SLUG, 'type' => $type, 'target' => $target, 'file' => $file ), 'strlen' ), admin_url( 'admin.php' ) );
	}

	/**
	 * Hidden page (no menu item of its own; core menu entries redirect here).
	 */
	public function menu() {
		add_submenu_page( 'options.php', __( 'Code Editor', 'appstrdesign-foldpress' ), __( 'Code Editor', 'appstrdesign-foldpress' ), 'read', self::SLUG, array( $this, 'render' ) );
	}

	/**
	 * Request value.
	 *
	 * @param string $k Key.
	 * @return string
	 */
	private static function req( $k ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- routing / verified by callers.
		return isset( $_REQUEST[ $k ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $k ] ) ) : '';
	}

	/**
	 * Active?
	 *
	 * @return bool
	 */
	private function enabled() {
		return FoldPress_Settings::get( 'replace_code_editor' ) && ! self::req( 'foldpress_classic' );
	}

	/**
	 * theme-editor.php -> FoldPress.
	 */
	public function redirect_theme() {
		if ( $this->enabled() && current_user_can( 'edit_themes' ) ) {
			wp_safe_redirect( self::url( 'theme', self::req( 'theme' ) ? self::req( 'theme' ) : get_stylesheet(), self::req( 'file' ) ) );
			exit;
		}
	}

	/**
	 * plugin-editor.php -> FoldPress.
	 */
	public function redirect_plugin() {
		if ( $this->enabled() && current_user_can( 'edit_plugins' ) ) {
			wp_safe_redirect( self::url( 'plugin', self::req( 'plugin' ), self::req( 'file' ) ) );
			exit;
		}
	}

	/**
	 * Current screen is ours.
	 *
	 * @return bool
	 */
	private function is_screen() {
		return is_admin() && self::SLUG === self::req( 'page' );
	}

	/**
	 * Menu highlight.
	 *
	 * @param string $f Parent file.
	 * @return string
	 */
	public function parent_file( $f ) {
		if ( ! $this->is_screen() ) {
			return $f;
		}
		$t = self::req( 'type' );
		return 'plugin' === $t ? 'plugins.php' : ( 'media' === $t ? 'upload.php' : 'themes.php' );
	}

	/**
	 * Submenu highlight.
	 *
	 * @param string|null $f Submenu file.
	 * @return string|null
	 */
	public function submenu_file( $f ) {
		if ( ! $this->is_screen() ) {
			return $f;
		}
		$t = self::req( 'type' );
		return 'plugin' === $t ? 'plugin-editor.php' : ( 'media' === $t ? FoldPress_Admin::SLUG : 'theme-editor.php' );
	}

	/**
	 * Extra theme file types.
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public function theme_types( $types ) {
		return array_values( array_unique( array_merge( (array) $types, self::EXTRA_EXT ) ) );
	}

	/**
	 * Extra plugin file types.
	 *
	 * @param array $types Types.
	 * @return array
	 */
	public function plugin_types( $types ) {
		return array_values( array_unique( array_merge( (array) $types, self::EXTRA_EXT ) ) );
	}

	/**
	 * Capability for a scope.
	 *
	 * @param string $type   Type.
	 * @param string $target Target.
	 * @return bool
	 */
	private static function can( $type, $target ) {
		if ( 'theme' === $type ) {
			return current_user_can( 'edit_themes' );
		}
		if ( 'plugin' === $type ) {
			return current_user_can( 'edit_plugins' );
		}
		return 'media' === $type && current_user_can( 'upload_files' ) && current_user_can( 'edit_post', (int) $target );
	}

	/**
	 * Editable files: relative => absolute.
	 *
	 * @param string $type   Type.
	 * @param string $target Target.
	 * @return array|WP_Error
	 */
	private static function files( $type, $target ) {
		$out = array();
		if ( 'theme' === $type ) {
			$theme = wp_get_theme( $target );
			if ( ! $theme->exists() ) {
				return new WP_Error( 'notheme', __( 'Theme not found.', 'appstrdesign-foldpress' ) );
			}
			$files = $theme->get_files( wp_get_theme_file_editable_extensions( $theme ), -1 );
			foreach ( $files as $rel => $abs ) {
				$out[ $rel ] = $abs;
			}
			$out['style.css'] = $theme->get_stylesheet_directory() . '/style.css';
		} elseif ( 'plugin' === $type ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$plugins = get_plugins();
			if ( ! isset( $plugins[ $target ] ) ) {
				return new WP_Error( 'noplugin', __( 'Plugin not found.', 'appstrdesign-foldpress' ) );
			}
			$exts = wp_get_plugin_file_editable_extensions( $target );
			foreach ( get_plugin_files( $target ) as $rel ) {
				if ( in_array( strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) ), $exts, true ) ) {
					$out[ $rel ] = WP_PLUGIN_DIR . '/' . $rel;
				}
			}
		} elseif ( 'media' === $type ) {
			$id   = (int) $target;
			$path = get_attached_file( $id );
			if ( 'attachment' !== get_post_type( $id ) || ! $path || ! in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), self::MEDIA_EXT, true ) ) {
				return new WP_Error( 'nomedia', __( 'This file type cannot be edited as code.', 'appstrdesign-foldpress' ) );
			}
			$out[ wp_basename( $path ) ] = $path;
		}
		foreach ( array_keys( $out ) as $rel ) {
			if ( preg_match( '#(^|/)(node_modules|\.git|\.svn)/#', $rel ) || ! is_file( $out[ $rel ] ) ) {
				unset( $out[ $rel ] );
			}
		}
		ksort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		return $out;
	}

	/**
	 * Guard + scope + file lookup.
	 *
	 * @param bool $need_file Require a file.
	 * @return array{type:string,target:string,file:string,abs:string,files:array}
	 */
	private function scope( $need_file = true ) {
		check_ajax_referer( FoldPress_Ajax::NONCE, 'nonce' );
		$type   = self::req( 'type' );
		$target = self::req( 'target' );
		if ( ! in_array( $type, array( 'theme', 'plugin', 'media' ), true ) || ! self::can( $type, $target ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit these files.', 'appstrdesign-foldpress' ) ), 403 );
		}
		$files = self::files( $type, $target );
		if ( is_wp_error( $files ) ) {
			wp_send_json_error( array( 'message' => $files->get_error_message() ) );
		}
		$file = self::req( 'file' );
		$abs  = '';
		if ( $need_file ) {
			if ( ! isset( $files[ $file ] ) ) {
				wp_send_json_error( array( 'message' => __( 'File not found or not editable.', 'appstrdesign-foldpress' ) ) );
			}
			$abs = $files[ $file ];
		}
		return compact( 'type', 'target', 'file', 'abs', 'files' );
	}

	/**
	 * Core nonce action for a file.
	 *
	 * @param array $s Scope.
	 * @return string
	 */
	private static function core_nonce_action( $s ) {
		return 'theme' === $s['type'] ? 'edit-theme_' . $s['target'] . '_' . $s['file'] : 'edit-plugin_' . $s['file'];
	}

	/**
	 * File tree.
	 */
	public function ajax_code_tree() {
		$s     = $this->scope( false );
		$base  = 'plugin' === $s['type'] && false !== strpos( $s['target'], '/' ) ? dirname( $s['target'] ) . '/' : '';
		$list  = array();
		foreach ( $s['files'] as $rel => $abs ) {
			$list[] = array(
				'file'     => $rel,
				'path'     => $base && 0 === strpos( $rel, $base ) ? substr( $rel, strlen( $base ) ) : $rel,
				'ext'      => strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) ),
				'size'     => (int) filesize( $abs ),
				'writable' => wp_is_writable( $abs ),
			);
		}
		$main = '';
		if ( 'theme' === $s['type'] ) {
			$main = isset( $s['files']['style.css'] ) ? 'style.css' : '';
			if ( isset( $s['files']['functions.php'] ) ) {
				$main = 'style.css';
			}
		} elseif ( 'plugin' === $s['type'] ) {
			$main = $s['target'];
		} else {
			$main = key( $s['files'] );
		}
		wp_send_json_success(
			array(
				'files' => $list,
				'main'  => $main,
			)
		);
	}

	/**
	 * Read a file.
	 */
	public function ajax_code_read() {
		$s = $this->scope();
		if ( filesize( $s['abs'] ) > self::MAX_SIZE ) {
			wp_send_json_error( array( 'message' => __( 'This file is too large to edit in the browser (max 3 MB).', 'appstrdesign-foldpress' ) ) );
		}
		$content = (string) file_get_contents( $s['abs'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false !== strpos( $content, "\0" ) ) {
			wp_send_json_error( array( 'message' => __( 'Binary files cannot be edited.', 'appstrdesign-foldpress' ) ) );
		}
		$ext       = strtolower( pathinfo( $s['abs'], PATHINFO_EXTENSION ) );
		$functions = array();
		if ( in_array( $ext, array( 'php', 'phtml', 'inc' ), true ) && function_exists( 'wp_doc_link_parse' ) ) {
			$functions = array_values( (array) wp_doc_link_parse( $content ) );
		}
		wp_send_json_success(
			array(
				'content'   => $content,
				'hash'      => md5( $content ),
				'writable'  => wp_is_writable( $s['abs'] ),
				'size'      => strlen( $content ),
				'modified'  => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), filemtime( $s['abs'] ) ),
				'functions' => $functions,
				'coreNonce' => 'media' === $s['type'] ? '' : wp_create_nonce( self::core_nonce_action( $s ) ),
			)
		);
	}

	/**
	 * Save a file.
	 */
	public function ajax_code_save() {
		$s = $this->scope();
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in scope(); file contents are code and must stay byte-exact, exactly like core's file editor.
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : null;
		$hash    = isset( $_POST['hash'] ) ? sanitize_text_field( wp_unslash( $_POST['hash'] ) ) : '';
		$core    = isset( $_POST['coreNonce'] ) ? sanitize_text_field( wp_unslash( $_POST['coreNonce'] ) ) : '';
		$force   = ! empty( $_POST['force'] );
		// phpcs:enable
		if ( null === $content ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'appstrdesign-foldpress' ) ) );
		}
		$current = (string) file_get_contents( $s['abs'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $force && $hash && md5( $current ) !== $hash ) {
			wp_send_json_error(
				array(
					'code'    => 'conflict',
					'message' => __( 'This file was changed on the server after you opened it (by another user, an update or FTP).', 'appstrdesign-foldpress' ),
				)
			);
		}
		if ( ! wp_is_writable( $s['abs'] ) ) {
			wp_send_json_error(
				array(
					'code'    => 'not_writable',
					'message' => __( 'The file is not writable. Make it writable on the server (file permissions 644 or 664) before saving.', 'appstrdesign-foldpress' ),
				)
			);
		}

		$ext     = strtolower( pathinfo( $s['abs'], PATHINFO_EXTENSION ) );
		$is_php  = in_array( $ext, array( 'php', 'phtml', 'inc', 'php5', 'php7', 'php8' ), true );
		$warning = '';
		if ( $is_php ) {
			$lint = self::lint_php( $content );
			if ( is_wp_error( $lint ) ) {
				wp_send_json_error(
					array(
						'code'    => 'php_syntax',
						'message' => $lint->get_error_message(),
						'line'    => (int) $lint->get_error_data(),
					)
				);
			}
		}

		if ( 'media' === $s['type'] ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			global $wp_filesystem;
			if ( ! WP_Filesystem() || ! $wp_filesystem->put_contents( $s['abs'], $content, FS_CHMOD_FILE ) ) {
				wp_send_json_error( array( 'message' => __( 'Could not write the file.', 'appstrdesign-foldpress' ) ) );
			}
			clean_post_cache( (int) $s['target'] );
		} else {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$args = array(
				'file'       => $s['file'],
				'newcontent' => $content,
				'nonce'      => $core,
			);
			if ( 'theme' === $s['type'] ) {
				$args['theme'] = $s['target'];
			} else {
				$args['plugin'] = $s['target'];
			}
			$r = wp_edit_theme_plugin_file( $args );
			// Many hosts block the site from calling itself (loopback), so core refuses every PHP save.
			// The code already passed a full PHP syntax check above: write it directly and tell the user.
			// Core wraps the loopback failure as 'php_error' with the real reason in the error data ('code').
			$loop_codes = array( 'loopback_request_failed', 'json_parse_error', 'scrape_failed', 'unable_to_connect' );
			$err_data   = is_wp_error( $r ) ? $r->get_error_data() : null;
			$sub_code   = is_array( $err_data ) && isset( $err_data['code'] ) ? (string) $err_data['code'] : '';
			$loop_fail  = is_wp_error( $r ) && ( in_array( $r->get_error_code(), $loop_codes, true ) || in_array( $sub_code, $loop_codes, true ) );
			if ( $loop_fail && $is_php ) {
				global $wp_filesystem;
				if ( WP_Filesystem() && $wp_filesystem->put_contents( $s['abs'], $content, FS_CHMOD_FILE ) ) {
					if ( function_exists( 'opcache_invalidate' ) ) {
						opcache_invalidate( $s['abs'], true );
					}
					$r       = true;
					$warning = __( 'Saved after a full PHP syntax check. Your server does not allow WordPress to call itself (loopback), so the extra runtime check was skipped. If the site shows an error, undo the change via FTP or the file manager of your hosting panel.', 'appstrdesign-foldpress' );
				}
			}
			if ( is_wp_error( $r ) ) {
				$data    = $r->get_error_data();
				$line    = 0;
				$message = wp_strip_all_tags( $r->get_error_message() );
				$code    = $r->get_error_code();
				if ( $loop_fail ) {
					$code    = 'loopback_failed';
					$message = __( 'WordPress could not reach your site to test the change, and the file could not be written directly. Nothing was changed. Check the file permissions or save the file via FTP.', 'appstrdesign-foldpress' );
				} elseif ( 'php_error' === $code && is_array( $data ) && isset( $data['line'] ) ) {
					// A real runtime fatal error found by the loopback test: core already rolled the file back.
					$same = empty( $data['file'] ) || wp_normalize_path( (string) $data['file'] ) === wp_normalize_path( $s['abs'] );
					$line = $same ? (int) $data['line'] : 0;
					$code = 'php_fatal';
					/* translators: 1: PHP error message, 2: file name, 3: line number */
					$message = sprintf( __( 'WordPress tested the change and it causes a fatal error, so the file was restored: %1$s (%2$s, line %3$d).', 'appstrdesign-foldpress' ), $message, wp_basename( isset( $data['file'] ) ? (string) $data['file'] : $s['abs'] ), (int) $data['line'] );
				} elseif ( preg_match( '/line (\d+)/i', $message, $m ) ) {
					$line = (int) $m[1];
				}
				wp_send_json_error(
					array(
						'code'    => $code,
						'message' => $message,
						'line'    => $line,
					)
				);
			}
		}
		clearstatcache();
		wp_send_json_success(
			array(
				'hash'     => md5( (string) file_get_contents( $s['abs'] ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'modified' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), filemtime( $s['abs'] ) ),
				'message'  => __( 'File saved successfully.', 'appstrdesign-foldpress' ),
				'warning'  => $warning,
			)
		);
	}

	/**
	 * PHP syntax check without executing the code (PHP 7+ ParseError via the tokenizer).
	 *
	 * @param string $code Code.
	 * @return true|WP_Error Error data holds the line number.
	 */
	private static function lint_php( $code ) {
		if ( ! function_exists( 'token_get_all' ) || ! defined( 'TOKEN_PARSE' ) ) {
			return true;
		}
		try {
			token_get_all( $code, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			/* translators: 1: PHP error message, 2: line number */
			return new WP_Error( 'php_syntax', sprintf( __( 'PHP syntax error: %1$s on line %2$d. Nothing was saved.', 'appstrdesign-foldpress' ), $e->getMessage(), $e->getLine() ), $e->getLine() );
		} catch ( \Throwable $e ) {
			return true;
		}
		return true;
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
		// Core CodeMirror bundle (modes + addons) and linters.
		wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		wp_enqueue_script( 'wp-codemirror' );
		wp_enqueue_style( 'wp-codemirror' );
		foreach ( array( 'csslint', 'jshint', 'jsonlint', 'htmlhint' ) as $h ) {
			if ( wp_script_is( $h, 'registered' ) ) {
				wp_enqueue_script( $h );
			}
		}
		wp_enqueue_style( 'foldpress-code', FOLDPRESS_URL . 'assets/css/foldpress-code.css', array( 'foldpress', 'wp-codemirror' ), FOLDPRESS_VERSION );
		wp_enqueue_script( 'foldpress-code', FOLDPRESS_URL . 'assets/js/foldpress-code.js', array( 'foldpress-ui', 'wp-codemirror' ), FOLDPRESS_VERSION, true );

		$themes  = array();
		$plugins = array();
		if ( current_user_can( 'edit_themes' ) ) {
			foreach ( wp_get_themes() as $slug => $t ) {
				$themes[] = array(
					'value'  => $slug,
					'label'  => $t->get( 'Name' ),
					'active' => get_stylesheet() === $slug,
				);
			}
		}
		if ( current_user_can( 'edit_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			foreach ( get_plugins() as $file => $p ) {
				$plugins[] = array(
					'value'  => $file,
					'label'  => $p['Name'],
					'active' => is_plugin_active( $file ),
				);
			}
		}
		$type   = self::req( 'type' ) ? self::req( 'type' ) : ( $themes ? 'theme' : 'plugin' );
		$target = self::req( 'target' );
		if ( ! $target && 'theme' === $type ) {
			$target = get_stylesheet();
		} elseif ( ! $target && 'plugin' === $type && $plugins ) {
			$target = $plugins[0]['value'];
		}
		$media_title = '';
		if ( 'media' === $type ) {
			$media_title = get_the_title( (int) $target );
		}
		wp_localize_script(
			'foldpress-code',
			'FoldPressCode',
			array(
				'type'       => $type,
				'target'     => $target,
				'file'       => self::req( 'file' ),
				'themes'     => $themes,
				'plugins'    => $plugins,
				'mediaTitle' => $media_title,
				'libraryUrl' => FoldPress_Admin::url(),
				'classicUrl' => add_query_arg( 'foldpress_classic', 1, admin_url( 'plugin' === $type ? 'plugin-editor.php' : 'theme-editor.php' ) ),
				'docsUrl'    => add_query_arg(
					array(
						'locale'   => get_user_locale(),
						'version'  => get_bloginfo( 'version' ),
						'redirect' => 'true',
					),
					'https://api.wordpress.org/core/handbook/1.0/'
				),
				'phpDocs'    => 'https://www.php.net/manual/' . ( 0 === strpos( get_user_locale(), 'tr' ) ? 'tr' : 'en' ) . '/function.',
				'disabled'   => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
				'i18n'       => array(
					'title'          => __( 'Code Editor', 'appstrdesign-foldpress' ),
					'theme'          => __( 'Theme', 'appstrdesign-foldpress' ),
					'plugin'         => __( 'Plugin', 'appstrdesign-foldpress' ),
					'media'          => __( 'Media file', 'appstrdesign-foldpress' ),
					'active'         => __( 'active', 'appstrdesign-foldpress' ),
					'files'          => __( 'Files', 'appstrdesign-foldpress' ),
					'filterFiles'    => __( 'Filter files...', 'appstrdesign-foldpress' ),
					'noFiles'        => __( 'No editable files found.', 'appstrdesign-foldpress' ),
					'pickFile'       => __( 'Select a file from the tree to start editing.', 'appstrdesign-foldpress' ),
					'save'           => __( 'Save', 'appstrdesign-foldpress' ),
					'saving'         => __( 'Saving...', 'appstrdesign-foldpress' ),
					'saved'          => __( 'File saved', 'appstrdesign-foldpress' ),
					'savedBody'      => __( 'Your changes were written to the file.', 'appstrdesign-foldpress' ),
					'saveError'      => __( 'The file could not be saved', 'appstrdesign-foldpress' ),
					'phpError'       => __( 'Your PHP code changes were rolled back due to an error', 'appstrdesign-foldpress' ),
					'phpErrorBody'   => __( 'WordPress tested the change and found a fatal error, so the previous version was kept. Fix the problem and save again.', 'appstrdesign-foldpress' ),
					'goToLine'       => __( 'Go to line', 'appstrdesign-foldpress' ),
					'lineNumber'     => __( 'Line number', 'appstrdesign-foldpress' ),
					'conflict'       => __( 'File changed on the server', 'appstrdesign-foldpress' ),
					'overwrite'      => __( 'Overwrite with my version', 'appstrdesign-foldpress' ),
					'reload'         => __( 'Load server version', 'appstrdesign-foldpress' ),
					'notWritable'    => __( 'File is not writable', 'appstrdesign-foldpress' ),
					'readOnly'       => __( 'Read only: this file is not writable on the server. You can view it, but changes cannot be saved until its permissions are changed.', 'appstrdesign-foldpress' ),
					'permHelp'       => __( 'Learn about file permissions', 'appstrdesign-foldpress' ),
					'unsavedTitle'   => __( 'Unsaved changes', 'appstrdesign-foldpress' ),
					'unsavedClose'   => __( 'This file has unsaved changes. Close it anyway?', 'appstrdesign-foldpress' ),
					'unsavedSwitch'  => __( 'Some open files have unsaved changes. Switch anyway? The changes will be lost.', 'appstrdesign-foldpress' ),
					'closeAnyway'    => __( 'Close without saving', 'appstrdesign-foldpress' ),
					'switchAnyway'   => __( 'Switch and discard', 'appstrdesign-foldpress' ),
					'leave'          => __( 'You have unsaved changes.', 'appstrdesign-foldpress' ),
					'docs'           => __( 'Documentation', 'appstrdesign-foldpress' ),
					'fnName'         => __( 'Function name...', 'appstrdesign-foldpress' ),
					'lookUp'         => __( 'Look up', 'appstrdesign-foldpress' ),
					'lookUpHint'     => __( 'Choose a function or place the cursor on a function name.', 'appstrdesign-foldpress' ),
					'find'           => __( 'Find', 'appstrdesign-foldpress' ),
					'replace'        => __( 'Replace', 'appstrdesign-foldpress' ),
					'replaceAll'     => __( 'Replace all', 'appstrdesign-foldpress' ),
					'matchCase'      => __( 'Match case', 'appstrdesign-foldpress' ),
					'noMatches'      => __( 'No results', 'appstrdesign-foldpress' ),
					'replaced'       => __( '%d replacements made.', 'appstrdesign-foldpress' ),
					'wrap'           => __( 'Word wrap', 'appstrdesign-foldpress' ),
					'darkTheme'      => __( 'Dark theme', 'appstrdesign-foldpress' ),
					'fontSmaller'    => __( 'Smaller text', 'appstrdesign-foldpress' ),
					'fontLarger'     => __( 'Larger text', 'appstrdesign-foldpress' ),
					'line'           => __( 'Ln', 'appstrdesign-foldpress' ),
					'col'            => __( 'Col', 'appstrdesign-foldpress' ),
					'lines'          => __( 'lines', 'appstrdesign-foldpress' ),
					'selected'       => __( 'selected', 'appstrdesign-foldpress' ),
					'tabs'           => __( 'Tabs', 'appstrdesign-foldpress' ),
					'writable'       => __( 'Writable', 'appstrdesign-foldpress' ),
					'readOnlyShort'  => __( 'Read only', 'appstrdesign-foldpress' ),
					'modified'       => __( 'Modified', 'appstrdesign-foldpress' ),
					'close'          => __( 'Close', 'appstrdesign-foldpress' ),
					'classic'        => __( 'Classic editor', 'appstrdesign-foldpress' ),
					'library'        => __( 'Back to library', 'appstrdesign-foldpress' ),
					'disabled'       => __( 'File editing is disabled on this site (DISALLOW_FILE_EDIT).', 'appstrdesign-foldpress' ),
					'shortcuts'      => __( 'Ctrl+S save · Ctrl+F find · Ctrl+H replace · Ctrl+G go to line · Ctrl+/ comment', 'appstrdesign-foldpress' ),
					'activeTheme'    => __( 'Active theme', 'appstrdesign-foldpress' ),
					'activePlugin'   => __( 'Active plugin', 'appstrdesign-foldpress' ),
					'loading'        => __( 'Loading...', 'appstrdesign-foldpress' ),
					'fullscreen'     => __( 'Full screen', 'appstrdesign-foldpress' ),
				),
			)
		);
	}

	/**
	 * Shell.
	 */
	public function render() {
		if ( ! current_user_can( 'edit_themes' ) && ! current_user_can( 'edit_plugins' ) && ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You are not allowed to edit these files.', 'appstrdesign-foldpress' ) );
		}
		echo '<div class="wrap foldpress-wrap afp-code-wrap"><h1 class="screen-reader-text">' . esc_html__( 'Code Editor', 'appstrdesign-foldpress' ) . '</h1><div id="foldpress-code" class="afp-app afp-code"><div class="afp-boot"><span class="afp-spin"></span></div></div></div>';
	}
}
