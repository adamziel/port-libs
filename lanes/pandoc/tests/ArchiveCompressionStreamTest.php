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
$lz4DictionaryUncompressedFrame = static function (
    int $dictionaryId,
    string $decodedPayload,
    bool $blockIndependent = true
) use ($lz4HeaderChecksum): string {
    $descriptor = chr(0x40 | ($blockIndependent ? 0x20 : 0x00) | 0x08 | 0x04 | 0x01)
        . chr(0x40)
        . pack('V2', strlen($decodedPayload), 0)
        . pack('V', $dictionaryId);

    return pack('V', 0x184d2204)
        . $descriptor
        . $lz4HeaderChecksum($descriptor)
        . pack('V', 0x80000000 | strlen($decodedPayload))
        . $decodedPayload
        . pack('V', 0)
        . pack('V', intval(hash('xxh32', $decodedPayload), 16));
};

$zipFixtureBytes = static function (array $entries, string $packageComment = '', array $eocd = []): string {
    $body = '';
    $centralDirectory = '';
    foreach ($entries as $entry) {
        $name = (string) $entry['name'];
        $data = (string) ($entry['data'] ?? '');
        $method = (int) ($entry['compressionMethod'] ?? 0);
        $localMethod = (int) ($entry['localCompressionMethod'] ?? $method);
        $centralMethod = (int) ($entry['centralCompressionMethod'] ?? $method);
        $versionNeededToExtract = (int) ($entry['versionNeededToExtract'] ?? 20);
        $localVersionNeededToExtract = (int) ($entry['localVersionNeededToExtract'] ?? $versionNeededToExtract);
        $centralVersionNeededToExtract = (int) ($entry['centralVersionNeededToExtract'] ?? $versionNeededToExtract);
        $flags = (int) ($entry['flags'] ?? 0);
        $descriptor = (bool) ($entry['descriptor'] ?? false);
        if ($descriptor) {
            $flags |= 0x0008;
        }
        $localFlags = (int) ($entry['localFlags'] ?? $flags);
        if ($descriptor) {
            $localFlags |= 0x0008;
        }
        $centralExtra = (string) ($entry['centralExtra'] ?? $entry['extra'] ?? '');
        $localExtra = (string) ($entry['localExtra'] ?? $entry['extra'] ?? '');
        $comment = (string) ($entry['comment'] ?? '');
        $diskStart = (int) ($entry['diskStart'] ?? 0);
        $payload = $method === 8 ? gzdeflate($data) : $data;
        $crc32 = (int) sprintf('%u', crc32($data));
        $localCrc32 = (int) ($entry['localCrc32'] ?? ($descriptor ? 0 : $crc32));
        $localCompressedSize = (int) ($entry['localCompressedSize'] ?? ($descriptor ? 0 : strlen($payload)));
        $localUncompressedSize = (int) ($entry['localUncompressedSize'] ?? ($descriptor ? 0 : strlen($data)));
        $localHeaderOffset = strlen($body);
        $centralCompressedSize = (int) ($entry['centralCompressedSize'] ?? strlen($payload));
        $centralUncompressedSize = (int) ($entry['centralUncompressedSize'] ?? strlen($data));
        $centralLocalHeaderOffset = (int) ($entry['centralLocalHeaderOffset'] ?? $localHeaderOffset);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            $localVersionNeededToExtract,
            $localFlags,
            $localMethod,
            0,
            0,
            $localCrc32,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($name),
            strlen($localExtra)
        ) . $name . $localExtra . $payload;

        if ($descriptor) {
            if ((bool) ($entry['descriptorSignature'] ?? true)) {
                $body .= "PK\x07\x08";
            }

            if ((bool) ($entry['descriptorZip64'] ?? false)) {
                $body .= pack(
                    'VVVVV',
                    (int) ($entry['descriptorCrc32'] ?? $crc32),
                    (int) ($entry['descriptorCompressedSize'] ?? strlen($payload)),
                    0,
                    (int) ($entry['descriptorUncompressedSize'] ?? strlen($data)),
                    0
                );
            } else {
                $body .= pack(
                    'VVV',
                    (int) ($entry['descriptorCrc32'] ?? $crc32),
                    (int) ($entry['descriptorCompressedSize'] ?? strlen($payload)),
                    (int) ($entry['descriptorUncompressedSize'] ?? strlen($data))
                );
            }
        }

        $centralDirectory .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            $centralVersionNeededToExtract,
            $flags,
            $centralMethod,
            0,
            0,
            $crc32,
            $centralCompressedSize,
            $centralUncompressedSize,
            strlen($name),
            strlen($centralExtra),
            strlen($comment),
            $diskStart,
            0,
            (int) ($entry['externalAttributes'] ?? 0),
            $centralLocalHeaderOffset
        ) . $name . $centralExtra . $comment;
    }

    return $body
        . $centralDirectory
        . pack(
            'VvvvvVVv',
            0x06054b50,
            (int) ($eocd['diskNumber'] ?? 0),
            (int) ($eocd['centralDirectoryDisk'] ?? 0),
            (int) ($eocd['diskEntryCount'] ?? count($entries)),
            (int) ($eocd['totalEntryCount'] ?? count($entries)),
            strlen($centralDirectory),
            strlen($body),
            strlen($packageComment)
        )
        . $packageComment;
};

$zipWithCentralDirectorySignature = static function (string $zip, string $signatureData = 'central-signature', ?int $declaredLength = null): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if (! is_int($eocdOffset)) {
        throw new \RuntimeException('ZIP fixture is missing an end of central directory record.');
    }

    return substr($zip, 0, $eocdOffset)
        . pack('Vv', 0x05054b50, $declaredLength ?? strlen($signatureData))
        . $signatureData
        . substr($zip, $eocdOffset);
};

$packZip64UInt64 = static function (int $value): string {
    return pack('VV', $value & 0xffffffff, intdiv($value, 0x100000000));
};

$buildZip64EndOfCentralDirectoryZip = static function (string $zip) use ($packZip64UInt64): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if (! is_int($eocdOffset)) {
        throw new \RuntimeException('ZIP fixture is missing an end of central directory record.');
    }

    $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4));
    $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4));
    if (! is_array($centralDirectorySize) || ! is_array($centralDirectoryOffset)) {
        throw new \RuntimeException('Unable to read ZIP central directory metadata.');
    }

    $zip64EocdOffset = $eocdOffset;
    $zip64Eocd = "PK\x06\x06"
        . $packZip64UInt64(44)
        . pack('vvVV', 45, 45, 0, 0)
        . $packZip64UInt64(1)
        . $packZip64UInt64(1)
        . $packZip64UInt64((int) $centralDirectorySize['value'])
        . $packZip64UInt64((int) $centralDirectoryOffset['value']);
    $zip64Locator = "PK\x06\x07"
        . pack('V', 0)
        . $packZip64UInt64($zip64EocdOffset)
        . pack('V', 1);

    $eocd = substr($zip, $eocdOffset);
    $eocd = substr_replace($eocd, pack('v', 0xffff), 8, 2);
    $eocd = substr_replace($eocd, pack('v', 0xffff), 10, 2);
    $eocd = substr_replace($eocd, pack('V', 0xffffffff), 12, 4);
    $eocd = substr_replace($eocd, pack('V', 0xffffffff), 16, 4);

    return substr($zip, 0, $eocdOffset) . $zip64Eocd . $zip64Locator . $eocd;
};

$rewriteZipEndOfCentralDirectory = static function (string $zip, array $fields): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if (! is_int($eocdOffset)) {
        throw new \RuntimeException('ZIP fixture is missing an end of central directory record.');
    }

    $fieldOffsets = [
        'diskEntryCount' => [8, 'v'],
        'totalEntryCount' => [10, 'v'],
        'centralDirectorySize' => [12, 'V'],
        'centralDirectoryOffset' => [16, 'V'],
    ];

    foreach ($fields as $name => $value) {
        if (! isset($fieldOffsets[$name])) {
            throw new \InvalidArgumentException('Unknown ZIP EOCD field: ' . $name);
        }

        [$offset, $format] = $fieldOffsets[$name];
        $zip = substr_replace($zip, pack($format, (int) $value), $eocdOffset + $offset, $format === 'v' ? 2 : 4);
    }

    return $zip;
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

    'preserves pax creation timestamp metadata for tar review packets' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $localContent = "# Local creation time packet\n\nReady for WordPress provenance review.\n";
        $inheritedContent = "# Inherited creation time packet\n\nReady for WordPress provenance review.\n";
        $starContent = "# Star birthtime packet\n\nReady for WordPress provenance review.\n";
        $archive = $rawTarHeader('GlobalHead/created', 'g', $paxPayload([
            'LIBARCHIVE.creationtime' => '1780479036.75',
        ]), 0, false)
            . $rawTarHeader('PaxHeaders/local-created', 'x', $paxPayload([
                'path' => 'packet/created/local.md',
                'LIBARCHIVE.creationtime' => '1780479037.25',
            ]), 0, false)
            . $rawTarHeader('placeholder-local.md', '0', $localContent, 1780479038, false)
            . $rawTarHeader('packet/created/inherited.md', '0', $inheritedContent, 1780479039, false)
            . $rawTarHeader('PaxHeaders/star-created', 'x', $paxPayload([
                'path' => 'packet/created/star.md',
                'LIBARCHIVE.creationtime' => '',
                'SCHILY.birthtime' => '1780479040.50',
            ]), 0, false)
            . $rawTarHeader('placeholder-star.md', '0', $starContent, 1780479041, false)
            . str_repeat("\0", 1024);
        $roundTrip = TarArchive::fromString($archive);
        $inspection = ArchiveCompressionStream::inspectTarStream(
            $archive,
            ArchiveCompressionStream::FORMAT_TAR,
            strlen($archive),
            strlen($localContent) + strlen($inheritedContent) + strlen($starContent)
        );
        $generated = TarArchive::fromEntries([
            [
                'name' => 'packet/created/generated.md',
                'data' => "# Generated creation time packet\n\nReady for WordPress provenance review.\n",
                'createdAt' => 1780479042,
            ],
        ]);
        $generatedEntry = $generated->entry('/packet/created/generated.md');

        $t->same(1780479037, $roundTrip->entry('/packet/created/local.md')->createdAt);
        $t->same(1780479036, $roundTrip->entry('/packet/created/inherited.md')->createdAt);
        $t->same(1780479040, $roundTrip->entry('/packet/created/star.md')->createdAt);
        $t->same('1780479037.25', $roundTrip->entry('/packet/created/local.md')->paxHeaders['LIBARCHIVE.creationtime'] ?? null);
        $t->same('1780479036.75', $roundTrip->entry('/packet/created/inherited.md')->globalPaxHeaders['LIBARCHIVE.creationtime'] ?? null);
        $t->same(null, $roundTrip->entry('/packet/created/star.md')->paxHeaders['LIBARCHIVE.creationtime'] ?? null);
        $t->same('1780479040.50', $roundTrip->entry('/packet/created/star.md')->paxHeaders['SCHILY.birthtime'] ?? null);
        $t->same(1780479037, $inspection['entryLayouts'][0]['createdAt']);
        $t->same(1780479036, $inspection['entryLayouts'][1]['createdAt']);
        $t->same(1780479040, $inspection['entryLayouts'][2]['createdAt']);
        $t->same(['LIBARCHIVE.creationtime', 'path'], $inspection['entryLayouts'][0]['paxLocalHeaderKeys']);
        $t->same(['LIBARCHIVE.creationtime'], $inspection['entryLayouts'][1]['paxGlobalHeaderKeys']);
        $t->same(['LIBARCHIVE.creationtime', 'SCHILY.birthtime', 'path'], $inspection['entryLayouts'][2]['paxLocalHeaderKeys']);
        $t->same(['LIBARCHIVE.creationtime'], $inspection['entryLayouts'][2]['paxDeletedHeaderKeys']);
        $t->same($localContent, $roundTrip->read('/packet/created/local.md'));
        $t->same($inheritedContent, $roundTrip->read('/packet/created/inherited.md'));
        $t->same($starContent, $roundTrip->read('/packet/created/star.md'));
        $t->same(1780479042, $generatedEntry->createdAt);
        $t->same('1780479042', $generatedEntry->paxHeaders['LIBARCHIVE.creationtime'] ?? null);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString(
            $rawTarHeader('PaxHeaders/overflow-created', 'x', $paxPayload([
                'path' => 'packet/created/overflow.md',
                'LIBARCHIVE.creationtime' => (string) PHP_INT_MAX . '0.25',
            ]), 0, false)
            . $rawTarHeader('placeholder-overflow.md', '0', '# Overflow creation time', 0, false)
            . str_repeat("\0", 1024)
        ));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            [
                'name' => 'packet/created/generated-overflow.md',
                'data' => '# Overflow generated creation time',
                'createdAt' => -1,
            ],
        ]));
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

    'preflights tar filesystem attributes without applying modes or owners' => static function (TestRunner $t): void {
        $scriptBytes = "#!/bin/sh\nprintf 'Pandoc archive review\\n'\n";
        $contentBytes = "# Filesystem attribute policy\n\nReady for WordPress import review.\n";
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/',
                'type' => TarArchiveEntry::TYPE_DIRECTORY,
                'mode' => 01777,
                'modifiedAt' => 1780479088,
            ],
            [
                'name' => 'packet/bin/import.sh',
                'data' => $scriptBytes,
                'mode' => 04755,
                'uid' => 1001,
                'gid' => 1002,
                'userName' => 'author',
                'groupName' => 'docs',
                'modifiedAt' => 1780479089,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $contentBytes,
                'mode' => 0644,
                'modifiedAt' => 1780479090,
            ],
        ]);
        $gzip = GzipStream::build($archive->bytes(), [
            'filename' => 'wordpress-tar-filesystem-attributes.tar',
            'comment' => 'mode and owner metadata stay review-only',
        ]);

        $policy = TarArchive::filesystemAttributePolicyPreflight($archive->bytes());
        $inspection = ArchiveCompressionStream::inspectTarFilesystemAttributePolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archive->bytes())
        );
        $roundTrip = ArchiveCompressionStream::openTar(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archive->bytes())
        );

        $t->same('tar-filesystem-attribute-policy', $policy['type']);
        $t->same(3, $policy['entryCount']);
        $t->same(2, $policy['attributeEntryCount']);
        $t->same(2, $policy['modeFlagEntryCount']);
        $t->same(1, $policy['ownerMetadataEntryCount']);
        $t->same(1, $policy['nonRootOwnerEntryCount']);
        $t->same(1, $policy['regularExecutableEntryCount']);
        $t->same(1, $policy['worldWritableEntryCount']);
        $t->same(1, $policy['setuidEntryCount']);
        $t->same(0, $policy['setgidEntryCount']);
        $t->same(1, $policy['stickyEntryCount']);
        $t->same('filesystem-attributes-metadata-only', $policy['extractionPolicy']);
        $t->same(['tar-filesystem-attributes-not-applied'], $policy['diagnostics']);
        $t->same(['packet/', 'packet/bin/import.sh'], array_column($policy['entries'], 'name'));
        $t->same(['1777', '4755'], array_column($policy['entries'], 'modeOctal'));
        $t->same([0777, 0755], array_column($policy['entries'], 'permissionBits'));
        $t->same([01000, 04000], array_column($policy['entries'], 'specialBits'));
        $t->same([0, 1001], array_column($policy['entries'], 'uid'));
        $t->same([0, 1002], array_column($policy['entries'], 'gid'));
        $t->same([
            ['sticky', 'world-writable'],
            ['setuid', 'regular-executable'],
        ], array_column($policy['entries'], 'modeFlags'));
        $t->same([
            [],
            ['non-root-uid', 'non-root-gid', 'user-name', 'group-name'],
        ], array_column($policy['entries'], 'ownerFlags'));
        $t->same('metadata-only-not-applied', $policy['entries'][0]['modePolicy']);
        $t->same('default-owner', $policy['entries'][0]['ownerPolicy']);
        $t->same('metadata-only-not-applied', $policy['entries'][1]['modePolicy']);
        $t->same('metadata-only-not-applied', $policy['entries'][1]['ownerPolicy']);
        $t->same([
            'tar-filesystem-attributes-not-applied',
            'tar-mode-sticky-not-applied',
            'tar-mode-world-writable-not-applied',
        ], $policy['entries'][0]['diagnostics']);
        $t->same([
            'tar-filesystem-attributes-not-applied',
            'tar-mode-setuid-not-applied',
            'tar-mode-executable-not-applied',
            'tar-owner-metadata-not-applied',
        ], $policy['entries'][1]['diagnostics']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $inspection['format']);
        $t->same('tar-filesystem-attribute-policy', $inspection['type']);
        $t->same('filesystem-attributes-metadata-only', $inspection['extractionPolicy']);
        $t->same(2, $inspection['attributeEntryCount']);
        $t->same('gzip', $inspection['stream']['type']);
        $t->same('wordpress-tar-filesystem-attributes.tar', $inspection['stream']['members'][0]['filename']);
        $t->same(false, isset($inspection['archive']));
        $t->same(false, isset($inspection['entries'][0]['data']));
        $t->same($scriptBytes, $roundTrip->read('/packet/bin/import.sh'));
        $t->same($contentBytes, $roundTrip->read('/packet/content.md'));
        $t->same(04755, $roundTrip->entry('/packet/bin/import.sh')->mode);
        $t->same(1001, $roundTrip->entry('/packet/bin/import.sh')->uid);
        $t->same(1002, $roundTrip->entry('/packet/bin/import.sh')->gid);
    },

    'preflights tar case-insensitive name collisions before package handoff' => static function (TestRunner $t): void {
        $asciiArchive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"tar-name-collision","target":"wordpress"}',
            ],
            [
                'name' => 'packet/media/Review.PNG',
                'data' => 'upper-case media',
            ],
            [
                'name' => 'packet/media/review.png',
                'data' => 'lower-case media',
            ],
        ]);
        $unicodePrecomposedName = "packet/media/Caf\u{00e9}.PNG";
        $unicodeDecomposedName = "packet/media/cafe\u{0301}.png";
        $unicodeArchive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"tar-unicode-name-collision","target":"wordpress"}',
            ],
            [
                'name' => $unicodePrecomposedName,
                'data' => 'precomposed media',
            ],
            [
                'name' => $unicodeDecomposedName,
                'data' => 'decomposed media',
            ],
        ]);
        $gzip = GzipStream::build($unicodeArchive->bytes(), [
            'filename' => 'wordpress-tar-name-collision.tar',
            'comment' => 'TAR name collision policy preflight',
        ]);

        $asciiPolicy = $asciiArchive->caseInsensitiveNamePreflight();
        $unicodePolicy = $unicodeArchive->caseInsensitiveNamePreflight();
        $streamPolicy = ArchiveCompressionStream::inspectTarCaseInsensitiveNamePolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($unicodeArchive->bytes())
        );
        $safePolicy = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{}',
            ],
            [
                'name' => 'packet/media/review.png',
                'data' => 'safe media',
            ],
        ])->assertNoCaseInsensitiveNameCollisions();

        $t->same(3, $asciiPolicy['entryCount']);
        $t->same(1, $asciiPolicy['collisionGroupCount']);
        $t->same(2, $asciiPolicy['collisionEntryCount']);
        $t->same('packet/media/review.png', $asciiPolicy['collisionGroups'][0]['caseFoldKey']);
        $t->same(['packet/media/Review.PNG', 'packet/media/review.png'], $asciiPolicy['collisionGroups'][0]['entryNames']);
        $t->same(['case-insensitive-name-collision'], $asciiPolicy['collisionEntries'][0]['issues']);
        $t->same(['packet/media/Review.PNG', 'packet/media/review.png'], $asciiPolicy['collisionEntries'][0]['equivalentEntryNames']);
        $t->same('upper-case media', $asciiArchive->read('/packet/media/Review.PNG'));
        $t->same('lower-case media', $asciiArchive->read('/packet/media/review.png'));

        $t->same(1, $unicodePolicy['collisionGroupCount']);
        $t->same(2, $unicodePolicy['collisionEntryCount']);
        $t->same("packet/media/caf\u{00e9}.png", $unicodePolicy['collisionGroups'][0]['caseFoldKey']);
        $t->same([$unicodePrecomposedName, $unicodeDecomposedName], $unicodePolicy['collisionGroups'][0]['entryNames']);
        $t->same(true, $unicodePolicy['collisionEntries'][1]['hasCaseInsensitiveNameCollision']);
        $t->same(['case-insensitive-name-collision'], $unicodePolicy['collisionEntries'][1]['issues']);
        $t->same('precomposed media', $unicodeArchive->read('/' . $unicodePrecomposedName));
        $t->same('decomposed media', $unicodeArchive->read('/' . $unicodeDecomposedName));

        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $streamPolicy['format']);
        $t->same(strlen($unicodeArchive->bytes()), $streamPolicy['uncompressedSize']);
        $t->same('tar-case-insensitive-name-policy', $streamPolicy['type']);
        $t->same('review-before-conversion', $streamPolicy['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $streamPolicy['extractionPolicy']);
        $t->same(['tar-case-insensitive-name-collision'], $streamPolicy['diagnostics']);
        $t->same(1, $streamPolicy['collisionGroupCount']);
        $t->same(2, $streamPolicy['collisionEntryCount']);
        $t->same($unicodePolicy['entries'], $streamPolicy['entries']);
        $t->same('gzip', $streamPolicy['stream']['type']);
        $t->same('wordpress-tar-name-collision.tar', $streamPolicy['stream']['members'][0]['filename']);
        $t->same('TAR name collision policy preflight', $streamPolicy['stream']['members'][0]['comment']);
        $t->same(false, isset($streamPolicy['archive']));
        $t->same(false, isset($streamPolicy['entries'][0]['data']));

        $t->same(0, $safePolicy['collisionEntryCount']);
        $t->same([], $safePolicy['diagnostics']);
        $t->throws(\RuntimeException::class, static fn (): array => $asciiArchive->assertNoCaseInsensitiveNameCollisions());
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectTarCaseInsensitiveNamePolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($unicodeArchive->bytes()) - 1
        ));
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
        $checksumPolicy = TarArchive::checksumPolicyPreflight($archive);

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
        $t->same(['gnu-long-name', 'regular-file', 'gnu-long-name', 'directory'], array_column($checksumPolicy['entries'], 'role'));
        $t->same(['gnu-long-name', null, 'gnu-long-name', null], array_column($checksumPolicy['entries'], 'metadataKind'));
        $t->same($longDocumentName, $checksumPolicy['entries'][0]['metadataValue']);
        $t->same(strlen($longDocumentName), $checksumPolicy['entries'][0]['metadataValueSize']);
        $t->same($longDirectoryName, $checksumPolicy['entries'][2]['metadataValue']);
        $t->same(strlen($longDirectoryName), $checksumPolicy['entries'][2]['metadataValueSize']);
        $t->same([0, 0, 0, 0], array_column($checksumPolicy['entries'], 'paxHeaderCount'));
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

    'preflights tar checksum provenance without exposing package payloads' => static function (TestRunner $t) use ($rawTarHeader, $rewriteTarHeaderWithSignedChecksum, $paxPayload): void {
        $unsignedName = "packet/caf\u{00e9}-unsigned.md";
        $signedName = "packet/r\u{00e9}sum\u{00e9}-signed.md";
        $unsignedBytes = "# POSIX checksum packet\n\nReady for archive review.\n";
        $signedBytes = "# Historic signed checksum packet\n\nReady for archive review.\n";
        $unsignedRecord = $rawTarHeader($unsignedName, '0', $unsignedBytes, 1780479092, false);
        $paxRecord = $rawTarHeader('PaxHeaders/checksum', 'x', $paxPayload([
            'comment' => 'checksum review metadata',
            'path' => $signedName,
            'size' => (string) strlen($signedBytes),
        ]), 1780479093, false);
        $signedRecord = $rewriteTarHeaderWithSignedChecksum(
            $rawTarHeader("placeholder-r\u{00e9}sum\u{00e9}.md", '0', $signedBytes, 1780479094, false, 0)
        );
        $archiveBytes = $unsignedRecord . $paxRecord . $signedRecord . str_repeat("\0", 1024);
        $gzipBytes = GzipStream::build($archiveBytes, [
            'filename' => 'wordpress-tar-checksum-policy.tar',
            'comment' => 'TAR checksum provenance preflight',
        ]);
        $corruptedHeader = substr_replace($archiveBytes, 'x', 0, 1);

        $inspection = ArchiveCompressionStream::inspectTarChecksumPolicy(
            $gzipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes)
        );

        $t->same('tar-checksum-policy', $inspection['type']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $inspection['format']);
        $t->same(strlen($archiveBytes), $inspection['uncompressedSize']);
        $t->same(3, $inspection['headerRecordCount']);
        $t->same(2, $inspection['entryCount']);
        $t->same(1, $inspection['metadataRecordCount']);
        $t->same(2, $inspection['unsignedChecksumRecordCount']);
        $t->same(1, $inspection['signedChecksumRecordCount']);
        $t->same(1, $inspection['ambiguousChecksumRecordCount']);
        $t->same('checksum-provenance-only-no-extraction', $inspection['extractionPolicy']);
        $t->same(['tar-header-historic-signed-checksum'], $inspection['diagnostics']);
        $t->same([$unsignedName, 'PaxHeaders/checksum', $signedName], array_column($inspection['entries'], 'name'));
        $t->same(['regular-file', 'pax-local', 'regular-file'], array_column($inspection['entries'], 'role'));
        $t->same([null, 'pax-local', null], array_column($inspection['entries'], 'metadataKind'));
        $t->same([0, 3, 0], array_column($inspection['entries'], 'paxHeaderCount'));
        $t->same(['comment', 'path', 'size'], $inspection['entries'][1]['paxHeaderKeys']);
        $t->same(null, $inspection['entries'][1]['metadataValue']);
        $t->same([
            'posix-unsigned',
            'posix-unsigned-and-historic-signed',
            'historic-signed',
        ], array_column($inspection['entries'], 'checksumKind'));
        $t->same([true, true, false], array_column($inspection['entries'], 'matchesUnsigned'));
        $t->same([false, true, true], array_column($inspection['entries'], 'matchesSigned'));
        $t->same([0, 1024, 2048], array_column($inspection['entries'], 'headerOffset'));
        $t->same([1024, 2048, 3072], array_column($inspection['entries'], 'recordEndOffset'));
        $t->same(0, $inspection['entries'][2]['headerPayloadSize']);
        $t->same(strlen($signedBytes), $inspection['entries'][2]['payloadSize']);
        $t->same('pax-path', $inspection['entries'][2]['nameSource']);
        $t->same($inspection['entries'][0]['storedChecksum'], $inspection['entries'][0]['unsignedChecksum']);
        $t->same($inspection['entries'][2]['storedChecksum'], $inspection['entries'][2]['signedChecksum']);
        $t->true($inspection['entries'][0]['unsignedChecksum'] !== $inspection['entries'][0]['signedChecksum']);
        $t->true($inspection['entries'][2]['unsignedChecksum'] !== $inspection['entries'][2]['signedChecksum']);
        $t->same('accepted-checksum-provenance', $inspection['entries'][2]['policy']);
        $t->same(['tar-header-historic-signed-checksum'], $inspection['entries'][2]['diagnostics']);
        $t->same('wordpress-tar-checksum-policy.tar', $inspection['stream']['members'][0]['filename']);
        $t->true(!array_key_exists('archive', $inspection));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectTarChecksumPolicy(
            $corruptedHeader,
            ArchiveCompressionStream::FORMAT_TAR
        ));
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

    'rejects control-byte tar paths from headers pax gnu and link metadata' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $headerControlPath = $rawTarHeader("packet/control\nname.xml", '0', '<w:document/>');
        $paxControlPath = $rawTarHeader('PaxHeaders/control-path', 'x', $paxPayload([
            'path' => "packet/control\tname.xml",
        ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $gnuControlPath = $rawTarHeader('././@LongLink', 'L', "packet/control\x7fname.xml\0", 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);
        $linkControlPath = $rawTarHeader('packet/source.md', '0', '# Source', 0, false)
            . $rawTarHeader('PaxHeaders/control-link', 'x', $paxPayload([
                'path' => 'packet/control-link.md',
                'linkpath' => "packet/media/control\nasset.png",
            ]), 0, false)
            . $rawTarHeader('placeholder-link.md', '2', '', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            ['name' => "packet/generated\rname.xml", 'data' => '<w:document/>'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($headerControlPath));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($paxControlPath));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($gnuControlPath));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::linkPolicyPreflight($linkControlPath));
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

    'rejects tar incremental snapshot metadata before package bytes are exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $gnuDumpdirType = $rawTarHeader('packet/snapshot/', 'D', "Ycontent.md\0Nmedia/old.png\0Dassets/\0");
        $paxDumpdirRegular = $rawTarHeader('PaxHeaders/incremental', 'x', $paxPayload([
            'path' => 'packet/incremental/current.md',
            'GNU.dumpdir' => "Ycurrent.md\nNold.md\n",
        ]), 0, false)
            . $rawTarHeader('placeholder-incremental.md', '0', '# Incremental snapshot source', 0, false)
            . str_repeat("\0", 1024);
        $globalDumpdir = $rawTarHeader('GlobalHead/incremental', 'g', $paxPayload([
            'GNU.dumpdir' => "Ycontent.md\n",
        ]), 0, false)
            . $rawTarHeader('packet/content.md', '0', 'content', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($gnuDumpdirType));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($paxDumpdirRegular));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($globalDumpdir));
    },

    'preflights tar incremental snapshot policy without exposing dumpdir entries' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $typePayload = "Ycontent.md\0Nmedia/old.png\0Dassets/\0";
        $paxPayloadValue = "Ycurrent.md\nNold.md\n";
        $archiveBytes = $rawTarHeader('packet/snapshot/', 'D', $typePayload, 1780479090, false)
            . $rawTarHeader('PaxHeaders/incremental-pax', 'x', $paxPayload([
                'path' => 'packet/incremental/current.md',
                'GNU.dumpdir' => $paxPayloadValue,
            ]), 0, false)
            . $rawTarHeader('placeholder-incremental.md', '0', '# Incremental snapshot source', 1780479091, false)
            . str_repeat("\0", 1024);
        $gzip = GzipStream::build($archiveBytes, [
            'filename' => 'wordpress-incremental-snapshot-policy.tar',
            'comment' => 'incremental dumpdir metadata stays blocked for extraction',
        ]);

        $policy = TarArchive::incrementalSnapshotPolicyPreflight($archiveBytes);
        $streamPolicy = ArchiveCompressionStream::inspectTarIncrementalSnapshotPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes)
        );

        $t->same(2, $policy['entryCount']);
        $t->same(2, $policy['incrementalEntryCount']);
        $t->same(1, $policy['typeflagEntryCount']);
        $t->same(1, $policy['paxMetadataEntryCount']);
        $t->same(5, $policy['dumpdirRecordCount']);
        $t->same(2, $policy['deletedRecordCount']);
        $t->same(1, $policy['directoryRecordCount']);
        $t->same('incremental-snapshot-entries-blocked', $policy['extractionPolicy']);
        $t->same('packet/snapshot/', $policy['entries'][0]['name']);
        $t->same('gnu-dumpdir-typeflag', $policy['entries'][0]['incrementalType']);
        $t->same(['gnu-typeflag'], $policy['entries'][0]['incrementalHeaderFamilies']);
        $t->same([], $policy['entries'][0]['incrementalHeaderKeys']);
        $t->same(3, $policy['entries'][0]['dumpdirRecordCount']);
        $t->same(1, $policy['entries'][0]['deletedRecordCount']);
        $t->same(1, $policy['entries'][0]['directoryRecordCount']);
        $t->same(strlen($typePayload), $policy['entries'][0]['payloadSize']);
        $t->same('typeflag-payload', $policy['entries'][0]['dumpdirRecords'][0]['source']);
        $t->same('Y', $policy['entries'][0]['dumpdirRecords'][0]['marker']);
        $t->same('present', $policy['entries'][0]['dumpdirRecords'][0]['action']);
        $t->same('content.md', $policy['entries'][0]['dumpdirRecords'][0]['name']);
        $t->same('N', $policy['entries'][0]['dumpdirRecords'][1]['marker']);
        $t->same('deleted', $policy['entries'][0]['dumpdirRecords'][1]['action']);
        $t->same('media/old.png', $policy['entries'][0]['dumpdirRecords'][1]['name']);
        $t->same(['tar-incremental-snapshot-not-extracted', 'gnu-typeflag'], $policy['entries'][0]['diagnostics']);
        $t->same('packet/incremental/current.md', $policy['entries'][1]['name']);
        $t->same('pax-gnu-dumpdir-metadata', $policy['entries'][1]['incrementalType']);
        $t->same(['gnu-pax'], $policy['entries'][1]['incrementalHeaderFamilies']);
        $t->same(['GNU.dumpdir'], $policy['entries'][1]['incrementalHeaderKeys']);
        $t->same(2, $policy['entries'][1]['dumpdirRecordCount']);
        $t->same('pax-gnu-dumpdir', $policy['entries'][1]['dumpdirRecords'][0]['source']);
        $t->same('current.md', $policy['entries'][1]['dumpdirRecords'][0]['name']);
        $t->same('old.md', $policy['entries'][1]['dumpdirRecords'][1]['name']);
        $t->same('pax-path', $policy['entries'][1]['nameSource']);
        $t->same(['tar-incremental-snapshot-not-extracted', 'gnu-pax'], $policy['entries'][1]['diagnostics']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $streamPolicy['format']);
        $t->same(strlen($archiveBytes), $streamPolicy['uncompressedSize']);
        $t->same('gzip', $streamPolicy['stream']['type']);
        $t->same('wordpress-incremental-snapshot-policy.tar', $streamPolicy['stream']['members'][0]['filename']);
        $t->same(2, $streamPolicy['incrementalEntryCount']);
        $t->same('packet/incremental/current.md', $streamPolicy['entries'][1]['name']);
        $t->same('old.md', $streamPolicy['entries'][1]['dumpdirRecords'][1]['name']);
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($archiveBytes));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectTarIncrementalSnapshotPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($archiveBytes) - 1
        ));
    },

    'rejects malformed tar incremental snapshot policy metadata before diagnostics are exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $incrementalArchive = static function (array $headers) use ($rawTarHeader, $paxPayload): string {
            return $rawTarHeader('PaxHeaders/incremental', 'x', $paxPayload($headers), 0, false)
                . $rawTarHeader('placeholder-incremental.md', '0', '# Incremental snapshot source', 0, false)
                . str_repeat("\0", 1024);
        };

        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::incrementalSnapshotPolicyPreflight($incrementalArchive([
            'path' => 'packet/invalid-marker.md',
            'GNU.dumpdir' => "Xcontent.md\n",
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::incrementalSnapshotPolicyPreflight($incrementalArchive([
            'path' => 'packet/unsafe-dumpdir.md',
            'GNU.dumpdir' => "N../old.md\n",
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => TarArchive::incrementalSnapshotPolicyPreflight(
            $rawTarHeader('GlobalHead/incremental', 'g', $paxPayload([
                'GNU.dumpdir' => "Ycontent.md\n",
            ]), 0, false)
            . $rawTarHeader('packet/content.md', '0', 'content', 0, false)
            . str_repeat("\0", 1024)
        ));
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

    'exposes gzip header crc provenance for archive review packets' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-header-crc","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# GZIP header CRC provenance\n\nReady for review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $extraFieldData = pack('CCv', ord('H'), ord('C'), strlen('fhcrc:v1')) . 'fhcrc:v1';
        $filename = 'wordpress-header-crc-packet.tar';
        $comment = 'gzip header CRC provenance';
        $gzip = GzipStream::build($tarBytes, [
            'filename' => $filename,
            'comment' => $comment,
            'extraFieldData' => $extraFieldData,
            'headerCrc' => true,
        ]);
        $plainGzip = GzipStream::build($tarBytes, [
            'filename' => 'wordpress-no-header-crc-packet.tar',
        ]);

        $headerCrcOffset = 10 + 2 + strlen($extraFieldData) + strlen($filename) + 1 + strlen($comment) + 1;
        $expectedHeaderCrc16 = ((int) sprintf('%u', crc32(substr($gzip, 0, $headerCrcOffset)))) & 0xffff;
        $member = GzipStream::members($gzip)[0];
        $plainMember = GzipStream::members($plainGzip)[0];
        $inspection = ArchiveCompressionStream::inspectTarStreamAuto(
            $gzip,
            strlen($tarBytes),
            strlen($archive->read('/packet/manifest.json')) + strlen($archive->read('/packet/content.md'))
        );
        $inspectionMember = $inspection['stream']['members'][0];
        $tamperedHeaderCrc = substr_replace(
            $gzip,
            chr(ord($gzip[$headerCrcOffset]) ^ 0x01),
            $headerCrcOffset,
            1
        );

        $t->true($member['headerCrcPresent']);
        $t->same($expectedHeaderCrc16, $member['headerCrc16']);
        $t->same(sprintf('%04x', $expectedHeaderCrc16), $member['headerCrc16Hex']);
        $t->same($headerCrcOffset, $member['headerCrcOffset']);
        $t->same($headerCrcOffset, $member['headerCrcCoverageSize']);
        $t->same($headerCrcOffset + 2, $member['headerSize']);
        $t->same($member['headerCrcPresent'], $inspectionMember['headerCrcPresent']);
        $t->same($member['headerCrc16Hex'], $inspectionMember['headerCrc16Hex']);
        $t->same($member['headerCrcOffset'], $inspectionMember['headerCrcOffset']);
        $t->same($member['headerCrcCoverageSize'], $inspectionMember['headerCrcCoverageSize']);
        $t->same('HC', $inspectionMember['extraFields'][0]['identifier']);
        $t->same('fhcrc:v1', $inspectionMember['extraFields'][0]['data']);
        $t->same("# GZIP header CRC provenance\n\nReady for review.\n", $inspection['archive']->read('/packet/content.md'));
        $t->same(false, $plainMember['headerCrcPresent']);
        $t->same(null, $plainMember['headerCrc16']);
        $t->same(null, $plainMember['headerCrc16Hex']);
        $t->same(null, $plainMember['headerCrcOffset']);
        $t->same(null, $plainMember['headerCrcCoverageSize']);
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($tamperedHeaderCrc));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectTarStreamAuto($tamperedHeaderCrc, strlen($tarBytes)));
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

    'preflights gzip text hint policy for binary package payloads' => static function (TestRunner $t): void {
        $tarArchive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-text-hint-policy","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# GZIP text-hint policy\n\nReady for binary review.\n",
            ],
        ]);
        $tarBytes = $tarArchive->bytes();
        $zipBytes = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>GZIP text hint ZIP policy</w:p></w:body></w:document>',
            ],
        ])->bytes();
        $gzipTar = GzipStream::build($tarBytes, [
            'filename' => 'wordpress-text-hint-packet.tar',
            'comment' => 'claimed text but contains tar bytes',
            'textHint' => true,
        ]);
        $gzipZip = GzipStream::build($zipBytes, [
            'filename' => 'wordpress-text-hint-package.zip',
            'textHint' => true,
        ]);
        $plainTextGzip = GzipStream::build("Plain text review packet\n", [
            'filename' => 'wordpress-text-review.txt',
            'textHint' => true,
        ]);

        $tarPolicy = ArchiveCompressionStream::inspectGzipTextHintPolicy(
            $gzipTar,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes)
        );
        $zipPolicy = ArchiveCompressionStream::inspectGzipTextHintPolicy(
            $gzipZip,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $textPolicy = ArchiveCompressionStream::inspectGzipTextHintPolicy(
            $plainTextGzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen("Plain text review packet\n")
        );
        $tarInspection = ArchiveCompressionStream::inspectTarStream(
            $gzipTar,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes)
        );

        $t->same('gzip-text-hint-policy', $tarPolicy['type']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $tarPolicy['format']);
        $t->same(strlen($gzipTar), $tarPolicy['compressedSize']);
        $t->same(strlen($tarBytes), $tarPolicy['uncompressedSize']);
        $t->same(1, $tarPolicy['memberCount']);
        $t->same(1, $tarPolicy['textHintMemberCount']);
        $t->same(1, $tarPolicy['binaryTextHintMemberCount']);
        $t->same('review-before-conversion', $tarPolicy['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $tarPolicy['extractionPolicy']);
        $t->same(['gzip-text-hint-binary-payload'], $tarPolicy['diagnostics']);
        $t->same(false, isset($tarPolicy['archive']));
        $t->same(false, isset($tarPolicy['tarBytes']));
        $t->same(false, isset($tarPolicy['members'][0]['data']));
        $t->same('wordpress-text-hint-packet.tar', $tarPolicy['members'][0]['filename']);
        $t->same('claimed text but contains tar bytes', $tarPolicy['members'][0]['commentText']);
        $t->same(true, $tarPolicy['members'][0]['textHint']);
        $t->same(true, $tarPolicy['members'][0]['payloadLooksBinary']);
        $t->same(strlen($tarBytes), $tarPolicy['members'][0]['uncompressedSize']);
        $t->same(min(strlen($tarBytes), 4096), $tarPolicy['members'][0]['payloadProbeBytes']);
        $t->same('review', $tarPolicy['members'][0]['policy']);
        $t->same([
            'gzip-text-hint-binary-payload',
            'gzip-text-hint-payload-contains-nul',
        ], $tarPolicy['members'][0]['diagnostics']);
        $t->same($tarArchive->read('/packet/content.md'), $tarInspection['archive']->read('/packet/content.md'));

        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $zipPolicy['format']);
        $t->same(strlen($zipBytes), $zipPolicy['uncompressedSize']);
        $t->same(1, $zipPolicy['binaryTextHintMemberCount']);
        $t->same('review-before-conversion', $zipPolicy['handoffPolicy']);
        $t->same('wordpress-text-hint-package.zip', $zipPolicy['members'][0]['filename']);
        $t->same(true, $zipPolicy['members'][0]['payloadLooksBinary']);
        $t->same(['gzip-text-hint-binary-payload'], array_slice($zipPolicy['members'][0]['diagnostics'], 0, 1));

        $t->same(1, $textPolicy['textHintMemberCount']);
        $t->same(0, $textPolicy['binaryTextHintMemberCount']);
        $t->same('within-thresholds', $textPolicy['handoffPolicy']);
        $t->same([], $textPolicy['diagnostics']);
        $t->same(false, $textPolicy['members'][0]['payloadLooksBinary']);
        $t->same(strlen("Plain text review packet\n"), $textPolicy['members'][0]['payloadProbeBytes']);
        $t->same('metadata', $textPolicy['members'][0]['policy']);
        $t->same([], $textPolicy['members'][0]['diagnostics']);
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectGzipTextHintPolicy(
            $tarBytes,
            ArchiveCompressionStream::FORMAT_TAR
        ));
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

    'preflights gzip timestamp metadata before package handoff' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-timestamp-policy","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# GZIP timestamp policy\n\nReady for WordPress archive review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $secondOffset = 512;
        $thirdOffset = 1536;
        $gzip = GzipStream::build(substr($tarBytes, 0, $secondOffset), [
            'filename' => 'wordpress-timestamp-part-1.tar',
            'comment' => 'reproducible first package segment',
            'modifiedAt' => 0,
            'extraFlags' => 4,
            'operatingSystem' => 3,
        ]) . GzipStream::build(substr($tarBytes, $secondOffset, $thirdOffset - $secondOffset), [
            'filename' => 'wordpress-timestamp-part-2.tar',
            'comment' => 'timestamped second package segment',
            'modifiedAt' => 1780479010,
            'extraFlags' => 2,
            'operatingSystem' => 255,
        ]) . GzipStream::build(substr($tarBytes, $thirdOffset), [
            'filename' => 'wordpress-timestamp-part-3.tar',
            'comment' => 'timestamped final package segment',
            'modifiedAt' => 1780479020,
            'extraFlags' => 0,
            'operatingSystem' => 11,
        ]);
        $zipBytes = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>GZIP zero timestamp ZIP package</w:p></w:body></w:document>',
            ],
        ])->bytes();
        $zipGzip = GzipStream::build($zipBytes, [
            'filename' => 'wordpress-zero-timestamp-package.zip',
            'modifiedAt' => 0,
        ]);

        $policy = ArchiveCompressionStream::inspectGzipTimestampPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes)
        );
        $zipPolicy = ArchiveCompressionStream::inspectGzipTimestampPolicy(
            $zipGzip,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $roundTrip = ArchiveCompressionStream::openTar(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes)
        );

        $t->same('archive-gzip-timestamp-policy', $policy['type']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $policy['format']);
        $t->same(strlen($gzip), $policy['compressedSize']);
        $t->same(strlen($tarBytes), $policy['uncompressedSize']);
        $t->same(3, $policy['memberCount']);
        $t->same(2, $policy['timestampedMemberCount']);
        $t->same(1, $policy['unknownModifiedAtMemberCount']);
        $t->same(1780479010, $policy['earliestModifiedAt']);
        $t->same('2026-06-03T09:30:10Z', $policy['earliestModifiedAtText']);
        $t->same(1780479020, $policy['latestModifiedAt']);
        $t->same('2026-06-03T09:30:20Z', $policy['latestModifiedAtText']);
        $t->same(10, $policy['timestampSpreadSeconds']);
        $t->same('review-before-conversion', $policy['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $policy['extractionPolicy']);
        $t->same([
            'gzip-member-timestamp-metadata-present',
            'gzip-member-timestamp-metadata-varies',
        ], $policy['diagnostics']);
        $t->same([
            'wordpress-timestamp-part-1.tar',
            'wordpress-timestamp-part-2.tar',
            'wordpress-timestamp-part-3.tar',
        ], array_column($policy['members'], 'filename'));
        $t->same([
            'reproducible first package segment',
            'timestamped second package segment',
            'timestamped final package segment',
        ], array_column($policy['members'], 'commentText'));
        $t->same([0, 1780479010, 1780479020], array_column($policy['members'], 'modifiedAt'));
        $t->same([false, true, true], array_column($policy['members'], 'modifiedAtKnown'));
        $t->same([null, '2026-06-03T09:30:10Z', '2026-06-03T09:30:20Z'], array_column($policy['members'], 'modifiedAtText'));
        $t->same(['fastest-compression', 'maximum-compression', 'unspecified'], array_column($policy['members'], 'extraFlagsMeaning'));
        $t->same(['unix', 'unknown', 'ntfs-filesystem'], array_column($policy['members'], 'operatingSystemName'));
        $t->same(['metadata', 'review-before-conversion', 'review-before-conversion'], array_column($policy['members'], 'policy'));
        $t->same([
            [],
            ['gzip-member-mtime-present', 'gzip-member-mtime-varies'],
            ['gzip-member-mtime-present', 'gzip-member-mtime-varies'],
        ], array_column($policy['members'], 'diagnostics'));
        $t->same([0, $secondOffset, $thirdOffset], array_column($policy['members'], 'decodedDataOffset'));
        $t->same([$secondOffset, $thirdOffset, strlen($tarBytes)], array_column($policy['members'], 'decodedDataEndOffset'));
        $t->true(($policy['members'][1]['memberOffset'] ?? 0) > ($policy['members'][0]['memberOffset'] ?? 0));
        $t->true(($policy['members'][2]['memberOffset'] ?? 0) > ($policy['members'][1]['memberOffset'] ?? 0));
        $t->same(($policy['members'][1]['memberOffset'] ?? null), ($policy['members'][0]['nextMemberOffset'] ?? null));
        $t->same(($policy['members'][2]['memberOffset'] ?? null), ($policy['members'][1]['nextMemberOffset'] ?? null));
        $t->same(false, isset($policy['members'][0]['data']));
        $t->same(false, isset($policy['archive']));
        $t->same(false, isset($policy['tarBytes']));
        $t->same('{"source":"gzip-timestamp-policy","target":"wordpress"}', $roundTrip->read('/packet/manifest.json'));
        $t->same("# GZIP timestamp policy\n\nReady for WordPress archive review.\n", $roundTrip->read('/packet/content.md'));

        $t->same('archive-gzip-timestamp-policy', $zipPolicy['type']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $zipPolicy['format']);
        $t->same(1, $zipPolicy['memberCount']);
        $t->same(0, $zipPolicy['timestampedMemberCount']);
        $t->same(1, $zipPolicy['unknownModifiedAtMemberCount']);
        $t->same(null, $zipPolicy['earliestModifiedAt']);
        $t->same(null, $zipPolicy['latestModifiedAtText']);
        $t->same(null, $zipPolicy['timestampSpreadSeconds']);
        $t->same('within-thresholds', $zipPolicy['handoffPolicy']);
        $t->same([], $zipPolicy['diagnostics']);
        $t->same('wordpress-zero-timestamp-package.zip', $zipPolicy['members'][0]['filename']);
        $t->same('metadata', $zipPolicy['members'][0]['policy']);
        $t->same([], $zipPolicy['members'][0]['diagnostics']);
        $t->same(false, isset($zipPolicy['members'][0]['data']));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectGzipTimestampPolicy(
            $tarBytes,
            ArchiveCompressionStream::FORMAT_TAR
        ));
    },

    'preflights gzip platform metadata before package handoff' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-platform-policy","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# GZIP platform policy\n\nReady for WordPress archive review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $splitOffset = 1024;
        $gzip = GzipStream::build(substr($tarBytes, 0, $splitOffset), [
            'filename' => 'wordpress-platform-part-1.tar',
            'comment' => 'unix maximum compression segment',
            'extraFlags' => 2,
            'operatingSystem' => 3,
        ]) . GzipStream::build(substr($tarBytes, $splitOffset), [
            'filename' => 'wordpress-platform-part-2.tar',
            'comment' => 'ntfs fastest compression segment',
            'extraFlags' => 4,
            'operatingSystem' => 11,
        ]);
        $cleanGzip = GzipStream::build($tarBytes, [
            'filename' => 'wordpress-platform-clean.tar',
        ]);

        $policy = ArchiveCompressionStream::inspectGzipPlatformMetadataPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes)
        );
        $cleanPolicy = ArchiveCompressionStream::inspectGzipPlatformMetadataPolicy(
            $cleanGzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes)
        );
        $roundTrip = ArchiveCompressionStream::openTar(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes)
        );

        $t->same('archive-gzip-platform-metadata-policy', $policy['type']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $policy['format']);
        $t->same(strlen($gzip), $policy['compressedSize']);
        $t->same(strlen($tarBytes), $policy['uncompressedSize']);
        $t->same(2, $policy['memberCount']);
        $t->same(2, $policy['platformMetadataMemberCount']);
        $t->same(2, $policy['knownOperatingSystemMemberCount']);
        $t->same(0, $policy['unknownOperatingSystemMemberCount']);
        $t->same(2, $policy['optimizedCompressionMemberCount']);
        $t->same(0, $policy['unknownExtraFlagsMemberCount']);
        $t->same(['unix', 'ntfs-filesystem'], $policy['knownOperatingSystemNames']);
        $t->same(['maximum-compression', 'fastest-compression'], $policy['extraFlagsMeanings']);
        $t->same('review-before-conversion', $policy['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $policy['extractionPolicy']);
        $t->same([
            'gzip-platform-metadata-present',
            'gzip-platform-operating-system-varies',
            'gzip-compression-strategy-metadata-present',
        ], $policy['diagnostics']);
        $t->same([
            'wordpress-platform-part-1.tar',
            'wordpress-platform-part-2.tar',
        ], array_column($policy['members'], 'filename'));
        $t->same(['unix', 'ntfs-filesystem'], array_column($policy['members'], 'operatingSystemName'));
        $t->same(['maximum-compression', 'fastest-compression'], array_column($policy['members'], 'extraFlagsMeaning'));
        $t->same(['review-before-conversion', 'review-before-conversion'], array_column($policy['members'], 'policy'));
        $t->same([
            [
                'gzip-member-operating-system-present',
                'gzip-member-extra-flags-present',
                'gzip-member-operating-system-varies',
            ],
            [
                'gzip-member-operating-system-present',
                'gzip-member-extra-flags-present',
                'gzip-member-operating-system-varies',
            ],
        ], array_column($policy['members'], 'diagnostics'));
        $t->same([0, $splitOffset], array_column($policy['members'], 'decodedDataOffset'));
        $t->same([$splitOffset, strlen($tarBytes)], array_column($policy['members'], 'decodedDataEndOffset'));
        $t->same(false, isset($policy['members'][0]['data']));
        $t->same(false, isset($policy['archive']));
        $t->same(false, isset($policy['tarBytes']));
        $t->same("# GZIP platform policy\n\nReady for WordPress archive review.\n", $roundTrip->read('/packet/content.md'));

        $t->same('within-thresholds', $cleanPolicy['handoffPolicy']);
        $t->same(0, $cleanPolicy['platformMetadataMemberCount']);
        $t->same(0, $cleanPolicy['knownOperatingSystemMemberCount']);
        $t->same(1, $cleanPolicy['unknownOperatingSystemMemberCount']);
        $t->same(0, $cleanPolicy['optimizedCompressionMemberCount']);
        $t->same([], $cleanPolicy['diagnostics']);
        $t->same('metadata', $cleanPolicy['members'][0]['policy']);
        $t->same([], $cleanPolicy['members'][0]['diagnostics']);
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectGzipPlatformMetadataPolicy(
            $tarBytes,
            ArchiveCompressionStream::FORMAT_TAR
        ));
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

    'maps gzip decoded byte ranges for split package streams' => static function (TestRunner $t): void {
        $manifestBytes = '{"source":"gzip-decoded-byte-ranges","target":"wordpress"}';
        $contentBytes = "# GZIP decoded byte ranges\n\nReady for stream review.\n";
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => $manifestBytes,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $contentBytes,
            ],
        ]);
        $tarBytes = $archive->bytes();
        $firstLength = 512;
        $secondLength = 768;
        $thirdOffset = $firstLength + $secondLength;
        $firstMember = GzipStream::build(substr($tarBytes, 0, $firstLength), [
            'filename' => 'decoded-ranges-part-1.tar',
        ]);
        $secondMember = GzipStream::build(substr($tarBytes, $firstLength, $secondLength), [
            'filename' => 'decoded-ranges-part-2.tar',
        ]);
        $thirdMember = GzipStream::build(substr($tarBytes, $thirdOffset), [
            'filename' => 'decoded-ranges-part-3.tar',
        ]);
        $gzip = $firstMember . $secondMember . $thirdMember;

        $inspection = ArchiveCompressionStream::inspectTarStreamAuto(
            $gzip,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($contentBytes)
        );
        $members = $inspection['stream']['members'];
        $directMembers = GzipStream::members($gzip);
        $expectedStarts = [0, $firstLength, $thirdOffset];
        $expectedEnds = [$firstLength, $thirdOffset, strlen($tarBytes)];

        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $inspection['format']);
        $t->same(3, $inspection['stream']['memberCount']);
        $t->same($expectedStarts, array_map(static fn (array $member): int => $member['decodedDataOffset'], $members));
        $t->same($expectedEnds, array_map(static fn (array $member): int => $member['decodedDataEndOffset'], $members));
        $t->same([strlen($firstMember), strlen($secondMember), strlen($thirdMember)], array_map(static fn (array $member): int => $member['memberSize'], $members));
        $t->same($expectedStarts, array_map(static fn (array $member): int => $member['decodedDataOffset'], $directMembers));
        $t->same($expectedEnds, array_map(static fn (array $member): int => $member['decodedDataEndOffset'], $directMembers));
        $t->same($tarBytes, GzipStream::decode($gzip));
        $t->same($contentBytes, $inspection['archive']->read('/packet/content.md'));
    },

    'preflights gzip member count thresholds before package handoff' => static function (TestRunner $t): void {
        $manifestBytes = '{"source":"gzip-member-count","target":"wordpress"}';
        $contentBytes = "# GZIP member count\n\nReady for bounded stream review.\n";
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => $manifestBytes,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $contentBytes,
            ],
        ]);
        $tarBytes = $archive->bytes();
        $firstLength = 512;
        $secondLength = 768;
        $thirdOffset = $firstLength + $secondLength;
        $gzip = GzipStream::build(substr($tarBytes, 0, $firstLength), [
            'filename' => 'member-count-part-1.tar',
            'comment' => 'first decoded package segment',
        ]) . GzipStream::build(substr($tarBytes, $firstLength, $secondLength), [
            'filename' => 'member-count-part-2.tar',
            'comment' => 'second decoded package segment',
        ]) . GzipStream::build(substr($tarBytes, $thirdOffset), [
            'filename' => 'member-count-part-3.tar',
            'comment' => 'third decoded package segment',
        ]);

        $reviewPolicy = ArchiveCompressionStream::inspectGzipMemberCountPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            2,
            strlen($tarBytes)
        );
        $withinPolicy = ArchiveCompressionStream::inspectGzipMemberCountPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            3,
            strlen($tarBytes)
        );

        $t->same('archive-gzip-member-count-policy', $reviewPolicy['type']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $reviewPolicy['format']);
        $t->same(strlen($gzip), $reviewPolicy['compressedSize']);
        $t->same(strlen($tarBytes), $reviewPolicy['uncompressedSize']);
        $t->same(3, $reviewPolicy['memberCount']);
        $t->same(2, $reviewPolicy['maxMemberCount']);
        $t->same(1, $reviewPolicy['overLimitMemberCount']);
        $t->same(2, $reviewPolicy['firstOverLimitMemberIndex']);
        $t->same('review-before-conversion', $reviewPolicy['handoffPolicy']);
        $t->same('gzip-member-count-review', $reviewPolicy['extractionPolicy']);
        $t->same(['gzip-member-count-exceeds-threshold'], $reviewPolicy['diagnostics']);
        $t->same(0, $reviewPolicy['trailingPaddingBytes']);
        $t->same(['member-count-part-1.tar', 'member-count-part-2.tar', 'member-count-part-3.tar'], array_column($reviewPolicy['members'], 'filename'));
        $t->same(['first decoded package segment', 'second decoded package segment', 'third decoded package segment'], array_column($reviewPolicy['members'], 'comment'));
        $t->same([0, $firstLength, $thirdOffset], array_column($reviewPolicy['members'], 'decodedDataOffset'));
        $t->same([$firstLength, $thirdOffset, strlen($tarBytes)], array_column($reviewPolicy['members'], 'decodedDataEndOffset'));
        $t->same(['metadata', 'metadata', 'review-before-conversion'], array_column($reviewPolicy['members'], 'policy'));
        $t->same([[], [], ['gzip-member-over-limit']], array_column($reviewPolicy['members'], 'diagnostics'));
        $t->same(false, isset($reviewPolicy['members'][0]['data']));
        $t->same(false, isset($reviewPolicy['archive']));
        $t->same(false, isset($reviewPolicy['tarBytes']));

        $t->same(0, $withinPolicy['overLimitMemberCount']);
        $t->same(null, $withinPolicy['firstOverLimitMemberIndex']);
        $t->same('within-thresholds', $withinPolicy['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $withinPolicy['extractionPolicy']);
        $t->same([], $withinPolicy['diagnostics']);
        $t->same(['metadata', 'metadata', 'metadata'], array_column($withinPolicy['members'], 'policy'));

        $inspection = ArchiveCompressionStream::inspectTarStreamAuto(
            $gzip,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($contentBytes)
        );
        $t->same($contentBytes, $inspection['archive']->read('/packet/content.md'));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectGzipMemberCountPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_TAR,
            2
        ));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectGzipMemberCountPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            0
        ));
    },

    'preflights gzip member byte limits before package handoff' => static function (TestRunner $t): void {
        $manifestBytes = '{"source":"gzip-member-size","target":"wordpress"}';
        $contentBytes = "# GZIP member byte limit\n\nReady for bounded stream review.\n";
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => $manifestBytes,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $contentBytes,
            ],
        ]);
        $tarBytes = $archive->bytes();
        $firstLength = 512;
        $secondLength = 1536;
        $thirdOffset = $firstLength + $secondLength;
        $threshold = 1200;
        $gzip = GzipStream::build(substr($tarBytes, 0, $firstLength), [
            'filename' => 'member-size-part-1.tar',
            'comment' => 'first bounded decoded package segment',
        ]) . GzipStream::build(substr($tarBytes, $firstLength, $secondLength), [
            'filename' => 'member-size-part-2.tar',
            'comment' => 'oversized decoded package segment',
        ]) . GzipStream::build(substr($tarBytes, $thirdOffset), [
            'filename' => 'member-size-part-3.tar',
            'comment' => 'third bounded decoded package segment',
        ]);

        $reviewPolicy = ArchiveCompressionStream::inspectGzipMemberByteLimitPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            $threshold,
            strlen($tarBytes)
        );
        $withinPolicy = ArchiveCompressionStream::inspectGzipMemberByteLimitPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes),
            strlen($tarBytes)
        );

        $t->same('archive-gzip-member-byte-limit-policy', $reviewPolicy['type']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $reviewPolicy['format']);
        $t->same(strlen($gzip), $reviewPolicy['compressedSize']);
        $t->same(strlen($tarBytes), $reviewPolicy['uncompressedSize']);
        $t->same(3, $reviewPolicy['memberCount']);
        $t->same($threshold, $reviewPolicy['maxMemberUncompressedBytes']);
        $t->same(1, $reviewPolicy['overLimitMemberCount']);
        $t->same(1, $reviewPolicy['firstOverLimitMemberIndex']);
        $t->same($secondLength, $reviewPolicy['largestMemberUncompressedSize']);
        $t->same('review-before-conversion', $reviewPolicy['handoffPolicy']);
        $t->same('gzip-member-byte-limit-review', $reviewPolicy['extractionPolicy']);
        $t->same(['gzip-member-byte-limit-exceeds-threshold'], $reviewPolicy['diagnostics']);
        $t->same(0, $reviewPolicy['trailingPaddingBytes']);
        $t->same(['member-size-part-1.tar', 'member-size-part-2.tar', 'member-size-part-3.tar'], array_column($reviewPolicy['members'], 'filename'));
        $t->same(['first bounded decoded package segment', 'oversized decoded package segment', 'third bounded decoded package segment'], array_column($reviewPolicy['members'], 'comment'));
        $t->same([0, $firstLength, $thirdOffset], array_column($reviewPolicy['members'], 'decodedDataOffset'));
        $t->same([$firstLength, $thirdOffset, strlen($tarBytes)], array_column($reviewPolicy['members'], 'decodedDataEndOffset'));
        $t->same([$firstLength, $secondLength, strlen($tarBytes) - $thirdOffset], array_column($reviewPolicy['members'], 'uncompressedSize'));
        $t->same(['metadata', 'review-before-conversion', 'metadata'], array_column($reviewPolicy['members'], 'policy'));
        $t->same([[], ['gzip-member-byte-limit-over-limit'], []], array_column($reviewPolicy['members'], 'diagnostics'));
        $t->same(false, isset($reviewPolicy['members'][1]['data']));
        $t->same(false, isset($reviewPolicy['archive']));
        $t->same(false, isset($reviewPolicy['tarBytes']));

        $t->same(0, $withinPolicy['overLimitMemberCount']);
        $t->same(null, $withinPolicy['firstOverLimitMemberIndex']);
        $t->same($secondLength, $withinPolicy['largestMemberUncompressedSize']);
        $t->same('within-thresholds', $withinPolicy['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $withinPolicy['extractionPolicy']);
        $t->same([], $withinPolicy['diagnostics']);
        $t->same(['metadata', 'metadata', 'metadata'], array_column($withinPolicy['members'], 'policy'));

        $inspection = ArchiveCompressionStream::inspectTarStreamAuto(
            $gzip,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($contentBytes)
        );
        $t->same($contentBytes, $inspection['archive']->read('/packet/content.md'));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectGzipMemberByteLimitPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_ZIP,
            $threshold
        ));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectGzipMemberByteLimitPolicy(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            0
        ));
    },

    'preflights gzip member package boundaries before package handoff' => static function (TestRunner $t): void {
        $manifestBytes = '{"source":"gzip-member-boundary","target":"wordpress"}';
        $contentBytes = "# GZIP member boundary\n\nReady for split stream review.\n";
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => $manifestBytes,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $contentBytes,
            ],
        ]);
        $tarBytes = $archive->bytes();
        $splitOffset = 768;
        $splitGzip = GzipStream::build(substr($tarBytes, 0, $splitOffset), [
            'filename' => 'member-boundary-part-1.tar',
            'comment' => 'first decoded package segment',
        ]) . GzipStream::build(substr($tarBytes, $splitOffset), [
            'filename' => 'member-boundary-part-2.tar',
            'comment' => 'second decoded package segment',
        ]);

        $firstStandalone = TarArchive::fromEntries([
            [
                'name' => 'packet/first.md',
                'data' => "# First complete packet\n",
            ],
        ]);
        $secondStandalone = TarArchive::fromEntries([
            [
                'name' => 'packet/second.md',
                'data' => "# Second complete packet\n",
            ],
        ]);
        $twoPackageGzip = GzipStream::build($firstStandalone->bytes(), [
            'filename' => 'standalone-first.tar',
            'comment' => 'first complete package member',
        ]) . GzipStream::build($secondStandalone->bytes(), [
            'filename' => 'standalone-second.tar',
            'comment' => 'second complete package member',
        ]);

        $splitPolicy = ArchiveCompressionStream::inspectGzipMemberPackageBoundaryPolicy(
            $splitGzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($contentBytes)
        );
        $standalonePolicy = ArchiveCompressionStream::inspectGzipMemberPackageBoundaryPolicy(
            $twoPackageGzip,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            strlen($firstStandalone->bytes()) + strlen($secondStandalone->bytes())
        );

        $t->same('archive-gzip-member-package-boundary-policy', $splitPolicy['type']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $splitPolicy['expectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $splitPolicy['format']);
        $t->same(2, $splitPolicy['memberCount']);
        $t->same(strlen($splitGzip), $splitPolicy['compressedSize']);
        $t->same(strlen($tarBytes), $splitPolicy['decodedSize']);
        $t->same('package', $splitPolicy['combinedPackageStatus']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $splitPolicy['combinedEntryNames']);
        $t->same(0, $splitPolicy['standalonePackageMemberCount']);
        $t->same('single-decoded-package-stream', $splitPolicy['policy']);
        $t->same([], $splitPolicy['diagnostics']);
        $t->same([0, $splitOffset], array_map(static fn (array $member): int => $member['decodedDataOffset'], $splitPolicy['members']));
        $t->same([$splitOffset, strlen($tarBytes)], array_map(static fn (array $member): int => $member['decodedDataEndOffset'], $splitPolicy['members']));
        $t->same(['member-boundary-part-1.tar', 'member-boundary-part-2.tar'], array_map(static fn (array $member): ?string => $member['filename'], $splitPolicy['members']));
        $t->same([false, false], array_map(static fn (array $member): bool => $member['standalonePackage'], $splitPolicy['members']));
        $t->same(['package-segment', 'package-segment'], array_map(static fn (array $member): string => $member['policy'], $splitPolicy['members']));
        $t->same(false, isset($splitPolicy['members'][0]['data']));
        $t->same(false, isset($splitPolicy['combinedPackage']));

        $t->same('invalid', $standalonePolicy['combinedPackageStatus']);
        $t->true(str_contains($standalonePolicy['combinedPackageError'] ?? '', 'non-zero bytes after the end marker'));
        $t->same(2, $standalonePolicy['standalonePackageMemberCount']);
        $t->same('review-before-conversion', $standalonePolicy['policy']);
        $t->same([
            'gzip-combined-package-decode-failed',
            'gzip-members-contain-standalone-packages',
            'gzip-multiple-standalone-package-members',
        ], $standalonePolicy['diagnostics']);
        $t->same([true, true], array_map(static fn (array $member): bool => $member['standalonePackage'], $standalonePolicy['members']));
        $t->same([
            ['packet/first.md'],
            ['packet/second.md'],
        ], array_map(static fn (array $member): array => $member['entryNames'], $standalonePolicy['members']));
        $t->same(['standalone-gzip-member-package', 'standalone-gzip-member-package'], array_map(static fn (array $member): string => $member['policy'], $standalonePolicy['members']));
        $t->same([ArchiveCompressionStream::PACKAGE_KIND_TAR, ArchiveCompressionStream::PACKAGE_KIND_TAR], array_map(static fn (array $member): string => $member['kind'], $standalonePolicy['members']));
        $t->same([ArchiveCompressionStream::FORMAT_TAR, ArchiveCompressionStream::FORMAT_TAR], array_map(static fn (array $member): string => $member['format'], $standalonePolicy['members']));
        $t->same(false, isset($standalonePolicy['members'][0]['archive']));
        $t->same(false, isset($standalonePolicy['members'][1]['tarBytes']));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectGzipMemberPackageBoundaryPolicy(
            $splitGzip,
            ArchiveCompressionStream::FORMAT_ZLIB_TAR
        ));
    },

    'preflights decoded package chunks across split gzip source members' => static function (TestRunner $t): void {
        $manifestBytes = '{"source":"decoded-package-chunks","target":"wordpress"}';
        $contentBytes = "# Decoded package chunks\n\nReady for streaming archive review.\n";
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => $manifestBytes,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $contentBytes,
            ],
        ]);
        $tarBytes = $archive->bytes();
        $splitOffset = 1536;
        $gzip = GzipStream::build(substr($tarBytes, 0, $splitOffset), [
            'filename' => 'chunk-source-part-1.tar',
            'comment' => 'first decoded package segment',
        ]) . GzipStream::build(substr($tarBytes, $splitOffset), [
            'filename' => 'chunk-source-part-2.tar',
            'comment' => 'second decoded package segment',
        ]);

        $inspection = ArchiveCompressionStream::inspectDecodedPackageChunksAuto(
            $gzip,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($contentBytes),
            1024
        );

        $t->same('archive-decoded-package-chunk-policy', $inspection['type']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $inspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $inspection['format']);
        $t->same(strlen($gzip), $inspection['compressedSize']);
        $t->same(strlen($tarBytes), $inspection['decodedPackageSize']);
        $t->same(1024, $inspection['chunkSize']);
        $t->same(3, $inspection['chunkCount']);
        $t->same(2, $inspection['entryCount']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $inspection['entryNames']);
        $t->same('within-thresholds', $inspection['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $inspection['extractionPolicy']);
        $t->same('gzip', $inspection['stream']['type']);
        $t->same(2, $inspection['stream']['memberCount']);
        $t->same(['chunk-source-part-1.tar', 'chunk-source-part-2.tar'], array_column($inspection['stream']['members'], 'filename'));
        $t->same([0, $splitOffset], array_column($inspection['stream']['members'], 'decodedDataOffset'));
        $t->same([$splitOffset, strlen($tarBytes)], array_column($inspection['stream']['members'], 'decodedDataEndOffset'));
        $t->same([0, 1024, 2048], array_column($inspection['chunks'], 'decodedOffset'));
        $t->same([1024, 2048, strlen($tarBytes)], array_column($inspection['chunks'], 'decodedEndOffset'));
        $t->same([1, 2, 1], array_column($inspection['chunks'], 'sourceSegmentCount'));
        $t->same([false, true, false], array_column($inspection['chunks'], 'crossesSourceBoundary'));
        $t->same(['gzip-member'], array_column($inspection['chunks'][0]['sourceSegments'], 'sourceType'));
        $t->same(['chunk-source-part-1.tar'], array_column($inspection['chunks'][0]['sourceSegments'], 'sourceLabel'));
        $t->same(['gzip-member', 'gzip-member'], array_column($inspection['chunks'][1]['sourceSegments'], 'sourceType'));
        $t->same(['chunk-source-part-1.tar', 'chunk-source-part-2.tar'], array_column($inspection['chunks'][1]['sourceSegments'], 'sourceLabel'));
        $t->same([1024, $splitOffset], array_column($inspection['chunks'][1]['sourceSegments'], 'sourceDecodedOffset'));
        $t->same([$splitOffset, 2048], array_column($inspection['chunks'][1]['sourceSegments'], 'sourceDecodedEndOffset'));
        $t->same([0, $splitOffset - 1024], array_column($inspection['chunks'][1]['sourceSegments'], 'chunkOffset'));
        $t->same([$splitOffset - 1024, 1024], array_column($inspection['chunks'][1]['sourceSegments'], 'chunkEndOffset'));
        $t->same(['chunk-source-part-2.tar'], array_column($inspection['chunks'][2]['sourceSegments'], 'sourceLabel'));
        $t->same('metadata-only-no-extraction', $inspection['chunks'][1]['policy']);
        $t->same(false, isset($inspection['tarBytes']));
        $t->same(false, isset($inspection['zipBytes']));
        $t->same(false, isset($inspection['archive']));
        $t->same(false, isset($inspection['package']));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectDecodedPackageChunksAuto(
            $gzip,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($contentBytes),
            0
        ));
    },

    'maps tar entry layouts to decoded compression stream source segments' => static function (TestRunner $t): void {
        $manifestBytes = '{"source":"entry-source-segments","target":"wordpress"}';
        $contentBytes = "# Entry source segments\n\nReady for split stream review.\n";
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => $manifestBytes,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $contentBytes,
            ],
        ]);
        $tarBytes = $archive->bytes();
        $splitOffset = 1280;
        $gzip = GzipStream::build(substr($tarBytes, 0, $splitOffset), [
            'filename' => 'entry-source-part-1.tar',
        ]) . GzipStream::build(substr($tarBytes, $splitOffset), [
            'filename' => 'entry-source-part-2.tar',
        ]);
        $lz4 = Lz4Frame::skippableFrame('entry-source-segments', 6)
            . Lz4Frame::build(substr($tarBytes, 0, $splitOffset), [
                'contentSize' => true,
            ])
            . Lz4Frame::build(substr($tarBytes, $splitOffset), [
                'contentSize' => true,
            ]);

        $plainInspection = ArchiveCompressionStream::inspectTarStream(
            $tarBytes,
            ArchiveCompressionStream::FORMAT_TAR,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($contentBytes)
        );
        $gzipInspection = ArchiveCompressionStream::inspectTarStreamAuto(
            $gzip,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($contentBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectTarStream(
            $lz4,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($contentBytes)
        );

        $gzipManifestLayout = $gzipInspection['entryLayouts'][0];
        $gzipContentLayout = $gzipInspection['entryLayouts'][1];
        $lz4ContentSegments = $lz4Inspection['entryLayouts'][1]['decodedSourceSegments'];

        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $gzipInspection['format']);
        $t->same(ArchiveCompressionStream::FORMAT_LZ4_TAR, $lz4Inspection['format']);
        $t->same(1, $plainInspection['entryLayouts'][0]['decodedSourceSegmentCount']);
        $t->same('plain-tar', $plainInspection['entryLayouts'][0]['decodedSourceSegments'][0]['sourceType']);
        $t->same(1, $gzipManifestLayout['decodedSourceSegmentCount']);
        $t->same([
            [
                'sourceType' => 'gzip-member',
                'sourceIndex' => 0,
                'sourceLabel' => 'entry-source-part-1.tar',
                'sourceDecodedOffset' => 0,
                'sourceDecodedEndOffset' => 1024,
                'entryRecordOffset' => 0,
                'entryRecordEndOffset' => 1024,
            ],
        ], $gzipManifestLayout['decodedSourceSegments']);
        $t->same(1024, $gzipContentLayout['headerOffset']);
        $t->same(1024, $gzipContentLayout['recordSize']);
        $t->same(2, $gzipContentLayout['decodedSourceSegmentCount']);
        $t->same([
            [
                'sourceType' => 'gzip-member',
                'sourceIndex' => 0,
                'sourceLabel' => 'entry-source-part-1.tar',
                'sourceDecodedOffset' => 1024,
                'sourceDecodedEndOffset' => 1280,
                'entryRecordOffset' => 0,
                'entryRecordEndOffset' => 256,
            ],
            [
                'sourceType' => 'gzip-member',
                'sourceIndex' => 1,
                'sourceLabel' => 'entry-source-part-2.tar',
                'sourceDecodedOffset' => 1280,
                'sourceDecodedEndOffset' => 2048,
                'entryRecordOffset' => 256,
                'entryRecordEndOffset' => 1024,
            ],
        ], $gzipContentLayout['decodedSourceSegments']);
        $t->same(['lz4-frame', 'lz4-frame'], array_column($lz4ContentSegments, 'sourceType'));
        $t->same([0, 1], array_column($lz4ContentSegments, 'sourceIndex'));
        $t->same([1024, 1280], array_column($lz4ContentSegments, 'sourceDecodedOffset'));
        $t->same([1280, 2048], array_column($lz4ContentSegments, 'sourceDecodedEndOffset'));
        $t->same([0, 256], array_column($lz4ContentSegments, 'entryRecordOffset'));
        $t->same([256, 1024], array_column($lz4ContentSegments, 'entryRecordEndOffset'));
        $t->same($contentBytes, $gzipInspection['archive']->read('/packet/content.md'));
        $t->same($contentBytes, $lz4Inspection['archive']->read('/packet/content.md'));
    },

    'maps tar metadata record layouts to decoded compression stream source segments' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $contentBytes = "# TAR metadata layout\n\nReady for WordPress archive provenance review.\n";
        $globalPaxPayload = $paxPayload([
            'comment' => 'global reviewer metadata',
        ]);
        $localPaxPayload = $paxPayload([
            'path' => 'packet/metadata-layout/content.md',
            'mtime' => '1780479101',
        ]);
        $tarBytes = $rawTarHeader('GlobalHead/metadata-layout', 'g', $globalPaxPayload, 0, false)
            . $rawTarHeader('PaxHeaders/metadata-layout', 'x', $localPaxPayload, 0, false)
            . $rawTarHeader('placeholder.md', '0', $contentBytes, 1780479100, false)
            . str_repeat("\0", 1024);
        $splitOffset = 1536;
        $gzip = GzipStream::build(substr($tarBytes, 0, $splitOffset), [
            'filename' => 'metadata-layout-part-1.tar',
            'comment' => 'global pax and local pax header source',
        ]) . GzipStream::build(substr($tarBytes, $splitOffset), [
            'filename' => 'metadata-layout-part-2.tar',
            'comment' => 'local pax payload tail and content source',
        ]);

        $inspection = ArchiveCompressionStream::inspectTarStreamAuto(
            $gzip,
            strlen($tarBytes),
            strlen($contentBytes)
        );
        $metadataLayouts = $inspection['metadataLayouts'] ?? [];

        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $inspection['format']);
        $t->same(2, $inspection['metadataLayoutCount'] ?? 0);
        $t->same(2, count($metadataLayouts));
        $t->same(['pax-global', 'pax-local'], array_column($metadataLayouts, 'role'));
        $t->same(['pax-global', 'pax-local'], array_column($metadataLayouts, 'metadataKind'));
        $t->same(['comment'], $metadataLayouts[0]['paxHeaderKeys']);
        $t->same(['mtime', 'path'], $metadataLayouts[1]['paxHeaderKeys']);
        $t->same([0, 1024], array_column($metadataLayouts, 'headerOffset'));
        $t->same([1024, 2048], array_column($metadataLayouts, 'recordEndOffset'));
        $t->same([strlen($globalPaxPayload), strlen($localPaxPayload)], array_column($metadataLayouts, 'payloadSize'));
        $t->same(1, $metadataLayouts[0]['decodedSourceSegmentCount']);
        $t->same([
            [
                'sourceType' => 'gzip-member',
                'sourceIndex' => 0,
                'sourceLabel' => 'metadata-layout-part-1.tar',
                'sourceDecodedOffset' => 0,
                'sourceDecodedEndOffset' => 1024,
                'recordOffset' => 0,
                'recordEndOffset' => 1024,
            ],
        ], $metadataLayouts[0]['decodedSourceSegments']);
        $t->same(2, $metadataLayouts[1]['decodedSourceSegmentCount']);
        $t->same(['gzip-member', 'gzip-member'], array_column($metadataLayouts[1]['decodedSourceSegments'], 'sourceType'));
        $t->same(['metadata-layout-part-1.tar', 'metadata-layout-part-2.tar'], array_column($metadataLayouts[1]['decodedSourceSegments'], 'sourceLabel'));
        $t->same([1024, 1536], array_column($metadataLayouts[1]['decodedSourceSegments'], 'sourceDecodedOffset'));
        $t->same([1536, 2048], array_column($metadataLayouts[1]['decodedSourceSegments'], 'sourceDecodedEndOffset'));
        $t->same([0, 512], array_column($metadataLayouts[1]['decodedSourceSegments'], 'recordOffset'));
        $t->same([512, 1024], array_column($metadataLayouts[1]['decodedSourceSegments'], 'recordEndOffset'));
        $t->same('packet/metadata-layout/content.md', $inspection['entryLayouts'][0]['name']);
        $t->same(['comment', 'mtime', 'path'], $inspection['entryLayouts'][0]['paxHeaderKeys']);
        $t->same($contentBytes, $inspection['archive']->read('/packet/metadata-layout/content.md'));
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
        $plainComparableLayouts = array_map(static function (array $layout): array {
            unset($layout['decodedSourceSegmentCount'], $layout['decodedSourceSegments']);

            return $layout;
        }, $plainInspection['entryLayouts']);
        $gzipComparableLayouts = array_map(static function (array $layout): array {
            unset($layout['decodedSourceSegmentCount'], $layout['decodedSourceSegments']);

            return $layout;
        }, $gzipInspection['entryLayouts']);
        $t->same($plainComparableLayouts, $gzipComparableLayouts);
        $t->same('gzip-member', $gzipInspection['entryLayouts'][0]['decodedSourceSegments'][0]['sourceType']);
        $t->same('wordpress-layout-review.tar', $gzipInspection['entryLayouts'][0]['decodedSourceSegments'][0]['sourceLabel']);
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

    'maps zip local entry layouts to decoded compression stream source segments' => static function (TestRunner $t): void {
        $contentTypesXml = '<Types><Default Extension="xml" ContentType="application/xml"/></Types>';
        $documentXml = '<w:document><w:body><w:p>ZIP entry source segment review packet</w:p></w:body></w:document>'
            . str_repeat('<w:p>Review paragraph.</w:p>', 8);
        $stylesXml = '<w:styles><w:style w:type="paragraph" w:styleId="Normal"/></w:styles>';
        $package = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => $contentTypesXml,
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/styles.xml',
                'data' => $stylesXml,
                'compressionMethod' => 0,
            ],
        ]);
        $zipBytes = $package->bytes();
        $localPreflight = $package->localHeaderPreflight();
        $documentLocal = $localPreflight['entries'][1];
        $splitOffset = $documentLocal['dataStart'] + 40;
        $gzip = GzipStream::build(substr($zipBytes, 0, $splitOffset), [
            'filename' => 'zip-entry-source-part-1.zip',
            'comment' => 'first ZIP entry byte source',
        ]) . GzipStream::build(substr($zipBytes, $splitOffset), [
            'filename' => 'zip-entry-source-part-2.zip',
            'comment' => 'second ZIP entry byte source',
        ]);

        $inspection = ArchiveCompressionStream::inspectZipStream(
            $gzip,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );

        $layouts = $inspection['entryLayouts'];
        $documentLayout = $layouts[1];
        $documentRecordSplitOffset = $splitOffset - $documentLocal['localHeaderOffset'];

        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $inspection['format']);
        $t->same($package->names(), $inspection['entryNames']);
        $t->same('gzip', $inspection['stream']['type']);
        $t->same(['zip-entry-source-part-1.zip', 'zip-entry-source-part-2.zip'], array_column($inspection['stream']['members'], 'filename'));
        $t->same(3, count($layouts));
        $t->same('[Content_Types].xml', $layouts[0]['name']);
        $t->same('word/document.xml', $documentLayout['name']);
        $t->same('file', $documentLayout['type']);
        $t->same(1, $documentLayout['centralDirectoryIndex']);
        $t->same(1, $documentLayout['localHeaderOrder']);
        $t->same(0, $documentLayout['compressionMethod']);
        $t->same(0x0800, $documentLayout['generalPurposeFlags']);
        $t->same(strlen($documentXml), $documentLayout['compressedSize']);
        $t->same(strlen($documentXml), $documentLayout['uncompressedSize']);
        $t->same($documentLocal['localHeaderOffset'], $documentLayout['localHeaderOffset']);
        $t->same($documentLocal['localHeaderLength'], $documentLayout['localHeaderLength']);
        $t->same($documentLocal['dataStart'], $documentLayout['compressedDataOffset']);
        $t->same($documentLocal['compressedDataEnd'], $documentLayout['compressedDataEndOffset']);
        $t->same(false, $documentLayout['usesDataDescriptor']);
        $t->same(null, $documentLayout['descriptorOffset']);
        $t->same(null, $documentLayout['descriptorLength']);
        $t->same($documentLocal['recordEnd'], $documentLayout['recordEndOffset']);
        $t->same($documentLocal['recordEnd'] - $documentLocal['localHeaderOffset'], $documentLayout['recordSize']);
        $t->same(2, $documentLayout['decodedSourceSegmentCount']);
        $t->same([
            [
                'sourceType' => 'gzip-member',
                'sourceIndex' => 0,
                'sourceLabel' => 'zip-entry-source-part-1.zip',
                'sourceDecodedOffset' => $documentLocal['localHeaderOffset'],
                'sourceDecodedEndOffset' => $splitOffset,
                'entryRecordOffset' => 0,
                'entryRecordEndOffset' => $documentRecordSplitOffset,
            ],
            [
                'sourceType' => 'gzip-member',
                'sourceIndex' => 1,
                'sourceLabel' => 'zip-entry-source-part-2.zip',
                'sourceDecodedOffset' => $splitOffset,
                'sourceDecodedEndOffset' => $documentLocal['recordEnd'],
                'entryRecordOffset' => $documentRecordSplitOffset,
                'entryRecordEndOffset' => $documentLayout['recordSize'],
            ],
        ], $documentLayout['decodedSourceSegments']);
        $t->same('word/styles.xml', $layouts[2]['name']);
        $t->same(1, $layouts[2]['decodedSourceSegmentCount']);
        $t->same('zip-entry-source-part-2.zip', $layouts[2]['decodedSourceSegments'][0]['sourceLabel']);
        $t->same($documentXml, $inspection['package']->read('/word/document.xml'));
    },

    'preflights zip data descriptors across archive streams without losing provenance' => static function (TestRunner $t) use ($zipFixtureBytes): void {
        $documentXml = '<w:document><w:body><w:p>Descriptor-backed document.xml</w:p></w:body></w:document>';
        $footnotesXml = '<w:footnotes><w:footnote w:id="1">Descriptor-backed footnote</w:footnote></w:footnotes>';
        $zipBytes = $zipFixtureBytes([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'compressionMethod' => 8,
                'descriptor' => true,
            ],
            [
                'name' => 'word/footnotes.xml',
                'data' => $footnotesXml,
                'compressionMethod' => 0,
                'descriptor' => true,
                'descriptorSignature' => false,
            ],
        ], 'zip descriptor review fixture');
        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-descriptor-package.zip',
                'comment' => 'zip descriptor preflight fixture',
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
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('zip descriptor reviewer metadata', 11)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        foreach ($streams as $format => $bytes) {
            $inspection = ArchiveCompressionStream::inspectZipDataDescriptorPolicy($bytes, $format, strlen($zipBytes));
            $descriptorEntries = $inspection['descriptorEntries'];

            $t->same($format, $inspection['format']);
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same(3, $inspection['entryCount']);
            $t->same(2, $inspection['descriptorEntryCount']);
            $t->same(1, $inspection['signedDescriptorEntryCount']);
            $t->same(1, $inspection['unsignedDescriptorEntryCount']);
            $t->same(0, $inspection['zip64SizedDescriptorEntryCount']);
            $t->same([
                'word/document.xml',
                'word/footnotes.xml',
            ], array_column($descriptorEntries, 'name'));
            $t->same([
                true,
                false,
            ], array_column($descriptorEntries, 'hasSignature'));
            $t->same([
                16,
                12,
            ], array_column($descriptorEntries, 'descriptorLength'));
            $t->same([
                true,
                true,
            ], array_column($descriptorEntries, 'hasZeroLocalHeaderPlaceholders'));
            $t->same([
                0,
                0,
            ], array_column($descriptorEntries, 'localHeaderCrc32'));
            $t->same([
                $descriptorEntries[0]['descriptorOffset'] + 4,
                $descriptorEntries[1]['descriptorOffset'],
            ], array_column($descriptorEntries, 'valueOffset'));
            $t->same((int) sprintf('%u', crc32($documentXml)), $descriptorEntries[0]['crc32']);
            $t->same((int) sprintf('%u', crc32($footnotesXml)), $descriptorEntries[1]['crc32']);
            $t->same(strlen(gzdeflate($documentXml)), $descriptorEntries[0]['compressedSize']);
            $t->same(strlen($footnotesXml), $descriptorEntries[1]['compressedSize']);
            $t->same([
                false,
                false,
            ], array_column($descriptorEntries, 'usesZip64SizedDescriptor'));
            $t->same('word/document.xml', $inspection['entries'][1]['name']);
            $t->same(true, $inspection['entries'][1]['usesDataDescriptor']);
            $t->same(false, $inspection['entries'][0]['usesDataDescriptor']);
        }

        $gzipInspection = ArchiveCompressionStream::inspectZipDataDescriptorPolicy(
            $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectZipDataDescriptorPolicy(
            $streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP],
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            strlen($zipBytes)
        );

        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-descriptor-package.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('zip descriptor preflight fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same('zip descriptor reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
        $t->throws(
            \RuntimeException::class,
            static fn (): array => ArchiveCompressionStream::inspectZipDataDescriptorPolicy(
                $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
                ArchiveCompressionStream::FORMAT_GZIP_TAR,
                strlen($zipBytes)
            )
        );
    },

    'preflights zip data descriptor integrity across archive streams without accepting zip64 descriptors' => static function (TestRunner $t) use ($zipFixtureBytes): void {
        $documentXml = '<w:document><w:body><w:p>ZIP64 descriptor document.xml</w:p></w:body></w:document>';
        $footnotesXml = '<w:footnotes><w:footnote w:id="2">ZIP64 descriptor footnote</w:footnote></w:footnotes>';
        $zipBytes = $zipFixtureBytes([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'compressionMethod' => 8,
                'descriptor' => true,
                'descriptorZip64' => true,
            ],
            [
                'name' => 'word/footnotes.xml',
                'data' => $footnotesXml,
                'compressionMethod' => 0,
                'descriptor' => true,
                'descriptorSignature' => false,
                'descriptorZip64' => true,
            ],
        ], 'zip64 descriptor integrity fixture');
        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-zip64-descriptor-package.zip',
                'comment' => 'zip64 descriptor integrity preflight fixture',
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
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('zip64 descriptor reviewer metadata', 12)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        $zip64ExtractionBlocked = false;
        try {
            ZipPackage::fromString($zipBytes);
        } catch (\RuntimeException) {
            $zip64ExtractionBlocked = true;
        }

        $t->same(true, $zip64ExtractionBlocked);

        foreach ($streams as $format => $bytes) {
            $inspection = ArchiveCompressionStream::inspectZipDataDescriptorIntegrityPolicy($bytes, $format, strlen($zipBytes));
            $descriptorEntries = $inspection['descriptorEntries'];

            $t->same($format, $inspection['format']);
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same(3, $inspection['entryCount']);
            $t->same(3, $inspection['totalEntryCount']);
            $t->same(2, $inspection['descriptorEntryCount']);
            $t->same(0, $inspection['matchedDescriptorEntryCount']);
            $t->same(2, $inspection['mismatchedDescriptorEntryCount']);
            $t->same(1, $inspection['signedDescriptorEntryCount']);
            $t->same(1, $inspection['unsignedDescriptorEntryCount']);
            $t->same(2, $inspection['zip64SizedDescriptorEntryCount']);
            $t->same(false, $inspection['isSupportedByBoundedReader']);
            $t->same(['zip64-sized-data-descriptor'], $inspection['issues']);
            $t->same([
                'word/document.xml',
                'word/footnotes.xml',
            ], array_column($descriptorEntries, 'name'));
            $t->same([
                true,
                false,
            ], array_column($descriptorEntries, 'hasSignature'));
            $t->same([
                24,
                20,
            ], array_column($descriptorEntries, 'descriptorLength'));
            $t->same([
                true,
                true,
            ], array_column($descriptorEntries, 'usesZip64SizedDescriptor'));
            $t->same([
                true,
                true,
            ], array_column($descriptorEntries, 'descriptorValuesMatchCentral'));
            $t->same([
                ['zip64-sized-data-descriptor'],
                ['zip64-sized-data-descriptor'],
            ], array_column($descriptorEntries, 'issues'));
            $t->same($descriptorEntries, $inspection['mismatchedDescriptorEntries']);
            $t->same([
                true,
                true,
            ], array_column($descriptorEntries, 'hasZeroLocalHeaderPlaceholders'));
            $t->same([
                $descriptorEntries[0]['descriptorOffset'] + 4,
                $descriptorEntries[1]['descriptorOffset'],
            ], array_column($descriptorEntries, 'valueOffset'));
            $t->same((int) sprintf('%u', crc32($documentXml)), $descriptorEntries[0]['crc32']);
            $t->same((int) sprintf('%u', crc32($footnotesXml)), $descriptorEntries[1]['crc32']);
            $t->same(strlen(gzdeflate($documentXml)), $descriptorEntries[0]['compressedSize']);
            $t->same(strlen($footnotesXml), $descriptorEntries[1]['compressedSize']);
            $t->same('word/document.xml', $inspection['entries'][1]['name']);
            $t->same(true, $inspection['entries'][1]['usesDataDescriptor']);
            $t->same(false, $inspection['entries'][0]['usesDataDescriptor']);
        }

        $gzipInspection = ArchiveCompressionStream::inspectZipDataDescriptorIntegrityPolicy(
            $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectZipDataDescriptorIntegrityPolicy(
            $streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP],
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            strlen($zipBytes)
        );

        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-zip64-descriptor-package.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('zip64 descriptor integrity preflight fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same('zip64 descriptor reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
        $t->throws(
            \RuntimeException::class,
            static fn (): array => ArchiveCompressionStream::inspectZipDataDescriptorIntegrityPolicy(
                $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
                ArchiveCompressionStream::FORMAT_GZIP_TAR,
                strlen($zipBytes)
            )
        );
    },

    'preflights zip64 end of central directory across archive streams without exposing package entries' => static function (TestRunner $t) use ($zipFixtureBytes, $buildZip64EndOfCentralDirectoryZip, $rewriteZipEndOfCentralDirectory): void {
        $zipBytes = $buildZip64EndOfCentralDirectoryZip($zipFixtureBytes([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>ZIP64 EOCD archive stream</w:p></w:body></w:document>',
                'compressionMethod' => 8,
            ],
        ], 'zip64 eocd archive stream fixture'));
        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-zip64-eocd-package.zip',
                'comment' => 'zip64 end of central directory preflight fixture',
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
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('zip64 eocd reviewer metadata', 12)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        $zip64ExtractionBlocked = false;
        try {
            ZipPackage::fromString($zipBytes);
        } catch (\RuntimeException) {
            $zip64ExtractionBlocked = true;
        }

        $t->same(true, $zip64ExtractionBlocked);

        foreach ($streams as $format => $bytes) {
            $inspection = ArchiveCompressionStream::inspectZip64EndOfCentralDirectoryPolicy($bytes, $format, strlen($zipBytes));

            $t->same($format, $inspection['format']);
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same(true, $inspection['requiresZip64']);
            $t->same(false, $inspection['isSupportedByBoundedReader']);
            $t->same(true, $inspection['hasZip64EndOfCentralDirectoryLocator']);
            $t->same(true, $inspection['hasZip64EndOfCentralDirectory']);
            $t->same(['zip64-end-of-central-directory'], $inspection['issues']);
            $t->same(44, $inspection['recordPayloadSize']);
            $t->same(56, $inspection['recordSize']);
            $t->same(45, $inspection['versionMadeBy']);
            $t->same(45, $inspection['versionNeededToExtract']);
            $t->same(0, $inspection['locatorDiskWithEndOfCentralDirectory']);
            $t->same(1, $inspection['locatorTotalDisks']);
            $t->same(0, $inspection['diskNumber']);
            $t->same(0, $inspection['centralDirectoryDisk']);
            $t->same(1, $inspection['diskEntryCount']);
            $t->same(1, $inspection['totalEntryCount']);
            $t->same($inspection['centralDirectoryOffset'] + $inspection['centralDirectorySize'], $inspection['centralDirectoryEnd']);
            $t->same($inspection['recordOffset'], $inspection['centralDirectoryEnd']);
            $t->same(true, $inspection['centralDirectoryEndMatchesRecordOffset']);
            $t->same(true, $inspection['isSingleDisk']);
            $t->same(0, $inspection['eocdDiskNumber']);
            $t->same(0, $inspection['eocdCentralDirectoryDisk']);
            $t->same(0xffff, $inspection['eocdDiskEntryCount']);
            $t->same(0xffff, $inspection['eocdTotalEntryCount']);
            $t->same(0xffffffff, $inspection['eocdCentralDirectorySize']);
            $t->same(0xffffffff, $inspection['eocdCentralDirectoryOffset']);
        }

        $gzipInspection = ArchiveCompressionStream::inspectZip64EndOfCentralDirectoryPolicy(
            $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectZip64EndOfCentralDirectoryPolicy(
            $streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP],
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            strlen($zipBytes)
        );

        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-zip64-eocd-package.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('zip64 end of central directory preflight fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same('zip64 eocd reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);

        $locatorOffset = $gzipInspection['locatorOffset'];
        if ($locatorOffset === null) {
            throw new \RuntimeException('Expected ZIP64 locator offset in fixture.');
        }

        $mismatchedLocatorZip = substr_replace($zipBytes, pack('V', 2), $locatorOffset + 16, 4);
        $mismatchedLocatorInspection = ArchiveCompressionStream::inspectZip64EndOfCentralDirectoryPolicy(
            DeflateStream::build($mismatchedLocatorZip, [
                'format' => DeflateStream::FORMAT_ZLIB,
                'compressionLevel' => 9,
            ]),
            ArchiveCompressionStream::FORMAT_ZLIB_ZIP,
            strlen($mismatchedLocatorZip)
        );
        $t->same(2, $mismatchedLocatorInspection['locatorTotalDisks']);
        $t->same(false, $mismatchedLocatorInspection['isSingleDisk']);
        $t->same([
            'zip64-end-of-central-directory',
            'zip64-split-archive',
            'zip64-locator-total-disks-mismatch',
        ], $mismatchedLocatorInspection['issues']);

        $sentinelOnlyZip = $rewriteZipEndOfCentralDirectory($zipFixtureBytes([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>ZIP64 sentinel-only EOCD</w:p></w:body></w:document>',
                'compressionMethod' => 8,
            ],
        ], 'zip64 sentinel-only archive stream fixture'), [
            'totalEntryCount' => 0xffff,
            'centralDirectorySize' => 0xffffffff,
        ]);
        $sentinelOnlyInspection = ArchiveCompressionStream::inspectZip64EndOfCentralDirectoryPolicy(
            DeflateStream::build($sentinelOnlyZip, [
                'format' => DeflateStream::FORMAT_RAW,
                'compressionLevel' => 9,
            ]),
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_ZIP,
            strlen($sentinelOnlyZip)
        );

        $t->same(true, $sentinelOnlyInspection['requiresZip64']);
        $t->same(false, $sentinelOnlyInspection['hasZip64EndOfCentralDirectoryLocator']);
        $t->same(false, $sentinelOnlyInspection['hasZip64EndOfCentralDirectory']);
        $t->same(false, $sentinelOnlyInspection['isSupportedByBoundedReader']);
        $t->same(['zip64-end-of-central-directory-required'], $sentinelOnlyInspection['issues']);
        $t->same(null, $sentinelOnlyInspection['locatorOffset']);
        $t->same(null, $sentinelOnlyInspection['recordOffset']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($sentinelOnlyZip));
        $t->throws(
            \RuntimeException::class,
            static fn (): array => ArchiveCompressionStream::inspectZip64EndOfCentralDirectoryPolicy(
                $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
                ArchiveCompressionStream::FORMAT_GZIP_TAR,
                strlen($zipBytes)
            )
        );
    },

    'preflights zip64 extra fields across compressed archive streams before package exposure' => static function (TestRunner $t) use ($zipFixtureBytes, $packZip64UInt64): void {
        $documentXml = '<w:document><w:body><w:p>ZIP64 extra field archive stream</w:p></w:body></w:document>';
        $documentDeflated = gzdeflate($documentXml);
        $centralZip64Values = $packZip64UInt64(strlen($documentXml))
            . $packZip64UInt64(strlen($documentDeflated))
            . $packZip64UInt64(0)
            . pack('V', 0);
        $centralZip64Extra = pack('vv', 0x0001, strlen($centralZip64Values)) . $centralZip64Values;
        $localData = "local ZIP64 stream wrapper metadata\n";
        $localZip64Values = $packZip64UInt64(strlen($localData)) . $packZip64UInt64(strlen($localData));
        $localZip64Extra = pack('vv', 0x0001, strlen($localZip64Values)) . $localZip64Values;
        $zipBytes = $zipFixtureBytes([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'compressionMethod' => 8,
                'centralVersionNeededToExtract' => 45,
                'centralCompressedSize' => 0xffffffff,
                'centralUncompressedSize' => 0xffffffff,
                'centralLocalHeaderOffset' => 0xffffffff,
                'diskStart' => 0xffff,
                'centralExtra' => $centralZip64Extra,
            ],
            [
                'name' => 'word/media/local-size.bin',
                'data' => $localData,
                'compressionMethod' => 0,
                'localVersionNeededToExtract' => 45,
                'localCompressedSize' => 0xffffffff,
                'localUncompressedSize' => 0xffffffff,
                'localExtra' => $localZip64Extra,
                'centralExtra' => '',
            ],
        ], 'zip64 extra field stream fixture');
        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-zip64-extra-package.zip',
                'comment' => 'zip64 extra field preflight fixture',
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
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('zip64 extra reviewer metadata', 12)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        $zip64ExtractionBlocked = false;
        try {
            ZipPackage::fromString($zipBytes);
        } catch (\RuntimeException) {
            $zip64ExtractionBlocked = true;
        }

        $t->same(true, $zip64ExtractionBlocked);

        foreach ($streams as $format => $bytes) {
            $inspection = ArchiveCompressionStream::inspectZip64ExtraFieldPolicy($bytes, $format, strlen($zipBytes));

            $t->same($zipBytes, ArchiveCompressionStream::decodeZipBytes($bytes, $format, strlen($zipBytes)));
            $t->same($format, $inspection['format']);
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same(2, $inspection['entryCount']);
            $t->same(2, $inspection['zip64ExtraFieldEntryCount']);
            $t->same(1, $inspection['centralZip64ExtraFieldEntryCount']);
            $t->same(1, $inspection['localZip64ExtraFieldEntryCount']);
            $t->same(2, $inspection['requiresZip64EntryCount']);
            $t->same(0, $inspection['mismatchedLocalHeaderEntryCount']);
            $t->same(false, $inspection['isSupportedByBoundedReader']);
            $t->same(['zip64-extra-field', 'zip64-size-or-offset-sentinel'], $inspection['issues']);
            $t->same(['word/document.xml', 'word/media/local-size.bin'], array_column($inspection['entries'], 'name'));
            $t->same(['word/document.xml', 'word/media/local-size.bin'], array_column($inspection['zip64Entries'], 'name'));
            $t->same(false, array_key_exists('package', $inspection));

            $centralEntry = $inspection['entries'][0];
            $t->same(true, $centralEntry['centralZip64ExtraFieldPresent']);
            $t->same(false, $centralEntry['localZip64ExtraFieldPresent']);
            $t->same([
                'uncompressedSize',
                'compressedSize',
                'localHeaderOffset',
                'diskStart',
            ], $centralEntry['centralZip64RequiredFields']);
            $t->same([
                'uncompressedSize' => strlen($documentXml),
                'compressedSize' => strlen($documentDeflated),
                'localHeaderOffset' => 0,
                'diskStart' => 0,
            ], $centralEntry['centralZip64Values']);
            $t->same(0xffffffff, $centralEntry['centralCompressedSize']);
            $t->same(0xffffffff, $centralEntry['centralUncompressedSize']);
            $t->same(0xffffffff, $centralEntry['centralLocalHeaderOffset']);
            $t->same(0xffff, $centralEntry['centralDiskStart']);
            $t->same('zip64-extra-field', $centralEntry['localHeaderOffsetSource']);
            $t->same(0, $centralEntry['localHeaderOffset']);
            $t->same(0, $centralEntry['centralZip64ExtraBytes']);
            $t->same(['zip64-extra-field', 'zip64-size-or-offset-sentinel'], $centralEntry['issues']);

            $localEntry = $inspection['entries'][1];
            $t->same(false, $localEntry['centralZip64ExtraFieldPresent']);
            $t->same(true, $localEntry['localZip64ExtraFieldPresent']);
            $t->same([], $localEntry['centralZip64RequiredFields']);
            $t->same([
                'uncompressedSize',
                'compressedSize',
            ], $localEntry['localZip64RequiredFields']);
            $t->same([
                'uncompressedSize' => strlen($localData),
                'compressedSize' => strlen($localData),
            ], $localEntry['localZip64Values']);
            $t->same(0xffffffff, $localEntry['localHeaderCompressedSize']);
            $t->same(0xffffffff, $localEntry['localHeaderUncompressedSize']);
            $t->same(0, $localEntry['localZip64ExtraBytes']);
            $t->same(['zip64-extra-field', 'zip64-size-or-offset-sentinel'], $localEntry['issues']);
        }

        $gzipInspection = ArchiveCompressionStream::inspectZip64ExtraFieldPolicy(
            $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectZip64ExtraFieldPolicy(
            $streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP],
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            strlen($zipBytes)
        );

        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-zip64-extra-package.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('zip64 extra field preflight fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same('zip64 extra reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
        $t->throws(
            \RuntimeException::class,
            static fn (): array => ArchiveCompressionStream::inspectZip64ExtraFieldPolicy(
                $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
                ArchiveCompressionStream::FORMAT_GZIP_TAR,
                strlen($zipBytes)
            )
        );
    },

    'preflights zip central directory provenance across compressed archive streams' => static function (TestRunner $t) use ($zipFixtureBytes, $zipWithCentralDirectorySignature): void {
        $documentXml = '<w:document><w:body><w:p>Central directory signature stream provenance</w:p></w:body></w:document>';
        $zipBytes = $zipWithCentralDirectorySignature($zipFixtureBytes([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'compressionMethod' => 8,
            ],
        ], 'central directory provenance fixture'), 'central-signature');
        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-central-directory-package.zip',
                'comment' => 'central directory provenance fixture',
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
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('central directory reviewer metadata', 8)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        foreach ($streams as $format => $bytes) {
            $inspection = ArchiveCompressionStream::inspectZipCentralDirectoryInventoryPolicy($bytes, $format, strlen($zipBytes));

            $t->same($zipBytes, ArchiveCompressionStream::decodeZipBytes($bytes, $format, strlen($zipBytes)));
            $t->same('zip-central-directory-inventory-policy', $inspection['type']);
            $t->same($format, $inspection['format']);
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same(2, $inspection['declaredEntryCount']);
            $t->same(2, $inspection['diskEntryCount']);
            $t->same(2, $inspection['scannedEntryCount']);
            $t->same(2, $inspection['entryCount']);
            $t->same($inspection['centralDirectorySize'], $inspection['scannedCentralDirectoryBytes']);
            $t->same(0, $inspection['centralDirectoryTailBytes']);
            $t->same(false, $inspection['hasEntryCountMismatch']);
            $t->same(true, $inspection['hasCentralDirectorySignature']);
            $t->same('between-central-directory-and-eocd', $inspection['centralDirectorySignature']['location']);
            $t->same(strlen('central-signature'), $inspection['centralDirectorySignature']['dataLength']);
            $t->same(strlen('central-signature'), $inspection['centralDirectorySignatureLength']);
            $t->same('not-performed-native-bounded-reader', $inspection['centralDirectorySignatureVerification']);
            $t->same(true, $inspection['isSupportedByBoundedReader']);
            $t->same([], $inspection['issues']);
            $t->same(['central-directory-signature-unverified'], $inspection['diagnostics']);
            $t->same('review-before-conversion', $inspection['handoffPolicy']);
            $t->same('central-directory-inventory-review', $inspection['extractionPolicy']);
            $t->same(['[Content_Types].xml', 'word/document.xml'], array_column($inspection['entries'], 'name'));
            $t->same([0, 1], array_column($inspection['entries'], 'centralDirectoryIndex'));
            $t->same(false, array_key_exists('package', $inspection));
        }

        $cleanZipBytes = $zipFixtureBytes([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Unsigned central directory</w:p></w:body></w:document>',
                'compressionMethod' => 8,
            ],
        ], 'unsigned central directory provenance fixture');
        $cleanInspection = ArchiveCompressionStream::inspectZipCentralDirectoryInventoryPolicy(
            $cleanZipBytes,
            ArchiveCompressionStream::FORMAT_ZIP,
            strlen($cleanZipBytes)
        );
        $gzipInspection = ArchiveCompressionStream::inspectZipCentralDirectoryInventoryPolicy(
            $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $zlibInspection = ArchiveCompressionStream::inspectZipCentralDirectoryInventoryPolicy(
            $streams[ArchiveCompressionStream::FORMAT_ZLIB_ZIP],
            ArchiveCompressionStream::FORMAT_ZLIB_ZIP,
            strlen($zipBytes)
        );
        $rawInspection = ArchiveCompressionStream::inspectZipCentralDirectoryInventoryPolicy(
            $streams[ArchiveCompressionStream::FORMAT_RAW_DEFLATE_ZIP],
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_ZIP,
            strlen($zipBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectZipCentralDirectoryInventoryPolicy(
            $streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP],
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            strlen($zipBytes)
        );

        $t->same(false, $cleanInspection['hasCentralDirectorySignature']);
        $t->same(null, $cleanInspection['centralDirectorySignature']);
        $t->same(0, $cleanInspection['centralDirectorySignatureLength']);
        $t->same('not-present', $cleanInspection['centralDirectorySignatureVerification']);
        $t->same([], $cleanInspection['diagnostics']);
        $t->same('within-thresholds', $cleanInspection['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $cleanInspection['extractionPolicy']);
        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-central-directory-package.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('central directory provenance fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('zlib-deflate', $zlibInspection['stream']['type']);
        $t->same('raw-deflate', $rawInspection['stream']['type']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same('central directory reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
        $t->throws(
            \RuntimeException::class,
            static fn (): array => ArchiveCompressionStream::inspectZipCentralDirectoryInventoryPolicy(
                $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
                ArchiveCompressionStream::FORMAT_GZIP_TAR,
                strlen($zipBytes)
            )
        );
    },

    'preflights split zip disk markers across archive streams without exposing package entries' => static function (TestRunner $t) use ($zipFixtureBytes): void {
        $documentXml = '<w:document><w:body><w:p>Split ZIP document.xml</w:p></w:body></w:document>';
        $mediaBytes = "split archive media placeholder\n";
        $zipBytes = $zipFixtureBytes([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'compressionMethod' => 8,
            ],
            [
                'name' => 'word/media/split.png',
                'data' => $mediaBytes,
                'compressionMethod' => 0,
                'diskStart' => 2,
            ],
        ], 'split zip review fixture', [
            'diskNumber' => 1,
            'centralDirectoryDisk' => 1,
            'diskEntryCount' => 2,
            'totalEntryCount' => 3,
        ]);
        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-split-package.zip',
                'comment' => 'zip split archive policy fixture',
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
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('split zip reviewer metadata', 12)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        foreach ($streams as $format => $bytes) {
            $inspection = ArchiveCompressionStream::inspectZipSplitArchivePolicy($bytes, $format, strlen($zipBytes));

            $t->same($format, $inspection['format']);
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same(3, $inspection['entryCount']);
            $t->same(1, $inspection['diskNumber']);
            $t->same(1, $inspection['centralDirectoryDisk']);
            $t->same(2, $inspection['diskEntryCount']);
            $t->same(3, $inspection['totalEntryCount']);
            $t->same(false, $inspection['isSingleDisk']);
            $t->same(true, $inspection['hasSplitArchiveMarkers']);
            $t->same(false, $inspection['isSupportedByBoundedReader']);
            $t->same(['split-archive-eocd', 'split-entry-disk-start'], $inspection['issues']);
            $t->same(1, $inspection['splitArchiveEntryCount']);
            $t->same('word/media/split.png', $inspection['splitArchiveEntries'][0]['name']);
            $t->same(2, $inspection['splitArchiveEntries'][0]['diskStart']);
            $t->same(['split-entry-disk-start'], $inspection['splitArchiveEntries'][0]['issues']);
            $t->same([
                '[Content_Types].xml',
                'word/document.xml',
                'word/media/split.png',
            ], array_column($inspection['entries'], 'name'));
            $t->same([0, 0, 2], array_column($inspection['entries'], 'diskStart'));
            $t->same([0, 1, 2], array_column($inspection['entries'], 'centralDirectoryIndex'));
        }

        $gzipInspection = ArchiveCompressionStream::inspectZipSplitArchivePolicy(
            $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectZipSplitArchivePolicy(
            $streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP],
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            strlen($zipBytes)
        );

        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-split-package.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('zip split archive policy fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same('split zip reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zipBytes));
        $t->throws(
            \RuntimeException::class,
            static fn (): array => ArchiveCompressionStream::inspectZipSplitArchivePolicy(
                $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
                ArchiveCompressionStream::FORMAT_GZIP_TAR,
                strlen($zipBytes)
            )
        );
    },

    'preflights zip archive extra data records across archive streams before package exposure' => static function (TestRunner $t) use ($zipFixtureBytes): void {
        $documentXml = '<w:document><w:body><w:p>Archive extra data record stream policy</w:p></w:body></w:document>';
        $archiveExtraData = 'archive-extra-stream-review-metadata';
        $archiveExtraRecord = "PK\x06\x08" . pack('V', strlen($archiveExtraData)) . $archiveExtraData;
        $baseZipBytes = $zipFixtureBytes([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'compressionMethod' => 8,
            ],
        ], 'archive extra data stream fixture');
        $eocdOffset = strrpos($baseZipBytes, "PK\x05\x06");
        if (!is_int($eocdOffset)) {
            throw new RuntimeException('Expected archive-extra ZIP fixture EOCD.');
        }
        $zipBytes = substr($baseZipBytes, 0, $eocdOffset)
            . $archiveExtraRecord
            . substr($baseZipBytes, $eocdOffset);

        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-archive-extra-package.zip',
                'comment' => 'zip archive extra data record policy fixture',
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
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('archive extra data reviewer metadata', 12)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        foreach ($streams as $format => $bytes) {
            $inspection = ArchiveCompressionStream::inspectZipArchiveExtraDataRecordPolicy($bytes, $format, strlen($zipBytes));

            $t->same($zipBytes, ArchiveCompressionStream::decodeZipBytes($bytes, $format, strlen($zipBytes)));
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same($format, $inspection['format']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same(2, $inspection['entryCount']);
            $t->same(1, $inspection['archiveExtraDataRecordCount']);
            $t->same(true, $inspection['hasArchiveExtraDataRecord']);
            $t->same(false, $inspection['isSupportedByBoundedReader']);
            $t->same(['[Content_Types].xml', 'word/document.xml'], array_column($inspection['entries'], 'name'));
            $t->same('between-central-directory-and-eocd', $inspection['archiveExtraDataRecords'][0]['location']);
            $t->same($archiveExtraData, substr(
                $inspection['zipBytes'],
                $inspection['archiveExtraDataRecords'][0]['dataOffset'],
                $inspection['archiveExtraDataRecords'][0]['dataLength']
            ));
            $t->same(strlen($archiveExtraData), $inspection['archiveExtraDataRecords'][0]['dataLength']);
            $t->same(['archive-extra-data-record'], $inspection['archiveExtraDataRecords'][0]['issues']);
            $t->same(false, array_key_exists('package', $inspection));
            $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zipBytes));
        }

        $gzipInspection = ArchiveCompressionStream::inspectZipArchiveExtraDataRecordPolicy(
            $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectZipArchiveExtraDataRecordPolicy(
            $streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP],
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            strlen($zipBytes)
        );

        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-archive-extra-package.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('zip archive extra data record policy fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same('archive extra data reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
        $t->throws(
            \RuntimeException::class,
            static fn (): array => ArchiveCompressionStream::inspectZipArchiveExtraDataRecordPolicy(
                $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
                ArchiveCompressionStream::FORMAT_GZIP_TAR,
                strlen($zipBytes)
            )
        );
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

    'preflights zip general purpose flags across archive streams before strict handoff' => static function (TestRunner $t) use ($zipFixtureBytes): void {
        $utf8 = 0x0800;
        $zipBytes = $zipFixtureBytes([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'flags' => $utf8,
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Descriptor and deflate option flags</w:p></w:body></w:document>',
                'flags' => $utf8 | 0x0006,
                'compressionMethod' => 8,
                'descriptor' => true,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "legacy CP437 metadata remains readable\n",
                'flags' => 0,
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/styles.xml',
                'data' => '<w:styles/>',
                'flags' => $utf8,
                'compressionMethod' => 8,
            ],
        ], 'general purpose flag review fixture');

        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-general-purpose-flags.zip',
                'comment' => 'ZIP general purpose flag preflight fixture',
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
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('general purpose flag reviewer metadata', 12)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        foreach ($streams as $format => $bytes) {
            $inspection = ArchiveCompressionStream::inspectZipGeneralPurposeFlagPolicy($bytes, $format, strlen($zipBytes));

            $t->same($zipBytes, ArchiveCompressionStream::decodeZipBytes($bytes, $format, strlen($zipBytes)));
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same($format, $inspection['format']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same(4, $inspection['entryCount']);
            $t->same(4, $inspection['supportedEntryCount']);
            $t->same(0, $inspection['unsupportedFlagEntryCount']);
            $t->same(3, $inspection['utf8NameEntryCount']);
            $t->same(1, $inspection['dataDescriptorEntryCount']);
            $t->same(1, $inspection['deflateOptionEntryCount']);
            $t->same(1, $inspection['strictReviewEntryCount']);
            $t->same([], $inspection['unsupportedEntries']);
            $t->same(['word/document.xml'], array_column($inspection['strictReviewEntries'], 'name'));
            $t->same(['[Content_Types].xml', 'word/document.xml', 'word/media/review.txt', 'word/styles.xml'], array_column($inspection['entries'], 'name'));
            $t->same(0x0800, $inspection['entries'][0]['generalPurposeFlags']);
            $t->same(['utf-8-names'], $inspection['entries'][0]['flagNames']);
            $t->same(false, $inspection['entries'][0]['requiresStrictReview']);
            $t->same(0x080e, $inspection['entries'][1]['generalPurposeFlags']);
            $t->same(['deflate-super-fast', 'data-descriptor', 'utf-8-names'], $inspection['entries'][1]['flagNames']);
            $t->same(0, $inspection['entries'][1]['unsupportedFlagBits']);
            $t->same(true, $inspection['entries'][1]['isSupportedByReader']);
            $t->same(true, $inspection['entries'][1]['usesUtf8Names']);
            $t->same(true, $inspection['entries'][1]['usesDataDescriptor']);
            $t->same(0x0006, $inspection['entries'][1]['deflateOptionFlags']);
            $t->same('deflate-super-fast', $inspection['entries'][1]['deflateOptionName']);
            $t->same(true, $inspection['entries'][1]['requiresStrictReview']);
            $t->same(['data-descriptor-entry', 'deflate-option-flags'], $inspection['entries'][1]['issues']);
            $t->same(false, $inspection['entries'][2]['usesUtf8Names']);
            $t->same(null, $inspection['entries'][2]['deflateOptionName']);
            $t->same([], $inspection['entries'][2]['issues']);
            $t->same(['utf-8-names'], $inspection['entries'][3]['flagNames']);
            $t->same(false, array_key_exists('package', $inspection));
        }

        $gzipInspection = ArchiveCompressionStream::inspectZipGeneralPurposeFlagPolicy(
            $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectZipGeneralPurposeFlagPolicy(
            $streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP],
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            strlen($zipBytes)
        );

        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-general-purpose-flags.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('ZIP general purpose flag preflight fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same('general purpose flag reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
        $t->throws(
            \RuntimeException::class,
            static fn (): array => ArchiveCompressionStream::inspectZipGeneralPurposeFlagPolicy(
                $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
                ArchiveCompressionStream::FORMAT_GZIP_TAR,
                strlen($zipBytes)
            )
        );
    },

    'preflights unsupported zip compression methods across archive streams without exposing package entries' => static function (TestRunner $t) use ($zipFixtureBytes): void {
        $utf8 = 0x0800;
        $zipBytes = $zipFixtureBytes([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'flags' => $utf8,
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Supported deflate part</w:p></w:body></w:document>',
                'flags' => $utf8,
                'compressionMethod' => 8,
            ],
            [
                'name' => 'word/media/bzip2-review.bin',
                'data' => 'BZIP2 method payload is intentionally not decoded by native package streams',
                'flags' => $utf8,
                'compressionMethod' => 12,
                'versionNeededToExtract' => 46,
            ],
            [
                'name' => 'word/media/local-method-mismatch.bin',
                'data' => 'Central deflate metadata with local LZMA method mismatch stays blocked',
                'flags' => $utf8,
                'compressionMethod' => 8,
                'localCompressionMethod' => 14,
            ],
        ], 'unsupported compression review fixture');

        $streams = [
            ArchiveCompressionStream::FORMAT_ZIP => $zipBytes,
            ArchiveCompressionStream::FORMAT_GZIP_ZIP => GzipStream::build($zipBytes, [
                'filename' => 'wordpress-unsupported-compression.zip',
                'comment' => 'unsupported ZIP method preflight fixture',
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
            ArchiveCompressionStream::FORMAT_LZ4_ZIP => Lz4Frame::skippableFrame('unsupported zip compression reviewer metadata', 13)
                . Lz4Frame::build($zipBytes, [
                    'contentSize' => true,
                    'contentChecksum' => true,
                ]),
        ];

        foreach ($streams as $format => $bytes) {
            $inspection = ArchiveCompressionStream::inspectZipCompressionMethodPolicy($bytes, $format, strlen($zipBytes));

            $t->same($zipBytes, ArchiveCompressionStream::decodeZipBytes($bytes, $format, strlen($zipBytes)));
            $t->same($zipBytes, $inspection['zipBytes']);
            $t->same($format, $inspection['format']);
            $t->same(strlen($zipBytes), $inspection['packageByteSize']);
            $t->same(4, $inspection['entryCount']);
            $t->same(2, $inspection['supportedEntryCount']);
            $t->same(2, $inspection['unsupportedCompressionMethodCount']);
            $t->same(1, $inspection['storedEntryCount']);
            $t->same(2, $inspection['deflatedEntryCount']);
            $t->same(1, $inspection['methodMismatchEntryCount']);
            $t->same(1, $inspection['unsupportedVersionEntryCount']);
            $t->same(true, $inspection['hasUnsupportedCompressionMethods']);
            $t->same(false, $inspection['isSupportedByBoundedReader']);
            $t->same('unsupported-compression-methods-blocked', $inspection['extractionPolicy']);
            $t->same([
                'unsupported-compression-methods',
                'local-header-compression-method-mismatch',
                'unsupported-version-needed',
            ], $inspection['issues']);
            $t->same([
                'word/media/bzip2-review.bin',
                'word/media/local-method-mismatch.bin',
            ], array_column($inspection['unsupportedEntries'], 'name'));
            $t->same('blocked', $inspection['unsupportedEntries'][0]['policy']);
            $t->same(12, $inspection['unsupportedEntries'][0]['compressionMethod']);
            $t->same('unsupported', $inspection['unsupportedEntries'][0]['compressionMethodName']);
            $t->same(46, $inspection['unsupportedEntries'][0]['versionNeededToExtract']);
            $t->same(['zip-unsupported-compression-method', 'zip-version-needed-exceeds-bounded-reader'], $inspection['unsupportedEntries'][0]['diagnostics']);
            $t->same(8, $inspection['unsupportedEntries'][1]['compressionMethod']);
            $t->same(14, $inspection['unsupportedEntries'][1]['localCompressionMethod']);
            $t->same(['zip-unsupported-compression-method', 'zip-local-header-compression-method-mismatch'], $inspection['unsupportedEntries'][1]['diagnostics']);
            $t->same('metadata', $inspection['entries'][1]['policy']);
            $t->same(false, array_key_exists('package', $inspection));
            $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectZipStream($bytes, $format, strlen($zipBytes)));
        }

        $gzipInspection = ArchiveCompressionStream::inspectZipCompressionMethodPolicy(
            $streams[ArchiveCompressionStream::FORMAT_GZIP_ZIP],
            ArchiveCompressionStream::FORMAT_GZIP_ZIP,
            strlen($zipBytes)
        );
        $lz4Inspection = ArchiveCompressionStream::inspectZipCompressionMethodPolicy(
            $streams[ArchiveCompressionStream::FORMAT_LZ4_ZIP],
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            strlen($zipBytes)
        );

        $t->same('gzip', $gzipInspection['stream']['type']);
        $t->same('wordpress-unsupported-compression.zip', $gzipInspection['stream']['members'][0]['filename']);
        $t->same('unsupported ZIP method preflight fixture', $gzipInspection['stream']['members'][0]['comment']);
        $t->same('lz4', $lz4Inspection['stream']['type']);
        $t->same(2, $lz4Inspection['stream']['frameCount']);
        $t->same('unsupported zip compression reviewer metadata', $lz4Inspection['stream']['frames'][0]['data']);
    },

    'preflights unsupported bzip2 and xz archive streams without exposing package bytes' => static function (TestRunner $t): void {
        $bzip2TarUpload = 'BZh9' . 'compressed tar payload bytes stay opaque to the native preflight';
        $xzZipUpload = "\xfd" . '7zXZ' . "\0" . "\0\x04" . "\0\0\0\0"
            . 'compressed zip payload bytes stay opaque to the native preflight';

        $bzip2Policy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            $bzip2TarUpload,
            'wordpress-review-packet.tar.bz2'
        );
        $xzPolicy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            $xzZipUpload,
            'wordpress-documents.zip.xz'
        );
        $xzDocxPolicy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            $xzZipUpload,
            'WORD-EXPORT.DOCX.XZ'
        );
        $nameOnlyPolicy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            '',
            'offline-review-packet.txz'
        );
        $bzip2EpubNameOnlyPolicy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            '',
            'BOOK-EXPORT.EPUB.BZ2'
        );
        $mismatchPolicy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            $bzip2TarUpload,
            'wrong-extension.tar.xz'
        );

        $t->same('unsupported-archive-compression-stream', $bzip2Policy['type']);
        $t->same('bzip2', $bzip2Policy['format']);
        $t->same('wordpress-review-packet.tar.bz2', $bzip2Policy['sourceName']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $bzip2Policy['candidateKind']);
        $t->same('bzip2-tar', $bzip2Policy['candidateFormat']);
        $t->same(strlen($bzip2TarUpload), $bzip2Policy['compressedSize']);
        $t->same(true, $bzip2Policy['signatureMatched']);
        $t->same('bzip2', $bzip2Policy['signatureName']);
        $t->same('425a6839', $bzip2Policy['signatureBytesHex']);
        $t->same(4, $bzip2Policy['streamHeaderSize']);
        $t->same(9, $bzip2Policy['blockSize100k']);
        $t->same(null, $bzip2Policy['streamFlagsHex']);
        $t->same('review-before-conversion', $bzip2Policy['handoffPolicy']);
        $t->same('unsupported-compression-stream-blocked', $bzip2Policy['extractionPolicy']);
        $t->same([
            'archive-compression-format-unsupported',
            'archive-compression-format-bzip2-not-decoded',
            'archive-external-decompressor-not-run',
            'archive-package-bytes-not-exposed',
        ], $bzip2Policy['diagnostics']);

        $t->same('xz', $xzPolicy['format']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $xzPolicy['candidateKind']);
        $t->same('xz-zip', $xzPolicy['candidateFormat']);
        $t->same(true, $xzPolicy['signatureMatched']);
        $t->same('xz', $xzPolicy['signatureName']);
        $t->same('fd377a585a00', $xzPolicy['signatureBytesHex']);
        $t->same(12, $xzPolicy['streamHeaderSize']);
        $t->same('0004', $xzPolicy['streamFlagsHex']);
        $t->same(null, $xzPolicy['blockSize100k']);
        $t->same([
            'archive-compression-format-unsupported',
            'archive-compression-format-xz-not-decoded',
            'archive-external-decompressor-not-run',
            'archive-package-bytes-not-exposed',
        ], $xzPolicy['diagnostics']);

        $t->same('xz', $xzDocxPolicy['format']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $xzDocxPolicy['candidateKind']);
        $t->same('xz-zip', $xzDocxPolicy['candidateFormat']);
        $t->same(true, $xzDocxPolicy['signatureMatched']);
        $t->same([], array_slice($xzDocxPolicy['diagnostics'], 4));

        $t->same('xz', $nameOnlyPolicy['format']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $nameOnlyPolicy['candidateKind']);
        $t->same('xz-tar', $nameOnlyPolicy['candidateFormat']);
        $t->same(false, $nameOnlyPolicy['signatureMatched']);
        $t->same([
            'archive-compression-format-unsupported',
            'archive-compression-format-xz-not-decoded',
            'archive-external-decompressor-not-run',
            'archive-package-bytes-not-exposed',
            'archive-compression-signature-unverified',
        ], $nameOnlyPolicy['diagnostics']);
        $t->same('bzip2', $bzip2EpubNameOnlyPolicy['format']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $bzip2EpubNameOnlyPolicy['candidateKind']);
        $t->same('bzip2-zip', $bzip2EpubNameOnlyPolicy['candidateFormat']);
        $t->same(false, $bzip2EpubNameOnlyPolicy['signatureMatched']);
        $t->same('archive-compression-signature-unverified', $bzip2EpubNameOnlyPolicy['diagnostics'][4] ?? null);
        $t->same('bzip2', $mismatchPolicy['format']);
        $t->same('bzip2-tar', $mismatchPolicy['candidateFormat']);
        $t->same('archive-compression-signature-source-name-mismatch', $mismatchPolicy['diagnostics'][4] ?? null);
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::detectPackageKindAuto($bzip2TarUpload));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectPackageStreamAuto($xzZipUpload));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy('not a compressed archive', 'notes.txt'));
    },

    'preflights unsupported zstandard archive streams without exposing package bytes' => static function (TestRunner $t): void {
        $zstandardTarUpload = "\x28\xb5\x2f\xfd" . "\x20"
            . 'compressed tar payload bytes stay opaque to the native preflight';
        $zstandardZipUpload = "\x28\xb5\x2f\xfd" . "\x04"
            . 'compressed zip payload bytes stay opaque to the native preflight';

        $zstandardPolicy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            $zstandardTarUpload,
            'wordpress-review-packet.tar.zst'
        );
        $zstandardZipPolicy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            $zstandardZipUpload,
            'wordpress-documents.zip.zstd'
        );
        $zstandardOdtNameOnlyPolicy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            '',
            'WORDPRESS-REVIEW.ODT.ZSTD'
        );
        $zstandardNameOnlyPolicy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            '',
            'offline-review-packet.tzst'
        );
        $mismatchPolicy = ArchiveCompressionStream::inspectUnsupportedCompressionStreamPolicy(
            $zstandardTarUpload,
            'wrong-extension.tar.xz'
        );

        $t->same('unsupported-archive-compression-stream', $zstandardPolicy['type']);
        $t->same('zstandard', $zstandardPolicy['format']);
        $t->same('wordpress-review-packet.tar.zst', $zstandardPolicy['sourceName']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $zstandardPolicy['candidateKind']);
        $t->same('zstandard-tar', $zstandardPolicy['candidateFormat']);
        $t->same(strlen($zstandardTarUpload), $zstandardPolicy['compressedSize']);
        $t->same(true, $zstandardPolicy['signatureMatched']);
        $t->same('zstandard', $zstandardPolicy['signatureName']);
        $t->same('28b52ffd', $zstandardPolicy['signatureBytesHex']);
        $t->same(5, $zstandardPolicy['streamHeaderSize']);
        $t->same('20', $zstandardPolicy['streamFlagsHex']);
        $t->same(null, $zstandardPolicy['blockSize100k']);
        $t->same('review-before-conversion', $zstandardPolicy['handoffPolicy']);
        $t->same('unsupported-compression-stream-blocked', $zstandardPolicy['extractionPolicy']);
        $t->same([
            'archive-compression-format-unsupported',
            'archive-compression-format-zstandard-not-decoded',
            'archive-external-decompressor-not-run',
            'archive-package-bytes-not-exposed',
        ], $zstandardPolicy['diagnostics']);

        $t->same('zstandard', $zstandardZipPolicy['format']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $zstandardZipPolicy['candidateKind']);
        $t->same('zstandard-zip', $zstandardZipPolicy['candidateFormat']);
        $t->same('04', $zstandardZipPolicy['streamFlagsHex']);
        $t->same('zstandard', $zstandardOdtNameOnlyPolicy['format']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $zstandardOdtNameOnlyPolicy['candidateKind']);
        $t->same('zstandard-zip', $zstandardOdtNameOnlyPolicy['candidateFormat']);
        $t->same(false, $zstandardOdtNameOnlyPolicy['signatureMatched']);
        $t->same('archive-compression-signature-unverified', $zstandardOdtNameOnlyPolicy['diagnostics'][4] ?? null);
        $t->same('zstandard', $zstandardNameOnlyPolicy['format']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $zstandardNameOnlyPolicy['candidateKind']);
        $t->same('zstandard-tar', $zstandardNameOnlyPolicy['candidateFormat']);
        $t->same(false, $zstandardNameOnlyPolicy['signatureMatched']);
        $t->same('archive-compression-signature-unverified', $zstandardNameOnlyPolicy['diagnostics'][4] ?? null);
        $t->same('zstandard', $mismatchPolicy['format']);
        $t->same('zstandard-tar', $mismatchPolicy['candidateFormat']);
        $t->same('archive-compression-signature-source-name-mismatch', $mismatchPolicy['diagnostics'][4] ?? null);
        $t->throws(\RuntimeException::class, static fn (): string => ArchiveCompressionStream::detectPackageKindAuto($zstandardTarUpload));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectPackageStreamAuto($zstandardZipUpload));
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

    'preflights source-name package stream mismatches before conversion handoff' => static function (TestRunner $t): void {
        $tarPacket = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"source-name-policy","format":"tar"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Source-name policy TAR packet\n\nReady for archive review.\n",
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
                'data' => '<w:document><w:body><w:p>Source-name policy ZIP package</w:p></w:body></w:document>',
            ],
        ]);
        $gzipTar = GzipStream::build($tarPacket->bytes(), [
            'filename' => 'actual-review-packet.tar',
            'comment' => 'actual gzip tar upload',
        ]);
        $gzipZip = GzipStream::build($zipPackage->bytes(), [
            'filename' => 'actual-office-package.zip',
            'comment' => 'actual gzip zip upload',
        ]);

        $matchingTar = ArchiveCompressionStream::inspectPackageSourceNamePolicyAuto(
            $gzipTar,
            'review-packet.tar.gz',
            strlen($tarPacket->bytes()),
            strlen($tarPacket->read('/packet/manifest.json')) + strlen($tarPacket->read('/packet/content.md'))
        );
        $matchingDocx = ArchiveCompressionStream::inspectPackageSourceNamePolicyAuto(
            $zipPackage->bytes(),
            'word-export.docx',
            strlen($zipPackage->bytes())
        );
        $matchingCompressedDocx = ArchiveCompressionStream::inspectPackageSourceNamePolicyAuto(
            $gzipZip,
            'WORD-EXPORT.DOCX.GZ',
            strlen($zipPackage->bytes())
        );
        $mismatchedDocx = ArchiveCompressionStream::inspectPackageSourceNamePolicyAuto(
            $gzipTar,
            'word-export.docx',
            strlen($tarPacket->bytes())
        );
        $mismatchedTar = ArchiveCompressionStream::inspectPackageSourceNamePolicyAuto(
            $gzipZip,
            'review-packet.tar.gz',
            strlen($zipPackage->bytes())
        );
        $unknownName = ArchiveCompressionStream::inspectPackageSourceNamePolicyAuto(
            $gzipZip,
            'opaque-upload.bin',
            strlen($zipPackage->bytes())
        );

        $t->same('archive-package-source-name-policy', $matchingTar['type']);
        $t->same('review-packet.tar.gz', $matchingTar['sourceName']);
        $t->same(true, $matchingTar['sourceNameCandidate']);
        $t->same('extension:gzip-tar', $matchingTar['sourceNameReason']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $matchingTar['expectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $matchingTar['expectedFormat']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $matchingTar['detectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $matchingTar['detectedFormat']);
        $t->same(strlen($gzipTar), $matchingTar['compressedSize']);
        $t->same(strlen($tarPacket->bytes()), $matchingTar['decodedPackageSize']);
        $t->same(2, $matchingTar['entryCount']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $matchingTar['entryNames']);
        $t->same('within-thresholds', $matchingTar['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $matchingTar['extractionPolicy']);
        $t->same([], $matchingTar['diagnostics']);
        $t->same('gzip', $matchingTar['stream']['type']);
        $t->same('actual-review-packet.tar', $matchingTar['stream']['members'][0]['filename']);
        $t->same(false, isset($matchingTar['tarBytes']));
        $t->same(false, isset($matchingTar['archive']));
        $t->same(false, isset($matchingTar['package']));

        $t->same('extension:zip-package', $matchingDocx['sourceNameReason']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $matchingDocx['expectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $matchingDocx['expectedFormat']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $matchingDocx['detectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $matchingDocx['detectedFormat']);
        $t->same('within-thresholds', $matchingDocx['handoffPolicy']);
        $t->same([], $matchingDocx['diagnostics']);

        $t->same('WORD-EXPORT.DOCX.GZ', $matchingCompressedDocx['sourceName']);
        $t->same(true, $matchingCompressedDocx['sourceNameCandidate']);
        $t->same('extension:gzip-zip-package', $matchingCompressedDocx['sourceNameReason']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $matchingCompressedDocx['expectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $matchingCompressedDocx['expectedFormat']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $matchingCompressedDocx['detectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $matchingCompressedDocx['detectedFormat']);
        $t->same('within-thresholds', $matchingCompressedDocx['handoffPolicy']);
        $t->same([], $matchingCompressedDocx['diagnostics']);
        $t->same('gzip', $matchingCompressedDocx['stream']['type']);
        $t->same('actual-office-package.zip', $matchingCompressedDocx['stream']['members'][0]['filename']);

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $mismatchedDocx['expectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $mismatchedDocx['expectedFormat']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $mismatchedDocx['detectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $mismatchedDocx['detectedFormat']);
        $t->same('review-before-conversion', $mismatchedDocx['handoffPolicy']);
        $t->same([
            'archive-source-name-package-kind-mismatch',
            'archive-source-name-compression-format-mismatch',
        ], $mismatchedDocx['diagnostics']);

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $mismatchedTar['expectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $mismatchedTar['expectedFormat']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $mismatchedTar['detectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $mismatchedTar['detectedFormat']);
        $t->same('review-before-conversion', $mismatchedTar['handoffPolicy']);
        $t->same([
            'archive-source-name-package-kind-mismatch',
            'archive-source-name-compression-format-mismatch',
        ], $mismatchedTar['diagnostics']);

        $t->same(false, $unknownName['sourceNameCandidate']);
        $t->same(null, $unknownName['expectedKind']);
        $t->same(null, $unknownName['expectedFormat']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $unknownName['detectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $unknownName['detectedFormat']);
        $t->same('review-before-conversion', $unknownName['handoffPolicy']);
        $t->same(['archive-source-name-package-type-unknown'], $unknownName['diagnostics']);
    },

    'preflights gzip member source-name mismatches before package conversion handoff' => static function (TestRunner $t): void {
        $tarPacket = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-member-source-name-policy","format":"tar"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Gzip member source-name policy TAR packet\n\nReady for archive review.\n",
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
                'data' => '<w:document><w:body><w:p>Gzip member source-name policy ZIP package</w:p></w:body></w:document>',
            ],
        ]);

        $matchingTar = GzipStream::build($tarPacket->bytes(), [
            'filename' => 'review-packet.tar',
            'comment' => 'matching gzip member tar name',
        ]);
        $matchingDocx = GzipStream::build($zipPackage->bytes(), [
            'filename' => 'WORDPRESS-REVIEW.DOCX',
            'comment' => 'matching gzip member docx name',
        ]);
        $mismatchedTarMember = GzipStream::build($tarPacket->bytes(), [
            'filename' => 'review-packet.docx',
            'comment' => 'mismatched member name for tar bytes',
        ]);
        $redundantlyCompressedMember = GzipStream::build($zipPackage->bytes(), [
            'filename' => 'wordpress-review-package.zip.gz',
            'comment' => 'member name still carries gzip suffix',
        ]);
        $missingFilename = GzipStream::build($zipPackage->bytes());

        $matchingTarPolicy = ArchiveCompressionStream::inspectGzipMemberSourceNamePolicyAuto(
            $matchingTar,
            strlen($tarPacket->bytes()),
            strlen($tarPacket->read('/packet/manifest.json')) + strlen($tarPacket->read('/packet/content.md'))
        );
        $matchingDocxPolicy = ArchiveCompressionStream::inspectGzipMemberSourceNamePolicyAuto(
            $matchingDocx,
            strlen($zipPackage->bytes())
        );
        $mismatchedTarPolicy = ArchiveCompressionStream::inspectGzipMemberSourceNamePolicyAuto(
            $mismatchedTarMember,
            strlen($tarPacket->bytes())
        );
        $redundantSuffixPolicy = ArchiveCompressionStream::inspectGzipMemberSourceNamePolicyAuto(
            $redundantlyCompressedMember,
            strlen($zipPackage->bytes())
        );
        $missingFilenamePolicy = ArchiveCompressionStream::inspectGzipMemberSourceNamePolicyAuto(
            $missingFilename,
            strlen($zipPackage->bytes())
        );

        $t->same('archive-gzip-member-source-name-policy', $matchingTarPolicy['type']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $matchingTarPolicy['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $matchingTarPolicy['format']);
        $t->same(ArchiveCompressionStream::FORMAT_TAR, $matchingTarPolicy['decodedFormat']);
        $t->same(strlen($matchingTar), $matchingTarPolicy['compressedSize']);
        $t->same(strlen($tarPacket->bytes()), $matchingTarPolicy['decodedPackageSize']);
        $t->same(2, $matchingTarPolicy['entryCount']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $matchingTarPolicy['entryNames']);
        $t->same(1, $matchingTarPolicy['memberCount']);
        $t->same(1, $matchingTarPolicy['memberFilenameCandidateCount']);
        $t->same(0, $matchingTarPolicy['missingMemberFilenameCount']);
        $t->same(0, $matchingTarPolicy['mismatchedMemberCount']);
        $t->same('within-thresholds', $matchingTarPolicy['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $matchingTarPolicy['extractionPolicy']);
        $t->same([], $matchingTarPolicy['diagnostics']);
        $t->same('gzip', $matchingTarPolicy['stream']['type']);
        $t->same('review-packet.tar', $matchingTarPolicy['stream']['members'][0]['filename']);
        $t->same('review-packet.tar', $matchingTarPolicy['members'][0]['filename']);
        $t->same(true, $matchingTarPolicy['members'][0]['memberFilenameCandidate']);
        $t->same('extension:tar', $matchingTarPolicy['members'][0]['memberNameReason']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $matchingTarPolicy['members'][0]['expectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_TAR, $matchingTarPolicy['members'][0]['expectedDecodedFormat']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $matchingTarPolicy['members'][0]['detectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_TAR, $matchingTarPolicy['members'][0]['detectedDecodedFormat']);
        $t->same('within-thresholds', $matchingTarPolicy['members'][0]['policy']);
        $t->same([], $matchingTarPolicy['members'][0]['diagnostics']);
        $t->same(false, isset($matchingTarPolicy['tarBytes']));
        $t->same(false, isset($matchingTarPolicy['archive']));
        $t->same(false, isset($matchingTarPolicy['zipBytes']));
        $t->same(false, isset($matchingTarPolicy['package']));

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $matchingDocxPolicy['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $matchingDocxPolicy['format']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $matchingDocxPolicy['decodedFormat']);
        $t->same('within-thresholds', $matchingDocxPolicy['handoffPolicy']);
        $t->same([], $matchingDocxPolicy['diagnostics']);
        $t->same('WORDPRESS-REVIEW.DOCX', $matchingDocxPolicy['members'][0]['filename']);
        $t->same('extension:zip-package', $matchingDocxPolicy['members'][0]['memberNameReason']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $matchingDocxPolicy['members'][0]['expectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $matchingDocxPolicy['members'][0]['expectedDecodedFormat']);

        $t->same('review-before-conversion', $mismatchedTarPolicy['handoffPolicy']);
        $t->same(1, $mismatchedTarPolicy['mismatchedMemberCount']);
        $t->same([
            'archive-gzip-member-source-name-package-kind-mismatch',
            'archive-gzip-member-source-name-compression-format-mismatch',
        ], $mismatchedTarPolicy['diagnostics']);
        $t->same([
            'archive-gzip-member-source-name-package-kind-mismatch',
            'archive-gzip-member-source-name-compression-format-mismatch',
        ], $mismatchedTarPolicy['members'][0]['diagnostics']);
        $t->same('review-before-conversion', $mismatchedTarPolicy['members'][0]['policy']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $mismatchedTarPolicy['members'][0]['expectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $mismatchedTarPolicy['members'][0]['expectedDecodedFormat']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $mismatchedTarPolicy['members'][0]['detectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_TAR, $mismatchedTarPolicy['members'][0]['detectedDecodedFormat']);

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $redundantSuffixPolicy['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $redundantSuffixPolicy['format']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $redundantSuffixPolicy['decodedFormat']);
        $t->same('review-before-conversion', $redundantSuffixPolicy['handoffPolicy']);
        $t->same(['archive-gzip-member-source-name-compression-format-mismatch'], $redundantSuffixPolicy['diagnostics']);
        $t->same('wordpress-review-package.zip.gz', $redundantSuffixPolicy['members'][0]['filename']);
        $t->same('extension:gzip-zip', $redundantSuffixPolicy['members'][0]['memberNameReason']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $redundantSuffixPolicy['members'][0]['expectedKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $redundantSuffixPolicy['members'][0]['expectedDecodedFormat']);
        $t->same(ArchiveCompressionStream::FORMAT_ZIP, $redundantSuffixPolicy['members'][0]['detectedDecodedFormat']);

        $t->same('review-before-conversion', $missingFilenamePolicy['handoffPolicy']);
        $t->same(0, $missingFilenamePolicy['memberFilenameCandidateCount']);
        $t->same(1, $missingFilenamePolicy['missingMemberFilenameCount']);
        $t->same(1, $missingFilenamePolicy['mismatchedMemberCount']);
        $t->same(['archive-gzip-member-source-name-missing'], $missingFilenamePolicy['diagnostics']);
        $t->same(false, $missingFilenamePolicy['members'][0]['memberFilenameCandidate']);
        $t->same(null, $missingFilenamePolicy['members'][0]['memberNameReason']);
        $t->same(null, $missingFilenamePolicy['members'][0]['expectedKind']);
        $t->same(null, $missingFilenamePolicy['members'][0]['expectedDecodedFormat']);
        $t->same(['archive-gzip-member-source-name-missing'], $missingFilenamePolicy['members'][0]['diagnostics']);

        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectGzipMemberSourceNamePolicyAuto(
            $zipPackage->bytes(),
            strlen($zipPackage->bytes())
        ));
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
        $t->same(0, $inspection['depthLimitReachedCount']);
        $t->same(0, $inspection['depthLimitedCandidateCount']);
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
        $t->same(false, $byPath['packet/nested/review.tar.gz']['depthLimitReached']);
        $t->same(0, $byPath['packet/nested/review.tar.gz']['depthLimitedCandidateCount']);
        $t->same([], $byPath['packet/nested/review.tar.gz']['depthLimitedCandidateNames']);

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
        $t->same(2, $depthOne['diagnosticCount']);
        $t->same(1, $depthOne['depthLimitReachedCount']);
        $t->same(1, $depthOne['depthLimitedCandidateCount']);
        $t->same([
            'packet/nested/review.tar.gz',
            'packet/nested/document.docx',
            'packet/nested/signature.bin',
            'packet/nested/broken.zip',
        ], array_map(static fn (array $entry): string => $entry['path'], $depthOne['entries']));
        $t->same(true, $depthOne['entries'][0]['depthLimitReached']);
        $t->same(['nested-package-depth-limit-reached'], $depthOne['entries'][0]['diagnostics']);
        $t->same(1, $depthOne['entries'][0]['depthLimitedCandidateCount']);
        $t->same(['packet/deeper/export.zip'], $depthOne['entries'][0]['depthLimitedCandidateNames']);
        $t->same([
            [
                'entryName' => 'packet/deeper/export.zip',
                'candidateReasons' => ['extension:zip'],
                'size' => strlen($innerZip->bytes()),
            ],
        ], $depthOne['entries'][0]['depthLimitedCandidates']);
        $t->same(false, $depthOne['entries'][1]['depthLimitReached']);
        $t->same(0, $depthOne['entries'][1]['depthLimitedCandidateCount']);
        $t->same([], $depthOne['entries'][1]['depthLimitedCandidateNames']);
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectNestedPackageStreamsAuto($upload, null, null, -1));
    },

    'preflights unsupported nested archive compression candidates without external decompressors' => static function (TestRunner $t): void {
        $xzTarBytes = "\xfd" . '7zXZ' . "\0\x00\x04" . 'xz-compressed-tar-placeholder';
        $zstdDocxBytes = "\x28\xb5\x2f\xfd\x00" . 'zstandard-compressed-docx-placeholder';
        $bzip2ZipBytes = 'BZh9' . 'bzip2-compressed-zip-placeholder';
        $innerTar = TarArchive::fromEntries([
            [
                'name' => 'packet/deeper/report.zip.bz2',
                'data' => $bzip2ZipBytes,
            ],
            [
                'name' => 'packet/deeper/readme.md',
                'data' => "Unsupported compression candidates stay metadata-only.\n",
            ],
        ]);
        $innerGzip = GzipStream::build($innerTar->bytes(), [
            'filename' => 'nested-unsupported.tar',
            'comment' => 'nested unsupported compression carrier',
        ]);
        $outerTar = TarArchive::fromEntries([
            [
                'name' => 'packet/content.md',
                'data' => "# Outer packet\n\nUnsupported nested package streams require review.\n",
            ],
            [
                'name' => 'packet/nested/review.tar.gz',
                'data' => $innerGzip,
            ],
            [
                'name' => 'packet/nested/source.tar.xz',
                'data' => $xzTarBytes,
            ],
            [
                'name' => 'packet/nested/export.docx.zst',
                'data' => $zstdDocxBytes,
            ],
        ]);
        $upload = GzipStream::build($outerTar->bytes(), [
            'filename' => 'wordpress-unsupported-nested.tar',
            'comment' => 'unsupported nested archive compression preflight',
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
        $bombPolicy = ArchiveCompressionStream::inspectNestedArchiveBombPolicyAuto(
            $upload,
            strlen($outerTar->bytes()),
            strlen($outerTar->bytes()),
            2
        );

        $byPath = [];
        foreach ($inspection['entries'] as $entry) {
            $byPath[$entry['path']] = $entry;
        }

        $bzip2Path = 'packet/nested/review.tar.gz!packet/deeper/report.zip.bz2';
        $t->same(4, $inspection['candidateCount']);
        $t->same(1, $inspection['packageCount']);
        $t->same(3, $inspection['unsupportedCompressionCount']);
        $t->same(3, $inspection['diagnosticCount']);
        $t->same([
            'packet/nested/review.tar.gz',
            $bzip2Path,
            'packet/nested/source.tar.xz',
            'packet/nested/export.docx.zst',
        ], array_column($inspection['entries'], 'path'));
        $t->same('package', $byPath['packet/nested/review.tar.gz']['status']);
        $t->same('unsupported-compression', $byPath[$bzip2Path]['status']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $byPath[$bzip2Path]['kind']);
        $t->same('bzip2', $byPath[$bzip2Path]['format']);
        $t->same('bzip2-zip', $byPath[$bzip2Path]['candidateFormat']);
        $t->same([
            'extension:unsupported-bzip2-zip',
            'signature:unsupported-bzip2',
        ], $byPath[$bzip2Path]['candidateReasons']);
        $t->same('unsupported-compression-stream-blocked', $byPath[$bzip2Path]['extractionPolicy']);
        $t->same([
            'archive-compression-format-unsupported',
            'archive-compression-format-bzip2-not-decoded',
            'archive-external-decompressor-not-run',
            'archive-package-bytes-not-exposed',
        ], $byPath[$bzip2Path]['diagnostics']);
        $t->same(9, $byPath[$bzip2Path]['blockSize100k']);
        $t->same('unsupported-compression', $byPath['packet/nested/source.tar.xz']['status']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $byPath['packet/nested/source.tar.xz']['kind']);
        $t->same('xz', $byPath['packet/nested/source.tar.xz']['format']);
        $t->same('xz-tar', $byPath['packet/nested/source.tar.xz']['candidateFormat']);
        $t->same(true, $byPath['packet/nested/source.tar.xz']['signatureMatched']);
        $t->same('unsupported-compression', $byPath['packet/nested/export.docx.zst']['status']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $byPath['packet/nested/export.docx.zst']['kind']);
        $t->same('zstandard', $byPath['packet/nested/export.docx.zst']['format']);
        $t->same('zstandard-zip', $byPath['packet/nested/export.docx.zst']['candidateFormat']);
        $t->same(false, isset($byPath[$bzip2Path]['data']));
        $t->same(false, isset($byPath[$bzip2Path]['package']));
        $t->same(false, isset($byPath['packet/nested/source.tar.xz']['tarBytes']));

        $t->same(3, $depthOne['candidateCount']);
        $t->same(1, $depthOne['packageCount']);
        $t->same(2, $depthOne['unsupportedCompressionCount']);
        $t->same(3, $depthOne['diagnosticCount']);
        $t->same(1, $depthOne['depthLimitReachedCount']);
        $t->same(1, $depthOne['depthLimitedCandidateCount']);
        $t->same([$bzip2Path], [$depthOne['entries'][0]['path'] . '!' . ($depthOne['entries'][0]['depthLimitedCandidateNames'][0] ?? '')]);
        $t->same([
            'extension:unsupported-bzip2-zip',
        ], $depthOne['entries'][0]['depthLimitedCandidates'][0]['candidateReasons']);

        $bombEntriesByPath = [];
        foreach ($bombPolicy['entries'] as $entry) {
            $bombEntriesByPath[$entry['path']] = $entry;
        }
        $t->same(4, $bombPolicy['nestedCandidateCount']);
        $t->same(1, $bombPolicy['nestedPackageCount']);
        $t->same(3, $bombPolicy['nestedUnsupportedCompressionCount']);
        $t->same(3, $bombPolicy['nestedDiagnosticCount']);
        $t->same(['nested-package-unsupported-compression'], $bombPolicy['diagnostics']);
        $t->same('unsupported-compression', $bombEntriesByPath[$bzip2Path]['status']);
        $t->same('unsupported-compression-stream-blocked', $bombEntriesByPath[$bzip2Path]['extractionPolicy']);
        $t->same(false, isset($bombEntriesByPath[$bzip2Path]['data']));
        $t->same(false, isset($bombEntriesByPath[$bzip2Path]['package']));
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

    'preflights nested archive expansion ratios across compressed package streams' => static function (TestRunner $t): void {
        $nestedLargeMarkdown = str_repeat('B', 4096);
        $nestedZip = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="md" ContentType="text/markdown"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $nestedLargeMarkdown,
            ],
        ]);
        $nestedZipBytes = $nestedZip->bytes();
        $nestedGzipZip = GzipStream::build($nestedZipBytes, [
            'filename' => 'nested-bomb.zip',
            'comment' => 'nested zip expansion policy',
            'compressionLevel' => 9,
        ]);
        $innerTar = TarArchive::fromEntries([
            [
                'name' => 'packet/nested/bomb.zip.gz',
                'data' => $nestedGzipZip,
            ],
            [
                'name' => 'packet/readme.md',
                'data' => "Nested archive carrier\n",
            ],
        ]);
        $innerTarGzip = GzipStream::build($innerTar->bytes(), [
            'filename' => 'nested-review.tar',
            'comment' => 'nested tar carrier',
            'compressionLevel' => 9,
        ]);
        $outerTar = TarArchive::fromEntries([
            [
                'name' => 'packet/content.md',
                'data' => "# Outer archive\n\nReady for nested archive review.\n",
            ],
            [
                'name' => 'packet/nested/review.tar.gz',
                'data' => $innerTarGzip,
            ],
        ]);
        $outerGzip = GzipStream::build($outerTar->bytes(), [
            'filename' => 'wordpress-nested-bomb-review.tar',
            'comment' => 'nested archive expansion preflight',
            'compressionLevel' => 9,
        ]);

        $policy = ArchiveCompressionStream::inspectNestedArchiveBombPolicyAuto(
            $outerGzip,
            strlen($outerTar->bytes()),
            strlen($outerTar->bytes()),
            2,
            10.0,
            10.0,
            10.0
        );

        $byPath = [];
        foreach ($policy['entries'] as $entry) {
            $byPath[$entry['path']] = $entry;
        }
        $nestedZipPath = 'packet/nested/review.tar.gz!packet/nested/bomb.zip.gz';

        $t->same('nested-archive-bomb-policy', $policy['type']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $policy['rootKind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $policy['rootFormat']);
        $t->same(2, $policy['maxDepth']);
        $t->same(2, $policy['nestedCandidateCount']);
        $t->same(2, $policy['nestedPackageCount']);
        $t->same(1, $policy['ratioDiagnosticCount']);
        $t->same(1, $policy['recordDiagnosticCount']);
        $t->same(0, $policy['depthLimitReachedCount']);
        $t->same(0, $policy['depthLimitedCandidateCount']);
        $t->same('review-before-conversion', $policy['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $policy['extractionPolicy']);
        $t->same(['nested-archive-expansion-ratio-exceeds-threshold'], $policy['diagnostics']);
        $t->same('within-thresholds', $policy['root']['policy']);
        $t->same([], $policy['root']['diagnostics']);
        $t->true($policy['root']['streamCompressionRatio'] < 10.0);
        $t->same([
            'packet/nested/review.tar.gz',
            $nestedZipPath,
        ], array_column($policy['entries'], 'path'));
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $byPath['packet/nested/review.tar.gz']['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_TAR, $byPath['packet/nested/review.tar.gz']['format']);
        $t->same('within-thresholds', $byPath['packet/nested/review.tar.gz']['policy']);
        $t->same([], $byPath['packet/nested/review.tar.gz']['diagnostics']);
        $t->same(['packet/nested/bomb.zip.gz', 'packet/readme.md'], $byPath['packet/nested/review.tar.gz']['entryNames']);
        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $byPath[$nestedZipPath]['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_GZIP_ZIP, $byPath[$nestedZipPath]['format']);
        $t->same(strlen($nestedGzipZip), $byPath[$nestedZipPath]['compressedSize']);
        $t->same(strlen($nestedZipBytes), $byPath[$nestedZipPath]['decodedPackageSize']);
        $t->same(strlen('<Types><Default Extension="md" ContentType="text/markdown"/></Types>') + strlen($nestedLargeMarkdown), $byPath[$nestedZipPath]['entryUncompressedSize']);
        $t->true($byPath[$nestedZipPath]['packageExpansionRatio'] > 10.0);
        $t->true($byPath[$nestedZipPath]['totalExpansionRatio'] > 10.0);
        $t->same([
            'archive-package-expansion-ratio-exceeds-threshold',
            'archive-total-expansion-ratio-exceeds-threshold',
        ], $byPath[$nestedZipPath]['diagnostics']);
        $t->same(2, $byPath[$nestedZipPath]['ratioDiagnosticCount']);
        $t->same('review-before-conversion', $byPath[$nestedZipPath]['policy']);
        $t->same(['[Content_Types].xml', 'packet/content.md'], $byPath[$nestedZipPath]['entryNames']);
        $t->same(false, isset($policy['root']['tarBytes']));
        $t->same(false, isset($policy['entries'][0]['tarBytes']));
        $t->same(false, isset($policy['entries'][1]['zipBytes']));
        $t->same(false, isset($policy['entries'][1]['package']));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectNestedArchiveBombPolicyAuto(
            $outerGzip,
            strlen($outerTar->bytes()),
            strlen($outerTar->bytes()),
            -1
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

    'inspects zlib preset dictionary package streams with supplied fixture dictionaries' => static function (TestRunner $t) use ($zlibDictionaryStream): void {
        $tarArchive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"zlib-dictionary-inspection","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# ZLIB dictionary inspection\n\nReady for WordPress archive review.\n",
                'modifiedAt' => 1780479092,
            ],
        ]);
        $tarBytes = $tarArchive->bytes();
        $tarDictionary = 'packet/content.md:inspection-dictionary';
        $tarDictionaryId = intval(hash('adler32', $tarDictionary), 16);
        $zlibTar = $zlibDictionaryStream($tarDictionary, $tarBytes);

        $tarInspection = ArchiveCompressionStream::inspectPackageStreamWithZlibDictionaries(
            $zlibTar,
            ArchiveCompressionStream::FORMAT_ZLIB_TAR,
            [$tarDictionaryId => $tarDictionary],
            strlen($tarBytes),
            512
        );
        $directTarInspection = ArchiveCompressionStream::inspectTarStreamWithZlibDictionaries(
            $zlibTar,
            ArchiveCompressionStream::FORMAT_ZLIB_TAR,
            [$tarDictionaryId => $tarDictionary],
            strlen($tarBytes),
            512
        );

        $zipPackage = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>ZLIB package inspection</w:p></w:body></w:document>',
            ],
        ]);
        $zipBytes = $zipPackage->bytes();
        $zipDictionary = '[Content_Types].xml:word/document.xml:inspection';
        $zipDictionaryId = intval(hash('adler32', $zipDictionary), 16);
        $zlibZip = $zlibDictionaryStream($zipDictionary, $zipBytes);
        $zipInspection = ArchiveCompressionStream::inspectPackageStreamWithZlibDictionaries(
            $zlibZip,
            ArchiveCompressionStream::FORMAT_ZLIB_ZIP,
            [$zipDictionaryId => $zipDictionary],
            strlen($zipBytes)
        );

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $tarInspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_ZLIB_TAR, $tarInspection['format']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $tarInspection['entryNames']);
        $t->same(2, $tarInspection['entryCount']);
        $t->same(2, $tarInspection['regularFileCount']);
        $t->same(strlen($tarBytes), $tarInspection['uncompressedSize']);
        $t->same("# ZLIB dictionary inspection\n\nReady for WordPress archive review.\n", $tarInspection['archive']->read('/packet/content.md'));
        $t->same(1780479092, $tarInspection['entryLayouts'][1]['modifiedAt']);
        $t->same($tarInspection['entryNames'], $directTarInspection['entryNames']);
        $t->same('zlib-deflate', $tarInspection['stream']['type']);
        $t->same(1, $tarInspection['stream']['memberCount']);
        $t->true($tarInspection['stream']['hasPresetDictionary']);
        $t->true($tarInspection['stream']['dictionarySupplied']);
        $t->same($tarDictionaryId, $tarInspection['stream']['presetDictionaryId']);
        $t->same(sprintf('%08x', $tarDictionaryId), $tarInspection['stream']['presetDictionaryIdHex']);
        $t->same(strlen($tarDictionary), $tarInspection['stream']['dictionarySize']);
        $t->same($tarDictionaryId, $tarInspection['stream']['dictionaryAdler32']);
        $t->same(sprintf('%08x', $tarDictionaryId), $tarInspection['stream']['dictionaryAdler32Hex']);
        $t->same(strlen($tarBytes), $tarInspection['stream']['uncompressedSize']);
        $t->same(strlen($zlibTar), $tarInspection['stream']['compressedSize']);
        $t->same(strlen($zlibTar), $tarInspection['stream']['consumedBytes']);
        $t->same(6, $tarInspection['stream']['headerSize']);
        $t->same(4, $tarInspection['stream']['trailerSize']);
        $t->same(strlen($zlibTar) - 10, $tarInspection['stream']['compressedPayloadSize']);
        $t->same(intval(hash('adler32', $tarBytes), 16), $tarInspection['stream']['adler32']);
        $t->same(sprintf('%08x', intval(hash('adler32', $tarBytes), 16)), $tarInspection['stream']['adler32Hex']);

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $zipInspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_ZLIB_ZIP, $zipInspection['format']);
        $t->same(['[Content_Types].xml', 'word/document.xml'], $zipInspection['entryNames']);
        $t->same(2, $zipInspection['entryCount']);
        $t->same(strlen($zipBytes), $zipInspection['packageByteSize']);
        $t->same('<w:document><w:body><w:p>ZLIB package inspection</w:p></w:body></w:document>', $zipInspection['package']->read('/word/document.xml'));
        $t->same('zlib-deflate', $zipInspection['stream']['type']);
        $t->same($zipDictionaryId, $zipInspection['stream']['presetDictionaryId']);
        $t->same(strlen($zipDictionary), $zipInspection['stream']['dictionarySize']);
        $t->same(strlen($zipBytes), $zipInspection['stream']['uncompressedSize']);
        $t->same(strlen($zlibZip), $zipInspection['stream']['compressedSize']);

        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectPackageStreamWithZlibDictionaries(
            $zlibTar,
            ArchiveCompressionStream::FORMAT_ZLIB_TAR,
            [],
            strlen($tarBytes),
            512
        ));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectPackageStreamWithZlibDictionaries(
            $zlibTar,
            ArchiveCompressionStream::FORMAT_RAW_DEFLATE_TAR,
            [$tarDictionaryId => $tarDictionary],
            strlen($tarBytes),
            512
        ));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectZipStreamWithZlibDictionaries(
            $zlibZip,
            ArchiveCompressionStream::FORMAT_ZLIB_TAR,
            [$zipDictionaryId => $zipDictionary],
            strlen($zipBytes)
        ));
    },

    'maps zlib preset dictionary decoded source segments for review packets' => static function (TestRunner $t) use ($zlibDictionaryStream): void {
        $manifestBytes = '{"source":"zlib-dictionary-source-segments","target":"wordpress"}';
        $contentBytes = "# ZLIB dictionary source segments\n\nReady for WordPress archive provenance review.\n";
        $tarArchive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => $manifestBytes,
            ],
            [
                'name' => 'packet/content.md',
                'data' => $contentBytes,
                'modifiedAt' => 1780479095,
            ],
        ]);
        $tarBytes = $tarArchive->bytes();
        $dictionary = 'packet/content.md:zlib-source-segment-dictionary';
        $dictionaryId = intval(hash('adler32', $dictionary), 16);
        $zlibTar = $zlibDictionaryStream($dictionary, $tarBytes);
        $inspection = ArchiveCompressionStream::inspectPackageStreamWithZlibDictionaries(
            $zlibTar,
            ArchiveCompressionStream::FORMAT_ZLIB_TAR,
            [$dictionaryId => $dictionary],
            strlen($tarBytes),
            strlen($manifestBytes) + strlen($contentBytes)
        );
        $manifestLayout = $inspection['entryLayouts'][0];
        $contentLayout = $inspection['entryLayouts'][1];

        $t->same(ArchiveCompressionStream::FORMAT_ZLIB_TAR, $inspection['format']);
        $t->same('zlib-deflate', $inspection['stream']['type']);
        $t->same(true, $inspection['stream']['hasPresetDictionary']);
        $t->same($dictionaryId, $inspection['stream']['presetDictionaryId']);
        $t->same(sprintf('%08x', $dictionaryId), $inspection['stream']['presetDictionaryIdHex']);
        $t->same('packet/content.md', $contentLayout['name']);
        $t->same($contentBytes, $inspection['archive']->read('/packet/content.md'));
        $t->same(1, $manifestLayout['decodedSourceSegmentCount']);
        $t->same(1, $contentLayout['decodedSourceSegmentCount']);
        $t->same('zlib-preset-dictionary-deflate', $contentLayout['decodedSourceSegments'][0]['sourceType']);
        $t->same('dictid:0x' . sprintf('%08x', $dictionaryId), $contentLayout['decodedSourceSegments'][0]['sourceLabel']);
        $t->same(0, $contentLayout['decodedSourceSegments'][0]['sourceIndex']);
        $t->same($contentLayout['headerOffset'], $contentLayout['decodedSourceSegments'][0]['sourceDecodedOffset']);
        $t->same($contentLayout['headerOffset'] + $contentLayout['recordSize'], $contentLayout['decodedSourceSegments'][0]['sourceDecodedEndOffset']);
        $t->same(0, $contentLayout['decodedSourceSegments'][0]['entryRecordOffset']);
        $t->same($contentLayout['recordSize'], $contentLayout['decodedSourceSegments'][0]['entryRecordEndOffset']);
        $t->same('zlib-preset-dictionary-deflate', $manifestLayout['decodedSourceSegments'][0]['sourceType']);
        $t->same('dictid:0x' . sprintf('%08x', $dictionaryId), $manifestLayout['decodedSourceSegments'][0]['sourceLabel']);
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

    'preflights lz4 skippable frame metadata without exposing payload bytes' => static function (TestRunner $t): void {
        $packet = 'packet/word/document.xml:skippable-review';
        $smallPayload = 'review-index:legacy';
        $largePayload = str_repeat('review-metadata-', 8);
        $stream = Lz4Frame::skippableFrame($smallPayload, 2)
            . Lz4Frame::build($packet, [
                'contentChecksum' => true,
                'contentSize' => true,
            ])
            . Lz4Frame::skippableFrame($largePayload, 15);

        $inspection = ArchiveCompressionStream::inspectLz4SkippableFramePolicy($stream, 32);
        $cleanInspection = ArchiveCompressionStream::inspectLz4SkippableFramePolicy($stream, strlen($largePayload));

        $t->same('lz4', $inspection['format']);
        $t->same('lz4-skippable-frame-policy', $inspection['type']);
        $t->same(strlen($stream), $inspection['compressedSize']);
        $t->same(3, $inspection['frameCount']);
        $t->same(1, $inspection['dataFrameCount']);
        $t->same(2, $inspection['skippableFrameCount']);
        $t->same(strlen($smallPayload) + strlen($largePayload), $inspection['skippablePayloadBytes']);
        $t->same(32, $inspection['maxSkippablePayloadBytes']);
        $t->same(1, $inspection['overLimitSkippableFrameCount']);
        $t->same(1, $inspection['firstOverLimitSkippableFrameIndex']);
        $t->same(strlen($largePayload), $inspection['largestSkippablePayloadSize']);
        $t->same('review-before-conversion', $inspection['handoffPolicy']);
        $t->same('lz4-skippable-frame-review', $inspection['extractionPolicy']);
        $t->same(['lz4-skippable-frame-byte-limit-exceeds-threshold'], $inspection['diagnostics']);
        $t->same(3, $inspection['stream']['frameCount']);
        $t->same(1, $inspection['stream']['dataFrameCount']);
        $t->same(2, $inspection['stream']['skippableFrameCount']);
        $t->same(0, $inspection['stream']['dictionaryFrameCount']);
        $t->same('metadata-only-no-extraction', $inspection['stream']['extractionPolicy']);

        $t->same('skippable', $inspection['stream']['frames'][0]['type']);
        $t->same(2, $inspection['stream']['frames'][0]['id']);
        $t->same(0, $inspection['stream']['frames'][0]['frameIndex']);
        $t->same(0, $inspection['stream']['frames'][0]['skippableFrameIndex']);
        $t->same(strlen($smallPayload), $inspection['stream']['frames'][0]['payloadSize']);
        $t->same(hash('sha256', $smallPayload), $inspection['stream']['frames'][0]['payloadSha256']);
        $t->same($smallPayload, $inspection['stream']['frames'][0]['payloadPreview']);
        $t->same('metadata-only-no-extraction', $inspection['stream']['frames'][0]['policy']);
        $t->same([], $inspection['stream']['frames'][0]['diagnostics']);
        $t->same(false, array_key_exists('data', $inspection['stream']['frames'][0]));

        $t->same('frame', $inspection['stream']['frames'][1]['type']);
        $t->same(1, $inspection['stream']['frames'][1]['frameIndex']);
        $t->same(0, $inspection['stream']['frames'][1]['dataFrameIndex']);
        $t->same(null, $inspection['stream']['frames'][1]['dictionaryId']);
        $t->same(strlen($packet), $inspection['stream']['frames'][1]['contentSize']);
        $t->same('decodable-without-dictionary', $inspection['stream']['frames'][1]['policy']);
        $t->same([], $inspection['stream']['frames'][1]['diagnostics']);
        $t->same(false, array_key_exists('data', $inspection['stream']['frames'][1]));

        $t->same('skippable', $inspection['stream']['frames'][2]['type']);
        $t->same(15, $inspection['stream']['frames'][2]['id']);
        $t->same(2, $inspection['stream']['frames'][2]['frameIndex']);
        $t->same(1, $inspection['stream']['frames'][2]['skippableFrameIndex']);
        $t->same(strlen($largePayload), $inspection['stream']['frames'][2]['payloadSize']);
        $t->same(hash('sha256', $largePayload), $inspection['stream']['frames'][2]['payloadSha256']);
        $t->same(substr($largePayload, 0, 64), $inspection['stream']['frames'][2]['payloadPreview']);
        $t->same('review-before-conversion', $inspection['stream']['frames'][2]['policy']);
        $t->same(['lz4-skippable-frame-byte-limit-over-limit'], $inspection['stream']['frames'][2]['diagnostics']);
        $t->same(false, array_key_exists('data', $inspection['stream']['frames'][2]));

        $t->same('within-thresholds', $cleanInspection['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $cleanInspection['extractionPolicy']);
        $t->same(0, $cleanInspection['overLimitSkippableFrameCount']);
        $t->same([], $cleanInspection['diagnostics']);
        $t->same($packet, Lz4Frame::decode($stream));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectLz4SkippableFramePolicy($stream, 0));
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

    'inspects lz4 dictionary package streams with supplied fixture dictionaries' => static function (TestRunner $t) use ($lz4DictionaryUncompressedFrame): void {
        $tarArchive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"lz4-dictionary-inspection","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# LZ4 dictionary inspection\n\nReady for WordPress archive review.\n",
                'modifiedAt' => 1780479093,
            ],
        ]);
        $tarBytes = $tarArchive->bytes();
        $tarDictionaryId = 0x0a0b0c0d;
        $tarDictionary = 'packet/content.md:lz4-package-inspection';
        $lz4Tar = Lz4Frame::skippableFrame('dictionary-id:0x0a0b0c0d', 10)
            . $lz4DictionaryUncompressedFrame($tarDictionaryId, $tarBytes);

        $tarInspection = ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
            $lz4Tar,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            [$tarDictionaryId => $tarDictionary],
            strlen($tarBytes),
            512
        );
        $directTarInspection = ArchiveCompressionStream::inspectTarStreamWithLz4Dictionaries(
            $lz4Tar,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            [$tarDictionaryId => $tarDictionary],
            strlen($tarBytes),
            512
        );

        $zipPackage = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>LZ4 package inspection</w:p></w:body></w:document>',
            ],
        ]);
        $zipBytes = $zipPackage->bytes();
        $zipDictionaryId = 0x01020304;
        $zipDictionary = '[Content_Types].xml:word/document.xml:lz4-inspection';
        $lz4Zip = Lz4Frame::skippableFrame('dictionary-id:0x01020304', 9)
            . $lz4DictionaryUncompressedFrame($zipDictionaryId, $zipBytes);
        $zipInspection = ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
            $lz4Zip,
            ArchiveCompressionStream::FORMAT_LZ4_ZIP,
            [$zipDictionaryId => $zipDictionary],
            strlen($zipBytes)
        );

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $tarInspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_LZ4_TAR, $tarInspection['format']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $tarInspection['entryNames']);
        $t->same(2, $tarInspection['entryCount']);
        $t->same(2, $tarInspection['regularFileCount']);
        $t->same(strlen($tarBytes), $tarInspection['uncompressedSize']);
        $t->same("# LZ4 dictionary inspection\n\nReady for WordPress archive review.\n", $tarInspection['archive']->read('/packet/content.md'));
        $t->same(1780479093, $tarInspection['entryLayouts'][1]['modifiedAt']);
        $t->same($tarInspection['entryNames'], $directTarInspection['entryNames']);
        $t->same('lz4', $tarInspection['stream']['type']);
        $t->same(2, $tarInspection['stream']['frameCount']);
        $t->same(1, $tarInspection['stream']['dataFrameCount']);
        $t->same(1, $tarInspection['stream']['skippableFrameCount']);
        $t->same(1, $tarInspection['stream']['dictionaryFrameCount']);
        $t->same(1, $tarInspection['stream']['blockCount']);
        $t->same(strlen($lz4Tar), $tarInspection['stream']['compressedSize']);
        $t->same(strlen($tarBytes), $tarInspection['stream']['uncompressedSize']);
        $t->same('dictionary-id:0x0a0b0c0d', $tarInspection['stream']['frames'][0]['data']);
        $t->same($tarDictionaryId, $tarInspection['stream']['frames'][1]['dictionaryId']);
        $t->true($tarInspection['stream']['frames'][1]['dictionarySupplied']);
        $t->same(strlen($tarDictionary), $tarInspection['stream']['frames'][1]['dictionarySize']);
        $t->same(strlen($tarBytes), $tarInspection['stream']['frames'][1]['contentSize']);
        $t->same(strlen($tarBytes), $tarInspection['stream']['frames'][1]['decodedDataSize']);
        $t->same(['uncompressed'], $tarInspection['stream']['frames'][1]['blockTypes']);
        $t->true($tarInspection['stream']['frames'][1]['contentChecksum']);

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_ZIP, $zipInspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_LZ4_ZIP, $zipInspection['format']);
        $t->same(['[Content_Types].xml', 'word/document.xml'], $zipInspection['entryNames']);
        $t->same(2, $zipInspection['entryCount']);
        $t->same(strlen($zipBytes), $zipInspection['packageByteSize']);
        $t->same('<w:document><w:body><w:p>LZ4 package inspection</w:p></w:body></w:document>', $zipInspection['package']->read('/word/document.xml'));
        $t->same('lz4', $zipInspection['stream']['type']);
        $t->same(1, $zipInspection['stream']['dictionaryFrameCount']);
        $t->same($zipDictionaryId, $zipInspection['stream']['frames'][1]['dictionaryId']);
        $t->same(strlen($zipDictionary), $zipInspection['stream']['frames'][1]['dictionarySize']);
        $t->same(strlen($zipBytes), $zipInspection['stream']['uncompressedSize']);
        $t->same(strlen($lz4Zip), $zipInspection['stream']['compressedSize']);

        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
            $lz4Tar,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            [],
            strlen($tarBytes),
            512
        ));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decodeWithDictionaries($lz4Tar, [
            $tarDictionaryId => '',
        ]));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
            $lz4Tar,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            [$tarDictionaryId => ''],
            strlen($tarBytes),
            512
        ));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
            $lz4Tar,
            ArchiveCompressionStream::FORMAT_GZIP_TAR,
            [$tarDictionaryId => $tarDictionary],
            strlen($tarBytes),
            512
        ));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectZipStreamWithLz4Dictionaries(
            $lz4Zip,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            [$zipDictionaryId => $zipDictionary],
            strlen($zipBytes)
        ));
    },

    'maps split lz4 dictionary package frame byte ranges for review packets' => static function (TestRunner $t) use ($lz4DictionaryUncompressedFrame): void {
        $tarArchive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"lz4-dictionary-split","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Split LZ4 dictionary package\n\nReady for WordPress archive review.\n",
                'modifiedAt' => 1780479094,
            ],
        ]);
        $tarBytes = $tarArchive->bytes();
        $splitOffset = 1536;
        $firstPayload = substr($tarBytes, 0, $splitOffset);
        $secondPayload = substr($tarBytes, $splitOffset);
        $firstDictionaryId = 0x10111213;
        $secondDictionaryId = 0x20212223;
        $firstDictionary = 'packet/content.md:first-split-lz4-dictionary';
        $secondDictionary = 'packet/content.md:second-split-lz4-dictionary';
        $skippable = Lz4Frame::skippableFrame('split-lz4-dictionary-tar:2', 14);
        $firstFrame = $lz4DictionaryUncompressedFrame($firstDictionaryId, $firstPayload);
        $secondFrame = $lz4DictionaryUncompressedFrame($secondDictionaryId, $secondPayload);
        $stream = $skippable . $firstFrame . $secondFrame;

        $inspection = ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
            $stream,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            [
                $firstDictionaryId => $firstDictionary,
                $secondDictionaryId => $secondDictionary,
            ],
            strlen($tarBytes)
        );
        $directFrames = Lz4Frame::framesWithDictionaries($stream, [
            $firstDictionaryId => $firstDictionary,
            $secondDictionaryId => $secondDictionary,
        ]);

        $t->same(ArchiveCompressionStream::PACKAGE_KIND_TAR, $inspection['kind']);
        $t->same(ArchiveCompressionStream::FORMAT_LZ4_TAR, $inspection['format']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $inspection['entryNames']);
        $t->same("# Split LZ4 dictionary package\n\nReady for WordPress archive review.\n", $inspection['archive']->read('/packet/content.md'));
        $t->same(1780479094, $inspection['entryLayouts'][1]['modifiedAt']);
        $t->same('lz4', $inspection['stream']['type']);
        $t->same(3, $inspection['stream']['frameCount']);
        $t->same(2, $inspection['stream']['dataFrameCount']);
        $t->same(1, $inspection['stream']['skippableFrameCount']);
        $t->same(2, $inspection['stream']['dictionaryFrameCount']);
        $t->same(2, $inspection['stream']['blockCount']);
        $t->same(strlen($stream), $inspection['stream']['compressedSize']);
        $t->same(strlen($tarBytes), $inspection['stream']['uncompressedSize']);
        $t->same(0, $inspection['stream']['frames'][0]['frameOffset'] ?? null);
        $t->same(strlen($skippable), $inspection['stream']['frames'][0]['nextFrameOffset'] ?? null);
        $t->same('split-lz4-dictionary-tar:2', $inspection['stream']['frames'][0]['data']);
        $t->same(strlen($skippable), $inspection['stream']['frames'][1]['frameOffset'] ?? null);
        $t->same(strlen($skippable) + strlen($firstFrame), $inspection['stream']['frames'][1]['nextFrameOffset'] ?? null);
        $t->same(0, $inspection['stream']['frames'][1]['decodedDataOffset'] ?? null);
        $t->same($splitOffset, $inspection['stream']['frames'][1]['decodedDataEndOffset'] ?? null);
        $t->same($splitOffset, $inspection['stream']['frames'][1]['decodedDataSize']);
        $t->same($firstDictionaryId, $inspection['stream']['frames'][1]['dictionaryId']);
        $t->same(strlen($firstDictionary), $inspection['stream']['frames'][1]['dictionarySize']);
        $t->same(['uncompressed'], $inspection['stream']['frames'][1]['blockTypes']);
        $t->same(strlen($skippable) + strlen($firstFrame), $inspection['stream']['frames'][2]['frameOffset'] ?? null);
        $t->same(strlen($stream), $inspection['stream']['frames'][2]['nextFrameOffset'] ?? null);
        $t->same($splitOffset, $inspection['stream']['frames'][2]['decodedDataOffset'] ?? null);
        $t->same(strlen($tarBytes), $inspection['stream']['frames'][2]['decodedDataEndOffset'] ?? null);
        $t->same(strlen($secondPayload), $inspection['stream']['frames'][2]['decodedDataSize']);
        $t->same($secondDictionaryId, $inspection['stream']['frames'][2]['dictionaryId']);
        $t->same(strlen($secondDictionary), $inspection['stream']['frames'][2]['dictionarySize']);
        $t->same(0, $directFrames[1]['decodedDataOffset'] ?? null);
        $t->same($splitOffset, $directFrames[1]['decodedDataEndOffset'] ?? null);
        $t->same($splitOffset, $directFrames[2]['decodedDataOffset'] ?? null);
        $t->same(strlen($tarBytes), $directFrames[2]['decodedDataEndOffset'] ?? null);
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries(
            $stream,
            ArchiveCompressionStream::FORMAT_LZ4_TAR,
            [$firstDictionaryId => $firstDictionary],
            strlen($tarBytes)
        ));
    },

    'inspects lz4 frame block size policy without extracting packages' => static function (TestRunner $t): void {
        $payload = '';
        for ($index = 0; $index < 320; $index++) {
            $payload .= hash('sha256', 'lz4-block-size-policy:' . $index, true);
        }

        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/content.bin',
                'data' => $payload,
            ],
        ]);
        $tarBytes = $archive->bytes();
        $skippable = Lz4Frame::skippableFrame('wordpress-lz4-block-size-review', 15);
        $lz4Stream = $skippable . Lz4Frame::build($tarBytes, [
            'blockMaxSize' => 262144,
            'blockChecksum' => true,
            'contentChecksum' => true,
        ]);

        $inspection = ArchiveCompressionStream::inspectLz4BlockSizePolicy($lz4Stream, 4096);

        $t->same('lz4', $inspection['format']);
        $t->same('lz4-block-size-policy', $inspection['type']);
        $t->same(strlen($lz4Stream), $inspection['compressedSize']);
        $t->same(2, $inspection['frameCount']);
        $t->same(1, $inspection['dataFrameCount']);
        $t->same(1, $inspection['skippableFrameCount']);
        $t->same(0, $inspection['dictionaryFrameCount']);
        $t->same(1, $inspection['blockCount']);
        $t->same(4096, $inspection['maxBlockPayloadBytes']);
        $t->same(1, $inspection['declaredOverLimitFrameCount']);
        $t->same(1, $inspection['payloadOverLimitBlockCount']);
        $t->same(0, $inspection['firstOverLimitDataFrameIndex']);
        $t->same(262144, $inspection['largestDeclaredBlockMaxSize']);
        $t->true($inspection['largestBlockPayloadSize'] > 4096);
        $t->same('review-before-conversion', $inspection['handoffPolicy']);
        $t->same('lz4-block-size-review', $inspection['extractionPolicy']);
        $t->same([
            'lz4-declared-block-max-size-exceeds-threshold',
            'lz4-block-payload-size-exceeds-threshold',
        ], $inspection['diagnostics']);
        $t->same('metadata-only-no-extraction', $inspection['stream']['extractionPolicy']);
        $t->same('skippable', $inspection['stream']['frames'][0]['type']);
        $t->same('metadata-only-no-extraction', $inspection['stream']['frames'][0]['policy']);
        $t->same('frame', $inspection['stream']['frames'][1]['type']);
        $t->same(0, $inspection['stream']['frames'][1]['dataFrameIndex']);
        $t->same(262144, $inspection['stream']['frames'][1]['blockMaxSize']);
        $t->same(true, $inspection['stream']['frames'][1]['declaredBlockMaxOverLimit']);
        $t->same(1, $inspection['stream']['frames'][1]['payloadOverLimitBlockCount']);
        $t->same(1, $inspection['stream']['frames'][1]['blockCount']);
        $t->same(1, count($inspection['stream']['frames'][1]['blocks']));
        $t->same(0, $inspection['stream']['frames'][1]['blocks'][0]['blockIndex']);
        $t->true($inspection['stream']['frames'][1]['blocks'][0]['payloadSize'] > 4096);
        $t->same(true, $inspection['stream']['frames'][1]['blocks'][0]['overLimit']);
        $t->same('review-before-conversion', $inspection['stream']['frames'][1]['blocks'][0]['policy']);
        $t->same(['lz4-block-payload-size-exceeds-threshold'], $inspection['stream']['frames'][1]['blocks'][0]['diagnostics']);
        $t->same([
            'lz4-declared-block-max-size-exceeds-threshold',
            'lz4-block-payload-size-exceeds-threshold',
        ], $inspection['stream']['frames'][1]['diagnostics']);
        $t->same($tarBytes, Lz4Frame::decode($lz4Stream));
        $t->same(false, isset($inspection['stream']['frames'][1]['data']));

        $smallStream = Lz4Frame::build('small package marker', [
            'blockMaxSize' => 65536,
            'contentChecksum' => true,
        ]);
        $smallInspection = ArchiveCompressionStream::inspectLz4BlockSizePolicy($smallStream, 65536);
        $t->same('within-thresholds', $smallInspection['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $smallInspection['extractionPolicy']);
        $t->same(0, $smallInspection['declaredOverLimitFrameCount']);
        $t->same(0, $smallInspection['payloadOverLimitBlockCount']);
        $t->same([], $smallInspection['diagnostics']);
        $t->same(false, $smallInspection['stream']['frames'][0]['declaredBlockMaxOverLimit']);

        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectLz4BlockSizePolicy($smallStream, 0));
    },

    'preflights lz4 declared content size mismatches before package handoff' => static function (TestRunner $t) use ($lz4HeaderChecksum): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"lz4-content-size","target":"wordpress"}',
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# LZ4 content-size review\n\nReady for WordPress archive review.\n",
            ],
        ]);
        $tarBytes = $archive->bytes();
        $valid = Lz4Frame::build($tarBytes, [
            'blockChecksum' => true,
            'contentChecksum' => true,
            'contentSize' => true,
        ]);
        $declaredSize = strlen($tarBytes) + 7;
        $mismatched = substr_replace($valid, pack('V2', $declaredSize, 0), 6, 8);
        $mismatched = substr_replace($mismatched, $lz4HeaderChecksum(substr($mismatched, 4, 10)), 14, 1);
        $noDeclaredSize = Lz4Frame::build('sidecar-less review packet', [
            'contentChecksum' => true,
            'contentSize' => false,
        ]);
        $skippablePayload = 'wordpress-lz4-content-size-review';
        $stream = Lz4Frame::skippableFrame($skippablePayload, 6) . $mismatched . $noDeclaredSize;

        $inspection = ArchiveCompressionStream::inspectLz4ContentSizePolicy($stream);
        $cleanInspection = ArchiveCompressionStream::inspectLz4ContentSizePolicy($valid, strlen($tarBytes));

        $t->same('lz4', $inspection['format']);
        $t->same('lz4-content-size-policy', $inspection['type']);
        $t->same(strlen($stream), $inspection['compressedSize']);
        $t->same(3, $inspection['frameCount']);
        $t->same(2, $inspection['dataFrameCount']);
        $t->same(1, $inspection['skippableFrameCount']);
        $t->same(1, $inspection['declaredContentSizeFrameCount']);
        $t->same(1, $inspection['missingContentSizeFrameCount']);
        $t->same(1, $inspection['mismatchedContentSizeFrameCount']);
        $t->same(1, $inspection['firstMismatchedFrameIndex']);
        $t->same(0, $inspection['firstMismatchedDataFrameIndex']);
        $t->same($declaredSize, $inspection['declaredContentSizeBytes']);
        $t->same(strlen($tarBytes) + strlen('sidecar-less review packet'), $inspection['decodedContentBytes']);
        $t->same('review-before-conversion', $inspection['handoffPolicy']);
        $t->same('lz4-content-size-review', $inspection['extractionPolicy']);
        $t->same(['lz4-content-size-mismatch'], $inspection['diagnostics']);
        $t->same('lz4-content-size-mismatch-blocked', $inspection['stream']['extractionPolicy']);

        $t->same('skippable', $inspection['stream']['frames'][0]['type']);
        $t->same(0, $inspection['stream']['frames'][0]['frameIndex']);
        $t->same(6, $inspection['stream']['frames'][0]['id']);
        $t->same(strlen($skippablePayload), $inspection['stream']['frames'][0]['payloadSize']);
        $t->same(hash('sha256', $skippablePayload), $inspection['stream']['frames'][0]['payloadSha256']);
        $t->same($skippablePayload, $inspection['stream']['frames'][0]['payloadPreview']);
        $t->same(false, array_key_exists('data', $inspection['stream']['frames'][0]));

        $t->same('frame', $inspection['stream']['frames'][1]['type']);
        $t->same(1, $inspection['stream']['frames'][1]['frameIndex']);
        $t->same(0, $inspection['stream']['frames'][1]['dataFrameIndex']);
        $t->same($declaredSize, $inspection['stream']['frames'][1]['contentSize']);
        $t->same(strlen($tarBytes), $inspection['stream']['frames'][1]['decodedDataSize']);
        $t->same(false, $inspection['stream']['frames'][1]['contentSizeMatches']);
        $t->same(7, $inspection['stream']['frames'][1]['contentSizeDelta']);
        $t->same(1, $inspection['stream']['frames'][1]['blockCount']);
        $t->same('review-before-conversion', $inspection['stream']['frames'][1]['policy']);
        $t->same(['lz4-content-size-mismatch'], $inspection['stream']['frames'][1]['diagnostics']);
        $t->same(false, array_key_exists('data', $inspection['stream']['frames'][1]));

        $t->same('frame', $inspection['stream']['frames'][2]['type']);
        $t->same(1, $inspection['stream']['frames'][2]['dataFrameIndex']);
        $t->same(null, $inspection['stream']['frames'][2]['contentSize']);
        $t->same(strlen('sidecar-less review packet'), $inspection['stream']['frames'][2]['decodedDataSize']);
        $t->same(null, $inspection['stream']['frames'][2]['contentSizeMatches']);
        $t->same(null, $inspection['stream']['frames'][2]['contentSizeDelta']);
        $t->same('metadata-only-no-extraction', $inspection['stream']['frames'][2]['policy']);
        $t->same([], $inspection['stream']['frames'][2]['diagnostics']);

        $t->same('within-thresholds', $cleanInspection['handoffPolicy']);
        $t->same('metadata-only-no-extraction', $cleanInspection['extractionPolicy']);
        $t->same(1, $cleanInspection['declaredContentSizeFrameCount']);
        $t->same(0, $cleanInspection['mismatchedContentSizeFrameCount']);
        $t->same(true, $cleanInspection['stream']['frames'][0]['contentSizeMatches']);
        $t->same($tarBytes, Lz4Frame::decode($valid));
        $t->throws(\RuntimeException::class, static fn (): string => Lz4Frame::decode($mismatched));
        $t->throws(\RuntimeException::class, static fn (): array => ArchiveCompressionStream::inspectLz4ContentSizePolicy(
            $valid,
            strlen($tarBytes) - 1
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
