<?php
/**
 * Where is an attachment used? Scans by attachment ID and by file URL across
 * post content, post meta, user meta, term meta and options, then verifies every
 * candidate in PHP so partial matches (e.g. ID 12 inside 123) are not reported.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Usage
 */
class FoldPress_Usage {

	const LIMIT = 300;

	/**
	 * Meta keys that belong to the attachment itself or are internal noise.
	 *
	 * @var string[]
	 */
	const SKIP_KEYS = array( '_wp_attached_file', '_wp_attachment_metadata', '_wp_attachment_backup_sizes', '_edit_lock', '_edit_last', '_foldpress_backup', '_foldpress_rename_history', 'session_tokens', '_wp_old_slug', '_wp_trash_meta_status' );

	/**
	 * Id under test.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * URL / path stems (without extension).
	 *
	 * @var string[]
	 */
	private $stems = array();

	/**
	 * Build.
	 *
	 * @param int $id Attachment id.
	 */
	public function __construct( $id ) {
		$this->id = (int) $id;
		$url      = (string) wp_get_attachment_url( $id );
		$rel      = (string) get_post_meta( $id, '_wp_attached_file', true );
		foreach ( array( $url, $rel ) as $p ) {
			$stem = preg_replace( '/(-scaled)?\.[a-z0-9]{2,5}$/i', '', $p );
			if ( strlen( $stem ) > 4 ) {
				$this->stems[] = $stem;
				$this->stems[] = str_replace( '/', '\\/', $stem );
			}
		}
		$this->stems = array_values( array_unique( $this->stems ) );
	}

	/**
	 * Run the full scan.
	 *
	 * @param int $id Attachment id.
	 * @return array{total:int,groups:array}
	 */
	public static function find( $id ) {
		$u      = new self( $id );
		$groups = array(
			'content' => $u->posts(),
			'users'   => $u->users(),
			'terms'   => $u->terms(),
			'options' => $u->options(),
		);
		$total  = 0;
		$out    = array();
		foreach ( $groups as $key => $items ) {
			$items  = array_values( $items );
			$total += count( $items );
			$out[]  = array(
				'key'   => $key,
				'items' => $items,
			);
		}
		return array(
			'total'  => $total,
			'groups' => $out,
		);
	}

	/**
	 * SQL LIKE candidates for a column (id patterns + url stems).
	 *
	 * @param string $col Column.
	 * @param bool   $meta Also exact / comma-list match (meta values).
	 * @return array{0:string,1:array} SQL fragment and params.
	 */
	private function where( $col, $meta = false ) {
		global $wpdb;
		$id    = (string) $this->id;
		$likes = array(
			'%wp-image-' . $id . '%',
			'%"id":' . $id . '%',
			'%"id":"' . $id . '"%',
			'%Id":' . $id . '%',
			'%_id":' . $id . '%',
			'%";i:' . $id . ';%',
			'%:"' . $id . '";%',
			'%ids=%',
		);
		$sql    = array();
		$params = array();
		foreach ( $likes as $l ) {
			$sql[]    = "`{$col}` LIKE %s";
			$params[] = $l;
		}
		foreach ( $this->stems as $st ) {
			$sql[]    = "`{$col}` LIKE %s";
			$params[] = '%' . $wpdb->esc_like( $st ) . '%';
		}
		if ( $meta ) {
			$sql[]    = "`{$col}` = %s";
			$params[] = $id;
			$sql[]    = "FIND_IN_SET(%s, `{$col}`) > 0";
			$params[] = $id;
		}
		return array( '(' . implode( ' OR ', $sql ) . ')', $params );
	}

	/**
	 * Verify a value really references the attachment. Returns 'id', 'url' or ''.
	 *
	 * @param string $val Value.
	 * @return string
	 */
	private function match( $val ) {
		$val = (string) $val;
		$id  = (string) $this->id;
		foreach ( $this->stems as $st ) {
			if ( preg_match( '#' . preg_quote( $st, '#' ) . '(?:-\\d+x\\d+|-scaled)?\\.[a-z0-9]{2,5}#i', $val ) ) {
				return 'url';
			}
		}
		if ( trim( $val ) === $id || preg_match( '/^\\s*(\\d+\\s*,\\s*)*' . $id . '(\\s*,\\s*\\d+)*\\s*$/', $val ) ) {
			return 'id';
		}
		$patterns = array(
			'/wp-image-' . $id . '(?!\\d)/',
			'/"(?:id|ID|mediaId|imageId|image_id|attachment_id|attachmentId|thumbnail_id|logo|icon|image)"\\s*:\\s*"?' . $id . '"?(?!\\d)/',
			'/";i:' . $id . ';/',
			'/s:\\d+:"' . $id . '";/',
			'/\\bids=["\'][^"\']*(?<!\\d)' . $id . '(?!\\d)/',
		);
		foreach ( $patterns as $p ) {
			if ( preg_match( $p, $val ) ) {
				return 'id';
			}
		}
		return '';
	}

	/**
	 * Friendly meta key label.
	 *
	 * @param string $key Meta key.
	 * @param string $table posts|users|terms.
	 * @return string
	 */
	private function key_label( $key, $table = 'posts' ) {
		$map = array(
			'_thumbnail_id'                    => __( 'Featured image', 'appstrdesign-foldpress' ),
			'_product_image_gallery'           => __( 'Product gallery', 'appstrdesign-foldpress' ),
			'_elementor_data'                  => __( 'Elementor content', 'appstrdesign-foldpress' ),
			'_elementor_page_settings'         => __( 'Elementor page settings', 'appstrdesign-foldpress' ),
			'_yoast_wpseo_opengraph-image'     => __( 'Yoast SEO social image', 'appstrdesign-foldpress' ),
			'_yoast_wpseo_opengraph-image-id'  => __( 'Yoast SEO social image', 'appstrdesign-foldpress' ),
			'_yoast_wpseo_twitter-image'       => __( 'Yoast SEO social image', 'appstrdesign-foldpress' ),
			'_yoast_wpseo_twitter-image-id'    => __( 'Yoast SEO social image', 'appstrdesign-foldpress' ),
			'rank_math_facebook_image'         => __( 'Rank Math social image', 'appstrdesign-foldpress' ),
			'rank_math_facebook_image_id'      => __( 'Rank Math social image', 'appstrdesign-foldpress' ),
			'rank_math_twitter_image_id'       => __( 'Rank Math social image', 'appstrdesign-foldpress' ),
			'thumbnail_id'                     => 'terms' === $table ? __( 'Category image', 'appstrdesign-foldpress' ) : __( 'Image field', 'appstrdesign-foldpress' ),
			'_menu_item_url'                   => __( 'Menu link', 'appstrdesign-foldpress' ),
		);
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
		/* translators: %s: meta key */
		return sprintf( __( 'Field: %s', 'appstrdesign-foldpress' ), $key );
	}

	/**
	 * Add / merge a found entry.
	 *
	 * @param array  $list  List (by ref).
	 * @param string $k     Unique key.
	 * @param array  $base  title, sub, url.
	 * @param string $how   Label.
	 */
	private function add( &$list, $k, $base, $how ) {
		if ( ! isset( $list[ $k ] ) ) {
			$list[ $k ]        = $base;
			$list[ $k ]['how'] = array();
		}
		if ( ! in_array( $how, $list[ $k ]['how'], true ) ) {
			$list[ $k ]['how'][] = $how;
		}
	}

	/**
	 * Post descriptor.
	 *
	 * @param WP_Post $p Post.
	 * @return array
	 */
	private function post_base( $p ) {
		$type   = get_post_type_object( $p->post_type );
		$status = get_post_status_object( $p->post_status );
		return array(
			'kind'        => in_array( $p->post_type, array( 'post', 'page', 'product' ), true ) ? $p->post_type : 'post',
			'type'        => $type ? $type->labels->singular_name : $p->post_type,
			'status'      => $p->post_status,
			'statusLabel' => $status ? $status->label : $p->post_status,
			'id'    => (int) $p->ID,
			'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ) ? html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ) : '#' . $p->ID,
			'sub'   => ( $type ? $type->labels->singular_name : $p->post_type ) . ' #' . $p->ID . ' · ' . $p->post_status,
			'url'   => current_user_can( 'edit_post', $p->ID ) ? (string) get_edit_post_link( $p->ID, 'raw' ) : '',
			'view'  => 'publish' === $p->post_status ? (string) get_permalink( $p ) : '',
		);
	}

	/**
	 * Posts: parent, content, excerpt and post meta.
	 *
	 * @return array
	 */
	private function posts() {
		global $wpdb;
		$found = array();
		$att   = get_post( $this->id );

		if ( $att && $att->post_parent && ( $parent = get_post( $att->post_parent ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			$this->add( $found, 'p' . $parent->ID, $this->post_base( $parent ), __( 'Uploaded to', 'appstrdesign-foldpress' ) );
		}

		list( $w1, $p1 ) = $this->where( 'post_content' );
		list( $w2, $p2 ) = $this->where( 'post_excerpt' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','attachment') AND post_status NOT IN ('trash','auto-draft','inherit') AND ( {$w1} OR {$w2} ) LIMIT " . self::LIMIT, array_merge( $p1, $p2 ) ) );
		foreach ( (array) $rows as $r ) {
			$m = $this->match( $r->post_content ) ? $this->match( $r->post_content ) : $this->match( $r->post_excerpt );
			if ( $m ) {
				$p = get_post( $r->ID );
				$this->add( $found, 'p' . $p->ID, $this->post_base( $p ), 'url' === $m ? __( 'Link in content', 'appstrdesign-foldpress' ) : __( 'In content (block / gallery)', 'appstrdesign-foldpress' ) );
			}
		}

		list( $w, $p ) = $this->where( 'meta_value', true );
		$skip          = "'" . implode( "','", array_map( 'esc_sql', self::SKIP_KEYS ) ) . "'";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id <> %d AND meta_key NOT IN ({$skip}) AND {$w} LIMIT " . self::LIMIT, array_merge( array( $this->id ), $p ) ) );
		foreach ( (array) $rows as $r ) {
			if ( ! $this->match( $r->meta_value ) ) {
				continue;
			}
			$post = get_post( $r->post_id );
			if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				continue;
			}
			// Revisions / variations report their parent.
			if ( in_array( $post->post_type, array( 'revision', 'product_variation' ), true ) && $post->post_parent ) {
				$post = get_post( $post->post_parent );
			}
			if ( ! $post || 'attachment' === $post->post_type ) {
				continue;
			}
			$this->add( $found, 'p' . $post->ID, $this->post_base( $post ), $this->key_label( $r->meta_key ) );
		}
		return $found;
	}

	/**
	 * User meta (avatars, profile images, author boxes...).
	 *
	 * @return array
	 */
	private function users() {
		global $wpdb;
		$found         = array();
		list( $w, $p ) = $this->where( 'meta_value', true );
		$skip          = "'" . implode( "','", array_map( 'esc_sql', self::SKIP_KEYS ) ) . "'";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key NOT IN ({$skip}) AND {$w} LIMIT " . self::LIMIT, $p ) );
		foreach ( (array) $rows as $r ) {
			$u = get_userdata( (int) $r->user_id );
			if ( ! $u || ! $this->match( $r->meta_value ) ) {
				continue;
			}
			$this->add(
				$found,
				'u' . $u->ID,
				array(
					'kind'        => 'user',
					'type'        => __( 'User', 'appstrdesign-foldpress' ),
					'status'      => '',
					'statusLabel' => $u->user_login,
					'id'    => (int) $u->ID,
					'title' => $u->display_name,
					'sub'   => __( 'User', 'appstrdesign-foldpress' ) . ' #' . $u->ID . ' · ' . $u->user_login,
					'url'   => current_user_can( 'edit_user', $u->ID ) ? get_edit_user_link( $u->ID ) : '',
					'view'  => '',
				),
				$this->key_label( $r->meta_key, 'users' )
			);
		}
		return $found;
	}

	/**
	 * Term meta (category / product category images...).
	 *
	 * @return array
	 */
	private function terms() {
		global $wpdb;
		$found         = array();
		list( $w, $p ) = $this->where( 'meta_value', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta} WHERE {$w} LIMIT " . self::LIMIT, $p ) );
		foreach ( (array) $rows as $r ) {
			$t = get_term( (int) $r->term_id );
			if ( ! $t || is_wp_error( $t ) || FOLDPRESS_TAX === $t->taxonomy || ! $this->match( $r->meta_value ) ) {
				continue;
			}
			$tax = get_taxonomy( $t->taxonomy );
			$this->add(
				$found,
				't' . $t->term_id,
				array(
					'kind'        => 'term',
					'type'        => $tax ? $tax->labels->singular_name : $t->taxonomy,
					'status'      => '',
					'statusLabel' => '',
					'id'    => (int) $t->term_id,
					'title' => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ),
					'sub'   => ( $tax ? $tax->labels->singular_name : $t->taxonomy ) . ' #' . $t->term_id,
					'url'   => current_user_can( 'edit_term', $t->term_id ) ? (string) get_edit_term_link( $t->term_id, $t->taxonomy ) : '',
					'view'  => (string) get_term_link( $t ),
				),
				$this->key_label( $r->meta_key, 'terms' )
			);
		}
		return $found;
	}

	/**
	 * Options (site icon, logo / theme mods, widgets, plugin settings).
	 *
	 * @return array
	 */
	private function options() {
		global $wpdb;
		$found = array();
		if ( (int) get_option( 'site_icon' ) === $this->id ) {
			$this->add(
				$found,
				'o-site_icon',
				array(
					'kind'        => 'option',
					'type'        => __( 'Site settings', 'appstrdesign-foldpress' ),
					'status'      => '',
					'statusLabel' => '',
					'id'    => 0,
					'title' => __( 'Site icon', 'appstrdesign-foldpress' ),
					'sub'   => 'site_icon',
					'url'   => current_user_can( 'manage_options' ) ? admin_url( 'options-general.php' ) : '',
					'view'  => '',
				),
				__( 'Site settings', 'appstrdesign-foldpress' )
			);
		}
		list( $w, $p ) = $this->where( 'option_value', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT IN ('site_icon','foldpress_settings','cron','rewrite_rules') AND {$w} LIMIT " . self::LIMIT, array_merge( array( $wpdb->esc_like( '_transient' ) . '%', $wpdb->esc_like( '_site_transient' ) . '%' ), $p ) ) );
		foreach ( (array) $rows as $r ) {
			if ( ! $this->match( $r->option_value ) ) {
				continue;
			}
			$name = $r->option_name;
			if ( 0 === strpos( $name, 'theme_mods_' ) ) {
				$title = __( 'Theme settings (Customizer)', 'appstrdesign-foldpress' );
				$how   = false !== strpos( $r->option_value, '"custom_logo";i:' . $this->id . ';' ) ? __( 'Site logo', 'appstrdesign-foldpress' ) : __( 'Customizer', 'appstrdesign-foldpress' );
				$url   = current_user_can( 'customize' ) ? admin_url( 'customize.php' ) : '';
			} elseif ( 0 === strpos( $name, 'widget_' ) ) {
				$title = __( 'Widgets', 'appstrdesign-foldpress' );
				$how   = $name;
				$url   = current_user_can( 'edit_theme_options' ) ? admin_url( 'widgets.php' ) : '';
			} else {
				$title = $name;
				$how   = __( 'Plugin / site setting', 'appstrdesign-foldpress' );
				$url   = '';
			}
			$this->add(
				$found,
				'o-' . $name,
				array(
					'kind'        => 'option',
					'type'        => __( 'Site settings', 'appstrdesign-foldpress' ),
					'status'      => '',
					'statusLabel' => '',
					'id'    => 0,
					'title' => $title,
					'sub'   => $name,
					'url'   => $url,
					'view'  => '',
				),
				$how
			);
		}
		return $found;
	}
}
