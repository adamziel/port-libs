<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$nonAsciiSegment = "caf\xC3\xA9";
$nonAsciiPart = 'Objects/' . $nonAsciiSegment . '/review.xml';
$heroPart = 'Pictures/HERO Image.PNG';
$encodedPart = 'Pictures/media%20encoded.xml';
$spacePart = 'notes/Space Name.txt';

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="{$heroPart}" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="{$encodedPart}" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="{$nonAsciiPart}" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="{$spacePart}" manifest:media-type="text/plain"/>
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
    <style:style style:name="SegmentNameReviewBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Package path segment name character review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => $heroPart, 'data' => str_repeat('P', 25), 'compressionMethod' => 0],
    ['name' => $encodedPart, 'data' => '<encoded/>', 'compressionMethod' => 0],
    ['name' => $nonAsciiPart, 'data' => '<review/>', 'compressionMethod' => 0],
    ['name' => $spacePart, 'data' => 'plain note', 'compressionMethod' => 0],
], 'odt package path segment name character review');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

return [
    'summarizes ODT package path segment name characters across compact and rich package provenance' => static function (TestRunner $t) use (
        $buildPackage,
        $encodedPart,
        $heroPart,
        $indexBy,
        $nonAsciiPart,
        $nonAsciiSegment,
        $spacePart
    ): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedFlagOccurrenceCounts = [
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 6,
            'whitespace' => 2,
        ];
        $expectedFlagPartCounts = [
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 5,
            'whitespace' => 2,
        ];
        $expectedSegmentNames = [
            'HERO Image.PNG',
            'META-INF',
            'Objects',
            'Pictures',
            'Space Name.txt',
            $nonAsciiSegment,
            'media%20encoded.xml',
        ];
        $expectedUppercaseParts = [
            'META-INF/manifest.xml',
            $nonAsciiPart,
            $heroPart,
            $encodedPart,
            $spacePart,
        ];

        foreach ([
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
            'document provenance' => $documentProvenance,
        ] as $label => $handoff) {
            $t->same(7, $handoff['packagePathSegmentNameCharacterReviewSegmentCount'], "{$label} segment count");
            $t->same(8, $handoff['packagePathSegmentNameCharacterReviewOccurrenceCount'], "{$label} occurrence count");
            $t->same(5, $handoff['packagePathSegmentNameCharacterReviewPartCount'], "{$label} part count");
            $t->same(6, $handoff['packagePathSegmentNameUppercaseOccurrenceCount'], "{$label} uppercase occurrences");
            $t->same(2, $handoff['packagePathSegmentNameWhitespaceOccurrenceCount'], "{$label} whitespace occurrences");
            $t->same(1, $handoff['packagePathSegmentNamePercentEncodedOctetOccurrenceCount'], "{$label} encoded-octet occurrences");
            $t->same(1, $handoff['packagePathSegmentNameNonAsciiOccurrenceCount'], "{$label} non-ascii occurrences");
            $t->same(5, $handoff['packagePathSegmentNameUppercasePartCount'], "{$label} uppercase parts");
            $t->same(2, $handoff['packagePathSegmentNameWhitespacePartCount'], "{$label} whitespace parts");
            $t->same(1, $handoff['packagePathSegmentNamePercentEncodedOctetPartCount'], "{$label} encoded-octet parts");
            $t->same(1, $handoff['packagePathSegmentNameNonAsciiPartCount'], "{$label} non-ascii parts");
            $t->same($expectedFlagOccurrenceCounts, $handoff['packagePathSegmentNameCharacterFlagOccurrenceCounts'], "{$label} flag occurrence counts");
            $t->same($expectedFlagPartCounts, $handoff['packagePathSegmentNameCharacterFlagPartCounts'], "{$label} flag part counts");
            $t->same($expectedSegmentNames, $handoff['packagePathSegmentNameCharacterReviewSegmentNames'], "{$label} segment names");
            $t->same([
                'HERO Image.PNG',
                'META-INF',
                'Objects',
                'Pictures',
                'Space Name.txt',
            ], $handoff['packagePathSegmentNameCharacterFlagSegments']['uppercase'], "{$label} uppercase segments");
            $t->same([
                'HERO Image.PNG',
                'Space Name.txt',
            ], $handoff['packagePathSegmentNameCharacterFlagSegments']['whitespace'], "{$label} whitespace segments");
            $t->same(['media%20encoded.xml'], $handoff['packagePathSegmentNameCharacterFlagSegments']['percent-encoded-octet'], "{$label} encoded-octet segments");
            $t->same([$nonAsciiSegment], $handoff['packagePathSegmentNameCharacterFlagSegments']['non-ascii'], "{$label} non-ascii segments");
            $t->same($expectedUppercaseParts, $handoff['packagePathSegmentNameCharacterFlagPartNames']['uppercase'], "{$label} uppercase parts");
            $t->same([$heroPart, $spacePart], $handoff['packagePathSegmentNameCharacterFlagPartNames']['whitespace'], "{$label} whitespace parts");
            $t->same([$encodedPart], $handoff['packagePathSegmentNameCharacterFlagPartNames']['percent-encoded-octet'], "{$label} encoded-octet parts");
            $t->same([$nonAsciiPart], $handoff['packagePathSegmentNameCharacterFlagPartNames']['non-ascii'], "{$label} non-ascii parts");

            $segments = $indexBy($handoff['packagePathSegmentNameCharacterReviewSegments'], 'segment');
            $pictures = $segments['Pictures'];
            $t->same(2, $pictures['occurrenceCount'], "{$label} Pictures occurrences");
            $t->same(2, $pictures['partCount'], "{$label} Pictures part count");
            $t->same(['uppercase'], $pictures['flags'], "{$label} Pictures flags");
            $t->same(['uppercase' => 2], $pictures['flagOccurrenceCounts'], "{$label} Pictures flag occurrences");
            $t->same(['uppercase' => 2], $pictures['flagPartCounts'], "{$label} Pictures flag parts");
            $t->same([0 => 2], $pictures['pathSegmentIndexCounts'], "{$label} Pictures path index");
            $t->same(['first' => 2], $pictures['pathSegmentPositionCounts'], "{$label} Pictures path position");
            $t->same([2 => 2], $pictures['pathDepthCounts'], "{$label} Pictures depth");
            $t->same(['Pictures' => 2], $pictures['topLevelSegmentCounts'], "{$label} Pictures top level");
            $t->same(['Pictures/' => 2], $pictures['directoryCounts'], "{$label} Pictures directories");
            $t->same(['HERO Image.PNG' => 1, 'media%20encoded.xml' => 1], $pictures['baseNameCounts'], "{$label} Pictures base names");
            $t->same(['png' => 1, 'xml' => 1], $pictures['partExtensionCounts'], "{$label} Pictures extensions");
            $t->same([$heroPart, $encodedPart], $pictures['partNames'], "{$label} Pictures parts");
            $t->same($heroPart, $pictures['largestPart']['partName'], "{$label} Pictures largest part");
            $t->same('odf-package-path-segment-name-character-metadata-only', $pictures['reviewPolicy'], "{$label} Pictures policy");

            $hero = $segments['HERO Image.PNG'];
            $t->same(['uppercase', 'whitespace'], $hero['flags'], "{$label} hero flags");
            $t->same([1 => 1], $hero['pathSegmentIndexCounts'], "{$label} hero path index");
            $t->same(['last' => 1], $hero['pathSegmentPositionCounts'], "{$label} hero path position");

            $nonAscii = $segments[$nonAsciiSegment];
            $t->same(['non-ascii'], $nonAscii['flags'], "{$label} non-ascii flags");
            $t->same([$nonAsciiPart], $nonAscii['partNames'], "{$label} non-ascii parts");
            $t->same(['middle' => 1], $nonAscii['pathSegmentPositionCounts'], "{$label} non-ascii path position");
        }

        $compactIdentityEntries = $indexBy($compactIdentity['packageEntries'], 'path');
        $richIdentityEntries = $indexBy($richIdentity['packageEntries'], 'part');
        foreach ([
            'compact inventory' => $compactInventory['parts'][$heroPart],
            'compact identity' => $compactIdentityEntries[$heroPart],
            'rich provenance' => $richProvenance['parts'][$heroPart],
            'rich identity' => $richIdentityEntries[$heroPart],
        ] as $label => $part) {
            $t->same(['uppercase', 'whitespace'], $part['packagePathSegmentNameCharacterFlags'], "{$label} hero part flags");
            $t->same(true, $part['packagePathSegmentNameHasUppercase'], "{$label} hero uppercase");
            $t->same(true, $part['packagePathSegmentNameHasWhitespace'], "{$label} hero whitespace");
            $t->same(false, $part['packagePathSegmentNameHasPercentEncodedOctet'], "{$label} hero encoded-octet");
            $t->same(false, $part['packagePathSegmentNameHasNonAscii'], "{$label} hero non-ascii");
            $t->same(2, count($part['packagePathSegmentNameCharacterReviews']), "{$label} hero review count");
        }
    },
];
