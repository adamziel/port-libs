<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PptxReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads pptx package slides text media notes and tables into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/><Override PartName="/ppt/slides/slide2.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/><Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/><Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/><Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/><Override PartName="/ppt/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/><Override PartName="/ppt/notesSlides/notesSlide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.notesSlide+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdOffice" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"><dc:title>PPTX Reader Demo</dc:title><dc:creator>Port Libs</dc:creator><dc:description>Bounded PPTX reader smoke.</dc:description><cp:keywords>slides,import</cp:keywords><dcterms:created>2026-06-25T00:00:00Z</dcterms:created></cp:coreProperties>');
        $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0"?>
<p:presentation
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldSz cx="12192000" cy="6858000"/>
  <p:sldIdLst>
    <p:sldId id="256" r:id="rId1"/>
    <p:sldId id="257" r:id="rId2"/>
  </p:sldIdLst>
</p:presentation>
XML);
        $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide2.xml"/></Relationships>');
        $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/><Relationship Id="rIdHyper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/pptx" TargetMode="External"/><Relationship Id="rIdNotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide" Target="../notesSlides/notesSlide1.xml"/><Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/><Relationship Id="rIdChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart1.xml"/></Relationships>');
        $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0"?>
<p:sld
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld>
    <p:spTree>
      <p:nvGrpSpPr/><p:grpSpPr/>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="1" name="Title"/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Slide One</a:t></a:r></a:p></p:txBody>
      </p:sp>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="2" name="Body"/><p:nvPr/></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/>
          <a:p><a:r><a:t>A </a:t></a:r><a:r><a:rPr b="1"><a:hlinkClick r:id="rIdHyper"/></a:rPr><a:t>linked bold</a:t></a:r><a:r><a:t> and </a:t></a:r><a:r><a:rPr i="1"/><a:t>italic</a:t></a:r><a:r><a:t> run.</a:t></a:r></a:p>
          <a:p><a:pPr><a:buChar char="•"/></a:pPr><a:r><a:t>Bullet one</a:t></a:r></a:p>
          <a:p><a:pPr><a:buChar char="•"/></a:pPr><a:r><a:t>Bullet two</a:t></a:r></a:p>
          <a:p><a:pPr><a:buAutoNum type="arabicPeriod" startAt="3"/></a:pPr><a:r><a:t>Step three</a:t></a:r></a:p>
        </p:txBody>
      </p:sp>
      <p:pic>
        <p:nvPicPr><p:cNvPr id="3" name="Pixel image" descr="Pixel alt"/></p:nvPicPr>
        <p:blipFill><a:blip r:embed="rIdImage"/><a:srcRect l="1000" t="2000" r="3000" b="4000"/></p:blipFill>
        <p:spPr><a:xfrm rot="5400000"><a:off x="914400" y="457200"/><a:ext cx="1828800" cy="914400"/></a:xfrm></p:spPr>
      </p:pic>
      <p:graphicFrame>
        <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table">
          <a:tbl>
            <a:tblPr firstRow="1" bandRow="1"><a:tableStyleId>style-guid</a:tableStyleId></a:tblPr>
            <a:tblGrid><a:gridCol w="1000000"/><a:gridCol w="1200000"/><a:gridCol w="1400000"/></a:tblGrid>
            <a:tr h="360000"><a:tc gridSpan="2"><a:txBody><a:p><a:pPr algn="ctr"/><a:r><a:t>Head A</a:t></a:r></a:p></a:txBody><a:tcPr anchor="mid" marL="91440" marR="91440"><a:solidFill><a:schemeClr val="accent1"/></a:solidFill><a:lnL w="25400"><a:solidFill><a:srgbClr val="111111"/></a:solidFill><a:prstDash val="dash"/></a:lnL></a:tcPr></a:tc><a:tc hMerge="1"><a:txBody><a:p><a:r><a:t>Hidden</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Head C</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
            <a:tr><a:tc rowSpan="2"><a:txBody><a:p><a:r><a:t>Tall A</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Cell B</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Cell C</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
            <a:tr><a:tc vMerge="1"><a:txBody><a:p><a:r><a:t>Hidden</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Cell D</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Cell E</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
          </a:tbl>
        </a:graphicData></a:graphic>
      </p:graphicFrame>
      <p:graphicFrame>
        <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">
          <c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" r:id="rIdChart"/>
        </a:graphicData></a:graphic>
      </p:graphicFrame>
    </p:spTree>
  </p:cSld>
</p:sld>
XML);
        $zip->addFromString('ppt/notesSlides/notesSlide1.xml', <<<'XML'
<?xml version="1.0"?>
<p:notes
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree><p:sp><p:txBody><a:p><a:r><a:t>Speaker note text.</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld>
</p:notes>
XML);
        $zip->addFromString('ppt/slides/_rels/slide2.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/></Relationships>');
        $zip->addFromString('ppt/slides/slide2.xml', <<<'XML'
<?xml version="1.0"?>
<p:sld
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:sp><p:nvSpPr><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr></p:sp>
    <p:sp><p:nvSpPr><p:nvPr><p:ph type="body" idx="2"/></p:nvPr></p:nvSpPr></p:sp>
    <p:sp><p:txBody><a:p><a:r><a:t>Second slide body.</a:t></a:r></a:p></p:txBody></p:sp>
    <p:sp><p:nvSpPr><p:nvPr><p:ph type="ftr" idx="3"/></p:nvPr></p:nvSpPr></p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
        $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdMaster" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/></Relationships>');
        $zip->addFromString('ppt/slideLayouts/slideLayout1.xml', <<<'XML'
<?xml version="1.0"?>
<p:sldLayout
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:bg><p:bgPr><a:solidFill><a:schemeClr val="accent1"/></a:solidFill></p:bgPr></p:bg><p:spTree>
    <p:sp><p:nvSpPr><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:txBody><a:p><a:r><a:t>Inherited Layout Title</a:t></a:r></a:p></p:txBody></p:sp>
    <p:sp><p:nvSpPr><p:nvPr><p:ph type="body" idx="2"/></p:nvPr></p:nvSpPr><p:txBody><a:p><a:r><a:t>Inherited Layout Body</a:t></a:r></a:p></p:txBody></p:sp>
  </p:spTree></p:cSld>
</p:sldLayout>
XML);
        $zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/></Relationships>');
        $zip->addFromString('ppt/slideMasters/slideMaster1.xml', <<<'XML'
<?xml version="1.0"?>
<p:sldMaster
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:sp><p:nvSpPr><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:txBody><a:p><a:r><a:t>Inherited Master Title</a:t></a:r></a:p></p:txBody></p:sp>
    <p:sp><p:nvSpPr><p:nvPr><p:ph type="ftr" idx="3"/></p:nvPr></p:nvSpPr><p:txBody><a:p><a:r><a:t>Inherited Master Footer</a:t></a:r></a:p></p:txBody></p:sp>
  </p:spTree></p:cSld>
</p:sldMaster>
XML);
        $zip->addFromString('ppt/theme/theme1.xml', <<<'XML'
<?xml version="1.0"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Demo Theme">
  <a:themeElements><a:clrScheme name="Demo"><a:dk1><a:srgbClr val="000000"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:accent1><a:srgbClr val="4472C4"/></a:accent1></a:clrScheme></a:themeElements>
</a:theme>
XML);
        $zip->addFromString('ppt/charts/chart1.xml', <<<'XML'
<?xml version="1.0"?>
<c:chartSpace
  xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <c:style val="10"/>
  <c:chart>
    <c:title><c:tx><c:rich><a:p><a:r><a:t>Revenue chart</a:t></a:r></a:p></c:rich></c:tx></c:title>
    <c:plotArea><c:barChart><c:grouping val="clustered"/><c:varyColors val="1"/><c:ser>
      <c:tx><c:strRef><c:strCache><c:pt idx="0"><c:v>Revenue</c:v></c:pt></c:strCache></c:strRef></c:tx>
      <c:cat><c:strRef><c:strCache><c:pt idx="0"><c:v>Q1</c:v></c:pt><c:pt idx="1"><c:v>Q2</c:v></c:pt></c:strCache></c:strRef></c:cat>
      <c:val><c:numRef><c:numCache><c:pt idx="0"><c:v>12</c:v></c:pt><c:pt idx="1"><c:v>18</c:v></c:pt></c:numCache></c:numRef></c:val>
    </c:ser><c:axId val="100"/><c:axId val="200"/></c:barChart><c:catAx><c:axId val="100"/><c:title><c:tx><c:rich><a:p><a:r><a:t>Quarter</a:t></a:r></a:p></c:rich></c:tx></c:title></c:catAx><c:valAx><c:axId val="200"/><c:title><c:tx><c:rich><a:p><a:r><a:t>Revenue</a:t></a:r></a:p></c:rich></c:tx></c:title></c:valAx></c:plotArea>
    <c:legend><c:legendPos val="r"/></c:legend>
  </c:chart>
  <c:externalData r:id="rIdWorkbook"/>
</c:chartSpace>
XML);
        $zip->addFromString('ppt/charts/_rels/chart1.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="../embeddings/Microsoft_Excel_Worksheet1.xlsx"/></Relationships>');
        $zip->addFromString('ppt/media/image1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->close();

        try {
            $document = (new PptxReader())->readPptxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'pptx', 'blocks');
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('PPTX Reader Demo', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('Bounded PPTX reader smoke.', $meta['description']);
        $t->same('slides,import', $meta['keywords']);
        $t->same(2, $meta['pptxSlideCount']);
        $t->same('ppt/presentation.xml', $meta['pptxPresentationPath']);
        $t->same(12192000, $meta['pptxSlideSize']['widthEmu']);
        $t->same(['ppt/media/image1.png'], $meta['pptxMediaFiles']);
        $t->same('div', $document->children[0]->type);
        $t->same(['pptx-slide'], $document->children[0]->attr('classes'));
        $t->same('Slide One', $document->children[0]->children[0]->attr('text'));
        $t->same('Inherited Layout Title', $document->children[1]->children[0]->attr('text'));
        $t->same('Inherited Layout Body', $document->children[1]->children[1]->attr('text'));
        $t->same('Second slide body.', $document->children[1]->children[2]->attr('text'));
        $t->same('Inherited Master Footer', $document->children[1]->children[3]->attr('text'));
        $t->same('ppt/slideLayouts/slideLayout1.xml', $document->children[0]->attr('attributes')['data-pptx-layout-path']);
        $t->same('ppt/slideMasters/slideMaster1.xml', $document->children[0]->attr('attributes')['data-pptx-master-path']);
        $t->same('ppt/theme/theme1.xml', $document->children[0]->attr('attributes')['data-pptx-theme-path']);
        $t->same('#4472C4', $document->children[0]->attr('attributes')['data-pptx-background-color']);
        $image = $document->children[0]->children[4]->children[0];
        $t->same('image', $image->type);
        $t->same('5400000', $image->attr('attributes')['data-pptx-picture-rotation']);
        $t->same('914400', $image->attr('attributes')['data-pptx-picture-offset-x-emu']);
        $t->same('1828800', $image->attr('attributes')['data-pptx-picture-extent-cx-emu']);
        $t->same('1000', $image->attr('attributes')['data-pptx-crop-left']);
        $t->same('4000', $image->attr('attributes')['data-pptx-crop-bottom']);
        $table = null;
        $chart = null;
        foreach ($document->children[0]->children as $child) {
            if ($child->type === 'table') {
                $table = $child;
            }
            if ($child->type === 'div' && $child->attr('classes') === ['pptx-chart']) {
                $chart = $child;
            }
        }
        if (!$table instanceof PortLibs\Pandoc\AstNode) {
            throw new RuntimeException('Expected PPTX table node');
        }
        if (!$chart instanceof PortLibs\Pandoc\AstNode) {
            throw new RuntimeException('Expected PPTX chart node');
        }
        $t->same(2, $table->children[0]->children[0]->children[0]->attr('colspan'));
        $t->same('1000000,1200000,1400000', $table->attr('htmlAttributes')['data-pptx-grid-columns-emu']);
        $t->same('true', $table->attr('htmlAttributes')['data-pptx-table-firstrow']);
        $t->same('style-guid', $table->attr('htmlAttributes')['data-pptx-table-style-id']);
        $t->same('360000', $table->children[0]->children[0]->attr('htmlAttributes')['data-pptx-row-height-emu']);
        $t->same('#4472C4', $table->children[0]->children[0]->children[0]->attr('htmlAttributes')['data-pptx-fill-color']);
        $t->same('91440', $table->children[0]->children[0]->children[0]->attr('htmlAttributes')['data-pptx-margin-left-emu']);
        $t->same('#111111', $table->children[0]->children[0]->children[0]->attr('htmlAttributes')['data-pptx-border-left-color']);
        $t->same('25400', $table->children[0]->children[0]->children[0]->attr('htmlAttributes')['data-pptx-border-left-width-emu']);
        $t->same('dash', $table->children[0]->children[0]->children[0]->attr('htmlAttributes')['data-pptx-border-left-dash']);
        $t->same(2, $table->children[1]->children[0]->children[0]->attr('rowspan'));
        $t->same('Revenue chart', $chart->children[0]->attr('text'));
        $t->same('barChart', $chart->attr('attributes')['data-pptx-chart-types']);
        $t->same('1', $chart->attr('attributes')['data-pptx-chart-series-count']);
        $t->same('r', $chart->attr('attributes')['data-pptx-chart-legend-position']);
        $t->same('100,200', $chart->attr('attributes')['data-pptx-chart-axis-ids']);
        $t->same('Quarter,Revenue', $chart->attr('attributes')['data-pptx-chart-axis-titles']);
        $t->same('clustered', $chart->attr('attributes')['data-pptx-chart-groupings']);
        $t->same('true', $chart->attr('attributes')['data-pptx-chart-vary-colors']);
        $t->same('10', $chart->attr('attributes')['data-pptx-chart-style']);
        $t->same('ppt/embeddings/Microsoft_Excel_Worksheet1.xlsx', $chart->attr('attributes')['data-pptx-chart-workbook']);
        $t->same('Q2', $chart->children[1]->children[1]->children[1]->children[0]->attr('text'));
        $t->same('18', $chart->children[1]->children[1]->children[1]->children[1]->attr('text'));
        $t->contains('class="pptx-slide"', $blocks);
        $t->contains('<h2 id="slide-1">Slide One</h2>', $blocks);
        $t->contains('<a href="https://example.test/pptx"><strong>linked bold</strong></a>', $blocks);
        $t->contains('<em>italic</em>', $blocks);
        $t->contains('<ul><li>Bullet one</li><li>Bullet two</li></ul>', $blocks);
        $t->contains('<ol start="3"><li>Step three</li></ol>', $blocks);
        $t->contains('<img src="ppt/media/image1.png" alt="Pixel alt" title="Pixel image"', $blocks);
        $t->contains('data-pptx-picture-rotation="5400000"', $blocks);
        $t->contains('data-pptx-crop-bottom="4000"', $blocks);
        $t->contains('data-pptx-grid-columns-emu="1000000,1200000,1400000"', $blocks);
        $t->contains('data-pptx-table-firstrow="true"', $blocks);
        $t->contains('data-pptx-fill-color="#4472C4" data-pptx-border-color="#111111" data-pptx-margin-left-emu="91440"', $blocks);
        $t->contains('data-pptx-border-left-width-emu="25400"', $blocks);
        $t->contains('padding-left:7.2pt', $blocks);
        $t->contains('<td rowspan="2"><p>Tall A</p></td>', $blocks);
        $t->contains('Cell E', $blocks);
        $t->contains('class="pptx-chart"', $blocks);
        $t->contains('data-pptx-chart-legend-position="r"', $blocks);
        $t->contains('data-pptx-chart-axis-titles="Quarter,Revenue"', $blocks);
        $t->contains('<h3>Revenue chart</h3>', $blocks);
        $t->contains('data-pptx-chart-category-index="1">Q2</td>', $blocks);
        $t->contains('data-pptx-chart-point-index="1">18</td>', $blocks);
        $t->contains('Inherited Layout Title', $blocks);
        $t->contains('Inherited Layout Body', $blocks);
        $t->contains('Inherited Master Footer', $blocks);
        $t->contains('class="pptx-notes"', $blocks);
        $t->contains('Speaker note text.', $blocks);
        $t->contains('Second slide body.', $converterBlocks);
    },
    'reads pptx bytes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-bytes-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdOffice" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');
        $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:sldIdLst><p:sldId id="256" r:id="rId1"/></p:sldIdLst></p:presentation>');
        $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/></Relationships>');
        $zip->addFromString('ppt/slides/slide1.xml', '<?xml version="1.0"?><p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><p:cSld><p:spTree><p:sp><p:nvSpPr><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:txBody><a:p><a:r><a:t>Byte PPTX</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld></p:sld>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary PPTX package');
            }
            $document = PandocConverter::read($bytes, 'pptx');
        } finally {
            @unlink($path);
        }

        $t->same('div', $document->children[0]->type);
        $t->same('Byte PPTX', $document->children[0]->children[0]->attr('text'));
    },
];
