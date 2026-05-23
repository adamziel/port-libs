<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-raw-entry-source-' . bin2hex(random_bytes(6));
$restoreDir = sys_get_temp_dir() . '/quadrable-wp-raw-entry-restore-' . bin2hex(random_bytes(6));

$cleanup = static function (string $dir): void {
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
};

$rawSnapshotHex = static function (array $snapshot): array {
    $out = [];
    foreach ([
        'quadrable_head',
        'quadrable_nodesLeaf',
        'quadrable_nodesInterior',
        'quadrable_key',
        'quadrable_quadb_state',
    ] as $bucket) {
        $out[$bucket] = [];
        foreach ($snapshot[$bucket] as $entry) {
            $out[$bucket][] = [
                'keyHex' => bin2hex($entry['key']),
                'valueHex' => bin2hex($entry['value']),
            ];
        }
    }

    return $out;
};

try {
    $source = QuadbStore::init($sourceDir);
    $source->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n",
        '|'
    );
    $masterRoot = $source->tree()->rootHash();

    $source->fork('wp-preview', 'master');
    $source->put('wp_posts:1', 'Preview post');
    $previewRoot = $source->tree()->rootHash();

    $source->checkout();
    $source->put('wp_posts:2', "Detached page\0serialized");
    $detachedRoot = $source->tree()->rootHash();

    $rawEntries = $rawSnapshotHex($source->lmdbRawEntrySnapshot());
    $restored = QuadbStore::restoreRawEntryDump($restoreDir, $rawEntries);
    $rawEntriesMatchAfterImport = $rawEntries === $rawSnapshotHex($restored->lmdbRawEntrySnapshot());

    $detachedPost = $restored->get('wp_posts:2');
    $restored->checkout('wp-preview');
    $previewPost = $restored->get('wp_posts:1');
    $restored->checkout('master');
    $publishedPost = $restored->get('wp_posts:1');

    echo json_encode([
        'scenario' => 'restore a WordPress Quadrable full-head store from raw LMDB cursor entries only',
        'rawEntryDigest' => QuadbStore::portableRawEntryDigest($rawEntries),
        'rawEntriesMatchAfterImport' => $rawEntriesMatchAfterImport,
        'roots' => [
            'master' => $masterRoot,
            'preview' => $previewRoot,
            'detached' => $detachedRoot,
        ],
        'readsAfterImport' => [
            'detachedPostHex' => bin2hex($detachedPost),
            'previewPost' => $previewPost,
            'publishedPost' => $publishedPost,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($restoreDir);
}
