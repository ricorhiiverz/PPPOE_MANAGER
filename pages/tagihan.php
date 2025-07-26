<?php
/**
 * Halaman Manajemen Tagihan.
 *
 * Mengelola semua aspek tagihan pelanggan, termasuk pembuatan massal,
 * pembaruan status, dan penghapusan.
 *
 * @package PPPOE_MANAGER
 */

// Keamanan: Pastikan hanya admin yang bisa mengakses halaman ini.
if ($_SESSION['level'] !== 'admin') {
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    return;
}

// Inisialisasi variabel
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$error_message = '';
$success_message = '';

// --- Logika untuk Generate Tagihan Massal ---
if ($action === 'generate') {
    $bulan_tagihan = $_POST['bulan_tagihan']; // Format YYYY-MM
    if (empty($bulan_tagihan)) {
        $error_message = 'Silakan pilih bulan dan tahun untuk generate tagihan.';
    } else {
        try {
            // 1. Ambil semua pelanggan aktif yang memiliki paket.
            $stmt_pelanggan = $pdo->query("
                SELECT p.id, p.nama_pelanggan, pk.harga 
                FROM pelanggan p
                JOIN paket pk ON p.paket_id = pk.id
                WHERE p.status_berlangganan = 'aktif'
            ");
            $pelanggan_aktif = $stmt_pelanggan->fetchAll();

            $tagihan_dibuat = 0;
            $tagihan_dilewati = 0;

            // Siapkan statement untuk insert dan check
            $stmt_check = $pdo->prepare("SELECT id FROM tagihan WHERE pelanggan_id = ? AND bulan_tagihan = ?");
            $stmt_insert = $pdo->prepare("INSERT INTO tagihan (no_tagihan, pelanggan_id, bulan_tagihan, jumlah_tagihan, tanggal_pembuatan) VALUES (?, ?, ?, ?, NOW())");

            foreach ($pelanggan_aktif as $pelanggan) {
                // 2. Cek apakah pelanggan ini sudah punya tagihan untuk bulan tersebut.
                $stmt_check->execute([$pelanggan['id'], $bulan_tagihan]);
                if ($stmt_check->fetch()) {
                    // Jika sudah ada, lewati.
                    $tagihan_dilewati++;
                    continue;
                }

                // 3. Jika belum ada, buat tagihan baru.
                $no_tagihan = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());
                $stmt_insert->execute([$no_tagihan, $pelanggan['id'], $bulan_tagihan, $pelanggan['harga']]);
                $tagihan_dibuat++;
            }
            $success_message = "Proses selesai. Berhasil membuat $tagihan_dibuat tagihan baru. Dilewati: $tagihan_dilewati (sudah ada).";

        } catch (PDOException $e) {
            $error_message = "Generate Tagihan Gagal: " . $e->getMessage();
        }
    }
    $action = 'list'; // Kembali ke daftar setelah proses
}

// --- Logika untuk Update Status Lunas (Manual oleh Admin) ---
if ($action === 'mark_as_paid' && isset($_POST['tagihan_id'])) {
    $tagihan_id = $_POST['tagihan_id'];
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'Tunai';
    $dibayar_oleh = $_SESSION['nama_lengkap']; // Ambil nama admin yang login

    try {
        $stmt = $pdo->prepare("UPDATE tagihan SET status_pembayaran = 'lunas', tanggal_pembayaran = NOW(), metode_pembayaran = ?, dibayar_oleh = ? WHERE id = ?");
        $stmt->execute([$metode_pembayaran, $dibayar_oleh, $tagihan_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = 'Status tagihan berhasil diperbarui menjadi Lunas.';

            // Kirim notifikasi WhatsApp
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
                             "Kami memberitahukan bahwa pembayaran tagihan internet Anda untuk bulan " . date('F Y', strtotime($pelanggan['bulan_tagihan'])) . " sebesar Rp " . number_format($pelanggan['jumlah_tagihan']) . " telah kami terima melalui pembayaran " . $metode_pembayaran . ".\n\n" .
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
        $error_message = 'Update status gagal: ' . $e->getMessage();
    }
    $action = 'list';
}

// --- Logika untuk Membatalkan Status Lunas ---
if ($action === 'cancel_payment' && isset($_POST['tagihan_id'])) {
    $tagihan_id = $_POST['tagihan_id'];
    try {
        // Hanya batalkan jika bukan 'Online Payment'
        $stmt = $pdo->prepare("UPDATE tagihan SET status_pembayaran = 'belum lunas', tanggal_pembayaran = NULL, metode_pembayaran = NULL, dibayar_oleh = NULL WHERE id = ? AND dibayar_oleh != 'Online Payment'");
        $stmt->execute([$tagihan_id]);
        if ($stmt->rowCount() > 0) {
            $success_message = 'Pembayaran berhasil dibatalkan.';
        } else {
            $error_message = 'Gagal membatalkan. Pembayaran online tidak dapat dibatalkan.';
        }
    } catch (PDOException $e) {
        $error_message = 'Pembatalan gagal: ' . $e->getMessage();
    }
    $action = 'list';
}

// --- Logika untuk Menampilkan Daftar Tagihan (dengan filter) ---
$filter_bulan = $_GET['filter_bulan'] ?? date('Y-m');
$filter_status = $_GET['filter_status'] ?? '';

$sql = "SELECT t.*, p.nama_pelanggan, p.no_pelanggan FROM tagihan t JOIN pelanggan p ON t.pelanggan_id = p.id WHERE t.bulan_tagihan = ?";
$params = [$filter_bulan];

if (!empty($filter_status)) {
    $sql .= " AND t.status_pembayaran = ?";
    $params[] = $filter_status;
}
$sql .= " ORDER BY p.nama_pelanggan ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tagihan_list = $stmt->fetchAll();

?>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-bolt me-2"></i>Generate Tagihan Massal</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="?page=tagihan" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="generate">
                <div class="col-md-4">
                    <label for="bulan_tagihan" class="form-label">Pilih Bulan & Tahun</label>
                    <input type="month" class="form-control" id="bulan_tagihan" name="bulan_tagihan" value="<?php echo date('Y-m'); ?>" required>
                </div>
                <div class="col-md-8">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-cogs me-2"></i>Generate Sekarang</button>
                </div>
            </form>
            <small class="form-text text-muted mt-2">
                Fitur ini akan membuat tagihan untuk semua pelanggan berstatus 'Aktif'. Tagihan yang sudah ada untuk bulan yang dipilih akan dilewati.
            </small>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Daftar Tagihan</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="main_view.php" class="row g-3 mb-4">
                <input type="hidden" name="page" value="tagihan">
                <div class="col-md-4">
                    <label for="filter_bulan" class="form-label">Filter Bulan & Tahun</label>
                    <input type="month" class="form-control" id="filter_bulan" name="filter_bulan" value="<?php echo htmlspecialchars($filter_bulan); ?>">
                </div>
                <div class="col-md-4">
                    <label for="filter_status" class="form-label">Filter Status</label>
                    <select id="filter_status" name="filter_status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="belum lunas" <?php echo ($filter_status === 'belum lunas') ? 'selected' : ''; ?>>Belum Lunas</option>
                        <option value="lunas" <?php echo ($filter_status === 'lunas') ? 'selected' : ''; ?>>Lunas</option>
                        <option value="menunggu konfirmasi" <?php echo ($filter_status === 'menunggu konfirmasi') ? 'selected' : ''; ?>>Menunggu Konfirmasi</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-info"><i class="fas fa-filter me-2"></i>Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Pelanggan</th>
                            <th>Bulan</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Metode Bayar</th>
                            <th>Dibayar Oleh</th>
                            <th style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tagihan_list) > 0): ?>
                            <?php foreach ($tagihan_list as $tagihan): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($tagihan['nama_pelanggan']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($tagihan['no_pelanggan']); ?></small>
                                    </td>
                                    <td><?php echo date('F Y', strtotime($tagihan['bulan_tagihan'])); ?></td>
                                    <td>Rp <?php echo number_format($tagihan['jumlah_tagihan'], 0, ',', '.'); ?></td>
                                    <td>
                                        <?php 
                                        $status_class = 'bg-secondary';
                                        if ($tagihan['status_pembayaran'] == 'lunas') $status_class = 'bg-success';
                                        if ($tagihan['status_pembayaran'] == 'belum lunas') $status_class = 'bg-danger';
                                        if ($tagihan['status_pembayaran'] == 'menunggu konfirmasi') $status_class = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($tagihan['status_pembayaran']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($tagihan['metode_pembayaran'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($tagihan['dibayar_oleh'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($tagihan['status_pembayaran'] === 'belum lunas'): ?>
                                        <form method="POST" action="?page=tagihan" class="d-inline">
                                            <input type="hidden" name="action" value="mark_as_paid">
                                            <input type="hidden" name="tagihan_id" value="<?php echo $tagihan['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Tandai Lunas"><i class="fas fa-check"></i></button>
                                        </form>
                                        <?php elseif ($tagihan['status_pembayaran'] === 'lunas' && $tagihan['dibayar_oleh'] !== 'Online Payment'): ?>
                                        <form method="POST" action="?page=tagihan" class="d-inline">
                                            <input type="hidden" name="action" value="cancel_payment">
                                            <input type="hidden" name="tagihan_id" value="<?php echo $tagihan['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" title="Batalkan Lunas" onclick="return confirm('Anda yakin ingin membatalkan pembayaran ini?')"><i class="fas fa-times"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center">Tidak ada data tagihan untuk filter yang dipilih.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>