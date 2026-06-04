<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$containerXml = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="OEBPS/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

$opfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="pub-id">urn:uuid:wp-epub-source-42</dc:identifier>
    <dc:title>WordPress Import EPUB</dc:title>
    <dc:creator id="creator">Migration Desk</dc:creator>
    <dc:language>en</dc:language>
    <dc:subject>Data Liberation</dc:subject>
    <meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>
    <meta name="cover" content="cover-image"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>
    <item id="style" href="styles/book.css" media-type="text/css"/>
    <item id="cover-image" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine toc="toc">
    <itemref idref="chapter-1"/>
    <itemref idref="chapter-2" linear="no"/>
  </spine>
</package>
XML;

$navXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <head><title>Navigation</title></head>
  <body>
    <nav epub:type="toc">
      <h1>Table of contents</h1>
      <ol>
        <li><a href="text/chapter1.xhtml#intro">Imported packet</a></li>
        <li>
          <a href="text/chapter2.xhtml">Review appendix</a>
          <ol>
            <li><a href="text/chapter2.xhtml#media">Media audit</a></li>
          </ol>
        </li>
      </ol>
    </nav>
  </body>
</html>
XML;

$chapter1Xhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head><title>Imported packet</title></head>
  <body><h1 id="intro">Imported packet</h1><p>Chapter XHTML stays available for WordPress review.</p></body>
</html>
XML;

$chapter2Xhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head><title>Review appendix</title></head>
  <body><h1>Review appendix</h1><p id="media">Media audit follows.</p></body>
</html>
XML;

$ncxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="navpoint-1" playOrder="1">
      <navLabel><text>Imported packet</text></navLabel>
      <content src="text/chapter1.xhtml#intro"/>
    </navPoint>
    <navPoint id="navpoint-2" playOrder="2">
      <navLabel><text>Review appendix</text></navLabel>
      <content src="text/chapter2.xhtml"/>
      <navPoint id="navpoint-2-1" playOrder="3">
        <navLabel><text>Media audit</text></navLabel>
        <content src="text/chapter2.xhtml#media"/>
      </navPoint>
    </navPoint>
  </navMap>
</ncx>
XML;

$buildEpubPackage = static function (
    ?string $overrideOpfXml = null,
    ?string $overrideContainerXml = null,
    array $extraParts = []
) use ($containerXml, $opfXml, $navXhtml, $chapter1Xhtml, $chapter2Xhtml, $ncxXml): ZipPackage {
    return ZipPackage::fromParts(array_merge([
        ['name' => 'mimetype', 'data' => EpubReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/container.xml', 'data' => $overrideContainerXml ?? $containerXml],
        ['name' => 'OEBPS/package.opf', 'data' => $overrideOpfXml ?? $opfXml],
        ['name' => 'OEBPS/nav.xhtml', 'data' => $navXhtml],
        ['name' => 'OEBPS/text/chapter1.xhtml', 'data' => $chapter1Xhtml],
        ['name' => 'OEBPS/text/chapter2.xhtml', 'data' => $chapter2Xhtml],
        ['name' => 'OEBPS/toc.ncx', 'data' => $ncxXml],
        ['name' => 'OEBPS/styles/book.css', 'data' => 'body { color: #222; }'],
        ['name' => 'OEBPS/images/cover.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ], $extraParts));
};

return [
    'reads EPUB3 container OPF metadata manifest spine and XHTML assets' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $reader = new EpubReader();
        $result = $reader->readPackage($buildEpubPackage());
        $document = $result['document'];
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('/OEBPS/package.opf', $result['opfPart']);
        $t->same('/OEBPS/package.opf', $result['container']['rootfiles'][0]['path']);
        $t->same('application/oebps-package+xml', $result['container']['rootfiles'][0]['mediaType']);
        $t->same(true, $result['container']['rootfiles'][0]['exists']);
        $t->same('3.0', $result['package']['version']);
        $t->same('pub-id', $result['package']['uniqueIdentifierId']);
        $t->same('WordPress Import EPUB', $result['metadata']['title']);
        $t->same('urn:uuid:wp-epub-source-42', $result['metadata']['identifier']);
        $t->same(['Migration Desk'], $result['metadata']['creators']);
        $t->same('en', $result['metadata']['language']);
        $t->same(['Data Liberation'], $result['metadata']['subjects']);
        $t->same('2026-06-04T21:00:00Z', $result['metadata']['modified']);
        $t->same('cover-image', $result['metadata']['coverItemId']);

        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }
        $t->same('/OEBPS/text/chapter1.xhtml', $manifestById['chapter-1']['part']);
        $t->same('application/xhtml+xml', $manifestById['chapter-1']['mediaType']);
        $t->same(true, $manifestById['cover-image']['exists']);
        $t->same('image/png', $manifestById['cover-image']['mediaType']);
        $t->same('text/css', $manifestById['style']['mediaType']);
        $t->same(['nav'], $manifestById['nav']['properties']);

        $t->same(2, count($result['spine']));
        $t->same('/OEBPS/text/chapter1.xhtml', $result['spine'][0]['part']);
        $t->same(true, $result['spine'][0]['linear']);
        $t->same(false, $result['spine'][1]['linear']);
        $t->same(3, count($result['xhtmlAssets']));
        $t->contains('<h1 id="intro">Imported packet</h1>', $result['xhtmlAssets'][1]['html']);
        $t->same(2, count($document->children));
        $t->same('epub3', $document->attr('source'));
        $t->same('raw_html', $document->children[0]->type);
        $t->same('/OEBPS/text/chapter1.xhtml', $document->children[0]->attr('part'));
        $t->contains('Chapter XHTML stays available', $markdown);
        $t->contains('<!-- wp:html -->', $blocks);
    },
    'parses EPUB3 nav and legacy NCX table of contents targets' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $result = (new EpubReader())->readPackage($buildEpubPackage());

        $nav = $result['nav'];
        $ncx = $result['ncx'];
        $t->same('/OEBPS/nav.xhtml', $nav['part']);
        $t->same(2, count($nav['items']));
        $t->same('Imported packet', $nav['items'][0]['title']);
        $t->same('text/chapter1.xhtml#intro', $nav['items'][0]['href']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $nav['items'][0]['target']);
        $t->same('Review appendix', $nav['items'][1]['title']);
        $t->same('/OEBPS/text/chapter2.xhtml', $nav['items'][1]['target']);
        $t->same('Media audit', $nav['items'][1]['children'][0]['title']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $nav['items'][1]['children'][0]['target']);

        $t->same('/OEBPS/toc.ncx', $ncx['part']);
        $t->same(2, count($ncx['items']));
        $t->same('navpoint-1', $ncx['items'][0]['id']);
        $t->same('1', $ncx['items'][0]['playOrder']);
        $t->same('Imported packet', $ncx['items'][0]['title']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $ncx['items'][0]['target']);
        $t->same('Media audit', $ncx['items'][1]['children'][0]['title']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $ncx['items'][1]['children'][0]['target']);
    },
    'reports missing non-spine package assets without dropping XHTML handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithMissingAudio = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="missing-audio" href="audio/missing.mp3" media-type="audio/mpeg"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithMissingAudio));
        $missing = $result['importReport']['manifest']['missingItems'];
        $assetById = [];
        foreach ($result['assets'] as $asset) {
            $assetById[$asset['id']] = $asset;
        }

        $t->same(1, count($missing));
        $t->same('missing-audio', $missing[0]['id']);
        $t->same('/OEBPS/audio/missing.mp3', $missing[0]['part']);
        $t->same(false, $assetById['missing-audio']['exists']);
        $t->same(null, $assetById['missing-audio']['byteLength']);
        $t->same(null, $assetById['missing-audio']['crc32']);
        $t->same(2, count($result['document']->children));
        $t->contains('Review appendix', $result['document']->children[1]->attr('html'));
    },
    'rejects malformed EPUB packages before conversion handoff' => static function (TestRunner $t) use ($buildEpubPackage, $containerXml, $opfXml): void {
        $reader = new EpubReader();

        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'META-INF/container.xml', 'data' => $containerXml],
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'META-INF/container.xml', 'data' => $containerXml],
            ['name' => 'mimetype', 'data' => EpubReader::MIMETYPE, 'compressionMethod' => 0],
        ])));
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => EpubReader::MIMETYPE],
            ['name' => 'META-INF/container.xml', 'data' => $containerXml],
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => EpubReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => '<container/>'],
        ])));

        $missingSpineOpf = str_replace('<itemref idref="chapter-2" linear="no"/>', '<itemref idref="missing"/>', $opfXml);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildEpubPackage($missingSpineOpf)));

        $absoluteHrefOpf = str_replace('href="styles/book.css"', 'href="https://example.test/book.css"', $opfXml);
        $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage($buildEpubPackage($absoluteHrefOpf)));
    },
];
