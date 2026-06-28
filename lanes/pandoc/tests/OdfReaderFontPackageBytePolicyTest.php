<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$reviewFont = 'WOFF2-FONT-BYTES';
$assetFont = 'ASSET-WOFF-BYTES';
$invalidFont = 'NOT-A-FONT-BINARY';
$lockedFont = 'LOCKED-OTF-BYTES';
$orphanFont = 'ORPHAN-TTF-BYTES';

$reviewFontSize = strlen($reviewFont);
$assetFontSize = strlen($assetFont);
$invalidFontSize = strlen($invalidFont);
$lockedFontSize = strlen($lockedFont);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Fonts/ReviewSans.woff2" manifest:media-type="font/woff2" manifest:size="{$reviewFontSize}"/>
  <manifest:file-entry manifest:full-path="Assets/source.woff" manifest:media-type="font/woff" manifest:size="{$assetFontSize}"/>
  <manifest:file-entry manifest:full-path="Fonts/missing.ttf" manifest:media-type="font/ttf" manifest:size="21"/>
  <manifest:file-entry manifest:full-path="Fonts/not-font.bin" manifest:media-type="application/octet-stream" manifest:size="{$invalidFontSize}"/>
  <manifest:file-entry manifest:full-path="Fonts/locked.otf" manifest:media-type="font/otf" manifest:size="{$lockedFontSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="font-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Font package byte policy packet.</text:p>
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
    <dc:title>Font Package Byte Policy</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Fonts/ReviewSans.woff2', 'data' => $reviewFont, 'compressionMethod' => 0],
    ['name' => 'Assets/source.woff', 'data' => $assetFont, 'compressionMethod' => 0],
    ['name' => 'Fonts/not-font.bin', 'data' => $invalidFont, 'compressionMethod' => 0],
    ['name' => 'Fonts/locked.otf', 'data' => $lockedFont, 'compressionMethod' => 0],
    ['name' => 'Fonts/orphan.ttf', 'data' => $orphanFont, 'compressionMethod' => 0],
], 'odt font package byte policy sidecars');

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
    'reports ODT font package parts with explicit metadata-only byte policies' => static function (TestRunner $t) use (
        $buildPackage,
        $reviewFont,
        $assetFont,
        $invalidFont,
        $orphanFont,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerFonts = $result['packageFonts'];
        $readerItems = $indexBy($readerFonts['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];
        $readerMediaResources = $readerProvenance['mediaResources'];
        $readerMediaItems = $indexBy($readerMediaResources['items'], 'part');

        $t->same($readerFonts, $result['document']->attr('packageFonts'));
        $t->same($readerFonts, $result['metadata']['odfPackageFonts']);
        $t->same($readerFonts, $result['importReport']['packageFonts']);
        $t->same(6, $readerFonts['count']);
        $t->same(4, $readerFonts['readableCount']);
        $t->same(5, $readerFonts['declaredCount']);
        $t->same(1, $readerFonts['undeclaredCount']);
        $t->same(1, $readerFonts['missingCount']);
        $t->same(1, $readerFonts['encryptedCount']);
        $t->same(1, $readerFonts['invalidMediaTypeCount']);
        $t->same(4, $readerFonts['issueCount']);
        $t->same([
            'odf-font-encrypted-package-part',
            'odf-font-invalid-media-type',
            'odf-font-missing-package-part',
            'odf-font-undeclared-package-part',
        ], $readerFonts['issueCodes']);
        $t->same('font-package-bytes-blocked', $readerFonts['byteExposurePolicy']);
        $t->same('package-font-metadata-only', $readerFonts['reviewPolicy']);

        $review = $readerItems['Fonts/ReviewSans.woff2'];
        $t->same(true, $review['valid']);
        $t->same('woff2', $review['fontFormat']);
        $t->same(strlen($reviewFont), $review['byteLength']);
        $t->same(sprintf('%08x', crc32($reviewFont)), $review['crc32']);
        $t->same(false, $review['canExposeBytes']);
        $t->same(false, $review['canExposeAsDocumentMedia']);
        $t->same('font-package-bytes-blocked', $review['byteExposurePolicy']);
        $t->same('package-font-metadata-only', $review['reviewPolicy']);

        $asset = $readerItems['Assets/source.woff'];
        $t->same(true, $asset['declared']);
        $t->same('woff', $asset['fontFormat']);
        $t->same(strlen($assetFont), $asset['byteLength']);
        $t->same(false, $asset['canExposeBytes']);
        $t->same('font-package-bytes-blocked', $asset['byteExposurePolicy']);

        $missing = $readerItems['Fonts/missing.ttf'];
        $t->same(false, $missing['exists']);
        $t->same(null, $missing['byteLength']);
        $t->same(false, $missing['canExposeBytes']);
        $t->same('font-package-bytes-blocked', $missing['byteExposurePolicy']);
        $t->same(['odf-font-missing-package-part'], $missing['issues']);

        $invalid = $readerItems['Fonts/not-font.bin'];
        $t->same(false, $invalid['valid']);
        $t->same('unknown', $invalid['fontFormat']);
        $t->same(strlen($invalidFont), $invalid['byteLength']);
        $t->same(false, $invalid['canExposeBytes']);
        $t->same('font-package-bytes-blocked', $invalid['byteExposurePolicy']);
        $t->same(['odf-font-invalid-media-type'], $invalid['issues']);

        $encrypted = $readerItems['Fonts/locked.otf'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(false, $encrypted['canExposeBytes']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-font-encrypted-package-part'], $encrypted['issues']);

        $orphan = $readerItems['Fonts/orphan.ttf'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same(strlen($orphanFont), $orphan['byteLength']);
        $t->same(false, $orphan['canExposeBytes']);
        $t->same('font-package-bytes-blocked', $orphan['byteExposurePolicy']);
        $t->same(['odf-font-undeclared-package-part'], $orphan['issues']);

        $manifestReview = $manifestByPart['Fonts/ReviewSans.woff2'];
        $t->same(true, $manifestReview['fontPackagePart']);
        $t->same(false, $manifestReview['canExposeBytes']);
        $t->same(null, $manifestReview['byteLength']);
        $t->same(strlen($reviewFont), $manifestReview['storedByteLength']);
        $t->same(null, $manifestReview['byteSha256']);
        $t->same('font-package-bytes-blocked', $manifestReview['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(6, $readerMediaResources['manifestDeclaredCount']);
        $t->same(1, $readerMediaResources['mediaResourceCount']);
        $t->same(1, $readerMediaResources['mediaResourceExistingCount']);
        $t->same(0, $readerMediaResources['mediaResourceMissingCount']);
        $t->same(1, $readerMediaResources['mediaResourceCanExposeCount']);
        $t->same(5, $readerMediaResources['existingCount']);
        $t->same(1, $readerMediaResources['missingCount']);
        $t->same(['image' => 1, 'audio' => 0, 'video' => 0, 'other' => 5], $readerMediaResources['familyCounts']);
        $t->same([
            'application/octet-stream' => 1,
            'font/otf' => 1,
            'font/ttf' => 1,
            'font/woff' => 1,
            'font/woff2' => 1,
            'image/png' => 1,
        ], $readerMediaResources['mediaTypeBaseCounts']);
        $t->same(5, $readerMediaResources['packageRolePrecedenceCount']);
        $t->same([
            'odf-media-resource-missing-package-part' => 1,
            'odf-media-resource-package-role-precedence' => 5,
        ], $readerMediaResources['issueCodeCounts']);
        $t->same([
            'Fonts/ReviewSans.woff2',
            'Assets/source.woff',
            'Fonts/missing.ttf',
            'Fonts/not-font.bin',
            'Fonts/locked.otf',
        ], array_column($readerMediaResources['packageRolePrecedenceItems'], 'part'));
        $t->same(true, $readerMediaItems['Pictures/hero.png']['mediaResource']);
        $t->same([], $readerMediaItems['Pictures/hero.png']['packageRolePrecedence'] ?? []);
        $t->same(false, $readerMediaItems['Fonts/ReviewSans.woff2']['mediaResource']);
        $t->same(['font-package'], $readerMediaItems['Fonts/ReviewSans.woff2']['packageRolePrecedence']);
        $t->same('font-package-bytes-blocked', $readerMediaItems['Fonts/ReviewSans.woff2']['byteExposurePolicy']);
        $t->same(false, $readerMediaItems['Assets/source.woff']['mediaResource']);
        $t->same(['font-package'], $readerMediaItems['Assets/source.woff']['packageRolePrecedence']);
        $t->same(false, $readerMediaItems['Fonts/missing.ttf']['exists']);
        $t->same(['odf-media-resource-missing-package-part', 'odf-media-resource-package-role-precedence'], $readerMediaItems['Fonts/missing.ttf']['issues']);
        $t->same(false, $readerMediaItems['Fonts/not-font.bin']['mediaResource']);
        $t->same(['font-package'], $readerMediaItems['Fonts/not-font.bin']['packageRolePrecedence']);
        $t->same(false, $readerMediaItems['Fonts/locked.otf']['canExposeBytes']);
        $t->same('encrypted-resource-bytes-blocked', $readerMediaItems['Fonts/locked.otf']['byteExposurePolicy']);
        $t->same(5, $readerProvenance['packageFontPartCount']);
        $t->same(5, $readerProvenance['roleCounts']['font-package']);
        $t->same(['font-package', 'manifest-declared'], $readerProvenance['parts']['Fonts/ReviewSans.woff2']['roles']);
        $t->same(['font-package', 'undeclared-package-entry'], $readerProvenance['parts']['Fonts/orphan.ttf']['roles']);
        $t->same(false, $readerProvenance['parts']['Fonts/ReviewSans.woff2']['canExposeBytes']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactFonts = $compactSummary['packageFonts'];
        $compactItems = $indexBy($compactFonts['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $compactMediaResources = $compactSummary['manifestReview']['mediaResources'];
        $compactMediaItems = $indexBy($compactMediaResources['items'], 'part');
        $inventory = $compactSummary['packageInventory'];

        $t->same(6, $compactFonts['count']);
        $t->same(4, $compactFonts['readableCount']);
        $t->same(5, $compactFonts['declaredCount']);
        $t->same(1, $compactFonts['undeclaredCount']);
        $t->same(1, $compactFonts['missingCount']);
        $t->same(1, $compactFonts['encryptedCount']);
        $t->same(1, $compactFonts['invalidMediaTypeCount']);
        $t->same(4, $compactFonts['issueCount']);
        $t->same($readerFonts['issueCodes'], $compactFonts['issueCodes']);
        $t->same('font-package-bytes-blocked', $compactFonts['byteExposurePolicy']);
        $t->same('package-font-metadata-only', $compactFonts['reviewPolicy']);
        $t->same(false, $compactItems['Fonts/ReviewSans.woff2']['canExposeBytes']);
        $t->same(false, $compactItems['Fonts/ReviewSans.woff2']['canExposeAsDocumentMedia']);
        $t->same('font-package-bytes-blocked', $compactItems['Fonts/ReviewSans.woff2']['byteExposurePolicy']);
        $t->same(strlen($reviewFont), $compactItems['Fonts/ReviewSans.woff2']['byteLength']);
        $t->same(sprintf('%08x', crc32($reviewFont)), $compactItems['Fonts/ReviewSans.woff2']['crc32']);
        $t->same(false, $compactItems['Assets/source.woff']['canExposeBytes']);
        $t->same('font-package-bytes-blocked', $compactItems['Assets/source.woff']['byteExposurePolicy']);
        $t->same(false, $compactItems['Fonts/missing.ttf']['canExposeBytes']);
        $t->same('font-package-bytes-blocked', $compactItems['Fonts/missing.ttf']['byteExposurePolicy']);
        $t->same(false, $compactItems['Fonts/not-font.bin']['canExposeBytes']);
        $t->same('font-package-bytes-blocked', $compactItems['Fonts/not-font.bin']['byteExposurePolicy']);
        $t->same(false, $compactItems['Fonts/locked.otf']['canExposeBytes']);
        $t->same('encrypted-resource-bytes-blocked', $compactItems['Fonts/locked.otf']['byteExposurePolicy']);
        $t->same(false, $compactItems['Fonts/orphan.ttf']['canExposeBytes']);
        $t->same('font-package-bytes-blocked', $compactItems['Fonts/orphan.ttf']['byteExposurePolicy']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(6, $compactMediaResources['manifestDeclaredCount']);
        $t->same(1, $compactMediaResources['mediaResourceCount']);
        $t->same(1, $compactMediaResources['mediaResourceExistingCount']);
        $t->same(0, $compactMediaResources['mediaResourceMissingCount']);
        $t->same(1, $compactMediaResources['mediaResourceCanExposeCount']);
        $t->same(5, $compactMediaResources['existingCount']);
        $t->same(1, $compactMediaResources['missingCount']);
        $t->same($readerMediaResources['familyCounts'], $compactMediaResources['familyCounts']);
        $t->same($readerMediaResources['mediaTypeBaseCounts'], $compactMediaResources['mediaTypeBaseCounts']);
        $t->same(5, $compactMediaResources['packageRolePrecedenceCount']);
        $t->same($readerMediaResources['issueCodeCounts'], $compactMediaResources['issueCodeCounts']);
        $t->same(array_column($readerMediaResources['packageRolePrecedenceItems'], 'part'), array_column($compactMediaResources['packageRolePrecedenceItems'], 'part'));
        $t->same(true, $compactMediaItems['Pictures/hero.png']['mediaResource']);
        $t->same([], $compactMediaItems['Pictures/hero.png']['packageRolePrecedence'] ?? []);
        $t->same(false, $compactMediaItems['Fonts/ReviewSans.woff2']['mediaResource']);
        $t->same(['font-package'], $compactMediaItems['Fonts/ReviewSans.woff2']['packageRolePrecedence']);
        $t->same('font-package-bytes-blocked', $compactMediaItems['Fonts/ReviewSans.woff2']['byteExposurePolicy']);
        $t->same(false, $compactMediaItems['Assets/source.woff']['mediaResource']);
        $t->same(['font-package'], $compactMediaItems['Assets/source.woff']['packageRolePrecedence']);
        $t->same(false, $compactMediaItems['Fonts/missing.ttf']['exists']);
        $t->same(['odf-media-resource-missing-package-part', 'odf-media-resource-package-role-precedence'], $compactMediaItems['Fonts/missing.ttf']['issues']);
        $t->same(false, $compactMediaItems['Fonts/not-font.bin']['mediaResource']);
        $t->same(['font-package'], $compactMediaItems['Fonts/not-font.bin']['packageRolePrecedence']);
        $t->same(false, $compactMediaItems['Fonts/locked.otf']['canExposeBytes']);
        $t->same('encrypted-resource-bytes-blocked', $compactMediaItems['Fonts/locked.otf']['byteExposurePolicy']);
        $t->same(true, $reviewByPath['Fonts/ReviewSans.woff2']['fontPackagePart']);
        $t->same(false, $reviewByPath['Fonts/ReviewSans.woff2']['canExposeBytes']);
        $t->same(null, $reviewByPath['Fonts/ReviewSans.woff2']['byteLength']);
        $t->same('font-package-bytes-blocked', $reviewByPath['Fonts/ReviewSans.woff2']['byteExposurePolicy']);
        $t->same(5, $compactSummary['manifestReview']['fontPackagePartCount']);
        $t->same(5, $inventory['fontPackagePartCount']);
        $t->same(5, $inventory['roleCounts']['font-package']);
        $t->same(false, $inventory['parts']['Fonts/ReviewSans.woff2']['canExposeBytes']);
        $t->same('font-package-bytes-blocked', $inventory['parts']['Fonts/ReviewSans.woff2']['byteExposurePolicy']);
    },
];
