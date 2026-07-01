<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package part case-fold directory base names for review handoff' => static function (TestRunner $t): void {
        $parts = docx_casefold_directory_base_name_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $groups = [];
        foreach ($summary['partCaseFoldDirectoryBaseNames'] as $group) {
            $groups[$group['caseFoldDirectoryBaseName']] = $group;
        }

        $t->same(4, $summary['partCaseFoldDirectoryBaseNameCount']);
        $t->same([
            '/' => 1,
            '_rels' => 2,
            'media' => 4,
            'word' => 1,
        ], $summary['partCaseFoldDirectoryBaseNameCounts']);
        $t->same(2, $summary['duplicatePartCaseFoldDirectoryBaseNameCount']);
        $t->same(['_rels', 'media'], $summary['duplicatePartCaseFoldDirectoryBaseNames']);
        $t->same(['/', '_rels', 'media', 'word'], array_column($summary['partCaseFoldDirectoryBaseNames'], 'caseFoldDirectoryBaseName'));
        $t->same('media', $inventory['word/Media/review.png']['caseFoldDirectoryBaseName']);
        $t->same('media', $inventory['word/media/second.png']['caseFoldDirectoryBaseName']);
        $t->same('media', $inventory['customXml/MEDIA/data.xml']['caseFoldDirectoryBaseName']);
        $t->same('media', $inventory['customXml/media/raw.bin']['caseFoldDirectoryBaseName']);

        $media = $groups['media'];
        $t->same(3, $media['directoryBaseNameVariantCount']);
        $t->same(4, $media['directoryCount']);
        $t->same(4, $media['partCount']);
        $t->same(
            strlen($parts['word/Media/review.png'])
                + strlen($parts['word/media/second.png'])
                + strlen($parts['customXml/MEDIA/data.xml'])
                + strlen($parts['customXml/media/raw.bin']),
            $media['byteLength']
        );
        $t->same(0, $media['relationshipPartCount']);
        $t->same(1, $media['missingContentTypePartCount']);
        $t->same(0, $media['parameterizedPartCount']);
        $t->same(['MEDIA' => 1, 'Media' => 1, 'media' => 2], $media['directoryBaseNameCounts']);
        $t->same([2 => 4], $media['directoryDepthCounts']);
        $t->same(['customXml' => 2, 'word' => 2], $media['topLevelSegmentCounts']);
        $t->same(['customXml/MEDIA', 'customXml/media', 'word/Media', 'word/media'], $media['directories']);
        $t->same([
            'customXml/MEDIA/data.xml',
            'customXml/media/raw.bin',
            'word/Media/review.png',
            'word/media/second.png',
        ], $media['partNames']);
        $t->same(['default' => 3, 'missing' => 1], $media['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            'application/xml' => 1,
            'image/png' => 2,
        ], $media['contentTypeBaseCounts']);
        $t->same([
            'document-relationship-target' => 2,
            'package-part' => 2,
        ], $media['roleCounts']);
        $t->same('word/media/second.png', $media['largestPart']['partName']);
        $t->same('word/media', $media['largestPart']['directory']);
        $t->same('media', $media['largestPart']['directoryBaseName']);
        $t->same('media', $media['largestPart']['caseFoldDirectoryBaseName']);
        $t->same(2, $media['largestPart']['directoryDepth']);
        $t->same('word', $media['largestPart']['topLevelSegment']);
        $t->same('second.png', $media['largestPart']['baseName']);
        $t->same(47, $media['largestPart']['bytes']);
        $t->same(hash('sha256', $parts['word/media/second.png']), $media['largestPart']['sha256']);
        $t->same('image/png', $media['largestPart']['contentTypeBase']);
        $t->same('default', $media['largestPart']['contentTypeSource']);
        $t->same(['document-relationship-target'], $media['largestPart']['roles']);

        $rels = $groups['_rels'];
        $t->same(1, $rels['directoryBaseNameVariantCount']);
        $t->same(2, $rels['directoryCount']);
        $t->same(2, $rels['partCount']);
        $t->same(['_rels' => 2], $rels['directoryBaseNameCounts']);
        $t->same([1 => 1, 2 => 1], $rels['directoryDepthCounts']);
        $t->same(['_rels', 'word/_rels'], $rels['directories']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $rels['partNames']);
        $t->same(['default' => 2], $rels['contentTypeSourceCounts']);
        $t->same(['application/vnd.openxmlformats-package.relationships+xml' => 2], $rels['contentTypeBaseCounts']);
        $t->same([
            'office-document-relationships' => 1,
            'package-relationships' => 1,
            'relationship-part' => 2,
        ], $rels['roleCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_casefold_directory_base_name_fixture_parts(): array
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
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Case-fold directory base-name fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/Media/review.png' => 'mixed case media bytes',
        'word/media/second.png' => str_repeat('S', 47),
        'customXml/MEDIA/data.xml' => '<data/>',
        'customXml/media/raw.bin' => 'missing raw bytes',
    ];
}
