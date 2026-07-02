<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes shared ZIP package directory depths before OPC handoff' => static function (TestRunner $t): void {
        $coverage = json_decode(
            file_get_contents(dirname(__DIR__) . '/UPSTREAM_TEST_MANIFEST.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $t->same(1, $coverage['mappedSharedZipPackageDirectoryDepthCases']);
        $t->same(41, $coverage['sharedZipPackageDirectoryDepthAssertions']);
        $t->same(1, $coverage['inventory']['mappedSharedZipPackageDirectoryDepthCases']);
        $t->same(41, $coverage['inventory']['sharedZipPackageDirectoryDepthAssertions']);

        $reviewBytes = 'REVIEWBIN';
        $parts = [
            [
                'name' => '[Content_Types].xml',
                'data' => <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
</Types>
XML,
                'compressionMethod' => 0,
            ],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>', 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => '<w:document/>', 'compressionMethod' => 0],
            ['name' => 'word/media/image.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'word/media/deep/review.bin', 'data' => $reviewBytes, 'compressionMethod' => 0],
            ['name' => 'word/media/deep/review/', 'data' => '', 'compressionMethod' => 0],
        ];

        $package = ZipPackage::fromParts($parts);
        $manifest = $package->packageManifestPreflight();
        $opcManifest = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $entriesByName = array_column($manifest['entries'], null, 'name');
        $opcEntriesByName = array_column($opcManifest['entries'], null, 'entryName');
        $summariesByDepth = [];
        foreach ($manifest['directoryDepthSummaries'] as $summary) {
            $summariesByDepth[$summary['directoryDepth']] = $summary;
        }
        $depth3 = $summariesByDepth[3];
        $depth3LocalRecordBytes = $entriesByName['word/media/deep/review.bin']['localRecordBytes']
            + $entriesByName['word/media/deep/review/']['localRecordBytes'];
        $depth3SourceRecordBytes = $entriesByName['word/media/deep/review.bin']['sourceRecordBytes']
            + $entriesByName['word/media/deep/review/']['sourceRecordBytes'];

        $t->same(4, $manifest['directoryDepthSummaryCount']);
        $t->same([0, 1, 2, 3], $manifest['directoryDepths']);
        $t->same([0 => 1, 1 => 2, 2 => 1, 3 => 2], $manifest['directoryDepthCounts']);
        $t->same([0 => 1, 1 => 2, 2 => 1, 3 => 1], $manifest['directoryDepthFileEntryCounts']);
        $t->same([0 => 0, 1 => 0, 2 => 0, 3 => 1], $manifest['directoryDepthDirectoryEntryCounts']);
        $t->same(3, $manifest['maxDirectoryDepth']);
        $t->same(['word/media/deep/review.bin', 'word/media/deep/review/'], $manifest['deepestEntryNames']);
        $t->same([
            '[Content_Types].xml' => 0,
            '_rels/.rels' => 1,
            'word/document.xml' => 1,
            'word/media/image.png' => 2,
            'word/media/deep/review.bin' => 3,
            'word/media/deep/review/' => 3,
        ], array_map(
            static fn (array $entry): int => $entry['directoryDepth'],
            $entriesByName
        ));
        $t->same(1, $summariesByDepth[0]['entryCount']);
        $t->same(['xml'], $summariesByDepth[0]['packagePartExtensionKeys']);
        $t->same(2, $summariesByDepth[1]['entryCount']);
        $t->same(1, $summariesByDepth[2]['entryCount']);
        $t->same(2, $depth3['entryCount']);
        $t->same(1, $depth3['fileEntryCount']);
        $t->same(1, $depth3['directoryEntryCount']);
        $t->same(strlen($reviewBytes), $depth3['compressedBytes']);
        $t->same(strlen($reviewBytes), $depth3['uncompressedBytes']);
        $t->same($depth3LocalRecordBytes, $depth3['localRecordBytes']);
        $t->same($depth3SourceRecordBytes, $depth3['sourceRecordBytes']);
        $t->same(0, $depth3['dataDescriptorEntryCount']);
        $t->same(0, $depth3['dataDescriptorBytes']);
        $t->same(['word/'], $depth3['directoryRoots']);
        $t->same(['(directory)', 'bin'], $depth3['packagePartExtensionKeys']);
        $t->same(['stored'], $depth3['compressionMethodNames']);
        $t->same(['word/media/deep/review.bin', 'word/media/deep/review/'], $depth3['entryNames']);
        $t->same(strlen($reviewBytes), $depth3['largestEntryUncompressedBytes']);
        $t->same(['word/media/deep/review.bin'], $depth3['largestEntryNames']);

        $t->same($manifest['manifestSha256'], $opcManifest['zipPackageManifestSha256']);
        $t->same($manifest['entryCount'], $opcManifest['entryCount']);
        $t->same($manifest['directoryDepthSummaryCount'], $opcManifest['directoryDepthSummaryCount']);
        $t->same($manifest['directoryDepths'], $opcManifest['directoryDepths']);
        $t->same($manifest['directoryDepthCounts'], $opcManifest['directoryDepthCounts']);
        $t->same($manifest['directoryDepthFileEntryCounts'], $opcManifest['directoryDepthFileEntryCounts']);
        $t->same($manifest['directoryDepthDirectoryEntryCounts'], $opcManifest['directoryDepthDirectoryEntryCounts']);
        $t->same($manifest['directoryDepthSummaries'], $opcManifest['directoryDepthSummaries']);
        $t->same([
            '[Content_Types].xml' => 0,
            '_rels/.rels' => 1,
            'word/document.xml' => 1,
            'word/media/image.png' => 2,
            'word/media/deep/review.bin' => 3,
            'word/media/deep/review/' => 3,
        ], array_map(
            static fn (array $entry): int => $entry['directoryDepth'],
            $opcEntriesByName
        ));
        $t->same($depth3, $opcManifest['directoryDepthSummaries'][3]);
    },
];
