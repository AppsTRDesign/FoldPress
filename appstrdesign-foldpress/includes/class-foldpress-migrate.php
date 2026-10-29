<?php
/**
 * Folder import / export and migration from other media folder plugins.
 *
 * Sources (read straight from the database, the other plugin does not need to be active):
 * FileBird (tables + legacy taxonomy), Real Media Library, WordPress Media Library Folders and every
 * taxonomy based plugin (HappyFiles, Folders by Premio, WP Media Folder, Enhanced Media Library...).
 * Files: FoldPress JSON / CSV and FileBird CSV, plus tolerant generic JSON / CSV.
 * Targets: FoldPress itself, the same taxonomies and FileBird tables, so other plugins see FoldPress folders.
 *
 * Every job is normalised into a plan { folders: [ [key, name, parent_key] ], items: [ [attachment_id, folder_key] ] }
 * kept in a per-user transient and written in chunks.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Migrate
 */
class FoldPress_Migrate {

	const FORMAT = 'foldpress-folders';
	const CHUNK  = 400;

	/**
	 * Taxonomy based plugins: key => [ name, author, taxonomy ].
	 *
	 * @var array
	 */
	const TAXONOMIES = array(
		'happyfiles'  => array( 'HappyFiles', 'Codeer', 'happyfiles_category' ),
		'premio'      => array( 'Folders', 'Premio', 'media_folder' ),
		'wpmf'        => array( 'WP Media Folder', 'JoomUnited', 'wpmf-category' ),
		'enhanced'    => array( 'Enhanced Media Library', 'wpUXsolutions', 'media_category' ),
		'feml'        => array( 'WP Media Folders', 'Damien Barrère', 'feml-folder' ),
		'wicked'      => array( 'Wicked Folders', 'Wicked Plugins', 'wf_attachment_folders' ),
		'mla'         => array( 'Media Library Assistant', 'David Lingren', 'attachment_category' ),
		'mediamatic'  => array( 'Mediamatic', 'Plugincraft', 'mediamatic_wpfolder' ),
		'filebird_v3' => array( 'FileBird (3.x)', 'Ninja Team', 'nt_wmc_folder' ),
	);

	/**
	 * Hooks.
	 */
	public function __construct() {
		foreach ( array( 'scan', 'prepare', 'run' ) as $a ) {
			add_action( 'wp_ajax_foldpress_migrate_' . $a, array( $this, 'ajax_' . $a ) );
		}
		add_action( 'admin_post_foldpress_export', array( $this, 'download' ) );
	}

	/**
	 * Admin only.
	 */
	private function guard() {
		check_ajax_referer( FoldPress_Ajax::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change these settings.', 'appstrdesign-foldpress' ) ), 403 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/* ===================== Detection ===================== */

	/**
	 * Does a table exist.
	 *
	 * @param string $name Table name without prefix.
	 * @return bool
	 */
	private static function table( $name ) {
		global $wpdb;
		$t = $wpdb->prefix . $name;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) );
	}

	/**
	 * Every known source with folder / file counts. Only sources with data are returned unless $all.
	 *
	 * @param bool $all Include empty ones.
	 * @return array
	 */
	public static function sources( $all = false ) {
		global $wpdb;
		$out = array();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( self::table( 'fbv' ) && self::table( 'fbv_attachment_folder' ) ) {
			$out['filebird'] = array(
				'name'    => 'FileBird',
				'author'  => 'Ninja Team',
				'folders' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fbv" ),
				'files'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT attachment_id) FROM {$wpdb->prefix}fbv_attachment_folder" ),
			);
		}
		if ( self::table( 'realmedialibrary' ) && self::table( 'realmedialibrary_posts' ) ) {
			$out['realmedia'] = array(
				'name'    => 'Real Media Library',
				'author'  => 'devowl.io',
				'folders' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}realmedialibrary" ),
				'files'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT attachment) FROM {$wpdb->prefix}realmedialibrary_posts" ),
			);
		}
		if ( self::table( 'mgmlp_folders' ) ) {
			$out['wpmlf'] = array(
				'name'    => 'Media Library Folders',
				'author'  => 'Max Foundry',
				'folders' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'mgmlp_media_folder' ) ),
				'files'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mgmlp_folders f INNER JOIN {$wpdb->posts} p ON p.ID = f.post_id WHERE p.post_type = %s", 'attachment' ) ),
			);
		}
		foreach ( self::TAXONOMIES as $key => $t ) {
			$folders = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $t[2] ) );
			if ( ! $folders && ! $all ) {
				continue;
			}
			$out[ $key ] = array(
				'name'    => $t[0],
				'author'  => $t[1],
				'folders' => $folders,
				'files'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type = 'attachment'", $t[2] ) ),
			);
		}
		// phpcs:enable
		return $all ? $out : array_filter(
			$out,
			function ( $s ) {
				return $s['folders'] > 0;
			}
		);
	}

	/**
	 * Plugins FoldPress can write its folders into.
	 *
	 * @return array key => name
	 */
	public static function targets() {
		$out = array();
		foreach ( self::TAXONOMIES as $key => $t ) {
			if ( 'filebird_v3' !== $key ) {
				$out[ $key ] = $t[0];
			}
		}
		if ( self::table( 'fbv' ) && self::table( 'fbv_attachment_folder' ) ) {
			$out = array( 'filebird' => 'FileBird' ) + $out;
		}
		return $out;
	}

	/* ===================== Reading sources ===================== */

	/**
	 * Read another plugin's folders into a plan.
	 *
	 * @param string $key Source key.
	 * @return array|WP_Error
	 */
	private static function read_source( $key ) {
		global $wpdb;
		$folders = array();
		$items   = array();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 'filebird' === $key ) {
			foreach ( $wpdb->get_results( "SELECT id, name, parent FROM {$wpdb->prefix}fbv ORDER BY parent, ord, id" ) as $r ) {
				$folders[] = array( (string) $r->id, $r->name, (string) (int) $r->parent );
			}
			foreach ( $wpdb->get_results( "SELECT attachment_id AS a, folder_id AS f FROM {$wpdb->prefix}fbv_attachment_folder" ) as $r ) {
				$items[] = array( (int) $r->a, (string) $r->f );
			}
		} elseif ( 'realmedia' === $key ) {
			foreach ( $wpdb->get_results( "SELECT id, name, parent FROM {$wpdb->prefix}realmedialibrary ORDER BY parent, id" ) as $r ) {
				$folders[] = array( (string) $r->id, $r->name, (string) max( 0, (int) $r->parent ) );
			}
			$cols     = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}realmedialibrary_posts" );
			$shortcut = in_array( 'isShortcut', (array) $cols, true ) ? ' WHERE isShortcut = 0' : '';
			foreach ( $wpdb->get_results( "SELECT attachment AS a, fid AS f FROM {$wpdb->prefix}realmedialibrary_posts{$shortcut}" ) as $r ) {
				$items[] = array( (int) $r->a, (string) $r->f );
			}
		} elseif ( 'wpmlf' === $key ) {
			// The uploads directory itself is the root folder; its children become top level folders.
			$root = (int) get_option( 'mgmlp_upload_folder_id' );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID AS id, p.post_title AS name, f.folder_id AS parent FROM {$wpdb->posts} p INNER JOIN {$wpdb->prefix}mgmlp_folders f ON f.post_id = p.ID WHERE p.post_type = %s ORDER BY f.folder_id, p.ID", 'mgmlp_media_folder' ) );
			foreach ( $rows as $r ) {
				if ( (int) $r->id === $root ) {
					continue;
				}
				$folders[] = array( (string) $r->id, $r->name, (string) ( (int) $r->parent === $root ? 0 : (int) $r->parent ) );
			}
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT f.post_id AS a, f.folder_id AS f FROM {$wpdb->prefix}mgmlp_folders f INNER JOIN {$wpdb->posts} p ON p.ID = f.post_id WHERE p.post_type = %s", 'attachment' ) ) as $r ) {
				if ( (int) $r->f !== $root ) {
					$items[] = array( (int) $r->a, (string) $r->f );
				}
			}
		} elseif ( isset( self::TAXONOMIES[ $key ] ) ) {
			$tax  = self::TAXONOMIES[ $key ][2];
			$root = 'wpmf' === $key ? (int) get_option( 'wpmf_folder_root_id' ) : 0;
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id AS id, t.name, tt.parent FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s ORDER BY tt.parent, t.name", $tax ) );
			foreach ( (array) $rows as $r ) {
				if ( $root && (int) $r->id === $root ) {
					continue;
				}
				$folders[] = array( (string) $r->id, $r->name, (string) ( $root && (int) $r->parent === $root ? 0 : (int) $r->parent ) );
			}
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT tr.object_id AS a, tt.term_id AS f FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type = 'attachment' ORDER BY tr.object_id, tr.term_order", $tax ) ) as $r ) {
				$items[] = array( (int) $r->a, (string) $r->f );
			}
		} else {
			return new WP_Error( 'source', __( 'Unknown source.', 'appstrdesign-foldpress' ) );
		}
		// phpcs:enable
		$name = 'filebird' === $key ? 'FileBird' : ( 'realmedia' === $key ? 'Real Media Library' : ( 'wpmlf' === $key ? 'Media Library Folders' : self::TAXONOMIES[ $key ][0] ) );
		return self::plan( $name, $folders, $items );
	}

	/**
	 * Current FoldPress folders as a plan (used for exports and for writing into other plugins).
	 *
	 * @return array
	 */
	private static function read_own() {
		global $wpdb;
		$folders = array();
		foreach ( FoldPress_Files::folders() as $f ) {
			$folders[] = array( (string) $f['id'], $f['name'], (string) $f['parent'] );
		}
		$items = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT tr.object_id AS a, tt.term_id AS f FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type = 'attachment'", FOLDPRESS_TAX ) );
		foreach ( $rows as $r ) {
			$items[] = array( (int) $r->a, (string) $r->f );
		}
		return self::plan( 'FoldPress', $folders, $items );
	}

	/**
	 * Normalise: decode names, drop unknown parents, sort parents first, one folder per file.
	 *
	 * @param string $label   Source name.
	 * @param array  $folders [ key, name, parent_key ].
	 * @param array  $items   [ attachment_id, folder_key ].
	 * @return array
	 */
	private static function plan( $label, $folders, $items ) {
		$byk = array();
		foreach ( $folders as $f ) {
			$name = trim( wp_strip_all_tags( html_entity_decode( (string) $f[1], ENT_QUOTES, 'UTF-8' ) ) );
			if ( '' !== $f[0] && '' !== $name ) {
				$byk[ (string) $f[0] ] = array( (string) $f[0], mb_substr( $name, 0, 190 ), (string) $f[2] );
			}
		}
		// Parents first; unknown or circular parents become top level.
		$sorted = array();
		$state  = array();
		$visit  = function ( $k ) use ( &$visit, &$byk, &$sorted, &$state ) {
			if ( isset( $state[ $k ] ) ) {
				if ( 1 === $state[ $k ] ) {
					$byk[ $k ][2] = '0';
				}
				return;
			}
			$state[ $k ] = 1;
			$p           = $byk[ $k ][2];
			if ( '0' === $p || '' === $p || ! isset( $byk[ $p ] ) || $p === $k ) {
				$byk[ $k ][2] = '0';
			} else {
				$visit( $p );
				if ( 1 === $state[ $p ] ) {
					$byk[ $k ][2] = '0';
				}
			}
			$state[ $k ] = 2;
			$sorted[]    = $byk[ $k ];
		};
		foreach ( array_keys( $byk ) as $k ) {
			$visit( (string) $k );
		}
		$seen = array();
		$out  = array();
		foreach ( $items as $it ) {
			$a = (int) $it[0];
			$k = (string) $it[1];
			if ( $a > 0 && isset( $byk[ $k ] ) && ! isset( $seen[ $a ] ) ) {
				$seen[ $a ] = true;
				$out[]      = array( $a, $k );
			}
		}
		return array(
			'label'   => $label,
			'folders' => $sorted,
			'items'   => $out,
			'skipped' => 0,
		);
	}

	/* ===================== Files ===================== */

	/**
	 * Parse an uploaded export (FoldPress JSON / CSV, FileBird CSV, generic JSON / CSV).
	 *
	 * @param string $path     Temp file.
	 * @param string $filename Original name.
	 * @return array|WP_Error
	 */
	private static function read_file( $path, $filename ) {
		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
		if ( '' === trim( $raw ) ) {
			return new WP_Error( 'empty', __( 'The file is empty.', 'appstrdesign-foldpress' ) );
		}
		$json = json_decode( $raw, true );
		if ( is_array( $json ) ) {
			return self::read_json( $json );
		}
		if ( preg_match( '/\.json$/i', $filename ) ) {
			return new WP_Error( 'json', __( 'The JSON file could not be read.', 'appstrdesign-foldpress' ) );
		}
		return self::read_csv( $raw );
	}

	/**
	 * Same site? (IDs are only trusted on the site that exported them).
	 *
	 * @param string $site Exported site URL.
	 * @return bool
	 */
	private static function same_site( $site ) {
		$norm = function ( $u ) {
			$p = wp_parse_url( strtolower( trim( (string) $u ) ) );
			return ( isset( $p['host'] ) ? preg_replace( '/^www\./', '', $p['host'] ) : '' ) . ( isset( $p['path'] ) ? untrailingslashit( $p['path'] ) : '' );
		};
		return '' !== $site && $norm( $site ) === $norm( home_url() );
	}

	/**
	 * Split a list of ids / files ("1|2|3", "1,2", array).
	 *
	 * @param mixed $v Value.
	 * @return array
	 */
	private static function split( $v ) {
		if ( is_array( $v ) ) {
			return $v;
		}
		$v = trim( (string) $v );
		return '' === $v ? array() : preg_split( '/\s*[|,;]\s*/', $v );
	}

	/**
	 * First matching key of a row (case-insensitive).
	 *
	 * @param array    $row  Row.
	 * @param string[] $keys Candidates.
	 * @return mixed|null
	 */
	private static function pick( $row, $keys ) {
		$row = array_change_key_case( (array) $row, CASE_LOWER );
		foreach ( $keys as $k ) {
			if ( isset( $row[ $k ] ) && '' !== $row[ $k ] && array() !== $row[ $k ] ) {
				return $row[ $k ];
			}
		}
		return null;
	}

	/**
	 * JSON exports.
	 *
	 * @param array $j Decoded.
	 * @return array|WP_Error
	 */
	private static function read_json( $j ) {
		$site    = isset( $j['site'] ) ? (string) $j['site'] : '';
		$label   = isset( $j['format'] ) && self::FORMAT === $j['format'] ? 'FoldPress' : ( isset( $j['generator'] ) ? sanitize_text_field( $j['generator'] ) : __( 'JSON file', 'appstrdesign-foldpress' ) );
		$list    = isset( $j['folders'] ) && is_array( $j['folders'] ) ? $j['folders'] : ( isset( $j['data'] ) && is_array( $j['data'] ) ? $j['data'] : ( wp_is_numeric_array( $j ) ? $j : array() ) );
		$folders = array();
		$refs    = array();
		$walk    = function ( $nodes, $parent ) use ( &$walk, &$folders, &$refs ) {
			foreach ( (array) $nodes as $i => $f ) {
				if ( ! is_array( $f ) ) {
					continue;
				}
				$id   = self::pick( $f, array( 'id', 'term_id', 'folder_id', 'key' ) );
				$k    = null === $id ? $parent . '/' . $i : (string) $id;
				$name = self::pick( $f, array( 'name', 'title', 'folder_name', 'label', 'text' ) );
				$par  = null !== $parent ? $parent : self::pick( $f, array( 'parent', 'parent_id', 'parent_folder', 'parentid' ) );
				if ( null === $name ) {
					continue;
				}
				$folders[] = array( $k, (string) $name, null === $par ? '0' : (string) $par );
				foreach ( self::split( self::pick( $f, array( 'attachment_ids', 'attachments', 'files', 'ids', 'media', 'items' ) ) ) as $a ) {
					$refs[] = array( is_array( $a ) ? $a : array( ( is_numeric( $a ) ? 'id' : 'file' ) => $a ), $k );
				}
				$kids = self::pick( $f, array( 'children', 'subfolders', 'nodes' ) );
				if ( is_array( $kids ) ) {
					$walk( $kids, $k );
				}
			}
		};
		$walk( $list, null );
		foreach ( isset( $j['files'] ) && is_array( $j['files'] ) ? $j['files'] : array() as $f ) {
			$k = self::pick( $f, array( 'folder', 'folder_id', 'term_id' ) );
			if ( null !== $k ) {
				$refs[] = array( $f, (string) $k );
			}
		}
		if ( ! $folders ) {
			return new WP_Error( 'format', __( 'No folders were found in this file.', 'appstrdesign-foldpress' ) );
		}
		return self::resolve( $label, $folders, $refs, $site );
	}

	/**
	 * CSV exports: FileBird (id, name, parent, attachment_ids...) or path based (folder_path, file, attachment_id).
	 *
	 * @param string $raw Contents.
	 * @return array|WP_Error
	 */
	private static function read_csv( $raw ) {
		$first = strtok( $raw, "\n" );
		$delim = ',';
		foreach ( array( ';', "\t" ) as $d ) {
			if ( substr_count( $first, $d ) > substr_count( $first, $delim ) ) {
				$delim = $d;
			}
		}
		$fh = fopen( 'php://memory', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $fh, $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $fh );
		$head = fgetcsv( $fh, 0, $delim );
		if ( ! $head ) {
			return new WP_Error( 'format', __( 'No folders were found in this file.', 'appstrdesign-foldpress' ) );
		}
		$head = array_map(
			function ( $h ) {
				return strtolower( trim( (string) $h, " \t\"'" ) );
			},
			$head
		);
		$rows = array();
		while ( ( $r = fgetcsv( $fh, 0, $delim ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( count( $r ) === count( $head ) ) {
				$rows[] = array_combine( $head, array_map( array( __CLASS__, 'unescape' ), $r ) );
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$folders = array();
		$refs    = array();
		$site    = '';
		if ( in_array( 'name', $head, true ) && in_array( 'parent', $head, true ) ) {
			$label = in_array( 'attachment_ids', $head, true ) ? 'FileBird CSV' : __( 'CSV file', 'appstrdesign-foldpress' );
			foreach ( $rows as $i => $r ) {
				if ( isset( $r['post_type'] ) && '' !== $r['post_type'] && 'attachment' !== $r['post_type'] ) {
					continue; // FileBird also exports post type folders.
				}
				$k         = isset( $r['id'] ) && '' !== $r['id'] ? (string) $r['id'] : 'row' . $i;
				$folders[] = array( $k, $r['name'], (string) $r['parent'] );
				foreach ( self::split( self::pick( $r, array( 'attachment_ids', 'attachments', 'files' ) ) ) as $a ) {
					$refs[] = array( array( ( is_numeric( $a ) ? 'id' : 'file' ) => $a ), $k );
				}
				if ( ! $site && ! empty( $r['site'] ) ) {
					$site = $r['site'];
				}
			}
		} else {
			$label = __( 'CSV file', 'appstrdesign-foldpress' );
			$col   = null;
			foreach ( array( 'folder_path', 'path', 'folder', 'folders', 'category' ) as $c ) {
				if ( in_array( $c, $head, true ) ) {
					$col = $c;
					break;
				}
			}
			if ( ! $col ) {
				return new WP_Error( 'format', __( 'Unknown CSV format. Expected FoldPress or FileBird columns.', 'appstrdesign-foldpress' ) );
			}
			foreach ( $rows as $r ) {
				$parts = array_values( array_filter( array_map( 'trim', preg_split( '#\s*(?:/|>|›|\\\\)\s*#u', (string) $r[ $col ] ) ), 'strlen' ) );
				$key   = '0';
				foreach ( $parts as $p ) {
					$child = $key . '/' . $p;
					if ( ! isset( $folders[ $child ] ) ) {
						$folders[ $child ] = array( $child, $p, $key );
					}
					$key = $child;
				}
				if ( '0' !== $key ) {
					$refs[] = array( $r, $key );
				}
				if ( ! $site && ! empty( $r['site'] ) ) {
					$site = $r['site'];
				}
			}
			$folders = array_values( $folders );
		}
		if ( ! $folders ) {
			return new WP_Error( 'format', __( 'No folders were found in this file.', 'appstrdesign-foldpress' ) );
		}
		return self::resolve( $label, $folders, $refs, $site );
	}

	/**
	 * Undo the spreadsheet formula guard added on export.
	 *
	 * @param string $v Cell.
	 * @return string
	 */
	public static function unescape( $v ) {
		return preg_match( "/^'[=+\-@]/", (string) $v ) ? substr( $v, 1 ) : (string) $v;
	}

	/**
	 * Map file references to attachments on this site: ID (same site only), relative path, URL, file name.
	 *
	 * @param string $label   Label.
	 * @param array  $folders Folders.
	 * @param array  $refs    [ ref array, folder_key ].
	 * @param string $site    Exported site URL.
	 * @return array
	 */
	private static function resolve( $label, $folders, $refs, $site ) {
		global $wpdb;
		$same   = self::same_site( $site );
		$has_fs = false;
		foreach ( $refs as $r ) {
			if ( null !== self::pick( $r[0], array( 'file', 'path', 'url', 'filename' ) ) ) {
				$has_fs = true;
				break;
			}
		}
		// Without a site and without file paths the IDs can only be trusted as they are.
		$trust_ids = $same || ( '' === $site && ! $has_fs );
		$paths     = array();
		$names     = array();
		if ( $has_fs ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'" ) as $m ) {
				$paths[ strtolower( $m->meta_value ) ] = (int) $m->post_id;
				$b                                     = strtolower( wp_basename( $m->meta_value ) );
				$names[ $b ]                           = isset( $names[ $b ] ) ? 0 : (int) $m->post_id; // 0 = ambiguous.
			}
		}
		$base    = strtolower( (string) wp_get_upload_dir()['baseurl'] );
		$items   = array();
		$skipped = 0;
		foreach ( $refs as $r ) {
			$ref = $r[0];
			$id  = (int) self::pick( $ref, array( 'id', 'attachment_id', 'post_id', 'ID' ) );
			$hit = 0;
			if ( $id && $trust_ids && 'attachment' === get_post_type( $id ) ) {
				$hit = $id;
			}
			if ( ! $hit ) {
				$f = strtolower( (string) self::pick( $ref, array( 'file', 'path', 'url', 'filename' ) ) );
				if ( '' !== $f ) {
					$f = preg_replace( '#^https?:#', '', $f );
					$b = preg_replace( '#^https?:#', '', $base );
					if ( 0 === strpos( $f, $b . '/' ) ) {
						$f = substr( $f, strlen( $b ) + 1 );
					}
					$f   = ltrim( $f, '/' );
					$hit = isset( $paths[ $f ] ) ? $paths[ $f ] : ( ! empty( $names[ wp_basename( $f ) ] ) ? $names[ wp_basename( $f ) ] : 0 );
				}
			}
			if ( $hit ) {
				$items[] = array( $hit, $r[1] );
			} else {
				++$skipped;
			}
		}
		$plan            = self::plan( $label, $folders, $items );
		$plan['skipped'] = $skipped;
		return $plan;
	}

	/* ===================== Writing ===================== */

	/**
	 * Make sure a foreign taxonomy is registered for this request (the plugin may be inactive).
	 *
	 * @param string $tax Taxonomy.
	 */
	private static function ensure_tax( $tax ) {
		if ( ! taxonomy_exists( $tax ) ) {
			register_taxonomy(
				$tax,
				'attachment',
				array(
					'hierarchical' => true,
					'public'       => false,
					'rewrite'      => false,
					'query_var'    => false,
				)
			);
		}
	}

	/**
	 * Target taxonomy for a plan target ('' for FileBird tables).
	 *
	 * @param string $target Target key.
	 * @return string
	 */
	private static function target_tax( $target ) {
		return 'foldpress' === $target ? FOLDPRESS_TAX : ( isset( self::TAXONOMIES[ $target ] ) ? self::TAXONOMIES[ $target ][2] : '' );
	}

	/**
	 * Create (or reuse) a folder in the target.
	 *
	 * @param string $target Target.
	 * @param string $name   Name.
	 * @param int    $parent Parent id in the target.
	 * @return int
	 */
	private static function make_folder( $target, $name, $parent ) {
		global $wpdb;
		if ( 'filebird' === $target ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}fbv WHERE name = %s AND parent = %d AND created_by = 0 LIMIT 1", $name, $parent ) );
			if ( ! $id ) {
				$ord = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(ord) FROM {$wpdb->prefix}fbv WHERE parent = %d", $parent ) );
				$wpdb->insert(
					$wpdb->prefix . 'fbv',
					array(
						'name'       => $name,
						'parent'     => $parent,
						'type'       => 0,
						'created_by' => 0,
						'ord'        => $ord + 1,
					)
				);
				$id = (int) $wpdb->insert_id;
			}
			// phpcs:enable
			return $id;
		}
		$tax = self::target_tax( $target );
		$ex  = term_exists( $name, $tax, $parent );
		if ( $ex ) {
			return (int) ( is_array( $ex ) ? $ex['term_id'] : $ex );
		}
		$r = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
		if ( is_wp_error( $r ) ) {
			$id = $r->get_error_data( 'term_exists' );
			return $id ? (int) $id : 0;
		}
		return (int) $r['term_id'];
	}

	/**
	 * Put an attachment in a folder of the target.
	 *
	 * @param string $target  Target.
	 * @param int    $att     Attachment.
	 * @param int    $folder  Folder id.
	 * @param bool   $replace Replace an existing folder.
	 * @return bool Assigned.
	 */
	private static function assign( $target, $att, $folder, $replace ) {
		global $wpdb;
		if ( 'filebird' === $target ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$has = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}fbv_attachment_folder WHERE attachment_id = %d", $att ) );
			if ( $has && ! $replace ) {
				return false;
			}
			$wpdb->delete( $wpdb->prefix . 'fbv_attachment_folder', array( 'attachment_id' => $att ) );
			$wpdb->insert(
				$wpdb->prefix . 'fbv_attachment_folder',
				array(
					'folder_id'     => $folder,
					'attachment_id' => $att,
				)
			);
			// phpcs:enable
			return true;
		}
		$tax = self::target_tax( $target );
		if ( ! $replace && wp_get_object_terms( $att, $tax, array( 'fields' => 'ids' ) ) ) {
			return false;
		}
		return ! is_wp_error( wp_set_object_terms( $att, array( (int) $folder ), $tax, false ) );
	}

	/* ===================== AJAX ===================== */

	/**
	 * Transient key of the running job.
	 *
	 * @return string
	 */
	private static function key() {
		return 'foldpress_mig_' . get_current_user_id();
	}

	/**
	 * Detected sources and targets.
	 */
	public function ajax_scan() {
		$this->guard();
		wp_send_json_success(
			array(
				'sources' => self::sources(),
				'targets' => self::targets(),
			)
		);
	}

	/**
	 * Build a plan and return its summary.
	 */
	public function ajax_prepare() {
		$this->guard();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		$kind    = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$key     = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$mode    = isset( $_POST['mode'] ) && 'new' === $_POST['mode'] ? 'new' : 'merge';
		$replace = ! empty( $_POST['replace'] );
		if ( 'source' === $kind ) {
			$plan   = self::read_source( $key );
			$target = 'foldpress';
		} elseif ( 'file' === $kind ) {
			if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				wp_send_json_error( array( 'message' => __( 'Choose a JSON or CSV export file.', 'appstrdesign-foldpress' ) ) );
			}
			if ( (int) $_FILES['file']['size'] > 20 * MB_IN_BYTES ) {
				wp_send_json_error( array( 'message' => __( 'The file is larger than 20 MB.', 'appstrdesign-foldpress' ) ) );
			}
			$plan   = self::read_file( $_FILES['file']['tmp_name'], sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$target = 'foldpress';
		} elseif ( 'target' === $kind && isset( self::targets()[ $key ] ) ) {
			$plan   = self::read_own();
			$target = $key;
		} else {
			wp_send_json_error( array( 'message' => __( 'Unknown source.', 'appstrdesign-foldpress' ) ) );
		}
		// phpcs:enable
		if ( is_wp_error( $plan ) ) {
			wp_send_json_error( array( 'message' => $plan->get_error_message() ) );
		}
		if ( ! $plan['folders'] ) {
			wp_send_json_error( array( 'message' => __( 'No folders were found in this file.', 'appstrdesign-foldpress' ) ) );
		}
		$plan['target']  = $target;
		$plan['mode']    = $mode;
		$plan['replace'] = $replace;
		$plan['map']     = null;
		$plan['done']    = 0;
		$plan['moved']   = 0;
		set_transient( self::key(), $plan, DAY_IN_SECONDS );
		wp_send_json_success(
			array(
				'label'   => $plan['label'],
				'target'  => 'foldpress' === $target ? 'FoldPress' : ( 'filebird' === $target ? 'FileBird' : self::TAXONOMIES[ $target ][0] ),
				'folders' => count( $plan['folders'] ),
				'files'   => count( $plan['items'] ),
				'skipped' => (int) $plan['skipped'],
			)
		);
	}

	/**
	 * Write the next chunk of the prepared plan.
	 */
	public function ajax_run() {
		$this->guard();
		$plan = get_transient( self::key() );
		if ( ! is_array( $plan ) ) {
			wp_send_json_error( array( 'message' => __( 'The import expired. Start it again.', 'appstrdesign-foldpress' ) ) );
		}
		$target = $plan['target'];
		$tax    = self::target_tax( $target );
		if ( $tax ) {
			self::ensure_tax( $tax );
		}
		if ( null === $plan['map'] ) {
			$map  = array( '0' => 0 );
			$root = 0;
			if ( 'wpmf' === $target && get_option( 'wpmf_folder_root_id' ) && term_exists( (int) get_option( 'wpmf_folder_root_id' ), $tax ) ) {
				$root = (int) get_option( 'wpmf_folder_root_id' );
			}
			if ( 'new' === $plan['mode'] ) {
				/* translators: 1: source name, 2: date */
				$root = self::make_folder( $target, sprintf( __( '%1$s import %2$s', 'appstrdesign-foldpress' ), $plan['label'], wp_date( 'Y-m-d H:i' ) ), $root );
			}
			$map['0'] = $root;
			foreach ( $plan['folders'] as $f ) {
				$parent        = isset( $map[ $f[2] ] ) ? $map[ $f[2] ] : $root;
				$map[ $f[0] ] = self::make_folder( $target, $f[1], (int) $parent );
			}
			$plan['map'] = $map;
		}
		$slice = array_slice( $plan['items'], $plan['done'], self::CHUNK );
		foreach ( $slice as $it ) {
			$fid = isset( $plan['map'][ $it[1] ] ) ? (int) $plan['map'][ $it[1] ] : 0;
			if ( $fid && 'attachment' === get_post_type( $it[0] ) && self::assign( $target, (int) $it[0], $fid, $plan['replace'] ) ) {
				++$plan['moved'];
			}
		}
		$plan['done'] += count( $slice );
		$finished      = $plan['done'] >= count( $plan['items'] );
		if ( $finished ) {
			if ( $tax ) {
				$tt = array();
				foreach ( array_unique( array_filter( array_map( 'intval', $plan['map'] ) ) ) as $tid ) {
					$term = get_term( $tid, $tax );
					if ( $term && ! is_wp_error( $term ) ) {
						$tt[] = (int) $term->term_taxonomy_id;
					}
				}
				wp_update_term_count_now( $tt, $tax );
				clean_taxonomy_cache( $tax );
			}
			delete_transient( self::key() );
		} else {
			set_transient( self::key(), $plan, DAY_IN_SECONDS );
		}
		wp_send_json_success(
			array(
				'done'     => (int) $plan['done'],
				'total'    => count( $plan['items'] ),
				'moved'    => (int) $plan['moved'],
				'folders'  => count( array_unique( array_filter( array_map( 'intval', (array) $plan['map'] ) ) ) ),
				'finished' => $finished,
			)
		);
	}

	/* ===================== Export download ===================== */

	/**
	 * Formula guard for spreadsheet apps.
	 *
	 * @param string $v Cell.
	 * @return string
	 */
	private static function cell( $v ) {
		return preg_match( '/^[=+\-@]/', (string) $v ) ? "'" . $v : (string) $v;
	}

	/**
	 * Stream an export file: json (FoldPress), csv (folder paths), filebird (FileBird CSV).
	 */
	public function download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'appstrdesign-foldpress' ), 403 );
		}
		check_admin_referer( 'foldpress_export' );
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'json';
		$plan   = self::read_own();
		$files  = array();
		$byf    = array();
		foreach ( $plan['items'] as $it ) {
			$byf[ $it[1] ][] = $it[0];
			$files[]         = array(
				'id'     => $it[0],
				'folder' => (int) $it[1],
				'file'   => (string) get_post_meta( $it[0], '_wp_attached_file', true ),
			);
		}
		$stamp = wp_date( 'Y-m-d' );
		nocache_headers();
		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="foldpress-folders-' . $stamp . '.json"' );
			$folders = array();
			foreach ( $plan['folders'] as $f ) {
				$folders[] = array(
					'id'     => (int) $f[0],
					'name'   => $f[1],
					'parent' => (int) $f[2],
				);
			}
			echo wp_json_encode(
				array(
					'format'    => self::FORMAT,
					'version'   => 1,
					'generator' => 'FoldPress ' . FOLDPRESS_VERSION,
					'site'      => home_url(),
					'exported'  => gmdate( 'c' ),
					'folders'   => $folders,
					'files'     => $files,
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
			exit;
		}
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . ( 'filebird' === $format ? 'filebird-folders-' : 'foldpress-folders-' ) . $stamp . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( 'filebird' === $format ) {
			// Same columns as FileBird's own export: pipe separated attachment ids, parent 0 = root.
			fputcsv( $out, array( 'id', 'name', 'parent', 'type', 'created_by', 'ord', 'attachment_ids', 'post_type', 'site', 'color' ) );
			$ord = 0;
			foreach ( $plan['folders'] as $f ) {
				fputcsv( $out, array( (int) $f[0], self::cell( $f[1] ), (int) $f[2], 0, 0, $ord++, implode( '|', isset( $byf[ $f[0] ] ) ? $byf[ $f[0] ] : array() ), 'attachment', home_url(), '' ) );
			}
		} else {
			$names = array();
			foreach ( $plan['folders'] as $f ) {
				$names[ $f[0] ] = ( '0' !== $f[2] && isset( $names[ $f[2] ] ) ? $names[ $f[2] ] . ' / ' : '' ) . str_replace( '/', '-', $f[1] );
			}
			fputcsv( $out, array( 'folder_path', 'file', 'attachment_id', 'url', 'site' ) );
			$used = array();
			foreach ( $files as $f ) {
				$used[ $f['folder'] ] = true;
				fputcsv( $out, array( self::cell( $names[ (string) $f['folder'] ] ), $f['file'], $f['id'], wp_get_attachment_url( $f['id'] ), home_url() ) );
			}
			foreach ( $plan['folders'] as $f ) {
				if ( empty( $used[ (int) $f[0] ] ) ) {
					fputcsv( $out, array( self::cell( $names[ $f[0] ] ), '', '', '', home_url() ) ); // Empty folders keep their place.
				}
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
