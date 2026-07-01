<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source part name character flags' => static function (TestRunner $t): void {
        $nonAsciiSource = "word/caf\xC3\xA9.xml";
        $nonAsciiRelationshipPart = "word/_rels/caf\xC3\xA9.xml.rels";
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
            'word/_rels/ReviewSource.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/upper-source.png"/>
</Relationships>
XML,
            'word/_rels/review source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rWhitespaceSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/whitespace-source.png"/>
</Relationships>
XML,
            'word/_rels/literal%20source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLiteralPercentSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/literal-percent-source.png"/>
</Relationships>
XML,
            $nonAsciiRelationshipPart => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rNonAsciiSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/non-ascii-source.png"/>
</Relationships>
XML,
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship source name review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/ReviewSource.xml' => '<review/>',
            'word/review source.xml' => '<review/>',
            'word/literal%20source.xml' => '<review/>',
            $nonAsciiSource => '<review/>',
            'word/media/document.png' => 'document image bytes',
            'word/media/upper-source.png' => 'upper source image bytes',
            'word/media/whitespace-source.png' => 'whitespace source image bytes',
            'word/media/literal-percent-source.png' => 'literal percent source image bytes',
            'word/media/non-ascii-source.png' => 'non ascii source image bytes',
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $bySourcePart = [];
        foreach ($summary['relationshipSourceNameCharacterReviewSources'] as $source) {
            $bySourcePart[$source['sourcePart']] = $source;
        }

        $expectedSourceParts = [
            'word/ReviewSource.xml',
            $nonAsciiSource,
            'word/literal%20source.xml',
            'word/review source.xml',
        ];

        $t->same(4, $summary['relationshipSourceNameCharacterReviewSourceCount']);
        $t->same(4, $summary['relationshipSourceNameCharacterReviewRelationshipCount']);
        $t->same(4, $summary['relationshipSourceNameCharacterReviewRelationshipRecordCount']);
        $t->same(4, $summary['relationshipSourceNameCharacterReviewSourcePartCount']);
        $t->same(1, $summary['relationshipSourceNameUppercaseSourceCount']);
        $t->same(1, $summary['relationshipSourceNameWhitespaceSourceCount']);
        $t->same(1, $summary['relationshipSourceNamePercentEncodedOctetSourceCount']);
        $t->same(1, $summary['relationshipSourceNameNonAsciiSourceCount']);
        $t->same([
            'non-ascii' => 1,
            'percent-encoded-octet' => 1,
            'uppercase' => 1,
            'whitespace' => 1,
        ], $summary['relationshipSourceNameCharacterFlagSourceCounts']);
        $t->same(['word/ReviewSource.xml'], $summary['relationshipSourceNameCharacterFlagSourceParts']['uppercase']);
        $t->same(['word/review source.xml'], $summary['relationshipSourceNameCharacterFlagSourceParts']['whitespace']);
        $t->same(['word/literal%20source.xml'], $summary['relationshipSourceNameCharacterFlagSourceParts']['percent-encoded-octet']);
        $t->same([$nonAsciiSource], $summary['relationshipSourceNameCharacterFlagSourceParts']['non-ascii']);
        $t->same($expectedSourceParts, $summary['relationshipSourceNameCharacterReviewSourceParts']);

        $upper = $bySourcePart['word/ReviewSource.xml'];
        $t->same('word/ReviewSource.xml', $upper['sourcePart']);
        $t->same('word', $upper['sourceDirectory']);
        $t->same('ReviewSource.xml', $upper['sourceBaseName']);
        $t->same(['word', 'ReviewSource.xml'], $upper['sourcePathSegments']);
        $t->same('xml', $upper['sourcePartExtension']);
        $t->same(true, $upper['sourceExists']);
        $t->same('package-part', $upper['relationshipSourceKind']);
        $t->same('application/xml', $upper['sourceContentTypeBase']);
        $t->same('default', $upper['sourceContentTypeSource']);
        $t->same('word/_rels/ReviewSource.xml.rels', $upper['relationshipsPart']);
        $t->same(1, $upper['relationshipCount']);
        $t->same(1, $upper['relationshipRecordCount']);
        $t->same(['uppercase'], $upper['flags']);
        $t->same('relationship-source-name-character-metadata-only', $upper['reviewPolicy']);

        $t->same(['whitespace'], $bySourcePart['word/review source.xml']['flags']);
        $t->same(['percent-encoded-octet'], $bySourcePart['word/literal%20source.xml']['flags']);
        $t->same(['non-ascii'], $bySourcePart[$nonAsciiSource]['flags']);
        $t->same(false, isset($bySourcePart['word/document.xml']));
    },
];
