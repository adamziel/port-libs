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
    'flags EPUB switch XHTML content for package review' => static function (TestRunner $t) use ($buildEpubPackage, $opfXml): void {
        $switchXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <h1>Layout alternate</h1>
    <epub:switch id="layout-choice">
      <epub:case required-namespace="http://www.w3.org/2000/svg"><p>Reading-system SVG path.</p></epub:case>
      <epub:default><p>Fallback text preserved for WordPress review.</p></epub:default>
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
        $t->same(['switch'], $asset['reviewFlags']);
        $t->same(true, $asset['flags']['switch'] ?? null);
        $t->same(0, $asset['referenceCount']);
        $t->same([], $asset['references']);
        $t->same('/OEBPS/text/switch-content.xhtml', $switchBlock->attr('part'));
        $t->same(['switch'], $switchBlock->attr('contentResourceReviewFlags'));
        $t->same($asset['flags'], $switchBlock->attr('contentResourceFlags'));
        $t->same($asset['references'], $switchBlock->attr('contentReferences'));
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
