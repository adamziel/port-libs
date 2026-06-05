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
    'maps ODT page layouts and master pages into import report metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithPageLayout = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:automatic-styles>
    <style:page-layout style:name="pmReview" style:page-usage="all">
      <style:page-layout-properties
        fo:page-width="8.5in"
        fo:page-height="11in"
        fo:margin-top="1in"
        fo:margin-right="0.75in"
        fo:margin-bottom="1in"
        fo:margin-left="0.75in"
        style:print-orientation="portrait"
        style:writing-mode="lr-tb"/>
    </style:page-layout>
  </office:automatic-styles>
  <office:styles>
    <style:style style:name="AppendixBreak" style:family="paragraph" style:master-page-name="AppendixPage">
      <style:paragraph-properties fo:break-before="page"/>
    </style:style>
  </office:styles>
  <office:master-styles>
    <style:master-page style:name="ReviewPage" style:display-name="Review Page" style:page-layout-name="pmReview" style:next-style-name="AppendixPage">
      <style:header><text:p>Confidential import packet</text:p></style:header>
      <style:footer><text:p>Page <text:page-number>1</text:page-number></text:p></style:footer>
    </style:master-page>
    <style:master-page style:name="AppendixPage" style:page-layout-name="pmReview"/>
  </office:master-styles>
</office:document-styles>
XML;
        $contentWithMasterPageStyle = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p text:style-name="AppendixBreak">Appendix starts here.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithMasterPageStyle, null, $stylesWithPageLayout));
        $document = $result['document'];
        $pageLayout = $result['pageLayouts']['pmReview'];
        $reviewPage = $result['masterPages']['ReviewPage'];
        $appendixStyle = $result['styles']['AppendixBreak'];
        $paragraph = $document->children[0];

        $t->same(1, $document->attr('pageLayouts')['count']);
        $t->same(2, $document->attr('masterPages')['count']);
        $t->same('pmReview', $pageLayout['name']);
        $t->same('all', $pageLayout['pageUsage']);
        $t->same('8.5in', $pageLayout['properties']['pageWidth']);
        $t->same('11in', $pageLayout['properties']['pageHeight']);
        $t->same('portrait', $pageLayout['properties']['printOrientation']);
        $t->same('lr-tb', $pageLayout['properties']['writingMode']);
        $t->same('1in', $pageLayout['properties']['marginTop']);
        $t->same('0.75in', $pageLayout['properties']['marginRight']);
        $t->true(abs($pageLayout['properties']['pageWidthPoints'] - 612.0) < 0.000001);
        $t->true(abs($pageLayout['properties']['marginLeftPoints'] - 54.0) < 0.000001);

        $t->same('ReviewPage', $reviewPage['name']);
        $t->same('Review Page', $reviewPage['displayName']);
        $t->same('pmReview', $reviewPage['pageLayoutName']);
        $t->same('AppendixPage', $reviewPage['nextStyleName']);
        $t->same(['Confidential import packet'], $reviewPage['headerText']);
        $t->same(['Page 1'], $reviewPage['footerText']);
        $t->same('AppendixPage', $result['masterPages']['AppendixPage']['name']);

        $t->same('AppendixPage', $appendixStyle['masterPageName']);
        $t->same('page', $appendixStyle['paragraphProperties']['breakBefore']);
        $t->same('paragraph', $paragraph->type);
        $t->same('AppendixBreak', $paragraph->attr('styleName'));
        $t->same('AppendixPage', $paragraph->attr('style')['masterPageName']);
        $t->same('page', $paragraph->attr('style')['paragraphProperties']['breakBefore']);
        $t->same('Appendix starts here.', $paragraph->attr('text'));

        $t->same(1, $result['importReport']['pageLayouts']['count']);
        $t->same(2, $result['importReport']['masterPages']['count']);
        $t->same('Confidential import packet', $result['importReport']['masterPages']['items'][0]['headerText'][0]);
        $t->same(1, $result['importReport']['styles']['pageLayoutCount']);
        $t->same(2, $result['importReport']['styles']['masterPageCount']);
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
    'continues ODT ordered list numbering across sibling lists by level' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithContinuationLists = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <text:list-style style:name="ContinuationSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="2"/>
      <text:list-level-style-number text:level="2" style:num-format="a" text:start-value="4"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithContinuationLists = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="ContinuationSteps">
        <text:list-item>
          <text:p>First review item</text:p>
          <text:list text:style-name="ContinuationSteps">
            <text:list-item><text:p>Nested legal note</text:p></text:list-item>
          </text:list>
        </text:list-item>
        <text:list-item><text:p>Second review item</text:p></text:list-item>
      </text:list>
      <text:p>Interruption paragraph.</text:p>
      <text:list text:style-name="ContinuationSteps" text:continue-numbering="true">
        <text:list-item><text:p>Third review item</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="ContinuationSteps">
        <text:list-item><text:p>Reset review item</text:p></text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithContinuationLists, null, $stylesWithContinuationLists));
        $blocks = $result['document']->children;

        $t->same(4, count($blocks));
        $firstList = $blocks[0];
        $paragraph = $blocks[1];
        $continuedList = $blocks[2];
        $resetList = $blocks[3];
        $nestedList = $firstList->children[0]->children[1];

        $t->same('ordered_list', $firstList->type);
        $t->same(2, $firstList->attr('start'));
        $t->same('decimal', $firstList->attr('style'));
        $t->same(1, $firstList->attr('listLevel'));
        $t->same('First review item', $firstList->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $nestedList->type);
        $t->same(4, $nestedList->attr('start'));
        $t->same('lower_alpha', $nestedList->attr('style'));
        $t->same(2, $nestedList->attr('listLevel'));
        $t->same('Nested legal note', $nestedList->children[0]->children[0]->attr('text'));
        $t->same('Interruption paragraph.', $paragraph->attr('text'));
        $t->same('ordered_list', $continuedList->type);
        $t->same(true, $continuedList->attr('continued'));
        $t->same(4, $continuedList->attr('start'));
        $t->same('Third review item', $continuedList->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $resetList->type);
        $t->same(2, $resetList->attr('start'));
        $t->same('Reset review item', $resetList->children[0]->children[0]->attr('text'));
        $t->same(1, $result['importReport']['content']['continuedListCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('2.  First review item', $markdown);
        $t->contains('  d.  Nested legal note', $markdown);
        $t->contains('4.  Third review item', $markdown);
        $t->contains('2.  Reset review item', $markdown);
        $t->contains('<ol start="2">', $blocksHtml);
        $t->contains('<ol start="4" type="a">', $blocksHtml);
        $t->contains('<ol start="4">', $blocksHtml);
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
    'maps ODT reference marks and references into internal review links' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithReferenceMarks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Range <text:reference-mark-start text:name="Source Claim"/>source claim<text:reference-mark-end text:name="Source Claim"/> and point <text:reference-mark text:name="Point Review"/>marker.</text:p>
      <text:p>See <text:reference-ref text:ref-name="Source Claim" text:reference-format="text">source claim</text:reference-ref> and <text:reference-ref text:ref-name="Point Review" text:reference-format="page">point marker</text:reference-ref>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithReferenceMarks));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $sourceAnchor = $blocks[0]->children[1];
        $pointAnchor = $blocks[0]->children[3];
        $sourceReference = $blocks[1]->children[1];
        $pointReference = $blocks[1]->children[3];

        $t->same('span', $sourceAnchor->type);
        $t->same('source-claim', $sourceAnchor->attr('id'));
        $t->same(['anchor', 'odf-reference-mark'], $sourceAnchor->attr('classes'));
        $t->same('Source Claim', $sourceAnchor->attr('attributes')['data-odf-reference-name']);
        $t->same('source claim and point ', $blocks[0]->children[2]->attr('text'));
        $t->same('span', $pointAnchor->type);
        $t->same('point-review', $pointAnchor->attr('id'));
        $t->same('Point Review', $pointAnchor->attr('attributes')['data-odf-reference-name']);
        $t->same('Range source claim and point marker.', $blocks[0]->attr('text'));

        $t->same('link', $sourceReference->type);
        $t->same('#source-claim', $sourceReference->attr('url'));
        $t->same(['odf-reference-ref'], $sourceReference->attr('classes'));
        $t->same('Source Claim', $sourceReference->attr('attributes')['data-odf-ref-name']);
        $t->same('text', $sourceReference->attr('attributes')['data-odf-reference-format']);
        $t->same('source claim', $sourceReference->children[0]->attr('text'));
        $t->same('link', $pointReference->type);
        $t->same('#point-review', $pointReference->attr('url'));
        $t->same('page', $pointReference->attr('attributes')['data-odf-reference-format']);
        $t->same('point marker', $pointReference->children[0]->attr('text'));

        $t->same(2, $result['importReport']['content']['referenceMarkCount']);
        $t->same(2, $result['importReport']['content']['referenceReferenceCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[]{#source-claim .anchor .odf-reference-mark data-odf-reference-name="Source Claim"}source claim', $markdown);
        $t->contains('[source claim](#source-claim){.odf-reference-ref data-odf-ref-name="Source Claim" data-odf-reference-format="text"}', $markdown);
        $t->contains('<span id="source-claim" class="anchor odf-reference-mark" data-odf-reference-name="Source Claim"></span>source claim', $blocksHtml);
        $t->contains('<a href="#source-claim" class="odf-reference-ref" data-odf-ref-name="Source Claim" data-odf-reference-format="text">source claim</a>', $blocksHtml);
        $t->contains('<span id="point-review" class="anchor odf-reference-mark" data-odf-reference-name="Point Review"></span>marker.', $blocksHtml);
        $t->contains('<a href="#point-review" class="odf-reference-ref" data-odf-ref-name="Point Review" data-odf-reference-format="page">point marker</a>', $blocksHtml);
    },
    'maps ODT sequence fields into review spans and import report metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithSequences = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Caption <text:sequence text:name="Illustration" text:formula="ooow:Illustration+1" text:ref-name="seq-hero">Figure 1</text:sequence>: Hero image.</text:p>
      <text:h text:outline-level="2">Appendix <text:sequence text:name="Chapter" text:formula="ooow:Chapter+1">A</text:sequence></text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithSequences));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $paragraph = $blocks[0];
        $heading = $blocks[1];
        $figureSequence = $paragraph->children[1];
        $chapterSequence = $heading->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same('Caption Figure 1: Hero image.', $paragraph->attr('text'));
        $t->same('span', $figureSequence->type);
        $t->same(['odf-sequence'], $figureSequence->attr('classes'));
        $t->same('Figure 1', $figureSequence->children[0]->attr('text'));
        $t->same('Illustration', $figureSequence->attr('attributes')['data-odf-sequence-name']);
        $t->same('ooow:Illustration+1', $figureSequence->attr('attributes')['data-odf-sequence-formula']);
        $t->same('seq-hero', $figureSequence->attr('attributes')['data-odf-sequence-ref-name']);
        $t->same('heading', $heading->type);
        $t->same(2, $heading->attr('level'));
        $t->same('span', $chapterSequence->type);
        $t->same('Chapter', $chapterSequence->attr('attributes')['data-odf-sequence-name']);
        $t->same('A', $chapterSequence->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['sequenceCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Caption [Figure 1]{.odf-sequence data-odf-sequence-name="Illustration" data-odf-sequence-formula="ooow:Illustration+1" data-odf-sequence-ref-name="seq-hero"}: Hero image.', $markdown);
        $t->contains('## Appendix [A]{.odf-sequence data-odf-sequence-name="Chapter" data-odf-sequence-formula="ooow:Chapter+1"}', $markdown);
        $t->contains('<span class="odf-sequence" data-odf-sequence-name="Illustration" data-odf-sequence-formula="ooow:Illustration+1" data-odf-sequence-ref-name="seq-hero">Figure 1</span>', $blocksHtml);
        $t->contains('<h2>Appendix <span class="odf-sequence" data-odf-sequence-name="Chapter" data-odf-sequence-formula="ooow:Chapter+1">A</span></h2>', $blocksHtml);
    },
    'maps ODT linked and protected sections into review div metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithLinkedSections = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:section text:name="Imported Appendix" text:style-name="LinkedSection" text:protected="true" text:protection-key="sha1-key" text:protection-key-digest-algorithm="http://www.w3.org/2000/09/xmldsig#sha1">
        <text:section-source xlink:href="Sections/appendix.odt" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad" text:section-name="Appendix Source" text:filter-name="writer8"/>
        <text:p>Linked appendix fallback.</text:p>
      </text:section>
      <text:section text:name="Local Sidebar">
        <text:p>Local sidebar text.</text:p>
      </text:section>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithLinkedSections));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $linked = $blocks[0];
        $local = $blocks[1];
        $t->same('div', $linked->type);
        $t->same('imported-appendix', $linked->attr('id'));
        $t->same(['odf-section', 'odf-linked-section', 'odf-protected-section'], $linked->attr('classes'));
        $t->same('LinkedSection', $linked->attr('styleName'));
        $t->same(true, $linked->attr('protected'));
        $t->same(true, $linked->attr('protectionKeyPresent'));
        $t->same('Sections/appendix.odt', $linked->attr('sectionSource')['href']);
        $t->same('Appendix Source', $linked->attr('sectionSource')['sectionName']);
        $t->same('writer8', $linked->attr('sectionSource')['filterName']);
        $t->same('simple', $linked->attr('sectionSource')['type']);
        $t->same('embed', $linked->attr('sectionSource')['show']);
        $t->same('onLoad', $linked->attr('sectionSource')['actuate']);
        $t->same('Linked appendix fallback.', $linked->children[0]->attr('text'));
        $t->same('Imported Appendix', $linked->attr('attributes')['data-odf-section-name']);
        $t->same('LinkedSection', $linked->attr('attributes')['data-odf-section-style-name']);
        $t->same('true', $linked->attr('attributes')['data-odf-section-protected']);
        $t->same('true', $linked->attr('attributes')['data-odf-section-protection-key-present']);
        $t->same('http://www.w3.org/2000/09/xmldsig#sha1', $linked->attr('attributes')['data-odf-section-protection-key-digest-algorithm']);
        $t->same('Sections/appendix.odt', $linked->attr('attributes')['data-odf-section-source-href']);
        $t->same('Appendix Source', $linked->attr('attributes')['data-odf-section-source-name']);
        $t->same('writer8', $linked->attr('attributes')['data-odf-section-source-filter-name']);
        $t->same('simple', $linked->attr('attributes')['data-odf-section-source-type']);
        $t->same('embed', $linked->attr('attributes')['data-odf-section-source-show']);
        $t->same('onLoad', $linked->attr('attributes')['data-odf-section-source-actuate']);
        $t->same('div', $local->type);
        $t->same(['odf-section'], $local->attr('classes'));
        $t->same('Local sidebar text.', $local->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['sectionCount']);
        $t->same(1, $result['importReport']['content']['linkedSectionCount']);
        $t->same(1, $result['importReport']['content']['protectedSectionCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {#imported-appendix .odf-section .odf-linked-section .odf-protected-section data-odf-section-name="Imported Appendix"', $markdown);
        $t->contains('data-odf-section-source-href="Sections/appendix.odt"', $markdown);
        $t->contains('data-odf-section-protection-key-present="true"', $markdown);
        $t->contains('<div id="imported-appendix" class="odf-section odf-linked-section odf-protected-section" data-odf-section-name="Imported Appendix"', $blocksHtml);
        $t->contains('data-odf-section-source-href="Sections/appendix.odt"', $blocksHtml);
        $t->contains('data-odf-section-protection-key-present="true"', $blocksHtml);
        $t->contains('<p>Linked appendix fallback.</p>', $blocksHtml);
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
    'preserves ODT frame image dimensions for Markdown and WordPress handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithSizedImages = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Inline <draw:frame draw:name="Inline proof" svg:width="2.5cm" svg:height="1.25cm"><draw:image xlink:href="Pictures/hero.png"><svg:title>Inline proof title</svg:title><svg:desc>Inline proof alt</svg:desc></draw:image></draw:frame> image.</text:p>
      <draw:frame draw:name="Block proof" svg:width="5cm" svg:height="3cm">
        <draw:image xlink:href="Pictures/hero.png">
          <svg:title>Block proof title</svg:title>
          <svg:desc>Block proof alt</svg:desc>
        </draw:image>
      </draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithSizedImages));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $paragraphImage = $blocks[0]->children[1];
        $figure = $blocks[1];
        $figureImage = $figure->children[0];

        $t->same('image', $paragraphImage->type);
        $t->same('2.5cm', $paragraphImage->attr('width'));
        $t->same('1.25cm', $paragraphImage->attr('height'));
        $t->same('2.5cm', $paragraphImage->attr('attributes')['width']);
        $t->same('1.25cm', $paragraphImage->attr('attributes')['height']);
        $t->same('Inline proof alt', $paragraphImage->attr('alt'));
        $t->same('Inline proof title', $paragraphImage->attr('title'));
        $t->same('Inline Inline proof alt image.', $blocks[0]->attr('text'));

        $t->same('figure', $figure->type);
        $t->same('Block proof alt', $figure->attr('caption'));
        $t->same('image', $figureImage->type);
        $t->same('5cm', $figureImage->attr('width'));
        $t->same('3cm', $figureImage->attr('height'));
        $t->same('5cm', $figureImage->attr('attributes')['width']);
        $t->same('3cm', $figureImage->attr('attributes')['height']);
        $t->same('Block proof title', $figureImage->attr('title'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('![Inline proof alt](Pictures/hero.png "Inline proof title"){width="2.5cm" height="1.25cm"}', $markdown);
        $t->contains('![Block proof alt](Pictures/hero.png "Block proof title"){width="5cm" height="3cm"}', $markdown);
        $t->contains('<img src="Pictures/hero.png" alt="Inline proof alt" title="Inline proof title" width="2.5cm" height="1.25cm"/>', $blocksHtml);
        $t->contains('<img src="Pictures/hero.png" alt="Block proof alt" title="Block proof title" width="5cm" height="3cm"/>', $blocksHtml);
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
