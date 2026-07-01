<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$imageBytes = 'PNGDATA';
$objectXml = '<chart/>';
$configurationXml = '<statusbar/>';
$scriptBytes = "function areaByteReview() { return true; }\n";
$privateBytes = 'PRIVATE-NOTE';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Object 1/" manifest:media-type="application/vnd.oasis.opendocument.chart"/>
  <manifest:file-entry manifest:full-path="Object 1/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/statusbar/statusbar.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Scripts/review.js" manifest:media-type="application/javascript"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ODF package area byte identity review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="AreaByteBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ODF package area byte identity review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => $imageBytes, 'compressionMethod' => 0],
    ['name' => 'Object 1/content.xml', 'data' => $objectXml, 'compressionMethod' => 0],
    ['name' => 'Configurations2/statusbar/statusbar.xml', 'data' => $configurationXml, 'compressionMethod' => 0],
    ['name' => 'Scripts/review.js', 'data' => $scriptBytes, 'compressionMethod' => 0],
    ['name' => 'Notes/private.txt', 'data' => $privateBytes, 'compressionMethod' => 0],
], 'odt package area byte identity provenance');

$summaryByArea = static function (array $summaries): array {
    $indexed = [];
    foreach ($summaries as $summary) {
        $indexed[(string) $summary['packageArea']] = $summary;
    }

    return $indexed;
};

$sumMap = static fn (array $values): int => array_sum(array_map(static fn ($value): int => is_int($value) ? $value : 0, $values));

return [
    'mirrors ODT package area byte buckets into compact and rich package identities' => static function (TestRunner $t) use (
        $buildPackage,
        $summaryByArea,
        $sumMap,
        $manifestXml,
        $contentXml,
        $stylesXml,
        $metaXml,
        $imageBytes,
        $objectXml,
        $configurationXml,
        $scriptBytes,
        $privateBytes
    ): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $documentIdentity = $documentProvenance['packageIdentity'];

        $expectedAreaBytes = [
            '/' => strlen(OdfReader::MIMETYPE) + strlen($contentXml) + strlen($stylesXml) + strlen($metaXml),
            'Configurations2/' => strlen($configurationXml),
            'META-INF/' => strlen($manifestXml),
            'Notes/' => strlen($privateBytes),
            'Object 1/' => strlen($objectXml),
            'Pictures/' => strlen($imageBytes),
            'Scripts/' => strlen($scriptBytes),
        ];
        ksort($expectedAreaBytes, SORT_STRING);
        $expectedTotalBytes = array_sum($expectedAreaBytes);

        foreach ([
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
            'document provenance' => $documentProvenance,
            'document identity' => $documentIdentity,
        ] as $label => $handoff) {
            $t->same($expectedAreaBytes, $handoff['packageAreaByteLengths'], "{$label} area byte lengths");
            $t->same($expectedAreaBytes, $handoff['packageAreaCompressedByteLengths'], "{$label} compressed area byte lengths");
            $t->same($expectedTotalBytes, $sumMap($handoff['packageAreaByteLengths']), "{$label} total byte length");
            $t->same($expectedTotalBytes, $sumMap($handoff['packageAreaCompressedByteLengths']), "{$label} total compressed byte length");
        }

        foreach (['packageAreaByteLengths', 'packageAreaCompressedByteLengths'] as $field) {
            $t->same($compactInventory[$field], $compactIdentity[$field], "{$field} compact identity");
            $t->same($richProvenance[$field], $richIdentity[$field], "{$field} rich identity");
            $t->same($richProvenance[$field], $documentProvenance[$field], "{$field} document provenance");
            $t->same($richIdentity[$field], $documentIdentity[$field], "{$field} document identity");
        }

        $compactAreas = $summaryByArea($compactInventory['packageAreaSummaries']);
        $richAreas = $summaryByArea($richProvenance['packageAreaSummaries']);
        foreach ($expectedAreaBytes as $area => $byteLength) {
            $t->same($byteLength, $compactAreas[$area]['byteLength'], "compact {$area} summary bytes");
            $t->same($byteLength, $compactAreas[$area]['compressedByteLength'], "compact {$area} summary compressed bytes");
            $t->same($compactAreas[$area], $richAreas[$area], "rich {$area} summary parity");
        }

        $t->same(['package-bytes-exposable' => 1], $compactAreas['Pictures/']['byteExposurePolicyCounts']);
        $t->same(['script-package-bytes-blocked' => 1], $compactAreas['Scripts/']['byteExposurePolicyCounts']);
        $t->same(['undeclared-package-entry-no-bytes' => 1], $compactAreas['Notes/']['byteExposurePolicyCounts']);
    },
];
