<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Fonts/ReviewSans.woff2" manifest:media-type="font/woff2"/>
  <manifest:file-entry manifest:full-path="manifest.rdf" manifest:media-type="application/rdf+xml"/>
  <manifest:file-entry manifest:full-path="ObjectReplacements/preview.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary"/>
  <manifest:file-entry manifest:full-path="META-INF/review-state.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="database/script" manifest:media-type="text/plain"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Identity sidecar role packet.</text:p>
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
    <dc:title>Identity Sidecar Roles</dc:title>
  </office:meta>
</office:document-meta>
XML;

$rdfXml = <<<'XML'
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => '<script:module/>', 'compressionMethod' => 0],
    ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accel:acceleratorlist/>', 'compressionMethod' => 0],
    ['name' => 'Fonts/ReviewSans.woff2', 'data' => 'WOFF2BYTES', 'compressionMethod' => 0],
    ['name' => 'manifest.rdf', 'data' => $rdfXml, 'compressionMethod' => 0],
    ['name' => 'ObjectReplacements/preview.png', 'data' => 'PREVIEWPNG', 'compressionMethod' => 0],
    ['name' => 'layout-cache', 'data' => 'LAYOUTCACHE', 'compressionMethod' => 0],
    ['name' => 'META-INF/review-state.xml', 'data' => '<review-state/>', 'compressionMethod' => 0],
    ['name' => 'META-INF/documentsignatures.xml', 'data' => '<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"/>', 'compressionMethod' => 0],
    ['name' => 'database/script', 'data' => 'CREATE TABLE review_packet(id INTEGER);', 'compressionMethod' => 0],
], 'odt identity sidecar roles');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $value = $item[$key] ?? null;
        if (is_string($value) && $value !== '') {
            $indexed[$value] = $item;
        }
    }

    return $indexed;
};

return [
    'preserves compact ODT package role flags in identity metadata' => static function (TestRunner $t) use ($buildPackage, $indexBy): void {
        $summary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $identity = $summary['packageIdentity'];
        $manifestByPath = $indexBy($identity['manifestEntries'], 'packagePath');
        $packageByPath = $indexBy($identity['packageEntries'], 'path');

        $expectedFlags = [
            'Basic/Standard/Review.xml' => 'scriptPackagePart',
            'Configurations2/accelerator/current.xml' => 'configurationPackagePart',
            'Fonts/ReviewSans.woff2' => 'fontPackagePart',
            'manifest.rdf' => 'rdfMetadataPart',
            'ObjectReplacements/preview.png' => 'objectReplacementPackagePart',
            'layout-cache' => 'layoutCachePackagePart',
            'META-INF/review-state.xml' => 'metaInfSidecarPackagePart',
            'META-INF/documentsignatures.xml' => 'signaturePackagePart',
            'database/script' => 'databasePackagePart',
        ];
        $expectedManifestByteExposurePolicyCounts = [
            'configuration-package-bytes-blocked' => 1,
            'database-package-bytes-blocked' => 1,
            'font-package-bytes-blocked' => 1,
            'layout-cache-package-bytes-blocked' => 1,
            'meta-inf-sidecar-package-bytes-blocked' => 1,
            'object-replacement-package-bytes-blocked' => 1,
            'package-bytes-exposable' => 4,
            'package-root-no-bytes' => 1,
            'rdf-metadata-bytes-blocked' => 1,
            'script-package-bytes-blocked' => 1,
            'signature-package-bytes-blocked' => 1,
        ];
        $expectedManifestByteExposurePolicyPaths = [
            '/',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'Pictures/hero.png',
            'Basic/Standard/Review.xml',
            'Configurations2/accelerator/current.xml',
            'Fonts/ReviewSans.woff2',
            'manifest.rdf',
            'ObjectReplacements/preview.png',
            'layout-cache',
            'META-INF/review-state.xml',
            'META-INF/documentsignatures.xml',
            'database/script',
        ];

        foreach ($expectedFlags as $path => $flag) {
            $t->same(true, $manifestByPath[$path][$flag], "{$path} manifest identity flag");
            $t->same(true, $packageByPath[$path][$flag], "{$path} package identity flag");
            $t->same(false, $manifestByPath[$path]['canExposeBytes'], "{$path} manifest bytes blocked");
            $t->same(false, $packageByPath[$path]['canExposeBytes'], "{$path} package bytes blocked");
        }

        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));
        $t->same('script-package-bytes-blocked', $manifestByPath['Basic/Standard/Review.xml']['byteExposurePolicy']);
        $t->same('configuration-package-bytes-blocked', $manifestByPath['Configurations2/accelerator/current.xml']['byteExposurePolicy']);
        $t->same('font-package-bytes-blocked', $manifestByPath['Fonts/ReviewSans.woff2']['byteExposurePolicy']);
        $t->same('rdf-metadata-bytes-blocked', $manifestByPath['manifest.rdf']['byteExposurePolicy']);
        $t->same('object-replacement-package-bytes-blocked', $manifestByPath['ObjectReplacements/preview.png']['byteExposurePolicy']);
        $t->same('layout-cache-package-bytes-blocked', $manifestByPath['layout-cache']['byteExposurePolicy']);
        $t->same('meta-inf-sidecar-package-bytes-blocked', $manifestByPath['META-INF/review-state.xml']['byteExposurePolicy']);
        $t->same('signature-package-bytes-blocked', $manifestByPath['META-INF/documentsignatures.xml']['byteExposurePolicy']);
        $t->same('database-package-bytes-blocked', $manifestByPath['database/script']['byteExposurePolicy']);
        $t->same($expectedManifestByteExposurePolicyCounts, $summary['manifestReview']['manifestByteExposurePolicyCounts']);
        $t->same(14, $summary['manifestReview']['manifestByteExposurePolicyItemCount']);
        $t->same(
            $expectedManifestByteExposurePolicyPaths,
            array_column($summary['manifestReview']['manifestByteExposurePolicyItems'], 'path')
        );
        $t->same($expectedManifestByteExposurePolicyCounts, $identity['manifestByteExposurePolicyCounts']);
        $t->same(14, $identity['manifestByteExposurePolicyItemCount']);
        $t->same(
            $expectedManifestByteExposurePolicyPaths,
            array_column($identity['manifestByteExposurePolicyItems'], 'path')
        );
        $t->same('odf-package-identity-metadata-only', $identity['byteExposurePolicy']);
    },
];
