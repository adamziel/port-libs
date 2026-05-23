<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$restoreDir = sys_get_temp_dir() . '/quadrable-wp-sequential-raw-merge-' . bin2hex(random_bytes(6));

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
    $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-sequential-raw-restored-merge-oracle.json';
    $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($oracle)
        || !isset(
            $oracle['fixtureValues'],
            $oracle['beforeUpdate']['entries'],
            $oracle['afterFirstMergeBeforeSecond']['entries'],
            $oracle['afterSecondMergeBeforeGc']['entries'],
            $oracle['afterGc']['entries'],
            $oracle['gc'],
            $oracle['updatedRootHex'],
            $oracle['firstMergedRootHex'],
            $oracle['secondMergedRootHex']
        )
        || !is_array($oracle['fixtureValues'])
        || !is_array($oracle['beforeUpdate']['entries'])
        || !is_array($oracle['afterFirstMergeBeforeSecond']['entries'])
        || !is_array($oracle['afterSecondMergeBeforeGc']['entries'])
        || !is_array($oracle['afterGc']['entries'])
        || !is_array($oracle['gc'])
    ) {
        throw new RuntimeException('malformed upstream sequential raw-restored mergeProof oracle fixture');
    }

    $values = $oracle['fixtureValues'];
    $store = QuadbStore::restoreRawEntryDump($restoreDir, $oracle['beforeUpdate']['entries']);
    $beforeImportMatches = $oracle['beforeUpdate']['entries'] === $rawSnapshotHex($store->lmdbRawEntrySnapshot());

    $store->put($values['siteUrlKey'], $values['updatedUrl']);
    $authoritative = new SparseTree();
    $authoritative->change()
        ->put($values['siteUrlKey'], $values['updatedUrl'])
        ->put($values['homeKey'], $values['originalUrl'])
        ->put($values['postKey'], $values['postValue'])
        ->apply();

    $homeProof = $authoritative->exportProof([$values['homeKey']])
        ->encode(Proof::ENCODING_FULL_KEYS);
    $postProof = $authoritative->exportProof([$values['postKey']])
        ->encode(Proof::ENCODING_FULL_KEYS);

    $firstMergeRoot = $store->mergeProofBytes($homeProof);
    $afterFirstMerge = $rawSnapshotHex($store->lmdbRawEntrySnapshot());
    $homeAfterFirstMerge = $store->get($values['homeKey']);

    $secondMergeRoot = $store->mergeProofBytes($postProof);
    $afterSecondMerge = $rawSnapshotHex($store->lmdbRawEntrySnapshot());
    $postAfterSecondMerge = $store->get($values['postKey']);

    $gcText = $store->garbageCollectText();
    $afterGc = $rawSnapshotHex($store->lmdbRawEntrySnapshot());

    echo json_encode([
        'scenario' => 'restore a WordPress delegated proof head from raw LMDB entries and merge home/post proofs sequentially',
        'head' => $store->currentHeadName(),
        'beforeImportMatchesOracle' => $beforeImportMatches,
        'roots' => [
            'updated' => $oracle['updatedRootHex'],
            'firstMerge' => $firstMergeRoot,
            'secondMerge' => $secondMergeRoot,
            'firstMatchesOracle' => $firstMergeRoot === $oracle['firstMergedRootHex'],
            'secondMatchesOracle' => $secondMergeRoot === $oracle['secondMergedRootHex'],
        ],
        'mergeReads' => [
            'siteurl' => $store->get($values['siteUrlKey']),
            'homeAfterFirstMerge' => $homeAfterFirstMerge,
            'postAfterSecondMerge' => $postAfterSecondMerge,
        ],
        'rawProjection' => [
            'afterFirstMergeMatchesOracle' => $afterFirstMerge === $oracle['afterFirstMergeBeforeSecond']['entries'],
            'afterSecondMergeMatchesOracle' => $afterSecondMerge === $oracle['afterSecondMergeBeforeGc']['entries'],
            'afterGcMatchesOracle' => $afterGc === $oracle['afterGc']['entries'],
            'bucketCountsAfterSecondMerge' => $bucketCounts($afterSecondMerge),
            'bucketCountsAfterGc' => $bucketCounts($afterGc),
        ],
        'gc' => [
            'text' => trim($gcText),
            'oracleGarbage' => $oracle['gc']['garbage'],
            'oracleTotal' => $oracle['gc']['total'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($restoreDir);
}
