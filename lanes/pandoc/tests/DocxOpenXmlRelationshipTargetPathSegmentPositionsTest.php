<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'records docx relationship target path segment position mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'summarizes DOCX relationship target path segment positions for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_target_path_segment_position_fixture_parts();

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $positions = [];
        foreach ($summary['relationshipTargetPathSegmentPositions'] as $position) {
            $positions[$position['position']] = $position;
        }

        $t->same(7, $summary['relationshipCount']);
        $t->same(4, $summary['relationshipTargetPathSegmentPositionBucketCount']);
        $t->same(14, $summary['relationshipTargetPathSegmentPositionOccurrenceCount']);
        $t->same($summary['relationshipTargetPathSegmentOccurrenceCount'], $summary['relationshipTargetPathSegmentPositionOccurrenceCount']);
        $t->same(['first' => 5, 'last' => 5, 'middle' => 3, 'only' => 1], $summary['relationshipTargetPathSegmentPositionCounts']);
        $t->same(['first' => 5, 'last' => 5, 'middle' => 3, 'only' => 1], $summary['relationshipTargetPathSegmentPositionRelationshipCounts']);
        $t->same(['first', 'last', 'middle', 'only'], array_column($summary['relationshipTargetPathSegmentPositions'], 'position'));

        $first = $positions['first'];
        $t->same(5, $first['occurrenceCount']);
        $t->same(5, $first['relationshipCount']);
        $t->same(3, $first['existingTargetCount']);
        $t->same(2, $first['missingTargetCount']);
        $t->same(1, $first['missingContentTypeTargetCount']);
        $t->same(1, $first['parameterizedTargetCount']);
        $t->same(2, $first['uniqueSegmentCount']);
        $t->same(['media' => 1, 'word' => 4], $first['segmentCounts']);
        $t->same(['media', 'word'], $first['segments']);
        $t->same([0 => 5], $first['pathSegmentIndexCounts']);
        $t->same([
            'media' => 1,
            'word' => 1,
            'word/media' => 2,
            'word/review' => 1,
        ], $first['targetDirectoryCounts']);
        $t->same(['default' => 2, 'missing' => 1, 'override' => 2], $first['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            'application/octet-stream' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'image/png' => 2,
        ], $first['contentTypeBaseCounts']);
        $t->same([
            'document-relationship-target' => 2,
            'embedded-package' => 1,
            'missing-relationship-target' => 2,
            'office-document' => 1,
            'root-relationship-target' => 1,
        ], $first['roleCounts']);
        $t->same(['/', 'word/document.xml'], $first['sourceParts']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $first['relationshipParts']);
        $t->same(['rDocument', 'rMissingImage', 'rMissingNoContentType', 'rReviewBin', 'rReviewImage'], $first['relationshipIds']);
        $t->same([
            'media/missing-no-default.unknown',
            'word/document.xml',
            'word/media/missing.png',
            'word/media/review.png',
            'word/review/audit.bin',
        ], $first['targetParts']);
        $t->same(
            strlen($parts['word/document.xml']) + strlen($parts['word/review/audit.bin']) + strlen($parts['word/media/review.png']),
            $first['existingTargetByteLength']
        );
        $t->same('word/document.xml', $first['largestExistingTargetPart']['targetPart']);
        $t->same(['word', 'document.xml'], $first['largestExistingTargetPart']['targetPathSegments']);

        $middle = $positions['middle'];
        $t->same(3, $middle['occurrenceCount']);
        $t->same(3, $middle['relationshipCount']);
        $t->same(2, $middle['existingTargetCount']);
        $t->same(1, $middle['missingTargetCount']);
        $t->same(['media' => 2, 'review' => 1], $middle['segmentCounts']);
        $t->same(['media', 'review'], $middle['segments']);
        $t->same([1 => 3], $middle['pathSegmentIndexCounts']);
        $t->same(['default' => 2, 'override' => 1], $middle['contentTypeSourceCounts']);
        $t->same(['application/octet-stream' => 1, 'image/png' => 2], $middle['contentTypeBaseCounts']);
        $t->same(['rMissingImage', 'rReviewBin', 'rReviewImage'], $middle['relationshipIds']);
        $t->same('word/media/review.png', $middle['largestExistingTargetPart']['targetPart']);

        $last = $positions['last'];
        $t->same(5, $last['occurrenceCount']);
        $t->same(5, $last['relationshipCount']);
        $t->same([
            'audit.bin' => 1,
            'document.xml' => 1,
            'missing-no-default.unknown' => 1,
            'missing.png' => 1,
            'review.png' => 1,
        ], $last['segmentCounts']);
        $t->same([1 => 2, 2 => 3], $last['pathSegmentIndexCounts']);
        $t->same($first['targetParts'], $last['targetParts']);

        $only = $positions['only'];
        $t->same(1, $only['occurrenceCount']);
        $t->same(1, $only['relationshipCount']);
        $t->same(1, $only['existingTargetCount']);
        $t->same(0, $only['missingTargetCount']);
        $t->same(['thumbnail.png' => 1], $only['segmentCounts']);
        $t->same([0 => 1], $only['pathSegmentIndexCounts']);
        $t->same(['default' => 1], $only['contentTypeSourceCounts']);
        $t->same(['image/png' => 1], $only['contentTypeBaseCounts']);
        $t->same(['package-thumbnail' => 1, 'root-relationship-target' => 1], $only['roleCounts']);
        $t->same(['rThumbnail'], $only['relationshipIds']);
        $t->same(['thumbnail.png'], $only['targetParts']);
        $t->same('thumbnail.png', $only['largestExistingTargetPart']['targetPart']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_path_segment_position_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/review/audit.bin" ContentType="application/octet-stream; profile=relationship-target-review"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rThumbnail" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="thumbnail.png"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png?slot=main#asset"/>
  <Relationship Id="rMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rReviewBin" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="review/audit.bin"/>
  <Relationship Id="rMissingNoContentType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../media/missing-no-default.unknown"/>
  <Relationship Id="rExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://cdn.example.test/assets/remote.png" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target path segment position fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/media/review.png' => str_repeat('R', 31),
        'word/review/audit.bin' => str_repeat('B', 17),
        'customXml/item1.xml' => '<item/>',
        'thumbnail.png' => str_repeat('T', 13),
    ];
}
