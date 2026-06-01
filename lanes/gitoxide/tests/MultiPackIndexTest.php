<?php

declare(strict_types=1);

use PortLibs\Gitoxide\MultiPackIndex;

$packUInt64 = static function (int $value): string {
    if ($value < 0) {
        throw new RuntimeException('Cannot encode a negative 64-bit integer');
    }

    return pack('N2', intdiv($value, 4294967296), $value % 4294967296);
};

$padToFour = static function (string $bytes): string {
    $padding = (4 - (strlen($bytes) % 4)) % 4;

    return $bytes . str_repeat("\0", $padding);
};

$buildMultiIndex = static function (
    array $entries,
    array $indexNames,
    string $hash = 'sha1',
    bool $sortEntries = true,
    bool $includeLargeOffsets = true,
) use ($packUInt64, $padToFour): string {
    if ($sortEntries) {
        usort($entries, static fn (array $a, array $b): int => strcmp($a['oid'], $b['oid']));
    }

    $hashKind = match ($hash) {
        'sha1' => 1,
        'sha256' => 2,
        default => throw new RuntimeException("Unsupported test hash: {$hash}"),
    };
    $hashBytes = $hash === 'sha1' ? 20 : 32;

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
        $oid = hex2bin($entry['oid']);
        if ($oid === false || strlen($oid) !== $hashBytes) {
            throw new RuntimeException('Invalid object id in test multi-pack-index');
        }
        $oidBytes .= $oid;
    }

    $needsLargeOffsets = $includeLargeOffsets && array_reduce(
        $entries,
        static fn (bool $carry, array $entry): bool => $carry || $entry['offset'] > 0xffffffff,
        false
    );
    $largeOffsets = [];
    $offsetBytes = '';
    foreach ($entries as $entry) {
        $offsetBytes .= pack('N', $entry['packIndex']);
        if ($needsLargeOffsets && $entry['offset'] > 0x7fffffff) {
            $largeIndex = count($largeOffsets);
            $largeOffsets[] = $entry['offset'];
            $offsetBytes .= pack('N', 0x80000000 | $largeIndex);
        } else {
            $offsetBytes .= pack('N', $entry['offset']);
        }
    }

    $chunks = [
        'PNAM' => $padToFour($packNames),
        'OIDF' => $fanoutBytes,
        'OIDL' => $oidBytes,
        'OOFF' => $offsetBytes,
    ];
    if ($needsLargeOffsets) {
        $largeOffsetBytes = '';
        foreach ($largeOffsets as $offset) {
            $largeOffsetBytes .= $packUInt64($offset);
        }
        $chunks['LOFF'] = $largeOffsetBytes;
    }

    $header = 'MIDX' . chr(1) . chr($hashKind) . chr(count($chunks)) . "\0" . pack('N', count($indexNames));
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

    return $bytes . hex2bin(hash($hash, $bytes));
};

$buildMultiIndexWithAbsurdPackCount = static function () use ($packUInt64): string {
    $headerBytes = 12;
    $tocBytes = 5 * 12;
    $packNamesStart = $headerBytes + $tocBytes;
    $fanoutStart = $packNamesStart + 1;
    $lookupStart = $fanoutStart + 256 * 4;
    $offsetsStart = $lookupStart + 20;
    $trailerStart = $offsetsStart + 8;

    $bytes = 'MIDX' . chr(1) . chr(1) . chr(4) . "\0" . pack('N', 0xffffffff);
    $bytes .= 'PNAM' . $packUInt64($packNamesStart);
    $bytes .= 'OIDF' . $packUInt64($fanoutStart);
    $bytes .= 'OIDL' . $packUInt64($lookupStart);
    $bytes .= 'OOFF' . $packUInt64($offsetsStart);
    $bytes .= "\0\0\0\0" . $packUInt64($trailerStart);

    $bytes .= "\0";
    for ($i = 0; $i < 256; $i++) {
        $bytes .= pack('N', $i === 255 ? 1 : 0);
    }
    $bytes .= str_repeat("\0", 20);
    $bytes .= pack('N2', 0, 0);

    return $bytes . hex2bin(hash('sha1', $bytes));
};

$indexNames = [
    'pack-1111111111111111111111111111111111111111.idx',
    'pack-2222222222222222222222222222222222222222.idx',
];
$entries = [
    ['oid' => '0034111111111111111111111111111111111111', 'packIndex' => 0, 'offset' => 12],
    ['oid' => '0ffa111111111111111111111111111111111111', 'packIndex' => 1, 'offset' => 96],
    ['oid' => '0ffa222222222222222222222222222222222222', 'packIndex' => 1, 'offset' => 8589934597],
    ['oid' => 'fabc111111111111111111111111111111111111', 'packIndex' => 0, 'offset' => 128],
];

return [
    'parses v1 multi-pack-index chunks names hashes offsets and checksum' => static function (TestRunner $t) use ($buildMultiIndex, $entries, $indexNames): void {
        $index = MultiPackIndex::fromBytes($buildMultiIndex($entries, $indexNames));

        $t->same(1, $index->version());
        $t->same('sha1', $index->objectHash());
        $t->same(20, $index->hashBytes());
        $t->same(2, $index->packCount());
        $t->same(4, $index->count());
        $t->same($indexNames, $index->indexNames());
        $t->same($index->checksum(), $index->verifyChecksum());
        $t->same($index->checksum(), $index->verifyIntegrityFast());

        $entry = $index->entryAt(2);
        $t->same('0ffa222222222222222222222222222222222222', $entry->oid);
        $t->same(1, $entry->packIndex);
        $t->same(8589934597, $entry->packOffset);
        $t->same($entry->oid, $index->oidAtIndex(2));
        $t->same(['packIndex' => 1, 'packOffset' => 8589934597], $index->packIdAndPackOffsetAtIndex(2));

        $sha256Oid = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $sha256 = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => $sha256Oid, 'packIndex' => 0, 'offset' => 12],
        ], [$indexNames[0]], 'sha256'));
        $t->same('sha256', $sha256->objectHash());
        $t->same(32, $sha256->hashBytes());
        $t->same(12, $sha256->lookup($sha256Oid)?->packOffset);
    },
    'looks up multi-pack-index full object ids and prefixes' => static function (TestRunner $t) use ($buildMultiIndex, $entries, $indexNames): void {
        $index = MultiPackIndex::fromBytes($buildMultiIndex($entries, $indexNames));

        $found = $index->lookup('0FFA111111111111111111111111111111111111');
        $t->same(96, $found?->packOffset);
        $t->same(null, $index->lookup('ffffffffffffffffffffffffffffffffffffffff'));

        $prefix = $index->lookupPrefix('003411');
        $t->same('found', $prefix['status']);
        $t->same('0034111111111111111111111111111111111111', $prefix['entry']->oid);
        $t->same('missing', $index->lookupPrefix('abcd')['status']);

        $ambiguous = $index->lookupPrefix('0ffa');
        $t->same('ambiguous', $ambiguous['status']);
        $t->same([1, 2], $ambiguous['matches']);
    },
    'expands multi-pack-index prefix candidates around the binary-search midpoint like gix-pack' => static function (TestRunner $t) use ($buildMultiIndex, $indexNames): void {
        $first = '5abc011111111111111111111111111111111111';
        $middle = '5abc122222222222222222222222222222222222';
        $last = '5abc233333333333333333333333333333333333';
        $outside = '5abd344444444444444444444444444444444444';
        $index = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => $outside, 'packIndex' => 1, 'offset' => 48],
            ['oid' => $last, 'packIndex' => 1, 'offset' => 36],
            ['oid' => $first, 'packIndex' => 0, 'offset' => 12],
            ['oid' => $middle, 'packIndex' => 0, 'offset' => 24],
        ], $indexNames));

        $ambiguous = $index->lookupPrefix('5ABC');
        $t->same('ambiguous', $ambiguous['status']);
        $t->same([0, 1, 2], $ambiguous['matches']);
        $t->same(['start' => 0, 'end' => 3], $ambiguous['candidateRange']);

        $found = $index->lookupPrefix('5abc1');
        $t->same('found', $found['status']);
        $t->same($middle, $found['entry']->oid);
        $t->same(['start' => 1, 'end' => 2], $found['candidateRange']);
        $t->same('5abc0', $index->disambiguatePrefix($first, 4));
    },
    'reports upstream-style multi-pack-index prefix candidate ranges and disambiguates by hash kind' => static function (TestRunner $t) use ($buildMultiIndex, $entries, $indexNames): void {
        $index = MultiPackIndex::fromBytes($buildMultiIndex($entries, $indexNames));

        $ambiguous = $index->lookupPrefix('0FFA');
        $t->same('ambiguous', $ambiguous['status']);
        $t->same([1, 2], $ambiguous['matches']);
        $t->same(['start' => 1, 'end' => 3], $ambiguous['candidateRange']);

        $found = $index->lookupPrefix('0ffa1');
        $t->same('found', $found['status']);
        $t->same('0ffa111111111111111111111111111111111111', $found['entry']->oid);
        $t->same(['start' => 1, 'end' => 2], $found['candidateRange']);

        $missing = $index->lookupPrefix('ffff');
        $t->same('missing', $missing['status']);
        $t->same(['start' => 0, 'end' => 0], $missing['candidateRange']);

        $t->same('0ffa1', $index->disambiguatePrefix('0FFA111111111111111111111111111111111111', 4));
        $t->same('0ffa111111111111111111111111111111111111', $index->disambiguatePrefix('0ffa111111111111111111111111111111111111', 40));
        $t->same(null, $index->disambiguatePrefix('ffffffffffffffffffffffffffffffffffffffff', 4));

        $sha256First = 'aaaa111111111111111111111111111111111111111111111111111111111111';
        $sha256Second = 'aaaab22222222222222222222222222222222222222222222222222222222222';
        $sha256 = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => $sha256Second, 'packIndex' => 0, 'offset' => 24],
            ['oid' => $sha256First, 'packIndex' => 0, 'offset' => 12],
        ], [$indexNames[0]], 'sha256'));

        $sha256Ambiguous = $sha256->lookupPrefix('aaaa');
        $t->same('ambiguous', $sha256Ambiguous['status']);
        $t->same(['start' => 0, 'end' => 2], $sha256Ambiguous['candidateRange']);
        $t->same('aaaa1', $sha256->disambiguatePrefix(strtoupper($sha256First), 4));
        $t->same($sha256First, $sha256->disambiguatePrefix($sha256First, 64));
        $t->throws(InvalidArgumentException::class, static fn () => $sha256->lookupPrefix(str_repeat('f', 65)));
        $t->throws(InvalidArgumentException::class, static fn () => $sha256->disambiguatePrefix($sha256First, 3));
    },
    'resets odd-length missing multi-pack-index prefixes and treats full ids as one-entry ranges' => static function (TestRunner $t) use ($buildMultiIndex, $entries, $indexNames): void {
        $index = MultiPackIndex::fromBytes($buildMultiIndex($entries, $indexNames));

        $missing = $index->lookupPrefix('0000000');
        $t->same('missing', $missing['status']);
        $t->same(['start' => 0, 'end' => 0], $missing['candidateRange']);

        $first = $index->lookupPrefix(strtoupper('0034111111111111111111111111111111111111'));
        $t->same('found', $first['status']);
        $t->same('0034111111111111111111111111111111111111', $first['entry']->oid);
        $t->same(['start' => 0, 'end' => 1], $first['candidateRange']);

        $last = $index->lookupPrefix(strtoupper('fabc111111111111111111111111111111111111'));
        $t->same('found', $last['status']);
        $t->same('fabc111111111111111111111111111111111111', $last['entry']->oid);
        $t->same(['start' => 3, 'end' => 4], $last['candidateRange']);

        $sha256First = '1111' . str_repeat('a', 60);
        $sha256Second = '2222' . str_repeat('b', 60);
        $sha256 = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => $sha256Second, 'packIndex' => 0, 'offset' => 24],
            ['oid' => $sha256First, 'packIndex' => 0, 'offset' => 12],
        ], [$indexNames[0]], 'sha256'));
        $sha256Missing = $sha256->lookupPrefix('0000000');
        $t->same('missing', $sha256Missing['status']);
        $t->same(['start' => 0, 'end' => 0], $sha256Missing['candidateRange']);

        $sha256Full = $sha256->lookupPrefix(strtoupper($sha256Second));
        $t->same('found', $sha256Full['status']);
        $t->same($sha256Second, $sha256Full['entry']->oid);
        $t->same(['start' => 1, 'end' => 2], $sha256Full['candidateRange']);
    },
    'round-trips generated odd and even multi-pack-index prefixes for every entry like upstream lookup prefix' => static function (TestRunner $t) use ($buildMultiIndex, $indexNames): void {
        $index = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => '0111111111111111111111111111111111111111', 'packIndex' => 0, 'offset' => 12],
            ['oid' => '1222222222222222222222222222222222222222', 'packIndex' => 1, 'offset' => 24],
            ['oid' => '2333333333333333333333333333333333333333', 'packIndex' => 0, 'offset' => 36],
            ['oid' => '3444444444444444444444444444444444444444', 'packIndex' => 1, 'offset' => 48],
            ['oid' => '4555555555555555555555555555555555555555', 'packIndex' => 0, 'offset' => 60],
            ['oid' => '5666666666666666666666666666666666666666', 'packIndex' => 1, 'offset' => 72],
        ], $indexNames));

        foreach ($index->entries() as $entryIndex => $entry) {
            $hexLength = 5 + $entryIndex;
            $prefix = substr($entry->oid, 0, $hexLength);
            $lookup = $index->lookupPrefix(strtoupper($prefix));

            $t->same('found', $lookup['status'], "entry {$entryIndex} generated prefix is unique");
            $t->same($entry->oid, $lookup['entry']->oid);
            $t->same($entry->packIndex, $lookup['entry']->packIndex);
            $t->same($entry->packOffset, $lookup['entry']->packOffset);
            $t->same(['start' => $entryIndex, 'end' => $entryIndex + 1], $lookup['candidateRange']);
            $t->same($prefix, $index->disambiguatePrefix(strtoupper($entry->oid), $hexLength));
        }
    },
    'returns full multi-pack-index prefix after every shorter prefix remains ambiguous like gix-odb' => static function (TestRunner $t) use ($buildMultiIndex, $indexNames): void {
        $sharedThirtyNine = str_repeat('b', 39);
        $first = $sharedThirtyNine . '1';
        $second = $sharedThirtyNine . '2';
        $missingCandidate = $sharedThirtyNine . '3';
        $index = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => $second, 'packIndex' => 1, 'offset' => 24],
            ['oid' => $first, 'packIndex' => 0, 'offset' => 12],
        ], $indexNames));

        $ambiguous = $index->lookupPrefix(substr($missingCandidate, 0, 39));
        $t->same('ambiguous', $ambiguous['status']);
        $t->same(['start' => 0, 'end' => 2], $ambiguous['candidateRange']);
        $t->same($missingCandidate, $index->disambiguatePrefix(strtoupper($missingCandidate), 4));
        $t->same(null, $index->disambiguatePrefix($missingCandidate, 40));
        $t->same('missing', $index->lookupPrefix($missingCandidate)['status']);
    },
    'returns unique multi-pack-index prefix for absent full candidate like gix-odb' => static function (TestRunner $t) use ($buildMultiIndex, $indexNames): void {
        $existing = '9bbb' . str_repeat('1', 36);
        $missingCandidate = '9bbb' . str_repeat('9', 36);
        $index = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => $existing, 'packIndex' => 0, 'offset' => 12],
        ], $indexNames));

        $prefix = $index->lookupPrefix('9BBB');
        $t->same('found', $prefix['status']);
        $t->same($existing, $prefix['entry']->oid);
        $t->same(['start' => 0, 'end' => 1], $prefix['candidateRange']);
        $t->same('9bbb', $index->disambiguatePrefix(strtoupper($missingCandidate), 4));
        $t->same(null, $index->disambiguatePrefix($missingCandidate, 40));
        $t->same('missing', $index->lookupPrefix($missingCandidate)['status']);
    },
    'parses empty multi-pack-index lookup chunks and reports missing prefixes like gix-pack' => static function (TestRunner $t) use ($buildMultiIndex, $indexNames): void {
        $index = MultiPackIndex::fromBytes($buildMultiIndex([], [$indexNames[0]]));

        $t->same(1, $index->version());
        $t->same(1, $index->packCount());
        $t->same(0, $index->count());
        $t->same([$indexNames[0]], $index->packNames());
        $t->same([], $index->entries());
        $t->same(null, $index->lookup(str_repeat('0', 40)));

        $missing = $index->lookupPrefix('0000');
        $t->same('missing', $missing['status']);
        $t->same(['start' => 0, 'end' => 0], $missing['candidateRange']);
        $t->same(null, $index->disambiguatePrefix(str_repeat('0', 40), 4));
        $t->same(null, $index->disambiguatePrefix(str_repeat('0', 40), 40));
        $t->throws(RuntimeException::class, static fn () => $index->verifyIntegrityFast());
    },
    'supports raw high-bit offsets when no large-offset chunk is present' => static function (TestRunner $t) use ($buildMultiIndex, $indexNames): void {
        $index = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => '0034111111111111111111111111111111111111', 'packIndex' => 0, 'offset' => 0x80000005],
        ], $indexNames, 'sha1', true, false));

        $t->same(0x80000005, $index->entryAt(0)->packOffset);
    },
    'honors multi-pack-index large-offset chunk threshold boundaries like gix-pack' => static function (TestRunner $t) use ($buildMultiIndex, $indexNames): void {
        $rawHighBit = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => '1000111111111111111111111111111111111111', 'packIndex' => 0, 'offset' => 0x80000000],
            ['oid' => '2000222222222222222222222222222222222222', 'packIndex' => 1, 'offset' => 0xffffffff],
        ], $indexNames));

        $t->same(0x80000000, $rawHighBit->lookup('1000111111111111111111111111111111111111')?->packOffset);
        $t->same(0xffffffff, $rawHighBit->lookup('2000222222222222222222222222222222222222')?->packOffset);
        $t->same('found', $rawHighBit->lookupPrefix('2000')['status']);

        $withLargeOffsetChunk = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => '1000111111111111111111111111111111111111', 'packIndex' => 0, 'offset' => 0x7fffffff],
            ['oid' => '2000222222222222222222222222222222222222', 'packIndex' => 0, 'offset' => 0x80000000],
            ['oid' => '3000333333333333333333333333333333333333', 'packIndex' => 1, 'offset' => 0xffffffff],
            ['oid' => '4000444444444444444444444444444444444444', 'packIndex' => 1, 'offset' => 0x100000000],
        ], $indexNames));

        $t->same(['packIndex' => 0, 'packOffset' => 0x7fffffff], $withLargeOffsetChunk->packIdAndPackOffsetAtIndex(0));
        $t->same(['packIndex' => 0, 'packOffset' => 0x80000000], $withLargeOffsetChunk->packIdAndPackOffsetAtIndex(1));
        $t->same(['packIndex' => 1, 'packOffset' => 0xffffffff], $withLargeOffsetChunk->packIdAndPackOffsetAtIndex(2));
        $t->same(['packIndex' => 1, 'packOffset' => 0x100000000], $withLargeOffsetChunk->packIdAndPackOffsetAtIndex(3));
        $t->same('found', $withLargeOffsetChunk->lookupPrefix('4000')['status']);
        $t->same($withLargeOffsetChunk->checksum(), $withLargeOffsetChunk->verifyIntegrityFast());
    },
    'rejects corrupt multi-pack-index headers chunks names and checksums' => static function (TestRunner $t) use ($buildMultiIndex, $entries, $indexNames): void {
        $valid = $buildMultiIndex($entries, $indexNames);
        $t->throws(InvalidArgumentException::class, static fn () => MultiPackIndex::fromBytes('not a midx'));
        $t->throws(InvalidArgumentException::class, static fn () => MultiPackIndex::fromBytes('NOPE' . substr($valid, 4)));
        $t->throws(InvalidArgumentException::class, static fn () => MultiPackIndex::fromBytes(substr_replace($valid, chr(2), 4, 1)));
        $t->throws(InvalidArgumentException::class, static fn () => MultiPackIndex::fromBytes(substr_replace($valid, chr(9), 5, 1)));

        $missingFanout = $valid;
        $fanoutPosition = strpos($missingFanout, 'OIDF');
        if ($fanoutPosition === false) {
            throw new RuntimeException('Test fixture did not contain OIDF chunk id');
        }
        $missingFanout = substr_replace($missingFanout, 'NOPE', $fanoutPosition, 4);
        $t->throws(InvalidArgumentException::class, static fn () => MultiPackIndex::fromBytes($missingFanout));

        $badNames = $buildMultiIndex($entries, array_reverse($indexNames));
        $t->throws(InvalidArgumentException::class, static fn () => MultiPackIndex::fromBytes($badNames));

        $badChecksum = substr($valid, 0, -1) . "\0";
        $index = MultiPackIndex::fromBytes($badChecksum);
        $t->throws(RuntimeException::class, static fn () => $index->verifyChecksum());
    },
    'rejects multi-pack-index pack-name allocations over a configured upstream-style cap' => static function (TestRunner $t) use ($buildMultiIndex, $buildMultiIndexWithAbsurdPackCount): void {
        $longName = str_repeat('a', 65) . '.idx';
        $longNameIndex = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => '0034111111111111111111111111111111111111', 'packIndex' => 0, 'offset' => 12],
        ], [$longName]));
        $t->same([$longName], $longNameIndex->indexNames());
        $t->throws(InvalidArgumentException::class, static fn () => MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => '0034111111111111111111111111111111111111', 'packIndex' => 0, 'offset' => 12],
        ], [$longName]), 64));
        $t->throws(InvalidArgumentException::class, static fn () => MultiPackIndex::fromBytes($buildMultiIndexWithAbsurdPackCount(), 64 * 1024 * 1024));
    },
    'fast integrity catches out-of-order objects and missing pack ids' => static function (TestRunner $t) use ($buildMultiIndex, $indexNames): void {
        $outOfOrder = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => '0ffa222222222222222222222222222222222222', 'packIndex' => 0, 'offset' => 12],
            ['oid' => '0ffa111111111111111111111111111111111111', 'packIndex' => 0, 'offset' => 24],
        ], [$indexNames[0]], 'sha1', false));
        $t->throws(RuntimeException::class, static fn () => $outOfOrder->verifyIntegrityFast());

        $missingPack = MultiPackIndex::fromBytes($buildMultiIndex([
            ['oid' => '0034111111111111111111111111111111111111', 'packIndex' => 2, 'offset' => 12],
        ], [$indexNames[0]]));
        $t->throws(RuntimeException::class, static fn () => $missingPack->verifyIntegrityFast());
    },
    'wordpress fixture maps content and media packs through one multi-pack-index' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-multi-pack-index.php';
        $index = MultiPackIndex::fromBytes($fixture['multiIndexBytes']);
        $mediaObject = $fixture['objectsByRole']['large-media'];
        $templateObject = $fixture['objectsByRole']['template'];

        $t->same(2, $index->packCount());
        $t->same(3, $index->count());
        $t->same($fixture['checksum'], $index->verifyIntegrityFast());
        $t->same($fixture['indexNames'], $index->packNames());

        $media = $index->lookup($mediaObject['oid']);
        $t->same($mediaObject['packIndex'], $media?->packIndex);
        $t->same($mediaObject['offset'], $media?->packOffset);
        $t->same('found', $index->lookupPrefix(substr($templateObject['oid'], 0, 8))['status']);

        $empty = MultiPackIndex::fromBytes($fixture['emptyMultiIndexBytes']);
        $emptyPrefix = $empty->lookupPrefix('0000');
        $t->same(0, $empty->count());
        $t->same('missing', $emptyPrefix['status']);
        $t->same(['start' => 0, 'end' => 0], $emptyPrefix['candidateRange']);
        $t->throws(RuntimeException::class, static fn () => $empty->verifyIntegrityFast());
    },
];
