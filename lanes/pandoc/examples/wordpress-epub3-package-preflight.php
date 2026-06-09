<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\EpubPackage;
use PortLibs\Pandoc\ZipPackage;

$containerXml = <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/content.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

$opfXml = <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:wordpress-import-epub</dc:identifier>
    <dc:title>WordPress EPUB Import Packet</dc:title>
    <dc:creator>Data Liberation Team</dc:creator>
    <dc:language>en</dc:language>
    <meta property="dcterms:modified">2026-06-03T22:09:50Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter1" href="chapters/intro.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter2" href="chapters/review.xhtml" media-type="application/xhtml+xml"/>
    <item id="cover" href="media/cover.png" media-type="image/png" properties="cover-image"/>
    <item id="css" href="styles/import.css" media-type="text/css"/>
  </manifest>
  <spine>
    <itemref idref="chapter1"/>
    <itemref idref="chapter2"/>
  </spine>
</package>
XML;

$navXml = <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapters/intro.xhtml">Intro</a></li>
        <li><a href="chapters/review.xhtml">Review checklist</a></li>
      </ol>
    </nav>
  </body>
</html>
XML;

$package = ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
    ['name' => 'META-INF/container.xml', 'data' => $containerXml],
    ['name' => 'EPUB/content.opf', 'data' => $opfXml],
    ['name' => 'EPUB/nav.xhtml', 'data' => $navXml],
    ['name' => 'EPUB/chapters/intro.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Intro</h1></body></html>'],
    ['name' => 'EPUB/chapters/review.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Review checklist</h1></body></html>'],
    ['name' => 'EPUB/media/cover.png', 'data' => 'PNG'],
    ['name' => 'EPUB/styles/import.css', 'data' => 'body { line-height: 1.5; }'],
]);

$epub = EpubPackage::fromPackage($package);
$summary = $epub->summary();

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        'WordPress EPUB Import Packet',
        ['/EPUB/chapters/intro.xhtml', '/EPUB/chapters/review.xhtml'],
        ['Intro', 'Review checklist'],
        '/EPUB/media/cover.png',
        ['/EPUB/styles/import.css'],
    ];
    $actual = [
        $summary['wordpressImport']['title'],
        $summary['wordpressImport']['readingOrderParts'],
        $summary['wordpressImport']['navigationLabels'],
        $summary['wordpressImport']['coverImagePart'],
        $summary['wordpressImport']['stylesheetParts'],
    ];

    if ($actual !== $expected) {
        throw new RuntimeException('EPUB3 package preflight self-test failed');
    }

    echo "epub3 package preflight self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
