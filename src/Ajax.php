<?php

namespace Vendidero\Shiptastic\DHL;

use Vendidero\Shiptastic\Admin\Settings;

/**
 * WC_Ajax class.
 */
class Ajax {

	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'refresh_deutsche_post_label_preview',
			'charge_deutsche_post_im',
		);

		foreach ( $ajax_events as $ajax_event ) {
			add_action( 'wp_ajax_woocommerce_stc_dhl_' . $ajax_event, array( __CLASS__, 'suppress_errors' ), 5 );
			add_action( 'wp_ajax_woocommerce_stc_dhl_' . $ajax_event, array( __CLASS__, $ajax_event ) );
		}
	}

	public static function suppress_errors() {
		if ( ! WP_DEBUG || ( WP_DEBUG && ! WP_DEBUG_DISPLAY ) ) {
			@ini_set( 'display_errors', 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.display_errors_Disallowed
		}

		$GLOBALS['wpdb']->hide_errors();
	}

	public static function charge_deutsche_post_im() {
		check_ajax_referer( 'wc-stc-dhl-charge-deutsche-post-im', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) || ! isset( $_POST['amount'] ) ) {
			wp_die( -1 );
		}

		$amount = wc_format_decimal( wc_clean( wp_unslash( $_POST['amount'] ) ) );
		$result = array(
			'success' => false,
			'balance' => '0.0',
			'message' => _x( 'There was an error while recharging the Portokasse.', 'dhl', 'shiptastic-integration-for-dhl' ),
		);

		if ( ! empty( $amount ) ) {
			$response = Package::get_internetmarke_api()->charge_wallet( $amount );

			if ( false !== $response ) {
				$result = array(
					'success' => true,
					'balance' => wc_price( $response ),
					'message' => _x( 'Portokasse recharged successfully.', 'dhl', 'shiptastic-integration-for-dhl' ),
				);
			}
		} else {
			$result['message'] = _x( 'Please choose an amount to charge the Portokasse.', 'dhl', 'shiptastic-integration-for-dhl' );
		}

		wp_send_json( $result );
	}

	public static function refresh_deutsche_post_label_preview() {
		check_ajax_referer( 'wc-stc-dhl-refresh-deutsche-post-label-preview', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) || ! isset( $_POST['product_id'], $_POST['reference_id'] ) ) {
			wp_die( -1 );
		}

		$selected_services = isset( $_POST['selected_services'] ) ? wc_clean( wp_unslash( $_POST['selected_services'] ) ) : array();
		$im_product_id     = absint( $_POST['product_id'] );
		$shipment_id       = absint( $_POST['reference_id'] );
		$product_id        = $im_product_id;
		$response          = array(
			'success'      => true,
			'preview_url'  => '',
			'preview_data' => array(),
			'fragments'    => array(),
		);

		if ( ! empty( $im_product_id ) ) {
			/**
			 * Refresh im product id by selected services.
			 */
			$im_product_id = Package::get_internetmarke_api()->get_product_code( $im_product_id, $selected_services );

			if ( $im_product_id ) {
				$preview_url  = Package::get_internetmarke_api()->preview_stamp( $im_product_id );
				$preview_data = Package::get_internetmarke_api()->get_product_preview_data( $im_product_id );

				if ( $preview_url ) {
					$response['preview_url']  = $preview_url;
					$response['preview_data'] = $preview_data;
				}
			}
		}

		if ( ( $provider = Package::get_deutsche_post_shipping_provider() ) && ( $shipment = wc_stc_get_shipment( $shipment_id ) ) ) {
			$fields = $provider->get_available_additional_services( $product_id, $selected_services );

			$response['fragments']['#wc-stc-shipment-label-wrapper-additional-services'] = Settings::render_label_fields( $fields, $shipment );
		}

		wp_send_json( $response );
	}
}
