<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/text-attr.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>ZIP platform attribute issue buckets.</text:p>
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
    <style:style style:name="PlatformIssueBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>ZIP Platform Attribute Issue Buckets</dc:title>
  </office:meta>
</office:document-meta>
XML;

$scriptXml = '<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review">Sub Main' . "\n" . 'End Sub</script:module>';
$configurationXml = '<config:config-item-set xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0" config:name="Review"/>';
$textAttributePng = 'TEXTPNG';

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => $scriptXml, 'compressionMethod' => 0, 'externalAttributes' => 0x81ed0000],
    ['name' => 'Configurations2/accelerator/current.xml', 'data' => $configurationXml, 'compressionMethod' => 0, 'creatorHostSystem' => 10, 'externalAttributes' => 0x00000022],
    ['name' => 'Pictures/text-attr.png', 'data' => $textAttributePng, 'compressionMethod' => 0, 'externalAttributes' => 0x81a40000, 'internalAttributes' => 0x0001],
], 'odt zip platform attribute issue buckets');

$platformIssueSubset = static function (array $packet): array {
    $keys = [
        'packagePlatformAttributeIssueEntryCount',
        'packagePlatformAttributeIssueOccurrenceCount',
        'packagePlatformAttributeIssueCounts',
        'packagePlatformAttributeIssueByteLengths',
        'packagePlatformAttributeIssueCompressedByteLengths',
        'entryNamesByPackagePlatformAttributeIssue',
        'packagePlatformAttributeIssueRoleCount',
        'packagePlatformAttributeIssueRoleCounts',
        'entryNamesByPackagePlatformAttributeIssueRole',
        'packagePlatformAttributeIssueManifestMediaFamilyCount',
        'packagePlatformAttributeIssueManifestMediaFamilyCounts',
        'entryNamesByPackagePlatformAttributeIssueManifestMediaFamily',
        'packagePlatformAttributeIssueSummaries',
    ];

    $subset = [];
    foreach ($keys as $key) {
        $subset[$key] = $packet[$key];
    }

    return $subset;
};

return [
    'buckets ODT ZIP platform attribute issues by package role and media family' => static function (TestRunner $t) use (
        $buildPackage,
        $platformIssueSubset,
        $scriptXml,
        $configurationXml,
        $textAttributePng
    ): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedIssueCounts = [
            'dos-hidden-attribute' => 1,
            'internal-text-attribute' => 1,
            'unix-executable-file' => 1,
        ];
        $expectedEntryNames = [
            'dos-hidden-attribute' => ['Configurations2/accelerator/current.xml'],
            'internal-text-attribute' => ['Pictures/text-attr.png'],
            'unix-executable-file' => ['Basic/Standard/Review.xml'],
        ];
        $expectedRoleCounts = [
            'dos-hidden-attribute' => ['configuration-package' => 1, 'manifest-declared' => 1],
            'internal-text-attribute' => ['manifest-declared' => 1, 'media-resource' => 1],
            'unix-executable-file' => ['manifest-declared' => 1, 'script-package' => 1],
        ];
        $expectedRoleEntryNames = [
            'dos-hidden-attribute' => [
                'configuration-package' => ['Configurations2/accelerator/current.xml'],
                'manifest-declared' => ['Configurations2/accelerator/current.xml'],
            ],
            'internal-text-attribute' => [
                'manifest-declared' => ['Pictures/text-attr.png'],
                'media-resource' => ['Pictures/text-attr.png'],
            ],
            'unix-executable-file' => [
                'manifest-declared' => ['Basic/Standard/Review.xml'],
                'script-package' => ['Basic/Standard/Review.xml'],
            ],
        ];
        $expectedFamilyCounts = [
            'dos-hidden-attribute' => ['configuration' => 1],
            'internal-text-attribute' => ['image' => 1],
            'unix-executable-file' => ['script' => 1],
        ];
        $expectedFamilyEntryNames = [
            'dos-hidden-attribute' => ['configuration' => ['Configurations2/accelerator/current.xml']],
            'internal-text-attribute' => ['image' => ['Pictures/text-attr.png']],
            'unix-executable-file' => ['script' => ['Basic/Standard/Review.xml']],
        ];
        $expectedByteLengths = [
            'dos-hidden-attribute' => strlen($configurationXml),
            'internal-text-attribute' => strlen($textAttributePng),
            'unix-executable-file' => strlen($scriptXml),
        ];
        $expectedSummaries = [
            [
                'issueCode' => 'dos-hidden-attribute',
                'entryCount' => 1,
                'byteLength' => strlen($configurationXml),
                'compressedByteLength' => strlen($configurationXml),
                'roleCount' => 2,
                'roles' => ['configuration-package', 'manifest-declared'],
                'roleCounts' => ['configuration-package' => 1, 'manifest-declared' => 1],
                'manifestMediaFamilyCount' => 1,
                'manifestMediaFamilies' => ['configuration'],
                'manifestMediaFamilyCounts' => ['configuration' => 1],
                'entryNames' => ['Configurations2/accelerator/current.xml'],
            ],
            [
                'issueCode' => 'internal-text-attribute',
                'entryCount' => 1,
                'byteLength' => strlen($textAttributePng),
                'compressedByteLength' => strlen($textAttributePng),
                'roleCount' => 2,
                'roles' => ['manifest-declared', 'media-resource'],
                'roleCounts' => ['manifest-declared' => 1, 'media-resource' => 1],
                'manifestMediaFamilyCount' => 1,
                'manifestMediaFamilies' => ['image'],
                'manifestMediaFamilyCounts' => ['image' => 1],
                'entryNames' => ['Pictures/text-attr.png'],
            ],
            [
                'issueCode' => 'unix-executable-file',
                'entryCount' => 1,
                'byteLength' => strlen($scriptXml),
                'compressedByteLength' => strlen($scriptXml),
                'roleCount' => 2,
                'roles' => ['manifest-declared', 'script-package'],
                'roleCounts' => ['manifest-declared' => 1, 'script-package' => 1],
                'manifestMediaFamilyCount' => 1,
                'manifestMediaFamilies' => ['script'],
                'manifestMediaFamilyCounts' => ['script' => 1],
                'entryNames' => ['Basic/Standard/Review.xml'],
            ],
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $packet) {
            $t->same(3, $packet['packagePlatformAttributeIssueEntryCount']);
            $t->same(3, $packet['packagePlatformAttributeIssueOccurrenceCount']);
            $t->same($expectedIssueCounts, $packet['packagePlatformAttributeIssueCounts']);
            $t->same($expectedByteLengths, $packet['packagePlatformAttributeIssueByteLengths']);
            $t->same($expectedByteLengths, $packet['packagePlatformAttributeIssueCompressedByteLengths']);
            $t->same($expectedEntryNames, $packet['entryNamesByPackagePlatformAttributeIssue']);
            $t->same(3, $packet['packagePlatformAttributeIssueRoleCount']);
            $t->same($expectedRoleCounts, $packet['packagePlatformAttributeIssueRoleCounts']);
            $t->same($expectedRoleEntryNames, $packet['entryNamesByPackagePlatformAttributeIssueRole']);
            $t->same(3, $packet['packagePlatformAttributeIssueManifestMediaFamilyCount']);
            $t->same($expectedFamilyCounts, $packet['packagePlatformAttributeIssueManifestMediaFamilyCounts']);
            $t->same($expectedFamilyEntryNames, $packet['entryNamesByPackagePlatformAttributeIssueManifestMediaFamily']);
            $t->same($expectedSummaries, $packet['packagePlatformAttributeIssueSummaries']);
        }

        $t->same($platformIssueSubset($compactInventory), $platformIssueSubset($compactIdentity));
        $t->same($platformIssueSubset($compactInventory), $platformIssueSubset($richProvenance));
        $t->same($platformIssueSubset($richProvenance), $platformIssueSubset($richIdentity));
        $t->same($platformIssueSubset($richProvenance), $platformIssueSubset($documentProvenance));
    },
];
