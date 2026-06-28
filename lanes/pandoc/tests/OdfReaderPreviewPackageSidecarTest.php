<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$previewBytes = 'PREVIEWPNG';
$encryptedBytes = 'ENCRYPTED-PREVIEW';
$orphanBytes = 'ORPHAN-PREVIEW';
$previewSize = strlen($previewBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Preview/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Preview/thumbnail.png" manifest:media-type="image/png" manifest:size="{$previewSize}"/>
  <manifest:file-entry manifest:full-path="Preview/missing.png" manifest:media-type="image/png" manifest:size="13"/>
  <manifest:file-entry manifest:full-path="Preview/encrypted.png" manifest:media-type="image/png" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="preview-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Preview package sidecars.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.3">
  <office:styles/>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.3">
  <office:meta/>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Preview/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Preview/thumbnail.png', 'data' => $previewBytes, 'compressionMethod' => 0],
    ['name' => 'Preview/encrypted.png', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Preview/orphan.png', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt preview package sidecars');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && isset($item[$key])) {
            $indexed[(string) $item[$key]] = $item;
        }
    }

    return $indexed;
};

return [
    'blocks ODT Preview package sidecars from document media handoff' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $previewBytes,
        $encryptedBytes
    ): void {
        $package = $buildPackage();
        $readerResult = (new OdfReader())->readPackage($package);
        $manifestByPart = $indexBy($readerResult['manifest'], 'part');
        $provenance = $readerResult['importReport']['manifest']['packageProvenance'];
        $readerParts = $provenance['parts'];
        $readerIdentity = $provenance['packageIdentity'];

        $t->same(['Pictures/hero.png'], array_column($readerResult['media'], 'part'));
        $t->same(true, $manifestByPart['Preview/thumbnail.png']['previewPackagePart']);
        $t->same(false, $manifestByPart['Preview/thumbnail.png']['canExposeBytes']);
        $t->same(null, $manifestByPart['Preview/thumbnail.png']['byteLength']);
        $t->same(strlen($previewBytes), $manifestByPart['Preview/thumbnail.png']['storedByteLength']);
        $t->same(null, $manifestByPart['Preview/thumbnail.png']['byteSha256']);
        $t->same('preview-package-bytes-blocked', $manifestByPart['Preview/thumbnail.png']['byteExposurePolicy']);
        $t->same('encrypted-resource-bytes-blocked', $manifestByPart['Preview/encrypted.png']['byteExposurePolicy']);
        $t->same(strlen($encryptedBytes), $manifestByPart['Preview/encrypted.png']['storedByteLength']);
        $t->same(false, $manifestByPart['Preview/missing.png']['exists']);
        $t->same(null, $manifestByPart['Preview/missing.png']['byteLength']);

        $t->same(['preview-package', 'manifest-declared'], $readerParts['Preview/thumbnail.png']['roles']);
        $t->same(['preview-package', 'undeclared-package-entry'], $readerParts['Preview/orphan.png']['roles']);
        $t->same('preview-package-bytes-blocked', $readerParts['Preview/thumbnail.png']['byteExposurePolicy']);
        $t->same(false, $readerParts['Preview/thumbnail.png']['canExposeBytes']);
        $t->same(4, $provenance['previewPackagePartCount']);
        $t->same(4, $readerIdentity['previewPackagePartCount']);
        $t->same(true, $readerIdentity['manifestEntries'][6]['previewPackagePart']);
        $t->same(true, $readerIdentity['packageEntries'][7]['previewPackagePart']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactReviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $compactInventory = $compactSummary['packageInventory'];

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(4, $compactSummary['manifestReview']['previewPackagePartCount']);
        $t->same(true, $compactReviewByPath['Preview/thumbnail.png']['previewPackagePart']);
        $t->same(false, $compactReviewByPath['Preview/thumbnail.png']['canExposeBytes']);
        $t->same(null, $compactReviewByPath['Preview/thumbnail.png']['byteLength']);
        $t->same(strlen($previewBytes), $compactReviewByPath['Preview/thumbnail.png']['storedByteLength']);
        $t->same('preview-package-bytes-blocked', $compactReviewByPath['Preview/thumbnail.png']['byteExposurePolicy']);
        $t->same('preview', $compactReviewByPath['Preview/thumbnail.png']['manifestMediaFamily']);
        $t->same(4, $compactInventory['previewPackagePartCount']);
        $t->same(['preview-package', 'manifest-declared'], $compactInventory['parts']['Preview/thumbnail.png']['roles']);
        $t->same(['preview-package', 'undeclared-package-entry'], $compactInventory['parts']['Preview/orphan.png']['roles']);
        $t->same(4, $compactSummary['packageIdentity']['previewPackagePartCount']);
        $t->same(true, $compactSummary['packageIdentity']['manifestEntries'][6]['previewPackagePart']);
        $t->same(true, $compactSummary['packageIdentity']['packageEntries'][7]['previewPackagePart']);
    },
];
