<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanEventCollector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-failure-events-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $logged = [];
    $collector = new FolderScanEventCollector(
        'wordpress-media',
        static function (string $type, array $data) use (&$logged): void {
            $logged[] = ['type' => $type, 'data' => $data];
        },
    );
    $scanner = new FileInfoScanner(
        $root,
        directoryLister: static fn (string $path): array => throw new RuntimeException('media volume temporarily unavailable'),
    );

    $aborted = false;
    try {
        $scanner->walk(failureLogger: $collector->failureLogger());
    } catch (RuntimeException) {
        $aborted = true;
    }

    echo json_encode([
        'folder' => 'wordpress-media',
        'aborted' => $aborted,
        'failureEvents' => $collector->failureEvents(),
        'loggedEvents' => $logged,
        'scanErrors' => $collector->scanErrors(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_failure_events_rm($root);
}

function wordpress_scanner_failure_events_rm(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        wordpress_scanner_failure_events_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
