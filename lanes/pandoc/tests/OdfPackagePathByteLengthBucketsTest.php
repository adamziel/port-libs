<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$longPrivatePart = 'Notes/private-review-path-that-is-longer-than-sixty-four-characters.txt';
$configurationPart = 'Configurations2/accelerator/current.xml';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="7"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml" manifest:size="8"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package path byte-length buckets.</text:p>
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
    <dc:title>Package path byte-length buckets</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => $configurationPart, 'data' => '<accel/>', 'compressionMethod' => 0],
    ['name' => $longPrivatePart, 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odt package path byte-length buckets');

return [
    'summarizes ODT package path byte-length buckets across compact and rich package provenance' => static function (TestRunner $t) use (
        $buildPackage,
        $configurationPart,
        $longPrivatePart
    ): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedBuckets = [
            'up-to-8-bytes',
            '9-to-16-bytes',
            '17-to-32-bytes',
            '33-to-64-bytes',
            'over-64-bytes',
        ];
        $expectedBucketCounts = [
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
            '17-to-32-bytes' => [
                'package-bytes-exposable' => 1,
            ],
            '33-to-64-bytes' => [
                'configuration-package-bytes-blocked' => 1,
            ],
            '9-to-16-bytes' => [
                'package-bytes-exposable' => 2,
            ],
            'over-64-bytes' => [
                'undeclared-package-entry-no-bytes' => 1,
            ],
            'up-to-8-bytes' => [
                'package-bytes-exposable' => 1,
            ],
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(5, $handoff['packagePathByteLengthBucketCount']);
            $t->same($expectedBuckets, $handoff['packagePathByteLengthBuckets']);
            $t->same($expectedBucketCounts, $handoff['packagePathByteLengthBucketCounts']);
            $t->same($expectedEntryNames, $handoff['entryNamesByPackagePathByteLengthBucket']);
            $t->same($expectedRoleCounts, $handoff['packagePathByteLengthRoleCounts']);
            $t->same($expectedPolicyCounts, $handoff['packagePathByteLengthByteExposurePolicyCounts']);

            $summaries = [];
            foreach ($handoff['packagePathByteLengthBucketSummaries'] as $summary) {
                $summaries[$summary['packagePathByteLengthBucket']] = $summary;
            }
            $t->same($configurationPart, $summaries['33-to-64-bytes']['longestEntryName']);
            $t->same(strlen($configurationPart), $summaries['33-to-64-bytes']['longestPackagePathByteLength']);
            $t->same($longPrivatePart, $summaries['over-64-bytes']['longestEntryName']);
            $t->same(strlen($longPrivatePart), $summaries['over-64-bytes']['longestPackagePathByteLength']);
            $t->same(1, $summaries['over-64-bytes']['undeclaredEntryCount']);
            $t->same(['undeclared-package-entry'], $summaries['over-64-bytes']['roles']);
        }

        $compactIdentityEntries = [];
        foreach ($compactIdentity['packageEntries'] as $entry) {
            $compactIdentityEntries[$entry['path']] = $entry;
        }
        $richIdentityEntries = [];
        foreach ($richIdentity['packageEntries'] as $entry) {
            $richIdentityEntries[$entry['part']] = $entry;
        }

        $t->same(strlen($longPrivatePart), $compactInventory['parts'][$longPrivatePart]['packagePathByteLength']);
        $t->same('over-64-bytes', $compactInventory['parts'][$longPrivatePart]['packagePathByteLengthBucket']);
        $t->same(strlen($longPrivatePart), $compactIdentityEntries[$longPrivatePart]['packagePathByteLength']);
        $t->same('over-64-bytes', $compactIdentityEntries[$longPrivatePart]['packagePathByteLengthBucket']);
        $t->same(strlen($longPrivatePart), $richProvenance['parts'][$longPrivatePart]['packagePathByteLength']);
        $t->same('over-64-bytes', $richIdentityEntries[$longPrivatePart]['packagePathByteLengthBucket']);
    },
];
