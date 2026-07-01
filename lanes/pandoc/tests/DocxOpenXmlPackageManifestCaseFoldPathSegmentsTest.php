<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'mirrors DOCX ZIP package manifest case-fold path segment aggregates' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_package_manifest_case_fold_path_segment_zip_parts(),
            'docx package manifest case-fold path segments'
        );
        $manifest = $zip->packageManifestPreflight();
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $zipPackage = $package['zipPackage'];
        $identity = $package['packageIdentity'];
        $caseFoldSegments = docx_package_manifest_case_fold_path_segment_index_by(
            $manifest['caseFoldPathSegmentSummaries'],
            'caseFoldSegment'
        );
        $positions = docx_package_manifest_case_fold_path_segment_index_by(
            $manifest['pathSegmentPositionSummaries'],
            'position'
        );

        $t->same('Case-fold manifest path segments.', $document->children[0]->attr('text'));

        foreach (docx_package_manifest_case_fold_path_segment_fields() as $manifestField => $summaryField) {
            $t->same($manifest[$manifestField], $summary[$summaryField], "{$summaryField} summary mirror");
            $t->same($manifest[$manifestField], $zipPackage['packageManifest' . ucfirst($manifestField)], "{$manifestField} zipPackage mirror");
            $t->same($summary[$summaryField], $identity[$summaryField], "{$summaryField} identity mirror");
        }

        $media = $caseFoldSegments['media'];
        $t->same(3, $media['entryCount']);
        $t->same(3, $media['occurrenceCount']);
        $t->same(2, $media['segmentVariantCount']);
        $t->same(['Media', 'media'], $media['segments']);
        $t->same(['Media' => 1, 'media' => 2], $media['segmentCounts']);
        $t->same(['customXml/' => 1, 'word/' => 2], $media['directoryRootCounts']);
        $t->same(['png' => 3], $media['packagePartExtensionCounts']);
        $t->same(['0' => 2, '8' => 1], $media['compressionMethodCounts']);
        $t->same([
            'customXml/media/review.png',
            'word/Media/Review.PNG',
            'word/media/review.png',
        ], $media['entryNames']);

        $review = $caseFoldSegments['review.png'];
        $t->same(2, $review['segmentVariantCount']);
        $t->same(['Review.PNG', 'review.png'], $review['segments']);
        $t->same(['Review.PNG' => 1, 'review.png' => 2], $review['segmentCounts']);

        $t->same(['first', 'last', 'middle', 'only'], array_keys($manifest['pathSegmentPositionCounts']));
        $t->same(['_rels', 'customXml', 'word'], $positions['first']['segments']);
        $t->same(['Media', 'media'], $positions['middle']['segments']);
        $t->same(['[Content_Types].xml'], $positions['only']['segments']);
        $t->same(['[Content_Types].xml'], $positions['only']['entryNames']);

        $t->same(false, array_key_exists('contents', $summary['zipPackageManifestCaseFoldPathSegmentSummaries'][0]));
        $t->same(false, $identity['canExposeBytes']);
        $t->same('docx-package-identity-metadata-only', $identity['byteExposurePolicy']);
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int}>
 */
function docx_package_manifest_case_fold_path_segment_zip_parts(): array
{
    return [
        ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML, 'compressionMethod' => 0],
        ['name' => '_rels/.rels', 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML, 'compressionMethod' => 0],
        ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Case-fold manifest path segments.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML, 'compressionMethod' => 0],
        ['name' => 'word/media/review.png', 'data' => 'lower review png bytes', 'compressionMethod' => 0],
        ['name' => 'word/Media/Review.PNG', 'data' => str_repeat('upper-review-png-', 128), 'compressionMethod' => 8],
        ['name' => 'customXml/media/review.png', 'data' => 'custom xml review png bytes', 'compressionMethod' => 0],
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_package_manifest_case_fold_path_segment_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && is_string($item[$key] ?? null)) {
            $indexed[$item[$key]] = $item;
        }
    }

    return $indexed;
}

/**
 * @return array<string, string>
 */
function docx_package_manifest_case_fold_path_segment_fields(): array
{
    return [
        'pathSegmentSummaryCount' => 'zipPackageManifestPathSegmentSummaryCount',
        'pathSegmentOccurrenceCount' => 'zipPackageManifestPathSegmentOccurrenceCount',
        'pathSegmentCounts' => 'zipPackageManifestPathSegmentCounts',
        'pathSegmentEntryCounts' => 'zipPackageManifestPathSegmentEntryCounts',
        'pathSegmentSummaries' => 'zipPackageManifestPathSegmentSummaries',
        'caseFoldPathSegmentSummaryCount' => 'zipPackageManifestCaseFoldPathSegmentSummaryCount',
        'caseFoldPathSegments' => 'zipPackageManifestCaseFoldPathSegments',
        'caseFoldPathSegmentOccurrenceCount' => 'zipPackageManifestCaseFoldPathSegmentOccurrenceCount',
        'caseFoldPathSegmentCounts' => 'zipPackageManifestCaseFoldPathSegmentCounts',
        'caseFoldPathSegmentEntryCounts' => 'zipPackageManifestCaseFoldPathSegmentEntryCounts',
        'caseFoldPathSegmentSummaries' => 'zipPackageManifestCaseFoldPathSegmentSummaries',
        'pathSegmentPositionSummaryCount' => 'zipPackageManifestPathSegmentPositionSummaryCount',
        'pathSegmentPositionOccurrenceCount' => 'zipPackageManifestPathSegmentPositionOccurrenceCount',
        'pathSegmentPositionCounts' => 'zipPackageManifestPathSegmentPositionCounts',
        'pathSegmentPositionEntryCounts' => 'zipPackageManifestPathSegmentPositionEntryCounts',
        'pathSegmentPositionSummaries' => 'zipPackageManifestPathSegmentPositionSummaries',
    ];
}
