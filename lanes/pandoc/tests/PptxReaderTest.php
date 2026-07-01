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
        $chartDivs = $nodesWithClass($divs, 'pptx-chart');
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
        $t->same(2, $tables[0]->children[1]->children[0]->children[0]->attr('colspan'));
        $t->same('D9EAF7', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['fillColor'] ?? null);
        $t->same('ctr', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['verticalAlign'] ?? null);
        $t->same('theme:accent1', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['borders']['bottom'] ?? null);
        $t->same('4472C4', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['resolvedBorders']['bottom'] ?? null);
        $t->same(12700, $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['borderStyles']['bottom']['width'] ?? null);
        $t->same('solid', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['borderStyles']['bottom']['dash'] ?? null);
        $t->same('4472C4', $tables[0]->children[1]->children[0]->children[0]->attr('pptxCellStyle')['borderStyles']['bottom']['resolvedColor'] ?? null);
        $t->same(2, $tables[0]->children[1]->children[1]->children[0]->attr('rowspan'));
        $t->same('23', $tables[0]->children[1]->children[1]->children[1]->attr('text'));
        $t->same(1, count($images));
        $t->same('ppt/media/image1.png', $images[0]->attr('url'));
        $t->same('Picture 6', $images[0]->attr('title'));

        $t->same(1, count($chartDivs));
        $t->same(['pptx-chart', 'pptx-chart-bar'], $chartDivs[0]->attr('classes'));
        $t->same('ppt/charts/chart1.xml', $chartDivs[0]->attr('attributes')['src'] ?? null);
        $t->same('Quarterly Revenue', $chartDivs[0]->attr('attributes')['title'] ?? null);
        $t->same('2', $chartDivs[0]->attr('attributes')['series-count'] ?? null);
        $t->same('2', $chartDivs[0]->attr('attributes')['plot-count'] ?? null);
        $t->same('bar', $chartDivs[0]->attr('pptxChart')['chartType'] ?? null);
        $t->same(['bar', 'line'], $chartDivs[0]->attr('pptxChart')['chartTypes'] ?? null);
        $t->same(2, $chartDivs[0]->attr('pptxChart')['chartTypeCount'] ?? null);
        $t->same('col', $chartDivs[0]->attr('pptxChart')['plots'][0]['barDirection'] ?? null);
        $t->same(['10', '20'], $chartDivs[0]->attr('pptxChart')['plots'][0]['axisIds'] ?? null);
        $t->same('line', $chartDivs[0]->attr('pptxChart')['plots'][1]['type'] ?? null);
        $t->same('standard', $chartDivs[0]->attr('pptxChart')['plots'][1]['grouping'] ?? null);
        $t->same(['Q1', 'Q2'], $chartDivs[0]->attr('pptxChart')['series'][0]['categories'] ?? null);
        $t->same(['12', '18'], $chartDivs[0]->attr('pptxChart')['series'][0]['values'] ?? null);
        $t->same('line', $chartDivs[0]->attr('pptxChart')['series'][1]['plotType'] ?? null);
        $t->same(['9', '13'], $chartDivs[0]->attr('pptxChart')['series'][1]['values'] ?? null);
        $t->same('Quarter', $chartDivs[0]->attr('pptxChart')['axes'][0]['title'] ?? null);
        $t->same('Revenue', $chartDivs[0]->attr('pptxChart')['axes'][1]['title'] ?? null);
        $t->same('$#,##0', $chartDivs[0]->attr('pptxChart')['axes'][1]['numberFormat'] ?? null);
        $t->same(false, $chartDivs[0]->attr('pptxChart')['axes'][1]['sourceLinked'] ?? null);
        $t->same(['rIdWorkbook'], $chartDivs[0]->attr('pptxChart')['externalDataRelationshipIds'] ?? null);
        $t->same('ppt/embeddings/Microsoft_Excel_Worksheet1.xlsx', $chartDivs[0]->attr('pptxChart')['externalDataRelationships'][0]['partName'] ?? null);
        $t->same('North: Q1=12; Q2=18', $chartDivs[0]->children[1]->attr('text'));
        $t->same('South: Q1=9; Q2=13', $chartDivs[0]->children[2]->attr('text'));

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

        $t->same(1, count($mediaDivs));
        $t->same(['pptx-rich-media', 'pptx-video'], $mediaDivs[0]->attr('classes'));
        $t->same('ppt/media/video1.mp4', $mediaDivs[0]->attr('attributes')['src'] ?? null);
        $t->same('video', $mediaDivs[0]->attr('pptxMedia')['kind'] ?? null);
        $t->same('pic', $mediaDivs[0]->attr('pptxShape')['element'] ?? null);
        $t->same(3, $mediaDivs[0]->attr('pptxShape')['zOrder'] ?? null);
        $t->same(['x' => 555, 'y' => 666, 'cx' => 777, 'cy' => 888], $mediaDivs[0]->attr('pptxShape')['layout'] ?? null);

        $t->same(1, count($commentDivs));
        $t->same('Ada Reviewer', $commentDivs[0]->attr('pptxComments')[0]['author'] ?? null);
        $t->same('Review this clip', $commentDivs[0]->attr('pptxComments')[0]['text'] ?? null);
        $t->same(1, count($backLayerParagraphs));
        $t->same(2, $backLayerParagraphs[0]->attr('pptxShape')['zOrder'] ?? null);
        $t->same(['x' => 111, 'y' => 222, 'cx' => 333, 'cy' => 444], $backLayerParagraphs[0]->attr('pptxShape')['layout'] ?? null);
        $t->same(1, count($frontLayerParagraphs));
        $t->same(4, $frontLayerParagraphs[0]->attr('pptxShape')['zOrder'] ?? null);

        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "LLMs" ]', $native);
        $t->contains('BulletList', $native);
        $t->contains('Table ( "" , [  ] , [  ] )', $native);
        $t->contains('Image ( "" , [  ] , [  ] ) [  ] ( "ppt/media/image1.png" , "Picture 6" )', $native);
        $t->contains('Div ( "" , [ "pptx-chart" , "pptx-chart-bar" ]', $native);
        $t->contains('Div ( "" , [ "smartart" , "chevron2" ] , [ ( "layout" , "chevron2" ) ] )', $native);
        $t->contains('Div ( "" , [ "pptx-rich-media" , "pptx-video" ]', $native);
        $t->contains('( "src" , "ppt/media/video1.mp4" )', $native);
        $t->contains('<!-- wp:heading {"level":2} -->', $blocks);
        $t->contains('<th>Col1</th>', $blocks);
        $t->contains('Quarterly Revenue', $blocks);
        $t->contains('ppt/media/image1.png', $blocks);
        $t->contains('data-pandoc-comment-author="Ada Reviewer"', $blocks);
        $t->contains('Inherited Layout Body', $blocks);
        $t->contains('Inherited Master Footer', $blocks);
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
        $t->same('612e143fbe6d735b612c4800d21e61b7d44e4dca', $review['upstreamEvidence']['fixtureCommit'] ?? null);
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

    'wraps pptx pictures with shape-level hyperlinks' => static function (TestRunner $t) use ($buildHyperlinkedPicturePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildHyperlinkedPicturePptxPackage());
        $review = $document->attr('pptx');
        $links = $nodesOfType($document, 'link');
        $images = $nodesOfType($document, 'image');

        $t->same(1, count($links));
        $t->same(1, count($images));
        $t->same('image', $links[0]->children[0]->type);
        $t->same('https://example.test/picture', $links[0]->attr('url'));
        $t->same('Open figure', $links[0]->attr('title'));
        $t->same('rIdPictureLink', $links[0]->attr('relationshipId'));
        $t->same('External', $links[0]->attr('targetMode'));
        $t->same(true, $links[0]->attr('external'));
        $t->same('ppt/media/picture.png', $images[0]->attr('url'));
        $t->same('Linked Picture', $images[0]->attr('title'));
        $t->same('Picture alt', $images[0]->attr('alt'));
        $t->same('embed', $images[0]->attr('relationshipAttribute'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same(1, $review['slides'][0]['linkCount'] ?? null);
        $t->same('https://example.test/picture', $review['slides'][0]['links'][0]['url'] ?? null);
        $t->same('rIdPictureLink', $review['slides'][0]['links'][0]['relationshipId'] ?? null);
    },

    'wraps pptx text boxes with shape-level hyperlinks' => static function (TestRunner $t) use ($buildHyperlinkedTextBoxPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildHyperlinkedTextBoxPptxPackage());
        $review = $document->attr('pptx');
        $links = $nodesOfType($document, 'link');
        $native = PandocConverter::write($document, 'native');
        $textBoxLinks = array_values(array_filter(
            $links,
            static fn (AstNode $link): bool => $link->attr('relationshipId') === 'rIdTextBoxLink'
        ));

        $t->same(1, count($textBoxLinks));
        $t->same('https://example.test/text-box', $textBoxLinks[0]->attr('url'));
        $t->same('Open text box', $textBoxLinks[0]->attr('title'));
        $t->same('External', $textBoxLinks[0]->attr('targetMode'));
        $t->same(true, $textBoxLinks[0]->attr('external'));
        $t->same(['text'], array_map(static fn (AstNode $inline): string => $inline->type, $textBoxLinks[0]->children));
        $t->same('Open the text box', $textBoxLinks[0]->children[0]->attr('text'));
        $t->same(1, $review['slides'][0]['linkCount'] ?? null);
        $t->same('https://example.test/text-box', $review['slides'][0]['links'][0]['url'] ?? null);
        $t->same('rIdTextBoxLink', $review['slides'][0]['links'][0]['relationshipId'] ?? null);
        $t->contains('Link ( "" , [  ] , [  ] ) [ Str "Open" , Space , Str "the" , Space , Str "text" , Space , Str "box" ] ( "https://example.test/text-box" , "Open text box" )', $native);
    },

    'reads text and images inside grouped pptx shapes' => static function (TestRunner $t) use ($buildGroupedShapesPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildGroupedShapesPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $images = $nodesOfType($document, 'image');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);

        $t->same(true, in_array('Grouped body', $texts, true));
        $t->same(true, in_array('Nested grouped body', $texts, true));
        $t->same(1, count($images));
        $t->same('ppt/media/grouped.png', $images[0]->attr('url'));
        $t->same('Grouped Picture', $images[0]->attr('title'));
        $t->same('Grouped alt', $images[0]->attr('alt'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same([], $review['slides'][0]['imageIssues'] ?? null);
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

    'preserves pptx text run hyperlinks with relationship metadata' => static function (TestRunner $t) use ($buildHyperlinkedTextPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildHyperlinkedTextPptxPackage());
        $review = $document->attr('pptx');
        $links = $nodesOfType($document, 'link');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($links));
        $t->same('the spec', $links[0]->children[0]->attr('text'));
        $t->same('https://example.test/spec?x=1', $links[0]->attr('url'));
        $t->same('Spec link', $links[0]->attr('title'));
        $t->same('rIdLink', $links[0]->attr('relationshipId'));
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink', $links[0]->attr('relationshipType'));
        $t->same('External', $links[0]->attr('targetMode'));
        $t->same(true, $links[0]->attr('external'));
        $t->same(true, $links[0]->attr('externalTargetAllowed'));
        $t->same('https', $links[0]->attr('externalTargetScheme'));
        $t->same(1, $review['slides'][0]['linkCount'] ?? null);
        $t->same('https://example.test/spec?x=1', $review['slides'][0]['links'][0]['url'] ?? null);
        $t->same('rIdLink', $review['slides'][0]['links'][0]['relationshipId'] ?? null);
        $t->same('External', $review['slides'][0]['links'][0]['targetMode'] ?? null);
        $t->contains('Link ( "" , [  ] , [  ] ) [ Str "the" , Space , Str "spec" ] ( "https://example.test/spec?x=1" , "Spec link" )', $native);
    },

    'preserves pptx drawing text breaks and tabs as inlines' => static function (TestRunner $t) use ($buildBreakTabTextPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildBreakTabTextPptxPackage());
        $paragraphs = $nodesOfType($document, 'paragraph');
        $native = PandocConverter::write($document, 'native');
        $bodyParagraphs = array_values(array_filter(
            $paragraphs,
            static fn (AstNode $paragraph): bool => str_contains((string) $paragraph->attr('text'), 'Line one')
        ));

        $t->same(1, count($bodyParagraphs));
        $t->same(['text', 'linebreak', 'text', 'space', 'text'], array_map(static fn (AstNode $inline): string => $inline->type, $bodyParagraphs[0]->children));
        $t->same('Line one', $bodyParagraphs[0]->children[0]->attr('text'));
        $t->same('Line two', $bodyParagraphs[0]->children[2]->attr('text'));
        $t->same('Tabbed', $bodyParagraphs[0]->children[4]->attr('text'));
        $t->same(1, count($nodesOfType($document, 'linebreak')));
        $t->contains('Para [ Str "Line" , Space , Str "one" , LineBreak , Str "Line" , Space , Str "two" , Space , Str "Tabbed" ]', $native);
    },

    'preserves pptx auto-numbered paragraphs as ordered lists' => static function (TestRunner $t) use ($buildNumberedListPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildNumberedListPptxPackage());
        $orderedLists = $nodesOfType($document, 'ordered_list');
        $native = PandocConverter::write($document, 'native');
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

        $t->same(2, count($orderedLists));
        $t->same(3, $orderedLists[0]->attr('start'));
        $t->same('decimal', $orderedLists[0]->attr('style'));
        $t->same('period', $orderedLists[0]->attr('delimiter'));
        $t->same('arabicPeriod', $orderedLists[0]->attr('pptxAutoNumberType'));
        $t->same(['Third item', 'Fourth item'], array_map($itemText, $orderedLists[0]->children));

        $t->same(1, $orderedLists[1]->attr('start'));
        $t->same('upper_alpha', $orderedLists[1]->attr('style'));
        $t->same('one_paren', $orderedLists[1]->attr('delimiter'));
        $t->same('alphaUcParenR', $orderedLists[1]->attr('pptxAutoNumberType'));
        $t->same('Alpha item', $itemText($orderedLists[1]->children[0]));
        $t->contains('OrderedList ( 3 , Decimal , Period )', $native);
        $t->contains('OrderedList ( 1 , UpperAlpha , OneParen )', $native);
    },

    'preserves pptx nested list levels inside list items' => static function (TestRunner $t) use ($buildNestedListPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildNestedListPptxPackage());
        $bulletLists = $nodesOfType($document, 'bullet_list');
        $orderedLists = $nodesOfType($document, 'ordered_list');
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

        $t->same(['bullet_list'], array_map(static fn (AstNode $node): string => $node->type, $topLevelLists));
        $t->same(2, count($bulletLists));
        $t->same(1, count($orderedLists));
        $t->same(2, count($topLevelLists[0]->children));
        $t->same(['Parent bullet', 'Second parent'], array_map($itemText, $topLevelLists[0]->children));
        $t->same(['plain', 'bullet_list', 'ordered_list'], array_map(static fn (AstNode $node): string => $node->type, $topLevelLists[0]->children[0]->children));
        $t->same('Child bullet', $itemText($topLevelLists[0]->children[0]->children[1]->children[0]));
        $t->same(2, $topLevelLists[0]->children[0]->children[2]->attr('start'));
        $t->same('Numbered child', $itemText($topLevelLists[0]->children[0]->children[2]->children[0]));
        $t->contains('OrderedList ( 2 , Decimal , Period )', $native);
    },

    'keeps pptx buNone paragraphs as list item continuations' => static function (TestRunner $t) use ($buildListContinuationPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildListContinuationPptxPackage());
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
        $paragraphText = static fn (AstNode $paragraph): string => (string) $paragraph->attr('text');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($topLevelLists));
        $t->same(['Top-level', 'Second top-level'], array_map($itemText, $topLevelLists[0]->children));
        $t->same(['plain', 'paragraph', 'bullet_list'], array_map(static fn (AstNode $node): string => $node->type, $topLevelLists[0]->children[0]->children));
        $t->same('With continuation', $paragraphText($topLevelLists[0]->children[0]->children[1]));
        $nested = $topLevelLists[0]->children[0]->children[2];
        $t->same('bullet_list', $nested->type);
        $t->same(['plain', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $nested->children[0]->children));
        $t->same('Nested bullet', $itemText($nested->children[0]));
        $t->same('Nested continuation', $paragraphText($nested->children[0]->children[1]));
        $t->same([], array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'paragraph' && in_array($node->attr('text'), ['With continuation', 'Nested continuation'], true)
        )));
        $t->contains('Para [ Str "With" , Space , Str "continuation" ]', $native);
        $t->contains('Para [ Str "Nested" , Space , Str "continuation" ]', $native);
    },

    'preserves pptx speaker notes as notes divs' => static function (TestRunner $t) use ($buildSpeakerNotesPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildSpeakerNotesPptxPackage());
        $review = $document->attr('pptx');
        $notesDivs = $nodesWithClass($nodesOfType($document, 'div'), 'notes');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($notesDivs));
        $t->same('pptx', $notesDivs[0]->attr('attributes')['source'] ?? null);
        $t->same('ppt/notesSlides/notesSlide1.xml', $notesDivs[0]->attr('attributes')['part'] ?? null);
        $t->same('rIdNotes', $notesDivs[0]->attr('attributes')['relationship-id'] ?? null);
        $t->same('ppt/notesSlides/notesSlide1.xml', $notesDivs[0]->attr('pptxSpeakerNote')['partName'] ?? null);
        $t->same('Remember the launch date.' . "\n" . 'Ask about migration risks.', $notesDivs[0]->attr('pptxSpeakerNote')['text'] ?? null);
        $t->same(1, $review['slides'][0]['speakerNoteCount'] ?? null);
        $t->same('rIdNotes', $review['slides'][0]['speakerNotes'][0]['relationshipId'] ?? null);
        $t->same('ppt/notesSlides/notesSlide1.xml', $review['slides'][0]['speakerNotes'][0]['partName'] ?? null);
        $t->same(2, $review['slides'][0]['speakerNotes'][0]['blockCount'] ?? null);
        $t->true(!isset($review['slides'][0]['speakerNotes'][0]['blocks']), 'Review metadata must not embed AST note blocks');

        $noteParagraphs = $nodesOfType($notesDivs[0], 'paragraph');
        $t->same(['Remember the launch date.', 'Ask about migration risks.'], array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $noteParagraphs));
        $t->same(false, in_array('1', array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $noteParagraphs), true));
        $t->contains('Div ( "" , [ "notes" ]', $native);
        $t->contains('Para [ Str "Remember" , Space , Str "the" , Space , Str "launch" , Space , Str "date." ]', $native);
        $t->contains('Para [ Str "Ask" , Space , Str "about" , Space , Str "migration" , Space , Str "risks." ]', $native);
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
