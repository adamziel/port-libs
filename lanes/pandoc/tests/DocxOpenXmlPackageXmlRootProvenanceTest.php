<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml roots across package inventory' => static function (TestRunner $t): void {
        $parts = docx_package_xml_root_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $reviewPart = $package['parts']['customXml/root-review.xml'];
        $brokenPart = $package['parts']['customXml/broken.xml'];
        $binaryPart = $package['parts']['customXml/not-xml.bin'];

        $rootsByPart = [];
        foreach ($summary['partXmlRoots'] as $root) {
            $rootsByPart[$root['partName']] = $root;
        }

        $t->same(7, $summary['partXmlRootPartCount']);
        $t->same(6, $summary['partXmlRootValidPartCount']);
        $t->same(1, $summary['partXmlRootInvalidPartCount']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'customXml/broken.xml',
            'customXml/root-review.xml',
            'docProps/core.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
        ], $summary['partXmlRootPartNames']);
        $t->same(['customXml/broken.xml'], $summary['partXmlRootInvalidPartNames']);
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'customXml/root-review.xml',
            'docProps/core.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
        ], $summary['partXmlRootValidPartNames']);

        $t->same([
            'Relationships' => 2,
            'Types' => 1,
            'cp:coreProperties' => 1,
            'review:packet' => 1,
            'w:document' => 1,
        ], $summary['partXmlRootQualifiedNameCounts']);
        $t->same([
            'Relationships' => 2,
            'Types' => 1,
            'coreProperties' => 1,
            'document' => 1,
            'packet' => 1,
        ], $summary['partXmlRootLocalNameCounts']);
        $t->same([
            'cp' => 1,
            'review' => 1,
            'w' => 1,
        ], $summary['partXmlRootPrefixCounts']);
        $t->same(3, $summary['partXmlRootAttributeCount']);
        $t->same(8, $summary['partXmlRootNamespaceDeclarationCount']);
        $t->same(['aux', 'cp', 'dc', 'default', 'review', 'w'], $summary['partXmlRootNamespacePrefixes']);
        $t->same(false, $summary['partXmlRootsTruncated']);

        $t->same(true, $reviewPart['xmlPackagePart']);
        $t->same(true, $reviewPart['xmlRootValid']);
        $t->same(null, $reviewPart['xmlRootParseError']);
        $t->same('urn:docx-root-review', $reviewPart['xmlRootNamespace']);
        $t->same('packet', $reviewPart['xmlRootLocalName']);
        $t->same('review:packet', $reviewPart['xmlRootQualifiedName']);
        $t->same('review', $reviewPart['xmlRootPrefix']);
        $t->same(3, $reviewPart['xmlRootAttributeCount']);
        $t->same(2, $reviewPart['xmlRootNamespaceDeclarationCount']);
        $t->same(['review', 'aux'], $reviewPart['xmlRootNamespacePrefixes']);

        $t->same('customXml/root-review.xml', $rootsByPart['customXml/root-review.xml']['partName']);
        $t->same(true, $rootsByPart['customXml/root-review.xml']['validXml']);
        $t->same('review:packet', $rootsByPart['customXml/root-review.xml']['rootQualifiedName']);
        $t->same(3, $rootsByPart['customXml/root-review.xml']['rootAttributeCount']);
        $t->same(2, $rootsByPart['customXml/root-review.xml']['rootNamespaceDeclarationCount']);

        $t->same(true, $brokenPart['xmlPackagePart']);
        $t->same(false, $brokenPart['xmlRootValid']);
        $t->true(is_string($brokenPart['xmlRootParseError']) && $brokenPart['xmlRootParseError'] !== '', 'invalid XML should retain a parse diagnostic');
        $t->same('customXml/broken.xml', $summary['partXmlRootParseErrors'][0]['partName']);
        $t->true($summary['partXmlRootParseErrors'][0]['xmlParseError'] !== '', 'summary should retain bounded parse diagnostics');
        $t->same(false, $rootsByPart['customXml/broken.xml']['validXml']);
        $t->true($rootsByPart['customXml/broken.xml']['xmlParseError'] !== '', 'root summary should retain invalid XML diagnostics');

        $t->same(false, $binaryPart['xmlPackagePart']);
        $t->same(false, $binaryPart['xmlRootValid']);
        $t->same(null, $binaryPart['xmlRootParseError']);
        $t->true(!isset($rootsByPart['customXml/not-xml.bin']), 'binary package parts should not be summarized as XML roots');

        $encodedRootMetadata = json_encode([
            $brokenPart,
            $summary['partXmlRootParseErrors'],
            $summary['partXmlRoots'],
        ]);
        $t->true(is_string($encodedRootMetadata), 'XML root metadata should encode for review');
        $t->true(!str_contains((string) $encodedRootMetadata, 'hidden-payload'), 'raw invalid XML text should not appear in package metadata');
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
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/root-review.xml" ContentType="application/xml; profile=root-review"/>
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
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
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
<review:packet xmlns:review="urn:docx-root-review" xmlns:aux="urn:docx-root-review-aux" review:state="draft" aux:checksum="abc123" plain="yes">
  <review:value>safe</review:value>
</review:packet>
XML,
        'customXml/broken.xml' => <<<'XML'
<broken><value>hidden-payload
XML,
        'customXml/not-xml.bin' => '<review:packet>hidden-payload</review:packet>',
    ];
}
