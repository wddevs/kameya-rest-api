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

// Конфігурація
$batch_size = 10; // Кількість паралельних запитів
$total_requests = count($data);
$completed_requests = 0;

$errors = [];
// die(var_dump($total_requests));

// Початок вимірювання часу
$start_time = microtime(true);

// Функція для створення cURL-хендлера
function create_curl_handle($method, $url, $auth, $data = null) {
	// $url = urlencode($url);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/json',
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    return $ch;
}

// Основний цикл обробки запитів
while ($completed_requests < $total_requests) {
    $multi_handle = curl_multi_init();
    $handles = [];
    $active_requests = 0;

    // Додавання групи запитів
    for ($i = $completed_requests; $i < $total_requests && $active_requests < $batch_size; $i++) {
        $entry = $data[$i];
        if (isset($entry['get'])) {
            // Створюємо `GET` запит
            // $ch = create_curl_handle('GET', $entry['get'], $auth);
        } elseif (isset($entry['create'])) {
            // Створюємо `POST` запит
            if( isset( $entry['create']['parent_sku'] ) ) {
                $new_entry['create'][] = $entry['create'];
            	// $ch = create_curl_handle('POST', "$api_url/product_sku/batch", $auth, $new_entry);
            } else {

                // $new_entry['create'][] = $entry['create'];

                // die( var_dump( json_encode($new_entry, JSON_UNESCAPED_UNICODE) ) );
            	// $ch = create_curl_handle('POST', "$api_url/products/batch", $auth, $new_entry);
                $ch = create_curl_handle('POST', "$api_url/create_blank", $auth, $new_entry);
            }

            if( is_array( $entry['create'] ) ) {
                // list($http_code, $response) = execute_request('POST', "$api_url/create_blank", $auth, $entry);
                // $ch = create_curl_handle('POST', "$api_url/create_blank", $auth, $entry);
                $ch = create_curl_handle('POST', "$api_url/products/batch", $auth, $new_entry);
            }
            
        } else {
            continue;
        }

        curl_multi_add_handle($multi_handle, $ch);
        $handles[] = $ch;
        $active_requests++;
    }

    // Виконання паралельних запитів
    do {
        $status = curl_multi_exec($multi_handle, $active);
        curl_multi_select($multi_handle);
    } while ($active && $status === CURLM_OK);

    // Обробка результатів
    foreach ($handles as $ch) {
        $response = curl_multi_getcontent($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $full_info = curl_getinfo($ch);
        if ($http_code >= 200 && $http_code < 300) {
            echo "Успішна відповідь: $response" . str_replace('https://test.kameya.com.ua/wp-json', '', $full_info['url'] ) . "\n " . PHP_EOL;
        } else {
            echo "Помилка: HTTP $http_code\n";
            echo "Відповідь: $response\n";
            $errors[] = "Помилка: HTTP $http_code\n" . "Відповідь: $response\n";
        }

        curl_multi_remove_handle($multi_handle, $ch);
        curl_close($ch);
    }

    // Закриття мульти-хендлера
    curl_multi_close($multi_handle);

    // Оновлюємо кількість оброблених запитів
    $completed_requests += $active_requests;

    echo "Оброблено $completed_requests з $total_requests запитів...\n" . "Помилок: " . count($errors);
}

// Кінець вимірювання часу
$end_time = microtime(true);
$total_time = $end_time - $start_time;

// Виведення результатів
echo "Скрипт завершено за " . $total_time . " секунд.\n";
// var_dump($data);