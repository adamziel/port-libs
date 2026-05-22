<?php

declare(strict_types=1);

use PortLibs\Quadrable\HashTree;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\QuadbStore;

return [
    'native quadb store reopens the current named head and integer import export lines' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $t->same('master', $repo->currentHeadName());
            $t->same(2, $repo->importIntegerLines("1,wp_options:siteurl=https://example.test\n3,wp_posts:1=Hello world\n"));

            $master = $repo->tree();
            $masterRoot = $master->rootHash();
            $masterHeadNodeId = $master->headNodeId();

            $reopened = QuadbStore::open($dir);
            $t->same('master', $reopened->currentHeadName());
            $t->same($masterRoot, $reopened->tree()->rootHash());
            $t->same($masterHeadNodeId, $reopened->tree()->headNodeId());
            $t->same([
                '1,wp_options:siteurl=https://example.test',
                '3,wp_posts:1=Hello world',
            ], quadrableQuadbSortedLines($reopened->exportIntegerLines()));

            $preview = $reopened->checkout('wp-preview');
            $t->same(HashTree::EMPTY_HASH, $preview->rootHash());
            $t->same(2, $reopened->importIntegerLines("3,wp_posts:1=Preview edit\n4,wp_postmeta:1:_thumbnail_id=42\n"));

            $again = QuadbStore::open($dir);
            $t->same('wp-preview', $again->currentHeadName());
            $t->same('wp_posts:1=Preview edit', $again->tree()->getKey(Key::fromInteger(3)));
            $t->same('wp_postmeta:1:_thumbnail_id=42', $again->tree()->getKey(Key::fromInteger(4)));

            $restoredMaster = $again->checkout('master');
            $t->same($masterRoot, $restoredMaster->rootHash());
            $t->same($masterHeadNodeId, $restoredMaster->headNodeId());
            $t->same('wp_posts:1=Hello world', $restoredMaster->getKey(Key::fromInteger(3)));
            $t->same(null, $restoredMaster->getKey(Key::fromInteger(4)));

            $t->throws(RuntimeException::class, static fn () => $again->importIntegerLines("missing separator\n"));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store forks named heads across reopen without copying unchanged leaves' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importIntegerLines("1,wp_options:siteurl=https://example.test\n2,wp_options:home=https://example.test\n3,wp_posts:1=Hello world\n");

            $master = $repo->tree();
            $masterSiteUrlNodeId = 0;
            $t->same('wp_options:siteurl=https://example.test', $master->getKey(Key::fromInteger(1), $masterSiteUrlNodeId));
            $masterHeadNodeId = $master->headNodeId();
            $masterRoot = $master->rootHash();

            $snapshot = $repo->fork('wp-snapshot');
            $t->same($masterHeadNodeId, $snapshot->headNodeId());
            $t->same($masterRoot, $snapshot->rootHash());

            $repo->importIntegerLines("3,wp_posts:1=Published update\n5,wp_posts:2=New page\n");
            $updatedSnapshot = $repo->tree();
            $snapshotSiteUrlNodeId = 0;
            $t->same('wp_options:siteurl=https://example.test', $updatedSnapshot->getKey(Key::fromInteger(1), $snapshotSiteUrlNodeId));
            $t->same($masterSiteUrlNodeId, $snapshotSiteUrlNodeId);

            $reopened = QuadbStore::open($dir);
            $t->same('wp-snapshot', $reopened->currentHeadName());
            $t->same('wp_posts:1=Published update', $reopened->tree()->getKey(Key::fromInteger(3)));
            $t->same('wp_posts:2=New page', $reopened->tree()->getKey(Key::fromInteger(5)));

            $restoredMaster = $reopened->checkout('master');
            $t->same($masterRoot, $restoredMaster->rootHash());
            $t->same('wp_posts:1=Hello world', $restoredMaster->getKey(Key::fromInteger(3)));
            $t->same(null, $restoredMaster->getKey(Key::fromInteger(5)));

            $releaseCopy = $reopened->fork('wp-release-copy', 'master');
            $t->same($masterHeadNodeId, $releaseCopy->headNodeId());
            $t->same($masterRoot, $releaseCopy->rootHash());
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store persists detached fork state across reopen' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importIntegerLines("1,wp_options:siteurl=https://example.test\n3,wp_posts:1=Hello world\n");
            $masterRoot = $repo->tree()->rootHash();

            $detached = $repo->fork();
            $t->true($repo->isDetachedHead());
            $t->same(null, $repo->currentHeadName());
            $t->same($masterRoot, $detached->rootHash());

            $detached->putKey(Key::fromInteger(3), 'wp_posts:1=Detached preview edit');
            $detached->putKey(Key::fromInteger(4), 'wp_postmeta:1:_thumbnail_id=42');
            $repo->save($detached);

            $reopened = QuadbStore::open($dir);
            $t->true($reopened->isDetachedHead());
            $t->same(null, $reopened->currentHeadName());
            $t->same('wp_posts:1=Detached preview edit', $reopened->tree()->getKey(Key::fromInteger(3)));
            $t->same('wp_postmeta:1:_thumbnail_id=42', $reopened->tree()->getKey(Key::fromInteger(4)));

            $master = $reopened->checkout('master');
            $t->same($masterRoot, $master->rootHash());
            $t->same('wp_posts:1=Hello world', $master->getKey(Key::fromInteger(3)));
            $t->same(null, $master->getKey(Key::fromInteger(4)));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store emits and applies tracked string-key patch lines' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $t->same(3, $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Hello world\n",
                '|'
            ));

            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-preview');
            $repo->put('wp_posts:1', 'Preview edit');
            $repo->put('wp_posts:2', 'New page');
            $repo->delete('wp_options:home');

            $previewRoot = $repo->tree()->rootHash();
            $patch = $repo->diffLines('master', '|');
            $t->same([
                '+wp_posts:1|Preview edit',
                '+wp_posts:2|New page',
                '-wp_options:home|https://example.test',
                '-wp_posts:1|Hello world',
            ], quadrableQuadbSortedLines($patch));

            $reopened = QuadbStore::open($dir);
            $t->same($patch, $reopened->diffLines('master', '|'));

            $replica = $reopened->fork('wp-replica', 'master');
            $t->same($masterRoot, $replica->rootHash());
            $t->same($masterHeadNodeId, $replica->headNodeId());

            $t->same(4, $reopened->applyPatchLines("# preview patch\n" . $patch, '|'));
            $t->same($previewRoot, $reopened->tree()->rootHash());
            $t->same('Preview edit', $reopened->get('wp_posts:1'));
            $t->same('New page', $reopened->get('wp_posts:2'));
            $t->throws(RuntimeException::class, static fn () => $reopened->get('wp_options:home'));

            $t->same([
                'wp_options:siteurl|https://example.test',
                'wp_posts:1|Preview edit',
                'wp_posts:2|New page',
            ], quadrableQuadbSortedLines($reopened->exportLines('|')));

            $t->throws(RuntimeException::class, static fn () => $reopened->applyPatchLines("\n", '|'));
            $t->throws(RuntimeException::class, static fn () => $reopened->applyPatchLines("~wp_posts:1|bad\n", '|'));
            $t->throws(RuntimeException::class, static fn () => $reopened->applyPatchLines("+wp_posts:1 missing separator\n", '|'));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store formats root status and sorted head output' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", $repo->rootText());
            $t->same("Head: master\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", $repo->statusText());
            $t->same('', $repo->headText());

            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-release');
            $repo->put('wp_posts:2', 'Released page');
            $releaseRoot = $repo->tree()->rootHash();
            $releaseHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-preview', 'master');
            $repo->put('wp_posts:1', 'Preview edit');
            $previewRoot = $repo->tree()->rootHash();
            $previewHeadNodeId = $repo->tree()->headNodeId();

            $t->same('0x' . $previewRoot . "\n", $repo->rootText());
            $t->same("Head: wp-preview\nRoot: 0x{$previewRoot} ({$previewHeadNodeId})\n", $repo->statusText());
            $t->same([
                "=> wp-preview : 0x{$previewRoot} ({$previewHeadNodeId})",
                "   wp-release : 0x{$releaseRoot} ({$releaseHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $reopened = QuadbStore::open($dir);
            $t->same($repo->headText(), $reopened->headText());
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store removes named and current heads like quadb head rm' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-preview');
            $repo->put('wp_posts:1', 'Preview edit');
            $previewRoot = $repo->tree()->rootHash();
            $previewHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-throwaway', 'master');
            $repo->put('wp_posts:2', 'Throwaway preview');
            $repo->removeHead('wp-throwaway');
            $repo->checkout('wp-preview');

            $t->same([
                "=> wp-preview : 0x{$previewRoot} ({$previewHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $repo->removeHead();
            $t->same('wp-preview', $repo->currentHeadName());
            $t->same(HashTree::EMPTY_HASH, $repo->tree()->rootHash());
            $t->same("Head: wp-preview\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", $repo->statusText());
            $t->same([
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $repo->put('wp_posts:3', 'Recreated preview head');
            $recreatedRoot = $repo->tree()->rootHash();
            $recreatedHeadNodeId = $repo->tree()->headNodeId();

            $t->same([
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
                "=> wp-preview : 0x{$recreatedRoot} ({$recreatedHeadNodeId})",
            ], quadrableQuadbOutputLines(QuadbStore::open($dir)->headText()));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store head rm resets detached head to an empty tree' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines("wp_posts:1|Published post\n", '|');
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $detached = $repo->fork();
            $t->true($repo->isDetachedHead());
            $t->same($masterRoot, $detached->rootHash());
            $t->same([
                "D> [detached] : 0x{$masterRoot} ({$masterHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $repo->removeHead();
            $t->true($repo->isDetachedHead());
            $t->same(HashTree::EMPTY_HASH, $repo->tree()->rootHash());
            $t->same([
                'D> [detached] : 0x' . HashTree::EMPTY_HASH . ' (0)',
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines(QuadbStore::open($dir)->headText()));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
];

function quadrableQuadbTempDir(): string
{
    return sys_get_temp_dir() . '/quadrable-quadb-' . bin2hex(random_bytes(6));
}

function quadrableQuadbRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($dir);
}

/**
 * @return list<string>
 */
function quadrableQuadbSortedLines(string $lines): array
{
    $trimmed = trim($lines);
    if ($trimmed === '') {
        return [];
    }

    $output = explode("\n", $trimmed);
    sort($output, SORT_STRING);

    return $output;
}

/**
 * @return list<string>
 */
function quadrableQuadbOutputLines(string $lines): array
{
    $trimmed = rtrim($lines, "\r\n");
    if ($trimmed === '') {
        return [];
    }

    return explode("\n", $trimmed);
}
