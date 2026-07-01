<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$nonAsciiPart = "media/caf\xC3\xA9.xml";

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Media/HERO.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="media/draft review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="media/normal.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package path character review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="ReviewBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Package Path Character Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Media/HERO.PNG', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'media/draft review.png', 'data' => 'DRAFTPNG', 'compressionMethod' => 0],
    ['name' => 'media/normal.png', 'data' => 'NORMALPNG', 'compressionMethod' => 0],
    ['name' => 'media/media%20encoded.xml', 'data' => '<encoded/>', 'compressionMethod' => 0],
    ['name' => $nonAsciiPart, 'data' => '<non-ascii/>', 'compressionMethod' => 0],
], 'odt package path character provenance');

$indexEntries = static function (array $entries, string $key): array {
    $indexed = [];
    foreach ($entries as $entry) {
        $indexed[(string) $entry[$key]] = $entry;
    }

    return $indexed;
};

$indexReviewEntries = static function (array $handoff): array {
    $indexed = [];
    foreach ($handoff['packagePathCharacterReviewEntries'] as $entry) {
        $indexed[(string) $entry['entryName']] = $entry;
    }

    return $indexed;
};

return [
    'summarizes ODT package path character flags across compact and rich package provenance' => static function (TestRunner $t) use (
        $buildPackage,
        $indexEntries,
        $indexReviewEntries,
        $nonAsciiPart
    ): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedFlagCounts = [
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 2,
            'whitespace' => 1,
        ];
        $expectedEntryNamesByFlag = [
            'non-ascii' => [$nonAsciiPart],
            'percent-encoded-octet' => ['media/media%20encoded.xml'],
            'uppercase' => ['META-INF/manifest.xml', 'Media/HERO.PNG'],
            'whitespace' => ['media/draft review.png'],
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(5, $handoff['packagePathCharacterReviewEntryCount']);
            $t->same(4, $handoff['packagePathCharacterFlagCount']);
            $t->same(2, $handoff['packagePathUppercaseEntryCount']);
            $t->same(1, $handoff['packagePathWhitespaceEntryCount']);
            $t->same(1, $handoff['packagePathPercentEncodedOctetEntryCount']);
            $t->same(1, $handoff['packagePathNonAsciiEntryCount']);
            $t->same($expectedFlagCounts, $handoff['packagePathCharacterFlagEntryCounts']);
            $t->same($expectedEntryNamesByFlag, $handoff['entryNamesByPackagePathCharacterFlag']);
            $t->same('odf-package-path-character-metadata-only', $handoff['packagePathCharacterByteExposurePolicy']);
            $t->same(false, $handoff['packagePathCharacterCanExposeBytes']);

            $reviewEntries = $indexReviewEntries($handoff);
            $t->same(['uppercase'], $reviewEntries['META-INF/manifest.xml']['flags']);
            $t->same(['uppercase'], $reviewEntries['Media/HERO.PNG']['flags']);
            $t->same(['whitespace'], $reviewEntries['media/draft review.png']['flags']);
            $t->same(['percent-encoded-octet'], $reviewEntries['media/media%20encoded.xml']['flags']);
            $t->same(['non-ascii'], $reviewEntries[$nonAsciiPart]['flags']);
            $t->same('package-bytes-exposable', $reviewEntries['Media/HERO.PNG']['byteExposurePolicy']);
            $t->same('undeclared-package-entry-no-bytes', $reviewEntries[$nonAsciiPart]['byteExposurePolicy']);
            $t->same('odf-package-path-character-metadata-only', $reviewEntries['Media/HERO.PNG']['reviewPolicy']);
        }

        $compactIdentityEntries = $indexEntries($compactIdentity['packageEntries'], 'path');
        $richIdentityEntries = $indexEntries($richIdentity['packageEntries'], 'part');

        $t->same(['uppercase'], $compactInventory['parts']['Media/HERO.PNG']['packagePathCharacterFlags']);
        $t->same(true, $compactInventory['parts']['Media/HERO.PNG']['packagePathHasUppercase']);
        $t->same(true, $compactInventory['parts']['media/draft review.png']['packagePathHasWhitespace']);
        $t->same(true, $compactInventory['parts']['media/media%20encoded.xml']['packagePathHasPercentEncodedOctet']);
        $t->same(true, $compactInventory['parts'][$nonAsciiPart]['packagePathHasNonAscii']);
        $t->same([], $compactInventory['parts']['media/normal.png']['packagePathCharacterFlags']);
        $t->same(false, $compactInventory['parts']['media/normal.png']['packagePathHasUppercase']);

        $t->same(['uppercase'], $compactIdentityEntries['Media/HERO.PNG']['packagePathCharacterFlags']);
        $t->same(true, $compactIdentityEntries['Media/HERO.PNG']['packagePathHasUppercase']);
        $t->same(true, $richProvenance['parts']['media/draft review.png']['packagePathHasWhitespace']);
        $t->same(['percent-encoded-octet'], $richIdentityEntries['media/media%20encoded.xml']['packagePathCharacterFlags']);
        $t->same(true, $richIdentityEntries[$nonAsciiPart]['packagePathHasNonAscii']);
        $t->same(false, $richIdentityEntries['media/normal.png']['packagePathHasUppercase']);
    },
];
