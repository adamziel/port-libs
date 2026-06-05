<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="Heading_20_1" style:family="paragraph" style:display-name="Heading 1" style:default-outline-level="1"/>
    <style:style style:name="BodyText" style:family="paragraph" style:display-name="Body Text"/>
    <style:style style:name="BaseStrong" style:family="text">
      <style:text-properties fo:font-weight="bold"/>
    </style:style>
    <style:style style:name="StrongEmphasis" style:family="text" style:parent-style-name="BaseStrong">
      <style:text-properties fo:font-style="italic" style:text-underline-style="solid"/>
    </style:style>
    <style:style style:name="NarrowColumn" style:family="table-column">
      <style:table-column-properties style:column-width="2cm"/>
    </style:style>
    <style:style style:name="WideColumn" style:family="table-column">
      <style:table-column-properties style:column-width="4cm"/>
    </style:style>
    <text:list-style style:name="NumberedReview">
      <text:list-level-style-number text:level="1" style:num-format="a" text:start-value="3"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:automatic-styles>
    <style:style style:name="AutoHeading" style:family="paragraph" style:parent-style-name="Heading_20_1" style:display-name="Auto Review Heading"/>
    <text:list-style style:name="BulletReview">
      <text:list-level-style-bullet text:level="1" text:bullet-char="*"/>
    </text:list-style>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <text:h text:outline-level="1" text:style-name="AutoHeading">Imported ODT Packet</text:h>
      <text:p text:style-name="BodyText">Reviewer <text:span text:style-name="StrongEmphasis">summary</text:span><text:s text:c="2"/>keeps <text:a xlink:href="https://example.test/source.odt">source link</text:a><text:line-break/>next line<office:annotation><dc:creator>Migration Desk</dc:creator><dc:date>2026-06-04T22:10:00Z</dc:date><text:p>Annotation for reviewers.</text:p></office:annotation></text:p>
      <text:list text:style-name="NumberedReview">
        <text:list-item><text:p>Legal review</text:p></text:list-item>
        <text:list-item><text:p>Publish packet</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="BulletReview">
        <text:list-item><text:p>Confirm media map</text:p></text:list-item>
      </text:list>
      <text:section text:name="Sidebar">
        <text:p>Section packet note.</text:p>
      </text:section>
      <draw:frame draw:name="Hero">
        <draw:image xlink:href="Pictures/hero.png">
          <svg:title>Hero title</svg:title>
          <svg:desc>Hero alt text</svg:desc>
        </draw:image>
      </draw:frame>
      <draw:frame draw:name="Pull quote">
        <draw:text-box>
          <text:p>Text box source note.</text:p>
        </draw:text-box>
      </draw:frame>
      <table:table table:name="Audit">
        <table:table-column table:style-name="NarrowColumn"/>
        <table:table-column table:style-name="WideColumn"/>
        <table:table-header-rows>
          <table:table-row>
            <table:table-cell><text:p>Status</text:p></table:table-cell>
            <table:table-cell><text:p>Owner</text:p></table:table-cell>
          </table:table-row>
        </table:table-header-rows>
        <table:table-row>
          <table:table-cell table:number-columns-spanned="2"><text:p>Ready for review</text:p></table:table-cell>
          <table:covered-table-cell/>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <dc:title>ODT Import Packet</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <dc:description>Source ODT for WordPress import review</dc:description>
    <dc:language>en</dc:language>
    <dc:date>2026-06-04T22:00:00Z</dc:date>
    <meta:keyword>migration</meta:keyword>
    <meta:keyword>odt</meta:keyword>
    <meta:initial-creator>Data Liberation</meta:initial-creator>
    <meta:creation-date>2026-06-04T21:30:00Z</meta:creation-date>
    <meta:editing-cycles>7</meta:editing-cycles>
    <meta:document-statistic meta:page-count="2" meta:word-count="128" meta:paragraph-count="9" meta:image-count="1"/>
    <meta:user-defined meta:name="wp-source-id">packet-42</meta:user-defined>
  </office:meta>
</office:document-meta>
XML;

$buildOdtPackage = static function (
    ?string $overrideContentXml = null,
    ?string $overrideManifestXml = null,
    ?string $overrideStylesXml = null,
    ?string $overrideMetaXml = null,
    array $extraParts = []
) use ($contentXml, $manifestXml, $stylesXml, $metaXml): ZipPackage {
    return ZipPackage::fromParts(array_merge([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $overrideManifestXml ?? $manifestXml],
        ['name' => 'content.xml', 'data' => $overrideContentXml ?? $contentXml],
        ['name' => 'styles.xml', 'data' => $overrideStylesXml ?? $stylesXml],
        ['name' => 'meta.xml', 'data' => $overrideMetaXml ?? $metaXml],
        ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ], $extraParts));
};

return [
    'reads ODT manifest metadata styles and package media' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $result = (new OdfReader())->readPackage($buildOdtPackage());
        $document = $result['document'];

        $t->same('odt', $document->attr('source'));
        $t->same('ODT Import Packet', $document->attr('title'));
        $t->same('ODT Import Packet', $result['metadata']['title']);
        $t->same('Migration Desk', $result['metadata']['creator']);
        $t->same('Source ODT for WordPress import review', $result['metadata']['description']);
        $t->same('en', $result['metadata']['language']);
        $t->same('2026-06-04T22:00:00Z', $result['metadata']['date']);
        $t->same(['migration', 'odt'], $result['metadata']['keywords']);
        $t->same('Data Liberation', $result['metadata']['initialCreator']);
        $t->same('2026-06-04T21:30:00Z', $result['metadata']['created']);
        $t->same('7', $result['metadata']['editingCycles']);
        $t->same(2, $result['metadata']['statistics']['pageCount']);
        $t->same(128, $result['metadata']['statistics']['wordCount']);
        $t->same('packet-42', $result['metadata']['userDefined']['wp-source-id']);

        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }
        $t->same(OdfReader::MIMETYPE, $manifestByPath['/']['mediaType']);
        $t->same('1.3', $manifestByPath['/']['version']);
        $t->same(true, $manifestByPath['content.xml']['exists']);
        $t->same('text/xml', $manifestByPath['styles.xml']['mediaType']);
        $t->same(true, $manifestByPath['Pictures/hero.png']['exists']);
        $t->same('image/png', $manifestByPath['Pictures/hero.png']['mediaType']);
        $t->same(7, $manifestByPath['Pictures/hero.png']['byteLength']);

        $t->same(1, count($result['media']));
        $t->same('Pictures/hero.png', $result['media'][0]['part']);
        $t->same('image/png', $result['media'][0]['mediaType']);
        $t->same(true, $result['media'][0]['exists']);
        $t->same(7, $result['media'][0]['byteLength']);

        $t->same('BaseStrong', $result['styles']['StrongEmphasis']['parentName']);
        $t->same(true, $document->children[1]->children[1]->children[0]->children[0]->children[0]->attr('styleName') === 'StrongEmphasis');
        $t->same(2, count($result['listStyles']));
        $t->same(5, $result['importReport']['manifest']['count']);
        $t->same(0, count($result['importReport']['manifest']['missingItems']));
    },
    'maps ODT content XML blocks to the shared Pandoc-like AST' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $result = (new OdfReader())->readPackage($buildOdtPackage());
        $document = $result['document'];
        $blocks = $document->children;

        $t->same(8, count($blocks));
        $t->same('heading', $blocks[0]->type);
        $t->same(1, $blocks[0]->attr('level'));
        $t->same('AutoHeading', $blocks[0]->attr('styleName'));
        $t->same('Imported ODT Packet', $blocks[0]->children[0]->attr('text'));

        $paragraph = $blocks[1];
        $t->same('paragraph', $paragraph->type);
        $t->same('BodyText', $paragraph->attr('styleName'));
        $t->same('underline', $paragraph->children[1]->type);
        $t->same('emph', $paragraph->children[1]->children[0]->type);
        $t->same('strong', $paragraph->children[1]->children[0]->children[0]->type);
        $t->same('summary', $paragraph->children[1]->children[0]->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Reviewer summary  keeps source link' . "\n" . 'next line', $paragraph->attr('text'));
        $t->same('link', $paragraph->children[3]->type);
        $t->same('https://example.test/source.odt', $paragraph->children[3]->attr('url'));
        $t->same('linebreak', $paragraph->children[4]->type);
        $t->same('note', $paragraph->children[6]->type);
        $t->same('Migration Desk', $paragraph->children[6]->attr('author'));
        $t->same('2026-06-04T22:10:00Z', $paragraph->children[6]->attr('date'));
        $t->same('Annotation for reviewers.', $paragraph->children[6]->children[0]->attr('text'));

        $ordered = $blocks[2];
        $t->same('ordered_list', $ordered->type);
        $t->same('lower_alpha', $ordered->attr('style'));
        $t->same(3, $ordered->attr('start'));
        $t->same('Legal review', $ordered->children[0]->children[0]->attr('text'));

        $bullet = $blocks[3];
        $t->same('bullet_list', $bullet->type);
        $t->same('*', $bullet->attr('format'));
        $t->same('Confirm media map', $bullet->children[0]->children[0]->attr('text'));

        $section = $blocks[4];
        $t->same('div', $section->type);
        $t->same('sidebar', $section->attr('id'));
        $t->same('Sidebar', $section->attr('attributes')['data-odf-section-name']);
        $t->same('Section packet note.', $section->children[0]->attr('text'));

        $figure = $blocks[5];
        $t->same('figure', $figure->type);
        $t->same('image', $figure->children[0]->type);
        $t->same('Pictures/hero.png', $figure->children[0]->attr('url'));
        $t->same('Hero alt text', $figure->children[0]->attr('alt'));
        $t->same(7, $figure->children[0]->attr('bytes'));

        $textBox = $blocks[6];
        $t->same('div', $textBox->type);
        $t->same('Pull quote', $textBox->attr('attributes')['data-odf-frame-name']);
        $t->same('Text box source note.', $textBox->children[0]->attr('text'));

        $table = $blocks[7];
        $t->same('table', $table->type);
        $widths = $table->attr('widths');
        $t->true(is_array($widths));
        $t->true(abs($widths[0] - (1 / 3)) < 0.000001);
        $t->true(abs($widths[1] - (2 / 3)) < 0.000001);
        $t->same('table_head', $table->children[0]->type);
        $t->same('Status', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('table_body', $table->children[1]->type);
        $t->same(2, $table->children[1]->children[0]->children[0]->attr('colspan'));
        $t->same('Ready for review', $table->children[1]->children[0]->children[0]->attr('text'));
    },
    'maps ODT footnotes endnotes and bookmark references into reviewable AST nodes' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithNotesAndBookmarks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Bookmark <text:bookmark-start text:name="Review Anchor"/>target<text:bookmark-end text:name="Review Anchor"/> and <text:bookmark-ref text:ref-name="Review Anchor" text:reference-format="text">jump back</text:bookmark-ref>.</text:p>
      <text:p>Footnote<text:note text:id="ftn1" text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>ODF footnote body.</text:p></text:note-body></text:note> Endnote<text:note text:id="edn1" text:note-class="endnote"><text:note-citation>i</text:note-citation><text:note-body><text:p>ODF endnote body with <text:a xlink:href="https://example.test/review">review link</text:a>.</text:p></text:note-body></text:note></text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithNotesAndBookmarks));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $bookmarkParagraph = $blocks[0];
        $bookmark = $bookmarkParagraph->children[1];
        $reference = $bookmarkParagraph->children[3];
        $t->same('span', $bookmark->type);
        $t->same('review-anchor', $bookmark->attr('id'));
        $t->same(['anchor', 'odf-bookmark'], $bookmark->attr('classes'));
        $t->same('Review Anchor', $bookmark->attr('attributes')['data-odf-bookmark-name']);
        $t->same('link', $reference->type);
        $t->same('#review-anchor', $reference->attr('url'));
        $t->same(['odf-bookmark-ref'], $reference->attr('classes'));
        $t->same('Review Anchor', $reference->attr('attributes')['data-odf-ref-name']);
        $t->same('text', $reference->attr('attributes')['data-odf-reference-format']);
        $t->same('jump back', $reference->children[0]->attr('text'));

        $noteParagraph = $blocks[1];
        $footnote = $noteParagraph->children[1];
        $endnote = $noteParagraph->children[3];
        $t->same('note', $footnote->type);
        $t->same('footnote', $footnote->attr('noteClass'));
        $t->same('ftn1', $footnote->attr('id'));
        $t->same('1', $footnote->attr('citation'));
        $t->same('ODF footnote body.', $footnote->children[0]->attr('text'));
        $t->same('note', $endnote->type);
        $t->same('endnote', $endnote->attr('noteClass'));
        $t->same('edn1', $endnote->attr('id'));
        $t->same('i', $endnote->attr('citation'));
        $t->same('ODF endnote body with review link.', $endnote->children[0]->attr('text'));
        $t->same('link', $endnote->children[0]->children[1]->type);
        $t->same('https://example.test/review', $endnote->children[0]->children[1]->attr('url'));

        $t->same(2, $result['importReport']['content']['noteCount']);
        $t->same(1, $result['importReport']['content']['bookmarkCount']);
        $t->same(1, $result['importReport']['content']['bookmarkReferenceCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[]{#review-anchor .anchor .odf-bookmark data-odf-bookmark-name="Review Anchor"}', $markdown);
        $t->contains('[jump back](#review-anchor){.odf-bookmark-ref data-odf-ref-name="Review Anchor" data-odf-reference-format="text"}', $markdown);
        $t->contains('[^1]: ODF footnote body.', $markdown);
        $t->contains('[^2]: ODF endnote body with [review link](https://example.test/review).', $markdown);
        $t->contains('<span id="review-anchor" class="anchor odf-bookmark" data-odf-bookmark-name="Review Anchor"></span>', $blocksHtml);
        $t->contains('<a href="#review-anchor" class="odf-bookmark-ref" data-odf-ref-name="Review Anchor" data-odf-reference-format="text">jump back</a>', $blocksHtml);
        $t->contains('<li id="fn-1"><p>ODF footnote body.</p>', $blocksHtml);
        $t->contains('<li id="fn-2"><p>ODF endnote body with <a href="https://example.test/review">review link</a>.</p>', $blocksHtml);
    },
    'maps ODT tracked changes into review spans and import report metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTrackedChanges = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:body>
    <office:text>
      <text:tracked-changes>
        <text:changed-region text:id="ct-ins">
          <text:insertion>
            <office:change-info>
              <dc:creator>Editor A</dc:creator>
              <dc:date>2026-06-05T00:10:00Z</dc:date>
              <text:p>Inserted during source review.</text:p>
            </office:change-info>
          </text:insertion>
        </text:changed-region>
        <text:changed-region text:id="ct-del">
          <text:deletion>
            <office:change-info>
              <dc:creator>Editor B</dc:creator>
              <dc:date>2026-06-05T00:12:00Z</dc:date>
            </office:change-info>
            <text:p>legacy deleted claim</text:p>
          </text:deletion>
        </text:changed-region>
        <text:changed-region text:id="ct-fmt">
          <text:format-change>
            <office:change-info>
              <dc:creator>Editor C</dc:creator>
              <dc:date>2026-06-05T00:14:00Z</dc:date>
            </office:change-info>
          </text:format-change>
        </text:changed-region>
      </text:tracked-changes>
      <text:p>Stable <text:change-start text:change-id="ct-ins"/>inserted review text<text:change-end text:change-id="ct-ins"/> and <text:change text:change-id="ct-del"/> plus <text:change-start text:change-id="ct-fmt"/>formatted cue<text:change-end text:change-id="ct-fmt"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTrackedChanges));
        $paragraph = $result['document']->children[0];
        $changesById = [];
        foreach ($result['trackedChanges'] as $change) {
            $changesById[$change['id']] = $change;
        }

        $t->same('paragraph', $paragraph->type);
        $t->same('Stable inserted review text and legacy deleted claim plus formatted cue.', $paragraph->attr('text'));
        $t->same(3, count($result['trackedChanges']));
        $t->same('insertion', $changesById['ct-ins']['type']);
        $t->same('Editor A', $changesById['ct-ins']['creator']);
        $t->same('2026-06-05T00:10:00Z', $changesById['ct-ins']['date']);
        $t->same(['Inserted during source review.'], $changesById['ct-ins']['comments']);
        $t->same('deletion', $changesById['ct-del']['type']);
        $t->same('legacy deleted claim', $changesById['ct-del']['text']);
        $t->same('format-change', $changesById['ct-fmt']['type']);

        $insertion = $paragraph->children[1];
        $deletion = $paragraph->children[3];
        $formatChange = $paragraph->children[5];
        $t->same('span', $insertion->type);
        $t->same(['odf-change', 'odf-insertion'], $insertion->attr('classes'));
        $t->same('ct-ins', $insertion->attr('attributes')['data-odf-change-id']);
        $t->same('insertion', $insertion->attr('attributes')['data-odf-change-type']);
        $t->same('Editor A', $insertion->attr('attributes')['data-odf-change-creator']);
        $t->same('inserted review text', $insertion->children[0]->attr('text'));
        $t->same(['odf-change', 'odf-deletion'], $deletion->attr('classes'));
        $t->same('legacy deleted claim', $deletion->children[0]->attr('text'));
        $t->same(['odf-change', 'odf-format-change'], $formatChange->attr('classes'));
        $t->same('formatted cue', $formatChange->children[0]->attr('text'));

        $t->same(3, $result['importReport']['trackedChanges']['count']);
        $t->same(3, $result['importReport']['content']['trackedChangeCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[inserted review text]{.odf-change .odf-insertion data-odf-change-id="ct-ins" data-odf-change-type="insertion" data-odf-change-creator="Editor A" data-odf-change-date="2026-06-05T00:10:00Z"}', $markdown);
        $t->contains('[legacy deleted claim]{.odf-change .odf-deletion data-odf-change-id="ct-del" data-odf-change-type="deletion" data-odf-change-creator="Editor B" data-odf-change-date="2026-06-05T00:12:00Z"}', $markdown);
        $t->contains('<span class="odf-change odf-insertion" data-odf-change-id="ct-ins" data-odf-change-type="insertion" data-odf-change-creator="Editor A" data-odf-change-date="2026-06-05T00:10:00Z">inserted review text</span>', $blocksHtml);
        $t->contains('<span class="odf-change odf-deletion" data-odf-change-id="ct-del" data-odf-change-type="deletion" data-odf-change-creator="Editor B" data-odf-change-date="2026-06-05T00:12:00Z">legacy deleted claim</span>', $blocksHtml);
    },
    'maps ODT embedded MathML objects into display math handoff nodes' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $contentWithMathObjects = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Inline formula <draw:frame draw:name="Inline formula"><draw:object xlink:href="./Object 1"/></draw:frame> preserved.</text:p>
      <draw:frame draw:name="Display formula"><draw:object xlink:href="Object 2"/></draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $mathObjectOne = <<<'XML'
<office:document
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
  <office:body>
    <office:math>
      <math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">
        <semantics>
          <mrow><mi>x</mi><mo>=</mo><mn>1</mn></mrow>
          <annotation encoding="application/x-tex">x=1</annotation>
        </semantics>
      </math>
    </office:math>
  </office:body>
</office:document>
XML;
        $mathObjectTwo = <<<'XML'
<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">
  <mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow>
</math>
XML;
        $manifestWithMathObjects = str_replace(
            '</manifest:manifest>',
            '<manifest:file-entry manifest:full-path="Object 1/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>'
            . '<manifest:file-entry manifest:full-path="Object 1/content.xml" manifest:media-type="text/xml"/>'
            . '<manifest:file-entry manifest:full-path="Object 2/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>'
            . '<manifest:file-entry manifest:full-path="Object 2/content.xml" manifest:media-type="text/xml"/>'
            . '</manifest:manifest>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithMathObjects,
            $manifestWithMathObjects,
            null,
            null,
            [
                ['name' => 'Object 1/content.xml', 'data' => $mathObjectOne],
                ['name' => 'Object 2/content.xml', 'data' => $mathObjectTwo],
            ]
        ));

        $blocks = $result['document']->children;
        $t->same(2, count($blocks));
        if (count($blocks) !== 2) {
            return;
        }
        $paragraph = $blocks[0];
        $inlineMath = $paragraph->children[1];
        $displayParagraph = $blocks[1];
        $displayMath = $displayParagraph->children[0];

        $t->same('Inline formula x=1 preserved.', $paragraph->attr('text'));
        $t->same('math', $inlineMath->type);
        $t->same(true, $inlineMath->attr('display'));
        $t->same('odt-mathml', $inlineMath->attr('sourceFormat'));
        $t->same('Object 1', $inlineMath->attr('objectPath'));
        $t->same('Object 1/content.xml', $inlineMath->attr('sourcePart'));
        $t->same('x=1', $inlineMath->attr('text'));
        $t->contains('<annotation encoding="application/x-tex">x=1</annotation>', $inlineMath->attr('mathml'));
        $t->same('paragraph', $displayParagraph->type);
        $t->same('a+b', $displayParagraph->attr('text'));
        $t->same('math', $displayMath->type);
        $t->same('Object 2/content.xml', $displayMath->attr('sourcePart'));
        $t->same('a+b', $displayMath->attr('text'));
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $displayMath->attr('mathml'));
        $t->same(2, $result['importReport']['content']['mathCount']);
        $t->same(1, count($result['media']));
        $t->same('Pictures/hero.png', $result['media'][0]['part']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Inline formula $$x=1$$ preserved.', $markdown);
        $t->contains('$$a+b$$', $markdown);
        $t->contains('<span class="math display"><math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">', $blocksHtml);
        $t->contains('<annotation encoding="application/x-tex">x=1</annotation>', $blocksHtml);
        $t->contains('<span class="math display"><math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $blocksHtml);
    },
    'renders ODT handoff nodes through Markdown and WordPress writers' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $document = (new OdfReader())->readDocument($buildOdtPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('# Imported ODT Packet', $markdown);
        $t->contains('[source link](https://example.test/source.odt)', $markdown);
        $t->contains('[summary]{data-odf-style-name="StrongEmphasis"}', $markdown);
        $t->contains('[^1]', $markdown);
        $t->contains('c.  Legal review', $markdown);
        $t->contains('![Hero alt text](Pictures/hero.png "Hero title")', $markdown);
        $t->contains('| Status', $markdown);
        $t->contains('Ready for review', $markdown);

        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<span data-odf-style-name="StrongEmphasis">summary</span>', $blocks);
        $t->contains('<a href="https://example.test/source.odt">source link</a>', $blocks);
        $t->contains('<section class="footnotes" role="doc-endnotes">', $blocks);
        $t->contains('<ol start="3" type="a">', $blocks);
        $t->contains('<img src="Pictures/hero.png" alt="Hero alt text" title="Hero title"/>', $blocks);
        $t->contains('<th><p>Status</p></th>', $blocks);
        $t->contains('<td colspan="2"><p>Ready for review</p></td>', $blocks);
    },
    'reports missing ODT manifest media without dropping content blocks' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $manifestWithMissing = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/><manifest:file-entry manifest:full-path="Pictures/missing.jpg" manifest:media-type="image/jpeg"/>',
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithMissing));
        $missing = $result['importReport']['manifest']['missingItems'];
        $mediaByPart = [];
        foreach ($result['media'] as $media) {
            $mediaByPart[$media['part']] = $media;
        }

        $t->same(1, count($missing));
        $t->same('Pictures/missing.jpg', $missing[0]['part']);
        $t->same(false, $mediaByPart['Pictures/missing.jpg']['exists']);
        $t->same(null, $mediaByPart['Pictures/missing.jpg']['byteLength']);
        $t->same(8, count($result['document']->children));
        $t->same('Imported ODT Packet', $result['document']->children[0]->children[0]->attr('text'));
    },
    'reports encrypted ODT manifest resources without exposing media bytes' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml): void {
        $encryptedEntry = <<<'XML'
<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="checksum-base64">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="iv-base64"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:iteration-count="1024" manifest:salt="salt-base64"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
    </manifest:encryption-data>
  </manifest:file-entry>
XML;
        $manifestWithEncryptedMedia = str_replace(
            '<manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>',
            $encryptedEntry,
            $manifestXml
        );

        $result = (new OdfReader())->readPackage($buildOdtPackage(null, $manifestWithEncryptedMedia));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }

        $heroManifest = $manifestByPath['Pictures/hero.png'];
        $t->same(true, $heroManifest['encrypted']);
        $t->same(false, $heroManifest['canExposeBytes']);
        $t->same(2048, $heroManifest['declaredSize']);
        $t->same('SHA1/1K', $heroManifest['encryption']['checksumType']);
        $t->same('checksum-base64', $heroManifest['encryption']['checksum']);
        $t->same('Blowfish CFB', $heroManifest['encryption']['algorithm']['name']);
        $t->same('iv-base64', $heroManifest['encryption']['algorithm']['initialisationVector']);
        $t->same('PBKDF2', $heroManifest['encryption']['keyDerivation']['name']);
        $t->same(1024, $heroManifest['encryption']['keyDerivation']['iterationCount']);
        $t->same('salt-base64', $heroManifest['encryption']['keyDerivation']['salt']);
        $t->same('SHA1', $heroManifest['encryption']['startKeyGeneration']['name']);
        $t->same(20, $heroManifest['encryption']['startKeyGeneration']['keySize']);

        $media = $result['media'][0];
        $t->same('Pictures/hero.png', $media['part']);
        $t->same(true, $media['encrypted']);
        $t->same(false, $media['canExposeBytes']);
        $t->same(null, $media['byteLength']);
        $t->same(7, $media['storedByteLength']);
        $t->same('Blowfish CFB', $media['encryption']['algorithm']['name']);

        $image = $result['document']->children[5]->children[0];
        $t->same(true, $image->attr('encrypted'));
        $t->same(false, $image->attr('canExposeBytes'));
        $t->same('not-exposed', $image->attr('bytes', 'not-exposed'));
        $t->same('Blowfish CFB', $image->attr('encryption')['algorithm']['name']);

        $t->same(1, $result['importReport']['manifest']['encryptedCount']);
        $t->same('Pictures/hero.png', $result['importReport']['manifest']['encryptedItems'][0]['part']);
        $t->same(1, $result['importReport']['encryption']['count']);
        $t->same(['Pictures/hero.png'], $result['importReport']['encryption']['encryptedParts']);
        $t->same(0, count($result['importReport']['manifest']['missingItems']));

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<img src="Pictures/hero.png" alt="Hero alt text" title="Hero title"/>', $blocks);
    },
    'rejects malformed ODT packages before conversion handoff' => static function (TestRunner $t) use ($buildOdtPackage, $manifestXml, $contentXml): void {
        $reader = new OdfReader();

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
        ])));

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
        ])));

        $wrongContentRoot = '<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>';
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage($buildOdtPackage($wrongContentRoot)));

        $unsafeManifest = str_replace('Pictures/hero.png', 'Pictures/../secret.png', $manifestXml);
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage($buildOdtPackage(null, $unsafeManifest)));

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
        ])));
    },
];
