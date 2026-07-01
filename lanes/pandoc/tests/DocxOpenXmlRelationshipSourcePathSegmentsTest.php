<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source path segments for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_source_path_segment_fixture_parts();

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $segments = [];
        foreach ($summary['relationshipSourcePathSegments'] as $segment) {
            $segments[$segment['segment']] = $segment;
        }

        $t->same(8, $summary['relationshipSourceCount']);
        $t->same(11, $summary['relationshipSourcePathSegmentCount']);
        $t->same(17, $summary['relationshipSourcePathSegmentOccurrenceCount']);
        $t->same([
            '[Content_Types].xml' => 1,
            '_rels' => 1,
            'comments.xml' => 1,
            'customXml' => 1,
            'document.xml' => 1,
            'document.xml.rels' => 1,
            'item.xml' => 1,
            'missing.xml' => 1,
            'review' => 3,
            'source.bin' => 1,
            'word' => 5,
        ], $summary['relationshipSourcePathSegmentCounts']);
        $t->same([
            '[Content_Types].xml',
            '_rels',
            'comments.xml',
            'customXml',
            'document.xml',
            'document.xml.rels',
            'item.xml',
            'missing.xml',
            'review',
            'source.bin',
            'word',
        ], array_column($summary['relationshipSourcePathSegments'], 'segment'));

        $word = $segments['word'];
        $t->same(5, $word['occurrenceCount']);
        $t->same(5, $word['sourceCount']);
        $t->same(4, $word['existingSourceCount']);
        $t->same(1, $word['nonExistingSourceCount']);
        $t->same(1, $word['missingContentTypeSourceCount']);
        $t->same([0 => 5], $word['pathSegmentIndexCounts']);
        $t->same([
            'missing-source' => 1,
            'package-part' => 3,
            'relationship-part' => 1,
        ], $word['relationshipSourceKindCounts']);
        $t->same([
            'word' => 2,
            'word/_rels' => 1,
            'word/review' => 2,
        ], $word['sourceDirectoryCounts']);
        $t->same([
            '(missing)' => 1,
            'application/octet-stream' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'application/vnd.openxmlformats-package.relationships+xml' => 1,
        ], $word['sourceContentTypeBaseCounts']);
        $t->same([
            'word/_rels/document.xml.rels',
            'word/comments.xml',
            'word/document.xml',
            'word/review/missing.xml',
            'word/review/source.bin',
        ], $word['sourceParts']);
        $t->same('word/_rels/document.xml.rels', $word['largestExistingSourcePart']['sourcePart']);
        $t->same(['word', '_rels', 'document.xml.rels'], $word['largestExistingSourcePart']['sourcePathSegments']);

        $review = $segments['review'];
        $t->same(3, $review['occurrenceCount']);
        $t->same(3, $review['sourceCount']);
        $t->same(2, $review['existingSourceCount']);
        $t->same(1, $review['nonExistingSourceCount']);
        $t->same([1 => 3], $review['pathSegmentIndexCounts']);
        $t->same(['missing-source' => 1, 'package-part' => 2], $review['relationshipSourceKindCounts']);
        $t->same(['customXml/review' => 1, 'word/review' => 2], $review['sourceDirectoryCounts']);
        $t->same(['(missing)' => 1, 'application/octet-stream' => 1, 'application/xml' => 1], $review['sourceContentTypeBaseCounts']);
        $t->same(['(missing)' => 1, 'default' => 2], $review['sourceContentTypeSourceCounts']);
        $t->same(['package-part' => 2], $review['sourceRoleCounts']);
        $t->same(['customXml/review/item.xml', 'word/review/missing.xml', 'word/review/source.bin'], $review['sourceParts']);
        $t->same('word/review/source.bin', $review['largestExistingSourcePart']['sourcePart']);

        $relationshipPartSource = $segments['_rels'];
        $t->same(1, $relationshipPartSource['sourceCount']);
        $t->same(['relationship-part' => 1], $relationshipPartSource['relationshipSourceKindCounts']);
        $t->same(['office-document-relationships' => 1, 'relationship-part' => 1], $relationshipPartSource['sourceRoleCounts']);
        $t->same(['word/_rels/document.xml.rels'], $relationshipPartSource['sourceParts']);

        $contentTypes = $segments['[Content_Types].xml'];
        $t->same(1, $contentTypes['sourceCount']);
        $t->same(['content-types-item' => 1], $contentTypes['relationshipSourceKindCounts']);
        $t->same(['content-types' => 1], $contentTypes['sourceRoleCounts']);
        $t->same(['_rels/[Content_Types].xml.rels'], $contentTypes['relationshipParts']);
    },
    'summarizes DOCX relationship source path segment positions for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_source_path_segment_fixture_parts();

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $positions = [];
        foreach ($summary['relationshipSourcePathSegmentPositions'] as $position) {
            $positions[$position['position']] = $position;
        }

        $t->same(4, $summary['relationshipSourcePathSegmentPositionBucketCount']);
        $t->same(17, $summary['relationshipSourcePathSegmentPositionOccurrenceCount']);
        $t->same($summary['relationshipSourcePathSegmentOccurrenceCount'], $summary['relationshipSourcePathSegmentPositionOccurrenceCount']);
        $t->same(['first' => 6, 'last' => 6, 'middle' => 4, 'only' => 1], $summary['relationshipSourcePathSegmentPositionCounts']);
        $t->same(['first' => 6, 'last' => 6, 'middle' => 4, 'only' => 1], $summary['relationshipSourcePathSegmentPositionSourceCounts']);
        $t->same(['first', 'last', 'middle', 'only'], array_column($summary['relationshipSourcePathSegmentPositions'], 'position'));

        $first = $positions['first'];
        $t->same(6, $first['occurrenceCount']);
        $t->same(6, $first['sourceCount']);
        $t->same(5, $first['existingSourceCount']);
        $t->same(1, $first['nonExistingSourceCount']);
        $t->same(2, $first['uniqueSegmentCount']);
        $t->same(['customXml' => 1, 'word' => 5], $first['segmentCounts']);
        $t->same(['customXml', 'word'], $first['segments']);
        $t->same([0 => 6], $first['pathSegmentIndexCounts']);
        $t->same([
            'customXml/review' => 1,
            'word' => 2,
            'word/_rels' => 1,
            'word/review' => 2,
        ], $first['sourceDirectoryCounts']);
        $t->same([
            'missing-source' => 1,
            'package-part' => 4,
            'relationship-part' => 1,
        ], $first['relationshipSourceKindCounts']);
        $t->same([
            'customXml/review/item.xml',
            'word/_rels/document.xml.rels',
            'word/comments.xml',
            'word/document.xml',
            'word/review/missing.xml',
            'word/review/source.bin',
        ], $first['sourceParts']);

        $middle = $positions['middle'];
        $t->same(4, $middle['occurrenceCount']);
        $t->same(4, $middle['sourceCount']);
        $t->same(3, $middle['existingSourceCount']);
        $t->same(1, $middle['nonExistingSourceCount']);
        $t->same(2, $middle['uniqueSegmentCount']);
        $t->same(['_rels' => 1, 'review' => 3], $middle['segmentCounts']);
        $t->same(['_rels', 'review'], $middle['segments']);
        $t->same([1 => 4], $middle['pathSegmentIndexCounts']);
        $t->same(['missing-source' => 1, 'package-part' => 2, 'relationship-part' => 1], $middle['relationshipSourceKindCounts']);
        $t->same([
            '(missing)' => 1,
            'application/octet-stream' => 1,
            'application/vnd.openxmlformats-package.relationships+xml' => 1,
            'application/xml' => 1,
        ], $middle['sourceContentTypeBaseCounts']);
        $t->same('word/_rels/document.xml.rels', $middle['largestExistingSourcePart']['sourcePart']);
        $t->same(['word', '_rels', 'document.xml.rels'], $middle['largestExistingSourcePart']['sourcePathSegments']);

        $last = $positions['last'];
        $t->same(6, $last['occurrenceCount']);
        $t->same(6, $last['sourceCount']);
        $t->same([
            'comments.xml' => 1,
            'document.xml' => 1,
            'document.xml.rels' => 1,
            'item.xml' => 1,
            'missing.xml' => 1,
            'source.bin' => 1,
        ], $last['segmentCounts']);
        $t->same([1 => 2, 2 => 4], $last['pathSegmentIndexCounts']);
        $t->same($first['sourceParts'], $last['sourceParts']);

        $only = $positions['only'];
        $t->same(1, $only['occurrenceCount']);
        $t->same(1, $only['sourceCount']);
        $t->same(['[Content_Types].xml' => 1], $only['segmentCounts']);
        $t->same([0 => 1], $only['pathSegmentIndexCounts']);
        $t->same(['/' => 1], $only['sourceDirectoryCounts']);
        $t->same(['content-types-item' => 1], $only['relationshipSourceKindCounts']);
        $t->same(['[Content_Types].xml'], $only['sourceParts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_source_path_segment_fixture_parts(): array
{
    $document = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Relationship source path segment fixture.</w:t></w:r></w:p></w:body>
</w:document>
XML;
    $comments = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>
XML;

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        '_rels/[Content_Types].xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rContentTypesNote" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="root-note.xml"/>
</Relationships>
XML,
        'word/document.xml' => $document,
        'word/comments.xml' => $comments,
        'customXml/review/item.xml' => '<item>review source</item>',
        'word/review/source.bin' => str_repeat('S', 64),
        'root-note.xml' => '<root-note/>',
        'word/media/review.png' => 'review image bytes',
        'word/media/comments.png' => 'comments image bytes',
        'customXml/review/asset.bin' => 'custom asset bytes',
        'word/review/media.bin' => 'review source target bytes',
        'word/review/missing-source-target.bin' => 'missing source target bytes',
        'word/media/from-relationship-source.png' => 'relationship source image bytes',
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        'word/_rels/comments.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCommentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/comments.png"/>
</Relationships>
XML,
        'customXml/review/_rels/item.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCustomAsset" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="asset.bin"/>
</Relationships>
XML,
        'word/review/_rels/source.bin.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReviewSourceTarget" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="media.bin"/>
</Relationships>
XML,
        'word/review/_rels/missing.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingSourceTarget" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="missing-source-target.bin"/>
</Relationships>
XML,
        'word/_rels/_rels/document.xml.rels.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rRelationshipSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/from-relationship-source.png"/>
</Relationships>
XML,
    ];
}
