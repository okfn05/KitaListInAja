<?php

// Konfigurasi OAuth untuk Google dan Apple

// Google OAuth Configuration
$google_client_id = '583572695554-5ajesvomumkt4nnp636kps3fo7cc99no.apps.googleusercontent.com';
$google_client_secret = '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';
$google_redirect_uri = 'http://localhost/Kitalistinaja/register.php'; 

// Apple OAuth Configuration
$apple_client_id = 'com.yourcompany.yourapp'; // Service ID dari Apple Developer
$apple_client_secret = 'YOUR_APPLE_CLIENT_SECRET'; // Generated JWT atau key
$apple_redirect_uri = 'http://localhost/Kitalistinaja/register.php'; // Sesuaikan dengan URL Anda

// Validasi konfigurasi
if (empty($google_client_id) || $google_client_id === '583572695554-5ajesvomumkt4nnp636kps3fo7cc99no.apps.googleusercontent.com') {

}

if (empty($apple_client_id) || $apple_client_id === 'com.yourcompany.yourapp') {

}
?>