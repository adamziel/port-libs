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
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/word/endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
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
  <Relationship Id="rIdFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>
  <Relationship Id="rIdEndnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="endnotes.xml"/>
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
  <Relationship Id="rIdHeaderDefault" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>
  <Relationship Id="rIdFooterDefault" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
  <Relationship Id="rIdReviewChunk" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/review.html"/>
  <Relationship Id="rIdPlainTextChunk" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/plain-review.txt"/>
</Relationships>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>DOCX source packet</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ReviewSubhead"/></w:pPr><w:r><w:t>Reviewer checklist</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ChecklistBullet"/></w:pPr><w:r><w:t>Match media IDs</w:t></w:r></w:p>
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
      <w:r><w:footnoteReference w:id="2"/></w:r>
      <w:r><w:t xml:space="preserve"> Also keep endnote context</w:t></w:r>
      <w:r><w:endnoteReference w:id="5"/></w:r>
      <w:commentRangeStart w:id="9"/>
      <w:r><w:t xml:space="preserve"> and reviewer comment</w:t></w:r>
      <w:commentRangeEnd w:id="9"/>
      <w:r><w:commentReference w:id="9"/></w:r>
    </w:p>
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
    <w:p><w:r><w:drawing><wp:inline><wp:docPr id="9" name="Hero" descr="Source hero alt" title="Source hero"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdHero"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>
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
    </w:sectPr>
  </w:body>
</w:document>
XML],
    ['name' => 'word/chunks/review.html', 'data' => '<aside data-review="docx-alt"><p>Alternative HTML chunk from source packet.</p></aside>'],
    ['name' => 'word/chunks/plain-review.txt', 'data' => "\xEF\xBB\xBFPlain text source note\r\nSecond imported line\r\n\r\nFinal plain-text checkpoint."],
    ['name' => 'word/styles.xml', 'data' => <<<'XML'
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/></w:style>
  <w:style w:type="paragraph" w:styleId="ReviewSubhead"><w:name w:val="Review Subhead"/><w:basedOn w:val="Heading2"/></w:style>
  <w:style w:type="paragraph" w:styleId="ChecklistBullet"><w:name w:val="Checklist Bullet"/><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="11"/></w:numPr></w:pPr></w:style>
</w:styles>
XML],
    ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="10"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="-"/></w:lvl></w:abstractNum>
  <w:num w:numId="11"><w:abstractNumId w:val="10"/></w:num>
  <w:abstractNum w:abstractNumId="20"><w:lvl w:ilvl="0"><w:start w:val="3"/><w:numFmt w:val="lowerLetter"/><w:lvlText w:val="%1)"/></w:lvl></w:abstractNum>
  <w:num w:numId="12"><w:abstractNumId w:val="20"/></w:num>
</w:numbering>
XML],
    ['name' => 'word/footnotes.xml', 'data' => <<<'XML'
<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:footnote w:id="2"><w:p><w:r><w:t>DOCX footnote import note.</w:t></w:r></w:p></w:footnote>
</w:footnotes>
XML],
    ['name' => 'word/endnotes.xml', 'data' => <<<'XML'
<w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:endnote w:id="5"><w:p><w:r><w:t>DOCX endnote import note.</w:t></w:r></w:p></w:endnote>
</w:endnotes>
XML],
    ['name' => 'word/comments.xml', 'data' => <<<'XML'
<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:comment w:id="9" w:author="Migration Reviewer" w:initials="MR" w:date="2026-06-04T09:55:00Z">
    <w:p><w:r><w:t>DOCX reviewer comment import note.</w:t></w:r></w:p>
  </w:comment>
</w:comments>
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
    ['name' => 'word/media/hero.png', 'data' => 'PNGDATA'],
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
    if (($summary['importReport']['media']['embeddedCount'] ?? 0) !== 1) {
        throw new RuntimeException('DOCX body handoff self-test missing media import report');
    }
    if (($summary['importReport']['media']['items'][0]['bytes'] ?? 0) !== 7) {
        throw new RuntimeException('DOCX body handoff self-test missing media byte count');
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
    if (($summary['sectionProperties'][0]['pageSize']['orientation'] ?? '') !== 'landscape') {
        throw new RuntimeException('DOCX body handoff self-test missing section page orientation');
    }
    if (($summary['sectionProperties'][0]['columns']['count'] ?? 0) !== 2) {
        throw new RuntimeException('DOCX body handoff self-test missing section column count');
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
        '<ul><li>Match media IDs</li><li>Preserve alt text</li></ul>',
        '<ol start="3" type="a"><li>Confirm source URL</li><li>Publish packet</li></ol>',
        '<a href="#source_packet_anchor">source packet anchor</a>',
        '<a href="https://example.test/field-link?post=42" title="Field link title">field-coded source</a>',
        '<span class="docx-content-control docx-content-control-text" data-docx-sdt-id="42" data-docx-sdt-alias="Import Status" data-docx-sdt-tag="import_status" data-docx-sdt-type="text">Ready for import</span>',
        '<span id="source_packet_anchor" class="anchor"></span>Import reviewer keeps',
        '<a href="https://example.test/source-packet?post=42">the source link</a>',
        '<span class="docx-insertion" data-docx-change="insertion" data-docx-change-id="8" data-docx-author="Migration Editor" data-docx-date="2026-06-04T17:50:00Z"> Approved tracked wording.</span>',
        '<span class="docx-move-to" data-docx-change="move-to" data-docx-change-id="17" data-docx-author="Migration Editor" data-docx-date="2026-06-04T18:07:00Z"> Moved into import checklist.</span>',
        '<span class="docx-comment-range" data-docx-comment-id="9" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-04T09:55:00Z"> and reviewer comment</span>',
        '<aside data-review="docx-alt"><p>Alternative HTML chunk from source packet.</p></aside>',
        '<p>Plain text source note<br/>Second imported line</p>',
        '<p>Final plain-text checkpoint.</p>',
        '<span class="math inline">\(x_{i} + \frac{1}{\sqrt{n}}\)</span>',
        '<div class="docx-content-control docx-content-control-rich-text" data-docx-sdt-id="99" data-docx-sdt-alias="Review Checklist" data-docx-sdt-tag="review_checklist"',
        'data-docx-sdt-xpath="/packet/review/checklist"',
        '<p>Content-control checklist for reviewer handoff.</p>',
        '<img src="word/media/hero.png" alt="Source hero alt" title="Source hero"/>',
        '<td colspan="2" rowspan="2"><p>Review scope</p></td><td><p>Status</p></td>',
        '<td><p>Owner</p></td><td colspan="2"><p>Migration desk</p></td>',
        'DOCX footnote import note.',
        'DOCX endnote import note.',
        'DOCX reviewer comment import note.',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            throw new RuntimeException('DOCX body handoff self-test missing: ' . $needle);
        }
    }
    if (str_contains($blocks, '_GoBack')) {
        throw new RuntimeException('DOCX body handoff self-test rendered dummy Word return bookmark');
    }

    echo "docx body handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
