<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml doctypes without exposing internal subset text' => static function (TestRunner $t): void {
        $reviewSubset = ' <!ENTITY review "hidden-payload-alpha"> <!ENTITY audit SYSTEM "file:///tmp/audit"> ';
        $settingsSubset = ' <!ENTITY settings "hidden-payload-beta"> ';
        $reviewDoctype = '<!DOCTYPE review:packet PUBLIC "-//PORT LIBS//DOCX REVIEW//EN" "https://example.test/review-packet.dtd" [' . $reviewSubset . ']>';
        $settingsDoctype = '<!DOCTYPE w:settings [' . $settingsSubset . ']>';
        $parts = docx_package_xml_doctype_fixture_parts($reviewDoctype, $settingsDoctype);

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $reviewPart = $package['parts']['customXml/doctype-review.xml'];
        $settingsPart = $package['parts']['word/settings.xml'];
        $doctypesByPart = [];
        foreach ($summary['partXmlDoctypes'] as $doctype) {
            if (is_array($doctype) && is_string($doctype['partName'] ?? null)) {
                $doctypesByPart[$doctype['partName']] = $doctype;
            }
        }

        $t->same(2, $summary['partXmlDoctypePartCount']);
        $t->same(2, $summary['partXmlDoctypeCount']);
        $t->same(1, $summary['partXmlDoctypeExternalReferenceCount']);
        $t->same(2, $summary['partXmlDoctypeInternalSubsetCount']);
        $t->same(strlen($reviewSubset) + strlen($settingsSubset), $summary['partXmlDoctypeInternalSubsetByteLength']);
        $t->same(3, $summary['partXmlDoctypeEntityDeclarationCount']);
        $t->same(strlen($reviewDoctype) + strlen($settingsDoctype), $summary['partXmlDoctypeByteLength']);
        $t->same(['review:packet', 'w:settings'], $summary['partXmlDoctypeNames']);
        $t->same([
            'xml-doctype-declaration',
            'xml-entity-declaration',
            'xml-external-doctype-reference',
        ], $summary['partXmlDoctypeIssueCodes']);
        $t->same(['customXml/doctype-review.xml', 'word/settings.xml'], $summary['partXmlDoctypePartNames']);
        $t->same(false, $summary['partXmlDoctypesTruncated']);

        $t->same(true, $reviewPart['xmlDoctypePresent']);
        $t->same('review:packet', $reviewPart['xmlDoctypeName']);
        $t->same('-//PORT LIBS//DOCX REVIEW//EN', $reviewPart['xmlDoctypePublicId']);
        $t->same('https://example.test/review-packet.dtd', $reviewPart['xmlDoctypeSystemId']);
        $t->same(true, $reviewPart['xmlDoctypeInternalSubsetPresent']);
        $t->same(strlen($reviewSubset), $reviewPart['xmlDoctypeInternalSubsetByteLength']);
        $t->same(2, $reviewPart['xmlDoctypeEntityDeclarationCount']);
        $t->same(strlen($reviewDoctype), $reviewPart['xmlDoctypeByteLength']);
        $t->same(hash('sha256', $reviewDoctype), $reviewPart['xmlDoctypeSha256']);
        $t->same([
            'xml-doctype-declaration',
            'xml-external-doctype-reference',
            'xml-entity-declaration',
        ], $reviewPart['xmlDoctypeIssueCodes']);

        $t->same(true, $settingsPart['xmlDoctypePresent']);
        $t->same('w:settings', $settingsPart['xmlDoctypeName']);
        $t->same(null, $settingsPart['xmlDoctypePublicId']);
        $t->same(null, $settingsPart['xmlDoctypeSystemId']);
        $t->same(strlen($settingsSubset), $settingsPart['xmlDoctypeInternalSubsetByteLength']);
        $t->same(1, $settingsPart['xmlDoctypeEntityDeclarationCount']);
        $t->same(hash('sha256', $settingsDoctype), $settingsPart['xmlDoctypeSha256']);

        $t->same(true, $doctypesByPart['customXml/doctype-review.xml']['hasExternalReference']);
        $t->same(2, $doctypesByPart['customXml/doctype-review.xml']['entityDeclarationCount']);
        $t->same(false, $doctypesByPart['word/settings.xml']['hasExternalReference']);
        $t->same(1, $doctypesByPart['word/settings.xml']['entityDeclarationCount']);

        $t->true(!isset($reviewPart['xmlDoctypeInternalSubset']), 'raw doctype internal subset should not be exposed on part metadata');
        $encodedDoctypes = json_encode([
            $reviewPart,
            $settingsPart,
            $summary['partXmlDoctypes'],
        ]);
        $t->true(is_string($encodedDoctypes), 'doctype metadata should encode for review');
        $t->true(!str_contains((string) $encodedDoctypes, 'hidden-payload'), 'raw doctype internal subset text should not appear in package metadata');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_doctype_fixture_parts(string $reviewDoctype, string $settingsDoctype): array
{
    $reviewXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
{$reviewDoctype}
<review:packet xmlns:review="urn:docx-doctype-review">
  <review:value>safe</review:value>
</review:packet>
XML;
    $settingsXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
{$settingsDoctype}
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:updateFields w:val="true"/>
</w:settings>
XML;

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/doctype-review.xml" ContentType="application/xml; profile=doctype-review"/>
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
  <Relationship Id="rCustomDoctype" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/doctype-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML doctype provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML doctype fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/doctype-review.xml' => $reviewXml,
        'word/settings.xml' => $settingsXml,
    ];
}
