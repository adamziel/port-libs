<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target directory base-name stems for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_target_directory_base_name_stem_fixture_parts();
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['relationshipTargetDirectoryBaseNameStems'] as $group) {
            $groups[$group['directoryBaseNameStem']] = $group;
        }

        $t->same(4, $summary['relationshipTargetDirectoryBaseNameStemCount']);
        $t->same([
            'MEDIA' => 2,
            'Media' => 1,
            'media' => 1,
            'word' => 1,
        ], $summary['relationshipTargetDirectoryBaseNameStemCounts']);
        $t->same([
            'MEDIA' => 1,
            'Media' => 1,
            'media' => 1,
            'word' => 1,
        ], $summary['relationshipTargetExistingDirectoryBaseNameStemCounts']);
        $t->same(['MEDIA' => 1], $summary['relationshipTargetMissingDirectoryBaseNameStemCounts']);
        $t->same(1, $summary['duplicateRelationshipTargetDirectoryBaseNameStemCount']);
        $t->same(['MEDIA'], $summary['duplicateRelationshipTargetDirectoryBaseNameStems']);
        $t->same(['MEDIA', 'Media', 'media', 'word'], array_column($summary['relationshipTargetDirectoryBaseNameStems'], 'directoryBaseNameStem'));

        $media = $groups['MEDIA'];
        $t->same(1, $media['directoryBaseNameStemVariantCount']);
        $t->same(2, $media['directoryBaseNameVariantCount']);
        $t->same(2, $media['directoryCount']);
        $t->same(2, $media['relationshipCount']);
        $t->same(1, $media['existingTargetCount']);
        $t->same(1, $media['missingTargetCount']);
        $t->same(0, $media['missingContentTypeTargetCount']);
        $t->same(0, $media['parameterizedTargetCount']);
        $t->same(strlen($parts['word/other/MEDIA/other.png']), $media['existingTargetByteLength']);
        $t->same(['MEDIA' => 2], $media['directoryBaseNameStemCounts']);
        $t->same(['MEDIA' => 1, 'MEDIA.tmp' => 1], $media['directoryBaseNameCounts']);
        $t->same([
            'word/MEDIA.tmp' => 1,
            'word/other/MEDIA' => 1,
        ], $media['targetDirectoryCounts']);
        $t->same(['default' => 2], $media['contentTypeSourceCounts']);
        $t->same(['image/png' => 2], $media['contentTypeBaseCounts']);
        $t->same([$imageRel => 2], $media['relationshipTypeCounts']);
        $t->same([
            'document-relationship-target' => 1,
            'missing-relationship-target' => 1,
        ], $media['roleCounts']);
        $t->same([
            'word/MEDIA.tmp',
            'word/other/MEDIA',
        ], $media['targetDirectories']);
        $t->same(['word/document.xml'], $media['sourceParts']);
        $t->same(['word/_rels/document.xml.rels'], $media['relationshipParts']);
        $t->same(['rImageMissing', 'rImageOther'], $media['relationshipIds']);
        $t->same([$imageRel], $media['relationshipTypes']);
        $t->same(['image/png'], $media['contentTypes']);
        $t->same([
            'word/MEDIA.tmp/missing.png',
            'word/other/MEDIA/other.png',
        ], $media['targetParts']);
        $t->same('word/other/MEDIA/other.png', $media['largestExistingTargetPart']['partName']);
        $t->same('word/other/MEDIA/other.png', $media['largestExistingTargetPart']['targetPart']);
        $t->same('word/other/MEDIA', $media['largestExistingTargetPart']['directory']);
        $t->same('MEDIA', $media['largestExistingTargetPart']['directoryBaseName']);
        $t->same('MEDIA', $media['largestExistingTargetPart']['directoryBaseNameStem']);
        $t->same('media', $media['largestExistingTargetPart']['caseFoldDirectoryBaseNameStem']);
        $t->same('other.png', $media['largestExistingTargetPart']['baseName']);
        $t->same(4, $media['largestExistingTargetPart']['targetPathDepth']);
        $t->same('png', $media['largestExistingTargetPart']['partExtension']);
        $t->same(53, $media['largestExistingTargetPart']['bytes']);
        $t->same(53, $media['largestExistingTargetPart']['targetBytes']);
        $t->same(hash('sha256', $parts['word/other/MEDIA/other.png']), $media['largestExistingTargetPart']['sha256']);
        $t->same('image/png', $media['largestExistingTargetPart']['contentTypeBase']);
        $t->same('default', $media['largestExistingTargetPart']['contentTypeSource']);
        $t->same(['document-relationship-target'], $media['largestExistingTargetPart']['roles']);

        $exactMedia = $groups['media'];
        $t->same(1, $exactMedia['relationshipCount']);
        $t->same(1, $exactMedia['existingTargetCount']);
        $t->same(0, $exactMedia['missingTargetCount']);
        $t->same(['media' => 1], $exactMedia['directoryBaseNameStemCounts']);
        $t->same(['media.raw' => 1], $exactMedia['directoryBaseNameCounts']);
        $t->same(['word/media.raw' => 1], $exactMedia['targetDirectoryCounts']);

        $t->true(!in_array('rExternalMediaStem', $media['relationshipIds'], true), 'external targets should not enter internal directory stem buckets');
        $t->same(1, $summary['externalRelationshipCount']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_directory_base_name_stem_fixture_parts(): array
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
  <Relationship Id="rImageReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Media.assets/review.png"/>
  <Relationship Id="rImageLarge" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media.raw/large.png"/>
  <Relationship Id="rImageOther" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="other/MEDIA/other.png"/>
  <Relationship Id="rImageMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="MEDIA.tmp/missing.png"/>
  <Relationship Id="rExternalMediaStem" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/media.assets/external.png" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target directory basename stem fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/Media.assets/review.png' => str_repeat('R', 31),
        'word/media.raw/large.png' => str_repeat('L', 41),
        'word/other/MEDIA/other.png' => str_repeat('O', 53),
    ];
}
