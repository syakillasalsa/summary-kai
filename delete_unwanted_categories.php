<?php
include 'koneksi.php';

// Categories to keep
$allowedCategories = ['pendapatan', 'beban', 'laba rugi usaha'];

// Prepare placeholders for SQL IN clause
$placeholders = implode(',', array_fill(0, count($allowedCategories), '?'));

// Prepare SQL to delete records NOT in allowed categories
$sql = "DELETE FROM laporan WHERE kategori NOT IN ($placeholders)";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Prepare failed: ' . $conn->error);
}

// Bind parameters dynamically
$types = str_repeat('s', count($allowedCategories));
$stmt->bind_param($types, ...$allowedCategories);

if ($stmt->execute()) {
    echo "Records not in categories (" . implode(', ', $allowedCategories) . ") have been deleted successfully.";
} else {
    echo "Error deleting records: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
