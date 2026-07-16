<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target case-fold directory base names for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_target_casefold_directory_base_name_fixture_parts();
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['relationshipTargetCaseFoldDirectoryBaseNames'] as $group) {
            $groups[$group['caseFoldDirectoryBaseName']] = $group;
        }

        $t->same(2, $summary['relationshipTargetCaseFoldDirectoryBaseNameCount']);
        $t->same([
            'media' => 4,
            'word' => 1,
        ], $summary['relationshipTargetCaseFoldDirectoryBaseNameCounts']);
        $t->same([
            'media' => 3,
            'word' => 1,
        ], $summary['relationshipTargetExistingCaseFoldDirectoryBaseNameCounts']);
        $t->same(['media' => 1], $summary['relationshipTargetMissingCaseFoldDirectoryBaseNameCounts']);
        $t->same(1, $summary['duplicateRelationshipTargetCaseFoldDirectoryBaseNameCount']);
        $t->same(['media'], $summary['duplicateRelationshipTargetCaseFoldDirectoryBaseNames']);
        $t->same(['media', 'word'], array_column($summary['relationshipTargetCaseFoldDirectoryBaseNames'], 'caseFoldDirectoryBaseName'));

        $media = $groups['media'];
        $t->same(3, $media['directoryBaseNameVariantCount']);
        $t->same(4, $media['directoryCount']);
        $t->same(4, $media['relationshipCount']);
        $t->same(3, $media['existingTargetCount']);
        $t->same(1, $media['missingTargetCount']);
        $t->same(0, $media['missingContentTypeTargetCount']);
        $t->same(0, $media['parameterizedTargetCount']);
        $t->same(
            strlen($parts['word/Media/review.png'])
                + strlen($parts['word/media/large.png'])
                + strlen($parts['word/other/Media/other.png']),
            $media['existingTargetByteLength']
        );
        $t->same(['MEDIA' => 1, 'Media' => 2, 'media' => 1], $media['directoryBaseNameCounts']);
        $t->same([
            'word/MEDIA' => 1,
            'word/Media' => 1,
            'word/media' => 1,
            'word/other/Media' => 1,
        ], $media['targetDirectoryCounts']);
        $t->same(['default' => 4], $media['contentTypeSourceCounts']);
        $t->same(['image/png' => 4], $media['contentTypeBaseCounts']);
        $t->same([$imageRel => 4], $media['relationshipTypeCounts']);
        $t->same([
            'document-relationship-target' => 3,
            'missing-relationship-target' => 1,
        ], $media['roleCounts']);
        $t->same(['word/MEDIA', 'word/Media', 'word/media', 'word/other/Media'], $media['targetDirectories']);
        $t->same(['word/document.xml'], $media['sourceParts']);
        $t->same(['word/_rels/document.xml.rels'], $media['relationshipParts']);
        $t->same(['rImageLarge', 'rImageMissing', 'rImageOther', 'rImageReview'], $media['relationshipIds']);
        $t->same([$imageRel], $media['relationshipTypes']);
        $t->same(['image/png'], $media['contentTypes']);
        $t->same([
            'word/MEDIA/missing.png',
            'word/Media/review.png',
            'word/media/large.png',
            'word/other/Media/other.png',
        ], $media['targetParts']);
        $t->same('word/other/Media/other.png', $media['largestExistingTargetPart']['partName']);
        $t->same('word/other/Media/other.png', $media['largestExistingTargetPart']['targetPart']);
        $t->same('word/other/Media', $media['largestExistingTargetPart']['directory']);
        $t->same('Media', $media['largestExistingTargetPart']['directoryBaseName']);
        $t->same('media', $media['largestExistingTargetPart']['caseFoldDirectoryBaseName']);
        $t->same('other.png', $media['largestExistingTargetPart']['baseName']);
        $t->same(4, $media['largestExistingTargetPart']['targetPathDepth']);
        $t->same('png', $media['largestExistingTargetPart']['partExtension']);
        $t->same(53, $media['largestExistingTargetPart']['bytes']);
        $t->same(53, $media['largestExistingTargetPart']['targetBytes']);
        $t->same(hash('sha256', $parts['word/other/Media/other.png']), $media['largestExistingTargetPart']['sha256']);
        $t->same('image/png', $media['largestExistingTargetPart']['contentTypeBase']);
        $t->same('default', $media['largestExistingTargetPart']['contentTypeSource']);
        $t->same(['document-relationship-target'], $media['largestExistingTargetPart']['roles']);
        $t->true(!in_array('rExternalMedia', $media['relationshipIds'], true), 'external targets should not enter internal case-fold directory buckets');
        $t->same(1, $summary['externalRelationshipCount']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_casefold_directory_base_name_fixture_parts(): array
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
  <Relationship Id="rImageReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="Media/review.png"/>
  <Relationship Id="rImageLarge" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/large.png"/>
  <Relationship Id="rImageOther" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="other/Media/other.png"/>
  <Relationship Id="rImageMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="MEDIA/missing.png"/>
  <Relationship Id="rExternalMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/media/external.png" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target case-fold directory fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/Media/review.png' => 'mixed media image bytes',
        'word/media/large.png' => str_repeat('L', 41),
        'word/other/Media/other.png' => str_repeat('O', 53),
    ];
}
