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
    <nav epub:type="landmarks">
      <ol>
        <li><a type="application/xhtml+xml" epub:type="bodymatter" href="text/chapter.xhtml#main">Begin reading</a></li>
        <li><a epub:type="review" href="https://example.invalid/epub/source-record.html">Remote review</a></li>
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
        $t->same(2, $meta['epubLandmarkEntryCount']);
        $t->same([
            ['text' => 'Begin reading', 'href' => 'OEBPS/text/chapter.xhtml#main', 'level' => 1, 'epubTypes' => ['bodymatter']],
            ['text' => 'Remote review', 'href' => 'https://example.invalid/epub/source-record.html', 'level' => 1, 'epubTypes' => ['review']],
        ], $meta['epubLandmarkEntries']);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<strong>chapter</strong>', $blocks);
        $t->contains('href="../images/cover.png"', $blocks);
        $t->contains('<!-- wp:image -->', $blocks);
        $t->contains('src="images/cover.png"', $blocks);
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
        $t->same('paragraph', $document->children[0]->type);
        $t->same('span', $document->children[0]->children[0]->type);
        $t->same('chapter.xhtml', $document->children[0]->children[0]->attr('id'));
        $t->same('heading', $document->children[1]->type);
        $t->same('Byte EPUB', $document->children[1]->attr('text'));
    },
    'preserves epub3 navigation landmarks as reader package provenance' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('EPUB/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-landmarks</dc:identifier>
    <dc:title>Landmark EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('EPUB/text/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="main">Landmark EPUB</h1><p>Body.</p></body></html>');
        $zip->addFromString('EPUB/nav.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol><li><a href="text/chapter.xhtml">Chapter</a></li></ol>
    </nav>
    <nav epub:type="landmarks">
      <ol>
        <li><a type="application/xhtml+xml" epub:type="bodymatter" href="text/chapter.xhtml#main">Begin reading</a></li>
        <li epub:type="cover"><span>Cover image</span></li>
        <li><a epub:type="review" href="https://example.invalid/epub/source-record.html">Remote review</a></li>
      </ol>
    </nav>
  </body>
</html>
HTML);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('Landmark EPUB', $meta['title']);
        $t->same(['EPUB/nav.xhtml'], $meta['epubTocResources']);
        $t->same(1, $meta['epubTocEntryCount']);
        $t->same(3, $meta['epubLandmarkEntryCount']);
        $t->same([
            ['text' => 'Begin reading', 'href' => 'EPUB/text/chapter.xhtml#main', 'level' => 1, 'epubTypes' => ['bodymatter']],
            ['text' => 'Cover image', 'href' => '', 'level' => 1, 'epubTypes' => ['cover']],
            ['text' => 'Remote review', 'href' => 'https://example.invalid/epub/source-record.html', 'level' => 1, 'epubTypes' => ['review']],
        ], $meta['epubLandmarkEntries']);
        $t->same([], $meta['epubReferencedResources']);
    },
    'preserves epub manifest and spine metadata without fetching remote targets' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-manifest-spine</dc:identifier>
    <dc:title>Manifest Spine EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml" properties="scripted mathml"/>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="cover" href="images/cover.svg" media-type="image/svg+xml" properties="cover-image svg"/>
    <item id="remote" href="https://example.invalid/remote.xhtml" media-type="application/xhtml+xml" properties="remote-resources"/>
  </manifest>
  <spine>
    <itemref id="spine-chapter" idref="chapter" properties="page-spread-right"/>
    <itemref id="spine-remote" idref="remote" linear="no"/>
    <itemref idref="missing"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/text/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Manifest Spine EPUB</h1><p>Local body.</p></body></html>');
        $zip->addFromString('OPS/nav.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="text/chapter.xhtml">Chapter</a></li></ol></nav></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('Manifest Spine EPUB', $meta['title']);
        $t->same(4, $meta['epubManifestItemCount']);
        $t->same([
            [
                'id' => 'chapter',
                'href' => 'text/chapter.xhtml',
                'path' => 'OPS/text/chapter.xhtml',
                'mediaType' => 'application/xhtml+xml',
                'properties' => ['scripted', 'mathml'],
                'external' => false,
                'readable' => true,
                'navigation' => false,
                'ncx' => false,
                'coverImage' => false,
            ],
            [
                'id' => 'nav',
                'href' => 'nav.xhtml',
                'path' => 'OPS/nav.xhtml',
                'mediaType' => 'application/xhtml+xml',
                'properties' => ['nav'],
                'external' => false,
                'readable' => true,
                'navigation' => true,
                'ncx' => false,
                'coverImage' => false,
            ],
            [
                'id' => 'cover',
                'href' => 'images/cover.svg',
                'path' => 'OPS/images/cover.svg',
                'mediaType' => 'image/svg+xml',
                'properties' => ['cover-image', 'svg'],
                'external' => false,
                'readable' => false,
                'navigation' => false,
                'ncx' => false,
                'coverImage' => true,
            ],
            [
                'id' => 'remote',
                'href' => 'https://example.invalid/remote.xhtml',
                'path' => 'https://example.invalid/remote.xhtml',
                'mediaType' => 'application/xhtml+xml',
                'properties' => ['remote-resources'],
                'external' => true,
                'readable' => false,
                'navigation' => false,
                'ncx' => false,
                'coverImage' => false,
            ],
        ], $meta['epubManifestItems']);
        $t->same(3, $meta['epubSpineItems']);
        $t->same([
            [
                'index' => 0,
                'id' => 'spine-chapter',
                'idref' => 'chapter',
                'href' => 'text/chapter.xhtml',
                'path' => 'OPS/text/chapter.xhtml',
                'mediaType' => 'application/xhtml+xml',
                'linear' => true,
                'properties' => ['page-spread-right'],
                'manifestProperties' => ['scripted', 'mathml'],
                'missingManifestItem' => false,
                'external' => false,
                'readable' => true,
            ],
            [
                'index' => 1,
                'id' => 'spine-remote',
                'idref' => 'remote',
                'href' => 'https://example.invalid/remote.xhtml',
                'path' => 'https://example.invalid/remote.xhtml',
                'mediaType' => 'application/xhtml+xml',
                'linear' => false,
                'properties' => [],
                'manifestProperties' => ['remote-resources'],
                'missingManifestItem' => false,
                'external' => true,
                'readable' => false,
            ],
            [
                'index' => 2,
                'id' => null,
                'idref' => 'missing',
                'href' => '',
                'path' => '',
                'mediaType' => '',
                'linear' => true,
                'properties' => [],
                'manifestProperties' => [],
                'missingManifestItem' => true,
                'external' => false,
                'readable' => false,
            ],
        ], $meta['epubSpineItemRefs']);
        $t->same(['OPS/text/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same(['OPS/nav.xhtml'], $meta['epubTocResources']);
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
