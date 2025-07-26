<?php
/**
 * Halaman Profil Pengguna.
 *
 * Memungkinkan pengguna yang sedang login untuk melihat dan
 * memperbarui data pribadi mereka, termasuk password.
 *
 * @package PPPOE_MANAGER
 */

// Keamanan: File ini harus di-include dari main_view.php, jadi session sudah ada.
// Kita hanya perlu memastikan user_id ada di session.
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Sesi tidak valid. Silakan login kembali.</div>';
    return;
}

// Inisialisasi variabel
$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// --- Logika untuk Memproses Form Update Profil ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $no_hp = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    // Validasi dasar
    if (empty($nama_lengkap) || empty($username)) {
        $error_message = 'Nama Lengkap dan Username wajib diisi.';
    } elseif (!empty($password_baru) && ($password_baru !== $konfirmasi_password)) {
        $error_message = 'Konfirmasi password baru tidak cocok.';
    } else {
        try {
            // Cek apakah username baru sudah digunakan oleh orang lain
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt_check->execute([$username, $user_id]);
            if ($stmt_check->fetch()) {
                $error_message = "Username '$username' sudah digunakan oleh pengguna lain.";
            } else {
                // Jika username aman, lanjutkan update
                if (!empty($password_baru)) {
                    // Jika password diisi, update semua termasuk password
                    $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET nama_lengkap=?, username=?, password=?, no_hp=?, alamat=? WHERE id=?");
                    $stmt->execute([$nama_lengkap, $username, $hashed_password, $no_hp, $alamat, $user_id]);
                } else {
                    // Jika password kosong, update semua kecuali password
                    $stmt = $pdo->prepare("UPDATE users SET nama_lengkap=?, username=?, no_hp=?, alamat=? WHERE id=?");
                    $stmt->execute([$nama_lengkap, $username, $no_hp, $alamat, $user_id]);
                }
                
                // Update informasi di session agar langsung terlihat di header
                $_SESSION['nama_lengkap'] = $nama_lengkap;
                $_SESSION['username'] = $username;

                $success_message = 'Profil berhasil diperbarui.';
            }
        } catch (PDOException $e) {
            $error_message = 'Update profil gagal: ' . $e->getMessage();
        }
    }
}

// Ambil data terbaru pengguna untuk ditampilkan di form
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Gagal memuat data profil: ' . $e->getMessage() . '</div>';
    $user_data = []; // Kosongkan data jika gagal
}

?>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-user-edit me-2"></i>Edit Profil Saya</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="?page=profil">
                <div class="row">
                    <!-- Kolom Kiri: Informasi Dasar -->
                    <div class="col-md-6">
                        <h5 class="mb-3">Informasi Akun</h5>
                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($user_data['nama_lengkap'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="level" class="form-label">Level Akun</label>
                            <input type="text" class="form-control" id="level" value="<?php echo ucfirst(htmlspecialchars($user_data['level'] ?? '')); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="no_hp" class="form-label">No. HP</label>
                            <input type="text" class="form-control" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($user_data['no_hp'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($user_data['alamat'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Kolom Kanan: Ganti Password -->
                    <div class="col-md-6">
                        <h5 class="mb-3">Ubah Password</h5>
                        <p class="text-muted">Kosongkan field di bawah ini jika Anda tidak ingin mengubah password.</p>
                        <div class="mb-3">
                            <label for="password_baru" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="password_baru" name="password_baru">
                        </div>
                        <div class="mb-3">
                            <label for="konfirmasi_password" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password">
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
