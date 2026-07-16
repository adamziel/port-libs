<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX content type override case-fold part collisions' => static function (TestRunner $t): void {
        $lowerImage = 'lower preview bytes';
        $upperImage = 'upper preview bytes with larger payload';
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/media/review.png" ContentType="image/png; profile=lower"/>
  <Override PartName="/word/media/REVIEW.PNG" ContentType="image/png"/>
  <Override PartName="/word/media/Missing.png" ContentType="image/png; profile=missing"/>
  <Override PartName="/word/media/missing.PNG" ContentType="image/png"/>
</Types>
XML,
            '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Content type override case-fold review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/media/review.png' => $lowerImage,
            'word/media/REVIEW.PNG' => $upperImage,
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $contentTypesPart = $package['contentTypesPart'];
        $summary = $package['summary'];
        $groups = [];
        foreach ($contentTypesPart['duplicateOverrideCaseFoldPartGroups'] as $group) {
            $groups[$group['caseFoldPartName']] = $group;
        }

        $t->same(3, $contentTypesPart['overrideCaseFoldPartCount']);
        $t->same(2, $contentTypesPart['duplicateOverrideCaseFoldPartCount']);
        $t->same(4, $contentTypesPart['duplicateOverrideCaseFoldDeclarationCount']);
        $t->same(4, $contentTypesPart['duplicateOverrideCaseFoldPartNameCount']);
        $t->same(['word/media/missing.png', 'word/media/review.png'], $contentTypesPart['duplicateOverrideCaseFoldPartNames']);
        $t->same([
            'word/media/Missing.png',
            'word/media/REVIEW.PNG',
            'word/media/missing.PNG',
            'word/media/review.png',
        ], $contentTypesPart['duplicateOverrideCaseFoldPartNameVariants']);
        $t->same($contentTypesPart['overrideCaseFoldPartCount'], $summary['contentTypeOverrideCaseFoldPartCount']);
        $t->same($contentTypesPart['duplicateOverrideCaseFoldPartCount'], $summary['duplicateContentTypeOverrideCaseFoldPartCount']);
        $t->same($contentTypesPart['duplicateOverrideCaseFoldDeclarationCount'], $summary['duplicateContentTypeOverrideCaseFoldDeclarationCount']);
        $t->same($contentTypesPart['duplicateOverrideCaseFoldPartNameCount'], $summary['duplicateContentTypeOverrideCaseFoldPartNameCount']);
        $t->same($contentTypesPart['duplicateOverrideCaseFoldPartNames'], $summary['duplicateContentTypeOverrideCaseFoldPartNames']);
        $t->same($contentTypesPart['duplicateOverrideCaseFoldPartNameVariants'], $summary['duplicateContentTypeOverrideCaseFoldPartNameVariants']);

        $review = $groups['word/media/review.png'];
        $t->same('word/media/review.png', $review['caseFoldPartName']);
        $t->same(2, $review['declarationCount']);
        $t->same(2, $review['partNameVariantCount']);
        $t->same(2, $review['existingDeclarationCount']);
        $t->same(0, $review['missingDeclarationCount']);
        $t->same(1, $review['parameterizedDeclarationCount']);
        $t->same(strlen($lowerImage) + strlen($upperImage), $review['existingPartByteLength']);
        $t->same([
            'word/media/REVIEW.PNG' => 1,
            'word/media/review.png' => 1,
        ], $review['partNameCounts']);
        $t->same(['image/png' => 2], $review['contentTypeBaseCounts']);
        $t->same(['profile' => 1], $review['contentTypeParameterNameCounts']);
        $t->same(['word/media/REVIEW.PNG', 'word/media/review.png'], $review['partNames']);
        $t->same(['word/media/REVIEW.PNG', 'word/media/review.png'], $review['existingPartNames']);
        $t->same([], $review['missingPartNames']);
        $t->same(['image/png', 'image/png; profile=lower'], $review['contentTypes']);
        $t->same(['image/png'], $review['contentTypeBases']);
        $t->same('word/media/REVIEW.PNG', $review['largestExistingPart']['partName']);
        $t->same(strlen($upperImage), $review['largestExistingPart']['bytes']);
        $t->same(hash('sha256', $upperImage), $review['largestExistingPart']['sha256']);
        $t->same('image/png', $review['largestExistingPart']['contentType']);
        $t->same([], $review['largestExistingPart']['contentTypeParameterMap']);

        $missing = $groups['word/media/missing.png'];
        $t->same(2, $missing['declarationCount']);
        $t->same(2, $missing['partNameVariantCount']);
        $t->same(0, $missing['existingDeclarationCount']);
        $t->same(2, $missing['missingDeclarationCount']);
        $t->same(1, $missing['parameterizedDeclarationCount']);
        $t->same(0, $missing['existingPartByteLength']);
        $t->same([
            'word/media/Missing.png' => 1,
            'word/media/missing.PNG' => 1,
        ], $missing['partNameCounts']);
        $t->same(['image/png' => 2], $missing['contentTypeBaseCounts']);
        $t->same(['profile' => 1], $missing['contentTypeParameterNameCounts']);
        $t->same([], $missing['existingPartNames']);
        $t->same(['word/media/Missing.png', 'word/media/missing.PNG'], $missing['missingPartNames']);
        $t->same(null, $missing['largestExistingPart']);
    },
];
