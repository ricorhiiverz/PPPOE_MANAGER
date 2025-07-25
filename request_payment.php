<?php
// Sertakan file konfigurasi dan fungsi database
require_once('config.php'); // Menggunakan require_once untuk memastikan $app_settings tersedia

function connect_db() {
    $db_file = 'users.db';
    try {
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage());
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['invoice_id'])) {
    display_error('Akses tidak valid.');
}

$invoice_id = $_POST['invoice_id'];

// 1. Ambil detail tagihan dari database
try {
    $pdo = connect_db();
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND status = 'Belum Lunas'");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        display_error('Tagihan tidak ditemukan atau sudah lunas.');
    }
} catch (Exception $e) {
    display_error('Gagal mengambil data tagihan dari database.');
}

// 2. Siapkan data untuk dikirim ke Tripay
$merchantRef    = 'INV-' . $invoice['id'] . '-' . time(); // ID unik untuk transaksi ini
$amount         = $invoice['amount'];

global $app_settings; // Akses variabel global $app_settings

$data = [
    'method'            => 'QRIS', // Metode pembayaran default, bisa diganti
    'merchant_ref'      => $merchantRef,
    'amount'            => $amount,
    'customer_name'     => $invoice['username'],
    'customer_email'    => 'email@pelanggan.com', // Ganti dengan email pelanggan jika ada
    'customer_phone'    => '081234567890', // Ganti dengan no. telp pelanggan jika ada
    'order_items'       => [
        [
            'sku'       => 'INTERNET',
            'name'      => 'Tagihan Internet ' . date('F Y', strtotime($invoice['billing_month'] . '-01')),
            'price'     => $amount,
            'quantity'  => 1
        ]
    ],
    'expired_time'      => (time() + (24 * 60 * 60)), // 24 jam
    'signature'         => hash_hmac('sha256', $app_settings['tripay_merchant_code'] . $merchantRef . $amount, $app_settings['tripay_private_key'])
];

// 3. Kirim permintaan ke Tripay menggunakan cURL
$curl = curl_init();

$apiUrl = $app_settings['tripay_production_mode'] ? 'https://tripay.co.id/api/transaction/create' : 'https://tripay.co.id/api-sandbox/transaction/create';

curl_setopt_array($curl, [
    CURLOPT_FRESH_CONNECT  => true,
    CURLOPT_URL            => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $app_settings['tripay_api_key']],
    CURLOPT_FAILONERROR    => false,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($data)
]);

$response = curl_exec($curl);
$error = curl_error($curl);

curl_close($curl);

if ($error) {
    display_error('Gagal terhubung ke payment gateway: ' . $error);
}

$result = json_decode($response, true);

// 4. Proses respons dari Tripay
if (isset($result['success']) && $result['success'] == true && isset($result['data']['checkout_url'])) {
    // Redirect pelanggan ke halaman pembayaran Tripay
    header('Location: ' . $result['data']['checkout_url']);
    exit;
} else {
    // Tampilkan pesan error jika gagal membuat transaksi
    $error_message = isset($result['message']) ? $result['message'] : 'Gagal membuat transaksi pembayaran.';
    display_error($error_message);
}
?>