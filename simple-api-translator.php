<?php
/**
 * Plugin Name:       Simple API Translator
 * Plugin URI:        https://www.tommasovietina.it/simple-api-translator
 * Description:       Translates post and page content using OpenAI or DeepL while preserving HTML and WordPress shortcodes. Supports WPML languages.
 * Version:           1.0.3
 * Author:            Tommaso Vietina
 * Text Domain:       simple-api-translator
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAT_VERSION', '1.0.3' );
define( 'SAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SAT_PLUGIN_DIR . 'includes/class-settings.php';
require_once SAT_PLUGIN_DIR . 'includes/class-translator.php';
require_once SAT_PLUGIN_DIR . 'includes/class-metabox.php';

function sat_init_plugin() {
	new SAT_Settings();
	new SAT_Metabox();
}
add_action( 'plugins_loaded', 'sat_init_plugin' );
