<?php

declare(strict_types=1);

use PortLibs\Gitoxide\PackIndex;

$packChecksum = '0f3ea84cd1bba10c2a03d736a460635082833e59';

$buildIndex = static function (array $entries, string $packChecksum, string $hash = 'sha1') use (&$buildIndex): string {
    usort($entries, static fn (array $a, array $b): int => strcmp($a['oid'], $b['oid']));
    $hashBytes = match ($hash) {
        'sha1' => 20,
        'sha256' => 32,
        default => throw new RuntimeException("Unsupported test hash: {$hash}"),
    };
    $fanout = array_fill(0, 256, 0);
    foreach ($entries as $entry) {
        $fanout[hexdec(substr($entry['oid'], 0, 2))]++;
    }
    $running = 0;
    foreach ($fanout as $i => $count) {
        $running += $count;
        $fanout[$i] = $running;
    }

    $bytes = "\xfftOc" . pack('N', 2);
    foreach ($fanout as $count) {
        $bytes .= pack('N', $count);
    }
    foreach ($entries as $entry) {
        $oid = hex2bin($entry['oid']);
        if ($oid === false || strlen($oid) !== $hashBytes) {
            throw new RuntimeException('Invalid object id in test pack index');
        }
        $bytes .= $oid;
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
        $high = intdiv($offset, 4294967296);
        $low = $offset % 4294967296;
        $bytes .= pack('N2', $high, $low);
    }

    $bytes .= hex2bin($packChecksum);
    return $bytes . hex2bin(hash($hash, $bytes));
};

$buildV1Index = static function (array $entries, string $packChecksum): string {
    usort($entries, static fn (array $a, array $b): int => strcmp($a['oid'], $b['oid']));
    $fanout = array_fill(0, 256, 0);
    foreach ($entries as $entry) {
        $fanout[hexdec(substr($entry['oid'], 0, 2))]++;
    }
    $running = 0;
    foreach ($fanout as $i => $count) {
        $running += $count;
        $fanout[$i] = $running;
    }

    $bytes = '';
    foreach ($fanout as $count) {
        $bytes .= pack('N', $count);
    }
    foreach ($entries as $entry) {
        $bytes .= pack('N', $entry['offset']);
        $bytes .= hex2bin($entry['oid']);
    }

    $bytes .= hex2bin($packChecksum);
    return $bytes . hex2bin(hash('sha1', $bytes));
};

$entries = [
    ['oid' => '134385f6d781b7e97062102c6a483440bfda2a03', 'offset' => 12, 'crc32' => 0x12345678],
    ['oid' => '3b18e512dba79e4c8300dd08aeb37f8e728b8dad', 'offset' => 96, 'crc32' => 0x90abcdef],
    ['oid' => 'a98ad44f7f0d6eae901abe9c6f10b4d9be2a190f', 'offset' => 8589934597, 'crc32' => 0x0badc0de],
];

return [
    'parses v1 pack index offset object-id entries like upstream gix-pack' => static function (TestRunner $t) use ($buildV1Index, $packChecksum): void {
        $index = PackIndex::fromBytes($buildV1Index([
            ['oid' => '036bd66fe9b6591e959e6df51160e636ab1a682e', 'offset' => 20],
            ['oid' => '8a1218a0a4bfb2dc0cfb9590e70f49b3376f4c1d', 'offset' => 128],
            ['oid' => 'f7f791d96b9a34ef0f08db4b007c5309b9adc3d6', 'offset' => 256],
        ], $packChecksum));

        $t->same(1, $index->version());
        $t->same(3, $index->count());
        $t->same($packChecksum, $index->packChecksum());
        $t->same($index->indexChecksum(), $index->verifyChecksum());

        $first = $index->entryAt(0);
        $t->same('036bd66fe9b6591e959e6df51160e636ab1a682e', $first->oid);
        $t->same(20, $first->packOffset);
        $t->same(null, $first->crc32);
        $t->same(2, $index->lookup('f7f791d96b9a34ef0f08db4b007c5309b9adc3d6')?->index);
        $t->same('found', $index->lookupPrefix('8a1218a')['status']);
        $t->same([20, 128, 256], $index->sortedOffsets());
    },
    'parses v2 pack index fanout entries checksums and large offsets' => static function (TestRunner $t) use ($buildIndex, $entries, $packChecksum): void {
        $index = PackIndex::fromBytes($buildIndex($entries, $packChecksum));
        $t->same(2, $index->version());
        $t->same(3, $index->count());
        $t->same($packChecksum, $index->packChecksum());
        $t->same($index->indexChecksum(), $index->verifyChecksum());

        $first = $index->entryAt(0);
        $t->same('134385f6d781b7e97062102c6a483440bfda2a03', $first->oid);
        $t->same(12, $first->packOffset);
        $t->same(0x12345678, $first->crc32);
        $t->same([12, 96, 8589934597], $index->sortedOffsets());
    },
    'returns pack-index entries in pack-offset traversal order like gix-pack util' => static function (TestRunner $t) use ($buildIndex, $packChecksum): void {
        $index = PackIndex::fromBytes($buildIndex([
            ['oid' => '9000111111111111111111111111111111111111', 'offset' => 48, 'crc32' => 9],
            ['oid' => '3000333333333333333333333333333333333333', 'offset' => 12, 'crc32' => 3],
            ['oid' => '1000111111111111111111111111111111111111', 'offset' => 12, 'crc32' => 1],
            ['oid' => '2000222222222222222222222222222222222222', 'offset' => 24, 'crc32' => 2],
        ], $packChecksum));

        $traversal = $index->entriesSortedByPackOffset();

        $t->same([12, 12, 24, 48], array_map(static fn ($entry): int => $entry->packOffset, $traversal));
        $t->same([0, 2, 1, 3], array_map(static fn ($entry): int => $entry->index, $traversal));
        $t->same([
            '1000111111111111111111111111111111111111',
            '3000333333333333333333333333333333333333',
            '2000222222222222222222222222222222222222',
            '9000111111111111111111111111111111111111',
        ], array_map(static fn ($entry): string => $entry->oid, $traversal));
        $t->same([1, 3, 2, 9], array_map(static fn ($entry): ?int => $entry->crc32, $traversal));
    },
    'honors pack index large-offset threshold boundaries like gix-pack' => static function (TestRunner $t) use ($buildIndex, $packChecksum): void {
        $boundaryEntries = [
            ['oid' => '1000111111111111111111111111111111111111', 'offset' => 0x7fffffff, 'crc32' => 1],
            ['oid' => '2000222222222222222222222222222222222222', 'offset' => 0x80000000, 'crc32' => 2],
            ['oid' => '3000333333333333333333333333333333333333', 'offset' => 0xffffffff, 'crc32' => 3],
        ];
        $index = PackIndex::fromBytes($buildIndex($boundaryEntries, $packChecksum));

        $t->same(0x7fffffff, $index->lookup('1000111111111111111111111111111111111111')?->packOffset);
        $t->same(0x80000000, $index->lookup('2000222222222222222222222222222222222222')?->packOffset);
        $t->same(0xffffffff, $index->lookup('3000333333333333333333333333333333333333')?->packOffset);
        $t->same([0x7fffffff, 0x80000000, 0xffffffff], $index->sortedOffsets());
        $t->same('found', $index->lookupPrefix('2000')['status']);
    },
    'looks up full object ids and prefixes like gix-pack index access' => static function (TestRunner $t) use ($buildIndex, $entries, $packChecksum): void {
        $index = PackIndex::fromBytes($buildIndex($entries, $packChecksum));
        $entry = $index->lookup('3B18E512DBA79E4C8300DD08AEB37F8E728B8DAD');
        $t->same(96, $entry?->packOffset);
        $t->same(null, $index->lookup('ffffffffffffffffffffffffffffffffffffffff'));

        $prefix = $index->lookupPrefix('3b18e51');
        $t->same('found', $prefix['status']);
        $t->same('3b18e512dba79e4c8300dd08aeb37f8e728b8dad', $prefix['entry']->oid);
        $t->same('missing', $index->lookupPrefix('fffffff')['status']);
    },
    'rejects invalid-byte pack index prefixes and object ids like gix hash parsing' => static function (TestRunner $t) use ($buildIndex, $entries, $packChecksum): void {
        $index = PackIndex::fromBytes($buildIndex($entries, $packChecksum));
        $oid = $entries[1]['oid'];
        $invalidOid = substr($oid, 0, 7) . 'g' . substr($oid, 8);

        $t->throws(InvalidArgumentException::class, static fn () => $index->lookup($oid . "\n"));
        $t->throws(InvalidArgumentException::class, static fn () => $index->lookup($invalidOid));
        $t->throws(InvalidArgumentException::class, static fn () => $index->lookupPrefix(substr($oid, 0, 8) . "\n"));
        $t->throws(InvalidArgumentException::class, static fn () => $index->lookupPrefix(substr($oid, 0, 4) . "\0"));
        $t->throws(InvalidArgumentException::class, static fn () => $index->lookupPrefix(substr($oid, 0, 4) . 'g'));
        $t->throws(InvalidArgumentException::class, static fn () => $index->lookupPrefix(substr($oid, 0, 3)));
        $t->throws(InvalidArgumentException::class, static fn () => $index->lookupPrefix($oid . '0'));
        $t->throws(InvalidArgumentException::class, static fn () => $index->disambiguatePrefix($oid . "\n", 4));
        $t->throws(InvalidArgumentException::class, static fn () => $index->disambiguatePrefix($invalidOid, 4));
    },
    'reports ambiguous pack index prefixes' => static function (TestRunner $t) use ($buildIndex, $packChecksum): void {
        $index = PackIndex::fromBytes($buildIndex([
            ['oid' => '3b18e512dba79e4c8300dd08aeb37f8e728b8dad', 'offset' => 12, 'crc32' => 1],
            ['oid' => '3b18e5ffffffffffffffffffffffffffffffffff', 'offset' => 24, 'crc32' => 2],
        ], $packChecksum));
        $prefix = $index->lookupPrefix('3b18e5');
        $t->same('ambiguous', $prefix['status']);
        $t->same([0, 1], $prefix['matches']);
    },
    'expands pack index prefix candidates around the binary-search midpoint like gix-pack' => static function (TestRunner $t) use ($buildIndex, $packChecksum): void {
        $first = '4abc011111111111111111111111111111111111';
        $middle = '4abc122222222222222222222222222222222222';
        $last = '4abc233333333333333333333333333333333333';
        $outside = '4abd344444444444444444444444444444444444';
        $index = PackIndex::fromBytes($buildIndex([
            ['oid' => $outside, 'offset' => 48, 'crc32' => 4],
            ['oid' => $last, 'offset' => 36, 'crc32' => 3],
            ['oid' => $first, 'offset' => 12, 'crc32' => 1],
            ['oid' => $middle, 'offset' => 24, 'crc32' => 2],
        ], $packChecksum));

        $ambiguous = $index->lookupPrefix('4ABC');
        $t->same('ambiguous', $ambiguous['status']);
        $t->same([0, 1, 2], $ambiguous['matches']);
        $t->same(['start' => 0, 'end' => 3], $ambiguous['candidateRange']);

        $found = $index->lookupPrefix('4abc1');
        $t->same('found', $found['status']);
        $t->same($middle, $found['entry']->oid);
        $t->same(['start' => 1, 'end' => 2], $found['candidateRange']);
        $t->same('4abc0', $index->disambiguatePrefix($first, 4));
    },
    'reports upstream-style pack index prefix candidate ranges and disambiguates by nibble' => static function (TestRunner $t) use ($buildIndex, $packChecksum): void {
        $first = '3b18a11111111111111111111111111111111111';
        $second = '3b18b22222222222222222222222222222222222';
        $third = '3b19c33333333333333333333333333333333333';
        $index = PackIndex::fromBytes($buildIndex([
            ['oid' => $second, 'offset' => 24, 'crc32' => 2],
            ['oid' => $third, 'offset' => 36, 'crc32' => 3],
            ['oid' => $first, 'offset' => 12, 'crc32' => 1],
        ], $packChecksum));

        $ambiguous = $index->lookupPrefix('3B18');
        $t->same('ambiguous', $ambiguous['status']);
        $t->same([0, 1], $ambiguous['matches']);
        $t->same(['start' => 0, 'end' => 2], $ambiguous['candidateRange']);

        $found = $index->lookupPrefix('3b18a');
        $t->same('found', $found['status']);
        $t->same($first, $found['entry']->oid);
        $t->same(['start' => 0, 'end' => 1], $found['candidateRange']);

        $missing = $index->lookupPrefix('ffff');
        $t->same('missing', $missing['status']);
        $t->same(['start' => 0, 'end' => 0], $missing['candidateRange']);

        $t->same('3b18a', $index->disambiguatePrefix(strtoupper($first), 4));
        $t->same($first, $index->disambiguatePrefix($first, 40));
        $t->same($first, $index->disambiguatePrefix(strtoupper($first), 40));
        $t->same(null, $index->disambiguatePrefix('ffffffffffffffffffffffffffffffffffffffff', 4));
        $t->throws(InvalidArgumentException::class, static fn () => $index->disambiguatePrefix($first, 3));
    },
    'reports sha256 pack index prefix ranges like multi-pack-index hash-kind lookup' => static function (TestRunner $t) use ($buildIndex): void {
        $first = 'aaaa' . str_repeat('1', 60);
        $second = 'aaaab' . str_repeat('2', 59);
        $third = 'bbbb' . str_repeat('3', 60);
        $packChecksum = hash('sha256', 'wordpress-sha256-pack-checksum');
        $indexBytes = $buildIndex([
            ['oid' => $second, 'offset' => 24, 'crc32' => 2],
            ['oid' => $third, 'offset' => 36, 'crc32' => 3],
            ['oid' => $first, 'offset' => 12, 'crc32' => 1],
        ], $packChecksum, 'sha256');
        $index = PackIndex::fromBytes($indexBytes, 'sha256');

        $t->same('sha256', $index->objectHash());
        $t->same(32, $index->hashBytes());
        $t->same($packChecksum, $index->packChecksum());
        $t->same(hash('sha256', substr($indexBytes, 0, -32)), $index->verifyChecksum());

        $ambiguous = $index->lookupPrefix('AAAA');
        $t->same('ambiguous', $ambiguous['status']);
        $t->same([0, 1], $ambiguous['matches']);
        $t->same(['start' => 0, 'end' => 2], $ambiguous['candidateRange']);

        $found = $index->lookupPrefix('aaaa1');
        $t->same('found', $found['status']);
        $t->same($first, $found['entry']->oid);
        $t->same(['start' => 0, 'end' => 1], $found['candidateRange']);
        $t->same(12, $index->lookup(strtoupper($first))?->packOffset);

        $missing = $index->lookupPrefix('ffff');
        $t->same('missing', $missing['status']);
        $t->same(['start' => 0, 'end' => 0], $missing['candidateRange']);

        $t->same('aaaa1', $index->disambiguatePrefix(strtoupper($first), 4));
        $t->same($first, $index->disambiguatePrefix($first, 64));
        $t->same($first, $index->disambiguatePrefix(strtoupper($first), 64));
        $t->same(null, $index->disambiguatePrefix(str_repeat('f', 64), 4));
        $t->throws(InvalidArgumentException::class, static fn () => $index->lookup($first . '0'));
        $t->throws(InvalidArgumentException::class, static fn () => PackIndex::fromBytes('', 'blake3'));
    },
    'resets odd-length missing pack index prefixes and treats full ids as one-entry ranges' => static function (TestRunner $t) use ($buildIndex, $entries, $packChecksum): void {
        $index = PackIndex::fromBytes($buildIndex($entries, $packChecksum));

        $missing = $index->lookupPrefix('0000000');
        $t->same('missing', $missing['status']);
        $t->same(['start' => 0, 'end' => 0], $missing['candidateRange']);

        $first = $index->lookupPrefix(strtoupper($entries[0]['oid']));
        $t->same('found', $first['status']);
        $t->same($entries[0]['oid'], $first['entry']->oid);
        $t->same(['start' => 0, 'end' => 1], $first['candidateRange']);

        $last = $index->lookupPrefix(strtoupper($entries[2]['oid']));
        $t->same('found', $last['status']);
        $t->same($entries[2]['oid'], $last['entry']->oid);
        $t->same(['start' => 2, 'end' => 3], $last['candidateRange']);
    },
    'round-trips generated odd and even pack index prefixes for every entry like upstream lookup prefix' => static function (TestRunner $t) use ($buildIndex, $packChecksum): void {
        $index = PackIndex::fromBytes($buildIndex([
            ['oid' => '0211111111111111111111111111111111111111', 'offset' => 12, 'crc32' => 1],
            ['oid' => '1332222222222222222222222222222222222222', 'offset' => 24, 'crc32' => 2],
            ['oid' => '2444333333333333333333333333333333333333', 'offset' => 36, 'crc32' => 3],
            ['oid' => '3555544444444444444444444444444444444444', 'offset' => 48, 'crc32' => 4],
            ['oid' => '4666655555555555555555555555555555555555', 'offset' => 60, 'crc32' => 5],
            ['oid' => '5777766666666666666666666666666666666666', 'offset' => 72, 'crc32' => 6],
        ], $packChecksum));

        foreach ($index->entries() as $entryIndex => $entry) {
            $hexLength = 7 + $entryIndex;
            $prefix = substr($entry->oid, 0, $hexLength);
            $lookup = $index->lookupPrefix(strtoupper($prefix));

            $t->same('found', $lookup['status'], "entry {$entryIndex} generated prefix is unique");
            $t->same($entry->oid, $lookup['entry']->oid);
            $t->same(['start' => $entryIndex, 'end' => $entryIndex + 1], $lookup['candidateRange']);
            $t->same($prefix, $index->disambiguatePrefix(strtoupper($entry->oid), $hexLength));
        }
    },
    'returns full pack index prefix after every shorter prefix remains ambiguous like gix-odb' => static function (TestRunner $t) use ($buildIndex, $packChecksum): void {
        $sharedThirtyNine = str_repeat('a', 39);
        $first = $sharedThirtyNine . '1';
        $second = $sharedThirtyNine . '2';
        $missingCandidate = $sharedThirtyNine . '3';
        $index = PackIndex::fromBytes($buildIndex([
            ['oid' => $second, 'offset' => 24, 'crc32' => 2],
            ['oid' => $first, 'offset' => 12, 'crc32' => 1],
        ], $packChecksum));

        $ambiguous = $index->lookupPrefix(substr($missingCandidate, 0, 39));
        $t->same('ambiguous', $ambiguous['status']);
        $t->same(['start' => 0, 'end' => 2], $ambiguous['candidateRange']);
        $t->same($missingCandidate, $index->disambiguatePrefix(strtoupper($missingCandidate), 4));
        $t->same(null, $index->disambiguatePrefix($missingCandidate, 40));
        $t->same('missing', $index->lookupPrefix($missingCandidate)['status']);
    },
    'returns unique pack index prefix for absent full candidate like gix-odb' => static function (TestRunner $t) use ($buildIndex, $packChecksum): void {
        $existing = '8aaa' . str_repeat('1', 36);
        $missingCandidate = '8aaa' . str_repeat('9', 36);
        $index = PackIndex::fromBytes($buildIndex([
            ['oid' => $existing, 'offset' => 12, 'crc32' => 1],
        ], $packChecksum));

        $prefix = $index->lookupPrefix('8AAA');
        $t->same('found', $prefix['status']);
        $t->same($existing, $prefix['entry']->oid);
        $t->same(['start' => 0, 'end' => 1], $prefix['candidateRange']);
        $t->same('8aaa', $index->disambiguatePrefix(strtoupper($missingCandidate), 4));
        $t->same(null, $index->disambiguatePrefix($missingCandidate, 40));
        $t->same('missing', $index->lookupPrefix($missingCandidate)['status']);
    },
    'parses empty pack indexes and reports missing prefix ranges like gix-pack' => static function (TestRunner $t) use ($buildIndex, $buildV1Index, $packChecksum): void {
        $v2 = PackIndex::fromBytes($buildIndex([], $packChecksum));
        $t->same(2, $v2->version());
        $t->same(0, $v2->count());
        $t->same([], $v2->entries());
        $t->same([], $v2->sortedOffsets());
        $t->same(null, $v2->lookup(str_repeat('0', 40)));

        $missing = $v2->lookupPrefix('0000');
        $t->same('missing', $missing['status']);
        $t->same(['start' => 0, 'end' => 0], $missing['candidateRange']);
        $t->same(null, $v2->disambiguatePrefix(str_repeat('0', 40), 4));
        $t->same(null, $v2->disambiguatePrefix(str_repeat('0', 40), 40));

        $v1 = PackIndex::fromBytes($buildV1Index([], $packChecksum));
        $t->same(1, $v1->version());
        $t->same(0, $v1->count());
        $t->same('missing', $v1->lookupPrefix('ffff')['status']);
        $t->same(['start' => 0, 'end' => 0], $v1->lookupPrefix('ffff')['candidateRange']);
    },
    'rejects corrupt pack index headers fanout sizes and checksums' => static function (TestRunner $t) use ($buildIndex, $entries, $packChecksum): void {
        $valid = $buildIndex($entries, $packChecksum);
        $t->throws(InvalidArgumentException::class, static fn () => PackIndex::fromBytes('not an index'));
        $t->throws(InvalidArgumentException::class, static fn () => PackIndex::fromBytes("\xfftOc" . pack('N', 3) . substr($valid, 8)));

        $badFanout = $valid;
        $badFanout[8] = "\0";
        $badFanout[9] = "\0";
        $badFanout[10] = "\0";
        $badFanout[11] = "\2";
        $t->throws(InvalidArgumentException::class, static fn () => PackIndex::fromBytes($badFanout));

        $badChecksum = substr($valid, 0, -1) . "\0";
        $index = PackIndex::fromBytes($badChecksum);
        $t->throws(RuntimeException::class, static fn () => $index->verifyChecksum());
    },
    'rejects truncated v1 pack indices before lookup can read past entry tables' => static function (TestRunner $t) use ($buildV1Index, $packChecksum): void {
        $valid = $buildV1Index([
            ['oid' => '036bd66fe9b6591e959e6df51160e636ab1a682e', 'offset' => 20],
            ['oid' => 'f7f791d96b9a34ef0f08db4b007c5309b9adc3d6', 'offset' => 256],
        ], $packChecksum);

        $t->throws(InvalidArgumentException::class, static fn () => PackIndex::fromBytes(substr($valid, 0, -1)));
    },
    'wordpress fixture locates compacted content objects without git binary' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-pack-index.php';
        $index = PackIndex::fromBytes($fixture['indexBytes'], $fixture['objectHash']);
        $summary = require dirname(__DIR__) . '/examples/wordpress-pack-index.php';
        $t->same('sha1', $index->objectHash());
        $t->same(20, $index->hashBytes());
        $t->same(3, $index->count());
        $t->same($fixture['packChecksum'], $index->packChecksum());
        $t->same($fixture['objects'][1]['offset'], $index->lookup($fixture['objects'][1]['oid'])?->packOffset);
        $t->same($fixture['objects'][2]['offset'], $index->lookup($fixture['objects'][2]['oid'])?->packOffset);
        $t->same($fixture['objects'][1]['oid'], $summary['wordpressBlobFullPrefixFromUppercase']);
    },
];
