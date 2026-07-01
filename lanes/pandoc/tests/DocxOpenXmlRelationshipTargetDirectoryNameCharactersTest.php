<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target directory name character flags for package review' => static function (TestRunner $t): void {
        $nonAsciiDirectory = "word/caf\xC3\xA9";
        $nonAsciiTargetPart = $nonAsciiDirectory . '/review.png';
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
  <Relationship Id="rUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Media/review.png"/>
  <Relationship Id="rUpperMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Media/missing.png"/>
  <Relationship Id="rWhitespace" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media draft/review.png"/>
  <Relationship Id="rLiteralPercent" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media%2520encoded/review.png"/>
  <Relationship Id="rNonAscii" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="caf%C3%A9/review.png"/>
  <Relationship Id="rBaseNameOnly" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Review.PNG"/>
  <Relationship Id="rExternalUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/UPPER/PATH.png" TargetMode="External"/>
</Relationships>
XML,
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target directory name review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/Media/review.png' => 'upper image bytes',
            'word/media draft/review.png' => 'whitespace image bytes',
            'word/media%20encoded/review.png' => 'literal percent image bytes',
            $nonAsciiTargetPart => 'non ascii image bytes',
            'word/media/Review.PNG' => 'base name uppercase only bytes',
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $relationships = $package['relationshipParts']['word/_rels/document.xml.rels']['relationships'];
        $byDirectory = [];
        $allReviewedTargetParts = [];
        $allReviewedRelationshipIds = [];
        foreach ($summary['relationshipTargetDirectoryNameCharacterReviewDirectories'] as $directory) {
            $byDirectory[$directory['targetDirectory']] = $directory;
            foreach ($directory['targetParts'] as $targetPart) {
                $allReviewedTargetParts[] = $targetPart;
            }
            foreach ($directory['relationshipIds'] as $relationshipId) {
                $allReviewedRelationshipIds[] = $relationshipId;
            }
        }
        sort($allReviewedTargetParts, SORT_STRING);
        sort($allReviewedRelationshipIds, SORT_STRING);

        $expectedDirectoryNames = [
            'word/Media',
            $nonAsciiDirectory,
            'word/media draft',
            'word/media%20encoded',
        ];
        sort($expectedDirectoryNames, SORT_STRING);

        $t->same(4, $summary['relationshipTargetDirectoryNameCharacterReviewDirectoryCount']);
        $t->same(5, $summary['relationshipTargetDirectoryNameCharacterReviewRelationshipCount']);
        $t->same(5, $summary['relationshipTargetDirectoryNameCharacterReviewTargetPartCount']);
        $t->same(2, $summary['relationshipTargetDirectoryNameUppercaseRelationshipCount']);
        $t->same(1, $summary['relationshipTargetDirectoryNameWhitespaceRelationshipCount']);
        $t->same(1, $summary['relationshipTargetDirectoryNamePercentEncodedOctetRelationshipCount']);
        $t->same(1, $summary['relationshipTargetDirectoryNameNonAsciiRelationshipCount']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 2,
            'whitespace' => 1,
        ], $summary['relationshipTargetDirectoryNameCharacterFlagRelationshipCounts']);
        $t->same($expectedDirectoryNames, $summary['relationshipTargetDirectoryNameCharacterReviewDirectoryNames']);
        $t->same(['word/Media'], $summary['relationshipTargetDirectoryNameCharacterFlagDirectories']['uppercase']);
        $t->same(['word/media draft'], $summary['relationshipTargetDirectoryNameCharacterFlagDirectories']['whitespace']);
        $t->same(['word/media%20encoded'], $summary['relationshipTargetDirectoryNameCharacterFlagDirectories']['percent-encoded-octet']);
        $t->same([$nonAsciiDirectory], $summary['relationshipTargetDirectoryNameCharacterFlagDirectories']['non-ascii']);
        $t->same(['word/Media/missing.png', 'word/Media/review.png'], $summary['relationshipTargetDirectoryNameCharacterFlagTargetParts']['uppercase']);
        $t->same(['word/media draft/review.png'], $summary['relationshipTargetDirectoryNameCharacterFlagTargetParts']['whitespace']);
        $t->same(['word/media%20encoded/review.png'], $summary['relationshipTargetDirectoryNameCharacterFlagTargetParts']['percent-encoded-octet']);
        $t->same([$nonAsciiTargetPart], $summary['relationshipTargetDirectoryNameCharacterFlagTargetParts']['non-ascii']);
        $t->same($expectedDirectoryNames, array_keys($byDirectory));

        $upper = $byDirectory['word/Media'];
        $t->same('word/Media', $upper['targetDirectory']);
        $t->same(2, $upper['relationshipCount']);
        $t->same(2, $upper['targetPartCount']);
        $t->same(1, $upper['existingTargetCount']);
        $t->same(1, $upper['missingTargetCount']);
        $t->same(['uppercase'], $upper['flags']);
        $t->same(['uppercase' => 2], $upper['flagRelationshipCounts']);
        $t->same(['missing.png' => 1, 'review.png' => 1], $upper['targetBaseNameCounts']);
        $t->same(['png' => 2], $upper['targetPartExtensionCounts']);
        $t->same(['image/png' => 2], $upper['contentTypeBaseCounts']);
        $t->same(['default' => 2], $upper['contentTypeSourceCounts']);
        $t->same(['http://schemas.openxmlformats.org/officeDocument/2006/relationships/image' => 2], $upper['relationshipTypeCounts']);
        $t->same([
            'document-relationship-target' => 1,
            'missing-relationship-target' => 1,
        ], $upper['roleCounts']);
        $t->same(['word/document.xml'], $upper['sourceParts']);
        $t->same(['word/_rels/document.xml.rels'], $upper['relationshipParts']);
        $t->same(['rUpper', 'rUpperMissing'], $upper['relationshipIds']);
        $t->same(['word/Media/missing.png', 'word/Media/review.png'], $upper['targetParts']);
        $t->same(['word/Media/review.png'], $upper['existingTargetParts']);
        $t->same(['word/Media/missing.png'], $upper['missingTargetParts']);
        $t->same('word/Media/review.png', $upper['largestExistingTargetPart']['partName']);
        $t->same(strlen('upper image bytes'), $upper['largestExistingTargetPart']['bytes']);
        $t->same('relationship-target-directory-name-character-metadata-only', $upper['reviewPolicy']);

        $whitespace = $byDirectory['word/media draft'];
        $t->same(['whitespace'], $whitespace['flags']);
        $t->same(['rWhitespace'], $whitespace['relationshipIds']);
        $t->same(['word/media draft/review.png'], $whitespace['targetParts']);

        $percent = $byDirectory['word/media%20encoded'];
        $t->same(['percent-encoded-octet'], $percent['flags']);
        $t->same(['rLiteralPercent'], $percent['relationshipIds']);
        $t->same(['word/media%20encoded/review.png'], $percent['targetParts']);

        $nonAscii = $byDirectory[$nonAsciiDirectory];
        $t->same(['non-ascii'], $nonAscii['flags']);
        $t->same(['rNonAscii'], $nonAscii['relationshipIds']);
        $t->same([$nonAsciiTargetPart], $nonAscii['targetParts']);

        $t->same('word/media%20encoded/review.png', $relationships['rLiteralPercent']['targetPart']);
        $t->same($nonAsciiTargetPart, $relationships['rNonAscii']['targetPart']);
        $t->same(false, in_array('word/media/Review.PNG', $allReviewedTargetParts, true));
        $t->same(false, in_array('rBaseNameOnly', $allReviewedRelationshipIds, true));
        $t->same(false, in_array('rExternalUpper', $allReviewedRelationshipIds, true));
    },
];
