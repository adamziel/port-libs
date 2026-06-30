<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$reviewXml = '<review-state status="ready"/>';
$previewBytes = 'META-INF-PREVIEW';
$encryptedBytes = 'ENCRYPTED-META-INF';
$orphanBytes = '<orphan-review/>';
$reviewSize = strlen($reviewXml);
$previewSize = strlen($previewBytes);
$encryptedSize = strlen($encryptedBytes);
$invalidReviewSize = $reviewSize . 'bytes';

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="META-INF/review-state.xml" manifest:media-type="text/xml" manifest:size="{$invalidReviewSize}"/>
  <manifest:file-entry manifest:full-path="META-INF/missing-review.xml" manifest:media-type="application/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/encrypted-review.xml" manifest:media-type="text/xml" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="meta-inf-checksum"/>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="META-INF/preview.png" manifest:media-type="image/png" manifest:size="{$previewSize}"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Meta-INF sidecar package.</text:p>
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
    <dc:title>Meta-INF Sidecars</dc:title>
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
    ['name' => 'META-INF/review-state.xml', 'data' => $reviewXml, 'compressionMethod' => 0],
    ['name' => 'META-INF/encrypted-review.xml', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'META-INF/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
    ['name' => 'META-INF/orphan-review.xml', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt meta-inf sidecars');

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
    'reports ODT META-INF sidecars as metadata-only package review data' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $reviewXml,
        $previewBytes,
        $encryptedBytes,
        $orphanBytes
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $sidecars = $result['packageMetaInfSidecars'];
        $items = $indexBy($sidecars['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $provenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($sidecars, $result['document']->attr('packageMetaInfSidecars'));
        $t->same($sidecars, $result['metadata']['odfPackageMetaInfSidecars']);
        $t->same($sidecars, $result['importReport']['packageMetaInfSidecars']);
        $t->same(5, $sidecars['count']);
        $t->same(3, $sidecars['readableCount']);
        $t->same(4, $sidecars['declaredCount']);
        $t->same(1, $sidecars['undeclaredCount']);
        $t->same(1, $sidecars['missingCount']);
        $t->same(1, $sidecars['encryptedCount']);
        $t->same(0, $sidecars['invalidMediaTypeCount']);
        $t->same(4, $sidecars['issueCount']);
        $t->same([
            'odf-meta-inf-sidecar-encrypted-package-part',
            'odf-meta-inf-sidecar-invalid-declared-size',
            'odf-meta-inf-sidecar-missing-package-part',
            'odf-meta-inf-sidecar-undeclared-package-part',
        ], $sidecars['issueCodes']);
        $t->same(1, $sidecars['invalidDeclaredSizeCount']);
        $t->same('meta-inf-sidecar-package-bytes-blocked', $sidecars['byteExposurePolicy']);
        $t->same('meta-inf-sidecar-metadata-only', $sidecars['reviewPolicy']);

        $review = $items['META-INF/review-state.xml'];
        $t->same('text/xml', $review['mediaType']);
        $t->same(true, $review['declared']);
        $t->same(true, $review['valid']);
        $t->same(strlen($reviewXml), $review['byteLength']);
        $t->same(null, $review['declaredSize']);
        $t->same(strlen($reviewXml) . 'bytes', $review['declaredSizeRaw']);
        $t->same(false, $review['declaredSizeValid']);
        $t->same(true, $review['declaredSizeInvalid']);
        $t->same(false, $review['declaredSizeMismatch']);
        $t->same(sprintf('%08x', crc32($reviewXml)), $review['crc32']);
        $t->same(false, $review['canExposeAsDocumentMedia']);
        $t->same('meta-inf-sidecar-package-bytes-blocked', $review['byteExposurePolicy']);
        $t->same(['odf-meta-inf-sidecar-invalid-declared-size'], $review['issues']);

        $missing = $items['META-INF/missing-review.xml'];
        $t->same(false, $missing['exists']);
        $t->same(['odf-meta-inf-sidecar-missing-package-part'], $missing['issues']);

        $encrypted = $items['META-INF/encrypted-review.xml'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedBytes), $encrypted['storedByteLength']);
        $t->same(['odf-meta-inf-sidecar-encrypted-package-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $preview = $items['META-INF/preview.png'];
        $t->same('image/png', $preview['mediaType']);
        $t->same(true, $preview['valid']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);

        $orphan = $items['META-INF/orphan-review.xml'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-meta-inf-sidecar-undeclared-package-part'], $orphan['issues']);

        $manifestReview = $manifestByPart['META-INF/review-state.xml'];
        $t->same(true, $manifestReview['metaInfSidecarPackagePart']);
        $t->same(false, $manifestReview['canExposeBytes']);
        $t->same(null, $manifestReview['byteLength']);
        $t->same(strlen($reviewXml), $manifestReview['storedByteLength']);
        $t->same(null, $manifestReview['byteSha256']);
        $t->same('meta-inf-sidecar-package-bytes-blocked', $manifestReview['byteExposurePolicy']);

        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(4, $provenance['metaInfSidecarPartCount']);
        $t->same(4, $provenance['roleCounts']['meta-inf-sidecar']);
        $t->same(1, $provenance['undeclaredRoleCounts']['meta-inf-sidecar']);
        $t->same(['meta-inf-sidecar', 'manifest-declared'], $provenance['parts']['META-INF/review-state.xml']['roles']);
        $t->same(['meta-inf-sidecar', 'undeclared-package-entry'], $provenance['parts']['META-INF/orphan-review.xml']['roles']);
        $t->same(true, $provenance['parts']['META-INF/preview.png']['metaInfSidecarPackagePart']);
        $t->same(false, in_array('media-resource', $provenance['parts']['META-INF/preview.png']['roles'], true));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactSidecars = $compactSummary['packageMetaInfSidecars'];
        $compactItems = $indexBy($compactSidecars['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(5, $compactSidecars['count']);
        $t->same(3, $compactSidecars['readableCount']);
        $t->same(4, $compactSidecars['declaredCount']);
        $t->same(1, $compactSidecars['undeclaredCount']);
        $t->same(1, $compactSidecars['missingCount']);
        $t->same(1, $compactSidecars['encryptedCount']);
        $t->same($sidecars['issueCodes'], $compactSidecars['issueCodes']);
        $t->same(1, $compactSidecars['invalidDeclaredSizeCount']);
        $t->same('meta-inf-sidecar-package-bytes-blocked', $compactSidecars['byteExposurePolicy']);
        $t->same('meta-inf-sidecar-metadata-only', $compactSidecars['reviewPolicy']);
        $t->same(strlen($reviewXml), $compactItems['META-INF/review-state.xml']['byteLength']);
        $t->same(null, $compactItems['META-INF/review-state.xml']['declaredSize']);
        $t->same(strlen($reviewXml) . 'bytes', $compactItems['META-INF/review-state.xml']['declaredSizeRaw']);
        $t->same(false, $compactItems['META-INF/review-state.xml']['declaredSizeValid']);
        $t->same(true, $compactItems['META-INF/review-state.xml']['declaredSizeInvalid']);
        $t->same(false, $compactItems['META-INF/review-state.xml']['declaredSizeMismatch']);
        $t->same(['odf-meta-inf-sidecar-invalid-declared-size'], $compactItems['META-INF/review-state.xml']['issues']);
        $t->same(strlen($previewBytes), $compactItems['META-INF/preview.png']['byteLength']);
        $t->same(['odf-meta-inf-sidecar-missing-package-part'], $compactItems['META-INF/missing-review.xml']['issues']);
        $t->same(['odf-meta-inf-sidecar-encrypted-package-part'], $compactItems['META-INF/encrypted-review.xml']['issues']);
        $t->same(['odf-meta-inf-sidecar-undeclared-package-part'], $compactItems['META-INF/orphan-review.xml']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(4, $compactSummary['manifestReview']['metaInfSidecarPartCount']);
        $t->same(true, $reviewByPath['META-INF/review-state.xml']['metaInfSidecarPackagePart']);
        $t->same(false, $reviewByPath['META-INF/review-state.xml']['canExposeBytes']);
        $t->same(null, $reviewByPath['META-INF/review-state.xml']['byteLength']);
        $t->same(strlen($reviewXml), $reviewByPath['META-INF/review-state.xml']['storedByteLength']);
        $t->same('meta-inf-sidecar-package-bytes-blocked', $reviewByPath['META-INF/review-state.xml']['byteExposurePolicy']);
        $t->same('meta-inf-sidecar', $reviewByPath['META-INF/review-state.xml']['manifestMediaFamily']);
        $t->same(4, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['meta-inf-sidecar']);
        $t->same(4, $inventory['metaInfSidecarPartCount']);
        $t->same(4, $inventory['roleCounts']['meta-inf-sidecar']);
        $t->same(['meta-inf-sidecar', 'manifest-declared'], $inventory['parts']['META-INF/review-state.xml']['roles']);
        $t->same(['meta-inf-sidecar', 'undeclared-package-entry'], $inventory['parts']['META-INF/orphan-review.xml']['roles']);
        $t->same(true, $inventory['parts']['META-INF/preview.png']['metaInfSidecarPackagePart']);
        $t->same(false, $inventory['parts']['META-INF/preview.png']['canExposeBytes']);
    },
];
