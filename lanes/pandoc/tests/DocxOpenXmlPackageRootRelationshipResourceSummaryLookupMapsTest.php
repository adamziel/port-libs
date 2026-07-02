<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'records docx package root relationship resource summary lookup map mapped case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedDocxPackageRootRelationshipResourceSummaryLookupMapCases'] ?? null);
        $t->same(36, $manifest['docxPackageRootRelationshipResourceSummaryLookupMapAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedDocxPackageRootRelationshipResourceSummaryLookupMapCases'] ?? null);
        $t->same(36, $manifest['benchmarkDenominator']['breakdown']['docxPackageRootRelationshipResourceSummaryLookupMapAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedDocxPackageRootRelationshipResourceSummaryLookupMapCases'] ?? null);
        $t->same(36, $manifest['benchmarkDenominator']['inventory']['docxPackageRootRelationshipResourceSummaryLookupMapAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedDocxPackageRootRelationshipResourceSummaryLookupMapCases'] ?? null);
        $t->same(36, $manifest['inventory']['docxPackageRootRelationshipResourceSummaryLookupMapAssertions'] ?? null);
    },

    'mirrors docx package root relationship resource lookup maps into package summary' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(
            docx_package_root_relationship_resource_summary_lookup_fixture_parts()
        );
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $resources = $package['packageRootRelationshipResources'];

        $rootSummaryKeys = [
            'packageRootRelationshipResourceTargetDirectoryBaseNameCounts' => 'targetDirectoryBaseNameCounts',
            'packageRootRelationshipResourceTargetBaseNameStemCounts' => 'targetBaseNameStemCounts',
            'packageRootRelationshipResourceTargetCaseFoldBaseNameStemCounts' => 'targetCaseFoldBaseNameStemCounts',
            'packageRootRelationshipResourceTargetExistingBaseNameCounts' => 'targetExistingBaseNameCounts',
            'packageRootRelationshipResourceTargetMissingBaseNameCounts' => 'targetMissingBaseNameCounts',
            'packageRootRelationshipResourceTargetExistingCaseFoldBaseNameCounts' => 'targetExistingCaseFoldBaseNameCounts',
            'packageRootRelationshipResourceTargetMissingCaseFoldBaseNameCounts' => 'targetMissingCaseFoldBaseNameCounts',
            'packageRootRelationshipResourceTargetPartsByDirectory' => 'targetPartsByDirectory',
            'packageRootRelationshipResourceTargetPartsByDirectoryBaseName' => 'targetPartsByDirectoryBaseName',
            'packageRootRelationshipResourceTargetPartsByBaseName' => 'targetPartsByBaseName',
            'packageRootRelationshipResourceTargetPartsByBaseNameStem' => 'targetPartsByBaseNameStem',
            'packageRootRelationshipResourceTargetPartsByCaseFoldBaseNameStem' => 'targetPartsByCaseFoldBaseNameStem',
        ];
        foreach ($rootSummaryKeys as $summaryKey => $resourceKey) {
            $t->same($resources[$resourceKey], $summary[$summaryKey]);
        }

        $targetRelationshipSummaryKeys = [
            'packageRootRelationshipResourceTargetRelationshipTargetDirectoryBaseNameCounts' => 'targetRelationshipTargetDirectoryBaseNameCounts',
            'packageRootRelationshipResourceTargetRelationshipTargetBaseNameStemCounts' => 'targetRelationshipTargetBaseNameStemCounts',
            'packageRootRelationshipResourceTargetRelationshipTargetCaseFoldBaseNameStemCounts' => 'targetRelationshipTargetCaseFoldBaseNameStemCounts',
            'packageRootRelationshipResourceTargetRelationshipTargetExistingBaseNameCounts' => 'targetRelationshipTargetExistingBaseNameCounts',
            'packageRootRelationshipResourceTargetRelationshipTargetMissingBaseNameCounts' => 'targetRelationshipTargetMissingBaseNameCounts',
            'packageRootRelationshipResourceTargetRelationshipTargetExistingCaseFoldBaseNameCounts' => 'targetRelationshipTargetExistingCaseFoldBaseNameCounts',
            'packageRootRelationshipResourceTargetRelationshipTargetMissingCaseFoldBaseNameCounts' => 'targetRelationshipTargetMissingCaseFoldBaseNameCounts',
            'packageRootRelationshipResourceTargetRelationshipTargetPartsByDirectory' => 'targetRelationshipTargetPartsByDirectory',
            'packageRootRelationshipResourceTargetRelationshipTargetPartsByDirectoryBaseName' => 'targetRelationshipTargetPartsByDirectoryBaseName',
            'packageRootRelationshipResourceTargetRelationshipTargetPartsByBaseName' => 'targetRelationshipTargetPartsByBaseName',
            'packageRootRelationshipResourceTargetRelationshipTargetPartsByBaseNameStem' => 'targetRelationshipTargetPartsByBaseNameStem',
            'packageRootRelationshipResourceTargetRelationshipTargetPartsByCaseFoldBaseNameStem' => 'targetRelationshipTargetPartsByCaseFoldBaseNameStem',
        ];
        foreach ($targetRelationshipSummaryKeys as $summaryKey => $resourceKey) {
            $t->same($resources[$resourceKey], $summary[$summaryKey]);
        }

        $t->same(['customXml' => 1, 'docProps' => 3], $summary['packageRootRelationshipResourceTargetDirectoryBaseNameCounts']);
        $t->same(['missing-audit' => 1, 'review-audit' => 2, 'sidecar-audit' => 1], $summary['packageRootRelationshipResourceTargetCaseFoldBaseNameStemCounts']);
        $t->same(['media' => 3], $summary['packageRootRelationshipResourceTargetRelationshipTargetDirectoryBaseNameCounts']);
        $t->same(['missing-preview' => 1, 'preview' => 2], $summary['packageRootRelationshipResourceTargetRelationshipTargetCaseFoldBaseNameStemCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_package_root_relationship_resource_summary_lookup_fixture_parts(): array
{
    $resourceType = 'http://example.test/openxml/relationships/reviewResource';

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/review-audit.xml" ContentType="application/vnd.example.review+xml; profile=root"/>
  <Override PartName="/docProps/sidecar-audit.xml" ContentType="application/vnd.example.review+xml; profile=sidecar"/>
</Types>
XML,
        '_rels/.rels' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rPackageAudit" Type="{$resourceType}" Target="docProps/review-audit.xml?slot=root#review"/>
  <Relationship Id="rPackageAuditUpper" Type="{$resourceType}" Target="customXml/Review-Audit.XML"/>
  <Relationship Id="rPackageSidecar" Type="{$resourceType}" Target="docProps/sidecar-audit.xml"/>
  <Relationship Id="rMissingPackageAudit" Type="{$resourceType}" Target="docProps/missing-audit.xml"/>
  <Relationship Id="rExternalPackageAudit" Type="{$resourceType}" Target="https://example.test/review-audit.xml" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package root relationship resource summary lookup fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/review-audit.xml' => '<review-resource>root audit</review-resource>',
        'customXml/Review-Audit.XML' => '<review-resource>upper audit</review-resource>',
        'docProps/sidecar-audit.xml' => '<review-resource>sidecar audit</review-resource>',
        'docProps/_rels/sidecar-audit.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rSidecarPreviewUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Preview.PNG"/>
  <Relationship Id="rSidecarPreviewLower" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/preview.png?variant=copy#asset"/>
  <Relationship Id="rSidecarMissingPreview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-preview.PNG"/>
  <Relationship Id="rSidecarExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review/preview.png" TargetMode="External"/>
</Relationships>
XML,
        'docProps/media/Preview.PNG' => 'upper preview bytes',
        'docProps/media/preview.png' => 'lower preview bytes',
    ];
}
