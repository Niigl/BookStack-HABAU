<?php

// Session starten und prüfen ob User eingeloggt ist
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    Illuminate\Http\Request::capture()
);

if (!auth()->check()) {
    http_response_code(403);
    die('Access denied');
}

// S3 Konfiguration aus .env laden
$key = env('STORAGE_S3_KEY');
$secret = env('STORAGE_S3_SECRET');
$bucket = env('STORAGE_S3_BUCKET');
$endpoint = env('STORAGE_S3_ENDPOINT');

// Pfad aus URL holen
$path = $_GET['path'] ?? '';
$path = ltrim($path, '/');

if (empty($path)) {
    http_response_code(404);
    die('Not found');
}

// Datei von S3 holen
$disk = Illuminate\Support\Facades\Storage::disk('s3');

if (!$disk->exists($path)) {
    http_response_code(404);
    die('Image not found');
}

$content = $disk->get($path);
$mime = $disk->mimeType($path) ?: 'image/png';
$size = $disk->size($path);

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=86400');
echo $content;
exit;