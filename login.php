<?php


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

// Handle form submission
if ($_POST && isset($_POST['login_type']) && $_POST['login_type'] === 'regular') {
    $input_username = $_POST['username'] ?? '';
    $input_password = $_POST['password'] ?? '';
    
    if (!empty($input_username) && !empty($input_password)) {
        try {
            // Query untuk mencari user berdasarkan username
            $stmt = $pdo->prepare("SELECT id, username, password, email, registration_type FROM users WHERE username = ? AND registration_type = 'regular'");
            $stmt->execute([$input_username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($input_password, $user['password'])) {
                // Login berhasil
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['display_name'] = $user ['display_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['logged_in'] = true;
                
                // Redirect ke dashboard atau halaman utama
                header("Location: index.php?page=dashboard");
                exit();
            } else {
                $error = "Username atau password salah";
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all fields";
    }
}

// Handle OAuth login 
if ($_POST && isset($_POST['oauth_provider'])) {
    $provider = $_POST['oauth_provider'];
    
    if ($provider === 'google') {
        // Redirect ke Google OAuth
        $google_auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
            'client_id' => '583572695554-5ajesvomumkt4nnp636kps3fo7cc99no.apps.googleusercontent.com',
            'redirect_uri' => 'http://localhost/Kitalistinaja/oauth_callback.php',
            'scope' => 'email profile',
            'response_type' => 'code',
            'state' => 'google'
        ]);
        header("Location: " . $google_auth_url);
        exit();
    }
    
//     if ($provider === 'apple') {
//         // Redirect ke Apple OAuth
//         $apple_auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
//             'client_id' => 'YOUR_APPLE_CLIENT_ID',
//             'redirect_uri' => 'http://yoursite.com/oauth_callback.php',
//             'scope' => 'email name',
//             'response_type' => 'code',
//             'state' => 'apple'
//         ]);
//         header("Location: " . $apple_auth_url);
//         exit();
//     }
 }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Account</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .back-arrow {
            font-size: 24px;
            margin-bottom: 30px;
            cursor: pointer;
            display: inline-block;
        }

        .title {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 40px;
            line-height: 1.2;
        }

        .form-container {
            display: flex;
            gap: 40px;
            align-items: flex-start;
        }

        .left-section {
            flex: 1;
        }

        .right-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 30px;
        }

        .form-group label {
            display: block;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            font-size: 16px;
            border: none;
            background-color: #FFD700;
            border-radius: 5px;
            outline: none;
        }

        .form-group input::placeholder {
            color: #666;
        }

        .login-btn {
            background-color: #ccc;
            color: black;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-transform: uppercase;
        }

        .login-btn:hover {
            background-color: #bbb;
        }

        .social-btn {
            padding: 15px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
            color: white;
            width: 100%; /* Membuat tombol mengisi penuh container */
            min-width: 280px; /* Lebar minimum untuk konsistensi */
            box-sizing: border-box;
        }

        .social-btn img {
            width: 20px;
            height: 20px;
            object-fit: contain;
        }

        .google-btn {
            background-color: #4285f4;
        }

        .google-btn:hover {
            background-color: #357ae8;
        }

        .apple-btn {
            background: linear-gradient(45deg, #ff69b4, #ff1493);
        }

        .apple-btn:hover {
            background: linear-gradient(45deg, #ff1493, #dc143c);
        }

        .separator {
            width: 2px;
            background-color: #ddd;
            margin: 0 20px;
        }

        .error {
            color: red;
            margin-bottom: 10px;
            font-size: 14px;
            padding: 10px;
            background-color: #ffe6e6;
            border: 1px solid #ff9999;
            border-radius: 5px;
        }

        .success {
            color: green;
            margin-bottom: 10px;
            font-size: 14px;
            padding: 10px;
            background-color: #e6ffe6;
            border: 1px solid #99ff99;
            border-radius: 5px;
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
            .form-container {
                flex-direction: column;
                gap: 20px;
            }
            
            .separator {
                width: 100%;
                height: 2px;
                margin: 20px 0;
            }
            
            .title {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-arrow" onclick="window.location.href='index.php?page=halaman3'">←</div>

        <h1 class="title">LOG IN<br>ACCOUNT</h1>
        
        <div class="form-container">
            <div class="left-section">
                <form method="POST" action="">
                    <input type="hidden" name="login_type" value="regular">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="Enter your Username"
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
                    
                    <button type="submit" class="login-btn">LOG IN</button>
                </form>
            </div>
            
            <div class="separator"></div>
            
            <div class="right-section">
                <form method="POST" action="" style="margin-bottom: 20px;">
                    <input type="hidden" name="oauth_provider" value="google">
                    <button type="submit" class="social-btn google-btn">
                        <img src="google.png" alt="Google"> LOG IN WITH GOOGLE
                    </button>
                </form>
                               
                <button type="button" class="social-btn apple-btn" onclick="loginWithApple()">
                    <img src="apple.png" alt="Apple"> LOG IN WITH APPLE
                </button>
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

        // Function untuk login dengan Apple
        function loginWithApple() {
            showAlert('Mohon maaf, Login Menggunakan Apple account belum tersedia', 'error');
        }
    </script>
</body>
</html>