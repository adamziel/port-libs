<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target case-fold base names for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_target_casefold_base_name_fixture_parts();
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['relationshipTargetCaseFoldBaseNames'] as $group) {
            $groups[$group['targetCaseFoldBaseNameKey']] = $group;
        }

        $t->same(2, $summary['relationshipTargetCaseFoldBaseNameCount']);
        $t->same([
            'document.xml' => 1,
            'review.png' => 4,
        ], $summary['relationshipTargetCaseFoldBaseNameCounts']);
        $t->same([
            'document.xml' => 1,
            'review.png' => 3,
        ], $summary['relationshipTargetExistingCaseFoldBaseNameCounts']);
        $t->same(['review.png' => 1], $summary['relationshipTargetMissingCaseFoldBaseNameCounts']);
        $t->same(1, $summary['duplicateRelationshipTargetCaseFoldBaseNameCount']);
        $t->same(4, $summary['duplicateRelationshipTargetCaseFoldBaseNameRelationshipCount']);
        $t->same(3, $summary['duplicateRelationshipTargetCaseFoldBaseNameTargetCount']);
        $t->same(['review.png'], $summary['duplicateRelationshipTargetCaseFoldBaseNames']);
        $t->same(['document.xml', 'review.png'], array_column($summary['relationshipTargetCaseFoldBaseNames'], 'targetCaseFoldBaseNameKey'));

        $review = $groups['review.png'];
        $t->same('review.png', $review['targetCaseFoldBaseNameKey']);
        $t->same('review.png', $review['targetCaseFoldBaseName']);
        $t->same(3, $review['baseNameVariantCount']);
        $t->same(1, $review['extensionVariantCount']);
        $t->same(3, $review['targetPartVariantCount']);
        $t->same(4, $review['relationshipCount']);
        $t->same(3, $review['existingTargetCount']);
        $t->same(1, $review['missingTargetCount']);
        $t->same(0, $review['missingContentTypeTargetCount']);
        $t->same(0, $review['parameterizedTargetCount']);
        $t->same(
            strlen($parts['word/media/review.png']) + strlen($parts['word/media/REVIEW.PNG']),
            $review['existingTargetByteLength']
        );
        $t->same([
            'REVIEW.PNG' => 1,
            'Review.PNG' => 1,
            'review.png' => 2,
        ], $review['baseNameCounts']);
        $t->same(['png' => 4], $review['targetPartExtensionCounts']);
        $t->same(['word/media' => 4], $review['targetDirectoryCounts']);
        $t->same(['default' => 4], $review['contentTypeSourceCounts']);
        $t->same(['image/png' => 4], $review['contentTypeBaseCounts']);
        $t->same([$imageRel => 4], $review['relationshipTypeCounts']);
        $t->same([
            'document-relationship-target' => 3,
            'missing-relationship-target' => 1,
        ], $review['roleCounts']);
        $t->same(['word/media'], $review['targetDirectories']);
        $t->same(['word/document.xml'], $review['sourceParts']);
        $t->same(['word/_rels/document.xml.rels'], $review['relationshipParts']);
        $t->same(['rReviewLower', 'rReviewLowerAgain', 'rReviewMissing', 'rReviewUpper'], $review['relationshipIds']);
        $t->same([$imageRel], $review['relationshipTypes']);
        $t->same(['image/png'], $review['contentTypes']);
        $t->same([
            'word/media/REVIEW.PNG',
            'word/media/Review.PNG',
            'word/media/review.png',
        ], $review['targetParts']);
        $t->same(['word/media/REVIEW.PNG', 'word/media/review.png'], $review['existingTargetParts']);
        $t->same(['word/media/Review.PNG'], $review['missingTargetParts']);
        $t->same('word/media/REVIEW.PNG', $review['largestExistingTargetPart']['targetPart']);
        $t->same('REVIEW.PNG', $review['largestExistingTargetPart']['targetBaseName']);
        $t->same('review.png', $review['largestExistingTargetPart']['targetCaseFoldBaseName']);
        $t->same(3, $review['largestExistingTargetPart']['targetPathDepth']);
        $t->same('png', $review['largestExistingTargetPart']['targetPartExtension']);
        $t->same(strlen($parts['word/media/REVIEW.PNG']), $review['largestExistingTargetPart']['targetBytes']);
        $t->same(hash('sha256', $parts['word/media/REVIEW.PNG']), $review['largestExistingTargetPart']['targetSha256']);
        $t->same('image/png', $review['largestExistingTargetPart']['targetContentTypeBase']);
        $t->same('default', $review['largestExistingTargetPart']['targetContentTypeSource']);
        $t->same(['document-relationship-target'], $review['largestExistingTargetPart']['targetRoles']);
        $t->true(
            !in_array('rExternalReview', $review['relationshipIds'], true),
            'external targets should not enter internal case-fold basename buckets'
        );
        $t->same(1, $summary['externalRelationshipCount']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_casefold_base_name_fixture_parts(): array
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
  <Relationship Id="rReviewLower" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rReviewUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/REVIEW.PNG"/>
  <Relationship Id="rReviewLowerAgain" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png?slot=copy#asset"/>
  <Relationship Id="rReviewMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Review.PNG"/>
  <Relationship Id="rExternalReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/media/REVIEW.PNG" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target case-fold basename fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/media/review.png' => 'lower review image bytes',
        'word/media/REVIEW.PNG' => str_repeat('U', 37),
    ];
}
