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

$lz4HeaderChecksum = static fn (string $descriptor): string => chr((intval(hash('xxh32', $descriptor), 16) >> 8) & 0xff);

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

        $t->true(strlen($ustarName) > 100);
        $t->true(strlen($paxName) > 100);
        $t->same($ustarName, $roundTrip->entry($ustarName)->name);
        $t->same($paxName, $paxEntry->name);
        $t->same($paxName, $paxEntry->paxHeaders['path'] ?? null);
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
        $t->same($localDocument, $roundTrip->read('/packet/local-delete.xml'));
        $t->same(1780479074, $inherited->modifiedAt);
        $t->same('global-reviewer', $inherited->userName);
        $t->same('global archive review', $inherited->paxHeaders['comment'] ?? null);
        $t->same('BINARY', $inherited->paxHeaders['hdrcharset'] ?? null);
        $t->same($inheritedDocument, $roundTrip->read('/packet/inherited.xml'));
        $t->same(1780479074, $afterGlobalDelete->modifiedAt);
        $t->same('', $afterGlobalDelete->userName);
        $t->same(null, $afterGlobalDelete->paxHeaders['comment'] ?? null);
        $t->same(null, $afterGlobalDelete->paxHeaders['hdrcharset'] ?? null);
        $t->same(null, $afterGlobalDelete->paxHeaders['uname'] ?? null);
        $t->same($globalDeletionDocument, $roundTrip->read('/packet/global-delete.xml'));
        $t->same(['hdrcharset', 'org.wordpress.import.review'], $inspection['entryLayouts'][0]['paxHeaderKeys']);
        $t->same(['comment', 'hdrcharset', 'mtime', 'uname'], $inspection['entryLayouts'][1]['paxHeaderKeys']);
        $t->same(['mtime'], $inspection['entryLayouts'][2]['paxHeaderKeys']);
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

        $t->true(strlen($longDocumentName) > 100);
        $t->true(strlen($longDirectoryName) > 100);
        $t->same([$longDocumentName, $longDirectoryName], $roundTrip->names());
        $t->true($documentEntry->isRegularFile());
        $t->same(1780479025, $documentEntry->modifiedAt);
        $t->same('<w:document><w:p>GNU long name source</w:p></w:document>', $roundTrip->read('/' . $longDocumentName));
        $t->true($directoryEntry->isDirectory());
        $t->same(1780479027, $directoryEntry->modifiedAt);
        $t->same('', $roundTrip->read($longDirectoryName));
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

    'rejects tar pax linkpath metadata before package bytes are exposed' => static function (TestRunner $t) use ($rawTarHeader, $paxPayload): void {
        $paxLinkPath = $rawTarHeader('PaxHeaders/linkpath', 'x', $paxPayload([
            'path' => 'packet/linkpath-regular.xml',
            'linkpath' => 'packet/target.xml',
        ]), 0, false)
            . $rawTarHeader('placeholder.xml', '0', '<w:document/>', 0, false)
            . str_repeat("\0", 1024);

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($paxLinkPath));
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
        $zlibRoundTrip = TarArchive::fromString(DeflateStream::decode($zlib));
        $rawRoundTrip = TarArchive::fromString(DeflateStream::decode($raw, DeflateStream::FORMAT_RAW));

        $t->same(DeflateStream::FORMAT_ZLIB, $metadata['format']);
        $t->same(8, $metadata['compressionMethod']);
        $t->same(32768, $metadata['windowSize']);
        $t->same('maximum', $metadata['compressionLevelHint']);
        $t->same(strlen($archive->bytes()), $metadata['uncompressedSize']);
        $t->same(strlen($zlib) - 6, $metadata['compressedSize']);
        $t->same($archive->bytes(), $metadata['data']);
        $t->same('{"source":"deflate-tar","target":"wordpress"}', $zlibRoundTrip->read('/packet/manifest.json'));
        $t->same("# Deflate archive\n\nReady for import review.\n", $zlibRoundTrip->read('/packet/content.md'));
        $t->same($zlibRoundTrip->read('/packet/content.md'), $rawRoundTrip->read('packet/content.md'));
        $t->same($metadata['adler32'], intval(hash('adler32', $archive->bytes()), 16));
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
        $t->same(strlen($tarBytes), $zlibInspection['stream']['uncompressedSize']);
        $t->same(32768, $zlibInspection['stream']['windowSize']);
        $t->same('maximum', $zlibInspection['stream']['compressionLevelHint']);
        $t->same(intval(hash('adler32', $tarBytes), 16), $zlibInspection['stream']['adler32']);
        $t->same("# Deflate provenance\n\nReady for stream review.\n", $zlibInspection['archive']->read('/packet/content.md'));

        $t->same(ArchiveCompressionStream::FORMAT_RAW_DEFLATE_TAR, $rawInspection['format']);
        $t->same('raw-deflate', $rawInspection['stream']['type']);
        $t->same(1, $rawInspection['stream']['memberCount']);
        $t->same(strlen($raw), $rawInspection['stream']['compressedSize']);
        $t->same(strlen($raw), $rawInspection['stream']['compressedPayloadSize']);
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
