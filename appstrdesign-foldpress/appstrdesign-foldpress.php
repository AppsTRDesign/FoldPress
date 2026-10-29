<?php
/**
 * Plugin Name:       AppsTRDesign - FoldPress
 * Plugin URI:        https://wplugins.shop
 * Description:       Advanced AJAX media file manager with folders, drag and drop, bulk actions, context menu, ZIP/UNZIP and file type grouping. Replaces the default Media Library screens.
 * Version:           1.6.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            AppsTRDesign
 * Author URI:        https://wplugins.shop
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       appstrdesign-foldpress
 * Domain Path:       /languages
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

define( 'FOLDPRESS_VERSION', '1.6.1' );
define( 'FOLDPRESS_FILE', __FILE__ );
define( 'FOLDPRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOLDPRESS_URL', plugin_dir_url( __FILE__ ) );
define( 'FOLDPRESS_TAX', 'foldpress_folder' );

require_once FOLDPRESS_DIR . 'includes/class-foldpress-settings.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-replacer.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-converter.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-files.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-usage.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-ajax.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-admin.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-modal.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-editor.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-settings-page.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-code.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-preview.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-cloud.php';
require_once FOLDPRESS_DIR . 'includes/class-foldpress-migrate.php';

/**
 * Bootstrap.
 */
function foldpress_init() {
	load_plugin_textdomain( 'appstrdesign-foldpress', false, dirname( plugin_basename( FOLDPRESS_FILE ) ) . '/languages' );

	register_taxonomy(
		FOLDPRESS_TAX,
		'attachment',
		array(
			'hierarchical'          => true,
			'public'                => false,
			'show_ui'               => false,
			'show_in_rest'          => false,
			'rewrite'               => false,
			'query_var'             => false,
			'update_count_callback' => '_update_generic_term_count',
			'labels'                => array(
				'name' => __( 'Folders', 'appstrdesign-foldpress' ),
			),
		)
	);

	FoldPress_Settings::init();
	FoldPress_Converter::init();
	new FoldPress_Modal();

	if ( is_admin() ) {
		new FoldPress_Ajax();
		new FoldPress_Admin();
		new FoldPress_Editor();
		new FoldPress_Settings_Page();
		new FoldPress_Code();
		new FoldPress_Preview();
		new FoldPress_Cloud();
		new FoldPress_Migrate();
	}
}
add_action( 'init', 'foldpress_init' );
