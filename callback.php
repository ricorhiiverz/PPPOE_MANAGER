<?php
/**
 * Halaman Callback dari Payment Gateway (Tripay).
 *
 * File ini menerima notifikasi status pembayaran dari Tripay.
 * Ini adalah komunikasi server-to-server, tidak untuk diakses pengguna.
 *
 * @package PPPOE_MANAGER
 */

require_once 'config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ambil kredensial dari database untuk validasi
$privateKey = '';
try {
    $app_settings = load_app_settings($pdo);
    $privateKey = $app_settings['payment_private_key'] ?? '';
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database config error.']);
    exit;
}

$callbackSignature = $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] ?? '';
$json = file_get_contents('php://input');

if (empty($privateKey) || empty($callbackSignature) || empty($json)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request. Missing parameters.']);
    exit;
}

$signature = hash_hmac('sha256', $json, $privateKey);

if ($callbackSignature !== $signature) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid signature.']);
    exit;
}

$data = json_decode($json, true);
$event = $_SERVER['HTTP_X_CALLBACK_EVENT'] ?? '';

if ($event !== 'payment_status') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid event.']);
    exit;
}

$merchantRef = $data['merchant_ref'];
$status = strtoupper((string) $data['status']);
$reference = $data['reference'];

if ($data['is_closed_payment'] === 1) {
    try {
        $stmt_pembayaran = $pdo->prepare("UPDATE pembayaran SET status = ? WHERE reference = ?");
        $stmt_pembayaran->execute([$status, $reference]);

        if ($status === 'PAID') {
            $stmt_tagihan = $pdo->prepare("UPDATE tagihan SET status_pembayaran = 'lunas', metode_pembayaran = ?, tanggal_pembayaran = NOW(), dibayar_oleh = 'Online Payment' WHERE no_tagihan = ?");
            $stmt_tagihan->execute([$data['payment_method'], $merchantRef]);

            $_SESSION['payment_success_message'] = "Pembayaran untuk tagihan " . htmlspecialchars($merchantRef) . " telah berhasil!";

            // REVISI: Kirim notifikasi WhatsApp setelah pembayaran berhasil
            try {
                $stmt_pelanggan = $pdo->prepare("
                    SELECT p.no_hp, p.nama_pelanggan, t.jumlah_tagihan, t.bulan_tagihan
                    FROM tagihan t
                    JOIN pelanggan p ON t.pelanggan_id = p.id
                    WHERE t.no_tagihan = ?
                ");
                $stmt_pelanggan->execute([$merchantRef]);
                $pelanggan = $stmt_pelanggan->fetch();

                if ($pelanggan && !empty($pelanggan['no_hp'])) {
                    $nama_isp = $app_settings['nama_isp'] ?? 'Kami';
                    $pesan = "Terima kasih Bpk/Ibu " . $pelanggan['nama_pelanggan'] . ",\n\n" .
                             "Pembayaran tagihan internet Anda untuk bulan " . date('F Y', strtotime($pelanggan['bulan_tagihan'])) . " sebesar Rp " . number_format($pelanggan['jumlah_tagihan']) . " telah kami terima.\n\n" .
                             "Terima kasih telah menjadi pelanggan setia " . $nama_isp . ".";
                    
                    send_whatsapp_notification($app_settings, $pelanggan['no_hp'], $pesan);
                }
            } catch (Exception $e) {
                // Log error jika pengiriman WA gagal, tapi jangan hentikan proses callback
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;

    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database update error: ' . $e->getMessage()]);
        exit;
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;

?>