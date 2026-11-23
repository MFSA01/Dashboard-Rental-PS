<?php
/**
 * config/database.php
 * Koneksi database MySQL (prosedural mysqli) untuk Davin's Gaming Space.
 */

date_default_timezone_set('Asia/Jakarta');

// Matikan exception otomatis mysqli agar kegagalan bisa ditangani manual dengan rapi.
mysqli_report(MYSQLI_REPORT_OFF);

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'rental_3551';
$dbPort = 3306;

$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if ($conn === false) {
    error_log('[DGS] Koneksi database gagal: ' . mysqli_connect_error());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Koneksi Database Gagal</title>
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 24px;
                background: #0B0F0D;
                color: #F0FDF4;
                font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            }
            .error-card {
                width: 100%;
                max-width: 480px;
                padding: 28px;
                border: 1px solid #23332B;
                border-radius: 18px;
                background: #121815;
                box-shadow: 0 24px 60px rgba(0, 0, 0, .45);
            }
            .error-card h1 {
                margin: 0 0 10px;
                font-size: 18px;
                color: #4ADE80;
            }
            .error-card p {
                margin: 0;
                font-size: 13px;
                line-height: 1.7;
                color: #94A39B;
            }
            .error-card code {
                display: inline-block;
                margin-top: 16px;
                padding: 6px 10px;
                border-radius: 8px;
                background: #18221D;
                border: 1px solid #23332B;
                color: #FACC15;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <h1>Koneksi Database Gagal</h1>
            <p>
                Aplikasi tidak dapat terhubung ke database. Pastikan MySQL Laragon sudah berjalan
                dan database <strong>rental_3551</strong> sudah diimpor dari <code>database/schema.sql</code>
                serta <code>database/seed.sql</code>.
            </p>
            <code>rental_3551 &middot; 127.0.0.1:3306</code>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!mysqli_set_charset($conn, 'utf8mb4')) {
    error_log('[DGS] Gagal mengatur charset utf8mb4: ' . mysqli_error($conn));
}

// Samakan zona waktu MySQL dengan Asia/Jakarta (WIB).
if (!mysqli_query($conn, "SET time_zone = '+07:00'")) {
    error_log('[DGS] Gagal mengatur time_zone MySQL: ' . mysqli_error($conn));
}
