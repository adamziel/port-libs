<?php

declare(strict_types=1);

use PortLibs\Pandoc\CitationCslProcessor;
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

$buildZipPackageWithCentralDirectoryOrder = static function (array $parts, array $centralOrder): ZipPackage {
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $centralRecords = [];

    foreach ($parts as $part) {
        $name = $part['name'];
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $offset = strlen($body);
        $crc = $crc32($data);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0
        );
        $body .= $name . $compressed;

        $centralRecords[$name] = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            0,
            0,
            0,
            0,
            str_ends_with($name, '/') ? 0x10 : 0,
            $offset
        ) . $name;
    }

    $central = '';
    foreach ($centralOrder as $name) {
        if (!isset($centralRecords[$name])) {
            throw new RuntimeException("Missing central directory record for {$name}");
        }

        $central .= $centralRecords[$name];
    }

    $centralOffset = strlen($body);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($parts), count($parts), strlen($central), $centralOffset, 0)
    );
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
    'maps ODT indented paragraph styles into Pandoc block quotes' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithQuoteIndent = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="ReviewQuote" style:family="paragraph" style:display-name="Review Quote">
      <style:paragraph-properties fo:margin-left="6mm"/>
    </style:style>
    <style:style style:name="InheritedQuote" style:family="paragraph" style:parent-style-name="ReviewQuote" style:display-name="Inherited Review Quote">
      <style:paragraph-properties fo:text-indent="1mm"/>
    </style:style>
    <style:style style:name="SmallIndent" style:family="paragraph" style:display-name="Small Indent">
      <style:paragraph-properties fo:margin-left="3mm"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithQuoteIndent = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p text:style-name="ReviewQuote">Quoted migration decision.</text:p>
      <text:p text:style-name="InheritedQuote">Inherited quoted detail.</text:p>
      <text:p text:style-name="SmallIndent">Indented but not quoted.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithQuoteIndent, null, $stylesWithQuoteIndent));
        $blocks = $result['document']->children;
        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);

        $t->same(3, count($blocks));
        $t->same('blockquote', $blocks[0]->type);
        $t->same(['odf-blockquote'], $blocks[0]->attr('classes'));
        $t->same('ReviewQuote', $blocks[0]->attr('styleName'));
        $t->same('6mm', $blocks[0]->attr('style')['paragraphProperties']['marginLeft']);
        $t->true(abs(($blocks[0]->attr('style')['paragraphProperties']['marginLeftPoints'] ?? 0.0) - 17.00787402) < 0.000001);
        $t->same('paragraph', $blocks[0]->children[0]->type);
        $t->same('Quoted migration decision.', $blocks[0]->children[0]->attr('text'));
        $t->same('blockquote', $blocks[1]->type);
        $t->same('InheritedQuote', $blocks[1]->attr('styleName'));
        $t->same('ReviewQuote', $blocks[1]->attr('style')['parentName']);
        $t->same('1mm', $blocks[1]->attr('style')['paragraphProperties']['textIndent']);
        $t->same('paragraph', $blocks[1]->children[0]->type);
        $t->same('Inherited quoted detail.', $blocks[1]->children[0]->attr('text'));
        $t->same('paragraph', $blocks[2]->type);
        $t->same('Indented but not quoted.', $blocks[2]->attr('text'));
        $t->same(2, $result['importReport']['content']['blockquoteCount']);

        $t->contains('> Quoted migration decision.', $markdown);
        $t->contains('> Inherited quoted detail.', $markdown);
        $t->contains('Indented but not quoted.', $markdown);
        $t->contains('<blockquote class="wp-block-quote"><p>Quoted migration decision.</p></blockquote>', $blocksHtml);
        $t->contains('<blockquote class="wp-block-quote"><p>Inherited quoted detail.</p></blockquote>', $blocksHtml);
        $t->contains('<p>Indented but not quoted.</p>', $blocksHtml);
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
    'maps ODT table names and protection metadata into review table handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithNamedProtectedTable = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table
        table:name="Protected Review Matrix"
        table:style-name="ReviewTable"
        table:protected="true"
        table:protection-key="opaque-source-key"
        table:protection-key-digest-algorithm="urn:odf:sha1">
        <table:table-row>
          <table:table-cell><text:p>Owner</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Migration desk</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithNamedProtectedTable));
        $table = $result['document']->children[0];
        $geometry = $table->attr('tableGeometry');

        $t->same('table', $table->type);
        $t->same('Protected Review Matrix', $table->attr('caption'));
        $t->same('Protected Review Matrix', $table->attr('tableName'));
        $t->same('ReviewTable', $table->attr('styleName'));
        $t->same(true, $table->attr('protected'));
        $t->same(true, $table->attr('protectionKeyPresent'));
        $t->same('urn:odf:sha1', $table->attr('protectionKeyDigestAlgorithm'));
        $t->same('Protected Review Matrix', $table->attr('htmlAttributes')['data-odf-table-name']);
        $t->same('ReviewTable', $table->attr('htmlAttributes')['data-odf-table-style-name']);
        $t->same('true', $table->attr('htmlAttributes')['data-odf-table-protected']);
        $t->same('true', $table->attr('htmlAttributes')['data-odf-table-protection-key-present']);
        $t->same('urn:odf:sha1', $table->attr('htmlAttributes')['data-odf-table-protection-key-digest-algorithm']);
        $t->true(is_array($geometry));
        $geometry = is_array($geometry) ? $geometry : [];
        $t->same('Protected Review Matrix', $geometry['caption'] ?? null);
        $t->same(2, $geometry['columnCount'] ?? null);
        $t->same(2, $geometry['summary']['rowCount'] ?? null);
        $t->same('Migration desk', $geometry['coverage'][2]['text'] ?? null);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains(': Protected Review Matrix', $markdown);
        $t->contains('<table data-odf-table-name="Protected Review Matrix" data-odf-table-style-name="ReviewTable" data-odf-table-protected="true" data-odf-table-protection-key-present="true" data-odf-table-protection-key-digest-algorithm="urn:odf:sha1">', $blocksHtml);
        $t->contains('<figcaption class="wp-element-caption">Protected Review Matrix</figcaption>', $blocksHtml);
    },
    'maps ODT table cell formulas and typed values into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTypedTableCells = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Calculated Review">
        <table:table-row>
          <table:table-cell><text:p>Metric</text:p></table:table-cell>
          <table:table-cell><text:p>Value</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell office:value-type="string" office:string-value="Source total"><text:p>Total</text:p></table:table-cell>
          <table:table-cell table:formula="of:=SUM([.B2:.B3])" office:value-type="currency" office:value="42.5" office:currency="USD"><text:p>$42.50</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell office:value-type="date" office:date-value="2026-06-05"><text:p>Review date</text:p></table:table-cell>
          <table:table-cell office:value-type="boolean" office:boolean-value="true"><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTypedTableCells));
        $table = $result['document']->children[0];
        $rows = $table->children[0]->children;
        $stringCell = $rows[1]->children[0];
        $formulaCell = $rows[1]->children[1];
        $dateCell = $rows[2]->children[0];
        $booleanCell = $rows[2]->children[1];
        $geometry = $table->attr('tableGeometry');
        $coverage = is_array($geometry) ? ($geometry['coverage'] ?? []) : [];

        $t->same('table', $table->type);
        $t->same('Calculated Review', $table->attr('caption'));
        $t->same('Total', $stringCell->attr('text'));
        $t->same(['odf-table-cell-value'], $stringCell->attr('classes'));
        $t->same('string', $stringCell->attr('odfCellMetadata')['valueType']);
        $t->same('Source total', $stringCell->attr('odfCellMetadata')['stringValue']);
        $t->same('string', $stringCell->attr('htmlAttributes')['data-odf-cell-value-type']);
        $t->same('Source total', $stringCell->attr('htmlAttributes')['data-odf-cell-string-value']);

        $t->same('$42.50', $formulaCell->attr('text'));
        $t->same(['odf-table-cell-value', 'odf-table-cell-formula'], $formulaCell->attr('classes'));
        $t->same('of:=SUM([.B2:.B3])', $formulaCell->attr('odfCellMetadata')['formula']);
        $t->same('currency', $formulaCell->attr('odfCellMetadata')['valueType']);
        $t->same('42.5', $formulaCell->attr('odfCellMetadata')['value']);
        $t->same('USD', $formulaCell->attr('odfCellMetadata')['currency']);
        $t->same('of:=SUM([.B2:.B3])', $formulaCell->attr('htmlAttributes')['data-odf-cell-formula']);
        $t->same('currency', $formulaCell->attr('htmlAttributes')['data-odf-cell-value-type']);
        $t->same('42.5', $formulaCell->attr('htmlAttributes')['data-odf-cell-value']);
        $t->same('USD', $formulaCell->attr('htmlAttributes')['data-odf-cell-currency']);

        $t->same('date', $dateCell->attr('odfCellMetadata')['valueType']);
        $t->same('2026-06-05', $dateCell->attr('odfCellMetadata')['dateValue']);
        $t->same('boolean', $booleanCell->attr('odfCellMetadata')['valueType']);
        $t->same(true, $booleanCell->attr('odfCellMetadata')['booleanValue']);
        $t->same('true', $booleanCell->attr('htmlAttributes')['data-odf-cell-boolean-value']);
        $t->same(4, count(array_filter(
            $coverage,
            static fn (array $record): bool => isset($record['sourceAttributes']['htmlAttributes']['data-odf-cell-value-type'])
        )));
        $t->same('of:=SUM([.B2:.B3])', $coverage[3]['sourceAttributes']['htmlAttributes']['data-odf-cell-formula'] ?? null);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<td class="odf-table-cell-value" data-odf-cell-value-type="string" data-odf-cell-string-value="Source total"><p>Total</p></td>', $blocksHtml);
        $t->contains('<td class="odf-table-cell-value odf-table-cell-formula" data-odf-cell-formula="of:=SUM([.B2:.B3])" data-odf-cell-value-type="currency" data-odf-cell-value="42.5" data-odf-cell-currency="USD"><p>$42.50</p></td>', $blocksHtml);
        $t->contains('<td class="odf-table-cell-value" data-odf-cell-value-type="date" data-odf-cell-date-value="2026-06-05"><p>Review date</p></td>', $blocksHtml);
        $t->contains('<td class="odf-table-cell-value" data-odf-cell-value-type="boolean" data-odf-cell-boolean-value="true"><p>Ready</p></td>', $blocksHtml);
    },
    'maps ODT table templates into table review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithTableTemplate = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:styles>
    <table:table-template
      table:name="ReviewTemplate"
      table:first-row-start-column="HeaderStart"
      table:first-row-end-column="HeaderEnd"
      table:first-column="FirstColumn"
      table:last-column="LastColumn"
      table:first-row="HeaderRow"
      table:last-row="SummaryRow"
      table:body="BodyCell"
      table:odd-rows="OddRow"
      table:even-rows="EvenRow"/>
  </office:styles>
</office:document-styles>
XML;
        $contentWithTemplatedTable = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0">
  <office:body>
    <office:text>
      <table:table table:name="Templated Review" table:template-name="ReviewTemplate">
        <table:table-row>
          <table:table-cell><text:p>Area</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell><text:p>Media</text:p></table:table-cell>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
      </table:table>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTemplatedTable, null, $stylesWithTableTemplate));
        $table = $result['document']->children[0];
        $template = $result['tableTemplates']['ReviewTemplate'];

        $t->same(1, $result['document']->attr('tableTemplates')['count']);
        $t->same(1, $result['importReport']['tableTemplates']['count']);
        $t->same(1, $result['importReport']['styles']['tableTemplateCount']);
        $t->same('ReviewTemplate', $template['name']);
        $t->same('HeaderStart', $template['styles']['firstRowStartColumn']);
        $t->same('HeaderEnd', $template['styles']['firstRowEndColumn']);
        $t->same('FirstColumn', $template['styles']['firstColumn']);
        $t->same('LastColumn', $template['styles']['lastColumn']);
        $t->same('HeaderRow', $template['styles']['firstRow']);
        $t->same('SummaryRow', $template['styles']['lastRow']);
        $t->same('BodyCell', $template['styles']['body']);
        $t->same('OddRow', $template['styles']['oddRows']);
        $t->same('EvenRow', $template['styles']['evenRows']);

        $t->same('table', $table->type);
        $t->same('ReviewTemplate', $table->attr('templateName'));
        $t->same(['odf-table-template'], $table->attr('classes'));
        $t->same('ReviewTemplate', $table->attr('tableTemplate')['name']);
        $t->same('BodyCell', $table->attr('tableTemplate')['styles']['body']);
        $t->same('ReviewTemplate', $table->attr('htmlAttributes')['data-odf-table-template-name']);
        $t->same('true', $table->attr('htmlAttributes')['data-odf-table-template-exists']);
        $t->same('9', $table->attr('htmlAttributes')['data-odf-table-template-style-count']);
        $t->same(1, $result['importReport']['content']['tableTemplateReferenceCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains(': Templated Review', $markdown);
        $t->contains('<table class="odf-table-template" data-odf-table-name="Templated Review" data-odf-table-template-name="ReviewTemplate" data-odf-table-template-exists="true" data-odf-table-template-style-count="9">', $blocksHtml);
    },
    'maps ODT text-position styles into superscript and subscript spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithVerticalText = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <style:style style:name="SourceSuperscript" style:family="text">
      <style:text-properties style:text-position="super 58%"/>
    </style:style>
    <style:style style:name="InheritedSuperscript" style:family="text" style:parent-style-name="SourceSuperscript"/>
    <style:style style:name="SourceSubscript" style:family="text">
      <style:text-properties style:text-position="sub 58%"/>
    </style:style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithVerticalText = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Reviewed mark<text:span text:style-name="InheritedSuperscript">TM</text:span> and H<text:span text:style-name="SourceSubscript">2</text:span>O survive.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithVerticalText, null, $stylesWithVerticalText));
        $paragraph = $result['document']->children[0];
        $superscript = $paragraph->children[1];
        $subscript = $paragraph->children[3];

        $t->same('Reviewed markTM and H2O survive.', $paragraph->attr('text'));
        $t->same('SourceSuperscript', $result['styles']['InheritedSuperscript']['parentName']);
        $t->same(true, $result['styles']['SourceSuperscript']['textProperties']['superscript']);
        $t->same(true, $result['styles']['SourceSubscript']['textProperties']['subscript']);
        $t->same('superscript', $superscript->type);
        $t->same('span', $superscript->children[0]->type);
        $t->same('InheritedSuperscript', $superscript->children[0]->attr('styleName'));
        $t->same('TM', $superscript->children[0]->children[0]->attr('text'));
        $t->same('subscript', $subscript->type);
        $t->same('span', $subscript->children[0]->type);
        $t->same('SourceSubscript', $subscript->children[0]->attr('styleName'));
        $t->same('2', $subscript->children[0]->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Reviewed mark^[TM]{data-odf-style-name="InheritedSuperscript"}^ and H~[2]{data-odf-style-name="SourceSubscript"}~O survive.', $markdown);
        $t->contains('<sup><span data-odf-style-name="InheritedSuperscript">TM</span></sup>', $blocksHtml);
        $t->contains('<sub><span data-odf-style-name="SourceSubscript">2</span></sub>', $blocksHtml);
    },
    'maps ODT ruby annotations into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithRuby = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Localized <text:ruby text:style-name="SourceRuby"><text:ruby-base>漢字</text:ruby-base><text:ruby-text text:style-name="RubyText">kanji</text:ruby-text></text:ruby> label and <text:ruby><text:ruby-base><text:span>東京</text:span></text:ruby-base><text:ruby-text>Tokyo</text:ruby-text></text:ruby> note.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithRuby));
        $paragraph = $result['document']->children[0];
        $firstRuby = $paragraph->children[1];
        $secondRuby = $paragraph->children[3];

        $t->same('Localized 漢字 label and 東京 note.', $paragraph->attr('text'));
        $t->same('span', $firstRuby->type);
        $t->same(['odf-ruby'], $firstRuby->attr('classes'));
        $t->same('kanji', $firstRuby->attr('rubyText'));
        $t->same('SourceRuby', $firstRuby->attr('rubyStyleName'));
        $t->same('RubyText', $firstRuby->attr('rubyTextStyleName'));
        $t->same('kanji', $firstRuby->attr('attributes')['data-odf-ruby-text']);
        $t->same('SourceRuby', $firstRuby->attr('attributes')['data-odf-ruby-style-name']);
        $t->same('RubyText', $firstRuby->attr('attributes')['data-odf-ruby-text-style-name']);
        $t->same('漢字', $firstRuby->children[0]->attr('text'));

        $t->same('span', $secondRuby->type);
        $t->same('Tokyo', $secondRuby->attr('rubyText'));
        $t->same('東京', $secondRuby->children[0]->attr('text'));
        $t->same(2, $result['importReport']['content']['rubyCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[漢字]{.odf-ruby data-odf-ruby-text="kanji" data-odf-ruby-style-name="SourceRuby" data-odf-ruby-text-style-name="RubyText"}', $markdown);
        $t->contains('[東京]{.odf-ruby data-odf-ruby-text="Tokyo"}', $markdown);
        $t->contains('<span class="odf-ruby" data-odf-ruby-text="kanji" data-odf-ruby-style-name="SourceRuby" data-odf-ruby-text-style-name="RubyText">漢字</span>', $blocksHtml);
        $t->contains('<span class="odf-ruby" data-odf-ruby-text="Tokyo">東京</span>', $blocksHtml);
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
    'inherits parent ODT list style for styleless nested lists' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithInheritedList = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="1" text:start-value="2"/>
      <text:list-level-style-number text:level="2" style:num-format="a" text:start-value="4"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithStylelessNestedList = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="ReviewSteps">
        <text:list-item>
          <text:p>Top-level review item</text:p>
          <text:list>
            <text:list-item><text:p>Inherited nested review item</text:p></text:list-item>
          </text:list>
        </text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithStylelessNestedList, null, $stylesWithInheritedList));
        $outer = $result['document']->children[0];
        $inner = $outer->children[0]->children[1];

        $t->same('ordered_list', $outer->type);
        $t->same('ReviewSteps', $outer->attr('styleName'));
        $t->same(2, $outer->attr('start'));
        $t->same('ordered_list', $inner->type);
        $t->same('ReviewSteps', $inner->attr('inheritedStyleName'));
        $t->same(4, $inner->attr('start'));
        $t->same('lower_alpha', $inner->attr('style'));
        $t->same(2, $inner->attr('listLevel'));
        $t->same('Inherited nested review item', $inner->children[0]->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('2.  Top-level review item', $markdown);
        $t->contains('  d.  Inherited nested review item', $markdown);
        $t->contains('<ol start="2">', $blocksHtml);
        $t->contains('<ol start="4" type="a">', $blocksHtml);
    },
    'maps ODT list headers as unnumbered review content' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $stylesWithListHeader = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:styles>
    <text:list-style style:name="HeaderReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="a" text:start-value="3"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;
        $contentWithListHeader = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:list text:style-name="HeaderReviewSteps">
        <text:list-header><text:p>Review scope introduction</text:p></text:list-header>
        <text:list-item><text:p>First numbered item</text:p></text:list-item>
        <text:list-item><text:p>Second numbered item</text:p></text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithListHeader, null, $stylesWithListHeader));
        $list = $result['document']->children[0];
        $header = $list->children[0];
        $firstItem = $list->children[1];
        $secondItem = $list->children[2];

        $t->same('ordered_list', $list->type);
        $t->same(3, $list->attr('start'));
        $t->same('lower_alpha', $list->attr('style'));
        $t->same(true, $header->attr('listHeader'));
        $t->same(['odf-list-header'], $header->attr('classes'));
        $t->same('true', $header->attr('attributes')['data-odf-list-header']);
        $t->same('1', $header->attr('attributes')['data-odf-list-level']);
        $t->same('Review scope introduction', $header->children[0]->attr('text'));
        $t->same('First numbered item', $firstItem->children[0]->attr('text'));
        $t->same('Second numbered item', $secondItem->children[0]->attr('text'));
        $t->same(1, $result['importReport']['content']['listHeaderCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {.odf-list-header data-odf-list-header="true" data-odf-list-level="1"}', $markdown);
        $t->contains('Review scope introduction', $markdown);
        $t->contains('c.  First numbered item', $markdown);
        $t->contains('d.  Second numbered item', $markdown);
        $t->true(!str_contains($markdown, 'd.  First numbered item'), 'List header must not advance ordered Markdown numbering');
        $t->contains('<div class="odf-list-header" data-odf-list-header="true" data-odf-list-level="1"><p>Review scope introduction</p></div>', $blocksHtml);
        $t->contains('<ol start="3" type="a"><li>First numbered item</li><li>Second numbered item</li></ol>', $blocksHtml);
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
    'preserves ODT link metadata for Markdown and WordPress review output' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithLinkMetadata = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Source <text:a xlink:href="https://example.test/source.odt#review" xlink:type="simple" xlink:show="new" xlink:actuate="onRequest" office:name="Source Link" office:title="Source ODT review" office:target-frame-name="_blank" text:style-name="SourceLink" text:visited-style-name="VisitedSourceLink">review link</text:a> remains auditable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithLinkMetadata));
        $paragraph = $result['document']->children[0];
        $link = $paragraph->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same('Source review link remains auditable.', $paragraph->attr('text'));
        $t->same('link', $link->type);
        $t->same('https://example.test/source.odt#review', $link->attr('url'));
        $t->same('Source ODT review', $link->attr('title'));
        $t->same('review link', $link->children[0]->attr('text'));
        $t->same('odt', $link->attr('sourceFormat'));
        $t->same(['odf-link'], $link->attr('classes'));
        $t->same('Source Link', $link->attr('odfLinkMetadata')['name']);
        $t->same('SourceLink', $link->attr('odfLinkMetadata')['styleName']);
        $t->same('VisitedSourceLink', $link->attr('odfLinkMetadata')['visitedStyleName']);
        $t->same('_blank', $link->attr('odfLinkMetadata')['targetFrameName']);
        $t->same('simple', $link->attr('odfLinkMetadata')['type']);
        $t->same('new', $link->attr('odfLinkMetadata')['show']);
        $t->same('onRequest', $link->attr('odfLinkMetadata')['actuate']);
        $t->same('Source Link', $link->attr('attributes')['data-odf-link-name']);
        $t->same('SourceLink', $link->attr('attributes')['data-odf-link-style-name']);
        $t->same('VisitedSourceLink', $link->attr('attributes')['data-odf-link-visited-style-name']);
        $t->same('_blank', $link->attr('attributes')['data-odf-link-target-frame-name']);
        $t->same('simple', $link->attr('attributes')['data-odf-link-type']);
        $t->same('new', $link->attr('attributes')['data-odf-link-show']);
        $t->same('onRequest', $link->attr('attributes')['data-odf-link-actuate']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[review link](https://example.test/source.odt#review "Source ODT review"){.odf-link data-odf-link-name="Source Link" data-odf-link-style-name="SourceLink" data-odf-link-visited-style-name="VisitedSourceLink" data-odf-link-target-frame-name="_blank" data-odf-link-type="simple" data-odf-link-show="new" data-odf-link-actuate="onRequest"}', $markdown);
        $t->contains('<a href="https://example.test/source.odt#review" title="Source ODT review" class="odf-link" data-odf-link-name="Source Link" data-odf-link-style-name="SourceLink" data-odf-link-visited-style-name="VisitedSourceLink" data-odf-link-target-frame-name="_blank" data-odf-link-type="simple" data-odf-link-show="new" data-odf-link-actuate="onRequest">review link</a>', $blocksHtml);
    },
    'maps ODT annotation ranges into review spans and note handoff' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithAnnotationRange = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:body>
    <office:text>
      <text:p>Review <office:annotation office:name="ann-review-1"><dc:creator>Migration Reviewer</dc:creator><dc:date>2026-06-05T05:58:00Z</dc:date><text:p>Range comment for the annotated source claim.</text:p></office:annotation>annotated <text:span>claim</text:span><office:annotation-end office:name="ann-review-1"/> after.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithAnnotationRange));
        $paragraph = $result['document']->children[0];
        $range = $paragraph->children[1];
        $note = $range->children[1];

        $t->same('Review annotated claim after.', $paragraph->attr('text'));
        $t->same('span', $range->type);
        $t->same(['odf-annotation-range'], $range->attr('classes'));
        $t->same('ann-review-1', $range->attr('annotationName'));
        $t->same('ann-review-1', $range->attr('attributes')['data-odf-annotation-name']);
        $t->same('Migration Reviewer', $range->attr('annotationMetadata')['author']);
        $t->same('2026-06-05T05:58:00Z', $range->attr('annotationMetadata')['date']);
        $t->same('Migration Reviewer', $range->attr('attributes')['data-odf-annotation-author']);
        $t->same('2026-06-05T05:58:00Z', $range->attr('attributes')['data-odf-annotation-date']);
        $t->same('annotated claim', $range->children[0]->attr('text'));
        $t->same('note', $note->type);
        $t->same('Migration Reviewer', $note->attr('author'));
        $t->same('2026-06-05T05:58:00Z', $note->attr('date'));
        $t->same('Range comment for the annotated source claim.', $note->children[0]->attr('text'));
        $t->same(1, $result['importReport']['content']['noteCount']);
        $t->same(1, $result['importReport']['content']['annotationRangeCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[annotated claim[^1]]{.odf-annotation-range data-odf-annotation-name="ann-review-1" data-odf-annotation-author="Migration Reviewer" data-odf-annotation-date="2026-06-05T05:58:00Z"}', $markdown);
        $t->contains('[^1]: Range comment for the annotated source claim.', $markdown);
        $t->contains('<span class="odf-annotation-range" data-odf-annotation-name="ann-review-1" data-odf-annotation-author="Migration Reviewer" data-odf-annotation-date="2026-06-05T05:58:00Z">annotated claim<sup id="fnref-1">', $blocksHtml);
        $t->contains('<li id="fn-1"><p>Range comment for the annotated source claim.</p>', $blocksHtml);
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
    'preserves ODT soft page breaks as review markers' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithSoftPageBreak = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Before source page boundary <text:soft-page-break/>after source page boundary.</text:p>
      <text:h text:outline-level="2">Appendix marker<text:soft-page-break/>continued heading</text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithSoftPageBreak));
        $blocks = $result['document']->children;
        $paragraph = $blocks[0];
        $heading = $blocks[1];
        $paragraphBreak = $paragraph->children[1];
        $headingBreak = $heading->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same('Before source page boundary after source page boundary.', $paragraph->attr('text'));
        $t->same('span', $paragraphBreak->type);
        $t->same(true, $paragraphBreak->attr('softPageBreak'));
        $t->same(['odf-soft-page-break'], $paragraphBreak->attr('classes'));
        $t->same('true', $paragraphBreak->attr('attributes')['data-odf-soft-page-break']);
        $t->same('heading', $heading->type);
        $t->same('span', $headingBreak->type);
        $t->same(true, $headingBreak->attr('softPageBreak'));
        $t->same(2, $result['importReport']['content']['softPageBreakCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Before source page boundary []{.odf-soft-page-break data-odf-soft-page-break="true"}after source page boundary.', $markdown);
        $t->contains('## Appendix marker[]{.odf-soft-page-break data-odf-soft-page-break="true"}continued heading', $markdown);
        $t->contains('<span class="odf-soft-page-break" data-odf-soft-page-break="true"></span>after source page boundary.', $blocksHtml);
        $t->contains('<h2>Appendix marker<span class="odf-soft-page-break" data-odf-soft-page-break="true"></span>continued heading</h2>', $blocksHtml);
    },
    'maps ODT tab stops to Pandoc spaces in inline content' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTabs = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Before<text:tab/>after and <text:span>inner<text:tab/>tab</text:span>.</text:p>
      <text:h text:outline-level="2">Heading<text:tab/>tab</text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTabs));
        $paragraph = $result['document']->children[0];
        $heading = $result['document']->children[1];

        $t->same('Before after and inner tab.', $paragraph->attr('text'));
        $t->same('Before after and inner tab.', $paragraph->children[0]->attr('text'));
        $t->true(!str_contains($paragraph->attr('text'), "\t"), 'ODF tabs should normalize to Pandoc spaces in plain text');
        $t->same('Heading tab', $heading->children[0]->attr('text'));
        $t->true(!str_contains($heading->children[0]->attr('text'), "\t"), 'ODF tabs should normalize to Pandoc spaces in headings');

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Before after and inner tab.', $markdown);
        $t->contains('## Heading tab', $markdown);
        $t->contains('<p>Before after and inner tab.</p>', $blocksHtml);
        $t->contains('<h2>Heading tab</h2>', $blocksHtml);
    },
    'maps ODT form controls into review placeholders' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithForms = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <office:forms>
        <form:form form:name="Review Form">
          <form:text form:id="ctrl-title" form:name="SourceTitle" form:label="Source title" form:current-value="Migrated title" form:control-implementation="ooo:com.sun.star.form.component.TextField"/>
          <form:checkbox form:id="ctrl-publish" form:name="PublishReady" form:label="Ready to publish" form:current-state="checked"/>
        </form:form>
      </office:forms>
      <text:p>Title field <draw:control draw:control="ctrl-title"/> and missing <draw:control draw:control="ctrl-missing"/> remain visible.</text:p>
      <draw:frame draw:name="Publish checkbox" svg:width="3cm" svg:height="1cm">
        <draw:control draw:control="ctrl-publish"/>
      </draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithForms));
        $blocks = $result['document']->children;
        $paragraph = $blocks[0];
        $titleControl = $paragraph->children[1];
        $missingControl = $paragraph->children[3];
        $blockControl = $blocks[1];

        $t->same(2, count($blocks));
        $t->same('paragraph', $paragraph->type);
        $t->same('Title field Source title and missing ctrl-missing remain visible.', $paragraph->attr('text'));
        $t->same('span', $titleControl->type);
        $t->same(['odf-form-control', 'odf-control-text'], $titleControl->attr('classes'));
        $t->same('ctrl-title', $titleControl->attr('controlId'));
        $t->same('text', $titleControl->attr('controlType'));
        $t->same(true, $titleControl->attr('exists'));
        $t->same('Review Form', $titleControl->attr('formControl')['formName']);
        $t->same('SourceTitle', $titleControl->attr('formControl')['name']);
        $t->same('Source title', $titleControl->attr('formControl')['label']);
        $t->same('Migrated title', $titleControl->attr('formControl')['currentValue']);
        $t->same('ooo:com.sun.star.form.component.TextField', $titleControl->attr('formControl')['implementation']);
        $t->same('ctrl-title', $titleControl->attr('attributes')['data-odf-control-id']);
        $t->same('text', $titleControl->attr('attributes')['data-odf-control-type']);
        $t->same('true', $titleControl->attr('attributes')['data-odf-control-exists']);
        $t->same('Source title', $titleControl->children[0]->attr('text'));

        $t->same('span', $missingControl->type);
        $t->same(['odf-form-control', 'odf-missing-form-control'], $missingControl->attr('classes'));
        $t->same(false, $missingControl->attr('exists'));
        $t->same('ctrl-missing', $missingControl->children[0]->attr('text'));
        $t->same('false', $missingControl->attr('attributes')['data-odf-control-exists']);

        $t->same('div', $blockControl->type);
        $t->same(['odf-form-control', 'odf-control-checkbox'], $blockControl->attr('classes'));
        $t->same('ctrl-publish', $blockControl->attr('controlId'));
        $t->same('checkbox', $blockControl->attr('controlType'));
        $t->same('checked', $blockControl->attr('formControl')['currentState']);
        $t->same('Publish checkbox', $blockControl->attr('attributes')['data-odf-control-frame-name']);
        $t->same('3cm', $blockControl->attr('attributes')['data-odf-control-width']);
        $t->same('1cm', $blockControl->attr('attributes')['data-odf-control-height']);
        $t->same('Ready to publish', $blockControl->children[0]->attr('text'));
        $t->same(3, $result['importReport']['content']['formControlCount']);
        $t->same(1, $result['importReport']['content']['missingFormControlCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Source title]{.odf-form-control .odf-control-text data-odf-control-id="ctrl-title" data-odf-control-type="text" data-odf-control-exists="true"', $markdown);
        $t->contains('[ctrl-missing]{.odf-form-control .odf-missing-form-control data-odf-control-id="ctrl-missing" data-odf-control-exists="false"}', $markdown);
        $t->contains('::: {.odf-form-control .odf-control-checkbox data-odf-control-id="ctrl-publish" data-odf-control-type="checkbox" data-odf-control-exists="true"', $markdown);
        $t->contains('<span class="odf-form-control odf-control-text" data-odf-control-id="ctrl-title" data-odf-control-type="text" data-odf-control-exists="true"', $blocksHtml);
        $t->contains('<span class="odf-form-control odf-missing-form-control" data-odf-control-id="ctrl-missing" data-odf-control-exists="false">ctrl-missing</span>', $blocksHtml);
        $t->contains('<div class="odf-form-control odf-control-checkbox" data-odf-control-id="ctrl-publish" data-odf-control-type="checkbox" data-odf-control-exists="true"', $blocksHtml);
        $t->contains('Ready to publish', $blocksHtml);
    },
    'maps ODT form submission metadata onto review controls' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithFormSubmission = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <office:forms>
        <form:form
          form:name="Submission Form"
          xlink:href="https://example.test/import-review"
          xlink:type="simple"
          form:method="post"
          form:enctype="application/x-www-form-urlencoded"
          form:target-frame="_blank"
          form:command-type="table"
          form:command="import_review_packets"
          form:datasource="wp_import_queue"
          form:apply-filter="true"
          form:filter="status = 'ready'"
          form:order="created DESC"
          form:navigation-mode="current"
          form:tab-cycle="records"
          form:ignore-result="false"
          form:escape-processing="true"
          form:master-fields="source_id"
          form:detail-fields="source_id">
          <form:text form:id="ctrl-submit-title" form:name="SourceTitle" form:label="Source title" form:current-value="Ready packet"/>
        </form:form>
      </office:forms>
      <text:p>Submission <draw:control draw:control="ctrl-submit-title"/> stays auditable.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithFormSubmission));
        $control = $result['document']->children[0]->children[1];
        $formMetadata = $control->attr('formControl')['formMetadata'] ?? [];

        $t->same('Source title', $control->children[0]->attr('text'));
        $t->same('https://example.test/import-review', $formMetadata['action'] ?? null);
        $t->same('post', $formMetadata['method'] ?? null);
        $t->same('application/x-www-form-urlencoded', $formMetadata['enctype'] ?? null);
        $t->same('_blank', $formMetadata['targetFrame'] ?? null);
        $t->same('table', $formMetadata['commandType'] ?? null);
        $t->same('import_review_packets', $formMetadata['command'] ?? null);
        $t->same('wp_import_queue', $formMetadata['datasource'] ?? null);
        $t->same(true, $formMetadata['applyFilter'] ?? null);
        $t->same(false, $formMetadata['ignoreResult'] ?? null);
        $t->same(true, $formMetadata['escapeProcessing'] ?? null);
        $t->same('source_id', $formMetadata['masterFields'] ?? null);
        $t->same('source_id', $formMetadata['detailFields'] ?? null);
        $t->same('https://example.test/import-review', $control->attr('formControl')['formAction']);
        $t->same('post', $control->attr('attributes')['data-odf-control-form-method']);
        $t->same('table', $control->attr('attributes')['data-odf-control-form-command-type']);
        $t->same('true', $control->attr('attributes')['data-odf-control-form-apply-filter']);
        $t->same('false', $control->attr('attributes')['data-odf-control-form-ignore-result']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);

        $t->contains('data-odf-control-form-action="https://example.test/import-review"', $markdown);
        $t->contains('data-odf-control-form-command="import_review_packets"', $markdown);
        $t->contains('data-odf-control-form-datasource="wp_import_queue"', $markdown);
        $t->contains('<span class="odf-form-control odf-control-text" data-odf-control-id="ctrl-submit-title"', $blocksHtml);
        $t->contains('data-odf-control-form-target-frame="_blank"', $blocksHtml);
        $t->contains('data-odf-control-form-master-fields="source_id"', $blocksHtml);
    },
    'maps ODT field declarations and user-field fallback values into review metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithFieldDeclarations = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:sequence-decls>
        <text:sequence-decl text:name="Illustration" text:display-outline-level="0" text:separation-character="."/>
        <text:sequence-decl text:name="Table" text:display-outline-level="1" text:separation-character=":"/>
      </text:sequence-decls>
      <text:variable-decls>
        <text:variable-decl text:name="ReviewStatus" office:value-type="string"/>
      </text:variable-decls>
      <text:user-field-decls>
        <text:user-field-decl text:name="Reviewer" office:value-type="string" office:string-value="Migration Desk"/>
        <text:user-field-decl text:name="SourcePage" office:value-type="float" office:value="12"/>
      </text:user-field-decls>
      <text:p>Declared reviewer <text:user-field-get text:name="Reviewer"/> saw source page <text:user-field-get text:name="SourcePage"/> before <text:sequence text:name="Illustration" text:formula="ooow:Illustration+1">Figure 1</text:sequence>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithFieldDeclarations));
        $document = $result['document'];
        $declarations = $result['contentDeclarations'];
        $paragraph = $document->children[0];
        $reviewerField = $paragraph->children[1];
        $pageField = $paragraph->children[3];
        $sequence = $paragraph->children[5];

        $t->same('Declared reviewer Migration Desk saw source page 12 before Figure 1.', $paragraph->attr('text'));
        $t->same(2, $declarations['sequenceDeclarationCount']);
        $t->same(1, $declarations['variableDeclarationCount']);
        $t->same(2, $declarations['userFieldDeclarationCount']);
        $t->same('Illustration', $declarations['sequenceDeclarations']['Illustration']['name']);
        $t->same(0, $declarations['sequenceDeclarations']['Illustration']['displayOutlineLevel']);
        $t->same('.', $declarations['sequenceDeclarations']['Illustration']['separationCharacter']);
        $t->same('Table', $declarations['sequenceDeclarations']['Table']['name']);
        $t->same(1, $declarations['sequenceDeclarations']['Table']['displayOutlineLevel']);
        $t->same(':', $declarations['sequenceDeclarations']['Table']['separationCharacter']);
        $t->same('ReviewStatus', $declarations['variableDeclarations']['ReviewStatus']['name']);
        $t->same('string', $declarations['variableDeclarations']['ReviewStatus']['valueType']);
        $t->same('Migration Desk', $declarations['userFieldDeclarations']['Reviewer']['stringValue']);
        $t->same('float', $declarations['userFieldDeclarations']['SourcePage']['valueType']);
        $t->same('12', $declarations['userFieldDeclarations']['SourcePage']['value']);
        $t->same($declarations, $document->attr('contentDeclarations'));

        $t->same('span', $reviewerField->type);
        $t->same('Reviewer', $reviewerField->attr('fieldName'));
        $t->same(true, $reviewerField->attr('fieldMetadata')['declared']);
        $t->same('Migration Desk', $reviewerField->attr('fieldMetadata')['stringValue']);
        $t->same('Migration Desk', $reviewerField->children[0]->attr('text'));
        $t->same('true', $reviewerField->attr('attributes')['data-odf-field-declared']);
        $t->same('Migration Desk', $reviewerField->attr('attributes')['data-odf-field-string-value']);

        $t->same('span', $pageField->type);
        $t->same('SourcePage', $pageField->attr('fieldName'));
        $t->same(true, $pageField->attr('fieldMetadata')['declared']);
        $t->same('float', $pageField->attr('fieldMetadata')['valueType']);
        $t->same('12', $pageField->attr('fieldMetadata')['value']);
        $t->same('12', $pageField->children[0]->attr('text'));
        $t->same('true', $pageField->attr('attributes')['data-odf-field-declared']);
        $t->same('12', $pageField->attr('attributes')['data-odf-field-value']);

        $t->same('span', $sequence->type);
        $t->same('Illustration', $sequence->attr('attributes')['data-odf-sequence-name']);
        $t->same(2, $result['importReport']['content']['fieldCount']);
        $t->same(1, $result['importReport']['content']['sequenceCount']);
        $t->same(2, $result['importReport']['contentDeclarations']['sequenceDeclarationCount']);
        $t->same(1, $result['importReport']['contentDeclarations']['variableDeclarationCount']);
        $t->same(2, $result['importReport']['contentDeclarations']['userFieldDeclarationCount']);
        $t->same('Migration Desk', $result['importReport']['contentDeclarations']['userFieldDeclarations']['Reviewer']['stringValue']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Migration Desk]{.odf-field .odf-field-user-field-get data-odf-field-type="user-field-get" data-odf-field-name="Reviewer" data-odf-field-value-type="string" data-odf-field-string-value="Migration Desk" data-odf-field-declared="true"}', $markdown);
        $t->contains('[12]{.odf-field .odf-field-user-field-get data-odf-field-type="user-field-get" data-odf-field-name="SourcePage" data-odf-field-value-type="float" data-odf-field-value="12" data-odf-field-declared="true"}', $markdown);
        $t->contains('<span class="odf-field odf-field-user-field-get" data-odf-field-type="user-field-get" data-odf-field-name="Reviewer" data-odf-field-value-type="string" data-odf-field-string-value="Migration Desk" data-odf-field-declared="true">Migration Desk</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-user-field-get" data-odf-field-type="user-field-get" data-odf-field-name="SourcePage" data-odf-field-value-type="float" data-odf-field-value="12" data-odf-field-declared="true">12</span>', $blocksHtml);
    },
    'maps ODT variable user page and date fields into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithFields = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:body>
    <office:text>
      <text:user-field-decls>
        <text:user-field-decl text:name="Reviewer" office:value-type="string" office:string-value="Migration Desk"/>
      </text:user-field-decls>
      <text:p>Fields <text:variable-set text:name="ReviewStatus" office:value-type="string" office:string-value="Ready">Ready</text:variable-set> by <text:user-field-get text:name="Reviewer">Migration Desk</text:user-field-get> page <text:page-number text:select-page="current" text:page-adjust="1">2</text:page-number> exported <text:date text:fixed="true" text:date-value="2026-06-05">June 5, 2026</text:date>.</text:p>
      <text:h text:outline-level="2">Status <text:variable-get text:name="ReviewStatus">Ready</text:variable-get></text:h>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithFields));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));
        $paragraph = $blocks[0];
        $heading = $blocks[1];
        $reviewStatus = $paragraph->children[1];
        $reviewer = $paragraph->children[3];
        $pageNumber = $paragraph->children[5];
        $date = $paragraph->children[7];
        $statusGet = $heading->children[1];

        $t->same('paragraph', $paragraph->type);
        $t->same('Fields Ready by Migration Desk page 2 exported June 5, 2026.', $paragraph->attr('text'));
        $t->same('span', $reviewStatus->type);
        $t->same(['odf-field', 'odf-field-variable-set'], $reviewStatus->attr('classes'));
        $t->same('variable-set', $reviewStatus->attr('fieldType'));
        $t->same('ReviewStatus', $reviewStatus->attr('fieldName'));
        $t->same('string', $reviewStatus->attr('fieldMetadata')['valueType']);
        $t->same('Ready', $reviewStatus->attr('fieldMetadata')['stringValue']);
        $t->same('Ready', $reviewStatus->children[0]->attr('text'));

        $t->same(['odf-field', 'odf-field-user-field-get'], $reviewer->attr('classes'));
        $t->same('Reviewer', $reviewer->attr('fieldName'));
        $t->same('Migration Desk', $reviewer->children[0]->attr('text'));
        $t->same('page-number', $pageNumber->attr('fieldType'));
        $t->same('current', $pageNumber->attr('fieldMetadata')['selectPage']);
        $t->same('1', $pageNumber->attr('fieldMetadata')['pageAdjust']);
        $t->same('2', $pageNumber->children[0]->attr('text'));
        $t->same('date', $date->attr('fieldType'));
        $t->same(true, $date->attr('fieldMetadata')['fixed']);
        $t->same('2026-06-05', $date->attr('fieldMetadata')['dateValue']);
        $t->same('June 5, 2026', $date->children[0]->attr('text'));

        $t->same('heading', $heading->type);
        $t->same(2, $heading->attr('level'));
        $t->same('variable-get', $statusGet->attr('fieldType'));
        $t->same('ReviewStatus', $statusGet->attr('attributes')['data-odf-field-name']);
        $t->same(5, $result['importReport']['content']['fieldCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Ready]{.odf-field .odf-field-variable-set data-odf-field-type="variable-set" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready"}', $markdown);
        $t->contains('[2]{.odf-field .odf-field-page-number data-odf-field-type="page-number" data-odf-field-select-page="current" data-odf-field-page-adjust="1"}', $markdown);
        $t->contains('<span class="odf-field odf-field-variable-set" data-odf-field-type="variable-set" data-odf-field-name="ReviewStatus" data-odf-field-value-type="string" data-odf-field-string-value="Ready">Ready</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-user-field-get" data-odf-field-type="user-field-get" data-odf-field-name="Reviewer">Migration Desk</span>', $blocksHtml);
        $t->contains('<span class="odf-field odf-field-date" data-odf-field-type="date" data-odf-field-date-value="2026-06-05" data-odf-field-fixed="true">June 5, 2026</span>', $blocksHtml);
    },
    'maps ODT bibliography marks into citation handoff nodes' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithBibliographyMarks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Source cites <text:bibliography-mark text:identifier="smith1899" text:number="4">Smith source packet</text:bibliography-mark> and <text:bibliography-mark text:identifier="missing-source" text:number="5">missing source packet</text:bibliography-mark>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithBibliographyMarks));
        $paragraph = $result['document']->children[0];
        $knownCitation = $paragraph->children[1];
        $missingCitation = $paragraph->children[3];

        $t->same('paragraph', $paragraph->type);
        $t->same('Source cites Smith source packet and missing source packet.', $paragraph->attr('text'));
        $t->same('citation', $knownCitation->type);
        $t->same('smith1899', $knownCitation->attr('id'));
        $t->same('[@smith1899]', $knownCitation->attr('text'));
        $t->same('normal', $knownCitation->attr('mode'));
        $t->same('odt', $knownCitation->attr('sourceFormat'));
        $t->same('Smith source packet', $knownCitation->attr('displayText'));
        $t->same(4, $knownCitation->attr('citationNumber'));
        $t->same('Smith source packet', $knownCitation->children[0]->attr('text'));
        $t->same('citation', $missingCitation->type);
        $t->same('missing-source', $missingCitation->attr('id'));
        $t->same('missing source packet', $missingCitation->attr('displayText'));
        $t->same(5, $missingCitation->attr('citationNumber'));
        $t->same(2, $result['importReport']['content']['citationCount']);

        $processor = CitationCslProcessor::fromItems([[
            'id' => 'smith1899',
            'title' => 'Source Packet',
            'author' => [['family' => 'Smith', 'given' => 'Ada']],
            'issued' => ['date-parts' => [[1899]]],
        ]]);
        $processed = $processor->apply($result['document']);
        $t->same('(Smith 1899)', $processed->children[0]->children[1]->attr('rendered'));
        $t->same('[@missing-source]', $processed->children[0]->children[3]->attr('rendered'));
        $t->same(['smith1899', 'missing-source'], $processor->citationIds($result['document']));
        $t->same(['missing-source'], $processor->missingCitationIds($result['document']));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $processedBlocks = (new WordPressBlockWriter())->write($processed);
        $t->contains('Source cites [@smith1899] and [@missing-source].', $markdown);
        $t->contains('<p>Source cites [@smith1899] and [@missing-source].</p>', $blocksHtml);
        $t->contains('<p>Source cites (Smith 1899) and [@missing-source].</p>', $processedBlocks);
    },
    'maps ODT table of contents into review div metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithTableOfContents = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:table-of-content text:name="Source Navigation" text:style-name="Contents_20_1" text:protected="true" text:protection-key="toc-key" text:protection-key-digest-algorithm="urn:odf:sha1">
        <text:table-of-content-source text:outline-level="3" text:relative-tab-stop-position="true" text:use-index-marks="false" text:use-index-source-styles="true">
          <text:index-title-template text:style-name="ContentsTitle">Contents</text:index-title-template>
          <text:table-of-content-entry-template text:outline-level="1" text:style-name="ContentsEntry">
            <text:index-entry-link-start/>
            <text:index-entry-text/>
            <text:index-entry-tab-stop style:type="right" style:position="17cm" style:leader-char="."/>
            <text:index-entry-page-number/>
            <text:index-entry-link-end/>
          </text:table-of-content-entry-template>
          <text:index-source-styles text:outline-level="1">
            <text:index-source-style text:style-name="ImportHeading"/>
          </text:index-source-styles>
        </text:table-of-content-source>
        <text:index-title text:name="Table of Contents">
          <text:p>Contents</text:p>
        </text:index-title>
        <text:index-body>
          <text:p><text:a xlink:href="#odt-source-packet">ODT source packet</text:a><text:tab/>1</text:p>
          <text:p><text:a xlink:href="#review">Review table</text:a><text:tab/>2</text:p>
        </text:index-body>
      </text:table-of-content>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithTableOfContents));
        $blocks = $result['document']->children;

        $t->same(1, count($blocks));
        $toc = $blocks[0];
        $t->same('div', $toc->type);
        $t->same('source-navigation', $toc->attr('id'));
        $t->same(['odf-table-of-contents', 'odf-protected-table-of-contents'], $toc->attr('classes'));
        $t->same('Source Navigation', $toc->attr('tableOfContentsName'));
        $t->same('Contents_20_1', $toc->attr('styleName'));
        $t->same(true, $toc->attr('protected'));
        $t->same(true, $toc->attr('protectionKeyPresent'));
        $t->same('urn:odf:sha1', $toc->attr('protectionKeyDigestAlgorithm'));

        $source = $toc->attr('tableOfContentsSource');
        $t->same(3, $source['outlineLevel']);
        $t->same(true, $source['relativeTabStopPosition']);
        $t->same(false, $source['useIndexMarks']);
        $t->same(true, $source['useIndexSourceStyles']);
        $t->same([['outlineLevel' => 1, 'styleNames' => ['ImportHeading']]], $source['sourceStyles']);
        $t->same('title', $source['templates'][0]['type']);
        $t->same('ContentsTitle', $source['templates'][0]['styleName']);
        $t->same('entry', $source['templates'][1]['type']);
        $t->same(1, $source['templates'][1]['outlineLevel']);
        $t->same('ContentsEntry', $source['templates'][1]['styleName']);
        $t->same(['index-entry-link-start', 'index-entry-text', 'index-entry-tab-stop', 'index-entry-page-number', 'index-entry-link-end'], array_column($source['templates'][1]['components'], 'type'));
        $t->same('right', $source['templates'][1]['components'][2]['tabStopType']);
        $t->same('17cm', $source['templates'][1]['components'][2]['tabStopPosition']);
        $t->same('.', $source['templates'][1]['components'][2]['leaderChar']);

        $attributes = $toc->attr('attributes');
        $t->same('Source Navigation', $attributes['data-odf-toc-name']);
        $t->same('Contents_20_1', $attributes['data-odf-toc-style-name']);
        $t->same('true', $attributes['data-odf-toc-protected']);
        $t->same('true', $attributes['data-odf-toc-protection-key-present']);
        $t->same('urn:odf:sha1', $attributes['data-odf-toc-protection-key-digest-algorithm']);
        $t->same('3', $attributes['data-odf-toc-source-outline-level']);
        $t->same('true', $attributes['data-odf-toc-source-relative-tab-stop-position']);
        $t->same('false', $attributes['data-odf-toc-source-use-index-marks']);
        $t->same('true', $attributes['data-odf-toc-source-use-index-source-styles']);
        $t->same('1', $attributes['data-odf-toc-source-style-count']);
        $t->same('2', $attributes['data-odf-toc-template-count']);

        $title = $toc->children[0];
        $body = $toc->children[1];
        $t->same('div', $title->type);
        $t->same(['odf-index-title'], $title->attr('classes'));
        $t->same('true', $title->attr('attributes')['data-odf-index-title']);
        $t->same('Contents', $title->children[0]->attr('text'));
        $t->same('div', $body->type);
        $t->same(['odf-index-body'], $body->attr('classes'));
        $t->same('true', $body->attr('attributes')['data-odf-index-body']);
        $t->same('#odt-source-packet', $body->children[0]->children[0]->attr('url'));
        $t->same('ODT source packet', $body->children[0]->children[0]->children[0]->attr('text'));
        $t->same('#review', $body->children[1]->children[0]->attr('url'));
        $t->same(1, $result['importReport']['content']['tableOfContentsCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {#source-navigation .odf-table-of-contents .odf-protected-table-of-contents data-odf-toc-name="Source Navigation"', $markdown);
        $t->contains('data-odf-toc-source-use-index-marks="false"', $markdown);
        $t->contains('[ODT source packet](#odt-source-packet)', $markdown);
        $t->contains('<div id="source-navigation" class="odf-table-of-contents odf-protected-table-of-contents" data-odf-toc-name="Source Navigation"', $blocksHtml);
        $t->contains('data-odf-toc-source-style-count="1"', $blocksHtml);
        $t->contains('<div class="odf-index-title" data-odf-index-title="true"><p>Contents</p></div>', $blocksHtml);
        $t->contains('<a href="#odt-source-packet">ODT source packet</a>', $blocksHtml);
    },
    'maps ODT generated indexes beyond table of contents into review div metadata' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithGeneratedIndexes = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:illustration-index text:name="Figure Review" text:style-name="IllustrationIndex" text:protected="true" text:protection-key="figure-key" text:protection-key-digest-algorithm="urn:odf:sha256">
        <text:illustration-index-source text:caption-sequence-name="Illustration" text:use-caption="true">
          <text:index-title-template text:style-name="FigureTitle">Figures</text:index-title-template>
          <text:illustration-index-entry-template text:style-name="FigureEntry">
            <text:index-entry-link-start/>
            <text:index-entry-chapter/>
            <text:index-entry-text/>
            <text:index-entry-tab-stop style:type="right" style:position="16cm" style:leader-char="."/>
            <text:index-entry-page-number/>
            <text:index-entry-link-end/>
          </text:illustration-index-entry-template>
        </text:illustration-index-source>
        <text:index-title text:name="Illustrations">
          <text:p>Figures</text:p>
        </text:index-title>
        <text:index-body>
          <text:p><text:a xlink:href="#source-hero-seq">Figure 1</text:a><text:tab/>2</text:p>
        </text:index-body>
      </text:illustration-index>
      <text:alphabetical-index text:name="Glossary Terms" text:style-name="AlphabeticalIndex">
        <text:alphabetical-index-source text:main-entry-style-name="MainTerm" text:ignore-case="true" text:alphabetical-separators="true" text:combine-entries="true" text:combine-entries-with-dash="false" text:sort-algorithm="alphanumeric">
          <text:alphabetical-index-entry-template text:outline-level="1" text:style-name="GlossaryEntry">
            <text:index-entry-text/>
            <text:index-entry-page-number/>
          </text:alphabetical-index-entry-template>
        </text:alphabetical-index-source>
        <text:index-title text:name="Glossary">
          <text:p>Glossary</text:p>
        </text:index-title>
        <text:index-body>
          <text:p>Migration 5</text:p>
        </text:index-body>
      </text:alphabetical-index>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithGeneratedIndexes));
        $blocks = $result['document']->children;

        $t->same(2, count($blocks));

        $illustration = $blocks[0];
        $t->same('div', $illustration->type);
        $t->same('figure-review', $illustration->attr('id'));
        $t->same(['odf-generated-index', 'odf-illustration-index', 'odf-protected-generated-index'], $illustration->attr('classes'));
        $t->same('illustration', $illustration->attr('generatedIndexType'));
        $t->same('illustration-index', $illustration->attr('generatedIndexElement'));
        $t->same('Figure Review', $illustration->attr('generatedIndexName'));
        $t->same('IllustrationIndex', $illustration->attr('styleName'));
        $t->same(true, $illustration->attr('protected'));
        $t->same(true, $illustration->attr('protectionKeyPresent'));
        $t->same('urn:odf:sha256', $illustration->attr('protectionKeyDigestAlgorithm'));

        $source = $illustration->attr('generatedIndexSource');
        $t->same('illustration-index-source', $source['element']);
        $t->same('Illustration', $source['captionSequenceName']);
        $t->same(true, $source['useCaption']);
        $t->same('title', $source['templates'][0]['type']);
        $t->same('index-title-template', $source['templates'][0]['element']);
        $t->same('FigureTitle', $source['templates'][0]['styleName']);
        $t->same('entry', $source['templates'][1]['type']);
        $t->same('illustration-index-entry-template', $source['templates'][1]['element']);
        $t->same('FigureEntry', $source['templates'][1]['styleName']);
        $t->same(['index-entry-link-start', 'index-entry-chapter', 'index-entry-text', 'index-entry-tab-stop', 'index-entry-page-number', 'index-entry-link-end'], array_column($source['templates'][1]['components'], 'type'));
        $t->same('right', $source['templates'][1]['components'][3]['tabStopType']);
        $t->same('16cm', $source['templates'][1]['components'][3]['tabStopPosition']);
        $t->same('.', $source['templates'][1]['components'][3]['leaderChar']);

        $attributes = $illustration->attr('attributes');
        $t->same('illustration', $attributes['data-odf-index-type']);
        $t->same('illustration-index', $attributes['data-odf-index-element']);
        $t->same('Figure Review', $attributes['data-odf-index-name']);
        $t->same('true', $attributes['data-odf-index-protected']);
        $t->same('true', $attributes['data-odf-index-source-use-caption']);
        $t->same('Illustration', $attributes['data-odf-index-source-caption-sequence-name']);
        $t->same('2', $attributes['data-odf-index-template-count']);

        $title = $illustration->children[0];
        $body = $illustration->children[1];
        $t->same(['odf-index-title'], $title->attr('classes'));
        $t->same('Figures', $title->children[0]->attr('text'));
        $t->same(['odf-index-body'], $body->attr('classes'));
        $t->same('#source-hero-seq', $body->children[0]->children[0]->attr('url'));
        $t->same('Figure 1', $body->children[0]->children[0]->children[0]->attr('text'));

        $alphabetical = $blocks[1];
        $t->same(['odf-generated-index', 'odf-alphabetical-index'], $alphabetical->attr('classes'));
        $t->same('alphabetical', $alphabetical->attr('generatedIndexType'));
        $alphabeticalSource = $alphabetical->attr('generatedIndexSource');
        $t->same('alphabetical-index-source', $alphabeticalSource['element']);
        $t->same('MainTerm', $alphabeticalSource['mainEntryStyleName']);
        $t->same(true, $alphabeticalSource['ignoreCase']);
        $t->same(true, $alphabeticalSource['alphabeticalSeparators']);
        $t->same(true, $alphabeticalSource['combineEntries']);
        $t->same(false, $alphabeticalSource['combineEntriesWithDash']);
        $t->same('alphanumeric', $alphabeticalSource['sortAlgorithm']);
        $t->same(1, $alphabeticalSource['templates'][0]['outlineLevel']);
        $t->same('GlossaryEntry', $alphabeticalSource['templates'][0]['styleName']);
        $t->same('Glossary', $alphabetical->children[0]->children[0]->attr('text'));
        $t->same('Migration 5', $alphabetical->children[1]->children[0]->attr('text'));

        $t->same(2, $result['importReport']['content']['generatedIndexCount']);
        $t->same(0, $result['importReport']['content']['tableOfContentsCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('::: {#figure-review .odf-generated-index .odf-illustration-index .odf-protected-generated-index data-odf-index-type="illustration"', $markdown);
        $t->contains('data-odf-index-source-caption-sequence-name="Illustration"', $markdown);
        $t->contains('[Figure 1](#source-hero-seq)', $markdown);
        $t->contains('<div id="figure-review" class="odf-generated-index odf-illustration-index odf-protected-generated-index" data-odf-index-type="illustration"', $blocksHtml);
        $t->contains('data-odf-index-source-use-caption="true"', $blocksHtml);
        $t->contains('<a href="#source-hero-seq">Figure 1</a>', $blocksHtml);
        $t->contains('<div id="glossary-terms" class="odf-generated-index odf-alphabetical-index" data-odf-index-type="alphabetical"', $blocksHtml);
    },
    'maps ODT inline index marks into review spans' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $contentWithInlineIndexMarks = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Navigation <text:toc-mark text:string-value="ODT source packet" text:outline-level="1"/> term <text:alphabetical-index-mark-start text:id="idx-claim" text:string-value="source claim" text:key1="Migration" text:key2="ODT" text:main-entry="true"/>source claim<text:alphabetical-index-mark-end text:id="idx-claim"/> and <text:user-index-mark text:index-name="Reviewer Terms" text:string-value="Data Liberation"/>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage($contentWithInlineIndexMarks));
        $paragraph = $result['document']->children[0];
        $tocMark = $paragraph->children[1];
        $alphabeticalMark = $paragraph->children[3];
        $userMark = $paragraph->children[5];

        $t->same('Navigation ODT source packet term source claim and Data Liberation.', $paragraph->attr('text'));
        $t->same('span', $tocMark->type);
        $t->same(['odf-index-mark', 'odf-index-mark-toc'], $tocMark->attr('classes'));
        $t->same('toc', $tocMark->attr('indexMarkType'));
        $t->same('toc-mark', $tocMark->attr('indexMarkElement'));
        $t->same('ODT source packet', $tocMark->children[0]->attr('text'));
        $t->same('ODT source packet', $tocMark->attr('indexMarkMetadata')['stringValue']);
        $t->same(1, $tocMark->attr('indexMarkMetadata')['outlineLevel']);

        $t->same('span', $alphabeticalMark->type);
        $t->same(['odf-index-mark', 'odf-index-mark-alphabetical'], $alphabeticalMark->attr('classes'));
        $t->same('alphabetical', $alphabeticalMark->attr('indexMarkType'));
        $t->same('alphabetical-index-mark-start', $alphabeticalMark->attr('indexMarkElement'));
        $t->same('source claim', $alphabeticalMark->children[0]->attr('text'));
        $t->same('idx-claim', $alphabeticalMark->attr('indexMarkMetadata')['id']);
        $t->same('Migration', $alphabeticalMark->attr('indexMarkMetadata')['key1']);
        $t->same('ODT', $alphabeticalMark->attr('indexMarkMetadata')['key2']);
        $t->same(true, $alphabeticalMark->attr('indexMarkMetadata')['mainEntry']);

        $t->same('span', $userMark->type);
        $t->same(['odf-index-mark', 'odf-index-mark-user'], $userMark->attr('classes'));
        $t->same('user', $userMark->attr('indexMarkType'));
        $t->same('Reviewer Terms', $userMark->attr('indexMarkMetadata')['indexName']);
        $t->same('Data Liberation', $userMark->children[0]->attr('text'));
        $t->same(3, $result['importReport']['content']['indexMarkCount']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[ODT source packet]{.odf-index-mark .odf-index-mark-toc data-odf-index-mark-type="toc"', $markdown);
        $t->contains('[source claim]{.odf-index-mark .odf-index-mark-alphabetical data-odf-index-mark-type="alphabetical"', $markdown);
        $t->contains('data-odf-index-mark-main-entry="true"', $markdown);
        $t->contains('<span class="odf-index-mark odf-index-mark-toc" data-odf-index-mark-type="toc" data-odf-index-mark-element="toc-mark" data-odf-index-mark-string-value="ODT source packet" data-odf-index-mark-outline-level="1">ODT source packet</span>', $blocksHtml);
        $t->contains('<span class="odf-index-mark odf-index-mark-alphabetical" data-odf-index-mark-type="alphabetical" data-odf-index-mark-element="alphabetical-index-mark-start" data-odf-index-mark-id="idx-claim" data-odf-index-mark-string-value="source claim" data-odf-index-mark-key1="Migration" data-odf-index-mark-key2="ODT" data-odf-index-mark-main-entry="true">source claim</span>', $blocksHtml);
        $t->contains('<span class="odf-index-mark odf-index-mark-user" data-odf-index-mark-type="user" data-odf-index-mark-element="user-index-mark" data-odf-index-mark-index-name="Reviewer Terms" data-odf-index-mark-string-value="Data Liberation">Data Liberation</span>', $blocksHtml);
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
    'maps ODT chart draw objects into embedded object review placeholders' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithChartObjects = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20Missing/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
</manifest:manifest>
XML;
        $contentWithChartObjects = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Inline chart <draw:frame draw:name="Inline chart"><svg:desc>Revenue chart placeholder</svg:desc><draw:object xlink:href="./Object%20Chart"/></draw:frame> queued for review.</text:p>
      <draw:frame draw:name="Missing chart">
        <svg:title>Missing chart placeholder</svg:title>
        <draw:object xlink:href="Object%20Missing"/>
      </draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $chartObjectXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0">
  <office:body>
    <office:chart>
      <chart:chart chart:class="chart:bar"/>
    </office:chart>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithChartObjects,
            $manifestWithChartObjects,
            null,
            null,
            [
                ['name' => 'Object Chart/content.xml', 'data' => $chartObjectXml],
            ]
        ));

        $blocks = $result['document']->children;
        $paragraph = $blocks[0];
        $inlineChart = $paragraph->children[1];
        $missingChart = $blocks[1];

        $t->same(2, count($blocks));
        $t->same('Inline chart Revenue chart placeholder queued for review.', $paragraph->attr('text'));
        $t->same('span', $inlineChart->type);
        $t->same(['odf-embedded-object', 'odf-object-chart'], $inlineChart->attr('classes'));
        $t->same('chart', $inlineChart->attr('objectType'));
        $t->same('./Object%20Chart', $inlineChart->attr('href'));
        $t->same('Object Chart', $inlineChart->attr('objectPath'));
        $t->same('Object Chart/', $inlineChart->attr('sourcePart'));
        $t->same('application/vnd.oasis.opendocument.chart', $inlineChart->attr('mediaType'));
        $t->same(true, $inlineChart->attr('exists'));
        $t->same(false, $inlineChart->attr('canExposeBytes'));
        $t->same(['Object Chart/content.xml'], $inlineChart->attr('containedParts'));
        $t->same(1, $inlineChart->attr('containedPartCount'));
        $t->same(strlen($chartObjectXml), $inlineChart->attr('containedByteLength'));
        $t->same('Revenue chart placeholder', $inlineChart->children[0]->attr('text'));

        $t->same('div', $missingChart->type);
        $t->same(['odf-embedded-object', 'odf-object-chart'], $missingChart->attr('classes'));
        $t->same('chart', $missingChart->attr('objectType'));
        $t->same('Object Missing', $missingChart->attr('objectPath'));
        $t->same('Object Missing/', $missingChart->attr('sourcePart'));
        $t->same(false, $missingChart->attr('exists'));
        $t->same(0, $missingChart->attr('containedPartCount'));
        $t->same('Missing chart placeholder', $missingChart->children[0]->children[0]->attr('text'));

        $t->same(2, $result['importReport']['content']['embeddedObjectCount']);
        $t->same(1, $result['importReport']['content']['missingEmbeddedObjectCount']);
        $t->same(0, count($result['media']));

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Revenue chart placeholder]{.odf-embedded-object .odf-object-chart data-odf-object-type="chart" data-odf-object-href="./Object%20Chart" data-odf-object-path="Object Chart" data-odf-object-source-part="Object Chart/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="' . strlen($chartObjectXml) . '" data-odf-object-can-expose-bytes="false"}', $markdown);
        $t->contains('::: {.odf-embedded-object .odf-object-chart data-odf-object-type="chart" data-odf-object-href="Object%20Missing" data-odf-object-path="Object Missing" data-odf-object-source-part="Object Missing/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="false" data-odf-object-contained-part-count="0" data-odf-object-can-expose-bytes="false"}', $markdown);
        $t->contains('<span class="odf-embedded-object odf-object-chart" data-odf-object-type="chart" data-odf-object-href="./Object%20Chart" data-odf-object-path="Object Chart" data-odf-object-source-part="Object Chart/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="' . strlen($chartObjectXml) . '" data-odf-object-can-expose-bytes="false">Revenue chart placeholder</span>', $blocksHtml);
        $t->contains('<div class="odf-embedded-object odf-object-chart" data-odf-object-type="chart" data-odf-object-href="Object%20Missing" data-odf-object-path="Object Missing" data-odf-object-source-part="Object Missing/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="false" data-odf-object-contained-part-count="0" data-odf-object-can-expose-bytes="false"><p>Missing chart placeholder</p></div>', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'chart:bar'), 'Opaque chart object XML must not render in WordPress output');
    },
    'maps ODT object-ole frames into embedded object review placeholders' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithOleObjects = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object%20OLE/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/>
  <manifest:file-entry manifest:full-path="Object%20OLE/oleObject.bin" manifest:media-type="application/vnd.openxmlformats-officedocument.oleObject" manifest:size="9"/>
  <manifest:file-entry manifest:full-path="Object%20Missing/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
</manifest:manifest>
XML;
        $contentWithOleObjects = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Inline object <draw:frame draw:name="Inline spreadsheet"><draw:object-ole xlink:href="./Object%20OLE"/></draw:frame> queued.</text:p>
      <draw:frame draw:name="Missing object">
        <svg:title>Linked chart</svg:title>
        <draw:object-ole xlink:href="Object%20Missing"/>
      </draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithOleObjects,
            $manifestWithOleObjects,
            null,
            null,
            [
                ['name' => 'Object OLE/oleObject.bin', 'data' => 'OLEBYTES!'],
            ]
        ));

        $blocks = $result['document']->children;
        $paragraph = $blocks[0];
        $inlineObject = $paragraph->children[1];
        $blockObject = $blocks[1];

        $t->same(2, count($blocks));
        $t->same('Inline object Inline spreadsheet queued.', $paragraph->attr('text'));
        $t->same('span', $inlineObject->type);
        $t->same(['odf-embedded-object', 'odf-object-ole'], $inlineObject->attr('classes'));
        $t->same('ole', $inlineObject->attr('objectType'));
        $t->same('./Object%20OLE', $inlineObject->attr('href'));
        $t->same('Object OLE', $inlineObject->attr('objectPath'));
        $t->same('Object OLE/', $inlineObject->attr('sourcePart'));
        $t->same('application/vnd.oasis.opendocument.spreadsheet', $inlineObject->attr('mediaType'));
        $t->same(true, $inlineObject->attr('exists'));
        $t->same(false, $inlineObject->attr('canExposeBytes'));
        $t->same(['Object OLE/oleObject.bin'], $inlineObject->attr('containedParts'));
        $t->same(1, $inlineObject->attr('containedPartCount'));
        $t->same(9, $inlineObject->attr('containedByteLength'));
        $t->same('Inline spreadsheet', $inlineObject->children[0]->attr('text'));

        $t->same('div', $blockObject->type);
        $t->same(['odf-embedded-object', 'odf-object-ole'], $blockObject->attr('classes'));
        $t->same('Object Missing', $blockObject->attr('objectPath'));
        $t->same('Object Missing/', $blockObject->attr('sourcePart'));
        $t->same(false, $blockObject->attr('exists'));
        $t->same('Linked chart', $blockObject->children[0]->children[0]->attr('text'));

        $mediaByPart = [];
        foreach ($result['media'] as $media) {
            $mediaByPart[$media['part']] = $media;
        }
        $t->same(2, $result['importReport']['content']['embeddedObjectCount']);
        $t->same(1, $result['importReport']['content']['missingEmbeddedObjectCount']);
        $t->same('application/vnd.openxmlformats-officedocument.oleObject', $mediaByPart['Object OLE/oleObject.bin']['mediaType']);
        $t->same(9, $mediaByPart['Object OLE/oleObject.bin']['byteLength']);

        $markdown = (new MarkdownWriter())->write($result['document']);
        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('[Inline spreadsheet]{.odf-embedded-object .odf-object-ole data-odf-object-type="ole" data-odf-object-href="./Object%20OLE" data-odf-object-path="Object OLE" data-odf-object-source-part="Object OLE/" data-odf-object-media-type="application/vnd.oasis.opendocument.spreadsheet" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="9" data-odf-object-can-expose-bytes="false"}', $markdown);
        $t->contains('::: {.odf-embedded-object .odf-object-ole data-odf-object-type="ole" data-odf-object-href="Object%20Missing" data-odf-object-path="Object Missing" data-odf-object-source-part="Object Missing/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="false" data-odf-object-contained-part-count="0" data-odf-object-can-expose-bytes="false"}', $markdown);
        $t->contains('<span class="odf-embedded-object odf-object-ole" data-odf-object-type="ole" data-odf-object-href="./Object%20OLE" data-odf-object-path="Object OLE" data-odf-object-source-part="Object OLE/" data-odf-object-media-type="application/vnd.oasis.opendocument.spreadsheet" data-odf-object-exists="true" data-odf-object-contained-part-count="1" data-odf-object-contained-byte-length="9" data-odf-object-can-expose-bytes="false">Inline spreadsheet</span>', $blocksHtml);
        $t->contains('<div class="odf-embedded-object odf-object-ole" data-odf-object-type="ole" data-odf-object-href="Object%20Missing" data-odf-object-path="Object Missing" data-odf-object-source-part="Object Missing/" data-odf-object-media-type="application/vnd.oasis.opendocument.chart" data-odf-object-exists="false" data-odf-object-contained-part-count="0" data-odf-object-can-expose-bytes="false"><p>Linked chart</p></div>', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'OLEBYTES!'), 'Opaque OLE bytes must not render in WordPress output');
    },
    'normalizes ODT URI encoded package part references for media and objects' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $manifestWithEncodedParts = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/source%20hero.png" manifest:media-type="image/png" manifest:size="8"/>
  <manifest:file-entry manifest:full-path="Object%201/" manifest:media-type="application/vnd.oasis.opendocument.formula"/>
  <manifest:file-entry manifest:full-path="Object%201/content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;
        $contentWithEncodedReferences = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:p>Encoded package image <draw:frame draw:name="Encoded hero"><draw:image xlink:href="./Pictures/source%20hero.png"><svg:title>Encoded hero</svg:title><svg:desc>Decoded source hero</svg:desc></draw:image></draw:frame> and formula <draw:frame draw:name="Encoded formula"><draw:object xlink:href="./Object%201"/></draw:frame>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $mathObject = <<<'XML'
<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">
  <mrow><mi>y</mi><mo>=</mo><mn>2</mn></mrow>
</math>
XML;

        $result = (new OdfReader())->readPackage($buildOdtPackage(
            $contentWithEncodedReferences,
            $manifestWithEncodedParts,
            null,
            null,
            [
                ['name' => 'Pictures/source hero.png', 'data' => 'PNGDATA!'],
                ['name' => 'Object 1/content.xml', 'data' => $mathObject],
            ]
        ));
        $manifestByPath = [];
        foreach ($result['manifest'] as $item) {
            $manifestByPath[$item['fullPath']] = $item;
        }

        $paragraph = $result['document']->children[0];
        $image = $paragraph->children[1];
        $math = $paragraph->children[3];

        $t->same('Pictures/source hero.png', $manifestByPath['Pictures/source%20hero.png']['part']);
        $t->same(true, $manifestByPath['Pictures/source%20hero.png']['exists']);
        $t->same(8, $manifestByPath['Pictures/source%20hero.png']['byteLength']);
        $t->same('Object 1/content.xml', $manifestByPath['Object%201/content.xml']['part']);
        $t->same(true, $manifestByPath['Object%201/content.xml']['exists']);
        $t->same(1, count($result['media']));
        $t->same('Pictures/source hero.png', $result['media'][0]['part']);
        $t->same(8, $result['media'][0]['byteLength']);

        $t->same('Encoded package image Decoded source hero and formula y=2.', $paragraph->attr('text'));
        $t->same('image', $image->type);
        $t->same('./Pictures/source%20hero.png', $image->attr('url'));
        $t->same('Pictures/source hero.png', $image->attr('sourcePart'));
        $t->same(8, $image->attr('bytes'));
        $t->same('math', $math->type);
        $t->same('Object 1', $math->attr('objectPath'));
        $t->same('Object 1/content.xml', $math->attr('sourcePart'));
        $t->same('y=2', $math->attr('text'));
        $t->same(1, $result['importReport']['content']['mathCount']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('<img src="./Pictures/source%20hero.png" alt="Decoded source hero" title="Encoded hero"/>', $blocksHtml);
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
    'checks ODT mimetype placement by local ZIP header order' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml, $stylesXml, $metaXml): void {
        $parts = [
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
        ];

        $result = (new OdfReader())->readPackage($buildZipPackageWithCentralDirectoryOrder($parts, [
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'mimetype',
        ]));

        $t->same('ODT Import Packet', $result['metadata']['title']);
        $t->same('odt', $result['document']->attr('source'));
        $t->same('Pictures/hero.png', $result['media'][0]['part']);
    },
    'rejects malformed ODT packages before conversion handoff' => static function (TestRunner $t) use ($buildOdtPackage, $buildZipPackageWithCentralDirectoryOrder, $manifestXml, $contentXml): void {
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

        $encodedUnsafeManifest = str_replace('Pictures/hero.png', 'Pictures/%2e%2e/secret.png', $manifestXml);
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage($buildOdtPackage(null, $encodedUnsafeManifest)));

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildZipPackageWithCentralDirectoryOrder([
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml],
        ], [
            'mimetype',
            'META-INF/manifest.xml',
            'content.xml',
        ])));

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
        ])));
    },
];
