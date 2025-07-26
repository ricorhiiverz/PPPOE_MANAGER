<?php
// Sertakan file konfigurasi, class API, dan fungsi database
require_once('config.php'); // Menggunakan require_once untuk memastikan $app_settings, sendWhatsAppMessage, dan parse_comment_for_wa tersedia
require('RouterosAPI.php');

// Pastikan zona waktu adalah WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

function connect_db() {
    $db_file = 'users.db';
    try {
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // Jangan tampilkan error ke publik, cukup catat di log server
        error_log("Koneksi database gagal: ". $e->getMessage());
        return null;
    }
}

// Fungsi untuk menampilkan halaman error yang user-friendly
function display_error($message) {
    die('
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error Pembayaran</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background-color: #16191c; color: #d1d2d3; }
                .card { background-color: #212529; border: 1px solid #2a2e34; }
            </style>
        </head>
        <body>
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card text-center">
                            <div class="card-header bg-danger text-white">
                                <h4>Terjadi Kesalahan</h4>
                            </div>
                            <div class="card-body">
                                <p>' . htmlspecialchars($message) . '</p>
                                <a href="cek_tagihan.php" class="btn btn-primary">Kembali ke Cek Tagihan</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
    ');
}

$invoices = []; // Inisialisasi array invoices
$error_message = '';
$username = ''; // Inisialisasi username
$payment_channels = []; // Inisialisasi array untuk channel pembayaran

// Ambil daftar channel pembayaran dari Tripay
$payment_channels = getTripayPaymentChannels();
// Filter channel yang aktif dan memiliki group 'E-Wallet' atau 'Virtual Account' atau 'Retail Outlet'
$filtered_payment_channels = [];
foreach ($payment_channels as $channel) {
    if ($channel['active'] && in_array($channel['group'], ['E-Wallet', 'Virtual Account', 'Retail Outlet', 'QRIS'])) {
        $filtered_payment_channels[] = $channel;
    }
}


// --- PERBAIKAN: Tangani request GET dengan parameter username 'u' ---
if (isset($_GET['u']) && !empty($_GET['u'])) {
    $username = trim($_GET['u']);
    try {
        $pdo = connect_db();
        if ($pdo) {
            // Ambil semua tagihan yang belum lunas untuk username ini
            $stmt = $pdo->prepare("SELECT * FROM invoices WHERE username = ? AND status = 'Belum Lunas' ORDER BY billing_month ASC");
            $stmt->execute([$username]);
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($invoices)) {
                $error_message = 'Tidak ada tagihan yang belum lunas untuk pelanggan ' . htmlspecialchars($username) . '.';
            }
        } else {
            $error_message = 'Gagal terhubung ke database. Silakan coba lagi nanti.';
        }
    } catch (Exception $e) {
        error_log("Error in cek_tagihan.php (GET username): ". $e->getMessage());
        $error_message = 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';
    }
} 
// --- AKHIR PERBAIKAN GET ---
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_bill'])) {
    $whatsapp_input = trim($_POST['whatsapp_number']);

    if (empty($whatsapp_input)) {
        $error_message = 'Nomor WhatsApp tidak boleh kosong.';
    } else {
        try {
            // Akses pengaturan aplikasi global
            global $app_settings; 

            // 1. Cari username di MikroTik berdasarkan Nomor WhatsApp
            $API = new RouterosAPI();
            $API->debug = false;
            $user_found = false;
            // $username = ''; // Sudah diinisialisasi di atas

            // Gunakan pengaturan dari $app_settings
            if ($API->connect($app_settings['router_ip'], $app_settings['router_user'], $app_settings['router_pass'])) {
                $secrets = $API->comm('/ppp/secret/print');
                $API->disconnect();

                // Normalisasi nomor WA yang diinput
                $input_wa_normalized = preg_replace('/[^0-9]/', '', $whatsapp_input);
                if (substr($input_wa_normalized, 0, 1) === '0') {
                    $input_wa_normalized = '62' . substr($input_wa_normalized, 1);
                }

                foreach ($secrets as $secret) {
                    $stored_wa_raw = parse_comment_for_wa($secret['comment'] ?? ''); // Gunakan fungsi global
                    if ($stored_wa_raw) {
                        // Normalisasi nomor WA yang tersimpan
                        $stored_wa_normalized = preg_replace('/[^0-9]/', '', $stored_wa_raw);
                        if (substr($stored_wa_normalized, 0, 1) === '0') {
                            $stored_wa_normalized = '62' . substr($stored_wa_normalized, 1);
                        }

                        if ($stored_wa_normalized === $input_wa_normalized) {
                            $username = $secret['name'];
                            $user_found = true;
                            break; // Hentikan pencarian jika sudah ketemu
                        }
                    }
                }
            } else {
                $error_message = 'Tidak dapat terhubung ke server MikroTik. Silakan coba lagi nanti.';
            }

            // 2. Jika user ditemukan, cari tagihannya di database lokal
            if ($user_found) {
                $pdo = connect_db();
                if ($pdo) {
                    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE username = ? AND status = 'Belum Lunas' ORDER BY billing_month ASC");
                    $stmt->execute([$username]);
                    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($invoices)) {
                        $error_message = 'Tidak ada tagihan yang belum lunas untuk nomor WhatsApp ini.';
                    }
                } else {
                    $error_message = 'Gagal terhubung ke database. Silakan coba lagi nanti.';
                }
            } elseif (empty($error_message)) { // Hanya set error jika belum ada error lain
                $error_message = 'Nomor WhatsApp tidak terdaftar atau tidak ditemukan di MikroTik.';
            }

        } catch (Exception $e) {
            error_log("Error in cek_tagihan.php (POST whatsapp): ". $e->getMessage());
            $error_message = 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';
        }
    }
}
// Jika tidak ada POST request atau GET parameter 'u', tampilkan form kosong
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Tagihan Internet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #16191c;
            color: #d1d2d3;
        }
        .card {
            background-color: #212529;
            border: 1px solid #2a2e34;
        }
        .form-control {
            background-color: #2c3034;
            border-color: #3e444a;
            color: #fff;
        }
        .form-control:focus {
            background-color: #2c3034;
            border-color: #4e73df;
            color: #fff;
            box-shadow: none;
        }
        .table {
            --bs-table-bg: #212529;
            --bs-table-color: #d1d2d3;
            --bs-table-border-color: #2a2e34;
            --bs-table-hover-bg: #32383e;
        }
        /* Style for payment channel buttons */
        .payment-channel-btn {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 15px;
            padding: 10px 15px;
            margin-bottom: 10px;
            border: 1px solid #3e444a;
            border-radius: 0.375rem;
            background-color: #2c3034;
            color: #d1d2d3;
            text-align: left;
            width: 100%;
            transition: all 0.2s ease-in-out;
        }
        .payment-channel-btn:hover {
            background-color: #32383e;
            border-color: #4e73df;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .payment-channel-btn img {
            max-height: 30px;
            max-width: 60px;
            object-fit: contain;
            border-radius: 4px;
        }
        .payment-channel-btn span {
            font-weight: bold;
        }
        .payment-channel-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg">
                    <div class="card-header text-center">
                        <h3 class="mb-0 text-white"><i class="fas fa-file-invoice-dollar me-2"></i>Cek Tagihan Anda</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-warning mt-4"><?= $error_message ?></div>
                        <?php endif; ?>

                        <?php 
                        // --- PERBAIKAN: Tampilkan form input WA hanya jika tidak ada username dari URL dan tidak ada POST data ---
                        if (empty($username) && empty($_POST['check_bill'])): 
                        ?>
                        <p class="text-center text-muted">Masukkan nomor WhatsApp Anda yang terdaftar untuk melihat tagihan.</p>
                        <form method="POST" action="cek_tagihan.php">
                            <input type="hidden" name="check_bill" value="1">
                            <div class="input-group mb-3">
                                <input type="tel" class="form-control form-control-lg" id="whatsapp_number" name="whatsapp_number" placeholder="Contoh: 081234567890" value="" required>
                                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-search me-2"></i>Cek</button>
                            </div>
                        </form>
                        <?php 
                        // --- AKHIR PERBAIKAN ---
                        elseif (!empty($invoices)): 
                        ?>
                        <hr class="my-4">
                        <h4 class="mb-3">Tagihan untuk: <span class="text-primary"><?= htmlspecialchars($username) ?></span></h4>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Bulan Tagihan</th>
                                        <th>Jumlah (Rp)</th>
                                        <th>Jatuh Tempo</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?= date('F Y', strtotime($invoice['billing_month'] . '-01')) ?></td>
                                        <td class="fw-bold"><?= number_format($invoice['amount'], 0, ',', '.') ?></td>
                                        <td><?= date('d F Y', strtotime($invoice['due_date'])) ?></td>
                                        <td class="text-center">
                                            <!-- Tombol "Bayar Sekarang" yang memicu modal -->
                                            <button type="button" class="btn btn-success select-payment-method-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#paymentMethodModal"
                                                    data-invoice-id="<?= $invoice['id'] ?>"
                                                    data-invoice-amount="<?= $invoice['amount'] ?>"
                                                    data-invoice-username="<?= htmlspecialchars($invoice['username']) ?>"
                                                    title="Pilih Metode Pembayaran">
                                                <i class="fas fa-credit-card me-2"></i>Bayar Sekarang
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pemilihan Metode Pembayaran -->
    <div class="modal fade" id="paymentMethodModal" tabindex="-1" aria-labelledby="paymentMethodModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentMethodModalLabel">Pilih Metode Pembayaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Anda akan membayar tagihan untuk <strong id="modal-invoice-username-display"></strong> sebesar <strong id="modal-invoice-amount-display"></strong>.</p>
                    <hr>
                    <div id="payment-channels-list">
                        <?php if (!empty($filtered_payment_channels)): ?>
                            <?php foreach ($filtered_payment_channels as $channel): ?>
                                <button type="button" class="payment-channel-btn" 
                                        data-method-code="<?= htmlspecialchars($channel['code']) ?>"
                                        data-method-name="<?= htmlspecialchars($channel['name']) ?>"
                                        <?= $channel['active'] ? '' : 'disabled' ?>>
                                    <?php if (!empty($channel['icon_url'])): ?>
                                        <img src="<?= htmlspecialchars($channel['icon_url']) ?>" onerror="this.onerror=null;this.src='https://placehold.co/60x30/2c3034/d1d2d3?text=No+Icon';" alt="<?= htmlspecialchars($channel['name']) ?>">
                                    <?php else: ?>
                                        <img src="https://placehold.co/60x30/2c3034/d1d2d3?text=No+Icon" alt="No Icon">
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($channel['name']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-warning text-center">Tidak ada metode pembayaran yang tersedia.</div>
                        <?php endif; ?>
                    </div>
                    <!-- Hidden form to submit to request_payment.php -->
                    <form id="paymentForm" action="request_payment.php" method="POST" style="display: none;">
                        <input type="hidden" name="invoice_id" id="hidden_invoice_id">
                        <input type="hidden" name="payment_method_code" id="hidden_payment_method_code">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethodModal = document.getElementById('paymentMethodModal');
            const hiddenInvoiceId = document.getElementById('hidden_invoice_id');
            const hiddenPaymentMethodCode = document.getElementById('hidden_payment_method_code');
            const paymentForm = document.getElementById('paymentForm');
            const modalInvoiceAmountDisplay = document.getElementById('modal-invoice-amount-display');
            const modalInvoiceUsernameDisplay = document.getElementById('modal-invoice-username-display');

            paymentMethodModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const invoiceId = button.getAttribute('data-invoice-id');
                const invoiceAmount = button.getAttribute('data-invoice-amount');
                const invoiceUsername = button.getAttribute('data-invoice-username');

                hiddenInvoiceId.value = invoiceId;
                modalInvoiceAmountDisplay.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(invoiceAmount);
                modalInvoiceUsernameDisplay.textContent = invoiceUsername;
            });

            // Add click listener to all payment channel buttons inside the modal
            const paymentChannelButtons = document.querySelectorAll('#paymentMethodModal .payment-channel-btn');
            paymentChannelButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const methodCode = this.getAttribute('data-method-code');
                    hiddenPaymentMethodCode.value = methodCode;
                    paymentForm.submit(); // Submit the hidden form
                });
            });
        });
    </script>
</body>
</html>