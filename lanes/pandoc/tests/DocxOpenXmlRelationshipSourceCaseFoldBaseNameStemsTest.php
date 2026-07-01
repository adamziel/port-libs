<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source case-fold base name stems' => static function (TestRunner $t): void {
        $parts = docx_relationship_source_casefold_base_name_stem_fixture_parts();

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $stems = [];
        foreach ($summary['relationshipSourceCaseFoldBaseNameStems'] as $stem) {
            $stems[$stem['sourceCaseFoldBaseNameStemKey']] = $stem;
        }

        $t->same(8, $summary['relationshipSourceCount']);
        $t->same(4, $summary['relationshipSourceCaseFoldBaseNameStemCount']);
        $t->same([
            '/' => 1,
            'document' => 3,
            'item' => 1,
            'report' => 3,
        ], $summary['relationshipSourceCaseFoldBaseNameStemCounts']);
        $t->same([
            '/' => 1,
            'document' => 3,
            'item' => 1,
            'report' => 2,
        ], $summary['relationshipSourceExistingCaseFoldBaseNameStemCounts']);
        $t->same(['report' => 1], $summary['relationshipSourceNonExistingCaseFoldBaseNameStemCounts']);
        $t->same(2, $summary['duplicateRelationshipSourceCaseFoldBaseNameStemCount']);
        $t->same(6, $summary['duplicateRelationshipSourceCaseFoldBaseNameStemSourceCount']);
        $t->same(8, $summary['duplicateRelationshipSourceCaseFoldBaseNameStemRelationshipCount']);
        $t->same(['document', 'report'], $summary['duplicateRelationshipSourceCaseFoldBaseNameStems']);
        $t->same([
            '/',
            'document',
            'item',
            'report',
        ], array_column($summary['relationshipSourceCaseFoldBaseNameStems'], 'sourceCaseFoldBaseNameStemKey'));

        $document = $stems['document'];
        $t->same('document', $document['sourceCaseFoldBaseNameStem']);
        $t->same(3, $document['sourceCount']);
        $t->same(3, $document['existingSourceCount']);
        $t->same(0, $document['nonExistingSourceCount']);
        $t->same(0, $document['missingContentTypeSourceCount']);
        $t->same(0, $document['parameterizedSourceCount']);
        $t->same(4, $document['relationshipCount']);
        $t->same(4, $document['relationshipRecordCount']);
        $t->same(
            strlen($parts['Word/DOCUMENT.XML'])
                + strlen($parts['word/document.bin'])
                + strlen($parts['word/document.xml']),
            $document['existingSourceByteLength']
        );
        $t->same(['DOCUMENT' => 1, 'document' => 2], $document['baseNameStemCounts']);
        $t->same(['DOCUMENT.XML' => 1, 'document.bin' => 1, 'document.xml' => 1], $document['baseNameCounts']);
        $t->same(['bin' => 1, 'xml' => 2], $document['sourcePartExtensionCounts']);
        $t->same(['2' => 3], $document['sourcePathDepthCounts']);
        $t->same(['package-part' => 3], $document['relationshipSourceKindCounts']);
        $t->same(['Word' => 1, 'word' => 2], $document['sourceDirectoryCounts']);
        $t->same([
            'application/octet-stream' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'application/xml' => 1,
        ], $document['sourceContentTypeBaseCounts']);
        $t->same(['default' => 2, 'override' => 1], $document['sourceContentTypeSourceCounts']);
        $t->same([
            'office-document' => 1,
            'package-part' => 2,
            'root-relationship-target' => 1,
        ], $document['sourceRoleCounts']);
        $t->same([
            'Word/DOCUMENT.XML',
            'word/document.bin',
            'word/document.xml',
        ], $document['sourceParts']);
        $t->same($document['sourceParts'], $document['existingSourceParts']);
        $t->same([], $document['nonExistingSourceParts']);
        $t->same([
            'Word/_rels/DOCUMENT.XML.rels',
            'word/_rels/document.bin.rels',
            'word/_rels/document.xml.rels',
        ], $document['relationshipParts']);
        $t->same([
            'application/octet-stream',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'application/xml',
        ], $document['contentTypes']);
        $t->same(2, $document['baseNameStemVariantCount']);
        $t->same(3, $document['baseNameVariantCount']);
        $t->same(2, $document['extensionVariantCount']);
        $t->same('Word/DOCUMENT.XML', $document['largestExistingSourcePart']['sourcePart']);
        $t->same('DOCUMENT.XML', $document['largestExistingSourcePart']['sourceBaseName']);
        $t->same('document.xml', $document['largestExistingSourcePart']['sourceCaseFoldBaseName']);
        $t->same('DOCUMENT', $document['largestExistingSourcePart']['sourceBaseNameStem']);
        $t->same('document', $document['largestExistingSourcePart']['sourceCaseFoldBaseNameStem']);
        $t->same('xml', $document['largestExistingSourcePart']['sourcePartExtension']);
        $t->same(strlen($parts['Word/DOCUMENT.XML']), $document['largestExistingSourcePart']['sourceBytes']);
        $t->same(hash('sha256', $parts['Word/DOCUMENT.XML']), $document['largestExistingSourcePart']['sourceSha256']);

        $report = $stems['report'];
        $t->same(3, $report['sourceCount']);
        $t->same(2, $report['existingSourceCount']);
        $t->same(1, $report['nonExistingSourceCount']);
        $t->same(1, $report['missingContentTypeSourceCount']);
        $t->same(4, $report['relationshipCount']);
        $t->same(4, $report['relationshipRecordCount']);
        $t->same(strlen($parts['word/review/Report.XML']) + strlen($parts['word/review/report.bin']), $report['existingSourceByteLength']);
        $t->same(['REPORT' => 1, 'Report' => 1, 'report' => 1], $report['baseNameStemCounts']);
        $t->same(['REPORT.Missing' => 1, 'Report.XML' => 1, 'report.bin' => 1], $report['baseNameCounts']);
        $t->same(['bin' => 1, 'missing' => 1, 'xml' => 1], $report['sourcePartExtensionCounts']);
        $t->same(['3' => 3], $report['sourcePathDepthCounts']);
        $t->same(['missing-source' => 1, 'package-part' => 2], $report['relationshipSourceKindCounts']);
        $t->same(['word/review' => 3], $report['sourceDirectoryCounts']);
        $t->same([
            '(missing)' => 1,
            'application/octet-stream' => 1,
            'application/xml' => 1,
        ], $report['sourceContentTypeBaseCounts']);
        $t->same(['(missing)' => 1, 'default' => 2], $report['sourceContentTypeSourceCounts']);
        $t->same(['package-part' => 2], $report['sourceRoleCounts']);
        $t->same(['word/review/REPORT.Missing', 'word/review/Report.XML', 'word/review/report.bin'], $report['sourceParts']);
        $t->same(['word/review/Report.XML', 'word/review/report.bin'], $report['existingSourceParts']);
        $t->same(['word/review/REPORT.Missing'], $report['nonExistingSourceParts']);
        $t->same([
            'word/review/_rels/REPORT.Missing.rels',
            'word/review/_rels/Report.XML.rels',
            'word/review/_rels/report.bin.rels',
        ], $report['relationshipParts']);
        $t->same(['application/octet-stream', 'application/xml'], $report['contentTypes']);
        $t->same(3, $report['baseNameStemVariantCount']);
        $t->same(3, $report['baseNameVariantCount']);
        $t->same(3, $report['extensionVariantCount']);
        $t->same('word/review/report.bin', $report['largestExistingSourcePart']['sourcePart']);
        $t->same('report.bin', $report['largestExistingSourcePart']['sourceBaseName']);
        $t->same('report', $report['largestExistingSourcePart']['sourceCaseFoldBaseNameStem']);
        $t->same('bin', $report['largestExistingSourcePart']['sourcePartExtension']);

        $item = $stems['item'];
        $t->same(1, $item['sourceCount']);
        $t->same(1, $item['existingSourceCount']);
        $t->same(['Item' => 1], $item['baseNameStemCounts']);
        $t->same(['Item.XML' => 1], $item['baseNameCounts']);
        $t->same(['customXml/Item.XML'], $item['sourceParts']);

        $root = $stems['/'];
        $t->same(1, $root['sourceCount']);
        $t->same(1, $root['existingSourceCount']);
        $t->same(['/' => 1], $root['baseNameStemCounts']);
        $t->same(['0' => 1], $root['sourcePathDepthCounts']);
        $t->same(['package-root' => 1], $root['relationshipSourceKindCounts']);
        $t->same(['package-root' => 1], $root['sourceRoleCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_source_casefold_base_name_stem_fixture_parts(): array
{
    $lowerDocument = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Lower document source.</w:t></w:r></w:p></w:body>
</w:document>
XML;
    $upperDocument = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body><w:p><w:r><w:t>'
        . str_repeat('Upper document case-fold stem payload. ', 32)
        . '</w:t></w:r></w:p></w:body></w:document>';
    $documentBin = str_repeat('D', 53);
    $reportXml = '<report>' . str_repeat('xml-source ', 8) . '</report>';
    $reportBin = str_repeat('R', 161);

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="XML" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
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
        'Word/DOCUMENT.XML' => $upperDocument,
        'word/document.bin' => $documentBin,
        'word/review/Report.XML' => $reportXml,
        'word/review/report.bin' => $reportBin,
        'customXml/Item.XML' => '<item>single source</item>',
        'word/media/lower.png' => 'lower image bytes',
        'word/media/upper-a.png' => 'upper image bytes a',
        'word/media/upper-b.png' => 'upper image bytes b',
        'word/media/document-bin.png' => 'document bin image bytes',
        'word/review/media/report-xml.png' => 'report xml image bytes',
        'word/review/media/report-bin-a.png' => 'report bin image bytes a',
        'word/review/media/report-bin-b.png' => 'report bin image bytes b',
        'word/review/media/report-missing.png' => 'report missing image bytes',
        'word/media/item.png' => 'item image bytes',
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLowerImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/lower.png"/>
</Relationships>
XML,
        'Word/_rels/DOCUMENT.XML.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperImageA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="/word/media/upper-a.png"/>
  <Relationship Id="rUpperImageB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="/word/media/upper-b.png"/>
</Relationships>
XML,
        'word/_rels/document.bin.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocumentBinImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/document-bin.png"/>
</Relationships>
XML,
        'word/review/_rels/Report.XML.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReportXmlImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/report-xml.png"/>
</Relationships>
XML,
        'word/review/_rels/report.bin.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rReportBinImageA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/report-bin-a.png"/>
  <Relationship Id="rReportBinImageB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/report-bin-b.png"/>
</Relationships>
XML,
        'word/review/_rels/REPORT.Missing.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingReportImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/report-missing.png"/>
</Relationships>
XML,
        'customXml/_rels/Item.XML.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rItemImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../word/media/item.png"/>
</Relationships>
XML,
    ];
}
