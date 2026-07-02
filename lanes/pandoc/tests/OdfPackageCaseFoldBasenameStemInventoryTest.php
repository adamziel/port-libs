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
      <text:p>Case-folded basename stem package review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="CaseFoldStemBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Case Folded Basename Stem Review</dc:title>
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
], 'odt package case-folded basename stem provenance');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT package case-folded basename stems across package handoffs' => static function (TestRunner $t) use ($buildPackage, $indexBy): void {
        $manifest = json_decode(
            (string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $compactIdentityParts = $indexBy($compactIdentity['packageEntries'], 'path');
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $richIdentityParts = $indexBy($richIdentity['packageEntries'], 'part');
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $documentIdentity = $documentProvenance['packageIdentity'];

        $expectedCounts = [
            'content' => 2,
            'hero' => 2,
            'manifest' => 1,
            'meta' => 1,
            'mimetype' => 1,
            'no-extension' => 1,
            'pictures' => 1,
            'private' => 1,
            'statusbar' => 1,
            'styles' => 1,
        ];
        $expectedEntryNames = [
            'content' => ['content.xml', 'objects/content.xml'],
            'hero' => ['Pictures/HERO.PNG', 'thumbnails/hero.png'],
            'manifest' => ['META-INF/manifest.xml'],
            'meta' => ['meta.xml'],
            'mimetype' => ['mimetype'],
            'no-extension' => ['Pictures/no-extension'],
            'pictures' => ['Pictures/'],
            'private' => ['Notes/private'],
            'statusbar' => ['Configurations2/statusbar/statusbar.xml'],
            'styles' => ['styles.xml'],
        ];
        $expectedDuplicateSummaries = [
            [
                'caseFoldStemKey' => 'content',
                'entryCount' => 2,
                'packageBasenameStems' => ['content'],
                'packageBasenames' => ['content.xml'],
                'entryNames' => ['content.xml', 'objects/content.xml'],
            ],
            [
                'caseFoldStemKey' => 'hero',
                'entryCount' => 2,
                'packageBasenameStems' => ['HERO', 'hero'],
                'packageBasenames' => ['HERO.PNG', 'hero.png'],
                'entryNames' => ['Pictures/HERO.PNG', 'thumbnails/hero.png'],
            ],
        ];

        foreach ([
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
            'document provenance' => $documentProvenance,
            'document identity' => $documentIdentity,
        ] as $label => $handoff) {
            $t->same($expectedCounts, $handoff['packageCaseFoldedBasenameStemCounts'], "{$label} folded basename stem counts");
            $t->same($expectedEntryNames, $handoff['entryNamesByPackageCaseFoldedBasenameStem'], "{$label} folded basename stem entry names");
            $t->same(2, $handoff['caseFoldedPackageBasenameStemDuplicateCount'], "{$label} folded basename stem duplicate count");
            $t->same(4, $handoff['caseFoldedPackageBasenameStemDuplicateEntryCount'], "{$label} folded basename stem duplicate entries");
            $t->same($expectedDuplicateSummaries, $handoff['caseFoldedPackageBasenameStemDuplicateSummaries'], "{$label} folded basename stem duplicate summaries");
        }

        $t->same($compactInventory['packageCaseFoldedBasenameStemCounts'], $richProvenance['packageCaseFoldedBasenameStemCounts']);
        $t->same($richProvenance['packageCaseFoldedBasenameStemCounts'], $documentProvenance['packageCaseFoldedBasenameStemCounts']);
        $t->same($richIdentity['packageCaseFoldedBasenameStemCounts'], $documentIdentity['packageCaseFoldedBasenameStemCounts']);
        $t->same('hero', $compactInventory['parts']['Pictures/HERO.PNG']['packageCaseFoldedBasenameStem']);
        $t->same('hero', $compactIdentityParts['Pictures/HERO.PNG']['packageCaseFoldedBasenameStem']);
        $t->same('hero', $richProvenance['parts']['Pictures/HERO.PNG']['packageCaseFoldedBasenameStem']);
        $t->same('hero', $richIdentityParts['Pictures/HERO.PNG']['packageCaseFoldedBasenameStem']);
        $t->same('content', $richIdentityParts['objects/content.xml']['packageCaseFoldedBasenameStem']);
        $t->same('pictures', $richProvenance['parts']['Pictures/']['packageCaseFoldedBasenameStem']);

        $t->same(1, $manifest['mappedOdfPackageCaseFoldBasenameStemInventoryCases'] ?? null);
        $t->same(45, $manifest['odfPackageCaseFoldBasenameStemInventoryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedOdfPackageCaseFoldBasenameStemInventoryCases'] ?? null);
        $t->same(45, $manifest['benchmarkDenominator']['breakdown']['odfPackageCaseFoldBasenameStemInventoryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedOdfPackageCaseFoldBasenameStemInventoryCases'] ?? null);
        $t->same(45, $manifest['benchmarkDenominator']['inventory']['odfPackageCaseFoldBasenameStemInventoryAssertions'] ?? null);
    },
];
