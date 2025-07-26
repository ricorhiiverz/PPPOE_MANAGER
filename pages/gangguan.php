<?php
/**
 * Halaman Manajemen Laporan Gangguan (Trouble Ticket).
 *
 * Mengelola semua laporan gangguan dari pelanggan.
 *
 * @package PPPOE_MANAGER
 */

// Keamanan: Pastikan hanya admin yang bisa mengakses halaman ini.
if ($_SESSION['level'] !== 'admin') {
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    return;
}

// Inisialisasi variabel
$action = $_GET['action'] ?? 'list';
$gangguan_id = $_GET['id'] ?? null;
$error_message = '';
$success_message = '';

// --- Logika untuk Memproses Form (Tambah/Edit) ---
if ($action === 'save') {
    $id_to_update = $_POST['id'] ?? null;
    $pelanggan_id = $_POST['pelanggan_id'];
    $teknisi_id = !empty($_POST['teknisi_id']) ? $_POST['teknisi_id'] : null;
    $jenis_gangguan = trim($_POST['jenis_gangguan']);
    $deskripsi = trim($_POST['deskripsi']);
    $status_gangguan = $_POST['status_gangguan'];
    $tanggal_laporan = $_POST['tanggal_laporan'];
    $tanggal_selesai = !empty($_POST['tanggal_selesai']) ? $_POST['tanggal_selesai'] : null;

    if (empty($pelanggan_id) || empty($jenis_gangguan)) {
        $error_message = 'Pelanggan dan Jenis Gangguan wajib diisi.';
    } else {
        try {
            if ($id_to_update) {
                // Proses UPDATE
                $stmt = $pdo->prepare("UPDATE gangguan SET pelanggan_id=?, teknisi_id=?, jenis_gangguan=?, deskripsi=?, status_gangguan=?, tanggal_laporan=?, tanggal_selesai=? WHERE id=?");
                $stmt->execute([$pelanggan_id, $teknisi_id, $jenis_gangguan, $deskripsi, $status_gangguan, $tanggal_laporan, $tanggal_selesai, $id_to_update]);
                $success_message = 'Laporan gangguan berhasil diperbarui.';
            } else {
                // Proses INSERT
                $stmt = $pdo->prepare("INSERT INTO gangguan (pelanggan_id, teknisi_id, jenis_gangguan, deskripsi, status_gangguan, tanggal_laporan, tanggal_selesai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$pelanggan_id, $teknisi_id, $jenis_gangguan, $deskripsi, $status_gangguan, $tanggal_laporan, $tanggal_selesai]);
                $success_message = 'Laporan gangguan baru berhasil ditambahkan.';
            }
            $action = 'list';
        } catch (PDOException $e) {
            $error_message = 'Operasi Gagal: ' . $e->getMessage();
        }
    }
}

// --- Logika untuk Menghapus Data ---
if ($action === 'delete' && $gangguan_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM gangguan WHERE id = ?");
        $stmt->execute([$gangguan_id]);
        $success_message = 'Laporan gangguan berhasil dihapus.';
    } catch (PDOException $e) {
        $error_message = 'Operasi Gagal: ' . $e->getMessage();
    }
    $action = 'list';
}

// --- Tampilkan Halaman Berdasarkan Aksi ---
switch ($action) {
    case 'add':
    case 'edit':
        $gangguan = null;
        if ($action === 'edit' && $gangguan_id) {
            $stmt = $pdo->prepare("SELECT * FROM gangguan WHERE id = ?");
            $stmt->execute([$gangguan_id]);
            $gangguan = $stmt->fetch();
        }
        // Ambil data pelanggan dan teknisi untuk dropdown
        $pelanggan_list = $pdo->query("SELECT id, nama_pelanggan, no_pelanggan FROM pelanggan ORDER BY nama_pelanggan ASC")->fetchAll();
        $teknisi_list = $pdo->query("SELECT id, nama_lengkap FROM users WHERE level = 'teknisi' ORDER BY nama_lengkap ASC")->fetchAll();
        
        display_gangguan_form($gangguan, $pelanggan_list, $teknisi_list, $action, $error_message);
        break;
    
    case 'list':
    default:
        $filter_status = $_GET['filter_status'] ?? '';
        $sql = "
            SELECT g.*, p.nama_pelanggan, u.nama_lengkap as nama_teknisi
            FROM gangguan g
            JOIN pelanggan p ON g.pelanggan_id = p.id
            LEFT JOIN users u ON g.teknisi_id = u.id
        ";
        $params = [];
        if (!empty($filter_status)) {
            $sql .= " WHERE g.status_gangguan = ?";
            $params[] = $filter_status;
        }
        $sql .= " ORDER BY g.tanggal_laporan DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $gangguan_list = $stmt->fetchAll();

        display_gangguan_table($gangguan_list, $filter_status, $error_message, $success_message);
        break;
}

// --- Fungsi Tampilan ---
function display_gangguan_form($gangguan, $pelanggan_list, $teknisi_list, $action, $error) {
    ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-tools me-2"></i><?php echo $action === 'edit' ? 'Edit Laporan Gangguan' : 'Buat Laporan Baru'; ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="?page=gangguan&action=save">
                <?php if ($action === 'edit' && $gangguan): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($gangguan['id']); ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="pelanggan_id" class="form-label">Pelanggan</label>
                            <select class="form-select" id="pelanggan_id" name="pelanggan_id" required>
                                <option value="">-- Pilih Pelanggan --</option>
                                <?php foreach ($pelanggan_list as $pelanggan): ?>
                                    <option value="<?php echo $pelanggan['id']; ?>" <?php echo (isset($gangguan) && $gangguan['pelanggan_id'] == $pelanggan['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pelanggan['nama_pelanggan']) . ' (' . htmlspecialchars($pelanggan['no_pelanggan']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="jenis_gangguan" class="form-label">Jenis Gangguan</label>
                            <input type="text" class="form-control" id="jenis_gangguan" name="jenis_gangguan" value="<?php echo htmlspecialchars($gangguan['jenis_gangguan'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi Lengkap</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"><?php echo htmlspecialchars($gangguan['deskripsi'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="teknisi_id" class="form-label">Ditugaskan ke Teknisi</label>
                            <select class="form-select" id="teknisi_id" name="teknisi_id">
                                <option value="">-- Belum Ditugaskan --</option>
                                <?php foreach ($teknisi_list as $teknisi): ?>
                                    <option value="<?php echo $teknisi['id']; ?>" <?php echo (isset($gangguan) && $gangguan['teknisi_id'] == $teknisi['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teknisi['nama_lengkap']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="status_gangguan" class="form-label">Status</label>
                            <select class="form-select" id="status_gangguan" name="status_gangguan" required>
                                <option value="terbuka" <?php echo (isset($gangguan) && $gangguan['status_gangguan'] == 'terbuka') ? 'selected' : ''; ?>>Terbuka</option>
                                <option value="dalam pengerjaan" <?php echo (isset($gangguan) && $gangguan['status_gangguan'] == 'dalam pengerjaan') ? 'selected' : ''; ?>>Dalam Pengerjaan</option>
                                <option value="selesai" <?php echo (isset($gangguan) && $gangguan['status_gangguan'] == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="tanggal_laporan" class="form-label">Tanggal Laporan</label>
                            <input type="datetime-local" class="form-control" id="tanggal_laporan" name="tanggal_laporan" value="<?php echo htmlspecialchars($gangguan['tanggal_laporan'] ?? date('Y-m-d\TH:i')); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                            <input type="datetime-local" class="form-control" id="tanggal_selesai" name="tanggal_selesai" value="<?php echo htmlspecialchars($gangguan['tanggal_selesai'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <a href="?page=gangguan" class="btn btn-secondary me-2">Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Laporan</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function display_gangguan_table($list, $filter_status, $error, $success) {
    ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-tools me-2"></i>Daftar Laporan Gangguan</h5>
            <a href="?page=gangguan&action=add" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Buat Laporan</a>
        </div>
        <div class="card-body">
            <form method="GET" action="main_view.php" class="row g-3 mb-4">
                <input type="hidden" name="page" value="gangguan">
                <div class="col-md-4">
                    <label for="filter_status" class="form-label">Filter Status</label>
                    <select id="filter_status" name="filter_status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="terbuka" <?php echo ($filter_status === 'terbuka') ? 'selected' : ''; ?>>Terbuka</option>
                        <option value="dalam pengerjaan" <?php echo ($filter_status === 'dalam pengerjaan') ? 'selected' : ''; ?>>Dalam Pengerjaan</option>
                        <option value="selesai" <?php echo ($filter_status === 'selesai') ? 'selected' : ''; ?>>Selesai</option>
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
                            <th>Jenis Gangguan</th>
                            <th>Teknisi</th>
                            <th>Tgl Laporan</th>
                            <th>Status</th>
                            <th style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($list) > 0): ?>
                            <?php foreach ($list as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nama_pelanggan']); ?></td>
                                    <td><?php echo htmlspecialchars($item['jenis_gangguan']); ?></td>
                                    <td><?php echo htmlspecialchars($item['nama_teknisi'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($item['tanggal_laporan'])); ?></td>
                                    <td>
                                        <?php 
                                        $status_class = 'bg-secondary';
                                        if ($item['status_gangguan'] == 'selesai') $status_class = 'bg-success';
                                        if ($item['status_gangguan'] == 'terbuka') $status_class = 'bg-danger';
                                        if ($item['status_gangguan'] == 'dalam pengerjaan') $status_class = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($item['status_gangguan']); ?></span>
                                    </td>
                                    <td>
                                        <a href="?page=gangguan&action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="?page=gangguan&action=delete&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" title="Hapus" onclick="return confirm('Anda yakin ingin menghapus laporan ini?')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center">Tidak ada data laporan gangguan untuk filter yang dipilih.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
?>