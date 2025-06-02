<?php
session_start();

// Include koneksi database jika diperlukan
require_once __DIR__ . '/config/database.php';

// Routing berdasarkan parameter URL
$page = $_GET['page'] ?? 'halaman1';

switch ($page) {
    case 'login':
        require 'login.php';
        break;
    case 'register':
        require 'register.php';
        break;
    case 'dashboard':
        require 'dashboard.php';
        break;
    case 'logout':
        require 'logout.php';
        break;
    case 'halaman1':
        require 'halaman1.php';
        break;
    case 'halaman2':
        require 'halaman2.php';
        break;
    case 'halaman3':
        require 'halaman3.php';
        break;
    default:
        echo "<h2>404 - Page not found</h2>";
        break;
}
?>
