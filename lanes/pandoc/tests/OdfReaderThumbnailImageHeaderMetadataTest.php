<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$pngThumbnail = "\x89PNG\r\n\x1a\n"
    . pack('N', 13)
    . 'IHDR'
    . pack('N', 64)
    . pack('N', 32)
    . "\x08\x02\x00\x00\x00"
    . 'CRC!';

$jpegThumbnail = "\xff\xd8"
    . "\xff\xe0"
    . pack('n', 4)
    . 'JF'
    . "\xff\xc0"
    . pack('n', 11)
    . "\x08"
    . pack('n', 45)
    . pack('n', 120)
    . "\x01\x01\x11\x00"
    . "\xff\xd9";

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Thumbnail image header package.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:styles/>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Thumbnail Header Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Thumbnails/thumbnail.png" manifest:media-type="image/png" manifest:size="__PNG_SIZE__"/>
</manifest:manifest>
XML;

$manifestXml = str_replace('__PNG_SIZE__', (string) strlen($pngThumbnail), $manifestXml);

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Thumbnails/thumbnail.png', 'data' => $pngThumbnail, 'compressionMethod' => 0],
    ['name' => 'Thumbnails/orphan.jpg', 'data' => $jpegThumbnail, 'compressionMethod' => 0],
], 'odt thumbnail image header package');

$indexByPart = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $part = $item[$key] ?? null;
        if (is_string($part) && $part !== '') {
            $indexed[$part] = $item;
        }
    }

    return $indexed;
};

$assertThumbnailHeaders = static function (TestRunner $t, array $report, string $partKey) use ($indexByPart): void {
    $items = $indexByPart($report['items'], $partKey);
    $summaryItems = $indexByPart($report['imageHeaderItems'], 'part');

    $t->same(2, $report['count']);
    $t->same(2, $report['readableCount']);
    $t->same(1, $report['declaredCount']);
    $t->same(1, $report['undeclaredCount']);
    $t->same(2, $report['imageHeaderCount']);
    $t->same(['jpeg' => 1, 'png' => 1], $report['imageHeaderFormatCounts']);

    $png = $items['Thumbnails/thumbnail.png'];
    $t->same(true, $png['imageHeaderDetected']);
    $t->same('png', $png['imageHeaderFormat']);
    $t->same(64, $png['imageWidth']);
    $t->same(32, $png['imageHeight']);
    $t->same(2048, $png['imagePixelCount']);
    $t->same('package-thumbnail-image-header-metadata-only', $png['imageHeaderByteExposurePolicy']);
    $t->same(false, $png['imageHeader']['canExposeBytes']);
    $t->same(false, $png['canExposeAsDocumentMedia']);
    $t->same([], $png['issues']);

    $jpeg = $items['Thumbnails/orphan.jpg'];
    $t->same(false, $jpeg['declared']);
    $t->same(true, $jpeg['undeclared']);
    $t->same(true, $jpeg['imageHeaderDetected']);
    $t->same('jpeg', $jpeg['imageHeaderFormat']);
    $t->same(120, $jpeg['imageWidth']);
    $t->same(45, $jpeg['imageHeight']);
    $t->same(5400, $jpeg['imagePixelCount']);
    $t->same('jpeg-sof-c0', $jpeg['imageHeader']['source']);
    $t->same('package-thumbnail-image-header-metadata-only', $jpeg['imageHeader']['byteExposurePolicy']);
    $t->same(['odf-thumbnail-undeclared-package-part'], $jpeg['issues']);

    $t->same(64, $summaryItems['Thumbnails/thumbnail.png']['width']);
    $t->same(32, $summaryItems['Thumbnails/thumbnail.png']['height']);
    $t->same(false, $summaryItems['Thumbnails/thumbnail.png']['canExposeBytes']);
    $t->same(120, $summaryItems['Thumbnails/orphan.jpg']['width']);
    $t->same(45, $summaryItems['Thumbnails/orphan.jpg']['height']);
    $t->same('package-thumbnail-image-header-metadata-only', $summaryItems['Thumbnails/orphan.jpg']['byteExposurePolicy']);
};

return [
    'preserves ODT package thumbnail image dimensions as metadata-only header review' => static function (TestRunner $t) use ($buildPackage, $assertThumbnailHeaders): void {
        $package = $buildPackage();
        $readerResult = (new OdfReader())->readPackage($package);
        $readerThumbnails = $readerResult['importReport']['packageThumbnails'];
        $compactThumbnails = OpenDocumentPackage::fromPackage($package)->summarize()['packageThumbnails'];

        $t->same($readerThumbnails, $readerResult['metadata']['odfPackageThumbnails']);
        $t->same($readerThumbnails, $readerResult['document']->attr('packageThumbnails'));
        $t->same([], $readerResult['media']);
        $assertThumbnailHeaders($t, $readerThumbnails, 'part');
        $assertThumbnailHeaders($t, $compactThumbnails, 'packagePath');
    },
];
