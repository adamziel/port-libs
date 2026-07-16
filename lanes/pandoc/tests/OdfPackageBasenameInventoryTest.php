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
  <manifest:file-entry manifest:full-path="thumbnails/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="objects/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/statusbar/statusbar.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/no-extension" manifest:media-type="image/svg+xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package basename review.</text:p>
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
    <dc:title>Package Basename Review</dc:title>
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
    ['name' => 'thumbnails/hero.png', 'data' => 'thumb', 'compressionMethod' => 0],
    ['name' => 'objects/content.xml', 'data' => '<object/>', 'compressionMethod' => 0],
    ['name' => 'Configurations2/statusbar/statusbar.xml', 'data' => '<statusbar/>', 'compressionMethod' => 0],
    ['name' => 'Pictures/no-extension', 'data' => '<svg/>', 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odt package basename provenance');

$indexIdentityEntries = static function (array $entries, string $key): array {
    $indexed = [];
    foreach ($entries as $entry) {
        $indexed[(string) $entry[$key]] = $entry;
    }

    return $indexed;
};

return [
    'summarizes ODT package basenames across compact and rich package provenance' => static function (TestRunner $t) use ($buildPackage, $indexIdentityEntries): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $richIdentityParts = $indexIdentityEntries($richIdentity['packageEntries'], 'part');

        $expectedBasenameCounts = [
            'HERO.PNG' => 1,
            'Pictures' => 1,
            'content.xml' => 2,
            'hero.png' => 1,
            'manifest.xml' => 1,
            'meta.xml' => 1,
            'mimetype' => 1,
            'no-extension' => 1,
            'private' => 1,
            'statusbar.xml' => 1,
            'styles.xml' => 1,
        ];
        $expectedStemCounts = [
            'HERO' => 1,
            'Pictures' => 1,
            'content' => 2,
            'hero' => 1,
            'manifest' => 1,
            'meta' => 1,
            'mimetype' => 1,
            'no-extension' => 1,
            'private' => 1,
            'statusbar' => 1,
            'styles' => 1,
        ];
        $expectedCaseFoldedCounts = [
            'content.xml' => 2,
            'hero.png' => 2,
            'manifest.xml' => 1,
            'meta.xml' => 1,
            'mimetype' => 1,
            'no-extension' => 1,
            'pictures' => 1,
            'private' => 1,
            'statusbar.xml' => 1,
            'styles.xml' => 1,
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity] as $handoff) {
            $t->same($expectedBasenameCounts, $handoff['packageBasenameCounts']);
            $t->same($expectedStemCounts, $handoff['packageBasenameStemCounts']);
            $t->same($expectedCaseFoldedCounts, $handoff['packageCaseFoldedBasenameCounts']);
            $t->same(1, $handoff['duplicatePackageBasenameCount']);
            $t->same(2, $handoff['duplicatePackageBasenameEntryCount']);
            $t->same([
                [
                    'packageBasename' => 'content.xml',
                    'entryCount' => 2,
                    'entryNames' => ['content.xml', 'objects/content.xml'],
                ],
            ], $handoff['duplicatePackageBasenameSummaries']);
            $t->same(2, $handoff['caseFoldedPackageBasenameDuplicateCount']);
            $t->same(4, $handoff['caseFoldedPackageBasenameDuplicateEntryCount']);
            $t->same([
                [
                    'caseFoldKey' => 'content.xml',
                    'entryCount' => 2,
                    'packageBasenames' => ['content.xml'],
                    'entryNames' => ['content.xml', 'objects/content.xml'],
                ],
                [
                    'caseFoldKey' => 'hero.png',
                    'entryCount' => 2,
                    'packageBasenames' => ['HERO.PNG', 'hero.png'],
                    'entryNames' => ['Pictures/HERO.PNG', 'thumbnails/hero.png'],
                ],
            ], $handoff['caseFoldedPackageBasenameDuplicateSummaries']);
        }

        $t->same(['content.xml', 'objects/content.xml'], $compactInventory['entryNamesByPackageBasename']['content.xml']);
        $t->same(['Pictures/HERO.PNG', 'thumbnails/hero.png'], $richProvenance['entryNamesByPackageCaseFoldedBasename']['hero.png']);
        $t->same($compactInventory['packageBasenameCounts'], $richProvenance['packageBasenameCounts']);
        $t->same($richProvenance['packageBasenameCounts'], $documentProvenance['packageBasenameCounts']);

        $t->same('HERO.PNG', $compactInventory['parts']['Pictures/HERO.PNG']['pathShape']['basename']);
        $t->same('HERO.PNG', $richProvenance['parts']['Pictures/HERO.PNG']['packageBasename']);
        $t->same('HERO.PNG', $richIdentityParts['Pictures/HERO.PNG']['packageBasename']);
        $t->same('Pictures', $richProvenance['parts']['Pictures/']['packageBasename']);
        $t->same('content.xml', $richIdentityParts['objects/content.xml']['packageBasename']);
    },
];
