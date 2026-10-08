<?php

declare(strict_types=1);

// Minimal asynchronous upload endpoint for examples/file-upload/filepond-async.php.
// Speaks the FilePond FormPostStore protocol:
//
//   POST multipart file  → plain/text upload id
//   GET  ?id=...         → stored bytes (HEAD answers metadata headers only)
//   DELETE ?id=...       → release, 200 OK
//
// Serve with: php -S 127.0.0.1:8080 -t examples/file-upload
// Then open http://127.0.0.1:8080/filepond-async.php with DEMO_CSRF_TOKEN set.
//
// Separate requests need their own protection: the token travels in the
// X-CSRF-Token header (see resolveRequest in filepond-async.php). The check
// below is illustrative; use your framework CSRF in production.

$expected = $_SERVER['DEMO_CSRF_TOKEN'] ?? '';
$expected = is_string($expected) ? $expected : '';
$provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Invalid CSRF token. Set DEMO_CSRF_TOKEN and use your framework CSRF in production.';
    exit();
}

$dir = sys_get_temp_dir() . '/kaly-forms-demo-uploads';
if (!is_dir($dir)) {
    mkdir($dir, 0o777, true);
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$id = $_GET['id'] ?? null;
$id = is_string($id) && preg_match('/^[0-9a-f]{32}$/', $id) === 1 ? $id : null;

if ($method === 'POST') {
    $stored = null;
    $raw = $_FILES['documents'] ?? null;
    $names = is_array($raw) ? $raw['name'] ?? null : null;
    if (is_string($names)) {
        $names = [$names];
    } elseif (!is_array($names)) {
        $names = [];
    } else {
        $names = array_values($names);
    }
    $tmps = is_array($raw) && isset($raw['tmp_name']) && is_array($raw['tmp_name']) ? array_values($raw['tmp_name']) : [];
    foreach ($names as $i => $name) {
        if (!is_string($name)) {
            continue;
        }
        $tmp = isset($tmps[$i]) && is_string($tmps[$i]) ? $tmps[$i] : '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            continue;
        }
        $stored = bin2hex(random_bytes(16));
        move_uploaded_file($tmp, $dir . '/' . $stored . '.bin');
        file_put_contents($dir . '/' . $stored . '.name', $name);
        break;
    }
    if ($stored === null) {
        http_response_code(422);
        header('Content-Type: text/plain');
        echo 'No file received.';
        exit();
    }
    header('Content-Type: text/plain');
    echo $stored;
    exit();
}

if ($id === null) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Missing upload id.';
    exit();
}

$path = $dir . '/' . $id . '.bin';
if (!is_file($path)) {
    http_response_code(404);
    exit();
}

if ($method === 'DELETE') {
    unlink($path);
    $sidecar = $dir . '/' . $id . '.name';
    if (is_file($sidecar)) {
        unlink($sidecar);
    }
    exit();
}

if ($method === 'GET' || $method === 'HEAD') {
    $sidecar = $dir . '/' . $id . '.name';
    $name = is_file($sidecar) && is_string($content = file_get_contents($sidecar)) ? $content : $id;
    $size = filesize($path);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . ($size === false ? '0' : (string) $size));
    header('X-File-Name: ' . $name);
    if ($method === 'GET') {
        readfile($path);
    }
    exit();
}

http_response_code(405);
