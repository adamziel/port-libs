<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads epub metadata and xhtml spine content into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', <<<'XML'
<?xml version="1.0"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="OEBPS/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
        $zip->addFromString('OEBPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-epub-reader</dc:identifier>
    <dc:title>EPUB Reader Demo</dc:title>
    <dc:creator>Port Libs</dc:creator>
    <dc:language>en</dc:language>
    <dc:description>Bounded EPUB reader smoke.</dc:description>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="cover" href="images/cover.png" media-type="image/png"/>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OEBPS/text/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1>EPUB Reader Demo</h1>
    <p>A <strong>chapter</strong> with <a href="../images/cover.png">a relative link</a>.</p>
    <p><img src="../images/cover.png" alt="Cover art"/></p>
    <ul><li>One</li><li>Two</li></ul>
  </body>
</html>
HTML);
        $zip->addFromString('OEBPS/nav.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter.xhtml">Start</a>
          <ol>
            <li><a href="text/chapter.xhtml#nested">Nested</a></li>
          </ol>
        </li>
      </ol>
    </nav>
  </body>
</html>
HTML);
        $zip->addFromString('OEBPS/images/cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'epub', 'blocks');
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('EPUB Reader Demo', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('en', $meta['lang']);
        $t->same('OEBPS/package.opf', $meta['epubRootfile']);
        $t->same(1, $meta['epubSpineItems']);
        $t->same(['OEBPS/text/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same(['OEBPS/images/cover.png'], $meta['epubReferencedResources']);
        $t->same(['OEBPS/images/cover.png'], $meta['epubImageResources']);
        $t->same(['OEBPS/nav.xhtml'], $meta['epubTocResources']);
        $t->same(2, $meta['epubTocEntryCount']);
        $t->same([
            ['text' => 'Start', 'href' => 'OEBPS/text/chapter.xhtml', 'level' => 1],
            ['text' => 'Nested', 'href' => 'OEBPS/text/chapter.xhtml#nested', 'level' => 2],
        ], $meta['epubTocEntries']);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<strong>chapter</strong>', $blocks);
        $t->contains('href="OEBPS/images/cover.png"', $blocks);
        $t->contains('<!-- wp:image -->', $blocks);
        $t->contains('src="OEBPS/images/cover.png"', $blocks);
        $t->contains('alt="Cover art"', $blocks);
        $t->contains('<!-- wp:list -->', $converterBlocks);
    },
    'reads epub bytes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('package.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/"><metadata><dc:title>Byte EPUB</dc:title></metadata><manifest><item id="c1" href="chapter.xhtml" media-type="application/xhtml+xml"/></manifest><spine><itemref idref="c1"/></spine></package>');
        $zip->addFromString('chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Byte EPUB</h1><p>Body.</p></body></html>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary EPUB package');
            }
            $document = PandocConverter::read($bytes, 'epub');
        } finally {
            @unlink($path);
        }

        $t->same('Byte EPUB', $document->attr('meta')['title']);
        $t->same('heading', $document->children[0]->type);
        $t->same('Byte EPUB', $document->children[0]->attr('text'));
    },
    'reads epub ncx table of contents metadata' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/"><metadata><dc:title>NCX EPUB</dc:title></metadata><manifest><item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/><item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/></manifest><spine toc="toc"><itemref idref="chapter"/></spine></package>');
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>NCX EPUB</h1><p>Body.</p></body></html>');
        $zip->addFromString('OPS/toc.ncx', '<?xml version="1.0"?><ncx xmlns="http://www.daisy.org/z3986/2005/ncx/"><navMap><navPoint id="n1"><navLabel><text>Chapter One</text></navLabel><content src="chapter.xhtml"/><navPoint id="n1-1"><navLabel><text>Section A</text></navLabel><content src="chapter.xhtml#section-a"/></navPoint></navPoint></navMap></ncx>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same(['OPS/toc.ncx'], $meta['epubTocResources']);
        $t->same(2, $meta['epubTocEntryCount']);
        $t->same([
            ['text' => 'Chapter One', 'href' => 'OPS/chapter.xhtml', 'level' => 1],
            ['text' => 'Section A', 'href' => 'OPS/chapter.xhtml#section-a', 'level' => 2],
        ], $meta['epubTocEntries']);
    },
];
