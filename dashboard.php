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

// Fungsi agregasi rekursif untuk menjumlahkan nilai anak ke parent
function aggregateChildrenValues(array &$elements, $parentId = null) {
    $sumRealisasi = 0;
    $sumAnggaran = 0;
    foreach ($elements as &$element) {
        if ($element['parent_id'] == $parentId) {
            // Rekursif agregasi anak
            $childSums = aggregateChildrenValues($elements, $element['id']);
            // Tambahkan nilai anak ke parent
            $element['REALISASI_TAHUN_INI'] += $childSums['realisasi'];
            $element['ANGGARAN_TAHUN_INI'] += $childSums['anggaran'];
            $sumRealisasi += $element['REALISASI_TAHUN_INI'];
            $sumAnggaran += $element['ANGGARAN_TAHUN_INI'];
        }
    }
    return ['realisasi' => $sumRealisasi, 'anggaran' => $sumAnggaran];
}

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

list($labelsPendapatan, $valuesPendapatan) = getDataKategori($conn, 'pendapatan', $tahun, $bulan);
list($labelsBeban, $valuesBeban) = getDataKategori($conn, 'beban', $tahun, $bulan);

// Query data untuk Pendapatan & Jumlah Penumpang
$sqlPendapatanPenumpang = "SELECT 
    SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS realisasi_pendapatan,
    SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS anggaran_pendapatan
    FROM laporan
    WHERE kategori = 'pendapatan' AND (Uraian LIKE '%Penumpang%' OR Uraian LIKE '%Penumpang KA%') AND YEAR(input_date) = ? AND (MONTH(input_date) = ? OR ? = 'all')";
$stmtPendapatanPenumpang = $conn->prepare($sqlPendapatanPenumpang);
if (!$stmtPendapatanPenumpang) {
    error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
} else {
    $stmtPendapatanPenumpang->bind_param("iis", $tahun, $bulan, $bulan);
    $stmtPendapatanPenumpang->execute();
    $resultPendapatanPenumpang = $stmtPendapatanPenumpang->get_result();
    $dataPendapatanPenumpang = $resultPendapatanPenumpang->fetch_assoc();
    $stmtPendapatanPenumpang->close();
}

// Query data untuk Pendapatan & Volume Barang
$sqlPendapatanVolume = "SELECT 
    SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS realisasi_pendapatan,
    SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS anggaran_pendapatan
    FROM laporan
    WHERE kategori = 'pendapatan' AND (Uraian LIKE '%Barang%' OR Uraian LIKE '%Volume%') AND YEAR(input_date) = ? AND (MONTH(input_date) = ? OR ? = 'all')";
$stmtPendapatanVolume = $conn->prepare($sqlPendapatanVolume);
if (!$stmtPendapatanVolume) {
    error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
} else {
    $stmtPendapatanVolume->bind_param("iis", $tahun, $bulan, $bulan);
    $stmtPendapatanVolume->execute();
    $resultPendapatanVolume = $stmtPendapatanVolume->get_result();
    $dataPendapatanVolume = $resultPendapatanVolume->fetch_assoc();
    $stmtPendapatanVolume->close();
}

// Query data untuk Pendapatan Asset
$sqlPendapatanAsset = "SELECT 
    SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS realisasi_kinerja,
    SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS anggaran_kinerja,
    SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS realisasi_pendapatan,
    SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS anggaran_pendapatan
    FROM laporan
    WHERE kategori = 'pendapatan' AND (Uraian LIKE '%Kinerja Kontrak%' OR Uraian LIKE '%Pendapatan%') AND YEAR(input_date) = ? AND (MONTH(input_date) = ? OR ? = 'all')";
$stmtPendapatanAsset = $conn->prepare($sqlPendapatanAsset);
if (!$stmtPendapatanAsset) {
    error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
} else {
    $stmtPendapatanAsset->bind_param("iis", $tahun, $bulan, $bulan);
    $stmtPendapatanAsset->execute();
    $resultPendapatanAsset = $stmtPendapatanAsset->get_result();
    $dataPendapatanAsset = $resultPendapatanAsset->fetch_assoc();
    $stmtPendapatanAsset->close();
}

// Query data untuk Beban Asset (baru)
$sqlBebanAsset = "SELECT 
    SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS realisasi_beban,
    SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS anggaran_beban
    FROM laporan
    WHERE kategori = 'beban' AND (Uraian LIKE '%Kinerja Kontrak%' OR Uraian LIKE '%Pendapatan%') AND YEAR(input_date) = ? AND (MONTH(input_date) = ? OR ? = 'all')";
$stmtBebanAsset = $conn->prepare($sqlBebanAsset);
if (!$stmtBebanAsset) {
    error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
} else {
    $stmtBebanAsset->bind_param("iis", $tahun, $bulan, $bulan);
    $stmtBebanAsset->execute();
    $resultBebanAsset = $stmtBebanAsset->get_result();
    $dataBebanAsset = $resultBebanAsset->fetch_assoc();
    $stmtBebanAsset->close();
}

// Query total realisasi and anggaran for Biaya Keselamatan
$sqlBiayaKeselamatanTotal = "SELECT 
    SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS total_realisasi, 
    SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS total_anggaran
    FROM laporan
    WHERE kategori = 'biaya keselamatan' AND YEAR(input_date) = ? AND (MONTH(input_date) = ? OR ? = 'all')";
$stmtBiayaKeselamatanTotal = $conn->prepare($sqlBiayaKeselamatanTotal);
$stmtBiayaKeselamatanTotal->bind_param("iis", $tahun, $bulan, $bulan);
$stmtBiayaKeselamatanTotal->execute();
$resultBiayaKeselamatanTotal = $stmtBiayaKeselamatanTotal->get_result();
$dataBiayaKeselamatanTotal = $resultBiayaKeselamatanTotal->fetch_assoc();
$stmtBiayaKeselamatanTotal->close();

// Query consumption per unit for Biaya Keselamatan
$sqlBiayaKeselamatanUnits = "SELECT Uraian AS uraian, 
    SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS realisasi, 
    SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS anggaran
    FROM laporan
    WHERE kategori = 'biaya keselamatan' AND YEAR(input_date) = ? AND (MONTH(input_date) = ? OR ? = 'all')
    GROUP BY Uraian ORDER BY Uraian";
$stmtBiayaKeselamatanUnits = $conn->prepare($sqlBiayaKeselamatanUnits);
$stmtBiayaKeselamatanUnits->bind_param("iis", $tahun, $bulan, $bulan);
$stmtBiayaKeselamatanUnits->execute();
$resultBiayaKeselamatanUnits = $stmtBiayaKeselamatanUnits->get_result();

$labelsBiayaKeselamatan = [];
$realisasiBiayaKeselamatan = [];
$anggaranBiayaKeselamatan = [];

while ($row = $resultBiayaKeselamatanUnits->fetch_assoc()) {
    $labelsBiayaKeselamatan[] = $row['uraian'];
    $realisasiBiayaKeselamatan[] = (float)$row['realisasi'];
    $anggaranBiayaKeselamatan[] = (float)$row['anggaran'];
}
$stmtBiayaKeselamatanUnits->close();

// Calculate percentage for display
$percentageBiayaKeselamatan = 0;
if ($dataBiayaKeselamatanTotal['total_anggaran'] > 0) {
    $percentageBiayaKeselamatan = ($dataBiayaKeselamatanTotal['total_realisasi'] / $dataBiayaKeselamatanTotal['total_anggaran']) * 100;
}


$whereClauses = ["tahun = ?"];
$params = [$tahun];
$paramTypes = "i";

if ($bulan !== null && $bulan !== 'all') {
    $whereClauses[] = "bulan = ?";
    $params[] = (int)$bulan;
    $paramTypes .= "i";
}

// Ambil data lengkap pendapatan
$sqlPendapatan = "SELECT * FROM laporan WHERE kategori = 'pendapatan' AND " . implode(" AND ", $whereClauses) . " ORDER BY id ASC";
$stmtPendapatan = $conn->prepare($sqlPendapatan);
$stmtPendapatan->bind_param($paramTypes, ...$params);
$stmtPendapatan->execute();
$resultPendapatan = $stmtPendapatan->get_result();

$laporanPendapatan = [];
while ($row = $resultPendapatan->fetch_assoc()) {
    $laporanPendapatan[] = $row;
}
$stmtPendapatan->close();

// Agregasi data pendapatan dari anak ke parent
aggregateChildrenValues($laporanPendapatan);

// Siapkan data untuk chart pendapatan
$labelsPendapatan = [];
$valuesPendapatan = [];
foreach ($laporanPendapatan as $item) {
    if ($item['parent_id'] === null || $item['parent_id'] === '' || $item['parent_id'] === 0) {
        $labelsPendapatan[] = $item['Uraian'];
        $valuesPendapatan[] = $item['REALISASI_TAHUN_INI'];
    }
}

// Ambil data lengkap beban
$sqlBeban = "SELECT * FROM laporan WHERE kategori = 'beban' AND " . implode(" AND ", $whereClauses) . " ORDER BY id ASC";
$stmtBeban = $conn->prepare($sqlBeban);
$stmtBeban->bind_param($paramTypes, ...$params);
$stmtBeban->execute();
$resultBeban = $stmtBeban->get_result();

$laporanBeban = [];
while ($row = $resultBeban->fetch_assoc()) {
    $laporanBeban[] = $row;
}
$stmtBeban->close();

// Agregasi data beban dari anak ke parent
aggregateChildrenValues($laporanBeban);

// Siapkan data untuk chart beban
$labelsBeban = [];
$valuesBeban = [];
foreach ($laporanBeban as $item) {
    if ($item['parent_id'] === null || $item['parent_id'] === '' || $item['parent_id'] === 0) {
        $labelsBeban[] = $item['Uraian'];
        $valuesBeban[] = $item['REALISASI_TAHUN_INI'];
    }
}

// Debugging: log data chart pendapatan dan beban
error_log("DEBUG: labelsPendapatan: " . json_encode($labelsPendapatan));
error_log("DEBUG: valuesPendapatan: " . json_encode($valuesPendapatan));
error_log("DEBUG: labelsBeban: " . json_encode($labelsBeban));
error_log("DEBUG: valuesBeban: " . json_encode($valuesBeban));

// Data untuk Kompensasi Pemerintahan (uraian mengandung 'Kompensasi Pemerintahan') dan Perawatan Sarana dan Prasarana (uraian mengandung 'Perawatan Sarana dan Prasarana')
list($labelsKompensasi, $realisasiKompensasi, $anggaranKompensasi) = getDataRealisasiAnggaran($conn, 'pendapatan', $tahun, $bulan, ['Kontribusi Pemerintah sebagai Bentuk Kewajiban Pelayanan Publik (PSO)', 'Kontribusi Pemerintah sebagai Bentuk Subsidi Angkutan Perintis', 'Kontribusi Negara untuk Penyediaan Prasarana (IMO)']);
list($labelsPerawatan, $realisasiPerawatan, $anggaranPerawatan) = getDataRealisasiAnggaran($conn, 'beban', $tahun, $bulan, ['Sarana Perkeretaapian', 'Bangunan (Stasiun & Bangunan Lainnya)', 'Prasarana Perkeretaapian']);

$filterBulanForLabaRugi = 'all'; // Disable month filter for laba rugi to always show data

// Prepare data for Laba Rugi per month for selected year
$labaRugiLabels = [];
$labaValues = [];
$ach1Values = [];
$groValues = [];
$ach2Values = [];

for ($m = 1; $m <= 12; $m++) {
    $monthLabel = sprintf("M%02d", $m);
    $labaRugiLabels[] = $monthLabel;

    // Query pendapatan and beban for each metric per month
    $sqlPendapatan = "SELECT 
        SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS realisasi,
        SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS anggaran,
        SUM(COALESCE(ANGGARAN_PER_TAHUN,0)) AS anggaran_per_tahun
        FROM laporan WHERE kategori = 'pendapatan' AND YEAR(input_date) = ? AND MONTH(input_date) = ?";
    $stmtPendapatan = $conn->prepare($sqlPendapatan);
    $stmtPendapatan->bind_param("ii", $tahun, $m);
    $stmtPendapatan->execute();
    $resultPendapatan = $stmtPendapatan->get_result();
    $pendapatanData = $resultPendapatan->fetch_assoc();
    $stmtPendapatan->close();

    $sqlBeban = "SELECT 
        SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS realisasi,
        SUM(COALESCE(ANGGARAN_TAHUN_INI,0)) AS anggaran,
        SUM(COALESCE(ANGGARAN_PER_TAHUN,0)) AS anggaran_per_tahun
        FROM laporan WHERE kategori = 'beban' AND YEAR(input_date) = ? AND MONTH(input_date) = ?";
    $stmtBeban = $conn->prepare($sqlBeban);
    $stmtBeban->bind_param("ii", $tahun, $m);
    $stmtBeban->execute();
    $resultBeban = $stmtBeban->get_result();
    $bebanData = $resultBeban->fetch_assoc();
    $stmtBeban->close();

    // Calculate laba rugi for the month
    $labaRugiRealisasi = ($pendapatanData['realisasi'] ?? 0) - ($bebanData['realisasi'] ?? 0);
    $labaRugiAnggaran = ($pendapatanData['anggaran'] ?? 0) - ($bebanData['anggaran'] ?? 0);
    $labaRugiAnggaranPerTahun = ($pendapatanData['anggaran_per_tahun'] ?? 0) - ($bebanData['anggaran_per_tahun'] ?? 0);

    $labaValues[] = $labaRugiRealisasi;

    // Calculate Ach 1 = Realisasi / Anggaran Tahun Ini * 100
    $ach1 = ($labaRugiAnggaran != 0) ? ($labaRugiRealisasi / $labaRugiAnggaran) * 100 : 0;
    $ach1Values[] = round($ach1, 2);

    // Calculate Ach 2 = Realisasi / Anggaran Per Tahun * 100
    $ach2 = ($labaRugiAnggaranPerTahun != 0) ? ($labaRugiRealisasi / $labaRugiAnggaranPerTahun) * 100 : 0;
    $ach2Values[] = round($ach2, 2);

    // Calculate Growth (GRO) = (Realisasi Tahun Ini - Realisasi Tahun Lalu) / Realisasi Tahun Lalu * 100
    $sqlPrevYear = "SELECT 
        SUM(COALESCE(REALISASI_TAHUN_INI,0)) AS realisasi
        FROM laporan WHERE kategori IN ('pendapatan', 'beban') AND YEAR(input_date) = ? AND MONTH(input_date) = ?";
    $stmtPrevYear = $conn->prepare($sqlPrevYear);
    $prevYear = $tahun - 1;
    $stmtPrevYear->bind_param("ii", $prevYear, $m);
    $stmtPrevYear->execute();
    $resultPrevYear = $stmtPrevYear->get_result();
    $prevYearData = $resultPrevYear->fetch_assoc();
    $stmtPrevYear->close();

    $prevRealisasi = $prevYearData['realisasi'] ?? 0;
    $growth = ($prevRealisasi != 0) ? (($labaRugiRealisasi - $prevRealisasi) / $prevRealisasi) * 100 : 0;
    $groValues[] = round($growth, 2);
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
                <?php
                for ($m = 1; $m <= 12; $m++) {
                    $selected = ($bulan == $m) ? 'selected' : '';
                    echo "<option value=\"$m\" $selected>" . $namaBulan[$m] . "</option>";
                }
                ?>
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
        <div id="labaRugiSummary" style="margin-bottom: 10px; font-size: 14px; font-weight: bold;"></div>
        <div id="labaRugiBadges" style="margin-bottom: 15px; display: flex; gap: 20px; justify-content: center; align-items: center;"></div>
        <canvas id="chartLabaRugi" height="200" style="width: 100%; height: 300px;"></canvas>
    </div>
    </div>
        <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px 20px; margin-top: 20px;">
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Pendapatan</h5>
            <div id="pendapatanBadges" style="margin-bottom: 10px; display: flex; gap: 10px; justify-content: center; align-items: center;"></div>
            <canvas id="chartPendapatan" width="450" height="300"></canvas>
        </div>
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Beban</h5>
            <div id="bebanBadges" style="margin-bottom: 10px; display: flex; gap: 10px; justify-content: center; align-items: center;"></div>
            <canvas id="chartBeban" width="450" height="300"></canvas>
        </div>
    </div>

    <!-- Tambahan chart baru di bawah chart Pendapatan dan Beban -->
    <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px 20px; margin-top: 40px;">
        <div class="card" style="padding: 20px;">
            <h5 class="card-title" style="text-align: center;">Pendapatan & Jumlah Penumpang</h5>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <div>
                    <div>Total Pendapatan</div>
                    <div style="font-weight: bold; font-size: 1.2rem;">Rp2,457.43M</div>
                    <div style="color: green;">▲ 3.51% yoy</div>
                </div>
                <div>
                    <div>Total Penumpang</div>
                    <div style="font-weight: bold; font-size: 1.2rem;">9,010,138</div>
                    <div style="color: red;">▼ 5.43% yoy</div>
                </div>
            </div>
            <canvas id="chartPendapatanPenumpang" width="400" height="250"></canvas>
            <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">Sumber : Rail Ticket System</div>
        </div>
        <div class="card" style="padding: 20px;">
            <h5 class="card-title" style="text-align: center;">Pendapatan & Volume Barang</h5>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <div>
                    <div>Total Pendapatan</div>
                    <div style="font-weight: bold; font-size: 1.2rem;">Rp155.52M</div>
                    <div style="color: red;">▼ 51.53% yoy</div>
                </div>
                <div>
                    <div>Total Volume</div>
                    <div style="font-weight: bold; font-size: 1.2rem;">2,522,249 Ton</div>
                    <div style="color: red;">▼ 19.03% yoy</div>
                </div>
            </div>
            <canvas id="chartPendapatanVolume" width="400" height="250"></canvas>
            <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">Sumber : Rail Cargo System</div>
        </div>
        <div class="card" style="padding: 20px;">
            <h5 class="card-title" style="text-align: center;">Pendapatan Asset</h5>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <div>
                    <div>Kinerja Kontrak</div>
                    <div style="font-weight: bold; font-size: 1.2rem;">Rp257.80M</div>
                    <div style="color: red;">▼ 59.00% yoy</div>
                </div>
                <div>
                    <div>Total Pendapatan</div>
                    <div style="font-weight: bold; font-size: 1.2rem;">Rp263.73M</div>
                    <div style="color: red;">▼ 40.59% yoy</div>
                </div>
            </div>
            <canvas id="chartPendapatanAsset" width="400" height="250"></canvas>
            <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">Sumber : Portal Asset</div>
        </div>
    </div>

    <!-- START OF MOVED SECTION: Biaya Keselamatan -->
    <div class="dashboard-grid" style="display: grid; grid-template-columns: 1fr 3fr; gap: 12px 20px; margin-top: 40px;">
        <div class="card" style="padding: 20px; text-align: center;">
            <h5 class="card-title">Biaya Keselamatan</h5>
            <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;"><?= number_format($percentageBiayaKeselamatan, 2) ?>%</div>
            <div style="font-size: 1rem; color: #666; margin-bottom: 10px;">
                Rp<?= number_format($dataBiayaKeselamatanTotal['total_realisasi'], 2, ',', '.') ?> / Rp<?= number_format($dataBiayaKeselamatanTotal['total_anggaran'], 2, ',', '.') ?>
            </div>
        </div>
        <div class="card" style="padding: 20px;">
            <canvas id="chartBiayaKeselamatan" width="600" height="250"></canvas>
        </div>
    </div>

    <script>
    const ctxBiayaKeselamatan = document.getElementById('chartBiayaKeselamatan').getContext('2d');
    const chartBiayaKeselamatan = new Chart(ctxBiayaKeselamatan, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsBiayaKeselamatan) ?>,
            datasets: [
                {
                    label: 'Realisasi',
                    data: <?= json_encode($realisasiBiayaKeselamatan) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderRadius: 4,
                    barPercentage: 0.5,
                },
                {
                    label: 'Anggaran',
                    data: <?= json_encode($anggaranBiayaKeselamatan) ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    borderRadius: 4,
                    barPercentage: 0.5,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            size: 12
                        }
                    }
                },
                datalabels: {
                    anchor: 'end',
                    align: 'end',
                    color: '#444',
                    font: {
                        weight: 'bold',
                        size: 12
                    },
                    formatter: function(value) {
                        let num = typeof value === 'string' ? parseFloat(value) : value;
                        if (num >= 1e12) {
                            return 'Rp' + (num / 1e12).toFixed(2) + 'T';
                        } else if (num >= 1e9) {
                            return 'Rp' + (num / 1e9).toFixed(2) + 'M';
                        } else if (num >= 1e6) {
                            return 'Rp' + (num / 1e6).toFixed(2) + 'Jt';
                        } else if (num >= 1e3) {
                            return 'Rp' + (num / 1e3).toFixed(2) + 'Rb';
                        } else {
                            return 'Rp' + num.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    display: true,
                    ticks: {
                        callback: function(value) {
                            let num = typeof value === 'string' ? parseFloat(value) : value;
                            if (num >= 1e12) {
                                return 'Rp' + (num / 1e12).toFixed(2) + 'T';
                            } else if (num >= 1e9) {
                                return 'Rp' + (num / 1e9).toFixed(2) + 'M';
                            } else if (num >= 1e6) {
                                return 'Rp' + (num / 1e6).toFixed(2) + 'Jt';
                            } else if (num >= 1e3) {
                                return 'Rp' + (num / 1e3).toFixed(2) + 'Rb';
                            } else {
                                return 'Rp' + num.toFixed(2);
                            }
                        }
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 10
                        }
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
    </script>
    <!-- END OF MOVED SECTION: Biaya Keselamatan -->

    <?php
    $filter_bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
    $filter_tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

    $totalProjects = 0;
    $totalContractValue = 0;
    $prasaranaCount = 0;
    $bangunanCount = 0;
    $statusCounts = [
        'in progress' => 0,
        'completed' => 0,
        'administrative' => 0,
    ];

    // Build query with filters
    $sqlInvestasi = "SELECT * FROM investasi";
    $conditions = [];
    $params = [];
    $param_types = '';

    if ($filter_bulan > 0) {
        $conditions[] = "MONTH(input_date) = ?";
        $params[] = $filter_bulan;
        $param_types .= 'i';
    }

    if ($filter_tahun > 0) {
        $conditions[] = "YEAR(input_date) = ?";
        $params[] = $filter_tahun;
        $param_types .= 'i';
    }

    if (!empty($search_query)) {
        $conditions[] = "(uraian LIKE ? OR wbs LIKE ? OR lokasi_pengadaan LIKE ?)";
        $search_param = '%' . $search_query . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= 'sss';
    }

    if (count($conditions) > 0) {
        $sqlInvestasi .= " WHERE " . implode(" AND ", $conditions);
    }

    $sqlInvestasi .= " ORDER BY id ASC";

    if (count($params) > 0) {
        $stmt = $conn->prepare($sqlInvestasi);
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $resultInvestasi = $stmt->get_result();
    } else {
        $resultInvestasi = $conn->query($sqlInvestasi);
    }

    if ($resultInvestasi) {
        $totalProjects = $resultInvestasi->num_rows;
        while ($row = $resultInvestasi->fetch_assoc()) {
            $totalContractValue += floatval($row['nilai_kontrak']);
            $uraianLower = strtolower($row['uraian']);
            $progresLower = strtolower($row['progres_saat_ini']);

            // Check for prasarana or bangunan in uraian
            if (strpos($uraianLower, 'prasarana') !== false) {
                $prasaranaCount++;
            } elseif (strpos($uraianLower, 'bangunan') !== false) {
                $bangunanCount++;
            }

            // Count status
            if (strpos($progresLower, 'in progress') !== false) {
                $statusCounts['in progress']++;
            } elseif (strpos($progresLower, 'completed') !== false) {
                $statusCounts['completed']++;
            } elseif (strpos($progresLower, 'administrative') !== false) {
                $statusCounts['administrative']++;
            }
        }
    }
    
    // Calculate percentages
    $prasaranaPercent = $totalProjects > 0 ? ($prasaranaCount / $totalProjects) * 100 : 0;
    $bangunanPercent = $totalProjects > 0 ? ($bangunanCount / $totalProjects) * 100 : 0;

    $statusPercentages = [];
    foreach ($statusCounts as $key => $count) {
        $statusPercentages[$key] = $totalProjects > 0 ? ($count / $totalProjects) * 100 : 0;
    }
    ?>

    <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px 20px; margin-top: 20px;">
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Overview Project Investasi</h5>
            <p style="font-size: 2rem; font-weight: bold; margin-bottom: 0;"><?= $totalProjects ?></p>
            <p>Total Project</p>
            <p>Pembayaran / Nilai Kontrak</p>
            <p><?= number_format($totalContractValue, 0, ',', '.') ?></p>
            <div style="background-color: #d4edda; border-radius: 4px; height: 20px; width: 100%; margin-top: 10px;">
                <div style="background-color: #28a745; width: <?= round($totalContractValue > 0 ? 100 : 0) ?>%; height: 100%; border-radius: 4px;"></div>
            </div>
        </div>
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Project Investasi Prasarana</h5>
            <p style="font-weight: bold; margin-bottom: 0;"><?= $prasaranaCount ?> Total Project</p>
            <p>(<?= number_format($prasaranaPercent, 2) ?>% dari total project)</p>
            <div style="background-color: #d4edda; border-radius: 4px; height: 20px; width: 100%; margin-top: 10px;">
                <div style="background-color: #28a745; width: <?= round($prasaranaPercent) ?>%; height: 100%; border-radius: 4px;"></div>
            </div>
        </div>
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Project Investasi Bangunan</h5>
            <p style="font-weight: bold; margin-bottom: 0;"><?= $bangunanCount ?> Total Project</p>
            <p>(<?= number_format($bangunanPercent, 2) ?>% dari total project)</p>
            <div style="background-color: #d4edda; border-radius: 4px; height: 20px; width: 100%; margin-top: 10px;">
                <div style="background-color: #28a745; width: <?= round($bangunanPercent) ?>%; height: 100%; border-radius: 4px;"></div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px 20px; margin-top: 20px;">
        <div class="card" style="padding: 20px;">
            <h5 class="card-title">Status Project Investasi</h5>
            <?php foreach ($statusCounts as $status => $count): ?>
                <div style="margin-bottom: 10px;">
                    <p style="margin: 0; font-weight: bold;"><?= ucfirst($status) ?> (<?= $count ?>)</p>
                    <div style="background-color: #d4edda; border-radius: 4px; height: 20px; width: 100%;">
                        <div style="background-color: #28a745; width: <?= round($statusPercentages[$status]) ?>%; height: 100%; border-radius: 4px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    // Fetch detailed investasi project data for dashboard
    $sqlDetailInvestasi = "SELECT uraian, ket, progres_saat_ini, nilai_kontrak, commitment, actual, budget_tahun_2024, tambahan_dana FROM investasi ORDER BY id ASC";
    $resultDetailInvestasi = $conn->query($sqlDetailInvestasi);
    ?>

<div class="dashboard-grid" style="margin-top: 20px; padding-right: 20px; max-width: calc(100vw - 250px); overflow-x: auto;">
    <div class="card" style="padding: 20px; overflow-x: auto;">
        <h5 class="card-title">Rincian Project Investasi</h5>
        <table class="table table-bordered table-striped align-middle text-center" style="min-width: 1000px;">
            <thead style="background-color: #4CAF50; color: white;">
                <tr>
                    <th>Final</th>
                    <th>Vendor</th>
                    <th>Status</th>
                    <th>Administrative</th>
                    <th>Nilai Kontrak</th>
                    <th>Total Pembayaran</th>
                    <th>Progress Pembayaran</th>
                    <th>Rencana</th>
                    <th>Realisasi</th>
                    <th>Deviasi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultDetailInvestasi && $resultDetailInvestasi->num_rows > 0): ?>
                    <?php while ($row = $resultDetailInvestasi->fetch_assoc()): ?>
                        <?php
                        $totalPembayaran = floatval($row['commitment']) + floatval($row['actual']);
                        $progressPembayaran = $row['nilai_kontrak'] > 0 ? ($totalPembayaran / $row['nilai_kontrak']) * 100 : 0;
                        $rencana = $row['budget_tahun_2024'] + $row['tambahan_dana'];
                        $realisasi = $totalPembayaran;
                        $deviasi = $rencana > 0 ? (($realisasi - $rencana) / $rencana) * 100 : 0;
                        ?>
                        <tr>
                            <td class="text-start"><?= nl2br(htmlspecialchars($row['uraian'])) ?></td>
                            <td><!-- Vendor data not available --></td>
                            <td><?= htmlspecialchars($row['progres_saat_ini']) ?></td>
                            <td><?= htmlspecialchars($row['ket']) ?></td>
                            <td class="text-end"><?= number_format($row['nilai_kontrak'], 0, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($totalPembayaran, 0, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($progressPembayaran, 2) ?>%</td>
                            <td class="text-end"><?= number_format($rencana, 0, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($realisasi, 0, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($deviasi, 2) ?>%</td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10">No data available</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<script>
const ctxBiayaKeselamatan = document.getElementById('chartBiayaKeselamatan').getContext('2d');
const chartBiayaKeselamatan = new Chart(ctxBiayaKeselamatan, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelsBiayaKeselamatan) ?>,
        datasets: [
            {
                label: 'Realisasi',
                data: <?= json_encode($realisasiBiayaKeselamatan) ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderRadius: 4,
                barPercentage: 0.5,
            },
            {
                label: 'Anggaran',
                data: <?= json_encode($anggaranBiayaKeselamatan) ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderRadius: 4,
                barPercentage: 0.5,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    font: {
                        size: 12
                    }
                }
            },
            datalabels: {
                anchor: 'end',
                align: 'end',
                color: '#444',
                font: {
                    weight: 'bold',
                    size: 12
                },
                formatter: function(value) {
                    let num = typeof value === 'string' ? parseFloat(value) : value;
                    if (num >= 1e12) {
                        return 'Rp' + (num / 1e12).toFixed(2) + 'T';
                    } else if (num >= 1e9) {
                        return 'Rp' + (num / 1e9).toFixed(2) + 'M';
                    } else if (num >= 1e6) {
                        return 'Rp' + (num / 1e6).toFixed(2) + 'Jt';
                    } else if (num >= 1e3) {
                        return 'Rp' + (num / 1e3).toFixed(2) + 'Rb';
                    } else {
                        return 'Rp' + num.toFixed(2);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                display: true,
                ticks: {
                    callback: function(value) {
                        let num = typeof value === 'string' ? parseFloat(value) : value;
                        if (num >= 1e12) {
                            return 'Rp' + (num / 1e12).toFixed(2) + 'T';
                        } else if (num >= 1e9) {
                            return 'Rp' + (num / 1e9).toFixed(2) + 'M';
                        } else if (num >= 1e6) {
                            return 'Rp' + (num / 1e6).toFixed(2) + 'Jt';
                        } else if (num >= 1e3) {
                            return 'Rp' + (num / 1e3).toFixed(2) + 'Rb';
                        } else {
                            return 'Rp' + num.toFixed(2);
                        }
                    }
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 10
                    }
                }
            }
        }
    },
    plugins: [ChartDataLabels]
});
</script>

<?php include 'footer.php'; ?>
<a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
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
                    barPercentage: 0.6,
                },
                {
                    label: labelAnggaran,
                    data: dataAnggaran,
                    backgroundColor: colorAnggaran,
                    borderRadius: 4,
                    barPercentage: 0.6,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: { enabled: true },
                datalabels: {
                    display: true,
                    color: 'black',
                    anchor: 'end',
                    align: 'top',
                    offset: -4,
                    formatter: function(value) {
                        return Math.round(value);
                    },
                    font: {
                        weight: 'bold',
                        size: 12
                    }
                },
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
                },
                datalabels: {
                    anchor: 'end',
                    align: 'end',
                    color: '#444',
                    font: {
                        weight: 'bold',
                        size: 12
                    },
                    formatter: function(value) {
                        console.log('Datalabel formatter value:', value);
                        let num = typeof value === 'string' ? parseFloat(value) : value;
                        if (num >= 1e12) {
                            return 'Rp' + (num / 1e12).toFixed(2) + 'T';
                        } else if (num >= 1e9) {
                            return 'Rp' + (num / 1e9).toFixed(2) + 'M';
                        } else if (num >= 1e6) {
                            return 'Rp' + (num / 1e6).toFixed(2) + 'Jt';
                        } else if (num >= 1e3) {
                            return 'Rp' + (num / 1e3).toFixed(2) + 'Rb';
                        } else {
                            return 'Rp' + num.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    display: true
                },
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
                    display: false,
                    labels: {
                        font: {
                            size: 10
                        }
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
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

let labaData = <?= json_encode($labaValues) ?>;
const ach1Data = <?= json_encode($ach1Values) ?>;
const groData = <?= json_encode($groValues) ?>;
const ach2Data = <?= json_encode($ach2Values) ?>;
let labaRugiLabels = <?= json_encode($labaRugiLabels) ?>;

const ctx = document.getElementById('chartLabaRugi').getContext('2d');

if (!Array.isArray(labaRugiLabels) || labaRugiLabels.length === 0 || !Array.isArray(labaData) || labaData.length === 0) {
    // Jika data kosong, buat data dummy agar chart tetap tampil
    labaRugiLabels = ['No Data'];
    labaData = [0];
}

const labaRugiChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: labaRugiLabels,
        datasets: [
            {
                label: 'Laba Rugi',
                data: labaData,
                borderColor: 'green',
                backgroundColor: 'green',
                fill: false,
                tension: 0.1,
                pointRadius: 5,
                pointHoverRadius: 7,
                borderWidth: 3
            }
        ]
    },
        options: {
            responsive: true,
            interaction: {
                mode: 'nearest',
                intersect: false
            },
            stacked: false,
            plugins: {
                legend: { display: true },
                tooltip: { 
                    enabled: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                if (context.dataset.yAxisID === 'y1') {
                                    label += context.parsed.y.toFixed(2) + '%';
                                } else {
                                    label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
                                }
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Nominal (Rupiah)'
                    },
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    },
                    title: {
                        display: true,
                        text: 'Persentase (%)'
                    },
                    min: 0,
                    max: 150,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                },
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Bulan'
                    }
                }
            }
        }
    });

// Display summary above chart
const labaRugiSummary = document.getElementById('labaRugiSummary');
const previousLabaRugi = <?= json_encode(number_format($anggaranTahunLaluPendapatan - $anggaranTahunLaluBeban, 2, ',', '.')) ?>;
const programBerjalan = <?= json_encode(number_format(array_sum($labaValues), 2, ',', '.')) ?>;
const programSatuTahun = <?= json_encode(number_format(array_sum($ach2Values), 2, ',', '.')) ?>;

labaRugiSummary.innerHTML = `
    <div>
        Previous Laba/Rugi: <span style="color: green;">Rp${previousLabaRugi}</span> &nbsp;|&nbsp;
        Program Berjalan: <span style="color: green;">Rp${programBerjalan}</span> &nbsp;|&nbsp;
        Program 1 Tahun: <span style="color: green;">Rp${programSatuTahun}</span>
    </div>
`;

// Tambahkan badge persentase growth dan achievement di div labaRugiBadges
const labaRugiBadges = document.getElementById('labaRugiBadges');

// Ambil nilai terakhir dari data growth dan achievement
const lastGrowth = groData.length > 0 ? groData[groData.length - 1] : 0;
const lastAch1 = ach1Data.length > 0 ? ach1Data[ach1Data.length - 1] : 0;
const lastAch2 = ach2Data.length > 0 ? ach2Data[ach2Data.length - 1] : 0;

// Fungsi untuk membuat elemen badge
function createBadge(text, icon = '', bgColor = '#198754') {
    const badge = document.createElement('div');
    badge.style.backgroundColor = bgColor;
    badge.style.color = 'white';
    badge.style.padding = '6px 12px';
    badge.style.borderRadius = '12px';
    badge.style.fontWeight = 'bold';
    badge.style.display = 'flex';
    badge.style.alignItems = 'center';
    badge.style.gap = '6px';
    badge.style.fontSize = '14px';
    badge.style.minWidth = '100px';
    badge.style.justifyContent = 'center';
    badge.innerHTML = `${icon} ${text}`;
    return badge;
}

// Icon panah naik untuk growth
const upArrowIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="white" class="bi bi-arrow-up" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 12a.5.5 0 0 0 .5-.5V4.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4-.007-.007a.498.498 0 0 0-.7.007l-4 4a.5.5 0 1 0 .708.708L7.5 4.707V11.5A.5.5 0 0 0 8 12z"/></svg>';

// Buat badge untuk growth dan achievement
const growthBadge = createBadge(`▲ ${lastGrowth.toFixed(2)}% yoy`, upArrowIcon);
const ach1Badge = createBadge(`Ach 1: ${lastAch1.toFixed(2)}%`);
const ach2Badge = createBadge(`Ach 2: ${lastAch2.toFixed(2)}%`);

// Tambahkan badge ke div
labaRugiBadges.appendChild(growthBadge);
labaRugiBadges.appendChild(ach1Badge);
labaRugiBadges.appendChild(ach2Badge);

// Calculate totals for pendapatan and beban

const totalPendapatanRealisasi = <?= json_encode(array_sum($valuesPendapatan)) ?>;
const totalPendapatanAnggaran = <?= json_encode(0) ?>; // Adjust if anggaran data available
const totalBebanRealisasi = <?= json_encode(array_sum($valuesBeban)) ?>;
const totalBebanAnggaran = <?= json_encode(0) ?>; // Adjust if anggaran data available

const lastGrowthPendapatan = 5.25;
const lastAch1Pendapatan = 90.5;
const lastAch2Pendapatan = 88.3;

const lastGrowthBeban = 3.75;
const lastAch1Beban = 85.2;
const lastAch2Beban = 80.1;

// Create badges for pendapatan
const pendapatanBadges = document.getElementById('pendapatanBadges');
const growthBadgePendapatan = createBadge(`▲ ${lastGrowthPendapatan.toFixed(2)}% yoy`, upArrowIcon);
const ach1BadgePendapatan = createBadge(`Ach 1: ${lastAch1Pendapatan.toFixed(2)}%`);
const ach2BadgePendapatan = createBadge(`Ach 2: ${lastAch2Pendapatan.toFixed(2)}%`);
pendapatanBadges.appendChild(growthBadgePendapatan);
pendapatanBadges.appendChild(ach1BadgePendapatan);
pendapatanBadges.appendChild(ach2BadgePendapatan);

// Create badges for beban
const bebanBadges = document.getElementById('bebanBadges');
const growthBadgeBeban = createBadge(`▲ ${lastGrowthBeban.toFixed(2)}% yoy`, upArrowIcon);
const ach1BadgeBeban = createBadge(`Ach 1: ${lastAch1Beban.toFixed(2)}%`);
const ach2BadgeBeban = createBadge(`Ach 2: ${lastAch2Beban.toFixed(2)}%`);
bebanBadges.appendChild(growthBadgeBeban);
bebanBadges.appendChild(ach1BadgeBeban);
bebanBadges.appendChild(ach2BadgeBeban);

const chartPendapatan = createBarChart(
    document.getElementById('chartPendapatan').getContext('2d'),
    <?= json_encode($labelsPendapatan) ?>,
    <?= json_encode($valuesPendapatan) ?>,
    'Realisasi',
    'Anggaran',
    'rgba(75, 192, 192, 0.7)',
    'rgba(75, 192, 192, 0.3)',
    totalPendapatanRealisasi,
    totalPendapatanAnggaran,
    'chartPendapatan'
);

chartPendapatan.options.plugins.subtitle.display = true;
chartPendapatan.options.plugins.subtitle.text = `Total Realisasi: ${totalPendapatanRealisasi.toLocaleString()} | Total Anggaran: ${totalPendapatanAnggaran.toLocaleString()}`;
chartPendapatan.update();

const chartBeban = createBarChart(
    document.getElementById('chartBeban').getContext('2d'),
    <?= json_encode($labelsBeban) ?>,
    <?= json_encode($valuesBeban) ?>,
    'Realisasi',
    'Anggaran',
    'rgba(255, 99, 132, 0.7)',
    'rgba(255, 99, 132, 0.3)',
    totalBebanRealisasi,
    totalBebanAnggaran,
    'chartBeban'
);

chartBeban.options.plugins.subtitle.display = true;
chartBeban.options.plugins.subtitle.text = `Total Realisasi: ${totalBebanRealisasi.toLocaleString()} | Total Anggaran: ${totalBebanAnggaran.toLocaleString()}`;
chartBeban.update();

chartPendapatan.options.scales.y.display = false;
chartPendapatan.options.scales.y.grid.drawTicks = false;
chartPendapatan.options.scales.y.grid.drawBorder = false;
chartPendapatan.options.scales.y.grid.drawOnChartArea = false;
chartPendapatan.options.scales.y.ticks.display = false;
chartPendapatan.update();

chartBeban.options.scales.y.display = false;
chartBeban.options.scales.y.grid.drawTicks = false;
chartBeban.options.scales.y.grid.drawBorder = false;
chartBeban.options.scales.y.grid.drawOnChartArea = false;
chartBeban.options.scales.y.ticks.display = false;
chartBeban.update();

// Chart baru: Pendapatan & Jumlah Penumpang
const ctxPendapatanPenumpang = document.getElementById('chartPendapatanPenumpang').getContext('2d');
const chartPendapatanPenumpang = new Chart(ctxPendapatanPenumpang, {
    type: 'bar',
    data: {
        labels: ['Pendapatan', 'Penumpang'],
        datasets: [
            {
                label: 'Previous',
                data: [<?= $dataPendapatanPenumpang['realisasi_pendapatan'] ?? 0 ?>, <?= $dataPendapatanPenumpang['jumlah_penumpang'] ?? 0 ?>],
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderRadius: 4,
                barPercentage: 0.5,
            },
            {
                label: 'Program',
                data: [<?= $dataPendapatanPenumpang['anggaran_pendapatan'] ?? 0 ?>, <?= $dataPendapatanPenumpang['jumlah_penumpang_anggaran'] ?? 0 ?>],
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderRadius: 4,
                barPercentage: 0.5,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    font: {
                        size: 12
                    }
                }
            },
            datalabels: {
                anchor: 'end',
                align: 'end',
                color: '#444',
                font: {
                    weight: 'bold',
                    size: 12
                },
                formatter: function(value) {
                    let num = typeof value === 'string' ? parseFloat(value) : value;
                    if (num >= 1e12) {
                        return 'Rp' + (num / 1e12).toFixed(2) + 'T';
                    } else if (num >= 1e9) {
                        return 'Rp' + (num / 1e9).toFixed(2) + 'M';
                    } else if (num >= 1e6) {
                        return 'Rp' + (num / 1e6).toFixed(2) + 'Jt';
                    } else if (num >= 1e3) {
                        return 'Rp' + (num / 1e3).toFixed(2) + 'Rb';
                    } else {
                        return 'Rp' + num.toFixed(2);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                display: false,
                grid: {
                    drawTicks: false,
                    drawBorder: false,
                    drawOnChartArea: false
                },
                ticks: {
                    display: false,
                    callback: function(value) {
                        return '';
                    }
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 10
                    }
                }
            }
        }
    },
    plugins: [ChartDataLabels]
});
chartPendapatanPenumpang.options.scales.y.display = false;
chartPendapatanPenumpang.options.scales.y.grid.drawTicks = false;
chartPendapatanPenumpang.options.scales.y.grid.drawBorder = false;
chartPendapatanPenumpang.options.scales.y.grid.drawOnChartArea = false;
chartPendapatanPenumpang.options.scales.y.ticks.display = false;
chartPendapatanPenumpang.update();

// Chart baru: Pendapatan & Volume Barang
const ctxPendapatanVolume = document.getElementById('chartPendapatanVolume').getContext('2d');
const chartPendapatanVolume = new Chart(ctxPendapatanVolume, {
    type: 'bar',
    data: {
        labels: ['Pendapatan', 'Volume'],
        datasets: [
            {
                label: 'Previous',
                data: [<?= $dataPendapatanVolume['realisasi_pendapatan'] ?? 0 ?>, <?= $dataPendapatanVolume['volume_barang'] ?? 0 ?>],
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderRadius: 4,
                barPercentage: 0.5,
            },
            {
                label: 'Program',
                data: [<?= $dataPendapatanVolume['anggaran_pendapatan'] ?? 0 ?>, <?= $dataPendapatanVolume['volume_barang_anggaran'] ?? 0 ?>],
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderRadius: 4,
                barPercentage: 0.5,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    font: {
                        size: 12
                    }
                }
            },
            datalabels: {
                anchor: 'end',
                align: 'end',
                color: '#444',
                font: {
                    weight: 'bold',
                    size: 12
                },
                formatter: function(value) {
                    let num = typeof value === 'string' ? parseFloat(value) : value;
                    if (num >= 1e12) {
                        return 'Rp' + (num / 1e12).toFixed(2) + 'T';
                    } else if (num >= 1e9) {
                        return 'Rp' + (num / 1e9).toFixed(2) + 'M';
                    } else if (num >= 1e6) {
                        return 'Rp' + (num / 1e6).toFixed(2) + 'Jt';
                    } else if (num >= 1e3) {
                        return 'Rp' + (num / 1e3).toFixed(2) + 'Rb';
                    } else {
                        return 'Rp' + num.toFixed(2);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                display: false,
                grid: {
                    drawTicks: false,
                    drawBorder: false,
                    drawOnChartArea: false
                },
                ticks: {
                    display: false,
                    callback: function(value) {
                        let num = typeof value === 'string' ? parseFloat(value) : value;
                        if (num >= 1e12) {
                            return 'Rp' + (num / 1e12).toFixed(2) + 'T';
                        } else if (num >= 1e9) {
                            return 'Rp' + (num / 1e9).toFixed(2) + 'M';
                        } else if (num >= 1e6) {
                            return 'Rp' + (num / 1e6).toFixed(2) + 'Jt';
                        } else if (num >= 1e3) {
                            return 'Rp' + (num / 1e3).toFixed(2) + 'Rb';
                        } else {
                            return 'Rp' + num.toFixed(2);
                        }
                    }
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 10
                    }
                }
            }
        }
    },
    plugins: [ChartDataLabels]
});

const ctxPendapatanAsset = document.getElementById('chartPendapatanAsset').getContext('2d');
const chartPendapatanAsset = new Chart(ctxPendapatanAsset, {
    type: 'bar',
    data: {
        labels: ['Kinerja Kontrak', 'Pendapatan', 'Beban'],
        datasets: [
            {
                label: 'Previous',
                data: [<?= $dataPendapatanAsset['realisasi_kinerja'] ?? 0 ?>, <?= $dataPendapatanAsset['realisasi_pendapatan'] ?? 0 ?>, <?= $dataBebanAsset['realisasi_beban'] ?? 0 ?>],
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderRadius: 4,
                barPercentage: 0.5,
            },
            {
                label: 'Program',
                data: [<?= $dataPendapatanAsset['anggaran_kinerja'] ?? 0 ?>, <?= $dataPendapatanAsset['anggaran_pendapatan'] ?? 0 ?>, <?= $dataBebanAsset['anggaran_beban'] ?? 0 ?>],
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderRadius: 4,
                barPercentage: 0.5,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    font: {
                        size: 12
                    }
                }
            },
            datalabels: {
                anchor: 'end',
                align: 'end',
                color: '#444',
                font: {
                    weight: 'bold',
                    size: 12
                },
                formatter: function(value) {
                    let num = typeof value === 'string' ? parseFloat(value) : value;
                    if (num >= 1e12) {
                        return 'Rp' + (num / 1e12).toFixed(2) + 'T';
                    } else if (num >= 1e9) {
                        return 'Rp' + (num / 1e9).toFixed(2) + 'M';
                    } else if (num >= 1e6) {
                        return 'Rp' + (num / 1e6).toFixed(2) + 'Jt';
                    } else if (num >= 1e3) {
                        return 'Rp' + (num / 1e3).toFixed(2) + 'Rb';
                    } else {
                        return 'Rp' + num.toFixed(2);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                display: false,
                grid: {
                    drawTicks: false,
                    drawBorder: false,
                    drawOnChartArea: false
                },
                ticks: {
                    display: false,
                    callback: function(value) {
                        let num = typeof value === 'string' ? parseFloat(value) : value;
                        if (num >= 1e12) {
                            return 'Rp' + (num / 1e12).toFixed(2) + 'T';
                        } else if (num >= 1e9) {
                            return 'Rp' + (num / 1e9).toFixed(2) + 'M';
                        } else if (num >= 1e6) {
                            return 'Rp' + (num / 1e6).toFixed(2) + 'Jt';
                        } else if (num >= 1e3) {
                            return 'Rp' + (num / 1e3).toFixed(2) + 'Rb';
                        } else {
                            return 'Rp' + num.toFixed(2);
                        }
                    }
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 10
                    }
                }
            }
        }
    },
    plugins: [ChartDataLabels]
});
</script>