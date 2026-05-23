<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\BlockPullResult;
use PortLibs\Syncthing\EncryptionKey;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\IgnoreMatcher;
use PortLibs\Syncthing\PlatformMetadataApplier;
use PortLibs\Syncthing\PullFinalizationResult;
use PortLibs\Syncthing\PullTemporaryFile;
use PortLibs\Syncthing\ReceiveEncrypted;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\VersionVector;

return [
    'assembles copied sparse and pulled blocks into final media file' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $blockSize = BlockList::MIN_BLOCK_SIZE;
            $bytes = str_repeat('A', $blockSize)
                . str_repeat("\0", $blockSize)
                . str_repeat('B', $blockSize);
            $file = syncthing_pull_temp_file('wp-content/uploads/2026/finalized-hero.jpg', $bytes, 0600, 1700000000);
            $assembler = new PullTemporaryFile($file, $root);

            $assembler->writeBlock($file->blocks[0], substr($bytes, 0, $blockSize), source: 'copiedFromOrigin');
            $assembler->skipSparseBlock($file->blocks[1]);
            $assembler->applyPullResult(new BlockPullResult(
                block: $file->blocks[2],
                data: substr($bytes, 2 * $blockSize, $blockSize),
            ));

            $result = $assembler->finalize();
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->name);
            $tempPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, RequestServer::temporaryName($file->name));

            $t->true($result->closed);
            $t->true($result->finalized);
            $t->same(PullFinalizationResult::DB_UPDATE_HANDLE_FILE, $result->dbUpdateType);
            $t->same([0, 1, 2], $result->availableBlockIndexes);
            $t->same([
                0 => 'copiedFromOrigin',
                1 => 'sparseSkipped',
                2 => 'pulled',
            ], $assembler->sourcesByBlockIndex());
            $t->same($bytes, (string) file_get_contents($finalPath));
            $t->true(!file_exists($tempPath));
            $t->same(0600, fileperms($finalPath) & 0777);
            $t->same(1700000000, filemtime($finalPath));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'finalClose waits for all blocks and is idempotent after rename' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $blockSize = BlockList::MIN_BLOCK_SIZE;
            $bytes = str_repeat('first', intdiv($blockSize, 5))
                . str_repeat('second', intdiv($blockSize, 6));
            $file = syncthing_pull_temp_file('wp-content/uploads/2026/two-block.jpg', $bytes);
            $assembler = new PullTemporaryFile($file, $root);

            $assembler->writeBlock($file->blocks[0], substr($bytes, 0, $blockSize), source: 'copiedFromElsewhere');

            $notReady = $assembler->finalize();
            $t->true(!$notReady->closed);
            $t->true(!$notReady->finalized);
            $t->same([0], $notReady->availableBlockIndexes);
            $t->true(file_exists($assembler->tempPath()));

            $assembler->applyPullResult(new BlockPullResult(
                block: $file->blocks[1],
                data: substr($bytes, $blockSize),
            ));
            $done = $assembler->finalize();
            $again = $assembler->finalize();

            $t->true($done->closed);
            $t->true($done->finalized);
            $t->same([0, 1], $done->availableBlockIndexes);
            $t->true(!$again->closed);
            $t->true(!$again->finalized);
            $t->throws(LogicException::class, static fn () => $assembler->writeBlock($file->blocks[0], substr($bytes, 0, $blockSize)));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'temporary files keep upstream provisional owner write permissions' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $bytes = str_repeat('private-media-draft', 6000);
            $file = syncthing_pull_temp_file('wp-content/uploads/private/draft.jpg', $bytes, 0400, 1700000400);
            $assembler = new PullTemporaryFile($file, $root);

            $assembler->writeBlock($file->blocks[0], $bytes, source: 'pulled');
            $t->same(0600, syncthing_pull_temp_mode($assembler->tempPath()));

            $result = $assembler->finalize();

            $t->true($result->finalized);
            $t->same(0400, syncthing_pull_temp_mode($assembler->finalPath()));
            $t->same($bytes, (string) file_get_contents($assembler->finalPath()));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish applies platform metadata to temp file before promotion' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $bytes = str_repeat('platform metadata media ', 4000);
            $base = syncthing_pull_temp_file('wp-content/uploads/2026/platform-owned.jpg', $bytes, 0644, 1_700_000_410);
            $uid = (int) fileowner($root);
            $gid = (int) filegroup($root);
            $file = new FileInfo(
                name: $base->name,
                modifiedS: $base->modifiedS,
                version: $base->version,
                size: $base->size,
                rawBlockSize: $base->rawBlockSize,
                permissions: $base->permissions,
                blocks: $base->blocks,
                unixUid: $uid,
                unixGid: $gid,
                modifiedBy: $base->modifiedBy,
                xattrs: ['user.wordpress.source' => 'playground'],
            );
            $appliedXattrs = [];
            $platform = new PlatformMetadataApplier(
                syncOwnership: true,
                syncXattrs: true,
                xattrSetter: static function (string $xattrPath, string $xattrName, string $xattrValue) use (&$appliedXattrs): bool {
                    $appliedXattrs[] = [basename($xattrPath), $xattrName, $xattrValue];
                    return true;
                },
            );
            $assembler = new PullTemporaryFile($file, $root, platformMetadata: $platform);

            $assembler->writeBlock($file->blocks[0], $bytes, source: 'pulledWithPlatformMetadata');
            $result = $assembler->finalize();

            clearstatcache(true, $assembler->finalPath());
            $t->true($result->finalized);
            $t->same($uid, (int) fileowner($assembler->finalPath()));
            $t->same($gid, (int) filegroup($assembler->finalPath()));
            $t->same([[basename($assembler->tempName()), 'user.wordpress.source', 'playground']], $appliedXattrs);
            $t->same($bytes, (string) file_get_contents($assembler->finalPath()));
            $t->same(PullFinalizationResult::DB_UPDATE_HANDLE_FILE, $result->dbUpdateType);
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish keeps temp file when platform metadata fails' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $bytes = str_repeat('retry platform metadata ', 4000);
            $base = syncthing_pull_temp_file('wp-content/uploads/2026/platform-retry.jpg', $bytes, 0644, 1_700_000_420);
            $file = new FileInfo(
                name: $base->name,
                modifiedS: $base->modifiedS,
                version: $base->version,
                size: $base->size,
                rawBlockSize: $base->rawBlockSize,
                permissions: $base->permissions,
                blocks: $base->blocks,
                modifiedBy: $base->modifiedBy,
                xattrs: ['user.wordpress.source' => 'playground'],
            );
            $platform = new PlatformMetadataApplier(
                syncXattrs: true,
                xattrSetter: static fn (): bool => false,
            );
            $assembler = new PullTemporaryFile($file, $root, platformMetadata: $platform);

            $assembler->writeBlock($file->blocks[0], $bytes, source: 'pulledRetryableMetadata');
            $result = $assembler->finalize();

            $t->true($result->closed);
            $t->true(!$result->finalized);
            $t->same('setting metadata: setting xattrs: user.wordpress.source failed', $result->error);
            $t->same('', $result->dbUpdateType);
            $t->true(file_exists($assembler->tempPath()));
            $t->true(!file_exists($assembler->finalPath()));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'reused read-only temporary files are made writable before block writes' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $bytes = str_repeat('correct-private-block', 6000);
            $file = syncthing_pull_temp_file('wp-content/uploads/private/resumed.jpg', $bytes, 0400, 1700000500);
            $tempName = RequestServer::temporaryName($file->name);
            $tempPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $tempName);
            if (!mkdir(dirname($tempPath), 0777, true) && !is_dir(dirname($tempPath))) {
                throw new RuntimeException('Failed to create temporary test directory');
            }
            file_put_contents($tempPath, str_repeat('stale', 100));
            chmod($tempPath, 0400);

            $assembler = new PullTemporaryFile($file, $root, $tempName);
            $assembler->writeBlock($file->blocks[0], $bytes, source: 'pulledAfterRestart');

            $t->same(0600, syncthing_pull_temp_mode($tempPath));
            $t->same($bytes, (string) file_get_contents($tempPath));

            $result = $assembler->finalize();

            $t->true($result->finalized);
            $t->same([0 => 'pulledAfterRestart'], $assembler->sourcesByBlockIndex());
            $t->true(!file_exists($tempPath));
            $t->same($bytes, (string) file_get_contents($assembler->finalPath()));
            $t->same(0400, syncthing_pull_temp_mode($assembler->finalPath()));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish moves conflicting local file aside before promotion' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/concurrent-edit.jpg';
            $localBytes = str_repeat('local editor crop ', 5000);
            $remoteBytes = str_repeat('remote camera edit ', 5000);
            $current = syncthing_pull_temp_file($name, $localBytes, 0644, 1700000600, VersionVector::fromCounters([101 => 4]));
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700000700,
                VersionVector::fromCounters([202 => 2]),
                modifiedBy: 202,
            );
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!mkdir(dirname($finalPath), 0777, true) && !is_dir(dirname($finalPath))) {
                throw new RuntimeException('Failed to create final parent directory');
            }
            syncthing_pull_temp_write_current_file($root, $current, $localBytes);

            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current, conflictTimestamp: 1700000800);
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledConcurrentWinner');
            $result = $assembler->finalize();
            $expectedConflict = 'wp-content/uploads/2026/concurrent-edit.sync-conflict-'
                . date('Ymd-His', 1700000800)
                . '-202.jpg';
            $conflictPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedConflict);

            $t->true($result->finalized);
            $t->same($expectedConflict, $result->conflictName);
            $t->same([$expectedConflict], $result->scanNames);
            $t->same($remoteBytes, (string) file_get_contents($finalPath));
            $t->same($localBytes, (string) file_get_contents($conflictPath));
            $t->same([0 => 'pulledConcurrentWinner'], $assembler->sourcesByBlockIndex());
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish replaces non-conflicting descendant file without conflict copy' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/metadata-only.jpg';
            $localBytes = str_repeat('local published media ', 4000);
            $remoteBytes = str_repeat('remote normalized media ', 4000);
            $current = syncthing_pull_temp_file($name, $localBytes, 0644, 1700000900, VersionVector::fromCounters([101 => 4]));
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001000,
                VersionVector::fromCounters([101 => 4, 202 => 1]),
                modifiedBy: 202,
            );
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!mkdir(dirname($finalPath), 0777, true) && !is_dir(dirname($finalPath))) {
                throw new RuntimeException('Failed to create final parent directory');
            }
            syncthing_pull_temp_write_current_file($root, $current, $localBytes);

            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current, conflictTimestamp: 1700001100);
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledDescendant');
            $result = $assembler->finalize();
            $conflicts = glob($root . DIRECTORY_SEPARATOR . 'wp-content/uploads/2026/*.sync-conflict-*') ?: [];

            $t->true($result->finalized);
            $t->same(null, $result->conflictName);
            $t->same([], $result->scanNames);
            $t->same([], $conflicts);
            $t->same($remoteBytes, (string) file_get_contents($finalPath));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish schedules a scan when the existing final file changed after scanning' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/unscanned-local-edit.jpg';
            $scannedBytes = str_repeat('scanned wordpress media ', 3200);
            $unscannedBytes = str_repeat('edited locally after scan ', 3300);
            $remoteBytes = str_repeat('remote normalized media ', 3400);
            $current = syncthing_pull_temp_file($name, $scannedBytes, 0644, 1700000950, VersionVector::fromCounters([101 => 4]));
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001000,
                VersionVector::fromCounters([101 => 4, 202 => 1]),
                modifiedBy: 202,
            );
            $finalPath = syncthing_pull_temp_write_current_file($root, $current, $unscannedBytes);
            touch($finalPath, 1700000975);

            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current);
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledButBlockedByLocalEdit');
            $result = $assembler->finalize();

            $t->true($result->closed);
            $t->true(!$result->finalized);
            $t->same('checking existing file: file modified but not rescanned; will try again later', $result->error);
            $t->same([$name], $result->scanNames);
            $t->same($unscannedBytes, (string) file_get_contents($finalPath));
            $t->true(file_exists($assembler->tempPath()));
            $t->same(null, $result->conflictName);
            $t->same(null, $result->archivedName);
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish promotes case-only replacement on case-sensitive filesystems' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $currentName = 'wp-content/uploads/2026/hero.jpg';
            $remoteName = 'wp-content/uploads/2026/HERO.JPG';
            $bytes = str_repeat('case only wordpress media ', 3200);
            $current = syncthing_pull_temp_file($currentName, $bytes, 0644, 1700001010, VersionVector::fromCounters([101 => 4]));
            $remote = syncthing_pull_temp_file(
                $remoteName,
                $bytes,
                0644,
                1700001020,
                VersionVector::fromCounters([202 => 1]),
                modifiedBy: 202,
            );
            $currentPath = syncthing_pull_temp_write_current_file($root, $current, $bytes);

            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current);
            $assembler->writeBlock($remote->blocks[0], $bytes, source: 'pulledCaseOnlyTarget');
            $result = $assembler->finalize();

            $t->true($result->finalized);
            $t->same(PullFinalizationResult::DB_UPDATE_HANDLE_FILE, $result->dbUpdateType);
            $t->same([], $result->scanNames);
            $t->same($bytes, (string) file_get_contents($assembler->finalPath()));
            $t->same($bytes, (string) file_get_contents($currentPath));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish reports case conflict without scan on case-detecting filesystems' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $currentName = 'wp-content/uploads/2026/plugin-banner.png';
            $remoteName = 'wp-content/uploads/2026/Plugin-Banner.png';
            $bytes = str_repeat('local first plugin banner ', 3200);
            $current = syncthing_pull_temp_file($currentName, $bytes, 0644, 1700001030, VersionVector::fromCounters([101 => 4]));
            $remote = syncthing_pull_temp_file(
                $remoteName,
                $bytes,
                0644,
                1700001040,
                VersionVector::fromCounters([202 => 1]),
                modifiedBy: 202,
            );
            $currentPath = syncthing_pull_temp_write_current_file($root, $current, $bytes);

            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current, detectCaseConflicts: true);
            $assembler->writeBlock($remote->blocks[0], $bytes, source: 'pulledCaseOnlyConflict');
            $result = $assembler->finalize();

            $t->true($result->closed);
            $t->true(!$result->finalized);
            $t->contains('uses different upper or lowercase', $result->error ?? '');
            $t->same([], $result->scanNames);
            $t->same('', $result->dbUpdateType);
            $t->same($bytes, (string) file_get_contents($currentPath));
            $t->true(!file_exists($assembler->finalPath()));
            $t->true(file_exists($assembler->tempPath()));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish archives non-conflicting regular replacement when versioner is configured' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/versioned-hero.jpg';
            $localBytes = str_repeat('published wordpress crop ', 3600);
            $remoteBytes = str_repeat('normalized playground crop ', 3600);
            $current = syncthing_pull_temp_file($name, $localBytes, 0640, 1700001200, VersionVector::fromCounters([101 => 7]));
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001300,
                VersionVector::fromCounters([101 => 7, 202 => 2]),
                modifiedBy: 202,
            );
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!mkdir(dirname($finalPath), 0777, true) && !is_dir(dirname($finalPath))) {
                throw new RuntimeException('Failed to create final parent directory');
            }
            file_put_contents($finalPath, $localBytes);
            chmod($finalPath, 0640);
            touch($finalPath, 1700001200);

            $archiveTimestamp = strtotime('2026-05-23 12:34:56 UTC');
            if ($archiveTimestamp === false) {
                throw new RuntimeException('Failed to create archive timestamp');
            }
            $assembler = new PullTemporaryFile(
                $remote,
                $root,
                currentFile: $current,
                archiveRootPath: '.stversions',
                archiveTimestamp: $archiveTimestamp,
            );
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledArchivedReplacement');
            $result = $assembler->finalize();
            $expectedArchiveName = 'wp-content/uploads/2026/versioned-hero~' . date('Ymd-His', $archiveTimestamp) . '.jpg';
            $archivePath = $root . DIRECTORY_SEPARATOR . '.stversions' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedArchiveName);

            $t->true($result->finalized);
            $t->same(null, $result->conflictName);
            $t->same([], $result->scanNames);
            $t->same($expectedArchiveName, $result->archivedName);
            $t->same($remoteBytes, (string) file_get_contents($finalPath));
            $t->same($localBytes, (string) file_get_contents($archivePath));
            $t->same(0640, syncthing_pull_temp_mode($archivePath));
            $t->same(1700001200, filemtime($archivePath));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish prefers conflict copy over version archive for conflicting regular replacement' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/conflicting-versioned-hero.jpg';
            $localBytes = str_repeat('local editor crop ', 3600);
            $remoteBytes = str_repeat('remote editor crop ', 3600);
            $current = syncthing_pull_temp_file($name, $localBytes, 0644, 1700001400, VersionVector::fromCounters([101 => 8]));
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001500,
                VersionVector::fromCounters([202 => 3]),
                modifiedBy: 202,
            );
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!mkdir(dirname($finalPath), 0777, true) && !is_dir(dirname($finalPath))) {
                throw new RuntimeException('Failed to create final parent directory');
            }
            syncthing_pull_temp_write_current_file($root, $current, $localBytes);

            $conflictTimestamp = strtotime('2026-05-24 12:00:00 UTC');
            if ($conflictTimestamp === false) {
                throw new RuntimeException('Failed to create conflict timestamp');
            }
            $assembler = new PullTemporaryFile(
                $remote,
                $root,
                currentFile: $current,
                conflictTimestamp: $conflictTimestamp,
                archiveRootPath: '.stversions',
                archiveTimestamp: $conflictTimestamp,
            );
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledConflictOverArchive');
            $result = $assembler->finalize();
            $expectedConflictName = 'wp-content/uploads/2026/conflicting-versioned-hero.sync-conflict-'
                . date('Ymd-His', $conflictTimestamp)
                . '-202.jpg';
            $conflictPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedConflictName);

            $t->true($result->finalized);
            $t->same($expectedConflictName, $result->conflictName);
            $t->same(null, $result->archivedName);
            $t->true(!is_dir($root . DIRECTORY_SEPARATOR . '.stversions'));
            $t->same($localBytes, (string) file_get_contents($conflictPath));
            $t->same($remoteBytes, (string) file_get_contents($finalPath));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish deletes tracked directory before regular file promotion' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/gallery';
            $remoteBytes = str_repeat('remote media export zip ', 4000);
            $current = new FileInfo(
                name: $name,
                modifiedS: 1700001200,
                version: VersionVector::fromCounters([101 => 5]),
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 101,
            );
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001300,
                VersionVector::fromCounters([202 => 1]),
                modifiedBy: 202,
            );
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!mkdir($finalPath . DIRECTORY_SEPARATOR . 'thumbs', 0777, true) && !is_dir($finalPath . DIRECTORY_SEPARATOR . 'thumbs')) {
                throw new RuntimeException('Failed to create old directory tree');
            }
            file_put_contents($finalPath . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . 'stale.jpg', 'old generated thumbnail');

            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current);
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledDirectoryReplacement');
            $result = $assembler->finalize();

            $t->true($result->finalized);
            $t->same(null, $result->conflictName);
            $t->same([], $result->scanNames);
            $t->true(is_file($finalPath));
            $t->same($remoteBytes, (string) file_get_contents($finalPath));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish schedules a scan instead of deleting directory with unknown children' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/generated-gallery';
            $remoteBytes = str_repeat('remote generated gallery archive ', 3000);
            $current = new FileInfo(
                name: $name,
                modifiedS: 1700001550,
                version: VersionVector::fromCounters([101 => 8]),
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 101,
            );
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001600,
                VersionVector::fromCounters([202 => 3]),
                modifiedBy: 202,
            );
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            $unknownChild = $name . '/thumbs/local-only.jpg';
            $unknownChildPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $unknownChild);
            if (!mkdir(dirname($unknownChildPath), 0777, true) && !is_dir(dirname($unknownChildPath))) {
                throw new RuntimeException('Failed to create unknown child directory');
            }
            file_put_contents($unknownChildPath, 'locally generated thumbnail');

            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current, knownDirectoryChildren: []);
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledDirectoryGuardedReplacement');
            $result = $assembler->finalize();

            $t->true($result->closed);
            $t->true(!$result->finalized);
            $t->same('directory has been deleted on a remote device but contains changed files, scheduling scan', $result->error);
            $t->same([$name . '/thumbs', $unknownChild], $result->scanNames);
            $t->true(is_dir($finalPath));
            $t->same('locally generated thumbnail', (string) file_get_contents($unknownChildPath));
            $t->true(file_exists($assembler->tempPath()));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish removes upstream temporary children before directory replacement' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/stale-temp-gallery';
            $remoteBytes = str_repeat('remote compacted gallery archive ', 3000);
            $current = new FileInfo(
                name: $name,
                modifiedS: 1700001650,
                version: VersionVector::fromCounters([101 => 9]),
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 101,
            );
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001700,
                VersionVector::fromCounters([202 => 4]),
                modifiedBy: 202,
            );
            $temporaryChild = RequestServer::temporaryName($name . '/orphan.jpg');
            $temporaryChildPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $temporaryChild);
            if (!mkdir(dirname($temporaryChildPath), 0777, true) && !is_dir(dirname($temporaryChildPath))) {
                throw new RuntimeException('Failed to create temporary child directory');
            }
            file_put_contents($temporaryChildPath, 'abandoned partial child');

            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current, knownDirectoryChildren: []);
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledAfterTempCleanup');
            $result = $assembler->finalize();

            $t->true($result->finalized);
            $t->same([], $result->scanNames);
            $t->true(!file_exists($temporaryChildPath));
            $t->true(is_file($assembler->finalPath()));
            $t->same($remoteBytes, (string) file_get_contents($assembler->finalPath()));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish preserves nondeletable ignored directory children before replacement' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/private-cache';
            $ignoredDir = $name . '/local-review';
            $ignoredChild = $ignoredDir . '/keep.txt';
            $remoteBytes = str_repeat('remote private media archive ', 3000);
            $current = new FileInfo(
                name: $name,
                modifiedS: 1700001750,
                version: VersionVector::fromCounters([101 => 10]),
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 101,
            );
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001800,
                VersionVector::fromCounters([202 => 5]),
                modifiedBy: 202,
            );
            $ignoredChildPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ignoredChild);
            if (!mkdir(dirname($ignoredChildPath), 0777, true) && !is_dir(dirname($ignoredChildPath))) {
                throw new RuntimeException('Failed to create ignored child directory');
            }
            file_put_contents($ignoredChildPath, 'local private review cache');

            $matcher = IgnoreMatcher::fromLines([$ignoredDir]);
            $assembler = new PullTemporaryFile(
                $remote,
                $root,
                currentFile: $current,
                knownDirectoryChildren: [],
                ignoreMatcher: $matcher,
            );
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledIgnoredChildGuard');
            $result = $assembler->finalize();

            $t->true($result->closed);
            $t->true(!$result->finalized);
            $t->same('directory has been deleted on a remote device but contains ignored files (see ignore documentation for (?d) prefix)', $result->error);
            $t->same([], $result->scanNames);
            $t->true(is_dir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name)));
            $t->same('local private review cache', (string) file_get_contents($ignoredChildPath));
            $t->true(file_exists($assembler->tempPath()));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'performFinish treats receive-only changed directory children as scanned resurrection work' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/receive-only-gallery';
            $receiveOnlyChild = $name . '/local-crop.jpg';
            $receiveOnlyBytes = str_repeat('local receive-only crop ', 1800);
            $remoteBytes = str_repeat('remote gallery export archive ', 3000);
            $current = new FileInfo(
                name: $name,
                modifiedS: 1700001850,
                version: VersionVector::fromCounters([101 => 11]),
                type: FileInfo::TYPE_DIRECTORY,
                permissions: 0755,
                modifiedBy: 101,
            );
            $knownReceiveOnlyChild = new FileInfo(
                name: $receiveOnlyChild,
                modifiedS: 1700001860,
                version: VersionVector::fromCounters([101 => 12]),
                localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY,
                size: strlen($receiveOnlyBytes),
                type: FileInfo::TYPE_FILE,
                permissions: 0644,
                rawBlockSize: BlockList::MIN_BLOCK_SIZE,
                modifiedBy: 101,
            );
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001900,
                VersionVector::fromCounters([202 => 6]),
                modifiedBy: 202,
            );
            $receiveOnlyChildPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $receiveOnlyChild);
            if (!mkdir(dirname($receiveOnlyChildPath), 0777, true) && !is_dir(dirname($receiveOnlyChildPath))) {
                throw new RuntimeException('Failed to create receive-only child directory');
            }
            file_put_contents($receiveOnlyChildPath, $receiveOnlyBytes);

            $assembler = new PullTemporaryFile(
                $remote,
                $root,
                currentFile: $current,
                knownDirectoryChildren: [$knownReceiveOnlyChild],
                receiveOnlyFolder: true,
            );
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledReceiveOnlyReplacement');
            $result = $assembler->finalize();

            $t->true($result->finalized);
            $t->same([$name], $result->scanNames);
            $t->same(PullFinalizationResult::DB_UPDATE_HANDLE_FILE, $result->dbUpdateType);
            $t->same($remoteBytes, (string) file_get_contents($assembler->finalPath()));
            $t->true(is_file($assembler->finalPath()));
            $t->true(!file_exists($receiveOnlyChildPath));
            $t->same(null, $result->error);
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'moveForConflict prunes older conflict copies past maxConflicts' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $name = 'wp-content/uploads/2026/rotating-hero.jpg';
            $localBytes = str_repeat('local wordpress crop ', 3500);
            $remoteBytes = str_repeat('remote playground crop ', 3500);
            $current = syncthing_pull_temp_file($name, $localBytes, 0644, 1700001400, VersionVector::fromCounters([101 => 6]));
            $remote = syncthing_pull_temp_file(
                $name,
                $remoteBytes,
                0644,
                1700001500,
                VersionVector::fromCounters([202 => 3]),
                modifiedBy: 202,
            );
            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!mkdir(dirname($finalPath), 0777, true) && !is_dir(dirname($finalPath))) {
                throw new RuntimeException('Failed to create final parent directory');
            }
            syncthing_pull_temp_write_current_file($root, $current, $localBytes);

            $olderName = 'wp-content/uploads/2026/rotating-hero.sync-conflict-20260101-000000-101.jpg';
            $newerName = 'wp-content/uploads/2026/rotating-hero.sync-conflict-20260201-000000-101.jpg';
            foreach ([$olderName => 'older conflict', $newerName => 'newer conflict'] as $conflictName => $bytes) {
                $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $conflictName);
                file_put_contents($path, $bytes);
            }

            $timestamp = strtotime('2026-03-01 12:00:00 UTC');
            if ($timestamp === false) {
                throw new RuntimeException('Failed to create conflict timestamp');
            }
            $assembler = new PullTemporaryFile($remote, $root, currentFile: $current, maxConflicts: 2, conflictTimestamp: $timestamp);
            $assembler->writeBlock($remote->blocks[0], $remoteBytes, source: 'pulledNewestConflict');
            $result = $assembler->finalize();
            $expectedNewName = 'wp-content/uploads/2026/rotating-hero.sync-conflict-' . date('Ymd-His', $timestamp) . '-202.jpg';

            $t->true($result->finalized);
            $t->same($expectedNewName, $result->conflictName);
            $t->true(!file_exists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $olderName)));
            $t->true(file_exists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newerName)));
            $t->same($localBytes, (string) file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedNewName)));
            $t->same($remoteBytes, (string) file_get_contents($finalPath));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'failed block pulls close but leave temporary file reusable' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $bytes = str_repeat('missing-media', 12000);
            $file = syncthing_pull_temp_file('wp-content/uploads/2026/retry-later.jpg', $bytes);
            $assembler = new PullTemporaryFile($file, $root);
            $failed = new BlockPullResult($file->blocks[0], error: 'temporary peer disconnected');

            $t->true(!$assembler->applyPullResult($failed));
            $result = $assembler->finalize();

            $finalPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->name);
            $t->true($result->closed);
            $t->true(!$result->finalized);
            $t->same('pull: temporary peer disconnected', $result->error);
            $t->same([], $result->availableBlockIndexes);
            $t->true(file_exists($assembler->tempPath()));
            $t->true(!file_exists($finalPath));
            $t->throws(LogicException::class, static fn () => $assembler->writeBlock($file->blocks[0], substr($bytes, 0, $file->blocks[0]->size)));
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
    'receive-encrypted finalization appends FileInfo trailer before promotion' => static function (TestRunner $t): void {
        $root = syncthing_pull_temp_root();
        try {
            $plainBytes = str_repeat('private wordpress media export ', 24);
            $plainBlocks = (new BlockList())->fromBytes($plainBytes, strlen($plainBytes));
            $plainFile = new FileInfo(
                name: 'wp-content/uploads/2026/private/finalized-pull.bin',
                modifiedS: 1700002300,
                version: VersionVector::fromCounters([77 => 16]),
                size: strlen($plainBytes),
                blocksHash: (new BlockList())->hashBlocks($plainBlocks),
                rawBlockSize: strlen($plainBytes),
                sequence: 161,
                blocks: $plainBlocks,
                modifiedBy: 77,
            );
            $folderKey = EncryptionKey::folderKeyFromPassword('wordpress-private-media', 'wordpress media sync secret');
            $fileKey = ReceiveEncrypted::fileKey($plainFile->name, $folderKey);
            $encryptedFile = ReceiveEncrypted::encryptFileInfo(
                $plainFile,
                $folderKey,
                str_repeat("\12", ReceiveEncrypted::NONCE_SIZE),
            );
            $encryptedData = ReceiveEncrypted::encryptBytes(
                $plainBytes . str_repeat('P', ReceiveEncrypted::MIN_PADDED_SIZE - strlen($plainBytes)),
                $fileKey,
                str_repeat("\13", ReceiveEncrypted::NONCE_SIZE),
            );

            $assembler = new PullTemporaryFile($encryptedFile, $root);
            $assembler->writeBlock($encryptedFile->blocks[0], $encryptedData, receiveEncrypted: true, source: 'receiveEncryptedPull');

            $result = $assembler->finalize();
            $finalBytes = (string) file_get_contents($assembler->finalPath());
            $verified = ReceiveEncrypted::verifyFinalizedEncryptedFile($finalBytes, $folderKey);

            $t->true($result->closed);
            $t->true($result->finalized);
            $t->same(PullFinalizationResult::DB_UPDATE_HANDLE_FILE, $result->dbUpdateType);
            $t->same($encryptedFile->size, strlen($encryptedData));
            $t->same($encryptedData, $verified['encryptedData']);
            $t->same($plainBytes, $verified['plaintext']);
            $t->same($plainFile->name, $verified['plainFile']->name);
            $t->same($verified['trailerSize'], $result->encryptionTrailerSize);
            $t->same(strlen($finalBytes), $result->finalSize);
            $t->same($encryptedFile->size + $verified['trailerSize'], $result->finalSize);
            $t->same([0], $result->availableBlockIndexes);
            $t->same([0 => 'receiveEncryptedPull'], $assembler->sourcesByBlockIndex());
        } finally {
            syncthing_pull_temp_rm($root);
        }
    },
];

function syncthing_pull_temp_file(
    string $name,
    string $bytes,
    int $permissions = 0644,
    int $modifiedS = 0,
    ?VersionVector $version = null,
    int $modifiedBy = 101,
): FileInfo
{
    $blockList = new BlockList();
    $blocks = $blockList->fromBytes($bytes, BlockList::MIN_BLOCK_SIZE);

    return new FileInfo(
        name: $name,
        modifiedS: $modifiedS,
        version: $version ?? VersionVector::fromCounters([101 => 1]),
        size: strlen($bytes),
        rawBlockSize: BlockList::MIN_BLOCK_SIZE,
        permissions: $permissions,
        blocks: $blocks,
        modifiedBy: $modifiedBy,
    );
}

function syncthing_pull_temp_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-pull-temp-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary pull root');
    }

    return $root;
}

function syncthing_pull_temp_write_current_file(string $root, FileInfo $file, string $bytes): string
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create current file parent directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write current file');
    }
    chmod($path, $file->permissions & 0777);
    if ($file->modifiedS > 0) {
        touch($path, $file->modifiedS);
    }

    return $path;
}

function syncthing_pull_temp_rm(string $path): void
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

function syncthing_pull_temp_mode(string $path): int
{
    clearstatcache(true, $path);

    return fileperms($path) & 0777;
}
