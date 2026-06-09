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

$rightsXml = <<<'XML'
<rights xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:drm="https://example.invalid/epub-drm" xml:lang="en">
  <drm:license id="local-license" href="META-INF/licenses/source-license.xml" media-type="application/xml">Migration license</drm:license>
  <drm:policy id="remote-policy" href="https://rights.example.invalid/policy.xml">Remote policy</drm:policy>
  <drm:notice id="missing-notice" href="META-INF/licenses/missing.xml">Missing notice</drm:notice>
</rights>
XML;

$metadataXml = <<<'XML'
<metadata xmlns="http://www.idpf.org/2013/metadata" xmlns:review="https://example.invalid/epub-review" xml:lang="en">
  <review:source id="source-record" href="META-INF/review/source.json" media-type="application/ld+json">Migration source record</review:source>
  <review:policy id="remote-policy" href="https://metadata.example.test/container-policy.json">Remote container policy</review:policy>
  <review:notice id="missing-notice" href="META-INF/review/missing.json">Missing review notice</review:notice>
  <review:checksum id="self-check" URI="#container-digest">Container digest</review:checksum>
  <legacy xmlns="" id="legacy-note">Legacy unqualified note</legacy>
</metadata>
XML;

$ocfManifestXml = sprintf(<<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/epub+zip"/>
  <manifest:file-entry manifest:full-path="OEBPS/package.opf" manifest:media-type="application/oebps-package+xml"/>
  <manifest:file-entry manifest:full-path="OEBPS/text/chapter1.xhtml" manifest:media-type="application/xhtml+xml" manifest:size="%d"/>
  <manifest:file-entry manifest:full-path="OEBPS/styles/book.css" manifest:media-type="text/css" manifest:size="4"/>
  <manifest:file-entry manifest:full-path="OEBPS/images/unmanifested-review.png" manifest:media-type="image/png" manifest:size="16"/>
  <manifest:file-entry manifest:full-path="OEBPS/images/missing-review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="../outside.txt" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:media-type="text/plain"/>
</manifest:manifest>
XML, strlen($chapter1Xhtml));

$signaturesXml = <<<'XML'
<signatures xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:Signature Id="package-signature">
    <ds:SignedInfo>
      <ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
      <ds:Reference URI="OEBPS/text/chapter1.xhtml#intro">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>chapter-digest</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="OEBPS/images/missing-signed.png">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>missing-digest</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="https://signatures.example.invalid/source-manifest.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>remote-digest</ds:DigestValue>
      </ds:Reference>
    </ds:SignedInfo>
    <ds:SignatureValue>signed-review-packet</ds:SignatureValue>
  </ds:Signature>
</signatures>
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
    'reports OCF container links with package targets and diagnostics' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $containerRecord = '{"source":"wordpress-export","kind":"epub-container-link"}';
        $containerXml = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="OEBPS/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
  <links>
    <link href="META-INF/container-record.json" rel="record alternate" media-type="application/ld+json" properties="schema-org reviewer"/>
    <link href="OEBPS/text/chapter1.xhtml#epubcfi(/6/2[chapter-1]!/4/2/1:12)" rel="preview" media-type="application/xhtml+xml"/>
    <link href="https://metadata.example.test/source-record.json" rel="record" media-type="application/ld+json"/>
    <link href="META-INF/missing-record.json" rel="record" media-type="application/json"/>
  </links>
</container>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideContainerXml: $containerXml,
            extraParts: [
                ['name' => 'META-INF/container-record.json', 'data' => $containerRecord],
            ],
        ));
        $container = $result['container'];

        $t->same(4, $container['linkCount']);
        $t->same(3, count($container['linksByRel']['record']));
        $t->same(1, count($container['linksByRel']['alternate']));
        $t->same(1, count($container['linksByRel']['preview']));

        $local = $container['links'][0];
        $t->same(0, $local['index']);
        $t->same(['record', 'alternate'], $local['rel']);
        $t->same(['schema-org', 'reviewer'], $local['properties']);
        $t->same('application/ld+json', $local['mediaType']);
        $t->same('/META-INF/container-record.json', $local['target']);
        $t->same('/META-INF/container-record.json', $local['part']);
        $t->same(false, $local['external']);
        $t->same(true, $local['exists']);
        $t->same(strlen($containerRecord), $local['byteLength']);
        $t->same(hash('sha256', $containerRecord), $local['byteSha256']);
        $t->same([], $local['diagnostics']);

        $preview = $container['links'][1];
        $t->same('/OEBPS/text/chapter1.xhtml#epubcfi(/6/2[chapter-1]!/4/2/1:12)', $preview['target']);
        $t->same('/OEBPS/text/chapter1.xhtml', $preview['part']);
        $t->same('epubcfi(/6/2[chapter-1]!/4/2/1:12)', $preview['fragment']);
        $t->same('epub-cfi', $preview['fragmentKind']);
        $t->same('/6/2[chapter-1]!/4/2/1:12', $preview['epubCfi']['path']);
        $t->same(true, $preview['exists']);

        $remote = $container['links'][2];
        $t->same(true, $remote['external']);
        $t->same(false, $remote['exists']);
        $t->same(null, $remote['part']);
        $t->same('external-container-link-reference', $remote['diagnostics'][0]['type']);

        $missing = $container['links'][3];
        $t->same(false, $missing['exists']);
        $t->same('/META-INF/missing-record.json', $missing['part']);
        $t->same('missing-container-link-reference', $missing['diagnostics'][0]['type']);

        $t->same(2, count($container['linkDiagnostics']));
        $t->same(2, $container['linkDiagnostics'][0]['index']);
        $t->same('external-container-link-reference', $container['linkDiagnostics'][0]['type']);
        $t->same(3, $container['linkDiagnostics'][1]['index']);
        $t->same('missing-container-link-reference', $container['linkDiagnostics'][1]['type']);
        $t->same($container, $result['importReport']['container']);
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
    'resolves OPF metadata property vocabulary terms from package prefixes' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithVocabulary = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" id="package-record" version="3.0" unique-identifier="pub-id" xml:lang="en" prefix="schema: https://schema.org/ dcterms: http://purl.org/dc/terms/">',
            $opfXml
        );
        $opfWithVocabulary = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta property="schema:accessibilitySummary">Vocabulary review summary</meta>'
            . '<meta refines="#package-record" property="schema:name">Vocabulary package record</meta>'
            . '<meta refines="#chapter-1" property="schema:encodingFormat">application/xhtml+xml</meta>'
            . '<meta refines="#reading-order" property="schema:position">primary sequence</meta>'
            . '<meta property="unknown:reviewFlag">unresolved prefix remains visible</meta>',
            $opfWithVocabulary
        );
        $opfWithVocabulary = str_replace(
            '<spine toc="toc">',
            '<spine id="reading-order" toc="toc">',
            $opfWithVocabulary
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithVocabulary));
        $metadata = $result['metadata'];
        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }

        $t->same('https://schema.org/accessibilitySummary', $metadata['metaProperties']['schema:accessibilitySummary'][0]['propertyVocabulary']['iri']);
        $t->same('http://purl.org/dc/terms/modified', $metadata['metaProperties']['dcterms:modified'][0]['propertyVocabulary']['iri']);
        $t->same('https://schema.org/name', $result['package']['refinements']['schema:name'][0]['propertyVocabulary']['iri']);
        $t->same('https://schema.org/encodingFormat', $manifestById['chapter-1']['refinements']['schema:encodingFormat'][0]['propertyVocabulary']['iri']);
        $t->same('https://schema.org/position', $result['spineProperties']['refinements']['schema:position'][0]['propertyVocabulary']['iri']);
        $t->same(false, $metadata['metaProperties']['unknown:reviewFlag'][0]['propertyVocabulary']['resolved']);
        $t->same('unknown', $metadata['metaProperties']['unknown:reviewFlag'][0]['propertyVocabulary']['prefix']);
        $t->same('unknown-package-prefix', $metadata['metaProperties']['unknown:reviewFlag'][0]['propertyVocabulary']['diagnostics'][0]['type']);
        $t->same(5, $metadata['vocabulary']['resolvedPropertyCount']);
        $t->same(1, $metadata['vocabulary']['unresolvedPropertyCount']);
        $t->same(4, $metadata['vocabulary']['byPrefix']['schema']['entryCount']);
        $t->same(1, $metadata['vocabulary']['byPrefix']['dcterms']['entryCount']);
        $t->same($metadata['vocabulary'], $result['importReport']['metadata']['vocabulary']);
        $t->same($metadata['vocabulary'], $result['document']->attr('metadata')['vocabulary']);
    },
    'reports OPF vendor metadata fields for package review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithVendorMetadata = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en" prefix="ibooks: http://vocabulary.itunes.apple.com/rdf/ibooks/vocabulary-extensions-1.0/">',
            $opfXml
        );
        $opfWithVendorMetadata = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta property="ibooks:specified-fonts">true</meta>'
            . '<meta property="ibooks:version">1.2</meta>'
            . '<meta property="calibre:series" content="Migration Library"/>'
            . '<meta property="calibre:series_index" content="7"/>',
            $opfWithVendorMetadata
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithVendorMetadata));
        $vendorMetadata = $result['metadata']['vendorMetadata'] ?? [];

        $t->same(true, $vendorMetadata['present'] ?? null);
        $t->same(4, $vendorMetadata['itemCount'] ?? null);
        $t->same(2, $vendorMetadata['ibooksCount'] ?? null);
        $t->same(2, $vendorMetadata['calibreCount'] ?? null);
        $t->same('true', $vendorMetadata['ibooks']['specified-fonts'][0]['value'] ?? null);
        $t->same('true', $vendorMetadata['ibooks']['specified-fonts'][0]['text'] ?? null);
        $t->same('http://vocabulary.itunes.apple.com/rdf/ibooks/vocabulary-extensions-1.0/specified-fonts', $vendorMetadata['ibooks']['specified-fonts'][0]['propertyVocabulary']['iri'] ?? null);
        $t->same('1.2', $vendorMetadata['ibooks']['version'][0]['value'] ?? null);
        $t->same('Migration Library', $vendorMetadata['calibre']['series'][0]['value'] ?? null);
        $t->same('', $vendorMetadata['calibre']['series'][0]['text'] ?? null);
        $t->same('Migration Library', $vendorMetadata['calibre']['series'][0]['content'] ?? null);
        $t->same('7', $vendorMetadata['calibre']['series_index'][0]['value'] ?? null);
        $t->same('series_index', $vendorMetadata['itemsByVendor']['calibre'][1]['field'] ?? null);
        $t->same([], $vendorMetadata['diagnostics'] ?? null);
        $t->same($vendorMetadata, $result['importReport']['metadata']['vendorMetadata']);
        $t->same($vendorMetadata, $result['document']->attr('metadata')['vendorMetadata']);
    },
    'reports OPF belongs-to-collection metadata for package review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $seriesRecord = '{"name":"Migration Series","source":"wordpress-epub"}';
        $opfWithCollectionMembership = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta property="belongs-to-collection" id="series-membership" xml:lang="en" dir="ltr">Migration Series</meta>'
            . '<meta refines="#series-membership" property="collection-type">series</meta>'
            . '<meta refines="#series-membership" property="group-position">3</meta>'
            . '<meta refines="#series-membership" property="display-seq">1</meta>'
            . '<meta refines="#series-membership" property="file-as">Migration Series</meta>'
            . '<meta property="belongs-to-collection" id="set-membership" content="Reviewer Set"/>'
            . '<meta refines="#set-membership" property="collection-type">set</meta>'
            . '<meta refines="#set-membership" property="group-position">appendix</meta>',
            $opfXml
        );
        $opfWithCollectionMembership = str_replace(
            '<meta name="cover" content="cover-image"/>',
            '<meta name="cover" content="cover-image"/>'
            . '<link id="series-record" rel="record" refines="#series-membership" href="meta/series.json" media-type="application/ld+json"/>',
            $opfWithCollectionMembership
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithCollectionMembership,
            null,
            [
                ['name' => 'OEBPS/meta/series.json', 'data' => $seriesRecord],
            ]
        ));

        $membership = $result['metadata']['collectionMembership'];
        $t->same(true, $membership['present']);
        $t->same(2, $membership['count']);
        $t->same(['series', 'set'], $membership['types']);
        $t->same(2, $membership['typedCount']);
        $t->same(1, $membership['positionedCount']);
        $t->same(1, $membership['invalidGroupPositionCount']);
        $t->same(1, count($membership['diagnostics']));
        $t->same('invalid-collection-group-position', $membership['diagnostics'][0]['type']);
        $t->same('set-membership', $membership['diagnostics'][0]['id']);

        $series = $membership['items'][0];
        $t->same(0, $series['index']);
        $t->same('series-membership', $series['id']);
        $t->same('Migration Series', $series['title']);
        $t->same('Migration Series', $series['value']);
        $t->same('series', $series['collectionType']);
        $t->same('3', $series['groupPosition']);
        $t->same(3.0, $series['groupPositionNumber']);
        $t->same('1', $series['displaySeq']);
        $t->same('Migration Series', $series['fileAs']);
        $t->same('en', $series['language']);
        $t->same('ltr', $series['direction']);
        $t->same([], $series['diagnostics']);
        $t->same('series-record', $series['linkedResources'][0]['id']);
        $t->same('/OEBPS/meta/series.json', $series['linkedResources'][0]['target']);
        $t->same(hash('sha256', $seriesRecord), $series['linkedResources'][0]['byteSha256']);

        $set = $membership['items'][1];
        $t->same('set-membership', $set['id']);
        $t->same('Reviewer Set', $set['title']);
        $t->same('Reviewer Set', $set['content']);
        $t->same('set', $set['collectionType']);
        $t->same('appendix', $set['groupPosition']);
        $t->same(null, $set['groupPositionNumber']);
        $t->same('invalid-collection-group-position', $set['diagnostics'][0]['type']);

        $t->same($series, $membership['byType']['series'][0]);
        $t->same($set, $membership['byType']['set'][0]);
        $t->same($membership, $result['importReport']['metadata']['collectionMembership']);
        $t->same($membership, $result['document']->attr('metadata')['collectionMembership']);
    },
    'reports OPF unique identifier binding and diagnostics for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $reader = new EpubReader();
        $result = $reader->readPackage($buildEpubPackage());
        $identifier = $result['metadata']['uniqueIdentifier'];

        $t->same(true, $identifier['specified']);
        $t->same('pub-id', $identifier['id']);
        $t->same(true, $identifier['matched']);
        $t->same('urn:uuid:wp-epub-source-42', $identifier['value']);
        $t->same('unique-identifier', $identifier['selectedBy']);
        $t->same(1, $identifier['identifierCount']);
        $t->same(1, $identifier['matchCount']);
        $t->same(0, $identifier['duplicateMatchCount']);
        $t->same(0, $identifier['matchedEntries'][0]['index']);
        $t->same('pub-id', $identifier['matchedEntries'][0]['id']);
        $t->same('urn:uuid:wp-epub-source-42', $identifier['matchedEntries'][0]['text']);
        $t->same([], $identifier['diagnostics']);
        $t->same($identifier, $result['package']['uniqueIdentifier']);
        $t->same($identifier, $result['importReport']['metadata']['uniqueIdentifier']);
        $t->same($identifier, $result['document']->attr('metadata')['uniqueIdentifier']);

        $missingIdOpf = str_replace('unique-identifier="pub-id"', 'unique-identifier="missing-id"', $opfXml);
        $missingResult = $reader->readPackage($buildEpubPackage($missingIdOpf));
        $missingIdentifier = $missingResult['metadata']['uniqueIdentifier'];
        $t->same(true, $missingIdentifier['specified']);
        $t->same('missing-id', $missingIdentifier['id']);
        $t->same(false, $missingIdentifier['matched']);
        $t->same('urn:uuid:wp-epub-source-42', $missingIdentifier['value']);
        $t->same('first-dc-identifier', $missingIdentifier['selectedBy']);
        $t->same(1, $missingIdentifier['identifierCount']);
        $t->same(0, $missingIdentifier['matchCount']);
        $t->same('unique-identifier-not-found', $missingIdentifier['diagnostics'][0]['type']);
        $t->same('missing-id', $missingIdentifier['diagnostics'][0]['id']);
        $t->same($missingIdentifier, $missingResult['package']['uniqueIdentifier']);

        $noUniqueIdOpf = str_replace(' unique-identifier="pub-id"', '', $opfXml);
        $noUniqueResult = $reader->readPackage($buildEpubPackage($noUniqueIdOpf));
        $noUniqueIdentifier = $noUniqueResult['metadata']['uniqueIdentifier'];
        $t->same(false, $noUniqueIdentifier['specified']);
        $t->same(null, $noUniqueIdentifier['id']);
        $t->same(false, $noUniqueIdentifier['matched']);
        $t->same('urn:uuid:wp-epub-source-42', $noUniqueIdentifier['value']);
        $t->same('first-dc-identifier', $noUniqueIdentifier['selectedBy']);
        $t->same('missing-unique-identifier', $noUniqueIdentifier['diagnostics'][0]['type']);
        $t->same($noUniqueIdentifier, $noUniqueResult['document']->attr('metadata')['uniqueIdentifier']);

        $duplicateIdOpf = str_replace(
            '<dc:title>WordPress Import EPUB</dc:title>',
            '<dc:identifier id="pub-id">urn:uuid:duplicate-review-copy</dc:identifier><dc:title>WordPress Import EPUB</dc:title>',
            $opfXml
        );
        $duplicateResult = $reader->readPackage($buildEpubPackage($duplicateIdOpf));
        $duplicateIdentifier = $duplicateResult['metadata']['uniqueIdentifier'];
        $t->same(2, $duplicateIdentifier['identifierCount']);
        $t->same(2, $duplicateIdentifier['matchCount']);
        $t->same(1, $duplicateIdentifier['duplicateMatchCount']);
        $t->same('urn:uuid:wp-epub-source-42', $duplicateIdentifier['value']);
        $t->same('urn:uuid:duplicate-review-copy', $duplicateIdentifier['matchedEntries'][1]['text']);
        $t->same('duplicate-unique-identifier-id', $duplicateIdentifier['diagnostics'][0]['type']);
        $t->same(['urn:uuid:wp-epub-source-42', 'urn:uuid:duplicate-review-copy'], $duplicateIdentifier['diagnostics'][0]['values']);

        $withoutIdentifierOpf = str_replace(
            '    <dc:identifier id="pub-id">urn:uuid:wp-epub-source-42</dc:identifier>' . "\n",
            '',
            $opfXml
        );
        $withoutIdentifierResult = $reader->readPackage($buildEpubPackage($withoutIdentifierOpf));
        $withoutIdentifier = $withoutIdentifierResult['metadata']['uniqueIdentifier'];
        $t->same(true, $withoutIdentifier['specified']);
        $t->same('pub-id', $withoutIdentifier['id']);
        $t->same(null, $withoutIdentifier['value']);
        $t->same(null, $withoutIdentifier['selectedBy']);
        $t->same(0, $withoutIdentifier['identifierCount']);
        $t->same('unique-identifier-not-found', $withoutIdentifier['diagnostics'][0]['type']);
        $t->same('missing-dc-identifier', $withoutIdentifier['diagnostics'][1]['type']);
    },
    'summarizes OPF identifier schemes and date events for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithIdentifierAndDateMetadata = str_replace(
            '<dc:identifier id="pub-id">urn:uuid:wp-epub-source-42</dc:identifier>',
            '<dc:identifier id="pub-id" scheme="UUID">urn:uuid:wp-epub-source-42</dc:identifier>'
            . '<dc:identifier id="isbn-id" scheme="ISBN">9781234567890</dc:identifier>'
            . '<dc:identifier id="duplicate-id" scheme="UUID">urn:uuid:wp-epub-source-42</dc:identifier>',
            $opfXml
        );
        $opfWithIdentifierAndDateMetadata = str_replace(
            '<dc:language>en</dc:language>',
            '<dc:date id="publication-date" event="publication">2026-06-01</dc:date>'
            . '<dc:date id="review-date">2026-06-05</dc:date>'
            . '<dc:language>en</dc:language>',
            $opfWithIdentifierAndDateMetadata
        );
        $opfWithIdentifierAndDateMetadata = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta refines="#pub-id" property="identifier-type" scheme="onix:codelist5">22</meta>'
            . '<meta refines="#isbn-id" property="identifier-type" scheme="onix:codelist5">15</meta>'
            . '<meta refines="#duplicate-id" property="identifier-type" scheme="onix:codelist5">22</meta>'
            . '<meta refines="#review-date" property="event">review</meta>'
            . '<meta refines="#review-date" property="display-seq">2</meta>',
            $opfWithIdentifierAndDateMetadata
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithIdentifierAndDateMetadata));
        $metadata = $result['metadata'];
        $identifierDetails = $metadata['identifierDetails'];
        $dateDetails = $metadata['dateDetails'];

        $t->same(3, count($identifierDetails));
        $t->same('pub-id', $identifierDetails[0]['id']);
        $t->same('UUID', $identifierDetails[0]['scheme']);
        $t->same('22', $identifierDetails[0]['identifierType']);
        $t->same('onix:codelist5', $identifierDetails[0]['identifierTypeScheme']);
        $t->same(true, $identifierDetails[0]['selectedByUniqueIdentifier']);
        $t->same(true, $identifierDetails[0]['duplicateValue']);
        $t->same(['pub-id', 'duplicate-id'], $identifierDetails[0]['duplicateIds']);
        $t->same('isbn-id', $identifierDetails[1]['id']);
        $t->same('ISBN', $identifierDetails[1]['scheme']);
        $t->same('15', $identifierDetails[1]['identifierType']);
        $t->same(false, $identifierDetails[1]['selectedByUniqueIdentifier']);
        $t->same(false, $identifierDetails[1]['duplicateValue']);
        $t->same('duplicate-id', $identifierDetails[2]['id']);
        $t->same('22', $metadata['uniqueIdentifier']['matchedEntries'][0]['identifierType']);
        $t->same(true, $metadata['uniqueIdentifier']['matchedEntries'][0]['duplicateValue']);

        $summary = $metadata['identifierSummary'];
        $t->same(true, $summary['present']);
        $t->same(3, $summary['count']);
        $t->same(3, $summary['typedCount']);
        $t->same(2, $summary['schemeCount']);
        $t->same(1, $summary['duplicateValueCount']);
        $t->same(['urn:uuid:wp-epub-source-42'], $summary['duplicateValues']);
        $t->same('urn:uuid:wp-epub-source-42', $summary['selectedValue']);
        $t->same(0, $summary['selectedIndex']);
        $t->same(['UUID', 'ISBN'], $summary['schemes']);
        $t->same(['22', '15'], $summary['identifierTypes']);
        $t->same(['pub-id', 'duplicate-id'], $summary['duplicatesByValue']['urn:uuid:wp-epub-source-42']['ids']);
        $t->same('duplicate-metadata-identifier-value', $summary['diagnostics'][0]['type']);

        $t->same('9781234567890', $metadata['identifiersByType']['15'][0]['text']);
        $t->same('isbn-id', $metadata['identifiersByScheme']['ISBN'][0]['id']);

        $t->same(2, count($dateDetails));
        $t->same('publication-date', $dateDetails[0]['id']);
        $t->same('2026-06-01', $dateDetails[0]['text']);
        $t->same('publication', $dateDetails[0]['event']);
        $t->same('attribute', $dateDetails[0]['eventSource']);
        $t->same('review-date', $dateDetails[1]['id']);
        $t->same('review', $dateDetails[1]['event']);
        $t->same('refinement', $dateDetails[1]['eventSource']);
        $t->same('2', $dateDetails[1]['displaySeq']);
        $t->same('2026-06-01', $metadata['datesByEvent']['publication'][0]['text']);
        $t->same('2026-06-05', $metadata['datesByEvent']['review'][0]['text']);
        $t->same(true, $metadata['dateSummary']['present']);
        $t->same(2, $metadata['dateSummary']['count']);
        $t->same(2, $metadata['dateSummary']['eventCount']);
        $t->same(['publication', 'review'], $metadata['dateSummary']['events']);
        $t->same('2026-06-01', $metadata['date']);

        $t->same($identifierDetails, $result['importReport']['metadata']['identifierDetails']);
        $t->same($summary, $result['document']->attr('metadata')['identifierSummary']);
        $t->same($dateDetails, $result['document']->attr('metadata')['dateDetails']);
    },
    'summarizes OPF source metadata and source-of refinements for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $sourceRecord = '{"kind":"source-record","title":"Print source packet"}';
        $opfWithSourceMetadata = str_replace(
            '<dc:language>en</dc:language>',
            '<dc:source id="print-source" scheme="ISBN" xml:lang="fr" dir="ltr">9781234567890</dc:source>'
            . '<dc:source id="web-source">https://example.test/source-post</dc:source>'
            . '<dc:language>en</dc:language>',
            $opfXml
        );
        $opfWithSourceMetadata = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta refines="#print-source" property="source-of">pagination</meta>'
            . '<meta refines="#print-source" property="identifier-type" scheme="onix:codelist5">15</meta>'
            . '<meta refines="#print-source" property="display-seq">1</meta>'
            . '<meta refines="#web-source" property="source-of">content</meta>',
            $opfWithSourceMetadata
        );
        $opfWithSourceMetadata = str_replace(
            '<meta name="cover" content="cover-image"/>',
            '<meta name="cover" content="cover-image"/>'
            . '<link id="source-record" rel="record" refines="#print-source" href="meta/source.json" media-type="application/ld+json" properties="schema-org"/>',
            $opfWithSourceMetadata
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithSourceMetadata,
            null,
            [
                ['name' => 'OEBPS/meta/source.json', 'data' => $sourceRecord],
            ]
        ));
        $metadata = $result['metadata'];
        $sourceDetails = $metadata['sourceDetails'];

        $t->same(['9781234567890', 'https://example.test/source-post'], $metadata['sources']);
        $t->same('9781234567890', $metadata['source']);
        $t->same(2, count($sourceDetails));

        $printSource = $sourceDetails[0];
        $t->same('source', $printSource['kind']);
        $t->same(0, $printSource['index']);
        $t->same('print-source', $printSource['id']);
        $t->same('9781234567890', $printSource['text']);
        $t->same('ISBN', $printSource['scheme']);
        $t->same('fr', $printSource['language']);
        $t->same('ltr', $printSource['direction']);
        $t->same('pagination', $printSource['sourceOf']);
        $t->same(['pagination'], $printSource['sourceOfValues']);
        $t->same('1', $printSource['displaySeq']);
        $t->same('15', $printSource['identifierType']);
        $t->same('onix:codelist5', $printSource['identifierTypeScheme']);
        $t->same('source-record', $printSource['linkedResources'][0]['id']);
        $t->same('/OEBPS/meta/source.json', $printSource['linkedResources'][0]['target']);
        $t->same(hash('sha256', $sourceRecord), $printSource['linkedResources'][0]['byteSha256']);

        $webSource = $sourceDetails[1];
        $t->same('web-source', $webSource['id']);
        $t->same('https://example.test/source-post', $webSource['text']);
        $t->same('content', $webSource['sourceOf']);
        $t->same(null, $webSource['identifierType']);
        $t->same([], $webSource['linkedResources']);

        $t->same('9781234567890', $metadata['sourcesBySourceOf']['pagination'][0]['text']);
        $t->same('https://example.test/source-post', $metadata['sourcesBySourceOf']['content'][0]['text']);
        $t->same(true, $metadata['sourceSummary']['present']);
        $t->same(2, $metadata['sourceSummary']['count']);
        $t->same(2, $metadata['sourceSummary']['sourceOfCount']);
        $t->same(['pagination', 'content'], $metadata['sourceSummary']['sourceOfValues']);
        $t->same(1, $metadata['sourceSummary']['identifierTypeCount']);
        $t->same(['15'], $metadata['sourceSummary']['identifierTypes']);
        $t->same(1, $metadata['sourceSummary']['linkedResourceCount']);
        $t->same([], $metadata['sourceSummary']['diagnostics']);
        $t->same($sourceDetails, $result['importReport']['metadata']['sourceDetails']);
        $t->same($sourceDetails, $result['document']->attr('metadata')['sourceDetails']);
        $t->same($metadata['sourceSummary'], $result['document']->attr('metadata')['sourceSummary']);
    },
    'summarizes OPF bibliographic Dublin Core metadata for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $rightsRecord = '{"license":"CC-BY-SA-4.0","source":"wordpress-epub"}';
        $relationRecord = '{"kind":"source-post","id":42}';
        $opfWithBibliographicMetadata = str_replace(
            '<dc:language>en</dc:language>',
            '<dc:description id="summary" xml:lang="en" dir="ltr">Importer review summary.</dc:description>'
            . '<dc:publisher id="publisher">Migration Publisher</dc:publisher>'
            . '<dc:rights id="license" xml:lang="en">Creative Commons Attribution-ShareAlike 4.0</dc:rights>'
            . '<dc:type id="resource-type" scheme="dcterms:DCMIType">Text</dc:type>'
            . '<dc:format id="format" scheme="IANA">application/epub+zip</dc:format>'
            . '<dc:relation id="source-post">https://example.test/posts/42</dc:relation>'
            . '<dc:coverage id="coverage">Global migration packet</dc:coverage>'
            . '<dc:language>en</dc:language>',
            $opfXml
        );
        $opfWithBibliographicMetadata = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta refines="#license" property="authority">Creative Commons</meta>'
            . '<meta refines="#license" property="term">CC-BY-SA-4.0</meta>'
            . '<meta refines="#resource-type" property="authority">DCMI Type Vocabulary</meta>'
            . '<meta refines="#resource-type" property="term">Text</meta>'
            . '<meta refines="#source-post" property="display-seq">1</meta>'
            . '<meta refines="#source-post" property="file-as">Post 42</meta>'
            . '<meta refines="#coverage" property="alternate-script" xml:lang="fr">Dossier de migration mondial</meta>',
            $opfWithBibliographicMetadata
        );
        $opfWithBibliographicMetadata = str_replace(
            '<meta name="cover" content="cover-image"/>',
            '<meta name="cover" content="cover-image"/>'
            . '<link id="license-record" rel="license record" refines="#license" href="meta/license.json" media-type="application/ld+json"/>'
            . '<link id="source-post-record" rel="record" refines="#source-post" href="meta/source-post.json" media-type="application/json"/>',
            $opfWithBibliographicMetadata
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithBibliographicMetadata,
            null,
            [
                ['name' => 'OEBPS/meta/license.json', 'data' => $rightsRecord],
                ['name' => 'OEBPS/meta/source-post.json', 'data' => $relationRecord],
            ],
        ));
        $metadata = $result['metadata'];
        $details = $metadata['bibliographicDetails'];
        $byKind = $metadata['bibliographicDetailsByKind'];
        $summary = $metadata['bibliographicSummary'];

        $t->same('Importer review summary.', $metadata['description']);
        $t->same('Migration Publisher', $metadata['publisher']);
        $t->same(7, count($details));
        $t->same(['description', 'publisher', 'rights', 'type', 'format', 'relation', 'coverage'], $summary['kinds']);
        $t->same(7, $summary['count']);
        $t->same(7, $summary['kindCount']);
        $t->same(2, $summary['authorityCount']);
        $t->same(2, $summary['termCount']);
        $t->same(2, $summary['linkedResourceCount']);
        $t->same(1, $summary['kindCounts']['rights']);
        $t->same(1, $summary['kindCounts']['relation']);
        $t->same([], $summary['diagnostics']);

        $description = $byKind['description'][0];
        $t->same('description', $description['kind']);
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
        $t->same('license-record', $rights['linkedResources'][0]['id']);
        $t->same(['license', 'record'], $rights['linkedResources'][0]['rel']);
        $t->same('/OEBPS/meta/license.json', $rights['linkedResources'][0]['target']);
        $t->same(hash('sha256', $rightsRecord), $rights['linkedResources'][0]['byteSha256']);

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
        $t->same('source-post-record', $relation['linkedResources'][0]['id']);
        $t->same(hash('sha256', $relationRecord), $relation['linkedResources'][0]['byteSha256']);

        $coverage = $byKind['coverage'][0];
        $t->same('Global migration packet', $coverage['text']);
        $t->same('Dossier de migration mondial', $coverage['alternateScripts'][0]['text']);
        $t->same('fr', $coverage['alternateScripts'][0]['language']);

        $t->same($details, $result['importReport']['metadata']['bibliographicDetails']);
        $t->same($byKind, $result['document']->attr('metadata')['bibliographicDetailsByKind']);
        $t->same($summary, $result['document']->attr('metadata')['bibliographicSummary']);
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
    'reports OPF spine rendition flow and alignment itemref properties' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithRenditionProperties = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref idref="chapter-1" properties="rendition:flow-paginated rendition:align-x-center"/>',
            $opfXml
        );
        $opfWithRenditionProperties = str_replace(
            '<itemref idref="chapter-2" linear="no"/>',
            '<itemref idref="chapter-2" linear="no" properties="rendition:flow-scrolled-continuous rendition:flow-scrolled-doc"/>',
            $opfWithRenditionProperties
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithRenditionProperties));
        $spineProperties = $result['spineProperties'];

        $first = $result['spine'][0];
        $t->same('paginated', $first['flow']);
        $t->same(['rendition:flow-paginated'], $first['flowProperties']);
        $t->same('paginated', $first['spineItemProperties']['flow']['value']);
        $t->same(false, $first['spineItemProperties']['flow']['conflicting']);
        $t->same('center', $first['alignX']);
        $t->same(['rendition:align-x-center'], $first['alignXProperties']);
        $t->same('center', $first['spineItemProperties']['alignX']['value']);
        $t->same([], $first['spineItemDiagnostics']);

        $second = $result['spine'][1];
        $t->same('scrolled-continuous', $second['flow']);
        $t->same(['rendition:flow-scrolled-continuous', 'rendition:flow-scrolled-doc'], $second['flowProperties']);
        $t->same(true, $second['spineItemProperties']['flow']['conflicting']);
        $t->same(['scrolled-continuous', 'scrolled-doc'], $second['spineItemProperties']['flow']['values']);
        $t->same('conflicting-spine-flow-properties', $second['spineItemDiagnostics'][0]['type']);
        $t->same(['rendition:flow-scrolled-continuous', 'rendition:flow-scrolled-doc'], $second['spineItemDiagnostics'][0]['properties']);

        $t->same(1, count($spineProperties['itemDiagnostics']));
        $t->same('conflicting-spine-flow-properties', $spineProperties['itemDiagnostics'][0]['type']);
        $t->same(1, $spineProperties['itemDiagnostics'][0]['index']);
        $t->same('chapter-2', $spineProperties['itemDiagnostics'][0]['idref']);
        $t->same($spineProperties, $result['importReport']['spine']['properties']);

        $t->same('paginated', $result['document']->children[0]->attr('flow'));
        $t->same('center', $result['document']->children[0]->attr('alignX'));
        $t->same($first['spineItemProperties'], $result['document']->children[0]->attr('spineItemProperties'));
        $t->same('scrolled-continuous', $result['document']->children[1]->attr('flow'));
        $t->same($second['spineItemDiagnostics'], $result['document']->children[1]->attr('spineItemDiagnostics'));
    },
    'reports OPF spine fixed layout itemref override properties' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithFixedLayoutOverrides = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref idref="chapter-1" properties="rendition:layout-pre-paginated rendition:orientation-landscape rendition:spread-none"/>',
            $opfXml
        );
        $opfWithFixedLayoutOverrides = str_replace(
            '<itemref idref="chapter-2" linear="no"/>',
            '<itemref idref="chapter-2" linear="no" properties="rendition:layout-reflowable rendition:layout-pre-paginated rendition:orientation-portrait rendition:orientation-auto rendition:spread-landscape rendition:spread-both"/>',
            $opfWithFixedLayoutOverrides
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithFixedLayoutOverrides));

        $first = $result['spine'][0];
        $t->same('pre-paginated', $first['layout']);
        $t->same(['rendition:layout-pre-paginated'], $first['layoutProperties']);
        $t->same('pre-paginated', $first['spineItemProperties']['layout']['value']);
        $t->same(true, $first['spineItemProperties']['layout']['fixedLayout']);
        $t->same(false, $first['spineItemProperties']['layout']['conflicting']);
        $t->same('landscape', $first['orientation']);
        $t->same(['rendition:orientation-landscape'], $first['orientationProperties']);
        $t->same('landscape', $first['spineItemProperties']['orientation']['value']);
        $t->same(false, $first['spineItemProperties']['orientation']['conflicting']);
        $t->same('none', $first['spread']);
        $t->same(['rendition:spread-none'], $first['spreadProperties']);
        $t->same('none', $first['spineItemProperties']['spread']['value']);
        $t->same(false, $first['spineItemProperties']['spread']['conflicting']);
        $t->same([], $first['spineItemDiagnostics']);

        $second = $result['spine'][1];
        $t->same('reflowable', $second['layout']);
        $t->same(['reflowable', 'pre-paginated'], $second['spineItemProperties']['layout']['values']);
        $t->same(true, $second['spineItemProperties']['layout']['conflicting']);
        $t->same('portrait', $second['orientation']);
        $t->same(['portrait', 'auto'], $second['spineItemProperties']['orientation']['values']);
        $t->same(true, $second['spineItemProperties']['orientation']['conflicting']);
        $t->same('landscape', $second['spread']);
        $t->same(['landscape', 'both'], $second['spineItemProperties']['spread']['values']);
        $t->same(true, $second['spineItemProperties']['spread']['conflicting']);
        $t->same('conflicting-spine-layout-properties', $second['spineItemDiagnostics'][0]['type']);
        $t->same(['rendition:layout-reflowable', 'rendition:layout-pre-paginated'], $second['spineItemDiagnostics'][0]['properties']);
        $t->same('conflicting-spine-orientation-properties', $second['spineItemDiagnostics'][1]['type']);
        $t->same(['portrait', 'auto'], $second['spineItemDiagnostics'][1]['values']);
        $t->same('conflicting-spine-spread-properties', $second['spineItemDiagnostics'][2]['type']);
        $t->same(['rendition:spread-landscape', 'rendition:spread-both'], $second['spineItemDiagnostics'][2]['properties']);

        $spineProperties = $result['spineProperties'];
        $t->same(3, count($spineProperties['itemDiagnostics']));
        $t->same('conflicting-spine-layout-properties', $spineProperties['itemDiagnostics'][0]['type']);
        $t->same(1, $spineProperties['itemDiagnostics'][0]['index']);
        $t->same('chapter-2', $spineProperties['itemDiagnostics'][0]['idref']);
        $t->same($spineProperties, $result['importReport']['spine']['properties']);

        $t->same('pre-paginated', $result['document']->children[0]->attr('layout'));
        $t->same('landscape', $result['document']->children[0]->attr('orientation'));
        $t->same('none', $result['document']->children[0]->attr('spread'));
        $t->same($first['spineItemProperties'], $result['document']->children[0]->attr('spineItemProperties'));
        $t->same('reflowable', $result['document']->children[1]->attr('layout'));
        $t->same('portrait', $result['document']->children[1]->attr('orientation'));
        $t->same('landscape', $result['document']->children[1]->attr('spread'));
        $t->same($second['spineItemDiagnostics'], $result['document']->children[1]->attr('spineItemDiagnostics'));
    },
    'reports effective OPF rendition values from package defaults and itemref overrides' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithEffectiveRendition = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
                . '<meta property="rendition:layout">pre-paginated</meta>'
                . '<meta property="rendition:orientation">portrait</meta>'
                . '<meta property="rendition:spread">auto</meta>'
                . '<meta property="rendition:viewport">width=600,height=900</meta>'
                . '<meta refines="#chapter-2-spine" property="rendition:viewport">width=480,height=640</meta>',
            $opfXml
        );
        $opfWithEffectiveRendition = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref id="chapter-1-spine" idref="chapter-1" properties="rendition:orientation-landscape"/>',
            $opfWithEffectiveRendition
        );
        $opfWithEffectiveRendition = str_replace(
            '<itemref idref="chapter-2" linear="no"/>',
            '<itemref id="chapter-2-spine" idref="chapter-2" linear="no" properties="rendition:layout-reflowable rendition:spread-none"/>',
            $opfWithEffectiveRendition
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithEffectiveRendition));
        $packageRendition = $result['metadata']['renditionLayout'];
        $first = $result['spine'][0]['effectiveRendition'];
        $second = $result['spine'][1]['effectiveRendition'];

        $t->same('pre-paginated', $packageRendition['layout']);
        $t->same('portrait', $packageRendition['orientation']);
        $t->same('auto', $packageRendition['spread']);
        $t->same(600, $packageRendition['viewportWidth']);
        $t->same('pre-paginated', $first['layout']);
        $t->same('package', $first['layoutSource']);
        $t->same(true, $first['fixedLayout']);
        $t->same('landscape', $first['orientation']);
        $t->same('itemref', $first['orientationSource']);
        $t->same('auto', $first['spread']);
        $t->same('package', $first['spreadSource']);
        $t->same(600, $first['viewportWidth']);
        $t->same(900, $first['viewportHeight']);
        $t->same('package', $first['viewportSource']);
        $t->same([], $first['diagnostics']);

        $t->same('reflowable', $second['layout']);
        $t->same('itemref', $second['layoutSource']);
        $t->same(false, $second['fixedLayout']);
        $t->same('portrait', $second['orientation']);
        $t->same('package', $second['orientationSource']);
        $t->same('none', $second['spread']);
        $t->same('itemref', $second['spreadSource']);
        $t->same(480, $second['viewportWidth']);
        $t->same(640, $second['viewportHeight']);
        $t->same('itemref-refinement', $second['viewportSource']);
        $t->same(1, $second['itemrefViewportCount']);
        $t->same('chapter-2-spine', $second['itemrefViewports'][0]['subjectId']);
        $t->same('width=480,height=640', $second['viewportRaw']);
        $t->same($second, $result['importReport']['spine']['items'][1]['effectiveRendition']);

        $t->same($first, $result['document']->children[0]->attr('effectiveRendition'));
        $t->same('package', $result['document']->children[0]->attr('effectiveRendition')['layoutSource']);
        $t->same($second, $result['document']->children[1]->attr('effectiveRendition'));
        $t->same('itemref-refinement', $result['document']->children[1]->attr('effectiveRendition')['viewportSource']);
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
    'reports invalid OPF spine linear values without dropping reading order' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithInvalidLinear = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref idref="chapter-1" linear="maybe"/>',
            $opfXml
        );
        $opfWithInvalidLinear = str_replace(
            '<itemref idref="chapter-2" linear="no"/>',
            '<itemref idref="chapter-2" linear="yes"/>',
            $opfWithInvalidLinear
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithInvalidLinear));
        $spineProperties = $result['spineProperties'];

        $t->same(2, count($result['spine']));
        $t->same(true, $result['spine'][0]['linear']);
        $t->same('maybe', $result['spine'][0]['linearRaw']);
        $t->same(true, $result['spine'][0]['linearSpecified']);
        $t->same(false, $result['spine'][0]['linearValid']);
        $t->same('invalid-spine-linear-value', $result['spine'][0]['spineItemDiagnostics'][0]['type']);
        $t->same('maybe', $result['spine'][0]['spineItemDiagnostics'][0]['value']);
        $t->same('chapter-1', $result['spine'][0]['spineItemProperties']['linear']['idref']);
        $t->same(false, $result['spine'][0]['spineItemProperties']['linear']['valid']);

        $t->same(true, $result['spine'][1]['linear']);
        $t->same('yes', $result['spine'][1]['linearRaw']);
        $t->same(true, $result['spine'][1]['linearSpecified']);
        $t->same(true, $result['spine'][1]['linearValid']);
        $t->same([], $result['spine'][1]['spineItemDiagnostics']);

        $t->same(1, count($spineProperties['itemDiagnostics']));
        $t->same('invalid-spine-linear-value', $spineProperties['itemDiagnostics'][0]['type']);
        $t->same(0, $spineProperties['itemDiagnostics'][0]['index']);
        $t->same('chapter-1', $spineProperties['itemDiagnostics'][0]['idref']);
        $t->same('maybe', $spineProperties['itemDiagnostics'][0]['value']);
        $t->same($spineProperties, $result['importReport']['spine']['properties']);
        $t->same($spineProperties, $result['document']->attr('spineProperties'));

        $t->same(true, $result['document']->children[0]->attr('linear'));
        $t->same('maybe', $result['document']->children[0]->attr('linearRaw'));
        $t->same(false, $result['document']->children[0]->attr('linearValid'));
        $t->same($result['spine'][0]['spineItemDiagnostics'], $result['document']->children[0]->attr('spineItemDiagnostics'));
    },
    'reports all non-linear OPF spine itemrefs as empty primary reading order' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithNonLinearSpine = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref idref="chapter-1" linear="no"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithNonLinearSpine));
        $spineProperties = $result['spineProperties'];

        $t->same(2, $spineProperties['itemCount']);
        $t->same(0, $spineProperties['linearItemCount']);
        $t->same(2, $spineProperties['nonLinearItemCount']);
        $t->same(false, $spineProperties['hasLinearItems']);
        $t->same(true, $spineProperties['primaryReadingOrderEmpty']);
        $t->same(1, count($spineProperties['diagnostics']));
        $t->same('spine-has-no-linear-items', $spineProperties['diagnostics'][0]['type']);
        $t->same(2, $spineProperties['diagnostics'][0]['itemCount']);
        $t->same(['chapter-1', 'chapter-2'], $spineProperties['diagnostics'][0]['idrefs']);
        $t->same($spineProperties, $result['importReport']['spine']['properties']);
        $t->same($spineProperties, $result['document']->attr('spineProperties'));

        $t->same(false, $result['spine'][0]['linear']);
        $t->same('no', $result['spine'][0]['linearRaw']);
        $t->same(false, $result['spine'][1]['linear']);
        $t->same('no', $result['spine'][1]['linearRaw']);
        $t->same(2, count($result['document']->children));
        $t->same(false, $result['document']->children[0]->attr('linear'));
        $t->same(false, $result['document']->children[1]->attr('linear'));
        $t->contains('Chapter XHTML stays available', $result['document']->children[0]->attr('html'));
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
    'summarizes OPF fixed layout viewport metadata for package review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithFixedLayout = str_replace(
            '<meta name="cover" content="cover-image"/>',
            '<meta name="cover" content="cover-image"/>'
            . '<meta property="rendition:layout">pre-paginated</meta>'
            . '<meta property="rendition:orientation">landscape</meta>'
            . '<meta property="rendition:spread">both</meta>'
            . '<meta property="rendition:viewport">width=768, height=1024</meta>'
            . '<meta property="rendition:viewport" id="invalid-viewport">width=cover,height=600,scale=1</meta>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithFixedLayout));
        $renditionLayout = $result['metadata']['renditionLayout'];

        $t->same(true, $renditionLayout['present']);
        $t->same(true, $renditionLayout['fixedLayout']);
        $t->same('pre-paginated', $renditionLayout['layout']);
        $t->same('landscape', $renditionLayout['orientation']);
        $t->same('both', $renditionLayout['spread']);
        $t->same('width=768, height=1024', $renditionLayout['viewportRaw']);
        $t->same(768, $renditionLayout['viewportWidth']);
        $t->same(1024, $renditionLayout['viewportHeight']);
        $t->same(2, $renditionLayout['viewportCount']);
        $t->same(1, $renditionLayout['invalidViewportCount']);
        $t->same(true, $renditionLayout['viewport']['valid']);
        $t->same(['width' => '768', 'height' => '1024'], $renditionLayout['viewport']['parameters']);
        $t->same('invalid-rendition-viewport-width', $renditionLayout['viewports'][1]['diagnostics'][0]['type']);
        $t->same('cover', $renditionLayout['viewports'][1]['diagnostics'][0]['value']);
        $t->same('unknown-rendition-viewport-parameter', $renditionLayout['viewports'][1]['diagnostics'][1]['type']);
        $t->same($renditionLayout['viewports'][1]['diagnostics'], $renditionLayout['diagnostics']);
        $t->same($renditionLayout, $result['package']['renditionLayout']);
        $t->same($renditionLayout, $result['importReport']['metadata']['renditionLayout']);
        $t->same($renditionLayout, $result['document']->attr('metadata')['renditionLayout']);
    },
    'preserves EPUB XHTML viewport metadata for fixed layout review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $fixedLayoutXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="en" lang="en-US" dir="ltr">
  <head>
    <title>Fixed page metadata</title>
    <meta name="viewport" content="width=600, height=900"/>
    <meta id="bad-viewport" name="viewport" content="width=cover,height=0,scale=1"/>
  </head>
  <body id="fixed-body" class="fixed review" xml:lang="ar" lang="ar-Latn" dir="rtl" epub:type="bodymatter chapter"><h1>Fixed page metadata</h1><p>Viewport dimensions remain available for package review.</p></body>
</html>
XML;
        $opfWithFixedContent = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="fixed-content" href="text/fixed-layout.xhtml" media-type="application/xhtml+xml" properties="rendition:layout-pre-paginated"/>',
            $opfXml
        );
        $opfWithFixedContent = str_replace(
            '</spine>',
            '<itemref idref="fixed-content"/></spine>',
            $opfWithFixedContent
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithFixedContent,
            null,
            [
                ['name' => 'OEBPS/text/fixed-layout.xhtml', 'data' => $fixedLayoutXhtml],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/fixed-layout.xhtml'];
        $metadata = $asset['metadata'];
        $block = $result['document']->children[2];

        $t->same(1, $report['viewportAssetCount']);
        $t->same(2, $report['viewportCount']);
        $t->same(1, $report['validViewportCount']);
        $t->same(1, $report['invalidViewportCount']);
        $t->same(2, count($report['viewportItems']));
        $t->same('/OEBPS/text/fixed-layout.xhtml', $report['viewportItems'][0]['sourcePart']);
        $t->same(3, count($report['viewportDiagnostics']));
        $t->same('/OEBPS/text/fixed-layout.xhtml', $report['viewportDiagnostics'][0]['part']);

        $t->same(true, $metadata['present']);
        $t->same(true, $metadata['headPresent']);
        $t->same('Fixed page metadata', $metadata['title']);
        $t->same('en', $metadata['htmlXmlLang'] ?? null);
        $t->same('en-US', $metadata['htmlLang'] ?? null);
        $t->same('en', $metadata['htmlLanguage'] ?? null);
        $t->same('ltr', $metadata['htmlDirection'] ?? null);
        $t->same(true, $metadata['bodyPresent'] ?? null);
        $t->same('fixed-body', $metadata['bodyId'] ?? null);
        $t->same('fixed review', $metadata['bodyClass'] ?? null);
        $t->same(['fixed', 'review'], $metadata['bodyClasses'] ?? null);
        $t->same('ar', $metadata['bodyXmlLang'] ?? null);
        $t->same('ar-Latn', $metadata['bodyLang'] ?? null);
        $t->same('ar', $metadata['bodyLanguage'] ?? null);
        $t->same('rtl', $metadata['bodyDirection'] ?? null);
        $t->same(['bodymatter', 'chapter'], $metadata['bodyEpubTypes'] ?? null);
        $t->same('ar', $metadata['language'] ?? null);
        $t->same('rtl', $metadata['direction'] ?? null);
        $t->same(2, $metadata['metaCount']);
        $t->same(2, $metadata['viewportCount']);
        $t->same(1, $metadata['validViewportCount']);
        $t->same(1, $metadata['invalidViewportCount']);

        $viewport = $metadata['viewport'];
        $t->same(true, $viewport['valid']);
        $t->same('width=600, height=900', $viewport['raw']);
        $t->same(['width' => '600', 'height' => '900'], $viewport['parameters']);
        $t->same(600, $viewport['width']);
        $t->same(900, $viewport['height']);
        $t->same([], $viewport['diagnostics']);

        $invalid = $metadata['viewports'][1];
        $t->same('bad-viewport', $invalid['id']);
        $t->same(false, $invalid['valid']);
        $t->same(null, $invalid['width']);
        $t->same(null, $invalid['height']);
        $t->same(['width' => 'cover', 'height' => '0', 'scale' => '1'], $invalid['parameters']);
        $t->same([
            'invalid-xhtml-viewport-width',
            'invalid-xhtml-viewport-height',
            'unknown-xhtml-viewport-parameter',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $invalid['diagnostics']));
        $t->same($invalid['diagnostics'], $metadata['diagnostics']);
        $t->same($metadata['diagnostics'], $asset['metadataDiagnostics']);
        $t->same($metadata['diagnostics'], $asset['diagnostics']);

        $t->same($metadata, $block->attr('contentMetadata'));
        $t->same('ar', $block->attr('contentLanguage'));
        $t->same('rtl', $block->attr('contentDirection'));
        $t->same(['bodymatter', 'chapter'], $block->attr('contentBodyEpubTypes'));
        $t->same($viewport, $block->attr('contentViewport'));
        $t->same($metadata['viewports'], $block->attr('contentViewports'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
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
    'preserves NCX navPoint provenance for legacy package review' => static function (TestRunner $t) use ($buildEpubPackage, $chapter1Xhtml, $chapter2Xhtml): void {
        $ncxWithNavPointMetadata = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1" xml:lang="en">
  <navMap>
    <navPoint id="chapter-source" class="frontmatter source-point" playOrder="1" xml:lang="fr" dir="rtl">
      <navLabel id="chapter-label" class="label source-label" xml:lang="en" dir="ltr"><text>Imported packet</text></navLabel>
      <content id="chapter-content" src="text/chapter1.xhtml#intro" data-review="source"/>
      <navPoint id="media-audit" class="appendix-point" playOrder="2">
        <navLabel><text>Media audit</text></navLabel>
        <content src="text/chapter2.xhtml#media"/>
      </navPoint>
    </navPoint>
  </navMap>
</ncx>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideNcxXml: $ncxWithNavPointMetadata,
        ));

        $ncx = $result['ncx'];
        $first = $ncx['items'][0];
        $t->same('chapter-source', $first['id']);
        $t->same('frontmatter source-point', $first['class']);
        $t->same(['frontmatter', 'source-point'], $first['classes']);
        $t->same('fr', $first['language']);
        $t->same('rtl', $first['direction']);
        $t->same('Imported packet', $first['title']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $first['target']);
        $t->same('/OEBPS/text/chapter1.xhtml', $first['part']);
        $t->same(strlen($chapter1Xhtml), $first['byteLength']);
        $t->same(hash('crc32b', $chapter1Xhtml), $first['crc32']);
        $t->same('chapter-source', $first['attributes']['id']);
        $t->same('frontmatter source-point', $first['attributes']['class']);
        $t->same('chapter-label', $first['labelAttributes']['id']);
        $t->same('label source-label', $first['labelAttributes']['class']);
        $t->same('chapter-content', $first['contentAttributes']['id']);
        $t->same('source', $first['contentAttributes']['data-review']);
        $t->same([], $first['diagnostics']);

        $child = $first['children'][0];
        $t->same('media-audit', $child['id']);
        $t->same('appendix-point', $child['class']);
        $t->same(['appendix-point'], $child['classes']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $child['target']);
        $t->same(hash('crc32b', $chapter2Xhtml), $child['crc32']);

        $navigationNcx = null;
        foreach ($result['navigation']['items'] as $navigationItem) {
            if (($navigationItem['source'] ?? null) === 'ncx') {
                $navigationNcx = $navigationItem;
                break;
            }
        }
        $t->same(true, is_array($navigationNcx));
        $t->same('chapter-source', $navigationNcx['id']);
        $t->same('frontmatter source-point', $navigationNcx['class']);
        $t->same(['frontmatter', 'source-point'], $navigationNcx['classes']);
        $t->same('fr', $navigationNcx['language']);
        $t->same('rtl', $navigationNcx['direction']);
        $t->same('chapter-label', $navigationNcx['labelAttributes']['id']);
        $t->same($ncx, $result['importReport']['ncx']);
    },
    'reports NCX head title and author metadata for package review' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $ncxWithMetadata = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1" xml:lang="en">
  <head>
    <meta name="dtb:uid" content="urn:uuid:wp-epub-source-42"/>
    <meta name="dtb:depth" content="2"/>
    <meta name="dtb:totalPageCount" content="24"/>
    <meta name="dtb:maxPageNumber" content="xii"/>
    <meta name="review:source" content="wordpress-import"/>
    <meta content="missing-name"/>
    <meta name="missing-content"/>
  </head>
  <docTitle id="source-title" xml:lang="en">
    <text>WordPress Import EPUB</text>
  </docTitle>
  <docAuthor id="primary-author">
    <text>Migration Desk</text>
  </docAuthor>
  <docAuthor xml:lang="fr">
    <text>Bureau de revue</text>
  </docAuthor>
  <navMap>
    <navPoint id="navpoint-1" playOrder="1">
      <navLabel><text>Imported packet</text></navLabel>
      <content src="text/chapter1.xhtml#intro"/>
    </navPoint>
  </navMap>
</ncx>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideNcxXml: $ncxWithMetadata,
        ));

        $ncx = $result['ncx'];
        $t->same('/OEBPS/toc.ncx', $ncx['part']);
        $t->same('2005-1', $ncx['version']);
        $t->same('en', $ncx['language']);
        $t->same('WordPress Import EPUB', $ncx['docTitle']);
        $t->same(1, count($ncx['docTitleEntries']));
        $t->same('source-title', $ncx['docTitleEntries'][0]['id']);
        $t->same('en', $ncx['docTitleEntries'][0]['language']);
        $t->same(['Migration Desk', 'Bureau de revue'], $ncx['docAuthors']);
        $t->same(2, count($ncx['docAuthorDetails']));
        $t->same('primary-author', $ncx['docAuthorDetails'][0]['id']);
        $t->same('fr', $ncx['docAuthorDetails'][1]['language']);

        $head = $ncx['head'];
        $t->same(true, $head['present']);
        $t->same(7, $head['metaCount']);
        $t->same('urn:uuid:wp-epub-source-42', $head['uid']);
        $t->same('2', $head['depth']);
        $t->same('24', $head['totalPageCount']);
        $t->same('xii', $head['maxPageNumber']);
        $t->same('wordpress-import', $head['byName']['review:source'][0]['content']);
        $t->same('missing-ncx-head-meta-name', $head['diagnostics'][0]['type']);
        $t->same(5, $head['diagnostics'][0]['index']);
        $t->same('missing-ncx-head-meta-content', $head['diagnostics'][1]['type']);
        $t->same(6, $head['diagnostics'][1]['index']);

        $t->same($ncx, $result['importReport']['ncx']);
    },
    'reports NCX navList targets for legacy package review' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $ncxWithNavList = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1" xml:lang="en">
  <navMap>
    <navPoint id="navpoint-1" playOrder="1">
      <navLabel><text>Imported packet</text></navLabel>
      <content src="text/chapter1.xhtml#intro"/>
    </navPoint>
  </navMap>
  <navList id="review-nav-list" class="review-list" xml:lang="en">
    <navLabel id="review-list-label" class="review-list-label"><text id="review-list-title" class="review-list-title">Reviewer reference list</text></navLabel>
    <navTarget id="glossary-target" class="glossary entry" playOrder="10">
      <navLabel id="glossary-label" class="glossary-label"><text id="glossary-text" class="glossary-text">Media glossary</text></navLabel>
      <content src="text/chapter2.xhtml#media"/>
    </navTarget>
    <navTarget id="remote-target" playOrder="11">
      <navLabel><text>Remote source record</text></navLabel>
      <content src="https://cdn.example.test/epub/review-record.xhtml"/>
    </navTarget>
    <navTarget id="missing-target" playOrder="12">
      <navLabel><text>Missing source note</text></navLabel>
      <content src="text/missing.xhtml#note"/>
    </navTarget>
  </navList>
  <navList id="empty-nav-list">
    <navLabel><text>Empty reviewer list</text></navLabel>
  </navList>
</ncx>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideNcxXml: $ncxWithNavList,
        ));

        $ncx = $result['ncx'];
        $t->same(2, $ncx['navListCount']);
        $t->same(2, count($ncx['navLists']));
        $t->same(2, count($ncx['navListDiagnostics']));
        $t->same($ncx, $result['importReport']['ncx']);

        $list = $ncx['navLists'][0];
        $t->same('review-nav-list', $list['id']);
        $t->same('review-list', $list['class']);
        $t->same(['review-list'], $list['classes']);
        $t->same('en', $list['language']);
        $t->same('Reviewer reference list', $list['title']);
        $t->same('review-list-label', $list['labelAttributes']['id']);
        $t->same('review-list-label', $list['labelAttributes']['class']);
        $t->same('review-list-title', $list['labelTextAttributes']['id']);
        $t->same('review-list-title', $list['labelTextAttributes']['class']);
        $t->same(3, $list['itemCount']);
        $t->same(2, count($list['diagnostics']));

        $local = $list['items'][0];
        $t->same('glossary-target', $local['id']);
        $t->same('glossary entry', $local['class']);
        $t->same(['glossary', 'entry'], $local['classes']);
        $t->same('10', $local['playOrder']);
        $t->same('Media glossary', $local['title']);
        $t->same('glossary-label', $local['labelAttributes']['id']);
        $t->same('glossary-label', $local['labelAttributes']['class']);
        $t->same('glossary-text', $local['labelTextAttributes']['id']);
        $t->same('glossary-text', $local['labelTextAttributes']['class']);
        $t->same('text/chapter2.xhtml#media', $local['href']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $local['target']);
        $t->same('/OEBPS/text/chapter2.xhtml', $local['part']);
        $t->same('media', $local['fragment']);
        $t->same('id', $local['fragmentKind']);
        $t->same(false, $local['external']);
        $t->same(true, $local['exists']);
        $t->same([], $local['diagnostics']);

        $remote = $list['items'][1];
        $t->same('remote-target', $remote['id']);
        $t->same('Remote source record', $remote['title']);
        $t->same(true, $remote['external']);
        $t->same(null, $remote['part']);
        $t->same('external-ncx-nav-list-reference', $remote['diagnostics'][0]['type']);

        $missing = $list['items'][2];
        $t->same('missing-target', $missing['id']);
        $t->same('/OEBPS/text/missing.xhtml#note', $missing['target']);
        $t->same('/OEBPS/text/missing.xhtml', $missing['part']);
        $t->same('note', $missing['fragment']);
        $t->same(false, $missing['exists']);
        $t->same('missing-ncx-nav-list-reference', $missing['diagnostics'][0]['type']);

        $t->same('Empty reviewer list', $ncx['navLists'][1]['title']);
        $t->same(0, $ncx['navLists'][1]['itemCount']);
        $navigation = $result['navigation'];
        $t->same(1, $navigation['ncxCount']);
        $t->same(4, $navigation['mappedSpineTargetCount']);
        $t->same(2, $navigation['ncxNavListCount']);
        $t->same(3, $navigation['ncxNavListTargetCount']);
        $t->same(3, $navigation['supplementalTargetCount']);
        $t->same(1, $navigation['supplementalMappedSpineTargetCount']);
        $t->same(1, $navigation['supplementalExternalTargetCount']);
        $t->same(1, $navigation['supplementalMissingTargetCount']);
        $t->same(0, $navigation['supplementalOutsideSpineTargetCount']);

        $supplemental = $navigation['supplementalItems'];
        $t->same('ncx-nav-list', $supplemental[0]['source']);
        $t->same('review-nav-list', $supplemental[0]['listId']);
        $t->same('Reviewer reference list', $supplemental[0]['listTitle']);
        $t->same('glossary-target', $supplemental[0]['id']);
        $t->same('glossary-label', $supplemental[0]['labelAttributes']['id']);
        $t->same('glossary-text', $supplemental[0]['labelTextAttributes']['id']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $supplemental[0]['target']);
        $t->same(1, $supplemental[0]['spineIndex']);
        $t->same('chapter-2', $supplemental[0]['spineIdref']);
        $t->same(true, $supplemental[0]['supplemental']);
        $t->same('remote-target', $supplemental[1]['id']);
        $t->same(true, $supplemental[1]['external']);
        $t->same('missing-target', $supplemental[2]['id']);
        $t->same(false, $supplemental[2]['exists']);
        $t->same('external-navigation-target', $navigation['supplementalDiagnostics'][0]['type']);
        $t->same('ncx-nav-list', $navigation['supplementalDiagnostics'][0]['source']);
        $t->same('missing-navigation-target', $navigation['supplementalDiagnostics'][1]['type']);
        $t->same($navigation, $result['importReport']['navigation']);
        $t->same($navigation, $result['document']->attr('navigation'));
    },
    'reports NCX navList role metadata for supplemental navigation review' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $ncxWithTypedNavLists = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1" xml:lang="en">
  <navMap>
    <navPoint id="navpoint-1" playOrder="1">
      <navLabel><text>Imported packet</text></navLabel>
      <content src="text/chapter1.xhtml#intro"/>
    </navPoint>
  </navMap>
  <navList id="figure-list" class="loi list-of-illustrations reviewer-list" xml:lang="en" dir="ltr">
    <navLabel><text>List of figures</text></navLabel>
    <navTarget id="figure-cover" class="figure-entry" playOrder="10">
      <navLabel><text>Cover figure</text></navLabel>
      <content src="text/chapter1.xhtml#intro"/>
    </navTarget>
  </navList>
  <navList id="ambiguous-list" class="lot bibliography">
    <navLabel><text>Tables and bibliography</text></navLabel>
    <navTarget id="table-one" playOrder="11">
      <navLabel><text>Table one</text></navLabel>
      <content src="text/chapter2.xhtml#media"/>
    </navTarget>
  </navList>
  <navList id="custom-review-list" class="review-links">
    <navLabel><text>Custom reviewer links</text></navLabel>
    <navTarget id="custom-link" playOrder="12">
      <navLabel><text>Reviewer link</text></navLabel>
      <content src="text/chapter2.xhtml#media"/>
    </navTarget>
  </navList>
</ncx>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideNcxXml: $ncxWithTypedNavLists,
        ));

        $ncx = $result['ncx'];
        $t->same(3, $ncx['navListCount']);
        $t->same('list-of-illustrations', $ncx['navLists'][0]['type'] ?? null);
        $t->same('list-of-illustrations', $ncx['navLists'][0]['role'] ?? null);
        $t->same(['list-of-illustrations'], $ncx['navLists'][0]['types'] ?? null);
        $t->same(['list-of-illustrations'], $ncx['navLists'][0]['roles'] ?? null);
        $t->same(['loi', 'list-of-illustrations'], $ncx['navLists'][0]['roleAliases'] ?? null);
        $t->same([], $ncx['navLists'][0]['roleDiagnostics'] ?? null);
        $t->same('application/xhtml+xml', $ncx['navLists'][0]['items'][0]['mediaType'] ?? null);
        $t->same('chapter-1', $ncx['navLists'][0]['items'][0]['manifestId'] ?? null);
        $t->same(false, $ncx['navLists'][0]['items'][0]['encrypted'] ?? null);
        $t->same(true, $ncx['navLists'][0]['items'][0]['canExposeBytes'] ?? null);

        $ambiguous = $ncx['navLists'][1];
        $t->same('list-of-tables', $ambiguous['type'] ?? null);
        $t->same(['list-of-tables', 'bibliography'], $ambiguous['types'] ?? null);
        $t->same(['lot', 'bibliography'], $ambiguous['roleAliases'] ?? null);
        $t->same('conflicting-ncx-nav-list-roles', $ambiguous['roleDiagnostics'][0]['type'] ?? null);
        $t->same(['list-of-tables', 'bibliography'], $ambiguous['roleDiagnostics'][0]['roles'] ?? null);

        $custom = $ncx['navLists'][2];
        $t->same(null, $custom['type'] ?? null);
        $t->same([], $custom['types'] ?? null);
        $t->same(['review-links'], $custom['unmappedRoleClasses'] ?? null);
        $t->same('missing-ncx-nav-list-role', $custom['roleDiagnostics'][0]['type'] ?? null);

        $roleReport = $ncx['navListRoleReport'];
        $t->same(true, $roleReport['present']);
        $t->same(3, $roleReport['listCount']);
        $t->same(2, $roleReport['typedListCount']);
        $t->same(1, $roleReport['untypedListCount']);
        $t->same(1, $roleReport['conflictingListCount']);
        $t->same(['list-of-illustrations', 'list-of-tables', 'bibliography'], $roleReport['roles']);
        $t->same('figure-list', $roleReport['byRole']['list-of-illustrations'][0]['id']);
        $t->same('ambiguous-list', $roleReport['byRole']['list-of-tables'][0]['id']);
        $t->same('ambiguous-list', $roleReport['byRole']['bibliography'][0]['id']);
        $t->same(2, $roleReport['diagnosticCount']);
        $t->same('conflicting-ncx-nav-list-roles', $roleReport['diagnostics'][0]['type']);
        $t->same('missing-ncx-nav-list-role', $roleReport['diagnostics'][1]['type']);

        $navigation = $result['navigation'];
        $t->same($roleReport, $navigation['ncxNavListRoleReport']);
        $t->same('list-of-illustrations', $navigation['supplementalItems'][0]['listType'] ?? null);
        $t->same(['list-of-illustrations'], $navigation['supplementalItems'][0]['listTypes'] ?? null);
        $t->same(['loi', 'list-of-illustrations'], $navigation['supplementalItems'][0]['listRoleAliases'] ?? null);
        $t->same('chapter-1', $navigation['supplementalItems'][0]['manifestId'] ?? null);
        $t->same('application/xhtml+xml', $navigation['supplementalItems'][0]['mediaType'] ?? null);
        $t->same('list-of-tables', $navigation['supplementalItems'][1]['listType'] ?? null);
        $t->same(['list-of-tables', 'bibliography'], $navigation['supplementalItems'][1]['listTypes'] ?? null);
        $t->same('missing-ncx-nav-list-role', $navigation['supplementalItems'][2]['listRoleDiagnostics'][0]['type'] ?? null);
        $t->same($roleReport, $result['importReport']['ncx']['navListRoleReport']);
        $t->same($navigation, $result['document']->attr('navigation'));
    },
    'preserves EPUB3 nav section and item provenance for review handoff' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $navWithProvenance = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="source-toc" class="review-toc primary" epub:type="toc" xml:lang="en" dir="ltr" hidden="hidden">
      <h1>Source navigation</h1>
      <ol>
        <li id="toc-entry-1" class="chapter current">
          <a id="toc-link-1" class="nav-link cfi" xml:lang="fr" dir="rtl" href="text/chapter1.xhtml#intro">Paquet importé</a>
        </li>
        <li id="toc-entry-2" class="appendix" hidden="hidden">
          <span id="toc-label-2" class="group-label">Appendices</span>
          <ol>
            <li id="toc-entry-2-1" aria-hidden="true">
              <a id="toc-link-2-1" class="child-link" href="text/chapter2.xhtml#media">Media audit</a>
            </li>
          </ol>
        </li>
      </ol>
    </nav>
    <nav id="page-nav" class="print-pages" epub:type="page-list" xml:lang="en">
      <ol>
        <li id="page-entry-1"><a id="page-link-1" class="page-ref" epub:type="pagebreak" href="text/chapter1.xhtml#page-1">1</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideNavXhtml: $navWithProvenance,
        ));

        $nav = $result['nav'];
        $t->same(2, $nav['sectionCount']);
        $t->same(1, $nav['hiddenSectionCount']);
        $t->same(2, $nav['hiddenItemCount']);
        $t->same('source-toc', $nav['sections'][0]['id']);
        $t->same('review-toc primary', $nav['sections'][0]['class']);
        $t->same(['review-toc', 'primary'], $nav['sections'][0]['classes']);
        $t->same('en', $nav['sections'][0]['language']);
        $t->same('ltr', $nav['sections'][0]['direction']);
        $t->same(true, $nav['sections'][0]['hidden']);
        $t->same('hidden', $nav['sections'][0]['attributes']['hidden']);
        $t->same('source-toc', $nav['sectionsByType']['toc'][0]['id']);

        $first = $nav['items'][0];
        $t->same('toc-link-1', $first['id']);
        $t->same('toc-entry-1', $first['itemId']);
        $t->same('toc-link-1', $first['labelId']);
        $t->same('a', $first['labelElement']);
        $t->same('chapter current nav-link cfi', $first['class']);
        $t->same(['chapter', 'current', 'nav-link', 'cfi'], $first['classes']);
        $t->same(['chapter', 'current'], $first['itemClasses']);
        $t->same(['nav-link', 'cfi'], $first['labelClasses']);
        $t->same('fr', $first['language']);
        $t->same('rtl', $first['direction']);
        $t->same(false, $first['hidden']);
        $t->same('text/chapter1.xhtml#intro', $first['labelAttributes']['href']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $first['target']);

        $spanItem = $nav['items'][1];
        $t->same('toc-label-2', $spanItem['id']);
        $t->same('toc-entry-2', $spanItem['itemId']);
        $t->same('span', $spanItem['labelElement']);
        $t->same(true, $spanItem['hidden']);
        $t->same(null, $spanItem['target']);
        $t->same('toc-link-2-1', $spanItem['children'][0]['id']);
        $t->same(true, $spanItem['children'][0]['hidden']);
        $t->same('child-link', $spanItem['children'][0]['class']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $spanItem['children'][0]['target']);

        $navigation = $result['navigation'];
        $t->same('toc-link-1', $navigation['items'][0]['id']);
        $t->same('toc-entry-1', $navigation['items'][0]['itemId']);
        $t->same(['chapter', 'current', 'nav-link', 'cfi'], $navigation['items'][0]['classes']);
        $t->same('fr', $navigation['items'][0]['language']);
        $t->same('rtl', $navigation['items'][0]['direction']);
        $t->same('toc-label-2', $navigation['items'][1]['id']);
        $t->same(true, $navigation['items'][1]['hidden']);
        $t->same('missing-navigation-target', $navigation['items'][1]['diagnostics'][0]['type']);
        $t->same(true, $navigation['items'][2]['hidden']);
        $t->same('toc-link-2-1', $navigation['items'][2]['labelId']);

        $pageBreak = $result['pageBreaks']['items'][0];
        $t->same('page-link-1', $pageBreak['id']);
        $t->same('page-entry-1', $pageBreak['itemId']);
        $t->same('page-ref', $pageBreak['class']);
        $t->same(['page-ref'], $pageBreak['classes']);
        $t->same('a', $pageBreak['labelElement']);
        $t->same(false, $pageBreak['hidden']);
        $t->same('pagebreak', $pageBreak['type']);
        $t->same('/OEBPS/text/chapter1.xhtml#page-1', $pageBreak['target']);
        $t->same($nav, $result['importReport']['nav']);
        $t->same($navigation, $result['document']->attr('navigation'));
    },
    'preserves EPUB nav item semantic type sources for package review' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $navWithItemTypes = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="landmark-source" epub:type="landmarks">
      <ol>
        <li id="body-li" epub:type="bodymatter frontmatter">
          <a id="body-link" href="text/chapter1.xhtml#intro">Body from list item</a>
        </li>
        <li id="page-li" epub:type="bodymatter">
          <a id="page-link" epub:type="pagebreak" href="text/chapter1.xhtml#page-1">Page one</a>
        </li>
        <li id="figure-li" epub:type="loi">
          <span id="figure-label" epub:type="list-of-illustrations">Figure list heading</span>
        </li>
      </ol>
    </nav>
    <nav id="print-pages" epub:type="page-list">
      <ol>
        <li id="print-page-li" epub:type="pagebreak">
          <a id="print-page-link" href="text/chapter1.xhtml#page-1">1</a>
        </li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideNavXhtml: $navWithItemTypes,
        ));

        $nav = $result['nav'];
        $landmarks = $nav['landmarks'];
        $t->same(3, count($landmarks));

        $body = $landmarks[0];
        $t->same('body-link', $body['id']);
        $t->same('body-li', $body['itemId']);
        $t->same('bodymatter', $body['type']);
        $t->same(['bodymatter', 'frontmatter'], $body['types']);
        $t->same(['bodymatter', 'frontmatter'], $body['itemTypes']);
        $t->same([], $body['labelTypes']);
        $t->same('item', $body['typeSource']);
        $t->same([
            ['type' => 'bodymatter', 'source' => 'item', 'element' => 'li'],
            ['type' => 'frontmatter', 'source' => 'item', 'element' => 'li'],
        ], $body['typeSources']);

        $page = $landmarks[1];
        $t->same('page-link', $page['id']);
        $t->same('page-li', $page['itemId']);
        $t->same('pagebreak', $page['type']);
        $t->same(['pagebreak', 'bodymatter'], $page['types']);
        $t->same(['bodymatter'], $page['itemTypes']);
        $t->same(['pagebreak'], $page['labelTypes']);
        $t->same('label', $page['typeSource']);
        $t->same([
            ['type' => 'pagebreak', 'source' => 'label', 'element' => 'a'],
            ['type' => 'bodymatter', 'source' => 'item', 'element' => 'li'],
        ], $page['typeSources']);

        $span = $landmarks[2];
        $t->same('figure-label', $span['id']);
        $t->same('figure-li', $span['itemId']);
        $t->same('list-of-illustrations', $span['type']);
        $t->same(['list-of-illustrations', 'loi'], $span['types']);
        $t->same(['loi'], $span['itemTypes']);
        $t->same(['list-of-illustrations'], $span['labelTypes']);
        $t->same('label', $span['typeSource']);
        $t->same(null, $span['target']);

        $policy = $nav['primaryNavigationTargetPolicy'];
        $policyBody = $policy['itemsBySectionType']['landmarks'][0];
        $policyPage = $policy['itemsBySectionType']['landmarks'][1];
        $policySpan = $policy['itemsBySectionType']['landmarks'][2];
        $t->same(0, $policy['landmarkMissingTypeCount']);
        $t->same(false, in_array('missing-landmark-nav-type', array_column($policy['diagnostics'], 'type'), true));
        $t->same(['bodymatter', 'frontmatter'], $policyBody['itemTypes']);
        $t->same([], $policyBody['labelTypes']);
        $t->same('item', $policyBody['typeSource']);
        $t->same(['pagebreak'], $policyPage['labelTypes']);
        $t->same(['bodymatter'], $policyPage['itemTypes']);
        $t->same('label', $policyPage['typeSource']);
        $t->same(['loi'], $policySpan['itemTypes']);
        $t->same(['list-of-illustrations'], $policySpan['labelTypes']);

        $navigation = $result['navigation'];
        $t->same(['bodymatter', 'frontmatter'], $navigation['items'][0]['types']);
        $t->same(['bodymatter', 'frontmatter'], $navigation['items'][0]['itemTypes']);
        $t->same('item', $navigation['items'][0]['typeSource']);
        $t->same(['pagebreak', 'bodymatter'], $navigation['items'][1]['types']);
        $t->same(['pagebreak'], $navigation['items'][1]['labelTypes']);
        $t->same('label', $navigation['items'][1]['typeSource']);
        $t->same('list-of-illustrations', $navigation['items'][2]['type']);
        $t->same(['list-of-illustrations'], $navigation['items'][2]['labelTypes']);

        $pageBreak = $result['pageBreaks']['items'][0];
        $t->same('print-page-link', $pageBreak['id']);
        $t->same('print-page-li', $pageBreak['itemId']);
        $t->same('pagebreak', $pageBreak['type']);
        $t->same(['pagebreak'], $pageBreak['itemTypes']);
        $t->same([], $pageBreak['labelTypes']);
        $t->same('item', $pageBreak['typeSource']);
        $t->same($nav, $result['importReport']['nav']);
        $t->same($navigation, $result['document']->attr('navigation'));
    },
    'reports EPUB nav document structure diagnostics for package review' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $navWithDocumentDiagnostics = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="hidden-toc" epub:type="toc" hidden="hidden">
      <h1>Hidden source contents</h1>
    </nav>
    <nav id="visible-toc" epub:type="toc">
      <h2>Visible contents</h2>
      <ol>
        <li><a href="text/chapter1.xhtml#intro">Imported packet</a></li>
      </ol>
    </nav>
    <nav id="untyped-review">
      <ol>
        <li><a href="text/chapter2.xhtml#media">Untyped review trail</a></li>
      </ol>
    </nav>
    <nav id="print-pages" epub:type="page-list">
      <h2>Print page list</h2>
    </nav>
  </body>
</html>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideNavXhtml: $navWithDocumentDiagnostics,
        ));

        $report = $result['nav']['documentDiagnostics'];
        $t->same(true, $report['present']);
        $t->same('/OEBPS/nav.xhtml', $report['part']);
        $t->same(4, $report['sectionCount']);
        $t->same(3, $report['primarySectionCount']);
        $t->same(2, $report['tocSectionCount']);
        $t->same(0, $report['landmarksSectionCount']);
        $t->same(1, $report['pageListSectionCount']);
        $t->same(1, $report['duplicatePrimaryTypeCount']);
        $t->same(2, $report['emptySectionCount']);
        $t->same(1, $report['hiddenPrimarySectionCount']);
        $t->same(2, $report['missingOrderedListSectionCount']);
        $t->same(1, $report['untypedSectionCount']);
        $t->same(7, $report['diagnosticCount']);
        $t->same([
            'hidden-primary-nav-section',
            'missing-nav-section-ordered-list',
            'empty-nav-section',
            'missing-nav-section-type',
            'missing-nav-section-ordered-list',
            'empty-nav-section',
            'duplicate-primary-nav-section',
        ], array_column($report['diagnostics'], 'type'));
        $t->same('hidden-toc', $report['diagnostics'][0]['sectionId']);
        $t->same(['toc'], $report['diagnostics'][0]['sectionTypes']);
        $t->same('untyped-review', $report['diagnostics'][3]['sectionId']);
        $t->same('toc', $report['diagnostics'][6]['sectionType']);
        $t->same([0, 1], $report['diagnostics'][6]['sectionIndexes']);
        $t->same(['hidden-toc', 'visible-toc'], $report['diagnostics'][6]['sectionIds']);
        $t->same(7, $result['nav']['documentDiagnosticCount']);
        $t->same($report, $result['importReport']['nav']['documentDiagnostics']);
    },
    'reports EPUB nav document heading diagnostics for package review' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $navWithMissingHeading = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <ol>
        <li><a href="text/chapter1.xhtml#intro">Imported packet</a></li>
      </ol>
    </nav>
    <nav id="print-pages" epub:type="page-list">
      <h2>Print page list</h2>
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter1.xhtml#page-1">1</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideNavXhtml: $navWithMissingHeading,
        ));

        $report = $result['nav']['documentDiagnostics'];
        $t->same(true, $report['present']);
        $t->same('/OEBPS/nav.xhtml', $report['part']);
        $t->same(2, $report['sectionCount']);
        $t->same(2, $report['primarySectionCount']);
        $t->same(1, $report['tocSectionCount']);
        $t->same(1, $report['pageListSectionCount']);
        $t->same(1, $report['missingHeadingSectionCount']);
        $t->same(0, $report['missingOrderedListSectionCount']);
        $t->same(1, $report['diagnosticCount']);
        $t->same(['missing-primary-nav-section-heading'], array_column($report['diagnostics'], 'type'));
        $t->same('main-toc', $report['diagnostics'][0]['sectionId']);
        $t->same(['toc'], $report['diagnostics'][0]['sectionTypes']);
        $t->same(1, $result['nav']['documentDiagnosticCount']);
        $t->same($report, $result['importReport']['nav']['documentDiagnostics']);
    },
    'reports EPUB nav document item label diagnostics for package review' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $navWithMissingItemLabels = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a id="blank-toc-link" href="text/chapter1.xhtml#intro"></a></li>
        <li><a href="text/chapter2.xhtml#media">Media audit</a></li>
      </ol>
    </nav>
    <nav id="print-pages" epub:type="page-list">
      <h2>Print page list</h2>
      <ol>
        <li><a id="blank-page-link" epub:type="pagebreak" href="text/chapter1.xhtml#page-1"></a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideNavXhtml: $navWithMissingItemLabels,
        ));

        $report = $result['nav']['documentDiagnostics'];
        $t->same(true, $report['present']);
        $t->same(2, $report['sectionCount']);
        $t->same(2, $report['primarySectionCount']);
        $t->same(0, $report['missingHeadingSectionCount']);
        $t->same(2, $report['missingPrimaryItemLabelCount']);
        $t->same(2, $report['diagnosticCount']);
        $t->same(['missing-primary-nav-item-label', 'missing-primary-nav-item-label'], array_column($report['diagnostics'], 'type'));

        $tocDiagnostic = $report['diagnostics'][0];
        $t->same('main-toc', $tocDiagnostic['sectionId']);
        $t->same('toc', $tocDiagnostic['sectionType']);
        $t->same(0, $tocDiagnostic['itemIndex']);
        $t->same('blank-toc-link', $tocDiagnostic['labelId']);
        $t->same('text/chapter1.xhtml#intro', $tocDiagnostic['href']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $tocDiagnostic['target']);
        $t->same(0, $tocDiagnostic['depth']);

        $pageDiagnostic = $report['diagnostics'][1];
        $t->same('print-pages', $pageDiagnostic['sectionId']);
        $t->same(['page-list'], $pageDiagnostic['sectionTypes']);
        $t->same('blank-page-link', $pageDiagnostic['labelId']);
        $t->same('/OEBPS/text/chapter1.xhtml#page-1', $pageDiagnostic['target']);
        $t->same(2, $result['nav']['documentDiagnosticCount']);
        $t->same($report, $result['importReport']['nav']['documentDiagnostics']);
    },
    'reconciles EPUB navigation targets with resolved spine coverage' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $coverageNavXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter1.xhtml#intro">Imported packet</a></li>
        <li><a href="appendix/outside.xhtml">Outside appendix</a></li>
        <li><a href="https://cdn.example.test/epub/remote.xhtml">Remote appendix</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $coverageNcxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="navpoint-1" playOrder="1">
      <navLabel><text>Imported packet</text></navLabel>
      <content src="text/chapter1.xhtml#intro"/>
    </navPoint>
    <navPoint id="navpoint-2" playOrder="2">
      <navLabel><text>Review appendix</text></navLabel>
      <content src="text/chapter2.xhtml"/>
    </navPoint>
    <navPoint id="navpoint-missing" playOrder="3">
      <navLabel><text>Missing note</text></navLabel>
      <content src="text/missing.xhtml"/>
    </navPoint>
  </navMap>
</ncx>
XML;

        $opfWithUncoveredChapter = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="chapter-3" href="text/chapter3.xhtml" media-type="application/xhtml+xml"/><item id="appendix" href="appendix/outside.xhtml" media-type="application/xhtml+xml"/><item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );
        $opfWithUncoveredChapter = str_replace(
            '<itemref idref="chapter-2" linear="no"/>',
            '<itemref idref="chapter-2" linear="no"/><itemref idref="chapter-3"/>',
            $opfWithUncoveredChapter
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithUncoveredChapter,
            null,
            [
                ['name' => 'OEBPS/text/chapter3.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Unlisted chapter</h1></body></html>'],
                ['name' => 'OEBPS/appendix/outside.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Outside appendix</h1></body></html>'],
            ],
            $coverageNavXhtml,
            $coverageNcxXml
        ));

        $navigation = $result['navigation'];
        $t->same(true, $navigation['present']);
        $t->same(3, $navigation['navTocCount']);
        $t->same(3, $navigation['ncxCount']);
        $t->same(6, $navigation['targetCount']);
        $t->same(3, $navigation['mappedSpineTargetCount']);
        $t->same(1, $navigation['outsideSpineTargetCount']);
        $t->same(1, $navigation['missingTargetCount']);
        $t->same(1, $navigation['externalTargetCount']);
        $t->same(1, $navigation['uncoveredLinearSpineItemCount']);
        $t->same($navigation, $result['importReport']['navigation']);
        $t->same($navigation, $result['document']->attr('navigation'));

        $first = $navigation['items'][0];
        $t->same('nav', $first['source']);
        $t->same(0, $first['sourceIndex']);
        $t->same(0, $first['depth']);
        $t->same('Imported packet', $first['label']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $first['target']);
        $t->same('/OEBPS/text/chapter1.xhtml', $first['part']);
        $t->same('intro', $first['fragment']);
        $t->same(0, $first['spineIndex']);
        $t->same('chapter-1', $first['spineIdref']);
        $t->same(true, $first['linear']);
        $t->same([], $first['diagnostics']);

        $outside = $navigation['items'][1];
        $t->same('nav', $outside['source']);
        $t->same('/OEBPS/appendix/outside.xhtml', $outside['part']);
        $t->same(true, $outside['exists']);
        $t->same(null, $outside['spineIndex']);
        $t->same('navigation-target-outside-spine', $outside['diagnostics'][0]['type']);

        $remote = $navigation['items'][2];
        $t->same(true, $remote['external']);
        $t->same(null, $remote['part']);
        $t->same('external-nav-reference', $remote['sourceDiagnostics'][0]['type']);
        $t->same('external-navigation-target', $remote['diagnostics'][0]['type']);

        $chapter2Ncx = $navigation['items'][4];
        $t->same('ncx', $chapter2Ncx['source']);
        $t->same('2', $chapter2Ncx['playOrder']);
        $t->same('/OEBPS/text/chapter2.xhtml', $chapter2Ncx['part']);
        $t->same(1, $chapter2Ncx['spineIndex']);
        $t->same('chapter-2', $chapter2Ncx['spineIdref']);
        $t->same(false, $chapter2Ncx['linear']);

        $missing = $navigation['items'][5];
        $t->same('/OEBPS/text/missing.xhtml', $missing['part']);
        $t->same(false, $missing['exists']);
        $t->same('missing-ncx-reference', $missing['sourceDiagnostics'][0]['type']);
        $t->same('missing-navigation-target', $missing['diagnostics'][0]['type']);

        $chapter1Coverage = $navigation['spineCoverage'][0];
        $t->same('chapter-1', $chapter1Coverage['idref']);
        $t->same('/OEBPS/text/chapter1.xhtml', $chapter1Coverage['contentPart']);
        $t->same(true, $chapter1Coverage['linear']);
        $t->same(2, $chapter1Coverage['targetCount']);
        $t->same(1, $chapter1Coverage['navTocCount']);
        $t->same(1, $chapter1Coverage['ncxCount']);
        $t->same([], $chapter1Coverage['diagnostics']);

        $chapter3Coverage = $navigation['spineCoverage'][2];
        $t->same('chapter-3', $chapter3Coverage['idref']);
        $t->same(true, $chapter3Coverage['linear']);
        $t->same(0, $chapter3Coverage['targetCount']);
        $t->same('linear-spine-item-missing-navigation', $chapter3Coverage['diagnostics'][0]['type']);
        $t->same('chapter-3', $navigation['uncoveredLinearSpineItems'][0]['idref']);

        $t->same(3, count($navigation['diagnostics']));
        $t->same('navigation-target-outside-spine', $navigation['diagnostics'][0]['type']);
        $t->same('external-navigation-target', $navigation['diagnostics'][1]['type']);
        $t->same('missing-navigation-target', $navigation['diagnostics'][2]['type']);
    },
    'builds EPUB navigation outline review handoff from nav and NCX sources' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $result = (new EpubReader())->readPackage($buildEpubPackage());
        $outline = $result['navigationOutline'] ?? [];

        $t->same(true, $outline['present'] ?? null);
        $t->same('nav', $outline['source'] ?? null);
        $t->same(3, $outline['itemCount'] ?? null);
        $t->same(3, $outline['localTargetCount'] ?? null);
        $t->same(0, $outline['externalTargetCount'] ?? null);
        $t->same(0, $outline['missingTargetCount'] ?? null);
        $t->same(3, $outline['mappedSpineTargetCount'] ?? null);
        $t->same(1, $outline['maxDepth'] ?? null);
        $t->same('Imported packet', $outline['items'][0]['label'] ?? null);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $outline['items'][0]['target'] ?? null);
        $t->same(0, $outline['items'][0]['spineIndex'] ?? null);
        $t->same('Review appendix', $outline['items'][1]['label'] ?? null);
        $t->same(1, $outline['items'][1]['childCount'] ?? null);
        $t->same('Media audit', $outline['items'][1]['children'][0]['label'] ?? null);
        $t->same(1, $outline['items'][1]['children'][0]['spineIndex'] ?? null);
        $t->same('Media audit', $outline['flatItems'][2]['label'] ?? null);
        $t->contains('class="epub-navigation-outline"', $outline['html'] ?? '');
        $t->contains('data-epub-source="nav"', $outline['html'] ?? '');
        $t->contains('data-epub-target="/OEBPS/text/chapter2.xhtml#media"', $outline['html'] ?? '');
        $t->same($outline, $result['importReport']['navigationOutline'] ?? null);
        $t->same($outline, $result['document']->attr('navigationOutline'));

        $escapedNavXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter1.xhtml#intro">Imported &lt;packet&gt; &amp; audit</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;
        $escapedResult = (new EpubReader())->readPackage($buildEpubPackage(overrideNavXhtml: $escapedNavXhtml));
        $escapedOutline = $escapedResult['navigationOutline'] ?? [];
        $t->same('Imported <packet> & audit', $escapedOutline['items'][0]['label'] ?? null);
        $t->contains('Imported &lt;packet&gt; &amp; audit', $escapedOutline['html'] ?? '');

        $navWithoutToc = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="text/chapter1.xhtml#intro">Start reading</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;
        $ncxResult = (new EpubReader())->readPackage($buildEpubPackage(overrideNavXhtml: $navWithoutToc));
        $ncxOutline = $ncxResult['navigationOutline'] ?? [];
        $t->same(true, $ncxOutline['present'] ?? null);
        $t->same('ncx', $ncxOutline['source'] ?? null);
        $t->same(3, $ncxOutline['itemCount'] ?? null);
        $t->same('Imported packet', $ncxOutline['items'][0]['label'] ?? null);
        $t->same('1', $ncxOutline['items'][0]['playOrder'] ?? null);
        $t->same('Review appendix', $ncxOutline['items'][1]['label'] ?? null);
        $t->same(1, $ncxOutline['items'][1]['childCount'] ?? null);
        $t->same('Media audit', $ncxOutline['items'][1]['children'][0]['label'] ?? null);
        $t->same('3', $ncxOutline['items'][1]['children'][0]['playOrder'] ?? null);
        $t->contains('data-epub-source="ncx"', $ncxOutline['html'] ?? '');
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
    'preserves EPUB3 auxiliary navigation sections for package review' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $navWithAuxiliary = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol><li><a href="text/chapter1.xhtml#intro">Imported packet</a></li></ol>
    </nav>
    <nav id="figures-nav" class="review-list figures" epub:type="loi list-of-illustrations" xml:lang="en">
      <h2>Figures</h2>
      <ol>
        <li id="figure-cover"><a id="figure-cover-link" href="text/chapter1.xhtml#cover-figure">Cover figure</a></li>
        <li id="figure-remote"><a href="https://cdn.example.test/figures/source.svg">Remote figure source</a></li>
      </ol>
    </nav>
    <nav id="tables-nav" epub:type="lot" hidden="hidden">
      <h2>Tables</h2>
      <ol><li><a href="text/chapter2.xhtml#table-1">Table 1</a></li></ol>
    </nav>
    <nav epub:type="page-list">
      <ol><li><a href="text/chapter1.xhtml#page-1">1</a></li></ol>
    </nav>
  </body>
</html>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            overrideNavXhtml: $navWithAuxiliary,
        ));

        $nav = $result['nav'];
        $auxiliary = $nav['auxiliaryNavigation'];
        $t->same(true, $auxiliary['present']);
        $t->same(2, $auxiliary['sectionCount']);
        $t->same(3, $auxiliary['itemCount']);
        $t->same(['loi', 'list-of-illustrations', 'lot'], $auxiliary['types']);
        $t->same($auxiliary['sections'], $nav['auxiliarySections']);
        $t->same($auxiliary['items'], $nav['auxiliaryItems']);

        $figures = $auxiliary['sections'][0];
        $t->same(1, $figures['sectionIndex']);
        $t->same('figures-nav', $figures['id']);
        $t->same('review-list figures', $figures['class']);
        $t->same(['review-list', 'figures'], $figures['classes']);
        $t->same('en', $figures['language']);
        $t->same(false, $figures['hidden']);
        $t->same('loi', $figures['type']);
        $t->same(['loi', 'list-of-illustrations'], $figures['auxiliaryTypes']);
        $t->same('Figures', $figures['title']);
        $t->same(2, $figures['itemCount']);

        $firstFigure = $auxiliary['items'][0];
        $t->same('figures-nav', $firstFigure['sectionId']);
        $t->same('loi', $firstFigure['sectionType']);
        $t->same(['loi', 'list-of-illustrations'], $firstFigure['sectionTypes']);
        $t->same('figure-cover-link', $firstFigure['id']);
        $t->same('figure-cover', $firstFigure['itemId']);
        $t->same('Cover figure', $firstFigure['title']);
        $t->same('/OEBPS/text/chapter1.xhtml#cover-figure', $firstFigure['target']);
        $t->same('/OEBPS/text/chapter1.xhtml', $firstFigure['part']);
        $t->same(false, $firstFigure['external']);
        $t->same(true, $firstFigure['exists']);
        $t->same([], $firstFigure['diagnostics']);

        $remoteFigure = $auxiliary['items'][1];
        $t->same('Remote figure source', $remoteFigure['title']);
        $t->same(true, $remoteFigure['external']);
        $t->same(null, $remoteFigure['part']);
        $t->same('external-nav-reference', $remoteFigure['diagnostics'][0]['type']);

        $tableSection = $auxiliary['sectionsByType']['lot'][0];
        $t->same('tables-nav', $tableSection['id']);
        $t->same(true, $tableSection['hidden']);
        $t->same('Tables', $tableSection['title']);
        $t->same('lot', $auxiliary['items'][2]['sectionType']);
        $t->same('/OEBPS/text/chapter2.xhtml#table-1', $auxiliary['items'][2]['target']);

        $t->same(false, isset($auxiliary['sectionsByType']['toc']));
        $t->same(false, isset($auxiliary['sectionsByType']['page-list']));
        $t->same($auxiliary, $result['importReport']['nav']['auxiliaryNavigation']);
    },
    'reports EPUB3 primary navigation target policy for package review' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $navWithTargetPolicy = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="text/chapter1.xhtml#intro">Start reading</a></li>
        <li><a href="appendix/outside.xhtml#appendix">Appendix outside spine</a></li>
        <li><a href="https://cdn.example.test/epub/remote.xhtml">Remote review note</a></li>
      </ol>
    </nav>
    <nav id="landmarks" epub:type="landmarks">
      <h2>Landmarks</h2>
      <ol>
        <li><a epub:type="bodymatter" href="text/chapter1.xhtml#intro">Body</a></li>
        <li><a href="text/chapter2.xhtml#media">Untyped reading point</a></li>
      </ol>
    </nav>
    <nav id="pages" epub:type="page-list">
      <h2>Pages</h2>
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter1.xhtml#page-1">1</a></li>
        <li><a epub:type="pagebreak" href="text/missing.xhtml#page-404">404</a></li>
      </ol>
    </nav>
    <nav id="figures" epub:type="loi">
      <h2>Figures</h2>
      <ol>
        <li><a href="https://cdn.example.test/figures/source.svg">Remote figure source</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            null,
            null,
            [
                ['name' => 'OEBPS/appendix/outside.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="appendix">Appendix</h1></body></html>'],
            ],
            $navWithTargetPolicy
        ));

        $policy = $result['nav']['primaryNavigationTargetPolicy'];
        $t->same(true, $policy['present']);
        $t->same(3, $policy['sectionCount']);
        $t->same(7, $policy['itemCount']);
        $t->same(['toc', 'landmarks', 'page-list'], $policy['types']);
        $t->same(7, $policy['targetedItemCount']);
        $t->same(4, $policy['validTargetCount']);
        $t->same(1, $policy['externalTargetCount']);
        $t->same(0, $policy['missingTargetCount']);
        $t->same(1, $policy['missingReferenceCount']);
        $t->same(1, $policy['outsideSpineTargetCount']);
        $t->same(2, $policy['landmarkCount']);
        $t->same(1, $policy['landmarkMissingTypeCount']);
        $t->same(4, $policy['diagnosticCount']);
        $t->same(['primary-nav-target-outside-spine', 'external-primary-nav-target', 'missing-landmark-nav-type', 'missing-primary-nav-reference'], array_column($policy['diagnostics'], 'type'));

        $outside = $policy['itemsBySectionType']['toc'][1];
        $t->same('Appendix outside spine', $outside['label']);
        $t->same('/OEBPS/appendix/outside.xhtml#appendix', $outside['target']);
        $t->same(true, $outside['exists']);
        $t->same(null, $outside['spineIndex']);
        $t->same('primary-nav-target-outside-spine', $outside['diagnostics'][0]['type']);

        $remoteToc = $policy['itemsBySectionType']['toc'][2];
        $t->same(true, $remoteToc['external']);
        $t->same('external-nav-reference', $remoteToc['sourceDiagnostics'][0]['type']);
        $t->same('external-primary-nav-target', $remoteToc['diagnostics'][0]['type']);

        $untypedLandmark = $policy['itemsBySectionType']['landmarks'][1];
        $t->same('Untyped reading point', $untypedLandmark['label']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $untypedLandmark['target']);
        $t->same(1, $untypedLandmark['spineIndex']);
        $t->same('chapter-2', $untypedLandmark['spineIdref']);
        $t->same('missing-landmark-nav-type', $untypedLandmark['diagnostics'][0]['type']);

        $missingPage = $policy['itemsBySectionType']['page-list'][1];
        $t->same('/OEBPS/text/missing.xhtml#page-404', $missingPage['target']);
        $t->same(false, $missingPage['exists']);
        $t->same('missing-nav-reference', $missingPage['sourceDiagnostics'][0]['type']);
        $t->same('missing-primary-nav-reference', $missingPage['diagnostics'][0]['type']);

        $t->same(false, isset($policy['itemsBySectionType']['loi']));
        $t->same(1, $result['nav']['auxiliaryNavigation']['itemCount']);
        $t->same($policy, $result['importReport']['nav']['primaryNavigationTargetPolicy']);
    },
    'reports EPUB navigation media fragments for package review' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $navWithMediaFragments = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="media-toc" epub:type="toc">
      <h1>Media navigation</h1>
      <ol>
        <li><a href="audio/chapter1.mp3#t=1.5,4.5">Audio clip</a></li>
        <li><a href="images/cover.png#xywh=percent:10,20,30,40">Cover crop</a></li>
      </ol>
    </nav>
    <nav id="pages" epub:type="page-list">
      <h2>Pages</h2>
      <ol>
        <li><a epub:type="pagebreak" href="images/cover.png#xywh=pixel:5,10,50,80">Cover crop page</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;
        $ncxWithMediaFragments = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="audio-point" playOrder="1">
      <navLabel><text>Audio clip</text></navLabel>
      <content src="audio/chapter1.mp3#t=2,6"/>
    </navPoint>
  </navMap>
</ncx>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            null,
            null,
            [
                ['name' => 'OEBPS/audio/chapter1.mp3', 'data' => 'MP3DATA'],
            ],
            $navWithMediaFragments,
            $ncxWithMediaFragments
        ));

        $audioTarget = $result['nav']['items'][0];
        $t->same('media-fragment', $audioTarget['fragmentKind']);
        $t->same('t=1.5,4.5', $audioTarget['fragment']);
        $t->same(['t'], $audioTarget['mediaFragment']['dimensionNames']);
        $t->same(true, $audioTarget['mediaFragment']['valid']);
        $t->same(1.5, $audioTarget['mediaFragment']['time']['startSeconds']);
        $t->same(4.5, $audioTarget['mediaFragment']['time']['endSeconds']);
        $t->same(3.0, $audioTarget['mediaFragment']['time']['durationSeconds']);

        $coverCrop = $result['nav']['items'][1];
        $t->same('media-fragment', $coverCrop['fragmentKind']);
        $t->same('percent', $coverCrop['mediaFragment']['xywh']['unit']);
        $t->same(10.0, $coverCrop['mediaFragment']['xywh']['x']);
        $t->same(20.0, $coverCrop['mediaFragment']['xywh']['y']);
        $t->same(30.0, $coverCrop['mediaFragment']['xywh']['width']);
        $t->same(40.0, $coverCrop['mediaFragment']['xywh']['height']);

        $navigation = $result['navigation'];
        $t->same(3, $navigation['targetCount']);
        $t->same(3, $navigation['mediaFragmentTargetCount']);
        $t->same(3, count($navigation['mediaFragmentTargets']));
        $t->same(0, $navigation['cfiTargetCount']);
        $t->same(true, in_array('navigation-media-fragment-target', array_column($navigation['diagnostics'], 'type'), true));
        $t->same(2.0, $navigation['mediaFragmentTargets'][2]['mediaFragment']['time']['startSeconds']);
        $t->same(6.0, $navigation['mediaFragmentTargets'][2]['mediaFragment']['time']['endSeconds']);

        $policy = $result['nav']['primaryNavigationTargetPolicy'];
        $t->same(3, $policy['mediaFragmentTargetCount']);
        $t->same(true, in_array('primary-nav-media-fragment-target', array_column($policy['diagnostics'], 'type'), true));

        $pageBreaks = $result['pageBreaks'];
        $t->same('nav-page-list', $pageBreaks['source']);
        $t->same(1, $pageBreaks['mediaFragmentPageBreakCount']);
        $t->same('media-fragment', $pageBreaks['items'][0]['fragmentKind']);
        $t->same('pixel', $pageBreaks['items'][0]['mediaFragment']['xywh']['unit']);
        $t->same(5.0, $pageBreaks['items'][0]['mediaFragment']['xywh']['x']);
        $t->same(80.0, $pageBreaks['items'][0]['mediaFragment']['xywh']['height']);
        $t->same(true, in_array('page-list-media-fragment-target', array_column($pageBreaks['diagnostics'], 'type'), true));
        $t->same($pageBreaks, $result['document']->attr('pageBreaks'));
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
    'builds EPUB page-break report from legacy NCX pageList when nav page-list is absent' => static function (TestRunner $t) use ($buildEpubPackage, $chapter1Xhtml): void {
        $tocOnlyNavXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter1.xhtml#intro">Imported packet</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $ncxWithPageList = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="navpoint-1" playOrder="1">
      <navLabel><text>Imported packet</text></navLabel>
      <content src="text/chapter1.xhtml#intro"/>
    </navPoint>
  </navMap>
  <pageList id="print-pages" class="legacy-pages print-pages" xml:lang="en" dir="ltr">
    <navLabel id="print-pages-label" class="page-list-label"><text id="print-pages-title">Print page list</text></navLabel>
    <pageTarget id="page-1" type="normal" value="1" playOrder="10" class="main-page first" xml:lang="fr" dir="rtl" aria-hidden="true">
      <navLabel id="page-one-label" class="page-label"><text id="page-one-text">1</text></navLabel>
      <content id="page-one-content" src="text/chapter1.xhtml#page-1" data-review="pagebreak"/>
    </pageTarget>
    <pageTarget id="page-appendix" type="front" value="iii" playOrder="11" class="frontmatter">
      <navLabel><text>iii</text></navLabel>
      <content src="appendix/print-page.xhtml#page-iii"/>
    </pageTarget>
    <pageTarget id="page-remote" type="special" value="remote" playOrder="12">
      <navLabel><text>Remote</text></navLabel>
      <content src="https://cdn.example.test/epub/remote-page.xhtml#page-remote"/>
    </pageTarget>
  </pageList>
</ncx>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            null,
            null,
            [
                ['name' => 'OEBPS/appendix/print-page.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><span id="page-iii">iii</span></body></html>'],
            ],
            $tocOnlyNavXhtml,
            $ncxWithPageList
        ));

        $ncx = $result['ncx'];
        $t->same(3, $ncx['pageListCount']);
        $t->same(true, $ncx['pageListReport']['present']);
        $t->same('print-pages', $ncx['pageListReport']['id']);
        $t->same('legacy-pages print-pages', $ncx['pageListReport']['class']);
        $t->same(['legacy-pages', 'print-pages'], $ncx['pageListReport']['classes']);
        $t->same('en', $ncx['pageListReport']['language']);
        $t->same('ltr', $ncx['pageListReport']['direction']);
        $t->same('Print page list', $ncx['pageListReport']['title']);
        $t->same(['class' => 'legacy-pages print-pages', 'dir' => 'ltr', 'id' => 'print-pages', 'lang' => 'en'], $ncx['pageListReport']['attributes']);
        $t->same(3, $ncx['pageListReport']['itemCount']);
        $t->same(1, $ncx['pageListReport']['diagnosticCount']);
        $t->same('external-ncx-page-list-reference', $ncx['pageListDiagnostics'][0]['type']);
        $t->same(2, $ncx['pageListDiagnostics'][0]['targetIndex']);
        $t->same('page-remote', $ncx['pageListDiagnostics'][0]['targetId']);
        $t->same(3, count($ncx['pageList']));
        $t->same('page-1', $ncx['pageList'][0]['id']);
        $t->same('normal', $ncx['pageList'][0]['type']);
        $t->same('1', $ncx['pageList'][0]['value']);
        $t->same('10', $ncx['pageList'][0]['playOrder']);
        $t->same('main-page first', $ncx['pageList'][0]['class']);
        $t->same(['main-page', 'first'], $ncx['pageList'][0]['classes']);
        $t->same('fr', $ncx['pageList'][0]['language']);
        $t->same('rtl', $ncx['pageList'][0]['direction']);
        $t->same(true, $ncx['pageList'][0]['hidden']);
        $t->same('/OEBPS/text/chapter1.xhtml#page-1', $ncx['pageList'][0]['target']);
        $t->same(strlen($chapter1Xhtml), $ncx['pageList'][0]['byteLength']);
        $t->same(hash('crc32b', $chapter1Xhtml), $ncx['pageList'][0]['crc32']);
        $t->same(['aria-hidden' => 'true', 'class' => 'main-page first', 'dir' => 'rtl', 'id' => 'page-1', 'lang' => 'fr', 'playOrder' => '10', 'type' => 'normal', 'value' => '1'], $ncx['pageList'][0]['attributes']);
        $t->same(['class' => 'page-label', 'id' => 'page-one-label'], $ncx['pageList'][0]['labelAttributes']);
        $t->same(['id' => 'page-one-text'], $ncx['pageList'][0]['labelTextAttributes']);
        $t->same(['data-review' => 'pagebreak', 'id' => 'page-one-content', 'src' => 'text/chapter1.xhtml#page-1'], $ncx['pageList'][0]['contentAttributes']);
        $t->same('frontmatter', $ncx['pageList'][1]['class']);
        $t->same(true, $ncx['pageList'][2]['external']);
        $t->same('external-ncx-page-list-reference', $ncx['pageList'][2]['diagnostics'][0]['type']);

        $pageBreaks = $result['pageBreaks'];
        $t->same(true, $pageBreaks['present']);
        $t->same('ncx-page-list', $pageBreaks['source']);
        $t->same(3, $pageBreaks['count']);
        $t->same($pageBreaks, $result['importReport']['pageBreaks']);
        $t->same($pageBreaks, $result['document']->attr('pageBreaks'));

        $first = $pageBreaks['items'][0];
        $t->same('ncx', $first['source']);
        $t->same('page-1', $first['id']);
        $t->same('1', $first['label']);
        $t->same('text/chapter1.xhtml#page-1', $first['href']);
        $t->same('/OEBPS/text/chapter1.xhtml#page-1', $first['target']);
        $t->same('page-1', $first['fragment']);
        $t->same('normal', $first['type']);
        $t->same(['normal'], $first['types']);
        $t->same(['main-page', 'first'], $first['classes']);
        $t->same('fr', $first['language']);
        $t->same('rtl', $first['direction']);
        $t->same(true, $first['hidden']);
        $t->same(['data-review' => 'pagebreak', 'id' => 'page-one-content', 'src' => 'text/chapter1.xhtml#page-1'], $first['contentAttributes']);
        $t->same(['id' => 'page-one-text'], $first['labelTextAttributes']);
        $t->same(strlen($chapter1Xhtml), $first['byteLength']);
        $t->same(hash('crc32b', $chapter1Xhtml), $first['crc32']);
        $t->same(false, $first['encrypted']);
        $t->same(true, $first['canExposeBytes']);
        $t->same('1', $first['value']);
        $t->same('10', $first['playOrder']);
        $t->same(0, $first['spineIndex']);
        $t->same('chapter-1', $first['spineIdref']);
        $t->same([], $first['sourceDiagnostics']);
        $t->same([], $first['diagnostics']);

        $outside = $pageBreaks['items'][1];
        $t->same('page-appendix', $outside['id']);
        $t->same('/OEBPS/appendix/print-page.xhtml', $outside['part']);
        $t->same(null, $outside['spineIndex']);
        $t->same('page-list-target-outside-spine', $outside['diagnostics'][0]['type']);

        $remote = $pageBreaks['items'][2];
        $t->same('page-remote', $remote['id']);
        $t->same(true, $remote['external']);
        $t->same('https://cdn.example.test/epub/remote-page.xhtml#page-remote', $remote['target']);
        $t->same('external-ncx-page-list-reference', $remote['sourceDiagnostics'][0]['type']);
        $t->same('external-page-list-reference', $remote['diagnostics'][0]['type']);

        $t->same(1, count($pageBreaks['itemsByPart']['/OEBPS/text/chapter1.xhtml']));
        $t->same(1, count($pageBreaks['itemsByPart']['/OEBPS/appendix/print-page.xhtml']));
        $t->same(2, count($pageBreaks['diagnostics']));
        $t->same('page-list-target-outside-spine', $pageBreaks['diagnostics'][0]['type']);
        $t->same('external-page-list-reference', $pageBreaks['diagnostics'][1]['type']);

        $t->same(1, $result['document']->children[0]->attr('pageBreakCount'));
        $t->same('ncx', $result['document']->children[0]->attr('pageBreaks')[0]['source']);
        $t->same('1', $result['document']->children[0]->attr('pageBreaks')[0]['label']);
    },
    'builds EPUB page-break report from XHTML semantic pagebreaks when nav page lists are absent' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $tocOnlyNavXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/semantic-pages.xhtml#source">Source pages</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;
        $semanticPagesXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="en">
  <body>
    <section id="source" epub:type="bodymatter chapter">
      <h1>Source pages</h1>
      <span id="page-10" class="print-page" epub:type="pagebreak" title="10"></span>
      <span id="page-11" epub:type="pagebreak" aria-label="Page eleven">11</span>
    </section>
  </body>
</html>
XML;
        $opfWithSemanticPages = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="semantic-pages" href="text/semantic-pages.xhtml" media-type="application/xhtml+xml"/>',
            $opfXml
        );
        $opfWithSemanticPages = str_replace(
            '</spine>',
            '<itemref idref="semantic-pages"/></spine>',
            $opfWithSemanticPages
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithSemanticPages,
            null,
            [
                ['name' => 'OEBPS/text/semantic-pages.xhtml', 'data' => $semanticPagesXhtml],
            ],
            $tocOnlyNavXhtml
        ));

        $pageBreaks = $result['pageBreaks'];
        $t->same(true, $pageBreaks['present']);
        $t->same('xhtml-semantic-pagebreak', $pageBreaks['source']);
        $t->same(2, $pageBreaks['count']);
        $t->same(0, $pageBreaks['cfiPageBreakCount']);
        $t->same(0, $pageBreaks['mediaFragmentPageBreakCount']);
        $t->same($pageBreaks, $result['importReport']['pageBreaks']);
        $t->same($pageBreaks, $result['document']->attr('pageBreaks'));

        $first = $pageBreaks['items'][0];
        $t->same('xhtml-semantic', $first['source']);
        $t->same('page-10', $first['id']);
        $t->same('10', $first['label']);
        $t->same('/OEBPS/text/semantic-pages.xhtml#page-10', $first['target']);
        $t->same('/OEBPS/text/semantic-pages.xhtml', $first['part']);
        $t->same('page-10', $first['fragment']);
        $t->same('id', $first['fragmentKind']);
        $t->same('semantic-pages', $first['spineIdref']);
        $t->same('semantic-pages', $first['manifestId']);
        $t->same(['pagebreak'], $first['types']);
        $t->same(['print-page'], $first['classes']);
        $t->same('span', $first['labelElement']);
        $t->same('10', $first['attributes']['title']);
        $t->same([], $first['diagnostics']);

        $second = $pageBreaks['items'][1];
        $t->same('11', $second['label']);
        $t->same('page-11', $second['fragment']);
        $t->same('Page eleven', $second['attributes']['aria-label']);
        $t->same(2, count($pageBreaks['itemsByPart']['/OEBPS/text/semantic-pages.xhtml']));

        $semanticBlock = $result['document']->children[2];
        $t->same(2, $semanticBlock->attr('pageBreakCount'));
        $t->same('xhtml-semantic', $semanticBlock->attr('pageBreaks')[0]['source']);
        $t->same('page-11', $semanticBlock->attr('pageBreaks')[1]['fragment']);
    },
    'parses OPF guide references and collection review metadata' => static function (TestRunner $t) use ($buildEpubPackage): void {
        $result = (new EpubReader())->readPackage($buildEpubPackage());

        $guide = $result['guide'];
        $t->same(true, $guide['present']);
        $t->same(3, $guide['itemCount']);
        $t->same(3, $guide['typedItemCount']);
        $t->same(0, $guide['missingTypeCount']);
        $t->same(['cover', 'text', 'glossary'], $guide['types']);
        $t->same(['cover' => 1, 'text' => 1, 'glossary' => 1], $guide['typeCounts']);
        $t->same(3, count($guide['items']));
        $t->same('cover', $guide['items'][0]['type']);
        $t->same('cover', $guide['items'][0]['typeRaw']);
        $t->same(['cover'], $guide['items'][0]['types']);
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
        $t->same('Start reading', $guide['itemsByType']['text'][0]['title']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $guide['itemsByType']['text'][0]['target']);
        $t->same(false, $guide['items'][2]['exists']);
        $t->same('/OEBPS/text/missing.xhtml', $guide['items'][2]['part']);
        $t->same('missing-guide-reference', $guide['items'][2]['diagnostics'][0]['type']);
        $t->same($guide['items'][2], $guide['itemsByType']['glossary'][0]);
        $t->same(1, $guide['diagnosticCount']);
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
    'summarizes OPF guide reference type vocabulary for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $guideVocabularyOpf = str_replace(
            '<reference type="cover" title="Cover image" href="images/cover.png"/>',
            '<reference type="cover title-page" title="Cover image" href="images/cover.png"/>',
            $opfXml
        );
        $guideVocabularyOpf = str_replace(
            '<reference type="glossary" title="Missing glossary" href="text/missing.xhtml"/>',
            '<reference title="Untyped reading point" href="text/chapter2.xhtml#media"/>',
            $guideVocabularyOpf
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($guideVocabularyOpf));
        $guide = $result['guide'];

        $t->same(true, $guide['present']);
        $t->same(3, $guide['itemCount']);
        $t->same(2, $guide['typedItemCount']);
        $t->same(1, $guide['missingTypeCount']);
        $t->same(['cover', 'title-page', 'text'], $guide['types']);
        $t->same(['cover' => 1, 'title-page' => 1, 'text' => 1], $guide['typeCounts']);
        $t->same('cover', $guide['items'][0]['type']);
        $t->same('cover title-page', $guide['items'][0]['typeRaw']);
        $t->same(['cover', 'title-page'], $guide['items'][0]['types']);
        $t->same('Cover image', $guide['itemsByType']['cover'][0]['title']);
        $t->same('Cover image', $guide['itemsByType']['title-page'][0]['title']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $guide['itemsByType']['text'][0]['target']);
        $t->same(null, $guide['items'][2]['type']);
        $t->same(null, $guide['items'][2]['typeRaw']);
        $t->same([], $guide['items'][2]['types']);
        $t->same('Untyped reading point', $guide['items'][2]['title']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $guide['items'][2]['target']);
        $t->same('chapter-2', $guide['items'][2]['manifestId']);
        $t->same('missing-guide-reference-type', $guide['items'][2]['diagnostics'][0]['type']);
        $t->same(1, $guide['diagnosticCount']);
        $t->same('missing-guide-reference-type', $guide['diagnostics'][0]['type']);
        $t->same(2, $guide['diagnostics'][0]['index']);
        $t->same($guide, $result['importReport']['guide']);
        $t->same($guide, $result['document']->attr('guide'));
    },
    'reports OPF collection role tokens for package review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithCollectionRoles = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en" prefix="schema: https://schema.org/ review: https://example.invalid/epub-review#">',
            $opfXml
        );
        $opfWithCollectionRoles = str_replace(
            '<collection id="series" role="series" xml:lang="en">',
            '<collection id="series" role="series schema:hasPart https://example.invalid/roles#review-packet review:packet bad/role https://example.invalid/roles/no-fragment unknown:tag series" xml:lang="en">',
            $opfWithCollectionRoles
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithCollectionRoles));
        $series = $result['collections'][0];
        $roleReport = $series['roleReport'];

        $t->same('series schema:hasPart https://example.invalid/roles#review-packet review:packet bad/role https://example.invalid/roles/no-fragment unknown:tag series', $roleReport['raw']);
        $t->same([
            'series',
            'schema:hasPart',
            'https://example.invalid/roles#review-packet',
            'review:packet',
            'bad/role',
            'https://example.invalid/roles/no-fragment',
            'unknown:tag',
            'series',
        ], $roleReport['values']);
        $t->same('series', $roleReport['primaryRole']);
        $t->same(8, $roleReport['count']);
        $t->same(6, $roleReport['validCount']);
        $t->same(2, $roleReport['invalidCount']);
        $t->same(3, $roleReport['resolvedCount']);
        $t->same(1, $roleReport['absoluteUrlCount']);

        $t->same('nmtoken', $roleReport['items'][0]['kind']);
        $t->same(true, $roleReport['items'][0]['valid']);
        $t->same('prefixed-nmtoken', $roleReport['items'][1]['kind']);
        $t->same('schema', $roleReport['items'][1]['prefix']);
        $t->same('hasPart', $roleReport['items'][1]['localName']);
        $t->same('https://schema.org/hasPart', $roleReport['items'][1]['iri']);
        $t->same(true, $roleReport['items'][1]['resolved']);
        $t->same('absolute-url-with-fragment', $roleReport['items'][2]['kind']);
        $t->same(true, $roleReport['items'][2]['absoluteUrlWithFragment']);
        $t->same('https://example.invalid/roles#review-packet', $roleReport['items'][2]['iri']);
        $t->same('https://example.invalid/epub-review#packet', $roleReport['items'][3]['iri']);
        $t->same('invalid-collection-role-token', $roleReport['items'][4]['diagnostics'][0]['type']);
        $t->same('bad/role', $roleReport['items'][4]['diagnostics'][0]['role']);
        $t->same('invalid-collection-role-url-fragment', $roleReport['items'][5]['diagnostics'][0]['type']);
        $t->same('unknown-collection-role-prefix', $roleReport['items'][6]['diagnostics'][0]['type']);
        $t->same('unknown', $roleReport['items'][6]['diagnostics'][0]['prefix']);
        $t->same('duplicate-collection-role-token', $roleReport['items'][7]['diagnostics'][0]['type']);
        $t->same(0, $roleReport['items'][7]['diagnostics'][0]['previousIndex']);
        $t->same([
            'invalid-collection-role-token',
            'invalid-collection-role-url-fragment',
            'unknown-collection-role-prefix',
            'duplicate-collection-role-token',
        ], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $roleReport['diagnostics']));

        $t->same($roleReport['values'], $series['roleTokens']);
        $t->same('series', $series['primaryRole']);
        $t->same('preview', $series['children'][0]['roleReport']['primaryRole']);
        $t->same([], $series['children'][0]['roleReport']['diagnostics']);
        $t->same($result['collections'], $result['importReport']['collections']);
        $t->same($roleReport, $result['document']->attr('collections')[0]['roleReport']);
    },
    'summarizes OPF collection link relations and review targets' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithCollectionLinks = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="locked-audio" href="audio/locked.mp3" media-type="audio/mpeg"/>'
                . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );
        $opfWithCollectionLinks = str_replace(
            '<link rel="first" href="text/chapter1.xhtml#intro" media-type="application/xhtml+xml" properties="preview"/>',
            '<link rel="first preview" href="text/chapter1.xhtml#intro" media-type="application/xhtml+xml" properties="preview sample"/>',
            $opfWithCollectionLinks
        );
        $opfWithCollectionLinks = str_replace(
            '<link rel="record" href="https://example.invalid/source-record" media-type="text/html"/>',
            '<link id="external-record" rel="record alternate" href="https://example.invalid/source-record" media-type="text/html" properties="schema-org reviewer"/>'
                . '<link id="missing-review" rel="review" href="text/missing.xhtml" media-type="application/xhtml+xml"/>'
                . '<link id="locked-audio-link" rel="voicing" href="audio/locked.mp3" media-type="audio/mpeg"/>'
                . '<link id="unclassified" href="text/chapter2.xhtml#media" media-type="application/xhtml+xml"/>',
            $opfWithCollectionLinks
        );
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="OEBPS/audio/locked.mp3"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithCollectionLinks,
            null,
            [
                ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
                ['name' => 'OEBPS/audio/locked.mp3', 'data' => 'LOCKED-MP3'],
            ]
        ));

        $series = $result['collections'][0];
        $report = $series['linkReport'];

        $t->same(5, $series['linkCount']);
        $t->same(4, $series['localLinkCount']);
        $t->same(1, $series['externalLinkCount']);
        $t->same(1, $series['missingLinkCount']);
        $t->same(1, $series['encryptedLinkCount']);
        $t->same(true, $report['present']);
        $t->same(5, $report['count']);
        $t->same(4, $report['localCount']);
        $t->same(1, $report['externalCount']);
        $t->same(1, $report['missingCount']);
        $t->same(1, $report['encryptedCount']);
        $t->same(1, $report['recordLinkCount']);
        $t->same(4, $report['reviewRequiredCount']);
        $t->same(['first', 'preview', 'record', 'alternate', 'review', 'voicing'], $report['relTokens']);
        $t->same([
            'first' => 1,
            'preview' => 1,
            'record' => 1,
            'alternate' => 1,
            'review' => 1,
            'voicing' => 1,
        ], $report['relCounts']);
        $t->same('chapter-1', $report['linksByRel']['first'][0]['manifestId']);
        $t->same('external-record', $report['linksByRel']['record'][0]['id']);
        $t->same('locked-audio-link', $report['linksByRel']['voicing'][0]['id']);
        $t->same(['preview', 'sample', 'schema-org', 'reviewer'], $report['propertyTokens']);
        $t->same([
            'preview' => 1,
            'sample' => 1,
            'schema-org' => 1,
            'reviewer' => 1,
        ], $report['propertyCounts']);
        $t->same($report['relTokens'], $series['collectionLinkRelTokens']);
        $t->same($report['relCounts'], $series['collectionLinkRelCounts']);
        $t->same($report['linksByRel'], $series['collectionLinksByRel']);
        $t->same($report['propertyTokens'], $series['collectionLinkPropertyTokens']);
        $t->same($report['propertyCounts'], $series['collectionLinkPropertyCounts']);
        $t->same([
            'external-collection-link',
            'missing-collection-reference',
            'missing-collection-link-rel',
        ], array_map(static fn (array $diagnostic): string => (string) $diagnostic['type'], $report['diagnostics']));
        $t->same(3, $report['diagnosticCount']);
        $t->same('external-record', $report['diagnostics'][0]['id']);
        $t->same('missing-review', $report['diagnostics'][1]['id']);
        $t->same('unclassified', $report['diagnostics'][2]['id']);
        $t->same(true, $series['links'][3]['encrypted']);
        $t->same(false, $series['links'][3]['canExposeBytes']);
        $t->same($report['diagnostics'], $series['collectionLinkDiagnostics']);
        $t->same($report, $result['importReport']['collections'][0]['linkReport']);
        $t->same($report, $result['document']->attr('collections')[0]['linkReport']);
        $t->same(1, $series['children'][0]['linkReport']['count']);
        $t->same(['sample'], $series['children'][0]['linkReport']['relTokens']);
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
    'uses OPF bindings as XHTML fallback handlers for custom spine media' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $tourFallbackXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head><title>Interactive tour fallback</title></head>
  <body><h1>Interactive tour fallback</h1><p>Bound media handler content remains reviewable.</p></body>
</html>
XML;
        $opfWithBoundSpine = str_replace(
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/><item id="interactive-tour" href="interactive/tour.bin" media-type="application/x-review-tour"/><item id="tour-handler" href="text/tour-fallback.xhtml" media-type="application/xhtml+xml" properties="scripted"/>',
            $opfXml
        );
        $opfWithBoundSpine = str_replace(
            '<itemref idref="chapter-2" linear="no"/>',
            '<itemref idref="interactive-tour" linear="no"/><itemref idref="chapter-2" linear="no"/>',
            $opfWithBoundSpine
        );
        $opfWithBoundSpine = str_replace(
            '</package>',
            '<bindings><mediaType media-type="application/x-review-tour" handler="tour-handler"/></bindings></package>',
            $opfWithBoundSpine
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithBoundSpine,
            null,
            [
                ['name' => 'OEBPS/interactive/tour.bin', 'data' => 'BOUND-TOUR'],
                ['name' => 'OEBPS/text/tour-fallback.xhtml', 'data' => $tourFallbackXhtml],
            ]
        ));

        $binding = $result['bindings']['items'][0];
        $boundSpine = $result['spine'][1];
        $t->same('application/x-review-tour', $binding['mediaType']);
        $t->same('tour-handler', $binding['handlerId']);
        $t->same('/OEBPS/text/tour-fallback.xhtml', $binding['handlerPart']);
        $t->same([], $binding['diagnostics']);

        $t->same('interactive-tour', $boundSpine['idref']);
        $t->same('application/x-review-tour', $boundSpine['mediaType']);
        $t->same('/OEBPS/interactive/tour.bin', $boundSpine['part']);
        $t->same($binding, $boundSpine['binding']);
        $t->same('tour-handler', $boundSpine['contentId']);
        $t->same('/OEBPS/text/tour-fallback.xhtml', $boundSpine['contentPart']);
        $t->same('application/xhtml+xml', $boundSpine['contentMediaType']);
        $t->same(true, $boundSpine['contentIsFallback']);
        $t->same([], $boundSpine['fallbackDiagnostics']);
        $t->same(1, count($boundSpine['fallbackChain']));
        $t->same('tour-handler', $boundSpine['fallbackChain'][0]['id']);
        $t->same('binding-handler', $boundSpine['fallbackChain'][0]['source']);
        $t->same('application/x-review-tour', $boundSpine['fallbackChain'][0]['bindingMediaType']);

        $t->same(3, count($result['document']->children));
        $fallbackBlock = $result['document']->children[1];
        $t->same('epub3-spine-fallback', $fallbackBlock->attr('source'));
        $t->same('interactive-tour', $fallbackBlock->attr('fallbackOf'));
        $t->same('/OEBPS/interactive/tour.bin', $fallbackBlock->attr('spinePart'));
        $t->same('application/x-review-tour', $fallbackBlock->attr('spineMediaType'));
        $t->same('/OEBPS/text/tour-fallback.xhtml', $fallbackBlock->attr('part'));
        $t->same('tour-handler', $fallbackBlock->attr('contentId'));
        $t->same($binding, $fallbackBlock->attr('binding'));
        $t->same($boundSpine['fallbackChain'], $fallbackBlock->attr('fallbackChain'));
        $t->contains('Bound media handler content remains reviewable.', $fallbackBlock->attr('html'));

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Bound media handler content remains reviewable.', $blocks);
    },
    'reports missing non-spine package assets without dropping XHTML handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithMissingAudio = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="missing-audio" href="audio/missing.mp3" media-type="audio/mpeg"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithMissingAudio));
        $manifestReport = $result['importReport']['manifest'];
        $missing = $manifestReport['missingItems'];
        $assetById = [];
        foreach ($result['assets'] as $asset) {
            $assetById[$asset['id']] = $asset;
        }

        $t->same(1, $manifestReport['missingItemCount']);
        $t->same(1, count($missing));
        $t->same('missing-audio', $missing[0]['id']);
        $t->same('/OEBPS/audio/missing.mp3', $missing[0]['part']);
        $t->same('missing-non-spine-manifest-resource', $missing[0]['diagnostics'][0]['type']);
        $t->same('missing-audio', $missing[0]['diagnostics'][0]['id']);
        $t->same('/OEBPS/audio/missing.mp3', $missing[0]['diagnostics'][0]['part']);
        $t->same(1, $manifestReport['itemDiagnosticCount']);
        $t->same('missing-non-spine-manifest-resource', $manifestReport['itemDiagnostics'][0]['type']);
        $t->same('missing-audio', $manifestReport['itemDiagnostics'][0]['id']);
        $t->same('/OEBPS/audio/missing.mp3', $manifestReport['itemDiagnostics'][0]['part']);
        $t->same($manifestReport['itemDiagnostics'][0], $manifestReport['diagnostics'][0]);
        $t->same(false, $assetById['missing-audio']['exists']);
        $t->same(null, $assetById['missing-audio']['byteLength']);
        $t->same(null, $assetById['missing-audio']['crc32']);
        $t->same('missing-non-spine-manifest-resource', $assetById['missing-audio']['diagnostics'][0]['type']);
        $t->same(2, count($result['document']->children));
        $t->contains('Review appendix', $result['document']->children[1]->attr('html'));
    },
    'rejects missing spine package resources before manifest review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithMissingSpineContent = str_replace(
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-2" href="text/missing-spine.xhtml" media-type="application/xhtml+xml"/>',
            $opfXml
        );

        $thrown = false;
        try {
            (new EpubReader())->readPackage($buildEpubPackage($opfWithMissingSpineContent));
        } catch (RuntimeException $exception) {
            $thrown = true;
            $t->contains('EPUB spine item is missing from the package: /OEBPS/text/missing-spine.xhtml', $exception->getMessage());
        }

        $t->same(true, $thrown);
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

        $manifestReport = $result['importReport']['manifest'];
        $t->same(0, $manifestReport['missingItemCount']);
        $t->same([], $manifestReport['missingItems']);
        $t->same(1, $manifestReport['externalItemCount']);
        $t->same(1, count($manifestReport['externalItems']));
        $t->same('remote-audio', $manifestReport['externalItems'][0]['id']);
        $t->same('https://cdn.example.test/audio/source-note.mp3', $manifestReport['externalItems'][0]['href']);
        $t->same('external-manifest-resource', $manifestReport['itemDiagnostics'][0]['type']);
        $t->same('remote-audio', $manifestReport['itemDiagnostics'][0]['id']);
        $t->same(null, $manifestReport['itemDiagnostics'][0]['part']);

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
    'reports OPF manifest resource byte provenance for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $chapter1Xhtml, $encryptionXml): void {
        $opfWithManifestResources = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>'
            . '<item id="remote-audio" href="https://cdn.example.test/audio/source-note.mp3" media-type="audio/mpeg"/>'
            . '<item id="missing-audio" href="audio/missing.mp3" media-type="audio/mpeg"/>'
            . '<item id="font-main" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithManifestResources,
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

        $t->same(hash('sha256', $chapter1Xhtml), $manifestById['chapter-1']['byteSha256']);
        $t->same(strlen($chapter1Xhtml), $manifestById['chapter-1']['byteLength']);
        $t->same(hash('crc32b', $chapter1Xhtml), $manifestById['chapter-1']['crc32']);
        $t->same(hash('sha256', 'body { color: #222; }'), $manifestById['style']['byteSha256']);
        $t->same(hash('sha256', 'PNGDATA'), $manifestById['cover-image']['byteSha256']);
        $t->same(null, $manifestById['remote-audio']['byteSha256']);
        $t->same(null, $manifestById['missing-audio']['byteSha256']);
        $t->same(true, $manifestById['font-main']['encrypted']);
        $t->same(false, $manifestById['font-main']['canExposeBytes']);
        $t->same(null, $manifestById['font-main']['byteSha256']);

        $provenance = $result['importReport']['manifest']['byteProvenance'];
        $t->same(true, $provenance['present']);
        $t->same(9, $provenance['itemCount']);
        $t->same(6, $provenance['hashedItemCount']);
        $t->same(1, $provenance['encryptedItemCount']);
        $t->same(1, $provenance['missingItemCount']);
        $t->same(1, $provenance['externalItemCount']);
        $t->same(hash('sha256', $chapter1Xhtml), $provenance['itemsById']['chapter-1']['byteSha256']);
        $t->same(null, $provenance['itemsById']['remote-audio']['byteSha256']);
        $t->same(null, $provenance['itemsById']['missing-audio']['byteSha256']);
        $t->same(null, $provenance['itemsById']['font-main']['byteSha256']);
        $t->same('/OEBPS/fonts/source.otf', $provenance['encryptedItems'][0]['part']);
        $t->same('/OEBPS/audio/missing.mp3', $provenance['missingItems'][0]['part']);
        $t->same('https://cdn.example.test/audio/source-note.mp3', $provenance['externalItems'][0]['target']);
        $t->same($provenance['itemsById']['chapter-1'], $provenance['itemsByPart']['/OEBPS/text/chapter1.xhtml']);
        $t->same($result['manifest'], $result['importReport']['manifest']['items']);
    },
    'reports duplicate OPF manifest package parts for import preflight' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithDuplicateTargets = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="chapter-review-copy" href="text/chapter1.xhtml#wp-review" media-type="application/xhtml+xml"/>'
            . '<item id="cover-review-copy" href="images/cover.png?review=1" media-type="image/png"/>'
            . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithDuplicateTargets));
        $manifestReport = $result['importReport']['manifest'];
        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }
        $itemDiagnosticsById = [];
        foreach ($manifestReport['itemDiagnostics'] as $diagnostic) {
            $itemDiagnosticsById[$diagnostic['id']] = $diagnostic;
        }

        $t->same(8, $manifestReport['count']);
        $t->same(2, $manifestReport['duplicatePackagePartCount']);
        $t->same(4, $manifestReport['duplicatePackageItemCount']);
        $t->same(['/OEBPS/images/cover.png', '/OEBPS/text/chapter1.xhtml'], $manifestReport['duplicatePackageParts']);
        $t->same('/OEBPS/images/cover.png', $manifestReport['duplicatePackagePartItems'][0]['part']);
        $t->same(['cover-image', 'cover-review-copy'], $manifestReport['duplicatePackagePartItems'][0]['ids']);
        $t->same(['images/cover.png', 'images/cover.png?review=1'], $manifestReport['duplicatePackagePartItems'][0]['hrefs']);
        $t->same('/OEBPS/text/chapter1.xhtml', $manifestReport['duplicatePackagePartItems'][1]['part']);
        $t->same(['chapter-1', 'chapter-review-copy'], $manifestReport['duplicatePackagePartItems'][1]['ids']);
        $t->same(['text/chapter1.xhtml', 'text/chapter1.xhtml#wp-review'], $manifestReport['duplicatePackagePartItems'][1]['hrefs']);
        $t->same('duplicate-manifest-package-part', $manifestReport['diagnostics'][0]['type']);
        $t->same('/OEBPS/images/cover.png', $manifestReport['diagnostics'][0]['part']);
        $t->same(4, $manifestReport['itemDiagnosticCount']);
        $t->same('duplicate-manifest-package-part', $itemDiagnosticsById['cover-image']['type']);
        $t->same('/OEBPS/images/cover.png', $itemDiagnosticsById['cover-image']['part']);
        $t->same(['cover-image', 'cover-review-copy'], $itemDiagnosticsById['cover-image']['ids']);
        $t->same('duplicate-manifest-package-part', $manifestById['chapter-1']['diagnostics'][0]['type']);
        $t->same(['chapter-1', 'chapter-review-copy'], $manifestById['chapter-review-copy']['duplicatePackagePartIds']);
        $t->same(true, $manifestById['cover-review-copy']['duplicatePackagePart']);
        $t->same([], $manifestReport['missingItems']);
        $t->same(2, count($result['document']->children));
        $t->contains('Chapter XHTML stays available', $result['document']->children[0]->attr('html'));
    },
    'classifies OPF manifest core media and foreign resource fallback coverage' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $widgetFallbackXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head><title>Widget review fallback</title></head>
  <body><p>Custom widget fallback stays reviewable.</p></body>
</html>
XML;
        $opfWithMediaTypes = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="poster-heic" href="images/poster.heic" media-type="image/heic"/>'
            . '<item id="poster-heic-fallback" href="images/poster-fallback.heic" media-type="image/heic" fallback="cover-image"/>'
            . '<item id="review-video" href="video/review.mp4" media-type="video/mp4"/>'
            . '<item id="custom-widget" href="widgets/review.bin" media-type="application/x-review-widget"/>'
            . '<item id="widget-handler" href="text/widget-fallback.xhtml" media-type="application/xhtml+xml"/>'
            . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );
        $opfWithMediaTypes = str_replace(
            '</package>',
            '<bindings><mediaType media-type="application/x-review-widget" handler="widget-handler"/></bindings></package>',
            $opfWithMediaTypes
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithMediaTypes,
            null,
            [
                ['name' => 'OEBPS/images/poster.heic', 'data' => 'HEIC-POSTER'],
                ['name' => 'OEBPS/images/poster-fallback.heic', 'data' => 'HEIC-FALLBACK'],
                ['name' => 'OEBPS/video/review.mp4', 'data' => 'MP4-REVIEW'],
                ['name' => 'OEBPS/widgets/review.bin', 'data' => 'WIDGET-BYTES'],
                ['name' => 'OEBPS/text/widget-fallback.xhtml', 'data' => $widgetFallbackXhtml],
            ]
        ));

        $mediaTypes = $result['mediaTypes'];
        $itemsById = $mediaTypes['itemsById'];

        $t->same(11, $mediaTypes['manifestItemCount']);
        $t->same(7, $mediaTypes['coreMediaTypeCount']);
        $t->same(3, $mediaTypes['foreignResourceCount']);
        $t->same(1, $mediaTypes['exemptResourceCount']);
        $t->same(4, $mediaTypes['epubContentDocumentCount']);
        $t->same(1, $mediaTypes['manifestFallbackCount']);
        $t->same(1, $mediaTypes['bindingHandledCount']);
        $t->same(1, $mediaTypes['foreignResourceWithoutFallbackCount']);
        $t->same(1, $mediaTypes['reviewRequiredCount']);
        $t->same('foreign-resource-without-fallback', $mediaTypes['diagnostics'][0]['type']);
        $t->same('poster-heic', $mediaTypes['diagnostics'][0]['id']);

        $t->same(true, $itemsById['cover-image']['coreMediaType']);
        $t->same('image', $itemsById['cover-image']['coreMediaTypeKind']);
        $t->same(false, $itemsById['cover-image']['foreignResource']);
        $t->same(false, $itemsById['cover-image']['reviewRequired']);

        $t->same(false, $itemsById['poster-heic']['coreMediaType']);
        $t->same(true, $itemsById['poster-heic']['foreignResource']);
        $t->same(false, $itemsById['poster-heic']['hasManifestFallback']);
        $t->same(false, $itemsById['poster-heic']['bindingHandled']);
        $t->same(true, $itemsById['poster-heic']['reviewRequired']);
        $t->same(['foreign-resource-without-fallback'], $itemsById['poster-heic']['reviewFlags']);

        $t->same(true, $itemsById['poster-heic-fallback']['foreignResource']);
        $t->same(true, $itemsById['poster-heic-fallback']['hasManifestFallback']);
        $t->same('cover-image', $itemsById['poster-heic-fallback']['fallbackId']);
        $t->same('manifest-fallback', $itemsById['poster-heic-fallback']['fallbackCoverage']);
        $t->same(false, $itemsById['poster-heic-fallback']['reviewRequired']);

        $t->same(false, $itemsById['review-video']['coreMediaType']);
        $t->same(true, $itemsById['review-video']['exemptResource']);
        $t->same('video', $itemsById['review-video']['exemptReason']);
        $t->same('exempt-resource', $itemsById['review-video']['fallbackCoverage']);
        $t->same(false, $itemsById['review-video']['reviewRequired']);

        $t->same(true, $itemsById['custom-widget']['foreignResource']);
        $t->same(true, $itemsById['custom-widget']['bindingHandled']);
        $t->same('widget-handler', $itemsById['custom-widget']['bindingHandlerId']);
        $t->same('binding-handler', $itemsById['custom-widget']['fallbackCoverage']);
        $t->same(false, $itemsById['custom-widget']['reviewRequired']);

        $t->same(true, $itemsById['chapter-1']['epubContentDocument']);
        $t->same(false, $itemsById['chapter-1']['requiresSpineFallbackWhenDirect']);
        $t->same(true, $itemsById['style']['requiresSpineFallbackWhenDirect']);
        $t->same($mediaTypes, $result['importReport']['mediaTypes']);
        $t->same($mediaTypes, $result['document']->attr('mediaTypes'));
    },
    'reports malformed OPF manifest media types for package preflight' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithManifestMediaTypes = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="opus-audio" href="audio/chapter.ogg" media-type="audio/ogg; codecs=opus"/>'
            . '<item id="bad-media" href="data/bad.bin" media-type="application /x-review"/>'
            . '<item id="bad-param" href="data/bad-param.bin" media-type="application/x-review; profile"/>'
            . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithManifestMediaTypes,
            null,
            [
                ['name' => 'OEBPS/audio/chapter.ogg', 'data' => 'OGG-OPUS-AUDIO'],
                ['name' => 'OEBPS/data/bad.bin', 'data' => 'BAD-MEDIA-TYPE'],
                ['name' => 'OEBPS/data/bad-param.bin', 'data' => 'BAD-MEDIA-PARAM'],
            ]
        ));

        $mediaTypes = $result['mediaTypes'];
        $itemsById = $mediaTypes['itemsById'];
        $assetById = [];
        foreach ($result['assets'] as $asset) {
            $assetById[$asset['id']] = $asset;
        }

        $t->same(9, $mediaTypes['manifestItemCount']);
        $t->same(7, $mediaTypes['coreMediaTypeCount']);
        $t->same(2, $mediaTypes['foreignResourceCount']);
        $t->same(2, $mediaTypes['invalidMediaTypeCount']);
        $t->same(2, $mediaTypes['reviewRequiredCount']);
        $t->same(4, count($mediaTypes['diagnostics']));

        $t->same(true, $itemsById['opus-audio']['mediaTypeSyntaxValid']);
        $t->same('audio/ogg; codecs=opus', $itemsById['opus-audio']['normalizedMediaType']);
        $t->same('audio/ogg', $itemsById['opus-audio']['baseMediaType']);
        $t->same(['codecs' => 'opus'], $itemsById['opus-audio']['mediaTypeParameters']);
        $t->same(true, $itemsById['opus-audio']['coreMediaType']);
        $t->same('audio', $itemsById['opus-audio']['coreMediaTypeKind']);
        $t->same([], $itemsById['opus-audio']['diagnostics']);

        $badMedia = $itemsById['bad-media'];
        $t->same(false, $badMedia['mediaTypeSyntaxValid']);
        $t->same('application /x-review', $badMedia['baseMediaType']);
        $t->same(true, $badMedia['foreignResource']);
        $t->same(true, $badMedia['reviewRequired']);
        $t->same(['invalid-media-type', 'foreign-resource-without-fallback'], $badMedia['reviewFlags']);
        $t->same('invalid-manifest-media-type', $badMedia['diagnostics'][0]['type']);
        $t->same('application /x-review', $badMedia['diagnostics'][0]['mediaType']);
        $t->same('foreign-resource-without-fallback', $badMedia['diagnostics'][1]['type']);

        $badParam = $itemsById['bad-param'];
        $t->same(false, $badParam['mediaTypeSyntaxValid']);
        $t->same('application/x-review', $badParam['baseMediaType']);
        $t->same([], $badParam['mediaTypeParameters']);
        $t->same(['invalid-media-type', 'foreign-resource-without-fallback'], $badParam['reviewFlags']);
        $t->same('invalid-manifest-media-type-parameter', $badParam['diagnostics'][0]['type']);
        $t->same('profile', $badParam['diagnostics'][0]['parameter']);
        $t->same('foreign-resource-without-fallback', $badParam['diagnostics'][1]['type']);

        $t->same('bad-media', $mediaTypes['diagnostics'][0]['id']);
        $t->same('invalid-manifest-media-type', $mediaTypes['diagnostics'][0]['type']);
        $t->same('bad-param', $mediaTypes['diagnostics'][2]['id']);
        $t->same('invalid-manifest-media-type-parameter', $mediaTypes['diagnostics'][2]['type']);
        $t->same($badMedia['diagnostics'], $assetById['bad-media']['mediaTypeDiagnostics']);
        $t->same($badParam['reviewFlags'], $assetById['bad-param']['mediaTypeReviewFlags']);
        $t->same($mediaTypes, $result['importReport']['mediaTypes']);
        $t->same($mediaTypes, $result['document']->attr('mediaTypes'));
    },
    'reports invalid OPF manifest fallback chains in media type preflight' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $fallbackXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Fallback content remains reviewable.</p></body></html>
XML;
        $opfWithFallbackChains = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="custom-ok" href="widgets/custom-ok.bin" media-type="application/x-review-widget" fallback="ok-fallback"/>'
            . '<item id="ok-fallback" href="text/ok-fallback.xhtml" media-type="application/xhtml+xml"/>'
            . '<item id="custom-missing" href="widgets/custom-missing.bin" media-type="application/x-review-widget" fallback="missing-fallback"/>'
            . '<item id="custom-cycle" href="widgets/custom-cycle.bin" media-type="application/x-review-widget" fallback="cycle-b"/>'
            . '<item id="cycle-b" href="widgets/cycle-b.bin" media-type="application/x-review-widget" fallback="custom-cycle"/>'
            . '<item id="custom-unsupported" href="widgets/custom-unsupported.bin" media-type="application/x-review-widget" fallback="unsupported-terminal"/>'
            . '<item id="unsupported-terminal" href="widgets/unsupported-terminal.bin" media-type="application/x-unsupported-terminal"/>'
            . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithFallbackChains,
            null,
            [
                ['name' => 'OEBPS/widgets/custom-ok.bin', 'data' => 'CUSTOM-OK'],
                ['name' => 'OEBPS/text/ok-fallback.xhtml', 'data' => $fallbackXhtml],
                ['name' => 'OEBPS/widgets/custom-missing.bin', 'data' => 'CUSTOM-MISSING'],
                ['name' => 'OEBPS/widgets/custom-cycle.bin', 'data' => 'CUSTOM-CYCLE'],
                ['name' => 'OEBPS/widgets/cycle-b.bin', 'data' => 'CYCLE-B'],
                ['name' => 'OEBPS/widgets/custom-unsupported.bin', 'data' => 'CUSTOM-UNSUPPORTED'],
                ['name' => 'OEBPS/widgets/unsupported-terminal.bin', 'data' => 'UNSUPPORTED-TERMINAL'],
            ]
        ));

        $mediaTypes = $result['mediaTypes'];
        $itemsById = $mediaTypes['itemsById'];
        $assetById = [];
        foreach ($result['assets'] as $asset) {
            $assetById[$asset['id']] = $asset;
        }

        $ok = $itemsById['custom-ok'];
        $t->same('manifest-fallback', $ok['fallbackCoverage']);
        $t->same(true, $ok['fallbackResolved']);
        $t->same(true, $ok['fallbackUsable']);
        $t->same('ok-fallback', $ok['fallbackTerminalId']);
        $t->same('application/xhtml+xml', $ok['fallbackTerminalMediaType']);
        $t->same(true, $ok['fallbackTerminalCoreMediaType']);
        $t->same(true, $ok['fallbackTerminalEpubContentDocument']);
        $t->same(['ok-fallback'], array_map(static fn (array $item): string => $item['id'], $ok['fallbackChain']));
        $t->same(false, $ok['reviewRequired']);
        $t->same([], $ok['diagnostics']);

        $missing = $itemsById['custom-missing'];
        $t->same('invalid-manifest-fallback', $missing['fallbackCoverage']);
        $t->same(false, $missing['fallbackResolved']);
        $t->same(false, $missing['fallbackUsable']);
        $t->same([], $missing['fallbackChain']);
        $t->same(null, $missing['fallbackTerminalId']);
        $t->same(true, $missing['reviewRequired']);
        $t->same(['unresolved-manifest-fallback', 'foreign-resource-without-fallback'], $missing['reviewFlags']);
        $t->same(['missing-manifest-fallback-item', 'foreign-resource-without-fallback'], array_column($missing['diagnostics'], 'type'));
        $t->same('missing-fallback', $missing['diagnostics'][0]['fallback']);

        $cycle = $itemsById['custom-cycle'];
        $t->same('invalid-manifest-fallback', $cycle['fallbackCoverage']);
        $t->same(false, $cycle['fallbackResolved']);
        $t->same(false, $cycle['fallbackUsable']);
        $t->same(['cycle-b'], array_map(static fn (array $item): string => $item['id'], $cycle['fallbackChain']));
        $t->same('cycle-b', $cycle['fallbackTerminalId']);
        $t->same(['cyclic-manifest-fallback', 'foreign-resource-without-fallback'], $cycle['reviewFlags']);
        $t->same(['cyclic-manifest-fallback-chain', 'foreign-resource-without-fallback'], array_column($cycle['diagnostics'], 'type'));
        $t->same('custom-cycle', $cycle['diagnostics'][0]['fallback']);

        $unsupported = $itemsById['custom-unsupported'];
        $t->same('invalid-manifest-fallback', $unsupported['fallbackCoverage']);
        $t->same(false, $unsupported['fallbackResolved']);
        $t->same(false, $unsupported['fallbackUsable']);
        $t->same('unsupported-terminal', $unsupported['fallbackTerminalId']);
        $t->same('application/x-unsupported-terminal', $unsupported['fallbackTerminalMediaType']);
        $t->same(false, $unsupported['fallbackTerminalCoreMediaType']);
        $t->same(false, $unsupported['fallbackTerminalEpubContentDocument']);
        $t->same(false, $unsupported['fallbackTerminalExemptResource']);
        $t->same(['unsupported-terminal'], array_map(static fn (array $item): string => $item['id'], $unsupported['fallbackChain']));
        $t->same(['unsupported-manifest-fallback', 'foreign-resource-without-fallback'], $unsupported['reviewFlags']);
        $t->same(['unsupported-manifest-fallback-terminal', 'foreign-resource-without-fallback'], array_column($unsupported['diagnostics'], 'type'));
        $t->same('unsupported-terminal', $unsupported['diagnostics'][0]['terminalId']);

        $t->same($missing['diagnostics'], $assetById['custom-missing']['mediaTypeDiagnostics']);
        $t->same($cycle['reviewFlags'], $assetById['custom-cycle']['mediaTypeReviewFlags']);
        $t->same($unsupported['fallbackChain'], $assetById['custom-unsupported']['mediaTypeReport']['fallbackChain']);
        $t->same($mediaTypes, $result['importReport']['mediaTypes']);
        $t->same($mediaTypes, $result['document']->attr('mediaTypes'));
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
    'preserves OPF metadata link title provenance for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $reviewRecordBytes = '{"@context":"https://schema.org","name":"Titled publication record"}';
        $chapterRecordBytes = '{"@context":"https://schema.org","name":"Titled chapter record"}';
        $opfWithTitledMetadataLinks = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref id="chapter-spine" idref="chapter-1"/>',
            $opfXml
        );
        $opfWithTitledMetadataLinks = str_replace(
            '</metadata>',
            '<link id="review-record" title="Publication review packet" rel="record alternate" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org reviewer" hreflang="en"/>'
            . '<link id="chapter-review" title="Chapter reviewer packet" rel="record preview" refines="#chapter-spine" href="meta/chapter-review.json" media-type="application/ld+json"/>'
            . '</metadata>',
            $opfWithTitledMetadataLinks
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithTitledMetadataLinks,
            null,
            [
                ['name' => 'OEBPS/meta/review-record.json', 'data' => $reviewRecordBytes],
                ['name' => 'OEBPS/meta/chapter-review.json', 'data' => $chapterRecordBytes],
            ],
        ));

        $links = $result['metadata']['links'];
        $targetReport = $result['metadata']['linkTargetReport'];
        $spineLinkedResources = $result['spine'][0]['linkedResources'];

        $t->same('Publication review packet', $links[0]['title']);
        $t->same('Chapter reviewer packet', $links[1]['title']);
        $t->same('Publication review packet', $result['metadata']['raw'][7]['title']);
        $t->same('Chapter reviewer packet', $result['metadata']['raw'][8]['title']);
        $t->same('Publication review packet', $result['metadata']['linksByRel']['alternate'][0]['title']);
        $t->same('Chapter reviewer packet', $result['metadata']['linksByRefinedId']['chapter-spine'][0]['title']);
        $t->same('Chapter reviewer packet', $spineLinkedResources[0]['title']);
        $t->same('Chapter reviewer packet', $result['document']->children[0]->attr('linkedResources')[0]['title']);
        $t->same('Publication review packet', $targetReport['publicationItems'][0]['title']);
        $t->same('Chapter reviewer packet', $targetReport['refinedItems'][0]['title']);
        $t->same('Publication review packet', $targetReport['itemsByRel']['alternate'][0]['title']);
        $t->same($targetReport, $result['importReport']['metadata']['linkTargetReport']);
        $t->same($links, $result['document']->attr('metadata')['links']);
    },
    'reports OPF metadata link target policy for publication resources' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $reviewRecordBytes = '{"@context":"https://schema.org","name":"Publication review record"}';
        $manifestRecordBytes = '{"@context":"https://schema.org","name":"Manifested publication record"}';
        $opfWithMetadataLinks = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="publication-record" href="meta/publication-record.json" media-type="application/ld+json"/>'
            . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );
        $opfWithMetadataLinks = str_replace(
            '</metadata>',
            '<link id="review-record" rel="record alternate" href="meta/review-record.json" media-type="application/ld+json" properties="schema-org reviewer"/>'
            . '<link id="manifested-record" rel="record" href="meta/publication-record.json" media-type="application/ld+json"/>'
            . '<link id="remote-onix" rel="record" href="https://metadata.example.test/onix/source.xml" media-type="application/xml"/>'
            . '<link id="missing-record" rel="record" href="meta/missing-record.json" media-type="application/json"/>'
            . '<link id="creator-voicing" rel="voicing" refines="#creator" href="audio/creator-name.mp3" media-type="audio/mpeg"/>'
            . '</metadata>',
            $opfWithMetadataLinks
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithMetadataLinks,
            null,
            [
                ['name' => 'OEBPS/meta/review-record.json', 'data' => $reviewRecordBytes],
                ['name' => 'OEBPS/meta/publication-record.json', 'data' => $manifestRecordBytes],
            ]
        ));

        $report = $result['metadata']['linkTargetReport'];
        $t->same(true, $report['present']);
        $t->same(5, $report['linkCount']);
        $t->same(4, $report['publicationLinkCount']);
        $t->same(1, $report['refinedLinkCount']);
        $t->same(4, $report['localLinkCount']);
        $t->same(2, $report['existingLocalLinkCount']);
        $t->same(1, $report['manifestLinkCount']);
        $t->same(1, $report['unmanifestedLocalLinkCount']);
        $t->same(1, $report['externalLinkCount']);
        $t->same(2, $report['missingLinkCount']);
        $t->same(2, $report['byteExposedLinkCount']);
        $t->same(4, $report['diagnosticCount']);
        $t->same(4, $report['rels']['record']);
        $t->same(1, $report['rels']['alternate']);
        $t->same(1, $report['rels']['voicing']);

        $publication = $report['publicationItems'];
        $t->same(4, count($publication));
        $t->same('review-record', $publication[0]['id']);
        $t->same('publication', $publication[0]['scope']);
        $t->same('/OEBPS/meta/review-record.json', $publication[0]['part']);
        $t->same(true, $publication[0]['exists']);
        $t->same(null, $publication[0]['manifestId']);
        $t->same(hash('sha256', $reviewRecordBytes), $publication[0]['byteSha256']);
        $t->same('unmanifested-publication-metadata-link', $publication[0]['diagnostics'][0]['type']);

        $t->same('manifested-record', $publication[1]['id']);
        $t->same('publication-record', $publication[1]['manifestId']);
        $t->same('/OEBPS/meta/publication-record.json', $publication[1]['part']);
        $t->same(hash('sha256', $manifestRecordBytes), $publication[1]['byteSha256']);
        $t->same([], $publication[1]['diagnostics']);

        $t->same('remote-onix', $publication[2]['id']);
        $t->same(true, $publication[2]['external']);
        $t->same(null, $publication[2]['part']);
        $t->same('external-publication-metadata-link', $publication[2]['diagnostics'][0]['type']);

        $t->same('missing-record', $publication[3]['id']);
        $t->same('/OEBPS/meta/missing-record.json', $publication[3]['part']);
        $t->same(false, $publication[3]['exists']);
        $t->same('missing-publication-metadata-link', $publication[3]['diagnostics'][0]['type']);

        $refined = $report['refinedItems'][0];
        $t->same('creator-voicing', $refined['id']);
        $t->same('refined-subject', $refined['scope']);
        $t->same('creator', $refined['subjectId']);
        $t->same('/OEBPS/audio/creator-name.mp3', $refined['part']);
        $t->same('missing-refined-metadata-link', $refined['diagnostics'][0]['type']);

        $t->same(['unmanifested-publication-metadata-link', 'external-publication-metadata-link', 'missing-publication-metadata-link', 'missing-refined-metadata-link'], array_column($report['diagnostics'], 'type'));
        $t->same(4, count($report['itemsByRel']['record']));
        $t->same('manifested-record', $report['itemsByRel']['record'][1]['id']);
        $t->same(1, count($report['itemsByRel']['voicing']));
        $t->same($report, $result['importReport']['metadata']['linkTargetReport']);
        $t->same($report, $result['document']->attr('metadata')['linkTargetReport']);
    },
    'reports OPF metadata link vocabulary tokens for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $reviewRecordBytes = '{"@context":"https://schema.org","name":"Vocabulary review record"}';
        $opfWithLinkVocabulary = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en" prefix="review: https://example.invalid/epub-review#">',
            $opfXml
        );
        $opfWithLinkVocabulary = str_replace(
            '</metadata>',
            '<link id="review-record" rel="record schema:associatedMedia https://example.invalid/link-rel#review bad/rel record unknown:rel" href="meta/vocabulary-review.json" media-type="application/ld+json" properties="schema-org review:packet https://example.invalid/link-property#review bad/property schema-org unknown:flag"/>'
            . '</metadata>',
            $opfWithLinkVocabulary
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithLinkVocabulary,
            null,
            [
                ['name' => 'OEBPS/meta/vocabulary-review.json', 'data' => $reviewRecordBytes],
            ]
        ));

        $link = $result['metadata']['links'][0];
        $relVocabulary = $link['relVocabulary'];
        $propertyVocabulary = $link['propertyVocabulary'];

        $t->same('review-record', $link['id']);
        $t->same('/OEBPS/meta/vocabulary-review.json', $link['target']);
        $t->same(hash('sha256', $reviewRecordBytes), $link['byteSha256']);
        $t->same(6, $relVocabulary['count']);
        $t->same(5, $relVocabulary['validCount']);
        $t->same(1, $relVocabulary['invalidCount']);
        $t->same(1, $relVocabulary['resolvedCount']);
        $t->same(1, $relVocabulary['absoluteUrlCount']);
        $t->same(1, $relVocabulary['duplicateCount']);
        $t->same('nmtoken', $relVocabulary['items'][0]['kind']);
        $t->same('prefixed-nmtoken', $relVocabulary['items'][1]['kind']);
        $t->same('schema', $relVocabulary['items'][1]['prefix']);
        $t->same('associatedMedia', $relVocabulary['items'][1]['localName']);
        $t->same('http://schema.org/associatedMedia', $relVocabulary['items'][1]['iri']);
        $t->same(true, $relVocabulary['items'][1]['resolved']);
        $t->same('absolute-url-with-fragment', $relVocabulary['items'][2]['kind']);
        $t->same('https://example.invalid/link-rel#review', $relVocabulary['items'][2]['iri']);
        $t->same(false, $relVocabulary['items'][3]['valid']);
        $t->same('invalid-metadata-link-rel-token', $relVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-metadata-link-rel-token', $relVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same(0, $relVocabulary['items'][4]['diagnostics'][0]['previousIndex']);
        $t->same('unknown-metadata-link-rel-prefix', $relVocabulary['items'][5]['diagnostics'][0]['type']);
        $t->same('unknown', $relVocabulary['items'][5]['diagnostics'][0]['prefix']);

        $t->same(6, $propertyVocabulary['count']);
        $t->same(5, $propertyVocabulary['validCount']);
        $t->same(1, $propertyVocabulary['invalidCount']);
        $t->same(1, $propertyVocabulary['resolvedCount']);
        $t->same(1, $propertyVocabulary['absoluteUrlCount']);
        $t->same(1, $propertyVocabulary['duplicateCount']);
        $t->same('schema-org', $propertyVocabulary['items'][0]['value']);
        $t->same('https://example.invalid/epub-review#packet', $propertyVocabulary['items'][1]['iri']);
        $t->same('absolute-url-with-fragment', $propertyVocabulary['items'][2]['kind']);
        $t->same('invalid-metadata-link-properties-token', $propertyVocabulary['items'][3]['diagnostics'][0]['type']);
        $t->same('duplicate-metadata-link-properties-token', $propertyVocabulary['items'][4]['diagnostics'][0]['type']);
        $t->same('unknown-metadata-link-properties-prefix', $propertyVocabulary['items'][5]['diagnostics'][0]['type']);

        $summary = $result['metadata']['linkVocabulary'];
        $t->same(true, $summary['present']);
        $t->same(1, $summary['linkCount']);
        $t->same(6, $summary['relTokenCount']);
        $t->same(6, $summary['propertyTokenCount']);
        $t->same(2, $summary['resolvedTokenCount']);
        $t->same(2, $summary['absoluteUrlTokenCount']);
        $t->same(2, $summary['duplicateTokenCount']);
        $t->same(6, $summary['diagnosticCount']);
        $t->same(2, $summary['rels']['record']);
        $t->same(1, $summary['properties']['review:packet']);
        $t->same('invalid-metadata-link-rel-token', $summary['diagnostics'][0]['type']);
        $t->same('duplicate-metadata-link-rel-token', $summary['diagnostics'][1]['type']);
        $t->same('unknown-metadata-link-rel-prefix', $summary['diagnostics'][2]['type']);
        $t->same('invalid-metadata-link-properties-token', $summary['diagnostics'][3]['type']);
        $t->same('duplicate-metadata-link-properties-token', $summary['diagnostics'][4]['type']);
        $t->same('unknown-metadata-link-properties-prefix', $summary['diagnostics'][5]['type']);
        $t->same($summary, $result['importReport']['metadata']['linkVocabulary']);
        $t->same($summary, $result['document']->attr('metadata')['linkVocabulary']);
    },
    'attaches OPF metadata link refines records to package review subjects' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $identifierRecord = '{"@context":"https://schema.org","identifier":"urn:uuid:wp-epub-source-42"}';
        $packageRecord = '{"@context":"https://schema.org","name":"Source package"}';
        $chapterRecord = '{"@context":"https://schema.org","name":"Chapter one"}';
        $creatorVoicing = 'MP3-CREATOR-NAME';
        $opfWithLinkRefinements = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" id="package-record" version="3.0" unique-identifier="pub-id" xml:lang="en">',
            $opfXml
        );
        $opfWithLinkRefinements = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref id="chapter-entry" idref="chapter-1"/>',
            $opfWithLinkRefinements
        );
        $opfWithLinkRefinements = str_replace(
            '</metadata>',
            '<link id="identifier-record" rel="record" refines="#pub-id" href="meta/identifier.json" media-type="application/ld+json" properties="schema-org"/>'
            . '<link id="creator-voicing" rel="voicing" refines="#creator" href="audio/creator-name.mp3" media-type="audio/mpeg"/>'
            . '<link id="package-review-record" rel="record" refines="#package-record" href="meta/package.json" media-type="application/ld+json"/>'
            . '<link id="chapter-review-record" rel="record" refines="#chapter-1" href="meta/chapter.json" media-type="application/ld+json"/>'
            . '<link id="spine-preview" rel="preview" refines="#chapter-entry" href="text/chapter1.xhtml#intro" media-type="application/xhtml+xml"/>'
            . '</metadata>',
            $opfWithLinkRefinements
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithLinkRefinements,
            null,
            [
                ['name' => 'OEBPS/meta/identifier.json', 'data' => $identifierRecord],
                ['name' => 'OEBPS/meta/package.json', 'data' => $packageRecord],
                ['name' => 'OEBPS/meta/chapter.json', 'data' => $chapterRecord],
                ['name' => 'OEBPS/audio/creator-name.mp3', 'data' => $creatorVoicing],
            ],
        ));
        $metadata = $result['metadata'];
        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }

        $t->same(true, $metadata['linkedResourceSummary']['present']);
        $t->same(5, $metadata['linkedResourceSummary']['linkCount']);
        $t->same(5, $metadata['linkedResourceSummary']['subjectCount']);
        $t->same(['pub-id', 'creator', 'package-record', 'chapter-1', 'chapter-entry'], $metadata['linkedResourceSummary']['subjects']);
        $t->same($metadata['links'][0], $metadata['linksByRefinedId']['pub-id'][0]);
        $t->same('pub-id', $metadata['linksByRefinedId']['pub-id'][0]['subjectId']);
        $t->same('identifier-record', $metadata['dc']['identifier'][0]['linkedResources'][0]['id']);
        $t->same(hash('sha256', $identifierRecord), $metadata['dc']['identifier'][0]['linkedResources'][0]['byteSha256']);
        $t->same('identifier-record', $metadata['uniqueIdentifier']['matchedEntries'][0]['linkedResources'][0]['id']);
        $t->same('creator-voicing', $metadata['dc']['creator'][0]['linkedResources'][0]['id']);
        $t->same(['voicing'], $metadata['creatorDetails'][0]['linkedResources'][0]['rel']);
        $t->same(hash('sha256', $creatorVoicing), $metadata['creatorDetails'][0]['linkedResources'][0]['byteSha256']);
        $t->same('package-review-record', $result['package']['linkedResources'][0]['id']);
        $t->same('/OEBPS/meta/package.json', $result['package']['linkedResources'][0]['part']);
        $t->same('chapter-review-record', $manifestById['chapter-1']['linkedResources'][0]['id']);
        $t->same(hash('sha256', $chapterRecord), $manifestById['chapter-1']['linkedResources'][0]['byteSha256']);
        $t->same('spine-preview', $result['spine'][0]['linkedResources'][0]['id']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $result['spine'][0]['linkedResources'][0]['target']);
        $t->same('intro', $result['spine'][0]['linkedResources'][0]['fragment']);
        $t->same('chapter-1', $result['spine'][0]['linkedResources'][0]['manifestId']);
        $t->same($result['spine'][0]['linkedResources'], $result['document']->children[0]->attr('linkedResources'));
        $t->same($metadata['linksByRefinedId'], $result['importReport']['metadata']['linksByRefinedId']);
        $t->same($metadata['linkedResourceSummary'], $result['document']->attr('metadata')['linkedResourceSummary']);
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
    'reports dangling OPF metadata refinement subjects for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithRefinementSubjects = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" id="package-record" version="3.0" unique-identifier="pub-id" xml:lang="en" prefix="schema: https://schema.org/">',
            $opfXml
        );
        $opfWithRefinementSubjects = str_replace(
            '<spine toc="toc">',
            '<spine id="reading-order" toc="toc">',
            $opfWithRefinementSubjects
        );
        $opfWithRefinementSubjects = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref id="chapter-entry" idref="chapter-1"/>',
            $opfWithRefinementSubjects
        );
        $opfWithRefinementSubjects = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta id="source-rating" property="schema:ratingValue">5</meta>'
            . '<meta refines="#source-rating" property="schema:ratingScale">stars</meta>'
            . '<meta refines="#package-record" property="schema:name">Package review record</meta>'
            . '<meta refines="#chapter-1" property="schema:name">Chapter review record</meta>'
            . '<meta refines="#reading-order" property="schema:position">primary reading order</meta>'
            . '<meta refines="#chapter-entry" property="schema:position">first spine entry</meta>'
            . '<meta refines="#missing-review-subject" property="schema:reviewStatus">needs source audit</meta>',
            $opfWithRefinementSubjects
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithRefinementSubjects));
        $metadata = $result['metadata'];
        $summary = $metadata['refinementSubjectSummary'];

        $t->same(true, $summary['present']);
        $t->same(6, $summary['subjectCount']);
        $t->same(6, $summary['refinementCount']);
        $t->same(5, $summary['knownSubjectCount']);
        $t->same(1, $summary['unknownSubjectCount']);
        $t->same(['source-rating', 'package-record', 'chapter-1', 'reading-order', 'chapter-entry', 'missing-review-subject'], $summary['subjects']);

        $t->same(true, $summary['subjectsById']['source-rating']['known']);
        $t->same('metadata', $summary['subjectsById']['source-rating']['kind']);
        $t->same(['schema:ratingScale'], $summary['subjectsById']['source-rating']['properties']);
        $t->same(true, $summary['subjectsById']['package-record']['known']);
        $t->same('package', $summary['subjectsById']['package-record']['kind']);
        $t->same(true, $summary['subjectsById']['chapter-1']['known']);
        $t->same('manifest', $summary['subjectsById']['chapter-1']['kind']);
        $t->same(true, $summary['subjectsById']['reading-order']['known']);
        $t->same('spine', $summary['subjectsById']['reading-order']['kind']);
        $t->same(true, $summary['subjectsById']['chapter-entry']['known']);
        $t->same('spine-item', $summary['subjectsById']['chapter-entry']['kind']);

        $missing = $summary['subjectsById']['missing-review-subject'];
        $t->same(false, $missing['known']);
        $t->same(null, $missing['kind']);
        $t->same(['schema:reviewStatus'], $missing['properties']);
        $t->same('unknown-metadata-refinement-subject', $missing['diagnostics'][0]['type']);
        $t->same('missing-review-subject', $summary['diagnostics'][0]['subjectId']);
        $t->same('schema:reviewStatus', $summary['diagnostics'][0]['properties'][0]);

        $t->same('needs source audit', $metadata['refinementsById']['missing-review-subject']['schema:reviewStatus'][0]['text']);
        $t->same($summary, $result['importReport']['metadata']['refinementSubjectSummary']);
        $t->same($summary, $result['document']->attr('metadata')['refinementSubjectSummary']);
    },
    'summarizes OPF title-type refinements and direction metadata for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithTitleMetadata = str_replace(
            '<dc:title>WordPress Import EPUB</dc:title>',
            '<dc:title id="main-title" dir="ltr">WordPress Import EPUB</dc:title>'
            . '<dc:title id="subtitle-title" xml:lang="ar-Latn" dir="rtl">Murajaat al-hijra</dc:title>'
            . '<dc:title id="short-title">WP EPUB packet</dc:title>',
            $opfXml
        );
        $opfWithTitleMetadata = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta refines="#main-title" property="title-type">main</meta>'
            . '<meta refines="#main-title" property="file-as">WordPress EPUB source packet</meta>'
            . '<meta refines="#main-title" property="display-seq">1</meta>'
            . '<meta refines="#subtitle-title" property="title-type">subtitle</meta>'
            . '<meta refines="#subtitle-title" property="display-seq">2</meta>'
            . '<meta refines="#subtitle-title" property="alternate-script" xml:lang="en" dir="ltr">Migration review subtitle</meta>'
            . '<meta refines="#short-title" property="title-type">short</meta>',
            $opfWithTitleMetadata
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithTitleMetadata));
        $metadata = $result['metadata'];

        $t->same('WordPress Import EPUB', $metadata['title']);
        $t->same(3, count($metadata['titleDetails']));
        $t->same('WordPress Import EPUB', $metadata['mainTitle']['text']);
        $t->same('main', $metadata['mainTitle']['titleType']);
        $t->same('ltr', $metadata['mainTitle']['direction']);
        $t->same('WordPress EPUB source packet', $metadata['mainTitle']['fileAs']);
        $t->same('1', $metadata['mainTitle']['displaySeq']);

        $t->same('Murajaat al-hijra', $metadata['subtitle']['text']);
        $t->same('subtitle', $metadata['subtitle']['titleType']);
        $t->same('ar-Latn', $metadata['subtitle']['language']);
        $t->same('rtl', $metadata['subtitle']['direction']);
        $t->same('Migration review subtitle', $metadata['subtitle']['alternateScripts'][0]['text']);
        $t->same('en', $metadata['subtitle']['alternateScripts'][0]['language']);
        $t->same('ltr', $metadata['subtitle']['alternateScripts'][0]['direction']);

        $t->same('WP EPUB packet', $metadata['shortTitle']['text']);
        $t->same(['main', 'subtitle', 'short'], array_keys($metadata['titlesByType']));
        $t->same('Murajaat al-hijra', $metadata['titlesByType']['subtitle'][0]['text']);
        $t->same('rtl', $metadata['dc']['title'][1]['direction']);
        $t->same('ltr', $metadata['refinementsById']['subtitle-title']['alternate-script'][0]['direction']);
        $t->same($metadata['titleDetails'], $result['importReport']['metadata']['titleDetails']);
        $t->same($metadata['titlesByType'], $result['document']->attr('metadata')['titlesByType']);
    },
    'inherits OPF metadata language and direction for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithMetadataContext = preg_replace(
            '~<metadata xmlns:dc="http://purl\.org/dc/elements/1\.1/">~',
            '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xml:lang="ar" dir="rtl">',
            $opfXml,
            1
        );
        if (!is_string($opfWithMetadataContext)) {
            throw new RuntimeException('Failed to build EPUB metadata inheritance fixture');
        }
        $opfWithMetadataContext = str_replace(
            '<dc:title>WordPress Import EPUB</dc:title>',
            '<dc:title id="localized-title">WordPress Import EPUB</dc:title>',
            $opfWithMetadataContext
        );
        $opfWithMetadataContext = str_replace(
            '<dc:creator id="creator">Migration Desk</dc:creator>',
            '<dc:creator id="creator" xml:lang="en">Migration Desk</dc:creator>',
            $opfWithMetadataContext
        );
        $opfWithMetadataContext = str_replace(
            '<dc:subject>Data Liberation</dc:subject>',
            '<dc:subject dir="ltr">Data Liberation</dc:subject>',
            $opfWithMetadataContext
        );
        $opfWithMetadataContext = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta refines="#localized-title" property="alternate-script">Migration import title</meta>',
            $opfWithMetadataContext
        );
        $opfWithMetadataContext = str_replace(
            '<meta name="cover" content="cover-image"/>',
            '<meta name="cover" content="cover-image"/>'
            . '<link id="localized-review-record" rel="record" href="meta/localized-review.json" media-type="application/ld+json"/>',
            $opfWithMetadataContext
        );
        $opfWithMetadataContext = str_replace(
            '<collection id="series" role="series" xml:lang="en">',
            '<collection id="series" role="series" xml:lang="en" dir="rtl">',
            $opfWithMetadataContext
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithMetadataContext,
            null,
            [
                ['name' => 'OEBPS/meta/localized-review.json', 'data' => '{"name":"localized review"}'],
            ]
        ));
        $metadata = $result['metadata'];

        $t->same('ar', $metadata['titleDetails'][0]['language']);
        $t->same('rtl', $metadata['titleDetails'][0]['direction']);
        $t->same('ar', $metadata['dc']['title'][0]['language']);
        $t->same('rtl', $metadata['dc']['title'][0]['direction']);
        $t->same('en', $metadata['creatorDetails'][0]['language']);
        $t->same('rtl', $metadata['creatorDetails'][0]['direction']);
        $t->same('ar', $metadata['dc']['subject'][0]['language']);
        $t->same('ltr', $metadata['dc']['subject'][0]['direction']);
        $t->same('ar', $metadata['metaProperties']['dcterms:modified'][0]['language']);
        $t->same('rtl', $metadata['metaProperties']['dcterms:modified'][0]['direction']);
        $t->same('Migration import title', $metadata['titleDetails'][0]['alternateScripts'][0]['text']);
        $t->same('ar', $metadata['titleDetails'][0]['alternateScripts'][0]['language']);
        $t->same('rtl', $metadata['titleDetails'][0]['alternateScripts'][0]['direction']);
        $t->same('ar', $metadata['links'][0]['language']);
        $t->same('rtl', $metadata['links'][0]['direction']);
        $t->same('/OEBPS/meta/localized-review.json', $metadata['links'][0]['target']);

        $series = $result['collections'][0];
        $t->same('en', $series['metadata']['titleDetails'][0]['language']);
        $t->same('rtl', $series['metadata']['titleDetails'][0]['direction']);
        $t->same('en', $series['metadata']['metaProperties']['group-position'][0]['language']);
        $t->same('rtl', $series['metadata']['metaProperties']['group-position'][0]['direction']);
        $t->same($metadata['titleDetails'], $result['importReport']['metadata']['titleDetails']);
        $t->same($series['metadata']['titleDetails'], $result['document']->attr('collections')[0]['metadata']['titleDetails']);
    },
    'reports OPF contributor role metadata for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithContributors = str_replace(
            '<dc:language>en</dc:language>',
            '<dc:contributor id="editor">Review Editor</dc:contributor>'
            . '<dc:contributor id="translator" xml:lang="fr">Translation Desk</dc:contributor>'
            . '<dc:contributor>Untyped Reviewer</dc:contributor>'
            . '<dc:language>en</dc:language>',
            $opfXml
        );
        $opfWithContributors = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta refines="#editor" property="file-as">Editor, Review</meta>'
            . '<meta refines="#editor" property="role" scheme="marc:relators">edt</meta>'
            . '<meta refines="#editor" property="display-seq">2</meta>'
            . '<meta refines="#translator" property="role" scheme="marc:relators">trl</meta>'
            . '<meta refines="#translator" property="alternate-script" xml:lang="ja-Latn">Honyaku desuku</meta>',
            $opfWithContributors
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithContributors));
        $metadata = $result['metadata'];

        $t->same(['Review Editor', 'Translation Desk', 'Untyped Reviewer'], $metadata['contributors']);
        $t->same(3, count($metadata['contributorDetails']));
        $t->same('Review Editor', $metadata['contributorDetails'][0]['text']);
        $t->same('editor', $metadata['contributorDetails'][0]['id']);
        $t->same('Editor, Review', $metadata['contributorDetails'][0]['fileAs']);
        $t->same('2', $metadata['contributorDetails'][0]['displaySeq']);
        $t->same(['edt'], $metadata['contributorDetails'][0]['roleValues']);
        $t->same('edt', $metadata['contributorDetails'][0]['primaryRole']);
        $t->same('marc:relators', $metadata['contributorDetails'][0]['roles'][0]['scheme']);
        $t->same('#editor', $metadata['contributorDetails'][0]['roles'][0]['refines']);
        $t->same($metadata['refinementsById']['editor'], $metadata['contributorDetails'][0]['refinements']);

        $t->same('Translation Desk', $metadata['contributorDetails'][1]['text']);
        $t->same('fr', $metadata['contributorDetails'][1]['language']);
        $t->same(['trl'], $metadata['contributorDetails'][1]['roleValues']);
        $t->same('Honyaku desuku', $metadata['contributorDetails'][1]['alternateScripts'][0]['text']);
        $t->same('ja-Latn', $metadata['contributorDetails'][1]['alternateScripts'][0]['language']);
        $t->same('Translation Desk', $metadata['contributorsByRole']['trl'][0]['text']);

        $t->same('Untyped Reviewer', $metadata['untypedContributors'][0]['text']);
        $t->same([], $metadata['untypedContributors'][0]['roleValues']);
        $t->same('Review Editor', $metadata['contributorsByRole']['edt'][0]['text']);
        $t->same('Migration Desk', $metadata['creatorDetails'][0]['text']);
        $t->same([], $metadata['creatorDetails'][0]['roleValues']);
        $t->same($metadata['contributorDetails'], $result['importReport']['metadata']['contributorDetails']);
        $t->same($metadata['contributorsByRole'], $result['document']->attr('metadata')['contributorsByRole']);
    },
    'reports OPF creator contributor display sequence for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithAgentSequence = str_replace(
            '<dc:creator id="creator">Migration Desk</dc:creator>',
            '<dc:creator id="creator">Migration Desk</dc:creator>'
            . '<dc:creator id="illustrator">Illustration Desk</dc:creator>',
            $opfXml
        );
        $opfWithAgentSequence = str_replace(
            '<dc:language>en</dc:language>',
            '<dc:contributor id="editor">Review Editor</dc:contributor>'
            . '<dc:contributor id="translator" xml:lang="fr">Translation Desk</dc:contributor>'
            . '<dc:contributor>Untyped Reviewer</dc:contributor>'
            . '<dc:language>en</dc:language>',
            $opfWithAgentSequence
        );
        $opfWithAgentSequence = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta refines="#creator" property="role" scheme="marc:relators">aut</meta>'
            . '<meta refines="#creator" property="display-seq">2</meta>'
            . '<meta refines="#creator" property="file-as">Desk, Migration</meta>'
            . '<meta refines="#illustrator" property="role" scheme="marc:relators">ill</meta>'
            . '<meta refines="#illustrator" property="display-seq">appendix</meta>'
            . '<meta refines="#editor" property="role" scheme="marc:relators">edt</meta>'
            . '<meta refines="#editor" property="display-seq">1</meta>'
            . '<meta refines="#translator" property="role" scheme="marc:relators">trl</meta>'
            . '<meta refines="#translator" property="display-seq">3</meta>',
            $opfWithAgentSequence
        );
        $opfWithAgentSequence = str_replace(
            '<meta name="cover" content="cover-image"/>',
            '<meta name="cover" content="cover-image"/>'
            . '<link id="editor-record" rel="record" refines="#editor" href="meta/editor.json" media-type="application/json"/>',
            $opfWithAgentSequence
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithAgentSequence,
            null,
            [
                ['name' => 'OEBPS/meta/editor.json', 'data' => '{"role":"editor"}'],
            ]
        ));
        $metadata = $result['metadata'];
        $agentOrder = $metadata['agentDisplayOrder'];

        $t->same(true, $agentOrder['present']);
        $t->same(5, $agentOrder['count']);
        $t->same(3, $agentOrder['sequencedCount']);
        $t->same(1, $agentOrder['invalidDisplaySeqCount']);
        $t->same(1, $agentOrder['unsequencedCount']);
        $t->same(['Review Editor', 'Migration Desk', 'Translation Desk', 'Illustration Desk', 'Untyped Reviewer'], array_map(
            static fn (array $item): string => $item['text'],
            $agentOrder['items']
        ));
        $t->same('contributor', $agentOrder['items'][0]['kind']);
        $t->same(1, $agentOrder['items'][0]['displaySeqNumber']);
        $t->same('edt', $agentOrder['items'][0]['primaryRole']);
        $t->same('editor-record', $agentOrder['items'][0]['linkedResources'][0]['id']);
        $t->same('creator', $agentOrder['items'][1]['kind']);
        $t->same(2, $agentOrder['items'][1]['displaySeqNumber']);
        $t->same('Desk, Migration', $agentOrder['items'][1]['fileAs']);
        $t->same('trl', $agentOrder['items'][2]['primaryRole']);
        $t->same(null, $agentOrder['items'][3]['displaySeqNumber']);
        $t->same(false, $agentOrder['items'][3]['displaySeqValid']);
        $t->same('appendix', $agentOrder['items'][3]['displaySeq']);
        $t->same('invalid-agent-display-seq', $agentOrder['items'][3]['diagnostics'][0]['type']);
        $t->same(true, $agentOrder['items'][4]['unsequenced']);
        $t->same(2, count($agentOrder['byKind']['creator']));
        $t->same(3, count($agentOrder['byKind']['contributor']));
        $t->same('Review Editor', $agentOrder['byRole']['edt'][0]['text']);
        $t->same('Migration Desk', $agentOrder['byRole']['aut'][0]['text']);
        $t->same('invalid-agent-display-seq', $agentOrder['diagnostics'][0]['type']);
        $t->same('Illustration Desk', $agentOrder['diagnostics'][0]['text']);
        $t->same($agentOrder, $result['importReport']['metadata']['agentDisplayOrder']);
        $t->same($agentOrder, $result['document']->attr('metadata')['agentDisplayOrder']);
    },
    'attaches OPF metadata refinements to package resources and spine itemrefs' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithResourceRefinements = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" id="package-record" version="3.0" unique-identifier="pub-id" xml:lang="en" prefix="schema: https://schema.org/">',
            $opfXml
        );
        $opfWithResourceRefinements = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta refines="#package-record" property="schema:name">Reviewer package record</meta>'
            . '<meta refines="#chapter-1" property="schema:name">Chapter one review resource</meta>'
            . '<meta refines="#style" property="schema:encodingFormat">text/css</meta>'
            . '<meta refines="#reading-order" property="schema:position">default reading order</meta>'
            . '<meta refines="#chapter-entry" property="rendition:viewport">width=768,height=1024</meta>',
            $opfWithResourceRefinements
        );
        $opfWithResourceRefinements = str_replace(
            '<spine toc="toc">',
            '<spine id="reading-order" toc="toc">',
            $opfWithResourceRefinements
        );
        $opfWithResourceRefinements = str_replace(
            '<itemref idref="chapter-1"/>',
            '<itemref id="chapter-entry" idref="chapter-1"/>',
            $opfWithResourceRefinements
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithResourceRefinements));
        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }

        $t->same('package-record', $result['package']['id']);
        $t->same('Reviewer package record', $result['package']['refinements']['schema:name'][0]['text']);
        $t->same('#package-record', $result['package']['refinements']['schema:name'][0]['refines']);
        $t->same('Chapter one review resource', $manifestById['chapter-1']['refinements']['schema:name'][0]['text']);
        $t->same('#chapter-1', $manifestById['chapter-1']['refinements']['schema:name'][0]['refines']);
        $t->same('text/css', $manifestById['style']['refinements']['schema:encodingFormat'][0]['text']);
        $t->same($manifestById['chapter-1']['refinements'], $result['importReport']['manifest']['items'][1]['refinements']);
        $t->same($manifestById['style']['refinements'], $result['importReport']['manifest']['items'][3]['refinements']);

        $t->same('reading-order', $result['spineProperties']['id']);
        $t->same('default reading order', $result['spineProperties']['refinements']['schema:position'][0]['text']);
        $t->same($result['spineProperties'], $result['importReport']['spine']['properties']);
        $t->same($result['spineProperties'], $result['document']->attr('spineProperties'));

        $t->same('chapter-entry', $result['spine'][0]['id']);
        $t->same('chapter-1', $result['spine'][0]['idref']);
        $t->same('width=768,height=1024', $result['spine'][0]['refinements']['rendition:viewport'][0]['text']);
        $t->same('#chapter-entry', $result['spine'][0]['refinements']['rendition:viewport'][0]['refines']);
        $t->same('chapter-entry', $result['document']->children[0]->attr('spineItemId'));
        $t->same($result['spine'][0]['refinements'], $result['document']->children[0]->attr('refinements'));
        $t->same($manifestById['chapter-1']['refinements'], $result['metadata']['refinementsById']['chapter-1']);
        $t->same($result['spine'][0]['refinements'], $result['metadata']['refinementsById']['chapter-entry']);
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
    'resolves OPF manifest property vocabulary terms for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithManifestVocabulary = str_replace(
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en">',
            '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="pub-id" xml:lang="en" prefix="schema: https://schema.org/ review: https://example.invalid/epub-review#">',
            $opfXml
        );
        $opfWithManifestVocabulary = str_replace(
            '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>',
            '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav rendition:layout-pre-paginated"/>',
            $opfWithManifestVocabulary
        );
        $opfWithManifestVocabulary = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" properties="mathml schema:encodingFormat review:source-record unknown:review-flag"/>',
            $opfWithManifestVocabulary
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage($opfWithManifestVocabulary));
        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }

        $chapterVocabulary = $manifestById['chapter-1']['propertyVocabulary'];
        $t->same(true, $chapterVocabulary['present']);
        $t->same(4, $chapterVocabulary['count']);
        $t->same(3, $chapterVocabulary['prefixedCount']);
        $t->same(2, $chapterVocabulary['resolvedCount']);
        $t->same(1, $chapterVocabulary['unresolvedCount']);
        $t->same('mathml', $chapterVocabulary['items'][0]['property']);
        $t->same(false, $chapterVocabulary['items'][0]['vocabulary']['prefixed']);
        $t->same('schema:encodingFormat', $chapterVocabulary['items'][1]['property']);
        $t->same('https://schema.org/encodingFormat', $chapterVocabulary['items'][1]['vocabulary']['iri']);
        $t->same('review:source-record', $chapterVocabulary['items'][2]['property']);
        $t->same('https://example.invalid/epub-review#source-record', $chapterVocabulary['items'][2]['vocabulary']['iri']);
        $t->same(false, $chapterVocabulary['items'][3]['vocabulary']['resolved']);
        $t->same('unknown-manifest-property-prefix', $chapterVocabulary['items'][3]['vocabulary']['diagnostics'][0]['type']);
        $t->same('chapter-1', $chapterVocabulary['diagnostics'][0]['manifestId']);

        $navVocabulary = $manifestById['nav']['propertyVocabulary'];
        $t->same('rendition:layout-pre-paginated', $navVocabulary['items'][1]['property']);
        $t->same('http://www.idpf.org/vocab/rendition/#layout-pre-paginated', $navVocabulary['items'][1]['vocabulary']['iri']);

        $report = $result['resourceProperties']['propertyVocabulary'];
        $t->same(true, $report['present']);
        $t->same(3, $report['itemCount']);
        $t->same(7, $report['propertyTokenCount']);
        $t->same(4, $report['prefixedPropertyCount']);
        $t->same(3, $report['resolvedPropertyCount']);
        $t->same(1, $report['unresolvedPropertyCount']);
        $t->same('/OEBPS/text/chapter1.xhtml', $report['itemsById']['chapter-1']['part']);
        $t->same(['schema:encodingFormat'], $report['byPrefix']['schema']['properties']);
        $t->same(['chapter-1'], $report['byPrefix']['schema']['manifestIds']);
        $t->same(['review:source-record'], $report['byPrefix']['review']['properties']);
        $t->same(['unknown:review-flag'], $report['byPrefix']['unknown']['properties']);
        $t->same(1, $report['diagnosticCount']);
        $t->same('unknown:review-flag', $report['diagnostics'][0]['property']);
        $t->same('unknown-manifest-property-prefix', $report['diagnostics'][0]['type']);

        $t->same(['mathml'], $manifestById['chapter-1']['resourceReviewFlags']);
        $t->same('https://schema.org/encodingFormat', $result['resourceProperties']['itemsById']['chapter-1']['propertyVocabulary']['items'][1]['vocabulary']['iri']);
        $t->same('https://example.invalid/epub-review#source-record', $result['importReport']['manifest']['items'][1]['propertyVocabulary']['items'][2]['vocabulary']['iri']);
        $t->same($report, $result['importReport']['resourceProperties']['propertyVocabulary']);
        $t->same($report, $result['document']->attr('resourceProperties')['propertyVocabulary']);
    },
    'scans EPUB XHTML content resources without fetching remote references' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $contentScanXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:svg="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
  <body>
    <h1 id="local">Content resource scan</h1>
    <p><a href="#local">Local anchor</a></p>
    <p><a href="chapter1.xhtml#intro">Source chapter link</a></p>
    <p><img src="../images/cover.png" alt="cover"/></p>
    <p><img src="https://cdn.example.test/epub/remote.png" alt="remote"/></p>
    <p><img src="../images/missing.png" alt="missing"/></p>
    <script>window.epubReview = true;</script>
    <math xmlns="http://www.w3.org/1998/Math/MathML"><mi>x</mi><mo>=</mo><mn>1</mn></math>
    <svg:svg viewBox="0 0 10 10"><svg:image xlink:href="../images/cover.png"/></svg:svg>
  </body>
</html>
XML;
        $opfWithContentScan = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="content-scan" href="text/content-scan.xhtml" media-type="application/xhtml+xml"/>',
            $opfXml
        );
        $opfWithContentScan = str_replace(
            '</spine>',
            '<itemref idref="content-scan"/></spine>',
            $opfWithContentScan
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithContentScan,
            null,
            [
                ['name' => 'OEBPS/text/content-scan.xhtml', 'data' => $contentScanXhtml],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/content-scan.xhtml'];
        $referencesByHref = [];
        foreach ($asset['references'] as $reference) {
            $referencesByHref[$reference['href']] = $reference;
        }

        $t->same(true, $report['present']);
        $t->same(4, $report['assetCount']);
        $t->same(6, $asset['referenceCount']);
        $t->same(1, $report['externalReferenceCount']);
        $t->same(1, $report['missingReferenceCount']);
        $t->same(1, $report['mathmlAssetCount']);
        $t->same(1, $report['svgAssetCount']);
        $t->same(1, $report['scriptedAssetCount']);
        $t->same(['mathml', 'svg', 'scripted', 'remote-resources', 'missing-references'], $asset['reviewFlags']);
        $t->same(true, $asset['flags']['mathml']);
        $t->same(true, $asset['flags']['svg']);
        $t->same(true, $asset['flags']['scripted']);
        $t->same(true, $asset['flags']['remoteResources']);
        $t->same(true, $asset['flags']['missingReferences']);
        $t->same(false, $asset['flags']['encryptedReferences']);

        $t->same('/OEBPS/text/content-scan.xhtml#local', $referencesByHref['#local']['target']);
        $t->same('/OEBPS/text/content-scan.xhtml', $referencesByHref['#local']['part']);
        $t->same('content-scan', $referencesByHref['#local']['manifestId']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $referencesByHref['chapter1.xhtml#intro']['target']);
        $t->same('chapter-1', $referencesByHref['chapter1.xhtml#intro']['manifestId']);
        $t->same('/OEBPS/images/cover.png', $referencesByHref['../images/cover.png']['target']);
        $t->same('cover-image', $referencesByHref['../images/cover.png']['manifestId']);
        $t->same(7, $referencesByHref['../images/cover.png']['byteLength']);

        $remote = $referencesByHref['https://cdn.example.test/epub/remote.png'];
        $t->same(true, $remote['external']);
        $t->same(false, $remote['exists']);
        $t->same(null, $remote['part']);
        $t->same('external-xhtml-content-reference', $remote['diagnostics'][0]['type']);

        $missing = $referencesByHref['../images/missing.png'];
        $t->same(false, $missing['external']);
        $t->same(false, $missing['exists']);
        $t->same('/OEBPS/images/missing.png', $missing['part']);
        $t->same('missing-xhtml-content-reference', $missing['diagnostics'][0]['type']);
        $t->same('missing-xhtml-content-reference', $report['missingReferences'][0]['diagnostics'][0]['type']);
        $t->same('external-xhtml-content-reference', $report['externalReferences'][0]['diagnostics'][0]['type']);
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));

        $scanBlock = $result['document']->children[2];
        $t->same('/OEBPS/text/content-scan.xhtml', $scanBlock->attr('part'));
        $t->same($asset['flags'], $scanBlock->attr('contentResourceFlags'));
        $t->same($asset['reviewFlags'], $scanBlock->attr('contentResourceReviewFlags'));
        $t->same($asset['references'], $scanBlock->attr('contentReferences'));
        $t->same($asset['diagnostics'], $scanBlock->attr('contentDiagnostics'));
    },
    'scans EPUB XHTML srcset candidates for responsive image review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $responsiveXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <picture>
      <source srcset="../images/cover.png 1x, ../images/hero@2x.webp 2x, https://cdn.example.test/epub/hero.avif 640w" type="image/avif"/>
      <img src="../images/cover.png" srcset="../images/cover.png 1x, ../images/missing-large.png 2x" alt="Responsive cover"/>
    </picture>
  </body>
</html>
XML;
        $opfWithSrcset = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="hero-webp" href="images/hero@2x.webp" media-type="image/webp"/>'
                . '<item id="responsive-srcset" href="text/responsive-srcset.xhtml" media-type="application/xhtml+xml" properties="remote-resources"/>'
                . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );
        $opfWithSrcset = str_replace(
            '</spine>',
            '<itemref idref="responsive-srcset"/></spine>',
            $opfWithSrcset
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithSrcset,
            null,
            [
                ['name' => 'OEBPS/text/responsive-srcset.xhtml', 'data' => $responsiveXhtml],
                ['name' => 'OEBPS/images/hero@2x.webp', 'data' => 'WEBP2X', 'compressionMethod' => 0],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/responsive-srcset.xhtml'];
        $srcsetReferences = array_values(array_filter(
            $asset['references'],
            static fn (array $reference): bool => ($reference['attribute'] ?? null) === 'srcset'
        ));

        $t->same(true, $report['present']);
        $t->same(4, $report['assetCount']);
        $t->same(6, $asset['referenceCount']);
        $t->same(5, count($srcsetReferences));
        $t->same(1, $report['externalReferenceCount']);
        $t->same(1, $report['missingReferenceCount']);
        $t->same(['remote-resources', 'missing-references'], $asset['reviewFlags']);
        $t->same(true, $asset['flags']['remoteResources']);
        $t->same(true, $asset['flags']['missingReferences']);

        $t->same('source', $srcsetReferences[0]['element']);
        $t->same('/OEBPS/images/cover.png', $srcsetReferences[0]['target']);
        $t->same('cover-image', $srcsetReferences[0]['manifestId']);
        $t->same(0, $srcsetReferences[0]['srcsetCandidateIndex']);
        $t->same('../images/cover.png 1x', $srcsetReferences[0]['srcsetCandidate']);
        $t->same('1x', $srcsetReferences[0]['srcsetDescriptor']);

        $t->same('/OEBPS/images/hero@2x.webp', $srcsetReferences[1]['target']);
        $t->same('hero-webp', $srcsetReferences[1]['manifestId']);
        $t->same(6, $srcsetReferences[1]['byteLength']);
        $t->same('2x', $srcsetReferences[1]['srcsetDescriptor']);

        $t->same(true, $srcsetReferences[2]['external']);
        $t->same('https://cdn.example.test/epub/hero.avif', $srcsetReferences[2]['target']);
        $t->same('640w', $srcsetReferences[2]['srcsetDescriptor']);
        $t->same('external-xhtml-content-reference', $srcsetReferences[2]['diagnostics'][0]['type']);

        $t->same('img', $srcsetReferences[3]['element']);
        $t->same('/OEBPS/images/cover.png', $srcsetReferences[3]['target']);
        $t->same('1x', $srcsetReferences[3]['srcsetDescriptor']);

        $t->same(false, $srcsetReferences[4]['external']);
        $t->same(false, $srcsetReferences[4]['exists']);
        $t->same('/OEBPS/images/missing-large.png', $srcsetReferences[4]['part']);
        $t->same('2x', $srcsetReferences[4]['srcsetDescriptor']);
        $t->same('missing-xhtml-content-reference', $srcsetReferences[4]['diagnostics'][0]['type']);

        $remoteResources = $result['remoteResources'];
        $t->same(1, $remoteResources['declaredCount']);
        $t->same(1, $remoteResources['observedAssetCount']);
        $t->same(1, $remoteResources['remoteReferenceCount']);
        $t->same('srcset', $remoteResources['observedItemsByPart']['/OEBPS/text/responsive-srcset.xhtml']['remoteReferences'][0]['attribute']);
        $t->same('640w', $remoteResources['observedItemsByPart']['/OEBPS/text/responsive-srcset.xhtml']['remoteReferences'][0]['srcsetDescriptor']);

        $scanBlock = $result['document']->children[2];
        $t->same('/OEBPS/text/responsive-srcset.xhtml', $scanBlock->attr('part'));
        $t->same($asset['references'], $scanBlock->attr('contentReferences'));
        $t->same($asset['reviewFlags'], $scanBlock->attr('contentResourceReviewFlags'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
    },
    'reports EPUB XHTML inline style resource references for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $inlineStyleXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>Inline style review chapter</title>
    <style id="inline-style" media="screen">
      @import url("../styles/linked.css") screen and (min-width: 40em);
      .hero { background-image: image-set(url("../images/cover.png") 1x, "https://cdn.example.test/epub/hero@2x.png" 2x type("image/png")); }
      .missing { background-image: url("../images/missing-style.png"); }
    </style>
  </head>
  <body>
    <p id="styled-paragraph" style="background-image: url('../images/cover.png'); border-image-source: url(https://cdn.example.test/borders/review.png)">Inline CSS package references stay reviewable.</p>
  </body>
</html>
XML;
        $opfWithInlineStyles = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="inline-style-content" href="text/inline-style.xhtml" media-type="application/xhtml+xml" properties="remote-resources"/>'
                . '<item id="linked-style" href="styles/linked.css" media-type="text/css"/>'
                . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );
        $opfWithInlineStyles = str_replace(
            '</spine>',
            '<itemref idref="inline-style-content"/></spine>',
            $opfWithInlineStyles
        );

        $linkedCss = 'body { color: #222; }';
        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithInlineStyles,
            null,
            [
                ['name' => 'OEBPS/text/inline-style.xhtml', 'data' => $inlineStyleXhtml],
                ['name' => 'OEBPS/styles/linked.css', 'data' => $linkedCss],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/inline-style.xhtml'];

        $t->same(1, $report['styleAssetCount']);
        $t->same(2, $report['styleCount']);
        $t->same(1, $report['styleElementCount']);
        $t->same(1, $report['styleAttributeCount']);
        $t->same(6, $report['styleReferenceCount']);
        $t->same(2, $report['externalStyleReferenceCount']);
        $t->same(1, $report['missingStyleReferenceCount']);
        $t->same(2, $report['externalReferenceCount']);
        $t->same(1, $report['missingReferenceCount']);

        $t->same(6, $asset['referenceCount']);
        $t->same(2, $asset['styleCount']);
        $t->same(6, $asset['styleReferenceCount']);
        $t->same(['linked-resources', 'inline-styles', 'remote-resources', 'missing-references'], $asset['reviewFlags']);
        $t->same(true, $asset['flags']['inlineStyles']);
        $t->same(true, $asset['flags']['linkedResources']);
        $t->same(true, $asset['flags']['remoteResources']);
        $t->same(true, $asset['flags']['missingReferences']);

        $styleElement = $asset['styles'][0];
        $t->same('style-element', $styleElement['kind']);
        $t->same('inline-style', $styleElement['id']);
        $t->same('screen', $styleElement['media']);
        $t->same('text', $styleElement['attribute']);
        $t->same(4, $styleElement['referenceCount']);
        $t->same(1, $styleElement['externalReferenceCount']);
        $t->same(1, $styleElement['missingReferenceCount']);
        $t->same(true, $styleElement['cssLength'] > 0);
        $t->same(64, strlen($styleElement['cssSha256']));

        $import = $styleElement['references'][0];
        $t->same('import', $import['kind']);
        $t->same('../styles/linked.css', $import['href']);
        $t->same('/OEBPS/styles/linked.css', $import['part']);
        $t->same('linked-style', $import['manifestId']);
        $t->same('text/css', $import['mediaType']);
        $t->same('screen and (min-width: 40em)', $import['importMedia']);

        $localImageSet = $styleElement['references'][1];
        $t->same('image-set', $localImageSet['kind']);
        $t->same('/OEBPS/images/cover.png', $localImageSet['part']);
        $t->same('cover-image', $localImageSet['manifestId']);
        $t->same('1x', $localImageSet['imageSetDescriptor']);

        $remoteImageSet = $styleElement['references'][2];
        $t->same(true, $remoteImageSet['external']);
        $t->same('https://cdn.example.test/epub/hero@2x.png', $remoteImageSet['target']);
        $t->same('2x type("image/png")', $remoteImageSet['imageSetDescriptor']);
        $t->same('image/png', $remoteImageSet['imageSetType']);
        $t->same('external-xhtml-inline-style-reference', $remoteImageSet['diagnostics'][0]['type']);

        $missing = $styleElement['references'][3];
        $t->same('url', $missing['kind']);
        $t->same('/OEBPS/images/missing-style.png', $missing['part']);
        $t->same(false, $missing['exists']);
        $t->same('missing-xhtml-inline-style-reference', $missing['diagnostics'][0]['type']);

        $styleAttribute = $asset['styles'][1];
        $t->same('style-attribute', $styleAttribute['kind']);
        $t->same('p', $styleAttribute['element']);
        $t->same('styled-paragraph', $styleAttribute['id']);
        $t->same('style', $styleAttribute['attribute']);
        $t->same(2, $styleAttribute['referenceCount']);
        $t->same('../images/cover.png', $styleAttribute['references'][0]['href']);
        $t->same('/OEBPS/images/cover.png', $styleAttribute['references'][0]['part']);
        $t->same('https://cdn.example.test/borders/review.png', $styleAttribute['references'][1]['href']);
        $t->same(true, $styleAttribute['references'][1]['external']);
        $t->same('external-xhtml-inline-style-reference', $styleAttribute['references'][1]['diagnostics'][0]['type']);

        $remoteResources = $result['remoteResources'];
        $t->same(1, $remoteResources['declaredCount']);
        $t->same(1, $remoteResources['observedAssetCount']);
        $t->same(2, $remoteResources['remoteReferenceCount']);
        $t->same(2, $remoteResources['xhtmlExternalReferenceCount']);
        $t->same(true, $remoteResources['observedItemsByPart']['/OEBPS/text/inline-style.xhtml']['manifestDeclared']);
        $t->same('style-element', $remoteResources['observedItemsByPart']['/OEBPS/text/inline-style.xhtml']['remoteReferences'][0]['source']);
        $t->same('style-attribute', $remoteResources['observedItemsByPart']['/OEBPS/text/inline-style.xhtml']['remoteReferences'][1]['source']);

        $scanBlock = $result['document']->children[2];
        $t->same('/OEBPS/text/inline-style.xhtml', $scanBlock->attr('part'));
        $t->same($asset['styles'], $scanBlock->attr('contentStyles'));
        $t->same($asset['styleDiagnostics'], $scanBlock->attr('contentStyleDiagnostics'));
        $t->same($asset['references'], $scanBlock->attr('contentReferences'));
        $t->same($asset['reviewFlags'], $scanBlock->attr('contentResourceReviewFlags'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
    },
    'reports EPUB XHTML scripted content sources for static review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $scriptedXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>Scripted review chapter</title>
    <script id="head-inline" type="text/javascript">console.log("review");</script>
  </head>
  <body onload="bootstrapReview()">
    <p><a id="unsafe-link" href="javascript:alert('legacy')">Legacy action</a></p>
    <script id="local-script" src="../scripts/app.js" defer="defer"></script>
    <script id="remote-script" src="https://cdn.example.test/epub/app.js" async="async"></script>
    <button id="event-button" onclick="runImport()">Run import action</button>
  </body>
</html>
XML;
        $opfWithScriptedContent = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="scripted-content" href="text/scripted.xhtml" media-type="application/xhtml+xml"/>'
                . '<item id="app-js" href="scripts/app.js" media-type="application/javascript"/>'
                . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );
        $opfWithScriptedContent = str_replace(
            '</spine>',
            '<itemref idref="scripted-content"/></spine>',
            $opfWithScriptedContent
        );

        $localScript = 'window.localReview = true;';
        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithScriptedContent,
            null,
            [
                ['name' => 'OEBPS/text/scripted.xhtml', 'data' => $scriptedXhtml],
                ['name' => 'OEBPS/scripts/app.js', 'data' => $localScript],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/scripted.xhtml'];

        $t->same(1, $report['scriptedAssetCount']);
        $t->same(3, $report['scriptCount']);
        $t->same(2, $report['scriptEventHandlerCount']);
        $t->same(1, $report['javascriptReferenceCount']);
        $t->same(['scripted', 'remote-resources'], $asset['reviewFlags']);
        $t->same(true, $asset['flags']['scripted']);
        $t->same(true, $asset['flags']['remoteResources']);

        $t->same(3, $asset['scriptCount']);
        $t->same('head-inline', $asset['scripts'][0]['id']);
        $t->same(true, $asset['scripts'][0]['inline']);
        $t->same('text/javascript', $asset['scripts'][0]['type']);
        $t->same(strlen('console.log("review");'), $asset['scripts'][0]['inlineTextLength']);
        $t->same(hash('sha256', 'console.log("review");'), $asset['scripts'][0]['inlineTextSha256']);
        $t->same('inline-xhtml-script-content', $asset['scripts'][0]['diagnostics'][0]['type']);

        $t->same('local-script', $asset['scripts'][1]['id']);
        $t->same(false, $asset['scripts'][1]['inline']);
        $t->same('../scripts/app.js', $asset['scripts'][1]['src']);
        $t->same('/OEBPS/scripts/app.js', $asset['scripts'][1]['part']);
        $t->same('app-js', $asset['scripts'][1]['manifestId']);
        $t->same(strlen($localScript), $asset['scripts'][1]['byteLength']);
        $t->same(hash('sha256', $localScript), $asset['scripts'][1]['byteSha256']);
        $t->same(true, $asset['scripts'][1]['defer']);

        $t->same('remote-script', $asset['scripts'][2]['id']);
        $t->same(true, $asset['scripts'][2]['external']);
        $t->same('https://cdn.example.test/epub/app.js', $asset['scripts'][2]['target']);
        $t->same('external-xhtml-script-source-reference', $asset['scripts'][2]['diagnostics'][0]['type']);
        $t->same(true, $asset['scripts'][2]['async']);

        $t->same(2, $asset['scriptEventHandlerCount']);
        $t->same('body', $asset['scriptEventHandlers'][0]['element']);
        $t->same('onload', $asset['scriptEventHandlers'][0]['attribute']);
        $t->same(hash('sha256', 'bootstrapReview()'), $asset['scriptEventHandlers'][0]['valueSha256']);
        $t->same('event-button', $asset['scriptEventHandlers'][1]['elementId']);
        $t->same('onclick', $asset['scriptEventHandlers'][1]['attribute']);

        $javascriptReference = $asset['javascriptReferences'][0];
        $t->same('a', $javascriptReference['element']);
        $t->same('href', $javascriptReference['attribute']);
        $t->same("javascript:alert('legacy')", $javascriptReference['href']);
        $t->same(true, $javascriptReference['external']);
        $t->same('javascript-xhtml-content-reference', $javascriptReference['diagnostics'][0]['type']);

        $scanBlock = $result['document']->children[2];
        $t->same('/OEBPS/text/scripted.xhtml', $scanBlock->attr('part'));
        $t->same($asset['scripts'], $scanBlock->attr('contentScripts'));
        $t->same($asset['scriptEventHandlers'], $scanBlock->attr('contentScriptEventHandlers'));
        $t->same($asset['javascriptReferences'], $scanBlock->attr('contentJavascriptReferences'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
    },
    'reports EPUB XHTML link resource policy for static review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $linkedXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>Linked review chapter</title>
    <link id="local-style" class="review-css" rel="stylesheet" href="../styles/linked.css" type="text/css" media="screen"/>
    <link id="remote-preload" rel="preload" as="image" href="https://cdn.example.test/epub/hero.png" type="image/png" crossorigin="anonymous"/>
    <link id="canonical-chapter" rel="canonical" href="chapter1.xhtml#intro" hreflang="en"/>
    <link id="alternate-record" rel="alternate" href="../meta/feed.json" type="application/json" title="Review feed"/>
    <link id="missing-icon" rel="icon" href="../images/missing-icon.png" sizes="any"/>
    <link id="bad-preload" rel="preload" href="../images/cover.png"/>
    <link id="untyped-link" href="../styles/linked.css"/>
    <link id="empty-style" rel="stylesheet"/>
  </head>
  <body><p>XHTML link resource policy stays inert for WordPress import review.</p></body>
</html>
XML;
        $opfWithLinkedContent = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="linked-content" href="text/linked.xhtml" media-type="application/xhtml+xml"/>'
                . '<item id="linked-style" href="styles/linked.css" media-type="text/css"/>'
                . '<item id="alternate-feed" href="meta/feed.json" media-type="application/json"/>'
                . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );
        $opfWithLinkedContent = str_replace(
            '</spine>',
            '<itemref idref="linked-content"/></spine>',
            $opfWithLinkedContent
        );

        $linkedCss = 'body { color: #222; }';
        $feedJson = '{"source":"epub-xhtml-link"}';
        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithLinkedContent,
            null,
            [
                ['name' => 'OEBPS/text/linked.xhtml', 'data' => $linkedXhtml],
                ['name' => 'OEBPS/styles/linked.css', 'data' => $linkedCss],
                ['name' => 'OEBPS/meta/feed.json', 'data' => $feedJson],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/linked.xhtml'];

        $t->same(1, $report['linkAssetCount']);
        $t->same(8, $report['linkCount']);
        $t->same(4, $report['activeLinkCount']);
        $t->same(4, $report['passiveLinkCount']);
        $t->same(6, $report['linkReviewRequiredCount']);
        $t->same(1, $report['externalReferenceCount']);
        $t->same(1, $report['missingReferenceCount']);
        $t->same(['linked-resources', 'remote-resources', 'missing-references'], $asset['reviewFlags']);
        $t->same(true, $asset['flags']['linkedResources']);
        $t->same(8, $asset['linkCount']);
        $t->same(4, $asset['activeLinkCount']);
        $t->same(4, $asset['passiveLinkCount']);
        $t->same(6, $asset['linkReviewRequiredCount']);

        $localStyle = $asset['links'][0];
        $t->same('local-style', $localStyle['id']);
        $t->same(['stylesheet'], $localStyle['rel']);
        $t->same('stylesheet', $localStyle['policy']);
        $t->same(true, $localStyle['active']);
        $t->same('../styles/linked.css', $localStyle['href']);
        $t->same('/OEBPS/styles/linked.css', $localStyle['part']);
        $t->same('linked-style', $localStyle['manifestId']);
        $t->same('text/css', $localStyle['declaredType']);
        $t->same('screen', $localStyle['media']);
        $t->same(strlen($linkedCss), $localStyle['byteLength']);
        $t->same(hash('sha256', $linkedCss), $localStyle['byteSha256']);
        $t->same('active-xhtml-link-resource', $localStyle['diagnostics'][0]['type']);

        $remotePreload = $asset['links'][1];
        $t->same('remote-preload', $remotePreload['id']);
        $t->same('preload', $remotePreload['policy']);
        $t->same('image', $remotePreload['as']);
        $t->same(true, $remotePreload['external']);
        $t->same('https://cdn.example.test/epub/hero.png', $remotePreload['target']);
        $t->same('external-xhtml-link-resource-reference', $remotePreload['diagnostics'][1]['type']);

        $canonical = $asset['links'][2];
        $t->same('canonical', $canonical['policy']);
        $t->same(false, $canonical['active']);
        $t->same('en', $canonical['hreflang']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $canonical['target']);
        $t->same('chapter-1', $canonical['manifestId']);
        $t->same([], $canonical['diagnostics']);

        $alternate = $asset['links'][3];
        $t->same('alternate', $alternate['policy']);
        $t->same('/OEBPS/meta/feed.json', $alternate['part']);
        $t->same('alternate-feed', $alternate['manifestId']);
        $t->same(hash('sha256', $feedJson), $alternate['byteSha256']);
        $t->same('Review feed', $alternate['title']);

        $missingIcon = $asset['links'][4];
        $t->same('icon', $missingIcon['policy']);
        $t->same(false, $missingIcon['exists']);
        $t->same('/OEBPS/images/missing-icon.png', $missingIcon['part']);
        $t->same('missing-xhtml-link-resource-reference', $missingIcon['diagnostics'][0]['type']);

        $badPreload = $asset['links'][5];
        $t->same('preload', $badPreload['policy']);
        $t->same(null, $badPreload['as']);
        $t->same('xhtml-link-preload-missing-as', $badPreload['diagnostics'][1]['type']);

        $untyped = $asset['links'][6];
        $t->same('untyped', $untyped['policy']);
        $t->same(['missing-xhtml-link-rel'], array_column($untyped['diagnostics'], 'type'));

        $empty = $asset['links'][7];
        $t->same('stylesheet', $empty['policy']);
        $t->same(null, $empty['href']);
        $t->same(['missing-xhtml-link-href', 'active-xhtml-link-resource'], array_column($empty['diagnostics'], 'type'));

        $t->same(8, count($report['linkItems']));
        $t->same(9, count($report['linkDiagnostics']));
        $t->same('active-xhtml-link-resource', $report['linkDiagnostics'][0]['type']);
        $t->same('external-xhtml-link-resource-reference', $report['linkDiagnostics'][2]['type']);
        $t->same('missing-xhtml-link-resource-reference', $report['linkDiagnostics'][3]['type']);
        $t->same('missing-xhtml-link-rel', $report['linkDiagnostics'][6]['type']);
        $t->same('missing-xhtml-link-href', $report['linkDiagnostics'][7]['type']);

        $scanBlock = $result['document']->children[2];
        $t->same('/OEBPS/text/linked.xhtml', $scanBlock->attr('part'));
        $t->same($asset['links'], $scanBlock->attr('contentLinks'));
        $t->same($asset['linkDiagnostics'], $scanBlock->attr('contentLinkDiagnostics'));
        $t->same($asset['reviewFlags'], $scanBlock->attr('contentResourceReviewFlags'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
    },
    'reports EPUB XHTML meta refresh targets for static review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $refreshXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>Refresh review chapter</title>
    <meta id="local-refresh" http-equiv="refresh" content="2.5; url=../text/chapter2.xhtml#media"/>
    <meta id="remote-refresh" http-equiv="Refresh" content="0; URL='https://cdn.example.test/epub/redirect.xhtml'"/>
    <meta id="missing-refresh" http-equiv="refresh" content="soon; url=missing.xhtml"/>
    <meta id="no-url-refresh" http-equiv="refresh" content="10"/>
  </head>
  <body><p>XHTML meta refresh targets stay inert for WordPress import review.</p></body>
</html>
XML;
        $opfWithRefreshContent = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="refresh-content" href="text/refresh.xhtml" media-type="application/xhtml+xml"/>'
                . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );
        $opfWithRefreshContent = str_replace(
            '</spine>',
            '<itemref idref="refresh-content"/></spine>',
            $opfWithRefreshContent
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithRefreshContent,
            null,
            [
                ['name' => 'OEBPS/text/refresh.xhtml', 'data' => $refreshXhtml],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/refresh.xhtml'];

        $t->same(1, $report['refreshAssetCount'] ?? null);
        $t->same(4, $report['refreshCount'] ?? null);
        $t->same(4, $report['refreshReviewRequiredCount'] ?? null);
        $t->same(1, $report['externalRefreshCount'] ?? null);
        $t->same(1, $report['missingRefreshCount'] ?? null);
        $t->same(3, $asset['referenceCount']);
        $t->same(4, $asset['refreshCount']);
        $t->same(4, $asset['refreshReviewRequiredCount']);
        $t->same(1, $asset['externalRefreshCount']);
        $t->same(1, $asset['missingRefreshCount']);
        $t->same(['linked-resources', 'remote-resources', 'missing-references'], $asset['reviewFlags']);
        $t->same(true, $asset['flags']['linkedResources']);
        $t->same(true, $asset['flags']['remoteResources']);
        $t->same(true, $asset['flags']['missingReferences']);

        $local = $asset['refreshes'][0];
        $t->same('local-refresh', $local['id']);
        $t->same('refresh', $local['httpEquiv']);
        $t->same('2.5; url=../text/chapter2.xhtml#media', $local['content']);
        $t->same('2.5', $local['delayRaw']);
        $t->same(2.5, $local['delaySeconds']);
        $t->same('../text/chapter2.xhtml#media', $local['url']);
        $t->same('/OEBPS/text/chapter2.xhtml#media', $local['target']);
        $t->same('/OEBPS/text/chapter2.xhtml', $local['part']);
        $t->same('media', $local['fragment']);
        $t->same(true, $local['exists']);
        $t->same('chapter-2', $local['manifestId']);
        $t->same('active-xhtml-meta-refresh', $local['diagnostics'][0]['type']);

        $remote = $asset['refreshes'][1];
        $t->same('remote-refresh', $remote['id']);
        $t->same('0', $remote['delayRaw']);
        $t->same(0.0, $remote['delaySeconds']);
        $t->same('https://cdn.example.test/epub/redirect.xhtml', $remote['url']);
        $t->same(true, $remote['external']);
        $t->same('external-xhtml-meta-refresh-reference', $remote['diagnostics'][1]['type']);

        $missing = $asset['refreshes'][2];
        $t->same('missing-refresh', $missing['id']);
        $t->same(null, $missing['delaySeconds']);
        $t->same('/OEBPS/text/missing.xhtml', $missing['part']);
        $t->same(false, $missing['exists']);
        $t->same([
            'active-xhtml-meta-refresh',
            'invalid-xhtml-meta-refresh-delay',
            'missing-xhtml-meta-refresh-reference',
        ], array_column($missing['diagnostics'], 'type'));

        $noUrl = $asset['refreshes'][3];
        $t->same('no-url-refresh', $noUrl['id']);
        $t->same('10', $noUrl['delayRaw']);
        $t->same(10.0, $noUrl['delaySeconds']);
        $t->same(null, $noUrl['url']);
        $t->same(null, $noUrl['target']);
        $t->same(['active-xhtml-meta-refresh', 'missing-xhtml-meta-refresh-url'], array_column($noUrl['diagnostics'], 'type'));

        $refreshReferences = array_values(array_filter(
            $asset['references'],
            static fn (array $reference): bool => ($reference['element'] ?? null) === 'meta'
                && ($reference['attribute'] ?? null) === 'content'
        ));
        $t->same(3, count($refreshReferences));
        $t->same('../text/chapter2.xhtml#media', $refreshReferences[0]['href']);
        $t->same('https://cdn.example.test/epub/redirect.xhtml', $refreshReferences[1]['href']);
        $t->same('external-xhtml-meta-refresh-reference', $refreshReferences[1]['diagnostics'][0]['type']);
        $t->same('missing-xhtml-meta-refresh-reference', $refreshReferences[2]['diagnostics'][0]['type']);

        $remoteResources = $result['remoteResources'];
        $t->same(1, $remoteResources['observedAssetCount']);
        $t->same(1, $remoteResources['remoteReferenceCount']);
        $t->same(1, $remoteResources['xhtmlExternalReferenceCount']);
        $t->same('https://cdn.example.test/epub/redirect.xhtml', $remoteResources['observedItemsByPart']['/OEBPS/text/refresh.xhtml']['remoteReferences'][0]['target']);
        $t->same('content', $remoteResources['observedItemsByPart']['/OEBPS/text/refresh.xhtml']['remoteReferences'][0]['attribute']);

        $scanBlock = $result['document']->children[2];
        $t->same('/OEBPS/text/refresh.xhtml', $scanBlock->attr('part'));
        $t->same($asset['refreshes'], $scanBlock->attr('contentRefreshes'));
        $t->same($asset['refreshDiagnostics'], $scanBlock->attr('contentRefreshDiagnostics'));
        $t->same($asset['references'], $scanBlock->attr('contentReferences'));
        $t->same($asset['reviewFlags'], $scanBlock->attr('contentResourceReviewFlags'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
    },
    'reports EPUB XHTML form and ping side effects for static review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $sideEffectXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <form id="comment-form" class="review-form" action="https://forms.example.test/submit" method="post" enctype="multipart/form-data" target="_blank">
      <input id="author-field" name="author" type="text" value="Migration Desk" required="required"/>
      <input id="draft-submit" name="draft" type="submit" value="Save draft" formaction="../forms/draft.xhtml#review"/>
      <button id="remote-submit" type="submit" formaction="https://forms.example.test/button-submit">Send remote</button>
      <button id="plain-button" type="button">No submit</button>
    </form>
    <p><a id="ping-link" href="chapter1.xhtml#intro" ping="https://analytics.example.test/ping ../forms/missing-ping.xhtml">Tracked local chapter</a></p>
  </body>
</html>
XML;
        $opfWithSideEffects = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="side-effect-content" href="text/side-effects.xhtml" media-type="application/xhtml+xml"/>'
                . '<item id="draft-target" href="forms/draft.xhtml" media-type="application/xhtml+xml"/>'
                . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );
        $opfWithSideEffects = str_replace(
            '</spine>',
            '<itemref idref="side-effect-content"/></spine>',
            $opfWithSideEffects
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithSideEffects,
            null,
            [
                ['name' => 'OEBPS/text/side-effects.xhtml', 'data' => $sideEffectXhtml],
                ['name' => 'OEBPS/forms/draft.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><p id="review">Draft target</p></body></html>'],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/side-effects.xhtml'];

        $t->same(1, $report['sideEffectAssetCount'] ?? null);
        $t->same(4, $report['sideEffectCount'] ?? null);
        $t->same(5, $report['sideEffectReferenceCount'] ?? null);
        $t->same(3, $report['externalSideEffectReferenceCount'] ?? null);
        $t->same(1, $report['missingSideEffectReferenceCount'] ?? null);
        $t->same(0, $report['encryptedSideEffectReferenceCount'] ?? null);
        $t->same(4, $report['sideEffectReviewRequiredCount'] ?? null);
        $t->same(1, $asset['referenceCount']);
        $t->same(4, $asset['sideEffectCount']);
        $t->same(5, $asset['sideEffectReferenceCount']);
        $t->same(3, $asset['externalSideEffectReferenceCount']);
        $t->same(1, $asset['missingSideEffectReferenceCount']);
        $t->same(['side-effects'], $asset['reviewFlags']);
        $t->same(true, $asset['flags']['sideEffects']);
        $t->same(false, $asset['flags']['remoteResources']);
        $t->same(false, $asset['flags']['missingReferences']);

        $form = $asset['sideEffects'][0];
        $t->same('form', $form['kind']);
        $t->same('comment-form', $form['id']);
        $t->same('post', $form['method']);
        $t->same('multipart/form-data', $form['enctype']);
        $t->same('_blank', $form['targetFrame']);
        $t->same('https://forms.example.test/submit', $form['action']);
        $t->same(true, $form['external']);
        $t->same(4, $form['controlCount']);
        $t->same(2, $form['submitControlCount']);
        $t->same('input', $form['controls'][0]['element']);
        $t->same('author', $form['controls'][0]['name']);
        $t->same('text', $form['controls'][0]['type']);
        $t->same(true, $form['controls'][0]['required']);
        $t->same('submit', $form['controls'][1]['type']);
        $t->same(true, $form['controls'][1]['submit']);
        $t->same('../forms/draft.xhtml#review', $form['controls'][1]['formAction']);
        $t->same('button', $form['controls'][2]['element']);
        $t->same('button', $form['controls'][3]['type']);
        $t->same(false, $form['controls'][3]['submit']);
        $t->same(['active-xhtml-form-submission', 'external-xhtml-form-action-reference'], array_column($form['diagnostics'], 'type'));

        $draftSubmit = $asset['sideEffects'][1];
        $t->same('form-control', $draftSubmit['kind']);
        $t->same('draft-submit', $draftSubmit['id']);
        $t->same('input', $draftSubmit['controlElement']);
        $t->same('submit', $draftSubmit['type']);
        $t->same('../forms/draft.xhtml#review', $draftSubmit['formAction']);
        $t->same('/OEBPS/forms/draft.xhtml#review', $draftSubmit['target']);
        $t->same('/OEBPS/forms/draft.xhtml', $draftSubmit['part']);
        $t->same('review', $draftSubmit['fragment']);
        $t->same('draft-target', $draftSubmit['manifestId']);
        $t->same(false, $draftSubmit['external']);
        $t->same(true, $draftSubmit['exists']);
        $t->same(['active-xhtml-form-control-submission'], array_column($draftSubmit['diagnostics'], 'type'));

        $remoteSubmit = $asset['sideEffects'][2];
        $t->same('remote-submit', $remoteSubmit['id']);
        $t->same('button', $remoteSubmit['controlElement']);
        $t->same('https://forms.example.test/button-submit', $remoteSubmit['formAction']);
        $t->same(true, $remoteSubmit['external']);
        $t->same('external-xhtml-form-control-action-reference', $remoteSubmit['diagnostics'][1]['type']);

        $ping = $asset['sideEffects'][3];
        $t->same('anchor-ping', $ping['kind']);
        $t->same('ping-link', $ping['id']);
        $t->same('chapter1.xhtml#intro', $ping['href']);
        $t->same(2, $ping['pingCount']);
        $t->same(1, $ping['externalPingCount']);
        $t->same(1, $ping['missingPingCount']);
        $t->same('https://analytics.example.test/ping', $ping['pings'][0]['target']);
        $t->same(true, $ping['pings'][0]['external']);
        $t->same('external-xhtml-anchor-ping-reference', $ping['pings'][0]['diagnostics'][0]['type']);
        $t->same('/OEBPS/forms/missing-ping.xhtml', $ping['pings'][1]['part']);
        $t->same(false, $ping['pings'][1]['exists']);
        $t->same('missing-xhtml-anchor-ping-reference', $ping['pings'][1]['diagnostics'][0]['type']);
        $t->same([
            'active-xhtml-anchor-ping',
            'external-xhtml-anchor-ping-reference',
            'missing-xhtml-anchor-ping-reference',
        ], array_column($ping['diagnostics'], 'type'));

        $t->same(4, count($report['sideEffectItems']));
        $t->same(8, count($report['sideEffectDiagnostics']));
        $t->same('active-xhtml-form-submission', $report['sideEffectDiagnostics'][0]['type']);
        $t->same('external-xhtml-form-action-reference', $report['sideEffectDiagnostics'][1]['type']);
        $t->same('active-xhtml-form-control-submission', $report['sideEffectDiagnostics'][2]['type']);
        $t->same('external-xhtml-form-control-action-reference', $report['sideEffectDiagnostics'][4]['type']);
        $t->same('active-xhtml-anchor-ping', $report['sideEffectDiagnostics'][5]['type']);
        $t->same('missing-xhtml-anchor-ping-reference', $report['sideEffectDiagnostics'][7]['type']);

        $remoteResources = $result['remoteResources'];
        $t->same(0, $remoteResources['observedAssetCount']);
        $t->same(0, $remoteResources['remoteReferenceCount']);
        $t->same(0, $remoteResources['xhtmlExternalReferenceCount']);

        $scanBlock = $result['document']->children[2];
        $t->same('/OEBPS/text/side-effects.xhtml', $scanBlock->attr('part'));
        $t->same($asset['sideEffects'], $scanBlock->attr('contentSideEffects'));
        $t->same($asset['sideEffectDiagnostics'], $scanBlock->attr('contentSideEffectDiagnostics'));
        $t->same($asset['references'], $scanBlock->attr('contentReferences'));
        $t->same($asset['reviewFlags'], $scanBlock->attr('contentResourceReviewFlags'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
    },
    'reports EPUB stylesheet resource references for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithCssAssets = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/review.css" media-type="text/css" properties="remote-resources"/>'
            . '<item id="reset-style" href="styles/reset.css" media-type="text/css"/>'
            . '<item id="theme-style" href="styles/theme.css" media-type="text/css"/>'
            . '<item id="font-main" href="fonts/source.woff2" media-type="font/woff2"/>',
            $opfXml
        );
        $reviewCss = <<<'CSS'
@import url("../styles/reset.css") screen;
@font-face { font-family: Review; src: url("../fonts/source.woff2") format("woff2"); }
body { background-image: url("../images/cover.png"); }
.remote { background: url("https://cdn.example.test/epub/paper.png"); }
.missing { background: url("../images/missing-css.png"); }
.inline { background: url(data:image/png;base64,AAAA); }
CSS;
        $themeCss = '.theme { background-image: url("https://widgets.example.test/theme.png"); }';

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithCssAssets,
            null,
            [
                ['name' => 'OEBPS/styles/review.css', 'data' => $reviewCss],
                ['name' => 'OEBPS/styles/reset.css', 'data' => 'html { box-sizing: border-box; }'],
                ['name' => 'OEBPS/styles/theme.css', 'data' => $themeCss],
                ['name' => 'OEBPS/fonts/source.woff2', 'data' => 'WOFF2DATA'],
            ]
        ));

        $css = $result['cssResourceReport'];
        $t->same(true, $css['present']);
        $t->same(3, $css['assetCount']);
        $t->same(6, $css['referenceCount']);
        $t->same(1, $css['importReferenceCount']);
        $t->same(5, $css['urlReferenceCount']);
        $t->same(1, $css['fontFaceCount']);
        $t->same(2, $css['externalReferenceCount']);
        $t->same(1, $css['missingReferenceCount']);
        $t->same(2, $css['reviewRequiredCount']);
        $t->same($css, $result['importReport']['cssResourceReport']);
        $t->same($css, $result['document']->attr('cssResourceReport'));

        $style = $css['itemsByPart']['/OEBPS/styles/review.css'];
        $t->same('style', $style['id']);
        $t->same(['remote-resources'], $style['manifestProperties']);
        $t->same(5, $style['referenceCount']);
        $t->same(['remote-resources', 'missing-references', 'conditional-styles'], $style['reviewFlags']);
        $t->same('import', $style['references'][0]['kind']);
        $t->same('../styles/reset.css', $style['references'][0]['href']);
        $t->same('/OEBPS/styles/reset.css', $style['references'][0]['target']);
        $t->same('reset-style', $style['references'][0]['manifestId']);
        $t->same('screen', $style['references'][0]['importCondition']);
        $t->same('screen', $style['references'][0]['importMedia']);
        $t->same('url', $style['references'][1]['kind']);
        $t->same('/OEBPS/fonts/source.woff2', $style['references'][1]['part']);
        $t->same('font-main', $style['references'][1]['manifestId']);
        $t->same('font/woff2', $style['references'][1]['mediaType']);
        $t->same('/OEBPS/images/cover.png', $style['references'][2]['part']);
        $t->same('cover-image', $style['references'][2]['manifestId']);
        $t->same(true, $style['references'][3]['external']);
        $t->same('external-css-resource-reference', $style['references'][3]['diagnostics'][0]['type']);
        $t->same('/OEBPS/images/missing-css.png', $style['references'][4]['part']);
        $t->same(false, $style['references'][4]['exists']);
        $t->same('missing-css-resource-reference', $style['references'][4]['diagnostics'][0]['type']);

        $theme = $css['itemsByPart']['/OEBPS/styles/theme.css'];
        $t->same(['remote-resources'], $theme['reviewFlags']);
        $t->same(true, $theme['references'][0]['external']);
        $t->same('https://widgets.example.test/theme.png', $theme['references'][0]['target']);

        $remoteResources = $result['remoteResources'];
        $t->same(1, $remoteResources['declaredCount']);
        $t->same(2, $remoteResources['observedAssetCount']);
        $t->same(2, $remoteResources['remoteReferenceCount']);
        $t->same(0, $remoteResources['xhtmlExternalReferenceCount']);
        $t->same(2, $remoteResources['cssExternalReferenceCount']);
        $t->same(1, $remoteResources['undeclaredAssetCount']);
        $t->same(0, $remoteResources['declaredButUnobservedCount']);
        $t->same('css', $remoteResources['observedItemsByPart']['/OEBPS/styles/review.css']['source']);
        $t->same(true, $remoteResources['observedItemsByPart']['/OEBPS/styles/review.css']['manifestDeclared']);
        $t->same(false, $remoteResources['observedItemsByPart']['/OEBPS/styles/theme.css']['manifestDeclared']);
        $t->same('undeclared-css-remote-resources', $remoteResources['diagnostics'][0]['type']);
    },
    'reports EPUB stylesheet font-face descriptors for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithFonts = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/fonts.css" media-type="text/css" properties="remote-resources"/>'
            . '<item id="serif-woff2" href="fonts/review-serif.woff2" media-type="font/woff2"/>'
            . '<item id="serif-woff" href="fonts/review-serif.woff" media-type="font/woff"/>',
            $opfXml
        );
        $fontCss = <<<'CSS'
@font-face {
  font-family: "Review Serif";
  font-style: italic;
  font-weight: 400 700;
  font-display: swap;
  unicode-range: U+0000-00FF;
  src: local("Review Serif Italic"),
       url("../fonts/review-serif.woff2") format("woff2"),
       url("https://cdn.example.test/fonts/review-serif.woff") format("woff"),
       url("../fonts/missing-serif.woff") format("woff");
}
@font-face {
  font-family: IconFont;
  src: url("../fonts/review-serif.woff") format("woff");
}
CSS;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithFonts,
            null,
            [
                ['name' => 'OEBPS/styles/fonts.css', 'data' => $fontCss],
                ['name' => 'OEBPS/fonts/review-serif.woff2', 'data' => 'WOFF2DATA'],
                ['name' => 'OEBPS/fonts/review-serif.woff', 'data' => 'WOFFDATA'],
            ]
        ));

        $css = $result['cssResourceReport'];
        $style = $css['itemsByPart']['/OEBPS/styles/fonts.css'];

        $t->same(2, $css['fontFaceCount']);
        $t->same(5, $css['fontFaceSourceCount']);
        $t->same(1, $css['fontFaceLocalSourceCount']);
        $t->same(4, $css['fontFaceUrlSourceCount']);
        $t->same(1, $css['fontFaceExternalSourceCount']);
        $t->same(1, $css['fontFaceMissingSourceCount']);
        $t->same(2, $css['fontFaceFamilyCount']);
        $t->same(['Review Serif', 'IconFont'], $css['fontFaceFamilies']);
        $t->same($style['fontFaces'], $css['fontFaceItems']);
        $t->same(2, $style['fontFaceCount']);
        $t->same(5, $style['fontFaceSourceCount']);
        $t->same(['remote-resources', 'missing-references'], $style['reviewFlags']);

        $first = $style['fontFaces'][0];
        $t->same('Review Serif', $first['family']);
        $t->same('"Review Serif"', $first['descriptors']['font-family']);
        $t->same('italic', $first['style']);
        $t->same('400 700', $first['weight']);
        $t->same('swap', $first['display']);
        $t->same('U+0000-00FF', $first['unicodeRange']);
        $t->same(4, $first['sourceCount']);
        $t->same(1, $first['localSourceCount']);
        $t->same(3, $first['urlSourceCount']);
        $t->same(1, $first['externalSourceCount']);
        $t->same(1, $first['missingSourceCount']);
        $t->same('local', $first['sources'][0]['kind']);
        $t->same('Review Serif Italic', $first['sources'][0]['name']);
        $t->same('url', $first['sources'][1]['kind']);
        $t->same('/OEBPS/fonts/review-serif.woff2', $first['sources'][1]['part']);
        $t->same('serif-woff2', $first['sources'][1]['manifestId']);
        $t->same('font/woff2', $first['sources'][1]['mediaType']);
        $t->same('woff2', $first['sources'][1]['format']);
        $t->same(true, $first['sources'][2]['external']);
        $t->same('https://cdn.example.test/fonts/review-serif.woff', $first['sources'][2]['target']);
        $t->same('external-css-font-face-source', $first['sources'][2]['diagnostics'][0]['type']);
        $t->same('/OEBPS/fonts/missing-serif.woff', $first['sources'][3]['part']);
        $t->same(false, $first['sources'][3]['exists']);
        $t->same('missing-css-font-face-source', $first['sources'][3]['diagnostics'][0]['type']);

        $second = $style['fontFaces'][1];
        $t->same('IconFont', $second['family']);
        $t->same('/OEBPS/fonts/review-serif.woff', $second['sources'][0]['part']);
        $t->same('serif-woff', $second['sources'][0]['manifestId']);

        $remoteResources = $result['remoteResources'];
        $t->same(1, $remoteResources['cssExternalReferenceCount']);
        $t->same(1, $remoteResources['remoteReferenceCount']);
        $t->same($css, $result['importReport']['cssResourceReport']);
        $t->same($css, $result['document']->attr('cssResourceReport'));
    },
    'reports EPUB stylesheet image-set candidates for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithImageSet = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/image-set.css" media-type="text/css" properties="remote-resources"/>'
            . '<item id="cover-hidpi" href="images/cover@2x.png" media-type="image/png"/>',
            $opfXml
        );
        $imageSetCss = <<<'CSS'
.hero {
  background-image: image-set(
    "../images/cover.png" 1x,
    url("../images/cover@2x.png") 2x type("image/png"),
    "https://cdn.example.test/epub/cover@3x.png" 3x,
    "../images/missing-cover.png" type("image/png")
  );
}
.icon { background-image: image-set(url(data:image/png;base64,AAAA) 1x, "../images/cover.png" 2x); }
CSS;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithImageSet,
            null,
            [
                ['name' => 'OEBPS/styles/image-set.css', 'data' => $imageSetCss],
                ['name' => 'OEBPS/images/cover@2x.png', 'data' => 'HIDPI-PNG', 'compressionMethod' => 0],
            ]
        ));

        $css = $result['cssResourceReport'];
        $style = $css['itemsByPart']['/OEBPS/styles/image-set.css'];

        $t->same(1, $css['assetCount']);
        $t->same(5, $css['referenceCount']);
        $t->same(5, $css['imageSetReferenceCount']);
        $t->same(0, $css['urlReferenceCount']);
        $t->same(1, $css['externalReferenceCount']);
        $t->same(1, $css['missingReferenceCount']);
        $t->same(['remote-resources', 'missing-references'], $style['reviewFlags']);
        $t->same(5, $style['imageSetReferenceCount']);
        $t->same(5, count($style['references']));

        $standard = $style['references'][0];
        $t->same('image-set', $standard['kind']);
        $t->same('../images/cover.png', $standard['href']);
        $t->same('/OEBPS/images/cover.png', $standard['part']);
        $t->same('cover-image', $standard['manifestId']);
        $t->same(0, $standard['imageSetCandidateIndex']);
        $t->same('1x', $standard['imageSetDescriptor']);
        $t->same(null, $standard['imageSetType']);

        $hidpi = $style['references'][1];
        $t->same('image-set', $hidpi['kind']);
        $t->same('../images/cover@2x.png', $hidpi['href']);
        $t->same('/OEBPS/images/cover@2x.png', $hidpi['part']);
        $t->same('cover-hidpi', $hidpi['manifestId']);
        $t->same(1, $hidpi['imageSetCandidateIndex']);
        $t->same('2x type("image/png")', $hidpi['imageSetDescriptor']);
        $t->same('image/png', $hidpi['imageSetType']);

        $remote = $style['references'][2];
        $t->same(true, $remote['external']);
        $t->same('https://cdn.example.test/epub/cover@3x.png', $remote['target']);
        $t->same('3x', $remote['imageSetDescriptor']);
        $t->same('external-css-resource-reference', $remote['diagnostics'][0]['type']);

        $missing = $style['references'][3];
        $t->same('/OEBPS/images/missing-cover.png', $missing['part']);
        $t->same(false, $missing['exists']);
        $t->same('image/png', $missing['imageSetType']);
        $t->same('missing-css-resource-reference', $missing['diagnostics'][0]['type']);

        $iconFallback = $style['references'][4];
        $t->same('../images/cover.png', $iconFallback['href']);
        $t->same(1, $iconFallback['imageSetCandidateIndex']);
        $t->same('2x', $iconFallback['imageSetDescriptor']);

        $remoteResources = $result['remoteResources'];
        $t->same(1, $remoteResources['observedAssetCount']);
        $t->same(1, $remoteResources['remoteReferenceCount']);
        $t->same(1, $remoteResources['cssExternalReferenceCount']);
        $t->same(0, $remoteResources['undeclaredAssetCount']);
        $t->same(true, $remoteResources['observedItemsByPart']['/OEBPS/styles/image-set.css']['manifestDeclared']);
        $t->same($css, $result['importReport']['cssResourceReport']);
        $t->same($css, $result['document']->attr('cssResourceReport'));
    },
    'reports EPUB stylesheet conditional at-rules for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithConditionalCss = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/conditional.css" media-type="text/css" properties="remote-resources"/>'
            . '<item id="base-style" href="styles/base.css" media-type="text/css"/>',
            $opfXml
        );
        $conditionalCss = <<<'CSS'
@import url("../styles/base.css") layer(review) supports(display: grid) screen and (min-width: 700px);
@media screen and (min-width: 700px), print {
  .hero { background-image: url("../images/cover.png"); }
}
@supports (display: grid) and (not (display: subgrid)) {
  .grid { background-image: url("https://cdn.example.test/grid.png"); }
}
@media speech {
  .note { display: none; }
}
CSS;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithConditionalCss,
            null,
            [
                ['name' => 'OEBPS/styles/conditional.css', 'data' => $conditionalCss],
                ['name' => 'OEBPS/styles/base.css', 'data' => 'html { font-size: 100%; }'],
            ]
        ));

        $css = $result['cssResourceReport'];
        $style = $css['itemsByPart']['/OEBPS/styles/conditional.css'];

        $t->same(2, $css['assetCount']);
        $t->same(3, $css['referenceCount']);
        $t->same(1, $css['importReferenceCount']);
        $t->same(2, $css['urlReferenceCount']);
        $t->same(3, $css['conditionalRuleCount']);
        $t->same(2, $css['mediaRuleCount']);
        $t->same(1, $css['supportsRuleCount']);
        $t->same(1, $css['importConditionCount']);
        $t->same(['screen and (min-width: 700px)', 'print', 'speech'], $css['mediaConditions']);
        $t->same(['(display: grid) and (not (display: subgrid))'], $css['supportsConditions']);
        $t->same($style['conditionalRules'], $css['conditionalRules']);

        $t->same(['remote-resources', 'conditional-styles'], $style['reviewFlags']);
        $t->same(3, $style['referenceCount']);
        $t->same(3, $style['conditionalRuleCount']);
        $t->same(2, $style['mediaRuleCount']);
        $t->same(1, $style['supportsRuleCount']);
        $t->same(1, $style['importConditionCount']);
        $t->same('../styles/base.css', $style['references'][0]['href']);
        $t->same('/OEBPS/styles/base.css', $style['references'][0]['part']);
        $t->same('base-style', $style['references'][0]['manifestId']);
        $t->same('layer(review) supports(display: grid) screen and (min-width: 700px)', $style['references'][0]['importCondition']);
        $t->same('review', $style['references'][0]['importLayer']);
        $t->same(false, $style['references'][0]['importLayerAnonymous']);
        $t->same('display: grid', $style['references'][0]['importSupports']);
        $t->same('screen and (min-width: 700px)', $style['references'][0]['importMedia']);

        $firstMedia = $style['conditionalRules'][0];
        $t->same('media', $firstMedia['kind']);
        $t->same('screen and (min-width: 700px), print', $firstMedia['condition']);
        $t->same(['screen and (min-width: 700px)', 'print'], $firstMedia['conditionItems']);
        $t->same(2, $firstMedia['conditionItemCount']);
        $t->same(1, $firstMedia['nestedReferenceCount']);

        $supports = $style['conditionalRules'][1];
        $t->same('supports', $supports['kind']);
        $t->same('(display: grid) and (not (display: subgrid))', $supports['condition']);
        $t->same(['(display: grid) and (not (display: subgrid))'], $supports['conditionItems']);
        $t->same(1, $supports['nestedReferenceCount']);

        $speech = $style['conditionalRules'][2];
        $t->same('media', $speech['kind']);
        $t->same('speech', $speech['condition']);
        $t->same(0, $speech['nestedReferenceCount']);

        $remoteResources = $result['remoteResources'];
        $t->same(1, $remoteResources['cssExternalReferenceCount']);
        $t->same(1, $remoteResources['remoteReferenceCount']);
        $t->same(1, $remoteResources['observedAssetCount']);
        $t->same(true, $remoteResources['observedItemsByPart']['/OEBPS/styles/conditional.css']['manifestDeclared']);
        $t->same($css, $result['importReport']['cssResourceReport']);
        $t->same($css, $result['document']->attr('cssResourceReport'));
    },
    'reports EPUB stylesheet export policy for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $encryptionXml): void {
        $opfWithCssPolicy = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/local.css" media-type="text/css"/>'
            . '<item id="review-style" href="styles/review.css" media-type="text/css" properties="remote-resources"/>'
            . '<item id="blocked-style" href="styles/blocked.css" media-type="text/css"/>'
            . '<item id="locked-font" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>',
            $opfXml
        );
        $reviewCss = <<<'CSS'
@import url("https://cdn.example.test/epub/review.css") screen;
@media print {
  body { color: black; }
}
@page review:left {
  margin-left: 1in;
}
CSS;
        $blockedCss = <<<'CSS'
@font-face { font-family: Locked; src: url("../fonts/source.otf") format("opentype"); }
.missing { background: url("../images/missing-css-export.png"); }
CSS;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithCssPolicy,
            null,
            [
                ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
                ['name' => 'OEBPS/styles/local.css', 'data' => 'body { color: #222; }'],
                ['name' => 'OEBPS/styles/review.css', 'data' => $reviewCss],
                ['name' => 'OEBPS/styles/blocked.css', 'data' => $blockedCss],
                ['name' => 'OEBPS/fonts/source.otf', 'data' => 'OBFUSCATED-FONT'],
            ]
        ));

        $css = $result['cssResourceReport'];
        $policy = $css['exportPolicy'];
        $t->same(3, $css['assetCount']);
        $t->same(3, $policy['assetCount']);
        $t->same(1, $policy['exportableAssetCount']);
        $t->same(1, $policy['reviewRequiredAssetCount']);
        $t->same(1, $policy['blockedAssetCount']);
        $t->same([
            'exportable' => 1,
            'review-required' => 1,
            'blocked' => 1,
        ], $policy['statusCounts']);
        $t->same(false, $policy['canExportAll']);
        $t->same(true, $policy['requiresReview']);
        $t->same(['exportable', 'review-required', 'blocked'], $policy['statuses']);
        $t->same(['remote-resources', 'conditional-styles', 'paged-media'], $policy['reviewReasons']);
        $t->same(['missing-references', 'encrypted-references'], $policy['blockingReasons']);
        $t->same([
            'remote-resources',
            'conditional-styles',
            'paged-media',
            'missing-references',
            'encrypted-references',
        ], $policy['reasons']);

        $localPolicy = $css['itemsByPart']['/OEBPS/styles/local.css']['exportPolicy'];
        $t->same('exportable', $localPolicy['status']);
        $t->same(true, $localPolicy['canExport']);
        $t->same(false, $localPolicy['requiresReview']);
        $t->same([], $localPolicy['reasons']);

        $reviewPolicy = $css['itemsByPart']['/OEBPS/styles/review.css']['exportPolicy'];
        $t->same('review-required', $reviewPolicy['status']);
        $t->same(true, $reviewPolicy['canExport']);
        $t->same(true, $reviewPolicy['requiresReview']);
        $t->same(['remote-resources', 'conditional-styles', 'paged-media'], $reviewPolicy['reviewReasons']);
        $t->same([], $reviewPolicy['blockingReasons']);
        $t->same(1, $reviewPolicy['externalReferenceCount']);
        $t->same(1, $reviewPolicy['conditionalRuleCount']);
        $t->same(1, $reviewPolicy['importConditionCount']);
        $t->same(1, $reviewPolicy['pageRuleCount']);

        $blockedPolicy = $css['itemsByPart']['/OEBPS/styles/blocked.css']['exportPolicy'];
        $t->same('blocked', $blockedPolicy['status']);
        $t->same(false, $blockedPolicy['canExport']);
        $t->same(true, $blockedPolicy['requiresReview']);
        $t->same(['missing-references', 'encrypted-references'], $blockedPolicy['blockingReasons']);
        $t->same([], $blockedPolicy['reviewReasons']);
        $t->same(2, $blockedPolicy['referenceCount']);
        $t->same(1, $blockedPolicy['missingReferenceCount']);
        $t->same(1, $blockedPolicy['encryptedReferenceCount']);
        $t->same(1, $blockedPolicy['fontFaceCount']);
        $t->same($reviewPolicy, $policy['itemsByPart']['/OEBPS/styles/review.css']);
        $t->same($blockedPolicy, $policy['itemsByPart']['/OEBPS/styles/blocked.css']);
        $t->same($policy, $result['importReport']['cssResourceReport']['exportPolicy']);
        $t->same($policy, $result['document']->attr('cssResourceReport')['exportPolicy']);
    },
    'reports EPUB stylesheet page rules for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithPagedCss = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/paged.css" media-type="text/css"/>',
            $opfXml
        );
        $pagedCss = <<<'CSS'
@page {
  size: 6in 9in;
  margin: 0.75in;
  bleed: 6pt;
  marks: crop cross;
  @top-center { content: string(chapter); }
}
@page chapter:left {
  margin-left: 1.25in;
  @bottom-center { content: counter(page); }
}
@page :blank {
  @top-center { content: none; }
}
CSS;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithPagedCss,
            null,
            [
                ['name' => 'OEBPS/styles/paged.css', 'data' => $pagedCss],
            ]
        ));

        $css = $result['cssResourceReport'];
        $style = $css['itemsByPart']['/OEBPS/styles/paged.css'];

        $t->same(1, $css['assetCount']);
        $t->same(3, $css['pageRuleCount']);
        $t->same(1, $css['namedPageRuleCount']);
        $t->same(2, $css['pagePseudoClassCount']);
        $t->same(3, $css['pageMarginBoxCount']);
        $t->same(['chapter'], $css['pageRuleNames']);
        $t->same(['left', 'blank'], $css['pagePseudoClasses']);
        $t->same(['top-center', 'bottom-center'], $css['pageMarginBoxNames']);
        $t->same($style['pageRules'], $css['pageRules']);

        $t->same(['paged-media'], $style['reviewFlags']);
        $t->same(3, $style['pageRuleCount']);
        $t->same(1, $style['namedPageRuleCount']);
        $t->same(2, $style['pagePseudoClassCount']);
        $t->same(3, $style['pageMarginBoxCount']);
        $t->same(['chapter'], $style['pageRuleNames']);
        $t->same(['left', 'blank'], $style['pagePseudoClasses']);

        $defaultPage = $style['pageRules'][0];
        $t->same('', $defaultPage['selector']);
        $t->same(null, $defaultPage['name']);
        $t->same([], $defaultPage['pseudoClasses']);
        $t->same('6in 9in', $defaultPage['descriptors']['size']);
        $t->same('0.75in', $defaultPage['descriptors']['margin']);
        $t->same('6pt', $defaultPage['bleed']);
        $t->same('crop cross', $defaultPage['marks']);
        $t->same(1, $defaultPage['marginBoxCount']);
        $t->same('top-center', $defaultPage['marginBoxes'][0]['name']);
        $t->same('string(chapter)', $defaultPage['marginBoxes'][0]['descriptors']['content']);

        $chapterLeft = $style['pageRules'][1];
        $t->same('chapter:left', $chapterLeft['selector']);
        $t->same('chapter', $chapterLeft['name']);
        $t->same(['left'], $chapterLeft['pseudoClasses']);
        $t->same('1.25in', $chapterLeft['descriptors']['margin-left']);
        $t->same('bottom-center', $chapterLeft['marginBoxes'][0]['name']);
        $t->same('counter(page)', $chapterLeft['marginBoxes'][0]['descriptors']['content']);

        $blank = $style['pageRules'][2];
        $t->same(':blank', $blank['selector']);
        $t->same(null, $blank['name']);
        $t->same(['blank'], $blank['pseudoClasses']);
        $t->same('none', $blank['marginBoxes'][0]['descriptors']['content']);

        $t->same($css, $result['importReport']['cssResourceReport']);
        $t->same($css, $result['document']->attr('cssResourceReport'));
    },
    'flags EPUB switch XHTML content for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $switchXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <h1>Layout alternate</h1>
    <epub:switch id="layout-choice" class="layout-review">
      <epub:case id="svg-case" required-namespace="http://www.w3.org/2000/svg"><p>Reading-system SVG path.</p></epub:case>
      <epub:case id="math-case" required-modules="mathml"><p>MathML reading-system path.</p></epub:case>
      <epub:default id="fallback-case"><p>Fallback text preserved for WordPress review.</p></epub:default>
    </epub:switch>
    <epub:switch id="invalid-choice">
      <epub:case id="unqualified-case"><p>Unqualified branch needs review.</p></epub:case>
    </epub:switch>
  </body>
</html>
XML;
        $opfWithSwitchContent = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="switch-content" href="text/switch-content.xhtml" media-type="application/xhtml+xml"/>',
            $opfXml
        );
        $opfWithSwitchContent = str_replace(
            '</spine>',
            '<itemref idref="switch-content"/></spine>',
            $opfWithSwitchContent
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithSwitchContent,
            null,
            [
                ['name' => 'OEBPS/text/switch-content.xhtml', 'data' => $switchXhtml],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/switch-content.xhtml'];
        $switchBlock = $result['document']->children[2];

        $t->same(1, $report['switchAssetCount'] ?? null);
        $t->same(2, $report['switchCount'] ?? null);
        $t->same(3, $report['switchCaseCount'] ?? null);
        $t->same(1, $report['switchDefaultCount'] ?? null);
        $t->same(1, $report['validSwitchCount'] ?? null);
        $t->same(1, $report['invalidSwitchCount'] ?? null);
        $t->same(['switch'], $asset['reviewFlags']);
        $t->same(true, $asset['flags']['switch'] ?? null);
        $t->same(0, $asset['referenceCount']);
        $t->same([], $asset['references']);
        $t->same(2, $asset['switchCount']);
        $t->same(3, $asset['switchCaseCount']);
        $t->same(1, $asset['switchDefaultCount']);
        $t->same(1, $asset['validSwitchCount']);
        $t->same(1, $asset['invalidSwitchCount']);

        $layout = $asset['switches'][0];
        $t->same('layout-choice', $layout['id']);
        $t->same('switch', $layout['element']);
        $t->same(['layout-review'], $layout['classes']);
        $t->same(2, $layout['caseCount']);
        $t->same(1, $layout['defaultCount']);
        $t->same(true, $layout['valid']);
        $t->same([], $layout['diagnostics']);
        $t->same('svg-case', $layout['cases'][0]['id']);
        $t->same('http://www.w3.org/2000/svg', $layout['cases'][0]['requiredNamespace']);
        $t->same([], $layout['cases'][0]['requiredModules']);
        $t->same('Reading-system SVG path.', $layout['cases'][0]['text']);
        $t->same(true, $layout['cases'][0]['valid']);
        $t->same('math-case', $layout['cases'][1]['id']);
        $t->same(null, $layout['cases'][1]['requiredNamespace']);
        $t->same(['mathml'], $layout['cases'][1]['requiredModules']);
        $t->same('MathML reading-system path.', $layout['cases'][1]['text']);
        $t->same('fallback-case', $layout['defaults'][0]['id']);
        $t->same('Fallback text preserved for WordPress review.', $layout['defaults'][0]['text']);

        $invalid = $asset['switches'][1];
        $t->same('invalid-choice', $invalid['id']);
        $t->same(false, $invalid['valid']);
        $t->same(['missing-epub-switch-default', 'epub-switch-case-missing-requirement'], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $invalid['diagnostics']));
        $t->same(false, $invalid['cases'][0]['valid']);
        $t->same('epub-switch-case-missing-requirement', $invalid['cases'][0]['diagnostics'][0]['type']);
        $t->same(2, count($asset['diagnostics']));
        $t->same(['missing-epub-switch-default', 'epub-switch-case-missing-requirement'], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $asset['diagnostics']));
        $t->same('/OEBPS/text/switch-content.xhtml', $switchBlock->attr('part'));
        $t->same(['switch'], $switchBlock->attr('contentResourceReviewFlags'));
        $t->same($asset['flags'], $switchBlock->attr('contentResourceFlags'));
        $t->same($asset['references'], $switchBlock->attr('contentReferences'));
        $t->same($asset['switches'], $switchBlock->attr('contentSwitches'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
    },
    'preserves EPUB trigger XHTML controls for static review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $triggerXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xmlns:ev="http://www.w3.org/2001/xml-events">
  <head>
    <title>Trigger controls</title>
    <epub:trigger id="play-trigger" ev:observer="play-button" ev:event="click" action="play" ref="intro-audio"/>
    <epub:trigger id="hide-trigger" ev:observer="details-toggle" ev:event="click" ev:defaultAction="cancel" ev:phase="default" ev:propagate="stop" action="hide" ref="review-details"/>
    <epub:trigger id="bad-trigger" ev:observer="missing-button" ev:event="click" action="spin" ref="missing-audio"/>
    <epub:trigger id="empty-trigger"/>
  </head>
  <body>
    <h1>Trigger controls</h1>
    <span id="play-button" role="button" tabindex="0">Play audio</span>
    <span id="details-toggle" role="button" tabindex="0">Toggle details</span>
    <audio id="intro-audio" src="../audio/intro.mp3"/>
    <aside id="review-details">Reviewer-only details.</aside>
  </body>
</html>
XML;
        $opfWithTriggerContent = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="trigger-content" href="text/trigger-content.xhtml" media-type="application/xhtml+xml"/>',
            $opfXml
        );
        $opfWithTriggerContent = str_replace(
            '</spine>',
            '<itemref idref="trigger-content"/></spine>',
            $opfWithTriggerContent
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithTriggerContent,
            null,
            [
                ['name' => 'OEBPS/text/trigger-content.xhtml', 'data' => $triggerXhtml],
                ['name' => 'OEBPS/audio/intro.mp3', 'data' => 'INTRO-AUDIO'],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/trigger-content.xhtml'];
        $triggerBlock = $result['document']->children[2];

        $t->same(1, $report['triggerAssetCount'] ?? null);
        $t->same(4, $report['triggerCount'] ?? null);
        $t->same(['trigger'], $asset['reviewFlags']);
        $t->same(true, $asset['flags']['trigger'] ?? null);
        $t->same(4, count($asset['triggers']));
        $t->same(2, $asset['validTriggerCount']);
        $t->same(2, $asset['invalidTriggerCount']);

        $play = $asset['triggers'][0];
        $t->same('play-trigger', $play['id']);
        $t->same('play', $play['action']);
        $t->same(true, $play['actionValid']);
        $t->same('intro-audio', $play['ref']);
        $t->same(true, $play['refExists']);
        $t->same('audio', $play['refElement']);
        $t->same('play-button', $play['observer']);
        $t->same(true, $play['observerExists']);
        $t->same('span', $play['observerElement']);
        $t->same('click', $play['event']);
        $t->same([], $play['diagnostics']);

        $hide = $asset['triggers'][1];
        $t->same('hide', $hide['action']);
        $t->same('review-details', $hide['ref']);
        $t->same(true, $hide['refExists']);
        $t->same('aside', $hide['refElement']);
        $t->same('details-toggle', $hide['observer']);
        $t->same('cancel', $hide['defaultAction']);
        $t->same('default', $hide['phase']);
        $t->same('stop', $hide['propagate']);
        $t->same(true, $hide['valid']);

        $bad = $asset['triggers'][2];
        $t->same('bad-trigger', $bad['id']);
        $t->same('spin', $bad['action']);
        $t->same(false, $bad['actionValid']);
        $t->same(false, $bad['refExists']);
        $t->same(false, $bad['observerExists']);
        $t->same(['invalid-epub-trigger-action', 'unresolved-epub-trigger-ref', 'unresolved-epub-trigger-observer'], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $bad['diagnostics']));

        $empty = $asset['triggers'][3];
        $t->same('empty-trigger', $empty['id']);
        $t->same(null, $empty['action']);
        $t->same(null, $empty['ref']);
        $t->same(null, $empty['event']);
        $t->same(null, $empty['observer']);
        $t->same(['missing-epub-trigger-action', 'missing-epub-trigger-ref', 'missing-epub-trigger-event', 'missing-epub-trigger-observer'], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $empty['diagnostics']));

        $t->same(7, count($asset['diagnostics']));
        $t->same([
            'invalid-epub-trigger-action',
            'unresolved-epub-trigger-ref',
            'unresolved-epub-trigger-observer',
            'missing-epub-trigger-action',
            'missing-epub-trigger-ref',
            'missing-epub-trigger-event',
            'missing-epub-trigger-observer',
        ], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $asset['diagnostics']));
        $t->same($asset['triggers'], $triggerBlock->attr('contentTriggers'));
        $t->same($asset['flags'], $triggerBlock->attr('contentResourceFlags'));
        $t->same(['trigger'], $triggerBlock->attr('contentResourceReviewFlags'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
    },
    'reports EPUB XHTML semantic type annotations for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $semanticXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="en">
  <body>
    <section id="bodymatter" class="chapter section" epub:type="bodymatter chapter" xml:lang="en-US" dir="ltr">
      <h1>Semantic review</h1>
      <p><a id="note-ref" epub:type="noteref" href="#fn-1">1</a></p>
      <span id="page-7" epub:type="pagebreak" title="7">7</span>
      <aside id="fn-1" epub:type="footnote" role="doc-footnote">Footnote source text.</aside>
      <section id="refs" epub:type="bibliography"><p>Reference list.</p></section>
      <a id="missing-note-ref" epub:type="noteref" href="#missing-note">missing note</a>
    </section>
  </body>
</html>
XML;
        $opfWithSemanticContent = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="semantic-content" href="text/semantic.xhtml" media-type="application/xhtml+xml"/>',
            $opfXml
        );
        $opfWithSemanticContent = str_replace(
            '</spine>',
            '<itemref idref="semantic-content"/></spine>',
            $opfWithSemanticContent
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithSemanticContent,
            null,
            [
                ['name' => 'OEBPS/text/semantic.xhtml', 'data' => $semanticXhtml],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/semantic.xhtml'];
        $semanticBlock = $result['document']->children[2];

        $t->same(6, $asset['semanticCount']);
        $t->same(['bodymatter', 'chapter', 'noteref', 'pagebreak', 'footnote', 'bibliography'], $asset['semanticTypes']);
        $t->same(1, $asset['semanticDiagnosticCount']);
        $t->same('unresolved-xhtml-semantic-fragment', $asset['semanticDiagnostics'][0]['type']);
        $t->same('missing-note', $asset['semanticDiagnostics'][0]['fragment']);

        $bodymatter = $asset['semantics'][0];
        $t->same('section', $bodymatter['element']);
        $t->same('bodymatter', $bodymatter['primaryType']);
        $t->same(['bodymatter', 'chapter'], $bodymatter['types']);
        $t->same('bodymatter', $bodymatter['id']);
        $t->same(['chapter', 'section'], $bodymatter['classes']);
        $t->same('en-US', $bodymatter['language']);
        $t->same('ltr', $bodymatter['direction']);
        $t->same(null, $bodymatter['href']);

        $noteref = $asset['semanticItemsByType']['noteref'][0];
        $t->same('note-ref', $noteref['id']);
        $t->same('#fn-1', $noteref['href']);
        $t->same('/OEBPS/text/semantic.xhtml#fn-1', $noteref['target']);
        $t->same('/OEBPS/text/semantic.xhtml', $noteref['part']);
        $t->same('fn-1', $noteref['fragment']);
        $t->same(true, $noteref['fragmentExists']);
        $t->same('semantic-content', $noteref['manifestId']);
        $t->same([], $noteref['diagnostics']);

        $page = $asset['semanticItemsByType']['pagebreak'][0];
        $t->same('page-7', $page['id']);
        $t->same('span', $page['element']);
        $t->same('7', $page['attributes']['title']);

        $footnote = $asset['semanticItemsByType']['footnote'][0];
        $t->same('fn-1', $footnote['id']);
        $t->same('aside', $footnote['element']);
        $t->same('doc-footnote', $footnote['attributes']['role']);

        $bibliography = $asset['semanticItemsByType']['bibliography'][0];
        $t->same('refs', $bibliography['id']);
        $t->same('section', $bibliography['element']);

        $missing = $asset['semanticItemsByType']['noteref'][1];
        $t->same('missing-note-ref', $missing['id']);
        $t->same('#missing-note', $missing['href']);
        $t->same(true, $missing['exists']);
        $t->same(false, $missing['fragmentExists']);
        $t->same('unresolved-xhtml-semantic-fragment', $missing['diagnostics'][0]['type']);

        $t->same($asset['semantics'], $semanticBlock->attr('contentSemantics'));
        $t->same($asset['semanticTypes'], $semanticBlock->attr('contentSemanticTypes'));
        $t->same($asset['semanticDiagnostics'], $semanticBlock->attr('contentSemanticDiagnostics'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
    },
    'reconciles OPF remote-resources declarations with observed XHTML resource references' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $declaredRemoteXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body><p><img src="https://cdn.example.test/epub/declared.png" alt="declared remote"/></p></body>
</html>
XML;
        $undeclaredRemoteXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <p><img src="//cdn.example.test/epub/undeclared.png" alt="undeclared remote"/></p>
    <iframe src="https://widgets.example.test/review-frame.html"></iframe>
  </body>
</html>
XML;
        $declaredCleanXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body><p>Remote resources are declared for a scripted reading-system path, but none are directly visible in the XHTML scan.</p></body>
</html>
XML;
        $opfWithRemoteResources = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>'
            . '<item id="remote-declared" href="text/remote-declared.xhtml" media-type="application/xhtml+xml" properties="remote-resources"/>'
            . '<item id="remote-undeclared" href="text/remote-undeclared.xhtml" media-type="application/xhtml+xml"/>'
            . '<item id="remote-clean" href="text/remote-clean.xhtml" media-type="application/xhtml+xml" properties="remote-resources"/>',
            $opfXml
        );
        $opfWithRemoteResources = str_replace(
            '</spine>',
            '<itemref idref="remote-declared"/><itemref idref="remote-undeclared"/><itemref idref="remote-clean"/></spine>',
            $opfWithRemoteResources
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithRemoteResources,
            null,
            [
                ['name' => 'OEBPS/text/remote-declared.xhtml', 'data' => $declaredRemoteXhtml],
                ['name' => 'OEBPS/text/remote-undeclared.xhtml', 'data' => $undeclaredRemoteXhtml],
                ['name' => 'OEBPS/text/remote-clean.xhtml', 'data' => $declaredCleanXhtml],
            ]
        ));

        $remoteResources = $result['remoteResources'];
        $t->same(true, $remoteResources['present']);
        $t->same(2, $remoteResources['declaredCount']);
        $t->same(2, $remoteResources['observedAssetCount']);
        $t->same(3, $remoteResources['remoteReferenceCount']);
        $t->same(3, $remoteResources['xhtmlExternalReferenceCount']);
        $t->same(1, $remoteResources['undeclaredAssetCount']);
        $t->same(1, $remoteResources['declaredButUnobservedCount']);

        $t->same('remote-declared', $remoteResources['declaredItems'][0]['id']);
        $t->same('/OEBPS/text/remote-declared.xhtml', $remoteResources['declaredItems'][0]['part']);
        $t->same('remote-clean', $remoteResources['declaredItems'][1]['id']);
        $t->same('remote-declared', $remoteResources['declaredItemsByPart']['/OEBPS/text/remote-declared.xhtml']['id']);

        $declaredObserved = $remoteResources['observedItemsByPart']['/OEBPS/text/remote-declared.xhtml'];
        $t->same('remote-declared', $declaredObserved['id']);
        $t->same(true, $declaredObserved['manifestDeclared']);
        $t->same(1, $declaredObserved['remoteReferenceCount']);
        $t->same('https://cdn.example.test/epub/declared.png', $declaredObserved['remoteReferences'][0]['target']);
        $t->same([], $declaredObserved['diagnostics']);

        $undeclaredObserved = $remoteResources['observedItemsByPart']['/OEBPS/text/remote-undeclared.xhtml'];
        $t->same('remote-undeclared', $undeclaredObserved['id']);
        $t->same(false, $undeclaredObserved['manifestDeclared']);
        $t->same(2, $undeclaredObserved['remoteReferenceCount']);
        $t->same('//cdn.example.test/epub/undeclared.png', $undeclaredObserved['remoteReferences'][0]['target']);
        $t->same('https://widgets.example.test/review-frame.html', $undeclaredObserved['remoteReferences'][1]['target']);
        $t->same('undeclared-xhtml-remote-resources', $undeclaredObserved['diagnostics'][0]['type']);

        $t->same('remote-undeclared', $remoteResources['undeclaredItems'][0]['id']);
        $t->same('remote-clean', $remoteResources['declaredButUnobservedItems'][0]['id']);
        $t->same('declared-remote-resources-not-observed', $remoteResources['declaredButUnobservedItems'][0]['diagnostics'][0]['type']);
        $t->same('undeclared-xhtml-remote-resources', $remoteResources['diagnostics'][0]['type']);
        $t->same('declared-remote-resources-not-observed', $remoteResources['diagnostics'][1]['type']);
        $t->same($remoteResources, $result['importReport']['remoteResources']);
        $t->same($remoteResources, $result['document']->attr('remoteResources'));

        $declaredBlock = $result['document']->children[2];
        $undeclaredBlock = $result['document']->children[3];
        $cleanBlock = $result['document']->children[4];
        $t->same(['remote-resources'], $declaredBlock->attr('resourceReviewFlags'));
        $t->same(['remote-resources'], $declaredBlock->attr('contentResourceReviewFlags'));
        $t->same([], $undeclaredBlock->attr('resourceReviewFlags'));
        $t->same(['remote-resources'], $undeclaredBlock->attr('contentResourceReviewFlags'));
        $t->same(['remote-resources'], $cleanBlock->attr('resourceReviewFlags'));
        $t->same([], $cleanBlock->attr('contentResourceReviewFlags'));
    },
    'reconciles OPF content feature properties with observed XHTML scans' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $undeclaredFeaturesXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <script>window.reviewFeature = true;</script>
    <math xmlns="http://www.w3.org/1998/Math/MathML"><mi>x</mi></math>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>
    <epub:switch><epub:default><p>Fallback branch.</p></epub:default></epub:switch>
  </body>
</html>
XML;
        $overdeclaredFeaturesXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body><p>Manifest declares optional content features that are not visible in the bounded XHTML scan.</p></body>
</html>
XML;
        $mixedFeaturesXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <math xmlns="http://www.w3.org/1998/Math/MathML"><mi>y</mi></math>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><title>Review icon</title></svg>
  </body>
</html>
XML;
        $opfWithFeatureReconciliation = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>'
            . '<item id="undeclared-features" href="text/undeclared-features.xhtml" media-type="application/xhtml+xml"/>'
            . '<item id="overdeclared-features" href="text/overdeclared-features.xhtml" media-type="application/xhtml+xml" properties="mathml svg scripted switch"/>'
            . '<item id="mixed-features" href="text/mixed-features.xhtml" media-type="application/xhtml+xml" properties="mathml"/>',
            $opfXml
        );
        $opfWithFeatureReconciliation = str_replace(
            '</spine>',
            '<itemref idref="undeclared-features"/>'
            . '<itemref idref="overdeclared-features"/>'
            . '<itemref idref="mixed-features"/>'
            . '</spine>',
            $opfWithFeatureReconciliation
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithFeatureReconciliation,
            null,
            [
                ['name' => 'OEBPS/text/undeclared-features.xhtml', 'data' => $undeclaredFeaturesXhtml],
                ['name' => 'OEBPS/text/overdeclared-features.xhtml', 'data' => $overdeclaredFeaturesXhtml],
                ['name' => 'OEBPS/text/mixed-features.xhtml', 'data' => $mixedFeaturesXhtml],
            ]
        ));

        $report = $result['resourceProperties']['contentFeatureReconciliation'];
        $t->same(true, $report['present']);
        $t->same(['mathml', 'svg', 'scripted', 'switch'], $report['features']);
        $t->same(3, $report['itemCount']);
        $t->same(5, $report['declaredFeatureCount']);
        $t->same(6, $report['observedFeatureCount']);
        $t->same(1, $report['matchedFeatureCount']);
        $t->same(5, $report['undeclaredFeatureCount']);
        $t->same(4, $report['declaredButUnobservedFeatureCount']);
        $t->same(2, $report['undeclaredItemCount']);
        $t->same(1, $report['declaredButUnobservedItemCount']);
        $t->same(3, $report['diagnosticCount']);

        $undeclared = $report['itemsById']['undeclared-features'];
        $t->same([], $undeclared['declaredFeatures']);
        $t->same(['mathml', 'svg', 'scripted', 'switch'], $undeclared['observedFeatures']);
        $t->same(['mathml', 'svg', 'scripted', 'switch'], $undeclared['undeclaredFeatures']);
        $t->same('undeclared-xhtml-content-feature-properties', $undeclared['diagnostics'][0]['type']);
        $t->same(['mathml', 'svg', 'scripted', 'switch'], $undeclared['diagnostics'][0]['features']);

        $overdeclared = $report['itemsById']['overdeclared-features'];
        $t->same(['mathml', 'svg', 'scripted', 'switch'], $overdeclared['declaredFeatures']);
        $t->same([], $overdeclared['observedFeatures']);
        $t->same(['mathml', 'svg', 'scripted', 'switch'], $overdeclared['declaredButUnobservedFeatures']);
        $t->same('declared-xhtml-content-feature-properties-not-observed', $overdeclared['diagnostics'][0]['type']);
        $t->same(['mathml', 'svg', 'scripted', 'switch'], $overdeclared['diagnostics'][0]['features']);

        $mixed = $report['itemsById']['mixed-features'];
        $t->same(['mathml'], $mixed['declaredFeatures']);
        $t->same(['mathml', 'svg'], $mixed['observedFeatures']);
        $t->same(['mathml'], $mixed['matchedFeatures']);
        $t->same(['svg'], $mixed['undeclaredFeatures']);
        $t->same([], $mixed['declaredButUnobservedFeatures']);
        $t->same('/OEBPS/text/mixed-features.xhtml', $report['itemsByPart']['/OEBPS/text/mixed-features.xhtml']['part']);

        $t->same('undeclared-xhtml-content-feature-properties', $report['diagnostics'][0]['type']);
        $t->same('declared-xhtml-content-feature-properties-not-observed', $report['diagnostics'][1]['type']);
        $t->same('undeclared-xhtml-content-feature-properties', $report['diagnostics'][2]['type']);
        $t->same($report, $result['importReport']['resourceProperties']['contentFeatureReconciliation']);
        $t->same($report, $result['document']->attr('resourceProperties')['contentFeatureReconciliation']);
    },
    'summarizes EPUB XHTML embedded media object and frame resources for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $embeddedXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <audio id="intro-audio-player" src="../audio/intro.mp3"/>
    <video id="review-video" src="../video/review.mp4" poster="../images/poster.png">
      <source id="review-video-hd" src="../video/review-hd.mp4" type="video/mp4"/>
      <track id="review-captions" kind="captions" srclang="en" label="English" src="../captions/review.vtt"/>
    </video>
    <object id="model-review" data="../interactive/model.bin" type="application/x-model"/>
    <embed id="remote-widget" src="https://widgets.example.test/epub/widget.html" type="text/html"/>
    <iframe id="missing-frame" src="../frames/missing.xhtml"></iframe>
  </body>
</html>
XML;
        $opfWithEmbeddedResources = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>'
            . '<item id="embedded-media" href="text/embedded.xhtml" media-type="application/xhtml+xml"/>'
            . '<item id="intro-audio" href="audio/intro.mp3" media-type="audio/mpeg"/>'
            . '<item id="review-video" href="video/review.mp4" media-type="video/mp4"/>'
            . '<item id="review-video-hd" href="video/review-hd.mp4" media-type="video/mp4"/>'
            . '<item id="poster-image" href="images/poster.png" media-type="image/png"/>'
            . '<item id="review-captions" href="captions/review.vtt" media-type="text/vtt"/>'
            . '<item id="model-bin" href="interactive/model.bin" media-type="application/x-model"/>',
            $opfXml
        );
        $opfWithEmbeddedResources = str_replace(
            '</spine>',
            '<itemref idref="embedded-media"/></spine>',
            $opfWithEmbeddedResources
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithEmbeddedResources,
            null,
            [
                ['name' => 'OEBPS/text/embedded.xhtml', 'data' => $embeddedXhtml],
                ['name' => 'OEBPS/audio/intro.mp3', 'data' => 'INTRO-MP3'],
                ['name' => 'OEBPS/video/review.mp4', 'data' => 'VIDEO-MP4'],
                ['name' => 'OEBPS/video/review-hd.mp4', 'data' => 'VIDEO-HD'],
                ['name' => 'OEBPS/images/poster.png', 'data' => 'POSTER-PNG', 'compressionMethod' => 0],
                ['name' => 'OEBPS/captions/review.vtt', 'data' => "WEBVTT\n\n00:00.000 --> 00:01.000\nReview"],
                ['name' => 'OEBPS/interactive/model.bin', 'data' => 'MODEL-BYTES'],
            ]
        ));

        $report = $result['xhtmlResourceReport'];
        $asset = $report['itemsByPart']['/OEBPS/text/embedded.xhtml'];
        $block = $result['document']->children[2];

        $t->same(1, $report['embeddedResourceAssetCount']);
        $t->same(8, $report['embeddedResourceCount']);
        $t->same(1, $report['externalEmbeddedResourceCount']);
        $t->same(1, $report['missingEmbeddedResourceCount']);
        $t->same(0, $report['encryptedEmbeddedResourceCount']);
        $t->same(['audio', 'video', 'poster', 'source', 'track', 'object', 'embed', 'iframe'], $report['embeddedResourceKinds']);
        $t->same(2, count($report['embeddedResourceDiagnostics']));
        $t->same(['external-xhtml-content-reference', 'missing-xhtml-content-reference'], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $report['embeddedResourceDiagnostics']));

        $t->same(8, $asset['embeddedResourceCount']);
        $t->same(['audio', 'video', 'poster', 'source', 'track', 'object', 'embed', 'iframe'], $asset['embeddedResourceKinds']);
        $t->same(1, $asset['externalEmbeddedResourceCount']);
        $t->same(1, $asset['missingEmbeddedResourceCount']);

        $audio = $asset['embeddedResources'][0];
        $t->same('audio', $audio['kind']);
        $t->same('media-playback', $audio['policy']);
        $t->same('../audio/intro.mp3', $audio['href']);
        $t->same('/OEBPS/audio/intro.mp3', $audio['part']);
        $t->same('intro-audio', $audio['manifestId']);
        $t->same('audio/mpeg', $audio['mediaType']);
        $t->same(9, $audio['byteLength']);
        $t->same(false, $audio['requiresReview']);

        $video = $asset['embeddedResourcesByKind']['video'][0];
        $t->same('/OEBPS/video/review.mp4', $video['part']);
        $t->same('review-video', $video['manifestId']);
        $t->same('video/mp4', $video['mediaType']);

        $poster = $asset['embeddedResourcesByKind']['poster'][0];
        $t->same('media-poster', $poster['policy']);
        $t->same('/OEBPS/images/poster.png', $poster['part']);
        $t->same('poster-image', $poster['manifestId']);

        $source = $asset['embeddedResourcesByKind']['source'][0];
        $t->same('/OEBPS/video/review-hd.mp4', $source['part']);
        $t->same('review-video-hd', $source['manifestId']);

        $track = $asset['embeddedResourcesByKind']['track'][0];
        $t->same('timed-text-track', $track['policy']);
        $t->same('/OEBPS/captions/review.vtt', $track['part']);
        $t->same('review-captions', $track['manifestId']);
        $t->same('text/vtt', $track['mediaType']);

        $object = $asset['embeddedResourcesByKind']['object'][0];
        $t->same('interactive-embedded-content', $object['policy']);
        $t->same(true, $object['requiresReview']);
        $t->same('/OEBPS/interactive/model.bin', $object['part']);
        $t->same('model-bin', $object['manifestId']);

        $embed = $asset['embeddedResourcesByKind']['embed'][0];
        $t->same(true, $embed['external']);
        $t->same('https://widgets.example.test/epub/widget.html', $embed['target']);
        $t->same('external-xhtml-content-reference', $embed['diagnostics'][0]['type']);

        $iframe = $asset['embeddedResourcesByKind']['iframe'][0];
        $t->same(false, $iframe['exists']);
        $t->same('/OEBPS/frames/missing.xhtml', $iframe['part']);
        $t->same('missing-xhtml-content-reference', $iframe['diagnostics'][0]['type']);

        $t->same($asset['embeddedResources'], $block->attr('contentEmbeddedResources'));
        $t->same($asset['embeddedResourceDiagnostics'], $block->attr('contentEmbeddedResourceDiagnostics'));
        $t->same($report, $result['importReport']['xhtmlResourceReport']);
        $t->same($report, $result['document']->attr('xhtmlResourceReport'));
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
    'reports conflicting EPUB cover image candidates for import review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithCoverConflict = str_replace(
            '<meta name="cover" content="cover-image"/>',
            '<meta name="cover" content="legacy-cover"/>',
            $opfXml
        );
        $opfWithCoverConflict = str_replace(
            '<item id="cover-image" href="images/cover.png" media-type="image/png" properties="cover-image"/>',
            '<item id="cover-image" href="images/cover.png" media-type="image/png" properties="cover-image"/>'
            . '<item id="legacy-cover" href="images/legacy-cover.jpg" media-type="image/jpeg"/>',
            $opfWithCoverConflict
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithCoverConflict,
            null,
            [
                ['name' => 'OEBPS/images/legacy-cover.jpg', 'data' => 'LEGACY-JPEG', 'compressionMethod' => 0],
            ]
        ));
        $assets = $result['importReport']['assets'];

        $t->same(2, $assets['coverImageCount']);
        $t->same('cover-image', $assets['coverImage']['id']);
        $t->same('/OEBPS/images/cover.png', $assets['coverImage']['part']);
        $t->same(['cover-image', 'legacy-cover'], array_map(
            static fn (array $asset): string => (string) $asset['id'],
            $assets['coverImages']
        ));
        $t->same(['manifest-property-cover-image'], $assets['coverImages'][0]['coverImageSources']);
        $t->same(['meta-name-cover'], $assets['coverImages'][1]['coverImageSources']);
        $t->same(hash('sha256', 'LEGACY-JPEG'), $assets['coverImages'][1]['byteSha256']);
        $t->same(1, $assets['coverImageDiagnosticCount']);
        $t->same('multiple-cover-image-candidates', $assets['coverImageDiagnostics'][0]['type']);
        $t->same('cover-image', $assets['coverImageDiagnostics'][0]['selectedId']);
        $t->same('legacy-cover', $assets['coverImageDiagnostics'][0]['metaCoverItemId']);
        $t->same(['cover-image'], $assets['coverImageDiagnostics'][0]['manifestCoverImageIds']);
        $t->same(['legacy-cover'], $assets['coverImageDiagnostics'][0]['metaCoverImageIds']);
        $t->same([
            'cover-image' => ['manifest-property-cover-image'],
            'legacy-cover' => ['meta-name-cover'],
        ], $assets['coverImageDiagnostics'][0]['sourcesById']);
        $t->same($assets['coverImageDiagnostics'][0], $assets['diagnostics'][0]);
        $t->same($assets, $result['assetReport']);
        $t->same($assets, $result['importReport']['assets']);

        $opfWithMissingLegacyCover = str_replace(
            '<meta name="cover" content="cover-image"/>',
            '<meta name="cover" content="missing-cover"/>',
            $opfXml
        );
        $missingResult = (new EpubReader())->readPackage($buildEpubPackage($opfWithMissingLegacyCover));
        $missingAssets = $missingResult['importReport']['assets'];
        $t->same(1, $missingAssets['coverImageCount']);
        $t->same('cover-image', $missingAssets['coverImage']['id']);
        $t->same(1, $missingAssets['coverImageDiagnosticCount']);
        $t->same('missing-meta-cover-image', $missingAssets['coverImageDiagnostics'][0]['type']);
        $t->same('missing-cover', $missingAssets['coverImageDiagnostics'][0]['metaCoverItemId']);
        $t->same(['cover-image'], $missingAssets['coverImageDiagnostics'][0]['manifestCoverImageIds']);
        $t->same('cover-image', $missingAssets['coverImageDiagnostics'][0]['selectedId']);
    },
    'reports non-spine OPF asset fallback chains for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithAssetFallbacks = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="interactive-map" href="widgets/map.bin" media-type="application/x-review-widget" fallback="interactive-poster"/>'
            . '<item id="interactive-poster" href="images/map-poster.png" media-type="image/png"/>'
            . '<item id="broken-widget" href="widgets/broken.bin" media-type="application/x-broken-widget" fallback="missing-poster"/>'
            . '<item id="cyclic-widget-a" href="widgets/cyclic-a.bin" media-type="application/x-cycle" fallback="cyclic-widget-b"/>'
            . '<item id="cyclic-widget-b" href="widgets/cyclic-b.bin" media-type="application/x-cycle" fallback="cyclic-widget-a"/>'
            . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithAssetFallbacks,
            null,
            [
                ['name' => 'OEBPS/widgets/map.bin', 'data' => 'WIDGET-DATA'],
                ['name' => 'OEBPS/images/map-poster.png', 'data' => 'POSTER-PNG', 'compressionMethod' => 0],
                ['name' => 'OEBPS/widgets/broken.bin', 'data' => 'BROKEN-WIDGET'],
                ['name' => 'OEBPS/widgets/cyclic-a.bin', 'data' => 'A'],
                ['name' => 'OEBPS/widgets/cyclic-b.bin', 'data' => 'B'],
            ]
        ));

        $assets = $result['importReport']['assets'];
        $assetById = [];
        foreach ($assets['items'] as $asset) {
            $assetById[$asset['id']] = $asset;
        }

        $map = $assetById['interactive-map'];
        $t->same('interactive-poster', $map['fallbackId']);
        $t->same('interactive-poster', $map['fallbackContentId']);
        $t->same('/OEBPS/images/map-poster.png', $map['fallbackContentPart']);
        $t->same('image/png', $map['fallbackContentMediaType']);
        $t->same(true, $map['fallbackAttachmentCandidate']);
        $t->same('image', $map['fallbackAttachmentRole']);
        $t->same(hash('sha256', 'POSTER-PNG'), $map['fallbackByteSha256']);
        $t->same([], $map['fallbackDiagnostics']);
        $t->same(1, count($map['fallbackChain']));
        $t->same('interactive-poster', $map['fallbackChain'][0]['id']);
        $t->same('/OEBPS/images/map-poster.png', $map['fallbackChain'][0]['part']);
        $t->same(true, $map['fallbackChain'][0]['attachmentCandidate']);
        $t->same(hash('sha256', 'POSTER-PNG'), $map['fallbackChain'][0]['byteSha256']);

        $broken = $assetById['broken-widget'];
        $t->same('missing-poster', $broken['fallbackId']);
        $t->same(null, $broken['fallbackContentId']);
        $t->same(false, $broken['fallbackAttachmentCandidate']);
        $t->same('missing-asset-fallback-manifest-item', $broken['fallbackDiagnostics'][0]['type']);
        $t->same('missing-poster', $broken['fallbackDiagnostics'][0]['fallback']);
        $t->same($broken['fallbackDiagnostics'], $broken['diagnostics']);

        $cycle = $assetById['cyclic-widget-a'];
        $t->same('cyclic-widget-b', $cycle['fallbackId']);
        $t->same(1, count($cycle['fallbackChain']));
        $t->same('cyclic-widget-b', $cycle['fallbackChain'][0]['id']);
        $t->same('cyclic-asset-fallback-chain', $cycle['fallbackDiagnostics'][0]['type']);
        $t->same('cyclic-widget-a', $cycle['fallbackDiagnostics'][0]['fallback']);

        $t->same(4, $assets['fallbackCount']);
        $t->same(3, $assets['fallbackDiagnosticCount']);
        $t->same('interactive-map', $assets['fallbackItems'][0]['id']);
        $t->same('broken-widget', $assets['fallbackDiagnostics'][0]['id']);
        $t->same('cyclic-widget-a', $assets['fallbackDiagnostics'][1]['id']);
        $t->same('cyclic-widget-b', $assets['fallbackDiagnostics'][2]['id']);
    },
    'reports OPF asset fallback-style chains for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $styleCss = 'body { color: #333; }';
        $opfWithFallbackStyles = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="interactive-map" href="widgets/map.bin" media-type="application/x-review-widget" fallback-style="map-style"/>'
            . '<item id="map-style" href="styles/map.css" media-type="text/css"/>'
            . '<item id="missing-style-widget" href="widgets/missing-style.bin" media-type="application/x-review-widget" fallback-style="missing-style"/>'
            . '<item id="bad-style-widget" href="widgets/bad-style.bin" media-type="application/x-review-widget" fallback-style="cover-image"/>'
            . '<item id="cyclic-style-a" href="widgets/style-a.bin" media-type="application/x-style-cycle" fallback-style="cyclic-style-b"/>'
            . '<item id="cyclic-style-b" href="widgets/style-b.bin" media-type="application/x-style-cycle" fallback-style="cyclic-style-a"/>'
            . '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithFallbackStyles,
            null,
            [
                ['name' => 'OEBPS/widgets/map.bin', 'data' => 'WIDGET-DATA'],
                ['name' => 'OEBPS/styles/map.css', 'data' => $styleCss],
                ['name' => 'OEBPS/widgets/missing-style.bin', 'data' => 'MISSING-STYLE'],
                ['name' => 'OEBPS/widgets/bad-style.bin', 'data' => 'BAD-STYLE'],
                ['name' => 'OEBPS/widgets/style-a.bin', 'data' => 'STYLE-A'],
                ['name' => 'OEBPS/widgets/style-b.bin', 'data' => 'STYLE-B'],
            ]
        ));

        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }
        $assetById = [];
        foreach ($result['importReport']['assets']['items'] as $asset) {
            $assetById[$asset['id']] = $asset;
        }

        $map = $assetById['interactive-map'];
        $t->same('map-style', $manifestById['interactive-map']['fallbackStyle']);
        $t->same('map-style', $map['fallbackStyleId']);
        $t->same('map-style', $map['fallbackStyleContentId']);
        $t->same('/OEBPS/styles/map.css', $map['fallbackStyleContentPart']);
        $t->same('text/css', $map['fallbackStyleContentMediaType']);
        $t->same(hash('sha256', $styleCss), $map['fallbackStyleByteSha256']);
        $t->same([], $map['fallbackStyleDiagnostics']);
        $t->same(1, count($map['fallbackStyleChain']));
        $t->same('map-style', $map['fallbackStyleChain'][0]['id']);
        $t->same('/OEBPS/styles/map.css', $map['fallbackStyleChain'][0]['part']);
        $t->same(hash('sha256', $styleCss), $map['fallbackStyleChain'][0]['byteSha256']);

        $missing = $assetById['missing-style-widget'];
        $t->same('missing-style', $missing['fallbackStyleId']);
        $t->same(null, $missing['fallbackStyleContentId']);
        $t->same('missing-asset-fallback-style-manifest-item', $missing['fallbackStyleDiagnostics'][0]['type']);
        $t->same('missing-style', $missing['fallbackStyleDiagnostics'][0]['fallbackStyle']);
        $t->same($missing['fallbackStyleDiagnostics'], $missing['diagnostics']);

        $badStyle = $assetById['bad-style-widget'];
        $t->same('cover-image', $badStyle['fallbackStyleId']);
        $t->same('cover-image', $badStyle['fallbackStyleContentId']);
        $t->same('/OEBPS/images/cover.png', $badStyle['fallbackStyleContentPart']);
        $t->same('image/png', $badStyle['fallbackStyleContentMediaType']);
        $t->same('non-css-asset-fallback-style', $badStyle['fallbackStyleDiagnostics'][0]['type']);

        $cycle = $assetById['cyclic-style-a'];
        $t->same('cyclic-style-b', $cycle['fallbackStyleId']);
        $t->same(1, count($cycle['fallbackStyleChain']));
        $t->same('cyclic-style-b', $cycle['fallbackStyleChain'][0]['id']);
        $t->same('cyclic-asset-fallback-style-chain', $cycle['fallbackStyleDiagnostics'][0]['type']);
        $t->same('cyclic-style-a', $cycle['fallbackStyleDiagnostics'][0]['fallbackStyle']);

        $assets = $result['importReport']['assets'];
        $t->same(5, $assets['fallbackStyleCount']);
        $t->same(5, count($assets['fallbackStyleItems']));
        $t->same(4, $assets['fallbackStyleDiagnosticCount']);
        $t->same('interactive-map', $assets['fallbackStyleItems'][0]['id']);
        $t->same('missing-style-widget', $assets['fallbackStyleDiagnostics'][0]['id']);
        $t->same('bad-style-widget', $assets['fallbackStyleDiagnostics'][1]['id']);
        $t->same('cyclic-style-a', $assets['fallbackStyleDiagnostics'][2]['id']);
        $t->same('cyclic-style-b', $assets['fallbackStyleDiagnostics'][3]['id']);
        $t->same($assets, $result['assetReport']);
        $t->same($assets, $result['document']->attr('assets'));
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
    'reports encrypted EPUB resource exposure policy by package resource role' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $opfWithEncryptedResources = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/book.css" media-type="text/css"/>'
                . '<item id="locked-style" href="styles/locked.css" media-type="text/css"/>'
                . '<item id="locked-audio" href="audio/locked.mp3" media-type="audio/mpeg"/>'
                . '<item id="locked-image" href="images/locked.png" media-type="image/png"/>'
                . '<item id="font-main" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>',
            $opfXml
        );
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="OEBPS/styles/locked.css"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="OEBPS/audio/locked.mp3"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
    <CipherData><CipherReference URI="OEBPS/images/locked.png"/></CipherData>
  </EncryptedData>
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
    <CipherData><CipherReference URI="OEBPS/fonts/source.otf"/></CipherData>
  </EncryptedData>
</encryption>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithEncryptedResources,
            null,
            [
                ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
                ['name' => 'OEBPS/styles/locked.css', 'data' => 'body { color: red; }'],
                ['name' => 'OEBPS/audio/locked.mp3', 'data' => 'LOCKED-MP3'],
                ['name' => 'OEBPS/images/locked.png', 'data' => 'LOCKED-PNG'],
                ['name' => 'OEBPS/fonts/source.otf', 'data' => 'OBFUSCATED-FONT'],
            ]
        ));

        $encryption = $result['encryption'];
        $exposure = $encryption['exposure'];
        $t->same(true, $exposure['present']);
        $t->same(4, $exposure['itemCount']);
        $t->same(4, $exposure['blockedByteExposureCount']);
        $t->same(1, $exposure['obfuscatedFontCount']);
        $t->same(3, $exposure['nonObfuscatedEncryptedCount']);
        $t->same(3, $exposure['attachmentCandidateBlockedCount']);
        $t->same(['audio', 'font', 'image', 'stylesheet'], $exposure['roles']);
        $t->same([
            'audio' => 1,
            'font' => 1,
            'image' => 1,
            'stylesheet' => 1,
        ], $exposure['roleCounts']);
        $t->same([
            '/OEBPS/audio/locked.mp3',
            '/OEBPS/images/locked.png',
            '/OEBPS/styles/locked.css',
        ], $exposure['nonObfuscatedEncryptedParts']);
        $t->same(['/OEBPS/fonts/source.otf'], $exposure['obfuscatedFontParts']);

        $itemsByPart = [];
        foreach ($exposure['items'] as $item) {
            $itemsByPart[$item['part']] = $item;
        }
        $t->same('stylesheet', $itemsByPart['/OEBPS/styles/locked.css']['role']);
        $t->same('encrypted-resource-review', $itemsByPart['/OEBPS/styles/locked.css']['reviewPolicy']);
        $t->same('encrypted-resource-bytes-blocked', $itemsByPart['/OEBPS/styles/locked.css']['byteExposurePolicy']);
        $t->same(false, $itemsByPart['/OEBPS/styles/locked.css']['attachmentCandidateBlocked']);
        $t->same('audio', $itemsByPart['/OEBPS/audio/locked.mp3']['role']);
        $t->same(true, $itemsByPart['/OEBPS/audio/locked.mp3']['attachmentCandidateBlocked']);
        $t->same('image', $itemsByPart['/OEBPS/images/locked.png']['role']);
        $t->same(true, $itemsByPart['/OEBPS/images/locked.png']['attachmentCandidateBlocked']);
        $t->same('font', $itemsByPart['/OEBPS/fonts/source.otf']['role']);
        $t->same('obfuscated-font-review', $itemsByPart['/OEBPS/fonts/source.otf']['reviewPolicy']);
        $t->same('obfuscated-font-bytes-blocked', $itemsByPart['/OEBPS/fonts/source.otf']['byteExposurePolicy']);
        $t->same(true, $itemsByPart['/OEBPS/fonts/source.otf']['attachmentCandidateBlocked']);
        $t->same([], $exposure['diagnostics']);

        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }
        $t->same('stylesheet', $manifestById['locked-style']['encryption']['role']);
        $t->same('encrypted-resource-review', $manifestById['locked-style']['encryption']['reviewPolicy']);
        $t->same('audio', $manifestById['locked-audio']['encryption']['role']);
        $t->same('font', $manifestById['font-main']['encryption']['role']);
        $t->same('obfuscated-font-review', $manifestById['font-main']['encryption']['reviewPolicy']);
        $t->same($exposure, $result['importReport']['encryption']['exposure']);
    },
    'reports OCF metadata sidecar records for container-level review' => static function (TestRunner $t) use ($buildEpubPackage, $metadataXml): void {
        $sourceBytes = '{"source":"wordpress-import","review":true}';
        $result = (new EpubReader())->readPackage($buildEpubPackage(
            null,
            null,
            [
                ['name' => 'META-INF/metadata.xml', 'data' => $metadataXml],
                ['name' => 'META-INF/review/source.json', 'data' => $sourceBytes],
            ]
        ));

        $ocf = $result['ocf'];
        $metadata = $ocf['metadata'];

        $t->same(true, $ocf['present']);
        $t->same(1, $ocf['sidecarCount']);
        $t->same(4, $ocf['referenceCount']);
        $t->same(2, $ocf['localReferenceCount']);
        $t->same(1, $ocf['externalReferenceCount']);
        $t->same(1, $ocf['missingReferenceCount']);
        $t->same(['ocf-metadata-remote-reference', 'ocf-metadata-missing-reference', 'unqualified-ocf-metadata-element'], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $ocf['diagnostics']));

        $t->same(true, $metadata['present']);
        $t->same('/META-INF/metadata.xml', $metadata['part']);
        $t->same('metadata', $metadata['rootName']);
        $t->same(EpubReader::OCF_METADATA_NS, $metadata['rootNamespace']);
        $t->same(true, $metadata['recommendedRoot']);
        $t->same('en', $metadata['language']);
        $t->same(strlen($metadataXml), $metadata['byteLength']);
        $t->same(hash('sha256', $metadataXml), $metadata['byteSha256']);
        $t->same(5, $metadata['itemCount']);
        $t->same(4, $metadata['referenceCount']);
        $t->same(2, $metadata['localReferenceCount']);
        $t->same(1, $metadata['externalReferenceCount']);
        $t->same(1, $metadata['missingReferenceCount']);

        $t->same('source-record', $metadata['items'][0]['id']);
        $t->same('source', $metadata['items'][0]['name']);
        $t->same('https://example.invalid/epub-review', $metadata['items'][0]['namespace']);
        $t->same('Migration source record', $metadata['items'][0]['text']);
        $t->same('/META-INF/review/source.json', $metadata['items'][0]['reference']['target']);
        $t->same(true, $metadata['items'][0]['reference']['exists']);
        $t->same(strlen($sourceBytes), $metadata['items'][0]['reference']['byteLength']);
        $t->same('application/ld+json', $metadata['items'][0]['mediaType']);

        $t->same(true, $metadata['items'][1]['reference']['external']);
        $t->same('ocf-metadata-remote-reference', $metadata['items'][1]['diagnostics'][0]['type']);
        $t->same(false, $metadata['items'][2]['reference']['exists']);
        $t->same('ocf-metadata-missing-reference', $metadata['items'][2]['diagnostics'][0]['type']);
        $t->same('/META-INF/metadata.xml#container-digest', $metadata['items'][3]['reference']['target']);
        $t->same(true, $metadata['items'][3]['reference']['exists']);
        $t->same('legacy', $metadata['items'][4]['name']);
        $t->same(null, $metadata['items'][4]['namespace']);
        $t->same(false, $metadata['items'][4]['namespaceQualified']);
        $t->same('unqualified-ocf-metadata-element', $metadata['items'][4]['diagnostics'][0]['type']);

        $t->same($metadata, $result['importReport']['ocf']['metadata']);
        $t->same($ocf, $result['document']->attr('ocf'));
    },
    'reports OCF manifest sidecar entries without using them as OPF assets' => static function (TestRunner $t) use ($buildEpubPackage, $ocfManifestXml, $chapter1Xhtml): void {
        $unmanifestedBytes = 'UNMANIFESTED-PNG';
        $result = (new EpubReader())->readPackage($buildEpubPackage(
            null,
            null,
            [
                ['name' => 'META-INF/manifest.xml', 'data' => $ocfManifestXml],
                ['name' => 'OEBPS/images/unmanifested-review.png', 'data' => $unmanifestedBytes, 'compressionMethod' => 0],
            ]
        ));

        $ocf = $result['ocf'];
        $manifest = $ocf['manifest'];

        $t->same(true, $ocf['present']);
        $t->same(1, $ocf['sidecarCount']);
        $t->same(7, $ocf['referenceCount']);
        $t->same(5, $ocf['localReferenceCount']);
        $t->same(2, $ocf['missingReferenceCount']);
        $t->same(['ocf-manifest-size-mismatch', 'ocf-manifest-missing-reference', 'ocf-manifest-invalid-reference', 'missing-ocf-manifest-full-path'], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $ocf['diagnostics']));

        $t->same(true, $manifest['present']);
        $t->same('/META-INF/manifest.xml', $manifest['part']);
        $t->same('manifest', $manifest['rootName']);
        $t->same(EpubReader::ODF_MANIFEST_NS, $manifest['rootNamespace']);
        $t->same('odf-manifest', $manifest['format']);
        $t->same(true, $manifest['odfCompatible']);
        $t->same('1.3', $manifest['version']);
        $t->same(strlen($ocfManifestXml), $manifest['byteLength']);
        $t->same(hash('sha256', $ocfManifestXml), $manifest['byteSha256']);
        $t->same(8, $manifest['itemCount']);
        $t->same(5, $manifest['declaredPartCount']);
        $t->same(3, $manifest['missingItemCount']);
        $t->same(1, $manifest['sizeMismatchCount']);

        $root = $manifest['items'][0];
        $t->same('/', $root['fullPath']);
        $t->same('/', $root['target']);
        $t->same(null, $root['part']);
        $t->same(true, $root['root']);
        $t->same(true, $root['directory']);
        $t->same(true, $root['exists']);
        $t->same('application/epub+zip', $root['mediaType']);
        $t->same(false, $root['canExposeBytes']);

        $chapter = $manifest['itemsByPart']['/OEBPS/text/chapter1.xhtml'];
        $t->same('OEBPS/text/chapter1.xhtml', $chapter['fullPath']);
        $t->same('/OEBPS/text/chapter1.xhtml', $chapter['target']);
        $t->same('application/xhtml+xml', $chapter['mediaType']);
        $t->same(strlen($chapter1Xhtml), $chapter['declaredSize']);
        $t->same(strlen($chapter1Xhtml), $chapter['byteLength']);
        $t->same(true, $chapter['sizeMatches']);
        $t->same(hash('sha256', $chapter1Xhtml), $chapter['byteSha256']);
        $t->same(true, $chapter['canExposeBytes']);
        $t->same([], $chapter['diagnostics']);

        $style = $manifest['itemsByPart']['/OEBPS/styles/book.css'];
        $t->same(4, $style['declaredSize']);
        $t->same(false, $style['sizeMatches']);
        $t->same('ocf-manifest-size-mismatch', $style['diagnostics'][0]['type']);

        $unmanifested = $manifest['itemsByPart']['/OEBPS/images/unmanifested-review.png'];
        $t->same(true, $unmanifested['exists']);
        $t->same(16, $unmanifested['declaredSize']);
        $t->same(hash('sha256', $unmanifestedBytes), $unmanifested['byteSha256']);

        $missing = $manifest['items'][5];
        $t->same('/OEBPS/images/missing-review.png', $missing['part']);
        $t->same(false, $missing['exists']);
        $t->same('ocf-manifest-missing-reference', $missing['diagnostics'][0]['type']);

        $invalid = $manifest['items'][6];
        $t->same('../outside.txt', $invalid['fullPath']);
        $t->same(null, $invalid['part']);
        $t->same('ocf-manifest-invalid-reference', $invalid['diagnostics'][0]['type']);

        $missingFullPath = $manifest['items'][7];
        $t->same(null, $missingFullPath['fullPath']);
        $t->same('missing-ocf-manifest-full-path', $missingFullPath['diagnostics'][0]['type']);

        $assetUnmanifestedParts = array_map(
            static fn (array $item): ?string => $item['part'] ?? null,
            $result['importReport']['assets']['unmanifestedItems']
        );
        $t->same(true, in_array('/OEBPS/images/unmanifested-review.png', $assetUnmanifestedParts, true));
        $t->same($manifest, $result['importReport']['ocf']['manifest']);
        $t->same($ocf, $result['document']->attr('ocf'));
        $t->same(2, count($result['document']->children));
    },
    'reports OCF rights and signatures sidecars without validating cryptography' => static function (TestRunner $t) use ($buildEpubPackage, $rightsXml, $signaturesXml): void {
        $licenseBytes = '<license source="wordpress-import">review required</license>';
        $result = (new EpubReader())->readPackage($buildEpubPackage(
            null,
            null,
            [
                ['name' => 'META-INF/rights.xml', 'data' => $rightsXml],
                ['name' => 'META-INF/signatures.xml', 'data' => $signaturesXml],
                ['name' => 'META-INF/licenses/source-license.xml', 'data' => $licenseBytes],
            ]
        ));

        $ocf = $result['ocf'];
        $rights = $ocf['rights'];
        $signatures = $ocf['signatures'];

        $t->same(true, $ocf['present']);
        $t->same(2, $ocf['sidecarCount']);
        $t->same(6, $ocf['referenceCount']);
        $t->same(2, $ocf['externalReferenceCount']);
        $t->same(2, $ocf['missingReferenceCount']);
        $t->same(['ocf-rights-remote-reference', 'ocf-rights-missing-reference', 'ocf-signature-missing-reference', 'ocf-signature-remote-reference'], array_map(static fn (array $diagnostic): string => $diagnostic['type'], $ocf['diagnostics']));

        $t->same(true, $rights['present']);
        $t->same('/META-INF/rights.xml', $rights['part']);
        $t->same('rights', $rights['rootName']);
        $t->same(EpubReader::OCF_CONTAINER_NS, $rights['rootNamespace']);
        $t->same('en', $rights['language']);
        $t->same(strlen($rightsXml), $rights['byteLength']);
        $t->same(hash('sha256', $rightsXml), $rights['byteSha256']);
        $t->same(3, $rights['itemCount']);
        $t->same(3, $rights['referenceCount']);
        $t->same(1, $rights['localReferenceCount']);
        $t->same(1, $rights['externalReferenceCount']);
        $t->same(1, $rights['missingReferenceCount']);
        $t->same('local-license', $rights['items'][0]['id']);
        $t->same('license', $rights['items'][0]['name']);
        $t->same('https://example.invalid/epub-drm', $rights['items'][0]['namespace']);
        $t->same('Migration license', $rights['items'][0]['text']);
        $t->same('/META-INF/licenses/source-license.xml', $rights['items'][0]['reference']['target']);
        $t->same(true, $rights['items'][0]['reference']['exists']);
        $t->same(strlen($licenseBytes), $rights['items'][0]['reference']['byteLength']);
        $t->same('application/xml', $rights['items'][0]['mediaType']);
        $t->same(true, $rights['items'][1]['reference']['external']);
        $t->same('ocf-rights-remote-reference', $rights['items'][1]['diagnostics'][0]['type']);
        $t->same(false, $rights['items'][2]['reference']['exists']);
        $t->same('ocf-rights-missing-reference', $rights['items'][2]['diagnostics'][0]['type']);

        $t->same(true, $signatures['present']);
        $t->same('/META-INF/signatures.xml', $signatures['part']);
        $t->same('signatures', $signatures['rootName']);
        $t->same(EpubReader::OCF_CONTAINER_NS, $signatures['rootNamespace']);
        $t->same(strlen($signaturesXml), $signatures['byteLength']);
        $t->same(hash('sha256', $signaturesXml), $signatures['byteSha256']);
        $t->same(1, $signatures['signatureCount']);
        $t->same(3, $signatures['referenceCount']);
        $t->same(1, $signatures['localReferenceCount']);
        $t->same(1, $signatures['externalReferenceCount']);
        $t->same(1, $signatures['missingReferenceCount']);
        $t->same('package-signature', $signatures['items'][0]['id']);
        $t->same('http://www.w3.org/TR/2001/REC-xml-c14n-20010315', $signatures['items'][0]['canonicalizationMethod']);
        $t->same('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', $signatures['items'][0]['signatureMethod']);
        $t->same(true, $signatures['items'][0]['signatureValuePresent']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $signatures['items'][0]['references'][0]['target']);
        $t->same('/OEBPS/text/chapter1.xhtml', $signatures['items'][0]['references'][0]['part']);
        $t->same(true, $signatures['items'][0]['references'][0]['exists']);
        $t->same('http://www.w3.org/2001/04/xmlenc#sha256', $signatures['items'][0]['references'][0]['digestMethod']);
        $t->same('chapter-digest', $signatures['items'][0]['references'][0]['digestValue']);
        $t->same(false, $signatures['items'][0]['references'][1]['exists']);
        $t->same('ocf-signature-missing-reference', $signatures['items'][0]['references'][1]['diagnostics'][0]['type']);
        $t->same(true, $signatures['items'][0]['references'][2]['external']);
        $t->same('ocf-signature-remote-reference', $signatures['items'][0]['references'][2]['diagnostics'][0]['type']);
        $t->same($ocf, $result['importReport']['ocf']);
        $t->same($ocf, $result['document']->attr('ocf'));
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
    'reports EPUB SMIL media overlay resource provenance for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $chapter1Xhtml): void {
        $smilWithResourceProvenance = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <seq id="resource-overlay" epub:textref="../text/chapter1.xhtml">
      <par id="intro-audio" epub:type="bodymatter">
        <text src="../text/chapter1.xhtml#intro"/>
        <audio src="../audio/chapter1.mp3" clipBegin="0s" clipEnd="2s"/>
      </par>
      <par id="encrypted-audio" epub:type="annotation">
        <text src="../text/chapter1.xhtml#page-1"/>
        <audio src="../audio/encrypted.mp3" clipBegin="2s" clipEnd="4s"/>
      </par>
    </seq>
  </body>
</smil>
XML;
        $encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
    <CipherData>
      <CipherReference URI="OEBPS/audio/encrypted.mp3"/>
    </CipherData>
  </EncryptedData>
</encryption>
XML;
        $opfWithOverlay = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-resource"/>'
                . '<item id="mo-resource" href="overlays/resource.smil" media-type="application/smil+xml"/>'
                . '<item id="audio-chapter-1" href="audio/chapter1.mp3" media-type="audio/mpeg"/>'
                . '<item id="audio-encrypted" href="audio/encrypted.mp3" media-type="audio/mpeg"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithOverlay,
            null,
            [
                ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
                ['name' => 'OEBPS/overlays/resource.smil', 'data' => $smilWithResourceProvenance],
                ['name' => 'OEBPS/audio/chapter1.mp3', 'data' => 'MP3-DATA'],
                ['name' => 'OEBPS/audio/encrypted.mp3', 'data' => 'LOCKED-AUDIO'],
            ]
        ));

        $overlay = $result['mediaOverlays']['mo-resource'];
        $t->same('/OEBPS/text/chapter1.xhtml', $overlay['textRefTarget']);
        $t->same('/OEBPS/text/chapter1.xhtml', $overlay['textRefPart']);
        $t->same('chapter-1', $overlay['textRefManifestId']);
        $t->same('application/xhtml+xml', $overlay['textRefMediaType']);
        $t->same(true, $overlay['textRefExists']);
        $t->same(false, $overlay['textRefEncrypted']);
        $t->same(true, $overlay['textRefCanExposeBytes']);
        $t->same(strlen($chapter1Xhtml), $overlay['textRefByteLength']);
        $t->same(hash('sha256', $chapter1Xhtml), $overlay['textRefByteSha256']);

        $intro = $overlay['items'][0];
        $t->same('intro-audio', $intro['id']);
        $t->same('chapter-1', $intro['textManifestId']);
        $t->same('application/xhtml+xml', $intro['textMediaType']);
        $t->same(false, $intro['textEncrypted']);
        $t->same(true, $intro['textCanExposeBytes']);
        $t->same(hash('sha256', $chapter1Xhtml), $intro['textByteSha256']);
        $t->same('audio-chapter-1', $intro['audioManifestId']);
        $t->same('audio/mpeg', $intro['audioMediaType']);
        $t->same(false, $intro['audioEncrypted']);
        $t->same(true, $intro['audioCanExposeBytes']);
        $t->same(hash('sha256', 'MP3-DATA'), $intro['audioByteSha256']);
        $t->same([], $intro['diagnostics']);

        $encrypted = $overlay['items'][1];
        $t->same('encrypted-audio', $encrypted['id']);
        $t->same('chapter-1', $encrypted['textManifestId']);
        $t->same('audio-encrypted', $encrypted['audioManifestId']);
        $t->same('audio/mpeg', $encrypted['audioMediaType']);
        $t->same(true, $encrypted['audioEncrypted']);
        $t->same(false, $encrypted['audioCanExposeBytes']);
        $t->same(null, $encrypted['audioByteSha256']);
        $t->same('encrypted-media-overlay-reference', $encrypted['diagnostics'][0]['type']);
        $t->same('/OEBPS/audio/encrypted.mp3', $encrypted['diagnostics'][0]['part']);

        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }
        $t->same('chapter-1', $manifestById['chapter-1']['mediaOverlayReference']['textRefManifestId']);
        $t->same(hash('sha256', $chapter1Xhtml), $manifestById['chapter-1']['mediaOverlayReference']['textRefByteSha256']);
        $t->same($overlay, $result['importReport']['mediaOverlays']['mo-resource']);
    },
    'preserves EPUB SMIL media overlay sequence provenance for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $chapter1Xhtml): void {
        $smilWithSequences = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <seq id="chapter-seq" epub:type="bodymatter" epub:textref="../text/chapter1.xhtml" repeatCount="1">
      <seq id="annotation-seq" epub:type="annotation note" epub:textref="https://cdn.example.test/remote.xhtml#voice" repeatDur="00:00:10.000" dur="10s">
        <par id="local-intro" epub:type="sentence">
          <text src="../text/chapter1.xhtml#intro"/>
          <audio src="../audio/chapter1.mp3" clipBegin="0s" clipEnd="1.5s"/>
        </par>
      </seq>
      <seq id="page-seq" epub:type="pagebreak">
        <par id="page-audio">
          <text src="../text/chapter1.xhtml#page-1"/>
          <audio src="../audio/chapter1.mp3" clipBegin="1.5s" clipEnd="2.5s"/>
        </par>
      </seq>
    </seq>
  </body>
</smil>
XML;
        $opfWithOverlay = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-sequence"/>'
                . '<item id="mo-sequence" href="overlays/sequence.smil" media-type="application/smil+xml"/>'
                . '<item id="audio-chapter-1" href="audio/chapter1.mp3" media-type="audio/mpeg"/>',
            $opfXml
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithOverlay,
            null,
            [
                ['name' => 'OEBPS/overlays/sequence.smil', 'data' => $smilWithSequences],
                ['name' => 'OEBPS/audio/chapter1.mp3', 'data' => 'MP3-DATA'],
            ]
        ));

        $overlay = $result['mediaOverlays']['mo-sequence'];
        $t->same(3, $overlay['sequenceCount']);
        $t->same(3, count($overlay['sequences']));

        $root = $overlay['sequences'][0];
        $t->same(0, $root['index']);
        $t->same('chapter-seq', $root['id']);
        $t->same(['bodymatter'], $root['types']);
        $t->same(0, $root['depth']);
        $t->same(null, $root['parentIndex']);
        $t->same(['chapter-seq'], $root['path']);
        $t->same('../text/chapter1.xhtml', $root['textRef']);
        $t->same('/OEBPS/text/chapter1.xhtml', $root['textRefTarget']);
        $t->same('/OEBPS/text/chapter1.xhtml', $root['textRefPart']);
        $t->same('chapter-1', $root['textRefManifestId']);
        $t->same('application/xhtml+xml', $root['textRefMediaType']);
        $t->same(strlen($chapter1Xhtml), $root['textRefByteLength']);
        $t->same(hash('sha256', $chapter1Xhtml), $root['textRefByteSha256']);
        $t->same('1', $root['repeatCount']);
        $t->same(null, $root['repeatDur']);
        $t->same(0, $root['directParCount']);
        $t->same(2, $root['childSequenceCount']);
        $t->same([], $root['diagnostics']);

        $annotation = $overlay['sequences'][1];
        $t->same('annotation-seq', $annotation['id']);
        $t->same(['annotation', 'note'], $annotation['types']);
        $t->same(1, $annotation['depth']);
        $t->same(0, $annotation['parentIndex']);
        $t->same(['chapter-seq', 'annotation-seq'], $annotation['path']);
        $t->same('https://cdn.example.test/remote.xhtml#voice', $annotation['textRefTarget']);
        $t->same(true, $annotation['textRefExternal']);
        $t->same(false, $annotation['textRefExists']);
        $t->same('00:00:10.000', $annotation['repeatDur']);
        $t->same('10s', $annotation['dur']);
        $t->same(1, $annotation['directParCount']);
        $t->same(0, $annotation['childSequenceCount']);
        $t->same('external-media-overlay-reference', $annotation['diagnostics'][0]['type']);

        $pageSequence = $overlay['sequences'][2];
        $t->same('page-seq', $pageSequence['id']);
        $t->same(['pagebreak'], $pageSequence['types']);
        $t->same(['chapter-seq', 'page-seq'], $pageSequence['path']);
        $t->same(null, $pageSequence['textRef']);
        $t->same('/OEBPS/text/chapter1.xhtml', $pageSequence['textRefTarget']);
        $t->same(1, $pageSequence['directParCount']);

        $t->same(1, count($overlay['sequenceDiagnostics']));
        $t->same(1, $overlay['sequenceDiagnostics'][0]['sequenceIndex']);
        $t->same('annotation-seq', $overlay['sequenceDiagnostics'][0]['sequenceId']);
        $t->same('external-media-overlay-reference', $overlay['sequenceDiagnostics'][0]['type']);

        $intro = $overlay['items'][0];
        $t->same('local-intro', $intro['id']);
        $t->same(1, $intro['sequenceIndex']);
        $t->same('annotation-seq', $intro['sequenceId']);
        $t->same(1, $intro['sequenceDepth']);
        $t->same(['chapter-seq', 'annotation-seq'], $intro['sequencePath']);
        $t->same(['bodymatter', 'annotation', 'note'], $intro['sequenceTypes']);
        $t->same('https://cdn.example.test/remote.xhtml#voice', $intro['sequenceTextTarget']);
        $t->same('/OEBPS/text/chapter1.xhtml#intro', $intro['textTarget']);
        $t->same('chapter-1', $intro['textManifestId']);
        $t->same('audio-chapter-1', $intro['audioManifestId']);
        $t->same(1.5, $intro['clipDurationSeconds']);

        $page = $overlay['items'][1];
        $t->same('page-audio', $page['id']);
        $t->same(2, $page['sequenceIndex']);
        $t->same('page-seq', $page['sequenceId']);
        $t->same(['chapter-seq', 'page-seq'], $page['sequencePath']);
        $t->same(['bodymatter', 'pagebreak'], $page['sequenceTypes']);
        $t->same('/OEBPS/text/chapter1.xhtml', $page['sequenceTextTarget']);
        $t->same('/OEBPS/text/chapter1.xhtml#page-1', $page['textTarget']);
        $t->same(1.0, $page['clipDurationSeconds']);

        $t->same(3, $result['spine'][0]['mediaOverlayReference']['sequenceCount']);
        $t->same('external-media-overlay-reference', $result['spine'][0]['mediaOverlayReference']['sequenceDiagnostics'][0]['type']);
        $t->same(3, $result['document']->children[0]->attr('mediaOverlayReference')['sequenceCount']);
        $t->same($overlay, $result['importReport']['mediaOverlays']['mo-sequence']);
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
    'reports OPF media overlay style class metadata for review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $smilXml): void {
        $opfWithOverlayStyleClasses = str_replace(
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>',
            '<meta property="dcterms:modified">2026-06-04T21:00:00Z</meta>'
            . '<meta property="media:active-class">publication-active global-speaking</meta>'
            . '<meta property="media:playback-active-class" content="publication-playing"/>'
            . '<meta property="media:active-class" refines="#mo-chapter-1">mo-active now-speaking</meta>'
            . '<meta property="media:playback-active-class" refines="#mo-chapter-1">mo-playing</meta>'
            . '<meta property="media:active-class" refines="#style">style-active</meta>'
            . '<meta property="media:playback-active-class" refines="#missing-overlay">missing-playing</meta>',
            $opfXml
        );
        $opfWithOverlayStyleClasses = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter-1"/><item id="mo-chapter-1" href="overlays/chapter1.smil" media-type="application/smil+xml"/>',
            $opfWithOverlayStyleClasses
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithOverlayStyleClasses,
            null,
            [
                ['name' => 'OEBPS/overlays/chapter1.smil', 'data' => $smilXml],
                ['name' => 'OEBPS/audio/chapter1.mp3', 'data' => 'MP3-DATA'],
            ]
        ));

        $styles = $result['mediaOverlayStyles'];
        $t->same(true, $styles['present']);
        $t->same('publication-active global-speaking', $styles['activeClass']);
        $t->same(['publication-active', 'global-speaking'], $styles['activeClassTokens']);
        $t->same('publication-playing', $styles['playbackActiveClass']);
        $t->same(['publication-playing'], $styles['playbackActiveClassTokens']);
        $t->same(6, count($styles['items']));
        $t->same(2, count($styles['diagnostics']));
        $t->same('media-overlay-style-refines-non-overlay-manifest-item', $styles['diagnostics'][0]['type']);
        $t->same('style', $styles['diagnostics'][0]['subjectId']);
        $t->same('media-overlay-style-refines-missing-manifest-item', $styles['diagnostics'][1]['type']);
        $t->same('missing-overlay', $styles['diagnostics'][1]['subjectId']);

        $overlayStyles = $styles['overlaysById']['mo-chapter-1'];
        $t->same('mo-chapter-1', $overlayStyles['id']);
        $t->same('mo-active now-speaking', $overlayStyles['activeClass']);
        $t->same(['mo-active', 'now-speaking'], $overlayStyles['activeClassTokens']);
        $t->same('mo-playing', $overlayStyles['playbackActiveClass']);
        $t->same(['mo-playing'], $overlayStyles['playbackActiveClassTokens']);
        $t->same('mo-chapter-1', $overlayStyles['manifestId']);
        $t->same('/OEBPS/overlays/chapter1.smil', $overlayStyles['manifestPart']);
        $t->same('application/smil+xml', $overlayStyles['manifestMediaType']);
        $t->same(['chapter-1'], $overlayStyles['referencedBy']);
        $t->same([], $overlayStyles['diagnostics']);

        $overlay = $result['mediaOverlays']['mo-chapter-1'];
        $t->same('mo-active now-speaking', $overlay['activeClass']);
        $t->same(['mo-active', 'now-speaking'], $overlay['activeClassTokens']);
        $t->same('mo-playing', $overlay['playbackActiveClass']);
        $t->same(['mo-playing'], $overlay['playbackActiveClassTokens']);
        $t->same($overlayStyles, $overlay['styleMetadata']);

        $manifestById = [];
        foreach ($result['manifest'] as $item) {
            $manifestById[$item['id']] = $item;
        }
        $chapterOverlay = $manifestById['chapter-1']['mediaOverlayReference'];
        $t->same('mo-active now-speaking', $chapterOverlay['activeClass']);
        $t->same(['mo-active', 'now-speaking'], $chapterOverlay['activeClassTokens']);
        $t->same('mo-playing', $chapterOverlay['playbackActiveClass']);
        $t->same(['mo-playing'], $chapterOverlay['playbackActiveClassTokens']);
        $t->same($overlayStyles, $chapterOverlay['styleMetadata']);
        $t->same($chapterOverlay, $result['spine'][0]['mediaOverlayReference']);
        $t->same($chapterOverlay, $result['document']->children[0]->attr('mediaOverlayReference'));
        $t->same($styles, $result['metadata']['mediaOverlayStyles']);
        $t->same($styles, $result['importReport']['mediaOverlayStyles']);
        $t->same($styles, $result['document']->attr('mediaOverlayStyles'));
    },
    'reports OPF manifest media-overlay bindings for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml, $smilXml): void {
        $opfWithOverlayBindings = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter-1"/><item id="mo-chapter-1" href="overlays/chapter1.smil" media-type="application/smil+xml"/>',
            $opfXml
        );
        $opfWithOverlayBindings = str_replace(
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-2" href="text/chapter2.xhtml" media-type="application/xhtml+xml" media-overlay="missing-overlay"/>',
            $opfWithOverlayBindings
        );
        $opfWithOverlayBindings = str_replace(
            '<item id="style" href="styles/book.css" media-type="text/css"/>',
            '<item id="style" href="styles/book.css" media-type="text/css" media-overlay="mo-style"/><item id="mo-style" href="styles/not-smil.css" media-type="text/css"/>',
            $opfWithOverlayBindings
        );

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithOverlayBindings,
            null,
            [
                ['name' => 'OEBPS/overlays/chapter1.smil', 'data' => $smilXml],
                ['name' => 'OEBPS/styles/not-smil.css', 'data' => 'body { color: #444; }'],
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

        $chapterOverlay = $manifestById['chapter-1']['mediaOverlayReference'];
        $t->same('mo-chapter-1', $chapterOverlay['id']);
        $t->same('/OEBPS/overlays/chapter1.smil', $chapterOverlay['part']);
        $t->same('application/smil+xml', $chapterOverlay['mediaType']);
        $t->same(true, $chapterOverlay['exists']);
        $t->same(['chapter-1'], $chapterOverlay['referencedBy']);
        $t->same(2, $chapterOverlay['itemCount']);
        $t->same('/OEBPS/text/chapter1.xhtml', $chapterOverlay['textRefTarget']);
        $t->same([], $chapterOverlay['diagnostics']);

        $missingOverlay = $manifestById['chapter-2']['mediaOverlayReference'];
        $t->same('missing-overlay', $missingOverlay['id']);
        $t->same(false, $missingOverlay['exists']);
        $t->same(['chapter-2'], $missingOverlay['referencedBy']);
        $t->same('missing-media-overlay-manifest-item', $missingOverlay['diagnostics'][0]['type']);

        $styleOverlay = $manifestById['style']['mediaOverlayReference'];
        $t->same('mo-style', $styleOverlay['id']);
        $t->same('/OEBPS/styles/not-smil.css', $styleOverlay['part']);
        $t->same('text/css', $styleOverlay['mediaType']);
        $t->same(true, $styleOverlay['exists']);
        $t->same('unexpected-media-overlay-type', $styleOverlay['diagnostics'][0]['type']);

        $t->same($chapterOverlay, $result['spine'][0]['mediaOverlayReference']);
        $t->same($missingOverlay, $result['spine'][1]['mediaOverlayReference']);
        $t->same($chapterOverlay, $result['xhtmlAssets'][1]['mediaOverlayReference']);
        $t->same($styleOverlay, $assetById['style']['mediaOverlayReference']);
        $t->same($chapterOverlay, $result['document']->children[0]->attr('mediaOverlayReference'));
        $t->same($missingOverlay, $result['document']->children[1]->attr('mediaOverlayReference'));
        $t->same($chapterOverlay, $result['importReport']['manifest']['items'][1]['mediaOverlayReference']);
        $importAssetById = [];
        foreach ($result['importReport']['assets']['items'] as $asset) {
            $importAssetById[$asset['id']] = $asset;
        }
        $t->same($styleOverlay, $importAssetById['style']['mediaOverlayReference']);
        $t->same($result['mediaOverlays']['mo-chapter-1'], $result['importReport']['mediaOverlays']['mo-chapter-1']);
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
    'reports NCX label audio clips for navigation review handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $audioBytes = 'NCX-AUDIO-DATA';
        $opfWithAudio = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="ncx-audio" href="audio/nav-label.mp3" media-type="audio/mpeg"/>',
            $opfXml
        );
        $navWithoutPageList = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter1.xhtml#intro">Imported packet</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;
        $ncxWithAudio = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="audio-point" playOrder="1">
      <navLabel id="audio-point-label">
        <text>Audio introduction</text>
        <audio id="audio-point-clip" src="audio/nav-label.mp3" clipBegin="0:00:01.000" clipEnd="0:00:03.500"/>
      </navLabel>
      <content src="text/chapter1.xhtml#intro"/>
    </navPoint>
  </navMap>
  <pageList id="print-pages">
    <navLabel>
      <text>Print pages</text>
      <audio src="https://audio.example.test/pages.mp3"/>
    </navLabel>
    <pageTarget id="print-page-1" type="normal" value="1" playOrder="10">
      <navLabel>
        <text>1</text>
        <audio src="audio/missing-page.mp3" clipBegin="bad-clock" clipEnd="0:00:04.000"/>
      </navLabel>
      <content src="text/chapter1.xhtml#page-1"/>
    </pageTarget>
  </pageList>
  <navList id="figures" class="loi">
    <navLabel>
      <text>Figures</text>
      <audio src="audio/nav-label.mp3" clipBegin="00:00:04.000" clipEnd="00:00:02.000"/>
    </navLabel>
    <navTarget id="figure-1" playOrder="11">
      <navLabel>
        <text>Figure one</text>
        <audio src="audio/nav-label.mp3"/>
      </navLabel>
      <content src="text/chapter2.xhtml#media"/>
    </navTarget>
  </navList>
</ncx>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithAudio,
            null,
            [
                ['name' => 'OEBPS/audio/nav-label.mp3', 'data' => $audioBytes],
            ],
            $navWithoutPageList,
            $ncxWithAudio
        ));
        $ncx = $result['ncx'];

        $t->same(5, $ncx['audioLabelCount']);
        $t->same(true, $ncx['audioLabelReport']['present']);
        $t->same(5, $ncx['audioLabelReport']['count']);
        $t->same(3, $ncx['audioLabelReport']['localCount']);
        $t->same(1, $ncx['audioLabelReport']['externalCount']);
        $t->same(1, $ncx['audioLabelReport']['missingCount']);
        $t->same(0, $ncx['audioLabelReport']['encryptedCount']);
        $t->same(4, $ncx['audioLabelReport']['diagnosticCount']);
        $t->same('external-ncx-audio-reference', $ncx['audioLabelDiagnostics'][0]['type']);
        $t->same('missing-ncx-audio-reference', $ncx['audioLabelDiagnostics'][1]['type']);
        $t->same('invalid-ncx-audio-clip-begin', $ncx['audioLabelDiagnostics'][2]['type']);
        $t->same('ncx-audio-clip-end-before-begin', $ncx['audioLabelReport']['diagnostics'][3]['type']);

        $pointAudio = $ncx['items'][0]['labelAudio'][0];
        $t->same('audio-point-clip', $pointAudio['id']);
        $t->same('audio/nav-label.mp3', $pointAudio['src']);
        $t->same('/OEBPS/audio/nav-label.mp3', $pointAudio['target']);
        $t->same('/OEBPS/audio/nav-label.mp3', $pointAudio['part']);
        $t->same('ncx-audio', $pointAudio['manifestId']);
        $t->same('audio/mpeg', $pointAudio['mediaType']);
        $t->same(strlen($audioBytes), $pointAudio['byteLength']);
        $t->same(hash('crc32b', $audioBytes), $pointAudio['crc32']);
        $t->same(hash('sha256', $audioBytes), $pointAudio['byteSha256']);
        $t->same(1.0, $pointAudio['clipBeginSeconds']);
        $t->same(3.5, $pointAudio['clipEndSeconds']);
        $t->same(2.5, $pointAudio['clipDurationSeconds']);
        $t->same(true, $pointAudio['clipValid']);
        $t->same([], $pointAudio['diagnostics']);

        $navigationNcxItem = $result['navigation']['items'][1];
        $t->same('ncx', $navigationNcxItem['source']);
        $t->same(1, $navigationNcxItem['labelAudioCount']);
        $t->same(hash('sha256', $audioBytes), $navigationNcxItem['labelAudio'][0]['byteSha256']);
        $t->same($result['navigation'], $result['document']->attr('navigation'));

        $pageListLabel = $ncx['pageListReport']['labelAudio'][0];
        $t->same(true, $pageListLabel['external']);
        $t->same('https://audio.example.test/pages.mp3', $pageListLabel['target']);
        $t->same('external-ncx-audio-reference', $pageListLabel['diagnostics'][0]['type']);

        $pageTargetAudio = $ncx['pageList'][0]['labelAudio'][0];
        $t->same(false, $pageTargetAudio['exists']);
        $t->same('/OEBPS/audio/missing-page.mp3', $pageTargetAudio['part']);
        $t->same('missing-ncx-audio-reference', $pageTargetAudio['diagnostics'][0]['type']);
        $t->same('invalid-ncx-audio-clip-begin', $pageTargetAudio['clipDiagnostics'][0]['type']);

        $pageBreak = $result['pageBreaks']['items'][0];
        $t->same('ncx', $pageBreak['source']);
        $t->same(1, $pageBreak['labelAudioCount']);
        $t->same('audio/missing-page.mp3', $pageBreak['labelAudio'][0]['src']);
        $t->same('missing-ncx-audio-reference', $pageBreak['labelAudio'][0]['diagnostics'][0]['type']);
        $t->same('audio/missing-page.mp3', $result['document']->children[0]->attr('pageBreaks')[0]['labelAudio'][0]['src']);
        $t->same('invalid-ncx-audio-clip-begin', $result['document']->children[0]->attr('pageBreaks')[0]['labelAudio'][0]['clipDiagnostics'][0]['type']);

        $navListAudio = $ncx['navLists'][0]['labelAudio'][0];
        $t->same('ncx-audio-clip-end-before-begin', $navListAudio['diagnostics'][0]['type']);
        $t->same(false, $navListAudio['clipValid']);
        $t->same(4.0, $navListAudio['clipBeginSeconds']);
        $t->same(2.0, $navListAudio['clipEndSeconds']);
        $t->same(null, $navListAudio['clipDurationSeconds']);
        $t->same(1, $ncx['navLists'][0]['items'][0]['labelAudioCount']);
        $t->same('/OEBPS/audio/nav-label.mp3', $ncx['navLists'][0]['items'][0]['labelAudio'][0]['target']);
        $t->same(1, $result['navigation']['supplementalItems'][0]['labelAudioCount']);
        $t->same(hash('sha256', $audioBytes), $result['navigation']['supplementalItems'][0]['labelAudio'][0]['byteSha256']);
        $t->same($ncx['audioLabelReport'], $result['importReport']['ncx']['audioLabelReport']);
    },
    'preserves EPUB CFI fragment targets across package handoff' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $cfi = 'epubcfi(/6/2[chapter1]!/4/2/1:12)';
        $pageCfi = 'epubcfi(/6/2[chapter1]!/4/2/3:1)';
        $href = 'text/chapter1.xhtml#' . $cfi;
        $target = '/OEBPS/text/chapter1.xhtml#' . $cfi;

        $opfWithCfi = str_replace(
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml"/>',
            '<item id="chapter-1" href="text/chapter1.xhtml" media-type="application/xhtml+xml" media-overlay="mo-cfi"/><item id="mo-cfi" href="overlays/cfi.smil" media-type="application/smil+xml"/><item id="audio-cfi" href="audio/cfi.mp3" media-type="audio/mpeg"/>',
            $opfXml
        );
        $opfWithCfi = str_replace(
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>',
            '<item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/><item id="cfi-content" href="text/cfi-content.xhtml" media-type="application/xhtml+xml"/>',
            $opfWithCfi
        );
        $opfWithCfi = str_replace(
            '</spine>',
            '<itemref idref="cfi-content"/></spine>',
            $opfWithCfi
        );
        $opfWithCfi = str_replace(
            '</metadata>',
            '<link id="cfi-review-target" rel="record" href="' . $href . '" media-type="application/xhtml+xml" properties="epub-cfi-review"/></metadata>',
            $opfWithCfi
        );
        $opfWithCfi = str_replace(
            '<reference type="text" title="Start reading" href="text/chapter1.xhtml#intro"/>',
            '<reference type="text" title="Start reading by CFI" href="' . $href . '"/>',
            $opfWithCfi
        );
        $opfWithCfi = str_replace(
            '<link rel="first" href="text/chapter1.xhtml#intro" media-type="application/xhtml+xml" properties="preview"/>',
            '<link rel="first" href="' . $href . '" media-type="application/xhtml+xml" properties="preview"/>',
            $opfWithCfi
        );

        $navWithCfi = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter1.xhtml#epubcfi(/6/2[chapter1]!/4/2/1:12)">CFI chapter position</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter1.xhtml#epubcfi(/6/2[chapter1]!/4/2/3:1)">CFI page</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

        $ncxWithCfi = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="navpoint-cfi" playOrder="1">
      <navLabel><text>CFI chapter position</text></navLabel>
      <content src="text/chapter1.xhtml#epubcfi(/6/2[chapter1]!/4/2/1:12)"/>
    </navPoint>
  </navMap>
</ncx>
XML;

        $smilWithCfi = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <seq id="cfi-overlay" epub:textref="../text/chapter1.xhtml#epubcfi(/6/2[chapter1]!/4/2/1:12)">
      <par id="cfi-audio">
        <text src="../text/chapter1.xhtml#epubcfi(/6/2[chapter1]!/4/2/1:12)"/>
        <audio src="../audio/cfi.mp3" clipBegin="0:00:00.000" clipEnd="0:00:02.000"/>
      </par>
    </seq>
  </body>
</smil>
XML;

        $cfiContentXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body><p><a href="chapter1.xhtml#epubcfi(/6/2[chapter1]!/4/2/1:12)">CFI self link</a></p></body>
</html>
XML;

        $result = (new EpubReader())->readPackage($buildEpubPackage(
            $opfWithCfi,
            null,
            [
                ['name' => 'OEBPS/overlays/cfi.smil', 'data' => $smilWithCfi],
                ['name' => 'OEBPS/audio/cfi.mp3', 'data' => 'CFI-AUDIO'],
                ['name' => 'OEBPS/text/cfi-content.xhtml', 'data' => $cfiContentXhtml],
            ],
            $navWithCfi,
            $ncxWithCfi
        ));

        $t->same($target, $result['nav']['items'][0]['target']);
        $t->same($cfi, $result['nav']['items'][0]['fragment']);
        $t->same('epub-cfi', $result['nav']['items'][0]['fragmentKind']);
        $t->same(true, $result['nav']['items'][0]['epubCfi']['present']);
        $t->same('/6/2[chapter1]!/4/2/1:12', $result['nav']['items'][0]['epubCfi']['path']);
        $t->same(true, $result['nav']['items'][0]['epubCfi']['valid']);
        $t->same([], $result['nav']['items'][0]['epubCfi']['diagnostics']);

        $t->same($cfi, $result['ncx']['items'][0]['fragment']);
        $t->same('epub-cfi', $result['ncx']['items'][0]['fragmentKind']);
        $t->same($target, $result['navigation']['items'][0]['target']);
        $t->same('epub-cfi', $result['navigation']['items'][0]['fragmentKind']);
        $t->same(2, $result['navigation']['cfiTargetCount']);
        $t->same(2, count($result['navigation']['cfiTargets']));
        $t->same(2, $result['navigation']['spineCoverage'][0]['targetCount']);
        $t->same($result['navigation'], $result['document']->attr('navigation'));

        $pageBreak = $result['pageBreaks']['items'][0];
        $t->same($pageCfi, $pageBreak['fragment']);
        $t->same('epub-cfi', $pageBreak['fragmentKind']);
        $t->same('/6/2[chapter1]!/4/2/3:1', $pageBreak['epubCfi']['path']);
        $t->same(1, $result['pageBreaks']['cfiPageBreakCount']);
        $t->same($result['pageBreaks'], $result['document']->attr('pageBreaks'));

        $t->same($cfi, $result['guide']['items'][1]['fragment']);
        $t->same('epub-cfi', $result['guide']['items'][1]['fragmentKind']);
        $t->same($cfi, $result['collections'][0]['links'][0]['fragment']);
        $t->same('epub-cfi', $result['metadata']['links'][0]['fragmentKind']);
        $t->same('/6/2[chapter1]!/4/2/1:12', $result['metadata']['links'][0]['epubCfi']['path']);

        $overlay = $result['mediaOverlays']['mo-cfi'];
        $t->same($cfi, $overlay['textRefFragment']);
        $t->same('epub-cfi', $overlay['textRefFragmentKind']);
        $t->same('/6/2[chapter1]!/4/2/1:12', $overlay['textRefEpubCfi']['path']);
        $t->same($cfi, $overlay['items'][0]['textFragment']);
        $t->same('epub-cfi', $overlay['items'][0]['textFragmentKind']);
        $t->same(2.0, $overlay['items'][0]['clipDurationSeconds']);

        $chapterResources = $result['xhtmlResourceReport']['itemsByPart']['/OEBPS/text/cfi-content.xhtml'];
        $cfiReferences = array_values(array_filter(
            $chapterResources['references'],
            static fn (array $reference): bool => ($reference['fragmentKind'] ?? null) === 'epub-cfi',
        ));
        $t->same(1, count($cfiReferences));
        $t->same('chapter1.xhtml#' . $cfi, $cfiReferences[0]['href']);
        $t->same('/OEBPS/text/chapter1.xhtml#' . $cfi, $cfiReferences[0]['target']);
        $t->same('/6/2[chapter1]!/4/2/1:12', $cfiReferences[0]['epubCfi']['path']);
        $t->same(3, $result['xhtmlResourceReport']['cfiReferenceCount']);
        $t->same($chapterResources['references'], $result['document']->children[2]->attr('contentReferences'));
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
        $t->throws(\RuntimeException::class, static fn (): array => $reader->readPackage(ZipPackage::fromParts([
            [
                'name' => 'mimetype',
                'data' => EpubReader::MIMETYPE,
                'compressionMethod' => 0,
                'extraFieldData' => pack('vva*', 0xcafe, strlen('review'), 'review'),
            ],
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
