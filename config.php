<?php
/**
 * File Konfigurasi dan Koneksi Utama Aplikasi.
 *
 * File ini bertanggung jawab untuk:
 * 1. Menetapkan kredensial koneksi ke database MySQL.
 * 2. Membuat objek koneksi database (PDO) yang akan digunakan di seluruh aplikasi.
 * 3. Menyediakan fungsi untuk memeriksa status instalasi aplikasi.
 *
 * @package PPPOE_MANAGER
 */

// Mulai session untuk manajemen login di seluruh aplikasi.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// =================================================================
// PENGATURAN KONEKSI DATABASE (HARAP DIISI OLEH PENGGUNA)
// =================================================================
// Ganti nilai-nilai di bawah ini dengan informasi koneksi
// ke server database MySQL Anda.

/** @var string Alamat server database (biasanya 'localhost' atau '127.0.0.1'). */
define('DB_HOST', 'localhost');

/** @var string Nama database yang akan digunakan. */
define('DB_NAME', 'u409826558_web_manager'); // Pastikan database ini sudah dibuat.

/** @var string Username untuk mengakses database. */
define('DB_USER', 'u409826558_web_manager'); // Ganti dengan username database Anda.

/** @var string Password untuk username database. */
define('DB_PASS', 's!P[Hb3v7H4N'); // Ganti dengan password database Anda.


// =================================================================
// MEMBUAT KONEKSI DATABASE (JANGAN DIUBAH)
// =================================================================

/** @var PDO|null Objek koneksi PDO. */
$pdo = null;

try {
    // Membuat koneksi menggunakan PDO.
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);

    // Mengatur mode error PDO ke exception untuk penanganan error yang lebih baik.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Mengatur mode pengambilan data default ke associative array.
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Jika koneksi gagal, hentikan aplikasi dan tampilkan pesan error.
    // Ini penting agar pengguna tahu bahwa konfigurasi database salah.
    header("Content-Type: text/html; charset=utf8");
    die(
        '<div style="font-family: Arial, sans-serif; border: 2px solid #d9534f; padding: 20px; margin: 50px; border-radius: 8px; background-color: #f2dede; color: #a94442;">' .
        '<h2>Koneksi Database Gagal</h2>' .
        '<p>Aplikasi tidak dapat terhubung ke database MySQL. Silakan periksa detail koneksi di file <strong>config.php</strong> dan pastikan informasinya sudah benar.</p>' .
        '<hr>' .
        '<p><strong>Detail Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>' .
        '</div>'
    );
}


// =================================================================
// FUNGSI BANTUAN (JANGAN DIUBAH)
// =================================================================

/**
 * Memeriksa apakah aplikasi sudah melalui proses setup awal.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @return bool True jika setup sudah selesai, false jika belum.
 */
function is_setup_complete(PDO $pdo) {
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Mengambil semua pengaturan dari tabel 'pengaturan' dan menyimpannya dalam array.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @return array Array asosiatif dari semua pengaturan.
 */
function load_app_settings(PDO $pdo) {
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM pengaturan");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // Biarkan array kosong jika tabel belum ada
    }
    return $settings;
}

/**
 * REVISI: Mengirim notifikasi WhatsApp melalui API Fonnte.
 *
 * @param array $app_settings Array pengaturan aplikasi.
 * @param string $customer_phone Nomor HP pelanggan tujuan.
 * @param string $message Pesan yang akan dikirim.
 * @return bool True jika berhasil, false jika gagal.
 */
function send_whatsapp_notification(array $app_settings, string $customer_phone, string $message) {
    $fonnte_token = $app_settings['fonnte_token'] ?? '';

    if (empty($fonnte_token) || empty($customer_phone)) {
        return false;
    }

    // Format nomor HP ke format internasional (misal: 628xxxx)
    $formatted_phone = preg_replace('/[^0-9]/', '', $customer_phone);
    if (substr($formatted_phone, 0, 1) === '0') {
        $formatted_phone = '62' . substr($formatted_phone, 1);
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.fonnte.com/send",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => [
          'target' => $formatted_phone,
          'message' => $message
      ],
      CURLOPT_HTTPHEADER => array(
        "Authorization: " . $fonnte_token
      ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return false;
    }
    
    $result = json_decode($response, true);
    // Fonnte biasanya mengembalikan 'status' => true pada sukses
    return isset($result['status']) && $result['status'] == true;
}

?>