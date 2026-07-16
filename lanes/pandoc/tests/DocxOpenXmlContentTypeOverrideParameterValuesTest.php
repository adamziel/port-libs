<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX content type override parameter value buckets' => static function (TestRunner $t): void {
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml; profile=main; stage=published"/>
  <Override PartName="/customXml/review.xml" ContentType="application/xml; profile=shared; stage=draft"/>
  <Override PartName="/word/media/review.png" ContentType="image/png; profile=shared; variant=screen"/>
  <Override PartName="/word/missing.xml" ContentType="application/xml; profile=missing; stage=draft"/>
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
    <w:p><w:r><w:t>Override parameter values.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'customXml/review.xml' => '<review/>',
            'word/media/review.png' => 'png bytes',
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $contentTypesPart = $package['contentTypesPart'];
        $summary = $package['summary'];
        $declarations = [];
        foreach ($contentTypesPart['overrideDeclarations'] as $declaration) {
            $declarations[$declaration['partName']] = $declaration;
        }

        $t->same(4, $contentTypesPart['overrideDeclarationCount']);
        $t->same(4, $contentTypesPart['parameterizedOverrideDeclarationCount']);
        $t->same([
            'profile' => 4,
            'stage' => 3,
            'variant' => 1,
        ], $contentTypesPart['overrideDeclarationContentTypeParameterNameCounts']);
        $t->same([
            'profile' => [
                'main' => 1,
                'missing' => 1,
                'shared' => 2,
            ],
            'stage' => [
                'draft' => 2,
                'published' => 1,
            ],
            'variant' => [
                'screen' => 1,
            ],
        ], $contentTypesPart['overrideDeclarationContentTypeParameterValueCounts']);

        $t->same(
            $contentTypesPart['overrideDeclarationContentTypeParameterValueCounts'],
            $summary['contentTypeOverrideDeclarationContentTypeParameterValueCounts']
        );
        $t->same(['profile' => 'main', 'stage' => 'published'], $declarations['word/document.xml']['contentTypeParameterMap']);
        $t->same(['profile' => 'shared', 'stage' => 'draft'], $declarations['customXml/review.xml']['contentTypeParameterMap']);
        $t->same(['profile' => 'shared', 'variant' => 'screen'], $declarations['word/media/review.png']['contentTypeParameterMap']);
        $t->same(['profile' => 'missing', 'stage' => 'draft'], $declarations['word/missing.xml']['contentTypeParameterMap']);
        $t->same(['override-target-missing-part'], $declarations['word/missing.xml']['issues']);
    },
];
