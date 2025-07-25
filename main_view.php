<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard PPPoE Modern</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-body-font-family: 'Roboto', sans-serif;
            --sidebar-width: 260px;
            --bs-primary-rgb: 78, 115, 223;
            --bs-dark-rgb: 28, 32, 37;
        }
        body { background-color: #16191c; color: #d1d2d3; overflow-x: hidden; }
        .wrapper { display: flex; width: 100%; align-items: stretch; }
        #sidebar { min-width: var(--sidebar-width); max-width: var(--sidebar-width); background: #1c2025; transition: all 0.3s; min-height: 100vh; border-right: 1px solid #2a2e34; position: fixed; top: 0; left: 0; z-index: 1045; }
        #sidebar.active { margin-left: calc(-1 * var(--sidebar-width)); } /* Hidden state for sidebar */
        #sidebar .sidebar-header { padding: 20px; background: rgba(var(--bs-dark-rgb), 0.5); border-bottom: 1px solid #2a2e34; }
        #sidebar ul li a { padding: 12px 20px; font-size: 1rem; display: block; color: #a7aeb8; border-left: 4px solid transparent; transition: all 0.2s ease-in-out; }
        #sidebar ul li a:hover { color: #fff; background: rgba(var(--bs-primary-rgb), 0.1); border-left-color: rgba(var(--bs-primary-rgb), 0.5); }
        #sidebar ul li.active > a { color: #fff; background: rgba(var(--bs-primary-rgb), 0.2); border-left-color: rgb(var(--bs-primary-rgb)); }
        #content { width: 100%; padding: 1.5rem; min-height: 100vh; transition: all 0.3s; margin-left: var(--sidebar-width); } /* Adjust content margin for sidebar */
        
        /* Responsive adjustments for content area when sidebar is active/hidden */
        @media (max-width: 768px) {
            #sidebar { margin-left: calc(-1 * var(--sidebar-width)); } /* Default hidden on mobile */
            #sidebar.active { margin-left: 0; } /* Show sidebar on mobile when active */
            #content { margin-left: 0; } /* No margin on mobile */
            .wrapper { display: block; } /* Stack elements on mobile */
            .navbar-light { margin-left: 0 !important; } /* Remove margin from navbar on mobile */
        }

        .card { background-color: #212529; border: 1px solid #2a2e34; }
        .card-summary { cursor: pointer; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .card-summary:hover { transform: translateY(-5px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,.35)!important; }
        .card .text-muted { color: #ffffff !important; } /* Changed to white */
        .card .fw-bold { color: #ffffff; }
        .mb-4 { margin-bottom: 0.6rem !important; } /* Penyesuaian jarak */
        .table { --bs-table-bg: #212529; --bs-table-color: #d1d2d3; --bs-table-border-color: #2a2e34; --bs-table-striped-bg: #2c3034; --bs-table-hover-bg: #32383e; }
        .modal-content { background-color: #212529; border: 1px solid #2a2e34; }
        .form-control, .form-select { background-color: #2c3034; border-color: #3e444a; color: #fff; }
        .form-control:focus, .form-select:focus { background-color: #2c3034; border-color: rgb(var(--bs-primary-rgb)); color: #fff; box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25); }
        .pagination { --bs-pagination-bg: #2c3034; --bs-pagination-border-color: #3e444a; --bs-pagination-color: #d1d2d3; --bs-pagination-hover-bg: #32383e; --bs-pagination-hover-color: #fff; --bs-pagination-active-bg: rgb(var(--bs-primary-rgb)); --bs-pagination-active-border-color: rgb(var(--bs-primary-rgb)); --bs-pagination-disabled-bg: #212529; --bs-pagination-disabled-color: #6c757d; }
        .navbar-light { background: rgba(33, 37, 41, 0.7) !important; backdrop-filter: blur(10px); border: 1px solid #2a2e34; }
        
        /* Specific adjustments for detail user modal to ensure readability on small screens */
        #detailUserModal .dl-horizontal dt { 
            float: none; /* Remove float for better stacking on small screens */
            width: auto; 
            text-align: left; /* Align text to left */
            white-space: normal; /* Allow text to wrap */
            margin-bottom: 5px; /* Add some space below dt */
            font-weight: bold; /* Make dt bold */
            color: #a7aeb8; /* Slightly lighter color for labels */
        }
        #detailUserModal .dl-horizontal dd { 
            margin-left: 0; /* Remove left margin */
            margin-bottom: 15px; /* Add more space below dd */
        }

        /* General font size adjustments for smaller screens */
        @media (max-width: 576px) {
            body { font-size: 0.9rem; }
            .h1, .h2, .h3, .h4, .h5, .h6, h1, h2, h3, h4, h5, h6 { font-size: 1.2rem; }
            .fs-4 { font-size: 1.2rem !important; }
            .btn { font-size: 0.85rem; padding: 0.4rem 0.8rem; }
            .form-control, .form-select { font-size: 0.85rem; padding: 0.4rem 0.8rem; }
            .table th, .table td { font-size: 0.85rem; }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header d-flex justify-content-between align-items-center">
            <h5 class="text-white mb-0"><i class="fas fa-network-wired me-2"></i>PPPoE Panel</h5>
            <button type="button" id="sidebarCollapseClose" class="btn btn-dark d-md-none"><i class="fas fa-times"></i></button>
        </div>
        <ul class="list-unstyled components">
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="<?= $page === 'dashboard' ? 'active' : '' ?>"><a href="index.php?page=dashboard"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a></li>
            <?php elseif ($_SESSION['role'] === 'penagih'): ?>
            <li class="<?= $page === 'penagih_dashboard' ? 'active' : '' ?>"><a href="index.php?page=penagih_dashboard"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard Penagih</a></li>
            <?php elseif ($_SESSION['role'] === 'teknisi'): ?>
            <li class="<?= $page === 'teknisi_dashboard' ? 'active' : '' ?>"><a href="index.php?page=teknisi_dashboard"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard Teknisi</a></li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'penagih'): ?>
            <li class="<?= $page === 'pelanggan' ? 'active' : '' ?>"><a href="index.php?page=pelanggan"><i class="fas fa-users fa-fw me-2"></i>Pelanggan</a></li>
            <?php endif; ?>
            
            <?php if (in_array($_SESSION['role'], ['admin', 'penagih'])): ?>
            <li class="<?= $page === 'peta' ? 'active' : '' ?>"><a href="index.php?page=peta"><i class="fas fa-map-location-dot fa-fw me-2"></i>Peta Pelanggan</a></li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="<?= $page === 'tagihan' ? 'active' : '' ?>"><a href="index.php?page=tagihan"><i class="fas fa-file-invoice-dollar fa-fw me-2"></i>Manajemen Tagihan</a></li>
            <?php elseif ($_SESSION['role'] === 'penagih'): ?>
            <li class="<?= $page === 'penagih_tagihan' ? 'active' : '' ?>"><a href="index.php?page=penagih_tagihan"><i class="fas fa-file-invoice-dollar fa-fw me-2"></i>Manajemen Tagihan Saya</a></li>
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'], ['admin'])): ?>
            <li class="<?= $page === 'profil' ? 'active' : '' ?>"><a href="index.php?page=profil"><i class="fas fa-file-alt fa-fw me-2"></i>Manajemen Profil</a></li>
            <li class="<?= $page === 'wilayah' ? 'active' : '' ?>"><a href="index.php?page=wilayah"><i class="fas fa-map-marked-alt fa-fw me-2"></i>Manajemen Wilayah</a></li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="<?= $page === 'laporan' ? 'active' : '' ?>"><a href="index.php?page=laporan"><i class="fas fa-clipboard-list fa-fw me-2"></i>Manajemen Laporan</a></li>
            <?php elseif ($_SESSION['role'] === 'teknisi'): ?>
            <li class="<?= $page === 'gangguan' ? 'active' : '' ?>"><a href="index.php?page=gangguan"><i class="fas fa-tools fa-fw me-2"></i>Laporan Gangguan Saya</a></li>
            <?php endif; ?>

            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="<?= $page === 'user' ? 'active' : '' ?>"><a href="index.php?page=user"><i class="fas fa-users-cog fa-fw me-2"></i>Manajemen User</a></li>
            <li class="<?= $page === 'pengaturan' ? 'active' : '' ?>"><a href="index.php?page=pengaturan"><i class="fas fa-cog fa-fw me-2"></i>Pengaturan</a></li>
            <?php endif; ?>
        </ul>
        <ul class="list-unstyled CTAs mt-auto">
            <li class="px-3 py-4"><a href="index.php?logout=1" class="btn btn-outline-danger w-100">Logout</a></li>
            <li class="text-center text-muted small">Login sebagai: <?= htmlspecialchars($_SESSION['full_name']) ?> (<?= ucfirst($_SESSION['role']) ?>)</li>
        </ul>
    </nav>

    <!-- Page Content -->
    <div id="content">
        <!-- Header -->
        <nav class="navbar navbar-expand-lg navbar-light rounded mb-4 shadow-sm">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-dark d-md-none"><i class="fas fa-bars"></i></button> <!-- Show only on mobile -->
                <h5 class="navbar-brand mb-0 h1 text-white ms-2">
                    <?= ucfirst(str_replace('_', ' ', $page)) ?>
                </h5>
                <!-- Add a spacer div to push content to the right on larger screens -->
                <div class="d-none d-md-block flex-grow-1"></div> 
                <!-- Display username on larger screens -->
                <span class="navbar-text text-muted d-none d-md-block">
                    Login sebagai: <?= htmlspecialchars($_SESSION['full_name']) ?> (<?= ucfirst($_SESSION['role']) ?>)
                </span>
            </div>
        </nav>

        <!-- Notifikasi Toast -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
            <div id="notificationToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header"><i id="toast-icon" class="fas me-2"></i><strong class="me-auto" id="toast-title">Notifikasi</strong><button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button></div>
                <div class="toast-body" id="toast-message"></div>
            </div>
        </div>

        <?php if (!$connection_status && empty($message)): ?>
            <div class="alert alert-danger text-center" role="alert"><h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Koneksi Gagal!</h4><p>Tidak dapat terhubung ke router. Periksa kembali file `config.php`, koneksi jaringan, dan pastikan service API di MikroTik sudah aktif.</p></div>
        <?php else: 
            $page_file = 'pages/' . $page . '.php';
            if (file_exists($page_file)) {
                include $page_file;
            } else {
                echo '<div class="alert alert-warning">Halaman tidak ditemukan.</div>';
            }
        endif; ?>

    </div> <!-- End #content -->
</div> <!-- End .wrapper -->

<!-- Modals -->
<?php if (in_array($_SESSION['role'], ['admin', 'teknisi'])): ?>
<button class="btn btn-primary btn-lg rounded-circle position-fixed" style="bottom: 20px; right: 20px; z-index: 1030;" data-bs-toggle="modal" data-bs-target="#addUserModal" title="Tambah Pelanggan Baru"><i class="fas fa-plus"></i></button>
<?php endif; ?>
<!-- Modal Tambah User -->
<div class="modal fade" id="addUserModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Tambah Pelanggan Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form action="" method="POST">
        <input type="hidden" name="action" value="add_user">
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Username</label><input type="text" name="user" required class="form-control"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Password</label><input type="text" name="password" required class="form-control"></div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Service</label><select name="service" class="form-select"><option value="pppoe">pppoe</option><option value="any">any</option></select></div>
            <div class="col-md-6 mb-3"><label class="form-label">Profil</label><select name="profile" required class="form-select"><?php if(isset($profiles)) foreach ($profiles as $profile): ?><option value="<?= htmlspecialchars($profile['name']) ?>"><?= htmlspecialchars($profile['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <hr class="my-4">
        <h6 class="mb-3">Informasi Tambahan (Opsional)</h6>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Lokasi (Koordinat)</label>
                <div class="input-group">
                    <input type="text" name="koordinat" id="add-koordinat-input" class="form-control" placeholder="-6.123, 106.456">
                    <button class="btn btn-outline-secondary" type="button" id="get-location-btn-add" title="Ambil Lokasi Saat Ini"><i class="fas fa-location-crosshairs"></i></button>
                </div>
            </div>
            <div class="col-md-6 mb-3"><label class="form-label">Nama Wilayah/Area</label>
                <select name="wilayah" class="form-select">
                    <option value="">-- Pilih Wilayah --</option>
                    <?php if(isset($wilayah_list)) foreach ($wilayah_list as $wilayah): ?><option value="<?= htmlspecialchars($wilayah) ?>"><?= htmlspecialchars($wilayah) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Nomor WhatsApp</label><input type="tel" name="whatsapp" class="form-control" placeholder="08123456789"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Tgl Registrasi</label><input type="date" name="registrasi" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="mb-3"><label class="form-label">Tgl Tagihan (1-31)</label><input type="number" name="tgl_tagihan" class="form-control" placeholder="Contoh: 24" min="1" max="31"></div>
        <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
    </form></div>
</div></div></div>
<!-- Modal Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editUserModalLabel">Edit Pelanggan</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div id="edit-form-container">
            <div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>
        </div>
    </div>
</div></div></div>
<!-- Modal Detail User -->
<div class="modal fade" id="detailUserModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="detailUserModalLabel">Detail Pelanggan</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="detail-content"><div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div></div>
</div></div></div>
<!-- Modal Tambah Profil -->
<div class="modal fade" id="addProfileModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Tambah Profil Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form action="" method="POST"><input type="hidden" name="action" value="add_profile">
        <div class="mb-3"><label class="form-label">Nama Profil</label><input type="text" name="profile_name" required class="form-control"></div>
        <div class="mb-3"><label class="form-label">Rate Limit (Upload/Download)</label><input type="text" name="rate_limit" class="form-control" placeholder="Contoh: 5M/10M"><div class="form-text">Kosongkan jika tidak ada batas kecepatan.</div></div>
        <div class="mb-3"><label class="form-label">Tagihan (Rp)</label><input type="number" name="tagihan" class="form-control" placeholder="150000"></div>
        <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Profil</button></div>
    </form></div>
</div></div></div>
<!-- Modal Edit Profil -->
<div class="modal fade" id="editProfileModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editProfileModalLabel">Edit Profil</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form action="" method="POST"><input type="hidden" name="action" value="edit_profile"><input type="hidden" name="edit_profile_id" id="edit-profile-id">
        <div class="mb-3"><label class="form-label">Nama Profil</label><input type="text" name="edit_profile_name" id="edit-profile-name" required class="form-control" readonly></div>
        <div class="mb-3"><label class="form-label">Rate Limit (Upload/Download)</label><input type="text" name="edit_rate_limit" id="edit-rate-limit" class="form-control" placeholder="Contoh: 5M/10M"></div>
        <div class="mb-3"><label class="form-label">Tagihan (Rp)</label><input type="number" name="edit_tagihan" id="edit-tagihan" class="form-control" placeholder="150000"></div>
        <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
    </form></div>
</div></div></div>
<!-- Modal Tambah User Aplikasi -->
<div class="modal fade" id="addAppUserModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Tambah Pengguna Aplikasi Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form action="" method="POST">
            <input type="hidden" name="action" value="add_app_user">
            <div class="mb-3"><label class="form-label">Nama Lengkap</label><input type="text" name="app_full_name" required class="form-control"></div>
            <div class="mb-3"><label class="form-label">Username (untuk login)</label><input type="text" name="app_username" required class="form-control"></div>
            <div class="mb-3"><label class="form-label">Password</label><input type="password" name="app_password" required class="form-control"></div>
            <div class="mb-3"><label class="form-label">Role</label>
                <select name="app_role" class="form-select">
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
    </form></div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const sidebarCollapse = document.getElementById('sidebarCollapse');
        const sidebarCollapseClose = document.getElementById('sidebarCollapseClose');
        
        // Toggle sidebar on button click
        if (sidebarCollapse) { 
            sidebarCollapse.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            }); 
        }
        // Close sidebar on close button click (for mobile)
        if (sidebarCollapseClose) { 
            sidebar.classList.remove('active');
        }

        // Close sidebar when clicking outside on mobile
        document.getElementById('content').addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active') && !sidebar.contains(event.target) && event.target !== sidebarCollapse) {
                sidebar.classList.remove('active');
            }
        });

        const editUserModal = document.getElementById('editUserModal');
        if(editUserModal) {
            editUserModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-id');
                const formContainer = document.getElementById('edit-form-container');
                formContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

                fetch(`index.php?action=get_user_details&id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            formContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                            return;
                        }
                        
                        let profileOptions = '';
                        <?php if(isset($profiles)) foreach ($profiles as $profile): ?>
                            profileOptions += `<option value="<?= htmlspecialchars($profile['name']) ?>" ${data.profile === '<?= htmlspecialchars($profile['name']) ?>' ? 'selected' : ''}>
                                <?= htmlspecialchars($profile['name']) ?>
                            </option>`;
                        <?php endforeach; ?>
                        
                        let wilayahOptions = '<option value="">-- Pilih Wilayah --</option>';
                        <?php if(isset($wilayah_list)) foreach ($wilayah_list as $wilayah): ?>
                             wilayahOptions += `<option value="<?= htmlspecialchars($wilayah) ?>" ${data.wilayah === '<?= htmlspecialchars($wilayah) ?>' ? 'selected' : ''}>
                                <?= htmlspecialchars($wilayah) ?>
                            </option>`;
                        <?php endforeach; ?>

                        formContainer.innerHTML = `
                            <form action="" method="POST">
                                <input type="hidden" name="action" value="edit_user">
                                <input type="hidden" name="edit_id" value="${data['.id']}">
                                <div class="mb-3"><label class="form-label">Username</label><input type="text" value="${data.name}" required class="form-control" readonly></div>
                                <div class="mb-3"><label class="form-label">Password Baru (Opsional)</label><input type="text" name="edit_password" class="form-control" placeholder="Kosongkan jika tidak ingin diubah"></div>
                                <div class="mb-3"><label class="form-label">Profil</label><select name="edit_profile" required class="form-select">${profileOptions}</select></div>
                                <hr class="my-4">
                                <h6 class="mb-3">Informasi Tambahan</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Lokasi (Koordinat)</label>
                                        <div class="input-group">
                                            <input type="text" name="edit_koordinat" id="edit-koordinat-input" class="form-control" value="${data.koordinat || ''}">
                                            <button class="btn btn-outline-secondary" type="button" id="get-location-btn-edit" title="Ambil Lokasi Saat Ini"><i class="fas fa-location-crosshairs"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3"><label class="form-label">Nama Wilayah/Area</label><select name="edit_wilayah" class="form-select">${wilayahOptions}</select></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">Nomor WhatsApp</label><input type="tel" name="edit_whatsapp" class="form-control" value="${data.whatsapp || ''}"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">Tgl Registrasi</label><input type="date" name="edit_registrasi" class="form-control" value="${data.registrasi || ''}"></div>
                                </div>
                                <div class="mb-3"><label class="form-label">Tgl Tagihan (1-31)</label><input type="number" name="edit_tgl_tagihan" class="form-control" value="${data.tgl_tagihan || ''}" min="1" max="31"></div>
                                <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
                            </form>
                        `;

                        document.getElementById('get-location-btn-edit').addEventListener('click', function() { getLocation('edit-koordinat-input', this); });
                    });
            });
        }
        
        const addUserModal = document.getElementById('addUserModal');
        if(addUserModal) {
            document.getElementById('get-location-btn-add').addEventListener('click', function() { getLocation('add-koordinat-input', this); });
        }

        const editProfileModal = document.getElementById('editProfileModal');
        if(editProfileModal) {
            editProfileModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                editProfileModal.querySelector('#edit-profile-id').value = button.getAttribute('data-id');
                editProfileModal.querySelector('#edit-profile-name').value = button.getAttribute('data-name');
                editProfileModal.querySelector('#edit-rate-limit').value = button.getAttribute('data-rate-limit');
                editProfileModal.querySelector('#edit-tagihan').value = button.getAttribute('data-tagihan');
            });
        }
        const detailUserModal = document.getElementById('detailUserModal');
        if(detailUserModal) {
            detailUserModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget; const userId = button.getAttribute('data-id');
                const detailContent = document.getElementById('detail-content');
                detailContent.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                fetch(`index.php?action=get_user_details&id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) { detailContent.innerHTML = `<div class="alert alert-danger">${data.error}</div>`; return; }
                        let statusBadge = (data.disabled === 'true') ? '<span class="badge text-bg-danger">Disabled</span>' : (data['status-online'] ? '<span class="badge text-bg-success">Online</span>' : '<span class="badge text-bg-secondary">Offline</span>');
                        let onlineInfo = data['status-online'] ? `<dt>Alamat IP</dt><dd>${data.address || 'N/A'}</dd><dt>Uptime</dt><dd>${data.uptime || 'N/A'}</dd>` : '';
                        
                        let koordinatHTML = `<dd>${data.koordinat || 'N/A'}</dd>`;
                        if (data.koordinat && data.koordinat.trim() !== '' && data.koordinat !== 'N/A') {
                            const coords = encodeURIComponent(data.koordinat.trim());
                            koordinatHTML = `<dd>${data.koordinat} <a href="https://www.google.com/maps?q=${coords}&t=k" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="fas fa-map-location-dot me-1"></i> Lihat Peta</a></dd>`;
                        }

                        let commentInfo = `
                            <hr>
                            <dt>Koordinat</dt>${koordinatHTML}
                            <dt>Wilayah</dt><dd>${data.wilayah || 'N/A'}</dd>
                            <dt>No. WhatsApp</dt><dd>${data.whatsapp || 'N/A'}</dd>
                            <dt>Tgl Registrasi</dt><dd>${data.registrasi || 'N/A'}</dd>
                            <dt>Tgl Tagihan</dt><dd>Setiap tanggal ${data.tgl_tagihan || 'N/A'}</dd>
                            <dt>Tagihan Profil</dt><dd>Rp ${data.tagihan ? new Intl.NumberFormat('id-ID').format(data.tagihan) : 'N/A'}</dd>
                        `;
                        detailContent.innerHTML = `<dl class="dl-horizontal"><dt>Username</dt><dd class="fw-bold">${data.name || 'N/A'}</dd><dt>Status</dt><dd>${statusBadge}</dd><dt>Profil</dt><dd>${data.profile || 'N/A'}</dd><dt>Service</dt><dd>${data.service || 'N/A'}</dd>${onlineInfo}<dt>Login Terakhir Keluar</dt><dd>${data['last-logged-out'] || 'N/A'}</dd>${commentInfo}</dl>`;
                    })
                    .catch(error => { detailContent.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan.</div>`; console.error('Error:', error); });
            });
        }

        // Handle Payment Confirmation Modal
        const paymentModal = document.getElementById('paymentModal');
        if (paymentModal) {
            paymentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const invoiceId = button.getAttribute('data-invoice-id');
                const username = button.getAttribute('data-username');

                const modalInvoiceIdInput = paymentModal.querySelector('#modal_invoice_id');
                const modalUsernameSpan = paymentModal.querySelector('#modal_username');

                modalInvoiceIdInput.value = invoiceId;
                modalUsernameSpan.textContent = username;
            });
        }

        const trafficCanvas = document.getElementById('trafficChart');
        if (trafficCanvas) {
            const ctx = trafficCanvas.getContext('2d');
            const trafficChart = new Chart(ctx, { type: 'line', data: { labels: [], datasets: [{ label: 'Download', data: [], borderColor: 'rgba(78, 115, 223, 1)', backgroundColor: 'rgba(78, 115, 223, 0.1)', fill: true, tension: 0.4 }, { label: 'Upload', data: [], borderColor: 'rgba(28, 200, 138, 1)', backgroundColor: 'rgba(28, 200, 138, 0.1)', fill: true, tension: 0.4 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { color: '#adb5bd', callback: (value) => formatBytes(value) } }, x: { ticks: { color: '#adb5bd' } } }, plugins: { legend: { labels: { color: '#d1d2d3' } } }, animation: { duration: 1000 } } });
            function formatBytes(bytes, decimals = 2) { if (bytes === 0) return '0 Bps'; const k = 1000; const dm = decimals < 0 ? 0 : decimals; const sizes = ['Bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps']; const i = Math.floor(Math.log(bytes) / Math.log(k)); return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]; }
            function updateTrafficData() {
                fetch('index.php?action=get_traffic')
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) { console.error('Traffic Error:', data.error); return; }
                        const now = new Date(); const timeLabel = now.getHours() + ':' + ('0' + now.getMinutes()).slice(-2) + ':' + ('0' + now.getSeconds()).slice(-2);
                        trafficChart.data.labels.push(timeLabel);
                        trafficChart.data.datasets[0].data.push(data.download);
                        trafficChart.data.datasets[1].data.push(data.upload);
                        if (trafficChart.data.labels.length > 15) { trafficChart.data.labels.shift(); trafficChart.data.datasets.forEach(dataset => dataset.data.shift()); }
                        trafficChart.update();
                    });
            }
            setInterval(updateTrafficData, 3000);
        }
        <?php if ($message): ?>
        showToast("<?= addslashes($message['text']) ?>", "<?= $message['type'] ?>");
        <?php endif; ?>
    });

    function getLocation(inputId, button) {
        if (navigator.geolocation) {
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            button.disabled = true;
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    document.getElementById(inputId).value = `${position.coords.latitude}, ${position.coords.longitude}`;
                    showToast('Lokasi berhasil didapatkan.', 'success');
                    button.innerHTML = '<i class="fas fa-location-crosshairs"></i>';
                    button.disabled = false;
                },
                (error) => {
                    showToast('Gagal mendapatkan lokasi. Pastikan izin lokasi diberikan.', 'error');
                    button.innerHTML = '<i class="fas fa-location-crosshairs"></i>';
                    button.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        } else {
            showToast('Geolocation tidak didukung oleh browser ini.', 'error');
        }
    }

    function filterData(filterType) { window.location.href = 'index.php?page=dashboard&filter=' + filterType; }
    function showToast(message, type = 'success') {
        const toastEl = document.getElementById('notificationToast');
        const toast = new bootstrap.Toast(toastEl);
        const header = toastEl.querySelector('.toast-header');
        toastEl.querySelector('#toast-message').innerText = message;
        header.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-white');
        toastEl.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-white');
        
        let iconClass = 'fas fa-check-circle me-2';
        let title = 'Sukses';
        let bgClass = 'bg-success';

        if (type === 'error') {
            iconClass = 'fas fa-times-circle me-2';
            title = 'Error';
            bgClass = 'bg-danger';
        } else if (type === 'warning') {
            iconClass = 'fas fa-exclamation-triangle me-2';
            title = 'Peringatan';
            bgClass = 'bg-warning';
        }
        
        header.classList.add(bgClass, 'text-white'); 
        toastEl.classList.add(bgClass, 'text-white');
        header.querySelector('#toast-icon').className = iconClass; 
        header.querySelector('#toast-title').innerText = title;
        toast.show();
    }
</script>
</body>
</html>