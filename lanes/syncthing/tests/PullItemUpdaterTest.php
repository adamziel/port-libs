<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullDbUpdater;
use PortLibs\Syncthing\PullItemUpdater;
use PortLibs\Syncthing\VersionVector;

return [
    'handleDir creates nested directory emits lifecycle and schedules database update' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $dir = new FileInfo(
                name: 'wp-content/uploads/2026/05',
                version: VersionVector::fromCounters([202 => 10]),
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0750,
                modifiedBy: 202,
            );
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $updater->handleDirectory($dir);

            $path = syncthing_item_path($root, $dir->name);
            $t->true(is_dir($path));
            $t->same(0750, fileperms($path) & 0777);
            $t->same([
                [
                    'folder' => 'wordpress-media',
                    'item' => $dir->name,
                    'type' => 'dir',
                    'action' => 'update',
                ],
            ], $updater->itemStartedEvents());
            $t->same([
                [
                    'folder' => 'wordpress-media',
                    'item' => $dir->name,
                    'error' => null,
                    'type' => 'dir',
                    'action' => 'update',
                ],
            ], $updater->itemFinishedEvents());
            $t->same(['wp-content/uploads/2026'], $updater->scanNames());
            $t->same(PullDbUpdater::DB_UPDATE_HANDLE_DIR, $updater->dbUpdates()[0]['type']);
            $t->same($dir->name, $updater->dbUpdates()[0]['file']->name);

            $db = new PullDbUpdater(folderId: 'wordpress-media', folderLabel: 'Media');
            foreach ($updater->dbUpdates() as $update) {
                $db->append($update['file'], $update['type']);
            }
            $t->same(1, $db->close());
            $t->same([$dir->name], $db->fsyncedDirectories());
            $t->same('dir', $db->remoteChangeEvents()[0]['type']);
            $t->same(str_replace('/', DIRECTORY_SEPARATOR, $dir->name), $db->remoteChangeEvents()[0]['path']);
        } finally {
            syncthing_item_rm($root);
        }
    },
    'handleSymlink creates link emits lifecycle and schedules symlink database update' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            mkdir(syncthing_item_path($root, 'wp-content/uploads/current'), 0777, true);
            $link = new FileInfo(
                name: 'wp-content/uploads/current/latest',
                version: VersionVector::fromCounters([202 => 11]),
                type: FileInfo::TYPE_SYMLINK,
                symlinkTarget: '../2026/05',
                permissions: 0644,
                modifiedBy: 202,
            );
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $updater->handleSymlink($link);

            $path = syncthing_item_path($root, $link->name);
            $t->true(is_link($path));
            $t->same('../2026/05', readlink($path));
            $t->same([], $updater->pullErrors());
            $t->same([], $updater->scanNames());
            $t->same(PullDbUpdater::DB_UPDATE_HANDLE_SYMLINK, $updater->dbUpdates()[0]['type']);
            $t->same([
                [
                    'folder' => 'wordpress-media',
                    'item' => $link->name,
                    'error' => null,
                    'type' => 'symlink',
                    'action' => 'update',
                ],
            ], $updater->itemFinishedEvents());

            $db = new PullDbUpdater(folderId: 'wordpress-media', folderLabel: 'Media');
            foreach ($updater->dbUpdates() as $update) {
                $db->append($update['file'], $update['type']);
            }
            $t->same(1, $db->close());
            $t->same([], $db->fsyncedDirectories());
            $t->same('symlink', $db->remoteChangeEvents()[0]['type']);
            $t->same([], $db->receivedFiles());
        } finally {
            syncthing_item_rm($root);
        }
    },
    'handleDir moves conflicting regular file aside before directory update' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            mkdir(syncthing_item_path($root, 'wp-content/uploads/2026'), 0777, true);
            $name = 'wp-content/uploads/2026/gallery';
            $path = syncthing_item_path($root, $name);
            file_put_contents($path, 'local gallery export');
            touch($path, 1_700_000_000);

            $current = new FileInfo(
                name: $name,
                modifiedS: 1_700_000_000,
                version: VersionVector::fromCounters([101 => 5]),
                size: strlen('local gallery export'),
                type: FileInfo::TYPE_FILE,
                permissions: 0644,
                modifiedBy: 101,
            );
            $remote = new FileInfo(
                name: $name,
                version: VersionVector::fromCounters([202 => 1]),
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 202,
            );
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media', conflictTimestamp: 1_700_000_000);

            $updater->handleDirectory($remote, $current);

            $conflict = 'wp-content/uploads/2026/gallery.sync-conflict-20231114-221320-202';
            $t->true(is_dir($path));
            $t->same('local gallery export', file_get_contents(syncthing_item_path($root, $conflict)));
            $t->same([$conflict], $updater->scanNames());
            $t->same([], $updater->pullErrors());
            $t->same(PullDbUpdater::DB_UPDATE_HANDLE_DIR, $updater->dbUpdates()[0]['type']);
            $t->same(null, $updater->itemFinishedEvents()[0]['error']);
        } finally {
            syncthing_item_rm($root);
        }
    },
    'handleSymlink rejects incompatible empty targets without database update' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $link = new FileInfo(
                name: 'wp-content/uploads/current/latest',
                version: VersionVector::fromCounters([202 => 12]),
                type: FileInfo::TYPE_SYMLINK,
                symlinkTarget: '',
                permissions: 0644,
                modifiedBy: 202,
            );
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $updater->handleSymlink($link);

            $t->same([], $updater->dbUpdates());
            $t->same([
                [
                    'path' => $link->name,
                    'error' => 'incompatible symlink entry; rescan with newer Syncthing on source',
                ],
            ], $updater->pullErrors());
            $t->same(null, $updater->itemFinishedEvents()[0]['error']);
            $t->true(!is_link(syncthing_item_path($root, $link->name)));
        } finally {
            syncthing_item_rm($root);
        }
    },
];

function syncthing_item_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-item-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create Syncthing item test root');
    }

    return $root;
}

function syncthing_item_path(string $root, string $name): string
{
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function syncthing_item_rm(string $path): void
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
