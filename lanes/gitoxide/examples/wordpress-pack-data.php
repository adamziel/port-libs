<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;
use PortLibs\Gitoxide\GitObject;

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-data.php';
$pack = PackData::fromBytes($fixture['packBytes']);
$index = PackIndex::fromBytes($fixture['indexBytes']);
$commit = $pack->readObject($index, $fixture['objects'][0]['oid']);
$blob = $pack->readObject($index, $fixture['objects'][1]['oid']);
$deltaBlob = $pack->readObject($index, $fixture['objects'][2]['oid']);
$deltaHeader = $pack->readObjectHeader($index, $fixture['objects'][2]['oid']);
$traversal = $pack->traverseObjectsWithIndex($index);

$strictDeclaredSizeGuard = false;
try {
    $malformedBlobPack = 'PACK' . pack('N2', 2, 1)
        . chr((3 << 4) | 1)
        . gzcompress('AB')
        . str_repeat("\0", 20);
    PackData::fromBytes($malformedBlobPack)->entryAtOffset(12);
} catch (RuntimeException) {
    $strictDeclaredSizeGuard = true;
}

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
$encodeDeltaSize = static function (int $size): string {
    $out = '';
    do {
        $byte = $size & 0x7f;
        $size >>= 7;
        if ($size !== 0) {
            $byte |= 0x80;
        }
        $out .= chr($byte);
    } while ($size !== 0);

    return $out;
};
$buildSingleEntryIndex = static function (string $oid, int $offset, string $entryBytes, string $packChecksum): string {
    $fanout = array_fill(0, 256, 0);
    $fanout[hexdec(substr($oid, 0, 2))] = 1;
    for ($i = 1; $i < 256; $i++) {
        $fanout[$i] += $fanout[$i - 1];
    }

    $bytes = "\xfftOc" . pack('N', 2);
    foreach ($fanout as $count) {
        $bytes .= pack('N', $count);
    }
    $bytes .= hex2bin($oid) . pack('N', hexdec(hash('crc32b', $entryBytes))) . pack('N', $offset) . hex2bin($packChecksum);

    return $bytes . hex2bin(hash('sha1', $bytes));
};
$buildRawPack = static function (string $entryBytes): string {
    $prefix = 'PACK' . pack('N2', 2, 1) . $entryBytes;

    return $prefix . hex2bin(hash('sha1', $prefix));
};

$oversizedDeltaHeaderGuard = false;
try {
    $base = new GitObject('blob', 'A');
    $target = new GitObject('blob', 'B');
    $oversizedResultDelta = chr(1) . str_repeat(chr(0x80), 9) . chr(0x01);
    $entryOffset = 12;
    $entryBytes = $encodeEntryHeader(7, strlen($oversizedResultDelta))
        . hex2bin($base->oid())
        . gzcompress($oversizedResultDelta);
    $malformedPackPrefix = 'PACK' . pack('N2', 2, 1) . $entryBytes;
    $malformedPackChecksum = hash('sha1', $malformedPackPrefix);
    $malformedPack = PackData::fromBytes($malformedPackPrefix . hex2bin($malformedPackChecksum));
    $malformedIndex = PackIndex::fromBytes($buildSingleEntryIndex($target->oid(), $entryOffset, $entryBytes, $malformedPackChecksum));

    $malformedPack->readObjectWithExternalBases($malformedIndex, $target->oid(), [$base->oid() => $base]);
} catch (RuntimeException) {
    $oversizedDeltaHeaderGuard = true;
}

$deltaResultBufferGuard = false;
try {
    $base = new GitObject('blob', str_repeat('A', 256));
    $target = new GitObject('blob', 'AA');
    $copyTooMuchDelta = $encodeDeltaSize(strlen($base->body)) . $encodeDeltaSize(1) . chr(0x90) . chr(2);
    $entryOffset = 12;
    $entryBytes = $encodeEntryHeader(7, strlen($copyTooMuchDelta))
        . hex2bin($base->oid())
        . gzcompress($copyTooMuchDelta);
    $malformedPackPrefix = 'PACK' . pack('N2', 2, 1) . $entryBytes;
    $malformedPackChecksum = hash('sha1', $malformedPackPrefix);
    $malformedPack = PackData::fromBytes($malformedPackPrefix . hex2bin($malformedPackChecksum));
    $malformedIndex = PackIndex::fromBytes($buildSingleEntryIndex($target->oid(), $entryOffset, $entryBytes, $malformedPackChecksum));

    $malformedPack->readObjectWithExternalBases($malformedIndex, $target->oid(), [$base->oid() => $base]);
} catch (RuntimeException) {
    $deltaResultBufferGuard = true;
}

$packEntryMetadataGuard = false;
try {
    $nonCanonicalBlob = chr((3 << 4) | 0x80) . chr(0x00) . gzcompress('');
    PackData::fromBytes($buildRawPack($nonCanonicalBlob))->entryAtOffset(12);
} catch (InvalidArgumentException) {
    try {
        $overflowingOfsDelta = chr(6 << 4) . str_repeat(chr(0xff), 9) . chr(0x7f) . gzcompress('');
        PackData::fromBytes($buildRawPack($overflowingOfsDelta))->entryAtOffset(12);
    } catch (InvalidArgumentException) {
        $packEntryMetadataGuard = true;
    }
}

return [
    'version' => $pack->version(),
    'objects' => $pack->count(),
    'checksum' => $pack->verifyChecksum(),
    'commitOid' => $commit->oid(),
    'blobOid' => $blob->oid(),
    'deltaBlobOid' => $deltaBlob->oid(),
    'blobPreview' => strtok($blob->body, "\n"),
    'deltaBlobHasPackedEdit' => str_contains($deltaBlob->body, 'reconstructed packed edit'),
    'deltaHeaderProbe' => $deltaHeader,
    'traversalOids' => array_column($traversal['objects'], 'oid'),
    'traversalPackOffsets' => array_column($traversal['objects'], 'packOffset'),
    'traversalStatistics' => $traversal['statistics'],
    'strictDeclaredSizeGuard' => $strictDeclaredSizeGuard,
    'oversizedDeltaHeaderGuard' => $oversizedDeltaHeaderGuard,
    'deltaResultBufferGuard' => $deltaResultBufferGuard,
    'packEntryMetadataGuard' => $packEntryMetadataGuard,
];
