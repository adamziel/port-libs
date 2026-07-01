<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package part case-fold path segments for review handoff' => static function (TestRunner $t): void {
        $parts = docx_casefold_path_segment_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['partCaseFoldPathSegments'] as $group) {
            $groups[$group['caseFoldSegment']] = $group;
        }

        $t->same(13, $summary['partCaseFoldPathSegmentCount']);
        $t->same(23, $summary['partCaseFoldPathSegmentOccurrenceCount']);
        $t->same([
            '.rels' => 1,
            '[content_types].xml' => 1,
            '_rels' => 2,
            'customxml' => 2,
            'data.xml' => 1,
            'document.xml' => 1,
            'document.xml.rels' => 1,
            'media' => 5,
            'raw.bin' => 1,
            'review.png' => 1,
            'second.png' => 1,
            'third.png' => 1,
            'word' => 5,
        ], $summary['partCaseFoldPathSegmentCounts']);
        $t->same(1, $summary['duplicatePartCaseFoldPathSegmentCount']);
        $t->same(5, $summary['duplicatePartCaseFoldPathSegmentPartCount']);
        $t->same(5, $summary['duplicatePartCaseFoldPathSegmentOccurrenceCount']);
        $t->same(['media'], $summary['duplicatePartCaseFoldPathSegments']);
        $t->same([
            '.rels',
            '[content_types].xml',
            '_rels',
            'customxml',
            'data.xml',
            'document.xml',
            'document.xml.rels',
            'media',
            'raw.bin',
            'review.png',
            'second.png',
            'third.png',
            'word',
        ], array_column($summary['partCaseFoldPathSegments'], 'caseFoldSegment'));

        $media = $groups['media'];
        $t->same(3, $media['segmentVariantCount']);
        $t->same(5, $media['occurrenceCount']);
        $t->same(5, $media['partCount']);
        $t->same(
            strlen($parts['word/Media/review.png'])
                + strlen($parts['word/media/second.png'])
                + strlen($parts['word/MEDIA/third.png'])
                + strlen($parts['customXml/MEDIA/data.xml'])
                + strlen($parts['customXml/media/raw.bin']),
            $media['byteLength']
        );
        $t->same(0, $media['relationshipPartCount']);
        $t->same(1, $media['missingContentTypePartCount']);
        $t->same(0, $media['parameterizedPartCount']);
        $t->same(['MEDIA' => 2, 'Media' => 1, 'media' => 2], $media['segmentCounts']);
        $t->same([1 => 5], $media['pathSegmentIndexCounts']);
        $t->same([3 => 5], $media['pathDepthCounts']);
        $t->same(['customXml' => 2, 'word' => 3], $media['topLevelSegmentCounts']);
        $t->same([
            'customXml/MEDIA' => 1,
            'customXml/media' => 1,
            'word/MEDIA' => 1,
            'word/Media' => 1,
            'word/media' => 1,
        ], $media['directoryCounts']);
        $t->same([
            'customXml/MEDIA',
            'customXml/media',
            'word/MEDIA',
            'word/Media',
            'word/media',
        ], $media['directories']);
        $t->same(['default' => 4, 'missing' => 1], $media['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            'application/xml' => 1,
            'image/png' => 3,
        ], $media['contentTypeBaseCounts']);
        $t->same([
            'document-relationship-target' => 3,
            'package-part' => 2,
        ], $media['roleCounts']);
        $t->same([
            'customXml/MEDIA/data.xml',
            'customXml/media/raw.bin',
            'word/MEDIA/third.png',
            'word/Media/review.png',
            'word/media/second.png',
        ], $media['partNames']);
        $t->same('word/media/second.png', $media['largestPart']['partName']);
        $t->same('word/media', $media['largestPart']['directory']);
        $t->same('second.png', $media['largestPart']['baseName']);
        $t->same(3, $media['largestPart']['pathSegmentCount']);
        $t->same(['word', 'media', 'second.png'], $media['largestPart']['pathSegments']);
        $t->same(['word', 'media', 'second.png'], $media['largestPart']['caseFoldPathSegments']);
        $t->same('word', $media['largestPart']['topLevelSegment']);
        $t->same(47, $media['largestPart']['bytes']);
        $t->same(hash('sha256', $parts['word/media/second.png']), $media['largestPart']['sha256']);
        $t->same('image/png', $media['largestPart']['contentType']);
        $t->same('image/png', $media['largestPart']['contentTypeBase']);
        $t->same('default', $media['largestPart']['contentTypeSource']);
        $t->same(false, $media['largestPart']['isRelationshipPart']);
        $t->same(['document-relationship-target'], $media['largestPart']['roles']);

        $rels = $groups['_rels'];
        $t->same(1, $rels['segmentVariantCount']);
        $t->same(2, $rels['occurrenceCount']);
        $t->same(2, $rels['partCount']);
        $t->same(2, $rels['relationshipPartCount']);
        $t->same(['_rels' => 2], $rels['segmentCounts']);
        $t->same([0 => 1, 1 => 1], $rels['pathSegmentIndexCounts']);
        $t->same([2 => 1, 3 => 1], $rels['pathDepthCounts']);
        $t->same(['_rels' => 1, 'word' => 1], $rels['topLevelSegmentCounts']);
        $t->same(['_rels' => 1, 'word/_rels' => 1], $rels['directoryCounts']);
        $t->same(['_rels', 'word/_rels'], $rels['directories']);
        $t->same(['default' => 2], $rels['contentTypeSourceCounts']);
        $t->same(['application/vnd.openxmlformats-package.relationships+xml' => 2], $rels['contentTypeBaseCounts']);
        $t->same([
            'office-document-relationships' => 1,
            'package-relationships' => 1,
            'relationship-part' => 2,
        ], $rels['roleCounts']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $rels['partNames']);
        $t->same('word/_rels/document.xml.rels', $rels['largestPart']['partName']);
        $t->same(true, $rels['largestPart']['isRelationshipPart']);
        $t->same(['office-document-relationships', 'relationship-part'], $rels['largestPart']['roles']);
    },

    'records mapped DOCX package case-fold path segment case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
];

/**
 * @return array<string, string>
 */
function docx_casefold_path_segment_fixture_parts(): array
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
  <Relationship Id="rMixedCaseMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Media/review.png"/>
  <Relationship Id="rLowerMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/second.png"/>
  <Relationship Id="rUpperMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="MEDIA/third.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Case-fold path segment fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/Media/review.png' => 'mixed case media bytes',
        'word/media/second.png' => str_repeat('S', 47),
        'word/MEDIA/third.png' => str_repeat('T', 33),
        'customXml/MEDIA/data.xml' => '<data/>',
        'customXml/media/raw.bin' => 'missing raw bytes',
    ];
}
