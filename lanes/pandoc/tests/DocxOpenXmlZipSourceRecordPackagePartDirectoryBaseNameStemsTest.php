<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'records mapped DOCX ZIP source-record directory base-name stem case count' => static function (TestRunner $t): void {
        $manifest = json_decode(
            file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json') ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $t->same(1, $manifest['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameStemCases']);
        $t->same(64, $manifest['docxZipSourceRecordPackagePartDirectoryBaseNameStemAssertions']);
        $t->same(
            1,
            $manifest['benchmarkDenominator']['breakdown']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameStemCases']
        );
        $t->same(
            64,
            $manifest['benchmarkDenominator']['breakdown']['docxZipSourceRecordPackagePartDirectoryBaseNameStemAssertions']
        );
        $t->same(
            1,
            $manifest['benchmarkDenominator']['inventory']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameStemCases']
        );
        $t->same(
            64,
            $manifest['benchmarkDenominator']['inventory']['docxZipSourceRecordPackagePartDirectoryBaseNameStemAssertions']
        );
        $t->same(1, $manifest['inventory']['mappedDocxZipSourceRecordPackagePartDirectoryBaseNameStemCases']);
        $t->same(64, $manifest['inventory']['docxZipSourceRecordPackagePartDirectoryBaseNameStemAssertions']);
    },

    'summarizes DOCX ZIP source records by package part directory base-name stems' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_directory_base_name_stem_fixture_parts(),
            'docx source record directory base-name stem review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $directoryStems = docx_zip_source_record_directory_base_name_stem_index_by(
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameStems'],
            'directoryBaseNameStem'
        );
        $caseFoldDirectoryStems = docx_zip_source_record_directory_base_name_stem_index_by(
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStems'],
            'caseFoldDirectoryBaseNameStem'
        );

        $t->same('ZIP source record directory base-name stems.', $document->children[0]->attr('text'));
        $t->same(6, $summary['partZipSourceRecordPackagePartDirectoryBaseNameStemCount']);
        $t->same([
            '/' => 1,
            'Media' => 1,
            '_rels' => 2,
            'embeddings' => 1,
            'media' => 2,
            'word' => 1,
        ], $summary['partZipSourceRecordPackagePartDirectoryBaseNameStemCounts']);
        $t->same(
            docx_zip_source_record_directory_base_name_stem_sums($inventory, 'directoryBaseNameStem', 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameStemBytes']
        );
        $t->same(2, $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNameStemCount']);
        $t->same(4, $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNameStemPartCount']);
        $t->same(['_rels', 'media'], $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNameStems']);
        $t->same(0, $summary['partZipSourceRecordPackagePartDirectoryBaseNameStemDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartDirectoryBaseNameStemIssuePartCount']);
        $t->same(5, $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemCount']);
        $t->same([
            '/' => 1,
            '_rels' => 2,
            'embeddings' => 1,
            'media' => 3,
            'word' => 1,
        ], $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemCounts']);
        $t->same(
            docx_zip_source_record_directory_base_name_stem_sums($inventory, 'caseFoldDirectoryBaseNameStem', 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemBytes']
        );
        $t->same(2, $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNameStemCount']);
        $t->same(5, $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNameStemPartCount']);
        $t->same(['_rels', 'media'], $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNameStems']);
        $t->same(0, $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemIssuePartCount']);

        $t->same(
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameStemCount'],
            $identity['partZipSourceRecordPackagePartDirectoryBaseNameStemCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameStemCounts'],
            $identity['partZipSourceRecordPackagePartDirectoryBaseNameStemCounts']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameStemBytes'],
            $identity['partZipSourceRecordPackagePartDirectoryBaseNameStemBytes']
        );
        $t->same(
            $summary['partZipSourceRecordDuplicatePackagePartDirectoryBaseNameStems'],
            $identity['partZipSourceRecordDuplicatePackagePartDirectoryBaseNameStems']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartDirectoryBaseNameStems'],
            $identity['partZipSourceRecordPackagePartDirectoryBaseNameStems']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemCount'],
            $identity['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemCounts'],
            $identity['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemCounts']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemBytes'],
            $identity['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStemBytes']
        );
        $t->same(
            $summary['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNameStems'],
            $identity['partZipSourceRecordDuplicatePackagePartCaseFoldDirectoryBaseNameStems']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStems'],
            $identity['partZipSourceRecordPackagePartCaseFoldDirectoryBaseNameStems']
        );

        $media = $directoryStems['media'];
        $t->same(2, $media['partCount']);
        $t->same(2, $media['directoryCount']);
        $t->same(2, $media['directoryBaseNameVariantCount']);
        $t->same(1, $media['extensionVariantCount']);
        $t->same(2, $media['baseNameVariantCount']);
        $t->same(['media' => 1, 'media.raw' => 1], $media['directoryBaseNameCounts']);
        $t->same(['customXml/media' => 1, 'word/media.raw' => 1], $media['directoryCounts']);
        $t->same(['customXml/' => 1, 'word/' => 1], $media['directoryRootCounts']);
        $t->same(['default' => 2], $media['contentTypeSourceCounts']);
        $t->same(['image/png' => 2], $media['contentTypeBaseCounts']);
        $t->same(['0' => 1, '8' => 1], $media['compressionMethodCounts']);
        $t->same(['document-relationship-target' => 1, 'package-part' => 1], $media['roleCounts']);
        $t->same(['customXml/media/review.png', 'word/media.raw/image.png'], $media['partNames']);
        $t->same(
            docx_zip_source_record_directory_base_name_stem_sum_for_stem(
                $inventory,
                'directoryBaseNameStem',
                'media',
                'sourceRecordBytes'
            ),
            $media['sourceRecordBytes']
        );
        $t->same(false, array_key_exists('contents', $media['largestSourceRecordPart']));

        $upperMedia = $directoryStems['Media'];
        $t->same(1, $upperMedia['partCount']);
        $t->same('Media', $upperMedia['directoryBaseNameStem']);
        $t->same(['application/xml' => 1], $upperMedia['contentTypeBaseCounts']);

        $caseFoldMedia = $caseFoldDirectoryStems['media'];
        $t->same(3, $caseFoldMedia['partCount']);
        $t->same(3, $caseFoldMedia['directoryCount']);
        $t->same(2, $caseFoldMedia['directoryBaseNameStemVariantCount']);
        $t->same(['Media.assets' => 1, 'media' => 1, 'media.raw' => 1], $caseFoldMedia['directoryBaseNameCounts']);
        $t->same([
            'customXml/media' => 1,
            'word/Media.assets' => 1,
            'word/media.raw' => 1,
        ], $caseFoldMedia['directoryCounts']);
        $t->same(['png' => 2, 'xml' => 1], $caseFoldMedia['partExtensionCounts']);
        $t->same(['application/xml' => 1, 'image/png' => 2], $caseFoldMedia['contentTypeBaseCounts']);
        $t->same([
            'custom-xml-part' => 1,
            'document-relationship-target' => 2,
            'package-part' => 1,
        ], $caseFoldMedia['roleCounts']);
        $t->same([
            'customXml/media/review.png',
            'word/Media.assets/source.xml',
            'word/media.raw/image.png',
        ], $caseFoldMedia['partNames']);
        $t->same('media', $caseFoldMedia['largestSourceRecordPart']['caseFoldDirectoryBaseNameStem']);
        $t->same(false, array_key_exists('contents', $caseFoldMedia['largestSourceRecordPart']));
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_directory_base_name_stem_fixture_parts(): array
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
    <w:p><w:r><w:t>ZIP source record directory base-name stems.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        ['name' => 'word/Media.assets/source.xml', 'data' => '<review-source/>', 'compressionMethod' => 8],
        ['name' => 'word/media.raw/image.png', 'data' => str_repeat('I', 96), 'compressionMethod' => 8],
        ['name' => 'customXml/media/review.png', 'data' => str_repeat('R', 128), 'compressionMethod' => 0],
        ['name' => 'word/embeddings/report.bin', 'data' => str_repeat('B', 64), 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_directory_base_name_stem_index_by(array $items, string $key): array
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
function docx_zip_source_record_directory_base_name_stem_sums(
    array $inventory,
    string $stemField,
    string $sumField
): array {
    $sums = [];
    foreach ($inventory as $part) {
        if (($part['zipEntryPresent'] ?? false) !== true) {
            continue;
        }
        $stem = is_string($part[$stemField] ?? null) ? $part[$stemField] : '';
        $sums[$stem] = ($sums[$stem] ?? 0) + (is_int($part[$sumField] ?? null) ? $part[$sumField] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_directory_base_name_stem_sum_for_stem(
    array $inventory,
    string $stemField,
    string $stem,
    string $sumField
): int {
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part['zipEntryPresent'] ?? false) !== true || ($part[$stemField] ?? null) !== $stem) {
            continue;
        }

        $sum += is_int($part[$sumField] ?? null) ? $part[$sumField] : 0;
    }

    return $sum;
}
