<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$containerXml = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

$opfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="source-id">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="source-id">urn:uuid:wordpress-epub-source</dc:identifier>
    <dc:title>WordPress EPUB source packet</dc:title>
    <dc:creator>Migration Desk</dc:creator>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-04T21:45:00Z</meta>
    <meta name="cover" content="cover-image"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml" media-overlay="mo-chapter"/>
    <item id="mo-chapter" href="overlays/chapter.smil" media-type="application/smil+xml"/>
    <item id="audio-chapter" href="audio/chapter.mp3" media-type="audio/mpeg"/>
    <item id="style" href="styles/review.css" media-type="text/css"/>
    <item id="font-main" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>
    <item id="cover-image" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine toc="toc">
    <itemref idref="chapter"/>
  </spine>
  <guide>
    <reference type="text" title="Begin source" href="text/chapter.xhtml#source"/>
    <reference type="cover" title="Cover image" href="images/cover.png"/>
  </guide>
  <collection id="source-set" role="set" xml:lang="en">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>WordPress source collection</dc:title>
      <meta property="group-position">1</meta>
    </metadata>
    <link rel="first" href="text/chapter.xhtml#source" media-type="application/xhtml+xml" properties="preview"/>
    <link rel="record" href="https://example.invalid/wp-source" media-type="text/html"/>
  </collection>
</package>
XML;

$navXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter.xhtml#source">Source chapter</a></li>
        <li><a href="https://cdn.example.test/epub/source-note.html">Remote source note</a></li>
      </ol>
    </nav>
    <nav epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="text/chapter.xhtml#source">Begin source</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter.xhtml#page-1">1</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

$chapterXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1 id="source">Source chapter</h1>
    <span id="page-1"></span>
    <p>EPUB XHTML content is preserved for WordPress import review.</p>
  </body>
</html>
XML;

$smilXml = <<<'XML'
<smil xmlns="http://www.w3.org/ns/SMIL" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <seq id="chapter-overlay" epub:textref="../text/chapter.xhtml">
      <par id="source-audio" epub:type="bodymatter">
        <text src="../text/chapter.xhtml#source"/>
        <audio src="../audio/chapter.mp3" clipBegin="0:00:00.000" clipEnd="0:00:04.250"/>
      </par>
      <par id="page-audio" epub:type="pagebreak">
        <text src="../text/chapter.xhtml#page-1"/>
        <audio src="../audio/chapter.mp3" clipBegin="0:00:04.250" clipEnd="0:00:05.000"/>
      </par>
      <par id="remote-audio" epub:type="annotation">
        <text src="../text/chapter.xhtml#source"/>
        <audio src="https://cdn.example.test/audio/source-note.mp3" clipBegin="0:00:05.000" clipEnd="0:00:08.000"/>
      </par>
    </seq>
  </body>
</smil>
XML;

$ncxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="source" playOrder="1">
      <navLabel><text>Source chapter</text></navLabel>
      <content src="text/chapter.xhtml#source"/>
    </navPoint>
    <navPoint id="remote-note" playOrder="2">
      <navLabel><text>Remote source note</text></navLabel>
      <content src="https://cdn.example.test/epub/source-note.html"/>
    </navPoint>
  </navMap>
</ncx>
XML;

$encryptionXml = <<<'XML'
<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <EncryptedData xmlns="http://www.w3.org/2001/04/xmlenc#">
    <EncryptionMethod Algorithm="http://www.idpf.org/2008/embedding"/>
    <CipherData>
      <CipherReference URI="EPUB/fonts/source.otf"/>
    </CipherData>
  </EncryptedData>
</encryption>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => EpubReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/container.xml', 'data' => $containerXml],
    ['name' => 'META-INF/encryption.xml', 'data' => $encryptionXml],
    ['name' => 'EPUB/package.opf', 'data' => $opfXml],
    ['name' => 'EPUB/nav.xhtml', 'data' => $navXhtml],
    ['name' => 'EPUB/text/chapter.xhtml', 'data' => $chapterXhtml],
    ['name' => 'EPUB/overlays/chapter.smil', 'data' => $smilXml],
    ['name' => 'EPUB/audio/chapter.mp3', 'data' => 'MP3-DATA'],
    ['name' => 'EPUB/styles/review.css', 'data' => 'body { color: #222; }'],
    ['name' => 'EPUB/fonts/source.otf', 'data' => 'OBFUSCATED-FONT'],
    ['name' => 'EPUB/images/cover.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'EPUB/toc.ncx', 'data' => $ncxXml],
]);

$reader = new EpubReader();
$result = $reader->readPackage($package);
$blocks = (new WordPressBlockWriter())->write($result['document']);

if (($argv[1] ?? '') === '--self-test') {
    if ($result['metadata']['title'] !== 'WordPress EPUB source packet') {
        throw new RuntimeException('Expected EPUB OPF title metadata');
    }
    if ($result['spine'][0]['part'] !== '/EPUB/text/chapter.xhtml') {
        throw new RuntimeException('Expected spine chapter part to resolve relative to the OPF');
    }
    if (($result['nav']['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB nav href to resolve to the chapter fragment');
    }
    if (($result['nav']['items'][1]['external'] ?? null) !== true || ($result['nav']['items'][1]['diagnostics'][0]['type'] ?? null) !== 'external-nav-reference') {
        throw new RuntimeException('Expected remote EPUB nav reference to stay unfetched for review');
    }
    if (($result['ncx']['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected NCX content src to resolve to the chapter fragment');
    }
    if (($result['ncx']['items'][1]['external'] ?? null) !== true || ($result['ncx']['items'][1]['diagnostics'][0]['type'] ?? null) !== 'external-ncx-reference') {
        throw new RuntimeException('Expected remote NCX reference to stay unfetched for review');
    }
    if (($result['nav']['landmarks'][0]['type'] ?? null) !== 'bodymatter') {
        throw new RuntimeException('Expected EPUB nav landmarks to preserve item type');
    }
    if (($result['nav']['pageList'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#page-1') {
        throw new RuntimeException('Expected EPUB page-list target to resolve to the source page marker');
    }
    if (($result['guide']['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB OPF guide text target to resolve to the source chapter');
    }
    if (($result['guide']['items'][1]['manifestId'] ?? null) !== 'cover-image') {
        throw new RuntimeException('Expected EPUB OPF guide cover reference to match the cover manifest item');
    }
    if (($result['collections'][0]['role'] ?? null) !== 'set') {
        throw new RuntimeException('Expected EPUB OPF collection role to be preserved');
    }
    if (($result['collections'][0]['metadata']['title'] ?? null) !== 'WordPress source collection') {
        throw new RuntimeException('Expected EPUB OPF collection metadata title');
    }
    if (($result['collections'][0]['links'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected EPUB OPF collection internal link to resolve to the source chapter');
    }
    if (($result['collections'][0]['links'][1]['diagnostics'][0]['type'] ?? null) !== 'external-collection-link') {
        throw new RuntimeException('Expected EPUB OPF collection external link to be reported without fetching');
    }
    if (($result['encryption']['obfuscatedFonts'][0]['part'] ?? null) !== '/EPUB/fonts/source.otf') {
        throw new RuntimeException('Expected EPUB obfuscated font preflight to identify the package font');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][0]['audioTarget'] ?? null) !== '/EPUB/audio/chapter.mp3') {
        throw new RuntimeException('Expected EPUB media-overlay audio target to resolve relative to the SMIL part');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][1]['textTarget'] ?? null) !== '/EPUB/text/chapter.xhtml#page-1') {
        throw new RuntimeException('Expected EPUB media-overlay page marker to stay addressable for review');
    }
    if (($result['mediaOverlays']['mo-chapter']['items'][2]['audioExternal'] ?? null) !== true || ($result['mediaOverlays']['mo-chapter']['items'][2]['diagnostics'][0]['type'] ?? null) !== 'external-media-overlay-reference') {
        throw new RuntimeException('Expected remote EPUB media-overlay audio to stay unfetched for review');
    }
    $foundEncryptedFont = false;
    foreach ($result['assets'] as $asset) {
        if ($asset['id'] === 'font-main' && (($asset['encrypted'] ?? false) !== true || ($asset['canExposeBytes'] ?? true) !== false)) {
            throw new RuntimeException('Expected obfuscated font asset bytes to require follow-up review');
        }
        if ($asset['id'] === 'font-main') {
            $foundEncryptedFont = true;
        }
    }
    if (!$foundEncryptedFont) {
        throw new RuntimeException('Expected obfuscated font asset in EPUB import report');
    }
    if (!str_contains($blocks, '<!-- wp:html -->') || !str_contains($blocks, 'EPUB XHTML content is preserved')) {
        throw new RuntimeException('Expected EPUB XHTML spine item to hand off as a WordPress HTML block');
    }

    echo "epub3 package handoff self-test ok\n";
    exit(0);
}

echo "EPUB3 package handoff for WordPress import:\n";
echo 'title=' . $result['metadata']['title'] . "\n";
echo 'identifier=' . $result['metadata']['identifier'] . "\n";
echo 'opfPart=' . $result['opfPart'] . "\n";
echo 'spineItems=' . count($result['spine']) . "\n";
echo 'navTarget=' . ($result['nav']['items'][0]['target'] ?? '') . "\n";
echo 'remoteNavExternal=' . (($result['nav']['items'][1]['external'] ?? false) ? 'yes' : 'no') . "\n";
echo 'landmarkTarget=' . ($result['nav']['landmarks'][0]['target'] ?? '') . "\n";
echo 'pageListTarget=' . ($result['nav']['pageList'][0]['target'] ?? '') . "\n";
echo 'guideReferences=' . count($result['guide']['items'] ?? []) . "\n";
echo 'guideTextTarget=' . ($result['guide']['items'][0]['target'] ?? '') . "\n";
echo 'collectionRole=' . ($result['collections'][0]['role'] ?? '') . "\n";
echo 'collectionFirstTarget=' . ($result['collections'][0]['links'][0]['target'] ?? '') . "\n";
echo 'obfuscatedFonts=' . count($result['encryption']['obfuscatedFonts']) . "\n";
echo 'mediaOverlayItems=' . count($result['mediaOverlays']['mo-chapter']['items'] ?? []) . "\n";
echo 'mediaOverlayAudio=' . ($result['mediaOverlays']['mo-chapter']['items'][0]['audioTarget'] ?? '') . "\n";
echo 'remoteOverlayAudio=' . ($result['mediaOverlays']['mo-chapter']['items'][2]['audioTarget'] ?? '') . "\n";
echo 'assets=' . count($result['assets']) . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
