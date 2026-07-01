<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PptxReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$buildPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rId2"/>
    <p:sldId id="444" r:id="rId3"/>
    <p:sldId id="459" r:id="rId4"/>
    <p:sldId id="462" r:id="rId5"/>
    <p:sldId id="463" r:id="rId6"/>
  </p:sldIdLst>
  <p:sldSz cx="12192000" cy="6858000"/>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide2.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide3.xml"/>
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide4.xml"/>
  <Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide5.xml"/>
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/tableStyles" Target="tableStyles.xml"/>
</Relationships>
XML);

    $slideOpen = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
XML;
    $slideClose = <<<'XML'
  </p:spTree></p:cSld>
</p:sld>
XML;
    $titleShape = static fn (string $title): string => <<<XML
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{$title}</a:t></a:r></a:p></p:txBody>
    </p:sp>
XML;

    $zip->addFromString('ppt/slides/slide1.xml', $slideOpen . $titleShape('LLMs') . <<<'XML'
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Content Placeholder 2"/><p:cNvSpPr/><p:nvPr><p:ph idx="1"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:t>Provider </a:t></a:r><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>&#61664; Available LLMs &#8211; who manages? How?</a:t></a:r></a:p>
        <a:p><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>EW maintained list of &#8220;approved&#8221; LLMs for Universal workers</a:t></a:r></a:p>
        <a:p><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>Rebuilding of UWs to the &#8220;Newgen&#8221; thing completely</a:t></a:r></a:p>
        <a:p><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>Streaming support</a:t></a:r></a:p>
        <a:p><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>Multimodal (voice streaming) models?</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
XML . $slideClose);

    $zip->addFromString('ppt/slides/slide2.xml', $slideOpen . <<<'XML'
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Everworker</a:t></a:r><a:r><a:t> </a:t></a:r><a:r><a:t>venn</a:t></a:r><a:r><a:t> </a:t></a:r><a:r><a:t>diagram</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="4" name="Oval 3"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:t>SKILLS</a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>Specialized Workers / Workflows:</a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>n8n, UI Path, </a:t></a:r></a:p>
        <a:p><a:r><a:t>other RPA</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="5" name="Oval 4"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:t>BRAINS</a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>Universal Workers / AI Agents:</a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>openai , anthropic,</a:t></a:r></a:p>
        <a:p><a:r><a:t>Crew AI, other </a:t></a:r></a:p>
        <a:p><a:r><a:t>&#8220;AI natives&#8221;</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="6" name="Oval 5"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:t>KNOWLEDGE </a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>Data / </a:t></a:r></a:p>
        <a:p><a:r><a:t>RAG Pipelines</a:t></a:r></a:p>
        <a:p/>
        <a:p><a:r><a:t>Vector DBs, specialized data prep vendors, &#8230;</a:t></a:r></a:p>
        <a:p><a:r><a:t>glean</a:t></a:r></a:p>
        <a:p><a:r><a:t>EW</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="7" name="Inherited body"/><p:cNvSpPr/><p:nvPr><p:ph type="body" idx="7"/></p:nvPr></p:nvSpPr>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="8" name="Inherited footer"/><p:cNvSpPr/><p:nvPr><p:ph type="ftr" idx="8"/></p:nvPr></p:nvSpPr>
    </p:sp>
XML . $slideClose);
    $zip->addFromString('ppt/slides/_rels/slide2.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slideLayouts/slideLayout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sldLayout xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
             xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="1" name="Layout body"/><p:cNvSpPr/><p:nvPr><p:ph type="body" idx="7"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Inherited Layout Body</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sldLayout>
XML);
    $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMaster" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slideMasters/slideMaster1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sldMaster xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
             xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="1" name="Master footer"/><p:cNvSpPr/><p:nvPr><p:ph type="ftr" idx="8"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Inherited Master Footer</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sldMaster>
XML);
    $zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/theme/theme1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">
  <a:themeElements>
    <a:clrScheme name="Office">
      <a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1>
      <a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>
      <a:accent1><a:srgbClr val="4472C4"/></a:accent1>
    </a:clrScheme>
    <a:fontScheme name="Aptos">
      <a:majorFont><a:latin typeface="Aptos Display"/></a:majorFont>
      <a:minorFont><a:latin typeface="Aptos"/></a:minorFont>
    </a:fontScheme>
  </a:themeElements>
</a:theme>
XML);

    $zip->addFromString('ppt/slides/slide3.xml', $slideOpen . $titleShape('Table') . <<<'XML'
    <p:graphicFrame>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl>
        <a:tblPr firstRow="1" bandRow="1"><a:tableStyleId>{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}</a:tableStyleId></a:tblPr>
        <a:tblGrid><a:gridCol w="1828800"/><a:gridCol w="1828800"/><a:gridCol w="1828800"/></a:tblGrid>
        <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Col1</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Col2</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Col3</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
        <a:tr><a:tc gridSpan="2"><a:txBody><a:p><a:r><a:t>Name</a:t></a:r></a:p></a:txBody><a:tcPr anchor="ctr" marL="120"><a:solidFill><a:srgbClr val="D9EAF7"/></a:solidFill><a:lnB w="12700" cap="flat"><a:solidFill><a:schemeClr val="accent1"/></a:solidFill><a:prstDash val="solid"/></a:lnB></a:tcPr></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Anton</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Antich</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
        <a:tr><a:tc rowSpan="2"><a:txBody><a:p><a:r><a:t>Age</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>23</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>years</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
      </a:tbl></a:graphicData></a:graphic>
    </p:graphicFrame>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="8" name="Revenue Chart"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <p:xfrm><a:off x="7000" y="8000"/><a:ext cx="9000" cy="10000"/></p:xfrm>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" r:id="rIdChart"/></a:graphicData></a:graphic>
    </p:graphicFrame>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Picture 6" descr=""/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rId2"/></p:blipFill>
    </p:pic>
XML . $slideClose);
    $zip->addFromString('ppt/slides/_rels/slide3.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/>
  <Relationship Id="rIdChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart1.xml"/>
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/image1.png', 'fake-png-bytes');
    $zip->addFromString('ppt/tableStyles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<a:tblStyleLst xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" def="{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}">
  <a:tblStyle styleId="{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}" styleName="Medium Style 2 - Accent 1">
    <a:wholeTbl>
      <a:tcTxStyle b="1"><a:fontRef idx="minor"><a:schemeClr val="tx1"/></a:fontRef></a:tcTxStyle>
      <a:tcStyle>
        <a:fill><a:solidFill><a:schemeClr val="accent2"/></a:solidFill></a:fill>
        <a:lnB w="12700"><a:solidFill><a:schemeClr val="accent1"/></a:solidFill><a:prstDash val="solid"/></a:lnB>
      </a:tcStyle>
    </a:wholeTbl>
    <a:firstRow><a:tcTxStyle b="1"/></a:firstRow>
  </a:tblStyle>
</a:tblStyleLst>
XML);
    $zip->addFromString('ppt/charts/chart1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"
              xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
              xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <c:chart>
    <c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Quarterly Revenue</a:t></a:r></a:p></c:rich></c:tx></c:title>
    <c:plotArea>
      <c:barChart>
        <c:barDir val="col"/>
        <c:ser>
          <c:idx val="0"/><c:order val="0"/>
          <c:tx><c:strRef><c:strCache><c:pt idx="0"><c:v>North</c:v></c:pt></c:strCache></c:strRef></c:tx>
          <c:cat><c:strRef><c:strCache><c:pt idx="0"><c:v>Q1</c:v></c:pt><c:pt idx="1"><c:v>Q2</c:v></c:pt></c:strCache></c:strRef></c:cat>
          <c:val><c:numRef><c:numCache><c:pt idx="0"><c:v>12</c:v></c:pt><c:pt idx="1"><c:v>18</c:v></c:pt></c:numCache></c:numRef></c:val>
        </c:ser>
        <c:axId val="10"/><c:axId val="20"/>
      </c:barChart>
      <c:lineChart>
        <c:grouping val="standard"/>
        <c:ser>
          <c:idx val="1"/><c:order val="1"/>
          <c:tx><c:strRef><c:strCache><c:pt idx="0"><c:v>South</c:v></c:pt></c:strCache></c:strRef></c:tx>
          <c:cat><c:strRef><c:strCache><c:pt idx="0"><c:v>Q1</c:v></c:pt><c:pt idx="1"><c:v>Q2</c:v></c:pt></c:strCache></c:strRef></c:cat>
          <c:val><c:numRef><c:numCache><c:pt idx="0"><c:v>9</c:v></c:pt><c:pt idx="1"><c:v>13</c:v></c:pt></c:numCache></c:numRef></c:val>
        </c:ser>
        <c:axId val="10"/><c:axId val="20"/>
      </c:lineChart>
      <c:catAx>
        <c:axId val="10"/><c:axPos val="b"/>
        <c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Quarter</a:t></a:r></a:p></c:rich></c:tx></c:title>
        <c:crossAx val="20"/>
      </c:catAx>
      <c:valAx>
        <c:axId val="20"/><c:axPos val="l"/>
        <c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Revenue</a:t></a:r></a:p></c:rich></c:tx></c:title>
        <c:numFmt formatCode="$#,##0" sourceLinked="0"/>
        <c:crossAx val="10"/>
      </c:valAx>
    </c:plotArea>
  </c:chart>
  <c:externalData r:id="rIdWorkbook"/>
</c:chartSpace>
XML);
    $zip->addFromString('ppt/charts/_rels/chart1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="../embeddings/Microsoft_Excel_Worksheet1.xlsx"/>
</Relationships>
XML);

    $zip->addFromString('ppt/slides/slide4.xml', $slideOpen . $titleShape('Smart Art') . <<<'XML'
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="SmartArt Placeholder"/><p:cNvGraphicFramePr/><p:nvPr><p:ph type="body" idx="9"/></p:nvPr></p:nvGraphicFramePr>
      <p:xfrm><a:off x="1000" y="2000"/><a:ext cx="3000" cy="4000"/></p:xfrm>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" r:dm="rId2" r:lo="rId3"/></a:graphicData></a:graphic>
    </p:graphicFrame>
XML . $slideClose);
    $zip->addFromString('ppt/slides/_rels/slide4.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="../diagrams/data1.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="../diagrams/layout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/diagrams/layout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/chevron2"><dgm:title val=""/></dgm:layoutDef>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="0" type="doc"><dgm:t><a:p/></dgm:t></dgm:pt>
    <dgm:pt modelId="1"><dgm:t><a:p><a:r><a:t>First</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="11"><dgm:t><a:p><a:r><a:t>another</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="12"><dgm:t><a:p><a:r><a:t>subtitle</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="2"><dgm:t><a:p><a:r><a:t>Second</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="21"><dgm:t><a:p><a:r><a:t>and yet again</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="22"><dgm:t><a:p><a:r><a:t>yet more</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="0" destId="1"/>
    <dgm:cxn srcId="1" destId="11"/>
    <dgm:cxn srcId="1" destId="12"/>
    <dgm:cxn srcId="0" destId="2"/>
    <dgm:cxn srcId="2" destId="21"/>
    <dgm:cxn srcId="2" destId="22"/>
  </dgm:cxnLst>
</dgm:dataModel>
XML);

    $zip->addFromString('ppt/slides/slide5.xml', $slideOpen . $titleShape('Review Media') . <<<'XML'
    <p:sp>
      <p:nvSpPr><p:cNvPr id="30" name="Back layer text"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:spPr><a:xfrm><a:off x="111" y="222"/><a:ext cx="333" cy="444"/></a:xfrm></p:spPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Back layer</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr>
        <p:cNvPr id="31" name="Video Placeholder" descr="Training clip"/>
        <p:cNvPicPr/>
        <p:nvPr><a:videoFile r:link="rIdVideo"/></p:nvPr>
      </p:nvPicPr>
      <p:spPr><a:xfrm><a:off x="555" y="666"/><a:ext cx="777" cy="888"/></a:xfrm></p:spPr>
    </p:pic>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="32" name="Front layer text"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:spPr><a:xfrm><a:off x="999" y="1000"/><a:ext cx="1001" cy="1002"/></a:xfrm></p:spPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Front layer</a:t></a:r></a:p></p:txBody>
    </p:sp>
XML . $slideClose);
    $zip->addFromString('ppt/slides/_rels/slide5.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="../comments/comment1.xml"/>
  <Relationship Id="rIdVideo" Type="http://schemas.microsoft.com/office/2007/relationships/media" Target="../media/video1.mp4"/>
</Relationships>
XML);
    $zip->addFromString('ppt/commentAuthors.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:cmAuthorLst xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:cmAuthor id="0" name="Ada Reviewer" initials="AR" lastIdx="1"/>
</p:cmAuthorLst>
XML);
    $zip->addFromString('ppt/comments/comment1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:cmLst xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:cm authorId="0" dt="2026-06-26T12:00:00Z" idx="1"><p:pos x="12" y="34"/><p:text>Review this clip</p:text></p:cm>
</p:cmLst>
XML);
    $zip->addFromString('ppt/media/video1.mp4', 'fake-video-bytes');
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildExternalTableStylesPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-ext-table-styles-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/tableStyles" Target="javascript:alert(1)" TargetMode="External"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>External table styles</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildEmptyTablePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-table-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Empty table</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="8" name="Empty Table"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl><a:tblPr/><a:tblGrid/></a:tbl></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildEmptyTextPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-text-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Empty text</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Empty Text Box"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p/></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="4" name="Empty Cell Table"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl>
        <a:tblPr/>
        <a:tr><a:tc><a:txBody><a:p/></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Filled</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
      </a:tbl></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildInheritedTitlePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-inherited-title-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body 1"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Visible body</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slideLayouts/slideLayout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sldLayout xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
             xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="1" name="Layout Title"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Inherited Layout Title</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sldLayout>
XML);
    $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMaster" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slideMasters/slideMaster1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sldMaster xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
             xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="1" name="Master Title"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Inherited Master Title</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sldMaster>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildMissingImagePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-missing-image-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Missing image</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Missing Picture" descr="Alt that should not leak"/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/missing.png"/>
</Relationships>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildMediaRelativeImagePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-media-relative-image-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Media-relative image</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Relative Picture" descr="Relative alt"/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/relative.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/relative.png', 'relative-image-bytes');
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildTitlePlaceholderPicturePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-title-placeholder-picture-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Picture placeholder title</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Title Placeholder Picture" descr="Title placeholder alt"/><p:cNvPicPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/title-placeholder.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/title-placeholder.png', 'title-placeholder-image-bytes');
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildRootTargetImagePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-root-target-image-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root target image</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Root Picture" descr="Root alt"/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="assets/root.png"/>
</Relationships>
XML);
    $zip->addFromString('assets/root.png', 'root-image-bytes');
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildPictureWithoutNonVisualPropertiesPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-picture-no-nvpr-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Malformed picture</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/picture.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/picture.png', 'fake-picture-bytes');
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildExternalLinkedImagePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-linked-image-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Linked image</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Linked Picture" descr="External alt"/></p:nvPicPr>
      <p:blipFill><a:blip r:link="rIdLinkedImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdLinkedImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://example.test/linked.png" TargetMode="External"/>
</Relationships>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildInternalLinkedImagePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-internal-linked-image-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Internal linked image</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Internal Linked Picture" descr="Internal linked alt"/></p:nvPicPr>
      <p:blipFill><a:blip r:link="rIdLinkedImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdLinkedImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/internal-linked.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/internal-linked.png', 'fake-internal-linked-png');
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildHyperlinkedPicturePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-picture-link-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Picture link</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Linked Picture" descr="Picture alt"><a:hlinkClick r:id="rIdPictureLink" tooltip="Open figure"/></p:cNvPr></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/picture.png"/>
  <Relationship Id="rIdPictureLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/picture" TargetMode="External"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/picture.png', 'fake-picture-bytes');
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildHyperlinkedTextBoxPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-textbox-link-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Text box link</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Linked Text Box"><a:hlinkClick r:id="rIdTextBoxLink" tooltip="Open text box"/></p:cNvPr><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Open the text box</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdTextBoxLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/text-box" TargetMode="External"/>
</Relationships>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildGroupedShapesPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-grouped-shapes-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Grouped slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:grpSp>
      <p:nvGrpSpPr><p:cNvPr id="10" name="Group 1"/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
      <p:grpSpPr><a:xfrm><a:off x="100" y="200"/><a:ext cx="300" cy="400"/></a:xfrm></p:grpSpPr>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="11" name="Grouped Text"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Grouped body</a:t></a:r></a:p></p:txBody>
      </p:sp>
      <p:pic>
        <p:nvPicPr><p:cNvPr id="12" name="Grouped Picture" descr="Grouped alt"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>
        <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
      </p:pic>
      <p:grpSp>
        <p:nvGrpSpPr><p:cNvPr id="13" name="Nested Group"/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
        <p:grpSpPr/>
        <p:sp>
          <p:nvSpPr><p:cNvPr id="14" name="Nested Text"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
          <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Nested grouped body</a:t></a:r></a:p></p:txBody>
        </p:sp>
      </p:grpSp>
    </p:grpSp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/grouped.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/grouped.png', 'fake-grouped-png');
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildUnsupportedConnectorPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-connector-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Connector diagnostics</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:cxnSp>
      <p:nvCxnSpPr><p:cNvPr id="9" name="Connector 8" descr="Connector desc"/><p:cNvCxnSpPr/><p:nvPr/></p:nvCxnSpPr>
      <p:spPr><a:xfrm><a:off x="111" y="222"/><a:ext cx="333" cy="444"/></a:xfrm></p:spPr>
      <p:style/>
    </p:cxnSp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildHyperlinkedTextPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-link-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Hyperlink slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Linked body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p>
          <a:r><a:t>Read </a:t></a:r>
          <a:r><a:rPr><a:hlinkClick r:id="rIdLink" tooltip="Spec link"/></a:rPr><a:t>the spec</a:t></a:r>
          <a:r><a:t> now</a:t></a:r>
        </a:p>
      </p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/spec?x=1" TargetMode="External"/>
</Relationships>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildBreakTabTextPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-break-tab-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Breaks slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Break tab body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p>
          <a:r><a:t>Line one</a:t></a:r>
          <a:br/>
          <a:r><a:t>Line two</a:t></a:r>
          <a:tab/>
          <a:r><a:t>Tabbed</a:t></a:r>
        </a:p>
      </p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildNumberedListPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-numbered-list-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Numbered slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Numbered body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr><a:buAutoNum type="arabicPeriod" startAt="3"/></a:pPr><a:r><a:t>Third item</a:t></a:r></a:p>
        <a:p><a:pPr><a:buAutoNum type="arabicPeriod"/></a:pPr><a:r><a:t>Fourth item</a:t></a:r></a:p>
        <a:p><a:pPr><a:buAutoNum type="alphaUcParenR"/></a:pPr><a:r><a:t>Alpha item</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildEndParagraphSymbolPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-end-para-symbol-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Symbol slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="End paragraph symbol body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p>
          <a:r><a:t>Not a Wingdings bullet</a:t></a:r>
          <a:endParaRPr><a:sym typeface="Wingdings"/></a:endParaRPr>
        </a:p>
      </p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildBuNoneWingdingsSymbolPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-bunone-wingdings-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>BuNone symbol slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="BuNone symbol body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr><a:buNone/></a:pPr><a:r><a:rPr><a:sym typeface="Wingdings"/></a:rPr><a:t>Wingdings still wins</a:t></a:r></a:p>
        <a:p><a:pPr><a:buNone/></a:pPr><a:r><a:t>Plain buNone stays plain</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildNestedListPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-nested-list-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Nested list slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Nested list body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr lvl="0"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Parent bullet</a:t></a:r></a:p>
        <a:p><a:pPr lvl="1"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Child bullet</a:t></a:r></a:p>
        <a:p><a:pPr lvl="1"><a:buAutoNum type="arabicPeriod" startAt="2"/></a:pPr><a:r><a:t>Numbered child</a:t></a:r></a:p>
        <a:p><a:pPr lvl="0"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Second parent</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildListContinuationPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-list-continuation-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Continuation slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Continuation body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr lvl="0"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Top-level</a:t></a:r></a:p>
        <a:p><a:pPr lvl="1" indent="0" marL="342900"><a:buNone/></a:pPr><a:r><a:t>With continuation</a:t></a:r></a:p>
        <a:p><a:pPr lvl="1"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Nested bullet</a:t></a:r></a:p>
        <a:p><a:pPr lvl="2" indent="0" marL="685800"><a:buNone/></a:pPr><a:r><a:t>Nested continuation</a:t></a:r></a:p>
        <a:p><a:pPr lvl="0"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Second top-level</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildSpeakerNotesPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-speaker-notes-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Notes slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Visible body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Visible slide body</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdNotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide" Target="../notesSlides/notesSlide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/notesSlides/notesSlide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:notes xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
         xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
         xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Slide Image Placeholder 1"/><p:cNvSpPr/><p:nvPr><p:ph type="sldImg"/></p:nvPr></p:nvSpPr>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Notes Placeholder 2"/><p:cNvSpPr/><p:nvPr><p:ph type="body" idx="1"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:t>Remember the launch date.</a:t></a:r></a:p>
        <a:p><a:r><a:t>Ask about migration risks.</a:t></a:r></a:p>
      </p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="4" name="Slide Number Placeholder 3"/><p:cNvSpPr/><p:nvPr><p:ph type="sldNum" idx="2"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>1</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:notes>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildBrokenSmartArtPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-broken-smartart-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Broken SmartArt</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Broken SmartArt Frame"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds r:dm="rIdData" r:lo="rIdLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="../diagrams/missing-data.xml"/>
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="../diagrams/layout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/diagrams/layout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/basicBlockList"/>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildWrongNamespaceSmartArtPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-wrong-ns-smartart-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Wrong namespace SmartArt</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Wrong Namespace SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds r:dm="rIdData" r:lo="rIdLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="../diagrams/data1.xml"/>
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="../diagrams/layout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/diagrams/layout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/basicBlockList"/>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:bad="urn:not-diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <bad:ptLst>
    <bad:pt modelId="1"><bad:t><a:p><a:r><a:t>Wrong namespace node</a:t></a:r></a:p></bad:t></bad:pt>
  </bad:ptLst>
</dgm:dataModel>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildGraphicPlaceholderPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-graphic-placeholders-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Graphic placeholders</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="10" name="No URI Graphic"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData/></a:graphic>
    </p:graphicFrame>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="11" name="Diagram No RelIds"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"/></a:graphic>
    </p:graphicFrame>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="12" name="Diagram Missing Rels"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds/></a:graphicData></a:graphic>
    </p:graphicFrame>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="13" name="Diagram Unknown Rel"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds r:dm="rIdMissingData" r:lo="rIdMissingLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildWrongNamespaceShapePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-wrong-ns-shape-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:bad="urn:not-presentationml">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Namespace slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <bad:sp>
      <bad:nvSpPr><bad:cNvPr id="3" name="Bad Body"/><bad:cNvSpPr/><bad:nvPr/></bad:nvSpPr>
      <bad:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Wrong namespace body</a:t></a:r></a:p></bad:txBody>
    </bad:sp>
    <sp>
      <nvSpPr><cNvPr id="4" name="Unqualified Body"/><cNvSpPr/><nvPr/></nvSpPr>
      <txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Unqualified namespace body</a:t></a:r></a:p></txBody>
    </sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildWrongNamespacePresentationSlidesPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-wrong-ns-presentation-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
                xmlns:bad="urn:not-presentationml">
  <bad:sldIdLst>
    <bad:sldId r:id="rIdSlide"/>
  </bad:sldIdLst>
  <sldIdLst>
    <sldId r:id="rIdSlide"/>
  </sldIdLst>
  <bad:sldSz cx="12192000" cy="6858000"/>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Wrong namespace presentation body</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildUnqualifiedPresentationRelationshipPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-unqualified-presentation-rid-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:sldIdLst>
    <p:sldId id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Unqualified relationship body</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildWrongNamespaceTablePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-wrong-ns-table-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:bad="urn:not-drawingml">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Table namespaces</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="8" name="Mixed Namespace Table"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl>
        <a:tblGrid><a:gridCol w="1828800"/><bad:gridCol w="999999"/></a:tblGrid>
        <a:tr>
          <a:tc><a:txBody><a:p><a:r><a:t>Visible header</a:t></a:r></a:p></a:txBody></a:tc>
          <bad:tc><a:txBody><a:p><a:r><a:t>Wrong namespace cell</a:t></a:r></a:p></a:txBody></bad:tc>
        </a:tr>
        <bad:tr>
          <a:tc><a:txBody><a:p><a:r><a:t>Wrong namespace row</a:t></a:r></a:p></a:txBody></a:tc>
        </bad:tr>
        <a:tr>
          <a:tc><bad:txBody><a:p><a:r><a:t>Wrong namespace text body</a:t></a:r></a:p></bad:txBody></a:tc>
        </a:tr>
      </a:tbl></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildFirstOfficeDocumentRelationshipPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-first-office-doc-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdFirst" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/not-presentation.xml"/>
  <Relationship Id="rIdSecond" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/not-presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdMissing"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Second relationship body</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildLiteralPresentationRelationshipPartPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-literal-presentation-rels-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation deck.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation deck.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation deck.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Literal rels path</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildDotSegmentPresentationTargetPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-dot-presentation-target-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="./ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Normalized presentation target</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildRootRelativeSlideTargetPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-root-relative-slide-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="/ppt/slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root relative target body</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildUntypedRelationshipsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-untyped-rels-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdUntypedRoot" Target="ppt/ignored.xml"/>
  <Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdUntypedSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdUntypedSlide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Untyped relationships</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildInvalidRelationshipIdPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-invalid-rid-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary PPTX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary PPTX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="1bad"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="1bad" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Invalid relationship id</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary PPTX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$nodesOfType = static function (AstNode $node, string $type) use (&$nodesOfType): array {
    $nodes = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($nodes, ...$nodesOfType($child, $type));
    }

    return $nodes;
};

$nodesWithClass = static function (array $nodes, string $class): array {
    return array_values(array_filter($nodes, static function (AstNode $node) use ($class): bool {
        $classes = $node->attr('classes', []);

        return is_array($classes) && in_array($class, $classes, true);
    }));
};

$pandocReaderContentSignature = static function (AstNode $node) use (&$pandocReaderContentSignature): array {
    $childSignatures = static function (array $children) use (&$pandocReaderContentSignature): array {
        $signatures = [];
        foreach ($children as $child) {
            $signature = $pandocReaderContentSignature($child);
            if (($signature['type'] ?? null) === 'text' && ($signature['text'] ?? '') === '') {
                continue;
            }
            if (($signature['type'] ?? null) === 'table_foot' && ($signature['children'] ?? []) === []) {
                continue;
            }
            $signatures[] = $signature;
        }

        return $signatures;
    };

    return match ($node->type) {
        'document' => [
            'type' => 'document',
            'children' => $childSignatures($node->children),
        ],
        'heading' => [
            'type' => 'heading',
            'level' => (int) $node->attr('level'),
            'id' => (string) $node->attr('id'),
            'inlines' => $childSignatures($node->children),
        ],
        'paragraph', 'plain' => [
            'type' => $node->type,
            'inlines' => $childSignatures($node->children),
        ],
        'text' => [
            'type' => 'text',
            'text' => (string) $node->attr('text'),
        ],
        'space' => [
            'type' => 'space',
        ],
        'softbreak', 'linebreak' => [
            'type' => $node->type,
        ],
        'strong' => [
            'type' => 'strong',
            'inlines' => $childSignatures($node->children),
        ],
        'image' => [
            'type' => 'image',
            'url' => (string) $node->attr('url', $node->attr('src', '')),
            'title' => (string) $node->attr('title', ''),
            'alt' => (string) $node->attr('alt', ''),
        ],
        'div' => [
            'type' => 'div',
            'classes' => $node->attr('classes', []),
            'attributes' => $node->attr('attributes', []),
            'children' => $childSignatures($node->children),
        ],
        'table' => [
            'type' => 'table',
            'children' => $childSignatures($node->children),
        ],
        'bullet_list',
        'list_item',
        'table_head',
        'table_body',
        'table_foot',
        'table_row',
        'table_cell' => [
            'type' => $node->type,
            'children' => $childSignatures($node->children),
        ],
        default => [
            'type' => $node->type,
            'children' => $childSignatures($node->children),
        ],
    };
};

return [
    'matches pinned upstream pptx reader basic fixture semantics' => static function (TestRunner $t) use ($buildPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');
        $blocks = (new WordPressBlockWriter())->write($document);
        $tables = $nodesOfType($document, 'table');
        $images = $nodesOfType($document, 'image');
        $divs = $nodesOfType($document, 'div');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $chartParagraphs = array_values(array_filter(
            $paragraphs,
            static fn (AstNode $node): bool => is_array($node->attr('pptxChart'))
        ));
        $mediaDivs = $nodesWithClass($divs, 'pptx-rich-media');
        $commentDivs = $nodesWithClass($divs, 'pptx-comments');
        $backLayerParagraphs = array_values(array_filter($paragraphs, static fn (AstNode $node): bool => $node->attr('text') === 'Back layer'));
        $frontLayerParagraphs = array_values(array_filter($paragraphs, static fn (AstNode $node): bool => $node->attr('text') === 'Front layer'));

        $t->same('pptx', $document->attr('sourceFormat'));
        $t->same([], $document->attr('meta'));
        $t->same(1, $review['upstreamEvidence']['denominator'] ?? null);
        $t->same(['test/pptx-reader/basic.pptx', 'test/pptx-reader/basic.native'], $review['upstreamEvidence']['fixtures'] ?? null);
        $t->same(5, $review['slideCount'] ?? null);
        $t->same([
            'cx' => 12192000,
            'cy' => 6858000,
            'width' => 13,
            'height' => 7,
            'emusPerInch' => 914400,
            'source' => 'presentation',
        ], $review['slideSize'] ?? null);
        $t->same('Ada Reviewer', $review['commentAuthors']['0']['name'] ?? null);
        $t->same(1, $review['slides'][4]['commentCount'] ?? null);
        $t->same('Review this clip', $review['slides'][4]['comments'][0]['text'] ?? null);
        $t->same(1, $review['slides'][4]['richMediaCount'] ?? null);
        $t->same('ppt/media/video1.mp4', $review['slides'][4]['richMedia'][0]['partName'] ?? null);
        $t->same('ppt/slideLayouts/slideLayout1.xml', $review['slides'][1]['context']['layoutPart'] ?? null);
        $t->same('ppt/slideMasters/slideMaster1.xml', $review['slides'][1]['context']['masterPart'] ?? null);
        $t->same('ppt/theme/theme1.xml', $review['slides'][1]['context']['themePart'] ?? null);
        $t->same('Office Theme', $review['slides'][1]['context']['theme']['name'] ?? null);
        $t->same('4472C4', $review['slides'][1]['context']['theme']['colorScheme']['colors']['accent1'] ?? null);
        $t->same('Aptos', $review['slides'][1]['context']['theme']['fontScheme']['minorLatin'] ?? null);
        $t->same(1, $review['slides'][2]['chartCount'] ?? null);
        $t->same('http://schemas.openxmlformats.org/drawingml/2006/chart', $review['slides'][2]['charts'][0]['graphicUri'] ?? null);
        $t->same('ppt/charts/chart1.xml', $review['slides'][2]['charts'][0]['partName'] ?? null);
        $t->same('ppt/tableStyles.xml', $review['tableStyles']['partName'] ?? null);
        $t->same('rIdStyles', $review['tableStyles']['relationshipId'] ?? null);
        $t->same('{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}', $review['tableStyles']['defaultStyleId'] ?? null);
        $t->same(1, $review['tableStyles']['styleCount'] ?? null);
        $t->same('Medium Style 2 - Accent 1', $review['tableStyles']['styles']['{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}']['name'] ?? null);
        $t->same(true, $review['tableStyles']['styles']['{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}']['parts']['wholeTbl']['text']['bold'] ?? null);
        $t->same('minor', $review['tableStyles']['styles']['{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}']['parts']['wholeTbl']['text']['fontRef'] ?? null);
        $t->same('theme:accent2', $review['tableStyles']['styles']['{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}']['parts']['wholeTbl']['cell']['fillColor'] ?? null);
        $t->same(12700, $review['tableStyles']['styles']['{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}']['parts']['wholeTbl']['cell']['borderStyles']['bottom']['width'] ?? null);

        $t->same('heading', $document->children[0]->type);
        $t->same('slide-1', $document->children[0]->attr('id'));
        $t->same('LLMs', $document->children[0]->attr('text'));
        $t->same('bullet_list', $document->children[1]->type);
        $t->same(5, count($document->children[1]->children));
        $t->contains('Provider', $document->children[1]->children[0]->children[0]->children[0]->attr('text'));
        $t->contains('Available LLMs', $document->children[1]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('slide-2', $document->children[2]->attr('id'));
        $t->same('Everworker   venn   diagram', $document->children[2]->attr('text'));
        $t->same('SKILLS', $document->children[3]->attr('text'));
        $t->same('', $document->children[4]->attr('text'));
        $t->same('', $document->children[4]->children[0]->attr('text'));

        $t->same(1, count($tables));
        $t->same([
            'firstRow' => true,
            'bandRow' => true,
            'id' => '{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}',
            'name' => 'Medium Style 2 - Accent 1',
            'sourcePart' => 'ppt/tableStyles.xml',
            'relationshipId' => 'rIdStyles',
            'parts' => [
                'wholeTbl' => [
                    'text' => [
                        'bold' => true,
                        'fontRef' => 'minor',
                        'fontRefColor' => 'theme:tx1',
                        'textColor' => 'theme:tx1',
                    ],
                    'cell' => [
                        'fillColor' => 'theme:accent2',
                        'borders' => [
                            'bottom' => 'theme:accent1',
                        ],
                        'borderStyles' => [
                            'bottom' => [
                                'color' => 'theme:accent1',
                                'width' => 12700,
                                'dash' => 'solid',
                            ],
                        ],
                    ],
                ],
                'firstRow' => [
                    'text' => [
                        'bold' => true,
                    ],
                ],
            ],
            'default' => true,
        ], $tables[0]->attr('pptxTableStyle'));
        $t->same([1828800, 1828800, 1828800], $tables[0]->attr('columnWidths'));
        $t->same(['Col1', 'Col2', 'Col3'], array_map(static fn (AstNode $cell): string => (string) $cell->attr('text'), $tables[0]->children[0]->children[0]->children));
        $t->same('Name', $tables[0]->children[1]->children[0]->children[0]->attr('text'));
        $t->same(1, $tables[0]->children[1]->children[0]->children[0]->attr('colspan', 1));
        $t->same(2, $tables[0]->children[1]->children[0]->children[0]->attr('pptxCell')['gridSpan'] ?? null);
        $t->same('D9EAF7', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['fillColor'] ?? null);
        $t->same('ctr', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['verticalAlign'] ?? null);
        $t->same('theme:accent1', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['borders']['bottom'] ?? null);
        $t->same('4472C4', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['resolvedBorders']['bottom'] ?? null);
        $t->same(12700, $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['borderStyles']['bottom']['width'] ?? null);
        $t->same('solid', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['borderStyles']['bottom']['dash'] ?? null);
        $t->same('4472C4', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['borderStyles']['bottom']['resolvedColor'] ?? null);
        $t->same(1, $tables[0]->children[1]->children[1]->children[0]->attr('rowspan', 1));
        $t->same(2, $tables[0]->children[1]->children[1]->children[0]->attr('pptxCell')['rowSpan'] ?? null);
        $t->same('23', $tables[0]->children[1]->children[1]->children[1]->attr('text'));
        $t->same(1, count($images));
        $t->same('ppt/media/image1.png', $images[0]->attr('url'));
        $t->same('Picture 6', $images[0]->attr('title'));

        $t->same(1, count($chartParagraphs));
        $t->same('[Graphic: other: http://schemas.openxmlformats.org/drawingml/2006/chart]', $chartParagraphs[0]->attr('text'));
        $t->same('http://schemas.openxmlformats.org/drawingml/2006/chart', $chartParagraphs[0]->attr('pptxChart')['graphicUri'] ?? null);
        $t->same('ppt/charts/chart1.xml', $chartParagraphs[0]->attr('pptxChart')['partName'] ?? null);
        $t->same('Quarterly Revenue', $chartParagraphs[0]->attr('pptxChart')['title'] ?? null);
        $t->same('bar', $chartParagraphs[0]->attr('pptxChart')['chartType'] ?? null);
        $t->same(['bar', 'line'], $chartParagraphs[0]->attr('pptxChart')['chartTypes'] ?? null);
        $t->same(2, $chartParagraphs[0]->attr('pptxChart')['chartTypeCount'] ?? null);
        $t->same('col', $chartParagraphs[0]->attr('pptxChart')['plots'][0]['barDirection'] ?? null);
        $t->same(['10', '20'], $chartParagraphs[0]->attr('pptxChart')['plots'][0]['axisIds'] ?? null);
        $t->same('line', $chartParagraphs[0]->attr('pptxChart')['plots'][1]['type'] ?? null);
        $t->same('standard', $chartParagraphs[0]->attr('pptxChart')['plots'][1]['grouping'] ?? null);
        $t->same(['Q1', 'Q2'], $chartParagraphs[0]->attr('pptxChart')['series'][0]['categories'] ?? null);
        $t->same(['12', '18'], $chartParagraphs[0]->attr('pptxChart')['series'][0]['values'] ?? null);
        $t->same('line', $chartParagraphs[0]->attr('pptxChart')['series'][1]['plotType'] ?? null);
        $t->same(['9', '13'], $chartParagraphs[0]->attr('pptxChart')['series'][1]['values'] ?? null);
        $t->same('Quarter', $chartParagraphs[0]->attr('pptxChart')['axes'][0]['title'] ?? null);
        $t->same('Revenue', $chartParagraphs[0]->attr('pptxChart')['axes'][1]['title'] ?? null);
        $t->same('$#,##0', $chartParagraphs[0]->attr('pptxChart')['axes'][1]['numberFormat'] ?? null);
        $t->same(false, $chartParagraphs[0]->attr('pptxChart')['axes'][1]['sourceLinked'] ?? null);
        $t->same(['rIdWorkbook'], $chartParagraphs[0]->attr('pptxChart')['externalDataRelationshipIds'] ?? null);
        $t->same('ppt/embeddings/Microsoft_Excel_Worksheet1.xlsx', $chartParagraphs[0]->attr('pptxChart')['externalDataRelationships'][0]['partName'] ?? null);

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'chevron2'], $smartArtDivs[0]->attr('classes'));
        $t->same(['layout' => 'chevron2'], $smartArtDivs[0]->attr('attributes'));
        $t->same('graphicFrame', $smartArtDivs[0]->attr('pptxShape')['element'] ?? null);
        $t->same('body', $smartArtDivs[0]->attr('pptxShape')['placeholderType'] ?? null);
        $t->same(['x' => 1000, 'y' => 2000, 'cx' => 3000, 'cy' => 4000], $smartArtDivs[0]->attr('pptxShape')['layout'] ?? null);
        $t->same('strong', $smartArtDivs[0]->children[0]->children[0]->type);
        $t->same('First', $smartArtDivs[0]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('another', $smartArtDivs[0]->children[1]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Second', $smartArtDivs[0]->children[2]->children[0]->children[0]->attr('text'));

        $t->same(0, count($mediaDivs));
        $t->same('video', $review['slides'][4]['richMedia'][0]['kind'] ?? null);
        $t->same('pic', $review['slides'][4]['richMedia'][0]['shape']['element'] ?? null);
        $t->same(3, $review['slides'][4]['richMedia'][0]['shape']['zOrder'] ?? null);
        $t->same(['x' => 555, 'y' => 666, 'cx' => 777, 'cy' => 888], $review['slides'][4]['richMedia'][0]['shape']['layout'] ?? null);

        $t->same(0, count($commentDivs));
        $t->true(!str_contains($native, 'Review this clip'), 'PPTX comments should remain out of upstream-compatible visible output');
        $t->same(1, count($backLayerParagraphs));
        $t->same(2, $backLayerParagraphs[0]->attr('pptxShape')['zOrder'] ?? null);
        $t->same(['x' => 111, 'y' => 222, 'cx' => 333, 'cy' => 444], $backLayerParagraphs[0]->attr('pptxShape')['layout'] ?? null);
        $t->same(1, count($frontLayerParagraphs));
        $t->same(4, $frontLayerParagraphs[0]->attr('pptxShape')['zOrder'] ?? null);

        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "LLMs" ]', $native);
        $t->contains('BulletList', $native);
        $t->contains('Table ( "" , [  ] , [  ] )', $native);
        $t->true(!str_contains($native, '(ColSpan 2)'), 'PPTX gridSpan should remain review-only in upstream-compatible native output');
        $t->true(!str_contains($native, '(RowSpan 2)'), 'PPTX rowSpan should remain review-only in upstream-compatible native output');
        $t->contains('Image ( "" , [  ] , [  ] ) [  ] ( "ppt/media/image1.png" , "Picture 6" )', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "other:" , Space , Str "http://schemas.openxmlformats.org/drawingml/2006/chart]" ]', $native);
        $t->contains('Div ( "" , [ "smartart" , "chevron2" ] , [ ( "layout" , "chevron2" ) ] )', $native);
        $t->true(!str_contains($native, 'pptx-rich-media'), 'PPTX rich media should remain out of upstream-compatible native output');
        $t->true(!str_contains($native, 'video1.mp4'), 'PPTX rich media targets should remain review-only');
        $t->contains('<!-- wp:heading {"level":2} -->', $blocks);
        $t->contains('<th>Col1</th>', $blocks);
        $t->contains('[Graphic: other: http://schemas.openxmlformats.org/drawingml/2006/chart]', $blocks);
        $t->contains('ppt/media/image1.png', $blocks);
        $t->true(!str_contains($blocks, 'colspan="2"'), 'PPTX gridSpan should remain review-only in WordPress output');
        $t->true(!str_contains($blocks, 'rowspan="2"'), 'PPTX rowSpan should remain review-only in WordPress output');
        $t->true(!str_contains($blocks, 'data-pandoc-comment-author="Ada Reviewer"'), 'PPTX comments should not render into visible WordPress comment markup');
        $t->true(!str_contains($blocks, 'Inherited Layout Body'), 'Inherited layout placeholders should remain out of upstream-compatible visible output');
        $t->true(!str_contains($blocks, 'Inherited Master Footer'), 'Inherited master placeholders should remain out of upstream-compatible visible output');
    },

    'matches checked-in current upstream pptx reader basic golden content' => static function (TestRunner $t) use ($pandocReaderContentSignature): void {
        $fixtureRoot = dirname(__DIR__) . '/fixtures/upstream-current-pptx-reader';
        $pptxPath = $fixtureRoot . '/basic.pptx';
        $nativePath = $fixtureRoot . '/basic.native';

        $t->same('e48fd9c2f8369d1792197e301d5fea676bf6e51097a24af7d85831a6f96dc2dc', hash_file('sha256', $pptxPath));
        $t->same('42804b9b1954094a4b0ff0be20084e2e6d9bc0a84272f34f7f219f82505da6b4', hash_file('sha256', $nativePath));

        $pptxBytes = file_get_contents($pptxPath);
        $native = file_get_contents($nativePath);
        if (!is_string($pptxBytes) || !is_string($native)) {
            throw new RuntimeException('Unable to read checked-in upstream PPTX reader fixtures');
        }

        $expected = (new NativeReader())->read($native);
        $actual = (new PptxReader())->read($pptxBytes);
        $review = $actual->attr('pptx');

        $t->same(1, $review['upstreamEvidence']['denominator'] ?? null);
        $t->same(1, $review['upstreamEvidence']['covered'] ?? null);
        $t->same('4f5226df4faa0d66dd2c089465b13886360ab3c2', $review['upstreamEvidence']['fixtureCommit'] ?? null);
        $t->same(['test/pptx-reader/basic.pptx', 'test/pptx-reader/basic.native'], $review['upstreamEvidence']['fixtures'] ?? null);
        $t->same(4, $review['slideCount'] ?? null);
        $t->same(49, $review['entryCount'] ?? null);
        $t->same($pandocReaderContentSignature($expected), $pandocReaderContentSignature($actual));
    },

    'resolves pptx table style relationship provenance' => static function (TestRunner $t) use ($buildPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildPptxPackage());
        $review = $document->attr('pptx');
        $tables = $nodesOfType($document, 'table');

        $t->same('ppt/tableStyles.xml', $review['tableStyles']['partName'] ?? null);
        $t->same([], $review['tableStyles']['issues'] ?? null);
        $t->same('Medium Style 2 - Accent 1', $review['tableStyles']['styles']['{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}']['name'] ?? null);
        $t->same(true, $review['tableStyles']['styles']['{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}']['default'] ?? null);
        $t->same('Medium Style 2 - Accent 1', $tables[0]->attr('pptxTableStyle')['name'] ?? null);
        $t->same('ppt/tableStyles.xml', $tables[0]->attr('pptxTableStyle')['sourcePart'] ?? null);
    },

    'records external pptx table style policy without fetching target' => static function (TestRunner $t) use ($buildExternalTableStylesPptxPackage): void {
        $document = (new PptxReader())->read($buildExternalTableStylesPptxPackage());
        $tableStyles = $document->attr('pptx')['tableStyles'] ?? [];

        $t->same(true, $tableStyles['external'] ?? null);
        $t->same('', $tableStyles['partName'] ?? null);
        $t->same(['external-table-styles-part'], $tableStyles['issues'] ?? null);
        $t->same('javascript:alert(1)', $tableStyles['target'] ?? null);
        $t->same('absolute-uri', $tableStyles['externalTargetPolicy']['kind'] ?? null);
        $t->same('javascript', $tableStyles['externalTargetPolicy']['scheme'] ?? null);
        $t->same(false, $tableStyles['externalTargetPolicy']['allowed'] ?? null);
        $t->same(['external-target-unsafe-scheme'], $tableStyles['externalTargetPolicy']['issues'] ?? null);
    },

    'drops rowless pptx tables from visible reader content' => static function (TestRunner $t) use ($buildEmptyTablePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildEmptyTablePptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesOfType($document, 'table'));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->true(!str_contains($native, 'Table'), 'Rowless PPTX table should not emit a native Table block');
        $t->true(!str_contains($native, 'Empty Table'), 'Rowless PPTX table shape name should not leak into visible content');
    },

    'preserves upstream empty pptx text as explicit empty text nodes' => static function (TestRunner $t) use ($buildEmptyTextPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildEmptyTextPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $tables = $nodesOfType($document, 'table');
        $native = PandocConverter::write($document, 'native');

        $emptyParagraphs = array_values(array_filter(
            $paragraphs,
            static fn (AstNode $paragraph): bool => $paragraph->attr('text') === ''
        ));
        $emptyCell = $tables[0]->children[0]->children[0]->children[0] ?? null;
        $emptyParagraphInline = $emptyParagraphs[0]->children[0] ?? null;
        $emptyCellInline = $emptyCell instanceof AstNode ? ($emptyCell->children[0]->children[0] ?? null) : null;

        $t->same(1, count($emptyParagraphs));
        $t->same('', $emptyParagraphInline instanceof AstNode ? $emptyParagraphInline->attr('text') : null);
        $t->same('text', $emptyParagraphInline instanceof AstNode ? $emptyParagraphInline->type : null);
        $t->same(1, count($tables));
        $t->same('', $emptyCell instanceof AstNode ? $emptyCell->attr('text') : null);
        $t->same('text', $emptyCellInline instanceof AstNode ? $emptyCellInline->type : null);
        $t->same('', $emptyCellInline instanceof AstNode ? $emptyCellInline->attr('text') : null);
        $t->contains('Para [  ]', $native);
        $t->contains('[ Plain [  ]', $native);
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
    },

    'uses upstream fallback slide title instead of inherited layout title' => static function (TestRunner $t) use ($buildInheritedTitlePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildInheritedTitlePptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $native = PandocConverter::write($document, 'native');

        $t->same('heading', $document->children[0]->type);
        $t->same('Slide 1', $document->children[0]->attr('text'));
        $t->same('ppt/slideLayouts/slideLayout1.xml', $review['slides'][0]['context']['layoutPart'] ?? null);
        $t->same('ppt/slideMasters/slideMaster1.xml', $review['slides'][0]['context']['masterPart'] ?? null);
        $t->same(true, in_array('Visible body', $texts, true));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Slide" , Space , Str "1" ]', $native);
        $t->true(!str_contains($native, 'Inherited Layout Title'), 'Layout title should not become visible slide heading content');
        $t->true(!str_contains($native, 'Inherited Master Title'), 'Master title should not become visible slide heading content');
    },

    'drops missing image parts from visible pptx reader content with diagnostics' => static function (TestRunner $t) use ($buildMissingImagePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildMissingImagePptxPackage());
        $review = $document->attr('pptx');

        $t->same([], $nodesOfType($document, 'image'));
        $t->same(1, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same('missing-image-part', $review['slides'][0]['imageIssues'][0]['issue'] ?? null);
        $t->same('rIdImage', $review['slides'][0]['imageIssues'][0]['relationshipId'] ?? null);
        $t->same('../media/missing.png', $review['slides'][0]['imageIssues'][0]['target'] ?? null);
        $t->same('ppt/media/missing.png', $review['slides'][0]['imageIssues'][0]['partName'] ?? null);
        $t->same([
            'cx' => 9144000,
            'cy' => 6858000,
            'width' => 10,
            'height' => 7,
            'emusPerInch' => 914400,
            'source' => 'default',
        ], $review['slideSize'] ?? null);
    },

    'resolves upstream pptx media-relative image targets' => static function (TestRunner $t) use ($buildMediaRelativeImagePptxPackage, $buildRootTargetImagePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildMediaRelativeImagePptxPackage());
        $review = $document->attr('pptx');
        $images = $nodesOfType($document, 'image');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($images));
        $t->same('ppt/media/relative.png', $images[0]->attr('url'));
        $t->same('Relative Picture', $images[0]->attr('title'));
        $t->same('Relative alt', $images[0]->attr('alt'));
        $t->same('rIdImage', $images[0]->attr('relationshipId'));
        $t->same('embed', $images[0]->attr('relationshipAttribute'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Image ( "" , [  ] , [  ] ) [ Str "Relative" , Space , Str "alt" ] ( "ppt/media/relative.png" , "Relative Picture" )', $native);

        $rootDocument = (new PptxReader())->read($buildRootTargetImagePptxPackage());
        $rootReview = $rootDocument->attr('pptx');
        $rootImages = $nodesOfType($rootDocument, 'image');
        $rootNative = PandocConverter::write($rootDocument, 'native');

        $t->same(1, count($rootImages));
        $t->same('assets/root.png', $rootImages[0]->attr('url'));
        $t->same('Root Picture', $rootImages[0]->attr('title'));
        $t->same('Root alt', $rootImages[0]->attr('alt'));
        $t->same('rIdImage', $rootImages[0]->attr('relationshipId'));
        $t->same('embed', $rootImages[0]->attr('relationshipAttribute'));
        $t->same(0, $rootReview['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Image ( "" , [  ] , [  ] ) [ Str "Root" , Space , Str "alt" ] ( "assets/root.png" , "Root Picture" )', $rootNative);
    },

    'keeps pptx title-placeholder pictures visible like upstream' => static function (TestRunner $t) use ($buildTitlePlaceholderPicturePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildTitlePlaceholderPicturePptxPackage());
        $review = $document->attr('pptx');
        $images = $nodesOfType($document, 'image');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($images));
        $t->same('ppt/media/title-placeholder.png', $images[0]->attr('url'));
        $t->same('Title Placeholder Picture', $images[0]->attr('title'));
        $t->same('Title Placeholder Picture', $images[0]->attr('pptxShape')['name'] ?? null);
        $t->same('title', $images[0]->attr('pptxShape')['placeholderType'] ?? null);
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Picture" , Space , Str "placeholder" , Space , Str "title" ]', $native);
        $t->contains('Image ( "" , [  ] , [  ] ) [ Str "Title" , Space , Str "placeholder" , Space , Str "alt" ] ( "ppt/media/title-placeholder.png" , "Title Placeholder Picture" )', $native);
    },

    'drops pptx pictures without nonvisual properties from visible content' => static function (TestRunner $t) use ($buildPictureWithoutNonVisualPropertiesPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildPictureWithoutNonVisualPropertiesPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesOfType($document, 'image'));
        $t->same(1, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same('missing-picture-nonvisual-properties', $review['slides'][0]['imageIssues'][0]['issue'] ?? null);
        $t->true(!str_contains($native, 'Image'), 'Malformed PPTX picture should not emit a native Image inline');
        $t->true(!str_contains($native, 'ppt/media/picture.png'), 'Malformed PPTX picture media target should not leak into visible native content');
    },

    'records external linked pptx images without fetching target' => static function (TestRunner $t) use ($buildExternalLinkedImagePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildExternalLinkedImagePptxPackage());
        $review = $document->attr('pptx');
        $issue = $review['slides'][0]['imageIssues'][0] ?? [];

        $t->same([], $nodesOfType($document, 'image'));
        $t->same(1, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same('external-image-target', $issue['issue'] ?? null);
        $t->same('rIdLinkedImage', $issue['relationshipId'] ?? null);
        $t->same('link', $issue['relationshipAttribute'] ?? null);
        $t->same('https://example.test/linked.png', $issue['target'] ?? null);
        $t->same('absolute-uri', $issue['externalTargetPolicy']['kind'] ?? null);
        $t->same('https', $issue['externalTargetPolicy']['scheme'] ?? null);
        $t->same(true, $issue['externalTargetPolicy']['allowed'] ?? null);
        $t->same([], $issue['externalTargetPolicy']['issues'] ?? null);
    },

    'drops internal linked pptx images from visible content like upstream' => static function (TestRunner $t) use ($buildInternalLinkedImagePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildInternalLinkedImagePptxPackage());
        $review = $document->attr('pptx');
        $issue = $review['slides'][0]['imageIssues'][0] ?? [];
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesOfType($document, 'image'));
        $t->same(1, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same('linked-image-target', $issue['issue'] ?? null);
        $t->same('rIdLinkedImage', $issue['relationshipId'] ?? null);
        $t->same('link', $issue['relationshipAttribute'] ?? null);
        $t->same('../media/internal-linked.png', $issue['target'] ?? null);
        $t->same('ppt/media/internal-linked.png', $issue['partName'] ?? null);
        $t->true(!str_contains($native, 'Image'), 'Internal linked PPTX picture should not emit a native Image inline');
        $t->true(!str_contains($native, 'ppt/media/internal-linked.png'), 'Internal linked image target should not leak into visible native content');
    },

    'ignores pptx picture shape hyperlinks like upstream' => static function (TestRunner $t) use ($buildHyperlinkedPicturePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildHyperlinkedPicturePptxPackage());
        $review = $document->attr('pptx');
        $links = $nodesOfType($document, 'link');
        $images = $nodesOfType($document, 'image');
        $native = PandocConverter::write($document, 'native');

        $t->same(0, count($links));
        $t->same(1, count($images));
        $t->same('ppt/media/picture.png', $images[0]->attr('url'));
        $t->same('Linked Picture', $images[0]->attr('title'));
        $t->same('Picture alt', $images[0]->attr('alt'));
        $t->same('embed', $images[0]->attr('relationshipAttribute'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same(0, $review['slides'][0]['linkCount'] ?? null);
        $t->same([], $review['slides'][0]['links'] ?? null);
        $t->contains('Image', $native);
        $t->true(!str_contains($native, 'https://example.test/picture'), 'Picture hlinkClick target should not enter visible native output');
        $t->true(!str_contains($native, 'Link ('), 'Picture hlinkClick should not emit a native Link inline');
    },

    'ignores pptx text box shape hyperlinks like upstream' => static function (TestRunner $t) use ($buildHyperlinkedTextBoxPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildHyperlinkedTextBoxPptxPackage());
        $review = $document->attr('pptx');
        $links = $nodesOfType($document, 'link');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $native = PandocConverter::write($document, 'native');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);

        $t->same(0, count($links));
        $t->same(true, in_array('Open the text box', $texts, true));
        $t->same(0, $review['slides'][0]['linkCount'] ?? null);
        $t->same([], $review['slides'][0]['links'] ?? null);
        $t->contains('Para [ Str "Open" , Space , Str "the" , Space , Str "text" , Space , Str "box" ]', $native);
        $t->true(!str_contains($native, 'https://example.test/text-box'), 'Text box hlinkClick target should not enter visible native output');
        $t->true(!str_contains($native, 'Link ('), 'Text box hlinkClick should not emit a native Link inline');
    },

    'skips grouped pptx shapes to match upstream reader output' => static function (TestRunner $t) use ($buildGroupedShapesPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildGroupedShapesPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $images = $nodesOfType($document, 'image');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $native = PandocConverter::write($document, 'native');
        $issue = $review['slides'][0]['shapeIssues'][0] ?? [];

        $t->same('heading', $document->children[0]->type);
        $t->same('Grouped slide', $document->children[0]->attr('text'));
        $t->same(false, in_array('Grouped body', $texts, true));
        $t->same(false, in_array('Nested grouped body', $texts, true));
        $t->same(0, count($images));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same([], $review['slides'][0]['imageIssues'] ?? null);
        $t->same(1, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->same('unsupported-drawable-shape', $issue['issue'] ?? null);
        $t->same('grpSp', $issue['element'] ?? null);
        $t->same('10', $issue['id'] ?? null);
        $t->same('Group 1', $issue['name'] ?? null);
        $t->same(['x' => 100, 'y' => 200, 'cx' => 300, 'cy' => 400], $issue['layout'] ?? null);
        $t->true(!str_contains($native, 'Grouped body'), 'Grouped child text should stay out of upstream-compatible output');
        $t->true(!str_contains($native, 'Nested grouped body'), 'Nested grouped child text should stay out of upstream-compatible output');
        $t->true(!str_contains($native, 'Grouped Picture'), 'Grouped child picture should stay out of upstream-compatible output');
    },

    'records unsupported pptx connector shapes without fabricating content' => static function (TestRunner $t) use ($buildUnsupportedConnectorPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildUnsupportedConnectorPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $issue = $review['slides'][0]['shapeIssues'][0] ?? [];

        $t->same(false, in_array('Connector 8', $texts, true));
        $t->same(1, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->same('unsupported-drawable-shape', $issue['issue'] ?? null);
        $t->same('cxnSp', $issue['element'] ?? null);
        $t->same('9', $issue['id'] ?? null);
        $t->same('Connector 8', $issue['name'] ?? null);
        $t->same('Connector desc', $issue['descr'] ?? null);
        $t->same(['x' => 111, 'y' => 222, 'cx' => 333, 'cy' => 444], $issue['layout'] ?? null);
    },

    'keeps broken pptx SmartArt as visible parse diagnostics' => static function (TestRunner $t) use ($buildBrokenSmartArtPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildBrokenSmartArtPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $diagnostics = array_values(array_filter(
            $paragraphs,
            static fn (AstNode $paragraph): bool => str_contains((string) $paragraph->attr('text'), 'File not found in archive')
        ));

        $t->same(true, in_array('[Diagram parse error: File not found in archive: ppt/diagrams/missing-data.xml]', $texts, true));
        $t->same(1, count($diagnostics));
        $t->same('graphicFrame', $diagnostics[0]->attr('pptxShape')['element'] ?? null);
        $t->same('Broken SmartArt Frame', $diagnostics[0]->attr('pptxShape')['name'] ?? null);
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
    },

    'requires pptx SmartArt data nodes to use the diagram namespace like upstream' => static function (TestRunner $t) use ($buildWrongNamespaceSmartArtPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildWrongNamespaceSmartArtPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $divs = $nodesOfType($document, 'div');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesWithClass($divs, 'smartart'));
        $t->same(true, in_array('[Diagram parse error: Missing dgm:ptLst]', $texts, true));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->true(!str_contains($native, 'Wrong namespace node'), 'Non-dgm SmartArt data text should stay out of upstream-compatible output');
        $t->contains('Para [ Str "[Diagram" , Space , Str "parse" , Space , Str "error:" , Space , Str "Missing" , Space , Str "dgm:ptLst]" ]', $native);
    },

    'ignores pptx slide shapes outside the presentation namespace like upstream' => static function (TestRunner $t) use ($buildWrongNamespaceShapePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildWrongNamespaceShapePptxPackage());
        $review = $document->attr('pptx');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $nodesOfType($document, 'paragraph'));
        $native = PandocConverter::write($document, 'native');

        $t->same('Namespace slide', $document->children[0]->attr('text'));
        $t->same(false, in_array('Wrong namespace body', $paragraphTexts, true));
        $t->same(false, in_array('Unqualified namespace body', $paragraphTexts, true));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->true(!str_contains($native, 'Wrong namespace body'), 'Non-presentation namespace shapes should stay out of upstream-compatible output');
        $t->true(!str_contains($native, 'Unqualified namespace body'), 'Unqualified PPTX shape local names should stay out of upstream-compatible output');
    },

    'ignores pptx presentation slide lists outside the presentation namespace like upstream' => static function (TestRunner $t) use ($buildWrongNamespacePresentationSlidesPptxPackage): void {
        $document = (new PptxReader())->read($buildWrongNamespacePresentationSlidesPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same(0, $review['slideCount'] ?? null);
        $t->same([], $document->children);
        $t->same('default', $review['slideSize']['source'] ?? null);
        $t->true(!str_contains($native, 'Wrong namespace presentation body'), 'Non-presentation namespace slide IDs should not select visible slides');
    },

    'requires pptx presentation slide relationship ids to use the relationship namespace like upstream' => static function (TestRunner $t) use ($buildUnqualifiedPresentationRelationshipPptxPackage): void {
        $t->throws(RuntimeException::class, static fn (): AstNode => (new PptxReader())->read($buildUnqualifiedPresentationRelationshipPptxPackage()));
    },

    'ignores pptx table rows cells and text bodies outside the drawing namespace like upstream' => static function (TestRunner $t) use ($buildWrongNamespaceTablePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildWrongNamespaceTablePptxPackage());
        $tables = $nodesOfType($document, 'table');
        $cellTexts = array_map(static fn (AstNode $cell): string => (string) $cell->attr('text'), $nodesOfType($document, 'table_cell'));
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($tables));
        $t->same([1828800], $tables[0]->attr('columnWidths'));
        $t->same(true, in_array('Visible header', $cellTexts, true));
        $t->same(false, in_array('Wrong namespace cell', $cellTexts, true));
        $t->same(false, in_array('Wrong namespace row', $cellTexts, true));
        $t->same(false, in_array('Wrong namespace text body', $cellTexts, true));
        $t->contains('Str "Visible" , Space , Str "header"', $native);
        $t->true(!str_contains($native, 'Wrong namespace cell'), 'Non-drawing namespace table cells should stay out of upstream-compatible output');
        $t->true(!str_contains($native, 'Wrong namespace row'), 'Non-drawing namespace table rows should stay out of upstream-compatible output');
        $t->true(!str_contains($native, 'Wrong namespace text body'), 'Non-drawing namespace table text bodies should stay out of upstream-compatible output');
    },

    'uses the first root officeDocument relationship like upstream' => static function (TestRunner $t) use ($buildFirstOfficeDocumentRelationshipPptxPackage): void {
        $t->throws(RuntimeException::class, static fn (): AstNode => (new PptxReader())->read($buildFirstOfficeDocumentRelationshipPptxPackage()));
    },

    'uses upstream literal pptx relationship sidecar paths instead of OPC URI encoding' => static function (TestRunner $t) use ($buildLiteralPresentationRelationshipPartPptxPackage): void {
        $document = (new PptxReader())->read($buildLiteralPresentationRelationshipPartPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('ppt/presentation deck.xml', $review['presentationPart'] ?? null);
        $t->same('Literal rels path', $document->children[0]->attr('text'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Literal" , Space , Str "rels" , Space , Str "path" ]', $native);
    },

    'uses upstream literal root officeDocument targets instead of normalizing dot segments' => static function (TestRunner $t) use ($buildDotSegmentPresentationTargetPptxPackage): void {
        $t->throws(RuntimeException::class, static fn (): AstNode => (new PptxReader())->read($buildDotSegmentPresentationTargetPptxPackage()));
    },

    'uses upstream literal pptx slide targets instead of normalizing root-relative paths' => static function (TestRunner $t) use ($buildRootRelativeSlideTargetPptxPackage): void {
        $t->throws(RuntimeException::class, static fn (): AstNode => (new PptxReader())->read($buildRootRelativeSlideTargetPptxPackage()));
    },

    'keeps pptx relationships without Type usable for target lookup like upstream' => static function (TestRunner $t) use ($buildUntypedRelationshipsPptxPackage): void {
        $document = (new PptxReader())->read($buildUntypedRelationshipsPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Untyped relationships', $document->children[0]->attr('text'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Untyped" , Space , Str "relationships" ]', $native);
    },

    'keeps pptx relationship ids as raw text like upstream' => static function (TestRunner $t) use ($buildInvalidRelationshipIdPptxPackage): void {
        $document = (new PptxReader())->read($buildInvalidRelationshipIdPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Invalid relationship id', $document->children[0]->attr('text'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Invalid" , Space , Str "relationship" , Space , Str "id" ]', $native);
    },

    'uses upstream pptx graphic placeholders for missing graphic metadata' => static function (TestRunner $t) use ($buildGraphicPlaceholderPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildGraphicPlaceholderPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $native = PandocConverter::write($document, 'native');

        foreach ([
            '[Graphic: no-uri]',
            '[Graphic: diagram-no-relIds]',
            '[Graphic: diagram-missing-rels]',
            '[Diagram parse error: Relationship not found: rIdMissingData]',
        ] as $expected) {
            $t->same(true, in_array($expected, $texts, true));
        }

        $t->contains('Para [ Str "[Graphic:" , Space , Str "no-uri]" ]', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "diagram-no-relIds]" ]', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "diagram-missing-rels]" ]', $native);
        $t->contains('Para [ Str "[Diagram" , Space , Str "parse" , Space , Str "error:" , Space , Str "Relationship" , Space , Str "not" , Space , Str "found:" , Space , Str "rIdMissingData]" ]', $native);

        $placeholderParagraphs = array_values(array_filter(
            $paragraphs,
            static fn (AstNode $paragraph): bool => str_starts_with((string) $paragraph->attr('text'), '[Graphic:')
                || str_starts_with((string) $paragraph->attr('text'), '[Diagram parse error:')
        ));

        $t->same(4, count($placeholderParagraphs));
        $t->same('No URI Graphic', $placeholderParagraphs[0]->attr('pptxShape')['name'] ?? null);
        $t->same('Diagram No RelIds', $placeholderParagraphs[1]->attr('pptxShape')['name'] ?? null);
        $t->same('Diagram Missing Rels', $placeholderParagraphs[2]->attr('pptxShape')['name'] ?? null);
        $t->same('Diagram Unknown Rel', $placeholderParagraphs[3]->attr('pptxShape')['name'] ?? null);
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
    },

    'ignores pptx text run hyperlinks like upstream' => static function (TestRunner $t) use ($buildHyperlinkedTextPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildHyperlinkedTextPptxPackage());
        $review = $document->attr('pptx');
        $links = $nodesOfType($document, 'link');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $native = PandocConverter::write($document, 'native');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);

        $t->same(0, count($links));
        $t->same(true, count(array_filter($texts, static fn (string $text): bool => str_contains($text, 'the spec'))) > 0);
        $t->same(0, $review['slides'][0]['linkCount'] ?? null);
        $t->same([], $review['slides'][0]['links'] ?? null);
        $t->contains('Str "the" , Space , Str "spec"', $native);
        $t->true(!str_contains($native, 'https://example.test/spec?x=1'), 'Run hlinkClick target should not enter visible native output');
        $t->true(!str_contains($native, 'Link ('), 'Run hlinkClick should not emit a native Link inline');
    },

    'ignores pptx drawing text breaks and tabs like upstream' => static function (TestRunner $t) use ($buildBreakTabTextPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildBreakTabTextPptxPackage());
        $paragraphs = $nodesOfType($document, 'paragraph');
        $native = PandocConverter::write($document, 'native');
        $bodyParagraphs = array_values(array_filter(
            $paragraphs,
            static fn (AstNode $paragraph): bool => str_contains((string) $paragraph->attr('text'), 'Line one')
        ));

        $t->same(1, count($bodyParagraphs));
        $t->same('Line one Line two Tabbed', $bodyParagraphs[0]->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $inline): string => $inline->type, $bodyParagraphs[0]->children));
        $t->same('Line one Line two Tabbed', $bodyParagraphs[0]->children[0]->attr('text'));
        $t->same(0, count($nodesOfType($document, 'linebreak')));
        $t->contains('Para [ Str "Line" , Space , Str "one" , Space , Str "Line" , Space , Str "two" , Space , Str "Tabbed" ]', $native);
        $t->true(!str_contains($native, 'LineBreak'), 'DrawingML break markers should not become native LineBreak nodes');
    },

    'keeps pptx auto-numbered paragraphs plain like upstream' => static function (TestRunner $t) use ($buildNumberedListPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildNumberedListPptxPackage());
        $orderedLists = $nodesOfType($document, 'ordered_list');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $native = PandocConverter::write($document, 'native');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);

        $t->same(0, count($orderedLists));
        $t->same(true, in_array('Third item', $texts, true));
        $t->same(true, in_array('Fourth item', $texts, true));
        $t->same(true, in_array('Alpha item', $texts, true));
        $t->contains('Para [ Str "Third" , Space , Str "item" ]', $native);
        $t->contains('Para [ Str "Fourth" , Space , Str "item" ]', $native);
        $t->contains('Para [ Str "Alpha" , Space , Str "item" ]', $native);
        $t->true(!str_contains($native, 'OrderedList'), 'PPTX buAutoNum should not become a native OrderedList with the current upstream reader');
    },

    'requires pptx Wingdings bullet symbols to live in run properties like upstream' => static function (TestRunner $t) use ($buildEndParagraphSymbolPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildEndParagraphSymbolPptxPackage());
        $bulletLists = $nodesOfType($document, 'bullet_list');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $native = PandocConverter::write($document, 'native');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);

        $t->same(0, count($bulletLists));
        $t->same(true, in_array('Not a Wingdings bullet', $texts, true));
        $t->contains('Para [ Str "Not" , Space , Str "a" , Space , Str "Wingdings" , Space , Str "bullet" ]', $native);
        $t->true(!str_contains($native, 'BulletList'), 'Wingdings symbols outside a:r/a:rPr should not create upstream PPTX bullet lists');
    },

    'lets pptx Wingdings run symbols override buNone like upstream' => static function (TestRunner $t) use ($buildBuNoneWingdingsSymbolPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildBuNoneWingdingsSymbolPptxPackage());
        $bulletLists = $nodesOfType($document, 'bullet_list');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $native = PandocConverter::write($document, 'native');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $firstItem = $bulletLists[0]->children[0]->children[0]->children[0] ?? null;

        $t->same(1, count($bulletLists));
        $t->same('Wingdings still wins', $firstItem instanceof AstNode ? $firstItem->attr('text') : null);
        $t->same(true, in_array('Plain buNone stays plain', $texts, true));
        $t->contains('BulletList [ [ Plain [ Str "Wingdings" , Space , Str "still" , Space , Str "wins"', $native);
        $t->contains('Para [ Str "Plain" , Space , Str "buNone" , Space , Str "stays" , Space , Str "plain" ]', $native);
    },

    'splits pptx list levels instead of nesting like upstream' => static function (TestRunner $t) use ($buildNestedListPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildNestedListPptxPackage());
        $bulletLists = $nodesOfType($document, 'bullet_list');
        $orderedLists = $nodesOfType($document, 'ordered_list');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $topLevelLists = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => in_array($node->type, ['bullet_list', 'ordered_list'], true)
        ));
        $itemText = static function (AstNode $item): string {
            $plain = $item->children[0] ?? new AstNode('plain');
            $text = '';
            foreach ($plain->children as $inline) {
                if ($inline->type === 'text') {
                    $text .= (string) $inline->attr('text');
                } elseif ($inline->type === 'space') {
                    $text .= ' ';
                }
            }

            return $text;
        };
        $native = PandocConverter::write($document, 'native');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);

        $t->same(['bullet_list', 'bullet_list', 'bullet_list'], array_map(static fn (AstNode $node): string => $node->type, $topLevelLists));
        $t->same(3, count($bulletLists));
        $t->same(0, count($orderedLists));
        $t->same(['Parent bullet'], array_map($itemText, $topLevelLists[0]->children));
        $t->same(['Child bullet'], array_map($itemText, $topLevelLists[1]->children));
        $t->same(['Second parent'], array_map($itemText, $topLevelLists[2]->children));
        $t->same(true, in_array('Numbered child', $paragraphTexts, true));
        $t->contains('Para [ Str "Numbered" , Space , Str "child" ]', $native);
        $t->true(!str_contains($native, 'OrderedList'), 'Nested buAutoNum paragraph should remain plain with the current upstream reader');
    },

    'keeps pptx buNone paragraphs plain like upstream' => static function (TestRunner $t) use ($buildListContinuationPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildListContinuationPptxPackage());
        $topLevelLists = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => in_array($node->type, ['bullet_list', 'ordered_list'], true)
        ));
        $paragraphs = $nodesOfType($document, 'paragraph');
        $itemText = static function (AstNode $item): string {
            $plain = $item->children[0] ?? new AstNode('plain');
            $text = '';
            foreach ($plain->children as $inline) {
                if ($inline->type === 'text') {
                    $text .= (string) $inline->attr('text');
                } elseif ($inline->type === 'space') {
                    $text .= ' ';
                }
            }

            return $text;
        };
        $native = PandocConverter::write($document, 'native');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);

        $t->same(3, count($topLevelLists));
        $t->same(['Top-level'], array_map($itemText, $topLevelLists[0]->children));
        $t->same(['Nested bullet'], array_map($itemText, $topLevelLists[1]->children));
        $t->same(['Second top-level'], array_map($itemText, $topLevelLists[2]->children));
        $t->same(true, in_array('With continuation', $paragraphTexts, true));
        $t->same(true, in_array('Nested continuation', $paragraphTexts, true));
        $t->contains('Para [ Str "With" , Space , Str "continuation" ]', $native);
        $t->contains('Para [ Str "Nested" , Space , Str "continuation" ]', $native);
    },

    'keeps pptx speaker notes out of visible output like upstream' => static function (TestRunner $t) use ($buildSpeakerNotesPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildSpeakerNotesPptxPackage());
        $review = $document->attr('pptx');
        $notesDivs = $nodesWithClass($nodesOfType($document, 'div'), 'notes');
        $native = PandocConverter::write($document, 'native');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $nodesOfType($document, 'paragraph'));

        $t->same(0, count($notesDivs));
        $t->same(1, $review['slides'][0]['speakerNoteCount'] ?? null);
        $t->same('rIdNotes', $review['slides'][0]['speakerNotes'][0]['relationshipId'] ?? null);
        $t->same('ppt/notesSlides/notesSlide1.xml', $review['slides'][0]['speakerNotes'][0]['partName'] ?? null);
        $t->same('Remember the launch date.' . "\n" . 'Ask about migration risks.', $review['slides'][0]['speakerNotes'][0]['text'] ?? null);
        $t->same(2, $review['slides'][0]['speakerNotes'][0]['blockCount'] ?? null);
        $t->true(!isset($review['slides'][0]['speakerNotes'][0]['blocks']), 'Review metadata must not embed AST note blocks');
        $t->same(false, in_array('Remember the launch date.', $paragraphTexts, true));
        $t->same(false, in_array('Ask about migration risks.', $paragraphTexts, true));
        $t->true(!str_contains($native, 'Div ( "" , [ "notes" ]'), 'PPTX notesSlide content should not emit a native notes Div');
        $t->true(!str_contains($native, 'Remember the launch date'), 'PPTX notesSlide text should stay out of visible native output');
        $t->true(!str_contains($native, 'Ask about migration risks'), 'PPTX notesSlide text should stay out of visible native output');
    },

    'reads pptx bytes through the converter input path' => static function (TestRunner $t) use ($buildPptxPackage): void {
        $document = PandocConverter::read($buildPptxPackage(), 'pptx');
        $html = PandocConverter::write($document, 'html');

        $t->same('pptx', $document->attr('sourceFormat'));
        $t->same('LLMs', $document->children[0]->attr('text'));
        $t->contains('<h2 id="slide-1">LLMs</h2>', $html);
        $t->contains('<th>Col1</th>', $html);
        $t->contains('<img src="ppt/media/image1.png"', $html);
    },

    'rejects pptx packages without a presentation relationship' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary PPTX package');
            }
        } finally {
            @unlink($path);
        }

        $t->throws(RuntimeException::class, static fn (): AstNode => (new PptxReader())->read($bytes));
    },
];
