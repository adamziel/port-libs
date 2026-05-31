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
    'reports ambiguous pack index prefixes' => static function (TestRunner $t) use ($buildIndex, $packChecksum): void {
        $index = PackIndex::fromBytes($buildIndex([
            ['oid' => '3b18e512dba79e4c8300dd08aeb37f8e728b8dad', 'offset' => 12, 'crc32' => 1],
            ['oid' => '3b18e5ffffffffffffffffffffffffffffffffff', 'offset' => 24, 'crc32' => 2],
        ], $packChecksum));
        $prefix = $index->lookupPrefix('3b18e5');
        $t->same('ambiguous', $prefix['status']);
        $t->same([0, 1], $prefix['matches']);
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
        $t->same(null, $index->disambiguatePrefix(str_repeat('f', 64), 4));
        $t->throws(InvalidArgumentException::class, static fn () => $index->lookup($first . '0'));
        $t->throws(InvalidArgumentException::class, static fn () => PackIndex::fromBytes('', 'blake3'));
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
        $t->same('sha1', $index->objectHash());
        $t->same(20, $index->hashBytes());
        $t->same(3, $index->count());
        $t->same($fixture['packChecksum'], $index->packChecksum());
        $t->same($fixture['objects'][1]['offset'], $index->lookup($fixture['objects'][1]['oid'])?->packOffset);
        $t->same($fixture['objects'][2]['offset'], $index->lookup($fixture['objects'][2]['oid'])?->packOffset);
    },
];
