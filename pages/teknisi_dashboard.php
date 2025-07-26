<?php
/**
 * Halaman Dashboard untuk Teknisi.
 *
 * Menampilkan daftar tugas (laporan gangguan) yang ditugaskan
 * kepada teknisi yang sedang login.
 *
 * @package PPPOE_MANAGER
 */

// Keamanan: Pastikan hanya teknisi yang bisa mengakses halaman ini.
if ($_SESSION['level'] !== 'teknisi') {
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    return;
}

// Inisialisasi variabel
$teknisi_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// --- Logika untuk Update Status Laporan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $gangguan_id = $_POST['gangguan_id'] ?? null;
    $new_status = $_POST['status_gangguan'] ?? null;

    if ($gangguan_id && $new_status) {
        // Verifikasi bahwa tiket ini memang milik teknisi yang login untuk keamanan
        $stmt_verify = $pdo->prepare("SELECT id FROM gangguan WHERE id = ? AND teknisi_id = ?");
        $stmt_verify->execute([$gangguan_id, $teknisi_id]);
        
        if ($stmt_verify->fetch()) {
            // Jika verifikasi berhasil, update status
            try {
                $tanggal_selesai = ($new_status === 'selesai') ? date('Y-m-d H:i:s') : null;
                $stmt_update = $pdo->prepare("UPDATE gangguan SET status_gangguan = ?, tanggal_selesai = ? WHERE id = ?");
                $stmt_update->execute([$new_status, $tanggal_selesai, $gangguan_id]);
                $success_message = "Status laporan berhasil diperbarui.";
            } catch (PDOException $e) {
                $error_message = "Gagal memperbarui status: " . $e->getMessage();
            }
        } else {
            $error_message = "Aksi tidak diizinkan. Laporan ini tidak ditugaskan kepada Anda.";
        }
    }
}

// --- Ambil semua laporan yang ditugaskan ke teknisi ini ---
try {
    $stmt = $pdo->prepare("
        SELECT g.*, p.nama_pelanggan, p.alamat, p.no_hp
        FROM gangguan g
        JOIN pelanggan p ON g.pelanggan_id = p.id
        WHERE g.teknisi_id = ?
        ORDER BY FIELD(g.status_gangguan, 'terbuka', 'dalam pengerjaan', 'selesai'), g.tanggal_laporan DESC
    ");
    $stmt->execute([$teknisi_id]);
    $laporan_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $laporan_list = [];
    $error_message = "Gagal memuat daftar tugas: " . $e->getMessage();
}

?>

<div class="container-fluid">
    <?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-tasks me-2"></i>Daftar Tugas Saya</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Status</th>
                            <th>Tgl Laporan</th>
                            <th>Pelanggan</th>
                            <th>Alamat & Kontak</th>
                            <th>Deskripsi Gangguan</th>
                            <th style="width: 20%;">Ubah Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($laporan_list) > 0): ?>
                            <?php foreach ($laporan_list as $laporan): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        $status_class = 'bg-secondary';
                                        if ($laporan['status_gangguan'] == 'selesai') $status_class = 'bg-success';
                                        if ($laporan['status_gangguan'] == 'terbuka') $status_class = 'bg-danger';
                                        if ($laporan['status_gangguan'] == 'dalam pengerjaan') $status_class = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($laporan['status_gangguan']); ?></span>
                                    </td>
                                    <td><?php echo date('d M Y, H:i', strtotime($laporan['tanggal_laporan'])); ?></td>
                                    <td><?php echo htmlspecialchars($laporan['nama_pelanggan']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($laporan['alamat']); ?><br>
                                        <small class="text-muted"><i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($laporan['no_hp']); ?></small>
                                    </td>
                                    <td><?php echo nl2br(htmlspecialchars($laporan['deskripsi'])); ?></td>
                                    <td>
                                        <?php if ($laporan['status_gangguan'] !== 'selesai'): ?>
                                        <form method="POST" action="?page=teknisi_dashboard">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="gangguan_id" value="<?php echo $laporan['id']; ?>">
                                            <div class="input-group">
                                                <select name="status_gangguan" class="form-select form-select-sm">
                                                    <option value="terbuka" <?php echo ($laporan['status_gangguan'] == 'terbuka') ? 'selected' : ''; ?>>Terbuka</option>
                                                    <option value="dalam pengerjaan" <?php echo ($laporan['status_gangguan'] == 'dalam pengerjaan') ? 'selected' : ''; ?>>Dalam Pengerjaan</option>
                                                    <option value="selesai" <?php echo ($laporan['status_gangguan'] == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check"></i></button>
                                            </div>
                                        </form>
                                        <?php else: ?>
                                            <span class="text-success"><i class="fas fa-check-circle me-1"></i> Selesai pada <?php echo date('d M Y', strtotime($laporan['tanggal_selesai'])); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center">Tidak ada tugas yang ditugaskan kepada Anda saat ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>