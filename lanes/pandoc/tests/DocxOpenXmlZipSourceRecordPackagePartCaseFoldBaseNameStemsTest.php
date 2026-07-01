<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes DOCX ZIP source records by package part case-fold base-name stems' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_case_fold_base_name_stem_fixture_parts(),
            'docx source record case-fold base-name stem review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $caseFoldStems = docx_zip_source_record_case_fold_base_name_stem_index_by(
            $summary['partZipSourceRecordPackagePartCaseFoldBaseNameStems'],
            'caseFoldBaseNameStem'
        );

        $t->same(6, $summary['partZipSourceRecordPackagePartCaseFoldBaseNameStemCount']);
        $t->same([
            '.rels' => 1,
            '[content_types]' => 1,
            'document' => 1,
            'document.xml' => 1,
            'report' => 2,
            'review' => 2,
        ], $summary['partZipSourceRecordPackagePartCaseFoldBaseNameStemCounts']);
        $t->same(
            docx_zip_source_record_case_fold_base_name_stem_sums($inventory, 'sourceRecordBytes'),
            $summary['partZipSourceRecordPackagePartCaseFoldBaseNameStemBytes']
        );
        $t->same(2, $summary['partZipSourceRecordDuplicatePackagePartCaseFoldBaseNameStemCount']);
        $t->same(4, $summary['partZipSourceRecordDuplicatePackagePartCaseFoldBaseNameStemPartCount']);
        $t->same(['report', 'review'], $summary['partZipSourceRecordDuplicatePackagePartCaseFoldBaseNameStems']);
        $t->same(0, $summary['partZipSourceRecordPackagePartCaseFoldBaseNameStemDataDescriptorPartCount']);
        $t->same(0, $summary['partZipSourceRecordPackagePartCaseFoldBaseNameStemIssuePartCount']);

        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldBaseNameStemCount'],
            $identity['partZipSourceRecordPackagePartCaseFoldBaseNameStemCount']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldBaseNameStemCounts'],
            $identity['partZipSourceRecordPackagePartCaseFoldBaseNameStemCounts']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldBaseNameStemBytes'],
            $identity['partZipSourceRecordPackagePartCaseFoldBaseNameStemBytes']
        );
        $t->same(
            $summary['partZipSourceRecordDuplicatePackagePartCaseFoldBaseNameStems'],
            $identity['partZipSourceRecordDuplicatePackagePartCaseFoldBaseNameStems']
        );
        $t->same(
            $summary['partZipSourceRecordPackagePartCaseFoldBaseNameStems'],
            $identity['partZipSourceRecordPackagePartCaseFoldBaseNameStems']
        );

        $review = $caseFoldStems['review'];
        $t->same(2, $review['partCount']);
        $t->same(2, $review['baseNameStemVariantCount']);
        $t->same(2, $review['baseNameVariantCount']);
        $t->same(1, $review['extensionVariantCount']);
        $t->same(['Review' => 1, 'review' => 1], $review['baseNameStemCounts']);
        $t->same(['Review.PNG' => 1, 'review.png' => 1], $review['baseNameCounts']);
        $t->same(['png' => 2], $review['partExtensionCounts']);
        $t->same(['customXml/' => 1, 'word/' => 1], $review['directoryRootCounts']);
        $t->same(['default' => 2], $review['contentTypeSourceCounts']);
        $t->same(['image/png' => 2], $review['contentTypeBaseCounts']);
        $t->same(['0' => 1, '8' => 1], $review['compressionMethodCounts']);
        $t->same([
            'document-relationship-target' => 1,
            'package-part' => 1,
        ], $review['roleCounts']);
        $t->same(['customXml/review.png', 'word/media/Review.PNG'], $review['partNames']);
        $t->same(
            docx_zip_source_record_case_fold_base_name_stem_sum_for_stem($inventory, 'review', 'sourceRecordBytes'),
            $review['sourceRecordBytes']
        );
        $t->same(
            docx_zip_source_record_case_fold_base_name_stem_sum_for_stem($inventory, 'review', 'centralDirectoryRecordBytes'),
            $review['centralDirectoryRecordBytes']
        );
        $t->same('review', $review['largestSourceRecordPart']['caseFoldBaseNameStem']);
        $t->same(false, array_key_exists('contents', $review['largestSourceRecordPart']));

        $report = $caseFoldStems['report'];
        $t->same(2, $report['partCount']);
        $t->same(2, $report['baseNameStemVariantCount']);
        $t->same(2, $report['baseNameVariantCount']);
        $t->same(2, $report['extensionVariantCount']);
        $t->same(['Report' => 1, 'report' => 1], $report['baseNameStemCounts']);
        $t->same(['Report.bin' => 1, 'report.ole' => 1], $report['baseNameCounts']);
        $t->same(['bin' => 1, 'ole' => 1], $report['partExtensionCounts']);
        $t->same(['embeddings/' => 2], $report['directoryRootCounts']);
        $t->same(['default' => 1, 'missing' => 1], $report['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            'application/octet-stream' => 1,
        ], $report['contentTypeBaseCounts']);
        $t->same(['0' => 2], $report['compressionMethodCounts']);
        $t->same(['package-part' => 2], $report['roleCounts']);
        $t->same(['embeddings/Report.bin', 'embeddings/report.ole'], $report['partNames']);
        $t->same('embeddings/report.ole', $report['largestSourceRecordPart']['partName']);
        $t->same('report', $report['largestSourceRecordPart']['caseFoldBaseNameStem']);
        $t->same('', $report['largestSourceRecordPart']['contentTypeBase']);
        $t->same('missing', $report['largestSourceRecordPart']['contentTypeSource']);
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, comment?:string}>
 */
function docx_zip_source_record_case_fold_base_name_stem_fixture_parts(): array
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
  <Relationship Id="rReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Review.PNG"/>
</Relationships>
XML,
        ],
        ['name' => 'word/document.xml', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>ZIP source record case-fold base-name stems.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        ['name' => 'word/media/Review.PNG', 'data' => str_repeat('R', 96), 'compressionMethod' => 8],
        ['name' => 'customXml/review.png', 'data' => str_repeat('C', 64), 'compressionMethod' => 0],
        ['name' => 'embeddings/Report.bin', 'data' => str_repeat('B', 48), 'compressionMethod' => 0],
        ['name' => 'embeddings/report.ole', 'data' => str_repeat('O', 160), 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_zip_source_record_case_fold_base_name_stem_index_by(array $items, string $key): array
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
function docx_zip_source_record_case_fold_base_name_stem_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        $stem = is_string($part['caseFoldBaseNameStem'] ?? null)
            ? $part['caseFoldBaseNameStem']
            : docx_zip_source_record_case_fold_base_name_stem_key((string) ($part['baseNameStem'] ?? ''));
        $sums[$stem] = ($sums[$stem] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function docx_zip_source_record_case_fold_base_name_stem_sum_for_stem(
    array $inventory,
    string $stem,
    string $field
): int {
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part['caseFoldBaseNameStem'] ?? null) !== $stem) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}

function docx_zip_source_record_case_fold_base_name_stem_key(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}
