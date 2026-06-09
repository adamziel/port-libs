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
