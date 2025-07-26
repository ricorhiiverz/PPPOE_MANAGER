<?php
/**
 * Halaman Peta Pelanggan.
 *
 * Menampilkan peta interaktif dengan lokasi semua pelanggan yang memiliki
 * data koordinat, dengan opsi filter berdasarkan wilayah.
 *
 * @package PPPOE_MANAGER
 */

// Keamanan: Pastikan hanya admin yang bisa mengakses halaman ini.
if ($_SESSION['level'] !== 'admin') {
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    return;
}

// Ambil nilai filter dari URL
$filter_wilayah = $_GET['filter_wilayah'] ?? '';

// Ambil daftar semua wilayah untuk dropdown filter
try {
    $wilayah_list = $pdo->query("SELECT id, nama_wilayah FROM wilayah ORDER BY nama_wilayah ASC")->fetchAll();
} catch (PDOException $e) {
    $wilayah_list = []; // Kosongkan jika gagal
}

// Ambil data pelanggan yang memiliki koordinat, dengan filter jika ada
try {
    $sql = "
        SELECT nama_pelanggan, alamat, koordinat, status_berlangganan 
        FROM pelanggan 
        WHERE koordinat IS NOT NULL AND koordinat != ''
    ";
    $params = [];

    if (!empty($filter_wilayah)) {
        $sql .= " AND wilayah_id = ?";
        $params[] = $filter_wilayah;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pelanggan_list = $stmt->fetchAll();

    // Ubah data menjadi format JSON yang mudah dibaca oleh JavaScript
    $pelanggan_json = json_encode($pelanggan_list);

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Gagal memuat data pelanggan untuk peta: ' . $e->getMessage() . '</div>';
    $pelanggan_json = '[]'; // Kirim array kosong jika gagal
}
?>

<!-- Memuat library Leaflet.js untuk peta -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" xintegrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" xintegrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>

<style>
    #customerMap {
        height: 70vh; /* Tinggi peta 70% dari tinggi viewport */
        width: 100%;
        border-radius: 0.5rem;
        border: 1px solid #ddd;
    }
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="fas fa-map-marked-alt me-2"></i>Peta Sebaran Pelanggan</h5>
        
        <!-- Form Filter Wilayah -->
        <form method="GET" action="main_view.php" class="d-flex align-items-center" style="min-width: 250px;">
            <input type="hidden" name="page" value="peta">
            <label for="filter_wilayah" class="form-label me-2 mb-0">Wilayah:</label>
            <select class="form-select form-select-sm" id="filter_wilayah" name="filter_wilayah" onchange="this.form.submit()">
                <option value="">-- Semua Wilayah --</option>
                <?php foreach ($wilayah_list as $wilayah): ?>
                    <option value="<?php echo $wilayah['id']; ?>" <?php echo ($filter_wilayah == $wilayah['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($wilayah['nama_wilayah']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="card-body">
        <div id="customerMap"></div>
    </div>
</div>

<script>
    // Inisialisasi peta, berpusat di Indonesia
    var map = L.map('customerMap').setView([-2.548926, 118.0148634], 5);

    // Tambahkan layer peta dari OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Ambil data pelanggan dari PHP
    var customers = <?php echo $pelanggan_json; ?>;
    var markers = []; // Array untuk menampung semua layer marker

    // Definisikan ikon kustom untuk setiap status
    var greenIcon = new L.Icon({
      iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
      shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34],
      shadowSize: [41, 41]
    });

    var redIcon = new L.Icon({
      iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
      shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34],
      shadowSize: [41, 41]
    });

    var yellowIcon = new L.Icon({
      iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-yellow.png',
      shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34],
      shadowSize: [41, 41]
    });

    // Loop melalui setiap pelanggan dan tambahkan marker ke peta
    customers.forEach(function(customer) {
        if (customer.koordinat) {
            var coords = customer.koordinat.split(',').map(function(coord) {
                return parseFloat(coord.trim());
            });

            if (coords.length === 2 && !isNaN(coords[0]) && !isNaN(coords[1])) {
                var icon;
                switch(customer.status_berlangganan) {
                    case 'aktif':
                        icon = greenIcon;
                        break;
                    case 'nonaktif':
                        icon = redIcon;
                        break;
                    case 'isolir':
                        icon = yellowIcon;
                        break;
                    default:
                        icon = L.Icon.Default();
                }

                var marker = L.marker(coords, {icon: icon});
                
                // Tambahkan popup informasi
                marker.bindPopup(
                    "<b>" + customer.nama_pelanggan + "</b><br>" +
                    "Status: <b>" + customer.status_berlangganan + "</b><br>" +
                    "Alamat: " + customer.alamat
                );
                
                markers.push(marker); // Tambahkan marker ke array
            }
        }
    });

    if (markers.length > 0) {
        // Jika ada marker, buat sebuah feature group
        var featureGroup = L.featureGroup(markers).addTo(map);
        // Atur view peta agar pas dengan semua marker
        map.fitBounds(featureGroup.getBounds().pad(0.1)); // pad(0.1) memberi sedikit ruang di pinggir
    } else {
        // Jika tidak ada marker, biarkan view default (Indonesia)
        // Tampilkan pesan jika filter aktif tapi tidak ada hasil
        <?php if (!empty($filter_wilayah)): ?>
            var mapContainer = document.getElementById('customerMap');
            var noResultDiv = document.createElement('div');
            noResultDiv.innerHTML = '<div class="alert alert-info m-3">Tidak ada pelanggan dengan data koordinat di wilayah yang dipilih.</div>';
            mapContainer.parentNode.insertBefore(noResultDiv, mapContainer);
        <?php endif; ?>
    }
</script>