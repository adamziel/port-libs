<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

return [
    'blocks ODT package thumbnail bytes in manifest and package provenance' => static function (TestRunner $t): void {
        $thumbnailBytes = 'THUMBNAIL-PREVIEW';
        $thumbnailSize = strlen($thumbnailBytes);
        $manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Thumbnails/thumbnail.png" manifest:media-type="image/png" manifest:size="{$thumbnailSize}"/>
</manifest:manifest>
XML;
        $contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Thumbnail byte policy packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $package = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'Thumbnails/thumbnail.png', 'data' => $thumbnailBytes, 'compressionMethod' => 0],
        ], 'odt thumbnail byte policy package');

        $compact = OpenDocumentPackage::fromPackage($package);
        $compactSummary = $compact->summarize();
        $compactThumbnail = $compact->manifestEntry('Thumbnails/thumbnail.png');
        $compactReviewByPath = [];
        foreach ($compactSummary['manifestReview']['items'] as $item) {
            $compactReviewByPath[$item['path']] = $item;
        }
        $compactThumbnailReport = $compactSummary['packageThumbnails']['items'][0];
        $compactInventoryPart = $compactSummary['packageInventory']['parts']['Thumbnails/thumbnail.png'];

        $t->same(true, $compactThumbnail['packageThumbnailPart']);
        $t->same('thumbnail', $compactThumbnail['manifestMediaFamily']);
        $t->same(false, $compactThumbnail['canExposeBytes']);
        $t->same(null, $compactThumbnail['byteLength']);
        $t->same(null, $compactThumbnail['crc32']);
        $t->same(null, $compactThumbnail['byteSha256']);
        $t->same(strlen($thumbnailBytes), $compactThumbnail['storedByteLength']);
        $t->same('package-thumbnail-bytes-blocked', $compactThumbnail['byteExposurePolicy']);
        $t->same(true, $compactReviewByPath['Thumbnails/thumbnail.png']['packageThumbnailPart']);
        $t->same(false, $compactReviewByPath['Thumbnails/thumbnail.png']['canExposeBytes']);
        $t->same('package-thumbnail-bytes-blocked', $compactReviewByPath['Thumbnails/thumbnail.png']['byteExposurePolicy']);
        $t->same(['package-thumbnail', 'manifest-declared'], $compactInventoryPart['roles']);
        $t->same(true, $compactInventoryPart['packageThumbnailPart']);
        $t->same(false, $compactInventoryPart['canExposeBytes']);
        $t->same(null, $compactInventoryPart['byteSha256']);
        $t->same('package-thumbnail-bytes-blocked', $compactInventoryPart['byteExposurePolicy']);
        $t->same(1, $compactSummary['packageInventory']['byteExposurePolicyCounts']['package-thumbnail-bytes-blocked']);
        $t->same(strlen($thumbnailBytes), $compactThumbnailReport['byteLength']);
        $t->same(sprintf('%08x', crc32($thumbnailBytes)), $compactThumbnailReport['crc32']);
        $t->same('package-thumbnail-metadata-only', $compactThumbnailReport['reviewPolicy']);

        $result = (new OdfReader())->readPackage($package);
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $manifestOrderByPart = [];
        foreach ($provenance['manifestFileEntryOrder'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestOrderByPart[$item['part']] = $item;
            }
        }
        $richThumbnailReport = $result['importReport']['packageThumbnails']['items'][0];
        $richPart = $provenance['parts']['Thumbnails/thumbnail.png'];

        $t->same(true, $manifestByPart['Thumbnails/thumbnail.png']['packageThumbnailPart']);
        $t->same(false, $manifestByPart['Thumbnails/thumbnail.png']['canExposeBytes']);
        $t->same(null, $manifestByPart['Thumbnails/thumbnail.png']['byteLength']);
        $t->same(null, $manifestByPart['Thumbnails/thumbnail.png']['crc32']);
        $t->same(null, $manifestByPart['Thumbnails/thumbnail.png']['byteSha256']);
        $t->same(strlen($thumbnailBytes), $manifestByPart['Thumbnails/thumbnail.png']['storedByteLength']);
        $t->same('package-thumbnail-bytes-blocked', $manifestByPart['Thumbnails/thumbnail.png']['byteExposurePolicy']);
        $t->same(true, $manifestOrderByPart['Thumbnails/thumbnail.png']['packageThumbnailPart']);
        $t->same(false, $manifestOrderByPart['Thumbnails/thumbnail.png']['canExposeBytes']);
        $t->same('package-thumbnail-bytes-blocked', $manifestOrderByPart['Thumbnails/thumbnail.png']['byteExposurePolicy']);
        $t->same(['package-thumbnail', 'manifest-declared'], $richPart['roles']);
        $t->same(true, $richPart['packageThumbnailPart']);
        $t->same(false, $richPart['canExposeBytes']);
        $t->same(null, $richPart['byteSha256']);
        $t->same('package-thumbnail-bytes-blocked', $richPart['byteExposurePolicy']);
        $t->same(1, $provenance['manifestByteExposurePolicyCounts']['package-thumbnail-bytes-blocked']);
        $t->same(1, $provenance['packagePartByteExposurePolicyCounts']['package-thumbnail-bytes-blocked']);
        $t->same(strlen($thumbnailBytes), $richThumbnailReport['byteLength']);
        $t->same(sprintf('%08x', crc32($thumbnailBytes)), $richThumbnailReport['crc32']);
        $t->same('package-thumbnail-metadata-only', $richThumbnailReport['reviewPolicy']);
        $t->same(false, in_array('Thumbnails/thumbnail.png', array_column($result['media'], 'part'), true));
    },
];
