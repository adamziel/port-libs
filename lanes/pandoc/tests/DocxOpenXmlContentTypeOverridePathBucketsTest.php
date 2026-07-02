<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX content type override declaration paths for package review' => static function (TestRunner $t): void {
        $imagePayload = 'preview image bytes';
        $customXmlPayload = '<root/>';
        $relationshipPayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML;
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="jpeg" ContentType="image/jpeg"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/media/review.png" ContentType="image/png; profile=preview"/>
  <Override PartName="/word/media/review.jpeg" ContentType="image/jpeg"/>
  <Override PartName="/customXml/item1.xml" ContentType="application/xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/word/_rels/document.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
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
    <w:p><w:r><w:t>Content type override path bucket fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/media/review.png' => $imagePayload,
            'customXml/item1.xml' => $customXmlPayload,
            'word/_rels/document.xml.rels' => $relationshipPayload,
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $contentTypesPart = $package['contentTypesPart'];
        $summary = $package['summary'];
        $declarations = [];
        foreach ($contentTypesPart['overrideDeclarations'] as $declaration) {
            $declarations[$declaration['partName']] = $declaration;
        }

        $t->same(6, $contentTypesPart['overrideDeclarationCount']);
        $t->same(4, $contentTypesPart['usedOverrideDeclarationCount']);
        $t->same(2, $contentTypesPart['unusedOverrideDeclarationCount']);
        $t->same(2, $contentTypesPart['invalidOverrideDeclarationCount']);
        $t->same(['docProps/app.xml', 'word/media/review.jpeg'], $contentTypesPart['unusedOverridePartNames']);
        $t->same(1, $contentTypesPart['parameterizedOverrideDeclarationCount']);
        $t->same(['profile' => 1], $contentTypesPart['overrideDeclarationContentTypeParameterNameCounts']);
        $t->same(['profile' => ['preview' => 1]], $contentTypesPart['overrideDeclarationContentTypeParameterValueCounts']);

        $t->same([
            'customXml' => 1,
            'docProps' => 1,
            'word' => 1,
            'word/_rels' => 1,
            'word/media' => 2,
        ], $contentTypesPart['overrideDeclarationDirectoryCounts']);
        $t->same([
            'customXml' => 1,
            'docProps' => 1,
            'word' => 4,
        ], $contentTypesPart['overrideDeclarationTopLevelSegmentCounts']);
        $t->same([
            'jpeg' => 1,
            'png' => 1,
            'rels' => 1,
            'xml' => 3,
        ], $contentTypesPart['overrideDeclarationPartExtensionCounts']);
        $t->same($contentTypesPart['overrideDeclarationDirectoryCounts'], $summary['contentTypeOverrideDeclarationDirectoryCounts']);
        $t->same(
            $contentTypesPart['overrideDeclarationTopLevelSegmentCounts'],
            $summary['contentTypeOverrideDeclarationTopLevelSegmentCounts']
        );
        $t->same(
            $contentTypesPart['overrideDeclarationPartExtensionCounts'],
            $summary['contentTypeOverrideDeclarationPartExtensionCounts']
        );

        $image = $declarations['word/media/review.png'];
        $t->same('word/media', $image['directory']);
        $t->same(2, $image['directoryDepth']);
        $t->same('word', $image['topLevelSegment']);
        $t->same('review.png', $image['baseName']);
        $t->same('review', $image['baseNameStem']);
        $t->same(['word', 'media', 'review.png'], $image['pathSegments']);
        $t->same(3, $image['pathSegmentCount']);
        $t->same('png', $image['partExtension']);
        $t->same('png', $image['rawPartExtension']);
        $t->same(true, $image['exists']);
        $t->same('exact', $image['matchKind']);
        $t->same(['profile' => 'preview'], $image['contentTypeParameterMap']);
        $t->same([], $image['issues']);

        $relationships = $declarations['word/_rels/document.xml.rels'];
        $t->same('word/_rels', $relationships['directory']);
        $t->same(2, $relationships['directoryDepth']);
        $t->same('document.xml.rels', $relationships['baseName']);
        $t->same('document.xml', $relationships['baseNameStem']);
        $t->same('rels', $relationships['partExtension']);
        $t->same(true, $relationships['relationshipPart']);
        $t->same('word/document.xml', $relationships['relationshipSource']);
        $t->same(true, $relationships['relationshipSourceExists']);
        $t->same(true, $relationships['valid']);

        $missingDocProps = $declarations['docProps/app.xml'];
        $t->same('docProps', $missingDocProps['directory']);
        $t->same('docProps', $missingDocProps['topLevelSegment']);
        $t->same('xml', $missingDocProps['partExtension']);
        $t->same(false, $missingDocProps['exists']);
        $t->same('missing', $missingDocProps['matchKind']);
        $t->same(false, $missingDocProps['valid']);
        $t->same(['override-target-missing-part'], $missingDocProps['issues']);

        $missingImage = $declarations['word/media/review.jpeg'];
        $t->same('word/media', $missingImage['directory']);
        $t->same('review', $missingImage['baseNameStem']);
        $t->same('jpeg', $missingImage['partExtension']);
        $t->same(false, isset($package['parts']['word/media/review.jpeg']));
    },
];
