<?php
/**
 * Woo rest endpoint to get customer by phone
 *
 * @package kameya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WC_REST_Sku_Check_Route {
	public $rest_base = 'rest_check';

	public $namespace = 'wc/v3';

	public function __construct() {

		// echo $this->namespace. '/' .$this->rest_base . '/(?P<sku>[\d]+)';

		add_action( 'rest_api_init', function () {
			register_rest_route( 'wc/v3', '/sku_check/(?P<sku>.+)', 
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => [$this, 'get_сustomers_by_otp_phone'],
					'permission_callback' => '__return_true'
				)
			);
		} );
	}

	public function get_сustomers_by_otp_phone( $request ) {

		try {
			$sku = KameyaRestApi::transliterate( urldecode( $request->get_param('sku') ) );

			$data = [
				'exists' => false,
				'message' => esc_html__('Product not found', 'kameya')
			];

			$id = wc_get_product_id_by_sku( $sku );

			if( $id > 0 ) {
				$data['exists'] = true;
				$data['message'] = esc_html__('Product found', 'kameya');
			}

			return rest_ensure_response( $data );
		} catch ( WC_REST_Exception $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

	public function get_items_permissions_check( $request ) {
		if ( ! wc_rest_check_user_permissions( 'read' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}
}

new WC_REST_Sku_Check_Route();