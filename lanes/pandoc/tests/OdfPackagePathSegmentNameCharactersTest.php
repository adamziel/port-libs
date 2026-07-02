<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$nonAsciiSegment = "caf\xC3\xA9";
$nonAsciiPart = 'Pictures/' . $nonAsciiSegment . '/review.xml';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/Review.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/media draft/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Package path segment name character review.</text:p>
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
    <dc:title>Package path segment name characters</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/Review.PNG', 'data' => 'upper-image-payload', 'compressionMethod' => 0],
    ['name' => 'Pictures/media draft/review.png', 'data' => 'draft-image', 'compressionMethod' => 0],
    ['name' => 'Pictures/media%20encoded/review.xml', 'data' => '<encoded/>', 'compressionMethod' => 0],
    ['name' => $nonAsciiPart, 'data' => '<non-ascii/>', 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => '<script-review/>', 'compressionMethod' => 0],
    ['name' => 'Object%20Chart/content.xml', 'data' => '<chart/>', 'compressionMethod' => 0],
], 'odt package path segment name character review');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT package path segment name character flags across compact and rich provenance' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $nonAsciiSegment,
        $nonAsciiPart
    ): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedSegments = [
            'Basic',
            'META-INF',
            'Object%20Chart',
            'Pictures',
            'Review.PNG',
            'Review.xml',
            'Standard',
            $nonAsciiSegment,
            'media draft',
            'media%20encoded',
        ];
        $expectedFlagOccurrences = [
            'non-ascii' => 1,
            'percent-encoded-octet' => 2,
            'uppercase' => 10,
            'whitespace' => 1,
        ];
        $expectedFlagEntries = [
            'non-ascii' => 1,
            'percent-encoded-octet' => 2,
            'uppercase' => 7,
            'whitespace' => 1,
        ];
        $expectedFlagSegments = [
            'non-ascii' => [$nonAsciiSegment],
            'percent-encoded-octet' => ['Object%20Chart', 'media%20encoded'],
            'uppercase' => ['Basic', 'META-INF', 'Object%20Chart', 'Pictures', 'Review.PNG', 'Review.xml', 'Standard'],
            'whitespace' => ['media draft'],
        ];
        $expectedFlagEntriesByFlag = [
            'non-ascii' => [$nonAsciiPart],
            'percent-encoded-octet' => ['Object%20Chart/content.xml', 'Pictures/media%20encoded/review.xml'],
            'uppercase' => [
                'Basic/Standard/Review.xml',
                'META-INF/manifest.xml',
                'Object%20Chart/content.xml',
                'Pictures/Review.PNG',
                $nonAsciiPart,
                'Pictures/media draft/review.png',
                'Pictures/media%20encoded/review.xml',
            ],
            'whitespace' => ['Pictures/media draft/review.png'],
        ];

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity, $documentProvenance] as $handoff) {
            $t->same(10, $handoff['packagePathSegmentNameCharacterReviewSegmentCount']);
            $t->same(13, $handoff['packagePathSegmentNameCharacterReviewOccurrenceCount']);
            $t->same(7, $handoff['packagePathSegmentNameCharacterReviewEntryCount']);
            $t->same(10, $handoff['packagePathSegmentNameUppercaseOccurrenceCount']);
            $t->same(1, $handoff['packagePathSegmentNameWhitespaceOccurrenceCount']);
            $t->same(2, $handoff['packagePathSegmentNamePercentEncodedOctetOccurrenceCount']);
            $t->same(1, $handoff['packagePathSegmentNameNonAsciiOccurrenceCount']);
            $t->same(7, $handoff['packagePathSegmentNameUppercaseEntryCount']);
            $t->same(1, $handoff['packagePathSegmentNameWhitespaceEntryCount']);
            $t->same(2, $handoff['packagePathSegmentNamePercentEncodedOctetEntryCount']);
            $t->same(1, $handoff['packagePathSegmentNameNonAsciiEntryCount']);
            $t->same($expectedFlagOccurrences, $handoff['packagePathSegmentNameCharacterFlagOccurrenceCounts']);
            $t->same($expectedFlagEntries, $handoff['packagePathSegmentNameCharacterFlagEntryCounts']);
            $t->same($expectedFlagSegments, $handoff['packagePathSegmentNameCharacterFlagSegments']);
            $t->same($expectedFlagEntriesByFlag, $handoff['entryNamesByPackagePathSegmentNameCharacterFlag']);
            $t->same($expectedSegments, $handoff['packagePathSegmentNameCharacterReviewSegmentNames']);
        }

        $compactSegments = $indexBy($compactInventory['packagePathSegmentNameCharacterReviewSegments'], 'segment');
        $richSegments = $indexBy($richProvenance['packagePathSegmentNameCharacterReviewSegments'], 'segment');

        foreach ([$compactSegments['Pictures'], $richSegments['Pictures']] as $pictures) {
            $t->same('Pictures', $pictures['segment']);
            $t->same('pictures', $pictures['caseFoldSegment']);
            $t->same(4, $pictures['occurrenceCount']);
            $t->same(4, $pictures['entryCount']);
            $t->same(['uppercase'], $pictures['flags']);
            $t->same(['uppercase' => 4], $pictures['flagOccurrenceCounts']);
            $t->same(['uppercase' => 4], $pictures['flagEntryCounts']);
            $t->same([0 => 4], $pictures['pathSegmentIndexCounts']);
            $t->same(['first' => 4], $pictures['pathSegmentPositionCounts']);
            $t->same([2 => 1, 3 => 3], $pictures['packagePathDepthCounts']);
            $t->same(['Pictures/' => 4], $pictures['packageAreaCounts']);
            $t->same(['Pictures' => 4], $pictures['packageTopLevelSegmentCounts']);
            $t->same([
                'Pictures/' => 1,
                'Pictures/caf' . "\xC3\xA9" . '/' => 1,
                'Pictures/media draft/' => 1,
                'Pictures/media%20encoded/' => 1,
            ], $pictures['packageDirectoryCounts']);
            $t->same([
                'Review.PNG' => 1,
                'review.png' => 1,
                'review.xml' => 2,
            ], $pictures['packageBasenameCounts']);
            $t->same(['png' => 2, 'xml' => 2], $pictures['packagePartExtensionCounts']);
            $t->same(['(missing)' => 2, 'image/png' => 2], $pictures['manifestMediaTypeBaseCounts']);
            $t->same([
                'manifest-declared' => 2,
                'media-resource' => 2,
                'undeclared-package-entry' => 2,
            ], $pictures['roleCounts']);
            $t->same([
                'package-bytes-exposable' => 2,
                'undeclared-package-entry-no-bytes' => 2,
            ], $pictures['byteExposurePolicyCounts']);
            $t->same([
                'Pictures/Review.PNG',
                $nonAsciiPart,
                'Pictures/media draft/review.png',
                'Pictures/media%20encoded/review.xml',
            ], $pictures['entryNames']);
            $t->same('Pictures/Review.PNG', $pictures['largestEntry']['entryName']);
            $t->same('Pictures', $pictures['largestEntry']['segment']);
            $t->same('first', $pictures['largestEntry']['pathSegmentPosition']);
            $t->same(true, $pictures['largestEntry']['canExposeBytes']);
            $t->same(false, array_key_exists('contents', $pictures['largestEntry']));
        }

        foreach ([$compactSegments['media%20encoded'], $richSegments['media%20encoded']] as $encoded) {
            $t->same(['percent-encoded-octet'], $encoded['flags']);
            $t->same(['percent-encoded-octet' => 1], $encoded['flagEntryCounts']);
            $t->same(['middle' => 1], $encoded['pathSegmentPositionCounts']);
            $t->same(['undeclared-package-entry' => 1], $encoded['roleCounts']);
            $t->same(['undeclared-package-entry-no-bytes' => 1], $encoded['byteExposurePolicyCounts']);
            $t->same(['Pictures/media%20encoded/review.xml'], $encoded['entryNames']);
            $t->same('Pictures/media%20encoded/review.xml', $encoded['largestEntry']['entryName']);
        }

        foreach ([$compactSegments['Object%20Chart'], $richSegments['Object%20Chart']] as $object) {
            $t->same(['percent-encoded-octet', 'uppercase'], $object['flags']);
            $t->same(['percent-encoded-octet' => 1, 'uppercase' => 1], $object['flagOccurrenceCounts']);
            $t->same(['first' => 1], $object['pathSegmentPositionCounts']);
            $t->same(['Object%20Chart/' => 1], $object['packageAreaCounts']);
            $t->same(['Object%20Chart/content.xml'], $object['entryNames']);
        }

        $t->same($compactInventory['packagePathSegmentNameCharacterFlagSegments'], $richProvenance['packagePathSegmentNameCharacterFlagSegments']);
        $t->same($richProvenance['packagePathSegmentNameCharacterReviewSegments'], $documentProvenance['packagePathSegmentNameCharacterReviewSegments']);
        $t->same($compactInventory['packagePathSegmentNameCharacterReviewSegments'], $compactIdentity['packagePathSegmentNameCharacterReviewSegments']);
        $t->same($richProvenance['packagePathSegmentNameCharacterReviewSegments'], $richIdentity['packagePathSegmentNameCharacterReviewSegments']);
    },
];
