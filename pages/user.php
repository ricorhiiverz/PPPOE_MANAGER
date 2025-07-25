<?php
// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger">Akses ditolak. Halaman ini hanya untuk admin.</div>';
    return; // Hentikan eksekusi script jika bukan admin
}

// Ambil daftar wilayah untuk dropdown
$wilayah_list_for_users = get_wilayah(); // Pastikan fungsi get_wilayah() tersedia
?>

<div class="card shadow-sm">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="card-title mb-0 text-white">Daftar Pengguna Aplikasi</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAppUserModal"><i class="fas fa-user-plus me-2"></i>Tambah User</button>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nama Lengkap</th>
                    <th>Username (Login)</th>
                    <th>Role</th>
                    <th>Wilayah Ditugaskan</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($app_users)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">Tidak ada pengguna.</td></tr>
                <?php endif; ?>
                <?php foreach ($app_users as $user): 
                    $assigned_regions_display = json_decode($user['assigned_regions'] ?? '[]', true);
                    $assigned_regions_text = empty($assigned_regions_display) ? 'Semua Wilayah' : implode(', ', $assigned_regions_display);
                ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($user['full_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td>
                        <?php 
                            $role = $user['role'];
                            $badge_class = 'secondary';
                            if ($role === 'admin') $badge_class = 'success';
                            if ($role === 'teknisi') $badge_class = 'info';
                            if ($role === 'penagih') $badge_class = 'warning';
                        ?>
                        <span class="badge text-bg-<?= $badge_class ?>"><?= ucfirst($role) ?></span>
                    </td>
                    <td><?= htmlspecialchars($assigned_regions_text) ?></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-info edit-app-user-btn"
                                    data-bs-toggle="modal" data-bs-target="#editAppUserModal"
                                    data-id="<?= $user['id'] ?>"
                                    data-username="<?= htmlspecialchars($user['username']) ?>"
                                    data-full-name="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                                    data-role="<?= htmlspecialchars($user['role']) ?>"
                                    data-assigned-regions='<?= htmlspecialchars($user['assigned_regions'] ?? '[]') ?>'
                                    title="Edit Pengguna">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($user['username'] !== $_SESSION['username']): // Admin tidak bisa menghapus dirinya sendiri ?>
                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Anda yakin ingin menghapus pengguna ini?')">
                                <input type="hidden" name="action" value="delete_app_user">
                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Pengguna"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah User Aplikasi -->
<div class="modal fade" id="addAppUserModal" tabindex="-1" aria-labelledby="addAppUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAppUserModalLabel">Tambah Pengguna Aplikasi Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_app_user">
                    <div class="mb-3"><label class="form-label">Nama Lengkap</label><input type="text" name="app_full_name" required class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Username (untuk login)</label><input type="text" name="app_username" required class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="app_password" required class="form-control"></div>
                    <div class="mb-3">
                        <label for="add_app_role" class="form-label">Role</label>
                        <select name="app_role" id="add_app_role" class="form-select">
                            <option value="admin">Admin</option>
                            <option value="teknisi">Teknisi</option>
                            <option value="penagih" selected>Penagih</option>
                        </select>
                    </div>
                    <div class="mb-3" id="add_assigned_regions_container" style="display: block;">
                        <label class="form-label">Wilayah Ditugaskan (khusus Penagih)</label>
                        <div class="form-check-group border rounded p-2">
                            <?php if (empty($wilayah_list_for_users)): ?>
                                <p class="text-muted small mb-0">Belum ada wilayah yang terdaftar. Tambahkan di halaman Manajemen Wilayah.</p>
                            <?php else: ?>
                                <?php foreach ($wilayah_list_for_users as $wilayah): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="assigned_regions[]" value="<?= htmlspecialchars($wilayah) ?>" id="add_region_<?= str_replace(' ', '_', $wilayah) ?>">
                                        <label class="form-check-label" for="add_region_<?= str_replace(' ', '_', $wilayah) ?>">
                                            <?= htmlspecialchars($wilayah) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="form-text">Pilih satu atau lebih wilayah yang akan ditugaskan kepada penagih ini. Kosongkan untuk akses ke semua wilayah.</div>
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User Aplikasi -->
<div class="modal fade" id="editAppUserModal" tabindex="-1" aria-labelledby="editAppUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAppUserModalLabel">Edit Pengguna Aplikasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_app_user">
                    <input type="hidden" name="app_user_id" id="edit_app_user_id">
                    <div class="mb-3"><label class="form-label">Nama Lengkap</label><input type="text" name="app_full_name" id="edit_app_full_name" required class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Username (untuk login)</label><input type="text" name="app_username" id="edit_app_username" required class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Password Baru (Opsional)</label><input type="password" name="app_password" class="form-control" placeholder="Kosongkan jika tidak ingin diubah"></div>
                    <div class="mb-3">
                        <label for="edit_app_role" class="form-label">Role</label>
                        <select name="app_role" id="edit_app_role" class="form-select">
                            <option value="admin">Admin</option>
                            <option value="teknisi">Teknisi</option>
                            <option value="penagih">Penagih</option>
                        </select>
                    </div>
                    <div class="mb-3" id="edit_assigned_regions_container" style="display: none;">
                        <label class="form-label">Wilayah Ditugaskan (khusus Penagih)</label>
                        <div class="form-check-group border rounded p-2">
                            <?php if (empty($wilayah_list_for_users)): ?>
                                <p class="text-muted small mb-0">Belum ada wilayah yang terdaftar. Tambahkan di halaman Manajemen Wilayah.</p>
                            <?php else: ?>
                                <?php foreach ($wilayah_list_for_users as $wilayah): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="assigned_regions[]" value="<?= htmlspecialchars($wilayah) ?>" id="edit_region_<?= str_replace(' ', '_', $wilayah) ?>">
                                        <label class="form-check-label" for="edit_region_<?= str_replace(' ', '_', $wilayah) ?>">
                                            <?= htmlspecialchars($wilayah) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="form-text">Pilih satu atau lebih wilayah yang akan ditugaskan kepada penagih ini. Kosongkan untuk akses ke semua wilayah.</div>
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fungsi untuk menampilkan/menyembunyikan pilihan wilayah berdasarkan role
    function toggleAssignedRegions(roleSelectId, regionsContainerId) {
        const roleSelect = document.getElementById(roleSelectId);
        const regionsContainer = document.getElementById(regionsContainerId);
        if (roleSelect && regionsContainer) {
            if (roleSelect.value === 'penagih') {
                regionsContainer.style.display = 'block';
            } else {
                regionsContainer.style.display = 'none';
            }
        }
    }

    // Untuk modal Tambah User
    const addAppRoleSelect = document.getElementById('add_app_role');
    if (addAppRoleSelect) {
        addAppRoleSelect.addEventListener('change', () => toggleAssignedRegions('add_app_role', 'add_assigned_regions_container'));
        toggleAssignedRegions('add_app_role', 'add_assigned_regions_container'); // Initial check
    }

    // Untuk modal Edit User
    const editAppUserModal = document.getElementById('editAppUserModal');
    if (editAppUserModal) {
        editAppUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-id');
            const username = button.getAttribute('data-username');
            const fullName = button.getAttribute('data-full-name');
            const role = button.getAttribute('data-role');
            const assignedRegionsJson = button.getAttribute('data-assigned-regions');
            const assignedRegions = JSON.parse(assignedRegionsJson);

            // Isi form
            editAppUserModal.querySelector('#edit_app_user_id').value = userId;
            editAppUserModal.querySelector('#edit_app_username').value = username;
            editAppUserModal.querySelector('#edit_app_full_name').value = fullName;
            editAppUserModal.querySelector('#edit_app_role').value = role;

            // Atur pilihan wilayah (checkboxes)
            const editRegionsContainer = editAppUserModal.querySelector('#edit_assigned_regions_container');
            if (editRegionsContainer) {
                const checkboxes = editRegionsContainer.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = assignedRegions.includes(checkbox.value);
                });
            }

            // Tampilkan/sembunyikan container wilayah berdasarkan role
            toggleAssignedRegions('edit_app_role', 'edit_assigned_regions_container');
        });

        // Event listener untuk perubahan role di modal Edit
        const editAppRoleSelect = editAppUserModal.querySelector('#edit_app_role');
        if (editAppRoleSelect) {
            editAppRoleSelect.addEventListener('change', () => toggleAssignedRegions('edit_app_role', 'edit_assigned_regions_container'));
        }
    }
});
</script>
