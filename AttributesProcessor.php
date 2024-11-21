<?php

class AttributesProcessor
{
	private array $globalAttributes;

	public function __construct()
	{
		$this->global_attributes = [
			'Розмір',
			'Метал',
			'Проба',
			'Колір вставки',
			'Вставка',
			'Колір металу',
			'Для кого',
			'Тип обручки',
			'Вид застібки',
			'Плетіння',
			'Застібки',
			'Колекція',
			'Є комплект',
			'Під замовлення / В наявності',
		];
	}

	public function handle($value, $product, $variations = [])
	{
		if( ! empty($variations) ) {
			foreach( $variations as $key => $variation ) {
				$value .= $variation['meta_desk'];
			}
		}

		$options = $this->parse($value);

		$options = $this->generateSizes($options, $variations, $product);

		$type = $product->get_type();

		$attributes = [];

		foreach( $options as $key => $option ) {
			$attr_slug = sanitize_title($option[0]);

			$position = $key + 1;
			$isVariation = 0;
			$isVisible = 1;

			if( $attr_slug == 'rozmir' ) {
				$isVariation = 1;
			}

			$taxonomy = $this->getTaxonomyBySlug( $attr_slug );

			$options = explode(',', $option[1]);
			$options = array_map( 'wc_sanitize_term_text_based', $options );
			$options = array_map( 'wc_clean', $options );
			$options = array_filter( $options, 'strlen' );

			if( $taxonomy ) {
				$attribute_id = wc_attribute_taxonomy_id_by_name( $option[0] );

				if ( taxonomy_exists( $taxonomy ) ) {
					wp_set_object_terms( $product->get_id(), $options, $taxonomy );
				}

				if( ! empty( $options ) ) {
					// Add attribute to array, but don't set values.
					$attribute_object = new WC_Product_Attribute();
					$attribute_object->set_id( $attribute_id );
					$attribute_object->set_name( $taxonomy );
					$attribute_object->set_options( $options );
					$attribute_object->set_position( $position );
					$attribute_object->set_visible( $isVisible );
					$attribute_object->set_variation( $isVariation );
					$attributes[] = $attribute_object;
				}

			} else {
				// Custom attribute - Add attribute to array and set the values.
				$attribute_object = new WC_Product_Attribute();
				$attribute_object->set_name( $option[0] );
				$attribute_object->set_options( $options );
				$attribute_object->set_position( $position );
				$attribute_object->set_visible( 1 );
				$attribute_object->set_variation( 0 );
				$attributes[] = $attribute_object;
			}			
		}

		uasort( $attributes, 'wc_product_attribute_uasort_comparison' );

		return $attributes;
	}

	public function parse($value)
	{
		$desk = str_replace( '{p}', '', $value );
		$desk = explode( '{/p}', $desk );
		$desk = array_diff( $desk, array('') );
		
		$options = [];		

		foreach ($desk as $k => $v) {
			$v = strip_tags($v);

			$v = trim($v);
			$v = trim($v, '.');
			$v = trim($v, ';');
			$v = trim($v, ' ');
			$v = str_replace( ': ', ':', $v );			

			$v = explode(':', $v);

			if( $v[0] ) {
				$options[$v[0]] = array_map( 'trim', $v );
			}			
		}

		return $options;		
	}

	public function generateSizes($options, $variations, $product)
	{
		$sizes = [];

		$type = $product->get_type();

		

		if( ! empty( $variations ) ) {
			foreach( $variations as $key => $variation ) {
				if( $variation['published'] && $type == 'variable' ) {
					$sizes[] = $variation['size'];
				}

				if( $type == 'variation' ) {
					$sizes[] = $variation['size'];
				}			
			}

			if( empty($sizes) ) {
				$sizes[] = '777';
			}			
		}

		sort($sizes);

		$sizeOption['Розмір'] = ['Розмір', implode(', ', array_unique( $sizes )) ];

		array_unshift($options , $sizeOption['Розмір']);

		return $options;
	}

	private function getTaxonomyBySlug( $slug ) {
		$taxonomy = null;
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		foreach ( $attribute_taxonomies as $key => $tax ) {
			if ( $slug == $tax->attribute_name ) {
				$taxonomy = 'pa_' . $tax->attribute_name;

				break;
			}
		}

		return $taxonomy;
	}
}
