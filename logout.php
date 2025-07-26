<?php
/**
 * Skrip Logout Pengguna.
 *
 * Menghancurkan session yang ada dan mengalihkan pengguna
 * kembali ke halaman login.
 *
 * @package PPPOE_MANAGER
 */

// Selalu mulai session untuk dapat mengakses dan menghancurkannya.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel session.
$_SESSION = [];

// Hancurkan session.
session_destroy();

// Alihkan ke halaman login.
header('Location: login_page.php');
exit();
?>
