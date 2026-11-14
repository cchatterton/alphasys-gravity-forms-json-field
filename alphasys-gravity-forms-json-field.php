<?php
/**
 * Plugin Name: AlphaSys Gravity Forms JSON Field
 * Description: Dynamically adds and populates a Gravity Forms JSON hidden field before submission feeds run.
 * Version: 0.1.1
 * Author: AlphaSys
 * Text Domain: alphasys-gravity-forms-json-field
 * Author URI: https://alphasys.com.au
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/cchatterton/alphasys-gravity-forms-json-field
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ASGF_JSON_VERSION', '0.1.1');
define('ASGF_JSON_PLUGIN_FILE', __FILE__);
define('ASGF_JSON_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once ASGF_JSON_PLUGIN_DIR . 'functions/json-field.php';
