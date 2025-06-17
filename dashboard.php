<?php
$pageTitle = "Laporan Keuangan";
include 'header.php';
include 'koneksi.php';

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : 'all';
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

function getDataKategori($conn, $kategori, $tahun, $bulan = null) {
    $sql = "SELECT Uraian AS uraian, SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS total_realisasi 
            FROM laporan l 
            WHERE kategori = ? AND YEAR(input_date) = ? ";
    if ($bulan !== null && $bulan !== 'all') {
        $sql .= " AND MONTH(input_date) = ? ";
    }
    $sql .= " GROUP BY Uraian 
            ORDER BY Uraian";
    $stmt = $conn->prepare($sql);
    if ($bulan !== null && $bulan !== 'all') {
        $stmt->bind_param("sii", $kategori, $tahun, $bulan);
    } else {
        $stmt->bind_param("si", $kategori, $tahun);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $labels = [];
    $values = [];
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['uraian'];
        $values[] = (float)$row['total_realisasi'];
    }
    $stmt->close();
    return [$labels, $values];
}

// New function to get preview data from laporan_preview view


// Fungsi baru untuk mendapatkan data realisasi dan anggaran per uraian
function getDataRealisasiAnggaran($conn, $kategori, $tahun, $bulan = null, $uraianFilter = null) {
    $sql = "SELECT Uraian AS uraian, 
                   SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS total_realisasi, 
                   SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS total_anggaran
            FROM laporan l 
            WHERE kategori = ? AND YEAR(input_date) = ? ";
    $params = [$kategori, $tahun];
    $types = "si";

    if ($bulan !== null && $bulan !== 'all') {
        $sql .= " AND MONTH(input_date) = ? ";
        $params[] = $bulan;
        $types .= "i";
    }

    if ($uraianFilter !== null) {
        if (is_array($uraianFilter)) {
            $sql .= " AND (";
            $likeClauses = [];
            foreach ($uraianFilter as $index => $filter) {
                $likeClauses[] = "Uraian LIKE ?";
                $params[] = "%$filter%";
                $types .= "s";
            }
            $sql .= implode(" OR ", $likeClauses);
            $sql .= ")";
        } else {
            $sql .= " AND Uraian LIKE ? ";
            $params[] = "%$uraianFilter%";
            $types .= "s";
        }
    }
    $sql .= " GROUP BY Uraian ORDER BY Uraian";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $realisasi = [];
    $anggaran = [];
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['uraian'];
        $realisasi[] = (float)$row['total_realisasi'];
        $anggaran[] = (float)$row['total_anggaran'];
    }
    $stmt->close();

    return [$labels, $realisasi, $anggaran];
}

// Fungsi untuk mendapatkan daftar kategori unik dari tabel laporan_keuangan
function getKategoriUnik($conn) {
    $sql = "SELECT DISTINCT kategori FROM laporan ORDER BY kategori";
    $result = $conn->query($sql);
    $kategoriList = [];
    while ($row = $result->fetch_assoc()) {
        $kategoriList[] = $row['kategori'];
    }
    return $kategoriList;
}

// New code to display laporan preview on dashboard
?>




<?php

list($labelsPendapatan, $valuesPendapatan) = getDataKategori($conn, 'pendapatan', $tahun, $bulan);
list($labelsBeban, $valuesBeban) = getDataKategori($conn, 'beban', $tahun, $bulan);

// Data untuk card Pendapatan dan Beban dengan realisasi dan anggaran
list($labelsPendapatanDetail, $realisasiPendapatan, $anggaranPendapatan) = getDataRealisasiAnggaran($conn, 'pendapatan', $tahun, $bulan);
list($labelsBebanDetail, $realisasiBeban, $anggaranBeban) = getDataRealisasiAnggaran($conn, 'beban', $tahun, $bulan);

// Data untuk Kompensasi Pemerintahan (uraian mengandung 'Kompensasi Pemerintahan') dan Perawatan Sarana dan Prasarana (uraian mengandung 'Perawatan Sarana dan Prasarana')
list($labelsKompensasi, $realisasiKompensasi, $anggaranKompensasi) = getDataRealisasiAnggaran($conn, 'pendapatan', $tahun, $bulan, ['Kontribusi Pemerintah sebagai Bentuk Kewajiban Pelayanan Publik (PSO)', 'Kontribusi Pemerintah sebagai Bentuk Subsidi Angkutan Perintis', 'Kontribusi Negara untuk Penyediaan Prasarana (IMO)']);
list($labelsPerawatan, $realisasiPerawatan, $anggaranPerawatan) = getDataRealisasiAnggaran($conn, 'beban', $tahun, $bulan, ['Sarana Perkeretaapian', 'Bangunan (Stasiun & Bangunan Lainnya)', 'Prasarana Perkeretaapian']);

// Prepare data for Laba Rugi line chart (metrics data for selected year)
$labaRugiLabels = ['Realisasi', 'Anggaran', 'Anggaran Tahun', '% Ach', '% Growth', '% Ach (Lalu)', 'Analisis Vertical'];

$metrics = ['REALISASI_TAHUN_INI', 'ANGGARAN_TAHUN_INI', 'ACH_1', 'GRO', 'ACH_2', 'ANALISIS_VERTICAL'];

$pendapatanSums = [];
$bebanSums = [];

$previousYear = $tahun - 1;

$pendapatanSumsPrevYear = [];
$bebanSumsPrevYear = [];

foreach ($metrics as $metric) {
    // Sum for pendapatan current year
    if ($bulan !== null && $bulan !== 'all') {
        $sqlPendapatan = "SELECT SUM(COALESCE($metric,0)) AS total FROM laporan WHERE kategori = 'pendapatan' AND YEAR(input_date) = ? AND MONTH(input_date) = ?";
        $stmtPendapatan = $conn->prepare($sqlPendapatan);
        $stmtPendapatan->bind_param("ii", $tahun, $bulan);
    } else {
        $sqlPendapatan = "SELECT SUM(COALESCE($metric,0)) AS total FROM laporan WHERE kategori = 'pendapatan' AND YEAR(input_date) = ?";
        $stmtPendapatan = $conn->prepare($sqlPendapatan);
        $stmtPendapatan->bind_param("i", $tahun);
    }
    $stmtPendapatan->execute();
    $resultPendapatan = $stmtPendapatan->get_result();
    $rowPendapatan = $resultPendapatan->fetch_assoc();
    $pendapatanSums[$metric] = (float)$rowPendapatan['total'];
    $stmtPendapatan->close();

    // Sum for beban current year
    if ($bulan !== null && $bulan !== 'all') {
        $sqlBeban = "SELECT SUM(COALESCE($metric,0)) AS total FROM laporan WHERE kategori = 'beban' AND YEAR(input_date) = ? AND MONTH(input_date) = ?";
        $stmtBeban = $conn->prepare($sqlBeban);
        $stmtBeban->bind_param("ii", $tahun, $bulan);
    } else {
        $sqlBeban = "SELECT SUM(COALESCE($metric,0)) AS total FROM laporan WHERE kategori = 'beban' AND YEAR(input_date) = ?";
        $stmtBeban = $conn->prepare($sqlBeban);
        $stmtBeban->bind_param("i", $tahun);
    }
    $stmtBeban->execute();
    $resultBeban = $stmtBeban->get_result();
    $rowBeban = $resultBeban->fetch_assoc();
    $bebanSums[$metric] = (float)$rowBeban['total'];
    $stmtBeban->close();

    // Sum for pendapatan previous year
    if ($bulan !== null && $bulan !== 'all') {
        $sqlPendapatanPrev = "SELECT SUM(COALESCE($metric,0)) AS total FROM laporan WHERE kategori = 'pendapatan' AND YEAR(input_date) = ? AND MONTH(input_date) = ?";
        $stmtPendapatanPrev = $conn->prepare($sqlPendapatanPrev);
        $stmtPendapatanPrev->bind_param("ii", $previousYear, $bulan);
    } else {
        $sqlPendapatanPrev = "SELECT SUM(COALESCE($metric,0)) AS total FROM laporan WHERE kategori = 'pendapatan' AND YEAR(input_date) = ?";
        $stmtPendapatanPrev = $conn->prepare($sqlPendapatanPrev);
        $stmtPendapatanPrev->bind_param("i", $previousYear);
    }
    $stmtPendapatanPrev->execute();
    $resultPendapatanPrev = $stmtPendapatanPrev->get_result();
    $rowPendapatanPrev = $resultPendapatanPrev->fetch_assoc();
    $pendapatanSumsPrevYear[$metric] = (float)$rowPendapatanPrev['total'];
    $stmtPendapatanPrev->close();

    // Sum for beban previous year
    if ($bulan !== null && $bulan !== 'all') {
        $sqlBebanPrev = "SELECT SUM(COALESCE($metric,0)) AS total FROM laporan WHERE kategori = 'beban' AND YEAR(input_date) = ? AND MONTH(input_date) = ?";
        $stmtBebanPrev = $conn->prepare($sqlBebanPrev);
        $stmtBebanPrev->bind_param("ii", $previousYear, $bulan);
    } else {
        $sqlBebanPrev = "SELECT SUM(COALESCE($metric,0)) AS total FROM laporan WHERE kategori = 'beban' AND YEAR(input_date) = ?";
        $stmtBebanPrev = $conn->prepare($sqlBebanPrev);
        $stmtBebanPrev->bind_param("i", $previousYear);
    }
    $stmtBebanPrev->execute();
    $resultBebanPrev = $stmtBebanPrev->get_result();
    $rowBebanPrev = $resultBebanPrev->fetch_assoc();
    $bebanSumsPrevYear[$metric] = (float)$rowBebanPrev['total'];
    $stmtBebanPrev->close();

    // Calculate difference pendapatan - beban for current year
    $diff = $pendapatanSums[$metric] - $bebanSums[$metric];
    if ($diff >= 0) {
        $labaValues[] = $diff;
        $rugiValues[] = 0;
    } else {
        $labaValues[] = 0;
        $rugiValues[] = abs($diff);
    }
}

// Prepare previous year laba and rugi values for chart
$labaValuesPrevYear = [];
$rugiValuesPrevYear = [];

foreach ($metrics as $metric) {
    $diffPrev = $pendapatanSumsPrevYear[$metric] - $bebanSumsPrevYear[$metric];
    if ($diffPrev >= 0) {
        $labaValuesPrevYear[] = $diffPrev;
        $rugiValuesPrevYear[] = 0;
    } else {
        $labaValuesPrevYear[] = 0;
        $rugiValuesPrevYear[] = abs($diffPrev);
    }
}

// Calculate anggaran tahun lalu (previous year) sums for pendapatan and beban
$anggaranTahunLaluPendapatan = 0;
$anggaranTahunLaluBeban = 0;

if ($bulan !== null && $bulan !== 'all') {
    $sqlAnggaranTahunLaluPendapatan = "SELECT SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS total FROM laporan WHERE kategori = 'pendapatan' AND YEAR(input_date) = ? AND MONTH(input_date) = ?";
    $stmtAnggaranTahunLaluPendapatan = $conn->prepare($sqlAnggaranTahunLaluPendapatan);
    $stmtAnggaranTahunLaluPendapatan->bind_param("ii", $previousYear, $bulan);
    $stmtAnggaranTahunLaluPendapatan->execute();
    $resultAnggaranTahunLaluPendapatan = $stmtAnggaranTahunLaluPendapatan->get_result();
    $rowAnggaranTahunLaluPendapatan = $resultAnggaranTahunLaluPendapatan->fetch_assoc();
    $anggaranTahunLaluPendapatan = (float)$rowAnggaranTahunLaluPendapatan['total'];
    $stmtAnggaranTahunLaluPendapatan->close();

    $sqlAnggaranTahunLaluBeban = "SELECT SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS total FROM laporan WHERE kategori = 'beban' AND YEAR(input_date) = ? AND MONTH(input_date) = ?";
    $stmtAnggaranTahunLaluBeban = $conn->prepare($sqlAnggaranTahunLaluBeban);
    $stmtAnggaranTahunLaluBeban->bind_param("ii", $previousYear, $bulan);
    $stmtAnggaranTahunLaluBeban->execute();
    $resultAnggaranTahunLaluBeban = $stmtAnggaranTahunLaluBeban->get_result();
    $rowAnggaranTahunLaluBeban = $resultAnggaranTahunLaluBeban->fetch_assoc();
    $anggaranTahunLaluBeban = (float)$rowAnggaranTahunLaluBeban['total'];
    $stmtAnggaranTahunLaluBeban->close();
} else {
    $sqlAnggaranTahunLaluPendapatan = "SELECT SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS total FROM laporan WHERE kategori = 'pendapatan' AND YEAR(input_date) = ?";
    $stmtAnggaranTahunLaluPendapatan = $conn->prepare($sqlAnggaranTahunLaluPendapatan);
    $stmtAnggaranTahunLaluPendapatan->bind_param("i", $previousYear);
    $stmtAnggaranTahunLaluPendapatan->execute();
    $resultAnggaranTahunLaluPendapatan = $stmtAnggaranTahunLaluPendapatan->get_result();
    $rowAnggaranTahunLaluPendapatan = $resultAnggaranTahunLaluPendapatan->fetch_assoc();
    $anggaranTahunLaluPendapatan = (float)$rowAnggaranTahunLaluPendapatan['total'];
    $stmtAnggaranTahunLaluPendapatan->close();

    $sqlAnggaranTahunLaluBeban = "SELECT SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS total FROM laporan WHERE kategori = 'beban' AND YEAR(input_date) = ?";
    $stmtAnggaranTahunLaluBeban = $conn->prepare($sqlAnggaranTahunLaluBeban);
    $stmtAnggaranTahunLaluBeban->bind_param("i", $previousYear);
    $stmtAnggaranTahunLaluBeban->execute();
    $resultAnggaranTahunLaluBeban = $stmtAnggaranTahunLaluBeban->get_result();
    $rowAnggaranTahunLaluBeban = $resultAnggaranTahunLaluBeban->fetch_assoc();
    $anggaranTahunLaluBeban = (float)$rowAnggaranTahunLaluBeban['total'];
    $stmtAnggaranTahunLaluBeban->close();
}

function totalValue($values) {
    return array_sum($values);
}

$tahunMulai = 2020;
$tahunSekarang = 2025;

$namaBulan = [
    1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni',
    7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
];
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Dashboard Keuangan</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>

    <form method="GET" class="filter-form row g-2" style="margin-top: 20px; margin-bottom: 25px;">
        <div class="col-md-3">
            <label for="bulan" class="form-label">Bulan:</label>
            <select name="bulan" id="bulan" class="form-select" required>
                <option value="all" <?= ($bulan == 'all') ? 'selected' : '' ?>>All</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="tahun" class="form-label">Tahun:</label>
            <select name="tahun" id="tahun" class="form-select" required>
        <?php for($t=$tahunMulai; $t<=$tahunSekarang; $t++): ?>
            <option value="<?= $t ?>" <?= ($t == $tahun) ? 'selected' : '' ?>><?= $t ?></option>
        <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-success">Tampilkan</button>
        </div>
    </form>

    <div class="dashboard-grid" style="display: grid; grid-template-columns: 1fr; gap: 12px 20px; margin-top: 20px;">
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Laba Rugi</h5>
            <canvas id="chartLabaRugi" height="200" style="width: 100%; height: 300px;"></canvas>
        </div>
    </div>

    <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px 20px; margin-top: 20px;">
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Pendapatan</h5>
            <canvas id="chartPendapatan" width="450" height="300"></canvas>
        </div>
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Beban</h5>
            <canvas id="chartBeban" width="450" height="300"></canvas>
        </div>
    </div>

    <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px 20px; margin-top: 20px;">
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Kompensasi Pemerintahan</h5>
            <canvas id="chartKompensasi" width="450" height="300"></canvas>
        </div>
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Perawatan Sarana dan Prasarana</h5>
            <canvas id="chartPerawatan" width="450" height="300"></canvas>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
<a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/sidebar-accordion.js"></script>
<script>
const chartInstances = {};

function createBarChart(ctx, labels, dataRealisasi, dataAnggaran, labelRealisasi, labelAnggaran, colorRealisasi, colorAnggaran, totalRealisasi = null, totalAnggaran = null, chartId = null) {
    if (chartId && chartInstances[chartId]) {
        chartInstances[chartId].destroy();
    }
    console.log('createBarChart labels:', labels);
    console.log('createBarChart dataRealisasi:', dataRealisasi);
    console.log('createBarChart dataAnggaran:', dataAnggaran);
    const newChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: labelRealisasi,
                    data: dataRealisasi,
                    backgroundColor: colorRealisasi,
                    borderRadius: 4,
                    barPercentage: 0.4,
                },
                {
                    label: labelAnggaran,
                    data: dataAnggaran,
                    backgroundColor: colorAnggaran,
                    borderRadius: 4,
                    barPercentage: 0.4,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: { enabled: true },
                subtitle: {
                    display: (totalRealisasi !== null && totalAnggaran !== null),
                    text: totalRealisasi !== null && totalAnggaran !== null ? 
                        `Total Realisasi: ${totalRealisasi.toLocaleString()} | Total Anggaran: ${totalAnggaran.toLocaleString()}` : '',
                    font: {
                        size: 14,
                        weight: 'bold'
                    },
                    padding: {
                        bottom: 10
                    }
                }
            },
            scales: {
                y: { beginAtZero: true },
                x: {
                    ticks: {
                        font: {
                            size: 9
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        font: {
                            size: 10
                        }
                    }
                }
            }
        }
    });
    if (chartId) {
        chartInstances[chartId] = newChart;
    }
    return newChart;
}

function createLineChart(ctx, labels, data, label, color) {
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                borderColor: color,
                backgroundColor: color,
                fill: false,
                tension: 0.1,
                pointRadius: 5,
                pointHoverRadius: 7,
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
                tooltip: { enabled: true }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Keterangan'
                    }
                },
                y: {
                    beginAtZero: true,
                    display: true,
                    title: {
                        display: true,
                        text: 'Nominal (Rupiah)'
                    }
                }
            }
        }
    });
}

function isDataAvailable(dataArray) {
    return Array.isArray(dataArray) && dataArray.length > 0 && dataArray.some(value => value > 0);
}

const labaData = <?= json_encode($labaValues) ?>;
const rugiData = <?= json_encode($rugiValues) ?>;
const labaRugiLabels = <?= json_encode($labaRugiLabels) ?>;

const ctx = document.getElementById('chartLabaRugi').getContext('2d');
const labaRugiChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: labaRugiLabels,
        datasets: [
            {
                label: 'Laba',
                data: labaData,
                borderColor: 'green',
                backgroundColor: 'green',
                fill: false,
                tension: 0.1,
                pointRadius: 5,
                pointHoverRadius: 7,
                borderWidth: 3
            },
            {
                label: 'Rugi',
                data: rugiData,
                borderColor: 'darkred',
                backgroundColor: 'darkred',
                fill: false,
                tension: 0.1,
                pointRadius: 5,
                pointHoverRadius: 7,
                borderWidth: 3
            },
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: true },
            tooltip: { enabled: true }
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Keterangan'
                }
            },
            y: {
                beginAtZero: true,
                display: true,
                title: {
                    display: true,
                    text: 'Nominal (Rupiah)'
                }
            }
        }
    }
});

createBarChart(
    document.getElementById('chartPendapatan'),
    <?= json_encode($labelsPendapatanDetail) ?>,
    <?= json_encode($realisasiPendapatan) ?>,
    <?= json_encode($anggaranPendapatan) ?>,
    'Realisasi',
    'Anggaran',
    '#006400',  // hijau
    '#cc5500',  // oren tua
    <?= json_encode(array_sum($realisasiPendapatan)) ?>,
    <?= json_encode(array_sum($anggaranPendapatan)) ?>,
    'chartPendapatan'
);

createBarChart(
    document.getElementById('chartBeban'),
    <?= json_encode($labelsBebanDetail) ?>,
    <?= json_encode($realisasiBeban) ?>,
    <?= json_encode($anggaranBeban) ?>,
    'Realisasi',
    'Anggaran',
    '#006400',  // hijau
    '#cc5500',  // oren tua
    <?= json_encode(array_sum($realisasiBeban)) ?>,
    <?= json_encode(array_sum($anggaranBeban)) ?>,
    'chartBeban'
);

createBarChart(
    document.getElementById('chartKompensasi'),
    <?= json_encode($labelsKompensasi) ?>,
    <?= json_encode($realisasiKompensasi) ?>,
    <?= json_encode($anggaranKompensasi) ?>,
    'Realisasi',
    'Anggaran',
    '#006400',  // hijau
    '#cc5500',  // oren tua
    <?= json_encode(array_sum($realisasiKompensasi)) ?>,
    <?= json_encode(array_sum($anggaranKompensasi)) ?>,
    'chartKompensasi'
);

createBarChart(
    document.getElementById('chartPerawatan'),
    <?= json_encode($labelsPerawatan) ?>,
    <?= json_encode($realisasiPerawatan) ?>,
    <?= json_encode($anggaranPerawatan) ?>,
    'Realisasi',
    'Anggaran',
    '#006400',  // hijau
    '#cc5500',  // oren tua
    <?= json_encode(array_sum($realisasiPerawatan)) ?>,
    <?= json_encode(array_sum($anggaranPerawatan)) ?>,
    'chartPerawatan'
);
</script>
