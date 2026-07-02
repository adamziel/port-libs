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
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package top-level segment lookup maps.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="ReviewBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Package top-level segment lookup maps</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/raw.bin', 'data' => 'RAWPICTURE', 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => '<script/>', 'compressionMethod' => 0],
    ['name' => 'basic/Module/Other.xml', 'data' => '<script/>', 'compressionMethod' => 0],
    ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accel/>', 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odt package top-level segment lookup maps');

return [
    'carries ODT package top-level segment lookup maps through package identity' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $documentIdentity = $documentProvenance['packageIdentity'];

        $expectedCounts = [
            'Basic' => 1,
            'Configurations2' => 1,
            'META-INF' => 1,
            'Notes' => 1,
            'Pictures' => 2,
            'basic' => 1,
            'content.xml' => 1,
            'meta.xml' => 1,
            'mimetype' => 1,
            'styles.xml' => 1,
        ];
        $expectedEntryNames = [
            'Basic' => ['Basic/Standard/Review.xml'],
            'Configurations2' => ['Configurations2/accelerator/current.xml'],
            'META-INF' => ['META-INF/manifest.xml'],
            'Notes' => ['Notes/private'],
            'Pictures' => ['Pictures/hero.png', 'Pictures/raw.bin'],
            'basic' => ['basic/Module/Other.xml'],
            'content.xml' => ['content.xml'],
            'meta.xml' => ['meta.xml'],
            'mimetype' => ['mimetype'],
            'styles.xml' => ['styles.xml'],
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance, $documentIdentity] as $handoff) {
            $t->same($expectedCounts, $handoff['packageTopLevelSegmentCounts']);
            $t->same($expectedEntryNames, $handoff['entryNamesByPackageTopLevelSegment']);
        }

        $compactEntries = odf_package_top_level_segment_lookup_index_by($compactIdentity['packageEntries'], 'path');
        $richEntries = odf_package_top_level_segment_lookup_index_by($richIdentity['packageEntries'], 'part');
        $documentEntries = odf_package_top_level_segment_lookup_index_by($documentIdentity['packageEntries'], 'part');

        $t->same('Pictures', $compactInventory['parts']['Pictures/raw.bin']['packageTopLevelSegment']);
        $t->same('Pictures', $compactEntries['Pictures/raw.bin']['packageTopLevelSegment']);
        $t->same('Basic', $richProvenance['parts']['Basic/Standard/Review.xml']['packageTopLevelSegment']);
        $t->same('basic', $richProvenance['parts']['basic/Module/Other.xml']['packageTopLevelSegment']);
        $t->same('Basic', $richEntries['Basic/Standard/Review.xml']['packageTopLevelSegment']);
        $t->same('basic', $documentEntries['basic/Module/Other.xml']['packageTopLevelSegment']);
        $t->same(['Basic' => 1, 'basic' => 1], $richProvenance['packageCaseFoldTopLevelSegments'][0]['topLevelSegmentCounts']);
        $t->same(['Pictures/hero.png', 'Pictures/raw.bin'], $documentIdentity['entryNamesByPackageTopLevelSegment']['Pictures']);
    },
];

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function odf_package_top_level_segment_lookup_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && is_string($item[$key] ?? null)) {
            $indexed[$item[$key]] = $item;
        }
    }

    return $indexed;
}
