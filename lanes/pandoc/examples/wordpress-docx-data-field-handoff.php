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
      <w:r><w:t xml:space="preserve">Reviewer </w:t></w:r>
      <w:fldSimple w:instr=' MERGEFIELD ReviewerName \b "Reviewed by " \f " for migration" \* MERGEFORMAT '><w:r><w:t>Ada Lovelace</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> packet </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> DOCVARIABLE ImportBatch \* MERGEFORMAT </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>batch-42</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t xml:space="preserve"> from </w:t></w:r>
      <w:fldSimple w:instr=' DOCPROPERTY "Source System" \* MERGEFORMAT '><w:r><w:t>legacy-cms</w:t></w:r></w:fldSimple>
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
        'class="docx-field docx-field-mergefield docx-data-field docx-data-field-mail-merge',
        'data-docx-data-field-name="ReviewerName"',
        'data-docx-data-field-before-text="Reviewed by "',
        'data-docx-data-field-after-text=" for migration"',
        'class="docx-field docx-field-docvariable docx-data-field docx-data-field-document-variable',
        'data-docx-data-field-name="ImportBatch"',
        'class="docx-field docx-field-docproperty docx-data-field docx-data-field-document-property',
        'data-docx-data-field-name="Source System"',
        '>legacy-cms</span>.',
    ] as $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('DOCX data-field handoff did not preserve expected WordPress block metadata: ' . $expected);
        }
    }

    echo "wordpress-docx-data-field-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
