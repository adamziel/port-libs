<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX content type default extension usage for package review' => static function (TestRunner $t): void {
        $lowerPng = str_repeat('l', 17);
        $upperPng = str_repeat('U', 1024);
        $binPayload = str_repeat('b', 29);
        $rawPayload = str_repeat('r', 13);
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream; profile=default-bin"/>
  <Default Extension="unused" ContentType="application/vnd.example.unused"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
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
    <w:p><w:r><w:t>Default extension usage fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/media/review.png' => $lowerPng,
            'word/media/UPPER.PNG' => $upperPng,
            'customXml/data.bin' => $binPayload,
            'word/custom/no-default.raw' => $rawPayload,
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $contentTypesPart = $package['contentTypesPart'];
        $summary = $package['summary'];
        $declarations = [];
        foreach ($contentTypesPart['defaultDeclarations'] as $declaration) {
            $declarations[$declaration['extension']] = $declaration;
        }

        $t->same(5, $contentTypesPart['defaultDeclarationCount']);
        $t->same(4, $contentTypesPart['usedDefaultDeclarationCount']);
        $t->same(1, $contentTypesPart['unusedDefaultDeclarationCount']);
        $t->same(5, $contentTypesPart['defaultResolvedPartCount']);
        $t->same(1, $contentTypesPart['overrideResolvedPartCount']);
        $t->same(1, $contentTypesPart['missingDefaultContentTypePartCount']);
        $t->same(['unused'], $contentTypesPart['unusedDefaultExtensions']);
        $t->same(['raw'], $contentTypesPart['missingDefaultExtensions']);

        $t->same([
            'PNG' => 1,
            'bin' => 1,
            'png' => 1,
            'rels' => 1,
            'xml' => 1,
        ], $contentTypesPart['defaultDeclarationRawExtensionCounts']);
        $t->same([
            '/' => 1,
            '_rels' => 1,
            'customXml' => 1,
            'word/media' => 2,
        ], $contentTypesPart['defaultDeclarationDirectoryCounts']);
        $t->same([
            '[Content_Types].xml' => 1,
            '_rels' => 1,
            'customXml' => 1,
            'word' => 2,
        ], $contentTypesPart['defaultDeclarationTopLevelSegmentCounts']);

        $png = $declarations['png'];
        $t->same(2, $png['packagePartCount']);
        $t->same(strlen($lowerPng) + strlen($upperPng), $png['byteLength']);
        $t->same(['word/media/UPPER.PNG', 'word/media/review.png'], $png['packageParts']);
        $t->same(['PNG' => 1, 'png' => 1], $png['rawExtensionCounts']);
        $t->same(['word/media' => 2], $png['directoryCounts']);
        $t->same(['word' => 2], $png['topLevelSegmentCounts']);
        $t->same('word/media/UPPER.PNG', $png['largestPackagePart']['partName']);
        $t->same('PNG', $png['largestPackagePart']['rawPartExtension']);
        $t->same('png', $png['largestPackagePart']['partExtension']);
        $t->same(strlen($upperPng), $png['largestPackagePart']['bytes']);
        $t->same(hash('sha256', $upperPng), $png['largestPackagePart']['sha256']);
        $t->same('image/png', $png['largestPackagePart']['contentTypeBase']);
        $t->same('content-type-default-resolved-part-metadata-only', $png['largestPackagePart']['byteExposurePolicy']);
        $t->same(false, array_key_exists('contents', $png['largestPackagePart']));

        $bin = $declarations['bin'];
        $t->same(1, $bin['packagePartCount']);
        $t->same(['bin' => 1], $bin['rawExtensionCounts']);
        $t->same(['customXml' => 1], $bin['directoryCounts']);
        $t->same(['customXml' => 1], $bin['topLevelSegmentCounts']);
        $t->same('customXml/data.bin', $bin['largestPackagePart']['partName']);
        $t->same(['profile' => 'default-bin'], $bin['largestPackagePart']['contentTypeParameterMap']);

        $rels = $declarations['rels'];
        $t->same(1, $rels['packagePartCount']);
        $t->same(1, $rels['relationshipPartCount']);
        $t->same(['rels' => 1], $rels['rawExtensionCounts']);
        $t->same('_rels/.rels', $rels['largestPackagePart']['partName']);
        $t->same(true, $rels['largestPackagePart']['relationshipPart']);

        $unused = $declarations['unused'];
        $t->same(0, $unused['packagePartCount']);
        $t->same([], $unused['rawExtensionCounts']);
        $t->same(null, $unused['largestPackagePart']);

        $t->same('word/media/UPPER.PNG', $contentTypesPart['defaultDeclarationLargestPart']['partName']);
        $t->same(strlen($upperPng), $contentTypesPart['defaultDeclarationLargestPart']['bytes']);
        $t->same(4, count($contentTypesPart['defaultDeclarationLargestParts']));
        $t->same('word/media/UPPER.PNG', $contentTypesPart['defaultDeclarationLargestParts'][0]['partName']);
        $t->same('customXml/data.bin', $contentTypesPart['defaultDeclarationLargestParts'][3]['partName']);

        $t->same(
            $contentTypesPart['defaultDeclarationRawExtensionCounts'],
            $summary['contentTypeDefaultDeclarationRawExtensionCounts']
        );
        $t->same(
            $contentTypesPart['defaultDeclarationDirectoryCounts'],
            $summary['contentTypeDefaultDeclarationDirectoryCounts']
        );
        $t->same(
            $contentTypesPart['defaultDeclarationTopLevelSegmentCounts'],
            $summary['contentTypeDefaultDeclarationTopLevelSegmentCounts']
        );
        $t->same(
            $contentTypesPart['defaultDeclarationLargestPart'],
            $summary['contentTypeDefaultDeclarationLargestPart']
        );
        $t->same(
            $contentTypesPart['defaultDeclarationLargestParts'],
            $summary['contentTypeDefaultDeclarationLargestParts']
        );
        $t->same('missing', $package['parts']['word/custom/no-default.raw']['contentTypeSource']);
    },
];
