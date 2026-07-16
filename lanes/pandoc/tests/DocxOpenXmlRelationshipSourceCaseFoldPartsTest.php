<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source case-fold part collisions' => static function (TestRunner $t): void {
        $lowerDocument = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Lower source.</w:t></w:r></w:p></w:body>
</w:document>
XML;
        $upperDocument = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Upper source with a larger payload for byte rollup.</w:t></w:r></w:p></w:body>
</w:document>
XML;
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
            'word/document.xml' => $lowerDocument,
            'word/Document.xml' => $upperDocument,
            'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLowerImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/lower.png"/>
  <Relationship Id="rSharedImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/shared.png"/>
</Relationships>
XML,
            'word/_rels/Document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/upper.png"/>
</Relationships>
XML,
            'word/_rels/missing-source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingLower" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-lower.png"/>
</Relationships>
XML,
            'word/_rels/MISSING-SOURCE.XML.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-upper.png"/>
</Relationships>
XML,
            'word/media/lower.png' => 'lower image',
            'word/media/shared.png' => 'shared image',
            'word/media/upper.png' => 'upper image',
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['duplicateRelationshipSourceCaseFoldPartGroups'] as $group) {
            $groups[$group['caseFoldSourcePart']] = $group;
        }

        $t->same(2, $summary['relationshipSourceCaseFoldPartCount']);
        $t->same(2, $summary['duplicateRelationshipSourceCaseFoldPartCount']);
        $t->same(4, $summary['duplicateRelationshipSourceCaseFoldSourceCount']);
        $t->same(5, $summary['duplicateRelationshipSourceCaseFoldRelationshipCount']);
        $t->same(4, $summary['duplicateRelationshipSourceCaseFoldPartNameCount']);
        $t->same(['word/document.xml', 'word/missing-source.xml'], $summary['duplicateRelationshipSourceCaseFoldParts']);

        $review = $groups['word/document.xml'];
        $t->same('word/document.xml', $review['caseFoldSourcePart']);
        $t->same(2, $review['sourceCount']);
        $t->same(3, $review['relationshipCount']);
        $t->same(3, $review['relationshipRecordCount']);
        $t->same(2, $review['sourcePartVariantCount']);
        $t->same(2, $review['existingSourceCount']);
        $t->same(0, $review['missingSourceCount']);
        $t->same(strlen($lowerDocument) + strlen($upperDocument), $review['existingSourcePartByteLength']);
        $t->same([
            'word/Document.xml' => 1,
            'word/document.xml' => 1,
        ], $review['sourcePartCounts']);
        $t->same(['package-part' => 2], $review['relationshipSourceKindCounts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'application/xml' => 1,
        ], $review['contentTypeBaseCounts']);
        $t->same(['default' => 1, 'override' => 1], $review['contentTypeSourceCounts']);
        $t->same(['word/Document.xml', 'word/document.xml'], $review['sourceParts']);
        $t->same(['word/Document.xml', 'word/document.xml'], $review['existingSourceParts']);
        $t->same([], $review['missingSourceParts']);
        $t->same(['word/_rels/Document.xml.rels', 'word/_rels/document.xml.rels'], $review['relationshipParts']);
        $t->same('word/Document.xml', $review['largestExistingSourcePart']['sourcePart']);
        $t->same(strlen($upperDocument), $review['largestExistingSourcePart']['sourceBytes']);
        $t->same(hash('sha256', $upperDocument), $review['largestExistingSourcePart']['sourceSha256']);
        $t->same('application/xml', $review['largestExistingSourcePart']['sourceContentTypeBase']);
        $t->same('default', $review['largestExistingSourcePart']['sourceContentTypeSource']);
        $t->same(1, $review['largestExistingSourcePart']['relationshipCount']);

        $missing = $groups['word/missing-source.xml'];
        $t->same(2, $missing['sourceCount']);
        $t->same(2, $missing['relationshipCount']);
        $t->same(2, $missing['relationshipRecordCount']);
        $t->same(2, $missing['sourcePartVariantCount']);
        $t->same(0, $missing['existingSourceCount']);
        $t->same(2, $missing['missingSourceCount']);
        $t->same(0, $missing['existingSourcePartByteLength']);
        $t->same([
            'word/MISSING-SOURCE.XML' => 1,
            'word/missing-source.xml' => 1,
        ], $missing['sourcePartCounts']);
        $t->same(['missing-source' => 2], $missing['relationshipSourceKindCounts']);
        $t->same(['(missing)' => 2], $missing['contentTypeBaseCounts']);
        $t->same(['missing' => 2], $missing['contentTypeSourceCounts']);
        $t->same([], $missing['existingSourceParts']);
        $t->same(['word/MISSING-SOURCE.XML', 'word/missing-source.xml'], $missing['missingSourceParts']);
        $t->same(null, $missing['largestExistingSourcePart']);
    },
];
