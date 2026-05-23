<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoComparison;
use PortLibs\Syncthing\FileInfoScanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-scanner-platform-' . bin2hex(random_bytes(6));
$name = 'wp-content/uploads/2026/05/imported-media.jpg';
$path = wordpress_scanner_platform_path($root, $name);

try {
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, 'local first wordpress media bytes');
    chmod($path, 0640);
    touch($path, 1_700_006_100);

    $xattrMap = [
        $path => [
            'user.wordpress.source' => 'playground-import',
            'user.wordpress.attachment-id' => '8842',
            'security.selinux' => 'host-local-noise',
        ],
    ];

    $scanner = new FileInfoScanner(
        $root,
        scanOwnership: true,
        scanXattrs: true,
        xattrFilter: static fn (string $xattrName): bool => str_starts_with($xattrName, 'user.wordpress.'),
        xattrLister: static fn (string $xattrPath): array => array_keys($xattrMap[$xattrPath] ?? []),
        xattrGetter: static fn (string $xattrPath, string $xattrName): ?string => $xattrMap[$xattrPath][$xattrName] ?? null,
    );

    $scanned = $scanner->scan($name, hashBlocks: true, blockSize: 16);
    $withoutHostXattrs = $scanned->withSequence(2);

    echo json_encode([
        'file' => $scanned->name,
        'owner' => [
            'uid' => $scanned->unixUid,
            'gid' => $scanned->unixGid,
        ],
        'xattrs' => $scanned->xattrs,
        'blocks' => count($scanned->blocks),
        'blocksHash' => $scanned->blocksHash,
        'equivalentIgnoringScannerSequence' => $scanned->isEquivalent($withoutHostXattrs),
        'equivalentWhenXattrsIgnored' => $scanned->isEquivalent(
            new FileInfo(
                name: $scanned->name,
                modifiedS: $scanned->modifiedS,
                size: $scanned->size,
                blocksHash: $scanned->blocksHash,
                permissions: $scanned->permissions,
                rawBlockSize: $scanned->rawBlockSize,
                blocks: $scanned->blocks,
                unixUid: $scanned->unixUid,
                unixGid: $scanned->unixGid,
            ),
            new FileInfoComparison(ignoreXattrs: true),
        ),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_scanner_platform_rm($root);
}

function wordpress_scanner_platform_path(string $root, string $name): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function wordpress_scanner_platform_rm(string $path): void
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
        wordpress_scanner_platform_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
