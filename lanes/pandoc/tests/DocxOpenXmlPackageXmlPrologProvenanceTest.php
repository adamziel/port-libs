<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml declarations and doctypes without exposing subsets' => static function (TestRunner $t): void {
        $doctype = '<!DOCTYPE review:packet PUBLIC "-//Example//DTD Review 1.0//EN" "https://example.test/review.dtd" [ <!ENTITY review "blocked"> ]>';
        $parts = docx_package_xml_prolog_fixture_parts($doctype);

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $reviewPart = $package['parts']['customXml/prolog-review.xml'];
        $settingsPart = $package['parts']['word/settings.xml'];
        $noDeclarationPart = $package['parts']['customXml/no-declaration.xml'];
        $binaryPart = $package['parts']['customXml/not-xml.bin'];

        $expectedDeclarationParts = [
            '[Content_Types].xml',
            '_rels/.rels',
            'customXml/prolog-review.xml',
            'docProps/core.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/settings.xml',
        ];
        $expectedDoctypeIssues = [
            'xml-doctype-declaration',
            'xml-entity-declaration',
            'xml-external-doctype-reference',
        ];

        $declarationsByPart = [];
        foreach ($summary['partXmlDeclarations'] as $declaration) {
            $declarationsByPart[$declaration['partName']] = $declaration;
        }
        $doctypesByPart = [];
        foreach ($summary['partXmlDoctypes'] as $doctypeSummary) {
            $doctypesByPart[$doctypeSummary['partName']] = $doctypeSummary;
        }

        $t->same(7, $summary['partXmlDeclarationPartCount']);
        $t->same(7, $summary['partXmlDeclarationCount']);
        $t->same(16, $summary['partXmlDeclarationAttributeCount']);
        $t->same($expectedDeclarationParts, $summary['partXmlDeclarationPartNames']);
        $t->same(['1.0' => 6, '1.1' => 1], $summary['partXmlDeclarationVersionCounts']);
        $t->same([
            'ISO-8859-1' => 1,
            'UTF-8' => 5,
            'windows-1252' => 1,
        ], $summary['partXmlDeclarationEncodingCounts']);
        $t->same(['no' => 1, 'unspecified' => 5, 'yes' => 1], $summary['partXmlDeclarationStandaloneCounts']);
        $t->same(false, $summary['partXmlDeclarationsTruncated']);

        $t->same(true, $reviewPart['xmlDeclarationPresent']);
        $t->same('1.1', $reviewPart['xmlDeclarationVersion']);
        $t->same('ISO-8859-1', $reviewPart['xmlDeclarationEncoding']);
        $t->same(false, $reviewPart['xmlDeclarationStandalone']);
        $t->same(3, $reviewPart['xmlDeclarationAttributeCount']);
        $t->same('ISO-8859-1', $declarationsByPart['customXml/prolog-review.xml']['encoding']);
        $t->same('no', $declarationsByPart['customXml/prolog-review.xml']['standaloneKey']);

        $t->same(true, $settingsPart['xmlDeclarationPresent']);
        $t->same('windows-1252', $settingsPart['xmlDeclarationEncoding']);
        $t->same(true, $settingsPart['xmlDeclarationStandalone']);
        $t->same(false, $noDeclarationPart['xmlDeclarationPresent']);
        $t->same(false, $binaryPart['xmlDeclarationPresent']);

        $t->same(1, $summary['partXmlDoctypePartCount']);
        $t->same(1, $summary['partXmlDoctypeCount']);
        $t->same(1, $summary['partXmlDoctypeExternalReferencePartCount']);
        $t->same(1, $summary['partXmlDoctypeInternalSubsetPartCount']);
        $t->same(1, $summary['partXmlDoctypeEntityDeclarationCount']);
        $t->same(strlen($doctype), $summary['partXmlDoctypeByteLength']);
        $t->same(['review:packet' => 1], $summary['partXmlDoctypeNameCounts']);
        $t->same($expectedDoctypeIssues, $summary['partXmlDoctypeIssueCodes']);
        $t->same(['customXml/prolog-review.xml'], $summary['partXmlDoctypePartNames']);
        $t->same(false, $summary['partXmlDoctypesTruncated']);

        $t->same(true, $reviewPart['xmlDoctypePresent']);
        $t->same('review:packet', $reviewPart['xmlDoctypeName']);
        $t->same('-//Example//DTD Review 1.0//EN', $reviewPart['xmlDoctypePublicId']);
        $t->same('https://example.test/review.dtd', $reviewPart['xmlDoctypeSystemId']);
        $t->same(true, $reviewPart['xmlDoctypeInternalSubsetPresent']);
        $t->same(strlen(' <!ENTITY review "blocked"> '), $reviewPart['xmlDoctypeInternalSubsetByteLength']);
        $t->same(1, $reviewPart['xmlDoctypeEntityDeclarationCount']);
        $t->same(strlen($doctype), $reviewPart['xmlDoctypeByteLength']);
        $t->same(hash('sha256', $doctype), $reviewPart['xmlDoctypeSha256']);
        $t->same([
            'xml-doctype-declaration',
            'xml-external-doctype-reference',
            'xml-entity-declaration',
        ], $reviewPart['xmlDoctypeIssueCodes']);

        $reviewDoctype = $doctypesByPart['customXml/prolog-review.xml'];
        $t->same('review:packet', $reviewDoctype['name']);
        $t->same(true, $reviewDoctype['externalReference']);
        $t->same(strlen(' <!ENTITY review "blocked"> '), $reviewDoctype['internalSubsetByteLength']);
        $t->same(hash('sha256', $doctype), $reviewDoctype['sha256']);
        $t->true(!isset($reviewDoctype['internalSubset']), 'raw internal subset should not be exposed');
        $t->same(false, $binaryPart['xmlDoctypePresent']);

        $encodedDoctypeMetadata = json_encode([$reviewPart, $summary['partXmlDoctypes']]);
        $t->true(is_string($encodedDoctypeMetadata), 'doctype metadata should encode for review');
        $t->true(!str_contains((string) $encodedDoctypeMetadata, 'blocked'), 'raw entity value should not appear in package metadata');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_prolog_fixture_parts(string $doctype): array
{
    $reviewXml = <<<XML
<?xml version="1.1" encoding="ISO-8859-1" standalone="no"?>
{$doctype}
<review:packet xmlns:review="urn:docx-prolog-review">
  <review:value>safe</review:value>
</review:packet>
XML;
    $settingsXml = <<<'XML'
<?xml version="1.0" encoding="windows-1252" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:updateFields w:val="true"/>
</w:settings>
XML;

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/prolog-review.xml" ContentType="application/xml; profile=prolog-review"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
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
  <Relationship Id="rCustomProlog" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/prolog-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML prolog provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML prolog fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/prolog-review.xml' => $reviewXml,
        'customXml/no-declaration.xml' => <<<'XML'
<review:packet xmlns:review="urn:docx-prolog-review">
  <review:value>no declaration</review:value>
</review:packet>
XML,
        'customXml/not-xml.bin' => '<!DOCTYPE ignored SYSTEM "https://example.test/ignored.dtd"><ignored/>',
        'word/settings.xml' => $settingsXml,
    ];
}
