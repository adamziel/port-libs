<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PlatformMetadataApplier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-xattr-replacement-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $path = $root . DIRECTORY_SEPARATOR . 'wp-content-upload.jpg';
    file_put_contents($path, 'already synchronized WordPress media bytes');

    $hostXattrs = [
        $path => [
            'security.selinux' => 'host-label',
            'user.wordpress.source' => 'playground-export',
            'user.wordpress.old-import' => 'legacy-run',
            'user.wordpress.media-id' => 'old-id',
        ],
    ];
    $operations = [];

    $platform = new PlatformMetadataApplier(
        syncXattrs: true,
        xattrSetter: static function (string $xattrPath, string $name, string $value) use (&$hostXattrs, &$operations): bool {
            $operations[] = ['op' => 'set', 'name' => $name, 'value' => $value];
            $hostXattrs[$xattrPath][$name] = $value;
            return true;
        },
        xattrLister: static fn (string $xattrPath): array => array_keys($hostXattrs[$xattrPath] ?? []),
        xattrGetter: static fn (string $xattrPath, string $name): ?string => $hostXattrs[$xattrPath][$name] ?? null,
        xattrRemover: static function (string $xattrPath, string $name) use (&$hostXattrs, &$operations): bool {
            $operations[] = ['op' => 'remove', 'name' => $name];
            unset($hostXattrs[$xattrPath][$name]);
            return true;
        },
        xattrFilter: static fn (string $name): bool => str_starts_with($name, 'user.wordpress.'),
    );
    $remote = new FileInfo(
        name: 'wp-content/uploads/2026/05/imported-hero.jpg',
        xattrs: [
            'user.wordpress.source' => 'playground-export',
            'user.wordpress.media-id' => '451',
            'user.wordpress.origin' => 'remote-device',
        ],
    );

    $error = $platform->apply($remote, $path);

    echo json_encode([
        'error' => $error,
        'operations' => $operations,
        'finalXattrs' => $hostXattrs[$path],
        'unchangedSourceSkipped' => !in_array(['op' => 'set', 'name' => 'user.wordpress.source', 'value' => 'playground-export'], $operations, true),
        'hostAttributePreserved' => ($hostXattrs[$path]['security.selinux'] ?? null) === 'host-label',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_xattr_replacement_rm($root);
}

function wordpress_xattr_replacement_rm(string $path): void
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
            wordpress_xattr_replacement_rm($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}
