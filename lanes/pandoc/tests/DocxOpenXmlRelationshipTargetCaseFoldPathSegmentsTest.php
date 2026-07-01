<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target case-fold path segments for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_target_casefold_path_segment_fixture_parts();
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['relationshipTargetCaseFoldPathSegments'] as $group) {
            $groups[$group['caseFoldSegment']] = $group;
        }

        $t->same(7, $summary['relationshipTargetCaseFoldPathSegmentCount']);
        $t->same(16, $summary['relationshipTargetCaseFoldPathSegmentOccurrenceCount']);
        $t->same([
            'customxml' => 1,
            'document.xml' => 1,
            'item.xml' => 1,
            'media' => 4,
            'missing.png' => 1,
            'review.png' => 3,
            'word' => 5,
        ], $summary['relationshipTargetCaseFoldPathSegmentCounts']);
        $t->same(2, $summary['duplicateRelationshipTargetCaseFoldPathSegmentCount']);
        $t->same(7, $summary['duplicateRelationshipTargetCaseFoldPathSegmentRelationshipCount']);
        $t->same(7, $summary['duplicateRelationshipTargetCaseFoldPathSegmentTargetCount']);
        $t->same(7, $summary['duplicateRelationshipTargetCaseFoldPathSegmentOccurrenceCount']);
        $t->same(['media', 'review.png'], $summary['duplicateRelationshipTargetCaseFoldPathSegments']);
        $t->same([
            'customxml',
            'document.xml',
            'item.xml',
            'media',
            'missing.png',
            'review.png',
            'word',
        ], array_column($summary['relationshipTargetCaseFoldPathSegments'], 'caseFoldSegment'));

        $media = $groups['media'];
        $t->same(3, $media['segmentVariantCount']);
        $t->same(4, $media['occurrenceCount']);
        $t->same(4, $media['relationshipCount']);
        $t->same(3, $media['existingTargetCount']);
        $t->same(1, $media['missingTargetCount']);
        $t->same(0, $media['missingContentTypeTargetCount']);
        $t->same(0, $media['parameterizedTargetCount']);
        $t->same(
            strlen($parts['word/Media/Review.PNG'])
                + strlen($parts['word/media/review.png'])
                + strlen($parts['word/MEDIA/REVIEW.png']),
            $media['existingTargetByteLength']
        );
        $t->same(['MEDIA' => 1, 'Media' => 2, 'media' => 1], $media['segmentCounts']);
        $t->same([1 => 4], $media['pathSegmentIndexCounts']);
        $t->same([3 => 4], $media['targetPathDepthCounts']);
        $t->same(['word' => 4], $media['targetTopLevelSegmentCounts']);
        $t->same([
            'word/MEDIA' => 1,
            'word/Media' => 2,
            'word/media' => 1,
        ], $media['targetDirectoryCounts']);
        $t->same([
            'Missing.png' => 1,
            'REVIEW.png' => 1,
            'Review.PNG' => 1,
            'review.png' => 1,
        ], $media['targetBaseNameCounts']);
        $t->same(['png' => 4], $media['targetPartExtensionCounts']);
        $t->same(['default' => 4], $media['contentTypeSourceCounts']);
        $t->same(['image/png' => 4], $media['contentTypeBaseCounts']);
        $t->same([$imageRel => 4], $media['relationshipTypeCounts']);
        $t->same([
            'document-relationship-target' => 3,
            'missing-relationship-target' => 1,
        ], $media['roleCounts']);
        $t->same(['word/document.xml'], $media['sourceParts']);
        $t->same(['word/_rels/document.xml.rels'], $media['relationshipParts']);
        $t->same(['rLowerMedia', 'rMissingMedia', 'rMixedMedia', 'rUpperMedia'], $media['relationshipIds']);
        $t->same([$imageRel], $media['relationshipTypes']);
        $t->same(['image/png'], $media['contentTypes']);
        $t->same([
            'word/MEDIA/REVIEW.png',
            'word/Media/Missing.png',
            'word/Media/Review.PNG',
            'word/media/review.png',
        ], $media['targetParts']);
        $t->same([
            'word/MEDIA/REVIEW.png',
            'word/Media/Review.PNG',
            'word/media/review.png',
        ], $media['existingTargetParts']);
        $t->same(['word/Media/Missing.png'], $media['missingTargetParts']);
        $t->same('word/MEDIA/REVIEW.png', $media['largestExistingTargetPart']['targetPart']);
        $t->same('word/MEDIA', $media['largestExistingTargetPart']['targetDirectory']);
        $t->same('REVIEW.png', $media['largestExistingTargetPart']['targetBaseName']);
        $t->same(3, $media['largestExistingTargetPart']['targetPathDepth']);
        $t->same(['word', 'MEDIA', 'REVIEW.png'], $media['largestExistingTargetPart']['targetPathSegments']);
        $t->same(['word', 'media', 'review.png'], $media['largestExistingTargetPart']['caseFoldPathSegments']);
        $t->same('word', $media['largestExistingTargetPart']['targetTopLevelSegment']);
        $t->same('png', $media['largestExistingTargetPart']['targetPartExtension']);
        $t->same(53, $media['largestExistingTargetPart']['targetBytes']);
        $t->same(hash('sha256', $parts['word/MEDIA/REVIEW.png']), $media['largestExistingTargetPart']['targetSha256']);
        $t->same('image/png', $media['largestExistingTargetPart']['targetContentTypeBase']);
        $t->same('default', $media['largestExistingTargetPart']['targetContentTypeSource']);
        $t->same(['document-relationship-target'], $media['largestExistingTargetPart']['targetRoles']);

        $review = $groups['review.png'];
        $t->same(3, $review['segmentVariantCount']);
        $t->same(3, $review['relationshipCount']);
        $t->same(3, $review['existingTargetCount']);
        $t->same(0, $review['missingTargetCount']);
        $t->same(['REVIEW.png' => 1, 'Review.PNG' => 1, 'review.png' => 1], $review['segmentCounts']);
        $t->same([2 => 3], $review['pathSegmentIndexCounts']);
        $t->same(['word/MEDIA' => 1, 'word/Media' => 1, 'word/media' => 1], $review['targetDirectoryCounts']);
        $t->same(['REVIEW.png' => 1, 'Review.PNG' => 1, 'review.png' => 1], $review['targetBaseNameCounts']);
        $t->same(['rLowerMedia', 'rMixedMedia', 'rUpperMedia'], $review['relationshipIds']);
        $t->same('word/MEDIA/REVIEW.png', $review['largestExistingTargetPart']['targetPart']);
        $t->same(['word', 'MEDIA', 'REVIEW.png'], $review['largestExistingTargetPart']['targetPathSegments']);
        $t->same(['word', 'media', 'review.png'], $review['largestExistingTargetPart']['caseFoldPathSegments']);

        $missing = $groups['missing.png'];
        $t->same(1, $missing['relationshipCount']);
        $t->same(0, $missing['existingTargetCount']);
        $t->same(1, $missing['missingTargetCount']);
        $t->same(['Missing.png' => 1], $missing['segmentCounts']);
        $t->same(['word/Media/Missing.png'], $missing['missingTargetParts']);
        $t->same(null, $missing['largestExistingTargetPart']);

        $customXml = $groups['customxml'];
        $t->same(1, $customXml['relationshipCount']);
        $t->same(1, $customXml['existingTargetCount']);
        $t->same(['customXml' => 1], $customXml['segmentCounts']);
        $t->same(['application/xml' => 1], $customXml['contentTypeBaseCounts']);
        $t->same(['customXml/Item.XML'], $customXml['targetParts']);
        $t->true(!in_array('rExternalMedia', $media['relationshipIds'], true), 'external targets should not enter internal case-fold target segment buckets');
        $t->same(1, $summary['externalRelationshipCount']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_casefold_path_segment_fixture_parts(): array
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
  <Relationship Id="rMixedMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Media/Review.PNG"/>
  <Relationship Id="rLowerMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rUpperMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="MEDIA/REVIEW.png"/>
  <Relationship Id="rCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/Item.XML"/>
  <Relationship Id="rMissingMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Media/Missing.png"/>
  <Relationship Id="rExternalMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/Media/Review.PNG" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target case-fold path segment fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/Media/Review.PNG' => str_repeat('R', 31),
        'word/media/review.png' => str_repeat('L', 41),
        'word/MEDIA/REVIEW.png' => str_repeat('U', 53),
        'customXml/Item.XML' => '<item>mixed case xml target</item>',
    ];
}
