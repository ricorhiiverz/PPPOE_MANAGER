<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin - PPPoE Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #16191c; 
            color: #d1d2d3; 
            display: flex; /* Use flexbox for centering */
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
            min-height: 100vh; /* Full viewport height */
            margin: 0; /* Remove default body margin */
        }
        .card { 
            background-color: #212529; 
            border: 1px solid #2a2e34; 
            width: 100%; /* Ensure card takes full width of its column */
            max-width: 400px; /* Limit max width for better appearance on large screens */
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
        /* Responsive adjustments for smaller screens */
        @media (max-width: 576px) {
            body { font-size: 0.9rem; }
            .h3 { font-size: 1.5rem; }
            .card { margin: 15px; /* Add some margin on very small screens */ }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-lg">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-2">Setup Admin Awal</h3>
                        <?php if (isset($setup_error)): ?>
                            <div class="alert alert-danger mb-3"><?= htmlspecialchars($setup_error) ?></div>
                        <?php else: ?>
                            <p class="text-center text-muted mb-4">Database belum terinisialisasi atau pengguna admin belum ada. Silakan buat pengguna admin pertama.</p>
                        <?php endif; ?>
                        <form method="POST" action="index.php">
                            <input type="hidden" name="setup_admin" value="1">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username Admin</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password Admin</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Buat Admin & Mulai</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
