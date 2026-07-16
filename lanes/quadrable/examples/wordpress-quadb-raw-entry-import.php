<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceDir = sys_get_temp_dir() . '/quadrable-wp-raw-entry-source-' . bin2hex(random_bytes(6));
$restoreDir = sys_get_temp_dir() . '/quadrable-wp-raw-entry-restore-' . bin2hex(random_bytes(6));
$upstreamRestoreDir = sys_get_temp_dir() . '/quadrable-wp-raw-entry-upstream-' . bin2hex(random_bytes(6));
$noTrackRestoreDir = sys_get_temp_dir() . '/quadrable-wp-raw-entry-notrack-' . bin2hex(random_bytes(6));

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

    $oraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-dump-restore-oracle.json';
    $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($oracle)
        || !isset($oracle['fixtureValues'], $oracle['restored']['entries'])
        || !is_array($oracle['fixtureValues'])
        || !is_array($oracle['restored']['entries'])
    ) {
        throw new RuntimeException('malformed upstream LMDB dump/restore oracle fixture');
    }

    $upstream = QuadbStore::restoreRawEntryDump($upstreamRestoreDir, $oracle['restored']['entries']);
    $upstreamRawEntriesMatchAfterImport = $oracle['restored']['entries'] === $rawSnapshotHex($upstream->lmdbRawEntrySnapshot());
    $binaryKey = hex2bin($oracle['fixtureValues']['binaryKeyHex']);
    if (!is_string($binaryKey)) {
        throw new RuntimeException('malformed upstream binary key fixture');
    }
    $detachedDelegatedPost = $upstream->get($binaryKey);
    $upstream->checkout('binary-proof');
    $namedDelegatedPost = $upstream->get($binaryKey);
    $updatedDelegatedPost = "Raw-restored preview edit\0serialized";
    $rootBeforeDelegatedEdit = $upstream->status()['rootHash'];
    $upstream->put($binaryKey, $updatedDelegatedPost);
    $rootAfterDelegatedEdit = $upstream->status()['rootHash'];
    $delegatedProof = Proof::decode($upstream->exportProofBytes([$binaryKey], Proof::ENCODING_FULL_KEYS));
    $authenticatedUpdatedDelegated = SparseTree::importProof($delegatedProof, $rootAfterDelegatedEdit)->get($binaryKey);
    $authoritativeAfterEdit = new SparseTree();
    $authoritativeAfterEdit->change()
        ->put('wp_options:plain', 'plain')
        ->put($binaryKey, $updatedDelegatedPost)
        ->put('wp_posts:1', 'Published post')
        ->put('wp_postmeta:1:_thumbnail_id', '42')
        ->apply();
    $authoritativeRootMatches = $authoritativeAfterEdit->rootHash() === $rootAfterDelegatedEdit;
    $upstream->mergeProofBytes(
        $authoritativeAfterEdit->exportProof(['wp_options:plain'])->encode(Proof::ENCODING_FULL_KEYS)
    );
    $mergedPlainOption = $upstream->get('wp_options:plain');
    $upstream->checkout('private-proof');
    $privateDelegatedOption = $upstream->get('wp_options:private');

    $noTrackOraclePath = dirname(__DIR__) . '/fixtures/upstream-lmdb-notrack-raw-restored-merge-oracle.json';
    $noTrackOracle = json_decode((string) file_get_contents($noTrackOraclePath), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($noTrackOracle)
        || !isset($noTrackOracle['fixtureValues'], $noTrackOracle['beforeUpdate']['entries'], $noTrackOracle['afterGc']['entries'])
        || !is_array($noTrackOracle['fixtureValues'])
        || !is_array($noTrackOracle['beforeUpdate']['entries'])
        || !is_array($noTrackOracle['afterGc']['entries'])
    ) {
        throw new RuntimeException('malformed upstream noTrack raw-restored mergeProof oracle fixture');
    }
    $noTrackValues = $noTrackOracle['fixtureValues'];
    $noTrack = QuadbStore::restoreRawEntryDump($noTrackRestoreDir, $noTrackOracle['beforeUpdate']['entries'], false);
    $noTrackOriginalSiteUrl = $noTrack->get($noTrackValues['siteUrlKey']);
    $noTrack->put($noTrackValues['siteUrlKey'], $noTrackValues['updatedUrl']);
    $noTrackAuthoritative = new SparseTree();
    $noTrackAuthoritative->change()
        ->put($noTrackValues['siteUrlKey'], $noTrackValues['updatedUrl'])
        ->put($noTrackValues['homeKey'], $noTrackValues['originalUrl'])
        ->put($noTrackValues['postKey'], $noTrackValues['postValue'])
        ->apply();
    $noTrack->mergeProofBytes(
        $noTrackAuthoritative->exportProof([$noTrackValues['homeKey']])->encode()
    );
    $noTrackGc = $noTrack->garbageCollectText();
    $noTrackRawAfterGc = $rawSnapshotHex($noTrack->lmdbRawEntrySnapshot());

    echo json_encode([
        'scenario' => 'restore WordPress Quadrable stores from raw LMDB cursor entries only',
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
        'upstreamMixedProofRestore' => [
            'rawEntryDigest' => QuadbStore::portableRawEntryDigest($oracle['restored']['entries']),
            'rawEntriesMatchAfterImport' => $upstreamRawEntriesMatchAfterImport,
            'detachedDelegatedPostHex' => bin2hex($detachedDelegatedPost),
            'namedDelegatedPostHex' => bin2hex($namedDelegatedPost),
            'updatedDelegatedPostHex' => bin2hex($updatedDelegatedPost),
            'updatedProofAuthenticates' => $authenticatedUpdatedDelegated === $updatedDelegatedPost,
            'rootChangedAfterDelegatedEdit' => $rootAfterDelegatedEdit !== $rootBeforeDelegatedEdit,
            'mergeProofAfterRawRestoredEdit' => [
                'authoritativeRootMatches' => $authoritativeRootMatches,
                'mergedPlainOption' => $mergedPlainOption,
            ],
            'privateDelegatedOptionHex' => bin2hex($privateDelegatedOption),
        ],
        'upstreamNoTrackProofRestore' => [
            'head' => $noTrack->currentHeadName(),
            'originalSiteUrl' => $noTrackOriginalSiteUrl,
            'mergedHome' => $noTrack->get($noTrackValues['homeKey']),
            'keyBucketStayedEmpty' => $noTrackRawAfterGc['quadrable_key'] === [],
            'afterGcMatchesOracle' => $noTrackRawAfterGc === $noTrackOracle['afterGc']['entries'],
            'gc' => trim($noTrackGc),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $cleanup($sourceDir);
    $cleanup($restoreDir);
    $cleanup($upstreamRestoreDir);
    $cleanup($noTrackRestoreDir);
}
