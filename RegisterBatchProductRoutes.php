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

    	$categoriesId = KameyaRestApi::instance()->getCategoriesIdsFromString( $request->get_param('categories_raw') );
    	$attributes   = KameyaRestApi::instance()->getAttrsFromString( $request->get_param('attributes_raw'), $product, $request->get_param('variations_raw') );   	

		if( $product && $product->get_type() == 'variable' ) {

			$name = $request->get_param('name');
			
			$product->set_slug( $product->get_title() );
			$product->set_name( $name );

			if( ! empty( $categoriesId ) ) {
				$product->set_category_ids( $categoriesId );
			}

			if( $attributes ) {
				$product->set_attributes( $attributes );
			}

			$this->updateSimilarProduct( $product, $request );
		}

		return apply_filters( "woocommerce_rest_pre_insert_{$this->post_type}", $product, $request );
    }

    private function updateSimilarProduct( $product, $request )
    {
    	$duplicator = KameyaRestApi::instance()->duplicator;

    	$sku = KameyaRestApi::formatSku( $request->get_param('sku') ) . '_ru';
    	$name = $request->get_param('name_ru');

    	$duplicateId = wc_get_product_id_by_sku( $sku );

    	if( $duplicateId > 0 ) {
    		$duplicatedProduct = wc_get_product( $duplicateId );
    		$duplicatedProduct->set_name( $name );
    		$duplicatedProduct->set_slug( $name );
    		$duplicatedProduct->set_attributes( $product->get_attributes() );

    		$duplicator->syncTerms( $product->get_id(), $duplicateId );
    		// var_dump( $duplicatedProduct );

    		// var_dump( $duplicateId, $duplicatedProduct->get_id() );

    		$duplicatedProduct->save();
    	}
    }
}


add_filter('woocommerce_rest_api_get_rest_namespaces', 'kameya_api_batch_product_sku_route');

function kameya_api_batch_product_sku_route( $controllers ) {
    $controllers['wc/v3']['custom_product'] = 'WC_REST_Batch_Product_Sku_Controller';

    // var_dump($controllers);

    return $controllers;
}