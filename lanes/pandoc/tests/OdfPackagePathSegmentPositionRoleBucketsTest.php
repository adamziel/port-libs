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
      <text:p>Package path segment-position role buckets.</text:p>
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
    <dc:title>Package path segment-position role buckets</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
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
], 'odt package path segment-position role buckets');

return [
    'summarizes ODT package path segment positions by package role and byte exposure' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $documentIdentity = $documentProvenance['packageIdentity'];

        $expectedRoleCounts = [
            'first' => [
                'configuration-package' => 1,
                'manifest-declared' => 4,
                'media-resource' => 2,
                'odf-manifest' => 1,
                'script-package' => 1,
                'undeclared-package-entry' => 1,
            ],
            'last' => [
                'configuration-package' => 1,
                'manifest-declared' => 4,
                'media-resource' => 2,
                'odf-manifest' => 1,
                'script-package' => 1,
                'undeclared-package-entry' => 1,
            ],
            'middle' => [
                'configuration-package' => 1,
                'manifest-declared' => 2,
                'script-package' => 1,
            ],
            'only' => [
                'layout-cache' => 1,
                'manifest-declared' => 4,
                'odf-content' => 1,
                'odf-meta' => 1,
                'odf-mimetype' => 1,
                'odf-styles' => 1,
            ],
        ];
        $expectedPolicyCounts = [
            'first' => [
                'configuration-package-bytes-blocked' => 1,
                'package-bytes-exposable' => 2,
                'script-package-bytes-blocked' => 1,
                'undeclared-package-entry-no-bytes' => 1,
            ],
            'last' => [
                'configuration-package-bytes-blocked' => 1,
                'package-bytes-exposable' => 2,
                'script-package-bytes-blocked' => 1,
                'undeclared-package-entry-no-bytes' => 1,
            ],
            'middle' => [
                'configuration-package-bytes-blocked' => 1,
                'script-package-bytes-blocked' => 1,
            ],
            'only' => [
                'encrypted-resource-bytes-blocked' => 1,
                'package-bytes-exposable' => 3,
            ],
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentIdentity] as $handoff) {
            $t->same($expectedRoleCounts, $handoff['packagePathSegmentPositionRoleCounts']);
            $t->same($expectedPolicyCounts, $handoff['packagePathSegmentPositionByteExposurePolicyCounts']);
            $t->same(
                ['Basic/Standard/Review.xml', 'Configurations2/accelerator/current.xml'],
                $handoff['entryNamesByPackagePathSegmentPositionRole']['middle']['manifest-declared']
            );
            $t->same(
                ['Configurations2/accelerator/current.xml'],
                $handoff['entryNamesByPackagePathSegmentPositionRole']['middle']['configuration-package']
            );
            $t->same(
                ['Notes/private'],
                $handoff['entryNamesByPackagePathSegmentPositionByteExposurePolicy']['first']['undeclared-package-entry-no-bytes']
            );
            $t->same(
                ['layout-cache'],
                $handoff['entryNamesByPackagePathSegmentPositionByteExposurePolicy']['only']['encrypted-resource-bytes-blocked']
            );
        }

        $t->same($compactInventory['packagePathSegmentPositionRoleCounts'], $compactIdentity['packagePathSegmentPositionRoleCounts']);
        $t->same($richProvenance['packagePathSegmentPositionRoleCounts'], $documentProvenance['packagePathSegmentPositionRoleCounts']);
        $t->same($compactIdentity['entryNamesByPackagePathSegmentPositionRole'], $richIdentity['entryNamesByPackagePathSegmentPositionRole']);
        $t->same($richIdentity['entryNamesByPackagePathSegmentPositionByteExposurePolicy'], $documentIdentity['entryNamesByPackagePathSegmentPositionByteExposurePolicy']);
    },
];
