<?php

namespace Vendidero\Shiptastic\DHL;

use Vendidero\Shiptastic\ShippingProvider\Helper;
use Vendidero\Shiptastic\ShippingProvider\Simple;

defined( 'ABSPATH' ) || exit;

/**
 * Main package class.
 */
class Install {

	public static function install() {
		$current_version = get_option( 'woocommerce_shiptastic_dhl_version', null );

		self::create_db();

		if ( ! is_null( $current_version ) ) {
			self::update( $current_version );
		} else {
			if ( Package::is_standalone() && ( $dhl = Package::get_dhl_shipping_provider() ) ) {
				$dhl->activate(); // Activate on new install
			}

			if ( \Vendidero\Shiptastic\DHL\Admin\Importer\DHL::is_available() ) {
				\Vendidero\Shiptastic\DHL\Admin\Importer\DHL::import_settings();
			}

			if ( \Vendidero\Shiptastic\DHL\Admin\Importer\Internetmarke::is_available() ) {
				if ( Package::is_standalone() && ( $dp = Package::get_deutsche_post_shipping_provider() ) ) {
					$dp->activate(); // Activate on new install
				}

				\Vendidero\Shiptastic\DHL\Admin\Importer\Internetmarke::import_settings();
			}
		}

		update_option( 'woocommerce_shiptastic_dhl_version', Package::get_version() );
	}

	private static function update( $current_version ) {
		if ( version_compare( $current_version, '1.5.6', '<' ) ) {
			Helper::instance()->load_shipping_providers();

			$dhl = wc_stc_get_shipping_provider( 'dhl' );

			if ( ! is_a( $dhl, '\Vendidero\Shiptastic\DHL\ShippingProvider\DHL' ) ) {
				return;
			}

			$int_product = $dhl->get_setting( 'label_default_product_int' );
			$eu_product  = $dhl->get_setting( 'label_default_product_eu' );

			if ( empty( $eu_product ) ) {
				if ( ! empty( $int_product ) && in_array( $int_product, array_keys( $dhl->get_products( array( 'zone' => 'eu' ) )->as_options() ), true ) ) {
					$dhl->update_setting( 'label_default_product_eu', $int_product );
					$dhl->update_setting( 'label_default_product_int', 'V53WPAK' );
				} elseif ( ! empty( $int_product ) && in_array( $int_product, array_keys( $dhl->get_products( array( 'zone' => 'int' ) )->as_options() ), true ) ) {
					$dhl->update_setting( 'label_default_product_eu', 'V55PAK' );
				}

				$dhl->save();
			}
		}

		/**
		 * Maybe update DP to use the new tracking URL
		 */
		if ( version_compare( $current_version, '3.0.5', '<' ) ) {
			Helper::instance()->load_shipping_providers();

			$dp = wc_stc_get_shipping_provider( 'deutsche_post' );

			if ( ! is_a( $dp, '\Vendidero\Shiptastic\DHL\ShippingProvider\DeutschePost' ) ) {
				return;
			}

			if ( $dp->is_activated() ) {
				if ( strstr( $dp->get_tracking_url_placeholder(), 'form.einlieferungsdatum_tag' ) ) {
					$dp->set_tracking_url_placeholder( $dp->get_default_tracking_url_placeholder() );
					$dp->save();
				}
			}
		}

		if ( version_compare( $current_version, '3.1.0', '<' ) ) {
			Helper::instance()->load_shipping_providers();

			if ( $dhl = wc_stc_get_shipping_provider( 'dhl' ) ) {
				if ( $dhl->is_activated() ) {
					$dhl->update_setting( 'parcel_pickup_max_results', $dhl->get_setting( 'parcel_pickup_map_max_results', 20 ) );
					$dhl->save();

					if ( $dp = wc_stc_get_shipping_provider( 'deutsche_post' ) ) {
						if ( $dp->is_activated() && 'yes' === $dhl->get_setting( 'parcel_pickup_packstation_enable' ) ) {
							$dp->update_setting( 'parcel_pickup_packstation_enable', 'yes' );
							$dp->save();
						}
					}
				}
			}
		}

		if ( version_compare( $current_version, '3.5.0', '<' ) ) {
			Helper::instance()->load_shipping_providers();

			if ( $dhl = wc_stc_get_shipping_provider( 'dhl' ) ) {
				if ( $dhl->is_activated() ) {
					if ( $dhl->get_setting( 'participation_V62WP', '' ) ) {
						$dhl->update_setting( 'participation_V62KP', $dhl->get_setting( 'participation_V62WP', '' ) );
						$dhl->save();
					}
				}
			}
		}

		if ( version_compare( $current_version, '4.2.0', '<' ) ) {
			global $wpdb;

			delete_option( 'woocommerce_stc_dhl_enable_legacy_soap' );

			Helper::instance()->load_shipping_providers();

			if ( $dhl = wc_stc_get_shipping_provider( 'dhl' ) ) {
				if ( $dhl->is_activated() ) {
					if ( $national = $dhl->get_configuration_set(
						array(
							'shipment_type' => 'simple',
							'zone'          => 'dom',
						)
					) ) {
						if ( 'V62WP' === $national->get_product() ) {
							$national->update_product( 'V62KP' );

							$dhl->update_configuration_set( $national );
							$dhl->save();
						}
					}

					/**
					 * Replace V62WP in packaging options
					 */
					$wpdb->hide_errors();
					$packaging_ids = $wpdb->get_results( "SELECT stc_packaging_id FROM {$wpdb->stc_packagingmeta} WHERE meta_value LIKE '%V62WP%'" );

					if ( ! empty( $packaging_ids ) ) {
						foreach ( $packaging_ids as $packaging_row ) {
							$packaging_id = (int) $packaging_row->stc_packaging_id;

							if ( $packaging = wc_stc_get_packaging( $packaging_id ) ) {
								if ( $national = $packaging->get_configuration_set(
									array(
										'shipment_type' => 'simple',
										'zone'          => 'dom',
										'shipping_provider_name' => 'dhl',
									)
								) ) {
									if ( 'V62WP' === $national->get_product() ) {
										$national->update_product( 'V62KP' );
										$packaging->update_configuration_set( $national );
										$packaging->save();
									}
								}
							}
						}
					}

					/**
					 * Replace V62WP in shipping method options
					 */
					$methods = $wpdb->get_results( "SELECT * FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_%_%_settings' and option_value LIKE '%dhl-s-simple-z-dom%V62WP%'" );

					foreach ( $methods as $method ) {
						$value       = (array) get_option( $method->option_name, array() );
						$has_updated = false;

						if ( isset( $value['configuration_sets'] ) && is_array( $value['configuration_sets'] ) ) {
							if ( array_key_exists( '-p-dhl-s-simple-z-dom', $value['configuration_sets'] ) ) {
								$value['configuration_sets']['-p-dhl-s-simple-z-dom'] = wp_parse_args(
									(array) $value['configuration_sets']['-p-dhl-s-simple-z-dom'],
									array(
										'product' => '',
									)
								);

								if ( 'V62WP' === $value['configuration_sets']['-p-dhl-s-simple-z-dom']['product'] ) {
									$value['configuration_sets']['-p-dhl-s-simple-z-dom']['product'] = 'V62KP';
									$has_updated = true;
								}
							}
						}

						if ( $has_updated ) {
							update_option( $method->option_name, $value );
						}
					}
				}
			}
		}
	}

	private static function create_db() {
		global $wpdb;
		$wpdb->hide_errors();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::get_schema() );
	}

	private static function get_schema() {
		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$tables = "
CREATE TABLE {$wpdb->prefix}woocommerce_stc_dhl_im_products (
  product_id bigint(20) unsigned NOT NULL auto_increment,
  product_im_id bigint(20) unsigned NOT NULL,
  product_code int(16) NOT NULL,
  product_name varchar(150) NOT NULL DEFAULT '',
  product_slug varchar(150) NOT NULL DEFAULT '',
  product_version int(5) NOT NULL DEFAULT 1,
  product_annotation varchar(500) NOT NULL DEFAULT '',
  product_description varchar(500) NOT NULL DEFAULT '',
  product_information_text text NULL,
  product_type varchar(50) NOT NULL DEFAULT 'sales',
  product_destination varchar(20) NOT NULL DEFAULT 'national',
  product_price int(8) NOT NULL,
  product_length_min int(8) NULL,
  product_length_max int(8) NULL,
  product_length_unit varchar(8) NULL,
  product_width_min int(8) NULL,
  product_width_max int(8) NULL,
  product_width_unit varchar(8) NULL,
  product_height_min int(8) NULL,
  product_height_max int(8) NULL,
  product_height_unit varchar(8) NULL,
  product_weight_min int(8) NULL,
  product_weight_max int(8) NULL,
  product_weight_unit varchar(8) NULL,
  product_parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_service_count int(3) NOT NULL DEFAULT 0,
  product_is_wp_int int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (product_id),
  KEY product_im_id (product_im_id),
  KEY product_code (product_code)
) $collate;
CREATE TABLE {$wpdb->prefix}woocommerce_stc_dhl_im_product_services (
  product_service_id bigint(20) unsigned NOT NULL auto_increment,
  product_service_product_id bigint(20) unsigned NOT NULL,
  product_service_product_parent_id bigint(20) unsigned NOT NULL,
  product_service_slug varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY  (product_service_id),
  KEY product_service_product_id (product_service_product_id),
  KEY product_service_product_parent_id (product_service_product_parent_id)
) $collate;";

		return $tables;
	}
}
