<?php
session_start();
// --- PERBAIKAN: Pastikan zona waktu adalah WIB (Asia/Jakarta) untuk seluruh aplikasi ---
date_default_timezone_set('Asia/Jakarta');
// --- AKHIR PERBABAIKAN ---

// =================================================================
// SETUP & DATABASE
// =================================================================
$db_file = 'users.db';
$db_exists = file_exists($db_file);

// Sertakan file konfigurasi yang sekarang memuat pengaturan aplikasi
require_once('config.php'); // Menggunakan require_once untuk menghindari duplikasi

function connect_db() {
    global $db_file;
    try {
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) { die("Koneksi database gagal: " . $e->getMessage()); }
}

function initialize_database() {
    $pdo = connect_db();
    // Tabel Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, password TEXT NOT NULL, role TEXT NOT NULL)");
    
    // Tabel Invoices
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_secret_id TEXT NOT NULL,
        username TEXT NOT NULL,
        profile_name TEXT NOT NULL,
        billing_month TEXT NOT NULL,
        amount INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'Belum Lunas',
        due_date DATE NOT NULL,
        paid_date DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_by TEXT,
        UNIQUE(user_secret_id, billing_month)
    )");

    // New: Tabel Reports
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_username TEXT NOT NULL,
        issue_description TEXT NOT NULL,
        report_status TEXT NOT NULL DEFAULT 'Pending', -- Pending, In Progress, Resolved, Cancelled
        reported_by TEXT NOT NULL, -- Username of admin/staff who reported
        assigned_to TEXT, -- Username of technician assigned (now stores JSON array)
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME,
        FOREIGN KEY (customer_username) REFERENCES invoices(username) ON DELETE CASCADE
    )");

    // *** FIX: Check and add new columns if they don't exist ***
    try {
        // Check for 'payment_method' in 'invoices'
        $columns_invoices_stmt = $pdo->query("PRAGMA table_info(invoices)");
        $columns_invoices = $columns_invoices_stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('payment_method', $columns_invoices)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN payment_method TEXT");
        }

        // Check for 'full_name' in 'users'
        $columns_users_stmt = $pdo->query("PRAGMA table_info(users)");
        $columns_users = $columns_users_stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('full_name', $columns_users)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN full_name TEXT");
        }
        // New: Check for 'assigned_regions' in 'users'
        if (!in_array('assigned_regions', $columns_users)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN assigned_regions TEXT"); // Store as JSON string
        }

    } catch (PDOException $e) {
        // Ignore errors if tables don't exist yet, they will be created above.
    }
}


if (!$db_exists) {
    if (isset($_POST['setup_admin'])) {
        $username = trim($_POST['username']); $password = trim($_POST['password']);
        if (!empty($username) && !empty($password)) {
            try {
                initialize_database();
                $pdo = connect_db();
                // Add admin with full_name and empty assigned_regions
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, assigned_regions) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin', 'Administrator', json_encode([])]);
                header('Location: index.php'); exit;
            } catch (PDOException $e) { die("Setup gagal: " . $e->getMessage()); }
        }
    }
    include 'setup_page.php'; exit;
} else {
    // Run initialization on every load to check for new columns
    initialize_database();
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
        $all_secrets_for_export = $API->comm('/ppp/secret/print'); // Re-fetch if not global
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
function get_wilayah() { $wilayah_file = 'wilayah.json'; if (!file_exists($wilayah_file)) { return []; } return json_decode(file_get_contents($wilayah_file), true); }
function save_wilayah($data) { file_put_contents('wilayah.json', json_encode($data, JSON_PRETTY_PRINT)); }

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

    if ($action === 'get_user_details' && isset($_GET['id'])) {
        $userId = $_GET['id']; $secret_details_array = $API_AJAX->comm("/ppp/secret/print", ["?.id" => $userId]);
        if (empty($secret_details_array)) { echo json_encode(['error' => 'Pelanggan tidak ditemukan.']); exit; }
        $secret_details = $secret_details_array[0];
        $active_user_array = $API_AJAX->comm("/ppp/active/print", ["?name" => $secret_details['name']]);
        
        $details = $secret_details;
        $comment_details = parse_comment_string($details['comment'] ?? '');
        $details = array_merge($details, $comment_details);

        $profile_info_array = $API_AJAX->comm("/ppp/profile/print", ["?name" => $details['profile']]);
        if (!empty($profile_info_array)) {
            $profile_comment_data = parse_comment_string($profile_info_array[0]['comment'] ?? '');
            $details['tagihan'] = $profile_comment_data['tagihan'];
        }

        if (!empty($active_user_array)) { $details['status-online'] = true; $details['address'] = $active_user_array[0]['address'] ?? 'N/A'; $details['uptime'] = $active_user_array[0]['uptime'] ?? 'N/A'; } 
        else { $details['status-online'] = false; }
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $action = $_POST['action'];
            $admin_actions = ['add_user', 'edit_user', 'delete_user', 'disable_user', 'enable_user', 'add_profile', 'edit_profile', 'delete_profile', 'save_settings', 'add_app_user', 'edit_app_user', 'delete_app_user', 'add_wilayah', 'delete_wilayah', 'edit_wilayah', 'generate_invoices', 'cancel_payment', 'add_report', 'edit_report', 'delete_report']; // Added report actions
            $penagih_actions = ['mark_as_paid'];
            $teknisi_actions = ['update_report_status']; // Removed assign_report from teknisi actions
            $admin_report_actions = ['assign_report']; // Explicitly define assign_report for admin only

            $is_allowed = false;
            if ($_SESSION['role'] === 'admin') {
                $is_allowed = true; // Admin can do anything
            } elseif ($_SESSION['role'] === 'penagih' && in_array($action, $penagih_actions)) {
                $is_allowed = true;
            } elseif ($_SESSION['role'] === 'teknisi' && in_array($action, $teknisi_actions)) { // Technicians can only do their specific actions
                $is_allowed = true;
            }


            if (!$is_allowed) {
                 $_SESSION['message'] = ['type' => 'error', 'text' => 'Akses ditolak. Anda tidak memiliki izin untuk melakukan aksi ini.'];
            } else { /* ... Switch Case for Actions ... */
                 switch ($action) {
                    case 'add_user': $comment = build_comment_string($_POST); $API->comm("/ppp/secret/add", ["name" => trim($_POST['user']), "password" => trim($_POST['password']), "service" => $_POST['service'], "profile" => $_POST['profile'], "comment" => $comment]); $_SESSION['message'] = ['type' => 'success', 'text' => "Pelanggan '{$_POST['user']}' berhasil ditambahkan."]; break;
                    case 'edit_user': $comment = build_comment_string($_POST, 'edit_'); $update_data = [ ".id" => $_POST['edit_id'], "profile" => $_POST['edit_profile'], "comment" => $comment ]; if (!empty(trim($_POST['edit_password']))) { $update_data['password'] = trim($_POST['edit_password']); } $API->comm("/ppp/secret/set", $update_data); $_SESSION['message'] = ['type' => 'success', 'text' => 'Data pelanggan berhasil diperbarui.']; break;
                    case 'delete_user': $API->comm("/ppp/secret/remove", ["numbers" => $_POST['id']]); $_SESSION['message'] = ['type' => 'success', 'text' => 'Pelanggan berhasil dihapus.']; break;
                    case 'disable_user': $API->comm("/ppp/secret/disable", ["numbers" => $_POST['id']]); $_SESSION['message'] = ['type' => 'success', 'text' => 'Pelanggan berhasil dinonaktifkan.']; break;
                    case 'enable_user': $API->comm("/ppp/secret/enable", ["numbers" => $_POST['id']]); $_SESSION['message'] = ['type' => 'success', 'text' => 'Pelanggan berhasil diaktifkan.']; break;
                    case 'disconnect_user': $API->comm("/ppp/active/remove", ["numbers" => $_POST['id']]); $_SESSION['message'] = ['type' => 'success', 'text' => 'Koneksi pelanggan berhasil diputuskan.']; break;
                    case 'add_profile': 
                        $profile_comment = "TAGIHAN:" . ($_POST['tagihan'] ?? '');
                        $API->comm("/ppp/profile/add", ["name" => $_POST['profile_name'], "rate-limit" => $_POST['rate_limit'], "local-address" => $_POST['local_address'], "remote-address" => $_POST['remote_address'], "comment" => $profile_comment]); 
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Profil baru berhasil ditambahkan.']; break;
                    case 'edit_profile': 
                        $profile_comment = "TAGIHAN:" . ($_POST['edit_tagihan'] ?? '');
                        $command_data = [
                            ".id" => $_POST['edit_profile_id'],
                            "rate-limit" => $_POST['edit_rate_limit'],
                            "comment" => $profile_comment
                        ];
                        $API->comm("/ppp/profile/set", $command_data); 
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Profil berhasil diperbarui.']; break;
                    case 'delete_profile': $API->comm("/ppp/profile/remove", ["numbers" => $_POST['id']]); $_SESSION['message'] = ['type' => 'success', 'text' => 'Profil berhasil dihapus.']; break;
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
                        if (isset($_POST['tripay_production_mode'])) {
                            $new_settings['tripay_production_mode'] = isset($_POST['tripay_production_mode']) ? true : false;
                        } else {
                            $new_settings['tripay_production_mode'] = false; // If checkbox not sent, it's false
                        }
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

                        if (!empty($new_password)) {
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
                    case 'add_wilayah': $wilayah_list = get_wilayah(); $wilayah_list[] = trim($_POST['nama_wilayah']); save_wilayah(array_unique($wilayah_list)); $_SESSION['message'] = ['type' => 'success', 'text' => 'Wilayah baru berhasil ditambahkan.']; break;
                    case 'edit_wilayah': // New case for editing wilayah
                        $wilayah_list = get_wilayah();
                        $wilayah_id = $_POST['wilayah_id'];
                        $new_name = trim($_POST['edit_nama_wilayah']);
                        if (isset($wilayah_list[$wilayah_id])) {
                            $wilayah_list[$wilayah_id] = $new_name;
                            save_wilayah(array_values($wilayah_list)); // Re-index array after update
                            $_SESSION['message'] = ['type' => 'success', 'text' => 'Wilayah berhasil diperbarui.'];
                        } else {
                            $_SESSION['message'] = ['type' => 'error', 'text' => 'Wilayah tidak ditemukan.'];
                        }
                        break;
                    case 'delete_wilayah': $wilayah_list = get_wilayah(); unset($wilayah_list[$_POST['id']]); save_wilayah(array_values($wilayah_list)); $_SESSION['message'] = ['type' => 'success', 'text' => 'Wilayah berhasil dihapus.']; break;
                    
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
                        $stmt = $pdo->prepare("UPDATE reports SET report_status = ?, updated_at = CURRENT_TIMESTAMP, resolved_at = ? WHERE id = ? AND INSTR(assigned_to, ?)"); // Using INSTR for simplicity
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
                        global $all_secrets; // Use the global all_secrets
                        $secrets_for_generation = $all_secrets; // Use all secrets for generation
                        $profiles = $API->comm('/ppp/profile/print');
                        $profiles_map = [];
                        foreach ($profiles as $profile) {
                            $profiles_map[$profile['name']] = $profile;
                        }

                        $pdo = connect_db();
                        $stmt = $pdo->prepare("INSERT OR IGNORE INTO invoices (user_secret_id, username, profile_name, billing_month, amount, due_date) VALUES (?, ?, ?, ?, ?, ?)");
                        
                        $billing_month = date('Y-m');
                        $generated_count = 0;
                        foreach ($secrets_for_generation as $secret) { // Loop through all secrets
                            if (isset($secret['disabled']) && $secret['disabled'] === 'true') continue;

                            $profile_name = $secret['profile'];
                            if (isset($profiles_map[$profile_name])) {
                                $profile_details = $profiles_map[$profile_name];
                                $comment_data = parse_comment_string($profile_details['comment'] ?? '');
                                $secret_comment_data = parse_comment_string($secret['comment'] ?? '');
                                
                                $amount = filter_var($comment_data['tagihan'], FILTER_SANITIZE_NUMBER_INT);
                                $due_day = filter_var($secret_comment_data['tgl_tagihan'], FILTER_SANITIZE_NUMBER_INT);
                                $customer_whatsapp = parse_comment_for_wa($secret['comment'] ?? ''); // Get customer WA number

                                if (!empty($amount) && !empty($due_day)) {
                                    $due_date = date('Y-m-d', strtotime("{$billing_month}-{$due_day}"));
                                    $stmt->execute([$secret['.id'], $secret['name'], $profile_name, $billing_month, $amount, $due_date]);
                                    if ($stmt->rowCount() > 0) {
                                        $generated_count++;
                                        // Send WhatsApp notification for new invoice
                                        if ($customer_whatsapp) {
                                            $message = "Halo pelanggan " . $secret['name'] . ",\n";
                                            $message .= "Tagihan internet Anda untuk bulan " . date('F Y', strtotime($billing_month . '-01')) . " sebesar Rp " . number_format($amount, 0, ',', '.') . " akan jatuh tempo pada tanggal " . date('d F Y', strtotime($due_date)) . ".\n";
                                            $message .= "Silakan cek tagihan Anda di " . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/cek_tagihan.php\n";
                                            $message .= "Terima kasih.";
                                            sendWhatsAppMessage($customer_whatsapp, $message);
                                        }
                                    }
                                }
                            }
                        }
                        $_SESSION['message'] = ['type' => 'success', 'text' => "$generated_count tagihan baru untuk bulan " . date('F Y') . " berhasil dibuat."];
                        error_log("DEBUG: Invoices generated: " . $generated_count); // Debug log
                        break;

                    case 'mark_as_paid':
                        $invoice_id = $_POST['invoice_id'];
                        $payment_method = $_POST['payment_method'];
                        $pdo = connect_db();
                        
                        $stmt_invoice = $pdo->prepare("SELECT username, amount, billing_month FROM invoices WHERE id = ?"); // Ambil billing_month juga
                        $stmt_invoice->execute([$invoice_id]);
                        $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

                        if ($invoice) {
                            $username_to_enable = $invoice['username'];
                            $invoice_amount = $invoice['amount']; // Ambil amount
                            $billing_month = $invoice['billing_month']; // Ambil billing_month

                            $stmt = $pdo->prepare("UPDATE invoices SET status = 'Lunas', paid_date = ?, updated_by = ?, payment_method = ? WHERE id = ?");
                            $stmt->execute([date('Y-m-d H:i:s'), $_SESSION['full_name'], $payment_method, $invoice_id]);

                            // Use $all_secrets to find the customer's WhatsApp number
                            $customer_whatsapp = null;
                            foreach ($all_secrets as $secret_item) {
                                if ($secret_item['name'] === $username_to_enable) {
                                    $customer_whatsapp = parse_comment_for_wa($secret_item['comment'] ?? '');
                                    // Check if disabled and enable
                                    if (isset($secret_item['disabled']) && $secret_item['disabled'] === 'true') {
                                        $secret_id = $secret_item['.id'];
                                        $API->comm("/ppp/secret/enable", ["numbers" => $secret_id]);
                                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Tagihan lunas & pelanggan ' . htmlspecialchars($username_to_enable) . ' telah diaktifkan kembali.'];
                                    } else {
                                        $_SESSION['message'] = ['type' => 'success', 'text' => 'Tagihan berhasil ditandai lunas.'];
                                    }
                                    break;
                                }
                            }
                            if ($customer_whatsapp === null) {
                                $_SESSION['message'] = ['type' => 'warning', 'text' => 'Tagihan berhasil ditandai lunas, namun pelanggan tidak ditemukan di MikroTik atau nomor WA tidak ada.'];
                            }


                            // Send WhatsApp confirmation for payment
                            if ($customer_whatsapp) {
                                $message = "Halo pelanggan " . $username_to_enable . ",\n";
                                $message .= "Pembayaran tagihan internet Anda untuk bulan " . date('F Y', strtotime($billing_month . '-01')) . " sebesar Rp " . number_format($invoice_amount, 0, ',', '.') . " telah berhasil dikonfirmasi.\n";
                                $message .= "Terima kasih atas pembayaran Anda!";
                                sendWhatsAppMessage($customer_whatsapp, $message);
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
        // Pre-fetch all secrets and profiles once if needed by multiple pages,
        // and build a map for efficient lookup.
        $all_secrets = $API->comm('/ppp/secret/print');
        $all_profiles = $API->comm('/ppp/profile/print');
        $profiles_map_by_name = [];
        foreach ($all_profiles as $profile) {
            $profiles_map_by_name[$profile['name']] = $profile;
        }

        // Filter secrets based on assigned regions for 'penagih' role
        $filtered_secrets = [];
        if ($_SESSION['role'] === 'penagih') { // Only apply region filter for 'penagih'
            if (!empty($_SESSION['assigned_regions'])) {
                foreach ($all_secrets as $secret) {
                    $comment_data = parse_comment_string($secret['comment'] ?? '');
                    $customer_wilayah = $comment_data['wilayah'] ?? '';
                    if (in_array($customer_wilayah, $_SESSION['assigned_regions'])) {
                        $filtered_secrets[] = $secret;
                    }
                }
            } else {
                // If penagih has no assigned regions, they see nothing.
                // $filtered_secrets remains an empty array.
            }
        } else {
            $filtered_secrets = $all_secrets; // Admin and Teknisi see all
        }


        if ($page === 'dashboard') { // Dashboard Admin
            $secrets = $all_secrets; // Admin dashboard shows all secrets
            $active_sessions = $API->comm('/ppp/active/print');
            $profiles = $all_profiles;

            $total_secrets = count($secrets);
            $total_active = 0;
            $total_disabled = 0;
            $active_usernames = array_column($active_sessions, 'name');
            $active_usernames_map = array_flip($active_usernames);

            foreach ($secrets as $secret) {
                if (isset($secret['disabled']) && $secret['disabled'] === 'true') {
                    $total_disabled++;
                } elseif (isset($active_usernames_map[$secret['name']])) {
                    $total_active++;
                }
            }
            $total_offline = $total_secrets - $total_active - $total_disabled;

            // Financial Summary for Admin Dashboard (all invoices)
            $pdo = connect_db();
            $current_month = date('Y-m');
            $stmt = $pdo->prepare("SELECT status, SUM(amount) as total FROM invoices WHERE billing_month = ? GROUP BY status");
            $stmt->execute([$current_month]);
            $monthly_totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $uang_lunas = $monthly_totals['Lunas'] ?? 0;
            $uang_belum_bayar = $monthly_totals['Belum Lunas'] ?? 0;
            $uang_libur = 0; // Admin dashboard does not calculate uang_libur based on filtered secrets
            foreach($all_secrets as $secret) {
                 $price = $profiles_map_by_name[$secret['profile']]['comment'] ?? '';
                 $price = filter_var(parse_comment_string($price)['tagihan'], FILTER_SANITIZE_NUMBER_INT) ?? 0;
                 if ($price > 0 && (isset($secret['disabled']) && $secret['disabled'] === 'true')) {
                     $uang_libur += $price;
                 }
            }
            $total_uang = 0; // Admin dashboard total potential income
            foreach($all_secrets as $secret) {
                $price = $profiles_map_by_name[$secret['profile']]['comment'] ?? '';
                $price = filter_var(parse_comment_string($price)['tagihan'], FILTER_SANITIZE_NUMBER_INT) ?? 0;
                if ($price > 0) {
                    $total_uang += $price;
                }
            }

        } elseif ($page === 'penagih_dashboard') { // Dashboard Penagih
            $secrets = $filtered_secrets; // Penagih dashboard uses filtered secrets
            $active_sessions = $API->comm('/ppp/active/print');
            $profiles = $all_profiles; // Still need all profiles for price lookup

            $total_secrets = count($secrets);
            $total_active = 0;
            $total_disabled = 0;
            $active_usernames = array_column($active_sessions, 'name');
            $active_usernames_map = array_flip($active_usernames);

            foreach ($secrets as $secret) {
                if (isset($secret['disabled']) && $secret['disabled'] === 'true') {
                    $total_disabled++;
                } elseif (isset($active_usernames_map[$secret['name']])) {
                    $total_active++;
                }
            }
            $total_offline = $total_secrets - $total_active - $total_disabled;

            // Financial Summary for Penagih Dashboard (filtered invoices)
            $pdo = connect_db();
            $current_month = date('Y-m');
            
            $invoice_where_clauses = ["billing_month = ?"];
            $invoice_params = [$current_month];

            $filtered_secret_names = array_column($filtered_secrets, 'name');
            if (!empty($filtered_secret_names)) {
                $username_placeholders = implode(',', array_fill(0, count($filtered_secret_names), '?'));
                $invoice_where_clauses[] = "username IN ($username_placeholders)";
                $invoice_params = array_merge($invoice_params, $filtered_secret_names);
            } else {
                $invoice_where_clauses[] = "1 = 0"; // Force no results if no assigned regions or no customers in them
            }
            
            $stmt = $pdo->prepare("SELECT status, SUM(amount) as total FROM invoices WHERE " . implode(" AND ", $invoice_where_clauses) . " GROUP BY status");
            $stmt->execute($invoice_params);
            $monthly_totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $uang_lunas = $monthly_totals['Lunas'] ?? 0;
            $uang_belum_bayar = $monthly_totals['Belum Lunas'] ?? 0; // Corrected variable name

            $uang_libur = 0; // Calculated based on filtered secrets
            foreach($secrets as $secret) {
                $price = $profiles_map_by_name[$secret['profile']]['comment'] ?? '';
                $price = filter_var(parse_comment_string($price)['tagihan'], FILTER_SANITIZE_NUMBER_INT) ?? 0;
                if ($price > 0 && (isset($secret['disabled']) && $secret['disabled'] === 'true')) {
                    $uang_libur += $price;
                }
            }
            $total_uang = 0; // Total potential income for penagih based on filtered secrets
            foreach($secrets as $secret) {
                $price = $profiles_map_by_name[$secret['profile']]['comment'] ?? '';
                $price = filter_var(parse_comment_string($price)['tagihan'], FILTER_SANITIZE_NUMBER_INT) ?? 0;
                if ($price > 0) {
                    $total_uang += $price;
                }
            }

        } elseif ($page === 'pelanggan') {
            $active_sessions = $API->comm('/ppp/active/print'); 
            $secrets = $filtered_secrets; // Use filtered secrets for pelanggan page
            $profiles = $all_profiles; 
            $wilayah_list = get_wilayah(); // Still need all wilayah for the filter dropdown
            $active_usernames = array_column($active_sessions, 'name'); 
            $active_usernames_map = array_flip($active_usernames);
            
            $total_secrets = count($secrets);
            $total_active = 0;
            $total_disabled = 0;
            foreach ($secrets as $secret) {
                if (isset($secret['disabled']) && $secret['disabled'] === 'true') {
                    $total_disabled++;
                } elseif (isset($active_usernames_map[$secret['name']])) {
                    $total_active++;
                }
            }
            $total_offline = $total_secrets - $total_active - $total_disabled;

            $filter = $_GET['filter'] ?? 'all'; $search = $_GET['search'] ?? ''; $display_secrets = []; $table_title = "Semua Pelanggan";
            $filtered_by_status = [];
            switch ($filter) {
                case 'active': $table_title = "Pelanggan Aktif (Online)"; foreach ($secrets as $secret) { if (isset($active_usernames_map[$secret['name']]) && (!isset($secret['disabled']) || $secret['disabled'] !== 'true')) { $filtered_by_status[] = $secret; } } break;
                case 'disabled': $table_title = "Pelanggan Nonaktif (Disabled)"; foreach ($secrets as $secret) { if (isset($secret['disabled']) && $secret['disabled'] === 'true') { $filtered_by_status[] = $secret; } } break;
                case 'offline': $table_title = "Pelanggan Offline"; foreach ($secrets as $secret) { if ((!isset($secret['disabled']) || $secret['disabled'] !== 'true') && !isset($active_usernames_map[$secret['name']])) { $filtered_by_status[] = $secret; } } break;
                default: $filtered_by_status = $secrets; break;
            }
            if (!empty($search)) { $table_title .= " (Pencarian: '" . htmlspecialchars($search) . "')"; foreach ($filtered_by_status as $secret) { if (stripos($secret['name'], $search) !== false || stripos($secret['profile'], $search) !== false) { $display_secrets[] = $secret; } } } 
            else { $display_secrets = $filtered_by_status; }
            $items_per_page = 10; $total_items = count($display_secrets); $total_pages = ceil($total_items / $items_per_page);
            $current_page_num = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
            if ($current_page_num < 1) $current_page_num = 1; if ($current_page_num > $total_pages && $total_pages > 0) $current_page_num = $total_pages;
            $offset = ($current_page_num - 1) * $items_per_page; $paginated_secrets = array_slice($display_secrets, $offset, $items_per_page);
        } elseif ($page === 'peta') {
            $wilayah_list = get_wilayah();
            // Filter wilayah_list if current user is 'penagih'
            if ($_SESSION['role'] === 'penagih' && !empty($_SESSION['assigned_regions'])) {
                $filtered_wilayah_list = [];
                foreach ($wilayah_list as $wilayah) {
                    if (in_array($wilayah, $_SESSION['assigned_regions'])) {
                        $filtered_wilayah_list[] = $wilayah;
                    }
                }
                $wilayah_list = $filtered_wilayah_list;
            }


            $secrets = $filtered_secrets; // Use filtered secrets for peta page
            $active_sessions = $API->comm('/ppp/active/print');
            $active_usernames = array_column($active_sessions, 'name');

            $customers_with_coords = [];
            foreach ($secrets as $secret) {
                $comment_data = parse_comment_string($secret['comment'] ?? '');
                $coords = trim($comment_data['koordinat']);
                $wilayah = trim($comment_data['wilayah'] ?? ''); // Get wilayah from comment

                if (!empty($coords) && strpos($coords, ',') !== false) {
                    $status = 'Offline';
                    if (isset($secret['disabled']) && $secret['disabled'] === 'true') {
                        $status = 'Disabled';
                    } elseif (in_array($secret['name'], $active_usernames)) {
                        $status = 'Online';
                    }

                    $customers_with_coords[] = [
                        'name' => $secret['name'],
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
        } elseif ($page === 'profil') { $profiles = $all_profiles; $ip_pools = $API->comm('/ip/pool/print');
        } elseif ($page === 'user' && $_SESSION['role'] === 'admin') { 
            $pdo = connect_db(); 
            $stmt = $pdo->query("SELECT id, username, role, full_name, assigned_regions FROM users"); // Select assigned_regions
            $app_users = $stmt->fetchAll(PDO::FETCH_ASSOC); 
        } elseif ($page === 'wilayah') { $wilayah_list = get_wilayah();
        } elseif ($page === 'laporan' || $page === 'tagihan' || $page === 'penagih_tagihan') { // Combined data fetching for 'laporan', 'tagihan', and 'penagih_tagihan'
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
                        $where_clauses[] = "assigned_to IS NULL OR assigned_to = '[]'";
                    } else {
                        $where_clauses[] = "INSTR(assigned_to, ?)";
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

                // Get list of all customer usernames for report creation dropdown
                $customer_usernames = array_column($all_secrets, 'name');
            }
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

            // Apply region filter for 'penagih' role
            $filtered_secret_names = array_column($filtered_secrets, 'name');
            if (!empty($filtered_secret_names)) {
                $username_placeholders = implode(',', array_fill(0, count($filtered_secret_names), '?'));
                $where_clauses[] = "username IN ($username_placeholders)";
                $params = array_merge($params, $filtered_secret_names);
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

            $report_where_clauses = ["INSTR(assigned_to, ?)"]; // Filter by technician's username in assigned_to JSON
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
            $stmt_assigned_reports = $pdo->prepare("SELECT * FROM reports WHERE INSTR(assigned_to, ?) ORDER BY created_at DESC"); // Filter by technician's username in assigned_to JSON
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
                // Fetch secrets for these customers to get their coordinates
                $secrets_for_reports = [];
                foreach($all_secrets as $secret) {
                    if (in_array($secret['name'], $customer_usernames_in_reports)) {
                        $comment_data = parse_comment_string($secret['comment'] ?? '');
                        $coords = trim($comment_data['koordinat']);

                        if (!empty($coords) && strpos($coords, ',') !== false) {
                            // Add secret to secrets_for_reports only if it has valid coordinates
                            $secrets_for_reports[] = $secret;
                        }
                    }
                }

                foreach ($assigned_reports as $report) {
                    foreach ($secrets_for_reports as $secret) {
                        if ($report['customer_username'] === $secret['name']) {
                            $comment_data = parse_comment_string($secret['comment'] ?? '');
                            $coords = trim($comment_data['koordinat']);

                            if (!empty($coords) && strpos($coords, ',') !== false) {
                                $customers_with_reports_coords[] = [
                                    'name' => $secret['name'],
                                    'coords' => $coords,
                                    'report_status' => $report['report_status'], // Include report status for icon color
                                    'issue_description' => $report['issue_description'],
                                    'reported_by' => $report['reported_by'],
                                    'created_at' => date('d M Y H:i', strtotime($report['created_at']))
                                ];
                            }
                            break; // Found the secret for this report
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