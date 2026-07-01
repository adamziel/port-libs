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
  <manifest:file-entry manifest:full-path="Pictures/IMAGE.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/icon.PnG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Extras/report.bin" manifest:media-type="application/octet-stream"/>
  <manifest:file-entry manifest:full-path="Scripts/no-extension" manifest:media-type="application/octet-stream"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP source record package part raw extension review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="RawExtensionBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Source Record Package Part Raw Extension Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/IMAGE.PNG', 'data' => str_repeat('P', 320), 'compressionMethod' => 0],
    ['name' => 'Pictures/icon.PnG', 'data' => str_repeat('i', 64), 'compressionMethod' => 8],
    ['name' => 'Extras/report.bin', 'data' => str_repeat('B', 144), 'compressionMethod' => 0],
    ['name' => 'Scripts/no-extension', 'data' => 'macro source bytes', 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE-NOTE', 'compressionMethod' => 0],
], 'odt zip source record package part raw extension provenance');

return [
    'records mapped ODF ZIP source-record raw extension case count' => static function (TestRunner $t): void {
        $manifest = json_decode(
            file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json') ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $t->same(1, $manifest['mappedOdfZipSourceRecordPackagePartRawExtensionCases']);
        $t->same(80, $manifest['odfZipSourceRecordPackagePartRawExtensionAssertions']);
        $t->same(
            1,
            $manifest['benchmarkDenominator']['breakdown']['mappedOdfZipSourceRecordPackagePartRawExtensionCases']
        );
        $t->same(
            80,
            $manifest['benchmarkDenominator']['breakdown']['odfZipSourceRecordPackagePartRawExtensionAssertions']
        );
        $t->same(
            1,
            $manifest['benchmarkDenominator']['inventory']['mappedOdfZipSourceRecordPackagePartRawExtensionCases']
        );
        $t->same(
            80,
            $manifest['benchmarkDenominator']['inventory']['odfZipSourceRecordPackagePartRawExtensionAssertions']
        );
        $t->same(1, $manifest['inventory']['mappedOdfZipSourceRecordPackagePartRawExtensionCases']);
        $t->same(80, $manifest['inventory']['odfZipSourceRecordPackagePartRawExtensionAssertions']);
    },

    'summarizes ODT ZIP source records by raw package part extension' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedCounts = odf_zip_source_record_package_part_raw_extension_counts($compactInventory['parts']);
        $expectedBytes = odf_zip_source_record_package_part_raw_extension_sums(
            $compactInventory['parts'],
            'zipSourceRecordBytes'
        );

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(5, $handoff['packageZipSourceRecordPackagePartRawExtensionCount']);
            $t->same($expectedCounts, $handoff['packageZipSourceRecordPackagePartRawExtensionCounts']);
            $t->same($expectedBytes, $handoff['packageZipSourceRecordPackagePartRawExtensionBytes']);
            $t->same(3, $handoff['packageZipSourceRecordRawExtensionlessPackagePartCount']);
            $t->same(2, $handoff['packageZipSourceRecordPackagePartRawExtensionUppercasePartCount']);
            $t->same(2, $handoff['packageZipSourceRecordPackagePartRawExtensionNormalizedPartCount']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartRawExtensionDataDescriptorEntryCount']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartRawExtensionIssueEntryCount']);
        }

        $rawExtensions = odf_zip_source_record_package_part_raw_extension_index_by(
            $richProvenance['packageZipSourceRecordPackagePartRawExtensions'],
            'packagePartRawExtensionKey'
        );

        $png = $rawExtensions['PNG'];
        $t->same('PNG', $png['packagePartRawExtension']);
        $t->same(1, $png['entryCount']);
        $t->same(1, $png['uppercasePartCount']);
        $t->same(1, $png['normalizedPartCount']);
        $t->same(['png' => 1], $png['packagePartExtensionCounts']);
        $t->same(['Pictures/IMAGE.PNG'], $png['entryNames']);
        $t->same(['Pictures/' => 1], $png['directoryRootCounts']);
        $t->same(['image/png' => 1], $png['manifestMediaTypeBaseCounts']);
        $t->same('Pictures/IMAGE.PNG', $png['largestSourceRecordEntry']['entryName']);
        $t->same('PNG', $png['largestSourceRecordEntry']['packagePartRawExtension']);
        $t->same('png', $png['largestSourceRecordEntry']['packagePartExtension']);
        $t->same(true, $png['largestSourceRecordEntry']['packagePartExtensionHasUppercase']);
        $t->same(true, $png['largestSourceRecordEntry']['packagePartExtensionWasNormalized']);
        $t->same(false, array_key_exists('contents', $png['largestSourceRecordEntry']));

        $mixedPng = $rawExtensions['PnG'];
        $t->same(['png' => 1], $mixedPng['packagePartExtensionCounts']);
        $t->same(['Pictures/icon.PnG'], $mixedPng['entryNames']);
        $t->same(1, $mixedPng['uppercasePartCount']);
        $t->same(1, $mixedPng['normalizedPartCount']);

        $xml = $rawExtensions['xml'];
        $t->same(4, $xml['entryCount']);
        $t->same([
            'META-INF/manifest.xml',
            'content.xml',
            'meta.xml',
            'styles.xml',
        ], $xml['entryNames']);
        $t->same(['xml' => 4], $xml['packagePartExtensionCounts']);
        $t->same(['(missing)' => 1, 'text/xml' => 3], $xml['manifestMediaTypeBaseCounts']);

        $extensionless = $rawExtensions['(none)'];
        $t->same(null, $extensionless['packagePartRawExtension']);
        $t->same(true, $extensionless['extensionlessPackagePart']);
        $t->same(3, $extensionless['entryCount']);
        $t->same(['Notes/private', 'Scripts/no-extension', 'mimetype'], $extensionless['entryNames']);
        $t->same([
            '(missing)' => 2,
            'application/octet-stream' => 1,
        ], $extensionless['manifestMediaTypeBaseCounts']);
        $t->same([
            'manifest-declared' => 1,
            'odf-mimetype' => 1,
            'script-package' => 1,
            'undeclared-package-entry' => 1,
        ], $extensionless['roleCounts']);
        $t->same('Scripts/no-extension', $extensionless['largestSourceRecordEntry']['entryName']);
        $t->same(false, array_key_exists('contents', $extensionless['largestSourceRecordEntry']));

        $t->same(
            $compactInventory['packageZipSourceRecordPackagePartRawExtensionCounts'],
            $documentProvenance['packageZipSourceRecordPackagePartRawExtensionCounts']
        );
        $t->same(
            $richProvenance['packageZipSourceRecordPackagePartRawExtensions'],
            $richIdentity['packageZipSourceRecordPackagePartRawExtensions']
        );
    },
];

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function odf_zip_source_record_package_part_raw_extension_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function odf_zip_source_record_package_part_raw_extension_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true || ($part['isDirectory'] ?? false) === true) {
            continue;
        }

        $key = odf_zip_source_record_package_part_raw_extension_key($part);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function odf_zip_source_record_package_part_raw_extension_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true || ($part['isDirectory'] ?? false) === true) {
            continue;
        }

        $key = odf_zip_source_record_package_part_raw_extension_key($part);
        $sums[$key] = ($sums[$key] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, mixed> $part
 */
function odf_zip_source_record_package_part_raw_extension_key(array $part): string
{
    $entryName = is_string($part['path'] ?? null)
        ? $part['path']
        : (is_string($part['part'] ?? null) ? $part['part'] : '');
    $extension = pathinfo($entryName, PATHINFO_EXTENSION);

    return $extension === '' ? '(none)' : $extension;
}
