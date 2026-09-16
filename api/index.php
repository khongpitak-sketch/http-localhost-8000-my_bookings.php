<?php
// api/index.php - Vercel Serverless Entry Point & Router

$rootDir = realpath(__DIR__ . '/..');
chdir($rootDir);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');

// 1. Root / index.php
if ($path === '' || $path === 'index.php') {
    require $rootDir . '/index.php';
    exit;
}

// 2. Direct match for PHP files (e.g. book.php, login.php, my_bookings.php)
$targetFile = $rootDir . '/' . $path;
if (is_file($targetFile) && pathinfo($targetFile, PATHINFO_EXTENSION) === 'php') {
    require $targetFile;
    exit;
}

// 3. Extensionless match (e.g. /book -> book.php)
if (is_file($targetFile . '.php')) {
    require $targetFile . '.php';
    exit;
}

// 4. Static assets fallback (css, js, images)
if (is_file($targetFile)) {
    $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $mimes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf'
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    readfile($targetFile);
    exit;
}

// 404 Fallback
http_response_code(404);
echo "404 Not Found";