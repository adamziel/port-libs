<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'records docx relationship target fragment provenance mapped case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedDocxRelationshipTargetFragmentProvenanceCases'] ?? null);
        $t->same(55, $manifest['docxRelationshipTargetFragmentProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedDocxRelationshipTargetFragmentProvenanceCases'] ?? null);
        $t->same(55, $manifest['benchmarkDenominator']['breakdown']['docxRelationshipTargetFragmentProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedDocxRelationshipTargetFragmentProvenanceCases'] ?? null);
        $t->same(55, $manifest['benchmarkDenominator']['inventory']['docxRelationshipTargetFragmentProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedDocxRelationshipTargetFragmentProvenanceCases'] ?? null);
        $t->same(55, $manifest['inventory']['docxRelationshipTargetFragmentProvenanceAssertions'] ?? null);
    },

    'summarizes docx relationship target fragments for package review handoff' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_relationship_target_fragment_provenance_fixture_parts());
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $rootRelationships = $package['relationshipParts']['_rels/.rels']['relationships'];
        $documentRelationships = $package['relationshipParts']['word/_rels/document.xml.rels']['relationships'];
        $fragmentSummaries = array_column($summary['relationshipTargetFragmentSummaries'], null, 'fragment');
        $fragmentTargetIds = array_column($summary['relationshipTargetsWithFragments'], 'id');

        $t->same(6, $summary['relationshipTargetReferenceSuffixCount']);
        $t->same(6, $summary['relationshipTargetFragmentCount']);
        $t->same(5, $summary['relationshipTargetFragmentRelationshipCount']);
        $t->same(4, $summary['relationshipTargetFragmentValueCount']);
        $t->same(['main', 'asset', 'settings', 'remote'], $summary['relationshipTargetFragments']);
        $t->same([
            'asset' => 2,
            'main' => 1,
            'remote' => 1,
            'settings' => 1,
        ], $summary['relationshipTargetFragmentCounts']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $summary['relationshipPartsWithTargetFragments']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $summary['relationshipPartsWithTargetReferenceSuffix']);
        $t->same([
            'rDocument',
            'rImage',
            'rMissingImage',
            'rSettings',
            'rRemoteReview',
        ], $fragmentTargetIds);
        $t->same([
            'rDocument',
            'rImage',
            'rMissingImage',
            'rSettings',
            'rRemoteReview',
            'rEmptyFragment',
        ], array_column($summary['relationshipTargetsWithReferenceSuffix'], 'id'));

        $t->same('main', $rootRelationships['rDocument']['targetFragment']);
        $t->same('asset', $documentRelationships['rImage']['targetFragment']);
        $t->same('asset', $documentRelationships['rMissingImage']['targetFragment']);
        $t->same(false, $documentRelationships['rMissingImage']['exists']);
        $t->same('remote', $documentRelationships['rRemoteReview']['targetFragment']);
        $t->same(true, $documentRelationships['rRemoteReview']['external']);
        $t->same('', $documentRelationships['rEmptyFragment']['targetFragment']);
        $t->same('#', $documentRelationships['rEmptyFragment']['targetReferenceSuffix']);

        $t->same(['asset', 'main', 'remote', 'settings'], array_keys($fragmentSummaries));
        $t->true(!isset($fragmentSummaries['']), 'empty fragment markers are not grouped as fragment values');
        $t->same(false, in_array('rEmptyFragment', $fragmentTargetIds, true));

        $asset = $fragmentSummaries['asset'];
        $t->same(2, $asset['relationshipCount']);
        $t->same(2, $asset['internalRelationshipCount']);
        $t->same(0, $asset['externalRelationshipCount']);
        $t->same(1, $asset['existingTargetCount']);
        $t->same(1, $asset['missingTargetCount']);
        $t->same(['rImage', 'rMissingImage'], $asset['relationshipIds']);
        $t->same(['word/media/missing.png', 'word/media/review.png'], $asset['targetParts']);
        $t->same(['#asset'], $asset['targetReferenceSuffixes']);
        $t->same([
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image' => 2,
        ], $asset['relationshipTypeCounts']);
        $t->same(['(implicit-internal)' => 2], $asset['targetModeCounts']);
        $t->same(['image/png' => 2], $asset['contentTypeBaseCounts']);
        $t->same(['default' => 2], $asset['contentTypeSourceCounts']);

        $main = $fragmentSummaries['main'];
        $t->same(1, $main['relationshipCount']);
        $t->same(['/'], $main['sourceParts']);
        $t->same(['_rels/.rels'], $main['relationshipParts']);
        $t->same(['word/document.xml'], $main['targetParts']);
        $t->same(['override' => 1], $main['contentTypeSourceCounts']);

        $remote = $fragmentSummaries['remote'];
        $t->same(1, $remote['externalRelationshipCount']);
        $t->same(0, $remote['internalRelationshipCount']);
        $t->same(1, $remote['missingTargetCount']);
        $t->same([], $remote['targetParts']);
        $t->same(['(external)' => 1], $remote['contentTypeBaseCounts']);
        $t->same(['(external)' => 1], $remote['contentTypeSourceCounts']);

        $settings = $fragmentSummaries['settings'];
        $t->same(1, $settings['existingTargetCount']);
        $t->same(['word/settings.xml'], $settings['targetParts']);
        $t->same(['override' => 1], $settings['contentTypeSourceCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_fragment_provenance_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml#main"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png#asset"/>
  <Relationship Id="rMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png#asset"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml#settings"/>
  <Relationship Id="rRemoteReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review#remote" TargetMode="External"/>
  <Relationship Id="rEmptyFragment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/empty.png#"/>
</Relationships>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Relationship target fragment provenance fixture</dc:title>
</cp:coreProperties>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target fragment provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/settings.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:updateFields w:val="true"/>
</w:settings>
XML,
        'word/media/review.png' => 'review image bytes',
        'word/media/empty.png' => 'empty fragment image bytes',
    ];
}
