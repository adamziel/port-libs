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
        $compactPreferredViewModes = $compactSummary['manifestReview']['preferredViewModes'];
        $richResult = (new OdfReader())->readPackage($package);
        $richMediaTypes = $richResult['importReport']['manifest']['mediaTypeSummary'];
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];
        $richPreferredViewModes = $richProvenance['preferredViewModes'];
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
            'mediaTypeParameterValueCount',
            'mediaTypeParameterValuesByName',
            'mediaTypeParameterValueCounts',
            'mediaTypeParameterValueSummaries',
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
                'mediaTypeParameterValueCount',
                'mediaTypeParameterValuesByName',
                'mediaTypeParameterValueCounts',
                'mediaTypeParameterValueSummaries',
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
        $t->same($richMediaTypes, $richIdentity['manifestMediaTypeSummary']);
        $t->same($richIdentity['manifestMediaTypeSummary'], $documentIdentity['manifestMediaTypeSummary']);
        $t->same(4, $compactIdentity['manifestMediaTypeCount']);
        $t->same(4, $richIdentity['manifestMediaTypeCount']);
        $t->same(2, $compactIdentity['manifestMediaTypeParameterizedItemCount']);
        $t->same(2, $richIdentity['manifestMediaTypeParameterizedItemCount']);
        $t->same(['charset', 'profile', 'role'], $compactIdentity['manifestMediaTypeParameterNames']);
        $t->same(['charset', 'profile', 'role'], $richIdentity['manifestMediaTypeParameterNames']);
        $t->same(3, $compactIdentity['manifestMediaTypeParameterValueCount']);
        $t->same(3, $richIdentity['manifestMediaTypeParameterValueCount']);
        $t->same([
            'charset' => ['UTF-8'],
            'profile' => ['review cover'],
            'role' => ['preview'],
        ], $compactIdentity['manifestMediaTypeParameterValuesByName']);
        $t->same($compactIdentity['manifestMediaTypeParameterValuesByName'], $richIdentity['manifestMediaTypeParameterValuesByName']);
        $t->same([
            'charset' => ['UTF-8' => 1],
            'profile' => ['review cover' => 1],
            'role' => ['preview' => 1],
        ], $compactIdentity['manifestMediaTypeParameterValueCounts']);
        $t->same($compactIdentity['manifestMediaTypeParameterValueCounts'], $richIdentity['manifestMediaTypeParameterValueCounts']);
        $t->same($compactMediaTypes['mediaTypeParameterValueSummaries'], $compactIdentity['manifestMediaTypeParameterValueSummaries']);
        $t->same($richMediaTypes['mediaTypeParameterValueSummaries'], $richIdentity['manifestMediaTypeParameterValueSummaries']);
        $t->same(1, $compactIdentity['manifestEmptyMediaTypeCount']);
        $t->same(1, $richIdentity['manifestEmptyMediaTypeCount']);
        $t->same(1, $compactIdentity['manifestEmptyMediaTypeDirectoryCount']);
        $t->same(1, $richIdentity['manifestEmptyMediaTypeDirectoryCount']);
        $t->same(0, $compactIdentity['manifestEmptyMediaTypeNonDirectoryCount']);
        $t->same(0, $richIdentity['manifestEmptyMediaTypeNonDirectoryCount']);
        $t->same($compactPreferredViewModes, $compactIdentity['preferredViewModes']);
        $t->same($richPreferredViewModes, $richIdentity['preferredViewModes']);
        $t->same($richIdentity['preferredViewModes'], $documentIdentity['preferredViewModes']);
        foreach ([
            'count',
            'itemCount',
            'rootMode',
            'definedModeCount',
            'namespacedTokenCount',
            'invalidTokenCount',
            'nonRootEntryCount',
            'issueCount',
            'issueCodes',
            'issueCodeCounts',
            'modeCounts',
            'modeFamilyCounts',
            'fullPathsByModeFamily',
            'byteExposurePolicyCounts',
            'fullPathsByByteExposurePolicy',
        ] as $key) {
            $t->same($compactPreferredViewModes[$key], $richPreferredViewModes[$key], "shared preferred view mode field {$key}");
        }
        $t->same(['Configurations2/'], $compactMediaTypes['emptyMediaTypeDirectoryParts']);
        $t->same(['charset', 'profile'], $compactByType['image/jpeg']['mediaTypeParameterNames']);
        $t->same(2, $compactByType['image/jpeg']['mediaTypeParameterValueCount']);
        $t->same([
            'charset' => ['UTF-8'],
            'profile' => ['review cover'],
        ], $compactByType['image/jpeg']['mediaTypeParameterValuesByName']);
        $t->same(['Pictures/hero.jpg'], $compactByType['image/jpeg']['parts']);
        $t->same(['role'], $compactByType['image/png']['mediaTypeParameterNames']);
        $t->same(1, $compactByType['image/png']['mediaTypeParameterValueCount']);
        $t->same(['role' => ['preview']], $compactByType['image/png']['mediaTypeParameterValuesByName']);
        $t->same(['Thumbnails/thumb.png'], $compactByType['image/png']['parts']);

        $changedManifest = str_replace('profile=&quot;review cover&quot;', 'profile=&quot;final cover&quot;', $manifestXml);
        $changedIdentity = OpenDocumentPackage::fromPackage($buildPackage($changedManifest))->summarize()['packageIdentity'];
        $t->same(false, $compactIdentity['identitySha256'] === $changedIdentity['identitySha256']);
        $t->same(['charset', 'profile', 'role'], $changedIdentity['manifestMediaTypeParameterNames']);
        $t->same(['final cover'], $changedIdentity['manifestMediaTypeParameterValuesByName']['profile']);
    },

    'buckets ODT preferred view modes by mode family and byte exposure policy' => static function (TestRunner $t) use ($buildPackage): void {
        $viewModeManifest = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:preferred-view-mode="read-only" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:preferred-view-mode="edit" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.jpg" manifest:preferred-view-mode="lo:review" manifest:media-type="image/jpeg"/>
  <manifest:file-entry manifest:full-path="Thumbnails/thumb.png" manifest:preferred-view-mode="bad token" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Configurations2/" manifest:preferred-view-mode="presentation-slide-show"/>
</manifest:manifest>
XML;

        $package = $buildPackage($viewModeManifest);
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactPreferredViewModes = $compactSummary['manifestReview']['preferredViewModes'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richPreferredViewModes = $richProvenance['preferredViewModes'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        $bucketByItemField = static function (array $items, string $field): array {
            $buckets = [];
            foreach ($items as $item) {
                $key = is_string($item[$field] ?? null) && $item[$field] !== '' ? $item[$field] : '(missing)';
                $path = (string) ($item['fullPath'] ?? '');
                $buckets[$key][] = $path;
            }
            ksort($buckets, SORT_STRING);
            foreach ($buckets as &$paths) {
                sort($paths, SORT_STRING);
            }
            unset($paths);

            return $buckets;
        };
        $countByItemField = static function (array $items, string $field): array {
            $counts = [];
            foreach ($items as $item) {
                $key = is_string($item[$field] ?? null) && $item[$field] !== '' ? $item[$field] : '(missing)';
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
            ksort($counts, SORT_STRING);

            return $counts;
        };

        $t->same(5, $compactPreferredViewModes['count']);
        $t->same('read-only', $compactPreferredViewModes['rootMode']);
        $t->same(3, $compactPreferredViewModes['definedModeCount']);
        $t->same(1, $compactPreferredViewModes['namespacedTokenCount']);
        $t->same(1, $compactPreferredViewModes['invalidTokenCount']);
        $t->same(4, $compactPreferredViewModes['nonRootEntryCount']);
        $t->same(4, $compactPreferredViewModes['issueCount']);
        $t->same([
            'defined' => 3,
            'invalid-token' => 1,
            'namespaced-token' => 1,
        ], $compactPreferredViewModes['modeFamilyCounts']);
        $t->same([
            'defined' => ['/', 'Configurations2/', 'content.xml'],
            'invalid-token' => ['Thumbnails/thumb.png'],
            'namespaced-token' => ['Pictures/hero.jpg'],
        ], $compactPreferredViewModes['fullPathsByModeFamily']);
        $t->same(
            $countByItemField($compactPreferredViewModes['items'], 'byteExposurePolicy'),
            $compactPreferredViewModes['byteExposurePolicyCounts']
        );
        $t->same(
            $bucketByItemField($compactPreferredViewModes['items'], 'byteExposurePolicy'),
            $compactPreferredViewModes['fullPathsByByteExposurePolicy']
        );
        $t->same($compactPreferredViewModes, $compactIdentity['preferredViewModes']);
        foreach ([
            'count',
            'itemCount',
            'rootMode',
            'definedModeCount',
            'namespacedTokenCount',
            'invalidTokenCount',
            'nonRootEntryCount',
            'issueCount',
            'issueCodes',
            'issueCodeCounts',
            'modeCounts',
            'modeFamilyCounts',
            'fullPathsByModeFamily',
            'byteExposurePolicyCounts',
            'fullPathsByByteExposurePolicy',
        ] as $key) {
            $t->same($compactPreferredViewModes[$key], $richPreferredViewModes[$key], "shared preferred view mode field {$key}");
        }
        $t->same($richPreferredViewModes, $richIdentity['preferredViewModes']);
        $t->same($richIdentity['preferredViewModes'], $documentIdentity['preferredViewModes']);
    },
];
