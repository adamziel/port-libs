<?php

declare(strict_types=1);

$packUInt64 = static function (int $value): string {
    return pack('N2', intdiv($value, 4294967296), $value % 4294967296);
};

$padToFour = static function (string $bytes): string {
    return $bytes . str_repeat("\0", (4 - (strlen($bytes) % 4)) % 4);
};

$objectId = static fn (string $type, string $body): string => hash('sha1', $type . ' ' . strlen($body) . "\0" . $body);

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
    foreach ($entries as $entry) {
        $oidBytes .= hex2bin($entry['oid']);
    }

    $largeOffsets = [];
    $offsetBytes = '';
    foreach ($entries as $entry) {
        $offsetBytes .= pack('N', $entry['packIndex']);
        if ($entry['offset'] > 0x7fffffff) {
            $largeIndex = count($largeOffsets);
            $largeOffsets[] = $entry['offset'];
            $offsetBytes .= pack('N', 0x80000000 | $largeIndex);
        } else {
            $offsetBytes .= pack('N', $entry['offset']);
        }
    }

    $largeOffsetBytes = '';
    foreach ($largeOffsets as $offset) {
        $largeOffsetBytes .= $packUInt64($offset);
    }

    $chunks = [
        'PNAM' => $padToFour($packNames),
        'OIDF' => $fanoutBytes,
        'OIDL' => $oidBytes,
        'OOFF' => $offsetBytes,
        'LOFF' => $largeOffsetBytes,
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

$indexNames = [
    'pack-0a-content-objects.idx',
    'pack-1b-media-objects.idx',
];

$objects = [
    [
        'role' => 'content',
        'type' => 'blob',
        'body' => "wp_posts export chunk for published block content\n",
        'packIndex' => 0,
        'offset' => 12,
        'wordpressUse' => 'content pack object',
    ],
    [
        'role' => 'template',
        'type' => 'blob',
        'body' => "theme.json and block-template snapshot for Playground\n",
        'packIndex' => 0,
        'offset' => 144,
        'wordpressUse' => 'theme/template pack object',
    ],
    [
        'role' => 'large-media',
        'type' => 'blob',
        'body' => "large media attachment metadata stored past the 32-bit pack offset boundary\n",
        'packIndex' => 1,
        'offset' => 8589934599,
        'wordpressUse' => 'large media pack object',
    ],
];

foreach ($objects as $index => $object) {
    $objects[$index]['oid'] = $objectId($object['type'], $object['body']);
}

[$multiIndexBytes, $checksum, $sortedObjects] = $buildMultiIndex($objects, $indexNames);
[$emptyMultiIndexBytes, $emptyChecksum] = $buildMultiIndex([], [$indexNames[0]]);
$objectsByRole = [];
foreach ($sortedObjects as $object) {
    $objectsByRole[$object['role']] = $object;
}

return [
    'multiIndexBytes' => $multiIndexBytes,
    'checksum' => $checksum,
    'emptyMultiIndexBytes' => $emptyMultiIndexBytes,
    'emptyChecksum' => $emptyChecksum,
    'indexNames' => $indexNames,
    'objects' => $sortedObjects,
    'objectsByRole' => $objectsByRole,
    'wordpressUse' => 'A WordPress object database can use one multi-pack-index to locate content, template, and large media pack offsets before reading the referenced pack data.',
];
