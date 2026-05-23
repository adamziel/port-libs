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
    'processDeletions deletes files before reverse ordered directory tombstones' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $fileName = 'wp-content/uploads/2026/05/old.jpg';
            syncthing_item_write($root, $fileName, 'old media bytes', 1_700_003_400);

            $currentFile = syncthing_item_file_info($fileName, 'old media bytes', 1_700_003_400, [101 => 22]);
            $currentMonth = new FileInfo(
                name: 'wp-content/uploads/2026/05',
                version: VersionVector::fromCounters([101 => 22]),
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 101,
            );
            $currentYear = new FileInfo(
                name: 'wp-content/uploads/2026',
                version: VersionVector::fromCounters([101 => 22]),
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 101,
            );
            $deletedFile = syncthing_item_deleted($fileName, VersionVector::fromCounters([101 => 22, 202 => 23]));
            $deletedMonth = syncthing_item_deleted_dir($currentMonth->name, VersionVector::fromCounters([101 => 22, 202 => 23]));
            $deletedYear = syncthing_item_deleted_dir($currentYear->name, VersionVector::fromCounters([101 => 22, 202 => 23]));
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $updater->processDeletions(
                [$deletedFile],
                [$deletedYear, $deletedMonth],
                [$currentFile, $currentMonth, $currentYear],
            );

            $t->true(!file_exists(syncthing_item_path($root, 'wp-content/uploads/2026')));
            $t->same([], $updater->scanNames());
            $t->same([], $updater->pullErrors());
            $t->same([
                $fileName,
                'wp-content/uploads/2026/05',
                'wp-content/uploads/2026',
            ], array_column($updater->itemStartedEvents(), 'item'));
            $t->same([
                PullDbUpdater::DB_UPDATE_DELETE_FILE,
                PullDbUpdater::DB_UPDATE_DELETE_DIR,
                PullDbUpdater::DB_UPDATE_DELETE_DIR,
            ], array_column($updater->dbUpdates(), 'type'));
        } finally {
            syncthing_item_rm($root);
        }
    },
    'processDeletions coalesces repeated file tombstones by path' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $name = 'wp-content/uploads/2026/duplicate.jpg';
            syncthing_item_write($root, $name, 'old media bytes', 1_700_003_500);
            $current = syncthing_item_file_info($name, 'old media bytes', 1_700_003_500, [101 => 24]);
            $olderDelete = syncthing_item_deleted($name, VersionVector::fromCounters([202 => 1]), modifiedBy: 202);
            $newerDelete = syncthing_item_deleted($name, VersionVector::fromCounters([202 => 1, 203 => 2]), modifiedBy: 203);
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $updater->processDeletions([$olderDelete, $newerDelete], [], [$current]);

            $t->true(!file_exists(syncthing_item_path($root, $name)));
            $t->same(1, count($updater->itemStartedEvents()));
            $t->same(1, count($updater->dbUpdates()));
            $t->same($name, $updater->dbUpdates()[0]['file']->name);
            $t->same(203, $updater->dbUpdates()[0]['file']->modifiedBy);
            $t->same(PullDbUpdater::DB_UPDATE_DELETE_FILE, $updater->dbUpdates()[0]['type']);
        } finally {
            syncthing_item_rm($root);
        }
    },
    'processMetadataShortcuts updates same-block metadata and leaves other files for pull' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $name = 'wp-content/uploads/2026/05/hero.jpg';
            $otherName = 'wp-content/uploads/2026/05/new.jpg';
            $bytes = 'same wordpress media bytes';
            $blocksHash = hash('sha256', 'metadata shortcut shared blocks');
            $path = syncthing_item_write($root, $name, $bytes, 1_700_004_400);
            chmod($path, 0644);

            $current = syncthing_item_file_info($name, $bytes, 1_700_004_400, [101 => 70], $blocksHash);
            $target = syncthing_item_file_info($name, $bytes, 1_700_004_500, [202 => 71], $blocksHash, permissions: 0600);
            $fullPull = syncthing_item_file_info(
                $otherName,
                'new media bytes not already local',
                1_700_004_600,
                [202 => 72],
                hash('sha256', 'different metadata shortcut blocks'),
            );
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $remainingFiles = $updater->processMetadataShortcuts([$target, $fullPull], [$current]);

            $t->same([$otherName], array_map(static fn (FileInfo $file): string => $file->name, $remainingFiles));
            $t->same($bytes, file_get_contents($path));
            $t->same(1_700_004_500, filemtime($path));
            $t->same(0600, fileperms($path) & 0777);
            $t->same([], $updater->scanNames());
            $t->same([], $updater->pullErrors());
            $t->same([
                [
                    'folder' => 'wordpress-media',
                    'item' => $name,
                    'type' => 'file',
                    'action' => 'metadata',
                ],
            ], $updater->itemStartedEvents());
            $t->same([
                [
                    'folder' => 'wordpress-media',
                    'item' => $name,
                    'error' => null,
                    'type' => 'file',
                    'action' => 'metadata',
                ],
            ], $updater->itemFinishedEvents());
            $t->same(PullDbUpdater::DB_UPDATE_SHORTCUT_FILE, $updater->dbUpdates()[0]['type']);

            $db = new PullDbUpdater(folderId: 'wordpress-media', folderLabel: 'Media');
            foreach ($updater->dbUpdates() as $update) {
                $db->append($update['file'], $update['type']);
            }
            $t->same(1, $db->close());
            $t->same(['wp-content/uploads/2026/05'], $db->fsyncedDirectories());
            $t->same([], $db->receivedFiles());
            $t->same('modified', $db->remoteChangeEvents()[0]['action']);
            $t->same(str_replace('/', DIRECTORY_SEPARATOR, $name), $db->remoteChangeEvents()[0]['path']);
        } finally {
            syncthing_item_rm($root);
        }
    },
    'shortcutFile honors ignored permissions while applying upstream mtime' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $name = 'wp-content/uploads/2026/05/caption.txt';
            $bytes = 'caption bytes';
            $path = syncthing_item_write($root, $name, $bytes, 1_700_004_700);
            chmod($path, 0644);
            $target = syncthing_item_file_info(
                $name,
                $bytes,
                1_700_004_800,
                [202 => 73],
                hash('sha256', 'caption metadata shortcut blocks'),
                permissions: 0600,
            );
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media', ignorePerms: true);

            $updated = $updater->shortcutFile($target);

            $t->true($updated);
            $t->same(0644, fileperms($path) & 0777);
            $t->same(1_700_004_800, filemtime($path));
            $t->same(PullDbUpdater::DB_UPDATE_SHORTCUT_FILE, $updater->dbUpdates()[0]['type']);
            $t->same('metadata', $updater->itemFinishedEvents()[0]['action']);
            $t->same([], $updater->pullErrors());
        } finally {
            syncthing_item_rm($root);
        }
    },
    'shortcutFile reports missing files without creating empty placeholders' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $name = 'wp-content/uploads/2026/05/missing.jpg';
            $target = syncthing_item_file_info(
                $name,
                'expected bytes',
                1_700_004_900,
                [202 => 74],
                hash('sha256', 'missing metadata shortcut blocks'),
            );
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media', ignorePerms: true);

            $updated = $updater->shortcutFile($target);

            $t->true(!$updated);
            $t->true(!file_exists(syncthing_item_path($root, $name)));
            $t->same([], $updater->dbUpdates());
            $t->same([
                [
                    'path' => $name,
                    'error' => 'shortcut file (setting metadata): file is not a regular file',
                ],
            ], $updater->pullErrors());
            $t->same('shortcut file (setting metadata): file is not a regular file', $updater->itemFinishedEvents()[0]['error']);
        } finally {
            syncthing_item_rm($root);
        }
    },
    'processRenameShortcuts renames same-block source tombstone into target' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $sourceName = 'wp-content/uploads/2026/inbox/hero.jpg';
            $targetName = 'wp-content/uploads/2026/05/hero.jpg';
            $bytes = 'hero media bytes already present locally';
            $blocksHash = hash('sha256', 'shared hero block list');

            syncthing_item_write($root, $sourceName, $bytes, 1_700_003_600);
            mkdir(dirname(syncthing_item_path($root, $targetName)), 0777, true);

            $currentSource = syncthing_item_file_info($sourceName, $bytes, 1_700_003_600, [101 => 31], $blocksHash);
            $sourceDelete = syncthing_item_deleted($sourceName, VersionVector::fromCounters([101 => 31, 202 => 32]));
            $target = syncthing_item_file_info($targetName, $bytes, 1_700_003_700, [202 => 33], $blocksHash);
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $remainingDeletes = $updater->processRenameShortcuts([$target], [$sourceDelete], [$currentSource]);

            $t->same([], $remainingDeletes);
            $t->true(!file_exists(syncthing_item_path($root, $sourceName)));
            $t->same($bytes, file_get_contents(syncthing_item_path($root, $targetName)));
            $t->same(1_700_003_700, filemtime(syncthing_item_path($root, $targetName)));
            $t->same([], $updater->scanNames());
            $t->same([], $updater->pullErrors());
            $t->same([
                [$sourceName, 'delete'],
                [$targetName, 'update'],
            ], array_map(static fn (array $event): array => [$event['item'], $event['action']], $updater->itemStartedEvents()));
            $t->same([
                PullDbUpdater::DB_UPDATE_HANDLE_FILE,
                PullDbUpdater::DB_UPDATE_DELETE_FILE,
            ], array_column($updater->dbUpdates(), 'type'));
        } finally {
            syncthing_item_rm($root);
        }
    },
    'processRenameShortcuts retries next same-block candidate when first source changed' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $badSourceName = 'wp-content/uploads/2026/inbox/bad-hero.jpg';
            $goodSourceName = 'wp-content/uploads/2026/inbox/good-hero.jpg';
            $targetName = 'wp-content/uploads/2026/05/renamed-hero.jpg';
            $bytes = 'renamed hero bytes';
            $blocksHash = hash('sha256', 'two matching source block list');

            syncthing_item_write($root, $badSourceName, $bytes, 1_700_003_800);
            syncthing_item_write($root, $goodSourceName, $bytes, 1_700_003_900);
            mkdir(dirname(syncthing_item_path($root, $targetName)), 0777, true);

            $badCurrent = syncthing_item_file_info($badSourceName, $bytes, 1_700_003_700, [101 => 40], $blocksHash);
            $goodCurrent = syncthing_item_file_info($goodSourceName, $bytes, 1_700_003_900, [101 => 41], $blocksHash);
            $badDelete = syncthing_item_deleted($badSourceName, VersionVector::fromCounters([101 => 40, 202 => 42]));
            $goodDelete = syncthing_item_deleted($goodSourceName, VersionVector::fromCounters([101 => 41, 202 => 43]));
            $target = syncthing_item_file_info($targetName, $bytes, 1_700_004_000, [202 => 44], $blocksHash);
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $remainingDeletes = $updater->processRenameShortcuts(
                [$target],
                [$badDelete, $goodDelete],
                [$badCurrent, $goodCurrent],
            );

            $t->same([$badSourceName], array_map(static fn (FileInfo $file): string => $file->name, $remainingDeletes));
            $t->true(file_exists(syncthing_item_path($root, $badSourceName)));
            $t->true(!file_exists(syncthing_item_path($root, $goodSourceName)));
            $t->same($bytes, file_get_contents(syncthing_item_path($root, $targetName)));
            $t->same([$badSourceName], $updater->scanNames());
            $t->same([], $updater->pullErrors());
            $t->same('rename source: checking existing item: file modified but not rescanned; will try again later', $updater->itemFinishedEvents()[0]['error']);
            $t->same(null, $updater->itemFinishedEvents()[2]['error']);
            $t->same([
                PullDbUpdater::DB_UPDATE_HANDLE_FILE,
                PullDbUpdater::DB_UPDATE_DELETE_FILE,
            ], array_column($updater->dbUpdates(), 'type'));
        } finally {
            syncthing_item_rm($root);
        }
    },
    'renameFileShortcut scans changed existing target and leaves source in place' => static function (TestRunner $t): void {
        $root = syncthing_item_root();
        try {
            $sourceName = 'wp-content/uploads/2026/inbox/source.jpg';
            $targetName = 'wp-content/uploads/2026/05/source.jpg';
            $sourceBytes = 'source bytes';
            $targetBytes = 'locally changed target bytes';
            $blocksHash = hash('sha256', 'source target block list');

            syncthing_item_write($root, $sourceName, $sourceBytes, 1_700_004_100);
            syncthing_item_write($root, $targetName, $targetBytes, 1_700_004_200);

            $currentSource = syncthing_item_file_info($sourceName, $sourceBytes, 1_700_004_100, [101 => 50], $blocksHash);
            $sourceDelete = syncthing_item_deleted($sourceName, VersionVector::fromCounters([101 => 50, 202 => 51]));
            $target = syncthing_item_file_info($targetName, $sourceBytes, 1_700_004_300, [202 => 52], $blocksHash);
            $currentTarget = syncthing_item_file_info($targetName, $targetBytes, 1_700_004_000, [101 => 49], hash('sha256', 'old target block list'));
            $updater = new PullItemUpdater($root, folderId: 'wordpress-media');

            $renamed = $updater->renameFileShortcut($currentSource, $sourceDelete, $target, $currentTarget);

            $t->true(!$renamed);
            $t->same($sourceBytes, file_get_contents(syncthing_item_path($root, $sourceName)));
            $t->same($targetBytes, file_get_contents(syncthing_item_path($root, $targetName)));
            $t->same([$targetName], $updater->scanNames());
            $t->same([], $updater->pullErrors());
            $t->same([], $updater->dbUpdates());
            $t->same('rename target: checking existing item: file modified but not rescanned; will try again later', $updater->itemFinishedEvents()[0]['error']);
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
function syncthing_item_file_info(
    string $name,
    string $bytes,
    int $modifiedS,
    array $version,
    string $blocksHash = '',
    int $permissions = 0644,
    bool $noPermissions = false,
): FileInfo
{
    return new FileInfo(
        name: $name,
        modifiedS: $modifiedS,
        version: VersionVector::fromCounters($version),
        size: strlen($bytes),
        blocksHash: $blocksHash,
        type: FileInfo::TYPE_FILE,
        permissions: $permissions,
        noPermissions: $noPermissions,
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
