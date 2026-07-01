<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$layoutCacheBytes = 'LAYOUT-CACHE-BYTES';
$layoutCacheSize = strlen($layoutCacheBytes);

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Layout cache sidecar.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Layout Cache Sidecar Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$manifestTemplate = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="__LAYOUT_CACHE_SIZE__"/>
</manifest:manifest>
XML;

$buildPackage = static function (string $layoutCacheDeclaredSize) use (
    $contentXml,
    $stylesXml,
    $metaXml,
    $manifestTemplate,
    $layoutCacheBytes
): ZipPackage {
    $manifest = str_replace('__LAYOUT_CACHE_SIZE__', $layoutCacheDeclaredSize, $manifestTemplate);

    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifest, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
        ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
        ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
    ], 'odt layout-cache invalid declared-size package');
};

return [
    'preserves ODT layout-cache invalid declared-size provenance' => static function (TestRunner $t) use ($buildPackage, $layoutCacheBytes, $layoutCacheSize): void {
        $validReport = (new OdfReader())->readPackage($buildPackage((string) $layoutCacheSize))['packageLayoutCaches'];
        $t->same(1, $validReport['count']);
        $t->same(0, $validReport['invalidDeclaredSizeCount']);
        $t->same($layoutCacheSize, $validReport['items'][0]['declaredSize']);
        $t->same((string) $layoutCacheSize, $validReport['items'][0]['declaredSizeRaw']);
        $t->same(true, $validReport['items'][0]['declaredSizeValid']);
        $t->same(false, $validReport['items'][0]['declaredSizeInvalid']);
        $t->same(strlen($layoutCacheBytes), $validReport['items'][0]['storedByteLength']);

        $invalidPackage = $buildPackage($layoutCacheSize . 'bytes');
        $readerResult = (new OdfReader())->readPackage($invalidPackage);
        $invalidReport = $readerResult['packageLayoutCaches'];
        $invalid = $invalidReport['items'][0];

        $t->same($invalidReport, $readerResult['document']->attr('packageLayoutCaches'));
        $t->same($invalidReport, $readerResult['metadata']['odfPackageLayoutCaches']);
        $t->same($invalidReport, $readerResult['importReport']['packageLayoutCaches']);
        $t->same(1, $invalidReport['invalidDeclaredSizeCount']);
        $t->same(['odf-layout-cache-invalid-declared-size'], $invalidReport['issueCodes']);
        $t->same(null, $invalid['declaredSize']);
        $t->same($layoutCacheSize . 'bytes', $invalid['declaredSizeRaw']);
        $t->same(false, $invalid['declaredSizeValid']);
        $t->same(true, $invalid['declaredSizeInvalid']);
        $t->same(['odf-layout-cache-invalid-declared-size'], $invalid['issues']);
        $t->same('layout-cache-package-bytes-blocked', $invalid['byteExposurePolicy']);

        $compactReport = OpenDocumentPackage::fromPackage($invalidPackage)->summarize()['packageLayoutCaches'];
        $compact = $compactReport['items'][0];

        $t->same(1, $compactReport['invalidDeclaredSizeCount']);
        $t->same(['odf-layout-cache-invalid-declared-size'], $compactReport['issueCodes']);
        $t->same(null, $compact['declaredSize']);
        $t->same($layoutCacheSize . 'bytes', $compact['declaredSizeRaw']);
        $t->same(false, $compact['declaredSizeValid']);
        $t->same(true, $compact['declaredSizeInvalid']);
        $t->same(['odf-layout-cache-invalid-declared-size'], $compact['issues']);
        $t->same('layout-cache-package-bytes-blocked', $compact['byteExposurePolicy']);
    },
];
