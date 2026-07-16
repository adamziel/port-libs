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
  <manifest:file-entry manifest:full-path="Pictures/source.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/extra.bin" manifest:media-type="application/octet-stream"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP timestamp source rollup.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="TimestampBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Timestamp Source Rollup</dc:title>
  </office:meta>
</office:document-meta>
XML;

$dosDateTime = static function (int $year, int $month, int $day, int $hour, int $minute, int $second): array {
    return [
        (($hour & 0x1f) << 11) | (($minute & 0x3f) << 5) | ((intdiv($second, 2)) & 0x1f),
        ((($year - 1980) & 0x7f) << 9) | (($month & 0x0f) << 5) | ($day & 0x1f),
    ];
};
[$styleDosTime, $styleDosDate] = $dosDateTime(2026, 7, 1, 9, 10, 12);
[$pictureDosTime, $pictureDosDate] = $dosDateTime(2026, 7, 1, 9, 11, 14);

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0, 'modifiedAt' => 1780477000],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8, 'modifiedAt' => 1780478000],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8, 'modifiedDosTime' => $styleDosTime, 'modifiedDosDate' => $styleDosDate],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/source.png', 'data' => str_repeat('P', 256), 'compressionMethod' => 0, 'modifiedDosTime' => $pictureDosTime, 'modifiedDosDate' => $pictureDosDate],
    ['name' => 'Pictures/extra.bin', 'data' => str_repeat('B', 64), 'compressionMethod' => 8, 'modifiedAt' => 1780479000],
    ['name' => 'Notes/private.txt', 'data' => 'PRIVATE-NOTE', 'compressionMethod' => 0],
], 'odt zip timestamp source provenance');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

$sourceKey = static function (array $part): string {
    return is_string($part['zipTimestampSource'] ?? null) && $part['zipTimestampSource'] !== ''
        ? $part['zipTimestampSource']
        : '(missing)';
};

$countKey = static function (mixed $value): string {
    return is_string($value) && $value !== '' ? $value : '(missing)';
};

$expectedFromParts = static function (array $parts) use ($sourceKey, $countKey): array {
    $sources = [];
    foreach ($parts as $name => $part) {
        $key = $sourceKey($part);
        if (!isset($sources[$key])) {
            $sources[$key] = [
                'entryCount' => 0,
                'entryNames' => [],
                'byteLength' => 0,
                'sourceRecordBytes' => 0,
                'modifiedEntryCount' => 0,
                'issueEntryCount' => 0,
                'issueCount' => 0,
                'directoryRootCounts' => [],
                'localTimestampSourceCounts' => [],
                'centralTimestampSourceCounts' => [],
                'byteExposurePolicyCounts' => [],
                'manifestMediaTypeBaseCounts' => [],
                'roleCounts' => [],
                'earliestModifiedAt' => null,
                'latestModifiedAt' => null,
                'earliestModifiedEntry' => null,
                'latestModifiedEntry' => null,
                'largestSourceRecordEntry' => null,
            ];
        }

        $entryName = is_string($part['path'] ?? null) ? $part['path'] : (string) $name;
        $directoryRoot = $countKey($part['zipPackageManifestDirectoryRoot'] ?? null);
        $byteExposurePolicy = $countKey($part['byteExposurePolicy'] ?? null);
        $manifestMediaTypeBase = $countKey($part['manifestMediaTypeBase'] ?? null);
        $byteLength = is_int($part['byteLength'] ?? null) ? $part['byteLength'] : 0;
        $sourceRecordBytes = is_int($part['zipSourceRecordBytes'] ?? null) ? $part['zipSourceRecordBytes'] : 0;
        $modifiedAt = is_int($part['zipModifiedAt'] ?? null) ? $part['zipModifiedAt'] : null;
        $issues = is_array($part['zipTimestampIssues'] ?? null)
            ? array_values(array_filter($part['zipTimestampIssues'], static fn (mixed $issue): bool => is_string($issue)))
            : [];

        ++$sources[$key]['entryCount'];
        $sources[$key]['entryNames'][] = $entryName;
        $sources[$key]['byteLength'] += $byteLength;
        $sources[$key]['sourceRecordBytes'] += $sourceRecordBytes;
        $sources[$key]['directoryRootCounts'][$directoryRoot] = ($sources[$key]['directoryRootCounts'][$directoryRoot] ?? 0) + 1;
        $sources[$key]['localTimestampSourceCounts'][$countKey($part['zipLocalTimestampSource'] ?? null)] =
            ($sources[$key]['localTimestampSourceCounts'][$countKey($part['zipLocalTimestampSource'] ?? null)] ?? 0) + 1;
        $sources[$key]['centralTimestampSourceCounts'][$countKey($part['zipCentralTimestampSource'] ?? null)] =
            ($sources[$key]['centralTimestampSourceCounts'][$countKey($part['zipCentralTimestampSource'] ?? null)] ?? 0) + 1;
        $sources[$key]['byteExposurePolicyCounts'][$byteExposurePolicy] =
            ($sources[$key]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
        $sources[$key]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
            ($sources[$key]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
        foreach (array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []) as $role) {
            if ($role === '') {
                continue;
            }
            $sources[$key]['roleCounts'][$role] = ($sources[$key]['roleCounts'][$role] ?? 0) + 1;
        }
        if ($modifiedAt !== null) {
            ++$sources[$key]['modifiedEntryCount'];
            if (!is_int($sources[$key]['earliestModifiedAt']) || $modifiedAt < $sources[$key]['earliestModifiedAt']) {
                $sources[$key]['earliestModifiedAt'] = $modifiedAt;
                $sources[$key]['earliestModifiedEntry'] = $entryName;
            }
            if (!is_int($sources[$key]['latestModifiedAt']) || $modifiedAt > $sources[$key]['latestModifiedAt']) {
                $sources[$key]['latestModifiedAt'] = $modifiedAt;
                $sources[$key]['latestModifiedEntry'] = $entryName;
            }
        }
        if ($issues !== []) {
            ++$sources[$key]['issueEntryCount'];
            $sources[$key]['issueCount'] += count($issues);
        }
        if (
            $sources[$key]['largestSourceRecordEntry'] === null
            || $sourceRecordBytes > (int) ($parts[$sources[$key]['largestSourceRecordEntry']]['zipSourceRecordBytes'] ?? 0)
            || ($sourceRecordBytes === (int) ($parts[$sources[$key]['largestSourceRecordEntry']]['zipSourceRecordBytes'] ?? 0) && strcmp($entryName, $sources[$key]['largestSourceRecordEntry']) < 0)
        ) {
            $sources[$key]['largestSourceRecordEntry'] = $entryName;
        }
    }

    ksort($sources, SORT_STRING);
    foreach ($sources as $key => $source) {
        sort($source['entryNames'], SORT_STRING);
        ksort($source['directoryRootCounts'], SORT_STRING);
        ksort($source['localTimestampSourceCounts'], SORT_STRING);
        ksort($source['centralTimestampSourceCounts'], SORT_STRING);
        ksort($source['byteExposurePolicyCounts'], SORT_STRING);
        ksort($source['manifestMediaTypeBaseCounts'], SORT_STRING);
        ksort($source['roleCounts'], SORT_STRING);
        $sources[$key] = $source;
    }

    return $sources;
};

$mapFromExpected = static function (array $expected, string $field): array {
    $mapped = [];
    foreach ($expected as $key => $source) {
        $mapped[$key] = $source[$field];
    }

    return $mapped;
};

return [
    'summarizes ODT ZIP entries by timestamp source across package handoffs' => static function (TestRunner $t) use ($buildPackage, $indexBy, $expectedFromParts, $mapFromExpected): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expected = $expectedFromParts($compactInventory['parts']);
        $expectedCounts = $mapFromExpected($expected, 'entryCount');
        $expectedByteLengths = $mapFromExpected($expected, 'byteLength');
        $expectedSourceRecordBytes = $mapFromExpected($expected, 'sourceRecordBytes');
        $expectedModifiedEntryCount = array_sum($mapFromExpected($expected, 'modifiedEntryCount'));
        $expectedIssueEntryCount = array_sum($mapFromExpected($expected, 'issueEntryCount'));

        $t->same(['(missing)' => 3, 'dos' => 2, 'extended-timestamp' => 3], $expectedCounts);
        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(3, $handoff['packageZipTimestampSourceCount']);
            $t->same($expectedCounts, $handoff['packageZipTimestampSourceCounts']);
            $t->same($expectedByteLengths, $handoff['packageZipTimestampSourceByteLengths']);
            $t->same($expectedSourceRecordBytes, $handoff['packageZipTimestampSourceRecordBytes']);
            $t->same($expectedModifiedEntryCount, $handoff['packageZipTimestampSourceModifiedEntryCount']);
            $t->same($expectedIssueEntryCount, $handoff['packageZipTimestampSourceIssueEntryCount']);
        }

        $compactSources = $indexBy($compactInventory['packageZipTimestampSources'], 'timestampSourceKey');
        $richSources = $indexBy($richProvenance['packageZipTimestampSources'], 'timestampSourceKey');
        $documentSources = $indexBy($documentProvenance['packageZipTimestampSources'], 'timestampSourceKey');

        foreach ([$compactSources, $richSources, $documentSources] as $sources) {
            foreach ($expected as $key => $sourceExpected) {
                $source = $sources[$key];
                $t->same($key, $source['timestampSourceKey']);
                $t->same($key === '(missing)' ? null : $key, $source['timestampSource']);
                $t->same($sourceExpected['entryCount'], $source['entryCount']);
                $t->same($sourceExpected['entryNames'], $source['entryNames']);
                $t->same($sourceExpected['byteLength'], $source['byteLength']);
                $t->same($sourceExpected['sourceRecordBytes'], $source['sourceRecordBytes']);
                $t->same($sourceExpected['modifiedEntryCount'], $source['modifiedEntryCount']);
                $t->same($sourceExpected['issueEntryCount'], $source['zipModificationTimeIssueEntryCount']);
                $t->same($sourceExpected['issueCount'], $source['zipModificationTimeIssueCount']);
                $t->same($sourceExpected['directoryRootCounts'], $source['directoryRootCounts']);
                $t->same($sourceExpected['localTimestampSourceCounts'], $source['localTimestampSourceCounts']);
                $t->same($sourceExpected['centralTimestampSourceCounts'], $source['centralTimestampSourceCounts']);
                $t->same($sourceExpected['byteExposurePolicyCounts'], $source['byteExposurePolicyCounts']);
                $t->same($sourceExpected['manifestMediaTypeBaseCounts'], $source['manifestMediaTypeBaseCounts']);
                $t->same($sourceExpected['roleCounts'], $source['roleCounts']);
                $t->same($sourceExpected['earliestModifiedAt'], $source['earliestModifiedAt']);
                $t->same($sourceExpected['latestModifiedAt'], $source['latestModifiedAt']);
                $t->same($sourceExpected['earliestModifiedEntry'], is_array($source['earliestModifiedEntry']) ? $source['earliestModifiedEntry']['entryName'] : null);
                $t->same($sourceExpected['latestModifiedEntry'], is_array($source['latestModifiedEntry']) ? $source['latestModifiedEntry']['entryName'] : null);
                $t->same($sourceExpected['largestSourceRecordEntry'], $source['largestSourceRecordEntry']['entryName']);
                $t->same(false, array_key_exists('contents', $source['largestSourceRecordEntry']));
            }
        }

        $t->same(['META-INF/manifest.xml', 'Pictures/extra.bin', 'content.xml'], $compactSources['extended-timestamp']['entryNames']);
        $t->same(['Pictures/source.png', 'styles.xml'], $compactSources['dos']['entryNames']);
        $t->same(['Notes/private.txt', 'meta.xml', 'mimetype'], $compactSources['(missing)']['entryNames']);
        $t->same($compactInventory['packageZipTimestampSourceCounts'], $richProvenance['packageZipTimestampSourceCounts']);
        $t->same($richProvenance['packageZipTimestampSources'], $documentProvenance['packageZipTimestampSources']);
    },
];
