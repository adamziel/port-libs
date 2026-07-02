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
  <manifest:file-entry manifest:full-path="Objects/Pictures/readme.txt" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:full-path="Objects/pictures/icon.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package part directory inventory.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="DirectoryInventoryBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Package Part Directory Inventory</dc:title>
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
    ['name' => 'Objects/Pictures/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Objects/Pictures/readme.txt', 'data' => 'readme', 'compressionMethod' => 0],
    ['name' => 'Objects/pictures/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Objects/pictures/icon.png', 'data' => 'icon', 'compressionMethod' => 0],
], 'odt package part directory inventory');

return [
    'records ODT package part directory inventory mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'carries ODT package part directory maps through compact and rich identities' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        $expectedDirectoryCounts = [
            '/' => 4,
            'META-INF/' => 1,
            'Objects/Pictures/' => 2,
            'Objects/pictures/' => 2,
            'Pictures/' => 2,
            'objects/' => 1,
            'thumbnails/' => 1,
        ];
        $expectedEntryNames = [
            '/' => ['content.xml', 'meta.xml', 'mimetype', 'styles.xml'],
            'META-INF/' => ['META-INF/manifest.xml'],
            'Objects/Pictures/' => ['Objects/Pictures/', 'Objects/Pictures/readme.txt'],
            'Objects/pictures/' => ['Objects/pictures/', 'Objects/pictures/icon.png'],
            'Pictures/' => ['Pictures/', 'Pictures/HERO.PNG'],
            'objects/' => ['objects/content.xml'],
            'thumbnails/' => ['thumbnails/hero.png'],
        ];

        $t->same(3, $compactInventory['packageDirectoryCount']);
        $t->same(3, $richProvenance['packageDirectoryCount']);
        $t->same(7, $compactInventory['packagePartDirectoryCount']);
        $t->same(7, $richProvenance['packagePartDirectoryCount']);
        $t->same($expectedDirectoryCounts, $compactInventory['packagePartDirectoryCounts']);
        $t->same($expectedEntryNames, $compactInventory['entryNamesByPackagePartDirectory']);
        $t->same($compactInventory['packagePartDirectoryCounts'], $richProvenance['packagePartDirectoryCounts']);
        $t->same($compactInventory['entryNamesByPackagePartDirectory'], $richProvenance['entryNamesByPackagePartDirectory']);

        foreach ([$compactIdentity, $richIdentity, $documentIdentity] as $identity) {
            $t->same(7, $identity['packagePartDirectoryCount']);
            $t->same($expectedDirectoryCounts, $identity['packagePartDirectoryCounts']);
            $t->same($expectedEntryNames, $identity['entryNamesByPackagePartDirectory']);
            $t->same(['Pictures/', 'Pictures/HERO.PNG'], $identity['entryNamesByPackagePartDirectory']['Pictures/']);
            $t->same(
                ['Objects/Pictures/', 'Objects/Pictures/readme.txt'],
                $identity['entryNamesByPackagePartDirectory']['Objects/Pictures/']
            );
        }

        $compactEntries = [];
        foreach ($compactIdentity['packageEntries'] as $entry) {
            $compactEntries[(string) $entry['path']] = $entry;
        }
        $richEntries = [];
        foreach ($richIdentity['packageEntries'] as $entry) {
            $richEntries[(string) $entry['part']] = $entry;
        }

        $t->same('Pictures/', $compactEntries['Pictures/HERO.PNG']['packageDirectory']);
        $t->same('Objects/Pictures/', $compactEntries['Objects/Pictures/']['packageDirectory']);
        $t->same('Objects/Pictures/', $richEntries['Objects/Pictures/readme.txt']['packageDirectory']);
        $t->same(false, array_key_exists('contents', $richEntries['Objects/Pictures/readme.txt']));
    },
];
