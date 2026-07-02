<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP package manifest CRC32 rollups.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Package Manifest CRC32 Rollups</dc:title>
  </office:meta>
</office:document-meta>
XML;

$mediaBytes = 'REVIEW-PNG-DUPLICATE-PAYLOAD';
$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/review-copy.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$parts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/review.png', 'data' => $mediaBytes, 'compressionMethod' => 0],
    ['name' => 'Pictures/review-copy.png', 'data' => $mediaBytes, 'compressionMethod' => 0],
];

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $value = $item[$key] ?? null;
        if (is_string($value) && $value !== '') {
            $indexed[$value] = $item;
        }
    }

    return $indexed;
};

$crc32Fields = [
    'crc32SummaryCount' => 'zipPackageManifestCrc32SummaryCount',
    'crc32Summaries' => 'zipPackageManifestCrc32Summaries',
    'duplicateCrc32HexCount' => 'zipPackageManifestDuplicateCrc32HexCount',
    'duplicateCrc32EntryCount' => 'zipPackageManifestDuplicateCrc32EntryCount',
    'hasDuplicateCrc32Entries' => 'zipPackageManifestHasDuplicateCrc32Entries',
    'duplicateCrc32Hexes' => 'zipPackageManifestDuplicateCrc32Hexes',
    'duplicateCrc32Summaries' => 'zipPackageManifestDuplicateCrc32Summaries',
];

return [
    'carries ODT ZIP package manifest CRC32 rollups through compact and rich identities' => static function (TestRunner $t) use (
        $parts,
        $indexBy,
        $crc32Fields,
        $mediaBytes
    ): void {
        $package = ZipPackage::fromParts($parts, 'odt crc32 rollups');
        $zipManifest = $package->packageManifestPreflight();
        $zipManifestEntries = $indexBy($zipManifest['entries'], 'name');
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $duplicateCrc32Hex = $zipManifestEntries['Pictures/review.png']['crc32Hex'];

        $surfaces = [
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
            'document provenance' => $documentProvenance,
            'document identity' => $documentProvenance['packageIdentity'],
        ];

        foreach ($surfaces as $label => $surface) {
            foreach ($crc32Fields as $manifestKey => $surfaceKey) {
                $t->same($zipManifest[$manifestKey], $surface[$surfaceKey], "{$label} {$surfaceKey}");
            }

            $duplicateSummaries = $indexBy($surface['zipPackageManifestDuplicateCrc32Summaries'], 'crc32Hex');
            $duplicateSummary = $duplicateSummaries[$duplicateCrc32Hex];
            $t->same(1, $surface['zipPackageManifestDuplicateCrc32HexCount'], "{$label} duplicate crc32 hex count");
            $t->same(2, $surface['zipPackageManifestDuplicateCrc32EntryCount'], "{$label} duplicate crc32 entry count");
            $t->same(true, $surface['zipPackageManifestHasDuplicateCrc32Entries'], "{$label} duplicate crc32 flag");
            $t->same([$duplicateCrc32Hex], $surface['zipPackageManifestDuplicateCrc32Hexes'], "{$label} duplicate crc32 hexes");
            $t->same(2, $duplicateSummary['entryCount'], "{$label} duplicate crc32 summary entry count");
            $t->same(2, $duplicateSummary['fileEntryCount'], "{$label} duplicate crc32 file count");
            $t->same(0, $duplicateSummary['directoryEntryCount'], "{$label} duplicate crc32 directory count");
            $t->same(strlen($mediaBytes) * 2, $duplicateSummary['compressedBytes'], "{$label} duplicate crc32 compressed bytes");
            $t->same(strlen($mediaBytes) * 2, $duplicateSummary['uncompressedBytes'], "{$label} duplicate crc32 uncompressed bytes");
            $t->true($duplicateSummary['localRecordBytes'] > strlen($mediaBytes) * 2, "{$label} duplicate crc32 local record bytes");
            $t->true($duplicateSummary['sourceRecordBytes'] > $duplicateSummary['localRecordBytes'], "{$label} duplicate crc32 source record bytes");
            $t->same(0, $duplicateSummary['dataDescriptorEntryCount'], "{$label} duplicate crc32 data descriptor count");
            $t->same(0, $duplicateSummary['dataDescriptorBytes'], "{$label} duplicate crc32 data descriptor bytes");
            $t->same(['Pictures/'], $duplicateSummary['directoryRoots'], "{$label} duplicate crc32 directory roots");
            $t->same(['stored'], $duplicateSummary['compressionMethodNames'], "{$label} duplicate crc32 compression methods");
            $t->same(['Pictures/review-copy.png', 'Pictures/review.png'], $duplicateSummary['entryNames'], "{$label} duplicate crc32 entry names");
        }

        $t->same($duplicateCrc32Hex, $zipManifestEntries['Pictures/review-copy.png']['crc32Hex']);
        $t->same($richProvenance['zipPackageManifestCrc32Summaries'], $documentProvenance['zipPackageManifestCrc32Summaries']);
        $t->same($richIdentity['zipPackageManifestDuplicateCrc32Summaries'], $documentProvenance['packageIdentity']['zipPackageManifestDuplicateCrc32Summaries']);
    },
];
