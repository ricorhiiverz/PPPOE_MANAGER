<?php
/**
 * Halaman Login Pengguna.
 *
 * Halaman ini menangani proses otentikasi pengguna.
 * Logikanya adalah sebagai berikut:
 * 1. Memuat konfigurasi database.
 * 2. Jika pengguna sudah login, alihkan ke halaman utama (index.php).
 * 3. Menampilkan pesan sukses jika baru saja menyelesaikan proses setup.
 * 4. Saat form disubmit, verifikasi username dan password dengan data di tabel 'users'.
 * 5. Jika berhasil, simpan informasi pengguna ke dalam session dan alihkan ke index.php.
 *
 * @package PPPOE_MANAGER
 */

// Muat file konfigurasi untuk mendapatkan koneksi $pdo.
require_once 'config.php';

// Jika pengguna sudah login (ada user_id di session), jangan tampilkan halaman login.
// Langsung alihkan ke halaman utama.
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Inisialisasi variabel untuk pesan.
$error_message = '';
$success_message = '';

// Cek apakah ada pesan sukses dari halaman setup.
if (isset($_SESSION['setup_success'])) {
    $success_message = $_SESSION['setup_success'];
    // Hapus pesan dari session agar tidak muncul lagi saat halaman di-refresh.
    unset($_SESSION['setup_success']);
}

// Proses form jika metode request adalah POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password wajib diisi.';
    } else {
        try {
            // Cari pengguna berdasarkan username.
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Verifikasi pengguna dan password.
            // password_verify() akan membandingkan password yang diinput dengan hash di database.
            if ($user && password_verify($password, $user['password'])) {
                // Jika berhasil, simpan data penting ke session.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['level'] = $user['level'];

                // Alihkan ke halaman utama.
                header('Location: index.php');
                exit();
            } else {
                // Jika username atau password salah.
                $error_message = 'Username atau password salah.';
            }
        } catch (PDOException $e) {
            // Jika terjadi error pada database.
            $error_message = 'Terjadi masalah pada server. Silakan coba lagi nanti.';
            // Untuk debugging, Anda bisa menampilkan error aslinya:
            // $error_message = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PPPoE Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 5rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h2 class="text-center mb-4">Login</h2>

            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <form method="POST" action="login_page.php">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Login</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>