<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$picturePreview = 'PICTURE-PREVIEW';
$thumbnailPreview = 'THUMBNAIL-PREVIEW';
$configurationPreview = 'CONFIG-PREVIEW';
$privatePreview = 'PRIVATE-PREVIEW';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/preview.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Thumbnails/preview.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Configurations2/images/preview.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Package leaf name provenance.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Package Leaf Name Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/preview.png', 'data' => $picturePreview, 'compressionMethod' => 0],
    ['name' => 'Thumbnails/preview.png', 'data' => $thumbnailPreview, 'compressionMethod' => 0],
    ['name' => 'Configurations2/images/preview.png', 'data' => $configurationPreview, 'compressionMethod' => 0],
    ['name' => 'Notes/preview.png', 'data' => $privatePreview, 'compressionMethod' => 0],
], 'odt package leaf names');

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

return [
    'summarizes ODT package leaf names in rich package provenance identity' => static function (TestRunner $t) use (
        $buildPackage,
        $picturePreview,
        $thumbnailPreview,
        $configurationPreview,
        $privatePreview,
        $indexBy
    ): void {
        $result = (new OdfReader())->readPackage($buildPackage());
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $identity = $provenance['packageIdentity'];
        $leafByName = $indexBy($provenance['leafNameSummaries'], 'leafName');
        $identityLeafByName = $indexBy($identity['leafNameSummaries'], 'leafName');
        $packageByPart = $indexBy($identity['packageEntries'], 'part');
        $previewBytes = strlen($picturePreview)
            + strlen($thumbnailPreview)
            + strlen($configurationPreview)
            + strlen($privatePreview);

        $t->same(6, $provenance['leafNameCount']);
        $t->same(1, $provenance['sharedLeafNameCount']);
        $t->same(4, $provenance['sharedLeafNameEntryCount']);
        $t->same($provenance['leafNameSummaries'], $identity['leafNameSummaries']);
        $t->same($provenance['sharedLeafNameSummaries'], $identity['sharedLeafNameSummaries']);
        $t->same($leafByName['preview.png'], $identityLeafByName['preview.png']);

        $preview = $leafByName['preview.png'];
        $t->same(4, $preview['entryCount']);
        $t->same(4, $preview['fileEntryCount']);
        $t->same(0, $preview['directoryEntryCount']);
        $t->same($previewBytes, $preview['storedByteLength']);
        $t->same($previewBytes, $preview['compressedByteLength']);
        $t->same(['preview'], $preview['entryBaseNames']);
        $t->same(['png'], $preview['entryExtensionKeys']);
        $t->same([
            'Configurations2/images/preview.png',
            'Notes/preview.png',
            'Pictures/preview.png',
            'Thumbnails/preview.png',
        ], $preview['paths']);
        $t->same([
            'Configurations2/',
            'Notes/',
            'Pictures/',
            'Thumbnails/',
        ], $preview['directoryRoots']);
        $t->same([
            'Configurations2/images/',
            'Notes/',
            'Pictures/',
            'Thumbnails/',
        ], $preview['parentDirectories']);
        $t->same([
            'configuration-package',
            'manifest-declared',
            'media-resource',
            'package-thumbnail',
            'undeclared-package-entry',
        ], $preview['roles']);

        $t->same('preview.png', $packageByPart['Configurations2/images/preview.png']['leafName']);
        $t->same('Configurations2/', $packageByPart['Configurations2/images/preview.png']['directoryRoot']);
        $t->same('Configurations2/images/', $packageByPart['Configurations2/images/preview.png']['parentDirectory']);
        $t->same('preview', $packageByPart['Configurations2/images/preview.png']['entryBaseName']);
        $t->same('png', $packageByPart['Configurations2/images/preview.png']['entryExtension']);
        $t->same('png', $packageByPart['Configurations2/images/preview.png']['entryExtensionKey']);
        $t->same(3, $packageByPart['Configurations2/images/preview.png']['pathDepth']);

        $compact = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactPreview = $indexBy($compact['packageInventory']['leafNameSummaries'], 'leafName')['preview.png'];
        $t->same($preview['paths'], $compactPreview['paths']);
        $t->same($preview['entryCount'], $compactPreview['entryCount']);
        $t->same($preview['storedByteLength'], $compactPreview['storedByteLength']);
        $t->same(1, $compact['packageInventory']['sharedLeafNameCount']);
        $t->same(4, $compact['packageInventory']['sharedLeafNameEntryCount']);
    },
];
