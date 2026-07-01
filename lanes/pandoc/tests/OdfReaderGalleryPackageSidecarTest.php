<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$galleryIndexXml = '<gallery><theme name="Review"/></gallery>';
$previewBytes = 'GALLERY-PREVIEW-PNG';
$encryptedBytes = 'ENCRYPTED-GALLERY-BYTES';
$orphanBytes = 'ORPHAN-GALLERY-BYTES';

$galleryIndexSize = strlen($galleryIndexXml);
$previewSize = strlen($previewBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Gallery/Theme/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Gallery/Theme/sg100.thm" manifest:media-type="text/xml" manifest:size="{$galleryIndexSize}"/>
  <manifest:file-entry manifest:full-path="Gallery/Theme/preview.png" manifest:media-type="image/png" manifest:size="{$previewSize}bytes"/>
  <manifest:file-entry manifest:full-path="Gallery/Theme/missing.sdg" manifest:media-type="application/octet-stream" manifest:size="19"/>
  <manifest:file-entry manifest:full-path="Gallery/Theme/encrypted.sdv" manifest:media-type="application/octet-stream" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="gallery-checksum"/>
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
      <text:p>Gallery package sidecars.</text:p>
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
    <dc:title>Gallery Sidecar Packet</dc:title>
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
    ['name' => 'Gallery/Theme/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Gallery/Theme/sg100.thm', 'data' => $galleryIndexXml, 'compressionMethod' => 0],
    ['name' => 'Gallery/Theme/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
    ['name' => 'Gallery/Theme/encrypted.sdv', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Gallery/Theme/orphan.svm', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt gallery package sidecars');

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
    'reports ODT gallery package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $galleryIndexXml,
        $previewSize,
        $previewBytes,
        $encryptedBytes,
        $orphanBytes,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerGalleries = $result['packageGalleries'];
        $readerItems = $indexBy($readerGalleries['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($readerGalleries, $result['document']->attr('packageGalleries'));
        $t->same($readerGalleries, $result['metadata']['odfPackageGalleries']);
        $t->same($readerGalleries, $result['importReport']['packageGalleries']);
        $t->same(6, $readerGalleries['count']);
        $t->same(3, $readerGalleries['readableCount']);
        $t->same(5, $readerGalleries['declaredCount']);
        $t->same(1, $readerGalleries['undeclaredCount']);
        $t->same(1, $readerGalleries['missingCount']);
        $t->same(1, $readerGalleries['directoryCount']);
        $t->same(1, $readerGalleries['encryptedCount']);
        $t->same(0, $readerGalleries['missingMediaTypeCount']);
        $t->same(0, $readerGalleries['invalidMediaTypeCount']);
        $t->same(1, $readerGalleries['invalidDeclaredSizeCount']);
        $t->same(4, $readerGalleries['issueCount']);
        $t->same([
            'odf-gallery-package-encrypted-part',
            'odf-gallery-package-invalid-declared-size',
            'odf-gallery-package-missing-part',
            'odf-gallery-package-undeclared-part',
        ], $readerGalleries['issueCodes']);
        $t->same([
            'gallery-binary-resource' => 3,
            'gallery-directory' => 1,
            'gallery-index' => 1,
            'gallery-media-resource' => 1,
        ], $readerGalleries['kindCounts']);
        $t->same(['theme' => 6], $readerGalleries['groupCounts']);
        $t->same('gallery-package-bytes-blocked', $readerGalleries['byteExposurePolicy']);
        $t->same('gallery-package-metadata-only', $readerGalleries['reviewPolicy']);

        $directory = $readerItems['Gallery/Theme/'];
        $t->same('gallery-directory', $directory['kind']);
        $t->same('theme', $directory['group']);
        $t->same(true, $directory['isDirectory']);
        $t->same(null, $directory['byteLength']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);

        $index = $readerItems['Gallery/Theme/sg100.thm'];
        $t->same('gallery-index', $index['kind']);
        $t->same(strlen($galleryIndexXml), $index['byteLength']);
        $t->same(sprintf('%08x', crc32($galleryIndexXml)), $index['crc32']);
        $t->same(false, $index['canExposeBytes']);
        $t->same(false, $index['canExposeAsDocumentMedia']);
        $t->same('gallery-package-bytes-blocked', $index['byteExposurePolicy']);
        $t->same([], $index['issues']);

        $preview = $readerItems['Gallery/Theme/preview.png'];
        $t->same('gallery-media-resource', $preview['kind']);
        $t->same('image/png', $preview['mediaTypeBase']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(null, $preview['declaredSize']);
        $t->same($previewSize . 'bytes', $preview['declaredSizeRaw']);
        $t->same(false, $preview['declaredSizeValid']);
        $t->same(true, $preview['declaredSizeInvalid']);
        $t->same(['odf-gallery-package-invalid-declared-size'], $preview['issues']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);

        $missing = $readerItems['Gallery/Theme/missing.sdg'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-gallery-package-missing-part'], $missing['issues']);
        $t->same('gallery-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $readerItems['Gallery/Theme/encrypted.sdv'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedBytes), $encrypted['storedByteLength']);
        $t->same(['odf-gallery-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $readerItems['Gallery/Theme/orphan.svm'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('gallery-binary-resource', $orphan['kind']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-gallery-package-undeclared-part'], $orphan['issues']);
        $t->same('gallery-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $manifestPreview = $manifestByPart['Gallery/Theme/preview.png'];
        $t->same(true, $manifestPreview['galleryPackagePart']);
        $t->same(false, $manifestPreview['canExposeBytes']);
        $t->same(null, $manifestPreview['byteLength']);
        $t->same(strlen($previewBytes), $manifestPreview['storedByteLength']);
        $t->same(null, $manifestPreview['byteSha256']);
        $t->same('gallery-package-bytes-blocked', $manifestPreview['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(5, $readerProvenance['galleryPackagePartCount']);
        $t->same(5, $readerProvenance['roleCounts']['gallery-package']);
        $t->same(1, $readerProvenance['undeclaredRoleCounts']['gallery-package']);
        $t->same(['gallery-package', 'manifest-declared'], $readerProvenance['parts']['Gallery/Theme/preview.png']['roles']);
        $t->same(['gallery-package', 'undeclared-package-entry'], $readerProvenance['parts']['Gallery/Theme/orphan.svm']['roles']);
        $t->same(true, $readerProvenance['parts']['Gallery/Theme/preview.png']['galleryPackagePart']);

        $readerIdentityManifest = $indexBy($readerProvenance['packageIdentity']['manifestEntries'], 'part');
        $readerIdentityPackage = $indexBy($readerProvenance['packageIdentity']['packageEntries'], 'part');
        $t->same(true, $readerIdentityManifest['Gallery/Theme/preview.png']['galleryPackagePart']);
        $t->same(true, $readerIdentityPackage['Gallery/Theme/preview.png']['galleryPackagePart']);
        $t->same(5, $readerProvenance['packageIdentity']['galleryPackagePartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Gallery package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $galleryIndexXml));
        $t->same(false, str_contains($blocks, $previewBytes));
        $t->same(false, str_contains($blocks, $orphanBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactGalleries = $compactSummary['packageGalleries'];
        $compactItems = $indexBy($compactGalleries['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(6, $compactGalleries['count']);
        $t->same(3, $compactGalleries['readableCount']);
        $t->same(5, $compactGalleries['declaredCount']);
        $t->same(1, $compactGalleries['undeclaredCount']);
        $t->same(1, $compactGalleries['missingCount']);
        $t->same(1, $compactGalleries['directoryCount']);
        $t->same(1, $compactGalleries['encryptedCount']);
        $t->same(1, $compactGalleries['invalidDeclaredSizeCount']);
        $t->same(4, $compactGalleries['issueCount']);
        $t->same($readerGalleries['issueCodes'], $compactGalleries['issueCodes']);
        $t->same('gallery-package-bytes-blocked', $compactGalleries['byteExposurePolicy']);
        $t->same('gallery-package-metadata-only', $compactGalleries['reviewPolicy']);
        $t->same('gallery-index', $compactItems['Gallery/Theme/sg100.thm']['kind']);
        $t->same('gallery-media-resource', $compactItems['Gallery/Theme/preview.png']['kind']);
        $t->same(false, $compactItems['Gallery/Theme/preview.png']['canExposeBytes']);
        $t->same(false, $compactItems['Gallery/Theme/preview.png']['canExposeAsDocumentMedia']);
        $t->same(strlen($previewBytes), $compactItems['Gallery/Theme/preview.png']['byteLength']);
        $t->same(sprintf('%08x', crc32($previewBytes)), $compactItems['Gallery/Theme/preview.png']['crc32']);
        $t->same(null, $compactItems['Gallery/Theme/preview.png']['declaredSize']);
        $t->same($previewSize . 'bytes', $compactItems['Gallery/Theme/preview.png']['declaredSizeRaw']);
        $t->same(false, $compactItems['Gallery/Theme/preview.png']['declaredSizeValid']);
        $t->same(true, $compactItems['Gallery/Theme/preview.png']['declaredSizeInvalid']);
        $t->same(['odf-gallery-package-invalid-declared-size'], $compactItems['Gallery/Theme/preview.png']['issues']);
        $t->same(['odf-gallery-package-missing-part'], $compactItems['Gallery/Theme/missing.sdg']['issues']);
        $t->same(['odf-gallery-package-encrypted-part'], $compactItems['Gallery/Theme/encrypted.sdv']['issues']);
        $t->same(['odf-gallery-package-undeclared-part'], $compactItems['Gallery/Theme/orphan.svm']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(5, $compactSummary['manifestReview']['galleryPackagePartCount']);
        $t->same(true, $reviewByPath['Gallery/Theme/preview.png']['galleryPackagePart']);
        $t->same(false, $reviewByPath['Gallery/Theme/preview.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Gallery/Theme/preview.png']['byteLength']);
        $t->same(strlen($previewBytes), $reviewByPath['Gallery/Theme/preview.png']['storedByteLength']);
        $t->same('gallery-package-bytes-blocked', $reviewByPath['Gallery/Theme/preview.png']['byteExposurePolicy']);
        $t->same('gallery', $reviewByPath['Gallery/Theme/preview.png']['manifestMediaFamily']);
        $t->same(4, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['gallery']);
        $t->same(5, $inventory['galleryPackagePartCount']);
        $t->same(5, $inventory['roleCounts']['gallery-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['gallery-package']);
        $t->same(['gallery-package', 'manifest-declared'], $inventory['parts']['Gallery/Theme/preview.png']['roles']);
        $t->same(['gallery-package', 'undeclared-package-entry'], $inventory['parts']['Gallery/Theme/orphan.svm']['roles']);
        $t->same(true, $inventory['parts']['Gallery/Theme/preview.png']['galleryPackagePart']);
        $t->same(false, $inventory['parts']['Gallery/Theme/preview.png']['canExposeBytes']);

        $compactIdentityManifest = $indexBy($compactSummary['packageIdentity']['manifestEntries'], 'path');
        $compactIdentityPackage = $indexBy($compactSummary['packageIdentity']['packageEntries'], 'path');
        $t->same(true, $compactIdentityManifest['Gallery/Theme/preview.png']['galleryPackagePart']);
        $t->same(true, $compactIdentityPackage['Gallery/Theme/preview.png']['galleryPackagePart']);
        $t->same(5, $compactSummary['packageIdentity']['galleryPackagePartCount']);
    },
];
