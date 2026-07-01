<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

$tests = [
    'records docx package path segment position mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'summarizes docx package path segment positions for reviewer handoff' => static function (TestRunner $t): void {
        $parts = docx_package_path_segment_position_fixture_parts();
        $relationshipContentType = 'application/vnd.openxmlformats-package.relationships+xml';
        $documentContentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $inventory = $package['parts'];
        $positions = [];
        foreach ($summary['partPathSegmentPositions'] as $position) {
            $positions[$position['position']] = $position;
        }
        $segments = [];
        foreach ($summary['partPathSegments'] as $segment) {
            $segments[$segment['segment']] = $segment;
        }
        $expectedSegmentCounts = [
            '.rels' => 1,
            '[Content_Types].xml' => 1,
            '_rels' => 2,
            'customXml' => 2,
            'data.bin' => 1,
            'deep' => 1,
            'document.xml' => 1,
            'document.xml.rels' => 1,
            'item.xml' => 1,
            'media' => 2,
            'review' => 2,
            'review.png' => 1,
            'root-note.xml' => 1,
            'scan.png' => 1,
            'word' => 4,
        ];

        $t->same(15, $summary['partPathSegmentCount']);
        $t->same(22, $summary['partPathSegmentOccurrenceCount']);
        $t->same($expectedSegmentCounts, $summary['partPathSegmentCounts']);
        $t->same($expectedSegmentCounts, $summary['partPathSegmentOccurrenceCounts']);
        $t->same(1, $summary['partPathSegmentParameterizedBucketCount']);
        $t->same(1, $summary['partPathSegmentParameterizedPartCount']);
        $t->same(3, $summary['partPathSegmentMissingContentTypeBucketCount']);
        $t->same([
            '.rels',
            '[Content_Types].xml',
            '_rels',
            'customXml',
            'data.bin',
            'deep',
            'document.xml',
            'document.xml.rels',
            'item.xml',
            'media',
            'review',
            'review.png',
            'root-note.xml',
            'scan.png',
            'word',
        ], array_column($summary['partPathSegments'], 'segment'));
        $t->same(4, $summary['partPathSegmentPositionBucketCount']);
        $t->same($summary['partPathSegmentOccurrenceCount'], $summary['partPathSegmentPositionOccurrenceCount']);
        $t->same(['first' => 7, 'last' => 7, 'middle' => 6, 'only' => 2], $summary['partPathSegmentPositionCounts']);
        $t->same(['first' => 7, 'last' => 7, 'middle' => 5, 'only' => 2], $summary['partPathSegmentPositionPartCounts']);
        $t->same(1, $summary['partPathSegmentPositionParameterizedBucketCount']);
        $t->same(1, $summary['partPathSegmentPositionParameterizedPartCount']);
        $t->same(3, $summary['partPathSegmentPositionMissingContentTypeBucketCount']);
        $t->same(['first', 'last', 'middle', 'only'], array_column($summary['partPathSegmentPositions'], 'position'));
        $t->same($summary['partPathSegmentPositionBucketCount'], $identity['packagePathSegmentPositionBucketCount']);
        $t->same(
            $summary['partPathSegmentPositionOccurrenceCount'],
            $identity['packagePathSegmentPositionOccurrenceCount']
        );
        $t->same($summary['partPathSegmentPositionCounts'], $identity['packagePathSegmentPositionCounts']);
        $t->same($summary['partPathSegmentPositionPartCounts'], $identity['packagePathSegmentPositionPartCounts']);
        $t->same(
            $summary['partPathSegmentPositionParameterizedBucketCount'],
            $identity['packagePathSegmentPositionParameterizedBucketCount']
        );
        $t->same(
            $summary['partPathSegmentPositionParameterizedPartCount'],
            $identity['packagePathSegmentPositionParameterizedPartCount']
        );
        $t->same(
            $summary['partPathSegmentPositionMissingContentTypeBucketCount'],
            $identity['packagePathSegmentPositionMissingContentTypeBucketCount']
        );
        $t->same($summary['partPathSegmentPositions'], $identity['packagePathSegmentPositions']);

        $word = $segments['word'];
        $wordPartNames = [
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ];
        $t->same('word', $word['segment']);
        $t->same('word', $word['caseFoldSegment']);
        $t->same(4, $word['occurrenceCount']);
        $t->same(4, $word['partCount']);
        $t->same(array_sum(array_map(static fn (string $partName): int => strlen($parts[$partName]), $wordPartNames)), $word['byteLength']);
        $t->same(1, $word['relationshipPartCount']);
        $t->same(0, $word['missingContentTypePartCount']);
        $t->same(0, $word['parameterizedPartCount']);
        $t->same([0 => 4], $word['pathSegmentIndexCounts']);
        $t->same([2 => 1, 3 => 2, 4 => 1], $word['pathDepthCounts']);
        $t->same(['word' => 4], $word['topLevelSegmentCounts']);
        $t->same([
            'word' => 1,
            'word/_rels' => 1,
            'word/media' => 1,
            'word/media/deep' => 1,
        ], $word['directoryCounts']);
        $t->same(['word', 'word/_rels', 'word/media', 'word/media/deep'], $word['directories']);
        $t->same(['default' => 3, 'override' => 1], $word['contentTypeSourceCounts']);
        $t->same([
            $documentContentType => 1,
            $relationshipContentType => 1,
            'image/png' => 2,
        ], $word['contentTypeBaseCounts']);
        $t->same([
            'document-relationship-target' => 2,
            'office-document' => 1,
            'office-document-relationships' => 1,
            'relationship-part' => 1,
            'root-relationship-target' => 1,
        ], $word['roleCounts']);
        $t->same($wordPartNames, $word['partNames']);
        $t->same('word/_rels/document.xml.rels', $word['largestPart']['partName']);
        $t->same(['word', '_rels', 'document.xml.rels'], $word['largestPart']['pathSegments']);
        $t->same($relationshipContentType, $word['largestPart']['contentTypeBase']);
        $t->same(true, $word['largestPart']['isRelationshipPart']);
        $t->same(false, array_key_exists('contents', $word['largestPart']));

        $rootNote = $segments['root-note.xml'];
        $t->same(1, $rootNote['occurrenceCount']);
        $t->same(1, $rootNote['partCount']);
        $t->same(1, $rootNote['parameterizedPartCount']);
        $t->same(['override' => 1], $rootNote['contentTypeSourceCounts']);
        $t->same(['application/xml' => 1], $rootNote['contentTypeBaseCounts']);
        $t->same(['custom-xml-part' => 1, 'root-relationship-target' => 1], $rootNote['roleCounts']);
        $t->same(['root-note.xml'], $rootNote['partNames']);

        $customXml = $segments['customXml'];
        $t->same(2, $customXml['occurrenceCount']);
        $t->same(2, $customXml['partCount']);
        $t->same(1, $customXml['missingContentTypePartCount']);
        $t->same([0 => 2], $customXml['pathSegmentIndexCounts']);
        $t->same(['customXml/review' => 2], $customXml['directoryCounts']);
        $t->same(['default' => 1, 'missing' => 1], $customXml['contentTypeSourceCounts']);
        $t->same(['package-part' => 2], $customXml['roleCounts']);

        $t->same([
            [
                'pathSegmentIndex' => 0,
                'segment' => 'word',
                'position' => 'first',
                'isFirst' => true,
                'isLast' => false,
                'isOnly' => false,
            ],
            [
                'pathSegmentIndex' => 1,
                'segment' => 'media',
                'position' => 'middle',
                'isFirst' => false,
                'isLast' => false,
                'isOnly' => false,
            ],
            [
                'pathSegmentIndex' => 2,
                'segment' => 'deep',
                'position' => 'middle',
                'isFirst' => false,
                'isLast' => false,
                'isOnly' => false,
            ],
            [
                'pathSegmentIndex' => 3,
                'segment' => 'scan.png',
                'position' => 'last',
                'isFirst' => false,
                'isLast' => true,
                'isOnly' => false,
            ],
        ], $inventory['word/media/deep/scan.png']['pathSegmentPositionReviews']);

        $first = $positions['first'];
        $firstPartNames = [
            '_rels/.rels',
            'customXml/review/data.bin',
            'customXml/review/item.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ];
        $t->same(7, $first['occurrenceCount']);
        $t->same(7, $first['partCount']);
        $t->same(array_sum(array_map(static fn (string $partName): int => strlen($parts[$partName]), $firstPartNames)), $first['byteLength']);
        $t->same(2, $first['relationshipPartCount']);
        $t->same(1, $first['missingContentTypePartCount']);
        $t->same(3, $first['uniqueSegmentCount']);
        $t->same(['_rels' => 1, 'customXml' => 2, 'word' => 4], $first['segmentCounts']);
        $t->same(['_rels', 'customXml', 'word'], $first['segments']);
        $t->same([0 => 7], $first['pathSegmentIndexCounts']);
        $t->same([
            '_rels' => 1,
            'customXml/review' => 2,
            'word' => 1,
            'word/_rels' => 1,
            'word/media' => 1,
            'word/media/deep' => 1,
        ], $first['directoryCounts']);
        $t->same(['default' => 5, 'missing' => 1, 'override' => 1], $first['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            $documentContentType => 1,
            $relationshipContentType => 2,
            'application/xml' => 1,
            'image/png' => 2,
        ], $first['contentTypeBaseCounts']);
        $t->same([
            'document-relationship-target' => 2,
            'office-document' => 1,
            'office-document-relationships' => 1,
            'package-part' => 2,
            'package-relationships' => 1,
            'relationship-part' => 2,
            'root-relationship-target' => 1,
        ], $first['roleCounts']);
        $t->same($firstPartNames, $first['partNames']);

        $middle = $positions['middle'];
        $middlePartNames = [
            'customXml/review/data.bin',
            'customXml/review/item.xml',
            'word/_rels/document.xml.rels',
            'word/media/deep/scan.png',
            'word/media/review.png',
        ];
        $t->same(6, $middle['occurrenceCount']);
        $t->same(5, $middle['partCount']);
        $t->same(array_sum(array_map(static fn (string $partName): int => strlen($parts[$partName]), $middlePartNames)), $middle['byteLength']);
        $t->same(1, $middle['relationshipPartCount']);
        $t->same(1, $middle['missingContentTypePartCount']);
        $t->same(4, $middle['uniqueSegmentCount']);
        $t->same(['_rels' => 1, 'deep' => 1, 'media' => 2, 'review' => 2], $middle['segmentCounts']);
        $t->same([1 => 5, 2 => 1], $middle['pathSegmentIndexCounts']);
        $t->same([
            '(missing)' => 1,
            $relationshipContentType => 1,
            'application/xml' => 1,
            'image/png' => 2,
        ], $middle['contentTypeBaseCounts']);
        $t->same([
            'document-relationship-target' => 2,
            'office-document-relationships' => 1,
            'package-part' => 2,
            'relationship-part' => 1,
        ], $middle['roleCounts']);
        $t->same($middlePartNames, $middle['partNames']);
        $t->same('word/_rels/document.xml.rels', $middle['largestPart']['partName']);
        $t->same(['word', '_rels', 'document.xml.rels'], $middle['largestPart']['pathSegments']);
        $t->same($relationshipContentType, $middle['largestPart']['contentTypeBase']);

        $last = $positions['last'];
        $t->same(7, $last['occurrenceCount']);
        $t->same(7, $last['partCount']);
        $t->same([
            '.rels' => 1,
            'data.bin' => 1,
            'document.xml' => 1,
            'document.xml.rels' => 1,
            'item.xml' => 1,
            'review.png' => 1,
            'scan.png' => 1,
        ], $last['segmentCounts']);
        $t->same([1 => 2, 2 => 4, 3 => 1], $last['pathSegmentIndexCounts']);
        $t->same($firstPartNames, $last['partNames']);

        $only = $positions['only'];
        $onlyPartNames = ['[Content_Types].xml', 'root-note.xml'];
        $t->same(2, $only['occurrenceCount']);
        $t->same(2, $only['partCount']);
        $t->same(array_sum(array_map(static fn (string $partName): int => strlen($parts[$partName]), $onlyPartNames)), $only['byteLength']);
        $t->same(1, $only['parameterizedPartCount']);
        $t->same(0, $only['missingContentTypePartCount']);
        $t->same(['[Content_Types].xml' => 1, 'root-note.xml' => 1], $only['segmentCounts']);
        $t->same([0 => 2], $only['pathSegmentIndexCounts']);
        $t->same(['/' => 2], $only['directoryCounts']);
        $t->same(['default' => 1, 'override' => 1], $only['contentTypeSourceCounts']);
        $t->same(['application/xml' => 2], $only['contentTypeBaseCounts']);
        $t->same([
            'content-types' => 1,
            'custom-xml-part' => 1,
            'root-relationship-target' => 1,
        ], $only['roleCounts']);
        $t->same($onlyPartNames, $only['partNames']);
        $t->same('[Content_Types].xml', $only['largestPart']['partName']);
        $t->same(false, array_key_exists('contents', $only['largestPart']));
    },
];

return $tests;

/**
 * @return array<string, string>
 */
function docx_package_path_segment_position_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/root-note.xml" ContentType="application/xml; profile=position"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rRootNote" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="root-note.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rDeepScan" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/deep/scan.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Package path position fixture.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        'root-note.xml' => '<root-note/>',
        'word/media/review.png' => 'review png bytes',
        'word/media/deep/scan.png' => 'SSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSS',
        'customXml/review/item.xml' => '<item/>',
        'customXml/review/data.bin' => 'missing binary bytes',
    ];
}
