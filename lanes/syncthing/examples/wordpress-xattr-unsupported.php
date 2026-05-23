<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PlatformMetadataApplier;
use PortLibs\Syncthing\XattrsNotSupportedException;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-xattr-unsupported-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $path = $root . DIRECTORY_SEPARATOR . 'shared-hosting-media.jpg';
    file_put_contents($path, 'already synchronized WordPress media bytes');

    $attemptedWrites = [];
    $platform = new PlatformMetadataApplier(
        syncXattrs: true,
        xattrSetter: static function (string $_path, string $name) use (&$attemptedWrites): bool {
            $attemptedWrites[] = ['op' => 'set', 'name' => $name];
            return true;
        },
        xattrLister: static fn (): array => throw new XattrsNotSupportedException('extended attributes are not supported on this platform'),
        xattrRemover: static function (string $_path, string $name) use (&$attemptedWrites): bool {
            $attemptedWrites[] = ['op' => 'remove', 'name' => $name];
            return true;
        },
    );
    $remote = new FileInfo(
        name: 'wp-content/uploads/2026/05/shared-hosting-media.jpg',
        xattrs: [
            'user.wordpress.source' => 'playground-export',
            'user.wordpress.media-id' => '451',
        ],
    );

    $error = $platform->apply($remote, $path);

    echo json_encode([
        'error' => $error,
        'xattrsSupported' => false,
        'syncCanContinue' => $error === null,
        'attemptedWrites' => $attemptedWrites,
        'mediaBytesIntact' => file_get_contents($path) === 'already synchronized WordPress media bytes',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_xattr_unsupported_rm($root);
}

function wordpress_xattr_unsupported_rm(string $path): void
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
            wordpress_xattr_unsupported_rm($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}
