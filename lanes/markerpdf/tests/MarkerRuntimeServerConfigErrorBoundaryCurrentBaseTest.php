<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-server-config-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf server config test folder.');
    }

    return $path;
};

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

return [
    'plans marker server remote startup config without exposing api keys or running uvicorn' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $uploadDirectory = $root . DIRECTORY_SEPARATOR . 'uploads';
            $adapter = new MarkerServerAdapter();
            $response = $adapter->serverConfigErrorBoundary([
                'host' => '0.0.0.0',
                'port' => '8173',
                'api_key' => 'wp-secret-runtime-key',
                'datalab_url' => 'https://api.example/marker',
                'upload_directory' => $uploadDirectory,
                'ensure_upload_directory' => true,
            ]);

            $t->same(true, $response['success']);
            $config = $response['config'];
            $t->same('0.0.0.0', $config['host']);
            $t->same(8173, $config['port']);
            $t->same(false, $config['local']);
            $t->same(true, $config['api_key_configured']);
            $t->same(false, $config['app_state']['LOCAL']);
            $t->same('https://api.example/marker', $config['app_state']['DATALAB_URL']);
            $t->same(['app' => 'marker_server:app', 'host' => '0.0.0.0', 'port' => 8173], $config['uvicorn']);
            $t->same($uploadDirectory, $config['upload_directory_absolute']);
            $t->same('created', $config['upload_directory_status']);
            $t->same(true, $config['upload_directory_created']);
            $t->same(true, is_dir($uploadDirectory));
            $t->same(false, $config['loads_models_during_plan']);
            $t->same(false, $config['executes_uvicorn']);
            $t->same(false, $config['executes_fastapi']);
            $t->same(false, $config['executes_python_or_models']);
            $t->same(false, str_contains(json_encode($config, JSON_THROW_ON_ERROR), 'wp-secret-runtime-key'));
        } finally {
            $removeTree($root);
        }
    },
    'keeps upstream local mode when marker server api key is omitted' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $uploadDirectory = $root . DIRECTORY_SEPARATOR . 'planned-uploads';
            $plan = (new MarkerServerAdapter())->serverConfigPlan([
                'upload_directory' => $uploadDirectory,
            ]);

            $t->same('127.0.0.1', $plan['host']);
            $t->same(8000, $plan['port']);
            $t->same(true, $plan['local']);
            $t->same(false, $plan['api_key_configured']);
            $t->same(true, $plan['app_state']['LOCAL']);
            $t->same(MarkerServerAdapter::DEFAULT_DATALAB_URL, $plan['app_state']['DATALAB_URL']);
            $t->same($uploadDirectory, $plan['upload_directory_absolute']);
            $t->same('planned', $plan['upload_directory_status']);
            $t->same(false, is_dir($uploadDirectory));
            $t->same(false, $plan['executes_uvicorn']);
            $t->same(false, $plan['executes_python_or_models']);
        } finally {
            $removeTree($root);
        }
    },
    'returns config error payloads for invalid ports before server startup' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $response = (new MarkerServerAdapter())->serverConfigErrorBoundary([
                'port' => '0',
                'upload_directory' => $root . DIRECTORY_SEPARATOR . 'uploads',
                'ensure_upload_directory' => true,
            ]);

            $t->same(false, $response['success']);
            $t->same(null, $response['config']);
            $t->contains('Marker server port must be between 1 and 65535.', $response['error']);
            $t->same(false, is_dir($root . DIRECTORY_SEPARATOR . 'uploads'));
            $t->same(false, $response['executes_uvicorn']);
            $t->same(false, $response['executes_fastapi']);
            $t->same(false, $response['executes_python_or_models']);
        } finally {
            $removeTree($root);
        }
    },
    'returns config error payloads when upload directory initialization fails' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $uploadDirectory = $root . DIRECTORY_SEPARATOR . 'blocked-uploads';
            $attempted = [];
            $response = (new MarkerServerAdapter())->serverConfigErrorBoundary(
                [
                    'upload_directory' => $uploadDirectory,
                    'ensure_upload_directory' => true,
                ],
                static function (string $path) use (&$attempted): bool {
                    $attempted[] = $path;

                    return false;
                }
            );

            $t->same(false, $response['success']);
            $t->same(null, $response['config']);
            $t->same([$uploadDirectory], $attempted);
            $t->contains('Unable to create markerPDF upload folder', $response['error']);
            $t->same(false, is_dir($uploadDirectory));
            $t->same(false, $response['executes_uvicorn']);
            $t->same(false, $response['executes_fastapi']);
            $t->same(false, $response['executes_python_or_models']);
        } finally {
            $removeTree($root);
        }
    },
];
