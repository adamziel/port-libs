<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcMarkupCompatibility;

return [
    'carries DOCX content types XML root provenance into package review' => static function (TestRunner $t): void {
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:review="urn:wordpress-review" mc:Ignorable="review" review:source="import-preflight">
  <review:Audit packet="docx"/>
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
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Content type root provenance.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $contentTypesPart = $package['contentTypesPart'];
        $summary = $package['summary'];

        $t->same('Content type root provenance.', $document->children[0]->attr('text'));
        $t->same(true, $contentTypesPart['valid']);
        $t->same(true, $contentTypesPart['rootValidXml']);
        $t->same(null, $contentTypesPart['rootXmlParseError']);
        $t->same(OpcContentTypes::NAMESPACE_URI, $contentTypesPart['rootNamespace']);
        $t->same('Types', $contentTypesPart['rootLocalName']);
        $t->same('Types', $contentTypesPart['rootQualifiedName']);
        $t->same(null, $contentTypesPart['rootPrefix']);
        $t->same(2, $contentTypesPart['rootAttributeCount']);
        $t->same(3, $contentTypesPart['rootNamespaceDeclarationCount']);
        $t->same(['default', 'mc', 'review'], $contentTypesPart['rootNamespacePrefixes']);

        $t->same($contentTypesPart['rootValidXml'], $summary['contentTypeRootValidXml']);
        $t->same($contentTypesPart['rootXmlParseError'], $summary['contentTypeRootXmlParseError']);
        $t->same($contentTypesPart['rootNamespace'], $summary['contentTypeRootNamespace']);
        $t->same($contentTypesPart['rootLocalName'], $summary['contentTypeRootLocalName']);
        $t->same($contentTypesPart['rootQualifiedName'], $summary['contentTypeRootQualifiedName']);
        $t->same($contentTypesPart['rootPrefix'], $summary['contentTypeRootPrefix']);
        $t->same($contentTypesPart['rootAttributeCount'], $summary['contentTypeRootAttributeCount']);
        $t->same($contentTypesPart['rootNamespaceDeclarationCount'], $summary['contentTypeRootNamespaceDeclarationCount']);
        $t->same($contentTypesPart['rootNamespacePrefixes'], $summary['contentTypeRootNamespacePrefixes']);
        $t->same(OpcMarkupCompatibility::NAMESPACE_URI, $summary['contentTypeRootNamespacePrefixMap']['mc']);
        $t->same('urn:wordpress-review', $summary['contentTypeRootNamespacePrefixMap']['review']);
    },
];
