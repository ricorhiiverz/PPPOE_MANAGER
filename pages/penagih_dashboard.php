<?php
/**
 * Halaman Dashboard untuk Penagih.
 *
 * Menampilkan ringkasan data tunggakan untuk penagih yang login.
 *
 * @package PPPOE_MANAGER
 */

// Keamanan: Pastikan hanya penagih yang bisa mengakses halaman ini.
if ($_SESSION['level'] !== 'penagih') {
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    return;
}

// Inisialisasi variabel
$stats = [
    'jumlah_tunggakan' => 0,
    'nominal_tunggakan' => 0,
];
$bulan_ini = date('Y-m');

// --- Ambil statistik tunggakan untuk bulan ini ---
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(id) as total_tunggakan, 
            SUM(jumlah_tagihan) as total_nominal 
        FROM tagihan 
        WHERE status_pembayaran = 'belum lunas' AND bulan_tagihan = ?
    ");
    $stmt->execute([$bulan_ini]);
    $result = $stmt->fetch();

    if ($result) {
        $stats['jumlah_tunggakan'] = $result['total_tunggakan'] ?? 0;
        $stats['nominal_tunggakan'] = $result['total_nominal'] ?? 0;
    }

} catch (PDOException $e) {
    $error_message = "Gagal memuat statistik: " . $e->getMessage();
}

?>

<div class="container-fluid">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- Baris Sambutan -->
    <div class="row mb-4">
        <div class="col-12">
            <h4>Selamat Bekerja, <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>!</h4>
            <p class="text-muted">Berikut adalah ringkasan target penagihan Anda untuk bulan <strong><?php echo date('F Y'); ?></strong>.</p>
        </div>
    </div>

    <!-- Baris Kartu Statistik -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Jumlah Pelanggan Menunggak</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['jumlah_tunggakan']); ?> Pelanggan</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Nominal Tunggakan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format($stats['nominal_tunggakan'], 0, ',', '.'); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-wallet fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="row">
        <div class="col-12 text-center">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Siap Melakukan Penagihan?</h5>
                    <p class="card-text">Klik tombol di bawah ini untuk melihat daftar detail tagihan pelanggan berdasarkan wilayah.</p>
                    <a href="?page=penagih_tagihan" class="btn btn-primary btn-lg"><i class="fas fa-list-ul me-2"></i>Lihat Daftar Tagihan</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Style tambahan untuk kartu dashboard (Anda bisa memindahkannya ke CSS utama jika mau) */
.card .border-left-primary { border-left: .25rem solid #4e73df!important; }
.card .border-left-success { border-left: .25rem solid #1cc88a!important; }
.card .border-left-info { border-left: .25rem solid #36b9cc!important; }
.card .border-left-warning { border-left: .25rem solid #f6c23e!important; }
.card .border-left-danger { border-left: .25rem solid #e74a3b!important; }
.text-xs { font-size: .7rem; }
.text-gray-300 { color: #dddfeb!important; }
.text-gray-800 { color: #5a5c69!important; }
</style>