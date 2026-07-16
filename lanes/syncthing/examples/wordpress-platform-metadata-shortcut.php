<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PlatformMetadataApplier;
use PortLibs\Syncthing\PullItemUpdater;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/syncthing-wordpress-platform-metadata-' . bin2hex(random_bytes(6));
mkdir($root, 0777, true);

try {
    $name = 'wp-content/uploads/2026/05/imported-hero.jpg';
    $bytes = 'already synchronized WordPress media bytes';
    $blocksHash = hash('sha256', 'platform metadata shortcut block list');
    $path = wordpress_platform_metadata_path($root, $name);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
        throw new RuntimeException('Failed to create example directory');
    }
    file_put_contents($path, $bytes);
    touch($path, 1_700_006_000);

    $current = new FileInfo(
        name: $name,
        modifiedS: 1_700_006_000,
        version: VersionVector::fromCounters([101 => 12]),
        size: strlen($bytes),
        blocksHash: $blocksHash,
        permissions: 0644,
        rawBlockSize: strlen($bytes),
        modifiedBy: 101,
    );
    $remoteMetadata = new FileInfo(
        name: $name,
        modifiedS: 1_700_006_100,
        version: VersionVector::fromCounters([202 => 13]),
        size: strlen($bytes),
        blocksHash: $blocksHash,
        permissions: 0640,
        rawBlockSize: strlen($bytes),
        unixUid: (int) fileowner($path),
        unixGid: (int) filegroup($path),
        modifiedBy: 202,
        xattrs: [
            'user.wordpress.source' => 'playground-export',
            'user.wordpress.media-id' => '451',
        ],
    );

    $appliedXattrs = [];
    $platform = new PlatformMetadataApplier(
        syncOwnership: true,
        syncXattrs: true,
        xattrSetter: static function (string $xattrPath, string $name, string $value) use (&$appliedXattrs): bool {
            $appliedXattrs[] = [
                'path' => wordpress_platform_metadata_name($xattrPath),
                'name' => $name,
                'value' => $value,
            ];
            return true;
        },
    );
    $updater = new PullItemUpdater($root, folderId: 'wordpress-media', platformMetadata: $platform);
    $updated = $updater->shortcutFile($remoteMetadata, $current);

    clearstatcache(true, $path);
    echo json_encode([
        'folder' => 'wordpress-media',
        'metadataShortcut' => $updated,
        'bytesUnchanged' => file_get_contents($path) === $bytes,
        'mode' => substr(sprintf('%o', fileperms($path) & 0777), -4),
        'mtime' => filemtime($path),
        'owner' => fileowner($path),
        'group' => filegroup($path),
        'xattrsApplied' => $appliedXattrs,
        'dbUpdateTypes' => array_column($updater->dbUpdates(), 'type'),
        'pullErrors' => $updater->pullErrors(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    wordpress_platform_metadata_rm($root);
}

function wordpress_platform_metadata_path(string $root, string $name): string
{
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function wordpress_platform_metadata_name(string $path): string
{
    return str_replace(DIRECTORY_SEPARATOR, '/', basename($path));
}

function wordpress_platform_metadata_rm(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}
