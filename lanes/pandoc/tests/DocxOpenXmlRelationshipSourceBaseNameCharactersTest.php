<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source base name character flags for package review' => static function (TestRunner $t): void {
        $nonAsciiBaseName = "caf\xC3\xA9.xml";
        $nonAsciiSource = 'word/plain/' . $nonAsciiBaseName;
        $nonAsciiRelationshipPart = 'word/plain/_rels/' . $nonAsciiBaseName . '.rels';
        $payloadSource = 'word/plain/Payload.bin';
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="bin" ContentType="application/octet-stream; profile=&quot;relationship-source-basename&quot;"/>
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
            'word/plain/_rels/ReviewSource.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperBaseName" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/upper.png"/>
</Relationships>
XML,
            'word/plain/_rels/Payload.bin.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rParameterizedUpperBaseName" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/payload.png"/>
</Relationships>
XML,
            'word/plain/_rels/MissingSource.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingUpperBaseName" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
</Relationships>
XML,
            'word/plain/_rels/review source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rWhitespaceBaseName" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/whitespace.png"/>
</Relationships>
XML,
            'word/plain/_rels/literal%20source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLiteralPercentBaseName" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/percent.png"/>
</Relationships>
XML,
            $nonAsciiRelationshipPart => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rNonAsciiBaseName" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/non-ascii.png"/>
</Relationships>
XML,
            'word/UpperDir/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDirectoryOnly" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/directory-only.png"/>
</Relationships>
XML,
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship source base name review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/plain/ReviewSource.xml' => '<review/>',
            $payloadSource => str_repeat('B', 41),
            'word/plain/review source.xml' => '<review/>',
            'word/plain/literal%20source.xml' => '<review/>',
            $nonAsciiSource => '<review/>',
            'word/UpperDir/source.xml' => '<directory-only/>',
            'word/media/document.png' => 'document image bytes',
            'word/plain/media/upper.png' => 'upper source image bytes',
            'word/plain/media/payload.png' => 'payload source image bytes',
            'word/plain/media/missing.png' => 'missing source image bytes',
            'word/plain/media/whitespace.png' => 'whitespace source image bytes',
            'word/plain/media/percent.png' => 'percent source image bytes',
            'word/plain/media/non-ascii.png' => 'non ascii source image bytes',
            'word/UpperDir/media/directory-only.png' => 'directory-only image bytes',
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $byBaseName = [];
        $allReviewedSourceParts = [];
        foreach ($summary['relationshipSourceBaseNameCharacterReviewBaseNames'] as $baseName) {
            $byBaseName[$baseName['sourceBaseName']] = $baseName;
            foreach ($baseName['sourceParts'] as $sourcePart) {
                $allReviewedSourceParts[] = $sourcePart;
            }
        }
        sort($allReviewedSourceParts, SORT_STRING);

        $expectedBaseNameNames = [
            'MissingSource.xml',
            'Payload.bin',
            'ReviewSource.xml',
            $nonAsciiBaseName,
            'literal%20source.xml',
            'review source.xml',
        ];
        sort($expectedBaseNameNames, SORT_STRING);

        $t->same(6, $summary['relationshipSourceBaseNameCharacterReviewBaseNameCount']);
        $t->same(6, $summary['relationshipSourceBaseNameCharacterReviewSourceCount']);
        $t->same(6, $summary['relationshipSourceBaseNameCharacterReviewRelationshipCount']);
        $t->same(6, $summary['relationshipSourceBaseNameCharacterReviewRelationshipRecordCount']);
        $t->same(6, $summary['relationshipSourceBaseNameCharacterReviewSourcePartCount']);
        $t->same(3, $summary['relationshipSourceBaseNameUppercaseSourceCount']);
        $t->same(1, $summary['relationshipSourceBaseNameWhitespaceSourceCount']);
        $t->same(1, $summary['relationshipSourceBaseNamePercentEncodedOctetSourceCount']);
        $t->same(1, $summary['relationshipSourceBaseNameNonAsciiSourceCount']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 3,
            'whitespace' => 1,
        ], $summary['relationshipSourceBaseNameCharacterFlagSourceCounts']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 3,
            'whitespace' => 1,
        ], $summary['relationshipSourceBaseNameCharacterFlagRelationshipCounts']);
        $t->same($expectedBaseNameNames, $summary['relationshipSourceBaseNameCharacterReviewBaseNameNames']);
        $t->same(['MissingSource.xml', 'Payload.bin', 'ReviewSource.xml'], $summary['relationshipSourceBaseNameCharacterFlagBaseNames']['uppercase']);
        $t->same(['review source.xml'], $summary['relationshipSourceBaseNameCharacterFlagBaseNames']['whitespace']);
        $t->same(['literal%20source.xml'], $summary['relationshipSourceBaseNameCharacterFlagBaseNames']['percent-encoded-octet']);
        $t->same([$nonAsciiBaseName], $summary['relationshipSourceBaseNameCharacterFlagBaseNames']['non-ascii']);
        $t->same([
            'word/plain/MissingSource.xml',
            $payloadSource,
            'word/plain/ReviewSource.xml',
        ], $summary['relationshipSourceBaseNameCharacterFlagSourceParts']['uppercase']);
        $t->same(['word/plain/review source.xml'], $summary['relationshipSourceBaseNameCharacterFlagSourceParts']['whitespace']);
        $t->same(['word/plain/literal%20source.xml'], $summary['relationshipSourceBaseNameCharacterFlagSourceParts']['percent-encoded-octet']);
        $t->same([$nonAsciiSource], $summary['relationshipSourceBaseNameCharacterFlagSourceParts']['non-ascii']);
        $t->same($expectedBaseNameNames, array_keys($byBaseName));
        $t->same(false, in_array('word/UpperDir/source.xml', $allReviewedSourceParts, true));

        $payload = $byBaseName['Payload.bin'];
        $t->same('Payload.bin', $payload['sourceBaseName']);
        $t->same('payload.bin', $payload['caseFoldBaseName']);
        $t->same(1, $payload['sourceCount']);
        $t->same(1, $payload['sourcePartCount']);
        $t->same(1, $payload['relationshipCount']);
        $t->same(1, $payload['relationshipRecordCount']);
        $t->same(1, $payload['existingSourceCount']);
        $t->same(0, $payload['nonExistingSourceCount']);
        $t->same(0, $payload['missingContentTypeSourceCount']);
        $t->same(1, $payload['parameterizedSourceCount']);
        $t->same(41, $payload['existingSourceByteLength']);
        $t->same(['uppercase'], $payload['flags']);
        $t->same(['uppercase' => 1], $payload['flagSourceCounts']);
        $t->same(['uppercase' => 1], $payload['flagRelationshipCounts']);
        $t->same(['word/plain' => 1], $payload['sourceDirectoryCounts']);
        $t->same(['bin' => 1], $payload['sourcePartExtensionCounts']);
        $t->same(['3' => 1], $payload['sourcePathDepthCounts']);
        $t->same(['2' => 1], $payload['sourceDirectoryDepthCounts']);
        $t->same(['package-part' => 1], $payload['relationshipSourceKindCounts']);
        $t->same(['application/octet-stream' => 1], $payload['sourceContentTypeBaseCounts']);
        $t->same(['default' => 1], $payload['sourceContentTypeSourceCounts']);
        $t->same(['package-part' => 1], $payload['sourceRoleCounts']);
        $t->same([$payloadSource], $payload['sourceParts']);
        $t->same([$payloadSource], $payload['existingSourceParts']);
        $t->same([], $payload['nonExistingSourceParts']);
        $t->same(['word/plain/_rels/Payload.bin.rels'], $payload['relationshipParts']);
        $t->same(['application/octet-stream; profile="relationship-source-basename"'], $payload['contentTypes']);
        $t->same('relationship-source-base-name-character-metadata-only', $payload['reviewPolicy']);
        $t->same($payloadSource, $payload['largestExistingSourcePart']['sourcePart']);
        $t->same('word/plain', $payload['largestExistingSourcePart']['sourceDirectory']);
        $t->same(2, $payload['largestExistingSourcePart']['sourceDirectoryDepth']);
        $t->same(3, $payload['largestExistingSourcePart']['sourcePathDepth']);
        $t->same('Payload.bin', $payload['largestExistingSourcePart']['sourceBaseName']);
        $t->same('payload.bin', $payload['largestExistingSourcePart']['caseFoldBaseName']);
        $t->same('bin', $payload['largestExistingSourcePart']['sourcePartExtension']);
        $t->same(41, $payload['largestExistingSourcePart']['sourceBytes']);
        $t->same(hash('sha256', $parts[$payloadSource]), $payload['largestExistingSourcePart']['sourceSha256']);
        $t->same('application/octet-stream', $payload['largestExistingSourcePart']['sourceContentTypeBase']);
        $t->same('default', $payload['largestExistingSourcePart']['sourceContentTypeSource']);
        $t->same(true, $payload['largestExistingSourcePart']['sourceContentTypeHasParameters']);
        $t->same(1, $payload['largestExistingSourcePart']['sourceContentTypeParameterCount']);
        $t->same(['package-part'], $payload['largestExistingSourcePart']['sourceRoles']);
        $t->same(1, $payload['largestExistingSourcePart']['relationshipCount']);
        $t->same(1, $payload['largestExistingSourcePart']['relationshipRecordCount']);

        $missing = $byBaseName['MissingSource.xml'];
        $t->same(1, $missing['sourceCount']);
        $t->same(0, $missing['existingSourceCount']);
        $t->same(1, $missing['nonExistingSourceCount']);
        $t->same(1, $missing['missingContentTypeSourceCount']);
        $t->same(['missing-source' => 1], $missing['relationshipSourceKindCounts']);
        $t->same(['(missing)' => 1], $missing['sourceContentTypeBaseCounts']);
        $t->same(['missing' => 1], $missing['sourceContentTypeSourceCounts']);
        $t->same(['word/plain/MissingSource.xml'], $missing['sourceParts']);
        $t->same(['word/plain/MissingSource.xml'], $missing['nonExistingSourceParts']);
        $t->same(null, $missing['largestExistingSourcePart']);

        $whitespace = $byBaseName['review source.xml'];
        $t->same(['whitespace'], $whitespace['flags']);
        $t->same(['word/plain/review source.xml'], $whitespace['sourceParts']);

        $percent = $byBaseName['literal%20source.xml'];
        $t->same(['percent-encoded-octet'], $percent['flags']);
        $t->same(['word/plain/literal%20source.xml'], $percent['sourceParts']);

        $nonAscii = $byBaseName[$nonAsciiBaseName];
        $t->same(['non-ascii'], $nonAscii['flags']);
        $t->same([$nonAsciiSource], $nonAscii['sourceParts']);
        $t->same([$nonAsciiRelationshipPart], $nonAscii['relationshipParts']);
    },
];
