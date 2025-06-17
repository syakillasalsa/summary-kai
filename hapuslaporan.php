<?php
include 'koneksi.php';

$id = $_POST['id'] ?? null;
$kategori = $_POST['kategori'] ?? 'pendapatan';

if ($id) {
    // Recursive delete function
    function deleteLaporanRecursive($conn, $id) {
        // Delete children first
        $stmtChildren = $conn->prepare("SELECT id FROM laporan WHERE parent_id = ?");
        if (!$stmtChildren) {
            error_log("Prepare failed for fetching children: " . $conn->error);
            die("Prepare failed for fetching children: " . $conn->error);
        }
        $stmtChildren->bind_param("i", $id);
        if (!$stmtChildren->execute()) {
            error_log("Failed to fetch child entries: " . $stmtChildren->error);
            die("Failed to fetch child entries: " . $stmtChildren->error);
        }
        $resultChildren = $stmtChildren->get_result();
        while ($child = $resultChildren->fetch_assoc()) {
            deleteLaporanRecursive($conn, $child['id']);
        }
        $stmtChildren->close();

        // Delete this entry
        $stmtDel = $conn->prepare("DELETE FROM laporan WHERE id = ?");
        if (!$stmtDel) {
            error_log("Prepare failed for delete: " . $conn->error);
            die("Prepare failed for delete: " . $conn->error);
        }
        $stmtDel->bind_param("i", $id);
        if (!$stmtDel->execute()) {
            error_log("Failed to delete laporan: " . $stmtDel->error);
            die("Failed to delete laporan: " . $stmtDel->error);
        }
        $stmtDel->close();
    }

    // Get kategori and parent_id before deletion for resequencing
    $stmt = $conn->prepare("SELECT kategori, parent_id FROM laporan WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $delKategori = $row['kategori'] ?? null;
    $delParentId = $row['parent_id'] ?? null;
    $stmt->close();

    // Call recursive delete
    deleteLaporanRecursive($conn, $id);

    // Re-sequence numbering after deletion
    resequenceNumbering($conn, $delKategori, $delParentId);
}

header("Location: laporan.php?kategori=" . urlencode($kategori));
exit;

// Function to resequence numbering for a category and parent_id
function resequenceNumbering($conn, $kategori, $parent_id) {
    if ($kategori === null) return;

    if ($parent_id === null) {
        $sql = "SELECT id FROM laporan WHERE kategori = ? AND (parent_id IS NULL OR parent_id = 0) ORDER BY CAST(nomor AS UNSIGNED) ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $kategori);
    } else {
        $sql = "SELECT id FROM laporan WHERE parent_id = ? ORDER BY CAST(nomor AS UNSIGNED) ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $parent_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $counter = 1;
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        if ($parent_id === null) {
            $newNomor = (string)$counter;
        } else {
            $letters = range('a', 'z');
            $index = $counter - 1;
            $letter = $letters[$index] ?? '?';
            // Get parent nomor
            $sqlParent = "SELECT nomor FROM laporan WHERE id = ?";
            $stmtParent = $conn->prepare($sqlParent);
            $stmtParent->bind_param("i", $parent_id);
            $stmtParent->execute();
            $resultParent = $stmtParent->get_result();
            $rowParent = $resultParent->fetch_assoc();
            $parentNomor = $rowParent['nomor'] ?? '';
            $stmtParent->close();

            $newNomor = $parentNomor . $letter;
        }
        $stmtUpdate = $conn->prepare("UPDATE laporan SET nomor = ? WHERE id = ?");
        $stmtUpdate->bind_param("si", $newNomor, $id);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // Recursively resequence children
        resequenceNumbering($conn, $kategori, $id);

        $counter++;
    }
    $stmt->close();
}
