<?php
// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger">Akses ditolak. Halaman ini hanya untuk admin.</div>';
    return; // Hentikan eksekusi script jika bukan admin
}
?>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0 text-white">Manajemen Laporan Gangguan</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addReportModal"><i class="fas fa-plus me-2"></i>Buat Laporan Baru</button>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form action="" method="GET" class="row g-2 align-items-center mb-4">
            <input type="hidden" name="page" value="laporan">
            <div class="col-lg-3 col-md-6 col-sm-12">
                <label for="search_report" class="visually-hidden">Cari Pelanggan/Deskripsi</label>
                <div class="input-group input-group-sm">
                    <input type="search" id="search_report" name="search_report" class="form-control" placeholder="Cari pelanggan/deskripsi..." value="<?= htmlspecialchars($search_report ?? '') ?>">
                    <button class="btn btn-primary" type="submit" title="Cari"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <div class="col-lg-2 col-md-6 col-sm-6">
                <label for="report_status_filter" class="visually-hidden">Filter Status</label>
                <select id="report_status_filter" name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= ($report_status_filter ?? 'all') === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="Pending" <?= ($report_status_filter ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="In Progress" <?= ($report_status_filter ?? '') === 'In Progress' ? 'selected' : '' ?>>Dalam Proses</option>
                    <option value="Resolved" <?= ($report_status_filter ?? '') === 'Resolved' ? 'selected' : '' ?>>Selesai</option>
                    <option value="Cancelled" <?= ($report_status_filter ?? '') === 'Cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <label for="assigned_to_filter" class="visually-hidden">Ditugaskan Kepada</label>
                <select id="assigned_to_filter" name="assigned_to" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= ($assigned_to_filter ?? 'all') === 'all' ? 'selected' : '' ?>>Semua Teknisi</option>
                    <option value="" <?= ($assigned_to_filter ?? '') === '' ? 'selected' : '' ?>>Belum Ditugaskan</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?= htmlspecialchars($tech['username']) ?>" <?= ($assigned_to_filter ?? '') === $tech['username'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tech['full_name'] ?? $tech['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-6 col-sm-6 d-flex align-items-end">
                <?php if (!empty($search_report) || ($report_status_filter ?? 'all') !== 'all' || ($assigned_to_filter ?? 'all') !== 'all'): ?>
                    <a href="?page=laporan" class="btn btn-outline-secondary btn-sm w-100" title="Reset Filter"><i class="fas fa-times"></i> Reset</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary btn-sm w-100" title="Terapkan Filter"><i class="fas fa-filter"></i> Filter</button>
                <?php endif; ?>
            </div>
        </form>

        <!-- Tabel Laporan -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pelanggan</th>
                        <th>Deskripsi Gangguan</th>
                        <th>Status</th>
                        <th>Dilaporkan Oleh</th>
                        <th>Ditugaskan Ke</th>
                        <th>Tanggal Lapor</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">Tidak ada laporan gangguan untuk ditampilkan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($reports as $report):
                        // assigned_to disimpan sebagai JSON di DB
                        $assigned_to_users_array = json_decode($report['assigned_to'] ?? '[]', true);
                        
                        // Map usernames to full names for display
                        $display_assigned_to = [];
                        foreach ($assigned_to_users_array as $assigned_username) {
                            $found_tech_name = $assigned_username; // Default to username if full name not found
                            foreach ($technicians as $tech) {
                                if ($tech['username'] === $assigned_username) {
                                    $found_tech_name = $tech['full_name'] ?? $tech['username'];
                                    break;
                                }
                            }
                            $display_assigned_to[] = $found_tech_name;
                        }
                        $assigned_to_text_display = empty($display_assigned_to) ? 'Belum Ditugaskan' : implode(', ', $display_assigned_to);
                    ?>
                    <tr>
                        <td><?= $report['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($report['customer_username']) ?></td>
                        <td><?= htmlspecialchars(substr($report['issue_description'], 0, 50)) . (strlen($report['issue_description']) > 50 ? '...' : '') ?></td>
                        <td>
                            <?php 
                                $status = $report['report_status'];
                                $badge_class = 'secondary';
                                if ($status === 'Pending') $badge_class = 'warning';
                                elseif ($status === 'In Progress') $badge_class = 'info';
                                elseif ($status === 'Resolved') $badge_class = 'success';
                                elseif ($status === 'Cancelled') $badge_class = 'danger';
                            ?>
                            <span class="badge text-bg-<?= $badge_class ?>"><?= htmlspecialchars($status) ?></span>
                        </td>
                        <td><?= htmlspecialchars($report['reported_by']) ?></td>
                        <td>
                            <?php if (!empty($assigned_to_users_array)): ?>
                                <span class="badge text-bg-primary"><?= htmlspecialchars($assigned_to_text_display) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">Belum</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y H:i', strtotime($report['created_at'])) ?></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary view-report-btn"
                                        data-bs-toggle="modal" data-bs-target="#viewReportModal"
                                        data-id="<?= $report['id'] ?>"
                                        data-customer="<?= htmlspecialchars($report['customer_username']) ?>"
                                        data-description="<?= htmlspecialchars($report['issue_description']) ?>"
                                        data-status="<?= htmlspecialchars($report['report_status']) ?>"
                                        data-reported-by="<?= htmlspecialchars($report['reported_by']) ?>"
                                        data-assigned-to='<?= htmlspecialchars($report['assigned_to'] ?? '[]') ?>'
                                        data-created-at="<?= htmlspecialchars(date('d M Y H:i', strtotime($report['created_at']))) ?>"
                                        data-updated-at="<?= htmlspecialchars(date('d M Y H:i', strtotime($report['updated_at']))) ?>"
                                        data-resolved-at="<?= htmlspecialchars($report['resolved_at'] ? date('d M Y H:i', strtotime($report['resolved_at'])) : 'N/A') ?>"
                                        title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info edit-report-btn"
                                        data-bs-toggle="modal" data-bs-target="#editReportModal"
                                        data-id="<?= $report['id'] ?>"
                                        data-customer="<?= htmlspecialchars($report['customer_username']) ?>"
                                        data-description="<?= htmlspecialchars($report['issue_description']) ?>"
                                        data-status="<?= htmlspecialchars($report['report_status']) ?>"
                                        data-assigned-to='<?= htmlspecialchars($report['assigned_to'] ?? '[]') ?>'
                                        title="Edit Laporan">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('Anda yakin ingin menghapus laporan ini?')">
                                    <input type="hidden" name="action" value="delete_report">
                                    <input type="hidden" name="id" value="<?= $report['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Laporan"><i class="fas fa-trash"></i></button>
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

<!-- Modal Buat Laporan Baru -->
<div class="modal fade" id="addReportModal" tabindex="-1" aria-labelledby="addReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addReportModalLabel">Buat Laporan Gangguan Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_report">
                    <div class="mb-3">
                        <label for="customer_username" class="form-label">Username Pelanggan</label>
                        <select name="customer_username" id="customer_username" class="form-select" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            <?php foreach ($customer_usernames as $username): ?>
                                <option value="<?= htmlspecialchars($username) ?>"><?= htmlspecialchars($username) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="issue_description" class="form-label">Deskripsi Gangguan</label>
                        <textarea name="issue_description" id="issue_description" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tugaskan Kepada Teknisi (Opsional)</label>
                        <div class="form-check-group border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                            <?php if (empty($technicians)): ?>
                                <p class="text-muted small mb-0">Tidak ada teknisi terdaftar.</p>
                            <?php else: ?>
                                <?php foreach ($technicians as $tech): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="assigned_to[]" value="<?= htmlspecialchars($tech['username']) ?>" id="add_tech_<?= str_replace(' ', '_', $tech['username']) ?>">
                                        <label class="form-check-label" for="add_tech_<?= str_replace(' ', '_', $tech['username']) ?>">
                                            <?= htmlspecialchars($tech['full_name'] ?? $tech['username']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="form-text">Pilih satu atau lebih teknisi yang akan menangani laporan ini.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Laporan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Laporan -->
<div class="modal fade" id="editReportModal" tabindex="-1" aria-labelledby="editReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editReportModalLabel">Edit Laporan Gangguan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_report">
                    <input type="hidden" name="report_id" id="edit_report_id">
                    <div class="mb-3">
                        <label for="edit_customer_username" class="form-label">Username Pelanggan</label>
                        <input type="text" name="edit_customer_username" id="edit_customer_username" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_issue_description" class="form-label">Deskripsi Gangguan</label>
                        <textarea name="issue_description" id="edit_issue_description" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_report_status" class="form-label">Status Laporan</label>
                            <select name="report_status" id="edit_report_status" class="form-select">
                                <option value="Pending">Pending</option>
                                <option value="In Progress">Dalam Proses</option>
                                <option value="Resolved">Selesai</option>
                                <option value="Cancelled">Dibatalkan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tugaskan Kepada Teknisi</label>
                            <div class="form-check-group border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                                <?php if (empty($technicians)): ?>
                                    <p class="text-muted small mb-0">Tidak ada teknisi terdaftar.</p>
                                <?php else: ?>
                                    <?php foreach ($technicians as $tech): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="assigned_to[]" value="<?= htmlspecialchars($tech['username']) ?>" id="edit_tech_<?= str_replace(' ', '_', $tech['username']) ?>">
                                            <label class="form-check-label" for="edit_tech_<?= str_replace(' ', '_', $tech['username']) ?>">
                                                <?= htmlspecialchars($tech['full_name'] ?? $tech['username']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="form-text">Pilih satu atau lebih teknisi yang akan menangani laporan ini.</div>
                        </div>
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

<!-- Modal Lihat Detail Laporan -->
<div class="modal fade" id="viewReportModal" tabindex="-1" aria-labelledby="viewReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewReportModalLabel">Detail Laporan Gangguan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-4">ID Laporan:</dt>
                    <dd class="col-sm-8" id="view_report_id"></dd>

                    <dt class="col-sm-4">Username Pelanggan:</dt>
                    <dd class="col-sm-8" id="view_customer_username"></dd>

                    <dt class="col-sm-4">Deskripsi Gangguan:</dt>
                    <dd class="col-sm-8" id="view_issue_description"></dd>

                    <dt class="col-sm-4">Status:</dt>
                    <dd class="col-sm-8" id="view_report_status"></dd>

                    <dt class="col-sm-4">Dilaporkan Oleh:</dt>
                    <dd class="col-sm-8" id="view_reported_by"></dd>

                    <dt class="col-sm-4">Ditugaskan Kepada:</dt>
                    <dd class="col-sm-8" id="view_assigned_to"></dd>

                    <dt class="col-sm-4">Tanggal Lapor:</dt>
                    <dd class="col-sm-8" id="view_created_at"></dd>

                    <dt class="col-sm-4">Terakhir Diperbarui:</dt>
                    <dd class="col-sm-8" id="view_updated_at"></dd>

                    <dt class="col-sm-4">Tanggal Selesai:</dt>
                    <dd class="col-sm-8" id="view_resolved_at"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal Buat Laporan Baru
    const addReportModal = document.getElementById('addReportModal');
    if (addReportModal) {
        addReportModal.addEventListener('show.bs.modal', function (event) {
            // Reset form jika diperlukan
            this.querySelector('form').reset();
            // Pastikan semua checkbox teknisi tidak terpilih saat modal baru dibuka
            const checkboxes = this.querySelectorAll('input[name="assigned_to[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    }

    // Modal Edit Laporan
    const editReportModal = document.getElementById('editReportModal');
    if (editReportModal) {
        editReportModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const reportId = button.getAttribute('data-id');
            const customerUsername = button.getAttribute('data-customer');
            const description = button.getAttribute('data-description');
            const status = button.getAttribute('data-status');
            const assignedToJson = button.getAttribute('data-assigned-to'); // Get JSON string
            const assignedTo = JSON.parse(assignedToJson || '[]'); // Parse JSON, default to empty array

            editReportModal.querySelector('#edit_report_id').value = reportId;
            editReportModal.querySelector('#edit_customer_username').value = customerUsername;
            editReportModal.querySelector('#edit_issue_description').value = description;
            editReportModal.querySelector('#edit_report_status').value = status;
            
            // Set checkboxes for assigned technicians
            const checkboxes = editReportModal.querySelectorAll('input[name="assigned_to[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = assignedTo.includes(checkbox.value);
            });
        });
    }

    // Modal Lihat Detail Laporan
    const viewReportModal = document.getElementById('viewReportModal');
    if (viewReportModal) {
        viewReportModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const assignedToJson = button.getAttribute('data-assigned-to');
            const assignedTo = JSON.parse(assignedToJson || '[]');
            
            // Map usernames to full names for display in modal
            let assignedToDisplay = [];
            if (assignedTo.length > 0) {
                // In a real application, you would pass the technicians array to JS
                // or make an AJAX call to get full names. For now, just display usernames.
                // For this migration, the $technicians array is passed from index.php
                // so we can try to map it here.
                const allTechnicians = <?= json_encode($technicians ?? []) ?>; // Pass PHP array to JS
                assignedToDisplay = assignedTo.map(username => {
                    const tech = allTechnicians.find(t => t.username === username);
                    return tech ? (tech.full_name || tech.username) : username;
                });
            } else {
                assignedToDisplay.push('Belum Ditugaskan');
            }

            viewReportModal.querySelector('#view_report_id').innerText = button.getAttribute('data-id');
            viewReportModal.querySelector('#view_customer_username').innerText = button.getAttribute('data-customer');
            viewReportModal.querySelector('#view_issue_description').innerText = button.getAttribute('data-description');
            viewReportModal.querySelector('#view_report_status').innerText = button.getAttribute('data-status');
            viewReportModal.querySelector('#view_reported_by').innerText = button.getAttribute('data-reported-by');
            viewReportModal.querySelector('#view_assigned_to').innerText = assignedToDisplay.join(', '); // Join multiple names
            viewReportModal.querySelector('#view_created_at').innerText = button.getAttribute('data-created-at');
            viewReportModal.querySelector('#view_updated_at').innerText = button.getAttribute('data-updated-at');
            viewReportModal.querySelector('#view_resolved_at').innerText = button.getAttribute('data-resolved-at');
        });
    }
});
</script>