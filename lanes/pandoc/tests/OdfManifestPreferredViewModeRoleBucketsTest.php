<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Preferred view mode role buckets.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Preferred View Mode Role Buckets</dc:title>
  </office:meta>
</office:document-meta>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:preferred-view-mode="edit"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:preferred-view-mode="read-only"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml" manifest:preferred-view-mode="presentation-slide-show"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:preferred-view-mode="thumbnail"/>
  <manifest:file-entry manifest:full-path="Thumbnails/thumb.png" manifest:media-type="image/png" manifest:preferred-view-mode="read-only"/>
  <manifest:file-entry manifest:full-path="Scripts/macro.js" manifest:media-type="application/javascript" manifest:preferred-view-mode="wp:macro"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/" manifest:media-type="application/vnd.oasis.opendocument.chart" manifest:preferred-view-mode="view"/>
  <manifest:file-entry manifest:full-path="Object%20Chart/content.xml" manifest:media-type="text/xml" manifest:preferred-view-mode="wp:object"/>
</manifest:manifest>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Thumbnails/thumb.png', 'data' => 'THUMB', 'compressionMethod' => 0],
    ['name' => 'Scripts/macro.js', 'data' => 'function macro() { return true; }', 'compressionMethod' => 0],
    ['name' => 'Object Chart/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Object Chart/content.xml', 'data' => '<chart/>', 'compressionMethod' => 0],
], 'odf preferred view mode role bucket provenance');

$indexByPath = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item['fullPath']] = $item;
    }

    return $indexed;
};

return [
    'records mapped ODT preferred view mode role bucket case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedOdfManifestPreferredViewModeRoleBucketCases'] ?? null);
        $t->same(38, $manifest['odfManifestPreferredViewModeRoleBucketAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedOdfManifestPreferredViewModeRoleBucketCases'] ?? null);
        $t->same(38, $manifest['benchmarkDenominator']['breakdown']['odfManifestPreferredViewModeRoleBucketAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedOdfManifestPreferredViewModeRoleBucketCases'] ?? null);
        $t->same(38, $manifest['benchmarkDenominator']['inventory']['odfManifestPreferredViewModeRoleBucketAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedOdfManifestPreferredViewModeRoleBucketCases'] ?? null);
        $t->same(38, $manifest['inventory']['odfManifestPreferredViewModeRoleBucketAssertions'] ?? null);
    },

    'summarizes ODT preferred view mode role buckets across compact and rich package provenance' => static function (TestRunner $t) use ($buildPackage, $indexByPath): void {
        $package = $buildPackage();
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactPreferredViewModes = $compactSummary['manifestReview']['preferredViewModes'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richPreferredViewModes = $richProvenance['preferredViewModes'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        $expectedRoleCounts = [
            'embedded-object-part' => 1,
            'embedded-object-root' => 1,
            'manifest-declared' => 8,
            'media-resource' => 1,
            'odf-content' => 1,
            'odf-styles' => 1,
            'package-thumbnail' => 1,
            'script-package' => 1,
        ];
        $expectedModeRoleCounts = [
            'edit' => ['manifest-declared' => 1],
            'presentation-slide-show' => ['manifest-declared' => 1, 'odf-styles' => 1],
            'read-only' => ['manifest-declared' => 2, 'odf-content' => 1, 'package-thumbnail' => 1],
            'thumbnail' => ['manifest-declared' => 1, 'media-resource' => 1],
            'view' => ['embedded-object-root' => 1, 'manifest-declared' => 1],
            'wp:macro' => ['manifest-declared' => 1, 'script-package' => 1],
            'wp:object' => ['embedded-object-part' => 1, 'manifest-declared' => 1],
        ];
        $expectedEntryNamesByRole = [
            'embedded-object-part' => ['Object%20Chart/content.xml'],
            'embedded-object-root' => ['Object%20Chart/'],
            'manifest-declared' => [
                '/',
                'Object%20Chart/',
                'Object%20Chart/content.xml',
                'Pictures/hero.png',
                'Scripts/macro.js',
                'Thumbnails/thumb.png',
                'content.xml',
                'styles.xml',
            ],
            'media-resource' => ['Pictures/hero.png'],
            'odf-content' => ['content.xml'],
            'odf-styles' => ['styles.xml'],
            'package-thumbnail' => ['Thumbnails/thumb.png'],
            'script-package' => ['Scripts/macro.js'],
        ];

        $t->same($compactPreferredViewModes, $compactIdentity['preferredViewModes']);
        $t->same($richPreferredViewModes, $richIdentity['preferredViewModes']);
        $t->same($richIdentity['preferredViewModes'], $documentIdentity['preferredViewModes']);
        $t->same($compactPreferredViewModes['roleCounts'], $richPreferredViewModes['roleCounts']);
        $t->same($expectedRoleCounts, $richPreferredViewModes['roleCounts']);
        $t->same($expectedRoleCounts, $compactPreferredViewModes['roleCounts']);
        $t->same($expectedModeRoleCounts, $richPreferredViewModes['modeRoleCounts']);
        $t->same($expectedModeRoleCounts, $compactPreferredViewModes['modeRoleCounts']);
        $t->same($expectedEntryNamesByRole, $richPreferredViewModes['entryNamesByRole']);
        $t->same(8, $richPreferredViewModes['count']);
        $t->same(2, $richPreferredViewModes['invalidTokenCount']);
        $t->same(7, $richPreferredViewModes['nonRootEntryCount']);
        $t->same([
            'odf-preferred-view-mode-invalid-token' => 2,
            'odf-preferred-view-mode-non-root-entry' => 7,
        ], $richPreferredViewModes['issueCodeCounts']);
        $t->same([
            'edit' => 1,
            'presentation-slide-show' => 1,
            'read-only' => 2,
            'thumbnail' => 1,
            'view' => 1,
            'wp:macro' => 1,
            'wp:object' => 1,
        ], $richPreferredViewModes['modeCounts']);
        $t->same('edit', $richPreferredViewModes['rootMode']);
        $t->same(4, $richPreferredViewModes['definedModeCount']);
        $t->same(2, $richPreferredViewModes['namespacedTokenCount']);

        $itemsByPath = $indexByPath($richPreferredViewModes['items']);
        $compactItemsByPath = $indexByPath($compactPreferredViewModes['items']);

        $t->same(['manifest-declared'], $itemsByPath['/']['roles']);
        $t->same(['odf-content', 'manifest-declared'], $itemsByPath['content.xml']['roles']);
        $t->same(['odf-styles', 'manifest-declared'], $itemsByPath['styles.xml']['roles']);
        $t->same(['manifest-declared', 'media-resource'], $itemsByPath['Pictures/hero.png']['roles']);
        $t->same(['package-thumbnail', 'manifest-declared'], $itemsByPath['Thumbnails/thumb.png']['roles']);
        $t->same(['manifest-declared', 'script-package'], $itemsByPath['Scripts/macro.js']['roles']);
        $t->same(['embedded-object-root', 'manifest-declared'], $itemsByPath['Object%20Chart/']['roles']);
        $t->same(['embedded-object-part', 'manifest-declared'], $itemsByPath['Object%20Chart/content.xml']['roles']);
        $t->same('Object Chart/content.xml', $itemsByPath['Object%20Chart/content.xml']['part']);
        $t->same('Object Chart/content.xml', $compactItemsByPath['Object%20Chart/content.xml']['packagePath']);
        $t->same($expectedEntryNamesByRole['manifest-declared'], $compactPreferredViewModes['entryNamesByRole']['manifest-declared']);
        $t->same(['Pictures/hero.png', 'Object%20Chart/'], array_column($richPreferredViewModes['invalidTokenItems'], 'fullPath'));
        $t->same([
            'content.xml',
            'styles.xml',
            'Pictures/hero.png',
            'Thumbnails/thumb.png',
            'Scripts/macro.js',
            'Object%20Chart/',
            'Object%20Chart/content.xml',
        ], array_column($richPreferredViewModes['nonRootItems'], 'fullPath'));
    },
];
