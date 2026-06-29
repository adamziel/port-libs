<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\OpenDocumentReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
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
    <style:style style:name="Heading_20_1" style:family="paragraph" style:display-name="Heading 1"/>
    <style:style style:name="Heading_20_2" style:family="paragraph" style:display-name="Heading 2"/>
    <style:style style:name="ReviewHeading" style:family="paragraph" style:display-name="Migration Review" style:parent-style-name="Heading_20_2"/>
    <style:style style:name="StrongEmphasis" style:family="text">
      <style:text-properties fo:font-weight="bold" fo:font-style="italic"/>
    </style:style>
    <style:style style:name="UnderlineMark" style:family="text">
      <style:text-properties style:text-underline-style="solid"/>
    </style:style>
    <style:style style:name="SupMark" style:family="text">
      <style:text-properties style:text-position="super 58%"/>
    </style:style>
    <text:list-style style:name="Checklist">
      <text:list-level-style-bullet text:level="1" text:bullet-char="*"/>
    </text:list-style>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="a" text:start-value="3" style:num-suffix=")"/>
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
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0">
  <office:body>
    <office:text>
      <text:h text:outline-level="1" text:style-name="Heading_20_1">ODT import packet</text:h>
      <text:p text:style-name="ReviewHeading">Review checklist</text:p>
      <text:p>Reviewer <text:span text:style-name="StrongEmphasis">summary</text:span><text:s text:c="2"/>keeps <text:a xlink:href="https://example.test/source.odt?post=42">source link</text:a><text:line-break/>and <text:span text:style-name="SupMark">ODT</text:span> notes<text:note text:id="ftn1" text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>ODT footnote source audit.</text:p></text:note-body></text:note></text:p>
      <text:list text:style-name="Checklist">
        <text:list-item><text:p>Confirm media map</text:p></text:list-item>
        <text:list-item><text:p>Preserve footnotes</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="ReviewSteps" text:start-value="3">
        <text:list-item><text:p>Legal review</text:p></text:list-item>
        <text:list-item><text:p>Publish packet</text:p></text:list-item>
      </text:list>
      <text:p><draw:frame draw:name="Hero image"><svg:title>ODT hero title</svg:title><svg:desc>ODT hero alt</svg:desc><draw:image xlink:href="Pictures/hero.png" xlink:type="simple"/></draw:frame></text:p>
      <table:table table:name="ImportStatus">
        <table:table-row>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
          <table:table-cell table:number-columns-spanned="2"><text:p>Needs media review</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell table:number-columns-repeated="2"><text:p>Owner</text:p></table:table-cell>
          <table:table-cell><text:p>Migration team</text:p></table:table-cell>
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
    <dc:title>WordPress ODT packet</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <dc:description>Source ODT for Data Liberation review</dc:description>
    <dc:date>2026-06-04T05:39:23Z</dc:date>
    <meta:creation-date>2026-06-04T05:00:00Z</meta:creation-date>
    <meta:initial-creator>Author Team</meta:initial-creator>
    <meta:generator>LibreOffice/ODF fixture</meta:generator>
    <meta:keyword>wordpress</meta:keyword>
    <meta:keyword>odt</meta:keyword>
  </office:meta>
</office:document-meta>
XML;

$buildOdtPackage = static function () use ($manifestXml, $stylesXml, $contentXml, $metaXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OpenDocumentReader::ODT_MEDIA_TYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
        ['name' => 'content.xml', 'data' => $contentXml],
        ['name' => 'styles.xml', 'data' => $stylesXml],
        ['name' => 'meta.xml', 'data' => $metaXml],
        ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA'],
    ]);
};

return [
    'reads ODT manifest metadata headings paragraphs and inline markup' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $result = (new OpenDocumentReader())->readPackage($buildOdtPackage());
        $document = $result['document'];

        $t->same('content.xml', $result['contentPart']);
        $t->same(OpenDocumentReader::ODT_MEDIA_TYPE, $result['manifest']['/']['mediaType']);
        $t->same('image/png', $result['manifest']['Pictures/hero.png']['mediaType']);
        $t->same('WordPress ODT packet', $result['metadata']['title']);
        $t->same('Migration Desk', $result['metadata']['creator']);
        $t->same('Source ODT for Data Liberation review', $result['metadata']['description']);
        $t->same('2026-06-04T05:00:00Z', $result['metadata']['created']);
        $t->same('2026-06-04T05:39:23Z', $result['metadata']['modified']);
        $t->same('Author Team', $result['metadata']['initialCreator']);
        $t->same(['wordpress', 'odt'], $result['metadata']['keywords']);

        $t->same('document', $document->type);
        $t->same('odt', $document->attr('sourceFormat'));
        $t->same(7, count($document->children));

        $heading = $document->children[0];
        $t->same('heading', $heading->type);
        $t->same(1, $heading->attr('level'));
        $t->same('ODT import packet', $heading->attr('text'));
        $t->same('odt-import-packet', $heading->attr('id'));

        $styleHeading = $document->children[1];
        $t->same('heading', $styleHeading->type);
        $t->same(2, $styleHeading->attr('level'));
        $t->same('ReviewHeading', $styleHeading->attr('style'));
        $t->same('Review checklist', $styleHeading->attr('text'));

        $paragraph = $document->children[2];
        $t->same('paragraph', $paragraph->type);
        $t->same('Reviewer ', $paragraph->children[0]->attr('text'));
        $t->same('strong', $paragraph->children[1]->type);
        $t->same('emph', $paragraph->children[1]->children[0]->type);
        $t->same('summary', $paragraph->children[1]->children[0]->children[0]->attr('text'));
        $t->same('  keeps ', $paragraph->children[2]->attr('text'));
        $t->same('link', $paragraph->children[3]->type);
        $t->same('https://example.test/source.odt?post=42', $paragraph->children[3]->attr('url'));
        $t->same('linebreak', $paragraph->children[4]->type);
        $t->same('superscript', $paragraph->children[6]->type);
        $t->same('ODT', $paragraph->children[6]->children[0]->attr('text'));
        $t->same('note', $paragraph->children[8]->type);
        $t->same('ftn1', $paragraph->children[8]->attr('id'));
        $t->same('1', $paragraph->children[8]->attr('citation'));
        $t->same('ODT footnote source audit.', $paragraph->children[8]->children[0]->children[0]->attr('text'));
    },

    'maps ODT lists packaged images and tables into existing AST nodes' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $document = (new OpenDocumentReader())->readDocument($buildOdtPackage());

        $bulletList = $document->children[3];
        $t->same('bullet_list', $bulletList->type);
        $t->same('odt', $bulletList->attr('sourceFormat'));
        $t->same('Checklist', $bulletList->attr('styleName'));
        $t->same('bullet', $bulletList->attr('format'));
        $t->same(2, count($bulletList->children));
        $t->same('Confirm media map', $bulletList->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Preserve footnotes', $bulletList->children[1]->children[0]->children[0]->attr('text'));

        $orderedList = $document->children[4];
        $t->same('ordered_list', $orderedList->type);
        $t->same('ReviewSteps', $orderedList->attr('styleName'));
        $t->same('lower_alpha', $orderedList->attr('style'));
        $t->same('one_paren', $orderedList->attr('delimiter'));
        $t->same(3, $orderedList->attr('start'));
        $t->same('Legal review', $orderedList->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Publish packet', $orderedList->children[1]->children[0]->children[0]->attr('text'));

        $image = $document->children[5]->children[0];
        $t->same('image', $image->type);
        $t->same('Pictures/hero.png', $image->attr('url'));
        $t->same('/Pictures/hero.png', $image->attr('sourcePart'));
        $t->same('ODT hero alt', $image->attr('alt'));
        $t->same('ODT hero title', $image->attr('title'));
        $t->same('image/png', $image->attr('mediaType'));
        $t->same(7, $image->attr('bytes'));

        $table = $document->children[6];
        $t->same('table', $table->type);
        $t->same('ImportStatus', $table->attr('name'));
        $body = $table->children[0];
        $t->same(2, count($body->children));
        $t->same('Status', $body->children[0]->children[0]->attr('text'));
        $t->same('Needs media review', $body->children[0]->children[1]->attr('text'));
        $t->same(2, $body->children[0]->children[1]->attr('colspan'));
        $t->same('Owner', $body->children[1]->children[0]->attr('text'));
        $t->same('Owner', $body->children[1]->children[1]->attr('text'));
        $t->same('Migration team', $body->children[1]->children[2]->attr('text'));
    },

    'renders ODT reader AST through Markdown and WordPress writers' => static function (TestRunner $t) use ($buildOdtPackage): void {
        $document = (new OpenDocumentReader())->readDocument($buildOdtPackage());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('# ODT import packet', $markdown);
        $t->contains('## Review checklist', $markdown);
        $t->contains('Reviewer ***summary***  keeps [source link](https://example.test/source.odt?post=42)\\', $markdown);
        $t->contains('and ^ODT^ notes[^1]', $markdown);
        $t->contains('- Confirm media map', $markdown);
        $t->contains('c)  Legal review', $markdown);
        $t->contains('![ODT hero alt](Pictures/hero.png "ODT hero title")', $markdown);
        $t->contains('| Status | Needs media review', $markdown);
        $t->contains('| Owner  | Owner    | Migration team |', $markdown);
        $t->contains('[^1]: ODT footnote source audit.', $markdown);

        $t->contains('<h1 id="odt-import-packet">ODT import packet</h1>', $blocks);
        $t->contains('<h2 id="review-checklist">Review checklist</h2>', $blocks);
        $t->contains('<strong><em>summary</em></strong>', $blocks);
        $t->contains('<a href="https://example.test/source.odt?post=42">source link</a>', $blocks);
        $t->contains('<br/>and <sup>ODT</sup> notes', $blocks);
        $t->contains('<ul><li>Confirm media map</li><li>Preserve footnotes</li></ul>', $blocks);
        $t->contains('<ol start="3" type="a"><li>Legal review</li><li>Publish packet</li></ol>', $blocks);
        $t->contains('<img src="Pictures/hero.png" alt="ODT hero alt" title="ODT hero title"/>', $blocks);
        $t->contains('<td colspan="2">', $blocks);
        $t->contains('ODT footnote source audit.', $blocks);
    },

    'rejects malformed ODT packages without shelling out to office tooling' => static function (TestRunner $t) use ($manifestXml, $contentXml): void {
        $reader = new OpenDocumentReader();

        $t->throws(\RuntimeException::class, static fn (): AstNode => $reader->readDocument(ZipPackage::fromParts([
            ['name' => 'content.xml', 'data' => $contentXml],
        ])));

        $t->throws(\RuntimeException::class, static fn (): AstNode => $reader->readDocument(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/vnd.oasis.opendocument.spreadsheet', 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
        ])));

        $t->throws(\RuntimeException::class, static fn (): AstNode => $reader->readDocument(ZipPackage::fromParts([
            ['name' => 'META-INF/manifest.xml', 'data' => str_replace(OpenDocumentReader::ODT_MEDIA_TYPE, 'application/vnd.oasis.opendocument.presentation', $manifestXml)],
            ['name' => 'content.xml', 'data' => $contentXml],
        ])));

        $t->throws(\InvalidArgumentException::class, static fn (): AstNode => $reader->readDocument(ZipPackage::fromParts([
            ['name' => 'META-INF/manifest.xml', 'data' => str_replace('content.xml', '../evil.xml', $manifestXml)],
            ['name' => 'content.xml', 'data' => $contentXml],
        ])));

        $t->throws(\InvalidArgumentException::class, static fn (): AstNode => $reader->readDocument(ZipPackage::fromParts([
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => '<document/>'],
        ])));
    },
];
