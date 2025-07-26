<?php
/**
 * Halaman Pengaturan Aplikasi.
 *
 * Memungkinkan admin untuk mengelola semua konfigurasi sistem
 * yang disimpan di dalam tabel 'pengaturan' di database.
 *
 * @package PPPOE_MANAGER
 */

// Keamanan: Pastikan hanya admin yang bisa mengakses halaman ini.
if ($_SESSION['level'] !== 'admin') {
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    return; // Hentikan eksekusi jika bukan admin.
}

// Inisialisasi variabel pesan.
$success_message = '';
$error_message = '';

// Proses form jika metode request adalah POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Keamanan tambahan: Pastikan hanya admin yang bisa mengirim data ke halaman ini.
    if ($_SESSION['level'] !== 'admin') {
        $error_message = "Aksi tidak diizinkan.";
    } else {
        try {
            // Menggunakan logika "UPSERT" (INSERT ... ON DUPLICATE KEY UPDATE)
            $stmt = $pdo->prepare("
                INSERT INTO pengaturan (setting_name, setting_value) 
                VALUES (:setting_name, :setting_value) 
                ON DUPLICATE KEY UPDATE setting_value = :setting_value
            ");

            // Mulai transaksi untuk memastikan semua pembaruan berhasil.
            $pdo->beginTransaction();

            // Loop melalui semua data yang dikirim dari form.
            foreach ($_POST as $key => $value) {
                // Logika untuk tidak menghapus password jika kosong.
                if ($key === 'mikrotik_pass' && empty($value)) {
                    continue; 
                }
                
                // Update atau Insert setiap pengaturan di database.
                $stmt->execute([
                    ':setting_name' => $key,
                    ':setting_value' => trim($value)
                ]);
            }

            // Jika semua berhasil, commit perubahan.
            $pdo->commit();

            $success_message = "Pengaturan berhasil diperbarui!";

            // Muat ulang pengaturan ke dalam variabel $app_settings
            $app_settings = load_app_settings($pdo);

        } catch (PDOException $e) {
            // Jika terjadi error, batalkan semua perubahan.
            $pdo->rollBack();
            $error_message = "Gagal memperbarui pengaturan: " . $e->getMessage();
        }
    }
}

?>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<form method="POST" action="?page=pengaturan">
    <div class="row">
        <!-- Kolom Kiri: Pengaturan Umum & MikroTik -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-building me-2"></i>Informasi ISP</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="nama_isp" class="form-label">Nama ISP</label>
                        <input type="text" class="form-control" id="nama_isp" name="nama_isp" value="<?php echo htmlspecialchars($app_settings['nama_isp'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="alamat_isp" class="form-label">Alamat ISP</label>
                        <textarea class="form-control" id="alamat_isp" name="alamat_isp" rows="2"><?php echo htmlspecialchars($app_settings['alamat_isp'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="no_hp_isp" class="form-label">No. HP / Kontak</label>
                        <input type="text" class="form-control" id="no_hp_isp" name="no_hp_isp" value="<?php echo htmlspecialchars($app_settings['no_hp_isp'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="website_isp" class="form-label">Website</label>
                        <input type="text" class="form-control" id="website_isp" name="website_isp" value="<?php echo htmlspecialchars($app_settings['website_isp'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-server me-2"></i>Konfigurasi MikroTik</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="mikrotik_ip" class="form-label">IP Address MikroTik</label>
                        <input type="text" class="form-control" id="mikrotik_ip" name="mikrotik_ip" value="<?php echo htmlspecialchars($app_settings['mikrotik_ip'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="mikrotik_user" class="form-label">Username MikroTik</label>
                        <input type="text" class="form-control" id="mikrotik_user" name="mikrotik_user" value="<?php echo htmlspecialchars($app_settings['mikrotik_user'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="mikrotik_pass" class="form-label">Password MikroTik</label>
                        <input type="password" class="form-control" id="mikrotik_pass" name="mikrotik_pass" value="">
                        <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password yang sudah ada.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Payment Gateway & Notifikasi -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-credit-card me-2"></i>Payment Gateway (Tripay)</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="payment_merchant_code" class="form-label">Kode Merchant</label>
                        <input type="text" class="form-control" id="payment_merchant_code" name="payment_merchant_code" value="<?php echo htmlspecialchars($app_settings['payment_merchant_code'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="payment_api_key" class="form-label">API Key</label>
                        <input type="text" class="form-control" id="payment_api_key" name="payment_api_key" value="<?php echo htmlspecialchars($app_settings['payment_api_key'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="payment_private_key" class="form-label">Private Key</label>
                        <input type="text" class="form-control" id="payment_private_key" name="payment_private_key" value="<?php echo htmlspecialchars($app_settings['payment_private_key'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fab fa-whatsapp me-2"></i>Notifikasi WhatsApp (Fonnte)</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="fonnte_token" class="form-label">Fonnte Auth Token</label>
                        <input type="text" class="form-control" id="fonnte_token" name="fonnte_token" value="<?php echo htmlspecialchars($app_settings['fonnte_token'] ?? ''); ?>">
                        <small class="form-text text-muted">Masukkan token otorisasi dari akun Fonnte Anda.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-grid">
        <button type="submit" class="btn btn-primary btn-lg">Simpan Pengaturan</button>
    </div>
</form>