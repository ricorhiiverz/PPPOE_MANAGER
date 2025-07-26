<?php
// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger">Akses ditolak. Halaman ini hanya untuk admin.</div>';
    return; // Hentikan eksekusi script jika bukan admin
}
?>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="card-title mb-0 text-white">Tambah Wilayah Baru</h5></div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_wilayah">
                    <div class="mb-3">
                        <label for="nama_wilayah" class="form-label">Nama Wilayah/Area</label>
                        <input type="text" name="nama_wilayah" id="nama_wilayah" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Tambah</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="card-title mb-0 text-white">Daftar Wilayah</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Nama Wilayah</th><th class="text-center">Aksi</th></tr></thead>
                    <tbody>
                        <?php if (empty($wilayah_list)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4">Belum ada data wilayah.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($wilayah_list as $wilayah): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($wilayah['region_name']) ?></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-info edit-wilayah-btn"
                                            data-bs-toggle="modal" data-bs-target="#editWilayahModal"
                                            data-id="<?= htmlspecialchars($wilayah['id']) ?>"
                                            data-name="<?= htmlspecialchars($wilayah['region_name']) ?>"
                                            title="Edit Wilayah">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="" method="POST" class="d-inline" onsubmit="return confirm('Anda yakin ingin menghapus wilayah ini?')">
                                        <input type="hidden" name="action" value="delete_wilayah">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($wilayah['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Wilayah"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Wilayah -->
<div class="modal fade" id="editWilayahModal" tabindex="-1" aria-labelledby="editWilayahModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editWilayahModalLabel">Edit Wilayah</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_wilayah">
                    <input type="hidden" name="wilayah_id" id="edit-wilayah-id">
                    <div class="mb-3">
                        <label for="edit_nama_wilayah" class="form-label">Nama Wilayah</label>
                        <input type="text" name="edit_nama_wilayah" id="edit-nama-wilayah" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editWilayahModal = document.getElementById('editWilayahModal');
    if (editWilayahModal) {
        editWilayahModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const wilayahId = button.getAttribute('data-id');
            const wilayahName = button.getAttribute('data-name');

            editWilayahModal.querySelector('#edit-wilayah-id').value = wilayahId;
            editWilayahModal.querySelector('#edit-nama-wilayah').value = wilayahName;
        });
    }
});
</script>