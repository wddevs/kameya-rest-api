<?php

// die( file_exists( ABSPATH. 'wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-controller.php') );

require_once ABSPATH. 'wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-controller.php';
require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/class-wc-rest-authentication.php';
require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-posts-controller.php';
require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version1/class-wc-rest-products-v1-controller.php';

class WC_REST_Batch_Product_Sku_Controller extends WC_REST_Products_V1_Controller {

	protected $namespace = 'wc/v3';

    protected $rest_base = 'product_sku';

    protected $post_type = 'product';

    public function register_routes() {
    	register_rest_route( $this->namespace, '/' . $this->rest_base . '/batch', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'batch_items' ),
				'permission_callback' => array( $this, 'batch_items_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_batch_schema' ),
		) );
    }

    public function batch_items( $request ) {
    	$items       = array_filter( $request->get_params() );
    	$params      = $request->get_url_params();
		$query       = $request->get_query_params();
		$product_id  = $params['product_id'];
		$body_params = array();

		foreach ( array( 'update', 'create', 'delete' ) as $batch_type ) {
			if ( ! empty( $items[ $batch_type ] ) ) {
				$injected_items = array();

				foreach ( $items[ $batch_type ] as $item ) {					

					$product_id = wc_get_product_id_by_sku( KameyaRestApi::formatSku( $item['sku'] ) );

					$item['sku'] = KameyaRestApi::formatSku( $item['sku'] );

					$injected_items[] = is_array( $item ) ? array_merge(
						array(
							'id' => $product_id,
						), $item
					) : $item;
				}

				$body_params[ $batch_type ] = $injected_items;
			}
		}

		$request = new WP_REST_Request( $request->get_method() );
		$request->set_body_params( $body_params );
		$request->set_query_params( $query );

		return parent::batch_items( $request );
    }

    protected function prepare_item_for_database( $request ) {
    	$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

    	// Type is the most important part here because we need to be using the correct class and methods.
		$product = wc_get_product( $id );

		if( $product && $product->get_type() == 'variable' ) {
			die( var_dump( $product, KameyaRestApi::instance()->duplicator ) );
		}

		

		return apply_filters( "woocommerce_rest_pre_insert_{$this->post_type}", $product, $request );
    }


}


add_filter('woocommerce_rest_api_get_rest_namespaces', 'kameya_api_batch_product_sku_route');

function kameya_api_batch_product_sku_route( $controllers ) {
    $controllers['wc/v3']['custom_product'] = 'WC_REST_Batch_Product_Sku_Controller';

    // var_dump($controllers);

    return $controllers;
}