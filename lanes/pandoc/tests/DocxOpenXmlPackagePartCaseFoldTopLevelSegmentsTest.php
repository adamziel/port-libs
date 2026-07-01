<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package part case-fold top-level segments for review handoff' => static function (TestRunner $t): void {
        $parts = docx_casefold_top_level_segment_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['partCaseFoldTopLevelSegments'] as $group) {
            $groups[$group['caseFoldTopLevelSegment']] = $group;
        }

        $t->same(6, $summary['partTopLevelSegmentCount']);
        $t->same(4, $summary['partCaseFoldTopLevelSegmentCount']);
        $t->same([
            '[content_types].xml' => 1,
            '_rels' => 1,
            'customxml' => 1,
            'word' => 6,
        ], $summary['partCaseFoldTopLevelSegmentCounts']);
        $t->same(1, $summary['duplicatePartCaseFoldTopLevelSegmentCount']);
        $t->same(6, $summary['duplicatePartCaseFoldTopLevelSegmentPartCount']);
        $t->same(['word'], $summary['duplicatePartCaseFoldTopLevelSegments']);
        $t->same([
            '[content_types].xml',
            '_rels',
            'customxml',
            'word',
        ], array_column($summary['partCaseFoldTopLevelSegments'], 'caseFoldTopLevelSegment'));

        $word = $groups['word'];
        $t->same(3, $word['topLevelSegmentVariantCount']);
        $t->same(6, $word['partCount']);
        $t->same(
            strlen($parts['word/document.xml'])
                + strlen($parts['word/_rels/document.xml.rels'])
                + strlen($parts['word/media/lower.png'])
                + strlen($parts['Word/media/upper.png'])
                + strlen($parts['WORD/media/caps.png'])
                + strlen($parts['Word/media/raw.bin']),
            $word['byteLength']
        );
        $t->same(1, $word['relationshipPartCount']);
        $t->same(1, $word['missingContentTypePartCount']);
        $t->same(0, $word['parameterizedPartCount']);
        $t->same(['WORD' => 1, 'Word' => 2, 'word' => 3], $word['topLevelSegmentCounts']);
        $t->same([2 => 1, 3 => 5], $word['pathDepthCounts']);
        $t->same([
            'WORD/media' => 1,
            'Word/media' => 2,
            'word' => 1,
            'word/_rels' => 1,
            'word/media' => 1,
        ], $word['directoryCounts']);
        $t->same([
            'WORD/media',
            'Word/media',
            'word',
            'word/_rels',
            'word/media',
        ], $word['directories']);
        $t->same(['default' => 4, 'missing' => 1, 'override' => 1], $word['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'application/vnd.openxmlformats-package.relationships+xml' => 1,
            'image/png' => 3,
        ], $word['contentTypeBaseCounts']);
        $t->same([
            'document-relationship-target' => 3,
            'office-document' => 1,
            'office-document-relationships' => 1,
            'package-part' => 1,
            'relationship-part' => 1,
            'root-relationship-target' => 1,
        ], $word['roleCounts']);
        $t->same([
            'WORD/media/caps.png',
            'Word/media/raw.bin',
            'Word/media/upper.png',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/lower.png',
        ], $word['partNames']);
        $t->same('Word/media/raw.bin', $word['largestPart']['partName']);
        $t->same('Word', $word['largestPart']['topLevelSegment']);
        $t->same('word', $word['largestPart']['caseFoldTopLevelSegment']);
        $t->same('Word/media', $word['largestPart']['directory']);
        $t->same('raw.bin', $word['largestPart']['baseName']);
        $t->same(3, $word['largestPart']['pathSegmentCount']);
        $t->same(['Word', 'media', 'raw.bin'], $word['largestPart']['pathSegments']);
        $t->same(800, $word['largestPart']['bytes']);
        $t->same(hash('sha256', $parts['Word/media/raw.bin']), $word['largestPart']['sha256']);
        $t->same('', $word['largestPart']['contentType']);
        $t->same('', $word['largestPart']['contentTypeBase']);
        $t->same('missing', $word['largestPart']['contentTypeSource']);
        $t->same(false, $word['largestPart']['isRelationshipPart']);
        $t->same(['package-part'], $word['largestPart']['roles']);

        $rels = $groups['_rels'];
        $t->same(1, $rels['topLevelSegmentVariantCount']);
        $t->same(1, $rels['partCount']);
        $t->same(1, $rels['relationshipPartCount']);
        $t->same(['_rels' => 1], $rels['topLevelSegmentCounts']);
        $t->same([2 => 1], $rels['pathDepthCounts']);
        $t->same(['_rels' => 1], $rels['directoryCounts']);
        $t->same(['_rels'], $rels['directories']);
        $t->same(['default' => 1], $rels['contentTypeSourceCounts']);
        $t->same(['application/vnd.openxmlformats-package.relationships+xml' => 1], $rels['contentTypeBaseCounts']);
        $t->same(['package-relationships' => 1, 'relationship-part' => 1], $rels['roleCounts']);
        $t->same(['_rels/.rels'], $rels['partNames']);
        $t->same('_rels/.rels', $rels['largestPart']['partName']);
        $t->same('_rels', $rels['largestPart']['caseFoldTopLevelSegment']);
        $t->same(true, $rels['largestPart']['isRelationshipPart']);
    },

    'records mapped DOCX package case-fold top-level segment case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
];

/**
 * @return array<string, string>
 */
function docx_casefold_top_level_segment_fixture_parts(): array
{
    return [
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
  <Relationship Id="rLowerMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/lower.png"/>
  <Relationship Id="rUpperWordMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../Word/media/upper.png"/>
  <Relationship Id="rAllCapsWordMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../WORD/media/caps.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Case-fold top-level segment fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/media/lower.png' => 'lower word media bytes',
        'Word/media/upper.png' => 'upper word media bytes',
        'WORD/media/caps.png' => str_repeat('C', 33),
        'Word/media/raw.bin' => str_repeat('R', 800),
        'customXml/data.xml' => '<data/>',
    ];
}
