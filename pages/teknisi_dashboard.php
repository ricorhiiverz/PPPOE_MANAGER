<?php
// Pastikan hanya teknisi yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'teknisi') {
    echo '<div class="alert alert-danger">Akses ditolak. Halaman ini hanya untuk teknisi.</div>';
    return;
}

// Data yang dibutuhkan akan diambil di index.php
// $customers_with_reports_coords, $assigned_reports_summary
?>

<!-- Leaflet.js CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    #teknisiMap {
        height: 60vh; /* Sedikit lebih kecil untuk ringkasan laporan */
        width: 100%;
        border-radius: 0.375rem;
        margin-bottom: 1.5rem;
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
    /* Style for custom locate button (no longer needed as a button, but keeping styles if any part of Leaflet uses it) */
    .leaflet-control-locate {
        display: none; /* Hide the control button as it's now automatic */
    }
</style>

<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Total Laporan Ditugaskan</div>
                    <div class="fs-4 fw-bold"><?= $assigned_reports_summary['total'] ?? 0 ?></div>
                </div>
                <i class="fas fa-clipboard-list fa-2x text-primary ms-3"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Laporan Pending</div>
                    <div class="fs-4 fw-bold"><?= $assigned_reports_summary['Pending'] ?? 0 ?></div>
                </div>
                <i class="fas fa-hourglass-half fa-2x text-warning ms-3"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Laporan Dalam Proses</div>
                    <div class="fs-4 fw-bold"><?= $assigned_reports_summary['In Progress'] ?? 0 ?></div>
                </div>
                <i class="fas fa-tools fa-2x text-info ms-3"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Laporan Selesai</div>
                    <div class="fs-4 fw-bold"><?= $assigned_reports_summary['Resolved'] ?? 0 ?></div>
                </div>
                <i class="fas fa-check-circle fa-2x text-success ms-3"></i>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="card-title mb-0 text-white">Peta Lokasi Gangguan Ditugaskan</h5>
    </div>
    <div class="card-body">
        <div id="teknisiMap"></div>
    </div>
</div>

<!-- Leaflet.js JavaScript -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi peta, berpusat di Indonesia
        const map = L.map('teknisiMap').setView([-2.548926, 118.0148634], 5);

        // Tambahkan layer peta dari Esri World Imagery (Satellite)
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 18,
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        }).addTo(map);

        // Ambil data pelanggan dengan laporan dari PHP
        const customersWithReports = <?= json_encode($customers_with_reports_coords ?? []) ?>;

        // Buat ikon kustom untuk status laporan
        const icons = {
            'Pending': L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
            'In Progress': L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
            'Resolved': L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
            'Cancelled': L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-grey.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] })
        };

        const markerBounds = [];

        // Loop melalui data pelanggan dan tambahkan marker ke peta
        customersWithReports.forEach(customer => {
            const coords = customer.coords.split(',').map(coord => parseFloat(coord.trim()));
            if (coords.length === 2 && !isNaN(coords[0]) && !isNaN(coords[1])) {
                const [lat, lng] = coords;
                
                // Pilih ikon berdasarkan status laporan (jika ada laporan)
                const iconToUse = icons[customer.report_status] || icons['Pending']; // Default ke Pending jika status tidak dikenal

                const marker = L.marker([lat, lng], { icon: iconToUse }).addTo(map);
                
                // Tambahkan popup dengan info laporan
                marker.bindPopup(`
                    <b>${customer.name}</b><br>
                    Status Laporan: ${customer.report_status}<br>
                    Deskripsi: ${customer.issue_description}<br>
                    Dilaporkan oleh: ${customer.reported_by}<br>
                    Tanggal Lapor: ${customer.created_at}
                `);

                // Simpan koordinat untuk auto-zoom
                markerBounds.push([lat, lng]);
            }
        });

        // Auto-zoom ke area di mana ada marker
        if (markerBounds.length > 0) {
            map.fitBounds(markerBounds, { padding: [50, 50] });
        }

        // Tambahkan legenda
        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = function(map) {
            const div = L.DomUtil.create('div', 'info legend');
            const statuses = {
                'Pending': '#dc3545', // red
                'In Progress': '#0d6efd', // blue
                'Resolved': '#28a745', // green
                'Cancelled': '#6c757d' // grey
            };
            let legendHtml = '<h6>Status Laporan</h6>';
            for (const status in statuses) {
                legendHtml += `<i style="background:${statuses[status]}"></i> ${status}<br>`;
            }
            div.innerHTML = legendHtml;
            return div;
        };
        legend.addTo(map);

        // Logika lokasi teknisi real-time
        let currentLocationMarker;
        let watchId; // Untuk menyimpan ID dari watchPosition

        // Create a custom icon for current location (e.g., blue dot)
        const blueDotIcon = L.divIcon({
            className: 'custom-div-icon',
            html: '<div style="background-color:#007bff; width: 15px; height: 15px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>',
            iconSize: [15, 15],
            iconAnchor: [7.5, 7.5]
        });

        function updateCurrentLocation(position) {
            const latlng = [position.coords.latitude, position.coords.longitude];

            if (currentLocationMarker) {
                currentLocationMarker.setLatLng(latlng); // Update marker position
            } else {
                currentLocationMarker = L.marker(latlng, {icon: blueDotIcon}).addTo(map)
                    .bindPopup("").openPopup();
            }
            // Optional: Center map on current location after first update
            // map.setView(latlng, map.getZoom() || 16); 
        }

        function handleLocationError(error) {
            let message = "Gagal mendapatkan lokasi Anda.";
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message = "Akses lokasi ditolak. Harap izinkan akses lokasi di browser Anda.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    message = "Informasi lokasi tidak tersedia.";
                    break;
                case error.TIMEOUT:
                    message = "Waktu habis saat mencoba mendapatkan lokasi.";
                    break;
                case error.UNKNOWN_ERROR:
                    message = "Terjadi kesalahan yang tidak diketahui.";
                    break;
            }
            alert(message);
            // Hentikan pelacakan jika ada error
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }
            if (currentLocationMarker) {
                map.removeLayer(currentLocationMarker);
                currentLocationMarker = null;
            }
        }

        // Mulai pelacakan lokasi secara otomatis saat halaman dimuat
        if (navigator.geolocation) {
            // watchPosition akan terus memantau lokasi dan memanggil updateCurrentLocation
            watchId = navigator.geolocation.watchPosition(
                updateCurrentLocation,
                handleLocationError,
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 } // Perbarui setiap 5 detik
            );
            // Optional: Set view to current location initially, but watchPosition will handle ongoing updates
            // map.locate({setView: true, maxZoom: 16}); 
        } else {
            alert('Geolocation tidak didukung oleh browser Anda.');
        }

        // Cleanup function when component is unloaded (important for single-page apps or complex navigations)
        // In a multi-page PHP app, this might not be strictly necessary as page reloads clear JS state,
        // but it's good practice for robust Geolocation API usage.
        window.addEventListener('beforeunload', function() {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
        });
    });
</script>
