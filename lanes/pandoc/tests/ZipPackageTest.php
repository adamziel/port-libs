<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;
use PortLibs\Pandoc\ZipPackageEntry;
use PortLibs\Pandoc\GzipStream;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));

/**
 * @param list<array{
 *     name:string,
 *     data?:string,
 *     method?:int,
 *     flags?:int,
 *     descriptor?:bool,
 *     descriptorSignature?:bool,
 *     centralCrc?:int,
 *     descriptorCrc?:int,
 *     descriptorCompressedSize?:int,
 *     descriptorUncompressedSize?:int,
 *     localCrc?:int,
 *     localCompressedSize?:int,
 *     localUncompressedSize?:int,
 *     modifiedTime?:int,
 *     modifiedDate?:int,
 *     localModifiedTime?:int,
 *     localModifiedDate?:int,
 *     localExtra?:string,
 *     centralExtra?:string,
 *     localName?:string,
 *     diskStart?:int,
 *     externalAttributes?:int,
 *     comment?:string
 * }> $entries
 */
$buildZipPackage = static function (array $entries, string $comment = '') use ($crc32): string {
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $localName = $entry['localName'] ?? $name;
        $data = $entry['data'] ?? '';
        $method = $entry['method'] ?? 0;
        $flags = $entry['flags'] ?? 0x0800;
        $descriptor = (bool) ($entry['descriptor'] ?? false);
        if ($descriptor) {
            $flags |= 0x0008;
        }

        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $actualCrc = $crc32($data);
        $centralCrc = $entry['centralCrc'] ?? $actualCrc;
        $offset = strlen($body);
        $modifiedTime = $entry['modifiedTime'] ?? 0;
        $modifiedDate = $entry['modifiedDate'] ?? 0;
        $localModifiedTime = $entry['localModifiedTime'] ?? $modifiedTime;
        $localModifiedDate = $entry['localModifiedDate'] ?? $modifiedDate;
        $localExtra = $entry['localExtra'] ?? '';
        $centralExtra = $entry['centralExtra'] ?? $localExtra;
        $localCrc = $entry['localCrc'] ?? ($descriptor ? 0 : $actualCrc);
        $localCompressedSize = $entry['localCompressedSize'] ?? ($descriptor ? 0 : $compressedSize);
        $localUncompressedSize = $entry['localUncompressedSize'] ?? ($descriptor ? 0 : $uncompressedSize);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            $localModifiedTime,
            $localModifiedDate,
            $localCrc,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($localName),
            strlen($localExtra)
        );
        $body .= $localName . $localExtra . $compressed;
        if ($descriptor) {
            if ($entry['descriptorSignature'] ?? true) {
                $body .= "PK\x07\x08";
            }
            $body .= pack(
                'VVV',
                $entry['descriptorCrc'] ?? $actualCrc,
                $entry['descriptorCompressedSize'] ?? $compressedSize,
                $entry['descriptorUncompressedSize'] ?? $uncompressedSize
            );
        }

        $entryComment = $entry['comment'] ?? '';
        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            $modifiedTime,
            $modifiedDate,
            $centralCrc,
            $compressedSize,
            $uncompressedSize,
            strlen($name),
            strlen($centralExtra),
            strlen($entryComment),
            $entry['diskStart'] ?? 0,
            0,
            $entry['externalAttributes'] ?? 0,
            $offset
        );
        $central .= $name . $centralExtra . $entryComment;
    }

    $centralOffset = strlen($body);
    $centralSize = strlen($central);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), $centralSize, $centralOffset, strlen($comment))
        . $comment;
};

return [
    'reads current zip package central directory and stored deflated parts' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $zip = $buildZipPackage([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'method' => 0,
            ],
            [
                'name' => '_rels/.rels',
                'data' => '<Relationships><Relationship Target="word/document.xml"/></Relationships>',
                'method' => 8,
                'comment' => 'root rels',
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Imported post</w:p></w:body></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ], 'package comment');

        $package = ZipPackage::fromString($zip);
        $entries = $package->entries();

        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
            'word/media/',
        ], $package->names());
        $t->same(4, count($entries));
        $t->true($entries[0] instanceof ZipPackageEntry);
        $t->same('[Content_Types].xml', $entries[0]->name);
        $t->same(0, $entries[0]->compressionMethod);
        $t->same($crc32('<Types><Default Extension="xml" ContentType="application/xml"/></Types>'), $entries[0]->crc32);
        $t->same(sprintf('%08x', $entries[0]->crc32), $entries[0]->crc32Hex());
        $t->same('_rels/.rels', $entries[1]->name);
        $t->same(8, $entries[1]->compressionMethod);
        $t->same('root rels', $entries[1]->comment);
        $t->true($entries[3]->isDirectory());
        $t->true($package->has('/word/document.xml'));
        $t->same('<Types><Default Extension="xml" ContentType="application/xml"/></Types>', $package->read('[Content_Types].xml'));
        $t->same('<Relationships><Relationship Target="word/document.xml"/></Relationships>', $package->read('_rels/.rels'));
        $t->same('<w:document><w:body><w:p>Imported post</w:p></w:body></w:document>', $package->read('/word/document.xml'));
        $t->same('', $package->read('word/media/'));
        $t->true($package->centralDirectoryOffset() > 0);
        $t->same('package comment', $package->packageComment());
        $t->same($zip, $package->bytes());
    },

    'reads package entries whose local header uses a data descriptor' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => str_repeat('<w:comment>review</w:comment>', 8),
                'method' => 8,
                'descriptor' => true,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $entry = $package->entry('word/comments.xml');

        $t->same(8, $entry->compressionMethod);
        $t->same(0x0808, $entry->generalPurposeFlags);
        $t->same(str_repeat('<w:comment>review</w:comment>', 8), $package->read('word/comments.xml'));
    },

    'reads data descriptors with and without optional signatures' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>descriptor signed</w:p></w:document>',
                'method' => 8,
                'descriptor' => true,
            ],
            [
                'name' => 'word/footnotes.xml',
                'data' => '<w:footnotes><w:footnote>descriptor unsigned</w:footnote></w:footnotes>',
                'method' => 0,
                'descriptor' => true,
                'descriptorSignature' => false,
            ],
        ]);

        $package = ZipPackage::fromString($zip);

        $t->same('<w:document><w:p>descriptor signed</w:p></w:document>', $package->read('word/document.xml'));
        $t->same('<w:footnotes><w:footnote>descriptor unsigned</w:footnote></w:footnotes>', $package->read('word/footnotes.xml'));
        $t->same(8, $package->entry('word/document.xml')->compressionMethod);
        $t->same(0, $package->entry('word/footnotes.xml')->compressionMethod);
    },

    'builds current zip package bytes for generated pandoc containers' => static function (TestRunner $t): void {
        $parts = [
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
                'comment' => 'content types',
            ],
            [
                'name' => '_rels/.rels',
                'data' => '<Relationships><Relationship Target="word/document.xml"/></Relationships>',
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Generated WordPress packet</w:p></w:body></w:document>',
                'comment' => 'office document',
            ],
            [
                'name' => 'word/media/',
            ],
        ];

        $package = ZipPackage::fromParts($parts, 'wordpress package writer');
        $roundTrip = ZipPackage::fromString($package->bytes());

        $t->same($package->bytes(), ZipPackage::build($parts, 'wordpress package writer'));
        $t->contains("PK\x03\x04", $package->bytes());
        $t->contains("PK\x01\x02", $package->bytes());
        $t->same('wordpress package writer', $roundTrip->packageComment());
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
            'word/media/',
        ], $roundTrip->names());
        $t->same(0, $roundTrip->entry('[Content_Types].xml')->compressionMethod);
        $t->same(8, $roundTrip->entry('_rels/.rels')->compressionMethod);
        $t->same(8, $roundTrip->entry('word/document.xml')->compressionMethod);
        $t->same(0, $roundTrip->entry('word/media/')->compressionMethod);
        $t->same(0x10, $roundTrip->entry('word/media/')->externalFileAttributes);
        $t->same('content types', $roundTrip->entry('[Content_Types].xml')->comment);
        $t->same('office document', $roundTrip->entry('word/document.xml')->comment);
        $t->same('<Types><Default Extension="xml" ContentType="application/xml"/></Types>', $roundTrip->read('/[Content_Types].xml'));
        $t->same('<Relationships><Relationship Target="word/document.xml"/></Relationships>', $roundTrip->read('/_rels/.rels'));
        $t->same('<w:document><w:body><w:p>Generated WordPress packet</w:p></w:body></w:document>', $roundTrip->read('word/document.xml'));
        $t->same('', $roundTrip->read('word/media/'));
        $t->true($roundTrip->centralDirectoryOffset() > 0);
    },

    'preserves zip entry modification metadata and external attributes' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>metadata</w:p></w:document>',
                'method' => 8,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'externalAttributes' => 0x81a40000,
            ],
        ], 'metadata package');

        $package = ZipPackage::fromString($zip);
        $entry = $package->entry('/word/document.xml');

        $t->same(19400, $entry->modifiedDosTime());
        $t->same(23747, $entry->modifiedDosDate());
        $t->same(1780479016, $entry->lastModifiedTimestamp());
        $t->same(0x81a40000, $entry->externalFileAttributes);
        $t->same('metadata package', $package->packageComment());
        $t->same('<w:document><w:p>metadata</w:p></w:document>', $package->read('word/document.xml'));
    },

    'writes zip entry modification metadata for generated package parts' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>Generated metadata packet</w:p></w:document>',
                'modifiedAt' => 1780479016,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/',
                'modifiedDosTime' => 19400,
                'modifiedDosDate' => 23747,
            ],
        ]);

        $roundTrip = ZipPackage::fromString($package->bytes());
        $document = $roundTrip->entry('word/document.xml');
        $mediaDirectory = $roundTrip->entry('word/media/');

        $t->same(19400, $document->modifiedDosTime());
        $t->same(23747, $document->modifiedDosDate());
        $t->same(1780479016, $document->lastModifiedTimestamp());
        $t->same(0x81a40000, $document->externalFileAttributes);
        $t->same(19400, $mediaDirectory->modifiedDosTime());
        $t->same(23747, $mediaDirectory->modifiedDosDate());
        $t->same(1780479016, $mediaDirectory->lastModifiedTimestamp());
        $t->same(0x10, $mediaDirectory->externalFileAttributes);
    },

    'reads zip extra fields and uses extended timestamp metadata' => static function (TestRunner $t) use ($buildZipPackage): void {
        $extendedTimestamp = 1780479017;
        $extendedTimestampExtra = pack('vvCV', 0x5455, 5, 0x01, $extendedTimestamp);
        $vendorExtra = pack('vva*', 0xcafe, 6, 'review');

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>extra fields</w:p></w:document>',
                'method' => 8,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'localExtra' => $extendedTimestampExtra . $vendorExtra,
                'centralExtra' => $extendedTimestampExtra . $vendorExtra,
            ],
        ]));

        $entry = $package->entry('word/document.xml');

        $t->same($extendedTimestamp, $entry->extendedLastModifiedTimestamp());
        $t->same($extendedTimestamp, $entry->lastModifiedTimestamp());
        $t->same($extendedTimestampExtra . $vendorExtra, $entry->centralExtraFieldData);
        $t->same("\x01" . pack('V', $extendedTimestamp), $entry->centralExtraField(0x5455));
        $t->same('review', $entry->centralExtraField(0xcafe));
        $t->same(null, $entry->centralExtraField(0x000a));
        $t->same([
            ['id' => 0x5455, 'data' => "\x01" . pack('V', $extendedTimestamp)],
            ['id' => 0xcafe, 'data' => 'review'],
        ], $entry->centralExtraFields());
        $t->same('<w:document><w:p>extra fields</w:p></w:document>', $package->read('/word/document.xml'));
    },

    'writes extended timestamp extra fields for generated package parts' => static function (TestRunner $t): void {
        $modifiedAt = 1780479017;
        $package = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>exact modified timestamp</w:p></w:document>',
                'modifiedAt' => $modifiedAt,
            ],
        ]);

        $roundTrip = ZipPackage::fromString($package->bytes());
        $entry = $roundTrip->entry('word/document.xml');

        $t->same(19400, $entry->modifiedDosTime());
        $t->same(23747, $entry->modifiedDosDate());
        $t->same($modifiedAt, $entry->extendedLastModifiedTimestamp());
        $t->same($modifiedAt, $entry->lastModifiedTimestamp());
        $t->same("\x01" . pack('V', $modifiedAt), $entry->centralExtraField(0x5455));
        $t->contains(pack('vvCV', 0x5455, 5, 0x01, $modifiedAt), $package->bytes());
        $t->same('<w:document><w:p>exact modified timestamp</w:p></w:document>', $roundTrip->read('word/document.xml'));
    },

    'rejects malformed central and local zip extra fields' => static function (TestRunner $t) use ($buildZipPackage): void {
        $truncatedCentralExtra = pack('vvC', 0x5455, 5, 0x01);
        $truncatedLocalExtra = pack('vvC', 0x5455, 5, 0x01);
        $validCentralExtra = pack('vvCV', 0x5455, 5, 0x01, 1780479017);
        $localExtraMismatch = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document/>',
                'method' => 0,
                'centralExtra' => $validCentralExtra,
                'localExtra' => $truncatedLocalExtra,
            ],
        ]));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document/>',
                'method' => 0,
                'centralExtra' => $truncatedCentralExtra,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): string => $localExtraMismatch->read('word/document.xml'));
    },

    'rejects invalid generated zip package parts before writing' => static function (TestRunner $t): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'first'],
            ['name' => 'word/document.xml', 'data' => 'second'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/../document.xml', 'data' => 'traversal'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'compressionMethod' => 12],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/media/', 'data' => 'not a directory entry'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/media/', 'compressionMethod' => 8],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            'not a part array',
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => []],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'comment' => str_repeat('x', 0x10000)],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok'],
        ], str_repeat('x', 0x10000)));
    },

    'rejects invalid generated zip entry metadata before writing' => static function (TestRunner $t): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'modifiedAt' => '2026-06-03'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'modifiedAt' => 1780479016, 'modifiedDosTime' => 19400, 'modifiedDosDate' => 23747],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'modifiedDosTime' => 19400],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'modifiedDosTime' => 0x10000, 'modifiedDosDate' => 23747],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'modifiedAt' => 315532799],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'externalAttributes' => -1],
        ]));
    },

    'rejects unsafe package part names before exposing entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => '/word/document.xml', 'data' => 'absolute'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/../document.xml', 'data' => 'traversal'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word\\document.xml', 'data' => 'backslash'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): bool => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/document.xml', 'data' => 'ok'],
        ]))->has('../word/document.xml'));
    },

    'rejects duplicate encrypted and split zip package entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/document.xml', 'data' => 'first'],
            ['name' => 'word/document.xml', 'data' => 'second'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/encrypted.xml', 'data' => 'secret', 'flags' => 0x0801],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/split.xml', 'data' => 'split', 'diskStart' => 1],
        ])));
    },

    'verifies crc and local file header names before returning part bytes' => static function (TestRunner $t) use ($buildZipPackage): void {
        $crcMismatch = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document>changed</w:document>',
                'method' => 8,
                'centralCrc' => 0,
            ],
        ]));
        $localNameMismatch = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/other.xml',
                'data' => '<w:document/>',
                'method' => 0,
            ],
        ]));

        $t->throws(\RuntimeException::class, static fn (): string => $crcMismatch->read('word/document.xml'));
        $t->throws(\RuntimeException::class, static fn (): string => $localNameMismatch->read('word/document.xml'));
    },

    'rejects local header and data descriptor integrity mismatches' => static function (TestRunner $t) use ($buildZipPackage): void {
        $localCrcMismatch = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document>local crc mismatch</w:document>',
                'method' => 8,
                'localCrc' => 0,
            ],
        ]));
        $localSizeMismatch = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/styles.xml',
                'data' => '<w:styles/>',
                'method' => 0,
                'localCompressedSize' => 1,
            ],
        ]));
        $localModifiedTimeMismatch = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/settings.xml',
                'data' => '<w:settings/>',
                'method' => 0,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'localModifiedTime' => 19401,
            ],
        ]));
        $descriptorSizeMismatch = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments/>',
                'method' => 8,
                'descriptor' => true,
                'descriptorUncompressedSize' => 999,
            ],
        ]));
        $descriptorCrcMismatch = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/footnotes.xml',
                'data' => '<w:footnotes/>',
                'method' => 8,
                'descriptor' => true,
                'descriptorCrc' => 0,
            ],
        ]));

        $t->throws(\RuntimeException::class, static fn (): string => $localCrcMismatch->read('word/document.xml'));
        $t->throws(\RuntimeException::class, static fn (): string => $localSizeMismatch->read('word/styles.xml'));
        $t->throws(\RuntimeException::class, static fn (): string => $localModifiedTimeMismatch->read('word/settings.xml'));
        $t->throws(\RuntimeException::class, static fn (): string => $descriptorSizeMismatch->read('word/comments.xml'));
        $t->throws(\RuntimeException::class, static fn (): string => $descriptorCrcMismatch->read('word/footnotes.xml'));
    },

    'rejects unsupported compression methods and malformed package endings' => static function (TestRunner $t) use ($buildZipPackage): void {
        $unsupported = ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/document.xml', 'data' => '<w:document/>', 'method' => 12],
        ]));

        $t->same(['word/document.xml'], $unsupported->names());
        $t->throws(\RuntimeException::class, static fn (): string => $unsupported->read('word/document.xml'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString('not a zip package'));
    },

    'builds and reads bounded gzip streams around package fixture bytes' => static function (TestRunner $t) use ($crc32): void {
        $package = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>GZip wrapped import source</w:p></w:document>',
            ],
        ]);
        $gzip = GzipStream::build($package->bytes(), [
            'modifiedAt' => 1780479017,
            'extraFlags' => 2,
            'operatingSystem' => 3,
            'extraFieldData' => 'WP',
            'filename' => 'wordpress-package.zip',
            'comment' => 'migration source package',
            'headerCrc' => true,
            'compressionLevel' => 9,
        ]);
        $members = GzipStream::members($gzip);

        $t->same(1, count($members));
        $t->same($package->bytes(), GzipStream::decode($gzip));
        $t->same($package->bytes(), $members[0]['data']);
        $t->same(1780479017, $members[0]['modifiedAt']);
        $t->same(2, $members[0]['extraFlags']);
        $t->same(3, $members[0]['operatingSystem']);
        $t->same('WP', $members[0]['extraFieldData']);
        $t->same('wordpress-package.zip', $members[0]['filename']);
        $t->same('migration source package', $members[0]['comment']);
        $t->true(is_int($members[0]['headerCrc16']));
        $t->same($crc32($package->bytes()), $members[0]['crc32']);
        $t->same(strlen($package->bytes()), $members[0]['uncompressedSize']);
        $t->true($members[0]['compressedSize'] > 0);
        $t->same(strlen($gzip), $members[0]['memberSize']);
        $roundTrip = ZipPackage::fromString(GzipStream::decode($gzip));
        $t->same('<w:document><w:p>GZip wrapped import source</w:p></w:document>', $roundTrip->read('/word/document.xml'));
    },

    'reads concatenated gzip members as one import stream' => static function (TestRunner $t): void {
        $first = GzipStream::build("title: Archive packet\n", [
            'filename' => 'packet.yml',
            'comment' => 'front matter',
        ]);
        $second = GzipStream::build("body: Ready for WordPress review\n", [
            'filename' => 'body.yml',
        ]);
        $members = GzipStream::members($first . $second);

        $t->same(2, count($members));
        $t->same("title: Archive packet\nbody: Ready for WordPress review\n", GzipStream::decode($first . $second));
        $t->same("title: Archive packet\n", $members[0]['data']);
        $t->same("body: Ready for WordPress review\n", $members[1]['data']);
        $t->same('packet.yml', $members[0]['filename']);
        $t->same('front matter', $members[0]['comment']);
        $t->same('body.yml', $members[1]['filename']);
        $t->same(null, $members[1]['comment']);
        $t->true($members[0]['memberSize'] < strlen($first . $second));
        $t->same(strlen($second), $members[1]['memberSize']);
    },

    'rejects malformed gzip stream headers and trailers' => static function (TestRunner $t): void {
        $valid = GzipStream::build('review source', [
            'filename' => 'source.txt',
            'headerCrc' => true,
        ]);
        $badCrc = substr_replace($valid, "\0\0\0\0", -8, 4);
        $badSize = substr_replace($valid, pack('V', 999), -4, 4);
        $badHeaderCrc = substr_replace($valid, "\0\0", 21, 2);
        $badReservedFlags = substr_replace($valid, chr(0xe0 | 0x0a), 3, 1);
        $badMethod = substr_replace($valid, chr(12), 2, 1);
        $missingNameTerminator = "\x1f\x8b\x08\x08" . pack('VCC', 0, 0, 255) . 'unterminated';

        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::decode('not gzip'));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::decode($badCrc));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::decode($badSize));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($badHeaderCrc));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($badReservedFlags));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($badMethod));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($missingNameTerminator));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::build('x', ['filename' => "bad\0name"]));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::build('x', ['extraFieldData' => str_repeat('x', 0x10000)]));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($valid, 1));
    },
];
