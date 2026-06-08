<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
XML],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdFigure" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/workflow.png"/>
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML],
    ['name' => 'word/styles.xml', 'data' => <<<'XML'
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Caption">
    <w:name w:val="caption"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="FigureCaption">
    <w:name w:val="Figure Caption Review"/>
    <w:basedOn w:val="Caption"/>
  </w:style>
</w:styles>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="41" name="Workflow figure" descr="Workflow diagram alt" title="Workflow title"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdFigure"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:p>
      <w:pPr><w:pStyle w:val="FigureCaption"/></w:pPr>
      <w:r><w:t xml:space="preserve">Figure </w:t></w:r>
      <w:fldSimple w:instr='SEQ Figure \* ARABIC'><w:r><w:t>1</w:t></w:r></w:fldSimple>
      <w:r><w:t>: Workflow diagram for review.</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>After figure copy.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
    ['name' => 'word/media/workflow.png', 'data' => 'FLOWPNG'],
]);

$result = (new DocxReader())->readPackage($package);
$document = $result['document'];
$blocks = (new WordPressBlockWriter())->write($document);
$figure = $document->children[0] ?? null;

if (in_array('--self-test', $argv, true)) {
    if (!$figure instanceof \PortLibs\Pandoc\AstNode || $figure->type !== 'figure') {
        throw new RuntimeException('DOCX captioned drawing did not import as a figure AST node');
    }
    if ($figure->attr('caption') !== 'Figure 1: Workflow diagram for review.') {
        throw new RuntimeException('DOCX captioned drawing did not preserve the caption text');
    }
    if (($figure->attr('captionSource')['style'] ?? null) !== 'FigureCaption') {
        throw new RuntimeException('DOCX captioned drawing did not preserve caption style provenance');
    }
    if (($figure->attr('captionSource')['sequence']['name'] ?? null) !== 'Figure') {
        throw new RuntimeException('DOCX captioned drawing did not preserve caption sequence provenance');
    }
    if (($figure->attr('attributes')['data-docx-caption-number'] ?? null) !== '1') {
        throw new RuntimeException('DOCX captioned drawing did not preserve caption sequence result');
    }
    if (!str_contains($blocks, '<figcaption>Figure 1: Workflow diagram for review.</figcaption>')) {
        throw new RuntimeException('DOCX captioned drawing did not render a WordPress figure caption');
    }
    if (!str_contains($blocks, 'data-docx-caption-sequence="Figure" data-docx-caption-number="1"')) {
        throw new RuntimeException('DOCX captioned drawing did not render caption sequence metadata');
    }
    if (!str_contains($blocks, '<img src="word/media/workflow.png" alt="Workflow diagram alt" title="Workflow title"/>')) {
        throw new RuntimeException('DOCX captioned drawing did not render the image metadata into WordPress blocks');
    }
    if (!str_contains($blocks, '<p>After figure copy.</p>')) {
        throw new RuntimeException('DOCX captioned drawing example dropped the following body paragraph');
    }

    echo "wordpress-docx-captioned-figure-handoff self-test passed\n";
    return;
}

echo json_encode([
    'caption' => $figure instanceof \PortLibs\Pandoc\AstNode ? $figure->attr('caption') : null,
    'captionSource' => $figure instanceof \PortLibs\Pandoc\AstNode ? $figure->attr('captionSource') : null,
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
