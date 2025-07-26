<?php
// File ini sekarang berfungsi sebagai pusat untuk mengelola pengaturan aplikasi dan koneksi database.

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

        // --- PENGATURAN BARU UNTUK DATABASE MYSQL ---
        'db_host' => 'localhost', // Ganti dengan host database Anda
        'db_name' => 'u409826558_web_manager', // Ganti dengan nama database Anda
        'db_user' => 'u409826558_web_manager', // Ganti dengan username database Anda
        'db_pass' => 's!P[Hb3v7H4N', // Ganti dengan password database Anda
        'db_port' => 3306 // Port default MySQL
        // --- AKHIR PENGATURAN BARU ---
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
        'fonnte_base_url',
        // --- KUNCI BARU UNTUK DATABASE MYSQL ---
        'db_host',
        'db_name',
        'db_user',
        'db_pass',
        'db_port'
        // --- AKHIR KUNCI BARU ---
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

/**
 * Mengambil daftar channel pembayaran dari Tripay API.
 *
 * @return array Array berisi daftar channel pembayaran atau array kosong jika gagal.
 */
function getTripayPaymentChannels() {
    global $app_settings;

    $apiKey = $app_settings['tripay_api_key'];
    $productionMode = $app_settings['tripay_production_mode'];

    if (empty($apiKey)) {
        error_log("Tripay API Key is not set. Cannot fetch payment channels.");
        return [];
    }

    $apiUrl = $productionMode ? 'https://tripay.co.id/api/merchant/payment-channel' : 'https://tripay.co.id/api-sandbox/merchant/payment-channel';

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_FRESH_CONNECT  => true,
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_FAILONERROR    => false,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4 // Force IPv4 to avoid issues with some hosting
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);

    curl_close($curl);

    if ($error) {
        error_log("cURL Error fetching Tripay channels: " . $error);
        return [];
    }

    $result = json_decode($response, true);

    if (isset($result['success']) && $result['success'] === true && isset($result['data'])) {
        return $result['data'];
    } else {
        error_log("Tripay API Error fetching channels: " . ($result['message'] ?? json_encode($result) ?? 'Unknown error'));
        return [];
    }
}

/**
 * Menghubungkan ke database MySQL.
 * Fungsi ini dipindahkan ke config.php agar hanya dideklarasikan sekali.
 *
 * @return PDO Objek PDO untuk koneksi database.
 */
function connect_db() {
    global $app_settings;
    $host = $app_settings['db_host'];
    $db   = $app_settings['db_name'];
    $user = $app_settings['db_user'];
    $pass = $app_settings['db_pass'];
    $port = $app_settings['db_port'];
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Koneksi database gagal: " . $e->getMessage());
        // Melemparkan kembali exception agar ditangani oleh global exception handler di index.php
        throw new PDOException("Koneksi database gagal. Silakan hubungi administrator.");
    }
}

/**
 * Menginisialisasi skema database (membuat tabel jika belum ada).
 * Fungsi ini dipindahkan ke config.php agar hanya dideklarasikan sekali.
 */
function initialize_database() {
    $pdo = connect_db();
    
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL, -- Contoh: 'admin', 'teknisi', 'penagih'
            full_name VARCHAR(255),
            assigned_regions JSON -- Menyimpan array JSON dari nama wilayah yang ditugaskan
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);
    
    // Tabel customers
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE, -- Username PPPoE pelanggan
            password VARCHAR(255), -- Password PPPoE (hashed jika disimpan di sini, atau null jika hanya di MikroTik)
            profile_name VARCHAR(255) NOT NULL, -- Nama profil PPPoE yang digunakan
            service VARCHAR(50) DEFAULT 'pppoe', -- Tipe service PPPoE (misal: 'pppoe', 'any')
            koordinat VARCHAR(255), -- Koordinat lokasi pelanggan (contoh: "-6.123, 106.456")
            wilayah VARCHAR(255), -- Nama wilayah/area pelanggan
            whatsapp VARCHAR(255), -- Nomor WhatsApp pelanggan
            tgl_registrasi DATE, -- Tanggal registrasi pelanggan
            tgl_tagihan INT, -- Tanggal jatuh tempo tagihan setiap bulan (1-31)
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    // Tabel profiles
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            profile_name VARCHAR(255) NOT NULL UNIQUE, -- Nama profil PPPoE
            rate_limit VARCHAR(255), -- Batas kecepatan (contoh: "5M/10M")
            local_address VARCHAR(255), -- Local address profil
            remote_address VARCHAR(255), -- Remote address atau pool profil
            tagihan_amount INT, -- Jumlah tagihan untuk profil ini
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    // Tabel invoices
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_secret_id INT NOT NULL, -- Mengacu pada ID pelanggan di tabel 'customers'
            username VARCHAR(255) NOT NULL, -- Username pelanggan (untuk kemudahan query)
            profile_name VARCHAR(255) NOT NULL,
            billing_month VARCHAR(7) NOT NULL, -- Format YYYY-MM (misal: '2025-07')
            amount INT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Belum Lunas', -- Contoh: 'Belum Lunas', 'Lunas'
            due_date DATE NOT NULL,
            paid_date DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_by VARCHAR(255), -- Nama user yang mengkonfirmasi pembayaran
            payment_method VARCHAR(100), -- Metode pembayaran (misal: 'Cash', 'Online')
            UNIQUE KEY unique_invoice (user_secret_id, billing_month), -- Memastikan satu tagihan per pelanggan per bulan
            FOREIGN KEY (user_secret_id) REFERENCES customers(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    // Tabel reports
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_username VARCHAR(255) NOT NULL,
            issue_description TEXT NOT NULL,
            report_status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            reported_by VARCHAR(255) NOT NULL,
            assigned_to JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    // Tabel regions
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS regions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            region_name VARCHAR(255) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);
}


// Panggil get_app_settings() sekali untuk memuat pengaturan awal
// dan membuatnya tersedia di seluruh aplikasi melalui variabel global
$app_settings = get_app_settings();
?>