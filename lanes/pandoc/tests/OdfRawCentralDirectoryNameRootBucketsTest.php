<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Raw central directory root buckets.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/thumbs/icon.png', 'data' => 'ICONDATA', 'compressionMethod' => 8],
    ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accel/>', 'compressionMethod' => 0],
], 'odf raw central directory root bucket review');

return [
    'records mapped odf raw central directory root bucket case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedOdfRawCentralDirectoryNameRootBucketCases'] ?? null);
        $t->same(40, $manifest['odfRawCentralDirectoryNameRootBucketAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedOdfRawCentralDirectoryNameRootBucketCases'] ?? null);
        $t->same(40, $manifest['benchmarkDenominator']['breakdown']['odfRawCentralDirectoryNameRootBucketAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedOdfRawCentralDirectoryNameRootBucketCases'] ?? null);
        $t->same(40, $manifest['benchmarkDenominator']['inventory']['odfRawCentralDirectoryNameRootBucketAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedOdfRawCentralDirectoryNameRootBucketCases'] ?? null);
        $t->same(40, $manifest['inventory']['odfRawCentralDirectoryNameRootBucketAssertions'] ?? null);
    },

    'summarizes raw ODT central directory name buckets by directory root' => static function (TestRunner $t) use ($buildPackage): void {
        $summary = OpenDocumentPackage::rawImportPreflight($buildPackage()->bytes());

        $bucketSummaries = [];
        foreach ($summary['rawCentralDirectoryNameByteLengthBucketSummaries'] as $bucketSummary) {
            $bucketSummaries[$bucketSummary['rawCentralDirectoryNameByteLengthBucket']] = $bucketSummary;
        }
        $rootSummaries = [];
        foreach ($summary['rawCentralDirectoryNameByteLengthDirectoryRootSummaries'] as $rootSummary) {
            $rootSummaries[$rootSummary['directoryRoot']] = $rootSummary;
        }

        $t->same(true, $summary['isOpenDocumentTextPackage']);
        $t->same([
            '/' => 2,
            'Configurations2/' => 1,
            'META-INF/' => 1,
            'Pictures/' => 2,
        ], $summary['rawCentralDirectoryNameByteLengthDirectoryRootCounts']);
        $t->same([
            '/' => ['content.xml', 'mimetype'],
            'Configurations2/' => ['Configurations2/accelerator/current.xml'],
            'META-INF/' => ['META-INF/manifest.xml'],
            'Pictures/' => ['Pictures/hero.png', 'Pictures/thumbs/icon.png'],
        ], $summary['rawCentralDirectoryNameByteLengthEntryNamesByDirectoryRoot']);
        $t->same(4, $summary['rawCentralDirectoryNameByteLengthDirectoryRootSummaryCount']);
        $t->same('odf-raw-central-directory-name-byte-length-metadata-only', $summary['rawCentralDirectoryNameByteLengthByteExposurePolicy']);
        $t->same(false, $summary['rawCentralDirectoryNameByteLengthCanExposeBytes']);

        $t->same(['/' => 1], $bucketSummaries['up-to-8-bytes']['directoryRootCounts']);
        $t->same(['/' => 1], $bucketSummaries['9-to-16-bytes']['directoryRootCounts']);
        $t->same(['META-INF/' => 1, 'Pictures/' => 2], $bucketSummaries['17-to-32-bytes']['directoryRootCounts']);
        $t->same(['Configurations2/' => 1], $bucketSummaries['33-to-64-bytes']['directoryRootCounts']);

        $t->same(2, $rootSummaries['/']['entryCount']);
        $t->same(2, $rootSummaries['/']['fileEntryCount']);
        $t->same(strlen('mimetype') + strlen('content.xml'), $rootSummaries['/']['rawNameBytes']);
        $t->same(['up-to-8-bytes', '9-to-16-bytes'], $rootSummaries['/']['rawCentralDirectoryNameByteLengthBuckets']);
        $t->same(['content.xml', 'mimetype'], $rootSummaries['/']['entryNames']);
        $t->same('content.xml', $rootSummaries['/']['longestRawNameEntryName']);
        $t->same(strlen('content.xml'), $rootSummaries['/']['longestRawNameByteLength']);

        $t->same(1, $rootSummaries['Configurations2/']['entryCount']);
        $t->same(['33-to-64-bytes'], $rootSummaries['Configurations2/']['rawCentralDirectoryNameByteLengthBuckets']);
        $t->same(['Configurations2/accelerator/current.xml'], $rootSummaries['Configurations2/']['entryNames']);
        $t->same('Configurations2/accelerator/current.xml', $rootSummaries['Configurations2/']['longestRawNameEntryName']);

        $t->same(1, $rootSummaries['META-INF/']['entryCount']);
        $t->same(['stored'], $rootSummaries['META-INF/']['compressionMethodNames']);
        $t->same(2, $rootSummaries['Pictures/']['entryCount']);
        $t->same(strlen('Pictures/hero.png') + strlen('Pictures/thumbs/icon.png'), $rootSummaries['Pictures/']['rawNameBytes']);
        $t->same(['Pictures/hero.png', 'Pictures/thumbs/icon.png'], $rootSummaries['Pictures/']['entryNames']);
        $t->same(['17-to-32-bytes'], $rootSummaries['Pictures/']['rawCentralDirectoryNameByteLengthBuckets']);
        $t->same('Pictures/thumbs/icon.png', $rootSummaries['Pictures/']['longestRawNameEntryName']);
        $t->same(strlen('Pictures/thumbs/icon.png'), $rootSummaries['Pictures/']['longestRawNameByteLength']);

        $t->same(0, $summary['rawCentralDirectoryNameByteLengthDecodedNameDiffersFromRawNameCount']);
        $t->same(6, count($summary['zipRawStrictImport']['centralDirectorySize']['entries'] ?? []));
        $encodedRootSummaries = json_encode($summary['rawCentralDirectoryNameByteLengthDirectoryRootSummaries'], JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedRootSummaries, 'Raw central directory root buckets.'));
    },
];
