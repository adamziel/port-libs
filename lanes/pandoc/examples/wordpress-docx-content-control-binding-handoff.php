<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/customXml/itemProps1.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
  <Override PartName="/customXml/itemProps2.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
</Types>
XML;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCustomData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="customXml/item1.xml"/>
  <Relationship Id="rIdMalformedCustomData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="customXml/malformed-item.xml"/>
</Relationships>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Mapped source title </w:t></w:r>
      <w:sdt>
        <w:sdtPr>
          <w:id w:val="510"/>
          <w:alias w:val="Source Title"/>
          <w:tag w:val="source_title"/>
          <w:text/>
          <w:dataBinding w:prefixMappings="xmlns:wpd='https://example.test/wp/docx' xmlns:dc='http://purl.org/dc/elements/1.1/'" w:xpath="/wpd:packet/dc:title" w:storeItemID="{33333333-4444-5555-6666-777777777777}"/>
        </w:sdtPr>
        <w:sdtContent>
          <w:r><w:t>Migration packet</w:t></w:r>
        </w:sdtContent>
      </w:sdt>
      <w:r><w:t xml:space="preserve"> remains traceable.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML],
    ['name' => 'customXml/item1.xml', 'data' => <<<'XML'
<wpd:packet xmlns:wpd="https://example.test/wp/docx" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Migration packet</dc:title>
</wpd:packet>
XML],
    ['name' => 'customXml/malformed-item.xml', 'data' => <<<'XML'
<wpd:packet xmlns:wpd="https://example.test/wp/docx">
  <wpd:title>Unbound packet</wpd:title>
</wpd:packet>
XML],
    ['name' => 'customXml/_rels/item1.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCustomDataProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="itemProps1.xml"/>
</Relationships>
XML],
    ['name' => 'customXml/_rels/malformed-item.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMalformedCustomDataProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="itemProps2.xml"/>
</Relationships>
XML],
    ['name' => 'customXml/itemProps1.xml', 'data' => <<<'XML'
<ds:datastoreItem xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml" ds:itemID="{33333333-4444-5555-6666-777777777777}">
  <ds:schemaRefs>
    <ds:schemaRef ds:uri="https://example.test/wp/docx/schema"/>
  </ds:schemaRefs>
</ds:datastoreItem>
XML],
    ['name' => 'customXml/itemProps2.xml', 'data' => <<<'XML'
<ds:datastoreItem xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml">
  <ds:schemaRefs>
    <ds:schemaRef ds:uri="https://example.test/wp/docx/unbound-schema"/>
  </ds:schemaRefs>
</ds:datastoreItem>
XML],
]);

$result = (new DocxReader())->readPackage($package);
$document = $result['document'];
$customXmlStore = $result['importReport']['customXmlStore'];
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'class="docx-content-control docx-content-control-text"',
        'data-docx-sdt-xpath="/wpd:packet/dc:title"',
        'data-docx-sdt-prefix-mappings="xmlns:wpd=&#039;https://example.test/wp/docx&#039; xmlns:dc=&#039;http://purl.org/dc/elements/1.1/&#039;"',
        'data-docx-sdt-prefix-count="2"',
        'data-docx-sdt-prefix-1-name="wpd"',
        'data-docx-sdt-prefix-2-name="dc"',
        'data-docx-sdt-custom-xml-bound="true"',
        'data-docx-sdt-custom-xml-part="/customXml/item1.xml"',
        'data-docx-sdt-custom-xml-root-name="wpd:packet"',
        'data-docx-sdt-custom-xml-schema-ref-1-uri="https://example.test/wp/docx/schema"',
        '>Migration packet</span>',
    ] as $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('DOCX content-control binding handoff did not preserve expected metadata: ' . $expected);
        }
    }

    if (
        ($customXmlStore['count'] ?? null) !== 2
        || ($customXmlStore['boundStoreItemCount'] ?? null) !== 1
        || ($customXmlStore['issueCount'] ?? null) !== 1
        || ($customXmlStore['propertyIssueCount'] ?? null) !== 1
        || ($customXmlStore['propertyIssueCodes'] ?? null) !== ['missing-store-item-id']
        || ($customXmlStore['items'][1]['propertiesIssues'] ?? null) !== ['missing-store-item-id']
    ) {
        throw new RuntimeException('DOCX content-control binding handoff did not report malformed custom XML properties');
    }

    echo "wordpress-docx-content-control-binding-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
    'customXmlStore' => $customXmlStore,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
