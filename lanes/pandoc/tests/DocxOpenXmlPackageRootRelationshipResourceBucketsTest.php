<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package root relationship resource buckets' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_package_root_relationship_resource_bucket_parts());
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $resources = $package['packageRootRelationshipResources'];

        $reviewRelationshipType = 'http://example.test/package/relationships/review-resource';
        $auditRelationshipType = 'http://example.test/package/relationships/audit-feed';
        $metadataRelationshipType = 'http://example.test/package/relationships/metadata';
        $rawDataRelationshipType = 'http://example.test/package/relationships/raw-data';
        $imageRelationshipType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $hyperlinkRelationshipType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';

        $t->same('Package root resource bucket fixture.', $document->children[0]->attr('text'));
        $t->same(3, $summary['packageRootRelationshipResourceCount']);
        $t->same(1, $summary['packageRootRelationshipResourceExistingCount']);
        $t->same(1, $summary['packageRootRelationshipResourceMissingCount']);
        $t->same(1, $summary['packageRootRelationshipResourceExternalCount']);
        $t->same(1, $summary['packageRootRelationshipResourceTargetRelationshipCount']);
        $t->same(4, $summary['packageRootRelationshipResourceTargetRelationshipRecordCount']);
        $t->same(2, $summary['packageRootRelationshipResourceTargetRelationshipExistingTargetCount']);
        $t->same(1, $summary['packageRootRelationshipResourceTargetRelationshipMissingTargetCount']);
        $t->same(1, $summary['packageRootRelationshipResourceTargetRelationshipExternalTargetCount']);
        $t->same(1, $summary['packageRootRelationshipResourceTargetRelationshipUnsafeExternalTargetCount']);
        $t->same(1, $summary['packageRootRelationshipResourceTargetRelationshipMissingContentTypeTargetCount']);

        $t->same([
            $auditRelationshipType => 1,
            $reviewRelationshipType => 2,
        ], $resources['relationshipTypeCounts']);
        $t->same($resources['relationshipTypeCounts'], $summary['packageRootRelationshipResourceRelationshipTypeCounts']);
        $t->same(['docProps' => 2], $resources['targetDirectoryCounts']);
        $t->same($resources['targetDirectoryCounts'], $summary['packageRootRelationshipResourceTargetDirectoryCounts']);
        $t->same(['bin' => 1, 'xml' => 1], $resources['targetPartExtensionCounts']);
        $t->same($resources['targetPartExtensionCounts'], $summary['packageRootRelationshipResourceTargetPartExtensionCounts']);
        $t->same([
            '(external)' => 1,
            '(missing)' => 1,
            'application/vnd.example.review+xml' => 1,
        ], $resources['contentTypeBaseCounts']);
        $t->same($resources['contentTypeBaseCounts'], $summary['packageRootRelationshipResourceContentTypeBaseCounts']);
        $t->same([
            '(external)' => 1,
            'missing' => 1,
            'override' => 1,
        ], $resources['contentTypeSourceCounts']);
        $t->same($resources['contentTypeSourceCounts'], $summary['packageRootRelationshipResourceContentTypeSourceCounts']);

        $t->same([
            $metadataRelationshipType => 1,
            $rawDataRelationshipType => 1,
            $hyperlinkRelationshipType => 1,
            $imageRelationshipType => 1,
        ], $resources['targetRelationshipTypeCounts']);
        $t->same($resources['targetRelationshipTypeCounts'], $summary['packageRootRelationshipResourceTargetRelationshipTypeCounts']);
        $t->same([
            'customXml' => 1,
            'docProps' => 1,
            'word/media' => 1,
        ], $resources['targetRelationshipTargetDirectoryCounts']);
        $t->same(
            $resources['targetRelationshipTargetDirectoryCounts'],
            $summary['packageRootRelationshipResourceTargetRelationshipTargetDirectoryCounts']
        );
        $t->same([
            'png' => 1,
            'raw' => 1,
            'review' => 1,
        ], $resources['targetRelationshipTargetPartExtensionCounts']);
        $t->same(
            $resources['targetRelationshipTargetPartExtensionCounts'],
            $summary['packageRootRelationshipResourceTargetRelationshipTargetPartExtensionCounts']
        );
        $t->same([
            '(external)' => 1,
            '(missing)' => 1,
            'application/vnd.example.review-sidecar+xml' => 1,
            'image/png' => 1,
        ], $resources['targetRelationshipContentTypeBaseCounts']);
        $t->same(
            $resources['targetRelationshipContentTypeBaseCounts'],
            $summary['packageRootRelationshipResourceTargetRelationshipContentTypeBaseCounts']
        );
        $t->same([
            '(external)' => 1,
            'default' => 1,
            'missing' => 1,
            'override' => 1,
        ], $resources['targetRelationshipContentTypeSourceCounts']);
        $t->same(
            $resources['targetRelationshipContentTypeSourceCounts'],
            $summary['packageRootRelationshipResourceTargetRelationshipContentTypeSourceCounts']
        );

        $review = $resources['byRelationshipId']['rReviewResource'];
        $t->same('docProps/review.xml', $review['targetPart']);
        $t->same(4, $review['targetRelationshipRecordCount']);
        $t->same(['word/media/preview.PNG', 'docProps/sidecar.review'], $review['targetRelationshipExistingTargetParts']);
        $t->same(['customXml/missing.raw'], $review['targetRelationshipMissingTargetParts']);
        $t->same(['file:///etc/passwd'], $review['targetRelationshipUnsafeExternalTargets']);
        $t->true(!array_key_exists('contents', $review), 'package root resource bytes must stay metadata-only');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_root_relationship_resource_bucket_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/review.xml" ContentType="application/vnd.example.review+xml"/>
  <Override PartName="/docProps/sidecar.review" ContentType="application/vnd.example.review-sidecar+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rReviewResource" Type="http://example.test/package/relationships/review-resource" Target="docProps/review.xml?review=1#root"/>
  <Relationship Id="rMissingReview" Type="http://example.test/package/relationships/review-resource" Target="docProps/missing.bin"/>
  <Relationship Id="rAuditFeed" Type="http://example.test/package/relationships/audit-feed" TargetMode="External" Target="https://example.test/audit-feed"/>
</Relationships>
XML,
        'docProps/_rels/review.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rPreview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="/word/media/preview.PNG?asset=1#img"/>
  <Relationship Id="rSidecar" Type="http://example.test/package/relationships/metadata" Target="sidecar.review"/>
  <Relationship Id="rMissingRaw" Type="http://example.test/package/relationships/raw-data" Target="/customXml/missing.raw"/>
  <Relationship Id="rUnsafeExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" TargetMode="External" Target="file:///etc/passwd"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package root resource bucket fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/review.xml' => '<review xmlns="urn:example:review">metadata only</review>',
        'docProps/sidecar.review' => 'sidecar review bytes',
        'word/media/preview.PNG' => 'preview image bytes',
    ];
}
