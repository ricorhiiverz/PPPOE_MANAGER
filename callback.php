<?php
// Sertakan file konfigurasi, class API, dan fungsi database
require_once('config.php'); // Menggunakan require_once untuk memastikan $app_settings, sendWhatsAppMessage, dan parse_comment_for_wa tersedia
require('RouterosAPI.php'); // Diperlukan untuk auto-enable

// Pastikan zona waktu adalah WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

function connect_db() {
    $db_file = 'users.db';
    try {
        $pdo = new PDO('sqlite:'. $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // Jangan tampilkan error ke publik, cukup catat di log server
        error_log("Koneksi database gagal: ". $e->getMessage());
        return null;
    }
}

// Fungsi parse_comment_for_wa sekarang ada di config.php
// function parse_comment_for_wa($comment) {
//     $parts = explode('|', $comment);
//     foreach ($parts as $part) {
//         if (strpos($part, 'WA:') === 0) {
//             return substr($part, 3);
//         }
//     }
//     return null;
// }

// Set header response ke JSON
header('Content-Type: application/json');

// 1. Ambil data callback dari Tripay
$json = file_get_contents('php://input');

// Menggunakan getallheaders() untuk kompatibilitas yang lebih baik
$headers = getallheaders();
$callbackSignature = isset($headers['X-Callback-Signature']) ? $headers['X-Callback-Signature'] : (isset($_SERVER['HTTP_X_CALLBACK_SIGN_ATURE']) ? $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] : '');

// 2. Validasi Signature untuk keamanan
global $app_settings; // Akses variabel global $app_settings
$signature = hash_hmac('sha256', $json, $app_settings['tripay_private_key']);

if ($signature !== $callbackSignature) {
    // Jika signature tidak valid, hentikan eksekusi dan beri respons error
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Signature'
    ]);
    exit;
}

$data = json_decode($json, true);
$event = isset($headers['X-Callback-Event']) ? $headers['X-Callback-Event'] : (isset($_SERVER['HTTP_X_CALLBACK_EVENT']) ? $_SERVER['HTTP_X_CALLBACK_EVENT'] : null);

if ($event !== 'payment_status') {
    // Jika event bukan 'payment_status', kita tidak perlu proses lebih lanjut
    echo json_encode(['success' => true]); // Beri respons sukses agar Tripay tidak kirim ulang
    exit;
}

if (isset($data['status']) && $data['status'] === 'PAID') {
    $merchantRef = $data['merchant_ref'];
    
    // Ekstrak ID invoice dari merchant_ref (Contoh: INV-123-timestamp)
    $parts = explode('-', $merchantRef);
    if (count($parts) < 2 || $parts[0] !== 'INV') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid merchant_ref format'
        ]);
        exit;
    }
    $invoice_id = $parts[1];

    // 3. Update status tagihan di database
    $pdo = connect_db();
    if ($pdo) {
        try {
            // Ambil username dan amount dari invoice terlebih dahulu
            $stmt_invoice = $pdo->prepare("SELECT username, amount, billing_month FROM invoices WHERE id = ? AND status = 'Belum Lunas'");
            $stmt_invoice->execute([$invoice_id]);
            $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

            if ($invoice) {
                $username_to_enable = $invoice['username'];
                $invoice_amount = $invoice['amount'];
                $billing_month = $invoice['billing_month'];

                // Update database
                $stmt_update = $pdo->prepare(
                    "UPDATE invoices 
                     SET status = 'Lunas', 
                         paid_date = ?, 
                         updated_by = 'Tripay System', 
                         payment_method = 'Online' 
                     WHERE id = ?"
                );
                $stmt_update->execute([date('Y-m-d H:i:s'), $invoice_id]);

                // 4. Logika Auto-Enable Pelanggan dan Kirim Notifikasi WA
                $API = new RouterosAPI();
                $API->debug = false;
                // Gunakan pengaturan dari $app_settings
                if ($API->connect($app_settings['router_ip'], $app_settings['router_user'], $app_settings['router_pass'])) {
                    $secrets = $API->comm("/ppp/secret/print", ["?name" => $username_to_enable]);
                    $customer_whatsapp = null;
                    if (!empty($secrets)) {
                        $secret_details = $secrets[0];
                        $customer_whatsapp = parse_comment_for_wa($secret_details['comment'] ?? ''); // Gunakan fungsi global
                        if (isset($secret_details['disabled']) && $secret_details['disabled'] === 'true') {
                            $secret_id = $secret_details['.id'];
                            $API->comm("/ppp/secret/enable", ["numbers" => $secret_id]);
                        }
                    }
                    $API->disconnect();

                    // Kirim notifikasi WhatsApp konfirmasi pembayaran
                    if ($customer_whatsapp) {
                        $message = "Halo pelanggan " . $username_to_enable . ",\n";
                        $message .= "Pembayaran tagihan internet Anda untuk bulan " . date('F Y', strtotime($billing_month . '-01')) . " sebesar Rp " . number_format($invoice_amount, 0, ',', '.') . " telah berhasil dikonfirmasi.\n";
                        $message .= "Layanan Anda sudah aktif kembali. Terima kasih atas pembayaran Anda!";
                        sendWhatsAppMessage($customer_whatsapp, $message); // Gunakan fungsi global
                    }

                } else {
                    // Log error jika koneksi MikroTik gagal dari callback
                    error_log("Callback MikroTik Connection Error: Could not connect to router for user " . $username_to_enable);
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Callback processed successfully, user enabled if was disabled and WA sent.'
                ]);

            } else {
                // Tidak ada baris yang diupdate (mungkin sudah lunas atau tidak ada)
                echo json_encode([
                    'success' => false,
                    'message' => 'Invoice not found or already paid'
                ]);
            }

        } catch (Exception $e) {
            error_log("Callback DB Error: ". $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Database update failed'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
    }

} else {
    // Jika status bukan PAID, kita tidak melakukan apa-apa
    echo json_encode(['success' => true]);
}

exit;
?>