<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubPackage;
use PortLibs\Pandoc\ZipPackage;

$epubContainerXml = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

$epub3OpfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:isbn:9780000000001</dc:identifier>
    <dc:title>WordPress Migration Guide</dc:title>
    <dc:creator id="creator">Data Liberation Team</dc:creator>
    <dc:language>en-US</dc:language>
    <meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="style" href="styles/book.css" media-type="text/css"/>
    <item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2" linear="no" properties="page-spread-right"/>
  </spine>
</package>
XML;

$epub3NavXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <head><title>Contents</title></head>
  <body>
    <nav epub:type="toc" id="toc">
      <h1>Contents</h1>
      <ol>
        <li>
          <a href="text/chapter1.xhtml">Introduction</a>
          <ol>
            <li><a href="text/chapter1.xhtml#install">Install notes</a></li>
          </ol>
        </li>
        <li><a href="text/chapter2.xhtml">Review checklist</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

$epub2OpfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="legacy-id">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="legacy-id">legacy-42</dc:identifier>
    <dc:title>Legacy Export</dc:title>
    <dc:creator>Classic Press</dc:creator>
    <dc:language>en</dc:language>
    <meta name="cover" content="legacy-cover"/>
  </metadata>
  <manifest>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
    <item id="legacy-cover" href="cover.jpg" media-type="image/jpeg"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine toc="toc">
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

$epub2NcxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="navPoint-1" playOrder="1">
      <navLabel><text>Legacy Start</text></navLabel>
      <content src="chapter.xhtml"/>
      <navPoint id="navPoint-1-1" playOrder="2">
        <navLabel><text>Legacy Detail</text></navLabel>
        <content src="chapter.xhtml#detail"/>
      </navPoint>
    </navPoint>
  </navMap>
</ncx>
XML;

$epub3Package = static function () use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
        ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
        ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
        ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
        ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
        ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
        ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
        ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
    ], 'epub3 preflight');
};

return [
    'preflights EPUB3 container OPF metadata manifest spine and nav handoff' => static function (TestRunner $t) use ($epub3Package): void {
        $epub = EpubPackage::fromPackage($epub3Package());
        $summary = $epub->summary();

        $t->same('/EPUB/package.opf', $epub->opfPartName());
        $t->same('3.0', $epub->metadata()['version']);
        $t->same('urn:isbn:9780000000001', $epub->metadata()['identifier']);
        $t->same('WordPress Migration Guide', $epub->metadata()['title']);
        $t->same(['Data Liberation Team'], $epub->metadata()['creators']);
        $t->same('en-US', $epub->metadata()['language']);
        $t->same('2026-06-03T22:09:50Z', $epub->metadata()['modified']);

        $t->same('/EPUB/text/chapter1.xhtml', $epub->readingOrder()[0]['partName']);
        $t->same(true, $epub->readingOrder()[0]['linear']);
        $t->same('/EPUB/text/chapter2.xhtml', $epub->readingOrder()[1]['partName']);
        $t->same(false, $epub->readingOrder()[1]['linear']);
        $t->same(['page-spread-right'], $epub->readingOrder()[1]['properties']);

        $t->same('nav', $epub->navigation()['type']);
        $t->same('/EPUB/nav.xhtml', $epub->navigation()['partName']);
        $t->same('Introduction', $epub->navigation()['entries'][0]['label']);
        $t->same('/EPUB/text/chapter1.xhtml', $epub->navigation()['entries'][0]['target']);
        $t->same(1, $epub->navigation()['entries'][0]['depth']);
        $t->same('Install notes', $epub->navigation()['entries'][1]['label']);
        $t->same('/EPUB/text/chapter1.xhtml#install', $epub->navigation()['entries'][1]['target']);
        $t->same(2, $epub->navigation()['entries'][1]['depth']);
        $t->same('Review checklist', $epub->navigation()['entries'][2]['label']);

        $t->same('/EPUB/images/cover.png', $epub->assetSummary()['coverImagePart']);
        $t->same(['/EPUB/styles/book.css'], $epub->assetSummary()['stylesheetParts']);
        $t->same(['/EPUB/images/cover.png'], $epub->assetSummary()['imageParts']);
        $t->same(['/EPUB/nav.xhtml', '/EPUB/text/chapter1.xhtml', '/EPUB/text/chapter2.xhtml'], array_column($epub->xhtmlAssets(), 'partName'));
        $t->same(['Introduction', 'Install notes', 'Review checklist'], $summary['wordpressImport']['navigationLabels']);
        $t->same(['/EPUB/text/chapter1.xhtml', '/EPUB/text/chapter2.xhtml'], $summary['wordpressImport']['readingOrderParts']);
    },

    'falls back to NCX navigation and legacy cover metadata' => static function (TestRunner $t) use ($epubContainerXml, $epub2OpfXml, $epub2NcxXml): void {
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub2OpfXml],
            ['name' => 'EPUB/toc.ncx', 'data' => $epub2NcxXml],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Legacy</body></html>'],
            ['name' => 'EPUB/cover.jpg', 'data' => 'JPG'],
        ]));

        $t->same('2.0', $epub->metadata()['version']);
        $t->same('legacy-42', $epub->metadata()['identifier']);
        $t->same('/EPUB/cover.jpg', $epub->assetSummary()['coverImagePart']);
        $t->same('ncx', $epub->navigation()['type']);
        $t->same('/EPUB/toc.ncx', $epub->navigation()['partName']);
        $t->same('Legacy Start', $epub->navigation()['entries'][0]['label']);
        $t->same('/EPUB/chapter.xhtml', $epub->navigation()['entries'][0]['target']);
        $t->same('Legacy Detail', $epub->navigation()['entries'][1]['label']);
        $t->same('/EPUB/chapter.xhtml#detail', $epub->navigation()['entries'][1]['target']);
        $t->same(2, $epub->navigation()['entries'][1]['depth']);
    },

    'exposes OPF manifest items by id and preserves non-spine assets' => static function (TestRunner $t) use ($epub3Package): void {
        $epub = EpubPackage::fromPackage($epub3Package());

        $nav = $epub->manifestItem('nav');
        $style = $epub->manifestItem('style');
        $missing = $epub->manifestItem('missing');

        $t->same('/EPUB/nav.xhtml', $nav['partName']);
        $t->same(['nav'], $nav['properties']);
        $t->same('text/css', $style['mediaType']);
        $t->same('/EPUB/styles/book.css', $style['partName']);
        $t->same(null, $missing);
        $t->same(['nav', 'style', 'cover', 'chapter1', 'chapter2'], array_column($epub->manifestItems(), 'id'));
        $t->same(['chapter1', 'chapter2'], array_column($epub->spine(), 'idref'));
    },

    'preserves OPF manifest href query and fragment suffixes for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithHrefSuffixes = str_replace(
            'href="styles/book.css"',
            'href="styles/book.css?revision=20260610"',
            $epub3OpfXml
        );
        $opfWithHrefSuffixes = str_replace(
            'href="text/chapter1.xhtml"',
            'href="text/chapter1.xhtml?source=archive#start"',
            $opfWithHrefSuffixes
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithHrefSuffixes],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $chapter = $epub->manifestItem('chapter1');
        $style = $epub->manifestItem('style');
        $validation = $epub->validationReport();
        $manifest = $validation['manifest'];
        $summary = $epub->summary();

        $t->same('/EPUB/text/chapter1.xhtml?source=archive#start', $chapter['target']);
        $t->same('/EPUB/text/chapter1.xhtml', $chapter['partName']);
        $t->same(true, $chapter['hrefHasQuery']);
        $t->same('source=archive', $chapter['hrefQuery']);
        $t->same(true, $chapter['hrefHasFragment']);
        $t->same('start', $chapter['hrefFragment']);
        $t->same('/EPUB/styles/book.css?revision=20260610', $style['target']);
        $t->same('/EPUB/styles/book.css', $style['partName']);
        $t->same(true, $style['hrefHasQuery']);
        $t->same('revision=20260610', $style['hrefQuery']);
        $t->same(false, $style['hrefHasFragment']);
        $t->same(null, $style['hrefFragment']);
        $t->same('/EPUB/text/chapter1.xhtml', $epub->readingOrder()[0]['partName']);
        $t->same('/EPUB/styles/book.css', $epub->assetSummary()['stylesheetParts'][0]);
        $t->same(false, $validation['valid']);
        $t->same(3, $validation['diagnosticCount']);
        $t->same(2, $manifest['hrefSuffixCount']);
        $t->same(['style', 'chapter1'], array_column($manifest['hrefSuffixItems'], 'id'));
        $t->same(['manifest-href-query-component', 'manifest-href-query-component', 'manifest-href-fragment-component'], array_column($manifest['diagnostics'], 'type'));
        $t->same('source=archive', $manifest['hrefSuffixItems'][1]['query']);
        $t->same('start', $manifest['hrefSuffixItems'][1]['fragment']);
        $t->same($manifest['hrefSuffixItems'], $summary['wordpressImport']['packageValidation']['manifest']['hrefSuffixItems']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
    },

    'preserves compact OPF spine itemref ids and refinement provenance' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithSpineRefinements = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="schema: https://schema.org/">',
            $epub3OpfXml
        );
        $opfWithSpineRefinements = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta refines="#chapter1-spine" property="rendition:viewport" xml:lang="en" dir="ltr">width=600,height=900</meta>
    <meta id="chapter1-spine-position" refines="#chapter1-spine" property="schema:position" content="primary reading-order entry"/>',
            $opfWithSpineRefinements
        );
        $opfWithSpineRefinements = str_replace(
            '<spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2" linear="no" properties="page-spread-right"/>
  </spine>',
            '<spine>
    <itemref id="chapter1-spine" idref="chapter1" linear="yes" properties="rendition:orientation-landscape"/>
    <itemref id="chapter2-spine" idref="chapter2" linear="no" properties="page-spread-right"/>
  </spine>',
            $opfWithSpineRefinements
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithSpineRefinements],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $spine = $epub->spine();
        $summary = $epub->summary();

        $t->same('chapter1-spine', $spine[0]['id']);
        $t->same('chapter1', $spine[0]['idref']);
        $t->same('yes', $spine[0]['linearRaw']);
        $t->same(true, $spine[0]['linear']);
        $t->same(['rendition:orientation-landscape'], $spine[0]['properties']);
        $t->same('width=600,height=900', $spine[0]['refinements']['rendition:viewport'][0]['content']);
        $t->same('#chapter1-spine', $spine[0]['refinements']['rendition:viewport'][0]['refines']);
        $t->same('chapter1-spine', $spine[0]['refinements']['rendition:viewport'][0]['subjectId']);
        $t->same('en', $spine[0]['refinements']['rendition:viewport'][0]['language']);
        $t->same('ltr', $spine[0]['refinements']['rendition:viewport'][0]['direction']);
        $t->same('chapter1-spine-position', $spine[0]['refinements']['schema:position'][0]['id']);
        $t->same('primary reading-order entry', $spine[0]['refinements']['schema:position'][0]['content']);
        $t->same('width=600,height=900', $spine[0]['renditionViewportRefinements'][0]['content']);
        $t->same('chapter1-spine', $spine[0]['renditionViewportRefinements'][0]['subjectId']);
        $t->same('chapter2-spine', $spine[1]['id']);
        $t->same('no', $spine[1]['linearRaw']);
        $t->same(false, $spine[1]['linear']);
        $t->same([], $spine[1]['refinements']);
        $t->same($spine, $epub->readingOrder());
        $t->same($spine[0]['refinements'], $summary['metadata']['refinementsById']['chapter1-spine']);
        $t->same('chapter1-spine', $summary['readingOrder'][0]['id']);
        $t->same($spine[0]['refinements'], $summary['readingOrder'][0]['refinements']);
    },

    'summarizes OPF media-type bindings for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $handlerXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Slideshow fallback</h1></body></html>';
        $opfWithBindings = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="slideshow" href="widgets/source-slideshow.bin" media-type="application/x-demo-slideshow"/>
    <item id="slideshow-handler" href="widgets/slideshow-fallback.xhtml" media-type="application/xhtml+xml" properties="scripted"/>',
            $epub3OpfXml
        );
        $opfWithBindings = str_replace(
            '</spine>',
            '</spine>
  <bindings>
    <mediaType media-type="application/x-demo-slideshow" handler="slideshow-handler"/>
    <mediaType media-type="application/x-review-widget" handler="missing-handler"/>
    <mediaType handler="slideshow-handler"/>
  </bindings>',
            $opfWithBindings
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithBindings],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/widgets/source-slideshow.bin', 'data' => 'SLIDESHOW'],
            ['name' => 'EPUB/widgets/slideshow-fallback.xhtml', 'data' => $handlerXhtml],
        ]));

        $bindings = $epub->bindings();
        $summary = $epub->summary();

        $t->same(true, $bindings['present']);
        $t->same(3, $bindings['itemCount']);
        $t->same(['application/x-demo-slideshow', 'application/x-review-widget'], $bindings['boundMediaTypes']);
        $t->same('application/x-demo-slideshow', $bindings['items'][0]['mediaType']);
        $t->same('slideshow-handler', $bindings['items'][0]['handlerId']);
        $t->same('widgets/slideshow-fallback.xhtml', $bindings['items'][0]['handlerHref']);
        $t->same('/EPUB/widgets/slideshow-fallback.xhtml', $bindings['items'][0]['handlerPartName']);
        $t->same('application/xhtml+xml', $bindings['items'][0]['handlerMediaType']);
        $t->same(['scripted'], $bindings['items'][0]['handlerProperties']);
        $t->same(true, $bindings['items'][0]['handlerExists']);
        $t->same(strlen($handlerXhtml), $bindings['items'][0]['handlerByteLength']);
        $t->same([], $bindings['items'][0]['diagnostics']);
        $t->same('application/x-review-widget', $bindings['items'][1]['mediaType']);
        $t->same('missing-handler', $bindings['items'][1]['handlerId']);
        $t->same(false, $bindings['items'][1]['handlerExists']);
        $t->same(null, $bindings['items'][1]['handlerPartName']);
        $t->same('missing-binding-handler-manifest-item', $bindings['items'][1]['diagnostics'][0]['type']);
        $t->same(null, $bindings['items'][2]['mediaType']);
        $t->same('missing-binding-media-type', $bindings['items'][2]['diagnostics'][0]['type']);
        $t->same(2, count($bindings['diagnostics']));
        $t->same(1, $bindings['diagnostics'][0]['index']);
        $t->same(2, $bindings['diagnostics'][1]['index']);
        $t->same($bindings, $summary['bindings']);
        $t->same($bindings['items'], $summary['wordpressImport']['mediaTypeBindings']);
        $t->same($bindings['diagnostics'], $summary['wordpressImport']['mediaTypeBindingDiagnostics']);
    },

    'flags encrypted OPF binding handlers for compact package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $handlerXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Locked widget fallback</h1></body></html>';
        $opfWithEncryptedBinding = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="locked-handler" href="widgets/locked-handler.xhtml" media-type="application/xhtml+xml" properties="scripted"/>',
            $epub3OpfXml
        );
        $opfWithEncryptedBinding = str_replace(
            '</spine>',
            '</spine>
  <bindings>
    <mediaType media-type="application/x-locked-widget" handler="locked-handler"/>
  </bindings>',
            $opfWithEncryptedBinding
        );
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/widgets/locked-handler.xhtml"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithEncryptedBinding],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/widgets/locked-handler.xhtml', 'data' => $handlerXhtml],
        ]));

        $bindings = $epub->bindings();
        $summary = $epub->summary();
        $handler = $bindings['items'][0];
        $diagnostic = $handler['diagnostics'][0];

        $t->same(true, $bindings['present']);
        $t->same(1, $bindings['itemCount']);
        $t->same('locked-handler', $handler['handlerId']);
        $t->same('/EPUB/widgets/locked-handler.xhtml', $handler['handlerPartName']);
        $t->same(true, $handler['handlerExists']);
        $t->same(true, $handler['handlerEncrypted']);
        $t->same(false, $handler['handlerCanExposeBytes']);
        $t->same(strlen($handlerXhtml), $handler['handlerByteLength']);
        $t->same('xhtml', $handler['handlerEncryption']['role']);
        $t->same('encrypted-resource-review', $handler['handlerEncryption']['reviewPolicy']);
        $t->same('encrypted-resource-bytes-blocked', $handler['handlerEncryption']['byteExposurePolicy']);
        $t->same('encrypted-binding-handler', $diagnostic['type']);
        $t->same('application/x-locked-widget', $diagnostic['mediaType']);
        $t->same('/EPUB/widgets/locked-handler.xhtml', $diagnostic['handlerPartName']);
        $t->same('encrypted-resource-review', $diagnostic['reviewPolicy']);
        $t->same(1, count($bindings['diagnostics']));
        $t->same(0, $bindings['diagnostics'][0]['index']);
        $t->same('encrypted-binding-handler', $bindings['diagnostics'][0]['type']);
        $t->same($bindings, $summary['bindings']);
        $t->same($bindings['items'], $summary['wordpressImport']['mediaTypeBindings']);
        $t->same($bindings['diagnostics'], $summary['wordpressImport']['mediaTypeBindingDiagnostics']);
    },

    'preserves OPF metadata refinements for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithRefinements = str_replace(
            '<dc:title>WordPress Migration Guide</dc:title>',
            '<dc:title id="main-title" xml:lang="en">WordPress Migration Guide</dc:title>
    <dc:title id="subtitle-title" xml:lang="es" dir="ltr">Guia de migracion</dc:title>',
            $epub3OpfXml
        );
        $opfWithRefinements = str_replace(
            '</metadata>',
            '    <meta refines="#main-title" property="title-type">main</meta>
    <meta refines="#main-title" property="file-as">WordPress Migration Guide, The</meta>
    <meta refines="#main-title" property="display-seq">1</meta>
    <meta refines="#subtitle-title" property="title-type">subtitle</meta>
    <meta refines="#subtitle-title" property="alternate-script" xml:lang="en" dir="ltr">Migration guide subtitle</meta>
    <meta refines="#creator" property="role" scheme="marc:relators">aut</meta>
    <meta refines="#creator" property="file-as">Team, Data Liberation</meta>
    <meta refines="#bookid" property="identifier-type" scheme="onix:codelist5">15</meta>
  </metadata>',
            $opfWithRefinements
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithRefinements],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();

        $t->same('WordPress Migration Guide', $metadata['title']);
        $t->same(['WordPress Migration Guide', 'Guia de migracion'], $metadata['titles']);
        $t->same('main', $metadata['mainTitle']['titleType']);
        $t->same('WordPress Migration Guide, The', $metadata['mainTitle']['fileAs']);
        $t->same('1', $metadata['mainTitle']['displaySeq']);
        $t->same('en', $metadata['mainTitle']['language']);
        $t->same('WordPress Migration Guide, The', $metadata['sortTitle']);
        $t->same('subtitle', $metadata['subtitle']['titleType']);
        $t->same('Guia de migracion', $metadata['titlesByType']['subtitle'][0]['text']);
        $t->same('Migration guide subtitle', $metadata['subtitle']['alternateScripts'][0]['text']);
        $t->same('en', $metadata['subtitle']['alternateScripts'][0]['language']);
        $t->same('ltr', $metadata['subtitle']['alternateScripts'][0]['direction']);

        $t->same('Data Liberation Team', $metadata['creatorDetails'][0]['text']);
        $t->same('Team, Data Liberation', $metadata['creatorDetails'][0]['fileAs']);
        $t->same(['aut'], $metadata['creatorDetails'][0]['roleValues']);
        $t->same('aut', $metadata['creatorDetails'][0]['primaryRole']);
        $t->same('Data Liberation Team', $metadata['creatorsByRole']['aut'][0]['text']);

        $t->same('urn:isbn:9780000000001', $metadata['identifierDetails'][0]['value']);
        $t->same('bookid', $metadata['identifierDetails'][0]['id']);
        $t->same('15', $metadata['identifierDetails'][0]['identifierType']);
        $t->same('onix:codelist5', $metadata['identifierDetails'][0]['identifierTypes'][0]['scheme']);
        $t->same('urn:isbn:9780000000001', $metadata['identifiersByType']['15'][0]['value']);
        $t->same('#bookid', $metadata['refinementsById']['bookid']['identifier-type'][0]['refines']);
        $t->same('#main-title', $metadata['refinementsById']['main-title']['file-as'][0]['refines']);

        $t->same('WordPress Migration Guide, The', $summary['wordpressImport']['metadataDetails']['sortTitle']);
        $t->same('Guia de migracion', $summary['wordpressImport']['metadataDetails']['titlesByType']['subtitle'][0]['text']);
        $t->same('Data Liberation Team', $summary['wordpressImport']['metadataDetails']['creatorsByRole']['aut'][0]['text']);
        $t->same('urn:isbn:9780000000001', $summary['wordpressImport']['metadataDetails']['identifiersByType']['15'][0]['value']);
    },

    'preflights OPF unique identifier and duplicate identifier diagnostics for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithIdentifierDiagnostics = str_replace(
            '<dc:identifier id="bookid">urn:isbn:9780000000001</dc:identifier>',
            '<dc:identifier id="bookid" scheme="UUID">urn:uuid:primary-source</dc:identifier>
    <dc:identifier id="bookid" scheme="UUID">urn:uuid:secondary-source</dc:identifier>
    <dc:identifier id="isbn-id" scheme="ISBN">9780000000001</dc:identifier>
    <dc:identifier id="duplicate-isbn" scheme="ISBN">9780000000001</dc:identifier>',
            $epub3OpfXml
        );
        $opfWithIdentifierDiagnostics = str_replace(
            '</metadata>',
            '    <meta refines="#bookid" property="identifier-type" scheme="onix:codelist5">15</meta>
    <meta refines="#duplicate-isbn" property="identifier-type" scheme="onix:codelist5">15</meta>
  </metadata>',
            $opfWithIdentifierDiagnostics
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithIdentifierDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $uniqueIdentifier = $metadata['uniqueIdentifier'];
        $identifierSummary = $metadata['identifierSummary'];
        $identifierDetails = $metadata['identifierDetails'];

        $t->same('urn:uuid:primary-source', $metadata['identifier']);
        $t->same(true, $uniqueIdentifier['specified']);
        $t->same('bookid', $uniqueIdentifier['id']);
        $t->same(true, $uniqueIdentifier['matched']);
        $t->same('urn:uuid:primary-source', $uniqueIdentifier['value']);
        $t->same('unique-identifier', $uniqueIdentifier['selectedBy']);
        $t->same(4, $uniqueIdentifier['identifierCount']);
        $t->same(2, $uniqueIdentifier['matchCount']);
        $t->same(1, $uniqueIdentifier['duplicateMatchCount']);
        $t->same(false, $uniqueIdentifier['valid']);
        $t->same('duplicate-unique-identifier-id', $uniqueIdentifier['diagnostics'][0]['type']);
        $t->same(['urn:uuid:primary-source', 'urn:uuid:secondary-source'], $uniqueIdentifier['diagnostics'][0]['values']);

        $t->same(true, $identifierDetails[0]['selectedByUniqueIdentifier']);
        $t->same(true, $identifierDetails[1]['selectedByUniqueIdentifier']);
        $t->same(false, $identifierDetails[0]['duplicateValue']);
        $t->same(true, $identifierDetails[2]['duplicateValue']);
        $t->same(['isbn-id', 'duplicate-isbn'], $identifierDetails[2]['duplicateIds']);
        $t->same([2, 3], $identifierDetails[2]['duplicateIndexes']);
        $t->same('15', $identifierDetails[3]['identifierType']);

        $t->same(true, $identifierSummary['present']);
        $t->same(4, $identifierSummary['count']);
        $t->same(3, $identifierSummary['typedCount']);
        $t->same(['UUID', 'ISBN'], $identifierSummary['schemes']);
        $t->same(['15'], $identifierSummary['identifierTypes']);
        $t->same('urn:uuid:primary-source', $identifierSummary['selectedValue']);
        $t->same('bookid', $identifierSummary['selectedId']);
        $t->same(0, $identifierSummary['selectedIndex']);
        $t->same(1, $identifierSummary['duplicateValueCount']);
        $t->same('9780000000001', $identifierSummary['duplicatesByValue'][0]['value']);
        $t->same('duplicate-metadata-identifier-value', $identifierSummary['diagnostics'][0]['type']);

        $t->same(['duplicate-unique-identifier-id', 'duplicate-metadata-identifier-value'], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $metadata['identifierDiagnostics']));
        $t->same($uniqueIdentifier, $summary['wordpressImport']['metadataDetails']['uniqueIdentifier']);
        $t->same($identifierSummary, $summary['wordpressImport']['metadataDetails']['identifierSummary']);
        $t->same($metadata['identifierDiagnostics'], $summary['wordpressImport']['metadataDetails']['identifierDiagnostics']);
    },

    'summarizes OPF date event metadata for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithDates = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:language>en-US</dc:language>
    <dc:date id="published" scheme="W3CDTF" event="publication">2026-06-01</dc:date>
    <dc:date id="reviewed" xml:lang="en" dir="ltr">2026-06-05</dc:date>',
            $epub3OpfXml
        );
        $opfWithDates = str_replace(
            '</metadata>',
            '    <meta refines="#reviewed" property="event">review</meta>
    <meta refines="#reviewed" property="display-seq">2</meta>
    <meta refines="#reviewed" property="alternate-script" xml:lang="fr" dir="ltr">5 juin 2026</meta>
  </metadata>',
            $opfWithDates
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithDates],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $dateDetails = $metadata['dateDetails'];

        $t->same(['2026-06-01', '2026-06-05'], $metadata['dates']);
        $t->same('2026-06-01', $metadata['date']);
        $t->same(2, count($dateDetails));
        $t->same('published', $dateDetails[0]['id']);
        $t->same('W3CDTF', $dateDetails[0]['scheme']);
        $t->same('publication', $dateDetails[0]['event']);
        $t->same('attribute', $dateDetails[0]['eventSource']);
        $t->same('reviewed', $dateDetails[1]['id']);
        $t->same('review', $dateDetails[1]['event']);
        $t->same('refinement', $dateDetails[1]['eventSource']);
        $t->same('2', $dateDetails[1]['displaySeq']);
        $t->same('5 juin 2026', $dateDetails[1]['alternateScripts'][0]['text']);
        $t->same('fr', $dateDetails[1]['alternateScripts'][0]['language']);
        $t->same('2026-06-01', $metadata['datesByEvent']['publication'][0]['text']);
        $t->same('2026-06-05', $metadata['datesByEvent']['review'][0]['text']);
        $t->same([
            'present' => true,
            'count' => 2,
            'eventCount' => 2,
            'events' => ['publication', 'review'],
        ], $metadata['dateSummary']);
        $t->same($dateDetails, $summary['wordpressImport']['metadataDetails']['dateDetails']);
        $t->same($metadata['datesByEvent'], $summary['wordpressImport']['metadataDetails']['datesByEvent']);
        $t->same($metadata['dateSummary'], $summary['wordpressImport']['metadataDetails']['dateSummary']);
    },

    'summarizes OPF language declarations for compact package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithLanguageMetadata = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:language id="lang-primary" scheme="BCP47" xml:lang="en">en-US</dc:language>
    <dc:language id="lang-secondary" xml:lang="fr">fr-CA</dc:language>
    <dc:language id="lang-duplicate">en-us</dc:language>
    <dc:language id="lang-invalid">review language</dc:language>',
            $epub3OpfXml
        );
        $opfWithLanguageMetadata = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta refines="#lang-primary" property="display-seq">1</meta>
    <meta refines="#lang-secondary" property="display-seq">2</meta>',
            $opfWithLanguageMetadata
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithLanguageMetadata],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $languageDetails = $metadata['languageDetails'];
        $languageSummary = $metadata['languageSummary'];

        $t->same('en-US', $metadata['language']);
        $t->same(['en-US', 'fr-CA', 'en-us', 'review language'], $metadata['languages']);
        $t->same(4, count($languageDetails));

        $primary = $languageDetails[0];
        $t->same('language', $primary['kind']);
        $t->same(0, $primary['index']);
        $t->same('lang-primary', $primary['id']);
        $t->same('en-US', $primary['tag']);
        $t->same('en-us', $primary['normalizedTag']);
        $t->same('en', $primary['primarySubtag']);
        $t->same('US', $primary['regionSubtag']);
        $t->same(true, $primary['wellFormed']);
        $t->same('BCP47', $primary['scheme']);
        $t->same('en', $primary['language']);
        $t->same('1', $primary['displaySeq']);
        $t->same(1, $primary['displaySeqNumber']);
        $t->same(true, $primary['duplicateTag']);
        $t->same([0, 2], $primary['duplicateIndexes']);
        $t->same('duplicate-language-tag', $primary['diagnostics'][0]['type']);

        $secondary = $languageDetails[1];
        $t->same('fr-CA', $secondary['tag']);
        $t->same('fr-ca', $secondary['normalizedTag']);
        $t->same('fr', $secondary['primarySubtag']);
        $t->same('CA', $secondary['regionSubtag']);
        $t->same(false, $secondary['duplicateTag']);
        $t->same('2', $secondary['displaySeq']);
        $t->same(2, $secondary['displaySeqNumber']);

        $invalid = $languageDetails[3];
        $t->same('review language', $invalid['tag']);
        $t->same(false, $invalid['wellFormed']);
        $t->same('invalid-language-tag', $invalid['diagnostics'][0]['type']);

        $t->same(true, $languageSummary['present']);
        $t->same(4, $languageSummary['count']);
        $t->same('en-US', $languageSummary['primaryLanguage']);
        $t->same(2, $languageSummary['uniqueTagCount']);
        $t->same(['en-us', 'fr-ca'], $languageSummary['normalizedTags']);
        $t->same(2, $languageSummary['primarySubtagCount']);
        $t->same(['en', 'fr'], $languageSummary['primarySubtags']);
        $t->same(['US', 'CA'], $languageSummary['regionSubtags']);
        $t->same(1, $languageSummary['duplicateTagCount']);
        $t->same(['en-us'], $languageSummary['duplicateTags']);
        $t->same(1, $languageSummary['invalidTagCount']);
        $t->same(['duplicate-language-tag', 'duplicate-language-tag', 'invalid-language-tag'], array_column($languageSummary['diagnostics'], 'type'));
        $t->same([$languageDetails[0], $languageDetails[2]], $metadata['languagesByPrimarySubtag']['en']);
        $t->same([$languageDetails[1]], $metadata['languagesByPrimarySubtag']['fr']);
        $t->same($languageDetails, $summary['wordpressImport']['metadataDetails']['languageDetails']);
        $t->same($metadata['languagesByPrimarySubtag'], $summary['wordpressImport']['metadataDetails']['languagesByPrimarySubtag']);
        $t->same($languageSummary, $summary['wordpressImport']['metadataDetails']['languageSummary']);
    },

    'summarizes OPF source provenance metadata for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithSources = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:language>en-US</dc:language>
    <dc:source id="print-source" scheme="ISBN" xml:lang="en">9781491905012</dc:source>
    <dc:source id="archive-source" xml:lang="fr" dir="ltr">Archive scan packet A</dc:source>',
            $epub3OpfXml
        );
        $opfWithSources = str_replace(
            '</metadata>',
            '    <meta refines="#print-source" property="source-of">pagination</meta>
    <meta refines="#print-source" property="identifier-type" scheme="onix:codelist5">15</meta>
    <meta refines="#print-source" property="display-seq">1</meta>
    <meta refines="#archive-source" property="source-of">transcription</meta>
    <meta refines="#archive-source" property="alternate-script" xml:lang="en" dir="ltr">Archive scan packet A translated</meta>
  </metadata>',
            $opfWithSources
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithSources],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $sourceDetails = $metadata['sourceDetails'];

        $t->same(['9781491905012', 'Archive scan packet A'], $metadata['sources']);
        $t->same('9781491905012', $metadata['source']);
        $t->same(2, count($sourceDetails));
        $t->same('print-source', $sourceDetails[0]['id']);
        $t->same('ISBN', $sourceDetails[0]['scheme']);
        $t->same('en', $sourceDetails[0]['language']);
        $t->same('pagination', $sourceDetails[0]['sourceOf']);
        $t->same(['pagination'], $sourceDetails[0]['sourceOfValues']);
        $t->same('15', $sourceDetails[0]['identifierType']);
        $t->same('onix:codelist5', $sourceDetails[0]['identifierTypes'][0]['scheme']);
        $t->same('1', $sourceDetails[0]['displaySeq']);
        $t->same('archive-source', $sourceDetails[1]['id']);
        $t->same('fr', $sourceDetails[1]['language']);
        $t->same('ltr', $sourceDetails[1]['direction']);
        $t->same('transcription', $sourceDetails[1]['sourceOf']);
        $t->same('Archive scan packet A translated', $sourceDetails[1]['alternateScripts'][0]['text']);
        $t->same('en', $sourceDetails[1]['alternateScripts'][0]['language']);
        $t->same('9781491905012', $metadata['sourcesByType']['pagination'][0]['text']);
        $t->same('Archive scan packet A', $metadata['sourcesByType']['transcription'][0]['text']);
        $t->same([
            'present' => true,
            'count' => 2,
            'typedCount' => 2,
            'schemeCount' => 1,
            'identifierTypeCount' => 1,
            'sourceOfValues' => ['pagination', 'transcription'],
            'identifierTypes' => ['15'],
            'schemes' => ['ISBN'],
        ], $metadata['sourceSummary']);

        $t->same($sourceDetails, $summary['wordpressImport']['metadataDetails']['sourceDetails']);
        $t->same($metadata['sourcesByType'], $summary['wordpressImport']['metadataDetails']['sourcesByType']);
        $t->same($metadata['sourceSummary'], $summary['wordpressImport']['metadataDetails']['sourceSummary']);
    },

    'summarizes OPF bibliographic Dublin Core metadata for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithBibliographicMetadata = str_replace(
            '<dc:language>en-US</dc:language>',
            '<dc:subject>Data Liberation</dc:subject>
    <dc:description id="summary" xml:lang="en" dir="ltr">Importer review summary.</dc:description>
    <dc:publisher id="publisher">Migration Publisher</dc:publisher>
    <dc:rights id="license" xml:lang="en">Creative Commons Attribution-ShareAlike 4.0</dc:rights>
    <dc:type id="resource-type" scheme="dcterms:DCMIType">Text</dc:type>
    <dc:format id="format" scheme="IANA">application/epub+zip</dc:format>
    <dc:relation id="source-post">https://example.test/posts/42</dc:relation>
    <dc:coverage id="coverage">Global migration packet</dc:coverage>
    <dc:language>en-US</dc:language>',
            $epub3OpfXml
        );
        $opfWithBibliographicMetadata = str_replace(
            '</metadata>',
            '    <meta refines="#license" property="authority">Creative Commons</meta>
    <meta refines="#license" property="term">CC-BY-SA-4.0</meta>
    <meta refines="#resource-type" property="authority">DCMI Type Vocabulary</meta>
    <meta refines="#resource-type" property="term">Text</meta>
    <meta refines="#source-post" property="display-seq">1</meta>
    <meta refines="#source-post" property="file-as">Post 42</meta>
    <meta refines="#coverage" property="alternate-script" xml:lang="fr">Dossier de migration mondial</meta>
  </metadata>',
            $opfWithBibliographicMetadata
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithBibliographicMetadata],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $metadata = $epub->metadata();
        $summary = $epub->summary();
        $details = $metadata['bibliographicDetails'];
        $byKind = $metadata['bibliographicDetailsByKind'];
        $bibliographicSummary = $metadata['bibliographicSummary'];

        $t->same(['Data Liberation'], $metadata['subjects']);
        $t->same('Importer review summary.', $metadata['description']);
        $t->same('Migration Publisher', $metadata['publisher']);
        $t->same(7, count($details));
        $t->same(['description', 'publisher', 'rights', 'type', 'format', 'relation', 'coverage'], $bibliographicSummary['kinds']);
        $t->same(7, $bibliographicSummary['count']);
        $t->same(7, $bibliographicSummary['kindCount']);
        $t->same(2, $bibliographicSummary['authorityCount']);
        $t->same(2, $bibliographicSummary['termCount']);
        $t->same(0, $bibliographicSummary['linkedResourceCount']);
        $t->same(1, $bibliographicSummary['kindCounts']['rights']);
        $t->same([], $bibliographicSummary['diagnostics']);

        $description = $byKind['description'][0];
        $t->same('summary', $description['id']);
        $t->same('Importer review summary.', $description['text']);
        $t->same('en', $description['language']);
        $t->same('ltr', $description['direction']);

        $rights = $byKind['rights'][0];
        $t->same('license', $rights['id']);
        $t->same('Creative Commons Attribution-ShareAlike 4.0', $rights['text']);
        $t->same('Creative Commons', $rights['authority']);
        $t->same('CC-BY-SA-4.0', $rights['term']);
        $t->same('Creative Commons', $rights['authorityEntries'][0]['text']);
        $t->same('CC-BY-SA-4.0', $rights['termEntries'][0]['value']);

        $type = $byKind['type'][0];
        $t->same('resource-type', $type['id']);
        $t->same('dcterms:DCMIType', $type['scheme']);
        $t->same('DCMI Type Vocabulary', $type['authority']);
        $t->same('Text', $type['term']);

        $format = $byKind['format'][0];
        $t->same('IANA', $format['scheme']);
        $t->same('application/epub+zip', $format['text']);

        $relation = $byKind['relation'][0];
        $t->same('source-post', $relation['id']);
        $t->same('https://example.test/posts/42', $relation['text']);
        $t->same('1', $relation['displaySeq']);
        $t->same('Post 42', $relation['fileAs']);

        $coverage = $byKind['coverage'][0];
        $t->same('Global migration packet', $coverage['text']);
        $t->same('Dossier de migration mondial', $coverage['alternateScripts'][0]['text']);
        $t->same('fr', $coverage['alternateScripts'][0]['language']);

        $t->same($details, $summary['wordpressImport']['metadataDetails']['bibliographicDetails']);
        $t->same($byKind, $summary['wordpressImport']['metadataDetails']['bibliographicDetailsByKind']);
        $t->same($bibliographicSummary, $summary['wordpressImport']['metadataDetails']['bibliographicSummary']);
    },

    'preserves OPF guide references and XHTML nav sections for package review' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithGuide = str_replace(
            '</spine>',
            '</spine>
  <guide>
    <reference type="text" title="Start reading" href="text/chapter1.xhtml#install"/>
    <reference type="cover" title="Cover image" href="images/cover.png"/>
    <reference type="glossary" title="Legacy glossary" href="https://example.invalid/glossary.xhtml"/>
  </guide>',
            $epub3OpfXml
        );
        $navWithSections = str_replace(
            '</body>',
            '    <nav epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="text/chapter1.xhtml#install">Start reading</a></li>
        <li><a epub:type="backmatter bibliography" href="text/chapter2.xhtml#refs">References</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter1.xhtml#page-1">1</a></li>
        <li><a epub:type="pagebreak" href="text/chapter2.xhtml#page-2">2</a></li>
      </ol>
    </nav>
  </body>',
            $epub3NavXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithGuide],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithSections],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="install">Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="refs">References</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $summary = $epub->summary();

        $t->same(['text', 'cover', 'glossary'], array_column($epub->guideReferences(), 'type'));
        $t->same('/EPUB/text/chapter1.xhtml#install', $epub->guideReferences()[0]['target']);
        $t->same(true, $epub->guideReferences()[0]['exists']);
        $t->same('/EPUB/images/cover.png', $epub->guideReferences()[1]['partName']);
        $t->same('https://example.invalid/glossary.xhtml', $epub->guideReferences()[2]['target']);
        $t->same(true, $epub->guideReferences()[2]['external']);

        $t->same(['toc', 'landmarks', 'page-list'], array_column($epub->navigationSections(), 'type'));
        $t->same(['Introduction', 'Install notes', 'Review checklist'], array_column($epub->navigationSections()[0]['entries'], 'label'));
        $t->same(['Start reading', 'References'], array_column($epub->navigationSections()[1]['entries'], 'label'));
        $t->same(['/EPUB/text/chapter1.xhtml#install', '/EPUB/text/chapter2.xhtml#refs'], array_column($epub->navigationSections()[1]['entries'], 'target'));
        $t->same(['1', '2'], array_column($epub->navigationSections()[2]['entries'], 'label'));
        $t->same(['/EPUB/text/chapter1.xhtml#page-1', '/EPUB/text/chapter2.xhtml#page-2'], array_column($summary['wordpressImport']['pageListTargets'], 'target'));
        $t->same(['/EPUB/text/chapter1.xhtml#install', '/EPUB/images/cover.png', 'https://example.invalid/glossary.xhtml'], array_column($summary['wordpressImport']['guideReferences'], 'target'));
    },

    'summarizes EPUB3 auxiliary navigation sections for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $navWithAuxiliarySections = str_replace(
            '</body>',
            '    <nav id="figures-nav" epub:type="loi list-of-illustrations">
      <h2>Figures</h2>
      <ol>
        <li><a href="text/chapter1.xhtml#fig-1">Figure 1</a></li>
        <li><a href="text/chapter2.xhtml#fig-2">Figure 2</a></li>
      </ol>
    </nav>
    <nav id="tables-nav" epub:type="lot" hidden="hidden">
      <h2>Tables</h2>
      <ol>
        <li><a href="text/chapter2.xhtml#table-1">Table 1</a></li>
      </ol>
    </nav>
  </body>',
            $epub3NavXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithAuxiliarySections],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1><figure id="fig-1"></figure></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1><figure id="fig-2"></figure><table id="table-1"></table></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));
        $summary = $epub->summary();
        $auxiliary = $summary['auxiliaryNavigation'];

        $t->same($auxiliary, $summary['wordpressImport']['auxiliaryNavigation']);
        $t->same($auxiliary['sections'], $summary['wordpressImport']['auxiliaryNavigationSections']);
        $t->same($auxiliary['items'], $summary['wordpressImport']['auxiliaryNavigationTargets']);
        $t->same(true, $auxiliary['present']);
        $t->same(2, $auxiliary['sectionCount']);
        $t->same(3, $auxiliary['itemCount']);
        $t->same(['loi', 'list-of-illustrations', 'lot'], $auxiliary['types']);
        $t->same(false, isset($auxiliary['sectionsByType']['toc']));

        $figures = $auxiliary['sections'][0];
        $t->same(1, $figures['sectionIndex']);
        $t->same('figures-nav', $figures['id']);
        $t->same('loi', $figures['type']);
        $t->same(['loi', 'list-of-illustrations'], $figures['auxiliaryTypes']);
        $t->same('Figures', $figures['title']);
        $t->same(false, $figures['hidden']);
        $t->same('/EPUB/nav.xhtml', $figures['partName']);
        $t->same($figures, $auxiliary['sectionsByType']['list-of-illustrations'][0]);

        $firstFigure = $auxiliary['items'][0];
        $t->same('figures-nav', $firstFigure['sectionId']);
        $t->same('loi', $firstFigure['sectionType']);
        $t->same(['loi', 'list-of-illustrations'], $firstFigure['sectionTypes']);
        $t->same('Figure 1', $firstFigure['label']);
        $t->same('/EPUB/text/chapter1.xhtml#fig-1', $firstFigure['target']);

        $tableSection = $auxiliary['sectionsByType']['lot'][0];
        $t->same('tables-nav', $tableSection['id']);
        $t->same(true, $tableSection['hidden']);
        $t->same('lot', $auxiliary['items'][2]['sectionType']);
        $t->same('/EPUB/text/chapter2.xhtml#table-1', $auxiliary['items'][2]['target']);
    },

    'preserves OPF collections and collection links for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithCollections = str_replace(
            '</spine>',
            '</spine>
  <collection id="series" role="series" xml:lang="en" dir="ltr">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>Migration packets</dc:title>
      <meta property="group-position">2</meta>
    </metadata>
    <link id="series-record" rel="record" href="meta/series.json" media-type="application/ld+json" properties="review"/>
    <link id="start" rel="first" href="text/chapter1.xhtml#install" media-type="application/xhtml+xml"/>
    <link id="remote-record" rel="record alternate" href="https://example.invalid/series.json" media-type="application/json"/>
    <link id="missing-review" rel="review" href="text/missing.xhtml" media-type="application/xhtml+xml"/>
    <collection id="samples" role="preview">
      <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
        <dc:title>Review samples</dc:title>
      </metadata>
      <link rel="sample" href="text/chapter2.xhtml#checklist" media-type="application/xhtml+xml"/>
    </collection>
  </collection>',
            $epub3OpfXml
        );

        $seriesRecord = '{"kind":"series","source":"wordpress-epub"}';
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithCollections],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="install">Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="checklist">Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/series.json', 'data' => $seriesRecord],
        ]));

        $collections = $epub->collections();
        $summary = $epub->summary();

        $t->same(1, count($collections));
        $series = $collections[0];
        $t->same('series', $series['id']);
        $t->same('series', $series['role']);
        $t->same(['series'], $series['roleTokens']);
        $t->same('en', $series['language']);
        $t->same('ltr', $series['direction']);
        $t->same('Migration packets', $series['metadata']['title']);
        $t->same('2', $series['metadata']['properties']['group-position'][0]);
        $t->same(4, $series['linkCount']);
        $t->same(3, $series['localLinkCount']);
        $t->same(1, $series['externalLinkCount']);
        $t->same(1, $series['missingLinkCount']);
        $t->same(['record', 'first', 'alternate', 'review'], $series['linkRelTokens']);
        $t->same(['record' => 2, 'first' => 1, 'alternate' => 1, 'review' => 1], $series['linkRelCounts']);
        $t->same('/EPUB/meta/series.json', $series['links'][0]['target']);
        $t->same('/EPUB/meta/series.json', $series['links'][0]['partName']);
        $t->same(true, $series['links'][0]['exists']);
        $t->same(strlen($seriesRecord), $series['links'][0]['byteLength']);
        $t->same('series-record', $series['linksByRel']['record'][0]['id']);
        $t->same('/EPUB/text/chapter1.xhtml#install', $series['linksByRel']['first'][0]['target']);
        $t->same('remote-record', $series['linksByRel']['record'][1]['id']);
        $t->same(true, $series['linksByRel']['record'][1]['external']);
        $t->same('missing-collection-link-target', $series['linksByRel']['review'][0]['diagnostics'][0]['type']);
        $t->same(2, $series['diagnosticCount']);
        $t->same(['external-collection-link-target', 'missing-collection-link-target'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $series['diagnostics']));
        $t->same(1, count($series['children']));
        $t->same('samples', $series['children'][0]['id']);
        $t->same('Review samples', $series['children'][0]['metadata']['title']);
        $t->same('/EPUB/text/chapter2.xhtml#checklist', $series['children'][0]['links'][0]['target']);

        $t->same($collections, $summary['collections']);
        $t->same($collections, $summary['wordpressImport']['collections']);
        $t->same(['Migration packets', 'Review samples'], $summary['wordpressImport']['collectionTitles']);
        $t->same(['/EPUB/meta/series.json', '/EPUB/text/chapter1.xhtml#install', 'https://example.invalid/series.json', '/EPUB/text/missing.xhtml', '/EPUB/text/chapter2.xhtml#checklist'], $summary['wordpressImport']['collectionLinkTargets']);
        $t->same(['external-collection-link-target', 'missing-collection-link-target'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $summary['wordpressImport']['collectionDiagnostics']));
    },

    'preserves OPF metadata link records for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithLinks = str_replace(
            '</metadata>',
            '    <link id="review-record" rel="record alternate" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org reviewer" hreflang="en" title="Review record"/>
    <link id="remote-onix" rel="record" href="https://metadata.example.invalid/onix.xml" media-type="application/xml" properties="onix"/>
    <link id="creator-voicing" rel="voicing" refines="#creator" href="audio/creator-name.mp3" media-type="audio/mpeg" properties="pronunciation"/>
    <link id="missing-record" rel="record" href="meta/missing-record.json" media-type="application/json"/>
  </metadata>',
            $epub3OpfXml
        );
        $opfWithLinks = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="creator-audio" href="audio/creator-name.mp3" media-type="audio/mpeg"/>',
            $opfWithLinks
        );

        $reviewRecord = '{"@context":"https://schema.org","name":"WordPress EPUB review record"}';
        $creatorAudio = 'MP3-CREATOR-NAME';
        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithLinks],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/review-record.json', 'data' => $reviewRecord],
            ['name' => 'EPUB/audio/creator-name.mp3', 'data' => $creatorAudio],
        ]));

        $links = $epub->packageLinks();
        $summary = $epub->summary();

        $t->same(4, count($links));
        $t->same('review-record', $links[0]['id']);
        $t->same(['record', 'alternate'], $links[0]['rel']);
        $t->same('/EPUB/meta/review-record.json', $links[0]['target']);
        $t->same('/EPUB/meta/review-record.json', $links[0]['partName']);
        $t->same(true, $links[0]['exists']);
        $t->same(strlen($reviewRecord), $links[0]['byteLength']);
        $t->same(hash('crc32b', $reviewRecord), $links[0]['crc32']);
        $t->same('application/ld+json', $links[0]['mediaType']);
        $t->same(null, $links[0]['manifestId']);
        $t->same(['schema-org', 'reviewer'], $links[0]['properties']);
        $t->same('en', $links[0]['hreflang']);
        $t->same('Review record', $links[0]['title']);
        $t->same('remote-onix', $links[1]['id']);
        $t->same(true, $links[1]['external']);
        $t->same('external-package-link-target', $links[1]['diagnostics'][0]['type']);
        $t->same('creator', $links[2]['subjectId']);
        $t->same('/EPUB/audio/creator-name.mp3', $links[2]['target']);
        $t->same('creator-audio', $links[2]['manifestId']);
        $t->same('audio/mpeg', $links[2]['manifestMediaType']);
        $t->same(strlen($creatorAudio), $links[2]['byteLength']);
        $t->same('missing-package-link-target', $links[3]['diagnostics'][0]['type']);

        $t->same(['record' => 3, 'alternate' => 1, 'voicing' => 1], $summary['packageLinkRelCounts']);
        $t->same('review-record', $summary['packageLinksByRel']['record'][0]['id']);
        $t->same('creator-voicing', $summary['packageLinksByRel']['voicing'][0]['id']);
        $t->same($links, $summary['packageLinks']);
        $t->same($links, $summary['metadata']['links']);
        $t->same($summary['packageLinkRelCounts'], $summary['metadata']['linkRelCounts']);
        $t->same(['/EPUB/meta/review-record.json', 'https://metadata.example.invalid/onix.xml', '/EPUB/audio/creator-name.mp3', '/EPUB/meta/missing-record.json'], $summary['wordpressImport']['packageLinkTargets']);
        $t->same(['external-package-link-target', 'missing-package-link-target'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $summary['wordpressImport']['packageLinkDiagnostics']));
        $t->same('creator-voicing', $summary['wordpressImport']['packageLinksByRel']['voicing'][0]['id']);
    },

    'summarizes OPF metadata link vocabulary tokens for package preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithPrefix = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="schema: https://schema.org/ review: https://example.invalid/epub-review# review: https://example.invalid/review-vocab-2# bad-prefix">',
            $epub3OpfXml
        );
        $opfWithPrefix = str_replace(
            '</metadata>',
            '    <link id="vocab-record" rel="record schema:associatedMedia https://example.invalid/link-rel#review bad/token record unknown:missing" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org review:packet https://example.invalid/props#review bad/property schema-org unknown:flag"/>
  </metadata>',
            $opfWithPrefix
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithPrefix],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/review-record.json', 'data' => '{"name":"package record"}'],
        ]));

        $metadata = $epub->metadata();
        $link = $epub->packageLinks()[0];
        $summary = $epub->summary();
        $relVocabulary = $link['relVocabulary'];
        $propertyVocabulary = $link['propertyVocabulary'];

        $t->same('https://schema.org/', $metadata['prefixBindings']['schema']);
        $t->same('https://example.invalid/review-vocab-2#', $metadata['prefixBindings']['review']);
        $t->same(['duplicate-package-prefix-declaration', 'invalid-package-prefix-declaration'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $metadata['prefixDiagnostics']));

        $t->same(6, $relVocabulary['count']);
        $t->same(5, $relVocabulary['validCount']);
        $t->same(1, $relVocabulary['invalidCount']);
        $t->same(1, $relVocabulary['resolvedCount']);
        $t->same(1, $relVocabulary['absoluteUrlCount']);
        $t->same(1, $relVocabulary['duplicateCount']);
        $t->same('prefixed-nmtoken', $relVocabulary['items'][1]['kind']);
        $t->same('schema', $relVocabulary['items'][1]['prefix']);
        $t->same('associatedMedia', $relVocabulary['items'][1]['localName']);
        $t->same('https://schema.org/associatedMedia', $relVocabulary['items'][1]['iri']);
        $t->same('absolute-url-with-fragment', $relVocabulary['items'][2]['kind']);
        $t->same('invalid-metadata-link-rel-token', $relVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-metadata-link-rel-token', $relVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same('unknown-metadata-link-rel-prefix', $relVocabulary['items'][5]['diagnostics'][0]['type']);

        $t->same(6, $propertyVocabulary['count']);
        $t->same(5, $propertyVocabulary['validCount']);
        $t->same(1, $propertyVocabulary['invalidCount']);
        $t->same(1, $propertyVocabulary['resolvedCount']);
        $t->same(1, $propertyVocabulary['absoluteUrlCount']);
        $t->same(1, $propertyVocabulary['duplicateCount']);
        $t->same('schema-org', $propertyVocabulary['items'][0]['value']);
        $t->same('https://example.invalid/review-vocab-2#packet', $propertyVocabulary['items'][1]['iri']);
        $t->same('absolute-url-with-fragment', $propertyVocabulary['items'][2]['kind']);
        $t->same('invalid-metadata-link-properties-token', $propertyVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-metadata-link-properties-token', $propertyVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same('unknown-metadata-link-properties-prefix', $propertyVocabulary['items'][5]['diagnostics'][0]['type']);

        $vocabulary = $summary['packageLinkVocabulary'];
        $t->same(true, $vocabulary['present']);
        $t->same(1, $vocabulary['linkCount']);
        $t->same(6, $vocabulary['relTokenCount']);
        $t->same(6, $vocabulary['propertyTokenCount']);
        $t->same(2, $vocabulary['resolvedTokenCount']);
        $t->same(2, $vocabulary['absoluteUrlTokenCount']);
        $t->same(2, $vocabulary['duplicateTokenCount']);
        $t->same(6, $vocabulary['diagnosticCount']);
        $t->same($vocabulary, $metadata['linkVocabulary']);
        $t->same($vocabulary, $summary['wordpressImport']['packageLinkVocabulary']);
        $t->same($vocabulary['diagnostics'], $summary['wordpressImport']['packageLinkVocabularyDiagnostics']);
    },

    'summarizes OPF package and collection remote link policy for preflight handoff' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithLinks = str_replace(
            '</metadata>',
            '    <link id="review-record" rel="record alternate" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org reviewer"/>
    <link id="remote-onix" rel="record" href="https://metadata.example.invalid/onix.xml" media-type="application/xml"/>
    <link id="missing-record" rel="record" href="meta/missing-record.json" media-type="application/json"/>
  </metadata>',
            $epub3OpfXml
        );
        $opfWithLinks = str_replace(
            '</spine>',
            '</spine>
  <collection id="series" role="series">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Migration packets</dc:title></metadata>
    <link id="series-record" rel="record" href="meta/series.json" media-type="application/ld+json"/>
    <link id="remote-series" rel="alternate" href="https://example.invalid/epub/series.json" media-type="application/json"/>
    <link id="missing-sample" rel="sample" href="text/missing.xhtml" media-type="application/xhtml+xml"/>
  </collection>',
            $opfWithLinks
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithLinks],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/meta/review-record.json', 'data' => '{"name":"package record"}'],
            ['name' => 'EPUB/meta/series.json', 'data' => '{"name":"series record"}'],
        ]));

        $summary = $epub->summary();
        $policy = $summary['remoteResourcePolicy'];

        $t->same(true, $policy['present']);
        $t->same(6, $policy['itemCount']);
        $t->same(3, $policy['packageLinkCount']);
        $t->same(3, $policy['collectionLinkCount']);
        $t->same(2, $policy['localTargetCount']);
        $t->same(2, $policy['externalTargetCount']);
        $t->same(2, $policy['missingTargetCount']);
        $t->same(2, $policy['remoteNoFetchCount']);
        $t->same(['/EPUB/meta/review-record.json', '/EPUB/meta/series.json'], $policy['localTargets']);
        $t->same(['https://metadata.example.invalid/onix.xml', 'https://example.invalid/epub/series.json'], $policy['externalTargets']);
        $t->same(['/EPUB/meta/missing-record.json', '/EPUB/text/missing.xhtml'], $policy['missingTargets']);
        $t->same(['local-package' => 2, 'remote-no-fetch' => 2, 'missing-package' => 2], $policy['policyCounts']);

        $t->same('package-link', $policy['items'][0]['source']);
        $t->same('review-record', $policy['items'][0]['id']);
        $t->same('local-package', $policy['items'][0]['policy']);
        $t->same('package-link', $policy['items'][1]['source']);
        $t->same('remote-no-fetch', $policy['items'][1]['policy']);
        $t->same('missing-package', $policy['items'][2]['policy']);
        $t->same('collection-link', $policy['items'][3]['source']);
        $t->same('series', $policy['items'][3]['collectionId']);
        $t->same([0], $policy['items'][3]['collectionPath']);
        $t->same('local-package', $policy['items'][3]['policy']);
        $t->same('remote-no-fetch', $policy['items'][4]['policy']);
        $t->same('missing-package', $policy['items'][5]['policy']);
        $t->same(['external-package-link-target', 'missing-package-link-target', 'external-collection-link-target', 'missing-collection-link-target'], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $policy['diagnostics']));
        $t->same($policy, $summary['wordpressImport']['remoteResourcePolicy']);
        $t->same($policy['externalTargets'], $summary['wordpressImport']['remoteResourceExternalTargets']);
        $t->same($policy['diagnostics'], $summary['wordpressImport']['remoteResourcePolicyDiagnostics']);
    },

    'summarizes OPF media-overlay preflight without running playback' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithMediaOverlay = str_replace(
            '</metadata>',
            '    <meta property="media:duration">0:00:07.000</meta>
    <meta property="media:duration" refines="#mo-chapter">0:00:07.000</meta>
  </metadata>',
            $epub3OpfXml
        );
        $opfWithMediaOverlay = str_replace(
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>',
            '<item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image" media-overlay="missing-overlay"/>',
            $opfWithMediaOverlay
        );
        $opfWithMediaOverlay = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter"/>',
            $opfWithMediaOverlay
        );
        $opfWithMediaOverlay = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml" media-overlay="bad-overlay"/>
    <item id="audio" href="audio/chapter.mp3" media-type="audio/mpeg"/>
    <item id="mo-chapter" href="overlays/chapter.smil" media-type="application/smil+xml"/>
    <item id="bad-overlay" href="styles/book.css" media-type="text/css"/>',
            $opfWithMediaOverlay
        );

        $smil = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL">
  <body>
    <seq id="chapter-seq">
      <par id="intro">
        <text src="../text/chapter1.xhtml#intro"/>
        <audio src="../audio/chapter.mp3" clipBegin="0:00:00.000" clipEnd="0:00:04.250"/>
      </par>
      <par id="remote-note">
        <text src="../text/missing.xhtml"/>
        <audio src="https://cdn.example.test/audio/review.mp3" clipBegin="4.25s" clipEnd="7s"/>
      </par>
      <par id="invalid-clip">
        <text src="../text/chapter2.xhtml"/>
        <audio src="../audio/chapter.mp3" clipBegin="bad-clock" clipEnd="6s"/>
      </par>
    </seq>
  </body>
</smil>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMediaOverlay],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="intro">Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/audio/chapter.mp3', 'data' => 'MP3'],
            ['name' => 'EPUB/overlays/chapter.smil', 'data' => $smil],
        ]));

        $overlays = $epub->mediaOverlays();
        $summary = $epub->summary();
        $chapter = $overlays['itemsById']['mo-chapter'];
        $bad = $overlays['itemsById']['bad-overlay'];
        $missing = $overlays['itemsById']['missing-overlay'];

        $t->same('mo-chapter', $epub->manifestItem('chapter1')['mediaOverlay']);
        $t->same('mo-chapter', $epub->spine()[0]['mediaOverlay']);
        $t->same(true, $overlays['present']);
        $t->same(3, $overlays['overlayCount']);
        $t->same(3, $overlays['referencedContentItemCount']);
        $t->same(2, $overlays['resolvedOverlayCount']);
        $t->same(1, $overlays['missingOverlayCount']);
        $t->same(2, $overlays['durationCount']);

        $t->same('mo-chapter', $chapter['id']);
        $t->same('/EPUB/overlays/chapter.smil', $chapter['partName']);
        $t->same('application/smil+xml', $chapter['mediaType']);
        $t->same(true, $chapter['exists']);
        $t->same(strlen($smil), $chapter['byteLength']);
        $t->same(hash('crc32b', $smil), $chapter['crc32']);
        $t->same(['chapter1'], $chapter['referencedByIds']);
        $t->same('0:00:07.000', $chapter['duration']);
        $t->same(7.0, $chapter['durationSeconds']);
        $t->same(3, $chapter['itemCount']);
        $t->same(['/EPUB/text/chapter1.xhtml#intro', '/EPUB/text/missing.xhtml', '/EPUB/text/chapter2.xhtml'], $chapter['textTargets']);
        $t->same(['/EPUB/audio/chapter.mp3', 'https://cdn.example.test/audio/review.mp3'], $chapter['audioTargets']);
        $t->same('/EPUB/text/chapter1.xhtml#intro', $chapter['items'][0]['textTarget']);
        $t->same('chapter1', $chapter['items'][0]['textManifestId']);
        $t->same('/EPUB/audio/chapter.mp3', $chapter['items'][0]['audioTarget']);
        $t->same('audio', $chapter['items'][0]['audioManifestId']);
        $t->same(4.25, $chapter['items'][0]['clipDurationSeconds']);
        $t->same('missing-media-overlay-text-reference', $chapter['items'][1]['diagnostics'][0]['type']);
        $t->same('external-media-overlay-audio-reference', $chapter['items'][1]['diagnostics'][1]['type']);
        $t->same(2.75, $chapter['items'][1]['clipDurationSeconds']);
        $t->same('invalid-media-overlay-clip-begin', $chapter['items'][2]['diagnostics'][0]['type']);

        $t->same('unexpected-media-overlay-type', $bad['diagnostics'][0]['type']);
        $t->same('text/css', $bad['mediaType']);
        $t->same('missing-media-overlay-manifest-item', $missing['diagnostics'][0]['type']);
        $t->same(['missing-overlay', 'mo-chapter', 'bad-overlay'], array_column($overlays['items'], 'id'));
        $t->same(['/EPUB/text/chapter1.xhtml#intro', '/EPUB/text/missing.xhtml', '/EPUB/text/chapter2.xhtml'], $overlays['textTargets']);
        $t->same(['/EPUB/audio/chapter.mp3', 'https://cdn.example.test/audio/review.mp3'], $overlays['audioTargets']);
        $t->same([
            'missing-media-overlay-manifest-item',
            'missing-media-overlay-text-reference',
            'external-media-overlay-audio-reference',
            'invalid-media-overlay-clip-begin',
            'unexpected-media-overlay-type',
        ], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $overlays['diagnostics']));

        $t->same($overlays, $summary['mediaOverlays']);
        $t->same($overlays, $summary['wordpressImport']['mediaOverlays']);
        $t->same($overlays['items'], $summary['wordpressImport']['mediaOverlayItems']);
        $t->same($overlays['textTargets'], $summary['wordpressImport']['mediaOverlayTargets']);
        $t->same($overlays['audioTargets'], $summary['wordpressImport']['mediaOverlayAudioTargets']);
        $t->same($overlays['diagnostics'], $summary['wordpressImport']['mediaOverlayDiagnostics']);
    },

    'summarizes OPF manifest fallback chains for compact package preflight' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $fallbackXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Fallback review content.</p></body></html>';
        $fallbackCss = 'body { color: #123456; }';
        $opfWithFallbacks = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="custom-ok" href="widgets/custom-ok.bin" media-type="application/x-review-widget" fallback="ok-fallback" fallback-style="widget-style"/>
    <item id="ok-fallback" href="text/ok-fallback.xhtml" media-type="application/xhtml+xml"/>
    <item id="poster-heic" href="images/poster.heic" media-type="image/heic" fallback="cover"/>
    <item id="custom-missing" href="widgets/custom-missing.bin" media-type="application/x-review-widget" fallback="missing-fallback"/>
    <item id="custom-cycle-a" href="widgets/cycle-a.bin" media-type="application/x-review-widget" fallback="custom-cycle-b"/>
    <item id="custom-cycle-b" href="widgets/cycle-b.bin" media-type="application/x-review-widget" fallback="custom-cycle-a"/>
    <item id="bad-style-widget" href="widgets/bad-style.bin" media-type="application/x-review-widget" fallback-style="cover"/>
    <item id="missing-style-widget" href="widgets/missing-style.bin" media-type="application/x-review-widget" fallback-style="missing-style"/>
    <item id="style-cycle-a" href="widgets/style-cycle-a.bin" media-type="application/x-review-widget" fallback-style="style-cycle-b"/>
    <item id="style-cycle-b" href="widgets/style-cycle-b.bin" media-type="application/x-review-widget" fallback-style="style-cycle-a"/>
    <item id="widget-style" href="styles/widget.css" media-type="text/css"/>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithFallbacks],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/text/ok-fallback.xhtml', 'data' => $fallbackXhtml],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/styles/widget.css', 'data' => $fallbackCss],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/images/poster.heic', 'data' => 'HEIC'],
            ['name' => 'EPUB/widgets/custom-ok.bin', 'data' => 'CUSTOM-OK'],
            ['name' => 'EPUB/widgets/custom-missing.bin', 'data' => 'CUSTOM-MISSING'],
            ['name' => 'EPUB/widgets/cycle-a.bin', 'data' => 'CYCLE-A'],
            ['name' => 'EPUB/widgets/cycle-b.bin', 'data' => 'CYCLE-B'],
            ['name' => 'EPUB/widgets/bad-style.bin', 'data' => 'BAD-STYLE'],
            ['name' => 'EPUB/widgets/missing-style.bin', 'data' => 'MISSING-STYLE'],
            ['name' => 'EPUB/widgets/style-cycle-a.bin', 'data' => 'STYLE-CYCLE-A'],
            ['name' => 'EPUB/widgets/style-cycle-b.bin', 'data' => 'STYLE-CYCLE-B'],
        ]));

        $fallbacks = $epub->manifestFallbacks();
        $summary = $epub->summary();
        $itemsById = $fallbacks['itemsById'];

        $t->same(true, $fallbacks['present']);
        $t->same(9, $fallbacks['itemCount']);
        $t->same(5, $fallbacks['fallbackCount']);
        $t->same(2, $fallbacks['resolvedFallbackCount']);
        $t->same(3, $fallbacks['fallbackDiagnosticCount']);
        $t->same(5, $fallbacks['fallbackStyleCount']);
        $t->same(1, $fallbacks['resolvedFallbackStyleCount']);
        $t->same(4, $fallbacks['fallbackStyleDiagnosticCount']);

        $ok = $itemsById['custom-ok'];
        $t->same('ok-fallback', $ok['fallbackId']);
        $t->same(true, $ok['fallbackResolved']);
        $t->same(true, $ok['fallbackUsable']);
        $t->same('ok-fallback', $ok['fallbackTerminalId']);
        $t->same('/EPUB/text/ok-fallback.xhtml', $ok['fallbackTerminalPartName']);
        $t->same('application/xhtml+xml', $ok['fallbackTerminalMediaType']);
        $t->same(true, $ok['fallbackTerminalEpubContentDocument']);
        $t->same(strlen($fallbackXhtml), $ok['fallbackChain'][0]['byteLength']);
        $t->same(hash('crc32b', $fallbackXhtml), $ok['fallbackChain'][0]['crc32']);
        $t->same('widget-style', $ok['fallbackStyleId']);
        $t->same(true, $ok['fallbackStyleResolved']);
        $t->same('/EPUB/styles/widget.css', $ok['fallbackStyleTerminalPartName']);
        $t->same(hash('crc32b', $fallbackCss), $ok['fallbackStyleChain'][0]['crc32']);

        $poster = $itemsById['poster-heic'];
        $t->same('cover', $poster['fallbackId']);
        $t->same(true, $poster['fallbackResolved']);
        $t->same('image/png', $poster['fallbackTerminalMediaType']);
        $t->same(false, $poster['fallbackTerminalEpubContentDocument']);
        $t->same('image', $poster['fallbackChain'][0]['coreMediaTypeKind']);

        $missing = $itemsById['custom-missing'];
        $t->same(false, $missing['fallbackResolved']);
        $t->same('missing-fallback', $missing['fallbackId']);
        $t->same('missing-manifest-fallback-item', $missing['fallbackDiagnostics'][0]['type']);

        $cycle = $itemsById['custom-cycle-a'];
        $t->same(false, $cycle['fallbackResolved']);
        $t->same(['custom-cycle-b'], array_column($cycle['fallbackChain'], 'id'));
        $t->same('cyclic-manifest-fallback-chain', $cycle['fallbackDiagnostics'][0]['type']);

        $badStyle = $itemsById['bad-style-widget'];
        $t->same('cover', $badStyle['fallbackStyleId']);
        $t->same(false, $badStyle['fallbackStyleResolved']);
        $t->same('non-css-manifest-fallback-style', $badStyle['fallbackStyleDiagnostics'][0]['type']);

        $missingStyle = $itemsById['missing-style-widget'];
        $t->same('missing-manifest-fallback-style-item', $missingStyle['fallbackStyleDiagnostics'][0]['type']);

        $styleCycle = $itemsById['style-cycle-a'];
        $t->same(['style-cycle-b'], array_column($styleCycle['fallbackStyleChain'], 'id'));
        $t->same('cyclic-manifest-fallback-style-chain', $styleCycle['fallbackStyleDiagnostics'][0]['type']);

        $t->same($fallbacks, $summary['manifestFallbacks']);
        $t->same($fallbacks, $summary['wordpressImport']['manifestFallbacks']);
        $t->same(['custom-ok', 'poster-heic', 'custom-missing', 'custom-cycle-a', 'custom-cycle-b'], array_column($summary['wordpressImport']['manifestFallbackItems'], 'id'));
        $t->same(['custom-ok', 'bad-style-widget', 'missing-style-widget', 'style-cycle-a', 'style-cycle-b'], array_column($summary['wordpressImport']['manifestFallbackStyleItems'], 'id'));
        $t->same(['missing-manifest-fallback-item', 'cyclic-manifest-fallback-chain', 'cyclic-manifest-fallback-chain', 'non-css-manifest-fallback-style', 'missing-manifest-fallback-style-item', 'cyclic-manifest-fallback-style-chain', 'cyclic-manifest-fallback-style-chain'], array_column($summary['wordpressImport']['manifestFallbackDiagnostics'], 'type'));
    },

    'summarizes OPF manifest resource properties for compact package preflight' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithResourceProperties = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="en" prefix="schema: https://schema.org/ review: https://example.invalid/epub-review#">',
            $epub3OpfXml
        );
        $opfWithResourceProperties = str_replace(
            '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>',
            '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav rendition:layout-pre-paginated"/>',
            $opfWithResourceProperties
        );
        $opfWithResourceProperties = str_replace(
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" properties="mathml svg remote-resources schema:encodingFormat review:source-record unknown:review-flag"/>',
            $opfWithResourceProperties
        );
        $opfWithResourceProperties = str_replace(
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter2" href="text/chapter2.xhtml" media-type="application/xhtml+xml" properties="scripted switch"/>',
            $opfWithResourceProperties
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithResourceProperties],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><math/><svg/></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><script>review()</script></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $report = $epub->resourceProperties();
        $summary = $epub->summary();

        $t->same(1, $report['summary']['navCount']);
        $t->same(1, $report['summary']['coverImageCount']);
        $t->same(1, $report['summary']['mathmlCount']);
        $t->same(1, $report['summary']['svgCount']);
        $t->same(1, $report['summary']['remoteResourcesCount']);
        $t->same(1, $report['summary']['scriptedCount']);
        $t->same(1, $report['summary']['switchCount']);
        $t->same(2, $report['summary']['reviewRequiredCount']);

        $t->same('chapter1', $report['itemsByProperty']['mathml'][0]['id']);
        $t->same('chapter1', $report['itemsByProperty']['remote-resources'][0]['id']);
        $t->same('chapter2', $report['itemsByProperty']['scripted'][0]['id']);
        $t->same('/EPUB/text/chapter1.xhtml', $report['itemsById']['chapter1']['partName']);
        $t->same(['mathml', 'svg', 'remote-resources'], $report['itemsById']['chapter1']['reviewFlags']);
        $t->same(['scripted', 'switch'], $report['itemsById']['chapter2']['reviewFlags']);
        $t->same(true, $report['itemsById']['chapter1']['reviewRequired']);
        $t->same('chapter2', $report['reviewItems'][1]['id']);

        $vocabulary = $report['propertyVocabulary'];
        $t->same(true, $vocabulary['present']);
        $t->same(4, $vocabulary['itemCount']);
        $t->same(11, $vocabulary['propertyTokenCount']);
        $t->same(4, $vocabulary['prefixedPropertyCount']);
        $t->same(3, $vocabulary['resolvedPropertyCount']);
        $t->same(1, $vocabulary['unresolvedPropertyCount']);
        $t->same('http://www.idpf.org/vocab/rendition/#layout-pre-paginated', $vocabulary['itemsById']['nav']['propertyVocabulary']['items'][1]['vocabulary']['iri']);
        $t->same('https://schema.org/encodingFormat', $report['itemsById']['chapter1']['propertyVocabulary']['items'][3]['vocabulary']['iri']);
        $t->same('https://example.invalid/epub-review#source-record', $report['itemsById']['chapter1']['propertyVocabulary']['items'][4]['vocabulary']['iri']);
        $t->same(['schema:encodingFormat'], $vocabulary['byPrefix']['schema']['properties']);
        $t->same(['chapter1'], $vocabulary['byPrefix']['schema']['manifestIds']);
        $t->same(['unknown:review-flag'], $vocabulary['byPrefix']['unknown']['properties']);
        $t->same(1, $vocabulary['diagnosticCount']);
        $t->same('unknown-manifest-property-prefix', $vocabulary['diagnostics'][0]['type']);
        $t->same('unknown:review-flag', $vocabulary['diagnostics'][0]['property']);

        $t->same($report, $summary['resourceProperties']);
        $t->same($report, $summary['wordpressImport']['resourceProperties']);
        $t->same($report['summary'], $summary['wordpressImport']['resourcePropertySummary']);
        $t->same($report['reviewItems'], $summary['wordpressImport']['resourcePropertyReviewItems']);
        $t->same($vocabulary['diagnostics'], $summary['wordpressImport']['resourcePropertyDiagnostics']);
    },

    'summarizes OPF package rendition metadata for compact package preflight' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithRenditionMetadata = str_replace(
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>',
            '<meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
    <meta id="fixed-layout" property="rendition:layout" xml:lang="en" dir="ltr">pre-paginated</meta>
    <meta property="rendition:layout">reflowable</meta>
    <meta id="scroll-flow" property="rendition:flow" xml:lang="en" dir="ltr">scrolled-continuous</meta>
    <meta property="rendition:flow">sideways</meta>
    <meta property="rendition:orientation" content="landscape"/>
    <meta property="rendition:spread">diagonal</meta>
    <meta property="rendition:spread">none</meta>
    <meta property="rendition:viewport">width=1024, height=768</meta>
    <meta id="bad-viewport" property="rendition:viewport">width=cover,height=0,scale=1</meta>',
            $epub3OpfXml
        );

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithRenditionMetadata],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ]));

        $rendition = $epub->metadata()['renditionLayout'];
        $summary = $epub->summary();

        $t->same(true, $rendition['present']);
        $t->same(true, $rendition['fixedLayout']);
        $t->same('pre-paginated', $rendition['layout']);
        $t->same('pre-paginated', $rendition['layoutRaw']);
        $t->same('fixed-layout', $rendition['layoutProperty']['selected']['id']);
        $t->same('en', $rendition['layoutProperty']['selected']['language']);
        $t->same('ltr', $rendition['layoutProperty']['selected']['direction']);
        $t->same(false, $rendition['layoutProperty']['valid']);
        $t->same(['pre-paginated', 'reflowable'], $rendition['layoutProperty']['diagnostics'][0]['values']);
        $t->same('scrolled-continuous', $rendition['flow']);
        $t->same('scrolled-continuous', $rendition['flowRaw']);
        $t->same('scroll-flow', $rendition['flowProperty']['selected']['id']);
        $t->same('en', $rendition['flowProperty']['selected']['language']);
        $t->same('ltr', $rendition['flowProperty']['selected']['direction']);
        $t->same(1, $rendition['flowProperty']['invalidCount']);
        $t->same('invalid-rendition-flow-value', $rendition['flowProperty']['diagnostics'][0]['type']);
        $t->same('landscape', $rendition['orientation']);
        $t->same('none', $rendition['spread']);
        $t->same(1, $rendition['spreadProperty']['invalidCount']);
        $t->same('invalid-rendition-spread-value', $rendition['spreadProperty']['diagnostics'][0]['type']);

        $t->same('width=1024, height=768', $rendition['viewportRaw']);
        $t->same(1024, $rendition['viewportWidth']);
        $t->same(768, $rendition['viewportHeight']);
        $t->same(2, $rendition['viewportCount']);
        $t->same(1, $rendition['validViewportCount']);
        $t->same(1, $rendition['invalidViewportCount']);
        $t->same(['width' => '1024', 'height' => '768'], $rendition['viewport']['parameters']);
        $t->same('bad-viewport', $rendition['viewports'][1]['id']);
        $t->same(false, $rendition['viewports'][1]['valid']);
        $t->same(['width' => 'cover', 'height' => '0', 'scale' => '1'], $rendition['viewports'][1]['parameters']);
        $t->same(['conflicting-rendition-layout-values', 'invalid-rendition-flow-value', 'invalid-rendition-spread-value', 'invalid-rendition-viewport-width', 'invalid-rendition-viewport-height', 'unknown-rendition-viewport-parameter'], array_column($rendition['diagnostics'], 'type'));

        $t->same($rendition, $summary['renditionLayout']);
        $t->same($rendition, $summary['metadata']['renditionLayout']);
        $t->same($rendition, $summary['wordpressImport']['metadataDetails']['renditionLayout']);
    },

    'summarizes OCF encrypted resource exposure for compact package preflight' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $opfWithEncryptedResources = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/book.css" media-type="text/css"/>
    <item id="locked-style" href="styles/locked.css" media-type="text/css"/>
    <item id="locked-audio" href="audio/locked.mp3" media-type="audio/mpeg"/>
    <item id="font-main" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>',
            $epub3OpfXml
        );
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/images/cover.png"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/styles/locked.css"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="EPUB/audio/locked.mp3"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
    <CipherData><CipherReference URI="EPUB/fonts/source.otf"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithEncryptedResources],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/styles/locked.css', 'data' => 'body { color: red; }'],
            ['name' => 'EPUB/audio/locked.mp3', 'data' => 'LOCKED-MP3'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/fonts/source.otf', 'data' => 'OBFUSCATED-FONT'],
        ]));

        $encryption = $epub->encryption();
        $summary = $epub->summary();
        $exposure = $encryption['exposure'];
        $itemsByPart = [];
        foreach ($exposure['items'] as $item) {
            $itemsByPart[$item['partName']] = $item;
        }

        $t->same(true, $encryption['present']);
        $t->same('/META-INF/encryption.xml', $encryption['part']);
        $t->same(['/EPUB/images/cover.png', '/EPUB/styles/locked.css', '/EPUB/audio/locked.mp3', '/EPUB/fonts/source.otf'], $encryption['encryptedParts']);
        $t->same('/EPUB/fonts/source.otf', $encryption['obfuscatedFonts'][0]['partName']);
        $t->same([], $encryption['diagnostics']);

        $t->same(true, $exposure['present']);
        $t->same(4, $exposure['itemCount']);
        $t->same(4, $exposure['blockedByteExposureCount']);
        $t->same(1, $exposure['obfuscatedFontCount']);
        $t->same(3, $exposure['nonObfuscatedEncryptedCount']);
        $t->same(3, $exposure['attachmentCandidateBlockedCount']);
        $t->same(['audio', 'cover-image', 'font', 'stylesheet'], $exposure['roles']);
        $t->same([
            'audio' => 1,
            'cover-image' => 1,
            'font' => 1,
            'stylesheet' => 1,
        ], $exposure['roleCounts']);
        $t->same(['/EPUB/fonts/source.otf'], $exposure['obfuscatedFontParts']);
        $t->same(['/EPUB/audio/locked.mp3', '/EPUB/images/cover.png', '/EPUB/styles/locked.css'], $exposure['nonObfuscatedEncryptedParts']);

        $t->same('stylesheet', $itemsByPart['/EPUB/styles/locked.css']['role']);
        $t->same('encrypted-resource-review', $itemsByPart['/EPUB/styles/locked.css']['reviewPolicy']);
        $t->same('encrypted-resource-bytes-blocked', $itemsByPart['/EPUB/styles/locked.css']['byteExposurePolicy']);
        $t->same(false, $itemsByPart['/EPUB/styles/locked.css']['attachmentCandidateBlocked']);
        $t->same('cover-image', $itemsByPart['/EPUB/images/cover.png']['role']);
        $t->same(true, $itemsByPart['/EPUB/images/cover.png']['attachmentCandidateBlocked']);
        $t->same('audio', $itemsByPart['/EPUB/audio/locked.mp3']['role']);
        $t->same(true, $itemsByPart['/EPUB/audio/locked.mp3']['attachmentCandidateBlocked']);
        $t->same('font', $itemsByPart['/EPUB/fonts/source.otf']['role']);
        $t->same('obfuscated-font-review', $itemsByPart['/EPUB/fonts/source.otf']['reviewPolicy']);
        $t->same('obfuscated-font-bytes-blocked', $itemsByPart['/EPUB/fonts/source.otf']['byteExposurePolicy']);

        $t->same(true, $epub->manifestItem('cover')['encrypted']);
        $t->same(false, $epub->manifestItem('cover')['canExposeBytes']);
        $t->same('cover-image', $epub->manifestItem('cover')['encryption']['role']);
        $t->same('stylesheet', $epub->manifestItem('locked-style')['encryption']['role']);
        $t->same('audio', $epub->manifestItem('locked-audio')['encryption']['role']);
        $t->same('font', $epub->manifestItem('font-main')['encryption']['role']);
        $t->same('obfuscated-font-review', $epub->manifestItem('font-main')['encryption']['reviewPolicy']);

        $t->same($encryption, $summary['encryption']);
        $t->same($encryption, $summary['wordpressImport']['encryption']);
        $t->same($exposure, $summary['wordpressImport']['encryptedResourceExposure']);
        $t->same([], $summary['wordpressImport']['encryptedResourceDiagnostics']);
    },

    'summarizes compact EPUB package validation diagnostics for review handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithReviewDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:diagnostic-review</dc:identifier>
    <dc:title>Diagnostic EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="nav-css" href="styles/nav.css" media-type="text/css" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter-alias" href="chapter.xhtml#duplicate" media-type="application/xhtml+xml"/>
    <item id="audio" href="audio/chapter.mp3" media-type="audio/mpeg"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="audio" linear="no"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithReviewDiagnostics],
            ['name' => 'EPUB/styles/nav.css', 'data' => 'nav { display: block; }'],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Diagnostic</body></html>'],
            ['name' => 'EPUB/audio/chapter.mp3', 'data' => 'MP3'],
        ]));

        $validation = $epub->validationReport();
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same('3.0', $validation['packageVersion']);
        $t->same(true, $validation['epub3']);
        $t->same(7, $validation['diagnosticCount']);
        $t->same([
            'missing-epub-metadata-language',
            'missing-epub3-modified-metadata',
            'nav-property-non-xhtml-manifest-item',
            'manifest-href-fragment-component',
            'missing-epub3-nav-document',
            'duplicate-manifest-part-target',
            'non-content-document-spine-item',
        ], array_column($validation['diagnostics'], 'type'));

        $t->same(false, $validation['metadata']['valid']);
        $t->same(true, $validation['metadata']['titlePresent']);
        $t->same(true, $validation['metadata']['identifierPresent']);
        $t->same(false, $validation['metadata']['languagePresent']);
        $t->same(false, $validation['metadata']['modifiedPresent']);
        $t->same(['missing-epub-metadata-language', 'missing-epub3-modified-metadata'], array_column($validation['metadata']['diagnostics'], 'type'));

        $t->same(false, $validation['manifest']['valid']);
        $t->same(4, $validation['manifest']['itemCount']);
        $t->same(1, $validation['manifest']['navItemCount']);
        $t->same(0, $validation['manifest']['usableNavItemCount']);
        $t->same(['nav-css'], array_column($validation['manifest']['navItems'], 'id'));
        $t->same(['nav-css'], array_column($validation['manifest']['invalidNavItems'], 'id'));
        $t->same(1, $validation['manifest']['duplicatePartCount']);
        $t->same('/EPUB/chapter.xhtml', $validation['manifest']['duplicatePartItems'][0]['partName']);
        $t->same(['chapter', 'chapter-alias'], $validation['manifest']['duplicatePartItems'][0]['ids']);
        $t->same(1, $validation['manifest']['hrefSuffixCount']);
        $t->same('chapter-alias', $validation['manifest']['hrefSuffixItems'][0]['id']);
        $t->same('duplicate', $validation['manifest']['hrefSuffixItems'][0]['fragment']);

        $t->same(false, $validation['spine']['valid']);
        $t->same(2, $validation['spine']['itemCount']);
        $t->same(1, $validation['spine']['linearCount']);
        $t->same(1, $validation['spine']['nonLinearCount']);
        $t->same(1, $validation['spine']['nonContentDocumentCount']);
        $t->same('audio', $validation['spine']['nonContentDocumentItems'][0]['idref']);

        $t->same(true, $validation['navigation']['valid']);
        $t->same(null, $validation['navigation']['source']);
        $t->same(0, $validation['navigation']['entryCount']);
        $t->same([], $validation['navigation']['diagnostics']);

        $opfWithNavTargetDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-diagnostic-review</dc:identifier>
    <dc:title>Navigation diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T12:04:26Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="nav-alt" href="nav-alt.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML;
        $navWithMissingAndRemoteTargets = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Navigation target diagnostics</h1>
      <ol>
        <li><a href="chapter.xhtml">Start</a></li>
        <li><a href="missing.xhtml">Missing appendix</a></li>
        <li><a href="https://example.invalid/remote.xhtml">Remote appendix</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epubWithNavDiagnostics = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavTargetDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithMissingAndRemoteTargets],
            ['name' => 'EPUB/nav-alt.xhtml', 'data' => $navWithMissingAndRemoteTargets],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Start</body></html>'],
        ]));
        $navValidation = $epubWithNavDiagnostics->validationReport();

        $t->same(false, $navValidation['valid']);
        $t->same(3, $navValidation['diagnosticCount']);
        $t->same(['multiple-epub3-nav-documents', 'missing-navigation-target', 'external-navigation-target'], array_column($navValidation['diagnostics'], 'type'));
        $t->same(2, $navValidation['manifest']['usableNavItemCount']);
        $t->same(['nav', 'nav-alt'], array_column($navValidation['manifest']['usableNavItems'], 'id'));
        $t->same('nav', $navValidation['navigation']['source']);
        $t->same(3, $navValidation['navigation']['entryCount']);
        $t->same(1, $navValidation['navigation']['missingTargetCount']);
        $t->same(1, $navValidation['navigation']['externalTargetCount']);
        $t->same('/EPUB/missing.xhtml', $navValidation['navigation']['diagnostics'][0]['partName']);
        $t->same('https://example.invalid/remote.xhtml', $navValidation['navigation']['diagnostics'][1]['target']);

        $t->same($validation, $summary['validation']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
    },

    'reports compact EPUB nav document diagnostics for validation handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithNavDocumentDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-document-diagnostics</dc:identifier>
    <dc:title>Navigation document diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T19:30:47Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter2" href="chapter2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
  </spine>
</package>
XML;
        $navWithDocumentDiagnostics = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="hidden-toc" epub:type="toc" hidden="hidden">
      <h1>Hidden source contents</h1>
    </nav>
    <nav id="visible-toc" epub:type="toc">
      <h2>Visible contents</h2>
      <ol>
        <li><a href="chapter1.xhtml">Imported packet</a></li>
      </ol>
    </nav>
    <nav id="untyped-review">
      <ol>
        <li><a href="chapter2.xhtml">Untyped review trail</a></li>
      </ol>
    </nav>
    <nav id="print-pages" epub:type="page-list">
      <h2>Print page list</h2>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavDocumentDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithDocumentDiagnostics],
            ['name' => 'EPUB/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter 1</body></html>'],
            ['name' => 'EPUB/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter 2</body></html>'],
        ]));
        $validation = $epub->validationReport();
        $navigation = $validation['navigation'];
        $documentDiagnostics = $navigation['documentDiagnostics'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same(7, $validation['diagnosticCount']);
        $t->same([
            'hidden-primary-nav-section',
            'missing-nav-section-ordered-list',
            'empty-nav-section',
            'missing-nav-section-type',
            'missing-nav-section-ordered-list',
            'empty-nav-section',
            'duplicate-primary-nav-section',
        ], array_column($validation['diagnostics'], 'type'));

        $t->same('nav', $navigation['source']);
        $t->same(4, $navigation['sectionCount']);
        $t->same(2, $navigation['entryCount']);
        $t->same(2, $navigation['localTargetCount']);
        $t->same(0, $navigation['missingTargetCount']);
        $t->same(0, $navigation['externalTargetCount']);
        $t->same(7, $navigation['documentDiagnosticCount']);
        $t->same(7, $navigation['diagnosticCount']);

        $t->same(true, $documentDiagnostics['present']);
        $t->same('/EPUB/nav.xhtml', $documentDiagnostics['part']);
        $t->same(4, $documentDiagnostics['sectionCount']);
        $t->same(3, $documentDiagnostics['primarySectionCount']);
        $t->same(2, $documentDiagnostics['tocSectionCount']);
        $t->same(0, $documentDiagnostics['landmarksSectionCount']);
        $t->same(1, $documentDiagnostics['pageListSectionCount']);
        $t->same(1, $documentDiagnostics['duplicatePrimaryTypeCount']);
        $t->same(2, $documentDiagnostics['emptySectionCount']);
        $t->same(1, $documentDiagnostics['hiddenPrimarySectionCount']);
        $t->same(2, $documentDiagnostics['missingOrderedListSectionCount']);
        $t->same(1, $documentDiagnostics['untypedSectionCount']);
        $t->same('hidden-toc', $documentDiagnostics['diagnostics'][0]['sectionId']);
        $t->same(['toc'], $documentDiagnostics['diagnostics'][0]['sectionTypes']);
        $t->same('untyped-review', $documentDiagnostics['diagnostics'][3]['sectionId']);
        $t->same('toc', $documentDiagnostics['diagnostics'][6]['sectionType']);
        $t->same([0, 1], $documentDiagnostics['diagnostics'][6]['sectionIndexes']);
        $t->same(['hidden-toc', 'visible-toc'], $documentDiagnostics['diagnostics'][6]['sectionIds']);

        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
    },

    'reports compact EPUB nav heading diagnostics for validation handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithNavHeadingDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-heading-diagnostics</dc:identifier>
    <dc:title>Navigation heading diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T20:40:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
  </spine>
</package>
XML;
        $navWithMissingHeading = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <ol>
        <li><a href="chapter1.xhtml">Imported packet</a></li>
      </ol>
    </nav>
    <nav id="print-pages" epub:type="page-list">
      <h2>Print page list</h2>
      <ol>
        <li><a href="chapter1.xhtml#page-1">1</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavHeadingDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithMissingHeading],
            ['name' => 'EPUB/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter 1</h1><span id="page-1"></span></body></html>'],
        ]));
        $validation = $epub->validationReport();
        $navigation = $validation['navigation'];
        $documentDiagnostics = $navigation['documentDiagnostics'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same(['missing-primary-nav-section-heading'], array_column($validation['diagnostics'], 'type'));
        $t->same(1, $navigation['diagnosticCount']);
        $t->same(1, $navigation['documentDiagnosticCount']);
        $t->same(2, $navigation['entryCount']);
        $t->same(2, $navigation['localTargetCount']);
        $t->same(0, $navigation['missingTargetCount']);
        $t->same(true, $documentDiagnostics['present']);
        $t->same('/EPUB/nav.xhtml', $documentDiagnostics['part']);
        $t->same(2, $documentDiagnostics['primarySectionCount']);
        $t->same(1, $documentDiagnostics['missingHeadingSectionCount']);
        $t->same(0, $documentDiagnostics['missingOrderedListSectionCount']);
        $t->same('main-toc', $documentDiagnostics['diagnostics'][0]['sectionId']);
        $t->same(['toc'], $documentDiagnostics['diagnostics'][0]['sectionTypes']);
        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
    },

    'reports compact EPUB nav item label diagnostics for validation handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithNavItemLabelDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-item-label-diagnostics</dc:identifier>
    <dc:title>Navigation item label diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T22:47:34Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter2" href="chapter2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2"/>
  </spine>
</package>
XML;
        $navWithMissingItemLabels = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="chapter1.xhtml"></a></li>
        <li><a href="chapter2.xhtml">Chapter two</a></li>
      </ol>
    </nav>
    <nav id="print-pages" epub:type="page-list">
      <h2>Print page list</h2>
      <ol>
        <li><a href="chapter1.xhtml#page-1"></a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavItemLabelDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithMissingItemLabels],
            ['name' => 'EPUB/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter 1</h1><span id="page-1"></span></body></html>'],
            ['name' => 'EPUB/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter 2</h1></body></html>'],
        ]));
        $validation = $epub->validationReport();
        $navigation = $validation['navigation'];
        $documentDiagnostics = $navigation['documentDiagnostics'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same([
            'missing-primary-nav-item-label',
            'empty-nav-item-label',
            'missing-primary-nav-item-label',
            'empty-nav-item-label',
        ], array_column($validation['diagnostics'], 'type'));
        $t->same(4, $navigation['diagnosticCount']);
        $t->same(4, $navigation['documentDiagnosticCount']);
        $t->same(3, $navigation['entryCount']);
        $t->same(3, $navigation['localTargetCount']);
        $t->same(0, $navigation['missingTargetCount']);
        $t->same(0, $navigation['externalTargetCount']);
        $t->same(true, $documentDiagnostics['present']);
        $t->same(2, $documentDiagnostics['primarySectionCount']);
        $t->same(0, $documentDiagnostics['missingHeadingSectionCount']);
        $t->same(2, $documentDiagnostics['missingEntryLabelCount']);
        $t->same(2, $documentDiagnostics['missingPrimaryItemLabelCount']);

        $tocDiagnostic = $documentDiagnostics['diagnostics'][0];
        $t->same('main-toc', $tocDiagnostic['sectionId']);
        $t->same('toc', $tocDiagnostic['sectionType']);
        $t->same(0, $tocDiagnostic['entryIndex']);
        $t->same('chapter1.xhtml', $tocDiagnostic['href']);
        $t->same('/EPUB/chapter1.xhtml', $tocDiagnostic['target']);
        $t->same(1, $tocDiagnostic['depth']);

        $pageDiagnostic = $documentDiagnostics['diagnostics'][2];
        $t->same('print-pages', $pageDiagnostic['sectionId']);
        $t->same(['page-list'], $pageDiagnostic['sectionTypes']);
        $t->same('/EPUB/chapter1.xhtml#page-1', $pageDiagnostic['target']);
        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
    },

    'reports compact EPUB nav entry label diagnostics for non primary sections' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithNavEntryLabelDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-entry-label-diagnostics</dc:identifier>
    <dc:title>Navigation entry label diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T22:40:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
  </spine>
</package>
XML;
        $navWithEntryLabelDiagnostics = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="chapter1.xhtml#review">Review packet</a></li>
      </ol>
    </nav>
    <nav id="review-trails" epub:type="loa">
      <h2>Review trails</h2>
      <ol>
        <li><a href="chapter1.xhtml#cover"><span class="icon"></span></a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavEntryLabelDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithEntryLabelDiagnostics],
            ['name' => 'EPUB/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="review">Chapter 1</h1><figure id="cover"></figure></body></html>'],
        ]));
        $validation = $epub->validationReport();
        $navigation = $validation['navigation'];
        $documentDiagnostics = $navigation['documentDiagnostics'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same(['missing-nav-entry-label', 'empty-nav-item-label'], array_column($validation['diagnostics'], 'type'));
        $t->same(2, $navigation['diagnosticCount']);
        $t->same(2, $navigation['documentDiagnosticCount']);
        $t->same(2, $navigation['entryCount']);
        $t->same(2, $navigation['localTargetCount']);
        $t->same(0, $navigation['missingTargetCount']);
        $t->same(true, $documentDiagnostics['present']);
        $t->same(1, $documentDiagnostics['primarySectionCount']);
        $t->same(0, $documentDiagnostics['missingHeadingSectionCount']);
        $t->same(0, $documentDiagnostics['missingOrderedListSectionCount']);
        $t->same(1, $documentDiagnostics['missingEntryLabelCount']);
        $t->same(0, $documentDiagnostics['missingPrimaryItemLabelCount']);

        $entryDiagnostic = $documentDiagnostics['diagnostics'][0];
        $t->same('review-trails', $entryDiagnostic['sectionId']);
        $t->same(['loa'], $entryDiagnostic['sectionTypes']);
        $t->same(0, $entryDiagnostic['entryIndex']);
        $t->same('chapter1.xhtml#cover', $entryDiagnostic['href']);
        $t->same('/EPUB/chapter1.xhtml#cover', $entryDiagnostic['target']);
        $t->same(1, $entryDiagnostic['depth']);
        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
    },

    'reports compact EPUB nav item diagnostics for validation handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $opfWithNavItemDiagnostics = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:nav-item-diagnostics</dc:identifier>
    <dc:title>Navigation item diagnostics</dc:title>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-09T20:58:30Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter2" href="chapter2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2"/>
  </spine>
</package>
XML;
        $navWithItemDiagnostics = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li id="empty-label"><a id="empty-label-link" href="chapter1.xhtml"> </a></li>
        <li id="missing-href"><a id="missing-href-link">Untargeted chapter</a></li>
        <li id="missing-label"><ol><li><a href="chapter2.xhtml">Nested recovery</a></li></ol></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNavItemDiagnostics],
            ['name' => 'EPUB/nav.xhtml', 'data' => $navWithItemDiagnostics],
            ['name' => 'EPUB/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter 1</h1></body></html>'],
            ['name' => 'EPUB/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter 2</h1></body></html>'],
        ]));
        $validation = $epub->validationReport();
        $navigation = $validation['navigation'];
        $documentDiagnostics = $navigation['documentDiagnostics'];
        $section = $epub->navigationSections()[0];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same([
            'missing-primary-nav-item-label',
            'empty-nav-item-label',
            'missing-nav-item-href',
            'missing-nav-item-label',
        ], array_column($validation['diagnostics'], 'type'));
        $t->same(3, $navigation['entryCount']);
        $t->same(2, $navigation['localTargetCount']);
        $t->same(4, $navigation['documentDiagnosticCount']);
        $t->same(4, $section['rawItemCount']);
        $t->same(1, $section['emptyItemLabelCount']);
        $t->same(1, $section['missingItemHrefCount']);
        $t->same(1, $section['missingItemLabelCount']);
        $t->same(3, $section['itemDiagnosticCount']);
        $t->same(1, $documentDiagnostics['missingPrimaryItemLabelCount']);
        $t->same(1, $documentDiagnostics['emptyItemLabelCount']);
        $t->same(1, $documentDiagnostics['missingItemHrefCount']);
        $t->same(1, $documentDiagnostics['missingItemLabelCount']);
        $t->same(3, $documentDiagnostics['itemDiagnosticCount']);
        $t->same('empty-label', $documentDiagnostics['diagnostics'][1]['itemId']);
        $t->same('empty-label-link', $documentDiagnostics['diagnostics'][1]['labelId']);
        $t->same('Untargeted chapter', $documentDiagnostics['diagnostics'][2]['label']);
        $t->same('missing-label', $documentDiagnostics['diagnostics'][3]['itemId']);
        $t->same($documentDiagnostics, $summary['wordpressImport']['navDocumentDiagnostics']);
    },

    'reports compact EPUB NCX spine toc binding diagnostics for validation handoff' => static function (TestRunner $t) use ($epubContainerXml): void {
        $ncxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="chapter" playOrder="1">
      <navLabel><text>Legacy chapter</text></navLabel>
      <content src="chapter.xhtml"/>
    </navPoint>
  </navMap>
</ncx>
XML;
        $opfWithNonNcxToc = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:ncx-binding-diagnostics</dc:identifier>
    <dc:title>NCX binding diagnostics</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="wrong-toc" href="wrong-toc.xhtml" media-type="application/xhtml+xml"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine toc="wrong-toc">
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithNonNcxToc],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
            ['name' => 'EPUB/wrong-toc.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Wrong toc</body></html>'],
            ['name' => 'EPUB/toc.ncx', 'data' => $ncxXml],
        ]));
        $validation = $epub->validationReport();
        $summary = $epub->summary();
        $ncx = $validation['ncx'];

        $t->same(false, $validation['valid']);
        $t->same(1, $validation['diagnosticCount']);
        $t->same(['spine-toc-non-ncx-manifest-item'], array_column($validation['diagnostics'], 'type'));
        $t->same(false, $ncx['valid']);
        $t->same(true, $ncx['tocSpecified']);
        $t->same('wrong-toc', $ncx['tocId']);
        $t->same('wrong-toc', $ncx['tocItem']['id']);
        $t->same('/EPUB/wrong-toc.xhtml', $ncx['tocItem']['partName']);
        $t->same('application/xhtml+xml', $ncx['tocItem']['mediaType']);
        $t->same(1, $ncx['manifestNcxItemCount']);
        $t->same('toc', $ncx['manifestNcxItems'][0]['id']);
        $t->same('manifest-scan', $ncx['selectedBy']);
        $t->same('toc', $ncx['selectedItem']['id']);
        $t->same('/EPUB/toc.ncx', $ncx['selectedItem']['partName']);
        $t->same('spine-toc-non-ncx-manifest-item', $ncx['diagnostics'][0]['type']);
        $t->same('/EPUB/wrong-toc.xhtml', $ncx['diagnostics'][0]['partName']);
        $t->same('ncx', $validation['navigation']['source']);
        $t->same(1, $validation['navigation']['entryCount']);
        $t->same(1, $validation['navigation']['localTargetCount']);
        $t->same('/EPUB/toc.ncx', $epub->navigation()['partName']);
        $t->same('Legacy chapter', $epub->navigation()['entries'][0]['label']);
        $t->same('/EPUB/chapter.xhtml', $epub->navigation()['entries'][0]['target']);
        $t->same($validation, $summary['wordpressImport']['packageValidation']);
        $t->same($ncx, $summary['wordpressImport']['packageValidation']['ncx']);

        $opfWithMissingToc = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:ncx-missing-binding-diagnostics</dc:identifier>
    <dc:title>Missing NCX binding diagnostics</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine toc="missing-toc">
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

        $missingTocEpub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $opfWithMissingToc],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body>Chapter</body></html>'],
            ['name' => 'EPUB/toc.ncx', 'data' => $ncxXml],
        ]));
        $missingValidation = $missingTocEpub->validationReport();

        $t->same(false, $missingValidation['valid']);
        $t->same(['missing-spine-toc-manifest-item'], array_column($missingValidation['diagnostics'], 'type'));
        $t->same('missing-toc', $missingValidation['ncx']['tocId']);
        $t->same(null, $missingValidation['ncx']['tocItem']);
        $t->same('manifest-scan', $missingValidation['ncx']['selectedBy']);
        $t->same('toc', $missingValidation['ncx']['selectedItem']['id']);
        $t->same('ncx', $missingValidation['navigation']['source']);
        $t->same('Legacy chapter', $missingTocEpub->navigation()['entries'][0]['label']);
    },

    'reports compact EPUB container rootfile diagnostics for validation handoff' => static function (TestRunner $t) use ($epub3OpfXml, $epub3NavXml): void {
        $containerWithAlternateRootfiles = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
    <rootfile full-path="EPUB/missing-alternate.opf" media-type="application/oebps-package+xml"/>
    <rootfile full-path="EPUB/preview.xhtml" media-type="application/xhtml+xml"/>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

        $epub = EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $containerWithAlternateRootfiles],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review</h1></body></html>'],
            ['name' => 'EPUB/styles/book.css', 'data' => 'body { font-family: serif; }'],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
            ['name' => 'EPUB/preview.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Preview</h1></body></html>'],
        ]));
        $validation = $epub->validationReport();
        $rootfiles = $validation['rootfiles'];
        $summary = $epub->summary();

        $t->same(false, $validation['valid']);
        $t->same(3, $validation['diagnosticCount']);
        $t->same([
            'missing-rootfile-package-part',
            'non-opf-container-rootfile',
            'duplicate-rootfile-package-part',
        ], array_column($validation['diagnostics'], 'type'));
        $t->same(false, $rootfiles['valid']);
        $t->same(0, $rootfiles['selectedIndex']);
        $t->same('/EPUB/package.opf', $rootfiles['selectedPart']);
        $t->same(4, $rootfiles['rootfileCount']);
        $t->same(3, $rootfiles['opfRootfileCount']);
        $t->same(3, $rootfiles['alternateRootfileCount']);
        $t->same(1, $rootfiles['missingRootfileCount']);
        $t->same(1, $rootfiles['nonOpfRootfileCount']);
        $t->same(1, $rootfiles['duplicatePartCount']);
        $t->same(true, $rootfiles['items'][0]['selected']);
        $t->same(false, $rootfiles['items'][3]['selected']);
        $t->same('/EPUB/missing-alternate.opf', $rootfiles['missingRootfiles'][0]['partName']);
        $t->same('/EPUB/preview.xhtml', $rootfiles['nonOpfRootfiles'][0]['partName']);
        $t->same('/EPUB/package.opf', $rootfiles['duplicatePartItems'][0]['partName']);
        $t->same([0, 3], $rootfiles['duplicatePartItems'][0]['indexes']);
        $t->same($rootfiles, $summary['wordpressImport']['packageValidation']['rootfiles']);
        $t->same($validation['diagnostics'], $summary['wordpressImport']['packageValidationDiagnostics']);
    },

    'rejects EPUB OCF packages with invalid mimetype or container rootfile' => static function (TestRunner $t) use ($epubContainerXml, $epub3OpfXml, $epub3NavXml): void {
        $t->throws(\RuntimeException::class, static fn (): EpubPackage => EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/styles/book.css', 'data' => ''],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ])));

        $t->throws(\RuntimeException::class, static fn (): EpubPackage => EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip'],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $epub3OpfXml],
            ['name' => 'EPUB/nav.xhtml', 'data' => $epub3NavXml],
            ['name' => 'EPUB/text/chapter1.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/text/chapter2.xhtml', 'data' => '<html/>'],
            ['name' => 'EPUB/styles/book.css', 'data' => ''],
            ['name' => 'EPUB/images/cover.png', 'data' => 'PNG'],
        ])));

        $t->throws(\InvalidArgumentException::class, static fn (): EpubPackage => EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="../evil.opf" media-type="application/oebps-package+xml"/></rootfiles></container>'],
        ])));
    },

    'rejects OPF packages with missing manifest parts and bad spine references' => static function (TestRunner $t) use ($epubContainerXml): void {
        $badSpineOpf = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Broken</dc:title></metadata>
  <manifest><item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/></manifest>
  <spine><itemref idref="missing"/></spine>
</package>
XML;

        $missingPartOpf = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Broken</dc:title></metadata>
  <manifest><item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/></manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;

        $t->throws(\RuntimeException::class, static fn (): EpubPackage => EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $badSpineOpf],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html/>'],
        ])));

        $t->throws(\RuntimeException::class, static fn (): EpubPackage => EpubPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $epubContainerXml],
            ['name' => 'EPUB/package.opf', 'data' => $missingPartOpf],
        ])));
    },
];
