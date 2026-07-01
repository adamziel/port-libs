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
  <manifest:file-entry manifest:full-path="Pictures/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Coverage review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="CoverageBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Coverage Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/orphan.png', 'data' => 'ORPHAN', 'compressionMethod' => 0],
], 'odt manifest package coverage');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT manifest package coverage across compact and rich package provenance' => static function (TestRunner $t) use ($buildPackage, $indexBy): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactCoverage = $compactSummary['manifestPackageCoverage'];
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richCoverage = $richProvenance['manifestPackageCoverage'];
        $richIdentity = $richProvenance['packageIdentity'];

        $expectedReferencePaths = [
            'Pictures/',
            'Pictures/hero.png',
            'Pictures/missing.png',
            'content.xml',
            'meta.xml',
            'styles.xml',
        ];
        $expectedCoveredPaths = [
            'Pictures/hero.png',
            'content.xml',
            'meta.xml',
            'styles.xml',
        ];
        $expectedExistingPaths = [
            'Pictures/',
            'Pictures/hero.png',
            'content.xml',
            'meta.xml',
            'styles.xml',
        ];
        $expectedPackagePaths = [
            'META-INF/manifest.xml',
            'Pictures/hero.png',
            'Pictures/orphan.png',
            'content.xml',
            'meta.xml',
            'mimetype',
            'styles.xml',
        ];

        foreach ([$compactCoverage, $richCoverage] as $coverage) {
            $t->same(true, $coverage['present']);
            $t->same(6, $coverage['manifestPackageReferenceCount']);
            $t->same(5, $coverage['manifestPackageFileReferenceCount']);
            $t->same(1, $coverage['manifestPackageDirectoryReferenceCount']);
            $t->same(5, $coverage['manifestPackageExistingReferenceCount']);
            $t->same(4, $coverage['manifestPackageCoveredReferenceCount']);
            $t->same(1, $coverage['manifestPackageMissingReferenceCount']);
            $t->same(1, $coverage['manifestPackageVirtualDirectoryReferenceCount']);
            $t->same(false, $coverage['manifestPackageCoverageComplete']);
            $t->same(false, $coverage['manifestPackageZipCoverageComplete']);
            $t->same($expectedReferencePaths, $coverage['manifestPackageReferencePaths']);
            $t->same($expectedExistingPaths, $coverage['manifestPackageExistingReferencePaths']);
            $t->same($expectedCoveredPaths, $coverage['manifestPackageCoveredReferencePaths']);
            $t->same(['Pictures/missing.png'], $coverage['manifestPackageMissingReferencePaths']);
            $t->same(['Pictures/'], $coverage['manifestPackageDirectoryReferencePaths']);
            $t->same(['Pictures/'], $coverage['manifestPackageVirtualDirectoryReferencePaths']);
            $t->same(7, $coverage['packageEntryCount']);
            $t->same(7, $coverage['packageFileEntryCount']);
            $t->same(0, $coverage['packageDirectoryEntryCount']);
            $t->same(4, $coverage['packageDeclaredZipEntryCount']);
            $t->same(1, $coverage['packageUndeclaredZipEntryCount']);
            $t->same($expectedPackagePaths, $coverage['packageEntryPaths']);
            $t->same($expectedCoveredPaths, $coverage['packageDeclaredZipEntryPaths']);
            $t->same(['Pictures/orphan.png'], $coverage['packageUndeclaredZipEntryPaths']);
            $t->same(2, $coverage['issueCount']);
            $t->same([
                'missing-manifest-declared-package-references',
                'undeclared-zip-package-entries',
            ], $coverage['issueCodes']);
            $t->same('odf-manifest-package-coverage-metadata-only', $coverage['byteExposurePolicy']);
            $t->same(false, $coverage['canExposeBytes']);

            $references = $indexBy($coverage['manifestReferences'], 'packagePath');
            $t->same(true, $references['Pictures/']['virtualDirectoryReference']);
            $t->same(false, $references['Pictures/']['hasZipEntry']);
            $t->same(true, $references['Pictures/']['exists']);
            $t->same(true, $references['Pictures/missing.png']['missingPackageReference']);
            $t->same(false, $references['Pictures/missing.png']['exists']);
            $t->same(['manifest-declared', 'media-resource'], $references['Pictures/hero.png']['roles']);
        }

        $t->same($compactCoverage, $compactInventory['manifestPackageCoverage']);
        $t->same($compactCoverage, $compactIdentity['manifestPackageCoverage']);
        $t->same($richCoverage, $richResult['document']->attr('manifest')['packageProvenance']['manifestPackageCoverage']);
        $t->same($richCoverage, $richIdentity['manifestPackageCoverage']);
        $t->same($compactCoverage['issueCodes'], $richCoverage['issueCodes']);
        $t->same(2, $compactInventory['manifestPackageCoverageIssueCount']);
        $t->same(1, $compactInventory['manifestPackageMissingReferenceCount']);
        $t->same(1, $compactInventory['manifestPackageUndeclaredZipEntryCount']);
        $t->same(2, $richProvenance['manifestPackageCoverageIssueCount']);
        $t->same(1, $richProvenance['manifestPackageMissingReferenceCount']);
        $t->same(1, $richProvenance['manifestPackageUndeclaredZipEntryCount']);
    },
];
