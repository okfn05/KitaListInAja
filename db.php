<?php
$host = "localhost";
$user = "root";
$password = ""; // ganti sesuai konfigurasi MySQL-mu
$dbname = "kitalistinaja"; // nama database

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>
