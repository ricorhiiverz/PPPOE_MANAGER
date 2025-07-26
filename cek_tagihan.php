<?php
/**
 * Halaman Cek Tagihan untuk Pelanggan (Publik).
 *
 * Pelanggan dapat memasukkan nomor pelanggan mereka untuk melihat
 * tagihan yang belum lunas. Halaman ini juga menampilkan pesan
 * sukses setelah pembayaran berhasil.
 *
 * @package PPPOE_MANAGER
 */

// Memuat konfigurasi database. File ini harus bisa berdiri sendiri.
require_once 'config.php';

// REVISI: Memulai session untuk bisa membaca pesan dari callback.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inisialisasi variabel
$no_pelanggan = $_POST['no_pelanggan'] ?? null;
$error_message = '';
$success_message = '';
$pelanggan_info = null;
$tagihan_list = [];

// REVISI: Cek apakah ada pesan sukses dari session
if (isset($_SESSION['payment_success_message'])) {
    $success_message = $_SESSION['payment_success_message'];
    // Hapus pesan agar tidak muncul lagi saat di-refresh
    unset($_SESSION['payment_success_message']);
}


// Ambil Nama ISP dari database untuk ditampilkan di header
$nama_isp = 'Layanan ISP'; // Default
try {
    $stmt_isp = $pdo->query("SELECT setting_value FROM pengaturan WHERE setting_name = 'nama_isp'");
    $result = $stmt_isp->fetchColumn();
    if ($result) {
        $nama_isp = $result;
    }
} catch (PDOException $e) {
    // Biarkan nama default jika gagal
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $no_pelanggan) {
    try {
        // 1. Cari informasi pelanggan
        $stmt_pelanggan = $pdo->prepare("SELECT id, nama_pelanggan, alamat FROM pelanggan WHERE no_pelanggan = ?");
        $stmt_pelanggan->execute([$no_pelanggan]);
        $pelanggan_info = $stmt_pelanggan->fetch();

        if ($pelanggan_info) {
            // 2. Jika pelanggan ditemukan, cari tagihan yang belum lunas
            $stmt_tagihan = $pdo->prepare("
                SELECT * FROM tagihan 
                WHERE pelanggan_id = ? AND status_pembayaran = 'belum lunas'
                ORDER BY bulan_tagihan ASC
            ");
            $stmt_tagihan->execute([$pelanggan_info['id']]);
            $tagihan_list = $stmt_tagihan->fetchAll();

            if (empty($tagihan_list)) {
                // Jika tidak ada tagihan belum lunas, tampilkan pesan ini
                // tapi jangan timpa pesan sukses pembayaran jika ada.
                if (empty($success_message)) {
                    $error_message = 'Tidak ada tagihan yang belum lunas untuk pelanggan ini.';
                }
            }
        } else {
            $error_message = 'Nomor pelanggan tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $error_message = "Terjadi kesalahan pada server. Silakan coba lagi nanti.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Tagihan - <?php echo htmlspecialchars($nama_isp); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .container {
            max-width: 700px;
        }
        .card {
            border: none;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <i class="fas fa-file-invoice-dollar fa-3x text-primary"></i>
            <h1 class="h2 mt-2">Cek Tagihan Anda</h1>
            <p class="text-muted">Masukkan Nomor Pelanggan Anda untuk melihat status tagihan.</p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success mt-2 text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="cek_tagihan.php">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control form-control-lg" name="no_pelanggan" placeholder="Contoh: 12345678" value="<?php echo htmlspecialchars($no_pelanggan ?? ''); ?>" required>
                        <button class="btn btn-primary btn-lg" type="submit"><i class="fas fa-search me-2"></i>Cek</button>
                    </div>
                </form>

                <?php if ($error_message): ?>
                    <div class="alert alert-warning mt-4"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <?php if ($pelanggan_info && !empty($tagihan_list)): ?>
                    <hr class="my-4">
                    <div class="customer-info mb-3">
                        <h5>Data Pelanggan</h5>
                        <p class="mb-0"><strong>No. Pelanggan:</strong> <?php echo htmlspecialchars($no_pelanggan); ?></p>
                        <p class="mb-0"><strong>Nama:</strong> <?php echo htmlspecialchars($pelanggan_info['nama_pelanggan']); ?></p>
                        <p class="mb-0"><strong>Alamat:</strong> <?php echo htmlspecialchars($pelanggan_info['alamat']); ?></p>
                    </div>

                    <h5 class="mt-4">Tagihan Belum Lunas</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Bulan Tagihan</th>
                                    <th class="text-end">Jumlah</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tagihan_list as $tagihan): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($tagihan['bulan_tagihan'])); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($tagihan['jumlah_tagihan'], 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <a href="request_payment.php?invoice=<?php echo htmlspecialchars($tagihan['no_tagihan']); ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-money-check-alt me-2"></i>Bayar Sekarang
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <footer class="text-center mt-4 text-muted">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($nama_isp); ?></p>
        </footer>
    </div>
</body>
</html>