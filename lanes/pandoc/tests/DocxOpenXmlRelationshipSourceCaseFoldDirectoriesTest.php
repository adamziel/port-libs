<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source case-fold directories for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_source_casefold_directory_fixture_parts();

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['relationshipSourceCaseFoldDirectories'] as $group) {
            $groups[$group['sourceCaseFoldDirectoryKey']] = $group;
        }

        $t->same(5, $summary['relationshipSourceCount']);
        $t->same(3, $summary['relationshipSourceCaseFoldDirectoryCount']);
        $t->same([
            '/' => 1,
            'word' => 1,
            'word/media' => 3,
        ], $summary['relationshipSourceCaseFoldDirectoryCounts']);
        $t->same([
            '/' => 1,
            'word' => 1,
            'word/media' => 2,
        ], $summary['relationshipSourceExistingCaseFoldDirectoryCounts']);
        $t->same(['word/media' => 1], $summary['relationshipSourceNonExistingCaseFoldDirectoryCounts']);
        $t->same(1, $summary['duplicateRelationshipSourceCaseFoldDirectoryCount']);
        $t->same(3, $summary['duplicateRelationshipSourceCaseFoldDirectorySourceCount']);
        $t->same(4, $summary['duplicateRelationshipSourceCaseFoldDirectoryRelationshipCount']);
        $t->same(['word/media'], $summary['duplicateRelationshipSourceCaseFoldDirectories']);
        $t->same(['/', 'word', 'word/media'], array_column($summary['relationshipSourceCaseFoldDirectories'], 'sourceCaseFoldDirectory'));

        $media = $groups['word/media'];
        $t->same('word/media', $media['sourceCaseFoldDirectory']);
        $t->same(3, $media['sourceDirectoryVariantCount']);
        $t->same(3, $media['sourceCount']);
        $t->same(2, $media['existingSourceCount']);
        $t->same(1, $media['nonExistingSourceCount']);
        $t->same(1, $media['missingContentTypeSourceCount']);
        $t->same(1, $media['parameterizedSourceCount']);
        $t->same(4, $media['relationshipCount']);
        $t->same(4, $media['relationshipRecordCount']);
        $t->same(
            strlen($parts['word/Media/source.xml']) + strlen($parts['word/media/SOURCE.XML']),
            $media['existingSourceByteLength']
        );
        $t->same([
            'word/MEDIA' => 1,
            'word/Media' => 1,
            'word/media' => 1,
        ], $media['sourceDirectoryCounts']);
        $t->same(['3' => 3], $media['sourcePathDepthCounts']);
        $t->same(['2' => 3], $media['sourceDirectoryDepthCounts']);
        $t->same(['SOURCE.XML' => 1, 'missing.xml' => 1, 'source.xml' => 1], $media['sourceBaseNameCounts']);
        $t->same(['xml' => 3], $media['sourcePartExtensionCounts']);
        $t->same(['missing-source' => 1, 'package-part' => 2], $media['relationshipSourceKindCounts']);
        $t->same([
            '(missing)' => 1,
            'application/xml' => 2,
        ], $media['sourceContentTypeBaseCounts']);
        $t->same([
            '(missing)' => 1,
            'default' => 1,
            'override' => 1,
        ], $media['sourceContentTypeSourceCounts']);
        $t->same(['package-part' => 2], $media['sourceRoleCounts']);
        $t->same([
            'word/MEDIA',
            'word/Media',
            'word/media',
        ], $media['sourceDirectories']);
        $t->same([
            'word/MEDIA/missing.xml',
            'word/Media/source.xml',
            'word/media/SOURCE.XML',
        ], $media['sourceParts']);
        $t->same(['word/Media/source.xml', 'word/media/SOURCE.XML'], $media['existingSourceParts']);
        $t->same(['word/MEDIA/missing.xml'], $media['nonExistingSourceParts']);
        $t->same([
            'word/MEDIA/_rels/missing.xml.rels',
            'word/Media/_rels/source.xml.rels',
            'word/media/_rels/SOURCE.XML.rels',
        ], $media['relationshipParts']);
        $t->same(['application/xml', 'application/xml; profile=source-directory'], $media['contentTypes']);
        $t->same('word/media/SOURCE.XML', $media['largestExistingSourcePart']['sourcePart']);
        $t->same('word/media', $media['largestExistingSourcePart']['sourceDirectory']);
        $t->same('word/media', $media['largestExistingSourcePart']['sourceCaseFoldDirectory']);
        $t->same(2, $media['largestExistingSourcePart']['sourceDirectoryDepth']);
        $t->same(3, $media['largestExistingSourcePart']['sourcePathDepth']);
        $t->same('SOURCE.XML', $media['largestExistingSourcePart']['sourceBaseName']);
        $t->same('xml', $media['largestExistingSourcePart']['sourcePartExtension']);
        $t->same(strlen($parts['word/media/SOURCE.XML']), $media['largestExistingSourcePart']['sourceBytes']);
        $t->same(hash('sha256', $parts['word/media/SOURCE.XML']), $media['largestExistingSourcePart']['sourceSha256']);
        $t->same('application/xml', $media['largestExistingSourcePart']['sourceContentTypeBase']);
        $t->same('default', $media['largestExistingSourcePart']['sourceContentTypeSource']);
        $t->same(false, $media['largestExistingSourcePart']['sourceContentTypeHasParameters']);
        $t->same(0, $media['largestExistingSourcePart']['sourceContentTypeParameterCount']);
        $t->same(['package-part'], $media['largestExistingSourcePart']['sourceRoles']);
        $t->same(2, $media['largestExistingSourcePart']['relationshipCount']);

        $word = $groups['word'];
        $t->same(1, $word['sourceCount']);
        $t->same(1, $word['existingSourceCount']);
        $t->same(['word' => 1], $word['sourceDirectoryCounts']);
        $t->same(['office-document' => 1, 'root-relationship-target' => 1], $word['sourceRoleCounts']);

        $root = $groups['/'];
        $t->same('/', $root['sourceCaseFoldDirectory']);
        $t->same(1, $root['sourceCount']);
        $t->same(1, $root['existingSourceCount']);
        $t->same(['/' => 1], $root['sourceDirectoryCounts']);
        $t->same(['/'], $root['sourceDirectories']);
        $t->same(['package-root' => 1], $root['sourceRoleCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_source_casefold_directory_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/Media/source.xml" ContentType="application/xml; profile=source-directory"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship source case-fold directory fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocumentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/document.png"/>
</Relationships>
XML,
        'word/Media/source.xml' => str_repeat('M', 31),
        'word/Media/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMediaMixed" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/mixed.png"/>
</Relationships>
XML,
        'word/media/SOURCE.XML' => str_repeat('S', 43),
        'word/media/_rels/SOURCE.XML.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMediaLowerA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="lower-a.png"/>
  <Relationship Id="rMediaLowerB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="lower-b.png"/>
</Relationships>
XML,
        'word/MEDIA/_rels/missing.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingMediaSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="missing.png"/>
</Relationships>
XML,
        'word/media/document.png' => 'document image bytes',
        'word/media/mixed.png' => 'mixed media target',
        'word/media/lower-a.png' => 'lower media target a',
        'word/media/lower-b.png' => 'lower media target b',
    ];
}
