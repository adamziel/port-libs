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
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
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
</Types>
XML;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
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
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
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
  xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>DOCX source packet</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ReviewSubhead"/></w:pPr><w:r><w:t>Reviewer checklist</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ReviewLayout"/></w:pPr><w:r><w:t>Styled source packet note remains labeled.</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ChecklistBullet"/></w:pPr><w:r><w:t>Match media IDs</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="11"/></w:numPr></w:pPr><w:r><w:t>Map hero attachment</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ChecklistBullet"/></w:pPr><w:r><w:t>Preserve alt text</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="12"/></w:numPr></w:pPr><w:r><w:t>Confirm source URL</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="12"/></w:numPr></w:pPr><w:r><w:t>Publish packet</w:t></w:r></w:p>
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
      <w:r><w:t xml:space="preserve">Layout checkpoints </w:t></w:r>
      <w:r><w:br w:type="page" w:clear="all"/></w:r>
      <w:r><w:t xml:space="preserve"> after page </w:t></w:r>
      <w:r><w:lastRenderedPageBreak/></w:r>
      <w:r><w:t xml:space="preserve"> after rendered page </w:t></w:r>
      <w:r><w:br w:type="column" w:clear="left"/></w:r>
      <w:r><w:t xml:space="preserve"> after column.</w:t></w:r>
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
      <w:r><w:t xml:space="preserve">Reviewer marks </w:t></w:r>
      <w:r><w:rPr><w:highlight w:val="yellow"/></w:rPr><w:t>priority update</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:shd w:val="clear" w:fill="D9EAF7"/></w:rPr><w:t>source shading</w:t></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Character style reviewer label </w:t></w:r>
      <w:r><w:rPr><w:rStyle w:val="ReviewAlert"/></w:rPr><w:t>inherited urgency</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:rStyle w:val="ReviewMuted"/></w:rPr><w:t>muted follow-up</w:t></w:r>
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
      <w:pPr><w:bidi/><w:textDirection w:val="tbRl"/></w:pPr>
      <w:r><w:t>ملف المصدر paragraph direction remains labeled.</w:t></w:r>
    </w:p>
    <w:p>
      <w:pPr>
        <w:jc w:val="center"/>
        <w:spacing w:before="240" w:after="120" w:line="360" w:lineRule="auto"/>
        <w:ind w:left="720" w:firstLine="240"/>
        <w:keepNext/>
        <w:pageBreakBefore/>
      </w:pPr>
      <w:r><w:t>Centered source packet layout remains labeled.</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Textbox lead </w:t></w:r>
      <w:r>
        <w:pict>
          <v:shape id="_x0000_s42">
            <v:textbox>
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
              <v:rect id="_x0000_s43">
                <v:textbox>
                  <w:txbxContent>
                    <w:p><w:r><w:t>Fallback textbox reminder.</w:t></w:r></w:p>
                  </w:txbxContent>
                </v:textbox>
              </v:rect>
            </w:pict>
          </mc:Fallback>
        </mc:AlternateContent>
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
      <w:bookmarkStart w:id="14" w:name="source_packet_anchor"/>
      <w:bookmarkStart w:id="15" w:name="_GoBack"/>
      <w:bookmarkEnd w:id="15"/>
      <w:r><w:t xml:space="preserve">Import reviewer keeps </w:t></w:r>
      <w:hyperlink r:id="rIdSource"><w:r><w:t>the source link</w:t></w:r></w:hyperlink>
      <w:r><w:t xml:space="preserve"> visible.</w:t></w:r>
      <w:bookmarkEnd w:id="14"/>
      <w:del w:id="7" w:author="Source Editor" w:date="2026-06-04T17:45:00Z">
        <w:r><w:delText>Old reviewer draft.</w:delText></w:r>
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
      </m:oMath>
      <w:r><w:t xml:space="preserve"> stays native.</w:t></w:r>
    </w:p>
    <w:sdt>
      <w:sdtPr>
        <w:id w:val="99"/>
        <w:alias w:val="Review Checklist"/>
        <w:tag w:val="review_checklist"/>
        <w:richText/>
        <w:dataBinding w:xpath="/packet/review/checklist" w:storeItemID="{11111111-2222-3333-4444-555555555555}"/>
      </w:sdtPr>
      <w:sdtContent>
        <w:p><w:r><w:t>Content-control checklist for reviewer handoff.</w:t></w:r></w:p>
      </w:sdtContent>
    </w:sdt>
    <w:p><w:r><w:drawing><wp:inline><wp:docPr id="9" name="Hero" descr="Source hero alt" title="Source hero"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdHero"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline><wp:anchor><wp:docPr id="10" name="Review chart" descr="Linked review chart alt" title="Linked review chart"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:link="rIdExternalChart"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:anchor></w:drawing></w:r></w:p>
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
    <w:tbl>
      <w:tblPr>
        <w:tblCaption w:val="DOCX review table"/>
        <w:tblDescription w:val="Reviewer summary table from the source DOCX package."/>
      </w:tblPr>
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
          <w:p><w:r><w:t>Continuation marker should not render</w:t></w:r></w:p>
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
    <w:sectPr>
      <w:headerReference w:type="default" r:id="rIdHeaderDefault"/>
      <w:footerReference w:type="default" r:id="rIdFooterDefault"/>
      <w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>
      <w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360"/>
      <w:cols w:num="2" w:space="360" w:equalWidth="0"/>
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
</w:styles>
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
</w:numbering>
XML],
    ['name' => 'word/footnotes.xml', 'data' => <<<'XML'
<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:footnote w:id="2"><w:p><w:r><w:footnoteRef/><w:t xml:space="preserve"> DOCX footnote import note.</w:t><w:cr/><w:t>Second footnote marker line.</w:t></w:r></w:p></w:footnote>
  <w:footnote w:id="3"><w:p><w:r><w:t>DOCX automatic footnote label note.</w:t></w:r></w:p></w:footnote>
</w:footnotes>
XML],
    ['name' => 'word/endnotes.xml', 'data' => <<<'XML'
<w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
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
    if (($summary['importReport']['media']['embeddedCount'] ?? 0) !== 2) {
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
    if (($summary['importReport']['revisions']['insertionCount'] ?? 0) !== 2 || ($summary['importReport']['revisions']['deletionCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing tracked-change report');
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
    if (($summary['importReport']['notes']['count'] ?? 0) !== 8) {
        throw new RuntimeException('DOCX body handoff self-test missing note-reference import report');
    }
    if (($summary['importReport']['notes']['footnoteCount'] ?? 0) !== 3 || ($summary['importReport']['notes']['endnoteCount'] ?? 0) !== 3 || ($summary['importReport']['notes']['commentCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing typed note-reference counts');
    }
    if (($summary['importReport']['notes']['missingCount'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing unresolved note-reference count');
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
    if (str_contains($blocks, 'moved from an obsolete review section')) {
        throw new RuntimeException('DOCX body handoff self-test rendered moved-from tracked-change text');
    }

    foreach ([
        '<h1 id="docx-source-packet">DOCX source packet</h1>',
        '<h2 id="reviewer-checklist">Reviewer checklist</h2>',
        '<p><span class="docx-paragraph-align docx-align-center docx-paragraph-spacing docx-paragraph-indent docx-keep-next" data-docx-paragraph-align="center" data-docx-spacing-before-twips="240" data-docx-spacing-after-twips="120" data-docx-indent-left-twips="720" data-docx-indent-first-line-twips="240" data-docx-keep-next="true">Styled source packet note remains labeled.</span></p>',
        '<ul><li>Match media IDs<ul><li>Map hero attachment</li></ul></li><li>Preserve alt text</li></ul>',
        '<ol start="3" type="a"><li>Confirm source URL</li><li>Publish packet</li></ol>',
        '<a href="#source_packet_anchor">source packet anchor</a>',
        '<a href="https://example.test/field-link?post=42" title="Field link title">field-coded source</a>',
        '<span class="docx-break docx-page-break docx-break-clear" data-docx-break-type="page" data-docx-break-clear="all">DOCX page break</span>',
        '<span class="docx-break docx-rendered-page-break" data-docx-break-type="rendered-page" data-docx-last-rendered-page-break="true">DOCX rendered page break</span>',
        '<span class="docx-break docx-column-break docx-break-clear" data-docx-break-type="column" data-docx-break-clear="left">DOCX column break</span>',
        '<span class="docx-content-control docx-content-control-text" data-docx-sdt-id="42" data-docx-sdt-alias="Import Status" data-docx-sdt-tag="import_status" data-docx-sdt-type="text">Ready for import</span>',
        '<span class="docx-smart-tag" data-docx-smart-tag-uri="urn:schemas-microsoft-com:office:smarttags" data-docx-smart-tag-element="PersonName" data-docx-smart-tag-prop-normalized="Migration Desk" data-docx-smart-tag-prop-normalized-uri="https://example.test/docx/smart-tags" data-docx-smart-tag-prop-review-id="packet-42"><strong>Migration Desk</strong></span>',
        '<span class="docx-custom-xml" data-docx-custom-xml-uri="https://example.test/docx/custom" data-docx-custom-xml-element="packet-category" data-docx-custom-xml-prop-source-field="category" data-docx-custom-xml-prop-source-field-uri="https://example.test/docx/custom" data-docx-custom-xml-prop-review-id="packet-42"><em>Policy update</em></span>',
        '<p>Compatibility branch fallback reviewer text and <strong>supported reviewer branch</strong>.</p>',
        '<div class="docx-custom-xml" data-docx-custom-xml-uri="https://example.test/docx/custom" data-docx-custom-xml-element="review-section" data-docx-custom-xml-prop-section-id="source-review"><p>Custom XML review block for source packet.</p></div>',
        '<p>Decoded source symbols α • ✓ ← remain visible.</p>',
        '<p>Reviewer marks <span class="docx-highlight docx-highlight-yellow" data-docx-highlight="yellow">priority update</span> and <span class="docx-shading" data-docx-shading-val="clear" data-docx-shading-fill="D9EAF7">source shading</span>.</p>',
        '<p>Character style reviewer label <span class="docx-highlight docx-highlight-yellow docx-language docx-shading" data-docx-highlight="yellow" data-docx-lang="fr-FR" lang="fr-FR" data-docx-shading-fill="FFE699"><strong><em><u>inherited urgency</u></em></strong></span> and <span class="docx-shading docx-language" data-docx-shading-fill="FFE699" data-docx-lang="de-DE" lang="de-DE"><strong><u>muted follow-up</u></strong></span>.</p>',
        '<p>Multilingual source note <span class="docx-language" data-docx-lang="es-ES" lang="es-ES">Resumen</span> and <span class="docx-language docx-rtl" data-docx-lang="ar-SA" data-docx-lang-bidi="ar-SA" lang="ar-SA" dir="rtl">ملف المصدر</span> remain labeled.</p>',
        '<p><span class="docx-paragraph-bidi docx-rtl docx-text-direction docx-text-direction-tbrl" data-docx-paragraph-bidi="true" dir="rtl" data-docx-text-direction="tbRl">ملف المصدر paragraph direction remains labeled.</span></p>',
        '<p><span class="docx-paragraph-align docx-align-center docx-paragraph-spacing docx-paragraph-indent docx-keep-next docx-page-break-before" data-docx-paragraph-align="center" data-docx-spacing-before-twips="240" data-docx-spacing-after-twips="120" data-docx-spacing-line="360" data-docx-spacing-line-rule="auto" data-docx-indent-left-twips="720" data-docx-indent-first-line-twips="240" data-docx-keep-next="true" data-docx-page-break-before="true">Centered source packet layout remains labeled.</span></p>',
        '<p>Textbox lead </p>',
        '<p>Source textbox note from VML shape.</p>',
        '<p>Fallback textbox reminder.</p>',
        '<p> textbox tail.</p>',
        '<p>Cross paragraph comment <span class="docx-comment-range" data-docx-comment-id="10" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-05T03:20:00Z" data-docx-comment-para-id="00DOCX10" data-docx-comment-parent-para-id="00DOCX09" data-docx-comment-resolved="false">starts here</span></p>',
        '<p><span class="docx-comment-range" data-docx-comment-id="10" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-05T03:20:00Z" data-docx-comment-para-id="00DOCX10" data-docx-comment-parent-para-id="00DOCX09" data-docx-comment-resolved="false">continues here</span> for import review',
        '<span id="source_packet_anchor" class="anchor"></span>Import reviewer keeps',
        '<a href="https://example.test/source-packet?post=42">the source link</a>',
        '<span id="review_column_range" class="anchor docx-bookmark docx-bookmark-column-range" data-docx-bookmark-id="21" data-docx-bookmark-name="review_column_range" data-docx-bookmark-col-first="0" data-docx-bookmark-col-last="1"></span>Reviewed table scope',
        '<span class="docx-insertion" data-docx-change="insertion" data-docx-change-id="8" data-docx-author="Migration Editor" data-docx-date="2026-06-04T17:50:00Z"> Approved tracked wording.</span>',
        '<span class="docx-move-to" data-docx-change="move-to" data-docx-change-id="17" data-docx-author="Migration Editor" data-docx-date="2026-06-04T18:07:00Z"> Moved into import checklist.</span>',
        'and flag missing note references<sup id="fnref-4"><a href="#fn-4" role="doc-noteref">4</a></sup>/<sup id="fnref-5"><a href="#fn-5" role="doc-noteref">5</a></sup>',
        'while automatic note labels remain auditable<sup id="fnref-6"><a href="#fn-6" role="doc-noteref">6</a></sup>/<sup id="fnref-7"><a href="#fn-7" role="doc-noteref">7</a></sup>',
        '<span class="docx-comment-range" data-docx-comment-id="9" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-04T09:55:00Z" data-docx-comment-para-id="00DOCX09" data-docx-comment-resolved="true"> and reviewer comment</span>',
        '<aside data-review="docx-alt"><p>Alternative HTML chunk from source packet.</p></aside>',
        '<p>Plain text source note<br/>Second imported line</p>',
        '<p>Final plain-text checkpoint.</p>',
        '<span class="math inline">\(x_{i} + \frac{1}{\sqrt{n}}\)</span>',
        '<div class="docx-content-control docx-content-control-rich-text" data-docx-sdt-id="99" data-docx-sdt-alias="Review Checklist" data-docx-sdt-tag="review_checklist"',
        'data-docx-sdt-xpath="/packet/review/checklist"',
        '<p>Content-control checklist for reviewer handoff.</p>',
        '<img src="word/media/hero.png" alt="Source hero alt" title="Source hero"/>',
        '<img src="https://cdn.example.test/docx-review-chart.png" alt="Linked review chart alt" title="Linked review chart"/>',
        '<img src="word/media/vml-badge.png" alt="VML badge alt" title="VML badge title"/>',
        '<span class="docx-drawing-placeholder docx-drawing-chart" data-docx-drawing-kind="chart" data-docx-docpr-id="18" data-docx-docpr-name="Review chart" data-docx-docpr-descr="Imported review chart" data-docx-docpr-title="Review chart" data-docx-relationship-id="rIdReviewChart"',
        'data-docx-target-part="/word/charts/review-chart.xml"',
        'DOCX chart: Imported review chart</span>',
        '<span class="docx-drawing-placeholder docx-drawing-diagram" data-docx-drawing-kind="diagram" data-docx-docpr-id="19" data-docx-docpr-name="Review workflow" data-docx-docpr-descr="Imported workflow diagram" data-docx-docpr-title="Review workflow" data-docx-diagram-data-id="rIdReviewDiagramData"',
        'data-docx-diagram-layout-target-part="/word/diagrams/review-layout.xml"',
        'DOCX diagram: Imported workflow diagram</span>',
        '<span class="docx-embedded-object docx-embedded-ole-object" data-docx-embedded-kind="ole-object" data-docx-relationship-id="rIdReviewOleWorkbook"',
        'data-docx-target-part="/word/embeddings/review-workbook.bin"',
        'DOCX embedded OLE object: Review workbook</span>',
        '<span class="docx-embedded-object docx-embedded-package" data-docx-embedded-kind="package" data-docx-relationship-id="rIdReviewEmbeddedPackage"',
        'data-docx-target-part="/word/embeddings/source-audit.xlsx"',
        'DOCX embedded package: Source audit package</span>',
        '<table class="docx-table-metadata" aria-description="Reviewer summary table from the source DOCX package.">',
        '<td colspan="2" rowspan="2"><p>Review scope</p></td><td><p>Status</p></td>',
        '<td><p>Owner</p></td><td colspan="2"><p>Migration desk</p></td>',
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
