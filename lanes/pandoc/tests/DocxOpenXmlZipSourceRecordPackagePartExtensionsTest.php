<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by package part extension' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_package_part_extension_fixture_parts(),
            'docx source extension review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $extensions = docx_zip_source_record_package_part_extension_index_by(
            $summary['partZipSourceRecordPackagePartExtensions'],
            'partExtensionKey'
        );
        $expectedCounts = docx_zip_source_record_package_part_extension_counts($inventory);

        $t->same('Source extension buckets.', $document->children[0]->attr('text'));
        $t->same(count($expectedCounts), $summary['partZipSourceRecordPackagePartExtensionCount']);
        $t->same($expectedCounts, $summary['partZipSourceRecordPackagePartExtensionCounts']);
        $t->same(
            docx_zip_source_record_package_part_extension_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartExtensionBytes']
        );
        $t->same(1, $summary['partZipSourceRecordExtensionlessPackagePartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartExtensionDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartExtensionIssuePartCount']);
        $t->same($summary['partZipSourceRecordPartCount'], array_sum($summary['partZipSourceRecordPackagePartExtensionCounts']));
        $t->same($identity, $document->attr('docx')['packageIdentity']);
        $t->same(
            $summary['partZipSourceRecordPackagePartExtensionCount'],
            $identity['partZipSourceRecordPackagePartExtensionCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartExtensionCounts'],
            $identity['partZipSourceRecordPackagePartExtensionCounts']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartExtensionBytes'],
            $identity['partZipSourceRecordPackagePartExtensionBytes']
        );
        $t->same(
            $summary['partZipSourceRecordExtensionlessPackagePartCount'],
            $identity['partZipSourceRecordExtensionlessPackagePartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartExtensionDataDescriptorPartCount'],
            $identity['partZipSourceRecordPackagePartExtensionDataDescriptorPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartExtensionIssuePartCount'],
            $identity['partZipSourceRecordPackagePartExtensionIssuePartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartExtensions'],
            $identity['partZipSourceRecordPackagePartExtensions']
        );
        $t->same(
            false,
            array_key_exists('contents', $identity['partZipSourceRecordPackagePartExtensions'][0]['largestSourceRecordPart'])
        );

        $xml = $extensions['xml'];
        $t->same(3, $xml['partCount']);
        $t->same([
            '[Content_Types].xml',
            'customXml/item1.xml',
            'word/document.xml',
        ], $xml['partNames']);
        $t->same(['/' => 1, 'customXml/' => 1, 'word/' => 1], $xml['directoryRootCounts']);
        $t->same(['default' => 2, 'override' => 1], $xml['contentTypeSourceCounts']);
        $t->same([0 => 2, 8 => 1], $xml['compressionMethodCounts']);
        $t->same(
            docx_zip_source_record_package_part_extension_sum_for_key($inventory, 'xml', 'centralDirectoryRecordBytes'),
            $xml['centralDirectoryRecordBytes']
        );

        $rels = $extensions['rels'];
        $t->same(2, $rels['partCount']);
        $t->same([
            '_rels/.rels',
            'word/_rels/document.xml.rels',
        ], $rels['partNames']);
        $t->same(['_rels/' => 1, 'word/' => 1], $rels['directoryRootCounts']);
        $t->same([
            'office-document-relationships' => 1,
            'package-relationships' => 1,
            'relationship-part' => 2,
        ], $rels['roleCounts']);

        $png = $extensions['png'];
        $t->same(1, $png['partCount']);
        $t->same(['word/media/review.png'], $png['partNames']);
        $t->same(['default' => 1], $png['contentTypeSourceCounts']);
        $t->same(['document-relationship-target' => 1], $png['roleCounts']);
        $t->same(
            $inventory['word/media/review.png']['sourceRecordBytes'],
            $png['largestSourceRecordPart']['sourceRecordBytes']
        );
        $t->same(false, array_key_exists('contents', $png['largestSourceRecordPart']));

        $bin = $extensions['bin'];
        $t->same(1, $bin['partCount']);
        $t->same(['customXml/raw.bin'], $bin['partNames']);
        $t->same(['missing' => 1], $bin['contentTypeSourceCounts']);
        $t->same(['(missing)' => 1], $bin['contentTypeBaseCounts']);

        $extensionless = $extensions['(none)'];
        $t->same(null, $extensionless['partExtension']);
        $t->same(true, $extensionless['extensionlessPackagePart']);
        $t->same(1, $extensionless['partCount']);
        $t->same(['customXml/extensionless'], $extensionless['partNames']);
        $t->same(['override' => 1], $extensionless['contentTypeSourceCounts']);
        $t->same(['application/octet-stream' => 1], $extensionless['contentTypeBaseCounts']);
        $t->same(['customXml/' => 1], $extensionless['directoryRootCounts']);
        $t->same([0 => 1], $extensionless['compressionMethodCounts']);
        $t->same(
            $inventory['customXml/extensionless']['sourceRecordBytes'],
            $extensionless['sourceRecordBytes']
        );
        $t->same('customXml/extensionless', $extensionless['largestSourceRecordPart']['partName']);
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_package_part_extension_fixture_parts(): array
{
    return [
        ['name' => '[Content_Types].xml', 'compressionMethod' => 0, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/customXml/extensionless" ContentType="application/octet-stream"/>
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
        ['name' => 'word/_rels/document.xml.rels', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        ],
        ['name' => 'word/document.xml', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Source extension buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/media/review.png',
            'data' => str_repeat('M', 320),
            'compressionMethod' => 0,
            'comment' => 'source extension image',
        ],
        ['name' => 'customXml/item1.xml', 'data' => '<review>source-extension</review>', 'compressionMethod' => 0],
        ['name' => 'customXml/raw.bin', 'data' => 'raw source-extension payload', 'compressionMethod' => 0],
        ['name' => 'customXml/extensionless', 'data' => 'extensionless source record payload', 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_package_part_extension_index_by(array $items, string $key): array
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
function docx_zip_source_record_package_part_extension_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_package_part_extension_key($part);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_package_part_extension_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_package_part_extension_key($part);
        $sums[$key] = ($sums[$key] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_package_part_extension_sum_for_key(array $inventory, string $key, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (docx_zip_source_record_package_part_extension_key($part) !== $key) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}

/**
 * @param array<string, mixed> $part
 */
function docx_zip_source_record_package_part_extension_key(array $part): string
{
    return is_string($part['partExtension'] ?? null) ? $part['partExtension'] : '(none)';
}
