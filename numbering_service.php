<?php
function getNextTopLevelNumbering($conn, $kategori) {
    $sql = "SELECT nomor FROM laporan WHERE kategori = ? AND (parent_id IS NULL OR parent_id = 0) ORDER BY CAST(nomor AS UNSIGNED) DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $kategori);
    $stmt->execute();
    $result = $stmt->get_result();
    $nomor = '1';
    if ($row = $result->fetch_assoc()) {
        if (preg_match('/^(\d+)$/', $row['nomor'], $matches)) {
            $nomor = (string)(intval($matches[1]) + 1);
        }
    }
    $stmt->close();
    return $nomor;
}

function incrementLetterSuffix($suffix) {
    if ($suffix === '') return 'A';
    $i = strlen($suffix) - 1;
    while ($i >= 0 && $suffix[$i] === 'Z') {
        $i--;
    }

    if ($i == -1) {
        return str_repeat('A', strlen($suffix) + 1);
    }

    $prefix = substr($suffix, 0, $i);
    $nextChar = chr(ord($suffix[$i]) + 1);
    return $prefix . $nextChar . str_repeat('A', strlen($suffix) - $i - 1);
}

function resequenceNumbering($conn, $kategori, $bulan = null, $tahun = null, $parent_id = null) {
    $stack = [];
    if ($parent_id === null) {
        $stack[] = [null, '', 1];
    } else {
        // Cari nomor parent
        $stmt = $conn->prepare("SELECT nomor FROM laporan WHERE id = ?");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) return;

        $stack[] = [$parent_id, $row['nomor'], 0]; // Mulai suffix A
    }

    while (!empty($stack)) {
        list($currentParentId, $parentNomor, $mode) = array_pop($stack);

        if ($currentParentId === null) {
            $sql = "SELECT id FROM laporan WHERE kategori = ? AND (parent_id IS NULL OR parent_id = 0)";
            $params = [$kategori];
            $types = "s";

            if ($bulan !== null) {
                $sql .= " AND bulan = ?";
                $params[] = $bulan;
                $types .= "i";
            }
            if ($tahun !== null) {
                $sql .= " AND tahun = ?";
                $params[] = $tahun;
                $types .= "i";
            }

            $sql .= " ORDER BY id ASC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt = $conn->prepare("SELECT id FROM laporan WHERE parent_id = ? ORDER BY id ASC");
            $stmt->bind_param("i", $currentParentId);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $counter = 1;
        $suffix = 'A';

        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $newNomor = '';

            if ($currentParentId === null) {
                $newNomor = (string)$counter;
                $counter++;
            } else {
                $newNomor = $parentNomor . $suffix;
                $suffix = incrementLetterSuffix($suffix);
            }

            // Update nomor
            $stmtUpdate = $conn->prepare("UPDATE laporan SET nomor = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $newNomor, $id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            // Masukkan anak ke stack
            $stack[] = [$id, $newNomor, 1];
        }
        $stmt->close();
    }
}
function getNextChildNumbering($conn, $parent_id) {
    // Ambil nomor parent-nya
    $stmt = $conn->prepare("SELECT nomor FROM laporan WHERE id = ?");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) return 'A'; // fallback jika parent tidak ditemukan

    $parentNomor = $row['nomor'];

    // Cari suffix terbesar
    $stmt2 = $conn->prepare("SELECT nomor FROM laporan WHERE parent_id = ? ORDER BY nomor DESC LIMIT 1");
    $stmt2->bind_param("i", $parent_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    if ($res2 && $childRow = $res2->fetch_assoc()) {
        $childNomor = $childRow['nomor'];
        error_log("Found child suffix: " . $childNomor);
        $suffix = str_replace($parentNomor, '', $childNomor);
        error_log("Extracted suffix: " . $suffix);
        $nextSuffix = incrementLetterSuffix($suffix);
        error_log("Next suffix: " . $nextSuffix);
    } else {
        $nextSuffix = 'A';
    }
    $stmt2->close();

    return $parentNomor . $nextSuffix;
}

?>
