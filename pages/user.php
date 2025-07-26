<?php
/**
 * Halaman Manajemen User.
 *
 * Mengelola data pengguna sistem (admin, teknisi, penagih).
 * Semua operasi dilakukan pada tabel 'users' di database.
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
$user_id = $_GET['id'] ?? null;
$error_message = '';
$success_message = '';

// --- Logika untuk Memproses Form (Tambah/Edit) ---
if ($action === 'save') {
    $id_to_update = $_POST['id'] ?? null;
    $username = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $password = $_POST['password'];
    $level = $_POST['level'];
    $no_hp = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);

    // Validasi dasar
    if (empty($username) || empty($nama_lengkap) || empty($level)) {
        $error_message = 'Username, Nama Lengkap, dan Level wajib diisi.';
    } elseif (!$id_to_update && empty($password)) {
        $error_message = 'Password wajib diisi untuk pengguna baru.';
    } else {
        try {
            if ($id_to_update) {
                // --- PROSES EDIT ---
                if (!empty($password)) {
                    // Jika password diisi, update semua termasuk password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username=?, nama_lengkap=?, password=?, level=?, no_hp=?, alamat=? WHERE id=?");
                    $stmt->execute([$username, $nama_lengkap, $hashed_password, $level, $no_hp, $alamat, $id_to_update]);
                } else {
                    // Jika password kosong, update semua kecuali password
                    $stmt = $pdo->prepare("UPDATE users SET username=?, nama_lengkap=?, level=?, no_hp=?, alamat=? WHERE id=?");
                    $stmt->execute([$username, $nama_lengkap, $level, $no_hp, $alamat, $id_to_update]);
                }
                $success_message = 'Data pengguna berhasil diperbarui.';
            } else {
                // --- PROSES TAMBAH ---
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, nama_lengkap, password, level, no_hp, alamat) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $nama_lengkap, $hashed_password, $level, $no_hp, $alamat]);
                $success_message = 'Pengguna baru berhasil ditambahkan.';
            }
            $action = 'list'; // Kembali ke daftar
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Error untuk duplicate entry
                 $error_message = "Operasi Gagal: Username '$username' sudah digunakan.";
            } else {
                 $error_message = 'Operasi Database Gagal: ' . $e->getMessage();
            }
        }
    }
}

// --- Logika untuk Menghapus Data ---
if ($action === 'delete' && $user_id) {
    // Pengaman: Jangan biarkan user menghapus akunnya sendiri
    if ($user_id == $_SESSION['user_id']) {
        $error_message = 'Anda tidak dapat menghapus akun Anda sendiri.';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $success_message = 'Pengguna berhasil dihapus.';
        } catch (PDOException $e) {
            $error_message = 'Operasi Gagal: ' . $e->getMessage();
        }
    }
    $action = 'list';
}

// --- Tampilkan Halaman Berdasarkan Aksi ---
switch ($action) {
    case 'add':
    case 'edit':
        $user_data = null;
        if ($action === 'edit' && $user_id) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();
        }
        // Tampilkan form
        display_user_form($user_data, $action);
        break;
    
    case 'list':
    default:
        $users_list = $pdo->query("SELECT * FROM users ORDER BY nama_lengkap ASC")->fetchAll();
        // Tampilkan tabel
        display_users_table($users_list, $error_message, $success_message);
        break;
}

// --- Fungsi Tampilan ---
function display_user_form($user, $action) {
    ?>
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-user-edit me-2"></i><?php echo $action === 'edit' ? 'Edit Pengguna' : 'Tambah Pengguna Baru'; ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="?page=user&action=save">
                <?php if ($action === 'edit' && $user): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($user['nama_lengkap'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo ($action === 'add') ? 'required' : ''; ?>>
                            <?php if ($action === 'edit'): ?>
                                <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="level" class="form-label">Level</label>
                            <select class="form-select" id="level" name="level" required>
                                <option value="admin" <?php echo (isset($user) && $user['level'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="teknisi" <?php echo (isset($user) && $user['level'] == 'teknisi') ? 'selected' : ''; ?>>Teknisi</option>
                                <option value="penagih" <?php echo (isset($user) && $user['level'] == 'penagih') ? 'selected' : ''; ?>>Penagih</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="no_hp" class="form-label">No. HP</label>
                            <input type="text" class="form-control" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($user['no_hp'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="2"><?php echo htmlspecialchars($user['alamat'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <a href="?page=user" class="btn btn-secondary me-2">Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function display_users_table($users, $error, $success) {
    ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-user-cog me-2"></i>Daftar Pengguna Sistem</h5>
            <a href="?page=user&action=add" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Tambah Pengguna</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th>Level</th>
                            <th>No. HP</th>
                            <th style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <?php 
                                        $level_class = 'bg-secondary';
                                        if ($user['level'] == 'admin') $level_class = 'bg-primary';
                                        if ($user['level'] == 'teknisi') $level_class = 'bg-info text-dark';
                                        if ($user['level'] == 'penagih') $level_class = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?php echo $level_class; ?>"><?php echo ucfirst($user['level']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['no_hp']); ?></td>
                                    <td>
                                        <a href="?page=user&action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): // Tombol hapus tidak muncul untuk diri sendiri ?>
                                        <a href="?page=user&action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="Hapus" onclick="return confirm('Anda yakin ingin menghapus pengguna ini?')"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">Belum ada data pengguna.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
?>
