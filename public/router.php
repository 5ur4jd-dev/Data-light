<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPath = __DIR__;

// Route any /api/* request to the project api folder outside public
if (strpos($uri, '/api/') === 0) {
    $apiFile = realpath(__DIR__ . '/../' . ltrim($uri, '/'));
    if ($apiFile && is_file($apiFile)) {
        return require $apiFile;
    }
}

// Serve the SPA entrypoint for the root path or if no static asset is found.
if ($uri === '/' || $uri === '/index.html') {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($publicPath . '/index.html');
    return true;
}

$staticFile = $publicPath . $uri;
if ($uri !== '/' && file_exists($staticFile) && !is_dir($staticFile)) {
    return false;
}

header('Content-Type: text/html; charset=UTF-8');
readfile($publicPath . '/index.html');
