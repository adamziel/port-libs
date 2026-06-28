<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Document part package provenance.</text:p>
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
    <dc:title>Document Part Package Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0"
  office:version="1.3">
  <office:settings>
    <config:config-item-set config:name="ooo:view-settings"/>
  </office:settings>
</office:document-settings>
XML;

$manifestXml = sprintf(
    <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:size="%d"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml" manifest:size="%d"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml" manifest:size="%d"/>
  <manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml" manifest:size="%d"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML,
    strlen($contentXml),
    strlen($stylesXml),
    strlen($metaXml),
    strlen($settingsXml)
);

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'settings.xml', 'data' => $settingsXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
], 'odt document part package provenance');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[$item[$key]] = $item;
    }

    return $indexed;
};

return [
    'surfaces ODT core XML document package parts as metadata-only provenance' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $contentXml,
        $stylesXml,
        $metaXml,
        $settingsXml
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerParts = $result['packageDocumentParts'];
        $readerByPart = $indexBy($readerParts['items'], 'part');
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactParts = $compactSummary['packageDocumentParts'];
        $compactByPath = $indexBy($compactParts['items'], 'packagePath');

        $t->same($readerParts, $result['document']->attr('packageDocumentParts'));
        $t->same($readerParts, $result['metadata']['odfPackageDocumentParts']);
        $t->same($readerParts, $result['importReport']['packageDocumentParts']);
        $t->same(4, $readerParts['count']);
        $t->same(4, $readerParts['declaredCount']);
        $t->same(0, $readerParts['undeclaredCount']);
        $t->same(strlen($contentXml) + strlen($stylesXml) + strlen($metaXml) + strlen($settingsXml), $readerParts['storedByteLength']);
        $t->same(strlen(gzdeflate($contentXml)) + strlen($stylesXml) + strlen($metaXml) + strlen($settingsXml), $readerParts['compressedByteLength']);
        $t->same(['content', 'meta', 'settings', 'styles'], $readerParts['documentPartKinds']);
        $t->same([
            'content' => 1,
            'meta' => 1,
            'settings' => 1,
            'styles' => 1,
        ], $readerParts['kindCounts']);
        $t->same([
            'odf-content' => 1,
            'odf-meta' => 1,
            'odf-settings' => 1,
            'odf-styles' => 1,
        ], $readerParts['packageRoleCounts']);
        $t->same(['package-bytes-exposable' => 4], $readerParts['packageByteExposurePolicyCounts']);
        $t->same('odf-document-part-package-metadata-only', $readerParts['byteExposurePolicy']);
        $t->same(false, $readerParts['canExposeBytes']);

        $content = $readerByPart['content.xml'];
        $t->same('content', $content['documentPartKind']);
        $t->same('odf-content', $content['packageRole']);
        $t->same(['odf-content', 'manifest-declared'], $content['roles']);
        $t->same(true, $content['declaredInManifest']);
        $t->same(strlen($contentXml), $content['storedByteLength']);
        $t->same(strlen(gzdeflate($contentXml)), $content['compressedByteLength']);
        $t->same(8, $content['compressionMethod']);
        $t->same('deflated', $content['compressionMethodName']);
        $t->same(sprintf('%08x', crc32($contentXml)), $content['storedCrc32']);
        $t->same(hash('sha256', $contentXml), $content['byteSha256']);
        $t->same('package-bytes-exposable', $content['packageByteExposurePolicy']);
        $t->same(true, $content['canExposePackageBytes']);
        $t->same(false, $content['canExposeBytes']);
        $t->same('odf-document-part-package-metadata-only', $content['reviewPolicy']);

        $settings = $readerByPart['settings.xml'];
        $t->same('settings', $settings['documentPartKind']);
        $t->same('odf-settings', $settings['packageRole']);
        $t->same(['odf-settings', 'manifest-declared'], $settings['roles']);
        $t->same(strlen($settingsXml), $settings['storedByteLength']);
        $t->same('package-bytes-exposable', $settings['packageByteExposurePolicy']);
        $t->same(false, $settings['canExposeBytes']);

        $t->same(4, $compactParts['count']);
        $t->same($readerParts['kindCounts'], $compactParts['kindCounts']);
        $t->same($readerParts['packageRoleCounts'], $compactParts['packageRoleCounts']);
        $t->same($readerParts['packageByteExposurePolicyCounts'], $compactParts['packageByteExposurePolicyCounts']);
        $t->same(['content.xml', 'styles.xml', 'meta.xml', 'settings.xml'], array_column($compactParts['items'], 'packagePath'));
        $t->same('content', $compactByPath['content.xml']['documentPartKind']);
        $t->same('odf-content', $compactByPath['content.xml']['packageRole']);
        $t->same(strlen($contentXml), $compactByPath['content.xml']['storedByteLength']);
        $t->same(hash('sha256', $contentXml), $compactByPath['content.xml']['byteSha256']);
        $t->same('odf-document-part-package-metadata-only', $compactByPath['styles.xml']['byteExposurePolicy']);
        $t->same(false, $compactByPath['styles.xml']['canExposeBytes']);
        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(6, $compactSummary['packageInventory']['corePackagePartCount']);
    },
];
