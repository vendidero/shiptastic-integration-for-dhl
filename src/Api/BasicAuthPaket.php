<?php
namespace Vendidero\Shiptastic\DHL\Api;

use Vendidero\Shiptastic\DHL\Package;
use Vendidero\Shiptastic\API\Auth\Basic;
use Vendidero\Shiptastic\API\Auth\OAuth;
use Vendidero\Shiptastic\DataStores\Shipment;
use Vendidero\Shiptastic\SecretBox;
use Vendidero\Shiptastic\ShipmentError;

defined( 'ABSPATH' ) || exit;

class BasicAuthPaket extends Basic {

	public function get_username() {
		return Package::is_debug_mode() ? 'user-valid' : Package::get_gk_api_user();
	}

	public function get_password() {
		return Package::is_debug_mode() ? 'SandboxPasswort2023!' : Package::get_gk_api_signature();
	}

	public function get_headers() {
		$headers                = parent::get_headers();
		$headers['dhl-api-key'] = Package::get_dhl_com_api_key();

		return $headers;
	}
}
