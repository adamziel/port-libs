<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\ObjectDatabase;

$fixture = require __DIR__ . '/../fixtures/wordpress-object-database-multi-pack.php';
$gitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-midx-' . bin2hex(random_bytes(4)) . '/.git';
$packDir = $gitDir . '/objects/pack';
if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
    throw new RuntimeException("Unable to create pack example directory: {$packDir}");
}

$packUInt64 = static fn (int $value): string => pack('N2', intdiv($value, 4294967296), $value % 4294967296);
$padToFour = static fn (string $bytes): string => $bytes . str_repeat("\0", (4 - (strlen($bytes) % 4)) % 4);
$buildEmptyMultiPackIndex = static function (array $indexNames) use ($packUInt64, $padToFour): string {
    $packNames = '';
    foreach ($indexNames as $indexName) {
        $packNames .= $indexName . "\0";
    }

    $chunks = [
        'PNAM' => $padToFour($packNames),
        'OIDF' => str_repeat("\0", 256 * 4),
        'OIDL' => '',
        'OOFF' => '',
    ];
    $header = 'MIDX' . chr(1) . chr(1) . chr(count($chunks)) . "\0" . pack('N', count($indexNames));
    $chunkOffset = strlen($header) + (count($chunks) + 1) * 12;
    $table = '';
    $body = '';
    foreach ($chunks as $id => $chunk) {
        $table .= $id . $packUInt64($chunkOffset);
        $body .= $chunk;
        $chunkOffset += strlen($chunk);
    }
    $table .= "\0\0\0\0" . $packUInt64($chunkOffset);

    $bytes = $header . $table . $body;

    return $bytes . hex2bin(hash('sha1', $bytes));
};

foreach ($fixture['packs'] as $pack) {
    file_put_contents($packDir . '/' . $pack['packName'], $pack['packBytes']);
    file_put_contents($packDir . '/' . $pack['indexName'], $pack['indexBytes']);
}
file_put_contents($packDir . '/multi-pack-index', $fixture['multiIndexBytes']);

$writeFixtureToPackDirectory = static function (string $packDirectory) use ($fixture): void {
    if (!mkdir($packDirectory, 0777, true) && !is_dir($packDirectory)) {
        throw new RuntimeException("Unable to create pack example directory: {$packDirectory}");
    }
    foreach ($fixture['packs'] as $pack) {
        file_put_contents($packDirectory . '/' . $pack['packName'], $pack['packBytes']);
        file_put_contents($packDirectory . '/' . $pack['indexName'], $pack['indexBytes']);
    }
    file_put_contents($packDirectory . '/multi-pack-index', $fixture['multiIndexBytes']);
};

$alternateRoot = sys_get_temp_dir() . '/port-libs-wordpress-odb-midx-alt-' . bin2hex(random_bytes(4));
$alternateGitDir = $alternateRoot . '/site/.git';
$alternateObjectsDirectory = $alternateRoot . '/cache/objects';
$writeFixtureToPackDirectory($alternateGitDir . '/objects/pack');
$writeFixtureToPackDirectory($alternateObjectsDirectory . '/pack');
$alternateObjectsInfo = $alternateGitDir . '/objects/info';
if (!mkdir($alternateObjectsInfo, 0777, true) && !is_dir($alternateObjectsInfo)) {
    throw new RuntimeException("Unable to create alternate objects info directory: {$alternateObjectsInfo}");
}
file_put_contents($alternateObjectsInfo . '/alternates', $alternateObjectsDirectory . "\n");
$alternateDatabase = new ObjectDatabase($alternateGitDir);
$alternateDuplicatePrefix = $alternateDatabase->lookupPrefix(substr($fixture['objectsByRole']['content']['oid'], 0, 8), true);
$alternateLooseCandidateOid = LooseObjectStore::fromObjectsDirectory($alternateObjectsDirectory)
    ->write(new GitObject('blob', 'midx-prefix-candidate-128814'));
$alternateAmbiguousPrefix = $alternateDatabase->lookupPrefix(substr($fixture['objectsByRole']['content']['oid'], 0, 4), true);

$emptyMidxGitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-empty-midx-' . bin2hex(random_bytes(4)) . '/.git';
$emptyMidxPackDir = $emptyMidxGitDir . '/objects/pack';
$writeFixtureToPackDirectory($emptyMidxPackDir);
file_put_contents($emptyMidxPackDir . '/multi-pack-index', $buildEmptyMultiPackIndex([
    $fixture['packs'][0]['indexName'],
]));
$emptyMidxDatabase = new ObjectDatabase($emptyMidxGitDir);
$emptyMidxContentPrefix = $emptyMidxDatabase->lookupPrefix(substr($fixture['objectsByRole']['content']['oid'], 0, 8), true);
$emptyMidxMediaPrefix = $emptyMidxDatabase->lookupPrefix(substr($fixture['objectsByRole']['media']['oid'], 0, 8), true);

$database = new ObjectDatabase($gitDir);
$content = $database->read($fixture['objectsByRole']['content']['oid']);
$media = $database->read($fixture['objectsByRole']['media']['oid']);
$shared = $database->read($fixture['objectsByRole']['shared']['oid']);
$duplicateLooseContentOid = (new LooseObjectStore($gitDir))->write(new GitObject('blob', $fixture['objectsByRole']['content']['body']));
$contentCaseDuplicatePath = $gitDir . '/objects/' . substr($fixture['objectsByRole']['content']['oid'], 0, 2)
    . '/' . strtoupper(substr($fixture['objectsByRole']['content']['oid'], 2));
file_put_contents($contentCaseDuplicatePath, 'case-variant loose prefix path candidate');
$contentCaseDuplicatePrefix = $database->lookupPrefix($fixture['objectsByRole']['content']['oid']);
$contentCaseDuplicatePrefixWithCandidates = $database->lookupPrefix($fixture['objectsByRole']['content']['oid'], true);
$contentDuplicatePrefix = $database->lookupPrefix(substr($fixture['objectsByRole']['content']['oid'], 0, 8), true);
$loosePrefixCandidateOid = (new LooseObjectStore($gitDir))->write(new GitObject('blob', 'midx-prefix-candidate-128814'));
$contentPrefixCandidates = $database->lookupPrefix(substr($fixture['objectsByRole']['content']['oid'], 0, 4), true);
$contentShortestPrefix = $database->disambiguatePrefix($fixture['objectsByRole']['content']['oid'], 4);
$contentFullPrefix = $database->lookupPrefix($fixture['objectsByRole']['content']['oid'], true);
$contentDirectoryCandidateOid = substr($fixture['objectsByRole']['content']['oid'], 0, 4)
    . ($fixture['objectsByRole']['content']['oid'][4] === '0' ? '1' : '0')
    . str_repeat('f', 35);
$contentDirectoryCandidatePath = $gitDir . '/objects/' . substr($contentDirectoryCandidateOid, 0, 2) . '/' . substr($contentDirectoryCandidateOid, 2);
if (!is_dir($contentDirectoryCandidatePath) && !mkdir($contentDirectoryCandidatePath, 0777, true) && !is_dir($contentDirectoryCandidatePath)) {
    throw new RuntimeException("Unable to create loose directory prefix candidate: {$contentDirectoryCandidatePath}");
}
$contentDirectoryPrefixCandidates = $database->lookupPrefix(substr($fixture['objectsByRole']['content']['oid'], 0, 4), true);

// Prefix disambiguation only needs the loose-object inventory, not object body reads.
$syntheticSharedPrefix = str_repeat('c', 39);
foreach ([$syntheticSharedPrefix . '1', $syntheticSharedPrefix . '2'] as $syntheticOid) {
    $path = $gitDir . '/objects/' . substr($syntheticOid, 0, 2) . '/' . substr($syntheticOid, 2);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
        throw new RuntimeException("Unable to create synthetic loose object path: " . dirname($path));
    }
    file_put_contents($path, 'prefix-index-only-candidate');
}
$syntheticMissingCandidate = $syntheticSharedPrefix . '3';
$absentMediaCandidate = substr($fixture['objectsByRole']['media']['oid'], 0, 8) . str_repeat('f', 32);
$rejectsInvalidArgument = static function (callable $operation): bool {
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
};
$contentNewlinePrefixRejected = $rejectsInvalidArgument(
    static fn (): array => $database->lookupPrefix(substr($fixture['objectsByRole']['content']['oid'], 0, 8) . "\n")
);
$contentNewlineObjectIdRejected = $rejectsInvalidArgument(
    static fn (): bool => $database->contains($fixture['objectsByRole']['content']['oid'] . "\n")
);

return [
    'packedObjects' => $database->packedObjectCount(),
    'rawPackIndexObjects' => array_sum(array_map(static fn (array $pack): int => count($pack['objects']), $fixture['packs'])),
    'contentOid' => $content->oid(),
    'mediaOid' => $media->oid(),
    'sharedOid' => $shared->oid(),
    'sharedObjectDeduplicatedByMidx' => $database->packedObjectCount() < array_sum(array_map(static fn (array $pack): int => count($pack['objects']), $fixture['packs'])),
    'multiPackIndexOffsetsVerified' => true,
    'mediaPrefixStatus' => $database->lookupPrefix(substr($fixture['objectsByRole']['media']['oid'], 0, 8))['status'],
    'mediaShortestPrefix' => $database->disambiguatePrefix($fixture['objectsByRole']['media']['oid'], 4),
    'duplicateLooseContentOid' => $duplicateLooseContentOid,
    'contentCaseDuplicatePrefixStatus' => $contentCaseDuplicatePrefix['status'],
    'contentCaseDuplicatePrefixMatches' => $contentCaseDuplicatePrefix['matches'] ?? [],
    'contentCaseDuplicateCandidateStatus' => $contentCaseDuplicatePrefixWithCandidates['status'],
    'contentCaseDuplicateCandidates' => $contentCaseDuplicatePrefixWithCandidates['candidates'],
    'contentDuplicatePrefixStatus' => $contentDuplicatePrefix['status'],
    'contentDuplicatePrefixCandidates' => $contentDuplicatePrefix['candidates'],
    'loosePrefixCandidateOid' => $loosePrefixCandidateOid,
    'contentPrefixCandidateStatus' => $contentPrefixCandidates['status'],
    'contentPrefixCandidates' => $contentPrefixCandidates['candidates'],
    'contentShortestPrefixAfterLooseCandidate' => $contentShortestPrefix,
    'contentFullPrefixStatus' => $contentFullPrefix['status'],
    'contentFullPrefixCandidates' => $contentFullPrefix['candidates'],
    'contentNewlinePrefixRejected' => $contentNewlinePrefixRejected,
    'contentNewlineObjectIdRejected' => $contentNewlineObjectIdRejected,
    'contentDirectoryCandidateOid' => $contentDirectoryCandidateOid,
    'contentDirectoryCandidateExists' => $database->contains($contentDirectoryCandidateOid),
    'contentDirectoryPrefixStatus' => $contentDirectoryPrefixCandidates['status'],
    'contentDirectoryPrefixCandidates' => $contentDirectoryPrefixCandidates['candidates'],
    'missingAmbiguousFullPrefix' => $database->disambiguatePrefix($syntheticMissingCandidate, 4),
    'missingAmbiguousFullPrefixExists' => $database->contains($syntheticMissingCandidate),
    'absentMediaCandidateShortestPrefix' => $database->disambiguatePrefix($absentMediaCandidate, 8),
    'absentMediaCandidateFullPrefixExists' => $database->contains($absentMediaCandidate),
    'alternateDuplicatePrefixStatus' => $alternateDuplicatePrefix['status'],
    'alternateDuplicatePrefixCandidates' => $alternateDuplicatePrefix['candidates'],
    'alternateLooseCandidateOid' => $alternateLooseCandidateOid,
    'alternateAmbiguousPrefixStatus' => $alternateAmbiguousPrefix['status'],
    'alternateAmbiguousPrefixCandidates' => $alternateAmbiguousPrefix['candidates'],
    'emptyMidxPackedObjects' => $emptyMidxDatabase->packedObjectCount(),
    'emptyMidxContentPrefixStatus' => $emptyMidxContentPrefix['status'],
    'emptyMidxContentPrefixCandidates' => $emptyMidxContentPrefix['candidates'],
    'emptyMidxContentPresent' => $emptyMidxDatabase->contains($fixture['objectsByRole']['content']['oid']),
    'emptyMidxMediaPrefixStatus' => $emptyMidxMediaPrefix['status'],
    'emptyMidxMediaPrefixCandidates' => $emptyMidxMediaPrefix['candidates'],
    'emptyMidxMediaPresent' => $emptyMidxDatabase->contains($fixture['objectsByRole']['media']['oid']),
    'packOffsetOrder' => $database->objectIds(ObjectDatabase::ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL),
];
