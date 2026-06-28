<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$versionListXml = '<version-list><version id="v1"/></version-list>';
$versionContentXml = '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>';
$versionPreviewBytes = 'VERSION-PREVIEW-PNG';
$versionDocumentBytes = 'VERSION-ODT-BYTES';
$encryptedVersionBytes = 'ENCRYPTED-VERSION-CONTENT';
$orphanVersionXml = '<orphan-version/>';

$versionListSize = strlen($versionListXml);
$versionContentSize = strlen($versionContentXml);
$versionPreviewSize = strlen($versionPreviewBytes);
$versionDocumentSize = strlen($versionDocumentBytes);
$encryptedVersionSize = strlen($encryptedVersionBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Versions/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Versions/VersionList.xml" manifest:media-type="text/xml" manifest:size="{$versionListSize}"/>
  <manifest:file-entry manifest:full-path="Versions/v1/content.xml" manifest:media-type="text/xml" manifest:size="{$versionContentSize}"/>
  <manifest:file-entry manifest:full-path="Versions/v1/preview.png" manifest:media-type="image/png" manifest:size="{$versionPreviewSize}"/>
  <manifest:file-entry manifest:full-path="Versions/v1/document.odt" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:size="{$versionDocumentSize}"/>
  <manifest:file-entry manifest:full-path="Versions/missing/content.xml" manifest:media-type="text/xml" manifest:size="19"/>
  <manifest:file-entry manifest:full-path="Versions/v2/content.xml" manifest:media-type="text/xml" manifest:size="{$encryptedVersionSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="version-checksum"/>
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
      <text:p>Version history package.</text:p>
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
    <dc:title>Version History Packet</dc:title>
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
    ['name' => 'Versions/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Versions/VersionList.xml', 'data' => $versionListXml, 'compressionMethod' => 0],
    ['name' => 'Versions/v1/content.xml', 'data' => $versionContentXml, 'compressionMethod' => 0],
    ['name' => 'Versions/v1/preview.png', 'data' => $versionPreviewBytes, 'compressionMethod' => 0],
    ['name' => 'Versions/v1/document.odt', 'data' => $versionDocumentBytes, 'compressionMethod' => 0],
    ['name' => 'Versions/v2/content.xml', 'data' => $encryptedVersionBytes, 'compressionMethod' => 0],
    ['name' => 'Versions/orphan/content.xml', 'data' => $orphanVersionXml, 'compressionMethod' => 0],
], 'odt version history package sidecars');

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
    'reports ODT version history package parts as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $versionListXml,
        $versionContentXml,
        $versionPreviewBytes,
        $versionDocumentBytes,
        $orphanVersionXml,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerVersions = $result['packageVersions'];
        $readerItems = $indexBy($readerVersions['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($readerVersions, $result['document']->attr('packageVersions'));
        $t->same($readerVersions, $result['metadata']['odfPackageVersions']);
        $t->same($readerVersions, $result['importReport']['packageVersions']);
        $t->same(8, $readerVersions['count']);
        $t->same(5, $readerVersions['readableCount']);
        $t->same(7, $readerVersions['declaredCount']);
        $t->same(1, $readerVersions['undeclaredCount']);
        $t->same(1, $readerVersions['missingCount']);
        $t->same(1, $readerVersions['directoryCount']);
        $t->same(1, $readerVersions['encryptedCount']);
        $t->same(0, $readerVersions['missingMediaTypeCount']);
        $t->same(0, $readerVersions['invalidMediaTypeCount']);
        $t->same(3, $readerVersions['issueCount']);
        $t->same([
            'odf-version-package-encrypted-part',
            'odf-version-package-missing-part',
            'odf-version-package-undeclared-part',
        ], $readerVersions['issueCodes']);
        $t->same('version-package-bytes-blocked', $readerVersions['byteExposurePolicy']);
        $t->same('version-package-metadata-only', $readerVersions['reviewPolicy']);

        $versionList = $readerItems['Versions/VersionList.xml'];
        $t->same('version-list', $versionList['kind']);
        $t->same(true, $versionList['declared']);
        $t->same(true, $versionList['valid']);
        $t->same(strlen($versionListXml), $versionList['byteLength']);
        $t->same(sprintf('%08x', crc32($versionListXml)), $versionList['crc32']);
        $t->same(false, $versionList['canExposeBytes']);
        $t->same(false, $versionList['canExposeAsDocumentMedia']);
        $t->same('version-package-bytes-blocked', $versionList['byteExposurePolicy']);
        $t->same([], $versionList['issues']);

        $content = $readerItems['Versions/v1/content.xml'];
        $t->same('version-xml', $content['kind']);
        $t->same('v1', $content['versionId']);
        $t->same(strlen($versionContentXml), $content['storedByteLength']);
        $t->same('version-package-bytes-blocked', $content['byteExposurePolicy']);

        $preview = $readerItems['Versions/v1/preview.png'];
        $t->same('version-media-resource', $preview['kind']);
        $t->same('image/png', $preview['mediaTypeBase']);
        $t->same(strlen($versionPreviewBytes), $preview['byteLength']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);

        $document = $readerItems['Versions/v1/document.odt'];
        $t->same('version-document-package', $document['kind']);
        $t->same(OdfReader::MIMETYPE, $document['mediaTypeBase']);
        $t->same(strlen($versionDocumentBytes), $document['byteLength']);

        $missing = $readerItems['Versions/missing/content.xml'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-version-package-missing-part'], $missing['issues']);
        $t->same('version-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $readerItems['Versions/v2/content.xml'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-version-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $undeclared = $readerItems['Versions/orphan/content.xml'];
        $t->same(false, $undeclared['declared']);
        $t->same(true, $undeclared['undeclared']);
        $t->same('orphan', $undeclared['versionId']);
        $t->same(strlen($orphanVersionXml), $undeclared['byteLength']);
        $t->same(['odf-version-package-undeclared-part'], $undeclared['issues']);

        $manifestPreview = $manifestByPart['Versions/v1/preview.png'];
        $t->same(true, $manifestPreview['versionPackagePart']);
        $t->same(false, $manifestPreview['canExposeBytes']);
        $t->same(null, $manifestPreview['byteLength']);
        $t->same(strlen($versionPreviewBytes), $manifestPreview['storedByteLength']);
        $t->same(null, $manifestPreview['byteSha256']);
        $t->same('version-package-bytes-blocked', $manifestPreview['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(7, $readerProvenance['versionPackagePartCount']);
        $t->same(7, $readerProvenance['roleCounts']['version-package']);
        $t->same(['version-package', 'manifest-declared'], $readerProvenance['parts']['Versions/v1/preview.png']['roles']);
        $t->same(['version-package', 'undeclared-package-entry'], $readerProvenance['parts']['Versions/orphan/content.xml']['roles']);
        $t->same(true, $readerProvenance['parts']['Versions/v1/preview.png']['versionPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['manifestEntries'][8]['versionPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['packageEntries'][9]['versionPackagePart']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactVersions = $compactSummary['packageVersions'];
        $compactItems = $indexBy($compactVersions['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $manifestOrderByPath = $indexBy($compactSummary['manifestReview']['manifestFileEntryOrder'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(8, $compactVersions['count']);
        $t->same(5, $compactVersions['readableCount']);
        $t->same(7, $compactVersions['declaredCount']);
        $t->same(1, $compactVersions['undeclaredCount']);
        $t->same(1, $compactVersions['missingCount']);
        $t->same(1, $compactVersions['directoryCount']);
        $t->same(1, $compactVersions['encryptedCount']);
        $t->same(3, $compactVersions['issueCount']);
        $t->same($readerVersions['issueCodes'], $compactVersions['issueCodes']);
        $t->same('version-package-bytes-blocked', $compactVersions['byteExposurePolicy']);
        $t->same('version-package-metadata-only', $compactVersions['reviewPolicy']);
        $t->same('version-list', $compactItems['Versions/VersionList.xml']['kind']);
        $t->same('version-media-resource', $compactItems['Versions/v1/preview.png']['kind']);
        $t->same('version-document-package', $compactItems['Versions/v1/document.odt']['kind']);
        $t->same(false, $compactItems['Versions/v1/preview.png']['canExposeBytes']);
        $t->same(false, $compactItems['Versions/v1/preview.png']['canExposeAsDocumentMedia']);
        $t->same(strlen($versionPreviewBytes), $compactItems['Versions/v1/preview.png']['byteLength']);
        $t->same(sprintf('%08x', crc32($versionPreviewBytes)), $compactItems['Versions/v1/preview.png']['crc32']);
        $t->same(['odf-version-package-missing-part'], $compactItems['Versions/missing/content.xml']['issues']);
        $t->same(['odf-version-package-encrypted-part'], $compactItems['Versions/v2/content.xml']['issues']);
        $t->same(['odf-version-package-undeclared-part'], $compactItems['Versions/orphan/content.xml']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(7, $compactSummary['manifestReview']['versionPackagePartCount']);
        $t->same(true, $reviewByPath['Versions/v1/preview.png']['versionPackagePart']);
        $t->same(true, $manifestOrderByPath['Versions/v1/preview.png']['versionPackagePart']);
        $t->same(false, $reviewByPath['Versions/v1/preview.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Versions/v1/preview.png']['byteLength']);
        $t->same(strlen($versionPreviewBytes), $reviewByPath['Versions/v1/preview.png']['storedByteLength']);
        $t->same('version-package-bytes-blocked', $reviewByPath['Versions/v1/preview.png']['byteExposurePolicy']);
        $t->same('version-history', $reviewByPath['Versions/v1/preview.png']['manifestMediaFamily']);
        $t->same(6, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['version-history']);
        $t->same(7, $inventory['versionPackagePartCount']);
        $t->same(7, $inventory['roleCounts']['version-package']);
        $t->same(['version-package', 'manifest-declared'], $inventory['parts']['Versions/v1/preview.png']['roles']);
        $t->same(['version-package', 'undeclared-package-entry'], $inventory['parts']['Versions/orphan/content.xml']['roles']);
        $t->same(true, $inventory['parts']['Versions/v1/preview.png']['versionPackagePart']);
        $t->same(false, $inventory['parts']['Versions/v1/preview.png']['canExposeBytes']);
    },
];
