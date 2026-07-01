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
        $t->same(47, $manifest['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']);
        $t->same(
            1,
            $manifest['benchmarkDenominator']['breakdown']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameCases']
        );
        $t->same(
            47,
            $manifest['benchmarkDenominator']['breakdown']['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']
        );
        $t->same(
            1,
            $manifest['benchmarkDenominator']['inventory']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameCases']
        );
        $t->same(
            47,
            $manifest['benchmarkDenominator']['inventory']['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']
        );
        $t->same(1, $manifest['inventory']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameCases']);
        $t->same(47, $manifest['inventory']['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']);
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

        $t->same('ZIP source record directory base names.', $document->children[0]->attr('text'));
        $t->same(7, $summary['partZipSourceRecordPackagePartDirectoryBaseNameCount']);
        $t->same([
            '/' => 1,
            '_rels' => 2,
            'charts' => 1,
            'customXml' => 1,
            'embeddings' => 1,
            'media' => 3,
            'word' => 1,
        ], $summary['partZipSourceRecordPackagePartDirectoryBaseNameCounts']);
        $t->same(
            docx_zip_source_record_directory_base_name_sums($inventory, 'directoryBaseName', 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameBytes']
        );
        $t->same(2, $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNameCount']);
        $t->same(5, $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNamePartCount']);
        $t->same(['_rels', 'media'], $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNames']);
        $t->same(0, $summary['partZipSourceRecordPackagePartDirectoryBaseNameDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartDirectoryBaseNameIssuePartCount']);

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

        $media = $directoryBaseNames['media'];
        $t->same(3, $media['partCount']);
        $t->same(2, $media['directoryCount']);
        $t->same(3, $media['extensionVariantCount']);
        $t->same(3, $media['baseNameVariantCount']);
        $t->same(['customXml/media' => 1, 'word/media' => 2], $media['directoryCounts']);
        $t->same(['customXml/' => 1, 'word/' => 2], $media['directoryRootCounts']);
        $t->same(['default' => 3], $media['contentTypeSourceCounts']);
        $t->same([
            'application/octet-stream' => 1,
            'image/jpeg' => 1,
            'image/png' => 1,
        ], $media['contentTypeBaseCounts']);
        $t->same(['0' => 1, '8' => 2], $media['compressionMethodCounts']);
        $t->same(['document-relationship-target' => 2, 'package-part' => 1], $media['roleCounts']);
        $t->same([
            'customXml/media/data.bin',
            'word/media/review.png',
            'word/media/second.jpg',
        ], $media['partNames']);
        $t->same(
            docx_zip_source_record_directory_base_name_sum_for_name(
                $inventory,
                'media',
                'sourceRecordBytes'
            ),
            $media['sourceRecordBytes']
        );
        $t->same('media', $media['largestSourceRecordPart']['directoryBaseName']);
        $t->same(false, array_key_exists('contents', $media['largestSourceRecordPart']));

        $relationships = $directoryBaseNames['_rels'];
        $t->same(2, $relationships['partCount']);
        $t->same(2, $relationships['directoryCount']);
        $t->same(['application/vnd.openxmlformats-package.relationships+xml' => 2], $relationships['contentTypeBaseCounts']);
        $t->same([
            'office-document-relationships' => 1,
            'package-relationships' => 1,
            'relationship-part' => 2,
        ], $relationships['roleCounts']);
        $t->same(['_rels', 'word/_rels'], $relationships['directories']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $relationships['partNames']);

        $root = $directoryBaseNames['/'];
        $t->same(1, $root['partCount']);
        $t->same('[Content_Types].xml', $root['largestSourceRecordPart']['partName']);

        $word = $directoryBaseNames['word'];
        $t->same(1, $word['partCount']);
        $t->same(['office-document' => 1, 'root-relationship-target' => 1], $word['roleCounts']);
        $t->same(['word/document.xml'], $word['partNames']);
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
  <Default Extension="jpg" ContentType="image/jpeg"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/embeddings/report.xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"/>
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
  <Relationship Id="rPng" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rJpeg" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/second.jpg"/>
  <Relationship Id="rChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/chart1.xml"/>
  <Relationship Id="rPackage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/report.xlsx"/>
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
        ['name' => 'word/media/review.png', 'data' => str_repeat('P', 96), 'compressionMethod' => 8],
        ['name' => 'word/media/second.jpg', 'data' => str_repeat('J', 72), 'compressionMethod' => 8],
        ['name' => 'customXml/media/data.bin', 'data' => str_repeat('B', 128), 'compressionMethod' => 0],
        ['name' => 'word/charts/chart1.xml', 'data' => '<c:chartSpace/>', 'compressionMethod' => 8],
        ['name' => 'word/embeddings/report.xlsx', 'data' => str_repeat('X', 256), 'compressionMethod' => 0],
        ['name' => 'customXml/item.xml', 'data' => '<item/>', 'compressionMethod' => 8],
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
function docx_zip_source_record_directory_base_name_sum_for_name(
    array $inventory,
    string $name,
    string $sumField
): int {
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part['zipEntryPresent'] ?? false) !== true || ($part['directoryBaseName'] ?? null) !== $name) {
            continue;
        }

        $sum += is_int($part[$sumField] ?? null) ? $part[$sumField] : 0;
    }

    return $sum;
}
