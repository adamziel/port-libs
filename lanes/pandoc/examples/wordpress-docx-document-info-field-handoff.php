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
      <w:r><w:t xml:space="preserve">Source file </w:t></w:r>
      <w:fldSimple w:instr=' FILENAME \p \* MERGEFORMAT '><w:r><w:t>C:/imports/source-packet.docx</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> by </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> AUTHOR \* MERGEFORMAT </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>Migration Desk</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t xml:space="preserve"> titled </w:t></w:r>
      <w:fldSimple w:instr=' TITLE '><w:r><w:t>Migration Review Packet</w:t></w:r></w:fldSimple>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML],
]);

$document = (new DocxReader())->readDocument($package);
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'class="docx-field docx-field-filename docx-field-path"',
        'data-docx-field="filename"',
        'data-docx-field-path="true"',
        'C:/imports/source-packet.docx</span>',
        'class="docx-field docx-field-author"',
        'data-docx-field-instruction="AUTHOR \* MERGEFORMAT"',
        'Migration Desk</span>',
        'class="docx-field docx-field-title"',
        'Migration Review Packet</span>.',
    ] as $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('DOCX document-info field handoff did not preserve expected WordPress block metadata: ' . $expected);
        }
    }

    echo "wordpress-docx-document-info-field-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
