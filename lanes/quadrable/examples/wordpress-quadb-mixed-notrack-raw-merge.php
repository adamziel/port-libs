<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$restoreDir = sys_get_temp_dir() . '/quadrable-wp-mixed-notrack-raw-merge-' . bin2hex(random_bytes(6));

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
    $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-mixed-notrack-raw-restored-merge-oracle.json';
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
        throw new RuntimeException('malformed upstream mixed noTrack raw-restored mergeProof oracle fixture');
    }

    $values = $oracle['fixtureValues'];
    $binaryKey = $oracleBytes($values['binaryKeyHex']);
    $binaryValue = $oracleBytes($values['binaryValueHex']);
    $delegatedValue = $oracleBytes($values['delegatedValueHex']);
    $privateValue = $oracleBytes($values['privateValueHex']);
    $privatePostValue = $oracleBytes($values['privatePostValueHex']);
    $privateDelegatedValue = $oracleBytes($values['privateDelegatedValueHex']);
    $privateMergedValue = $oracleBytes($values['privateMergedValueHex']);
    $privateKeyHex = bin2hex('wp_options:private');

    $store = QuadbStore::restoreRawEntryDump($restoreDir, $oracle['beforeUpdate']['entries']);
    $beforeImportMatches = $oracle['beforeUpdate']['entries'] === $rawSnapshotHex($store->lmdbRawEntrySnapshot());
    $privateBefore = $store->get('wp_options:private');

    $store->put('wp_options:private', $privateMergedValue);
    $authoritative = new SparseTree();
    $authoritative->change()
        ->put('wp_options:private', $privateMergedValue)
        ->put('wp_posts:private', $privatePostValue)
        ->apply();
    $authoritativeRootMatches = $authoritative->rootHash() === $oracle['updatedRootHex'];
    $store->mergeProofBytes(
        $authoritative->exportProof(['wp_posts:private'])->encode()
    );
    $fullKeysRejected = false;
    try {
        $store->exportProofBytes(['wp_options:private'], Proof::ENCODING_FULL_KEYS);
    } catch (RuntimeException) {
        $fullKeysRejected = true;
    }
    $gcText = $store->garbageCollectText();
    $afterGc = $rawSnapshotHex($store->lmdbRawEntrySnapshot());
    $privateKeyStored = in_array(
        $privateKeyHex,
        array_column($afterGc['quadrable_key'], 'valueHex'),
        true
    );

    $reopened = QuadbStore::open($restoreDir);
    $privateAfterMerge = $reopened->get('wp_options:private');
    $privatePostAfterMerge = $reopened->get('wp_posts:private');
    $reopened->checkout('private-full');
    $privateFull = $reopened->get('wp_options:private');
    $reopened->checkout('binary-proof');
    $trackedDelegated = $reopened->get($binaryKey);
    $reopened->checkout('master');
    $masterBinary = $reopened->get($binaryKey);

    echo json_encode([
        'scenario' => 'restore a mixed WordPress Quadrable raw LMDB backup, use the noTrack private delegated preview head, merge a private post proof, and prune orphan proof nodes without adding private keys',
        'currentHead' => 'private-proof',
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
        'privacy' => [
            'keyBucketCountAfterGc' => count($afterGc['quadrable_key']),
            'privateKeyStored' => $privateKeyStored,
            'fullKeysRejected' => $fullKeysRejected,
        ],
        'bucketCounts' => [
            'beforeUpdate' => $bucketCounts($oracle['beforeUpdate']['entries']),
            'afterGc' => $bucketCounts($afterGc),
        ],
        'reads' => [
            'privateBeforeHex' => bin2hex($privateBefore),
            'privateAfterMergeHex' => bin2hex($privateAfterMerge),
            'privatePostAfterMergeHex' => bin2hex($privatePostAfterMerge),
            'privateFullHex' => bin2hex($privateFull),
            'trackedDelegatedHex' => bin2hex($trackedDelegated),
            'masterBinaryHex' => bin2hex($masterBinary),
        ],
        'expectedReads' => [
            'privateBeforeHex' => bin2hex($privateDelegatedValue),
            'privateAfterMergeHex' => bin2hex($privateMergedValue),
            'privatePostAfterMergeHex' => bin2hex($privatePostValue),
            'privateFullHex' => bin2hex($privateValue),
            'trackedDelegatedHex' => bin2hex($delegatedValue),
            'masterBinaryHex' => bin2hex($binaryValue),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($restoreDir);
}
