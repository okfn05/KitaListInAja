<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Halaman Logo</title>
    <style>
        body {
            margin: 0;
            background-color: white;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            font-family: sans-serif;
        }

        .title {
            font-size: 60px;
            font-weight: bold;
            color: #000;
            margin: 0 0 20px 0;
        }

        .logo {
            width: 600px;
            height: auto;
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 18px;
            color: #555;
            margin: 0 0 20px 0;
            text-align: center;
            max-width: 500px;
        }

        .button {
            width: 250px;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 10px 0;
        }

        .login-button {
            background-color: yellow;
            color: black;
        }

        .login-button:hover {
            background-color: gold;
        }

        .create-button {
            background-color: #ccc;
            color: black;
        }

        .create-button:hover {
            background-color: #bbb;
        }

        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 24px;
            background: none;
            border: none;
            color: #000;
            cursor: pointer;
        }

        .back-button:hover {
            color: #555;
        }
    </style>
</head>
<body>
    <!-- Tombol kembali -->
    <button class="back-button" onclick="window.location.href='index.php?page=halaman2'">&#8592;</button>

    <!-- Judul -->
    <h1 class="title">Welcome to</h1>

    <!-- Logo -->
    <img src="logo.png" alt="Logo" class="logo">

    <!-- Subjudul -->
    <p class="subtitle">Please login to your account or create new account to continue</p>

    <!-- Tombol Login -->
    <button class="button login-button" onclick="window.location.href='index.php?page=login'">Login</button>

    <!-- Tombol Create New Account -->
    <button class="button create-button" onclick="window.location.href='index.php?page=register'">Create New Account</button>
</body>
</html>
