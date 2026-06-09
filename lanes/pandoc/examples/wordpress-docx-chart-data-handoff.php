<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
</Types>
XML],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/chart1.xml"/>
</Relationships>
XML],
    ['name' => 'word/charts/_rels/chart1.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdChartWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="../embeddings/chart-data.xlsx"/>
</Relationships>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="44" name="Embedded data chart" descr="Revenue workbook chart" title="Revenue workbook"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">
                <c:chart r:id="rIdChart"/>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML],
    ['name' => 'word/charts/chart1.xml', 'data' => <<<'XML'
<c:chartSpace
  xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <c:externalData r:id="rIdChartWorkbook">
    <c:autoUpdate val="0"/>
  </c:externalData>
</c:chartSpace>
XML],
    ['name' => 'word/embeddings/chart-data.xlsx', 'data' => 'XLSXCHARTDATA'],
]);

$result = (new DocxReader())->readPackage($package);
$document = $result['document'];
$markdown = (new MarkdownWriter())->write($document);
$blocks = (new WordPressBlockWriter())->write($document);
$chart = $document->children[0]->children[0] ?? null;

if (in_array('--self-test', $argv, true)) {
    if (!$chart instanceof AstNode || $chart->type !== 'span') {
        throw new RuntimeException('DOCX chart embedded-data example did not import a chart placeholder span');
    }

    $classes = $chart->attr('classes');
    if (!is_array($classes) || !in_array('docx-chart-embedded-data', $classes, true)) {
        throw new RuntimeException('DOCX chart embedded-data example did not expose the embedded-data class');
    }

    $attrs = $chart->attr('attributes');
    if (!is_array($attrs) || ($attrs['data-docx-chart-external-data-id'] ?? null) !== 'rIdChartWorkbook') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose the workbook relationship id');
    }
    if (($attrs['data-docx-chart-external-data-target-part'] ?? null) !== '/word/embeddings/chart-data.xlsx') {
        throw new RuntimeException('DOCX chart embedded-data example did not resolve the workbook target part');
    }
    if (($attrs['data-docx-chart-external-data-content-type'] ?? null) !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose workbook content type');
    }
    if (($attrs['data-docx-chart-external-data-bytes'] ?? null) !== '13') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose workbook byte count');
    }
    if (!str_contains($blocks, 'class="docx-drawing-placeholder docx-drawing-chart docx-chart-embedded-data"')) {
        throw new RuntimeException('DOCX chart embedded-data example did not render the WordPress review class');
    }
    if (!str_contains($blocks, 'data-docx-chart-external-data-target-part="/word/embeddings/chart-data.xlsx"')) {
        throw new RuntimeException('DOCX chart embedded-data example did not render workbook target metadata');
    }

    echo "wordpress-docx-chart-data-handoff self-test passed\n";
    return;
}

echo json_encode([
    'text' => $chart instanceof AstNode ? $chart->children[0]->attr('text') : null,
    'classes' => $chart instanceof AstNode ? $chart->attr('classes') : null,
    'attributes' => $chart instanceof AstNode ? $chart->attr('attributes') : null,
    'markdown' => $markdown,
    'blocks' => $blocks,
    'relationshipCount' => $result['importReport']['relationshipCount'] ?? null,
    'reachableRelationshipCount' => $result['importReport']['reachableRelationshipCount'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
