<?php

$publicRoot = realpath(__DIR__);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestedFile = realpath($publicRoot . DIRECTORY_SEPARATOR . ltrim(rawurldecode($requestPath), '/\\'));
$publicPrefix = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if (
    $requestedFile !== false
    && is_file($requestedFile)
    && str_starts_with(strtolower($requestedFile), strtolower($publicPrefix))
    && strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION)) !== 'php'
) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicRoot . DIRECTORY_SEPARATOR . 'index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require $_SERVER['SCRIPT_FILENAME'];
