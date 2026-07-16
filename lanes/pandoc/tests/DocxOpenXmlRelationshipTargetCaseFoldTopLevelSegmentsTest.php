<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target case-fold top-level segments for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_target_casefold_top_level_segment_fixture_parts();
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $customXmlRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['relationshipTargetCaseFoldTopLevelSegments'] as $group) {
            $groups[$group['caseFoldTopLevelSegment']] = $group;
        }

        $t->same(3, $summary['relationshipTargetCaseFoldTopLevelSegmentCount']);
        $t->same([
            'customxml' => 1,
            'media' => 3,
            'word' => 1,
        ], $summary['relationshipTargetCaseFoldTopLevelSegmentCounts']);
        $t->same([
            'customxml' => 1,
            'media' => 2,
            'word' => 1,
        ], $summary['relationshipTargetExistingCaseFoldTopLevelSegmentCounts']);
        $t->same(['media' => 1], $summary['relationshipTargetMissingCaseFoldTopLevelSegmentCounts']);
        $t->same(1, $summary['duplicateRelationshipTargetCaseFoldTopLevelSegmentCount']);
        $t->same(3, $summary['duplicateRelationshipTargetCaseFoldTopLevelSegmentRelationshipCount']);
        $t->same(3, $summary['duplicateRelationshipTargetCaseFoldTopLevelSegmentTargetCount']);
        $t->same(['media'], $summary['duplicateRelationshipTargetCaseFoldTopLevelSegments']);
        $t->same(['customxml', 'media', 'word'], array_column($summary['relationshipTargetCaseFoldTopLevelSegments'], 'caseFoldTopLevelSegment'));

        $media = $groups['media'];
        $t->same(3, $media['topLevelSegmentVariantCount']);
        $t->same(3, $media['relationshipCount']);
        $t->same(2, $media['existingTargetCount']);
        $t->same(1, $media['missingTargetCount']);
        $t->same(0, $media['missingContentTypeTargetCount']);
        $t->same(0, $media['parameterizedTargetCount']);
        $t->same(strlen($parts['MEDIA/Review.PNG']) + strlen($parts['media/review.png']), $media['existingTargetByteLength']);
        $t->same(['MEDIA' => 1, 'Media' => 1, 'media' => 1], $media['topLevelSegmentCounts']);
        $t->same([2 => 3], $media['targetPathDepthCounts']);
        $t->same([
            'MEDIA' => 1,
            'Media' => 1,
            'media' => 1,
        ], $media['targetDirectoryCounts']);
        $t->same([
            'Missing.png' => 1,
            'Review.PNG' => 1,
            'review.png' => 1,
        ], $media['targetBaseNameCounts']);
        $t->same(['png' => 3], $media['targetPartExtensionCounts']);
        $t->same(['default' => 3], $media['contentTypeSourceCounts']);
        $t->same(['image/png' => 3], $media['contentTypeBaseCounts']);
        $t->same([$imageRel => 3], $media['relationshipTypeCounts']);
        $t->same([
            'document-relationship-target' => 2,
            'missing-relationship-target' => 1,
        ], $media['roleCounts']);
        $t->same(['word/document.xml'], $media['sourceParts']);
        $t->same(['word/_rels/document.xml.rels'], $media['relationshipParts']);
        $t->same(['rLowerMedia', 'rMissingMedia', 'rUpperMedia'], $media['relationshipIds']);
        $t->same([$imageRel], $media['relationshipTypes']);
        $t->same(['image/png'], $media['contentTypes']);
        $t->same([
            'MEDIA/Review.PNG',
            'Media/Missing.png',
            'media/review.png',
        ], $media['targetParts']);
        $t->same([
            'MEDIA/Review.PNG',
            'media/review.png',
        ], $media['existingTargetParts']);
        $t->same(['Media/Missing.png'], $media['missingTargetParts']);
        $t->same('MEDIA/Review.PNG', $media['largestExistingTargetPart']['partName']);
        $t->same('MEDIA/Review.PNG', $media['largestExistingTargetPart']['targetPart']);
        $t->same('MEDIA', $media['largestExistingTargetPart']['targetTopLevelSegment']);
        $t->same('media', $media['largestExistingTargetPart']['targetCaseFoldTopLevelSegment']);
        $t->same(['MEDIA', 'Review.PNG'], $media['largestExistingTargetPart']['targetPathSegments']);
        $t->same(['media', 'review.png'], $media['largestExistingTargetPart']['caseFoldPathSegments']);
        $t->same('MEDIA', $media['largestExistingTargetPart']['directory']);
        $t->same('Review.PNG', $media['largestExistingTargetPart']['baseName']);
        $t->same(2, $media['largestExistingTargetPart']['targetPathDepth']);
        $t->same('png', $media['largestExistingTargetPart']['partExtension']);
        $t->same(53, $media['largestExistingTargetPart']['bytes']);
        $t->same(53, $media['largestExistingTargetPart']['targetBytes']);
        $t->same(hash('sha256', $parts['MEDIA/Review.PNG']), $media['largestExistingTargetPart']['sha256']);
        $t->same('image/png', $media['largestExistingTargetPart']['contentTypeBase']);
        $t->same('default', $media['largestExistingTargetPart']['contentTypeSource']);
        $t->same(['document-relationship-target'], $media['largestExistingTargetPart']['roles']);

        $customXml = $groups['customxml'];
        $t->same(1, $customXml['relationshipCount']);
        $t->same(1, $customXml['existingTargetCount']);
        $t->same(['customXml' => 1], $customXml['topLevelSegmentCounts']);
        $t->same(['customXml/Item.XML'], $customXml['targetParts']);
        $t->same(['xml' => 1], $customXml['targetPartExtensionCounts']);
        $t->same(['application/xml' => 1], $customXml['contentTypeBaseCounts']);
        $t->same([$customXmlRel => 1], $customXml['relationshipTypeCounts']);
        $t->true(!in_array('rExternalMediaTop', $media['relationshipIds'], true), 'external targets should not enter internal case-fold top-level segment buckets');
        $t->same(1, $summary['externalRelationshipCount']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_casefold_top_level_segment_fixture_parts(): array
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
  <Relationship Id="rUpperMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../MEDIA/Review.PNG"/>
  <Relationship Id="rLowerMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/review.png"/>
  <Relationship Id="rMissingMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../Media/Missing.png"/>
  <Relationship Id="rCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/Item.XML"/>
  <Relationship Id="rExternalMediaTop" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/MEDIA/Review.PNG" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target case-fold top-level segment fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'MEDIA/Review.PNG' => str_repeat('U', 53),
        'media/review.png' => str_repeat('L', 41),
        'customXml/Item.XML' => str_repeat('C', 31),
    ];
}
