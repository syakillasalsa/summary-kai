<?php
// Koneksi database
$conn = new mysqli("localhost", "root", "", "kai");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo "ID tidak valid.";
    exit;
}

// Hapus data investasi berdasarkan id
$stmt = $conn->prepare("DELETE FROM investasi WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// Resequence numbering after deletion
function resequenceNumbering($conn) {
    $sql = "SELECT id FROM investasi ORDER BY id ASC";
    $result = $conn->query($sql);
    $counter = 1;
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $stmtUpdate = $conn->prepare("UPDATE investasi SET no = ? WHERE id = ?");
        $stmtUpdate->bind_param("ii", $counter, $id);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        $counter++;
    }
}
resequenceNumbering($conn);

header("Location: investasi.php");
exit;
?>
