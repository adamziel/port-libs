<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target part name character flags' => static function (TestRunner $t): void {
        $nonAsciiPart = "word/media/caf\xC3\xA9.png";
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
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
  <Relationship Id="rUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Review.PNG"/>
  <Relationship Id="rWhitespace" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review draft.png"/>
  <Relationship Id="rLiteralPercent" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/literal%2520encoded.png"/>
  <Relationship Id="rNonAscii" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/caf%C3%A9.png"/>
  <Relationship Id="rRemoteUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/UPPER.png" TargetMode="External"/>
</Relationships>
XML,
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target name review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/media/Review.PNG' => 'upper image bytes',
            'word/media/review draft.png' => 'whitespace image bytes',
            'word/media/literal%20encoded.png' => 'literal percent image bytes',
            $nonAsciiPart => 'non ascii image bytes',
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $relationships = $package['relationshipParts']['word/_rels/document.xml.rels']['relationships'];
        $byRelationshipId = [];
        foreach ($summary['relationshipTargetNameCharacterReviewTargets'] as $target) {
            $byRelationshipId[$target['relationshipId']] = $target;
        }

        $expectedTargetParts = [
            'word/media/Review.PNG',
            $nonAsciiPart,
            'word/media/literal%20encoded.png',
            'word/media/review draft.png',
        ];

        $t->same(4, $summary['relationshipTargetNameCharacterReviewRelationshipCount']);
        $t->same(4, $summary['relationshipTargetNameCharacterReviewTargetPartCount']);
        $t->same(1, $summary['relationshipTargetNameUppercaseRelationshipCount']);
        $t->same(1, $summary['relationshipTargetNameWhitespaceRelationshipCount']);
        $t->same(1, $summary['relationshipTargetNamePercentEncodedOctetRelationshipCount']);
        $t->same(1, $summary['relationshipTargetNameNonAsciiRelationshipCount']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 1,
            'whitespace' => 1,
        ], $summary['relationshipTargetNameCharacterFlagRelationshipCounts']);
        $t->same(['word/media/Review.PNG'], $summary['relationshipTargetNameCharacterFlagTargetParts']['uppercase']);
        $t->same(['word/media/review draft.png'], $summary['relationshipTargetNameCharacterFlagTargetParts']['whitespace']);
        $t->same(['word/media/literal%20encoded.png'], $summary['relationshipTargetNameCharacterFlagTargetParts']['percent-encoded-octet']);
        $t->same([$nonAsciiPart], $summary['relationshipTargetNameCharacterFlagTargetParts']['non-ascii']);
        $t->same($expectedTargetParts, $summary['relationshipTargetNameCharacterReviewTargetParts']);

        $t->same('word/media/literal%20encoded.png', $relationships['rLiteralPercent']['targetPart']);
        $t->same($nonAsciiPart, $relationships['rNonAscii']['targetPart']);

        $upper = $byRelationshipId['rUpper'];
        $t->same('word/media/Review.PNG', $upper['targetPart']);
        $t->same('word/media', $upper['targetDirectory']);
        $t->same('Review.PNG', $upper['targetBaseName']);
        $t->same(['word', 'media', 'Review.PNG'], $upper['targetPathSegments']);
        $t->same('png', $upper['targetPartExtension']);
        $t->same(true, $upper['targetExists']);
        $t->same('image/png', $upper['targetContentTypeBase']);
        $t->same('default', $upper['targetContentTypeSource']);
        $t->same(['document-relationship-target'], $upper['targetRoles']);
        $t->same('word/document.xml', $upper['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $upper['relationshipsPart']);
        $t->same(['uppercase'], $upper['flags']);
        $t->same('relationship-target-name-character-metadata-only', $upper['reviewPolicy']);

        $t->same(['whitespace'], $byRelationshipId['rWhitespace']['flags']);
        $t->same(['percent-encoded-octet'], $byRelationshipId['rLiteralPercent']['flags']);
        $t->same(['non-ascii'], $byRelationshipId['rNonAscii']['flags']);
        $t->same(false, isset($byRelationshipId['rRemoteUpper']));
    },
];
