<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.4">
  <office:body>
    <office:text>
      <text:p>Version mismatch paragraph.</text:p>
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

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.4">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.4" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:version="1.4" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:version="1.3" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:version="1.2" manifest:media-type="image/png" manifest:size="7"/>
</manifest:manifest>
XML;

$buildPackage = static fn (?string $manifest = null): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifest ?? $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
], 'odf manifest version mismatch summary');

$indexByMediaType = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item['mediaType']] = $item;
    }

    return $indexed;
};

return [
    'records mapped odf manifest version mismatch summary case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedOdfManifestVersionMismatchSummaryCases'] ?? null);
        $t->same(34, $manifest['odfManifestVersionMismatchSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedOdfManifestVersionMismatchSummaryCases'] ?? null);
        $t->same(34, $manifest['benchmarkDenominator']['breakdown']['odfManifestVersionMismatchSummaryAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedOdfManifestVersionMismatchSummaryCases'] ?? null);
        $t->same(34, $manifest['benchmarkDenominator']['inventory']['odfManifestVersionMismatchSummaryAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedOdfManifestVersionMismatchSummaryCases'] ?? null);
        $t->same(34, $manifest['inventory']['odfManifestVersionMismatchSummaryAssertions'] ?? null);
    },

    'summarizes ODT manifest file-entry version mismatches across compact and rich handoffs' => static function (TestRunner $t) use (
        $buildPackage,
        $indexByMediaType,
        $manifestXml
    ): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactMediaTypes = $compactSummary['manifestMediaTypeSummary'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richMediaTypes = $richResult['importReport']['manifest']['mediaTypeSummary'];
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        foreach ([
            'manifestRootVersion',
            'manifestVersionMismatchCount',
            'manifestVersionMismatchParts',
            'manifestVersionMismatches',
        ] as $key) {
            $t->same($richMediaTypes[$key], $compactMediaTypes[$key], "shared version field {$key}");
        }

        $t->same('1.4', $compactMediaTypes['manifestRootVersion']);
        $t->same(2, $compactMediaTypes['manifestVersionMismatchCount']);
        $t->same(['styles.xml', 'Pictures/hero.png'], $compactMediaTypes['manifestVersionMismatchParts']);
        $t->same([
            [
                'fullPath' => 'styles.xml',
                'part' => 'styles.xml',
                'mediaType' => 'text/xml',
                'version' => '1.3',
                'manifestRootVersion' => '1.4',
                'exists' => true,
                'isDirectory' => false,
            ],
            [
                'fullPath' => 'Pictures/hero.png',
                'part' => 'Pictures/hero.png',
                'mediaType' => 'image/png',
                'version' => '1.2',
                'manifestRootVersion' => '1.4',
                'exists' => true,
                'isDirectory' => false,
            ],
        ], $compactMediaTypes['manifestVersionMismatches']);
        $t->same(4, $compactMediaTypes['versionedItemCount']);
        $t->same(['1.4', '1.3', '1.2'], $compactMediaTypes['manifestVersions']);

        $t->same($compactMediaTypes, $compactIdentity['manifestMediaTypeSummary']);
        $t->same($richMediaTypes, $richIdentity['manifestMediaTypeSummary']);
        $t->same($richIdentity['manifestMediaTypeSummary'], $documentIdentity['manifestMediaTypeSummary']);
        $t->same(2, $compactIdentity['manifestMediaTypeSummary']['manifestVersionMismatchCount']);
        $t->same(2, $richIdentity['manifestMediaTypeSummary']['manifestVersionMismatchCount']);
        $t->same(2, $documentIdentity['manifestMediaTypeSummary']['manifestVersionMismatchCount']);
        $t->same('1.4', $compactIdentity['manifestMediaTypeSummary']['manifestRootVersion']);
        $t->same('1.4', $richIdentity['manifestMediaTypeSummary']['manifestRootVersion']);

        $compactByType = $indexByMediaType($compactMediaTypes['items']);
        $t->same(2, $compactByType['text/xml']['versionedItemCount']);
        $t->same(['1.4', '1.3'], $compactByType['text/xml']['manifestVersions']);
        $t->same(1, $compactByType['image/png']['versionedItemCount']);
        $t->same(['1.2'], $compactByType['image/png']['manifestVersions']);

        $changedManifest = str_replace(
            'manifest:full-path="styles.xml" manifest:version="1.3"',
            'manifest:full-path="styles.xml" manifest:version="1.4"',
            $manifestXml
        );
        $changedIdentity = OpenDocumentPackage::fromPackage($buildPackage($changedManifest))->summarize()['packageIdentity'];
        $t->same(false, $compactIdentity['identitySha256'] === $changedIdentity['identitySha256']);
        $t->same(1, $changedIdentity['manifestMediaTypeSummary']['manifestVersionMismatchCount']);
        $t->same(['Pictures/hero.png'], $changedIdentity['manifestMediaTypeSummary']['manifestVersionMismatchParts']);

        $encodedSummary = json_encode($compactMediaTypes, JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedSummary, 'Version mismatch paragraph.'));
    },
];
