<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="html" ContentType="text/html"/>
  <Default Extension="txt" ContentType="text/plain; charset=utf-8"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/vnd.openxmlformats-officedocument.oleObject"/>
  <Default Extension="xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/glossary/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/word/endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/commentsExtended.xml" ContentType="application/vnd.ms-word.commentsExt+xml"/>
  <Override PartName="/word/charts/review-chart.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/diagrams/review-data.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramData+xml"/>
  <Override PartName="/word/diagrams/review-layout.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramLayout+xml"/>
  <Override PartName="/word/diagrams/review-style.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramStyle+xml"/>
  <Override PartName="/word/diagrams/review-colors.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramColors+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/custom.xml" ContentType="application/vnd.openxmlformats-officedocument.custom-properties+xml"/>
</Types>
XML;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdApp" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
  <Relationship Id="rIdCustom" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties" Target="docProps/custom.xml"/>
</Relationships>
XML],
    ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source-packet?post=42" TargetMode="External"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdExternalChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://cdn.example.test/docx-review-chart.png" TargetMode="External"/>
  <Relationship Id="rIdVmlBadge" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/vml-badge.png"/>
  <Relationship Id="rIdFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>
  <Relationship Id="rIdEndnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="endnotes.xml"/>
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdCommentsExtended" Type="http://schemas.microsoft.com/office/2011/relationships/commentsExtended" Target="commentsExtended.xml"/>
  <Relationship Id="rIdSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
  <Relationship Id="rIdGlossary" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/glossaryDocument" Target="glossary/document.xml"/>
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
  <Relationship Id="rIdNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
  <Relationship Id="rIdHeaderDefault" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>
  <Relationship Id="rIdFooterDefault" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
  <Relationship Id="rIdReviewChunk" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/review.html"/>
  <Relationship Id="rIdPlainTextChunk" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/plain-review.txt"/>
  <Relationship Id="rIdReviewChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/review-chart.xml"/>
  <Relationship Id="rIdReviewDiagramData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="diagrams/review-data.xml"/>
  <Relationship Id="rIdReviewDiagramLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="diagrams/review-layout.xml"/>
  <Relationship Id="rIdReviewDiagramStyle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramQuickStyle" Target="diagrams/review-style.xml"/>
  <Relationship Id="rIdReviewDiagramColors" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramColors" Target="diagrams/review-colors.xml"/>
  <Relationship Id="rIdReviewOleWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject" Target="embeddings/review-workbook.bin"/>
  <Relationship Id="rIdReviewEmbeddedPackage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-audit.xlsx"/>
  <Relationship Id="rIdSourceSubdocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/subDocument" Target="https://example.test/source-master/subdocument.docx" TargetMode="External"/>
</Relationships>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"
  xmlns:v="urn:schemas-microsoft-com:vml"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
  xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"
  xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"
  xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"
  xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>DOCX source packet</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ReviewSubhead"/></w:pPr><w:r><w:t>Reviewer checklist</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ReviewLayout"/></w:pPr><w:r><w:t>Styled source packet note remains labeled.</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ChecklistBullet"/></w:pPr><w:r><w:t>Match media IDs</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="11"/></w:numPr></w:pPr><w:r><w:t>Map hero attachment</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ChecklistBullet"/></w:pPr><w:r><w:t>Preserve alt text</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="12"/></w:numPr></w:pPr><w:r><w:t>Confirm source URL</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="12"/></w:numPr></w:pPr><w:r><w:t>Publish packet</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="13"/></w:numPr></w:pPr><w:r><w:t>Escalate legal review</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="13"/></w:numPr></w:pPr><w:r><w:t>Final archive signoff</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Jump to </w:t></w:r>
      <w:hyperlink w:anchor="source_packet_anchor"><w:r><w:t>source packet anchor</w:t></w:r></w:hyperlink>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Field-coded review link </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> HYPERLINK "https://example.test/field-link?post=42" \o "Field link title" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>field-coded source</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t xml:space="preserve"> remains clickable.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Cross-reference fields cite </w:t></w:r>
      <w:fldSimple w:instr=' REF "source_packet_anchor" \h \* MERGEFORMAT '><w:r><w:t>source packet anchor</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> on page </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> PAGEREF source_packet_anchor \h \p </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>12</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t xml:space="preserve"> and footnote </w:t></w:r>
      <w:fldSimple w:instr=' NOTEREF source_footnote \h \n '><w:r><w:t>c</w:t></w:r></w:fldSimple>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Layout checkpoints </w:t></w:r>
      <w:r><w:br w:type="page" w:clear="all"/></w:r>
      <w:r><w:t xml:space="preserve"> after page </w:t></w:r>
      <w:r><w:lastRenderedPageBreak/></w:r>
      <w:r><w:t xml:space="preserve"> after rendered page </w:t></w:r>
      <w:r><w:br w:type="column" w:clear="left"/></w:r>
      <w:r><w:t xml:space="preserve"> after column with leader </w:t></w:r>
      <w:r><w:ptab w:alignment="right" w:relativeTo="margin" w:leader="dot"/></w:r>
      <w:r><w:t>7.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t xml:space="preserve">Generated page </w:t>
        <w:pgNum/>
        <w:t xml:space="preserve"> on </w:t>
        <w:dayShort/>
        <w:t>/</w:t>
        <w:monthLong/>
        <w:t>/</w:t>
        <w:yearLong/>
        <w:t> stays auditable.</w:t>
      </w:r>
    </w:p>
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
      <w:r><w:t xml:space="preserve"> remains auditable.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Tagged reviewer </w:t></w:r>
      <w:smartTag w:uri="urn:schemas-microsoft-com:office:smarttags" w:element="PersonName">
        <w:smartTagPr>
          <w:attr w:name="normalized" w:uri="https://example.test/docx/smart-tags" w:val="Migration Desk"/>
          <w:attr w:name="review-id" w:val="packet-42"/>
        </w:smartTagPr>
        <w:r><w:rPr><w:b/></w:rPr><w:t>Migration Desk</w:t></w:r>
      </w:smartTag>
      <w:r><w:t xml:space="preserve"> remains traceable.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Custom XML category </w:t></w:r>
      <w:customXml w:uri="https://example.test/docx/custom" w:element="packet-category">
        <w:customXmlPr>
          <w:attr w:name="source-field" w:uri="https://example.test/docx/custom" w:val="category"/>
          <w:attr w:name="Review ID" w:val="packet-42"/>
        </w:customXmlPr>
        <w:r><w:rPr><w:i/></w:rPr><w:t>Policy update</w:t></w:r>
      </w:customXml>
      <w:r><w:t xml:space="preserve"> remains auditable.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Compatibility branch </w:t></w:r>
      <mc:AlternateContent>
        <mc:Choice Requires="w14"><w:r><w:t>unsupported reviewer text</w:t></w:r></mc:Choice>
        <mc:Fallback><w:r><w:t>fallback reviewer text</w:t></w:r></mc:Fallback>
      </mc:AlternateContent>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <mc:AlternateContent>
        <mc:Choice Requires="w"><w:r><w:rPr><w:b/></w:rPr><w:t>supported reviewer branch</w:t></w:r></mc:Choice>
        <mc:Fallback><w:r><w:t>unused reviewer fallback</w:t></w:r></mc:Fallback>
      </mc:AlternateContent>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:customXml w:uri="https://example.test/docx/custom" w:element="review-section">
      <w:customXmlPr>
        <w:attr w:name="section-id" w:val="source-review"/>
      </w:customXmlPr>
      <w:p><w:r><w:t>Custom XML review block for source packet.</w:t></w:r></w:p>
    </w:customXml>
    <w:p>
      <w:r><w:t xml:space="preserve">Decoded source symbols </w:t></w:r>
      <w:r><w:sym w:font="Symbol" w:char="F061"/></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:sym w:font="Wingdings" w:char="F09F"/></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:sym w:font="Wingdings 2" w:char="F050"/></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:sym w:font="Wingdings 3" w:char="F066"/></w:r>
      <w:r><w:t xml:space="preserve"> remain visible.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Ruby reviewer term </w:t></w:r>
      <w:r>
        <w:ruby>
          <w:rubyPr>
            <w:rubyAlign w:val="center"/>
            <w:hps w:val="14"/>
            <w:hpsRaise w:val="18"/>
            <w:hpsBaseText w:val="24"/>
            <w:lid w:val="ja-JP"/>
          </w:rubyPr>
          <w:rt><w:r><w:t>とうきょう</w:t></w:r></w:rt>
          <w:rubyBase><w:r><w:rPr><w:b/></w:rPr><w:t>東京</w:t></w:r></w:rubyBase>
        </w:ruby>
      </w:r>
      <w:r><w:t xml:space="preserve"> keeps pronunciation metadata.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Reviewer marks </w:t></w:r>
      <w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>priority update</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:shd w:val="clear" w:fill="D9EAF7"/></w:rPr><w:t>source shading</w:t></w:r>
      <w:r><w:t xml:space="preserve"> plus </w:t></w:r>
      <w:r><w:rPr><w:color w:val="C00000" w:themeColor="accent2" w:themeTint="33"/></w:rPr><w:t>redline label</w:t></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Run effect review </w:t></w:r>
      <w:r><w:rPr><w:vanish/><w:webHidden/></w:rPr><w:t>hidden source clue</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:em w:val="dot"/><w:effect w:val="blinkBackground"/></w:rPr><w:t>emphasis mark</w:t></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Proofing policy </w:t></w:r>
      <w:r><w:rPr><w:noProof/></w:rPr><w:t>OCR packet label</w:t></w:r>
      <w:r><w:t xml:space="preserve"> remains reviewer-only.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Typographic source label </w:t></w:r>
      <w:r><w:rPr><w:spacing w:val="30"/><w:w w:val="90"/><w:kern w:val="24"/></w:rPr><w:t>expanded reviewer copy</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:position w:val="-6"/><w:fitText w:val="1200" w:id="fit-review"/></w:rPr><w:t>fit text label</w:t></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr>
        <w:jc w:val="center"/>
        <w:pPrChange w:id="24" w:author="Layout Reviewer" w:date="2026-06-05T18:30:00Z">
          <w:pPr>
            <w:pStyle w:val="OldReviewLayout"/>
            <w:jc w:val="left"/>
          </w:pPr>
        </w:pPrChange>
      </w:pPr>
      <w:r><w:t>Tracked paragraph formatting remains auditable.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Tracked run formatting </w:t></w:r>
      <w:r>
        <w:rPr>
          <w:b/>
          <w:rPrChange w:id="25" w:author="Run Reviewer" w:date="2026-06-05T18:35:00Z">
            <w:rPr>
              <w:i/>
              <w:highlight w:val="yellow"/>
              <w:lang w:val="fr-FR"/>
            </w:rPr>
          </w:rPrChange>
        </w:rPr>
        <w:t>approved label</w:t>
      </w:r>
      <w:r><w:t xml:space="preserve"> stays visible.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Proof and permissions </w:t></w:r>
      <w:proofErr w:type="spellStart"/>
      <w:r><w:t>migraton</w:t></w:r>
      <w:proofErr w:type="spellEnd"/>
      <w:r><w:t xml:space="preserve"> plus </w:t></w:r>
      <w:permStart w:id="70" w:edGrp="everyone"/>
      <w:r><w:rPr><w:b/></w:rPr><w:t>review window</w:t></w:r>
      <w:permEnd w:id="70"/>
      <w:r><w:t xml:space="preserve"> stay labeled.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Cross paragraph proof </w:t></w:r>
      <w:proofErr w:type="gramStart"/>
      <w:r><w:t>starts before review</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t>continues after review</w:t></w:r>
      <w:proofErr w:type="gramEnd"/>
      <w:r><w:t xml:space="preserve"> for import.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Cross paragraph permission </w:t></w:r>
      <w:permStart w:id="71" w:user="reviewer@example.test"/>
      <w:r><w:rPr><w:b/></w:rPr><w:t>starts protected</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t>continues protected</w:t></w:r>
      <w:permEnd w:id="71"/>
      <w:r><w:t xml:space="preserve"> for handoff.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Character style reviewer label </w:t></w:r>
      <w:r><w:rPr><w:rStyle w:val="ReviewAlert"/></w:rPr><w:t>inherited urgency</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:rStyle w:val="ReviewMuted"/></w:rPr><w:t>muted follow-up</w:t></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Theme font reviewer label </w:t></w:r>
      <w:r><w:rPr><w:rStyle w:val="ReviewThemeMajor"/></w:rPr><w:t>major theme source</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:rStyle w:val="ReviewThemeMinor"/><w:rFonts w:ascii="Source Serif" w:hAnsi="Source Serif" w:eastAsiaTheme="minorEastAsia" w:cstheme="minorBidi"/></w:rPr><w:t>direct font override</w:t></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Multilingual source note </w:t></w:r>
      <w:r><w:rPr><w:lang w:val="es-ES"/></w:rPr><w:t>Resumen</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:rtl/><w:lang w:val="ar-SA" w:bidi="ar-SA"/></w:rPr><w:t>ملف المصدر</w:t></w:r>
      <w:r><w:t xml:space="preserve"> remain labeled.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Directional wrapper source </w:t></w:r>
      <w:dir w:val="rtl"><w:r><w:rPr><w:b/></w:rPr><w:t>ملف المصدر</w:t></w:r></w:dir>
      <w:r><w:t xml:space="preserve"> and override </w:t></w:r>
      <w:bdo w:val="ltr"><w:r><w:t>ABC-123</w:t></w:r></w:bdo>
      <w:r><w:t> remain traceable.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr><w:bidi/><w:textDirection w:val="tbRl"/></w:pPr>
      <w:r><w:t>ملف المصدر paragraph direction remains labeled.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr>
        <w:jc w:val="center"/>
        <w:spacing w:before="240" w:after="120" w:line="360" w:lineRule="auto"/>
        <w:ind w:left="720" w:firstLine="240"/>
        <w:tabs>
          <w:tab w:val="left" w:pos="720"/>
          <w:tab w:val="decimal" w:pos="1440" w:leader="dot"/>
        </w:tabs>
        <w:keepNext/>
        <w:pageBreakBefore/>
      </w:pPr>
      <w:r><w:t>Centered source packet layout remains labeled.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr>
        <w:keepLines/>
        <w:widowControl w:val="0"/>
        <w:contextualSpacing/>
        <w:mirrorIndents/>
        <w:suppressLineNumbers/>
        <w:suppressAutoHyphens/>
        <w:snapToGrid w:val="false"/>
      </w:pPr>
      <w:r><w:t>Paragraph policy source packet remains labeled.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr>
        <w:pBdr>
          <w:top w:val="single" w:sz="8" w:space="4" w:color="4F81BD"/>
          <w:bottom w:val="double" w:sz="12" w:space="6" w:themeColor="accent2" w:themeShade="66"/>
        </w:pBdr>
      </w:pPr>
      <w:r><w:t>Bordered source packet callout remains labeled.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Textbox lead </w:t></w:r>
      <w:r>
        <w:pict>
          <v:shape id="_x0000_s42" alt="Source review callout" style="width:216pt;height:48pt">
            <v:textbox inset="6pt,3pt,6pt,3pt">
              <w:txbxContent>
                <w:p><w:r><w:t>Source textbox note from VML shape.</w:t></w:r></w:p>
              </w:txbxContent>
            </v:textbox>
          </v:shape>
        </w:pict>
      </w:r>
      <w:r>
        <mc:AlternateContent>
          <mc:Fallback>
            <w:pict>
              <v:rect id="_x0000_s43" style="width:180pt;height:36pt">
                <v:textbox inset="3pt,3pt,3pt,3pt">
                  <w:txbxContent>
                    <w:p><w:r><w:t>Fallback textbox reminder.</w:t></w:r></w:p>
                  </w:txbxContent>
                </v:textbox>
              </v:rect>
            </w:pict>
          </mc:Fallback>
        </mc:AlternateContent>
      </w:r>
      <w:r>
        <w:drawing>
          <wp:inline distL="91440" distR="91440">
            <wp:extent cx="1200000" cy="480000"/>
            <wp:docPr id="81" name="Source DrawingML textbox" descr="Drawing textbox source note" title="Source callout"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
                <wps:wsp>
                  <wps:cNvSpPr txBox="1"/>
                  <wps:spPr>
                    <a:xfrm rot="900000">
                      <a:off x="6000" y="12000"/>
                      <a:ext cx="1200000" cy="480000"/>
                    </a:xfrm>
                    <a:prstGeom prst="rect"/>
                  </wps:spPr>
                  <wps:bodyPr anchor="ctr" wrap="square" lIns="91440" tIns="45720" rIns="91440" bIns="45720">
                    <a:noAutofit/>
                  </wps:bodyPr>
                  <wps:txbx>
                    <w:txbxContent>
                      <w:p><w:r><w:t>Source DrawingML textbox note.</w:t></w:r></w:p>
                    </w:txbxContent>
                  </wps:txbx>
                </wps:wsp>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r><w:t xml:space="preserve"> textbox tail.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Cross paragraph comment </w:t></w:r>
      <w:commentRangeStart w:id="10"/>
      <w:r><w:t>starts here</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t>continues here</w:t></w:r>
      <w:commentRangeEnd w:id="10"/>
      <w:r><w:t xml:space="preserve"> for import review</w:t></w:r>
      <w:r><w:commentReference w:id="10"/></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Cross move destination </w:t></w:r>
      <w:moveToRangeStart w:id="80" w:author="Migration Editor" w:date="2026-06-08T11:25:00Z" w:name="cross_move_destination"/>
      <w:r><w:t>starts accepted</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t>continues accepted</w:t></w:r>
      <w:moveToRangeEnd w:id="80"/>
      <w:r><w:t xml:space="preserve"> for publication.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Cross moved-from source </w:t></w:r>
      <w:moveFromRangeStart w:id="81" w:author="Source Editor" w:date="2026-06-08T11:20:00Z" w:name="cross_obsolete_source"/>
      <w:r><w:delText>old cross source begins</w:delText></w:r>
    </w:p>
    <w:p>
      <w:r><w:delText>old cross source continues</w:delText></w:r>
      <w:moveFromRangeEnd w:id="81"/>
      <w:r><w:t xml:space="preserve"> stays visible after source.</w:t></w:r>
    </w:p>
    <w:p>
      <w:bookmarkStart w:id="14" w:name="source_packet_anchor"/>
      <w:bookmarkStart w:id="15" w:name="_GoBack"/>
      <w:bookmarkEnd w:id="15"/>
      <w:r><w:t xml:space="preserve">Import reviewer keeps </w:t></w:r>
      <w:hyperlink r:id="rIdSource" w:tooltip="Source packet tooltip" w:tgtFrame="_blank" w:history="1" w:docLocation="ReviewSection"><w:r><w:t>the source link</w:t></w:r></w:hyperlink>
      <w:r><w:t xml:space="preserve"> visible.</w:t></w:r>
      <w:bookmarkEnd w:id="14"/>
      <w:del w:id="7" w:author="Source Editor" w:date="2026-06-04T17:45:00Z">
        <w:r><w:delText>Old reviewer draft.</w:delText></w:r>
      </w:del>
      <w:del w:id="28" w:author="Source Editor" w:date="2026-06-05T12:15:00Z">
        <w:r><w:delInstrText xml:space="preserve"> HYPERLINK "https://legacy.example.test/source" \o "Legacy source" </w:delInstrText></w:r>
      </w:del>
      <w:del w:id="29" w:author="Equation Reviewer" w:date="2026-06-05T12:30:00Z">
        <m:oMath>
          <m:r><m:t>x + y = z</m:t></m:r>
        </m:oMath>
      </w:del>
      <w:ins w:id="8" w:author="Migration Editor" w:date="2026-06-04T17:50:00Z">
        <w:r><w:t xml:space="preserve"> Approved tracked wording.</w:t></w:r>
      </w:ins>
      <w:moveFrom w:id="16" w:author="Source Editor" w:date="2026-06-04T18:05:00Z">
        <w:r><w:delText> moved from an obsolete review section.</w:delText></w:r>
      </w:moveFrom>
      <w:moveTo w:id="17" w:author="Migration Editor" w:date="2026-06-04T18:07:00Z">
        <w:r><w:t xml:space="preserve"> Moved into import checklist.</w:t></w:r>
      </w:moveTo>
      <w:moveFromRangeStart w:id="26" w:author="Source Editor" w:date="2026-06-05T08:10:00Z" w:name="obsolete_review_range"/>
      <w:r><w:delText> ranged moved from discarded section.</w:delText></w:r>
      <w:moveFromRangeEnd w:id="26"/>
      <w:moveToRangeStart w:id="27" w:author="Migration Editor" w:date="2026-06-05T08:12:00Z" w:name="accepted_review_range"/>
      <w:r><w:t xml:space="preserve"> Ranged move accepted for checklist.</w:t></w:r>
      <w:moveToRangeEnd w:id="27"/>
      <w:r><w:footnoteReference w:id="2" w:customMarkFollows="1"/></w:r>
      <w:r><w:t xml:space="preserve"> Also keep endnote context</w:t></w:r>
      <w:r><w:endnoteReference w:id="5" w:customMarkFollows="true"/></w:r>
      <w:r><w:t xml:space="preserve"> and flag missing note references</w:t></w:r>
      <w:r><w:footnoteReference w:id="404" w:customMarkFollows="1"/></w:r>
      <w:r><w:t xml:space="preserve">/</w:t></w:r>
      <w:r><w:endnoteReference w:id="405" w:customMarkFollows="true"/></w:r>
      <w:r><w:t xml:space="preserve"> while automatic note labels remain auditable</w:t></w:r>
      <w:r><w:footnoteReference w:id="3"/></w:r>
      <w:r><w:t xml:space="preserve">/</w:t></w:r>
      <w:r><w:endnoteReference w:id="6"/></w:r>
      <w:commentRangeStart w:id="9"/>
      <w:r><w:t xml:space="preserve"> and reviewer comment</w:t></w:r>
      <w:commentRangeEnd w:id="9"/>
      <w:r><w:commentReference w:id="9"/></w:r>
    </w:p>
    <w:del w:id="50" w:author="Source Editor" w:date="2026-06-05T09:00:00Z">
      <w:p><w:r><w:delText>Deleted block revision should stay report-only.</w:delText></w:r></w:p>
    </w:del>
    <w:ins w:id="51" w:author="Migration Editor" w:date="2026-06-05T09:05:00Z">
      <w:p><w:r><w:t>Accepted block revision paragraph.</w:t></w:r></w:p>
      <w:tbl>
        <w:tr>
          <w:tc><w:p><w:r><w:t>Accepted block table label</w:t></w:r></w:p></w:tc>
          <w:tc><w:p><w:r><w:t>Ready</w:t></w:r></w:p></w:tc>
        </w:tr>
      </w:tbl>
    </w:ins>
    <w:moveFrom w:id="52" w:author="Source Editor" w:date="2026-06-05T09:10:00Z">
      <w:p><w:r><w:delText>Moved-from block revision should stay report-only.</w:delText></w:r></w:p>
    </w:moveFrom>
    <w:moveTo w:id="53" w:author="Migration Editor" w:date="2026-06-05T09:12:00Z">
      <w:p><w:r><w:t>Accepted moved block revision paragraph.</w:t></w:r></w:p>
    </w:moveTo>
    <w:tbl>
      <w:tr>
        <w:tc>
          <w:p>
            <w:bookmarkStart w:id="21" w:name="review_column_range" w:colFirst="0" w:colLast="1"/>
            <w:r><w:t>Reviewed table scope</w:t></w:r>
            <w:bookmarkEnd w:id="21"/>
          </w:p>
        </w:tc>
        <w:tc><w:p><w:r><w:t>Needs column audit</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
    <w:altChunk r:id="rIdReviewChunk"/>
    <w:altChunk r:id="rIdPlainTextChunk"/>
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
          <m:den><m:rad><m:e><m:r><m:t>n</m:t></m:r></m:e></m:rad></m:den>
        </m:f>
        <m:r><m:t xml:space="preserve"> + </m:t></m:r>
        <m:nary>
          <m:naryPr><m:chr m:val="∑"/></m:naryPr>
          <m:sub><m:r><m:t>i=1</m:t></m:r></m:sub>
          <m:sup><m:r><m:t>n</m:t></m:r></m:sup>
          <m:e><m:r><m:t>a_i</m:t></m:r></m:e>
        </m:nary>
      </m:oMath>
      <w:r><w:t xml:space="preserve"> stays native.</w:t></w:r>
    </w:p>
    <w:sdt>
      <w:sdtPr>
        <w:id w:val="99"/>
        <w:alias w:val="Review Checklist"/>
        <w:tag w:val="review_checklist"/>
        <w:richText/>
        <w:docPartObj>
          <w:docPartGallery w:val="Quick Parts"/>
          <w:docPartCategory w:val="Migration Review"/>
          <w:docPartUnique/>
        </w:docPartObj>
        <w:placeholder><w:docPart w:val="ReviewChecklistPlaceholder"/></w:placeholder>
        <w:dataBinding w:xpath="/packet/review/checklist" w:storeItemID="{11111111-2222-3333-4444-555555555555}"/>
      </w:sdtPr>
      <w:sdtContent>
        <w:p><w:r><w:t>Content-control checklist for reviewer handoff.</w:t></w:r></w:p>
      </w:sdtContent>
    </w:sdt>
    <w:p>
      <w:r><w:t xml:space="preserve">Approval controls </w:t></w:r>
      <w:sdt>
        <w:sdtPr>
          <w:id w:val="142"/>
          <w:alias w:val="Approval Checkbox"/>
          <w:tag w:val="approval_checkbox"/>
          <w:checkBox>
            <w:checked w:val="0"/>
            <w:checkedState w:val="2612" w:font="MS Gothic"/>
            <w:uncheckedState w:val="2610" w:font="MS Gothic"/>
          </w:checkBox>
        </w:sdtPr>
        <w:sdtContent>
          <w:r><w:t>Needs review</w:t></w:r>
        </w:sdtContent>
      </w:sdt>
      <w:r><w:t xml:space="preserve"> target </w:t></w:r>
      <w:sdt>
        <w:sdtPr>
          <w:id w:val="143"/>
          <w:alias w:val="Publish Target"/>
          <w:tag w:val="publish_target"/>
          <w:dropDownList>
            <w:lastValue w:val="publish"/>
            <w:listItem w:displayText="Draft review" w:value="draft"/>
            <w:listItem w:displayText="Publish to site" w:value="publish"/>
          </w:dropDownList>
        </w:sdtPr>
        <w:sdtContent>
          <w:r><w:t>Publish to site</w:t></w:r>
        </w:sdtContent>
      </w:sdt>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p><w:r><w:drawing><wp:inline distL="114300" distR="114300"><wp:extent cx="914400" cy="457200"/><wp:effectExtent l="1000" t="2000" r="3000" b="4000"/><wp:docPr id="9" name="Hero" descr="Source hero alt" title="Source hero"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdHero"/><a:srcRect l="12500" t="2500" r="5000" b="7500"/></pic:blipFill><pic:spPr><a:xfrm rot="5400000" flipH="1"><a:off x="12000" y="34000"/><a:ext cx="800000" cy="400000"/></a:xfrm></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline><wp:anchor><wp:docPr id="10" name="Review chart" descr="Linked review chart alt" title="Linked review chart"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:link="rIdExternalChart"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:anchor></w:drawing></w:r></w:p>
    <w:p><w:r><w:pict><v:shape id="_x0000_i42" alt="VML badge alt"><v:imagedata r:id="rIdVmlBadge" o:title="VML badge title"/></v:shape></w:pict></w:r></w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Office drawing review </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="18" name="Review chart" descr="Imported review chart" title="Review chart"/>
            <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart r:id="rIdReviewChart"/></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r><w:t xml:space="preserve">, </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="20" name="Review callout" descr="Imported review callout" title="Review callout"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
                <wps:wsp>
                  <a:txBody>
                    <a:bodyPr/>
                    <a:lstStyle/>
                    <a:p>
                      <a:r><a:t>Source callout note</a:t></a:r>
                      <a:br/>
                      <a:r><a:t>Verify chart captions</a:t></a:r>
                    </a:p>
                  </a:txBody>
                </wps:wsp>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="19" name="Review workflow" descr="Imported workflow diagram" title="Review workflow"/>
            <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds r:dm="rIdReviewDiagramData" r:lo="rIdReviewDiagramLayout" r:qs="rIdReviewDiagramStyle" r:cs="rIdReviewDiagramColors"/></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r><w:t xml:space="preserve"> stay visible.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Embedded review objects </w:t></w:r>
      <w:r>
        <w:object>
          <v:shape id="_x0000_i88" alt="Review workbook"/>
          <o:OLEObject Type="Embed" ProgID="Excel.Sheet.12" ShapeID="_x0000_i88" DrawAspect="Content" ObjectID="_1650000088" r:id="rIdReviewOleWorkbook"/>
        </w:object>
      </w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r>
        <w:object>
          <v:shape id="_x0000_i89" alt="Source audit package"/>
          <o:OLEObject Type="Embed" ProgID="Package" ShapeID="_x0000_i89" DrawAspect="Icon" ObjectID="_1650000089" r:id="rIdReviewEmbeddedPackage"/>
        </w:object>
      </w:r>
      <w:r><w:t xml:space="preserve"> remain reviewable.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t xml:space="preserve">Master document reference </w:t>
        <w:subDoc r:id="rIdSourceSubdocument"/>
        <w:t> stays reviewable.</w:t>
      </w:r>
    </w:p>
    <w:tbl>
      <w:tblPr>
        <w:tblCaption w:val="DOCX review table"/>
        <w:tblDescription w:val="Reviewer summary table from the source DOCX package."/>
      </w:tblPr>
      <w:tr>
        <w:trPr><w:tblHeader/></w:trPr>
        <w:tc>
          <w:tcPr><w:tcW w:w="5000" w:type="pct"/><w:tcMar><w:top w:w="120" w:type="dxa"/><w:start w:w="240" w:type="dxa"/><w:bottom w:w="120" w:type="dxa"/><w:end w:w="240" w:type="dxa"/></w:tcMar><w:gridSpan w:val="2"/><w:vMerge w:val="restart"/><w:vAlign w:val="center"/><w:shd w:val="clear" w:fill="D9EAF7" w:color="auto"/></w:tcPr>
          <w:p><w:r><w:t>Review scope</w:t></w:r></w:p>
        </w:tc>
        <w:tc><w:p><w:r><w:t>Status</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc>
          <w:tcPr><w:gridSpan w:val="2"/><w:vMerge/></w:tcPr>
          <w:p><w:r><w:t>Continuation marker should not render</w:t></w:r></w:p>
        </w:tc>
        <w:tc><w:p><w:r><w:t>Ready</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:trPr><w:cantSplit/></w:trPr>
        <w:tc><w:p><w:r><w:t>Owner</w:t></w:r></w:p></w:tc>
        <w:tc>
          <w:tcPr><w:gridSpan w:val="2"/></w:tcPr>
          <w:p><w:r><w:t>Migration desk</w:t></w:r></w:p>
        </w:tc>
      </w:tr>
      <w:tr>
        <w:trPr><w:gridBefore w:val="1"/><w:wBefore w:type="dxa" w:w="720"/><w:gridAfter w:val="1"/><w:wAfter w:type="auto" w:w="0"/></w:trPr>
        <w:tc><w:p><w:r><w:t>Omitted grid reviewer note</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
    <w:sectPr>
      <w:headerReference w:type="default" r:id="rIdHeaderDefault"/>
      <w:footerReference w:type="default" r:id="rIdFooterDefault"/>
      <w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>
      <w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360"/>
      <w:cols w:num="2" w:space="360" w:equalWidth="0"/>
      <w:type w:val="continuous"/>
      <w:titlePg/>
      <w:pgNumType w:start="3" w:fmt="lowerRoman" w:chapStyle="2" w:chapSep="hyphen"/>
      <w:lnNumType w:start="5" w:countBy="2" w:restart="newPage" w:distance="360"/>
      <w:docGrid w:type="lines" w:linePitch="360" w:charSpace="80"/>
      <w:footnotePr>
        <w:pos w:val="beneathText"/>
        <w:numFmt w:val="lowerLetter"/>
        <w:numStart w:val="3"/>
        <w:numRestart w:val="eachSect"/>
      </w:footnotePr>
      <w:endnotePr>
        <w:pos w:val="docEnd"/>
        <w:numFmt w:val="upperRoman"/>
        <w:numStart w:val="8"/>
        <w:numRestart w:val="continuous"/>
      </w:endnotePr>
    </w:sectPr>
  </w:body>
</w:document>
XML],
    ['name' => 'word/_rels/settings.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewTemplate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate" Target="file:///C:/source-templates/review-packet.dotx" TargetMode="External"/>
</Relationships>
XML],
    ['name' => 'word/settings.xml', 'data' => <<<'XML'
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:trackRevisions/>
  <w:doNotTrackMoves/>
  <w:doNotTrackFormatting w:val="0"/>
  <w:evenAndOddHeaders/>
  <w:updateFields w:val="true"/>
  <w:documentProtection w:edit="readOnly" w:enforcement="1" w:cryptProviderType="rsaFull" w:cryptAlgorithmClass="hash" w:cryptAlgorithmType="typeAny" w:cryptAlgorithmSid="14" w:cryptSpinCount="100000"/>
  <w:proofState w:spelling="clean" w:grammar="dirty"/>
  <w:zoom w:percent="125"/>
  <w:defaultTabStop w:val="720"/>
  <w:decimalSymbol w:val=","/>
  <w:listSeparator w:val=";"/>
  <w:docVars>
    <w:docVar w:name="ReviewStatus" w:val="needs-media-review"/>
    <w:docVar w:name="ImportOwner" w:val="Migration Desk"/>
    <w:docVar w:name="ReviewStatus" w:val="approved-for-staging"/>
    <w:docVar w:name="DeferredOwner" w:val=""/>
    <w:docVar w:name="" w:val="ignored-empty-name"/>
  </w:docVars>
  <w:attachedTemplate r:id="rIdReviewTemplate"/>
  <w:compat>
    <w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/>
    <w:compatSetting w:name="overrideTableStyleFontSizeAndJustification" w:uri="http://schemas.microsoft.com/office/word" w:val="1"/>
  </w:compat>
</w:settings>
XML],
    ['name' => 'word/glossary/document.xml', 'data' => <<<'XML'
<w:glossaryDocument xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:docParts>
    <w:docPart>
      <w:docPartPr>
        <w:name w:val="ReviewChecklistPlaceholder"/>
        <w:style w:val="ReviewGlossary"/>
        <w:category>
          <w:name w:val="Migration Review"/>
          <w:gallery w:val="Quick Parts"/>
        </w:category>
        <w:types><w:type w:val="bbPlcHdr"/></w:types>
        <w:description w:val="Reusable review checklist placeholder"/>
        <w:guid w:val="{33333333-4444-5555-6666-777777777777}"/>
      </w:docPartPr>
      <w:docPartBody>
        <w:p><w:r><w:t>Review checklist placeholder for import staging.</w:t></w:r></w:p>
        <w:p>
          <w:hyperlink r:id="rIdGlossarySource" w:tooltip="Glossary source"><w:r><w:t>Glossary checklist source</w:t></w:r></w:hyperlink>
          <w:r><w:t xml:space="preserve"> </w:t></w:r>
          <w:r>
            <w:drawing>
              <wp:inline>
                <wp:docPr id="801" name="Glossary checklist logo" descr="Glossary checklist logo" title="Glossary checklist logo title"/>
                <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdGlossaryLogo"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
              </wp:inline>
            </w:drawing>
          </w:r>
        </w:p>
      </w:docPartBody>
    </w:docPart>
  </w:docParts>
</w:glossaryDocument>
XML],
    ['name' => 'word/glossary/_rels/document.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdGlossarySource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/glossary-checklist?post=42" TargetMode="External"/>
  <Relationship Id="rIdGlossaryLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/glossary-logo.png"/>
</Relationships>
XML],
    ['name' => 'word/glossary/media/glossary-logo.png', 'data' => 'GLOSSARYPNG'],
    ['name' => 'word/chunks/review.html', 'data' => '<aside data-review="docx-alt"><p>Alternative HTML chunk from source packet.</p></aside>'],
    ['name' => 'word/chunks/plain-review.txt', 'data' => "\xEF\xBB\xBFPlain text source note\r\nSecond imported line\r\n\r\nFinal plain-text checkpoint."],
    ['name' => 'word/styles.xml', 'data' => <<<'XML'
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/></w:style>
  <w:style w:type="paragraph" w:styleId="ReviewSubhead"><w:name w:val="Review Subhead"/><w:basedOn w:val="Heading2"/></w:style>
  <w:style w:type="paragraph" w:styleId="ReviewLayout">
    <w:name w:val="Review Layout"/>
    <w:pPr>
      <w:jc w:val="center"/>
      <w:spacing w:before="240" w:after="120"/>
      <w:ind w:left="720" w:firstLine="240"/>
      <w:keepNext/>
    </w:pPr>
    <w:rPr><w:i/><w:highlight w:val="cyan"/><w:lang w:val="en-US"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="ChecklistBullet"><w:name w:val="Checklist Bullet"/><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="11"/></w:numPr></w:pPr></w:style>
  <w:style w:type="character" w:styleId="ReviewEmphasis">
    <w:name w:val="Review Emphasis"/>
    <w:rPr><w:i/><w:highlight w:val="yellow"/><w:lang w:val="fr-FR"/></w:rPr>
  </w:style>
  <w:style w:type="character" w:styleId="ReviewAlert">
    <w:name w:val="Review Alert"/>
    <w:basedOn w:val="ReviewEmphasis"/>
    <w:rPr><w:b/><w:u/><w:shd w:fill="FFE699"/></w:rPr>
  </w:style>
  <w:style w:type="character" w:styleId="ReviewMuted">
    <w:name w:val="Review Muted"/>
    <w:basedOn w:val="ReviewAlert"/>
    <w:rPr><w:i w:val="0"/><w:highlight w:val="none"/><w:lang w:val="de-DE"/></w:rPr>
  </w:style>
  <w:style w:type="character" w:styleId="ReviewThemeMajor">
    <w:name w:val="Review Theme Major"/>
    <w:rPr><w:rFonts w:asciiTheme="majorHAnsi" w:hAnsiTheme="majorHAnsi" w:eastAsiaTheme="majorEastAsia" w:cstheme="majorBidi"/></w:rPr>
  </w:style>
  <w:style w:type="character" w:styleId="ReviewThemeMinor">
    <w:name w:val="Review Theme Minor"/>
    <w:basedOn w:val="ReviewThemeMajor"/>
    <w:rPr><w:rFonts w:asciiTheme="minorHAnsi" w:hAnsiTheme="minorHAnsi" w:eastAsiaTheme="minorEastAsia" w:cstheme="minorBidi"/></w:rPr>
  </w:style>
</w:styles>
XML],
    ['name' => 'word/theme/theme1.xml', 'data' => <<<'XML'
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="WordPress Review Theme">
  <a:themeElements>
    <a:fontScheme name="WordPress Review Fonts">
      <a:majorFont>
        <a:latin typeface="Aptos Display"/>
        <a:ea typeface="Yu Gothic"/>
        <a:cs typeface="Arial"/>
      </a:majorFont>
      <a:minorFont>
        <a:latin typeface="Aptos"/>
        <a:ea typeface="Meiryo"/>
        <a:cs typeface="Times New Roman"/>
      </a:minorFont>
    </a:fontScheme>
  </a:themeElements>
</a:theme>
XML],
    ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="10">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="-"/></w:lvl>
    <w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/><w:lvlText w:val="*"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="11"><w:abstractNumId w:val="10"/></w:num>
  <w:abstractNum w:abstractNumId="20"><w:lvl w:ilvl="0"><w:start w:val="3"/><w:numFmt w:val="lowerLetter"/><w:lvlText w:val="%1)"/></w:lvl></w:abstractNum>
  <w:num w:numId="12"><w:abstractNumId w:val="20"/></w:num>
  <w:abstractNum w:abstractNumId="30"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl></w:abstractNum>
  <w:num w:numId="13"><w:abstractNumId w:val="30"/><w:lvlOverride w:ilvl="0"><w:lvl w:ilvl="0"><w:start w:val="5"/><w:numFmt w:val="upperRoman"/><w:lvlText w:val="%1)"/></w:lvl></w:lvlOverride></w:num>
</w:numbering>
XML],
    ['name' => 'word/footnotes.xml', 'data' => <<<'XML'
<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:footnote w:id="-1" w:type="separator"><w:p><w:r><w:separator/></w:r></w:p></w:footnote>
  <w:footnote w:id="-2" w:type="continuationSeparator"><w:p><w:r><w:continuationSeparator/></w:r></w:p></w:footnote>
  <w:footnote w:id="-3" w:type="continuationNotice"><w:p><w:r><w:t>DOCX footnote continuation notice.</w:t></w:r></w:p></w:footnote>
  <w:footnote w:id="2"><w:p><w:r><w:footnoteRef/><w:t xml:space="preserve"> DOCX footnote import note.</w:t><w:cr/><w:t>Second footnote marker line.</w:t></w:r></w:p></w:footnote>
  <w:footnote w:id="3"><w:p><w:r><w:t>DOCX automatic footnote label note.</w:t></w:r></w:p></w:footnote>
</w:footnotes>
XML],
    ['name' => 'word/endnotes.xml', 'data' => <<<'XML'
<w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:endnote w:id="-1" w:type="separator"><w:p><w:r><w:separator/></w:r></w:p></w:endnote>
  <w:endnote w:id="-2" w:type="continuationSeparator"><w:p><w:r><w:continuationSeparator/></w:r></w:p></w:endnote>
  <w:endnote w:id="5"><w:p><w:r><w:endnoteRef/><w:t xml:space="preserve"> DOCX endnote import note.</w:t></w:r></w:p></w:endnote>
  <w:endnote w:id="6"><w:p><w:r><w:t>DOCX automatic endnote label note.</w:t></w:r></w:p></w:endnote>
</w:endnotes>
XML],
    ['name' => 'word/comments.xml', 'data' => <<<'XML'
<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml">
  <w:comment w:id="9" w:author="Migration Reviewer" w:initials="MR" w:date="2026-06-04T09:55:00Z">
    <w:p w14:paraId="00DOCX09"><w:r><w:annotationRef/><w:t xml:space="preserve"> DOCX reviewer comment import note.</w:t></w:r></w:p>
  </w:comment>
  <w:comment w:id="10" w:author="Migration Reviewer" w:initials="MR" w:date="2026-06-05T03:20:00Z">
    <w:p w14:paraId="00DOCX10"><w:r><w:t>DOCX multi-paragraph reviewer comment import note.</w:t></w:r></w:p>
  </w:comment>
</w:comments>
XML],
    ['name' => 'word/commentsExtended.xml', 'data' => <<<'XML'
<w15:commentsEx xmlns:w15="http://schemas.microsoft.com/office/word/2012/wordml">
  <w15:commentEx w15:paraId="00DOCX09" w15:done="1"/>
  <w15:commentEx w15:paraId="00DOCX10" w15:paraIdParent="00DOCX09" w15:done="0"/>
</w15:commentsEx>
XML],
    ['name' => 'word/header1.xml', 'data' => <<<'XML'
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:p>
    <w:r><w:t xml:space="preserve">Source packet header </w:t></w:r>
    <w:hyperlink r:id="rIdHeaderSource"><w:r><w:t>review link</w:t></w:r></w:hyperlink>
  </w:p>
</w:hdr>
XML],
    ['name' => 'word/_rels/header1.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHeaderSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/header-review?post=42" TargetMode="External"/>
</Relationships>
XML],
    ['name' => 'word/footer1.xml', 'data' => <<<'XML'
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p>
    <w:r><w:t xml:space="preserve">Source packet footer page </w:t></w:r>
    <w:fldSimple w:instr=' PAGE \* Arabic '><w:r><w:t>7</w:t></w:r></w:fldSimple>
    <w:r><w:t xml:space="preserve"> of </w:t></w:r>
    <w:fldSimple w:instr=' NUMPAGES \* Arabic '><w:r><w:t>12</w:t></w:r></w:fldSimple>
  </w:p>
</w:ftr>
XML],
    ['name' => 'word/charts/review-chart.xml', 'data' => '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"/>'],
    ['name' => 'word/diagrams/review-data.xml', 'data' => '<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>'],
    ['name' => 'word/diagrams/review-layout.xml', 'data' => '<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>'],
    ['name' => 'word/diagrams/review-style.xml', 'data' => '<dgm:styleDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>'],
    ['name' => 'word/diagrams/review-colors.xml', 'data' => '<dgm:colorsDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>'],
    ['name' => 'word/embeddings/review-workbook.bin', 'data' => 'OLEWORKBOOK'],
    ['name' => 'word/embeddings/source-audit.xlsx', 'data' => 'XLSXPACKAGE'],
    ['name' => 'word/media/hero.png', 'data' => 'PNGDATA'],
    ['name' => 'word/media/vml-badge.png', 'data' => 'VMLPNGDATA'],
    ['name' => 'docProps/core.xml', 'data' => <<<'XML'
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>WordPress DOCX handoff</dc:title>
  <dc:creator>Migration Desk</dc:creator>
</cp:coreProperties>
XML],
    ['name' => 'docProps/app.xml', 'data' => <<<'XML'
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"
  xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Company>WordPress Migration Desk</Company>
  <Pages>12</Pages>
  <Words>3456</Words>
  <Application>Microsoft Word</Application>
  <AppVersion>16.0000</AppVersion>
  <HyperlinkBase>https://example.test/docx/</HyperlinkBase>
  <HeadingPairs>
    <vt:vector size="2" baseType="variant">
      <vt:variant><vt:lpstr>Title</vt:lpstr></vt:variant>
      <vt:variant><vt:i4>1</vt:i4></vt:variant>
    </vt:vector>
  </HeadingPairs>
</Properties>
XML],
    ['name' => 'docProps/custom.xml', 'data' => <<<'XML'
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties"
  xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="2" name="ImportStatus"><vt:lpwstr>needs-media-review</vt:lpwstr></property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="3" name="ReviewBatch"><vt:i4>42</vt:i4></property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="4" name="Approved"><vt:bool>false</vt:bool></property>
</Properties>
XML],
]);

$reader = new DocxReader();
$result = $reader->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

$summary = [
    'metadata' => $result['metadata'],
    'documentPart' => $result['documentPart'],
    'blockCount' => count($result['document']->children),
    'sectionProperties' => $result['document']->attr('sectionProperties', []),
    'importReport' => $result['importReport'],
    'wordpressBlocks' => $blocks,
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['metadata']['title'] ?? '') !== 'WordPress DOCX handoff') {
        throw new RuntimeException('DOCX body handoff self-test missing metadata title');
    }
    if (($summary['metadata']['docxExtendedProperties']['company'] ?? '') !== 'WordPress Migration Desk') {
        throw new RuntimeException('DOCX body handoff self-test missing extended package company metadata');
    }
    if (($summary['metadata']['docxExtendedProperties']['pages'] ?? 0) !== 12) {
        throw new RuntimeException('DOCX body handoff self-test missing extended package page count');
    }
    if (($summary['metadata']['docxCustomProperties']['byName']['ImportStatus'] ?? '') !== 'needs-media-review') {
        throw new RuntimeException('DOCX body handoff self-test missing custom package import status');
    }
    if (($summary['metadata']['customProperties']['ReviewBatch'] ?? 0) !== 42) {
        throw new RuntimeException('DOCX body handoff self-test missing flattened custom package review batch');
    }
    if (($summary['importReport']['properties']['custom']['count'] ?? 0) !== 3) {
        throw new RuntimeException('DOCX body handoff self-test missing custom package properties import report');
    }
    if (($summary['metadata']['docxTheme']['fonts']['schemeName'] ?? '') !== 'WordPress Review Fonts') {
        throw new RuntimeException('DOCX body handoff self-test missing theme font scheme metadata');
    }
    if (($summary['metadata']['docxTheme']['fonts']['majorLatin'] ?? '') !== 'Aptos Display') {
        throw new RuntimeException('DOCX body handoff self-test missing major theme font metadata');
    }
    if (($summary['importReport']['theme']['fonts']['minorEastAsia'] ?? '') !== 'Meiryo') {
        throw new RuntimeException('DOCX body handoff self-test missing theme font import report');
    }
    if (($summary['metadata']['docxSettings']['trackRevisions'] ?? null) !== true) {
        throw new RuntimeException('DOCX body handoff self-test missing settings tracked-revisions metadata');
    }
    if (($summary['metadata']['docxSettings']['documentProtection']['edit'] ?? '') !== 'readOnly') {
        throw new RuntimeException('DOCX body handoff self-test missing settings protection metadata');
    }
    if (($summary['metadata']['docxSettings']['documentVariables']['byName']['ReviewStatus'] ?? '') !== 'needs-media-review') {
        throw new RuntimeException('DOCX body handoff self-test missing settings document variable metadata');
    }
    if (($summary['metadata']['docxSettings']['documentVariables']['duplicateNames'][0] ?? '') !== 'ReviewStatus') {
        throw new RuntimeException('DOCX body handoff self-test missing duplicate settings document variable report');
    }
    if (($summary['metadata']['docxSettings']['documentVariables']['emptyValueCount'] ?? -1) !== 1) {
        throw new RuntimeException('DOCX body handoff self-test missing empty settings document variable report');
    }
    if (($summary['metadata']['docxSettings']['attachedTemplate']['issues'][0] ?? '') !== 'external-target-unsafe-scheme') {
        throw new RuntimeException('DOCX body handoff self-test missing unsafe attached-template setting');
    }
    if (($summary['importReport']['settings']['part'] ?? '') !== '/word/settings.xml') {
        throw new RuntimeException('DOCX body handoff self-test missing settings import report');
    }
    if (($summary['importReport']['settings']['attachedTemplate']['id'] ?? '') !== 'rIdReviewTemplate') {
        throw new RuntimeException('DOCX body handoff self-test missing attached-template relationship report');
    }
    if (($summary['importReport']['settings']['documentVariables']['items'][2]['duplicate'] ?? null) !== true) {
        throw new RuntimeException('DOCX body handoff self-test missing settings document variable import report');
    }
    if (($summary['metadata']['docxGlossary']['docPartCount'] ?? 0) !== 1) {
        throw new RuntimeException('DOCX body handoff self-test missing glossary document metadata');
    }
    if (($summary['metadata']['docxGlossary']['items'][0]['name'] ?? '') !== 'ReviewChecklistPlaceholder') {
        throw new RuntimeException('DOCX body handoff self-test missing glossary document part name');
    }
    if (($summary['metadata']['docxGlossary']['items'][0]['text'] ?? '') !== "Review checklist placeholder for import staging.\nGlossary checklist source Glossary checklist logo") {
        throw new RuntimeException('DOCX body handoff self-test missing glossary document part text');
    }
    if (($summary['importReport']['glossary']['relationship']['id'] ?? '') !== 'rIdGlossary') {
        throw new RuntimeException('DOCX body handoff self-test missing glossary relationship import report');
    }
    if (($summary['metadata']['docxGlossary']['relationshipsPart'] ?? '') !== '/word/glossary/_rels/document.xml.rels') {
        throw new RuntimeException('DOCX body handoff self-test missing glossary-local relationships part');
    }
    if (($summary['metadata']['docxGlossary']['relationshipCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing glossary-local relationship count');
    }
    if (($summary['metadata']['docxGlossary']['relationships'][0]['target'] ?? '') !== 'https://example.test/glossary-checklist?post=42') {
        throw new RuntimeException('DOCX body handoff self-test missing glossary-local hyperlink relationship');
    }
    if (($summary['metadata']['docxGlossary']['relationships'][1]['target'] ?? '') !== '/word/glossary/media/glossary-logo.png') {
        throw new RuntimeException('DOCX body handoff self-test missing glossary-local image relationship');
    }
    $glossaryBlock = $summary['metadata']['docxGlossary']['items'][0]['blocks'][1] ?? null;
    if (!$glossaryBlock instanceof PortLibs\Pandoc\AstNode || ($glossaryBlock->children[0]->attr('url') ?? '') !== 'https://example.test/glossary-checklist?post=42') {
        throw new RuntimeException('DOCX body handoff self-test missing parsed glossary hyperlink block');
    }
    if (($glossaryBlock->children[2]->attr('sourcePart') ?? '') !== '/word/glossary/media/glossary-logo.png') {
        throw new RuntimeException('DOCX body handoff self-test missing parsed glossary image block');
    }
    if (($glossaryBlock->children[2]->attr('bytes') ?? 0) !== 11) {
        throw new RuntimeException('DOCX body handoff self-test missing parsed glossary image byte count');
    }
    if (($summary['importReport']['media']['embeddedCount'] ?? 0) !== 3) {
        throw new RuntimeException('DOCX body handoff self-test missing media import report');
    }
    if (($summary['importReport']['media']['items'][0]['bytes'] ?? 0) !== 7) {
        throw new RuntimeException('DOCX body handoff self-test missing media byte count');
    }
    if (($summary['importReport']['media']['items'][1]['external'] ?? null) !== true || ($summary['importReport']['media']['items'][1]['usedCount'] ?? 0) !== 1) {
        throw new RuntimeException('DOCX body handoff self-test missing linked external media handoff');
    }
    if (($summary['importReport']['media']['items'][2]['id'] ?? '') !== 'rIdVmlBadge' || ($summary['importReport']['media']['items'][2]['usedCount'] ?? 0) !== 1) {
        throw new RuntimeException('DOCX body handoff self-test missing VML image media handoff');
    }
    if (($summary['importReport']['media']['items'][3]['source'] ?? '') !== '/word/glossary/document.xml' || ($summary['importReport']['media']['items'][3]['bytes'] ?? 0) !== 11) {
        throw new RuntimeException('DOCX body handoff self-test missing glossary image media handoff');
    }
    if (!str_contains($summary['wordpressBlocks'], 'class="docx-drawing-geometry docx-drawing-inline docx-picture-crop docx-picture-transform docx-picture-flip-horizontal"')) {
        throw new RuntimeException('DOCX body handoff self-test missing DrawingML picture crop/transform classes');
    }
    if (!str_contains($summary['wordpressBlocks'], 'data-docx-picture-crop-left="12500"') || !str_contains($summary['wordpressBlocks'], 'data-docx-picture-rotation="5400000"')) {
        throw new RuntimeException('DOCX body handoff self-test missing DrawingML picture crop/rotation attributes');
    }
    if (!str_contains($summary['wordpressBlocks'], 'data-docx-picture-offset-x-emu="12000"') || !str_contains($summary['wordpressBlocks'], 'data-docx-picture-width-emu="800000"')) {
        throw new RuntimeException('DOCX body handoff self-test missing DrawingML picture transform extents');
    }
    if (($summary['importReport']['revisions']['insertionCount'] ?? 0) !== 6 || ($summary['importReport']['revisions']['deletionCount'] ?? 0) !== 8) {
        throw new RuntimeException('DOCX body handoff self-test missing tracked-change report');
    }
    if (($summary['importReport']['revisions']['formattingCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing tracked formatting-change report');
    }
    $revisionItemsById = [];
    foreach (($summary['importReport']['revisions']['items'] ?? []) as $item) {
        if (is_array($item) && isset($item['id'])) {
            $revisionItemsById[(string) $item['id']] = $item;
        }
    }
    if (($revisionItemsById['28']['text'] ?? '') !== 'HYPERLINK "https://legacy.example.test/source" \\o "Legacy source"') {
        throw new RuntimeException('DOCX body handoff self-test missing deleted field instruction audit text');
    }
    if (($revisionItemsById['29']['text'] ?? '') !== 'x + y = z') {
        throw new RuntimeException('DOCX body handoff self-test missing deleted math revision audit text');
    }
    if (!str_contains((string) ($revisionItemsById['51']['text'] ?? ''), 'Accepted block revision paragraph.') || !str_contains((string) ($revisionItemsById['51']['text'] ?? ''), 'Accepted block table label')) {
        throw new RuntimeException('DOCX body handoff self-test missing accepted block revision audit text');
    }
    if (($revisionItemsById['53']['text'] ?? '') !== 'Accepted moved block revision paragraph.') {
        throw new RuntimeException('DOCX body handoff self-test missing moved block revision audit text');
    }
    if (($summary['importReport']['sections']['count'] ?? 0) !== 1) {
        throw new RuntimeException('DOCX body handoff self-test missing section property report');
    }
    if (($summary['importReport']['alternativeFormats']['importedCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing alternative-format import report');
    }
    if (($summary['importReport']['alternativeFormats']['items'][1]['paragraphCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing plain-text altChunk paragraphs');
    }
    if (($summary['importReport']['embeddedObjects']['count'] ?? 0) !== 2 || ($summary['importReport']['embeddedObjects']['embeddedCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing embedded object package report');
    }
    if (($summary['importReport']['embeddedObjects']['items'][0]['bytes'] ?? 0) !== 11) {
        throw new RuntimeException('DOCX body handoff self-test missing embedded OLE byte count');
    }
    if (($summary['importReport']['subdocuments']['count'] ?? 0) !== 1 || ($summary['importReport']['subdocuments']['externalCount'] ?? 0) !== 1) {
        throw new RuntimeException('DOCX body handoff self-test missing subdocument relationship report');
    }
    if (($summary['importReport']['subdocuments']['items'][0]['target'] ?? '') !== 'https://example.test/source-master/subdocument.docx') {
        throw new RuntimeException('DOCX body handoff self-test missing subdocument target');
    }
    if (($summary['importReport']['notes']['count'] ?? 0) !== 8) {
        throw new RuntimeException('DOCX body handoff self-test missing note-reference import report');
    }
    if (($summary['importReport']['notes']['footnoteCount'] ?? 0) !== 3 || ($summary['importReport']['notes']['endnoteCount'] ?? 0) !== 3 || ($summary['importReport']['notes']['commentCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing typed note-reference counts');
    }
    if (($summary['importReport']['notes']['missingCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing unresolved note-reference count');
    }
    $specialNotes = $summary['importReport']['notes']['specialNotes'] ?? [];
    if (($specialNotes['count'] ?? 0) !== 5 || ($specialNotes['footnoteCount'] ?? 0) !== 3 || ($specialNotes['endnoteCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing special footnote/endnote separator report');
    }
    if (($specialNotes['items'][0]['type'] ?? '') !== 'separator' || ($specialNotes['items'][0]['markers'] ?? []) !== ['separator']) {
        throw new RuntimeException('DOCX body handoff self-test missing footnote separator marker report');
    }
    if (($specialNotes['items'][2]['type'] ?? '') !== 'continuationNotice' || ($specialNotes['items'][2]['text'] ?? '') !== 'DOCX footnote continuation notice.') {
        throw new RuntimeException('DOCX body handoff self-test missing continuation notice report');
    }
    if (($specialNotes['items'][4]['sourceType'] ?? '') !== 'endnote' || ($specialNotes['items'][4]['type'] ?? '') !== 'continuationSeparator') {
        throw new RuntimeException('DOCX body handoff self-test missing endnote continuation separator report');
    }
    $noteItemsByKey = [];
    foreach (($summary['importReport']['notes']['items'] ?? []) as $item) {
        if (is_array($item)) {
            $noteItemsByKey[(string) ($item['sourceType'] ?? '') . ':' . (string) ($item['id'] ?? '')] = $item;
        }
    }
    if (($noteItemsByKey['footnote:404']['missing'] ?? false) !== true) {
        throw new RuntimeException('DOCX body handoff self-test missing unresolved footnote placeholder report');
    }
    if (($noteItemsByKey['endnote:405']['missing'] ?? false) !== true) {
        throw new RuntimeException('DOCX body handoff self-test missing unresolved endnote placeholder report');
    }
    foreach (['footnote:2', 'endnote:5', 'footnote:404', 'endnote:405'] as $key) {
        if (($noteItemsByKey[$key]['customMarkFollows'] ?? false) !== true) {
            throw new RuntimeException('DOCX body handoff self-test missing custom note marker report for ' . $key);
        }
    }
    if (($noteItemsByKey['footnote:3']['referenceLabel'] ?? '') !== 'c' || ($noteItemsByKey['footnote:3']['referenceNumber'] ?? 0) !== 3) {
        throw new RuntimeException('DOCX body handoff self-test missing automatic footnote label report');
    }
    if (($noteItemsByKey['endnote:6']['referenceLabel'] ?? '') !== 'VIII' || ($noteItemsByKey['endnote:6']['referenceNumber'] ?? 0) !== 8) {
        throw new RuntimeException('DOCX body handoff self-test missing automatic endnote label report');
    }
    if (($noteItemsByKey['comment:9']['commentParaId'] ?? '') !== '00DOCX09' || ($noteItemsByKey['comment:9']['commentResolved'] ?? null) !== true) {
        throw new RuntimeException('DOCX body handoff self-test missing resolved commentsExtended metadata');
    }
    if (($noteItemsByKey['comment:10']['commentParaId'] ?? '') !== '00DOCX10' || ($noteItemsByKey['comment:10']['commentParentParaId'] ?? '') !== '00DOCX09' || ($noteItemsByKey['comment:10']['commentResolved'] ?? null) !== false) {
        throw new RuntimeException('DOCX body handoff self-test missing threaded commentsExtended metadata');
    }
    if (($summary['sectionProperties'][0]['pageSize']['orientation'] ?? '') !== 'landscape') {
        throw new RuntimeException('DOCX body handoff self-test missing section page orientation');
    }
    if (($summary['sectionProperties'][0]['columns']['count'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing section column count');
    }
    if (($summary['sectionProperties'][0]['sectionType'] ?? '') !== 'continuous') {
        throw new RuntimeException('DOCX body handoff self-test missing section type metadata');
    }
    if (($summary['sectionProperties'][0]['titlePage'] ?? null) !== true) {
        throw new RuntimeException('DOCX body handoff self-test missing title-page metadata');
    }
    if (($summary['sectionProperties'][0]['pageNumbering']['start'] ?? 0) !== 3 || ($summary['sectionProperties'][0]['pageNumbering']['format'] ?? '') !== 'lowerRoman') {
        throw new RuntimeException('DOCX body handoff self-test missing page-numbering metadata');
    }
    if (($summary['sectionProperties'][0]['pageNumbering']['chapterStyle'] ?? 0) !== 2 || ($summary['sectionProperties'][0]['pageNumbering']['chapterSeparator'] ?? '') !== 'hyphen') {
        throw new RuntimeException('DOCX body handoff self-test missing chapter page-numbering metadata');
    }
    if (($summary['sectionProperties'][0]['lineNumbering']['start'] ?? 0) !== 5 || ($summary['sectionProperties'][0]['lineNumbering']['countBy'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing line-numbering counters');
    }
    if (($summary['sectionProperties'][0]['lineNumbering']['restart'] ?? '') !== 'newPage' || ($summary['sectionProperties'][0]['lineNumbering']['distanceTwips'] ?? 0) !== 360) {
        throw new RuntimeException('DOCX body handoff self-test missing line-numbering policy');
    }
    if (($summary['sectionProperties'][0]['documentGrid']['type'] ?? '') !== 'lines' || ($summary['sectionProperties'][0]['documentGrid']['linePitchTwips'] ?? 0) !== 360) {
        throw new RuntimeException('DOCX body handoff self-test missing section document-grid line metadata');
    }
    if (($summary['sectionProperties'][0]['documentGrid']['charSpaceTwips'] ?? 0) !== 80) {
        throw new RuntimeException('DOCX body handoff self-test missing section document-grid character metadata');
    }
    if (($summary['sectionProperties'][0]['footnoteProperties']['numberFormat'] ?? '') !== 'lowerLetter') {
        throw new RuntimeException('DOCX body handoff self-test missing footnote numbering format');
    }
    if (($summary['sectionProperties'][0]['footnoteProperties']['numberStart'] ?? 0) !== 3) {
        throw new RuntimeException('DOCX body handoff self-test missing footnote numbering start');
    }
    if (($summary['sectionProperties'][0]['footnoteProperties']['numberRestart'] ?? '') !== 'eachSect') {
        throw new RuntimeException('DOCX body handoff self-test missing footnote restart policy');
    }
    if (($summary['sectionProperties'][0]['footnoteProperties']['position'] ?? '') !== 'beneathText') {
        throw new RuntimeException('DOCX body handoff self-test missing footnote position policy');
    }
    if (($summary['sectionProperties'][0]['endnoteProperties']['numberFormat'] ?? '') !== 'upperRoman') {
        throw new RuntimeException('DOCX body handoff self-test missing endnote numbering format');
    }
    if (($summary['sectionProperties'][0]['endnoteProperties']['numberStart'] ?? 0) !== 8) {
        throw new RuntimeException('DOCX body handoff self-test missing endnote numbering start');
    }
    if (($summary['sectionProperties'][0]['endnoteProperties']['numberRestart'] ?? '') !== 'continuous') {
        throw new RuntimeException('DOCX body handoff self-test missing endnote restart policy');
    }
    if (($summary['sectionProperties'][0]['endnoteProperties']['position'] ?? '') !== 'docEnd') {
        throw new RuntimeException('DOCX body handoff self-test missing endnote position policy');
    }
    if (($summary['sectionProperties'][0]['headers'][0]['target'] ?? '') !== '/word/header1.xml') {
        throw new RuntimeException('DOCX body handoff self-test missing section header target');
    }
    if (($summary['sectionProperties'][0]['headers'][0]['text'] ?? '') !== 'Source packet header review link') {
        throw new RuntimeException('DOCX body handoff self-test missing parsed section header text');
    }
    if (($summary['sectionProperties'][0]['headers'][0]['relationshipsPart'] ?? '') !== '/word/_rels/header1.xml.rels') {
        throw new RuntimeException('DOCX body handoff self-test missing section header relationships part');
    }
    if (($summary['sectionProperties'][0]['headers'][0]['relationshipCount'] ?? 0) !== 1) {
        throw new RuntimeException('DOCX body handoff self-test missing section header relationship count');
    }
    if (($summary['sectionProperties'][0]['headers'][0]['relationships'][0]['id'] ?? '') !== 'rIdHeaderSource') {
        throw new RuntimeException('DOCX body handoff self-test missing section header relationship id');
    }
    if (($summary['sectionProperties'][0]['headers'][0]['relationships'][0]['target'] ?? '') !== 'https://example.test/header-review?post=42') {
        throw new RuntimeException('DOCX body handoff self-test missing section header relationship target');
    }
    if (($summary['sectionProperties'][0]['headers'][0]['relationships'][0]['external'] ?? false) !== true) {
        throw new RuntimeException('DOCX body handoff self-test missing section header external relationship metadata');
    }
    $headerRelationship = $summary['sectionProperties'][0]['headers'][0]['relationships'][0] ?? [];
    if (!array_key_exists('contentType', $headerRelationship) || $headerRelationship['contentType'] !== null) {
        throw new RuntimeException('DOCX body handoff self-test should not assign content type to external section header relationship');
    }
    if (($summary['sectionProperties'][0]['headers'][0]['blocks'][0]->children[1]->attr('url') ?? '') !== 'https://example.test/header-review?post=42') {
        throw new RuntimeException('DOCX body handoff self-test missing section header hyperlink target');
    }
    if (($summary['sectionProperties'][0]['footers'][0]['text'] ?? '') !== 'Source packet footer page 7 of 12') {
        throw new RuntimeException('DOCX body handoff self-test missing parsed section footer text');
    }
    $footerPageField = $summary['sectionProperties'][0]['footers'][0]['blocks'][0]->children[1] ?? null;
    if (!$footerPageField instanceof PortLibs\Pandoc\AstNode || ($footerPageField->attr('attributes')['data-docx-field'] ?? '') !== 'page') {
        throw new RuntimeException('DOCX body handoff self-test missing footer page field metadata');
    }
    if (($footerPageField->attr('attributes')['data-docx-field-instruction'] ?? '') !== 'PAGE \* Arabic') {
        throw new RuntimeException('DOCX body handoff self-test missing footer page field instruction');
    }
    $footerPageCountField = $summary['sectionProperties'][0]['footers'][0]['blocks'][0]->children[3] ?? null;
    if (!$footerPageCountField instanceof PortLibs\Pandoc\AstNode || ($footerPageCountField->attr('attributes')['data-docx-field'] ?? '') !== 'numpages') {
        throw new RuntimeException('DOCX body handoff self-test missing footer page-count field metadata');
    }
    if (str_contains($blocks, 'Old reviewer draft.')) {
        throw new RuntimeException('DOCX body handoff self-test rendered deleted tracked-change text');
    }
    if (str_contains($blocks, 'legacy.example.test')) {
        throw new RuntimeException('DOCX body handoff self-test rendered deleted field instruction');
    }
    if (str_contains($blocks, 'x + y = z')) {
        throw new RuntimeException('DOCX body handoff self-test rendered deleted math revision text');
    }
    if (str_contains($blocks, 'moved from an obsolete review section')) {
        throw new RuntimeException('DOCX body handoff self-test rendered moved-from tracked-change text');
    }
    if (str_contains($blocks, 'ranged moved from discarded section')) {
        throw new RuntimeException('DOCX body handoff self-test rendered moved-from tracked-change range text');
    }
    if (str_contains($blocks, 'old cross source begins') || str_contains($blocks, 'old cross source continues')) {
        throw new RuntimeException('DOCX body handoff self-test rendered cross-paragraph moved-from range text');
    }
    if (str_contains($blocks, 'Deleted block revision should stay report-only.')) {
        throw new RuntimeException('DOCX body handoff self-test rendered deleted block tracked-change text');
    }
    if (str_contains($blocks, 'Moved-from block revision should stay report-only.')) {
        throw new RuntimeException('DOCX body handoff self-test rendered moved-from block tracked-change text');
    }
    if (str_contains($blocks, 'DOCX footnote continuation notice.')) {
        throw new RuntimeException('DOCX body handoff self-test rendered special footnote continuation notice');
    }

    foreach ([
        '<h1 id="docx-source-packet">DOCX source packet</h1>',
        '<h2 id="reviewer-checklist">Reviewer checklist</h2>',
        '<p><span class="docx-paragraph-align docx-align-center docx-paragraph-spacing docx-paragraph-indent docx-keep-next" data-docx-paragraph-align="center" data-docx-spacing-before-twips="240" data-docx-spacing-after-twips="120" data-docx-indent-left-twips="720" data-docx-indent-first-line-twips="240" data-docx-keep-next="true"><span class="docx-highlight docx-highlight-cyan docx-language" data-docx-highlight="cyan" data-docx-lang="en-US" lang="en-US"><em>Styled source packet note remains labeled.</em></span></span></p>',
        '<ul><li>Match media IDs<ul><li>Map hero attachment</li></ul></li><li>Preserve alt text</li></ul>',
        '<ol start="3" type="a"><li>Confirm source URL</li><li>Publish packet</li></ol>',
        '<ol start="5" type="I"><li>Escalate legal review</li><li>Final archive signoff</li></ol>',
        '<a href="#source_packet_anchor">source packet anchor</a>',
        '<a href="https://example.test/field-link?post=42" title="Field link title">field-coded source</a>',
        '<span class="docx-field docx-field-ref docx-field-hyperlink" data-docx-field="ref" data-docx-field-instruction="REF &quot;source_packet_anchor&quot; \h \* MERGEFORMAT" data-docx-field-target="source_packet_anchor" data-docx-field-format="MERGEFORMAT" data-docx-field-hyperlink="true">source packet anchor</span>',
        '<span class="docx-field docx-field-pageref docx-field-hyperlink docx-field-relative-position" data-docx-field="pageref" data-docx-field-instruction="PAGEREF source_packet_anchor \h \p" data-docx-field-target="source_packet_anchor" data-docx-field-hyperlink="true" data-docx-field-relative-position="true">12</span>',
        '<span class="docx-field docx-field-noteref docx-field-hyperlink docx-field-number" data-docx-field="noteref" data-docx-field-instruction="NOTEREF source_footnote \h \n" data-docx-field-target="source_footnote" data-docx-field-hyperlink="true" data-docx-field-number="true">c</span>',
        '<span class="docx-break docx-page-break docx-break-clear" data-docx-break-type="page" data-docx-break-clear="all">DOCX page break</span>',
        '<span class="docx-break docx-rendered-page-break" data-docx-break-type="rendered-page" data-docx-last-rendered-page-break="true">DOCX rendered page break</span>',
        '<span class="docx-break docx-column-break docx-break-clear" data-docx-break-type="column" data-docx-break-clear="left">DOCX column break</span>',
        '<span class="docx-tab docx-positional-tab docx-positional-tab-right docx-positional-tab-leader docx-positional-tab-leader-dot" data-docx-tab-type="positional" data-docx-tab-alignment="right" data-docx-tab-relative-to="margin" data-docx-tab-leader="dot">DOCX positional tab</span>',
        '<p>Generated page <span class="docx-run-field-marker docx-page-number-marker" data-docx-run-field-marker="page-number">DOCX page number</span> on <span class="docx-run-field-marker docx-date-field-marker docx-date-field-day-short" data-docx-run-field-marker="date" data-docx-date-field="dayShort">DOCX date field: dayShort</span>/<span class="docx-run-field-marker docx-date-field-marker docx-date-field-month-long" data-docx-run-field-marker="date" data-docx-date-field="monthLong">DOCX date field: monthLong</span>/<span class="docx-run-field-marker docx-date-field-marker docx-date-field-year-long" data-docx-run-field-marker="date" data-docx-date-field="yearLong">DOCX date field: yearLong</span> stays auditable.</p>',
        '<span class="docx-content-control docx-content-control-text" data-docx-sdt-id="42" data-docx-sdt-alias="Import Status" data-docx-sdt-tag="import_status" data-docx-sdt-type="text">Ready for import</span>',
        '<span class="docx-smart-tag" data-docx-smart-tag-uri="urn:schemas-microsoft-com:office:smarttags" data-docx-smart-tag-element="PersonName" data-docx-smart-tag-prop-normalized="Migration Desk" data-docx-smart-tag-prop-normalized-uri="https://example.test/docx/smart-tags" data-docx-smart-tag-prop-review-id="packet-42"><strong>Migration Desk</strong></span>',
        '<span class="docx-custom-xml" data-docx-custom-xml-uri="https://example.test/docx/custom" data-docx-custom-xml-element="packet-category" data-docx-custom-xml-prop-source-field="category" data-docx-custom-xml-prop-source-field-uri="https://example.test/docx/custom" data-docx-custom-xml-prop-review-id="packet-42"><em>Policy update</em></span>',
        '<p>Compatibility branch fallback reviewer text and <strong>supported reviewer branch</strong>.</p>',
        '<div class="docx-custom-xml" data-docx-custom-xml-uri="https://example.test/docx/custom" data-docx-custom-xml-element="review-section" data-docx-custom-xml-prop-section-id="source-review"><p>Custom XML review block for source packet.</p></div>',
        '<p>Decoded source symbols α • ✓ ← remain visible.</p>',
        '<p>Ruby reviewer term <span class="docx-ruby" data-docx-ruby-text="とうきょう" data-docx-ruby-align="center" data-docx-ruby-lang="ja-JP" data-docx-ruby-hps="14" data-docx-ruby-hps-raise="18" data-docx-ruby-hps-base-text="24"><strong>東京</strong></span> keeps pronunciation metadata.</p>',
        '<p>Reviewer marks <span class="docx-highlight docx-highlight-yellow" data-docx-highlight="yellow">priority update</span> and <span class="docx-shading" data-docx-shading-val="clear" data-docx-shading-fill="D9EAF7">source shading</span> plus <span class="docx-color docx-color-c00000 docx-theme-color docx-theme-color-accent2" data-docx-color="C00000" data-docx-theme-color="accent2" data-docx-theme-tint="33">redline label</span>.</p>',
        '<p>Run effect review <span class="docx-run-effect docx-run-hidden docx-run-web-hidden" data-docx-run-hidden="true" data-docx-run-web-hidden="true">hidden source clue</span> and <span class="docx-run-effect docx-emphasis-mark docx-emphasis-mark-dot docx-text-effect docx-text-effect-blinkbackground" data-docx-emphasis-mark="dot" data-docx-text-effect="blinkBackground">emphasis mark</span>.</p>',
        '<p>Proofing policy <span class="docx-no-proof" data-docx-no-proof="true">OCR packet label</span> remains reviewer-only.</p>',
        '<p>Typographic source label <span class="docx-run-metrics docx-run-spacing docx-run-spacing-expanded docx-run-scale docx-run-kern" data-docx-run-spacing-twips="30" data-docx-run-scale-percent="90" data-docx-run-kern-half-points="24">expanded reviewer copy</span> and <span class="docx-run-metrics docx-run-position docx-run-position-lowered docx-run-fit-text" data-docx-run-position-half-points="-6" data-docx-fit-text-width-twips="1200" data-docx-fit-text-id="fit-review">fit text label</span>.</p>',
        '<p><span class="docx-paragraph-align docx-align-center docx-formatting-change docx-paragraph-formatting-change" data-docx-paragraph-align="center" data-docx-formatting-change="paragraph" data-docx-change-id="24" data-docx-author="Layout Reviewer" data-docx-date="2026-06-05T18:30:00Z" data-docx-previous-paragraph-style="OldReviewLayout" data-docx-previous-paragraph-align="left">Tracked paragraph formatting remains auditable.</span></p>',
        '<p>Tracked run formatting <span class="docx-formatting-change docx-run-formatting-change" data-docx-formatting-change="run" data-docx-change-id="25" data-docx-author="Run Reviewer" data-docx-date="2026-06-05T18:35:00Z" data-docx-previous-italic="true" data-docx-previous-highlight="yellow" data-docx-previous-lang="fr-FR"><strong>approved label</strong></span> stays visible.</p>',
        '<p>Proof and permissions <span class="docx-proof-error docx-proof-spelling" data-docx-proof-error="spelling" data-docx-proof-start="spellStart" data-docx-proof-end="spellEnd">migraton</span> plus <span class="docx-permission-range docx-permission-group" data-docx-permission-id="70" data-docx-permission-group="everyone"><strong>review window</strong></span> stay labeled.</p>',
        '<p>Cross paragraph proof <span class="docx-proof-error docx-proof-grammar" data-docx-proof-error="grammar" data-docx-proof-start="gramStart">starts before review</span></p>',
        '<p><span class="docx-proof-error docx-proof-grammar" data-docx-proof-error="grammar" data-docx-proof-start="gramStart" data-docx-proof-end="gramEnd">continues after review</span> for import.</p>',
        '<p>Cross paragraph permission <span class="docx-permission-range docx-permission-user" data-docx-permission-id="71" data-docx-permission-user="reviewer@example.test"><strong>starts protected</strong></span></p>',
        '<p><span class="docx-permission-range docx-permission-user" data-docx-permission-id="71" data-docx-permission-user="reviewer@example.test">continues protected</span> for handoff.</p>',
        '<p>Character style reviewer label <span class="docx-highlight docx-highlight-yellow docx-language docx-shading" data-docx-highlight="yellow" data-docx-lang="fr-FR" lang="fr-FR" data-docx-shading-fill="FFE699"><strong><em><u>inherited urgency</u></em></strong></span> and <span class="docx-shading docx-language" data-docx-shading-fill="FFE699" data-docx-lang="de-DE" lang="de-DE"><strong><u>muted follow-up</u></strong></span>.</p>',
        '<p>Theme font reviewer label <span class="docx-theme-font docx-font" data-docx-theme-font-ascii="majorHAnsi" data-docx-font-ascii="Aptos Display" data-docx-theme-font-hansi="majorHAnsi" data-docx-font-hansi="Aptos Display" data-docx-theme-font-east-asia="majorEastAsia" data-docx-font-east-asia="Yu Gothic" data-docx-theme-font-complex-script="majorBidi" data-docx-font-complex-script="Arial">major theme source</span> and <span class="docx-font docx-theme-font" data-docx-font-ascii="Source Serif" data-docx-font-hansi="Source Serif" data-docx-theme-font-east-asia="minorEastAsia" data-docx-font-east-asia="Meiryo" data-docx-theme-font-complex-script="minorBidi" data-docx-font-complex-script="Times New Roman">direct font override</span>.</p>',
        '<p>Multilingual source note <span class="docx-language" data-docx-lang="es-ES" lang="es-ES">Resumen</span> and <span class="docx-language docx-rtl" data-docx-lang="ar-SA" data-docx-lang-bidi="ar-SA" lang="ar-SA" dir="rtl">ملف المصدر</span> remain labeled.</p>',
        '<p>Directional wrapper source <span class="docx-direction docx-dir docx-dir-rtl" data-docx-direction-kind="embedding" data-docx-direction="rtl" dir="rtl"><strong>ملف المصدر</strong></span> and override <span class="docx-direction docx-bidi-override docx-bdo docx-bdo-ltr" data-docx-direction-kind="override" data-docx-direction="ltr" data-docx-bidi-override="true" dir="ltr">ABC-123</span> remain traceable.</p>',
        '<p><span class="docx-paragraph-bidi docx-rtl docx-text-direction docx-text-direction-tbrl" data-docx-paragraph-bidi="true" dir="rtl" data-docx-text-direction="tbRl">ملف المصدر paragraph direction remains labeled.</span></p>',
        '<p><span class="docx-paragraph-align docx-align-center docx-paragraph-spacing docx-paragraph-indent docx-paragraph-tabs docx-keep-next docx-page-break-before" data-docx-paragraph-align="center" data-docx-spacing-before-twips="240" data-docx-spacing-after-twips="120" data-docx-spacing-line="360" data-docx-spacing-line-rule="auto" data-docx-indent-left-twips="720" data-docx-indent-first-line-twips="240" data-docx-tab-stop-count="2" data-docx-tab-1-val="left" data-docx-tab-1-pos-twips="720" data-docx-tab-2-val="decimal" data-docx-tab-2-pos-twips="1440" data-docx-tab-2-leader="dot" data-docx-keep-next="true" data-docx-page-break-before="true">Centered source packet layout remains labeled.</span></p>',
        '<p><span class="docx-paragraph-policy docx-keep-lines docx-widow-control-off docx-contextual-spacing docx-mirror-indents docx-suppress-line-numbers docx-suppress-auto-hyphens docx-snap-to-grid-off" data-docx-keep-lines="true" data-docx-widow-control="false" data-docx-contextual-spacing="true" data-docx-mirror-indents="true" data-docx-suppress-line-numbers="true" data-docx-suppress-auto-hyphens="true" data-docx-snap-to-grid="false">Paragraph policy source packet remains labeled.</span></p>',
        '<p><span class="docx-paragraph-border docx-border-top docx-border-top-single docx-border-bottom docx-border-bottom-double" data-docx-border-top-val="single" data-docx-border-top-size-eighth-points="8" data-docx-border-top-space-points="4" data-docx-border-top-color="4F81BD" data-docx-border-bottom-val="double" data-docx-border-bottom-size-eighth-points="12" data-docx-border-bottom-space-points="6" data-docx-border-bottom-theme-color="accent2" data-docx-border-bottom-theme-shade="66">Bordered source packet callout remains labeled.</span></p>',
        '<p>Textbox lead </p>',
        '<div class="docx-textbox docx-vml-textbox docx-vml-shape" data-docx-textbox-kind="vml" data-docx-shape-kind="shape" data-docx-shape-id="_x0000_s42" data-docx-shape-alt="Source review callout" data-docx-shape-style="width:216pt;height:48pt" data-docx-textbox-inset="6pt,3pt,6pt,3pt"><p>Source textbox note from VML shape.</p></div>',
        '<p>Source textbox note from VML shape.</p>',
        '<div class="docx-textbox docx-vml-textbox docx-vml-rect" data-docx-textbox-kind="vml" data-docx-shape-kind="rect" data-docx-shape-id="_x0000_s43" data-docx-shape-style="width:180pt;height:36pt" data-docx-textbox-inset="3pt,3pt,3pt,3pt"><p>Fallback textbox reminder.</p></div>',
        '<p>Fallback textbox reminder.</p>',
        '<div class="docx-textbox docx-drawing-textbox docx-drawing-geometry docx-drawing-inline docx-drawing-shape docx-shape-textbox docx-shape-transform docx-shape-preset-rect docx-textbox-body-properties docx-textbox-anchor-ctr docx-textbox-wrap-square docx-textbox-autofit-none" data-docx-textbox-kind="drawingml" data-docx-docpr-id="81" data-docx-docpr-name="Source DrawingML textbox" data-docx-docpr-description="Drawing textbox source note" data-docx-docpr-title="Source callout" data-docx-drawing-placement="inline" data-docx-distance-left-emu="91440" data-docx-distance-right-emu="91440" data-docx-width-emu="1200000" data-docx-height-emu="480000" data-docx-shape-textbox="true" data-docx-shape-rotation="900000" data-docx-shape-offset-x-emu="6000" data-docx-shape-offset-y-emu="12000" data-docx-shape-width-emu="1200000" data-docx-shape-height-emu="480000" data-docx-shape-preset="rect" data-docx-textbox-anchor="ctr" data-docx-textbox-wrap="square" data-docx-textbox-inset-left-emu="91440" data-docx-textbox-inset-top-emu="45720" data-docx-textbox-inset-right-emu="91440" data-docx-textbox-inset-bottom-emu="45720" data-docx-textbox-autofit="none"><p>Source DrawingML textbox note.</p></div>',
        '<p>Source DrawingML textbox note.</p>',
        '<p> textbox tail.</p>',
        '<p>Cross paragraph comment <span class="docx-comment-range" data-docx-comment-id="10" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-05T03:20:00Z" data-docx-comment-para-id="00DOCX10" data-docx-comment-parent-para-id="00DOCX09" data-docx-comment-resolved="false">starts here</span></p>',
        '<p><span class="docx-comment-range" data-docx-comment-id="10" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-05T03:20:00Z" data-docx-comment-para-id="00DOCX10" data-docx-comment-parent-para-id="00DOCX09" data-docx-comment-resolved="false">continues here</span> for import review',
        '<p>Cross move destination <span class="docx-move-to-range" data-docx-change="move-to-range" data-docx-change-id="80" data-docx-author="Migration Editor" data-docx-date="2026-06-08T11:25:00Z" data-docx-move-range-name="cross_move_destination">starts accepted</span></p>',
        '<p><span class="docx-move-to-range" data-docx-change="move-to-range" data-docx-change-id="80" data-docx-author="Migration Editor" data-docx-date="2026-06-08T11:25:00Z" data-docx-move-range-name="cross_move_destination">continues accepted</span> for publication.</p>',
        '<p>Cross moved-from source </p>',
        '<p> stays visible after source.</p>',
        '<span id="source_packet_anchor" class="anchor"></span>Import reviewer keeps',
        '<a href="https://example.test/source-packet?post=42" title="Source packet tooltip" class="docx-hyperlink" data-docx-tooltip="Source packet tooltip" data-docx-relationship-id="rIdSource" data-docx-doc-location="ReviewSection" data-docx-target-frame="_blank" data-docx-history="true">the source link</a>',
        '<span id="review_column_range" class="anchor docx-bookmark docx-bookmark-column-range" data-docx-bookmark-id="21" data-docx-bookmark-name="review_column_range" data-docx-bookmark-col-first="0" data-docx-bookmark-col-last="1"></span>Reviewed table scope',
        '<span class="docx-insertion" data-docx-change="insertion" data-docx-change-id="8" data-docx-author="Migration Editor" data-docx-date="2026-06-04T17:50:00Z"> Approved tracked wording.</span>',
        '<span class="docx-move-to" data-docx-change="move-to" data-docx-change-id="17" data-docx-author="Migration Editor" data-docx-date="2026-06-04T18:07:00Z"> Moved into import checklist.</span>',
        '<span class="docx-move-to-range" data-docx-change="move-to-range" data-docx-change-id="27" data-docx-author="Migration Editor" data-docx-date="2026-06-05T08:12:00Z" data-docx-move-range-name="accepted_review_range"> Ranged move accepted for checklist.</span>',
        '<div class="docx-insertion" data-docx-change="insertion" data-docx-change-id="51" data-docx-author="Migration Editor" data-docx-date="2026-06-05T09:05:00Z"><p>Accepted block revision paragraph.</p><table><tbody><tr><td><p>Accepted block table label</p></td><td><p>Ready</p></td></tr></tbody></table></div>',
        '<div class="docx-move-to" data-docx-change="move-to" data-docx-change-id="53" data-docx-author="Migration Editor" data-docx-date="2026-06-05T09:12:00Z"><p>Accepted moved block revision paragraph.</p></div>',
        'and flag missing note references<sup id="fnref-4"><a href="#fn-4" role="doc-noteref">4</a></sup>/<sup id="fnref-5"><a href="#fn-5" role="doc-noteref">5</a></sup>',
        'while automatic note labels remain auditable<sup id="fnref-6"><a href="#fn-6" role="doc-noteref">6</a></sup>/<sup id="fnref-7"><a href="#fn-7" role="doc-noteref">7</a></sup>',
        '<span class="docx-comment-range" data-docx-comment-id="9" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-04T09:55:00Z" data-docx-comment-para-id="00DOCX09" data-docx-comment-resolved="true"> and reviewer comment</span>',
        '<aside data-review="docx-alt"><p>Alternative HTML chunk from source packet.</p></aside>',
        '<p>Plain text source note<br/>Second imported line</p>',
        '<p>Final plain-text checkpoint.</p>',
        '<span class="math inline">\(x_{i} + \frac{1}{\sqrt{n}} + \sum_{i=1}^{n} a_i\)</span>',
        '<div class="docx-content-control docx-content-control-rich-text" data-docx-sdt-id="99" data-docx-sdt-alias="Review Checklist" data-docx-sdt-tag="review_checklist"',
        'data-docx-sdt-xpath="/packet/review/checklist"',
        'data-docx-sdt-placeholder="ReviewChecklistPlaceholder"',
        'data-docx-sdt-doc-part-kind="object"',
        'data-docx-sdt-doc-part-gallery="Quick Parts"',
        'data-docx-sdt-doc-part-category="Migration Review"',
        '<p>Content-control checklist for reviewer handoff.</p>',
        '<span class="docx-content-control docx-content-control-checkbox" data-docx-sdt-id="142" data-docx-sdt-alias="Approval Checkbox" data-docx-sdt-tag="approval_checkbox" data-docx-sdt-type="checkbox" data-docx-sdt-checkbox-checked="false" data-docx-sdt-checkbox-checked-state-value="2612" data-docx-sdt-checkbox-checked-state-font="MS Gothic" data-docx-sdt-checkbox-unchecked-state-value="2610" data-docx-sdt-checkbox-unchecked-state-font="MS Gothic">Needs review</span>',
        '<span class="docx-content-control docx-content-control-drop-down-list" data-docx-sdt-id="143" data-docx-sdt-alias="Publish Target" data-docx-sdt-tag="publish_target" data-docx-sdt-type="drop-down-list" data-docx-sdt-list-kind="drop-down-list" data-docx-sdt-list-last-value="publish" data-docx-sdt-list-item-1-display-text="Draft review" data-docx-sdt-list-item-1-value="draft" data-docx-sdt-list-item-2-display-text="Publish to site" data-docx-sdt-list-item-2-value="publish" data-docx-sdt-list-item-count="2">Publish to site</span>',
        '<img src="word/media/hero.png" alt="Source hero alt" title="Source hero" class="docx-drawing-geometry docx-drawing-inline docx-picture-crop docx-picture-transform docx-picture-flip-horizontal" data-docx-drawing-placement="inline"',
        'data-docx-width-emu="914400" data-docx-height-emu="457200"',
        '<img src="https://cdn.example.test/docx-review-chart.png" alt="Linked review chart alt" title="Linked review chart"/>',
        '<img src="word/media/vml-badge.png" alt="VML badge alt" title="VML badge title"/>',
        '<span class="docx-drawing-placeholder docx-drawing-chart" data-docx-drawing-kind="chart" data-docx-docpr-id="18" data-docx-docpr-name="Review chart" data-docx-docpr-descr="Imported review chart" data-docx-docpr-title="Review chart" data-docx-relationship-id="rIdReviewChart"',
        'data-docx-target-part="/word/charts/review-chart.xml"',
        'DOCX chart: Imported review chart</span>',
        '<span class="docx-drawing-text" data-docx-drawing-kind="text" data-docx-drawing-text-paragraphs="1" data-docx-docpr-id="20" data-docx-docpr-name="Review callout" data-docx-docpr-descr="Imported review callout" data-docx-docpr-title="Review callout">Source callout note<br/>Verify chart captions</span>',
        '<span class="docx-drawing-placeholder docx-drawing-diagram" data-docx-drawing-kind="diagram" data-docx-docpr-id="19" data-docx-docpr-name="Review workflow" data-docx-docpr-descr="Imported workflow diagram" data-docx-docpr-title="Review workflow" data-docx-diagram-data-id="rIdReviewDiagramData"',
        'data-docx-diagram-layout-target-part="/word/diagrams/review-layout.xml"',
        'DOCX diagram: Imported workflow diagram</span>',
        '<span class="docx-embedded-object docx-embedded-ole-object" data-docx-embedded-kind="ole-object" data-docx-relationship-id="rIdReviewOleWorkbook"',
        'data-docx-target-part="/word/embeddings/review-workbook.bin"',
        'DOCX embedded OLE object: Review workbook</span>',
        '<span class="docx-embedded-object docx-embedded-package" data-docx-embedded-kind="package" data-docx-relationship-id="rIdReviewEmbeddedPackage"',
        'data-docx-target-part="/word/embeddings/source-audit.xlsx"',
        'DOCX embedded package: Source audit package</span>',
        '<span class="docx-subdocument" data-docx-subdocument="true" data-docx-relationship-id="rIdSourceSubdocument"',
        'data-docx-target="https://example.test/source-master/subdocument.docx"',
        'DOCX subdocument: https://example.test/source-master/subdocument.docx</span>',
        '<table class="docx-table-metadata" aria-description="Reviewer summary table from the source DOCX package." data-docx-table-description="Reviewer summary table from the source DOCX package.">',
        '<tr class="docx-table-row-repeat-header" data-docx-table-row-repeat-header="true">',
        '<td class="docx-cell-width docx-cell-width-pct docx-cell-margin docx-cell-margin-top docx-cell-margin-start docx-cell-margin-bottom docx-cell-margin-end docx-cell-margin-dxa docx-cell-vertical-align docx-cell-vertical-align-center docx-cell-shading docx-cell-shading-clear docx-cell-fill-d9eaf7"',
        'data-docx-cell-margin-top-points="6"',
        'data-docx-cell-margin-start-points="12"',
        'style="width:100%; padding-top:6pt; padding-inline-start:12pt; padding-bottom:6pt; padding-inline-end:12pt; background-color:#D9EAF7"><p>Review scope</p></td><td><p>Status</p></td>',
        '<tr class="docx-table-row-cant-split" data-docx-table-row-cant-split="true">',
        '<td><p>Owner</p></td><td colspan="2"><p>Migration desk</p></td>',
        '<tr class="docx-table-row-grid-before docx-table-row-width-before docx-table-row-width-before-dxa docx-table-row-grid-after docx-table-row-width-after docx-table-row-width-after-auto" data-docx-table-row-grid-before="1" data-docx-table-row-width-before-type="dxa" data-docx-table-row-width-before-value="720" data-docx-table-row-width-before-points="36" data-docx-table-row-grid-after="1" data-docx-table-row-width-after-type="auto" data-docx-table-row-width-after-value="0">',
        '<td class="docx-omitted-table-cell docx-omitted-table-cell-before" data-docx-omitted-table-cell="before" data-docx-omitted-grid-count="1" data-docx-omitted-grid-index="1" aria-hidden="true"></td><td><p>Omitted grid reviewer note</p></td><td class="docx-omitted-table-cell docx-omitted-table-cell-after" data-docx-omitted-table-cell="after" data-docx-omitted-grid-count="1" data-docx-omitted-grid-index="1" aria-hidden="true"></td>',
        '<figcaption class="wp-element-caption">DOCX review table</figcaption>',
        '<span class="docx-reference-marker docx-footnote-reference-marker" data-docx-reference-marker="footnote">DOCX footnote reference marker</span> DOCX footnote import note.<br/>Second footnote marker line.',
        '<span class="docx-reference-marker docx-endnote-reference-marker" data-docx-reference-marker="endnote">DOCX endnote reference marker</span> DOCX endnote import note.',
        '<span class="docx-reference-marker docx-annotation-reference-marker" data-docx-reference-marker="annotation">DOCX annotation reference marker</span> DOCX reviewer comment import note.',
        'DOCX footnote import note.',
        'DOCX endnote import note.',
        'DOCX automatic footnote label note.',
        'DOCX automatic endnote label note.',
        'DOCX reviewer comment import note.',
        'DOCX multi-paragraph reviewer comment import note.',
        '<li id="fn-4"> <a href="#fnref-4" aria-label="Back to content">Back</a></li>',
        '<li id="fn-5"> <a href="#fnref-5" aria-label="Back to content">Back</a></li>',
        '<li id="fn-6"><p>DOCX automatic footnote label note.</p> <a href="#fnref-6" aria-label="Back to content">Back</a></li>',
        '<li id="fn-7"><p>DOCX automatic endnote label note.</p> <a href="#fnref-7" aria-label="Back to content">Back</a></li>',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            throw new RuntimeException('DOCX body handoff self-test missing: ' . $needle);
        }
    }
    if (str_contains($blocks, '_GoBack')) {
        throw new RuntimeException('DOCX body handoff self-test rendered dummy Word return bookmark');
    }
    if (str_contains($blocks, 'unsupported reviewer text') || str_contains($blocks, 'unused reviewer fallback')) {
        throw new RuntimeException('DOCX body handoff self-test rendered unselected AlternateContent branch');
    }

    echo "docx body handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
