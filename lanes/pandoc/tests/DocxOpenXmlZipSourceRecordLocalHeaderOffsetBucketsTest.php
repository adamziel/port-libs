<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by local header offset bucket' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_local_header_offset_bucket_fixture_parts(),
            'docx source local header offset review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $buckets = docx_zip_source_record_local_header_offset_bucket_index_by(
            $summary['partZipSourceRecordLocalHeaderOffsetBucketSummaries'],
            'localHeaderOffsetBucket'
        );
        $identityEntries = docx_zip_source_record_local_header_offset_bucket_index_by(
            $identity['packageEntries'],
            'partName'
        );
        $repeatIdentity = (new DocxOpenXmlReader())
            ->readZipPackage(ZipPackage::fromParts(
                docx_zip_source_record_local_header_offset_bucket_fixture_parts(),
                'docx source local header offset review'
            ))
            ->attr('docx')['packageIdentity'];
        $changedIdentity = (new DocxOpenXmlReader())
            ->readZipPackage(ZipPackage::fromParts(
                docx_zip_source_record_local_header_offset_bucket_fixture_parts(true),
                'docx source local header offset review'
            ))
            ->attr('docx')['packageIdentity'];

        $t->same('ZIP local header offset buckets.', $document->children[0]->attr('text'));

        $expectedCounts = docx_zip_source_record_local_header_offset_bucket_counts($inventory);
        $t->same(
            ['start-of-archive', '1-to-255-bytes', '256-to-1023-bytes', '1024-plus-bytes'],
            $summary['partZipSourceRecordLocalHeaderOffsetBuckets']
        );
        $t->same(count($expectedCounts), $summary['partZipSourceRecordLocalHeaderOffsetBucketSummaryCount']);
        $t->same($expectedCounts, $summary['partZipSourceRecordLocalHeaderOffsetBucketCounts']);
        $t->same(
            docx_zip_source_record_local_header_offset_bucket_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordLocalHeaderOffsetBucketBytes']
        );
        $t->same(
            docx_zip_source_record_local_header_offset_bucket_sums($inventory, 'compressedByteLength'),
            $summary['partZipSourceRecordLocalHeaderOffsetBucketCompressedByteLengths']
        );
        $t->same(
            docx_zip_source_record_local_header_offset_bucket_sums($inventory, 'bytes'),
            $summary['partZipSourceRecordLocalHeaderOffsetBucketUncompressedByteLengths']
        );
        $t->same(0, $summary['partZipSourceRecordLocalHeaderOffsetBucketUnknownPartCount']);
        $t->same(0, $summary['partZipSourceRecordLocalHeaderOffsetBucketDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordLocalHeaderOffsetBucketIssuePartCount']);
        $t->same(
            $summary['partZipSourceRecordPartCount'],
            array_sum($summary['partZipSourceRecordLocalHeaderOffsetBucketCounts'])
        );

        $t->same($identity, $document->attr('docx')['packageIdentity']);
        $t->same(
            $summary['partZipSourceRecordLocalHeaderOffsetBucketSummaryCount'],
            $identity['partZipSourceRecordLocalHeaderOffsetBucketSummaryCount']
        );
        $t->same(
            $summary['partZipSourceRecordLocalHeaderOffsetBuckets'],
            $identity['partZipSourceRecordLocalHeaderOffsetBuckets']
        );
        $t->same(
            $summary['partZipSourceRecordLocalHeaderOffsetBucketCounts'],
            $identity['partZipSourceRecordLocalHeaderOffsetBucketCounts']
        );
        $t->same(
            $summary['partZipSourceRecordLocalHeaderOffsetBucketBytes'],
            $identity['partZipSourceRecordLocalHeaderOffsetBucketBytes']
        );
        $t->same(
            $summary['partZipSourceRecordLocalHeaderOffsetBucketCompressedByteLengths'],
            $identity['partZipSourceRecordLocalHeaderOffsetBucketCompressedByteLengths']
        );
        $t->same(
            $summary['partZipSourceRecordLocalHeaderOffsetBucketUncompressedByteLengths'],
            $identity['partZipSourceRecordLocalHeaderOffsetBucketUncompressedByteLengths']
        );
        $t->same(
            $summary['partZipSourceRecordLocalHeaderOffsetBucketUnknownPartCount'],
            $identity['partZipSourceRecordLocalHeaderOffsetBucketUnknownPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordLocalHeaderOffsetBucketDataDescriptorPartCount'],
            $identity['partZipSourceRecordLocalHeaderOffsetBucketDataDescriptorPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordLocalHeaderOffsetBucketIssuePartCount'],
            $identity['partZipSourceRecordLocalHeaderOffsetBucketIssuePartCount']
        );
        $t->same(
            $summary['partZipSourceRecordLocalHeaderOffsetBucketSummaries'],
            $identity['partZipSourceRecordLocalHeaderOffsetBucketSummaries']
        );
        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->true(
            $identity['identitySha256'] !== $changedIdentity['identitySha256'],
            'package identity must include ZIP source-record local header offset buckets'
        );

        $t->same(['a.bin'], $buckets['start-of-archive']['partNames']);
        $t->same(['[Content_Types].xml'], $buckets['1-to-255-bytes']['partNames']);
        $t->same(['_rels/.rels', 'customXml/pad.bin'], $buckets['256-to-1023-bytes']['partNames']);
        $t->same(['word/document.xml'], $buckets['1024-plus-bytes']['partNames']);
        $t->same(0, $buckets['start-of-archive']['minLocalHeaderOffset']);
        $t->same(0, $buckets['start-of-archive']['maxLocalHeaderOffset']);
        $t->same(1, $buckets['1-to-255-bytes']['minLocalHeaderOffset']);
        $t->same(255, $buckets['1-to-255-bytes']['maxLocalHeaderOffset']);
        $t->same(256, $buckets['256-to-1023-bytes']['minLocalHeaderOffset']);
        $t->same(1023, $buckets['256-to-1023-bytes']['maxLocalHeaderOffset']);
        $t->same(1024, $buckets['1024-plus-bytes']['minLocalHeaderOffset']);
        $t->same(null, $buckets['1024-plus-bytes']['maxLocalHeaderOffset']);
        $t->same('a.bin', $buckets['start-of-archive']['lowestLocalHeaderOffsetPart']['partName']);
        $t->same(0, $buckets['start-of-archive']['lowestLocalHeaderOffsetPart']['localHeaderOffset']);
        $t->same('word/document.xml', $buckets['1024-plus-bytes']['highestLocalHeaderOffsetPart']['partName']);
        $t->same(false, array_key_exists('contents', $buckets['1024-plus-bytes']['largestSourceRecordPart']));

        $t->same('start-of-archive', $inventory['a.bin']['zipSourceRecordLocalHeaderOffsetBucket']);
        $t->same('1-to-255-bytes', $inventory['[Content_Types].xml']['zipSourceRecordLocalHeaderOffsetBucket']);
        $t->same('256-to-1023-bytes', $inventory['_rels/.rels']['zipSourceRecordLocalHeaderOffsetBucket']);
        $t->same('256-to-1023-bytes', $inventory['customXml/pad.bin']['zipSourceRecordLocalHeaderOffsetBucket']);
        $t->same('1024-plus-bytes', $inventory['word/document.xml']['zipSourceRecordLocalHeaderOffsetBucket']);
        $t->same('start-of-archive', $identityEntries['a.bin']['zipSourceRecordLocalHeaderOffsetBucket']);
        $t->same('1-to-255-bytes', $identityEntries['[Content_Types].xml']['zipSourceRecordLocalHeaderOffsetBucket']);
        $t->same('256-to-1023-bytes', $identityEntries['_rels/.rels']['zipSourceRecordLocalHeaderOffsetBucket']);
        $t->same('256-to-1023-bytes', $identityEntries['customXml/pad.bin']['zipSourceRecordLocalHeaderOffsetBucket']);
        $t->same('1024-plus-bytes', $identityEntries['word/document.xml']['zipSourceRecordLocalHeaderOffsetBucket']);
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_zip_source_record_local_header_offset_bucket_fixture_parts(bool $padFirst = false): array
{
    $pad = ['name' => 'customXml/pad.bin', 'compressionMethod' => 0, 'data' => str_repeat('P', 1400)];
    $parts = [
        ['name' => 'a.bin', 'compressionMethod' => 0, 'data' => 'A'],
        ['name' => '[Content_Types].xml', 'compressionMethod' => 0, 'data' => docx_zip_source_record_local_header_offset_bucket_content_types_xml()],
        ['name' => '_rels/.rels', 'compressionMethod' => 0, 'data' => docx_zip_source_record_local_header_offset_bucket_root_relationships_xml()],
        $pad,
        ['name' => 'word/document.xml', 'compressionMethod' => 0, 'data' => docx_zip_source_record_local_header_offset_bucket_document_xml()],
    ];

    if (!$padFirst) {
        return $parts;
    }

    return [
        $pad,
        ['name' => 'a.bin', 'compressionMethod' => 0, 'data' => 'A'],
        ['name' => '[Content_Types].xml', 'compressionMethod' => 0, 'data' => docx_zip_source_record_local_header_offset_bucket_content_types_xml()],
        ['name' => '_rels/.rels', 'compressionMethod' => 0, 'data' => docx_zip_source_record_local_header_offset_bucket_root_relationships_xml()],
        ['name' => 'word/document.xml', 'compressionMethod' => 0, 'data' => docx_zip_source_record_local_header_offset_bucket_document_xml()],
    ];
}

function docx_zip_source_record_local_header_offset_bucket_content_types_xml(): string
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

function docx_zip_source_record_local_header_offset_bucket_root_relationships_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
}

function docx_zip_source_record_local_header_offset_bucket_document_xml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>ZIP local header offset buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;
}

/**
 * @param list<array<string, mixed>> $entries
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_local_header_offset_bucket_index_by(array $entries, string $field): array
{
    $indexed = [];
    foreach ($entries as $entry) {
        if (is_array($entry) && (is_string($entry[$field] ?? null) || is_int($entry[$field] ?? null))) {
            $indexed[(string) $entry[$field]] = $entry;
        }
    }

    return $indexed;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_local_header_offset_bucket_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $bucket = is_string($part['zipSourceRecordLocalHeaderOffsetBucket'] ?? null)
            ? $part['zipSourceRecordLocalHeaderOffsetBucket']
            : 'unknown';
        $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
    }
    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_local_header_offset_bucket_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $bucket = is_string($part['zipSourceRecordLocalHeaderOffsetBucket'] ?? null)
            ? $part['zipSourceRecordLocalHeaderOffsetBucket']
            : 'unknown';
        $sums[$bucket] = ($sums[$bucket] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }
    ksort($sums, SORT_STRING);

    return $sums;
}
