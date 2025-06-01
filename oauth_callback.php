<?php
session_start();

// Tambahkan debug di awal file oauth_callback.php
echo "<h3>Debug OAuth Callback</h3>";
echo "<p><strong>GET Parameters:</strong></p>";
echo "<pre>" . print_r($_GET, true) . "</pre>";

echo "<p><strong>POST Parameters:</strong></p>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

echo "<p><strong>Current URL:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";

// Jika ada parameter, lanjutkan ke kode asli
// Jika tidak ada, tampilkan pesan debug
if (empty($_GET)) {
    echo "<p style='color: red;'><strong>Tidak ada parameter GET yang diterima!</strong></p>";
    echo "<p>Ini berarti Google tidak mengirim callback ke URL ini dengan benar.</p>";
    exit();
}


// Database configuration
$host = 'localhost';
$dbname = 'kitalistinaja';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Google OAuth configuration
$google_client_id = '583572695554-5ajesvomumkt4nnp636kps3fo7cc99no.apps.googleusercontent.com';
$google_client_secret = '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';

// Gunakan URL yang benar sesuai dengan struktur folder Anda
$google_redirect_uri = 'http://localhost/Kitalistinaja/oauth_callback.php';

// Apple OAuth configuration
$apple_client_id = 'YOUR_APPLE_CLIENT_ID';
$apple_client_secret = 'YOUR_APPLE_CLIENT_SECRET';
$apple_redirect_uri = 'http://localhost/Kitalistinaja/oauth_callback.php';

// Debug: Log semua parameter yang diterima
error_log("GET parameters: " . print_r($_GET, true));

if (isset($_GET['code']) && isset($_GET['state'])) {
    $code = $_GET['code'];
    $state = $_GET['state'];
    
    if ($state === 'google') {
        // Handle Google OAuth
        $token_url = 'https://oauth2.googleapis.com/token';
        $token_data = [
            'client_id' => $google_client_id,
            'client_secret' => $google_client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $google_redirect_uri
        ];
        
        // Get access token dengan error handling yang lebih baik
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Untuk development
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $token_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Debug: Log response
        error_log("Token response: " . $token_response);
        error_log("HTTP Code: " . $http_code);
        
        if ($curl_error) {
            header("Location: login.php?error=" . urlencode("cURL Error: " . $curl_error));
            exit();
        }
        
        $token_info = json_decode($token_response, true);
        
        if (isset($token_info['access_token'])) {
            // Get user info from Google dengan error handling
            $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_info['access_token'];
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'ignore_errors' => true
                ]
            ]);
            
            $user_info_response = file_get_contents($user_info_url, false, $context);
            $user_info = json_decode($user_info_response, true);
            
            if ($user_info && isset($user_info['id'])) {
                handleOAuthUser($pdo, $user_info, 'google');
            } else {
                header("Location: login.php?error=" . urlencode("Failed to get user info from Google"));
                exit();
            }
        } else {
            $error_msg = isset($token_info['error_description']) ? $token_info['error_description'] : 'Failed to get access token';
            header("Location: login.php?error=" . urlencode("Google OAuth Error: " . $error_msg));
            exit();
        }
        
    } elseif ($state === 'apple') {
        // Handle Apple OAuth (keep existing code)
        header("Location: login.php?error=" . urlencode("Apple OAuth not implemented yet"));
        exit();
    }
} else {
    // Jika tidak ada code atau state, atau ada error dari OAuth provider
    $error_msg = "Invalid OAuth response";
    
    if (isset($_GET['error'])) {
        $error_msg = $_GET['error'];
        if (isset($_GET['error_description'])) {
            $error_msg .= ': ' . $_GET['error_description'];
        }
    }
    
    header("Location: login.php?error=" . urlencode($error_msg));
    exit();
}

function handleOAuthUser($pdo, $user_info, $provider) {
    try {
        $oauth_id = $user_info['id'];
        $email = $user_info['email'] ?? '';
        $name = $user_info['name'] ?? ($provider == 'google' ? ($user_info['given_name'] ?? 'User') : 'User');
        
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE oauth_id = ? AND oauth_provider = ?");
        $stmt->execute([$oauth_id, $provider]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            // User sudah ada, langsung login
            $_SESSION['user_id'] = $existing_user['id'];
            $_SESSION['username'] = $existing_user['username'];
            $_SESSION['email'] = $existing_user['email'];
            $_SESSION['logged_in'] = true;
            
        } else {
            // User baru, buat akun
            $username = generateUniqueUsername($pdo, $name, $provider);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, oauth_id, oauth_provider, registration_type, created_at) VALUES (?, ?, ?, ?, 'oauth', NOW())");
            $stmt->execute([$username, $email, $oauth_id, $provider]);
            
            $new_user_id = $pdo->lastInsertId();
            
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['logged_in'] = true;
        }
        
        // Redirect ke dashboard
        header("Location: dashboard.php");
        exit();
        
    } catch(PDOException $e) {
        // Handle error
        error_log("Database error in handleOAuthUser: " . $e->getMessage());
        header("Location: login.php?error=" . urlencode("OAuth login failed: " . $e->getMessage()));
        exit();
    }
}

function generateUniqueUsername($pdo, $name, $provider) {
    // Clean name and create base username
    $clean_name = preg_replace('/[^a-zA-Z0-9]/', '', $name);
    if (empty($clean_name)) {
        $clean_name = 'user';
    }
    $base_username = strtolower($clean_name) . '_' . $provider;
    
    // Check if username exists and make it unique
    $username = $base_username;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            break;
        }
        
        $username = $base_username . '_' . $counter;
        $counter++;
    }
    
    return $username;
}
?>