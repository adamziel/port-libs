<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target case-fold path segment positions for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_target_casefold_path_segment_position_fixture_parts();
        $customXmlRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $officeDocumentRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $positions = [];
        foreach ($summary['relationshipTargetCaseFoldPathSegmentPositions'] as $position) {
            $positions[$position['position']] = $position;
        }
        $allRelationshipIds = array_merge(...array_column($summary['relationshipTargetCaseFoldPathSegmentPositions'], 'relationshipIds'));

        $t->same(4, $summary['relationshipTargetCaseFoldPathSegmentPositionBucketCount']);
        $t->same(20, $summary['relationshipTargetCaseFoldPathSegmentPositionOccurrenceCount']);
        $t->same(
            $summary['relationshipTargetCaseFoldPathSegmentOccurrenceCount'],
            $summary['relationshipTargetCaseFoldPathSegmentPositionOccurrenceCount']
        );
        $t->same(['first' => 7, 'last' => 7, 'middle' => 5, 'only' => 1], $summary['relationshipTargetCaseFoldPathSegmentPositionCounts']);
        $t->same(['first' => 7, 'last' => 7, 'middle' => 5, 'only' => 1], $summary['relationshipTargetCaseFoldPathSegmentPositionRelationshipCounts']);
        $t->same(1, $summary['relationshipTargetCaseFoldPathSegmentPositionParameterizedBucketCount']);
        $t->same(1, $summary['relationshipTargetCaseFoldPathSegmentPositionParameterizedRelationshipCount']);
        $t->same(3, $summary['relationshipTargetCaseFoldPathSegmentPositionMissingContentTypeBucketCount']);
        $t->same(2, $summary['duplicateRelationshipTargetCaseFoldPathSegmentPositionCount']);
        $t->same(2, $summary['duplicateRelationshipTargetCaseFoldPathSegmentPositionCaseFoldSegmentCount']);
        $t->same(['last', 'middle'], $summary['duplicateRelationshipTargetCaseFoldPathSegmentPositions']);
        $t->same(['first', 'last', 'middle', 'only'], array_column($summary['relationshipTargetCaseFoldPathSegmentPositions'], 'position'));
        $t->true(!in_array('rExternalMedia', $allRelationshipIds, true), 'external target should not enter case-fold target position buckets');

        $first = $positions['first'];
        $t->same(7, $first['occurrenceCount']);
        $t->same(7, $first['relationshipCount']);
        $t->same(6, $first['existingTargetCount']);
        $t->same(1, $first['missingTargetCount']);
        $t->same(1, $first['missingContentTypeTargetCount']);
        $t->same(0, $first['parameterizedTargetCount']);
        $t->same(2, $first['uniqueCaseFoldSegmentCount']);
        $t->same(2, $first['segmentVariantCount']);
        $t->same(0, $first['duplicateCaseFoldSegmentCount']);
        $t->same(['customxml' => 1, 'word' => 6], $first['caseFoldSegmentCounts']);
        $t->same(['customXml' => 1, 'word' => 6], $first['segmentCounts']);
        $t->same(['customxml' => 1, 'word' => 1], $first['caseFoldSegmentVariantCounts']);
        $t->same([], $first['duplicateCaseFoldSegments']);
        $t->same(['customxml', 'word'], $first['caseFoldSegments']);
        $t->same([0 => 7], $first['pathSegmentIndexCounts']);
        $t->same([
            'customXml' => 1,
            'word' => 1,
            'word/MEDIA' => 1,
            'word/Media' => 2,
            'word/media' => 1,
            'word/raw' => 1,
        ], $first['targetDirectoryCounts']);
        $t->same(['default' => 5, 'missing' => 1, 'override' => 1], $first['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'application/xml' => 1,
            'image/png' => 4,
        ], $first['contentTypeBaseCounts']);
        $t->same([
            $customXmlRel => 2,
            $imageRel => 4,
            $officeDocumentRel => 1,
        ], $first['relationshipTypeCounts']);
        $t->same(['/', 'word/document.xml'], $first['sourceParts']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $first['relationshipParts']);
        $t->same([
            'rCustomXml',
            'rDocument',
            'rLowerMedia',
            'rMissingMedia',
            'rMixedMedia',
            'rNoContentType',
            'rUpperMedia',
        ], $first['relationshipIds']);
        $t->same([
            'customXml/Item.XML',
            'word/MEDIA/REVIEW.png',
            'word/Media/Missing.png',
            'word/Media/Review.PNG',
            'word/document.xml',
            'word/media/review.png',
            'word/raw/NoDefault.UNKNOWN',
        ], $first['targetParts']);
        $t->same('word/document.xml', $first['largestExistingTargetPart']['targetPart']);
        $t->same(['word', 'document.xml'], $first['largestExistingTargetPart']['targetPathSegments']);
        $t->same(['word', 'document.xml'], $first['largestExistingTargetPart']['caseFoldPathSegments']);

        $middle = $positions['middle'];
        $t->same(5, $middle['occurrenceCount']);
        $t->same(5, $middle['relationshipCount']);
        $t->same(4, $middle['existingTargetCount']);
        $t->same(1, $middle['missingTargetCount']);
        $t->same(1, $middle['missingContentTypeTargetCount']);
        $t->same(2, $middle['uniqueCaseFoldSegmentCount']);
        $t->same(4, $middle['segmentVariantCount']);
        $t->same(1, $middle['duplicateCaseFoldSegmentCount']);
        $t->same(['media' => 4, 'raw' => 1], $middle['caseFoldSegmentCounts']);
        $t->same(['MEDIA' => 1, 'Media' => 2, 'media' => 1, 'raw' => 1], $middle['segmentCounts']);
        $t->same(['media' => 3, 'raw' => 1], $middle['caseFoldSegmentVariantCounts']);
        $t->same(['media'], $middle['duplicateCaseFoldSegments']);
        $t->same(['media', 'raw'], $middle['caseFoldSegments']);
        $t->same([1 => 5], $middle['pathSegmentIndexCounts']);
        $t->same(['default' => 4, 'missing' => 1], $middle['contentTypeSourceCounts']);
        $t->same(['(missing)' => 1, 'image/png' => 4], $middle['contentTypeBaseCounts']);
        $t->same([$customXmlRel => 1, $imageRel => 4], $middle['relationshipTypeCounts']);
        $t->same(['rLowerMedia', 'rMissingMedia', 'rMixedMedia', 'rNoContentType', 'rUpperMedia'], $middle['relationshipIds']);
        $t->same('word/MEDIA/REVIEW.png', $middle['largestExistingTargetPart']['targetPart']);
        $t->same(['word', 'MEDIA', 'REVIEW.png'], $middle['largestExistingTargetPart']['targetPathSegments']);
        $t->same(['word', 'media', 'review.png'], $middle['largestExistingTargetPart']['caseFoldPathSegments']);

        $last = $positions['last'];
        $t->same(7, $last['occurrenceCount']);
        $t->same(7, $last['relationshipCount']);
        $t->same(6, $last['existingTargetCount']);
        $t->same(1, $last['missingTargetCount']);
        $t->same(5, $last['uniqueCaseFoldSegmentCount']);
        $t->same(7, $last['segmentVariantCount']);
        $t->same(1, $last['duplicateCaseFoldSegmentCount']);
        $t->same([
            'document.xml' => 1,
            'item.xml' => 1,
            'missing.png' => 1,
            'nodefault.unknown' => 1,
            'review.png' => 3,
        ], $last['caseFoldSegmentCounts']);
        $t->same(['review.png'], $last['duplicateCaseFoldSegments']);
        $t->same([1 => 2, 2 => 5], $last['pathSegmentIndexCounts']);
        $t->same($first['relationshipIds'], $last['relationshipIds']);
        $t->same($first['targetParts'], $last['targetParts']);
        $t->same('word/document.xml', $last['largestExistingTargetPart']['targetPart']);
        $t->same(['word', 'document.xml'], $last['largestExistingTargetPart']['caseFoldPathSegments']);

        $only = $positions['only'];
        $t->same(1, $only['occurrenceCount']);
        $t->same(1, $only['relationshipCount']);
        $t->same(1, $only['existingTargetCount']);
        $t->same(0, $only['missingTargetCount']);
        $t->same(1, $only['parameterizedTargetCount']);
        $t->same(1, $only['uniqueCaseFoldSegmentCount']);
        $t->same(1, $only['segmentVariantCount']);
        $t->same(0, $only['duplicateCaseFoldSegmentCount']);
        $t->same(['root-position.xml' => 1], $only['caseFoldSegmentCounts']);
        $t->same(['Root-Position.XML' => 1], $only['segmentCounts']);
        $t->same(['root-position.xml' => 1], $only['caseFoldSegmentVariantCounts']);
        $t->same(['root-position.xml'], $only['caseFoldSegments']);
        $t->same([0 => 1], $only['pathSegmentIndexCounts']);
        $t->same(['/' => 1], $only['targetDirectoryCounts']);
        $t->same(['application/xml' => 1], $only['contentTypeBaseCounts']);
        $t->same(['override' => 1], $only['contentTypeSourceCounts']);
        $t->same([$customXmlRel => 1], $only['relationshipTypeCounts']);
        $t->same(['rOnlyPosition'], $only['relationshipIds']);
        $t->same(['Root-Position.XML'], $only['targetParts']);
        $t->same('Root-Position.XML', $only['largestExistingTargetPart']['targetPart']);
        $t->same(['Root-Position.XML'], $only['largestExistingTargetPart']['targetPathSegments']);
        $t->same(['root-position.xml'], $only['largestExistingTargetPart']['caseFoldPathSegments']);
        $t->same(true, $only['largestExistingTargetPart']['targetContentTypeHasParameters']);
    },

    'records mapped DOCX relationship target case-fold path segment position case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_casefold_path_segment_position_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="PNG" ContentType="image/png"/>
  <Default Extension="XML" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/Root-Position.XML" ContentType="application/xml; profile=casefold-position"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rOnlyPosition" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="Root-Position.XML?slot=only#target"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMixedMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Media/Review.PNG"/>
  <Relationship Id="rLowerMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rUpperMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="MEDIA/REVIEW.png"/>
  <Relationship Id="rCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/Item.XML"/>
  <Relationship Id="rMissingMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Media/Missing.png"/>
  <Relationship Id="rNoContentType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="raw/NoDefault.UNKNOWN"/>
  <Relationship Id="rExternalMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/Media/Review.PNG" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target case-fold path segment position fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'Root-Position.XML' => '<root-position/>',
        'word/Media/Review.PNG' => str_repeat('R', 31),
        'word/media/review.png' => str_repeat('L', 41),
        'word/MEDIA/REVIEW.png' => str_repeat('U', 53),
        'word/raw/NoDefault.UNKNOWN' => str_repeat('N', 19),
        'customXml/Item.XML' => '<item>mixed case xml target</item>',
    ];
}
