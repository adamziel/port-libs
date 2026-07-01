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
  <manifest:file-entry manifest:full-path="Pictures/HERO.PNG" manifest:media-type="image/png" manifest:size="7"/>
  <manifest:file-entry manifest:full-path="Pictures/no-extension" manifest:media-type="image/svg+xml" manifest:size="6"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml" manifest:size="15"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml" manifest:size="8"/>
  <manifest:file-entry manifest:full-path="layout-cache" manifest:media-type="application/binary" manifest:size="11">
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
      <text:p>Package extension review.</text:p>
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
    <dc:title>Package Extension Review</dc:title>
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
], 'odt package extension provenance');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT package part extensions across compact and rich package provenance' => static function (TestRunner $t) use ($buildPackage, $indexBy): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];

        $compactExtensions = $indexBy($compactInventory['packagePartExtensionSummaries'], 'extensionKey');
        $richExtensions = $indexBy($richProvenance['packagePartExtensionSummaries'], 'extensionKey');
        $compactParts = $compactInventory['parts'];
        $richParts = $richProvenance['parts'];
        $identity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];
        $identityParts = [];
        foreach ($identity['packageEntries'] as $entry) {
            $identityParts[$entry['part']] = $entry;
        }

        $expectedCounts = [
            '(none)' => 4,
            'png' => 1,
            'xml' => 6,
        ];
        $expectedPathKindCounts = [
            'file' => 11,
        ];
        $expectedTopLevelSegmentCounts = [
            'Basic' => 1,
            'Configurations2' => 1,
            'META-INF' => 1,
            'Notes' => 1,
            'Pictures' => 2,
            'content.xml' => 1,
            'layout-cache' => 1,
            'meta.xml' => 1,
            'mimetype' => 1,
            'styles.xml' => 1,
        ];
        $expectedPathExtensionCounts = [
            'png' => 1,
            'xml' => 6,
        ];
        $expectedRoleByteExposurePolicyCounts = [
            'configuration-package' => [
                'configuration-package-bytes-blocked' => 1,
            ],
            'layout-cache' => [
                'encrypted-resource-bytes-blocked' => 1,
            ],
            'manifest-declared' => [
                'configuration-package-bytes-blocked' => 1,
                'encrypted-resource-bytes-blocked' => 1,
                'package-bytes-exposable' => 5,
                'script-package-bytes-blocked' => 1,
            ],
            'media-resource' => [
                'package-bytes-exposable' => 2,
            ],
            'odf-content' => [
                'package-bytes-exposable' => 1,
            ],
            'odf-meta' => [
                'package-bytes-exposable' => 1,
            ],
            'odf-styles' => [
                'package-bytes-exposable' => 1,
            ],
            'script-package' => [
                'script-package-bytes-blocked' => 1,
            ],
            'undeclared-package-entry' => [
                'undeclared-package-entry-no-bytes' => 1,
            ],
        ];

        $t->same($expectedCounts, $compactInventory['packagePartExtensionCounts']);
        $t->same($expectedCounts, $richProvenance['packagePartExtensionCounts']);
        $t->same($expectedCounts, $identity['packagePartExtensionCounts']);
        $t->same($expectedPathKindCounts, $compactInventory['packagePathKindCounts']);
        $t->same($expectedPathKindCounts, $richProvenance['packagePathKindCounts']);
        $t->same($expectedPathKindCounts, $identity['packagePathKindCounts']);
        $t->same($expectedTopLevelSegmentCounts, $compactInventory['packageTopLevelSegmentCounts']);
        $t->same($expectedTopLevelSegmentCounts, $richProvenance['packageTopLevelSegmentCounts']);
        $t->same($expectedTopLevelSegmentCounts, $identity['packageTopLevelSegmentCounts']);
        $t->same($expectedPathExtensionCounts, $compactInventory['packagePathExtensionCounts']);
        $t->same($expectedPathExtensionCounts, $richProvenance['packagePathExtensionCounts']);
        $t->same($expectedPathExtensionCounts, $identity['packagePathExtensionCounts']);
        $t->same($expectedRoleByteExposurePolicyCounts, $compactInventory['packageRoleByteExposurePolicyCounts']);
        $t->same($expectedRoleByteExposurePolicyCounts, $compactIdentity['packageRoleByteExposurePolicyCounts']);
        $t->same($expectedRoleByteExposurePolicyCounts, $richProvenance['packageRoleByteExposurePolicyCounts']);
        $t->same($expectedRoleByteExposurePolicyCounts, $identity['packageRoleByteExposurePolicyCounts']);
        $t->same($expectedRoleByteExposurePolicyCounts, $documentIdentity['packageRoleByteExposurePolicyCounts']);
        foreach ([
            'packageAreaSummaries',
            'packagePathsByPackageArea',
            'packagePathsByPathDepth',
        ] as $key) {
            $t->same($compactInventory[$key], $compactIdentity[$key], "compact identity {$key}");
            $t->same($richProvenance[$key], $identity[$key], "rich identity {$key}");
            $t->same($identity[$key], $documentIdentity[$key], "document identity {$key}");
        }
        $t->same($compactInventory['packagePathDepthCounts'], $compactIdentity['packagePathDepthCounts']);
        $t->same($richProvenance['packagePathDepthCounts'], $identity['packagePathDepthCounts']);
        $t->same($identity['packagePathDepthCounts'], $documentIdentity['packagePathDepthCounts']);
        $t->same($compactInventory['maxPackagePathDepth'], $compactIdentity['maxPackagePathDepth']);
        $t->same($richProvenance['maxPackagePathDepth'], $identity['maxPackagePathDepth']);
        $t->same('Pictures/', $identityParts['Pictures/HERO.PNG']['packageArea']);
        $t->same('Basic/', $identityParts['Basic/Standard/Review.xml']['packageArea']);
        $t->same(3, $identityParts['Basic/Standard/Review.xml']['packagePathDepth']);
        $t->true(in_array('Pictures/HERO.PNG', $identity['packagePathsByPackageArea']['Pictures/'] ?? [], true));
        $t->true(in_array('Basic/Standard/Review.xml', $identity['packagePathsByPackageArea']['Basic/'] ?? [], true));
        $t->true(in_array('Basic/Standard/Review.xml', $identity['packagePathsByPathDepth'][3] ?? [], true));
        $t->same(4, $compactInventory['extensionlessPackagePartCount']);
        $t->same(4, $richProvenance['extensionlessPackagePartCount']);
        $t->same(4, $identity['extensionlessPackagePartCount']);
        $t->same(3, $compactInventory['packagePartExtensionSummaryCount']);
        $t->same(3, $richProvenance['packagePartExtensionSummaryCount']);

        $t->same([
            'Notes/private',
            'Pictures/no-extension',
            'layout-cache',
            'mimetype',
        ], $compactInventory['entryNamesByPackagePartExtension']['(none)']);
        $t->same($compactInventory['entryNamesByPackagePartExtension'], $richProvenance['entryNamesByPackagePartExtension']);

        $t->same('png', $compactParts['Pictures/HERO.PNG']['packagePartExtension']);
        $t->same('PNG', $compactParts['Pictures/HERO.PNG']['rawPackagePartExtension']);
        $t->same(true, $compactParts['Pictures/HERO.PNG']['packagePartExtensionHasUppercase']);
        $t->same(true, $compactParts['Pictures/HERO.PNG']['packagePartExtensionWasNormalized']);
        $t->same('file', $richParts['Pictures/HERO.PNG']['packagePathShape']['kind']);
        $t->same('Pictures', $richParts['Pictures/HERO.PNG']['packagePathShape']['topLevelSegment']);
        $t->same('Pictures/', $richParts['Pictures/HERO.PNG']['packagePathShape']['directory']);
        $t->same('HERO.PNG', $richParts['Pictures/HERO.PNG']['packagePathShape']['basename']);
        $t->same('png', $richParts['Pictures/HERO.PNG']['packagePathShape']['extension']);
        $t->same(['Pictures', 'HERO.PNG'], $richParts['Pictures/HERO.PNG']['packagePathShape']['segments']);
        $t->same(2, $richParts['Pictures/HERO.PNG']['packagePathShape']['segmentCount']);
        $t->same(1, $richParts['Pictures/HERO.PNG']['packagePathShape']['directorySegmentCount']);
        $t->same('file', $richParts['Pictures/HERO.PNG']['packagePathKind']);
        $t->same('Pictures', $richParts['Pictures/HERO.PNG']['packageTopLevelSegment']);
        $t->same('Pictures/', $richParts['Pictures/HERO.PNG']['packageDirectory']);
        $t->same('HERO.PNG', $richParts['Pictures/HERO.PNG']['packageBasename']);
        $t->same($compactParts['Pictures/HERO.PNG']['packagePartExtension'], $richParts['Pictures/HERO.PNG']['packagePartExtension']);
        $t->same($compactParts['Pictures/HERO.PNG']['rawPackagePartExtension'], $richParts['Pictures/HERO.PNG']['rawPackagePartExtension']);

        $t->same(true, $compactParts['Pictures/no-extension']['extensionlessPackagePart']);
        $t->same(true, $compactParts['Pictures/no-extension']['canExposeBytes']);
        $t->same('package-bytes-exposable', $compactParts['Pictures/no-extension']['byteExposurePolicy']);
        $t->same(true, $richParts['Pictures/no-extension']['extensionlessPackagePart']);

        $t->same(true, $compactParts['Notes/private']['extensionlessPackagePart']);
        $t->same(true, $compactParts['Notes/private']['undeclared']);
        $t->same('undeclared-package-entry-no-bytes', $compactParts['Notes/private']['byteExposurePolicy']);
        $t->same(true, $richParts['Notes/private']['undeclared']);

        $t->same(true, $compactParts['layout-cache']['extensionlessPackagePart']);
        $t->same(true, $compactParts['layout-cache']['encrypted']);
        $t->same('encrypted-resource-bytes-blocked', $compactParts['layout-cache']['byteExposurePolicy']);
        $t->same('encrypted-resource-bytes-blocked', $richParts['layout-cache']['byteExposurePolicy']);

        $none = $compactExtensions['(none)'];
        $t->same(4, $none['partCount']);
        $t->same(4, $none['extensionlessPackagePartCount']);
        $t->same(2, $none['declaredPartCount']);
        $t->same(1, $none['undeclaredPartCount']);
        $t->same(1, $none['encryptedPartCount']);
        $t->same(1, $none['exposablePartCount']);
        $t->same(3, $none['blockedPartCount']);
        $t->same('mimetype', $none['largestPart']['path']);
        $t->same(['layout-cache' => 1, 'manifest-declared' => 2, 'media-resource' => 1, 'odf-mimetype' => 1, 'undeclared-package-entry' => 1], $none['roleCounts']);
        $t->same($none['roleCounts'], $richExtensions['(none)']['roleCounts']);

        $png = $compactExtensions['png'];
        $t->same(1, $png['partCount']);
        $t->same(0, $png['extensionlessPackagePartCount']);
        $t->same(['manifest-declared' => 1, 'media-resource' => 1], $png['roleCounts']);
        $t->same('Pictures/HERO.PNG', $png['largestPart']['path']);

        $xml = $compactExtensions['xml'];
        $t->same(6, $xml['partCount']);
        $t->same(5, $xml['declaredPartCount']);
        $t->same(0, $xml['undeclaredPartCount']);
        $t->same([
            'configuration-package' => 1,
            'manifest-declared' => 5,
            'odf-content' => 1,
            'odf-manifest' => 1,
            'odf-meta' => 1,
            'odf-styles' => 1,
            'script-package' => 1,
        ], $xml['roleCounts']);
        $t->same($xml['roleCounts'], $richExtensions['xml']['roleCounts']);

        $t->same('png', $identityParts['Pictures/HERO.PNG']['packagePartExtension']);
        $t->same('PNG', $identityParts['Pictures/HERO.PNG']['rawPackagePartExtension']);
        $t->same(true, $identityParts['Pictures/HERO.PNG']['packagePartExtensionWasNormalized']);
        $t->same(true, $identityParts['layout-cache']['extensionlessPackagePart']);
        $t->same('layout-cache', $identityParts['layout-cache']['packagePathShape']['basename']);
        $t->same('layout-cache', $identityParts['layout-cache']['packageBasename']);
        $t->same('Notes', $identityParts['Notes/private']['packageTopLevelSegment']);
        $t->same('private', $identityParts['Notes/private']['packagePathShape']['basename']);
        $t->same(true, $identityParts['Notes/private']['undeclared']);
        $t->same($richProvenance, $richResult['document']->attr('manifest')['packageProvenance']);
    },
];
