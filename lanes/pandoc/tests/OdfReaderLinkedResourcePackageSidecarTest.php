<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$linkManifestXml = '<links><link href="https://example.test/source.png"/></links>';
$previewBytes = 'LINKED-PREVIEW-PNG';
$encryptedBytes = 'ENCRYPTED-LINKED-CACHE';
$orphanBytes = 'ORPHAN-LINKED-CACHE';

$linkManifestSize = strlen($linkManifestXml);
$previewSize = strlen($previewBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Links/manifest.xml" manifest:media-type="text/xml" manifest:size="{$linkManifestSize}"/>
  <manifest:file-entry manifest:full-path="Links/cache/preview.png" manifest:media-type="image/png" manifest:size="{$previewSize}"/>
  <manifest:file-entry manifest:full-path="Links/cache/missing.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Links/cache/encrypted.bin" manifest:media-type="application/octet-stream" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="linked-cache-checksum"/>
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
      <text:p>Linked resource cache package.</text:p>
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
    <dc:title>Linked Resource Cache Packet</dc:title>
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
    ['name' => 'Links/manifest.xml', 'data' => $linkManifestXml, 'compressionMethod' => 0],
    ['name' => 'Links/cache/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
    ['name' => 'Links/cache/encrypted.bin', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Links/cache/orphan.dat', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt linked resource cache package');

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
    'reports ODT linked resource cache sidecars as metadata-only package review data' => static function (TestRunner $t) use (
        $buildPackage,
        $linkManifestXml,
        $previewBytes,
        $encryptedBytes,
        $orphanBytes,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $linkedResources = $result['packageLinkedResources'];
        $items = $indexBy($linkedResources['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $provenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($linkedResources, $result['document']->attr('packageLinkedResources'));
        $t->same($linkedResources, $result['metadata']['odfPackageLinkedResources']);
        $t->same($linkedResources, $result['importReport']['packageLinkedResources']);
        $t->same(5, $linkedResources['count']);
        $t->same(3, $linkedResources['readableCount']);
        $t->same(4, $linkedResources['declaredCount']);
        $t->same(1, $linkedResources['undeclaredCount']);
        $t->same(1, $linkedResources['missingCount']);
        $t->same(1, $linkedResources['encryptedCount']);
        $t->same(0, $linkedResources['missingMediaTypeCount']);
        $t->same(0, $linkedResources['invalidMediaTypeCount']);
        $t->same(3, $linkedResources['issueCount']);
        $t->same([
            'odf-linked-resource-package-encrypted-part',
            'odf-linked-resource-package-missing-part',
            'odf-linked-resource-package-undeclared-part',
        ], $linkedResources['issueCodes']);
        $t->same('linked-resource-package-bytes-blocked', $linkedResources['byteExposurePolicy']);
        $t->same('linked-resource-package-metadata-only', $linkedResources['reviewPolicy']);

        $linkManifest = $items['Links/manifest.xml'];
        $t->same('linked-resource-manifest', $linkManifest['kind']);
        $t->same(strlen($linkManifestXml), $linkManifest['byteLength']);
        $t->same(sprintf('%08x', crc32($linkManifestXml)), $linkManifest['crc32']);
        $t->same(false, $linkManifest['canExposeBytes']);
        $t->same(false, $linkManifest['canExposeAsDocumentMedia']);
        $t->same('linked-resource-package-bytes-blocked', $linkManifest['byteExposurePolicy']);
        $t->same([], $linkManifest['issues']);

        $preview = $items['Links/cache/preview.png'];
        $t->same('linked-resource-media-cache', $preview['kind']);
        $t->same('image/png', $preview['mediaTypeBase']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);

        $missing = $items['Links/cache/missing.png'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-linked-resource-package-missing-part'], $missing['issues']);
        $t->same('linked-resource-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $items['Links/cache/encrypted.bin'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedBytes), $encrypted['storedByteLength']);
        $t->same(['odf-linked-resource-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $items['Links/cache/orphan.dat'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('linked-resource-binary-cache', $orphan['kind']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-linked-resource-package-undeclared-part'], $orphan['issues']);
        $t->same('linked-resource-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $manifestPreview = $manifestByPart['Links/cache/preview.png'];
        $t->same(true, $manifestPreview['linkedResourcePackagePart']);
        $t->same(false, $manifestPreview['canExposeBytes']);
        $t->same(null, $manifestPreview['byteLength']);
        $t->same(strlen($previewBytes), $manifestPreview['storedByteLength']);
        $t->same(null, $manifestPreview['byteSha256']);
        $t->same('linked-resource-package-bytes-blocked', $manifestPreview['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(4, $provenance['linkedResourcePackagePartCount']);
        $t->same(4, $provenance['roleCounts']['linked-resource-package']);
        $t->same(1, $provenance['undeclaredRoleCounts']['linked-resource-package']);
        $t->same(['linked-resource-package', 'manifest-declared'], $provenance['parts']['Links/cache/preview.png']['roles']);
        $t->same(['linked-resource-package', 'undeclared-package-entry'], $provenance['parts']['Links/cache/orphan.dat']['roles']);
        $t->same(true, $provenance['parts']['Links/cache/preview.png']['linkedResourcePackagePart']);
        $t->same(true, $provenance['packageIdentity']['manifestEntries'][6]['linkedResourcePackagePart']);
        $t->same(true, $provenance['packageIdentity']['packageEntries'][7]['linkedResourcePackagePart']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactLinkedResources = $compactSummary['packageLinkedResources'];
        $compactItems = $indexBy($compactLinkedResources['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(5, $compactLinkedResources['count']);
        $t->same(3, $compactLinkedResources['readableCount']);
        $t->same(4, $compactLinkedResources['declaredCount']);
        $t->same(1, $compactLinkedResources['undeclaredCount']);
        $t->same(1, $compactLinkedResources['missingCount']);
        $t->same(1, $compactLinkedResources['encryptedCount']);
        $t->same($linkedResources['issueCodes'], $compactLinkedResources['issueCodes']);
        $t->same('linked-resource-package-bytes-blocked', $compactLinkedResources['byteExposurePolicy']);
        $t->same('linked-resource-package-metadata-only', $compactLinkedResources['reviewPolicy']);
        $t->same('linked-resource-manifest', $compactItems['Links/manifest.xml']['kind']);
        $t->same('linked-resource-media-cache', $compactItems['Links/cache/preview.png']['kind']);
        $t->same(false, $compactItems['Links/cache/preview.png']['canExposeBytes']);
        $t->same(false, $compactItems['Links/cache/preview.png']['canExposeAsDocumentMedia']);
        $t->same(strlen($previewBytes), $compactItems['Links/cache/preview.png']['byteLength']);
        $t->same(sprintf('%08x', crc32($previewBytes)), $compactItems['Links/cache/preview.png']['crc32']);
        $t->same(['odf-linked-resource-package-missing-part'], $compactItems['Links/cache/missing.png']['issues']);
        $t->same(['odf-linked-resource-package-encrypted-part'], $compactItems['Links/cache/encrypted.bin']['issues']);
        $t->same(['odf-linked-resource-package-undeclared-part'], $compactItems['Links/cache/orphan.dat']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(4, $compactSummary['manifestReview']['linkedResourcePackagePartCount']);
        $t->same(true, $reviewByPath['Links/cache/preview.png']['linkedResourcePackagePart']);
        $t->same(false, $reviewByPath['Links/cache/preview.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Links/cache/preview.png']['byteLength']);
        $t->same(strlen($previewBytes), $reviewByPath['Links/cache/preview.png']['storedByteLength']);
        $t->same('linked-resource-package-bytes-blocked', $reviewByPath['Links/cache/preview.png']['byteExposurePolicy']);
        $t->same('linked-resource', $reviewByPath['Links/cache/preview.png']['manifestMediaFamily']);
        $t->same(4, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['linked-resource']);
        $t->same(4, $inventory['linkedResourcePackagePartCount']);
        $t->same(4, $inventory['roleCounts']['linked-resource-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['linked-resource-package']);
        $t->same(['linked-resource-package', 'manifest-declared'], $inventory['parts']['Links/cache/preview.png']['roles']);
        $t->same(['linked-resource-package', 'undeclared-package-entry'], $inventory['parts']['Links/cache/orphan.dat']['roles']);
        $t->same(true, $inventory['parts']['Links/cache/preview.png']['linkedResourcePackagePart']);
        $t->same(false, $inventory['parts']['Links/cache/preview.png']['canExposeBytes']);
    },
];
