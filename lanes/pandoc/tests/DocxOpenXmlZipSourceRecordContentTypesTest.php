<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by package content type' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_content_type_fixture_parts(),
            'docx source content type review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $contentTypes = docx_zip_source_record_content_type_index_by(
            $summary['partZipSourceRecordContentTypes'],
            'contentTypeKey'
        );
        $expectedCounts = docx_zip_source_record_content_type_counts($inventory);

        $t->same('Source content type buckets.', $document->children[0]->attr('text'));
        $t->same(count($expectedCounts), $summary['partZipSourceRecordContentTypeCount']);
        $t->same($expectedCounts, $summary['partZipSourceRecordContentTypeCounts']);
        $t->same(
            docx_zip_source_record_content_type_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordContentTypeBytes']
        );
        $t->same(0, $summary['partZipSourceRecordContentTypeDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordContentTypeIssuePartCount']);
        $t->same($summary['partZipSourceRecordPartCount'], array_sum($summary['partZipSourceRecordContentTypeCounts']));
        $t->same($identity, $document->attr('docx')['packageIdentity']);
        $t->same($summary['partZipSourceRecordContentTypeCount'], $identity['partZipSourceRecordContentTypeCount']);
        $t->same($summary['partZipSourceRecordContentTypeCounts'], $identity['partZipSourceRecordContentTypeCounts']);
        $t->same($summary['partZipSourceRecordContentTypeBytes'], $identity['partZipSourceRecordContentTypeBytes']);
        $t->same(
            $summary['partZipSourceRecordContentTypeDataDescriptorPartCount'],
            $identity['partZipSourceRecordContentTypeDataDescriptorPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordContentTypeIssuePartCount'],
            $identity['partZipSourceRecordContentTypeIssuePartCount']
        );
        $t->same($summary['partZipSourceRecordContentTypes'], $identity['partZipSourceRecordContentTypes']);
        $t->same(
            false,
            array_key_exists('contents', $identity['partZipSourceRecordContentTypes'][0]['largestSourceRecordPart'])
        );

        $relationships = $contentTypes['application/vnd.openxmlformats-package.relationships+xml'];
        $t->same(2, $relationships['partCount']);
        $t->same([
            '_rels/.rels',
            'word/_rels/document.xml.rels',
        ], $relationships['partNames']);
        $t->same(['default' => 2], $relationships['contentTypeSourceCounts']);
        $t->same(['_rels/' => 1, 'word/' => 1], $relationships['directoryRootCounts']);
        $t->same([
            'office-document-relationships' => 1,
            'package-relationships' => 1,
            'relationship-part' => 2,
        ], $relationships['roleCounts']);
        $t->same(
            docx_zip_source_record_content_type_sum_for_key(
                $inventory,
                'application/vnd.openxmlformats-package.relationships+xml',
                'sourceRecordBytes'
            ),
            $relationships['sourceRecordBytes']
        );

        $images = $contentTypes['image/png'];
        $t->same(2, $images['partCount']);
        $t->same([
            'word/media/preview.png',
            'word/media/review.png',
        ], $images['partNames']);
        $t->same(['default' => 2], $images['contentTypeSourceCounts']);
        $t->same(['word/' => 2], $images['directoryRootCounts']);
        $t->same([0 => 1, 8 => 1], $images['compressionMethodCounts']);
        $t->same(
            docx_zip_source_record_content_type_sum_for_key($inventory, 'image/png', 'centralDirectoryRecordBytes'),
            $images['centralDirectoryRecordBytes']
        );
        $t->same(
            docx_zip_source_record_content_type_largest($inventory, 'image/png'),
            $images['largestSourceRecordPart']['partName']
        );
        $t->same(false, array_key_exists('contents', $images['largestSourceRecordPart']));

        $embedded = $contentTypes['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $t->same(1, $embedded['partCount']);
        $t->same(['override' => 1], $embedded['contentTypeSourceCounts']);
        $t->same(['document-relationship-target' => 1, 'embedded-package' => 1], $embedded['roleCounts']);
        $t->same('word/embeddings/review.xlsx', $embedded['largestSourceRecordPart']['partName']);
        $t->same(
            $inventory['word/embeddings/review.xlsx']['sourceRecordBytes'],
            $embedded['sourceRecordBytes']
        );

        $xml = $contentTypes['application/xml'];
        $t->same(2, $xml['partCount']);
        $t->same([
            '[Content_Types].xml',
            'customXml/item1.xml',
        ], $xml['partNames']);
        $t->same(['default' => 2], $xml['contentTypeSourceCounts']);
        $t->same(['/' => 1, 'customXml/' => 1], $xml['directoryRootCounts']);

        $missing = $contentTypes['(missing)'];
        $t->same('', $missing['contentTypeBase']);
        $t->same([], $missing['contentTypes']);
        $t->same(1, $missing['partCount']);
        $t->same(['customXml/untyped-source.bin'], $missing['partNames']);
        $t->same(['missing' => 1], $missing['contentTypeSourceCounts']);
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
function docx_zip_source_record_content_type_fixture_parts(): array
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
    <w:p><w:r><w:t>Source content type buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/media/review.png',
            'data' => str_repeat('M', 512),
            'compressionMethod' => 0,
            'comment' => 'source content type image',
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
        ['name' => 'customXml/item1.xml', 'data' => '<review>source-content-type</review>', 'compressionMethod' => 0],
        ['name' => 'customXml/untyped-source.bin', 'data' => 'untyped source-content-type payload', 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_content_type_index_by(array $items, string $key): array
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
function docx_zip_source_record_content_type_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_content_type_key($part);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_content_type_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_content_type_key($part);
        $sums[$key] = ($sums[$key] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_content_type_sum_for_key(array $inventory, string $key, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (docx_zip_source_record_content_type_key($part) !== $key) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_content_type_largest(array $inventory, string $key): ?string
{
    $largest = null;
    foreach ($inventory as $partName => $part) {
        if (docx_zip_source_record_content_type_key($part) !== $key) {
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
function docx_zip_source_record_content_type_key(array $part): string
{
    $contentTypeBase = is_string($part['contentTypeBase'] ?? null) ? $part['contentTypeBase'] : '';

    return $contentTypeBase === '' ? '(missing)' : $contentTypeBase;
}
