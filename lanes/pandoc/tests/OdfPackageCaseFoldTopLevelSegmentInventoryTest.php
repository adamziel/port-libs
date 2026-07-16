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
  <manifest:file-entry manifest:full-path="PICTURES/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="pictures/thumb.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/raw.bin" manifest:media-type="application/octet-stream"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="basic/Standard/helper.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package case-fold top-level segment review.</text:p>
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
    <dc:title>Package Case-fold Top-level Segment Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'PICTURES/hero.png', 'data' => str_repeat('H', 7), 'compressionMethod' => 0],
    ['name' => 'pictures/thumb.png', 'data' => str_repeat('T', 11), 'compressionMethod' => 0],
    ['name' => 'Pictures/raw.bin', 'data' => str_repeat('R', 23), 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => '<script-review/>', 'compressionMethod' => 0],
    ['name' => 'basic/Standard/helper.xml', 'data' => '<helper/>', 'compressionMethod' => 0],
    ['name' => 'Notes/private.txt', 'data' => 'PRIVATE', 'compressionMethod' => 0],
], 'odt package case-fold top-level segment provenance');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT package case-fold top-level segments across compact and rich provenance' => static function (TestRunner $t) use ($buildPackage, $indexBy): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedTopLevelSegmentCounts = [
            'Basic' => 1,
            'META-INF' => 1,
            'Notes' => 1,
            'PICTURES' => 1,
            'Pictures' => 1,
            'basic' => 1,
            'content.xml' => 1,
            'meta.xml' => 1,
            'mimetype' => 1,
            'pictures' => 1,
            'styles.xml' => 1,
        ];
        $expectedCaseFoldCounts = [
            'basic' => 2,
            'content.xml' => 1,
            'meta-inf' => 1,
            'meta.xml' => 1,
            'mimetype' => 1,
            'notes' => 1,
            'pictures' => 3,
            'styles.xml' => 1,
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same($expectedTopLevelSegmentCounts, $handoff['packageTopLevelSegmentCounts']);
            $t->same(8, $handoff['packageCaseFoldTopLevelSegmentCount']);
            $t->same($expectedCaseFoldCounts, $handoff['packageCaseFoldTopLevelSegmentCounts']);
            $t->same(2, $handoff['duplicatePackageCaseFoldTopLevelSegmentCount']);
            $t->same(5, $handoff['duplicatePackageCaseFoldTopLevelSegmentEntryCount']);
            $t->same(['basic', 'pictures'], $handoff['duplicatePackageCaseFoldTopLevelSegments']);
        }

        $compactGroups = $indexBy($compactInventory['packageCaseFoldTopLevelSegments'], 'caseFoldTopLevelSegment');
        $richGroups = $indexBy($richProvenance['packageCaseFoldTopLevelSegments'], 'caseFoldTopLevelSegment');

        foreach ([$compactGroups['pictures'], $richGroups['pictures']] as $picturesGroup) {
            $t->same(3, $picturesGroup['topLevelSegmentVariantCount']);
            $t->same(3, $picturesGroup['entryCount']);
            $t->same(3, $picturesGroup['fileEntryCount']);
            $t->same(0, $picturesGroup['directoryEntryCount']);
            $t->same(3, $picturesGroup['declaredPartCount']);
            $t->same(0, $picturesGroup['undeclaredPartCount']);
            $t->same(41, $picturesGroup['byteLength']);
            $t->same(41, $picturesGroup['compressedByteLength']);
            $t->same(['PICTURES' => 1, 'Pictures' => 1, 'pictures' => 1], $picturesGroup['topLevelSegmentCounts']);
            $t->same([2 => 3], $picturesGroup['packagePathDepthCounts']);
            $t->same([
                'PICTURES/' => 1,
                'Pictures/' => 1,
                'pictures/' => 1,
            ], $picturesGroup['packageDirectoryCounts']);
            $t->same(['image' => 3], $picturesGroup['manifestMediaFamilyCounts']);
            $t->same([
                'application/octet-stream' => 1,
                'image/png' => 2,
            ], $picturesGroup['manifestMediaTypeBaseCounts']);
            $t->same([
                'manifest-declared' => 3,
                'media-resource' => 3,
            ], $picturesGroup['roleCounts']);
            $t->same([
                'PICTURES/hero.png',
                'Pictures/raw.bin',
                'pictures/thumb.png',
            ], $picturesGroup['entryNames']);

            $largest = $picturesGroup['largestEntry'];
            $t->same('Pictures/raw.bin', $largest['entryName']);
            $t->same('Pictures', $largest['topLevelSegment']);
            $t->same('pictures', $largest['caseFoldTopLevelSegment']);
            $t->same('Pictures/', $largest['packageDirectory']);
            $t->same('raw.bin', $largest['packageBasename']);
            $t->same(2, $largest['packagePathDepth']);
            $t->same(['Pictures', 'raw.bin'], $largest['packagePathSegments']);
            $t->same(23, $largest['byteLength']);
            $t->same(true, $largest['canExposeBytes']);
            $t->same(false, array_key_exists('contents', $largest));
        }

        foreach ([$compactGroups['basic'], $richGroups['basic']] as $basicGroup) {
            $t->same(2, $basicGroup['topLevelSegmentVariantCount']);
            $t->same(2, $basicGroup['entryCount']);
            $t->same(['Basic' => 1, 'basic' => 1], $basicGroup['topLevelSegmentCounts']);
            $t->same([3 => 2], $basicGroup['packagePathDepthCounts']);
            $t->same([
                'Basic/Standard/' => 1,
                'basic/Standard/' => 1,
            ], $basicGroup['packageDirectoryCounts']);
            $t->same(['text/xml' => 2], $basicGroup['manifestMediaTypeBaseCounts']);
            $t->same([
                'manifest-declared' => 2,
                'script-package' => 2,
            ], $basicGroup['roleCounts']);
            $t->same([
                'Basic/Standard/Review.xml',
                'basic/Standard/helper.xml',
            ], $basicGroup['entryNames']);
        }

        $t->same(
            $compactInventory['packageCaseFoldTopLevelSegmentCounts'],
            $richProvenance['packageCaseFoldTopLevelSegmentCounts']
        );
        $t->same(
            $richProvenance['packageCaseFoldTopLevelSegmentCounts'],
            $documentProvenance['packageCaseFoldTopLevelSegmentCounts']
        );
        $t->same(
            $compactInventory['duplicatePackageCaseFoldTopLevelSegments'],
            $compactIdentity['duplicatePackageCaseFoldTopLevelSegments']
        );
        $t->same(
            $richProvenance['packageCaseFoldTopLevelSegments'],
            $richIdentity['packageCaseFoldTopLevelSegments']
        );
        $t->same('pictures', strtolower((string) $compactInventory['parts']['PICTURES/hero.png']['pathShape']['topLevelSegment']));
        $t->same('pictures', strtolower((string) $richProvenance['parts']['Pictures/raw.bin']['packageTopLevelSegment']));
    },
];
