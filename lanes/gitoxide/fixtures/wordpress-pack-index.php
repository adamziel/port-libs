<?php

declare(strict_types=1);

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

    $largeOffsets = [];
    foreach ($entries as $entry) {
        if ($entry['offset'] >= 0x80000000) {
            $largeIndex = count($largeOffsets);
            $largeOffsets[] = $entry['offset'];
            $bytes .= pack('N', 0x80000000 | $largeIndex);
        } else {
            $bytes .= pack('N', $entry['offset']);
        }
    }

    foreach ($largeOffsets as $offset) {
        $bytes .= pack('N2', intdiv($offset, 4294967296), $offset % 4294967296);
    }

    $bytes .= hex2bin($packChecksum);
    return $bytes . hex2bin(hash('sha1', $bytes));
};

$objects = [
    [
        'oid' => '134385f6d781b7e97062102c6a483440bfda2a03',
        'offset' => 12,
        'crc32' => 0x12345678,
        'wordpressUse' => 'deployment commit object',
    ],
    [
        'oid' => '3b18e512dba79e4c8300dd08aeb37f8e728b8dad',
        'offset' => 96,
        'crc32' => 0x90abcdef,
        'wordpressUse' => 'wp-content export blob',
    ],
    [
        'oid' => 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f',
        'offset' => 8589934597,
        'crc32' => 0x0badc0de,
        'wordpressUse' => 'large pack media object',
    ],
];
$packChecksum = '0f3ea84cd1bba10c2a03d736a460635082833e59';

return [
    'indexBytes' => $buildIndex($objects, $packChecksum),
    'objectHash' => 'sha1',
    'objects' => $objects,
    'packChecksum' => $packChecksum,
    'wordpressUse' => 'A PHP object database can use a pack index to locate compacted WordPress content objects in shared-hosting repositories without invoking git.',
];
