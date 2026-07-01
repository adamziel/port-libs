<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by content type declaration source' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_content_type_source_fixture_parts(),
            'docx source content type source review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $sources = docx_zip_source_record_content_type_source_index_by(
            $summary['partZipSourceRecordContentTypeSources'],
            'contentTypeSource'
        );
        $expectedCounts = docx_zip_source_record_content_type_source_counts($inventory);

        $t->same('Source content-type-source buckets.', $document->children[0]->attr('text'));
        $t->same(count($expectedCounts), $summary['partZipSourceRecordContentTypeSourceCount']);
        $t->same($expectedCounts, $summary['partZipSourceRecordContentTypeSourceCounts']);
        $t->same(
            docx_zip_source_record_content_type_source_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordContentTypeSourceBytes']
        );
        $t->same(0, $summary['partZipSourceRecordContentTypeSourceDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordContentTypeSourceIssuePartCount']);
        $t->same($summary['partZipSourceRecordPartCount'], array_sum($summary['partZipSourceRecordContentTypeSourceCounts']));
        $t->same($identity, $document->attr('docx')['packageIdentity']);
        $t->same(
            $summary['partZipSourceRecordContentTypeSourceCount'],
            $identity['partZipSourceRecordContentTypeSourceCount']
        );
        $t->same(
            $summary['partZipSourceRecordContentTypeSourceCounts'],
            $identity['partZipSourceRecordContentTypeSourceCounts']
        );
        $t->same(
            $summary['partZipSourceRecordContentTypeSourceBytes'],
            $identity['partZipSourceRecordContentTypeSourceBytes']
        );
        $t->same(
            $summary['partZipSourceRecordContentTypeSourceDataDescriptorPartCount'],
            $identity['partZipSourceRecordContentTypeSourceDataDescriptorPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordContentTypeSourceIssuePartCount'],
            $identity['partZipSourceRecordContentTypeSourceIssuePartCount']
        );
        $t->same($summary['partZipSourceRecordContentTypeSources'], $identity['partZipSourceRecordContentTypeSources']);
        $t->same(
            false,
            array_key_exists('contents', $identity['partZipSourceRecordContentTypeSources'][0]['largestSourceRecordPart'])
        );

        $default = $sources['default'];
        $t->same(6, $default['partCount']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'customXml/item1.xml',
            'word/_rels/document.xml.rels',
            'word/media/preview.png',
            'word/media/review.png',
        ], $default['partNames']);
        $t->same(['/' => 1, '_rels/' => 1, 'customXml/' => 1, 'word/' => 3], $default['directoryRootCounts']);
        $t->same([
            'application/vnd.openxmlformats-package.relationships+xml' => 2,
            'application/xml' => 2,
            'image/png' => 2,
        ], $default['contentTypeBaseCounts']);
        $t->same([0 => 3, 8 => 3], $default['compressionMethodCounts']);
        $t->same(
            docx_zip_source_record_content_type_source_sum_for_source($inventory, 'default', 'localHeaderBytes'),
            $default['localHeaderBytes']
        );
        $t->same(
            docx_zip_source_record_content_type_source_sum_for_source($inventory, 'default', 'centralDirectoryRecordBytes'),
            $default['centralDirectoryRecordBytes']
        );
        $t->same(
            docx_zip_source_record_content_type_source_largest($inventory, 'default'),
            $default['largestSourceRecordPart']['partName']
        );
        $t->same('default', $default['largestSourceRecordPart']['contentTypeSource']);
        $t->same(false, array_key_exists('contents', $default['largestSourceRecordPart']));

        $override = $sources['override'];
        $t->same(2, $override['partCount']);
        $t->same([
            'word/document.xml',
            'word/embeddings/review.xlsx',
        ], $override['partNames']);
        $t->same(['word/' => 2], $override['directoryRootCounts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
        ], $override['contentTypeBaseCounts']);
        $t->same([0 => 1, 8 => 1], $override['compressionMethodCounts']);
        $t->same(
            ['document-relationship-target' => 1, 'embedded-package' => 1, 'office-document' => 1, 'root-relationship-target' => 1],
            $override['roleCounts']
        );
        $t->same(
            docx_zip_source_record_content_type_source_largest($inventory, 'override'),
            $override['largestSourceRecordPart']['partName']
        );
        $t->same(
            docx_zip_source_record_content_type_source_sum_for_source($inventory, 'override', 'sourceRecordBytes'),
            $override['sourceRecordBytes']
        );

        $missing = $sources['missing'];
        $t->same(1, $missing['partCount']);
        $t->same(['customXml/untyped-source.bin'], $missing['partNames']);
        $t->same(['customXml/' => 1], $missing['directoryRootCounts']);
        $t->same(['(missing)' => 1], $missing['contentTypeBaseCounts']);
        $t->same(['package-part' => 1], $missing['roleCounts']);
        $t->same(
            $inventory['customXml/untyped-source.bin']['sourceRecordBytes'],
            $missing['sourceRecordBytes']
        );
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_content_type_source_fixture_parts(): array
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
    <w:p><w:r><w:t>Source content-type-source buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/media/review.png',
            'data' => str_repeat('M', 512),
            'compressionMethod' => 0,
            'comment' => 'source content type source image',
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
        ['name' => 'customXml/item1.xml', 'data' => '<review>source-content-type-source</review>', 'compressionMethod' => 0],
        ['name' => 'customXml/untyped-source.bin', 'data' => 'untyped source-content-type-source payload', 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_content_type_source_index_by(array $items, string $key): array
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
function docx_zip_source_record_content_type_source_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $source = docx_zip_source_record_content_type_source_key($part);
        $counts[$source] = ($counts[$source] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_content_type_source_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $source = docx_zip_source_record_content_type_source_key($part);
        $sums[$source] = ($sums[$source] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_content_type_source_sum_for_source(array $inventory, string $source, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (docx_zip_source_record_content_type_source_key($part) !== $source) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_content_type_source_largest(array $inventory, string $source): ?string
{
    $largest = null;
    foreach ($inventory as $partName => $part) {
        if (docx_zip_source_record_content_type_source_key($part) !== $source) {
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
function docx_zip_source_record_content_type_source_key(array $part): string
{
    $source = is_string($part['contentTypeSource'] ?? null) ? $part['contentTypeSource'] : 'missing';

    return $source === '' ? 'missing' : $source;
}
