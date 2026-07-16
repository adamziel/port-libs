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
      <text:p>Manifest declared size role buckets.</text:p>
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
    <dc:title>Manifest Declared Size Roles</dc:title>
  </office:meta>
</office:document-meta>
XML;

$imageBytes = 'PNGROLE1';
$scriptXml = <<<'XML'
<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review">Sub Main
End Sub</script:module>
XML;

$contentDeclaredSize = strlen($contentXml) + 5;
$imageSize = strlen($imageBytes);
$missingImageSize = 13;
$scriptSize = strlen($scriptXml);
$manifestDeclaredSize = $contentDeclaredSize + $imageSize + $missingImageSize + $scriptSize;
$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:size="{$contentDeclaredSize}"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="{$imageSize}"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png" manifest:size="{$missingImageSize}"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml" manifest:size="{$scriptSize}"/>
</manifest:manifest>
XML;

$buildPackage = static function (string $manifest = null) use ($manifestXml, $contentXml, $stylesXml, $metaXml, $imageBytes, $scriptXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifest ?? $manifestXml, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
        ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
        ['name' => 'Pictures/hero.png', 'data' => $imageBytes, 'compressionMethod' => 0],
        ['name' => 'Basic/Standard/Review.xml', 'data' => $scriptXml, 'compressionMethod' => 0],
    ], 'odt manifest declared size role buckets');
};

$indexByRole = static function (array $summaries): array {
    $indexed = [];
    foreach ($summaries as $summary) {
        $indexed[(string) $summary['role']] = $summary;
    }

    return $indexed;
};

return [
    'summarizes ODT manifest declared sizes by package role across compact and rich identity' => static function (TestRunner $t) use (
        $buildPackage,
        $contentDeclaredSize,
        $imageSize,
        $missingImageSize,
        $scriptSize,
        $manifestDeclaredSize,
        $manifestXml,
        $indexByRole,
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
            'manifestDeclaredSizeRoleCount',
            'manifestDeclaredSizeRoleCounts',
            'manifestDeclaredSizeRoleByteLengths',
            'manifestDeclaredSizeRoleMismatchCounts',
            'manifestDeclaredSizeRoleExistingCounts',
            'manifestDeclaredSizeRoleMissingCounts',
            'manifestDeclaredSizeRoleSummaries',
        ] as $key) {
            $t->same($compactReview[$key], $compactIdentity[$key], "compact identity carries {$key}");
            $t->same($richProvenance[$key], $richIdentity[$key], "rich identity carries {$key}");
            $t->same($richIdentity[$key], $documentIdentity[$key], "document identity carries {$key}");
            $t->same($compactReview[$key], $richProvenance[$key], "compact and rich agree on {$key}");
        }

        $t->same(4, $compactReview['manifestDeclaredSizeRoleCount']);
        $t->same([
            'manifest-declared' => 4,
            'media-resource' => 2,
            'odf-content' => 1,
            'script-package' => 1,
        ], $compactReview['manifestDeclaredSizeRoleCounts']);
        $t->same([
            'manifest-declared' => $manifestDeclaredSize,
            'media-resource' => $imageSize + $missingImageSize,
            'odf-content' => $contentDeclaredSize,
            'script-package' => $scriptSize,
        ], $compactReview['manifestDeclaredSizeRoleByteLengths']);
        $t->same([
            'manifest-declared' => 1,
            'odf-content' => 1,
        ], $compactReview['manifestDeclaredSizeRoleMismatchCounts']);
        $t->same([
            'manifest-declared' => 3,
            'media-resource' => 1,
            'odf-content' => 1,
            'script-package' => 1,
        ], $compactReview['manifestDeclaredSizeRoleExistingCounts']);
        $t->same([
            'manifest-declared' => 1,
            'media-resource' => 1,
        ], $compactReview['manifestDeclaredSizeRoleMissingCounts']);

        $summaries = $indexByRole($compactReview['manifestDeclaredSizeRoleSummaries']);
        $t->same(['content.xml', 'Pictures/hero.png', 'Pictures/missing.png', 'Basic/Standard/Review.xml'], $summaries['manifest-declared']['parts']);
        $t->same(['Pictures/hero.png', 'Pictures/missing.png'], $summaries['media-resource']['parts']);
        $t->same(['content.xml'], $summaries['odf-content']['parts']);
        $t->same(['Basic/Standard/Review.xml'], $summaries['script-package']['parts']);
        $t->same(1, $summaries['manifest-declared']['declaredSizeMismatchCount']);
        $t->same(1, $summaries['media-resource']['missingCount']);

        $changedManifest = str_replace(
            'manifest:full-path="Pictures/missing.png" manifest:media-type="image/png" manifest:size="13"',
            'manifest:full-path="Pictures/missing.png" manifest:media-type="image/png" manifest:size="21"',
            $manifestXml
        );
        $changedIdentity = OpenDocumentPackage::fromPackage($buildPackage($changedManifest))->summarize()['packageIdentity'];
        $t->same(false, $compactIdentity['identitySha256'] === $changedIdentity['identitySha256']);
        $t->same(21 + $imageSize, $changedIdentity['manifestDeclaredSizeRoleByteLengths']['media-resource']);
    },
];
