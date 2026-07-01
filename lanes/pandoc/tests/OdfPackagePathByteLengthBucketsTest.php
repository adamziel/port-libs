<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$widePath = 'Review/settings/settings-large-name.xml';
$longPath = 'LongPaths/previews/review-preview-name-that-exceeds-sixty-four-characters.png';

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="$widePath" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="$longPath" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Path byte length review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:styles/>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Path Byte Length Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:settings/>
</office:document-settings>
XML;

$parts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml],
    ['name' => 'styles.xml', 'data' => $stylesXml],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'settings.xml', 'data' => $settingsXml],
    ['name' => 'Pictures/review.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => $widePath, 'data' => '<review:settings xmlns:review="urn:wordpress:review"/>', 'compressionMethod' => 0],
    ['name' => $longPath, 'data' => 'LONGPATHPNG', 'compressionMethod' => 0],
];

$package = static fn (): ZipPackage => ZipPackage::fromParts($parts, 'odt path byte length bucket review');

$summariesByBucket = static function (array $summaries): array {
    $byBucket = [];
    foreach ($summaries as $summary) {
        $byBucket[$summary['packagePathByteLengthBucket']] = $summary;
    }

    return $byBucket;
};

$entriesByKey = static function (array $entries, string $key): array {
    $byKey = [];
    foreach ($entries as $entry) {
        $byKey[$entry[$key]] = $entry;
    }

    return $byKey;
};

return [
    'summarizes ODT package path byte length buckets in compact and rich package identities' => static function (TestRunner $t) use ($package, $widePath, $longPath, $summariesByBucket, $entriesByKey): void {
        $expectedBucketNames = [
            'up-to-8-bytes',
            '9-to-16-bytes',
            '17-to-32-bytes',
            '33-to-64-bytes',
            'over-64-bytes',
        ];
        $expectedBucketCounts = [
            'up-to-8-bytes' => 2,
            '9-to-16-bytes' => 3,
            '17-to-32-bytes' => 2,
            '33-to-64-bytes' => 1,
            'over-64-bytes' => 1,
        ];
        $expectedNamesByBucket = [
            'up-to-8-bytes' => ['meta.xml', 'mimetype'],
            '9-to-16-bytes' => ['content.xml', 'settings.xml', 'styles.xml'],
            '17-to-32-bytes' => ['META-INF/manifest.xml', 'Pictures/review.png'],
            '33-to-64-bytes' => [$widePath],
            'over-64-bytes' => [$longPath],
        ];

        $compact = OpenDocumentPackage::fromPackage($package())->summarize();
        $compactInventory = $compact['packageInventory'];
        $rich = (new OdfReader())->readPackage($package());
        $provenance = $rich['importReport']['manifest']['packageProvenance'];

        $t->same($expectedBucketNames, $compactInventory['packagePathByteLengthBuckets']);
        $t->same($expectedBucketNames, $provenance['packagePathByteLengthBuckets']);
        $t->same($expectedBucketCounts, $compactInventory['packagePathByteLengthBucketCounts']);
        $t->same($expectedBucketCounts, $provenance['packagePathByteLengthBucketCounts']);
        $t->same($expectedNamesByBucket, $compactInventory['entryNamesByPackagePathByteLengthBucket']);
        $t->same($expectedNamesByBucket, $provenance['entryNamesByPackagePathByteLengthBucket']);
        $t->same($provenance, $rich['document']->attr('manifest')['packageProvenance']);

        $compactParts = $compactInventory['parts'];
        $richParts = $provenance['parts'];
        foreach ([$compactParts, $richParts] as $partsByName) {
            $t->same(strlen($widePath), $partsByName[$widePath]['packagePathByteLength']);
            $t->same('33-to-64-bytes', $partsByName[$widePath]['packagePathByteLengthBucket']);
            $t->same(33, $partsByName[$widePath]['packagePathByteLengthBucketMin']);
            $t->same(64, $partsByName[$widePath]['packagePathByteLengthBucketMax']);
            $t->same(strlen($longPath), $partsByName[$longPath]['packagePathByteLength']);
            $t->same('over-64-bytes', $partsByName[$longPath]['packagePathByteLengthBucket']);
            $t->same(65, $partsByName[$longPath]['packagePathByteLengthBucketMin']);
            $t->same(null, $partsByName[$longPath]['packagePathByteLengthBucketMax']);
        }

        $compactSummaries = $summariesByBucket($compactInventory['packagePathByteLengthBucketSummaries']);
        $richSummaries = $summariesByBucket($provenance['packagePathByteLengthBucketSummaries']);
        $t->same(5, $compactInventory['packagePathByteLengthBucketCount']);
        $t->same(5, $provenance['packagePathByteLengthBucketCount']);
        $t->same(2, $richSummaries['up-to-8-bytes']['partCount']);
        $t->same(['meta.xml', 'mimetype'], $richSummaries['up-to-8-bytes']['entryNames']);
        $t->same(1, $richSummaries['33-to-64-bytes']['partCount']);
        $t->same($widePath, $richSummaries['33-to-64-bytes']['longestPart']['part']);
        $t->same(strlen($longPath), $richSummaries['over-64-bytes']['longestPackagePathByteLength']);
        $t->same($longPath, $richSummaries['over-64-bytes']['longestPart']['part']);
        $t->same($longPath, $compactSummaries['over-64-bytes']['longestPart']['path']);
        $t->same(2, $provenance['packagePathByteLengthRoleCounts']['media-resource']);
        $t->same([$longPath, 'Pictures/review.png'], $provenance['entryNamesByPackagePathByteLengthRole']['media-resource']);

        $compactIdentityEntries = $entriesByKey($compact['packageIdentity']['packageEntries'], 'path');
        $richIdentityEntries = $entriesByKey($provenance['packageIdentity']['packageEntries'], 'part');
        $t->same($expectedBucketCounts, $compact['packageIdentity']['packagePathByteLengthBucketCounts']);
        $t->same($expectedBucketCounts, $provenance['packageIdentity']['packagePathByteLengthBucketCounts']);
        $t->same($expectedNamesByBucket, $compact['packageIdentity']['entryNamesByPackagePathByteLengthBucket']);
        $t->same($expectedNamesByBucket, $provenance['packageIdentity']['entryNamesByPackagePathByteLengthBucket']);
        $t->same(strlen($longPath), $compactIdentityEntries[$longPath]['packagePathByteLength']);
        $t->same('over-64-bytes', $compactIdentityEntries[$longPath]['packagePathByteLengthBucket']);
        $t->same(65, $compactIdentityEntries[$longPath]['packagePathByteLengthBucketMin']);
        $t->same(null, $compactIdentityEntries[$longPath]['packagePathByteLengthBucketMax'] ?? null);
        $t->same(strlen($widePath), $richIdentityEntries[$widePath]['packagePathByteLength']);
        $t->same('33-to-64-bytes', $richIdentityEntries[$widePath]['packagePathByteLengthBucket']);
        $t->same(33, $richIdentityEntries[$widePath]['packagePathByteLengthBucketMin']);
        $t->same(64, $richIdentityEntries[$widePath]['packagePathByteLengthBucketMax']);
    },
];
