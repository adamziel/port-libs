<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;

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

$packUInt64 = static fn (int $value): string => pack('N2', intdiv($value, 4294967296), $value % 4294967296);
$padToFour = static fn (string $bytes): string => $bytes . str_repeat("\0", (4 - (strlen($bytes) % 4)) % 4);

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
        $oid = hex2bin($entry['oid']);
        if ($oid === false || strlen($oid) !== 32) {
            throw new RuntimeException('Invalid SHA-256 object id in fixture pack index');
        }
        $bytes .= $oid;
    }
    foreach ($entries as $entry) {
        $bytes .= pack('N', $entry['crc32']);
    }
    foreach ($entries as $entry) {
        $bytes .= pack('N', $entry['offset']);
    }
    $bytes .= hex2bin($packChecksum);

    return $bytes . hex2bin(hash('sha256', $bytes));
};

$buildPack = static function (array $objects) use ($encodeEntryHeader, $buildIndex): array {
    $pack = 'PACK' . pack('N2', 2, count($objects));
    $entries = [];
    foreach ($objects as $object) {
        $offset = strlen($pack);
        $entryBytes = $encodeEntryHeader($object['typeId'], strlen($object['body'])) . gzcompress($object['body']);
        $pack .= $entryBytes;
        $gitObject = new GitObject($object['type'], $object['body']);
        $entries[] = [
            'role' => $object['role'],
            'type' => $object['type'],
            'body' => $object['body'],
            'oid' => $gitObject->oid('sha256'),
            'offset' => $offset,
            'crc32' => hexdec(hash('crc32b', $entryBytes)),
            'wordpressUse' => $object['wordpressUse'],
        ];
    }
    $packChecksum = hash('sha256', $pack);
    $pack .= hex2bin($packChecksum);

    return [
        'packBytes' => $pack,
        'indexBytes' => $buildIndex($entries, $packChecksum),
        'objects' => $entries,
        'packChecksum' => $packChecksum,
    ];
};

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
        $oid = hex2bin($entry['oid']);
        if ($oid === false || strlen($oid) !== 32) {
            throw new RuntimeException('Invalid SHA-256 object id in fixture multi-pack-index');
        }
        $oidBytes .= $oid;
        $offsetBytes .= pack('N2', $entry['packIndex'], $entry['offset']);
    }

    $chunks = [
        'PNAM' => $padToFour($packNames),
        'OIDF' => $fanoutBytes,
        'OIDL' => $oidBytes,
        'OOFF' => $offsetBytes,
    ];
    $header = 'MIDX' . chr(1) . chr(2) . chr(count($chunks)) . "\0" . pack('N', count($indexNames));
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
    $checksum = hash('sha256', $bytes);

    return [$bytes . hex2bin($checksum), $checksum, $entries];
};

$contentPack = $buildPack([
    [
        'role' => 'content',
        'type' => 'blob',
        'typeId' => 3,
        'body' => "wp_posts SHA-256 content object\n",
        'wordpressUse' => 'content object selected through SHA-256 MIDX',
    ],
]);
$mediaPack = $buildPack([
    [
        'role' => 'media',
        'type' => 'blob',
        'typeId' => 3,
        'body' => "SHA-256 media attachment object\n",
        'wordpressUse' => 'media object selected through SHA-256 MIDX',
    ],
]);

$indexNames = [
    'pack-sha256-content.idx',
    'pack-sha256-media.idx',
];
$contentObject = $contentPack['objects'][0] + ['packIndex' => 0];
$mediaObject = $mediaPack['objects'][0] + ['packIndex' => 1];
[$multiIndexBytes, $multiIndexChecksum, $multiIndexObjects] = $buildMultiIndex([$contentObject, $mediaObject], $indexNames);

$objectsByRole = [];
foreach ($multiIndexObjects as $object) {
    $objectsByRole[$object['role']] = $object;
}

return [
    'objectHash' => 'sha256',
    'packs' => [
        [
            'indexName' => $indexNames[0],
            'packName' => 'pack-sha256-content.pack',
            'packBytes' => $contentPack['packBytes'],
            'indexBytes' => $contentPack['indexBytes'],
            'objects' => $contentPack['objects'],
            'packChecksum' => $contentPack['packChecksum'],
        ],
        [
            'indexName' => $indexNames[1],
            'packName' => 'pack-sha256-media.pack',
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
    'wordpressUse' => 'A WordPress object database can resolve SHA-256 pack indexes through a matching SHA-256 multi-pack-index for Git repositories initialized with objectFormat=sha256.',
];
