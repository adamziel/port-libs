<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by raw package part extension' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_package_part_raw_extension_fixture_parts(),
            'docx source raw extension review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $rawExtensions = docx_zip_source_record_package_part_raw_extension_index_by(
            $summary['partZipSourceRecordPackagePartRawExtensions'],
            'rawPartExtensionKey'
        );
        $expectedCounts = docx_zip_source_record_package_part_raw_extension_counts($inventory);

        $t->same('Source raw extension buckets.', $document->children[0]->attr('text'));
        $t->same(10, $summary['partZipSourceRecordPartCount']);
        $t->same(count($expectedCounts), $summary['partZipSourceRecordPackagePartRawExtensionCount']);
        $t->same($expectedCounts, $summary['partZipSourceRecordPackagePartRawExtensionCounts']);
        $t->same(
            docx_zip_source_record_package_part_raw_extension_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartRawExtensionBytes']
        );
        $t->same(1, $summary['partZipSourceRecordRawExtensionlessPackagePartCount']);
        $t->same(3, $summary['partZipSourceRecordPackagePartRawExtensionUppercasePartCount']);
        $t->same(3, $summary['partZipSourceRecordPackagePartRawExtensionNormalizedPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartRawExtensionDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartRawExtensionIssuePartCount']);
        $t->same(
            $summary['partZipSourceRecordPartCount'],
            array_sum($summary['partZipSourceRecordPackagePartRawExtensionCounts'])
        );

        $t->same(
            $summary['partZipSourceRecordPackagePartRawExtensionCount'],
            $identity['partZipSourceRecordPackagePartRawExtensionCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartRawExtensionCounts'],
            $identity['partZipSourceRecordPackagePartRawExtensionCounts']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartRawExtensionBytes'],
            $identity['partZipSourceRecordPackagePartRawExtensionBytes']
        );
        $t->same(
            $summary['partZipSourceRecordRawExtensionlessPackagePartCount'],
            $identity['partZipSourceRecordRawExtensionlessPackagePartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartRawExtensionUppercasePartCount'],
            $identity['partZipSourceRecordPackagePartRawExtensionUppercasePartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartRawExtensionNormalizedPartCount'],
            $identity['partZipSourceRecordPackagePartRawExtensionNormalizedPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartRawExtensionDataDescriptorPartCount'],
            $identity['partZipSourceRecordPackagePartRawExtensionDataDescriptorPartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartRawExtensionIssuePartCount'],
            $identity['partZipSourceRecordPackagePartRawExtensionIssuePartCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartRawExtensions'],
            $identity['partZipSourceRecordPackagePartRawExtensions']
        );
        $t->same(
            false,
            array_key_exists('contents', $identity['partZipSourceRecordPackagePartRawExtensions'][0]['largestSourceRecordPart'])
        );

        $png = $rawExtensions['PNG'];
        $t->same('PNG', $png['rawPartExtension']);
        $t->same(false, $png['extensionlessPackagePart']);
        $t->same(1, $png['partCount']);
        $t->same(1, $png['uppercasePartCount']);
        $t->same(1, $png['normalizedPartCount']);
        $t->same(['png' => 1], $png['partExtensionCounts']);
        $t->same(['word/media/IMAGE.PNG'], $png['partNames']);
        $t->same(['word/' => 1], $png['directoryRootCounts']);
        $t->same(['default' => 1], $png['contentTypeSourceCounts']);
        $t->same(['image/png' => 1], $png['contentTypeBaseCounts']);
        $t->same(['document-relationship-target' => 1], $png['roleCounts']);
        $t->same(
            $inventory['word/media/IMAGE.PNG']['sourceRecordBytes'],
            $png['largestSourceRecordPart']['sourceRecordBytes']
        );
        $t->same('word/media/IMAGE.PNG', $png['largestSourceRecordPart']['partName']);
        $t->same('PNG', $png['largestSourceRecordPart']['rawPartExtension']);
        $t->same('png', $png['largestSourceRecordPart']['partExtension']);
        $t->same(true, $png['largestSourceRecordPart']['partExtensionHasUppercase']);
        $t->same(true, $png['largestSourceRecordPart']['partExtensionWasNormalized']);
        $t->same(false, array_key_exists('contents', $png['largestSourceRecordPart']));

        $mixedPng = $rawExtensions['PnG'];
        $t->same(['png' => 1], $mixedPng['partExtensionCounts']);
        $t->same(['word/media/icon.PnG'], $mixedPng['partNames']);
        $t->same(1, $mixedPng['uppercasePartCount']);
        $t->same(1, $mixedPng['normalizedPartCount']);
        $t->same(['document-relationship-target' => 1], $mixedPng['roleCounts']);

        $upperXml = $rawExtensions['XML'];
        $t->same(['xml' => 1], $upperXml['partExtensionCounts']);
        $t->same(['customXml/item.XML'], $upperXml['partNames']);
        $t->same(['default' => 1], $upperXml['contentTypeSourceCounts']);
        $t->same(['package-part' => 1], $upperXml['roleCounts']);
        $t->same(1, $upperXml['uppercasePartCount']);
        $t->same(1, $upperXml['normalizedPartCount']);

        $lowerXml = $rawExtensions['xml'];
        $t->same(3, $lowerXml['partCount']);
        $t->same([
            '[Content_Types].xml',
            'customXml/item.xml',
            'word/document.xml',
        ], $lowerXml['partNames']);
        $t->same(['xml' => 3], $lowerXml['partExtensionCounts']);
        $t->same(['default' => 2, 'override' => 1], $lowerXml['contentTypeSourceCounts']);

        $extensionless = $rawExtensions['(none)'];
        $t->same(null, $extensionless['rawPartExtension']);
        $t->same(true, $extensionless['extensionlessPackagePart']);
        $t->same(['(none)' => 1], $extensionless['partExtensionCounts']);
        $t->same(['customXml/extensionless'], $extensionless['partNames']);
        $t->same(['override' => 1], $extensionless['contentTypeSourceCounts']);
        $t->same(['application/octet-stream' => 1], $extensionless['contentTypeBaseCounts']);
        $t->same('customXml/extensionless', $extensionless['largestSourceRecordPart']['partName']);
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_package_part_raw_extension_fixture_parts(): array
{
    return [
        ['name' => '[Content_Types].xml', 'compressionMethod' => 0, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
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
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/IMAGE.PNG"/>
  <Relationship Id="rIcon" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/icon.PnG"/>
</Relationships>
XML,
        ],
        ['name' => 'word/document.xml', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Source raw extension buckets.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/media/IMAGE.PNG',
            'data' => str_repeat('P', 320),
            'compressionMethod' => 0,
            'comment' => 'raw extension uppercase image',
        ],
        ['name' => 'word/media/icon.PnG', 'data' => str_repeat('i', 64), 'compressionMethod' => 8],
        ['name' => 'customXml/item.XML', 'data' => '<item>upper xml</item>', 'compressionMethod' => 0],
        ['name' => 'customXml/item.xml', 'data' => '<item>lower xml</item>', 'compressionMethod' => 0],
        ['name' => 'customXml/data.bin', 'data' => 'binary payload', 'compressionMethod' => 0],
        ['name' => 'customXml/extensionless', 'data' => 'extensionless payload', 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_package_part_raw_extension_index_by(array $items, string $key): array
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
function docx_zip_source_record_package_part_raw_extension_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_package_part_raw_extension_key($part);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function docx_zip_source_record_package_part_raw_extension_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $key = docx_zip_source_record_package_part_raw_extension_key($part);
        $sums[$key] = ($sums[$key] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, mixed> $part
 */
function docx_zip_source_record_package_part_raw_extension_key(array $part): string
{
    return is_string($part['rawPartExtension'] ?? null) ? $part['rawPartExtension'] : '(none)';
}
