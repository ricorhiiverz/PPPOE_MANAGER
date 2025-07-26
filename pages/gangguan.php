<?php
// Pastikan hanya teknisi yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'teknisi') {
    echo '<div class="alert alert-danger">Akses ditolak. Halaman ini hanya untuk teknisi.</div>';
    return; // Hentikan eksekusi script jika bukan teknisi
}

// Data yang dibutuhkan akan diambil di index.php
// $reports
?>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0 text-white">Laporan Gangguan Saya</h5>
            <form action="" method="GET" class="row g-2 align-items-center flex-grow-1 justify-content-end">
                <input type="hidden" name="page" value="gangguan">
                <div class="col-lg-4 col-md-6 col-sm-12">
                    <label for="search_report" class="visually-hidden">Cari Pelanggan/Deskripsi</label>
                    <div class="input-group input-group-sm">
                        <input type="search" id="search_report" name="search_report" class="form-control" placeholder="Cari pelanggan/deskripsi..." value="<?= htmlspecialchars($search_report ?? '') ?>">
                        <button class="btn btn-primary" type="submit" title="Cari"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <label for="report_status_filter" class="visually-hidden">Filter Status</label>
                    <select id="report_status_filter" name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" <?= ($report_status_filter ?? 'all') === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="Pending" <?= ($report_status_filter ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="In Progress" <?= ($report_status_filter ?? '') === 'In Progress' ? 'selected' : '' ?>>Dalam Proses</option>
                        <option value="Resolved" <?= ($report_status_filter ?? '') === 'Resolved' ? 'selected' : '' ?>>Selesai</option>
                        <option value="Cancelled" <?= ($report_status_filter ?? '') === 'Cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 col-sm-6 d-flex align-items-end">
                    <?php if (!empty($search_report) || ($report_status_filter ?? 'all') !== 'all'): ?>
                        <a href="?page=gangguan" class="btn btn-outline-secondary btn-sm w-100" title="Reset Filter"><i class="fas fa-times"></i> Reset</a>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary btn-sm w-100" title="Terapkan Filter"><i class="fas fa-filter"></i> Filter</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="card-body">
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
                        <th>Tanggal Lapor</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">Tidak ada laporan gangguan untuk ditampilkan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($reports as $report): 
                        // Decode assigned_to from JSON string for display
                        $assigned_to_display = json_decode($report['assigned_to'] ?? '[]', true);
                        // For technicians page, we don't have $technicians array here to map full names.
                        // So, we'll display usernames for now.
                        $assigned_to_text_display = empty($assigned_to_display) ? 'Belum Ditugaskan' : implode(', ', $assigned_to_display);
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
                                <?php if ($report['report_status'] !== 'Resolved' && $report['report_status'] !== 'Cancelled'): ?>
                                <button type="button" class="btn btn-sm btn-outline-success update-status-btn"
                                        data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                                        data-id="<?= $report['id'] ?>"
                                        data-customer="<?= htmlspecialchars($report['customer_username']) ?>"
                                        data-status="<?= htmlspecialchars($report['report_status']) ?>"
                                        title="Perbarui Status">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Perbarui Status Laporan (Untuk Teknisi) -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel">Perbarui Status Laporan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_report_status">
                    <input type="hidden" name="report_id" id="update_report_id">
                    <p>Perbarui status laporan untuk pelanggan: <strong id="update_customer_username"></strong></p>
                    <div class="mb-3">
                        <label for="new_status" class="form-label">Status Baru</label>
                        <select name="new_status" id="new_status" class="form-select" required>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">Dalam Proses</option>
                            <option value="Resolved">Selesai</option>
                            <option value="Cancelled">Dibatalkan</option>
                        </select>
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

<!-- Modal Lihat Detail Laporan (Sama dengan Admin, bisa di-reuse atau duplikasi jika perlu perbedaan) -->
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
    // Modal Perbarui Status Laporan
    const updateStatusModal = document.getElementById('updateStatusModal');
    if (updateStatusModal) {
        updateStatusModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const reportId = button.getAttribute('data-id');
            const customerUsername = button.getAttribute('data-customer');
            const currentStatus = button.getAttribute('data-status');

            updateStatusModal.querySelector('#update_report_id').value = reportId;
            updateStatusModal.querySelector('#update_customer_username').innerText = customerUsername;
            updateStatusModal.querySelector('#new_status').value = currentStatus;
        });
    }

    // Modal Lihat Detail Laporan (Sama dengan di pages/laporan.php)
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
                // For this migration, the $technicians array is NOT available in this page's scope.
                // So, we'll just display usernames here.
                assignedToDisplay = assignedTo.map(username => username); 
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