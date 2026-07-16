<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-proof-merge-gc-source-' . bin2hex(random_bytes(6));
$targetDir = sys_get_temp_dir() . '/quadrable-wp-proof-merge-gc-target-' . bin2hex(random_bytes(6));

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

$rawNodeBytes = static function (array $lmdb): int {
    $bytes = 0;
    foreach ($lmdb['quadrable_nodesLeaf'] as $raw) {
        $bytes += strlen($raw);
    }
    foreach ($lmdb['quadrable_nodesInterior'] as $raw) {
        $bytes += strlen($raw);
    }

    return $bytes;
};

$nodeCount = static function (array $lmdb): int {
    return count($lmdb['quadrable_nodesLeaf']) + count($lmdb['quadrable_nodesInterior']);
};

$rawEntryKeyHexes = static function (array $entries): array {
    return array_map(
        static fn (array $entry): string => bin2hex($entry['key']),
        $entries
    );
};

try {
    $source = QuadbStore::init($sourceDir);
    $source->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n"
        . "wp_posts:2|Second post\n",
        '|'
    );

    $trustedRoot = $source->tree()->rootHash();
    $siteProofHex = $source->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
    $postProofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

    $target = QuadbStore::init($targetDir);
    $target->checkout('delegated-preview');
    $target->importProofHex($siteProofHex, $trustedRoot);
    $target->mergeProofHex($postProofHex);

    $before = $target->lmdbBucketSnapshot();
    $beforeRaw = $target->lmdbRawEntrySnapshot();
    $gcOutput = rtrim($target->garbageCollectText(), "\r\n");
    $after = $target->lmdbBucketSnapshot();
    $afterRaw = $target->lmdbRawEntrySnapshot();
    $beforeInteriorKeys = $rawEntryKeyHexes($beforeRaw['quadrable_nodesInterior']);
    $afterInteriorKeys = $rawEntryKeyHexes($afterRaw['quadrable_nodesInterior']);

    echo json_encode([
        'scenario' => 'merge delegated WordPress proofs and sweep retained proof-import nodes with quadb gc',
        'trustedRoot' => $trustedRoot,
        'delegatedReads' => [
            'siteurl' => $target->get('wp_options:siteurl'),
            'post1' => $target->get('wp_posts:1'),
        ],
        'beforeGc' => [
            'projectedNodeCount' => $nodeCount($before),
            'projectedRawNodeBytes' => $rawNodeBytes($before),
            'currentHeadStatsBytes' => $target->stats()['numBytes'],
            'containsRetainedMergeProofImports' => $rawNodeBytes($before) > $target->stats()['numBytes'],
        ],
        'gcOutput' => $gcOutput,
        'afterGc' => [
            'projectedNodeCount' => $nodeCount($after),
            'projectedRawNodeBytes' => $rawNodeBytes($after),
            'currentHeadStatsBytes' => $target->stats()['numBytes'],
            'retainedImportsSwept' => $nodeCount($after) < $nodeCount($before),
        ],
        'rawCursorBackupManifest' => [
            'beforeGcInteriorKeyHexes' => $beforeInteriorKeys,
            'afterGcInteriorKeyHexes' => $afterInteriorKeys,
            'survivingInteriorKeysPreserved' => array_values(array_intersect(
                $beforeInteriorKeys,
                $afterInteriorKeys
            )) === $afterInteriorKeys,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($targetDir);
}
