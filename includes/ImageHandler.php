<?php
/**
 * Woo rest endpoint for creating blank product
 *
 * @package kameya
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait KameyaRestHandleImages
{
    public function handleImages($request, $sku) {
        $maps = ['', 'r', '_m', '_m2', '_m3', '_m4', '-preview'];

        $names = [];

        foreach($maps as $map) {
            $names[] = $sku . $map . '.jpg';
        }

        $names[] = $sku . '.mp4';

        $ids = $this->getUploadedImageIds($names);

        
        $data = [
            'main_image' => $ids[$sku . '.jpg'],
            'gallery' => '',
            'video' => [],
        ];

        if( ! empty( $ids[$sku . '.mp4'] ) ) {
            $data['video']['id'] = $ids[$sku . '.mp4'];

            if( ! empty( $ids[$sku . '-preview.jpg'] ) ) {
                $data['video']['preview'] = $ids[$sku . '-preview.jpg'];
            }
        }

        unset($ids[$sku . '.mp4']);
        unset($ids[$sku . '.jpg']);
        unset($ids[$sku . '-preview.jpg']);

        $data['gallery'] = array_values( array_filter($ids) );

        return $data;
    }

     public function getUploadedImageIds($filenames) {
        global $wpdb;

        $ids = [];
        foreach ($filenames as $filename) {
            $like_query = '%' . $wpdb->esc_like($filename) . '%';

            // Знаходимо ID за допомогою SQL-запиту
            $id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID 
                     FROM {$wpdb->posts} 
                     WHERE post_type = 'attachment' 
                     AND guid LIKE %s",
                    $like_query
                )
            );

            if ($id) {
                $ids[$filename] = $id;
            } else {
                $ids[$filename] = null; // Не знайдено
            }
        }

        return $ids;
    }

    private function getIdFromUrl( $url, $product_id )
    {
        if ( empty( $url ) ) {
            return 0;
        }


    }
}