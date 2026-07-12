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
  <w:style w:type="table" w:styleId="ConditionalColumnReviewTable">
    <w:name w:val="Conditional Column Review Table"/>
    <w:tblStylePr w:type="firstRow">
      <w:trPr><w:tblHeader/></w:trPr>
      <w:tcPr><w:shd w:val="clear" w:fill="DDEBF7"/></w:tcPr>
      <w:pPr><w:jc w:val="center"/></w:pPr>
      <w:rPr><w:b/></w:rPr>
    </w:tblStylePr>
    <w:tblStylePr w:type="firstCol">
      <w:tcPr><w:shd w:val="clear" w:fill="D9EAD3"/></w:tcPr>
      <w:pPr><w:jc w:val="start"/></w:pPr>
      <w:rPr><w:color w:val="38761D"/></w:rPr>
    </w:tblStylePr>
    <w:tblStylePr w:type="lastCol">
      <w:tcPr><w:shd w:val="clear" w:fill="FCE5CD"/></w:tcPr>
      <w:pPr><w:jc w:val="end"/></w:pPr>
      <w:rPr><w:i/></w:rPr>
    </w:tblStylePr>
    <w:tblStylePr w:type="nwCell">
      <w:tcPr><w:shd w:val="clear" w:fill="B6D7A8"/></w:tcPr>
      <w:pPr><w:jc w:val="left"/></w:pPr>
      <w:rPr><w:b/><w:color w:val="274E13"/></w:rPr>
    </w:tblStylePr>
    <w:tblStylePr w:type="neCell">
      <w:tcPr><w:shd w:val="clear" w:fill="F9CB9C"/></w:tcPr>
      <w:rPr><w:smallCaps/></w:rPr>
    </w:tblStylePr>
    <w:tblStylePr w:type="swCell">
      <w:tcPr><w:shd w:val="clear" w:fill="D9D2E9"/></w:tcPr>
      <w:rPr><w:u w:val="single"/></w:rPr>
    </w:tblStylePr>
    <w:tblStylePr w:type="seCell">
      <w:tcPr><w:shd w:val="clear" w:fill="F4CCCC"/></w:tcPr>
      <w:rPr><w:strike/></w:rPr>
    </w:tblStylePr>
  </w:style>
</w:styles>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tblPr>
        <w:tblStyle w:val="ConditionalColumnReviewTable"/>
      </w:tblPr>
      <w:tblGrid>
        <w:gridCol w:w="1200"/>
        <w:gridCol w:w="1600"/>
        <w:gridCol w:w="1600"/>
        <w:gridCol w:w="1200"/>
      </w:tblGrid>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Top left</w:t></w:r></w:p></w:tc>
        <w:tc>
          <w:tcPr><w:gridSpan w:val="2"/></w:tcPr>
          <w:p><w:r><w:t>Spanned header middle</w:t></w:r></w:p>
        </w:tc>
        <w:tc><w:p><w:r><w:t>Top right</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Left body</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Body one</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Body two</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Right body</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Bottom left</w:t></w:r></w:p></w:tc>
        <w:tc>
          <w:tcPr><w:gridSpan w:val="2"/></w:tcPr>
          <w:p><w:r><w:t>Bottom middle</w:t></w:r></w:p>
        </w:tc>
        <w:tc><w:p><w:r><w:t>Bottom right</w:t></w:r></w:p></w:tc>
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
        throw new RuntimeException('DOCX conditional column-style example did not import a table');
    }

    $attrs = $table->attr('attributes', []);
    if (($attrs['data-docx-table-style-region-types'] ?? null) !== 'firstRow firstCol lastCol nwCell neCell swCell seCell') {
        throw new RuntimeException('DOCX conditional column-style example did not preserve conditional region order');
    }

    $body = $table->children[0] ?? null;
    $headerRow = $body instanceof AstNode ? ($body->children[0] ?? null) : null;
    $middleRow = $body instanceof AstNode ? ($body->children[1] ?? null) : null;
    $footerRow = $body instanceof AstNode ? ($body->children[2] ?? null) : null;
    $topLeft = $headerRow instanceof AstNode ? ($headerRow->children[0] ?? null) : null;
    $topMiddle = $headerRow instanceof AstNode ? ($headerRow->children[1] ?? null) : null;
    $topRight = $headerRow instanceof AstNode ? ($headerRow->children[2] ?? null) : null;
    $middleLeft = $middleRow instanceof AstNode ? ($middleRow->children[0] ?? null) : null;
    $middleRight = $middleRow instanceof AstNode ? ($middleRow->children[3] ?? null) : null;
    $bottomLeft = $footerRow instanceof AstNode ? ($footerRow->children[0] ?? null) : null;
    $bottomMiddle = $footerRow instanceof AstNode ? ($footerRow->children[1] ?? null) : null;
    $bottomRight = $footerRow instanceof AstNode ? ($footerRow->children[2] ?? null) : null;

    $checks = [
        [$topLeft, 'nwCell', 'B6D7A8'],
        [$topMiddle, 'firstRow', 'DDEBF7'],
        [$topRight, 'neCell', 'F9CB9C'],
        [$middleLeft, 'firstCol', 'D9EAD3'],
        [$middleRight, 'lastCol', 'FCE5CD'],
        [$bottomLeft, 'swCell', 'D9D2E9'],
        [$bottomRight, 'seCell', 'F4CCCC'],
    ];
    foreach ($checks as [$cell, $region, $fill]) {
        if (!$cell instanceof AstNode) {
            throw new RuntimeException('DOCX conditional column-style example is missing an expected cell');
        }

        $cellAttrs = $cell->attr('attributes', []);
        if (($cellAttrs['data-docx-table-style-cell-region'] ?? null) !== $region) {
            throw new RuntimeException('DOCX conditional column-style example did not apply ' . $region . ' to its visual cell');
        }
        if (($cellAttrs['data-docx-cell-shading-fill'] ?? null) !== $fill) {
            throw new RuntimeException('DOCX conditional column-style example did not apply ' . $region . ' shading');
        }
    }

    if (!$bottomMiddle instanceof AstNode || ($bottomMiddle->attr('attributes', [])['data-docx-table-style-cell-region'] ?? null) !== null) {
        throw new RuntimeException('DOCX conditional column-style example incorrectly styled a middle spanned cell as an edge column');
    }

    if (!str_contains($blocks, 'data-docx-table-style-cell-region="nwCell"')
        || !str_contains($blocks, 'data-docx-table-style-cell-region="seCell"')
        || !str_contains($blocks, '<em><del>Bottom right</del></em>')) {
        throw new RuntimeException('DOCX conditional column-style example did not render corner metadata to WordPress blocks');
    }

    echo "wordpress-docx-conditional-column-style-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
