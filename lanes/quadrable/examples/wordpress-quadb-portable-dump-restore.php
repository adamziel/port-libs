<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-dump-source-' . bin2hex(random_bytes(6));
$restoreDir = sys_get_temp_dir() . '/quadrable-wp-dump-restore-' . bin2hex(random_bytes(6));
$privateDir = sys_get_temp_dir() . '/quadrable-wp-dump-private-' . bin2hex(random_bytes(6));
$privateRestoreDir = sys_get_temp_dir() . '/quadrable-wp-dump-private-restore-' . bin2hex(random_bytes(6));

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

$bucketCounts = static function (array $rawEntries): array {
    $counts = [];
    foreach ($rawEntries as $bucket => $entries) {
        $counts[$bucket] = count($entries);
    }

    return $counts;
};

try {
    $repo = QuadbStore::init($sourceDir);
    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n"
        . "wp_posts:2|Second post\n",
        '|'
    );
    $masterRoot = $repo->tree()->rootHash();
    $proofHex = $repo->exportProofHex([
        'wp_options:siteurl',
        'wp_posts:1',
    ], Proof::ENCODING_FULL_KEYS);

    $repo->fork('wp-preview', 'master');
    $repo->put('wp_posts:1', 'Preview post');

    $repo->checkout('delegated-proof');
    $repo->importProofHex($proofHex, $masterRoot);
    $repo->put('wp_options:siteurl', 'https://delegated.example.test');

    $repo->checkout();
    $repo->importProofHex($proofHex, $masterRoot);
    $repo->put('wp_posts:1', 'Detached delegated post');

    $dump = $repo->exportPortableDump();
    $restored = QuadbStore::restorePortableDump($restoreDir, $dump);

    $private = QuadbStore::init($privateDir, false);
    $private->put('wp_options:private', "secret\0serialized-option");
    $private->put('wp_posts:1', 'Private post');
    $privateRoot = $private->tree()->rootHash();
    $privateProofHex = $private->exportProofHex([
        'wp_options:private',
        'wp_posts:404',
    ]);
    $private->checkout('private-proof');
    $private->importProofHex($privateProofHex, $privateRoot);
    $private->put('wp_options:private', "delegated\0secret");

    $privateDump = $private->exportPortableDump();
    $privateRestored = QuadbStore::restorePortableDump($privateRestoreDir, $privateDump);

    echo json_encode([
        'scenario' => 'restore WordPress Quadrable preview stores from portable raw-cursor dumps',
        'trackedStore' => [
            'dumpStatus' => $dump['current'],
            'rawEntryDigest' => $dump['rawEntryDigest'],
            'bucketCounts' => $bucketCounts($dump['rawEntries']),
            'rawEntriesMatchAfterRestore' => $dump['rawEntries'] === $rawSnapshotHex($restored->lmdbRawEntrySnapshot()),
            'rawEntryDigestMatches' => $dump['rawEntryDigest'] === QuadbStore::portableRawEntryDigest($dump['rawEntries']),
            'detachedDelegatedPost' => $restored->get('wp_posts:1'),
            'headsAfterRestore' => array_column($restored->lmdbRawEntrySnapshot()['quadrable_head'], 'key'),
        ],
        'privateNoTrackStore' => [
            'dumpStatus' => $privateDump['current'],
            'rawEntryDigest' => $privateDump['rawEntryDigest'],
            'bucketCounts' => $bucketCounts($privateDump['rawEntries']),
            'rawEntriesMatchAfterRestore' => $privateDump['rawEntries'] === $rawSnapshotHex($privateRestored->lmdbRawEntrySnapshot()),
            'rawEntryDigestMatches' => $privateDump['rawEntryDigest'] === QuadbStore::portableRawEntryDigest($privateDump['rawEntries']),
            'keyBucketEmpty' => $privateRestored->lmdbRawEntrySnapshot()['quadrable_key'] === [],
            'delegatedPrivateOptionHex' => bin2hex($privateRestored->get('wp_options:private')),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($restoreDir);
    $cleanup($privateDir);
    $cleanup($privateRestoreDir);
}
