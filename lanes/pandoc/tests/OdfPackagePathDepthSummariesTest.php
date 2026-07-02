<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/HERO.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/no-extension" manifest:media-type="image/svg+xml"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="layout-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package path-depth summaries.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="ReviewBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Package path-depth summaries</dc:title>
  </office:meta>
</office:document-meta>
XML;

$parts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/HERO.PNG', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/no-extension', 'data' => '<svg/>', 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => '<script/>', 'compressionMethod' => 0],
    ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accel/>', 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE', 'compressionMethod' => 0],
    ['name' => 'layout-cache', 'data' => 'LAYOUTCACHE', 'compressionMethod' => 0],
];

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts($parts, 'odt package path depth summaries');

$indexByDepth = static function (array $summaries): array {
    $indexed = [];
    foreach ($summaries as $summary) {
        $indexed[$summary['packagePathDepth']] = $summary;
    }

    return $indexed;
};

return [
    'summarizes ODT package path depths across compact and rich package provenance' => static function (TestRunner $t) use (
        $buildPackage,
        $indexByDepth,
        $manifestXml
    ): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $documentIdentity = $documentProvenance['packageIdentity'];

        $expectedDepthTwo = [
            'packagePathDepth' => 2,
            'entryCount' => 4,
            'fileEntryCount' => 4,
            'directoryEntryCount' => 0,
            'byteLength' => strlen($manifestXml) + strlen('PNGDATA') + strlen('<svg/>') + strlen('PRIVATE'),
            'compressedByteLength' => strlen($manifestXml) + strlen('PNGDATA') + strlen('<svg/>') + strlen('PRIVATE'),
            'declaredEntryCount' => 2,
            'undeclaredEntryCount' => 1,
            'exposableEntryCount' => 2,
            'blockedEntryCount' => 2,
            'packageAreaCounts' => [
                'META-INF/' => 1,
                'Notes/' => 1,
                'Pictures/' => 2,
            ],
            'roleCounts' => [
                'manifest-declared' => 2,
                'media-resource' => 2,
                'odf-manifest' => 1,
                'undeclared-package-entry' => 1,
            ],
            'byteExposurePolicyCounts' => [
                'package-bytes-exposable' => 2,
                'undeclared-package-entry-no-bytes' => 1,
            ],
            'packagePaths' => [
                'META-INF/manifest.xml',
                'Notes/private',
                'Pictures/HERO.PNG',
                'Pictures/no-extension',
            ],
        ];
        $expectedDepthThree = [
            'packagePathDepth' => 3,
            'entryCount' => 2,
            'fileEntryCount' => 2,
            'directoryEntryCount' => 0,
            'byteLength' => strlen('<script/>') + strlen('<accel/>'),
            'compressedByteLength' => strlen('<script/>') + strlen('<accel/>'),
            'declaredEntryCount' => 2,
            'undeclaredEntryCount' => 0,
            'exposableEntryCount' => 0,
            'blockedEntryCount' => 2,
            'packageAreaCounts' => [
                'Basic/' => 1,
                'Configurations2/' => 1,
            ],
            'roleCounts' => [
                'configuration-package' => 1,
                'manifest-declared' => 2,
                'script-package' => 1,
            ],
            'byteExposurePolicyCounts' => [
                'configuration-package-bytes-blocked' => 1,
                'script-package-bytes-blocked' => 1,
            ],
            'packagePaths' => [
                'Basic/Standard/Review.xml',
                'Configurations2/accelerator/current.xml',
            ],
        ];

        $handoffs = [
            $compactInventory,
            $compactIdentity,
            $richProvenance,
            $richIdentity,
            $documentProvenance,
            $documentIdentity,
        ];

        foreach ($handoffs as $handoff) {
            $summariesByDepth = $indexByDepth($handoff['packagePathDepthSummaries']);
            $t->same(3, $handoff['packagePathDepthSummaryCount']);
            $t->same([1, 2, 3], array_keys($summariesByDepth));
            $t->same($handoff['packagePathDepthCounts'][2], $summariesByDepth[2]['entryCount']);
            $t->same($handoff['packagePathDepthCounts'][3], $summariesByDepth[3]['entryCount']);
            $t->same($expectedDepthTwo, $summariesByDepth[2]);
            $t->same($expectedDepthThree, $summariesByDepth[3]);
        }

        $t->same($compactInventory['packagePathDepthSummaries'], $compactIdentity['packagePathDepthSummaries']);
        $t->same($richProvenance['packagePathDepthSummaries'], $richIdentity['packagePathDepthSummaries']);
        $t->same($richProvenance['packagePathDepthSummaries'], $documentProvenance['packagePathDepthSummaries']);
        $t->same($richIdentity['packagePathDepthSummaries'], $documentIdentity['packagePathDepthSummaries']);
        $t->same($compactInventory['packagePathDepthSummaryCount'], $richProvenance['packagePathDepthSummaryCount']);
        $t->same($compactInventory['packagePathDepthSummaries'], $richProvenance['packagePathDepthSummaries']);
    },
];
