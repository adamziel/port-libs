<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

$packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

$documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source-packet?post=42&amp;step=docx" TargetMode="External"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>
</Relationships>
XML;

$documentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Heading1"/></w:pPr>
      <w:r><w:t>Imported Packet</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Reviewer </w:t></w:r>
      <w:r><w:rPr><w:b/><w:i/></w:rPr><w:t>summary</w:t></w:r>
      <w:r><w:t xml:space="preserve"> keeps </w:t></w:r>
      <w:hyperlink r:id="rIdSource"><w:r><w:t>source link</w:t></w:r></w:hyperlink>
      <w:r><w:t xml:space="preserve"> and a line</w:t><w:br/><w:t>break</w:t><w:tab/><w:t>tab.</w:t></w:r>
      <w:r><w:footnoteReference w:id="2"/></w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="7" name="Hero image" descr="DOCX hero alt" title="Hero title"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdHero"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
          <wp:inline>
            <wp:docPr id="8" name="Missing image" descr="Missing image alt"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdMissing"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:tbl>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Status</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Needs media review</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Owner</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Migration team</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML;

$footnotesXml = <<<'XML'
<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:footnote w:id="-1"><w:p><w:r><w:t>separator</w:t></w:r></w:p></w:footnote>
  <w:footnote w:id="2"><w:p><w:r><w:t>Footnote source audit.</w:t></w:r></w:p></w:footnote>
</w:footnotes>
XML;

$linkedMediaContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="jpg" ContentType="image/jpeg"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$linkedMediaDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.png"/>
  <Relationship Id="rIdPortrait" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/portrait.jpg"/>
  <Relationship Id="rIdExternalImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://cdn.example.test/source-chart.png" TargetMode="External"/>
  <Relationship Id="rIdUnsafeImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="javascript:alert(1)" TargetMode="External"/>
</Relationships>
XML;

$linkedMediaDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="21" name="Logo image" descr="Logo alt" title="Logo title"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdLogo"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
          <wp:inline>
            <wp:docPr id="22" name="Portrait image" descr="Portrait alt" title="Portrait title"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdPortrait"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
          <wp:anchor>
            <wp:docPr id="23" name="External chart" descr="External chart alt" title="External chart title"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:link="rIdExternalImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:anchor>
          <wp:anchor>
            <wp:docPr id="24" name="Unsafe chart" descr="Unsafe chart alt"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:link="rIdUnsafeImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:anchor>
        </w:drawing>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$vmlImageContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$vmlImageDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdVmlLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/vml-logo.png"/>
  <Relationship Id="rIdVmlExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://cdn.example.test/vml-review-chart.png" TargetMode="External"/>
  <Relationship Id="rIdVmlUnsafe" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="javascript:alert(1)" TargetMode="External"/>
</Relationships>
XML;

$vmlImageDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:v="urn:schemas-microsoft-com:vml"
  xmlns:o="urn:schemas-microsoft-com:office:office">
  <w:body>
    <w:p>
      <w:r>
        <w:pict>
          <v:shape id="_x0000_i1025" alt="VML logo alt">
            <v:imagedata r:id="rIdVmlLogo" o:title="VML logo title"/>
          </v:shape>
        </w:pict>
      </w:r>
      <w:r>
        <w:pict>
          <v:shape id="_x0000_i1026" alt="VML linked alt">
            <v:imagedata r:id="rIdVmlExternal" o:title="VML linked title"/>
          </v:shape>
        </w:pict>
      </w:r>
      <w:r>
        <w:pict>
          <v:shape id="_x0000_i1027" alt="Unsafe VML alt">
            <v:imagedata r:id="rIdVmlUnsafe"/>
          </v:shape>
        </w:pict>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$drawingPlaceholderContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/diagrams/data1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramData+xml"/>
  <Override PartName="/word/diagrams/layout1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramLayout+xml"/>
  <Override PartName="/word/diagrams/quickStyle1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramStyle+xml"/>
  <Override PartName="/word/diagrams/colors1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramColors+xml"/>
</Types>
XML;

$drawingPlaceholderRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/chart1.xml"/>
  <Relationship Id="rIdDiagramData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="diagrams/data1.xml"/>
  <Relationship Id="rIdDiagramLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="diagrams/layout1.xml"/>
  <Relationship Id="rIdDiagramStyle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramQuickStyle" Target="diagrams/quickStyle1.xml"/>
  <Relationship Id="rIdDiagramColors" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramColors" Target="diagrams/colors1.xml"/>
</Relationships>
XML;

$drawingPlaceholderDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"
  xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Report includes </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="31" name="Quarterly chart" descr="Quarterly sales chart" title="Sales chart title"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">
                <c:chart r:id="rIdChart"/>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="32" name="Review workflow" descr="Review workflow diagram" title="SmartArt workflow"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram">
                <dgm:relIds r:dm="rIdDiagramData" r:lo="rIdDiagramLayout" r:qs="rIdDiagramStyle" r:cs="rIdDiagramColors"/>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$corePropertiesXml = <<<'XML'
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:dcterms="http://purl.org/dc/terms/">
  <dc:title>DOCX Import Packet</dc:title>
  <dc:creator>Migration Desk</dc:creator>
  <dc:description>Source packet for WordPress import review</dc:description>
  <dcterms:created>2026-06-03T09:30:00Z</dcterms:created>
  <cp:lastModifiedBy>Reviewer</cp:lastModifiedBy>
</cp:coreProperties>
XML;

$stylesNumberingContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
</Types>
XML;

$stylesNumberingRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$stylesNumberingDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
</Relationships>
XML;

$stylesXml = <<<'XML'
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="WpImportHeading">
    <w:name w:val="Migration Review Heading"/>
    <w:basedOn w:val="Heading2"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="ChecklistBullet">
    <w:name w:val="Checklist Bullet"/>
    <w:pPr>
      <w:numPr>
        <w:ilvl w:val="0"/>
        <w:numId w:val="11"/>
      </w:numPr>
    </w:pPr>
  </w:style>
</w:styles>
XML;

$numberingXml = <<<'XML'
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="10">
    <w:lvl w:ilvl="0">
      <w:numFmt w:val="bullet"/>
      <w:lvlText w:val="-"/>
    </w:lvl>
  </w:abstractNum>
  <w:num w:numId="11">
    <w:abstractNumId w:val="10"/>
  </w:num>
  <w:abstractNum w:abstractNumId="20">
    <w:lvl w:ilvl="0">
      <w:start w:val="3"/>
      <w:numFmt w:val="lowerLetter"/>
      <w:lvlText w:val="%1)"/>
    </w:lvl>
  </w:abstractNum>
  <w:num w:numId="12">
    <w:abstractNumId w:val="20"/>
  </w:num>
</w:numbering>
XML;

$stylesNumberingDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="WpImportHeading"/></w:pPr>
      <w:r><w:t>Review Steps</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:pStyle w:val="ChecklistBullet"/></w:pPr>
      <w:r><w:t>Confirm media map</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:pStyle w:val="ChecklistBullet"/></w:pPr>
      <w:r><w:t>Preserve footnotes</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr>
        <w:numPr><w:ilvl w:val="0"/><w:numId w:val="12"/></w:numPr>
      </w:pPr>
      <w:r><w:t>Legal review</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr>
        <w:numPr><w:ilvl w:val="0"/><w:numId w:val="12"/></w:numPr>
      </w:pPr>
      <w:r><w:t>Publish packet</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$multilevelNumberingXml = <<<'XML'
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="30">
    <w:lvl w:ilvl="0">
      <w:start w:val="1"/>
      <w:numFmt w:val="decimal"/>
      <w:lvlText w:val="%1."/>
    </w:lvl>
    <w:lvl w:ilvl="1">
      <w:start w:val="1"/>
      <w:numFmt w:val="lowerLetter"/>
      <w:lvlText w:val="%2)"/>
    </w:lvl>
    <w:lvl w:ilvl="2">
      <w:numFmt w:val="bullet"/>
      <w:lvlText w:val="*"/>
    </w:lvl>
  </w:abstractNum>
  <w:num w:numId="31">
    <w:abstractNumId w:val="30"/>
  </w:num>
</w:numbering>
XML;

$nestedNumberingDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="31"/></w:numPr></w:pPr>
      <w:r><w:t>Plan import</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="31"/></w:numPr></w:pPr>
      <w:r><w:t>Check media</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="31"/></w:numPr></w:pPr>
      <w:r><w:t>Check comments</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="2"/><w:numId w:val="31"/></w:numPr></w:pPr>
      <w:r><w:t>Resolve reviewer note</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="31"/></w:numPr></w:pPr>
      <w:r><w:t>Publish import</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$tableSpanDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tr>
        <w:tc>
          <w:tcPr><w:gridSpan w:val="2"/><w:vMerge w:val="restart"/></w:tcPr>
          <w:p><w:r><w:t>Review scope</w:t></w:r></w:p>
        </w:tc>
        <w:tc><w:p><w:r><w:t>Status</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc>
          <w:tcPr><w:gridSpan w:val="2"/><w:vMerge/></w:tcPr>
          <w:p><w:r><w:t>suppressed continuation</w:t></w:r></w:p>
        </w:tc>
        <w:tc><w:p><w:r><w:t>Ready</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Owner</w:t></w:r></w:p></w:tc>
        <w:tc>
          <w:tcPr><w:gridSpan w:val="2"/></w:tcPr>
          <w:p><w:r><w:t>Migration desk</w:t></w:r></w:p>
        </w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML;

$notesContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/>
</Types>
XML;

$notesDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdEndnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="endnotes.xml"/>
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
</Relationships>
XML;

$notesDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Audit trail </w:t></w:r>
      <w:r><w:endnoteReference w:id="5"/></w:r>
      <w:commentRangeStart w:id="9"/>
      <w:r><w:t xml:space="preserve"> commented source </w:t></w:r>
      <w:commentRangeEnd w:id="9"/>
      <w:r><w:commentReference w:id="9"/></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$endnotesXml = <<<'XML'
<w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:endnote w:id="-1" w:type="separator"><w:p><w:r><w:t>separator</w:t></w:r></w:p></w:endnote>
  <w:endnote w:id="5">
    <w:p><w:r><w:t>Endnote source audit.</w:t></w:r></w:p>
    <w:tbl>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Review table</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>kept in endnote</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
  </w:endnote>
</w:endnotes>
XML;

$commentsXml = <<<'XML'
<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:comment w:id="9" w:author="Migration Reviewer" w:initials="MR" w:date="2026-06-04T09:55:00Z">
    <w:p><w:r><w:t>Comment source audit.</w:t></w:r></w:p>
    <w:p><w:r><w:t>Keep reviewer context with the import.</w:t></w:r></w:p>
  </w:comment>
</w:comments>
XML;

$commentsOnlyDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
</Relationships>
XML;

$crossParagraphCommentRangeDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Before </w:t></w:r>
      <w:commentRangeStart w:id="10"/>
      <w:r><w:t>first paragraph note</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t>second paragraph note</w:t></w:r>
      <w:commentRangeEnd w:id="10"/>
      <w:r><w:t xml:space="preserve"> after range </w:t></w:r>
      <w:r><w:commentReference w:id="10"/></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$crossParagraphCommentsXml = <<<'XML'
<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:comment w:id="10" w:author="Migration Reviewer" w:initials="MR" w:date="2026-06-05T03:20:00Z">
    <w:p><w:r><w:t>Comment spans two DOCX paragraphs.</w:t></w:r></w:p>
  </w:comment>
</w:comments>
XML;

$mathDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Formula handoff </w:t></w:r>
      <m:oMath>
        <m:sSub>
          <m:e><m:r><m:t>x</m:t></m:r></m:e>
          <m:sub><m:r><m:t>i</m:t></m:r></m:sub>
        </m:sSub>
        <m:r><m:t xml:space="preserve"> + </m:t></m:r>
        <m:f>
          <m:num><m:r><m:t>1</m:t></m:r></m:num>
          <m:den>
            <m:rad>
              <m:e><m:r><m:t>n</m:t></m:r></m:e>
            </m:rad>
          </m:den>
        </m:f>
      </m:oMath>
      <w:r><w:t xml:space="preserve"> stays native.</w:t></w:r>
    </w:p>
    <w:p>
      <m:oMathPara>
        <m:oMath>
          <m:r><m:t xml:space="preserve">E = </m:t></m:r>
          <m:sSup>
            <m:e><m:r><m:t>mc</m:t></m:r></m:e>
            <m:sup><m:r><m:t>2</m:t></m:r></m:sup>
          </m:sSup>
        </m:oMath>
      </m:oMathPara>
    </w:p>
  </w:body>
</w:document>
XML;

$trackedChangesDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Import packet keeps </w:t></w:r>
      <w:del w:id="3" w:author="Source Editor" w:date="2026-06-04T17:45:00Z">
        <w:r><w:delText>old draft wording</w:delText></w:r>
      </w:del>
      <w:ins w:id="4" w:author="Migration Editor" w:date="2026-06-04T17:50:00Z">
        <w:r><w:t>approved copy</w:t></w:r>
      </w:ins>
      <w:r><w:t xml:space="preserve"> for reviewer handoff.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$moveTrackedChangesDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Moved review note </w:t></w:r>
      <w:moveFrom w:id="12" w:author="Source Editor" w:date="2026-06-04T18:05:00Z">
        <w:r><w:delText>from old section</w:delText></w:r>
      </w:moveFrom>
      <w:moveTo w:id="13" w:author="Migration Editor" w:date="2026-06-04T18:07:00Z">
        <w:r><w:t>to publication checklist</w:t></w:r>
      </w:moveTo>
      <w:r><w:t xml:space="preserve"> for import.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$bookmarkDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Jump to </w:t></w:r>
      <w:hyperlink w:anchor="source_packet"><w:r><w:t>source packet</w:t></w:r></w:hyperlink>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Before hidden return </w:t></w:r>
      <w:bookmarkStart w:id="0" w:name="_GoBack"/>
      <w:r><w:t>visible text</w:t></w:r>
      <w:bookmarkEnd w:id="0"/>
    </w:p>
    <w:p>
      <w:bookmarkStart w:id="7" w:name="source_packet"/>
      <w:r><w:t>Source packet target</w:t></w:r>
      <w:bookmarkEnd w:id="7"/>
      <w:r><w:t xml:space="preserve"> keeps reviewer context.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$fieldHyperlinkDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Field link to </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> HYPERLINK "https://example.test/field?post=42&amp;step=docx" \o "Field source" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:rPr><w:b/></w:rPr><w:t>source dossier</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t xml:space="preserve"> and internal </w:t></w:r>
      <w:fldSimple w:instr=' HYPERLINK \l "source_packet" '>
        <w:r><w:t>anchor jump</w:t></w:r>
      </w:fldSimple>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:bookmarkStart w:id="11" w:name="source_packet"/>
      <w:r><w:t>Source packet anchor target.</w:t></w:r>
      <w:bookmarkEnd w:id="11"/>
    </w:p>
  </w:body>
</w:document>
XML;

$fieldMetadataDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Page </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> PAGE \* Arabic </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>7</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t xml:space="preserve"> of </w:t></w:r>
      <w:fldSimple w:instr=' NUMPAGES \* Arabic '><w:r><w:t>12</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> updated </w:t></w:r>
      <w:fldSimple w:instr=' DATE \@ "MMMM d, yyyy" '><w:r><w:t>June 5, 2026</w:t></w:r></w:fldSimple>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$structuredDocumentTagXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Reviewer status </w:t></w:r>
      <w:sdt>
        <w:sdtPr>
          <w:id w:val="42"/>
          <w:alias w:val="Import Status"/>
          <w:tag w:val="import_status"/>
          <w:text/>
        </w:sdtPr>
        <w:sdtContent>
          <w:r><w:t>Ready for import</w:t></w:r>
        </w:sdtContent>
      </w:sdt>
      <w:r><w:t xml:space="preserve"> is visible.</w:t></w:r>
    </w:p>
    <w:sdt>
      <w:sdtPr>
        <w:id w:val="99"/>
        <w:alias w:val="Review Checklist"/>
        <w:tag w:val="review_checklist"/>
        <w:richText/>
        <w:lock w:val="sdtContentLocked"/>
        <w:placeholder><w:docPart w:val="DefaultPlaceholder_22675703"/></w:placeholder>
        <w:dataBinding w:xpath="/packet/review/checklist" w:storeItemID="{11111111-2222-3333-4444-555555555555}"/>
      </w:sdtPr>
      <w:sdtContent>
        <w:p><w:r><w:t>Checklist intro.</w:t></w:r></w:p>
        <w:tbl>
          <w:tr>
            <w:tc><w:p><w:r><w:t>Owner</w:t></w:r></w:p></w:tc>
            <w:tc><w:p><w:r><w:t>Migration desk</w:t></w:r></w:p></w:tc>
          </w:tr>
        </w:tbl>
      </w:sdtContent>
    </w:sdt>
    <w:p><w:r><w:t>After content controls.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

$smartTagDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Tagged </w:t></w:r>
      <w:smartTag w:uri="urn:schemas-microsoft-com:office:smarttags" w:element="PersonName">
        <w:smartTagPr>
          <w:attr w:name="normalized" w:uri="https://example.test/docx/smart-tags" w:val="Review Desk"/>
          <w:attr w:name="review-id" w:val="packet-42"/>
        </w:smartTagPr>
        <w:r><w:rPr><w:b/></w:rPr><w:t>Review Desk</w:t></w:r>
      </w:smartTag>
      <w:r><w:t xml:space="preserve"> for import.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$customXmlDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Packet category </w:t></w:r>
      <w:customXml w:uri="https://example.test/docx/custom" w:element="packet-category">
        <w:customXmlPr>
          <w:attr w:name="source-field" w:uri="https://example.test/docx/custom" w:val="category"/>
          <w:attr w:name="Review ID" w:val="packet-42"/>
        </w:customXmlPr>
        <w:r><w:rPr><w:i/></w:rPr><w:t>Policy update</w:t></w:r>
      </w:customXml>
      <w:r><w:t xml:space="preserve"> remains visible.</w:t></w:r>
    </w:p>
    <w:customXml w:uri="https://example.test/docx/custom" w:element="review-section">
      <w:customXmlPr>
        <w:attr w:name="section-id" w:val="source-review"/>
      </w:customXmlPr>
      <w:p><w:r><w:t>Source review block.</w:t></w:r></w:p>
      <w:tbl>
        <w:tr>
          <w:tc><w:p><w:r><w:t>Reviewer field</w:t></w:r></w:p></w:tc>
          <w:tc><w:p><w:r><w:t>Custom XML value</w:t></w:r></w:p></w:tc>
        </w:tr>
      </w:tbl>
    </w:customXml>
  </w:body>
</w:document>
XML;

$textboxDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:v="urn:schemas-microsoft-com:vml"
  xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Before textbox </w:t></w:r>
      <w:r>
        <w:pict>
          <v:shape id="_x0000_s1025">
            <v:textbox>
              <w:txbxContent>
                <w:p><w:r><w:t>Text box heading</w:t></w:r></w:p>
                <w:tbl>
                  <w:tr>
                    <w:tc><w:p><w:r><w:t>Reviewer field</w:t></w:r></w:p></w:tc>
                    <w:tc><w:p><w:r><w:t>VML note</w:t></w:r></w:p></w:tc>
                  </w:tr>
                </w:tbl>
              </w:txbxContent>
            </v:textbox>
          </v:shape>
        </w:pict>
      </w:r>
      <w:r><w:t xml:space="preserve"> after textbox.</w:t></w:r>
      <w:r>
        <mc:AlternateContent>
          <mc:Fallback>
            <w:pict>
              <v:rect id="_x0000_s1026">
                <v:textbox>
                  <w:txbxContent>
                    <w:p><w:r><w:t>Fallback textbox note</w:t></w:r></w:p>
                  </w:txbxContent>
                </v:textbox>
              </v:rect>
            </w:pict>
          </mc:Fallback>
        </mc:AlternateContent>
      </w:r>
      <w:r><w:t xml:space="preserve"> final text.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$alternateContentDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
  xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml">
  <w:body>
    <mc:AlternateContent>
      <mc:Choice Requires="w14">
        <w:p><w:r><w:t>Unsupported choice paragraph.</w:t></w:r></w:p>
      </mc:Choice>
      <mc:Fallback>
        <w:p><w:r><w:t>Fallback paragraph from compatibility markup.</w:t></w:r></w:p>
      </mc:Fallback>
    </mc:AlternateContent>
    <mc:AlternateContent>
      <mc:Choice Requires="w">
        <w:p><w:r><w:t>Supported WordprocessingML choice paragraph.</w:t></w:r></w:p>
      </mc:Choice>
      <mc:Fallback>
        <w:p><w:r><w:t>Unused fallback paragraph.</w:t></w:r></w:p>
      </mc:Fallback>
    </mc:AlternateContent>
    <w:p>
      <w:r><w:t xml:space="preserve">Inline </w:t></w:r>
      <mc:AlternateContent>
        <mc:Choice Requires="w14"><w:r><w:t>unsupported inline</w:t></w:r></mc:Choice>
        <mc:Fallback><w:r><w:t>fallback inline</w:t></w:r></mc:Fallback>
      </mc:AlternateContent>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <mc:AlternateContent>
        <mc:Choice Requires="w"><w:r><w:rPr><w:b/></w:rPr><w:t>supported inline</w:t></w:r></mc:Choice>
        <mc:Fallback><w:r><w:t>unused inline</w:t></w:r></mc:Fallback>
      </mc:AlternateContent>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r>
        <mc:AlternateContent>
          <mc:Choice Requires="w14"><w:t>unsupported run text</w:t></mc:Choice>
          <mc:Fallback><w:t>run fallback text</w:t></mc:Fallback>
        </mc:AlternateContent>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$symbolRunDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Checklist symbols </w:t></w:r>
      <w:r><w:sym w:font="Symbol" w:char="F061"/></w:r>
      <w:r><w:t xml:space="preserve">/</w:t></w:r>
      <w:r><w:sym w:font="Symbol" w:char="0061"/></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:sym w:font="Wingdings" w:char="F09F"/></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:sym w:font="Wingdings 2" w:char="F050"/></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:sym w:font="Wingdings 3" w:char="F066"/></w:r>
      <w:r><w:sym w:font="Unknown Symbol Font" w:char="F050"/></w:r>
      <w:r><w:t xml:space="preserve"> remain visible.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$reviewMarkupRunDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Reviewer marked </w:t></w:r>
      <w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>priority update</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:shd w:val="clear" w:fill="D9EAF7" w:color="auto"/></w:rPr><w:t>source shading</w:t></w:r>
      <w:r><w:t xml:space="preserve"> plus </w:t></w:r>
      <w:r><w:rPr><w:b/><w:highlight w:val="green"/><w:shd w:fill="FFE699"/></w:rPr><w:t>bold flagged text</w:t></w:r>
      <w:r><w:rPr><w:highlight w:val="none"/></w:rPr><w:t xml:space="preserve"> plain text.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$runLanguageDirectionDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Language review </w:t></w:r>
      <w:r><w:rPr><w:lang w:val="es-ES"/></w:rPr><w:t>Resumen</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:rtl/><w:lang w:val="ar-SA" w:bidi="ar-SA"/></w:rPr><w:t>ملف المصدر</w:t></w:r>
      <w:r><w:t xml:space="preserve"> plus </w:t></w:r>
      <w:r><w:rPr><w:b/><w:lang w:eastAsia="ja-JP"/></w:rPr><w:t>レビュー</w:t></w:r>
      <w:r><w:rPr><w:rtl w:val="0"/></w:rPr><w:t xml:space="preserve"> plain.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$paragraphBidiDirectionDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:bidi/><w:textDirection w:val="tbRl"/></w:pPr>
      <w:r><w:t>ملف المصدر review note.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:textDirection w:val="lrTb"/></w:pPr>
      <w:r><w:t>Vertical layout source note.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:bidi w:val="0"/></w:pPr>
      <w:r><w:t>Disabled bidi stays plain.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:pStyle w:val="Heading2"/><w:bidi/></w:pPr>
      <w:r><w:t>RTL Review Heading</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$paragraphLayoutDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr>
        <w:jc w:val="center"/>
        <w:spacing w:before="240" w:after="120" w:line="360" w:lineRule="auto"/>
        <w:ind w:left="720" w:right="360" w:firstLine="240" w:hanging="120"/>
        <w:keepNext/>
        <w:pageBreakBefore/>
      </w:pPr>
      <w:r><w:t>Centered review paragraph.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr>
        <w:jc w:val="both"/>
        <w:spacing w:beforeLines="100" w:afterLines="50"/>
        <w:ind w:start="480" w:end="240"/>
      </w:pPr>
      <w:r><w:t>Justified source packet paragraph.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:jc w:val="end"/><w:keepNext w:val="0"/><w:pageBreakBefore w:val="false"/></w:pPr>
      <w:r><w:t>Trailing aligned paragraph.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:pStyle w:val="Heading2"/><w:jc w:val="right"/></w:pPr>
      <w:r><w:t>Aligned review heading</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML;

$sectionPropertiesDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHeaderDefault" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>
  <Relationship Id="rIdFooterDefault" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
  <Relationship Id="rIdHeaderEven" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header-even.xml"/>
</Relationships>
XML;

$sectionPropertiesDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    <w:p>
      <w:pPr>
        <w:sectPr>
          <w:headerReference w:type="default" r:id="rIdHeaderDefault"/>
          <w:footerReference w:type="default" r:id="rIdFooterDefault"/>
          <w:pgSz w:w="12240" w:h="15840" w:orient="portrait"/>
          <w:pgMar w:top="1440" w:right="1080" w:bottom="1440" w:left="1080" w:header="720" w:footer="720" w:gutter="0"/>
          <w:cols w:num="1" w:space="720"/>
        </w:sectPr>
      </w:pPr>
      <w:r><w:t>Portrait packet section</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>Landscape continuation section.</w:t></w:r></w:p>
    <w:sectPr>
      <w:headerReference w:type="even" r:id="rIdHeaderEven"/>
      <w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>
      <w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360"/>
      <w:cols w:num="2" w:space="360" w:equalWidth="0"/>
    </w:sectPr>
  </w:body>
</w:document>
XML;

$altChunkContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="html" ContentType="text/html"/>
  <Default Extension="txt" ContentType="text/plain; charset=utf-8"/>
  <Default Extension="rtf" ContentType="application/rtf"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$altChunkDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHtmlChunk" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/review.html"/>
  <Relationship Id="rIdTextChunk" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/review.txt"/>
  <Relationship Id="rIdMissingChunk" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/missing.html"/>
  <Relationship Id="rIdExternalChunk" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="https://example.test/external-review.html" TargetMode="External"/>
  <Relationship Id="rIdUnsupportedChunk" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/source.rtf"/>
</Relationships>
XML;

$altChunkHtml = <<<'HTML'
<section data-review="docx-alt"><h2>Embedded review HTML</h2><p>Imported <strong>chunk</strong> &amp; reviewer note.</p><ul><li>Media map</li></ul></section>
HTML;

$altChunkText = "\xEF\xBB\xBFPlain review note\r\nSecond line\r\n\r\nFinal checkpoint.";

$altChunkDocumentXml = <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    <w:p><w:r><w:t>Before alternative chunk.</w:t></w:r></w:p>
    <w:altChunk r:id="rIdHtmlChunk"/>
    <w:altChunk r:id="rIdTextChunk"/>
    <w:altChunk r:id="rIdMissingChunk"/>
    <w:altChunk r:id="rIdExternalChunk"/>
    <w:altChunk r:id="rIdUnsupportedChunk"/>
    <w:p><w:r><w:t>After alternative chunk.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

$buildDocxPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $documentRelationshipsXml, $documentXml, $footnotesXml, $corePropertiesXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $documentXml],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
        ['name' => 'word/footnotes.xml', 'data' => $footnotesXml],
        ['name' => 'word/media/hero.png', 'data' => 'PNGDATA'],
        ['name' => 'docProps/core.xml', 'data' => $corePropertiesXml],
    ]);
};

$buildLinkedMediaPackage = static function () use ($linkedMediaContentTypesXml, $packageRelationshipsXml, $linkedMediaDocumentRelationshipsXml, $linkedMediaDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $linkedMediaContentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $linkedMediaDocumentXml],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $linkedMediaDocumentRelationshipsXml],
        ['name' => 'word/media/logo.png', 'data' => 'LOGO'],
        ['name' => 'word/media/portrait.jpg', 'data' => 'PORTRAIT'],
    ]);
};

$buildVmlImagePackage = static function () use ($vmlImageContentTypesXml, $packageRelationshipsXml, $vmlImageDocumentRelationshipsXml, $vmlImageDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $vmlImageContentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $vmlImageDocumentXml],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $vmlImageDocumentRelationshipsXml],
        ['name' => 'word/media/vml-logo.png', 'data' => 'VMLPNGDATA'],
    ]);
};

$buildDrawingPlaceholderPackage = static function () use (
    $drawingPlaceholderContentTypesXml,
    $packageRelationshipsXml,
    $drawingPlaceholderRelationshipsXml,
    $drawingPlaceholderDocumentXml
): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $drawingPlaceholderContentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $drawingPlaceholderDocumentXml],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $drawingPlaceholderRelationshipsXml],
        ['name' => 'word/charts/chart1.xml', 'data' => '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"/>'],
        ['name' => 'word/diagrams/data1.xml', 'data' => '<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>'],
        ['name' => 'word/diagrams/layout1.xml', 'data' => '<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>'],
        ['name' => 'word/diagrams/quickStyle1.xml', 'data' => '<dgm:styleDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>'],
        ['name' => 'word/diagrams/colors1.xml', 'data' => '<dgm:colorsDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>'],
    ]);
};

$buildStylesNumberingPackage = static function () use (
    $stylesNumberingContentTypesXml,
    $stylesNumberingRelationshipsXml,
    $stylesNumberingDocumentRelationshipsXml,
    $stylesNumberingDocumentXml,
    $stylesXml,
    $numberingXml
): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $stylesNumberingContentTypesXml],
        ['name' => '_rels/.rels', 'data' => $stylesNumberingRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $stylesNumberingDocumentXml],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $stylesNumberingDocumentRelationshipsXml],
        ['name' => 'word/styles.xml', 'data' => $stylesXml],
        ['name' => 'word/numbering.xml', 'data' => $numberingXml],
    ]);
};

$buildNestedNumberingPackage = static function () use (
    $stylesNumberingContentTypesXml,
    $stylesNumberingRelationshipsXml,
    $stylesNumberingDocumentRelationshipsXml,
    $nestedNumberingDocumentXml,
    $stylesXml,
    $multilevelNumberingXml
): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $stylesNumberingContentTypesXml],
        ['name' => '_rels/.rels', 'data' => $stylesNumberingRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $nestedNumberingDocumentXml],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $stylesNumberingDocumentRelationshipsXml],
        ['name' => 'word/styles.xml', 'data' => $stylesXml],
        ['name' => 'word/numbering.xml', 'data' => $multilevelNumberingXml],
    ]);
};

$buildTableSpanPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $tableSpanDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $tableSpanDocumentXml],
    ]);
};

$buildNotesPackage = static function () use (
    $notesContentTypesXml,
    $packageRelationshipsXml,
    $notesDocumentRelationshipsXml,
    $notesDocumentXml,
    $endnotesXml,
    $commentsXml
): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $notesContentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $notesDocumentXml],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $notesDocumentRelationshipsXml],
        ['name' => 'word/endnotes.xml', 'data' => $endnotesXml],
        ['name' => 'word/comments.xml', 'data' => $commentsXml],
    ]);
};

$buildCrossParagraphCommentRangePackage = static function () use (
    $notesContentTypesXml,
    $packageRelationshipsXml,
    $commentsOnlyDocumentRelationshipsXml,
    $crossParagraphCommentRangeDocumentXml,
    $crossParagraphCommentsXml
): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $notesContentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $crossParagraphCommentRangeDocumentXml],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $commentsOnlyDocumentRelationshipsXml],
        ['name' => 'word/comments.xml', 'data' => $crossParagraphCommentsXml],
    ]);
};

$buildMathPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $mathDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $mathDocumentXml],
    ]);
};

$buildTrackedChangesPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $trackedChangesDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $trackedChangesDocumentXml],
    ]);
};

$buildMoveTrackedChangesPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $moveTrackedChangesDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $moveTrackedChangesDocumentXml],
    ]);
};

$buildBookmarkPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $bookmarkDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $bookmarkDocumentXml],
    ]);
};

$buildFieldHyperlinkPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $fieldHyperlinkDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $fieldHyperlinkDocumentXml],
    ]);
};

$buildFieldMetadataPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $fieldMetadataDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $fieldMetadataDocumentXml],
    ]);
};

$buildStructuredDocumentTagPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $structuredDocumentTagXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $structuredDocumentTagXml],
    ]);
};

$buildSmartTagPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $smartTagDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $smartTagDocumentXml],
    ]);
};

$buildCustomXmlPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $customXmlDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $customXmlDocumentXml],
    ]);
};

$buildTextboxPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $textboxDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $textboxDocumentXml],
    ]);
};

$buildAlternateContentPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $alternateContentDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $alternateContentDocumentXml],
    ]);
};

$buildSymbolRunPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $symbolRunDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $symbolRunDocumentXml],
    ]);
};

$buildReviewMarkupRunPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $reviewMarkupRunDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $reviewMarkupRunDocumentXml],
    ]);
};

$buildRunLanguageDirectionPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $runLanguageDirectionDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $runLanguageDirectionDocumentXml],
    ]);
};

$buildParagraphBidiDirectionPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $paragraphBidiDirectionDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $paragraphBidiDirectionDocumentXml],
    ]);
};

$buildParagraphLayoutPackage = static function () use ($contentTypesXml, $packageRelationshipsXml, $paragraphLayoutDocumentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $paragraphLayoutDocumentXml],
    ]);
};

$buildSectionPropertiesPackage = static function () use (
    $contentTypesXml,
    $stylesNumberingRelationshipsXml,
    $sectionPropertiesDocumentRelationshipsXml,
    $sectionPropertiesDocumentXml
): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ['name' => '_rels/.rels', 'data' => $stylesNumberingRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $sectionPropertiesDocumentXml],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $sectionPropertiesDocumentRelationshipsXml],
        ['name' => 'word/_rels/header1.xml.rels', 'data' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdHeaderSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/header-source" TargetMode="External"/></Relationships>'],
        ['name' => 'word/header1.xml', 'data' => '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:p><w:r><w:t xml:space="preserve">Default header </w:t></w:r><w:hyperlink r:id="rIdHeaderSource"><w:r><w:t>source link</w:t></w:r></w:hyperlink></w:p></w:hdr>'],
        ['name' => 'word/footer1.xml', 'data' => '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Default footer note</w:t></w:r></w:p></w:ftr>'],
        ['name' => 'word/header-even.xml', 'data' => '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Even section header</w:t></w:r></w:p></w:hdr>'],
    ]);
};

$buildAltChunkPackage = static function () use (
    $altChunkContentTypesXml,
    $packageRelationshipsXml,
    $altChunkDocumentRelationshipsXml,
    $altChunkDocumentXml,
    $altChunkHtml,
    $altChunkText
): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => '[Content_Types].xml', 'data' => $altChunkContentTypesXml],
        ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ['name' => 'word/document.xml', 'data' => $altChunkDocumentXml],
        ['name' => 'word/_rels/document.xml.rels', 'data' => $altChunkDocumentRelationshipsXml],
        ['name' => 'word/chunks/review.html', 'data' => $altChunkHtml],
        ['name' => 'word/chunks/review.txt', 'data' => $altChunkText],
        ['name' => 'word/chunks/source.rtf', 'data' => '{\rtf1 unsupported reviewer chunk}'],
    ]);
};

return [
    'reads DOCX office document body parts and core properties from OPC package' => static function (TestRunner $t) use ($buildDocxPackage): void {
        $reader = new DocxReader();
        $result = $reader->readPackage($buildDocxPackage());
        $document = $result['document'];

        $t->same('/word/document.xml', $result['documentPart']);
        $t->same('document', $document->type);
        $t->same('docx', $document->attr('sourceFormat'));
        $t->same(4, count($document->children));
        $t->same('DOCX Import Packet', $result['metadata']['title']);
        $t->same('Migration Desk', $result['metadata']['creator']);
        $t->same('Source packet for WordPress import review', $result['metadata']['description']);
        $t->same('2026-06-03T09:30:00Z', $result['metadata']['created']);
        $t->same('Reviewer', $result['metadata']['lastModifiedBy']);
        $t->same(4, count($result['relationships']));

        $heading = $document->children[0];
        $t->same('heading', $heading->type);
        $t->same(1, $heading->attr('level'));
        $t->same('Imported Packet', $heading->attr('text'));
        $t->same('imported-packet', $heading->attr('id'));

        $paragraph = $document->children[1];
        $t->same('paragraph', $paragraph->type);
        $t->same('Reviewer ', $paragraph->children[0]->attr('text'));
        $t->same('strong', $paragraph->children[1]->type);
        $t->same('emph', $paragraph->children[1]->children[0]->type);
        $t->same('summary', $paragraph->children[1]->children[0]->children[0]->attr('text'));
        $t->same('link', $paragraph->children[3]->type);
        $t->same('https://example.test/source-packet?post=42&step=docx', $paragraph->children[3]->attr('url'));
        $t->same(' and a line', $paragraph->children[4]->attr('text'));
        $t->same('linebreak', $paragraph->children[5]->type);
        $t->same("break\ttab.", $paragraph->children[6]->attr('text'));
        $t->same('note', $paragraph->children[7]->type);
        $t->same('Footnote source audit.', $paragraph->children[7]->children[0]->children[0]->attr('text'));
    },
    'maps DOCX drawings and tables into existing Pandoc-like AST nodes' => static function (TestRunner $t) use ($buildDocxPackage): void {
        $document = (new DocxReader())->readDocument($buildDocxPackage());

        $imageParagraph = $document->children[2];
        $image = $imageParagraph->children[0];
        $t->same('paragraph', $imageParagraph->type);
        $t->same('image', $image->type);
        $t->same('word/media/hero.png', $image->attr('url'));
        $t->same('/word/media/hero.png', $image->attr('sourcePart'));
        $t->same('DOCX hero alt', $image->attr('alt'));
        $t->same('Hero title', $image->attr('title'));
        $t->same(7, $image->attr('bytes'));
        $t->same('DOCX hero alt', $image->children[0]->attr('text'));

        $table = $document->children[3];
        $t->same('table', $table->type);
        $body = $table->children[0];
        $t->same('table_body', $body->type);
        $t->same(2, count($body->children));
        $t->same('Status', $body->children[0]->children[0]->attr('text'));
        $t->same('Needs media review', $body->children[0]->children[1]->attr('text'));
        $t->same('Owner', $body->children[1]->children[0]->attr('text'));
        $t->same('Migration team', $body->children[1]->children[1]->attr('text'));
    },
    'maps DOCX drawing docPr metadata per image and preserves safe linked media' => static function (TestRunner $t) use ($buildLinkedMediaPackage): void {
        $reader = new DocxReader();
        $result = $reader->readPackage($buildLinkedMediaPackage());
        $document = $result['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same(3, count($paragraph->children));

        $logo = $paragraph->children[0];
        $t->same('image', $logo->type);
        $t->same('word/media/logo.png', $logo->attr('url'));
        $t->same('/word/media/logo.png', $logo->attr('sourcePart'));
        $t->same(false, $logo->attr('external'));
        $t->same('rIdLogo', $logo->attr('relationshipId'));
        $t->same('Logo alt', $logo->attr('alt'));
        $t->same('Logo title', $logo->attr('title'));
        $t->same(4, $logo->attr('bytes'));

        $portrait = $paragraph->children[1];
        $t->same('image', $portrait->type);
        $t->same('word/media/portrait.jpg', $portrait->attr('url'));
        $t->same('/word/media/portrait.jpg', $portrait->attr('sourcePart'));
        $t->same(false, $portrait->attr('external'));
        $t->same('rIdPortrait', $portrait->attr('relationshipId'));
        $t->same('Portrait alt', $portrait->attr('alt'));
        $t->same('Portrait title', $portrait->attr('title'));
        $t->same(8, $portrait->attr('bytes'));

        $external = $paragraph->children[2];
        $t->same('image', $external->type);
        $t->same('https://cdn.example.test/source-chart.png', $external->attr('url'));
        $t->same(true, $external->attr('external'));
        $t->same('rIdExternalImage', $external->attr('relationshipId'));
        $t->same('External chart alt', $external->attr('alt'));
        $t->same('External chart title', $external->attr('title'));
        $t->same('absolute-uri', $external->attr('externalTargetKind'));
        $t->same('https', $external->attr('externalTargetScheme'));

        $t->contains('![Logo alt](word/media/logo.png "Logo title")', $markdown);
        $t->contains('![Portrait alt](word/media/portrait.jpg "Portrait title")', $markdown);
        $t->contains('![External chart alt](https://cdn.example.test/source-chart.png "External chart title")', $markdown);
        $t->true(!str_contains($markdown, 'javascript:alert'), 'Unsafe linked image target should not render to Markdown');

        $t->contains('<img src="word/media/logo.png" alt="Logo alt" title="Logo title"/>', $blocks);
        $t->contains('<img src="word/media/portrait.jpg" alt="Portrait alt" title="Portrait title"/>', $blocks);
        $t->contains('<img src="https://cdn.example.test/source-chart.png" alt="External chart alt" title="External chart title"/>', $blocks);
        $t->true(!str_contains($blocks, 'javascript:alert'), 'Unsafe linked image target should not render to WordPress blocks');

        $media = $result['importReport']['media'];
        $t->same(4, $media['count']);
        $t->same(2, $media['embeddedCount']);
        $t->same(0, $media['missingCount']);
        $t->same(1, $media['items'][0]['usedCount']);
        $t->same(['Logo alt'], $media['items'][0]['altTexts']);
        $t->same(['Logo title'], $media['items'][0]['titles']);
        $t->same(1, $media['items'][1]['usedCount']);
        $t->same(['Portrait alt'], $media['items'][1]['altTexts']);
        $t->same(['Portrait title'], $media['items'][1]['titles']);
        $t->same(true, $media['items'][2]['external']);
        $t->same(null, $media['items'][2]['bytes']);
        $t->same(1, $media['items'][2]['usedCount']);
        $t->same(['External chart alt'], $media['items'][2]['altTexts']);
        $t->same([], $media['items'][2]['issues']);
        $t->same(true, $media['items'][3]['external']);
        $t->same(0, $media['items'][3]['usedCount']);
        $t->same(['external-target-unsafe-scheme'], $media['items'][3]['issues']);
    },
    'maps DOCX VML picture image data into media AST nodes' => static function (TestRunner $t) use ($buildVmlImagePackage): void {
        $reader = new DocxReader();
        $result = $reader->readPackage($buildVmlImagePackage());
        $document = $result['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same(2, count($paragraph->children));

        $embedded = $paragraph->children[0];
        $t->same('image', $embedded->type);
        $t->same('word/media/vml-logo.png', $embedded->attr('url'));
        $t->same('/word/media/vml-logo.png', $embedded->attr('sourcePart'));
        $t->same(false, $embedded->attr('external'));
        $t->same('rIdVmlLogo', $embedded->attr('relationshipId'));
        $t->same('VML logo alt', $embedded->attr('alt'));
        $t->same('VML logo title', $embedded->attr('title'));
        $t->same(10, $embedded->attr('bytes'));
        $t->same('VML logo alt', $embedded->children[0]->attr('text'));

        $linked = $paragraph->children[1];
        $t->same('image', $linked->type);
        $t->same('https://cdn.example.test/vml-review-chart.png', $linked->attr('url'));
        $t->same(true, $linked->attr('external'));
        $t->same('rIdVmlExternal', $linked->attr('relationshipId'));
        $t->same('VML linked alt', $linked->attr('alt'));
        $t->same('VML linked title', $linked->attr('title'));
        $t->same('absolute-uri', $linked->attr('externalTargetKind'));
        $t->same('https', $linked->attr('externalTargetScheme'));

        $t->contains('![VML logo alt](word/media/vml-logo.png "VML logo title")', $markdown);
        $t->contains('![VML linked alt](https://cdn.example.test/vml-review-chart.png "VML linked title")', $markdown);
        $t->true(!str_contains($markdown, 'javascript:alert'), 'Unsafe VML image target should not render to Markdown');

        $t->contains('<img src="word/media/vml-logo.png" alt="VML logo alt" title="VML logo title"/>', $blocks);
        $t->contains('<img src="https://cdn.example.test/vml-review-chart.png" alt="VML linked alt" title="VML linked title"/>', $blocks);
        $t->true(!str_contains($blocks, 'javascript:alert'), 'Unsafe VML image target should not render to WordPress blocks');

        $media = $result['importReport']['media'];
        $t->same(3, $media['count']);
        $t->same(1, $media['embeddedCount']);
        $t->same(0, $media['missingCount']);
        $t->same(1, $media['items'][0]['usedCount']);
        $t->same(['VML logo alt'], $media['items'][0]['altTexts']);
        $t->same(['VML logo title'], $media['items'][0]['titles']);
        $t->same(1, $media['items'][1]['usedCount']);
        $t->same(true, $media['items'][1]['external']);
        $t->same(['VML linked alt'], $media['items'][1]['altTexts']);
        $t->same(['VML linked title'], $media['items'][1]['titles']);
        $t->same(true, $media['items'][2]['external']);
        $t->same(0, $media['items'][2]['usedCount']);
        $t->same(['external-target-unsafe-scheme'], $media['items'][2]['issues']);
    },
    'preserves DOCX chart and diagram drawing references as review placeholders' => static function (TestRunner $t) use ($buildDrawingPlaceholderPackage): void {
        $reader = new DocxReader();
        $result = $reader->readPackage($buildDrawingPlaceholderPackage());
        $document = $result['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same(5, count($paragraph->children));
        $t->same('Report includes ', $paragraph->children[0]->attr('text'));

        $chart = $paragraph->children[1];
        $t->same('span', $chart->type);
        $t->same(['docx-drawing-placeholder', 'docx-drawing-chart'], $chart->attr('classes'));
        $t->same('DOCX chart: Quarterly sales chart', $chart->children[0]->attr('text'));
        $chartAttributes = $chart->attr('attributes');
        $t->same('chart', $chartAttributes['data-docx-drawing-kind']);
        $t->same('31', $chartAttributes['data-docx-docpr-id']);
        $t->same('Quarterly chart', $chartAttributes['data-docx-docpr-name']);
        $t->same('Quarterly sales chart', $chartAttributes['data-docx-docpr-descr']);
        $t->same('Sales chart title', $chartAttributes['data-docx-docpr-title']);
        $t->same('rIdChart', $chartAttributes['data-docx-relationship-id']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart', $chartAttributes['data-docx-relationship-type']);
        $t->same('/word/charts/chart1.xml', $chartAttributes['data-docx-target']);
        $t->same('/word/charts/chart1.xml', $chartAttributes['data-docx-target-part']);
        $t->same('false', $chartAttributes['data-docx-external']);
        $t->same('true', $chartAttributes['data-docx-exists']);
        $t->same('application/vnd.openxmlformats-officedocument.drawingml.chart+xml', $chartAttributes['data-docx-content-type']);

        $t->same(' and ', $paragraph->children[2]->attr('text'));
        $diagram = $paragraph->children[3];
        $t->same('span', $diagram->type);
        $t->same(['docx-drawing-placeholder', 'docx-drawing-diagram'], $diagram->attr('classes'));
        $t->same('DOCX diagram: Review workflow diagram', $diagram->children[0]->attr('text'));
        $diagramAttributes = $diagram->attr('attributes');
        $t->same('diagram', $diagramAttributes['data-docx-drawing-kind']);
        $t->same('32', $diagramAttributes['data-docx-docpr-id']);
        $t->same('Review workflow', $diagramAttributes['data-docx-docpr-name']);
        $t->same('Review workflow diagram', $diagramAttributes['data-docx-docpr-descr']);
        $t->same('rIdDiagramData', $diagramAttributes['data-docx-diagram-data-id']);
        $t->same('/word/diagrams/data1.xml', $diagramAttributes['data-docx-diagram-data-target-part']);
        $t->same('application/vnd.openxmlformats-officedocument.drawingml.diagramData+xml', $diagramAttributes['data-docx-diagram-data-content-type']);
        $t->same('rIdDiagramLayout', $diagramAttributes['data-docx-diagram-layout-id']);
        $t->same('/word/diagrams/layout1.xml', $diagramAttributes['data-docx-diagram-layout-target-part']);
        $t->same('rIdDiagramStyle', $diagramAttributes['data-docx-diagram-quick-style-id']);
        $t->same('/word/diagrams/quickStyle1.xml', $diagramAttributes['data-docx-diagram-quick-style-target-part']);
        $t->same('rIdDiagramColors', $diagramAttributes['data-docx-diagram-colors-id']);
        $t->same('/word/diagrams/colors1.xml', $diagramAttributes['data-docx-diagram-colors-target-part']);
        $t->same('.', $paragraph->children[4]->attr('text'));

        $t->contains('[DOCX chart: Quarterly sales chart]{.docx-drawing-placeholder .docx-drawing-chart', $markdown);
        $t->contains('data-docx-relationship-id="rIdChart"', $markdown);
        $t->contains('[DOCX diagram: Review workflow diagram]{.docx-drawing-placeholder .docx-drawing-diagram', $markdown);
        $t->contains('data-docx-diagram-data-id="rIdDiagramData"', $markdown);

        $t->contains('<span class="docx-drawing-placeholder docx-drawing-chart"', $blocks);
        $t->contains('data-docx-relationship-id="rIdChart"', $blocks);
        $t->contains('DOCX chart: Quarterly sales chart</span>', $blocks);
        $t->contains('<span class="docx-drawing-placeholder docx-drawing-diagram"', $blocks);
        $t->contains('data-docx-diagram-data-id="rIdDiagramData"', $blocks);
        $t->contains('DOCX diagram: Review workflow diagram</span>', $blocks);

        $report = $result['importReport'];
        $t->same(5, $report['relationshipCount']);
        $t->same(5, $report['reachableRelationshipCount']);
        $t->same([], $report['relationshipIssues']);
        $t->same(0, $report['media']['count']);
    },
    'reports DOCX media import inventory and missing media relationships' => static function (TestRunner $t) use ($buildDocxPackage): void {
        $result = (new DocxReader())->readPackage($buildDocxPackage());
        $report = $result['importReport'];

        $t->same('/word/document.xml', $report['documentPart']);
        $t->same('/word/_rels/document.xml.rels', $report['relationshipsPart']);
        $t->same(4, $report['relationshipCount']);
        $t->same(4, $report['reachableRelationshipCount']);
        $t->same(1, count($report['relationshipIssues']));
        $t->same('rIdMissing', $report['relationshipIssues'][0]['id']);
        $t->same(['missing-in-package'], $report['relationshipIssues'][0]['issues']);

        $media = $report['media'];
        $t->same(2, $media['count']);
        $t->same(1, $media['embeddedCount']);
        $t->same(1, $media['missingCount']);

        $hero = $media['items'][0];
        $t->same('/word/document.xml', $hero['source']);
        $t->same('rIdHero', $hero['id']);
        $t->same('/word/media/hero.png', $hero['target']);
        $t->same('/word/media/hero.png', $hero['targetPart']);
        $t->same('image/png', $hero['contentType']);
        $t->same(false, $hero['external']);
        $t->true($hero['exists']);
        $t->same(7, $hero['bytes']);
        $t->same(1, $hero['usedCount']);
        $t->same(['DOCX hero alt'], $hero['altTexts']);
        $t->same(['Hero title'], $hero['titles']);
        $t->same([], $hero['issues']);

        $missing = $media['items'][1];
        $t->same('rIdMissing', $missing['id']);
        $t->same('/word/media/missing.png', $missing['target']);
        $t->same('/word/media/missing.png', $missing['targetPart']);
        $t->same('image/png', $missing['contentType']);
        $t->same(false, $missing['external']);
        $t->same(false, $missing['exists']);
        $t->same(null, $missing['bytes']);
        $t->same(0, $missing['usedCount']);
        $t->same([], $missing['altTexts']);
        $t->same([], $missing['titles']);
        $t->same(['missing-in-package'], $missing['issues']);
    },
    'renders DOCX reader AST through Markdown and WordPress writers' => static function (TestRunner $t) use ($buildDocxPackage): void {
        $document = (new DocxReader())->readDocument($buildDocxPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('# Imported Packet', $markdown);
        $t->contains('Reviewer ***summary*** keeps [source link](https://example.test/source-packet?post=42&step=docx) and a line\\', $markdown);
        $t->contains('[^1]', $markdown);
        $t->contains('![DOCX hero alt](word/media/hero.png "Hero title")', $markdown);
        $t->contains('| Status | Needs media review |', $markdown);
        $t->contains('[^1]: Footnote source audit.', $markdown);

        $t->contains('<h1 id="imported-packet">Imported Packet</h1>', $blocks);
        $t->contains('<strong><em>summary</em></strong>', $blocks);
        $t->contains('<a href="https://example.test/source-packet?post=42&amp;step=docx">source link</a>', $blocks);
        $t->contains('<br/>break', $blocks);
        $t->contains('<img src="word/media/hero.png" alt="DOCX hero alt" title="Hero title"/>', $blocks);
        $t->contains('<section class="footnotes" role="doc-endnotes"><ol><li id="fn-1"><p>Footnote source audit.</p> <a href="#fnref-1" aria-label="Back to content">Back</a></li></ol></section>', $blocks);
    },
    'resolves DOCX styles and numbering into headings and AST lists' => static function (TestRunner $t) use ($buildStylesNumberingPackage): void {
        $document = (new DocxReader())->readDocument($buildStylesNumberingPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(3, count($document->children));

        $heading = $document->children[0];
        $t->same('heading', $heading->type);
        $t->same(2, $heading->attr('level'));
        $t->same('WpImportHeading', $heading->attr('style'));
        $t->same('Review Steps', $heading->attr('text'));
        $t->same('review-steps', $heading->attr('id'));

        $bulletList = $document->children[1];
        $t->same('bullet_list', $bulletList->type);
        $t->same('docx', $bulletList->attr('sourceFormat'));
        $t->same('11', $bulletList->attr('numId'));
        $t->same(0, $bulletList->attr('level'));
        $t->same('bullet', $bulletList->attr('format'));
        $t->same(2, count($bulletList->children));
        $t->same('list_item', $bulletList->children[0]->type);
        $t->same('Confirm media map', $bulletList->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Preserve footnotes', $bulletList->children[1]->children[0]->children[0]->attr('text'));

        $orderedList = $document->children[2];
        $t->same('ordered_list', $orderedList->type);
        $t->same('12', $orderedList->attr('numId'));
        $t->same('lower_alpha', $orderedList->attr('style'));
        $t->same('one_paren', $orderedList->attr('delimiter'));
        $t->same(3, $orderedList->attr('start'));
        $t->same(2, count($orderedList->children));
        $t->same('Legal review', $orderedList->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Publish packet', $orderedList->children[1]->children[0]->children[0]->attr('text'));

        $t->contains('## Review Steps', $markdown);
        $t->contains('- Confirm media map', $markdown);
        $t->contains('- Preserve footnotes', $markdown);
        $t->contains('c)  Legal review', $markdown);
        $t->contains('d)  Publish packet', $markdown);

        $t->contains('<h2 id="review-steps">Review Steps</h2>', $blocks);
        $t->contains('<ul><li>Confirm media map</li><li>Preserve footnotes</li></ul>', $blocks);
        $t->contains('<!-- wp:list {"ordered":true,"start":3} -->', $blocks);
        $t->contains('<ol start="3" type="a"><li>Legal review</li><li>Publish packet</li></ol>', $blocks);
    },
    'preserves nested DOCX numbering levels as child AST lists' => static function (TestRunner $t) use ($buildNestedNumberingPackage): void {
        $document = (new DocxReader())->readDocument($buildNestedNumberingPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children));
        $outline = $document->children[0];
        $t->same('ordered_list', $outline->type);
        $t->same('31', $outline->attr('numId'));
        $t->same(0, $outline->attr('level'));
        $t->same('decimal', $outline->attr('style'));
        $t->same('period', $outline->attr('delimiter'));
        $t->same(2, count($outline->children));

        $firstItem = $outline->children[0];
        $t->same('list_item', $firstItem->type);
        $t->same(0, $firstItem->attr('level'));
        $t->same('Plan import', $firstItem->children[0]->children[0]->attr('text'));

        $sublist = $firstItem->children[1];
        $t->same('ordered_list', $sublist->type);
        $t->same(1, $sublist->attr('level'));
        $t->same('lower_alpha', $sublist->attr('style'));
        $t->same('one_paren', $sublist->attr('delimiter'));
        $t->same(2, count($sublist->children));
        $t->same('Check media', $sublist->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Check comments', $sublist->children[1]->children[0]->children[0]->attr('text'));

        $thirdLevel = $sublist->children[1]->children[1];
        $t->same('bullet_list', $thirdLevel->type);
        $t->same(2, $thirdLevel->attr('level'));
        $t->same('bullet', $thirdLevel->attr('format'));
        $t->same('Resolve reviewer note', $thirdLevel->children[0]->children[0]->children[0]->attr('text'));

        $t->same('Publish import', $outline->children[1]->children[0]->children[0]->attr('text'));

        $t->contains("1.  Plan import\n  a)  Check media\n  b)  Check comments\n    - Resolve reviewer note\n2.  Publish import", $markdown);
        $t->contains('<!-- wp:list {"ordered":true} -->', $blocks);
        $t->contains('<ol><li>Plan import<ol type="a"><li>Check media</li><li>Check comments<ul><li>Resolve reviewer note</li></ul></li></ol></li><li>Publish import</li></ol>', $blocks);
    },
    'maps DOCX table gridSpan and vMerge cells into table span attributes' => static function (TestRunner $t) use ($buildTableSpanPackage): void {
        $document = (new DocxReader())->readDocument($buildTableSpanPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $table = $document->children[0];
        $t->same('table', $table->type);
        $body = $table->children[0];
        $geometry = $table->attr('tableGeometry');
        $t->same(true, is_array($geometry));
        $geometry = is_array($geometry) ? $geometry : [];
        $t->same('table_body', $body->type);
        $t->same(3, count($body->children));

        $firstRow = $body->children[0];
        $t->same(2, count($firstRow->children));
        $t->same('Review scope', $firstRow->children[0]->attr('text'));
        $t->same(2, $firstRow->children[0]->attr('colspan'));
        $t->same(2, $firstRow->children[0]->attr('rowspan'));
        $t->same('Status', $firstRow->children[1]->attr('text'));

        $secondRow = $body->children[1];
        $t->same(1, count($secondRow->children));
        $t->same('Ready', $secondRow->children[0]->attr('text'));

        $thirdRow = $body->children[2];
        $t->same('Owner', $thirdRow->children[0]->attr('text'));
        $t->same('Migration desk', $thirdRow->children[1]->attr('text'));
        $t->same(2, $thirdRow->children[1]->attr('colspan'));
        $t->same(3, $geometry['columnCount'] ?? null);
        $t->same(1, $geometry['summary']['sectionCount'] ?? null);
        $t->same(3, $geometry['summary']['rowCount'] ?? null);
        $t->same(5, $geometry['summary']['cellCount'] ?? null);
        $t->same(4, $geometry['summary']['coveredSlotCount'] ?? null);
        $t->same('Review scope', $geometry['coverage'][0]['text'] ?? null);
        $t->same([0, 1], $geometry['coverage'][0]['columns'] ?? null);
        $t->same('Ready', $geometry['coverage'][2]['text'] ?? null);
        $t->same(2, $geometry['coverage'][2]['column'] ?? null);
        $t->same('Migration desk', $geometry['coverage'][4]['text'] ?? null);
        $t->same('covered', $geometry['sections'][0]['rows'][1]['slots'][0]['kind'] ?? null);
        $t->same('rowspan', $geometry['sections'][0]['rows'][1]['slots'][0]['covering'] ?? null);
        json_encode($geometry, JSON_THROW_ON_ERROR);

        $normalizedMarkdown = preg_replace('/[ ]+/', ' ', $markdown) ?? $markdown;
        $t->contains('| Review scope | | Status |', $normalizedMarkdown);
        $t->contains('| | | Ready |', $normalizedMarkdown);
        $t->contains('| Owner | Migration desk | |', $normalizedMarkdown);
        $t->contains('<td colspan="2" rowspan="2"><p>Review scope</p></td><td><p>Status</p></td>', $blocks);
        $t->contains('<tr><td><p>Ready</p></td></tr>', $blocks);
        $t->contains('<td><p>Owner</p></td><td colspan="2"><p>Migration desk</p></td>', $blocks);
    },
    'maps DOCX endnotes and comments into note AST nodes' => static function (TestRunner $t) use ($buildNotesPackage): void {
        $document = (new DocxReader())->readDocument($buildNotesPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same('Audit trail ', $paragraph->children[0]->attr('text'));

        $endnote = $paragraph->children[1];
        $t->same('note', $endnote->type);
        $t->same('5', $endnote->attr('id'));
        $t->same('endnote', $endnote->attr('sourceType'));
        $t->same(2, count($endnote->children));
        $t->same('Endnote source audit.', $endnote->children[0]->children[0]->attr('text'));
        $t->same('table', $endnote->children[1]->type);
        $t->same('Review table', $endnote->children[1]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('kept in endnote', $endnote->children[1]->children[0]->children[0]->children[1]->attr('text'));

        $commentRange = $paragraph->children[2];
        $t->same('span', $commentRange->type);
        $t->same(['docx-comment-range'], $commentRange->attr('classes'));
        $t->same('9', $commentRange->attr('attributes')['data-docx-comment-id']);
        $t->same('Migration Reviewer', $commentRange->attr('attributes')['data-docx-comment-author']);
        $t->same('MR', $commentRange->attr('attributes')['data-docx-comment-initials']);
        $t->same('2026-06-04T09:55:00Z', $commentRange->attr('attributes')['data-docx-comment-date']);
        $t->same(' commented source ', $commentRange->children[0]->attr('text'));

        $comment = $paragraph->children[3];
        $t->same('note', $comment->type);
        $t->same('9', $comment->attr('id'));
        $t->same('comment', $comment->attr('sourceType'));
        $t->same('Migration Reviewer', $comment->attr('author'));
        $t->same('MR', $comment->attr('initials'));
        $t->same('2026-06-04T09:55:00Z', $comment->attr('date'));
        $t->same('Comment source audit.', $comment->children[0]->children[0]->attr('text'));
        $t->same('Keep reviewer context with the import.', $comment->children[1]->children[0]->attr('text'));

        $t->contains('Audit trail [^1][ commented source ]{.docx-comment-range data-docx-comment-id="9" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-04T09:55:00Z"}[^2]', $markdown);
        $t->contains('[^1]: Endnote source audit.', $markdown);
        $t->contains('| Review table | kept in endnote |', $markdown);
        $t->contains('[^2]: Comment source audit.', $markdown);
        $t->contains('    Keep reviewer context with the import.', $markdown);

        $t->contains('<p>Audit trail <sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup><span class="docx-comment-range" data-docx-comment-id="9" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-04T09:55:00Z"> commented source </span><sup id="fnref-2"><a href="#fn-2" role="doc-noteref">2</a></sup></p>', $blocks);
        $t->contains('<li id="fn-1"><p>Endnote source audit.</p><table><tbody><tr><td><p>Review table</p></td><td><p>kept in endnote</p></td></tr></tbody></table> <a href="#fnref-1" aria-label="Back to content">Back</a></li>', $blocks);
        $t->contains('<li id="fn-2"><p>Comment source audit.</p><p>Keep reviewer context with the import.</p> <a href="#fnref-2" aria-label="Back to content">Back</a></li>', $blocks);
    },
    'wraps DOCX comment ranges with reviewer metadata without replacing note references' => static function (TestRunner $t) use ($buildNotesPackage): void {
        $document = (new DocxReader())->readDocument($buildNotesPackage());
        $paragraph = $document->children[0];

        $commentRange = $paragraph->children[2];
        $t->same('span', $commentRange->type);
        $t->same('docx-comment-range', $commentRange->attr('classes')[0]);
        $t->same('9', $commentRange->attr('attributes')['data-docx-comment-id']);
        $t->same('Migration Reviewer', $commentRange->attr('attributes')['data-docx-comment-author']);
        $t->same('MR', $commentRange->attr('attributes')['data-docx-comment-initials']);
        $t->same('2026-06-04T09:55:00Z', $commentRange->attr('attributes')['data-docx-comment-date']);
        $t->same(' commented source ', $commentRange->children[0]->attr('text'));

        $commentNote = $paragraph->children[3];
        $t->same('note', $commentNote->type);
        $t->same('comment', $commentNote->attr('sourceType'));
        $t->same('Comment source audit.', $commentNote->children[0]->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('[ commented source ]{.docx-comment-range data-docx-comment-id="9" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-04T09:55:00Z"}[^2]', $markdown);
        $t->contains('<span class="docx-comment-range" data-docx-comment-id="9" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-04T09:55:00Z"> commented source </span><sup id="fnref-2"><a href="#fn-2" role="doc-noteref">2</a></sup>', $blocks);
    },
    'preserves DOCX comment ranges that span paragraphs before the note reference' => static function (TestRunner $t) use ($buildCrossParagraphCommentRangePackage): void {
        $document = (new DocxReader())->readDocument($buildCrossParagraphCommentRangePackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($document->children));

        $first = $document->children[0];
        $t->same('paragraph', $first->type);
        $t->same('Before ', $first->children[0]->attr('text'));
        $firstRange = $first->children[1];
        $t->same('span', $firstRange->type);
        $t->same(['docx-comment-range'], $firstRange->attr('classes'));
        $t->same('10', $firstRange->attr('attributes')['data-docx-comment-id']);
        $t->same('Migration Reviewer', $firstRange->attr('attributes')['data-docx-comment-author']);
        $t->same('MR', $firstRange->attr('attributes')['data-docx-comment-initials']);
        $t->same('2026-06-05T03:20:00Z', $firstRange->attr('attributes')['data-docx-comment-date']);
        $t->same('first paragraph note', $firstRange->children[0]->attr('text'));

        $second = $document->children[1];
        $t->same('paragraph', $second->type);
        $secondRange = $second->children[0];
        $t->same('span', $secondRange->type);
        $t->same(['docx-comment-range'], $secondRange->attr('classes'));
        $t->same('10', $secondRange->attr('attributes')['data-docx-comment-id']);
        $t->same('Migration Reviewer', $secondRange->attr('attributes')['data-docx-comment-author']);
        $t->same('second paragraph note', $secondRange->children[0]->attr('text'));
        $t->same(' after range ', $second->children[1]->attr('text'));
        $comment = $second->children[2];
        $t->same('note', $comment->type);
        $t->same('10', $comment->attr('id'));
        $t->same('comment', $comment->attr('sourceType'));
        $t->same('Comment spans two DOCX paragraphs.', $comment->children[0]->children[0]->attr('text'));

        $t->contains('Before [first paragraph note]{.docx-comment-range data-docx-comment-id="10" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-05T03:20:00Z"}', $markdown);
        $t->contains('[second paragraph note]{.docx-comment-range data-docx-comment-id="10" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-05T03:20:00Z"} after range [^1]', $markdown);
        $t->contains('[^1]: Comment spans two DOCX paragraphs.', $markdown);

        $t->contains('<p>Before <span class="docx-comment-range" data-docx-comment-id="10" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-05T03:20:00Z">first paragraph note</span></p>', $blocks);
        $t->contains('<p><span class="docx-comment-range" data-docx-comment-id="10" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-05T03:20:00Z">second paragraph note</span> after range <sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup></p>', $blocks);
        $t->contains('<li id="fn-1"><p>Comment spans two DOCX paragraphs.</p> <a href="#fnref-1" aria-label="Back to content">Back</a></li>', $blocks);
    },
    'maps DOCX OMML inline and display formulas into math AST nodes' => static function (TestRunner $t) use ($buildMathPackage): void {
        $document = (new DocxReader())->readDocument($buildMathPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($document->children));

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same('Formula handoff ', $paragraph->children[0]->attr('text'));
        $inlineMath = $paragraph->children[1];
        $t->same('math', $inlineMath->type);
        $t->same('docx-omml', $inlineMath->attr('sourceFormat'));
        $t->same(false, $inlineMath->attr('display'));
        $t->same('x_{i} + \frac{1}{\sqrt{n}}', $inlineMath->attr('text'));
        $t->same(' stays native.', $paragraph->children[2]->attr('text'));

        $displayParagraph = $document->children[1];
        $displayMath = $displayParagraph->children[0];
        $t->same('math', $displayMath->type);
        $t->same(true, $displayMath->attr('display'));
        $t->same('E = mc^{2}', $displayMath->attr('text'));

        $t->contains('Formula handoff $x_{i} + \frac{1}{\sqrt{n}}$ stays native.', $markdown);
        $t->contains('$$E = mc^{2}$$', $markdown);
        $t->contains('<p>Formula handoff <span class="math inline">\(x_{i} + \frac{1}{\sqrt{n}}\)</span> stays native.</p>', $blocks);
        $t->contains('<p><span class="math display">\[E = mc^{2}\]</span></p>', $blocks);
    },
    'preserves accepted DOCX tracked insertions and reports suppressed deletions' => static function (TestRunner $t) use ($buildTrackedChangesPackage): void {
        $reader = new DocxReader();
        $result = $reader->readPackage($buildTrackedChangesPackage());
        $document = $result['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same('Import packet keeps ', $paragraph->children[0]->attr('text'));

        $insertion = $paragraph->children[1];
        $t->same('span', $insertion->type);
        $t->same(['docx-insertion'], $insertion->attr('classes'));
        $t->same('insertion', $insertion->attr('attributes')['data-docx-change']);
        $t->same('4', $insertion->attr('attributes')['data-docx-change-id']);
        $t->same('Migration Editor', $insertion->attr('attributes')['data-docx-author']);
        $t->same('2026-06-04T17:50:00Z', $insertion->attr('attributes')['data-docx-date']);
        $t->same('approved copy', $insertion->children[0]->attr('text'));
        $t->same(' for reviewer handoff.', $paragraph->children[2]->attr('text'));

        $t->contains('Import packet keeps [approved copy]{.docx-insertion data-docx-change="insertion" data-docx-change-id="4" data-docx-author="Migration Editor" data-docx-date="2026-06-04T17:50:00Z"} for reviewer handoff.', $markdown);
        $t->true(!str_contains($markdown, 'old draft wording'), 'Deleted DOCX text should not render to Markdown');
        $t->contains('<p>Import packet keeps <span class="docx-insertion" data-docx-change="insertion" data-docx-change-id="4" data-docx-author="Migration Editor" data-docx-date="2026-06-04T17:50:00Z">approved copy</span> for reviewer handoff.</p>', $blocks);
        $t->true(!str_contains($blocks, 'old draft wording'), 'Deleted DOCX text should not render to WordPress blocks');

        $revisions = $result['importReport']['revisions'];
        $t->same(1, $revisions['insertionCount']);
        $t->same(1, $revisions['deletionCount']);
        $t->same(2, count($revisions['items']));
        $t->same('deletion', $revisions['items'][0]['type']);
        $t->same(false, $revisions['items'][0]['accepted']);
        $t->same('3', $revisions['items'][0]['id']);
        $t->same('Source Editor', $revisions['items'][0]['author']);
        $t->same('2026-06-04T17:45:00Z', $revisions['items'][0]['date']);
        $t->same('old draft wording', $revisions['items'][0]['text']);
        $t->same('insertion', $revisions['items'][1]['type']);
        $t->same(true, $revisions['items'][1]['accepted']);
        $t->same('4', $revisions['items'][1]['id']);
        $t->same('Migration Editor', $revisions['items'][1]['author']);
        $t->same('2026-06-04T17:50:00Z', $revisions['items'][1]['date']);
        $t->same('approved copy', $revisions['items'][1]['text']);
    },
    'preserves accepted DOCX moved text and reports suppressed move sources' => static function (TestRunner $t) use ($buildMoveTrackedChangesPackage): void {
        $reader = new DocxReader();
        $result = $reader->readPackage($buildMoveTrackedChangesPackage());
        $document = $result['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same('Moved review note ', $paragraph->children[0]->attr('text'));

        $moveTo = $paragraph->children[1];
        $t->same('span', $moveTo->type);
        $t->same(['docx-move-to'], $moveTo->attr('classes'));
        $t->same('move-to', $moveTo->attr('attributes')['data-docx-change']);
        $t->same('13', $moveTo->attr('attributes')['data-docx-change-id']);
        $t->same('Migration Editor', $moveTo->attr('attributes')['data-docx-author']);
        $t->same('2026-06-04T18:07:00Z', $moveTo->attr('attributes')['data-docx-date']);
        $t->same('to publication checklist', $moveTo->children[0]->attr('text'));
        $t->same(' for import.', $paragraph->children[2]->attr('text'));

        $t->contains('Moved review note [to publication checklist]{.docx-move-to data-docx-change="move-to" data-docx-change-id="13" data-docx-author="Migration Editor" data-docx-date="2026-06-04T18:07:00Z"} for import.', $markdown);
        $t->true(!str_contains($markdown, 'from old section'), 'Moved-from DOCX text should not render to Markdown');
        $t->contains('<p>Moved review note <span class="docx-move-to" data-docx-change="move-to" data-docx-change-id="13" data-docx-author="Migration Editor" data-docx-date="2026-06-04T18:07:00Z">to publication checklist</span> for import.</p>', $blocks);
        $t->true(!str_contains($blocks, 'from old section'), 'Moved-from DOCX text should not render to WordPress blocks');

        $revisions = $result['importReport']['revisions'];
        $t->same(1, $revisions['insertionCount']);
        $t->same(1, $revisions['deletionCount']);
        $t->same(2, count($revisions['items']));
        $t->same('move-from', $revisions['items'][0]['type']);
        $t->same(false, $revisions['items'][0]['accepted']);
        $t->same('12', $revisions['items'][0]['id']);
        $t->same('Source Editor', $revisions['items'][0]['author']);
        $t->same('2026-06-04T18:05:00Z', $revisions['items'][0]['date']);
        $t->same('from old section', $revisions['items'][0]['text']);
        $t->same('move-to', $revisions['items'][1]['type']);
        $t->same(true, $revisions['items'][1]['accepted']);
        $t->same('13', $revisions['items'][1]['id']);
        $t->same('Migration Editor', $revisions['items'][1]['author']);
        $t->same('2026-06-04T18:07:00Z', $revisions['items'][1]['date']);
        $t->same('to publication checklist', $revisions['items'][1]['text']);
    },
    'preserves DOCX bookmarks as anchor spans for internal hyperlink targets' => static function (TestRunner $t) use ($buildBookmarkPackage): void {
        $document = (new DocxReader())->readDocument($buildBookmarkPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $jump = $document->children[0];
        $t->same('paragraph', $jump->type);
        $t->same('Jump to ', $jump->children[0]->attr('text'));
        $t->same('link', $jump->children[1]->type);
        $t->same('#source_packet', $jump->children[1]->attr('url'));
        $t->same('source packet', $jump->children[1]->children[0]->attr('text'));
        $t->same('.', $jump->children[2]->attr('text'));

        $hiddenReturn = $document->children[1];
        $t->same(1, count($hiddenReturn->children));
        $t->same('Before hidden return visible text', $hiddenReturn->children[0]->attr('text'));

        $target = $document->children[2];
        $anchor = $target->children[0];
        $t->same('span', $anchor->type);
        $t->same('source_packet', $anchor->attr('id'));
        $t->same(['anchor'], $anchor->attr('classes'));
        $t->same([], $anchor->children);
        $t->same('Source packet target keeps reviewer context.', $target->children[1]->attr('text'));

        $t->contains('Jump to [source packet](#source_packet).', $markdown);
        $t->contains('[]{#source_packet .anchor}Source packet target keeps reviewer context.', $markdown);
        $t->true(!str_contains($markdown, '_GoBack'), 'Dummy Word return bookmarks should not render to Markdown');

        $t->contains('<a href="#source_packet">source packet</a>', $blocks);
        $t->contains('<span id="source_packet" class="anchor"></span>Source packet target keeps reviewer context.', $blocks);
        $t->true(!str_contains($blocks, '_GoBack'), 'Dummy Word return bookmarks should not render to WordPress blocks');
    },
    'maps DOCX field-code hyperlinks to normal link AST nodes' => static function (TestRunner $t) use ($buildFieldHyperlinkPackage): void {
        $document = (new DocxReader())->readDocument($buildFieldHyperlinkPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same('Field link to ', $paragraph->children[0]->attr('text'));

        $external = $paragraph->children[1];
        $t->same('link', $external->type);
        $t->same('https://example.test/field?post=42&step=docx', $external->attr('url'));
        $t->same('Field source', $external->attr('title'));
        $t->same('strong', $external->children[0]->type);
        $t->same('source dossier', $external->children[0]->children[0]->attr('text'));

        $t->same(' and internal ', $paragraph->children[2]->attr('text'));
        $internal = $paragraph->children[3];
        $t->same('link', $internal->type);
        $t->same('#source_packet', $internal->attr('url'));
        $t->same('anchor jump', $internal->children[0]->attr('text'));
        $t->same('.', $paragraph->children[4]->attr('text'));

        $target = $document->children[1];
        $t->same('span', $target->children[0]->type);
        $t->same('source_packet', $target->children[0]->attr('id'));
        $t->same('Source packet anchor target.', $target->children[1]->attr('text'));

        $t->contains('Field link to [**source dossier**](https://example.test/field?post=42&step=docx "Field source") and internal [anchor jump](#source_packet).', $markdown);
        $t->contains('[]{#source_packet .anchor}Source packet anchor target.', $markdown);
        $t->true(!str_contains($markdown, 'HYPERLINK'), 'DOCX field instructions should not render to Markdown');

        $t->contains('<a href="https://example.test/field?post=42&amp;step=docx" title="Field source"><strong>source dossier</strong></a>', $blocks);
        $t->contains('<a href="#source_packet">anchor jump</a>', $blocks);
        $t->contains('<span id="source_packet" class="anchor"></span>Source packet anchor target.', $blocks);
        $t->true(!str_contains($blocks, 'HYPERLINK'), 'DOCX field instructions should not render to WordPress blocks');
    },
    'preserves DOCX non-hyperlink field provenance around displayed results' => static function (TestRunner $t) use ($buildFieldMetadataPackage): void {
        $document = (new DocxReader())->readDocument($buildFieldMetadataPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same('Page ', $paragraph->children[0]->attr('text'));

        $page = $paragraph->children[1];
        $t->same('span', $page->type);
        $t->same(['docx-field', 'docx-field-page'], $page->attr('classes'));
        $t->same('page', $page->attr('attributes')['data-docx-field']);
        $t->same('PAGE \* Arabic', $page->attr('attributes')['data-docx-field-instruction']);
        $t->same('Arabic', $page->attr('attributes')['data-docx-field-format']);
        $t->same('7', $page->children[0]->attr('text'));

        $t->same(' of ', $paragraph->children[2]->attr('text'));
        $pageCount = $paragraph->children[3];
        $t->same('span', $pageCount->type);
        $t->same(['docx-field', 'docx-field-numpages'], $pageCount->attr('classes'));
        $t->same('numpages', $pageCount->attr('attributes')['data-docx-field']);
        $t->same('NUMPAGES \* Arabic', $pageCount->attr('attributes')['data-docx-field-instruction']);
        $t->same('Arabic', $pageCount->attr('attributes')['data-docx-field-format']);
        $t->same('12', $pageCount->children[0]->attr('text'));

        $t->same(' updated ', $paragraph->children[4]->attr('text'));
        $date = $paragraph->children[5];
        $t->same('span', $date->type);
        $t->same(['docx-field', 'docx-field-date'], $date->attr('classes'));
        $t->same('date', $date->attr('attributes')['data-docx-field']);
        $t->same('DATE \@ "MMMM d, yyyy"', $date->attr('attributes')['data-docx-field-instruction']);
        $t->same('MMMM d, yyyy', $date->attr('attributes')['data-docx-field-format']);
        $t->same('June 5, 2026', $date->children[0]->attr('text'));
        $t->same('.', $paragraph->children[6]->attr('text'));

        $t->contains('Page [7]{.docx-field .docx-field-page data-docx-field="page" data-docx-field-instruction="PAGE \\\\* Arabic" data-docx-field-format="Arabic"} of [12]{.docx-field .docx-field-numpages data-docx-field="numpages"', $markdown);
        $t->contains('updated [June 5, 2026]{.docx-field .docx-field-date data-docx-field="date" data-docx-field-instruction="DATE \\\\@ \\"MMMM d, yyyy\\"" data-docx-field-format="MMMM d, yyyy"}.', $markdown);

        $t->contains('<span class="docx-field docx-field-page" data-docx-field="page" data-docx-field-instruction="PAGE \* Arabic" data-docx-field-format="Arabic">7</span>', $blocks);
        $t->contains('<span class="docx-field docx-field-numpages" data-docx-field="numpages" data-docx-field-instruction="NUMPAGES \* Arabic" data-docx-field-format="Arabic">12</span>', $blocks);
        $t->contains('<span class="docx-field docx-field-date" data-docx-field="date" data-docx-field-instruction="DATE \@ &quot;MMMM d, yyyy&quot;" data-docx-field-format="MMMM d, yyyy">June 5, 2026</span>', $blocks);
    },
    'preserves DOCX structured document tag content controls with reviewer metadata' => static function (TestRunner $t) use ($buildStructuredDocumentTagPackage): void {
        $document = (new DocxReader())->readDocument($buildStructuredDocumentTagPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(3, count($document->children));

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same('Reviewer status ', $paragraph->children[0]->attr('text'));
        $inlineControl = $paragraph->children[1];
        $t->same('span', $inlineControl->type);
        $t->same(['docx-content-control', 'docx-content-control-text'], $inlineControl->attr('classes'));
        $t->same('42', $inlineControl->attr('attributes')['data-docx-sdt-id']);
        $t->same('Import Status', $inlineControl->attr('attributes')['data-docx-sdt-alias']);
        $t->same('import_status', $inlineControl->attr('attributes')['data-docx-sdt-tag']);
        $t->same('text', $inlineControl->attr('attributes')['data-docx-sdt-type']);
        $t->same('Ready for import', $inlineControl->children[0]->attr('text'));
        $t->same(' is visible.', $paragraph->children[2]->attr('text'));

        $blockControl = $document->children[1];
        $t->same('div', $blockControl->type);
        $t->same(['docx-content-control', 'docx-content-control-rich-text'], $blockControl->attr('classes'));
        $t->same('99', $blockControl->attr('attributes')['data-docx-sdt-id']);
        $t->same('Review Checklist', $blockControl->attr('attributes')['data-docx-sdt-alias']);
        $t->same('review_checklist', $blockControl->attr('attributes')['data-docx-sdt-tag']);
        $t->same('rich-text', $blockControl->attr('attributes')['data-docx-sdt-type']);
        $t->same('sdtContentLocked', $blockControl->attr('attributes')['data-docx-sdt-lock']);
        $t->same('DefaultPlaceholder_22675703', $blockControl->attr('attributes')['data-docx-sdt-placeholder']);
        $t->same('/packet/review/checklist', $blockControl->attr('attributes')['data-docx-sdt-xpath']);
        $t->same('{11111111-2222-3333-4444-555555555555}', $blockControl->attr('attributes')['data-docx-sdt-store-item-id']);
        $t->same(2, count($blockControl->children));
        $t->same('Checklist intro.', $blockControl->children[0]->children[0]->attr('text'));
        $t->same('table', $blockControl->children[1]->type);
        $t->same('Owner', $blockControl->children[1]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Migration desk', $blockControl->children[1]->children[0]->children[0]->children[1]->attr('text'));

        $t->same('After content controls.', $document->children[2]->children[0]->attr('text'));

        $t->contains('Reviewer status [Ready for import]{.docx-content-control .docx-content-control-text data-docx-sdt-id="42" data-docx-sdt-alias="Import Status" data-docx-sdt-tag="import_status" data-docx-sdt-type="text"} is visible.', $markdown);
        $t->contains('::: {.docx-content-control .docx-content-control-rich-text data-docx-sdt-id="99" data-docx-sdt-alias="Review Checklist" data-docx-sdt-tag="review_checklist"', $markdown);
        $t->contains('data-docx-sdt-type="rich-text"', $markdown);
        $t->contains('data-docx-sdt-xpath="/packet/review/checklist"', $markdown);
        $t->contains('| Owner | Migration desk |', $markdown);

        $t->contains('<span class="docx-content-control docx-content-control-text" data-docx-sdt-id="42" data-docx-sdt-alias="Import Status" data-docx-sdt-tag="import_status" data-docx-sdt-type="text">Ready for import</span>', $blocks);
        $t->contains('<div class="docx-content-control docx-content-control-rich-text" data-docx-sdt-id="99" data-docx-sdt-alias="Review Checklist" data-docx-sdt-tag="review_checklist"', $blocks);
        $t->contains('data-docx-sdt-type="rich-text"', $blocks);
        $t->contains('data-docx-sdt-xpath="/packet/review/checklist"', $blocks);
        $t->contains('<table><tbody><tr><td><p>Owner</p></td><td><p>Migration desk</p></td></tr></tbody></table>', $blocks);
    },
    'preserves DOCX smart tag metadata around visible inline text' => static function (TestRunner $t) use ($buildSmartTagPackage): void {
        $document = (new DocxReader())->readDocument($buildSmartTagPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same('Tagged ', $paragraph->children[0]->attr('text'));

        $smartTag = $paragraph->children[1];
        $t->same('span', $smartTag->type);
        $t->same(['docx-smart-tag'], $smartTag->attr('classes'));
        $t->same('urn:schemas-microsoft-com:office:smarttags', $smartTag->attr('attributes')['data-docx-smart-tag-uri']);
        $t->same('PersonName', $smartTag->attr('attributes')['data-docx-smart-tag-element']);
        $t->same('Review Desk', $smartTag->attr('attributes')['data-docx-smart-tag-prop-normalized']);
        $t->same('https://example.test/docx/smart-tags', $smartTag->attr('attributes')['data-docx-smart-tag-prop-normalized-uri']);
        $t->same('packet-42', $smartTag->attr('attributes')['data-docx-smart-tag-prop-review-id']);
        $t->same('strong', $smartTag->children[0]->type);
        $t->same('Review Desk', $smartTag->children[0]->children[0]->attr('text'));
        $t->same(' for import.', $paragraph->children[2]->attr('text'));

        $t->contains('Tagged [**Review Desk**]{.docx-smart-tag data-docx-smart-tag-uri="urn:schemas-microsoft-com:office:smarttags" data-docx-smart-tag-element="PersonName" data-docx-smart-tag-prop-normalized="Review Desk" data-docx-smart-tag-prop-normalized-uri="https://example.test/docx/smart-tags" data-docx-smart-tag-prop-review-id="packet-42"} for import.', $markdown);
        $t->contains('<p>Tagged <span class="docx-smart-tag" data-docx-smart-tag-uri="urn:schemas-microsoft-com:office:smarttags" data-docx-smart-tag-element="PersonName" data-docx-smart-tag-prop-normalized="Review Desk" data-docx-smart-tag-prop-normalized-uri="https://example.test/docx/smart-tags" data-docx-smart-tag-prop-review-id="packet-42"><strong>Review Desk</strong></span> for import.</p>', $blocks);
    },
    'preserves DOCX custom XML wrappers with visible content and metadata' => static function (TestRunner $t) use ($buildCustomXmlPackage): void {
        $document = (new DocxReader())->readDocument($buildCustomXmlPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($document->children));

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same('Packet category ', $paragraph->children[0]->attr('text'));
        $inlineCustom = $paragraph->children[1];
        $t->same('span', $inlineCustom->type);
        $t->same(['docx-custom-xml'], $inlineCustom->attr('classes'));
        $t->same('https://example.test/docx/custom', $inlineCustom->attr('attributes')['data-docx-custom-xml-uri']);
        $t->same('packet-category', $inlineCustom->attr('attributes')['data-docx-custom-xml-element']);
        $t->same('category', $inlineCustom->attr('attributes')['data-docx-custom-xml-prop-source-field']);
        $t->same('https://example.test/docx/custom', $inlineCustom->attr('attributes')['data-docx-custom-xml-prop-source-field-uri']);
        $t->same('packet-42', $inlineCustom->attr('attributes')['data-docx-custom-xml-prop-review-id']);
        $t->same('emph', $inlineCustom->children[0]->type);
        $t->same('Policy update', $inlineCustom->children[0]->children[0]->attr('text'));
        $t->same(' remains visible.', $paragraph->children[2]->attr('text'));

        $blockCustom = $document->children[1];
        $t->same('div', $blockCustom->type);
        $t->same(['docx-custom-xml'], $blockCustom->attr('classes'));
        $t->same('review-section', $blockCustom->attr('attributes')['data-docx-custom-xml-element']);
        $t->same('source-review', $blockCustom->attr('attributes')['data-docx-custom-xml-prop-section-id']);
        $t->same(2, count($blockCustom->children));
        $t->same('Source review block.', $blockCustom->children[0]->children[0]->attr('text'));
        $t->same('table', $blockCustom->children[1]->type);
        $t->same('Reviewer field', $blockCustom->children[1]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Custom XML value', $blockCustom->children[1]->children[0]->children[0]->children[1]->attr('text'));

        $t->contains('Packet category [*Policy update*]{.docx-custom-xml data-docx-custom-xml-uri="https://example.test/docx/custom" data-docx-custom-xml-element="packet-category" data-docx-custom-xml-prop-source-field="category" data-docx-custom-xml-prop-source-field-uri="https://example.test/docx/custom" data-docx-custom-xml-prop-review-id="packet-42"} remains visible.', $markdown);
        $t->contains('::: {.docx-custom-xml data-docx-custom-xml-uri="https://example.test/docx/custom" data-docx-custom-xml-element="review-section" data-docx-custom-xml-prop-section-id="source-review"}', $markdown);
        $t->contains('| Reviewer field | Custom XML value |', $markdown);

        $t->contains('<span class="docx-custom-xml" data-docx-custom-xml-uri="https://example.test/docx/custom" data-docx-custom-xml-element="packet-category" data-docx-custom-xml-prop-source-field="category" data-docx-custom-xml-prop-source-field-uri="https://example.test/docx/custom" data-docx-custom-xml-prop-review-id="packet-42"><em>Policy update</em></span>', $blocks);
        $t->contains('<div class="docx-custom-xml" data-docx-custom-xml-uri="https://example.test/docx/custom" data-docx-custom-xml-element="review-section" data-docx-custom-xml-prop-section-id="source-review">', $blocks);
        $t->contains('<p>Source review block.</p><table><tbody><tr><td><p>Reviewer field</p></td><td><p>Custom XML value</p></td></tr></tbody></table>', $blocks);
    },
    'unwraps DOCX VML textbox content into body blocks in paragraph order' => static function (TestRunner $t) use ($buildTextboxPackage): void {
        $document = (new DocxReader())->readDocument($buildTextboxPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(6, count($document->children));
        $t->same('Before textbox ', $document->children[0]->children[0]->attr('text'));
        $t->same('Text box heading', $document->children[1]->children[0]->attr('text'));

        $table = $document->children[2];
        $t->same('table', $table->type);
        $t->same('Reviewer field', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('VML note', $table->children[0]->children[0]->children[1]->attr('text'));
        $t->same(2, $table->attr('tableGeometry')['summary']['cellCount'] ?? null);

        $t->same(' after textbox.', $document->children[3]->children[0]->attr('text'));
        $t->same('Fallback textbox note', $document->children[4]->children[0]->attr('text'));
        $t->same(' final text.', $document->children[5]->children[0]->attr('text'));

        $t->contains("Before textbox \n\nText box heading", $markdown);
        $t->contains('| Reviewer field | VML note |', $markdown);
        $t->contains("after textbox.\n\nFallback textbox note", $markdown);
        $t->contains('<p>Before textbox </p>', $blocks);
        $t->contains('<p>Text box heading</p>', $blocks);
        $t->contains('<table><tbody><tr><td><p>Reviewer field</p></td><td><p>VML note</p></td></tr></tbody></table>', $blocks);
        $t->contains('<p> after textbox.</p>', $blocks);
        $t->contains('<p>Fallback textbox note</p>', $blocks);
        $t->contains('<p> final text.</p>', $blocks);
    },
    'selects DOCX markup compatibility alternate content in body and run contexts' => static function (TestRunner $t) use ($buildAlternateContentPackage): void {
        $document = (new DocxReader())->readDocument($buildAlternateContentPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, count($document->children));
        $t->same('Fallback paragraph from compatibility markup.', $document->children[0]->children[0]->attr('text'));
        $t->same('Supported WordprocessingML choice paragraph.', $document->children[1]->children[0]->attr('text'));

        $paragraph = $document->children[2];
        $t->same('paragraph', $paragraph->type);
        $t->same('Inline fallback inline and ', $paragraph->children[0]->attr('text'));
        $t->same('strong', $paragraph->children[1]->type);
        $t->same('supported inline', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('.', $paragraph->children[2]->attr('text'));
        $t->same('run fallback text', $document->children[3]->children[0]->attr('text'));

        $t->contains('Fallback paragraph from compatibility markup.', $markdown);
        $t->contains('Supported WordprocessingML choice paragraph.', $markdown);
        $t->contains('Inline fallback inline and **supported inline**.', $markdown);
        $t->contains('run fallback text', $markdown);
        $t->true(!str_contains($markdown, 'Unsupported choice paragraph'), 'Unsupported DOCX mc:Choice text should not render to Markdown');
        $t->true(!str_contains($markdown, 'unused inline'), 'Unused DOCX mc:Fallback text should not render to Markdown when a supported choice exists');

        $t->contains('<p>Fallback paragraph from compatibility markup.</p>', $blocks);
        $t->contains('<p>Supported WordprocessingML choice paragraph.</p>', $blocks);
        $t->contains('<p>Inline fallback inline and <strong>supported inline</strong>.</p>', $blocks);
        $t->contains('<p>run fallback text</p>', $blocks);
        $t->true(!str_contains($blocks, 'Unsupported choice paragraph'), 'Unsupported DOCX mc:Choice text should not render to WordPress blocks');
        $t->true(!str_contains($blocks, 'unused inline'), 'Unused DOCX mc:Fallback text should not render to WordPress blocks when a supported choice exists');
    },
    'decodes DOCX symbol font runs into Unicode text' => static function (TestRunner $t) use ($buildSymbolRunPackage): void {
        $document = (new DocxReader())->readDocument($buildSymbolRunPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same(1, count($paragraph->children));
        $t->same('Checklist symbols α/α • ✓ ← remain visible.', $paragraph->children[0]->attr('text'));

        $t->contains('Checklist symbols α/α • ✓ ← remain visible.', $markdown);
        $t->contains('<p>Checklist symbols α/α • ✓ ← remain visible.</p>', $blocks);
        $t->true(!str_contains($markdown, 'Unknown Symbol Font'), 'Unknown DOCX symbol fonts should not leak into Markdown output');
        $t->true(!str_contains($blocks, 'Unknown Symbol Font'), 'Unknown DOCX symbol fonts should not leak into WordPress blocks');
    },
    'preserves DOCX highlighted and shaded reviewer run markup as spans' => static function (TestRunner $t) use ($buildReviewMarkupRunPackage): void {
        $document = (new DocxReader())->readDocument($buildReviewMarkupRunPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same(7, count($paragraph->children));
        $t->same('Reviewer marked ', $paragraph->children[0]->attr('text'));

        $highlight = $paragraph->children[1];
        $t->same('span', $highlight->type);
        $t->same(['docx-highlight', 'docx-highlight-yellow'], $highlight->attr('classes'));
        $t->same('yellow', $highlight->attr('attributes')['data-docx-highlight']);
        $t->same('priority update', $highlight->children[0]->attr('text'));

        $t->same(' and ', $paragraph->children[2]->attr('text'));
        $shading = $paragraph->children[3];
        $t->same('span', $shading->type);
        $t->same(['docx-shading'], $shading->attr('classes'));
        $t->same('clear', $shading->attr('attributes')['data-docx-shading-val']);
        $t->same('D9EAF7', $shading->attr('attributes')['data-docx-shading-fill']);
        $t->same('auto', $shading->attr('attributes')['data-docx-shading-color']);
        $t->same('source shading', $shading->children[0]->attr('text'));

        $t->same(' plus ', $paragraph->children[4]->attr('text'));
        $combined = $paragraph->children[5];
        $t->same('span', $combined->type);
        $t->same(['docx-highlight', 'docx-highlight-green', 'docx-shading'], $combined->attr('classes'));
        $t->same('green', $combined->attr('attributes')['data-docx-highlight']);
        $t->same('FFE699', $combined->attr('attributes')['data-docx-shading-fill']);
        $t->same('strong', $combined->children[0]->type);
        $t->same('bold flagged text', $combined->children[0]->children[0]->attr('text'));
        $t->same(' plain text.', $paragraph->children[6]->attr('text'));

        $t->contains('[priority update]{.docx-highlight .docx-highlight-yellow data-docx-highlight="yellow"}', $markdown);
        $t->contains('[source shading]{.docx-shading data-docx-shading-val="clear" data-docx-shading-fill="D9EAF7" data-docx-shading-color="auto"}', $markdown);
        $t->contains('[**bold flagged text**]{.docx-highlight .docx-highlight-green .docx-shading data-docx-highlight="green" data-docx-shading-fill="FFE699"} plain text.', $markdown);

        $t->contains('<span class="docx-highlight docx-highlight-yellow" data-docx-highlight="yellow">priority update</span>', $blocks);
        $t->contains('<span class="docx-shading" data-docx-shading-val="clear" data-docx-shading-fill="D9EAF7" data-docx-shading-color="auto">source shading</span>', $blocks);
        $t->contains('<span class="docx-highlight docx-highlight-green docx-shading" data-docx-highlight="green" data-docx-shading-fill="FFE699"><strong>bold flagged text</strong></span> plain text.', $blocks);
        $t->true(!str_contains($markdown, 'data-docx-highlight="none"'), 'DOCX highlight none should not create reviewer markup');
        $t->true(!str_contains($blocks, 'data-docx-highlight="none"'), 'DOCX highlight none should not create WordPress reviewer markup');
    },
    'preserves DOCX run language and RTL metadata as reviewer spans' => static function (TestRunner $t) use ($buildRunLanguageDirectionPackage): void {
        $document = (new DocxReader())->readDocument($buildRunLanguageDirectionPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same(7, count($paragraph->children));
        $t->same('Language review ', $paragraph->children[0]->attr('text'));

        $spanish = $paragraph->children[1];
        $t->same('span', $spanish->type);
        $t->same(['docx-language'], $spanish->attr('classes'));
        $t->same('es-ES', $spanish->attr('attributes')['lang']);
        $t->same('es-ES', $spanish->attr('attributes')['data-docx-lang']);
        $t->same('Resumen', $spanish->children[0]->attr('text'));

        $t->same(' and ', $paragraph->children[2]->attr('text'));
        $arabic = $paragraph->children[3];
        $t->same('span', $arabic->type);
        $t->same(['docx-language', 'docx-rtl'], $arabic->attr('classes'));
        $t->same('ar-SA', $arabic->attr('attributes')['lang']);
        $t->same('ar-SA', $arabic->attr('attributes')['data-docx-lang']);
        $t->same('ar-SA', $arabic->attr('attributes')['data-docx-lang-bidi']);
        $t->same('rtl', $arabic->attr('attributes')['dir']);
        $t->same('ملف المصدر', $arabic->children[0]->attr('text'));

        $t->same(' plus ', $paragraph->children[4]->attr('text'));
        $japanese = $paragraph->children[5];
        $t->same('span', $japanese->type);
        $t->same(['docx-language'], $japanese->attr('classes'));
        $t->same('ja-JP', $japanese->attr('attributes')['lang']);
        $t->same('ja-JP', $japanese->attr('attributes')['data-docx-lang-east-asia']);
        $t->same('strong', $japanese->children[0]->type);
        $t->same('レビュー', $japanese->children[0]->children[0]->attr('text'));
        $t->same(' plain.', $paragraph->children[6]->attr('text'));

        $t->contains('[Resumen]{.docx-language data-docx-lang="es-ES" lang="es-ES"}', $markdown);
        $t->contains('[ملف المصدر]{.docx-language .docx-rtl data-docx-lang="ar-SA" data-docx-lang-bidi="ar-SA" lang="ar-SA" dir="rtl"}', $markdown);
        $t->contains('[**レビュー**]{.docx-language data-docx-lang-east-asia="ja-JP" lang="ja-JP"} plain.', $markdown);

        $t->contains('<span class="docx-language" data-docx-lang="es-ES" lang="es-ES">Resumen</span>', $blocks);
        $t->contains('<span class="docx-language docx-rtl" data-docx-lang="ar-SA" data-docx-lang-bidi="ar-SA" lang="ar-SA" dir="rtl">ملف المصدر</span>', $blocks);
        $t->contains('<span class="docx-language" data-docx-lang-east-asia="ja-JP" lang="ja-JP"><strong>レビュー</strong></span> plain.', $blocks);
        $t->true(!str_contains($markdown, 'dir="ltr"'), 'Disabled DOCX w:rtl should not create direction metadata');
        $t->true(!str_contains($blocks, 'dir="ltr"'), 'Disabled DOCX w:rtl should not create WordPress direction metadata');
    },
    'preserves DOCX paragraph bidi and text direction metadata as reviewer spans' => static function (TestRunner $t) use ($buildParagraphBidiDirectionPackage): void {
        $document = (new DocxReader())->readDocument($buildParagraphBidiDirectionPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, count($document->children));

        $paragraph = $document->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same(1, count($paragraph->children));
        $bidiSpan = $paragraph->children[0];
        $t->same('span', $bidiSpan->type);
        $t->same(['docx-paragraph-bidi', 'docx-rtl', 'docx-text-direction', 'docx-text-direction-tbrl'], $bidiSpan->attr('classes'));
        $t->same('true', $bidiSpan->attr('attributes')['data-docx-paragraph-bidi']);
        $t->same('rtl', $bidiSpan->attr('attributes')['dir']);
        $t->same('tbRl', $bidiSpan->attr('attributes')['data-docx-text-direction']);
        $t->same('ملف المصدر review note.', $bidiSpan->children[0]->attr('text'));

        $directionOnly = $document->children[1]->children[0];
        $t->same('span', $directionOnly->type);
        $t->same(['docx-text-direction', 'docx-text-direction-lrtb'], $directionOnly->attr('classes'));
        $t->same('lrTb', $directionOnly->attr('attributes')['data-docx-text-direction']);
        $t->same('Vertical layout source note.', $directionOnly->children[0]->attr('text'));

        $disabled = $document->children[2];
        $t->same('paragraph', $disabled->type);
        $t->same('text', $disabled->children[0]->type);
        $t->same('Disabled bidi stays plain.', $disabled->children[0]->attr('text'));

        $heading = $document->children[3];
        $t->same('heading', $heading->type);
        $t->same(2, $heading->attr('level'));
        $t->same('rtl-review-heading', $heading->attr('id'));
        $headingSpan = $heading->children[0];
        $t->same('span', $headingSpan->type);
        $t->same(['docx-paragraph-bidi', 'docx-rtl'], $headingSpan->attr('classes'));
        $t->same('rtl', $headingSpan->attr('attributes')['dir']);
        $t->same('RTL Review Heading', $headingSpan->children[0]->attr('text'));

        $t->contains('[ملف المصدر review note.]{.docx-paragraph-bidi .docx-rtl .docx-text-direction .docx-text-direction-tbrl data-docx-paragraph-bidi="true" dir="rtl" data-docx-text-direction="tbRl"}', $markdown);
        $t->contains('[Vertical layout source note.]{.docx-text-direction .docx-text-direction-lrtb data-docx-text-direction="lrTb"}', $markdown);
        $t->contains('Disabled bidi stays plain.', $markdown);
        $t->contains('## [RTL Review Heading]{.docx-paragraph-bidi .docx-rtl data-docx-paragraph-bidi="true" dir="rtl"}', $markdown);

        $t->contains('<p><span class="docx-paragraph-bidi docx-rtl docx-text-direction docx-text-direction-tbrl" data-docx-paragraph-bidi="true" dir="rtl" data-docx-text-direction="tbRl">ملف المصدر review note.</span></p>', $blocks);
        $t->contains('<p><span class="docx-text-direction docx-text-direction-lrtb" data-docx-text-direction="lrTb">Vertical layout source note.</span></p>', $blocks);
        $t->contains('<p>Disabled bidi stays plain.</p>', $blocks);
        $t->contains('<h2 id="rtl-review-heading"><span class="docx-paragraph-bidi docx-rtl" data-docx-paragraph-bidi="true" dir="rtl">RTL Review Heading</span></h2>', $blocks);
        $t->true(!str_contains($markdown, 'Disabled bidi stays plain.]{'), 'Disabled DOCX paragraph bidi should not create Markdown attributes');
        $t->true(!str_contains($blocks, 'Disabled bidi stays plain.</span>'), 'Disabled DOCX paragraph bidi should not create WordPress span metadata');
    },
    'preserves DOCX paragraph layout metadata as reviewer spans' => static function (TestRunner $t) use ($buildParagraphLayoutPackage): void {
        $document = (new DocxReader())->readDocument($buildParagraphLayoutPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, count($document->children));

        $centered = $document->children[0]->children[0];
        $t->same('span', $centered->type);
        $t->same([
            'docx-paragraph-align',
            'docx-align-center',
            'docx-paragraph-spacing',
            'docx-paragraph-indent',
            'docx-keep-next',
            'docx-page-break-before',
        ], $centered->attr('classes'));
        $centeredAttrs = $centered->attr('attributes');
        $t->same('center', $centeredAttrs['data-docx-paragraph-align']);
        $t->same('240', $centeredAttrs['data-docx-spacing-before-twips']);
        $t->same('120', $centeredAttrs['data-docx-spacing-after-twips']);
        $t->same('360', $centeredAttrs['data-docx-spacing-line']);
        $t->same('auto', $centeredAttrs['data-docx-spacing-line-rule']);
        $t->same('720', $centeredAttrs['data-docx-indent-left-twips']);
        $t->same('360', $centeredAttrs['data-docx-indent-right-twips']);
        $t->same('240', $centeredAttrs['data-docx-indent-first-line-twips']);
        $t->same('120', $centeredAttrs['data-docx-indent-hanging-twips']);
        $t->same('true', $centeredAttrs['data-docx-keep-next']);
        $t->same('true', $centeredAttrs['data-docx-page-break-before']);
        $t->same('Centered review paragraph.', $centered->children[0]->attr('text'));

        $justified = $document->children[1]->children[0];
        $t->same('span', $justified->type);
        $t->same(['docx-paragraph-align', 'docx-align-both', 'docx-paragraph-spacing', 'docx-paragraph-indent'], $justified->attr('classes'));
        $justifiedAttrs = $justified->attr('attributes');
        $t->same('both', $justifiedAttrs['data-docx-paragraph-align']);
        $t->same('100', $justifiedAttrs['data-docx-spacing-before-lines']);
        $t->same('50', $justifiedAttrs['data-docx-spacing-after-lines']);
        $t->same('480', $justifiedAttrs['data-docx-indent-start-twips']);
        $t->same('240', $justifiedAttrs['data-docx-indent-end-twips']);

        $endAligned = $document->children[2]->children[0];
        $t->same('span', $endAligned->type);
        $t->same(['docx-paragraph-align', 'docx-align-end'], $endAligned->attr('classes'));
        $t->same('end', $endAligned->attr('attributes')['data-docx-paragraph-align']);
        $t->true(!isset($endAligned->attr('attributes')['data-docx-keep-next']), 'Disabled keepNext should not create metadata');
        $t->true(!isset($endAligned->attr('attributes')['data-docx-page-break-before']), 'Disabled pageBreakBefore should not create metadata');

        $heading = $document->children[3];
        $t->same('heading', $heading->type);
        $t->same(2, $heading->attr('level'));
        $t->same('aligned-review-heading', $heading->attr('id'));
        $headingSpan = $heading->children[0];
        $t->same('span', $headingSpan->type);
        $t->same(['docx-paragraph-align', 'docx-align-right'], $headingSpan->attr('classes'));
        $t->same('right', $headingSpan->attr('attributes')['data-docx-paragraph-align']);
        $t->same('Aligned review heading', $headingSpan->children[0]->attr('text'));

        $t->contains('[Centered review paragraph.]{.docx-paragraph-align .docx-align-center .docx-paragraph-spacing .docx-paragraph-indent .docx-keep-next .docx-page-break-before data-docx-paragraph-align="center" data-docx-spacing-before-twips="240" data-docx-spacing-after-twips="120" data-docx-spacing-line="360" data-docx-spacing-line-rule="auto" data-docx-indent-left-twips="720" data-docx-indent-right-twips="360" data-docx-indent-first-line-twips="240" data-docx-indent-hanging-twips="120" data-docx-keep-next="true" data-docx-page-break-before="true"}', $markdown);
        $t->contains('[Justified source packet paragraph.]{.docx-paragraph-align .docx-align-both .docx-paragraph-spacing .docx-paragraph-indent data-docx-paragraph-align="both" data-docx-spacing-before-lines="100" data-docx-spacing-after-lines="50" data-docx-indent-start-twips="480" data-docx-indent-end-twips="240"}', $markdown);
        $t->contains('[Trailing aligned paragraph.]{.docx-paragraph-align .docx-align-end data-docx-paragraph-align="end"}', $markdown);
        $t->contains('## [Aligned review heading]{.docx-paragraph-align .docx-align-right data-docx-paragraph-align="right"}', $markdown);

        $t->contains('<p><span class="docx-paragraph-align docx-align-center docx-paragraph-spacing docx-paragraph-indent docx-keep-next docx-page-break-before" data-docx-paragraph-align="center" data-docx-spacing-before-twips="240" data-docx-spacing-after-twips="120" data-docx-spacing-line="360" data-docx-spacing-line-rule="auto" data-docx-indent-left-twips="720" data-docx-indent-right-twips="360" data-docx-indent-first-line-twips="240" data-docx-indent-hanging-twips="120" data-docx-keep-next="true" data-docx-page-break-before="true">Centered review paragraph.</span></p>', $blocks);
        $t->contains('<p><span class="docx-paragraph-align docx-align-both docx-paragraph-spacing docx-paragraph-indent" data-docx-paragraph-align="both" data-docx-spacing-before-lines="100" data-docx-spacing-after-lines="50" data-docx-indent-start-twips="480" data-docx-indent-end-twips="240">Justified source packet paragraph.</span></p>', $blocks);
        $t->contains('<p><span class="docx-paragraph-align docx-align-end" data-docx-paragraph-align="end">Trailing aligned paragraph.</span></p>', $blocks);
        $t->contains('<h2 id="aligned-review-heading"><span class="docx-paragraph-align docx-align-right" data-docx-paragraph-align="right">Aligned review heading</span></h2>', $blocks);
    },
    'reports DOCX section page geometry margins columns and header footer relationships' => static function (TestRunner $t) use ($buildSectionPropertiesPackage): void {
        $reader = new DocxReader();
        $result = $reader->readPackage($buildSectionPropertiesPackage());
        $document = $result['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($document->children));
        $t->same('Portrait packet section', $document->children[0]->children[0]->attr('text'));
        $t->same('Landscape continuation section.', $document->children[1]->children[0]->attr('text'));
        $t->contains('Portrait packet section', $markdown);
        $t->contains('Landscape continuation section.', $blocks);

        $sections = $document->attr('sectionProperties');
        $t->same(2, count($sections));

        $portrait = $sections[0];
        $t->same('paragraph', $portrait['source']);
        $t->same(0, $portrait['index']);
        $t->same(12240, $portrait['pageSize']['widthTwips']);
        $t->same(15840, $portrait['pageSize']['heightTwips']);
        $t->same('portrait', $portrait['pageSize']['orientation']);
        $t->same(1440, $portrait['margins']['topTwips']);
        $t->same(1080, $portrait['margins']['rightTwips']);
        $t->same(1440, $portrait['margins']['bottomTwips']);
        $t->same(1080, $portrait['margins']['leftTwips']);
        $t->same(720, $portrait['margins']['headerTwips']);
        $t->same(720, $portrait['margins']['footerTwips']);
        $t->same(0, $portrait['margins']['gutterTwips']);
        $t->same(1, $portrait['columns']['count']);
        $t->same(true, $portrait['columns']['equalWidth']);
        $t->same(720, $portrait['columns']['spaceTwips']);
        $t->same('rIdHeaderDefault', $portrait['headers'][0]['id']);
        $t->same('default', $portrait['headers'][0]['type']);
        $t->same('/word/header1.xml', $portrait['headers'][0]['target']);
        $t->same(false, $portrait['headers'][0]['external']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/header', $portrait['headers'][0]['relationshipType']);
        $t->same(true, $portrait['headers'][0]['exists']);
        $t->same('rIdFooterDefault', $portrait['footers'][0]['id']);
        $t->same('/word/footer1.xml', $portrait['footers'][0]['target']);
        $t->same(true, $portrait['footers'][0]['exists']);

        $landscape = $sections[1];
        $t->same('body', $landscape['source']);
        $t->same(1, $landscape['index']);
        $t->same(16838, $landscape['pageSize']['widthTwips']);
        $t->same(11906, $landscape['pageSize']['heightTwips']);
        $t->same('landscape', $landscape['pageSize']['orientation']);
        $t->same(720, $landscape['margins']['topTwips']);
        $t->same(720, $landscape['margins']['rightTwips']);
        $t->same(2, $landscape['columns']['count']);
        $t->same(false, $landscape['columns']['equalWidth']);
        $t->same(360, $landscape['columns']['spaceTwips']);
        $t->same('even', $landscape['headers'][0]['type']);
        $t->same('/word/header-even.xml', $landscape['headers'][0]['target']);
        $t->same(true, $landscape['headers'][0]['exists']);

        $reportSections = $result['importReport']['sections'];
        $t->same(2, $reportSections['count']);
        $t->same($sections, $reportSections['items']);
    },
    'imports DOCX header and footer body parts from section references' => static function (TestRunner $t) use ($buildSectionPropertiesPackage): void {
        $result = (new DocxReader())->readPackage($buildSectionPropertiesPackage());
        $sections = $result['importReport']['sections']['items'];
        $portrait = $sections[0];
        $landscape = $sections[1];

        $defaultHeader = $portrait['headers'][0];
        $t->same(true, $defaultHeader['exists']);
        $t->same('Default header source link', $defaultHeader['text']);
        $t->same(1, count($defaultHeader['blocks']));
        $t->same('paragraph', $defaultHeader['blocks'][0]->type);
        $t->same('Default header ', $defaultHeader['blocks'][0]->children[0]->attr('text'));
        $t->same('link', $defaultHeader['blocks'][0]->children[1]->type);
        $t->same('https://example.test/header-source', $defaultHeader['blocks'][0]->children[1]->attr('url'));
        $t->same('source link', $defaultHeader['blocks'][0]->children[1]->children[0]->attr('text'));

        $defaultFooter = $portrait['footers'][0];
        $t->same(true, $defaultFooter['exists']);
        $t->same('Default footer note', $defaultFooter['text']);
        $t->same(1, count($defaultFooter['blocks']));
        $t->same('paragraph', $defaultFooter['blocks'][0]->type);
        $t->same('Default footer note', $defaultFooter['blocks'][0]->children[0]->attr('text'));

        $evenHeader = $landscape['headers'][0];
        $t->same(true, $evenHeader['exists']);
        $t->same('Even section header', $evenHeader['text']);
        $t->same('Even section header', $evenHeader['blocks'][0]->children[0]->attr('text'));

        $headerBlocks = (new WordPressBlockWriter())->write(new AstNode('document', [], $defaultHeader['blocks']));
        $footerMarkdown = (new MarkdownWriter())->write(new AstNode('document', [], $defaultFooter['blocks']));
        $t->contains('<p>Default header <a href="https://example.test/header-source">source link</a></p>', $headerBlocks);
        $t->contains('Default footer note', $footerMarkdown);
    },
    'maps DOCX alternative-format HTML and plain-text chunks into AST blocks and reports skipped chunks' => static function (TestRunner $t) use ($buildAltChunkPackage): void {
        $reader = new DocxReader();
        $result = $reader->readPackage($buildAltChunkPackage());
        $document = $result['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(5, count($document->children));
        $t->same('Before alternative chunk.', $document->children[0]->children[0]->attr('text'));

        $chunk = $document->children[1];
        $t->same('raw_html', $chunk->type);
        $t->same('docx-altChunk', $chunk->attr('sourceFormat'));
        $t->same('rIdHtmlChunk', $chunk->attr('id'));
        $t->same('/word/chunks/review.html', $chunk->attr('targetPart'));
        $t->same('text/html', $chunk->attr('contentType'));
        $t->contains('<section data-review="docx-alt">', $chunk->attr('html'));
        $t->contains('<strong>chunk</strong>', $chunk->attr('html'));

        $plainText = $document->children[2];
        $t->same('paragraph', $plainText->type);
        $t->same('docx-altChunk', $plainText->attr('sourceFormat'));
        $t->same('rIdTextChunk', $plainText->attr('id'));
        $t->same('/word/chunks/review.txt', $plainText->attr('targetPart'));
        $t->same('text/plain; charset=utf-8', $plainText->attr('contentType'));
        $t->same('utf-8', $plainText->attr('encoding'));
        $t->same('utf-8', $plainText->attr('bom'));
        $t->same(0, $plainText->attr('repairs'));
        $t->same('Plain review note', $plainText->children[0]->attr('text'));
        $t->same('linebreak', $plainText->children[1]->type);
        $t->same('Second line', $plainText->children[2]->attr('text'));

        $plainTextSecondParagraph = $document->children[3];
        $t->same('paragraph', $plainTextSecondParagraph->type);
        $t->same('Final checkpoint.', $plainTextSecondParagraph->children[0]->attr('text'));
        $t->same('After alternative chunk.', $document->children[4]->children[0]->attr('text'));

        $t->contains('<section data-review="docx-alt"><h2>Embedded review HTML</h2><p>Imported <strong>chunk</strong> &amp; reviewer note.</p><ul><li>Media map</li></ul></section>', $markdown);
        $t->contains("Plain review note\\\nSecond line", $markdown);
        $t->contains('Final checkpoint.', $markdown);
        $t->contains('<!-- wp:html -->', $blocks);
        $t->contains('<section data-review="docx-alt"><h2>Embedded review HTML</h2><p>Imported <strong>chunk</strong> &amp; reviewer note.</p><ul><li>Media map</li></ul></section>', $blocks);
        $t->contains('<p>Plain review note<br/>Second line</p>', $blocks);
        $t->contains('<p>Final checkpoint.</p>', $blocks);
        $t->true(!str_contains($blocks, 'unsupported reviewer chunk'), 'Unsupported altChunk data should not render');

        $alternativeFormats = $result['importReport']['alternativeFormats'];
        $t->same(5, $alternativeFormats['count']);
        $t->same(2, $alternativeFormats['importedCount']);
        $t->same(1, $alternativeFormats['missingCount']);
        $t->same(1, $alternativeFormats['externalCount']);
        $t->same(1, $alternativeFormats['unsupportedCount']);

        $imported = $alternativeFormats['items'][0];
        $t->same('rIdHtmlChunk', $imported['id']);
        $t->same('/word/chunks/review.html', $imported['targetPart']);
        $t->same('text/html', $imported['contentType']);
        $t->same(true, $imported['imported']);
        $t->same('Embedded review HTML Imported chunk & reviewer note. Media map', $imported['text']);
        $t->same([], $imported['issues']);

        $text = $alternativeFormats['items'][1];
        $t->same('rIdTextChunk', $text['id']);
        $t->same('/word/chunks/review.txt', $text['targetPart']);
        $t->same('text/plain; charset=utf-8', $text['contentType']);
        $t->same(true, $text['imported']);
        $t->same("Plain review note\nSecond line\n\nFinal checkpoint.", $text['text']);
        $t->same('utf-8', $text['encoding']);
        $t->same('utf-8', $text['bom']);
        $t->same(0, $text['repairs']);
        $t->same(['normalized' => true, 'crlf' => 3, 'cr' => 0, 'conversions' => 3], $text['lineEndings']);
        $t->same(2, $text['paragraphCount']);
        $t->same([], $text['issues']);

        $missing = $alternativeFormats['items'][2];
        $t->same('rIdMissingChunk', $missing['id']);
        $t->same(false, $missing['exists']);
        $t->same(['missing-in-package'], $missing['issues']);

        $external = $alternativeFormats['items'][3];
        $t->same('rIdExternalChunk', $external['id']);
        $t->same(true, $external['external']);
        $t->same(['external-altchunk'], $external['issues']);

        $unsupported = $alternativeFormats['items'][4];
        $t->same('rIdUnsupportedChunk', $unsupported['id']);
        $t->same('application/rtf', $unsupported['contentType']);
        $t->same(['unsupported-content-type'], $unsupported['issues']);
    },
    'rejects malformed DOCX packages without shelling out to office tooling' => static function (TestRunner $t) use ($contentTypesXml, $documentXml): void {
        $reader = new DocxReader();

        $t->throws(\RuntimeException::class, static fn (): AstNode => $reader->readDocument(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
        ])));
        $t->throws(\RuntimeException::class, static fn (): AstNode => $reader->readDocument(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/missing.xml"/></Relationships>'],
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): AstNode => $reader->readDocument(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<document/>'],
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): AstNode => $reader->readDocument(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="../evil.xml"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => $documentXml],
        ])));
    },
];
