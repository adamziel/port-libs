<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoComparison;
use PortLibs\Syncthing\FileInfoScanner;

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
                ['scanned-platform.jpg', 'user.wordpress.source'],
                ['scanned-platform.jpg', 'user.wordpress.too-large'],
                ['scanned-platform.jpg', 'user.wordpress.origin'],
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
