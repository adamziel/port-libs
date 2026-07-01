<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml root elements without exposing root attribute values' => static function (TestRunner $t): void {
        $parts = docx_package_xml_root_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $roots = [];
        foreach ($summary['partXmlRoots'] as $root) {
            $roots[$root['partName']] = $root;
        }

        $t->same(9, $summary['partXmlRootPartCount']);
        $t->same(8, $summary['partXmlRootValidXmlCount']);
        $t->same(1, $summary['partXmlRootInvalidXmlCount']);
        $t->same(5, $summary['partXmlRootAttributeCount']);
        $t->same(4, $summary['partXmlRootAttributePartCount']);
        $t->same(12, $summary['partXmlRootNamespaceDeclarationCount']);
        $t->same(8, $summary['partXmlRootNamespaceDeclarationPartCount']);
        $t->same(5, $summary['partXmlRootPrefixedPartCount']);
        $t->same(['diagnostics/invalid.xml'], $summary['partXmlRootInvalidPartNames']);
        $t->same([
            'a',
            'cp',
            'dc',
            'default',
            'mc',
            'r',
            'review',
            'w',
        ], $summary['partXmlRootNamespacePrefixes']);

        $t->same([
            'http://schemas.openxmlformats.org/drawingml/2006/main' => 1,
            'http://schemas.openxmlformats.org/package/2006/content-types' => 1,
            'http://schemas.openxmlformats.org/package/2006/metadata/core-properties' => 1,
            'http://schemas.openxmlformats.org/package/2006/relationships' => 2,
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main' => 2,
            'urn:docx-root-review' => 1,
        ], $summary['partXmlRootNamespaceCounts']);
        $t->same([
            'Relationships' => 2,
            'Types' => 1,
            'coreProperties' => 1,
            'document' => 1,
            'packet' => 1,
            'settings' => 1,
            'theme' => 1,
        ], $summary['partXmlRootLocalNameCounts']);
        $t->same([
            'Relationships' => 2,
            'Types' => 1,
            'a:theme' => 1,
            'cp:coreProperties' => 1,
            'review:packet' => 1,
            'w:document' => 1,
            'w:settings' => 1,
        ], $summary['partXmlRootQualifiedNameCounts']);
        $t->same(['a' => 1, 'cp' => 1, 'review' => 1, 'w' => 2], $summary['partXmlRootPrefixCounts']);

        $documentRoot = $package['parts']['word/document.xml'];
        $reviewRoot = $package['parts']['customXml/root-review.xml'];
        $invalidRoot = $package['parts']['diagnostics/invalid.xml'];
        $binaryPart = $package['parts']['word/media/pixel.png'];

        $t->same(true, $documentRoot['xmlRootReviewed']);
        $t->same(true, $documentRoot['xmlRootValidXml']);
        $t->same('http://schemas.openxmlformats.org/wordprocessingml/2006/main', $documentRoot['xmlRootNamespace']);
        $t->same('document', $documentRoot['xmlRootLocalName']);
        $t->same('w:document', $documentRoot['xmlRootQualifiedName']);
        $t->same('w', $documentRoot['xmlRootPrefix']);
        $t->same(1, $documentRoot['xmlRootAttributeCount']);
        $t->same(3, $documentRoot['xmlRootNamespaceDeclarationCount']);
        $t->same(['w', 'r', 'mc'], $documentRoot['xmlRootNamespacePrefixes']);

        $t->same(true, $reviewRoot['xmlRootReviewed']);
        $t->same(true, $reviewRoot['xmlRootValidXml']);
        $t->same('urn:docx-root-review', $reviewRoot['xmlRootNamespace']);
        $t->same('packet', $reviewRoot['xmlRootLocalName']);
        $t->same('review:packet', $reviewRoot['xmlRootQualifiedName']);
        $t->same(2, $reviewRoot['xmlRootAttributeCount']);
        $t->same(1, $reviewRoot['xmlRootNamespaceDeclarationCount']);

        $t->same(true, $invalidRoot['xmlRootReviewed']);
        $t->same(false, $invalidRoot['xmlRootValidXml']);
        $t->true(is_string($invalidRoot['xmlRootParseError']), 'invalid XML part should carry parser diagnostics');
        $t->same(null, $invalidRoot['xmlRootQualifiedName']);
        $t->same(false, $binaryPart['xmlRootReviewed']);

        $t->same('review:packet', $roots['customXml/root-review.xml']['rootQualifiedName']);
        $t->same(2, $roots['customXml/root-review.xml']['rootAttributeCount']);
        $t->same(false, $roots['diagnostics/invalid.xml']['validXml']);
        $t->same(null, $roots['diagnostics/invalid.xml']['rootQualifiedName']);
        $t->true(!isset($roots['word/media/pixel.png']), 'binary media parts should not appear in XML root rollups');

        $encodedRoots = json_encode([
            $reviewRoot,
            $summary['partXmlRoots'],
        ]);
        $t->true(is_string($encodedRoots), 'XML root metadata should encode for review');
        $t->true(!str_contains((string) $encodedRoots, 'hidden-root-attribute-value'), 'root attribute values should not appear in package metadata');
        $t->true(!array_key_exists('xml', $roots['customXml/root-review.xml']), 'XML root rows must not expose XML bytes');
        $t->true(!array_key_exists('contents', $roots['customXml/root-review.xml']), 'XML root rows must not expose package bytes');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_root_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/root-review.xml" ContentType="application/xml; profile=root-review"/>
  <Override PartName="/diagnostics/invalid.xml" ContentType="application/xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/theme/review-theme.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCustomRoot" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/root-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
  <Relationship Id="rTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/review-theme.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" mc:Ignorable="w14">
  <w:body>
    <w:p><w:r><w:t>Package XML root provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML root fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/root-review.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<review:packet xmlns:review="urn:docx-root-review" review:secret="hidden-root-attribute-value" data-safe="yes">
  <review:value>safe</review:value>
</review:packet>
XML,
        'diagnostics/invalid.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<broken><unclosed></broken>
XML,
        'word/settings.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" mc:Ignorable="w15">
  <w:updateFields w:val="true"/>
</w:settings>
XML,
        'word/theme/review-theme.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Review Theme"/>
XML,
        'word/media/pixel.png' => 'fake png bytes',
    ];
}
