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
  <manifest:file-entry manifest:full-path="Pictures.assets/HERO.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="pictures.raw/thumb.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Objects/Pictures.assets/readme.txt" manifest:media-type="text/plain"/>
  <manifest:file-entry manifest:full-path="Objects/pictures/icon.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Thumbnails/thumbnail.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package directory base name stem review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="StemReviewBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Package Directory Base Name Stem Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures.assets/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Pictures.assets/HERO.PNG', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'pictures.raw/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'pictures.raw/thumb.png', 'data' => 'thumbdata', 'compressionMethod' => 0],
    ['name' => 'Objects/Pictures.assets/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Objects/Pictures.assets/readme.txt', 'data' => str_repeat('R', 31), 'compressionMethod' => 0],
    ['name' => 'Objects/pictures/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Objects/pictures/icon.png', 'data' => str_repeat('I', 17), 'compressionMethod' => 0],
    ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMB', 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odt package directory base name stem provenance');

$indexEntries = static function (array $entries, string $key): array {
    $indexed = [];
    foreach ($entries as $entry) {
        $indexed[(string) $entry[$key]] = $entry;
    }

    return $indexed;
};

$indexGroups = static function (array $groups, string $key): array {
    $indexed = [];
    foreach ($groups as $group) {
        $indexed[(string) $group[$key]] = $group;
    }

    return $indexed;
};

return [
    'summarizes ODT package directory base-name stems across compact and rich provenance' => static function (TestRunner $t) use ($buildPackage, $indexEntries, $indexGroups): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $compactIdentityParts = $indexEntries($compactIdentity['packageEntries'], 'path');

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $richIdentityParts = $indexEntries($richIdentity['packageEntries'], 'part');

        $expectedStemCounts = [
            'META-INF' => 1,
            'Notes' => 1,
            'Pictures' => 4,
            'Thumbnails' => 1,
            'pictures' => 4,
        ];
        $expectedCaseFoldStemCounts = [
            'meta-inf' => 1,
            'notes' => 1,
            'pictures' => 8,
            'thumbnails' => 1,
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity] as $handoff) {
            $t->same(5, $handoff['packageDirectoryBaseNameStemCount']);
            $t->same($expectedStemCounts, $handoff['packageDirectoryBaseNameStemCounts']);
            $t->same(2, $handoff['duplicatePackageDirectoryBaseNameStemCount']);
            $t->same(['Pictures', 'pictures'], $handoff['duplicatePackageDirectoryBaseNameStems']);
            $t->same(4, $handoff['packageCaseFoldDirectoryBaseNameStemCount']);
            $t->same($expectedCaseFoldStemCounts, $handoff['packageCaseFoldDirectoryBaseNameStemCounts']);
            $t->same(1, $handoff['duplicatePackageCaseFoldDirectoryBaseNameStemCount']);
            $t->same(['pictures'], $handoff['duplicatePackageCaseFoldDirectoryBaseNameStems']);
        }

        $t->same(
            ['Objects/Pictures.assets/', 'Objects/Pictures.assets/readme.txt', 'Pictures.assets/', 'Pictures.assets/HERO.PNG'],
            $compactInventory['entryNamesByPackageDirectoryBaseNameStem']['Pictures']
        );
        $t->same(
            [
                'Objects/Pictures.assets/',
                'Objects/Pictures.assets/readme.txt',
                'Objects/pictures/',
                'Objects/pictures/icon.png',
                'Pictures.assets/',
                'Pictures.assets/HERO.PNG',
                'pictures.raw/',
                'pictures.raw/thumb.png',
            ],
            $richProvenance['entryNamesByPackageCaseFoldDirectoryBaseNameStem']['pictures']
        );

        $compactStemGroups = $indexGroups($compactInventory['packageDirectoryBaseNameStems'], 'directoryBaseNameStem');
        $compactCaseFoldStemGroups = $indexGroups($compactInventory['packageCaseFoldDirectoryBaseNameStems'], 'caseFoldDirectoryBaseNameStem');
        $richStemGroups = $indexGroups($richProvenance['packageDirectoryBaseNameStems'], 'directoryBaseNameStem');
        $richCaseFoldStemGroups = $indexGroups($richProvenance['packageCaseFoldDirectoryBaseNameStems'], 'caseFoldDirectoryBaseNameStem');

        foreach ([$compactStemGroups['Pictures'], $richStemGroups['Pictures']] as $picturesStem) {
            $t->same(1, $picturesStem['directoryBaseNameVariantCount']);
            $t->same(2, $picturesStem['directoryCount']);
            $t->same(4, $picturesStem['entryCount']);
            $t->same(2, $picturesStem['fileEntryCount']);
            $t->same(2, $picturesStem['directoryEntryCount']);
            $t->same(38, $picturesStem['byteLength']);
            $t->same(['Pictures.assets' => 4], $picturesStem['directoryBaseNameCounts']);
            $t->same(['Objects/Pictures.assets/' => 2, 'Pictures.assets/' => 2], $picturesStem['packageDirectoryCounts']);
            $t->same(['image/png' => 1, 'text/plain' => 1], $picturesStem['manifestMediaTypeBaseCounts']);
            $t->same(
                ['Objects/Pictures.assets/', 'Objects/Pictures.assets/readme.txt', 'Pictures.assets/', 'Pictures.assets/HERO.PNG'],
                $picturesStem['entryNames']
            );

            $largest = $picturesStem['largestEntry'];
            $t->same('Objects/Pictures.assets/readme.txt', $largest['entryName']);
            $t->same('Objects/Pictures.assets/', $largest['packageDirectory']);
            $t->same('Pictures.assets', $largest['directoryBaseName']);
            $t->same('Pictures', $largest['directoryBaseNameStem']);
            $t->same('pictures', $largest['caseFoldDirectoryBaseNameStem']);
            $t->same('readme.txt', $largest['packageBasename']);
            $t->same(3, $largest['packagePathDepth']);
            $t->same('txt', $largest['packagePartExtension']);
            $t->same(31, $largest['byteLength']);
        }

        foreach ([$compactCaseFoldStemGroups['pictures'], $richCaseFoldStemGroups['pictures']] as $picturesStem) {
            $t->same(2, $picturesStem['directoryBaseNameStemVariantCount']);
            $t->same(3, $picturesStem['directoryBaseNameVariantCount']);
            $t->same(4, $picturesStem['directoryCount']);
            $t->same(8, $picturesStem['entryCount']);
            $t->same(4, $picturesStem['fileEntryCount']);
            $t->same(4, $picturesStem['directoryEntryCount']);
            $t->same(64, $picturesStem['byteLength']);
            $t->same(['Pictures' => 4, 'pictures' => 4], $picturesStem['directoryBaseNameStemCounts']);
            $t->same(['Pictures.assets' => 4, 'pictures' => 2, 'pictures.raw' => 2], $picturesStem['directoryBaseNameCounts']);
            $t->same([
                'Objects/Pictures.assets/' => 2,
                'Objects/pictures/' => 2,
                'Pictures.assets/' => 2,
                'pictures.raw/' => 2,
            ], $picturesStem['packageDirectoryCounts']);
            $t->same(['image/png' => 3, 'text/plain' => 1], $picturesStem['manifestMediaTypeBaseCounts']);
            $t->same(
                [
                    'Objects/Pictures.assets/',
                    'Objects/Pictures.assets/readme.txt',
                    'Objects/pictures/',
                    'Objects/pictures/icon.png',
                    'Pictures.assets/',
                    'Pictures.assets/HERO.PNG',
                    'pictures.raw/',
                    'pictures.raw/thumb.png',
                ],
                $picturesStem['entryNames']
            );

            $largest = $picturesStem['largestEntry'];
            $t->same('Objects/Pictures.assets/readme.txt', $largest['entryName']);
            $t->same('Objects/Pictures.assets/', $largest['packageDirectory']);
            $t->same('Pictures.assets', $largest['directoryBaseName']);
            $t->same('Pictures', $largest['directoryBaseNameStem']);
            $t->same('pictures', $largest['caseFoldDirectoryBaseNameStem']);
            $t->same(31, $largest['byteLength']);
        }

        $t->same($compactInventory['packageDirectoryBaseNameStemCounts'], $richProvenance['packageDirectoryBaseNameStemCounts']);
        $t->same($richProvenance['packageCaseFoldDirectoryBaseNameStemCounts'], $documentProvenance['packageCaseFoldDirectoryBaseNameStemCounts']);
        $t->same('Pictures', $compactInventory['parts']['Objects/Pictures.assets/readme.txt']['pathShape']['directoryBaseNameStem']);
        $t->same('Pictures', $compactInventory['parts']['Objects/Pictures.assets/readme.txt']['packageDirectoryBaseNameStem']);
        $t->same('pictures', $compactInventory['parts']['Objects/Pictures.assets/readme.txt']['packageCaseFoldDirectoryBaseNameStem']);
        $t->same('Pictures', $compactIdentityParts['Objects/Pictures.assets/readme.txt']['packageDirectoryBaseNameStem']);
        $t->same('pictures', $compactIdentityParts['Objects/Pictures.assets/readme.txt']['packageCaseFoldDirectoryBaseNameStem']);
        $t->same('pictures', $richProvenance['parts']['Objects/pictures/icon.png']['packagePathShape']['directoryBaseNameStem']);
        $t->same('pictures', $richProvenance['parts']['Objects/pictures/icon.png']['packageDirectoryBaseNameStem']);
        $t->same('pictures', $richIdentityParts['Objects/pictures/icon.png']['packageDirectoryBaseNameStem']);
        $t->same('pictures', $richIdentityParts['Objects/pictures/icon.png']['packageCaseFoldDirectoryBaseNameStem']);
    },
];
