<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by package area' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_package_area_zip_parts(),
            'docx source package area review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $areas = docx_zip_source_record_package_area_index_by_area(
            $summary['partZipSourceRecordPackageAreas']
        );
        $expectedCounts = docx_zip_source_record_package_area_counts($inventory);
        $expectedBytes = docx_zip_source_record_package_area_sums($inventory, 'sourceRecordBytes');

        $t->same('Source package area buckets.', $document->children[0]->attr('text'));
        $t->same(count($expectedCounts), $summary['partZipSourceRecordPackageAreaCount']);
        $t->same($expectedCounts, $summary['partZipSourceRecordPackageAreaCounts']);
        $t->same($expectedBytes, $summary['partZipSourceRecordPackageAreaBytes']);
        $t->same(0, $summary['partZipSourceRecordPackageAreaDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackageAreaIssuePartCount']);
        $t->same($summary['partZipSourceRecordPartCount'], array_sum($summary['partZipSourceRecordPackageAreaCounts']));
        $t->same($identity, $document->attr('docx')['packageIdentity']);
        $t->same($summary['partZipSourceRecordPackageAreaCount'], $identity['partZipSourceRecordPackageAreaCount']);
        $t->same($summary['partZipSourceRecordPackageAreaCounts'], $identity['partZipSourceRecordPackageAreaCounts']);
        $t->same($summary['partZipSourceRecordPackageAreaBytes'], $identity['partZipSourceRecordPackageAreaBytes']);
        $t->same(
            $summary['partZipSourceRecordPackageAreaDataDescriptorPartCount'],
            $identity['partZipSourceRecordPackageAreaDataDescriptorPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackageAreaIssuePartCount'],
            $identity['partZipSourceRecordPackageAreaIssuePartCount']
        );
        $t->same($summary['partZipSourceRecordPackageAreas'], $identity['partZipSourceRecordPackageAreas']);
        $t->same(
            false,
            array_key_exists('contents', $identity['partZipSourceRecordPackageAreas'][0]['largestSourceRecordPart'])
        );

        $rootArea = $areas['/'];
        $t->same(2, $rootArea['partCount']);
        $t->same([
            '[Content_Types].xml',
            'root-note.xml',
        ], $rootArea['partNames']);
        $t->same(['default' => 2], $rootArea['contentTypeSourceCounts']);
        $t->same(
            docx_zip_source_record_package_area_sum_for_area($inventory, '/', 'sourceRecordBytes'),
            $rootArea['sourceRecordBytes']
        );

        $wordArea = $areas['word/'];
        $t->same(5, $wordArea['partCount']);
        $t->same([
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/embeddings/review.xlsx',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ], $wordArea['partNames']);
        $t->same(['word/' => 5], $wordArea['directoryRootCounts']);
        $t->same(['default' => 3, 'override' => 2], $wordArea['contentTypeSourceCounts']);
        $t->same([8 => 5], $wordArea['compressionMethodCounts']);
        $t->same(
            docx_zip_source_record_package_area_sum_for_area($inventory, 'word/', 'centralDirectoryRecordBytes'),
            $wordArea['centralDirectoryRecordBytes']
        );
        $t->same(
            docx_zip_source_record_package_area_largest($inventory, 'word/'),
            $wordArea['largestSourceRecordPart']['partName']
        );
        $t->same('word/', $wordArea['largestSourceRecordPart']['packageArea']);
        $t->same(false, array_key_exists('contents', $wordArea['largestSourceRecordPart']));

        $docPropsArea = $areas['docProps/'];
        $t->same(1, $docPropsArea['partCount']);
        $t->same(['docProps/core.xml'], $docPropsArea['partNames']);
        $t->same(['core-properties' => 1, 'root-relationship-target' => 1], $docPropsArea['roleCounts']);

        $customXmlArea = $areas['customXml/'];
        $t->same(1, $customXmlArea['partCount']);
        $t->same(['customXml/review/data.bin'], $customXmlArea['partNames']);
        $t->same(['missing' => 1], $customXmlArea['contentTypeSourceCounts']);
        $t->same(['package-part' => 1], $customXmlArea['roleCounts']);
        $t->same(
            $inventory['customXml/review/data.bin']['sourceRecordBytes'],
            $customXmlArea['sourceRecordBytes']
        );
    },
];

/**
 * @return array<string, string>
 */
function docx_zip_source_record_package_area_fixture_parts(): array
{
    $embeddedContentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $embeddedPackageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';

    return [
        '[Content_Types].xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/review.xlsx" ContentType="{$embeddedContentType}"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rRootNote" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="root-note.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rDeepImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/deep/scan.png"/>
  <Relationship Id="rEmbeddedWorkbook" Type="{$embeddedPackageRel}" Target="embeddings/review.xlsx"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Source package area buckets.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Source package area buckets</dc:title>
</cp:coreProperties>
XML,
        'root-note.xml' => '<root-note>source package area</root-note>',
        'word/media/review.png' => str_repeat('R', 180),
        'word/media/deep/scan.png' => str_repeat('S', 96),
        'word/embeddings/review.xlsx' => str_repeat('E', 920),
        'customXml/review/data.bin' => 'untyped source-package-area payload',
    ];
}

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_zip_source_record_package_area_zip_parts(): array
{
    $zipParts = [];
    foreach (docx_zip_source_record_package_area_fixture_parts() as $name => $data) {
        $zipParts[] = [
            'name' => $name,
            'data' => $data,
            'compressionMethod' => str_starts_with($name, 'word/') ? 8 : 0,
        ];
    }

    return $zipParts;
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_package_area_index_by_area(array $items): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item['packageArea']] = $item;
    }

    return $indexed;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_package_area_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $area = docx_zip_source_record_package_area_key($part);
        $counts[$area] = ($counts[$area] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_package_area_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $area = docx_zip_source_record_package_area_key($part);
        $sums[$area] = ($sums[$area] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_package_area_sum_for_area(array $inventory, string $area, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (docx_zip_source_record_package_area_key($part) !== $area) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_package_area_largest(array $inventory, string $area): string
{
    $largest = null;
    foreach ($inventory as $partName => $part) {
        if (docx_zip_source_record_package_area_key($part) !== $area) {
            continue;
        }

        $partName = (string) ($part['partName'] ?? $partName);
        $sourceRecordBytes = is_int($part['sourceRecordBytes'] ?? null) ? $part['sourceRecordBytes'] : 0;
        if (
            !is_array($largest)
            || $sourceRecordBytes > $largest['sourceRecordBytes']
            || ($sourceRecordBytes === $largest['sourceRecordBytes'] && strcmp($partName, $largest['partName']) < 0)
        ) {
            $largest = [
                'partName' => $partName,
                'sourceRecordBytes' => $sourceRecordBytes,
            ];
        }
    }

    return is_array($largest) ? $largest['partName'] : '';
}

/**
 * @param array<string, mixed> $part
 */
function docx_zip_source_record_package_area_key(array $part): string
{
    return is_string($part['packageArea'] ?? null) ? $part['packageArea'] : '/';
}
