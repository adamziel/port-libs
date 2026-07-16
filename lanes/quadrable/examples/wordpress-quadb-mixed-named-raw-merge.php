<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$restoreDir = sys_get_temp_dir() . '/quadrable-wp-mixed-named-raw-merge-' . bin2hex(random_bytes(6));

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

$oracleBytes = static function (mixed $hex): string {
    if (!is_string($hex) || !preg_match('/^(?:[0-9a-f]{2})*$/', $hex)) {
        throw new RuntimeException('malformed upstream oracle byte hex');
    }

    $bytes = hex2bin($hex);
    if ($bytes === false) {
        throw new RuntimeException('malformed upstream oracle byte hex');
    }

    return $bytes;
};

try {
    $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-mixed-named-raw-restored-merge-oracle.json';
    $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($oracle)
        || !isset(
            $oracle['fixtureValues'],
            $oracle['beforeUpdate']['entries'],
            $oracle['afterGc']['entries'],
            $oracle['gc'],
            $oracle['updatedRootHex'],
            $oracle['mergedRootHex']
        )
        || !is_array($oracle['fixtureValues'])
        || !is_array($oracle['beforeUpdate']['entries'])
        || !is_array($oracle['afterGc']['entries'])
        || !is_array($oracle['gc'])
    ) {
        throw new RuntimeException('malformed upstream mixed named raw-restored mergeProof oracle fixture');
    }

    $values = $oracle['fixtureValues'];
    $binaryKey = $oracleBytes($values['binaryKeyHex']);
    $binaryValue = $oracleBytes($values['binaryValueHex']);
    $delegatedValue = $oracleBytes($values['delegatedValueHex']);
    $detachedMergedValue = $oracleBytes($values['detachedMergedValueHex']);
    $privateDelegatedValue = $oracleBytes($values['privateDelegatedValueHex']);

    $store = QuadbStore::restoreRawEntryDump($restoreDir, $oracle['beforeUpdate']['entries']);
    $beforeImportMatches = $oracle['beforeUpdate']['entries'] === $rawSnapshotHex($store->lmdbRawEntrySnapshot());
    $namedBefore = $store->get($binaryKey);

    $store->put($binaryKey, $detachedMergedValue);
    $authoritative = new SparseTree();
    $authoritative->change()
        ->put('wp_options:plain', 'plain')
        ->put($binaryKey, $detachedMergedValue)
        ->put('wp_posts:1', 'Published post')
        ->put('wp_postmeta:1:_thumbnail_id', '42')
        ->apply();
    $authoritativeRootMatches = $authoritative->rootHash() === $oracle['updatedRootHex'];
    $store->mergeProofBytes(
        $authoritative->exportProof(['wp_posts:1'])->encode(Proof::ENCODING_FULL_KEYS)
    );
    $gcText = $store->garbageCollectText();
    $afterGc = $rawSnapshotHex($store->lmdbRawEntrySnapshot());

    $reopened = QuadbStore::open($restoreDir);
    $namedAfterMerge = $reopened->get($binaryKey);
    $namedPostAfterMerge = $reopened->get('wp_posts:1');
    $reopened->checkout('private-proof');
    $privateDelegated = $reopened->get('wp_options:private');
    $reopened->checkout('master');
    $masterBinary = $reopened->get($binaryKey);

    echo json_encode([
        'scenario' => 'restore a mixed WordPress Quadrable raw LMDB backup, use the named delegated preview head, merge a post proof, and prune orphan detached proof nodes',
        'currentHead' => 'binary-proof',
        'beforeImportMatchesOracle' => $beforeImportMatches,
        'authoritativeRootMatches' => $authoritativeRootMatches,
        'roots' => [
            'updated' => $oracle['updatedRootHex'],
            'merged' => $oracle['mergedRootHex'],
        ],
        'gc' => [
            'text' => trim($gcText),
            'oracleGarbage' => $oracle['gc']['garbage'],
            'oracleTotal' => $oracle['gc']['total'],
            'afterGcMatchesOracle' => $afterGc === $oracle['afterGc']['entries'],
        ],
        'bucketCounts' => [
            'beforeUpdate' => $bucketCounts($oracle['beforeUpdate']['entries']),
            'afterGc' => $bucketCounts($afterGc),
        ],
        'reads' => [
            'namedBeforeHex' => bin2hex($namedBefore),
            'namedAfterMergeHex' => bin2hex($namedAfterMerge),
            'namedPostAfterMerge' => $namedPostAfterMerge,
            'privateDelegatedHex' => bin2hex($privateDelegated),
            'masterBinaryHex' => bin2hex($masterBinary),
        ],
        'expectedReads' => [
            'namedBeforeHex' => bin2hex($delegatedValue),
            'namedAfterMergeHex' => bin2hex($detachedMergedValue),
            'privateDelegatedHex' => bin2hex($privateDelegatedValue),
            'masterBinaryHex' => bin2hex($binaryValue),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($restoreDir);
}
