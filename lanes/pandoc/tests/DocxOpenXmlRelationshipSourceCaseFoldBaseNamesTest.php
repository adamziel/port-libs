<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source case-fold base name variants' => static function (TestRunner $t): void {
        $parts = docx_relationship_source_casefold_base_name_fixture_parts();

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['relationshipSourceCaseFoldBaseNames'] as $group) {
            $groups[$group['sourceCaseFoldBaseNameKey']] = $group;
        }

        $t->same(7, $summary['relationshipSourceCount']);
        $t->same(4, $summary['relationshipSourceCaseFoldBaseNameCount']);
        $t->same([
            '/' => 1,
            'document.xml' => 3,
            'document.xml.rels' => 1,
            'report.xml' => 2,
        ], $summary['relationshipSourceCaseFoldBaseNameCounts']);
        $t->same([
            '/' => 1,
            'document.xml' => 3,
            'document.xml.rels' => 1,
        ], $summary['relationshipSourceExistingCaseFoldBaseNameCounts']);
        $t->same(['report.xml' => 2], $summary['relationshipSourceNonExistingCaseFoldBaseNameCounts']);
        $t->same(2, $summary['duplicateRelationshipSourceCaseFoldBaseNameCount']);
        $t->same(5, $summary['duplicateRelationshipSourceCaseFoldBaseNameSourceCount']);
        $t->same(5, $summary['duplicateRelationshipSourceCaseFoldBaseNameRelationshipCount']);
        $t->same(['document.xml', 'report.xml'], $summary['duplicateRelationshipSourceCaseFoldBaseNames']);
        $t->same([
            '/',
            'document.xml',
            'document.xml.rels',
            'report.xml',
        ], array_column($summary['relationshipSourceCaseFoldBaseNames'], 'sourceCaseFoldBaseNameKey'));

        $document = $groups['document.xml'];
        $t->same('document.xml', $document['sourceCaseFoldBaseName']);
        $t->same(3, $document['sourceCount']);
        $t->same(3, $document['existingSourceCount']);
        $t->same(0, $document['nonExistingSourceCount']);
        $t->same(3, $document['relationshipCount']);
        $t->same(3, $document['relationshipRecordCount']);
        $t->same(
            strlen($parts['customXml/document.xml'])
                + strlen($parts['word/Document.XML'])
                + strlen($parts['word/document.xml']),
            $document['existingSourceByteLength']
        );
        $t->same(['Document.XML' => 1, 'document.xml' => 2], $document['baseNameCounts']);
        $t->same(2, $document['baseNameVariantCount']);
        $t->same(['package-part' => 3], $document['relationshipSourceKindCounts']);
        $t->same(['customXml' => 1, 'word' => 2], $document['sourceDirectoryCounts']);
        $t->same(['xml' => 3], $document['sourcePartExtensionCounts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'application/xml' => 2,
        ], $document['sourceContentTypeBaseCounts']);
        $t->same(['default' => 2, 'override' => 1], $document['sourceContentTypeSourceCounts']);
        $t->same([
            'office-document' => 1,
            'package-part' => 2,
            'root-relationship-target' => 1,
        ], $document['sourceRoleCounts']);
        $t->same([
            'customXml/document.xml',
            'word/Document.XML',
            'word/document.xml',
        ], $document['sourceParts']);
        $t->same($document['sourceParts'], $document['existingSourceParts']);
        $t->same([], $document['nonExistingSourceParts']);
        $t->same([
            'customXml/_rels/document.xml.rels',
            'word/_rels/Document.XML.rels',
            'word/_rels/document.xml.rels',
        ], $document['relationshipParts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'application/xml',
        ], $document['contentTypes']);
        $t->same('word/Document.XML', $document['largestExistingSourcePart']['sourcePart']);
        $t->same('Document.XML', $document['largestExistingSourcePart']['sourceBaseName']);
        $t->same('document.xml', $document['largestExistingSourcePart']['sourceCaseFoldBaseName']);
        $t->same('xml', $document['largestExistingSourcePart']['sourcePartExtension']);
        $t->same(strlen($parts['word/Document.XML']), $document['largestExistingSourcePart']['sourceBytes']);
        $t->same(hash('sha256', $parts['word/Document.XML']), $document['largestExistingSourcePart']['sourceSha256']);
        $t->same('application/xml', $document['largestExistingSourcePart']['sourceContentTypeBase']);
        $t->same('default', $document['largestExistingSourcePart']['sourceContentTypeSource']);

        $report = $groups['report.xml'];
        $t->same(2, $report['sourceCount']);
        $t->same(0, $report['existingSourceCount']);
        $t->same(2, $report['nonExistingSourceCount']);
        $t->same(2, $report['relationshipCount']);
        $t->same(2, $report['relationshipRecordCount']);
        $t->same(0, $report['existingSourceByteLength']);
        $t->same(['Report.XML' => 1, 'report.xml' => 1], $report['baseNameCounts']);
        $t->same(2, $report['baseNameVariantCount']);
        $t->same(['missing-source' => 2], $report['relationshipSourceKindCounts']);
        $t->same(['word' => 2], $report['sourceDirectoryCounts']);
        $t->same(['xml' => 2], $report['sourcePartExtensionCounts']);
        $t->same(['(missing)' => 2], $report['sourceContentTypeBaseCounts']);
        $t->same(['(missing)' => 2], $report['sourceContentTypeSourceCounts']);
        $t->same([], $report['existingSourceParts']);
        $t->same(['word/Report.XML', 'word/report.xml'], $report['nonExistingSourceParts']);
        $t->same(null, $report['largestExistingSourcePart']);

        $relationshipPart = $groups['document.xml.rels'];
        $t->same(1, $relationshipPart['sourceCount']);
        $t->same(1, $relationshipPart['existingSourceCount']);
        $t->same(1, $relationshipPart['relationshipCount']);
        $t->same(['document.xml.rels' => 1], $relationshipPart['baseNameCounts']);
        $t->same(['relationship-part' => 1], $relationshipPart['relationshipSourceKindCounts']);
        $t->same(['office-document-relationships' => 1, 'relationship-part' => 1], $relationshipPart['sourceRoleCounts']);
        $t->same(['word/_rels/document.xml.rels'], $relationshipPart['sourceParts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_source_casefold_base_name_fixture_parts(): array
{
    $lowerDocument = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Lower relationship source basename fixture.</w:t></w:r></w:p></w:body>
</w:document>
XML;
    $upperDocument = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body><w:p><w:r><w:t>'
        . str_repeat('Upper relationship source basename payload. ', 24)
        . '</w:t></w:r></w:p></w:body></w:document>';

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="XML" ContentType="application/xml"/>
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
        'word/document.xml' => $lowerDocument,
        'word/Document.XML' => $upperDocument,
        'customXml/document.xml' => '<customXml>document source</customXml>',
        'word/media/lower.png' => 'lower image bytes',
        'word/media/upper.png' => 'upper image bytes',
        'word/media/custom.png' => 'custom image bytes',
        'word/media/report-upper.png' => 'report upper target bytes',
        'word/media/report-lower.png' => 'report lower target bytes',
        'word/media/from-relationship-source.png' => 'relationship source target bytes',
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLowerImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/lower.png"/>
</Relationships>
XML,
        'word/_rels/Document.XML.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="/word/media/upper.png"/>
</Relationships>
XML,
        'customXml/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCustomImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="/word/media/custom.png"/>
</Relationships>
XML,
        'word/_rels/Report.XML.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingReportUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/report-upper.png"/>
</Relationships>
XML,
        'word/_rels/report.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingReportLower" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/report-lower.png"/>
</Relationships>
XML,
        'word/_rels/_rels/document.xml.rels.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rRelationshipSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/from-relationship-source.png"/>
</Relationships>
XML,
    ];
}
