<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package thumbnail provenance rollups' => static function (TestRunner $t): void {
        $parts = docx_package_thumbnail_provenance_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $thumbnails = $package['packageThumbnails'];
        $byId = $thumbnails['byRelationshipId'];

        $t->same('Package thumbnail rollups.', $document->children[0]->attr('text'));
        $t->same(4, $thumbnails['count']);
        $t->same(4, $summary['packageThumbnailCount']);
        $t->same(2, $thumbnails['readableCount']);
        $t->same(2, $summary['packageThumbnailReadableCount']);
        $t->same(1, $thumbnails['missingCount']);
        $t->same(1, $summary['packageThumbnailMissingCount']);
        $t->same(1, $thumbnails['externalCount']);
        $t->same(1, $summary['packageThumbnailExternalCount']);
        $t->same(4, $thumbnails['invalidCount']);
        $t->same(4, $summary['packageThumbnailInvalidCount']);
        $t->same(4, $thumbnails['issueCount']);
        $t->same([
            'external-thumbnail-target',
            'invalid-thumbnail-content-type',
            'missing-in-package',
            'multiple-thumbnail-relationships-for-source',
            'thumbnail-target-has-relationships',
        ], $thumbnails['issueCodes']);
        $t->same($thumbnails['issueCodes'], $summary['packageThumbnailIssueCodes']);

        $t->same([
            'rThumbPng',
            'rThumbText',
            'rThumbMissing',
            'rThumbExternal',
        ], $thumbnails['relationshipIds']);
        $t->same([
            'docProps/thumbnail.png',
            'docProps/thumb.txt',
            'docProps/missing.jpg',
        ], $thumbnails['targetParts']);
        $t->same(['https://example.test/thumb.png'], $thumbnails['externalTargets']);
        $t->same(['image/png', 'text/plain', 'image/jpeg'], $thumbnails['contentTypes']);

        $expectedContentTypeCounts = [
            '(external)' => 1,
            'image/jpeg' => 1,
            'image/png' => 1,
            'text/plain' => 1,
        ];
        $t->same($expectedContentTypeCounts, $thumbnails['contentTypeCounts']);
        $t->same($expectedContentTypeCounts, $summary['packageThumbnailContentTypeCounts']);
        $t->same($expectedContentTypeCounts, $thumbnails['contentTypeBaseCounts']);
        $t->same($expectedContentTypeCounts, $summary['packageThumbnailContentTypeBaseCounts']);
        $t->same(['(external)' => 1, 'default' => 3], $thumbnails['contentTypeSourceCounts']);
        $t->same($thumbnails['contentTypeSourceCounts'], $summary['packageThumbnailContentTypeSourceCounts']);
        $t->same(['(external)' => 1, 'jpg' => 1, 'png' => 1, 'txt' => 1], $thumbnails['targetPartExtensionCounts']);
        $t->same($thumbnails['targetPartExtensionCounts'], $summary['packageThumbnailTargetPartExtensionCounts']);
        $t->same(['(external)' => 1, 'docProps' => 3], $thumbnails['targetDirectoryCounts']);
        $t->same($thumbnails['targetDirectoryCounts'], $summary['packageThumbnailTargetDirectoryCounts']);

        $expectedReadableBytes = strlen($parts['docProps/thumbnail.png']) + strlen($parts['docProps/thumb.txt']);
        $t->same($expectedReadableBytes, $thumbnails['readableByteLength']);
        $t->same($expectedReadableBytes, $summary['packageThumbnailReadableByteLength']);
        $largest = $thumbnails['largestReadableThumbnail'];
        $t->same($largest, $summary['packageThumbnailLargestReadableThumbnail']);
        $t->same('rThumbText', $largest['id']);
        $t->same('docProps/thumb.txt', $largest['targetPart']);
        $t->same('text/plain', $largest['contentTypeBase']);
        $t->same(strlen($parts['docProps/thumb.txt']), $largest['byteLength']);
        $t->same(sprintf('%08x', crc32($parts['docProps/thumb.txt'])), $largest['crc32']);
        $t->same(hash('sha256', $parts['docProps/thumb.txt']), $largest['sha256']);
        $t->same('package-thumbnail-metadata-only', $largest['reviewPolicy']);
        $t->same([
            'multiple-thumbnail-relationships-for-source',
            'invalid-thumbnail-content-type',
        ], $largest['issues']);

        $t->same(true, $byId['rThumbPng']['targetHasRelationships']);
        $t->same(false, $byId['rThumbPng']['valid']);
        $t->same([
            'multiple-thumbnail-relationships-for-source',
            'thumbnail-target-has-relationships',
        ], $byId['rThumbPng']['issues']);
        $t->same(false, $byId['rThumbText']['valid']);
        $t->same(false, $byId['rThumbMissing']['exists']);
        $t->same(false, $byId['rThumbMissing']['external']);
        $t->same(['multiple-thumbnail-relationships-for-source', 'missing-in-package'], $byId['rThumbMissing']['issues']);
        $t->same(true, $byId['rThumbExternal']['external']);
        $t->same(null, $byId['rThumbExternal']['targetPart']);
        $t->same(['multiple-thumbnail-relationships-for-source', 'external-thumbnail-target'], $byId['rThumbExternal']['issues']);
        $t->same(false, array_key_exists('contents', $largest));
        json_encode($thumbnails, JSON_THROW_ON_ERROR);
    },
];

/**
 * @return array<string, string>
 */
function docx_package_thumbnail_provenance_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="jpg" ContentType="image/jpeg"/>
  <Default Extension="txt" ContentType="text/plain"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rThumbPng" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="docProps/thumbnail.png"/>
  <Relationship Id="rThumbText" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="docProps/thumb.txt"/>
  <Relationship Id="rThumbMissing" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="docProps/missing.jpg"/>
  <Relationship Id="rThumbExternal" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="https://example.test/thumb.png" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package thumbnail rollups.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/thumbnail.png' => 'PNG thumbnail bytes',
        'docProps/thumb.txt' => 'plain text thumbnail placeholder for review',
        'docProps/_rels/thumbnail.png.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>
XML,
    ];
}
