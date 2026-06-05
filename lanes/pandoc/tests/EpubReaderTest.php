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
  <guide>
    <reference type="cover" title="Cover image" href="images/cover.png"/>
    <reference type="text" title="Start reading" href="text/chapter1.xhtml#intro"/>
    <reference type="glossary" title="Missing glossary" href="text/missing.xhtml"/>
  </guide>
  <collection id="series" role="series" xml:lang="en">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>Migration packets</dc:title>
      <meta property="group-position">2</meta>
    </metadata>
    <link rel="first" href="text/chapter1.xhtml#intro" media-type="application/xhtml+xml" properties="preview"/>
    <link rel="record" href="https://example.invalid/source-record" media-type="text/html"/>
    <collection id="review" role="preview">
      <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
        <dc:title>Reviewer extracts</dc:title>
      </metadata>
      <link rel="sample" href="text/chapter2.xhtml#media" media-type="application/xhtml+xml"/>
    </collection>
  </collection>
</package>
XML;

$alternateOpfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="fixed-id" xml:lang="en">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="fixed-id">urn:uuid:wp-epub-fixed-layout-42</dc:identifier>
    <dc:title>Fixed layout reviewer edition</dc:title>
    <dc:creator>Migration Layout Desk</dc:creator>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-04T22:10:00Z</meta>
    <meta property="rendition:layout">pre-paginated</meta>
    <meta property="rendition:orientation">landscape</meta>
    <meta property="rendition:spread">none</meta>
    <meta property="rendition:viewport">width=1024, height=768</meta>
  </metadata>
  <manifest>
    <item id="fixed-nav" href="fixed-nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="fixed-page" href="fixed-page.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="fixed-page"/>
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
    <nav epub:type="landmarks">
      <h2>Book landmarks</h2>
      <ol>
        <li><a epub:type="bodymatter" href="text/chapter1.xhtml#intro">Start reading</a></li>
        <li><a epub:type="backmatter bibliography" href="text/chapter2.xhtml#media">Reviewer appendix</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <h2>Print page list</h2>
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter1.xhtml#page-1">1</a></li>
        <li><a epub:type="pagebreak" href="text/chapter2.xhtml#page-2">2</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

$chapter1Xhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head><title>Imported packet</title></head>
  <body><h1 id="intro">Imported packet</h1><span id="page-1"></span><p>Chapter XHTML stays available for WordPress review.</p></body>
</html>
XML;

$chapter2Xhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head><title>Review appendix</title></head>
  <body><h1>Review appendix</h1><span id="page-2"></span><p id="media">Media audit follows.</p></body>
</html>
XML;

$slideshowFallbackXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head><title>Slideshow fallback</title></head>
  <body><h1>Slideshow fallback</h1><p>Scripted slideshow fallback remains reviewable.</p></body>
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

$encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
    <CipherData>
      <CipherReference URI="OEBPS/fonts/source.otf"/>
    </CipherData>
  </EncryptedData>
</encryption>
XML;

$smilXml = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <seq id="chapter-1-overlay" epub:textref="../text/chapter1.xhtml">
      <par id="intro-audio" epub:type="bodymatter">
        <text src="../text/chapter1.xhtml#intro"/>
        <audio src="../audio/chapter1.mp3" clipBegin="0:00:01.000" clipEnd="0:00:05.500"/>
      </par>
      <seq id="nested-review">
        <par id="page-audio">
          <text src="../text/chapter1.xhtml#page-1"/>
          <audio src="../audio/chapter1.mp3" clipBegin="00:00:05.500" clipEnd="00:00:07.000"/>
        </par>
      </seq>
    </seq>
  </body>
</smil>
XML;

$remoteNavXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter1.xhtml#intro">Imported packet</a></li>
        <li><a href="https://cdn.example.test/epub/source-note.html">Remote audit record</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

$remoteNcxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="navpoint-remote" playOrder="1">
      <navLabel><text>Remote appendix</text></navLabel>
      <content src="https://cdn.example.test/epub/appendix.xhtml"/>
    </navPoint>
  </navMap>
</ncx>
XML;

$remoteSmilXml = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <seq id="remote-overlay" epub:textref="https://cdn.example.test/remote/chapter.xhtml">
      <par id="remote-audio" epub:type="bodymatter">
        <text src="https://cdn.example.test/remote/chapter.xhtml#intro"/>
        <audio src="https://cdn.example.test/audio/chapter.mp3" clipBegin="0:00:01.000" clipEnd="0:00:04.000"/>
      </par>
    </seq>
  </body>
</smil>
XML;

$buildEpubPackage = static function (
    ?string $overrideOpfXml = null,
    ?string $overrideContainerXml = null,
    array $extraParts = [],
    ?string $overrideNavXhtml = null,
    ?string $overrideNcxXml = null
) use ($containerXml, $opfXml, $navXhtml, $chapter1Xhtml, $chapter2Xhtml, $ncxXml): ZipPackage {
    return ZipPackage::fromParts(array_merge([
        ['name' => 'mimetype', 'data' => EpubReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/container.xml', 'data' => $overrideContainerXml ?? $containerXml],
        ['name' => 'OEBPS/package.opf', 'data' => $overrideOpfXml ?? $opfXml],
        ['name' => 'OEBPS/nav.xhtml', 'data' => $overrideNavXhtml ?? $navXhtml],
        ['name' => 'OEBPS/text/chapter1.xhtml', 'data' => $chapter1Xhtml],
        ['name' => 'OEBPS/text/chapter2.xhtml', 'data' => $chapter2Xhtml],
        ['name' => 'OEBPS/toc.ncx', 'data' => $overrideNcxXml ?? $ncxXml],
        ['name' => 'OEBPS/styles/book.css', 'data' => 'body { color: #222; }'],
        ['name' => 'OEBPS/images/cover.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
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
    'parses OPF package prefix declarations for metadata vocabulary review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $prefix = 'schema: https://schema.org/ marc: http://id.loc.gov/vocabulary/relators/ bad-prefix';
        $opfWithPrefixes = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en" prefix="' . $prefix . '">',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithPrefixes));
        $package = $result['package'];

        $t->same($prefix, $package['prefix']);
        $t->same([
            'schema' => 'https://schema.org/',
            'marc' => 'http://id.loc.gov/vocabulary/relators/',
        ], $package['prefixes']);
        $t->same(2, count($package['prefixBindings']));
        $t->same(0, $package['prefixBindings'][0]['index']);
        $t->same('schema', $package['prefixBindings'][0]['prefix']);
        $t->same('https://schema.org/', $package['prefixBindings'][0]['iri']);
        $t->same(1, $package['prefixBindings'][1]['index']);
        $t->same('marc', $package['prefixBindings'][1]['prefix']);
        $t->same('http://id.loc.gov/vocabulary/relators/', $package['prefixBindings'][1]['iri']);
        $t->same(1, count($package['prefixDiagnostics']));
        $t->same('invalid-package-prefix-declaration', $package['prefixDiagnostics'][0]['type']);
        $t->contains('bad-prefix', $package['prefixDiagnostics'][0]['value']);
        $t->same($package, $result['importReport']['package']);
    },
    'reports OPF spine page progression direction and itemref spread properties' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithReadingOrder = str_replace(
            '<spine toc="toc">',
            '<spine toc="toc" page-progression-direction="rtl">',
            $opfXml
        );
        $opfWithReadingOrder = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref idref="chapter-1" properties="rendition:page-spread-right page-spread-right"/>',
            $opfWithReadingOrder
        );
        $opfWithReadingOrder = str_replace(
            '<itemref idref="chapter-2" linear="no"/>',
            '<itemref idref="chapter-2" linear="no" properties="rendition:page-spread-center spread-none"/>',
            $opfWithReadingOrder
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithReadingOrder));
        $spineProperties = $result['spineProperties'];

        $t->same('toc', $spineProperties['toc']);
        $t->same('rtl', $spineProperties['pageProgressionDirection']);
        $t->same('rtl', $spineProperties['pageProgressionDirectionRaw']);
        $t->same(true, $spineProperties['pageProgressionDirectionSpecified']);
        $t->same(true, $spineProperties['pageProgressionDirectionValid']);
        $t->same(true, $spineProperties['rightToLeft']);
        $t->same([], $spineProperties['itemDiagnostics']);
        $t->same([], $spineProperties['diagnostics']);
        $t->same($spineProperties, $result['importReport']['spine']['properties']);
        $t->same($spineProperties, $result['document']->attr('spineProperties'));

        $t->same('right', $result['spine'][0]['pageSpread']);
        $t->same(['rendition:page-spread-right', 'page-spread-right'], $result['spine'][0]['pageSpreadProperties']);
        $t->same('right', $result['spine'][0]['spineItemProperties']['pageSpread']['placement']);
        $t->same(false, $result['spine'][0]['spineItemProperties']['pageSpread']['conflicting']);
        $t->same([], $result['spine'][0]['spineItemDiagnostics']);
        $t->same('center', $result['spine'][1]['pageSpread']);
        $t->same(['rendition:page-spread-center', 'spread-none'], $result['spine'][1]['pageSpreadProperties']);

        $t->same('rtl', $result['document']->children[0]->attr('pageProgressionDirection'));
        $t->same('right', $result['document']->children[0]->attr('pageSpread'));
        $t->same($result['spine'][0]['spineItemProperties'], $result['document']->children[0]->attr('spineItemProperties'));
        $t->same('center', $result['document']->children[1]->attr('pageSpread'));
    },
    'reports invalid OPF spine progression and conflicting spread diagnostics' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithInvalidSpineProperties = str_replace(
            '<spine toc="toc">',
            '<spine toc="toc" page-progression-direction="sideways">',
            $opfXml
        );
        $opfWithInvalidSpineProperties = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref idref="chapter-1" properties="page-spread-left page-spread-right"/>',
            $opfWithInvalidSpineProperties
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithInvalidSpineProperties));
        $spineProperties = $result['spineProperties'];

        $t->same('default', $spineProperties['pageProgressionDirection']);
        $t->same('sideways', $spineProperties['pageProgressionDirectionRaw']);
        $t->same(true, $spineProperties['pageProgressionDirectionSpecified']);
        $t->same(false, $spineProperties['pageProgressionDirectionValid']);
        $t->same(false, $spineProperties['rightToLeft']);
        $t->same('invalid-spine-page-progression-direction', $spineProperties['diagnostics'][0]['type']);
        $t->same('sideways', $spineProperties['diagnostics'][0]['value']);
        $t->same('conflicting-spine-page-spread-properties', $spineProperties['itemDiagnostics'][0]['type']);
        $t->same(0, $spineProperties['itemDiagnostics'][0]['index']);
        $t->same('chapter-1', $spineProperties['itemDiagnostics'][0]['idref']);
        $t->same(['page-spread-left', 'page-spread-right'], $spineProperties['itemDiagnostics'][0]['properties']);
        $t->same(['left', 'right'], $spineProperties['itemDiagnostics'][0]['placements']);
        $t->same(2, count($spineProperties['diagnostics']));
        $t->same($spineProperties, $result['importReport']['spine']['properties']);
        $t->same(true, $result['spine'][0]['spineItemProperties']['pageSpread']['conflicting']);
        $t->same('left', $result['spine'][0]['pageSpread']);
        $t->same($result['spine'][0]['spineItemDiagnostics'][0], array_slice($spineProperties['itemDiagnostics'][0], 2));
        $t->same('default', $result['document']->children[0]->attr('pageProgressionDirection'));
        $t->same('left', $result['document']->children[0]->attr('pageSpread'));
        $t->same($spineProperties, $result['document']->attr('spineProperties'));
    },
    'summarizes alternate EPUB rootfile renditions without changing selected spine' => static function (TestRunner $t) use ($buildEpubPackage, $containerXml, $alternateOpfXml): void {
        $multiRootContainer = str_replace(
            '</rootfiles>',
            '    <rootfile full-path="OEBPS/fixed/package.opf" media-type="application/oebps-package+xml"/>' . "\n" . '  </rootfiles>',
            $containerXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            null,
            $multiRootContainer,
            [
                ['name' => 'OEBPS/fixed/package.opf', 'data' => $alternateOpfXml],
            ]
        ));

        $t->same('/OEBPS/package.opf', $result['opfPart']);
        $t->same(2, count($result['container']['rootfiles']));
        $t->same(true, $result['container']['rootfiles'][0]['selected']);
        $t->same(false, $result['container']['rootfiles'][1]['selected']);
        $t->same(2, $result['renditions']['count']);
        $t->same(1, $result['renditions']['alternateCount']);
        $t->same('/OEBPS/package.opf', $result['renditions']['selectedPath']);
        $t->same(0, $result['renditions']['selectedIndex']);
        $t->same([], $result['renditions']['diagnostics']);

        $selected = $result['renditions']['items'][0];
        $alternate = $result['renditions']['items'][1];
        $t->same(true, $selected['selected']);
        $t->same('/OEBPS/package.opf', $selected['path']);
        $t->same('WordPress Import EPUB', $selected['metadata']['title']);
        $t->same(6, $selected['manifestCount']);
        $t->same(2, $selected['spineCount']);
        $t->same([], $selected['renditionProperties']);

        $t->same(false, $alternate['selected']);
        $t->same('/OEBPS/fixed/package.opf', $alternate['path']);
        $t->same(true, $alternate['exists']);
        $t->same('Fixed layout reviewer edition', $alternate['metadata']['title']);
        $t->same('urn:uuid:wp-epub-fixed-layout-42', $alternate['metadata']['identifier']);
        $t->same(['Migration Layout Desk'], $alternate['metadata']['creators']);
        $t->same('2026-06-04T22:10:00Z', $alternate['metadata']['modified']);
        $t->same('pre-paginated', $alternate['renditionProperties']['layout']);
        $t->same('landscape', $alternate['renditionProperties']['orientation']);
        $t->same('none', $alternate['renditionProperties']['spread']);
        $t->same('width=1024, height=768', $alternate['renditionProperties']['viewport']);
        $t->same(2, $alternate['manifestCount']);
        $t->same(1, $alternate['spineCount']);
        $t->same([], $alternate['diagnostics']);
        $t->same($result['renditions'], $result['importReport']['renditions']);
        $t->same($result['renditions'], $result['document']->attr('renditions'));
        $t->same('/OEBPS/text/chapter1.xhtml', $result['spine'][0]['part']);
        $t->same(2, count($result['document']->children));
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
    'parses typed EPUB3 landmarks and page-list navigation sections' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $result = (new EpubReader())->readPackage($buildEpubPackage());
        $nav = $result['nav'];

        $t->same(3, count($nav['sections']));
        $t->same('toc', $nav['sections'][0]['type']);
        $t->same(['toc'], $nav['sections'][0]['types']);
        $t->same('Table of contents', $nav['sections'][0]['title']);
        $t->same('landmarks', $nav['sections'][1]['type']);
        $t->same(['landmarks'], $nav['sections'][1]['types']);
        $t->same('Book landmarks', $nav['sections'][1]['title']);
        $t->same('page-list', $nav['sections'][2]['type']);
        $t->same('Print page list', $nav['sections'][2]['title']);

        $t->same(2, count($nav['landmarks']));
        $t->same('Start reading', $nav['landmarks'][0]['title']);
        $t->same('bodymatter', $nav['landmarks'][0]['type']);
        $t->same(['bodymatter'], $nav['landmarks'][0]['types']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $nav['landmarks'][0]['target']);
        $t->same('Reviewer appendix', $nav['landmarks'][1]['title']);
        $t->same('backmatter', $nav['landmarks'][1]['type']);
        $t->same(['backmatter', 'bibliography'], $nav['landmarks'][1]['types']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $nav['landmarks'][1]['target']);

        $t->same(2, count($nav['pageList']));
        $t->same('1', $nav['pageList'][0]['title']);
        $t->same('pagebreak', $nav['pageList'][0]['type']);
        $t->same('/OEBPS/text/chapter1.xhtml#page-1', $nav['pageList'][0]['target']);
        $t->same('2', $nav['pageList'][1]['title']);
        $t->same('/OEBPS/text/chapter2.xhtml#page-2', $nav['pageList'][1]['target']);
        $t->same($nav['items'], $nav['sections'][0]['items']);
    },
    'builds EPUB page-break report from page-list navigation for WordPress handoff' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $pageListNavXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="page-list">
      <h1>Print pages</h1>
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter1.xhtml#page-1">1</a></li>
        <li>
          <a epub:type="pagebreak" href="text/chapter2.xhtml#page-2">2</a>
          <ol>
            <li><a epub:type="pagebreak" href="text/chapter2.xhtml#page-2-note">2 note</a></li>
          </ol>
        </li>
        <li><a epub:type="pagebreak" href="appendix/print-page.xhtml#page-3">iii</a></li>
        <li><a epub:type="pagebreak" href="https://cdn.example.test/epub/page-4.xhtml#page-4">iv</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            null,
            null,
            [
                ['name' => 'OEBPS/appendix/print-page.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><span id="page-3">iii</span></body></html>'],
            ],
            $pageListNavXhtml
        ));

        $pageBreaks = $result['pageBreaks'];
        $t->same(true, $pageBreaks['present']);
        $t->same('nav-page-list', $pageBreaks['source']);
        $t->same(5, $pageBreaks['count']);
        $t->same($pageBreaks, $result['importReport']['pageBreaks']);
        $t->same($pageBreaks, $result['document']->attr('pageBreaks'));

        $first = $pageBreaks['items'][0];
        $t->same(0, $first['index']);
        $t->same(0, $first['depth']);
        $t->same('1', $first['label']);
        $t->same('text/chapter1.xhtml#page-1', $first['href']);
        $t->same('/OEBPS/text/chapter1.xhtml#page-1', $first['target']);
        $t->same('/OEBPS/text/chapter1.xhtml', $first['part']);
        $t->same('page-1', $first['fragment']);
        $t->same(false, $first['external']);
        $t->same(true, $first['exists']);
        $t->same('pagebreak', $first['type']);
        $t->same(['pagebreak'], $first['types']);
        $t->same(0, $first['spineIndex']);
        $t->same('chapter-1', $first['spineIdref']);
        $t->same('/OEBPS/text/chapter1.xhtml', $first['contentPart']);
        $t->same(true, $first['linear']);
        $t->same([], $first['navDiagnostics']);
        $t->same([], $first['diagnostics']);

        $t->same('2', $pageBreaks['items'][1]['label']);
        $t->same(1, $pageBreaks['items'][1]['spineIndex']);
        $t->same(false, $pageBreaks['items'][1]['linear']);
        $t->same('2 note', $pageBreaks['items'][2]['label']);
        $t->same(1, $pageBreaks['items'][2]['depth']);
        $t->same('page-2-note', $pageBreaks['items'][2]['fragment']);
        $t->same(1, $pageBreaks['items'][2]['spineIndex']);

        $outsideSpine = $pageBreaks['items'][3];
        $t->same('iii', $outsideSpine['label']);
        $t->same('/OEBPS/appendix/print-page.xhtml', $outsideSpine['part']);
        $t->same(true, $outsideSpine['exists']);
        $t->same(null, $outsideSpine['spineIndex']);
        $t->same('page-list-target-outside-spine', $outsideSpine['diagnostics'][0]['type']);

        $remote = $pageBreaks['items'][4];
        $t->same('iv', $remote['label']);
        $t->same('https://cdn.example.test/epub/page-4.xhtml#page-4', $remote['target']);
        $t->same('page-4', $remote['fragment']);
        $t->same(true, $remote['external']);
        $t->same(null, $remote['part']);
        $t->same('external-nav-reference', $remote['navDiagnostics'][0]['type']);
        $t->same('external-page-list-reference', $remote['diagnostics'][0]['type']);

        $t->same(1, count($pageBreaks['itemsByPart']['/OEBPS/text/chapter1.xhtml']));
        $t->same(2, count($pageBreaks['itemsByPart']['/OEBPS/text/chapter2.xhtml']));
        $t->same(1, count($pageBreaks['itemsByPart']['/OEBPS/appendix/print-page.xhtml']));
        $t->same(2, count($pageBreaks['diagnostics']));
        $t->same('page-list-target-outside-spine', $pageBreaks['diagnostics'][0]['type']);
        $t->same(3, $pageBreaks['diagnostics'][0]['index']);
        $t->same('external-page-list-reference', $pageBreaks['diagnostics'][1]['type']);
        $t->same(4, $pageBreaks['diagnostics'][1]['index']);

        $t->same(1, $result['document']->children[0]->attr('pageBreakCount'));
        $t->same('1', $result['document']->children[0]->attr('pageBreaks')[0]['label']);
        $t->same(2, $result['document']->children[1]->attr('pageBreakCount'));
        $t->same('2 note', $result['document']->children[1]->attr('pageBreaks')[1]['label']);
    },
    'parses OPF guide references and collection review metadata' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $result = (new EpubReader())->readPackage($buildEpubPackage());

        $guide = $result['guide'];
        $t->same(true, $guide['present']);
        $t->same(3, count($guide['items']));
        $t->same('cover', $guide['items'][0]['type']);
        $t->same('Cover image', $guide['items'][0]['title']);
        $t->same('images/cover.png', $guide['items'][0]['href']);
        $t->same('/OEBPS/images/cover.png', $guide['items'][0]['target']);
        $t->same('/OEBPS/images/cover.png', $guide['items'][0]['part']);
        $t->same(true, $guide['items'][0]['exists']);
        $t->same('cover-image', $guide['items'][0]['manifestId']);
        $t->same('image/png', $guide['items'][0]['mediaType']);
        $t->same([], $guide['items'][0]['diagnostics']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $guide['items'][1]['target']);
        $t->same('chapter-1', $guide['items'][1]['manifestId']);
        $t->same(false, $guide['items'][2]['exists']);
        $t->same('/OEBPS/text/missing.xhtml', $guide['items'][2]['part']);
        $t->same('missing-guide-reference', $guide['items'][2]['diagnostics'][0]['type']);
        $t->same($guide, $result['importReport']['guide']);

        $collections = $result['collections'];
        $t->same(1, count($collections));
        $series = $collections[0];
        $t->same('series', $series['id']);
        $t->same('series', $series['role']);
        $t->same('en', $series['language']);
        $t->same('Migration packets', $series['metadata']['title']);
        $t->same('2', $series['metadata']['metaProperties']['group-position'][0]['text']);
        $t->same(2, count($series['links']));
        $t->same(['first'], $series['links'][0]['rel']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $series['links'][0]['target']);
        $t->same('chapter-1', $series['links'][0]['manifestId']);
        $t->same(['preview'], $series['links'][0]['properties']);
        $t->same(['record'], $series['links'][1]['rel']);
        $t->same('https://example.invalid/source-record', $series['links'][1]['target']);
        $t->same(true, $series['links'][1]['external']);
        $t->same(null, $series['links'][1]['part']);
        $t->same(false, $series['links'][1]['exists']);
        $t->same('external-collection-link', $series['links'][1]['diagnostics'][0]['type']);
        $t->same(1, count($series['children']));
        $t->same('preview', $series['children'][0]['role']);
        $t->same('Reviewer extracts', $series['children'][0]['metadata']['title']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $series['children'][0]['links'][0]['target']);
        $t->same($collections, $result['importReport']['collections']);
        $t->same($guide, $result['document']->attr('guide'));
        $t->same($collections, $result['document']->attr('collections'));
    },
    'resolves OPF manifest fallback chains for foreign spine XHTML handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $slideshowFallbackXhtml): void {
        $opfWithFallbackSpine = str_replace(
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/><item id="slideshow" href="slides/slideshow.xml" media-type="application/x-demo-slideshow" fallback="slideshow-handler"/><item id="slideshow-handler" href="text/slideshow-fallback.xhtml" media-type="application/xhtml+xml" properties="scripted"/>',
            $opfXml
        );
        $opfWithFallbackSpine = str_replace(
            '<itemref idref="chapter-2" linear="no"/>',
            '<itemref idref="slideshow" linear="no"/><itemref idref="chapter-2" linear="no"/>',
            $opfWithFallbackSpine
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithFallbackSpine,
            null,
            [
                ['name' => 'OEBPS/slides/slideshow.xml', 'data' => '<slides><slide src="../images/cover.png"/></slides>'],
                ['name' => 'OEBPS/text/slideshow-fallback.xhtml', 'data' => $slideshowFallbackXhtml],
            ]
        ));

        $t->same(3, count($result['spine']));
        $t->same('slideshow', $result['spine'][1]['idref']);
        $t->same('application/x-demo-slideshow', $result['spine'][1]['mediaType']);
        $t->same('/OEBPS/slides/slideshow.xml', $result['spine'][1]['part']);
        $t->same('slideshow-handler', $result['spine'][1]['contentId']);
        $t->same('/OEBPS/text/slideshow-fallback.xhtml', $result['spine'][1]['contentPart']);
        $t->same('application/xhtml+xml', $result['spine'][1]['contentMediaType']);
        $t->same(true, $result['spine'][1]['contentIsFallback']);
        $t->same([], $result['spine'][1]['fallbackDiagnostics']);
        $t->same(1, count($result['spine'][1]['fallbackChain']));
        $t->same('slideshow-handler', $result['spine'][1]['fallbackChain'][0]['id']);
        $t->same('application/xhtml+xml', $result['spine'][1]['fallbackChain'][0]['mediaType']);
        $t->same('/OEBPS/text/slideshow-fallback.xhtml', $result['spine'][1]['fallbackChain'][0]['part']);

        $t->same(4, count($result['xhtmlAssets']));
        $t->same(3, count($result['document']->children));
        $fallbackBlock = $result['document']->children[1];
        $t->same('raw_html', $fallbackBlock->type);
        $t->same('epub3-spine-fallback', $fallbackBlock->attr('source'));
        $t->same('slideshow', $fallbackBlock->attr('id'));
        $t->same('/OEBPS/slides/slideshow.xml', $fallbackBlock->attr('spinePart'));
        $t->same('application/x-demo-slideshow', $fallbackBlock->attr('spineMediaType'));
        $t->same('slideshow', $fallbackBlock->attr('fallbackOf'));
        $t->same('/OEBPS/text/slideshow-fallback.xhtml', $fallbackBlock->attr('part'));
        $t->same('slideshow-handler', $fallbackBlock->attr('contentId'));
        $t->same($result['spine'][1]['fallbackChain'], $fallbackBlock->attr('fallbackChain'));
        $t->contains('Scripted slideshow fallback remains reviewable.', $fallbackBlock->attr('html'));

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Scripted slideshow fallback remains reviewable.', $blocks);
    },
    'reports OPF bindings for scripted media type handlers' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $slideshowFallbackXhtml): void {
        $opfWithBindings = str_replace(
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/><item id="slideshow" href="slides/slideshow.xml" media-type="application/x-demo-slideshow" fallback="slideshow-handler"/><item id="slideshow-handler" href="text/slideshow-fallback.xhtml" media-type="application/xhtml+xml" properties="scripted"/>',
            $opfXml
        );
        $opfWithBindings = str_replace(
            '<itemref idref="chapter-2" linear="no"/>',
            '<itemref idref="slideshow" linear="no"/><itemref idref="chapter-2" linear="no"/>',
            $opfWithBindings
        );
        $opfWithBindings = str_replace(
            '</package>',
            '<bindings><mediaType media-type="application/x-demo-slideshow" handler="slideshow-handler"/><mediaType media-type="application/x-review-widget" handler="missing-handler"/></bindings></package>',
            $opfWithBindings
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithBindings,
            null,
            [
                ['name' => 'OEBPS/slides/slideshow.xml', 'data' => '<slides><slide src="../images/cover.png"/></slides>'],
                ['name' => 'OEBPS/text/slideshow-fallback.xhtml', 'data' => $slideshowFallbackXhtml],
            ]
        ));

        $bindings = $result['bindings'];
        $t->same(true, $bindings['present']);
        $t->same(2, count($bindings['items']));
        $t->same('application/x-demo-slideshow', $bindings['items'][0]['mediaType']);
        $t->same('slideshow-handler', $bindings['items'][0]['handlerId']);
        $t->same('/OEBPS/text/slideshow-fallback.xhtml', $bindings['items'][0]['handlerPart']);
        $t->same('application/xhtml+xml', $bindings['items'][0]['handlerMediaType']);
        $t->same(['scripted'], $bindings['items'][0]['handlerProperties']);
        $t->same(true, $bindings['items'][0]['handlerExists']);
        $t->same(true, $bindings['items'][0]['handlerCanExposeBytes']);
        $t->same(strlen($slideshowFallbackXhtml), $bindings['items'][0]['handlerByteLength']);
        $t->same([], $bindings['items'][0]['diagnostics']);
        $t->same('application/x-review-widget', $bindings['items'][1]['mediaType']);
        $t->same('missing-handler', $bindings['items'][1]['handlerId']);
        $t->same(false, $bindings['items'][1]['handlerExists']);
        $t->same(null, $bindings['items'][1]['handlerPart']);
        $t->same('missing-binding-handler-manifest-item', $bindings['items'][1]['diagnostics'][0]['type']);
        $t->same('missing-binding-handler-manifest-item', $bindings['diagnostics'][0]['type']);
        $t->same(1, $bindings['diagnostics'][0]['index']);
        $t->same($bindings, $result['importReport']['bindings']);
        $t->same($bindings, $result['document']->attr('bindings'));

        $t->same($bindings['items'][0], $result['spine'][1]['binding']);
        $t->same($bindings['items'][0], $result['document']->children[1]->attr('binding'));
        $t->same('epub3-spine-fallback', $result['document']->children[1]->attr('source'));
        $t->contains('Scripted slideshow fallback remains reviewable.', $result['document']->children[1]->attr('html'));
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
    'reports remote OPF manifest resources without fetching or marking them missing' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithRemoteAudio = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="remote-audio" href="https://cdn.example.test/audio/source-note.mp3" media-type="audio/mpeg"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithRemoteAudio));
        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }
        $assetById = [];
        foreach ($result['assets'] as $asset) {
            $assetById[$asset['id']] = $asset;
        }

        $remoteManifest = $manifestById['remote-audio'];
        $t->same('https://cdn.example.test/audio/source-note.mp3', $remoteManifest['target']);
        $t->same(null, $remoteManifest['part']);
        $t->same(true, $remoteManifest['external']);
        $t->same(false, $remoteManifest['exists']);
        $t->same(false, $remoteManifest['canExposeBytes']);
        $t->same(null, $remoteManifest['byteLength']);
        $t->same(null, $remoteManifest['crc32']);
        $t->same('external-manifest-resource', $remoteManifest['diagnostics'][0]['type']);

        $t->same([], $result['importReport']['manifest']['missingItems']);
        $t->same(1, count($result['importReport']['manifest']['externalItems']));
        $t->same('remote-audio', $result['importReport']['manifest']['externalItems'][0]['id']);
        $t->same('https://cdn.example.test/audio/source-note.mp3', $result['importReport']['manifest']['externalItems'][0]['href']);

        $remoteAsset = $assetById['remote-audio'];
        $t->same(true, $remoteAsset['external']);
        $t->same(null, $remoteAsset['part']);
        $t->same('audio/mpeg', $remoteAsset['mediaType']);
        $t->same('audio', $remoteAsset['role']);
        $t->same(false, $remoteAsset['exists']);
        $t->same(false, $remoteAsset['exportCandidate']);
        $t->same(false, $remoteAsset['attachmentCandidate']);
        $t->same(null, $remoteAsset['byteSha256']);
        $t->same(false, $remoteAsset['canExposeBytes']);
        $t->same('external-manifest-resource', $remoteAsset['diagnostics'][0]['type']);
        $t->same(2, count($result['document']->children));
        $t->contains('Chapter XHTML stays available', $result['document']->children[0]->attr('html'));
    },
    'parses OPF metadata link records without treating linked records as undeclared assets' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $reviewRecordBytes = '{"@context":"https://schema.org","name":"WordPress EPUB review record"}';
        $opfWithMetadataLinks = str_replace(
            '</metadata>',
            '<link id="review-record" rel="record alternate" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org reviewer" hreflang="en"/>'
            . '<link id="remote-onix" rel="record" href="https://metadata.example.test/onix/source.xml" media-type="application/xml" properties="onix"/>'
            . '<link id="creator-voicing" rel="voicing" refines="#creator" href="audio/creator-name.mp3" media-type="audio/mpeg"/>'
            . '</metadata>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithMetadataLinks,
            null,
            [
                ['name' => 'OEBPS/meta/review-record.json', 'data' => $reviewRecordBytes],
            ]
        ));

        $links = $result['metadata']['links'];
        $t->same(3, count($links));
        $t->same('review-record', $links[0]['id']);
        $t->same(['record', 'alternate'], $links[0]['rel']);
        $t->same('meta/review-record.json', $links[0]['href']);
        $t->same('/OEBPS/meta/review-record.json', $links[0]['target']);
        $t->same('/OEBPS/meta/review-record.json', $links[0]['part']);
        $t->same(false, $links[0]['external']);
        $t->same(true, $links[0]['exists']);
        $t->same(strlen($reviewRecordBytes), $links[0]['byteLength']);
        $t->same(hash('sha256', $reviewRecordBytes), $links[0]['byteSha256']);
        $t->same('application/ld+json', $links[0]['mediaType']);
        $t->same(null, $links[0]['manifestId']);
        $t->same(['schema-org', 'reviewer'], $links[0]['properties']);
        $t->same('en', $links[0]['hreflang']);
        $t->same([], $links[0]['diagnostics']);

        $t->same('remote-onix', $links[1]['id']);
        $t->same(['record'], $links[1]['rel']);
        $t->same('https://metadata.example.test/onix/source.xml', $links[1]['target']);
        $t->same(null, $links[1]['part']);
        $t->same(true, $links[1]['external']);
        $t->same(false, $links[1]['exists']);
        $t->same(null, $links[1]['byteSha256']);
        $t->same('external-metadata-reference', $links[1]['diagnostics'][0]['type']);

        $t->same('creator-voicing', $links[2]['id']);
        $t->same(['voicing'], $links[2]['rel']);
        $t->same('#creator', $links[2]['refines']);
        $t->same('/OEBPS/audio/creator-name.mp3', $links[2]['target']);
        $t->same(false, $links[2]['exists']);
        $t->same('missing-metadata-reference', $links[2]['diagnostics'][0]['type']);

        $t->same(2, count($result['metadata']['linksByRel']['record']));
        $t->same($links[0], $result['metadata']['linksByRel']['record'][0]);
        $t->same($links[2], $result['metadata']['linksByRel']['voicing'][0]);
        $t->same($links, $result['importReport']['metadata']['links']);
        $t->same($links, $result['document']->attr('metadata')['links']);

        $unmanifestedParts = array_map(
            static fn (array $item): ?string => $item['part'] ?? null,
            $result['importReport']['assets']['unmanifestedItems']
        );
        $t->same(false, in_array('/OEBPS/meta/review-record.json', $unmanifestedParts, true));
    },
    'groups OPF metadata refinements by referenced metadata id for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithRefinedMetadata = str_replace(
            '<dc:title>WordPress Import EPUB</dc:title>',
            '<dc:title id="main-title">WordPress Import EPUB</dc:title>',
            $opfXml
        );
        $opfWithRefinedMetadata = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta refines="#pub-id" property="identifier-type" scheme="onix:codelist5">15</meta>'
            . '<meta refines="#main-title" property="title-type">main</meta>'
            . '<meta refines="#creator" property="file-as">Desk, Migration</meta>'
            . '<meta refines="#creator" property="role" scheme="marc:relators">aut</meta>'
            . '<meta refines="#creator" property="display-seq">1</meta>'
            . '<meta refines="#creator" property="alternate-script" xml:lang="ja-Latn">Iko desuku</meta>',
            $opfWithRefinedMetadata
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithRefinedMetadata));
        $metadata = $result['metadata'];
        $refinements = $metadata['refinementsById'];

        $t->same('15', $refinements['pub-id']['identifier-type'][0]['text']);
        $t->same('onix:codelist5', $refinements['pub-id']['identifier-type'][0]['scheme']);
        $t->same('#pub-id', $refinements['pub-id']['identifier-type'][0]['refines']);
        $t->same('main', $refinements['main-title']['title-type'][0]['text']);
        $t->same('Desk, Migration', $refinements['creator']['file-as'][0]['text']);
        $t->same('aut', $refinements['creator']['role'][0]['text']);
        $t->same('marc:relators', $refinements['creator']['role'][0]['scheme']);
        $t->same('1', $refinements['creator']['display-seq'][0]['text']);
        $t->same('Iko desuku', $refinements['creator']['alternate-script'][0]['text']);
        $t->same('ja-Latn', $refinements['creator']['alternate-script'][0]['language']);

        $t->same($refinements['pub-id'], $metadata['dc']['identifier'][0]['refinements']);
        $t->same($refinements['main-title'], $metadata['dc']['title'][0]['refinements']);
        $t->same($refinements['creator'], $metadata['dc']['creator'][0]['refinements']);
        $t->same([], $metadata['dc']['language'][0]['refinements']);
        $t->same($refinements, $result['importReport']['metadata']['refinementsById']);
        $t->same($refinements, $result['document']->attr('metadata')['refinementsById']);
    },
    'reports EPUB accessibility metadata and linked records for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $a11yRecordBytes = '{"@context":"https://schema.org","accessibilitySummary":"Reviewer accessibility record"}';
        $opfWithAccessibility = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta property="schema:accessMode">textual</meta>'
            . '<meta property="schema:accessMode">visual</meta>'
            . '<meta property="schema:accessModeSufficient">textual</meta>'
            . '<meta property="schema:accessModeSufficient">textual visual</meta>'
            . '<meta property="schema:accessibilityFeature">alternativeText</meta>'
            . '<meta property="schema:accessibilityFeature">MathML</meta>'
            . '<meta property="schema:accessibilityFeature">pageNavigation</meta>'
            . '<meta name="schema:accessibilityHazard" content="noFlashingHazard"/>'
            . '<meta property="schema:accessibilityHazard" content="noSoundHazard"/>'
            . '<meta property="schema:accessibilitySummary">Images have alternative text and MathML is preserved for review.</meta>'
            . '<meta property="a11y:certifiedBy">Migration Desk</meta>'
            . '<meta property="a11y:certifierCredential">WAS reviewer</meta>'
            . '<meta property="a11y:certifierReport">https://example.invalid/a11y/report</meta>'
            . '<meta property="dcterms:conformsTo">EPUB Accessibility 1.1 - WCAG 2.1 AA</meta>',
            $opfXml
        );
        $opfWithAccessibility = str_replace(
            '</metadata>',
            '<link id="a11y-record" rel="record accessibility-summary" href="meta/accessibility.json" media-type="application/ld+json" properties="accessibility-metadata schema-org"/>'
            . '</metadata>',
            $opfWithAccessibility
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithAccessibility,
            null,
            [
                ['name' => 'OEBPS/meta/accessibility.json', 'data' => $a11yRecordBytes],
            ]
        ));

        $accessibility = $result['accessibility'];
        $t->same(true, $accessibility['present']);
        $t->same(['textual', 'visual'], $accessibility['accessModes']);
        $t->same(['alternativeText', 'MathML', 'pageNavigation'], $accessibility['accessibilityFeatures']);
        $t->same(['noFlashingHazard', 'noSoundHazard'], $accessibility['accessibilityHazards']);
        $t->same('Images have alternative text and MathML is preserved for review.', $accessibility['accessibilitySummary']);
        $t->same('textual', $accessibility['accessModeSufficient'][0]['text']);
        $t->same(['textual'], $accessibility['accessModeSufficient'][0]['modes']);
        $t->same(['textual', 'visual'], $accessibility['accessModeSufficient'][1]['modes']);
        $t->same('Migration Desk', $accessibility['certification']['certifiedBy']);
        $t->same('WAS reviewer', $accessibility['certification']['certifierCredential']);
        $t->same('https://example.invalid/a11y/report', $accessibility['certification']['certifierReport']);
        $t->same(['EPUB Accessibility 1.1 - WCAG 2.1 AA'], $accessibility['certification']['conformsTo']);
        $t->same('schema:accessMode', $accessibility['entriesByProperty']['accessMode'][0]['rawProperty']);
        $t->same('property', $accessibility['entriesByProperty']['accessMode'][0]['source']);
        $t->same('schema:accessibilityHazard', $accessibility['entriesByProperty']['accessibilityHazard'][0]['rawName']);
        $t->same('name', $accessibility['entriesByProperty']['accessibilityHazard'][0]['source']);
        $t->same(1, count($accessibility['linkedRecords']));
        $t->same('a11y-record', $accessibility['linkedRecords'][0]['id']);
        $t->same('/OEBPS/meta/accessibility.json', $accessibility['linkedRecords'][0]['target']);
        $t->same(hash('sha256', $a11yRecordBytes), $accessibility['linkedRecords'][0]['byteSha256']);
        $t->same(['record', 'accessibility-summary'], $accessibility['linkedRecords'][0]['rel']);
        $t->same(['accessibility-metadata', 'schema-org'], $accessibility['linkedRecords'][0]['properties']);
        $t->same([], $accessibility['diagnostics']);
        $t->same($accessibility, $result['metadata']['accessibility']);
        $t->same($accessibility, $result['importReport']['accessibility']);
        $t->same($accessibility, $result['document']->attr('accessibility'));
    },
    'summarizes EPUB manifest resource properties for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithResourceProperties = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" properties="mathml svg remote-resources"/>',
            $opfXml
        );
        $opfWithResourceProperties = str_replace(
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml" properties="scripted switch"/>',
            $opfWithResourceProperties
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithResourceProperties));
        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }

        $report = $result['resourceProperties'];
        $t->same(1, $report['summary']['navCount']);
        $t->same(1, $report['summary']['coverImageCount']);
        $t->same(1, $report['summary']['mathmlCount']);
        $t->same(1, $report['summary']['svgCount']);
        $t->same(1, $report['summary']['remoteResourcesCount']);
        $t->same(1, $report['summary']['scriptedCount']);
        $t->same(1, $report['summary']['switchCount']);
        $t->same(2, $report['summary']['reviewRequiredCount']);

        $t->same(true, $manifestById['chapter-1']['resourceFlags']['mathml']);
        $t->same(true, $manifestById['chapter-1']['resourceFlags']['svg']);
        $t->same(true, $manifestById['chapter-1']['resourceFlags']['remoteResources']);
        $t->same(false, $manifestById['chapter-1']['resourceFlags']['scripted']);
        $t->same(['mathml', 'svg', 'remote-resources'], $manifestById['chapter-1']['resourceReviewFlags']);
        $t->same(true, $manifestById['chapter-2']['resourceFlags']['scripted']);
        $t->same(true, $manifestById['chapter-2']['resourceFlags']['switch']);
        $t->same(['scripted', 'switch'], $manifestById['chapter-2']['resourceReviewFlags']);

        $t->same('chapter-1', $report['itemsByProperty']['mathml'][0]['id']);
        $t->same('chapter-1', $report['itemsByProperty']['remote-resources'][0]['id']);
        $t->same('chapter-2', $report['itemsByProperty']['scripted'][0]['id']);
        $t->same('/OEBPS/text/chapter1.xhtml', $report['itemsById']['chapter-1']['part']);
        $t->same(['mathml', 'svg', 'remote-resources'], $report['itemsById']['chapter-1']['reviewFlags']);
        $t->same(true, $report['itemsById']['chapter-1']['reviewRequired']);
        $t->same('chapter-2', $report['reviewItems'][1]['id']);
        $t->same($report, $result['importReport']['resourceProperties']);
        $t->same($report, $result['document']->attr('resourceProperties'));
        $t->same(['mathml', 'svg', 'remote-resources'], $result['document']->children[0]->attr('resourceReviewFlags'));
        $t->same(['scripted', 'switch'], $result['document']->children[1]->attr('resourceReviewFlags'));
    },
    'reports cover image attachment candidates and unmanifested package assets' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $result = (new EpubReader())->readPackage($buildEpubPackage(
            null,
            null,
            [
                ['name' => 'OEBPS/images/unmanifested.png', 'data' => 'UNLISTED-PNG', 'compressionMethod' => 0],
            ]
        ));

        $assets = $result['importReport']['assets'];
        $assetById = [];
        foreach ($assets['items'] as $asset) {
            $assetById[$asset['id']] = $asset;
        }

        $t->same(count($result['assets']), $assets['count']);
        $t->same('cover-image', $assets['coverImage']['id']);
        $t->same('/OEBPS/images/cover.png', $assets['coverImage']['part']);
        $t->same('image/png', $assets['coverImage']['mediaType']);
        $t->same(true, $assets['coverImage']['isCoverImage']);
        $t->same(['manifest-property-cover-image', 'meta-name-cover'], $assets['coverImage']['coverImageSources']);
        $t->same(true, $assets['coverImage']['attachmentCandidate']);
        $t->same('cover-image', $assets['coverImage']['attachmentRole']);
        $t->same(hash('sha256', 'PNGDATA'), $assets['coverImage']['byteSha256']);
        $t->same($assetById['cover-image'], $assets['coverImage']);

        $t->same(1, $assets['attachmentCandidateCount']);
        $t->same('cover-image', $assets['attachmentCandidates'][0]['id']);
        $t->same(false, $assetById['style']['attachmentCandidate']);
        $t->same(true, $assetById['style']['exportCandidate']);
        $t->same(hash('sha256', 'body { color: #222; }'), $assetById['style']['byteSha256']);
        $t->same(false, $assetById['toc']['exportCandidate']);
        $t->same(null, $assetById['toc']['byteSha256']);

        $t->same(1, $assets['unmanifestedCount']);
        $t->same('/OEBPS/images/unmanifested.png', $assets['unmanifestedItems'][0]['part']);
        $t->same('image/png', $assets['unmanifestedItems'][0]['mediaType']);
        $t->same(12, $assets['unmanifestedItems'][0]['byteLength']);
        $t->same(hash('sha256', 'UNLISTED-PNG'), $assets['unmanifestedItems'][0]['byteSha256']);
        $t->same(true, $assets['unmanifestedItems'][0]['attachmentCandidate']);
        $t->same('unmanifested-package-resource', $assets['unmanifestedItems'][0]['diagnostics'][0]['type']);
        $t->same($assets['unmanifestedItems'], $assets['diagnostics'][0]['items']);
        $t->same('unmanifested-package-assets', $assets['diagnostics'][0]['type']);
    },
    'reports OCF encryption and obfuscated font resources without dropping XHTML handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $encryptionXml): void {
        $opfWithFont = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="font-main" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithFont,
            null,
            [
                ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
                ['name' => 'OEBPS/fonts/source.otf', 'data' => 'OBFUSCATED-FONT'],
            ]
        ));

        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }
        $assetById = [];
        foreach ($result['assets'] as $asset) {
            $assetById[$asset['id']] = $asset;
        }

        $t->same(true, $result['encryption']['present']);
        $t->same('/META-INF/encryption.xml', $result['encryption']['part']);
        $t->same(1, count($result['encryption']['items']));
        $t->same('/OEBPS/fonts/source.otf', $result['encryption']['items'][0]['part']);
        $t->same('font-main', $result['encryption']['items'][0]['manifestId']);
        $t->same('application/vnd.ms-opentype', $result['encryption']['items'][0]['mediaType']);
        $t->same('http://www.idpf.org/2008/embedding', $result['encryption']['items'][0]['algorithm']);
        $t->same(true, $result['encryption']['items'][0]['obfuscatedFont']);
        $t->same(true, $manifestById['font-main']['encrypted']);
        $t->same(true, $assetById['font-main']['encrypted']);
        $t->same(false, $assetById['font-main']['canExposeBytes']);
        $t->same('/OEBPS/fonts/source.otf', $result['importReport']['encryption']['obfuscatedFonts'][0]['part']);
        $t->same([], $result['importReport']['encryption']['diagnostics']);
        $t->same(2, count($result['document']->children));
        $t->contains('Chapter XHTML stays available', $result['document']->children[0]->attr('html'));
    },
    'parses EPUB3 SMIL media overlays for spine audio review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $smilXml): void {
        $opfWithOverlay = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter-1"/><item id="mo-chapter-1" href="overlays/chapter1.smil" media-type="application/smil+xml"/><item id="audio-chapter-1" href="audio/chapter1.mp3" media-type="audio/mpeg"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithOverlay,
            null,
            [
                ['name' => 'OEBPS/overlays/chapter1.smil', 'data' => $smilXml],
                ['name' => 'OEBPS/audio/chapter1.mp3', 'data' => 'MP3-DATA'],
            ]
        ));

        $overlay = $result['mediaOverlays']['mo-chapter-1'];
        $t->same('/OEBPS/overlays/chapter1.smil', $overlay['part']);
        $t->same(['chapter-1'], $overlay['referencedBy']);
        $t->same('/OEBPS/text/chapter1.xhtml', $overlay['textRefTarget']);
        $t->same(2, count($overlay['items']));
        $t->same('intro-audio', $overlay['items'][0]['id']);
        $t->same(['bodymatter'], $overlay['items'][0]['types']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $overlay['items'][0]['textTarget']);
        $t->same('/OEBPS/audio/chapter1.mp3', $overlay['items'][0]['audioTarget']);
        $t->same(true, $overlay['items'][0]['audioExists']);
        $t->same('0:00:01.000', $overlay['items'][0]['clipBegin']);
        $t->same(1.0, $overlay['items'][0]['clipBeginSeconds']);
        $t->same('0:00:05.500', $overlay['items'][0]['clipEnd']);
        $t->same(5.5, $overlay['items'][0]['clipEndSeconds']);
        $t->same(4.5, $overlay['items'][0]['clipDurationSeconds']);
        $t->same(true, $overlay['items'][0]['clipValid']);
        $t->same([], $overlay['items'][0]['clipDiagnostics']);
        $t->same('page-audio', $overlay['items'][1]['id']);
        $t->same('/OEBPS/text/chapter1.xhtml#page-1', $overlay['items'][1]['textTarget']);
        $t->same('/OEBPS/audio/chapter1.mp3', $overlay['items'][1]['audioTarget']);
        $t->same(5.5, $overlay['items'][1]['clipBeginSeconds']);
        $t->same(7.0, $overlay['items'][1]['clipEndSeconds']);
        $t->same(1.5, $overlay['items'][1]['clipDurationSeconds']);
        $t->same([], $overlay['diagnostics']);
        $t->same('mo-chapter-1', $result['spine'][0]['mediaOverlay']);
        $t->same('mo-chapter-1', $result['xhtmlAssets'][1]['mediaOverlay']);
        $t->same('mo-chapter-1', $result['document']->children[0]->attr('mediaOverlay'));
        $t->same($overlay, $result['importReport']['mediaOverlays']['mo-chapter-1']);
    },
    'reports OPF media overlay duration metadata for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $smilXml): void {
        $opfWithOverlayDuration = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta property="media:duration">0:00:12.500</meta>'
            . '<meta property="media:duration" refines="#mo-chapter-1">0:00:06.500</meta>'
            . '<meta property="media:duration" refines="#missing-overlay">not-a-clock</meta>',
            $opfXml
        );
        $opfWithOverlayDuration = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter-1"/><item id="mo-chapter-1" href="overlays/chapter1.smil" media-type="application/smil+xml"/><item id="audio-chapter-1" href="audio/chapter1.mp3" media-type="audio/mpeg"/>',
            $opfWithOverlayDuration
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithOverlayDuration,
            null,
            [
                ['name' => 'OEBPS/overlays/chapter1.smil', 'data' => $smilXml],
                ['name' => 'OEBPS/audio/chapter1.mp3', 'data' => 'MP3-DATA'],
            ]
        ));

        $durations = $result['mediaDurations'];
        $t->same(true, $durations['present']);
        $t->same('0:00:12.500', $durations['total']['duration']);
        $t->same(12.5, $durations['total']['durationSeconds']);
        $t->same(true, $durations['total']['validClock']);
        $t->same('publication', $durations['total']['scope']);
        $t->same(null, $durations['total']['refines']);
        $t->same(null, $durations['total']['subjectId']);
        $t->same([], $durations['total']['diagnostics']);
        $t->same(1, count($durations['totals']));

        $overlayDuration = $durations['overlaysById']['mo-chapter-1'];
        $t->same('0:00:06.500', $overlayDuration['duration']);
        $t->same(6.5, $overlayDuration['durationSeconds']);
        $t->same(true, $overlayDuration['validClock']);
        $t->same('media-overlay', $overlayDuration['scope']);
        $t->same('#mo-chapter-1', $overlayDuration['refines']);
        $t->same('mo-chapter-1', $overlayDuration['subjectId']);
        $t->same('mo-chapter-1', $overlayDuration['manifestId']);
        $t->same('/OEBPS/overlays/chapter1.smil', $overlayDuration['manifestPart']);
        $t->same('application/smil+xml', $overlayDuration['manifestMediaType']);
        $t->same(['chapter-1'], $overlayDuration['referencedBy']);
        $t->same([], $overlayDuration['diagnostics']);

        $t->same(3, count($durations['items']));
        $t->same(2, count($durations['diagnostics']));
        $t->same('invalid-media-duration-clock', $durations['diagnostics'][0]['type']);
        $t->same('not-a-clock', $durations['diagnostics'][0]['duration']);
        $t->same('media-duration-refines-missing-manifest-item', $durations['diagnostics'][1]['type']);
        $t->same('missing-overlay', $durations['diagnostics'][1]['subjectId']);

        $overlay = $result['mediaOverlays']['mo-chapter-1'];
        $t->same('0:00:06.500', $overlay['duration']);
        $t->same(6.5, $overlay['durationSeconds']);
        $t->same($overlayDuration, $overlay['durationMetadata']);
        $t->same($durations, $result['metadata']['mediaDurations']);
        $t->same($durations, $result['importReport']['mediaDurations']);
        $t->same($durations, $result['document']->attr('mediaDurations'));
    },
    'normalizes EPUB3 SMIL media overlay clip timing for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $smilWithClipTiming = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL">
  <body>
    <seq id="timing-overlay" epub:textref="../text/chapter1.xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
      <par id="metric-clip">
        <text src="../text/chapter1.xhtml#intro"/>
        <audio src="../audio/chapter1.mp3" clipBegin="1.25s" clipEnd="2250ms"/>
      </par>
      <par id="invalid-begin">
        <text src="../text/chapter1.xhtml#intro"/>
        <audio src="../audio/chapter1.mp3" clipBegin="not-a-clock" clipEnd="2s"/>
      </par>
      <par id="reversed-clip">
        <text src="../text/chapter1.xhtml#intro"/>
        <audio src="../audio/chapter1.mp3" clipBegin="0:00:05.000" clipEnd="0:00:04.000"/>
      </par>
    </seq>
  </body>
</smil>
XML;
        $opfWithOverlay = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter-1"/><item id="mo-chapter-1" href="overlays/chapter1.smil" media-type="application/smil+xml"/><item id="audio-chapter-1" href="audio/chapter1.mp3" media-type="audio/mpeg"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithOverlay,
            null,
            [
                ['name' => 'OEBPS/overlays/chapter1.smil', 'data' => $smilWithClipTiming],
                ['name' => 'OEBPS/audio/chapter1.mp3', 'data' => 'MP3-DATA'],
            ]
        ));

        $items = $result['mediaOverlays']['mo-chapter-1']['items'];
        $t->same(3, count($items));

        $t->same('metric-clip', $items[0]['id']);
        $t->same('1.25s', $items[0]['clipBegin']);
        $t->same(1.25, $items[0]['clipBeginSeconds']);
        $t->same('2250ms', $items[0]['clipEnd']);
        $t->same(2.25, $items[0]['clipEndSeconds']);
        $t->same(1.0, $items[0]['clipDurationSeconds']);
        $t->same(true, $items[0]['clipValid']);
        $t->same([], $items[0]['clipDiagnostics']);
        $t->same([], $items[0]['diagnostics']);

        $t->same('invalid-begin', $items[1]['id']);
        $t->same('not-a-clock', $items[1]['clipBegin']);
        $t->same(null, $items[1]['clipBeginSeconds']);
        $t->same(2.0, $items[1]['clipEndSeconds']);
        $t->same(null, $items[1]['clipDurationSeconds']);
        $t->same(false, $items[1]['clipValid']);
        $t->same('invalid-media-overlay-clip-begin', $items[1]['clipDiagnostics'][0]['type']);
        $t->same('not-a-clock', $items[1]['clipDiagnostics'][0]['clipBegin']);
        $t->same($items[1]['clipDiagnostics'], $items[1]['diagnostics']);

        $t->same('reversed-clip', $items[2]['id']);
        $t->same(5.0, $items[2]['clipBeginSeconds']);
        $t->same(4.0, $items[2]['clipEndSeconds']);
        $t->same(null, $items[2]['clipDurationSeconds']);
        $t->same(false, $items[2]['clipValid']);
        $t->same('media-overlay-clip-end-before-begin', $items[2]['clipDiagnostics'][0]['type']);
        $t->same(5.0, $items[2]['clipDiagnostics'][0]['clipBeginSeconds']);
        $t->same(4.0, $items[2]['clipDiagnostics'][0]['clipEndSeconds']);
        $t->same($result['mediaOverlays']['mo-chapter-1'], $result['importReport']['mediaOverlays']['mo-chapter-1']);
    },
    'retains remote nav NCX and media-overlay references without fetching' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $remoteNavXhtml, $remoteNcxXml, $remoteSmilXml): void {
        $opfWithOverlay = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter-1"/><item id="mo-chapter-1" href="overlays/chapter1.smil" media-type="application/smil+xml"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithOverlay,
            null,
            [
                ['name' => 'OEBPS/overlays/chapter1.smil', 'data' => $remoteSmilXml],
            ],
            $remoteNavXhtml,
            $remoteNcxXml
        ));

        $remoteNavItem = $result['nav']['items'][1];
        $t->same('Remote audit record', $remoteNavItem['title']);
        $t->same('https://cdn.example.test/epub/source-note.html', $remoteNavItem['target']);
        $t->same(true, $remoteNavItem['external']);
        $t->same(null, $remoteNavItem['part']);
        $t->same(false, $remoteNavItem['exists']);
        $t->same('external-nav-reference', $remoteNavItem['diagnostics'][0]['type']);

        $remoteNcxItem = $result['ncx']['items'][0];
        $t->same('Remote appendix', $remoteNcxItem['title']);
        $t->same('https://cdn.example.test/epub/appendix.xhtml', $remoteNcxItem['target']);
        $t->same(true, $remoteNcxItem['external']);
        $t->same(null, $remoteNcxItem['part']);
        $t->same(false, $remoteNcxItem['exists']);
        $t->same('external-ncx-reference', $remoteNcxItem['diagnostics'][0]['type']);

        $overlay = $result['mediaOverlays']['mo-chapter-1'];
        $t->same('https://cdn.example.test/remote/chapter.xhtml', $overlay['textRefTarget']);
        $t->same(true, $overlay['textRefExternal']);
        $t->same('external-media-overlay-reference', $overlay['textRefDiagnostics'][0]['type']);
        $t->same(1, count($overlay['items']));
        $t->same('https://cdn.example.test/remote/chapter.xhtml#intro', $overlay['items'][0]['textTarget']);
        $t->same(true, $overlay['items'][0]['textExternal']);
        $t->same('https://cdn.example.test/audio/chapter.mp3', $overlay['items'][0]['audioTarget']);
        $t->same(true, $overlay['items'][0]['audioExternal']);
        $t->same(false, $overlay['items'][0]['audioExists']);
        $t->same('external-media-overlay-reference', $overlay['items'][0]['diagnostics'][0]['type']);
        $t->same($overlay, $result['importReport']['mediaOverlays']['mo-chapter-1']);
        $t->same(2, count($result['document']->children));
    },
    'checks EPUB mimetype placement by local ZIP header order' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $containerXml, $opfXml, $navXhtml, $chapter1Xhtml, $chapter2Xhtml, $ncxXml): void {
        $parts = [
            ['name' => 'mimetype', 'data' => EpubReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $containerXml],
            ['name' => 'OEBPS/package.opf', 'data' => $opfXml],
            ['name' => 'OEBPS/nav.xhtml', 'data' => $navXhtml],
            ['name' => 'OEBPS/text/chapter1.xhtml', 'data' => $chapter1Xhtml],
            ['name' => 'OEBPS/text/chapter2.xhtml', 'data' => $chapter2Xhtml],
            ['name' => 'OEBPS/toc.ncx', 'data' => $ncxXml],
            ['name' => 'OEBPS/styles/book.css', 'data' => 'body { color: #222; }'],
            ['name' => 'OEBPS/images/cover.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
        ];

        $result = (new EpubReader())->readPackage($buildZipPackageWithCentralDirectoryOrder($parts, [
            'META-INF/container.xml',
            'OEBPS/package.opf',
            'OEBPS/nav.xhtml',
            'OEBPS/text/chapter1.xhtml',
            'OEBPS/text/chapter2.xhtml',
            'OEBPS/toc.ncx',
            'OEBPS/styles/book.css',
            'OEBPS/images/cover.png',
            'mimetype',
        ]));

        $t->same('WordPress Import EPUB', $result['metadata']['title']);
        $t->same('/OEBPS/package.opf', $result['opfPart']);
        $t->same(2, count($result['document']->children));
    },
    'rejects malformed EPUB packages before conversion handoff' => static function (TestRunner $t) use ($buildEpubPackage, $buildZipPackageWithCentralDirectoryOrder, $containerXml, $opfXml): void {
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
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildZipPackageWithCentralDirectoryOrder([
            ['name' => 'META-INF/container.xml', 'data' => $containerXml],
            ['name' => 'mimetype', 'data' => EpubReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'OEBPS/package.opf', 'data' => $opfXml],
        ], [
            'mimetype',
            'META-INF/container.xml',
            'OEBPS/package.opf',
        ])));

        $missingSpineOpf = str_replace('<itemref idref="chapter-2" linear="no"/>', '<itemref idref="missing"/>', $opfXml);
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage($buildEpubPackage($missingSpineOpf)));
    },
];
