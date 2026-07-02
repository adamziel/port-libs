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
  <manifest:file-entry manifest:full-path="Pictures/icon.svg" manifest:media-type="image/svg+xml"/>
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
      <text:p>Package directory inventory.</text:p>
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
    <dc:title>Package Directory Inventory</dc:title>
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
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/icon.svg', 'data' => '<svg/>', 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => '<script/>', 'compressionMethod' => 0],
    ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<accel/>', 'compressionMethod' => 0],
    ['name' => 'Objects/Sub/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Objects/Sub/data.bin', 'data' => 'DATA', 'compressionMethod' => 0],
], 'odt package directory inventory');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT package directories across compact and rich package provenance' => static function (TestRunner $t) use ($buildPackage, $indexBy): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $compactIdentityParts = $indexBy($compactIdentity['packageEntries'], 'path');

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];
        $documentIdentity = $documentProvenance['packageIdentity'];
        $richIdentityParts = $indexBy($richIdentity['packageEntries'], 'part');

        $expectedDirectoryCounts = [
            '(root)' => 4,
            'Basic/Standard/' => 1,
            'Configurations2/accelerator/' => 1,
            'META-INF/' => 1,
            'Objects/Sub/' => 2,
            'Pictures/' => 3,
        ];
        $expectedRootNames = ['content.xml', 'meta.xml', 'mimetype', 'styles.xml'];
        $expectedPicturesNames = ['Pictures/', 'Pictures/hero.png', 'Pictures/icon.svg'];
        $expectedObjectNames = ['Objects/Sub/', 'Objects/Sub/data.bin'];

        foreach ([
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
            'document provenance' => $documentProvenance,
            'document identity' => $documentIdentity,
        ] as $label => $handoff) {
            $t->same($expectedDirectoryCounts, $handoff['packageDirectoryCounts'], "{$label} directory counts");
            $t->same(6, $handoff['packageDirectorySummaryCount'], "{$label} directory summary count");
            $t->same($expectedRootNames, $handoff['entryNamesByPackageDirectory']['(root)'], "{$label} root names");
            $t->same($expectedPicturesNames, $handoff['entryNamesByPackageDirectory']['Pictures/'], "{$label} pictures names");
            $t->same($expectedObjectNames, $handoff['entryNamesByPackageDirectory']['Objects/Sub/'], "{$label} object subdirectory names");
        }

        $compactDirectories = $indexBy($compactInventory['packageDirectorySummaries'], 'packageDirectoryKey');
        $richDirectories = $indexBy($richProvenance['packageDirectorySummaries'], 'packageDirectoryKey');
        $identityDirectories = $indexBy($richIdentity['packageDirectorySummaries'], 'packageDirectoryKey');

        foreach ([$compactDirectories['Pictures/'], $richDirectories['Pictures/'], $identityDirectories['Pictures/']] as $pictures) {
            $t->same('Pictures/', $pictures['packageDirectory']);
            $t->same('Pictures', $pictures['directoryBaseName']);
            $t->same('Pictures', $pictures['directoryBaseNameStem']);
            $t->same('pictures', $pictures['caseFoldDirectoryBaseNameStem']);
            $t->same(3, $pictures['entryCount']);
            $t->same(2, $pictures['fileEntryCount']);
            $t->same(1, $pictures['directoryEntryCount']);
            $t->same(2, $pictures['declaredPartCount']);
            $t->same(0, $pictures['undeclaredPartCount']);
            $t->same(2, $pictures['exposablePartCount']);
            $t->same(1, $pictures['blockedPartCount']);
            $t->same(13, $pictures['byteLength']);
            $t->same([1 => 1, 2 => 2], $pictures['packagePathDepthCounts']);
            $t->same(['(none)' => 1, 'png' => 1, 'svg' => 1], $pictures['packagePartExtensionCounts']);
            $t->same(['manifest-declared' => 2, 'media-resource' => 2, 'zip-directory' => 1], $pictures['roleCounts']);
            $t->same(['package-bytes-exposable' => 2], $pictures['byteExposurePolicyCounts']);
            $t->same($expectedPicturesNames, $pictures['entryNames']);
            $t->same('Pictures/hero.png', $pictures['largestEntry']['entryName']);
            $t->same('hero.png', $pictures['largestEntry']['packageBasename']);
            $t->same(7, $pictures['largestEntry']['byteLength']);
            $t->same(true, $pictures['largestEntry']['canExposeBytes']);
        }

        foreach ([$compactDirectories['Objects/Sub/'], $richDirectories['Objects/Sub/']] as $objects) {
            $t->same(2, $objects['entryCount']);
            $t->same(1, $objects['fileEntryCount']);
            $t->same(1, $objects['directoryEntryCount']);
            $t->same(1, $objects['undeclaredPartCount']);
            $t->same(2, $objects['blockedPartCount']);
            $t->same(4, $objects['byteLength']);
            $t->same(['(none)' => 1, 'bin' => 1], $objects['packagePartExtensionCounts']);
            $t->same(['undeclared-package-entry' => 1, 'zip-directory' => 1], $objects['roleCounts']);
            $t->same(['undeclared-package-entry-no-bytes' => 1], $objects['byteExposurePolicyCounts']);
            $t->same('Objects/Sub/data.bin', $objects['largestEntry']['entryName']);
            $t->same(false, $objects['largestEntry']['canExposeBytes']);
        }

        $root = $compactDirectories['(root)'];
        $t->same(null, $root['packageDirectory']);
        $t->same(4, $root['entryCount']);
        $t->same(4, $root['fileEntryCount']);
        $t->same(0, $root['directoryEntryCount']);
        $t->same(3, $root['declaredPartCount']);
        $t->same(['(none)' => 1, 'xml' => 3], $root['packagePartExtensionCounts']);
        $t->same(['package-bytes-exposable' => 3], $root['byteExposurePolicyCounts']);

        $standard = $richDirectories['Basic/Standard/'];
        $t->same(['manifest-declared' => 1, 'script-package' => 1], $standard['roleCounts']);
        $t->same(['script-package-bytes-blocked' => 1], $standard['byteExposurePolicyCounts']);
        $t->same(['Basic/Standard/Review.xml'], $standard['entryNames']);

        $t->same($compactInventory['packageDirectorySummaries'], $compactIdentity['packageDirectorySummaries']);
        $t->same($richProvenance['packageDirectorySummaries'], $documentProvenance['packageDirectorySummaries']);
        $t->same($richIdentity['entryNamesByPackageDirectory'], $documentIdentity['entryNamesByPackageDirectory']);
        $t->same('Pictures/', $compactInventory['parts']['Pictures/hero.png']['packageDirectory']);
        $t->same('hero.png', $compactInventory['parts']['Pictures/hero.png']['packageBasename']);
        $t->same('Pictures/', $compactIdentityParts['Pictures/hero.png']['packageDirectory']);
        $t->same('Pictures/', $richIdentityParts['Pictures/icon.svg']['packageDirectory']);
    },
];
