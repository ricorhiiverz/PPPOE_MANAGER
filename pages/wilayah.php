<?php
/**
 * Halaman Manajemen Wilayah (Data Master).
 *
 * Mengelola data area/wilayah cakupan layanan.
 * Semua operasi (Tambah, Edit, Hapus) dilakukan pada tabel 'wilayah' di database.
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
$wilayah_id = $_GET['id'] ?? null;
$error_message = '';
$success_message = '';

// --- Logika untuk Memproses Form (Tambah/Edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_wilayah = trim($_POST['nama_wilayah']);
    $id_to_update = $_POST['id'] ?? null;

    if (empty($nama_wilayah)) {
        $error_message = 'Nama wilayah tidak boleh kosong.';
    } else {
        try {
            if ($id_to_update) {
                // Proses UPDATE
                $stmt = $pdo->prepare("UPDATE wilayah SET nama_wilayah = ? WHERE id = ?");
                $stmt->execute([$nama_wilayah, $id_to_update]);
                $success_message = 'Wilayah berhasil diperbarui.';
            } else {
                // Proses INSERT
                $stmt = $pdo->prepare("INSERT INTO wilayah (nama_wilayah) VALUES (?)");
                $stmt->execute([$nama_wilayah]);
                $success_message = 'Wilayah baru berhasil ditambahkan.';
            }
        } catch (PDOException $e) {
            $error_message = 'Operasi Gagal: ' . $e->getMessage();
        }
    }
    // Setelah operasi, kembali ke halaman list
    $action = 'list';
}

// --- Logika untuk Menghapus Data ---
if ($action === 'delete' && $wilayah_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM wilayah WHERE id = ?");
        $stmt->execute([$wilayah_id]);
        $success_message = 'Wilayah berhasil dihapus.';
    } catch (PDOException $e) {
        // Error jika wilayah masih digunakan oleh pelanggan
        if ($e->getCode() == '23000') {
            $error_message = 'Gagal menghapus: Wilayah ini masih digunakan oleh data pelanggan.';
        } else {
            $error_message = 'Operasi Gagal: ' . $e->getMessage();
        }
    }
    $action = 'list';
}

// Ambil data untuk form edit
$wilayah_to_edit = null;
if ($action === 'edit' && $wilayah_id) {
    $stmt = $pdo->prepare("SELECT * FROM wilayah WHERE id = ?");
    $stmt->execute([$wilayah_id]);
    $wilayah_to_edit = $stmt->fetch();
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
                    <h5><i class="fas fa-map-marker-alt me-2"></i><?php echo $action === 'edit' ? 'Edit Wilayah' : 'Tambah Wilayah Baru'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="?page=wilayah">
                        <?php if ($action === 'edit' && $wilayah_to_edit): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($wilayah_to_edit['id']); ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="nama_wilayah" class="form-label">Nama Wilayah</label>
                            <input type="text" class="form-control" id="nama_wilayah" name="nama_wilayah" value="<?php echo htmlspecialchars($wilayah_to_edit['nama_wilayah'] ?? ''); ?>" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                        <?php if ($action === 'edit'): ?>
                            <a href="?page=wilayah" class="btn btn-secondary">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Kolom Tabel Daftar Wilayah -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Daftar Wilayah</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Nama Wilayah</th>
                                    <th scope="col" style="width: 15%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT * FROM wilayah ORDER BY nama_wilayah ASC");
                                    $wilayah_list = $stmt->fetchAll();
                                    $no = 1;
                                    if (count($wilayah_list) > 0) {
                                        foreach ($wilayah_list as $wilayah) {
                                            echo '<tr>';
                                            echo '<td>' . $no++ . '</td>';
                                            echo '<td>' . htmlspecialchars($wilayah['nama_wilayah']) . '</td>';
                                            echo '<td>';
                                            echo '<a href="?page=wilayah&action=edit&id=' . $wilayah['id'] . '" class="btn btn-sm btn-warning me-1" title="Edit"><i class="fas fa-edit"></i></a>';
                                            echo '<a href="?page=wilayah&action=delete&id=' . $wilayah['id'] . '" class="btn btn-sm btn-danger" title="Hapus" onclick="return confirm(\'Apakah Anda yakin ingin menghapus wilayah ini?\')"><i class="fas fa-trash"></i></a>';
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="3" class="text-center">Belum ada data wilayah.</td></tr>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<tr><td colspan="3" class="text-center text-danger">Gagal memuat data: ' . $e->getMessage() . '</td></tr>';
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