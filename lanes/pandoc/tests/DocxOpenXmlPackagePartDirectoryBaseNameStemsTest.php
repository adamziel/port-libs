<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package part directory base-name stems for review handoff' => static function (TestRunner $t): void {
        $parts = docx_directory_base_name_stem_fixture_parts();

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $byStem = [];
        foreach ($summary['partDirectoryBaseNameStems'] as $stem) {
            $byStem[$stem['directoryBaseNameStem']] = $stem;
        }
        $byCaseFoldStem = [];
        foreach ($summary['partCaseFoldDirectoryBaseNameStems'] as $stem) {
            $byCaseFoldStem[$stem['caseFoldDirectoryBaseNameStem']] = $stem;
        }

        $t->same(5, $summary['partDirectoryBaseNameStemCount']);
        $t->same([
            '/' => 1,
            'MEDIA' => 1,
            '_rels' => 2,
            'media' => 3,
            'word' => 1,
        ], $summary['partDirectoryBaseNameStemCounts']);
        $t->same(2, $summary['duplicatePartDirectoryBaseNameStemCount']);
        $t->same(['_rels', 'media'], $summary['duplicatePartDirectoryBaseNameStems']);
        $t->same(['/', 'MEDIA', '_rels', 'media', 'word'], array_column($summary['partDirectoryBaseNameStems'], 'directoryBaseNameStem'));
        $t->same('media', $inventory['word/media.assets/review.png']['directoryBaseNameStem']);
        $t->same('MEDIA', $inventory['word/MEDIA.assets/upper.png']['directoryBaseNameStem']);
        $t->same('media', $inventory['customXml/media.raw/data.xml']['directoryBaseNameStem']);
        $t->same('media', $inventory['customXml/media/raw.bin']['directoryBaseNameStem']);

        $media = $byStem['media'];
        $t->same(3, $media['directoryBaseNameVariantCount']);
        $t->same(3, $media['directoryCount']);
        $t->same(3, $media['partCount']);
        $t->same(
            strlen($parts['word/media.assets/review.png'])
                + strlen($parts['customXml/media.raw/data.xml'])
                + strlen($parts['customXml/media/raw.bin']),
            $media['byteLength']
        );
        $t->same(0, $media['relationshipPartCount']);
        $t->same(1, $media['missingContentTypePartCount']);
        $t->same(0, $media['parameterizedPartCount']);
        $t->same(['media' => 1, 'media.assets' => 1, 'media.raw' => 1], $media['directoryBaseNameCounts']);
        $t->same([2 => 3], $media['directoryDepthCounts']);
        $t->same(['customXml' => 2, 'word' => 1], $media['topLevelSegmentCounts']);
        $t->same(['customXml/media', 'customXml/media.raw', 'word/media.assets'], $media['directories']);
        $t->same([
            'customXml/media.raw/data.xml',
            'customXml/media/raw.bin',
            'word/media.assets/review.png',
        ], $media['partNames']);
        $t->same(['default' => 2, 'missing' => 1], $media['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            'application/xml' => 1,
            'image/png' => 1,
        ], $media['contentTypeBaseCounts']);
        $t->same([
            'document-relationship-target' => 1,
            'package-part' => 2,
        ], $media['roleCounts']);
        $t->same('word/media.assets/review.png', $media['largestPart']['partName']);
        $t->same('word/media.assets', $media['largestPart']['directory']);
        $t->same('media.assets', $media['largestPart']['directoryBaseName']);
        $t->same('media', $media['largestPart']['directoryBaseNameStem']);
        $t->same(2, $media['largestPart']['directoryDepth']);
        $t->same('word', $media['largestPart']['topLevelSegment']);
        $t->same('review.png', $media['largestPart']['baseName']);
        $t->same(31, $media['largestPart']['bytes']);
        $t->same(hash('sha256', $parts['word/media.assets/review.png']), $media['largestPart']['sha256']);
        $t->same('image/png', $media['largestPart']['contentTypeBase']);
        $t->same('default', $media['largestPart']['contentTypeSource']);
        $t->same(['document-relationship-target'], $media['largestPart']['roles']);

        $t->same(4, $summary['partCaseFoldDirectoryBaseNameStemCount']);
        $t->same([
            '/' => 1,
            '_rels' => 2,
            'media' => 4,
            'word' => 1,
        ], $summary['partCaseFoldDirectoryBaseNameStemCounts']);
        $t->same(2, $summary['duplicatePartCaseFoldDirectoryBaseNameStemCount']);
        $t->same(['_rels', 'media'], $summary['duplicatePartCaseFoldDirectoryBaseNameStems']);
        $t->same(['/', '_rels', 'media', 'word'], array_column($summary['partCaseFoldDirectoryBaseNameStems'], 'caseFoldDirectoryBaseNameStem'));
        $t->same('media', $inventory['word/media.assets/review.png']['caseFoldDirectoryBaseNameStem']);
        $t->same('media', $inventory['word/MEDIA.assets/upper.png']['caseFoldDirectoryBaseNameStem']);
        $t->same('media', $inventory['customXml/media.raw/data.xml']['caseFoldDirectoryBaseNameStem']);
        $t->same('media', $inventory['customXml/media/raw.bin']['caseFoldDirectoryBaseNameStem']);

        $caseFoldMedia = $byCaseFoldStem['media'];
        $t->same(2, $caseFoldMedia['directoryBaseNameStemVariantCount']);
        $t->same(4, $caseFoldMedia['directoryBaseNameVariantCount']);
        $t->same(4, $caseFoldMedia['directoryCount']);
        $t->same(4, $caseFoldMedia['partCount']);
        $t->same(
            strlen($parts['word/media.assets/review.png'])
                + strlen($parts['word/MEDIA.assets/upper.png'])
                + strlen($parts['customXml/media.raw/data.xml'])
                + strlen($parts['customXml/media/raw.bin']),
            $caseFoldMedia['byteLength']
        );
        $t->same(['MEDIA' => 1, 'media' => 3], $caseFoldMedia['directoryBaseNameStemCounts']);
        $t->same([
            'MEDIA.assets' => 1,
            'media' => 1,
            'media.assets' => 1,
            'media.raw' => 1,
        ], $caseFoldMedia['directoryBaseNameCounts']);
        $t->same([2 => 4], $caseFoldMedia['directoryDepthCounts']);
        $t->same(['customXml' => 2, 'word' => 2], $caseFoldMedia['topLevelSegmentCounts']);
        $t->same([
            'customXml/media',
            'customXml/media.raw',
            'word/MEDIA.assets',
            'word/media.assets',
        ], $caseFoldMedia['directories']);
        $t->same([
            'customXml/media.raw/data.xml',
            'customXml/media/raw.bin',
            'word/MEDIA.assets/upper.png',
            'word/media.assets/review.png',
        ], $caseFoldMedia['partNames']);
        $t->same(['default' => 3, 'missing' => 1], $caseFoldMedia['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            'application/xml' => 1,
            'image/png' => 2,
        ], $caseFoldMedia['contentTypeBaseCounts']);
        $t->same([
            'document-relationship-target' => 2,
            'package-part' => 2,
        ], $caseFoldMedia['roleCounts']);
        $t->same('word/MEDIA.assets/upper.png', $caseFoldMedia['largestPart']['partName']);
        $t->same('word/MEDIA.assets', $caseFoldMedia['largestPart']['directory']);
        $t->same('MEDIA.assets', $caseFoldMedia['largestPart']['directoryBaseName']);
        $t->same('MEDIA', $caseFoldMedia['largestPart']['directoryBaseNameStem']);
        $t->same('media', $caseFoldMedia['largestPart']['caseFoldDirectoryBaseNameStem']);
        $t->same(43, $caseFoldMedia['largestPart']['bytes']);
        $t->same(hash('sha256', $parts['word/MEDIA.assets/upper.png']), $caseFoldMedia['largestPart']['sha256']);
        $t->same('image/png', $caseFoldMedia['largestPart']['contentTypeBase']);
        $t->same('default', $caseFoldMedia['largestPart']['contentTypeSource']);
        $t->same(['document-relationship-target'], $caseFoldMedia['largestPart']['roles']);

        $rels = $byCaseFoldStem['_rels'];
        $t->same(1, $rels['directoryBaseNameStemVariantCount']);
        $t->same(2, $rels['directoryCount']);
        $t->same(2, $rels['partCount']);
        $t->same(['_rels' => 2], $rels['directoryBaseNameStemCounts']);
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
function docx_directory_base_name_stem_fixture_parts(): array
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
  <Relationship Id="rMediaStem" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media.assets/review.png"/>
  <Relationship Id="rUpperMediaStem" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="MEDIA.assets/upper.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Directory basename stem fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/media.assets/review.png' => str_repeat('R', 31),
        'word/MEDIA.assets/upper.png' => str_repeat('U', 43),
        'customXml/media.raw/data.xml' => '<data/>',
        'customXml/media/raw.bin' => 'raw bytes',
    ];
}
