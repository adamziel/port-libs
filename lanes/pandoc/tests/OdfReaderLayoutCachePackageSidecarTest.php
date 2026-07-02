<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$layoutCacheBytes = 'LAYOUT-CACHE-BYTES';
$encryptedLayoutCacheBytes = 'ENCRYPTED-LAYOUT-CACHE-BYTES';
$heroBytes = 'PNGDATA';

$layoutCacheSize = strlen($layoutCacheBytes);
$encryptedLayoutCacheSize = strlen($encryptedLayoutCacheBytes);
$heroSize = strlen($heroBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="{$heroSize}"/>
  <manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="{$layoutCacheSize}"/>
</manifest:manifest>
XML;

$missingManifestXml = str_replace(
    '  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="' . $heroSize . '"/>',
    '  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="' . $heroSize . '"/>' . "\n"
    . '  <manifest:file-entry manifest:full-path="layout-cache-missing" manifest:media-type="application/binary" manifest:size="27"/>',
    $manifestXml
);

$invalidManifestXml = str_replace('manifest:media-type="application/binary"', 'manifest:media-type="image/png"', $manifestXml);

$encryptedManifestXml = str_replace(
    '<manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="' . $layoutCacheSize . '"/>',
    '<manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="' . $encryptedLayoutCacheSize . '"><manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="layout-cache-checksum"/></manifest:file-entry>',
    $manifestXml
);

$invalidSizeManifestXml = str_replace(
    'manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="' . $layoutCacheSize . '"',
    'manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="layout-cache-bytes"',
    $manifestXml
);

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

$buildPackage = static function (string $manifest, array $extraParts = []) use ($contentXml, $stylesXml, $metaXml, $heroBytes): ZipPackage {
    return ZipPackage::fromParts(array_merge([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifest, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
        ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
        ['name' => 'Pictures/hero.png', 'data' => $heroBytes, 'compressionMethod' => 0],
    ], $extraParts), 'odt layout-cache package sidecar');
};

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
    'reports ODT layout-cache sidecars as focused metadata-only package review data' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $manifestXml,
        $missingManifestXml,
        $invalidManifestXml,
        $encryptedManifestXml,
        $invalidSizeManifestXml,
        $layoutCacheBytes,
        $encryptedLayoutCacheBytes,
        $heroBytes
    ): void {
        $package = $buildPackage($manifestXml, [
            ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
        ]);
        $result = (new OdfReader())->readPackage($package);
        $layoutCaches = $result['packageLayoutCaches'];
        $layoutCacheByPart = $indexBy($layoutCaches['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $mediaResourceByPart = $indexBy($provenance['mediaResources']['items'], 'part');
        $mediaResourcePrecedenceByPart = $indexBy($provenance['mediaResources']['packageRolePrecedenceItems'], 'part');

        $t->same($layoutCaches, $result['document']->attr('packageLayoutCaches'));
        $t->same($layoutCaches, $result['metadata']['odfPackageLayoutCaches']);
        $t->same($layoutCaches, $result['importReport']['packageLayoutCaches']);
        $t->same(1, $layoutCaches['count']);
        $t->same(1, $layoutCaches['readableCount']);
        $t->same(1, $layoutCaches['declaredCount']);
        $t->same(0, $layoutCaches['undeclaredCount']);
        $t->same(0, $layoutCaches['missingCount']);
        $t->same(0, $layoutCaches['encryptedCount']);
        $t->same(0, $layoutCaches['invalidMediaTypeCount']);
        $t->same(0, $layoutCaches['issueCount']);
        $t->same('layout-cache-package-bytes-blocked', $layoutCaches['byteExposurePolicy']);
        $t->same('layout-cache-metadata-only', $layoutCaches['reviewPolicy']);

        $layoutCache = $layoutCacheByPart['layout-cache'];
        $t->same('application/binary', $layoutCache['mediaType']);
        $t->same('application/binary', $layoutCache['mediaTypeBase']);
        $t->same(['application/binary', 'application/octet-stream'], $layoutCache['expectedMediaTypes']);
        $t->same(true, $layoutCache['declared']);
        $t->same(true, $layoutCache['valid']);
        $t->same(strlen($layoutCacheBytes), $layoutCache['byteLength']);
        $t->same(strlen($layoutCacheBytes), $layoutCache['storedByteLength']);
        $t->same(sprintf('%08x', crc32($layoutCacheBytes)), $layoutCache['crc32']);
        $t->same(false, $layoutCache['canExposeAsDocumentMedia']);
        $t->same('layout-cache-package-bytes-blocked', $layoutCache['byteExposurePolicy']);
        $t->same([], $layoutCache['issues']);

        $manifestLayoutCache = $manifestByPart['layout-cache'];
        $t->same(true, $manifestLayoutCache['layoutCachePackagePart']);
        $t->same(false, $manifestLayoutCache['canExposeBytes']);
        $t->same(null, $manifestLayoutCache['byteLength']);
        $t->same(strlen($layoutCacheBytes), $manifestLayoutCache['storedByteLength']);
        $t->same(null, $manifestLayoutCache['byteSha256']);
        $t->same('layout-cache-package-bytes-blocked', $manifestLayoutCache['byteExposurePolicy']);

        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(strlen($heroBytes), $result['media'][0]['byteLength']);
        $t->same(1, $provenance['layoutCachePartCount']);
        $t->same(1, $provenance['roleCounts']['layout-cache']);
        $t->same(['layout-cache', 'manifest-declared'], $provenance['parts']['layout-cache']['roles']);
        $t->same(true, $provenance['parts']['layout-cache']['layoutCachePackagePart']);
        $t->same(false, $provenance['parts']['layout-cache']['canExposeBytes']);
        $t->same(1, $provenance['mediaResources']['mediaResourceCount']);
        $t->same(1, $provenance['mediaResources']['packageRolePrecedenceCount']);
        $t->same(['layout-cache'], $mediaResourceByPart['layout-cache']['packageRolePrecedence']);
        $t->same(false, $mediaResourceByPart['layout-cache']['mediaResource']);
        $t->same(['odf-media-resource-package-role-precedence'], $mediaResourceByPart['layout-cache']['issues']);
        $t->same('layout-cache-package-bytes-blocked', $mediaResourcePrecedenceByPart['layout-cache']['byteExposurePolicy']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Layout cache sidecar.', $blocks);
        $t->same(false, str_contains($blocks, $layoutCacheBytes));

        $missing = (new OdfReader())->readPackage($buildPackage($missingManifestXml))['packageLayoutCaches']['items'][0];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-layout-cache-missing-package-part'], $missing['issues']);
        $t->same('layout-cache-package-bytes-blocked', $missing['byteExposurePolicy']);

        $invalid = (new OdfReader())->readPackage($buildPackage($invalidManifestXml, [
            ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
        ]))['packageLayoutCaches']['items'][0];
        $t->same(false, $invalid['valid']);
        $t->same(['odf-layout-cache-invalid-media-type'], $invalid['issues']);
        $t->same('layout-cache-package-bytes-blocked', $invalid['byteExposurePolicy']);

        $invalidSizeResult = (new OdfReader())->readPackage($buildPackage($invalidSizeManifestXml, [
            ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
        ]));
        $invalidSizeCaches = $invalidSizeResult['packageLayoutCaches'];
        $invalidSize = $invalidSizeCaches['items'][0];
        $invalidSizeManifest = $indexBy($invalidSizeResult['manifest'], 'part')['layout-cache'];
        $t->same(1, $invalidSizeCaches['invalidDeclaredSizeCount']);
        $t->same(1, $invalidSizeCaches['issueCount']);
        $t->same(['odf-layout-cache-invalid-declared-size'], $invalidSizeCaches['issueCodes']);
        $t->same('layout-cache-bytes', $invalidSize['declaredSizeRaw']);
        $t->same(false, $invalidSize['declaredSizeValid']);
        $t->same(true, $invalidSize['declaredSizeInvalid']);
        $t->same(['odf-layout-cache-invalid-declared-size'], $invalidSize['issues']);
        $t->same(['odf-manifest-invalid-declared-size'], $invalidSizeManifest['diagnostics']);

        $encrypted = (new OdfReader())->readPackage($buildPackage($encryptedManifestXml, [
            ['name' => 'layout-cache', 'data' => $encryptedLayoutCacheBytes, 'compressionMethod' => 0],
        ]))['packageLayoutCaches']['items'][0];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedLayoutCacheBytes), $encrypted['storedByteLength']);
        $t->same(['odf-layout-cache-encrypted-package-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $undeclaredResult = (new OdfReader())->readPackage($buildPackage(str_replace(
            '  <manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="' . strlen($layoutCacheBytes) . '"/>' . "\n",
            '',
            $manifestXml
        ), [
            ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
        ]));
        $undeclared = $undeclaredResult['packageLayoutCaches']['items'][0];
        $t->same(false, $undeclared['declared']);
        $t->same(true, $undeclared['undeclared']);
        $t->same('application/binary', $undeclared['mediaType']);
        $t->same(['odf-layout-cache-undeclared-package-part'], $undeclared['issues']);
        $t->same(['layout-cache', 'undeclared-package-entry'], $undeclaredResult['importReport']['manifest']['packageProvenance']['parts']['layout-cache']['roles']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactLayoutCaches = $compactSummary['packageLayoutCaches'];
        $compactLayoutCacheByPath = $indexBy($compactLayoutCaches['items'], 'packagePath');
        $compactReviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $compactMediaResourceByPart = $indexBy($compactSummary['manifestReview']['mediaResources']['items'], 'part');
        $compactMediaResourcePrecedenceByPart = $indexBy($compactSummary['manifestReview']['mediaResources']['packageRolePrecedenceItems'], 'part');
        $inventory = $compactSummary['packageInventory'];

        $t->same(1, $compactLayoutCaches['count']);
        $t->same(1, $compactLayoutCaches['readableCount']);
        $t->same(1, $compactLayoutCaches['declaredCount']);
        $t->same(0, $compactLayoutCaches['undeclaredCount']);
        $t->same(0, $compactLayoutCaches['missingCount']);
        $t->same(0, $compactLayoutCaches['encryptedCount']);
        $t->same(0, $compactLayoutCaches['invalidMediaTypeCount']);
        $t->same(0, $compactLayoutCaches['invalidDeclaredSizeCount']);
        $t->same('layout-cache-package-bytes-blocked', $compactLayoutCaches['byteExposurePolicy']);
        $t->same('layout-cache-metadata-only', $compactLayoutCaches['reviewPolicy']);

        $compactLayoutCache = $compactLayoutCacheByPath['layout-cache'];
        $t->same('application/binary', $compactLayoutCache['mediaType']);
        $t->same(true, $compactLayoutCache['valid']);
        $t->same(strlen($layoutCacheBytes), $compactLayoutCache['byteLength']);
        $t->same(sprintf('%08x', crc32($layoutCacheBytes)), $compactLayoutCache['crc32']);
        $t->same(false, $compactLayoutCache['canExposeAsDocumentMedia']);
        $t->same([], $compactLayoutCache['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(1, $compactSummary['manifestReview']['layoutCachePartCount']);
        $t->same(true, $compactReviewByPath['layout-cache']['layoutCachePackagePart']);
        $t->same(false, $compactReviewByPath['layout-cache']['canExposeBytes']);
        $t->same(null, $compactReviewByPath['layout-cache']['byteLength']);
        $t->same(strlen($layoutCacheBytes), $compactReviewByPath['layout-cache']['storedByteLength']);
        $t->same('layout-cache-package-bytes-blocked', $compactReviewByPath['layout-cache']['byteExposurePolicy']);
        $t->same('layout-cache', $compactReviewByPath['layout-cache']['manifestMediaFamily']);
        $t->same(1, $compactSummary['manifestReview']['mediaResources']['mediaResourceCount']);
        $t->same(1, $compactSummary['manifestReview']['mediaResources']['packageRolePrecedenceCount']);
        $t->same(['layout-cache'], $compactMediaResourceByPart['layout-cache']['packageRolePrecedence']);
        $t->same(false, $compactMediaResourceByPart['layout-cache']['mediaResource']);
        $t->same(['odf-media-resource-package-role-precedence'], $compactMediaResourceByPart['layout-cache']['issues']);
        $t->same('layout-cache-package-bytes-blocked', $compactMediaResourcePrecedenceByPart['layout-cache']['byteExposurePolicy']);
        $t->same(1, $inventory['layoutCachePartCount']);
        $t->same(1, $inventory['roleCounts']['layout-cache']);
        $t->same(['layout-cache', 'manifest-declared'], $inventory['parts']['layout-cache']['roles']);
        $t->same(true, $inventory['parts']['layout-cache']['layoutCachePackagePart']);
        $t->same(false, $inventory['parts']['layout-cache']['canExposeBytes']);

        $compactInvalidSizeSummary = OpenDocumentPackage::fromPackage($buildPackage($invalidSizeManifestXml, [
            ['name' => 'layout-cache', 'data' => $layoutCacheBytes, 'compressionMethod' => 0],
        ]))->summarize();
        $compactInvalidSize = $compactInvalidSizeSummary['packageLayoutCaches']['items'][0];
        $compactInvalidSizeReview = $indexBy($compactInvalidSizeSummary['manifestReview']['items'], 'path')['layout-cache'];
        $t->same(1, $compactInvalidSizeSummary['packageLayoutCaches']['invalidDeclaredSizeCount']);
        $t->same(['odf-layout-cache-invalid-declared-size'], $compactInvalidSizeSummary['packageLayoutCaches']['issueCodes']);
        $t->same('layout-cache-bytes', $compactInvalidSize['declaredSizeRaw']);
        $t->same(false, $compactInvalidSize['declaredSizeValid']);
        $t->same(true, $compactInvalidSize['declaredSizeInvalid']);
        $t->same(['odf-layout-cache-invalid-declared-size'], $compactInvalidSize['issues']);
        $t->same(['odf-manifest-invalid-declared-size'], $compactInvalidSizeReview['diagnostics']);
    },
];
