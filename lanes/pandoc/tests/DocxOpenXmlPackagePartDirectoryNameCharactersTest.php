<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package part directory name character flags for package review' => static function (TestRunner $t): void {
        $nonAsciiDirectory = "word/caf\xC3\xA9";
        $nonAsciiPart = $nonAsciiDirectory . '/review.xml';
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
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package part directory name review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'CustomXml/item.xml' => '<custom/>',
            'CustomXml/Missing.DATA' => 'missing content type directory payload',
            'word/media draft/review.png' => 'whitespace directory image',
            'word/media%20encoded/review.xml' => '<encoded-directory/>',
            $nonAsciiPart => '<non-ascii-directory/>',
            'word/media/Review.PNG' => 'base name only uppercase',
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $byDirectory = [];
        foreach ($summary['partDirectoryNameCharacterReviewDirectories'] as $directory) {
            $byDirectory[$directory['directory']] = $directory;
        }

        $expectedDirectories = [
            'CustomXml',
            $nonAsciiDirectory,
            'word/media draft',
            'word/media%20encoded',
        ];

        $t->same(4, $summary['partDirectoryNameCharacterReviewDirectoryCount']);
        $t->same(5, $summary['partDirectoryNameCharacterReviewPartCount']);
        $t->same(2, $summary['partDirectoryNameUppercasePartCount']);
        $t->same(1, $summary['partDirectoryNameWhitespacePartCount']);
        $t->same(1, $summary['partDirectoryNamePercentEncodedOctetPartCount']);
        $t->same(1, $summary['partDirectoryNameNonAsciiPartCount']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 2,
            'whitespace' => 1,
        ], $summary['partDirectoryNameCharacterFlagPartCounts']);
        $t->same($expectedDirectories, $summary['partDirectoryNameCharacterReviewDirectoryNames']);
        $t->same(['CustomXml'], $summary['partDirectoryNameCharacterFlagDirectories']['uppercase']);
        $t->same(['word/media draft'], $summary['partDirectoryNameCharacterFlagDirectories']['whitespace']);
        $t->same(['word/media%20encoded'], $summary['partDirectoryNameCharacterFlagDirectories']['percent-encoded-octet']);
        $t->same([$nonAsciiDirectory], $summary['partDirectoryNameCharacterFlagDirectories']['non-ascii']);
        $t->same(['CustomXml/Missing.DATA', 'CustomXml/item.xml'], $summary['partDirectoryNameCharacterFlagPartNames']['uppercase']);
        $t->same(['word/media draft/review.png'], $summary['partDirectoryNameCharacterFlagPartNames']['whitespace']);
        $t->same(['word/media%20encoded/review.xml'], $summary['partDirectoryNameCharacterFlagPartNames']['percent-encoded-octet']);
        $t->same([$nonAsciiPart], $summary['partDirectoryNameCharacterFlagPartNames']['non-ascii']);

        $customXml = $byDirectory['CustomXml'];
        $t->same('CustomXml', $customXml['directory']);
        $t->same(1, $customXml['directoryDepth']);
        $t->same(2, $customXml['partCount']);
        $t->same(1, $customXml['missingContentTypePartCount']);
        $t->same(['uppercase'], $customXml['flags']);
        $t->same(['uppercase' => 2], $customXml['flagPartCounts']);
        $t->same(['Missing.DATA' => 1, 'item.xml' => 1], $customXml['baseNameCounts']);
        $t->same(['data' => 1, 'xml' => 1], $customXml['partExtensionCounts']);
        $t->same(['(missing)' => 1, 'application/xml' => 1], $customXml['contentTypeBaseCounts']);
        $t->same(['default' => 1, 'missing' => 1], $customXml['contentTypeSourceCounts']);
        $t->same(['package-part' => 2], $customXml['roleCounts']);
        $t->same(['CustomXml/Missing.DATA', 'CustomXml/item.xml'], $customXml['partNames']);
        $t->same('package-part-directory-name-character-metadata-only', $customXml['reviewPolicy']);

        $t->same(['whitespace'], $byDirectory['word/media draft']['flags']);
        $t->same(['word/media draft/review.png'], $byDirectory['word/media draft']['partNames']);
        $t->same(['percent-encoded-octet'], $byDirectory['word/media%20encoded']['flags']);
        $t->same([$nonAsciiPart], $byDirectory[$nonAsciiDirectory]['partNames']);
        $t->same(['non-ascii'], $byDirectory[$nonAsciiDirectory]['flags']);

        $t->same(['uppercase'], $inventory['CustomXml/item.xml']['directoryNameCharacterFlags']);
        $t->same(true, $inventory['CustomXml/item.xml']['directoryNameHasUppercase']);
        $t->same(true, $inventory['word/media draft/review.png']['directoryNameHasWhitespace']);
        $t->same(true, $inventory['word/media%20encoded/review.xml']['directoryNameHasPercentEncodedOctet']);
        $t->same(true, $inventory[$nonAsciiPart]['directoryNameHasNonAscii']);
        $t->same([], $inventory['word/media/Review.PNG']['directoryNameCharacterFlags']);
        $t->same(false, $inventory['word/media/Review.PNG']['directoryNameHasUppercase']);
        $t->same(false, in_array('word/media/Review.PNG', $summary['partDirectoryNameCharacterFlagPartNames']['uppercase'], true));
    },
    'summarizes DOCX package part directory name character aggregate metadata' => static function (TestRunner $t): void {
        $relationshipPart = 'word/UpperDir/_rels/source.xml.rels';
        $payloadPart = 'word/UpperDir/payload.bin';
        $sourcePart = 'word/UpperDir/source.xml';
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
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
    <w:p><w:r><w:t>Package part directory aggregate review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            $sourcePart => '<source/>',
            $payloadPart => str_repeat('B', 41),
            $relationshipPart => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>
XML,
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $byDirectory = [];
        foreach ($summary['partDirectoryNameCharacterReviewDirectories'] as $directory) {
            $byDirectory[$directory['directory']] = $directory;
        }

        $expectedPartNames = [$relationshipPart, $payloadPart, $sourcePart];
        sort($expectedPartNames, SORT_STRING);

        $t->same(2, $summary['partDirectoryNameCharacterReviewDirectoryCount']);
        $t->same(3, $summary['partDirectoryNameCharacterReviewPartCount']);
        $t->same(3, $summary['partDirectoryNameUppercasePartCount']);
        $t->same(['uppercase' => 3], $summary['partDirectoryNameCharacterFlagPartCounts']);
        $t->same(['word/UpperDir', 'word/UpperDir/_rels'], $summary['partDirectoryNameCharacterReviewDirectoryNames']);
        $t->same($expectedPartNames, $summary['partDirectoryNameCharacterFlagPartNames']['uppercase']);

        $upper = $byDirectory['word/UpperDir'];
        $t->same(2, $upper['directoryDepth']);
        $t->same(2, $upper['partCount']);
        $t->same(0, $upper['relationshipPartCount']);
        $t->same(1, $upper['parameterizedPartCount']);
        $t->same([], $upper['relationshipParts']);
        $t->same(['application/octet-stream' => 1, 'application/xml' => 1], $upper['contentTypeBaseCounts']);
        $t->same(['default' => 2], $upper['contentTypeSourceCounts']);
        $t->same('payload.bin', $upper['largestPart']['baseName']);
        $t->same(2, $upper['largestPart']['directoryDepth']);
        $t->same('application/octet-stream', $upper['largestPart']['contentTypeBase']);
        $t->same(true, $upper['largestPart']['contentTypeHasParameters']);
        $t->same(1, $upper['largestPart']['contentTypeParameterCount']);

        $relationships = $byDirectory['word/UpperDir/_rels'];
        $t->same(3, $relationships['directoryDepth']);
        $t->same(1, $relationships['partCount']);
        $t->same(1, $relationships['relationshipPartCount']);
        $t->same([$relationshipPart], $relationships['relationshipParts']);
        $t->same(['rels' => 1], $relationships['partExtensionCounts']);
        $t->same(['application/vnd.openxmlformats-package.relationships+xml' => 1], $relationships['contentTypeBaseCounts']);
        $t->same($relationshipPart, $relationships['largestPart']['partName']);
    },
];
