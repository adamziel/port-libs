<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

$tests = [
    'records docx package root relationship resource reference suffix mapped case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedDocxPackageRootRelationshipResourceReferenceSuffixCases'] ?? null);
        $t->same(56, $manifest['docxPackageRootRelationshipResourceReferenceSuffixAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedDocxPackageRootRelationshipResourceReferenceSuffixCases'] ?? null);
        $t->same(56, $manifest['benchmarkDenominator']['breakdown']['docxPackageRootRelationshipResourceReferenceSuffixAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedDocxPackageRootRelationshipResourceReferenceSuffixCases'] ?? null);
        $t->same(56, $manifest['benchmarkDenominator']['inventory']['docxPackageRootRelationshipResourceReferenceSuffixAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedDocxPackageRootRelationshipResourceReferenceSuffixCases'] ?? null);
        $t->same(56, $manifest['inventory']['docxPackageRootRelationshipResourceReferenceSuffixAssertions'] ?? null);
    },

    'carries docx package root relationship resource reference suffix rollups' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_package_root_relationship_resource_reference_suffix_fixture_parts());
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $resources = $package['packageRootRelationshipResources'];
        $audit = $resources['byRelationshipId']['rPackageAudit'];
        $external = $resources['byRelationshipId']['rExternalPackageAudit'];
        $sidecar = $resources['byRelationshipId']['rPackageSidecar'];
        $sidecarRelationships = [];
        foreach ($sidecar['targetRelationships'] as $relationship) {
            $sidecarRelationships[$relationship['id']] = $relationship;
        }

        $t->same(4, $resources['count']);
        $t->same(2, $resources['existingCount']);
        $t->same(1, $resources['missingCount']);
        $t->same(1, $resources['externalCount']);
        $t->same(3, $resources['targetReferenceSuffixCount']);
        $t->same(['?slot=root&slot=copy#review', '#sidecar', '?remote=1#ext'], $resources['targetReferenceSuffixes']);
        $t->same(3, $resources['targetFragmentCount']);
        $t->same(['review', 'sidecar', 'ext'], $resources['targetFragments']);
        $t->same(['ext' => 1, 'review' => 1, 'sidecar' => 1], $resources['targetFragmentCounts']);
        $t->same($resources['targetReferenceSuffixCount'], $summary['packageRootRelationshipResourceTargetReferenceSuffixCount']);
        $t->same($resources['targetReferenceSuffixes'], $summary['packageRootRelationshipResourceTargetReferenceSuffixes']);
        $t->same($resources['targetFragmentCount'], $summary['packageRootRelationshipResourceTargetFragmentCount']);
        $t->same($resources['targetFragments'], $summary['packageRootRelationshipResourceTargetFragments']);
        $t->same($resources['targetFragmentCounts'], $summary['packageRootRelationshipResourceTargetFragmentCounts']);
        $t->same(['rPackageAudit', 'rPackageSidecar', 'rExternalPackageAudit'], array_column($resources['targetsWithReferenceSuffixes'], 'id'));
        $t->same(['docProps/review-audit.xml', 'docProps/sidecar-audit.xml', null], array_column($resources['targetsWithReferenceSuffixes'], 'targetPart'));
        $t->same(3, $resources['targetQueryParameterCount']);
        $t->same(2, $resources['targetQueryParameterRelationshipCount']);
        $t->same(['remote' => 1, 'slot' => 2], $resources['targetQueryParameterNameCounts']);
        $t->same([
            'remote' => ['1' => 1],
            'slot' => ['copy' => 1, 'root' => 1],
        ], $resources['targetQueryParameterValueCounts']);
        $t->same(['rPackageAudit', 'rExternalPackageAudit'], array_column($resources['targetRelationshipsWithQueryParameters'], 'id'));
        $t->same('slot=root&slot=copy', $audit['targetQuery']);
        $t->same('review', $audit['targetFragment']);
        $t->same('?slot=root&slot=copy#review', $audit['targetReferenceSuffix']);
        $t->same(2, $audit['targetQueryParameterCount']);
        $t->same(null, $external['targetPart']);
        $t->same('ext', $external['targetFragment']);
        $t->same(3, $sidecar['targetRelationshipRecordCount']);
        $t->same(3, $resources['targetRelationshipTargetReferenceSuffixCount']);
        $t->same(['?variant=copy#asset', '#missing', '?remote=1#remote'], $resources['targetRelationshipTargetReferenceSuffixes']);
        $t->same(3, $resources['targetRelationshipTargetFragmentCount']);
        $t->same(['asset', 'missing', 'remote'], $resources['targetRelationshipTargetFragments']);
        $t->same(['asset' => 1, 'missing' => 1, 'remote' => 1], $resources['targetRelationshipTargetFragmentCounts']);
        $t->same($resources['targetRelationshipTargetReferenceSuffixCount'], $summary['packageRootRelationshipResourceTargetRelationshipTargetReferenceSuffixCount']);
        $t->same($resources['targetRelationshipTargetReferenceSuffixes'], $summary['packageRootRelationshipResourceTargetRelationshipTargetReferenceSuffixes']);
        $t->same($resources['targetRelationshipTargetFragmentCount'], $summary['packageRootRelationshipResourceTargetRelationshipTargetFragmentCount']);
        $t->same($resources['targetRelationshipTargetFragments'], $summary['packageRootRelationshipResourceTargetRelationshipTargetFragments']);
        $t->same($resources['targetRelationshipTargetFragmentCounts'], $summary['packageRootRelationshipResourceTargetRelationshipTargetFragmentCounts']);
        $t->same(['rPreview', 'rMissingPreview', 'rExternalPreview'], array_column($resources['targetRelationshipTargetsWithReferenceSuffixes'], 'id'));
        $t->same([
            'docProps/sidecar-audit.xml',
            'docProps/sidecar-audit.xml',
            'docProps/sidecar-audit.xml',
        ], array_column($resources['targetRelationshipTargetsWithReferenceSuffixes'], 'sourcePart'));

        $preview = $sidecarRelationships['rPreview'];
        $missing = $sidecarRelationships['rMissingPreview'];
        $remote = $sidecarRelationships['rExternalPreview'];

        $t->same('docProps/media/preview.png', $preview['targetPart']);
        $t->same('variant=copy', $preview['targetQuery']);
        $t->same('asset', $preview['targetFragment']);
        $t->same('?variant=copy#asset', $preview['targetReferenceSuffix']);
        $t->same(false, $missing['exists']);
        $t->same('missing', $missing['targetFragment']);
        $t->same(true, $remote['external']);
        $t->same('remote', $remote['targetFragment']);
    },
];

return $tests;

/**
 * @return array<string, string>
 */
function docx_package_root_relationship_resource_reference_suffix_fixture_parts(): array
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
  <Relationship Id="rPackageAudit" Type="{$resourceType}" Target="docProps/review-audit.xml?slot=root&amp;slot=copy#review"/>
  <Relationship Id="rPackageSidecar" Type="{$resourceType}" Target="docProps/sidecar-audit.xml#sidecar"/>
  <Relationship Id="rExternalPackageAudit" Type="{$resourceType}" Target="https://example.test/review-audit.xml?remote=1#ext" TargetMode="External"/>
  <Relationship Id="rMissingPackageAudit" Type="{$resourceType}" Target="docProps/missing-audit.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package root relationship suffix fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/review-audit.xml' => '<review-resource>root audit</review-resource>',
        'docProps/sidecar-audit.xml' => '<review-resource>sidecar audit</review-resource>',
        'docProps/_rels/sidecar-audit.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rPreview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/preview.png?variant=copy#asset"/>
  <Relationship Id="rMissingPreview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-preview.png#missing"/>
  <Relationship Id="rExternalPreview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review/preview.png?remote=1#remote" TargetMode="External"/>
</Relationships>
XML,
        'docProps/media/preview.png' => 'preview bytes',
    ];
}
