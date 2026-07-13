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
  <Default Extension="xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/charts/style1.xml" ContentType="application/vnd.ms-office.chartstyle+xml"/>
  <Override PartName="/word/charts/colors1.xml" ContentType="application/vnd.ms-office.chartcolorstyle+xml"/>
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
  <Relationship Id="rIdChartStyle" Type="http://schemas.microsoft.com/office/2011/relationships/chartStyle" Target="style1.xml"/>
  <Relationship Id="rIdChartColors" Type="http://schemas.microsoft.com/office/2011/relationships/chartColorStyle" Target="colors1.xml"/>
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
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <c:style val="201"/>
  <c:clrMapOvr>
    <a:overrideClrMapping bg1="lt1" tx1="dk1" accent1="accent3" accent2="accent4" hlink="hlink" folHlink="folHlink"/>
  </c:clrMapOvr>
  <c:chart>
    <c:title>
      <c:tx><c:rich><a:bodyPr/><a:p><a:r><a:t>Quarterly migration revenue</a:t></a:r></a:p></c:rich></c:tx>
    </c:title>
    <c:plotArea>
      <c:barChart>
        <c:barDir val="col"/>
        <c:grouping val="clustered"/>
        <c:varyColors val="1"/>
        <c:ser>
          <c:idx val="0"/>
          <c:order val="0"/>
          <c:tx><c:v>North region</c:v></c:tx>
        </c:ser>
        <c:ser>
          <c:idx val="1"/>
          <c:order val="1"/>
          <c:tx><c:strRef><c:strCache><c:pt idx="0"><c:v>South region</c:v></c:pt></c:strCache></c:strRef></c:tx>
        </c:ser>
        <c:dLbls>
          <c:dLblPos val="outEnd"/>
          <c:showLegendKey val="0"/>
          <c:showVal val="1"/>
          <c:showCatName val="0"/>
          <c:showSerName val="1"/>
          <c:showPercent val="0"/>
          <c:separator> | </c:separator>
          <c:numFmt formatCode="#,##0" sourceLinked="0"/>
        </c:dLbls>
      </c:barChart>
      <c:catAx>
        <c:axId val="10"/>
        <c:title><c:tx><c:rich><a:bodyPr/><a:p><a:r><a:t>Quarter</a:t></a:r></a:p></c:rich></c:tx></c:title>
      </c:catAx>
      <c:valAx>
        <c:axId val="20"/>
        <c:title><c:tx><c:rich><a:bodyPr/><a:p><a:r><a:t>Revenue</a:t></a:r></a:p></c:rich></c:tx></c:title>
      </c:valAx>
    </c:plotArea>
    <c:legend>
      <c:legendPos val="r"/>
      <c:layout>
        <c:manualLayout>
          <c:x val="0.72"/>
          <c:y val="0.18"/>
          <c:w val="0.20"/>
          <c:h val="0.35"/>
        </c:manualLayout>
      </c:layout>
      <c:overlay val="0"/>
    </c:legend>
  </c:chart>
  <c:externalData r:id="rIdChartWorkbook">
    <c:autoUpdate val="0"/>
  </c:externalData>
</c:chartSpace>
XML],
    ['name' => 'word/charts/style1.xml', 'data' => '<c15:chartStyle xmlns:c15="http://schemas.microsoft.com/office/drawing/2012/chartStyle" id="201"/>'],
    ['name' => 'word/charts/colors1.xml', 'data' => '<c15:colorStyle xmlns:c15="http://schemas.microsoft.com/office/drawing/2012/chartStyle" meth="cycle"/>'],
    ['name' => 'word/embeddings/chart-data.xlsx', 'data' => 'XLSXCHARTDATA'],
]);

$result = (new DocxReader())->readPackage($package);
$document = $result['document'];
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
    if (($attrs['data-docx-chart-style-val'] ?? null) !== '201') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose the chart style value');
    }
    if (($attrs['data-docx-chart-color-map-accent-1'] ?? null) !== 'accent3') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose chart color mapping metadata');
    }
    if (($attrs['data-docx-chart-title'] ?? null) !== 'Quarterly migration revenue') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose chart title metadata');
    }
    if (($attrs['data-docx-chart-series-2-name'] ?? null) !== 'South region') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose chart series metadata');
    }
    if (($attrs['data-docx-chart-axis-1-title'] ?? null) !== 'Quarter') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose chart axis title metadata');
    }
    if (($attrs['data-docx-chart-plot-1-type'] ?? null) !== 'barChart') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose chart plot metadata');
    }
    if (($attrs['data-docx-chart-plot-1-data-label-show-value'] ?? null) !== 'true') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose data-label metadata');
    }
    if (($attrs['data-docx-chart-legend-position'] ?? null) !== 'r') {
        throw new RuntimeException('DOCX chart embedded-data example did not expose chart legend metadata');
    }
    if (($attrs['data-docx-chart-style-target-part'] ?? null) !== '/word/charts/style1.xml') {
        throw new RuntimeException('DOCX chart embedded-data example did not resolve the chart style part');
    }
    if (($attrs['data-docx-chart-color-style-target-part'] ?? null) !== '/word/charts/colors1.xml') {
        throw new RuntimeException('DOCX chart embedded-data example did not resolve the chart color-style part');
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
    if (!str_contains($blocks, 'class="docx-drawing-placeholder docx-drawing-chart docx-chart-style docx-chart-override-color-mapping docx-chart-title docx-chart-series docx-chart-axis-title docx-chart-plot docx-chart-plot-bar docx-chart-data-labels docx-chart-legend docx-chart-style-part docx-chart-color-style-part docx-chart-embedded-data"')) {
        throw new RuntimeException('DOCX chart embedded-data example did not render the WordPress review class');
    }
    if (!str_contains($blocks, 'data-docx-chart-title="Quarterly migration revenue"')) {
        throw new RuntimeException('DOCX chart embedded-data example did not render chart title metadata');
    }
    if (!str_contains($blocks, 'data-docx-chart-axis-2-title="Revenue"')) {
        throw new RuntimeException('DOCX chart embedded-data example did not render chart axis metadata');
    }
    if (!str_contains($blocks, 'data-docx-chart-plot-1-grouping="clustered"')) {
        throw new RuntimeException('DOCX chart embedded-data example did not render chart plot metadata');
    }
    if (!str_contains($blocks, 'data-docx-chart-plot-1-data-label-number-format="#,##0"')) {
        throw new RuntimeException('DOCX chart embedded-data example did not render data-label metadata');
    }
    if (!str_contains($blocks, 'data-docx-chart-legend-layout-width="0.20"')) {
        throw new RuntimeException('DOCX chart embedded-data example did not render chart legend metadata');
    }
    if (!str_contains($blocks, 'data-docx-chart-style-target-part="/word/charts/style1.xml"')) {
        throw new RuntimeException('DOCX chart embedded-data example did not render chart style metadata');
    }
    if (!str_contains($blocks, 'data-docx-chart-color-style-target-part="/word/charts/colors1.xml"')) {
        throw new RuntimeException('DOCX chart embedded-data example did not render chart color-style metadata');
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
    'blocks' => $blocks,
    'relationshipCount' => $result['importReport']['relationshipCount'] ?? null,
    'reachableRelationshipCount' => $result['importReport']['reachableRelationshipCount'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
