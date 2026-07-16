<?php

declare(strict_types=1);

use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\Proof;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-lmdb-layout-source-' . bin2hex(random_bytes(6));
$proofDir = sys_get_temp_dir() . '/quadrable-wp-lmdb-layout-proof-' . bin2hex(random_bytes(6));

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

$uint64le = static function (string $bytes): int {
    $parts = unpack('Vlow/Vhigh', $bytes);
    if (!is_array($parts)) {
        throw new RuntimeException('unable to unpack uint64');
    }

    return $parts['low'] + ($parts['high'] * 4294967296);
};

$headTable = static function (array $bucket) use ($uint64le): array {
    $heads = [];
    foreach ($bucket as $head => $nodeIdBytes) {
        $heads[$head] = $uint64le($nodeIdBytes);
    }

    return $heads;
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

$nodeTypeCounts = static function (array $bucket) use ($uint64le): array {
    $counts = [];
    foreach ($bucket as $raw) {
        $type = (string) ($uint64le(substr($raw, 0, 8)) % 16);
        $counts[$type] = ($counts[$type] ?? 0) + 1;
    }
    ksort($counts, SORT_NUMERIC);

    return $counts;
};

try {
    $repo = QuadbStore::init($sourceDir);
    $repo->importLines(
        "wp_options:siteurl|https://example.test\n"
        . "wp_options:home|https://example.test\n"
        . "wp_posts:1|Published post\n"
        . "wp_postmeta:1:_thumbnail_id|42\n",
        '|'
    );

    $lmdb = $repo->lmdbBucketSnapshot();
    $firstLeaf = reset($lmdb['quadrable_nodesLeaf']);
    $firstInterior = reset($lmdb['quadrable_nodesInterior']);

    $trustedRoot = $repo->tree()->rootHash();
    $proofHex = $repo->exportProofHex([
        'wp_options:siteurl',
        'wp_posts:404',
    ], Proof::ENCODING_FULL_KEYS);

    $proofRepo = QuadbStore::init($proofDir);
    $proofRepo->checkout('zz-first-delegated-preview');
    $proofRepo->importProofHex($proofHex, $trustedRoot);
    $proofRepo->checkout('aa-second-delegated-preview');
    $proofRepo->importProofHex($proofHex, $trustedRoot);
    $proofRepo->checkout('zz-first-delegated-preview');
    $proofRepo->fork('zz-first-fork');
    $proofLmdb = $proofRepo->lmdbBucketSnapshot();
    $proofHeads = $headTable($proofLmdb['quadrable_head']);

    echo json_encode([
        'scenario' => 'inspect native quadb store through upstream LMDB bucket layout for WordPress backups',
        'fullHead' => [
            'currentState' => $lmdb['quadrable_quadb_state'],
            'headTableNodeIds' => $headTable($lmdb['quadrable_head']),
            'bucketCounts' => [
                'quadrable_nodesLeaf' => count($lmdb['quadrable_nodesLeaf']),
                'quadrable_nodesInterior' => count($lmdb['quadrable_nodesInterior']),
                'quadrable_key' => count($lmdb['quadrable_key']),
            ],
            'rawNodeBytesMatchStats' => $rawNodeBytes($lmdb) === $repo->stats()['numBytes'],
            'firstLeafRawHexPrefix' => is_string($firstLeaf) ? substr(bin2hex($firstLeaf), 0, 96) : '',
            'firstInteriorRawHexPrefix' => is_string($firstInterior) ? substr(bin2hex($firstInterior), 0, 96) : '',
            'trackedLeafKeys' => array_values($lmdb['quadrable_key']),
        ],
        'delegatedProofHead' => [
            'currentState' => $proofLmdb['quadrable_quadb_state'],
            'headTableNodeIds' => $proofHeads,
            'allocationOrderPreservesImportHistory' => $proofHeads['zz-first-delegated-preview'] < $proofHeads['aa-second-delegated-preview'],
            'forkSharesImportedRootNodeId' => $proofHeads['zz-first-delegated-preview'] === $proofHeads['zz-first-fork'],
            'bucketCounts' => [
                'quadrable_nodesLeaf' => count($proofLmdb['quadrable_nodesLeaf']),
                'quadrable_nodesInterior' => count($proofLmdb['quadrable_nodesInterior']),
                'quadrable_key' => count($proofLmdb['quadrable_key']),
            ],
            'rawNodeBytesForAllProofHeads' => $rawNodeBytes($proofLmdb),
            'currentHeadStatsNumBytes' => $proofRepo->stats()['numBytes'],
            'rawNodeBytesIncludeBothIndependentImports' => $rawNodeBytes($proofLmdb) > $proofRepo->stats()['numBytes'],
            'leafNodeTypes' => $nodeTypeCounts($proofLmdb['quadrable_nodesLeaf']),
            'interiorNodeTypes' => $nodeTypeCounts($proofLmdb['quadrable_nodesInterior']),
            'trackedLeafKeys' => array_values($proofLmdb['quadrable_key']),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($proofDir);
}
