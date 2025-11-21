<?php
/**
 * Plugin Name: Latest Google Drive File
 * Description: Serves the most recent file from up to 10 Google Drive folders at stable URLs for use with viewers like PDF Embedder, <img>, <video>, etc.
 * Version: 5.0.0
 * Author: Your Name
 * Text Domain: latest-google-drive-file
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Useful constants
define( 'LGDF_PLUGIN_VERSION', '5.0.0' );
define( 'LGDF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LGDF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once LGDF_PLUGIN_DIR . 'includes/helpers.php';
require_once LGDF_PLUGIN_DIR . 'includes/admin-settings.php';
require_once LGDF_PLUGIN_DIR . 'includes/serve-file.php';