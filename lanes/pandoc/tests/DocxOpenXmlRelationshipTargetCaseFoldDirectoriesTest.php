<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target case-fold directories for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_target_casefold_directory_fixture_parts();
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['relationshipTargetCaseFoldDirectories'] as $group) {
            $groups[$group['caseFoldDirectory']] = $group;
        }

        $t->same(3, $summary['relationshipTargetCaseFoldDirectoryCount']);
        $t->same([
            'word' => 1,
            'word/media' => 3,
            'word/other/media' => 1,
        ], $summary['relationshipTargetCaseFoldDirectoryCounts']);
        $t->same([
            'word' => 1,
            'word/media' => 2,
            'word/other/media' => 1,
        ], $summary['relationshipTargetExistingCaseFoldDirectoryCounts']);
        $t->same(['word/media' => 1], $summary['relationshipTargetMissingCaseFoldDirectoryCounts']);
        $t->same(1, $summary['duplicateRelationshipTargetCaseFoldDirectoryCount']);
        $t->same(3, $summary['duplicateRelationshipTargetCaseFoldDirectoryRelationshipCount']);
        $t->same(3, $summary['duplicateRelationshipTargetCaseFoldDirectoryTargetCount']);
        $t->same(['word/media'], $summary['duplicateRelationshipTargetCaseFoldDirectories']);
        $t->same(['word', 'word/media', 'word/other/media'], array_column($summary['relationshipTargetCaseFoldDirectories'], 'caseFoldDirectory'));

        $media = $groups['word/media'];
        $t->same('word/media', $media['targetCaseFoldDirectory']);
        $t->same(3, $media['directoryVariantCount']);
        $t->same(3, $media['relationshipCount']);
        $t->same(2, $media['existingTargetCount']);
        $t->same(1, $media['missingTargetCount']);
        $t->same(0, $media['missingContentTypeTargetCount']);
        $t->same(0, $media['parameterizedTargetCount']);
        $t->same(strlen($parts['word/Media/review.png']) + strlen($parts['word/media/large.png']), $media['existingTargetByteLength']);
        $t->same([
            'word/MEDIA' => 1,
            'word/Media' => 1,
            'word/media' => 1,
        ], $media['directoryCounts']);
        $t->same([3 => 3], $media['targetPathDepthCounts']);
        $t->same([2 => 3], $media['targetDirectoryDepthCounts']);
        $t->same(['large.png' => 1, 'missing.png' => 1, 'review.png' => 1], $media['targetBaseNameCounts']);
        $t->same(['png' => 3], $media['targetPartExtensionCounts']);
        $t->same(['default' => 3], $media['contentTypeSourceCounts']);
        $t->same(['image/png' => 3], $media['contentTypeBaseCounts']);
        $t->same([$imageRel => 3], $media['relationshipTypeCounts']);
        $t->same([
            'document-relationship-target' => 2,
            'missing-relationship-target' => 1,
        ], $media['roleCounts']);
        $t->same(['word/MEDIA', 'word/Media', 'word/media'], $media['targetDirectories']);
        $t->same(['word/document.xml'], $media['sourceParts']);
        $t->same(['word/_rels/document.xml.rels'], $media['relationshipParts']);
        $t->same(['rImageLarge', 'rImageMissing', 'rImageReview'], $media['relationshipIds']);
        $t->same([$imageRel], $media['relationshipTypes']);
        $t->same(['image/png'], $media['contentTypes']);
        $t->same([
            'word/MEDIA/missing.png',
            'word/Media/review.png',
            'word/media/large.png',
        ], $media['targetParts']);
        $t->same(['word/Media/review.png', 'word/media/large.png'], $media['existingTargetParts']);
        $t->same(['word/MEDIA/missing.png'], $media['missingTargetParts']);
        $t->same('word/media/large.png', $media['largestExistingTargetPart']['partName']);
        $t->same('word/media/large.png', $media['largestExistingTargetPart']['targetPart']);
        $t->same('word/media', $media['largestExistingTargetPart']['directory']);
        $t->same('word/media', $media['largestExistingTargetPart']['caseFoldDirectory']);
        $t->same('large.png', $media['largestExistingTargetPart']['baseName']);
        $t->same(3, $media['largestExistingTargetPart']['targetPathDepth']);
        $t->same(2, $media['largestExistingTargetPart']['targetDirectoryDepth']);
        $t->same('png', $media['largestExistingTargetPart']['partExtension']);
        $t->same(41, $media['largestExistingTargetPart']['bytes']);
        $t->same(41, $media['largestExistingTargetPart']['targetBytes']);
        $t->same(hash('sha256', $parts['word/media/large.png']), $media['largestExistingTargetPart']['sha256']);
        $t->same('image/png', $media['largestExistingTargetPart']['contentTypeBase']);
        $t->same('default', $media['largestExistingTargetPart']['contentTypeSource']);
        $t->same(false, $media['largestExistingTargetPart']['contentTypeHasParameters']);
        $t->same(['document-relationship-target'], $media['largestExistingTargetPart']['roles']);

        $otherMedia = $groups['word/other/media'];
        $t->same(1, $otherMedia['directoryVariantCount']);
        $t->same(1, $otherMedia['relationshipCount']);
        $t->same(['word/other/Media' => 1], $otherMedia['directoryCounts']);
        $t->same([4 => 1], $otherMedia['targetPathDepthCounts']);
        $t->same([3 => 1], $otherMedia['targetDirectoryDepthCounts']);
        $t->same('word/other/Media/other.png', $otherMedia['largestExistingTargetPart']['targetPart']);
        $t->same('word/other/media', $otherMedia['largestExistingTargetPart']['caseFoldDirectory']);

        $word = $groups['word'];
        $t->same(1, $word['relationshipCount']);
        $t->same(1, $word['existingTargetCount']);
        $t->same(['word' => 1], $word['directoryCounts']);
        $t->same(['office-document' => 1, 'root-relationship-target' => 1], $word['roleCounts']);

        $t->true(!in_array('rExternalMedia', $media['relationshipIds'], true), 'external targets should not enter internal case-fold directory buckets');
        $t->same(1, $summary['externalRelationshipCount']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_casefold_directory_fixture_parts(): array
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
