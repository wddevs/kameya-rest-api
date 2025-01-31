<?php

// WooCommerce API URL і ключі
$api_url = 'https://test.kameya.com.ua/wp-json/wc/v3';
$consumer_key = 'ck_bd95eed49befba00bcd381c6176f5c0dbda014f6';
$consumer_secret = 'cs_2e51ecd0414eb76395b0b86a28a5e7b1bc992d15';

// Базова авторизація
$auth = base64_encode("$consumer_key:$consumer_secret");

// Шлях до JSON-файлу
$json_file = __DIR__ . '/test_product_data_chunk.json';

// Перевіряємо наявність файлу
if (!file_exists($json_file)) {
    die("Файл JSON не знайдено: $json_file\n");
}

// Зчитування та декодування JSON
$data = json_decode(file_get_contents($json_file), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Помилка декодування JSON: " . json_last_error_msg() . "\n");
}

// get
// $data = array_filter($data, function($item) {
// 	return !$item['get'];
// });

// $data = array_filter($data, function($item) {
// 	return $item['create']['type'] == 'variable';
// });

// $data = array_values( $data );

// print_r( $data );
// die();
// Конфігурація
$delay_ms = 100; // Затримка між запитами (у мілісекундах)
$total_requests = count($data);

// Початок вимірювання часу
$start_time = microtime(true);

// Функція для виконання HTTP-запиту через cURL
function execute_request($method, $url, $auth, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/json',
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$http_code, $response];
}

// Обробка запитів
$completed_requests = 0;

foreach ($data as $entry) {
    // var_dump($entry);
    if (isset($entry['get'])) {
        // Виконуємо `GET` запит
        list($http_code, $response) = execute_request('GET', $entry['get'], $auth);
    } elseif (isset($entry['create'])) {
        // Виконуємо `POST` запит
        
        

        // Створюємо `POST` запит
        if( isset( $entry['create']['parent_sku'] ) ) {
        	// $new_entry['create'][] = $entry['create'];
        	// list($http_code, $response) = execute_request('POST', "$api_url/product_sku/batch", $auth, $new_entry);

        } else if( isset( $entry['create']['type'] ) ) {

            // die( var_dump( json_encode($new_entry, JSON_UNESCAPED_UNICODE) ) );
        	// list($http_code, $response) = execute_request('POST', "$api_url/products/batch", $auth, $new_entry);
        	// list($http_code, $response) = execute_request('POST', "$api_url/create_blank", $auth, $entry);
        }

        // chunk
        if( is_array( $entry['create'] ) ) {
            // list($http_code, $response) = execute_request('POST', "$api_url/create_blank", $auth, $entry);
            list($http_code, $response) = execute_request('POST', "$api_url/products/batch", $auth, $entry);
        }

        

    } else {
        echo "Пропущено некоректний запис.\n";
        continue;
    }

    // Логування результатів
    if ($http_code >= 200 && $http_code < 300) {
        echo "Запит успішний: $response\n";
        // echo "Запит успішний\n";
    } else {
        echo "Помилка: HTTP $http_code\n";
        echo "Відповідь: $response\n";
    }

    $completed_requests++;
    echo "Оброблено $completed_requests з $total_requests запитів...\n";

    // Затримка між запитами
    // usleep($delay_ms * 1000); // Переводимо мілісекунди у мікросекунди
}

// Кінець вимірювання часу
$end_time = microtime(true);
$total_time = $end_time - $start_time;

// Виведення результатів
echo "Скрипт завершено за " . $total_time . " секунд.\n";