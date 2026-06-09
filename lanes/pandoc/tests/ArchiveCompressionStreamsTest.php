<?php

declare(strict_types=1);

use PortLibs\Pandoc\ArchiveCompressionStreams;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));

/**
 * @param array{mtime?:int, extra?:string, name?:string, comment?:string, hcrc?:bool, os?:int} $options
 */
$buildGzipMember = static function (string $data, array $options = []) use ($crc32): string {
    $flags = 0;
    if (array_key_exists('extra', $options)) {
        $flags |= 0x04;
    }
    if (array_key_exists('name', $options)) {
        $flags |= 0x08;
    }
    if (array_key_exists('comment', $options)) {
        $flags |= 0x10;
    }
    if ($options['hcrc'] ?? false) {
        $flags |= 0x02;
    }

    $header = "\x1f\x8b\x08" . chr($flags) . pack('VCC', $options['mtime'] ?? 0, 0, $options['os'] ?? 255);
    if (array_key_exists('extra', $options)) {
        $extra = $options['extra'];
        $header .= pack('v', strlen($extra)) . $extra;
    }
    if (array_key_exists('name', $options)) {
        $header .= $options['name'] . "\0";
    }
    if (array_key_exists('comment', $options)) {
        $header .= $options['comment'] . "\0";
    }
    if ($options['hcrc'] ?? false) {
        $header .= pack('v', $crc32($header) & 0xffff);
    }

    return $header . gzdeflate($data) . pack('VV', $crc32($data), strlen($data) & 0xffffffff);
};

$tarString = static function (string $value, int $length): string {
    if (strlen($value) > $length) {
        throw new RuntimeException("Tar field is too long: {$value}");
    }

    return str_pad($value, $length, "\0");
};

$tarOctal = static function (int $value, int $length): string {
    return str_pad(decoct($value), $length - 1, '0', STR_PAD_LEFT) . "\0";
};

/**
 * @param list<array{name:string, data?:string, type?:string, mode?:int, mtime?:int, size?:int}> $entries
 */
$buildTarArchive = static function (array $entries) use ($tarString, $tarOctal): string {
    $archive = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $prefix = '';
        if (strlen($name) > 100) {
            $split = strrpos(substr($name, 0, 156), '/');
            if ($split === false) {
                throw new RuntimeException("Tar entry name is too long: {$name}");
            }
            $prefix = substr($name, 0, $split);
            $name = substr($name, $split + 1);
        }

        $data = $entry['data'] ?? '';
        $type = $entry['type'] ?? '0';
        $size = $entry['size'] ?? ($type === '5' ? 0 : strlen($data));
        $header = $tarString($name, 100)
            . $tarOctal($entry['mode'] ?? ($type === '5' ? 0755 : 0644), 8)
            . $tarOctal(0, 8)
            . $tarOctal(0, 8)
            . $tarOctal($size, 12)
            . $tarOctal($entry['mtime'] ?? 0, 12)
            . str_repeat(' ', 8)
            . $type
            . $tarString('', 100)
            . "ustar\0"
            . '00'
            . $tarString('wordpress', 32)
            . $tarString('wordpress', 32)
            . $tarString('', 8)
            . $tarString('', 8)
            . $tarString($prefix, 155)
            . $tarString('', 12);

        $checksum = 0;
        for ($offset = 0; $offset < strlen($header); $offset++) {
            $checksum += ord($header[$offset]);
        }
        $header = substr($header, 0, 148)
            . str_pad(decoct($checksum), 6, '0', STR_PAD_LEFT)
            . "\0 "
            . substr($header, 156);

        $paddingLength = $size === 0 ? 0 : (512 - ($size % 512)) % 512;
        $archive .= $header . ($size === 0 ? '' : substr($data, 0, $size)) . str_repeat("\0", $paddingLength);
    }

    return $archive . str_repeat("\0", 1024);
};

return [
    'decodes gzip members with header metadata and trailer integrity' => static function (TestRunner $t) use ($buildGzipMember, $crc32): void {
        $first = $buildGzipMember('first packet', [
            'mtime' => 1780479017,
            'extra' => 'WP',
            'name' => 'wordpress-import.tar',
            'comment' => 'review packet',
            'hcrc' => true,
            'os' => 3,
        ]);
        $second = $buildGzipMember('second packet');

        $members = ArchiveCompressionStreams::gzipMembers($first . $second);

        $t->same(2, count($members));
        $t->same(0, $members[0]['offset']);
        $t->true($members[0]['compressedOffset'] > 10);
        $t->true($members[0]['compressedSize'] > 0);
        $t->same(0x1e, $members[0]['flags']);
        $t->same(1780479017, $members[0]['modifiedAt']);
        $t->same('WP', $members[0]['extra']);
        $t->same('wordpress-import.tar', $members[0]['originalName']);
        $t->same('review packet', $members[0]['comment']);
        $t->same(3, $members[0]['os']);
        $t->same($crc32('first packet'), $members[0]['crc32']);
        $t->same(strlen('first packet'), $members[0]['isize']);
        $t->same('first packet', $members[0]['data']);
        $t->same(null, $members[1]['modifiedAt']);
        $t->same(null, $members[1]['originalName']);
        $t->same(null, $members[1]['comment']);
        $t->same('second packet', $members[1]['data']);
        $t->same('first packetsecond packet', ArchiveCompressionStreams::gzipDecode($first . $second));

        $badReservedFlags = substr_replace($first, chr(ord($first[3]) | 0xe0), 3, 1);
        $badTrailerCrc = substr_replace($first, "\0\0\0\0", -8, 4);
        $badTrailerSize = substr_replace($first, pack('V', 999), -4, 4);
        $badSignature = 'xx' . substr($first, 2);

        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::gzipMembers(''));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::gzipMembers($badSignature));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::gzipMembers($badReservedFlags));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::gzipMembers($badTrailerCrc));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::gzipMembers($badTrailerSize));
    },

    'ignores gzip trailing nul padding after valid archive members' => static function (TestRunner $t) use ($buildGzipMember): void {
        $member = $buildGzipMember('archive body', [
            'name' => 'padded-review-packet.tar',
            'comment' => 'null padded stream',
            'mtime' => 1780479044,
        ]);
        $padded = $member . str_repeat("\0", 12);

        $members = ArchiveCompressionStreams::gzipMembers($padded);

        $t->same(1, count($members));
        $t->same('archive body', $members[0]['data']);
        $t->same('padded-review-packet.tar', $members[0]['originalName']);
        $t->same(1780479044, $members[0]['modifiedAt']);
        $t->same(strlen($member), $members[0]['compressedOffset'] + $members[0]['compressedSize'] + 8);
        $t->same('archive body', ArchiveCompressionStreams::gzipDecode($padded));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::gzipMembers(str_repeat("\0", 8)));
        $t->throws(
            RuntimeException::class,
            static fn (): string => ArchiveCompressionStreams::gzipDecode($member . "\0review-trailer")
        );
    },

    'extracts gzip-compressed tar entries for wordpress import packets' => static function (TestRunner $t) use ($buildGzipMember, $buildTarArchive): void {
        $longMediaPath = 'content/media/uploads/2026/06/' . str_repeat('source-', 8) . 'hero.txt';
        $tar = $buildTarArchive([
            [
                'name' => 'content/',
                'type' => '5',
                'mode' => 0755,
                'mtime' => 1780479000,
            ],
            [
                'name' => 'content/post.md',
                'data' => "# Imported Packet\n\nA paragraph from a WordPress migration archive.\n",
                'mode' => 0644,
                'mtime' => 1780479017,
            ],
            [
                'name' => $longMediaPath,
                'data' => 'hero image placeholder',
                'mode' => 0600,
                'mtime' => 1780479020,
            ],
        ]);
        $entries = ArchiveCompressionStreams::tarGzipEntries($buildGzipMember($tar, [
            'name' => 'wordpress-import.tar',
            'mtime' => 1780479021,
        ]));
        $files = ArchiveCompressionStreams::tarGzipFiles($buildGzipMember($tar));

        $t->same(3, count($entries));
        $t->same('content/', $entries[0]['name']);
        $t->same('directory', $entries[0]['type']);
        $t->same(0, $entries[0]['size']);
        $t->same('', $entries[0]['data']);
        $t->same(0755, $entries[0]['mode']);
        $t->same(1780479000, $entries[0]['modifiedAt']);
        $t->same('content/post.md', $entries[1]['name']);
        $t->same('file', $entries[1]['type']);
        $t->same(0644, $entries[1]['mode']);
        $t->same(1780479017, $entries[1]['modifiedAt']);
        $t->same("# Imported Packet\n\nA paragraph from a WordPress migration archive.\n", $entries[1]['data']);
        $t->same($longMediaPath, $entries[2]['name']);
        $t->same(0600, $entries[2]['mode']);
        $t->same('hero image placeholder', $entries[2]['data']);
        $t->same([
            'content/post.md' => "# Imported Packet\n\nA paragraph from a WordPress migration archive.\n",
            $longMediaPath => 'hero image placeholder',
        ], $files);
    },

    'rejects unsafe malformed and unsupported tar streams' => static function (TestRunner $t) use ($buildTarArchive): void {
        $unsafe = $buildTarArchive([
            ['name' => '../post.md', 'data' => 'escape'],
        ]);
        $directoryWithData = $buildTarArchive([
            ['name' => 'content/', 'type' => '5', 'data' => 'not empty', 'size' => 9],
        ]);
        $symlink = $buildTarArchive([
            ['name' => 'content/link', 'type' => '2', 'data' => 'target'],
        ]);
        $checksumMismatch = substr_replace($buildTarArchive([
            ['name' => 'content/post.md', 'data' => 'ok'],
        ]), 'X', 0, 1);
        $truncated = substr($buildTarArchive([
            ['name' => 'content/post.md', 'data' => str_repeat('x', 700)],
        ]), 0, 512 + 700);
        $missingEnd = substr($buildTarArchive([
            ['name' => 'content/post.md', 'data' => 'ok'],
        ]), 0, -1024);
        $singleZeroBlockEnd = substr($buildTarArchive([
            ['name' => 'content/post.md', 'data' => 'ok'],
        ]), 0, -512);
        $trailingGarbage = $buildTarArchive([
            ['name' => 'content/post.md', 'data' => 'ok'],
        ]) . 'garbage';

        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::tarEntries(''));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::tarEntries($unsafe));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::tarEntries($directoryWithData));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::tarEntries($symlink));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::tarEntries($checksumMismatch));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::tarEntries($truncated));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::tarEntries($missingEnd));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::tarEntries($singleZeroBlockEnd));
        $t->throws(RuntimeException::class, static fn (): array => ArchiveCompressionStreams::tarEntries($trailingGarbage));
    },
];
