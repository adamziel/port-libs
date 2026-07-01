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
