<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\ScannerSubWalkDiagnostic;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-sub-walk-' . bin2hex(random_bytes(6));
$dir = 'wp-content/uploads/2026/05';

try {
    wordpress_scanner_sub_walk_write($root, $dir . '/library/original.jpg', 'original wordpress media');
    wordpress_scanner_sub_walk_write($root, $dir . '/not-a-directory', 'regular media file');

    $linkedDirName = $dir . '/linked-library';
    $linkedDirPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $linkedDirName);
    if (!@symlink('library', $linkedDirPath)) {
        throw new RuntimeException('Failed to create media directory symlink');
    }

    $scanner = new FileInfoScanner($root);
    $subs = [
        'directAlias' => $linkedDirName,
        'belowAlias' => $linkedDirName . '/original.jpg',
        'belowRegularFile' => $dir . '/not-a-directory/child.jpg',
        'belowMissingParent' => $dir . '/missing-parent/child.jpg',
        'missingDirectUpload' => $dir . '/missing-direct.jpg',
    ];

    $diagnostics = [];
    foreach ($subs as $label => $sub) {
        $diagnostics[$label] = $scanner->diagnoseSubWalk($sub)->toArray();
    }

    echo json_encode([
        'folder' => 'wordpress-media',
        'diagnostics' => $diagnostics,
        'blockedStatuses' => [
            ScannerSubWalkDiagnostic::STATUS_TRAVERSES_SYMLINK,
            ScannerSubWalkDiagnostic::STATUS_NOT_A_DIRECTORY,
        ],
        'canonicalMediaAdvertised' => array_map(
            static fn ($file): string => $file->name,
            $scanner->walk([$dir . '/library/original.jpg']),
        ),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_sub_walk_rm($root);
}

function wordpress_scanner_sub_walk_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner sub-walk example directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner sub-walk example file');
    }
}

function wordpress_scanner_sub_walk_rm(string $path): void
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
        wordpress_scanner_sub_walk_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
