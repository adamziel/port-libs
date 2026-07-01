<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX content type override basename stems for package review' => static function (TestRunner $t): void {
        $png = 'png bytes';
        $upperPng = 'upper png bytes';
        $jpeg = 'jpeg bytes with the largest declared override payload';
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/media/review.png" ContentType="image/png; profile=lower"/>
  <Override PartName="/word/media/REVIEW.PNG" ContentType="image/png"/>
  <Override PartName="/word/media/review.jpeg" ContentType="image/jpeg"/>
  <Override PartName="/word/charts/review.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
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
    <w:p><w:r><w:t>Content type override basename stem review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/media/review.png' => $png,
            'word/media/REVIEW.PNG' => $upperPng,
            'word/media/review.jpeg' => $jpeg,
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $contentTypesPart = $document->attr('docx')['packageProvenance']['contentTypesPart'];
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $stemGroups = [];
        foreach ($contentTypesPart['duplicateOverrideBaseNameStemGroups'] as $group) {
            $stemGroups[$group['baseNameStem']] = $group;
        }
        $caseFoldStemGroups = [];
        foreach ($contentTypesPart['duplicateOverrideCaseFoldBaseNameStemGroups'] as $group) {
            $caseFoldStemGroups[$group['caseFoldBaseNameStem']] = $group;
        }

        $t->same(4, $contentTypesPart['overrideBaseNameStemCount']);
        $t->same([
            'REVIEW' => 1,
            'comments' => 1,
            'document' => 1,
            'review' => 3,
        ], $contentTypesPart['overrideBaseNameStemCounts']);
        $t->same(1, $contentTypesPart['duplicateOverrideBaseNameStemCount']);
        $t->same(3, $contentTypesPart['duplicateOverrideBaseNameStemDeclarationCount']);
        $t->same(['review'], $contentTypesPart['duplicateOverrideBaseNameStems']);
        $t->same($contentTypesPart['overrideBaseNameStemCounts'], $summary['contentTypeOverrideBaseNameStemCounts']);
        $t->same($contentTypesPart['duplicateOverrideBaseNameStemGroups'], $summary['duplicateContentTypeOverrideBaseNameStemGroups']);

        $review = $stemGroups['review'];
        $t->same(3, $review['declarationCount']);
        $t->same(2, $review['existingDeclarationCount']);
        $t->same(1, $review['missingDeclarationCount']);
        $t->same(1, $review['invalidDeclarationCount']);
        $t->same(1, $review['parameterizedDeclarationCount']);
        $t->same(0, $review['relationshipPartDeclarationCount']);
        $t->same(strlen($png) + strlen($jpeg), $review['existingPartByteLength']);
        $t->same(3, $review['baseNameVariantCount']);
        $t->same(3, $review['partExtensionVariantCount']);
        $t->same([
            'review.jpeg' => 1,
            'review.png' => 1,
            'review.xml' => 1,
        ], $review['baseNameCounts']);
        $t->same([
            'word/charts/review.xml' => 1,
            'word/media/review.jpeg' => 1,
            'word/media/review.png' => 1,
        ], $review['partNameCounts']);
        $t->same(['word/charts' => 1, 'word/media' => 2], $review['directoryCounts']);
        $t->same(['jpeg' => 1, 'png' => 1, 'xml' => 1], $review['partExtensionCounts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.drawingml.chart+xml' => 1,
            'image/jpeg' => 1,
            'image/png' => 1,
        ], $review['contentTypeBaseCounts']);
        $t->same(['profile' => 1], $review['contentTypeParameterNameCounts']);
        $t->same([
            'chart-part' => 1,
            'content-type-override-target' => 3,
            'existing-package-part' => 2,
            'missing-package-part' => 1,
        ], $review['roleCounts']);
        $t->same(['override-target-missing-part' => 1], $review['issueCounts']);
        $t->same(['word/media/review.jpeg', 'word/media/review.png'], $review['existingPartNames']);
        $t->same(['word/charts/review.xml'], $review['missingPartNames']);

        $largest = $review['largestExistingPart'];
        $t->same('word/media/review.jpeg', $largest['partName']);
        $t->same('review.jpeg', $largest['baseName']);
        $t->same('review', $largest['baseNameStem']);
        $t->same('review', $largest['caseFoldBaseNameStem']);
        $t->same('jpeg', $largest['partExtension']);
        $t->same(strlen($jpeg), $largest['bytes']);
        $t->same(hash('sha256', $jpeg), $largest['sha256']);
        $t->same('image/jpeg', $largest['contentTypeBase']);
        $t->same(['content-type-override-target', 'existing-package-part'], $largest['roles']);
        $t->same(false, array_key_exists('contents', $largest));

        $t->same(3, $contentTypesPart['overrideCaseFoldBaseNameStemCount']);
        $t->same([
            'comments' => 1,
            'document' => 1,
            'review' => 4,
        ], $contentTypesPart['overrideCaseFoldBaseNameStemCounts']);
        $t->same(1, $contentTypesPart['duplicateOverrideCaseFoldBaseNameStemCount']);
        $t->same(4, $contentTypesPart['duplicateOverrideCaseFoldBaseNameStemDeclarationCount']);
        $t->same(['review'], $contentTypesPart['duplicateOverrideCaseFoldBaseNameStems']);
        $t->same($contentTypesPart['overrideCaseFoldBaseNameStemCounts'], $summary['contentTypeOverrideCaseFoldBaseNameStemCounts']);
        $t->same(
            $contentTypesPart['duplicateOverrideCaseFoldBaseNameStemGroups'],
            $summary['duplicateContentTypeOverrideCaseFoldBaseNameStemGroups']
        );

        $caseFoldReview = $caseFoldStemGroups['review'];
        $t->same(4, $caseFoldReview['declarationCount']);
        $t->same(3, $caseFoldReview['existingDeclarationCount']);
        $t->same(1, $caseFoldReview['missingDeclarationCount']);
        $t->same(2, $caseFoldReview['baseNameStemVariantCount']);
        $t->same(['REVIEW' => 1, 'review' => 3], $caseFoldReview['baseNameStemCounts']);
        $t->same([
            'REVIEW.PNG' => 1,
            'review.jpeg' => 1,
            'review.png' => 1,
            'review.xml' => 1,
        ], $caseFoldReview['baseNameCounts']);
        $t->same(['jpeg' => 1, 'png' => 2, 'xml' => 1], $caseFoldReview['partExtensionCounts']);
        $t->same(strlen($png) + strlen($upperPng) + strlen($jpeg), $caseFoldReview['existingPartByteLength']);
        $t->same([
            'chart-part' => 1,
            'content-type-override-target' => 4,
            'existing-package-part' => 3,
            'missing-package-part' => 1,
        ], $caseFoldReview['roleCounts']);
        $t->same('word/media/review.jpeg', $caseFoldReview['largestExistingPart']['partName']);
    },
];
