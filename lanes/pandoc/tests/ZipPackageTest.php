<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;
use PortLibs\Pandoc\ZipPackageEntry;
use PortLibs\Pandoc\GzipStream;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
$packNtfsFileTime = static function (int $timestamp): string {
    $filetime = ($timestamp + 11644473600) * 10000000;
    $low = $filetime & 0xffffffff;
    $high = intdiv($filetime, 0x100000000);

    return pack('VV', $low, $high);
};
$buildUnicodeExtra = static function (int $id, string $rawBytes, string $utf8Text) use ($crc32): string {
    $payload = pack('CV', 1, $crc32($rawBytes)) . $utf8Text;

    return pack('vv', $id, strlen($payload)) . $payload;
};
$buildNtfsExtra = static function (int $modifiedAt, int $accessedAt, int $createdAt) use ($packNtfsFileTime): string {
    $payload = pack('Vvv', 0, 0x0001, 24)
        . $packNtfsFileTime($modifiedAt)
        . $packNtfsFileTime($accessedAt)
        . $packNtfsFileTime($createdAt);

    return pack('vv', 0x000a, strlen($payload)) . $payload;
};

/**
 * @param list<array{
 *     name:string,
 *     data?:string,
 *     method?:int,
 *     localMethod?:int,
 *     centralMethod?:int,
 *     flags?:int,
 *     localFlags?:int,
 *     centralFlags?:int,
 *     descriptor?:bool,
 *     descriptorSignature?:bool,
 *     localSlack?:string,
 *     centralCrc?:int,
 *     centralCompressedSize?:int,
 *     centralUncompressedSize?:int,
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
 *     versionMadeBy?:int,
 *     diskStart?:int,
 *     externalAttributes?:int,
 *     comment?:string,
 *     versionNeededToExtract?:int,
 *     localVersionNeeded?:int,
 *     centralVersionNeeded?:int,
 *     centralIndex?:int
 * }> $entries
 */
$buildZipPackage = static function (array $entries, string $comment = '') use ($crc32): string {
    $body = '';
    $centralRecords = [];

    foreach ($entries as $entryIndex => $entry) {
        $name = $entry['name'];
        $localName = $entry['localName'] ?? $name;
        $data = $entry['data'] ?? '';
        $method = $entry['method'] ?? 0;
        $flags = $entry['flags'] ?? 0x0800;
        $descriptor = (bool) ($entry['descriptor'] ?? false);
        if ($descriptor) {
            $flags |= 0x0008;
        }

        $localMethod = $entry['localMethod'] ?? $method;
        $centralMethod = $entry['centralMethod'] ?? $method;
        $localFlags = $entry['localFlags'] ?? $flags;
        $centralFlags = $entry['centralFlags'] ?? $flags;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $actualCrc = $crc32($data);
        $centralCrc = $entry['centralCrc'] ?? $actualCrc;
        $centralCompressedSize = $entry['centralCompressedSize'] ?? $compressedSize;
        $centralUncompressedSize = $entry['centralUncompressedSize'] ?? $uncompressedSize;
        $offset = strlen($body);
        $modifiedTime = $entry['modifiedTime'] ?? 0;
        $modifiedDate = $entry['modifiedDate'] ?? 0;
        $localModifiedTime = $entry['localModifiedTime'] ?? $modifiedTime;
        $localModifiedDate = $entry['localModifiedDate'] ?? $modifiedDate;
        $localVersionNeeded = $entry['localVersionNeeded'] ?? ($entry['versionNeededToExtract'] ?? 20);
        $centralVersionNeeded = $entry['centralVersionNeeded'] ?? ($entry['versionNeededToExtract'] ?? 20);
        $localExtra = $entry['localExtra'] ?? '';
        $centralExtra = $entry['centralExtra'] ?? $localExtra;
        $localCrc = $entry['localCrc'] ?? ($descriptor ? 0 : $actualCrc);
        $localCompressedSize = $entry['localCompressedSize'] ?? ($descriptor ? 0 : $compressedSize);
        $localUncompressedSize = $entry['localUncompressedSize'] ?? ($descriptor ? 0 : $uncompressedSize);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            $localVersionNeeded,
            $localFlags,
            $localMethod,
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
        $body .= $entry['localSlack'] ?? '';

        $entryComment = $entry['comment'] ?? '';
        $centralRecord = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            $entry['versionMadeBy'] ?? 0x0314,
            $centralVersionNeeded,
            $centralFlags,
            $centralMethod,
            $modifiedTime,
            $modifiedDate,
            $centralCrc,
            $centralCompressedSize,
            $centralUncompressedSize,
            strlen($name),
            strlen($centralExtra),
            strlen($entryComment),
            $entry['diskStart'] ?? 0,
            0,
            $entry['externalAttributes'] ?? 0,
            $offset
        );
        $centralRecord .= $name . $centralExtra . $entryComment;
        $centralRecords[] = [
            'order' => $entry['centralIndex'] ?? $entryIndex,
            'index' => $entryIndex,
            'record' => $centralRecord,
        ];
    }

    usort(
        $centralRecords,
        static fn (array $left, array $right): int => [$left['order'], $left['index']] <=> [$right['order'], $right['index']]
    );
    $central = implode('', array_map(static fn (array $record): string => $record['record'], $centralRecords));
    $centralOffset = strlen($body);
    $centralSize = strlen($central);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), $centralSize, $centralOffset, strlen($comment))
        . $comment;
};

$buildPrefixedPackage = static function () use ($crc32): string {
    $prefix = "MZhidden-review-stub\n";
    $name = 'word/document.xml';
    $data = '<w:document><w:p>prefixed package</w:p></w:document>';
    $crc = $crc32($data);
    $localHeaderOffset = strlen($prefix);
    $body = $prefix . pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        0
    );
    $body .= $name . $data;
    $centralOffset = strlen($body);
    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        0,
        0,
        0,
        0,
        0x81a40000,
        $localHeaderOffset
    );
    $central .= $name;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), $centralOffset, 0);
};

$buildDuplicateLocalOffsetPackage = static function () use ($crc32, $buildUnicodeExtra): string {
    $rawName = 'word/media/review-image.bin';
    $data = "shared local entry should not be exposed twice\n";
    $crc = $crc32($data);
    $firstUnicodePath = $buildUnicodeExtra(0x7075, $rawName, 'word/media/review-one.png');
    $secondUnicodePath = $buildUnicodeExtra(0x7075, $rawName, 'word/media/review-two.png');

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($rawName),
        strlen($firstUnicodePath)
    );
    $body .= $rawName . $firstUnicodePath . $data;

    $central = '';
    foreach ([
        ['name' => $rawName, 'extra' => $firstUnicodePath],
        ['name' => $rawName, 'extra' => $secondUnicodePath],
    ] as $entry) {
        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0,
            0,
            0,
            0,
            $crc,
            strlen($data),
            strlen($data),
            strlen($entry['name']),
            strlen($entry['extra']),
            0,
            0,
            0,
            0x81a40000,
            0
        );
        $central .= $entry['name'] . $entry['extra'];
    }

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 2, 2, strlen($central), strlen($body), 0);
};
$buildCentralDirectorySignaturePackage = static function (
    bool $includeInCentralDirectorySize = false,
    ?int $declaredSignatureLength = null
) use ($crc32): string {
    $name = 'word/document.xml';
    $data = '<w:document><w:body><w:p>digitally signed central directory</w:p></w:body></w:document>';
    $crc = $crc32($data);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        0
    );
    $body .= $name . $data;

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        0,
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $name;
    $centralDirectorySignatureData = 'central-signature';
    $centralDirectorySignature = pack(
        'Vv',
        0x05054b50,
        $declaredSignatureLength ?? strlen($centralDirectorySignatureData)
    ) . $centralDirectorySignatureData;
    $centralDirectorySize = strlen($central) + ($includeInCentralDirectorySize ? strlen($centralDirectorySignature) : 0);

    return $body
        . $central
        . $centralDirectorySignature
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, $centralDirectorySize, strlen($body), 0);
};
$packUInt64 = static function (int $value): string {
    if ($value < 0) {
        throw new RuntimeException('ZIP64 fixture values must be non-negative');
    }

    return pack('VV', $value & 0xffffffff, intdiv($value, 0x100000000));
};
$buildZip64EndOfCentralDirectoryPackage = static function () use ($buildZipPackage, $packUInt64): string {
    $zip = $buildZipPackage([
        [
            'name' => 'word/document.xml',
            'data' => '<w:document><w:p>zip64 package metadata</w:p></w:document>',
            'method' => 8,
        ],
    ]);
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('EOCD fixture not found');
    }

    $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4))['value'];
    $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
    $zip64EocdOffset = $eocdOffset;
    $zip64Eocd = "PK\x06\x06"
        . $packUInt64(44)
        . pack('vvVV', 45, 45, 0, 0)
        . $packUInt64(1)
        . $packUInt64(1)
        . $packUInt64((int) $centralDirectorySize)
        . $packUInt64((int) $centralDirectoryOffset);
    $zip64Locator = "PK\x06\x07"
        . pack('V', 0)
        . $packUInt64($zip64EocdOffset)
        . pack('V', 1);
    $eocd = substr($zip, $eocdOffset);
    $eocd = substr_replace($eocd, pack('v', 0xffff), 8, 2);
    $eocd = substr_replace($eocd, pack('v', 0xffff), 10, 2);
    $eocd = substr_replace($eocd, pack('V', 0xffffffff), 12, 4);
    $eocd = substr_replace($eocd, pack('V', 0xffffffff), 16, 4);

    return substr($zip, 0, $eocdOffset) . $zip64Eocd . $zip64Locator . $eocd;
};
$rewriteEndOfCentralDirectory = static function (string $zip, array $fields): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('EOCD fixture not found');
    }

    $offsets = [
        'diskNumber' => 4,
        'centralDirectoryDisk' => 6,
        'diskEntryCount' => 8,
        'totalEntryCount' => 10,
        'centralDirectorySize' => 12,
        'centralDirectoryOffset' => 16,
    ];
    foreach ($fields as $field => $value) {
        if (!isset($offsets[$field]) || !is_int($value)) {
            throw new RuntimeException('Unsupported EOCD fixture field: ' . (string) $field);
        }

        $format = $field === 'centralDirectorySize' || $field === 'centralDirectoryOffset' ? 'V' : 'v';
        $zip = substr_replace($zip, pack($format, $value), $eocdOffset + $offsets[$field], $format === 'V' ? 4 : 2);
    }

    return $zip;
};
$corruptZipEntryPayload = static function (string $zip, string $entryName, int $byteOffset = 0): string {
    $cursor = 0;
    $length = strlen($zip);

    while ($cursor + 30 <= $length && substr($zip, $cursor, 4) === "PK\x03\x04") {
        $compressedSize = unpack('Vvalue', substr($zip, $cursor + 18, 4))['value'];
        $nameLength = unpack('vvalue', substr($zip, $cursor + 26, 2))['value'];
        $extraLength = unpack('vvalue', substr($zip, $cursor + 28, 2))['value'];
        $nameStart = $cursor + 30;
        $dataStart = $nameStart + $nameLength + $extraLength;
        $name = substr($zip, $nameStart, $nameLength);

        if ($name === $entryName) {
            if ($compressedSize <= 0 || $byteOffset < 0 || $byteOffset >= $compressedSize) {
                throw new RuntimeException('Cannot corrupt ZIP fixture payload for ' . $entryName);
            }

            $payloadOffset = $dataStart + $byteOffset;
            $zip[$payloadOffset] = chr(ord($zip[$payloadOffset]) ^ 0xff);

            return $zip;
        }

        $cursor = $dataStart + $compressedSize;
    }

    throw new RuntimeException('ZIP fixture entry not found: ' . $entryName);
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

    'exposes zip package entries in local header order for container preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => 'application/epub+zip',
                'method' => 0,
                'centralIndex' => 2,
            ],
            [
                'name' => 'META-INF/container.xml',
                'data' => '<container/>',
                'method' => 8,
                'centralIndex' => 0,
            ],
            [
                'name' => 'OEBPS/package.opf',
                'data' => '<package/>',
                'method' => 8,
                'centralIndex' => 1,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $localEntries = $package->localEntries();

        $t->same(['META-INF/container.xml', 'OEBPS/package.opf', 'mimetype'], $package->names());
        $t->same(['mimetype', 'META-INF/container.xml', 'OEBPS/package.opf'], $package->localNames());
        $t->same('mimetype', $localEntries[0]->name);
        $t->true($localEntries[0]->localHeaderOffset < $localEntries[1]->localHeaderOffset);
        $t->true($localEntries[1]->localHeaderOffset < $localEntries[2]->localHeaderOffset);
        $t->same('application/epub+zip', $package->read('/mimetype'));
        $t->same('<container/>', $package->read('/META-INF/container.xml'));
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

    'exposes zip version needed metadata and rejects local version mismatches before package preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>version metadata</w:p></w:document>',
                'method' => 8,
                'versionNeededToExtract' => 20,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => 'stored reviewer media bytes',
                'method' => 0,
                'versionNeededToExtract' => 10,
            ],
        ]));

        $document = $package->entry('/word/document.xml');
        $media = $package->entry('word/media/review.bin');

        $t->same(20, $document->versionNeededToExtract);
        $t->same(20, $document->neededToExtractVersion());
        $t->same(10, $media->versionNeededToExtract);
        $t->same(10, $media->neededToExtractVersion());
        $t->same('<w:document><w:p>version metadata</w:p></w:document>', $package->read('/word/document.xml'));
        $t->same('stored reviewer media bytes', $package->read('/word/media/review.bin'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/settings.xml',
                'data' => '<w:settings/>',
                'method' => 0,
                'centralVersionNeeded' => 20,
                'localVersionNeeded' => 10,
            ],
        ])));
    },

    'rejects unsupported zip extraction versions before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/zip64-era-review.bin',
                'data' => 'unsupported extraction version metadata should stay blocked',
                'method' => 0,
                'versionNeededToExtract' => 45,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/bzip2-review.bin',
                'data' => 'unsupported bzip2-era package metadata should stay blocked',
                'method' => 12,
                'versionNeededToExtract' => 46,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/lzma-review.bin',
                'data' => 'unsupported lzma-era package metadata should stay blocked',
                'method' => 14,
                'versionNeededToExtract' => 63,
            ],
        ])));
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
        $t->same(3, $entry->madeByHostSystem());
        $t->same(20, $entry->madeByVersion());
        $t->same(0x81a4, $entry->unixMode());
        $t->same(false, $entry->isUnixSymlink());
        $t->same(0x81a40000, $entry->externalFileAttributes);
        $t->same('metadata package', $package->packageComment());
        $t->same('<w:document><w:p>metadata</w:p></w:document>', $package->read('word/document.xml'));
    },

    'preflights unix executable permissions before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>permission preflight</w:p></w:document>',
                'method' => 8,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/reviewer-script.bin',
                'data' => "#!/bin/sh\necho reviewer-script\n",
                'method' => 0,
                'externalAttributes' => 0x81ed0000,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x41ed0000,
            ],
            [
                'name' => 'word/media/fat-flags.bin',
                'data' => 'non-unix host bits should stay metadata only',
                'method' => 0,
                'versionMadeBy' => 0x0014,
                'externalAttributes' => 0x81ff0000,
            ],
        ]));

        $document = $package->entry('/word/document.xml');
        $script = $package->entry('/word/media/reviewer-script.bin');
        $directory = $package->entry('/word/media/');
        $fatEntry = $package->entry('/word/media/fat-flags.bin');
        $summary = $package->permissionPreflight();

        $t->same(0644, $document->unixPermissionBits());
        $t->same(false, $document->isUnixExecutableFile());
        $t->same(0755, $script->unixPermissionBits());
        $t->same(true, $script->isUnixExecutableFile());
        $t->same(0755, $directory->unixPermissionBits());
        $t->same(false, $directory->isUnixExecutableFile());
        $t->same(null, $fatEntry->unixPermissionBits());
        $t->same(false, $fatEntry->isUnixExecutableFile());
        $t->same(4, $summary['entryCount']);
        $t->same(3, $summary['unixModeEntryCount']);
        $t->same(1, $summary['executableFileCount']);
        $t->same('word/media/reviewer-script.bin', $summary['executableEntries'][0]['name']);
        $t->same(0x81ed, $summary['executableEntries'][0]['unixMode']);
        $t->same(0755, $summary['executableEntries'][0]['permissions']);
        $t->same(true, $summary['entries'][1]['isExecutableFile']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertNoExecutableFiles());

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe generated permissions</w:p></w:document>',
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/',
                'externalAttributes' => 0x41ed0000,
            ],
        ]);
        $safeSummary = $safePackage->assertNoExecutableFiles();

        $t->same(2, $safeSummary['unixModeEntryCount']);
        $t->same(0, $safeSummary['executableFileCount']);
        $t->same([], $safeSummary['executableEntries']);
    },

    'preflights zip creator host systems before package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>creator host metadata</w:p></w:document>',
                'method' => 8,
                'versionMadeBy' => 0x0314,
            ],
            [
                'name' => 'word/media/windows-review.bin',
                'data' => 'windows-origin media bytes',
                'method' => 0,
                'versionMadeBy' => 0x0a14,
            ],
            [
                'name' => 'word/media/unknown-review.bin',
                'data' => 'unknown-origin media bytes',
                'method' => 0,
                'versionMadeBy' => 0x3f14,
            ],
        ]));
        $summary = $package->creatorHostSystemPreflight();

        $t->same(3, $summary['entryCount']);
        $t->same(2, $summary['knownHostSystemEntryCount']);
        $t->same(1, $summary['unknownHostSystemEntryCount']);
        $t->same(3, count($summary['hostSystems']));
        $t->same(3, $summary['hostSystems'][0]['id']);
        $t->same('unix', $summary['hostSystems'][0]['name']);
        $t->same(1, $summary['hostSystems'][0]['entryCount']);
        $t->same(10, $summary['hostSystems'][1]['id']);
        $t->same('windows-ntfs', $summary['hostSystems'][1]['name']);
        $t->same(63, $summary['hostSystems'][2]['id']);
        $t->same('unknown', $summary['hostSystems'][2]['name']);
        $t->same(false, $summary['hostSystems'][2]['isKnown']);
        $t->same('word/media/unknown-review.bin', $summary['unknownEntries'][0]['name']);
        $t->same(63, $summary['unknownEntries'][0]['madeByHostSystem']);
        $t->same('unknown', $summary['unknownEntries'][0]['madeByHostSystemName']);
        $t->same(20, $summary['unknownEntries'][0]['madeByVersion']);
        $t->same(0x3f14, $summary['unknownEntries'][0]['versionMadeBy']);
        $t->same('windows-ntfs', $summary['entries'][1]['madeByHostSystemName']);
        $t->same(true, $summary['entries'][1]['isKnown']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertKnownCreatorHostSystems());

        $safePackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>known creator metadata</w:p></w:document>',
                'method' => 8,
                'versionMadeBy' => 0x0314,
            ],
            [
                'name' => 'word/media/windows-review.bin',
                'data' => 'known windows media bytes',
                'method' => 0,
                'versionMadeBy' => 0x0a14,
            ],
        ]));
        $safeSummary = $safePackage->assertKnownCreatorHostSystems();

        $t->same(2, $safeSummary['knownHostSystemEntryCount']);
        $t->same(0, $safeSummary['unknownHostSystemEntryCount']);
        $t->same([], $safeSummary['unknownEntries']);
    },

    'rejects zip symlink entries before office package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/review.png',
                'data' => '../embeddings/oleObject1.bin',
                'method' => 0,
                'externalAttributes' => 0xa1ff0000,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/media/review.png',
                'data' => '../embeddings/oleObject1.bin',
                'compressionMethod' => 0,
                'externalAttributes' => 0xa1ff0000,
            ],
        ]));

        $fatPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/review.png',
                'data' => 'ordinary external attribute bytes',
                'method' => 0,
                'versionMadeBy' => 0x0014,
                'externalAttributes' => 0xa1ff0000,
            ],
        ]));

        $t->same(null, $fatPackage->entry('word/media/review.png')->unixMode());
        $t->same(false, $fatPackage->entry('word/media/review.png')->isUnixSymlink());
    },

    'rejects zip directory entries with payload before office package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => 'hidden directory payload',
                'method' => 0,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 8,
            ],
        ])));

        $directoryPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));

        $t->true($directoryPackage->entry('word/media/')->isDirectory());
        $t->same('', $directoryPackage->read('word/media/'));
    },

    'rejects zip DOS directory attributes without directory names before package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-folder',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x10,
            ],
        ])));

        $directoryPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x10,
            ],
        ]));

        $entry = $directoryPackage->entry('/word/media/');
        $t->true($entry->isDirectory());
        $t->true($entry->hasDosDirectoryAttribute());
        $t->same('', $directoryPackage->read('/word/media/'));
    },

    'rejects stored zip entry size mismatches before package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $storedMedia = "stored reviewer media bytes\n";
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => $storedMedia,
                'method' => 0,
                'centralUncompressedSize' => strlen($storedMedia) + 1,
                'localUncompressedSize' => strlen($storedMedia) + 1,
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => $storedMedia,
                'method' => 0,
            ],
        ]));

        $t->same($storedMedia, $package->read('/word/media/reviewer-note.txt'));
    },

    'rejects zip local entry layout overlap before office package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>overlap</w:p></w:document>',
                'method' => 0,
                'centralCompressedSize' => strlen('<w:document><w:p>overlap</w:p></w:document>') + 12,
                'centralUncompressedSize' => strlen('<w:document><w:p>overlap</w:p></w:document>') + 12,
            ],
            [
                'name' => 'word/styles.xml',
                'data' => '<w:styles/>',
                'method' => 0,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments/>',
                'method' => 0,
                'flags' => 0x0808,
            ],
            [
                'name' => 'word/footnotes.xml',
                'data' => '<w:footnotes/>',
                'method' => 0,
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>ordinary layout</w:p></w:document>',
                'method' => 0,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments/>',
                'method' => 8,
                'descriptor' => true,
            ],
        ]));

        $t->same([
            'word/document.xml',
            'word/comments.xml',
        ], $package->names());
        $t->same('<w:document><w:p>ordinary layout</w:p></w:document>', $package->read('/word/document.xml'));
        $t->same('<w:comments/>', $package->read('/word/comments.xml'));
    },

    'rejects zip local entry slack and package prefixes before import preflight' => static function (TestRunner $t) use ($buildZipPackage, $buildPrefixedPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildPrefixedPackage()));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>hidden local slack</w:p></w:document>',
                'method' => 0,
                'localSlack' => 'hidden-bytes-before-next-header',
            ],
            [
                'name' => 'word/settings.xml',
                'data' => '<w:settings/>',
                'method' => 0,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>hidden central slack</w:p></w:document>',
                'method' => 0,
                'localSlack' => 'hidden-bytes-before-central-directory',
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments><w:comment>descriptor remains valid</w:comment></w:comments>',
                'method' => 8,
                'descriptor' => true,
            ],
        ]));

        $t->same(['word/comments.xml'], $package->localNames());
        $t->same('<w:comments><w:comment>descriptor remains valid</w:comment></w:comments>', $package->read('/word/comments.xml'));
    },

    'rejects duplicate zip local header offsets before package import preflight' => static function (TestRunner $t) use ($buildDuplicateLocalOffsetPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildDuplicateLocalOffsetPackage()));
    },

    'preflights zip central directory digital signatures before package import handoff' => static function (TestRunner $t) use ($buildCentralDirectorySignaturePackage): void {
        $package = ZipPackage::fromString($buildCentralDirectorySignaturePackage());
        $signature = $package->centralDirectorySignaturePreflight();

        $t->true($package->hasCentralDirectorySignature());
        $t->same('central-signature', $package->centralDirectorySignature());
        $t->same(true, $signature['present']);
        $t->same($package->centralDirectoryOffset() + 46 + strlen('word/document.xml'), $signature['offset']);
        $t->same('central-signature', $signature['signatureData']);
        $t->same(strlen('central-signature'), $signature['signatureLength']);
        $t->same('not-performed-native-bounded-reader', $signature['cryptographicVerification']);
        $t->same('<w:document><w:body><w:p>digitally signed central directory</w:p></w:body></w:document>', $package->read('/word/document.xml'));

        $includedSizePackage = ZipPackage::fromString($buildCentralDirectorySignaturePackage(true));
        $t->true($includedSizePackage->hasCentralDirectorySignature());
        $t->same('central-signature', $includedSizePackage->centralDirectorySignaturePreflight()['signatureData']);
        $t->same(
            '<w:document><w:body><w:p>digitally signed central directory</w:p></w:body></w:document>',
            $includedSizePackage->read('/word/document.xml')
        );
    },

    'rejects malformed zip central directory digital signature records' => static function (TestRunner $t) use ($buildCentralDirectorySignaturePackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString(
            $buildCentralDirectorySignaturePackage(false, 5)
        ));
    },

    'preflights zip end of central directory archive layout before package import' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>EOCD preflight</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "PNG reviewer attachment placeholder\n",
                'method' => 0,
            ],
        ], 'archive layout comment');
        $rawSummary = ZipPackage::endOfCentralDirectoryPreflight($zip);
        $package = ZipPackage::fromString($zip);
        $summary = $package->archivePreflight();

        $t->same($rawSummary['eocdOffset'], strlen($zip) - 22 - strlen('archive layout comment'));
        $t->same(0, $summary['diskNumber']);
        $t->same(0, $summary['centralDirectoryDisk']);
        $t->same(2, $summary['diskEntryCount']);
        $t->same(2, $summary['totalEntryCount']);
        $t->same($package->centralDirectoryOffset(), $summary['centralDirectoryOffset']);
        $t->same($rawSummary['centralDirectorySize'], $summary['centralDirectorySize']);
        $t->same($summary['eocdOffset'], $summary['centralDirectoryEnd']);
        $t->same('archive layout comment', $summary['packageComment']);
        $t->same(strlen('archive layout comment'), $summary['packageCommentLength']);
        $t->same(true, $summary['isSingleDisk']);
        $t->same(false, $summary['requiresZip64']);
        $t->same(true, $summary['isArchiveLayoutSupported']);
        $t->same(false, $summary['hasCentralDirectorySignature']);
        $t->same(null, $summary['centralDirectorySignatureOffset']);
        $t->same(0, $summary['centralDirectorySignatureLength']);

        $splitZip = $rewriteEndOfCentralDirectory($zip, [
            'diskNumber' => 1,
            'centralDirectoryDisk' => 1,
            'diskEntryCount' => 1,
        ]);
        $splitSummary = ZipPackage::endOfCentralDirectoryPreflight($splitZip);
        $t->same(1, $splitSummary['diskNumber']);
        $t->same(1, $splitSummary['centralDirectoryDisk']);
        $t->same(1, $splitSummary['diskEntryCount']);
        $t->same(2, $splitSummary['totalEntryCount']);
        $t->same(false, $splitSummary['isSingleDisk']);
        $t->same(false, $splitSummary['isArchiveLayoutSupported']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($splitZip));

        $zip64MarkerZip = $rewriteEndOfCentralDirectory($zip, [
            'diskEntryCount' => 0xffff,
            'totalEntryCount' => 0xffff,
            'centralDirectorySize' => 0xffffffff,
        ]);
        $zip64Summary = ZipPackage::endOfCentralDirectoryPreflight($zip64MarkerZip);
        $t->same(true, $zip64Summary['requiresZip64']);
        $t->same(false, $zip64Summary['isArchiveLayoutSupported']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip64MarkerZip));
    },

    'preflights zip64 end of central directory locator before package import' => static function (TestRunner $t) use ($buildZip64EndOfCentralDirectoryPackage): void {
        $zip = $buildZip64EndOfCentralDirectoryPackage();
        $summary = ZipPackage::endOfCentralDirectoryPreflight($zip);

        $t->same(true, $summary['requiresZip64']);
        $t->same(false, $summary['isArchiveLayoutSupported']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectoryLocator']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectory']);
        $t->same($summary['eocdOffset'] - 20, $summary['zip64EndOfCentralDirectoryLocatorOffset']);
        $t->same(56, $summary['zip64EndOfCentralDirectorySize']);
        $t->same(1, $summary['zip64TotalDisks']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'decodes cp437 zip entry names and comments when utf8 flag is absent' => static function (TestRunner $t) use ($buildZipPackage): void {
        $rawName = "word/media/caf\x82.png";
        $decodedName = "word/media/caf\u{00e9}.png";
        $rawComment = "r\x82sum\x82 media";
        $decodedComment = "r\u{00e9}sum\u{00e9} media";

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => "PNG reviewer attachment placeholder\n",
                'method' => 0,
                'flags' => 0,
                'comment' => $rawComment,
            ],
        ]));
        $entry = $package->entry($decodedName);

        $t->same([$decodedName], $package->names());
        $t->same($decodedName, $entry->name);
        $t->same($rawName, $entry->rawName);
        $t->same('cp437', $entry->nameEncoding);
        $t->same($decodedComment, $entry->comment);
        $t->same($rawComment, $entry->rawComment);
        $t->same('cp437', $entry->commentEncoding);
        $t->same("PNG reviewer attachment placeholder\n", $package->read('/' . $decodedName));
    },

    'uses info zip unicode path and comment extras for legacy encoded package names' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra, $crc32): void {
        $rawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $rawComment = 'legacy reviewer comment';
        $unicodeComment = "Unicode reviewer \u{2603} comment";
        $unicodePathExtra = $buildUnicodeExtra(0x7075, $rawName, $unicodeName);
        $unicodeCommentExtra = $buildUnicodeExtra(0x6375, $rawComment, $unicodeComment);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => "Unicode media attachment placeholder\n",
                'method' => 8,
                'flags' => 0,
                'comment' => $rawComment,
                'localExtra' => $unicodePathExtra,
                'centralExtra' => $unicodePathExtra . $unicodeCommentExtra,
            ],
        ]));
        $entry = $package->entry($unicodeName);

        $t->same([$unicodeName], $package->names());
        $t->same($rawName, $entry->rawName);
        $t->same($unicodeName, $entry->name);
        $t->same('info-zip-unicode-path', $entry->nameEncoding);
        $t->same($rawComment, $entry->rawComment);
        $t->same($unicodeComment, $entry->comment);
        $t->same('info-zip-unicode-comment', $entry->commentEncoding);
        $t->same("\x01" . pack('V', $crc32($rawName)) . $unicodeName, $entry->centralExtraField(0x7075));
        $t->same("Unicode media attachment placeholder\n", $package->read('/' . $unicodeName));
    },

    'preflights package and entry comments for reviewer provenance' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $rawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $rawComment = 'legacy reviewer comment';
        $unicodeComment = "Unicode reviewer \u{2603} comment";
        $cp437Comment = "r\x82sum\x82 media";

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => "Unicode media attachment placeholder\n",
                'method' => 0,
                'flags' => 0,
                'comment' => $rawComment,
                'localExtra' => $buildUnicodeExtra(0x7075, $rawName, $unicodeName),
                'centralExtra' => $buildUnicodeExtra(0x7075, $rawName, $unicodeName)
                    . $buildUnicodeExtra(0x6375, $rawComment, $unicodeComment),
            ],
            [
                'name' => "word/media/caf\x82.png",
                'data' => "legacy media attachment placeholder\n",
                'method' => 0,
                'flags' => 0,
                'comment' => $cp437Comment,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>comment metadata</w:p></w:document>',
                'method' => 8,
            ],
        ], "package r\x82sum\x82"));

        $summary = $package->commentPreflight();

        $t->same("package r\u{00e9}sum\u{00e9}", $summary['packageComment']);
        $t->same('cp437', $summary['packageCommentEncoding']);
        $t->same(strlen("package r\x82sum\x82"), $summary['packageCommentLength']);
        $t->same(2, $summary['entryCommentCount']);
        $t->same(3, count($summary['entries']));
        $t->same($unicodeName, $summary['commentedEntries'][0]['name']);
        $t->same($unicodeComment, $summary['commentedEntries'][0]['comment']);
        $t->same('info-zip-unicode-comment', $summary['commentedEntries'][0]['commentEncoding']);
        $t->same($rawComment, $summary['commentedEntries'][0]['rawComment']);
        $t->same("word/media/caf\u{00e9}.png", $summary['commentedEntries'][1]['name']);
        $t->same("r\u{00e9}sum\u{00e9} media", $summary['commentedEntries'][1]['comment']);
        $t->same('cp437', $summary['commentedEntries'][1]['commentEncoding']);
        $t->same('', $summary['entries'][2]['comment']);
        $t->same('utf-8', $summary['entries'][2]['commentEncoding']);
    },

    'preflights strict zip comment policy before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $commentedPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>commented package policy</w:p></w:document>',
                'method' => 8,
                'comment' => 'document reviewer comment',
            ],
            [
                'name' => 'word/media/review-note.txt',
                'data' => 'review packet comment metadata',
                'method' => 0,
            ],
        ], 'source package review comment'));
        $summary = $commentedPackage->commentPreflight();

        $t->same('source package review comment', $summary['packageComment']);
        $t->same(true, $summary['hasPackageComment']);
        $t->same(true, $summary['hasEntryComments']);
        $t->same(true, $summary['hasComments']);
        $t->same(1, $summary['entryCommentCount']);
        $t->same(['word/document.xml'], $summary['commentedEntryNames']);
        $t->same('document reviewer comment', $summary['commentedEntries'][0]['comment']);
        $t->same('review packet comment metadata', $commentedPackage->read('/word/media/review-note.txt'));
        $t->throws(\RuntimeException::class, static fn (): array => $commentedPackage->assertNoPackageOrEntryComments());

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>no comment policy</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review-note.txt',
                'data' => 'comment-free media handoff',
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoPackageOrEntryComments();

        $t->same(false, $safeSummary['hasPackageComment']);
        $t->same(false, $safeSummary['hasEntryComments']);
        $t->same(false, $safeSummary['hasComments']);
        $t->same(0, $safeSummary['entryCommentCount']);
        $t->same([], $safeSummary['commentedEntryNames']);
        $t->same('comment-free media handoff', $safePackage->read('/word/media/review-note.txt'));
    },

    'rejects unsafe raw zip names even when unicode path metadata is safe' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $safeUnicodePath = 'word/media/review.png';
        $absoluteRawName = '/word/media/review.png';
        $traversalRawName = 'word/../media/review.png';

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $absoluteRawName,
                'data' => 'absolute raw path with safe Unicode path',
                'flags' => 0,
                'centralExtra' => $buildUnicodeExtra(0x7075, $absoluteRawName, $safeUnicodePath),
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $traversalRawName,
                'data' => 'traversal raw path with safe Unicode path',
                'flags' => 0,
                'centralExtra' => $buildUnicodeExtra(0x7075, $traversalRawName, $safeUnicodePath),
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/review-image.bin',
                'data' => "safe raw path with Unicode media name\n",
                'flags' => 0,
                'centralExtra' => $buildUnicodeExtra(0x7075, 'word/media/review-image.bin', $safeUnicodePath),
            ],
        ]));

        $t->same([$safeUnicodePath], $package->names());
        $t->same('word/media/review-image.bin', $package->entry('/' . $safeUnicodePath)->rawName);
        $t->same("safe raw path with Unicode media name\n", $package->read('/' . $safeUnicodePath));
    },

    'rejects mismatched unicode zip path metadata before exposing package names' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $rawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $centralPathExtra = $buildUnicodeExtra(0x7075, $rawName, $unicodeName);
        $badCentralPathExtra = $buildUnicodeExtra(0x7075, 'word/media/other.bin', $unicodeName);
        $badLocalPathExtra = $buildUnicodeExtra(0x7075, $rawName, 'word/media/other.png');

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'bad central unicode path crc',
                'flags' => 0,
                'centralExtra' => $badCentralPathExtra,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'bad local unicode path value',
                'flags' => 0,
                'localExtra' => $badLocalPathExtra,
                'centralExtra' => $centralPathExtra,
            ],
        ])));
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

    'exposes local zip extra fields for package preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $extendedTimestamp = 1780479017;
        $timestampExtra = pack('vvCV', 0x5455, 5, 0x01, $extendedTimestamp);
        $localVendorExtra = pack('vva*', 0xcafe, 5, 'local');
        $centralVendorExtra = pack('vva*', 0xcafe, 7, 'central');

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>local metadata</w:p></w:document>',
                'method' => 8,
                'localExtra' => $timestampExtra . $localVendorExtra,
                'centralExtra' => $timestampExtra . $centralVendorExtra,
            ],
        ]));

        $entry = $package->entry('word/document.xml');

        $t->same('central', $entry->centralExtraField(0xcafe));
        $t->same('local', $package->localExtraField('/word/document.xml', 0xcafe));
        $t->same($extendedTimestamp, $package->localExtendedLastModifiedTimestamp('word/document.xml'));
        $t->same([
            ['id' => 0x5455, 'data' => "\x01" . pack('V', $extendedTimestamp)],
            ['id' => 0xcafe, 'data' => 'local'],
        ], $package->localExtraFields('word/document.xml'));
        $t->same('<w:document><w:p>local metadata</w:p></w:document>', $package->read('/word/document.xml'));
    },

    'reads local extended timestamp access and creation metadata for package preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $modifiedAt = 1780479017;
        $accessedAt = 1780479022;
        $createdAt = 1780479023;
        $centralTimestampExtra = pack('vvCV', 0x5455, 5, 0x01, $modifiedAt);
        $localTimestampExtra = pack('vvCVVV', 0x5455, 13, 0x07, $modifiedAt, $accessedAt, $createdAt);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'reviewer media provenance',
                'method' => 8,
                'localExtra' => $localTimestampExtra,
                'centralExtra' => $centralTimestampExtra,
            ],
        ]));
        $entry = $package->entry('word/media/reviewer-note.txt');

        $t->same(['modifiedAt' => $modifiedAt], $entry->extendedTimestamps());
        $t->same($modifiedAt, $entry->extendedLastModifiedTimestamp());
        $t->same(null, $entry->extendedAccessedTimestamp());
        $t->same(null, $entry->extendedCreatedTimestamp());
        $t->same([
            'modifiedAt' => $modifiedAt,
            'accessedAt' => $accessedAt,
            'createdAt' => $createdAt,
        ], $package->localExtendedTimestamps('/word/media/reviewer-note.txt'));
        $t->same($modifiedAt, $package->localExtendedLastModifiedTimestamp('word/media/reviewer-note.txt'));
        $t->same($accessedAt, $package->localExtendedAccessedTimestamp('word/media/reviewer-note.txt'));
        $t->same($createdAt, $package->localExtendedCreatedTimestamp('word/media/reviewer-note.txt'));
        $t->same($modifiedAt, $entry->lastModifiedTimestamp());
        $t->same('reviewer media provenance', $package->read('/word/media/reviewer-note.txt'));
    },

    'reads local extended timestamp fields without modified time' => static function (TestRunner $t) use ($buildZipPackage): void {
        $accessedAt = 1780479022;
        $createdAt = 1780479023;
        $localTimestampExtra = pack('vvCVV', 0x5455, 9, 0x06, $accessedAt, $createdAt);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/audit-source.txt',
                'data' => 'source packet audit timestamp',
                'method' => 0,
                'localExtra' => $localTimestampExtra,
                'centralExtra' => '',
            ],
        ]));

        $t->same(null, $package->entry('/word/media/audit-source.txt')->extendedTimestamps());
        $t->same([
            'accessedAt' => $accessedAt,
            'createdAt' => $createdAt,
        ], $package->localExtendedTimestamps('word/media/audit-source.txt'));
        $t->same(null, $package->localExtendedLastModifiedTimestamp('word/media/audit-source.txt'));
        $t->same($accessedAt, $package->localExtendedAccessedTimestamp('word/media/audit-source.txt'));
        $t->same($createdAt, $package->localExtendedCreatedTimestamp('word/media/audit-source.txt'));
        $t->same('source packet audit timestamp', $package->read('word/media/audit-source.txt'));
    },

    'rejects local extended timestamp mismatches before exposing package entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $centralTimestampExtra = pack('vvCV', 0x5455, 5, 0x01, 1780479017);
        $localTimestampExtra = pack('vvCV', 0x5455, 5, 0x01, 1780479018);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>mismatch</w:p></w:document>',
                'method' => 8,
                'localExtra' => $localTimestampExtra,
                'centralExtra' => $centralTimestampExtra,
            ],
        ])));
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

    'writes bounded custom zip extra fields for generated review package parts' => static function (TestRunner $t): void {
        $modifiedAt = 1780479017;
        $reviewExtra = pack('vva*', 0xcafe, strlen('wp-review:v1'), 'wp-review:v1');
        $package = ZipPackage::fromParts([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'review packet provenance',
                'modifiedAt' => $modifiedAt,
                'extraFieldData' => $reviewExtra,
            ],
        ]);

        $roundTrip = ZipPackage::fromString($package->bytes());
        $entry = $roundTrip->entry('word/media/reviewer-note.txt');

        $t->same($modifiedAt, $entry->extendedLastModifiedTimestamp());
        $t->same('wp-review:v1', $entry->centralExtraField(0xcafe));
        $t->same('wp-review:v1', $roundTrip->localExtraField('/word/media/reviewer-note.txt', 0xcafe));
        $t->same([
            ['id' => 0x5455, 'data' => "\x01" . pack('V', $modifiedAt)],
            ['id' => 0xcafe, 'data' => 'wp-review:v1'],
        ], $entry->centralExtraFields());
        $t->same([
            ['id' => 0x5455, 'data' => "\x01" . pack('V', $modifiedAt)],
            ['id' => 0xcafe, 'data' => 'wp-review:v1'],
        ], $roundTrip->localExtraFields('word/media/reviewer-note.txt'));
        $t->same('review packet provenance', $roundTrip->read('word/media/reviewer-note.txt'));
    },

    'preflights duplicate zip extra field ids before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $centralExtra = pack('vva*', 0xcafe, strlen('central-one'), 'central-one')
            . pack('vva*', 0xcafe, strlen('central-two'), 'central-two')
            . pack('vva*', 0x5455, 5, "\x01" . pack('V', 1780479017));
        $localExtra = pack('vva*', 0xbeef, strlen('local-one'), 'local-one')
            . pack('vva*', 0xbeef, strlen('local-two'), 'local-two')
            . pack('vva*', 0x5455, 5, "\x01" . pack('V', 1780479017));
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'reviewer media provenance with duplicate extra fields',
                'method' => 0,
                'centralExtra' => $centralExtra,
                'localExtra' => $localExtra,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>duplicate extra field audit</w:p></w:document>',
                'method' => 8,
            ],
        ]));
        $summary = $package->extraFieldPreflight();

        $t->same(2, $summary['entryCount']);
        $t->same(1, $summary['extraFieldEntryCount']);
        $t->same(1, $summary['duplicateExtraFieldEntryCount']);
        $t->same(1, $summary['duplicateCentralExtraFieldEntryCount']);
        $t->same(1, $summary['duplicateLocalExtraFieldEntryCount']);
        $t->same('word/media/reviewer-note.txt', $summary['duplicateEntries'][0]['name']);
        $t->same([0xcafe, 0xcafe, 0x5455], $summary['entries'][0]['centralExtraFieldIds']);
        $t->same([0xbeef, 0xbeef, 0x5455], $summary['entries'][0]['localExtraFieldIds']);
        $t->same([0xcafe], $summary['entries'][0]['duplicateCentralExtraFieldIds']);
        $t->same([0xbeef], $summary['entries'][0]['duplicateLocalExtraFieldIds']);
        $t->same(true, $summary['entries'][0]['hasDuplicateExtraFieldIds']);
        $t->same([], $summary['entries'][1]['duplicateCentralExtraFieldIds']);
        $t->same([], $summary['entries'][1]['duplicateLocalExtraFieldIds']);
        $t->same('central-one', $package->entry('word/media/reviewer-note.txt')->centralExtraField(0xcafe));
        $t->same('local-one', $package->localExtraField('/word/media/reviewer-note.txt', 0xbeef));
        $t->same('reviewer media provenance with duplicate extra fields', $package->read('/word/media/reviewer-note.txt'));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertNoDuplicateExtraFieldIds());

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe extra fields</w:p></w:document>',
                'modifiedAt' => 1780479017,
                'extraFieldData' => pack('vva*', 0xcafe, strlen('wp-review:v1'), 'wp-review:v1'),
            ],
        ]);
        $safeSummary = $safePackage->assertNoDuplicateExtraFieldIds();

        $t->same(1, $safeSummary['entryCount']);
        $t->same(1, $safeSummary['extraFieldEntryCount']);
        $t->same(0, $safeSummary['duplicateExtraFieldEntryCount']);
        $t->same([], $safeSummary['duplicateEntries']);
        $t->same([0x5455, 0xcafe], $safeSummary['entries'][0]['centralExtraFieldIds']);
        $t->same([0x5455, 0xcafe], $safeSummary['entries'][0]['localExtraFieldIds']);
    },

    'reads ntfs zip extra field timestamps for office package preflight' => static function (TestRunner $t) use ($buildZipPackage, $buildNtfsExtra): void {
        $modifiedAt = 1780479017;
        $accessedAt = 1780479018;
        $createdAt = 1780479019;
        $ntfsExtra = $buildNtfsExtra($modifiedAt, $accessedAt, $createdAt);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>ntfs timestamps</w:p></w:document>',
                'method' => 8,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'localExtra' => $ntfsExtra,
                'centralExtra' => $ntfsExtra,
            ],
        ]));

        $entry = $package->entry('word/document.xml');
        $timestamps = [
            'modifiedAt' => $modifiedAt,
            'accessedAt' => $accessedAt,
            'createdAt' => $createdAt,
        ];

        $t->same($timestamps, $entry->ntfsTimestamps());
        $t->same($modifiedAt, $entry->ntfsLastModifiedTimestamp());
        $t->same($modifiedAt, $entry->lastModifiedTimestamp());
        $t->same($timestamps, $package->localNtfsTimestamps('/word/document.xml'));
        $t->same($modifiedAt, $package->localNtfsLastModifiedTimestamp('word/document.xml'));
        $t->same('<w:document><w:p>ntfs timestamps</w:p></w:document>', $package->read('/word/document.xml'));
    },

    'rejects malformed and mismatched ntfs zip extra field metadata' => static function (TestRunner $t) use ($buildZipPackage, $buildNtfsExtra): void {
        $centralNtfsExtra = $buildNtfsExtra(1780479017, 1780479018, 1780479019);
        $localNtfsExtra = $buildNtfsExtra(1780479020, 1780479018, 1780479019);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>ntfs mismatch</w:p></w:document>',
                'method' => 0,
                'centralExtra' => $centralNtfsExtra,
                'localExtra' => $localNtfsExtra,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/settings.xml',
                'data' => '<w:settings/>',
                'method' => 0,
                'centralExtra' => pack('vvVvv', 0x000a, 12, 0, 0x0001, 20) . str_repeat("\0", 20),
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/footnotes.xml',
                'data' => '<w:footnotes/>',
                'method' => 0,
                'centralExtra' => $centralNtfsExtra,
                'localExtra' => pack('vvC', 0x000a, 4, 0),
            ],
        ])));
    },

    'rejects malformed central and local zip extra fields' => static function (TestRunner $t) use ($buildZipPackage): void {
        $truncatedCentralExtra = pack('vvC', 0x5455, 5, 0x01);
        $truncatedLocalExtra = pack('vvC', 0x5455, 5, 0x01);
        $validCentralExtra = pack('vvCV', 0x5455, 5, 0x01, 1780479017);

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document/>',
                'method' => 0,
                'centralExtra' => $truncatedCentralExtra,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'truncated central access timestamp',
                'method' => 0,
                'centralExtra' => pack('vvC', 0x5455, 5, 0x02),
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document/>',
                'method' => 0,
                'centralExtra' => $validCentralExtra,
                'localExtra' => $truncatedLocalExtra,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'truncated local creation timestamp',
                'method' => 0,
                'centralExtra' => '',
                'localExtra' => pack('vvCVV', 0x5455, 9, 0x07, 1780479017, 1780479022),
            ],
        ])));
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
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok'],
        ], "invalid package comment \xff"));
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
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'extraFieldData' => ['not bytes']],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'extraFieldData' => pack('vvC', 0xcafe, 4, 1)],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'extraFieldData' => pack('vv', 0x0001, 8) . str_repeat("\0", 8)],
        ]));
    },

    'rejects unsafe package part names before exposing entries' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => '/word/document.xml', 'data' => 'absolute'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'C:word/document.xml', 'data' => 'drive letter'],
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
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'C:word/document.xml', 'data' => 'drive letter'],
        ]));

        $rawName = 'word/media/review-image.bin';
        $unsafeUnicodePathExtra = $buildUnicodeExtra(0x7075, $rawName, 'C:word/media/review.png');
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'unicode path drive letter',
                'flags' => 0,
                'centralExtra' => $unsafeUnicodePathExtra,
            ],
        ])));
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

    'rejects strong and central-directory encrypted zip metadata before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/strong-encryption.xml', 'data' => 'masked strong encryption metadata', 'flags' => 0x0840],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/central-directory-encrypted.xml', 'data' => 'masked local header metadata', 'flags' => 0x2800],
        ])));
    },

    'rejects unsupported zip general purpose flag bits before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/enhanced-deflate.bin',
                'data' => 'enhanced deflate metadata should stay blocked',
                'method' => 8,
                'flags' => 0x0810,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/patched-data.bin',
                'data' => 'compressed patched data should stay blocked',
                'method' => 8,
                'flags' => 0x0820,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reserved-flags.bin',
                'data' => 'reserved general-purpose flags should stay blocked',
                'method' => 0,
                'flags' => 0xc800,
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>supported descriptor and deflate option flags</w:p></w:document>',
                'method' => 8,
                'flags' => 0x080e,
                'descriptor' => true,
            ],
        ]));

        $t->same(0x080e, $package->entry('word/document.xml')->generalPurposeFlags);
        $t->same(
            '<w:document><w:p>supported descriptor and deflate option flags</w:p></w:document>',
            $package->read('/word/document.xml')
        );
    },

    'rejects deflate option flags on non-deflated zip entries before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/stored-maximum.bin',
                'data' => 'stored package media must not claim deflate maximum compression flags',
                'method' => 0,
                'flags' => 0x0802,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/stored-fast.bin',
                'data' => 'stored package media must not claim deflate fast compression flags',
                'method' => 0,
                'flags' => 0x0804,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/stored-superfast.bin',
                'data' => 'stored package media must not claim deflate superfast compression flags',
                'method' => 0,
                'flags' => 0x0806,
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>deflate option flags stay valid on deflated entries</w:p></w:document>',
                'method' => 8,
                'flags' => 0x0806,
            ],
            [
                'name' => 'word/media/unsupported-method.bin',
                'data' => 'unsupported methods without deflate option flags remain preflightable',
                'method' => 12,
            ],
        ]));

        $t->same(0x0806, $package->entry('word/document.xml')->generalPurposeFlags);
        $t->same(
            '<w:document><w:p>deflate option flags stay valid on deflated entries</w:p></w:document>',
            $package->read('/word/document.xml')
        );
        $t->same(12, $package->entry('word/media/unsupported-method.bin')->compressionMethod);
    },

    'rejects crc and local file header names before exposing package entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document>changed</w:document>',
                'method' => 8,
                'centralCrc' => 0,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/other.xml',
                'data' => '<w:document/>',
                'method' => 0,
            ],
        ])));
    },

    'rejects local header and data descriptor integrity mismatches' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document>local crc mismatch</w:document>',
                'method' => 8,
                'localCrc' => 0,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/styles.xml',
                'data' => '<w:styles/>',
                'method' => 0,
                'localCompressedSize' => 1,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/settings.xml',
                'data' => '<w:settings/>',
                'method' => 0,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'localModifiedTime' => 19401,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments/>',
                'method' => 8,
                'descriptor' => true,
                'descriptorUncompressedSize' => 999,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/footnotes.xml',
                'data' => '<w:footnotes/>',
                'method' => 8,
                'descriptor' => true,
                'descriptorCrc' => 0,
            ],
        ])));
    },

    'rejects local header flags and methods before exposing package entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments/>',
                'method' => 8,
                'descriptor' => true,
                'localFlags' => 0x0800,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>method mismatch</w:p></w:document>',
                'method' => 8,
                'localMethod' => 0,
            ],
        ])));
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>matching local header</w:p></w:document>',
                'method' => 8,
            ],
        ]));
        $t->same(['word/document.xml'], $package->names());
        $t->same(['word/document.xml'], $package->localNames());
        $t->same(8, $package->entry('word/document.xml')->compressionMethod);
        $t->same('<w:document><w:p>matching local header</w:p></w:document>', $package->read('word/document.xml'));
    },

    'rejects unsupported compression methods and malformed package endings' => static function (TestRunner $t) use ($buildZipPackage): void {
        $unsupported = ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/document.xml', 'data' => '<w:document/>', 'method' => 12],
        ]));

        $t->same(['word/document.xml'], $unsupported->names());
        $t->throws(\RuntimeException::class, static fn (): string => $unsupported->read('word/document.xml'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString('not a zip package'));
    },

    'preflights unsupported compression methods before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>compression preflight</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review-bzip2.bin',
                'data' => 'Unsupported method bytes stay blocked before media handoff',
                'method' => 12,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));
        $summary = $package->compressionMethodPreflight();

        $t->same(3, $summary['entryCount']);
        $t->same(2, $summary['supportedEntryCount']);
        $t->same(1, $summary['unsupportedCompressionMethodCount']);
        $t->same(1, $summary['storedEntryCount']);
        $t->same(1, $summary['deflatedEntryCount']);
        $t->same('word/media/review-bzip2.bin', $summary['unsupportedEntries'][0]['name']);
        $t->same(12, $summary['unsupportedEntries'][0]['compressionMethod']);
        $t->same(false, $summary['unsupportedEntries'][0]['isDirectory']);
        $t->same('word/media/review-bzip2.bin', $summary['entries'][1]['name']);
        $t->same('unsupported', $summary['entries'][1]['compressionMethodName']);
        $t->same(false, $summary['entries'][1]['isSupported']);
        $t->same('stored', $summary['entries'][2]['compressionMethodName']);
        $t->same(true, $summary['entries'][2]['isSupported']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertSupportedCompressionMethods());

        $safePackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>supported methods</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => 'stored media note',
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));
        $safeSummary = $safePackage->assertSupportedCompressionMethods();

        $t->same(3, $safeSummary['entryCount']);
        $t->same(3, $safeSummary['supportedEntryCount']);
        $t->same(0, $safeSummary['unsupportedCompressionMethodCount']);
        $t->same([], $safeSummary['unsupportedEntries']);
    },

    'rejects zip64 extra field metadata before office package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip64Extra = pack('vv', 0x0001, 8) . str_repeat("\0", 8);

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>zip64 central metadata</w:p></w:document>',
                'centralExtra' => $zip64Extra,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'local zip64 metadata must not be exposed',
                'localExtra' => $zip64Extra,
                'centralExtra' => '',
            ],
        ])));
    },

    'bounds zip part reads before exposing oversized package media bytes' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>bounded document</w:p></w:body></w:document>';
        $mediaBytes = str_repeat("review media bytes\n", 12);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => $mediaBytes,
                'method' => 8,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));

        $t->same($documentXml, $package->readBounded('/word/document.xml', strlen($documentXml)));
        $t->same($mediaBytes, $package->read('/word/media/review.bin', strlen($mediaBytes)));
        $t->same('', $package->readBounded('/word/media/', 0));
        $t->throws(\RuntimeException::class, static fn (): string => $package->readBounded('/word/document.xml', strlen($documentXml) - 1));
        $t->throws(\RuntimeException::class, static fn (): string => $package->read('/word/media/review.bin', 32));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $package->readBounded('/word/document.xml', -1));
    },

    'preflights aggregate zip package expansion before exposing media bytes' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>aggregate package preflight</w:p></w:body></w:document>';
        $mediaBytes = str_repeat("review media bytes\n", 24);
        $storedBytes = "stored reviewer note\n";

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => $mediaBytes,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $storedBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));
        $documentCompressed = strlen(gzdeflate($documentXml));
        $mediaCompressed = strlen(gzdeflate($mediaBytes));
        $storedCompressed = strlen($storedBytes);
        $totalCompressed = $documentCompressed + $mediaCompressed + $storedCompressed;
        $totalUncompressed = strlen($documentXml) + strlen($mediaBytes) + strlen($storedBytes);
        $summary = $package->sizePreflight();

        $t->same(4, $summary['entryCount']);
        $t->same(3, $summary['fileCount']);
        $t->same(1, $summary['directoryCount']);
        $t->same(2, $summary['deflatedEntryCount']);
        $t->same(2, $summary['storedEntryCount']);
        $t->same(0, $summary['unsupportedCompressionMethodCount']);
        $t->same($totalCompressed, $summary['compressedBytes']);
        $t->same($totalUncompressed, $summary['uncompressedBytes']);
        $t->same($totalUncompressed / $totalCompressed, $summary['expansionRatio']);
        $t->same('word/media/review.bin', $summary['largestEntry']['name']);
        $t->same(strlen($mediaBytes), $summary['largestEntry']['uncompressedSize']);
        $t->same($mediaCompressed, $summary['largestEntry']['compressedSize']);
        $t->same($mediaCompressed === 0 ? null : strlen($mediaBytes) / $mediaCompressed, $summary['largestEntry']['expansionRatio']);
        $t->same('word/media/review.bin', $summary['entries'][1]['name']);
        $t->same(strlen($mediaBytes), $summary['entries'][1]['uncompressedSize']);
        $t->same($summary, $package->assertSizePreflight($totalUncompressed, $summary['expansionRatio']));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertSizePreflight($totalUncompressed - 1, null));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertSizePreflight(null, $summary['expansionRatio'] - 0.01));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $package->assertSizePreflight(-1, null));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $package->assertSizePreflight(null, -0.01));

        $zeroCompressed = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/zero-compressed.bin',
                'data' => '',
                'method' => 12,
                'centralCompressedSize' => 0,
                'centralUncompressedSize' => 37,
                'localCompressedSize' => 0,
                'localUncompressedSize' => 37,
            ],
        ]));
        $t->same(null, $zeroCompressed->sizePreflight()['expansionRatio']);
        $t->throws(\RuntimeException::class, static fn (): array => $zeroCompressed->assertSizePreflight(null, 10.0));
    },

    'preflights readable zip entry payloads before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage, $corruptZipEntryPayload, $crc32): void {
        $documentXml = '<w:document><w:body><w:p>integrity preflight</w:p></w:body></w:document>';
        $mediaBytes = "review media payload bytes\n";
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $mediaBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));
        $summary = $package->readIntegrityPreflight(1024);

        $t->same(3, $summary['entryCount']);
        $t->same(3, $summary['readableEntryCount']);
        $t->same(0, $summary['failedEntryCount']);
        $t->same(1024, $summary['maxEntryUncompressedBytes']);
        $t->same([], $summary['failedEntries']);
        $t->same('word/document.xml', $summary['entries'][0]['name']);
        $t->same(true, $summary['entries'][0]['isReadable']);
        $t->same(strlen($documentXml), $summary['entries'][0]['bytesRead']);
        $t->same($crc32($documentXml), $summary['entries'][0]['crc32']);
        $t->same(sprintf('%08x', $crc32($documentXml)), $summary['entries'][0]['crc32Hex']);
        $t->same('word/media/', $summary['entries'][2]['name']);
        $t->same(true, $summary['entries'][2]['isDirectory']);
        $t->same(0, $summary['entries'][2]['bytesRead']);
        $t->same($summary, $package->assertReadableEntries(1024));

        $corruptPackage = ZipPackage::fromString($corruptZipEntryPayload($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $mediaBytes,
                'method' => 0,
            ],
        ]), 'word/document.xml'));
        $corruptSummary = $corruptPackage->readIntegrityPreflight();

        $t->same(2, $corruptSummary['entryCount']);
        $t->same(1, $corruptSummary['readableEntryCount']);
        $t->same(1, $corruptSummary['failedEntryCount']);
        $t->same(null, $corruptSummary['maxEntryUncompressedBytes']);
        $t->same('word/document.xml', $corruptSummary['failedEntries'][0]['name']);
        $t->same(false, $corruptSummary['entries'][0]['isReadable']);
        $t->same(null, $corruptSummary['entries'][0]['bytesRead']);
        $t->contains('ZIP entry word/document.xml', $corruptSummary['entries'][0]['error']);
        $t->same(true, $corruptSummary['entries'][1]['isReadable']);
        $t->throws(\RuntimeException::class, static fn (): array => $corruptPackage->assertReadableEntries());
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
        $extraFieldData = pack('CCv', ord('W'), ord('P'), strlen('review:v1')) . 'review:v1';
        $gzip = GzipStream::build($package->bytes(), [
            'modifiedAt' => 1780479017,
            'extraFlags' => 2,
            'operatingSystem' => 3,
            'extraFieldData' => $extraFieldData,
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
        $t->same($extraFieldData, $members[0]['extraFieldData']);
        $t->same('WP', $members[0]['extraFields'][0]['identifier']);
        $t->same('review:v1', $members[0]['extraFields'][0]['data']);
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
