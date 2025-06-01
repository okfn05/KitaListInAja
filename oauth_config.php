<?php
// oauth_config.php
// Konfigurasi OAuth untuk Google dan Apple

// Google OAuth Configuration
$google_client_id = 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com';
$google_client_secret = 'YOUR_GOOGLE_CLIENT_SECRET';
$google_redirect_uri = 'http://localhost/your-project/register.php'; // Sesuaikan dengan URL Anda

// Apple OAuth Configuration
$apple_client_id = 'com.yourcompany.yourapp'; // Service ID dari Apple Developer
$apple_client_secret = 'YOUR_APPLE_CLIENT_SECRET'; // Generated JWT atau key
$apple_redirect_uri = 'http://localhost/your-project/register.php'; // Sesuaikan dengan URL Anda

// Validasi konfigurasi
if (empty($google_client_id) || $google_client_id === 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com') {
    // Uncomment untuk debugging
    // error_log('Google OAuth belum dikonfigurasi. Silakan isi google_client_id dan google_client_secret');
}

if (empty($apple_client_id) || $apple_client_id === 'com.yourcompany.yourapp') {
    // Uncomment untuk debugging
    // error_log('Apple OAuth belum dikonfigurasi. Silakan isi apple_client_id dan apple_client_secret');
}
?>