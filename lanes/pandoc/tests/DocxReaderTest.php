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
    'maps DOCX table gridSpan and vMerge cells into table span attributes' => static function (TestRunner $t) use ($buildTableSpanPackage): void {
        $document = (new DocxReader())->readDocument($buildTableSpanPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $table = $document->children[0];
        $t->same('table', $table->type);
        $body = $table->children[0];
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

        $t->same(' commented source ', $paragraph->children[2]->attr('text'));
        $comment = $paragraph->children[3];
        $t->same('note', $comment->type);
        $t->same('9', $comment->attr('id'));
        $t->same('comment', $comment->attr('sourceType'));
        $t->same('Migration Reviewer', $comment->attr('author'));
        $t->same('MR', $comment->attr('initials'));
        $t->same('2026-06-04T09:55:00Z', $comment->attr('date'));
        $t->same('Comment source audit.', $comment->children[0]->children[0]->attr('text'));
        $t->same('Keep reviewer context with the import.', $comment->children[1]->children[0]->attr('text'));

        $t->contains('Audit trail [^1] commented source [^2]', $markdown);
        $t->contains('[^1]: Endnote source audit.', $markdown);
        $t->contains('| Review table | kept in endnote |', $markdown);
        $t->contains('[^2]: Comment source audit.', $markdown);
        $t->contains('    Keep reviewer context with the import.', $markdown);

        $t->contains('<p>Audit trail <sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup> commented source <sup id="fnref-2"><a href="#fn-2" role="doc-noteref">2</a></sup></p>', $blocks);
        $t->contains('<li id="fn-1"><p>Endnote source audit.</p><table><tbody><tr><td><p>Review table</p></td><td><p>kept in endnote</p></td></tr></tbody></table> <a href="#fnref-1" aria-label="Back to content">Back</a></li>', $blocks);
        $t->contains('<li id="fn-2"><p>Comment source audit.</p><p>Keep reviewer context with the import.</p> <a href="#fnref-2" aria-label="Back to content">Back</a></li>', $blocks);
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
