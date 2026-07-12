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
</Relationships>
XML],
    ['name' => 'word/styles.xml', 'data' => <<<'XML'
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="table" w:styleId="ConditionalReviewTable">
    <w:name w:val="Conditional Review Table"/>
    <w:tblPr>
      <w:tblW w:type="pct" w:w="5000"/>
      <w:jc w:val="center"/>
    </w:tblPr>
    <w:tblStylePr w:type="firstRow">
      <w:trPr><w:tblHeader/></w:trPr>
      <w:tcPr><w:shd w:val="clear" w:fill="CFE2F3" w:color="auto"/></w:tcPr>
      <w:rPr><w:b/><w:color w:val="1F4E79"/></w:rPr>
    </w:tblStylePr>
    <w:tblStylePr w:type="band1Horz">
      <w:tcPr><w:shd w:val="clear" w:fill="F3F6FA" w:themeFill="accent1" w:themeFillTint="33"/></w:tcPr>
      <w:rPr><w:i w:val="0"/></w:rPr>
    </w:tblStylePr>
    <w:tblStylePr w:type="lastRow">
      <w:trPr><w:cantSplit/></w:trPr>
      <w:tcPr><w:shd w:val="clear" w:fill="EADCF8"/></w:tcPr>
      <w:rPr><w:smallCaps/></w:rPr>
    </w:tblStylePr>
  </w:style>
</w:styles>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tblPr>
        <w:tblStyle w:val="ConditionalReviewTable"/>
      </w:tblPr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Styled header</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Reviewer status</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Media packet</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Needs alt text</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Final owner</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Import desk</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML],
]);

$document = (new DocxReader())->readDocument($package);
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    $table = $document->children[0] ?? null;
    if (!$table instanceof AstNode || $table->type !== 'table') {
        throw new RuntimeException('DOCX conditional table-style example did not import a table');
    }

    $attrs = $table->attr('attributes', []);
    if (($attrs['data-docx-table-style-region-count'] ?? null) !== '3') {
        throw new RuntimeException('DOCX conditional table-style example did not preserve region count');
    }
    if (($attrs['data-docx-table-style-region-types'] ?? null) !== 'firstRow band1Horz lastRow') {
        throw new RuntimeException('DOCX conditional table-style example did not preserve region order');
    }
    if (($attrs['data-docx-table-style-region-1-cell-shading-fill'] ?? null) !== 'CFE2F3') {
        throw new RuntimeException('DOCX conditional table-style example did not preserve first-row shading metadata');
    }
    if (($attrs['data-docx-table-style-region-2-cell-shading-theme-fill'] ?? null) !== 'accent1') {
        throw new RuntimeException('DOCX conditional table-style example did not preserve banded-row theme metadata');
    }
    if (($attrs['data-docx-table-style-region-3-row-cant-split'] ?? null) !== 'true') {
        throw new RuntimeException('DOCX conditional table-style example did not preserve last-row cantSplit metadata');
    }

    $body = $table->children[0] ?? null;
    $firstRow = $body instanceof AstNode ? ($body->children[0] ?? null) : null;
    $bandedRow = $body instanceof AstNode ? ($body->children[1] ?? null) : null;
    $lastRow = $body instanceof AstNode ? ($body->children[2] ?? null) : null;
    $firstCell = $firstRow instanceof AstNode ? ($firstRow->children[0] ?? null) : null;
    $bandedCell = $bandedRow instanceof AstNode ? ($bandedRow->children[0] ?? null) : null;
    $lastCell = $lastRow instanceof AstNode ? ($lastRow->children[0] ?? null) : null;

    if (!$firstRow instanceof AstNode || ($firstRow->attr('attributes', [])['data-docx-table-style-row-region'] ?? null) !== 'firstRow') {
        throw new RuntimeException('DOCX conditional table-style example did not apply the firstRow row style');
    }
    if (!$bandedRow instanceof AstNode || ($bandedRow->attr('attributes', [])['data-docx-table-style-row-region'] ?? null) !== 'band1Horz') {
        throw new RuntimeException('DOCX conditional table-style example did not apply the banded row style');
    }
    if (!$lastRow instanceof AstNode || ($lastRow->attr('attributes', [])['data-docx-table-style-row-region'] ?? null) !== 'lastRow') {
        throw new RuntimeException('DOCX conditional table-style example did not apply the lastRow row style');
    }
    if (!$firstCell instanceof AstNode || ($firstCell->attr('attributes', [])['data-docx-table-style-cell-region'] ?? null) !== 'firstRow') {
        throw new RuntimeException('DOCX conditional table-style example did not apply the firstRow cell style');
    }
    if (($firstCell->attr('attributes', [])['data-docx-cell-shading-fill'] ?? null) !== 'CFE2F3') {
        throw new RuntimeException('DOCX conditional table-style example did not apply first-row cell shading');
    }
    if (!$bandedCell instanceof AstNode || ($bandedCell->attr('attributes', [])['data-docx-table-style-cell-region'] ?? null) !== 'band1Horz') {
        throw new RuntimeException('DOCX conditional table-style example did not apply the banded cell style');
    }
    if (!$lastCell instanceof AstNode || ($lastCell->attr('attributes', [])['data-docx-table-style-cell-region'] ?? null) !== 'lastRow') {
        throw new RuntimeException('DOCX conditional table-style example did not apply the lastRow cell style');
    }

    if (!str_contains($blocks, 'data-docx-table-style-region-types="firstRow band1Horz lastRow"')) {
        throw new RuntimeException('DOCX conditional table-style example did not render region metadata to WordPress blocks');
    }
    if (!str_contains($blocks, 'data-docx-table-style-cell-region="band1Horz"')) {
        throw new RuntimeException('DOCX conditional table-style example did not render applied banded cell metadata');
    }
    if (!str_contains($blocks, 'font-variant:small-caps">Final owner</span>')) {
        throw new RuntimeException('DOCX conditional table-style example did not render applied last-row run styling');
    }

    echo "wordpress-docx-conditional-table-style-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
