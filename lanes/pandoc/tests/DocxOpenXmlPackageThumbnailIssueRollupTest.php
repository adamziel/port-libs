<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package thumbnail issue rollups' => static function (TestRunner $t): void {
        $thumbnailType = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail';
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="jpeg" ContentType="image/jpeg; profile=package-thumbnail"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
            '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rPackageThumb" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="docProps/thumbnail-review.jpeg?size=small#cover"/>
  <Relationship Id="rMissingThumb" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="docProps/missing-thumbnail.png"/>
  <Relationship Id="rExternalThumb" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="https://example.test/thumb.png?review=1#preview" TargetMode="External"/>
  <Relationship Id="rBadThumb" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="docProps/bad-thumbnail.xml"/>
</Relationships>
XML,
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Thumbnail issue rollups.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'docProps/thumbnail-review.jpeg' => 'jpeg thumbnail bytes',
            'docProps/bad-thumbnail.xml' => '<not-image/>',
            'docProps/_rels/thumbnail-review.jpeg.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rThumbAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="thumbnail-audit.png"/>
</Relationships>
XML,
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $thumbnails = $package['packageThumbnails'];

        $expectedCodeCounts = [
            'external-thumbnail-target' => 1,
            'invalid-thumbnail-content-type' => 1,
            'missing-in-package' => 1,
            'multiple-thumbnail-relationships-for-source' => 4,
            'thumbnail-target-has-relationships' => 1,
        ];
        $expectedRelationshipIdsByCode = [
            'external-thumbnail-target' => ['rExternalThumb'],
            'invalid-thumbnail-content-type' => ['rBadThumb'],
            'missing-in-package' => ['rMissingThumb'],
            'multiple-thumbnail-relationships-for-source' => [
                'rPackageThumb',
                'rMissingThumb',
                'rExternalThumb',
                'rBadThumb',
            ],
            'thumbnail-target-has-relationships' => ['rPackageThumb'],
        ];
        $expectedTargetPartsByCode = [
            'invalid-thumbnail-content-type' => ['docProps/bad-thumbnail.xml'],
            'missing-in-package' => ['docProps/missing-thumbnail.png'],
            'multiple-thumbnail-relationships-for-source' => [
                'docProps/thumbnail-review.jpeg',
                'docProps/missing-thumbnail.png',
                'docProps/bad-thumbnail.xml',
            ],
            'thumbnail-target-has-relationships' => ['docProps/thumbnail-review.jpeg'],
        ];
        $expectedExternalTargetsByCode = [
            'external-thumbnail-target' => ['https://example.test/thumb.png?review=1#preview'],
            'multiple-thumbnail-relationships-for-source' => ['https://example.test/thumb.png?review=1#preview'],
        ];

        $t->same(4, $thumbnails['count']);
        $t->same(4, $thumbnails['issueCount']);
        $t->same($expectedCodeCounts, $thumbnails['issueCodeCounts']);
        $t->same($expectedRelationshipIdsByCode, $thumbnails['issueRelationshipIdsByCode']);
        $t->same($expectedTargetPartsByCode, $thumbnails['issueTargetPartsByCode']);
        $t->same($expectedExternalTargetsByCode, $thumbnails['issueExternalTargetsByCode']);

        $t->same($expectedCodeCounts, $summary['packageThumbnailIssueCodeCounts']);
        $t->same($expectedRelationshipIdsByCode, $summary['packageThumbnailIssueRelationshipIdsByCode']);
        $t->same($expectedTargetPartsByCode, $summary['packageThumbnailIssueTargetPartsByCode']);
        $t->same($expectedExternalTargetsByCode, $summary['packageThumbnailIssueExternalTargetsByCode']);
        $t->same(4, $summary['relationshipTypeCounts'][$thumbnailType]);

        json_encode([$thumbnails, $summary], JSON_THROW_ON_ERROR);
    },
];
