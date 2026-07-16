<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoComparison;
use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\FolderScanEventCollector;
use PortLibs\Syncthing\FolderScanProgress;
use PortLibs\Syncthing\IgnoreMatcher;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\ScannerSubWalkDiagnostic;

return [
    'maps upstream scanner CreateFileInfo ownership xattrs and block metadata' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $name = 'wp-content/uploads/2026/05/scanned-platform.jpg';
            $path = syncthing_scanner_path($root, $name);
            @mkdir(dirname($path), 0777, true);
            $bytes = 'scanner platform metadata bytes';
            file_put_contents($path, $bytes);
            chmod($path, 0640);
            touch($path, 1_700_006_000);
            clearstatcache(true, $path);
            $stat = lstat($path);
            $t->true(is_array($stat));

            $seenXattrs = [];
            $scanner = new FileInfoScanner(
                $root,
                scanOwnership: true,
                scanXattrs: true,
                xattrFilter: static fn (string $xattrName): bool => str_starts_with($xattrName, 'user.wordpress.'),
                maxSingleXattrSize: 64,
                maxTotalXattrSize: 96,
                xattrLister: static fn (string $xattrPath): array => [
                    'security.selinux',
                    'user.wordpress.source',
                    'user.wordpress.too-large',
                    'user.wordpress.origin',
                ],
                xattrGetter: static function (string $xattrPath, string $xattrName) use (&$seenXattrs): ?string {
                    $seenXattrs[] = [basename($xattrPath), $xattrName];
                    return match ($xattrName) {
                        'user.wordpress.source' => 'playground',
                        'user.wordpress.origin' => 'remote-device',
                        'user.wordpress.too-large' => str_repeat('x', 80),
                        default => null,
                    };
                },
            );

            $info = $scanner->scan($name, hashBlocks: true, blockSize: 8);
            $expectedBlocks = (new BlockList())->fromBytes($bytes, 8);

            $t->same($name, $info->name);
            $t->same(FileInfo::TYPE_FILE, $info->type);
            $t->same(strlen($bytes), $info->size);
            $t->same(0640, $info->permissions & 0777);
            $t->same(1_700_006_000, $info->modifiedS);
            $t->same(8, $info->rawBlockSize);
            $t->same(count($expectedBlocks), count($info->blocks));
            $t->same($expectedBlocks[0]->hashHex, $info->blocks[0]->hashHex);
            $t->same((new BlockList())->hashBlocks($expectedBlocks), $info->blocksHash);
            $t->same((int) $stat['uid'], $info->unixUid);
            $t->same((int) $stat['gid'], $info->unixGid);
            $t->same([
                'user.wordpress.origin' => 'remote-device',
                'user.wordpress.source' => 'playground',
            ], $info->xattrs);
            $t->same([
                ['scanned-platform.jpg', 'user.wordpress.origin'],
                ['scanned-platform.jpg', 'user.wordpress.source'],
                ['scanned-platform.jpg', 'user.wordpress.too-large'],
            ], $seenXattrs);

            $withoutXattrs = new FileInfo(
                name: $info->name,
                modifiedS: $info->modifiedS,
                size: $info->size,
                blocksHash: $info->blocksHash,
                permissions: $info->permissions,
                rawBlockSize: $info->rawBlockSize,
                blocks: $info->blocks,
                unixUid: $info->unixUid,
                unixGid: $info->unixGid,
            );
            $t->true(!$info->isEquivalent($withoutXattrs));
            $t->true($info->isEquivalent($withoutXattrs, new FileInfoComparison(ignoreXattrs: true)));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'maps upstream scanner symlink FileInfo without following target metadata' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $targetName = 'wp-content/uploads/2026/05/current.jpg';
            $targetPath = syncthing_scanner_path($root, $targetName);
            @mkdir(dirname($targetPath), 0777, true);
            file_put_contents($targetPath, 'target media bytes');
            $linkName = 'wp-content/uploads/2026/05/latest.jpg';
            $linkPath = syncthing_scanner_path($root, $linkName);
            if (!@symlink('current.jpg', $linkPath)) {
                throw new RuntimeException('symlink creation failed');
            }
            $stat = lstat($linkPath);
            $t->true(is_array($stat));

            $scanner = new FileInfoScanner($root, scanOwnership: true);
            $info = $scanner->scan($linkName, hashBlocks: true);

            $t->same(FileInfo::TYPE_SYMLINK, $info->type);
            $t->same('current.jpg', $info->symlinkTarget);
            $t->true($info->noPermissions);
            $t->same(0, $info->permissions);
            $t->same(0, $info->size);
            $t->same([], $info->blocks);
            $t->same((int) $stat['uid'], $info->unixUid);
            $t->same((int) $stat['gid'], $info->unixGid);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'reads symlink xattrs from the link path without following the target' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $targetName = 'wp-content/uploads/2026/05/original.jpg';
            $targetPath = syncthing_scanner_path($root, $targetName);
            @mkdir(dirname($targetPath), 0777, true);
            file_put_contents($targetPath, 'target media bytes');
            $linkName = 'wp-content/uploads/2026/05/current.jpg';
            $linkPath = syncthing_scanner_path($root, $linkName);
            if (!@symlink('original.jpg', $linkPath)) {
                throw new RuntimeException('symlink creation failed');
            }
            $xattrs = [
                $targetPath => ['user.wordpress.target' => 'must-not-read'],
                $linkPath => ['user.wordpress.shortcut' => 'remote-alias'],
            ];
            $seen = [];
            $scanner = new FileInfoScanner(
                $root,
                scanXattrs: true,
                xattrFilter: static fn (string $xattrName): bool => str_starts_with($xattrName, 'user.wordpress.'),
                xattrLister: static fn (string $xattrPath): array => array_keys($xattrs[$xattrPath] ?? []),
                xattrGetter: static function (string $xattrPath, string $xattrName) use (&$xattrs, &$seen): ?string {
                    $seen[] = [basename($xattrPath), $xattrName];
                    return $xattrs[$xattrPath][$xattrName] ?? null;
                },
            );

            $info = $scanner->scan($linkName);

            $t->same(FileInfo::TYPE_SYMLINK, $info->type);
            $t->same('original.jpg', $info->symlinkTarget);
            $t->same(['user.wordpress.shortcut' => 'remote-alias'], $info->xattrs);
            $t->same([['current.jpg', 'user.wordpress.shortcut']], $seen);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'windows scanner walk skips symlink entries instead of emitting FileInfo' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_scanner_write($root, $dir . '/target/original.jpg', 'target media bytes');
            $linkName = $dir . '/shortcut.jpg';
            $linkPath = syncthing_scanner_path($root, $linkName);
            if (!@symlink('target/original.jpg', $linkPath)) {
                throw new RuntimeException('symlink creation failed');
            }
            $dirLinkName = $dir . '/linked-library';
            $dirLinkPath = syncthing_scanner_path($root, $dirLinkName);
            if (!@symlink('target', $dirLinkPath)) {
                throw new RuntimeException('directory symlink creation failed');
            }

            $posixScanner = new FileInfoScanner($root, platformFamily: 'Linux');
            $posixFiles = $posixScanner->walk([$dir]);
            $t->same([
                $dir,
                $dirLinkName,
                $linkName,
                $dir . '/target',
                $dir . '/target/original.jpg',
            ], array_map(static fn (FileInfo $file): string => $file->name, $posixFiles));
            $t->same(FileInfo::TYPE_SYMLINK, $posixFiles[1]->type);
            $t->same(FileInfo::TYPE_SYMLINK, $posixFiles[2]->type);

            $windowsScanner = new FileInfoScanner($root, platformFamily: 'Windows');
            $windowsFiles = $windowsScanner->walk([$dir]);
            $t->same([
                $dir,
                $dir . '/target',
                $dir . '/target/original.jpg',
            ], array_map(static fn (FileInfo $file): string => $file->name, $windowsFiles));
            $t->same([], $windowsScanner->walk([$linkName]));
            $t->same([], $windowsScanner->walk([$dirLinkName]));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk skips requested subs below symlinked parents like upstream TraversesSymlink' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_scanner_write($root, $dir . '/library/original.jpg', 'target media bytes');
            $linkedDirName = $dir . '/linked-library';
            $linkedDirPath = syncthing_scanner_path($root, $linkedDirName);
            if (!@symlink('library', $linkedDirPath)) {
                throw new RuntimeException('directory symlink creation failed');
            }

            $scanner = new FileInfoScanner($root);
            $linkedDir = $scanner->walk([$linkedDirName]);
            $belowLinkedDir = $scanner->walk([$linkedDirName . '/original.jpg'], hashBlocks: true, blockSize: 8);
            $ordinaryTarget = $scanner->walk([$dir . '/library/original.jpg'], hashBlocks: true, blockSize: 8);
            $wholeParent = $scanner->walk([$dir]);

            $t->same([$linkedDirName], array_map(static fn (FileInfo $file): string => $file->name, $linkedDir));
            $t->same(FileInfo::TYPE_SYMLINK, $linkedDir[0]->type);
            $t->same('library', $linkedDir[0]->symlinkTarget);
            $t->same([], $belowLinkedDir);
            $t->same([$dir . '/library/original.jpg'], array_map(static fn (FileInfo $file): string => $file->name, $ordinaryTarget));
            $t->same(hash('sha256', 'target m'), $ordinaryTarget[0]->blocks[0]->hashHex);
            $t->same([
                $dir,
                $dir . '/library',
                $dir . '/library/original.jpg',
                $linkedDirName,
            ], array_map(static fn (FileInfo $file): string => $file->name, $wholeParent));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'diagnoses upstream scanner sub walk parent guard boundaries' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_scanner_write($root, $dir . '/library/original.jpg', 'target media bytes');
            syncthing_scanner_write($root, $dir . '/not-a-directory', 'regular file parent');
            $linkedDirName = $dir . '/linked-library';
            $linkedDirPath = syncthing_scanner_path($root, $linkedDirName);
            if (!@symlink('library', $linkedDirPath)) {
                throw new RuntimeException('directory symlink creation failed');
            }

            $scanner = new FileInfoScanner($root);
            $directSymlink = $scanner->diagnoseSubWalk('/' . $linkedDirName);
            $belowSymlink = $scanner->diagnoseSubWalk($linkedDirName . '/original.jpg');
            $belowRegular = $scanner->diagnoseSubWalk($dir . '/not-a-directory/child.jpg');
            $missingParent = $scanner->diagnoseSubWalk($dir . '/missing-parent/child.jpg');
            $missingDirectSub = $scanner->diagnoseSubWalk($dir . '/missing-direct.jpg');

            $t->same([
                'sub' => $linkedDirName,
                'parent' => $dir,
                'status' => ScannerSubWalkDiagnostic::STATUS_ALLOWED,
                'path' => null,
                'message' => null,
                'shouldWalk' => true,
            ], $directSymlink->toArray());
            $t->same([
                'sub' => $linkedDirName . '/original.jpg',
                'parent' => $linkedDirName,
                'status' => ScannerSubWalkDiagnostic::STATUS_TRAVERSES_SYMLINK,
                'path' => $linkedDirName,
                'message' => 'traverses symlink: ' . $linkedDirName,
                'shouldWalk' => false,
            ], $belowSymlink->toArray());
            $t->same([
                'sub' => $dir . '/not-a-directory/child.jpg',
                'parent' => $dir . '/not-a-directory',
                'status' => ScannerSubWalkDiagnostic::STATUS_NOT_A_DIRECTORY,
                'path' => $dir . '/not-a-directory',
                'message' => 'not a directory: ' . $dir . '/not-a-directory',
                'shouldWalk' => false,
            ], $belowRegular->toArray());
            $t->same([
                'sub' => $dir . '/missing-parent/child.jpg',
                'parent' => $dir . '/missing-parent',
                'status' => ScannerSubWalkDiagnostic::STATUS_MISSING_PARENT,
                'path' => $dir . '/missing-parent',
                'message' => null,
                'shouldWalk' => false,
            ], $missingParent->toArray());
            $t->same([
                'sub' => $dir . '/missing-direct.jpg',
                'parent' => $dir,
                'status' => ScannerSubWalkDiagnostic::STATUS_MISSING,
                'path' => $dir . '/missing-direct.jpg',
                'message' => null,
                'shouldWalk' => false,
            ], $missingDirectSub->toArray());
            $t->true($belowSymlink->isTraversalBlocked());
            $t->true($belowRegular->isTraversalBlocked());
            $t->true(!$missingParent->isTraversalBlocked());
            $t->same([], $scanner->walk([$linkedDirName . '/original.jpg']));
            $t->same([], $scanner->walk([$dir . '/not-a-directory/child.jpg']));
            $t->same([], $scanner->walk([$dir . '/missing-parent/child.jpg']));
            $t->same([], $scanner->walk([$dir . '/missing-direct.jpg']));
            $t->same([$linkedDirName], array_map(static fn (FileInfo $file): string => $file->name, $scanner->walk([$linkedDirName])));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'propagates platform data read errors like upstream CreateFileInfo' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $name = 'wp-content/uploads/2026/05/private.jpg';
            $path = syncthing_scanner_path($root, $name);
            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, 'private bytes');

            $scanner = new FileInfoScanner(
                $root,
                scanXattrs: true,
                xattrLister: static fn (): array => throw new RuntimeException('permission denied'),
            );

            $t->throws(RuntimeException::class, static fn () => $scanner->scan($name));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'rejects noncanonical scanner paths before touching the filesystem' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $scanner = new FileInfoScanner($root);
            $t->throws(InvalidArgumentException::class, static fn () => $scanner->scan('../wp-config.php'));
            $t->throws(InvalidArgumentException::class, static fn () => $scanner->scan('wp-content//uploads/file.jpg'));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk maps upstream scanner sub walk ignore pruning' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            syncthing_scanner_write($root, 'dir2/cfile', "baz\n");
            syncthing_scanner_write($root, 'dir2/dfile', "quux\n");
            syncthing_scanner_write($root, 'dir2/dir21/media.jpg', 'ignored subtree');
            $scanner = new FileInfoScanner($root);
            $matcher = IgnoreMatcher::fromLines([
                'dir2/dfile',
                '/dir2/dir21',
            ]);

            $files = $scanner->walk(['dir2'], $matcher, hashBlocks: true, blockSize: 4);
            $names = array_map(static fn (FileInfo $file): string => $file->name, $files);

            $t->same(['dir2', 'dir2/cfile'], $names);
            $t->same(FileInfo::TYPE_DIRECTORY, $files[0]->type);
            $t->same(FileInfo::TYPE_FILE, $files[1]->type);
            $t->same(4, $files[1]->size);
            $t->same(hash('sha256', "baz\n"), $files[1]->blocks[0]->hashHex);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk preserves ignored ancestor directories for included descendants' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            syncthing_scanner_write($root, 'foo/bar/included/asset.jpg', 'public media');
            syncthing_scanner_write($root, 'foo/private/secret.zip', 'private export');
            $scanner = new FileInfoScanner($root);
            $matcher = IgnoreMatcher::fromLines([
                '!foo/bar',
                '*',
            ]);

            $files = $scanner->walk(ignoreMatcher: $matcher);
            $names = array_map(static fn (FileInfo $file): string => $file->name, $files);

            $t->same([
                'foo',
                'foo/bar',
                'foo/bar/included',
                'foo/bar/included/asset.jpg',
            ], $names);
            $t->same(FileInfo::TYPE_DIRECTORY, $files[0]->type);
            $t->same(FileInfo::TYPE_DIRECTORY, $files[1]->type);
            $t->same(FileInfo::TYPE_FILE, $files[3]->type);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk skips internal and temporary entries while accepting slash-rooted subs' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            syncthing_scanner_write($root, '.stignore', '*');
            syncthing_scanner_write($root, '.stfolder/marker', 'folder marker');
            syncthing_scanner_write($root, '.syncthing.asset.tmp', 'stale temp');
            syncthing_scanner_write($root, 'foo', 'scanned from slash sub');
            $scanner = new FileInfoScanner($root);

            $all = $scanner->walk();
            $slashSub = $scanner->walk(['/foo'], hashBlocks: true, blockSize: 8);

            $t->same(['foo'], array_map(static fn (FileInfo $file): string => $file->name, $all));
            $t->same(['foo'], array_map(static fn (FileInfo $file): string => $file->name, $slashSub));
            $t->same(hash('sha256', 'scanned '), $slashSub[0]->blocks[0]->hashHex);
            $t->same(22, $slashSub[0]->size);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk removes stale regular temporary files using upstream temp lifetime' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            $published = $dir . '/published.jpg';
            $freshTemp = RequestServer::temporaryName($dir . '/fresh.jpg');
            $staleTemp = RequestServer::temporaryName($dir . '/stale.jpg');
            $windowsTemp = $dir . '/~syncthing~windows.jpg.tmp';
            $tempDir = $dir . '/.syncthing.partial.tmp';

            syncthing_scanner_write($root, $published, 'published media');
            $freshPath = syncthing_scanner_write($root, $freshTemp, 'fresh temp bytes');
            $stalePath = syncthing_scanner_write($root, $staleTemp, 'stale temp bytes');
            $windowsPath = syncthing_scanner_write($root, $windowsTemp, 'stale windows temp bytes');
            @mkdir(syncthing_scanner_path($root, $tempDir), 0777, true);
            syncthing_scanner_write($root, $tempDir . '/leftover-block', 'directory temp child');

            $now = time();
            touch($freshPath, $now - 60);
            touch($stalePath, $now - 7200);
            touch($windowsPath, $now - 7200);
            clearstatcache();

            $scanner = new FileInfoScanner($root, tempLifetimeSeconds: 3600);
            $files = $scanner->walk([$dir]);

            $t->same([$dir, $published], array_map(static fn (FileInfo $file): string => $file->name, $files));
            $t->true(file_exists($freshPath));
            $t->true(!file_exists($stalePath));
            $t->true(!file_exists($windowsPath));
            $t->true(is_dir(syncthing_scanner_path($root, $tempDir)));
            $t->true(file_exists(syncthing_scanner_path($root, $tempDir . '/leftover-block')));
            $t->throws(InvalidArgumentException::class, static fn () => new FileInfoScanner($root, tempLifetimeSeconds: -1));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk emits upstream FolderScanProgress byte totals while hashing' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_scanner_write($root, $dir . '/hero.jpg', 'abcdefgh');
            syncthing_scanner_write($root, $dir . '/thumb.jpg', '12345');
            syncthing_scanner_write($root, $dir . '/empty.jpg', '');

            $progress = [];
            $scanner = new FileInfoScanner($root);
            $files = $scanner->walk(
                [$dir],
                hashBlocks: true,
                blockSize: 4,
                progressLogger: static function (FolderScanProgress $event) use (&$progress): void {
                    $progress[] = $event;
                },
                folder: 'wordpress-media',
            );

            $t->same([
                $dir,
                $dir . '/empty.jpg',
                $dir . '/hero.jpg',
                $dir . '/thumb.jpg',
            ], array_map(static fn (FileInfo $file): string => $file->name, $files));
            $t->same(BlockList::EMPTY_FILE_HASH_HEX, $files[1]->blocks[0]->hashHex);
            $t->same(hash('sha256', 'abcd'), $files[2]->blocks[0]->hashHex);
            $t->same(hash('sha256', '1234'), $files[3]->blocks[0]->hashHex);
            $t->same([
                ['folder' => 'wordpress-media', 'current' => 0, 'total' => 14, 'rate' => 0.0],
                ['folder' => 'wordpress-media', 'current' => 8, 'total' => 14, 'rate' => 0.0],
                ['folder' => 'wordpress-media', 'current' => 13, 'total' => 14, 'rate' => 0.0],
            ], array_map(static fn (FolderScanProgress $event): array => $event->toArray(), $progress));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk suppresses scan progress when no hashing work is queued' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $name = 'wp-content/uploads/2026/05/already-indexed.jpg';
            syncthing_scanner_write($root, $name, 'stable indexed media');

            $scanner = new FileInfoScanner($root);
            $current = $scanner->walk([$name], hashBlocks: true, blockSize: 8)[0];

            $unchangedProgress = [];
            $unchanged = $scanner->walk(
                [$name],
                hashBlocks: true,
                blockSize: 8,
                currentFiles: [$current],
                progressLogger: static function (FolderScanProgress $event) use (&$unchangedProgress): void {
                    $unchangedProgress[] = $event;
                },
                folder: 'wordpress-media',
            );

            $metadataOnlyProgress = [];
            $metadataOnly = $scanner->walk(
                [$name],
                progressLogger: static function (FolderScanProgress $event) use (&$metadataOnlyProgress): void {
                    $metadataOnlyProgress[] = $event;
                },
                folder: 'wordpress-media',
            );

            $t->same([], $unchanged);
            $t->same([], $unchangedProgress);
            $t->same(1, count($metadataOnly));
            $t->same([], $metadataOnlyProgress);
            $t->throws(InvalidArgumentException::class, static fn () => new FolderScanProgress('wordpress-media', -1, 1));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk reports scanner item errors and continues with siblings' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_scanner_write($root, $dir . '/good.jpg', 'publishable media');
            syncthing_scanner_write($root, $dir . '/metadata-error.jpg', 'metadata read fails');

            $errors = [];
            $scanner = new FileInfoScanner(
                $root,
                scanXattrs: true,
                xattrLister: static function (string $path): array {
                    if (basename($path) === 'metadata-error.jpg') {
                        throw new RuntimeException('xattr list failed');
                    }

                    return [];
                },
            );
            $files = $scanner->walk(
                [$dir],
                errorLogger: static function (string $path, Throwable $error, string $phase) use (&$errors): void {
                    $errors[] = [$path, $phase, $error->getMessage()];
                },
            );

            $t->same([$dir, $dir . '/good.jpg'], array_map(static fn (FileInfo $file): string => $file->name, $files));
            $t->same([[
                $dir . '/metadata-error.jpg',
                'scan',
                'reading platform data: get xattr ' . $dir . '/metadata-error.jpg: xattr list failed',
            ]], $errors);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk reports directory listing errors as scan errors without Failure events' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            $blockedDir = $dir . '/private-cache';
            syncthing_scanner_write($root, $dir . '/good.jpg', 'publishable media');
            syncthing_scanner_write($root, $dir . '/after.jpg', 'later sibling');
            syncthing_scanner_write($root, $blockedDir . '/secret.zip', 'private export');

            $errors = [];
            $failureEvents = [];
            $scanner = new FileInfoScanner(
                $root,
                directoryLister: static function (string $path) use ($root, $blockedDir): array {
                    if ($path === syncthing_scanner_path($root, $blockedDir)) {
                        throw new RuntimeException('permission denied');
                    }

                    return syncthing_scanner_entries($path);
                },
            );

            $files = $scanner->walk(
                [$dir],
                errorLogger: static function (string $path, Throwable $error, string $phase) use (&$errors): void {
                    $errors[] = [$path, $phase, $error->getMessage()];
                },
                failureLogger: static function (string $type, array $data) use (&$failureEvents): void {
                    $failureEvents[] = [$type, $data];
                },
            );

            $t->same([
                $dir,
                $dir . '/after.jpg',
                $dir . '/good.jpg',
                $blockedDir,
            ], array_map(static fn (FileInfo $file): string => $file->name, $files));
            $t->same([[$blockedDir, 'scan', 'permission denied']], $errors);
            $t->same([], $failureEvents);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'maps upstream scanner walk failure warnability boundary' => static function (TestRunner $t): void {
        $t->same('Failure', FileInfoScanner::WALK_FAILURE_EVENT);
        $t->same('Unexpected error while walking the filesystem during scan', FileInfoScanner::WALK_FAILURE_EVENT_DESCRIPTION);
        $t->true(!FileInfoScanner::isWarnableWalkFailure(null));
        $t->true(!FileInfoScanner::isWarnableWalkFailure(new RuntimeException('context canceled')));
        $t->true(!FileInfoScanner::isWarnableWalkFailure(new RuntimeException('context deadline exceeded')));
        $t->true(FileInfoScanner::isWarnableWalkFailure(new RuntimeException('stale filesystem handle')));
    },
    'walk progress cancellation stops before hashing another queued file' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_scanner_write($root, $dir . '/hero.jpg', 'abcdefgh');
            syncthing_scanner_write($root, $dir . '/thumb.jpg', '12345');

            $progress = [];
            $cancelAfterFirstHash = false;
            $cancelChecks = [];
            $scanner = new FileInfoScanner($root);
            $files = $scanner->walk(
                [$dir],
                hashBlocks: true,
                blockSize: 4,
                progressLogger: static function (FolderScanProgress $event) use (&$progress, &$cancelAfterFirstHash): void {
                    $progress[] = $event->toArray();
                    $cancelAfterFirstHash = true;
                },
                folder: 'wordpress-media',
                shouldCancel: static function (?string $path) use (&$cancelAfterFirstHash, &$cancelChecks): bool {
                    if ($cancelAfterFirstHash) {
                        $cancelChecks[] = $path;
                        return true;
                    }

                    return false;
                },
            );

            $t->same([$dir, $dir . '/hero.jpg'], array_map(static fn (FileInfo $file): string => $file->name, $files));
            $t->same(hash('sha256', 'abcd'), $files[1]->blocks[0]->hashHex);
            $t->same([['folder' => 'wordpress-media', 'current' => 8, 'total' => 14, 'rate' => 0.0]], $progress);
            $t->same([$dir . '/thumb.jpg'], $cancelChecks);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk checkpoint resumes after upstream cancellation boundary' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_scanner_write($root, $dir . '/hero.jpg', 'abcdefgh');
            syncthing_scanner_write($root, $dir . '/thumb.jpg', '12345');

            $progress = [];
            $cancelAfterFirstHash = false;
            $scanner = new FileInfoScanner($root);
            $first = $scanner->walkWithCheckpoint(
                [$dir],
                hashBlocks: true,
                blockSize: 4,
                progressLogger: static function (FolderScanProgress $event) use (&$progress, &$cancelAfterFirstHash): void {
                    $progress[] = $event->toArray();
                    $cancelAfterFirstHash = true;
                },
                folder: 'wordpress-media',
                shouldCancel: static function (?string $path) use (&$cancelAfterFirstHash): bool {
                    return $cancelAfterFirstHash && $path !== null;
                },
            );

            $t->true($first->cancelled);
            $t->same($dir . '/thumb.jpg', $first->cancelledAt);
            $t->same([$dir, $dir . '/hero.jpg'], $first->completedPaths());
            $t->same([$dir], $first->resumeSubs);
            $t->same([['folder' => 'wordpress-media', 'current' => 8, 'total' => 14, 'rate' => 0.0]], $progress);
            $t->same([
                'cancelled' => true,
                'cancelledAt' => $dir . '/thumb.jpg',
                'resumeSubs' => [$dir],
                'completedPaths' => [$dir, $dir . '/hero.jpg'],
                'fileCount' => 2,
            ], $first->toArray());

            $resumeProgress = [];
            $resumed = $scanner->walkWithCheckpoint(
                $first->resumeSubs,
                hashBlocks: true,
                blockSize: 4,
                currentFiles: $first->resumeCurrentFiles(),
                progressLogger: static function (FolderScanProgress $event) use (&$resumeProgress): void {
                    $resumeProgress[] = $event->toArray();
                },
                folder: 'wordpress-media',
            );

            $t->true(!$resumed->cancelled);
            $t->same(null, $resumed->cancelledAt);
            $t->same([$dir . '/thumb.jpg'], $resumed->completedPaths());
            $t->same(hash('sha256', '1234'), $resumed->files[0]->blocks[0]->hashHex);
            $t->same([['folder' => 'wordpress-media', 'current' => 5, 'total' => 6, 'rate' => 0.0]], $resumeProgress);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk checkpoint carries folder scan progress and path errors together' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            $blockedDir = $dir . '/private-cache';
            syncthing_scanner_write($root, $dir . '/after.jpg', 'after!');
            syncthing_scanner_write($root, $dir . '/good.jpg', 'good');
            syncthing_scanner_write($root, $blockedDir . '/secret.zip', 'private export');

            $userErrors = [];
            $collector = new FolderScanEventCollector('wordpress-media');
            $scanner = new FileInfoScanner(
                $root,
                directoryLister: static function (string $path) use ($root, $blockedDir): array {
                    if ($path === syncthing_scanner_path($root, $blockedDir)) {
                        throw new RuntimeException('permission denied');
                    }

                    return syncthing_scanner_entries($path);
                },
            );

            $result = $scanner->walkWithCheckpoint(
                [$dir],
                hashBlocks: true,
                blockSize: 4,
                folder: 'wordpress-media',
                errorLogger: static function (string $path, Throwable $error, string $phase) use (&$userErrors): void {
                    $userErrors[] = [$path, $phase, $error->getMessage()];
                },
                eventCollector: $collector,
            );

            $t->same([
                $dir,
                $dir . '/after.jpg',
                $dir . '/good.jpg',
                $blockedDir,
            ], $result->completedPaths());
            $t->same([
                [
                    'path' => $blockedDir,
                    'phase' => 'scan',
                    'error' => 'permission denied',
                ],
            ], $result->scanErrors());
            $t->same([[$blockedDir, 'scan', 'permission denied']], $userErrors);
            $t->same([], $result->failureEvents());
            $t->same([
                [
                    'type' => 'FolderScanProgress',
                    'data' => [
                        'folder' => 'wordpress-media',
                        'current' => 6,
                        'total' => 11,
                        'rate' => 0.0,
                    ],
                ],
                [
                    'type' => 'FolderScanProgress',
                    'data' => [
                        'folder' => 'wordpress-media',
                        'current' => 10,
                        'total' => 11,
                        'rate' => 0.0,
                    ],
                ],
            ], $result->scanEvents());
            $summary = $result->toArray();
            $t->same('wordpress-media', $summary['folderScan']['folder']);
            $t->same($result->scanEvents(), $summary['folderScan']['events']);
            $t->same($result->scanErrors(), $summary['folderScan']['scanErrors']);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk retains current file block size within upstream hysteresis window' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $name = 'wp-content/uploads/2026/05/hero.jpg';
            $bytes = 'existing wordpress media bytes';
            syncthing_scanner_write($root, $name, $bytes);

            $current = new FileInfo(
                name: $name,
                size: strlen($bytes),
                type: FileInfo::TYPE_FILE,
                rawBlockSize: 256 << 10,
            );
            $scanner = new FileInfoScanner($root);

            $scanned = $scanner->scan($name, hashBlocks: true, currentFile: $current);
            $walked = $scanner->walk(['wp-content/uploads/2026/05'], hashBlocks: true, currentFiles: [$current]);
            $walkedByName = [];
            foreach ($walked as $file) {
                $walkedByName[$file->name] = $file;
            }

            $t->same(256 << 10, $scanned->rawBlockSize);
            $t->same(256 << 10, $walkedByName[$name]->rawBlockSize);
            $t->same(hash('sha256', $bytes), $walkedByName[$name]->blocks[0]->hashHex);
            $t->same(BlockList::blockSizeForFileSize(strlen($bytes)), (new FileInfoScanner($root))->scan($name)->rawBlockSize);
            $t->throws(InvalidArgumentException::class, static fn () => $scanner->scan($name, currentFile: $current->withName('other.jpg')));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk skips unchanged current files while preserving local flag changes' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $name = 'wp-content/uploads/2026/05/unchanged.jpg';
            $bytes = 'stable wordpress media bytes';
            syncthing_scanner_write($root, $name, $bytes);

            $scanner = new FileInfoScanner($root, localFlags: FileInfo::FLAG_LOCAL_RECEIVE_ONLY);
            $first = $scanner->walk([$name], hashBlocks: true, blockSize: 8);
            $t->same(1, count($first));
            $t->same(FileInfo::FLAG_LOCAL_RECEIVE_ONLY, $first[0]->localFlags);
            $t->same((new BlockList())->hashBlocks($first[0]->blocks), $first[0]->blocksHash);

            $t->same(null, $scanner->scanIfChanged($name, hashBlocks: true, blockSize: 8, currentFile: $first[0]));
            $t->same([], $scanner->walk([$name], hashBlocks: true, blockSize: 8, currentFiles: [$first[0]]));

            $ignoredCurrent = new FileInfo(
                name: $name,
                modifiedS: $first[0]->modifiedS,
                size: $first[0]->size,
                blocksHash: $first[0]->blocksHash,
                type: FileInfo::TYPE_FILE,
                permissions: $first[0]->permissions,
                rawBlockSize: $first[0]->rawBlockSize,
                blocks: $first[0]->blocks,
                localFlags: FileInfo::FLAG_LOCAL_IGNORED,
            );
            $rescanned = $scanner->walk([$name], hashBlocks: true, blockSize: 8, currentFiles: [$ignoredCurrent]);

            $t->same(1, count($rescanned));
            $t->same(FileInfo::FLAG_LOCAL_RECEIVE_ONLY, $rescanned[0]->localFlags);
            $t->same($first[0]->blocksHash, $rescanned[0]->previousBlocksHash);
            $t->same($first[0]->blocksHash, $rescanned[0]->blocksHash);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk ignores permission-only changes when upstream IgnorePerms is enabled' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $name = 'wp-content/uploads/2026/05/noisy-permissions.jpg';
            $path = syncthing_scanner_write($root, $name, 'permission metadata noise');
            chmod($path, 0644);
            touch($path, 1_700_006_100);
            clearstatcache(true, $path);

            $strictScanner = new FileInfoScanner($root);
            $current = $strictScanner->walk([$name])[0];
            $t->same(0644, $current->permissions & 0777);
            $t->true(!$current->noPermissions);

            chmod($path, 0600);
            clearstatcache(true, $path);

            $strictChanged = $strictScanner->walk([$name], currentFiles: [$current]);
            $t->same(1, count($strictChanged));
            $t->same(0600, $strictChanged[0]->permissions & 0777);
            $t->true(!$strictChanged[0]->noPermissions);

            $ignorePermsScanner = new FileInfoScanner($root, ignorePerms: true);
            $t->same([], $ignorePermsScanner->walk([$name], currentFiles: [$current]));
            $ignoredInfo = $ignorePermsScanner->scan($name);
            $t->same(0600, $ignoredInfo->permissions & 0777);
            $t->true($ignoredInfo->noPermissions);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'windows scanner preserves current executable bits during equivalence' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $name = 'wp-content/plugins/local-first-sync/build/index.php';
            $path = syncthing_scanner_write($root, $name, '<?php echo "plugin asset";');
            chmod($path, 0644);
            touch($path, 1_700_006_150);
            clearstatcache(true, $path);

            $current = new FileInfo(
                name: $name,
                modifiedS: 1_700_006_150,
                size: strlen('<?php echo "plugin asset";'),
                type: FileInfo::TYPE_FILE,
                permissions: 0755,
            );

            $posixScanner = new FileInfoScanner($root, platformFamily: 'Linux');
            $posixChanged = $posixScanner->walk([$name], currentFiles: [$current]);
            $t->same(1, count($posixChanged));
            $t->same(0644, $posixChanged[0]->permissions & 0777);

            $windowsScanner = new FileInfoScanner($root, platformFamily: 'Windows');
            $windowsScanned = $windowsScanner->scan($name, currentFile: $current);
            $windowsChanged = $windowsScanner->walk([$name], currentFiles: [$current]);

            $t->same(0755, $windowsScanned->permissions & 0777);
            $t->same([], $windowsChanged);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk treats modification times inside the upstream window as unchanged' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $name = 'wp-content/uploads/2026/05/fat-window.jpg';
            $path = syncthing_scanner_write($root, $name, 'fat timestamp media');
            touch($path, 1_700_006_200);
            clearstatcache(true, $path);

            $scanner = new FileInfoScanner($root);
            $current = $scanner->walk([$name])[0];
            $t->same(1_700_006_200, $current->modifiedS);

            touch($path, 1_700_006_201);
            clearstatcache(true, $path);

            $strictChanged = $scanner->walk([$name], currentFiles: [$current]);
            $t->same(1, count($strictChanged));
            $t->same(1_700_006_201, $strictChanged[0]->modifiedS);

            $insideWindow = new FileInfoScanner($root, modTimeWindowNs: 2_000_000_000);
            $t->same([], $insideWindow->walk([$name], currentFiles: [$current]));

            $atBoundary = new FileInfoScanner($root, modTimeWindowNs: 1_000_000_000);
            $t->same(1, count($atBoundary->walk([$name], currentFiles: [$current])));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk skips unchanged symlink current files and emits target changes' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            syncthing_scanner_write($root, $dir . '/original.jpg', 'original media');
            syncthing_scanner_write($root, $dir . '/replacement.jpg', 'replacement media');
            $linkName = $dir . '/current.jpg';
            $linkPath = syncthing_scanner_path($root, $linkName);
            if (!@symlink('original.jpg', $linkPath)) {
                throw new RuntimeException('symlink creation failed');
            }

            $scanner = new FileInfoScanner($root);
            $current = $scanner->walk([$linkName])[0];
            $t->same(FileInfo::TYPE_SYMLINK, $current->type);
            $t->same('original.jpg', $current->symlinkTarget);
            $t->same(null, $scanner->scanIfChanged($linkName, currentFile: $current));
            $t->same([], $scanner->walk([$linkName], currentFiles: [$current]));

            unlink($linkPath);
            if (!@symlink('replacement.jpg', $linkPath)) {
                throw new RuntimeException('symlink replacement failed');
            }

            $changed = $scanner->walk([$linkName], currentFiles: [$current]);
            $t->same(1, count($changed));
            $t->same(FileInfo::TYPE_SYMLINK, $changed[0]->type);
            $t->same('replacement.jpg', $changed[0]->symlinkTarget);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk reports upstream normalization errors when auto normalization is disabled' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            $decomposedName = $dir . '/Cafe' . "\u{0301}" . '.jpg';
            syncthing_scanner_write($root, $decomposedName, 'decomposed wordpress media');

            $scanner = new FileInfoScanner($root);

            $t->throws(RuntimeException::class, static fn () => $scanner->walk([$dir]));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk auto normalizes UTF8 filenames before emitting FileInfo' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            $decomposedLeaf = 'Cafe' . "\u{0301}" . '.jpg';
            $normalizedLeaf = 'Caf' . "\u{00e9}" . '.jpg';
            $decomposedName = $dir . '/' . $decomposedLeaf;
            $normalizedName = $dir . '/' . $normalizedLeaf;
            $decomposedPath = syncthing_scanner_path($root, $decomposedName);
            $normalizedPath = syncthing_scanner_path($root, $normalizedName);
            syncthing_scanner_write($root, $decomposedName, 'normalized wordpress media');

            $scanner = new FileInfoScanner($root, autoNormalize: true);
            $files = $scanner->walk([$dir], hashBlocks: true, blockSize: 8);
            $names = array_map(static fn (FileInfo $file): string => $file->name, $files);

            $t->same([$dir, $normalizedName], $names);
            $t->true(!file_exists($decomposedPath));
            $t->true(file_exists($normalizedPath));
            $t->same(hash('sha256', 'normaliz'), $files[1]->blocks[0]->hashHex);
        } finally {
            syncthing_scanner_rm($root);
        }
    },
    'walk reports upstream normalization conflicts without replacing the existing item' => static function (TestRunner $t): void {
        $root = syncthing_scanner_root();
        try {
            $dir = 'wp-content/uploads/2026/05';
            $decomposedName = $dir . '/Cafe' . "\u{0301}" . '.jpg';
            $normalizedName = $dir . '/Caf' . "\u{00e9}" . '.jpg';
            syncthing_scanner_write($root, $decomposedName, 'decomposed wordpress media');
            syncthing_scanner_write($root, $normalizedName, 'existing normalized media');

            $scanner = new FileInfoScanner($root, autoNormalize: true);

            $t->throws(RuntimeException::class, static fn () => $scanner->walk([$dir]));
            $t->same('decomposed wordpress media', file_get_contents(syncthing_scanner_path($root, $decomposedName)));
            $t->same('existing normalized media', file_get_contents(syncthing_scanner_path($root, $normalizedName)));
        } finally {
            syncthing_scanner_rm($root);
        }
    },
];

function syncthing_scanner_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-fileinfo-scanner-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    return $root;
}

function syncthing_scanner_path(string $root, string $name): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
}

function syncthing_scanner_write(string $root, string $name, string $bytes): string
{
    $path = syncthing_scanner_path($root, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create scanner test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write scanner test file');
    }

    return $path;
}

/**
 * @return list<string>
 */
function syncthing_scanner_entries(string $path): array
{
    $entries = scandir($path);
    if (!is_array($entries)) {
        throw new RuntimeException('failed to list scanner test directory');
    }

    return array_values(array_filter(
        $entries,
        static fn (string $entry): bool => $entry !== '.' && $entry !== '..',
    ));
}

function syncthing_scanner_rm(string $path): void
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
        syncthing_scanner_rm($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}
