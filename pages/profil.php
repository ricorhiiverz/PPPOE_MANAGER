<?php
// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger">Akses ditolak. Halaman ini hanya untuk admin.</div>';
    return; // Hentikan eksekusi script jika bukan admin
}
?>

<div class="card shadow-sm">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="card-title mb-0 text-white">Daftar Profil PPPoE</h5>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProfileModal"><i class="fas fa-plus me-2"></i>Tambah Profil</button>
        <?php endif; ?>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nama Profil</th>
                    <th>Rate Limit (UL/DL)</th>
                    <th>Tagihan (Rp)</th>
                    <th class="d-none d-md-table-cell">Local Address</th> <!-- Hide on small screens -->
                    <th class="d-none d-md-table-cell">Remote Address</th> <!-- Hide on small screens -->
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($profiles)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">Tidak ada profil yang ditemukan.</td></tr>
                <?php endif; ?>
                <?php foreach ($profiles as $profile_db):
                    // Dapatkan ID MikroTik yang sesuai
                    $mikrotik_profile_id = null;
                    $mikrotik_profile_details = null;
                    foreach ($all_profiles_mikrotik as $mp) {
                        if ($mp['name'] === $profile_db['profile_name']) {
                            $mikrotik_profile_id = $mp['.id'];
                            $mikrotik_profile_details = $mp;
                            break;
                        }
                    }

                    // Data yang akan ditampilkan
                    $profile_name = htmlspecialchars($profile_db['profile_name']);
                    $rate_limit = htmlspecialchars($profile_db['rate_limit'] ?? 'N/A');
                    $tagihan = !empty($profile_db['tagihan_amount']) ? 'Rp ' . number_format($profile_db['tagihan_amount'], 0, ',', '.') : 'N/A';
                    $local_address = htmlspecialchars($profile_db['local_address'] ?? 'N/A');
                    $remote_address = htmlspecialchars($profile_db['remote_address'] ?? 'N/A');
                ?>
                <tr>
                    <td class="fw-bold"><?= $profile_name ?></td>
                    <td><span class="badge text-bg-info"><?= $rate_limit ?></span></td>
                    <td><?= $tagihan ?></td>
                    <td class="d-none d-md-table-cell"><?= $local_address ?></td> <!-- Hide on small screens -->
                    <td class="d-none d-md-table-cell"><?= $remote_address ?></td> <!-- Hide on small screens -->
                    <td class="text-center">
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="btn-group">
                            <?php if ($mikrotik_profile_id): // Hanya izinkan edit/hapus jika ada di MikroTik juga ?>
                            <button type="button" class="btn btn-sm btn-outline-info edit-profile-btn"
                                    data-bs-toggle="modal" data-bs-target="#editProfileModal"
                                    data-id="<?= htmlspecialchars($mikrotik_profile_id) ?>"
                                    data-id-db="<?= htmlspecialchars($profile_db['id']) ?>"
                                    data-name="<?= $profile_name ?>"
                                    data-rate-limit="<?= htmlspecialchars($profile_db['rate_limit'] ?? '') ?>"
                                    data-tagihan="<?= htmlspecialchars($profile_db['tagihan_amount'] ?? '') ?>"
                                    data-local-address="<?= htmlspecialchars($profile_db['local_address'] ?? '') ?>"
                                    data-remote-address="<?= htmlspecialchars($profile_db['remote_address'] ?? '') ?>"
                                    title="Edit Profil">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Anda yakin ingin menghapus profil ini dari database dan MikroTik?')">
                                <input type="hidden" name="action" value="delete_profile">
                                <input type="hidden" name="mikrotik_profile_id" value="<?= htmlspecialchars($mikrotik_profile_id) ?>">
                                <input type="hidden" name="profile_id_db" value="<?= htmlspecialchars($profile_db['id']) ?>">
                                <input type="hidden" name="profile_name" value="<?= $profile_name ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Profil"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php else: ?>
                                <span class="badge text-bg-warning me-2">Tidak di MikroTik</span>
                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('Profil ini hanya ada di database. Anda yakin ingin menghapusnya dari database?')">
                                    <input type="hidden" name="action" value="delete_profile">
                                    <input type="hidden" name="profile_id_db" value="<?= htmlspecialchars($profile_db['id']) ?>">
                                    <input type="hidden" name="mikrotik_profile_id" value=""> <!-- Kosongkan jika tidak ada di MikroTik -->
                                    <input type="hidden" name="profile_name" value="<?= $profile_name ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus dari DB"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php else: echo '<span class="text-muted small">Tidak ada aksi</span>'; endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
