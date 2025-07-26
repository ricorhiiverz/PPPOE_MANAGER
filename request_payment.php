<?php
/**
 * Halaman Pemilihan Metode Pembayaran & Request ke Payment Gateway (Tripay).
 *
 * @package PPPOE_MANAGER
 */

require_once 'config.php';

// --- REVISI: FUNGSI LOGGING UNTUK DEBUGGING ---
function write_to_log($message) {
    $logFile = __DIR__ . '/tripay_debug.log'; // Simpan log di direktori yang sama
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] " . print_r($message, true) . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Inisialisasi variabel
$error_message = '';
$tagihan = null;
$paymentChannels = [];

// Ambil semua pengaturan aplikasi
$app_settings = load_app_settings($pdo);
$apiKey       = $app_settings['payment_api_key'] ?? '';

// --- FUNGSI UNTUK MENGAMBIL CHANNEL PEMBAYARAN ---
function getPaymentChannels($apiKey) {
    if (empty($apiKey)) return [];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_FRESH_CONNECT  => true,
        CURLOPT_URL            => 'https://tripay.co.id/api-sandbox/merchant/payment-channel',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_FAILONERROR    => false,
    ]);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) return [];
    
    $result = json_decode($response, true);
    return ($result['success'] == true) ? $result['data'] : [];
}

// --- LOGIKA UTAMA ---

// Jika ini adalah request POST (pelanggan sudah memilih metode)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_number = $_POST['no_tagihan'] ?? null;
    $paymentMethod  = $_POST['method'] ?? null;

    if (!$invoice_number || !$paymentMethod) {
        die('Error: Permintaan tidak lengkap.');
    }

    try {
        // Ambil detail tagihan
        $stmt = $pdo->prepare("SELECT t.*, p.nama_pelanggan, p.no_hp, p.no_pelanggan, p.alamat FROM tagihan t JOIN pelanggan p ON t.pelanggan_id = p.id WHERE t.no_tagihan = ?");
        $stmt->execute([$invoice_number]);
        $tagihan = $stmt->fetch();

        if (!$tagihan) die('Error: Tagihan tidak ditemukan.');

        // Ambil kredensial
        $merchantCode = $app_settings['payment_merchant_code'] ?? '';
        $privateKey   = $app_settings['payment_private_key'] ?? '';

        $merchantRef  = $tagihan['no_tagihan'];
        $amount       = (int) $tagihan['jumlah_tagihan'];

        // Buat signature
        $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);
        
        $customerEmail = $tagihan['no_pelanggan'] . '@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');

        $data = [
            'method'         => $paymentMethod,
            'merchant_ref'   => $merchantRef,
            'amount'         => $amount,
            'customer_name'  => $tagihan['nama_pelanggan'],
            'customer_email' => $customerEmail,
            'customer_phone' => $tagihan['no_hp'],
            'order_items'    => [
                ['sku' => 'TAGIHAN' . $tagihan['bulan_tagihan'], 'name' => 'Tagihan Internet Bulan ' . date('F Y', strtotime($tagihan['bulan_tagihan'])), 'price' => $amount, 'quantity' => 1]
            ],
            'callback_url'   => 'https://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/callback.php',
            'return_url'     => 'https://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/cek_tagihan.php',
            'expired_time'   => (time() + (24 * 60 * 60)),
            'signature'      => $signature
        ];
        
        write_to_log("Requesting payment for invoice {$merchantRef}. Data sent: " . json_encode($data, JSON_PRETTY_PRINT));

        // Kirim request ke Tripay
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_FRESH_CONNECT  => true,
            CURLOPT_URL            => 'https://tripay.co.id/api-sandbox/transaction/create',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_FAILONERROR    => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data)
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        write_to_log("Raw response from Tripay: " . $response);

        if ($error) {
            write_to_log("cURL Error: " . $error);
            die("Error cURL: " . $error);
        }

        $result = json_decode($response, true);

        // --- PERBAIKAN UTAMA ---
        if (isset($result['success']) && $result['success'] == true && isset($result['data'])) {
            // Cek apakah ada checkout_url atau payment_url
            $redirect_url = $result['data']['checkout_url'] ?? $result['data']['payment_url'] ?? null;

            if ($redirect_url) {
                // Jika URL ditemukan, lanjutkan proses
                $stmt_pembayaran = $pdo->prepare("INSERT INTO pembayaran (no_tagihan, reference, merchant_ref, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt_pembayaran->execute([
                    $tagihan['no_tagihan'],
                    $result['data']['reference'],
                    $result['data']['merchant_ref'],
                    $result['data']['amount'],
                    $result['data']['status']
                ]);
                header('Location: ' . $redirect_url);
                exit();
            } else {
                // Kasus langka jika URL tidak ada di respons yang sukses
                $errorMessage = 'URL pembayaran tidak ditemukan dalam respons Tripay.';
                write_to_log("Tripay API Logic Error: " . $errorMessage . " | Response: " . json_encode($result));
                die('Error: ' . htmlspecialchars($errorMessage));
            }
        } else {
            // Tangani jika success false atau ada masalah lain
            $errorMessage = $result['message'] ?? 'Gagal membuat transaksi.';
            write_to_log("Tripay API Error: " . $errorMessage);
            die('Error: ' . htmlspecialchars($errorMessage));
        }

    } catch (PDOException $e) {
        write_to_log("Database Error: " . $e->getMessage());
        die('Error Database: ' . $e->getMessage());
    }
} 
// Jika ini adalah request GET (halaman dimuat pertama kali)
else {
    $invoice_number = $_GET['invoice'] ?? null;
    if (!$invoice_number) die('Error: Nomor tagihan tidak valid.');

    try {
        $stmt = $pdo->prepare("SELECT t.*, p.nama_pelanggan FROM tagihan t JOIN pelanggan p ON t.pelanggan_id = p.id WHERE t.no_tagihan = ?");
        $stmt->execute([$invoice_number]);
        $tagihan = $stmt->fetch();

        if (!$tagihan) die('Error: Tagihan tidak ditemukan.');
        if ($tagihan['status_pembayaran'] === 'lunas') die('Tagihan ini sudah lunas.');
        
        $paymentChannels = getPaymentChannels($apiKey);

        if (!empty($paymentChannels) && $tagihan) {
            $baseAmount = (int) $tagihan['jumlah_tagihan'];
            usort($paymentChannels, function($a, $b) use ($baseAmount) {
                // Hitung total fee untuk channel A
                $fee_a_flat = $a['fee_customer']['flat'];
                $fee_a_percent = ($baseAmount * $a['fee_customer']['percent']) / 100;
                $total_fee_a = $fee_a_flat + $fee_a_percent;

                // Hitung total fee untuk channel B
                $fee_b_flat = $b['fee_customer']['flat'];
                $fee_b_percent = ($baseAmount * $b['fee_customer']['percent']) / 100;
                $total_fee_b = $fee_b_flat + $fee_b_percent;

                // Bandingkan
                return $total_fee_a <=> $total_fee_b;
            });
        }

    } catch (PDOException $e) {
        $error_message = 'Gagal memuat data tagihan.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Metode Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .container { max-width: 600px; }
        .payment-method {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .payment-method:hover, .payment-method.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .payment-method img {
            max-width: 80px;
            margin-right: 1rem;
        }
        .payment-method .form-check-input {
            margin-left: auto;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white text-center">
                <h4 class="mb-0">Detail Tagihan</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($tagihan): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Nama Pelanggan:</span>
                    <strong><?php echo htmlspecialchars($tagihan['nama_pelanggan']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Nomor Tagihan:</span>
                    <strong><?php echo htmlspecialchars($tagihan['no_tagihan']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>Tagihan Bulan:</span>
                    <strong><?php echo date('F Y', strtotime($tagihan['bulan_tagihan'])); ?></strong>
                </div>
                <hr>
                <div class="rincian-biaya">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Jumlah Tagihan:</span>
                        <strong>Rp <?php echo number_format($tagihan['jumlah_tagihan'], 0, ',', '.'); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Biaya Admin:</span>
                        <strong id="adminFee">Rp 0</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded">
                        <span class="h5 mb-0">Total Bayar:</span>
                        <span class="h5 mb-0 text-danger"><strong id="totalAmount">Rp <?php echo number_format($tagihan['jumlah_tagihan'], 0, ',', '.'); ?></strong></span>
                    </div>
                </div>
                
                <form method="POST" action="request_payment.php" id="paymentForm" class="mt-4">
                    <input type="hidden" name="no_tagihan" value="<?php echo htmlspecialchars($tagihan['no_tagihan']); ?>">
                    <h5>Pilih Metode Pembayaran</h5>
                    
                    <?php if (!empty($paymentChannels)): ?>
                        <?php foreach($paymentChannels as $channel): ?>
                        <?php
                            $fee_flat = $channel['fee_customer']['flat'];
                            $fee_percent = $channel['fee_customer']['percent'];
                        ?>
                        <label class="payment-method" for="channel-<?php echo $channel['code']; ?>">
                            <img src="<?php echo htmlspecialchars($channel['icon_url']); ?>" alt="<?php echo htmlspecialchars($channel['name']); ?>">
                            <div class="flex-grow-1">
                                <strong><?php echo htmlspecialchars($channel['name']); ?></strong>
                                <div class="text-muted small">
                                    <?php if ($fee_flat == 0 && $fee_percent == 0): ?>
                                        Biaya: Gratis
                                    <?php else: ?>
                                        Biaya: Rp <?php echo number_format($fee_flat); ?> 
                                        <?php if($fee_percent > 0) { echo '+ ' . $fee_percent . '%'; } ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="radio" class="form-check-input" 
                                id="channel-<?php echo $channel['code']; ?>" 
                                name="method" 
                                value="<?php echo $channel['code']; ?>"
                                data-fee-flat="<?php echo $fee_flat; ?>"
                                data-fee-percent="<?php echo $fee_percent; ?>"
                                required>
                        </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">Tidak ada metode pembayaran yang tersedia saat ini.</div>
                    <?php endif; ?>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-success btn-lg" <?php echo empty($paymentChannels) ? 'disabled' : ''; ?>>
                            <i class="fas fa-shield-alt me-2"></i>Lanjutkan Pembayaran
                        </button>
                    </div>
                </form>
                <?php else: ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        const baseAmount = <?php echo $tagihan['jumlah_tagihan'] ?? 0; ?>;
        const adminFeeEl = document.getElementById('adminFee');
        const totalAmountEl = document.getElementById('totalAmount');
        const paymentRadios = document.querySelectorAll('.payment-method input[type="radio"]');

        paymentRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Style untuk item yang dipilih
                document.querySelectorAll('.payment-method').forEach(label => label.classList.remove('selected'));
                if (this.checked) {
                    this.closest('.payment-method').classList.add('selected');
                }

                // Kalkulasi biaya dinamis
                if (this.checked) {
                    const feeFlat = parseFloat(this.dataset.feeFlat);
                    const feePercent = parseFloat(this.dataset.feePercent);

                    const percentFeeAmount = (baseAmount * feePercent) / 100;
                    const totalFee = Math.ceil(feeFlat + percentFeeAmount); // Pembulatan ke atas
                    const finalAmount = baseAmount + totalFee;

                    // Format ke Rupiah
                    const formatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
                    
                    adminFeeEl.textContent = formatter.format(totalFee);
                    totalAmountEl.textContent = formatter.format(finalAmount);
                }
            });
        });
    </script>
</body>
</html>
