<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source path segment name character flags' => static function (TestRunner $t): void {
        $nonAsciiSegment = "caf\xC3\xA9";
        $nonAsciiSource = 'word/' . $nonAsciiSegment . '/source.xml';
        $nonAsciiRelationshipPart = 'word/' . $nonAsciiSegment . '/_rels/source.xml.rels';
        $payloadSource = 'word/UpperDir/payload.bin';
        $percentSource = 'word/media%20encoded/source.xml';
        $upperSource = 'word/UpperDir/source.xml';
        $parts = docx_relationship_source_path_segment_name_character_fixture_parts(
            $nonAsciiRelationshipPart,
            $nonAsciiSource,
            $payloadSource,
            $percentSource,
            $upperSource,
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $bySegment = [];
        foreach ($summary['relationshipSourcePathSegmentNameCharacterReviewSegments'] as $segment) {
            $bySegment[$segment['segment']] = $segment;
        }

        $t->same(6, $summary['relationshipSourcePathSegmentNameCharacterReviewSegmentCount']);
        $t->same(7, $summary['relationshipSourcePathSegmentNameCharacterReviewOccurrenceCount']);
        $t->same(7, $summary['relationshipSourcePathSegmentNameCharacterReviewSourceCount']);
        $t->same(7, $summary['relationshipSourcePathSegmentNameCharacterReviewRelationshipCount']);
        $t->same(7, $summary['relationshipSourcePathSegmentNameCharacterReviewRelationshipRecordCount']);
        $t->same(7, $summary['relationshipSourcePathSegmentNameCharacterReviewSourcePartCount']);
        $t->same(4, $summary['relationshipSourcePathSegmentNameUppercaseOccurrenceCount']);
        $t->same(1, $summary['relationshipSourcePathSegmentNameWhitespaceOccurrenceCount']);
        $t->same(1, $summary['relationshipSourcePathSegmentNamePercentEncodedOctetOccurrenceCount']);
        $t->same(1, $summary['relationshipSourcePathSegmentNameNonAsciiOccurrenceCount']);
        $t->same(4, $summary['relationshipSourcePathSegmentNameUppercaseSourceCount']);
        $t->same(1, $summary['relationshipSourcePathSegmentNameWhitespaceSourceCount']);
        $t->same(1, $summary['relationshipSourcePathSegmentNamePercentEncodedOctetSourceCount']);
        $t->same(1, $summary['relationshipSourcePathSegmentNameNonAsciiSourceCount']);
        $t->same(4, $summary['relationshipSourcePathSegmentNameUppercaseRelationshipCount']);
        $t->same(1, $summary['relationshipSourcePathSegmentNameWhitespaceRelationshipCount']);
        $t->same(1, $summary['relationshipSourcePathSegmentNamePercentEncodedOctetRelationshipCount']);
        $t->same(1, $summary['relationshipSourcePathSegmentNameNonAsciiRelationshipCount']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 4,
            'whitespace' => 1,
        ], $summary['relationshipSourcePathSegmentNameCharacterFlagOccurrenceCounts']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 4,
            'whitespace' => 1,
        ], $summary['relationshipSourcePathSegmentNameCharacterFlagSourceCounts']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 4,
            'whitespace' => 1,
        ], $summary['relationshipSourcePathSegmentNameCharacterFlagRelationshipCounts']);
        $t->same([
            'MissingDir',
            'Source.XML',
            'UpperDir',
            $nonAsciiSegment,
            'media draft',
            'media%20encoded',
        ], $summary['relationshipSourcePathSegmentNameCharacterReviewSegmentNames']);
        $t->same(['MissingDir', 'Source.XML', 'UpperDir'], $summary['relationshipSourcePathSegmentNameCharacterFlagSegments']['uppercase']);
        $t->same(['media draft'], $summary['relationshipSourcePathSegmentNameCharacterFlagSegments']['whitespace']);
        $t->same(['media%20encoded'], $summary['relationshipSourcePathSegmentNameCharacterFlagSegments']['percent-encoded-octet']);
        $t->same([$nonAsciiSegment], $summary['relationshipSourcePathSegmentNameCharacterFlagSegments']['non-ascii']);
        $t->same([
            'word/MissingDir/missing.xml',
            $payloadSource,
            $upperSource,
            'word/media/Source.XML',
        ], $summary['relationshipSourcePathSegmentNameCharacterFlagSourceParts']['uppercase']);
        $t->same(['word/media draft/source.xml'], $summary['relationshipSourcePathSegmentNameCharacterFlagSourceParts']['whitespace']);
        $t->same([$percentSource], $summary['relationshipSourcePathSegmentNameCharacterFlagSourceParts']['percent-encoded-octet']);
        $t->same([$nonAsciiSource], $summary['relationshipSourcePathSegmentNameCharacterFlagSourceParts']['non-ascii']);
        $t->same(false, isset($bySegment['[Content_Types].xml']));
        $t->same(false, isset($bySegment['document.xml']));

        $upperDir = $bySegment['UpperDir'];
        $t->same('UpperDir', $upperDir['segment']);
        $t->same('upperdir', $upperDir['caseFoldSegment']);
        $t->same(2, $upperDir['occurrenceCount']);
        $t->same(2, $upperDir['sourceCount']);
        $t->same(2, $upperDir['sourcePartCount']);
        $t->same(2, $upperDir['relationshipCount']);
        $t->same(2, $upperDir['relationshipRecordCount']);
        $t->same(2, $upperDir['existingSourceCount']);
        $t->same(0, $upperDir['nonExistingSourceCount']);
        $t->same(0, $upperDir['missingContentTypeSourceCount']);
        $t->same(1, $upperDir['parameterizedSourceCount']);
        $t->same(strlen($parts[$payloadSource]) + strlen($parts[$upperSource]), $upperDir['existingSourceByteLength']);
        $t->same(['uppercase'], $upperDir['flags']);
        $t->same(['uppercase' => 2], $upperDir['flagOccurrenceCounts']);
        $t->same(['uppercase' => 2], $upperDir['flagSourceCounts']);
        $t->same(['uppercase' => 2], $upperDir['flagRelationshipCounts']);
        $t->same([1 => 2], $upperDir['pathSegmentIndexCounts']);
        $t->same(['middle' => 2], $upperDir['pathSegmentPositionCounts']);
        $t->same([3 => 2], $upperDir['sourcePathDepthCounts']);
        $t->same(['word' => 2], $upperDir['sourceTopLevelSegmentCounts']);
        $t->same(['word/UpperDir' => 2], $upperDir['sourceDirectoryCounts']);
        $t->same(['payload.bin' => 1, 'source.xml' => 1], $upperDir['sourceBaseNameCounts']);
        $t->same(['bin' => 1, 'xml' => 1], $upperDir['sourcePartExtensionCounts']);
        $t->same(['application/octet-stream' => 1, 'application/xml' => 1], $upperDir['sourceContentTypeBaseCounts']);
        $t->same(['default' => 2], $upperDir['sourceContentTypeSourceCounts']);
        $t->same(['package-part' => 2], $upperDir['relationshipSourceKindCounts']);
        $t->same(['package-part' => 2], $upperDir['sourceRoleCounts']);
        $t->same([$payloadSource, $upperSource], $upperDir['sourceParts']);
        $t->same([$payloadSource, $upperSource], $upperDir['existingSourceParts']);
        $t->same([], $upperDir['nonExistingSourceParts']);
        $t->same([
            'word/UpperDir/_rels/payload.bin.rels',
            'word/UpperDir/_rels/source.xml.rels',
        ], $upperDir['relationshipParts']);
        $t->same([
            'application/octet-stream; profile="relationship-source-segment"',
            'application/xml',
        ], $upperDir['contentTypes']);
        $t->same('relationship-source-path-segment-name-character-metadata-only', $upperDir['reviewPolicy']);
        $t->same($payloadSource, $upperDir['largestExistingSourcePart']['sourcePart']);
        $t->same('word/UpperDir/_rels/payload.bin.rels', $upperDir['largestExistingSourcePart']['relationshipsPart']);
        $t->same('package-part', $upperDir['largestExistingSourcePart']['relationshipSourceKind']);
        $t->same('UpperDir', $upperDir['largestExistingSourcePart']['segment']);
        $t->same(1, $upperDir['largestExistingSourcePart']['pathSegmentIndex']);
        $t->same('middle', $upperDir['largestExistingSourcePart']['pathSegmentPosition']);
        $t->same(3, $upperDir['largestExistingSourcePart']['sourcePathDepth']);
        $t->same(['word', 'UpperDir', 'payload.bin'], $upperDir['largestExistingSourcePart']['sourcePathSegments']);
        $t->same('bin', $upperDir['largestExistingSourcePart']['sourcePartExtension']);
        $t->same(41, $upperDir['largestExistingSourcePart']['sourceBytes']);
        $t->same(hash('sha256', $parts[$payloadSource]), $upperDir['largestExistingSourcePart']['sourceSha256']);
        $t->same('application/octet-stream', $upperDir['largestExistingSourcePart']['sourceContentTypeBase']);
        $t->same('default', $upperDir['largestExistingSourcePart']['sourceContentTypeSource']);
        $t->same(true, $upperDir['largestExistingSourcePart']['sourceContentTypeHasParameters']);
        $t->same(1, $upperDir['largestExistingSourcePart']['sourceContentTypeParameterCount']);
        $t->same(['package-part'], $upperDir['largestExistingSourcePart']['sourceRoles']);

        $missing = $bySegment['MissingDir'];
        $t->same(1, $missing['sourceCount']);
        $t->same(0, $missing['existingSourceCount']);
        $t->same(1, $missing['nonExistingSourceCount']);
        $t->same(1, $missing['missingContentTypeSourceCount']);
        $t->same(['missing-source' => 1], $missing['relationshipSourceKindCounts']);
        $t->same(['word/MissingDir/missing.xml'], $missing['sourceParts']);
        $t->same(['word/MissingDir/missing.xml'], $missing['nonExistingSourceParts']);
        $t->same(null, $missing['largestExistingSourcePart']);

        $basenameOnly = $bySegment['Source.XML'];
        $t->same([2 => 1], $basenameOnly['pathSegmentIndexCounts']);
        $t->same(['last' => 1], $basenameOnly['pathSegmentPositionCounts']);
        $t->same(['word/media' => 1], $basenameOnly['sourceDirectoryCounts']);
        $t->same(['Source.XML' => 1], $basenameOnly['sourceBaseNameCounts']);

        $nonAscii = $bySegment[$nonAsciiSegment];
        $t->same(['non-ascii'], $nonAscii['flags']);
        $t->same([$nonAsciiSource], $nonAscii['sourceParts']);
        $t->same(['middle' => 1], $nonAscii['pathSegmentPositionCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_source_path_segment_name_character_fixture_parts(
    string $nonAsciiRelationshipPart,
    string $nonAsciiSource,
    string $payloadSource,
    string $percentSource,
    string $upperSource
): array {
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="bin" ContentType="application/octet-stream; profile=&quot;relationship-source-segment&quot;"/>
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
  <Relationship Id="rDocumentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/document.png"/>
</Relationships>
XML,
        'word/UpperDir/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/upper.png"/>
</Relationships>
XML,
        'word/UpperDir/_rels/payload.bin.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperPayload" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/payload.png"/>
</Relationships>
XML,
        'word/MissingDir/_rels/missing.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
</Relationships>
XML,
        'word/media/_rels/Source.XML.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rBaseNameOnly" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Source.PNG"/>
</Relationships>
XML,
        'word/media draft/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rWhitespaceSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="review.png"/>
</Relationships>
XML,
        'word/media%20encoded/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rPercentSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="review.png"/>
</Relationships>
XML,
        $nonAsciiRelationshipPart => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rNonAsciiSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="review.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship source path segment name review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        $upperSource => '<source/>',
        $payloadSource => str_repeat('B', 41),
        'word/media/Source.XML' => '<source/>',
        'word/media draft/source.xml' => '<source/>',
        $percentSource => '<source/>',
        $nonAsciiSource => '<source/>',
        'word/media/document.png' => 'document image bytes',
        'word/UpperDir/media/upper.png' => 'upper source image bytes',
        'word/UpperDir/media/payload.png' => 'payload source image bytes',
        'word/MissingDir/media/missing.png' => 'missing source target bytes',
        'word/media/Source.PNG' => 'basename-only target bytes',
        'word/media draft/review.png' => 'whitespace source target bytes',
        'word/media%20encoded/review.png' => 'percent source target bytes',
        'word/' . "caf\xC3\xA9" . '/review.png' => 'non ascii source target bytes',
    ];
}
