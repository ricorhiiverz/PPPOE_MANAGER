<?php
/**
 * Halaman Manajemen Paket Internet (Data Master).
 *
 * Mengelola data paket internet yang ditawarkan.
 * Semua operasi (Tambah, Edit, Hapus) dilakukan pada tabel 'paket' di database.
 *
 * @package PPPOE_MANAGER
 *
 * Catatan: File ini di-include oleh main_view.php, sehingga memiliki akses
 * ke variabel $pdo, $app_settings, dan $_SESSION.
 */

// Keamanan: Pastikan hanya admin yang bisa mengakses halaman ini.
if ($_SESSION['level'] !== 'admin') {
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    return; // Hentikan eksekusi jika bukan admin.
}

// Inisialisasi variabel
$action = $_GET['action'] ?? 'list'; // Aksi default adalah 'list'
$paket_id = $_GET['id'] ?? null;
$error_message = '';
$success_message = '';

// --- Logika untuk Memproses Form (Tambah/Edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_paket = trim($_POST['nama_paket']);
    $harga = filter_var($_POST['harga'], FILTER_SANITIZE_NUMBER_INT);
    $id_to_update = $_POST['id'] ?? null;

    if (empty($nama_paket) || empty($harga)) {
        $error_message = 'Nama paket dan harga tidak boleh kosong.';
    } else {
        try {
            if ($id_to_update) {
                // Proses UPDATE
                $stmt = $pdo->prepare("UPDATE paket SET nama_paket = ?, harga = ? WHERE id = ?");
                $stmt->execute([$nama_paket, $harga, $id_to_update]);
                $success_message = 'Paket berhasil diperbarui.';
            } else {
                // Proses INSERT
                $stmt = $pdo->prepare("INSERT INTO paket (nama_paket, harga) VALUES (?, ?)");
                $stmt->execute([$nama_paket, $harga]);
                $success_message = 'Paket baru berhasil ditambahkan.';
            }
        } catch (PDOException $e) {
            $error_message = 'Operasi Gagal: ' . $e->getMessage();
        }
    }
    // Setelah operasi, kembali ke halaman list
    $action = 'list';
}

// --- Logika untuk Menghapus Data ---
if ($action === 'delete' && $paket_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM paket WHERE id = ?");
        $stmt->execute([$paket_id]);
        $success_message = 'Paket berhasil dihapus.';
    } catch (PDOException $e) {
        // Error jika paket masih digunakan oleh pelanggan
        if ($e->getCode() == '23000') {
            $error_message = 'Gagal menghapus: Paket ini masih digunakan oleh data pelanggan.';
        } else {
            $error_message = 'Operasi Gagal: ' . $e->getMessage();
        }
    }
    $action = 'list';
}

// Ambil data untuk form edit
$paket_to_edit = null;
if ($action === 'edit' && $paket_id) {
    $stmt = $pdo->prepare("SELECT * FROM paket WHERE id = ?");
    $stmt->execute([$paket_id]);
    $paket_to_edit = $stmt->fetch();
}

?>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Kolom Form Tambah/Edit -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-wifi me-2"></i><?php echo $action === 'edit' ? 'Edit Paket' : 'Tambah Paket Baru'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?page=paket">
                        <?php if ($action === 'edit' && $paket_to_edit): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($paket_to_edit['id']); ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="nama_paket" class="form-label">Nama Paket</label>
                            <input type="text" class="form-control" id="nama_paket" name="nama_paket" value="<?php echo htmlspecialchars($paket_to_edit['nama_paket'] ?? ''); ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="harga" class="form-label">Harga (Rp)</label>
                            <input type="number" class="form-control" id="harga" name="harga" value="<?php echo htmlspecialchars($paket_to_edit['harga'] ?? ''); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                        <?php if ($action === 'edit'): ?>
                            <a href="?page=paket" class="btn btn-secondary">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Kolom Tabel Daftar Paket -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Daftar Paket Internet</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Nama Paket</th>
                                    <th scope="col">Harga</th>
                                    <th scope="col" style="width: 15%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT * FROM paket ORDER BY harga ASC");
                                    $paket_list = $stmt->fetchAll();
                                    $no = 1;
                                    if (count($paket_list) > 0) {
                                        foreach ($paket_list as $paket) {
                                            echo '<tr>';
                                            echo '<td>' . $no++ . '</td>';
                                            echo '<td>' . htmlspecialchars($paket['nama_paket']) . '</td>';
                                            echo '<td>Rp ' . number_format($paket['harga'], 0, ',', '.') . '</td>';
                                            echo '<td>';
                                            echo '<a href="?page=paket&action=edit&id=' . $paket['id'] . '" class="btn btn-sm btn-warning me-1" title="Edit"><i class="fas fa-edit"></i></a>';
                                            echo '<a href="?page=paket&action=delete&id=' . $paket['id'] . '" class="btn btn-sm btn-danger" title="Hapus" onclick="return confirm(\'Apakah Anda yakin ingin menghapus paket ini?\')"><i class="fas fa-trash"></i></a>';
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="4" class="text-center">Belum ada data paket.</td></tr>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<tr><td colspan="4" class="text-center text-danger">Gagal memuat data: ' . $e->getMessage() . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>