<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source case-fold top-level segments for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_source_casefold_top_level_segment_fixture_parts();

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['relationshipSourceCaseFoldTopLevelSegments'] as $group) {
            $groups[$group['sourceCaseFoldTopLevelSegmentKey']] = $group;
        }

        $t->same(5, $summary['relationshipSourceCount']);
        $t->same(3, $summary['relationshipSourceCaseFoldTopLevelSegmentCount']);
        $t->same([
            '(package-root)' => 1,
            'customxml' => 1,
            'word' => 3,
        ], $summary['relationshipSourceCaseFoldTopLevelSegmentCounts']);
        $t->same([
            '(package-root)' => 1,
            'customxml' => 1,
            'word' => 2,
        ], $summary['relationshipSourceExistingCaseFoldTopLevelSegmentCounts']);
        $t->same(['word' => 1], $summary['relationshipSourceNonExistingCaseFoldTopLevelSegmentCounts']);
        $t->same(1, $summary['duplicateRelationshipSourceCaseFoldTopLevelSegmentCount']);
        $t->same(3, $summary['duplicateRelationshipSourceCaseFoldTopLevelSegmentSourceCount']);
        $t->same(3, $summary['duplicateRelationshipSourceCaseFoldTopLevelSegmentRelationshipCount']);
        $t->same(['word'], $summary['duplicateRelationshipSourceCaseFoldTopLevelSegments']);
        $t->same(['(package-root)', 'customxml', 'word'], array_column($summary['relationshipSourceCaseFoldTopLevelSegments'], 'sourceCaseFoldTopLevelSegmentKey'));

        $word = $groups['word'];
        $t->same('word', $word['sourceCaseFoldTopLevelSegment']);
        $t->same(3, $word['topLevelSegmentVariantCount']);
        $t->same(3, $word['sourceCount']);
        $t->same(2, $word['existingSourceCount']);
        $t->same(1, $word['nonExistingSourceCount']);
        $t->same(1, $word['missingContentTypeSourceCount']);
        $t->same(0, $word['parameterizedSourceCount']);
        $t->same(3, $word['relationshipCount']);
        $t->same(3, $word['relationshipRecordCount']);
        $t->same(strlen($parts['word/document.xml']) + strlen($parts['Word/document.xml']), $word['existingSourceByteLength']);
        $t->same(['WORD' => 1, 'Word' => 1, 'word' => 1], $word['topLevelSegmentCounts']);
        $t->same(['2' => 3], $word['sourcePathDepthCounts']);
        $t->same(['1' => 3], $word['sourceDirectoryDepthCounts']);
        $t->same(['missing-source' => 1, 'package-part' => 2], $word['relationshipSourceKindCounts']);
        $t->same([
            'WORD' => 1,
            'Word' => 1,
            'word' => 1,
        ], $word['sourceDirectoryCounts']);
        $t->same(['document.xml' => 2, 'missing.xml' => 1], $word['sourceBaseNameCounts']);
        $t->same(['xml' => 3], $word['sourcePartExtensionCounts']);
        $t->same([
            '(missing)' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'application/xml' => 1,
        ], $word['sourceContentTypeBaseCounts']);
        $t->same([
            '(missing)' => 1,
            'default' => 1,
            'override' => 1,
        ], $word['sourceContentTypeSourceCounts']);
        $t->same([
            'office-document' => 1,
            'package-part' => 1,
            'root-relationship-target' => 1,
        ], $word['sourceRoleCounts']);
        $t->same([
            'WORD/missing.xml',
            'Word/document.xml',
            'word/document.xml',
        ], $word['sourceParts']);
        $t->same([
            'Word/document.xml',
            'word/document.xml',
        ], $word['existingSourceParts']);
        $t->same(['WORD/missing.xml'], $word['nonExistingSourceParts']);
        $t->same([
            'WORD/_rels/missing.xml.rels',
            'Word/_rels/document.xml.rels',
            'word/_rels/document.xml.rels',
        ], $word['relationshipParts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'application/xml',
        ], $word['contentTypes']);
        $t->same('Word/document.xml', $word['largestExistingSourcePart']['sourcePart']);
        $t->same('Word', $word['largestExistingSourcePart']['sourceTopLevelSegment']);
        $t->same('word', $word['largestExistingSourcePart']['sourceCaseFoldTopLevelSegment']);
        $t->same(['Word', 'document.xml'], $word['largestExistingSourcePart']['sourcePathSegments']);
        $t->same(['word', 'document.xml'], $word['largestExistingSourcePart']['caseFoldPathSegments']);
        $t->same('Word', $word['largestExistingSourcePart']['sourceDirectory']);
        $t->same('document.xml', $word['largestExistingSourcePart']['sourceBaseName']);
        $t->same('xml', $word['largestExistingSourcePart']['sourcePartExtension']);
        $t->same(strlen($parts['Word/document.xml']), $word['largestExistingSourcePart']['sourceBytes']);
        $t->same(hash('sha256', $parts['Word/document.xml']), $word['largestExistingSourcePart']['sourceSha256']);
        $t->same('application/xml', $word['largestExistingSourcePart']['sourceContentTypeBase']);
        $t->same('default', $word['largestExistingSourcePart']['sourceContentTypeSource']);
        $t->same(['package-part'], $word['largestExistingSourcePart']['sourceRoles']);
        $t->same(1, $word['largestExistingSourcePart']['relationshipCount']);

        $root = $groups['(package-root)'];
        $t->same(null, $root['sourceCaseFoldTopLevelSegment']);
        $t->same(1, $root['sourceCount']);
        $t->same(1, $root['existingSourceCount']);
        $t->same(['/' => 1], $root['topLevelSegmentCounts']);
        $t->same(['package-root' => 1], $root['relationshipSourceKindCounts']);
        $t->same(['/'], $root['sourceParts']);
        $t->same(['_rels/.rels'], $root['relationshipParts']);

        $customXml = $groups['customxml'];
        $t->same('customxml', $customXml['sourceCaseFoldTopLevelSegment']);
        $t->same(1, $customXml['sourceCount']);
        $t->same(1, $customXml['existingSourceCount']);
        $t->same(['customXml' => 1], $customXml['topLevelSegmentCounts']);
        $t->same(['customXml/item.xml'], $customXml['sourceParts']);
        $t->same('customXml/item.xml', $customXml['largestExistingSourcePart']['sourcePart']);
        $t->same('customXml', $customXml['largestExistingSourcePart']['sourceTopLevelSegment']);
        $t->same('customxml', $customXml['largestExistingSourcePart']['sourceCaseFoldTopLevelSegment']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_source_casefold_top_level_segment_fixture_parts(): array
{
    $lowerDocument = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Lower source.</w:t></w:r></w:p></w:body>
</w:document>
XML;
    $upperDocument = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body><w:p><w:r><w:t>'
        . str_repeat('Upper source payload. ', 40)
        . '</w:t></w:r></w:p></w:body></w:document>';

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
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
        'Word/document.xml' => $upperDocument,
        'customXml/item.xml' => '<item>' . str_repeat('custom xml source ', 3) . '</item>',
        'word/media/lower.png' => 'lower image',
        'word/media/upper.png' => 'upper image',
        'word/media/missing-source.png' => 'missing source target',
        'word/media/custom.png' => 'custom source target',
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLowerImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/lower.png"/>
</Relationships>
XML,
        'Word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../word/media/upper.png"/>
</Relationships>
XML,
        'WORD/_rels/missing.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../word/media/missing-source.png"/>
</Relationships>
XML,
        'customXml/_rels/item.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCustomImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../word/media/custom.png"/>
</Relationships>
XML,
    ];
}
