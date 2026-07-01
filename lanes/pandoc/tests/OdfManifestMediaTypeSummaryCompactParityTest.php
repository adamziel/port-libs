<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Manifest media type summary parity.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Manifest Media Type Summary Parity</dc:title>
  </office:meta>
</office:document-meta>
XML;

$heroBytes = 'JPEGDATA';
$thumbnailBytes = 'THUMB';
$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:preferred-view-mode="read-only" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.jpg" manifest:media-type="image/jpeg; charset=UTF-8; profile=&quot;review cover&quot;" manifest:size="8"/>
  <manifest:file-entry manifest:full-path="Thumbnails/thumb.png" manifest:media-type="image/png; role=&quot;preview&quot;" manifest:size="5"/>
  <manifest:file-entry manifest:full-path="Configurations2/"/>
</manifest:manifest>
XML;

$buildPackage = static fn (?string $manifest = null): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifest ?? $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.jpg', 'data' => $heroBytes, 'compressionMethod' => 0],
    ['name' => 'Thumbnails/thumb.png', 'data' => $thumbnailBytes, 'compressionMethod' => 0],
    ['name' => 'Configurations2/', 'data' => '', 'compressionMethod' => 0],
], 'odf manifest media type summary parity');

$indexByMediaType = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item['mediaType']] = $item;
    }

    return $indexed;
};

return [
    'carries compact ODT manifest media type summary into package identity' => static function (TestRunner $t) use ($buildPackage, $manifestXml, $indexByMediaType): void {
        $package = $buildPackage();
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactMediaTypes = $compactSummary['manifestMediaTypeSummary'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richMediaTypes = $richResult['importReport']['manifest']['mediaTypeSummary'];
        $compactByType = $indexByMediaType($compactMediaTypes['items']);
        $richByType = $indexByMediaType($richMediaTypes['items']);

        foreach ([
            'manifestItemCount',
            'typedItemCount',
            'mediaTypeCount',
            'emptyMediaTypeCount',
            'emptyMediaTypeParts',
            'emptyMediaTypeDirectoryCount',
            'emptyMediaTypeDirectoryParts',
            'emptyMediaTypeNonDirectoryCount',
            'emptyMediaTypeNonDirectoryItems',
            'directoryCount',
            'missingCount',
            'encryptedCount',
            'versionedItemCount',
            'manifestVersions',
            'versionedItems',
            'preferredViewModeCount',
            'preferredViewModes',
            'preferredViewModeItems',
            'declaredSizeMismatchCount',
            'invalidDeclaredSizeCount',
            'invalidDeclaredSizeItems',
            'parameterizedItemCount',
            'mediaTypeParameterNames',
            'storedByteLength',
            'compressedByteLength',
            'exposableByteLength',
            'declaredSize',
        ] as $key) {
            $t->same($richMediaTypes[$key], $compactMediaTypes[$key], "shared summary field {$key}");
        }
        foreach (['application/vnd.oasis.opendocument.text', 'text/xml', 'image/jpeg', 'image/png'] as $mediaType) {
            foreach ([
                'count',
                'parts',
                'rawMediaTypes',
                'rawMediaTypeCount',
                'parameterizedItemCount',
                'mediaTypeParameterNames',
                'existsCount',
                'missingCount',
                'directoryCount',
                'encryptedCount',
                'versionedItemCount',
                'manifestVersions',
                'preferredViewModeCount',
                'preferredViewModes',
                'declaredSizeMismatchCount',
                'invalidDeclaredSizeCount',
                'storedByteLength',
                'compressedByteLength',
                'exposableByteLength',
                'declaredSize',
            ] as $key) {
                $t->same($richByType[$mediaType][$key], $compactByType[$mediaType][$key], "{$mediaType} shared group field {$key}");
            }
        }
        $t->same(['odf-manifest-directory-entry' => 1], $compactMediaTypes['diagnosticCodeCounts']);
        $t->same($compactMediaTypes, $compactIdentity['manifestMediaTypeSummary']);
        $t->same(4, $compactIdentity['manifestMediaTypeCount']);
        $t->same(2, $compactIdentity['manifestMediaTypeParameterizedItemCount']);
        $t->same(['charset', 'profile', 'role'], $compactIdentity['manifestMediaTypeParameterNames']);
        $t->same(1, $compactIdentity['manifestEmptyMediaTypeCount']);
        $t->same(1, $compactIdentity['manifestEmptyMediaTypeDirectoryCount']);
        $t->same(0, $compactIdentity['manifestEmptyMediaTypeNonDirectoryCount']);
        $t->same(['Configurations2/'], $compactMediaTypes['emptyMediaTypeDirectoryParts']);
        $t->same(['charset', 'profile'], $compactByType['image/jpeg']['mediaTypeParameterNames']);
        $t->same(['Pictures/hero.jpg'], $compactByType['image/jpeg']['parts']);
        $t->same(['role'], $compactByType['image/png']['mediaTypeParameterNames']);
        $t->same(['Thumbnails/thumb.png'], $compactByType['image/png']['parts']);

        $changedManifest = str_replace('profile=&quot;review cover&quot;', 'profile=&quot;final cover&quot;', $manifestXml);
        $changedIdentity = OpenDocumentPackage::fromPackage($buildPackage($changedManifest))->summarize()['packageIdentity'];
        $t->same(false, $compactIdentity['identitySha256'] === $changedIdentity['identitySha256']);
        $t->same(['charset', 'profile', 'role'], $changedIdentity['manifestMediaTypeParameterNames']);
    },
];
