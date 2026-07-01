<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Raw ODT package preflight.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$packUInt64 = static function (int $value): string {
    if ($value < 0) {
        throw new RuntimeException('ZIP64 fixture value must be non-negative');
    }

    return pack('VV', $value & 0xffffffff, intdiv($value, 0x100000000));
};

$buildOdtZipBytes = static function () use ($manifestXml, $contentXml): string {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ])->bytes();
};

$buildOdtZipBytesWithCentralDirectoryRatioBuckets = static function () use ($manifestXml, $contentXml): string {
    $parts = [
        ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
        ['name' => 'Pictures/empty.bin', 'data' => '', 'compressionMethod' => 0],
        ['name' => 'Pictures/high.bin', 'data' => str_repeat('H', 70000), 'compressionMethod' => 8],
        [
            'name' => 'Payloads/zero-compressed.bin',
            'data' => '',
            'compressionMethod' => 12,
            'centralCompressedSize' => 0,
            'centralUncompressedSize' => 37,
            'localCompressedSize' => 0,
            'localUncompressedSize' => 37,
            'crc32' => 0,
        ],
    ];
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $central = '';

    foreach ($parts as $part) {
        $name = $part['name'];
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? 0;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if (!is_string($compressed)) {
            throw new RuntimeException("Unable to deflate ZIP entry {$name}");
        }

        $localCompressedSize = $part['localCompressedSize'] ?? strlen($compressed);
        $localUncompressedSize = $part['localUncompressedSize'] ?? strlen($data);
        $centralCompressedSize = $part['centralCompressedSize'] ?? strlen($compressed);
        $centralUncompressedSize = $part['centralUncompressedSize'] ?? strlen($data);
        $crc = $part['crc32'] ?? $crc32($data);
        $offset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($name),
            0
        ) . $name . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc,
            $centralCompressedSize,
            $centralUncompressedSize,
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $offset
        ) . $name;
    }

    $centralOffset = strlen($body);
    $entryCount = count($parts);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($central), $centralOffset, 0);
};

$buildOdtZipBytesWithCentralDirectoryReviewFields = static function () use ($manifestXml, $contentXml): string {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
        [
            'name' => 'META-INF/manifest.xml',
            'data' => $manifestXml,
            'compressionMethod' => 0,
            'comment' => 'manifest-audit',
        ],
        [
            'name' => 'content.xml',
            'data' => $contentXml,
            'compressionMethod' => 0,
            'extraFieldData' => pack('vva*', 0xcafe, strlen('odf'), 'odf'),
        ],
        [
            'name' => 'Pictures/hero.png',
            'data' => 'PNGDATA',
            'compressionMethod' => 0,
            'comment' => 'image-review-notes',
        ],
    ])->bytes();
};

$addZip64EndOfCentralDirectory = static function (string $zip) use ($packUInt64): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('EOCD fixture not found');
    }

    $diskEntryCount = unpack('vvalue', substr($zip, $eocdOffset + 8, 2))['value'];
    $totalEntryCount = unpack('vvalue', substr($zip, $eocdOffset + 10, 2))['value'];
    $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4))['value'];
    $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
    $zip64EocdOffset = $eocdOffset;
    $zip64Eocd = "PK\x06\x06"
        . $packUInt64(44)
        . pack('vvVV', 45, 45, 0, 0)
        . $packUInt64((int) $diskEntryCount)
        . $packUInt64((int) $totalEntryCount)
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

return [
    'preflights instantiable ODT raw packages with first mimetype evidence' => static function (TestRunner $t) use ($buildOdtZipBytes): void {
        $summary = OpenDocumentPackage::rawImportPreflight($buildOdtZipBytes());

        $t->same(true, $summary['isValid']);
        $t->same(true, $summary['isOpenDocumentTextPackage']);
        $t->same(true, $summary['canInstantiateZipPackage']);
        $t->same(true, $summary['canInstantiateOpenDocumentPackage']);
        $t->same(null, $summary['zipPackageInstantiationError']);
        $t->same(null, $summary['openDocumentPackageInstantiationError']);
        $t->same('1.3', $summary['manifestVersion']);
        $t->same(2, $summary['manifestEntryCount']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $summary['mimetypeEntry']['mediaType']);
        $t->same(true, $summary['mimetypeEntry']['matchesOpenDocumentText']);
        $t->same([], $summary['mimetypeEntry']['diagnostics']);
        $t->same([], $summary['diagnostics']);
        $t->same('odf-raw-package-import-metadata-only', $summary['byteExposurePolicy']);
        $t->same(false, $summary['canExposeBytes']);
    },

    'summarizes raw ODT central directory expansion ratio buckets before package instantiation' => static function (TestRunner $t) use ($buildOdtZipBytesWithCentralDirectoryRatioBuckets): void {
        $summary = OpenDocumentPackage::rawImportPreflight(
            $buildOdtZipBytesWithCentralDirectoryRatioBuckets(),
            null,
            10.0
        );

        $indexByBucket = [];
        foreach ($summary['rawCentralDirectoryExpansionRatioBucketSummaries'] as $bucketSummary) {
            $indexByBucket[$bucketSummary['expansionRatioBucket']] = $bucketSummary;
        }

        $t->same(4, $summary['rawCentralDirectoryExpansionRatioBucketSummaryCount']);
        $t->same(['zero-byte', 'up-to-1x', 'over-100x', 'unknown'], $summary['rawCentralDirectoryExpansionRatioBuckets']);
        $t->same(6, $summary['rawCentralDirectoryExpansionRatioEntryCount']);
        $t->same(1, $summary['rawCentralDirectoryExpansionRatioUnknownEntryCount']);
        $t->same('odf-raw-central-directory-expansion-ratio-metadata-only', $summary['rawCentralDirectoryExpansionRatioByteExposurePolicy']);
        $t->same(false, $summary['rawCentralDirectoryExpansionRatioCanExposeBytes']);
        $t->same($summary['zipRawStrictImport']['centralDirectorySize']['entries'], array_values($summary['zipRawStrictImport']['centralDirectorySize']['entries']));
        $t->same(['Pictures/empty.bin'], $indexByBucket['zero-byte']['entryNames']);
        $t->same(['mimetype', 'META-INF/manifest.xml', 'content.xml'], $indexByBucket['up-to-1x']['entryNames']);
        $t->same(['Pictures/high.bin'], $indexByBucket['over-100x']['entryNames']);
        $t->same(['Payloads/zero-compressed.bin'], $indexByBucket['unknown']['entryNames']);
        $t->same(['Pictures/'], $indexByBucket['zero-byte']['directoryRoots']);
        $t->same(['/', 'META-INF/'], $indexByBucket['up-to-1x']['directoryRoots']);
        $t->same(['Pictures/'], $indexByBucket['over-100x']['directoryRoots']);
        $t->same(['Payloads/'], $indexByBucket['unknown']['directoryRoots']);
        $t->same(['stored'], $indexByBucket['zero-byte']['compressionMethodNames']);
        $t->same(['deflated'], $indexByBucket['over-100x']['compressionMethodNames']);
        $t->same(['unsupported'], $indexByBucket['unknown']['compressionMethodNames']);
        $t->same(1, $indexByBucket['unknown']['unknownExpansionRatioEntryCount']);
        $t->same(null, $indexByBucket['unknown']['largestExpansionRatio']);
        $t->same('Pictures/high.bin', $indexByBucket['over-100x']['largestExpansionRatioEntryName']);
        $t->true(($indexByBucket['over-100x']['largestExpansionRatio'] ?? 0.0) > 100.0);
        $diagnostics = implode(',', $summary['diagnostics']);
        $t->contains('expansion-ratio-unknown', $diagnostics);
        $t->contains('unsupported-compression-methods', $diagnostics);
        $encodedSummary = json_encode($summary['rawCentralDirectoryExpansionRatioBucketSummaries'], JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedSummary, str_repeat('H', 32)));
    },

    'summarizes raw ODT central directory name byte length buckets before package instantiation' => static function (TestRunner $t) use ($buildOdtZipBytesWithCentralDirectoryRatioBuckets): void {
        $summary = OpenDocumentPackage::rawImportPreflight(
            $buildOdtZipBytesWithCentralDirectoryRatioBuckets()
        );

        $indexByBucket = [];
        foreach ($summary['rawCentralDirectoryNameByteLengthBucketSummaries'] as $bucketSummary) {
            $indexByBucket[$bucketSummary['rawCentralDirectoryNameByteLengthBucket']] = $bucketSummary;
        }

        $expectedNames = [
            'mimetype',
            'META-INF/manifest.xml',
            'content.xml',
            'Pictures/empty.bin',
            'Pictures/high.bin',
            'Payloads/zero-compressed.bin',
        ];
        $expectedRawNameBytes = array_sum(array_map('strlen', $expectedNames));

        $t->same(3, $summary['rawCentralDirectoryNameByteLengthBucketSummaryCount']);
        $t->same(['up-to-8-bytes', '9-to-16-bytes', '17-to-32-bytes'], $summary['rawCentralDirectoryNameByteLengthBuckets']);
        $t->same([
            'up-to-8-bytes' => 1,
            '9-to-16-bytes' => 1,
            '17-to-32-bytes' => 4,
        ], $summary['rawCentralDirectoryNameByteLengthBucketCounts']);
        $t->same(6, $summary['rawCentralDirectoryNameByteLengthEntryCount']);
        $t->same($expectedRawNameBytes, $summary['rawCentralDirectoryNameByteLengthRawBytes']);
        $t->same($expectedRawNameBytes, $summary['rawCentralDirectoryNameByteLengthDecodedBytes']);
        $t->same(0, $summary['rawCentralDirectoryNameByteLengthDecodedNameDiffersFromRawNameCount']);
        $t->same('odf-raw-central-directory-name-byte-length-metadata-only', $summary['rawCentralDirectoryNameByteLengthByteExposurePolicy']);
        $t->same(false, $summary['rawCentralDirectoryNameByteLengthCanExposeBytes']);

        $t->same(['mimetype'], $indexByBucket['up-to-8-bytes']['entryNames']);
        $t->same(['content.xml'], $indexByBucket['9-to-16-bytes']['entryNames']);
        $t->same([
            'META-INF/manifest.xml',
            'Payloads/zero-compressed.bin',
            'Pictures/empty.bin',
            'Pictures/high.bin',
        ], $indexByBucket['17-to-32-bytes']['entryNames']);
        $t->same(8, $indexByBucket['up-to-8-bytes']['rawNameBytes']);
        $t->same(strlen('content.xml'), $indexByBucket['9-to-16-bytes']['rawNameBytes']);
        $t->same(
            strlen('META-INF/manifest.xml')
                + strlen('Payloads/zero-compressed.bin')
                + strlen('Pictures/empty.bin')
                + strlen('Pictures/high.bin'),
            $indexByBucket['17-to-32-bytes']['rawNameBytes']
        );
        $t->same(0, $indexByBucket['17-to-32-bytes']['decodedNameDiffersFromRawNameCount']);
        $t->same(['/'], $indexByBucket['9-to-16-bytes']['directoryRoots']);
        $t->same(['META-INF/', 'Payloads/', 'Pictures/'], $indexByBucket['17-to-32-bytes']['directoryRoots']);
        $t->same(['stored'], $indexByBucket['up-to-8-bytes']['compressionMethodNames']);
        $t->same(['stored'], $indexByBucket['9-to-16-bytes']['compressionMethodNames']);
        $t->same(['deflated', 'stored', 'unsupported'], $indexByBucket['17-to-32-bytes']['compressionMethodNames']);
        $t->same('Payloads/zero-compressed.bin', $indexByBucket['17-to-32-bytes']['longestRawNameEntryName']);
        $t->same(strlen('Payloads/zero-compressed.bin'), $indexByBucket['17-to-32-bytes']['longestRawNameByteLength']);
        $encodedSummary = json_encode($summary['rawCentralDirectoryNameByteLengthBucketSummaries'], JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedSummary, str_repeat('H', 32)));
    },

    'summarizes raw ODT central directory review field byte length buckets before package instantiation' => static function (TestRunner $t) use ($buildOdtZipBytesWithCentralDirectoryReviewFields): void {
        $summary = OpenDocumentPackage::rawImportPreflight(
            $buildOdtZipBytesWithCentralDirectoryReviewFields()
        );

        $indexByBucket = [];
        foreach ($summary['rawCentralDirectoryReviewFieldByteLengthBucketSummaries'] as $bucketSummary) {
            $indexByBucket[$bucketSummary['rawCentralDirectoryReviewFieldByteLengthBucket']] = $bucketSummary;
        }

        $contentExtraBytes = strlen(pack('vva*', 0xcafe, strlen('odf'), 'odf'));
        $manifestCommentBytes = strlen('manifest-audit');
        $imageCommentBytes = strlen('image-review-notes');

        $t->same(4, $summary['rawCentralDirectoryReviewFieldByteLengthBucketSummaryCount']);
        $t->same([
            'none',
            'up-to-8-bytes',
            '9-to-16-bytes',
            '17-to-32-bytes',
        ], $summary['rawCentralDirectoryReviewFieldByteLengthBuckets']);
        $t->same([
            'none' => 1,
            'up-to-8-bytes' => 1,
            '9-to-16-bytes' => 1,
            '17-to-32-bytes' => 1,
        ], $summary['rawCentralDirectoryReviewFieldByteLengthBucketCounts']);
        $t->same(4, $summary['rawCentralDirectoryReviewFieldByteLengthEntryCount']);
        $t->same(3, $summary['rawCentralDirectoryReviewFieldByteLengthReviewEntryCount']);
        $t->same(
            $contentExtraBytes + $manifestCommentBytes + $imageCommentBytes,
            $summary['rawCentralDirectoryReviewFieldByteLengthReviewBytes']
        );
        $t->same($contentExtraBytes, $summary['rawCentralDirectoryReviewFieldByteLengthExtraFieldBytes']);
        $t->same(
            $manifestCommentBytes + $imageCommentBytes,
            $summary['rawCentralDirectoryReviewFieldByteLengthCommentBytes']
        );
        $t->same(1, $summary['rawCentralDirectoryReviewFieldByteLengthExtraFieldEntryCount']);
        $t->same(2, $summary['rawCentralDirectoryReviewFieldByteLengthCommentEntryCount']);
        $t->same('odf-raw-central-directory-review-field-byte-length-metadata-only', $summary['rawCentralDirectoryReviewFieldByteLengthByteExposurePolicy']);
        $t->same(false, $summary['rawCentralDirectoryReviewFieldByteLengthCanExposeBytes']);
        $t->same(
            $summary['zipRawStrictImport']['centralDirectoryVariableFields']['centralDirectoryReviewFieldBytes'],
            $summary['rawCentralDirectoryReviewFieldByteLengthReviewBytes']
        );
        $t->same(
            $summary['zipRawStrictImport']['centralDirectoryVariableFields']['centralDirectoryExtraFieldBytes'],
            $summary['rawCentralDirectoryReviewFieldByteLengthExtraFieldBytes']
        );
        $t->same(
            $summary['zipRawStrictImport']['centralDirectoryVariableFields']['centralDirectoryCommentBytes'],
            $summary['rawCentralDirectoryReviewFieldByteLengthCommentBytes']
        );

        $t->same(['mimetype'], $indexByBucket['none']['entryNames']);
        $t->same(['content.xml'], $indexByBucket['up-to-8-bytes']['entryNames']);
        $t->same(['META-INF/manifest.xml'], $indexByBucket['9-to-16-bytes']['entryNames']);
        $t->same(['Pictures/hero.png'], $indexByBucket['17-to-32-bytes']['entryNames']);
        $t->same(['/'], $indexByBucket['none']['directoryRoots']);
        $t->same(['/'], $indexByBucket['up-to-8-bytes']['directoryRoots']);
        $t->same(['META-INF/'], $indexByBucket['9-to-16-bytes']['directoryRoots']);
        $t->same(['Pictures/'], $indexByBucket['17-to-32-bytes']['directoryRoots']);
        $t->same(0, $indexByBucket['none']['reviewFieldBytes']);
        $t->same($contentExtraBytes, $indexByBucket['up-to-8-bytes']['centralExtraFieldBytes']);
        $t->same($manifestCommentBytes, $indexByBucket['9-to-16-bytes']['rawCommentBytes']);
        $t->same($imageCommentBytes, $indexByBucket['17-to-32-bytes']['rawCommentBytes']);
        $t->same('Pictures/hero.png', $indexByBucket['17-to-32-bytes']['longestReviewFieldEntryName']);
        $t->same($imageCommentBytes, $indexByBucket['17-to-32-bytes']['longestReviewFieldByteLength']);

        $encodedSummary = json_encode($summary['rawCentralDirectoryReviewFieldByteLengthBucketSummaries'], JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedSummary, 'manifest-audit'));
        $t->true(!str_contains($encodedSummary, 'image-review-notes'));
    },

    'preflights ZIP64 EOCD ODT packages before bounded package instantiation' => static function (TestRunner $t) use ($buildOdtZipBytes, $addZip64EndOfCentralDirectory): void {
        $summary = OpenDocumentPackage::rawImportPreflight(
            $addZip64EndOfCentralDirectory($buildOdtZipBytes())
        );

        $t->same(false, $summary['isValid']);
        $t->same(true, $summary['isOpenDocumentTextPackage']);
        $t->same(false, $summary['canInstantiateZipPackage']);
        $t->same(false, $summary['canInstantiateOpenDocumentPackage']);
        $t->contains('ZIP64 end-of-central-directory records are not supported', (string) $summary['zipPackageInstantiationError']);
        $t->contains('ZIP64 end-of-central-directory records are not supported', (string) $summary['openDocumentPackageInstantiationError']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $summary['mimetypeEntry']['mediaType']);
        $t->same(true, $summary['mimetypeEntry']['matchesOpenDocumentText']);
        $t->same([], $summary['mimetypeEntry']['diagnostics']);
        $t->same(true, $summary['requiresZip64']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectoryLocator']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectory']);
        $t->same(['zip64-end-of-central-directory'], $summary['zip64EndOfCentralDirectoryIssueCodes']);
        $t->same(3, $summary['zip64EndOfCentralDirectory']['totalEntryCount']);
        $t->same(6, $summary['zip64EndOfCentralDirectory']['eocdZip64ResolutionFieldCount']);
        $t->same(4, $summary['zip64EndOfCentralDirectory']['eocdZip64SentinelFieldCount']);
        $t->same(4, $summary['zip64EndOfCentralDirectory']['eocdZip64ResolvedFieldCount']);
        $t->same([
            'diskEntryCount',
            'totalEntryCount',
            'centralDirectorySize',
            'centralDirectoryOffset',
        ], $summary['zip64EndOfCentralDirectory']['eocdZip64ResolvedFields']);
        $diagnostics = implode(',', $summary['diagnostics']);
        $t->contains('zip64-end-of-central-directory', $diagnostics);
        $t->contains('zip-package-instantiation-failed', $diagnostics);
        $t->contains('odf-package-instantiation-failed', $diagnostics);
        $t->same(false, $summary['zipRawStrictImport']['canInstantiate']);
        $t->same('odf-raw-package-import-metadata-only', $summary['byteExposurePolicy']);
        $t->same(false, $summary['canExposeBytes']);
    },
];
