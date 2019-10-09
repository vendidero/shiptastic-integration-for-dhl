<?php
/**
 * Initializes blocks in WordPress.
 *
 * @package WooCommerce/Blocks
 */
namespace Vendidero\Germanized\DHL\Api;

use Vendidero\Germanized\DHL\Package;
use Exception;

defined( 'ABSPATH' ) || exit;

abstract class Rest {

    /**
     * The request response
     * @var array
     */
    protected $response = null;

    /**
     * @var PR_DHL_API_Auth_REST
     */
    protected $rest_auth = null;

    /**
     * @var Integrater
     */
    protected $id = '';

    /**
     * @var string
     */
    protected $token_bearer = '';

    /**
     * @var array
     */
    protected $remote_header = array();

    /**
     * DHL_Api constructor.
     *
     * @param string $api_key, $api_secret
     */
    public function __construct( ) {
        try {
            $this->rest_auth = AuthRest::get_instance();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Method to set id
     *
     * @param $id
     */
    public function set_id( $id ) {
        $this->id = $id;
    }

    /**
     * Get the id
     *
     * @return $id
     */
    public function get_id() {
        return $this->id;
    }

    public function get_access_token( $client_id, $client_secret ) {
        $this->token_bearer = $this->rest_auth->get_access_token( $client_id, $client_secret );

        return $this->token_bearer;
    }

    public function delete_access_token( ) {
        $this->rest_auth->delete_access_token();
    }

    protected function get_auth() {
    	return $this->get_basic_auth_encode( Package::get_cig_user(), Package::get_cig_password() );
    }

    public function get_request( $endpoint = '', $query_args = array() ) {
        $api_url   = Package::get_rest_url();

        $this->set_header( $this->get_auth() );

        $wp_request_url     = add_query_arg( $query_args, $api_url . $endpoint );
        $wp_request_headers = $this->get_header();

        Package::log( 'GET URL: ' . $wp_request_url );

        $wp_dhl_rest_response = wp_remote_get(
            $wp_request_url,
            array(
            	'headers' => $wp_request_headers,
                'timeout' => 30
            )
        );

        $response_code = wp_remote_retrieve_response_code( $wp_dhl_rest_response );
        $response_body = json_decode( wp_remote_retrieve_body( $wp_dhl_rest_response ) );

        Package::log( 'GET Response Code: ' . $response_code );
        Package::log( 'GET Response Body: ' . print_r( $response_body, true ) );

        switch ( $response_code ) {
            case '200':
            case '201':
                break;
            case '400':
                $error_message = str_replace('/', ' / ', isset( $response_body->statusText ) ? $response_body->statusText : '' );
                throw new Exception( __( '400 - ', 'woocommerce-germanized-dhl' ) . $error_message );
                break;
            case '401':
                throw new Exception( __( '401 - Unauthorized Access - Invalid token or Authentication Header parameter', 'woocommerce-germanized-dhl' ) );
                break;
            case '408':
                throw new Exception( __( '408 - Request Timeout', 'woocommerce-germanized-dhl' ) );
                break;
            case '429':
                throw new Exception( __( '429 - Too many requests in given amount of time', 'woocommerce-germanized-dhl' ) );
                break;
            case '503':
                throw new Exception( __( '503 - Service Unavailable', 'woocommerce-germanized-dhl' ) );
                break;
            default:
                if ( empty( $response_body->statusText ) ) {
                    $error_message = __( 'GET error or timeout occured. Please try again later.', 'woocommerce-germanized-dhl' );
                } else {
                    $error_message = str_replace('/', ' / ', $response_body->statusText);
                }

                Package::log( 'GET Error: ' . $response_code . ' - ' . $error_message );

                throw new Exception( $response_code .' - ' . $error_message );
                break;
        }

        return $response_body;
    }

	public function post_request( $endpoint = '', $query_args = array() ) {
		$api_url   = Package::get_rest_url();

		$this->set_header( $this->get_auth() );

		$wp_request_url     = $api_url . $endpoint;
		$wp_request_headers = $this->get_header();

		Package::log( 'POST URL: ' . $wp_request_url );

		$wp_dhl_rest_response = wp_remote_post(
			$wp_request_url,
			array(
				'headers' => $wp_request_headers,
				'timeout' => 100,
				'body'    => $query_args,
			)
		);

		$response_code = wp_remote_retrieve_response_code( $wp_dhl_rest_response );
		$response_body = json_decode( wp_remote_retrieve_body( $wp_dhl_rest_response ) );

		Package::log( 'POST Response Code: ' . $response_code );
		Package::log( 'POST Response Body: ' . print_r( $response_body, true ) );

		switch ( $response_code ) {
			case '200':
			case '201':
				break;
			default:
				if ( empty( $response_body->detail ) ) {
					$error_message = __( 'POST error or timeout occured. Please try again later.', 'woocommerce-germanized-dhl' );
				} else {
					$error_message = $response_body->detail;
				}

				Package::log( 'POST Error: ' . $response_code . ' - ' . $error_message );

				throw new Exception( $response_code .' - ' . $error_message );
				break;
		}

		return $response_body;
	}

    protected function get_basic_auth_encode( $user, $pass ) {
        return 'Basic ' . base64_encode( $user . ':' . $pass );
    }

    protected function set_header( $authorization = '' ) {
        $wp_version                  = get_bloginfo('version');
        $wc_version                  = defined( 'WC_Version' ) ? WC_Version : '';

        $dhl_header['Content-Type']  = 'application/json';
        $dhl_header['Accept']        = 'application/json';
        $dhl_header['Authorization'] = 'Bearer ' . $authorization;
        $dhl_header['User-Agent']    = 'WooCommerce/'. $wc_version . ' (WordPress/'. $wp_version . ') DHL-plug-in/' . Package::get_version();

        $this->remote_header         = array_merge( $this->remote_header, $dhl_header );
    }

    protected function get_header( ) {
        return $this->remote_header;
    }
}
