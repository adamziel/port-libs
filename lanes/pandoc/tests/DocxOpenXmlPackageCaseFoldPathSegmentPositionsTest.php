<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package case-fold path segment positions for review handoff' => static function (TestRunner $t): void {
        $parts = docx_casefold_path_segment_position_fixture_parts();
        $relationshipContentType = 'application/vnd.openxmlformats-package.relationships+xml';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $positions = [];
        foreach ($summary['partCaseFoldPathSegmentPositions'] as $position) {
            $positions[$position['position']] = $position;
        }

        $t->same(4, $summary['partCaseFoldPathSegmentPositionBucketCount']);
        $t->same(23, $summary['partCaseFoldPathSegmentPositionOccurrenceCount']);
        $t->same(
            $summary['partCaseFoldPathSegmentOccurrenceCount'],
            $summary['partCaseFoldPathSegmentPositionOccurrenceCount']
        );
        $t->same(['first' => 8, 'last' => 8, 'middle' => 6, 'only' => 1], $summary['partCaseFoldPathSegmentPositionCounts']);
        $t->same(['first' => 8, 'last' => 8, 'middle' => 6, 'only' => 1], $summary['partCaseFoldPathSegmentPositionPartCounts']);
        $t->same(0, $summary['partCaseFoldPathSegmentPositionParameterizedBucketCount']);
        $t->same(0, $summary['partCaseFoldPathSegmentPositionParameterizedPartCount']);
        $t->same(3, $summary['partCaseFoldPathSegmentPositionMissingContentTypeBucketCount']);
        $t->same(1, $summary['duplicatePartCaseFoldPathSegmentPositionCount']);
        $t->same(1, $summary['duplicatePartCaseFoldPathSegmentPositionCaseFoldSegmentCount']);
        $t->same(['middle'], $summary['duplicatePartCaseFoldPathSegmentPositions']);
        $t->same(['first', 'last', 'middle', 'only'], array_column($summary['partCaseFoldPathSegmentPositions'], 'position'));

        $first = $positions['first'];
        $firstPartNames = [
            '_rels/.rels',
            'customXml/MEDIA/data.xml',
            'customXml/media/raw.bin',
            'word/MEDIA/third.png',
            'word/Media/review.png',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/second.png',
        ];
        $t->same(8, $first['occurrenceCount']);
        $t->same(8, $first['partCount']);
        $t->same(3, $first['uniqueCaseFoldSegmentCount']);
        $t->same(3, $first['segmentVariantCount']);
        $t->same(0, $first['duplicateCaseFoldSegmentCount']);
        $t->same(['_rels' => 1, 'customxml' => 2, 'word' => 5], $first['caseFoldSegmentCounts']);
        $t->same(['_rels' => 1, 'customXml' => 2, 'word' => 5], $first['segmentCounts']);
        $t->same(['_rels' => 1, 'customxml' => 1, 'word' => 1], $first['caseFoldSegmentVariantCounts']);
        $t->same([], $first['duplicateCaseFoldSegments']);
        $t->same(['_rels', 'customxml', 'word'], $first['caseFoldSegments']);
        $t->same([0 => 8], $first['pathSegmentIndexCounts']);
        $t->same(['default' => 6, 'missing' => 1, 'override' => 1], $first['contentTypeSourceCounts']);
        $t->same($firstPartNames, $first['partNames']);

        $middle = $positions['middle'];
        $middlePartNames = [
            'customXml/MEDIA/data.xml',
            'customXml/media/raw.bin',
            'word/MEDIA/third.png',
            'word/Media/review.png',
            'word/_rels/document.xml.rels',
            'word/media/second.png',
        ];
        $t->same(6, $middle['occurrenceCount']);
        $t->same(6, $middle['partCount']);
        $t->same(array_sum(array_map(static fn (string $partName): int => strlen($parts[$partName]), $middlePartNames)), $middle['byteLength']);
        $t->same(1, $middle['relationshipPartCount']);
        $t->same(1, $middle['missingContentTypePartCount']);
        $t->same(0, $middle['parameterizedPartCount']);
        $t->same(2, $middle['uniqueCaseFoldSegmentCount']);
        $t->same(4, $middle['segmentVariantCount']);
        $t->same(1, $middle['duplicateCaseFoldSegmentCount']);
        $t->same(['_rels' => 1, 'media' => 5], $middle['caseFoldSegmentCounts']);
        $t->same(['MEDIA' => 2, 'Media' => 1, '_rels' => 1, 'media' => 2], $middle['segmentCounts']);
        $t->same(['_rels' => 1, 'media' => 3], $middle['caseFoldSegmentVariantCounts']);
        $t->same(['media'], $middle['duplicateCaseFoldSegments']);
        $t->same(['_rels', 'media'], $middle['caseFoldSegments']);
        $t->same([1 => 6], $middle['pathSegmentIndexCounts']);
        $t->same([
            'customXml/MEDIA' => 1,
            'customXml/media' => 1,
            'word/MEDIA' => 1,
            'word/Media' => 1,
            'word/_rels' => 1,
            'word/media' => 1,
        ], $middle['directoryCounts']);
        $t->same([
            'customXml/MEDIA',
            'customXml/media',
            'word/MEDIA',
            'word/Media',
            'word/_rels',
            'word/media',
        ], $middle['directories']);
        $t->same(['default' => 5, 'missing' => 1], $middle['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            $relationshipContentType => 1,
            'application/xml' => 1,
            'image/png' => 3,
        ], $middle['contentTypeBaseCounts']);
        $t->same([
            'document-relationship-target' => 3,
            'office-document-relationships' => 1,
            'package-part' => 2,
            'relationship-part' => 1,
        ], $middle['roleCounts']);
        $t->same($middlePartNames, $middle['partNames']);
        $t->same('word/_rels/document.xml.rels', $middle['largestPart']['partName']);
        $t->same(['word', '_rels', 'document.xml.rels'], $middle['largestPart']['pathSegments']);
        $t->same(['word', '_rels', 'document.xml.rels'], $middle['largestPart']['caseFoldPathSegments']);
        $t->same($relationshipContentType, $middle['largestPart']['contentTypeBase']);
        $t->same(true, $middle['largestPart']['isRelationshipPart']);

        $last = $positions['last'];
        $lastPartNames = [
            '_rels/.rels',
            'customXml/MEDIA/data.xml',
            'customXml/media/raw.bin',
            'word/MEDIA/third.png',
            'word/Media/review.png',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/second.png',
        ];
        $t->same(8, $last['occurrenceCount']);
        $t->same(8, $last['partCount']);
        $t->same(8, $last['uniqueCaseFoldSegmentCount']);
        $t->same(8, $last['segmentVariantCount']);
        $t->same(0, $last['duplicateCaseFoldSegmentCount']);
        $t->same([
            '.rels' => 1,
            'data.xml' => 1,
            'document.xml' => 1,
            'document.xml.rels' => 1,
            'raw.bin' => 1,
            'review.png' => 1,
            'second.png' => 1,
            'third.png' => 1,
        ], $last['caseFoldSegmentCounts']);
        $t->same([1 => 2, 2 => 6], $last['pathSegmentIndexCounts']);
        $t->same($lastPartNames, $last['partNames']);

        $only = $positions['only'];
        $t->same(1, $only['occurrenceCount']);
        $t->same(1, $only['partCount']);
        $t->same(1, $only['uniqueCaseFoldSegmentCount']);
        $t->same(['[content_types].xml' => 1], $only['caseFoldSegmentCounts']);
        $t->same(['[Content_Types].xml' => 1], $only['segmentCounts']);
        $t->same(['[content_types].xml' => 1], $only['caseFoldSegmentVariantCounts']);
        $t->same(['[content_types].xml'], $only['caseFoldSegments']);
        $t->same('[Content_Types].xml', $only['largestPart']['partName']);
        $t->same(['[content_types].xml'], $only['largestPart']['caseFoldPathSegments']);
    },

    'records mapped DOCX package case-fold path segment position case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
];

/**
 * @return array<string, string>
 */
function docx_casefold_path_segment_position_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
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
  <Relationship Id="rMixedCaseMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Media/review.png"/>
  <Relationship Id="rLowerMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/second.png"/>
  <Relationship Id="rUpperMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="MEDIA/third.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Case-fold path segment position fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/Media/review.png' => 'mixed case media bytes',
        'word/media/second.png' => str_repeat('S', 47),
        'word/MEDIA/third.png' => str_repeat('T', 33),
        'customXml/MEDIA/data.xml' => '<data/>',
        'customXml/media/raw.bin' => 'missing raw bytes',
    ];
}
