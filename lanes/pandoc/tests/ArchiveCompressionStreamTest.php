<?php

declare(strict_types=1);

use PortLibs\Pandoc\ArchiveCompressionStream;
use PortLibs\Pandoc\DeflateStream;
use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\Lz4Frame;
use PortLibs\Pandoc\TarArchive;
use PortLibs\Pandoc\TarArchiveEntry;
use PortLibs\Pandoc\ZipPackage;

$rawTarHeader = static function (string $name, string $typeFlag, string $data = '', int $modifiedAt = 0, bool $withEndMarker = true, ?int $headerSize = null): string {
    $octal = static function (int $value, int $length): string {
        return str_pad(decoct($value), $length - 1, '0', STR_PAD_LEFT) . "\0";
    };
    $field = static fn (string $value, int $length): string => str_pad($value, $length, "\0");
    $headerSize ??= strlen($data);

    $header = $field($name, 100)
        . $octal(0644, 8)
        . $octal(0, 8)
        . $octal(0, 8)
        . $octal($headerSize, 12)
        . $octal($modifiedAt, 12)
        . str_repeat(' ', 8)
        . $typeFlag
        . $field('', 100)
        . "ustar\0"
        . '00'
        . $field('', 32)
        . $field('', 32)
        . $octal(0, 8)
        . $octal(0, 8)
        . $field('', 155)
        . str_repeat("\0", 12);

    $checksum = 0;
    for ($index = 0; $index < strlen($header); $index++) {
        $checksum += ord($header[$index]);
    }

    $header = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);
    $padding = strlen($data) % 512 === 0 ? '' : str_repeat("\0", 512 - (strlen($data) % 512));

    return $header . $data . $padding . ($withEndMarker ? str_repeat("\0", 1024) : '');
};

$base256TarField = static function (int $value, int $length): string {
    if ($value < 0) {
        throw new RuntimeException('base-256 TAR fixture values must be non-negative');
    }

    $field = str_repeat("\0", $length);
    for ($index = $length - 1; $index >= 0 && $value > 0; $index--) {
        $field[$index] = chr($value & 0xff);
        $value = intdiv($value, 256);
    }

    if ($value !== 0) {
        throw new RuntimeException('base-256 TAR fixture value is too large');
    }

    $field[0] = chr(ord($field[0]) | 0x80);

    return $field;
};

$rewriteTarHeaderFields = static function (string $archive, array $fields): string {
    $header = substr($archive, 0, 512);
    foreach ($fields as $offset => $field) {
        $header = substr_replace($header, $field, (int) $offset, strlen($field));
    }

    $header = substr_replace($header, str_repeat(' ', 8), 148, 8);
    $checksum = 0;
    for ($index = 0; $index < strlen($header); $index++) {
        $checksum += ord($header[$index]);
    }

    $header = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);

    return $header . substr($archive, 512);
};

$rewriteTarHeaderWithSignedChecksum = static function (string $archive): string {
    $header = substr_replace(substr($archive, 0, 512), str_repeat(' ', 8), 148, 8);
    $checksum = 0;
    for ($index = 0; $index < strlen($header); $index++) {
        $byte = ord($header[$index]);
        $checksum += $byte < 128 ? $byte : $byte - 256;
    }

    $header = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);

    return $header . substr($archive, 512);
};

$paxPayload = static function (array $headers): string {
    $payload = '';
    foreach ($headers as $key => $value) {
        $body = " {$key}={$value}\n";
        $recordLength = strlen($body) + 1;
        do {
            $nextLength = strlen((string) $recordLength) + strlen($body);
            if ($nextLength === $recordLength) {
                $payload .= $recordLength . $body;
                break;
            }
            $recordLength = $nextLength;
        } while (true);
    }

    return $payload;
};

$zlibDictionaryStream = static function (string $dictionary, string $payload): string {
    $context = deflate_init(ZLIB_ENCODING_DEFLATE, ['dictionary' => $dictionary]);
    if ($context === false) {
        throw new RuntimeException('Unable to initialize zlib preset-dictionary fixture');
    }

    $encoded = deflate_add($context, $payload, ZLIB_FINISH);
    if ($encoded === false) {
        throw new RuntimeException('Unable to build zlib preset-dictionary fixture');
    }

    return $encoded;
};

$lz4HeaderChecksum = static fn (string $descriptor): string => chr((intval(hash('xxh32', $descriptor), 16) >> 8) & 0xff);
$lz4DictionaryMatchBlock = static function (string $dictionary, string $tail): string {
    $matchLength = strlen($dictionary);
    if ($matchLength < 19) {
        throw new RuntimeException('LZ4 dictionary fixture must be at least 19 bytes');
    }
    if (strlen($tail) > 14) {
        throw new RuntimeException('LZ4 dictionary fixture tail must fit in one literal token');
    }

    return chr(0x0f)
        . pack('v', $matchLength)
        . chr($matchLength - 19)
        . chr(strlen($tail) << 4)
        . $tail;
};
$lz4DictionaryCompressedFrame = static function (
    int $dictionaryId,
    string $decodedPayload,
    array $rawBlocks,
    bool $blockIndependent = true
) use ($lz4HeaderChecksum): string {
    $descriptor = chr(0x40 | ($blockIndependent ? 0x20 : 0x00) | 0x10 | 0x08 | 0x04 | 0x01)
        . chr(0x40)
        . pack('V2', strlen($decodedPayload), 0)
        . pack('V', $dictionaryId);
    $frame = pack('V', 0x184d2204)
        . $descriptor
        . $lz4HeaderChecksum($descriptor);

    foreach ($rawBlocks as $rawBlock) {
        if (!is_string($rawBlock)) {
            throw new RuntimeException('LZ4 dictionary fixture blocks must be byte strings');
        }
        $frame .= pack('V', strlen($rawBlock))
            . $rawBlock
            . pack('V', intval(hash('xxh32', $rawBlock), 16));
    }

    return $frame
        . pack('V', 0)
        . pack('V', intval(hash('xxh32', $decodedPayload), 16));
};

$zipFixtureBytes = static function (array $entries, string $packageComment = ''): string {
    $body = '';
    $centralDirectory = '';
    foreach ($entries as $entry) {
        $name = (string) $entry['name'];
        $data = (string) ($entry['data'] ?? '');
        $method = (int) ($entry['compressionMethod'] ?? 0);
        $flags = (int) ($entry['flags'] ?? 0);
        $localFlags = (int) ($entry['localFlags'] ?? $flags);
        $centralExtra = (string) ($entry['centralExtra'] ?? $entry['extra'] ?? '');
        $localExtra = (string) ($entry['localExtra'] ?? $entry['extra'] ?? '');
        $comment = (string) ($entry['comment'] ?? '');
        $payload = $method === 8 ? gzdeflate($data) : $data;
        $crc32 = (int) sprintf('%u', crc32($data));
        $localHeaderOffset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $localFlags,
            $method,
            0,
            0,
            $crc32,
            strlen($payload),
            strlen($data),
            strlen($name),
            strlen($localExtra)
        ) . $name . $localExtra . $payload;

        $centralDirectory .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc32,
            strlen($payload),
            strlen($data),
            strlen($name),
            strlen($centralExtra),
            strlen($comment),
            0,
            0,
            (int) ($entry['externalAttributes'] ?? 0),
            $localHeaderOffset
        ) . $name . $centralExtra . $comment;
    }

    return $body
        . $centralDirectory
        . pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($entries),
            count($entries),
            strlen($centralDirectory),
            strlen($body),
            strlen($packageComment)
        )
        . $packageComment;
};

return [
    'builds and reads bounded tar package fixture entries' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/',
                'type' => TarArchiveEntry::TYPE_DIRECTORY,
                'modifiedAt' => 1780479016,
                'mode' => 0755,
            ],
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"wordpress-import","format":"docx"}',
                'modifiedAt' => 1780479017,
                'mode' => 0640,
                'userName' => 'wp',
                'groupName' => 'import',
            ],
            [
                'name' => 'packet/word/document.xml',
                'data' => '<w:document><w:p>Tar-backed import source</w:p></w:document>',
                'modifiedAt' => 1780479018,
            ],
        ]);

        $roundTrip = TarArchive::fromString($archive->bytes());
        $manifest = $roundTrip->entry('packet/manifest.json');

        $t->same([
            'packet/',
            'packet/manifest.json',
            'packet/word/document.xml',
        ], $roundTrip->names());
        $t->true($roundTrip->entry('packet/')->isDirectory());
        $t->same('', $roundTrip->read('packet/'));
        $t->true($manifest->isRegularFile());
        $t->same(0640, $manifest->mode);
        $t->same(1780479017, $manifest->modifiedAt);
        $t->same('wp', $manifest->userName);
        $t->same('import', $manifest->groupName);
        $t->same('{"source":"wordpress-import","format":"docx"}', $roundTrip->read('/packet/manifest.json'));
        $t->same('<w:document><w:p>Tar-backed import source</w:p></w:document>', $roundTrip->read('packet/word/document.xml'));
        $t->same($archive->bytes(), $roundTrip->bytes());
    },

    'preserves ustar prefix and pax long path metadata' => static function (TestRunner $t): void {
        $ustarName = 'packet/' . str_repeat('nested/', 12) . 'document.xml';
        $paxName = 'packet/' . str_repeat('review-', 18) . 'document.xml';
        $archive = TarArchive::fromEntries([
            [
                'name' => $ustarName,
                'data' => '<w:document><w:p>ustar prefix path</w:p></w:document>',
                'modifiedAt' => 1780479019,
            ],
            [
                'name' => $paxName,
                'data' => '<w:document><w:p>pax path metadata</w:p></w:document>',
                'modifiedAt' => 1780479020,
            ],
        ]);

        $roundTrip = TarArchive::fromString($archive->bytes());
        $paxEntry = $roundTrip->entry($paxName);
        $inspection = ArchiveCompressionStream::inspectTarStream(
            $archive->bytes(),
            ArchiveCompressionStream::FORMAT_TAR,
            strlen($archive->bytes())
        );

        $t->true(strlen($ustarName) > 100);
        $t->true(strlen($paxName) > 100);
        $t->same($ustarName, $roundTrip->entry($ustarName)->name);
        $t->same($paxName, $paxEntry->name);
        $t->same($paxName, $paxEntry->paxHeaders['path'] ?? null);
        $t->same('ustar-prefix', $roundTrip->entry($ustarName)->nameSource);
        $t->same('pax-path', $paxEntry->nameSource);
        $t->same([], $paxEntry->globalPaxHeaders);
        $t->same(['path' => $paxName], $paxEntry->localPaxHeaders);
        $t->same([], $paxEntry->deletedPaxHeaderKeys);
        $t->same('ustar-prefix', $inspection['entryLayouts'][0]['nameSource']);
        $t->same('pax-path', $inspection['entryLayouts'][1]['nameSource']);
        $t->same(['path'], $inspection['entryLayouts'][1]['paxLocalHeaderKeys']);
        $t->same(1780479020, $paxEntry->modifiedAt);
        $t->same('<w:document><w:p>ustar prefix path</w:p></w:document>', $roundTrip->read($ustarName));
        $t->same('<w:document><w:p>pax path metadata</w:p></w:document>', $roundTrip->read('/' . $paxName));
    },

    'reads pax size and owner metadata for tar package fixture entries' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $documentName = 'packet/pax/document.xml';
        $documentBytes = '<w:document><w:body><w:p>PAX metadata tar source</w:p></w:body></w:document>';
        $extendedHeaders = $paxPayload([
            'path' => $documentName,
            'size' => (string) strlen($documentBytes),
            'mtime' => '1780479028.75',
            'uid' => '1001',
            'gid' => '1002',
            'uname' => 'wp-reviewer',
            'gname' => 'import-team',
        ]);
        $archive = $rawTarHeader('PaxHeaders/pax-document', 'x', $extendedHeaders, 0, false)
            . $rawTarHeader('placeholder.xml', '0', $documentBytes, 0, false, 0)
            . str_repeat("\0", 1024);

        $roundTrip = TarArchive::fromString($archive);
        $entry = $roundTrip->entry($documentName);

        $t->same([$documentName], $roundTrip->names());
        $t->true($entry->isRegularFile());
        $t->same(strlen($documentBytes), $entry->size);
        $t->same(1780479028, $entry->modifiedAt);
        $t->same(1001, $entry->uid);
        $t->same(1002, $entry->gid);
        $t->same('wp-reviewer', $entry->userName);
        $t->same('import-team', $entry->groupName);
        $t->same($documentName, $entry->paxHeaders['path'] ?? null);
        $t->same((string) strlen($documentBytes), $entry->paxHeaders['size'] ?? null);
        $t->same($documentBytes, $roundTrip->read('/' . $documentName));
    },

    'reads pax access and change timestamp metadata for tar package fixture entries' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $documentName = 'packet/pax/timestamps.xml';
        $documentBytes = '<w:document><w:body><w:p>PAX timestamp provenance</w:p></w:body></w:document>';
        $archive = $rawTarHeader('PaxHeaders/timestamps', 'x', $paxPayload([
            'path' => $documentName,
            'size' => (string) strlen($documentBytes),
            'mtime' => '1780479049.25',
            'atime' => '1780479050.50',
            'ctime' => '1780479051.75',
        ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', $documentBytes, 0, false, 0)
            . str_repeat("\0", 1024);
        $globalArchive = $rawTarHeader('GlobalHead/timestamps', 'g', $paxPayload([
            'atime' => '1780479052',
            'ctime' => '1780479053.99',
        ]), 0, false)
            . $rawTarHeader('packet/pax/global-timestamps.xml', '0', '<w:document/>', 1780479054, false)
            . str_repeat("\0", 1024);

        $entry = TarArchive::fromString($archive)->entry($documentName);
        $globalEntry = TarArchive::fromString($globalArchive)->entry('/packet/pax/global-timestamps.xml');
        $generated = TarArchive::fromEntries([
            [
                'name' => 'packet/generated-timestamps.xml',
                'data' => '<w:document><w:p>generated PAX timestamps</w:p></w:document>',
                'modifiedAt' => 1780479055,
                'accessedAt' => 1780479056,
                'changedAt' => 1780479057,
            ],
        ]);
        $generatedEntry = $generated->entry('/packet/generated-timestamps.xml');

        $t->same(1780479049, $entry->modifiedAt);
        $t->same(1780479050, $entry->accessedAt);
        $t->same(1780479051, $entry->changedAt);
        $t->same('1780479050.50', $entry->paxHeaders['atime'] ?? null);
        $t->same('1780479051.75', $entry->paxHeaders['ctime'] ?? null);
        $t->same($documentBytes, TarArchive::fromString($archive)->read('/' . $documentName));
        $t->same(1780479052, $globalEntry->accessedAt);
        $t->same(1780479053, $globalEntry->changedAt);
        $t->same(1780479054, $globalEntry->modifiedAt);
        $t->same(1780479056, $generatedEntry->accessedAt);
        $t->same(1780479057, $generatedEntry->changedAt);
        $t->same('1780479056', $generatedEntry->paxHeaders['atime'] ?? null);
        $t->same('1780479057', $generatedEntry->paxHeaders['ctime'] ?? null);
        $t->same('<w:document><w:p>generated PAX timestamps</w:p></w:document>', $generated->read('/packet/generated-timestamps.xml'));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString(
            $rawTarHeader('PaxHeaders/overflow-atime', 'x', $paxPayload([
                'path' => 'packet/pax/overflow-atime.xml',
                'atime' => (string) PHP_INT_MAX . '0.25',
            ]), 0, false)
            . $rawTarHeader('placeholder-atime.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024)
        ));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString(
            $rawTarHeader('PaxHeaders/overflow-ctime', 'x', $paxPayload([
                'path' => 'packet/pax/overflow-ctime.xml',
                'ctime' => (string) PHP_INT_MAX . '0.25',
            ]), 0, false)
            . $rawTarHeader('placeholder-ctime.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024)
        ));
    },

    'rejects overflowing pax mtime metadata before package exposure' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $documentBytes = '<w:document><w:body><w:p>Overflowing PAX mtime source</w:p></w:body></w:document>';
        $tooLargeTimestamp = (string) PHP_INT_MAX . '0.25';
        $overflowingMtime = $rawTarHeader('PaxHeaders/overflow-mtime', 'x', $paxPayload([
            'path' => 'packet/overflow-mtime.xml',
            'size' => (string) strlen($documentBytes),
            'mtime' => $tooLargeTimestamp,
        ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', $documentBytes, 0, false, 0)
            . str_repeat("\0", 1024);
        $overflowingGlobalMtime = $rawTarHeader('GlobalHead/overflow-mtime', 'g', $paxPayload([
            'mtime' => $tooLargeTimestamp,
        ]), 0, false)
            . $rawTarHeader('packet/global-overflow-mtime.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $validFractionalMtime = $rawTarHeader('PaxHeaders/fractional-mtime', 'x', $paxPayload([
            'path' => 'packet/fractional-mtime.xml',
            'size' => (string) strlen($documentBytes),
            'mtime' => '1780479048.75',
        ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', $documentBytes, 0, false, 0)
            . str_repeat("\0", 1024);

        $entry = TarArchive::fromString($validFractionalMtime)->entry('/packet/fractional-mtime.xml');

        $t->same(1780479048, $entry->modifiedAt);
        $t->same('1780479048.75', $entry->paxHeaders['mtime'] ?? null);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($overflowingMtime));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($overflowingGlobalMtime));
    },

    'rejects invalid utf8 tar owner metadata before package exposure' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload, $rewriteTarHeaderFields): void {
        $invalidUserName = $rewriteTarHeaderFields(
            $rawTarHeader('packet/invalid-user.xml', '0', '<w:document/>'),
            [
                265 => str_pad("reviewer-\xC3\x28", 32, "\0"),
            ]
        );
        $invalidGroupName = $rewriteTarHeaderFields(
            $rawTarHeader('packet/invalid-group.xml', '0', '<w:document/>'),
            [
                297 => str_pad("import-\xC3\x28", 32, "\0"),
            ]
        );
        $invalidPaxUserName = $rawTarHeader('PaxHeaders/invalid-uname', 'x', $paxPayload([
            'path' => 'packet/pax-invalid-user.xml',
            'uname' => "reviewer-\xC3\x28",
        ]), 0, false)
            . $rawTarHeader('placeholder-user.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $invalidPaxGroupName = $rawTarHeader('PaxHeaders/invalid-gname', 'x', $paxPayload([
            'path' => 'packet/pax-invalid-group.xml',
            'gname' => "import-\xC3\x28",
        ]), 0, false)
            . $rawTarHeader('placeholder-group.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidUserName));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidGroupName));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidPaxUserName));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidPaxGroupName));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            ['name' => 'packet/generated-invalid-user.xml', 'data' => '<w:document/>', 'userName' => "reviewer-\xC3\x28"],
        ]));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            ['name' => 'packet/generated-invalid-group.xml', 'data' => '<w:document/>', 'groupName' => "import-\xC3\x28"],
        ]));
    },

    'rejects invalid utf8 pax review metadata before package exposure' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $validReviewMetadata = $rawTarHeader('PaxHeaders/valid-review', 'x', $paxPayload([
            'path' => 'packet/review-metadata.xml',
            'comment' => "caf\u{00E9} tar review metadata",
            'org.wordpress.import.review' => 'ready',
        ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $invalidGlobalComment = $rawTarHeader('GlobalHead/invalid-comment', 'g', $paxPayload([
            'comment' => "bad-\xC3\x28",
        ]), 0, false)
            . $rawTarHeader('packet/document.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $invalidLocalComment = $rawTarHeader('PaxHeaders/invalid-comment', 'x', $paxPayload([
            'path' => 'packet/invalid-comment.xml',
            'comment' => "bad-\xC3\x28",
        ]), 0, false)
            . $rawTarHeader('placeholder-comment.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $invalidLocalKey = $rawTarHeader('PaxHeaders/invalid-key', 'x', $paxPayload([
            'path' => 'packet/invalid-key.xml',
            "review-\xC3\x28" => 'bad-key',
        ]), 0, false)
            . $rawTarHeader('placeholder-key.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $entry = TarArchive::fromString($validReviewMetadata)->entry('/packet/review-metadata.xml');

        $t->same("caf\u{00E9} tar review metadata", $entry->paxHeaders['comment'] ?? null);
        $t->same('ready', $entry->paxHeaders['org.wordpress.import.review'] ?? null);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidGlobalComment));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidLocalComment));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidLocalKey));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            ['name' => 'packet/generated-invalid-pax.xml', 'data' => '<w:document/>'],
        ], [
            'globalPaxHeaders' => [
                'comment' => "bad-\xC3\x28",
            ],
        ]));
    },

    'rejects duplicate pax keyword metadata before package bytes are exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $duplicatePath = $rawTarHeader(
            'PaxHeaders/duplicate-path',
            'x',
            $paxPayload(['path' => 'packet/first.xml'])
                . $paxPayload(['path' => 'packet/second.xml']),
            0,
            false
        )
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $duplicateSize = $rawTarHeader(
            'PaxHeaders/duplicate-size',
            'x',
            $paxPayload([
                'path' => 'packet/duplicate-size.xml',
                'size' => '13',
            ]) . $paxPayload(['size' => '13']),
            0,
            false
        )
            . $rawTarHeader('placeholder-size.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $duplicateGlobalComment = $rawTarHeader(
            'GlobalHead/duplicate-comment',
            'g',
            $paxPayload(['comment' => 'first review note'])
                . $paxPayload(['comment' => 'second review note']),
            0,
            false
        )
            . $rawTarHeader('packet/document.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($duplicatePath));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($duplicateSize));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($duplicateGlobalComment));
    },

    'preflights duplicate pax keyword policy without exposing package bytes' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $globalDocumentBytes = "# Global duplicate PAX packet\n\nReady for WordPress archive review.\n";
        $localDocumentBytes = "# Local duplicate PAX packet\n\nReady for WordPress archive review.\n";
        $archiveBytes = $rawTarHeader('GlobalHead/duplicate-review', 'g', $paxPayload([
            'comment' => 'first global review comment',
            'hdrcharset' => 'BINARY',
        ]) . $paxPayload([
            'comment' => 'second global review comment',
        ]), 0, false)
            . $rawTarHeader('packet/global-duplicate.md', '0', $globalDocumentBytes, 1780479085, false)
            . $rawTarHeader('PaxHeaders/duplicate-local', 'x', $paxPayload([
                'path' => 'packet/local-duplicate.md',
                'org.wordpress.import.review' => 'first local review state',
            ]) . $paxPayload([
                'org.wordpress.import.review' => 'second local review state',
                'size' => (string) strlen($localDocumentBytes),
            ]), 0, false)
            . $rawTarHeader('placeholder-local.md', '0', $localDocumentBytes, 1780479086, false, 0)
            . str_repeat("\0", 1024);
        $gzip = GzipStream::build($archiveBytes, [
            'filename' => 'wordpress-duplicate-pax.tar',
            'comment' => 'duplicate PAX keywords stay blocked for extraction',
        ]);
        $cleanPolicy = TarArchive::paxDuplicateKeywordPreflight(TarArchive::build([
            ['name' => 'packet/clean.md', 'data' => 'single PAX metadata'],
        ], [
            'globalPaxHeaders' => [
                'comment' => 'single archive review comment',
            ],
        ]));

        $policy = TarArchive::paxDuplicateKeywordPreflight($archiveBytes);
        $streamPolicy = ArchiveCompressionStream::inspectTarPaxDuplicateKeywordPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes)
        );

        $t->same('no-duplicate-pax-keywords', $cleanPolicy['extractionPolicy']);
        $t->same(0, $cleanPolicy['duplicateKeywordCount']);
        $t->same(2, $policy['entryCount']);
        $t->same(2, $policy['paxEntryCount']);
        $t->same(2, $policy['duplicatePaxEntryCount']);
        $t->same(2, $policy['duplicateKeywordCount']);
        $t->same(2, $policy['duplicateRecordCount']);
        $t->same('duplicate-pax-keywords-blocked', $policy['extractionPolicy']);
        $t->same('GlobalHead/duplicate-review', $policy['entries'][0]['paxEntryName']);
        $t->same('global', $policy['entries'][0]['paxType']);
        $t->same(['comment'], $policy['entries'][0]['duplicateKeywords']);
        $t->same(3, $policy['entries'][0]['recordCount']);
        $t->same([
            'keyword' => 'comment',
            'occurrences' => 2,
            'values' => ['first global review comment', 'second global review comment'],
            'firstValue' => 'first global review comment',
            'duplicateValues' => ['second global review comment'],
        ], $policy['entries'][0]['duplicateRecords'][0]);
        $t->same('PaxHeaders/duplicate-local', $policy['entries'][1]['paxEntryName']);
        $t->same('local', $policy['entries'][1]['paxType']);
        $t->same(['org.wordpress.import.review'], $policy['entries'][1]['duplicateKeywords']);
        $t->same(4, $policy['entries'][1]['recordCount']);
        $t->same([
            'keyword' => 'org.wordpress.import.review',
            'occurrences' => 2,
            'values' => ['first local review state', 'second local review state'],
            'firstValue' => 'first local review state',
            'duplicateValues' => ['second local review state'],
        ], $policy['entries'][1]['duplicateRecords'][0]);
        $t->same(['tar-pax-duplicate-keyword-not-extracted'], $policy['entries'][1]['diagnostics']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $streamPolicy['format']);
        $t->same(strlen($archiveBytes), $streamPolicy['uncompressedSize']);
        $t->same('gzip', $streamPolicy['stream']['type']);
        $t->same('wordpress-duplicate-pax.tar', $streamPolicy['stream']['members'][0]['filename']);
        $t->same('duplicate PAX keywords stay blocked for extraction', $streamPolicy['stream']['members'][0]['comment']);
        $t->same($policy['entries'], $streamPolicy['entries']);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($archiveBytes));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectTarPaxDuplicateKeywordPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes) - 1
        ));
    },

    'builds and reads global pax metadata for tar review packets' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"global-pax","target":"wordpress"}',
            ],
            [
                'name' => 'packet/word/document.xml',
                'data' => '<w:document><w:body><w:p>Global PAX tar review metadata</w:p></w:body></w:document>',
            ],
        ], [
            'globalPaxHeaders' => [
                'comment' => 'wordpress import review packet',
                'hdrcharset' => 'BINARY',
                'LIBARCHIVE.creationtime' => '1780479036',
            ],
        ]);
        $roundTrip = TarArchive::fromString($archive->bytes());
        $manifest = $roundTrip->entry('/packet/manifest.json');
        $document = $roundTrip->entry('/packet/word/document.xml');

        $t->same([
            'comment' => 'wordpress import review packet',
            'hdrcharset' => 'BINARY',
            'LIBARCHIVE.creationtime' => '1780479036',
        ], $roundTrip->globalPaxHeaders());
        $t->same('wordpress import review packet', $manifest->paxHeaders['comment'] ?? null);
        $t->same('BINARY', $document->paxHeaders['hdrcharset'] ?? null);
        $t->same('{"source":"global-pax","target":"wordpress"}', $roundTrip->read('/packet/manifest.json'));
        $t->same('<w:document><w:body><w:p>Global PAX tar review metadata</w:p></w:body></w:document>', $roundTrip->read('/packet/word/document.xml'));
    },

    'preflights pax filesystem metadata without applying xattrs or acls' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $localContent = "# Filesystem metadata packet\n\nReady for WordPress review.\n";
        $inheritedContent = "# Inherited filesystem metadata packet\n\nReady for WordPress review.\n";
        $archive = $rawTarHeader('GlobalHead/filesystem', 'g', $paxPayload([
            'SCHILY.xattr.user.review' => 'global-review',
            'SCHILY.acl.access' => "user::rw-\ngroup::r--\nother::---",
            'SCHILY.fflags' => 'archived,nodump',
        ]), 0, false)
            . $rawTarHeader('PaxHeaders/local-filesystem', 'x', $paxPayload([
                'path' => 'packet/filesystem/local.md',
                'SCHILY.xattr.user.review' => '',
                'LIBARCHIVE.xattr.user.wordpress-source' => 'post-42',
            ]), 0, false)
            . $rawTarHeader('placeholder-local.md', '0', $localContent, 1780479084, false)
            . $rawTarHeader('packet/filesystem/inherited.md', '0', $inheritedContent, 1780479085, false)
            . str_repeat("\0", 1024);
        $gzip = GzipStream::build($archive, [
            'filename' => 'wordpress-pax-filesystem-metadata.tar',
            'comment' => 'PAX xattr and ACL policy preflight',
        ]);

        $policy = TarArchive::paxFilesystemMetadataPolicyPreflight($archive);
        $inspection = ArchiveCompressionStream::inspectTarFilesystemMetadataPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archive)
        );
        $roundTrip = TarArchive::fromString($archive);

        $t->same('filesystem-pax-metadata-not-applied', $policy['extractionPolicy']);
        $t->same(2, $policy['entryCount']);
        $t->same(2, $policy['filesystemMetadataEntryCount']);
        $t->same(6, $policy['metadataRecordCount']);
        $t->same(2, $policy['extendedAttributeRecordCount']);
        $t->same(2, $policy['accessControlListRecordCount']);
        $t->same(2, $policy['fileFlagRecordCount']);
        $t->same('packet/filesystem/local.md', $policy['entries'][0]['name']);
        $t->same('pax-path', $policy['entries'][0]['nameSource']);
        $t->same([
            'SCHILY.acl.access',
            'SCHILY.fflags',
            'LIBARCHIVE.xattr.user.wordpress-source',
        ], $policy['entries'][0]['metadataKeys']);
        $t->same(['global-pax', 'global-pax', 'local-pax'], array_column($policy['entries'][0]['records'], 'source'));
        $t->same(['access-control-list', 'file-flags', 'extended-attribute'], array_column($policy['entries'][0]['records'], 'category'));
        $t->same(['access', 'SCHILY.fflags', 'user.wordpress-source'], array_column($policy['entries'][0]['records'], 'name'));
        $t->same(['tar-pax-filesystem-metadata-not-applied'], $policy['entries'][0]['diagnostics']);
        $t->same('packet/filesystem/inherited.md', $policy['entries'][1]['name']);
        $t->same([
            'SCHILY.xattr.user.review',
            'SCHILY.acl.access',
            'SCHILY.fflags',
        ], $policy['entries'][1]['metadataKeys']);
        $t->same(['global-pax', 'global-pax', 'global-pax'], array_column($policy['entries'][1]['records'], 'source'));
        $t->same(['user.review', 'access', 'SCHILY.fflags'], array_column($policy['entries'][1]['records'], 'name'));
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $inspection['format']);
        $t->same('filesystem-pax-metadata-not-applied', $inspection['extractionPolicy']);
        $t->same(2, $inspection['filesystemMetadataEntryCount']);
        $t->same(6, $inspection['metadataRecordCount']);
        $t->same('gzip', $inspection['stream']['type']);
        $t->same('wordpress-pax-filesystem-metadata.tar', $inspection['stream']['members'][0]['filename']);
        $t->same($localContent, $roundTrip->read('/packet/filesystem/local.md'));
        $t->same($inheritedContent, $roundTrip->read('/packet/filesystem/inherited.md'));
        $t->same(false, isset($roundTrip->entry('/packet/filesystem/local.md')->paxHeaders['SCHILY.xattr.user.review']));
        $t->same('post-42', $roundTrip->entry('/packet/filesystem/local.md')->paxHeaders['LIBARCHIVE.xattr.user.wordpress-source'] ?? null);
        $t->same('global-review', $roundTrip->entry('/packet/filesystem/inherited.md')->paxHeaders['SCHILY.xattr.user.review'] ?? null);
    },

    'enforces pax header charset policy before package exposure' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $documentBytes = "# PAX header charset packet\n\nReady for WordPress archive review.\n";
        $archive = $rawTarHeader('GlobalHead/charset', 'g', $paxPayload([
            'hdrcharset' => 'ISO-IR 10646 2000 UTF-8',
            'comment' => 'UTF-8 PAX metadata',
        ]), 0, false)
            . $rawTarHeader('PaxHeaders/local-charset', 'x', $paxPayload([
                'path' => "packet/charset-\u{2603}.md",
                'hdrcharset' => 'BINARY',
                'size' => (string) strlen($documentBytes),
                'uname' => 'wp-reviewer',
            ]), 0, false)
            . $rawTarHeader('placeholder.md', '0', $documentBytes, 1780479084, false, 0)
            . str_repeat("\0", 1024);
        $invalidGlobalCharset = $rawTarHeader('GlobalHead/invalid-charset', 'g', $paxPayload([
            'hdrcharset' => 'UTF-16LE',
        ]), 0, false)
            . $rawTarHeader('packet/global-invalid.md', '0', $documentBytes, 0, false)
            . str_repeat("\0", 1024);
        $invalidLocalCharset = $rawTarHeader('PaxHeaders/invalid-charset', 'x', $paxPayload([
            'path' => 'packet/local-invalid.md',
            'hdrcharset' => 'UTF-16LE',
        ]), 0, false)
            . $rawTarHeader('placeholder.md', '0', $documentBytes, 0, false)
            . str_repeat("\0", 1024);
        $invalidLinkPolicyCharset = $rawTarHeader('PaxHeaders/link-invalid-charset', 'x', $paxPayload([
            'path' => 'packet/link-invalid.md',
            'linkpath' => 'packet/source.md',
            'hdrcharset' => 'UTF-16LE',
        ]), 0, false)
            . $rawTarHeader('placeholder.md', '2', '', 0, false)
            . str_repeat("\0", 1024);

        $roundTrip = TarArchive::fromString($archive);
        $entry = $roundTrip->entry("/packet/charset-\u{2603}.md");
        $inspection = ArchiveCompressionStream::inspectTarStream(
            $archive,
            ArchiveCompressionStream::FORMAT_TAR,
            strlen($archive),
            strlen($documentBytes)
        );

        $t->same(["packet/charset-\u{2603}.md"], $roundTrip->names());
        $t->same($documentBytes, $roundTrip->read("/packet/charset-\u{2603}.md"));
        $t->same('BINARY', $entry->paxHeaders['hdrcharset'] ?? null);
        $t->same('ISO-IR 10646 2000 UTF-8', $entry->globalPaxHeaders['hdrcharset'] ?? null);
        $t->same('BINARY', $entry->localPaxHeaders['hdrcharset'] ?? null);
        $t->same(['comment', 'hdrcharset', 'path', 'size', 'uname'], $inspection['entryLayouts'][0]['paxHeaderKeys']);
        $t->same(['comment', 'hdrcharset'], $inspection['entryLayouts'][0]['paxGlobalHeaderKeys']);
        $t->same(['hdrcharset', 'path', 'size', 'uname'], $inspection['entryLayouts'][0]['paxLocalHeaderKeys']);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidGlobalCharset));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidLocalCharset));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::linkPolicyPreflight($invalidLinkPolicyCharset));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            ['name' => 'packet/generated-invalid-charset.md', 'data' => $documentBytes],
        ], [
            'globalPaxHeaders' => [
                'hdrcharset' => 'UTF-16LE',
            ],
        ]));
    },

    'applies zero-length pax records as scoped metadata deletions' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $localDocument = '<w:document><w:body><w:p>Local PAX deletion source</w:p></w:body></w:document>';
        $inheritedDocument = '<w:document><w:body><w:p>Inherited PAX source</w:p></w:body></w:document>';
        $globalDeletionDocument = '<w:document><w:body><w:p>Global PAX deletion source</w:p></w:body></w:document>';
        $archive = $rawTarHeader('GlobalHead/review', 'g', $paxPayload([
            'comment' => 'global archive review',
            'hdrcharset' => 'BINARY',
            'mtime' => '1780479074',
            'uname' => 'global-reviewer',
        ]), 0, false)
            . $rawTarHeader('PaxHeaders/local-delete', 'x', $paxPayload([
                'comment' => '',
                'mtime' => '',
                'uname' => '',
                'org.wordpress.import.review' => 'local-clean',
            ]), 0, false)
            . $rawTarHeader('packet/local-delete.xml', '0', $localDocument, 1780479073, false)
            . $rawTarHeader('packet/inherited.xml', '0', $inheritedDocument, 0, false)
            . $rawTarHeader('GlobalHead/delete-review', 'g', $paxPayload([
                'comment' => '',
                'hdrcharset' => '',
                'uname' => '',
            ]), 0, false)
            . $rawTarHeader('packet/global-delete.xml', '0', $globalDeletionDocument, 0, false)
            . str_repeat("\0", 1024);

        $roundTrip = TarArchive::fromString($archive);
        $inspection = ArchiveCompressionStream::inspectTarStream(
            $archive,
            ArchiveCompressionStream::FORMAT_TAR,
            strlen($archive),
            strlen($localDocument) + strlen($inheritedDocument) + strlen($globalDeletionDocument)
        );
        $local = $roundTrip->entry('/packet/local-delete.xml');
        $inherited = $roundTrip->entry('/packet/inherited.xml');
        $afterGlobalDelete = $roundTrip->entry('/packet/global-delete.xml');

        $t->same([
            'packet/local-delete.xml',
            'packet/inherited.xml',
            'packet/global-delete.xml',
        ], $roundTrip->names());
        $t->same(['mtime' => '1780479074'], $roundTrip->globalPaxHeaders());
        $t->same(1780479073, $local->modifiedAt);
        $t->same('', $local->userName);
        $t->same(null, $local->paxHeaders['comment'] ?? null);
        $t->same(null, $local->paxHeaders['mtime'] ?? null);
        $t->same(null, $local->paxHeaders['uname'] ?? null);
        $t->same('BINARY', $local->paxHeaders['hdrcharset'] ?? null);
        $t->same('local-clean', $local->paxHeaders['org.wordpress.import.review'] ?? null);
        $t->same([
            'comment' => 'global archive review',
            'hdrcharset' => 'BINARY',
            'mtime' => '1780479074',
            'uname' => 'global-reviewer',
        ], $local->globalPaxHeaders);
        $t->same([
            'comment' => '',
            'mtime' => '',
            'uname' => '',
            'org.wordpress.import.review' => 'local-clean',
        ], $local->localPaxHeaders);
        $t->same(['comment', 'mtime', 'uname'], $local->deletedPaxHeaderKeys);
        $t->same($localDocument, $roundTrip->read('/packet/local-delete.xml'));
        $t->same(1780479074, $inherited->modifiedAt);
        $t->same('global-reviewer', $inherited->userName);
        $t->same('global archive review', $inherited->paxHeaders['comment'] ?? null);
        $t->same('BINARY', $inherited->paxHeaders['hdrcharset'] ?? null);
        $t->same([
            'comment' => 'global archive review',
            'hdrcharset' => 'BINARY',
            'mtime' => '1780479074',
            'uname' => 'global-reviewer',
        ], $inherited->globalPaxHeaders);
        $t->same([], $inherited->localPaxHeaders);
        $t->same([], $inherited->deletedPaxHeaderKeys);
        $t->same($inheritedDocument, $roundTrip->read('/packet/inherited.xml'));
        $t->same(1780479074, $afterGlobalDelete->modifiedAt);
        $t->same('', $afterGlobalDelete->userName);
        $t->same(null, $afterGlobalDelete->paxHeaders['comment'] ?? null);
        $t->same(null, $afterGlobalDelete->paxHeaders['hdrcharset'] ?? null);
        $t->same(null, $afterGlobalDelete->paxHeaders['uname'] ?? null);
        $t->same(['mtime' => '1780479074'], $afterGlobalDelete->globalPaxHeaders);
        $t->same($globalDeletionDocument, $roundTrip->read('/packet/global-delete.xml'));
        $t->same(['hdrcharset', 'org.wordpress.import.review'], $inspection['entryLayouts'][0]['paxHeaderKeys']);
        $t->same(['comment', 'hdrcharset', 'mtime', 'uname'], $inspection['entryLayouts'][1]['paxHeaderKeys']);
        $t->same(['mtime'], $inspection['entryLayouts'][2]['paxHeaderKeys']);
        $t->same(['comment', 'hdrcharset', 'mtime', 'uname'], $inspection['entryLayouts'][0]['paxGlobalHeaderKeys']);
        $t->same(['comment', 'mtime', 'org.wordpress.import.review', 'uname'], $inspection['entryLayouts'][0]['paxLocalHeaderKeys']);
        $t->same(['comment', 'mtime', 'uname'], $inspection['entryLayouts'][0]['paxDeletedHeaderKeys']);
        $t->same(['comment', 'hdrcharset', 'mtime', 'uname'], $inspection['entryLayouts'][1]['paxGlobalHeaderKeys']);
        $t->same([], $inspection['entryLayouts'][1]['paxLocalHeaderKeys']);
        $t->same([], $inspection['entryLayouts'][1]['paxDeletedHeaderKeys']);
        $t->same(['mtime'], $inspection['entryLayouts'][2]['paxGlobalHeaderKeys']);
        $t->same([], $inspection['entryLayouts'][2]['paxLocalHeaderKeys']);
        $t->same([], $inspection['entryLayouts'][2]['paxDeletedHeaderKeys']);
    },

    'rejects per-entry global pax metadata before package bytes are exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $globalPath = $rawTarHeader('GlobalHead/path', 'g', $paxPayload([
            'path' => 'packet/global-name.xml',
        ]), 0, false)
            . $rawTarHeader('packet/original-name.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $documentBytes = '<w:document><w:body><w:p>Global PAX size must not override entry headers</w:p></w:body></w:document>';
        $globalSize = $rawTarHeader('GlobalHead/size', 'g', $paxPayload([
            'size' => (string) strlen($documentBytes),
        ]), 0, false)
            . $rawTarHeader('packet/size-override.xml', '0', $documentBytes, 0, false, 0)
            . str_repeat("\0", 1024);
        $globalLinkPath = $rawTarHeader('GlobalHead/linkpath', 'g', $paxPayload([
            'linkpath' => 'packet/target.xml',
        ]), 0, false)
            . $rawTarHeader('packet/linkpath.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($globalPath));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($globalSize));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($globalLinkPath));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            ['name' => 'packet/document.xml', 'data' => '<w:document/>'],
        ], [
            'globalPaxHeaders' => [
                'path' => 'packet/global-name.xml',
            ],
        ]));
    },

    'reads gnu long name metadata for tar package fixture entries' => static function (TestRunner $t) use ($rawTarHeader): void {
        $longDocumentName = 'packet/' . str_repeat('migration-review-', 7) . 'word/document.xml';
        $longDirectoryName = 'packet/' . str_repeat('review-directory-', 6) . 'assets/';
        $archive = $rawTarHeader('././@LongLink', 'L', $longDocumentName . "\0", 1780479024, false)
            . $rawTarHeader('placeholder-document.xml', '0', '<w:document><w:p>GNU long name source</w:p></w:document>', 1780479025, false)
            . $rawTarHeader('././@LongLink', 'L', $longDirectoryName . "\0", 1780479026, false)
            . $rawTarHeader('placeholder-assets', '5', '', 1780479027, false)
            . str_repeat("\0", 1024);

        $roundTrip = TarArchive::fromString($archive);
        $documentEntry = $roundTrip->entry($longDocumentName);
        $directoryEntry = $roundTrip->entry($longDirectoryName);
        $inspection = ArchiveCompressionStream::inspectTarStream(
            $archive,
            ArchiveCompressionStream::FORMAT_TAR,
            strlen($archive)
        );

        $t->true(strlen($longDocumentName) > 100);
        $t->true(strlen($longDirectoryName) > 100);
        $t->same([$longDocumentName, $longDirectoryName], $roundTrip->names());
        $t->true($documentEntry->isRegularFile());
        $t->same('gnu-long-name', $documentEntry->nameSource);
        $t->same($longDocumentName, $documentEntry->gnuLongName);
        $t->same([], $documentEntry->globalPaxHeaders);
        $t->same([], $documentEntry->localPaxHeaders);
        $t->same(1780479025, $documentEntry->modifiedAt);
        $t->same('<w:document><w:p>GNU long name source</w:p></w:document>', $roundTrip->read('/' . $longDocumentName));
        $t->true($directoryEntry->isDirectory());
        $t->same('gnu-long-name', $directoryEntry->nameSource);
        $t->same($longDirectoryName, $directoryEntry->gnuLongName);
        $t->same(1780479027, $directoryEntry->modifiedAt);
        $t->same('', $roundTrip->read($longDirectoryName));
        $t->same('gnu-long-name', $inspection['entryLayouts'][0]['nameSource']);
        $t->same($longDocumentName, $inspection['entryLayouts'][0]['gnuLongName']);
        $t->same('gnu-long-name', $inspection['entryLayouts'][1]['nameSource']);
        $t->same($longDirectoryName, $inspection['entryLayouts'][1]['gnuLongName']);
    },

    'reads base-256 tar numeric fields for package fixture entries' => static function (TestRunner $t) use ($rawTarHeader, $base256TarField, $rewriteTarHeaderFields): void {
        $documentBytes = '<w:document><w:body><w:p>Base-256 tar metadata source</w:p></w:body></w:document>';
        $archive = $rewriteTarHeaderFields(
            $rawTarHeader('packet/base256/document.xml', '0', $documentBytes),
            [
                100 => $base256TarField(0640, 8),
                108 => $base256TarField(100001, 8),
                116 => $base256TarField(100002, 8),
                124 => $base256TarField(strlen($documentBytes), 12),
                136 => $base256TarField(1780479035, 12),
            ]
        );
        $roundTrip = TarArchive::fromString($archive);
        $entry = $roundTrip->entry('/packet/base256/document.xml');

        $t->same(['packet/base256/document.xml'], $roundTrip->names());
        $t->true($entry->isRegularFile());
        $t->same(strlen($documentBytes), $entry->size);
        $t->same(0640, $entry->mode);
        $t->same(100001, $entry->uid);
        $t->same(100002, $entry->gid);
        $t->same(1780479035, $entry->modifiedAt);
        $t->same($documentBytes, $roundTrip->read('/packet/base256/document.xml'));
    },

    'accepts historic signed tar header checksums for utf8 review packet paths' => static function (TestRunner $t) use ($rawTarHeader, $rewriteTarHeaderWithSignedChecksum): void {
        $documentName = "packet/signed-\u{2603}-checksum.md";
        $documentBytes = "# Signed checksum TAR packet\n\nReady for WordPress archive review.\n";
        $archiveBytes = $rewriteTarHeaderWithSignedChecksum(
            $rawTarHeader($documentName, '0', $documentBytes, 1780479083)
        );
        $corruptedHeader = substr_replace($archiveBytes, '7', 100, 1);

        $archive = TarArchive::fromString($archiveBytes);
        $entry = $archive->entry('/' . $documentName);
        $inspection = ArchiveCompressionStream::inspectTarStream(
            $archiveBytes,
            ArchiveCompressionStream::FORMAT_TAR,
            strlen($archiveBytes),
            strlen($documentBytes)
        );

        $t->same([$documentName], $archive->names());
        $t->true($entry->isRegularFile());
        $t->same(1780479083, $entry->modifiedAt);
        $t->same($documentBytes, $archive->read('/' . $documentName));
        $t->same($documentName, $inspection['entryLayouts'][0]['name']);
        $t->same(512, $inspection['entryLayouts'][0]['dataOffset']);
        $t->same(strlen($documentBytes), $inspection['unpackedSize']);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($corruptedHeader));
    },

    'reads legacy tar contiguous file entries as regular package files' => static function (TestRunner $t) use ($rawTarHeader): void {
        $documentBytes = "# Legacy contiguous TAR entry\n\nReady for WordPress archive review.\n";
        $archiveBytes = $rawTarHeader('packet/legacy-contiguous.md', '7', $documentBytes, 1780479069);
        $gzip = GzipStream::build($archiveBytes, [
            'filename' => 'legacy-contiguous-review.tar',
            'comment' => 'legacy contiguous file entry',
        ]);

        $archive = TarArchive::fromString($archiveBytes);
        $entry = $archive->entry('/packet/legacy-contiguous.md');
        $plainInspection = ArchiveCompressionStream::inspectTarStream(
            $archiveBytes,
            ArchiveCompressionStream::FORMAT_TAR,
            strlen($archiveBytes),
            strlen($documentBytes)
        );
        $gzipInspection = ArchiveCompressionStream::inspectPackageStreamAuto(
            $gzip,
            strlen($archiveBytes),
            strlen($documentBytes)
        );

        $t->same(['packet/legacy-contiguous.md'], $archive->names());
        $t->true($entry->isRegularFile());
        $t->same(TarArchiveEntry::TYPE_FILE, $entry->type);
        $t->same(strlen($documentBytes), $entry->size);
        $t->same(1780479069, $entry->modifiedAt);
        $t->same($documentBytes, $archive->read('/packet/legacy-contiguous.md'));
        $t->same(1, $plainInspection['regularFileCount']);
        $t->same(0, $plainInspection['directoryCount']);
        $t->same(TarArchiveEntry::TYPE_FILE, $plainInspection['entryLayouts'][0]['type']);
        $t->same(512 + strlen($documentBytes), $plainInspection['entryLayouts'][0]['dataEndOffset']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $gzipInspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $gzipInspection['format']);
        $t->same('legacy-contiguous-review.tar', $gzipInspection['stream']['members'][0]['filename']);
        $t->same($documentBytes, $gzipInspection['archive']->read('/packet/legacy-contiguous.md'));
    },

    'normalizes zero-size regular tar entries with trailing slash as legacy directories' => static function (TestRunner $t) use ($rawTarHeader): void {
        $documentBytes = "# Legacy directory marker\n\nReady for WordPress archive review.\n";
        $archiveBytes = $rawTarHeader('packet/legacy-directory/', '0', '', 1780479070, false)
            . $rawTarHeader('packet/legacy-directory/content.md', '0', $documentBytes, 1780479071, false)
            . str_repeat("\0", 1024);
        $payloadDirectoryBytes = $rawTarHeader('packet/payload-directory/', '0', 'not a directory', 1780479072);
        $gzip = GzipStream::build($archiveBytes, [
            'filename' => 'legacy-directory-review.tar',
            'comment' => 'legacy trailing-slash directory entry',
        ]);

        $archive = TarArchive::fromString($archiveBytes);
        $directory = $archive->entry('/packet/legacy-directory/');
        $inspection = ArchiveCompressionStream::inspectPackageStreamAuto(
            $gzip,
            strlen($archiveBytes),
            strlen($documentBytes)
        );

        $t->same(['packet/legacy-directory/', 'packet/legacy-directory/content.md'], $archive->names());
        $t->true($directory->isDirectory());
        $t->same(TarArchiveEntry::TYPE_DIRECTORY, $directory->type);
        $t->same(0, $directory->size);
        $t->same(1780479070, $directory->modifiedAt);
        $t->same('', $archive->read('/packet/legacy-directory/'));
        $t->same($documentBytes, $archive->read('/packet/legacy-directory/content.md'));
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $inspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $inspection['format']);
        $t->same(1, $inspection['directoryCount']);
        $t->same(1, $inspection['regularFileCount']);
        $t->same(TarArchiveEntry::TYPE_DIRECTORY, $inspection['entryLayouts'][0]['type']);
        $t->same(TarArchiveEntry::TYPE_FILE, $inspection['entryLayouts'][1]['type']);
        $t->same('legacy-directory-review.tar', $inspection['stream']['members'][0]['filename']);
        $t->same('legacy trailing-slash directory entry', $inspection['stream']['members'][0]['comment']);
        $t->same($documentBytes, $inspection['archive']->read('/packet/legacy-directory/content.md'));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($payloadDirectoryBytes));
    },

    'rejects unsafe or unsupported tar archive entries' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $valid = TarArchive::build([
            ['name' => 'packet/document.xml', 'data' => '<w:document/>'],
        ]);
        $badChecksum = substr_replace($valid, '000000', 148, 6);
        $linkArchive = $rawTarHeader('packet/link', '2', 'packet/document.xml');
        $deviceArchive = $rawTarHeader('packet/device', '3');
        $directoryWithPayload = $rawTarHeader('packet/dir/', '5', 'payload');
        $unsafeGnuLongName = $rawTarHeader('././@LongLink', 'L', '../packet/document.xml' . "\0", 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $danglingGnuLongName = $rawTarHeader('././@LongLink', 'L', 'packet/missing.xml' . "\0", 0, false)
            . str_repeat("\0", 1024);
        $badPaxSize = $rawTarHeader('PaxHeaders/bad-size', 'x', $paxPayload(['size' => 'not-a-number']), 0, false)
            . $rawTarHeader('packet/bad-size.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            ['name' => '../packet/document.xml', 'data' => 'unsafe'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString(substr($valid, 0, -1)));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($badChecksum));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($linkArchive));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($deviceArchive));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($directoryWithPayload));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($unsafeGnuLongName));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($danglingGnuLongName));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($badPaxSize));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($valid, 1));
    },

    'rejects negative and overflowing base-256 tar numeric fields' => static function (TestRunner $t) use ($rawTarHeader, $rewriteTarHeaderFields): void {
        $valid = $rawTarHeader('packet/base256-bounds.xml', '0', '<w:document/>');
        $negativeSize = $rewriteTarHeaderFields($valid, [
            124 => str_repeat("\xff", 12),
        ]);
        $overflowingSize = $rewriteTarHeaderFields($valid, [
            124 => "\x80\x80" . str_repeat("\0", 10),
        ]);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($negativeSize));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($overflowingSize));
    },

    'rejects tar package streams without two zero end marker blocks' => static function (TestRunner $t) use ($rawTarHeader): void {
        $missingEndMarker = $rawTarHeader('packet/missing-end.xml', '0', '<w:document/>', 0, false);
        $singleZeroBlockEndMarker = $rawTarHeader('packet/single-zero-end.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 512);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($missingEndMarker));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($singleZeroBlockEndMarker));
    },

    'rejects unsupported ustar version bytes before package bytes are exposed' => static function (TestRunner $t) use ($rawTarHeader, $rewriteTarHeaderFields): void {
        $unsupportedVersion = $rewriteTarHeaderFields(
            $rawTarHeader('packet/bad-ustar-version.xml', '0', '<w:document/>'),
            [
                263 => '99',
            ]
        );

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($unsupportedVersion));
    },

    'rejects dangling local pax metadata before package bytes are exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $danglingPax = $rawTarHeader('PaxHeaders/dangling', 'x', $paxPayload([
            'path' => 'packet/dangling/document.xml',
        ]), 0, false)
            . str_repeat("\0", 1024);
        $overwrittenPax = $rawTarHeader('PaxHeaders/first', 'x', $paxPayload([
            'path' => 'packet/first/document.xml',
        ]), 0, false)
            . $rawTarHeader('PaxHeaders/second', 'x', $paxPayload([
                'path' => 'packet/second/document.xml',
            ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $paxBeforeGnuLongName = $rawTarHeader('PaxHeaders/first', 'x', $paxPayload([
            'path' => 'packet/first/document.xml',
        ]), 0, false)
            . $rawTarHeader('././@LongLink', 'L', 'packet/long/document.xml' . "\0", 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $paxBeforeGlobalPax = $rawTarHeader('PaxHeaders/first', 'x', $paxPayload([
            'path' => 'packet/first/document.xml',
        ]), 0, false)
            . $rawTarHeader('GlobalHead/import', 'g', $paxPayload([
                'comment' => 'review metadata',
            ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($danglingPax));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($overwrittenPax));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($paxBeforeGnuLongName));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($paxBeforeGlobalPax));
    },

    'rejects windows drive-letter tar paths from headers pax and gnu metadata' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $headerDriveLetter = $rawTarHeader('C:packet/document.xml', '0', '<w:document/>');
        $paxDriveLetter = $rawTarHeader('PaxHeaders/drive-letter', 'x', $paxPayload([
            'path' => 'C:packet/document.xml',
        ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $gnuDriveLetter = $rawTarHeader('././@LongLink', 'L', 'C:packet/document.xml' . "\0", 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            ['name' => 'C:packet/document.xml', 'data' => '<w:document/>'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($headerDriveLetter));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($paxDriveLetter));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($gnuDriveLetter));
    },

    'rejects tar sparse file metadata before package bytes are exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $gnuSparseType = $rawTarHeader('packet/sparse.bin', 'S', 'sparse map bytes');
        $gnuPaxSparse = $rawTarHeader('PaxHeaders/gnu-sparse', 'x', $paxPayload([
            'path' => 'packet/gnu-sparse.bin',
            'GNU.sparse.major' => '1',
            'GNU.sparse.minor' => '0',
            'GNU.sparse.name' => 'packet/gnu-sparse.bin',
            'GNU.sparse.realsize' => '4096',
            'GNU.sparse.map' => '0,12,4090,6',
        ]), 0, false)
            . $rawTarHeader('placeholder.bin', '0', 'sparse payload fragment', 0, false)
            . str_repeat("\0", 1024);
        $schilyPaxSparse = $rawTarHeader('PaxHeaders/schily-sparse', 'x', $paxPayload([
            'path' => 'packet/schily-sparse.bin',
            'SCHILY.filetype' => 'sparse',
            'SCHILY.realsize' => '8192',
            'SCHILY.sparse.map' => '0,16,8176,16',
        ]), 0, false)
            . $rawTarHeader('placeholder.bin', '0', 'sparse payload fragment', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($gnuSparseType));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($gnuPaxSparse));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($schilyPaxSparse));
    },

    'preflights tar sparse policy without exposing sparse entries' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $gnuTypePayload = 'gnu sparse payload fragment';
        $gnuPaxPayload = 'gnu pax sparse payload fragment';
        $schilyPaxPayload = 'schily sparse payload fragment';
        $archiveBytes = $rawTarHeader('packet/gnu-type-sparse.bin', 'S', $gnuTypePayload, 1780479080, false)
            . $rawTarHeader('PaxHeaders/gnu-sparse-policy', 'x', $paxPayload([
                'path' => 'packet/gnu-pax-sparse.bin',
                'GNU.sparse.major' => '1',
                'GNU.sparse.minor' => '0',
                'GNU.sparse.name' => 'packet/gnu-pax-sparse.bin',
                'GNU.sparse.realsize' => '4096',
                'GNU.sparse.map' => '0,12,4090,6',
            ]), 0, false)
            . $rawTarHeader('placeholder-gnu.bin', '0', $gnuPaxPayload, 1780479081, false)
            . $rawTarHeader('PaxHeaders/schily-sparse-policy', 'x', $paxPayload([
                'path' => 'packet/schily-pax-sparse.bin',
                'SCHILY.filetype' => 'sparse',
                'SCHILY.realsize' => '8192',
                'SCHILY.sparse.map' => '0,16,8176,16',
            ]), 0, false)
            . $rawTarHeader('placeholder-schily.bin', '0', $schilyPaxPayload, 1780479082, false)
            . str_repeat("\0", 1024);
        $gzip = GzipStream::build($archiveBytes, [
            'filename' => 'wordpress-sparse-policy.tar',
            'comment' => 'sparse entries stay blocked for extraction',
        ]);

        $policy = TarArchive::sparsePolicyPreflight($archiveBytes);
        $streamPolicy = ArchiveCompressionStream::inspectTarSparsePolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes)
        );

        $t->same(3, $policy['entryCount']);
        $t->same(3, $policy['sparseEntryCount']);
        $t->same('sparse-entries-blocked', $policy['extractionPolicy']);
        $t->same([
            'packet/gnu-type-sparse.bin',
            'packet/gnu-pax-sparse.bin',
            'packet/schily-pax-sparse.bin',
        ], array_map(static fn (array $entry): string => $entry['name'], $policy['entries']));
        $t->same('gnu-sparse-typeflag', $policy['entries'][0]['sparseType']);
        $t->same(['gnu-typeflag'], $policy['entries'][0]['sparseHeaderFamilies']);
        $t->same([], $policy['entries'][0]['sparseHeaderKeys']);
        $t->same(null, $policy['entries'][0]['realSize']);
        $t->same(null, $policy['entries'][0]['sparseMapSource']);
        $t->same([], $policy['entries'][0]['sparseMapSegments']);
        $t->same(0, $policy['entries'][0]['sparseMapSegmentCount']);
        $t->same(0, $policy['entries'][0]['sparseMapPayloadBytes']);
        $t->same(strlen($gnuTypePayload), $policy['entries'][0]['payloadSize']);
        $t->same(['tar-sparse-entry-not-extracted', 'gnu-typeflag'], $policy['entries'][0]['diagnostics']);
        $t->same('pax-sparse', $policy['entries'][1]['sparseType']);
        $t->same(['gnu-pax'], $policy['entries'][1]['sparseHeaderFamilies']);
        $t->same([
            'GNU.sparse.major',
            'GNU.sparse.map',
            'GNU.sparse.minor',
            'GNU.sparse.name',
            'GNU.sparse.realsize',
        ], $policy['entries'][1]['sparseHeaderKeys']);
        $t->same(4096, $policy['entries'][1]['realSize']);
        $t->same('GNU.sparse.map', $policy['entries'][1]['sparseMapSource']);
        $t->same([
            ['offset' => 0, 'length' => 12, 'endOffset' => 12],
            ['offset' => 4090, 'length' => 6, 'endOffset' => 4096],
        ], $policy['entries'][1]['sparseMapSegments']);
        $t->same(2, $policy['entries'][1]['sparseMapSegmentCount']);
        $t->same(18, $policy['entries'][1]['sparseMapPayloadBytes']);
        $t->same(strlen($gnuPaxPayload), $policy['entries'][1]['payloadSize']);
        $t->same('pax-path', $policy['entries'][1]['nameSource']);
        $t->same(['tar-sparse-entry-not-extracted', 'gnu-pax'], $policy['entries'][1]['diagnostics']);
        $t->same(['schily-pax'], $policy['entries'][2]['sparseHeaderFamilies']);
        $t->same([
            'SCHILY.filetype',
            'SCHILY.realsize',
            'SCHILY.sparse.map',
        ], $policy['entries'][2]['sparseHeaderKeys']);
        $t->same(8192, $policy['entries'][2]['realSize']);
        $t->same('SCHILY.sparse.map', $policy['entries'][2]['sparseMapSource']);
        $t->same([
            ['offset' => 0, 'length' => 16, 'endOffset' => 16],
            ['offset' => 8176, 'length' => 16, 'endOffset' => 8192],
        ], $policy['entries'][2]['sparseMapSegments']);
        $t->same(2, $policy['entries'][2]['sparseMapSegmentCount']);
        $t->same(32, $policy['entries'][2]['sparseMapPayloadBytes']);
        $t->same(strlen($schilyPaxPayload), $policy['entries'][2]['payloadSize']);
        $t->same('pax-path', $policy['entries'][2]['nameSource']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $streamPolicy['format']);
        $t->same(strlen($archiveBytes), $streamPolicy['uncompressedSize']);
        $t->same('gzip', $streamPolicy['stream']['type']);
        $t->same('wordpress-sparse-policy.tar', $streamPolicy['stream']['members'][0]['filename']);
        $t->same(3, $streamPolicy['sparseEntryCount']);
        $t->same('packet/schily-pax-sparse.bin', $streamPolicy['entries'][2]['name']);
        $t->same(32, $streamPolicy['entries'][2]['sparseMapPayloadBytes']);
        $t->same('SCHILY.sparse.map', $streamPolicy['entries'][2]['sparseMapSource']);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($archiveBytes));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectTarSparsePolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes) - 1
        ));
    },

    'rejects tar multi-volume metadata before package bytes are exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $gnuMultiVolumeType = $rawTarHeader('packet/volume-fragment.md', 'M', 'continued fragment');
        $paxMultiVolumeRegular = $rawTarHeader('PaxHeaders/volume-fragment', 'x', $paxPayload([
            'path' => 'packet/pax-volume-fragment.md',
            'GNU.volume.offset' => '2048',
            'GNU.volume.filename' => 'packet/full-document.md',
            'GNU.volume.size' => '8192',
        ]), 0, false)
            . $rawTarHeader('placeholder-volume.md', '0', 'pax volume fragment', 0, false)
            . str_repeat("\0", 1024);
        $globalMultiVolume = $rawTarHeader('GlobalHead/volume', 'g', $paxPayload([
            'GNU.volume.offset' => '1',
        ]), 0, false)
            . $rawTarHeader('packet/content.md', '0', 'content', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($gnuMultiVolumeType));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($paxMultiVolumeRegular));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($globalMultiVolume));
    },

    'preflights tar multi-volume policy without exposing split entries' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload, $rewriteTarHeaderFields): void {
        $octal12 = static fn (int $value): string => str_pad(decoct($value), 11, '0', STR_PAD_LEFT) . "\0";
        $typePayload = 'gnu multi-volume payload fragment';
        $paxPayloadFragment = 'pax multi-volume payload fragment';
        $archiveBytes = $rawTarHeader('PaxHeaders/volume-type', 'x', $paxPayload([
            'path' => 'packet/volume-fragment.md',
            'GNU.volume.filename' => 'packet/full-document.md',
            'GNU.volume.size' => '8192',
        ]), 0, false)
            . $rewriteTarHeaderFields(
                $rawTarHeader('placeholder-volume.md', 'M', $typePayload, 1780479082, false),
                [369 => $octal12(4096)]
            )
            . $rawTarHeader('PaxHeaders/volume-pax', 'x', $paxPayload([
                'path' => 'packet/pax-volume-fragment.md',
                'GNU.volume.offset' => '2048',
                'GNU.volume.filename' => 'packet/pax-full-document.md',
                'GNU.volume.size' => '4096',
            ]), 0, false)
            . $rawTarHeader('placeholder-pax-volume.md', '0', $paxPayloadFragment, 1780479083, false)
            . str_repeat("\0", 1024);
        $gzip = GzipStream::build($archiveBytes, [
            'filename' => 'wordpress-multivolume-policy.tar',
            'comment' => 'multi-volume entries stay blocked for extraction',
        ]);

        $policy = TarArchive::multiVolumePolicyPreflight($archiveBytes);
        $streamPolicy = ArchiveCompressionStream::inspectTarMultiVolumePolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes)
        );

        $t->same(2, $policy['entryCount']);
        $t->same(2, $policy['multiVolumeEntryCount']);
        $t->same(1, $policy['typeflagEntryCount']);
        $t->same(2, $policy['paxMetadataEntryCount']);
        $t->same('multi-volume-entries-blocked', $policy['extractionPolicy']);
        $t->same([
            'packet/volume-fragment.md',
            'packet/pax-volume-fragment.md',
        ], array_map(static fn (array $entry): string => $entry['name'], $policy['entries']));
        $t->same('gnu-multivolume-typeflag', $policy['entries'][0]['multiVolumeType']);
        $t->same(['gnu-typeflag', 'gnu-pax'], $policy['entries'][0]['volumeHeaderFamilies']);
        $t->same(['GNU.volume.filename', 'GNU.volume.size'], $policy['entries'][0]['volumeHeaderKeys']);
        $t->same(4096, $policy['entries'][0]['continuationOffset']);
        $t->same('oldgnu-offset-field', $policy['entries'][0]['continuationOffsetSource']);
        $t->same('packet/full-document.md', $policy['entries'][0]['originalName']);
        $t->same(8192, $policy['entries'][0]['declaredVolumeSize']);
        $t->same(strlen($typePayload), $policy['entries'][0]['payloadSize']);
        $t->same('pax-path', $policy['entries'][0]['nameSource']);
        $t->same(['tar-multi-volume-entry-not-extracted', 'gnu-typeflag', 'gnu-pax'], $policy['entries'][0]['diagnostics']);
        $t->same('pax-gnu-volume-metadata', $policy['entries'][1]['multiVolumeType']);
        $t->same(['gnu-pax'], $policy['entries'][1]['volumeHeaderFamilies']);
        $t->same(['GNU.volume.filename', 'GNU.volume.offset', 'GNU.volume.size'], $policy['entries'][1]['volumeHeaderKeys']);
        $t->same(2048, $policy['entries'][1]['continuationOffset']);
        $t->same('pax-gnu-volume-offset', $policy['entries'][1]['continuationOffsetSource']);
        $t->same('packet/pax-full-document.md', $policy['entries'][1]['originalName']);
        $t->same(4096, $policy['entries'][1]['declaredVolumeSize']);
        $t->same(strlen($paxPayloadFragment), $policy['entries'][1]['payloadSize']);
        $t->same(['tar-multi-volume-entry-not-extracted', 'gnu-pax'], $policy['entries'][1]['diagnostics']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $streamPolicy['format']);
        $t->same(strlen($archiveBytes), $streamPolicy['uncompressedSize']);
        $t->same('gzip', $streamPolicy['stream']['type']);
        $t->same('wordpress-multivolume-policy.tar', $streamPolicy['stream']['members'][0]['filename']);
        $t->same(2, $streamPolicy['multiVolumeEntryCount']);
        $t->same('packet/pax-volume-fragment.md', $streamPolicy['entries'][1]['name']);
        $t->same('pax-gnu-volume-offset', $streamPolicy['entries'][1]['continuationOffsetSource']);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($archiveBytes));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectTarMultiVolumePolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes) - 1
        ));
    },

    'rejects malformed tar multi-volume policy metadata before diagnostics are exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $multiVolumeArchive = static function (array $headers) use ($rawTarHeader, $paxPayload): string {
            return $rawTarHeader('PaxHeaders/volume', 'x', $paxPayload($headers), 0, false)
                . $rawTarHeader('placeholder-volume.md', '0', 'volume payload fragment', 0, false)
                . str_repeat("\0", 1024);
        };

        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::multiVolumePolicyPreflight($multiVolumeArchive([
            'path' => 'packet/non-numeric-volume.md',
            'GNU.volume.offset' => 'not-a-number',
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::multiVolumePolicyPreflight($multiVolumeArchive([
            'path' => 'packet/oversized-volume.md',
            'GNU.volume.size' => '999999999999999999999999999999',
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::multiVolumePolicyPreflight($multiVolumeArchive([
            'path' => 'packet/unsafe-volume.md',
            'GNU.volume.filename' => '../full-document.md',
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::multiVolumePolicyPreflight(
            $rawTarHeader('GlobalHead/volume', 'g', $paxPayload([
                'GNU.volume.offset' => '1',
            ]), 0, false)
            . $rawTarHeader('packet/content.md', '0', 'content', 0, false)
            . str_repeat("\0", 1024)
        ));
    },

    'rejects malformed tar sparse maps before policy metadata is exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $sparseArchive = static function (array $headers) use ($rawTarHeader, $paxPayload): string {
            return $rawTarHeader('PaxHeaders/sparse-map', 'x', $paxPayload($headers), 0, false)
                . $rawTarHeader('placeholder.bin', '0', 'sparse payload fragment', 0, false)
                . str_repeat("\0", 1024);
        };

        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::sparsePolicyPreflight($sparseArchive([
            'path' => 'packet/odd-map.bin',
            'GNU.sparse.realsize' => '4096',
            'GNU.sparse.map' => '0,12,4090',
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::sparsePolicyPreflight($sparseArchive([
            'path' => 'packet/non-numeric-map.bin',
            'SCHILY.filetype' => 'sparse',
            'SCHILY.realsize' => '8192',
            'SCHILY.sparse.map' => '0,16,not-a-number,16',
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::sparsePolicyPreflight($sparseArchive([
            'path' => 'packet/overlap-map.bin',
            'GNU.sparse.realsize' => '4096',
            'GNU.sparse.map' => '0,12,10,4',
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::sparsePolicyPreflight($sparseArchive([
            'path' => 'packet/beyond-realsize-map.bin',
            'GNU.sparse.realsize' => '4096',
            'GNU.sparse.map' => '4090,7',
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::sparsePolicyPreflight($sparseArchive([
            'path' => 'packet/mixed-map.bin',
            'GNU.sparse.realsize' => '4096',
            'GNU.sparse.map' => '0,12',
            'SCHILY.sparse.map' => '0,12',
        ])));
    },

    'rejects tar pax linkpath metadata before package bytes are exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $paxLinkPath = $rawTarHeader('PaxHeaders/linkpath', 'x', $paxPayload([
            'path' => 'packet/linkpath-regular.xml',
            'linkpath' => 'packet/target.xml',
        ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($paxLinkPath));
    },

    'preflights tar hardlink and symlink policy without exposing link entries' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload, $rewriteTarHeaderFields): void {
        $sourceBytes = "# Link target source\n\nReady for WordPress import review.\n";
        $gnuLongTarget = 'packet/' . str_repeat('nested-link-target-', 6) . 'review.md';
        $hardLink = $rewriteTarHeaderFields(
            $rawTarHeader('packet/hard-copy.md', '1', '', 1780479076, false),
            [157 => str_pad('packet/source.md', 100, "\0")]
        );
        $paxSymlink = $rawTarHeader('PaxHeaders/symlink-linkpath', 'x', $paxPayload([
            'linkpath' => 'packet/media/review.png',
        ]), 0, false)
            . $rewriteTarHeaderFields(
                $rawTarHeader('packet/media/latest.png', '2', '', 1780479077, false),
                [157 => str_pad('ignored-header-target.png', 100, "\0")]
            );
        $gnuSymlink = $rawTarHeader('././@LongLink', 'K', $gnuLongTarget . "\0", 0, false)
            . $rewriteTarHeaderFields(
                $rawTarHeader('packet/gnu-symlink.md', '2', '', 1780479078, false),
                [157 => str_pad('short-target.md', 100, "\0")]
            );
        $archiveBytes = $rawTarHeader('PaxHeaders/source-size', 'x', $paxPayload([
            'size' => (string) strlen($sourceBytes),
        ]), 0, false)
            . $rawTarHeader('packet/source.md', '0', $sourceBytes, 1780479075, false, 0)
            . $hardLink
            . $paxSymlink
            . $gnuSymlink
            . str_repeat("\0", 1024);
        $gzip = GzipStream::build($archiveBytes, [
            'filename' => 'wordpress-link-policy.tar',
            'comment' => 'link entries stay blocked for extraction',
        ]);

        $policy = TarArchive::linkPolicyPreflight($archiveBytes);
        $streamPolicy = ArchiveCompressionStream::inspectTarLinkPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes)
        );
        $missingHardLinkPolicy = TarArchive::linkPolicyPreflight(
            $rewriteTarHeaderFields(
                $rawTarHeader('packet/missing-hard-copy.md', '1', '', 1780479079),
                [157 => str_pad('packet/missing-target.md', 100, "\0")]
            )
        );
        $unsafeSymlink = $rewriteTarHeaderFields(
            $rawTarHeader('packet/unsafe-link.md', '2', ''),
            [157 => str_pad('../packet/source.md', 100, "\0")]
        );

        $t->same(4, $policy['entryCount']);
        $t->same(3, $policy['linkEntryCount']);
        $t->same(1, $policy['hardLinkCount']);
        $t->same(2, $policy['symbolicLinkCount']);
        $t->same('link-entries-blocked', $policy['extractionPolicy']);
        $t->same('packet/hard-copy.md', $policy['entries'][0]['name']);
        $t->same('hard-link', $policy['entries'][0]['linkType']);
        $t->same('packet/source.md', $policy['entries'][0]['linkTarget']);
        $t->same('header-linkname', $policy['entries'][0]['linkTargetSource']);
        $t->same(true, $policy['entries'][0]['targetEntryExists']);
        $t->same(['tar-link-entry-not-extracted'], $policy['entries'][0]['diagnostics']);
        $t->same('packet/media/latest.png', $policy['entries'][1]['name']);
        $t->same('symbolic-link', $policy['entries'][1]['linkType']);
        $t->same('packet/media/review.png', $policy['entries'][1]['linkTarget']);
        $t->same('pax-linkpath', $policy['entries'][1]['linkTargetSource']);
        $t->same('packet/gnu-symlink.md', $policy['entries'][2]['name']);
        $t->same($gnuLongTarget, $policy['entries'][2]['linkTarget']);
        $t->same('gnu-long-link', $policy['entries'][2]['linkTargetSource']);
        $t->same('blocked', $policy['entries'][2]['policy']);
        $t->same('hard-link-target-not-yet-seen', $missingHardLinkPolicy['entries'][0]['diagnostics'][1] ?? null);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $streamPolicy['format']);
        $t->same(3, $streamPolicy['linkEntryCount']);
        $t->same('gzip', $streamPolicy['stream']['type']);
        $t->same('wordpress-link-policy.tar', $streamPolicy['stream']['members'][0]['filename']);
        $t->same('packet/media/review.png', $streamPolicy['entries'][1]['linkTarget']);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($archiveBytes));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::linkPolicyPreflight($unsafeSymlink));
    },

    'preflights tar special file policy without exposing device entries' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload, $rewriteTarHeaderFields): void {
        $deviceField = static fn (int $value): string => str_pad(decoct($value), 7, '0', STR_PAD_LEFT) . "\0";
        $characterDevice = $rewriteTarHeaderFields(
            $rawTarHeader('packet/dev/console', '3', '', 1780479087, false),
            [
                329 => $deviceField(5),
                337 => $deviceField(1),
            ]
        );
        $blockDevice = $rawTarHeader('PaxHeaders/block-device', 'x', $paxPayload([
            'path' => 'packet/dev/disk0',
            'devmajor' => '8',
            'devminor' => '16',
        ]), 0, false)
            . $rewriteTarHeaderFields(
                $rawTarHeader('placeholder-device', '4', '', 1780479088, false),
                [
                    329 => $deviceField(0),
                    337 => $deviceField(0),
                ]
            );
        $fifo = $rawTarHeader('packet/dev/import.fifo', '6', '', 1780479089, false);
        $archiveBytes = $characterDevice . $blockDevice . $fifo . str_repeat("\0", 1024);
        $gzip = GzipStream::build($archiveBytes, [
            'filename' => 'wordpress-special-file-policy.tar',
            'comment' => 'special file entries stay blocked for extraction',
        ]);

        $policy = TarArchive::specialFilePolicyPreflight($archiveBytes);
        $streamPolicy = ArchiveCompressionStream::inspectTarSpecialFilePolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes)
        );
        $payloadDevice = $rawTarHeader('packet/dev/payload', '3', 'payload');
        $invalidPaxDevice = $rawTarHeader('PaxHeaders/invalid-device', 'x', $paxPayload([
            'path' => 'packet/dev/invalid',
            'devmajor' => 'not-a-number',
        ]), 0, false)
            . $rawTarHeader('placeholder-device', '3', '', 0, false)
            . str_repeat("\0", 1024);

        $t->same(3, $policy['entryCount']);
        $t->same(3, $policy['specialFileEntryCount']);
        $t->same(1, $policy['characterDeviceCount']);
        $t->same(1, $policy['blockDeviceCount']);
        $t->same(1, $policy['fifoCount']);
        $t->same('special-file-entries-blocked', $policy['extractionPolicy']);
        $t->same('packet/dev/console', $policy['entries'][0]['name']);
        $t->same('character-device', $policy['entries'][0]['specialType']);
        $t->same('3', $policy['entries'][0]['typeFlag']);
        $t->same(5, $policy['entries'][0]['deviceMajor']);
        $t->same(1, $policy['entries'][0]['deviceMinor']);
        $t->same('header-device-numbers', $policy['entries'][0]['deviceNumberSource']);
        $t->same(['tar-special-file-not-extracted', 'tar-character-device-not-extracted'], $policy['entries'][0]['diagnostics']);
        $t->same('packet/dev/disk0', $policy['entries'][1]['name']);
        $t->same('block-device', $policy['entries'][1]['specialType']);
        $t->same(8, $policy['entries'][1]['deviceMajor']);
        $t->same(16, $policy['entries'][1]['deviceMinor']);
        $t->same('pax-device-numbers', $policy['entries'][1]['deviceNumberSource']);
        $t->same('pax-path', $policy['entries'][1]['nameSource']);
        $t->same('packet/dev/import.fifo', $policy['entries'][2]['name']);
        $t->same('fifo', $policy['entries'][2]['specialType']);
        $t->same(null, $policy['entries'][2]['deviceMajor']);
        $t->same(null, $policy['entries'][2]['deviceMinor']);
        $t->same('none', $policy['entries'][2]['deviceNumberSource']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $streamPolicy['format']);
        $t->same(strlen($archiveBytes), $streamPolicy['uncompressedSize']);
        $t->same('gzip', $streamPolicy['stream']['type']);
        $t->same('wordpress-special-file-policy.tar', $streamPolicy['stream']['members'][0]['filename']);
        $t->same(3, $streamPolicy['specialFileEntryCount']);
        $t->same('packet/dev/disk0', $streamPolicy['entries'][1]['name']);
        $t->same(16, $streamPolicy['entries'][1]['deviceMinor']);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($archiveBytes));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::specialFilePolicyPreflight($payloadDevice));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::specialFilePolicyPreflight($invalidPaxDevice));
    },

    'rejects tar gnu long-link metadata before package bytes are exposed' => static function (TestRunner $t) use ($rawTarHeader): void {
        $gnuLongLink = $rawTarHeader('././@LongLink', 'K', 'packet/target.xml' . "\0", 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $message = null;

        try {
            TarArchive::fromString($gnuLongLink);
        } catch (\RuntimeException $exception) {
            $message = $exception->getMessage();
        }

        $t->same('TAR GNU long-link metadata is not supported by the pandoc archive reader', $message);
    },

    'accepts utf8 pax paths and rejects invalid pax path bytes before package exposure' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $unicodeName = "packet/review-\u{2603}/document.xml";
        $unicodeBytes = '<w:document><w:body><w:p>Unicode PAX path source</w:p></w:body></w:document>';
        $unicodePax = $rawTarHeader('PaxHeaders/unicode-path', 'x', $paxPayload([
            'path' => $unicodeName,
            'size' => (string) strlen($unicodeBytes),
        ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', $unicodeBytes, 0, false, 0)
            . str_repeat("\0", 1024);
        $invalidPax = $rawTarHeader('PaxHeaders/invalid-path', 'x', $paxPayload([
            'path' => "packet/invalid-\xC3\x28.xml",
        ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $roundTrip = TarArchive::fromString($unicodePax);

        $t->same([$unicodeName], $roundTrip->names());
        $t->same($unicodeBytes, $roundTrip->read('/' . $unicodeName));
        $t->same($unicodeName, $roundTrip->entry('/' . $unicodeName)->paxHeaders['path'] ?? null);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidPax));
    },

    'rejects invalid utf8 ustar path bytes before package exposure' => static function (TestRunner $t) use ($rawTarHeader, $rewriteTarHeaderFields): void {
        $invalidName = $rawTarHeader("packet/invalid-\xC3\x28.xml", '0', '<w:document/>');
        $invalidPrefix = $rewriteTarHeaderFields(
            $rawTarHeader('document.xml', '0', '<w:document/>'),
            [
                345 => str_pad("packet/invalid-\xC3\x28", 155, "\0"),
            ]
        );

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidName));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidPrefix));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            ['name' => "packet/generated-invalid-\xC3\x28.xml", 'data' => '<w:document/>'],
        ]));
    },

    'rejects invalid utf8 gnu long name metadata before package exposure' => static function (TestRunner $t) use ($rawTarHeader): void {
        $invalidGnuLongName = $rawTarHeader('././@LongLink', 'L', "packet/invalid-\xC3\x28.xml\0", 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($invalidGnuLongName));
    },

    'rejects unterminated gnu long name metadata before package exposure' => static function (TestRunner $t) use ($rawTarHeader): void {
        $longDocumentName = 'packet/' . str_repeat('unterminated-review-', 5) . 'word/document.xml';
        $unterminatedGnuLongName = $rawTarHeader('././@LongLink', 'L', $longDocumentName, 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $t->true(strlen($longDocumentName) > 100);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($unterminatedGnuLongName));
    },

    'reads gzip wrapped tar streams for package handoff fixtures' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-tar","target":"wordpress"}',
                'modifiedAt' => 1780479021,
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Imported archive\n\nReady for block review.\n",
                'modifiedAt' => 1780479022,
            ],
        ]);
        $gzip = GzipStream::build($archive->bytes(), [
            'filename' => 'wordpress-import-packet.tar',
            'comment' => 'tar package fixture',
            'modifiedAt' => 1780479023,
            'headerCrc' => true,
        ]);
        $members = GzipStream::members($gzip);
        $roundTrip = TarArchive::fromString(GzipStream::decode($gzip));

        $t->same(1, count($members));
        $t->same('wordpress-import-packet.tar', $members[0]['filename']);
        $t->same('tar package fixture', $members[0]['comment']);
        $t->same(1780479023, $members[0]['modifiedAt']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $roundTrip->names());
        $t->same('{"source":"gzip-tar","target":"wordpress"}', $roundTrip->read('packet/manifest.json'));
        $t->same("# Imported archive\n\nReady for block review.\n", $roundTrip->read('/packet/content.md'));
    },

    'parses gzip extra subfields for package handoff metadata' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-extra","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# GZIP extra metadata\n\nReady for review.\n",
            ],
        ]);
        $extraFieldData = pack('CCv', ord('W'), ord('P'), strlen('review:v1')) . 'review:v1'
            . pack('CCv', ord('P'), ord('D'), strlen('packet=docx')) . 'packet=docx';
        $gzip = GzipStream::build($archive->bytes(), [
            'extraFieldData' => $extraFieldData,
            'filename' => 'wordpress-import-packet.tar',
            'comment' => 'gzip extra metadata',
        ]);
        $members = GzipStream::members($gzip);
        $roundTrip = TarArchive::fromString(GzipStream::decode($gzip));

        $t->same(1, count($members));
        $t->same($extraFieldData, $members[0]['extraFieldData']);
        $t->same([
            [
                'identifier' => 'WP',
                'id1' => ord('W'),
                'id2' => ord('P'),
                'length' => strlen('review:v1'),
                'data' => 'review:v1',
            ],
            [
                'identifier' => 'PD',
                'id1' => ord('P'),
                'id2' => ord('D'),
                'length' => strlen('packet=docx'),
                'data' => 'packet=docx',
            ],
        ], $members[0]['extraFields']);
        $t->same('{"source":"gzip-extra","target":"wordpress"}', $roundTrip->read('/packet/manifest.json'));
        $t->same("# GZIP extra metadata\n\nReady for review.\n", $roundTrip->read('/packet/content.md'));
    },

    'decodes gzip latin1 filename and comment text for review packet provenance' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-latin1","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# GZIP Latin-1 provenance\n\nReady for review.\n",
            ],
        ]);
        $rawFilename = "review-r\xE9sum\xE9-packet.tar";
        $rawComment = "caf\xE9 archive packet";
        $gzip = GzipStream::build($archive->bytes(), [
            'filename' => $rawFilename,
            'comment' => $rawComment,
        ]);

        $members = GzipStream::members($gzip);
        $inspection = ArchiveCompressionStream::inspectTarStreamAuto($gzip, strlen($archive->bytes()));
        $inspectionMember = $inspection['stream']['members'][0];

        $t->same($rawFilename, $members[0]['filename']);
        $t->same("review-r\u{00E9}sum\u{00E9}-packet.tar", $members[0]['filenameText'] ?? null);
        $t->same('gzip-latin1', $members[0]['filenameEncoding'] ?? null);
        $t->same($rawComment, $members[0]['comment']);
        $t->same("caf\u{00E9} archive packet", $members[0]['commentText'] ?? null);
        $t->same('gzip-latin1', $members[0]['commentEncoding'] ?? null);
        $t->same("review-r\u{00E9}sum\u{00E9}-packet.tar", $inspectionMember['filenameText'] ?? null);
        $t->same("caf\u{00E9} archive packet", $inspectionMember['commentText'] ?? null);
        $t->same('{"source":"gzip-latin1","target":"wordpress"}', $inspection['archive']->read('/packet/manifest.json'));
        $t->same("# GZIP Latin-1 provenance\n\nReady for review.\n", $inspection['archive']->read('/packet/content.md'));
    },

    'exposes gzip text hint flag for archive review provenance' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-text-hint","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# GZIP text hint provenance\n\nReady for review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $defaultGzip = GzipStream::build($tarBytes, [
            'filename' => 'binary-review-packet.tar',
        ]);
        $textHintGzip = GzipStream::build($tarBytes, [
            'filename' => 'text-hint-review-packet.tar',
            'textHint' => true,
        ]);

        $defaultMember = GzipStream::members($defaultGzip)[0];
        $textHintInspection = ArchiveCompressionStream::inspectTarStream(
            $textHintGzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes)
        );
        $textHintMember = $textHintInspection['stream']['members'][0];

        $t->same(false, $defaultMember['textHint']);
        $t->same(0x08, $defaultMember['flags']);
        $t->same(true, $textHintMember['textHint']);
        $t->same(0x09, $textHintMember['flags']);
        $t->same('text-hint-review-packet.tar', $textHintMember['filename']);
        $t->same('{"source":"gzip-text-hint","target":"wordpress"}', $textHintInspection['archive']->read('/packet/manifest.json'));
        $t->same("# GZIP text hint provenance\n\nReady for review.\n", $textHintInspection['archive']->read('/packet/content.md'));
    },

    'labels gzip timestamp compression and platform provenance for review packets' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-review-labels","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# GZIP review labels\n\nReady for archive review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $reproducibleGzip = GzipStream::build($tarBytes, [
            'filename' => 'reproducible-review.tar',
            'modifiedAt' => 0,
            'extraFlags' => 4,
            'operatingSystem' => 3,
        ]);
        $timestampedGzip = GzipStream::build($tarBytes, [
            'filename' => 'timestamped-review.tar',
            'modifiedAt' => 1780479042,
            'extraFlags' => 2,
            'operatingSystem' => 255,
        ]);

        $reproducibleMember = GzipStream::members($reproducibleGzip)[0];
        $timestampedMember = GzipStream::members($timestampedGzip)[0];
        $inspection = ArchiveCompressionStream::inspectTarStreamAuto($reproducibleGzip, strlen($tarBytes));
        $inspectionMember = $inspection['stream']['members'][0];

        $t->same(0, $reproducibleMember['modifiedAt']);
        $t->same(false, $reproducibleMember['modifiedAtKnown']);
        $t->same(null, $reproducibleMember['modifiedAtText']);
        $t->same('fastest-compression', $reproducibleMember['extraFlagsMeaning']);
        $t->same('unix', $reproducibleMember['operatingSystemName']);
        $t->same('reproducible-review.tar', $reproducibleMember['filename']);
        $t->same('gzip-tar', $inspection['format']);
        $t->same(false, $inspectionMember['modifiedAtKnown']);
        $t->same(null, $inspectionMember['modifiedAtText']);
        $t->same('fastest-compression', $inspectionMember['extraFlagsMeaning']);
        $t->same('unix', $inspectionMember['operatingSystemName']);
        $t->same('{"source":"gzip-review-labels","target":"wordpress"}', $inspection['archive']->read('/packet/manifest.json'));

        $t->same(1780479042, $timestampedMember['modifiedAt']);
        $t->true($timestampedMember['modifiedAtKnown']);
        $t->same('2026-06-03T09:30:42Z', $timestampedMember['modifiedAtText']);
        $t->same('maximum-compression', $timestampedMember['extraFlagsMeaning']);
        $t->same('unknown', $timestampedMember['operatingSystemName']);
        $t->same('timestamped-review.tar', $timestampedMember['filename']);
    },

    'accepts nul-padded gzip package streams and rejects nonzero trailers' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-padding","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# GZIP padded stream\n\nReady for archive review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $unpackedBytes = strlen($archive->read('/packet/manifest.json'))
            + strlen($archive->read('/packet/content.md'));
        $gzip = GzipStream::build($tarBytes, [
            'filename' => 'padded-review-packet.tar',
            'comment' => 'nul padded gzip stream',
        ]);
        $padded = $gzip . str_repeat("\0", 12);

        $gzipInspection = GzipStream::inspect($padded);
        $streamInspection = ArchiveCompressionStream::inspectTarStream(
            $padded,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes),
            $unpackedBytes
        );

        $t->same($tarBytes, GzipStream::decode($padded, strlen($tarBytes)));
        $t->same(1, $gzipInspection['memberCount']);
        $t->same(12, $gzipInspection['trailingPaddingBytes']);
        $t->same(strlen($tarBytes), $gzipInspection['uncompressedSize']);
        $t->same(12, $streamInspection['stream']['trailingPaddingBytes']);
        $t->same(strlen($tarBytes), $streamInspection['stream']['uncompressedSize']);
        $t->same('padded-review-packet.tar', $streamInspection['stream']['members'][0]['filename']);
        $t->same('{"source":"gzip-padding","target":"wordpress"}', $streamInspection['archive']->read('/packet/manifest.json'));
        $t->same("# GZIP padded stream\n\nReady for archive review.\n", $streamInspection['archive']->read('/packet/content.md'));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::inspect(str_repeat("\0", 8)));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::decode($gzip . "\0" . 'review-trailer'));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectTarStream(
            $gzip . 'review-trailer',
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes),
            $unpackedBytes
        ));
    },

    'rejects malformed gzip extra subfields before package bytes are exposed' => static function (TestRunner $t): void {
        $valid = GzipStream::build('review packet', [
            'filename' => 'packet.txt',
        ]);
        $injectExtra = static function (string $gzip, string $extraFieldData): string {
            return substr_replace(
                substr_replace($gzip, chr(ord($gzip[3]) | 0x04), 3, 1),
                pack('v', strlen($extraFieldData)) . $extraFieldData,
                10,
                0
            );
        };
        $truncatedSubfield = $injectExtra(
            $valid,
            pack('CCv', ord('W'), ord('P'), 4) . 'x'
        );
        $duplicateSubfield = $injectExtra(
            $valid,
            pack('CCv', ord('W'), ord('P'), 1) . 'a'
                . pack('CCv', ord('W'), ord('P'), 1) . 'b'
        );

        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($truncatedSubfield));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::decode($truncatedSubfield));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($duplicateSubfield));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::build('review packet', [
            'extraFieldData' => pack('CCv', ord('W'), ord('P'), 4) . 'x',
        ]));
    },

    'inspects gzip member provenance for split tar package review streams' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-member-provenance","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Split gzip provenance\n\nReady for archive review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $splitOffset = 640;
        $firstExtra = pack('CCv', ord('W'), ord('P'), strlen('review:v1')) . 'review:v1';
        $secondExtra = pack('CCv', ord('P'), ord('D'), strlen('packet=tar')) . 'packet=tar';
        $gzip = GzipStream::build(substr($tarBytes, 0, $splitOffset), [
            'filename' => 'review-packet.part-1.tar',
            'comment' => 'split member one',
            'modifiedAt' => 1780479040,
            'extraFlags' => 4,
            'operatingSystem' => 3,
            'extraFieldData' => $firstExtra,
            'headerCrc' => true,
        ]) . GzipStream::build(substr($tarBytes, $splitOffset), [
            'filename' => 'review-packet.part-2.tar',
            'comment' => 'split member two',
            'modifiedAt' => 1780479041,
            'extraFlags' => 2,
            'operatingSystem' => 255,
            'extraFieldData' => $secondExtra,
            'headerCrc' => true,
        ]);

        $inspection = ArchiveCompressionStream::inspectTarStreamAuto($gzip, strlen($tarBytes));
        $members = $inspection['stream']['members'];

        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $inspection['format']);
        $t->same('gzip', $inspection['stream']['type']);
        $t->same(2, $inspection['stream']['memberCount']);
        $t->same(['review-packet.part-1.tar', 'review-packet.part-2.tar'], array_map(static fn (array $member): ?string => $member['filename'], $members));
        $t->same(['split member one', 'split member two'], array_map(static fn (array $member): ?string => $member['comment'], $members));
        $t->same([1780479040, 1780479041], array_map(static fn (array $member): int => $member['modifiedAt'], $members));
        $t->same([4, 2], array_map(static fn (array $member): int => $member['extraFlags'], $members));
        $t->same([3, 255], array_map(static fn (array $member): int => $member['operatingSystem'], $members));
        $t->same([$firstExtra, $secondExtra], array_map(static fn (array $member): ?string => $member['extraFieldData'], $members));
        $t->same([1, 1], array_map(static fn (array $member): int => $member['extraFieldCount'], $members));
        $t->same([
            [
                'identifier' => 'WP',
                'id1' => ord('W'),
                'id2' => ord('P'),
                'length' => strlen('review:v1'),
                'data' => 'review:v1',
            ],
            [
                'identifier' => 'PD',
                'id1' => ord('P'),
                'id2' => ord('D'),
                'length' => strlen('packet=tar'),
                'data' => 'packet=tar',
            ],
        ], array_map(static fn (array $member): array => $member['extraFields'][0], $members));
        $t->same([
            (int) sprintf('%u', crc32(substr($tarBytes, 0, $splitOffset))),
            (int) sprintf('%u', crc32(substr($tarBytes, $splitOffset))),
        ], array_map(static fn (array $member): int => $member['crc32'], $members));
        $firstHeaderSize = 10 + 2 + strlen($firstExtra)
            + strlen('review-packet.part-1.tar') + 1
            + strlen('split member one') + 1
            + 2;
        $secondHeaderSize = 10 + 2 + strlen($secondExtra)
            + strlen('review-packet.part-2.tar') + 1
            + strlen('split member two') + 1
            + 2;
        $t->same([0, $members[0]['memberSize']], array_map(static fn (array $member): int => $member['memberOffset'], $members));
        $t->same([$firstHeaderSize, $secondHeaderSize], array_map(static fn (array $member): int => $member['headerSize'], $members));
        $t->same([$firstHeaderSize, $members[0]['memberSize'] + $secondHeaderSize], array_map(static fn (array $member): int => $member['compressedDataOffset'], $members));
        $t->same([$members[0]['memberSize'] - 8, $members[0]['memberSize'] + $members[1]['memberSize'] - 8], array_map(static fn (array $member): int => $member['trailerOffset'], $members));
        $t->same([$members[0]['memberSize'], strlen($gzip)], array_map(static fn (array $member): int => $member['nextMemberOffset'], $members));
        $t->true($members[0]['headerCrc16'] !== null);
        $t->true($members[1]['headerCrc16'] !== null);
        $t->same("# Split gzip provenance\n\nReady for archive review.\n", $inspection['archive']->read('/packet/content.md'));
    },

    'inspects gzip member byte layout offsets for split package streams' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-byte-layout","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# GZIP byte layout\n\nReady for stream review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $splitOffset = 512;
        $firstExtra = pack('CCv', ord('B'), ord('L'), strlen('layout:1')) . 'layout:1';
        $secondExtra = pack('CCv', ord('B'), ord('L'), strlen('layout:2')) . 'layout:2';
        $firstMember = GzipStream::build(substr($tarBytes, 0, $splitOffset), [
            'filename' => 'byte-layout-part-1.tar',
            'comment' => 'byte layout one',
            'extraFieldData' => $firstExtra,
            'headerCrc' => true,
        ]);
        $secondMember = GzipStream::build(substr($tarBytes, $splitOffset), [
            'filename' => 'byte-layout-part-2.tar',
            'comment' => 'byte layout two',
            'extraFieldData' => $secondExtra,
            'headerCrc' => true,
        ]);
        $gzip = $firstMember . $secondMember;
        $firstHeaderSize = 10 + 2 + strlen($firstExtra)
            + strlen('byte-layout-part-1.tar') + 1
            + strlen('byte layout one') + 1
            + 2;
        $secondHeaderSize = 10 + 2 + strlen($secondExtra)
            + strlen('byte-layout-part-2.tar') + 1
            + strlen('byte layout two') + 1
            + 2;

        $inspection = ArchiveCompressionStream::inspectTarStreamAuto(
            $gzip,
            strlen($tarBytes),
            strlen($archive->read('/packet/manifest.json')) + strlen($archive->read('/packet/content.md'))
        );
        $members = $inspection['stream']['members'];
        $directMembers = GzipStream::members($gzip);

        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $inspection['format']);
        $t->same(2, $inspection['stream']['memberCount']);
        $t->same([0, strlen($firstMember)], array_map(static fn (array $member): int => $member['memberOffset'], $members));
        $t->same([$firstHeaderSize, $secondHeaderSize], array_map(static fn (array $member): int => $member['headerSize'], $members));
        $t->same([$firstHeaderSize, strlen($firstMember) + $secondHeaderSize], array_map(static fn (array $member): int => $member['compressedDataOffset'], $members));
        $t->same([strlen($firstMember) - 8, strlen($firstMember) + strlen($secondMember) - 8], array_map(static fn (array $member): int => $member['trailerOffset'], $members));
        $t->same([strlen($firstMember), strlen($gzip)], array_map(static fn (array $member): int => $member['nextMemberOffset'], $members));
        $t->same(array_map(static fn (array $member): int => $member['memberOffset'], $directMembers), array_map(static fn (array $member): int => $member['memberOffset'], $members));
        $t->same(array_map(static fn (array $member): int => $member['trailerOffset'], $directMembers), array_map(static fn (array $member): int => $member['trailerOffset'], $members));
        $t->same('{"source":"gzip-byte-layout","target":"wordpress"}', $inspection['archive']->read('/packet/manifest.json'));
    },

    'inspects tar entry byte layout for package review streams' => static function (TestRunner $t): void {
        $manifestBytes = '{"source":"tar-layout","target":"wordpress"}';
        $documentBytes = '<w:document><w:body><w:p>Layout-aware tar source</w:p></w:body></w:document>';
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/',
                'type' => TarArchiveEntry::TYPE_DIRECTORY,
                'modifiedAt' => 1780479058,
                'mode' => 0755,
            ],
            [
                'name' => 'packet/manifest.json',
                'data' => $manifestBytes,
                'modifiedAt' => 1780479059,
                'mode' => 0640,
                'uid' => 1001,
                'gid' => 1002,
                'userName' => 'wp-reviewer',
                'groupName' => 'import-team',
            ],
            [
                'name' => 'packet/generated-timestamps.xml',
                'data' => $documentBytes,
                'modifiedAt' => 1780479060,
                'accessedAt' => 1780479061,
                'changedAt' => 1780479062,
            ],
        ]);
        $tarBytes = $archive->bytes();
        $gzip = GzipStream::build($tarBytes, [
            'filename' => 'wordpress-layout-review.tar',
            'comment' => 'layout preflight',
        ]);
        $plainInspection = ArchiveCompressionStream::inspectTarStream(
            $tarBytes,
            ArchiveCompressionStream::FORMAT_TAR,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($documentBytes)
        );
        $gzipInspection = ArchiveCompressionStream::inspectPackageStreamAuto($gzip, strlen($tarBytes));
        $layouts = $plainInspection['entryLayouts'];
        $manifestLayout = $layouts[1];
        $timestampLayout = $layouts[2];

        $t->same(ArchiveCompressionStream::FORMAT_TAR, $plainInspection['format']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $gzipInspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $gzipInspection['format']);
        $t->same(['packet/', 'packet/manifest.json', 'packet/generated-timestamps.xml'], $plainInspection['entryNames']);
        $t->same($plainInspection['entryNames'], $gzipInspection['entryNames']);
        $t->same(3, $plainInspection['entryCount']);
        $t->same(2, $plainInspection['regularFileCount']);
        $t->same(1, $plainInspection['directoryCount']);
        $t->same(strlen($tarBytes), $plainInspection['uncompressedSize']);
        $t->same(strlen($manifestBytes) + strlen($documentBytes), $plainInspection['unpackedSize']);
        $t->same(1024, $plainInspection['trailingZeroBytes']);
        $t->same($plainInspection['endMarkerOffset'] + $plainInspection['trailingZeroBytes'], strlen($tarBytes));
        $t->same(0, $layouts[0]['headerOffset']);
        $t->same(512, $layouts[0]['dataOffset']);
        $t->same(512, $layouts[0]['dataEndOffset']);
        $t->same(0, $layouts[0]['paddedDataSize']);
        $t->same(512, $layouts[0]['recordSize']);
        $t->same(TarArchiveEntry::TYPE_DIRECTORY, $layouts[0]['type']);
        $t->same('packet/manifest.json', $manifestLayout['name']);
        $t->same(TarArchiveEntry::TYPE_FILE, $manifestLayout['type']);
        $t->same(0640, $manifestLayout['mode']);
        $t->same(1001, $manifestLayout['uid']);
        $t->same(1002, $manifestLayout['gid']);
        $t->same('wp-reviewer', $manifestLayout['userName']);
        $t->same('import-team', $manifestLayout['groupName']);
        $t->same(strlen($manifestBytes), $manifestLayout['size']);
        $t->same(512, $manifestLayout['headerOffset']);
        $t->same(1024, $manifestLayout['dataOffset']);
        $t->same(1024 + strlen($manifestBytes), $manifestLayout['dataEndOffset']);
        $t->same(512, $manifestLayout['paddedDataSize']);
        $t->same(1024, $manifestLayout['recordSize']);
        $t->same([], $manifestLayout['paxHeaderKeys']);
        $t->same('packet/generated-timestamps.xml', $timestampLayout['name']);
        $t->same(['atime', 'ctime'], $timestampLayout['paxHeaderKeys']);
        $t->same(2, $timestampLayout['paxHeaderCount']);
        $t->same(1780479061, $timestampLayout['accessedAt']);
        $t->same(1780479062, $timestampLayout['changedAt']);
        $t->same($timestampLayout['dataOffset'] - 512, $timestampLayout['headerOffset']);
        $t->same($timestampLayout['dataOffset'] + strlen($documentBytes), $timestampLayout['dataEndOffset']);
        $t->same($timestampLayout['headerOffset'] + $timestampLayout['recordSize'], $plainInspection['endMarkerOffset']);
        $t->same($plainInspection['entryLayouts'], $gzipInspection['entryLayouts']);
        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-layout-review.tar', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('layout preflight', $gzipInspection['stream']['members'][0]['comment']);
        $t->same($documentBytes, $gzipInspection['archive']->read('/packet/generated-timestamps.xml'));
    },

    'opens tar package fixtures through explicit compression stream formats' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"compressed-stream","target":"wordpress"}',
                'modifiedAt' => 1780479031,
            ],
            [
                'name' => 'packet/word/document.xml',
                'data' => '<w:document><w:body><w:p>Explicit archive stream dispatch</w:p></w:body></w:document>',
                'modifiedAt' => 1780479032,
            ],
        ]);

        $streams = [
            ArchiveCompressionStream::FORMAT_TAR => $archive->bytes(),
            ArchiveCompressionStream::FORMAT_GZIP_TAR => GzipStream::build($archive->bytes(), [
                'filename' => 'wordpress-import-packet.tar',
                'comment' => 'gzip stream dispatch',
                'headerCrc' => true,
            ]),
            ArchiveCompressionStream::FORMAT_ZLIB_TAR => DeflateStream::build($archive->bytes(), [
                'format' => DeflateStream::FORMAT_ZLIB,
            ]),
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_TAR => DeflateStream::build($archive->bytes(), [
                'format' => DeflateStream::FORMAT_RAW,
            ]),
            ArchiveCompressionStream::FORMAT_LZ4_TAR => Lz4Frame::skippableFrame('review metadata', 4)
                . Lz4Frame::build($archive->bytes(), [
                    'blockChecksum' => true,
                    'contentChecksum' => true,
                    'contentSize' => true,
                ]),
        ];

        $t->same([
            ArchiveCompressionStream::FORMAT_TAR,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            ArchiveCompressionStream::FORMAT_ZLIB_TAR,
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_TAR,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
        ], ArchiveCompressionStream::supportedTarFormats());

        foreach ($streams as $format => $bytes) {
            $roundTrip = ArchiveCompressionStream::openTar($bytes, $format, strlen($archive->bytes()), strlen($archive->read('packet/word/document.xml')) + 64);

            $t->same(['packet/manifest.json', 'packet/word/document.xml'], $roundTrip->names());
            $t->same('{"source":"compressed-stream","target":"wordpress"}', $roundTrip->read('/packet/manifest.json'));
            $t->same('<w:document><w:body><w:p>Explicit archive stream dispatch</w:p></w:body></w:document>', $roundTrip->read('/packet/word/document.xml'));
            $t->same($archive->bytes(), ArchiveCompressionStream::decodeTarBytes($bytes, $format, strlen($archive->bytes())));
        }
    },

    'auto-detects bounded tar package fixture compression streams' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"auto-detected-stream","target":"wordpress"}',
                'modifiedAt' => 1780479033,
            ],
            [
                'name' => 'packet/word/document.xml',
                'data' => '<w:document><w:body><w:p>Auto-detected archive stream dispatch</w:p></w:body></w:document>',
                'modifiedAt' => 1780479034,
            ],
        ]);

        $streams = [
            ArchiveCompressionStream::FORMAT_TAR => $archive->bytes(),
            ArchiveCompressionStream::FORMAT_GZIP_TAR => GzipStream::build($archive->bytes(), [
                'filename' => 'wordpress-auto-packet.tar',
                'comment' => 'auto-detected gzip stream',
                'headerCrc' => true,
            ]),
            ArchiveCompressionStream::FORMAT_ZLIB_TAR => DeflateStream::build($archive->bytes(), [
                'format' => DeflateStream::FORMAT_ZLIB,
                'compressionLevel' => 9,
            ]),
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_TAR => DeflateStream::build($archive->bytes(), [
                'format' => DeflateStream::FORMAT_RAW,
                'compressionLevel' => 9,
            ]),
            ArchiveCompressionStream::FORMAT_LZ4_TAR => Lz4Frame::skippableFrame('auto-detect reviewer metadata', 5)
                . Lz4Frame::build($archive->bytes(), [
                    'blockChecksum' => true,
                    'contentChecksum' => true,
                    'contentSize' => true,
                ]),
        ];

        foreach ($streams as $expectedFormat => $bytes) {
            $roundTrip = ArchiveCompressionStream::openTarAuto(
                $bytes,
                strlen($archive->bytes()),
                strlen($archive->read('/packet/manifest.json')) + strlen($archive->read('/packet/word/document.xml'))
            );

            $t->same($expectedFormat, ArchiveCompressionStream::detectTarFormat($bytes, strlen($archive->bytes())));
            $t->same($archive->bytes(), ArchiveCompressionStream::decodeTarBytesAuto($bytes, strlen($archive->bytes())));
            $t->same(['packet/manifest.json', 'packet/word/document.xml'], $roundTrip->names());
            $t->same('{"source":"auto-detected-stream","target":"wordpress"}', $roundTrip->read('/packet/manifest.json'));
            $t->same('<w:document><w:body><w:p>Auto-detected archive stream dispatch</w:p></w:body></w:document>', $roundTrip->read('/packet/word/document.xml'));
        }
    },

    'preflights split gzip members and lz4 frames as one tar review packet' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/',
                'type' => TarArchiveEntry::TYPE_DIRECTORY,
                'modifiedAt' => 1780479037,
            ],
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"split-stream","target":"wordpress"}',
                'modifiedAt' => 1780479038,
            ],
            [
                'name' => 'packet/word/document.xml',
                'data' => '<w:document><w:body><w:p>Split stream archive dispatch</w:p></w:body></w:document>',
                'modifiedAt' => 1780479039,
            ],
        ]);
        $tarBytes = $archive->bytes();
        $splitOffset = 700;
        $unpackedBytes = strlen($archive->read('/packet/manifest.json'))
            + strlen($archive->read('/packet/word/document.xml'));

        $gzipSplit = GzipStream::build(substr($tarBytes, 0, $splitOffset), [
            'filename' => 'wordpress-import-packet.part-1.tar',
            'comment' => 'split tar member one',
            'extraFlags' => 4,
            'operatingSystem' => 3,
            'extraFieldData' => pack('CCv', ord('W'), ord('P'), strlen('split:1')) . 'split:1',
            'headerCrc' => true,
        ]) . GzipStream::build(substr($tarBytes, $splitOffset), [
            'filename' => 'wordpress-import-packet.part-2.tar',
            'comment' => 'split tar member two',
            'extraFlags' => 2,
            'operatingSystem' => 255,
            'extraFieldData' => pack('CCv', ord('P'), ord('D'), strlen('split:2')) . 'split:2',
            'headerCrc' => true,
        ]);
        $gzipInspection = ArchiveCompressionStream::inspectTarStreamAuto(
            $gzipSplit,
            strlen($tarBytes),
            $unpackedBytes
        );

        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $gzipInspection['format']);
        $t->same($tarBytes, $gzipInspection['tarBytes']);
        $t->same(['packet/', 'packet/manifest.json', 'packet/word/document.xml'], $gzipInspection['entryNames']);
        $t->same(3, $gzipInspection['entryCount']);
        $t->same($unpackedBytes, $gzipInspection['unpackedSize']);
        $t->same(strlen($tarBytes), $gzipInspection['uncompressedSize']);
        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same(2, $gzipInspection['stream']['memberCount']);
        $t->same([
            'wordpress-import-packet.part-1.tar',
            'wordpress-import-packet.part-2.tar',
        ], array_map(static fn (array $member): ?string => $member['filename'], $gzipInspection['stream']['members']));
        $t->same([
            $splitOffset,
            strlen($tarBytes) - $splitOffset,
        ], array_map(static fn (array $member): int => $member['uncompressedSize'], $gzipInspection['stream']['members']));
        $t->same([4, 2], array_map(static fn (array $member): int => $member['extraFlags'], $gzipInspection['stream']['members']));
        $t->same([3, 255], array_map(static fn (array $member): int => $member['operatingSystem'], $gzipInspection['stream']['members']));
        $t->same([
            [
                'identifier' => 'WP',
                'id1' => ord('W'),
                'id2' => ord('P'),
                'length' => strlen('split:1'),
                'data' => 'split:1',
            ],
            [
                'identifier' => 'PD',
                'id1' => ord('P'),
                'id2' => ord('D'),
                'length' => strlen('split:2'),
                'data' => 'split:2',
            ],
        ], array_map(static fn (array $member): array => $member['extraFields'][0], $gzipInspection['stream']['members']));
        $t->same([
            (int) sprintf('%u', crc32(substr($tarBytes, 0, $splitOffset))),
            (int) sprintf('%u', crc32(substr($tarBytes, $splitOffset))),
        ], array_map(static fn (array $member): int => $member['crc32'], $gzipInspection['stream']['members']));
        $t->same('<w:document><w:body><w:p>Split stream archive dispatch</w:p></w:body></w:document>', $gzipInspection['archive']->read('/packet/word/document.xml'));

        $lz4Split = Lz4Frame::skippableFrame('split tar reviewer metadata', 8)
            . Lz4Frame::build(substr($tarBytes, 0, $splitOffset), [
                'contentSize' => true,
                'contentChecksum' => true,
            ])
            . Lz4Frame::build(substr($tarBytes, $splitOffset), [
                'contentSize' => true,
                'contentChecksum' => true,
            ]);
        $lz4Inspection = ArchiveCompressionStream::inspectTarStream(
            $lz4Split,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            strlen($tarBytes),
            $unpackedBytes
        );

        $t->same(ArchiveCompressionStream::FORMAT_LZ4_TAR, $lz4Inspection['format']);
        $t->same($tarBytes, $lz4Inspection['tarBytes']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(3, $lz4Inspection['stream']['frameCount']);
        $t->same(2, $lz4Inspection['stream']['dataFrameCount']);
        $t->same(1, $lz4Inspection['stream']['skippableFrameCount']);
        $t->same(2, $lz4Inspection['stream']['blockCount']);
        $t->same(['skippable', 'frame', 'frame'], array_map(static fn (array $frame): string => $frame['type'], $lz4Inspection['stream']['frames']));
        $t->same('split tar reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
        $t->same('{"source":"split-stream","target":"wordpress"}', $lz4Inspection['archive']->read('/packet/manifest.json'));

        $separateArchive = TarArchive::fromEntries([
            [
                'name' => 'packet/second-manifest.json',
                'data' => '{"source":"separate-tar"}',
            ],
        ]);
        $separateCompleteGzipMembers = GzipStream::build($tarBytes, [
            'filename' => 'first-complete.tar',
        ]) . GzipStream::build($separateArchive->bytes(), [
            'filename' => 'second-complete.tar',
        ]);

        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectTarStreamAuto($separateCompleteGzipMembers));
    },

    'opens zip package fixtures through explicit compression stream formats' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => '_rels/.rels',
                'data' => '<Relationships><Relationship Target="word/document.xml"/></Relationships>',
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Compressed ZIP stream dispatch</w:p></w:body></w:document>',
            ],
            [
                'name' => 'word/media/',
                'compressionMethod' => 0,
            ],
        ], 'wordpress compressed package review');
        $zipBytes = $package->bytes();
        $entryBytes = strlen($package->read('/[Content_Types].xml'))
            + strlen($package->read('/_rels/.rels'))
            + strlen($package->read('/word/document.xml'));

        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-import-package.zip',
                'comment' => 'gzip zip package fixture',
                'headerCrc' => true,
            ]),
            ArchiveCompressionStream::FORMAT_ZLIB_ZIP => DeflateStream::build($zipBytes, [
                'format' => DeflateStream::FORMAT_ZLIB,
                'compressionLevel' => 9,
            ]),
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_ZIP => DeflateStream::build($zipBytes, [
                'format' => DeflateStream::FORMAT_RAW,
                'compressionLevel' => 9,
            ]),
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('zip package reviewer metadata', 9)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        $t->same([
            ArchiveCompressionStream::FORMAT_ZIP,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            ArchiveCompressionStream::FORMAT_ZLIB_ZIP,
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_ZIP,
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
        ], ArchiveCompressionStream::supportedZipFormats());

        foreach ($streams as $format => $bytes) {
            $roundTrip = ArchiveCompressionStream::openZip($bytes, $format, strlen($zipBytes));
            $inspection = ArchiveCompressionStream::inspectZipStream($bytes, $format, strlen($zipBytes));

            $t->same($zipBytes, ArchiveCompressionStream::decodeZipBytes($bytes, $format, strlen($zipBytes)));
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same($format, $inspection['format']);
            $t->same($package->names(), $roundTrip->names());
            $t->same($package->names(), $inspection['entryNames']);
            $t->same(4, $inspection['entryCount']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same($entryBytes, $inspection['entryUncompressedSize']);
            $t->same('wordpress compressed package review', $inspection['package']->packageComment());
            $t->same('<w:document><w:body><w:p>Compressed ZIP stream dispatch</w:p></w:body></w:document>', $roundTrip->read('/word/document.xml'));
        }

        $gzipInspection = ArchiveCompressionStream::inspectZipStream($streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP], ArchiveCompressionStream::FORMAT_GZIP_ZIP, strlen($zipBytes));
        $lz4Inspection = ArchiveCompressionStream::inspectZipStream($streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP], ArchiveCompressionStream::FORMAT_LZ4_ZIP, strlen($zipBytes));

        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same(1, $gzipInspection['stream']['memberCount']);
        $t->same('wordpress-import-package.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('gzip zip package fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same(1, $lz4Inspection['stream']['skippableFrameCount']);
        $t->same('zip package reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
    },

    'preflights encrypted zip package streams without exposing entries' => static function (TestRunner $t) use ($zipFixtureBytes): void {
        $utf8 = 0x0800;
        $winZipAesExtra = pack('vvv', 0x9901, 7, 2) . 'AE' . "\x03" . pack('v', 8);
        $zipBytes = $zipFixtureBytes([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'flags' => $utf8,
            ],
            [
                'name' => 'word/encrypted.xml',
                'data' => '<w:document>traditional encrypted payload bytes</w:document>',
                'flags' => $utf8 | 0x0001,
            ],
            [
                'name' => 'word/strong.xml',
                'data' => '<w:document>strong encrypted payload bytes</w:document>',
                'flags' => $utf8 | 0x0040,
            ],
            [
                'name' => 'word/aes.xml',
                'data' => '<w:document>AES metadata payload bytes</w:document>',
                'flags' => $utf8 | 0x2000,
                'extra' => $winZipAesExtra,
            ],
            [
                'name' => 'word/local-only.xml',
                'data' => '<w:document>local header encrypted payload bytes</w:document>',
                'flags' => $utf8,
                'localFlags' => $utf8 | 0x0001,
            ],
        ], 'encrypted review fixture');

        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-encrypted-package.zip',
                'comment' => 'encrypted zip preflight fixture',
                'headerCrc' => true,
            ]),
            ArchiveCompressionStream::FORMAT_ZLIB_ZIP => DeflateStream::build($zipBytes, [
                'format' => DeflateStream::FORMAT_ZLIB,
                'compressionLevel' => 9,
            ]),
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_ZIP => DeflateStream::build($zipBytes, [
                'format' => DeflateStream::FORMAT_RAW,
                'compressionLevel' => 9,
            ]),
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('encrypted zip reviewer metadata', 10)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        foreach ($streams as $format => $bytes) {
            $inspection = ArchiveCompressionStream::inspectZipEncryptionPolicy($bytes, $format, strlen($zipBytes));

            $t->same($zipBytes, ArchiveCompressionStream::decodeZipBytes($bytes, $format, strlen($zipBytes)));
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same($format, $inspection['format']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same(5, $inspection['entryCount']);
            $t->same(4, $inspection['encryptedEntryCount']);
            $t->same(2, $inspection['traditionalEncryptionEntryCount']);
            $t->same(1, $inspection['strongEncryptionEntryCount']);
            $t->same(1, $inspection['centralDirectoryEncryptionEntryCount']);
            $t->same(1, $inspection['winZipAesEntryCount']);
            $t->same(true, $inspection['hasEncryptedEntries']);
            $t->same(false, $inspection['isSupportedByBoundedReader']);
            $t->same('encrypted-zip-entries-blocked', $inspection['extractionPolicy']);
            $t->same(['encrypted-zip-entries'], $inspection['issues']);
            $t->same([
                'word/encrypted.xml',
                'word/strong.xml',
                'word/aes.xml',
                'word/local-only.xml',
            ], array_column($inspection['encryptedEntries'], 'name'));
            $t->same('metadata', $inspection['entries'][0]['policy']);
            $t->same('blocked', $inspection['encryptedEntries'][0]['policy']);
            $t->same(['traditional'], $inspection['encryptedEntries'][0]['encryptionTypes']);
            $t->same([
                'zip-encrypted-entry-not-extracted',
                'zip-traditional-encryption',
            ], $inspection['encryptedEntries'][0]['diagnostics']);
            $t->same(['strong'], $inspection['encryptedEntries'][1]['encryptionTypes']);
            $t->same([
                'zip-encrypted-entry-not-extracted',
                'zip-strong-encryption',
            ], $inspection['encryptedEntries'][1]['diagnostics']);
            $t->same(['central-directory', 'winzip-aes'], $inspection['encryptedEntries'][2]['encryptionTypes']);
            $t->same([
                'zip-encrypted-entry-not-extracted',
                'zip-central-directory-encryption',
                'zip-winzip-aes-extra-field',
            ], $inspection['encryptedEntries'][2]['diagnostics']);
            $t->same([0x9901], $inspection['encryptedEntries'][2]['centralExtraFieldIds']);
            $t->same([0x9901], $inspection['encryptedEntries'][2]['localExtraFieldIds']);
            $t->same(['traditional'], $inspection['encryptedEntries'][3]['encryptionTypes']);
            $t->same([
                'zip-encrypted-entry-not-extracted',
                'zip-traditional-encryption',
                'zip-local-header-flags-mismatch',
            ], $inspection['encryptedEntries'][3]['diagnostics']);
            $t->same(false, array_key_exists('package', $inspection));
            $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectZipStream($bytes, $format, strlen($zipBytes)));
        }

        $gzipInspection = ArchiveCompressionStream::inspectZipEncryptionPolicy(
            $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectZipEncryptionPolicy(
            $streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP],
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            strlen($zipBytes)
        );

        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-encrypted-package.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('encrypted zip preflight fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same('encrypted zip reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
    },

    'auto-detects bounded zip package fixture compression streams' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Auto-detected ZIP stream dispatch</w:p></w:body></w:document>',
            ],
        ]);
        $zipBytes = $package->bytes();
        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-auto-package.zip',
            ]),
            ArchiveCompressionStream::FORMAT_ZLIB_ZIP => DeflateStream::build($zipBytes, [
                'format' => DeflateStream::FORMAT_ZLIB,
            ]),
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_ZIP => DeflateStream::build($zipBytes, [
                'format' => DeflateStream::FORMAT_RAW,
            ]),
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::build($zipBytes, [
                'contentSize' => true,
                'contentChecksum' => true,
            ]),
        ];

        foreach ($streams as $expectedFormat => $bytes) {
            $roundTrip = ArchiveCompressionStream::openZipAuto($bytes, strlen($zipBytes));

            $t->same($expectedFormat, ArchiveCompressionStream::detectZipFormat($bytes, strlen($zipBytes)));
            $t->same($zipBytes, ArchiveCompressionStream::decodeZipBytesAuto($bytes, strlen($zipBytes)));
            $t->same(['[Content_Types].xml', 'word/document.xml'], $roundTrip->names());
            $t->same('<w:document><w:body><w:p>Auto-detected ZIP stream dispatch</w:p></w:body></w:document>', $roundTrip->read('/word/document.xml'));
        }

        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::detectZipFormat('not a zip package'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ArchiveCompressionStream::openZipAuto($streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP], strlen($zipBytes) - 1));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::decodeZipBytesAuto($zipBytes, -1));
    },

    'auto-detects tar and zip package kinds from opaque compressed streams' => static function (TestRunner $t): void {
        $tarPacket = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"generic-archive-kind","format":"tar"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Generic TAR packet\n\nReady for archive review.\n",
            ],
        ]);
        $zipPackage = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Generic ZIP package stream</w:p></w:body></w:document>',
            ],
        ]);

        $tarUpload = GzipStream::build($tarPacket->bytes(), [
            'filename' => 'review-packet.tar',
            'comment' => 'opaque gzip tar upload',
        ]);
        $zipUpload = Lz4Frame::skippableFrame('opaque upload metadata', 10)
            . Lz4Frame::build($zipPackage->bytes(), [
                'contentSize' => true,
                'contentChecksum' => true,
            ]);
        $tarInspection = ArchiveCompressionStream::inspectPackageStreamAuto($tarUpload, strlen($tarPacket->bytes()));
        $zipInspection = ArchiveCompressionStream::inspectPackageStreamAuto($zipUpload, strlen($zipPackage->bytes()));

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, ArchiveCompressionStream::detectPackageKindAuto($tarUpload, strlen($tarPacket->bytes())));
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $tarInspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $tarInspection['format']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $tarInspection['entryNames']);
        $t->same('gzip', $tarInspection['stream']['type']);
        $t->same('review-packet.tar', $tarInspection['stream']['members'][0]['filename']);
        $t->same("# Generic TAR packet\n\nReady for archive review.\n", $tarInspection['archive']->read('/packet/content.md'));

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, ArchiveCompressionStream::detectPackageKindAuto($zipUpload, strlen($zipPackage->bytes())));
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $zipInspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_LZ4_ZIP, $zipInspection['format']);
        $t->same(['[Content_Types].xml', 'word/document.xml'], $zipInspection['entryNames']);
        $t->same('lz4', $zipInspection['stream']['type']);
        $t->same(2, $zipInspection['stream']['frameCount']);
        $t->same('opaque upload metadata', $zipInspection['stream']['frames'][0]['data']);
        $t->same('<w:document><w:body><w:p>Generic ZIP package stream</w:p></w:body></w:document>', $zipInspection['package']->read('/word/document.xml'));

        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::detectPackageKindAuto('not an archive package'));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectPackageStreamAuto($zipUpload, strlen($zipPackage->bytes()) - 1));
    },

    'discovers nested archive package streams without extracting package entries' => static function (TestRunner $t): void {
        $innerZip = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Nested ZIP review packet</w:p></w:body></w:document>',
            ],
        ]);
        $innerTar = TarArchive::fromEntries([
            [
                'name' => 'packet/inner.md',
                'data' => "# Nested TAR packet\n\nReady for review.\n",
            ],
            [
                'name' => 'packet/deeper/export.zip',
                'data' => $innerZip->bytes(),
            ],
        ]);
        $gzipInnerTar = GzipStream::build($innerTar->bytes(), [
            'filename' => 'review-inner.tar',
            'comment' => 'nested gzip tar packet',
        ]);
        $gzipInnerZip = GzipStream::build($innerZip->bytes(), [
            'filename' => 'signature-only.zip',
        ]);
        $outerTar = TarArchive::fromEntries([
            [
                'name' => 'packet/content.md',
                'data' => "# Outer packet\n\nDo not treat this as an archive.\n",
            ],
            [
                'name' => 'packet/nested/review.tar.gz',
                'data' => $gzipInnerTar,
            ],
            [
                'name' => 'packet/nested/document.docx',
                'data' => $innerZip->bytes(),
            ],
            [
                'name' => 'packet/nested/signature.bin',
                'data' => $gzipInnerZip,
            ],
            [
                'name' => 'packet/nested/broken.zip',
                'data' => "PK\x03\x04truncated-review-candidate",
            ],
        ]);
        $upload = GzipStream::build($outerTar->bytes(), [
            'filename' => 'wordpress-nested-review.tar',
            'comment' => 'outer archive with nested packages',
        ]);

        $inspection = ArchiveCompressionStream::inspectNestedPackageStreamsAuto(
            $upload,
            strlen($outerTar->bytes()),
            strlen($outerTar->bytes()),
            2
        );
        $depthOne = ArchiveCompressionStream::inspectNestedPackageStreamsAuto(
            $upload,
            strlen($outerTar->bytes()),
            strlen($outerTar->bytes()),
            1
        );

        $byPath = [];
        foreach ($inspection['entries'] as $entry) {
            $byPath[$entry['path']] = $entry;
        }

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $inspection['rootKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $inspection['rootFormat']);
        $t->same('metadata-only-no-extraction', $inspection['policy']);
        $t->same(2, $inspection['maxDepth']);
        $t->same(5, $inspection['candidateCount']);
        $t->same(4, $inspection['packageCount']);
        $t->same(1, $inspection['diagnosticCount']);
        $t->same([
            'packet/nested/review.tar.gz',
            'packet/nested/review.tar.gz!packet/deeper/export.zip',
            'packet/nested/document.docx',
            'packet/nested/signature.bin',
            'packet/nested/broken.zip',
        ], array_map(static fn (array $entry): string => $entry['path'], $inspection['entries']));

        $t->same('package', $byPath['packet/nested/review.tar.gz']['status']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $byPath['packet/nested/review.tar.gz']['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $byPath['packet/nested/review.tar.gz']['format']);
        $t->same(['extension:gzip-tar', 'signature:gzip'], $byPath['packet/nested/review.tar.gz']['candidateReasons']);
        $t->same(['packet/inner.md', 'packet/deeper/export.zip'], $byPath['packet/nested/review.tar.gz']['entryNames']);
        $t->same('tar', $byPath['packet/nested/review.tar.gz']['parentKind']);
        $t->same(1, $byPath['packet/nested/review.tar.gz']['depth']);
        $t->same(strlen($gzipInnerTar), $byPath['packet/nested/review.tar.gz']['size']);
        $t->same([], $byPath['packet/nested/review.tar.gz']['diagnostics']);

        $t->same('package', $byPath['packet/nested/review.tar.gz!packet/deeper/export.zip']['status']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $byPath['packet/nested/review.tar.gz!packet/deeper/export.zip']['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $byPath['packet/nested/review.tar.gz!packet/deeper/export.zip']['format']);
        $t->same(['extension:zip', 'signature:zip'], $byPath['packet/nested/review.tar.gz!packet/deeper/export.zip']['candidateReasons']);
        $t->same(['[Content_Types].xml', 'word/document.xml'], $byPath['packet/nested/review.tar.gz!packet/deeper/export.zip']['entryNames']);
        $t->same('tar', $byPath['packet/nested/review.tar.gz!packet/deeper/export.zip']['parentKind']);
        $t->same(2, $byPath['packet/nested/review.tar.gz!packet/deeper/export.zip']['depth']);

        $t->same('package', $byPath['packet/nested/document.docx']['status']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $byPath['packet/nested/document.docx']['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $byPath['packet/nested/document.docx']['format']);
        $t->same(['extension:zip-package', 'signature:zip'], $byPath['packet/nested/document.docx']['candidateReasons']);
        $t->same(2, $byPath['packet/nested/document.docx']['entryCount']);

        $t->same('package', $byPath['packet/nested/signature.bin']['status']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $byPath['packet/nested/signature.bin']['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $byPath['packet/nested/signature.bin']['format']);
        $t->same(['signature:gzip'], $byPath['packet/nested/signature.bin']['candidateReasons']);
        $t->same(['[Content_Types].xml', 'word/document.xml'], $byPath['packet/nested/signature.bin']['entryNames']);

        $t->same('unreadable', $byPath['packet/nested/broken.zip']['status']);
        $t->same(null, $byPath['packet/nested/broken.zip']['kind']);
        $t->same(null, $byPath['packet/nested/broken.zip']['format']);
        $t->same(['extension:zip', 'signature:zip'], $byPath['packet/nested/broken.zip']['candidateReasons']);
        $t->true(str_contains($byPath['packet/nested/broken.zip']['diagnostics'][0] ?? '', 'nested-package-detection-failed:'));

        $t->same(4, $depthOne['candidateCount']);
        $t->same(3, $depthOne['packageCount']);
        $t->same(1, $depthOne['diagnosticCount']);
        $t->same([
            'packet/nested/review.tar.gz',
            'packet/nested/document.docx',
            'packet/nested/signature.bin',
            'packet/nested/broken.zip',
        ], array_map(static fn (array $entry): string => $entry['path'], $depthOne['entries']));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectNestedPackageStreamsAuto($upload, null, null, -1));
    },

    'preflights archive expansion ratios before conversion handoff' => static function (TestRunner $t): void {
        $manifestBytes = '{"source":"archive-bomb-policy","target":"wordpress"}';
        $contentTypeBytes = '<Types><Default Extension="md" ContentType="text/markdown"/></Types>';
        $largeMarkdown = str_repeat('A', 4096);
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => $manifestBytes,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $largeMarkdown,
            ],
        ]);
        $tarBytes = $archive->bytes();
        $gzip = GzipStream::build($tarBytes, [
            'filename' => 'wordpress-compressed-review.tar',
            'comment' => 'bounded expansion-ratio preflight',
            'compressionLevel' => 9,
        ]);
        $tarPolicy = ArchiveCompressionStream::inspectArchiveBombPolicyAuto(
            $gzip,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($largeMarkdown),
            4.0,
            4.0,
            4.0
        );
        $tarDefaultPolicy = ArchiveCompressionStream::inspectArchiveBombPolicyAuto(
            $gzip,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($largeMarkdown)
        );

        $zipPackage = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => $contentTypeBytes,
                'compressionMethod' => 0,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $largeMarkdown,
            ],
        ]);
        $zipBytes = $zipPackage->bytes();
        $zipPolicy = ArchiveCompressionStream::inspectArchiveBombPolicyAuto(
            $zipBytes,
            null,
            null,
            4.0,
            4.0,
            4.0
        );
        $zipDefaultPolicy = ArchiveCompressionStream::inspectArchiveBombPolicyAuto($zipBytes);

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $tarPolicy['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $tarPolicy['format']);
        $t->same(strlen($gzip), $tarPolicy['compressedSize']);
        $t->same(strlen($tarBytes), $tarPolicy['decodedPackageSize']);
        $t->same(strlen($manifestBytes) + strlen($largeMarkdown), $tarPolicy['entryUncompressedSize']);
        $t->same(2, $tarPolicy['entryCount']);
        $t->true($tarPolicy['streamCompressionRatio'] > 4.0);
        $t->true($tarPolicy['totalExpansionRatio'] > 4.0);
        $t->true($tarPolicy['packageExpansionRatio'] < 4.0);
        $t->same([
            'archive-stream-compression-ratio-exceeds-threshold',
            'archive-total-expansion-ratio-exceeds-threshold',
        ], $tarPolicy['diagnostics']);
        $t->same(2, $tarPolicy['diagnosticCount']);
        $t->same('review-before-conversion', $tarPolicy['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $tarPolicy['extractionPolicy']);
        $t->same('gzip', $tarPolicy['stream']['type']);
        $t->same('wordpress-compressed-review.tar', $tarPolicy['stream']['members'][0]['filename']);
        $t->same('bounded expansion-ratio preflight', $tarPolicy['stream']['members'][0]['comment']);
        $t->same('within-thresholds', $tarDefaultPolicy['handoffPolicy']);
        $t->same([], $tarDefaultPolicy['diagnostics']);

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $zipPolicy['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $zipPolicy['format']);
        $t->same(strlen($zipBytes), $zipPolicy['compressedSize']);
        $t->same(strlen($zipBytes), $zipPolicy['decodedPackageSize']);
        $t->same(strlen($contentTypeBytes) + strlen($largeMarkdown), $zipPolicy['entryUncompressedSize']);
        $t->same(2, $zipPolicy['entryCount']);
        $t->same(1.0, $zipPolicy['streamCompressionRatio']);
        $t->true($zipPolicy['packageExpansionRatio'] > 4.0);
        $t->true($zipPolicy['totalExpansionRatio'] > 4.0);
        $t->same([
            'archive-package-expansion-ratio-exceeds-threshold',
            'archive-total-expansion-ratio-exceeds-threshold',
        ], $zipPolicy['diagnostics']);
        $t->same('review-before-conversion', $zipPolicy['handoffPolicy']);
        $t->same('plain-zip', $zipPolicy['stream']['type']);
        $t->same('within-thresholds', $zipDefaultPolicy['handoffPolicy']);
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectArchiveBombPolicyAuto(
            $gzip,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($largeMarkdown),
            0.0
        ));
    },

    'builds and reads bounded raw and zlib deflate package fixture streams' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"deflate-tar","target":"wordpress"}',
                'modifiedAt' => 1780479029,
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Deflate archive\n\nReady for import review.\n",
                'modifiedAt' => 1780479030,
            ],
        ]);

        $zlib = DeflateStream::build($archive->bytes(), [
            'format' => DeflateStream::FORMAT_ZLIB,
            'compressionLevel' => 9,
        ]);
        $raw = DeflateStream::build($archive->bytes(), [
            'format' => DeflateStream::FORMAT_RAW,
            'compressionLevel' => 9,
        ]);
        $metadata = DeflateStream::inspectZlib($zlib);
        $rawMetadata = DeflateStream::inspectRaw($raw);
        $zlibRoundTrip = TarArchive::fromString(DeflateStream::decode($zlib));
        $rawRoundTrip = TarArchive::fromString(DeflateStream::decode($raw, DeflateStream::FORMAT_RAW));

        $t->same(DeflateStream::FORMAT_ZLIB, $metadata['format']);
        $t->same(8, $metadata['compressionMethod']);
        $t->same(32768, $metadata['windowSize']);
        $t->same('maximum', $metadata['compressionLevelHint']);
        $t->same(strlen($archive->bytes()), $metadata['uncompressedSize']);
        $t->same(strlen($zlib) - 6, $metadata['compressedSize']);
        $t->same(2, $metadata['headerSize']);
        $t->same(2, $metadata['compressedPayloadOffset']);
        $t->same(strlen($zlib) - 6, $metadata['compressedPayloadSize']);
        $t->same(strlen($zlib) - 4, $metadata['trailerOffset']);
        $t->same(4, $metadata['trailerSize']);
        $t->same(strlen($zlib), $metadata['consumedBytes']);
        $t->same($archive->bytes(), $metadata['data']);
        $t->same('{"source":"deflate-tar","target":"wordpress"}', $zlibRoundTrip->read('/packet/manifest.json'));
        $t->same("# Deflate archive\n\nReady for import review.\n", $zlibRoundTrip->read('/packet/content.md'));
        $t->same($zlibRoundTrip->read('/packet/content.md'), $rawRoundTrip->read('packet/content.md'));
        $t->same($metadata['adler32'], intval(hash('adler32', $archive->bytes()), 16));
        $t->same(sprintf('%08x', $metadata['adler32']), $metadata['adler32Hex']);
        $t->same(DeflateStream::FORMAT_RAW, $rawMetadata['format']);
        $t->same(strlen($raw), $rawMetadata['compressedSize']);
        $t->same(strlen($raw), $rawMetadata['compressedPayloadSize']);
        $t->same(0, $rawMetadata['headerSize']);
        $t->same(0, $rawMetadata['compressedPayloadOffset']);
        $t->same(0, $rawMetadata['trailerSize']);
        $t->same(null, $rawMetadata['trailerOffset']);
        $t->same(strlen($raw), $rawMetadata['consumedBytes']);
    },

    'inspects raw and zlib deflate stream provenance for tar review packets' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"deflate-provenance","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Deflate provenance\n\nReady for stream review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $unpackedBytes = strlen($archive->read('/packet/manifest.json'))
            + strlen($archive->read('/packet/content.md'));
        $zlib = DeflateStream::build($tarBytes, [
            'format' => DeflateStream::FORMAT_ZLIB,
            'compressionLevel' => 9,
        ]);
        $raw = DeflateStream::build($tarBytes, [
            'format' => DeflateStream::FORMAT_RAW,
            'compressionLevel' => 9,
        ]);

        $zlibInspection = ArchiveCompressionStream::inspectTarStream(
            $zlib,
            ArchiveCompressionStream::FORMAT_ZLIB_TAR,
            strlen($tarBytes),
            $unpackedBytes
        );
        $rawInspection = ArchiveCompressionStream::inspectTarStream(
            $raw,
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_TAR,
            strlen($tarBytes),
            $unpackedBytes
        );

        $t->same(ArchiveCompressionStream::FORMAT_ZLIB_TAR, $zlibInspection['format']);
        $t->same('zlib-deflate', $zlibInspection['stream']['type']);
        $t->same(1, $zlibInspection['stream']['memberCount']);
        $t->same(strlen($zlib), $zlibInspection['stream']['compressedSize']);
        $t->same(strlen($zlib) - 6, $zlibInspection['stream']['compressedPayloadSize']);
        $t->same(2, $zlibInspection['stream']['headerSize']);
        $t->same(2, $zlibInspection['stream']['compressedPayloadOffset']);
        $t->same(strlen($zlib) - 4, $zlibInspection['stream']['trailerOffset']);
        $t->same(4, $zlibInspection['stream']['trailerSize']);
        $t->same(strlen($zlib), $zlibInspection['stream']['consumedBytes']);
        $t->same(strlen($tarBytes), $zlibInspection['stream']['uncompressedSize']);
        $t->same(32768, $zlibInspection['stream']['windowSize']);
        $t->same('maximum', $zlibInspection['stream']['compressionLevelHint']);
        $t->same(intval(hash('adler32', $tarBytes), 16), $zlibInspection['stream']['adler32']);
        $t->same(sprintf('%08x', intval(hash('adler32', $tarBytes), 16)), $zlibInspection['stream']['adler32Hex']);
        $t->same("# Deflate provenance\n\nReady for stream review.\n", $zlibInspection['archive']->read('/packet/content.md'));

        $t->same(ArchiveCompressionStream::FORMAT_RAW_DEFLATE_TAR, $rawInspection['format']);
        $t->same('raw-deflate', $rawInspection['stream']['type']);
        $t->same(1, $rawInspection['stream']['memberCount']);
        $t->same(strlen($raw), $rawInspection['stream']['compressedSize']);
        $t->same(strlen($raw), $rawInspection['stream']['compressedPayloadSize']);
        $t->same(0, $rawInspection['stream']['headerSize']);
        $t->same(0, $rawInspection['stream']['compressedPayloadOffset']);
        $t->same(null, $rawInspection['stream']['trailerOffset']);
        $t->same(0, $rawInspection['stream']['trailerSize']);
        $t->same(strlen($raw), $rawInspection['stream']['consumedBytes']);
        $t->same(strlen($tarBytes), $rawInspection['stream']['uncompressedSize']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $rawInspection['entryNames']);
        $t->same('{"source":"deflate-provenance","target":"wordpress"}', $rawInspection['archive']->read('/packet/manifest.json'));
    },

    'rejects malformed deflate streams and bounded decode overflows' => static function (TestRunner $t): void {
        $zlib = DeflateStream::build('review packet', [
            'format' => DeflateStream::FORMAT_ZLIB,
        ]);
        $raw = DeflateStream::build('review packet', [
            'format' => DeflateStream::FORMAT_RAW,
        ]);
        $badHeaderCheck = substr_replace($zlib, chr(ord($zlib[1]) ^ 0x01), 1, 1);
        $badMethod = "\x79\x01" . substr($zlib, 2);
        $badDictionaryFlag = "\x78\x3f" . substr($zlib, 2);
        $badTrailer = substr_replace($zlib, "\xff\xff\xff\xff", -4, 4);
        $trailingZlibWithCopiedAdler = $zlib . 'review-garbage' . substr($zlib, -4);
        $trailingRaw = $raw . 'review-garbage';

        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decode('not deflate'));
        $t->throws(\RuntimeException::class, static fn (): array => DeflateStream::inspectZlib($badHeaderCheck));
        $t->throws(\RuntimeException::class, static fn (): array => DeflateStream::inspectZlib($badMethod));
        $t->throws(\RuntimeException::class, static fn (): array => DeflateStream::inspectZlib($badDictionaryFlag));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decode($badTrailer));
        $t->throws(\RuntimeException::class, static fn (): array => DeflateStream::inspectZlib($trailingZlibWithCopiedAdler));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decode($trailingRaw, DeflateStream::FORMAT_RAW));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decode($zlib, DeflateStream::FORMAT_ZLIB, 1));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decode($raw, DeflateStream::FORMAT_RAW, 1));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decode($raw, DeflateStream::FORMAT_ZLIB));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::build('x', ['format' => 'zip']));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::build('x', ['compressionLevel' => 10]));
    },

    'decodes zlib preset dictionary archive streams with supplied fixture dictionaries' => static function (TestRunner $t) use ($zlibDictionaryStream): void {
        $tarArchive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"zlib-dictionary-tar","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# ZLIB dictionary TAR\n\nReady for supplied dictionary import.\n",
            ],
        ]);
        $tarBytes = $tarArchive->bytes();
        $tarDictionary = 'packet/word/document.xml:review-dictionary';
        $tarDictionaryId = intval(hash('adler32', $tarDictionary), 16);
        $zlibTar = $zlibDictionaryStream($tarDictionary, $tarBytes);
        $tarMetadata = DeflateStream::inspectZlibWithDictionaries(
            $zlibTar,
            [$tarDictionaryId => $tarDictionary],
            strlen($tarBytes)
        );
        $decodedTarBytes = ArchiveCompressionStream::decodeTarBytesWithZlibDictionaries(
            $zlibTar,
            ArchiveCompressionStream::FORMAT_ZLIB_TAR,
            [$tarDictionaryId => $tarDictionary],
            strlen($tarBytes)
        );
        $tarRoundTrip = TarArchive::fromString($decodedTarBytes);
        $plainZlib = DeflateStream::build('plain review packet', ['format' => DeflateStream::FORMAT_ZLIB]);
        $plainMetadata = DeflateStream::inspectZlibWithDictionaries($plainZlib, [$tarDictionaryId => $tarDictionary]);

        $zipPackage = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>ZLIB dictionary ZIP</w:p></w:body></w:document>',
            ],
        ]);
        $zipBytes = $zipPackage->bytes();
        $zipDictionary = '[Content_Types].xml:word/document.xml';
        $zipDictionaryId = intval(hash('adler32', $zipDictionary), 16);
        $zlibZip = $zlibDictionaryStream($zipDictionary, $zipBytes);
        $decodedZipBytes = ArchiveCompressionStream::decodeZipBytesWithZlibDictionaries(
            $zlibZip,
            ArchiveCompressionStream::FORMAT_ZLIB_ZIP,
            [$zipDictionaryId => $zipDictionary],
            strlen($zipBytes)
        );
        $zipRoundTrip = ZipPackage::fromString($decodedZipBytes);

        $t->same(DeflateStream::FORMAT_ZLIB, $tarMetadata['format']);
        $t->same($tarBytes, $tarMetadata['data']);
        $t->same(8, $tarMetadata['compressionMethod']);
        $t->same(32768, $tarMetadata['windowSize']);
        $t->true($tarMetadata['compressionLevelHint'] !== '');
        $t->true($tarMetadata['hasPresetDictionary']);
        $t->same($tarDictionaryId, $tarMetadata['presetDictionaryId']);
        $t->same(sprintf('%08x', $tarDictionaryId), $tarMetadata['presetDictionaryIdHex']);
        $t->true($tarMetadata['dictionarySupplied']);
        $t->same(strlen($tarDictionary), $tarMetadata['dictionarySize']);
        $t->same($tarDictionaryId, $tarMetadata['dictionaryAdler32']);
        $t->same(sprintf('%08x', $tarDictionaryId), $tarMetadata['dictionaryAdler32Hex']);
        $t->same(intval(hash('adler32', $tarBytes), 16), $tarMetadata['adler32']);
        $t->same(sprintf('%08x', intval(hash('adler32', $tarBytes), 16)), $tarMetadata['adler32Hex']);
        $t->same(6, $tarMetadata['headerSize']);
        $t->same(6, $tarMetadata['compressedPayloadOffset']);
        $t->same(strlen($zlibTar) - 10, $tarMetadata['compressedPayloadSize']);
        $t->same(strlen($zlibTar) - 4, $tarMetadata['trailerOffset']);
        $t->same(4, $tarMetadata['trailerSize']);
        $t->same(strlen($zlibTar), $tarMetadata['consumedBytes']);
        $t->same(strlen($tarBytes), $tarMetadata['uncompressedSize']);
        $t->same(strlen($zlibTar) - 10, $tarMetadata['compressedSize']);
        $t->same("# ZLIB dictionary TAR\n\nReady for supplied dictionary import.\n", $tarRoundTrip->read('/packet/content.md'));
        $t->same('{"source":"zlib-dictionary-tar","target":"wordpress"}', $tarRoundTrip->read('/packet/manifest.json'));
        $t->same(false, $plainMetadata['hasPresetDictionary']);
        $t->same(false, $plainMetadata['dictionarySupplied']);
        $t->same(null, $plainMetadata['presetDictionaryId']);
        $t->same(null, $plainMetadata['dictionaryAdler32']);
        $t->same('<w:document><w:body><w:p>ZLIB dictionary ZIP</w:p></w:body></w:document>', $zipRoundTrip->read('/word/document.xml'));

        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decode($zlibTar));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decodeZlibWithDictionaries($zlibTar, []));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decodeZlibWithDictionaries(
            $zlibTar,
            [$tarDictionaryId => substr($tarDictionary, 1)]
        ));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decodeZlibWithDictionaries(
            $zlibTar . 'trailing-review-bytes',
            [$tarDictionaryId => $tarDictionary]
        ));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decodeZlibWithDictionaries(
            $zlibTar,
            [$tarDictionaryId => $tarDictionary],
            strlen($tarBytes) - 1
        ));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decodeZlibWithDictionaries(
            $zlibTar,
            ['0x' . sprintf('%08x', $tarDictionaryId) => $tarDictionary]
        ));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decodeZlibWithDictionaries(
            $zlibTar,
            [$tarDictionaryId => '']
        ));
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decodeZlibWithDictionaries(
            $zlibTar,
            [$tarDictionaryId => 1234]
        ));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::decodeTarBytesWithZlibDictionaries(
            $zlibTar,
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_TAR,
            [$tarDictionaryId => $tarDictionary]
        ));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::decodeZipBytesWithZlibDictionaries(
            $zlibZip,
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_ZIP,
            [$zipDictionaryId => $zipDictionary]
        ));
    },

    'preflights zlib preset dictionary policy without exposing package bytes' => static function (TestRunner $t) use ($zlibDictionaryStream): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"zlib-dictionary-policy","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# ZLIB preset dictionary policy\n\nReady for blocked-dictionary review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $dictionary = 'packet/content.md:blocked-review-dictionary';
        $dictionaryId = intval(hash('adler32', $dictionary), 16);
        $zlib = $zlibDictionaryStream($dictionary, $tarBytes);
        $clean = DeflateStream::presetDictionaryPolicyPreflight(DeflateStream::build('plain review packet', [
            'format' => DeflateStream::FORMAT_ZLIB,
        ]));

        $policy = DeflateStream::presetDictionaryPolicyPreflight($zlib);
        $inspection = ArchiveCompressionStream::inspectZlibPresetDictionaryPolicy($zlib);

        $t->same('no-preset-dictionary-streams', $clean['extractionPolicy']);
        $t->same(0, $clean['dictionaryStreamCount']);
        $t->same(false, $clean['hasPresetDictionary']);
        $t->same(null, $clean['presetDictionaryId']);
        $t->same('decodable-without-preset-dictionary', $clean['policy']);
        $t->same([], $clean['diagnostics']);
        $t->same('preset-dictionary-streams-blocked', $policy['extractionPolicy']);
        $t->same(1, $policy['dictionaryStreamCount']);
        $t->same(true, $policy['hasPresetDictionary']);
        $t->same($dictionaryId, $policy['presetDictionaryId']);
        $t->same(sprintf('%08x', $dictionaryId), $policy['presetDictionaryIdHex']);
        $t->same(8, $policy['compressionMethod']);
        $t->same(32768, $policy['windowSize']);
        $t->true($policy['compressionLevelHint'] !== '');
        $t->same(strlen($zlib), $policy['compressedSize']);
        $t->same(strlen($zlib) - 10, $policy['compressedPayloadSize']);
        $t->same(intval(hash('adler32', $tarBytes), 16), $policy['adler32']);
        $t->same(sprintf('%08x', intval(hash('adler32', $tarBytes), 16)), $policy['adler32Hex']);
        $t->same('blocked', $policy['policy']);
        $t->same([
            'zlib-preset-dictionary-stream-not-decoded',
            'zlib-external-preset-dictionary-required',
        ], $policy['diagnostics']);
        $t->same('zlib', $inspection['format']);
        $t->same('zlib-preset-dictionary-policy', $inspection['type']);
        $t->same(strlen($zlib), $inspection['compressedSize']);
        $t->same(1, $inspection['dictionaryStreamCount']);
        $t->same('preset-dictionary-streams-blocked', $inspection['extractionPolicy']);
        $t->same($policy, $inspection['stream']);
        $t->throws(\RuntimeException::class, static fn (): string => DeflateStream::decode($zlib));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::decodeTarBytes(
            $zlib,
            ArchiveCompressionStream::FORMAT_ZLIB_TAR
        ));
        $t->throws(\RuntimeException::class, static fn (): array => DeflateStream::presetDictionaryPolicyPreflight(substr($zlib, 0, 5)));
    },

    'rejects unsupported archive stream formats and bounded tar dispatch overflows' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"bounded-stream"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Bounded archive\n\nReady for import.\n",
            ],
        ]);
        $gzip = GzipStream::build($archive->bytes());

        $t->throws(\RuntimeException::class, static fn (): TarArchive => ArchiveCompressionStream::openTar($gzip, 'zip'));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::decodeTarBytes($archive->bytes(), ArchiveCompressionStream::FORMAT_TAR, strlen($archive->bytes()) - 1));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => ArchiveCompressionStream::openTar($gzip, ArchiveCompressionStream::FORMAT_GZIP_TAR, strlen($archive->bytes()) - 1));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => ArchiveCompressionStream::openTar($gzip, ArchiveCompressionStream::FORMAT_GZIP_TAR, null, strlen($archive->read('packet/content.md')) - 1));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => ArchiveCompressionStream::openTar($archive->bytes(), ArchiveCompressionStream::FORMAT_TAR, -1));
    },

    'rejects auto-detected archive streams that are not bounded tar packets' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"auto-detect-bounds"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Bounded auto-detect archive\n\nReady for import.\n",
            ],
        ]);
        $gzip = GzipStream::build($archive->bytes(), [
            'filename' => 'bounded-auto-detect.tar',
            'headerCrc' => true,
        ]);
        $gzipText = GzipStream::build('not a tar archive', [
            'filename' => 'not-a-tar.txt',
        ]);

        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::detectTarFormat('not an archive stream'));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::detectTarFormat($gzipText));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::detectTarFormat($gzip, strlen($archive->bytes()) - 1));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => ArchiveCompressionStream::openTarAuto($gzip, null, strlen($archive->read('/packet/content.md')) - 1));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::decodeTarBytesAuto($archive->bytes(), -1));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => ArchiveCompressionStream::openTarAuto($archive->bytes(), null, -1));
    },

    'builds and reads bounded lz4 frames around package fixture bytes' => static function (TestRunner $t): void {
        $package = \PortLibs\Pandoc\ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>LZ4 wrapped package import</w:p></w:body></w:document>',
            ],
        ]);
        $lz4 = Lz4Frame::build($package->bytes(), [
            'blockChecksum' => true,
            'contentChecksum' => true,
            'contentSize' => true,
        ]);
        $frames = Lz4Frame::frames($lz4);
        $roundTrip = \PortLibs\Pandoc\ZipPackage::fromString(Lz4Frame::decode($lz4));

        $t->same(1, count($frames));
        $t->same('frame', $frames[0]['type']);
        $t->same($package->bytes(), $frames[0]['data']);
        $t->same(strlen($package->bytes()), $frames[0]['contentSize']);
        $t->same(65536, $frames[0]['blockMaxSize']);
        $t->true($frames[0]['blockChecksum']);
        $t->true($frames[0]['contentChecksum']);
        $t->true($frames[0]['blockCount'] >= 1);
        $t->true($frames[0]['compressedSize'] > 0);
        $t->same(strlen($lz4), $frames[0]['frameSize']);
        $t->same('<w:document><w:body><w:p>LZ4 wrapped package import</w:p></w:body></w:document>', $roundTrip->read('/word/document.xml'));
    },

    'reads compressed lz4 blocks and skippable import metadata frames' => static function (TestRunner $t): void {
        $packet = str_repeat('packet/word/document.xml:review;', 420) . 'tail';
        $lz4 = Lz4Frame::build($packet, [
            'blockChecksum' => true,
            'contentChecksum' => false,
            'contentSize' => false,
        ]);
        $stream = Lz4Frame::skippableFrame('legacy reviewer index', 7) . $lz4;
        $frames = Lz4Frame::frames($stream);

        $t->same(2, count($frames));
        $t->same('skippable', $frames[0]['type']);
        $t->same(7, $frames[0]['id']);
        $t->same('legacy reviewer index', $frames[0]['data']);
        $t->same('frame', $frames[1]['type']);
        $t->same(null, $frames[1]['contentSize']);
        $t->same(false, $frames[1]['contentChecksum']);
        $t->same(true, $frames[1]['blockChecksum']);
        $t->same(1, $frames[1]['blockCount']);
        $t->same('compressed', $frames[1]['blockTypes'][0]);
        $t->true($frames[1]['compressedSize'] < strlen($packet));
        $t->same($packet, Lz4Frame::decode($stream));
    },

    'decodes dependent lz4 frame blocks using previous package fixture bytes' => static function (TestRunner $t) use ($lz4HeaderChecksum): void {
        $dictionaryBlock = 'packet/word/document.xml:';
        $matchLength = strlen($dictionaryBlock);
        $matchPayload = chr(0x0f)
            . pack('v', $matchLength)
            . chr($matchLength - 19);
        $descriptor = chr(0x40) . chr(0x40);
        $dependentFrame = pack('V', 0x184d2204)
            . $descriptor
            . $lz4HeaderChecksum($descriptor)
            . pack('V', 0x80000000 | strlen($dictionaryBlock))
            . $dictionaryBlock
            . pack('V', strlen($matchPayload))
            . $matchPayload
            . pack('V', 0);
        $frames = Lz4Frame::frames($dependentFrame);

        $t->same($dictionaryBlock . $dictionaryBlock, Lz4Frame::decode($dependentFrame));
        $t->same(1, count($frames));
        $t->same('frame', $frames[0]['type']);
        $t->same(false, $frames[0]['blockIndependent']);
        $t->same(2, $frames[0]['blockCount']);
        $t->same(['uncompressed', 'compressed'], $frames[0]['blockTypes']);
        $t->same(strlen($dictionaryBlock) + strlen($matchPayload), $frames[0]['compressedSize']);
    },

    'builds dependent lz4 frame blocks using previous review fixture history' => static function (TestRunner $t): void {
        $historyBlock = '';
        for ($index = 0; strlen($historyBlock) < 65536; $index++) {
            $historyBlock .= hash('sha256', 'pandoc-dependent-lz4-review-' . $index, true);
        }
        $historyBlock = substr($historyBlock, 0, 65536);
        $dependentBlock = substr($historyBlock, 1) . $historyBlock[0];
        $reviewPacket = $historyBlock . $dependentBlock;

        $lz4 = Lz4Frame::build($reviewPacket, [
            'blockIndependent' => false,
            'blockChecksum' => true,
            'contentChecksum' => true,
            'contentSize' => true,
        ]);
        $frames = Lz4Frame::frames($lz4);

        $t->same($reviewPacket, Lz4Frame::decode($lz4));
        $t->same(1, count($frames));
        $t->same(false, $frames[0]['blockIndependent']);
        $t->same(2, $frames[0]['blockCount']);
        $t->same(['uncompressed', 'compressed'], $frames[0]['blockTypes']);
        $t->same(strlen($reviewPacket), $frames[0]['contentSize']);
        $t->true($frames[0]['compressedSize'] < strlen($reviewPacket));
        $t->true(strlen($lz4) < strlen($reviewPacket));
    },

    'preflights dictionary backed lz4 frames without exposing package bytes' => static function (TestRunner $t) use ($lz4HeaderChecksum): void {
        $dictionaryId = 0x1a2b3c4d;
        $dictionaryPayload = 'packet/word/document.xml needs an external LZ4 dictionary';
        $descriptor = chr(0x40 | 0x20 | 0x08 | 0x04 | 0x01)
            . chr(0x40)
            . pack('V2', strlen($dictionaryPayload), 0)
            . pack('V', $dictionaryId);
        $dictionaryFrame = pack('V', 0x184d2204)
            . $descriptor
            . $lz4HeaderChecksum($descriptor)
            . pack('V', 0x80000000 | strlen($dictionaryPayload))
            . $dictionaryPayload
            . pack('V', 0)
            . pack('V', intval(hash('xxh32', $dictionaryPayload), 16));
        $skippable = Lz4Frame::skippableFrame('dictionary-id:0x1a2b3c4d', 11);
        $stream = $skippable . $dictionaryFrame;
        $cleanPolicy = Lz4Frame::dictionaryPolicyPreflight(Lz4Frame::build('plain review packet'));

        $policy = Lz4Frame::dictionaryPolicyPreflight($stream);
        $inspection = ArchiveCompressionStream::inspectLz4DictionaryPolicy($stream);

        $t->same('no-dictionary-frames', $cleanPolicy['extractionPolicy']);
        $t->same(0, $cleanPolicy['dictionaryFrameCount']);
        $t->same('dictionary-frames-blocked', $policy['extractionPolicy']);
        $t->same(2, $policy['frameCount']);
        $t->same(1, $policy['dataFrameCount']);
        $t->same(1, $policy['skippableFrameCount']);
        $t->same(1, $policy['dictionaryFrameCount']);
        $t->same('skippable', $policy['frames'][0]['type']);
        $t->same(11, $policy['frames'][0]['id']);
        $t->same('dictionary-id:0x1a2b3c4d', $policy['frames'][0]['data']);
        $t->same('metadata', $policy['frames'][0]['policy']);
        $t->same('frame', $policy['frames'][1]['type']);
        $t->same($dictionaryId, $policy['frames'][1]['dictionaryId']);
        $t->same(strlen($dictionaryPayload), $policy['frames'][1]['contentSize']);
        $t->same(65536, $policy['frames'][1]['blockMaxSize']);
        $t->same(true, $policy['frames'][1]['blockIndependent']);
        $t->same(false, $policy['frames'][1]['blockChecksum']);
        $t->same(true, $policy['frames'][1]['contentChecksum']);
        $t->same(1, $policy['frames'][1]['blockCount']);
        $t->same(['uncompressed'], $policy['frames'][1]['blockTypes']);
        $t->same(strlen($dictionaryPayload), $policy['frames'][1]['compressedSize']);
        $t->same(strlen($skippable), $policy['frames'][1]['frameOffset']);
        $t->same('blocked', $policy['frames'][1]['policy']);
        $t->same(['lz4-dictionary-frame-not-decoded', 'lz4-external-dictionary-required'], $policy['frames'][1]['diagnostics']);
        $t->same('lz4', $inspection['format']);
        $t->same('lz4-dictionary-policy', $inspection['type']);
        $t->same(strlen($stream), $inspection['compressedSize']);
        $t->same(1, $inspection['dictionaryFrameCount']);
        $t->same('dictionary-frames-blocked', $inspection['extractionPolicy']);
        $t->same($policy['frames'], $inspection['stream']['frames']);
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode($stream));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::decodeTarBytes(
            $stream,
            ArchiveCompressionStream::FORMAT_LZ4_TAR
        ));
    },

    'decodes dictionary backed lz4 archive streams with supplied fixture dictionaries' => static function (TestRunner $t) use ($lz4DictionaryMatchBlock, $lz4DictionaryCompressedFrame): void {
        $dictionaryId = 0x01020304;
        $dictionary = 'packet/word/document.xml:';
        $decodedPayload = $dictionary . 'doc' . $dictionary . 'xml';
        $stream = Lz4Frame::skippableFrame('dictionary-id:0x01020304', 12)
            . $lz4DictionaryCompressedFrame($dictionaryId, $decodedPayload, [
                $lz4DictionaryMatchBlock($dictionary, 'doc'),
                $lz4DictionaryMatchBlock($dictionary, 'xml'),
            ]);

        $decodedFrames = Lz4Frame::framesWithDictionaries($stream, [
            $dictionaryId => $dictionary,
        ]);

        $t->same($decodedPayload, Lz4Frame::decodeWithDictionaries($stream, [
            $dictionaryId => $dictionary,
        ]));
        $t->same($decodedPayload, ArchiveCompressionStream::decodeTarBytesWithLz4Dictionaries(
            $stream,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            [$dictionaryId => $dictionary],
            strlen($decodedPayload)
        ));
        $t->same($decodedPayload, ArchiveCompressionStream::decodeZipBytesWithLz4Dictionaries(
            $stream,
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            [$dictionaryId => $dictionary],
            strlen($decodedPayload)
        ));
        $t->same(2, count($decodedFrames));
        $t->same('skippable', $decodedFrames[0]['type']);
        $t->same('dictionary-id:0x01020304', $decodedFrames[0]['data']);
        $t->same('frame', $decodedFrames[1]['type']);
        $t->same($dictionaryId, $decodedFrames[1]['dictionaryId']);
        $t->same(strlen($decodedPayload), $decodedFrames[1]['contentSize']);
        $t->same(true, $decodedFrames[1]['blockIndependent']);
        $t->same(true, $decodedFrames[1]['blockChecksum']);
        $t->same(true, $decodedFrames[1]['contentChecksum']);
        $t->same(2, $decodedFrames[1]['blockCount']);
        $t->same(['compressed', 'compressed'], $decodedFrames[1]['blockTypes']);
        $t->true($decodedFrames[1]['compressedSize'] < strlen($decodedPayload));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode($stream));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decodeWithDictionaries($stream, []));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decodeWithDictionaries($stream, [
            $dictionaryId => substr($dictionary, 1),
        ]));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::decodeZipBytesWithLz4Dictionaries(
            $stream,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            [$dictionaryId => $dictionary]
        ));
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::decodeTarBytesWithLz4Dictionaries(
            $stream,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            [$dictionaryId => $dictionary],
            strlen($decodedPayload) - 1
        ));
    },

    'rejects malformed lz4 frame descriptors checksums and limits' => static function (TestRunner $t) use ($lz4HeaderChecksum): void {
        $valid = Lz4Frame::build('review source', [
            'blockChecksum' => true,
            'contentChecksum' => true,
            'contentSize' => true,
        ]);
        $badHeaderChecksum = substr_replace($valid, chr(ord($valid[14]) ^ 0xff), 14, 1);
        $badContentSize = substr_replace($valid, pack('V2', 999, 0), 6, 8);
        $badContentSize = substr_replace($badContentSize, $lz4HeaderChecksum(substr($badContentSize, 4, 10)), 14, 1);
        $badBlockChecksum = substr_replace($valid, "\xff\xff\xff\xff", 15 + 4 + strlen('review source'), 4);
        $badContentChecksum = substr_replace($valid, "\xff\xff\xff\xff", -4, 4);
        $dictionaryDescriptor = chr(0x41) . chr(0x40) . pack('V', 17);
        $dictionaryBlocks = pack('V', 0x184d2204)
            . $dictionaryDescriptor
            . $lz4HeaderChecksum($dictionaryDescriptor)
            . pack('V', 0);

        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode('not lz4'));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode($badHeaderChecksum));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode($badContentSize));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode($badBlockChecksum));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode($badContentChecksum));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode(substr($valid, 0, -2)));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode($dictionaryBlocks));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode($valid, 1));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::skippableFrame('x', 16));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::build('x', ['blockMaxSize' => 123]));
    },
];
