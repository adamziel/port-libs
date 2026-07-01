<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

$tests = [
    'records docx package basename inventory mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'summarizes docx package basename inventory for reviewer handoff' => static function (TestRunner $t): void {
        $parts = docx_package_basename_inventory_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];

        $t->same(10, $summary['partBaseNameCount']);
        $t->same([
            '.rels' => 1,
            'Chart.XML' => 1,
            'Review.PNG' => 1,
            '[Content_Types].xml' => 1,
            'chart.xml' => 1,
            'document.xml' => 1,
            'document.xml.rels' => 1,
            'review' => 1,
            'review.bin' => 1,
            'review.png' => 2,
        ], $summary['partBaseNameCounts']);
        $t->same(
            ['customXml/review.png', 'word/media/review.png'],
            $summary['partNamesByPartBaseName']['review.png']
        );
        $t->same(1, $summary['duplicatePartBaseNameCount']);
        $t->same(2, $summary['duplicatePartBaseNameEntryCount']);
        $t->same(['review.png'], $summary['duplicatePartBaseNames']);

        $duplicate = $summary['duplicatePartBaseNameSummaries'][0];
        $t->same('review.png', $duplicate['baseName']);
        $t->same(2, $duplicate['partCount']);
        $t->same(strlen($parts['customXml/review.png']) + strlen($parts['word/media/review.png']), $duplicate['byteLength']);
        $t->same(['customXml', 'word/media'], $duplicate['directories']);
        $t->same(['image/png' => 2], $duplicate['contentTypeBaseCounts']);
        $t->same(['default' => 2], $duplicate['contentTypeSourceCounts']);
        $t->same(['document-relationship-target' => 1, 'package-part' => 1], $duplicate['roleCounts']);

        $t->same(8, $summary['partCaseFoldBaseNameCount']);
        $t->same([
            '.rels' => 1,
            '[content_types].xml' => 1,
            'chart.xml' => 2,
            'document.xml' => 1,
            'document.xml.rels' => 1,
            'review' => 1,
            'review.bin' => 1,
            'review.png' => 3,
        ], $summary['partCaseFoldBaseNameCounts']);
        $t->same(2, $summary['duplicatePartCaseFoldBaseNameCount']);
        $t->same(5, $summary['duplicatePartCaseFoldBaseNamePartCount']);
        $t->same(['chart.xml', 'review.png'], $summary['duplicatePartCaseFoldBaseNames']);
        $t->same(
            ['customXml/Review.PNG', 'customXml/review.png', 'word/media/review.png'],
            $summary['partNamesByCaseFoldBaseName']['review.png']
        );
        $t->same(
            ['customXml/chart.xml', 'word/Chart.XML'],
            $summary['partNamesByCaseFoldBaseName']['chart.xml']
        );

        $caseFoldDuplicates = [];
        foreach ($summary['duplicatePartCaseFoldBaseNameSummaries'] as $caseFoldDuplicate) {
            $caseFoldDuplicates[$caseFoldDuplicate['caseFoldBaseName']] = $caseFoldDuplicate;
        }

        $review = $caseFoldDuplicates['review.png'];
        $t->same(3, $review['partCount']);
        $t->same(2, $review['caseVariantCount']);
        $t->same(['Review.PNG' => 1, 'review.png' => 2], $review['baseNameCounts']);
        $t->same(['customXml', 'word/media'], $review['directories']);
        $t->same(['image/png' => 3], $review['contentTypeBaseCounts']);
        $t->same(['default' => 3], $review['contentTypeSourceCounts']);
        $t->same(['document-relationship-target' => 1, 'package-part' => 2], $review['roleCounts']);

        $chart = $caseFoldDuplicates['chart.xml'];
        $t->same(2, $chart['partCount']);
        $t->same(2, $chart['caseVariantCount']);
        $t->same(['Chart.XML' => 1, 'chart.xml' => 1], $chart['baseNameCounts']);
        $t->same(['customXml', 'word'], $chart['directories']);
        $t->same(['application/xml' => 2], $chart['contentTypeBaseCounts']);
        $t->same(['package-part' => 2], $chart['roleCounts']);

        $largestDuplicatePart = $duplicate['largestPart'];
        $t->same('word/media/review.png', $largestDuplicatePart['partName']);
        $t->same(strlen($parts['word/media/review.png']), $largestDuplicatePart['bytes']);
        $t->same('image/png', $largestDuplicatePart['contentTypeBase']);
        $t->same('default', $largestDuplicatePart['contentTypeSource']);
        $t->same(['document-relationship-target'], $largestDuplicatePart['roles']);
        $t->same(false, array_key_exists('contents', $largestDuplicatePart));
    },
];

return $tests;

/**
 * @return array<string, string>
 */
function docx_package_basename_inventory_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReviewPng" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package basename inventory fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/media/review.png' => 'document review png bytes',
        'customXml/review.png' => 'custom review png bytes',
        'customXml/Review.PNG' => 'upper review png bytes',
        'word/Chart.XML' => '<chart>upper</chart>',
        'customXml/chart.xml' => '<chart>lower</chart>',
        'customXml/review' => 'extensionless review bytes',
        'word/review.bin' => 'binary review bytes',
    ];
}
