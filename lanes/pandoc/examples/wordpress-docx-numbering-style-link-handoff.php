<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
</Types>
XML],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
</Relationships>
XML],
    ['name' => 'word/styles.xml', 'data' => <<<'XML'
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="WpImportChecklist">
    <w:name w:val="WP Import Checklist"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="WpImportSubtask">
    <w:name w:val="WP Import Subtask"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="WpImportDerivedSubtask">
    <w:name w:val="Derived WP Import Subtask"/>
    <w:basedOn w:val="WpImportSubtask"/>
  </w:style>
</w:styles>
XML],
    ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="76">
    <w:lvl w:ilvl="0">
      <w:pStyle w:val="WpImportChecklist"/>
      <w:numFmt w:val="bullet"/>
      <w:lvlText w:val="-"/>
    </w:lvl>
    <w:lvl w:ilvl="1">
      <w:start w:val="4"/>
      <w:pStyle w:val="WpImportSubtask"/>
      <w:numFmt w:val="lowerLetter"/>
      <w:lvlText w:val="%2)"/>
    </w:lvl>
  </w:abstractNum>
  <w:num w:numId="77">
    <w:abstractNumId w:val="76"/>
  </w:num>
</w:numbering>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="WpImportChecklist"/></w:pPr>
      <w:r><w:t>Archive DOCX source</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:pStyle w:val="WpImportChecklist"/></w:pPr>
      <w:r><w:t>Review packet</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:pStyle w:val="WpImportDerivedSubtask"/></w:pPr>
      <w:r><w:t>Check comments</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr>
        <w:pStyle w:val="WpImportChecklist"/>
        <w:numPr><w:numId w:val="0"/></w:numPr>
      </w:pPr>
      <w:r><w:t>Suppressed checklist marker remains a paragraph.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML],
]);

$document = (new DocxReader())->readDocument($package);
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    $checklist = $document->children[0] ?? null;
    if (!$checklist instanceof AstNode || $checklist->type !== 'bullet_list') {
        throw new RuntimeException('DOCX numbering style-link example did not import the checklist as a list');
    }
    if ($checklist->attr('numId') !== '77' || $checklist->attr('level') !== 0) {
        throw new RuntimeException('DOCX numbering style-link example did not preserve the top-level numbering metadata');
    }

    $sublist = $checklist->children[1]->children[1] ?? null;
    if (!$sublist instanceof AstNode || $sublist->type !== 'ordered_list') {
        throw new RuntimeException('DOCX numbering style-link example did not derive the nested list from basedOn style metadata');
    }
    if ($sublist->attr('start') !== 4 || $sublist->attr('style') !== 'lower_alpha') {
        throw new RuntimeException('DOCX numbering style-link example did not preserve nested numbering format metadata');
    }

    $suppressed = $document->children[1] ?? null;
    if (!$suppressed instanceof AstNode || $suppressed->type !== 'paragraph') {
        throw new RuntimeException('DOCX numbering style-link example did not preserve explicit numId=0 suppression');
    }

    if (!str_contains($blocks, '<ul><li>Archive DOCX source</li><li>Review packet<ol start="4" type="a"><li>Check comments</li></ol></li></ul>')) {
        throw new RuntimeException('DOCX numbering style-link example did not render nested WordPress list blocks');
    }
    if (!str_contains($blocks, '<p>Suppressed checklist marker remains a paragraph.</p>')) {
        throw new RuntimeException('DOCX numbering style-link example did not render suppressed numbering as a paragraph');
    }

    echo "wordpress-docx-numbering-style-link-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
