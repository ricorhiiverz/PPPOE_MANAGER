<?php
/**
 * Halaman Laporan.
 *
 * Menyediakan fungsionalitas untuk membuat dan mencetak berbagai jenis laporan,
 * seperti laporan pembayaran dan tunggakan.
 *
 * @package PPPOE_MANAGER
 */

// Keamanan: Pastikan hanya admin yang bisa mengakses halaman ini.
if ($_SESSION['level'] !== 'admin') {
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    return;
}

// Inisialisasi variabel
$report_data = [];
$report_type = $_POST['report_type'] ?? null;
$start_date = $_POST['start_date'] ?? date('Y-m-01');
$end_date = $_POST['end_date'] ?? date('Y-m-t');
$total = 0;
$report_title = '';

// --- Logika untuk Generate Laporan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $report_type) {
    try {
        switch ($report_type) {
            case 'pembayaran_lunas':
                $report_title = "Laporan Pembayaran Lunas Periode " . date('d M Y', strtotime($start_date)) . " - " . date('d M Y', strtotime($end_date));
                $stmt = $pdo->prepare("
                    SELECT t.*, p.nama_pelanggan, p.no_pelanggan
                    FROM tagihan t
                    JOIN pelanggan p ON t.pelanggan_id = p.id
                    WHERE t.status_pembayaran = 'lunas'
                    AND t.tanggal_pembayaran BETWEEN ? AND ?
                    ORDER BY t.tanggal_pembayaran ASC
                ");
                $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
                $report_data = $stmt->fetchAll();
                $total = array_sum(array_column($report_data, 'jumlah_tagihan'));
                break;

            case 'tunggakan':
                $report_title = "Laporan Tunggakan Periode " . date('F Y', strtotime($start_date));
                $bulan_tunggakan = date('Y-m', strtotime($start_date));
                $stmt = $pdo->prepare("
                    SELECT t.*, p.nama_pelanggan, p.no_pelanggan, w.nama_wilayah
                    FROM tagihan t
                    JOIN pelanggan p ON t.pelanggan_id = p.id
                    LEFT JOIN wilayah w ON p.wilayah_id = w.id
                    WHERE t.status_pembayaran = 'belum lunas'
                    AND t.bulan_tagihan = ?
                    ORDER BY w.nama_wilayah, p.nama_pelanggan ASC
                ");
                $stmt->execute([$bulan_tunggakan]);
                $report_data = $stmt->fetchAll();
                $total = array_sum(array_column($report_data, 'jumlah_tagihan'));
                break;
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Gagal membuat laporan: ' . $e->getMessage() . '</div>';
    }
}
?>

<div class="container-fluid">
    <!-- Card untuk Form Generator Laporan -->
    <div class="card mb-4" id="report-generator">
        <div class="card-header">
            <h5><i class="fas fa-file-alt me-2"></i>Buat Laporan</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="?page=laporan">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="report_type" class="form-label">Jenis Laporan</label>
                        <select class="form-select" id="report_type" name="report_type" required>
                            <option value="">-- Pilih Jenis Laporan --</option>
                            <option value="pembayaran_lunas">Laporan Pembayaran Lunas</option>
                            <option value="tunggakan">Laporan Tunggakan per Bulan</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Tanggal Mulai / Bulan</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Tanggal Selesai</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-cogs me-2"></i>Generate</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Card untuk Hasil Laporan -->
    <?php if (!empty($report_data)): ?>
    <div class="card" id="report-result">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?php echo htmlspecialchars($report_title); ?></h5>
            <button class="btn btn-secondary" onclick="printReport()"><i class="fas fa-print me-2"></i>Cetak Laporan</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <?php if ($report_type === 'pembayaran_lunas'): ?>
                        <tr>
                            <th>#</th>
                            <th>Tgl Bayar</th>
                            <th>No. Tagihan</th>
                            <th>No. Pelanggan</th>
                            <th>Nama Pelanggan</th>
                            <th class="text-end">Jumlah</th>
                        </tr>
                        <?php elseif ($report_type === 'tunggakan'): ?>
                        <tr>
                            <th>#</th>
                            <th>Wilayah</th>
                            <th>No. Pelanggan</th>
                            <th>Nama Pelanggan</th>
                            <th>Bulan Tagihan</th>
                            <th class="text-end">Jumlah</th>
                        </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($report_data as $item): ?>
                            <?php if ($report_type === 'pembayaran_lunas'): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo date('d M Y', strtotime($item['tanggal_pembayaran'])); ?></td>
                                <td><?php echo htmlspecialchars($item['no_tagihan']); ?></td>
                                <td><?php echo htmlspecialchars($item['no_pelanggan']); ?></td>
                                <td><?php echo htmlspecialchars($item['nama_pelanggan']); ?></td>
                                <td class="text-end">Rp <?php echo number_format($item['jumlah_tagihan'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php elseif ($report_type === 'tunggakan'): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($item['nama_wilayah'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($item['no_pelanggan']); ?></td>
                                <td><?php echo htmlspecialchars($item['nama_pelanggan']); ?></td>
                                <td><?php echo date('F Y', strtotime($item['bulan_tagihan'])); ?></td>
                                <td class="text-end">Rp <?php echo number_format($item['jumlah_tagihan'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="text-end">Total:</th>
                            <th class="text-end">Rp <?php echo number_format($total, 0, ',', '.'); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function printReport() {
    // Sembunyikan semua elemen kecuali area laporan
    document.body.style.visibility = 'hidden';
    document.getElementById('report-result').style.visibility = 'visible';
    
    // Posisikan area laporan di atas semua konten lain
    var reportResult = document.getElementById('report-result');
    reportResult.style.position = 'absolute';
    reportResult.style.left = '0';
    reportResult.style.top = '0';
    reportResult.style.width = '100%';

    // Panggil fungsi print browser
    window.print();

    // Kembalikan tampilan seperti semula setelah print
    document.body.style.visibility = 'visible';
    reportResult.style.position = 'static';
}
</script>

<style>
@media print {
    /* Sembunyikan elemen yang tidak perlu saat mencetak */
    body * {
        visibility: hidden;
    }
    #report-result, #report-result * {
        visibility: visible;
    }
    #report-result {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .btn {
        display: none !important;
    }
}
</style>