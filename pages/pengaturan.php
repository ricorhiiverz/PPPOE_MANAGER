<?php
// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger">Akses ditolak. Halaman ini hanya untuk admin.</div>';
    return; // Hentikan eksekusi script jika bukan admin
}
?>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="card-title mb-0 text-white">Pengaturan Koneksi MikroTik</h5></div>
    <div class="card-body">
        <form action="index.php?page=pengaturan" method="POST">
            <input type="hidden" name="action" value="save_settings">
            <div class="mb-3">
                <label for="router_ip" class="form-label">Alamat IP / Host MikroTik</label>
                <input type="text" name="router_ip" id="router_ip" class="form-control" value="<?= htmlspecialchars($settings['router_ip'] ?? '') ?>" required>
                <div class="form-text">Contoh: 192.168.88.1 atau 103.125.173.30:1003 (jika menggunakan port non-standar)</div>
            </div>
            <div class="mb-3">
                <label for="router_user" class="form-label">Username MikroTik API</label>
                <input type="text" name="router_user" id="router_user" class="form-control" value="<?= htmlspecialchars($settings['router_user'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="router_pass" class="form-label">Password MikroTik API</label>
                <input type="password" name="router_pass" id="router_pass" class="form-control" value="<?= htmlspecialchars($settings['router_pass'] ?? '') ?>" placeholder="Kosongkan jika tidak ingin diubah">
                <div class="form-text">Kosongkan jika tidak ingin mengubah password yang sudah tersimpan.</div>
            </div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <button type="submit" class="btn btn-primary">Simpan Pengaturan MikroTik</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="card-title mb-0 text-white">Pengaturan Koneksi Database (MySQL)</h5></div>
    <div class="card-body">
        <form action="index.php?page=pengaturan" method="POST">
            <input type="hidden" name="action" value="save_settings">
            <div class="mb-3">
                <label for="db_host" class="form-label">Host Database</label>
                <input type="text" name="db_host" id="db_host" class="form-control" value="<?= htmlspecialchars($settings['db_host'] ?? '') ?>" required>
                <div class="form-text">Contoh: localhost atau 127.0.0.1</div>
            </div>
            <div class="mb-3">
                <label for="db_port" class="form-label">Port Database</label>
                <input type="number" name="db_port" id="db_port" class="form-control" value="<?= htmlspecialchars($settings['db_port'] ?? '3306') ?>" required>
                <div class="form-text">Port default MySQL adalah 3306.</div>
            </div>
            <div class="mb-3">
                <label for="db_name" class="form-label">Nama Database</label>
                <input type="text" name="db_name" id="db_name" class="form-control" value="<?= htmlspecialchars($settings['db_name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="db_user" class="form-label">Username Database</label>
                <input type="text" name="db_user" id="db_user" class="form-control" value="<?= htmlspecialchars($settings['db_user'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="db_pass" class="form-label">Password Database</label>
                <input type="password" name="db_pass" id="db_pass" class="form-control" value="<?= htmlspecialchars($settings['db_pass'] ?? '') ?>" placeholder="Kosongkan jika tidak ingin diubah">
                <div class="form-text">Kosongkan jika tidak ingin mengubah password yang sudah tersimpan.</div>
            </div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <button type="submit" class="btn btn-primary">Simpan Pengaturan Database</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="card-title mb-0 text-white">Pengaturan Payment Gateway (Tripay)</h5></div>
    <div class="card-body">
        <form action="index.php?page=pengaturan" method="POST">
            <input type="hidden" name="action" value="save_settings">
            <div class="mb-3">
                <label for="tripay_merchant_code" class="form-label">Tripay Merchant Code</label>
                <input type="text" name="tripay_merchant_code" id="tripay_merchant_code" class="form-control" value="<?= htmlspecialchars($settings['tripay_merchant_code'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="tripay_api_key" class="form-label">Tripay API Key</label>
                <input type="text" name="tripay_api_key" id="tripay_api_key" class="form-control" value="<?= htmlspecialchars($settings['tripay_api_key'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="tripay_private_key" class="form-label">Tripay Private Key</label>
                <input type="text" name="tripay_private_key" id="tripay_private_key" class="form-control" value="<?= htmlspecialchars($settings['tripay_private_key'] ?? '') ?>" required>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="tripay_production_mode" name="tripay_production_mode" <?= ($settings['tripay_production_mode'] ?? false) ? 'checked' : '' ?>>
                <label class="form-check-label" for="tripay_production_mode">Mode Produksi (Aktifkan untuk transaksi riil)</label>
            </div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <button type="submit" class="btn btn-primary">Simpan Pengaturan Tripay</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="card-title mb-0 text-white">Pengaturan WhatsApp Gateway (Fonnte)</h5></div>
    <div class="card-body">
        <form action="index.php?page=pengaturan" method="POST" id="fonnteeSettingsForm">
            <input type="hidden" name="action" value="save_settings">
            <div class="mb-3">
                <label for="fonnte_api_key" class="form-label">Fonnte API Key</label>
                <input type="text" name="fonnte_api_key" id="fonnte_api_key" class="form-control" value="<?= htmlspecialchars($settings['fonnte_api_key'] ?? '') ?>">
            </div>
            <!-- Fonnte Instance ID dihapus dari form -->
            <input type="hidden" name="fonnte_instance_id" id="fonnte_instance_id" value=""> <!-- Tetap kirim nilai kosong -->
            <div class="mb-3">
                <label for="fonnte_base_url" class="form-label">Fonnte Base URL</label>
                <input type="text" name="fonnte_base_url" id="fonnte_base_url" class="form-control" value="<?= htmlspecialchars($settings['fonnte_base_url'] ?? 'https://api.fonnte.com/send') ?>">
                <div class="form-text">URL default: https://api.fonnte.com/send</div>
            </div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <button type="submit" class="btn btn-primary me-2">Simpan Pengaturan Fonnte</button>
            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#testFonteeModal">Uji Fonnte API</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header"><h5 class="card-title mb-0 text-white">Pengaturan Monitoring</h5></div>
    <div class="card-body">
        <form action="index.php?page=pengaturan" method="POST">
            <input type="hidden" name="action" value="save_settings">
            <div class="mb-3">
                <label for="monitor_interface" class="form-label">Pilih Interface untuk Monitoring Traffic</label>
                <select name="monitor_interface" id="monitor_interface" class="form-select" <?= $_SESSION['role'] !== 'admin' ? 'disabled' : '' ?>>
                    <option value="">-- Tidak Ada --</option>
                    <?php foreach ($interfaces as $interface): ?>
                        <option value="<?= htmlspecialchars($interface['name']) ?>" <?= (isset($settings['monitor_interface']) && $settings['monitor_interface'] === $interface['name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($interface['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Pilih interface utama (WAN/Internet) untuk melihat total traffic yang digunakan.</div>
            </div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <button type="submit" class="btn btn-primary">Simpan Pengaturan Monitoring</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Modal Uji Fontee API -->
<div class="modal fade" id="testFonteeModal" tabindex="-1" aria-labelledby="testFonteeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testFonteeModalLabel">Uji Fonnte API</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Kirim pesan uji ke nomor WhatsApp untuk memverifikasi pengaturan Fonnte API Anda.</p>
                <div class="mb-3">
                    <label for="test_phone_number" class="form-label">Nomor WhatsApp Tujuan (dengan kode negara)</label>
                    <input type="tel" id="test_phone_number" class="form-control" placeholder="Contoh: 6281234567890">
                    <div class="form-text">Pastikan nomor aktif dan terdaftar di WhatsApp.</div>
                </div>
                <div class="mb-3">
                    <label for="test_message" class="form-label">Pesan Uji</label>
                    <textarea id="test_message" class="form-control" rows="3">Ini adalah pesan uji dari PPPoE Manager Anda.</textarea>
                </div>
                <div id="test_result" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="sendTestMessageBtn">Kirim Pesan Uji</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ... (kode JavaScript yang sudah ada) ...

    // Event listener untuk tombol "Kirim Pesan Uji" di modal Fontee
    const sendTestMessageBtn = document.getElementById('sendTestMessageBtn');
    if (sendTestMessageBtn) {
        sendTestMessageBtn.addEventListener('click', function() {
            const phoneNumber = document.getElementById('test_phone_number').value;
            const message = document.getElementById('test_message').value;
            const testResultDiv = document.getElementById('test_result');
            
            // Ambil pengaturan Fonnte dari form (bukan dari PHP langsung, karena mungkin belum disimpan)
            const fonnteApiKey = document.getElementById('fonnte_api_key').value;
            // Instance ID tidak lagi diambil dari input, karena sudah dihapus
            // const fonnteInstanceId = document.getElementById('fonnte_instance_id').value; 
            const fonnteBaseUrl = document.getElementById('fonnte_base_url').value;

            // --- Validasi sisi klien baru ---
            if (!fonnteApiKey || !fonnteBaseUrl) { // Validasi hanya untuk API Key dan Base URL
                testResultDiv.innerHTML = '<div class="alert alert-danger">API Key dan Base URL Fonnte tidak boleh kosong. Harap isi semua kolom.</div>';
                return; // Hentikan eksekusi jika ada kolom kosong
            }
            if (!phoneNumber) {
                testResultDiv.innerHTML = '<div class="alert alert-danger">Nomor WhatsApp tujuan tidak boleh kosong.</div>';
                return;
            }
            // --- Akhir Validasi sisi klien baru ---

            testResultDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-info" role="status"><span class="visually-hidden">Mengirim...</span></div></div>';
            testResultDiv.classList.remove('alert', 'alert-success', 'alert-danger');

            fetch('index.php?action=test_fonnte_api', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    to: phoneNumber,
                    message: message,
                    api_key: fonnteApiKey,
                    base_url: fonnteBaseUrl
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    testResultDiv.innerHTML = `<div class="alert alert-success">Pesan berhasil dikirim! Respons: ${data.message}</div>`;
                } else {
                    testResultDiv.innerHTML = `<div class="alert alert-danger">Gagal mengirim pesan: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error testing Fonnte API:', error);
                testResultDiv.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan saat menguji API: ${error.message}</div>`;
            });
        });
    }
});
</script>