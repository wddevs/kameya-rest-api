<?php
/**
 * Woo rest endpoint for creating blank product
 *
 * @package kameya
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_REST_Create_Blank_Product {
    public $rest_base = 'rest_blank';

    public $namespace = 'wc/v3';

    public function __construct() {

        add_action( 'rest_api_init', function () {
            register_rest_route( 'wc/v3', '/create_blank',
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'createBlankProductBatch'],
                    'permission_callback' => '__return_true'
                )
            );
        } );
    }

    public function createBlankProductBatch( $request ) {
        $body = $request->get_body();

        $body = json_decode( $body, true );

        $response_array = [];



        if( is_array( $body['create'] ) ) {
            

            foreach(  $body['create'] as $key => $batch_item ) {



                $sku = KameyaRestApi::transliterate( urldecode( $batch_item['sku'] ) );
                $name = $batch_item['name'];

                $type =  $batch_item['type'];

                $product_id = wc_get_product_id_by_sku( $sku );



                try {
                    if( $product_id > 0 ) {

                        $response_array[] = [
                            'sku' => $sku,
                            'exists' => true,
                            'message' => esc_html__('Product with this SKU already exists.', 'kameya')
                        ];

                    } else {
                        if( $type == 'variable' ) {
                            $product = new WC_Product_Variable();
                        } else {
                            $product = new WC_Product_Variation();
                        }

                        $product->set_sku( $sku );
                        $product->set_name( $name );
                        $product->set_slug( $name );

                        $product->update_meta_data( '_json_body', $body );

                        $product_id = $product->save();

                        $response_array[] = [
                            'exists' => false,
                            'message' => esc_html__('Product created', 'kameya'),
                            'product_id' => $product->get_id(),
                            'sku' => $sku,
                        ];
                    }
                } catch ( WC_REST_Exception $e ) {
                    return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
                }
            }
        } else {
            return rest_ensure_response( false );
        }


        return rest_ensure_response( $response_array );
        // die( var_dump( $body ) );
    }

    /**
     * Create blank product
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function createBlankProduct( $request ) {
        $body = $request->get_body();

        $body = json_decode( $body, true );     

         // die( var_dump( $body  ) );   

        $body = $body['create'];

        $sku = KameyaRestApi::transliterate( urldecode( $body['sku'] ) );
        $name = $body['name'];

        $type =  $body['type'];

        $product_id = wc_get_product_id_by_sku( $sku );

        // die( var_dump( $product_id, $sku  ) );

        try {
            if( $product_id > 0 ) {

                $data = [
                    'exists' => true,
                    'message' => esc_html__('Product with this SKU already exists.', 'kameya')
                ];

                return rest_ensure_response( $data );

            } else {
                if( $type == 'variable' ) {
                    $product = new WC_Product_Variable();
                } else {
                    $product = new WC_Product_Variation();
                }

                $product->set_sku( $sku );
                $product->set_name( $name );
                $product->set_slug( $name );

                $product->update_meta_data( '_json_body', $body );

                $product_id = $product->save();

                // die( var_dump( $product  ) );

                // KameyaRestApi::instance()->duplicator->duplicateBlank( $product, $body, 'ru' );

                // KameyaRestApi::instance()->duplicator->setTranslationsGroup($product_id, $sku, 'uk');

                $data = [
                    'exists' => false,
                    'message' => esc_html__('Product created', 'kameya'),
                    'product_id' => $product->get_id(),
                ];

                return rest_ensure_response( $data );
            }

            return rest_ensure_response( false );
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

new WC_REST_Create_Blank_Product();

// add_filter('woocommerce_rest_api_get_rest_namespaces', 'WC_REST_Create_Blank_Product');

// function WC_REST_Create_Blank_Product( $controllers ) {
//     $controllers['wc/v3']['Blank_Product'] = 'WC_REST_Create_Blank_Product';

//     // var_dump($controllers);

//     return $controllers;
// }