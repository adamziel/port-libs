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
      <text:p>Manifest declared size media-family buckets.</text:p>
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

$imageBytes = 'PNGFAMILY';
$scriptXml = <<<'XML'
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review">Sub Main
End Sub</script:module>
XML;

$contentDeclaredSize = strlen($contentXml) + 7;
$imageSize = strlen($imageBytes);
$missingImageSize = 17;
$scriptSize = strlen($scriptXml);
$imageDeclaredSize = $imageSize + $missingImageSize;
$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:size="{$contentDeclaredSize}"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="{$imageSize}"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png" manifest:size="{$missingImageSize}"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml" manifest:size="{$scriptSize}"/>
</manifest:manifest>
XML;

$buildPackage = static function (string $manifest = null) use ($manifestXml, $contentXml, $stylesXml, $imageBytes, $scriptXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifest ?? $manifestXml, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
        ['name' => 'Pictures/hero.png', 'data' => $imageBytes, 'compressionMethod' => 0],
        ['name' => 'Basic/Standard/Review.xml', 'data' => $scriptXml, 'compressionMethod' => 0],
    ], 'odt manifest declared size media families');
};

$indexByFamily = static function (array $summaries): array {
    $indexed = [];
    foreach ($summaries as $summary) {
        $indexed[(string) $summary['manifestMediaFamily']] = $summary;
    }

    return $indexed;
};

return [
    'summarizes ODT manifest declared sizes by media family across compact and rich identity' => static function (TestRunner $t) use (
        $buildPackage,
        $contentDeclaredSize,
        $imageDeclaredSize,
        $imageSize,
        $missingImageSize,
        $scriptSize,
        $manifestXml,
        $indexByFamily,
    ): void {
        $package = $buildPackage();
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactReview = $compactSummary['manifestReview'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        foreach ([
            'manifestDeclaredSizeMediaFamilyCount',
            'manifestDeclaredSizeMediaFamilyCounts',
            'manifestDeclaredSizeMediaFamilyByteLengths',
            'manifestDeclaredSizeMediaFamilyMismatchCounts',
            'manifestDeclaredSizeMediaFamilyExistingCounts',
            'manifestDeclaredSizeMediaFamilyMissingCounts',
            'manifestDeclaredSizeMediaFamilySummaries',
        ] as $key) {
            $t->same($compactReview[$key], $compactIdentity[$key], "compact identity carries {$key}");
            $t->same($richProvenance[$key], $richIdentity[$key], "rich identity carries {$key}");
            $t->same($richIdentity[$key], $documentIdentity[$key], "document identity carries {$key}");
            $t->same($compactReview[$key], $richProvenance[$key], "compact and rich agree on {$key}");
        }

        $t->same(3, $compactReview['manifestDeclaredSizeMediaFamilyCount']);
        $t->same([
            'image' => 2,
            'script' => 1,
            'xml' => 1,
        ], $compactReview['manifestDeclaredSizeMediaFamilyCounts']);
        $t->same([
            'image' => $imageDeclaredSize,
            'script' => $scriptSize,
            'xml' => $contentDeclaredSize,
        ], $compactReview['manifestDeclaredSizeMediaFamilyByteLengths']);
        $t->same(['xml' => 1], $compactReview['manifestDeclaredSizeMediaFamilyMismatchCounts']);
        $t->same([
            'image' => 1,
            'script' => 1,
            'xml' => 1,
        ], $compactReview['manifestDeclaredSizeMediaFamilyExistingCounts']);
        $t->same(['image' => 1], $compactReview['manifestDeclaredSizeMediaFamilyMissingCounts']);

        $summaries = $indexByFamily($compactReview['manifestDeclaredSizeMediaFamilySummaries']);
        $t->same(['Pictures/hero.png', 'Pictures/missing.png'], $summaries['image']['parts']);
        $t->same(['Basic/Standard/Review.xml'], $summaries['script']['parts']);
        $t->same(['content.xml'], $summaries['xml']['parts']);
        $t->same(1, $summaries['image']['missingCount']);
        $t->same($imageSize + $missingImageSize, $summaries['image']['declaredSize']);
        $t->same(1, $summaries['xml']['declaredSizeMismatchCount']);

        $changedManifest = str_replace(
            'manifest:full-path="Pictures/missing.png" manifest:media-type="image/png" manifest:size="17"',
            'manifest:full-path="Pictures/missing.png" manifest:media-type="image/png" manifest:size="23"',
            $manifestXml
        );
        $changedIdentity = OpenDocumentPackage::fromPackage($buildPackage($changedManifest))->summarize()['packageIdentity'];
        $t->same(false, $compactIdentity['identitySha256'] === $changedIdentity['identitySha256']);
        $t->same(23 + $imageSize, $changedIdentity['manifestDeclaredSizeMediaFamilyByteLengths']['image']);
    },
];
