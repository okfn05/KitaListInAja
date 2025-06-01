<?php
session_start();

// Database configuration (sama seperti di login.php)
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
$google_client_id = 'YOUR_GOOGLE_CLIENT_ID';
$google_client_secret = 'YOUR_GOOGLE_CLIENT_SECRET';
$google_redirect_uri = 'http://yoursite.com/oauth_callback.php';

// Apple OAuth configuration
$apple_client_id = 'YOUR_APPLE_CLIENT_ID';
$apple_client_secret = 'YOUR_APPLE_CLIENT_SECRET';
$apple_redirect_uri = 'http://yoursite.com/oauth_callback.php';

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
        
        // Get access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $token_response = curl_exec($ch);
        curl_close($ch);
        
        $token_info = json_decode($token_response, true);
        
        if (isset($token_info['access_token'])) {
            // Get user info from Google
            $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_info['access_token'];
            $user_info_response = file_get_contents($user_info_url);
            $user_info = json_decode($user_info_response, true);
            
            if ($user_info) {
                handleOAuthUser($pdo, $user_info, 'google');
            }
        }
        
    } elseif ($state === 'apple') {
        // Handle Apple OAuth
        $token_url = 'https://appleid.apple.com/auth/token';
        $token_data = [
            'client_id' => $apple_client_id,
            'client_secret' => $apple_client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $apple_redirect_uri
        ];
        
        // Get access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $token_response = curl_exec($ch);
        curl_close($ch);
        
        $token_info = json_decode($token_response, true);
        
        if (isset($token_info['id_token'])) {
            // Decode JWT token untuk mendapatkan user info
            $jwt_parts = explode('.', $token_info['id_token']);
            $jwt_payload = json_decode(base64_decode($jwt_parts[1]), true);
            
            if ($jwt_payload) {
                $user_info = [
                    'id' => $jwt_payload['sub'],
                    'email' => $jwt_payload['email'] ?? '',
                    'name' => $jwt_payload['name'] ?? $jwt_payload['email'] ?? 'Apple User'
                ];
                
                handleOAuthUser($pdo, $user_info, 'apple');
            }
        }
    }
}

function handleOAuthUser($pdo, $user_info, $provider) {
    try {
        $oauth_id = $user_info['id'];
        $email = $user_info['email'] ?? '';
        $name = $user_info['name'] ?? ($provider == 'google' ? $user_info['given_name'] : 'User');
        
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
        header("Location: login.php?error=" . urlencode("OAuth login failed: " . $e->getMessage()));
        exit();
    }
}

function generateUniqueUsername($pdo, $name, $provider) {
    // Clean name and create base username
    $clean_name = preg_replace('/[^a-zA-Z0-9]/', '', $name);
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

// Jika tidak ada code atau state, redirect ke login
header("Location: login.php?error=" . urlencode("Invalid OAuth response"));
exit();
?>