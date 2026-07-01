<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target path segment name character flags' => static function (TestRunner $t): void {
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $packageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';
        $nonAsciiSegment = "caf\xC3\xA9";
        $nonAsciiPart = 'word/' . $nonAsciiSegment . '/review.xml';
        $percentPart = 'word/media%20encoded/review.xml';
        $missingPart = 'word/MissingDir/missing.png';
        $payloadPart = 'word/UpperDir/payload.bin';
        $upperReviewPart = 'word/UpperDir/review.png';
        $parts = docx_relationship_target_path_segment_name_character_fixture_parts(
            $nonAsciiPart,
            $payloadPart,
            $percentPart,
            $upperReviewPart,
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $relationships = $package['relationshipParts']['word/_rels/document.xml.rels']['relationships'];
        $bySegment = [];
        foreach ($summary['relationshipTargetPathSegmentNameCharacterReviewSegments'] as $segment) {
            $bySegment[$segment['segment']] = $segment;
        }

        $t->same(6, $summary['relationshipTargetPathSegmentNameCharacterReviewSegmentCount']);
        $t->same(7, $summary['relationshipTargetPathSegmentNameCharacterReviewOccurrenceCount']);
        $t->same(7, $summary['relationshipTargetPathSegmentNameCharacterReviewRelationshipCount']);
        $t->same(7, $summary['relationshipTargetPathSegmentNameCharacterReviewTargetPartCount']);
        $t->same(4, $summary['relationshipTargetPathSegmentNameUppercaseOccurrenceCount']);
        $t->same(1, $summary['relationshipTargetPathSegmentNameWhitespaceOccurrenceCount']);
        $t->same(1, $summary['relationshipTargetPathSegmentNamePercentEncodedOctetOccurrenceCount']);
        $t->same(1, $summary['relationshipTargetPathSegmentNameNonAsciiOccurrenceCount']);
        $t->same(4, $summary['relationshipTargetPathSegmentNameUppercaseRelationshipCount']);
        $t->same(1, $summary['relationshipTargetPathSegmentNameWhitespaceRelationshipCount']);
        $t->same(1, $summary['relationshipTargetPathSegmentNamePercentEncodedOctetRelationshipCount']);
        $t->same(1, $summary['relationshipTargetPathSegmentNameNonAsciiRelationshipCount']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 4,
            'whitespace' => 1,
        ], $summary['relationshipTargetPathSegmentNameCharacterFlagOccurrenceCounts']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 4,
            'whitespace' => 1,
        ], $summary['relationshipTargetPathSegmentNameCharacterFlagRelationshipCounts']);
        $t->same([
            'MissingDir',
            'Review.PNG',
            'UpperDir',
            $nonAsciiSegment,
            'media draft',
            'media%20encoded',
        ], $summary['relationshipTargetPathSegmentNameCharacterReviewSegmentNames']);
        $t->same(['MissingDir', 'Review.PNG', 'UpperDir'], $summary['relationshipTargetPathSegmentNameCharacterFlagSegments']['uppercase']);
        $t->same(['media draft'], $summary['relationshipTargetPathSegmentNameCharacterFlagSegments']['whitespace']);
        $t->same(['media%20encoded'], $summary['relationshipTargetPathSegmentNameCharacterFlagSegments']['percent-encoded-octet']);
        $t->same([$nonAsciiSegment], $summary['relationshipTargetPathSegmentNameCharacterFlagSegments']['non-ascii']);
        $t->same([
            $missingPart,
            $payloadPart,
            $upperReviewPart,
            'word/media/Review.PNG',
        ], $summary['relationshipTargetPathSegmentNameCharacterFlagTargetParts']['uppercase']);
        $t->same(['word/media draft/review.png'], $summary['relationshipTargetPathSegmentNameCharacterFlagTargetParts']['whitespace']);
        $t->same([$percentPart], $summary['relationshipTargetPathSegmentNameCharacterFlagTargetParts']['percent-encoded-octet']);
        $t->same([$nonAsciiPart], $summary['relationshipTargetPathSegmentNameCharacterFlagTargetParts']['non-ascii']);

        $t->same($percentPart, $relationships['rLiteralPercent']['targetPart']);
        $t->same($nonAsciiPart, $relationships['rNonAscii']['targetPart']);
        $t->same(false, isset($bySegment['UPPER.png']));

        $upperDir = $bySegment['UpperDir'];
        $upperDirTargetParts = [$payloadPart, $upperReviewPart];
        sort($upperDirTargetParts, SORT_STRING);
        $t->same('UpperDir', $upperDir['segment']);
        $t->same('upperdir', $upperDir['caseFoldSegment']);
        $t->same(2, $upperDir['occurrenceCount']);
        $t->same(2, $upperDir['relationshipCount']);
        $t->same(2, $upperDir['targetPartCount']);
        $t->same(2, $upperDir['existingTargetCount']);
        $t->same(0, $upperDir['missingTargetCount']);
        $t->same(0, $upperDir['missingContentTypeTargetCount']);
        $t->same(1, $upperDir['parameterizedTargetCount']);
        $t->same(strlen($parts[$payloadPart]) + strlen($parts[$upperReviewPart]), $upperDir['existingTargetByteLength']);
        $t->same(['uppercase'], $upperDir['flags']);
        $t->same(['uppercase' => 2], $upperDir['flagOccurrenceCounts']);
        $t->same(['uppercase' => 2], $upperDir['flagRelationshipCounts']);
        $t->same([1 => 2], $upperDir['pathSegmentIndexCounts']);
        $t->same(['middle' => 2], $upperDir['pathSegmentPositionCounts']);
        $t->same([3 => 2], $upperDir['targetPathDepthCounts']);
        $t->same(['word' => 2], $upperDir['targetTopLevelSegmentCounts']);
        $t->same(['word/UpperDir' => 2], $upperDir['targetDirectoryCounts']);
        $t->same(['payload.bin' => 1, 'review.png' => 1], $upperDir['targetBaseNameCounts']);
        $t->same(['bin' => 1, 'png' => 1], $upperDir['targetPartExtensionCounts']);
        $t->same(['application/octet-stream' => 1, 'image/png' => 1], $upperDir['contentTypeBaseCounts']);
        $t->same(['default' => 2], $upperDir['contentTypeSourceCounts']);
        $t->same([$imageRel => 1, $packageRel => 1], $upperDir['relationshipTypeCounts']);
        $t->same([
            'document-relationship-target' => 2,
            'embedded-package' => 1,
        ], $upperDir['roleCounts']);
        $t->same(['word/document.xml'], $upperDir['sourceParts']);
        $t->same(['word/_rels/document.xml.rels'], $upperDir['relationshipParts']);
        $t->same(['rUpperDir', 'rUpperPayload'], $upperDir['relationshipIds']);
        $t->same([$imageRel, $packageRel], $upperDir['relationshipTypes']);
        $t->same(['application/octet-stream; profile="relationship-target-segment"', 'image/png'], $upperDir['contentTypes']);
        $t->same($upperDirTargetParts, $upperDir['targetParts']);
        $t->same($upperDirTargetParts, $upperDir['existingTargetParts']);
        $t->same([], $upperDir['missingTargetParts']);
        $t->same('relationship-target-path-segment-name-character-metadata-only', $upperDir['reviewPolicy']);
        $t->same($payloadPart, $upperDir['largestExistingTargetPart']['targetPart']);
        $t->same('UpperDir', $upperDir['largestExistingTargetPart']['segment']);
        $t->same(1, $upperDir['largestExistingTargetPart']['pathSegmentIndex']);
        $t->same('middle', $upperDir['largestExistingTargetPart']['pathSegmentPosition']);
        $t->same(3, $upperDir['largestExistingTargetPart']['targetPathDepth']);
        $t->same(['word', 'UpperDir', 'payload.bin'], $upperDir['largestExistingTargetPart']['targetPathSegments']);
        $t->same('bin', $upperDir['largestExistingTargetPart']['targetPartExtension']);
        $t->same(41, $upperDir['largestExistingTargetPart']['targetBytes']);
        $t->same(hash('sha256', $parts[$payloadPart]), $upperDir['largestExistingTargetPart']['targetSha256']);
        $t->same('application/octet-stream', $upperDir['largestExistingTargetPart']['targetContentTypeBase']);
        $t->same(true, $upperDir['largestExistingTargetPart']['targetContentTypeHasParameters']);
        $t->same(1, $upperDir['largestExistingTargetPart']['targetContentTypeParameterCount']);

        $missing = $bySegment['MissingDir'];
        $t->same(1, $missing['relationshipCount']);
        $t->same(0, $missing['existingTargetCount']);
        $t->same(1, $missing['missingTargetCount']);
        $t->same([$missingPart], $missing['targetParts']);
        $t->same([$missingPart], $missing['missingTargetParts']);
        $t->same(null, $missing['largestExistingTargetPart']);

        $basenameOnly = $bySegment['Review.PNG'];
        $t->same([2 => 1], $basenameOnly['pathSegmentIndexCounts']);
        $t->same(['last' => 1], $basenameOnly['pathSegmentPositionCounts']);
        $t->same(['word/media' => 1], $basenameOnly['targetDirectoryCounts']);
        $t->same(['Review.PNG' => 1], $basenameOnly['targetBaseNameCounts']);

        $nonAscii = $bySegment[$nonAsciiSegment];
        $t->same(['non-ascii'], $nonAscii['flags']);
        $t->same([$nonAsciiPart], $nonAscii['targetParts']);
        $t->same(['middle' => 1], $nonAscii['pathSegmentPositionCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_path_segment_name_character_fixture_parts(
    string $nonAsciiPart,
    string $payloadPart,
    string $percentPart,
    string $upperReviewPart
): array {
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="bin" ContentType="application/octet-stream; profile=&quot;relationship-target-segment&quot;"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperDir" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="UpperDir/review.png"/>
  <Relationship Id="rUpperPayload" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="UpperDir/payload.bin"/>
  <Relationship Id="rBaseUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Review.PNG"/>
  <Relationship Id="rWhitespace" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media draft/review.png"/>
  <Relationship Id="rLiteralPercent" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="media%2520encoded/review.xml"/>
  <Relationship Id="rNonAscii" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="caf%C3%A9/review.xml"/>
  <Relationship Id="rMissingUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="MissingDir/missing.png"/>
  <Relationship Id="rRemoteUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/UPPER.png" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target path segment name review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        $upperReviewPart => 'upper directory image',
        $payloadPart => str_repeat('B', 41),
        'word/media/Review.PNG' => 'base name segment image',
        'word/media draft/review.png' => 'whitespace segment image',
        $percentPart => '<encoded-segment/>',
        $nonAsciiPart => '<non-ascii-segment/>',
    ];
}
