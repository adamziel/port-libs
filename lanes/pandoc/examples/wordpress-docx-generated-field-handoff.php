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
</Types>
XML;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Review packet contents </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> TOC \o "1-3" \h \z \u \t "Appendix,1,Review Heading,2" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>Contents preview</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Source citation </w:t></w:r>
      <w:fldSimple w:instr=' CITATION Smith2024 \l 1033 \v "42" '><w:r><w:t>Smith 2024, 42</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> with bibliography </w:t></w:r>
      <w:fldSimple w:instr=' BIBLIOGRAPHY \l 1033 '><w:r><w:t>Smith, Source Packet.</w:t></w:r></w:fldSimple>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Index marker </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> XE "Source Packet" \t "See source dossier" \b \i \y "sosupaketto" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t> preserved.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Citation manager </w:t></w:r>
      <w:fldSimple w:instr=' ADDIN ZOTERO_ITEM CSL_CITATION {"citationID":"source-note-1","citationItems":[{"id":"Smith2024"},{"id":"Jones2025"}],"properties":{"noteIndex":1}} '>
        <w:r><w:t>(Smith 2024; Jones 2025)</w:t></w:r>
      </w:fldSimple>
      <w:r><w:t xml:space="preserve"> bibliography </w:t></w:r>
      <w:fldSimple w:instr=' ADDIN ZOTERO_BIBL '><w:r><w:t>Smith. Source Packet.</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> endnote </w:t></w:r>
      <w:fldSimple w:instr=' ADDIN EN.REFLIST '><w:r><w:t>EndNote sources.</w:t></w:r></w:fldSimple>
    </w:p>
  </w:body>
</w:document>
XML],
]);

$document = (new DocxReader())->readDocument($package);
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'class="docx-field docx-field-toc docx-generated-field docx-generated-field-toc',
        'data-docx-generated-field-type="table-of-contents"',
        'data-docx-field-outline-levels="1-3"',
        'data-docx-field-style-levels="Appendix,1,Review Heading,2"',
        'class="docx-field docx-field-citation docx-generated-field docx-generated-field-citation"',
        'data-docx-field-target="Smith2024"',
        'data-docx-field-citation-volume="42"',
        'class="docx-field docx-field-bibliography docx-generated-field docx-generated-field-bibliography"',
        'Smith, Source Packet.</span>.',
        'class="indexref docx-field docx-field-xe docx-index-entry docx-index-entry-cross-reference docx-index-entry-yomi docx-index-entry-bold docx-index-entry-italic"',
        'data-docx-index-entry="Source Packet"',
        'data-docx-field-cross-reference="See source dossier"',
        'data-docx-field-yomi="sosupaketto"',
        'class="docx-field docx-field-addin docx-addin-field docx-addin-csl-citation docx-addin-provider-zotero"',
        'data-docx-addin-citation-id="source-note-1"',
        'data-docx-addin-citation-item-count="2"',
        'data-docx-addin-citation-item-ids="Smith2024,Jones2025"',
        'class="docx-field docx-field-addin docx-addin-field docx-addin-csl-bibliography docx-addin-provider-zotero"',
        'class="docx-field docx-field-addin docx-addin-field docx-addin-endnote-reference-list docx-addin-provider-endnote"',
    ] as $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('DOCX generated-field handoff did not preserve expected WordPress block metadata: ' . $expected);
        }
    }

    echo "wordpress-docx-generated-field-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
