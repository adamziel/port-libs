<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source directory name character flags for package review' => static function (TestRunner $t): void {
        $nonAsciiDirectory = "word/caf\xC3\xA9";
        $nonAsciiSource = $nonAsciiDirectory . '/source.xml';
        $nonAsciiRelationshipPart = $nonAsciiDirectory . '/_rels/source.xml.rels';
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
  <Relationship Id="rDocumentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/document.png"/>
</Relationships>
XML,
            'word/UpperDir/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/upper.png"/>
</Relationships>
XML,
            'word/UpperDir/_rels/missing.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
</Relationships>
XML,
            'word/space dir/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rWhitespaceSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/space.png"/>
</Relationships>
XML,
            'word/literal%20dir/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLiteralPercentSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/literal.png"/>
</Relationships>
XML,
            $nonAsciiRelationshipPart => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rNonAsciiSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/non-ascii.png"/>
</Relationships>
XML,
            'word/plain/_rels/ReviewSource.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rBaseNameOnly" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/plain.png"/>
</Relationships>
XML,
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship source directory name review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/UpperDir/source.xml' => str_repeat('U', 31),
            'word/space dir/source.xml' => str_repeat('S', 23),
            'word/literal%20dir/source.xml' => str_repeat('P', 29),
            $nonAsciiSource => str_repeat('N', 37),
            'word/plain/ReviewSource.xml' => '<review/>',
            'word/media/document.png' => 'document image bytes',
            'word/UpperDir/media/upper.png' => 'upper image bytes',
            'word/space dir/media/space.png' => 'space image bytes',
            'word/literal%20dir/media/literal.png' => 'literal image bytes',
            $nonAsciiDirectory . '/media/non-ascii.png' => 'non ascii image bytes',
            'word/plain/media/plain.png' => 'plain image bytes',
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $byDirectory = [];
        $allReviewedSourceParts = [];
        $allReviewedRelationshipParts = [];
        foreach ($summary['relationshipSourceDirectoryNameCharacterReviewDirectories'] as $directory) {
            $byDirectory[$directory['sourceDirectory']] = $directory;
            foreach ($directory['sourceParts'] as $sourcePart) {
                $allReviewedSourceParts[] = $sourcePart;
            }
            foreach ($directory['relationshipParts'] as $relationshipPart) {
                $allReviewedRelationshipParts[] = $relationshipPart;
            }
        }
        sort($allReviewedSourceParts, SORT_STRING);
        sort($allReviewedRelationshipParts, SORT_STRING);

        $expectedDirectoryNames = [
            'word/UpperDir',
            $nonAsciiDirectory,
            'word/literal%20dir',
            'word/space dir',
        ];
        sort($expectedDirectoryNames, SORT_STRING);

        $t->same(4, $summary['relationshipSourceDirectoryNameCharacterReviewDirectoryCount']);
        $t->same(5, $summary['relationshipSourceDirectoryNameCharacterReviewSourceCount']);
        $t->same(5, $summary['relationshipSourceDirectoryNameCharacterReviewRelationshipCount']);
        $t->same(5, $summary['relationshipSourceDirectoryNameCharacterReviewRelationshipRecordCount']);
        $t->same(5, $summary['relationshipSourceDirectoryNameCharacterReviewSourcePartCount']);
        $t->same(2, $summary['relationshipSourceDirectoryNameUppercaseSourceCount']);
        $t->same(1, $summary['relationshipSourceDirectoryNameWhitespaceSourceCount']);
        $t->same(1, $summary['relationshipSourceDirectoryNamePercentEncodedOctetSourceCount']);
        $t->same(1, $summary['relationshipSourceDirectoryNameNonAsciiSourceCount']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 2,
            'whitespace' => 1,
        ], $summary['relationshipSourceDirectoryNameCharacterFlagSourceCounts']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 2,
            'whitespace' => 1,
        ], $summary['relationshipSourceDirectoryNameCharacterFlagRelationshipCounts']);
        $t->same($expectedDirectoryNames, $summary['relationshipSourceDirectoryNameCharacterReviewDirectoryNames']);
        $t->same(['word/UpperDir'], $summary['relationshipSourceDirectoryNameCharacterFlagDirectories']['uppercase']);
        $t->same(['word/space dir'], $summary['relationshipSourceDirectoryNameCharacterFlagDirectories']['whitespace']);
        $t->same(['word/literal%20dir'], $summary['relationshipSourceDirectoryNameCharacterFlagDirectories']['percent-encoded-octet']);
        $t->same([$nonAsciiDirectory], $summary['relationshipSourceDirectoryNameCharacterFlagDirectories']['non-ascii']);
        $t->same([
            'word/UpperDir/missing.xml',
            'word/UpperDir/source.xml',
        ], $summary['relationshipSourceDirectoryNameCharacterFlagSourceParts']['uppercase']);
        $t->same(['word/space dir/source.xml'], $summary['relationshipSourceDirectoryNameCharacterFlagSourceParts']['whitespace']);
        $t->same(['word/literal%20dir/source.xml'], $summary['relationshipSourceDirectoryNameCharacterFlagSourceParts']['percent-encoded-octet']);
        $t->same([$nonAsciiSource], $summary['relationshipSourceDirectoryNameCharacterFlagSourceParts']['non-ascii']);
        $t->same($expectedDirectoryNames, array_keys($byDirectory));

        $upper = $byDirectory['word/UpperDir'];
        $t->same('word/UpperDir', $upper['sourceDirectory']);
        $t->same(2, $upper['sourceCount']);
        $t->same(2, $upper['sourcePartCount']);
        $t->same(2, $upper['relationshipCount']);
        $t->same(2, $upper['relationshipRecordCount']);
        $t->same(1, $upper['existingSourceCount']);
        $t->same(1, $upper['nonExistingSourceCount']);
        $t->same(1, $upper['missingContentTypeSourceCount']);
        $t->same(0, $upper['parameterizedSourceCount']);
        $t->same(strlen($parts['word/UpperDir/source.xml']), $upper['existingSourceByteLength']);
        $t->same(['uppercase'], $upper['flags']);
        $t->same(['uppercase' => 2], $upper['flagSourceCounts']);
        $t->same(['uppercase' => 2], $upper['flagRelationshipCounts']);
        $t->same(['missing.xml' => 1, 'source.xml' => 1], $upper['sourceBaseNameCounts']);
        $t->same(['xml' => 2], $upper['sourcePartExtensionCounts']);
        $t->same(['3' => 2], $upper['sourcePathDepthCounts']);
        $t->same(['2' => 2], $upper['sourceDirectoryDepthCounts']);
        $t->same(['missing-source' => 1, 'package-part' => 1], $upper['relationshipSourceKindCounts']);
        $t->same(['(missing)' => 1, 'application/xml' => 1], $upper['sourceContentTypeBaseCounts']);
        $t->same(['default' => 1, 'missing' => 1], $upper['sourceContentTypeSourceCounts']);
        $t->same(['package-part' => 1], $upper['sourceRoleCounts']);
        $t->same(['word/UpperDir/missing.xml', 'word/UpperDir/source.xml'], $upper['sourceParts']);
        $t->same(['word/UpperDir/source.xml'], $upper['existingSourceParts']);
        $t->same(['word/UpperDir/missing.xml'], $upper['nonExistingSourceParts']);
        $t->same([
            'word/UpperDir/_rels/missing.xml.rels',
            'word/UpperDir/_rels/source.xml.rels',
        ], $upper['relationshipParts']);
        $t->same(['application/xml'], $upper['contentTypes']);
        $t->same('word/UpperDir/source.xml', $upper['largestExistingSourcePart']['sourcePart']);
        $t->same('word/UpperDir', $upper['largestExistingSourcePart']['sourceDirectory']);
        $t->same(2, $upper['largestExistingSourcePart']['sourceDirectoryDepth']);
        $t->same(3, $upper['largestExistingSourcePart']['sourcePathDepth']);
        $t->same('source.xml', $upper['largestExistingSourcePart']['sourceBaseName']);
        $t->same('xml', $upper['largestExistingSourcePart']['sourcePartExtension']);
        $t->same(strlen($parts['word/UpperDir/source.xml']), $upper['largestExistingSourcePart']['sourceBytes']);
        $t->same(hash('sha256', $parts['word/UpperDir/source.xml']), $upper['largestExistingSourcePart']['sourceSha256']);
        $t->same('application/xml', $upper['largestExistingSourcePart']['sourceContentTypeBase']);
        $t->same('default', $upper['largestExistingSourcePart']['sourceContentTypeSource']);
        $t->same(false, $upper['largestExistingSourcePart']['sourceContentTypeHasParameters']);
        $t->same(0, $upper['largestExistingSourcePart']['sourceContentTypeParameterCount']);
        $t->same(['package-part'], $upper['largestExistingSourcePart']['sourceRoles']);
        $t->same(1, $upper['largestExistingSourcePart']['relationshipCount']);
        $t->same(1, $upper['largestExistingSourcePart']['relationshipRecordCount']);
        $t->same('relationship-source-directory-name-character-metadata-only', $upper['reviewPolicy']);

        $whitespace = $byDirectory['word/space dir'];
        $t->same(['whitespace'], $whitespace['flags']);
        $t->same(['word/space dir/source.xml'], $whitespace['sourceParts']);
        $t->same(['word/space dir/_rels/source.xml.rels'], $whitespace['relationshipParts']);

        $percent = $byDirectory['word/literal%20dir'];
        $t->same(['percent-encoded-octet'], $percent['flags']);
        $t->same(['word/literal%20dir/source.xml'], $percent['sourceParts']);
        $t->same(['word/literal%20dir/_rels/source.xml.rels'], $percent['relationshipParts']);

        $nonAscii = $byDirectory[$nonAsciiDirectory];
        $t->same(['non-ascii'], $nonAscii['flags']);
        $t->same([$nonAsciiSource], $nonAscii['sourceParts']);
        $t->same([$nonAsciiRelationshipPart], $nonAscii['relationshipParts']);

        $t->same(false, in_array('word/plain/ReviewSource.xml', $allReviewedSourceParts, true));
        $t->same(false, in_array('word/plain/_rels/ReviewSource.xml.rels', $allReviewedRelationshipParts, true));
    },
];
