<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

$mediumTargetPath = 'word/media/review-target-between-33-and-64.png';
$longTargetPath = 'customXml/review/relationship-target-name-that-exceeds-sixty-four-bytes.bin';

return [
    'summarizes DOCX relationship source and target path byte-length buckets' => static function (TestRunner $t) use (
        $mediumTargetPath,
        $longTargetPath
    ): void {
        $summary = (new DocxOpenXmlReader())
            ->readPackage(docx_relationship_path_byte_length_bucket_fixture_parts($mediumTargetPath, $longTargetPath))
            ->attr('docx')['packageProvenance']['summary'];

        $customXmlRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $officeDocumentRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';

        $t->same(2, $summary['relationshipSourcePathByteLengthBucketCount']);
        $t->same(['up-to-8-bytes', '17-to-32-bytes'], $summary['relationshipSourcePathByteLengthBuckets']);
        $t->same(
            ['up-to-8-bytes' => 1, '17-to-32-bytes' => 1],
            $summary['relationshipSourcePathByteLengthBucketCounts']
        );
        $t->same(
            ['17-to-32-bytes' => ['word/document.xml'], 'up-to-8-bytes' => ['/']],
            $summary['relationshipSourcePartsByPathByteLengthBucket']
        );

        $sourceBuckets = docx_relationship_path_byte_length_bucket_index_by(
            $summary['relationshipSourcePathByteLengthBucketSummaries'],
            'relationshipSourcePathByteLengthBucket'
        );
        $t->same(2, $sourceBuckets['up-to-8-bytes']['relationshipCount']);
        $t->same(['package-root' => 1], $sourceBuckets['up-to-8-bytes']['relationshipSourceKindCounts']);
        $t->same('/', $sourceBuckets['up-to-8-bytes']['longestSourcePart']);
        $t->same(2, $sourceBuckets['17-to-32-bytes']['relationshipCount']);
        $t->same(strlen('word/document.xml'), $sourceBuckets['17-to-32-bytes']['longestRelationshipSourcePathByteLength']);
        $t->same('word/document.xml', $sourceBuckets['17-to-32-bytes']['longestSourcePart']);

        $t->same(4, $summary['relationshipTargetPathByteLengthBucketCount']);
        $t->same(
            ['up-to-8-bytes', '17-to-32-bytes', '33-to-64-bytes', 'over-64-bytes'],
            $summary['relationshipTargetPathByteLengthBuckets']
        );
        $t->same(
            [
                'up-to-8-bytes' => 1,
                '17-to-32-bytes' => 1,
                '33-to-64-bytes' => 1,
                'over-64-bytes' => 1,
            ],
            $summary['relationshipTargetPathByteLengthBucketCounts']
        );
        $t->same(
            [
                '17-to-32-bytes' => ['word/document.xml'],
                '33-to-64-bytes' => [$mediumTargetPath],
                'over-64-bytes' => [$longTargetPath],
                'up-to-8-bytes' => ['a.xml'],
            ],
            $summary['relationshipTargetPartsByPathByteLengthBucket']
        );
        $t->same(
            [
                '17-to-32-bytes' => [$officeDocumentRel => 1],
                '33-to-64-bytes' => [$imageRel => 1],
                'over-64-bytes' => [$customXmlRel => 1],
                'up-to-8-bytes' => [$customXmlRel => 1],
            ],
            $summary['relationshipTargetPathByteLengthRelationshipTypeCounts']
        );

        $targetBuckets = docx_relationship_path_byte_length_bucket_index_by(
            $summary['relationshipTargetPathByteLengthBucketSummaries'],
            'relationshipTargetPathByteLengthBucket'
        );
        $t->same(1, $targetBuckets['up-to-8-bytes']['missingTargetCount']);
        $t->same(['a.xml'], $targetBuckets['up-to-8-bytes']['missingTargetParts']);
        $t->same($mediumTargetPath, $targetBuckets['33-to-64-bytes']['longestTargetPart']);
        $t->same(strlen($mediumTargetPath), $targetBuckets['33-to-64-bytes']['longestRelationshipTargetPathByteLength']);
        $t->same([$mediumTargetPath], $targetBuckets['33-to-64-bytes']['existingTargetParts']);
        $t->same($longTargetPath, $targetBuckets['over-64-bytes']['longestTargetPart']);
        $t->same(strlen($longTargetPath), $targetBuckets['over-64-bytes']['longestRelationshipTargetPathByteLength']);
        $t->same([$longTargetPath], $targetBuckets['over-64-bytes']['existingTargetParts']);
        $t->same([$customXmlRel => 1], $targetBuckets['over-64-bytes']['relationshipTypeCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_path_byte_length_bucket_fixture_parts(string $mediumTargetPath, string $longTargetPath): array
{
    $customXmlRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
    $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
    $officeDocumentRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="{$officeDocumentRel}" Target="word/document.xml"/>
  <Relationship Id="rTinyMissing" Type="{$customXmlRel}" Target="a.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMediumImage" Type="{$imageRel}" Target="media/review-target-between-33-and-64.png"/>
  <Relationship Id="rLongCustomXml" Type="{$customXmlRel}" Target="../{$longTargetPath}"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Relationship path byte-length buckets.</w:t></w:r></w:p></w:body>
</w:document>
XML,
        $mediumTargetPath => 'medium relationship target bytes',
        $longTargetPath => 'long relationship target bytes',
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_relationship_path_byte_length_bucket_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}
