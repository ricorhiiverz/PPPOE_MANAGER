<?php
/**
 * Halaman Dashboard Utama.
 *
 * File ini menampilkan ringkasan statistik dan informasi penting bagi admin.
 * Ia juga bertindak sebagai router untuk mengarahkan pengguna non-admin
 * ke dashboard spesifik mereka.
 *
 * @package PPPOE_MANAGER
 *
 * Catatan: File ini di-include oleh main_view.php, sehingga memiliki akses
 * ke variabel $pdo, $app_settings, dan $_SESSION.
 */

// --- Pengalihan Berdasarkan Level Pengguna ---
// Jika pengguna yang login adalah teknisi atau penagih, arahkan mereka
// ke dashboard yang sesuai untuk mereka.
if ($_SESSION['level'] === 'teknisi') {
    header('Location: ?page=teknisi_dashboard');
    exit();
}
if ($_SESSION['level'] === 'penagih') {
    header('Location: ?page=penagih_dashboard');
    exit();
}

// --- Logika Khusus untuk Admin ---
// Pastikan hanya admin yang bisa melihat konten di bawah ini.
if ($_SESSION['level'] !== 'admin') {
    echo '<div class="alert alert-danger">Akses ditolak. Anda tidak memiliki izin untuk melihat halaman ini.</div>';
    return; // Hentikan eksekusi jika bukan admin.
}

// Inisialisasi variabel statistik
$stats = [
    'pelanggan_aktif' => 0,
    'pendapatan_bulan_ini' => 0,
    'tagihan_belum_lunas' => 0,
    'gangguan_terbuka' => 0,
];

try {
    // 1. Hitung jumlah pelanggan aktif
    $stmt = $pdo->query("SELECT COUNT(id) as total FROM pelanggan WHERE status_berlangganan = 'aktif'");
    $stats['pelanggan_aktif'] = $stmt->fetchColumn();

    // 2. Hitung pendapatan bulan ini (dari tagihan yang lunas bulan ini)
    $stmt = $pdo->query("SELECT SUM(jumlah_tagihan) as total FROM tagihan WHERE status_pembayaran = 'lunas' AND MONTH(tanggal_pembayaran) = MONTH(CURDATE()) AND YEAR(tanggal_pembayaran) = YEAR(CURDATE())");
    $pendapatan = $stmt->fetchColumn();
    $stats['pendapatan_bulan_ini'] = $pendapatan ?: 0; // Jika hasilnya null, jadikan 0

    // 3. Hitung jumlah tagihan yang belum lunas
    $stmt = $pdo->query("SELECT COUNT(id) as total FROM tagihan WHERE status_pembayaran = 'belum lunas'");
    $stats['tagihan_belum_lunas'] = $stmt->fetchColumn();

    // 4. Hitung jumlah laporan gangguan yang masih terbuka
    $stmt = $pdo->query("SELECT COUNT(id) as total FROM gangguan WHERE status_gangguan = 'terbuka'");
    $stats['gangguan_terbuka'] = $stmt->fetchColumn();

} catch (PDOException $e) {
    // Tampilkan error jika query gagal
    echo '<div class="alert alert-danger">Gagal memuat data dashboard: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

?>

<div class="container-fluid">
    <!-- Baris Sambutan -->
    <div class="row mb-4">
        <div class="col-12">
            <h4>Selamat Datang Kembali, <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>!</h4>
            <p class="text-muted">Berikut adalah ringkasan aktivitas jaringan Anda hari ini.</p>
        </div>
    </div>

    <!-- Baris Kartu Statistik -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Pelanggan Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['pelanggan_aktif']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Pendapatan (Bulan Ini)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format($stats['pendapatan_bulan_ini'], 0, ',', '.'); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Tagihan Belum Lunas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['tagihan_belum_lunas']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Gangguan Terbuka</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['gangguan_terbuka']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tools fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Anda bisa menambahkan chart atau tabel ringkasan lainnya di sini -->

</div>

<style>
/* Style tambahan untuk kartu dashboard */
.card .border-left-primary { border-left: .25rem solid #4e73df!important; }
.card .border-left-success { border-left: .25rem solid #1cc88a!important; }
.card .border-left-info { border-left: .25rem solid #36b9cc!important; }
.card .border-left-warning { border-left: .25rem solid #f6c23e!important; }
.card .border-left-danger { border-left: .25rem solid #e74a3b!important; }
.text-xs { font-size: .7rem; }
.text-gray-300 { color: #dddfeb!important; }
.text-gray-800 { color: #5a5c69!important; }
</style>
