<?php
// Pastikan pengguna memiliki akses untuk melihat halaman ini
if (!in_array($_SESSION['role'], ['admin', 'teknisi', 'penagih'])) {
    echo '<div class="alert alert-danger">Akses ditolak.</div>';
    return;
}
?>

<!-- Leaflet.js CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    #customerMap {
        height: 70vh; /* 70% dari tinggi viewport, sedikit lebih kecil untuk filter */
        width: 100%;
        border-radius: 0.375rem; /* Menyamakan dengan radius card bootstrap */
    }
    .leaflet-popup-content-wrapper {
        background-color: #2c3034;
        color: #d1d2d3;
        border-radius: 0.375rem;
    }
    .leaflet-popup-content {
        margin: 13px 20px;
    }
    .leaflet-popup-tip {
        background: #2c3034;
    }
    .legend {
        padding: 6px 8px;
        font: 14px;
        background: rgba(33, 37, 41, 0.8);
        color: #d1d2d3;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
        border-radius: 5px;
    }
    .legend i {
        width: 18px;
        height: 18px;
        float: left;
        margin-right: 8px;
        opacity: 0.9;
        border-radius: 50%;
        border: 1px solid #fff;
    }
</style>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0 text-white">Peta Sebaran Pelanggan</h5>
            <div class="col-md-4 col-lg-3">
                <select id="wilayahFilter" class="form-select">
                    <option value="all">Semua Wilayah</option>
                    <?php foreach ($wilayah_list as $wilayah): ?>
                        <option value="<?= htmlspecialchars($wilayah['region_name']) ?>"><?= htmlspecialchars($wilayah['region_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div id="customerMap"></div>
    </div>
</div>

<!-- Leaflet.js JavaScript -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi peta, berpusat di Indonesia
        const map = L.map('customerMap').setView([-2.548926, 118.0148634], 5);

        // Tambahkan layer peta dari OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        // Ambil data pelanggan dan wilayah dari PHP
        // $customers_with_coords sekarang sudah diolah di index.php
        const allCustomers = <?= json_encode($customers_with_coords ?? []) ?>;
        let currentMarkers = L.featureGroup().addTo(map); // Layer grup untuk marker yang ditampilkan

        // Buat ikon kustom untuk setiap status
        const icons = {
            Online: L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
            Offline: L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-grey.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
            Disabled: L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
            'Tidak di MikroTik': L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-orange.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] })
        };

        // Fungsi untuk memperbarui marker di peta berdasarkan filter
        function updateMapMarkers(filterWilayah) {
            currentMarkers.clearLayers(); // Hapus marker yang ada
            const filteredCustomers = allCustomers.filter(customer => {
                return filterWilayah === 'all' || customer.wilayah === filterWilayah;
            });

            const markerBounds = [];

            filteredCustomers.forEach(customer => {
                const coords = customer.coords.split(',').map(coord => parseFloat(coord.trim()));
                if (coords.length === 2 && !isNaN(coords[0]) && !isNaN(coords[1])) {
                    const [lat, lng] = coords;
                    
                    // Gunakan status yang sudah dihitung di PHP
                    const marker = L.marker([lat, lng], { icon: icons[customer.status] });
                    marker.bindPopup(`<b>${customer.name}</b><br>Status: ${customer.status}<br>Wilayah: ${customer.wilayah || 'N/A'}`);
                    currentMarkers.addLayer(marker); // Tambahkan ke featureGroup

                    markerBounds.push([lat, lng]);
                }
            });

            // Auto-zoom ke area di mana ada marker yang difilter
            if (markerBounds.length > 0) {
                map.fitBounds(markerBounds, { padding: [50, 50] });
            } else {
                // Jika tidak ada marker, kembali ke tampilan awal Indonesia
                map.setView([-2.548926, 118.0148634], 5);
            }
        }

        // Inisialisasi awal dengan semua wilayah
        updateMapMarkers('all');

        // Event listener untuk dropdown filter wilayah
        document.getElementById('wilayahFilter').addEventListener('change', function() {
            updateMapMarkers(this.value);
        });

        // Tambahkan legenda
        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'info legend');
            const statuses = {
                'Online': '#28a745', // green
                'Offline': '#6c757d', // grey
                'Disabled': '#dc3545', // red
                'Tidak di MikroTik': '#ffc107' // orange
            };
            let legendHtml = '<h6>Status Pelanggan</h6>';
            for (const status in statuses) {
                legendHtml += `<i style="background:${statuses[status]}"></i> ${status}<br>`;
            }
            div.innerHTML = legendHtml;
            return div;
        };
        legend.addTo(map);
    });
</script>
