<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package part path segment name character flags for package review' => static function (TestRunner $t): void {
        $nonAsciiSegment = "caf\xC3\xA9";
        $nonAsciiPart = 'word/' . $nonAsciiSegment . '/review.xml';
        $payloadPart = 'word/UpperDir/payload.bin';
        $reviewPart = 'word/UpperDir/review.png';
        $parts = docx_package_part_path_segment_name_character_fixture_parts($nonAsciiPart, $payloadPart, $reviewPart);

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $bySegment = [];
        foreach ($summary['partPathSegmentNameCharacterReviewSegments'] as $segment) {
            $bySegment[$segment['segment']] = $segment;
        }

        $t->same(6, $summary['partPathSegmentNameCharacterReviewSegmentCount']);
        $t->same(7, $summary['partPathSegmentNameCharacterReviewOccurrenceCount']);
        $t->same(7, $summary['partPathSegmentNameCharacterReviewPartCount']);
        $t->same(4, $summary['partPathSegmentNameUppercaseOccurrenceCount']);
        $t->same(1, $summary['partPathSegmentNameWhitespaceOccurrenceCount']);
        $t->same(1, $summary['partPathSegmentNamePercentEncodedOctetOccurrenceCount']);
        $t->same(1, $summary['partPathSegmentNameNonAsciiOccurrenceCount']);
        $t->same(4, $summary['partPathSegmentNameUppercasePartCount']);
        $t->same(1, $summary['partPathSegmentNameWhitespacePartCount']);
        $t->same(1, $summary['partPathSegmentNamePercentEncodedOctetPartCount']);
        $t->same(1, $summary['partPathSegmentNameNonAsciiPartCount']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 4,
            'whitespace' => 1,
        ], $summary['partPathSegmentNameCharacterFlagOccurrenceCounts']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 4,
            'whitespace' => 1,
        ], $summary['partPathSegmentNameCharacterFlagPartCounts']);
        $t->same([
            'Review.PNG',
            'UpperDir',
            '[Content_Types].xml',
            $nonAsciiSegment,
            'media draft',
            'media%20encoded',
        ], $summary['partPathSegmentNameCharacterReviewSegmentNames']);
        $t->same([
            'Review.PNG',
            'UpperDir',
            '[Content_Types].xml',
        ], $summary['partPathSegmentNameCharacterFlagSegments']['uppercase']);
        $t->same(['media draft'], $summary['partPathSegmentNameCharacterFlagSegments']['whitespace']);
        $t->same(['media%20encoded'], $summary['partPathSegmentNameCharacterFlagSegments']['percent-encoded-octet']);
        $t->same([$nonAsciiSegment], $summary['partPathSegmentNameCharacterFlagSegments']['non-ascii']);
        $t->same([
            '[Content_Types].xml',
            $payloadPart,
            $reviewPart,
            'word/media/Review.PNG',
        ], $summary['partPathSegmentNameCharacterFlagPartNames']['uppercase']);
        $t->same(['word/media draft/review.png'], $summary['partPathSegmentNameCharacterFlagPartNames']['whitespace']);
        $t->same(['word/media%20encoded/review.xml'], $summary['partPathSegmentNameCharacterFlagPartNames']['percent-encoded-octet']);
        $t->same([$nonAsciiPart], $summary['partPathSegmentNameCharacterFlagPartNames']['non-ascii']);

        $upperDir = $bySegment['UpperDir'];
        $upperDirPartNames = [$payloadPart, $reviewPart];
        sort($upperDirPartNames, SORT_STRING);
        $t->same('UpperDir', $upperDir['segment']);
        $t->same('upperdir', $upperDir['caseFoldSegment']);
        $t->same(2, $upperDir['occurrenceCount']);
        $t->same(2, $upperDir['partCount']);
        $t->same(array_sum(array_map(static fn (string $partName): int => strlen($parts[$partName]), $upperDirPartNames)), $upperDir['byteLength']);
        $t->same(0, $upperDir['relationshipPartCount']);
        $t->same(0, $upperDir['missingContentTypePartCount']);
        $t->same(1, $upperDir['parameterizedPartCount']);
        $t->same(['uppercase'], $upperDir['flags']);
        $t->same(['uppercase' => 2], $upperDir['flagOccurrenceCounts']);
        $t->same(['uppercase' => 2], $upperDir['flagPartCounts']);
        $t->same([1 => 2], $upperDir['pathSegmentIndexCounts']);
        $t->same(['middle' => 2], $upperDir['pathSegmentPositionCounts']);
        $t->same([3 => 2], $upperDir['pathDepthCounts']);
        $t->same(['word' => 2], $upperDir['topLevelSegmentCounts']);
        $t->same(['word/UpperDir' => 2], $upperDir['directoryCounts']);
        $t->same(['payload.bin' => 1, 'review.png' => 1], $upperDir['baseNameCounts']);
        $t->same(['bin' => 1, 'png' => 1], $upperDir['partExtensionCounts']);
        $t->same(['application/octet-stream' => 1, 'image/png' => 1], $upperDir['contentTypeBaseCounts']);
        $t->same(['default' => 2], $upperDir['contentTypeSourceCounts']);
        $t->same(['package-part' => 2], $upperDir['roleCounts']);
        $t->same($upperDirPartNames, $upperDir['partNames']);
        $t->same('package-part-path-segment-name-character-metadata-only', $upperDir['reviewPolicy']);
        $t->same($payloadPart, $upperDir['largestPart']['partName']);
        $t->same('UpperDir', $upperDir['largestPart']['segment']);
        $t->same(1, $upperDir['largestPart']['pathSegmentIndex']);
        $t->same('middle', $upperDir['largestPart']['pathSegmentPosition']);
        $t->same(3, $upperDir['largestPart']['pathSegmentCount']);
        $t->same('bin', $upperDir['largestPart']['partExtension']);
        $t->same(41, $upperDir['largestPart']['bytes']);
        $t->same(hash('sha256', $parts[$payloadPart]), $upperDir['largestPart']['sha256']);
        $t->same('application/octet-stream', $upperDir['largestPart']['contentTypeBase']);
        $t->same(true, $upperDir['largestPart']['contentTypeHasParameters']);
        $t->same(1, $upperDir['largestPart']['contentTypeParameterCount']);

        $reviewPng = $bySegment['Review.PNG'];
        $t->same(1, $reviewPng['occurrenceCount']);
        $t->same(['uppercase'], $reviewPng['flags']);
        $t->same([2 => 1], $reviewPng['pathSegmentIndexCounts']);
        $t->same(['last' => 1], $reviewPng['pathSegmentPositionCounts']);
        $t->same(['word/media' => 1], $reviewPng['directoryCounts']);
        $t->same(['Review.PNG' => 1], $reviewPng['baseNameCounts']);
        $t->same(['image/png' => 1], $reviewPng['contentTypeBaseCounts']);
        $t->same('last', $reviewPng['largestPart']['pathSegmentPosition']);

        $nonAscii = $bySegment[$nonAsciiSegment];
        $t->same(['non-ascii'], $nonAscii['flags']);
        $t->same([$nonAsciiPart], $nonAscii['partNames']);
        $t->same(['middle' => 1], $nonAscii['pathSegmentPositionCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_package_part_path_segment_name_character_fixture_parts(
    string $nonAsciiPart,
    string $payloadPart,
    string $reviewPart
): array {
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream; profile=&quot;review&quot;"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package part path segment name review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        $reviewPart => 'upper directory image',
        $payloadPart => str_repeat('B', 41),
        'word/media draft/review.png' => 'whitespace segment image',
        'word/media%20encoded/review.xml' => '<encoded-segment/>',
        $nonAsciiPart => '<non-ascii-segment/>',
        'word/media/Review.PNG' => 'base name segment image',
    ];
}
