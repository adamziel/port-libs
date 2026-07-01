<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by compression method' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_compression_method_fixture_parts(),
            'docx source compression review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $methods = docx_zip_source_record_compression_method_index_by(
            $summary['partZipSourceRecordCompressionMethods'],
            'compressionMethodKey'
        );
        $expectedCounts = docx_zip_source_record_compression_method_counts($inventory);

        $t->same('Source compression buckets.', $document->children[0]->attr('text'));
        $t->same(count($expectedCounts), $summary['partZipSourceRecordCompressionMethodCount']);
        $t->same($expectedCounts, $summary['partZipSourceRecordCompressionMethodCounts']);
        $t->same(
            docx_zip_source_record_compression_method_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordCompressionMethodBytes']
        );
        $t->same(0, $summary['partZipSourceRecordCompressionMethodDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordCompressionMethodIssuePartCount']);
        $t->same(0, $summary['partZipSourceRecordCompressionMethodUnsupportedPartCount']);
        $t->same($summary['partZipSourceRecordPartCount'], array_sum($summary['partZipSourceRecordCompressionMethodCounts']));

        $stored = $methods['0'];
        $t->same(0, $stored['compressionMethod']);
        $t->same('stored', $stored['compressionMethodName']);
        $t->same(4, $stored['partCount']);
        $t->same(4, $stored['supportedPartCount']);
        $t->same(0, $stored['unsupportedPartCount']);
        $t->same([
            '[Content_Types].xml',
            'customXml/untyped-source.bin',
            'word/embeddings/review.xlsx',
            'word/media/review.png',
        ], $stored['partNames']);
        $t->same(['/' => 1, 'customXml/' => 1, 'word/' => 2], $stored['directoryRootCounts']);
        $t->same(['default' => 2, 'missing' => 1, 'override' => 1], $stored['contentTypeSourceCounts']);
        $t->same(
            docx_zip_source_record_compression_method_sum_for_key($inventory, '0', 'localRecordBytes'),
            $stored['localRecordBytes']
        );
        $t->same(
            docx_zip_source_record_compression_method_sum_for_key($inventory, '0', 'centralDirectoryRecordBytes'),
            $stored['centralDirectoryRecordBytes']
        );
        $t->same('word/embeddings/review.xlsx', $stored['largestSourceRecordPart']['partName']);
        $t->same(0, $stored['largestSourceRecordPart']['compressionMethod']);
        $t->same(
            $inventory['word/embeddings/review.xlsx']['sourceRecordBytes'],
            $stored['largestSourceRecordPart']['sourceRecordBytes']
        );
        $t->same(false, array_key_exists('contents', $stored['largestSourceRecordPart']));

        $deflated = $methods['8'];
        $t->same(8, $deflated['compressionMethod']);
        $t->same('deflated', $deflated['compressionMethodName']);
        $t->same(4, $deflated['partCount']);
        $t->same([
            '_rels/.rels',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/preview.png',
        ], $deflated['partNames']);
        $t->same(['_rels/' => 1, 'word/' => 3], $deflated['directoryRootCounts']);
        $t->same(['default' => 3, 'override' => 1], $deflated['contentTypeSourceCounts']);
        $t->same(
            ['document-relationship-target' => 1, 'office-document' => 1, 'office-document-relationships' => 1, 'package-relationships' => 1, 'relationship-part' => 2, 'root-relationship-target' => 1],
            $deflated['roleCounts']
        );
        $t->same(
            docx_zip_source_record_compression_method_sum_for_key($inventory, '8', 'compressedDataBytes'),
            $deflated['compressedDataBytes']
        );
        $t->same(
            docx_zip_source_record_compression_method_largest($inventory, '8'),
            $deflated['largestSourceRecordPart']['partName']
        );
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_compression_method_fixture_parts(): array
{
    $embeddedContentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $embeddedPackageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';

    return [
        ['name' => '[Content_Types].xml', 'compressionMethod' => 0, 'data' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/review.xlsx" ContentType="{$embeddedContentType}"/>
</Types>
XML,
        ],
        ['name' => '_rels/.rels', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        ],
        ['name' => 'word/_rels/document.xml.rels', 'compressionMethod' => 8, 'data' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rPreview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/preview.png"/>
  <Relationship Id="rEmbeddedWorkbook" Type="{$embeddedPackageRel}" Target="embeddings/review.xlsx"/>
</Relationships>
XML,
        ],
        ['name' => 'word/document.xml', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Source compression buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/media/review.png',
            'data' => str_repeat('M', 512),
            'compressionMethod' => 0,
            'comment' => 'source compression image',
        ],
        [
            'name' => 'word/media/preview.png',
            'data' => str_repeat('P', 192),
            'compressionMethod' => 8,
        ],
        [
            'name' => 'word/embeddings/review.xlsx',
            'data' => str_repeat('E', 768),
            'compressionMethod' => 0,
        ],
        ['name' => 'customXml/untyped-source.bin', 'data' => 'untyped source-compression payload', 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_compression_method_index_by(array $items, string $key): array
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
function docx_zip_source_record_compression_method_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_compression_method_key($part);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_compression_method_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_compression_method_key($part);
        $sums[$key] = ($sums[$key] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_compression_method_sum_for_key(array $inventory, string $key, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (docx_zip_source_record_compression_method_key($part) !== $key) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_compression_method_largest(array $inventory, string $key): ?string
{
    $largest = null;
    foreach ($inventory as $partName => $part) {
        if (docx_zip_source_record_compression_method_key($part) !== $key) {
            continue;
        }

        $partName = (string) ($part['partName'] ?? $partName);
        $bytes = is_int($part['sourceRecordBytes'] ?? null) ? $part['sourceRecordBytes'] : 0;
        if (
            $largest === null
            || $bytes > $largest['bytes']
            || ($bytes === $largest['bytes'] && strcmp($partName, $largest['partName']) < 0)
        ) {
            $largest = ['partName' => $partName, 'bytes' => $bytes];
        }
    }

    return is_array($largest) ? $largest['partName'] : null;
}

/**
 * @param array<string, mixed> $part
 */
function docx_zip_source_record_compression_method_key(array $part): string
{
    return is_int($part['compressionMethod'] ?? null) ? (string) $part['compressionMethod'] : '(missing)';
}
