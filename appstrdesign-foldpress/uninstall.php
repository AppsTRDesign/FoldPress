<?php
/**
 * Uninstall: remove folder terms only. Media files are never touched.
 *
 * @package FoldPress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$foldpress_terms = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'foldpress_folder' ) );
foreach ( $foldpress_terms as $foldpress_tt ) {
	$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => (int) $foldpress_tt ) );
}
$wpdb->query( $wpdb->prepare( "DELETE t FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s", 'foldpress_folder' ) );
$wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => 'foldpress_folder' ) );
// phpcs:enable
delete_option( 'foldpress_settings' );
