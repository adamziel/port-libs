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
  <manifest:file-entry manifest:full-path="Configurations2/statusbar/statusbar.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Thumbnails/thumbnail.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package directory base-name role buckets.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="DirectoryRoleReview" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Package Directory Base Name Role Buckets</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Pictures/HERO.PNG', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Configurations2/statusbar/statusbar.xml', 'data' => '<statusbar/>', 'compressionMethod' => 0],
    ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMB', 'compressionMethod' => 0],
    ['name' => 'Notes/private.txt', 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odt package directory base-name role buckets');

return [
    'summarizes ODT package directory base-name roles and byte exposure policies' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $documentIdentity = $documentProvenance['packageIdentity'];

        $expectedRoleCounts = [
            'META-INF' => [
                'odf-manifest' => 1,
            ],
            'Notes' => [
                'undeclared-package-entry' => 1,
            ],
            'Pictures' => [
                'manifest-declared' => 1,
                'media-resource' => 1,
                'zip-directory' => 1,
            ],
            'Thumbnails' => [
                'manifest-declared' => 1,
                'package-thumbnail' => 1,
            ],
            'statusbar' => [
                'configuration-package' => 1,
                'manifest-declared' => 1,
            ],
        ];
        $expectedPolicyCounts = [
            'Notes' => [
                'undeclared-package-entry-no-bytes' => 1,
            ],
            'Pictures' => [
                'package-bytes-exposable' => 1,
            ],
            'Thumbnails' => [
                'package-thumbnail-bytes-blocked' => 1,
            ],
            'statusbar' => [
                'configuration-package-bytes-blocked' => 1,
            ],
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance, $documentIdentity] as $handoff) {
            $t->same($expectedRoleCounts, $handoff['packageDirectoryBaseNameRoleCounts']);
            $t->same($expectedPolicyCounts, $handoff['packageDirectoryBaseNameByteExposurePolicyCounts']);
            $t->same(
                ['Configurations2/statusbar/statusbar.xml'],
                $handoff['entryNamesByPackageDirectoryBaseNameRole']['statusbar']['configuration-package']
            );
            $t->same(
                ['Thumbnails/thumbnail.png'],
                $handoff['entryNamesByPackageDirectoryBaseNameRole']['Thumbnails']['package-thumbnail']
            );
            $t->same(
                ['Notes/private.txt'],
                $handoff['entryNamesByPackageDirectoryBaseNameByteExposurePolicy']['Notes']['undeclared-package-entry-no-bytes']
            );
            $t->same(
                ['Thumbnails/thumbnail.png'],
                $handoff['entryNamesByPackageDirectoryBaseNameByteExposurePolicy']['Thumbnails']['package-thumbnail-bytes-blocked']
            );
        }

        $t->same($compactInventory['packageDirectoryBaseNameRoleCounts'], $richProvenance['packageDirectoryBaseNameRoleCounts']);
        $t->same($compactIdentity['entryNamesByPackageDirectoryBaseNameRole'], $richIdentity['entryNamesByPackageDirectoryBaseNameRole']);
    },
];
