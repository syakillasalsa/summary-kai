<?php
include 'koneksi.php';
include 'numbering_service.php';

$categories = [
    'pendapatan' => 'Pendapatan',
    'beban' => 'Beban',
    'laba rugi usaha' => 'Laba Rugi Usaha',
    'pendapatan beban lain lain' => 'Pendapatan Beban Lain Lain',
    'laba rugi sebelum pajak penghasilan' => 'Laba Rugi Sebelum Pajak Penghasilan',
    'pajak penghasilan' => 'Pajak Penghasilan',
    'laba rugi bersih tahun berjalan' => 'Laba Rugi Bersih Tahun Berjalan',
    'kepentingan non pengendali' => 'Kepentingan Non Pengendali',
    'laba yang dapat diatribusikan kepada pemilik entitas induk' => 'Laba yang Dapat Diatribusikan kepada Pemilik Entitas Induk'
];

$kategori = $_GET['kategori'] ?? 'pendapatan';
$parent_id = $_GET['parent_id'] ?? null;
if ($parent_id === '' || $parent_id === '0') {
    $parent_id = null;
} else {
    $parent_id = (int)$parent_id;
}

$errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kategori = $_POST['kategori'] ?? 'pendapatan';
    $uraian = $_POST['uraian'] ?? '';
    $parent_id = $_POST['parent_id'] ?? null;
    if ($parent_id === '' || $parent_id === '0') {
        $parent_id = null;
    } else {
        $parent_id = (int)$parent_id;
    }

    $realisasi_tahun_lalu = $_POST['realisasi_tahun_lalu'] ?? 0;
    $anggaran_tahun_ini = $_POST['anggaran_tahun_ini'] ?? 0;
    $realisasi_tahun_ini = $_POST['realisasi_tahun_ini'] ?? 0;
    $anggaran_per_tahun = $_POST['anggaran_per_tahun'] ?? 0;
    $bulan = $_POST['bulan'] ?? null;
    $tahun = $_POST['tahun'] ?? null;
    $analisis_vertical = 0;

    if (empty($uraian)) {
        $errors[] = "Uraian harus diisi.";
    }

    if (empty($errors)) {
        // Cek duplikasi
        if ($parent_id === null) {
            $checkSql = "SELECT COUNT(*) as count FROM laporan WHERE kategori = ? AND Uraian = ? AND parent_id IS NULL";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("ss", $kategori, $uraian);
        } else {
            $checkSql = "SELECT COUNT(*) as count FROM laporan WHERE kategori = ? AND Uraian = ? AND parent_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("ssi", $kategori, $uraian, $parent_id);
        }
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $row = $checkResult->fetch_assoc();
        $checkStmt->close();

        if ($row['count'] > 0) {
            $errors[] = "Data dengan uraian yang sama sudah ada di kategori ini.";
        } else {
            // Penentuan nomor
            $nomor = null;
            if ($parent_id === null) {
                $nomor = getNextTopLevelNumbering($conn, $kategori);
            } else {
                $nomor = getNextChildNumbering($conn, $parent_id);
            }

            $stmt = $conn->prepare("INSERT INTO laporan (kategori, Uraian, parent_id, nomor, REALISASI_TAHUN_LALU, ANGGARAN_TAHUN_INI, REALISASI_TAHUN_INI, ANALISIS_VERTICAL, tahun, bulan, ANGGARAN_PER_TAHUN) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssidddddii", $kategori, $uraian, $parent_id, $nomor, $realisasi_tahun_lalu, $anggaran_tahun_ini, $realisasi_tahun_ini, $analisis_vertical, $tahun, $bulan, $anggaran_per_tahun);

            if ($stmt->execute()) {
                error_log("Insert success untuk uraian: $uraian | Nomor: $nomor");

                // Resequence numbering after insert to fix duplicates and hierarchy
                resequenceNumbering($conn, $kategori, $parent_id);

                $redirectUrl = "laporan.php?kategori=" . urlencode($kategori);
                $bulan = $_POST['bulan'] ?? '';
                $tahun = $_POST['tahun'] ?? '';
                if ($bulan !== '') $redirectUrl .= "&bulan=" . urlencode($bulan);
                if ($tahun !== '') $redirectUrl .= "&tahun=" . urlencode($tahun);
                header("Location: " . $redirectUrl);
                exit;
            } else {
                $errors[] = "Gagal menyimpan data: " . $stmt->error;
            }
            $stmt->close();
        }
    }
} else {
    $prefix = null;
    if ($parent_id === null) {
        $prefix = getNextTopLevelNumbering($conn, $kategori);
    } else {
        $prefix = getNextChildNumbering($conn, $parent_id);
    }
    $uraian = '';
}
?>

<?php include 'header.php'; ?>
<main id="main" class="main" style="margin-left: 300px; padding: 20px;">
    <div class="pagetitle" style="margin-top: 60px;">
        <h1>Tambah Data Laporan - <?= htmlspecialchars($categories[$kategori]) ?></h1>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="laporan.php">Laporan</a></li>
                <li class="breadcrumb-item active">Tambah Data</li>
            </ol>
        </nav>
    </div>

    <div class="card p-3">
        <form method="POST" action="tambahlaporan.php" id="laporanForm">
            <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori) ?>">
            <input type="hidden" name="parent_id" value="<?= htmlspecialchars($parent_id) ?>">
            <div class="mb-3">
                <label for="uraian" class="form-label">U R A I A N</label>
                <input type="text" class="form-control" id="uraian" name="uraian" value="<?= htmlspecialchars($uraian) ?>" required>
            </div>
            <div class="mb-3">
                <label for="realisasi_tahun_lalu" class="form-label">REALISASI TAHUN LALU</label>
                <input type="number" step="0.01" class="form-control" id="realisasi_tahun_lalu" name="realisasi_tahun_lalu" value="0" onfocus="if(this.value=='0') this.value='';">
            </div>
            <div class="mb-3">
                <label for="anggaran_tahun_ini" class="form-label">ANGGARAN TAHUN INI</label>
                <input type="number" step="0.01" class="form-control" id="anggaran_tahun_ini" name="anggaran_tahun_ini" value="0" onfocus="if(this.value=='0') this.value='';">
            </div>
            <div class="mb-3">
                <label for="realisasi_tahun_ini" class="form-label">REALISASI TAHUN INI</label>
                <input type="number" step="0.01" class="form-control" id="realisasi_tahun_ini" name="realisasi_tahun_ini" value="0" onfocus="if(this.value=='0') this.value='';">
            </div>
            <div class="mb-3">
                <label for="anggaran_per_tahun" class="form-label">ANGGARAN PER TAHUN</label>
                <input type="number" step="0.01" class="form-control" id="anggaran_per_tahun" name="anggaran_per_tahun" value="0" onfocus="if(this.value=='0') this.value='';">
            </div>
            <div class="mb-3">
                <label for="bulan" class="form-label">Bulan</label>
                <select class="form-select" id="bulan" name="bulan" required>
                    <option value="">Pilih Bulan</option>
                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        $monthName = date('F', mktime(0, 0, 0, $m, 1));
                        echo '<option value="' . $m . '">' . $monthName . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="tahun" class="form-label">Tahun</label>
                <select class="form-select" id="tahun" name="tahun" required>
                    <option value="">Pilih Tahun</option>
                    <?php
                    $currentYear = date('Y');
                    for ($y = $currentYear - 5; $y <= $currentYear + 5; $y++) {
                        echo '<option value="' . $y . '">' . $y . '</option>';
                    }
                    ?>
                </select>
            </div>
            <button type="submit" class="btn btn-success" id="submitBtn">Simpan</button>
            <a href="laporan.php?kategori=<?= urlencode($kategori) ?>" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</main>
<script>
document.getElementById('laporanForm').addEventListener('submit', function() {
    document.getElementById('submitBtn').disabled = true;
});
</script>
<script src="assets/js/main.js"></script>
<?php include 'footer.php'; ?>
