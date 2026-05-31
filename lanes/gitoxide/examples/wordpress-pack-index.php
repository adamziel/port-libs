<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackIndex;

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-index.php';
$index = PackIndex::fromBytes($fixture['indexBytes']);
$blob = $index->lookup('3b18e512dba79e4c8300dd08aeb37f8e728b8dad');
$large = $index->lookup('a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f');
$blobPrefix = $index->lookupPrefix(substr($fixture['objects'][1]['oid'], 0, 7));

return [
    'version' => $index->version(),
    'objects' => $index->count(),
    'packChecksum' => $index->packChecksum(),
    'indexChecksum' => $index->verifyChecksum(),
    'wordpressBlobOffset' => $blob?->packOffset,
    'wordpressBlobPrefixStatus' => $blobPrefix['status'],
    'wordpressBlobPrefixRange' => $blobPrefix['candidateRange'],
    'wordpressBlobShortestPrefix' => $index->disambiguatePrefix($fixture['objects'][1]['oid'], 4),
    'largeMediaOffset' => $large?->packOffset,
    'sortedOffsets' => $index->sortedOffsets(),
];
