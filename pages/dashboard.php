<?php
// Pastikan pengguna memiliki akses untuk melihat halaman ini
if (!in_array($_SESSION['role'], ['admin', 'teknisi', 'penagih'])) {
    echo '<div class="alert alert-danger">Akses ditolak.</div>';
    return;
}

// Akses pengaturan aplikasi global
global $app_settings;
// Data yang dibutuhkan akan diambil di index.php:
// $total_customers_db, $total_active, $total_offline, $total_disabled (untuk admin dashboard)
// $total_uang, $uang_lunas, $uang_belum_bayar, $uang_libur (untuk admin dashboard)
// Atau versi filtered untuk penagih dashboard.
?>

<!-- Baris 1: Ringkasan Status Pelanggan -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card card-summary h-100" onclick="window.location.href='?page=pelanggan&filter=all'">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Total Pelanggan</div>
                    <div class="fs-4 fw-bold"><?= $total_customers_db ?? 0 ?></div>
                </div>
                <i class="fas fa-users fa-2x text-primary ms-3"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card card-summary h-100" onclick="window.location.href='?page=pelanggan&filter=active'">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Online</div>
                    <div class="fs-4 fw-bold"><?= $total_active ?? 0 ?></div>
                </div>
                <i class="fas fa-wifi fa-2x text-success ms-3"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card card-summary h-100" onclick="window.location.href='?page=pelanggan&filter=offline'">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Offline</div>
                    <div class="fs-4 fw-bold"><?= $total_offline ?? 0 ?></div>
                </div>
                <i class="fas fa-power-off fa-2x text-secondary ms-3"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card card-summary h-100" onclick="window.location.href='?page=pelanggan&filter=disabled'">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Nonaktif (Libur)</div>
                    <div class="fs-4 fw-bold"><?= $total_disabled ?? 0 ?></div>
                </div>
                <i class="fas fa-user-slash fa-2x text-danger ms-3"></i>
            </div>
        </div>
    </div>
</div>

<!-- Baris 2: Ringkasan Keuangan Bulan Ini -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title text-muted small">Potensi Pendapatan</h6>
                    <h4 class="fw-bold">Rp <?= number_format($total_uang ?? 0, 0, ',', '.') ?></h4>
                </div>
                <i class="fas fa-sack-dollar fa-2x text-info ms-3"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title text-muted small">Sudah Lunas</h6>
                    <h4 class="fw-bold">Rp <?= number_format($uang_lunas ?? 0, 0, ',', '.') ?></h4>
                </div>
                <i class="fas fa-check-circle fa-2x text-success ms-3"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title text-muted small">Belum Lunas</h6>
                    <h4 class="fw-bold">Rp <?= number_format($uang_belum_bayar ?? 0, 0, ',', '.') ?></h4>
                </div>
                <i class="fas fa-hourglass-half fa-2x text-warning ms-3"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title text-muted small">Pelanggan Libur</h6>
                    <h4 class="fw-bold">Rp <?= number_format($uang_libur ?? 0, 0, ',', '.') ?></h4>
                </div>
                <i class="fas fa-bed fa-2x text-secondary ms-3"></i>
            </div>
        </div>
    </div>
</div>


<!-- Baris 3: Grafik Monitoring -->
<?php if ($_SESSION['role'] !== 'penagih'): ?>
<div class="row">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-white">Monitoring Traffic</h6>
                <span class="badge bg-primary"><?= $app_settings['monitor_interface'] ?? 'Not Set' ?></span>
            </div>
            <div class="card-body" style="height: 300px;"><canvas id="trafficChart"></canvas></div>
        </div>
    </div>
</div>
<?php endif; ?>