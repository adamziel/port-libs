<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$bidiControl = "\u{202e}";
$bidiPart = 'Pictures/bidi' . $bidiControl . '.png';

$manifestXml = str_replace('__BIDI_PART__', $bidiPart, <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/CON.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/trailing." manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="__BIDI_PART__" manifest:media-type="image/png"/>
</manifest:manifest>
XML);

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP name hygiene package review.</text:p>
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
    <dc:title>ZIP Name Hygiene Package Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/CON.png', 'data' => 'CONPNG', 'compressionMethod' => 0],
    ['name' => 'Pictures/trailing.', 'data' => 'TRAILINGPNG', 'compressionMethod' => 0],
    ['name' => $bidiPart, 'data' => 'BIDIPNG', 'compressionMethod' => 0],
], 'odf name hygiene package review');

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

$nameHygieneProjectionFromZip = static fn (array $entry): array => [
    'zipNameHygieneSegments' => $entry['segments'],
    'zipNameHygieneFlaggedSegmentCount' => count($entry['flaggedSegments']),
    'zipNameHygieneFlaggedSegments' => $entry['flaggedSegments'],
    'zipNameHygieneIssueCodes' => $entry['issues'],
    'hasZipNameHygieneIssue' => $entry['hasNameHygieneIssue'],
];

$projectedNameHygiene = static fn (array $entry): array => [
    'zipNameHygieneSegments' => $entry['zipNameHygieneSegments'],
    'zipNameHygieneFlaggedSegmentCount' => $entry['zipNameHygieneFlaggedSegmentCount'],
    'zipNameHygieneFlaggedSegments' => $entry['zipNameHygieneFlaggedSegments'],
    'zipNameHygieneIssueCodes' => $entry['zipNameHygieneIssueCodes'],
    'hasZipNameHygieneIssue' => $entry['hasZipNameHygieneIssue'],
];

return [
    'carries ODT ZIP name hygiene provenance through compact and rich package identities' => static function (TestRunner $t) use ($buildPackage, $indexBy, $nameHygieneProjectionFromZip, $projectedNameHygiene, $bidiPart): void {
        $package = $buildPackage();
        $zipNameHygiene = $package->nameHygienePreflight();
        $zipNameHygieneByName = $indexBy($zipNameHygiene['entries'], 'name');
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];

        $compactParts = $indexBy($compactInventory['parts'], 'path');
        $compactIdentityParts = $indexBy($compactIdentity['packageEntries'], 'path');
        $richParts = $richProvenance['parts'];
        $richIdentityParts = $indexBy($richIdentity['packageEntries'], 'part');

        $expectedCounts = [
            'nameHygieneReviewEntryCount' => 3,
            'nameHygieneLeadingOrTrailingWhitespaceEntryCount' => 0,
            'nameHygieneTrailingDotSegmentEntryCount' => 1,
            'nameHygieneWindowsReservedNameEntryCount' => 1,
            'nameHygieneWindowsAlternateDataStreamEntryCount' => 0,
            'nameHygieneUnicodeFormatControlEntryCount' => 1,
            'nameHygieneUnicodeBidiControlEntryCount' => 1,
        ];

        $t->same($zipNameHygiene, $compactInventory['nameHygiene']);
        $t->same($zipNameHygiene, $richProvenance['nameHygiene']);
        foreach ($expectedCounts as $key => $value) {
            $t->same($value, $compactInventory[$key], "{$key} compact inventory");
            $t->same($value, $compactIdentity[$key], "{$key} compact identity");
            $t->same($value, $richProvenance[$key], "{$key} rich provenance");
            $t->same($value, $richIdentity[$key], "{$key} rich identity");
        }

        $reserved = $nameHygieneProjectionFromZip($zipNameHygieneByName['Pictures/CON.png']);
        $trailing = $nameHygieneProjectionFromZip($zipNameHygieneByName['Pictures/trailing.']);
        $bidi = $nameHygieneProjectionFromZip($zipNameHygieneByName[$bidiPart]);
        foreach ([
            'Pictures/CON.png' => $reserved,
            'Pictures/trailing.' => $trailing,
            $bidiPart => $bidi,
        ] as $part => $expected) {
            $t->same($expected, $projectedNameHygiene($compactParts[$part]), "{$part} compact part");
            $t->same($expected, $projectedNameHygiene($compactIdentityParts[$part]), "{$part} compact identity");
            $t->same($expected, $projectedNameHygiene($richParts[$part]), "{$part} rich part");
            $t->same($expected, $projectedNameHygiene($richIdentityParts[$part]), "{$part} rich identity");
        }

        $t->same(['segment-windows-reserved-name'], $reserved['zipNameHygieneIssueCodes']);
        $t->same(['segment-trailing-dot'], $trailing['zipNameHygieneIssueCodes']);
        $t->same(['segment-unicode-format-control', 'segment-bidi-format-control'], $bidi['zipNameHygieneIssueCodes']);
        $t->same(['right-to-left-override'], $bidi['zipNameHygieneFlaggedSegments'][0]['bidiControlNames']);
        $t->same($compactParts['Pictures/CON.png']['canExposeBytes'], $richParts['Pictures/CON.png']['canExposeBytes']);
        $t->same(false, $richIdentity['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $richIdentity['byteExposurePolicy']);
    },
];
