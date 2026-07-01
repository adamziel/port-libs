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
  <manifest:file-entry manifest:full-path="Pictures/HERO.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Object/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="OBJECT/settings.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/statusbar/statusbar.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package path segment review.</text:p>
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
    <dc:title>Package Path Segment Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Pictures/HERO.PNG', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'pictures/hero.png', 'data' => 'thumb', 'compressionMethod' => 0],
    ['name' => 'Object/content.xml', 'data' => '<object/>', 'compressionMethod' => 0],
    ['name' => 'OBJECT/settings.xml', 'data' => '<settings/>', 'compressionMethod' => 0],
    ['name' => 'Configurations2/statusbar/statusbar.xml', 'data' => '<statusbar/>', 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odt package path segment provenance');

$indexIdentityEntries = static function (array $entries, string $key): array {
    $indexed = [];
    foreach ($entries as $entry) {
        $indexed[(string) $entry[$key]] = $entry;
    }

    return $indexed;
};

return [
    'summarizes ODT package path segments across compact and rich package provenance' => static function (TestRunner $t) use ($buildPackage, $indexIdentityEntries): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $richIdentityParts = $indexIdentityEntries($richIdentity['packageEntries'], 'part');

        $expectedSegmentCounts = [
            'Configurations2' => 1,
            'HERO.PNG' => 1,
            'META-INF' => 1,
            'Notes' => 1,
            'OBJECT' => 1,
            'Object' => 1,
            'Pictures' => 2,
            'content.xml' => 2,
            'hero.png' => 1,
            'manifest.xml' => 1,
            'meta.xml' => 1,
            'mimetype' => 1,
            'pictures' => 1,
            'private' => 1,
            'settings.xml' => 1,
            'statusbar' => 1,
            'statusbar.xml' => 1,
            'styles.xml' => 1,
        ];
        $expectedCaseFoldedSegmentCounts = [
            'configurations2' => 1,
            'content.xml' => 2,
            'hero.png' => 2,
            'manifest.xml' => 1,
            'meta-inf' => 1,
            'meta.xml' => 1,
            'mimetype' => 1,
            'notes' => 1,
            'object' => 2,
            'pictures' => 3,
            'private' => 1,
            'settings.xml' => 1,
            'statusbar' => 1,
            'statusbar.xml' => 1,
            'styles.xml' => 1,
        ];
        $expectedRepeatedSegmentSummaries = [
            [
                'packagePathSegment' => 'Pictures',
                'occurrenceCount' => 2,
                'entryCount' => 2,
                'entryNames' => ['Pictures/', 'Pictures/HERO.PNG'],
            ],
            [
                'packagePathSegment' => 'content.xml',
                'occurrenceCount' => 2,
                'entryCount' => 2,
                'entryNames' => ['Object/content.xml', 'content.xml'],
            ],
        ];
        $expectedCaseFoldedSegmentSummaries = [
            [
                'caseFoldKey' => 'content.xml',
                'occurrenceCount' => 2,
                'entryCount' => 2,
                'packagePathSegments' => ['content.xml'],
                'entryNames' => ['Object/content.xml', 'content.xml'],
            ],
            [
                'caseFoldKey' => 'hero.png',
                'occurrenceCount' => 2,
                'entryCount' => 2,
                'packagePathSegments' => ['HERO.PNG', 'hero.png'],
                'entryNames' => ['Pictures/HERO.PNG', 'pictures/hero.png'],
            ],
            [
                'caseFoldKey' => 'object',
                'occurrenceCount' => 2,
                'entryCount' => 2,
                'packagePathSegments' => ['OBJECT', 'Object'],
                'entryNames' => ['OBJECT/settings.xml', 'Object/content.xml'],
            ],
            [
                'caseFoldKey' => 'pictures',
                'occurrenceCount' => 3,
                'entryCount' => 3,
                'packagePathSegments' => ['Pictures', 'pictures'],
                'entryNames' => ['Pictures/', 'Pictures/HERO.PNG', 'pictures/hero.png'],
            ],
        ];

        foreach ([$compactInventory, $richProvenance] as $handoff) {
            $t->same($expectedSegmentCounts, $handoff['packagePathSegmentCounts']);
            $t->same($expectedCaseFoldedSegmentCounts, $handoff['packageCaseFoldedPathSegmentCounts']);
            $t->same(['Pictures/', 'Pictures/HERO.PNG'], $handoff['entryNamesByPackagePathSegment']['Pictures']);
            $t->same(['Pictures/', 'Pictures/HERO.PNG', 'pictures/hero.png'], $handoff['entryNamesByPackageCaseFoldedPathSegment']['pictures']);
        }

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity] as $handoff) {
            $t->same($expectedSegmentCounts, $handoff['packagePathSegmentCounts']);
            $t->same($expectedCaseFoldedSegmentCounts, $handoff['packageCaseFoldedPathSegmentCounts']);
            $t->same(2, $handoff['repeatedPackagePathSegmentCount']);
            $t->same(4, $handoff['repeatedPackagePathSegmentEntryCount']);
            $t->same($expectedRepeatedSegmentSummaries, $handoff['repeatedPackagePathSegmentSummaries']);
            $t->same(4, $handoff['caseFoldedPackagePathSegmentDuplicateCount']);
            $t->same(9, $handoff['caseFoldedPackagePathSegmentDuplicateEntryCount']);
            $t->same($expectedCaseFoldedSegmentSummaries, $handoff['caseFoldedPackagePathSegmentDuplicateSummaries']);
        }

        $t->same($richProvenance['packagePathSegmentCounts'], $documentProvenance['packagePathSegmentCounts']);
        $t->same(['Pictures', 'HERO.PNG'], $compactInventory['parts']['Pictures/HERO.PNG']['pathShape']['segments']);
        $t->same(['Pictures', 'HERO.PNG'], $richProvenance['parts']['Pictures/HERO.PNG']['packagePathShape']['segments']);
        $t->same(['Pictures', 'HERO.PNG'], $richIdentityParts['Pictures/HERO.PNG']['packagePathShape']['segments']);
    },
];
