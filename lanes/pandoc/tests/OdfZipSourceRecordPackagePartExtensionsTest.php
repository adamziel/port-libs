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
  <manifest:file-entry manifest:full-path="Thumbnails/review.png" manifest:media-type="image/png"/>
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
      <text:p>ZIP source record package part extension review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="SourceExtensionBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Source Record Package Part Extension Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/review.png', 'data' => str_repeat('P', 256), 'compressionMethod' => 0, 'comment' => 'extension image'],
    ['name' => 'Thumbnails/review.png', 'data' => str_repeat('T', 96), 'compressionMethod' => 8],
    ['name' => 'Extras/report.bin', 'data' => str_repeat('B', 144), 'compressionMethod' => 0],
    ['name' => 'Scripts/no-extension', 'data' => 'macro source bytes', 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE-NOTE', 'compressionMethod' => 0],
], 'odt zip source record package part extension provenance');

return [
    'summarizes ODT ZIP source records by package part extension' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedCounts = odf_zip_source_record_package_part_extension_counts($compactInventory['parts']);
        $expectedBytes = odf_zip_source_record_package_part_extension_sums($compactInventory['parts'], 'zipSourceRecordBytes');

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(4, $handoff['packageZipSourceRecordPackagePartExtensionCount']);
            $t->same($expectedCounts, $handoff['packageZipSourceRecordPackagePartExtensionCounts']);
            $t->same($expectedBytes, $handoff['packageZipSourceRecordPackagePartExtensionBytes']);
            $t->same(3, $handoff['packageZipSourceRecordExtensionlessPackagePartCount']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartExtensionDataDescriptorEntryCount']);
            $t->same(0, $handoff['packageZipSourceRecordPackagePartExtensionIssueEntryCount']);
            $t->same(
                $handoff['packageZipSourceRecordEntryCount'],
                array_sum($handoff['packageZipSourceRecordPackagePartExtensionCounts'])
            );
        }

        $t->same(
            $compactInventory['packageZipSourceRecordPackagePartExtensions'],
            $compactIdentity['packageZipSourceRecordPackagePartExtensions']
        );
        $t->same(
            $richProvenance['packageZipSourceRecordPackagePartExtensions'],
            $richIdentity['packageZipSourceRecordPackagePartExtensions']
        );
        $t->same(
            $richIdentity['packageZipSourceRecordPackagePartExtensions'],
            $documentProvenance['packageZipSourceRecordPackagePartExtensions']
        );

        $extensions = odf_zip_source_record_package_part_extension_index_by(
            $richProvenance['packageZipSourceRecordPackagePartExtensions'],
            'packagePartExtensionKey'
        );

        $xml = $extensions['xml'];
        $t->same(4, $xml['entryCount']);
        $t->same([
            'META-INF/manifest.xml',
            'content.xml',
            'meta.xml',
            'styles.xml',
        ], $xml['entryNames']);
        $t->same(['/' => 3, 'META-INF/' => 1], $xml['directoryRootCounts']);
        $t->same([0 => 2, 8 => 2], $xml['compressionMethodCounts']);
        $t->same(['(missing)' => 1, 'text/xml' => 3], $xml['manifestMediaTypeBaseCounts']);
        $t->same(
            odf_zip_source_record_package_part_extension_sum_for_key($compactInventory['parts'], 'xml', 'zipCentralDirectoryRecordBytes'),
            $xml['centralDirectoryRecordBytes']
        );

        $png = $extensions['png'];
        $t->same(2, $png['entryCount']);
        $t->same(['Pictures/review.png', 'Thumbnails/review.png'], $png['entryNames']);
        $t->same(['Pictures/' => 1, 'Thumbnails/' => 1], $png['directoryRootCounts']);
        $t->same(['image/png' => 2], $png['manifestMediaTypeBaseCounts']);
        $t->same([
            'manifest-declared' => 2,
            'media-resource' => 1,
            'package-thumbnail' => 1,
        ], $png['roleCounts']);
        $t->same(
            $compactInventory['parts']['Pictures/review.png']['zipSourceRecordBytes'],
            $png['largestSourceRecordEntry']['sourceRecordBytes']
        );
        $t->same(false, array_key_exists('contents', $png['largestSourceRecordEntry']));

        $bin = $extensions['bin'];
        $t->same(1, $bin['entryCount']);
        $t->same(['Extras/report.bin'], $bin['entryNames']);
        $t->same(['application/octet-stream' => 1], $bin['manifestMediaTypeBaseCounts']);
        $t->same(['binary' => 1], $bin['manifestMediaFamilyCounts']);
        $t->same('Extras/report.bin', $bin['largestSourceRecordEntry']['entryName']);

        $extensionless = $extensions['(none)'];
        $t->same(null, $extensionless['packagePartExtension']);
        $t->same(true, $extensionless['extensionlessPackagePart']);
        $t->same(3, $extensionless['entryCount']);
        $t->same(['Notes/private', 'Scripts/no-extension', 'mimetype'], $extensionless['entryNames']);
        $t->same(['/' => 1, 'Notes/' => 1, 'Scripts/' => 1], $extensionless['directoryRootCounts']);
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
    },
];

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function odf_zip_source_record_package_part_extension_index_by(array $items, string $key): array
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
function odf_zip_source_record_package_part_extension_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true || ($part['isDirectory'] ?? false) === true) {
            continue;
        }

        $key = odf_zip_source_record_package_part_extension_key($part);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function odf_zip_source_record_package_part_extension_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true || ($part['isDirectory'] ?? false) === true) {
            continue;
        }

        $key = odf_zip_source_record_package_part_extension_key($part);
        $sums[$key] = ($sums[$key] ?? 0) + (is_int($part[$field] ?? null) ? $part[$field] : 0);
    }

    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function odf_zip_source_record_package_part_extension_sum_for_key(array $inventory, string $key, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (odf_zip_source_record_package_part_extension_key($part) !== $key) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}

/**
 * @param array<string, mixed> $part
 */
function odf_zip_source_record_package_part_extension_key(array $part): string
{
    return is_string($part['zipPackageManifestPackagePartExtension'] ?? null)
        ? $part['zipPackageManifestPackagePartExtension']
        : (is_string($part['packagePartExtension'] ?? null) ? $part['packagePartExtension'] : '(none)');
}
