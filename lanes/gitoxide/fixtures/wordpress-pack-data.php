<?php

declare(strict_types=1);

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

$encodeOfsDeltaDistance = static function (int $distance): string {
    $bytes = [$distance & 0x7f];
    $distance >>= 7;
    while ($distance !== 0) {
        $distance--;
        array_unshift($bytes, 0x80 | ($distance & 0x7f));
        $distance >>= 7;
    }

    return implode('', array_map(static fn (int $byte): string => chr($byte), $bytes));
};

$encodeDeltaSize = static function (int $size): string {
    $bytes = '';
    do {
        $byte = $size & 0x7f;
        $size >>= 7;
        if ($size !== 0) {
            $byte |= 0x80;
        }
        $bytes .= chr($byte);
    } while ($size !== 0);

    return $bytes;
};

$copyThenInsertDelta = static function (string $base, string $insert) use ($encodeDeltaSize): string {
    if (strlen($base) > 255 || strlen($insert) > 127) {
        throw new RuntimeException('WordPress pack fixture helper only encodes compact copy/insert commands');
    }

    return $encodeDeltaSize(strlen($base))
        . $encodeDeltaSize(strlen($base) + strlen($insert))
        . chr(0x80 | 0x10)
        . chr(strlen($base))
        . chr(strlen($insert))
        . $insert;
};

$objectId = static fn (string $type, string $body): string => hash('sha1', $type . ' ' . strlen($body) . "\0" . $body);

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

$postBlob = "Post title: Native PHP pack data\n\nThis blob stands in for a compacted wp_posts export.\n";
$deltaSuffix = "\nDelta: reconstructed packed edit for a WordPress importer.\n";

$objects = [
    [
        'type' => 'commit',
        'typeId' => 1,
        'body' => "tree e90926b07092bccb7bf7da445fae6ffdfacf3eae\n"
            . "author WordPress <wordpress@example.test> 1700000000 +0000\n"
            . "committer WordPress <wordpress@example.test> 1700000000 +0000\n\n"
            . "Import WordPress content\n",
        'wordpressUse' => 'deployment commit object',
    ],
    [
        'type' => 'blob',
        'typeId' => 3,
        'body' => $postBlob,
        'wordpressUse' => 'wp_posts export blob',
    ],
    [
        'type' => 'ofs-delta',
        'typeId' => 6,
        'body' => $copyThenInsertDelta($postBlob, $deltaSuffix),
        'baseEntry' => 1,
        'finalType' => 'blob',
        'finalBody' => $postBlob . $deltaSuffix,
        'wordpressUse' => 'wp_posts export blob reconstructed from OFS_DELTA',
    ],
];

$pack = 'PACK' . pack('N2', 2, count($objects));
$indexEntries = [];
foreach ($objects as $index => $object) {
    $offset = strlen($pack);
    $entryPrefix = '';
    if ($object['typeId'] === 6) {
        $base = $indexEntries[$object['baseEntry']] ?? null;
        if ($base === null) {
            throw new RuntimeException('OFS_DELTA WordPress fixture is missing its base entry');
        }
        $entryPrefix = $encodeOfsDeltaDistance($offset - $base['offset']);
    } elseif ($object['typeId'] === 7) {
        $base = $indexEntries[$object['baseEntry']] ?? null;
        if ($base === null) {
            throw new RuntimeException('REF_DELTA WordPress fixture is missing its base entry');
        }
        $entryPrefix = hex2bin($base['oid']);
    }

    $entryBytes = $encodeEntryHeader($object['typeId'], strlen($object['body'])) . $entryPrefix . gzcompress($object['body']);
    $pack .= $entryBytes;
    $indexType = $object['finalType'] ?? $object['type'];
    $indexBody = $object['finalBody'] ?? $object['body'];
    $indexEntries[] = [
        'type' => $indexType,
        'body' => $indexBody,
        'oid' => $objectId($indexType, $indexBody),
        'offset' => $offset,
        'crc32' => hexdec(hash('crc32b', $entryBytes)),
        'wordpressUse' => $object['wordpressUse'],
    ];
}
$packChecksum = hash('sha1', $pack);
$pack .= hex2bin($packChecksum);

return [
    'packBytes' => $pack,
    'indexBytes' => $buildIndex($indexEntries, $packChecksum),
    'objects' => $indexEntries,
    'packChecksum' => $packChecksum,
    'wordpressUse' => 'A PHP object database can use a pack index plus pack data decoder to read compacted WordPress content objects without invoking git.',
];
