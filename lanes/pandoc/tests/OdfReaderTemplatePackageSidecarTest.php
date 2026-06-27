<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$templateBytes = 'TEMPLATE-OTT-BYTES';
$previewBytes = 'TEMPLATE-PREVIEW-PNG';
$encryptedBytes = 'ENCRYPTED-TEMPLATE-BYTES';
$orphanBytes = 'ORPHAN-TEMPLATE-BYTES';

$templateSize = strlen($templateBytes);
$previewSize = strlen($previewBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Templates/Review/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Templates/Review/source-template.ott" manifest:media-type="application/vnd.oasis.opendocument.text-template" manifest:size="{$templateSize}"/>
  <manifest:file-entry manifest:full-path="Templates/Review/preview.png" manifest:media-type="image/png" manifest:size="{$previewSize}"/>
  <manifest:file-entry manifest:full-path="Templates/Review/missing.ott" manifest:media-type="application/vnd.oasis.opendocument.text-template" manifest:size="19"/>
  <manifest:file-entry manifest:full-path="Templates/Review/encrypted.ott" manifest:media-type="application/vnd.oasis.opendocument.text-template" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="template-checksum"/>
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
      <text:p>Template package sidecars.</text:p>
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
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  office:version="1.3">
  <office:meta>
    <dc:title>Template Sidecar Packet</dc:title>
    <meta:template
      xlink:href="../Templates/Review/source-template.ott"
      xlink:title="Source Template"
      xlink:type="simple"
      xlink:actuate="onRequest"
      xlink:show="replace"
      meta:date="2026-06-27T23:30:00Z"
      meta:name="source-template">Source Template</meta:template>
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
    ['name' => 'Templates/Review/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Templates/Review/source-template.ott', 'data' => $templateBytes, 'compressionMethod' => 0],
    ['name' => 'Templates/Review/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
    ['name' => 'Templates/Review/encrypted.ott', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Templates/Review/orphan.ott', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt template package sidecars');

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
    'reports ODT template package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $templateBytes,
        $previewBytes,
        $encryptedBytes,
        $orphanBytes,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerTemplates = $result['packageTemplates'];
        $readerItems = $indexBy($readerTemplates['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($readerTemplates, $result['document']->attr('packageTemplates'));
        $t->same($readerTemplates, $result['metadata']['odfPackageTemplates']);
        $t->same($readerTemplates, $result['importReport']['packageTemplates']);
        $t->same('../Templates/Review/source-template.ott', $result['metadata']['template']['href']);
        $t->same('Source Template', $result['metadata']['template']['title']);
        $t->same(6, $readerTemplates['count']);
        $t->same(3, $readerTemplates['readableCount']);
        $t->same(5, $readerTemplates['declaredCount']);
        $t->same(1, $readerTemplates['undeclaredCount']);
        $t->same(1, $readerTemplates['missingCount']);
        $t->same(1, $readerTemplates['directoryCount']);
        $t->same(1, $readerTemplates['encryptedCount']);
        $t->same(0, $readerTemplates['missingMediaTypeCount']);
        $t->same(0, $readerTemplates['invalidMediaTypeCount']);
        $t->same(3, $readerTemplates['issueCount']);
        $t->same([
            'odf-template-package-encrypted-part',
            'odf-template-package-missing-part',
            'odf-template-package-undeclared-part',
        ], $readerTemplates['issueCodes']);
        $t->same([
            'template-directory' => 1,
            'template-document-package' => 4,
            'template-media-resource' => 1,
        ], $readerTemplates['kindCounts']);
        $t->same(['review' => 6], $readerTemplates['groupCounts']);
        $t->same('template-package-bytes-blocked', $readerTemplates['byteExposurePolicy']);
        $t->same('template-package-metadata-only', $readerTemplates['reviewPolicy']);

        $directory = $readerItems['Templates/Review/'];
        $t->same('template-directory', $directory['kind']);
        $t->same('review', $directory['group']);
        $t->same(true, $directory['isDirectory']);
        $t->same(null, $directory['byteLength']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);

        $source = $readerItems['Templates/Review/source-template.ott'];
        $t->same('template-document-package', $source['kind']);
        $t->same('application/vnd.oasis.opendocument.text-template', $source['mediaTypeBase']);
        $t->same(strlen($templateBytes), $source['byteLength']);
        $t->same(sprintf('%08x', crc32($templateBytes)), $source['crc32']);
        $t->same(false, $source['canExposeBytes']);
        $t->same(false, $source['canExposeAsDocumentMedia']);
        $t->same('template-package-bytes-blocked', $source['byteExposurePolicy']);
        $t->same([], $source['issues']);

        $preview = $readerItems['Templates/Review/preview.png'];
        $t->same('template-media-resource', $preview['kind']);
        $t->same('image/png', $preview['mediaTypeBase']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);

        $missing = $readerItems['Templates/Review/missing.ott'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-template-package-missing-part'], $missing['issues']);
        $t->same('template-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $readerItems['Templates/Review/encrypted.ott'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedBytes), $encrypted['storedByteLength']);
        $t->same(['odf-template-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $readerItems['Templates/Review/orphan.ott'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('template-document-package', $orphan['kind']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-template-package-undeclared-part'], $orphan['issues']);
        $t->same('template-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $manifestPreview = $manifestByPart['Templates/Review/preview.png'];
        $t->same(true, $manifestPreview['templatePackagePart']);
        $t->same(false, $manifestPreview['canExposeBytes']);
        $t->same(null, $manifestPreview['byteLength']);
        $t->same(strlen($previewBytes), $manifestPreview['storedByteLength']);
        $t->same(null, $manifestPreview['byteSha256']);
        $t->same('template-package-bytes-blocked', $manifestPreview['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(5, $readerProvenance['templatePackagePartCount']);
        $t->same(5, $readerProvenance['roleCounts']['template-package']);
        $t->same(1, $readerProvenance['undeclaredRoleCounts']['template-package']);
        $t->same(['template-package', 'manifest-declared'], $readerProvenance['parts']['Templates/Review/preview.png']['roles']);
        $t->same(['template-package', 'undeclared-package-entry'], $readerProvenance['parts']['Templates/Review/orphan.ott']['roles']);
        $t->same(true, $readerProvenance['parts']['Templates/Review/preview.png']['templatePackagePart']);

        $readerIdentityManifest = $indexBy($readerProvenance['packageIdentity']['manifestEntries'], 'part');
        $readerIdentityPackage = $indexBy($readerProvenance['packageIdentity']['packageEntries'], 'part');
        $t->same(true, $readerIdentityManifest['Templates/Review/preview.png']['templatePackagePart']);
        $t->same(true, $readerIdentityPackage['Templates/Review/preview.png']['templatePackagePart']);
        $t->same(5, $readerProvenance['packageIdentity']['templatePackagePartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Template package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $templateBytes));
        $t->same(false, str_contains($blocks, $previewBytes));
        $t->same(false, str_contains($blocks, $orphanBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactTemplates = $compactSummary['packageTemplates'];
        $compactItems = $indexBy($compactTemplates['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(6, $compactTemplates['count']);
        $t->same(3, $compactTemplates['readableCount']);
        $t->same(5, $compactTemplates['declaredCount']);
        $t->same(1, $compactTemplates['undeclaredCount']);
        $t->same(1, $compactTemplates['missingCount']);
        $t->same(1, $compactTemplates['directoryCount']);
        $t->same(1, $compactTemplates['encryptedCount']);
        $t->same(3, $compactTemplates['issueCount']);
        $t->same($readerTemplates['issueCodes'], $compactTemplates['issueCodes']);
        $t->same($readerTemplates['kindCounts'], $compactTemplates['kindCounts']);
        $t->same($readerTemplates['groupCounts'], $compactTemplates['groupCounts']);
        $t->same('template-package-bytes-blocked', $compactTemplates['byteExposurePolicy']);
        $t->same('template-package-metadata-only', $compactTemplates['reviewPolicy']);
        $t->same('template-document-package', $compactItems['Templates/Review/source-template.ott']['kind']);
        $t->same('template-media-resource', $compactItems['Templates/Review/preview.png']['kind']);
        $t->same(false, $compactItems['Templates/Review/preview.png']['canExposeBytes']);
        $t->same(false, $compactItems['Templates/Review/preview.png']['canExposeAsDocumentMedia']);
        $t->same(strlen($previewBytes), $compactItems['Templates/Review/preview.png']['byteLength']);
        $t->same(sprintf('%08x', crc32($previewBytes)), $compactItems['Templates/Review/preview.png']['crc32']);
        $t->same(['odf-template-package-missing-part'], $compactItems['Templates/Review/missing.ott']['issues']);
        $t->same(['odf-template-package-encrypted-part'], $compactItems['Templates/Review/encrypted.ott']['issues']);
        $t->same(['odf-template-package-undeclared-part'], $compactItems['Templates/Review/orphan.ott']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(5, $compactSummary['manifestReview']['templatePackagePartCount']);
        $t->same(true, $reviewByPath['Templates/Review/preview.png']['templatePackagePart']);
        $t->same(false, $reviewByPath['Templates/Review/preview.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Templates/Review/preview.png']['byteLength']);
        $t->same(strlen($previewBytes), $reviewByPath['Templates/Review/preview.png']['storedByteLength']);
        $t->same('template-package-bytes-blocked', $reviewByPath['Templates/Review/preview.png']['byteExposurePolicy']);
        $t->same('template', $reviewByPath['Templates/Review/preview.png']['manifestMediaFamily']);
        $t->same(4, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['template']);
        $t->same(5, $inventory['templatePackagePartCount']);
        $t->same(5, $inventory['roleCounts']['template-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['template-package']);
        $t->same(['template-package', 'manifest-declared'], $inventory['parts']['Templates/Review/preview.png']['roles']);
        $t->same(['template-package', 'undeclared-package-entry'], $inventory['parts']['Templates/Review/orphan.ott']['roles']);
        $t->same(true, $inventory['parts']['Templates/Review/preview.png']['templatePackagePart']);
        $t->same(false, $inventory['parts']['Templates/Review/preview.png']['canExposeBytes']);

        $compactIdentityManifest = $indexBy($compactSummary['packageIdentity']['manifestEntries'], 'path');
        $compactIdentityPackage = $indexBy($compactSummary['packageIdentity']['packageEntries'], 'path');
        $t->same(true, $compactIdentityManifest['Templates/Review/preview.png']['templatePackagePart']);
        $t->same(true, $compactIdentityPackage['Templates/Review/preview.png']['templatePackagePart']);
        $t->same(5, $compactSummary['packageIdentity']['templatePackagePartCount']);
    },
];
