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
  <manifest:file-entry manifest:full-path="Pictures/HERO.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/icon.PnG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.XML" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Scripts/review.js" manifest:media-type="application/javascript"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Raw extension package review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="RawExtensionBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Raw Extension Package Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/HERO.PNG', 'data' => str_repeat('P', 32), 'compressionMethod' => 0],
    ['name' => 'Pictures/icon.PnG', 'data' => str_repeat('i', 16), 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.XML', 'data' => '<review/>', 'compressionMethod' => 0],
    ['name' => 'Scripts/review.js', 'data' => "function review() { return true; }\n", 'compressionMethod' => 0],
    ['name' => 'Notes/private', 'data' => 'PRIVATE EXTENSIONLESS', 'compressionMethod' => 0],
], 'odt package raw extension inventory');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT package parts by raw extension across package identities' => static function (TestRunner $t) use ($buildPackage, $indexBy): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        $expectedCounts = [
            '(none)' => 2,
            'PNG' => 1,
            'PnG' => 1,
            'XML' => 1,
            'js' => 1,
            'xml' => 4,
        ];
        $expectedNamesByRawExtension = [
            '(none)' => ['Notes/private', 'mimetype'],
            'PNG' => ['Pictures/HERO.PNG'],
            'PnG' => ['Pictures/icon.PnG'],
            'XML' => ['Basic/Standard/Review.XML'],
            'js' => ['Scripts/review.js'],
            'xml' => ['META-INF/manifest.xml', 'content.xml', 'meta.xml', 'styles.xml'],
        ];

        foreach ([
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
            'document identity' => $documentIdentity,
        ] as $label => $handoff) {
            $t->same(count($expectedCounts), $handoff['packagePartRawExtensionCount'], "{$label} raw extension count");
            $t->same($expectedCounts, $handoff['packagePartRawExtensionCounts'], "{$label} raw extension counts");
            $t->same($expectedNamesByRawExtension, $handoff['entryNamesByPackagePartRawExtension'], "{$label} raw extension names");
            $t->same(2, $handoff['extensionlessPackagePartCount'], "{$label} extensionless count");
            $t->same(3, $handoff['packagePartRawExtensionUppercasePartCount'], "{$label} uppercase raw extension count");
            $t->same(3, $handoff['packagePartRawExtensionNormalizedPartCount'], "{$label} normalized raw extension count");
            $t->same(2, $handoff['packagePartExtensionCaseVariantCount'], "{$label} extension case variant count");
            $t->same(['png', 'xml'], $handoff['packagePartExtensionCaseVariantExtensions'], "{$label} extension case variant names");
            $t->same(3, $handoff['packagePartExtensionUppercasePartCount'], "{$label} extension uppercase part count");
        }

        $compactRawExtensions = $indexBy($compactInventory['packagePartRawExtensionSummaries'], 'rawExtensionKey');
        $richRawExtensions = $indexBy($richProvenance['packagePartRawExtensionSummaries'], 'rawExtensionKey');
        $identityRawExtensions = $indexBy($richIdentity['packagePartRawExtensionSummaries'], 'rawExtensionKey');
        $compactCaseVariants = $indexBy($compactInventory['packagePartExtensionCaseVariants'], 'packagePartExtension');
        $richCaseVariants = $indexBy($richProvenance['packagePartExtensionCaseVariants'], 'packagePartExtension');
        $identityCaseVariants = $indexBy($richIdentity['packagePartExtensionCaseVariants'], 'packagePartExtension');

        $t->same($compactInventory['packagePartRawExtensionSummaries'], $compactIdentity['packagePartRawExtensionSummaries']);
        $t->same($richProvenance['packagePartRawExtensionSummaries'], $richIdentity['packagePartRawExtensionSummaries']);
        $t->same($richIdentity['packagePartRawExtensionSummaries'], $documentIdentity['packagePartRawExtensionSummaries']);
        $t->same($compactInventory['packagePartExtensionCaseVariants'], $compactIdentity['packagePartExtensionCaseVariants']);
        $t->same($richProvenance['packagePartExtensionCaseVariants'], $richIdentity['packagePartExtensionCaseVariants']);
        $t->same($richIdentity['packagePartExtensionCaseVariants'], $documentIdentity['packagePartExtensionCaseVariants']);

        $png = $compactRawExtensions['PNG'];
        $t->same('PNG', $png['rawPackagePartExtension']);
        $t->same(false, $png['extensionlessPackagePart']);
        $t->same(1, $png['partCount']);
        $t->same(1, $png['uppercasePartCount']);
        $t->same(1, $png['normalizedPartCount']);
        $t->same(['png' => 1], $png['packagePartExtensionCounts']);
        $t->same(['Pictures/HERO.PNG'], $png['partNames']);
        $t->same('Pictures/HERO.PNG', $png['largestPart']['path']);
        $t->same('PNG', $png['largestPart']['rawPackagePartExtension']);
        $t->same('png', $png['largestPart']['packagePartExtension']);
        $t->same(true, $png['largestPart']['packagePartExtensionHasUppercase']);
        $t->same(true, $png['largestPart']['packagePartExtensionWasNormalized']);
        $t->same(false, array_key_exists('contents', $png['largestPart']));
        foreach ([$richRawExtensions['PNG'], $identityRawExtensions['PNG']] as $richPng) {
            $t->same('PNG', $richPng['rawPackagePartExtension']);
            $t->same(['png' => 1], $richPng['packagePartExtensionCounts']);
            $t->same(['Pictures/HERO.PNG'], $richPng['partNames']);
            $t->same(1, $richPng['uppercasePartCount']);
            $t->same(1, $richPng['normalizedPartCount']);
            $t->same('Pictures/HERO.PNG', $richPng['largestPart']['path']);
            $t->same('PNG', $richPng['largestPart']['rawPackagePartExtension']);
            $t->same(false, array_key_exists('contents', $richPng['largestPart']));
        }

        $mixedPng = $compactRawExtensions['PnG'];
        $t->same(['png' => 1], $mixedPng['packagePartExtensionCounts']);
        $t->same(['Pictures/icon.PnG'], $mixedPng['partNames']);
        $t->same(1, $mixedPng['uppercasePartCount']);
        $t->same(1, $mixedPng['normalizedPartCount']);

        $pngVariant = $compactCaseVariants['png'];
        $t->same(2, $pngVariant['partCount']);
        $t->same(2, $pngVariant['uppercasePartCount']);
        $t->same(['PNG' => 1, 'PnG' => 1], $pngVariant['rawExtensionCounts']);
        $t->same([
            'PNG' => ['Pictures/HERO.PNG'],
            'PnG' => ['Pictures/icon.PnG'],
        ], $pngVariant['rawExtensionPartNames']);
        $t->same(['Pictures/HERO.PNG', 'Pictures/icon.PnG'], $pngVariant['partNames']);
        $t->same('Pictures/HERO.PNG', $pngVariant['largestPart']['path']);
        $t->same(false, array_key_exists('contents', $pngVariant['largestPart']));
        foreach ([$richCaseVariants['png'], $identityCaseVariants['png']] as $richPngVariant) {
            $t->same($pngVariant['rawExtensionCounts'], $richPngVariant['rawExtensionCounts']);
            $t->same($pngVariant['rawExtensionPartNames'], $richPngVariant['rawExtensionPartNames']);
            $t->same($pngVariant['partNames'], $richPngVariant['partNames']);
            $t->same(false, array_key_exists('contents', $richPngVariant['largestPart']));
        }

        $xmlVariant = $compactCaseVariants['xml'];
        $t->same(5, $xmlVariant['partCount']);
        $t->same(1, $xmlVariant['uppercasePartCount']);
        $t->same(['XML' => 1, 'xml' => 4], $xmlVariant['rawExtensionCounts']);
        $t->same(['Basic/Standard/Review.XML'], $xmlVariant['rawExtensionPartNames']['XML']);
        $t->same([
            'META-INF/manifest.xml',
            'content.xml',
            'meta.xml',
            'styles.xml',
        ], $xmlVariant['rawExtensionPartNames']['xml']);

        $extensionless = $compactRawExtensions['(none)'];
        $t->same(null, $extensionless['rawPackagePartExtension']);
        $t->same(true, $extensionless['extensionlessPackagePart']);
        $t->same(2, $extensionless['partCount']);
        $t->same(0, $extensionless['uppercasePartCount']);
        $t->same(0, $extensionless['normalizedPartCount']);
        $t->same(['(none)' => 2], $extensionless['packagePartExtensionCounts']);
        $t->same(['Notes/private', 'mimetype'], $extensionless['partNames']);

        $compactParts = $compactInventory['parts'];
        $richParts = $richProvenance['parts'];
        $compactIdentityParts = $indexBy($compactIdentity['packageEntries'], 'path');
        $richIdentityParts = $indexBy($richIdentity['packageEntries'], 'part');
        foreach (['Pictures/HERO.PNG', 'Pictures/icon.PnG', 'Basic/Standard/Review.XML', 'Notes/private'] as $part) {
            $t->same($compactParts[$part]['rawPackagePartExtension'], $compactIdentityParts[$part]['rawPackagePartExtension'] ?? null, "{$part} compact identity raw extension");
            $t->same($richParts[$part]['rawPackagePartExtension'], $richIdentityParts[$part]['rawPackagePartExtension'] ?? null, "{$part} rich identity raw extension");
            $t->same($compactParts[$part]['packagePartExtensionWasNormalized'], $richParts[$part]['packagePartExtensionWasNormalized'], "{$part} normalized parity");
        }
    },
];
