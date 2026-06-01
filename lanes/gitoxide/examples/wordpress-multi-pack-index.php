<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\MultiPackIndex;

$fixture = require __DIR__ . '/../fixtures/wordpress-multi-pack-index.php';
$allocationCapBytes = 64 * 1024 * 1024;
$index = MultiPackIndex::fromBytes($fixture['multiIndexBytes'], $allocationCapBytes);
$contentObject = $fixture['objectsByRole']['content'];
$templateObject = $fixture['objectsByRole']['template'];
$mediaObject = $fixture['objectsByRole']['large-media'];
$content = $index->lookup($contentObject['oid']);
$media = $index->lookup($mediaObject['oid']);
$templatePrefix = $index->lookupPrefix(substr($templateObject['oid'], 0, 8));
$generatedPrefixRanges = [];
foreach ($index->entries() as $entryIndex => $entry) {
    $hexLength = 5 + $entryIndex;
    $prefix = substr($entry->oid, 0, $hexLength);
    $lookup = $index->lookupPrefix(strtoupper($prefix));
    $generatedPrefixRanges[] = [
        'oid' => $entry->oid,
        'prefix' => $prefix,
        'status' => $lookup['status'],
        'candidateRange' => $lookup['candidateRange'],
    ];
}
$emptyIndex = MultiPackIndex::fromBytes($fixture['emptyMultiIndexBytes'], $allocationCapBytes);
$emptyPrefix = $emptyIndex->lookupPrefix('0000');
$emptyIntegrityStatus = 'accepted';
try {
    $emptyIndex->verifyIntegrityFast();
} catch (RuntimeException) {
    $emptyIntegrityStatus = 'empty-rejected';
}
$largeOffsetThreshold = 0x7fffffff;

return [
    'version' => $index->version(),
    'objectHash' => $index->objectHash(),
    'packCount' => $index->packCount(),
    'objects' => $index->count(),
    'allocationCapBytes' => $allocationCapBytes,
    'checksum' => $index->verifyIntegrityFast(),
    'packNames' => $index->packNames(),
    'contentPack' => $content === null ? null : $index->packNames()[$content->packIndex],
    'contentOffset' => $content?->packOffset,
    'largeMediaPack' => $media === null ? null : $index->packNames()[$media->packIndex],
    'largeMediaOffset' => $media?->packOffset,
    'largeOffsetThreshold' => $largeOffsetThreshold,
    'firstLargeOffset' => $largeOffsetThreshold + 1,
    'largeMediaUsesLargeOffsetChunk' => $media !== null && $media->packOffset > $largeOffsetThreshold,
    'templatePrefixStatus' => $templatePrefix['status'],
    'templatePrefixRange' => $templatePrefix['candidateRange'],
    'templateShortestPrefix' => $index->disambiguatePrefix($templateObject['oid'], 4),
    'templateFullPrefixFromUppercase' => $index->disambiguatePrefix(strtoupper($templateObject['oid']), 40),
    'generatedPrefixRanges' => $generatedPrefixRanges,
    'emptyObjects' => $emptyIndex->count(),
    'emptyChecksum' => $emptyIndex->verifyChecksum(),
    'emptyPrefixStatus' => $emptyPrefix['status'],
    'emptyPrefixRange' => $emptyPrefix['candidateRange'],
    'emptyIntegrityStatus' => $emptyIntegrityStatus,
];
