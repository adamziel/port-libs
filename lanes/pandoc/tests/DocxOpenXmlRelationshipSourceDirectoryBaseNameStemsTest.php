<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source directory base-name stems for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_source_directory_base_name_stem_fixture_parts();

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $stems = [];
        foreach ($summary['relationshipSourceDirectoryBaseNameStems'] as $stem) {
            $stems[$stem['sourceDirectoryBaseNameStemKey']] = $stem;
        }
        $caseFoldStems = [];
        foreach ($summary['relationshipSourceCaseFoldDirectoryBaseNameStems'] as $stem) {
            $caseFoldStems[$stem['sourceCaseFoldDirectoryBaseNameStemKey']] = $stem;
        }

        $t->same(6, $summary['relationshipSourceCount']);
        $t->same(5, $summary['relationshipSourceDirectoryBaseNameStemCount']);
        $t->same([
            '/' => 1,
            'MEDIA' => 2,
            'Media' => 1,
            'media' => 1,
            'word' => 1,
        ], $summary['relationshipSourceDirectoryBaseNameStemCounts']);
        $t->same([
            '/' => 1,
            'MEDIA' => 1,
            'Media' => 1,
            'media' => 1,
            'word' => 1,
        ], $summary['relationshipSourceExistingDirectoryBaseNameStemCounts']);
        $t->same(['MEDIA' => 1], $summary['relationshipSourceNonExistingDirectoryBaseNameStemCounts']);
        $t->same(1, $summary['duplicateRelationshipSourceDirectoryBaseNameStemCount']);
        $t->same(['MEDIA'], $summary['duplicateRelationshipSourceDirectoryBaseNameStems']);

        $t->same(3, $summary['relationshipSourceCaseFoldDirectoryBaseNameStemCount']);
        $t->same([
            '/' => 1,
            'media' => 4,
            'word' => 1,
        ], $summary['relationshipSourceCaseFoldDirectoryBaseNameStemCounts']);
        $t->same([
            '/' => 1,
            'media' => 3,
            'word' => 1,
        ], $summary['relationshipSourceExistingCaseFoldDirectoryBaseNameStemCounts']);
        $t->same(['media' => 1], $summary['relationshipSourceNonExistingCaseFoldDirectoryBaseNameStemCounts']);
        $t->same(1, $summary['duplicateRelationshipSourceCaseFoldDirectoryBaseNameStemCount']);
        $t->same(['media'], $summary['duplicateRelationshipSourceCaseFoldDirectoryBaseNameStems']);
        $t->same(['/', 'media', 'word'], array_column($summary['relationshipSourceCaseFoldDirectoryBaseNameStems'], 'sourceCaseFoldDirectoryBaseNameStem'));

        $media = $caseFoldStems['media'];
        $t->same('media', $media['sourceCaseFoldDirectoryBaseNameStem']);
        $t->same(3, $media['directoryBaseNameStemVariantCount']);
        $t->same(4, $media['directoryBaseNameVariantCount']);
        $t->same(4, $media['sourceDirectoryCount']);
        $t->same(4, $media['sourceCount']);
        $t->same(3, $media['existingSourceCount']);
        $t->same(1, $media['nonExistingSourceCount']);
        $t->same(1, $media['missingContentTypeSourceCount']);
        $t->same(0, $media['parameterizedSourceCount']);
        $t->same(5, $media['relationshipCount']);
        $t->same(5, $media['relationshipRecordCount']);
        $t->same(
            strlen($parts['word/Media.assets/source.xml'])
                + strlen($parts['word/media.raw/source.bin'])
                + strlen($parts['word/other/MEDIA/source.xml']),
            $media['existingSourceByteLength']
        );
        $t->same(['MEDIA' => 2, 'Media' => 1, 'media' => 1], $media['directoryBaseNameStemCounts']);
        $t->same([
            'MEDIA' => 1,
            'MEDIA.tmp' => 1,
            'Media.assets' => 1,
            'media.raw' => 1,
        ], $media['directoryBaseNameCounts']);
        $t->same([
            'word/MEDIA.tmp' => 1,
            'word/Media.assets' => 1,
            'word/media.raw' => 1,
            'word/other/MEDIA' => 1,
        ], $media['sourceDirectoryCounts']);
        $t->same(['3' => 3, '4' => 1], $media['sourcePathDepthCounts']);
        $t->same(['2' => 3, '3' => 1], $media['sourceDirectoryDepthCounts']);
        $t->same(['missing-source.xml' => 1, 'source.bin' => 1, 'source.xml' => 2], $media['sourceBaseNameCounts']);
        $t->same(['bin' => 1, 'xml' => 3], $media['sourcePartExtensionCounts']);
        $t->same(['missing-source' => 1, 'package-part' => 3], $media['relationshipSourceKindCounts']);
        $t->same([
            '(missing)' => 1,
            'application/octet-stream' => 1,
            'application/xml' => 2,
        ], $media['sourceContentTypeBaseCounts']);
        $t->same(['(missing)' => 1, 'default' => 3], $media['sourceContentTypeSourceCounts']);
        $t->same(['package-part' => 3], $media['sourceRoleCounts']);
        $t->same([
            'word/MEDIA.tmp',
            'word/Media.assets',
            'word/media.raw',
            'word/other/MEDIA',
        ], $media['sourceDirectories']);
        $t->same([
            'word/MEDIA.tmp/missing-source.xml',
            'word/Media.assets/source.xml',
            'word/media.raw/source.bin',
            'word/other/MEDIA/source.xml',
        ], $media['sourceParts']);
        $t->same([
            'word/Media.assets/source.xml',
            'word/media.raw/source.bin',
            'word/other/MEDIA/source.xml',
        ], $media['existingSourceParts']);
        $t->same(['word/MEDIA.tmp/missing-source.xml'], $media['nonExistingSourceParts']);
        $t->same([
            'word/MEDIA.tmp/_rels/missing-source.xml.rels',
            'word/Media.assets/_rels/source.xml.rels',
            'word/media.raw/_rels/source.bin.rels',
            'word/other/MEDIA/_rels/source.xml.rels',
        ], $media['relationshipParts']);
        $t->same(['application/octet-stream', 'application/xml'], $media['contentTypes']);
        $t->same('word/other/MEDIA/source.xml', $media['largestExistingSourcePart']['sourcePart']);
        $t->same('word/other/MEDIA', $media['largestExistingSourcePart']['sourceDirectory']);
        $t->same('MEDIA', $media['largestExistingSourcePart']['sourceDirectoryBaseName']);
        $t->same('MEDIA', $media['largestExistingSourcePart']['sourceDirectoryBaseNameStem']);
        $t->same('media', $media['largestExistingSourcePart']['sourceCaseFoldDirectoryBaseNameStem']);
        $t->same(3, $media['largestExistingSourcePart']['sourceDirectoryDepth']);
        $t->same(4, $media['largestExistingSourcePart']['sourcePathDepth']);
        $t->same('source.xml', $media['largestExistingSourcePart']['sourceBaseName']);
        $t->same('xml', $media['largestExistingSourcePart']['sourcePartExtension']);
        $t->same(strlen($parts['word/other/MEDIA/source.xml']), $media['largestExistingSourcePart']['sourceBytes']);
        $t->same(hash('sha256', $parts['word/other/MEDIA/source.xml']), $media['largestExistingSourcePart']['sourceSha256']);
        $t->same('application/xml', $media['largestExistingSourcePart']['sourceContentTypeBase']);
        $t->same('default', $media['largestExistingSourcePart']['sourceContentTypeSource']);
        $t->same(['package-part'], $media['largestExistingSourcePart']['sourceRoles']);
        $t->same(2, $media['largestExistingSourcePart']['relationshipCount']);

        $exactMedia = $stems['MEDIA'];
        $t->same(2, $exactMedia['sourceCount']);
        $t->same(1, $exactMedia['existingSourceCount']);
        $t->same(1, $exactMedia['nonExistingSourceCount']);
        $t->same(2, $exactMedia['sourceDirectoryCount']);
        $t->same(3, $exactMedia['relationshipCount']);
        $t->same(['MEDIA' => 2], $exactMedia['directoryBaseNameStemCounts']);
        $t->same(['MEDIA' => 1, 'MEDIA.tmp' => 1], $exactMedia['directoryBaseNameCounts']);
        $t->same(['word/MEDIA.tmp' => 1, 'word/other/MEDIA' => 1], $exactMedia['sourceDirectoryCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_source_directory_base_name_stem_fixture_parts(): array
{
    $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Relationship source directory stem fixture.</w:t></w:r></w:p></w:body>
</w:document>
XML;
    $mediaAssetSource = str_repeat('A', 31);
    $mediaRawSource = str_repeat('R', 41);
    $otherMediaSource = str_repeat('O', 53);

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
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
        'word/document.xml' => $documentXml,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocumentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/document.png"/>
</Relationships>
XML,
        'word/Media.assets/source.xml' => $mediaAssetSource,
        'word/Media.assets/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMediaAsset" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/review.png"/>
</Relationships>
XML,
        'word/media.raw/source.bin' => $mediaRawSource,
        'word/media.raw/_rels/source.bin.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMediaRaw" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/raw.png"/>
</Relationships>
XML,
        'word/other/MEDIA/source.xml' => $otherMediaSource,
        'word/other/MEDIA/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rOtherMediaA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../../media/other-a.png"/>
  <Relationship Id="rOtherMediaB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../../media/other-b.png"/>
</Relationships>
XML,
        'word/MEDIA.tmp/_rels/missing-source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/missing-source.png"/>
</Relationships>
XML,
        'word/media/document.png' => 'document image bytes',
        'word/media/review.png' => 'review image bytes',
        'word/media/raw.png' => 'raw image bytes',
        'word/media/other-a.png' => 'other image bytes a',
        'word/media/other-b.png' => 'other image bytes b',
        'word/media/missing-source.png' => 'missing source image bytes',
    ];
}
