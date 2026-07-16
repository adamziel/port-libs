<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/markerpdf-server-config-smoke-' . bin2hex(random_bytes(4));
if (!mkdir($root, 0777, true) && !is_dir($root)) {
    throw new RuntimeException('Unable to create markerPDF server config smoke folder.');
}

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

try {
    $adapter = new MarkerServerAdapter();
    $uploadDirectory = $root . DIRECTORY_SEPARATOR . 'uploads';
    $remoteConfig = $adapter->serverConfigErrorBoundary([
        'host' => '0.0.0.0',
        'port' => '8173',
        'api_key' => 'wp-demo-key-not-printed',
        'datalab_url' => 'https://api.example/marker',
        'upload_directory' => $uploadDirectory,
        'ensure_upload_directory' => true,
    ]);
    $invalidPort = $adapter->serverConfigErrorBoundary([
        'port' => '0',
        'upload_directory' => $root . DIRECTORY_SEPARATOR . 'invalid-uploads',
        'ensure_upload_directory' => true,
    ]);
    $blockedDirectory = $adapter->serverConfigErrorBoundary(
        [
            'upload_directory' => $root . DIRECTORY_SEPARATOR . 'blocked-uploads',
            'ensure_upload_directory' => true,
        ],
        static fn (): bool => false
    );

    if (($remoteConfig['success'] ?? null) !== true) {
        throw new RuntimeException('Expected marker server config smoke to produce a remote startup plan.');
    }
    if (($invalidPort['success'] ?? null) !== false || !str_contains((string) $invalidPort['error'], 'port')) {
        throw new RuntimeException('Expected invalid port to be reported as a config error payload.');
    }
    if (($blockedDirectory['success'] ?? null) !== false || !str_contains((string) $blockedDirectory['error'], 'upload folder')) {
        throw new RuntimeException('Expected upload-directory failure to be reported as a config error payload.');
    }

    $config = $remoteConfig['config'];
    $payload = [
        'scenario' => 'wordpress-marker-runtime-server-config-error-boundary-currentbase',
        'purpose' => 'Plan marker_server.py host/port/API-key/Datalab/upload-directory startup state for a WordPress import service without launching Uvicorn, FastAPI, Python, or model code.',
        'source' => 'sddai/markerPDF marker_server.py::main plus import-time UPLOAD_DIRECTORY setup',
        'remote_success' => $remoteConfig['success'],
        'remote_local_mode' => $config['local'],
        'api_key_configured' => $config['api_key_configured'],
        'raw_api_key_exposed' => str_contains(json_encode($config, JSON_THROW_ON_ERROR), 'wp-demo-key-not-printed'),
        'app_state' => $config['app_state'],
        'uvicorn' => $config['uvicorn'],
        'upload_directory_status' => $config['upload_directory_status'],
        'upload_directory_created' => $config['upload_directory_created'],
        'upload_directory_exists' => is_dir($uploadDirectory),
        'invalid_port_success' => $invalidPort['success'],
        'invalid_port_error' => $invalidPort['error'],
        'blocked_directory_success' => $blockedDirectory['success'],
        'blocked_directory_error' => $blockedDirectory['error'],
        'executes_uvicorn' => false,
        'executes_fastapi' => false,
        'executes_python_or_models' => false,
        'executes_live_http' => false,
        'executes_external_pdf_tools' => false,
    ];

    if ($payload['raw_api_key_exposed'] !== false) {
        throw new RuntimeException('Expected server config smoke to redact the API key.');
    }
    if ($payload['remote_local_mode'] !== false || $payload['api_key_configured'] !== true) {
        throw new RuntimeException('Expected API key config to select remote marker server mode.');
    }
    if ($payload['upload_directory_status'] !== 'created' || $payload['upload_directory_exists'] !== true) {
        throw new RuntimeException('Expected upload directory to be created by the config boundary.');
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
