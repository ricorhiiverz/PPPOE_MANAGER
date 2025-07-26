<?php
/**
 * Halaman Manajemen Pelanggan.
 *
 * Halaman ini adalah inti dari aplikasi, mengelola data pelanggan baik di
 * database lokal maupun di router MikroTik.
 *
 * @package PPPOE_MANAGER
 */

// Keamanan: Pastikan hanya admin yang bisa mengakses halaman ini.
if ($_SESSION['level'] !== 'admin') {
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    return;
}

// Memuat library RouterOS API
require_once 'RouterosAPI.php';

// Inisialisasi variabel
$API = new RouterosAPI();
$action = $_GET['action'] ?? 'list';
$pelanggan_id = $_GET['id'] ?? null;
$error_message = '';
$success_message = '';

// --- Logika untuk Memproses Form (Tambah/Edit) ---
if ($action === 'save') {
    // Ambil semua data dari POST
    $id_to_update = $_POST['id'] ?? null;
    $nama_pelanggan = trim($_POST['nama_pelanggan']);
    $no_pelanggan = trim($_POST['no_pelanggan']);
    $alamat = trim($_POST['alamat']);
    $no_hp = trim($_POST['no_hp']);
    $wilayah_id = $_POST['wilayah_id'];
    $paket_id = $_POST['paket_id'];
    $username_pppoe = trim($_POST['username_pppoe']);
    $password_pppoe = trim($_POST['password_pppoe']);
    $tanggal_pemasangan = $_POST['tanggal_pemasangan'];
    $koordinat = trim($_POST['koordinat']);
    $status_berlangganan = $_POST['status_berlangganan'];

    // Validasi dasar
    if (empty($nama_pelanggan) || empty($no_pelanggan) || empty($username_pppoe)) {
        $error_message = 'Nomor Pelanggan, Nama, dan Username PPPoE wajib diisi.';
    } else {
        // Koneksi ke MikroTik
        if ($API->connect($app_settings['mikrotik_ip'], $app_settings['mikrotik_user'], $app_settings['mikrotik_pass'])) {
            
            $mikrotik_data = [
                'name' => $username_pppoe,
                'service' => 'pppoe',
                'profile' => $app_settings['mikrotik_profile'] ?? 'default', // Asumsi ada profil di pengaturan, atau gunakan default
            ];
            // Hanya tambahkan password jika diisi (untuk update atau create)
            if (!empty($password_pppoe)) {
                $mikrotik_data['password'] = $password_pppoe;
            }

            $db_success = false;
            $mikrotik_success = false;

            if ($id_to_update) {
                // --- PROSES EDIT ---
                // Dapatkan username lama untuk mencari secret di mikrotik
                $stmt = $pdo->prepare("SELECT username_pppoe FROM pelanggan WHERE id = ?");
                $stmt->execute([$id_to_update]);
                $old_username = $stmt->fetchColumn();

                $get_secret = $API->comm('/ppp/secret/print', ["?name" => $old_username]);
                if (!empty($get_secret)) {
                    $mikrotik_data['.id'] = $get_secret[0]['.id'];
                    $API->comm('/ppp/secret/set', $mikrotik_data);
                    $mikrotik_success = true;
                } else {
                     $error_message = "Gagal update: User PPPoE '$old_username' tidak ditemukan di MikroTik.";
                }
            } else {
                // --- PROSES TAMBAH ---
                $API->comm('/ppp/secret/add', $mikrotik_data);
                $mikrotik_success = true;
            }

            // Jika operasi Mikrotik berhasil, lanjutkan ke database
            if ($mikrotik_success) {
                try {
                    if ($id_to_update) {
                        // UPDATE di database
                        $stmt = $pdo->prepare("UPDATE pelanggan SET no_pelanggan=?, nama_pelanggan=?, alamat=?, no_hp=?, wilayah_id=?, paket_id=?, username_pppoe=?, tanggal_pemasangan=?, koordinat=?, status_berlangganan=? WHERE id=?");
                        $params = [$no_pelanggan, $nama_pelanggan, $alamat, $no_hp, $wilayah_id, $paket_id, $username_pppoe, $tanggal_pemasangan, $koordinat, $status_berlangganan, $id_to_update];
                        // Jika password diisi, update juga di DB
                        if(!empty($password_pppoe)) {
                            $stmt = $pdo->prepare("UPDATE pelanggan SET no_pelanggan=?, nama_pelanggan=?, alamat=?, no_hp=?, wilayah_id=?, paket_id=?, username_pppoe=?, password_pppoe=?, tanggal_pemasangan=?, koordinat=?, status_berlangganan=? WHERE id=?");
                            $params = [$no_pelanggan, $nama_pelanggan, $alamat, $no_hp, $wilayah_id, $paket_id, $username_pppoe, $password_pppoe, $tanggal_pemasangan, $koordinat, $status_berlangganan, $id_to_update];
                        }
                        $stmt->execute($params);
                        $success_message = 'Data pelanggan berhasil diperbarui.';
                    } else {
                        // INSERT ke database
                        $stmt = $pdo->prepare("INSERT INTO pelanggan (no_pelanggan, nama_pelanggan, alamat, no_hp, wilayah_id, paket_id, username_pppoe, password_pppoe, tanggal_pemasangan, koordinat, status_berlangganan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$no_pelanggan, $nama_pelanggan, $alamat, $no_hp, $wilayah_id, $paket_id, $username_pppoe, $password_pppoe, $tanggal_pemasangan, $koordinat, $status_berlangganan]);
                        $success_message = 'Pelanggan baru berhasil ditambahkan.';
                    }
                    $action = 'list'; // Kembali ke daftar setelah sukses
                } catch (PDOException $e) {
                    $error_message = 'Operasi Database Gagal: ' . $e->getMessage();
                    // TODO: Tambahkan logika untuk menghapus user yang baru dibuat di Mikrotik jika DB gagal
                }
            }
            $API->disconnect();
        } else {
            $error_message = 'Gagal terhubung ke MikroTik. Periksa kembali IP, username, dan password di halaman Pengaturan.';
        }
    }
}

// --- Logika untuk Menghapus Data ---
if ($action === 'delete' && $pelanggan_id) {
    try {
        $stmt = $pdo->prepare("SELECT username_pppoe FROM pelanggan WHERE id = ?");
        $stmt->execute([$pelanggan_id]);
        $username_to_delete = $stmt->fetchColumn();

        if ($username_to_delete) {
            if ($API->connect($app_settings['mikrotik_ip'], $app_settings['mikrotik_user'], $app_settings['mikrotik_pass'])) {
                $get_secret = $API->comm('/ppp/secret/print', ["?name" => $username_to_delete]);
                if (!empty($get_secret)) {
                    $API->comm('/ppp/secret/remove', ['.id' => $get_secret[0]['.id']]);
                }
                $API->disconnect();

                // Hapus dari database setelah berhasil dihapus dari Mikrotik (atau jika tidak ada di Mikrotik)
                $stmt = $pdo->prepare("DELETE FROM pelanggan WHERE id = ?");
                $stmt->execute([$pelanggan_id]);
                $success_message = 'Pelanggan berhasil dihapus.';
            } else {
                $error_message = 'Gagal terhubung ke MikroTik untuk menghapus user.';
            }
        } else {
            $error_message = 'Pelanggan tidak ditemukan.';
        }
    } catch (PDOException $e) {
        $error_message = 'Operasi Database Gagal: ' . $e->getMessage();
    }
    $action = 'list';
}

// --- Tampilkan halaman berdasarkan Aksi ---
switch ($action) {
    case 'add':
    case 'edit':
        // Ambil data untuk form edit
        $pelanggan = null;
        if ($action === 'edit' && $pelanggan_id) {
            $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id = ?");
            $stmt->execute([$pelanggan_id]);
            $pelanggan = $stmt->fetch();
        }
        // Ambil data wilayah dan paket untuk dropdown
        $wilayah_list = $pdo->query("SELECT * FROM wilayah ORDER BY nama_wilayah ASC")->fetchAll();
        $paket_list = $pdo->query("SELECT * FROM paket ORDER BY nama_paket ASC")->fetchAll();
        
        // PERBAIKAN: Menghapus 'include' yang salah
        break;
    
    case 'list':
    default:
        // Ambil semua data pelanggan untuk ditampilkan di tabel
        $stmt = $pdo->query("
            SELECT p.*, w.nama_wilayah, pk.nama_paket, pk.harga 
            FROM pelanggan p
            LEFT JOIN wilayah w ON p.wilayah_id = w.id
            LEFT JOIN paket pk ON p.paket_id = pk.id
            ORDER BY p.nama_pelanggan ASC
        ");
        $pelanggan_list = $stmt->fetchAll();
        
        // PERBAIKAN: Menghapus 'include' yang salah
        break;
}

// --- File-file Tampilan (dipisah agar lebih rapi) ---

// Buat file baru bernama _form_pelanggan.php di dalam folder pages/
if (!function_exists('generate_pelanggan_form')) {
    function generate_pelanggan_form() {
        // Karena file di-include, kita perlu globalisasi variabel
        global $pelanggan, $wilayah_list, $paket_list, $action, $error_message, $success_message;
        ob_start();
        ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user-plus me-2"></i><?php echo $action === 'edit' ? 'Edit Pelanggan' : 'Tambah Pelanggan Baru'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=pelanggan&action=save">
                    <?php if ($action === 'edit' && $pelanggan): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($pelanggan['id']); ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <fieldset class="border p-3 mb-3">
                                <legend class="w-auto px-2 h6">Data Pribadi</legend>
                                <div class="mb-3">
                                    <label for="no_pelanggan" class="form-label">No. Pelanggan</label>
                                    <input type="text" class="form-control" id="no_pelanggan" name="no_pelanggan" value="<?php echo htmlspecialchars($pelanggan['no_pelanggan'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="nama_pelanggan" class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" id="nama_pelanggan" name="nama_pelanggan" value="<?php echo htmlspecialchars($pelanggan['nama_pelanggan'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="alamat" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="alamat" name="alamat" rows="2"><?php echo htmlspecialchars($pelanggan['alamat'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="no_hp" class="form-label">No. HP</label>
                                    <input type="text" class="form-control" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($pelanggan['no_hp'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="koordinat" class="form-label">Koordinat (Lat,Lng)</label>
                                    <input type="text" class="form-control" id="koordinat" name="koordinat" value="<?php echo htmlspecialchars($pelanggan['koordinat'] ?? ''); ?>">
                                </div>
                            </fieldset>
                        </div>
                        <div class="col-md-6">
                             <fieldset class="border p-3 mb-3">
                                <legend class="w-auto px-2 h6">Data Layanan & PPPoE</legend>
                                <div class="mb-3">
                                    <label for="tanggal_pemasangan" class="form-label">Tgl. Pemasangan</label>
                                    <input type="date" class="form-control" id="tanggal_pemasangan" name="tanggal_pemasangan" value="<?php echo htmlspecialchars($pelanggan['tanggal_pemasangan'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="wilayah_id" class="form-label">Wilayah</label>
                                    <select class="form-select" id="wilayah_id" name="wilayah_id">
                                        <option value="">-- Pilih Wilayah --</option>
                                        <?php foreach ($wilayah_list as $wilayah): ?>
                                            <option value="<?php echo $wilayah['id']; ?>" <?php echo (isset($pelanggan) && $pelanggan['wilayah_id'] == $wilayah['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($wilayah['nama_wilayah']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="paket_id" class="form-label">Paket Internet</label>
                                    <select class="form-select" id="paket_id" name="paket_id">
                                        <option value="">-- Pilih Paket --</option>
                                        <?php foreach ($paket_list as $paket): ?>
                                            <option value="<?php echo $paket['id']; ?>" <?php echo (isset($pelanggan) && $pelanggan['paket_id'] == $paket['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($paket['nama_paket']); ?> (Rp <?php echo number_format($paket['harga']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="username_pppoe" class="form-label">Username PPPoE</label>
                                    <input type="text" class="form-control" id="username_pppoe" name="username_pppoe" value="<?php echo htmlspecialchars($pelanggan['username_pppoe'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password_pppoe" class="form-label">Password PPPoE</label>
                                    <input type="text" class="form-control" id="password_pppoe" name="password_pppoe">
                                    <?php if ($action === 'edit'): ?>
                                    <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label for="status_berlangganan" class="form-label">Status</label>
                                    <select class="form-select" id="status_berlangganan" name="status_berlangganan">
                                        <option value="aktif" <?php echo (isset($pelanggan) && $pelanggan['status_berlangganan'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="nonaktif" <?php echo (isset($pelanggan) && $pelanggan['status_berlangganan'] == 'nonaktif') ? 'selected' : ''; ?>>Non-Aktif</option>
                                        <option value="isolir" <?php echo (isset($pelanggan) && $pelanggan['status_berlangganan'] == 'isolir') ? 'selected' : ''; ?>>Isolir</option>
                                    </select>
                                </div>
                            </fieldset>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <a href="?page=pelanggan" class="btn btn-secondary me-2">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        echo ob_get_clean();
    }
}

// Buat file baru bernama _tabel_pelanggan.php di dalam folder pages/
if (!function_exists('generate_pelanggan_table')) {
    function generate_pelanggan_table() {
        global $pelanggan_list, $error_message, $success_message;
        ob_start();
        ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-users me-2"></i>Daftar Pelanggan</h5>
                <a href="?page=pelanggan&action=add" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Tambah Pelanggan</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>No. Pelanggan</th>
                                <th>Nama</th>
                                <th>Wilayah</th>
                                <th>Paket</th>
                                <th>Username PPPoE</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pelanggan_list) > 0): ?>
                                <?php foreach ($pelanggan_list as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['no_pelanggan']); ?></td>
                                        <td><?php echo htmlspecialchars($p['nama_pelanggan']); ?></td>
                                        <td><?php echo htmlspecialchars($p['nama_wilayah'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($p['nama_paket'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($p['username_pppoe']); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = 'bg-secondary';
                                            if ($p['status_berlangganan'] == 'aktif') $status_class = 'bg-success';
                                            if ($p['status_berlangganan'] == 'isolir') $status_class = 'bg-warning text-dark';
                                            if ($p['status_berlangganan'] == 'nonaktif') $status_class = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($p['status_berlangganan']); ?></span>
                                        </td>
                                        <td>
                                            <a href="?page=pelanggan&action=edit&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="?page=pelanggan&action=delete&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger" title="Hapus" onclick="return confirm('Anda yakin ingin menghapus pelanggan ini? Aksi ini juga akan menghapus user PPPoE dari MikroTik.')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center">Belum ada data pelanggan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
        echo ob_get_clean();
    }
}


// --- Logika untuk memanggil fungsi tampilan ---
// Ini adalah cara untuk menghindari duplikasi kode HTML dan memisahkan logika dari presentasi.
// Saya sengaja tidak membuat file fisik _form_pelanggan.php dan _tabel_pelanggan.php
// agar Anda hanya perlu mengedit satu file ini.
if ($action === 'add' || $action === 'edit') {
    generate_pelanggan_form();
} else {
    generate_pelanggan_table();
}
?>