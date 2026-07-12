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
  <w:style w:type="table" w:styleId="BaseReviewTable">
    <w:name w:val="Base Review Table"/>
    <w:tblPr>
      <w:tblW w:type="pct" w:w="5000"/>
      <w:jc w:val="center"/>
      <w:tblInd w:type="dxa" w:w="240"/>
      <w:tblLayout w:type="fixed"/>
    </w:tblPr>
  </w:style>
  <w:style w:type="table" w:styleId="DerivedReviewTable">
    <w:name w:val="Derived Review Table"/>
    <w:basedOn w:val="BaseReviewTable"/>
    <w:tblPr>
      <w:tblW w:type="pct" w:w="4200"/>
      <w:jc w:val="end"/>
    </w:tblPr>
  </w:style>
</w:styles>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tblPr>
        <w:tblStyle w:val="DerivedReviewTable"/>
      </w:tblPr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Source field</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Review value</w:t></w:r></w:p></w:tc>
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
        throw new RuntimeException('DOCX table-style example did not import a table');
    }

    $attrs = $table->attr('attributes', []);
    if (($attrs['data-docx-table-style-name'] ?? null) !== 'Derived Review Table') {
        throw new RuntimeException('DOCX table-style example did not preserve inherited style name metadata');
    }
    if (($attrs['data-docx-table-style-based-on'] ?? null) !== 'BaseReviewTable') {
        throw new RuntimeException('DOCX table-style example did not preserve style basedOn metadata');
    }
    if (($attrs['data-docx-table-width-percent'] ?? null) !== '84') {
        throw new RuntimeException('DOCX table-style example did not apply derived table width');
    }
    if (($attrs['data-docx-table-indent-left-points'] ?? null) !== '12') {
        throw new RuntimeException('DOCX table-style example did not inherit base table indent');
    }
    if (($attrs['data-docx-table-layout'] ?? null) !== 'fixed') {
        throw new RuntimeException('DOCX table-style example did not inherit base table layout');
    }
    if (!str_contains($blocks, 'data-docx-table-style-name="Derived Review Table"')) {
        throw new RuntimeException('DOCX table-style example did not render table style name to WordPress blocks');
    }
    if (!str_contains($blocks, 'style="margin-left:12pt; width:84%"')) {
        throw new RuntimeException('DOCX table-style example did not render inherited table layout style to WordPress blocks');
    }

    echo "wordpress-docx-table-style-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
