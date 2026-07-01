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
            $t->same(3, $coverage['manifestPackageDirectoryRootCount']);
            $t->same(['/' => 3, 'Pictures/' => 3], $coverage['manifestPackageReferenceDirectoryRootCounts']);
            $t->same(['/' => 3, 'Pictures/' => 2], $coverage['manifestPackageExistingReferenceDirectoryRootCounts']);
            $t->same(['/' => 3, 'Pictures/' => 1], $coverage['manifestPackageCoveredReferenceDirectoryRootCounts']);
            $t->same(['Pictures/' => 1], $coverage['manifestPackageMissingReferenceDirectoryRootCounts']);
            $t->same(['Pictures/' => 1], $coverage['manifestPackageVirtualDirectoryReferenceDirectoryRootCounts']);
            $t->same([
                'directory' => 1,
                'image' => 2,
                'xml' => 3,
            ], $coverage['manifestPackageReferenceMediaFamilyCounts']);
            $t->same(['image' => 1], $coverage['manifestPackageMissingReferenceMediaFamilyCounts']);
            $t->same([
                'directory-entry-no-bytes' => 1,
                'missing-package-part' => 1,
                'package-bytes-exposable' => 4,
            ], $coverage['manifestPackageReferenceByteExposurePolicyCounts']);
            $t->same(['missing-package-part' => 1], $coverage['manifestPackageMissingReferenceByteExposurePolicyCounts']);
            $t->same(7, $coverage['packageEntryCount']);
            $t->same(7, $coverage['packageFileEntryCount']);
            $t->same(0, $coverage['packageDirectoryEntryCount']);
            $t->same(4, $coverage['packageDeclaredZipEntryCount']);
            $t->same(1, $coverage['packageUndeclaredZipEntryCount']);
            $t->same($expectedPackagePaths, $coverage['packageEntryPaths']);
            $t->same($expectedCoveredPaths, $coverage['packageDeclaredZipEntryPaths']);
            $t->same(['Pictures/orphan.png'], $coverage['packageUndeclaredZipEntryPaths']);
            $t->same(['/' => 4, 'META-INF/' => 1, 'Pictures/' => 2], $coverage['packageDirectoryRootCounts']);
            $t->same(['/' => 3, 'Pictures/' => 1], $coverage['packageDeclaredZipEntryDirectoryRootCounts']);
            $t->same(['Pictures/' => 1], $coverage['packageUndeclaredZipEntryDirectoryRootCounts']);
            $t->same(2, $coverage['issueCount']);
            $t->same([
                'missing-manifest-declared-package-references',
                'undeclared-zip-package-entries',
            ], $coverage['issueCodes']);
            $t->same('odf-manifest-package-coverage-metadata-only', $coverage['byteExposurePolicy']);
            $t->same(false, $coverage['canExposeBytes']);

            $references = $indexBy($coverage['manifestReferences'], 'packagePath');
            $t->same('/', $references['content.xml']['directoryRoot']);
            $t->same('Pictures/', $references['Pictures/missing.png']['directoryRoot']);
            $t->same(true, $references['Pictures/']['virtualDirectoryReference']);
            $t->same(false, $references['Pictures/']['hasZipEntry']);
            $t->same(true, $references['Pictures/']['exists']);
            $t->same(true, $references['Pictures/missing.png']['missingPackageReference']);
            $t->same(false, $references['Pictures/missing.png']['exists']);
            $t->same('image', $references['Pictures/missing.png']['manifestMediaFamily']);
            $t->same('missing-package-part', $references['Pictures/missing.png']['byteExposurePolicy']);
            $t->same('directory', $references['Pictures/']['manifestMediaFamily']);
            $t->same(['manifest-declared', 'media-resource'], $references['Pictures/hero.png']['roles']);

            $rootSummaries = $indexBy($coverage['manifestPackageDirectoryRootSummaries'], 'directoryRoot');
            $t->same(3, $rootSummaries['/']['manifestPackageReferenceCount']);
            $t->same(3, $rootSummaries['/']['manifestPackageCoveredReferenceCount']);
            $t->same(0, $rootSummaries['/']['manifestPackageMissingReferenceCount']);
            $t->same(4, $rootSummaries['/']['packageEntryCount']);
            $t->same(3, $rootSummaries['/']['packageDeclaredZipEntryCount']);
            $t->same(['content.xml', 'meta.xml', 'styles.xml'], $rootSummaries['/']['manifestPackageReferencePaths']);
            $t->same(['content.xml', 'meta.xml', 'mimetype', 'styles.xml'], $rootSummaries['/']['packageEntryPaths']);
            $t->same(['xml' => 3], $rootSummaries['/']['manifestMediaFamilyCounts']);
            $t->same(['package-bytes-exposable' => 3], $rootSummaries['/']['byteExposurePolicyCounts']);

            $t->same(0, $rootSummaries['META-INF/']['manifestPackageReferenceCount']);
            $t->same(1, $rootSummaries['META-INF/']['packageEntryCount']);
            $t->same(['META-INF/manifest.xml'], $rootSummaries['META-INF/']['packageEntryPaths']);

            $t->same(3, $rootSummaries['Pictures/']['manifestPackageReferenceCount']);
            $t->same(1, $rootSummaries['Pictures/']['manifestPackageDirectoryReferenceCount']);
            $t->same(1, $rootSummaries['Pictures/']['manifestPackageCoveredReferenceCount']);
            $t->same(1, $rootSummaries['Pictures/']['manifestPackageMissingReferenceCount']);
            $t->same(1, $rootSummaries['Pictures/']['manifestPackageVirtualDirectoryReferenceCount']);
            $t->same(2, $rootSummaries['Pictures/']['packageEntryCount']);
            $t->same(1, $rootSummaries['Pictures/']['packageDeclaredZipEntryCount']);
            $t->same(1, $rootSummaries['Pictures/']['packageUndeclaredZipEntryCount']);
            $t->same(['Pictures/', 'Pictures/hero.png', 'Pictures/missing.png'], $rootSummaries['Pictures/']['manifestPackageReferencePaths']);
            $t->same(['Pictures/missing.png'], $rootSummaries['Pictures/']['manifestPackageMissingReferencePaths']);
            $t->same(['Pictures/'], $rootSummaries['Pictures/']['manifestPackageVirtualDirectoryReferencePaths']);
            $t->same(['Pictures/hero.png', 'Pictures/orphan.png'], $rootSummaries['Pictures/']['packageEntryPaths']);
            $t->same(['Pictures/orphan.png'], $rootSummaries['Pictures/']['packageUndeclaredZipEntryPaths']);
            $t->same(['directory' => 1, 'image' => 2], $rootSummaries['Pictures/']['manifestMediaFamilyCounts']);
            $t->same([
                'directory-entry-no-bytes' => 1,
                'missing-package-part' => 1,
                'package-bytes-exposable' => 1,
            ], $rootSummaries['Pictures/']['byteExposurePolicyCounts']);
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
