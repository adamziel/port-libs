<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoComparison;
use PortLibs\Syncthing\FileInfoScanner;
use PortLibs\Syncthing\IgnoreMatcher;

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
