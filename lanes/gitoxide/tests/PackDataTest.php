<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;

$buildPackFixture = static function (array $objects): array {
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

        return implode('', array_map(chr(...), $bytes));
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

    $pack = 'PACK' . pack('N2', 2, count($objects));
    $entries = [];
    foreach ($objects as $object) {
        $offset = strlen($pack);
        $objectBody = $object['body'];
        $entryPrefix = '';
        if ($object['typeId'] === 6) {
            $base = $entries[$object['baseEntry']] ?? null;
            if ($base === null) {
                throw new RuntimeException('OFS_DELTA test fixture is missing its base entry');
            }
            $entryPrefix = $encodeOfsDeltaDistance($offset - $base['offset']);
        } elseif ($object['typeId'] === 7) {
            $base = $entries[$object['baseEntry']] ?? null;
            if ($base === null) {
                throw new RuntimeException('REF_DELTA test fixture is missing its base entry');
            }
            $entryPrefix = hex2bin($base['oid']);
        }

        $entryBytes = $encodeEntryHeader($object['typeId'], strlen($objectBody)) . $entryPrefix . gzcompress($objectBody);
        $pack .= $entryBytes;
        $indexType = $object['finalType'] ?? $object['type'];
        $indexBody = $object['finalBody'] ?? $objectBody;
        $entries[] = [
            'type' => $indexType,
            'body' => $indexBody,
            'oid' => (new GitObject($indexType, $indexBody))->oid(),
            'offset' => $offset,
            'crc32' => hexdec(hash('crc32b', $entryBytes)),
        ];
    }
    $packChecksum = hash('sha1', $pack);
    $pack .= hex2bin($packChecksum);

    return [$pack, $buildIndex($entries, $packChecksum), $entries, $packChecksum];
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
    if (strlen($base) > 255) {
        throw new RuntimeException('Test helper only encodes one-byte copy sizes');
    }

    return $encodeDeltaSize(strlen($base))
        . $encodeDeltaSize(strlen($base) + strlen($insert))
        . chr(0x80 | 0x10)
        . chr(strlen($base))
        . chr(strlen($insert))
        . $insert;
};

return [
    'parses pack data header and verifies checksum' => static function (TestRunner $t) use ($buildPackFixture): void {
        [$packBytes, , , $checksum] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => 'hello pack'],
        ]);
        $pack = PackData::fromBytes($packBytes);
        $t->same(2, $pack->version());
        $t->same(1, $pack->count());
        $t->same($checksum, $pack->checksum());
        $t->same($checksum, $pack->verifyChecksum());
    },
    'reads non-delta blob and commit objects by pack-index offset' => static function (TestRunner $t) use ($buildPackFixture): void {
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'commit', 'typeId' => 1, 'body' => "tree e90926b07092bccb7bf7da445fae6ffdfacf3eae\n\nInitial commit\n"],
            ['type' => 'blob', 'typeId' => 3, 'body' => str_repeat('WordPress pack data ', 10)],
        ]);
        $pack = PackData::fromBytes($packBytes);
        $index = PackIndex::fromBytes($indexBytes);

        foreach ($entries as $entry) {
            $object = $pack->readObject($index, $entry['oid']);
            $t->same($entry['type'], $object->type);
            $t->same($entry['body'], $object->body);
            $t->same($entry['oid'], $object->oid());
        }
    },
    'parses multi-byte entry size headers' => static function (TestRunner $t) use ($buildPackFixture): void {
        [$packBytes, , $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => str_repeat('x', 200)],
        ]);
        $entry = PackData::fromBytes($packBytes)->entryAtOffset($entries[0]['offset']);
        $t->same('blob', $entry->kind);
        $t->same(200, $entry->decompressedSize);
        $t->true($entry->headerSize > 1, 'large entries should use a multi-byte size header');
    },
    'rejects bad pack data and unsupported delta resolution' => static function (TestRunner $t) use ($buildPackFixture): void {
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => 'base'],
        ]);
        $t->throws(InvalidArgumentException::class, static fn () => PackData::fromBytes('not a pack'));
        $t->throws(InvalidArgumentException::class, static fn () => PackData::fromBytes('PACK' . pack('N2', 9, 0) . str_repeat("\0", 20)));

        $badChecksum = substr($packBytes, 0, -1) . "\0";
        $t->throws(RuntimeException::class, static fn () => PackData::fromBytes($badChecksum)->verifyChecksum());

        $pack = PackData::fromBytes($packBytes);
        $wrongIndex = PackIndex::fromBytes(str_replace(hex2bin($pack->checksum()), str_repeat("\0", 20), $indexBytes));
        $t->throws(RuntimeException::class, static fn () => $pack->readObject($wrongIndex, $entries[0]['oid']));

        $deltaPack = 'PACK' . pack('N2', 2, 1) . chr((6 << 4) | 4) . chr(1) . gzcompress('delt') . str_repeat("\0", 20);
        $delta = PackData::fromBytes($deltaPack)->entryAtOffset(12);
        $t->same('ofs-delta', $delta->kind);
        $t->same(1, $delta->baseDistance);
        $t->throws(RuntimeException::class, static fn () => $delta->object());
    },
    'resolves ofs-delta packed blob objects by base offset' => static function (TestRunner $t) use ($buildPackFixture, $copyThenInsertDelta): void {
        $base = 'Hello ';
        $final = 'Hello WordPress';
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => $base],
            ['type' => 'ofs-delta', 'typeId' => 6, 'body' => $copyThenInsertDelta($base, 'WordPress'), 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => $final],
        ]);

        $object = PackData::fromBytes($packBytes)->readObject(PackIndex::fromBytes($indexBytes), $entries[1]['oid']);
        $t->same('blob', $object->type);
        $t->same($final, $object->body);
        $t->same($entries[1]['oid'], $object->oid());
    },
    'resolves ref-delta packed blob objects by base object id' => static function (TestRunner $t) use ($buildPackFixture, $copyThenInsertDelta): void {
        $base = 'WordPress';
        $final = 'WordPress import';
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => $base],
            ['type' => 'ref-delta', 'typeId' => 7, 'body' => $copyThenInsertDelta($base, ' import'), 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => $final],
        ]);

        $object = PackData::fromBytes($packBytes)->readObject(PackIndex::fromBytes($indexBytes), $entries[1]['oid']);
        $t->same('blob', $object->type);
        $t->same($final, $object->body);
    },
    'rejects corrupt delta instructions during object resolution' => static function (TestRunner $t) use ($buildPackFixture, $encodeDeltaSize): void {
        [$packBytes, $indexBytes, $entries] = $buildPackFixture([
            ['type' => 'blob', 'typeId' => 3, 'body' => 'base'],
            ['type' => 'ofs-delta', 'typeId' => 6, 'body' => $encodeDeltaSize(4) . $encodeDeltaSize(4) . chr(0), 'baseEntry' => 0, 'finalType' => 'blob', 'finalBody' => 'base'],
        ]);

        $t->throws(RuntimeException::class, static fn () => PackData::fromBytes($packBytes)->readObject(PackIndex::fromBytes($indexBytes), $entries[1]['oid']));
    },
    'wordpress fixture reads compacted commit blob and delta objects without git binary' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-pack-data.php';
        $pack = PackData::fromBytes($fixture['packBytes']);
        $index = PackIndex::fromBytes($fixture['indexBytes']);

        $t->same($fixture['packChecksum'], $pack->verifyChecksum());
        $commit = $pack->readObject($index, $fixture['objects'][0]['oid']);
        $blob = $pack->readObject($index, $fixture['objects'][1]['oid']);
        $deltaBlob = $pack->readObject($index, $fixture['objects'][2]['oid']);
        $t->same('commit', $commit->type);
        $t->contains('Import WordPress content', $commit->body);
        $t->same('blob', $blob->type);
        $t->contains('wp_posts export', $blob->body);
        $t->same('blob', $deltaBlob->type);
        $t->contains('reconstructed packed edit', $deltaBlob->body);
    },
];
