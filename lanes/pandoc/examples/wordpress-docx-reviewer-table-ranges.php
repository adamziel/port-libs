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
      <w:r><w:t xml:space="preserve">Reviewer spelling </w:t></w:r>
      <w:proofErr w:type="spellStart"/>
      <w:r><w:t>migraton before table</w:t></w:r>
    </w:p>
    <w:tbl>
      <w:tr>
        <w:tc>
          <w:p>
            <w:r><w:t>closes inside cell</w:t></w:r>
            <w:proofErr w:type="spellEnd"/>
            <w:r><w:t xml:space="preserve"> after proof.</w:t></w:r>
          </w:p>
        </w:tc>
      </w:tr>
    </w:tbl>
    <w:p>
      <w:r><w:t xml:space="preserve">Reviewer permission </w:t></w:r>
      <w:permStart w:id="91" w:user="reviewer@example.test"/>
      <w:r><w:t>starts before table</w:t></w:r>
    </w:p>
    <w:tbl>
      <w:tr>
        <w:tc>
          <w:p>
            <w:r><w:t>continues in cell</w:t></w:r>
            <w:permEnd w:id="91"/>
            <w:r><w:t xml:space="preserve"> after permission.</w:t></w:r>
          </w:p>
        </w:tc>
      </w:tr>
    </w:tbl>
    <w:p>
      <w:r><w:t xml:space="preserve">Reviewer move </w:t></w:r>
      <w:moveToRangeStart w:id="92" w:author="Migration Editor" w:date="2026-06-08T12:43:39Z" w:name="table_move_destination"/>
      <w:r><w:t>starts before table</w:t></w:r>
    </w:p>
    <w:tbl>
      <w:tr>
        <w:tc>
          <w:p>
            <w:r><w:t>continues in cell</w:t></w:r>
            <w:moveToRangeEnd w:id="92"/>
            <w:r><w:t xml:space="preserve"> after move.</w:t></w:r>
          </w:p>
        </w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML],
]);

$document = (new DocxReader())->readDocument($package);
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'proof' => '<td><p><span class="docx-proof-error docx-proof-spelling" data-docx-proof-error="spelling" data-docx-proof-start="spellStart" data-docx-proof-end="spellEnd">closes inside cell</span> after proof.</p></td>',
        'permission' => '<td><p><span class="docx-permission-range docx-permission-user" data-docx-permission-id="91" data-docx-permission-user="reviewer@example.test">continues in cell</span> after permission.</p></td>',
        'move' => '<td><p><span class="docx-move-to-range" data-docx-change="move-to-range" data-docx-change-id="92" data-docx-author="Migration Editor" data-docx-date="2026-06-08T12:43:39Z" data-docx-move-range-name="table_move_destination">continues in cell</span> after move.</p></td>',
    ] as $name => $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('DOCX reviewer table range handoff missing ' . $name . ' table-cell metadata');
        }
    }

    echo "wordpress-docx-reviewer-table-ranges self-test passed\n";
    return;
}

echo json_encode([
    'childCount' => count($document->children),
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
