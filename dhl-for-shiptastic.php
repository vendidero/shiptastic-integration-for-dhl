<?php
/**
 * Plugin Name: DHL for Shiptastic
 * Plugin URI: https://github.com/vendidero/dhl-for-shiptastic
 * Description: Create DHL and Deutsche Post labels for Shiptastic.
 * Author: vendidero
 * Author URI: https://vendidero.de
 * Version: 3.6.0
 * Requires PHP: 5.6
 * License: GPLv3
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '5.6.0', '<' ) ) {
	return;
}

$autoloader = __DIR__ . '/vendor/autoload_packages.php';

if ( is_readable( $autoloader ) ) {
	require $autoloader;
} else {
	return;
}

register_activation_hook( __FILE__, array( '\Vendidero\Shiptastic\DHL\Package', 'install' ) );
add_action( 'plugins_loaded', array( '\Vendidero\Shiptastic\DHL\Package', 'init' ) );
