<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/nameless.bin"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Module1.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Notes/"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Manifest segment family paragraph.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="SegmentFamilyBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$buildPackage = static fn (?string $manifest = null): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifest ?? $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/nameless.bin', 'data' => 'NAMELESS', 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Module1.xml', 'data' => '<script:module/>', 'compressionMethod' => 0],
    ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accel/>', 'compressionMethod' => 0],
    ['name' => 'Notes/', 'data' => '', 'compressionMethod' => 0],
], 'odf manifest path segment position family buckets');

$expectedFamilyCounts = [
    'first' => ['configuration' => 1, 'image' => 1, 'missing-media-type' => 1, 'script' => 1],
    'last' => ['configuration' => 1, 'image' => 1, 'missing-media-type' => 1, 'script' => 1],
    'middle' => ['configuration' => 1, 'script' => 1],
    'only' => ['directory' => 1, 'xml' => 2],
];

$expectedFamilyPaths = [
    'first' => [
        'configuration' => ['Configurations2/accelerator/current.xml'],
        'image' => ['Pictures/hero.png'],
        'missing-media-type' => ['Pictures/nameless.bin'],
        'script' => ['Basic/Standard/Module1.xml'],
    ],
    'last' => [
        'configuration' => ['Configurations2/accelerator/current.xml'],
        'image' => ['Pictures/hero.png'],
        'missing-media-type' => ['Pictures/nameless.bin'],
        'script' => ['Basic/Standard/Module1.xml'],
    ],
    'middle' => [
        'configuration' => ['Configurations2/accelerator/current.xml'],
        'script' => ['Basic/Standard/Module1.xml'],
    ],
    'only' => [
        'directory' => ['Notes/'],
        'xml' => ['content.xml', 'styles.xml'],
    ],
];

$expectedPolicyCounts = [
    'first' => [
        'configuration-package-bytes-blocked' => 1,
        'missing-media-type-bytes-blocked' => 1,
        'package-bytes-exposable' => 1,
        'script-package-bytes-blocked' => 1,
    ],
    'last' => [
        'configuration-package-bytes-blocked' => 1,
        'missing-media-type-bytes-blocked' => 1,
        'package-bytes-exposable' => 1,
        'script-package-bytes-blocked' => 1,
    ],
    'middle' => [
        'configuration-package-bytes-blocked' => 1,
        'script-package-bytes-blocked' => 1,
    ],
    'only' => [
        'directory-entry-no-bytes' => 1,
        'package-bytes-exposable' => 2,
    ],
];

$expectedPolicyPaths = [
    'first' => [
        'configuration-package-bytes-blocked' => ['Configurations2/accelerator/current.xml'],
        'missing-media-type-bytes-blocked' => ['Pictures/nameless.bin'],
        'package-bytes-exposable' => ['Pictures/hero.png'],
        'script-package-bytes-blocked' => ['Basic/Standard/Module1.xml'],
    ],
    'last' => [
        'configuration-package-bytes-blocked' => ['Configurations2/accelerator/current.xml'],
        'missing-media-type-bytes-blocked' => ['Pictures/nameless.bin'],
        'package-bytes-exposable' => ['Pictures/hero.png'],
        'script-package-bytes-blocked' => ['Basic/Standard/Module1.xml'],
    ],
    'middle' => [
        'configuration-package-bytes-blocked' => ['Configurations2/accelerator/current.xml'],
        'script-package-bytes-blocked' => ['Basic/Standard/Module1.xml'],
    ],
    'only' => [
        'directory-entry-no-bytes' => ['Notes/'],
        'package-bytes-exposable' => ['content.xml', 'styles.xml'],
    ],
];

return [
    'records mapped odf manifest path segment position family case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedOdfManifestPathSegmentPositionFamilyCases'] ?? null);
        $t->same(39, $manifest['odfManifestPathSegmentPositionFamilyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedOdfManifestPathSegmentPositionFamilyCases'] ?? null);
        $t->same(39, $manifest['benchmarkDenominator']['breakdown']['odfManifestPathSegmentPositionFamilyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedOdfManifestPathSegmentPositionFamilyCases'] ?? null);
        $t->same(39, $manifest['benchmarkDenominator']['inventory']['odfManifestPathSegmentPositionFamilyAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedOdfManifestPathSegmentPositionFamilyCases'] ?? null);
        $t->same(39, $manifest['inventory']['odfManifestPathSegmentPositionFamilyAssertions'] ?? null);
    },

    'rolls ODT manifest path segment positions into media family and byte policy buckets' => static function (TestRunner $t) use (
        $buildPackage,
        $expectedFamilyCounts,
        $expectedFamilyPaths,
        $expectedPolicyCounts,
        $expectedPolicyPaths,
        $manifestXml,
        $contentXml,
        $stylesXml
    ): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactReview = $compactSummary['manifestReview'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        foreach ([$compactReview, $compactIdentity, $richProvenance, $richIdentity, $documentIdentity] as $handoff) {
            $t->same($expectedFamilyCounts, $handoff['manifestPathSegmentPositionMediaFamilyCounts']);
            $t->same($expectedFamilyPaths, $handoff['fullPathsByManifestPathSegmentPositionMediaFamily']);
            $t->same($expectedPolicyCounts, $handoff['manifestPathSegmentPositionByteExposurePolicyCounts']);
            $t->same($expectedPolicyPaths, $handoff['fullPathsByManifestPathSegmentPositionByteExposurePolicy']);
        }

        $t->same($compactReview['manifestPathSegmentPositionMediaFamilyCounts'], $richProvenance['manifestPathSegmentPositionMediaFamilyCounts']);
        $t->same($compactReview['fullPathsByManifestPathSegmentPositionMediaFamily'], $richProvenance['fullPathsByManifestPathSegmentPositionMediaFamily']);
        $t->same($compactIdentity['manifestPathSegmentPositionByteExposurePolicyCounts'], $documentIdentity['manifestPathSegmentPositionByteExposurePolicyCounts']);
        $t->same($compactIdentity['fullPathsByManifestPathSegmentPositionByteExposurePolicy'], $richIdentity['fullPathsByManifestPathSegmentPositionByteExposurePolicy']);
        $t->same(['Basic/Standard/Module1.xml'], $richProvenance['fullPathsByManifestPathSegmentPositionMediaFamily']['middle']['script']);
        $t->same(['Pictures/hero.png'], $richIdentity['fullPathsByManifestPathSegmentPositionMediaFamily']['last']['image']);
        $t->same(['Pictures/nameless.bin'], $richProvenance['fullPathsByManifestPathSegmentPositionByteExposurePolicy']['last']['missing-media-type-bytes-blocked']);
        $t->same(['Configurations2/accelerator/current.xml'], $documentIdentity['fullPathsByManifestPathSegmentPositionByteExposurePolicy']['first']['configuration-package-bytes-blocked']);

        $renamedManifest = str_replace('Pictures/hero.png', 'Pictures/covers/hero.png', $manifestXml);
        $renamedPackage = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $renamedManifest, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
            ['name' => 'Pictures/covers/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Pictures/nameless.bin', 'data' => 'NAMELESS', 'compressionMethod' => 0],
            ['name' => 'Basic/Standard/Module1.xml', 'data' => '<script:module/>', 'compressionMethod' => 0],
            ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accel/>', 'compressionMethod' => 0],
            ['name' => 'Notes/', 'data' => '', 'compressionMethod' => 0],
        ], 'renamed odf manifest path segment position family buckets');
        $renamedIdentity = OpenDocumentPackage::fromPackage($renamedPackage)->summarize()['packageIdentity'];
        $t->same(false, $compactIdentity['identitySha256'] === $renamedIdentity['identitySha256']);
        $t->same(['Pictures/covers/hero.png'], $renamedIdentity['fullPathsByManifestPathSegmentPositionMediaFamily']['middle']['image']);

        $encoded = json_encode($richProvenance['manifestPathSegmentPositionMediaFamilyCounts'], JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encoded, 'Manifest segment family paragraph.'));
    },
];
