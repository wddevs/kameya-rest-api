<?php

class DuplicateProduct
{
	public function __construct()
	{

	}

	public function duplicate( $product_id, $request, $lang = 'ru' )
	{
		$product = wc_get_product( $product_id );

		$name = $request->get_param('name_ru');
		$sku = KameyaRestApi::formatSku( $request->get_param('sku') ) . '_ru';

		if( $product ) {
			$duplicate = clone $product;
			$duplicate->set_id( 0 );
			$duplicate->set_name( $name );
			$duplicate->set_slug( $name );
			$duplicate->set_sku( $sku );

			// $duplicate->set_attributes();
			$duplicate->set_category_ids(false);

			// Save parent product.
			$duplicateId = $duplicate->save();

			$this->setTranslationsGroup($duplicateId, $product->get_sku(), 'ru');

			$this->syncTerms($product_id, $duplicateId);			

			return $duplicate;
		}

		return false;
	}

	public function duplicateFast( $product_id )
	{ 
		$WC_Duplicate = new \WC_Admin_Duplicate_Product;
		$product = wc_get_product( $product_id ); 

		if($product) {
			$duplicate = $WC_Duplicate->product_duplicate( $product );
			do_action( 'woocommerce_product_duplicate', $duplicate, $product );

			unset($WC_Duplicate);// A bit of memory management, just in case.

			return $product;
		} else {
			return false;
		}
	}

	public function syncVariations( $product, $duplicate, $request ) {

		if( $product && $product->is_type('variable') ) {
			foreach ( $product->get_children() as $child_id ) {
				$child           = wc_get_product( $child_id );

				$new_sku = $child->get_sku() . '_ru';

				if( wc_get_product_id_by_sku( $new_sku ) == 0 ) {

					$name = $duplicate->get_name() . ' - ' . $child->get_attribute('pa_rozmir');

					$child_duplicate = clone $child;
					$child_duplicate->set_id( 0 );
					$child_duplicate->set_parent_id( $duplicate->get_id() );
					$child_duplicate->set_sku( $new_sku );
					$child_duplicate->set_slug( $new_sku );
					$child_duplicate->set_name( $duplicate->get_name() . ' - ' . $child->get_attribute('pa_rozmir') );

					$child_duplicate->set_attributes( ['pa_rozmir'=> $child->get_attribute('pa_rozmir') . '-ru' ] );			

					$child_duplicate_id = $child_duplicate->save();

					// var_dump('child_id', $child_id);
					// var_dump('child_duplicate_id', $child_duplicate_id);

					pll_set_post_language($child_duplicate_id, 'ru');
				}
			}

			return true;
		}
	}

	public function syncVariation( $product, $duplicate, $request, $creating = false )
	{
		if( $creating === false ) {

		}		
	}

	public function syncTerms($product_id, $duplicateId)
	{
		$translatedTaxonomies = PLL()->model->get_translated_taxonomies();
		$taxonomies = get_post_taxonomies( $product_id );

		$taxonomies = array_intersect( $taxonomies, $translatedTaxonomies );

		// Update the term cache to reduce the number of queries in the loop
		update_object_term_cache( array( $product_id ), get_post_type( $product_id ) );

		foreach( $taxonomies as $tax ) {
			if ( $terms = get_the_terms( $product_id, $tax ) ) {
				$terms = array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) );

				$new_terms = [];

				foreach( $terms as $term ) {
					if( $term_id = PLL()->model->term->get_translation( $term, 'ru' ) ) {						
						$new_terms[] = $term_id;
					}
				}

				wp_set_object_terms( $duplicateId, $new_terms, $tax );			
			}
		}

		return $terms;
	}

	public function setTranslationsGroup($post_id, $sku, $lang = 'uk')
	{
		$taxonomy = 'post_translations';
		$group = 'pll_' . $sku;
		$term = get_term_by( 'name', $group, $taxonomy );

		if ( empty( $term ) ) {
			$translations = array( $lang => $post_id );
			$term = wp_insert_term( $group, $taxonomy, array( 'description' => serialize( $translations ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			if ( ! is_wp_error( $term ) ) {
				wp_set_object_terms( $post_id, $term['term_id'], $taxonomy );
			}
		} else {
			$translations = unserialize( $term->description ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			$translations[ $lang ] = $post_id;
			pll_save_post_translations( $translations );
		}

		pll_set_post_language($post_id, $lang);
	}
}