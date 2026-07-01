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
  <manifest:file-entry manifest:full-path="pictures/thumb.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Objects/Pictures/readme.txt" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:full-path="Objects/pictures/icon.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Configurations2/statusbar/statusbar.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Thumbnails/thumbnail.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package directory base name review.</text:p>
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
    <dc:title>Package Directory Base Name Review</dc:title>
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
    ['name' => 'pictures/thumb.png', 'data' => 'thumbdata', 'compressionMethod' => 0],
    ['name' => 'Objects/Pictures/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Objects/Pictures/readme.txt', 'data' => str_repeat('R', 31), 'compressionMethod' => 0],
    ['name' => 'Objects/pictures/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Objects/pictures/icon.png', 'data' => str_repeat('I', 17), 'compressionMethod' => 0],
    ['name' => 'Configurations2/statusbar/statusbar.xml', 'data' => '<statusbar/>', 'compressionMethod' => 0],
    ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMB', 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odt package case-fold directory base name provenance');

$indexEntries = static function (array $entries, string $key): array {
    $indexed = [];
    foreach ($entries as $entry) {
        $indexed[(string) $entry[$key]] = $entry;
    }

    return $indexed;
};

$indexGroups = static function (array $groups): array {
    $indexed = [];
    foreach ($groups as $group) {
        $indexed[(string) $group['caseFoldDirectoryBaseName']] = $group;
    }

    return $indexed;
};

return [
    'summarizes ODT package case-fold directory base names across compact and rich provenance' => static function (TestRunner $t) use ($buildPackage, $indexEntries, $indexGroups): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $compactIdentityParts = $indexEntries($compactIdentity['packageEntries'], 'path');

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $richIdentityParts = $indexEntries($richIdentity['packageEntries'], 'part');

        $expectedDirectoryBaseNameCounts = [
            'META-INF' => 1,
            'Notes' => 1,
            'Pictures' => 4,
            'Thumbnails' => 1,
            'pictures' => 3,
            'statusbar' => 1,
        ];
        $expectedCaseFoldDirectoryBaseNameCounts = [
            'meta-inf' => 1,
            'notes' => 1,
            'pictures' => 7,
            'statusbar' => 1,
            'thumbnails' => 1,
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity] as $handoff) {
            $t->same(6, $handoff['packageDirectoryBaseNameCount']);
            $t->same($expectedDirectoryBaseNameCounts, $handoff['packageDirectoryBaseNameCounts']);
            $t->same(5, $handoff['packageCaseFoldDirectoryBaseNameCount']);
            $t->same($expectedCaseFoldDirectoryBaseNameCounts, $handoff['packageCaseFoldDirectoryBaseNameCounts']);
            $t->same(1, $handoff['duplicatePackageCaseFoldDirectoryBaseNameCount']);
            $t->same(['pictures'], $handoff['duplicatePackageCaseFoldDirectoryBaseNames']);
        }

        $t->same(
            ['Objects/Pictures/', 'Objects/Pictures/readme.txt', 'Pictures/', 'Pictures/HERO.PNG'],
            $compactInventory['entryNamesByPackageDirectoryBaseName']['Pictures']
        );
        $t->same(
            [
                'Objects/Pictures/',
                'Objects/Pictures/readme.txt',
                'Objects/pictures/',
                'Objects/pictures/icon.png',
                'Pictures/',
                'Pictures/HERO.PNG',
                'pictures/thumb.png',
            ],
            $richProvenance['entryNamesByPackageCaseFoldDirectoryBaseName']['pictures']
        );

        $compactGroups = $indexGroups($compactInventory['packageCaseFoldDirectoryBaseNames']);
        $richGroups = $indexGroups($richProvenance['packageCaseFoldDirectoryBaseNames']);
        foreach ([$compactGroups['pictures'], $richGroups['pictures']] as $picturesGroup) {
            $t->same(2, $picturesGroup['directoryBaseNameVariantCount']);
            $t->same(4, $picturesGroup['directoryCount']);
            $t->same(7, $picturesGroup['entryCount']);
            $t->same(4, $picturesGroup['fileEntryCount']);
            $t->same(3, $picturesGroup['directoryEntryCount']);
            $t->same(64, $picturesGroup['byteLength']);
            $t->same(['Pictures' => 4, 'pictures' => 3], $picturesGroup['directoryBaseNameCounts']);
            $t->same([
                'Objects/Pictures/' => 2,
                'Objects/pictures/' => 2,
                'Pictures/' => 2,
                'pictures/' => 1,
            ], $picturesGroup['packageDirectoryCounts']);
            $t->same(['image/png' => 3, 'text/plain' => 1], $picturesGroup['manifestMediaTypeBaseCounts']);
            $t->same(
                [
                    'Objects/Pictures/',
                    'Objects/Pictures/readme.txt',
                    'Objects/pictures/',
                    'Objects/pictures/icon.png',
                    'Pictures/',
                    'Pictures/HERO.PNG',
                    'pictures/thumb.png',
                ],
                $picturesGroup['entryNames']
            );

            $largest = $picturesGroup['largestEntry'];
            $t->same('Objects/Pictures/readme.txt', $largest['entryName']);
            $t->same('Objects/Pictures/', $largest['packageDirectory']);
            $t->same('Pictures', $largest['directoryBaseName']);
            $t->same('pictures', $largest['caseFoldDirectoryBaseName']);
            $t->same('readme.txt', $largest['packageBasename']);
            $t->same(3, $largest['packagePathDepth']);
            $t->same('txt', $largest['packagePartExtension']);
            $t->same(31, $largest['byteLength']);
        }

        $t->same($compactInventory['packageDirectoryBaseNameCounts'], $richProvenance['packageDirectoryBaseNameCounts']);
        $t->same($richProvenance['packageCaseFoldDirectoryBaseNameCounts'], $documentProvenance['packageCaseFoldDirectoryBaseNameCounts']);
        $t->same('Pictures', $compactInventory['parts']['Objects/Pictures/readme.txt']['pathShape']['directoryBaseName']);
        $t->same('Pictures', $compactInventory['parts']['Objects/Pictures/readme.txt']['packageDirectoryBaseName']);
        $t->same('Pictures', $compactIdentityParts['Objects/Pictures/readme.txt']['packageDirectoryBaseName']);
        $t->same('pictures', $richProvenance['parts']['Objects/pictures/icon.png']['packagePathShape']['directoryBaseName']);
        $t->same('pictures', $richProvenance['parts']['Objects/pictures/icon.png']['packageDirectoryBaseName']);
        $t->same('pictures', $richIdentityParts['Objects/pictures/icon.png']['packageDirectoryBaseName']);
    },
];
