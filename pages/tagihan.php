<?php
// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger">Akses ditolak. Halaman ini hanya untuk admin.</div>';
    return;
}

// Bangun query string untuk link ekspor berdasarkan filter saat ini
$export_query_params = http_build_query([
    'action' => 'export_invoices',
    'search_user' => $search_user ?? '',
    'filter_month' => $filter_month ?? '',
    'filter_status' => $filter_status ?? 'all',
    'filter_by_user' => $filter_by_user ?? 'all'
]);

?>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0 text-white">Manajemen Tagihan</h5>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <form action="" method="POST" class="mb-0" onsubmit="return confirm('Ini akan membuat tagihan untuk semua pelanggan aktif yang belum memiliki tagihan di bulan ini. Lanjutkan?')">
                    <input type="hidden" name="action" value="generate_invoices">
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-file-invoice-dollar me-2"></i>Generate Tagihan Bulan Ini</button>
                </form>
                <?php endif; ?>
                <a href="index.php?<?= $export_query_params ?>" class="btn btn-info btn-sm">
                    <i class="fas fa-file-csv me-2"></i>Ekspor ke CSV
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form action="" method="GET" class="row g-2 align-items-center mb-4">
            <input type="hidden" name="page" value="tagihan">
            <div class="col-lg-3 col-md-6 col-sm-12">
                <label for="search_user" class="visually-hidden">Cari Username</label>
                <div class="input-group input-group-sm">
                    <input type="search" id="search_user" name="search_user" class="form-control" placeholder="Cari username..." value="<?= htmlspecialchars($search_user ?? '') ?>">
                    <button class="btn btn-primary" type="submit" title="Cari"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <div class="col-lg-2 col-md-6 col-sm-6">
                <label for="filter_month" class="visually-hidden">Filter Bulan</label>
                <input type="month" id="filter_month" name="filter_month" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_month ?? '') ?>" onchange="this.form.submit()">
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label for="filter_status" class="visually-hidden">Status</label>
                <select id="filter_status" name="filter_status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= ($filter_status ?? 'all') === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="Belum Lunas" <?= ($filter_status ?? '') === 'Belum Lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                    <option value="Lunas" <?= ($filter_status ?? '') === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label for="filter_by_user" class="visually-hidden">Dikonfirmasi Oleh</label>
                <select id="filter_by_user" name="filter_by_user" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all">Semua User</option>
                    <?php foreach ($confirmation_users as $user): ?>
                        <option value="<?= htmlspecialchars($user) ?>" <?= ($filter_by_user ?? '') === $user ? 'selected' : '' ?>><?= htmlspecialchars($user) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-1 col-md-4 col-sm-6 d-flex align-items-end">
                <?php if (!empty($search_user) || !empty($filter_month) || ($filter_status ?? 'all') !== 'all' || ($filter_by_user ?? 'all') !== 'all'): ?>
                    <a href="?page=tagihan" class="btn btn-outline-secondary btn-sm w-100" title="Reset Filter"><i class="fas fa-times"></i> Reset</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary btn-sm w-100" title="Terapkan Filter"><i class="fas fa-filter"></i> Filter</button>
                <?php endif; ?>
            </div>
        </form>

        <!-- Ringkasan Tagihan -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title">Total Lunas (Sesuai Filter)</h6>
                        <p class="card-text fs-4 fw-bold">Rp <?= number_format($total_lunas ?? 0, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h6 class="card-title">Total Belum Lunas (Sesuai Filter)</h6>
                        <p class="card-text fs-4 fw-bold">Rp <?= number_format($total_belum_lunas ?? 0, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Tagihan -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Bulan</th>
                        <th>Jumlah</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                        <th>VIA</th>
                        <th>Waktu Bayar</th>
                        <th>Dikonfirmasi Oleh</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                Tidak ada data tagihan untuk ditampilkan.
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <br>Pastikan Anda sudah menekan tombol "Generate Tagihan Bulan Ini" di atas, dan filter sudah benar.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($invoice['username']) ?></td>
                        <td><?= date('F Y', strtotime($invoice['billing_month'] . '-01')) ?></td>
                        <td><?= number_format($invoice['amount'], 0, ',', '.') ?></td>
                        <td><?= date('d F Y', strtotime($invoice['due_date'])) ?></td>
                        <td>
                            <?php if ($invoice['status'] == 'Lunas'): ?>
                                <span class="badge text-bg-success">Lunas</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning">Belum Lunas</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                                $method = $invoice['payment_method'];
                                $badge_class = 'secondary';
                                if ($method === 'Cash') $badge_class = 'primary';
                                if ($method === 'Online') $badge_class = 'info';
                            ?>
                            <span class="badge text-bg-<?= $badge_class ?>"><?= htmlspecialchars($method ?? 'N/A') ?></span>
                        </td>
                        <td><?= $invoice['paid_date'] ? date('d M Y, H:i', strtotime($invoice['paid_date'])) : 'N/A' ?></td>
                        <td><span class="badge text-bg-dark"><?= htmlspecialchars($invoice['updated_by'] ?? 'N/A') ?></span></td>
                        <td class="text-center">
                            <?php if ($invoice['status'] == 'Belum Lunas'): ?>
                                <?php if (in_array($_SESSION['role'], ['admin', 'penagih'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-success mark-paid-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#paymentModal"
                                        data-invoice-id="<?= $invoice['id'] ?>"
                                        data-username="<?= htmlspecialchars($invoice['username']) ?>"
                                        title="Tandai Lunas">
                                    <i class="fas fa-check"></i>
                                </button>
                                <?php endif; ?>
                            <?php else: // Status is 'Lunas' ?>
                                <?php if ($_SESSION['role'] === 'admin' && $invoice['payment_method'] === 'Cash'): ?>
                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('Anda yakin ingin MEMBATALKAN pembayaran untuk <?= htmlspecialchars($invoice['username']) ?>?')">
                                    <input type="hidden" name="action" value="cancel_payment">
                                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Batalkan Pembayaran"><i class="fas fa-times"></i></button>
                                </form>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Pembayaran -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentModalLabel">Konfirmasi Pembayaran</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="" method="POST">
        <div class="modal-body">
            <input type="hidden" name="action" value="mark_as_paid">
            <input type="hidden" name="invoice_id" id="modal_invoice_id">
            <p>Tandai tagihan untuk <strong id="modal_username"></strong> sebagai lunas?</p>
            <div class="mb-3">
                <label for="payment_method" class="form-label">Pilih Metode Pembayaran:</label>
                <select name="payment_method" id="payment_method" class="form-select" required>
                    <option value="Cash" selected>Cash</option>
                    <option value="Online">Online/Transfer</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Konfirmasi Pembayaran</button>
        </div>
      </form>
    </div>
  </div>
</div>