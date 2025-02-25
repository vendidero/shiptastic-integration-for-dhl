<?php
/**
 * Initializes blocks in WordPress.
 *
 * @package WooCommerce/Blocks
 */
namespace Vendidero\Shiptastic\DHL\Api;

use Exception;
use Vendidero\Shiptastic\DHL\Package;
use Vendidero\Shiptastic\Interfaces\Api;

defined( 'ABSPATH' ) || exit;

abstract class Soap implements Api {

	protected $is_sandbox = false;

	/**
	 * Passed arguments to the API
	 *
	 * @var string
	 */
	protected $args = array();

	/**
	 * The query string
	 *
	 * @var string
	 */
	private $query = array();

	/**
	 * The request response
	 * @var array
	 */
	protected $response = null;

	/**
	 * @var
	 */
	protected $soap_auth = null;

	/**
	 * @var array
	 */
	protected $body_request = array();

	/**
	 * DHL_Api constructor.
	 */
	public function __construct() {
		try {
			if ( ! Package::supports_soap() ) {
				throw new Exception( wp_kses_post( sprintf( _x( 'To enable communication between your shop and DHL, the PHP <a href="%1$s">SOAPClient</a> is required. Please contact your host and make sure that SOAPClient is <a href="%2$s">installed</a>.', 'dhl', 'dhl-for-shiptastic' ), 'https://www.php.net/manual/class.soapclient.php', esc_url( admin_url( 'admin.php?page=wc-status' ) ) ) ) );
			}

			$this->soap_auth = new AuthSoap( $this->get_wsdl_file( $this->get_url() ), $this );
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	abstract public function get_url();

	protected function get_wsdl_file( $wsdl_link ) {
		return $wsdl_link;
	}

	public function get_auth_api() {
		return $this->soap_auth;
	}

	abstract public function get_client();

	protected function walk_recursive_remove( array $the_array ) {
		foreach ( $the_array as $k => $v ) {
			if ( is_array( $v ) ) {
				$the_array[ $k ] = $this->walk_recursive_remove( $v );
			}

			// Explicitly allow street_number fields to equal 0
			if ( empty( $v ) && ( ! in_array( $k, array( 'minorRelease', 'streetNumber', 'houseNumber', 'zip', 'active', 'postNumber' ), true ) ) ) {
				unset( $the_array[ $k ] );
			}
		}

		return $the_array;
	}

	public function get_setting_name() {
		return $this->get_name() . ( $this->is_sandbox() ? '_sandbox' : '' );
	}

	public function is_sandbox() {
		return $this->is_sandbox;
	}

	public function set_is_sandbox( $is_sandbox ) {
		$this->is_sandbox = $is_sandbox;
	}
}
