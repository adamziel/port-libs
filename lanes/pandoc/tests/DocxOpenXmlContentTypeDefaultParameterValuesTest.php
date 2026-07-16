<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX content type default parameter value buckets' => static function (TestRunner $t): void {
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml; profile=shared; charset=UTF-8"/>
  <Default Extension="png" ContentType="image/png; profile=shared; variant=screen"/>
  <Default Extension="bin" ContentType="application/octet-stream; profile=payload; variant=screen"/>
  <Default Extension="unused" ContentType="application/vnd.example.unused; profile=unused"/>
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
    <w:p><w:r><w:t>Default parameter values.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/media/review.png' => 'png bytes',
            'customXml/data.bin' => 'binary payload',
            'word/custom/notes.xml' => '<notes/>',
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
        $t->same(4, $contentTypesPart['parameterizedDefaultDeclarationCount']);
        $t->same([
            'charset' => 1,
            'profile' => 4,
            'variant' => 2,
        ], $contentTypesPart['defaultDeclarationContentTypeParameterNameCounts']);
        $t->same([
            'charset' => [
                'UTF-8' => 1,
            ],
            'profile' => [
                'payload' => 1,
                'shared' => 2,
                'unused' => 1,
            ],
            'variant' => [
                'screen' => 2,
            ],
        ], $contentTypesPart['defaultDeclarationContentTypeParameterValueCounts']);

        $t->same(['profile' => 'shared', 'charset' => 'UTF-8'], $declarations['xml']['contentTypeParameterMap']);
        $t->same(['profile' => 'shared', 'variant' => 'screen'], $declarations['png']['contentTypeParameterMap']);
        $t->same(['profile' => 'payload', 'variant' => 'screen'], $declarations['bin']['contentTypeParameterMap']);
        $t->same(['profile' => 'unused'], $declarations['unused']['contentTypeParameterMap']);
        $t->same(0, $declarations['unused']['packagePartCount']);

        $t->same(
            $contentTypesPart['defaultDeclarationContentTypeParameterNameCounts'],
            $summary['contentTypeDefaultDeclarationContentTypeParameterNameCounts']
        );
        $t->same(
            $contentTypesPart['defaultDeclarationContentTypeParameterValueCounts'],
            $summary['contentTypeDefaultDeclarationContentTypeParameterValueCounts']
        );
        $t->same(['profile' => 'shared', 'charset' => 'UTF-8'], $package['parts']['word/custom/notes.xml']['contentTypeParameterMap']);
        $t->same(['profile' => 'payload', 'variant' => 'screen'], $package['parts']['customXml/data.bin']['contentTypeParameterMap']);
    },
];
