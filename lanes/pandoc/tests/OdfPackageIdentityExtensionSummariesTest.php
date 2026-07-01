<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/HERO.PNG" manifest:media-type="image/png" manifest:size="7"/>
  <manifest:file-entry manifest:full-path="Pictures/no-extension" manifest:media-type="image/svg+xml" manifest:size="6"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package identity extension summary review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/HERO.PNG', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Pictures/no-extension', 'data' => '<svg/>', 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odf package identity extension summary review');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'preserves package part extension summaries in ODF package identity metadata' => static function (TestRunner $t) use ($buildPackage, $indexBy): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $readerResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $readerResult['importReport']['manifest']['packageProvenance'];
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richIdentity = $richProvenance['packageIdentity'];

        $expectedCounts = [
            '(none)' => 3,
            'png' => 1,
            'xml' => 2,
        ];

        $t->same($expectedCounts, $compactIdentity['packagePartExtensionCounts']);
        $t->same(3, $compactIdentity['packagePartExtensionSummaryCount']);
        $t->same($compactInventory['entryNamesByPackagePartExtension'], $compactIdentity['entryNamesByPackagePartExtension']);
        $t->same($compactInventory['packagePartExtensionSummaries'], $compactIdentity['packagePartExtensionSummaries']);
        $t->same($compactIdentity['entryNamesByPackagePartExtension'], $richIdentity['entryNamesByPackagePartExtension']);
        $t->same($compactIdentity['packagePartExtensionSummaries'], $richIdentity['packagePartExtensionSummaries']);
        $t->same($richIdentity, $readerResult['document']->attr('manifest')['packageProvenance']['packageIdentity']);

        $identityExtensions = $indexBy($compactIdentity['packagePartExtensionSummaries'], 'extensionKey');
        $none = $identityExtensions['(none)'];
        $png = $identityExtensions['png'];
        $xml = $identityExtensions['xml'];

        $t->same([
            'Notes/private',
            'Pictures/no-extension',
            'mimetype',
        ], $compactIdentity['entryNamesByPackagePartExtension']['(none)']);
        $t->same(3, $none['partCount']);
        $t->same(3, $none['extensionlessPackagePartCount']);
        $t->same(1, $none['declaredPartCount']);
        $t->same(1, $none['undeclaredPartCount']);
        $t->same(1, $none['exposablePartCount']);
        $t->same(2, $none['blockedPartCount']);
        $t->same(['manifest-declared' => 1, 'media-resource' => 1, 'odf-mimetype' => 1, 'undeclared-package-entry' => 1], $none['roleCounts']);
        $t->same(['package-bytes-exposable' => 1, 'undeclared-package-entry-no-bytes' => 1], $none['byteExposurePolicyCounts']);
        $t->same('mimetype', $none['largestPart']['path']);

        $t->same(1, $png['partCount']);
        $t->same(0, $png['extensionlessPackagePartCount']);
        $t->same(['manifest-declared' => 1, 'media-resource' => 1], $png['roleCounts']);
        $t->same('Pictures/HERO.PNG', $png['largestPart']['path']);
        $t->same(['package-bytes-exposable' => 1], $png['byteExposurePolicyCounts']);

        $t->same(2, $xml['partCount']);
        $t->same(0, $xml['extensionlessPackagePartCount']);
        $t->same(['manifest-declared' => 1, 'odf-content' => 1, 'odf-manifest' => 1], $xml['roleCounts']);
    },
];
