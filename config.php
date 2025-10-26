<?php
// config.php
$host = "127.0.0.1";
$username = "root";
$password = "";
$database = "financial_management";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Fungsi untuk membersihkan input
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Start session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>