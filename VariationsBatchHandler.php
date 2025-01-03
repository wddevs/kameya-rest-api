<?php


class VariationsBatchHandler
{
	public function __construct() 
	{
		// add_action('woocommerce_rest_pre_insert_product_variation', [$this, 'preInsertVariation'], 10, 3 );

		// add_action('woocommerce_rest_insert_product', [$this, 'createVariation'], 10, 3 );

		add_action('woocommerce_rest_pre_insert_product_variation_object', [$this, 'handleVariation'], 10, 3 );
		// add_action('woocommerce_rest_insert_product', [$this, 'insertVariation'], 10, 3 );

		// sync
		// add_action( 'woocommerce_variable_product_sync_data', [$this, 'syncParent'], 10, 3 );
		add_action( 'woocommerce_after_product_object_save', [$this, 'syncParent'], 10, 2 );
	}

	

	public function createVariation( $post, $request, $creating ) {
		// if( $creating === true ) {
		// 	if( isset( $request['meta_sku'] ) ) {
		// 		set_post_type( $post->ID, 'product_variation' );
		// 	}
		// }

		die( var_dump( $creating ) );
	}

	public function preInsertVariation($product, $request)
	{	
		// Regular Price.
		if ( isset( $request['meta_sku'] ) ) {
			$product->set_sku( $request['meta_sku'] );
		}

		// Regular Price.
		if ( isset( $request['price'] ) ) {
			$product->set_regular_price( $request['price'] );
		}

		// Sale Price.
		if ( isset( $request['sale_price'] ) ) {
			$product->set_sale_price( $request['sale_price'] );
		}

		if ( isset( $request['published'] ) && $request['published'] == '1' ) {
			$product->set_stock_status( $request['published'] == '1' ? 'instock' : 'outofstock' );

			$product->set_manage_stock( 'yes' );

			$product->set_stock_quantity( 1 );
			$product->set_status('publish');
		} else if( isset( $request['published'] ) && $request['published'] == '0'  ) {
			$product->set_stock_quantity( '' );
			$product->set_status('private');
		}

		// parent sku
		if( isset( $request['parent_sku'] ) ) {
			$parent_id = wc_get_product_id_by_sku( KameyaRestApi::formatSku( $item['parent_sku'] ));

			$product->set_parent_id( $parent_id );					
		}

		if( ! empty( $request['size'] ) ) {
			$product->set_attributes( ['pa_rozmir'=> str_replace('.', '-', $data['size']) ] );
		}

		return $product;
	}

	public function insertVariation($product, $request, $creating)
	{
		// die( var_dump($product, $creating) );
		if ( 'variation' === $product->get_type() ) {
			if( false === $creating ) {			
				// Regular Price.
				if ( isset( $request['price'] ) ) {
					$product->set_regular_price( $request['price'] );
				}

				// Sale Price.
				if ( isset( $request['sale_price'] ) ) {
					$product->set_sale_price( $request['sale_price'] );
				}

				if ( isset( $request['published'] ) ) {
					$product->set_stock_status( $request['published'] ? 'instock' : 'outofstock' );

					$product->set_stock_quantity( 1 );
				} else {
					$product->set_stock_quantity( 0 );
					$product->set_status('private');
				}
			}

			if( true === $creating ) {			
				if( isset( $request['parent_sku'] ) ) {
					$parent_id = wc_get_product_id_by_sku( KameyaRestApi::formatSku( $request['parent_sku'] ) );

					$product->set_parent_id( $parent_id );					
				}
			}
		}

		return $product;
	}


	/**
	 * Handle variation object based on input. 
	 * 
	*/
	public function handleVariation($variation, $request, $creating)
	{
		if( $creating === true ) {
			$this->duplicateVariation( $variation, $request );
		} else {
			$this->syncVariation( $variation, $request );
		}

		return $variation;
	}

	public function syncParent( $product, $data_store )
	{
		// die( var_dump( $product ) );
	}

	public function injectSizes( $variation, $request, $lang = '' )
	{
		// потрібно знати чи є ще доступні варіації такого ж розміру, коли буде списуватися
		// вставляти атрибути розмірів при кожному оновленні? як це буде впливати на швидкість
		// https://stackoverflow.com/questions/64134538/how-update-product-attributes-programmatically
		// 

		$taxonomy = 'pa_rozmir';

		$parent_id = wc_get_product_id_by_sku( KameyaRestApi::formatSku( $request['parent_sku'] ) . $lang );		

		if( $parent_id > 0 ) {
			$product = wc_get_product( $parent_id );

			$attributes = $product->get_attributes();			

			$size = $request['size'] . $lang;

			$attribute_term = get_term_by('slug', str_replace(['.', '_'], '-', $size ), 'pa_rozmir');

			if( empty( $attributes['pa_rozmir']->get_options() ) && $attribute_term ) {

				$attribute_id = wc_attribute_taxonomy_id_by_name( 'pa_rozmir' );

				$attribute_object = new WC_Product_Attribute();
				$attribute_object->set_id( $attribute_id );
				$attribute_object->set_name( 'pa_rozmir' );
				$attribute_object->set_options( [$attribute_term->term_id]);
				$attribute_object->set_visible( 1 );
				$attribute_object->set_variation( 1 );

				$attributes[] = $attribute_object;

				if ( taxonomy_exists( $taxonomy ) ) {
					wp_set_object_terms( $parent_id, [$attribute_term->name], $taxonomy );
				}

				uasort( $attributes, 'wc_product_attribute_uasort_comparison' );

				$product->set_attributes( $attributes );				

				$product->save();	
			}

			// пере
			if( isset( $attributes['pa_rozmir'] ) && ! empty( $attributes['pa_rozmir'] ) && $attribute_term ) {
				$sizes = $attributes['pa_rozmir']->get_options();				

				if( ! in_array( $attribute_term->term_id, $sizes ) ) {

					$sizes[] = $attribute_term->term_id;

					$attributes['pa_rozmir']->set_options( $sizes );

					$product->set_attributes( $attributes );

					$product->save();

					$existedTerms = wp_get_object_terms( $parent_id, $taxonomy );

					$newTerms = [];

					foreach( $existedTerms as $term ) {
						$newTerms[] = $term->name;
					}

					$newTerms[] = $attribute_term->name;
					sort($newTerms);

					wp_set_object_terms( $parent_id, $newTerms, $taxonomy );
				}
			}

			// die( var_dump( $attributes ) );
 		}
	}

	/**
	 * Sync original variation with duplicated. 
	 * Fields: price, sale_price, published
	 * 
	*/
	private function syncVariation( $variation, $request )
	{
		$new_sku = $request['meta_sku'] . "_ru";

		$id = wc_get_product_id_by_sku( $new_sku );

		if( $id > 0 ) {
			$variation_ru = wc_get_product( $id );

			// Regular Price.
			if ( isset( $request['price'] ) ) {
				$variation_ru->set_regular_price( $request['price'] );
			}

			// Sale Price.
			if ( isset( $request['sale_price'] ) ) {
				$variation_ru->set_sale_price( $request['sale_price'] );
			}

			if ( isset( $request['published'] ) && $request['published'] == '1' ) {
				$variation_ru->set_stock_status( $request['published'] == '1' ? 'instock' : 'outofstock' );

				$variation_ru->set_manage_stock( 'yes' );

				$variation_ru->set_stock_quantity( 1 );
				$variation_ru->set_status('publish');
			} else if( isset( $request['published'] ) && $request['published'] == '0'  ) {
				$variation_ru->set_stock_quantity( '' );
				$variation_ru->set_status('private');
			}

			$variation_ru->save();
		}
	}

	/**
	 * Duplicate variation to other language and assign to localized product. 
	 * 
	*/
	private function duplicateVariation( $variation, $request )
	{
		$new_sku = $request['meta_sku'] . "_ru";

		if( wc_get_product_id_by_sku( $new_sku ) == 0 ) {
			$parent_id = pll_get_post( $request['parent_id'], 'ru' );

			$parent = get_post( $parent_id );
			$size = $variation->get_attribute('pa_rozmir');
			$name = $parent->post_title . ' - ' . $size;

			$child_duplicate = clone $variation;
			$child_duplicate->set_id( 0 );
			$child_duplicate->set_parent_id( $parent_id );
			$child_duplicate->set_sku( $new_sku );
			$child_duplicate->set_slug( $name );
			$child_duplicate->set_name( $name );

			$child_duplicate->set_attributes( ['pa_rozmir'=> str_replace('.','-',$size) . '-ru' ] );	

			// die( var_dump( $child_duplicate ) );		

			$child_duplicate_id = $child_duplicate->save();

			pll_set_post_language($child_duplicate_id, 'ru');

			// die( var_dump( $child_duplicate ) );			
		}

		$this->injectSizes( $variation, $request );
		$this->injectSizes( $child_duplicate, $request, '_ru' );
	}

}

new VariationsBatchHandler();