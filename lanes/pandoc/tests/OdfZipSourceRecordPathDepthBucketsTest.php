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
  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="ObjectReplacements/Object 1" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Scripts/python/review.py" manifest:media-type="text/x-python"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP source record path-depth review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="PathDepthBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Source Record Path Depth Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$objectXml = <<<'XML'
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/review.png', 'data' => str_repeat('P', 256), 'compressionMethod' => 8],
    ['name' => 'ObjectReplacements/Object 1', 'data' => $objectXml, 'compressionMethod' => 0],
    ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accel/>', 'compressionMethod' => 0],
    ['name' => 'Scripts/python/review.py', 'data' => 'print("review")', 'compressionMethod' => 8],
    ['name' => 'Notes/deep/private/review.txt', 'data' => 'PRIVATE-REVIEW', 'compressionMethod' => 0],
], 'odt zip source record path-depth provenance');

return [
    'summarizes ODT ZIP source records by package path depth' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedDepthCounts = [
            1 => 4,
            2 => 3,
            3 => 2,
            4 => 1,
        ];
        $expectedDepthBytes = odf_zip_source_record_path_depth_sums($compactInventory['parts'], 'zipSourceRecordBytes');

        foreach ([
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
            'document provenance' => $documentProvenance,
        ] as $label => $handoff) {
            $t->same(4, $handoff['packageZipSourceRecordPathDepthCount'], "{$label} path-depth count");
            $t->same($expectedDepthCounts, $handoff['packageZipSourceRecordPathDepthCounts'], "{$label} path-depth counts");
            $t->same($expectedDepthBytes, $handoff['packageZipSourceRecordPathDepthBytes'], "{$label} path-depth bytes");
            $t->same(0, $handoff['packageZipSourceRecordPathDepthDataDescriptorEntryCount'], "{$label} descriptor entries");
            $t->same(0, $handoff['packageZipSourceRecordPathDepthIssueEntryCount'], "{$label} issue entries");
            $t->same(4, $handoff['packageZipSourceRecordMaxPathDepth'], "{$label} max depth");
            $t->same([1, 2, 3, 4], array_column($handoff['packageZipSourceRecordPathDepths'], 'pathDepth'), "{$label} depth order");
            $t->same(
                $handoff['packageZipSourceRecordEntryCount'],
                array_sum($handoff['packageZipSourceRecordPathDepthCounts']),
                "{$label} source-record count parity"
            );
        }

        $t->same(
            $compactInventory['packageZipSourceRecordPathDepths'],
            $compactIdentity['packageZipSourceRecordPathDepths']
        );
        $t->same(
            $richProvenance['packageZipSourceRecordPathDepths'],
            $richIdentity['packageZipSourceRecordPathDepths']
        );
        $t->same(
            $richIdentity['packageZipSourceRecordPathDepths'],
            $documentProvenance['packageZipSourceRecordPathDepths']
        );

        $compactDepths = odf_zip_source_record_path_depth_index_by(
            $compactInventory['packageZipSourceRecordPathDepths'],
            'pathDepth'
        );
        $richDepths = odf_zip_source_record_path_depth_index_by(
            $richProvenance['packageZipSourceRecordPathDepths'],
            'pathDepth'
        );

        foreach ([$compactDepths[2], $richDepths[2]] as $depthTwo) {
            $t->same(3, $depthTwo['entryCount']);
            $t->same([
                'META-INF/manifest.xml',
                'ObjectReplacements/Object 1',
                'Pictures/review.png',
            ], $depthTwo['entryNames']);
            $t->same([
                'META-INF/' => 1,
                'ObjectReplacements/' => 1,
                'Pictures/' => 1,
            ], $depthTwo['directoryRootCounts']);
            $t->same([0 => 2, 8 => 1], $depthTwo['compressionMethodCounts']);
            $t->same(
                odf_zip_source_record_path_depth_sum_for_depth($compactInventory['parts'], 2, 'zipCentralDirectoryRecordBytes'),
                $depthTwo['centralDirectoryRecordBytes']
            );
            $t->same(false, array_key_exists('contents', $depthTwo['largestSourceRecordEntry']));
        }

        foreach ([$compactDepths[3], $richDepths[3]] as $depthThree) {
            $t->same(2, $depthThree['entryCount']);
            $t->same([
                'Configurations2/accelerator/current.xml',
                'Scripts/python/review.py',
            ], $depthThree['entryNames']);
            $t->same([
                'Configurations2/' => 1,
                'Scripts/' => 1,
            ], $depthThree['directoryRootCounts']);
            $t->same([
                'configuration-package' => 1,
                'manifest-declared' => 2,
                'script-package' => 1,
            ], $depthThree['roleCounts']);
        }

        foreach ([$compactDepths[4], $richDepths[4]] as $depthFour) {
            $t->same(1, $depthFour['entryCount']);
            $t->same(['Notes/deep/private/review.txt'], $depthFour['entryNames']);
            $t->same(['Notes/' => 1], $depthFour['directoryRootCounts']);
            $t->same(['undeclared-package-entry-no-bytes' => 1], $depthFour['byteExposurePolicyCounts']);
            $t->same(['undeclared-package-entry' => 1], $depthFour['roleCounts']);
            $t->same(0, $depthFour['exposableEntryCount']);
            $t->same(1, $depthFour['blockedEntryCount']);
            $t->same(
                $compactInventory['parts']['Notes/deep/private/review.txt']['zipSourceRecordBytes'],
                $depthFour['sourceRecordBytes']
            );

            $largest = $depthFour['largestSourceRecordEntry'];
            $t->same('Notes/deep/private/review.txt', $largest['entryName']);
            $t->same(4, $largest['pathDepth']);
            $t->same(4, $largest['packagePathDepth']);
            $t->same('Notes/', $largest['directoryRoot']);
            $t->same('Notes/deep/private/', $largest['packageDirectory']);
            $t->same('review.txt', $largest['packageBasename']);
            $t->same(false, $largest['canExposeBytes']);
            $t->same(false, array_key_exists('contents', $largest));
        }
    },
];

/**
 * @param list<array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function odf_zip_source_record_path_depth_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(int) $item[$key]] = $item;
    }

    return $indexed;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<int, int>
 */
function odf_zip_source_record_path_depth_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true || ($part['isDirectory'] ?? false) === true) {
            continue;
        }

        $depth = is_int($part['packagePathDepth'] ?? null) ? $part['packagePathDepth'] : 0;
        $sums[$depth] = ($sums[$depth] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_NUMERIC);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function odf_zip_source_record_path_depth_sum_for_depth(array $inventory, int $depth, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true || ($part['isDirectory'] ?? false) === true) {
            continue;
        }
        if (($part['packagePathDepth'] ?? null) !== $depth) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}
