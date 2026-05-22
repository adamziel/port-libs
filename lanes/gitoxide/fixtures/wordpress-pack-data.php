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
        'body' => "Post title: Native PHP pack data\n\nThis blob stands in for a compacted wp_posts export.\n",
        'wordpressUse' => 'wp_posts export blob',
    ],
];

$pack = 'PACK' . pack('N2', 2, count($objects));
$indexEntries = [];
foreach ($objects as $index => $object) {
    $offset = strlen($pack);
    $entryBytes = $encodeEntryHeader($object['typeId'], strlen($object['body'])) . gzcompress($object['body']);
    $pack .= $entryBytes;
    $indexEntries[] = [
        'type' => $object['type'],
        'body' => $object['body'],
        'oid' => hash('sha1', $object['type'] . ' ' . strlen($object['body']) . "\0" . $object['body']),
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
