<?php

declare(strict_types=1);

$objectId = static fn (string $type, string $body): string => hash('sha1', $type . ' ' . strlen($body) . "\0" . $body);

$encodeEntryHeader = static function (int $typeId, int $size): string {
    $out = '';
    $first = ($typeId << 4) | ($size & 0x0f);
    $size >>= 4;
    while ($size !== 0) {
        $out .= chr($first | 0x80);
        $first = $size & 0x7f;
        $size >>= 7;
    }
    $out .= chr($first);

    return $out;
};

$buildIndex = static function (array $entries, string $packChecksum): string {
    usort($entries, static fn (array $a, array $b): int => strcmp($a['oid'], $b['oid']));

    $fanout = array_fill(0, 256, 0);
    foreach ($entries as $entry) {
        $fanout[hexdec(substr($entry['oid'], 0, 2))]++;
    }
    $running = 0;
    foreach ($fanout as $index => $count) {
        $running += $count;
        $fanout[$index] = $running;
    }

    $bytes = "\xfftOc" . pack('N', 2);
    foreach ($fanout as $count) {
        $bytes .= pack('N', $count);
    }
    foreach ($entries as $entry) {
        $bytes .= hex2bin($entry['oid']);
    }
    foreach ($entries as $entry) {
        $bytes .= pack('N', $entry['crc32']);
    }
    foreach ($entries as $entry) {
        $bytes .= pack('N', $entry['offset']);
    }
    $bytes .= hex2bin($packChecksum);

    return $bytes . hex2bin(hash('sha1', $bytes));
};

$buildPack = static function (array $objects) use ($encodeEntryHeader, $objectId, $buildIndex): array {
    $pack = 'PACK' . pack('N2', 2, count($objects));
    $entries = [];
    foreach ($objects as $object) {
        $offset = strlen($pack);
        $entryBytes = $encodeEntryHeader($object['typeId'], strlen($object['body'])) . gzcompress($object['body']);
        $pack .= $entryBytes;
        $entries[] = [
            'role' => $object['role'],
            'type' => $object['type'],
            'body' => $object['body'],
            'oid' => $objectId($object['type'], $object['body']),
            'offset' => $offset,
            'crc32' => hexdec(hash('crc32b', $entryBytes)),
            'wordpressUse' => $object['wordpressUse'],
        ];
    }
    $packChecksum = hash('sha1', $pack);
    $pack .= hex2bin($packChecksum);

    return [
        'packBytes' => $pack,
        'indexBytes' => $buildIndex($entries, $packChecksum),
        'objects' => $entries,
        'packChecksum' => $packChecksum,
    ];
};

$packUInt64 = static fn (int $value): string => pack('N2', intdiv($value, 4294967296), $value % 4294967296);
$padToFour = static fn (string $bytes): string => $bytes . str_repeat("\0", (4 - (strlen($bytes) % 4)) % 4);

$buildMultiIndex = static function (array $entries, array $indexNames) use ($packUInt64, $padToFour): array {
    usort($entries, static fn (array $a, array $b): int => strcmp($a['oid'], $b['oid']));

    $fanout = array_fill(0, 256, 0);
    foreach ($entries as $entry) {
        $fanout[hexdec(substr($entry['oid'], 0, 2))]++;
    }
    $running = 0;
    foreach ($fanout as $index => $count) {
        $running += $count;
        $fanout[$index] = $running;
    }

    $packNames = '';
    foreach ($indexNames as $name) {
        $packNames .= $name . "\0";
    }

    $fanoutBytes = '';
    foreach ($fanout as $count) {
        $fanoutBytes .= pack('N', $count);
    }

    $oidBytes = '';
    $offsetBytes = '';
    foreach ($entries as $entry) {
        $oidBytes .= hex2bin($entry['oid']);
        $offsetBytes .= pack('N2', $entry['packIndex'], $entry['offset']);
    }

    $chunks = [
        'PNAM' => $padToFour($packNames),
        'OIDF' => $fanoutBytes,
        'OIDL' => $oidBytes,
        'OOFF' => $offsetBytes,
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
    $checksum = hash('sha1', $bytes);

    return [$bytes . hex2bin($checksum), $checksum, $entries];
};

$sharedBody = "Shared plugin package object duplicated across packs\n";

$contentPack = $buildPack([
    [
        'role' => 'content',
        'type' => 'blob',
        'typeId' => 3,
        'body' => "wp_posts export chunk stored in the content pack\n",
        'wordpressUse' => 'content object selected through MIDX',
    ],
    [
        'role' => 'shared-copy',
        'type' => 'blob',
        'typeId' => 3,
        'body' => $sharedBody,
        'wordpressUse' => 'duplicate package object in the content pack',
    ],
]);

$mediaPack = $buildPack([
    [
        'role' => 'shared',
        'type' => 'blob',
        'typeId' => 3,
        'body' => $sharedBody,
        'wordpressUse' => 'duplicate package object preferred by MIDX',
    ],
    [
        'role' => 'media',
        'type' => 'blob',
        'typeId' => 3,
        'body' => "Large media attachment metadata stored in the media pack\n",
        'wordpressUse' => 'media object selected through MIDX',
    ],
]);

$indexNames = [
    'pack-0a-content.idx',
    'pack-1b-media.idx',
];

$contentObject = $contentPack['objects'][0] + ['packIndex' => 0];
$sharedObject = $mediaPack['objects'][0] + ['packIndex' => 1];
$mediaObject = $mediaPack['objects'][1] + ['packIndex' => 1];
[$multiIndexBytes, $multiIndexChecksum, $multiIndexObjects] = $buildMultiIndex(
    [$contentObject, $sharedObject, $mediaObject],
    $indexNames
);

$objectsByRole = [];
foreach ($multiIndexObjects as $object) {
    $objectsByRole[$object['role']] = $object;
}

return [
    'packs' => [
        [
            'indexName' => $indexNames[0],
            'packName' => 'pack-0a-content.pack',
            'packBytes' => $contentPack['packBytes'],
            'indexBytes' => $contentPack['indexBytes'],
            'objects' => $contentPack['objects'],
            'packChecksum' => $contentPack['packChecksum'],
        ],
        [
            'indexName' => $indexNames[1],
            'packName' => 'pack-1b-media.pack',
            'packBytes' => $mediaPack['packBytes'],
            'indexBytes' => $mediaPack['indexBytes'],
            'objects' => $mediaPack['objects'],
            'packChecksum' => $mediaPack['packChecksum'],
        ],
    ],
    'multiIndexBytes' => $multiIndexBytes,
    'multiIndexChecksum' => $multiIndexChecksum,
    'multiIndexObjects' => $multiIndexObjects,
    'objectsByRole' => $objectsByRole,
    'wordpressUse' => 'A WordPress object database can use a multi-pack-index to de-duplicate objects repeated across content and media packs and select the pack that contains the object offset.',
];
