<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackIndex;

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-index.php';
$index = PackIndex::fromBytes($fixture['indexBytes'], $fixture['objectHash']);
$blob = $index->lookup('3b18e512dba79e4c8300dd08aeb37f8e728b8dad');
$large = $index->lookup('a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f');
$blobPrefix = $index->lookupPrefix(substr($fixture['objects'][1]['oid'], 0, 7));
$largeOffsetThreshold = 0x7fffffff;
$generatedPrefixRanges = [];
foreach ($fixture['objects'] as $entryIndex => $object) {
    $hexLength = 7 + $entryIndex;
    $prefix = substr($object['oid'], 0, $hexLength);
    $lookup = $index->lookupPrefix(strtoupper($prefix));
    $generatedPrefixRanges[] = [
        'oid' => $object['oid'],
        'prefix' => $prefix,
        'status' => $lookup['status'],
        'candidateRange' => $lookup['candidateRange'],
    ];
}

return [
    'version' => $index->version(),
    'objectHash' => $index->objectHash(),
    'hashBytes' => $index->hashBytes(),
    'objects' => $index->count(),
    'packChecksum' => $index->packChecksum(),
    'indexChecksum' => $index->verifyChecksum(),
    'wordpressBlobOffset' => $blob?->packOffset,
    'wordpressBlobPrefixStatus' => $blobPrefix['status'],
    'wordpressBlobPrefixRange' => $blobPrefix['candidateRange'],
    'wordpressBlobShortestPrefix' => $index->disambiguatePrefix($fixture['objects'][1]['oid'], 4),
    'wordpressBlobFullPrefixFromUppercase' => $index->disambiguatePrefix(strtoupper($fixture['objects'][1]['oid']), 40),
    'generatedPrefixRanges' => $generatedPrefixRanges,
    'largeOffsetThreshold' => $largeOffsetThreshold,
    'firstLargeOffset' => $largeOffsetThreshold + 1,
    'largeMediaOffset' => $large?->packOffset,
    'largeMediaUses64BitOffsetTable' => $large !== null && $large->packOffset > $largeOffsetThreshold,
    'sortedOffsets' => $index->sortedOffsets(),
];
