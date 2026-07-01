<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$attachmentManifestXml = '<attachments xmlns="urn:example:attachments"><item name="source.pdf"/></attachments>';
$sourcePdfBytes = '%PDF-ATTACHMENT-DATA';
$sourceOdtBytes = 'ODT-ATTACHMENT-DATA';
$sourceRtfBytes = '{\\rtf1 ATTACHMENT-DATA}';
$previewBytes = 'ATTACHMENT-PREVIEW-PNG';
$encryptedBytes = 'ENCRYPTED-ATTACHMENT-BYTES';
$orphanBytes = 'ORPHAN-ODT-ATTACHMENT-DATA';
$orphanDocBytes = 'ORPHAN-DOC-ATTACHMENT-DATA';

$attachmentManifestSize = strlen($attachmentManifestXml);
$sourcePdfSize = strlen($sourcePdfBytes);
$sourceOdtSize = strlen($sourceOdtBytes);
$sourceRtfSize = strlen($sourceRtfBytes);
$previewSize = strlen($previewBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Attachments/Review/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Attachments/Review/manifest.xml" manifest:media-type="text/xml" manifest:size="{$attachmentManifestSize}"/>
  <manifest:file-entry manifest:full-path="Attachments/Review/source.pdf" manifest:media-type="application/pdf" manifest:size="{$sourcePdfSize}bytes"/>
  <manifest:file-entry manifest:full-path="Attachments/Review/source.odt" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:size="{$sourceOdtSize}"/>
  <manifest:file-entry manifest:full-path="Attachments/Review/preview.png" manifest:media-type="image/png" manifest:size="{$previewSize}"/>
  <manifest:file-entry manifest:full-path="Attachments/Review/missing.dat" manifest:media-type="application/octet-stream" manifest:size="21"/>
  <manifest:file-entry manifest:full-path="Attachments/Review/encrypted.dat" manifest:media-type="application/octet-stream" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="attachment-checksum"/>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="Attachments/Review/source.rtf" manifest:media-type="application/rtf" manifest:size="{$sourceRtfSize}"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Attachment package sidecars.</text:p>
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
    <dc:title>Attachment Sidecar Packet</dc:title>
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
    ['name' => 'Attachments/Review/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Attachments/Review/manifest.xml', 'data' => $attachmentManifestXml, 'compressionMethod' => 0],
    ['name' => 'Attachments/Review/source.pdf', 'data' => $sourcePdfBytes, 'compressionMethod' => 0],
    ['name' => 'Attachments/Review/source.odt', 'data' => $sourceOdtBytes, 'compressionMethod' => 0],
    ['name' => 'Attachments/Review/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
    ['name' => 'Attachments/Review/encrypted.dat', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Attachments/Review/source.rtf', 'data' => $sourceRtfBytes, 'compressionMethod' => 0],
    ['name' => 'Attachments/Review/orphan.odt', 'data' => $orphanBytes, 'compressionMethod' => 0],
    ['name' => 'Attachments/Review/orphan.doc', 'data' => $orphanDocBytes, 'compressionMethod' => 0],
], 'odt attachment package sidecars');

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
    'reports ODT attachment package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $attachmentManifestXml,
        $sourcePdfBytes,
        $sourcePdfSize,
        $sourceOdtBytes,
        $sourceRtfBytes,
        $previewBytes,
        $orphanBytes,
        $orphanDocBytes,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerAttachments = $result['packageAttachments'];
        $readerItems = $indexBy($readerAttachments['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($readerAttachments, $result['document']->attr('packageAttachments'));
        $t->same($readerAttachments, $result['metadata']['odfPackageAttachments']);
        $t->same($readerAttachments, $result['importReport']['packageAttachments']);
        $t->same(10, $readerAttachments['count']);
        $t->same(7, $readerAttachments['readableCount']);
        $t->same(8, $readerAttachments['declaredCount']);
        $t->same(2, $readerAttachments['undeclaredCount']);
        $t->same(1, $readerAttachments['missingCount']);
        $t->same(1, $readerAttachments['directoryCount']);
        $t->same(1, $readerAttachments['encryptedCount']);
        $t->same(0, $readerAttachments['missingMediaTypeCount']);
        $t->same(0, $readerAttachments['invalidMediaTypeCount']);
        $t->same(1, $readerAttachments['invalidDeclaredSizeCount']);
        $t->same(5, $readerAttachments['issueCount']);
        $t->same([
            'odf-attachment-package-encrypted-part',
            'odf-attachment-package-invalid-declared-size',
            'odf-attachment-package-missing-part',
            'odf-attachment-package-undeclared-part',
        ], $readerAttachments['issueCodes']);
        $t->same([
            'attachment-binary-resource' => 2,
            'attachment-directory' => 1,
            'attachment-document-resource' => 5,
            'attachment-manifest' => 1,
            'attachment-media-resource' => 1,
        ], $readerAttachments['kindCounts']);
        $t->same(['review' => 10], $readerAttachments['groupCounts']);
        $t->same('attachment-package-bytes-blocked', $readerAttachments['byteExposurePolicy']);
        $t->same('attachment-package-metadata-only', $readerAttachments['reviewPolicy']);

        $directory = $readerItems['Attachments/Review/'];
        $t->same('attachment-directory', $directory['kind']);
        $t->same(true, $directory['isDirectory']);
        $t->same(null, $directory['byteLength']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);

        $attachmentManifest = $readerItems['Attachments/Review/manifest.xml'];
        $t->same('attachment-manifest', $attachmentManifest['kind']);
        $t->same(strlen($attachmentManifestXml), $attachmentManifest['byteLength']);
        $t->same(sprintf('%08x', crc32($attachmentManifestXml)), $attachmentManifest['crc32']);
        $t->same(false, $attachmentManifest['canExposeBytes']);
        $t->same(false, $attachmentManifest['canExposeAsDocumentMedia']);
        $t->same('attachment-package-bytes-blocked', $attachmentManifest['byteExposurePolicy']);
        $t->same([], $attachmentManifest['issues']);

        $sourcePdf = $readerItems['Attachments/Review/source.pdf'];
        $t->same('attachment-document-resource', $sourcePdf['kind']);
        $t->same('application/pdf', $sourcePdf['mediaTypeBase']);
        $t->same(strlen($sourcePdfBytes), $sourcePdf['byteLength']);
        $t->same(null, $sourcePdf['declaredSize']);
        $t->same($sourcePdfSize . 'bytes', $sourcePdf['declaredSizeRaw']);
        $t->same(false, $sourcePdf['declaredSizeValid']);
        $t->same(true, $sourcePdf['declaredSizeInvalid']);
        $t->same(['odf-attachment-package-invalid-declared-size'], $sourcePdf['issues']);
        $t->same(false, $sourcePdf['canExposeAsDocumentMedia']);

        $sourceOdt = $readerItems['Attachments/Review/source.odt'];
        $t->same('attachment-document-resource', $sourceOdt['kind']);
        $t->same('application/vnd.oasis.opendocument.text', $sourceOdt['mediaTypeBase']);
        $t->same(strlen($sourceOdtBytes), $sourceOdt['byteLength']);
        $t->same(false, $sourceOdt['canExposeBytes']);
        $t->same(false, $sourceOdt['canExposeAsDocumentMedia']);

        $sourceRtf = $readerItems['Attachments/Review/source.rtf'];
        $t->same('attachment-document-resource', $sourceRtf['kind']);
        $t->same('application/rtf', $sourceRtf['mediaTypeBase']);
        $t->same(strlen($sourceRtfBytes), $sourceRtf['byteLength']);
        $t->same(false, $sourceRtf['canExposeBytes']);
        $t->same(false, $sourceRtf['canExposeAsDocumentMedia']);

        $preview = $readerItems['Attachments/Review/preview.png'];
        $t->same('attachment-media-resource', $preview['kind']);
        $t->same('image/png', $preview['mediaTypeBase']);
        $t->same(strlen($previewBytes), $preview['byteLength']);
        $t->same(false, $preview['canExposeAsDocumentMedia']);

        $missing = $readerItems['Attachments/Review/missing.dat'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-attachment-package-missing-part'], $missing['issues']);
        $t->same('attachment-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $readerItems['Attachments/Review/encrypted.dat'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-attachment-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $readerItems['Attachments/Review/orphan.odt'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('attachment-document-resource', $orphan['kind']);
        $t->same('application/vnd.oasis.opendocument.text', $orphan['mediaTypeBase']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-attachment-package-undeclared-part'], $orphan['issues']);
        $t->same('attachment-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $orphanDoc = $readerItems['Attachments/Review/orphan.doc'];
        $t->same(false, $orphanDoc['declared']);
        $t->same(true, $orphanDoc['undeclared']);
        $t->same('attachment-document-resource', $orphanDoc['kind']);
        $t->same('application/msword', $orphanDoc['mediaTypeBase']);
        $t->same(strlen($orphanDocBytes), $orphanDoc['byteLength']);
        $t->same(['odf-attachment-package-undeclared-part'], $orphanDoc['issues']);
        $t->same('attachment-package-bytes-blocked', $orphanDoc['byteExposurePolicy']);

        $manifestPreview = $manifestByPart['Attachments/Review/preview.png'];
        $t->same(true, $manifestPreview['attachmentPackagePart']);
        $t->same(false, $manifestPreview['canExposeBytes']);
        $t->same(null, $manifestPreview['byteLength']);
        $t->same(strlen($previewBytes), $manifestPreview['storedByteLength']);
        $t->same(null, $manifestPreview['byteSha256']);
        $t->same('attachment-package-bytes-blocked', $manifestPreview['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(9, $readerProvenance['attachmentPackagePartCount']);
        $t->same(9, $readerProvenance['roleCounts']['attachment-package']);
        $t->same(2, $readerProvenance['undeclaredRoleCounts']['attachment-package']);
        $t->same(['attachment-package', 'manifest-declared'], $readerProvenance['parts']['Attachments/Review/preview.png']['roles']);
        $t->same(['attachment-package', 'undeclared-package-entry'], $readerProvenance['parts']['Attachments/Review/orphan.odt']['roles']);
        $t->same(true, $readerProvenance['parts']['Attachments/Review/preview.png']['attachmentPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['manifestEntries'][9]['attachmentPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['packageEntries'][10]['attachmentPackagePart']);
        $t->same(9, $readerProvenance['packageIdentity']['attachmentPackagePartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Attachment package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $attachmentManifestXml));
        $t->same(false, str_contains($blocks, $sourcePdfBytes));
        $t->same(false, str_contains($blocks, $sourceRtfBytes));
        $t->same(false, str_contains($blocks, $previewBytes));
        $t->same(false, str_contains($blocks, $orphanBytes));
        $t->same(false, str_contains($blocks, $orphanDocBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactAttachments = $compactSummary['packageAttachments'];
        $compactItems = $indexBy($compactAttachments['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $inventory = $compactSummary['packageInventory'];

        $t->same(10, $compactAttachments['count']);
        $t->same(7, $compactAttachments['readableCount']);
        $t->same(8, $compactAttachments['declaredCount']);
        $t->same(2, $compactAttachments['undeclaredCount']);
        $t->same(1, $compactAttachments['missingCount']);
        $t->same(1, $compactAttachments['directoryCount']);
        $t->same(1, $compactAttachments['encryptedCount']);
        $t->same(1, $compactAttachments['invalidDeclaredSizeCount']);
        $t->same(5, $compactAttachments['issueCount']);
        $t->same($readerAttachments['issueCodes'], $compactAttachments['issueCodes']);
        $t->same('attachment-package-bytes-blocked', $compactAttachments['byteExposurePolicy']);
        $t->same('attachment-package-metadata-only', $compactAttachments['reviewPolicy']);
        $t->same('attachment-manifest', $compactItems['Attachments/Review/manifest.xml']['kind']);
        $t->same(false, $compactItems['Attachments/Review/manifest.xml']['canExposeBytes']);
        $t->same(false, $compactItems['Attachments/Review/manifest.xml']['canExposeAsDocumentMedia']);
        $t->same(strlen($attachmentManifestXml), $compactItems['Attachments/Review/manifest.xml']['byteLength']);
        $t->same(sprintf('%08x', crc32($attachmentManifestXml)), $compactItems['Attachments/Review/manifest.xml']['crc32']);
        $t->same('attachment-document-resource', $compactItems['Attachments/Review/source.pdf']['kind']);
        $t->same(null, $compactItems['Attachments/Review/source.pdf']['declaredSize']);
        $t->same($sourcePdfSize . 'bytes', $compactItems['Attachments/Review/source.pdf']['declaredSizeRaw']);
        $t->same(false, $compactItems['Attachments/Review/source.pdf']['declaredSizeValid']);
        $t->same(true, $compactItems['Attachments/Review/source.pdf']['declaredSizeInvalid']);
        $t->same(['odf-attachment-package-invalid-declared-size'], $compactItems['Attachments/Review/source.pdf']['issues']);
        $t->same('attachment-document-resource', $compactItems['Attachments/Review/source.odt']['kind']);
        $t->same('application/vnd.oasis.opendocument.text', $compactItems['Attachments/Review/source.odt']['mediaTypeBase']);
        $t->same('attachment-document-resource', $compactItems['Attachments/Review/source.rtf']['kind']);
        $t->same('application/rtf', $compactItems['Attachments/Review/source.rtf']['mediaTypeBase']);
        $t->same('attachment-media-resource', $compactItems['Attachments/Review/preview.png']['kind']);
        $t->same(['odf-attachment-package-missing-part'], $compactItems['Attachments/Review/missing.dat']['issues']);
        $t->same(['odf-attachment-package-encrypted-part'], $compactItems['Attachments/Review/encrypted.dat']['issues']);
        $t->same(['odf-attachment-package-undeclared-part'], $compactItems['Attachments/Review/orphan.odt']['issues']);
        $t->same('attachment-document-resource', $compactItems['Attachments/Review/orphan.odt']['kind']);
        $t->same('application/vnd.oasis.opendocument.text', $compactItems['Attachments/Review/orphan.odt']['mediaTypeBase']);
        $t->same(['odf-attachment-package-undeclared-part'], $compactItems['Attachments/Review/orphan.doc']['issues']);
        $t->same('attachment-document-resource', $compactItems['Attachments/Review/orphan.doc']['kind']);
        $t->same('application/msword', $compactItems['Attachments/Review/orphan.doc']['mediaTypeBase']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(8, $compactSummary['manifestReview']['attachmentPackagePartCount']);
        $t->same(true, $reviewByPath['Attachments/Review/preview.png']['attachmentPackagePart']);
        $t->same(false, $reviewByPath['Attachments/Review/preview.png']['canExposeBytes']);
        $t->same(null, $reviewByPath['Attachments/Review/preview.png']['byteLength']);
        $t->same(strlen($previewBytes), $reviewByPath['Attachments/Review/preview.png']['storedByteLength']);
        $t->same('attachment-package-bytes-blocked', $reviewByPath['Attachments/Review/preview.png']['byteExposurePolicy']);
        $t->same('attachment', $reviewByPath['Attachments/Review/preview.png']['manifestMediaFamily']);
        $t->same(7, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['attachment']);
        $t->same(9, $inventory['attachmentPackagePartCount']);
        $t->same(9, $inventory['roleCounts']['attachment-package']);
        $t->same(2, $inventory['undeclaredRoleCounts']['attachment-package']);
        $t->same(['attachment-package', 'manifest-declared'], $inventory['parts']['Attachments/Review/preview.png']['roles']);
        $t->same(['attachment-package', 'undeclared-package-entry'], $inventory['parts']['Attachments/Review/orphan.odt']['roles']);
        $t->same(true, $inventory['parts']['Attachments/Review/preview.png']['attachmentPackagePart']);
        $t->same(false, $inventory['parts']['Attachments/Review/preview.png']['canExposeBytes']);
        $t->same(true, $compactSummary['packageIdentity']['manifestEntries'][9]['attachmentPackagePart']);
        $t->same(true, $compactSummary['packageIdentity']['packageEntries'][10]['attachmentPackagePart']);
        $t->same(9, $compactSummary['packageIdentity']['attachmentPackagePartCount']);
    },
];
