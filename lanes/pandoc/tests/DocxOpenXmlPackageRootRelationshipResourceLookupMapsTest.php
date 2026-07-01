<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

$tests = [
    'records docx package root relationship resource lookup map mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'carries docx package root relationship resource target lookup maps' => static function (TestRunner $t): void {
        $parts = docx_package_root_relationship_resource_lookup_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $resources = $package['packageRootRelationshipResources'];
        $audit = $resources['byRelationshipId']['rPackageAudit'];
        $auditUpper = $resources['byRelationshipId']['rPackageAuditUpper'];
        $sidecar = $resources['byRelationshipId']['rPackageSidecar'];
        $sidecarRelationships = [];
        foreach ($sidecar['targetRelationships'] as $relationship) {
            $sidecarRelationships[$relationship['id']] = $relationship;
        }

        $t->same(5, $resources['count']);
        $t->same(3, $resources['existingCount']);
        $t->same(1, $resources['missingCount']);
        $t->same(1, $resources['externalCount']);
        $t->same([
            'customXml' => 1,
            'docProps' => 3,
        ], $resources['targetDirectoryCounts']);
        $t->same([
            'Review-Audit.XML' => 1,
            'missing-audit.xml' => 1,
            'review-audit.xml' => 1,
            'sidecar-audit.xml' => 1,
        ], $resources['targetBaseNameCounts']);
        $t->same([
            'missing-audit.xml' => 1,
            'review-audit.xml' => 2,
            'sidecar-audit.xml' => 1,
        ], $resources['targetCaseFoldBaseNameCounts']);
        $t->same([
            'review-audit.xml' => 2,
            'sidecar-audit.xml' => 1,
        ], $resources['targetExistingCaseFoldBaseNameCounts']);
        $t->same(['missing-audit.xml' => 1], $resources['targetMissingCaseFoldBaseNameCounts']);
        $t->same(['xml' => 4], $resources['targetPartExtensionCounts']);
        $t->same([
            'customXml/Review-Audit.XML',
            'docProps/review-audit.xml',
        ], $resources['targetPartsByCaseFoldBaseName']['review-audit.xml']);
        $t->same(['docProps/missing-audit.xml'], $resources['targetPartsByBaseName']['missing-audit.xml']);

        $t->same($resources['targetDirectoryCounts'], $summary['packageRootRelationshipResourceTargetDirectoryCounts']);
        $t->same($resources['targetBaseNameCounts'], $summary['packageRootRelationshipResourceTargetBaseNameCounts']);
        $t->same($resources['targetCaseFoldBaseNameCounts'], $summary['packageRootRelationshipResourceTargetCaseFoldBaseNameCounts']);
        $t->same($resources['targetPartsByCaseFoldBaseName'], $summary['packageRootRelationshipResourceTargetPartsByCaseFoldBaseName']);

        $t->same('docProps', $audit['targetDirectory']);
        $t->same('docProps', $audit['targetDirectoryBaseName']);
        $t->same('review-audit.xml', $audit['targetBaseName']);
        $t->same('review-audit', $audit['targetBaseNameStem']);
        $t->same('review-audit.xml', $audit['targetCaseFoldBaseName']);
        $t->same('xml', $audit['targetPartExtension']);
        $t->same(['docProps', 'review-audit.xml'], $audit['targetPathSegments']);

        $t->same('customXml', $auditUpper['targetDirectory']);
        $t->same('Review-Audit.XML', $auditUpper['targetBaseName']);
        $t->same('Review-Audit', $auditUpper['targetBaseNameStem']);
        $t->same('review-audit.xml', $auditUpper['targetCaseFoldBaseName']);
        $t->same('review-audit', $auditUpper['targetCaseFoldBaseNameStem']);
        $t->same('xml', $auditUpper['targetPartExtension']);
        $t->same('XML', $auditUpper['targetRawPartExtension']);

        $t->same([
            'docProps/media' => 3,
        ], $resources['targetRelationshipTargetDirectoryCounts']);
        $t->same([
            'Preview.PNG' => 1,
            'missing-preview.PNG' => 1,
            'preview.png' => 1,
        ], $resources['targetRelationshipTargetBaseNameCounts']);
        $t->same([
            'missing-preview.png' => 1,
            'preview.png' => 2,
        ], $resources['targetRelationshipTargetCaseFoldBaseNameCounts']);
        $t->same(['preview.png' => 2], $resources['targetRelationshipTargetExistingCaseFoldBaseNameCounts']);
        $t->same(['missing-preview.png' => 1], $resources['targetRelationshipTargetMissingCaseFoldBaseNameCounts']);
        $t->same(['png' => 3], $resources['targetRelationshipTargetPartExtensionCounts']);
        $t->same([
            'docProps/media/Preview.PNG',
            'docProps/media/preview.png',
        ], $resources['targetRelationshipTargetPartsByCaseFoldBaseName']['preview.png']);

        $t->same(
            $resources['targetRelationshipTargetDirectoryCounts'],
            $summary['packageRootRelationshipResourceTargetRelationshipTargetDirectoryCounts']
        );
        $t->same(
            $resources['targetRelationshipTargetCaseFoldBaseNameCounts'],
            $summary['packageRootRelationshipResourceTargetRelationshipTargetCaseFoldBaseNameCounts']
        );
        $t->same(
            $resources['targetRelationshipTargetPartsByCaseFoldBaseName'],
            $summary['packageRootRelationshipResourceTargetRelationshipTargetPartsByCaseFoldBaseName']
        );

        $previewUpper = $sidecarRelationships['rSidecarPreviewUpper'];
        $t->same('docProps/media/Preview.PNG', $previewUpper['targetPart']);
        $t->same('docProps/media', $previewUpper['targetDirectory']);
        $t->same('Preview.PNG', $previewUpper['targetBaseName']);
        $t->same('preview.png', $previewUpper['targetCaseFoldBaseName']);
        $t->same('png', $previewUpper['targetPartExtension']);
        $t->same('PNG', $previewUpper['targetRawPartExtension']);
        $t->same(['docProps', 'media', 'Preview.PNG'], $previewUpper['targetPathSegments']);

        $missingPreview = $sidecarRelationships['rSidecarMissingPreview'];
        $t->same(false, $missingPreview['exists']);
        $t->same('missing-preview.PNG', $missingPreview['targetBaseName']);
        $t->same('missing-preview.png', $missingPreview['targetCaseFoldBaseName']);
        $t->same(['missing-package-root-target-relationship-target'], $missingPreview['issues']);
    },
];

return $tests;

/**
 * @return array<string, string>
 */
function docx_package_root_relationship_resource_lookup_fixture_parts(): array
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
    <w:p><w:r><w:t>Package root relationship resource lookup fixture.</w:t></w:r></w:p>
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
