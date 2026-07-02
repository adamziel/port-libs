<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocJsonWriter;
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
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdApp" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
  <Relationship Id="rIdCustom" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties" Target="docProps/custom.xml"/>
</Relationships>
XML);
    $zip->addFromString('docProps/core.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
                   xmlns:dc="http://purl.org/dc/elements/1.1/"
                   xmlns:dcterms="http://purl.org/dc/terms/"
                   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>Reader Review Deck</dc:title>
  <dc:creator>Ada Reviewer</dc:creator>
  <dc:subject>PPTX reader parity</dc:subject>
  <cp:keywords>pptx, parity, review</cp:keywords>
  <dc:description>Review-only document property extraction.</dc:description>
  <cp:category>Engineering</cp:category>
  <dcterms:created xsi:type="dcterms:W3CDTF">2026-07-01T00:00:00Z</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">2026-07-01T01:02:03Z</dcterms:modified>
</cp:coreProperties>
XML);
    $zip->addFromString('docProps/app.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
  <Application>PowerPoint</Application>
  <PresentationFormat>On-screen Show (16:9)</PresentationFormat>
  <Slides>5</Slides>
  <Notes>1</Notes>
  <HiddenSlides>0</HiddenSlides>
  <Company>Port Libs</Company>
  <LinksUpToDate>false</LinksUpToDate>
</Properties>
XML);
    $zip->addFromString('docProps/custom.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties"
            xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="2" name="ReviewStatus"><vt:lpwstr>current</vt:lpwstr></property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="3" name="Reviewed"><vt:bool>true</vt:bool></property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="4" name="Priority"><vt:i4>7</vt:i4></property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="5" name="ReviewStatus"><vt:lpwstr>duplicate ignored by byName</vt:lpwstr></property>
</Properties>
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

$buildSlideBackgroundPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-bg-');
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
  <p:cSld>
    <p:bg><p:bgPr><a:blipFill><a:blip r:embed="rIdBackground"/></a:blipFill></p:bgPr></p:bg>
    <p:spTree>
      <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
      <p:grpSpPr/>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Background image</a:t></a:r></a:p></p:txBody>
      </p:sp>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="3" name="Body 1"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Visible body</a:t></a:r></a:p></p:txBody>
      </p:sp>
    </p:spTree>
  </p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdBackground" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/background.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/background.png', 'background-image-bytes');
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

$buildTableGraphicWithoutTablePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-table-no-tbl-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Missing table child</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="8" name="Table Without Tbl"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"/></a:graphic>
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

$buildEmptyHeaderTablePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-header-table-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Empty header table</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="8" name="Empty Header Table"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl>
        <a:tblPr/>
        <a:tblGrid><a:gridCol w="1828800"/><a:gridCol w="1828800"/></a:tblGrid>
        <a:tr/>
        <a:tr>
          <a:tc><a:txBody><a:p><a:r><a:t>Body A</a:t></a:r></a:p></a:txBody></a:tc>
          <a:tc><a:txBody><a:p><a:r><a:t>Body B</a:t></a:r></a:p></a:txBody></a:tc>
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
        <a:tr><a:tc/><a:tc><a:txBody><a:p><a:r><a:t>Filled</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
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

$buildParagraphlessTextBodyPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-paragraphless-text-body-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Paragraphless text body</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Paragraphless Text Box"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/></p:txBody>
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

$buildUntypedImageRelationshipPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-untyped-image-rel-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Untyped image relationship</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Untyped Picture" descr="Untyped alt"/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Target="../media/untyped.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/untyped.png', 'untyped-image-bytes');
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

$buildQualifiedPictureMetadataPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-qualified-picture-metadata-');
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
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
       xmlns:q="urn:qualified-picture-metadata">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Qualified picture metadata</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" q:name="Qualified Picture" q:descr="Qualified alt"/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/qualified-picture-metadata.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/qualified-picture-metadata.png', 'qualified-picture-metadata-image-bytes');
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

$buildPercentEncodedImageTargetPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-percent-image-target-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Percent image</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Percent Picture" descr="Percent alt"/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/space%20image.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/space image.png', 'percent-decoded-image-bytes');
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

$buildCenteredTitlePlaceholderPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-centered-title-placeholder-');
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
      <p:nvSpPr><p:cNvPr id="2" name="Centered Title"/><p:cNvSpPr/><p:nvPr><p:ph type="ctrTitle"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Centered title placeholder</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Visible centered-title body</a:t></a:r></a:p></p:txBody>
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

$buildFirstEmptyTitlePlaceholderPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-first-empty-title-placeholder-');
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
      <p:nvSpPr><p:cNvPr id="2" name="Empty First Title"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p/></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Second Title"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Second title should be hidden</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="4" name="Body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Body remains visible</a:t></a:r></a:p></p:txBody>
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

$buildMalformedNvSpPrTitlePicturePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-malformed-nvsppr-title-picture-');
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
    <p:pic>
      <p:nvSpPr><p:cNvPr id="8" name="Malformed Title Picture"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:nvPicPr><p:cNvPr id="9" name="Visible If Not Skipped" descr="Should not be visible"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Malformed picture title</a:t></a:r></a:p></p:txBody>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/malformed-title-picture.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/malformed-title-picture.png', 'malformed-title-picture-bytes');
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

$buildWrongPrefixShapeRelationshipsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-wrong-prefix-shape-rels-');
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
       xmlns:r="urn:not-office-relationships"
       xmlns:rel="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Wrong prefix shapes</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Wrong Prefix Picture" descr="Wrong prefix alt"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>
      <p:blipFill><a:blip rel:embed="rIdImage"/></p:blipFill>
    </p:pic>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Wrong Prefix SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds rel:dm="rIdData" rel:lo="rIdLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/wrong-prefix.png"/>
  <Relationship Id="rIdData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="../diagrams/data1.xml"/>
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="../diagrams/layout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/wrong-prefix.png', 'wrong-prefix-image-bytes');
    $zip->addFromString('ppt/diagrams/layout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/wrongPrefixLayout"/>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Wrong prefix parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Wrong prefix child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildIntermediatePrefixShapeRelationshipsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-intermediate-prefix-shape-rels-');
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
       xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Intermediate prefix shapes</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
      <p:nvPicPr><p:cNvPr id="7" name="Intermediate Prefix Picture" descr="Intermediate prefix alt"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
    <p:graphicFrame xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Intermediate Prefix SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds r:dm="rIdData" r:lo="rIdLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/intermediate-prefix.png"/>
  <Relationship Id="rIdData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="../diagrams/data1.xml"/>
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="../diagrams/layout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/intermediate-prefix.png', 'intermediate-prefix-image-bytes');
    $zip->addFromString('ppt/diagrams/layout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/intermediatePrefixLayout"/>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Intermediate prefix parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Intermediate prefix child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildShapeTreePrefixShapeRelationshipsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-sp-tree-prefix-shape-rels-');
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
       xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram">
  <p:cSld><p:spTree xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Shape tree prefix relationships</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Shape Tree Prefix Picture" descr="Shape tree prefix alt"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Shape Tree Prefix SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds r:dm="rIdData" r:lo="rIdLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/sp-tree-prefix.png"/>
  <Relationship Id="rIdData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="../diagrams/data1.xml"/>
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="../diagrams/layout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/sp-tree-prefix.png', 'sp-tree-prefix-image-bytes');
    $zip->addFromString('ppt/diagrams/layout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/spTreePrefixLayout"/>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Shape tree prefix parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Shape tree prefix child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildLocalPrefixShapeRelationshipsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-local-prefix-shape-rels-');
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
       xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Local prefix relationships</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Local Prefix Picture" descr="Local prefix alt"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>
      <p:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rIdImage"/></p:blipFill>
    </p:pic>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Local Prefix SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:dm="rIdData" r:lo="rIdLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/local-prefix.png"/>
  <Relationship Id="rIdData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="../diagrams/data1.xml"/>
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="../diagrams/layout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/local-prefix.png', 'local-prefix-image-bytes');
    $zip->addFromString('ppt/diagrams/layout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/localPrefixLayout"/>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Local prefix parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Local prefix child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildDuplicateImageRelationshipPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-duplicate-image-rel-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Duplicate image relationship</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Duplicate Picture" descr="Duplicate alt"/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/missing-first.png"/>
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/second.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/second.png', 'second-image-bytes');
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

$buildPictureWithoutBlipPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-picture-no-blip-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Pictures without blips</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Missing BlipFill Picture" descr="Missing blipFill alt"/></p:nvPicPr>
    </p:pic>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="8" name="Missing Blip Picture" descr="Missing blip alt"/></p:nvPicPr>
      <p:blipFill><a:stretch/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/unreferenced-no-blip.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/unreferenced-no-blip.png', 'unreferenced-no-blip-image-bytes');
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

$buildExternalModeEmbeddedImagePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-external-mode-embedded-image-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>External mode embedded image</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="External Mode Picture" descr="External mode alt"/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/external-mode.png" TargetMode="External"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/external-mode.png', 'external-mode-image-bytes');
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

$buildWingdingsTypefaceCasePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-wingdings-case-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Wingdings case slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Wingdings case body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:rPr><a:sym typeface="wingdings"/></a:rPr><a:t>Lowercase wingdings stays plain</a:t></a:r></a:p>
        <a:p><a:r><a:rPr><a:sym typeface="WINGDINGS"/></a:rPr><a:t>Uppercase WINGDINGS stays plain</a:t></a:r></a:p>
        <a:p><a:r><a:rPr><a:sym typeface="Wingdings 2"/></a:rPr><a:t>Title case Wingdings bullet</a:t></a:r></a:p>
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

$buildEmptyBulletParagraphPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-bullet-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Empty bullet paragraphs</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body 1"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr><a:buChar char="&#8226;"/></a:pPr></a:p>
        <a:p><a:pPr><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Visible bullet</a:t></a:r></a:p>
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

$buildSignedBulletLevelPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-signed-bullet-level-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Signed level slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Signed level body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr lvl="-1"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Negative level bullet</a:t></a:r></a:p>
        <a:p><a:pPr lvl="0"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Zero level bullet</a:t></a:r></a:p>
        <a:p><a:pPr lvl="+1"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Plus level bullet</a:t></a:r></a:p>
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

$buildUnicodeWhitespaceBulletLevelPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-unicode-whitespace-bullet-level-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Unicode whitespace level slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Unicode whitespace level body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr lvl="&#160;1&#160;"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Unicode level one</a:t></a:r></a:p>
        <a:p><a:pPr lvl="0"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Zero level middle</a:t></a:r></a:p>
        <a:p><a:pPr lvl="&#160;1&#160;"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Unicode level one again</a:t></a:r></a:p>
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

$buildParenthesizedBulletLevelPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-parenthesized-bullet-level-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Parenthesized level slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Parenthesized level body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr lvl="(1)"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Parenthesized level one</a:t></a:r></a:p>
        <a:p><a:pPr lvl="0"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Zero level separator</a:t></a:r></a:p>
        <a:p><a:pPr lvl="((1))"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Nested parenthesized level one</a:t></a:r></a:p>
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

$buildBasedBulletLevelPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-based-bullet-level-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Based level slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Based level body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr lvl="0x1"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Hex level one</a:t></a:r></a:p>
        <a:p><a:pPr lvl="0"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Decimal zero separator</a:t></a:r></a:p>
        <a:p><a:pPr lvl="0o1"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Octal level one</a:t></a:r></a:p>
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

$buildOverflowBulletLevelPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-overflow-bullet-level-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Overflow level slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Overflow level body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr lvl="9223372036854775807"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Max int level</a:t></a:r></a:p>
        <a:p><a:pPr lvl="-9223372036854775808"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Min int level</a:t></a:r></a:p>
        <a:p><a:pPr lvl="9223372036854775808"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Overflow level fallback</a:t></a:r></a:p>
        <a:p><a:pPr lvl="-9223372036854775809"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Negative overflow level fallback</a:t></a:r></a:p>
        <a:p><a:pPr lvl="0"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Explicit zero joins fallback</a:t></a:r></a:p>
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

$buildInvalidSmartArtDataXmlPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-invalid-smartart-data-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Invalid SmartArt XML</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Invalid SmartArt Frame"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:ptLst>
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

$buildWhitespaceOnlySmartArtTextPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-whitespace-smartart-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Whitespace SmartArt</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Whitespace SmartArt Frame"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>&#160;</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Hidden child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildRootRelativeSmartArtTargetPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-root-smartart-target-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root SmartArt</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Root SmartArt Frame"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds r:dm="rIdData" r:lo="rIdLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="/ppt/diagrams/data1.xml"/>
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="/ppt/diagrams/layout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/diagrams/layout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/rootLayout"/>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Root target parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Root target child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildEmptyTypeSmartArtConnectionPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-type-smartart-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Empty type SmartArt</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Empty Type SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId=""><dgm:t><a:p><a:r><a:t>Empty id parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Empty type child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn type="" srcId="" destId="child"/>
  </dgm:cxnLst>
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

$buildRootPrefixSmartArtPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-root-prefix-smartart-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root prefix SmartArt</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Root Prefix SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
<dgm:layoutDef xmlns:dgm="urn:not-diagram"><dgm:title val="rootPrefixLayout"/></dgm:layoutDef>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="urn:not-diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Root prefix parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Root prefix child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildEmptyUniqueIdSmartArtLayoutPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-layout-uid-smartart-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Empty layout id SmartArt</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Empty Layout Id SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId=""><dgm:title val="fallbackTitleShouldNotWin"/></dgm:layoutDef>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Empty uniqueId parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Empty uniqueId child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildTrailingSlashUniqueIdSmartArtLayoutPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-trailing-layout-uid-smartart-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Trailing layout id SmartArt</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Trailing Layout Id SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/"><dgm:title val="fallbackTitleShouldNotWin"/></dgm:layoutDef>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Trailing uniqueId parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Trailing uniqueId child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildDuplicateModelIdSmartArtPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-duplicate-modelid-smartart-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Duplicate modelId SmartArt</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Duplicate modelId SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Stale duplicate parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Stale duplicate child</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Latest duplicate parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Latest duplicate child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildUntypedSmartArtRelationshipsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-untyped-smartart-rels-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Untyped SmartArt relationships</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Untyped SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds r:dm="rIdData" r:lo="rIdLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdData" Target="../diagrams/data1.xml"/>
  <Relationship Id="rIdLayout" Target="../diagrams/layout1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/diagrams/layout1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="urn:microsoft.com/office/officeart/2005/8/layout/basicBlockList"/>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Untyped SmartArt parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Untyped SmartArt child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildFilteredSmartArtConnectionsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-filtered-smartart-cxns-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Filtered SmartArt connections</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Filtered SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Visible parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Visible child</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="orphan"><dgm:t><a:p><a:r><a:t>Orphan text</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="typedParent"><dgm:t><a:p><a:r><a:t>Typed parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="typedChild"><dgm:t><a:p><a:r><a:t>Typed child</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="endpointParent"><dgm:t><a:p><a:r><a:t>Endpoint parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="endpointChild"><dgm:t><a:p><a:r><a:t>Endpoint child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
    <dgm:cxn type="parOf" srcId="typedParent" destId="typedChild"/>
    <dgm:cxn srcId="endpointParent"/>
    <dgm:cxn destId="endpointChild"/>
  </dgm:cxnLst>
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

$buildOrderedSmartArtConnectionsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-ordered-smartart-cxns-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Ordered SmartArt connections</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Ordered SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="zParent"><dgm:t><a:p><a:r><a:t>Second sorted parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="zChild"><dgm:t><a:p><a:r><a:t>Second sorted child</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="aParent"><dgm:t><a:p><a:r><a:t>First sorted parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="betaChild"><dgm:t><a:p><a:r><a:t>Beta connection child</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="alphaChild"><dgm:t><a:p><a:r><a:t>Alpha connection child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="zParent" destId="zChild"/>
    <dgm:cxn srcId="aParent" destId="betaChild"/>
    <dgm:cxn srcId="aParent" destId="alphaChild"/>
  </dgm:cxnLst>
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

$buildSmartArtAllDescendantTextPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-smartart-descendant-text-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>SmartArt descendant text</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="SmartArt Descendant Text"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:x="urn:foreign-smartart-text">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t>Direct parent <x:branch>foreign parent</x:branch><a:p><a:r><a:t>drawing parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><x:branch>foreign child</x:branch><a:p><a:r><a:t>drawing child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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

$buildUnknownLayoutSmartArtPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-unknown-layout-smartart-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Unknown layout SmartArt</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Unknown Layout SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
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
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>
XML);
    $zip->addFromString('ppt/diagrams/data1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <dgm:ptLst>
    <dgm:pt modelId="parent"><dgm:t><a:p><a:r><a:t>Unknown layout parent</a:t></a:r></a:p></dgm:t></dgm:pt>
    <dgm:pt modelId="child"><dgm:t><a:p><a:r><a:t>Unknown layout child</a:t></a:r></a:p></dgm:t></dgm:pt>
  </dgm:ptLst>
  <dgm:cxnLst>
    <dgm:cxn srcId="parent" destId="child"/>
  </dgm:cxnLst>
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
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdEmptyData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target=""/>
  <Relationship Id="rIdEmptyLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target=""/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"
       xmlns:bad="urn:example:wrong-relids-namespace"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Graphic placeholders</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="9" name="Missing GraphicData"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic/>
    </p:graphicFrame>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="10" name="No URI Graphic"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData/></a:graphic>
    </p:graphicFrame>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="16" name="Empty URI Graphic"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri=""/></a:graphic>
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
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="18" name="Wrong Namespace RelIds"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><bad:relIds r:dm="rIdMissingWrongData" r:lo="rIdMissingWrongLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="19" name="Empty Target SmartArt"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds r:dm="rIdEmptyData" r:lo="rIdEmptyLayout"/></a:graphicData></a:graphic>
    </p:graphicFrame>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="14" name="Chart Diagram URI"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart-diagram"/></a:graphic>
    </p:graphicFrame>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="15" name="Table Diagram URI"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table-diagram"><dgm:relIds/></a:graphicData></a:graphic>
    </p:graphicFrame>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="17" name="Uppercase Table URI"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/TABLE"><a:tbl>
        <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Uppercase URI table cell</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
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

$buildNamespaceAgnosticDrawingTextPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-raw-t-text-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Namespace agnostic text</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body 1"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/>
        <a:p><a:r><a:t>Drawing text</a:t></a:r><bad:r><bad:t>Foreign text</bad:t></bad:r></a:p>
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

$buildRootPrefixNamespaceShapePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-root-prefix-ns-shape-');
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
<p:sld xmlns:p="urn:not-presentationml"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root prefix namespace title</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body 1"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root prefix namespace body</a:t></a:r></a:p></p:txBody>
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

$buildShadowedSlidePrefixPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-shadowed-slide-prefix-');
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
<p:sld xmlns:p="urn:not-presentationml"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
    <p:spTree>
      <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
      <p:grpSpPr/>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Shadowed slide prefix title</a:t></a:r></a:p></p:txBody>
      </p:sp>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="3" name="Body 1"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Shadowed slide prefix body</a:t></a:r></a:p></p:txBody>
      </p:sp>
    </p:spTree>
  </p:cSld>
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

$buildShadowedDrawingPrefixPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-shadowed-drawing-prefix-');
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
       xmlns:a="urn:not-drawingml">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Shadowed drawing prefix title</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body 1"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Shadowed drawing prefix body</a:t></a:r></a:p></p:txBody>
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

$buildInheritedParagraphPropertiesPrefixPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-inherited-paragraph-prefix-');
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
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Inherited paragraph prefix</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body 1"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:bodyPr/><a:lstStyle/>
        <a:p><a:pPr lvl="3"><a:buChar char="&#8226;"/></a:pPr><a:r><a:t>Inherited bullet metadata</a:t></a:r></a:p>
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

$buildShadowedDrawingMediaPrefixPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-shadowed-drawing-media-prefix-');
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
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
       xmlns:a="urn:not-drawingml">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Shadowed drawing media title</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="7" name="Shadowed Drawing Picture" descr="Shadowed drawing alt"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>
      <p:blipFill xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
    <p:graphicFrame>
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Shadowed Drawing Table"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
        <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table">
          <a:tbl>
            <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Shadowed table header</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
            <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Shadowed table body</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
          </a:tbl>
        </a:graphicData>
      </a:graphic>
    </p:graphicFrame>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/shadowed-drawing.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/shadowed-drawing.png', 'shadowed-drawing-image-bytes');
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

$buildInheritedTablePrefixPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-inherited-table-prefix-');
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
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Inherited table prefix</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:graphicFrame xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
      <p:nvGraphicFramePr><p:cNvPr id="20" name="Inherited Prefix Table"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
      <a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
        <a:graphicData xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" uri="http://schemas.openxmlformats.org/drawingml/2006/table">
          <a:tbl>
            <a:tblGrid><a:gridCol w="1828800"/></a:tblGrid>
            <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Inherited table header</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
            <a:tr><a:tc><a:txBody><a:p><a:r><a:t>Inherited table body</a:t></a:r></a:p></a:txBody></a:tc></a:tr>
          </a:tbl>
        </a:graphicData>
      </a:graphic>
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

$buildSlideLessPresentationPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-slideless-');
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
  <p:sldSz cx="12192000" cy="6858000"/>
</p:presentation>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Unreferenced slide body</a:t></a:r></a:p></p:txBody>
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

$buildInvalidSlideSizePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-invalid-slide-size-');
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
  <p:sldSz cx="wide" cy="tall"/>
</p:presentation>
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

$buildPartialSlideSizePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-partial-slide-size-');
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
  <p:sldSz cx="12192000"/>
</p:presentation>
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

$buildShadowedPresentationPrefixPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-shadowed-presentation-prefix-');
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
<p:presentation xmlns:p="urn:not-presentationml"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
  <p:sldSz xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" cx="12192000" cy="6858000"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Shadowed presentation prefix body</a:t></a:r></a:p></p:txBody>
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

$buildAlternateRootPresentationAndSlidePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-alternate-roots-');
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
<p:notPresentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                   xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldSz cx=" 12192000 " cy=" 6858000 "/>
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:notPresentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:notSlide xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
            xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Alternate root title</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body 1"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Alternate root body</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:notSlide>
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

$buildWrongPrefixPresentationRelationshipPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-wrong-prefix-presentation-rid-');
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
                xmlns:r="urn:not-office-relationships"
                xmlns:rel="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" rel:id="rIdSlide"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Wrong prefix relationship body</a:t></a:r></a:p></p:txBody>
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

$buildIntermediatePrefixPresentationRelationshipPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-intermediate-prefix-presentation-rid-');
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
  <p:sldIdLst xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Intermediate prefix relationship body</a:t></a:r></a:p></p:txBody>
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

$buildRootOfficeDocumentAliasPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-root-office-doc-alias-');
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
  <OfficeDocumentAlias Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root officeDocument alias</a:t></a:r></a:p></p:txBody>
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

$buildSuffixRootOfficeDocumentTypePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-suffix-root-office-doc-type-');
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
  <Relationship Id="rIdPresentation" Type="urn:example:pptx-reader/officeDocument" Target="ppt/presentation.xml"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Suffix officeDocument type</a:t></a:r></a:p></p:txBody>
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

$buildFirstMissingTargetRootOfficeDocumentPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-first-missing-target-root-office-doc-');
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
  <Relationship Id="rIdFirst" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"/>
  <Relationship Id="rIdSecond" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>
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

$buildQualifiedRootOfficeDocumentTypePptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-qualified-root-office-doc-type-');
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
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"
               xmlns:q="urn:qualified-relationship-attributes">
  <Relationship Id="rIdPresentation" q:Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Qualified root type must not read</a:t></a:r></a:p></p:txBody>
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

$buildQualifiedRootOfficeDocumentTargetPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-qualified-root-office-doc-target-');
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
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"
               xmlns:q="urn:qualified-relationship-attributes">
  <Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" q:Target="ppt/presentation.xml"/>
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

$buildRootOfficeDocumentWithoutIdPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-root-office-doc-no-id-');
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
  <Relationship Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root relationship without id</a:t></a:r></a:p></p:txBody>
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

$buildExternalModeRootOfficeDocumentPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-root-office-doc-external-mode-');
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
  <Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml" TargetMode="External"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root TargetMode ignored</a:t></a:r></a:p></p:txBody>
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

$buildRootLevelPresentationRelationshipPartPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-root-level-presentation-rels-');
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
  <Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('presentation.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('_rels/presentation.xml.rels', <<<'XML'
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root level presentation sidecar</a:t></a:r></a:p></p:txBody>
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

$buildEmptyPresentationTargetPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-presentation-target-');
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
  <Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target=""/>
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

$buildRootRelativePresentationTargetPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-root-relative-presentation-target-');
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
  <Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/ppt/presentation.xml"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Root-relative presentation target</a:t></a:r></a:p></p:txBody>
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

$buildEmptySlideTargetPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-slide-target-');
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
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target=""/>
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

$buildDuplicateSlideRelationshipPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-duplicate-slide-rel-');
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
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/missing-first.xml"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Duplicate slide relationship should not win</a:t></a:r></a:p></p:txBody>
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

$buildExternalModeSlideRelationshipPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-external-mode-slide-');
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
    <p:sldId id="461" r:id="rIdSlide"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml" TargetMode="External"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>External mode slide</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>TargetMode is ignored</a:t></a:r></a:p></p:txBody>
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

$buildInvalidReviewSidecarTargetsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-invalid-review-targets-');
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
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target=""/>
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
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/tableStyles" Target=""/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
       xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld>
    <p:bg><p:bgPr><a:blipFill><a:blip r:link="rIdBg"/></a:blipFill></p:bgPr></p:bg>
    <p:spTree>
      <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
      <p:grpSpPr/>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Review targets</a:t></a:r></a:p></p:txBody>
      </p:sp>
      <p:sp>
        <p:nvSpPr><p:cNvPr id="3" name="Body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
        <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Review sidecars stay optional</a:t></a:r></a:p></p:txBody>
      </p:sp>
      <p:graphicFrame>
        <p:nvGraphicFramePr><p:cNvPr id="4" name="Broken chart metadata"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>
        <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" r:id="rIdChart"/></a:graphicData></a:graphic>
      </p:graphicFrame>
      <p:pic>
        <p:nvPicPr>
          <p:cNvPr id="5" name="Linked Picture" descr="Linked alt"/>
          <p:cNvPicPr/>
          <p:nvPr><a:videoFile r:link="rIdVideo"/></p:nvPr>
        </p:nvPicPr>
        <p:blipFill><a:blip r:link="rIdLinkedImage"/></p:blipFill>
      </p:pic>
    </p:spTree>
  </p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target=""/>
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target=""/>
  <Relationship Id="rIdNotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide" Target=""/>
  <Relationship Id="rIdBg" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target=""/>
  <Relationship Id="rIdChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target=""/>
  <Relationship Id="rIdLinkedImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target=""/>
  <Relationship Id="rIdVideo" Type="http://schemas.microsoft.com/office/2007/relationships/media" Target=""/>
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

$buildInvalidReviewRelationshipSidecarPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-invalid-review-rels-');
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Invalid review rels</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Visible body survives</a:t></a:r></a:p></p:txBody>
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
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="4" name="Layout body"/><p:cNvSpPr/><p:nvPr><p:ph type="body" idx="1"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Layout review text</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sldLayout>
XML);
    $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMaster" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"
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

$buildNonRelationshipChildRelationshipsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-non-relationship-children-');
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
  <OfficeDocumentAlias Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
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
  <SlideAlias Id="rIdSlide" Target="slides/slide1.xml"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Relationship child names</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="3" name="Odd Child Picture" descr="Odd child alt"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <ImageAlias Id="rIdImage" Target="../media/non-relationship-child.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/non-relationship-child.png', 'non-relationship-child-image-bytes');
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

$buildUnqualifiedRelationshipAttributesPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-unqualified-rel-attrs-');
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
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"
               xmlns:q="urn:qualified-relationship-attributes">
  <Relationship q:Id="rIdQualifiedPresentation" q:Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" q:Target="ppt/qualified-presentation.xml"/>
  <Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
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
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"
               xmlns:q="urn:qualified-relationship-attributes">
  <Relationship q:Id="rIdSlide" Target="slides/qualified-id.xml"/>
  <Relationship Id="rIdSlide" q:Target="slides/qualified-target.xml"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Unqualified relationship attributes</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:pic>
      <p:nvPicPr><p:cNvPr id="3" name="Unqualified Attribute Picture" descr="Unqualified attribute alt"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr>
      <p:blipFill><a:blip r:embed="rIdImage"/></p:blipFill>
    </p:pic>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"
               xmlns:q="urn:qualified-relationship-attributes">
  <Relationship q:Id="rIdImage" Target="../media/qualified-id.png"/>
  <Relationship Id="rIdImage" q:Target="../media/qualified-target.png"/>
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/unqualified-relationship-attribute.png"/>
</Relationships>
XML);
    $zip->addFromString('ppt/media/unqualified-relationship-attribute.png', 'unqualified-relationship-attribute-image-bytes');
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

$buildEmptyRelationshipIdPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-empty-rid-');
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
    <p:sldId id="461" r:id=""/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Empty relationship id</a:t></a:r></a:p></p:txBody>
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

$buildIgnoredPresentationSlideIdsPptxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-ignored-slide-ids-');
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
    <p:sldId id="not-an-int" r:id="rIdSlideOne"/>
    <p:sldId r:id="rIdSlideTwo"/>
  </p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSlideOne" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
  <Relationship Id="rIdSlideTwo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide2.xml"/>
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
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Invalid numeric slide id</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->addFromString('ppt/slides/slide2.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"
       xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
    <p:grpSpPr/>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title 1"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Missing numeric slide id</a:t></a:r></a:p></p:txBody>
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
        $t->same('docProps/core.xml', $review['documentProperties']['core']['partName'] ?? null);
        $t->same(true, $review['documentProperties']['core']['exists'] ?? null);
        $t->same('Reader Review Deck', $review['documentProperties']['core']['values']['title'] ?? null);
        $t->same('Ada Reviewer', $review['documentProperties']['core']['values']['creator'] ?? null);
        $t->same('2026-07-01T01:02:03Z', $review['documentProperties']['core']['values']['modified'] ?? null);
        $t->same('docProps/app.xml', $review['documentProperties']['extended']['partName'] ?? null);
        $t->same(5, $review['documentProperties']['extended']['values']['slides'] ?? null);
        $t->same(1, $review['documentProperties']['extended']['values']['notes'] ?? null);
        $t->same(false, $review['documentProperties']['extended']['values']['linksUpToDate'] ?? null);
        $t->same('docProps/custom.xml', $review['documentProperties']['custom']['partName'] ?? null);
        $t->same(4, $review['documentProperties']['custom']['count'] ?? null);
        $t->same(1, $review['documentProperties']['custom']['duplicateNameCount'] ?? null);
        $t->same(['ReviewStatus'], $review['documentProperties']['custom']['duplicateNames'] ?? null);
        $t->same('current', $review['documentProperties']['custom']['byName']['ReviewStatus'] ?? null);
        $t->same(true, $review['documentProperties']['custom']['byName']['Reviewed'] ?? null);
        $t->same(7, $review['documentProperties']['custom']['byName']['Priority'] ?? null);
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
        $t->true(!str_contains($native, 'Reader Review Deck'), 'PPTX document properties should remain review-only');
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

    'records pptx slide background image references without visible content' => static function (TestRunner $t) use ($buildSlideBackgroundPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildSlideBackgroundPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesOfType($document, 'image'));
        $t->same('Background image', $document->children[0]->attr('text'));
        $t->same('Visible body', $document->children[1]->attr('text'));
        $t->same(1, $review['slides'][0]['backgroundCount'] ?? null);
        $t->same('rIdBackground', $review['slides'][0]['backgrounds'][0]['relationshipId'] ?? null);
        $t->same('embed', $review['slides'][0]['backgrounds'][0]['relationshipAttribute'] ?? null);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', $review['slides'][0]['backgrounds'][0]['relationshipType'] ?? null);
        $t->same('../media/background.png', $review['slides'][0]['backgrounds'][0]['target'] ?? null);
        $t->same('ppt/media/background.png', $review['slides'][0]['backgrounds'][0]['partName'] ?? null);
        $t->same(true, $review['slides'][0]['backgrounds'][0]['exists'] ?? null);
        $t->same([], $review['slides'][0]['backgrounds'][0]['issues'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Background" , Space , Str "image" ]', $native);
        $t->true(!str_contains($native, 'background.png'), 'PPTX slide background images should remain review-only');
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

    'skips pptx table graphic frames without table children like upstream' => static function (TestRunner $t) use ($buildTableGraphicWithoutTablePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildTableGraphicWithoutTablePptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesOfType($document, 'table'));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->true(!str_contains($native, 'Table Without Tbl'), 'Table graphic frame names should not leak into visible content');
        $t->true(!str_contains($native, '[Graphic:'), 'Table graphic frames without a:tbl should be skipped rather than replaced with a graphic placeholder');
        $t->true(!str_contains($native, 'Table'), 'PPTX table graphic frames without a:tbl should not emit a native Table block');
    },

    'uses first pptx table row for native column specs like upstream' => static function (TestRunner $t) use ($buildEmptyHeaderTablePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildEmptyHeaderTablePptxPackage());
        $tables = $nodesOfType($document, 'table');
        $native = PandocConverter::write($document, 'native');
        $json = (new PandocJsonWriter())->toArray($document);
        $jsonTable = $json['blocks'][1] ?? [];

        $t->same(1, count($tables));
        $t->same([], $tables[0]->attr('alignments'));
        $t->same(0, $tables[0]->attr('nativeColumnCount'));
        $t->same([1828800, 1828800], $tables[0]->attr('columnWidths'));
        $t->same('Table', is_array($jsonTable) ? ($jsonTable['t'] ?? null) : null);
        $t->same([], is_array($jsonTable) ? ($jsonTable['c'][2] ?? null) : null);
        $t->same(0, count($tables[0]->children[0]->children[0]->children));
        $t->same('Body A', $tables[0]->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Body B', $tables[0]->children[1]->children[0]->children[1]->attr('text'));
        $t->contains('Table ( "" , [  ] , [  ] ) (Caption Nothing []) []', $native);
        $t->contains('TableHead ( "" , [  ] , [  ] ) [ Row ( "" , [  ] , [  ] ) [  ] ])', $native);
        $t->contains('Str "Body" , Space , Str "A"', $native);
        $t->contains('Str "Body" , Space , Str "B"', $native);
        $t->true(!str_contains($native, 'ColWidthDefault'), 'Empty-header PPTX table should not synthesize native column specs from body rows');
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
        $filledCell = $tables[0]->children[0]->children[0]->children[1] ?? null;
        $emptyParagraphInline = $emptyParagraphs[0]->children[0] ?? null;
        $emptyCellInline = $emptyCell instanceof AstNode ? ($emptyCell->children[0]->children[0] ?? null) : null;

        $t->same(1, count($emptyParagraphs));
        $t->same('', $emptyParagraphInline instanceof AstNode ? $emptyParagraphInline->attr('text') : null);
        $t->same('text', $emptyParagraphInline instanceof AstNode ? $emptyParagraphInline->type : null);
        $t->same(1, count($tables));
        $t->same('', $emptyCell instanceof AstNode ? $emptyCell->attr('text') : null);
        $t->same('text', $emptyCellInline instanceof AstNode ? $emptyCellInline->type : null);
        $t->same('', $emptyCellInline instanceof AstNode ? $emptyCellInline->attr('text') : null);
        $t->same('Filled', $filledCell instanceof AstNode ? $filledCell->attr('text') : null);
        $t->contains('Para [  ]', $native);
        $t->contains('[ Plain [  ]', $native);
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
    },

    'skips pptx text boxes without drawing paragraphs like upstream' => static function (TestRunner $t) use ($buildParagraphlessTextBodyPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildParagraphlessTextBodyPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same('Paragraphless text body', $document->children[0]->attr('text'));
        $t->same([], $nodesOfType($document, 'paragraph'));
        $t->same(1, $review['slides'][0]['blockCount'] ?? null);
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->true(!str_contains($native, 'Para [  ]'), 'Text bodies without a:p should be skipped, unlike explicit empty a:p paragraphs');
        $t->true(!str_contains($native, 'Paragraphless Text Box'), 'Skipped text box shape names should not leak into visible output');
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

    'keeps pptx image relationships without Type usable like upstream' => static function (TestRunner $t) use ($buildUntypedImageRelationshipPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildUntypedImageRelationshipPptxPackage());
        $review = $document->attr('pptx');
        $images = $nodesOfType($document, 'image');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($images));
        $t->same('ppt/media/untyped.png', $images[0]->attr('url'));
        $t->same('Untyped Picture', $images[0]->attr('title'));
        $t->same('Untyped alt', $images[0]->attr('alt'));
        $t->same('rIdImage', $images[0]->attr('relationshipId'));
        $t->same('embed', $images[0]->attr('relationshipAttribute'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Image ( "" , [  ] , [  ] ) [ Str "Untyped" , Space , Str "alt" ] ( "ppt/media/untyped.png" , "Untyped Picture" )', $native);
    },

    'uses only unqualified pptx picture metadata attributes like upstream' => static function (TestRunner $t) use ($buildQualifiedPictureMetadataPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildQualifiedPictureMetadataPptxPackage());
        $review = $document->attr('pptx');
        $images = $nodesOfType($document, 'image');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($images));
        $t->same('ppt/media/qualified-picture-metadata.png', $images[0]->attr('url'));
        $t->same('', $images[0]->attr('title'));
        $t->same('', $images[0]->attr('alt'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Image ( "" , [  ] , [  ] ) [  ] ( "ppt/media/qualified-picture-metadata.png" , "" )', $native);
        $t->true(!str_contains($native, 'Qualified Picture'), 'Qualified picture name should not become a native image title');
        $t->true(!str_contains($native, 'Qualified alt'), 'Qualified picture descr should not become native image alt text');
    },

    'uses upstream literal pptx image targets without percent-decoding' => static function (TestRunner $t) use ($buildPercentEncodedImageTargetPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildPercentEncodedImageTargetPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesOfType($document, 'image'));
        $t->same(1, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same('missing-image-part', $review['slides'][0]['imageIssues'][0]['issue'] ?? null);
        $t->same('../media/space%20image.png', $review['slides'][0]['imageIssues'][0]['target'] ?? null);
        $t->same('ppt/media/space%20image.png', $review['slides'][0]['imageIssues'][0]['partName'] ?? null);
        $t->true(!str_contains($native, 'Image'), 'Percent-encoded PPTX image target should stay missing when only decoded entry exists');
        $t->true(!str_contains($native, 'space image.png'), 'Decoded package entry should not become visible for an upstream-literal target');
    },

    'uses the first duplicate pptx relationship id like upstream' => static function (TestRunner $t) use ($buildDuplicateImageRelationshipPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildDuplicateImageRelationshipPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');
        $issue = $review['slides'][0]['imageIssues'][0] ?? [];

        $t->same([], $nodesOfType($document, 'image'));
        $t->same(1, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same('missing-image-part', $issue['issue'] ?? null);
        $t->same('rIdImage', $issue['relationshipId'] ?? null);
        $t->same('../media/missing-first.png', $issue['target'] ?? null);
        $t->same('ppt/media/missing-first.png', $issue['partName'] ?? null);
        $t->true(!str_contains($native, 'Image'), 'Duplicate relationship IDs should not let a later image target become visible');
        $t->true(!str_contains($native, 'ppt/media/second.png'), 'Later duplicate relationship target should not override the first target');
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

    'uses centered pptx title placeholders like upstream' => static function (TestRunner $t) use ($buildCenteredTitlePlaceholderPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildCenteredTitlePlaceholderPptxPackage());
        $review = $document->attr('pptx');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $nodesOfType($document, 'paragraph'));
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Centered title placeholder', $document->children[0]->attr('text'));
        $t->same(['Visible centered-title body'], $paragraphTexts);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Centered" , Space , Str "title" , Space , Str "placeholder" ]', $native);
        $t->contains('Para [ Str "Visible" , Space , Str "centered-title" , Space , Str "body" ]', $native);
    },

    'uses the first empty pptx title placeholder like upstream' => static function (TestRunner $t) use ($buildFirstEmptyTitlePlaceholderPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildFirstEmptyTitlePlaceholderPptxPackage());
        $review = $document->attr('pptx');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $nodesOfType($document, 'paragraph'));
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Slide 1', $document->children[0]->attr('text'));
        $t->same(['Body remains visible'], $paragraphTexts);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Slide" , Space , Str "1" ]', $native);
        $t->contains('Para [ Str "Body" , Space , Str "remains" , Space , Str "visible" ]', $native);
        $t->true(!str_contains($native, 'Second title should be hidden'), 'Later title placeholders should stay hidden after an empty first title placeholder');
    },

    'uses non-shape nvSpPr title placeholders like upstream' => static function (TestRunner $t) use ($buildMalformedNvSpPrTitlePicturePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildMalformedNvSpPrTitlePicturePptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');
        $native = PandocConverter::write($document, 'native');

        $t->same('Malformed picture title', $headings[0]->attr('text'));
        $t->same([], $nodesOfType($document, 'image'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Malformed" , Space , Str "picture" , Space , Str "title" ]', $native);
        $t->true(!str_contains($native, 'Image'), 'Malformed nvSpPr title placeholder picture should be filtered before picture parsing');
        $t->true(!str_contains($native, 'malformed-title-picture.png'), 'Filtered title placeholder picture media should not become visible');
    },

    'uses slide r prefix binding for picture and SmartArt relationships like upstream' => static function (TestRunner $t) use ($buildWrongPrefixShapeRelationshipsPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildWrongPrefixShapeRelationshipsPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesOfType($document, 'image'));
        $t->same([], $nodesWithClass($nodesOfType($document, 'div'), 'smartart'));
        $t->same(true, in_array('[Graphic: diagram-missing-rels]', $texts, true));
        $t->same(1, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Wrong" , Space , Str "prefix" , Space , Str "shapes" ]', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "diagram-missing-rels]" ]', $native);
        $t->true(!str_contains($native, 'wrong-prefix.png'), 'Picture relationship under the wrong r prefix binding should not become visible');
        $t->true(!str_contains($native, 'Wrong prefix parent'), 'SmartArt relIds under the wrong r prefix binding should not parse the diagram');
    },

    'ignores inherited intermediate r prefix bindings for picture and SmartArt relationships like upstream' => static function (TestRunner $t) use ($buildIntermediatePrefixShapeRelationshipsPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildIntermediatePrefixShapeRelationshipsPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesOfType($document, 'image'));
        $t->same([], $nodesWithClass($nodesOfType($document, 'div'), 'smartart'));
        $t->same(true, in_array('[Graphic: diagram-missing-rels]', $texts, true));
        $t->same(1, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same('missing-image-relationship-id', $review['slides'][0]['imageIssues'][0]['issue'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Intermediate" , Space , Str "prefix" , Space , Str "shapes" ]', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "diagram-missing-rels]" ]', $native);
        $t->true(!str_contains($native, 'intermediate-prefix.png'), 'Picture relationship declared only on an intermediate ancestor should not become visible');
        $t->true(!str_contains($native, 'Intermediate prefix parent'), 'SmartArt relIds declared only through an intermediate ancestor should not parse the diagram');
    },

    'ignores shape-tree r prefix bindings for picture and SmartArt relationships like upstream' => static function (TestRunner $t) use ($buildShapeTreePrefixShapeRelationshipsPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildShapeTreePrefixShapeRelationshipsPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesOfType($document, 'image'));
        $t->same([], $nodesWithClass($nodesOfType($document, 'div'), 'smartart'));
        $t->same(true, in_array('[Graphic: diagram-missing-rels]', $texts, true));
        $t->same(1, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same('missing-image-relationship-id', $review['slides'][0]['imageIssues'][0]['issue'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Shape" , Space , Str "tree" , Space , Str "prefix" , Space , Str "relationships" ]', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "diagram-missing-rels]" ]', $native);
        $t->true(!str_contains($native, 'sp-tree-prefix.png'), 'Picture relationship declared only on spTree should not become visible');
        $t->true(!str_contains($native, 'Shape tree prefix parent'), 'SmartArt relIds declared only through spTree should not parse the diagram');
    },

    'uses element-local r prefix bindings for picture and SmartArt relationships like upstream' => static function (TestRunner $t) use ($buildLocalPrefixShapeRelationshipsPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildLocalPrefixShapeRelationshipsPptxPackage());
        $review = $document->attr('pptx');
        $images = $nodesOfType($document, 'image');
        $smartArtDivs = $nodesWithClass($nodesOfType($document, 'div'), 'smartart');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($images));
        $t->same('ppt/media/local-prefix.png', $images[0]->attr('url'));
        $t->same('Local Prefix Picture', $images[0]->attr('title'));
        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'localPrefixLayout'], $smartArtDivs[0]->attr('classes'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Local" , Space , Str "prefix" , Space , Str "relationships" ]', $native);
        $t->contains('Image ( "" , [  ] , [  ] ) [ Str "Local" , Space , Str "prefix" , Space , Str "alt" ] ( "ppt/media/local-prefix.png" , "Local Prefix Picture" )', $native);
        $t->contains('Strong [ Str "Local" , Space , Str "prefix" , Space , Str "parent" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "Local" , Space , Str "prefix" , Space , Str "child"', $native);
        $t->true(!str_contains($native, 'Diagram parse error'), 'Element-local SmartArt relIds prefix should parse without diagnostics');
        $t->true(!str_contains($native, '[Graphic: diagram-missing-rels]'), 'Element-local SmartArt relIds prefix should not be treated as missing relationships');
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

    'skips pptx pictures without blip elements like upstream' => static function (TestRunner $t) use ($buildPictureWithoutBlipPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildPictureWithoutBlipPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesOfType($document, 'image'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Pictures" , Space , Str "without" , Space , Str "blips" ]', $native);
        $t->true(!str_contains($native, 'Missing BlipFill Picture'), 'Picture without p:blipFill should be skipped before visible image output');
        $t->true(!str_contains($native, 'Missing Blip Picture'), 'Picture without a:blip should be skipped before visible image output');
        $t->true(!str_contains($native, 'unreferenced-no-blip.png'), 'Unreferenced image media should not become visible without a blip relationship');
    },

    'ignores pptx embedded image TargetMode like upstream' => static function (TestRunner $t) use ($buildExternalModeEmbeddedImagePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildExternalModeEmbeddedImagePptxPackage());
        $review = $document->attr('pptx');
        $images = $nodesOfType($document, 'image');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($images));
        $t->same('ppt/media/external-mode.png', $images[0]->attr('url'));
        $t->same('External Mode Picture', $images[0]->attr('title'));
        $t->same('External mode alt', $images[0]->attr('alt'));
        $t->same('embed', $images[0]->attr('relationshipAttribute'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Image ( "" , [  ] , [  ] ) [ Str "External" , Space , Str "mode" , Space , Str "alt" ] ( "ppt/media/external-mode.png" , "External Mode Picture" )', $native);
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

    'keeps invalid pptx SmartArt XML as a parse diagnostic like upstream' => static function (TestRunner $t) use ($buildInvalidSmartArtDataXmlPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildInvalidSmartArtDataXmlPptxPackage());
        $paragraphs = $nodesOfType($document, 'paragraph');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $diagnostics = array_values(array_filter(
            $paragraphs,
            static fn (AstNode $paragraph): bool => str_starts_with((string) $paragraph->attr('text'), '[Diagram parse error:')
        ));
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($diagnostics));
        $parseErrors = array_values(array_filter(
            $texts,
            static fn (string $text): bool => str_starts_with($text, '[Diagram parse error: Unable to parse PPTX SmartArt data at line ')
        ));

        $t->same(1, count($parseErrors));
        $t->true(!str_contains($native, 'File not found in archive: ppt/diagrams/data1.xml'), 'Existing invalid SmartArt XML should not be mislabeled as a missing package part');
        $t->same('Invalid SmartArt Frame', $diagnostics[0]->attr('pptxShape')['name'] ?? null);
    },

    'treats Unicode-whitespace-only pptx SmartArt text as empty like upstream' => static function (TestRunner $t) use ($buildWhitespaceOnlySmartArtTextPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildWhitespaceOnlySmartArtTextPptxPackage());
        $review = $document->attr('pptx');
        $divs = $nodesOfType($document, 'div');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'basicBlockList'], $smartArtDivs[0]->attr('classes'));
        $t->same([], $smartArtDivs[0]->children);
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->true(!str_contains($native, 'Hidden child'), 'Children of a Unicode-whitespace-only SmartArt parent should stay hidden like upstream');
        $t->true(!str_contains($native, 'BulletList'), 'Whitespace-only SmartArt parent should not emit a child list');
    },

    'uses upstream literal pptx SmartArt targets without root-relative normalization' => static function (TestRunner $t) use ($buildRootRelativeSmartArtTargetPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildRootRelativeSmartArtTargetPptxPackage());
        $review = $document->attr('pptx');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $divs = $nodesOfType($document, 'div');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $native = PandocConverter::write($document, 'native');

        $t->same([], $nodesWithClass($divs, 'smartart'));
        $t->same(true, in_array('[Diagram parse error: File not found in archive: /ppt/diagrams/data1.xml]', $texts, true));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->true(!str_contains($native, 'Root target parent'), 'Root-relative SmartArt target should not be normalized into parsed visible content');
        $t->true(!str_contains($native, 'Root target child'), 'Root-relative SmartArt target children should stay hidden when upstream would miss the part');
        $t->contains('Para [ Str "[Diagram" , Space , Str "parse" , Space , Str "error:" , Space , Str "File" , Space , Str "not" , Space , Str "found" , Space , Str "in" , Space , Str "archive:" , Space , Str "/ppt/diagrams/data1.xml]" ]', $native);
    },

    'keeps empty-type and empty-id pptx SmartArt connections hierarchical like upstream' => static function (TestRunner $t) use ($buildEmptyTypeSmartArtConnectionPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildEmptyTypeSmartArtConnectionPptxPackage());
        $review = $document->attr('pptx');
        $divs = $nodesOfType($document, 'div');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $bulletLists = $nodesOfType($document, 'bullet_list');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'basicBlockList'], $smartArtDivs[0]->attr('classes'));
        $t->same(1, count($bulletLists));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Strong [ Str "Empty" , Space , Str "id" , Space , Str "parent" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "Empty" , Space , Str "type" , Space , Str "child"', $native);
    },

    'uses the SmartArt root dgm prefix binding like upstream' => static function (TestRunner $t) use ($buildRootPrefixSmartArtPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildRootPrefixSmartArtPptxPackage());
        $review = $document->attr('pptx');
        $divs = $nodesOfType($document, 'div');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $bulletLists = $nodesOfType($document, 'bullet_list');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'rootPrefixLayout'], $smartArtDivs[0]->attr('classes'));
        $t->same(['layout' => 'rootPrefixLayout'], $smartArtDivs[0]->attr('attributes'));
        $t->same(1, count($bulletLists));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Strong [ Str "Root" , Space , Str "prefix" , Space , Str "parent" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "Root" , Space , Str "prefix" , Space , Str "child"', $native);
        $t->true(!str_contains($native, 'Diagram parse error'), 'Root dgm prefix binding should parse rather than emitting a SmartArt diagnostic');
    },

    'uses present empty SmartArt layout uniqueIds like upstream' => static function (TestRunner $t) use ($buildEmptyUniqueIdSmartArtLayoutPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildEmptyUniqueIdSmartArtLayoutPptxPackage());
        $review = $document->attr('pptx');
        $divs = $nodesOfType($document, 'div');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', ''], $smartArtDivs[0]->attr('classes'));
        $t->same(['layout' => ''], $smartArtDivs[0]->attr('attributes'));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Strong [ Str "Empty" , Space , Str "uniqueId" , Space , Str "parent" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "Empty" , Space , Str "uniqueId" , Space , Str "child"', $native);
        $t->true(!str_contains($native, 'fallbackTitleShouldNotWin'), 'A present empty uniqueId should prevent dgm:title fallback');
    },

    'uses empty SmartArt layout names from trailing slash uniqueIds like upstream' => static function (TestRunner $t) use ($buildTrailingSlashUniqueIdSmartArtLayoutPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildTrailingSlashUniqueIdSmartArtLayoutPptxPackage());
        $review = $document->attr('pptx');
        $divs = $nodesOfType($document, 'div');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', ''], $smartArtDivs[0]->attr('classes'));
        $t->same(['layout' => ''], $smartArtDivs[0]->attr('attributes'));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Strong [ Str "Trailing" , Space , Str "uniqueId" , Space , Str "parent" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "Trailing" , Space , Str "uniqueId" , Space , Str "child"', $native);
        $t->true(!str_contains($native, 'fallbackTitleShouldNotWin'), 'A trailing slash uniqueId should extract an empty layout name instead of falling back to dgm:title');
    },

    'uses the last duplicate pptx SmartArt modelId text like upstream' => static function (TestRunner $t) use ($buildDuplicateModelIdSmartArtPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildDuplicateModelIdSmartArtPptxPackage());
        $review = $document->attr('pptx');
        $divs = $nodesOfType($document, 'div');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'basicBlockList'], $smartArtDivs[0]->attr('classes'));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Strong [ Str "Latest" , Space , Str "duplicate" , Space , Str "parent" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "Latest" , Space , Str "duplicate" , Space , Str "child"', $native);
        $t->true(!str_contains($native, 'Stale duplicate parent'), 'Earlier duplicate SmartArt parent text should be overwritten like upstream Map.fromList');
        $t->true(!str_contains($native, 'Stale duplicate child'), 'Earlier duplicate SmartArt child text should be overwritten like upstream Map.fromList');
    },

    'keeps pptx SmartArt relationships without Type usable like upstream' => static function (TestRunner $t) use ($buildUntypedSmartArtRelationshipsPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildUntypedSmartArtRelationshipsPptxPackage());
        $review = $document->attr('pptx');
        $divs = $nodesOfType($document, 'div');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'basicBlockList'], $smartArtDivs[0]->attr('classes'));
        $t->same(['layout' => 'basicBlockList'], $smartArtDivs[0]->attr('attributes'));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Strong [ Str "Untyped" , Space , Str "SmartArt" , Space , Str "parent" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "Untyped" , Space , Str "SmartArt" , Space , Str "child"', $native);
        $t->true(!str_contains($native, 'Diagram parse error'), 'Untyped SmartArt relationships should still resolve by relationship id');
        $t->true(!str_contains($native, '[Graphic: diagram-missing-rels]'), 'Untyped SmartArt relationships should not be treated as missing relIds');
    },

    'filters orphan typed and malformed pptx SmartArt connections like upstream' => static function (TestRunner $t) use ($buildFilteredSmartArtConnectionsPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildFilteredSmartArtConnectionsPptxPackage());
        $review = $document->attr('pptx');
        $divs = $nodesOfType($document, 'div');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $bulletLists = $nodesOfType($document, 'bullet_list');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'basicBlockList'], $smartArtDivs[0]->attr('classes'));
        $t->same(['layout' => 'basicBlockList'], $smartArtDivs[0]->attr('attributes'));
        $t->same(2, count($smartArtDivs[0]->children));
        $t->same(1, count($bulletLists));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Strong [ Str "Visible" , Space , Str "parent" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "Visible" , Space , Str "child"', $native);
        $t->true(!str_contains($native, 'Orphan text'), 'SmartArt nodes without outgoing untyped connections should stay hidden like upstream');
        $t->true(!str_contains($native, 'Typed parent'), 'Typed SmartArt connections should not make visible hierarchy parents');
        $t->true(!str_contains($native, 'Typed child'), 'Children reachable only through typed SmartArt connections should stay hidden');
        $t->true(!str_contains($native, 'Endpoint parent'), 'SmartArt connections without destId should be ignored');
        $t->true(!str_contains($native, 'Endpoint child'), 'SmartArt connections without srcId should be ignored');
    },

    'sorts pptx SmartArt parents while preserving child connection order like upstream' => static function (TestRunner $t) use ($buildOrderedSmartArtConnectionsPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildOrderedSmartArtConnectionsPptxPackage());
        $review = $document->attr('pptx');
        $smartArtDivs = $nodesWithClass($nodesOfType($document, 'div'), 'smartart');
        $native = PandocConverter::write($document, 'native');

        $firstParent = strpos($native, 'Strong [ Str "First" , Space , Str "sorted" , Space , Str "parent" ]');
        $betaChild = strpos($native, 'Plain [ Str "Beta" , Space , Str "connection" , Space , Str "child" ]');
        $alphaChild = strpos($native, 'Plain [ Str "Alpha" , Space , Str "connection" , Space , Str "child" ]');
        $secondParent = strpos($native, 'Strong [ Str "Second" , Space , Str "sorted" , Space , Str "parent" ]');
        $secondChild = strpos($native, 'Plain [ Str "Second" , Space , Str "sorted" , Space , Str "child" ]');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'basicBlockList'], $smartArtDivs[0]->attr('classes'));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->true($firstParent !== false, 'First sorted SmartArt parent should be present');
        $t->true($betaChild !== false, 'First SmartArt child should be present');
        $t->true($alphaChild !== false, 'Second SmartArt child should be present');
        $t->true($secondParent !== false, 'Second sorted SmartArt parent should be present');
        $t->true($secondChild !== false, 'Second sorted SmartArt child should be present');
        $t->true(is_int($firstParent) && is_int($betaChild) && $firstParent < $betaChild, 'Parent aParent should render before its children');
        $t->true(is_int($betaChild) && is_int($alphaChild) && $betaChild < $alphaChild, 'Children should preserve connection order, not modelId sort order');
        $t->true(is_int($alphaChild) && is_int($secondParent) && $alphaChild < $secondParent, 'Parent ids should be sorted before zParent renders');
        $t->true(is_int($secondParent) && is_int($secondChild) && $secondParent < $secondChild, 'Second parent should render before its child');
    },

    'uses all descendant pptx SmartArt text like upstream' => static function (TestRunner $t) use ($buildSmartArtAllDescendantTextPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildSmartArtAllDescendantTextPptxPackage());
        $review = $document->attr('pptx');
        $divs = $nodesOfType($document, 'div');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'basicBlockList'], $smartArtDivs[0]->attr('classes'));
        $t->same(['layout' => 'basicBlockList'], $smartArtDivs[0]->attr('attributes'));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Strong [ Str "Direct" , Space , Str "parent" , Space , Str "foreign" , Space , Str "parent" , Space , Str "drawing" , Space , Str "parent" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "foreign" , Space , Str "child" , Space , Str "drawing" , Space , Str "child"', $native);
        $t->true(!str_contains($native, 'Diagram parse error'), 'SmartArt descendant text should parse without falling back to diagnostics');
    },

    'uses unknown SmartArt layout names when uniqueId and title are absent like upstream' => static function (TestRunner $t) use ($buildUnknownLayoutSmartArtPptxPackage, $nodesOfType, $nodesWithClass): void {
        $document = (new PptxReader())->read($buildUnknownLayoutSmartArtPptxPackage());
        $review = $document->attr('pptx');
        $divs = $nodesOfType($document, 'div');
        $smartArtDivs = $nodesWithClass($divs, 'smartart');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, count($smartArtDivs));
        $t->same(['smartart', 'unknown'], $smartArtDivs[0]->attr('classes'));
        $t->same(['layout' => 'unknown'], $smartArtDivs[0]->attr('attributes'));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Strong [ Str "Unknown" , Space , Str "layout" , Space , Str "parent" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "Unknown" , Space , Str "layout" , Space , Str "child"', $native);
        $t->true(!str_contains($native, 'Diagram parse error'), 'Missing SmartArt layout labels should not make the diagram fail');
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

    'uses namespace-agnostic pptx text elements like upstream' => static function (TestRunner $t) use ($buildNamespaceAgnosticDrawingTextPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildNamespaceAgnosticDrawingTextPptxPackage());
        $review = $document->attr('pptx');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $nodesOfType($document, 'paragraph'));
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Namespace agnostic text', $document->children[0]->attr('text'));
        $t->same(true, in_array('Drawing text Foreign text', $paragraphTexts, true));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Para [ Str "Drawing" , Space , Str "text" , Space , Str "Foreign" , Space , Str "text" ]', $native);
    },

    'uses the slide root p namespace binding for shape elements like upstream' => static function (TestRunner $t) use ($buildRootPrefixNamespaceShapePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildRootPrefixNamespaceShapePptxPackage());
        $review = $document->attr('pptx');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $nodesOfType($document, 'paragraph'));
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Root prefix namespace title', $document->children[0]->attr('text'));
        $t->same(true, in_array('Root prefix namespace body', $paragraphTexts, true));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Root" , Space , Str "prefix" , Space , Str "namespace" , Space , Str "title" ]', $native);
        $t->contains('Para [ Str "Root" , Space , Str "prefix" , Space , Str "namespace" , Space , Str "body" ]', $native);
    },

    'uses the slide root p prefix binding for shape trees like upstream' => static function (TestRunner $t) use ($buildShadowedSlidePrefixPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildShadowedSlidePrefixPptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same(1, $review['slides'][0]['blockCount'] ?? null);
        $t->same('Slide 1', $headings[0]->attr('text'));
        $t->same([], $nodesOfType($document, 'paragraph'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Slide" , Space , Str "1" ]', $native);
        $t->true(!str_contains($native, 'Shadowed slide prefix title'), 'Locally corrected cSld should not override the slide root p prefix binding');
        $t->true(!str_contains($native, 'Shadowed slide prefix body'), 'Locally corrected shape tree should not become visible under a wrong slide root p binding');
    },

    'uses the slide root a prefix binding for text box paragraphs like upstream' => static function (TestRunner $t) use ($buildShadowedDrawingPrefixPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildShadowedDrawingPrefixPptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same(1, $review['slides'][0]['blockCount'] ?? null);
        $t->same('Shadowed drawing prefix title', $headings[0]->attr('text'));
        $t->same([], $nodesOfType($document, 'paragraph'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Shadowed" , Space , Str "drawing" , Space , Str "prefix" , Space , Str "title" ]', $native);
        $t->true(!str_contains($native, 'Shadowed drawing prefix body'), 'Locally corrected text-body DrawingML should not override the slide root a prefix binding');
    },

    'ignores inherited pptx paragraph property prefixes like upstream' => static function (TestRunner $t) use ($buildInheritedParagraphPropertiesPrefixPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildInheritedParagraphPropertiesPrefixPptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Inherited paragraph prefix', $headings[0]->attr('text'));
        $t->same(true, in_array('Inherited bullet metadata', $paragraphTexts, true));
        $t->contains('Para [ Str "Inherited" , Space , Str "bullet" , Space , Str "metadata" ]', $native);
        $t->true(!str_contains($native, 'BulletList'), 'Paragraph properties inheriting a from txBody should not create upstream PPTX bullets');
    },

    'uses the slide root a prefix binding for pictures and graphic frames like upstream' => static function (TestRunner $t) use ($buildShadowedDrawingMediaPrefixPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildShadowedDrawingMediaPrefixPptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Shadowed drawing media title', $headings[0]->attr('text'));
        $t->same([], $nodesOfType($document, 'image'));
        $t->same([], $nodesOfType($document, 'table'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same(1, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Shadowed" , Space , Str "drawing" , Space , Str "media" , Space , Str "title" ]', $native);
        $t->true(!str_contains($native, 'shadowed-drawing.png'), 'Locally corrected picture DrawingML should not override the slide root a prefix binding');
        $t->true(!str_contains($native, 'Shadowed table header'), 'Locally corrected graphic-frame DrawingML should not override the slide root a prefix binding');
        $t->true(!str_contains($native, 'Shadowed table body'), 'Locally corrected table DrawingML should not become visible under a wrong slide root a binding');
    },

    'ignores inherited pptx table row prefixes like upstream' => static function (TestRunner $t) use ($buildInheritedTablePrefixPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildInheritedTablePrefixPptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Inherited table prefix', $headings[0]->attr('text'));
        $t->same([], $nodesOfType($document, 'table'));
        $t->same(0, $review['slides'][0]['shapeIssueCount'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Inherited" , Space , Str "table" , Space , Str "prefix" ]', $native);
        $t->true(!str_contains($native, 'Inherited table header'), 'Table rows inheriting a from graphicData should stay hidden like upstream');
        $t->true(!str_contains($native, 'Inherited table body'), 'Table cells inheriting a from graphicData should stay hidden like upstream');
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

    'allows pptx presentations without slide lists like upstream' => static function (TestRunner $t) use ($buildSlideLessPresentationPptxPackage): void {
        $document = (new PptxReader())->read($buildSlideLessPresentationPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same(0, $review['slideCount'] ?? null);
        $t->same([], $review['slides'] ?? null);
        $t->same([], $document->children);
        $t->same('presentation', $review['slideSize']['source'] ?? null);
        $t->same(12192000, $review['slideSize']['cx'] ?? null);
        $t->same(6858000, $review['slideSize']['cy'] ?? null);
        $t->same([], $review['tableStyles'] ?? null);
        $t->true(!str_contains($native, 'Unreferenced slide body'), 'Unreferenced slide parts should not become visible without p:sldIdLst entries');
    },

    'falls back to zero for invalid pptx slide sizes like upstream' => static function (TestRunner $t) use ($buildInvalidSlideSizePptxPackage): void {
        $document = (new PptxReader())->read($buildInvalidSlideSizePptxPackage());
        $review = $document->attr('pptx');

        $t->same(0, $review['slideCount'] ?? null);
        $t->same([], $review['slides'] ?? null);
        $t->same([], $document->children);
        $t->same([
            'cx' => 0,
            'cy' => 0,
            'width' => 0,
            'height' => 0,
            'emusPerInch' => 914400,
            'source' => 'presentation',
        ], $review['slideSize'] ?? null);
    },

    'falls back independently for missing pptx slide size attributes like upstream' => static function (TestRunner $t) use ($buildPartialSlideSizePptxPackage): void {
        $document = (new PptxReader())->read($buildPartialSlideSizePptxPackage());
        $review = $document->attr('pptx');

        $t->same(0, $review['slideCount'] ?? null);
        $t->same([], $review['slides'] ?? null);
        $t->same([], $document->children);
        $t->same([
            'cx' => 12192000,
            'cy' => 0,
            'width' => 13,
            'height' => 0,
            'emusPerInch' => 914400,
            'source' => 'presentation',
        ], $review['slideSize'] ?? null);
    },

    'uses the presentation root p prefix binding for slide lists and sizes like upstream' => static function (TestRunner $t) use ($buildShadowedPresentationPrefixPptxPackage): void {
        $document = (new PptxReader())->read($buildShadowedPresentationPrefixPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same(0, $review['slideCount'] ?? null);
        $t->same([], $document->children);
        $t->same('default', $review['slideSize']['source'] ?? null);
        $t->same(9144000, $review['slideSize']['cx'] ?? null);
        $t->true(!str_contains($native, 'Shadowed presentation prefix body'), 'Locally corrected presentation children should not override the root p prefix binding');
    },

    'allows alternate pptx presentation and slide root names like upstream' => static function (TestRunner $t) use ($buildAlternateRootPresentationAndSlidePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildAlternateRootPresentationAndSlidePptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('presentation', $review['slideSize']['source'] ?? null);
        $t->same(12192000, $review['slideSize']['cx'] ?? null);
        $t->same('Alternate root title', $headings[0]->attr('text'));
        $t->same('Alternate root body', $paragraphs[0]->attr('text'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Alternate" , Space , Str "root" , Space , Str "title" ]', $native);
        $t->contains('Para [ Str "Alternate" , Space , Str "root" , Space , Str "body" ]', $native);
    },

    'requires pptx presentation slide relationship ids to use the relationship namespace like upstream' => static function (TestRunner $t) use ($buildUnqualifiedPresentationRelationshipPptxPackage): void {
        try {
            (new PptxReader())->read($buildUnqualifiedPresentationRelationshipPptxPackage());
        } catch (RuntimeException $exception) {
            $t->same('Missing r:id in slide 1', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected missing relationship namespace to reject the PPTX package');
    },

    'uses the presentation r prefix binding for slide relationship ids like upstream' => static function (TestRunner $t) use ($buildWrongPrefixPresentationRelationshipPptxPackage): void {
        $t->throws(RuntimeException::class, static fn (): AstNode => (new PptxReader())->read($buildWrongPrefixPresentationRelationshipPptxPackage()));
    },

    'ignores inherited intermediate r prefix bindings for slide relationship ids like upstream' => static function (TestRunner $t) use ($buildIntermediatePrefixPresentationRelationshipPptxPackage): void {
        $t->throws(RuntimeException::class, static fn (): AstNode => (new PptxReader())->read($buildIntermediatePrefixPresentationRelationshipPptxPackage()));
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

    'uses root officeDocument relationship children regardless of element name like upstream' => static function (TestRunner $t) use ($buildRootOfficeDocumentAliasPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildRootOfficeDocumentAliasPptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('ppt/presentation.xml', $review['presentationPart'] ?? null);
        $t->same('Root officeDocument alias', $headings[0]->attr('text'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Root" , Space , Str "officeDocument" , Space , Str "alias" ]', $native);
    },

    'uses root officeDocument relationship Type suffix matching like upstream' => static function (TestRunner $t) use ($buildSuffixRootOfficeDocumentTypePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildSuffixRootOfficeDocumentTypePptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('ppt/presentation.xml', $review['presentationPart'] ?? null);
        $t->same('Suffix officeDocument type', $headings[0]->attr('text'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Suffix" , Space , Str "officeDocument" , Space , Str "type" ]', $native);
    },

    'requires root officeDocument relationship Type to be unqualified like upstream' => static function (TestRunner $t) use ($buildQualifiedRootOfficeDocumentTypePptxPackage): void {
        try {
            (new PptxReader())->read($buildQualifiedRootOfficeDocumentTypePptxPackage());
        } catch (RuntimeException $exception) {
            $t->same('No presentation.xml relationship found. Found 1 relationships.', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected qualified root officeDocument Type to stay unrecognized like upstream');
    },

    'requires root officeDocument relationship Target to be unqualified like upstream' => static function (TestRunner $t) use ($buildQualifiedRootOfficeDocumentTargetPptxPackage): void {
        try {
            (new PptxReader())->read($buildQualifiedRootOfficeDocumentTargetPptxPackage());
        } catch (RuntimeException $exception) {
            $t->same('Missing Target attribute', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected qualified root officeDocument Target to stay missing like upstream');
    },

    'fails on the first missing-target root officeDocument relationship like upstream' => static function (TestRunner $t) use ($buildFirstMissingTargetRootOfficeDocumentPptxPackage): void {
        try {
            (new PptxReader())->read($buildFirstMissingTargetRootOfficeDocumentPptxPackage());
        } catch (RuntimeException $exception) {
            $t->same('Missing Target attribute', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected first matching root officeDocument relationship without Target to fail before later matches');
    },

    'allows root officeDocument relationships without Id like upstream' => static function (TestRunner $t) use ($buildRootOfficeDocumentWithoutIdPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildRootOfficeDocumentWithoutIdPptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');

        $t->same('ppt/presentation.xml', $review['presentationPart'] ?? null);
        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Root relationship without id', $headings[0]->attr('text'));
    },

    'ignores root officeDocument TargetMode like upstream' => static function (TestRunner $t) use ($buildExternalModeRootOfficeDocumentPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildExternalModeRootOfficeDocumentPptxPackage());
        $review = $document->attr('pptx');
        $headings = $nodesOfType($document, 'heading');

        $t->same('ppt/presentation.xml', $review['presentationPart'] ?? null);
        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Root TargetMode ignored', $headings[0]->attr('text'));
    },

    'uses upstream literal root-level pptx presentation relationship paths' => static function (TestRunner $t) use ($buildRootLevelPresentationRelationshipPartPptxPackage): void {
        try {
            (new PptxReader())->read($buildRootLevelPresentationRelationshipPartPptxPackage());
        } catch (RuntimeException $exception) {
            $t->same('Relationship not found: rIdSlide', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected root-level presentation relationship sidecar to stay unresolved like upstream');
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

    'keeps empty pptx root officeDocument targets as literal entry lookups like upstream' => static function (TestRunner $t) use ($buildEmptyPresentationTargetPptxPackage): void {
        try {
            (new PptxReader())->read($buildEmptyPresentationTargetPptxPackage());
        } catch (RuntimeException $exception) {
            $t->same('Entry not found: ', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected empty root presentation Target to look up the empty package entry like upstream');
    },

    'ignores pptx slide relationship TargetMode like upstream' => static function (TestRunner $t) use ($buildExternalModeSlideRelationshipPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildExternalModeSlideRelationshipPptxPackage());
        $review = $document->attr('pptx');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $nodesOfType($document, 'paragraph'));
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('ppt/slides/slide1.xml', $review['slides'][0]['partName'] ?? null);
        $t->same('External mode slide', $document->children[0]->attr('text'));
        $t->same(true, in_array('TargetMode is ignored', $paragraphTexts, true));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "External" , Space , Str "mode" , Space , Str "slide" ]', $native);
        $t->contains('Para [ Str "TargetMode" , Space , Str "is" , Space , Str "ignored" ]', $native);
    },

    'keeps invalid pptx review sidecar targets non-fatal like upstream' => static function (TestRunner $t) use ($buildInvalidReviewSidecarTargetsPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildInvalidReviewSidecarTargetsPptxPackage());
        $review = $document->attr('pptx');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $nodesOfType($document, 'paragraph'));
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Review targets', $document->children[0]->attr('text'));
        $t->same(true, in_array('Review sidecars stay optional', $paragraphTexts, true));
        $t->contains('Para [ Str "Review" , Space , Str "sidecars" , Space , Str "stay" , Space , Str "optional" ]', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "other:" , Space , Str "http://schemas.openxmlformats.org/drawingml/2006/chart]" ]', $native);
        $t->same(['invalid-document-properties-target'], $review['documentProperties']['core']['issues'] ?? null);
        $t->same(['invalid-table-styles-target'], $review['tableStyles']['issues'] ?? null);
        $t->same(0, $review['slides'][0]['commentCount'] ?? null);
        $t->same(0, $review['slides'][0]['speakerNoteCount'] ?? null);
        $t->same(1, $review['slides'][0]['backgroundCount'] ?? null);
        $t->same(['invalid-background-target'], $review['slides'][0]['backgrounds'][0]['issues'] ?? null);
        $t->same(1, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->same('invalid-linked-image-target', $review['slides'][0]['imageIssues'][0]['issue'] ?? null);
        $t->same(0, $review['slides'][0]['richMediaCount'] ?? null);
        $t->same(1, $review['slides'][0]['chartCount'] ?? null);
        $t->same(['invalid-chart-part-target'], $review['slides'][0]['charts'][0]['issues'] ?? null);
    },

    'keeps invalid pptx review relationship sidecars non-fatal like upstream' => static function (TestRunner $t) use ($buildInvalidReviewRelationshipSidecarPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildInvalidReviewRelationshipSidecarPptxPackage());
        $review = $document->attr('pptx');
        $paragraphTexts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $nodesOfType($document, 'paragraph'));
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Invalid review rels', $document->children[0]->attr('text'));
        $t->same(true, in_array('Visible body survives', $paragraphTexts, true));
        $t->same('ppt/slideLayouts/slideLayout1.xml', $review['slides'][0]['context']['layoutPart'] ?? null);
        $t->same(null, $review['slides'][0]['context']['masterPart'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Invalid" , Space , Str "review" , Space , Str "rels" ]', $native);
        $t->contains('Para [ Str "Visible" , Space , Str "body" , Space , Str "survives" ]', $native);
        $t->true(!str_contains($native, 'Layout review text'), 'Layout review text must not leak into visible output');
    },

    'uses upstream literal root officeDocument targets instead of normalizing root-relative paths' => static function (TestRunner $t) use ($buildRootRelativePresentationTargetPptxPackage): void {
        try {
            (new PptxReader())->read($buildRootRelativePresentationTargetPptxPackage());
        } catch (RuntimeException $exception) {
            $t->same('Entry not found: /ppt/presentation.xml', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected root-relative presentation Target to stay literal like upstream');
    },

    'uses upstream literal root officeDocument targets instead of normalizing dot segments' => static function (TestRunner $t) use ($buildDotSegmentPresentationTargetPptxPackage): void {
        $t->throws(RuntimeException::class, static fn (): AstNode => (new PptxReader())->read($buildDotSegmentPresentationTargetPptxPackage()));
    },

    'uses upstream literal pptx slide targets instead of normalizing root-relative paths' => static function (TestRunner $t) use ($buildRootRelativeSlideTargetPptxPackage): void {
        $t->throws(RuntimeException::class, static fn (): AstNode => (new PptxReader())->read($buildRootRelativeSlideTargetPptxPackage()));
    },

    'keeps empty pptx slide relationship targets as literal ppt slash lookups like upstream' => static function (TestRunner $t) use ($buildEmptySlideTargetPptxPackage): void {
        try {
            (new PptxReader())->read($buildEmptySlideTargetPptxPackage());
        } catch (RuntimeException $exception) {
            $t->same('Entry not found: ppt/', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected empty slide Target to look up ppt/ like upstream');
    },

    'uses the first duplicate pptx slide relationship id like upstream' => static function (TestRunner $t) use ($buildDuplicateSlideRelationshipPptxPackage): void {
        try {
            (new PptxReader())->read($buildDuplicateSlideRelationshipPptxPackage());
        } catch (RuntimeException $exception) {
            $t->same('Entry not found: ppt/slides/missing-first.xml', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected duplicate slide relationship IDs to keep the first target like upstream');
    },

    'keeps pptx relationships without Type usable for target lookup like upstream' => static function (TestRunner $t) use ($buildUntypedRelationshipsPptxPackage): void {
        $document = (new PptxReader())->read($buildUntypedRelationshipsPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Untyped relationships', $document->children[0]->attr('text'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Untyped" , Space , Str "relationships" ]', $native);
    },

    'uses pptx relationship children regardless of element name like upstream' => static function (TestRunner $t) use ($buildNonRelationshipChildRelationshipsPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildNonRelationshipChildRelationshipsPptxPackage());
        $review = $document->attr('pptx');
        $images = $nodesOfType($document, 'image');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('Relationship child names', $document->children[0]->attr('text'));
        $t->same(1, count($images));
        $t->same('ppt/media/non-relationship-child.png', $images[0]->attr('url'));
        $t->same('Odd Child Picture', $images[0]->attr('title'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Relationship" , Space , Str "child" , Space , Str "names" ]', $native);
        $t->contains('Image ( "" , [  ] , [  ] ) [ Str "Odd" , Space , Str "child" , Space , Str "alt" ] ( "ppt/media/non-relationship-child.png" , "Odd Child Picture" )', $native);
    },

    'uses only unqualified pptx relationship attributes like upstream' => static function (TestRunner $t) use ($buildUnqualifiedRelationshipAttributesPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildUnqualifiedRelationshipAttributesPptxPackage());
        $review = $document->attr('pptx');
        $images = $nodesOfType($document, 'image');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('ppt/slides/slide1.xml', $review['slides'][0]['partName'] ?? null);
        $t->same('Unqualified relationship attributes', $document->children[0]->attr('text'));
        $t->same(1, count($images));
        $t->same('ppt/media/unqualified-relationship-attribute.png', $images[0]->attr('url'));
        $t->same('Unqualified Attribute Picture', $images[0]->attr('title'));
        $t->same(0, $review['slides'][0]['imageIssueCount'] ?? null);
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Unqualified" , Space , Str "relationship" , Space , Str "attributes" ]', $native);
        $t->contains('Image ( "" , [  ] , [  ] ) [ Str "Unqualified" , Space , Str "attribute" , Space , Str "alt" ] ( "ppt/media/unqualified-relationship-attribute.png" , "Unqualified Attribute Picture" )', $native);
    },

    'ignores pptx presentation slide id attributes like upstream' => static function (TestRunner $t) use ($buildIgnoredPresentationSlideIdsPptxPackage): void {
        $document = (new PptxReader())->read($buildIgnoredPresentationSlideIdsPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same(2, $review['slideCount'] ?? null);
        $t->same('rIdSlideOne', $review['slides'][0]['relationshipId'] ?? null);
        $t->same('rIdSlideTwo', $review['slides'][1]['relationshipId'] ?? null);
        $t->same('ppt/slides/slide1.xml', $review['slides'][0]['partName'] ?? null);
        $t->same('ppt/slides/slide2.xml', $review['slides'][1]['partName'] ?? null);
        $t->same('slide-1', $document->children[0]->attr('id'));
        $t->same('slide-2', $document->children[1]->attr('id'));
        $t->same('Invalid numeric slide id', $document->children[0]->attr('text'));
        $t->same('Missing numeric slide id', $document->children[1]->attr('text'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Invalid" , Space , Str "numeric" , Space , Str "slide" , Space , Str "id" ]', $native);
        $t->contains('Header 2 ( "slide-2" , [  ] , [  ] ) [ Str "Missing" , Space , Str "numeric" , Space , Str "slide" , Space , Str "id" ]', $native);
    },

    'keeps empty pptx relationship ids usable like upstream' => static function (TestRunner $t) use ($buildEmptyRelationshipIdPptxPackage): void {
        $document = (new PptxReader())->read($buildEmptyRelationshipIdPptxPackage());
        $review = $document->attr('pptx');
        $native = PandocConverter::write($document, 'native');

        $t->same(1, $review['slideCount'] ?? null);
        $t->same('', $review['slides'][0]['relationshipId'] ?? null);
        $t->same('ppt/slides/slide1.xml', $review['slides'][0]['partName'] ?? null);
        $t->same('Empty relationship id', $document->children[0]->attr('text'));
        $t->contains('Header 2 ( "slide-1" , [  ] , [  ] ) [ Str "Empty" , Space , Str "relationship" , Space , Str "id" ]', $native);
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
            '[Graphic: other: ]',
            '[Graphic: other: http://schemas.openxmlformats.org/drawingml/2006/TABLE]',
            '[Graphic: diagram-no-relIds]',
            '[Graphic: diagram-missing-rels]',
            '[Diagram parse error: Relationship not found: rIdMissingData]',
            '[Diagram parse error: Relationship not found: rIdMissingWrongData]',
            '[Diagram parse error: File not found in archive: ]',
        ] as $expected) {
            $t->same(true, in_array($expected, $texts, true));
        }
        $t->same(2, count(array_filter($texts, static fn (string $text): bool => $text === '[Graphic: diagram-no-relIds]')));

        $t->contains('Para [ Str "[Graphic:" , Space , Str "no-uri]" ]', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "other:" , Space , Str "]" ]', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "other:" , Space , Str "http://schemas.openxmlformats.org/drawingml/2006/TABLE]" ]', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "diagram-no-relIds]" ]', $native);
        $t->contains('Para [ Str "[Graphic:" , Space , Str "diagram-missing-rels]" ]', $native);
        $t->contains('Para [ Str "[Diagram" , Space , Str "parse" , Space , Str "error:" , Space , Str "Relationship" , Space , Str "not" , Space , Str "found:" , Space , Str "rIdMissingData]" ]', $native);
        $t->contains('Para [ Str "[Diagram" , Space , Str "parse" , Space , Str "error:" , Space , Str "Relationship" , Space , Str "not" , Space , Str "found:" , Space , Str "rIdMissingWrongData]" ]', $native);
        $t->contains('Para [ Str "[Diagram" , Space , Str "parse" , Space , Str "error:" , Space , Str "File" , Space , Str "not" , Space , Str "found" , Space , Str "in" , Space , Str "archive:" , Space , Str "]" ]', $native);
        $t->true(!str_contains($native, 'chart-diagram'), 'Graphic URIs containing diagram should follow the upstream diagram branch before chart handling');
        $t->true(!str_contains($native, 'table-diagram'), 'Graphic URIs containing table should follow the upstream table branch before diagram handling');
        $t->true(!str_contains($native, 'Uppercase URI table cell'), 'Graphic URI detection is case-sensitive like upstream and should not parse uppercase TABLE as a table');

        $placeholderParagraphs = array_values(array_filter(
            $paragraphs,
            static fn (AstNode $paragraph): bool => str_starts_with((string) $paragraph->attr('text'), '[Graphic:')
                || str_starts_with((string) $paragraph->attr('text'), '[Diagram parse error:')
        ));

        $t->same(9, count($placeholderParagraphs));
        $t->same('No URI Graphic', $placeholderParagraphs[0]->attr('pptxShape')['name'] ?? null);
        $t->same('Empty URI Graphic', $placeholderParagraphs[1]->attr('pptxShape')['name'] ?? null);
        $t->same('Diagram No RelIds', $placeholderParagraphs[2]->attr('pptxShape')['name'] ?? null);
        $t->same('Diagram Missing Rels', $placeholderParagraphs[3]->attr('pptxShape')['name'] ?? null);
        $t->same('Diagram Unknown Rel', $placeholderParagraphs[4]->attr('pptxShape')['name'] ?? null);
        $t->same('Wrong Namespace RelIds', $placeholderParagraphs[5]->attr('pptxShape')['name'] ?? null);
        $t->same('Empty Target SmartArt', $placeholderParagraphs[6]->attr('pptxShape')['name'] ?? null);
        $t->same('Chart Diagram URI', $placeholderParagraphs[7]->attr('pptxShape')['name'] ?? null);
        $t->same('Uppercase Table URI', $placeholderParagraphs[8]->attr('pptxShape')['name'] ?? null);
        $t->true(!str_contains($native, 'Missing GraphicData'), 'Graphic frames without graphicData should be skipped like upstream');
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

    'matches pptx Wingdings typeface case sensitivity like upstream' => static function (TestRunner $t) use ($buildWingdingsTypefaceCasePptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildWingdingsTypefaceCasePptxPackage());
        $bulletLists = $nodesOfType($document, 'bullet_list');
        $paragraphs = $nodesOfType($document, 'paragraph');
        $native = PandocConverter::write($document, 'native');
        $texts = array_map(static fn (AstNode $paragraph): string => (string) $paragraph->attr('text'), $paragraphs);
        $firstItem = $bulletLists[0]->children[0]->children[0]->children[0] ?? null;

        $t->same(1, count($bulletLists));
        $t->same('Title case Wingdings bullet', $firstItem instanceof AstNode ? $firstItem->attr('text') : null);
        $t->same(true, in_array('Lowercase wingdings stays plain', $texts, true));
        $t->same(true, in_array('Uppercase WINGDINGS stays plain', $texts, true));
        $t->contains('Para [ Str "Lowercase" , Space , Str "wingdings" , Space , Str "stays" , Space , Str "plain" ]', $native);
        $t->contains('Para [ Str "Uppercase" , Space , Str "WINGDINGS" , Space , Str "stays" , Space , Str "plain" ]', $native);
        $t->contains('BulletList [ [ Plain [ Str "Title" , Space , Str "case" , Space , Str "Wingdings" , Space , Str "bullet"', $native);
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

    'keeps empty pptx bullet paragraphs as empty list items like upstream' => static function (TestRunner $t) use ($buildEmptyBulletParagraphPptxPackage, $nodesOfType): void {
        $document = (new PptxReader())->read($buildEmptyBulletParagraphPptxPackage());
        $bulletLists = $nodesOfType($document, 'bullet_list');
        $items = $bulletLists[0]->children ?? [];
        $firstText = $items[0]->children[0]->children[0] ?? null;
        $secondText = $items[1]->children[0]->children[0] ?? null;

        $t->same(1, count($bulletLists));
        $t->same(2, count($items));
        $t->same('', $firstText instanceof AstNode ? $firstText->attr('text') : null);
        $t->same('Visible bullet', $secondText instanceof AstNode ? $secondText->attr('text') : null);
    },

    'keeps signed pptx bullet levels distinct like upstream' => static function (TestRunner $t) use ($buildSignedBulletLevelPptxPackage): void {
        $document = (new PptxReader())->read($buildSignedBulletLevelPptxPackage());
        $topLevelLists = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'bullet_list'
        ));
        $itemTexts = static function (AstNode $list): array {
            return array_map(static function (AstNode $item): string {
                $inline = $item->children[0]->children[0] ?? null;

                return $inline instanceof AstNode ? (string) $inline->attr('text') : '';
            }, $list->children);
        };

        $t->same(2, count($topLevelLists));
        $t->same([
            ['Negative level bullet'],
            ['Zero level bullet', 'Plus level bullet'],
        ], array_map($itemTexts, $topLevelLists));
    },

    'trims Unicode whitespace in pptx numeric attributes like upstream' => static function (TestRunner $t) use ($buildUnicodeWhitespaceBulletLevelPptxPackage): void {
        $document = (new PptxReader())->read($buildUnicodeWhitespaceBulletLevelPptxPackage());
        $topLevelLists = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'bullet_list'
        ));
        $itemTexts = static function (AstNode $list): array {
            return array_map(static function (AstNode $item): string {
                $inline = $item->children[0]->children[0] ?? null;

                return $inline instanceof AstNode ? (string) $inline->attr('text') : '';
            }, $list->children);
        };
        $native = PandocConverter::write($document, 'native');

        $t->same(3, count($topLevelLists));
        $t->same([
            ['Unicode level one'],
            ['Zero level middle'],
            ['Unicode level one again'],
        ], array_map($itemTexts, $topLevelLists));
        $t->same(3, substr_count($native, 'BulletList'));
    },

    'reads parenthesized pptx numeric attributes like upstream' => static function (TestRunner $t) use ($buildParenthesizedBulletLevelPptxPackage): void {
        $document = (new PptxReader())->read($buildParenthesizedBulletLevelPptxPackage());
        $topLevelLists = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'bullet_list'
        ));
        $itemTexts = static function (AstNode $list): array {
            return array_map(static function (AstNode $item): string {
                $inline = $item->children[0]->children[0] ?? null;

                return $inline instanceof AstNode ? (string) $inline->attr('text') : '';
            }, $list->children);
        };
        $native = PandocConverter::write($document, 'native');

        $t->same(3, count($topLevelLists));
        $t->same([
            ['Parenthesized level one'],
            ['Zero level separator'],
            ['Nested parenthesized level one'],
        ], array_map($itemTexts, $topLevelLists));
        $t->same(3, substr_count($native, 'BulletList'));
    },

    'reads hexadecimal and octal pptx numeric attributes like upstream' => static function (TestRunner $t) use ($buildBasedBulletLevelPptxPackage): void {
        $document = (new PptxReader())->read($buildBasedBulletLevelPptxPackage());
        $topLevelLists = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'bullet_list'
        ));
        $itemTexts = static function (AstNode $list): array {
            return array_map(static function (AstNode $item): string {
                $inline = $item->children[0]->children[0] ?? null;

                return $inline instanceof AstNode ? (string) $inline->attr('text') : '';
            }, $list->children);
        };
        $native = PandocConverter::write($document, 'native');

        $t->same(3, count($topLevelLists));
        $t->same([
            ['Hex level one'],
            ['Decimal zero separator'],
            ['Octal level one'],
        ], array_map($itemTexts, $topLevelLists));
        $t->same(3, substr_count($native, 'BulletList'));
    },

    'falls back to level zero for out-of-range pptx bullet levels like upstream' => static function (TestRunner $t) use ($buildOverflowBulletLevelPptxPackage): void {
        $document = (new PptxReader())->read($buildOverflowBulletLevelPptxPackage());
        $topLevelLists = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'bullet_list'
        ));
        $itemTexts = static function (AstNode $list): array {
            return array_map(static function (AstNode $item): string {
                $inline = $item->children[0]->children[0] ?? null;

                return $inline instanceof AstNode ? (string) $inline->attr('text') : '';
            }, $list->children);
        };
        $native = PandocConverter::write($document, 'native');

        $t->same(3, count($topLevelLists));
        $t->same([
            ['Max int level'],
            ['Min int level'],
            ['Overflow level fallback', 'Negative overflow level fallback', 'Explicit zero joins fallback'],
        ], array_map($itemTexts, $topLevelLists));
        $t->same(3, substr_count($native, 'BulletList'));
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

        try {
            (new PptxReader())->read($bytes);
        } catch (RuntimeException $exception) {
            $t->same('No presentation.xml relationship found. Found 0 relationships.', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected missing presentation relationship to reject the PPTX package');
    },

    'rejects pptx packages without root relationships like upstream' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-no-root-rels-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"/>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary PPTX package');
            }
        } finally {
            @unlink($path);
        }

        try {
            (new PptxReader())->read($bytes);
        } catch (RuntimeException $exception) {
            $t->same('Missing _rels/.rels', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected missing root relationships to reject the PPTX package');
    },

    'rejects pptx root officeDocument relationships without Target like upstream' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-missing-target-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"/></Relationships>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary PPTX package');
            }
        } finally {
            @unlink($path);
        }

        try {
            (new PptxReader())->read($bytes);
        } catch (RuntimeException $exception) {
            $t->same('Missing Target attribute', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected missing Target relationship to reject the PPTX package');
    },

    'rejects missing pptx presentation parts with upstream entry errors' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-missing-presentation-part-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/missing-presentation.xml"/></Relationships>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary PPTX package');
            }
        } finally {
            @unlink($path);
        }

        try {
            (new PptxReader())->read($bytes);
        } catch (RuntimeException $exception) {
            $t->same('Entry not found: ppt/missing-presentation.xml', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected missing presentation part to reject the PPTX package');
    },

    'rejects missing pptx slide parts with upstream entry errors' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-missing-slide-part-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');
        $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:sldIdLst><p:sldId id="256" r:id="rIdSlide"/></p:sldIdLst></p:presentation>');
        $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdSlide" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/missing-slide.xml"/></Relationships>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary PPTX package');
            }
        } finally {
            @unlink($path);
        }

        try {
            (new PptxReader())->read($bytes);
        } catch (RuntimeException $exception) {
            $t->same('Entry not found: ppt/slides/missing-slide.xml', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected missing slide part to reject the PPTX package');
    },

    'rejects pptx slide relationships without Target like upstream' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-missing-slide-target-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary PPTX path');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create temporary PPTX package');
        }
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdPresentation" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>');
        $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:sldIdLst><p:sldId id="256" r:id="rIdMissingTarget"/></p:sldIdLst></p:presentation>');
        $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdMissingTarget" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide"/></Relationships>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary PPTX package');
            }
        } finally {
            @unlink($path);
        }

        try {
            (new PptxReader())->read($bytes);
        } catch (RuntimeException $exception) {
            $t->same('Relationship not found: rIdMissingTarget', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected missing slide Target relationship to reject the PPTX package');
    },
];
