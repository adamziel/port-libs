<?php

declare(strict_types=1);

use PortLibs\Syncthing\IgnoreMatcher;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\RequestServer;
use PortLibs\Syncthing\RequestServingResult;
use PortLibs\Syncthing\Response;

return [
    'loads upstream-style include chains relative to the current ignore file' => static function (TestRunner $t): void {
        $root = syncthing_ignore_root();
        try {
            syncthing_ignore_write($root, '.stignore', "#include excludes\nbfile\ndir1/cfile\n**/efile\n/ffile\nlost+found\n");
            syncthing_ignore_write($root, 'excludes', "dir2/dfile\n#include nested/further-excludes\n");
            syncthing_ignore_write($root, 'nested/further-excludes', "dir3\n");

            $matcher = IgnoreMatcher::fromFile($root . '/.stignore');

            $t->true(!$matcher->match('afile')->isIgnored());
            $t->true($matcher->match('bfile')->isIgnored());
            $t->true($matcher->match('dir1/cfile')->isIgnored());
            $t->true($matcher->match('dir2/dfile')->isIgnored());
            $t->true($matcher->match('dir3')->isIgnored());
            $t->true($matcher->match('dir3/asset.jpg')->isIgnored());
            $t->true($matcher->match('dir1/efile')->isIgnored());
            $t->true($matcher->match('ffile')->isIgnored());
            $t->true(!$matcher->match('dir1/ffile')->isIgnored());
            $t->true($matcher->match('lost+found')->isIgnored());
        } finally {
            syncthing_ignore_rm($root);
        }
    },
    'maps custom escape characters bracket ranges and brace alternatives' => static function (TestRunner $t): void {
        $matcher = IgnoreMatcher::fromLines([
            '#escape=|',
            'wp-content/uploads/2026/cache/|{literal|}/asset|?.jpg',
            'wp-content/uploads/2026/exports/|*draft|*.zip',
            'wp-content/uploads/2026/revisions/{draft,private}.zip',
            'wp-content/uploads/2026/sizes/image-[0-9].jpg',
        ]);

        $t->true($matcher->match('wp-content/uploads/2026/cache/{literal}/asset?.jpg')->isIgnored());
        $t->true(!$matcher->match('wp-content/uploads/2026/cache/{literal}/assetx.jpg')->isIgnored());
        $t->true($matcher->match('wp-content/uploads/2026/exports/*draft*.zip')->isIgnored());
        $t->true(!$matcher->match('wp-content/uploads/2026/exports/mydraft.zip')->isIgnored());
        $t->true($matcher->match('wp-content/uploads/2026/revisions/draft.zip')->isIgnored());
        $t->true($matcher->match('wp-content/uploads/2026/revisions/private.zip')->isIgnored());
        $t->true(!$matcher->match('wp-content/uploads/2026/revisions/public.zip')->isIgnored());
        $t->true($matcher->match('wp-content/uploads/2026/sizes/image-7.jpg')->isIgnored());
        $t->true(!$matcher->match('wp-content/uploads/2026/sizes/image-x.jpg')->isIgnored());
    },
    'rejects invalid include escape placement and applies upstream skip-dir boundary' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => IgnoreMatcher::fromLines(['#include']));
        $t->throws(InvalidArgumentException::class, static fn () => IgnoreMatcher::fromLines(['#escape=||', 'wp-content/**']));
        $t->throws(InvalidArgumentException::class, static fn () => IgnoreMatcher::fromLines(['wp-content/**', '#escape=|']));

        $plain = IgnoreMatcher::fromLines(['wp-content/uploads/2026/private/**']);
        $t->true($plain->match('wp-content/uploads/2026/private/secret.jpg')->canSkipDir());

        $withExclude = IgnoreMatcher::fromLines([
            '!wp-content/uploads/2026/private/keep.jpg',
            'wp-content/uploads/2026/private/**',
        ]);
        $ignored = $withExclude->match('wp-content/uploads/2026/private/secret.jpg');
        $t->true($ignored->isIgnored());
        $t->true(!$ignored->canSkipDir());
    },
    'uses included WordPress ignore snippets before serving request bytes' => static function (TestRunner $t): void {
        $root = syncthing_ignore_root();
        try {
            syncthing_ignore_write($root, '.stignore', "#include wp-private.ignore\n#escape=|\nwp-content/uploads/2026/literal/|*cache|*.zip\n");
            syncthing_ignore_write($root, 'wp-private.ignore', "(?d)(?i)wp-content/uploads/2026/private/**\n");

            $privateName = 'wp-content/uploads/2026/private/export.zip';
            $literalName = 'wp-content/uploads/2026/literal/*cache*.zip';
            $publicName = 'wp-content/uploads/2026/public/hero.jpg';
            syncthing_ignore_write($root, $privateName, 'private export must stay local');
            syncthing_ignore_write($root, $literalName, 'literal glob cache must stay local');
            syncthing_ignore_write($root, $publicName, 'public media bytes');

            $matcher = IgnoreMatcher::fromFile($root . '/.stignore');
            $server = new RequestServer('wordpress-media', $root, ['playground-peer'], ignoreMatcher: $matcher);

            $private = $server->serve('playground-peer', new Request(
                folder: 'wordpress-media',
                name: $privateName,
                size: 30,
                hashHex: hash('sha256', 'private export must stay local'),
            ));
            $t->same(Response::CODE_INVALID_FILE, $private->response->code);
            $t->same(RequestServingResult::SOURCE_NONE, $private->source);
            $t->same('ignored filename', $private->reason);

            $literal = $server->serve('playground-peer', new Request(
                folder: 'wordpress-media',
                name: $literalName,
                size: 34,
                hashHex: hash('sha256', 'literal glob cache must stay local'),
            ));
            $t->same(Response::CODE_INVALID_FILE, $literal->response->code);
            $t->same('ignored filename', $literal->reason);

            $public = $server->serve('playground-peer', new Request(
                folder: 'wordpress-media',
                name: $publicName,
                size: 18,
                hashHex: hash('sha256', 'public media bytes'),
            ));
            $t->true($public->successful());
            $t->same(RequestServingResult::SOURCE_FINAL, $public->source);
            $t->same('public media bytes', $public->response->data);
        } finally {
            syncthing_ignore_rm($root);
        }
    },
];

function syncthing_ignore_root(): string
{
    $root = sys_get_temp_dir() . '/syncthing-ignore-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Failed to create temporary ignore test root');
    }

    return $root;
}

function syncthing_ignore_write(string $root, string $name, string $bytes): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create ignore test directory');
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Failed to write ignore test file');
    }
}

function syncthing_ignore_rm(string $path): void
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
