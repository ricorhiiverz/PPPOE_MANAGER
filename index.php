<?php
session_start();
// Pastikan zona waktu adalah WIB (Asia/Jakarta) untuk seluruh aplikasi
date_default_timezone_set('Asia/Jakarta');

// =================================================================
// GLOBAL ERROR HANDLING (Untuk membantu debugging 500 errors)
// =================================================================
set_exception_handler(function ($exception) {
    error_log("UNCAUGHT EXCEPTION: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    http_response_code(500);
    echo '<!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error Server</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #16191c; color: #d1d2d3; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .card { background-color: #212529; border: 1px solid #2a2e34; }
        </style>
    </head>
    <body>
        <div class="container text-center">
            <div class="card p-4 shadow-lg">
                <h1 class="card-title text-danger"><i class="fas fa-exclamation-circle"></i> Error 500</h1>
                <p class="card-text">Terjadi kesalahan tak terduga di server. Mohon maaf atas ketidaknyamananannya.</p>
                <p class="card-text">Silakan coba lagi nanti atau hubungi administrator.</p>
                <hr>
                <p class="card-text small text-muted">Detail error telah dicatat.</p>
                <a href="index.php" class="btn btn-primary mt-3">Kembali ke Beranda</a>
            </div>
        </div>
    </body>
    </html>';
    exit();
});

register_shutdown_function(function () {
    $error = error_get_last();
    // Check if it's a fatal error (E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING)
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING])) {
        error_log("FATAL ERROR: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        // If headers already sent, we can't send a 500 response code, but we can still show the message
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error Server</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background-color: #16191c; color: #d1d2d3; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
                .card { background-color: #212529; border: 1px solid #2a2e34; }
            </style>
        </head>
        <body>
            <div class="container text-center">
                <div class="card p-4 shadow-lg">
                    <h1 class="card-title text-danger"><i class="fas fa-exclamation-circle"></i> Error 500</h1>
                    <p class="card-text">Terjadi kesalahan fatal di server. Mohon maaf atas ketidaknyamananannya.</p>
                    <p class="card-text">Silakan coba lagi nanti atau hubungi administrator.</p>
                    <hr>
                    <p class="card-text small text-muted">Detail error telah dicatat.</p>
                    <a href="index.php" class="btn btn-primary mt-3">Kembali ke Beranda</a>
                </div>
            </div>
        </body>
        </html>';
        exit();
    }
});
// =================================================================
// END GLOBAL ERROR HANDLING
// =================================================================


// =================================================================
// SETUP & DATABASE
// =================================================================
// Sertakan file konfigurasi yang sekarang memuat pengaturan aplikasi
require_once('config.php'); // Menggunakan require_once untuk menghindari duplikasi

// Akses pengaturan aplikasi global
global $app_settings;

// Cek apakah ada user di database. Jika tidak ada, arahkan ke setup page.
try {
    $pdo = connect_db(); // Panggil fungsi dari config.php
    initialize_database(); // Panggil fungsi dari config.php
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();

    if ($user_count === 0) {
        // Jika belum ada user, tampilkan halaman setup admin
        if (isset($_POST['setup_admin'])) {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            if (!empty($username) && !empty($password)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, assigned_regions) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin', 'Administrator', json_encode([])]);
                    header('Location: index.php'); exit;
                } catch (PDOException $e) {
                    $setup_error = "Setup gagal: " . $e->getMessage();
                }
            } else {
                $setup_error = "Username dan password tidak boleh kosong.";
            }
        }
        include 'setup_page.php';
        exit;
    }
} catch (PDOException $e) {
    // Jika koneksi database gagal saat startup (misal, database belum dibuat)
    // Tampilkan halaman setup dengan pesan error yang sesuai.
    $setup_error = "Koneksi database atau inisialisasi gagal: " . $e->getMessage() . ". Pastikan database MySQL sudah dibuat dan kredensial di config.php benar.";
    include 'setup_page.php';
    exit;
}


// =================================================================
// LOGIN & LOGOUT LOGIC
// =================================================================
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username']; $password = $_POST['password'];
    $pdo = connect_db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'] ?? $user['username']; // Fallback to username
        $_SESSION['role'] = $user['role'];
        $_SESSION['assigned_regions'] = json_decode($user['assigned_regions'] ?? '[]', true); // Load assigned regions
        header('Location: index.php'); exit;
    } else { $login_error = "Username atau password salah."; }
}

if (!isset($_SESSION['username'])) { include 'login_page.php'; exit; }


// =================================================================
// EXPORT ACTION HANDLER
// =================================================================
if (isset($_GET['action']) && $_GET['action'] === 'export_invoices') {
    if (!in_array($_SESSION['role'], ['admin', 'penagih'])) {
        die('Akses ditolak.');
    }

    $pdo = connect_db();
    $search_user = $_GET['search_user'] ?? '';
    $filter_month = $_GET['filter_month'] ?? '';
    $filter_status = $_GET['filter_status'] ?? 'all';
    $filter_by_user = $_GET['filter_by_user'] ?? 'all';
    
    $where_clauses = [];
    $params = [];
    
    if (!empty($search_user)) {
        $where_clauses[] = "username LIKE ?";
        $params[] = '%' . $search_user . '%';
    }
    if (!empty($filter_month)) {
        $where_clauses[] = "billing_month = ?";
        $params[] = $filter_month;
    }
    if ($filter_status !== 'all') {
        $where_clauses[] = "status = ?";
        $params[] = $filter_status;
    }
    if ($filter_by_user !== 'all') {
        $where_clauses[] = "updated_by = ?";
        $params[] = $filter_by_user;
    }
    // New: Filter by assigned regions for 'penagih' role
    // For export, we need to apply the filter based on the role here directly,
    // as the $filtered_secrets might not be available at this point for export action.
    if ($_SESSION['role'] === 'penagih') {
        // Fetch secrets for the assigned regions specifically for export
        $temp_filtered_secrets_for_export = [];
        // Re-initialize API for this block if not already done
        $API_export = new RouterosAPI();
        global $app_settings; // Ensure app_settings is available
        if ($API_export->connect($app_settings['router_ip'], $app_settings['router_user'], $app_settings['router_pass'])) {
            $all_secrets_for_export = $API_export->comm('/ppp/secret/print');
            $API_export->disconnect(); // Disconnect after use
        } else {
            error_log("EXPORT ERROR: Could not connect to MikroTik for export.");
            $all_secrets_for_export = []; // Set to empty to avoid errors
        }

        if (!empty($_SESSION['assigned_regions'])) {
            foreach ($all_secrets_for_export as $secret) {
                $comment_data = parse_comment_string($secret['comment'] ?? '');
                $customer_wilayah = $comment_data['wilayah'] ?? '';
                if (in_array($customer_wilayah, $_SESSION['assigned_regions'])) {
                    $temp_filtered_secrets_for_export[] = $secret;
                }
            }
        }
        $filtered_secret_names_for_export = array_column($temp_filtered_secrets_for_export, 'name');
        if (!empty($filtered_secret_names_for_export)) {
            $username_placeholders = implode(',', array_fill(0, count($filtered_secret_names_for_export), '?'));
            $where_clauses[] = "username IN ($username_placeholders)";
            $params = array_merge($params, $filtered_secret_names_for_export);
        } else {
            $where_clauses[] = "1 = 0"; // No results if no assigned regions or no customers in them
        }
    }


    $sql = "SELECT * FROM invoices";
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }
    $sql .= " ORDER BY username ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "laporan_tagihan_" . ($filter_month ? str_replace('-', '_', $filter_month) : 'semua_bulan') . "_" . date('Ymd') . ".csv";

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Username', 'Bulan Tagihan', 'Jumlah', 'Status', 'Metode Pembayaran', 'Waktu Bayar', 'Dikonfirmasi Oleh', 'Jatuh Tempo'
    ]);

    foreach ($invoices as $invoice) {
        fputcsv($output, [
            $invoice['username'],
            date('F Y', strtotime($invoice['billing_month'] . '-01')),
            $invoice['amount'],
            $invoice['status'],
            $invoice['payment_method'],
            $invoice['paid_date'] ? date('Y-m-d H:i:s', strtotime($invoice['paid_date'])) : '',
            $invoice['updated_by'],
            $invoice['due_date'],
        ]);
    }
    fclose($output);
    exit;
}


// =================================================================
// APLIKASI UTAMA (SETELAH LOGIN)
// =================================================================
if (!file_exists('RouterosAPI.php')) { die('File RouterosAPI.php tidak ditemukan.'); }
require('RouterosAPI.php'); // Panggil RouterosAPI setelah config dimuat

// Helper Functions
// Fungsi get_settings dan save_settings yang lama diganti dengan get_app_settings dan save_app_settings dari config.php
// function get_settings() { $settings_file = 'settings.json'; if (!file_exists($settings_file)) { return ['monitor_interface' => null]; } return json_decode(file_get_contents($settings_file), true); }
// function save_settings($data) { file_put_contents('settings.json', json_encode($data, JSON_PRETTY_PRINT)); }
// function get_wilayah() { $wilayah_file = 'wilayah.json'; if (!file_exists($wilayah_file)) { return []; } return json_decode(file_get_contents($wilayah_file), true); } // Diganti dengan DB
// function save_wilayah($data) { file_put_contents('wilayah.json', json_encode($data, JSON_PRETTY_PRINT)); } // Diganti dengan DB

// Fungsi build_comment_string dan parse_comment_string tetap di sini karena spesifik untuk struktur komentar MikroTik
function build_comment_string($post_data, $prefix = '') {
    $comment_parts = [
        "KOORDINAT:" . ($post_data[$prefix . 'koordinat'] ?? ''),
        "WILAYAH:" . ($post_data[$prefix . 'wilayah'] ?? ''),
        "WA:" . ($post_data[$prefix . 'whatsapp'] ?? ''),
        "REG:" . ($post_data[$prefix . 'registrasi'] ?? ''),
        "TGL_TAGIHAN:" . ($post_data[$prefix . 'tgl_tagihan'] ?? '')
    ];
    return implode("|", $comment_parts);
}

function parse_comment_string($comment) {
    $details = [ 'koordinat' => '','wilayah' => '','whatsapp' => '','registrasi' => '','tagihan' => '','tgl_tagihan' => '' ];
    $parts = explode('|', $comment);
    foreach ($parts as $part) {
        if (strpos($part, ':') !== false) {
            list($key, $value) = explode(':', $part, 2);
            $key_map = [ 'KOORDINAT' => 'koordinat','WILAYAH' => 'wilayah','WA' => 'whatsapp','REG' => 'registrasi','TAGIHAN' => 'tagihan','TGL_TAGIHAN' => 'tgl_tagihan' ];
            if (isset($key_map[$key])) { $details[$key_map[$key]] = $value; }
        }
    }
    return $details;
}


if (isset($_GET['action']) && $_GET['action'] !== 'export_invoices') { /* ... AJAX Handler Logic ... */
    header('Content-Type: application/json'); $action = $_GET['action'];
    $API_AJAX = new RouterosAPI();
    // Gunakan pengaturan dari $app_settings
    global $app_settings;
    if (!$API_AJAX->connect($app_settings['router_ip'], $app_settings['router_user'], $app_settings['router_pass'])) { echo json_encode(['error' => 'Koneksi API gagal.']); exit; }
    
    if ($action === 'get_traffic') {
        // Gunakan pengaturan dari $app_settings
        $interface_to_monitor = $app_settings['monitor_interface'] ?? null;
        if (!$interface_to_monitor) { echo json_encode(['error' => 'Interface untuk monitoring belum diatur.']); exit; }
        $traffic = $API_AJAX->comm('/interface/monitor-traffic', ['interface' => $interface_to_monitor, 'once' => '']);
        if (isset($traffic[0])) { echo json_encode(['upload' => $traffic[0]['tx-bits-per-second'], 'download' => $traffic[0]['rx-bits-per-second'], 'status' => 'ok']); } 
        else { echo json_encode(['error' => 'Gagal mengambil data traffic untuk interface: ' . $interface_to_monitor]); }
    }
    
    if ($action === 'get_router_health') {
        $health = $API_AJAX->comm('/system/resource/print');
        if (isset($health[0])) {
            $free_memory = $health[0]['free-memory'];
            $total_memory = $health[0]['total-memory'];
            $memory_percentage = ($total_memory > 0) ? round((1 - ($free_memory / $total_memory)) * 100) : 0;
            echo json_encode([
                'cpu_load' => $health[0]['cpu-load'],
                'free_memory_percent' => $memory_percentage,
                'uptime' => $health[0]['uptime'],
                'status' => 'ok'
            ]);
        } else {
            echo json_encode(['error' => 'Gagal mengambil data kesehatan router.']);
        }
    }

    // --- PERBAIKAN: get_user_details akan mengambil data dari DB dan MikroTik ---
    if ($action === 'get_user_details' && isset($_GET['id'])) {
        $pdo = connect_db();
        $user_id_db = $_GET['id']; // Ini adalah ID dari database kita, bukan MikroTik
        
        // Ambil data pelanggan dari database
        $stmt_db = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt_db->execute([$user_id_db]);
        $customer_db = $stmt_db->fetch(PDO::FETCH_ASSOC);

        if (!$customer_db) {
            echo json_encode(['error' => 'Pelanggan tidak ditemukan di database.']);
            exit;
        }

        // Ambil data real-time dari MikroTik
        $secret_details_array = $API_AJAX->comm("/ppp/secret/print", ["?name" => $customer_db['username']]);
        
        $details = $customer_db; // Mulai dengan data dari database

        if (!empty($secret_details_array)) {
            $secret_details = $secret_details_array[0];
            $active_user_array = $API_AJAX->comm("/ppp/active/print", ["?name" => $secret_details['name']]);
            
            // Gabungkan status real-time dari MikroTik
            $details['mikrotik_id'] = $secret_details['.id']; // ID MikroTik
            $details['status_mikrotik'] = (isset($secret_details['disabled']) && $secret_details['disabled'] === 'true') ? 'disabled' : 'enabled';
            $details['status-online'] = !empty($active_user_array);
            $details['address'] = $active_user_array[0]['address'] ?? 'N/A';
            $details['uptime'] = $active_user_array[0]['uptime'] ?? 'N/A';
            $details['last-logged-out'] = $secret_details['last-logged-out'] ?? 'N/A';
            $details['profile'] = $secret_details['profile'] ?? 'N/A'; // Ambil profil dari MikroTik
            $details['service'] = $secret_details['service'] ?? 'N/A'; // Ambil service dari MikroTik
            
            // Ambil tagihan dari profil MikroTik (jika masih diperlukan dari sana)
            $profile_info_array = $API_AJAX->comm("/ppp/profile/print", ["?name" => $details['profile']]);
            if (!empty($profile_info_array)) {
                $profile_comment_data = parse_comment_string($profile_info_array[0]['comment'] ?? '');
                $details['tagihan_profile'] = $profile_comment_data['tagihan'];
            } else {
                $details['tagihan_profile'] = 'N/A';
            }

        } else {
            // Pelanggan tidak ditemukan di MikroTik, anggap offline/disabled
            $details['mikrotik_id'] = null;
            $details['status_mikrotik'] = 'not_found';
            $details['status-online'] = false;
            $details['address'] = 'N/A';
            $details['uptime'] = 'N/A';
            $details['last-logged-out'] = 'N/A';
            $details['profile'] = $customer_db['profile_name'] ?? 'N/A'; // Fallback ke profil di DB
            $details['service'] = 'pppoe'; // Default service
            $details['tagihan_profile'] = 'N/A';
        }
        
        echo json_encode($details);
    } elseif ($action === 'test_fonnte_api') { // New AJAX action for Fonnte API test
        $input = json_decode(file_get_contents('php://input'), true);
        $to = $input['to'] ?? '';
        $message = $input['message'] ?? '';
        $test_api_key = $input['api_key'] ?? '';
        // Instance ID tidak lagi diambil dari input, karena sudah dihapus dari form
        // $test_instance_id = $input['instance_id'] ?? '';
        $test_base_url = $input['base_url'] ?? '';

        // Temporarily override global app_settings for testing with provided credentials
        $original_app_settings = $app_settings; // Save original settings
        $app_settings['fonnte_api_key'] = $test_api_key;
        $app_settings['fonnte_instance_id'] = ''; // Set instance ID to empty for test
        $app_settings['fonnte_base_url'] = $test_base_url;

        $result = sendWhatsAppMessage($to, $message);

        $app_settings = $original_app_settings; // Restore original settings

        echo json_encode($result);
        exit;
    }
    $API_AJAX->disconnect(); exit;
}

$API = new RouterosAPI(); $API->debug = false;
// Gunakan pengaturan dari $app_settings
global $app_settings;
$page = $_GET['page'] ?? 'dashboard';
$connection_status = null; $message = '';
if (isset($_SESSION['message'])) { $message = $_SESSION['message']; unset($_SESSION['message']); }

// Role-based page access control
// Penagih hanya melihat penagih_dashboard, penagih_tagihan, pelanggan, peta
$allowed_pages_for_penagih = ['penagih_dashboard', 'penagih_tagihan', 'pelanggan', 'peta'];
// Teknisi hanya melihat teknisi_dashboard dan gangguan
$allowed_pages_for_teknisi = ['teknisi_dashboard', 'gangguan'];
// Admin melihat semua
$allowed_pages_for_admin = ['dashboard', 'tagihan', 'pelanggan', 'peta', 'profil', 'wilayah', 'user', 'pengaturan', 'laporan'];


if ($_SESSION['role'] === 'penagih') {
    if (!in_array($page, $allowed_pages_for_penagih)) {
        $page = 'penagih_dashboard'; // Redirect penagih ke dashboard mereka
    }
} elseif ($_SESSION['role'] === 'teknisi') {
    if (!in_array($page, $allowed_pages_for_teknisi)) {
        $page = 'teknisi_dashboard'; // Redirect teknisi ke dashboard mereka
    }
} elseif ($_SESSION['role'] === 'admin') {
    if (!in_array($page, $allowed_pages_for_admin)) {
        $page = 'dashboard'; // Redirect admin ke dashboard default
    }
}


try {
    // Gunakan pengaturan dari $app_settings
    if ($API->connect($app_settings['router_ip'], $app_settings['router_user'], $app_settings['router_pass'])) {
        $connection_status = true;
        // Pre-fetch all secrets and profiles once if needed by multiple pages,
        // and build a map for efficient lookup.
        $all_secrets_mikrotik = $API->comm('/ppp/secret/print'); // Data dari MikroTik
        $all_profiles_mikrotik = $API->comm('/ppp/profile/print'); // Data dari MikroTik
        $profiles_map_by_name_mikrotik = [];
        foreach ($all_profiles_mikrotik as $profile) {
            $profiles_map_by_name_mikrotik[$profile['name']] = $profile;
        }

        // --- PERBAIKAN: Ambil data pelanggan, profil, dan wilayah dari database SQL secara global ---
        $pdo = connect_db();

        // Ambil semua pelanggan dari DB
        $stmt_customers = $pdo->query("SELECT * FROM customers");
        $all_customers_db = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

        // Ambil semua profil dari DB
        $stmt_profiles = $pdo->query("SELECT * FROM profiles");
        $profiles = $stmt_profiles->fetchAll(PDO::FETCH_ASSOC); // Variabel $profiles ini akan digunakan di main_view.php

        // Ambil semua wilayah dari DB
        $stmt_regions = $pdo->query("SELECT * FROM regions");
        $wilayah_list = $stmt_regions->fetchAll(PDO::FETCH_ASSOC); // Variabel $wilayah_list ini akan digunakan di main_view.php
        // --- AKHIR PERBAIKAN GLOBAL FETCH ---


        // Filter pelanggan dari database berdasarkan wilayah yang ditugaskan untuk 'penagih'
        $filtered_customers_db = [];
        if ($_SESSION['role'] === 'penagih') {
            if (!empty($_SESSION['assigned_regions'])) {
                foreach ($all_customers_db as $customer) {
                    if (in_array($customer['wilayah'], $_SESSION['assigned_regions'])) {
                        $filtered_customers_db[] = $customer;
                    }
                }
            }
        } else {
            $filtered_customers_db = $all_customers_db; // Admin dan Teknisi melihat semua pelanggan dari DB
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $action = $_POST['action'];
            $admin_actions = ['add_user', 'edit_user', 'delete_user', 'disable_user', 'enable_user', 'add_profile', 'edit_profile', 'delete_profile', 'save_settings', 'add_app_user', 'edit_app_user', 'delete_app_user', 'add_wilayah', 'delete_wilayah', 'edit_wilayah', 'generate_invoices', 'cancel_payment', 'add_report', 'edit_report', 'delete_report']; // Added report actions
            $penagih_actions = ['mark_as_paid'];
            $teknisi_actions = ['update_report_status']; // Removed assign_report from teknisi actions
            $admin_report_actions = ['assign_report']; // Explicitly define assign_report for admin only

            $is_allowed = false;
            if ($_SESSION['role'] === 'admin') {
                $is_allowed = true; // Admin can do anything
            } elseif (in_array($_SESSION['role'], ['penagih', 'teknisi'])) { // Gabungkan penagih dan teknisi
                if (in_array($action, $penagih_actions) && $_SESSION['role'] === 'penagih') {
                    $is_allowed = true;
                } elseif (in_array($action, $teknisi_actions) && $_SESSION['role'] === 'teknisi') {
                    $is_allowed = true;
                }
            }


            if (!$is_allowed) {
                 $_SESSION['message'] = ['type' => 'error', 'text' => 'Akses ditolak. Anda tidak memiliki izin untuk melakukan aksi ini.'];
            } else { /* ... Switch Case for Actions ... */
                 switch ($action) {
                    case 'add_user':
                        // Tambah ke MikroTik
                        $mikrotik_comment = build_comment_string($_POST);
                        $API->comm("/ppp/secret/add", [
                            "name" => trim($_POST['user']),
                            "password" => trim($_POST['password']),
                            "service" => $_POST['service'],
                            "profile" => $_POST['profile'],
                            "comment" => $mikrotik_comment
                        ]);

                        // Tambah ke Database MySQL
                        $pdo = connect_db();
                        $stmt = $pdo->prepare("INSERT INTO customers (username, password, profile_name, service, koordinat, wilayah, whatsapp, tgl_registrasi, tgl_tagihan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            trim($_POST['user']),
                            password_hash(trim($_POST['password']), PASSWORD_DEFAULT), // Simpan hashed password di DB
                            $_POST['profile'],
                            $_POST['service'],
                            $_POST['koordinat'] ?? null,
                            $_POST['wilayah'] ?? null,
                            $_POST['whatsapp'] ?? null,
                            $_POST['registrasi'] ?? null,
                            $_POST['tgl_tagihan'] ?? null
                        ]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => "Pelanggan '{$_POST['user']}' berhasil ditambahkan."];
                        break;
                    case 'edit_user':
                        $mikrotik_id = $_POST['mikrotik_id']; // ID MikroTik
                        $customer_id_db = $_POST['customer_id_db']; // ID dari database kita

                        // Update di MikroTik
                        if (!empty($mikrotik_id)) { // Hanya update di MikroTik jika ID-nya ada
                            $mikrotik_comment = build_comment_string($_POST, 'edit_');
                            $update_data_mikrotik = [
                                ".id" => $mikrotik_id,
                                "profile" => $_POST['edit_profile'],
                                "service" => $_POST['edit_service'], // Pastikan service juga diupdate di MikroTik
                                "comment" => $mikrotik_comment
                            ];
                            if (!empty(trim($_POST['edit_password']))) {
                                $update_data_mikrotik['password'] = trim($_POST['edit_password']);
                            }
                            $API->comm("/ppp/secret/set", $update_data_mikrotik);
                        } else {
                            error_log("WARNING: Attempted to edit user in MikroTik but mikrotik_id was empty. User: " . $_POST['edit_username']);
                        }

                        // Update di Database MySQL
                        $pdo = connect_db();
                        $sql_db = "UPDATE customers SET profile_name = ?, service = ?, koordinat = ?, wilayah = ?, whatsapp = ?, tgl_registrasi = ?, tgl_tagihan = ?";
                        $params_db = [
                            $_POST['edit_profile'],
                            $_POST['edit_service'], // Pastikan ini ada di form edit
                            $_POST['edit_koordinat'] ?? null,
                            $_POST['edit_wilayah'] ?? null,
                            $_POST['edit_whatsapp'] ?? null,
                            $_POST['edit_registrasi'] ?? null,
                            $_POST['edit_tgl_tagihan'] ?? null
                        ];
                        if (!empty(trim($_POST['edit_password']))) {
                            $sql_db .= ", password = ?";
                            $params_db[] = password_hash(trim($_POST['edit_password']), PASSWORD_DEFAULT);
                        }
                        $sql_db .= " WHERE id = ?";
                        $params_db[] = $customer_id_db;
                        $stmt_db = $pdo->prepare($sql_db);
                        $stmt_db->execute($params_db);

                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data pelanggan berhasil diperbarui.'];
                        break;
                    case 'delete_user':
                        $mikrotik_id = $_POST['mikrotik_id']; // ID MikroTik
                        $customer_id_db = $_POST['customer_id_db']; // ID dari database kita
                        $username_to_delete = $_POST['username']; // Username pelanggan

                        // Hapus dari MikroTik
                        if (!empty($mikrotik_id)) { // Hanya hapus di MikroTik jika ID-nya ada
                            $API->comm("/ppp/secret/remove", ["numbers" => $mikrotik_id]);
                        } else {
                            error_log("WARNING: Attempted to delete user from MikroTik but mikrotik_id was empty. User: " . $username_to_delete);
                        }

                        // Hapus dari Database MySQL
                        $pdo = connect_db();
                        $stmt_db = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                        $stmt_db->execute([$customer_id_db]);

                        // Hapus juga tagihan yang terkait
                        $stmt_invoices = $pdo->prepare("DELETE FROM invoices WHERE username = ?");
                        $stmt_invoices->execute([$username_to_delete]);

                        // Hapus juga laporan yang terkait
                        $stmt_reports = $pdo->prepare("DELETE FROM reports WHERE customer_username = ?");
                        $stmt_reports->execute([$username_to_delete]);

                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Pelanggan berhasil dihapus.'];
                        break;
                    case 'disable_user':
                        $mikrotik_id = $_POST['mikrotik_id']; // ID MikroTik
                        if (!empty($mikrotik_id)) {
                            $API->comm("/ppp/secret/disable", ["numbers" => $mikrotik_id]);
                            $_SESSION['message'] = ['type' => 'success', 'text' => 'Pelanggan berhasil dinonaktifkan.'];
                        } else {
                            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal menonaktifkan: Pelanggan tidak ditemukan di MikroTik.'];
                        }
                        break;
                    case 'enable_user':
                        $mikrotik_id = $_POST['mikrotik_id']; // ID MikroTik
                        if (!empty($mikrotik_id)) {
                            $API->comm("/ppp/secret/enable", ["numbers" => $mikrotik_id]);
                            $_SESSION['message'] = ['type' => 'success', 'text' => 'Pelanggan berhasil diaktifkan.'];
                        } else {
                            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal mengaktifkan: Pelanggan tidak ditemukan di MikroTik.'];
                        }
                        break;
                    case 'disconnect_user':
                        $mikrotik_active_id = $_POST['mikrotik_active_id']; // ID sesi aktif dari MikroTik
                        if (!empty($mikrotik_active_id)) {
                            $API->comm("/ppp/active/remove", ["numbers" => $mikrotik_active_id]);
                            $_SESSION['message'] = ['type' => 'success', 'text' => 'Koneksi pelanggan berhasil diputuskan.'];
                        } else {
                            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal memutuskan koneksi: Sesi aktif tidak ditemukan.'];
                        }
                        break;
                    case 'add_profile':
                        // Tambah ke MikroTik
                        $profile_comment = "TAGIHAN:" . ($_POST['tagihan'] ?? '');
                        $API->comm("/ppp/profile/add", [
                            "name" => $_POST['profile_name'],
                            "rate-limit" => $_POST['rate_limit'],
                            "local-address" => $_POST['local_address'],
                            "remote-address" => $_POST['remote_address'],
                            "comment" => $profile_comment
                        ]);

                        // Tambah ke Database MySQL
                        $pdo = connect_db();
                        $stmt = $pdo->prepare("INSERT INTO profiles (profile_name, rate_limit, local_address, remote_address, tagihan_amount) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_POST['profile_name'],
                            $_POST['rate_limit'] ?? null,
                            $_POST['local_address'] ?? null,
                            $_POST['remote_address'] ?? null,
                            $_POST['tagihan'] ?? 0
                        ]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Profil baru berhasil ditambahkan.'];
                        break;
                    case 'edit_profile':
                        $mikrotik_profile_id = $_POST['edit_profile_id']; // ID Profil MikroTik
                        $profile_id_db = $_POST['profile_id_db']; // ID Profil dari database kita

                        // Update di MikroTik
                        if (!empty($mikrotik_profile_id)) {
                            $profile_comment = "TAGIHAN:" . ($_POST['edit_tagihan'] ?? '');
                            $command_data_mikrotik = [
                                ".id" => $mikrotik_profile_id,
                                "rate-limit" => $_POST['edit_rate_limit'],
                                "comment" => $profile_comment
                            ];
                            if (isset($_POST['edit_local_address'])) {
                                $command_data_mikrotik['local-address'] = $_POST['edit_local_address'];
                            }
                            if (isset($_POST['edit_remote_address'])) {
                                $command_data_mikrotik['remote-address'] = $_POST['edit_remote_address'];
                            }
                            $API->comm("/ppp/profile/set", $command_data_mikrotik);
                        } else {
                            error_log("WARNING: Attempted to edit profile in MikroTik but mikrotik_profile_id was empty. Profile DB ID: " . $profile_id_db);
                        }

                        // Update di Database MySQL
                        $pdo = connect_db();
                        $stmt_db = $pdo->prepare("UPDATE profiles SET rate_limit = ?, local_address = ?, remote_address = ?, tagihan_amount = ? WHERE id = ?");
                        $stmt_db->execute([
                            $_POST['edit_rate_limit'] ?? null,
                            $_POST['edit_local_address'] ?? null,
                            $_POST['edit_remote_address'] ?? null,
                            $_POST['edit_tagihan'] ?? 0,
                            $profile_id_db
                        ]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Profil berhasil diperbarui.'];
                        break;
                    case 'delete_profile':
                        $mikrotik_profile_id = $_POST['mikrotik_profile_id']; // ID Profil MikroTik
                        $profile_id_db = $_POST['profile_id_db']; // ID Profil dari database kita
                        $profile_name_to_delete = $_POST['profile_name']; // Nama profil

                        // Hapus dari MikroTik
                        if (!empty($mikrotik_profile_id)) {
                            $API->comm("/ppp/profile/remove", ["numbers" => $mikrotik_profile_id]);
                        } else {
                            error_log("WARNING: Attempted to delete profile from MikroTik but mikrotik_profile_id was empty. Profile: " . $profile_name_to_delete);
                        }

                        // Hapus dari Database MySQL
                        $pdo = connect_db();
                        $stmt_db = $pdo->prepare("DELETE FROM profiles WHERE id = ?");
                        $stmt_db->execute([$profile_id_db]);

                        // Opsional: Perbarui pelanggan yang menggunakan profil ini di DB
                        // Atau tangani di UI untuk mencegah penghapusan jika ada pelanggan yang masih menggunakannya.
                        // Untuk saat ini, kita biarkan saja pelanggan di DB memiliki profile_name yang tidak ada.
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Profil berhasil dihapus.'];
                        break;
                    case 'save_settings': 
                        // Gunakan fungsi save_app_settings baru
                        $new_settings = get_app_settings(); // Ambil pengaturan saat ini
                        // Update only the fields that are present in the POST request
                        if (isset($_POST['monitor_interface'])) {
                            $new_settings['monitor_interface'] = $_POST['monitor_interface'];
                        }
                        if (isset($_POST['router_ip'])) {
                            $new_settings['router_ip'] = $_POST['router_ip'];
                        }
                        if (isset($_POST['router_user'])) {
                            $new_settings['router_user'] = $_POST['router_user'];
                        }
                        // Only update password if not empty
                        if (isset($_POST['router_pass']) && !empty(trim($_POST['router_pass']))) {
                            $new_settings['router_pass'] = trim($_POST['router_pass']);
                        }
                        if (isset($_POST['tripay_merchant_code'])) {
                            $new_settings['tripay_merchant_code'] = $_POST['tripay_merchant_code'];
                        }
                        if (isset($_POST['tripay_api_key'])) {
                            $new_settings['tripay_api_key'] = $_POST['tripay_api_key'];
                        }
                        if (isset($_POST['tripay_private_key'])) {
                            $new_settings['tripay_private_key'] = $_POST['tripay_private_key'];
                        }
                        // Checkbox handling: if it's set in POST, it's true, otherwise false.
                        $new_settings['tripay_production_mode'] = isset($_POST['tripay_production_mode']);
                        
                        // Fonnte settings - Corrected variable names
                        if (isset($_POST['fonnte_api_key'])) {
                            $new_settings['fonnte_api_key'] = $_POST['fonnte_api_key'];
                        }
                        if (isset($_POST['fonnte_instance_id'])) { // This is now a hidden field, but we still need to capture it if it's sent
                            $new_settings['fonnte_instance_id'] = $_POST['fonnte_instance_id'];
                        }
                        if (isset($_POST['fonnte_base_url'])) {
                            $new_settings['fonnte_base_url'] = $_POST['fonnte_base_url'];
                        }
                        // --- PERBAIKAN: Simpan pengaturan database baru ---
                        if (isset($_POST['db_host'])) { $new_settings['db_host'] = $_POST['db_host']; }
                        if (isset($_POST['db_name'])) { $new_settings['db_name'] = $_POST['db_name']; }
                        if (isset($_POST['db_user'])) { $new_settings['db_user'] = $_POST['db_user']; }
                        // Hanya update password jika tidak kosong
                        if (isset($_POST['db_pass']) && !empty(trim($_POST['db_pass']))) {
                            $new_settings['db_pass'] = trim($_POST['db_pass']);
                        }
                        if (isset($_POST['db_port'])) { $new_settings['db_port'] = $_POST['db_port']; }
                        // --- AKHIR PERBAIKAN ---

                        save_app_settings($new_settings);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Pengaturan berhasil disimpan.']; 
                        break;
                    case 'add_app_user': 
                        $pdo = connect_db(); 
                        $assigned_regions = isset($_POST['assigned_regions']) ? json_encode($_POST['assigned_regions']) : json_encode([]);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, assigned_regions) VALUES (?, ?, ?, ?, ?)"); 
                        $stmt->execute([$_POST['app_username'], password_hash($_POST['app_password'], PASSWORD_DEFAULT), $_POST['app_role'], $_POST['app_full_name'], $assigned_regions]); 
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Pengguna aplikasi berhasil ditambahkan.']; 
                        break;
                    case 'edit_app_user': // New: Edit app user
                        $pdo = connect_db();
                        $user_id = $_POST['app_user_id'];
                        $new_role = $_POST['app_role'];
                        $new_full_name = $_POST['app_full_name'];
                        $new_username = $_POST['app_username']; // Allow username change
                        $new_password = trim($_POST['app_password']);
                        $assigned_regions = isset($_POST['assigned_regions']) ? json_encode($_POST['assigned_regions']) : json_encode([]);

                        $sql = "UPDATE users SET username = ?, role = ?, full_name = ?, assigned_regions = ?";
                        $params = [$new_username, $new_role, $new_full_name, $assigned_regions];

                        if (!empty(trim($_POST['new_password']))) { // Perbaikan: Gunakan 'new_password' dari form
                            $sql .= ", password = ?";
                            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                        }
                        $sql .= " WHERE id = ?";
                        $params[] = $user_id;

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Pengguna aplikasi berhasil diperbarui.'];
                        break;
                    case 'delete_app_user': $pdo = connect_db(); $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?"); $stmt->execute([$_POST['id']]); $_SESSION['message'] = ['type' => 'success', 'text' => 'Pengguna aplikasi berhasil dihapus.']; break;
                    case 'add_wilayah': // Wilayah sekarang disimpan di database
                        $pdo = connect_db();
                        $stmt = $pdo->prepare("INSERT INTO regions (region_name) VALUES (?)");
                        $stmt->execute([trim($_POST['nama_wilayah'])]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Wilayah baru berhasil ditambahkan.'];
                        break;
                    case 'edit_wilayah': // Wilayah sekarang disimpan di database
                        $pdo = connect_db();
                        $wilayah_id = $_POST['wilayah_id'];
                        $new_name = trim($_POST['edit_nama_wilayah']);
                        $stmt = $pdo->prepare("UPDATE regions SET region_name = ? WHERE id = ?");
                        $stmt->execute([$new_name, $wilayah_id]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Wilayah berhasil diperbarui.'];
                        break;
                    case 'delete_wilayah': // Wilayah sekarang disimpan di database
                        $pdo = connect_db();
                        $stmt = $pdo->prepare("DELETE FROM regions WHERE id = ?");
                        $stmt->execute([$_POST['id']]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Wilayah berhasil dihapus.'];
                        break;
                    
                    case 'add_report': // New: Add a new report
                        $pdo = connect_db();
                        $assigned_to_users = isset($_POST['assigned_to']) ? json_encode($_POST['assigned_to']) : json_encode([]);
                        $stmt = $pdo->prepare("INSERT INTO reports (customer_username, issue_description, reported_by, assigned_to) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$_POST['customer_username'], $_POST['issue_description'], $_SESSION['full_name'], $assigned_to_users]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Laporan gangguan berhasil ditambahkan.'];
                        break;
                    case 'edit_report': // New: Edit an existing report
                        $pdo = connect_db();
                        $report_id = $_POST['report_id'];
                        $issue_description = $_POST['issue_description'];
                        $report_status = $_POST['report_status'];
                        $assigned_to_users = isset($_POST['assigned_to']) ? json_encode($_POST['assigned_to']) : json_encode([]); // Handle as array
                        $resolved_at = ($report_status === 'Resolved') ? date('Y-m-d H:i:s') : NULL;

                        $stmt = $pdo->prepare("UPDATE reports SET issue_description = ?, report_status = ?, assigned_to = ?, updated_at = CURRENT_TIMESTAMP, resolved_at = ? WHERE id = ?");
                        $stmt->execute([$issue_description, $report_status, $assigned_to_users, $resolved_at, $report_id]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Laporan gangguan berhasil diperbarui.'];
                        break;
                    case 'delete_report': // New: Delete a report
                        $pdo = connect_db();
                        $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
                        $stmt->execute([$_POST['id']]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Laporan gangguan berhasil dihapus.'];
                        break;
                    case 'update_report_status': // New: Technician updates status
                        $pdo = connect_db();
                        $report_id = $_POST['report_id'];
                        $new_status = $_POST['new_status'];
                        $resolved_at = ($new_status === 'Resolved') ? date('Y-m-d H:i:s') : NULL;
                        // For technicians, they can only update status of reports assigned to them
                        // The assigned_to field is a JSON string, so we need to check if their username is in it
                        $stmt = $pdo->prepare("UPDATE reports SET report_status = ?, updated_at = CURRENT_TIMESTAMP, resolved_at = ? WHERE id = ? AND JSON_CONTAINS(assigned_to, JSON_QUOTE(?))"); // Menggunakan JSON_CONTAINS untuk MySQL
                        $stmt->execute([$new_status, $resolved_at, $report_id, $_SESSION['username']]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Status laporan berhasil diperbarui.'];
                        break;
                    case 'assign_report': // This action is now handled by edit_report for multiple assignments
                        // This case might become redundant if all assignments are done via edit_report
                        // For now, keep it but note its potential redundancy.
                        // This specific action 'assign_report' is no longer used in the UI,
                        // as assignment is handled directly in add/edit report modals.
                        // However, if there's a separate "Assign" button, this logic would apply.
                        // For now, it's safe to keep it as a fallback or for future expansion.
                        $pdo = connect_db();
                        $report_id = $_POST['report_id'];
                        $assigned_to_user = $_POST['assigned_to_user']; // This is a single user
                        // Fetch current assigned_to array, add new user, then save as JSON
                        $stmt_fetch = $pdo->prepare("SELECT assigned_to FROM reports WHERE id = ?");
                        $stmt_fetch->execute([$report_id]);
                        $current_assigned = json_decode($stmt_fetch->fetchColumn(), true) ?? [];
                        if (!in_array($assigned_to_user, $current_assigned)) {
                            $current_assigned[] = $assigned_to_user;
                        }
                        $new_assigned_json = json_encode($current_assigned);

                        $stmt = $pdo->prepare("UPDATE reports SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$new_assigned_json, $report_id]);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Laporan berhasil ditugaskan.'];
                        break;
                    
                    case 'generate_invoices':
                        global $all_customers_db; // Gunakan data pelanggan dari DB
                        global $profiles_map_by_name_mikrotik; // Gunakan profil dari MikroTik
                        global $all_secrets_mikrotik; // Perlu untuk cek status disabled

                        error_log("DEBUG: --- Starting Invoice Generation ---");
                        error_log("DEBUG: Total customers from DB: " . count($all_customers_db));
                        error_log("DEBUG: Total profiles from MikroTik: " . count($profiles_map_by_name_mikrotik));

                        $pdo = connect_db();
                        $stmt_insert_invoice = $pdo->prepare("INSERT INTO invoices (user_secret_id, username, profile_name, billing_month, amount, due_date) VALUES (?, ?, ?, ?, ?, ?)");
                        
                        $billing_month = date('Y-m');
                        $generated_count = 0;
                        $skipped_count = 0;
                        $skipped_no_price = 0;
                        $skipped_no_duedate = 0;
                        $skipped_disabled = 0; 

                        foreach ($all_customers_db as $customer) { // Loop melalui pelanggan dari database
                            error_log("DEBUG: Processing customer from DB: " . ($customer['username'] ?? 'N/A') . ", ID: " . ($customer['id'] ?? 'N/A'));

                            // Dapatkan status disabled dari MikroTik secara real-time
                            $mikrotik_secret = null;
                            foreach($all_secrets_mikrotik as $ms) {
                                if ($ms['name'] === $customer['username']) {
                                    $mikrotik_secret = $ms;
                                    break;
                                }
                            }

                            if ($mikrotik_secret && isset($mikrotik_secret['disabled']) && $mikrotik_secret['disabled'] === 'true') {
                                $skipped_disabled++;
                                error_log("DEBUG: Skipping disabled user (from MikroTik): " . ($customer['username'] ?? 'N/A'));
                                continue;
                            }

                            $profile_name = $customer['profile_name'] ?? null;
                            if (!$profile_name || !isset($profiles_map_by_name_mikrotik[$profile_name])) {
                                error_log("DEBUG: Skipping customer " . ($customer['username'] ?? 'N/A') . " - Profile '" . ($profile_name ?? 'N/A') . "' not found in MikroTik profiles.");
                                $skipped_count++;
                                continue;
                            }
                            
                            $profile_details_mikrotik = $profiles_map_by_name_mikrotik[$profile_name];
                            $profile_comment_data = parse_comment_string($profile_details_mikrotik['comment'] ?? '');
                            
                            $amount = filter_var($profile_comment_data['tagihan'] ?? '', FILTER_SANITIZE_NUMBER_INT);
                            $due_day = filter_var($customer['tgl_tagihan'] ?? '', FILTER_SANITIZE_NUMBER_INT); // Ambil tgl_tagihan dari DB

                            error_log("DEBUG: Customer: " . ($customer['username'] ?? 'N/A') . ", Profile (DB): " . $profile_name . ", Parsed Amount (from MikroTik Profile Comment): " . $amount);
                            error_log("DEBUG: Parsed Due Day (from DB): " . $due_day);

                            if (empty($amount) || $amount <= 0) {
                                $skipped_no_price++;
                                error_log("DEBUG: Skipping customer " . ($customer['username'] ?? 'N/A') . " - Invalid or empty amount: " . $amount);
                                continue;
                            }
                            if (empty($due_day) || $due_day < 1 || $due_day > 31) {
                                $skipped_no_duedate++;
                                error_log("DEBUG: Skipping customer " . ($customer['username'] ?? 'N/A') . " - Invalid or empty due day: " . $due_day);
                                continue;
                            }

                            $due_date = date('Y-m-d', strtotime("{$billing_month}-{$due_day}"));
                            
                            try {
                                // user_secret_id di invoices akan menjadi ID dari tabel customers
                                $stmt_insert_invoice->execute([$customer['id'], $customer['username'], $profile_name, $billing_month, $amount, $due_date]);
                                if ($stmt_insert_invoice->rowCount() > 0) {
                                    $generated_count++;
                                    error_log("DEBUG: Invoice generated for " . $customer['username'] . " for month " . $billing_month);
                                    // Send WhatsApp notification for new invoice
                                    if ($customer['whatsapp']) { // Gunakan nomor WA dari DB
                                        $payment_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/cek_tagihan.php?u=" . urlencode($customer['username']);
                                        $message_wa = "Halo pelanggan " . $customer['username'] . ",\n";
                                        $message_wa .= "Tagihan internet Anda untuk bulan " . date('F Y', strtotime($billing_month . '-01')) . " sebesar Rp " . number_format($amount, 0, ',', '.') . " akan jatuh tempo pada tanggal " . date('d F Y', strtotime($due_date)) . ".\n";
                                        $message_wa .= "Silakan cek dan bayar tagihan Anda di: " . $payment_link . "\n";
                                        $message_wa .= "Terima kasih.";
                                        sendWhatsAppMessage($customer['whatsapp'], $message_wa);
                                        error_log("DEBUG: WA message sent to " . $customer['whatsapp'] . " for " . $customer['username']);
                                    } else {
                                        error_log("DEBUG: No WA number found for " . $customer['username'] . " in DB, skipping message.");
                                    }
                                } else {
                                    error_log("DEBUG: Invoice for " . $customer['username'] . " for month " . $billing_month . " already exists (ignored).");
                                    $skipped_count++; // Increment skipped count if ignored due to UNIQUE constraint
                                }
                            } catch (PDOException $e) {
                                error_log("ERROR: Database error during invoice generation for " . ($customer['username'] ?? 'N/A') . ": " . $e->getMessage());
                                $skipped_count++;
                            }
                        }
                        $_SESSION['message'] = [
                            'type' => 'success', 
                            'text' => "$generated_count tagihan baru untuk bulan " . date('F Y') . " berhasil dibuat. " .
                                      "($skipped_count dilewati karena sudah ada/error, $skipped_no_price dilewati karena harga kosong, $skipped_no_duedate dilewati karena tanggal jatuh tempo kosong, $skipped_disabled dilewati karena pelanggan nonaktif)."
                        ];
                        error_log("DEBUG: --- Invoice Generation Finished ---");
                        error_log("DEBUG: Summary - Generated: $generated_count, Skipped (existing/error): $skipped_count, Skipped (no price): $skipped_no_price, Skipped (no due date): $skipped_no_duedate, Skipped (disabled): $skipped_disabled");
                        break;

                    case 'mark_as_paid':
                        $invoice_id = $_POST['invoice_id'];
                        $payment_method = $_POST['payment_method'];
                        $pdo = connect_db();
                        
                        $stmt_invoice = $pdo->prepare("SELECT username, amount, billing_month, user_secret_id FROM invoices WHERE id = ?"); // Ambil user_secret_id juga
                        $stmt_invoice->execute([$invoice_id]);
                        $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

                        if ($invoice) {
                            $username_to_enable = $invoice['username'];
                            $invoice_amount = $invoice['amount']; // Ambil amount
                            $billing_month = $invoice['billing_month']; // Ambil billing_month
                            $customer_id_db = $invoice['user_secret_id']; // Ini adalah ID pelanggan di DB

                            $stmt = $pdo->prepare("UPDATE invoices SET status = 'Lunas', paid_date = ?, updated_by = ?, payment_method = ? WHERE id = ?");
                            $stmt->execute([date('Y-m-d H:i:s'), $_SESSION['full_name'], $payment_method, $invoice_id]);

                            // Dapatkan nomor WA dari database pelanggan
                            $stmt_customer_wa = $pdo->prepare("SELECT whatsapp FROM customers WHERE id = ?");
                            $stmt_customer_wa->execute([$customer_id_db]);
                            $customer_data_db = $stmt_customer_wa->fetch(PDO::FETCH_ASSOC);
                            $customer_whatsapp = $customer_data_db['whatsapp'] ?? null;

                            // Cek status disabled di MikroTik dan enable jika perlu
                            $mikrotik_secret = null;
                            foreach($all_secrets_mikrotik as $ms) {
                                if ($ms['name'] === $username_to_enable) {
                                    $mikrotik_secret = $ms;
                                    break;
                                }
                            }

                            if ($mikrotik_secret && isset($mikrotik_secret['disabled']) && $mikrotik_secret['disabled'] === 'true') {
                                $API->comm("/ppp/secret/enable", ["numbers" => $mikrotik_secret['.id']]);
                                $_SESSION['message'] = ['type' => 'success', 'text' => 'Tagihan lunas & pelanggan ' . htmlspecialchars($username_to_enable) . ' telah diaktifkan kembali.'];
                            } else {
                                $_SESSION['message'] = ['type' => 'success', 'text' => 'Tagihan berhasil ditandai lunas.'];
                            }

                            if ($customer_whatsapp === null) {
                                $_SESSION['message'] = ['type' => 'warning', 'text' => 'Tagihan berhasil ditandai lunas, namun nomor WA pelanggan tidak ditemukan di database.'];
                            }


                            // Send WhatsApp confirmation for payment
                            if ($customer_whatsapp) {
                                $message_wa = "Halo pelanggan " . $username_to_enable . ",\n";
                                $message_wa .= "Pembayaran tagihan internet Anda untuk bulan " . date('F Y', strtotime($billing_month . '-01')) . " sebesar Rp " . number_format($invoice_amount, 0, ',', '.') . " telah berhasil dikonfirmasi.\n";
                                $message_wa .= "Terima kasih atas pembayaran Anda!";
                                sendWhatsAppMessage($customer_whatsapp, $message_wa);
                            }

                        } else {
                            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal menemukan tagihan.'];
                        }
                        break;

                    case 'cancel_payment':
                        $invoice_id = $_POST['invoice_id'];
                        $pdo = connect_db();
                        $stmt = $pdo->prepare("UPDATE invoices SET status = 'Belum Lunas', paid_date = NULL, updated_by = NULL, payment_method = NULL WHERE id = ?");
                        $stmt->execute([$invoice_id]);
                        $_SESSION['message'] = ['type' => 'warning', 'text' => 'Pembayaran berhasil dibatalkan.'];
                        break;
                }
            }
            // Redirect logic
            $redirect_page = $page;
            if (in_array($action, ['add_user', 'edit_user', 'delete_user', 'disable_user', 'enable_user', 'disconnect_user'])) $redirect_page = 'pelanggan';
            if (in_array($action, ['add_profile', 'edit_profile', 'delete_profile'])) $redirect_page = 'profil';
            if (in_array($action, ['add_app_user', 'edit_app_user', 'delete_app_user'])) $redirect_page = 'user'; // Added edit_app_user
            if (in_array($action, ['add_wilayah', 'delete_wilayah', 'edit_wilayah'])) $redirect_page = 'wilayah';
            if ($action === 'save_settings') $redirect_page = 'pengaturan';
            // --- PERBAIKAN: Redirect untuk tagihan penagih ---
            if (in_array($action, ['generate_invoices', 'cancel_payment'])) { // generate_invoices hanya admin
                $redirect_page = 'tagihan';
            } elseif (in_array($action, ['mark_as_paid'])) { // mark_as_paid bisa admin/penagih
                if ($_SESSION['role'] === 'penagih') {
                    $redirect_page = 'penagih_tagihan';
                } else {
                    $redirect_page = 'tagihan';
                }
            }
            // --- AKHIR PERBAIKAN ---
            if (in_array($action, ['add_report', 'edit_report', 'delete_report', 'assign_report'])) $redirect_page = 'laporan'; // Redirect to laporan for admin actions
            if (in_array($action, ['update_report_status'])) $redirect_page = 'gangguan'; // Redirect to gangguan for teknisi actions
            
            $redirect_url = strtok($_SERVER["REQUEST_URI"], '?');
            $query_params = http_build_query(['page' => $redirect_page] + array_intersect_key($_GET, array_flip(['filter', 'search', 'p', 'filter_month', 'filter_status', 'search_user', 'filter_by_user'])));
            header('Location: ' . $redirect_url . '?' . $query_params); exit;
        }

        // Data Fetching Logic
        // These are now pre-fetched before the POST action block.
        // $all_secrets = $API->comm('/ppp/secret/print'); // Diganti dengan $all_secrets_mikrotik
        // $all_profiles = $API->comm('/ppp/profile/print'); // Diganti dengan $all_profiles_mikrotik
        // $profiles_map_by_name = []; // Diganti dengan $profiles_map_by_name_mikrotik

        // $filtered_secrets = []; // Diganti dengan $filtered_customers_db

        // --- PERBAIKAN: Menggabungkan semua blok pengambilan data halaman menjadi satu if/elseif chain yang benar ---
        if ($page === 'dashboard') { // Dashboard Admin
            $secrets = $all_customers_db; // Dashboard Admin menunjukkan semua pelanggan dari DB
            $active_sessions = $API->comm('/ppp/active/print');
            // $profiles = $all_profiles_mikrotik; // Profil sekarang dari DB, tapi untuk dashboard ini tidak perlu $profiles
            // Kita pakai $profiles_map_by_name_mikrotik untuk ambil harga dari MikroTik profile

            $total_customers_db = count($secrets);
            $total_active = 0;
            $total_disabled = 0;
            $active_usernames = array_column($active_sessions, 'name');
            $active_usernames_map = array_flip($active_usernames);

            foreach ($secrets as $customer_db_item) {
                // Cek status disabled dari MikroTik
                $mikrotik_secret_status = null;
                foreach($all_secrets_mikrotik as $ms) {
                    if ($ms['name'] === $customer_db_item['username']) {
                        $mikrotik_secret_status = $ms;
                        break;
                    }
                }

                if ($mikrotik_secret_status && isset($mikrotik_secret_status['disabled']) && $mikrotik_secret_status['disabled'] === 'true') {
                    $total_disabled++;
                } elseif (isset($active_usernames_map[$customer_db_item['username']])) {
                    $total_active++;
                }
            }
            $total_offline = $total_customers_db - $total_active - $total_disabled;

            // Financial Summary for Admin Dashboard (all invoices)
            $pdo = connect_db();
            $current_month = date('Y-m');
            $stmt = $pdo->prepare("SELECT status, SUM(amount) as total FROM invoices WHERE billing_month = ? GROUP BY status");
            $stmt->execute([$current_month]);
            $monthly_totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $uang_lunas = $monthly_totals['Lunas'] ?? 0;
            $uang_belum_bayar = $monthly_totals['Belum Lunas'] ?? 0;
            
            $uang_libur = 0; // Admin dashboard does not calculate uang_libur based on filtered secrets
            foreach($all_customers_db as $customer_db_item) {
                // Dapatkan harga tagihan dari profil MikroTik
                $price = 0;
                if (isset($profiles_map_by_name_mikrotik[$customer_db_item['profile_name']])) {
                    $profile_comment = $profiles_map_by_name_mikrotik[$customer_db_item['profile_name']]['comment'] ?? '';
                    $price = filter_var(parse_comment_string($profile_comment)['tagihan'], FILTER_SANITIZE_NUMBER_INT) ?? 0;
                }
                
                // Cek status disabled dari MikroTik
                $mikrotik_secret_status = null;
                foreach($all_secrets_mikrotik as $ms) {
                    if ($ms['name'] === $customer_db_item['username']) {
                        $mikrotik_secret_status = $ms;
                        break;
                    }
                }

                if ($price > 0 && ($mikrotik_secret_status && isset($mikrotik_secret_status['disabled']) && $mikrotik_secret_status['disabled'] === 'true')) {
                    $uang_libur += $price;
                }
            }
            $total_uang = 0; // Total potential income for penagih based on filtered secrets
            foreach($all_customers_db as $customer_db_item) {
                $price = 0;
                if (isset($profiles_map_by_name_mikrotik[$customer_db_item['profile_name']])) {
                    $profile_comment = $profiles_map_by_name_mikrotik[$customer_db_item['profile_name']]['comment'] ?? '';
                    $price = filter_var(parse_comment_string($profile_comment)['tagihan'], FILTER_SANITIZE_NUMBER_INT) ?? 0;
                }
                if ($price > 0) {
                    $total_uang += $price;
                }
            }

        } elseif ($page === 'penagih_dashboard') { // Dashboard Penagih
            $secrets = $filtered_customers_db; // Penagih dashboard menggunakan pelanggan yang sudah difilter dari DB
            $active_sessions = $API->comm('/ppp/active/print');
            // $profiles = $all_profiles_mikrotik; // Profil tetap dari MikroTik, tapi untuk dashboard ini tidak perlu $profiles
            // Kita pakai $profiles_map_by_name_mikrotik untuk ambil harga dari MikroTik profile

            $total_customers_db = count($secrets);
            $total_active = 0;
            $total_disabled = 0;
            $active_usernames = array_column($active_sessions, 'name');
            $active_usernames_map = array_flip($active_usernames);

            foreach ($secrets as $customer_db_item) {
                // Cek status disabled dari MikroTik
                $mikrotik_secret_status = null;
                foreach($all_secrets_mikrotik as $ms) {
                    if ($ms['name'] === $customer_db_item['username']) {
                        $mikrotik_secret_status = $ms;
                        break;
                    }
                }

                if ($mikrotik_secret_status && isset($mikrotik_secret_status['disabled']) && $mikrotik_secret_status['disabled'] === 'true') {
                    $total_disabled++;
                } elseif (isset($active_usernames_map[$customer_db_item['username']])) {
                    $total_active++;
                }
            }
            $total_offline = $total_customers_db - $total_active - $total_disabled;

            // Financial Summary for Penagih Dashboard (filtered invoices)
            $pdo = connect_db();
            $current_month = date('Y-m');
            
            $invoice_where_clauses = ["billing_month = ?"];
            $invoice_params = [$current_month];

            $filtered_customer_usernames_db = array_column($filtered_customers_db, 'username');
            if (!empty($filtered_customer_usernames_db)) {
                $username_placeholders = implode(',', array_fill(0, count($filtered_customer_usernames_db), '?'));
                $invoice_where_clauses[] = "username IN ($username_placeholders)";
                $invoice_params = array_merge($invoice_params, $filtered_customer_usernames_db);
            } else {
                $invoice_where_clauses[] = "1 = 0"; // Force no results if no assigned regions or no customers in them
            }
            
            $stmt = $pdo->prepare("SELECT status, SUM(amount) as total FROM invoices WHERE " . implode(" AND ", $invoice_where_clauses) . " GROUP BY status");
            $stmt->execute($invoice_params);
            $monthly_totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $uang_lunas = $monthly_totals['Lunas'] ?? 0;
            $uang_belum_bayar = $monthly_totals['Belum Lunas'] ?? 0; // Corrected variable name

            $uang_libur = 0; // Calculated based on filtered secrets
            foreach($secrets as $customer_db_item) {
                $price = 0;
                if (isset($profiles_map_by_name_mikrotik[$customer_db_item['profile_name']])) {
                    $profile_comment = $profiles_map_by_name_mikrotik[$customer_db_item['profile_name']]['comment'] ?? '';
                    $price = filter_var(parse_comment_string($profile_comment)['tagihan'], FILTER_SANITIZE_NUMBER_INT) ?? 0;
                }
                
                // Cek status disabled dari MikroTik
                $mikrotik_secret_status = null;
                foreach($all_secrets_mikrotik as $ms) {
                    if ($ms['name'] === $customer_db_item['username']) {
                        $mikrotik_secret_status = $ms;
                        break;
                    }
                }

                if ($price > 0 && ($mikrotik_secret_status && isset($mikrotik_secret_status['disabled']) && $mikrotik_secret_status['disabled'] === 'true')) {
                    $uang_libur += $price;
                }
            }
            $total_uang = 0; // Total potential income for penagih based on filtered secrets
            foreach($secrets as $customer_db_item) {
                $price = 0;
                if (isset($profiles_map_by_name_mikrotik[$customer_db_item['profile_name']])) {
                    $profile_comment = $profiles_map_by_name_mikrotik[$customer_db_item['profile_name']]['comment'] ?? '';
                    $price = filter_var(parse_comment_string($profile_comment)['tagihan'], FILTER_SANITIZE_NUMBER_INT) ?? 0;
                }
                if ($price > 0) {
                    $total_uang += $price;
                }
            }

        } elseif ($page === 'pelanggan') {
            $active_sessions = $API->comm('/ppp/active/print'); 
            $secrets = $filtered_customers_db; // Gunakan pelanggan dari DB yang sudah difilter
            // $profiles = $all_profiles_mikrotik; // Profil dari MikroTik, tidak digunakan langsung di sini
            
            // Ambil wilayah dari database (sudah diambil secara global)
            // $pdo = connect_db();
            // $stmt_regions = $pdo->query("SELECT id, region_name FROM regions");
            // $wilayah_list = $stmt_regions->fetchAll(PDO::FETCH_ASSOC); // Sudah ada secara global

            $active_usernames = array_column($active_sessions, 'name'); 
            $active_usernames_map = array_flip($active_usernames);
            
            $total_customers_db = count($secrets);
            $total_active = 0;
            $total_disabled = 0;
            foreach ($secrets as $customer_db_item) {
                 // Cek status disabled dari MikroTik
                $mikrotik_secret_status = null;
                foreach($all_secrets_mikrotik as $ms) {
                    if ($ms['name'] === $customer_db_item['username']) {
                        $mikrotik_secret_status = $ms;
                        break;
                    }
                }

                if ($mikrotik_secret_status && isset($mikrotik_secret_status['disabled']) && $mikrotik_secret_status['disabled'] === 'true') {
                    $total_disabled++;
                } elseif (isset($active_usernames_map[$customer_db_item['username']])) {
                    $total_active++;
                }
            }
            $total_offline = $total_customers_db - $total_active - $total_disabled;

            $filter = $_GET['filter'] ?? 'all'; $search = $_GET['search'] ?? ''; $display_secrets = []; $table_title = "Semua Pelanggan";
            $filtered_by_status = [];
            
            foreach ($secrets as $customer_db_item) {
                $is_disabled_mikrotik = false;
                $is_online_mikrotik = false;
                $is_in_mikrotik = false; // Tambahkan flag untuk cek apakah ada di MikroTik

                // Dapatkan status dari MikroTik
                $mikrotik_secret_status = null;
                foreach($all_secrets_mikrotik as $ms) {
                    if ($ms['name'] === $customer_db_item['username']) {
                        $mikrotik_secret_status = $ms;
                        $is_in_mikrotik = true;
                        break;
                    }
                }

                if ($mikrotik_secret_status && isset($mikrotik_secret_status['disabled']) && $mikrotik_secret_status['disabled'] === 'true') {
                    $is_disabled_mikrotik = true;
                } elseif (isset($active_usernames_map[$customer_db_item['username']])) {
                    $is_online_mikrotik = true;
                }

                $match_status = false;
                switch ($filter) {
                    case 'active':
                        if ($is_online_mikrotik && !$is_disabled_mikrotik) $match_status = true;
                        break;
                    case 'disabled':
                        if ($is_disabled_mikrotik) $match_status = true;
                        break;
                    case 'offline':
                        if (!$is_online_mikrotik && !$is_disabled_mikrotik && $is_in_mikrotik) $match_status = true; // Hanya offline jika ada di MikroTik
                        break;
                    case 'not_in_mikrotik': // Filter baru
                        if (!$is_in_mikrotik) $match_status = true;
                        break;
                    default: // 'all'
                        $match_status = true;
                        break;
                }

                if ($match_status) {
                    $filtered_by_status[] = $customer_db_item;
                }
            }

            if (!empty($search)) { $table_title .= " (Pencarian: '" . htmlspecialchars($search) . "')"; foreach ($filtered_by_status as $customer_db_item) { if (stripos($customer_db_item['username'], $search) !== false || stripos($customer_db_item['profile_name'], $search) !== false) { $display_secrets[] = $customer_db_item; } } } 
            else { $display_secrets = $filtered_by_status; }
            $items_per_page = 10; $total_items = count($display_secrets); $total_pages = ceil($total_items / $items_per_page);
            $current_page_num = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
            if ($current_page_num < 1) $current_page_num = 1; if ($current_page_num > $total_pages && $total_pages > 0) $current_page_num = $total_pages;
            $offset = ($current_page_num - 1) * $items_per_page; $paginated_secrets = array_slice($display_secrets, $offset, $items_per_page);
        } elseif ($page === 'peta') {
            // Ambil wilayah dari database (sudah diambil secara global)
            // $pdo = connect_db();
            // $stmt_regions = $pdo->query("SELECT id, region_name FROM regions");
            // $wilayah_list = $stmt_regions->fetchAll(PDO::FETCH_ASSOC); // Sudah ada secara global

            // Filter wilayah_list jika current user is 'penagih'
            if ($_SESSION['role'] === 'penagih' && !empty($_SESSION['assigned_regions'])) {
                $filtered_wilayah_list_names = [];
                foreach ($wilayah_list as $wilayah_item) {
                    if (in_array($wilayah_item['region_name'], $_SESSION['assigned_regions'])) {
                        $filtered_wilayah_list_names[] = $wilayah_item;
                    }
                }
                $wilayah_list = $filtered_wilayah_list_names;
            }


            $secrets = $filtered_customers_db; // Gunakan pelanggan dari DB yang sudah difilter
            $active_sessions = $API->comm('/ppp/active/print');
            $active_usernames = array_column($active_sessions, 'name');

            $customers_with_coords = [];
            foreach ($secrets as $customer_db_item) {
                $coords = trim($customer_db_item['koordinat'] ?? '');
                $wilayah = trim($customer_db_item['wilayah'] ?? ''); // Get wilayah from DB

                if (!empty($coords) && strpos($coords, ',') !== false) {
                    $status = 'Offline';
                    // Dapatkan status disabled dari MikroTik
                    $mikrotik_secret_status = null;
                    $is_in_mikrotik = false;
                    foreach($all_secrets_mikrotik as $ms) {
                        if ($ms['name'] === $customer_db_item['username']) {
                            $mikrotik_secret_status = $ms;
                            $is_in_mikrotik = true;
                            break;
                        }
                    }

                    if ($mikrotik_secret_status && isset($mikrotik_secret_status['disabled']) && $mikrotik_secret_status['disabled'] === 'true') {
                        $status = 'Disabled';
                    } elseif (in_array($customer_db_item['username'], $active_usernames)) {
                        $status = 'Online';
                    } elseif (!$is_in_mikrotik) { // Jika tidak ada di MikroTik sama sekali
                        $status = 'Tidak di MikroTik';
                    }

                    $customers_with_coords[] = [
                        'name' => $customer_db_item['username'],
                        'coords' => $coords,
                        'status' => $status,
                        'wilayah' => $wilayah // Include wilayah in the data
                    ];
                }
            }
        } elseif ($page === 'pengaturan') { 
            $interfaces = $API->comm('/interface/print'); 
            // Ambil pengaturan dari $app_settings
            $settings = $app_settings;
        } elseif ($page === 'profil') { 
            // Ambil profil dari database (sudah diambil secara global)
            // $pdo = connect_db();
            // $stmt_profiles = $pdo->query("SELECT * FROM profiles");
            // $profiles = $stmt_profiles->fetchAll(PDO::FETCH_ASSOC); // Sudah ada secara global
            $ip_pools = $API->comm('/ip/pool/print'); // IP Pools tetap dari MikroTik
        } elseif ($page === 'user' && $_SESSION['role'] === 'admin') { 
            $pdo = connect_db(); 
            $stmt = $pdo->query("SELECT id, username, role, full_name, assigned_regions FROM users"); // Select assigned_regions
            $app_users = $stmt->fetchAll(PDO::FETCH_ASSOC); 
        } elseif ($page === 'wilayah') { 
            // Ambil wilayah dari database (sudah diambil secara global)
            // $pdo = connect_db();
            // $stmt_regions = $pdo->query("SELECT id, region_name FROM regions");
            // $wilayah_list = $stmt_regions->fetchAll(PDO::FETCH_ASSOC); // Sudah ada secara global
        } else { // Ini adalah blok untuk halaman laporan, tagihan, dll.
            $pdo = connect_db();
            $report_status_filter = $_GET['status'] ?? 'all'; // For reports
            $assigned_to_filter = $_GET['assigned_to'] ?? 'all'; // For reports
            $search_report = $_GET['search_report'] ?? ''; // For reports

            $where_clauses = [];
            $params = [];
            
            if ($page === 'tagihan') { // Logic specific for admin's tagihan page
                $search_user = $_GET['search_user'] ?? '';
                $filter_month = $_GET['filter_month'] ?? '';
                $filter_status = $_GET['filter_status'] ?? 'all';
                $filter_by_user = $_GET['filter_by_user'] ?? 'all';

                if (!empty($search_user)) {
                    $where_clauses[] = "username LIKE ?";
                    $params[] = '%' . $search_user . '%';
                }
                if (!empty($filter_month)) {
                    $where_clauses[] = "billing_month = ?";
                    $params[] = $filter_month;
                }
                if ($filter_status !== 'all') {
                    $where_clauses[] = "status = ?";
                    $params[] = $filter_status;
                }
                if ($filter_by_user !== 'all') {
                    $where_clauses[] = "updated_by = ?";
                    $params[] = $filter_by_user;
                }
                // No region filter for admin's tagihan page

                $sql = "SELECT * FROM invoices";
                if (!empty($where_clauses)) {
                    $sql .= " WHERE " . implode(" AND ", $where_clauses);
                }
                $sql .= " ORDER BY due_date DESC";
                
                // --- DEBUGGING START ---
                error_log("DEBUG: Admin Tagihan SQL Query: " . $sql);
                error_log("DEBUG: Admin Tagihan SQL Params: " . json_encode($params));
                // --- DEBUGGING END ---

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // --- DEBUGGING START ---
                error_log("DEBUG: Admin Tagihan Invoices Count: " . count($invoices));
                // --- DEBUGGING END ---

                // Calculate totals based on the same filters
                $total_sql = "SELECT status, SUM(amount) as total FROM invoices";
                if (!empty($where_clauses)) {
                    $total_sql .= " WHERE " . implode(" AND ", $where_clauses);
                }
                $total_sql .= " GROUP BY status";

                $total_stmt = $pdo->prepare($total_sql);
                $total_stmt->execute($params);
                $totals = $total_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                $total_lunas = $totals['Lunas'] ?? 0;
                $total_belum_lunas = $totals['Belum Lunas'] ?? 0;

                // Get list of admins and collectors for the filter dropdown
                $user_stmt = $pdo->query("SELECT full_name FROM users WHERE (role = 'admin' OR role = 'penagih') AND full_name IS NOT NULL AND full_name != '' ORDER BY full_name ASC");
                $confirmation_users = $user_stmt->fetchAll(PDO::FETCH_COLUMN);

            } elseif ($page === 'laporan') { // Admin Report Page
                if ($report_status_filter !== 'all') {
                    $where_clauses[] = "report_status = ?";
                    $report_params[] = $report_status_filter;
                }
                if ($assigned_to_filter !== 'all') {
                    if (empty($assigned_to_filter)) { // 'Belum Ditugaskan'
                        $where_clauses[] = "assigned_to IS NULL OR JSON_LENGTH(assigned_to) = 0"; // MySQL JSON_LENGTH
                    } else {
                        $where_clauses[] = "JSON_CONTAINS(assigned_to, JSON_QUOTE(?))"; // MySQL JSON_CONTAINS
                        $report_params[] = $assigned_to_filter;
                    }
                }
                if (!empty($search_report)) {
                    $where_clauses[] = "(customer_username LIKE ? OR issue_description LIKE ?)";
                    $report_params[] = '%' . $search_report . '%';
                    $report_params[] = '%' . $search_report . '%';
                }

                $sql_reports = "SELECT * FROM reports";
                if (!empty($where_clauses)) {
                    $sql_reports .= " WHERE " . implode(" AND ", $where_clauses);
                }
                $sql_reports .= " ORDER BY created_at DESC";

                $stmt_reports = $pdo->prepare($sql_reports);
                $stmt_reports->execute($report_params);
                $reports = $stmt_reports->fetchAll(PDO::FETCH_ASSOC);

                // Get list of technicians for assignment dropdown
                $stmt_techs = $pdo->query("SELECT username, full_name FROM users WHERE role = 'teknisi' ORDER BY full_name ASC");
                $technicians = $stmt_techs->fetchAll(PDO::FETCH_ASSOC);

                // Get list of all customer usernames for report creation dropdown (from DB)
                $stmt_customer_usernames = $pdo->query("SELECT username FROM customers");
                $customer_usernames = $stmt_customer_usernames->fetchAll(PDO::FETCH_COLUMN);

            } elseif ($page === 'penagih_tagihan') { // Penagih Tagihan Page
                $pdo = connect_db();
                $search_user = $_GET['search_user'] ?? '';
                $filter_month = $_GET['filter_month'] ?? '';
                $filter_status = $_GET['filter_status'] ?? 'all';
                $filter_by_user = $_GET['filter_by_user'] ?? 'all';
                
                $where_clauses = [];
                $params = [];
                
                if (!empty($search_user)) {
                    $where_clauses[] = "username LIKE ?";
                    $params[] = '%' . $search_user . '%';
                }
                if (!empty($filter_month)) {
                    $where_clauses[] = "billing_month = ?";
                    $params[] = $filter_month;
                }
                if ($filter_status !== 'all') {
                    $where_clauses[] = "status = ?";
                    $params[] = $filter_status;
                }
                if ($filter_by_user !== 'all') {
                    $where_clauses[] = "updated_by = ?";
                    $params[] = $filter_by_user;
                }

                // Apply region filter for 'penagih' role (based on customers from DB)
                $filtered_customer_usernames_db = array_column($filtered_customers_db, 'username');
                if (!empty($filtered_customer_usernames_db)) {
                    $username_placeholders = implode(',', array_fill(0, count($filtered_customer_usernames_db), '?'));
                    $where_clauses[] = "username IN ($username_placeholders)";
                    $params = array_merge($params, $filtered_customer_usernames_db);
                } else {
                    $where_clauses[] = "1 = 0"; // Force no results if no assigned regions or no customers in them
                }

                $sql = "SELECT * FROM invoices";
                if (!empty($where_clauses)) {
                    $sql .= " WHERE " . implode(" AND ", $where_clauses);
                }
                $sql .= " ORDER BY due_date DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate totals based on the same filters
                $total_sql = "SELECT status, SUM(amount) as total FROM invoices";
                if (!empty($where_clauses)) {
                    $total_sql .= " WHERE " . implode(" AND ", $where_clauses);
                }
                $total_sql .= " GROUP BY status";

                $total_stmt = $pdo->prepare($total_sql);
                $total_stmt->execute($params);
                $totals = $total_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                $total_lunas = $totals['Lunas'] ?? 0;
                $total_belum_lunas = $totals['Belum Lunas'] ?? 0;

                // Get list of admins and collectors for the filter dropdown
                $user_stmt = $pdo->query("SELECT full_name FROM users WHERE (role = 'admin' OR role = 'penagih') AND full_name IS NOT NULL AND full_name != '' ORDER BY full_name ASC");
                $confirmation_users = $user_stmt->fetchAll(PDO::FETCH_COLUMN);

            } elseif ($page === 'gangguan' && $_SESSION['role'] === 'teknisi') { // New: Technician Report Page
                $pdo = connect_db();
                $report_status_filter = $_GET['status'] ?? 'all'; // Changed default from 'Pending' to 'all'
                $search_report = $_GET['search_report'] ?? '';

                $report_where_clauses = ["JSON_CONTAINS(assigned_to, JSON_QUOTE(?))"]; // MySQL JSON_CONTAINS
                $report_params = [$_SESSION['username']];

                if ($report_status_filter !== 'all') {
                    $report_where_clauses[] = "report_status = ?";
                    $report_params[] = $report_status_filter;
                }
                if (!empty($search_report)) {
                    $report_where_clauses[] = "(customer_username LIKE ? OR issue_description LIKE ?)";
                    $report_params[] = '%' . $search_report . '%';
                    $report_params[] = '%' . $search_report . '%';
                }

                $sql_reports = "SELECT * FROM reports";
                if (!empty($report_where_clauses)) {
                    $sql_reports .= " WHERE " . implode(" AND ", $report_where_clauses);
                }
                $sql_reports .= " ORDER BY created_at DESC";

                $stmt_reports = $pdo->prepare($sql_reports);
                $stmt_reports->execute($report_params);
                $reports = $stmt_reports->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($page === 'teknisi_dashboard') { // New: Technician Dashboard Page
                $pdo = connect_db();
                $assigned_reports = [];
                
                // Get reports assigned to the current technician
                $stmt_assigned_reports = $pdo->prepare("SELECT * FROM reports WHERE JSON_CONTAINS(assigned_to, JSON_QUOTE(?)) ORDER BY created_at DESC"); // MySQL JSON_CONTAINS
                $stmt_assigned_reports->execute([$_SESSION['username']]);
                $assigned_reports = $stmt_assigned_reports->fetchAll(PDO::FETCH_ASSOC);

                // Summarize assigned reports by status
                $assigned_reports_summary = [
                    'total' => count($assigned_reports),
                    'Pending' => 0,
                    'In Progress' => 0,
                    'Resolved' => 0,
                    'Cancelled' => 0
                ];
                foreach ($assigned_reports as $report) {
                    if (isset($assigned_reports_summary[$report['report_status']])) {
                        $assigned_reports_summary[$report['report_status']]++;
                    }
                }

                // Get customer coordinates for reports
                $customers_with_reports_coords = [];
                $customer_usernames_in_reports = array_unique(array_column($assigned_reports, 'customer_username'));
                
                if (!empty($customer_usernames_in_reports)) {
                    // Fetch customers from DB to get their coordinates
                    $username_placeholders = implode(',', array_fill(0, count($customer_usernames_in_reports), '?'));
                    $stmt_customers_for_reports = $pdo->prepare("SELECT username, koordinat FROM customers WHERE username IN ($username_placeholders)");
                    $stmt_customers_for_reports->execute($customer_usernames_in_reports);
                    $customers_coords_map = [];
                    foreach ($stmt_customers_for_reports->fetchAll(PDO::FETCH_ASSOC) as $c) {
                        $customers_coords_map[$c['username']] = $c['koordinat'];
                    }

                    foreach ($assigned_reports as $report) {
                        $coords = $customers_coords_map[$report['customer_username']] ?? null;

                        if (!empty($coords) && strpos($coords, ',') !== false) {
                            $customers_with_reports_coords[] = [
                                'name' => $report['customer_username'],
                                'coords' => $coords,
                                'report_status' => $report['report_status'], // Include report status for icon color
                                'issue_description' => $report['issue_description'],
                                'reported_by' => $report['reported_by'],
                                'created_at' => date('d M Y H:i', strtotime($report['created_at']))
                            ];
                        }
                    }
                }
            }
        }
    } else { $connection_status = false; }
} catch (Exception $e) { $connection_status = false; $message = ['type' => 'error', 'text' => 'Koneksi ke router gagal: ' . $e->getMessage()]; }
$API->disconnect();

// =================================================================
// RENDER HALAMAN
// =================================================================
include 'main_view.php';
?>