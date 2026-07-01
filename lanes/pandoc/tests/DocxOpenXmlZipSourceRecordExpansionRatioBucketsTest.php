<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by expansion ratio bucket' => static function (TestRunner $t): void {
        $unknownName = 'customXml/unsupported-ratio.bin';
        $document = (new DocxOpenXmlReader())->readZipPackage(
            docx_zip_source_record_expansion_ratio_bucket_fixture_package($unknownName)
        );
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $zipPackage = $package['zipPackage'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $manifest = $zipPackage['packageManifest'];
        $manifestBuckets = docx_zip_source_record_expansion_ratio_bucket_index_by(
            $manifest['expansionRatioBucketSummaries'],
            'expansionRatioBucket'
        );
        $partBuckets = docx_zip_source_record_expansion_ratio_bucket_index_by(
            $summary['partZipSourceRecordExpansionRatioBucketSummaries'],
            'expansionRatioBucket'
        );
        $identityEntries = docx_zip_source_record_expansion_ratio_bucket_index_by(
            $identity['packageEntries'],
            'partName'
        );

        $t->same('ZIP expansion ratio buckets.', $document->children[0]->attr('text'));
        $t->same(['zero-byte', 'up-to-1x', 'over-100x', 'unknown'], $manifest['expansionRatioBuckets']);
        $t->same(4, $manifest['expansionRatioBucketSummaryCount']);
        $t->same($manifest['expansionRatioBucketSummaryCount'], $zipPackage['packageManifestExpansionRatioBucketSummaryCount']);
        $t->same($manifest['expansionRatioBuckets'], $zipPackage['packageManifestExpansionRatioBuckets']);
        $t->same($manifest['expansionRatioBucketSummaries'], $zipPackage['packageManifestExpansionRatioBucketSummaries']);
        $t->same($manifest['expansionRatioBucketSummaryCount'], $summary['zipPackageManifestExpansionRatioBucketSummaryCount']);
        $t->same($manifest['expansionRatioBuckets'], $summary['zipPackageManifestExpansionRatioBuckets']);
        $t->same($manifest['expansionRatioBucketSummaries'], $summary['zipPackageManifestExpansionRatioBucketSummaries']);
        $t->same([$unknownName], $manifestBuckets['unknown']['entryNames']);
        $t->same(1, $manifestBuckets['unknown']['unknownExpansionRatioEntryCount']);
        $t->same(null, $manifestBuckets['unknown']['largestExpansionRatio']);
        $t->same('unknown', $zipPackage['byPackagePath'][$unknownName]['expansionRatioBucket']);
        $t->same(false, isset($inventory[$unknownName]));

        $expectedCounts = docx_zip_source_record_expansion_ratio_bucket_counts($inventory);
        $expectedSourceBytes = docx_zip_source_record_expansion_ratio_bucket_sums($inventory, 'sourceRecordBytes');
        $expectedCompressedBytes = docx_zip_source_record_expansion_ratio_bucket_sums($inventory, 'compressedByteLength');
        $expectedUncompressedBytes = docx_zip_source_record_expansion_ratio_bucket_sums($inventory, 'bytes');

        $t->same(['zero-byte', 'up-to-1x', 'over-100x'], $summary['partZipSourceRecordExpansionRatioBuckets']);
        $t->same(count($expectedCounts), $summary['partZipSourceRecordExpansionRatioBucketSummaryCount']);
        $t->same($expectedCounts, $summary['partZipSourceRecordExpansionRatioBucketCounts']);
        $t->same($expectedSourceBytes, $summary['partZipSourceRecordExpansionRatioBucketBytes']);
        $t->same(
            $expectedCompressedBytes,
            $summary['partZipSourceRecordExpansionRatioBucketCompressedByteLengths']
        );
        $t->same(
            $expectedUncompressedBytes,
            $summary['partZipSourceRecordExpansionRatioBucketUncompressedByteLengths']
        );
        $t->same(0, $summary['partZipSourceRecordExpansionRatioBucketUnknownPartCount']);
        $t->same(0, $summary['partZipSourceRecordExpansionRatioBucketDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordExpansionRatioBucketIssuePartCount']);
        $t->same($summary['partZipSourceRecordPartCount'], array_sum($summary['partZipSourceRecordExpansionRatioBucketCounts']));

        $t->same($identity, $document->attr('docx')['packageIdentity']);
        $t->same(
            $summary['partZipSourceRecordExpansionRatioBucketSummaryCount'],
            $identity['partZipSourceRecordExpansionRatioBucketSummaryCount']
        );
        $t->same(
            $summary['partZipSourceRecordExpansionRatioBuckets'],
            $identity['partZipSourceRecordExpansionRatioBuckets']
        );
        $t->same(
            $summary['partZipSourceRecordExpansionRatioBucketCounts'],
            $identity['partZipSourceRecordExpansionRatioBucketCounts']
        );
        $t->same(
            $summary['partZipSourceRecordExpansionRatioBucketBytes'],
            $identity['partZipSourceRecordExpansionRatioBucketBytes']
        );
        $t->same(
            $summary['partZipSourceRecordExpansionRatioBucketCompressedByteLengths'],
            $identity['partZipSourceRecordExpansionRatioBucketCompressedByteLengths']
        );
        $t->same(
            $summary['partZipSourceRecordExpansionRatioBucketUncompressedByteLengths'],
            $identity['partZipSourceRecordExpansionRatioBucketUncompressedByteLengths']
        );
        $t->same(
            $summary['partZipSourceRecordExpansionRatioBucketUnknownPartCount'],
            $identity['partZipSourceRecordExpansionRatioBucketUnknownPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordExpansionRatioBucketDataDescriptorPartCount'],
            $identity['partZipSourceRecordExpansionRatioBucketDataDescriptorPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordExpansionRatioBucketIssuePartCount'],
            $identity['partZipSourceRecordExpansionRatioBucketIssuePartCount']
        );
        $t->same(
            $summary['partZipSourceRecordExpansionRatioBucketSummaries'],
            $identity['partZipSourceRecordExpansionRatioBucketSummaries']
        );

        $t->same(['customXml/empty.bin'], $partBuckets['zero-byte']['partNames']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'customXml/stored.bin',
            'word/_rels/document.xml.rels',
            'word/document.xml',
        ], $partBuckets['up-to-1x']['partNames']);
        $t->same(['word/media/high.bin'], $partBuckets['over-100x']['partNames']);
        $t->same(0.0, $partBuckets['zero-byte']['minExpansionRatio']);
        $t->same(0.0, $partBuckets['zero-byte']['maxExpansionRatio']);
        $t->same(0.0, $partBuckets['up-to-1x']['minExpansionRatio']);
        $t->same(1.0, $partBuckets['up-to-1x']['maxExpansionRatio']);
        $t->same(100.0, $partBuckets['over-100x']['minExpansionRatio']);
        $t->same(null, $partBuckets['over-100x']['maxExpansionRatio']);
        $t->true(($partBuckets['over-100x']['largestExpansionRatioPart']['expansionRatio'] ?? 0.0) > 100.0);
        $t->same('word/media/high.bin', $partBuckets['over-100x']['largestExpansionRatioPart']['partName']);
        $t->same(false, array_key_exists('contents', $partBuckets['over-100x']['largestSourceRecordPart']));

        $t->same('zero-byte', $inventory['customXml/empty.bin']['zipExpansionRatioBucket']);
        $t->same('up-to-1x', $inventory['customXml/stored.bin']['zipExpansionRatioBucket']);
        $t->same('over-100x', $inventory['word/media/high.bin']['zipExpansionRatioBucket']);
        $t->same('zero-byte', $identityEntries['customXml/empty.bin']['zipExpansionRatioBucket']);
        $t->same('up-to-1x', $identityEntries['customXml/stored.bin']['zipExpansionRatioBucket']);
        $t->same('over-100x', $identityEntries['word/media/high.bin']['zipExpansionRatioBucket']);
    },
];

function docx_zip_source_record_expansion_ratio_bucket_fixture_package(string $unknownName): ZipPackage
{
    $parts = [
        ['name' => '[Content_Types].xml', 'data' => docx_zip_source_record_expansion_ratio_bucket_content_types_xml(), 'compressionMethod' => 0],
        ['name' => '_rels/.rels', 'data' => docx_zip_source_record_expansion_ratio_bucket_root_relationships_xml(), 'compressionMethod' => 0],
        ['name' => 'word/_rels/document.xml.rels', 'data' => docx_zip_source_record_expansion_ratio_bucket_document_relationships_xml(), 'compressionMethod' => 0],
        ['name' => 'word/document.xml', 'data' => docx_zip_source_record_expansion_ratio_bucket_document_xml(), 'compressionMethod' => 0],
        ['name' => 'customXml/stored.bin', 'data' => str_repeat('S', 64), 'compressionMethod' => 0],
        ['name' => 'customXml/empty.bin', 'data' => '', 'compressionMethod' => 0],
        ['name' => 'word/media/high.bin', 'data' => str_repeat('H', 70000), 'compressionMethod' => 8],
        [
            'name' => $unknownName,
            'data' => '',
            'compressionMethod' => 12,
            'centralCompressedSize' => 0,
            'centralUncompressedSize' => 37,
            'localData' => '',
            'localCompressedSize' => 0,
            'localUncompressedSize' => 37,
            'crc32' => 0,
        ],
    ];

    return docx_zip_source_record_expansion_ratio_bucket_zip_from_parts($parts);
}

function docx_zip_source_record_expansion_ratio_bucket_content_types_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;
}

function docx_zip_source_record_expansion_ratio_bucket_root_relationships_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
}

function docx_zip_source_record_expansion_ratio_bucket_document_relationships_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rHigh" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/high.bin"/>
</Relationships>
XML;
}

function docx_zip_source_record_expansion_ratio_bucket_document_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>ZIP expansion ratio buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;
}

/**
 * @param list<array<string, mixed>> $parts
 */
function docx_zip_source_record_expansion_ratio_bucket_zip_from_parts(array $parts): ZipPackage
{
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $central = '';

    foreach ($parts as $part) {
        $name = (string) $part['name'];
        $data = is_string($part['data'] ?? null) ? $part['data'] : '';
        $method = is_int($part['compressionMethod'] ?? null) ? $part['compressionMethod'] : 8;
        $flags = is_int($part['generalPurposeFlags'] ?? null) ? $part['generalPurposeFlags'] : 0x0800;
        if ($method === 8) {
            $compressed = gzdeflate($data);
            if (!is_string($compressed)) {
                throw new RuntimeException("Unable to deflate ZIP entry {$name}");
            }
        } else {
            $compressed = is_string($part['compressedData'] ?? null) ? $part['compressedData'] : $data;
        }

        $localData = is_string($part['localData'] ?? null) ? $part['localData'] : $compressed;
        $localCompressedSize = is_int($part['localCompressedSize'] ?? null) ? $part['localCompressedSize'] : strlen($localData);
        $localUncompressedSize = is_int($part['localUncompressedSize'] ?? null) ? $part['localUncompressedSize'] : strlen($data);
        $centralCompressedSize = is_int($part['centralCompressedSize'] ?? null) ? $part['centralCompressedSize'] : strlen($compressed);
        $centralUncompressedSize = is_int($part['centralUncompressedSize'] ?? null) ? $part['centralUncompressedSize'] : strlen($data);
        $crc = is_int($part['crc32'] ?? null) ? $part['crc32'] : $crc32($data);
        $offset = strlen($body);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($name),
            0
        );
        $body .= $name . $localData;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
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

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($central), $centralOffset, 0)
    );
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_expansion_ratio_bucket_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_expansion_ratio_bucket_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $bucket = docx_zip_source_record_expansion_ratio_bucket_for_part($part);
        $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_expansion_ratio_bucket_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $bucket = docx_zip_source_record_expansion_ratio_bucket_for_part($part);
        $sums[$bucket] = ($sums[$bucket] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, mixed> $part
 */
function docx_zip_source_record_expansion_ratio_bucket_for_part(array $part): string
{
    return is_string($part['zipExpansionRatioBucket'] ?? null) ? $part['zipExpansionRatioBucket'] : 'unknown';
}
