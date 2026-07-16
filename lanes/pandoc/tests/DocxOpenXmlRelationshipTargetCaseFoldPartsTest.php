<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target case-fold part collisions' => static function (TestRunner $t): void {
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $lowerImage = 'lower image bytes';
        $upperImage = 'upper image bytes with larger payload';
        $parts = [
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
  <Relationship Id="rLower" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/REVIEW.PNG"/>
  <Relationship Id="rLowerAgain" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png?slot=again#asset"/>
  <Relationship Id="rMissingLower" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rMissingUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/MISSING.PNG"/>
</Relationships>
XML,
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Case-fold relationship target review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/media/review.png' => $lowerImage,
            'word/media/REVIEW.PNG' => $upperImage,
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['duplicateRelationshipTargetCaseFoldPartGroups'] as $group) {
            $groups[$group['caseFoldTargetPart']] = $group;
        }

        $t->same(3, $summary['relationshipTargetCaseFoldPartCount']);
        $t->same(2, $summary['duplicateRelationshipTargetCaseFoldPartCount']);
        $t->same(5, $summary['duplicateRelationshipTargetCaseFoldPartRelationshipCount']);
        $t->same(4, $summary['duplicateRelationshipTargetCaseFoldPartTargetCount']);
        $t->same(['word/media/missing.png', 'word/media/review.png'], $summary['duplicateRelationshipTargetCaseFoldParts']);
        $t->same(1, $summary['duplicateRelationshipTargetPartCount']);
        $t->same(['word/media/review.png'], $summary['duplicateRelationshipTargetParts']);

        $review = $groups['word/media/review.png'];
        $t->same('word/media/review.png', $review['caseFoldTargetPart']);
        $t->same(3, $review['relationshipCount']);
        $t->same(2, $review['targetPartVariantCount']);
        $t->same(3, $review['existingTargetCount']);
        $t->same(0, $review['missingTargetCount']);
        $t->same(strlen($lowerImage) + strlen($upperImage), $review['existingTargetPartByteLength']);
        $t->same([
            'word/media/REVIEW.PNG' => 1,
            'word/media/review.png' => 2,
        ], $review['targetPartCounts']);
        $t->same(['word/media/REVIEW.PNG', 'word/media/review.png'], $review['targetParts']);
        $t->same(['word/media/REVIEW.PNG', 'word/media/review.png'], $review['existingTargetParts']);
        $t->same([], $review['missingTargetParts']);
        $t->same(['image/png' => 3], $review['contentTypeBaseCounts']);
        $t->same(['default' => 3], $review['contentTypeSourceCounts']);
        $t->same([$imageRel => 3], $review['relationshipTypeCounts']);
        $t->same(['document-relationship-target' => 3], $review['targetRoleCounts']);
        $t->same(['word/document.xml'], $review['sourceParts']);
        $t->same(['word/_rels/document.xml.rels'], $review['relationshipParts']);
        $t->same(['rLower', 'rLowerAgain', 'rUpper'], $review['relationshipIds']);
        $t->same([$imageRel], $review['relationshipTypes']);
        $t->same(['image/png'], $review['contentTypes']);
        $t->same('word/media/REVIEW.PNG', $review['largestExistingTargetPart']['targetPart']);
        $t->same(strlen($upperImage), $review['largestExistingTargetPart']['targetBytes']);
        $t->same(hash('sha256', $upperImage), $review['largestExistingTargetPart']['targetSha256']);
        $t->same(['document-relationship-target'], $review['largestExistingTargetPart']['targetRoles']);

        $missing = $groups['word/media/missing.png'];
        $t->same(2, $missing['relationshipCount']);
        $t->same(2, $missing['targetPartVariantCount']);
        $t->same(0, $missing['existingTargetCount']);
        $t->same(2, $missing['missingTargetCount']);
        $t->same(0, $missing['existingTargetPartByteLength']);
        $t->same([
            'word/media/MISSING.PNG' => 1,
            'word/media/missing.png' => 1,
        ], $missing['targetPartCounts']);
        $t->same(['word/media/MISSING.PNG', 'word/media/missing.png'], $missing['targetParts']);
        $t->same([], $missing['existingTargetParts']);
        $t->same(['word/media/MISSING.PNG', 'word/media/missing.png'], $missing['missingTargetParts']);
        $t->same(['image/png' => 2], $missing['contentTypeBaseCounts']);
        $t->same(['default' => 2], $missing['contentTypeSourceCounts']);
        $t->same(['missing-relationship-target' => 2], $missing['targetRoleCounts']);
        $t->same(['rMissingLower', 'rMissingUpper'], $missing['relationshipIds']);
        $t->same(null, $missing['largestExistingTargetPart']);
    },
];
