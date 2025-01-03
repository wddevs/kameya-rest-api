<?php

// die( file_exists( ABSPATH. 'wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-controller.php') );

require_once ABSPATH. 'wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-controller.php';
require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/class-wc-rest-authentication.php';
require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-posts-controller.php';
require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/rest-api/Controllers/Version1/class-wc-rest-products-v1-controller.php';

class WC_REST_Batch_Sku_Controller extends WC_REST_Products_V1_Controller {

	protected $namespace = 'wc/v3';

    protected $rest_base = 'sku';

    protected $post_type = 'product_variation';

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
					$product_id = wc_get_product_id_by_sku( $item['meta_sku'] );

					$parent_id = wc_get_product_id_by_sku( KameyaRestApi::formatSku( $item['parent_sku'] ) );

					$injected_items[] = is_array( $item ) ? array_merge(
						array(
							'id' => $product_id,
							'parent_id' => $parent_id,
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

    protected function prepare_item_for_database( $request, $creating = false ) {
    	if ( isset( $request['id'] ) && $request['id'] > 0 ) {
			$variation = wc_get_product( absint( $request['id'] ) );
		} else {
			$variation = new WC_Product_Variation();
			$creating = true;
		}

		$variation->set_parent_id( absint( $request['parent_id'] ) );

		$product = wc_get_product( absint( $request['parent_id'] )  );

		// Create initial name and status.
		if ( ! $variation->get_slug() ) {
			/* translators: 1: variation id 2: product name */
			$variation->set_name( sprintf( __( 'Variation #%1$s of %2$s', 'woocommerce-legacy-rest-api' ), $variation->get_id(), $product->get_name() ) );
			$variation->set_status( isset( $request['published'] ) && '0' === $request['published'] ? 'private' : 'publish' );
		}

		if ( isset( $request['meta_sku'] ) ) {
			$variation->set_sku( $request['meta_sku'] );
		}

		// Regular Price.
		if ( isset( $request['price'] ) ) {
			$variation->set_regular_price( $request['price'] );
		}

		// Sale Price.
		if ( isset( $request['sale_price'] ) ) {
			$variation->set_sale_price( $request['sale_price'] );
		}

		if ( isset( $request['published'] ) && $request['published'] == '1' ) {
			$variation->set_stock_status( $request['published'] == '1' ? 'instock' : 'outofstock' );

			$variation->set_manage_stock( 'yes' );

			$variation->set_stock_quantity( 1 );
			$variation->set_status('publish');
		} else if( isset( $request['published'] ) && $request['published'] == '0'  ) {
			$variation->set_stock_quantity( '' );
			$variation->set_status('private');
		}

		if( ! empty( $request['size'] ) ) {
			$variation->set_attributes( ['pa_rozmir'=> str_replace('.', '-', $request['size']) ] );
		}

		if ( isset( $request['weight'] ) ) {
			$variation->set_weight( '' === $request['weight'] ? '' : wc_format_decimal( str_replace(',', '.', $request['weight'] ) ) );
		}

    	return apply_filters( "woocommerce_rest_pre_insert_{$this->post_type}_object", $variation, $request, $creating );
    }
}


add_filter('woocommerce_rest_api_get_rest_namespaces', 'kameya_api_batch_sku_route');

function kameya_api_batch_sku_route( $controllers ) {
    $controllers['wc/v3']['custom'] = 'WC_REST_Batch_Sku_Controller';

    // var_dump($controllers);

    return $controllers;
}