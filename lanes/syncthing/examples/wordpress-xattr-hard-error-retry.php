<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PlatformMetadataApplier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-xattr-hard-error-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $path = $root . DIRECTORY_SEPARATOR . 'metadata-retry.jpg';
    file_put_contents($path, 'already synchronized WordPress media bytes');

    $hostXattrs = [
        $path => [
            'user.wordpress.old-import' => 'legacy-run',
        ],
    ];
    $attemptedSets = [];
    $platform = new PlatformMetadataApplier(
        syncXattrs: true,
        xattrSetter: static function (string $_path, string $name, string $value) use (&$attemptedSets): bool {
            $attemptedSets[] = ['name' => $name, 'value' => $value];
            return true;
        },
        xattrLister: static fn (string $xattrPath): array => array_keys($hostXattrs[$xattrPath] ?? []),
        xattrGetter: static fn (string $xattrPath, string $name): ?string => $hostXattrs[$xattrPath][$name] ?? null,
        xattrRemover: static fn (): bool => throw new RuntimeException('read-only filesystem'),
        xattrFilter: static fn (string $name): bool => str_starts_with($name, 'user.wordpress.'),
    );
    $remote = new FileInfo(
        name: 'wp-content/uploads/2026/05/metadata-retry.jpg',
        xattrs: [
            'user.wordpress.media-id' => '451',
            'user.wordpress.origin' => 'playground-export',
        ],
    );

    $error = $platform->apply($remote, $path);

    echo json_encode([
        'error' => $error,
        'syncNeedsRetry' => $error !== null,
        'databaseUpdateAllowed' => $error === null,
        'staleXattrStillPresent' => isset($hostXattrs[$path]['user.wordpress.old-import']),
        'newXattrsWereNotSet' => $attemptedSets === [],
        'mediaBytesIntact' => file_get_contents($path) === 'already synchronized WordPress media bytes',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_xattr_hard_error_rm($root);
}

function wordpress_xattr_hard_error_rm(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child) && !is_link($child)) {
            wordpress_xattr_hard_error_rm($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}
