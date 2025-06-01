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

        .logo {
            width: 600px;
            height: auto;
            margin-bottom: 20px;
        }

        .title {
            font-size: 36px;
            font-weight: bold;
            color: #000;
            margin: 0;
        }

        .subtitle {
            font-size: 18px;
            color: #555;
            margin-top: 10px;
            margin-bottom: 0;
        }

        .next-button {
            position: absolute;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: yellow;
            color: black;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .next-button:hover {
            background-color: gold;
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
    <button class="back-button" onclick="window.location.href='halaman1.php'">&#8592;</button>

    <img src="logo.png" alt="Logo" class="logo">
    <h1 class="title">MANAGE TASK</h1>
    <p class="subtitle">You can manage your own daily task</p>

    <button class="next-button" onclick="window.location.href='halaman3.php'">Get Started</button>
</body>
</html>
