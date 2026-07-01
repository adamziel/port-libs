<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Compact missing manifest-declared package parts.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:size="__CONTENT_SIZE__"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png" manifest:size="12"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Missing.xml" manifest:media-type="text/xml" manifest:size="13"/>
  <manifest:file-entry manifest:full-path="Fonts/Missing.woff2" manifest:media-type="font/woff2" manifest:size="14"/>
</manifest:manifest>
XML;

$manifestXml = str_replace('__CONTENT_SIZE__', (string) strlen($contentXml), $manifestXml);

$indexByPart = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        $part = $item['part'] ?? null;
        if (is_string($part) && $part !== '') {
            $indexed[$part] = $item;
        }
    }

    return $indexed;
};

return [
    'summarizes compact manifest-declared package parts missing from ODT zip payloads' => static function (TestRunner $t) use ($contentXml, $manifestXml, $indexByPart): void {
        $summary = OpenDocumentPackage::fromPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
        ], 'compact odt missing manifest-declared parts'))->summarize();

        $review = $summary['manifestReview'];
        $missing = $indexByPart($review['missingManifestDeclaredItems']);

        $t->same(3, $review['missingCount']);
        $t->same(3, $review['missingManifestDeclaredPartCount']);
        $t->same(['Pictures/missing.png', 'Basic/Standard/Missing.xml', 'Fonts/Missing.woff2'], array_column($review['missingManifestDeclaredItems'], 'part'));
        $t->same([
            'font-package' => 1,
            'manifest-declared' => 3,
            'media-resource' => 1,
            'script-package' => 1,
        ], $review['missingManifestDeclaredRoleCounts']);
        $t->same([
            'font-package-bytes-blocked' => 1,
            'missing-package-part' => 1,
            'script-package-bytes-blocked' => 1,
        ], $review['missingManifestDeclaredByteExposurePolicyCounts']);
        $t->same([
            'font/woff2' => 1,
            'image/png' => 1,
            'text/xml' => 1,
        ], $review['missingManifestDeclaredMediaTypeBaseCounts']);

        $image = $missing['Pictures/missing.png'];
        $t->same(['manifest-declared', 'media-resource'], $image['roles']);
        $t->same('missing-package-part', $image['byteExposurePolicy']);
        $t->same('image/png', $image['mediaTypeBase']);
        $t->same(12, $image['declaredSize']);

        $script = $missing['Basic/Standard/Missing.xml'];
        $t->same(['manifest-declared', 'script-package'], $script['roles']);
        $t->same('script-package-bytes-blocked', $script['byteExposurePolicy']);
        $t->same('text/xml', $script['mediaTypeBase']);
        $t->same(13, $script['declaredSize']);

        $font = $missing['Fonts/Missing.woff2'];
        $t->same(['font-package', 'manifest-declared'], $font['roles']);
        $t->same('font-package-bytes-blocked', $font['byteExposurePolicy']);
        $t->same('font/woff2', $font['mediaTypeBase']);
        $t->same(14, $font['declaredSize']);

        $t->same(false, isset($summary['packageInventory']['parts']['Pictures/missing.png']));
        $t->same(false, isset($summary['packageInventory']['roleCounts']['media-resource']));
        $t->same(false, isset($summary['packageInventory']['roleCounts']['script-package']));
        $t->same(false, isset($summary['packageInventory']['roleCounts']['font-package']));
    },
];
