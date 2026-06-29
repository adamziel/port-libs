<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdtReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads odt package metadata and body content into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ODT path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ODT package');
        }
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->addFromString('meta.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <dc:title>ODT Reader Demo</dc:title>
    <dc:creator>Port Libs</dc:creator>
    <dc:description>Bounded ODT reader smoke.</dc:description>
    <meta:keyword>odt</meta:keyword>
  </office:meta>
</office:document-meta>
XML);
        $zip->addFromString('styles.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="Bold" style:family="text"><style:text-properties fo:font-weight="bold"/></style:style>
    <style:style style:name="Italic" style:family="text"><style:text-properties fo:font-style="italic"/></style:style>
    <text:list-style style:name="NumberedAlpha">
      <text:list-level-style-number text:level="1" style:num-format="a" style:num-suffix=")"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML);
        $zip->addFromString('content.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:automatic-styles>
    <style:style xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" style:name="InlineStrong" style:family="text">
      <style:text-properties xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" fo:font-weight="bold"/>
    </style:style>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <text:h text:outline-level="1">ODT Reader Demo</text:h>
      <text:p>A <text:span text:style-name="Bold">bold</text:span> and <text:span text:style-name="Italic">italic</text:span> paragraph with <text:a xlink:href="https://example.test">a link</text:a>.</text:p>
      <text:list>
        <text:list-item><text:p>One</text:p></text:list-item>
        <text:list-item><text:p>Two</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="NumberedAlpha" text:start-value="3">
        <text:list-item><text:p>Alpha three</text:p></text:list-item>
        <text:list-item><text:p>Alpha four</text:p></text:list-item>
      </text:list>
      <table:table>
        <table:table-row>
          <table:table-cell><text:p>Cell A</text:p></table:table-cell>
          <table:table-cell><text:p>Cell B</text:p></table:table-cell>
        </table:table-row>
      </table:table>
      <text:p><draw:frame><draw:image xlink:href="Pictures/image.png"/></draw:frame></text:p>
    </office:text>
  </office:body>
</office:document-content>
XML);
        $zip->addFromString('Pictures/image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->close();

        try {
            $document = (new OdtReader())->readOdtFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'odt', 'blocks');
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('ODT Reader Demo', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('Bounded ODT reader smoke.', $meta['description']);
        $t->same('odt', $meta['keywords']);
        $t->true(($meta['odtTextStyleCount'] ?? 0) >= 2);
        $t->same(1, $meta['odtListStyleCount']);
        $t->true(($meta['odtPackageEntries'] ?? 0) >= 4);
        $t->same(['Pictures/image.png'], $meta['odtReferencedResources']);
        $t->same(['Pictures/image.png'], $meta['odtImageResources']);
        $t->same('bullet_list', $document->children[2]->type);
        $t->same('ordered_list', $document->children[3]->type);
        $t->same(3, $document->children[3]->attr('start'));
        $t->same('lower_alpha', $document->children[3]->attr('style'));
        $t->same('one_paren', $document->children[3]->attr('delimiter'));
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<strong>bold</strong>', $blocks);
        $t->contains('<em>italic</em>', $blocks);
        $t->contains('<a href="https://example.test">a link</a>', $blocks);
        $t->contains('<!-- wp:list {"ordered":true,"start":3} -->', $blocks);
        $t->contains('<ol start="3" type="a"><li>Alpha three</li><li>Alpha four</li></ol>', $blocks);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('Pictures/image.png', $blocks);
        $t->contains('<!-- wp:list -->', $converterBlocks);
    },
    'reads odt bytes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ODT path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ODT package');
        }
        $zip->addFromString('content.xml', '<?xml version="1.0"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"><office:body><office:text><text:h text:outline-level="1">Byte ODT</text:h><text:p>Body.</text:p></office:text></office:body></office:document-content>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary ODT package');
            }
            $document = PandocConverter::read($bytes, 'odt');
        } finally {
            @unlink($path);
        }

        $t->same('heading', $document->children[0]->type);
        $t->same('Byte ODT', $document->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->type);
    },
    'reports direct odt style diagnostics in document metadata' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ODT path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ODT package');
        }
        $zip->addFromString('styles.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:family="text" style:display-name="Nameless Text"/>
    <style:style style:name="FamilylessText"><style:text-properties fo:font-style="italic"/></style:style>
    <style:style style:name="VendorInline" style:family="review-extension"/>
    <style:style style:name="KnownText" style:family="text"><style:text-properties fo:font-weight="bold"/></style:style>
    <text:list-style>
      <text:list-level-style-number text:level="1" style:num-format="1"/>
    </text:list-style>
    <text:list-style style:name="KnownNumbers">
      <text:list-level-style-number text:level="1" style:num-format="1"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML);
        $zip->addFromString('content.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p><text:span text:style-name="KnownText">Known</text:span> and <text:span text:style-name="MissingText">missing</text:span> text style.</text:p>
      <text:list text:style-name="MissingList">
        <text:list-item><text:p>Missing list style.</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="KnownNumbers">
        <text:list-item><text:p>Known list style.</text:p></text:list-item>
      </text:list>
    </office:text>
  </office:body>
</office:document-content>
XML);
        $zip->close();

        try {
            $document = (new OdtReader())->readOdtFile($path);
        } finally {
            @unlink($path);
        }

        $meta = $document->attr('meta');
        $diagnosticsByCode = [];
        foreach ($meta['odtStyleDiagnostics'] as $diagnostic) {
            $diagnosticsByCode[$diagnostic['code']][] = $diagnostic;
        }

        $t->same(6, $meta['odtStyleDiagnosticCount']);
        $t->same([
            'odt-content-missing-list-style' => 1,
            'odt-content-missing-text-style' => 1,
            'odt-list-style-missing-name' => 1,
            'odt-style-missing-family' => 1,
            'odt-style-missing-name' => 1,
            'odt-style-unknown-family' => 1,
        ], $meta['odtStyleDiagnosticCodeCounts']);

        $missingName = $diagnosticsByCode['odt-style-missing-name'][0];
        $t->same('styles.xml', $missingName['sourcePart']);
        $t->same('style:style', $missingName['element']);
        $t->same('text', $missingName['family']);

        $missingFamily = $diagnosticsByCode['odt-style-missing-family'][0];
        $t->same('FamilylessText', $missingFamily['styleName']);
        $t->same('style:style', $missingFamily['element']);

        $unknownFamily = $diagnosticsByCode['odt-style-unknown-family'][0];
        $t->same('VendorInline', $unknownFamily['styleName']);
        $t->same('review-extension', $unknownFamily['family']);

        $missingListName = $diagnosticsByCode['odt-list-style-missing-name'][0];
        $t->same('styles.xml', $missingListName['sourcePart']);
        $t->same('text:list-style', $missingListName['element']);

        $missingTextStyle = $diagnosticsByCode['odt-content-missing-text-style'][0];
        $t->same('content.xml', $missingTextStyle['sourcePart']);
        $t->same('text:span', $missingTextStyle['element']);
        $t->same('MissingText', $missingTextStyle['styleName']);

        $missingListStyle = $diagnosticsByCode['odt-content-missing-list-style'][0];
        $t->same('content.xml', $missingListStyle['sourcePart']);
        $t->same('text:list', $missingListStyle['element']);
        $t->same('MissingList', $missingListStyle['listStyleName']);

        $t->same('paragraph', $document->children[0]->type);
        $t->same('Known and missing text style.', $document->children[0]->attr('text'));
    },
    'reports direct odt missing parent style diagnostics' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ODT path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ODT package');
        }
        $zip->addFromString('styles.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="KnownParent" style:family="text"><style:text-properties fo:font-weight="bold"/></style:style>
    <style:style style:name="ChildMissingParent" style:family="text" style:parent-style-name="MissingParent"><style:text-properties fo:font-style="italic"/></style:style>
    <style:style style:name="ChildKnownParent" style:family="text" style:parent-style-name="KnownParent"/>
  </office:styles>
</office:document-styles>
XML);
        $zip->addFromString('content.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p><text:span text:style-name="ChildMissingParent">Missing parent</text:span> and <text:span text:style-name="ChildKnownParent">known parent</text:span>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML);
        $zip->close();

        try {
            $document = (new OdtReader())->readOdtFile($path);
        } finally {
            @unlink($path);
        }

        $meta = $document->attr('meta');
        $diagnostic = $meta['odtStyleDiagnostics'][0];

        $t->same(3, $meta['odtTextStyleCount']);
        $t->same(1, $meta['odtStyleDiagnosticCount']);
        $t->same(['odt-style-missing-parent' => 1], $meta['odtStyleDiagnosticCodeCounts']);
        $t->same('styles.xml', $diagnostic['sourcePart']);
        $t->same('style:style', $diagnostic['element']);
        $t->same('ChildMissingParent', $diagnostic['styleName']);
        $t->same('MissingParent', $diagnostic['parentStyleName']);
        $t->same('text', $diagnostic['family']);
        $t->same('paragraph', $document->children[0]->type);
        $t->same('Missing parent and known parent.', $document->children[0]->attr('text'));
    },
    'preserves odt footnotes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ODT path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ODT package');
        }
        $zip->addFromString('content.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Paragraph with source note<text:note text:id="ftn1" text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>ODT footnote body <text:span>with inline text</text:span>.</text:p></text:note-body></text:note> after.</text:p>
      <text:p>Endnote marker<text:note text:id="edn1" text:note-class="endnote"><text:note-citation>i</text:note-citation><text:note-body><text:p>ODT endnote body.</text:p></text:note-body></text:note>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML);
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary ODT package');
            }
            $document = PandocConverter::read($bytes, 'odt');
        } finally {
            @unlink($path);
        }

        $firstParagraph = $document->children[0];
        $secondParagraph = $document->children[1];
        $footnote = $firstParagraph->children[1];
        $endnote = $secondParagraph->children[1];

        $t->same('Paragraph with source note after.', $firstParagraph->attr('text'));
        $t->same('note', $footnote->type);
        $t->same('odt', $footnote->attr('sourceFormat'));
        $t->same('footnote', $footnote->attr('noteClass'));
        $t->same('ftn1', $footnote->attr('id'));
        $t->same('1', $footnote->attr('citation'));
        $t->same('ODT footnote body with inline text.', $footnote->children[0]->attr('text'));
        $t->same('Endnote marker.', $secondParagraph->attr('text'));
        $t->same('note', $endnote->type);
        $t->same('endnote', $endnote->attr('noteClass'));
        $t->same('edn1', $endnote->attr('id'));
        $t->same('i', $endnote->attr('citation'));
        $t->same('ODT endnote body.', $endnote->children[0]->attr('text'));

        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('Paragraph with source note[^1] after.', $markdown);
        $t->contains('[^1]: ODT footnote body with inline text.', $markdown);
        $t->contains('[^2]: ODT endnote body.', $markdown);
        $t->contains('<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup>', $blocks);
        $t->contains('<section class="footnotes" role="doc-endnotes"><ol>', $blocks);
        $t->contains('ODT footnote body with inline text.', $blocks);
        $t->contains('ODT endnote body.', $blocks);
    },
];
