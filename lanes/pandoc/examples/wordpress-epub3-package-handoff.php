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
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="style" href="styles/review.css" media-type="text/css"/>
    <item id="font-main" href="fonts/source.otf" media-type="application/vnd.ms-opentype"/>
    <item id="cover-image" href="images/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine toc="toc">
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

$navXhtml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter.xhtml#source">Source chapter</a></li>
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

$ncxXml = <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="source" playOrder="1">
      <navLabel><text>Source chapter</text></navLabel>
      <content src="text/chapter.xhtml#source"/>
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
    if (($result['ncx']['items'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#source') {
        throw new RuntimeException('Expected NCX content src to resolve to the chapter fragment');
    }
    if (($result['nav']['landmarks'][0]['type'] ?? null) !== 'bodymatter') {
        throw new RuntimeException('Expected EPUB nav landmarks to preserve item type');
    }
    if (($result['nav']['pageList'][0]['target'] ?? null) !== '/EPUB/text/chapter.xhtml#page-1') {
        throw new RuntimeException('Expected EPUB page-list target to resolve to the source page marker');
    }
    if (($result['encryption']['obfuscatedFonts'][0]['part'] ?? null) !== '/EPUB/fonts/source.otf') {
        throw new RuntimeException('Expected EPUB obfuscated font preflight to identify the package font');
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
echo 'landmarkTarget=' . ($result['nav']['landmarks'][0]['target'] ?? '') . "\n";
echo 'pageListTarget=' . ($result['nav']['pageList'][0]['target'] ?? '') . "\n";
echo 'obfuscatedFonts=' . count($result['encryption']['obfuscatedFonts']) . "\n";
echo 'assets=' . count($result['assets']) . "\n";
echo "wordpressBlocks:\n" . $blocks . "\n";
