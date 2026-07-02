<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX content type override directory basename collisions for package review' => static function (TestRunner $t): void {
        $png = 'png bytes';
        $upperPng = 'upper png bytes';
        $jpeg = 'jpeg bytes with the largest directory basename payload';
        $xml = '<review/>';
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/media/review.png" ContentType="image/png; profile=lower"/>
  <Override PartName="/word/media/cover.jpeg" ContentType="image/jpeg"/>
  <Override PartName="/word/Media/UPPER.PNG" ContentType="image/png"/>
  <Override PartName="/customXml/media/review.xml" ContentType="application/xml"/>
  <Override PartName="/customXml/itemProps/review.xml" ContentType="application/xml; profile=missing"/>
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
    <w:p><w:r><w:t>Content type override directory basename review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/media/review.png' => $png,
            'word/media/cover.jpeg' => $jpeg,
            'word/Media/UPPER.PNG' => $upperPng,
            'customXml/media/review.xml' => $xml,
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $contentTypesPart = $document->attr('docx')['packageProvenance']['contentTypesPart'];
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $directoryGroups = [];
        foreach ($contentTypesPart['duplicateOverrideDirectoryBaseNameGroups'] as $group) {
            $directoryGroups[$group['directoryBaseName']] = $group;
        }
        $caseFoldGroups = [];
        foreach ($contentTypesPart['duplicateOverrideCaseFoldDirectoryBaseNameGroups'] as $group) {
            $caseFoldGroups[$group['caseFoldDirectoryBaseName']] = $group;
        }

        $t->same(4, $contentTypesPart['overrideDirectoryBaseNameCount']);
        $t->same([
            'Media' => 1,
            'itemProps' => 1,
            'media' => 3,
            'word' => 1,
        ], $contentTypesPart['overrideDirectoryBaseNameCounts']);
        $t->same(1, $contentTypesPart['duplicateOverrideDirectoryBaseNameCount']);
        $t->same(3, $contentTypesPart['duplicateOverrideDirectoryBaseNameDeclarationCount']);
        $t->same(['media'], $contentTypesPart['duplicateOverrideDirectoryBaseNames']);
        $t->same($contentTypesPart['overrideDirectoryBaseNameCounts'], $summary['contentTypeOverrideDirectoryBaseNameCounts']);
        $t->same($contentTypesPart['duplicateOverrideDirectoryBaseNameGroups'], $summary['duplicateContentTypeOverrideDirectoryBaseNameGroups']);

        $media = $directoryGroups['media'];
        $t->same(3, $media['declarationCount']);
        $t->same(3, $media['existingDeclarationCount']);
        $t->same(0, $media['missingDeclarationCount']);
        $t->same(0, $media['invalidDeclarationCount']);
        $t->same(1, $media['parameterizedDeclarationCount']);
        $t->same(0, $media['relationshipPartDeclarationCount']);
        $t->same(strlen($png) + strlen($jpeg) + strlen($xml), $media['existingPartByteLength']);
        $t->same(2, $media['directoryVariantCount']);
        $t->same([
            'customXml/media' => 1,
            'word/media' => 2,
        ], $media['directoryCounts']);
        $t->same(['customXml' => 1, 'word' => 2], $media['topLevelSegmentCounts']);
        $t->same([
            'cover.jpeg' => 1,
            'review.png' => 1,
            'review.xml' => 1,
        ], $media['baseNameCounts']);
        $t->same(['jpeg' => 1, 'png' => 1, 'xml' => 1], $media['partExtensionCounts']);
        $t->same([
            'application/xml' => 1,
            'image/jpeg' => 1,
            'image/png' => 1,
        ], $media['contentTypeBaseCounts']);
        $t->same(['profile' => 1], $media['contentTypeParameterNameCounts']);
        $t->same([
            'content-type-override-target' => 3,
            'existing-package-part' => 3,
        ], $media['roleCounts']);
        $t->same([], $media['issueCounts']);
        $t->same([
            'customXml/media/review.xml',
            'word/media/cover.jpeg',
            'word/media/review.png',
        ], $media['partNames']);

        $largest = $media['largestExistingPart'];
        $t->same('word/media/cover.jpeg', $largest['partName']);
        $t->same('word/media', $largest['directory']);
        $t->same('media', $largest['directoryBaseName']);
        $t->same('media', $largest['caseFoldDirectoryBaseName']);
        $t->same('word', $largest['topLevelSegment']);
        $t->same('cover.jpeg', $largest['baseName']);
        $t->same('cover', $largest['baseNameStem']);
        $t->same('jpeg', $largest['partExtension']);
        $t->same(strlen($jpeg), $largest['bytes']);
        $t->same(hash('sha256', $jpeg), $largest['sha256']);
        $t->same('image/jpeg', $largest['contentTypeBase']);
        $t->same(['content-type-override-target', 'existing-package-part'], $largest['roles']);
        $t->same(false, array_key_exists('contents', $largest));

        $t->same(3, $contentTypesPart['overrideCaseFoldDirectoryBaseNameCount']);
        $t->same([
            'itemprops' => 1,
            'media' => 4,
            'word' => 1,
        ], $contentTypesPart['overrideCaseFoldDirectoryBaseNameCounts']);
        $t->same(1, $contentTypesPart['duplicateOverrideCaseFoldDirectoryBaseNameCount']);
        $t->same(4, $contentTypesPart['duplicateOverrideCaseFoldDirectoryBaseNameDeclarationCount']);
        $t->same(['media'], $contentTypesPart['duplicateOverrideCaseFoldDirectoryBaseNames']);
        $t->same(
            $contentTypesPart['duplicateOverrideCaseFoldDirectoryBaseNameGroups'],
            $summary['duplicateContentTypeOverrideCaseFoldDirectoryBaseNameGroups']
        );

        $caseFoldMedia = $caseFoldGroups['media'];
        $t->same(4, $caseFoldMedia['declarationCount']);
        $t->same(4, $caseFoldMedia['existingDeclarationCount']);
        $t->same(3, $caseFoldMedia['directoryVariantCount']);
        $t->same(2, $caseFoldMedia['directoryBaseNameVariantCount']);
        $t->same(['Media' => 1, 'media' => 3], $caseFoldMedia['directoryBaseNameCounts']);
        $t->same([
            'customXml/media' => 1,
            'word/Media' => 1,
            'word/media' => 2,
        ], $caseFoldMedia['directoryCounts']);
        $t->same(['jpeg' => 1, 'png' => 2, 'xml' => 1], $caseFoldMedia['partExtensionCounts']);
        $t->same(strlen($png) + strlen($upperPng) + strlen($jpeg) + strlen($xml), $caseFoldMedia['existingPartByteLength']);
        $t->same('word/media/cover.jpeg', $caseFoldMedia['largestExistingPart']['partName']);
    },
];
