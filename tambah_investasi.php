<?php
ob_start();
$pageTitle = "Tambah Data Investasi";
include 'header.php';

// Koneksi database
$conn = new mysqli("localhost", "root", "", "kai");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no = $_POST['no'] ?? '';
    $uraian = $_POST['uraian'] ?? '';
    $wbs = $_POST['wbs'] ?? '';
    $lokasi_pengadaan = $_POST['lokasi_pengadaan'] ?? '';
    $volume_satuan = isset($_POST['volume_satuan']) ? (float)$_POST['volume_satuan'] : 0;
    $harga_satuan = isset($_POST['harga_satuan']) ? (float)$_POST['harga_satuan'] : 0;
    $jumlah_dana = $volume_satuan * $harga_satuan;
    $budget_tahun_2024 = $_POST['budget_tahun_2024'] ?? 0;
    $tambahan_dana = $_POST['tambahan_dana'] ?? 0;
    $total_tahun_2024 = $_POST['total_tahun_2024'] ?? 0;
    $commitment = $_POST['commitment'] ?? 0;
    $actual = $_POST['actual'] ?? 0;
    $consumed_budget = $_POST['consumed_budget'] ?? 0;
    $available_budget = $_POST['available_budget'] ?? 0;
    $progres_saat_ini = $_POST['progres_saat_ini'] ?? '';
    $tanggal_kontrak = $_POST['tanggal_kontrak'] ?? null;
    $no_kontrak = $_POST['no_kontrak'] ?? '';
    $nilai_kontrak = $_POST['nilai_kontrak'] ?? 0;
    $ket = $_POST['ket'] ?? '';

    // Validasi sederhana
    if (empty($uraian)) {
        $errors[] = "Uraian wajib diisi.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO investasi (no, uraian, wbs, lokasi_pengadaan,budget_tahun_2024, tambahan_dana, total_tahun_2024, commitment, actual, consumed_budget, available_budget, progres_saat_ini, tanggal_kontrak, no_kontrak, nilai_kontrak, ket) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error));
        }

        $stmt->bind_param("issssddddddddssssss",
            $no,
            $uraian,
            $wbs,
            $lokasi_pengadaan,
            $budget_tahun_2024,
            $tambahan_dana,
            $total_tahun_2024,
            $commitment,
            $actual,
            $consumed_budget,
            $available_budget,
            $progres_saat_ini,
            $tanggal_kontrak,
            $no_kontrak,
            $nilai_kontrak,
            $ket,
        );

        if (!$stmt->execute()) {
            die('Execute failed: ' . htmlspecialchars($stmt->error));
        }

        $stmt->close();

        header("Location: investasi.php");
        exit;
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Tambah Data Investasi</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="investasi.php">Data Investasi</a></li>
                <li class="breadcrumb-item active">Tambah Data</li>
            </ol>
        </nav>
    </div>

    <div class="card p-3" style="padding: 30px; margin-bottom: 20px;">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <div class="mb-3">
                <label for="input_date" class="form-label">WAKTU INPUT DATA</label>
                <input type="date" class="form-control" id="input_date" name="input_date" value="<?= htmlspecialchars($_POST['input_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="mb-3">
                <label for="no" class="form-label">NO</label>
                <input type="text" class="form-control" id="no" name="no" value="<?= htmlspecialchars($_POST['no'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="uraian" class="form-label">URAIAN</label>
                <input type="text" class="form-control" id="uraian" name="uraian" value="<?= htmlspecialchars($_POST['uraian'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="wbs" class="form-label">WBS</label>
                <input type="text" class="form-control" id="wbs" name="wbs" value="<?= htmlspecialchars($_POST['wbs'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="lokasi_pengadaan" class="form-label">LOKASI PENGADAAN</label>
                <input type="text" readonly class="form-control bg-light text-secondary" id="lokasi_pengadaan" name="lokasi_pengadaan" value="Daop 6 YK">
            </div>
            <div class="mb-3">
                <label for="budget_tahun_2024" class="form-label">BUDGET TAHUN 2024 (Rp)</label>
                <input type="number" step="0.01" class="form-control" id="budget_tahun_2024" name="budget_tahun_2024" value="<?= htmlspecialchars($_POST['budget_tahun_2024'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="tambahan_dana" class="form-label">TAMBAHAN DANA (Rp)</label>
                <input type="number" step="0.01" class="form-control" id="tambahan_dana" name="tambahan_dana" value="<?= htmlspecialchars($_POST['tambahan_dana'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="total_tahun_2024" class="form-label">TOTAL TAHUN 2024 (Rp)</label>
                <input type="number" step="0.01" class="form-control bg-light" id="total_tahun_2024" name="total_tahun_2024" value="<?= htmlspecialchars($_POST['total_tahun_2024'] ?? '') ?>" readonly>
            </div>
            <div class="mb-3">
                <label for="commitment" class="form-label">COMMITMENT</label>
                <input type="number" step="0.01" class="form-control" id="commitment" name="commitment" value="<?= htmlspecialchars($_POST['commitment'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="actual" class="form-label">ACTUAL</label>
                <input type="number" step="0.01" class="form-control" id="actual" name="actual" value="<?= htmlspecialchars($_POST['actual'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="consumed_budget" class="form-label">CONSUMED BUDGET</label>
                <input type="number" step="0.01" class="form-control bg-light" id="consumed_budget" name="consumed_budget" value="<?= htmlspecialchars($_POST['consumed_budget'] ?? '') ?>" readonly>
            </div>
            <div class="mb-3">
                <label for="available_budget" class="form-label">AVAILABLE BUDGET</label>
                <input type="number" step="0.01" class="form-control bg-light" id="available_budget" name="available_budget" value="<?= htmlspecialchars($_POST['available_budget'] ?? '') ?>" readonly>
            </div>
            <div class="mb-3">
                <label for="progres_saat_ini" class="form-label">PROGRES SAAT INI</label>
                <textarea class="form-control" id="progres_saat_ini" name="progres_saat_ini"><?= htmlspecialchars($_POST['progres_saat_ini'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label for="tanggal_kontrak" class="form-label">TANGGAL KONTRAK</label>
                <input type="date" class="form-control" id="tanggal_kontrak" name="tanggal_kontrak" value="<?= htmlspecialchars($_POST['tanggal_kontrak'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="no_kontrak" class="form-label">NO KONTRAK</label>
                <input type="text" class="form-control" id="no_kontrak" name="no_kontrak" value="<?= htmlspecialchars($_POST['no_kontrak'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="nilai_kontrak" class="form-label">NILAI KONTRAK (Rp)</label>
                <input type="number" step="0.01" class="form-control" id="nilai_kontrak" name="nilai_kontrak" value="<?= htmlspecialchars($_POST['nilai_kontrak'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="ket" class="form-label">KET</label>
                <textarea class="form-control" id="ket" name="ket"><?= htmlspecialchars($_POST['ket'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-success">Simpan</button>
            <a href="investasi.php" class="btn btn-secondary">Batal</a>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            function parseNumber(value) {
                var parsed = parseFloat(value);
                return isNaN(parsed) ? 0 : parsed;
            }

            function calculateFields() {
                var budgetTahun = parseNumber(document.getElementById('budget_tahun_2024').value);
                var tambahanDana = parseNumber(document.getElementById('tambahan_dana').value);
                var commitment = parseNumber(document.getElementById('commitment').value);
                var actual = parseNumber(document.getElementById('actual').value);

                var totalTahun = budgetTahun + tambahanDana;
                var consumedBudget = commitment + actual;
                var availableBudget = totalTahun - consumedBudget;

                document.getElementById('total_tahun_2024').value = totalTahun.toFixed(2);
                document.getElementById('consumed_budget').value = consumedBudget.toFixed(2);
                document.getElementById('available_budget').value = availableBudget.toFixed(2);
            }

            var inputsToWatch = [ 'budget_tahun_2024', 'tambahan_dana', 'commitment', 'actual'];
            inputsToWatch.forEach(function(id) {
                var el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', calculateFields);
                }
            });

            // Initial calculation on page load
            calculateFields();
        });
        </script>

    </div>
     <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

</main>

<script src="assets/js/main.js"></script>
<?php include 'footer.php'; ?>