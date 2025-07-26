<?php
/**
 * Kerangka Tampilan Utama (Main View).
 *
 * File ini adalah template utama aplikasi yang menampilkan header, sidebar, dan footer.
 * Ia juga bertindak sebagai router untuk memuat halaman konten yang sesuai
 * di dalam area konten utama.
 *
 * @package PPPOE_MANAGER
 */

// Muat konfigurasi, yang juga akan memulai session dan koneksi $pdo.
require_once 'config.php';

// Keamanan: Pastikan pengguna sudah login. Jika belum, tendang ke halaman login.
if (!isset($_SESSION['user_id'])) {
    header('Location: login_page.php');
    exit();
}

// Ambil semua pengaturan dari database untuk digunakan di seluruh aplikasi.
$app_settings = load_app_settings($pdo);

// Tentukan halaman yang sedang diminta. Defaultnya adalah 'dashboard'.
$page = $_GET['page'] ?? 'dashboard';

// Logika pengalihan ke halaman pengaturan jika belum dikonfigurasi.
// Jika IP Mikrotik kosong (indikator setup belum selesai) dan pengguna
// tidak sedang mencoba mengakses halaman pengaturan atau profil, paksa mereka
// ke halaman pengaturan.
if (empty($app_settings['mikrotik_ip']) && $page !== 'pengaturan' && $page !== 'profil' && $_SESSION['level'] === 'admin') {
    // Tambahkan pesan untuk memberitahu pengguna mengapa mereka dialihkan.
    $_SESSION['flash_message'] = [
        'type' => 'warning',
        'message' => 'Harap lengkapi konfigurasi sistem terlebih dahulu.'
    ];
    header('Location: main_view.php?page=pengaturan');
    exit();
}


// Daftar putih (whitelist) halaman yang diizinkan untuk dimuat.
// Ini adalah langkah keamanan penting untuk mencegah Local File Inclusion (LFI).
$allowed_pages = [
    'dashboard', 'pelanggan', 'tagihan', 'laporan', 'gangguan',
    'wilayah', 'paket', 'user', 'peta', 'profil', 'pengaturan',
    // Halaman khusus level
    'teknisi_dashboard', 'penagih_dashboard', 'penagih_tagihan'
];

// Jika halaman yang diminta tidak ada dalam daftar putih, kembalikan ke dashboard.
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Tentukan path file halaman yang akan dimuat.
$page_file = 'pages/' . $page . '.php';

// Periksa apakah file halaman tersebut benar-benar ada.
if (!file_exists($page_file)) {
    // Jika tidak ada, tampilkan halaman error sederhana.
    $page_title = 'Error 404';
    $page_content_to_load = 'pages/error_404.php'; // Anda perlu membuat file ini
} else {
    $page_title = ucfirst($page); // Judul halaman, misal: "Dashboard"
    $page_content_to_load = $page_file;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars($app_settings['nama_isp'] ?? 'PPPoE Manager'); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <style>
        :root {
            --sidebar-width: 260px;
        }
        body {
            background-color: #f4f7f6;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background-color: #212529;
            color: #fff;
            padding-top: 1rem;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 1rem;
            color: #fff;
            text-decoration: none;
            display: block;
        }
        .sidebar-nav .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1.5rem;
            display: block;
        }
        .sidebar-nav .nav-link:hover, .sidebar-nav .nav-link.active {
            color: #fff;
            background-color: #343a40;
        }
        .sidebar-nav .nav-link .fa-fw {
            margin-right: 0.5rem;
        }
        .dropdown-toggle::after {
            margin-left: auto;
        }
        .sidebar .nav-item .collapse .nav-link, .sidebar .nav-item .collapsing .nav-link {
            padding-left: 3rem;
            font-size: 0.9em;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="index.php" class="sidebar-brand">
            <?php echo htmlspecialchars($app_settings['nama_isp'] ?? 'PPPoE Manager'); ?>
        </a>

        <ul class="nav flex-column sidebar-nav">
            <li class="nav-item">
                <a class="nav-link <?php echo ($page === 'dashboard') ? 'active' : ''; ?>" href="?page=dashboard">
                    <i class="fa-fw fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>

            <?php if ($_SESSION['level'] === 'admin'): // MENU KHUSUS ADMIN ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($page === 'pelanggan') ? 'active' : ''; ?>" href="?page=pelanggan">
                    <i class="fa-fw fas fa-users"></i> Pelanggan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($page === 'tagihan') ? 'active' : ''; ?>" href="?page=tagihan">
                    <i class="fa-fw fas fa-file-invoice-dollar"></i> Tagihan
                </a>
            </li>
             <li class="nav-item">
                <a class="nav-link <?php echo ($page === 'gangguan') ? 'active' : ''; ?>" href="?page=gangguan">
                    <i class="fa-fw fas fa-tools"></i> Laporan Gangguan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($page === 'laporan') ? 'active' : ''; ?>" href="?page=laporan">
                    <i class="fa-fw fas fa-file-alt"></i> Laporan
                </a>
            </li>

            <hr class="text-secondary">

            <li class="nav-item">
                <a class="nav-link collapsed" href="#dataMasterSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="dataMasterSubmenu">
                    <i class="fa-fw fas fa-database"></i> Data Master
                </a>
                <div class="collapse <?php echo ($page === 'wilayah' || $page === 'paket') ? 'show' : ''; ?>" id="dataMasterSubmenu">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'wilayah') ? 'active' : ''; ?>" href="?page=wilayah">Wilayah</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'paket') ? 'active' : ''; ?>" href="?page=paket">Paket</a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($page === 'peta') ? 'active' : ''; ?>" href="?page=peta">
                    <i class="fa-fw fas fa-map-marked-alt"></i> Peta Pelanggan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($page === 'user') ? 'active' : ''; ?>" href="?page=user">
                    <i class="fa-fw fas fa-user-cog"></i> Manajemen User
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($page === 'pengaturan') ? 'active' : ''; ?>" href="?page=pengaturan">
                    <i class="fa-fw fas fa-cog"></i> Pengaturan
                </a>
            </li>
            <?php endif; // AKHIR MENU KHUSUS ADMIN ?>
        </ul>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light bg-light rounded mb-4 shadow-sm">
            <div class="container-fluid">
                <h4 class="mb-0 text-primary fw-bold"><?php echo htmlspecialchars(str_replace('_', ' ', $page_title)); ?></h4>
                <div class="ms-auto dropdown">
                    <a href="#" class="nav-link dropdown-toggle text-dark" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-2"></i>
                        <strong><?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?></strong>
                        (<?php echo htmlspecialchars($_SESSION['level']); ?>)
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="?page=profil"><i class="fas fa-user-edit me-2"></i>Profil Saya</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <?php
        // Menampilkan flash message jika ada (misal: dari pengalihan)
        if (isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($flash['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['flash_message']); // Hapus setelah ditampilkan
        }
        ?>

        <!-- Konten Halaman Dinamis -->
        <?php
        // Memuat file konten halaman yang sebenarnya.
        if (file_exists($page_content_to_load)) {
            include $page_content_to_load;
        } else {
            // Fallback jika file error 404 juga tidak ada
            echo '<div class="alert alert-danger">Error: File konten <strong>' . htmlspecialchars($page_content_to_load) . '</strong> tidak ditemukan.</div>';
        }
        ?>
    </main>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>