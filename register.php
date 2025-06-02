<?php
// register.php
require_once 'db.php';
require_once 'oauth_config.php'; 


// Proses form jika ada data POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    $success_message = '';
    
    // Validasi input
    if (empty($username)) {
        $errors[] = "Username harus diisi";
    }
    
    if (empty($password)) {
        $errors[] = "Password harus diisi";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Password dan konfirmasi password tidak sama";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password minimal 6 karakter";
    }
    
    // Jika tidak ada error, proses registrasi
    if (empty($errors)) {
        try {
            // Hash password dulu SEBELUM dipakai
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Cek apakah username sudah ada
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $existing_user = $check_stmt->get_result()->fetch_assoc();
            
            if ($existing_user) {
                $errors[] = "Username sudah terdaftar";
            } else {
                // Simpan ke database - untuk registrasi regular pakai kolom username
                $stmt = $conn->prepare("INSERT INTO users (username, password, registration_type) VALUES (?, ?, 'regular')");
                $stmt->bind_param("ss", $username, $hashed_password);

                if ($stmt->execute()) {
                    $success_message = "Registrasi berhasil! Silakan login.";
                    // Reset form values
                    $username = '';
                } else {
                    $errors[] = "Terjadi kesalahan saat registrasi.";
                }
            }
        } catch (mysqli_sql_exception $e) {
            // Handle duplicate entry error atau error lainnya
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errors[] = "Username sudah terdaftar";
            } else {
                $errors[] = "Terjadi kesalahan saat registrasi.";
            }
        }
    }
}

// Handle OAuth callback
if (isset($_GET['code']) && isset($_GET['state'])) {
    $provider = $_GET['state']; // google atau apple
    
    if ($provider === 'google') {
        $result = handleGoogleCallback($_GET['code']);
    } elseif ($provider === 'apple') {
        $result = handleAppleCallback($_GET['code']);
    }
    
    if ($result['success']) {
        // Cek apakah user sudah ada berdasarkan oauth_id dan provider
        $stmt = $conn->prepare("SELECT * FROM users WHERE oauth_id = ? AND oauth_provider = ?");
        $stmt->bind_param("ss", $result['oauth_id'], $provider);
        $stmt->execute();
        $existing_user = $stmt->get_result()->fetch_assoc();
        
        if ($existing_user) {
            // User sudah ada, login langsung
            $_SESSION['user_id'] = $existing_user['id'];
            $_SESSION['username'] = $existing_user['username'] ?: $existing_user['display_name'];
            $_SESSION['success'] = "Login dengan " . ucfirst($provider) . " berhasil!";
            header("Location: index.php?page=login");
            exit();
        } else {
            // Cek berdasarkan email untuk mencegah duplikasi email
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $result['email']);
            $stmt->execute();
            $existing_email = $stmt->get_result()->fetch_assoc();
            
            if ($existing_email) {
                $errors[] = "Email ini sudah terdaftar dengan metode lain.";
            } else {
                try {
                    // Registrasi user baru dengan OAuth - pakai display_name
                    $registration_type = 'oauth';
                    $stmt = $conn->prepare("INSERT INTO users (display_name, email, oauth_id, oauth_provider, registration_type) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $result['name'], $result['email'], $result['oauth_id'], $provider, $registration_type);
                    
                    if ($stmt->execute()) {
                        $_SESSION['user_id'] = $conn->insert_id;
                        $_SESSION['username'] = $result['name'];
                        $_SESSION['success'] = "Registrasi dengan " . ucfirst($provider) . " berhasil!";
                        header("Location: index.php?page=login");
                        exit();
                    } else {
                        $errors[] = "Terjadi kesalahan saat registrasi dengan " . ucfirst($provider);
                    }
                } catch (mysqli_sql_exception $e) {
                    $errors[] = "Terjadi kesalahan saat registrasi dengan " . ucfirst($provider);
                }
            }
        }
    } else {
        $errors[] = $result['error'];
    }
}

function handleGoogleCallback($code) {
    global $google_client_id, $google_client_secret, $google_redirect_uri;
    
    // Exchange code for access token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'client_id' => $google_client_id,
        'client_secret' => $google_client_secret,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $google_redirect_uri
    ];
    
    $token_response = makeHttpRequest($token_url, 'POST', $token_data);
    
    if (!$token_response || !isset($token_response['access_token'])) {
        return ['success' => false, 'error' => 'Gagal mendapatkan token dari Google'];
    }
    
    // Get user info
    $user_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_response['access_token'];
    $user_response = makeHttpRequest($user_url, 'GET');
    
    if (!$user_response) {
        return ['success' => false, 'error' => 'Gagal mendapatkan informasi user dari Google'];
    }
    
    return [
        'success' => true,
        'oauth_id' => $user_response['id'],
        'name' => $user_response['name'],
        'email' => $user_response['email']
    ];
}

function handleAppleCallback($code) {
    global $apple_client_id, $apple_client_secret, $apple_redirect_uri;
    
    // Apple Sign In menggunakan JWT, implementasi lebih kompleks
    // Untuk demo, kita simulasikan response
    return [
        'success' => true,
        'oauth_id' => 'apple_' . uniqid(),
        'name' => 'Apple User',
        'email' => 'user@apple.example.com'
    ];
}

function makeHttpRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return false;
    }
    
    return json_decode($response, true);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Account</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative; 
        }

        .back-arrow {
            position: absolute; 
            top: 20px; 
            left: 20px; 
            font-size: 24px;
            color: #333;
            text-decoration: none;
            cursor: pointer;
            z-index: 10; 
        }

        .back-arrow:hover {
            color: #666;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 40px;
            color: #333;
            letter-spacing: 2px;
            margin-top: 20px; 
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .form-container {
                flex-direction: column;
                gap: 20px;
            }

            h1 {
                font-size: 2rem;
                margin-bottom: 30px;
            }

            .back-arrow {
                top: 15px; 
                left: 15px; 
            }
        }

        h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 40px;
            color: #333;
            letter-spacing: 2px;
        }

        .form-container {
            display: flex;
            gap: 40px;
            align-items: flex-start;
        }

        .form-left {
            flex: 1;
        }

        .form-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 15px 20px;
            font-size: 1rem;
            border: none;
            background-color: #FFD700;
            border-radius: 5px;
            outline: none;
            transition: background-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            background-color: #FFC700;
        }

        input::placeholder {
            color: #666;
        }

        .register-btn {
            background-color: #888;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .register-btn:hover {
            background-color: #666;
        }

        .social-btn {
            padding: 15px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            color: white;
        }

        .social-btn img {
            height: 20px;
            width: auto;
            margin-right: 10px;
        }

        .google-btn {
            background-color: #4285F4;
        }

        .apple-btn {
            background-color:  #ff1493;
        }

        .social-btn:hover {
            opacity: 0.9;
        }

        .error-messages {
            background-color: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }

        .error-messages ul {
            list-style-type: none;
        }

        .error-messages li {
            margin-bottom: 5px;
        }

        .info-messages {
            background-color: #e3f2fd;
            color: #1565c0;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #1565c0;
        }

        .loading {
            pointer-events: none;
            opacity: 0.6;
        }

        .loading::after {
            content: "...";
            animation: dots 1s infinite;
        }

        @keyframes dots {
            0%, 20% { content: "..."; }
            40% { content: ".."; }
            60% { content: "."; }
            80%, 100% { content: ""; }
        }

        /* Custom Alert Styles */
        .custom-alert {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .alert-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 400px;
            min-width: 300px;
        }

        .alert-box.error {
            border-top: 5px solid #f44336;
        }

        .alert-box.success {
            border-top: 5px solid #4CAF50;
        }

        .alert-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .alert-box.error .alert-icon {
            color: #f44336;
        }

        .alert-box.success .alert-icon {
            color: #4CAF50;
        }

        .alert-message {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #333;
        }

        .alert-btn {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }

        .alert-btn:hover {
            background-color: #555;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .form-container {
                flex-direction: column;
                gap: 20px;
            }

            h1 {
                font-size: 2rem;
                margin-bottom: 30px;
            }

            .back-arrow {
                top: 20px;
                left: 20px;
            }
        }
    </style>
</head>
<body>

    
    <div class="container">
        <h1>REGISTER ACCOUNT</h1>
        <a href="index.php?page=halaman3" class="back-arrow">←</a>
        
        <div class="form-container">
            <div class="form-left">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="Enter your Username"
                            value="<?php echo htmlspecialchars($username ?? ''); ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter Password"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Enter Password"
                            required
                        >
                    </div>
                    
                    <button type="submit" class="register-btn">REGISTER</button>
                </form>
            </div>
            
            <div class="form-right">
                <a href="#" class="social-btn google-btn" id="google-btn" onclick="registerWithGoogle()">
                    <img src="google.png" alt="Google Logo">
                    REGISTER WITH GOOGLE
                </a>
                
                <a href="#" class="social-btn apple-btn" id="apple-btn" onclick="registerWithApple()">
                    <img src="apple.png" alt="Apple Logo">
                    REGISTER WITH APPLE
                </a>
            </div>
        </div>
    </div>

    <script>
        // Function to show custom alert
        function showAlert(message, type = 'error') {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'custom-alert';
            
            const icon = type === 'error' ? '❌' : '✅';
            
            alertDiv.innerHTML = `
                <div class="alert-box ${type}">
                    <div class="alert-icon">${icon}</div>
                    <div class="alert-message">${message}</div>
                    <button class="alert-btn" onclick="closeAlert(this)">OK</button>
                </div>
            `;
            
            document.body.appendChild(alertDiv);
        }

        function closeAlert(btn) {
            const alertDiv = btn.closest('.custom-alert');
            alertDiv.remove();
        }

        // Show alerts if there are PHP messages
        <?php if (!empty($errors)): ?>
            <?php if (in_array("Username sudah terdaftar", $errors)): ?>
                showAlert('Error: Username Sudah Terdaftar', 'error');
            <?php else: ?>
                showAlert('<?php echo implode("\\n", array_map("addslashes", $errors)); ?>', 'error');
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            showAlert('<?php echo addslashes($success_message); ?>', 'success');
        <?php endif; ?>

        function registerWithGoogle() {
            const btn = document.getElementById('google-btn');
            btn.classList.add('loading');
            btn.innerHTML = '<img src="google.png" alt="Google Logo">Connecting to Google';
            
            // Redirect ke Google OAuth
            const clientId = '<?php echo $google_client_id ?? ""; ?>';
            const redirectUri = encodeURIComponent('<?php echo $google_redirect_uri ?? ""; ?>');
            const scope = encodeURIComponent('openid profile email');
            const state = 'google';
            
            const googleAuthUrl = `https://accounts.google.com/o/oauth2/v2/auth?client_id=${clientId}&redirect_uri=${redirectUri}&scope=${scope}&response_type=code&state=${state}`;
            
            window.location.href = googleAuthUrl;
        }

        function registerWithApple() {
            showAlert('Mohon maaf, Registrasi Menggunakan Apple account belum tersedia', 'error');
            const btn = document.getElementById('apple-btn');
            btn.classList.add('loading');
            btn.innerHTML = '<img src="apple.png" alt="Apple Logo">Connecting to Apple';
            
            // Redirect ke Apple OAuth
            //const clientId = '<?php echo $apple_client_id ?? ""; ?>';
            //const redirectUri = encodeURIComponent('<?php echo $apple_redirect_uri ?? ""; ?>');
            //const scope = encodeURIComponent('name email');
           // const state = 'apple';
            
            //const appleAuthUrl = `https://appleid.apple.com/auth/authorize?client_id=${clientId}&redirect_uri=${redirectUri}&scope=${scope}&response_type=code&response_mode=form_post&state=${state}`;
            
           // window.location.href = appleAuthUrl;
        }

        // Validasi real-time untuk konfirmasi password
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword && confirmPassword !== '') {
                this.style.backgroundColor = '#ffcccb';
            } else {
                this.style.backgroundColor = '#FFD700';
            }
        });

        // Cek jika ada parameter error di URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error')) {
            showAlert('OAuth Error: ' + urlParams.get('error_description'), 'error');
        }
    </script>
</body>
</html>