<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubMediaBagComparisonHarness;
use PortLibs\Pandoc\EpubPackageReader;
use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\NativeWriter;

$imageBytes = static fn (): array => [
    'images/check.gif' => base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw=='),
    'images/photo' => "\xFF\xD8\xFF\xD9",
    'images/chart.png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
];

$opfXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-direct-image-spine</dc:identifier>
    <dc:title>Direct Image Spine EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="gif" href="images/check.gif" media-type="image/gif"/>
    <item id="jpeg" href="images/photo" media-type="image/jpeg"/>
    <item id="png" href="images/chart.png" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="gif"/>
    <itemref idref="jpeg"/>
    <itemref idref="png"/>
  </spine>
</package>
XML;

$containerXml = <<<'XML'
<?xml version="1.0"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

$makeTempDir = static function (string $prefix): string {
    $base = tempnam(sys_get_temp_dir(), $prefix);
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException('Unable to create temporary directory: ' . $base);
    }

    return $base;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
};

$writeFile = static function (string $root, string $relativePath, string $bytes): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create fixture directory: ' . $directory);
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Unable to write fixture file: ' . $path);
    }
};

$writeDirectImagePackage = static function (string $root) use ($writeFile, $containerXml, $opfXml, $imageBytes): void {
    $writeFile($root, 'META-INF/container.xml', $containerXml);
    $writeFile($root, 'OPS/package.opf', $opfXml);
    foreach ($imageBytes() as $path => $bytes) {
        $writeFile($root, 'OPS/' . $path, $bytes);
    }
};

$writeDirectImageEpub = static function (string $path) use ($containerXml, $opfXml, $imageBytes): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create temporary EPUB package');
    }
    $zip->addFromString('META-INF/container.xml', $containerXml);
    $zip->addFromString('OPS/package.opf', $opfXml);
    foreach ($imageBytes() as $imagePath => $bytes) {
        $zip->addFromString('OPS/' . $imagePath, $bytes);
    }
    $zip->close();
};

return [
    'reads direct image spine items as pandoc image blocks with media evidence' => static function (TestRunner $t) use ($writeDirectImageEpub, $imageBytes): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-direct-image-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }
        $writeDirectImageEpub($path);

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $fullPaths = [
            'OPS/images/check.gif',
            'OPS/images/photo',
            'OPS/images/chart.png',
        ];
        $urls = [
            'images/check.gif',
            'images/photo',
            'images/chart.png',
        ];

        $t->same('Direct Image Spine EPUB', $meta['title']);
        $t->same(['paragraph', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image', 'image', 'image'], array_map(static fn ($node): ?string => $node->children[0]->type ?? null, $document->children));
        $t->same($urls, array_map(static fn ($node): string => (string) $node->children[0]->attr('url'), $document->children));
        $t->same([
            ['', ''],
            ['', ''],
            ['', ''],
        ], array_map(static fn ($node): array => [
            (string) $node->children[0]->attr('alt'),
            (string) $node->children[0]->attr('title'),
        ], $document->children));
        $t->same($fullPaths, $meta['epubReadableResources']);
        $t->same($fullPaths, $meta['epubReferencedResources']);
        $t->same($fullPaths, $meta['epubImageResources']);
        $t->same([true, true, true], array_map(static fn (array $item): bool => (bool) $item['readable'], $meta['epubManifestItems']));
        $t->same([true, true, true], array_map(static fn (array $item): bool => (bool) $item['readable'], $meta['epubSpineItemRefs']));
        $t->contains('( "images/check.gif" , "" )', $native);
        $t->contains('( "images/photo" , "" )', $native);
        $t->contains('( "images/chart.png" , "" )', $native);
    },

    'matches upstream-style media bag evidence for direct image spine items' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeDirectImageEpub, $imageBytes): void {
        $root = $makeTempDir('pandoc-epub-direct-image-bag-');
        try {
            $writeFile($root, 'test/Tests/Readers/EPUB.hs', sprintf(
                <<<'HS'
directImageSpineBag = [("images/check.gif","image/gif",%d)
                      ,("images/photo","image/jpeg",%d)
                      ,("images/chart.png","image/png",%d)]

tests = [ testCase "direct image spine bag"
          (testMediaBag "epub/direct-image-spine.epub" directImageSpineBag) ]
HS,
                strlen($imageBytes()['images/check.gif']),
                strlen($imageBytes()['images/photo']),
                strlen($imageBytes()['images/chart.png'])
            ));
            $writeFile($root, 'src/Text/Pandoc/Readers/EPUB.hs', "module Text.Pandoc.Readers.EPUB where\n");
            $epubPath = $root . '/test/epub/direct-image-spine.epub';
            if (!is_dir(dirname($epubPath)) && !mkdir(dirname($epubPath), 0777, true) && !is_dir(dirname($epubPath))) {
                throw new RuntimeException('Unable to create EPUB fixture directory');
            }
            $writeDirectImageEpub($epubPath);

            $report = (new EpubMediaBagComparisonHarness())->run($root);

            $t->same('completed', $report['status']);
            $t->same(1, $report['comparedCaseCount']);
            $t->same(1, $report['epubParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(3, $report['expectedMediaItemCount']);
            $t->same(3, $report['actualMediaItemCount']);
            $t->same(1, $report['mediaBagMatchCount']);
            $t->same(0, $report['mediaBagMismatchCount']);
            $t->same('media-bag-equality-observed-not-runner-parity', $report['mediaBagParityStatus']);
        } finally {
            $removeTree($root);
        }
    },

    'reads direct image spine items from unpacked epub package directories' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDirectImagePackage): void {
        $root = $makeTempDir('pandoc-epub-direct-image-package-');
        try {
            $writeDirectImagePackage($root);
            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');

            $t->same(['paragraph', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
            $t->same(['image', 'image', 'image'], array_map(static fn ($node): ?string => $node->children[0]->type ?? null, $document->children));
            $t->same([
                'OPS/images/check.gif',
                'OPS/images/photo',
                'OPS/images/chart.png',
            ], array_map(static fn ($node): string => (string) $node->children[0]->attr('url'), $document->children));
            $t->same([true, true, true], array_map(static fn (array $item): bool => (bool) $item['readable'], $epub['spine']));
            $t->same(3, $epub['spineReport']['readableItemCount']);
            $t->same(0, $epub['spineReport']['skippedItemCount']);
        } finally {
            $removeTree($root);
        }
    },
];
