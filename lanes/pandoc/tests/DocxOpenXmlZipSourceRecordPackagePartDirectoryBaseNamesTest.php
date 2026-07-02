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
        $t->same(63, $manifest['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']);
        $t->same(
            1,
            $manifest['benchmarkDenominator']['breakdown']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameCases']
        );
        $t->same(
            63,
            $manifest['benchmarkDenominator']['breakdown']['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']
        );
        $t->same(
            1,
            $manifest['benchmarkDenominator']['inventory']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameCases']
        );
        $t->same(
            63,
            $manifest['benchmarkDenominator']['inventory']['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']
        );
        $t->same(1, $manifest['inventory']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameCases']);
        $t->same(63, $manifest['inventory']['docxZipSourceRecordPackagePartDirectoryBaseNameAssertions']);
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
        $t->same(7, $summary['partZipSourceRecordPackagePartDirectoryBaseNameCount']);
        $t->same([
            '/' => 1,
            'MEDIA' => 1,
            'Media' => 1,
            '_rels' => 2,
            'embeddings' => 1,
            'media' => 2,
            'word' => 1,
        ], $summary['partZipSourceRecordPackagePartDirectoryBaseNameCounts']);
        $t->same(
            docx_zip_source_record_directory_base_name_sums($inventory, 'directoryBaseName', 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameBytes']
        );
        $t->same(2, $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNameCount']);
        $t->same(4, $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNamePartCount']);
        $t->same(['_rels', 'media'], $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNames']);
        $t->same(0, $summary['partZipSourceRecordPackagePartDirectoryBaseNameDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartDirectoryBaseNameIssuePartCount']);
        $t->same(5, $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameCount']);
        $t->same([
            '/' => 1,
            '_rels' => 2,
            'embeddings' => 1,
            'media' => 4,
            'word' => 1,
        ], $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameCounts']);
        $t->same(
            docx_zip_source_record_directory_base_name_sums($inventory, 'caseFoldDirectoryBaseName', 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameBytes']
        );
        $t->same(2, $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNameCount']);
        $t->same(6, $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNamePartCount']);
        $t->same(['_rels', 'media'], $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNames']);
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

        $media = $directoryBaseNames['media'];
        $t->same(2, $media['partCount']);
        $t->same(2, $media['directoryCount']);
        $t->same(1, $media['extensionVariantCount']);
        $t->same(2, $media['baseNameVariantCount']);
        $t->same(['customXml/media' => 1, 'word/media' => 1], $media['directoryCounts']);
        $t->same(['customXml/' => 1, 'word/' => 1], $media['directoryRootCounts']);
        $t->same(['default' => 2], $media['contentTypeSourceCounts']);
        $t->same(['image/png' => 2], $media['contentTypeBaseCounts']);
        $t->same(['0' => 1, '8' => 1], $media['compressionMethodCounts']);
        $t->same(['document-relationship-target' => 1, 'package-part' => 1], $media['roleCounts']);
        $t->same(['customXml/media/review.png', 'word/media/image.png'], $media['partNames']);
        $t->same(
            docx_zip_source_record_directory_base_name_sum_for_name(
                $inventory,
                'directoryBaseName',
                'media',
                'sourceRecordBytes'
            ),
            $media['sourceRecordBytes']
        );
        $t->same('media', $media['largestSourceRecordPart']['directoryBaseName']);
        $t->same(false, array_key_exists('contents', $media['largestSourceRecordPart']));

        $upperMedia = $directoryBaseNames['Media'];
        $t->same(1, $upperMedia['partCount']);
        $t->same('Media', $upperMedia['directoryBaseName']);
        $t->same(['application/xml' => 1], $upperMedia['contentTypeBaseCounts']);

        $caseFoldMedia = $caseFoldDirectoryBaseNames['media'];
        $t->same(4, $caseFoldMedia['partCount']);
        $t->same(4, $caseFoldMedia['directoryCount']);
        $t->same(3, $caseFoldMedia['directoryBaseNameVariantCount']);
        $t->same(['MEDIA' => 1, 'Media' => 1, 'media' => 2], $caseFoldMedia['directoryBaseNameCounts']);
        $t->same([
            'customXml/MEDIA' => 1,
            'customXml/media' => 1,
            'word/Media' => 1,
            'word/media' => 1,
        ], $caseFoldMedia['directoryCounts']);
        $t->same(['png' => 2, 'xml' => 2], $caseFoldMedia['partExtensionCounts']);
        $t->same(['application/xml' => 2, 'image/png' => 2], $caseFoldMedia['contentTypeBaseCounts']);
        $t->same([
            'custom-xml-part' => 1,
            'document-relationship-target' => 2,
            'package-part' => 2,
        ], $caseFoldMedia['roleCounts']);
        $t->same([
            'customXml/MEDIA/data.xml',
            'customXml/media/review.png',
            'word/Media/source.xml',
            'word/media/image.png',
        ], $caseFoldMedia['partNames']);
        $t->same('media', $caseFoldMedia['largestSourceRecordPart']['caseFoldDirectoryBaseName']);
        $t->same(false, array_key_exists('contents', $caseFoldMedia['largestSourceRecordPart']));
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
  <Relationship Id="rMediaSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="Media/source.xml"/>
  <Relationship Id="rMediaImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/>
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
        ['name' => 'word/Media/source.xml', 'data' => '<review-source/>', 'compressionMethod' => 8],
        ['name' => 'word/media/image.png', 'data' => str_repeat('I', 96), 'compressionMethod' => 8],
        ['name' => 'customXml/MEDIA/data.xml', 'data' => '<review-data/>', 'compressionMethod' => 0],
        ['name' => 'customXml/media/review.png', 'data' => str_repeat('R', 128), 'compressionMethod' => 0],
        ['name' => 'word/embeddings/report.bin', 'data' => str_repeat('B', 64), 'compressionMethod' => 0],
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
