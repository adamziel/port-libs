<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdtReader;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="Centered" style:family="paragraph">
      <style:paragraph-properties fo:text-align="center"/>
    </style:style>
    <style:style style:name="Emphasis" style:family="text">
      <style:text-properties fo:font-style="italic"/>
    </style:style>
    <style:style style:name="StrongEmphasis" style:family="text" style:parent-style-name="Emphasis">
      <style:text-properties fo:font-weight="bold"/>
    </style:style>
    <text:list-style style:name="ReviewSteps">
      <text:list-level-style-number text:level="1" style:num-format="a" text:start-value="3"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:h text:outline-level="1" text:style-name="Heading_20_1">ODT source packet</text:h>
      <text:p text:style-name="Centered">Reviewer<text:s text:c="2"/><text:span text:style-name="StrongEmphasis">summary</text:span><text:s/><text:a xlink:href="https://example.test/source" office:title="Source packet">source link</text:a><text:line-break/>next line<text:note text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>Footnote source audit.</text:p></text:note-body></text:note><office:annotation><dc:creator>Migration Reviewer</dc:creator><dc:date>2026-06-04T10:00:00Z</dc:date><text:p>Check imported source.</text:p></office:annotation></text:p>
      <text:section text:name="ReviewSection"><text:p>Scoped paragraph.</text:p></text:section>
      <text:list text:style-name="ReviewSteps" text:start-value="3">
        <text:list-item><text:p>Confirm media map</text:p></text:list-item>
        <text:list-item><text:p>Publish packet</text:p></text:list-item>
      </text:list>
      <table:table table:name="Review matrix">
        <table:table-row>
          <table:table-cell table:number-columns-spanned="2" table:number-rows-spanned="2"><text:p>Scope</text:p></table:table-cell>
          <table:table-cell><text:p>Status</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:covered-table-cell/>
          <table:covered-table-cell/>
          <table:table-cell><text:p>Ready</text:p></table:table-cell>
        </table:table-row>
        <table:table-row>
          <table:table-cell table:number-columns-repeated="2"><text:p>Owner</text:p></table:table-cell>
          <table:table-cell><text:p>Migration desk</text:p></table:table-cell>
        </table:table-row>
      </table:table>
      <text:p><draw:frame draw:name="Hero image" svg:width="6cm" svg:height="4cm"><draw:image xlink:href="Pictures/hero.png" xlink:type="simple"/></draw:frame></text:p>
      <text:p><draw:frame draw:name="Missing image"><draw:image xlink:href="Pictures/missing.png" xlink:type="simple"/></draw:frame></text:p>
      <draw:frame draw:name="Sidebar"><draw:text-box><text:p>Text box reminder.</text:p></draw:text-box></draw:frame>
    </office:text>
  </office:body>
</office:document-content>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"
  office:version="1.3">
  <office:meta>
    <dc:title>WordPress ODT handoff</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <dc:description>Source packet for ODT import review</dc:description>
    <meta:initial-creator>Source Editor</meta:initial-creator>
    <meta:creation-date>2026-06-04T09:30:00Z</meta:creation-date>
    <meta:editing-cycles>4</meta:editing-cycles>
  </office:meta>
</office:document-meta>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="7"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$buildPackage = static function (array $overrides = []) use ($contentXml, $stylesXml, $metaXml, $manifestXml): ZipPackage {
    $parts = array_replace([
        'mimetype' => OdtReader::ODT_MIMETYPE,
        'content.xml' => $contentXml,
        'styles.xml' => $stylesXml,
        'meta.xml' => $metaXml,
        'META-INF/manifest.xml' => $manifestXml,
        'Pictures/hero.png' => 'PNGDATA',
    ], $overrides);

    $zipParts = [];
    foreach ($parts as $name => $data) {
        if ($data === null) {
            continue;
        }

        $zipParts[] = [
            'name' => (string) $name,
            'data' => (string) $data,
            'compressionMethod' => $name === 'mimetype' ? 0 : 8,
        ];
    }

    return ZipPackage::fromParts($zipParts);
};

return [
    'reads ODT package metadata manifest and styled body blocks' => static function (TestRunner $t) use ($buildPackage): void {
        $result = (new OdtReader())->readPackage($buildPackage());
        $document = $result['document'];

        $t->same('WordPress ODT handoff', $result['metadata']['title']);
        $t->same('Migration Desk', $result['metadata']['creator']);
        $t->same('Source Editor', $result['metadata']['initialCreator']);
        $t->same(6, count($result['manifest']));
        $t->same('application/vnd.oasis.opendocument.text', $result['importReport']['mimetype']);
        $t->same('document', $document->type);
        $t->same('odt', $document->attr('sourceFormat'));
        $t->same(8, count($document->children));

        $heading = $document->children[0];
        $t->same('heading', $heading->type);
        $t->same(1, $heading->attr('level'));
        $t->same('odt-source-packet', $heading->attr('id'));

        $paragraph = $document->children[1];
        $t->same('paragraph', $paragraph->type);
        $t->same('Centered', $paragraph->attr('style'));
        $t->same(['style' => 'text-align:center'], $paragraph->attr('htmlAttributes'));
        $t->same('text', $paragraph->children[0]->type);
        $t->same('Reviewer  ', $paragraph->children[0]->attr('text'));
        $t->same('emph', $paragraph->children[1]->type);
        $t->same('strong', $paragraph->children[1]->children[0]->type);
        $t->same('summary', $paragraph->children[1]->children[0]->children[0]->attr('text'));
        $t->same('link', $paragraph->children[3]->type);
        $t->same('https://example.test/source', $paragraph->children[3]->attr('url'));
        $t->same('linebreak', $paragraph->children[4]->type);
        $t->same('note', $paragraph->children[6]->type);
        $t->same('footnote', $paragraph->children[6]->attr('sourceType'));
        $t->same('span', $paragraph->children[7]->type);
        $t->same(['odt-annotation'], $paragraph->children[7]->attr('classes'));
        $t->same('Migration Reviewer', $paragraph->children[7]->attr('attributes')['data-odt-annotation-author']);

        $section = $document->children[2];
        $t->same('div', $section->type);
        $t->same('odt-section', $section->attr('sourceFormat'));
        $t->same('ReviewSection', $section->attr('name'));
        $t->same('Scoped paragraph.', $section->children[0]->children[0]->attr('text'));
    },
    'maps ODT ordered list restarts from list styles' => static function (TestRunner $t) use ($buildPackage): void {
        $document = (new OdtReader())->readDocument($buildPackage());
        $list = $document->children[3];

        $t->same('ordered_list', $list->type);
        $t->same('ReviewSteps', $list->attr('styleName'));
        $t->same('lower_alpha', $list->attr('style'));
        $t->same(3, $list->attr('start'));
        $t->same(true, $list->attr('restart'));
        $t->same(2, count($list->children));
        $t->same('Confirm media map', $list->children[0]->children[0]->children[0]->attr('text'));

        $blocks = (new WordPressBlockWriter())->write($document);
        $t->contains('<ol start="3" type="a">', $blocks);
        $t->contains('<li>Confirm media map</li><li>Publish packet</li>', $blocks);
    },
    'maps ODT table spans repeated cells and WordPress table output' => static function (TestRunner $t) use ($buildPackage): void {
        $document = (new OdtReader())->readDocument($buildPackage());
        $table = $document->children[4];
        $rows = $table->children[0]->children;

        $t->same('table', $table->type);
        $t->same('Review matrix', $table->attr('caption'));
        $t->same(3, TableGeometry::columnCount($table));
        $t->same(3, count($rows));
        $t->same(2, $rows[0]->children[0]->attr('colspan'));
        $t->same(2, $rows[0]->children[0]->attr('rowspan'));
        $t->same('Scope', $rows[0]->children[0]->attr('text'));
        $t->same('Owner', $rows[2]->children[0]->attr('text'));
        $t->same('Owner', $rows[2]->children[1]->attr('text'));

        $blocks = (new WordPressBlockWriter())->write($document);
        $t->contains('<td colspan="2" rowspan="2">', $blocks);
        $t->contains('<figcaption class="wp-element-caption">Review matrix</figcaption>', $blocks);
        $t->contains('<td><p>Migration desk</p></td>', $blocks);
    },
    'reports ODT frame images text boxes and package media metadata' => static function (TestRunner $t) use ($buildPackage): void {
        $result = (new OdtReader())->readPackage($buildPackage());
        $document = $result['document'];
        $report = $result['importReport'];

        $heroParagraph = $document->children[5];
        $missingParagraph = $document->children[6];
        $textBox = $document->children[7];

        $t->same('image', $heroParagraph->children[0]->type);
        $t->same('Pictures/hero.png', $heroParagraph->children[0]->attr('sourcePart'));
        $t->same(true, $heroParagraph->children[0]->attr('exists'));
        $t->same('6cm', $heroParagraph->children[0]->attr('attributes')['data-odt-width']);
        $t->same('4cm', $heroParagraph->children[0]->attr('attributes')['data-odt-height']);
        $t->same('image', $missingParagraph->children[0]->type);
        $t->same(false, $missingParagraph->children[0]->attr('exists'));
        $t->same('div', $textBox->type);
        $t->same('odt-text-box', $textBox->attr('sourceFormat'));
        $t->same('Text box reminder.', $textBox->children[0]->children[0]->attr('text'));

        $t->same(2, $report['media']['count']);
        $t->same(1, $report['media']['embeddedCount']);
        $t->same(1, $report['media']['missingCount']);
        $t->same(7, $report['media']['items'][0]['bytes']);
        $t->same('image/png', $report['media']['items'][0]['mediaType']);
        $t->same(1, $report['annotations']['count']);
        $t->same(1, $report['sections']['count']);
        $t->same(1, $report['textBoxes']['count']);
        $t->same(1, $report['styles']['listCount']);
    },
    'rejects malformed or non ODT package inputs' => static function (TestRunner $t) use ($buildPackage): void {
        $reader = new OdtReader();

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildPackage(['mimetype' => 'application/zip'])));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildPackage(['content.xml' => null])));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage($buildPackage(['content.xml' => '<broken>'])));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage($buildPackage(['content.xml' => '<office:document-content xmlns:office="' . OdtReader::OFFICE_NS . '"/>'])));
    },
    'renders ODT package content as WordPress blocks without office tooling' => static function (TestRunner $t) use ($buildPackage): void {
        $document = (new OdtReader())->readDocument($buildPackage());
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<h1 id="odt-source-packet">ODT source packet</h1>', $blocks);
        $t->contains('<em><strong>summary</strong></em>', $blocks);
        $t->contains('<a href="https://example.test/source" title="Source packet">source link</a>', $blocks);
        $t->contains('<section class="footnotes" role="doc-endnotes"><ol><li id="fn-1"><p>Footnote source audit.</p>', $blocks);
        $t->contains('<img src="Pictures/hero.png" alt="Hero image" title="Hero image" data-odt-width="6cm" data-odt-height="4cm"/>', $blocks);
        $t->contains('<figcaption>Hero image</figcaption>', $blocks);
        $t->contains('<div><p>Text box reminder.</p></div>', $blocks);
    },
];
