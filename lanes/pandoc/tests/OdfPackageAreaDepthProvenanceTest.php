<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes ODT package top-level areas and directory depth buckets for package review' => static function (TestRunner $t): void {
        $heroBytes = 'PNGDATA';
        $previewBytes = str_repeat('PREVIEW', 9);
        $scriptBytes = '<script:module xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0" script:name="Review"/>';
        $configurationBytes = '<config:config-item-set xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0" config:name="Review"/>';
        $signatureBytes = <<<'XML'
<dsig:document-signatures
  xmlns:dsig="urn:oasis:names:tc:opendocument:xmlns:digitalsignature:1.0"
  xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:Signature Id="sig-review">
    <ds:SignedInfo>
      <ds:Reference URI="content.xml"/>
    </ds:SignedInfo>
    <ds:SignatureValue>abc123</ds:SignatureValue>
  </ds:Signature>
</dsig:document-signatures>
XML;
        $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/deep/cache/preview.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Object 1/" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet"/>
  <manifest:file-entry manifest:full-path="Object 1/content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;
        $contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Area depth review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:styles/>
</office:document-styles>
XML;
        $metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Area Depth Review</dc:title>
  </office:meta>
</office:document-meta>
XML;
        $settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  office:version="1.3">
  <office:settings/>
</office:document-settings>
XML;
        $package = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'styles.xml', 'data' => $stylesXml],
            ['name' => 'meta.xml', 'data' => $metaXml],
            ['name' => 'settings.xml', 'data' => $settingsXml],
            ['name' => 'Pictures/hero.png', 'data' => $heroBytes, 'compressionMethod' => 0],
            ['name' => 'Pictures/deep/cache/preview.png', 'data' => $previewBytes, 'compressionMethod' => 0],
            ['name' => 'Basic/Standard/Review.xml', 'data' => $scriptBytes, 'compressionMethod' => 0],
            ['name' => 'Configurations2/accelerator/current.xml', 'data' => $configurationBytes, 'compressionMethod' => 0],
            ['name' => 'Object 1/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'Object 1/content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureBytes, 'compressionMethod' => 0],
            ['name' => 'Notes/private.txt', 'data' => 'PRIVATE', 'compressionMethod' => 0],
        ], 'odt area depth review');

        $compact = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compact['packageInventory'];
        $readerResult = (new OdfReader())->readPackage($package);
        $provenance = $readerResult['importReport']['manifest']['packageProvenance'];
        $identity = $provenance['packageIdentity'];

        $expectedTopLevelCounts = [
            'Basic' => 1,
            'Configurations2' => 1,
            'META-INF' => 2,
            'Notes' => 1,
            'Object 1' => 2,
            'Pictures' => 2,
            'content.xml' => 1,
            'meta.xml' => 1,
            'mimetype' => 1,
            'settings.xml' => 1,
            'styles.xml' => 1,
        ];
        $expectedDepthCounts = [
            0 => 6,
            1 => 5,
            2 => 2,
            3 => 1,
        ];

        $t->same($expectedTopLevelCounts, $compactInventory['packageTopLevelSegmentCounts']);
        $t->same($expectedTopLevelCounts, $provenance['packageTopLevelSegmentCounts']);
        $t->same($expectedDepthCounts, $compactInventory['packageDirectoryDepthCounts']);
        $t->same($expectedDepthCounts, $provenance['packageDirectoryDepthCounts']);
        $t->same(11, $provenance['packageTopLevelSegmentCount']);
        $t->same(4, $provenance['packageDirectoryDepthCount']);
        $t->same(3, $provenance['maxPackageDirectoryDepth']);
        $t->same(['Pictures/deep/cache/preview.png'], $provenance['deepestPackagePartNames']);
        $t->same($provenance, $readerResult['document']->attr('manifest')['packageProvenance']);
        $t->same($expectedTopLevelCounts, $identity['packageTopLevelSegmentCounts']);
        $t->same($expectedDepthCounts, $identity['packageDirectoryDepthCounts']);
        $t->same(['Pictures/deep/cache/preview.png'], $identity['deepestPackagePartNames']);
        $t->same($compactInventory['packageTopLevelSegmentCounts'], $compact['packageIdentity']['packageTopLevelSegmentCounts']);
        $t->same($compactInventory['packageDirectoryDepthCounts'], $compact['packageIdentity']['packageDirectoryDepthCounts']);

        $readerParts = $provenance['parts'];
        $compactParts = $compactInventory['parts'];
        $t->same('Pictures', $readerParts['Pictures/deep/cache/preview.png']['topLevelSegment']);
        $t->same('Pictures/deep/cache', $readerParts['Pictures/deep/cache/preview.png']['directory']);
        $t->same(3, $readerParts['Pictures/deep/cache/preview.png']['directoryDepth']);
        $t->same(4, $readerParts['Pictures/deep/cache/preview.png']['pathSegmentCount']);
        $t->same(['Pictures', 'deep', 'cache', 'preview.png'], $readerParts['Pictures/deep/cache/preview.png']['pathSegments']);
        $t->same('Notes', $readerParts['Notes/private.txt']['topLevelSegment']);
        $t->same(1, $readerParts['Notes/private.txt']['directoryDepth']);
        $t->same('Notes', $compactParts['Notes/private.txt']['topLevelSegment']);

        $segments = [];
        foreach ($provenance['packageTopLevelSegmentSummaries'] as $segment) {
            $segments[$segment['topLevelSegment']] = $segment;
        }
        $depths = [];
        foreach ($provenance['packageDirectoryDepthSummaries'] as $depth) {
            $depths[$depth['directoryDepth']] = $depth;
        }
        $roles = [];
        foreach ($provenance['packageRoleSummaries'] as $role) {
            $roles[$role['role']] = $role;
        }

        $pictures = $segments['Pictures'];
        $t->same(2, $pictures['partCount']);
        $t->same(strlen($heroBytes) + strlen($previewBytes), $pictures['byteLength']);
        $t->same([1 => 1, 3 => 1], $pictures['directoryDepthCounts']);
        $t->same(['Pictures', 'Pictures/deep/cache'], $pictures['directories']);
        $t->same(['manifest-declared' => 2, 'media-resource' => 2], $pictures['roleCounts']);
        $t->same('Pictures/deep/cache/preview.png', $pictures['largestPart']['part']);
        $t->same('Pictures/deep/cache/preview.png', $pictures['deepestPart']['part']);

        $depthThree = $depths[3];
        $t->same(1, $depthThree['partCount']);
        $t->same(['Pictures' => 1], $depthThree['topLevelSegmentCounts']);
        $t->same(['Pictures/deep/cache'], $depthThree['directories']);
        $t->same(['Pictures/deep/cache/preview.png'], $depthThree['partNames']);
        $t->same('Pictures/deep/cache/preview.png', $depthThree['largestPart']['part']);

        $script = $roles['script-package'];
        $t->same(['Basic' => 1], $script['topLevelSegmentCounts']);
        $t->same([2 => 1], $script['directoryDepthCounts']);
        $t->same(['script-package-bytes-blocked' => 1], $script['byteExposurePolicyCounts']);
        $t->same('Basic/Standard/Review.xml', $script['deepestPart']['part']);

        $undeclared = $roles['undeclared-package-entry'];
        $t->same(['Notes' => 1], $undeclared['topLevelSegmentCounts']);
        $t->same([1 => 1], $undeclared['directoryDepthCounts']);
        $t->same(['undeclared-package-entry-no-bytes' => 1], $undeclared['byteExposurePolicyCounts']);
        $t->same('Notes/private.txt', $undeclared['largestPart']['part']);

        $signature = $roles['package-signature'];
        $t->same(['META-INF' => 1], $signature['topLevelSegmentCounts']);
        $t->same([1 => 1], $signature['directoryDepthCounts']);
        $t->same('META-INF/documentsignatures.xml', $signature['largestPart']['part']);
    },
];
