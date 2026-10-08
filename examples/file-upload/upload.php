<?php

declare(strict_types=1);

// Minimal synchronous upload endpoint for examples/file-upload/basic.php
// and examples/file-upload/filepond.php.
//
// Serve with: php -S 127.0.0.1:8080 -t examples/file-upload
// Then open http://127.0.0.1:8080/basic.php with DEMO_CSRF_TOKEN set.
//
// Reads native multipart uploads only. Validation, storage policy and real
// CSRF belong to the application; the token check below is illustrative.
//
// SECURITY: uploads are stored OUTSIDE the served directory under a random
// identifier with a neutral extension. Never store uploads under their
// original name inside the document root: a submitted "shell.php" would
// become directly executable over HTTP.

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit();
}

$expected = $_SERVER['DEMO_CSRF_TOKEN'] ?? '';
$expected = is_string($expected) ? $expected : '';
$posted = $_POST['_csrf'] ?? '';
if ($expected === '' || !is_string($posted) || !hash_equals($expected, $posted)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Invalid CSRF token. Set DEMO_CSRF_TOKEN and use your framework CSRF in production.';
    exit();
}

$dir = demoStoreDir();

$stored = 0;
foreach (demoUploads($_FILES['documents'] ?? null) as $file) {
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        continue;
    }
    $id = bin2hex(random_bytes(16));
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $id . '.bin')) {
        $stored++;
    }
}

header('Content-Type: text/plain');
echo "Stored {$stored} file(s).";

/** @return list<array{name: string, tmp_name: string, error: int}> */
function demoUploads(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $names = $raw['name'] ?? null;
    if (is_string($names)) {
        return [demoSingleUpload($names, $raw['tmp_name'] ?? null, $raw['error'] ?? null)];
    }
    if (!is_array($names)) {
        return [];
    }
    $out = [];
    foreach ($names as $i => $name) {
        if (!is_string($name)) {
            continue;
        }
        $tmps = $raw['tmp_name'] ?? null;
        $errors = $raw['error'] ?? null;
        $tmp = is_array($tmps) && array_key_exists($i, $tmps) && is_string($tmps[$i]) ? $tmps[$i] : '';
        $error = is_array($errors) && array_key_exists($i, $errors) && is_int($errors[$i]) ? $errors[$i] : UPLOAD_ERR_NO_FILE;
        $out[] = ['name' => $name, 'tmp_name' => $tmp, 'error' => $error];
    }
    return $out;
}

/** @return array{name: string, tmp_name: string, error: int} */
function demoSingleUpload(string $name, mixed $tmp, mixed $error): array
{
    return [
        'name' => $name,
        'tmp_name' => is_string($tmp) ? $tmp : '',
        'error' => is_int($error) ? $error : UPLOAD_ERR_NO_FILE,
    ];
}

function demoStoreDir(): string
{
    // Outside the served directory on purpose: nothing stored here is
    // reachable (and therefore executable) over HTTP.
    $dir = sys_get_temp_dir() . '/kaly-forms-demo-uploads-sync';
    if (!is_dir($dir)) {
        mkdir($dir, 0o700, true);
    }
    return $dir;
}
