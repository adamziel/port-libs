<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\IgnoreMatcher;
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
    'deleteFile removes tracked media file emits delete lifecycle and schedules database update' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $name = 'wp-content/uploads/2026/old.jpg';
            $path = syncthing_item_write($root, $name, 'obsolete upload bytes', 1_700_003_000);
            $current = syncthing_item_file_info($name, 'obsolete upload bytes', 1_700_003_000, [101 => 8]);
            $deleted = syncthing_item_deleted($name, VersionVector::fromCounters([101 => 8, 202 => 9]));
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $updater->deleteFile($deleted, $current);

            $t->true(!file_exists($path));
            $t->same([], $updater->scanNames());
            $t->same([], $updater->pullErrors());
            $t->same(PullDbUpdater::DB_UPDATE_DELETE_FILE, $updater->dbUpdates()[0]['type']);
            $t->same([
                [
                    'folder' => 'wordpress-media',
                    'item' => $name,
                    'type' => 'file',
                    'action' => 'delete',
                ],
            ], $updater->itemStartedEvents());
            $t->same(null, $updater->itemFinishedEvents()[0]['error']);

            $db = new PullDbUpdater(folderId: 'wordpress-media', folderLabel: 'Media');
            foreach ($updater->dbUpdates() as $update) {
                $db->append($update['file'], $update['type']);
            }
            $t->same(1, $db->close());
            $t->same([], $db->fsyncedDirectories());
            $t->same([
                ['name' => $name, 'deleted' => true],
            ], $db->receivedFiles());
            $t->same('deleted', $db->remoteChangeEvents()[0]['action']);
        } finally {
            syncthing_item_rm($root);
        }
    },
    'deleteFile does not follow parent symlink and still records tombstone update' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            mkdir(syncthing_item_path($root, 'origin'), 0777, true);
            file_put_contents(syncthing_item_path($root, 'origin/file.jpg'), 'must stay behind symlink');
            symlink('origin', syncthing_item_path($root, 'link'));
            $name = 'link/file.jpg';
            $deleted = syncthing_item_deleted($name, VersionVector::fromCounters([202 => 11]));
            $current = syncthing_item_file_info($name, 'must stay behind symlink', 0, [101 => 10]);
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $updater->deleteFile($deleted, $current);

            $t->same('must stay behind symlink', file_get_contents(syncthing_item_path($root, 'origin/file.jpg')));
            $t->same([], $updater->scanNames());
            $t->same([], $updater->pullErrors());
            $t->same(PullDbUpdater::DB_UPDATE_DELETE_FILE, $updater->dbUpdates()[0]['type']);
            $t->same(null, $updater->itemFinishedEvents()[0]['error']);
        } finally {
            syncthing_item_rm($root);
        }
    },
    'deleteFile keeps case-only local sibling while accepting remote tombstone' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            syncthing_item_write($root, 'wp-content/uploads/2026/foo.jpg', 'case local asset', 1_700_003_100);
            $deleted = syncthing_item_deleted('wp-content/uploads/2026/Foo.jpg', VersionVector::fromCounters([202 => 12]));
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $updater->deleteFile($deleted);

            $t->same('case local asset', file_get_contents(syncthing_item_path($root, 'wp-content/uploads/2026/foo.jpg')));
            $t->same([], $updater->scanNames());
            $t->same([], $updater->pullErrors());
            $t->same(PullDbUpdater::DB_UPDATE_DELETE_FILE, $updater->dbUpdates()[0]['type']);
        } finally {
            syncthing_item_rm($root);
        }
    },
    'deleteFile moves conflict copy before accepting remote tombstone' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $name = 'wp-content/uploads/2026/conflict.jpg';
            syncthing_item_write($root, $name, 'local editor crop', 1_700_003_200);
            $current = syncthing_item_file_info($name, 'local editor crop', 1_700_003_200, [101 => 7]);
            $deleted = syncthing_item_deleted($name, VersionVector::fromCounters([202 => 1]), modifiedBy: 202);
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media', conflictTimestamp: 1_700_000_000);

            $updater->deleteFile($deleted, $current);

            $conflict = 'wp-content/uploads/2026/conflict.sync-conflict-20231114-221320-202.jpg';
            $t->true(!file_exists(syncthing_item_path($root, $name)));
            $t->same('local editor crop', file_get_contents(syncthing_item_path($root, $conflict)));
            $t->same([$conflict], $updater->scanNames());
            $t->same(PullDbUpdater::DB_UPDATE_DELETE_FILE, $updater->dbUpdates()[0]['type']);
            $t->same([], $updater->pullErrors());
        } finally {
            syncthing_item_rm($root);
        }
    },
    'deleteDirectory preserves unscanned local directory and queues scan' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            mkdir(syncthing_item_path($root, 'wp-content/uploads/2026/export'), 0777, true);
            $deleted = syncthing_item_deleted_dir('wp-content/uploads/2026/export', VersionVector::fromCounters([202 => 13]));
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $updater->deleteDirectory($deleted);

            $t->true(is_dir(syncthing_item_path($root, 'wp-content/uploads/2026/export')));
            $t->same(['wp-content/uploads/2026/export'], $updater->scanNames());
            $t->same([], $updater->dbUpdates());
            $t->same('delete dir: checking existing item: file modified but not rescanned; will try again later', $updater->pullErrors()[0]['error']);
            $t->same($updater->pullErrors()[0]['error'], $updater->itemFinishedEvents()[0]['error']);
        } finally {
            syncthing_item_rm($root);
        }
    },
    'deleteDirectory removes deletable ignored child tree and schedules directory tombstone' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            syncthing_item_write($root, 'wp-content/uploads/2026/private-cache/review.zip', 'ignored private cache', 1_700_003_300);
            $name = 'wp-content/uploads/2026';
            $current = new FileInfo(
                name: $name,
                version: VersionVector::fromCounters([101 => 14]),
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 101,
            );
            $deleted = syncthing_item_deleted_dir($name, VersionVector::fromCounters([101 => 14, 202 => 15]));
            $matcher = IgnoreMatcher::fromLines(['(?d)wp-content/uploads/2026/private-cache']);
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media', ignoreMatcher: $matcher);

            $updater->deleteDirectory($deleted, $current);

            $t->true(!file_exists(syncthing_item_path($root, $name)));
            $t->same([], $updater->scanNames());
            $t->same([], $updater->pullErrors());
            $t->same(PullDbUpdater::DB_UPDATE_DELETE_DIR, $updater->dbUpdates()[0]['type']);
            $t->same('dir', $updater->itemFinishedEvents()[0]['type']);
            $t->same('delete', $updater->itemFinishedEvents()[0]['action']);
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

function syncthing_item_write(string $root, string $name, string $bytes, int $mtime): string
{
    $path = syncthing_item_path($root, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create Syncthing item directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write Syncthing item fixture');
    }
    touch($path, $mtime);

    return $path;
}

/**
 * @param array<int, int> $version
 */
function syncthing_item_file_info(string $name, string $bytes, int $modifiedS, array $version): FileInfo
{
    return new FileInfo(
        name: $name,
        modifiedS: $modifiedS,
        version: VersionVector::fromCounters($version),
        size: strlen($bytes),
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        rawBlockSize: max(1, strlen($bytes)),
        modifiedBy: array_key_first($version) ?? 0,
    );
}

function syncthing_item_deleted(string $name, VersionVector $version, int $modifiedBy = 202): FileInfo
{
    return new FileInfo(
        name: $name,
        version: $version,
        deleted: true,
        type: FileInfo::TYPE_FILE,
        permissions: 0644,
        modifiedBy: $modifiedBy,
    );
}

function syncthing_item_deleted_dir(string $name, VersionVector $version, int $modifiedBy = 202): FileInfo
{
    return new FileInfo(
        name: $name,
        version: $version,
        deleted: true,
        type: FileInfo::TYPE_DIRECTORY,
        permissions: 0755,
        modifiedBy: $modifiedBy,
    );
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
