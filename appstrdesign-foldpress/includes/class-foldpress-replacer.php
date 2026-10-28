<?php
/**
 * Database-wide, serialization-safe URL replacement used after a physical rename.
 * Covers post content/excerpt, all post meta (Elementor JSON, WooCommerce, page builders, SEO plugins),
 * options (widgets, customizer, theme mods) and term meta.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Replacer
 */
class FoldPress_Replacer {

	/**
	 * Replace old => new strings everywhere.
	 *
	 * @param array $map Old => new.
	 * @return int Rows updated.
	 */
	public static function run( array $map ) {
		global $wpdb;

		$pairs = array();
		foreach ( $map as $old => $new ) {
			if ( '' === $old || $old === $new ) {
				continue;
			}
			$pairs[ $old ] = $new;
			// JSON-escaped form (Elementor, block attributes, REST caches).
			$pairs[ str_replace( '/', '\\/', $old ) ] = str_replace( '/', '\\/', $new );
		}
		if ( ! $pairs ) {
			return 0;
		}

		$targets = array(
			array( $wpdb->posts, 'ID', 'post_content', '' ),
			array( $wpdb->posts, 'ID', 'post_excerpt', '' ),
			array( $wpdb->postmeta, 'meta_id', 'meta_value', "AND meta_key NOT IN ('_wp_attached_file','_wp_attachment_metadata')" ),
			array( $wpdb->options, 'option_id', 'option_value', '' ),
			array( $wpdb->termmeta, 'meta_id', 'meta_value', '' ),
		);

		$updated = 0;
		$posts   = array();
		$options = false;
		foreach ( $targets as $t ) {
			list( $table, $id_col, $col, $extra ) = $t;
			$likes  = array();
			$params = array();
			foreach ( array_keys( $pairs ) as $old ) {
				$likes[]  = "`{$col}` LIKE %s";
				$params[] = '%' . $wpdb->esc_like( $old ) . '%';
			}
			$post_col = $wpdb->postmeta === $table ? ', post_id' : ( $wpdb->posts === $table ? ', ID AS post_id' : '' );
			$sql      = "SELECT `{$id_col}` AS row_id, `{$col}` AS val{$post_col} FROM `{$table}` WHERE (" . implode( ' OR ', $likes ) . ") {$extra}";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- table/column names are internal constants.
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
			foreach ( (array) $rows as $row ) {
				$new = self::replace_value( $row->val, $pairs );
				if ( $new === $row->val ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update( $table, array( $col => $new ), array( $id_col => $row->row_id ) );
				++$updated;
				if ( isset( $row->post_id ) ) {
					$posts[ (int) $row->post_id ] = true;
				}
				if ( $wpdb->options === $table ) {
					$options = true;
				}
			}
		}

		foreach ( array_keys( $posts ) as $pid ) {
			clean_post_cache( $pid );
			// Elementor keeps a generated CSS file per post.
			delete_post_meta( $pid, '_elementor_css' );
		}
		if ( $options ) {
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_flush();
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}
		/**
		 * Fires after FoldPress replaced file URLs across the database.
		 *
		 * @param array $map Old => new URL map.
		 */
		do_action( 'foldpress_urls_replaced', $map );
		return $updated;
	}

	/**
	 * Replace inside a raw DB value, keeping serialized data valid.
	 *
	 * @param string $value Value.
	 * @param array  $pairs Pairs.
	 * @return string
	 */
	public static function replace_value( $value, $pairs ) {
		if ( is_serialized( $value ) ) {
			$data = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			if ( false === $data && 'b:0;' !== $value ) {
				return $value;
			}
			if ( self::has_object( $data ) ) {
				$fixed = self::fix_serialized( strtr( $value, $pairs ) );
				return null === $fixed ? $value : $fixed;
			}
			return serialize( self::walk( $data, $pairs ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		}
		return strtr( $value, $pairs );
	}

	/**
	 * Recursive replace.
	 *
	 * @param mixed $data  Data.
	 * @param array $pairs Pairs.
	 * @return mixed
	 */
	private static function walk( $data, $pairs ) {
		if ( is_string( $data ) ) {
			if ( is_serialized( $data ) ) {
				return self::replace_value( $data, $pairs );
			}
			return strtr( $data, $pairs );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = self::walk( $v, $pairs );
			}
		}
		return $data;
	}

	/**
	 * Contains objects (cannot be rebuilt safely without their classes).
	 *
	 * @param mixed $data Data.
	 * @return bool
	 */
	private static function has_object( $data ) {
		if ( is_object( $data ) ) {
			return true;
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $v ) {
				if ( self::has_object( $v ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Recalculate string lengths of a serialized string after a raw replace.
	 *
	 * @param string $s Serialized.
	 * @return string|null Null when the result would be invalid.
	 */
	private static function fix_serialized( $s ) {
		$fixed = preg_replace_callback(
			'/s:(\d+):"(.*?)";(?=[a-zA-Z}]|$)/s',
			function ( $m ) {
				return 's:' . strlen( $m[2] ) . ':"' . $m[2] . '";';
			},
			$s
		);
		$test = @unserialize( $fixed, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		return false === $test ? null : $fixed;
	}
}
