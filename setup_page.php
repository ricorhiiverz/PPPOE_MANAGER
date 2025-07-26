<?php
/**
 * Halaman Setup Aplikasi (Wizard Instalasi).
 *
 * Halaman ini bertanggung jawab untuk:
 * 1. Memeriksa apakah aplikasi sudah di-setup. Jika sudah, alihkan ke index.
 * 2. Membuat semua tabel database yang diperlukan.
 * 3. Membuat akun administrator pertama.
 * 4. Mengisi tabel pengaturan dengan nilai default.
 *
 * @package PPPOE_MANAGER
 */

// Muat file konfigurasi untuk mendapatkan koneksi $pdo dan fungsi is_setup_complete().
require_once 'config.php';

// Jika setup sudah selesai, jangan biarkan pengguna mengakses halaman ini lagi.
// Alihkan ke halaman utama.
if (is_setup_complete($pdo)) {
    header('Location: index.php');
    exit();
}

// Inisialisasi variabel untuk pesan error atau sukses.
$error_message = '';
$success_message = '';

// Proses form jika metode request adalah POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form.
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $nama_lengkap = trim($_POST['nama_lengkap']);

    // Validasi sederhana.
    if (empty($username) || empty($password) || empty($nama_lengkap)) {
        $error_message = 'Semua field wajib diisi.';
    } elseif ($password !== $password_confirm) {
        $error_message = 'Konfirmasi password tidak cocok.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password minimal harus 6 karakter.';
    } else {
        // Jika validasi lolos, mulai proses instalasi.
        try {
            // Mulai transaksi database. Ini memastikan semua query berhasil atau tidak sama sekali.
            $pdo->beginTransaction();

            // === 1. BUAT SEMUA TABEL DATABASE ===
            $sql_queries = [
                // Tabel Pengguna (Admins, Technicians, Collectors)
                "CREATE TABLE users (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    nama_lengkap VARCHAR(100) NOT NULL,
                    level ENUM('admin', 'teknisi', 'penagih') NOT NULL,
                    no_hp VARCHAR(20),
                    alamat TEXT,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

                // Tabel Pengaturan Aplikasi
                "CREATE TABLE pengaturan (
                    setting_name VARCHAR(100) NOT NULL,
                    setting_value TEXT,
                    PRIMARY KEY (setting_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

                // Tabel Wilayah/Area
                "CREATE TABLE wilayah (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    nama_wilayah VARCHAR(100) NOT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

                // Tabel Paket Internet
                "CREATE TABLE paket (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    nama_paket VARCHAR(100) NOT NULL,
                    harga INT(11) NOT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

                // Tabel Pelanggan
                "CREATE TABLE pelanggan (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    no_pelanggan VARCHAR(20) NOT NULL UNIQUE,
                    nama_pelanggan VARCHAR(100) NOT NULL,
                    alamat TEXT NOT NULL,
                    no_hp VARCHAR(20),
                    wilayah_id INT(11),
                    koordinat VARCHAR(50),
                    paket_id INT(11),
                    username_pppoe VARCHAR(50),
                    password_pppoe VARCHAR(50),
                    status_berlangganan ENUM('aktif', 'nonaktif', 'isolir') DEFAULT 'aktif',
                    tanggal_pemasangan DATE,
                    PRIMARY KEY (id),
                    FOREIGN KEY (wilayah_id) REFERENCES wilayah(id) ON DELETE SET NULL,
                    FOREIGN KEY (paket_id) REFERENCES paket(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
                
                // Tabel Tagihan
                "CREATE TABLE tagihan (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    no_tagihan VARCHAR(25) NOT NULL UNIQUE,
                    pelanggan_id INT(11) NOT NULL,
                    bulan_tagihan VARCHAR(7) NOT NULL, -- Format: YYYY-MM
                    jumlah_tagihan INT(11) NOT NULL,
                    status_pembayaran ENUM('belum lunas', 'lunas', 'menunggu konfirmasi') DEFAULT 'belum lunas',
                    tanggal_pembuatan DATETIME NOT NULL,
                    tanggal_pembayaran DATETIME,
                    metode_pembayaran VARCHAR(50),
                    dibayar_oleh VARCHAR(100), 
                    bukti_pembayaran VARCHAR(255),
                    PRIMARY KEY (id),
                    FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

                // Tabel Laporan Gangguan
                "CREATE TABLE gangguan (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    pelanggan_id INT(11) NOT NULL,
                    teknisi_id INT(11),
                    jenis_gangguan VARCHAR(100),
                    deskripsi TEXT,
                    status_gangguan ENUM('terbuka', 'dalam pengerjaan', 'selesai') DEFAULT 'terbuka',
                    tanggal_laporan DATETIME NOT NULL,
                    tanggal_selesai DATETIME,
                    PRIMARY KEY (id),
                    FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE,
                    FOREIGN KEY (teknisi_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

                // Tabel untuk mencatat transaksi payment gateway
                "CREATE TABLE pembayaran (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    no_tagihan VARCHAR(25) NOT NULL,
                    reference VARCHAR(100),
                    merchant_ref VARCHAR(100),
                    total_amount INT(11) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
            ];

            foreach ($sql_queries as $query) {
                $pdo->exec($query);
            }

            // === 2. ISI PENGATURAN DEFAULT ===
            // REVISI: Menyesuaikan dengan Fonnte
            $default_settings = [
                'nama_isp' => 'ISP Anda', 'alamat_isp' => '', 'no_hp_isp' => '',
                'website_isp' => '', 'mikrotik_ip' => '', 'mikrotik_user' => '',
                'mikrotik_pass' => '', 'payment_merchant_code' => '', 'payment_api_key' => '',
                'payment_private_key' => '', 'fonnte_token' => ''
            ];
            $stmt = $pdo->prepare("INSERT INTO pengaturan (setting_name, setting_value) VALUES (?, ?)");
            foreach ($default_settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }

            // === 3. BUAT AKUN ADMIN PERTAMA ===
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, password, nama_lengkap, level) VALUES (?, ?, ?, 'admin')"
            );
            $stmt->execute([$username, $hashed_password, $nama_lengkap]);

            // Jika semua berhasil, commit transaksi.
            $pdo->commit();

            // Alihkan ke halaman login dengan pesan sukses.
            $_SESSION['setup_success'] = "Instalasi berhasil! Silakan login dengan akun administrator yang baru Anda buat.";
            header('Location: login_page.php');
            exit();

        } catch (PDOException $e) {
            // Jika terjadi error, batalkan semua perubahan.
            $pdo->rollBack();
            $error_message = "Instalasi Gagal: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Aplikasi PPPoE Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .setup-container {
            max-width: 500px;
            margin: 5rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="setup-container">
            <h2 class="text-center mb-4">Instalasi PPPoE Manager</h2>
            <p class="text-muted text-center">Selamat datang! Silakan buat akun administrator pertama untuk memulai.</p>
            <hr class="mb-4">

            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form method="POST" action="setup_page.php">
                <div class="mb-3">
                    <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username Admin</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="password_confirm" class="form-label">Konfirmasi Password</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Buat Akun & Install</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>