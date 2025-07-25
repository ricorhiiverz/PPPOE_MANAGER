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
                <?php foreach ($profiles as $profile): 
                    // Parse comment untuk mendapatkan tagihan
                    $comment_data = parse_comment_string($profile['comment'] ?? '');
                    $tagihan = $comment_data['tagihan'];
                ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($profile['name']) ?></td>
                    <td><span class="badge text-bg-info"><?= $profile['rate-limit'] ?? 'N/A' ?></span></td>
                    <td><?= !empty($tagihan) ? 'Rp ' . number_format($tagihan, 0, ',', '.') : 'N/A' ?></td>
                    <td class="d-none d-md-table-cell"><?= $profile['local-address'] ?? 'N/A' ?></td> <!-- Hide on small screens -->
                    <td class="d-none d-md-table-cell"><?= $profile['remote-address'] ?? 'N/A' ?></td> <!-- Hide on small screens -->
                    <td class="text-center">
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-info edit-profile-btn" 
                                    data-bs-toggle="modal" data-bs-target="#editProfileModal"
                                    data-id="<?= htmlspecialchars($profile['.id']) ?>"
                                    data-name="<?= htmlspecialchars($profile['name']) ?>"
                                    data-rate-limit="<?= htmlspecialchars($profile['rate-limit'] ?? '') ?>"
                                    data-tagihan="<?= htmlspecialchars($tagihan) ?>"
                                    data-local-address="<?= htmlspecialchars($profile['local-address'] ?? '') ?>"
                                    data-remote-address="<?= htmlspecialchars($profile['remote-address'] ?? '') ?>"
                                    title="Edit Profil">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Anda yakin ingin menghapus profil ini?')">
                                <input type="hidden" name="action" value="delete_profile">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($profile['.id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Profil"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                        <?php else: echo '<span class="text-muted small">Tidak ada aksi</span>'; endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>