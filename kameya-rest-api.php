<?php 

/**
 *
 * Plugin Name:       Kameya Rest Api
 * Plugin URI:        https://kameya.com.ua
 * Description:       Kameya Rest Api
 * Version:           1.0.0
 * Author:            kameya
 * Author URI:        https://kameya.com.ua
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       kameya-rest-api
 * Domain Path:       /languages
 *
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 3.9
 */

// https://test.kameya.com.ua/wp-json/wc/v3/products/batch

// https://stackoverflow.com/questions/42020630/wordpress-saving-translation-post-when-creating-new-post
// POST  https://kameya-api/wp-json/wc/v3/products/?lang=uk
// - варіації атрибути
// - duplicate 
// - набори
// - [+] форматування артикулу
// - форматування та підготовка атрибутів
// - оновлення
// - [+] витягнути всі атрибути з варіацій


// new
// створювати окремо варіації(штрихкоди) (parent_)
// оновлювати окремо варіації(штрихкоди)
// згенерувати розміри на основі додати дочірніх постів

// синхронізувати атрибути товарів автоматично, базуючись на мета полі з розмірами варіацій. Тобто дозаповнюєм при створенні варіації, потім в момент події sync регенеруємо атрибути. Робимо на основі метода sync_price класу WC_Product_Variable_Data_Store_CPT
//

require_once __DIR__ . '/AttributesProcessor.php';
require_once __DIR__ . '/DuplicateProduct.php';
require_once __DIR__ . '/RegisterBatchSkuRoutes.php';
require_once __DIR__ . '/RegisterBatchProductRoutes.php';
require_once __DIR__ . '/RegisterCheckSkuRoute.php';
require_once __DIR__ . '/VariationsBatchHandler.php';

class KameyaRestApi

{
	protected static $instance = null;

	public $attrsProcessor;
	public $duplicator;

	public static function instance() 
	{
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() 
	{
		$this->attrsProcessor = new AttributesProcessor();
		$this->duplicator = new DuplicateProduct();

		add_action('init', function() {
			

			// var_dump( $this->duplicator->setTranslationsGroup(396, false, 'ru', '11300811.248.1191549107') );

			// var_dump( $this->duplicator->syncTerms(367, 344, []) );
			// var_dump( $this->duplicator->syncTerms(343, 344, []) );


			// var_dump( $this->duplicator->syncVariations(wc_get_product(442), wc_get_product(445), []) );
		});

		$this->init();
	}

	public function init() 
	{
		add_filter('woocommerce_rest_pre_insert_product_object', [$this, 'preInsert'], 999, 3 );

		add_action('woocommerce_rest_insert_product_object', [$this, 'afterInsert'], 10, 3 );
		

		register_rest_field( 'product', 'categories_raw',
	        array(
	            // 'get_callback'          => [$this, 'getRawCategories'],
	            // 'update_callback'       => [$this, 'setRawCategories'],
	            'show_in_rest'          => true,
	        )
	    );

	    // add_action('init', function() {
	    // 	$variation  = new WC_Product_Variation( 295 );

			

		// 	// $variation->set_attributes( ['attribute_rozmir'=>'17.5'] );
		// 	// $variation->set_attributes( ['pa_rozmir'=>'17-5', 'pa_proba'=>'585'] );
		// 	// $variation->set_attributes( ['attribute_pa_rozmir'=>'17.5'] );

		// 	// $variation->save();

		// 	var_dump($variation->get_attributes());
		// });	    
	}

	public function preInsert($product, $request, $creating)
	{
		$variations  = $request->get_param('variations_raw');
		$categoriesId = $this->getCategoriesIdsFromString( $request->get_param('categories_raw') );
		$attributes   = $this->getAttrsFromString( $request->get_param('attributes_raw'), $product, $request->get_param('variations_raw') );
		$sku   = self::formatSku( $request->get_param('sku') );

		// die( var_dump( $sku ) );

		if( ! empty( $categoriesId ) ) {
			$product->set_category_ids( $categoriesId );
		}

		if( ! empty( $sku ) ) {
			$product->set_sku( $sku );
		}

		if( $attributes ) {
			$product->set_attributes( $attributes );
		}
 
		return $product;
	}

	public function afterInsert( $product, $request, $creating )
	{
		$variations = $request->get_param('variations_raw');		

		if( ! empty($variations) ) {
			$this->handleVariations($variations, $product);
		}		

		die( var_dump($creating, $request) );

		$duplicatedProduct = $this->duplicator->duplicate( $product->get_id(), $request );	
		$this->duplicator->setTranslationsGroup($product->get_id(), self::formatSku( $request->get_param('sku') ), 'uk');

		$this->duplicator->syncVariations($product, $duplicatedProduct, $request);
	}

	private function getCategoriesIdsFromString( $value )
	{
		if( empty( $value ) ) {
			return [];
		}
		
		$categoriesNames = [];
		$categories = [];

		$terms = str_replace( ';', ',', $value );
		$terms = str_replace( '/', ' > ', $terms );

		$terms = explode(',', $terms);
		$terms = array_diff($terms, array(''));

		if( $terms ) {
			foreach( $terms as $n => $c){
				$categoriesNames[] =  explode(' > ', $c)[0];        

		        $check = explode(' > ',  $c);
		        	
				if( count(array_unique($check)) != 1 && end($check) != $c[0] ) {
					$categoriesNames[] = $c;
				}
		    }
		}

		foreach ( $categoriesNames as $row_term ) {
			$_terms = array_map( 'trim', explode( '>', $row_term ) );
			
			foreach ( $_terms as $index => $_term ) {

				$term = get_term_by( 'slug', sanitize_title( $_term ), 'product_cat' );

				if( ! $term  ) {
					continue;
				}

				if( $term ) {
					$categories[] = $term->term_id;
				}
			}
		}

		return array_unique($categories);
	}

	private function getAttrsFromString( $value, $product, $variations = [] )
	{
		$attrs = $this->attrsProcessor->handle($value, $product, $variations);

		return $attrs;
	}

	public function handleVariations($variations, $product)
	{	
		foreach( $variations as $key => $data ) {

			$menu_order = $key + 1;

			$id = wc_get_product_id_by_sku( $data['meta_sku'] );

			$variation = new WC_Product_Variation( isset( $data['id'] ) ? absint( $data['id'] ) : 0 );

			// Create initial name and status.
			if ( ! $variation->get_slug() ) {
				/* translators: 1: variation id 2: product name */
				$variation->set_name( sprintf( __( 'Variation #%1$s of %2$s', 'woocommerce-legacy-rest-api' ), $variation->get_id(), $product->get_name() ) );
				$variation->set_status( isset( $data['published'] ) && false === $data['published'] ? 'private' : 'publish' );
			}

			// Parent ID.
			$variation->set_parent_id( $product->get_id() );

			// Menu order.
			$variation->set_menu_order( $menu_order );

			// Status.
			if ( isset( $data['published'] ) ) {
				$variation->set_status( ! $data['published'] ? 'private' : 'publish' );
			}

			// SKU.
			if ( isset( $data['meta_sku'] ) ) {
				$variation->set_sku( wc_clean( $data['meta_sku'] ) );
			}

			if ( isset( $data['published'] ) ) {
				$variation->set_stock_status( $data['published'] ? 'instock' : 'outofstock' );

				$variation->set_stock_quantity( 1 );
			} else {
				$variation->set_stock_quantity( 0 );
				$variation->set_status('private');
			}

			// Regular Price.
			if ( isset( $data['price'] ) ) {
				$variation->set_regular_price( $data['price'] );
			}

			// Sale Price.
			if ( isset( $data['sale_price'] ) ) {
				$variation->set_sale_price( $data['sale_price'] );
			}

			if ( isset( $data['weight'] ) ) {
				$variation->set_weight( '' === $data['weight'] ? '' : wc_format_decimal( str_replace(',', '.', $data['weight'] ) ) );
			}

			if( ! empty( $data['size'] ) ) {
				$variation->set_attributes( ['pa_rozmir'=> str_replace('.', '-', $data['size']) ] );
			}

			$id = $variation->save();

			// create sync pll taxonomy
			pll_set_post_language($id, 'uk');
		}

		$product->sync( $variation );
	}

	public static function formatSku( $value )
	{
		if( ! empty( $value ) ) {
			if( ! preg_match( '/^\d+$/', $value ) ) {
				$sku = preg_replace("/^[\w]+$/", '', $value);
			    $sku = self::transliterate( urldecode( $sku ) );

			    $sku = str_replace('/', '', $sku);
			    $sku = str_replace(' ', '', $sku);
			    $sku = str_replace('*', '', $sku);
			    $sku = str_replace(',', '.', $sku);
			    $sku = str_replace('(', '', $sku);
			    $sku = str_replace(')', '', $sku);
			    $sku = str_replace('_', '', $sku);
			    $sku = str_replace('..', '.', $sku);

			    // $sku = str_replace('(1)', '-model', $sku);			    		    
			    
			    return $sku;
			} else {
				return $sku;
			}
			
			// return sanitize_title($sku);
		}
	}

	public static function transliterate($textcyr = null, $textlat = null) {
	    $cyr = array(
	    'ї', 'і', 'ж',  'ч',  'щ',   'ш',  'ю',  'а', 'б', 'в', 'г', 'д', 'е', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ъ', 'ь', 'я',
	    'Ї', 'І', 'Ж',  'Ч',  'Щ',   'Ш',  'Ю',  'А', 'Б', 'В', 'Г', 'Д', 'Е', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ъ', 'Ь', 'Я');
	    $lat = array(
	    'yi', 'i', 'zh', 'ch', 'sht', 'sh', 'yu', 'a', 'b', 'v', 'g', 'd', 'e', 'z', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'y', 'x', 'q',
	    'Yi', 'I', 'Zh', 'Ch', 'Sht', 'Sh', 'Yu', 'A', 'B', 'V', 'G', 'D', 'E', 'Z', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', 'H', 'c', 'Y', 'X', 'Q');
	    if($textcyr) return str_replace($cyr, $lat, $textcyr);
	    else if($textlat) return str_replace($lat, $cyr, $textlat);
	    else return null;
	}
}

KameyaRestApi::instance();
