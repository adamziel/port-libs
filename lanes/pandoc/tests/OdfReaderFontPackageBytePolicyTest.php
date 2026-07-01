<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$reviewSansBytes = 'WOFF2DATA';
$orphanFontBytes = 'ORPHAN-OTF';
$privateNoteBytes = 'PRIVATE-NOTE';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Fonts/ReviewSans.woff2" manifest:media-type="font/woff2" manifest:size="__REVIEW_SIZE__"/>
  <manifest:file-entry manifest:full-path="Fonts/Missing.otf" manifest:media-type="application/vnd.ms-opentype"/>
</manifest:manifest>
XML;

$manifestXml = str_replace('__REVIEW_SIZE__', (string) strlen($reviewSansBytes), $manifestXml);

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Font package policy review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'Fonts/ReviewSans.woff2', 'data' => $reviewSansBytes, 'compressionMethod' => 0],
    ['name' => 'Fonts/orphan.otf', 'data' => $orphanFontBytes, 'compressionMethod' => 0],
    ['name' => 'Notes/private.txt', 'data' => $privateNoteBytes, 'compressionMethod' => 0],
], 'odt font package byte policy');

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
    'keeps undeclared ODT font package byte policies aligned with font review metadata' => static function (TestRunner $t) use (
        $buildPackage,
        $indexByPart,
        $reviewSansBytes,
        $orphanFontBytes,
        $privateNoteBytes
    ): void {
        $result = (new OdfReader())->readPackage($buildPackage());
        $fonts = $result['packageFonts'];
        $fontItems = $indexByPart($fonts['items']);
        $undeclared = $indexByPart($result['importReport']['manifest']['undeclaredEntries']);
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $parts = $provenance['parts'];
        $identity = $provenance['packageIdentity'];

        $t->same($fonts, $result['metadata']['odfPackageFonts']);
        $t->same($fonts, $result['document']->attr('packageFonts'));
        $t->same(3, $fonts['count']);
        $t->same(2, $fonts['readableCount']);
        $t->same(2, $fonts['declaredCount']);
        $t->same(1, $fonts['undeclaredCount']);
        $t->same(1, $fonts['missingCount']);
        $t->same('font-package-bytes-blocked', $fonts['byteExposurePolicy']);
        $t->same('package-font-metadata-only', $fonts['reviewPolicy']);

        $declared = $fontItems['Fonts/ReviewSans.woff2'];
        $t->same('font-package-bytes-blocked', $declared['byteExposurePolicy']);
        $t->same(strlen($reviewSansBytes), $declared['byteLength']);
        $t->same(sprintf('%08x', crc32($reviewSansBytes)), $declared['crc32']);
        $t->same(false, $declared['canExposeAsDocumentMedia']);

        $missing = $fontItems['Fonts/Missing.otf'];
        $t->same('font-package-bytes-blocked', $missing['byteExposurePolicy']);
        $t->same(false, $missing['exists']);
        $t->same(['odf-font-missing-package-part'], $missing['issues']);

        $orphan = $fontItems['Fonts/orphan.otf'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('opentype', $orphan['fontFormat']);
        $t->same('font-package-bytes-blocked', $orphan['byteExposurePolicy']);
        $t->same(strlen($orphanFontBytes), $orphan['byteLength']);
        $t->same(['odf-font-undeclared-package-part'], $orphan['issues']);

        $t->same('font-package-bytes-blocked', $undeclared['Fonts/orphan.otf']['byteExposurePolicy']);
        $t->same('undeclared-package-entry-no-bytes', $undeclared['Notes/private.txt']['byteExposurePolicy']);
        $t->same(false, $undeclared['Fonts/orphan.otf']['canExposeBytes']);
        $t->same(false, $undeclared['Notes/private.txt']['canExposeBytes']);

        $t->same(['font-package', 'undeclared-package-entry'], $parts['Fonts/orphan.otf']['roles']);
        $t->same(['undeclared-package-entry'], $parts['Notes/private.txt']['roles']);
        $t->same('font-package-bytes-blocked', $parts['Fonts/orphan.otf']['byteExposurePolicy']);
        $t->same('undeclared-package-entry-no-bytes', $parts['Notes/private.txt']['byteExposurePolicy']);
        $t->same(sprintf('%08x', crc32($orphanFontBytes)), $parts['Fonts/orphan.otf']['crc32']);
        $t->same(sprintf('%08x', crc32($privateNoteBytes)), $parts['Notes/private.txt']['crc32']);
        $t->same(null, $parts['Fonts/orphan.otf']['byteSha256'] ?? null);
        $t->same(null, $parts['Notes/private.txt']['byteSha256'] ?? null);

        $t->same(2, $provenance['packageFontPartCount']);
        $t->same(2, $provenance['roleCounts']['font-package']);
        $t->same(1, $provenance['undeclaredRoleCounts']['font-package']);
        $t->same(2, $provenance['packagePartByteExposurePolicyCounts']['font-package-bytes-blocked']);
        $t->same(1, $provenance['packagePartByteExposurePolicyCounts']['undeclared-package-entry-no-bytes']);
        $t->same(2, $identity['packagePartByteExposurePolicyCounts']['font-package-bytes-blocked']);
        $t->same(1, $identity['packagePartByteExposurePolicyCounts']['undeclared-package-entry-no-bytes']);
        $t->same([], array_column($result['media'], 'part'));
    },
];
