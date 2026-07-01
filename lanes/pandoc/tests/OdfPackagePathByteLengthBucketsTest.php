<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$configurationPart = 'Configurations2/accelerator/current.xml';
$longPrivatePart = 'Notes/private-review-path-that-is-longer-than-sixty-four-characters.txt';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Package path byte length review.</text:p>
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
    <dc:title>Path Byte Length Buckets</dc:title>
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
    ['name' => $configurationPart, 'data' => '<accel:acceleratorlist/>', 'compressionMethod' => 0],
    ['name' => $longPrivatePart, 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odt package path byte length review');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT package path byte length buckets across compact and rich provenance' => static function (TestRunner $t) use ($buildPackage, $configurationPart, $longPrivatePart, $indexBy): void {
        $compact = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compact['packageInventory'];
        $compactIdentity = $compact['packageIdentity'];

        $rich = (new OdfReader())->readPackage($buildPackage());
        $provenance = $rich['importReport']['manifest']['packageProvenance'];
        $richIdentity = $provenance['packageIdentity'];
        $identityParts = $indexBy($richIdentity['packageEntries'], 'part');

        $expectedBuckets = [
            'up-to-8-bytes',
            '9-to-16-bytes',
            '17-to-32-bytes',
            '33-to-64-bytes',
            'over-64-bytes',
        ];
        $expectedCounts = [
            'up-to-8-bytes' => 2,
            '9-to-16-bytes' => 2,
            '17-to-32-bytes' => 2,
            '33-to-64-bytes' => 1,
            'over-64-bytes' => 1,
        ];
        $expectedEntryNames = [
            '17-to-32-bytes' => ['META-INF/manifest.xml', 'Pictures/hero.png'],
            '33-to-64-bytes' => [$configurationPart],
            '9-to-16-bytes' => ['content.xml', 'styles.xml'],
            'over-64-bytes' => [$longPrivatePart],
            'up-to-8-bytes' => ['meta.xml', 'mimetype'],
        ];
        $expectedRoleCounts = [
            '17-to-32-bytes' => [
                'manifest-declared' => 1,
                'media-resource' => 1,
                'odf-manifest' => 1,
            ],
            '33-to-64-bytes' => [
                'configuration-package' => 1,
                'manifest-declared' => 1,
            ],
            '9-to-16-bytes' => [
                'manifest-declared' => 2,
                'odf-content' => 1,
                'odf-styles' => 1,
            ],
            'over-64-bytes' => [
                'undeclared-package-entry' => 1,
            ],
            'up-to-8-bytes' => [
                'manifest-declared' => 1,
                'odf-meta' => 1,
                'odf-mimetype' => 1,
            ],
        ];
        $expectedPolicyCounts = [
            '17-to-32-bytes' => ['package-bytes-exposable' => 1],
            '33-to-64-bytes' => ['configuration-package-bytes-blocked' => 1],
            '9-to-16-bytes' => ['package-bytes-exposable' => 2],
            'over-64-bytes' => ['undeclared-package-entry-no-bytes' => 1],
            'up-to-8-bytes' => ['package-bytes-exposable' => 1],
        ];

        $t->same(5, $compactInventory['packagePathByteLengthBucketCount']);
        $t->same($expectedBuckets, $compactInventory['packagePathByteLengthBuckets']);
        $t->same($expectedCounts, $compactInventory['packagePathByteLengthBucketCounts']);
        $t->same($expectedEntryNames, $compactInventory['entryNamesByPackagePathByteLengthBucket']);
        $t->same($expectedRoleCounts, $compactInventory['packagePathByteLengthRoleCounts']);
        $t->same($expectedPolicyCounts, $compactInventory['packagePathByteLengthByteExposurePolicyCounts']);

        $t->same($compactInventory['packagePathByteLengthBucketCounts'], $compactIdentity['packagePathByteLengthBucketCounts']);
        $t->same($compactInventory['packagePathByteLengthRoleCounts'], $compactIdentity['packagePathByteLengthRoleCounts']);
        $t->same($compactInventory['packagePathByteLengthByteExposurePolicyCounts'], $compactIdentity['packagePathByteLengthByteExposurePolicyCounts']);

        $t->same($expectedBuckets, $provenance['packagePathByteLengthBuckets']);
        $t->same($expectedCounts, $provenance['packagePathByteLengthBucketCounts']);
        $t->same($expectedEntryNames, $provenance['entryNamesByPackagePathByteLengthBucket']);
        $t->same($expectedRoleCounts, $provenance['packagePathByteLengthRoleCounts']);
        $t->same($expectedPolicyCounts, $provenance['packagePathByteLengthByteExposurePolicyCounts']);
        $t->same($provenance, $rich['document']->attr('manifest')['packageProvenance']);

        $t->same(8, $provenance['parts']['mimetype']['packagePathByteLength']);
        $t->same('up-to-8-bytes', $provenance['parts']['mimetype']['packagePathByteLengthBucket']);
        $t->same(65, $provenance['parts'][$longPrivatePart]['packagePathByteLengthBucketMin']);
        $t->same(null, $provenance['parts'][$longPrivatePart]['packagePathByteLengthBucketMax']);
        $t->same('over-64-bytes', $identityParts[$longPrivatePart]['packagePathByteLengthBucket']);
        $t->same(strlen($configurationPart), $identityParts[$configurationPart]['packagePathByteLength']);

        $summaries = $indexBy($provenance['packagePathByteLengthBucketSummaries'], 'packagePathByteLengthBucket');
        $t->same(1, $summaries['33-to-64-bytes']['entryCount']);
        $t->same($configurationPart, $summaries['33-to-64-bytes']['longestEntryName']);
        $t->same(['configuration-package' => 1, 'manifest-declared' => 1], $summaries['33-to-64-bytes']['roleCounts']);
        $t->same(1, $summaries['over-64-bytes']['undeclaredEntryCount']);
        $t->same([$longPrivatePart], $summaries['over-64-bytes']['entryNames']);

        $t->same($provenance['packagePathByteLengthBucketCounts'], $richIdentity['packagePathByteLengthBucketCounts']);
        $t->same($provenance['entryNamesByPackagePathByteLengthRole'], $richIdentity['entryNamesByPackagePathByteLengthRole']);
        $t->same(false, isset($richIdentity['packagePathByteLengthBucketSummaries'][0]['contents']));
    },
];
