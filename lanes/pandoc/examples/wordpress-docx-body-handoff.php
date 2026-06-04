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
      <w:r><w:t xml:space="preserve">Import reviewer keeps </w:t></w:r>
      <w:hyperlink r:id="rIdSource"><w:r><w:t>the source link</w:t></w:r></w:hyperlink>
      <w:r><w:t xml:space="preserve"> visible.</w:t></w:r>
      <w:del w:id="7" w:author="Source Editor" w:date="2026-06-04T17:45:00Z">
        <w:r><w:delText>Old reviewer draft.</w:delText></w:r>
      </w:del>
      <w:ins w:id="8" w:author="Migration Editor" w:date="2026-06-04T17:50:00Z">
        <w:r><w:t xml:space="preserve"> Approved tracked wording.</w:t></w:r>
      </w:ins>
      <w:r><w:footnoteReference w:id="2"/></w:r>
      <w:r><w:t xml:space="preserve"> Also keep endnote context</w:t></w:r>
      <w:r><w:endnoteReference w:id="5"/></w:r>
      <w:commentRangeStart w:id="9"/>
      <w:r><w:t xml:space="preserve"> and reviewer comment</w:t></w:r>
      <w:commentRangeEnd w:id="9"/>
      <w:r><w:commentReference w:id="9"/></w:r>
    </w:p>
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
  </w:body>
</w:document>
XML],
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
    if (($summary['importReport']['revisions']['insertionCount'] ?? 0) !== 1 || ($summary['importReport']['revisions']['deletionCount'] ?? 0) !== 1) {
        throw new RuntimeException('DOCX body handoff self-test missing tracked-change report');
    }
    if (str_contains($blocks, 'Old reviewer draft.')) {
        throw new RuntimeException('DOCX body handoff self-test rendered deleted tracked-change text');
    }

    foreach ([
        '<h1 id="docx-source-packet">DOCX source packet</h1>',
        '<h2 id="reviewer-checklist">Reviewer checklist</h2>',
        '<ul><li>Match media IDs</li><li>Preserve alt text</li></ul>',
        '<ol start="3" type="a"><li>Confirm source URL</li><li>Publish packet</li></ol>',
        '<a href="https://example.test/source-packet?post=42">the source link</a>',
        '<span class="docx-insertion" data-docx-change="insertion" data-docx-change-id="8" data-docx-author="Migration Editor" data-docx-date="2026-06-04T17:50:00Z"> Approved tracked wording.</span>',
        '<span class="docx-comment-range" data-docx-comment-id="9" data-docx-comment-author="Migration Reviewer" data-docx-comment-initials="MR" data-docx-comment-date="2026-06-04T09:55:00Z"> and reviewer comment</span>',
        '<span class="math inline">\(x_{i} + \frac{1}{\sqrt{n}}\)</span>',
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

    echo "docx body handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
