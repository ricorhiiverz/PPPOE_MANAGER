<?php
// Pastikan pengguna memiliki akses untuk melihat halaman ini
if (!in_array($_SESSION['role'], ['admin', 'teknisi', 'penagih'])) {
    echo '<div class="alert alert-danger">Akses ditolak.</div>';
    return;
}
?>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0 text-white"><?= $table_title ?></h5>
            <form action="" method="GET" class="row g-2 align-items-center flex-grow-1 justify-content-end">
                <input type="hidden" name="page" value="pelanggan">
                <div class="col-auto">
                    <label for="filterStatus" class="visually-hidden">Filter Status</label>
                    <select class="form-select form-select-sm" id="filterStatus" name="filter" onchange="this.form.submit()">
                        <option value="all" <?= ($filter === 'all' ? 'selected' : '') ?>>Semua Status</option>
                        <option value="active" <?= ($filter === 'active' ? 'selected' : '') ?>>Online</option>
                        <option value="offline" <?= ($filter === 'offline' ? 'selected' : '') ?>>Offline</option>
                        <option value="disabled" <?= ($filter === 'disabled' ? 'selected' : '') ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label for="searchInput" class="visually-hidden">Cari username/profil</label>
                    <div class="input-group input-group-sm">
                        <input class="form-control" type="search" name="search" id="searchInput" placeholder="Cari username/profil..." aria-label="Search" value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit" title="Cari"><i class="fas fa-search"></i></button>
                        <?php if (!empty($search) || $filter !== 'all'): ?>
                            <a href="?page=pelanggan" class="btn btn-outline-secondary" title="Reset Filter"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Status</th><th>Username</th><th>Profil</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
                <?php if (empty($paginated_secrets)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-5">Tidak ada data untuk ditampilkan.</td></tr>
                <?php endif; ?>
                <?php foreach ($paginated_secrets as $secret): ?>
                <tr>
                    <td>
                        <?php if (isset($secret['disabled']) && $secret['disabled'] == 'true'): ?><span class="badge text-bg-danger">Disabled</span>
                        <?php elseif (in_array($secret['name'], $active_usernames)): ?><span class="badge text-bg-success">Online</span>
                        <?php else: ?><span class="badge text-bg-secondary">Offline</span><?php endif; ?>
                    </td>
                    <td class="fw-bold"><?= htmlspecialchars($secret['name']) ?></td>
                    <td><?= htmlspecialchars($secret['profile']) ?></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary detail-btn" data-bs-toggle="modal" data-bs-target="#detailUserModal" data-id="<?= htmlspecialchars($secret['.id']) ?>" title="Lihat Detail"><i class="fas fa-eye"></i></button>
                            <?php if (in_array($_SESSION['role'], ['admin', 'teknisi'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-info edit-btn" data-bs-toggle="modal" data-bs-target="#editUserModal" data-id="<?= htmlspecialchars($secret['.id']) ?>" data-name="<?= htmlspecialchars($secret['name']) ?>" data-profile="<?= htmlspecialchars($secret['profile']) ?>" title="Edit"><i class="fas fa-edit"></i></button>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <?php if (isset($secret['disabled']) && $secret['disabled'] == 'true'): ?>
                                    <form action="" method="POST" class="d-inline"><input type="hidden" name="action" value="enable_user"><input type="hidden" name="id" value="<?= htmlspecialchars($secret['.id']) ?>"><button type="submit" class="btn btn-sm btn-outline-success" title="Enable"><i class="fas fa-check-circle"></i></button></form>
                                <?php else: ?>
                                    <form action="" method="POST" class="d-inline"><input type="hidden" name="action" value="disable_user"><input type="hidden" name="id" value="<?= htmlspecialchars($secret['.id']) ?>"><button type="submit" class="btn btn-sm btn-outline-warning" title="Disable"><i class="fas fa-ban"></i></button></form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (in_array($secret['name'], $active_usernames)): $active_id = array_search($secret['name'], array_column($active_sessions, 'name')); ?>
                                <form action="" method="POST" class="d-inline"><input type="hidden" name="action" value="disconnect_user"><input type="hidden" name="id" value="<?= htmlspecialchars($active_sessions[$active_id]['.id']) ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="Disconnect" onclick="return confirm('Anda yakin ingin memutuskan koneksi pelanggan ini?')"><i class="fas fa-plug-circle-xmark"></i></button></form>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('PERINGATAN: Pelanggan ini akan dihapus permanen. Lanjutkan?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= htmlspecialchars($secret['.id']) ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Permanen"><i class="fas fa-trash"></i></button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex flex-column flex-md-row justify-content-between align-items-center">
        <span class="text-muted small mb-2 mb-md-0">Halaman <?= $current_page_num ?> dari <?= $total_pages ?> (Total <?= $total_items ?> item)</span>
        <nav aria-label="Page navigation"><ul class="pagination mb-0">
                <li class="page-item <?= $current_page_num <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=pelanggan&filter=<?= $filter ?>&search=<?= $search ?>&p=<?= $current_page_num - 1 ?>">Sebelumnya</a></li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $current_page_num ? 'active' : '' ?>"><a class="page-link" href="?page=pelanggan&filter=<?= $filter ?>&search=<?= $search ?>&p=<?= $i ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $current_page_num >= $total_pages ? 'disabled' : '' ?>"><a class="page-link" href="?page=pelanggan&filter=<?= $filter ?>&search=<?= $search ?>&p=<?= $current_page_num + 1 ?>">Selanjutnya</a></li>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>