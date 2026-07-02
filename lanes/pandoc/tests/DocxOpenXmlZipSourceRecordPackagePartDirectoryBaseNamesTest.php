<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'records mapped DOCX ZIP source-record directory base-name case count' => static function (TestRunner $t): void {
        $manifest = json_decode(
            file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json') ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $t->same(1, $manifest['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameCases']);
        $t->same(64, $manifest['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']);
        $t->same(
            1,
            $manifest['benchmarkDenominator']['breakdown']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameCases']
        );
        $t->same(
            64,
            $manifest['benchmarkDenominator']['breakdown']['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']
        );
        $t->same(
            1,
            $manifest['benchmarkDenominator']['inventory']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameCases']
        );
        $t->same(
            64,
            $manifest['benchmarkDenominator']['inventory']['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']
        );
        $t->same(1, $manifest['inventory']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameCases']);
        $t->same(64, $manifest['inventory']['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']);
    },

    'summarizes DOCX ZIP source records by package part directory base names' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_directory_base_name_fixture_parts(),
            'docx source record directory base-name review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $directoryBaseNames = docx_zip_source_record_directory_base_name_index_by(
            $summary['partZipSourceRecordPackagePartDirectoryBaseNames'],
            'directoryBaseName'
        );
        $caseFoldDirectoryBaseNames = docx_zip_source_record_directory_base_name_index_by(
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNames'],
            'caseFoldDirectoryBaseName'
        );

        $t->same('ZIP source record directory base names.', $document->children[0]->attr('text'));
        $t->same(8, $summary['partZipSourceRecordPackagePartDirectoryBaseNameCount']);
        $t->same([
            '/' => 1,
            'Media.assets' => 1,
            '_rels' => 2,
            'embeddings' => 1,
            'media' => 1,
            'media.assets' => 1,
            'media.raw' => 1,
            'word' => 1,
        ], $summary['partZipSourceRecordPackagePartDirectoryBaseNameCounts']);
        $t->same(
            docx_zip_source_record_directory_base_name_sums($inventory, 'directoryBaseName', 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameBytes']
        );
        $t->same(1, $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNameCount']);
        $t->same(2, $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNamePartCount']);
        $t->same(['_rels'], $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNames']);
        $t->same(0, $summary['partZipSourceRecordPackagePartDirectoryBaseNameDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartDirectoryBaseNameIssuePartCount']);

        $t->same(7, $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameCount']);
        $t->same([
            '/' => 1,
            '_rels' => 2,
            'embeddings' => 1,
            'media' => 1,
            'media.assets' => 2,
            'media.raw' => 1,
            'word' => 1,
        ], $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameCounts']);
        $t->same(
            docx_zip_source_record_directory_base_name_sums(
                $inventory,
                'caseFoldDirectoryBaseName',
                'sourceRecordBytes'
            ),
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameBytes']
        );
        $t->same(2, $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNameCount']);
        $t->same(4, $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNamePartCount']);
        $t->same(['_rels', 'media.assets'], $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNames']);
        $t->same(0, $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameIssuePartCount']);

        $t->same(
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameCount'],
            $identity['partZipSourceRecordPackagePartDirectoryBaseNameCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameCounts'],
            $identity['partZipSourceRecordPackagePartDirectoryBaseNameCounts']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameBytes'],
            $identity['partZipSourceRecordPackagePartDirectoryBaseNameBytes']
        );
        $t->same(
            $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNames'],
            $identity['partZipSourceRecordDuplicatePackagePartDirectoryBaseNames']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartDirectoryBaseNames'],
            $identity['partZipSourceRecordPackagePartDirectoryBaseNames']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameCount'],
            $identity['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameCounts'],
            $identity['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameCounts']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameBytes'],
            $identity['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameBytes']
        );
        $t->same(
            $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNames'],
            $identity['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNames']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNames'],
            $identity['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNames']
        );

        $rels = $directoryBaseNames['_rels'];
        $t->same(2, $rels['partCount']);
        $t->same(2, $rels['directoryCount']);
        $t->same(1, $rels['extensionVariantCount']);
        $t->same(2, $rels['baseNameVariantCount']);
        $t->same(['_rels' => 1, 'word/_rels' => 1], $rels['directoryCounts']);
        $t->same(['_rels/' => 1, 'word/' => 1], $rels['directoryRootCounts']);
        $t->same(['rels' => 2], $rels['partExtensionCounts']);
        $t->same(['.rels' => 1, 'document.xml.rels' => 1], $rels['baseNameCounts']);
        $t->same(['default' => 2], $rels['contentTypeSourceCounts']);
        $t->same(['application/vnd.openxmlformats-package.relationships+xml' => 2], $rels['contentTypeBaseCounts']);
        $t->same(['8' => 2], $rels['compressionMethodCounts']);
        $t->same([
            'office-document-relationships' => 1,
            'package-relationships' => 1,
            'relationship-part' => 2,
        ], $rels['roleCounts']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $rels['partNames']);
        $t->same(
            docx_zip_source_record_directory_base_name_sum_for(
                $inventory,
                'directoryBaseName',
                '_rels',
                'sourceRecordBytes'
            ),
            $rels['sourceRecordBytes']
        );
        $t->same('word/_rels/document.xml.rels', $rels['largestSourceRecordPart']['partName']);
        $t->same('_rels', $rels['largestSourceRecordPart']['directoryBaseName']);
        $t->same(false, array_key_exists('contents', $rels['largestSourceRecordPart']));

        $caseFoldMediaAssets = $caseFoldDirectoryBaseNames['media.assets'];
        $t->same(2, $caseFoldMediaAssets['partCount']);
        $t->same(2, $caseFoldMediaAssets['directoryCount']);
        $t->same(2, $caseFoldMediaAssets['directoryBaseNameVariantCount']);
        $t->same(1, $caseFoldMediaAssets['extensionVariantCount']);
        $t->same(2, $caseFoldMediaAssets['baseNameVariantCount']);
        $t->same(['Media.assets' => 1, 'media.assets' => 1], $caseFoldMediaAssets['directoryBaseNameCounts']);
        $t->same(['word/Media.assets' => 1, 'word/media.assets' => 1], $caseFoldMediaAssets['directoryCounts']);
        $t->same(['word/' => 2], $caseFoldMediaAssets['directoryRootCounts']);
        $t->same(['xml' => 2], $caseFoldMediaAssets['partExtensionCounts']);
        $t->same(['alt.xml' => 1, 'source.xml' => 1], $caseFoldMediaAssets['baseNameCounts']);
        $t->same(['default' => 2], $caseFoldMediaAssets['contentTypeSourceCounts']);
        $t->same(['application/xml' => 2], $caseFoldMediaAssets['contentTypeBaseCounts']);
        $t->same(['0' => 1, '8' => 1], $caseFoldMediaAssets['compressionMethodCounts']);
        $t->same([
            'custom-xml-part' => 1,
            'document-relationship-target' => 1,
            'package-part' => 1,
        ], $caseFoldMediaAssets['roleCounts']);
        $t->same(['word/Media.assets/source.xml', 'word/media.assets/alt.xml'], $caseFoldMediaAssets['partNames']);
        $t->same(
            docx_zip_source_record_directory_base_name_sum_for(
                $inventory,
                'caseFoldDirectoryBaseName',
                'media.assets',
                'sourceRecordBytes'
            ),
            $caseFoldMediaAssets['sourceRecordBytes']
        );
        $t->same('word/media.assets/alt.xml', $caseFoldMediaAssets['largestSourceRecordPart']['partName']);
        $t->same('media.assets', $caseFoldMediaAssets['largestSourceRecordPart']['directoryBaseName']);
        $t->same('media.assets', $caseFoldMediaAssets['largestSourceRecordPart']['caseFoldDirectoryBaseName']);
        $t->same(false, array_key_exists('contents', $caseFoldMediaAssets['largestSourceRecordPart']));
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_directory_base_name_fixture_parts(): array
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
  <Relationship Id="rMediaAssetSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="Media.assets/source.xml"/>
  <Relationship Id="rMediaRawImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media.raw/image.png"/>
</Relationships>
XML,
        ],
        ['name' => 'word/document.xml', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>ZIP source record directory base names.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        ['name' => 'word/Media.assets/source.xml', 'data' => str_repeat('S', 96), 'compressionMethod' => 8],
        ['name' => 'word/media.assets/alt.xml', 'data' => str_repeat('A', 64), 'compressionMethod' => 0],
        ['name' => 'word/media.raw/image.png', 'data' => str_repeat('I', 80), 'compressionMethod' => 8],
        ['name' => 'customXml/media/review.png', 'data' => str_repeat('R', 72), 'compressionMethod' => 0],
        ['name' => 'word/embeddings/report.bin', 'data' => str_repeat('B', 48), 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_directory_base_name_index_by(array $items, string $key): array
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
function docx_zip_source_record_directory_base_name_sums(
    array $inventory,
    string $nameField,
    string $sumField
): array {
    $sums = [];
    foreach ($inventory as $part) {
        if (($part['zipEntryPresent'] ?? false) !== true) {
            continue;
        }

        $name = is_string($part[$nameField] ?? null) ? $part[$nameField] : '';
        $sums[$name] = ($sums[$name] ?? 0) + (is_int($part[$sumField] ?? null) ? $part[$sumField] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_directory_base_name_sum_for(
    array $inventory,
    string $nameField,
    string $name,
    string $sumField
): int {
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part['zipEntryPresent'] ?? false) !== true || ($part[$nameField] ?? null) !== $name) {
            continue;
        }

        $sum += is_int($part[$sumField] ?? null) ? $part[$sumField] : 0;
    }

    return $sum;
}
