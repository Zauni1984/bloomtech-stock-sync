<?php
/**
 * Plugin Name: Bloomtech Stock Sync
 * Plugin URI:  https://hanfjack.de/
 * Description: Hält den Warenbestand von Bloomtech-Produkten aktuell, indem die Bestandsliste mehrmals täglich direkt aus der Nextcloud abgerufen und per Artikelnummer mit den WooCommerce-Produkten abgeglichen wird. Führt zusätzlich einen vollständigen Katalog aller Bloomtech-Artikelnummern für künftige Produktanlagen.
 * Version:     1.0.0
 * Author:      hanfjack.de
 * Text Domain: bloomtech-stock-sync
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 11.0
 */

defined( 'ABSPATH' ) || exit;

define( 'BTS_VERSION', '1.0.0' );
define( 'BTS_FILE', __FILE__ );
define( 'BTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BTS_URL', plugin_dir_url( __FILE__ ) );

/** Meta key holding the Bloomtech article number on a product or variation. */
define( 'BTS_META_ARTNR', '_bloomtech_artnr' );

spl_autoload_register(
	static function ( $class ) {
		if ( strpos( $class, 'BTS_' ) !== 0 ) {
			return;
		}
		$file = BTS_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'BTS_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BTS_Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p><strong>Bloomtech Stock Sync</strong> benötigt WooCommerce.</p></div>';
				}
			);
			return;
		}
		BTS_Install::maybe_upgrade();
		BTS_Cron::init();
		if ( is_admin() ) {
			BTS_Admin::init();
			BTS_Product_Fields::init();
		}
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BTS_FILE, true );
		}
	}
);
