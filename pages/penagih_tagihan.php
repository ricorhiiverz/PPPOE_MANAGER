<?php
/**
 * Halaman Daftar Tagihan untuk Penagih.
 *
 * Menampilkan daftar pelanggan yang memiliki tunggakan,
 * dikelompokkan per wilayah untuk efisiensi kerja.
 *
 * @package PPPOE_MANAGER
 */

// Keamanan: Pastikan hanya penagih atau admin yang bisa mengakses halaman ini.
if (!in_array($_SESSION['level'], ['penagih', 'admin'])) {
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    return;
}

// Inisialisasi variabel
$error_message = '';
$success_message = '';

// --- Logika untuk Update Status Lunas ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $tagihan_id = $_POST['tagihan_id'] ?? null;
    if ($tagihan_id) {
        try {
            $dibayar_oleh = $_SESSION['nama_lengkap']; // Ambil nama penagih yang login
            $stmt_update = $pdo->prepare("UPDATE tagihan SET status_pembayaran = 'lunas', metode_pembayaran = 'Tunai', tanggal_pembayaran = NOW(), dibayar_oleh = ? WHERE id = ?");
            $stmt_update->execute([$dibayar_oleh, $tagihan_id]);

            if ($stmt_update->rowCount() > 0) {
                $success_message = "Tagihan berhasil ditandai lunas.";

                // REVISI: Kirim notifikasi WhatsApp
                try {
                    $stmt_pelanggan = $pdo->prepare("
                        SELECT p.no_hp, p.nama_pelanggan, t.jumlah_tagihan, t.bulan_tagihan
                        FROM tagihan t JOIN pelanggan p ON t.pelanggan_id = p.id WHERE t.id = ?
                    ");
                    $stmt_pelanggan->execute([$tagihan_id]);
                    $pelanggan = $stmt_pelanggan->fetch();

                    if ($pelanggan && !empty($pelanggan['no_hp'])) {
                        $nama_isp = $app_settings['nama_isp'] ?? 'Kami';
                        $pesan = "Yth. Bpk/Ibu " . $pelanggan['nama_pelanggan'] . ",\n\n" .
                                 "Kami memberitahukan bahwa pembayaran tagihan internet Anda untuk bulan " . date('F Y', strtotime($pelanggan['bulan_tagihan'])) . " sebesar Rp " . number_format($pelanggan['jumlah_tagihan']) . " telah kami terima melalui petugas kami.\n\n" .
                                 "Terima kasih atas pembayarannya.\n" . $nama_isp;
                        
                        if (!send_whatsapp_notification($app_settings, $pelanggan['no_hp'], $pesan)) {
                            $error_message = "Peringatan: Tagihan berhasil diupdate, namun notifikasi WhatsApp gagal terkirim.";
                        }
                    }
                } catch (Exception $e) {
                    $error_message = "Peringatan: Tagihan berhasil diupdate, namun notifikasi WhatsApp gagal terkirim.";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Gagal memperbarui status: " . $e->getMessage();
        }
    }
}

// --- Logika untuk Menampilkan Daftar Tagihan (dengan filter) ---
$filter_bulan = $_GET['filter_bulan'] ?? date('Y-m');
$filter_wilayah = $_GET['filter_wilayah'] ?? '';

// Ambil daftar wilayah untuk dropdown filter
try {
    $wilayah_list = $pdo->query("SELECT id, nama_wilayah FROM wilayah ORDER BY nama_wilayah ASC")->fetchAll();
} catch (PDOException $e) {
    $wilayah_list = [];
}

// Query utama untuk mengambil data tunggakan
$sql = "
    SELECT t.*, p.nama_pelanggan, p.no_pelanggan, p.alamat, p.no_hp, w.nama_wilayah
    FROM tagihan t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    LEFT JOIN wilayah w ON p.wilayah_id = w.id
    WHERE t.status_pembayaran = 'belum lunas' AND t.bulan_tagihan = ?
";
$params = [$filter_bulan];

if (!empty($filter_wilayah)) {
    $sql .= " AND p.wilayah_id = ?";
    $params[] = $filter_wilayah;
}
$sql .= " ORDER BY w.nama_wilayah, p.nama_pelanggan ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tunggakan_list = $stmt->fetchAll();

// Kelompokkan hasil berdasarkan wilayah
$tunggakan_by_wilayah = [];
foreach ($tunggakan_list as $tunggakan) {
    $nama_wilayah = $tunggakan['nama_wilayah'] ?? 'Tanpa Wilayah';
    $tunggakan_by_wilayah[$nama_wilayah][] = $tunggakan;
}

?>

<div class="container-fluid">
    <?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i>Daftar Penagihan (Belum Lunas)</h5>
        </div>
        <div class="card-body">
            <!-- Form Filter -->
            <form method="GET" action="main_view.php" class="row g-3 mb-4 p-3 bg-light border rounded">
                <input type="hidden" name="page" value="penagih_tagihan">
                <div class="col-md-5">
                    <label for="filter_bulan" class="form-label">Tagihan Bulan</label>
                    <input type="month" class="form-control" id="filter_bulan" name="filter_bulan" value="<?php echo htmlspecialchars($filter_bulan); ?>">
                </div>
                <div class="col-md-5">
                    <label for="filter_wilayah" class="form-label">Filter Wilayah</label>
                    <select id="filter_wilayah" name="filter_wilayah" class="form-select">
                        <option value="">Semua Wilayah</option>
                        <?php foreach ($wilayah_list as $wilayah): ?>
                            <option value="<?php echo $wilayah['id']; ?>" <?php echo ($filter_wilayah == $wilayah['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($wilayah['nama_wilayah']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-info w-100"><i class="fas fa-filter me-2"></i>Filter</button>
                </div>
            </form>

            <!-- Daftar Tagihan per Wilayah -->
            <?php if (count($tunggakan_by_wilayah) > 0): ?>
                <?php foreach ($tunggakan_by_wilayah as $wilayah_nama => $tunggakans): ?>
                    <div class="accordion mb-3" id="accordion-<?php echo md5($wilayah_nama); ?>">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading-<?php echo md5($wilayah_nama); ?>">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo md5($wilayah_nama); ?>" aria-expanded="true" aria-controls="collapse-<?php echo md5($wilayah_nama); ?>">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <strong><?php echo htmlspecialchars($wilayah_nama); ?></strong>
                                    <span class="badge bg-danger ms-3"><?php echo count($tunggakans); ?> Pelanggan</span>
                                </button>
                            </h2>
                            <div id="collapse-<?php echo md5($wilayah_nama); ?>" class="accordion-collapse collapse show" aria-labelledby="heading-<?php echo md5($wilayah_nama); ?>">
                                <div class="accordion-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <tbody>
                                                <?php foreach ($tunggakans as $tunggakan): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($tunggakan['nama_pelanggan']); ?></strong> (<?php echo htmlspecialchars($tunggakan['no_pelanggan']); ?>)<br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($tunggakan['alamat']); ?></small>
                                                    </td>
                                                    <td>
                                                        <i class="fas fa-phone-alt me-1 text-muted"></i> <?php echo htmlspecialchars($tunggakan['no_hp']); ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <strong>Rp <?php echo number_format($tunggakan['jumlah_tagihan'], 0, ',', '.'); ?></strong>
                                                    </td>
                                                    <td class="text-center" style="width: 15%;">
                                                        <form method="POST" action="?page=penagih_tagihan" onsubmit="return confirm('Tandai tagihan ini sebagai LUNAS?');">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="tagihan_id" value="<?php echo $tunggakan['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success w-100">
                                                                <i class="fas fa-check me-1"></i> Tandai Lunas
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info text-center">Tidak ada data tunggakan untuk filter yang dipilih.</div>
            <?php endif; ?>
        </div>
    </div>
</div>