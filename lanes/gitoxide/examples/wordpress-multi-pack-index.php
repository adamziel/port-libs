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
    'templatePrefixStatus' => $templatePrefix['status'],
    'templatePrefixRange' => $templatePrefix['candidateRange'],
    'templateShortestPrefix' => $index->disambiguatePrefix($templateObject['oid'], 4),
];
