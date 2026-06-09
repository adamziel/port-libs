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
