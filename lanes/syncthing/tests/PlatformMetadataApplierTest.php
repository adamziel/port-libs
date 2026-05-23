<?php

declare(strict_types=1);

use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PlatformMetadataApplier;
use PortLibs\Syncthing\XattrsNotSupportedException;

return [
    'replaces filtered xattrs like upstream SetXattr' => static function (TestRunner $t): void {
        $root = syncthing_platform_root();
        try {
            $path = $root . DIRECTORY_SEPARATOR . 'wp-content-upload.jpg';
            file_put_contents($path, 'media bytes');
            $xattrs = [
                $path => [
                    'security.selinux' => 'host-label',
                    'user.wordpress.source' => 'playground',
                    'user.wordpress.old-import' => 'delete-me',
                    'user.wordpress.media-id' => 'old-id',
                ],
            ];
            $operations = [];
            $applier = new PlatformMetadataApplier(
                syncXattrs: true,
                xattrSetter: static function (string $xattrPath, string $name, string $value) use (&$xattrs, &$operations): bool {
                    $operations[] = ['set', $name, $value];
                    $xattrs[$xattrPath][$name] = $value;
                    return true;
                },
                xattrLister: static fn (string $xattrPath): array => array_keys($xattrs[$xattrPath] ?? []),
                xattrGetter: static function (string $xattrPath, string $name) use (&$xattrs, &$operations): ?string {
                    $operations[] = ['get', $name];
                    return $xattrs[$xattrPath][$name] ?? null;
                },
                xattrRemover: static function (string $xattrPath, string $name) use (&$xattrs, &$operations): bool {
                    $operations[] = ['remove', $name];
                    unset($xattrs[$xattrPath][$name]);
                    return true;
                },
                xattrFilter: static fn (string $name): bool => str_starts_with($name, 'user.wordpress.'),
            );
            $file = new FileInfo(
                name: 'wp-content/uploads/2026/05/hero.jpg',
                xattrs: [
                    'user.wordpress.source' => 'playground',
                    'user.wordpress.media-id' => '451',
                    'user.wordpress.origin' => 'remote-device',
                ],
            );

            $error = $applier->apply($file, $path);

            $t->same(null, $error);
            $t->same([
                ['get', 'user.wordpress.media-id'],
                ['get', 'user.wordpress.old-import'],
                ['get', 'user.wordpress.source'],
                ['remove', 'user.wordpress.old-import'],
                ['set', 'user.wordpress.media-id', '451'],
                ['set', 'user.wordpress.origin', 'remote-device'],
            ], $operations);
            $t->same([
                'security.selinux' => 'host-label',
                'user.wordpress.source' => 'playground',
                'user.wordpress.media-id' => '451',
                'user.wordpress.origin' => 'remote-device',
            ], $xattrs[$path]);
        } finally {
            syncthing_platform_rm($root);
        }
    },
    'empty desired xattr set removes only filtered current attributes' => static function (TestRunner $t): void {
        $root = syncthing_platform_root();
        try {
            $path = $root . DIRECTORY_SEPARATOR . 'metadata-cleanup.jpg';
            file_put_contents($path, 'media bytes');
            $xattrs = [
                $path => [
                    'user.wordpress.source' => 'playground',
                    'user.wordpress.media-id' => '451',
                    'security.selinux' => 'host-label',
                ],
            ];
            $removed = [];
            $applier = new PlatformMetadataApplier(
                syncXattrs: true,
                xattrLister: static fn (string $xattrPath): array => array_keys($xattrs[$xattrPath] ?? []),
                xattrGetter: static fn (string $xattrPath, string $name): ?string => $xattrs[$xattrPath][$name] ?? null,
                xattrRemover: static function (string $xattrPath, string $name) use (&$xattrs, &$removed): bool {
                    $removed[] = $name;
                    unset($xattrs[$xattrPath][$name]);
                    return true;
                },
                xattrFilter: static fn (string $name): bool => str_starts_with($name, 'user.wordpress.'),
            );

            $error = $applier->apply(new FileInfo(name: 'wp-content/uploads/2026/05/hero.jpg'), $path);

            $t->same(null, $error);
            $t->same(['user.wordpress.media-id', 'user.wordpress.source'], $removed);
            $t->same(['security.selinux' => 'host-label'], $xattrs[$path]);
        } finally {
            syncthing_platform_rm($root);
        }
    },
    'stale xattr removal failure stops before setting new xattrs' => static function (TestRunner $t): void {
        $root = syncthing_platform_root();
        try {
            $path = $root . DIRECTORY_SEPARATOR . 'metadata-retry.jpg';
            file_put_contents($path, 'media bytes');
            $set = [];
            $applier = new PlatformMetadataApplier(
                syncXattrs: true,
                xattrSetter: static function (string $_path, string $name) use (&$set): bool {
                    $set[] = $name;
                    return true;
                },
                xattrLister: static fn (): array => ['user.wordpress.old-import'],
                xattrGetter: static fn (): string => 'delete-me',
                xattrRemover: static fn (): bool => false,
                xattrFilter: static fn (string $name): bool => str_starts_with($name, 'user.wordpress.'),
            );
            $file = new FileInfo(
                name: 'wp-content/uploads/2026/05/hero.jpg',
                xattrs: ['user.wordpress.source' => 'playground'],
            );

            $error = $applier->apply($file, $path);

            $t->same('setting xattrs: remove user.wordpress.old-import failed', $error);
            $t->same([], $set);
        } finally {
            syncthing_platform_rm($root);
        }
    },
    'ignores unsupported xattr filesystems like upstream setPlatformData' => static function (TestRunner $t): void {
        $root = syncthing_platform_root();
        try {
            $path = $root . DIRECTORY_SEPARATOR . 'shared-hosting-media.jpg';
            file_put_contents($path, 'media bytes');
            $writes = [];
            $applier = new PlatformMetadataApplier(
                syncXattrs: true,
                xattrSetter: static function (string $_path, string $name) use (&$writes): bool {
                    $writes[] = ['set', $name];
                    return true;
                },
                xattrLister: static fn (): array => throw new XattrsNotSupportedException('extended attributes are not supported on this platform'),
                xattrRemover: static function (string $_path, string $name) use (&$writes): bool {
                    $writes[] = ['remove', $name];
                    return true;
                },
            );
            $file = new FileInfo(
                name: 'wp-content/uploads/2026/05/shared-hosting-media.jpg',
                xattrs: ['user.wordpress.media-id' => '451'],
            );

            $error = $applier->apply($file, $path);

            $t->same(null, $error);
            $t->same([], $writes);
        } finally {
            syncthing_platform_rm($root);
        }
    },
    'propagates xattr list failures before metadata writes' => static function (TestRunner $t): void {
        $root = syncthing_platform_root();
        try {
            $path = $root . DIRECTORY_SEPARATOR . 'metadata-list-denied.jpg';
            file_put_contents($path, 'media bytes');
            $writes = [];
            $applier = new PlatformMetadataApplier(
                syncXattrs: true,
                xattrSetter: static function (string $_path, string $name) use (&$writes): bool {
                    $writes[] = ['set', $name];
                    return true;
                },
                xattrLister: static fn (): array => throw new RuntimeException('Listxattr permission denied'),
                xattrRemover: static function (string $_path, string $name) use (&$writes): bool {
                    $writes[] = ['remove', $name];
                    return true;
                },
            );
            $file = new FileInfo(
                name: 'wp-content/uploads/2026/05/hero.jpg',
                xattrs: ['user.wordpress.media-id' => '451'],
            );

            $error = $applier->apply($file, $path);

            $t->same('setting xattrs: GetXattr: Listxattr permission denied', $error);
            $t->same([], $writes);
        } finally {
            syncthing_platform_rm($root);
        }
    },
    'propagates xattr get failures before remove or set' => static function (TestRunner $t): void {
        $root = syncthing_platform_root();
        try {
            $path = $root . DIRECTORY_SEPARATOR . 'metadata-get-denied.jpg';
            file_put_contents($path, 'media bytes');
            $writes = [];
            $applier = new PlatformMetadataApplier(
                syncXattrs: true,
                xattrSetter: static function (string $_path, string $name) use (&$writes): bool {
                    $writes[] = ['set', $name];
                    return true;
                },
                xattrLister: static fn (): array => ['user.wordpress.old-import'],
                xattrGetter: static fn (): string => throw new RuntimeException('Lgetxattr permission denied'),
                xattrRemover: static function (string $_path, string $name) use (&$writes): bool {
                    $writes[] = ['remove', $name];
                    return true;
                },
            );
            $file = new FileInfo(
                name: 'wp-content/uploads/2026/05/hero.jpg',
                xattrs: ['user.wordpress.media-id' => '451'],
            );

            $error = $applier->apply($file, $path);

            $t->same('setting xattrs: GetXattr: Lgetxattr permission denied', $error);
            $t->same([], $writes);
        } finally {
            syncthing_platform_rm($root);
        }
    },
    'propagates xattr remove hard errors before setting new xattrs' => static function (TestRunner $t): void {
        $root = syncthing_platform_root();
        try {
            $path = $root . DIRECTORY_SEPARATOR . 'metadata-remove-denied.jpg';
            file_put_contents($path, 'media bytes');
            $set = [];
            $applier = new PlatformMetadataApplier(
                syncXattrs: true,
                xattrSetter: static function (string $_path, string $name) use (&$set): bool {
                    $set[] = $name;
                    return true;
                },
                xattrLister: static fn (): array => ['user.wordpress.old-import'],
                xattrGetter: static fn (): string => 'delete-me',
                xattrRemover: static fn (): bool => throw new RuntimeException('read-only filesystem'),
                xattrFilter: static fn (string $name): bool => str_starts_with($name, 'user.wordpress.'),
            );
            $file = new FileInfo(
                name: 'wp-content/uploads/2026/05/hero.jpg',
                xattrs: ['user.wordpress.media-id' => '451'],
            );

            $error = $applier->apply($file, $path);

            $t->same('setting xattrs: remove user.wordpress.old-import: read-only filesystem', $error);
            $t->same([], $set);
        } finally {
            syncthing_platform_rm($root);
        }
    },
    'propagates xattr set hard errors with the attribute name' => static function (TestRunner $t): void {
        $root = syncthing_platform_root();
        try {
            $path = $root . DIRECTORY_SEPARATOR . 'metadata-set-denied.jpg';
            file_put_contents($path, 'media bytes');
            $attempts = [];
            $applier = new PlatformMetadataApplier(
                syncXattrs: true,
                xattrSetter: static function (string $_path, string $name) use (&$attempts): bool {
                    $attempts[] = $name;
                    throw new RuntimeException('quota exceeded');
                },
                xattrLister: static fn (): array => [],
            );
            $file = new FileInfo(
                name: 'wp-content/uploads/2026/05/hero.jpg',
                xattrs: [
                    'user.wordpress.media-id' => '451',
                    'user.wordpress.origin' => 'remote-device',
                ],
            );

            $error = $applier->apply($file, $path);

            $t->same('setting xattrs: user.wordpress.media-id: quota exceeded', $error);
            $t->same(['user.wordpress.media-id'], $attempts);
        } finally {
            syncthing_platform_rm($root);
        }
    },
    'treats absent default host xattr extension as unsupported no-op' => static function (TestRunner $t): void {
        $root = syncthing_platform_root();
        try {
            $path = $root . DIRECTORY_SEPARATOR . 'metadata-no-extension.jpg';
            file_put_contents($path, 'media bytes');
            $applier = new PlatformMetadataApplier(syncXattrs: true);
            $file = new FileInfo(
                name: 'wp-content/uploads/2026/05/hero.jpg',
                xattrs: ['user.wordpress.media-id' => '451'],
            );

            if (function_exists('xattr_list') || function_exists('xattr_get') || function_exists('xattr_set') || function_exists('xattr_remove')) {
                $file = new FileInfo(name: 'wp-content/uploads/2026/05/hero.jpg');
            }

            $error = $applier->apply($file, $path);

            $t->same(null, $error);
            $t->same(true, file_get_contents($path) === 'media bytes');
        } finally {
            syncthing_platform_rm($root);
        }
    },
    'sets symlink xattrs on the link path without following the target' => static function (TestRunner $t): void {
        $root = syncthing_platform_root();
        try {
            $targetPath = $root . DIRECTORY_SEPARATOR . 'target.jpg';
            file_put_contents($targetPath, 'target media bytes');
            $linkPath = $root . DIRECTORY_SEPARATOR . 'latest.jpg';
            if (!@symlink('target.jpg', $linkPath)) {
                throw new RuntimeException('symlink creation failed');
            }
            $xattrs = [
                $targetPath => ['user.wordpress.target' => 'untouched'],
                $linkPath => ['user.wordpress.old-link' => 'delete-me'],
            ];
            $operations = [];
            $applier = new PlatformMetadataApplier(
                syncXattrs: true,
                xattrSetter: static function (string $xattrPath, string $name, string $value) use (&$xattrs, &$operations): bool {
                    $operations[] = ['set', basename($xattrPath), $name, $value];
                    $xattrs[$xattrPath][$name] = $value;
                    return true;
                },
                xattrLister: static fn (string $xattrPath): array => array_keys($xattrs[$xattrPath] ?? []),
                xattrGetter: static function (string $xattrPath, string $name) use (&$xattrs, &$operations): ?string {
                    $operations[] = ['get', basename($xattrPath), $name];
                    return $xattrs[$xattrPath][$name] ?? null;
                },
                xattrRemover: static function (string $xattrPath, string $name) use (&$xattrs, &$operations): bool {
                    $operations[] = ['remove', basename($xattrPath), $name];
                    unset($xattrs[$xattrPath][$name]);
                    return true;
                },
                xattrFilter: static fn (string $name): bool => str_starts_with($name, 'user.wordpress.'),
            );
            $file = new FileInfo(
                name: 'wp-content/uploads/2026/05/latest.jpg',
                type: FileInfo::TYPE_SYMLINK,
                symlinkTarget: 'target.jpg',
                xattrs: ['user.wordpress.link-origin' => 'remote-device'],
            );

            $error = $applier->apply($file, $linkPath);

            $t->same(null, $error);
            $t->same([
                ['get', 'latest.jpg', 'user.wordpress.old-link'],
                ['remove', 'latest.jpg', 'user.wordpress.old-link'],
                ['set', 'latest.jpg', 'user.wordpress.link-origin', 'remote-device'],
            ], $operations);
            $t->same(['user.wordpress.target' => 'untouched'], $xattrs[$targetPath]);
            $t->same(['user.wordpress.link-origin' => 'remote-device'], $xattrs[$linkPath]);
        } finally {
            syncthing_platform_rm($root);
        }
    },
];

function syncthing_platform_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-platform-metadata-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    return $root;
}

function syncthing_platform_rm(string $path): void
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
            syncthing_platform_rm($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}
