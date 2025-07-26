<?php
/**
 * File Entri Utama (Router) Aplikasi.
 *
 * File ini adalah titik masuk tunggal untuk semua permintaan.
 * Tugasnya adalah menentukan status aplikasi dan mengarahkan pengguna
 * ke halaman yang sesuai (Setup, Login, atau Halaman Utama).
 *
 * Alur Logika:
 * 1. Muat file konfigurasi dan koneksi database (config.php).
 * 2. Periksa apakah proses setup sudah selesai (dengan cek tabel 'users').
 * - Jika BELUM, alihkan ke setup_page.php.
 * 3. Jika setup sudah selesai, periksa apakah pengguna sudah login.
 * - Jika BELUM, alihkan ke login_page.php.
 * 4. Jika setup sudah selesai DAN pengguna sudah login, tampilkan halaman utama aplikasi.
 *
 * @package PPPOE_MANAGER
 */

// 1. Muat file konfigurasi utama.
// File ini akan membuat koneksi $pdo dan menyediakan fungsi is_setup_complete().
require_once 'config.php';

// Tentukan base URL untuk kemudahan redirection.
// Ini akan menghasilkan sesuatu seperti 'http://localhost/pppoe_manager/'
$baseURL = sprintf(
    "%s://%s%s",
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
    $_SERVER['SERVER_NAME'],
    rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/'
);

// 2. Periksa apakah proses setup sudah selesai.
if (!is_setup_complete($pdo)) {
    // Jika tabel 'users' tidak ditemukan, berarti aplikasi belum di-setup.
    // Alihkan pengguna ke halaman setup.
    header('Location: ' . $baseURL . 'setup_page.php');
    exit(); // Hentikan eksekusi skrip lebih lanjut.
}

// 3. Jika setup sudah selesai, periksa status login pengguna.
// Kita memeriksa apakah 'user_id' ada di dalam session.
if (!isset($_SESSION['user_id'])) {
    // Jika pengguna belum login, alihkan ke halaman login.
    header('Location: ' . $baseURL . 'login_page.php');
    exit(); // Hentikan eksekusi skrip lebih lanjut.
}

// 4. Jika semua pemeriksaan lolos (setup selesai dan pengguna sudah login),
// maka kita bisa menampilkan halaman utama aplikasi.
// main_view.php akan menjadi kerangka utama yang memuat halaman-halaman lain.
require_once 'main_view.php';

?>