<?php
// File ini sekarang berfungsi sebagai pusat untuk mengelola pengaturan aplikasi.
// Pengaturan akan dibaca dari dan disimpan ke file settings.json.

// Path ke file settings.json
define('SETTINGS_FILE', 'settings.json');

/**
 * Mengambil semua pengaturan aplikasi dari settings.json.
 * Jika file tidak ada atau pengaturan tertentu tidak ditemukan, nilai default akan digunakan.
 *
 * @return array Array asosiatif berisi semua pengaturan.
 */
function get_app_settings() {
    $default_settings = [
        'monitor_interface' => null,
        'router_ip' => '103.125.173.30:1003',
        'router_user' => 'RJ.NET-MERANTI',
        'router_pass' => 'CALONSULTAN!',
        'tripay_merchant_code' => 'T23738',
        'tripay_api_key' => 'DEV-5opwpPaNoSgHm9HtQSw6WpXupfohSgb6UrQAyEur',
        'tripay_private_key' => '6Ax7u-XQOlQ-BEMAk-82Oke-7ONno',
        'tripay_production_mode' => false,
        // Pengaturan Fonnte WhatsApp Gateway
        'fonnte_api_key' => '', // Ganti dengan API Key Fonnte Anda
        'fonnte_instance_id' => '', // Tetap ada di settings, tapi tidak akan dikirim ke API jika tidak diperlukan
        'fonnte_base_url' => 'https://api.fonnte.com/send', // URL API Fonnte
    ];

    if (file_exists(SETTINGS_FILE)) {
        $current_settings = json_decode(file_get_contents(SETTINGS_FILE), true);
        // Gabungkan pengaturan default dengan pengaturan yang ada,
        // memastikan semua kunci ada dan nilai yang ada dipertahankan.
        return array_merge($default_settings, $current_settings);
    } else {
        // Jika file settings.json belum ada, buat dengan pengaturan default
        save_app_settings($default_settings);
        return $default_settings;
    }
}

/**
 * Menyimpan pengaturan aplikasi ke settings.json.
 *
 * @param array $settings Array asosiatif berisi pengaturan yang akan disimpan.
 * @return bool True jika berhasil disimpan, false jika gagal.
 */
function save_app_settings($settings) {
    // Pastikan hanya menyimpan kunci yang relevan untuk menghindari penulisan data yang tidak diinginkan
    $sanitized_settings = [];
    $allowed_keys = [
        'monitor_interface',
        'router_ip',
        'router_user',
        'router_pass',
        'tripay_merchant_code',
        'tripay_api_key',
        'tripay_private_key',
        'tripay_production_mode',
        'fonnte_api_key',
        'fonnte_instance_id', // Tetap izinkan penyimpanan
        'fonnte_base_url'
    ];

    foreach ($allowed_keys as $key) {
        if (isset($settings[$key])) {
            $sanitized_settings[$key] = $settings[$key];
        }
    }

    return file_put_contents(SETTINGS_FILE, json_encode($sanitized_settings, JSON_PRETTY_PRINT));
}

/**
 * Mengirim pesan WhatsApp menggunakan Fonnte API.
 *
 * @param string $to Nomor WhatsApp tujuan (dengan kode negara, misal: 6281234567890)
 * @param string $message Teks pesan yang akan dikirim
 * @return array Respons dari API Fonnte (decoded JSON)
 */
function sendWhatsAppMessage($to, $message) {
    global $app_settings;

    $apiKey = $app_settings['fonnte_api_key'];
    $baseUrl = $app_settings['fonnte_base_url'];
    // Instance ID tidak lagi dikirim dalam payload jika tidak diperlukan oleh API Fonnte
    // $instanceId = $app_settings['fonnte_instance_id']; // Disimpan di settings, tapi tidak digunakan di sini

    if (empty($apiKey) || empty($baseUrl)) { // Hanya cek API Key dan Base URL
        error_log("Fonnte API Key or Base URL are not set. Cannot send WhatsApp message.");
        return ['success' => false, 'message' => 'Fonnte API Key or Base URL missing.'];
    }

    // Normalisasi nomor telepon: hapus semua non-digit, tambahkan 62 jika dimulai dengan 0
    $target_number = preg_replace('/[^0-9]/', '', $to);
    if (substr($target_number, 0, 1) === '0') {
        $target_number = '62' . substr($target_number, 1);
    }
    // Pastikan target_number tidak kosong setelah normalisasi
    if (empty($target_number)) {
        error_log("Invalid phone number format after normalization: " . $to);
        return ['success' => false, 'message' => 'Invalid phone number format.'];
    }


    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_FRESH_CONNECT  => true,
        CURLOPT_URL            => $baseUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'target' => $target_number, // Mengubah 'to' menjadi 'target'
            'message' => $message,
            'countryCode' => '62' // Menambahkan countryCode sesuai dokumentasi Fonnte
        ]),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: '.$apiKey // Mengirim API Key di header Authorization
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        error_log("cURL Error #:" . $err);
        return ['success' => false, 'message' => 'cURL Error: ' . $err];
    } else {
        $result = json_decode($response, true);
        // Fonnte API respons sukses biasanya memiliki status 200 OK dan 'status' => true di JSON
        // atau 'status' => 'success' tergantung versi API
        if (isset($result['status']) && ($result['status'] === true || $result['status'] === 'success')) {
            error_log("WhatsApp message sent successfully to " . $to . ". Response: " . json_encode($result));
            return ['success' => true, 'message' => 'Pesan berhasil dikirim.', 'data' => $result];
        } else {
            error_log("WhatsApp API Error: " . ($result['message'] ?? json_encode($result) ?? 'Unknown error') . " for " . $to);
            return ['success' => false, 'message' => $result['message'] ?? 'Unknown API error', 'data' => $result];
        }
    }
}

/**
 * Mengambil nomor WA dari string komentar MikroTik.
 *
 * @param string $comment String komentar dari MikroTik secret.
 * @return string|null Nomor WhatsApp yang ditemukan atau null jika tidak ada.
 */
function parse_comment_for_wa($comment) {
    $parts = explode('|', $comment);
    foreach ($parts as $part) {
        if (strpos($part, 'WA:') === 0) {
            return substr($part, 3);
        }
    }
    return null;
}


// Panggil get_app_settings() sekali untuk memuat pengaturan awal
// dan membuatnya tersedia di seluruh aplikasi melalui variabel global
$app_settings = get_app_settings();
?>