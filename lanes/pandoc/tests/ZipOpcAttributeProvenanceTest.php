<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\ZipPackage;

return [
    'carries ZIP DOS and internal attribute provenance through package and OPC manifests' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="txt" ContentType="text/plain"/>
</Types>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>', 'compressionMethod' => 0],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>attribute review</w:p></w:document>',
                'compressionMethod' => 0,
                'externalAttributes' => 0x81a40022,
                'internalAttributes' => 0x0001,
            ],
            [
                'name' => 'word/media/cover.png',
                'data' => "image bytes\n",
                'compressionMethod' => 0,
                'externalAttributes' => 0x81a40024,
                'internalAttributes' => 0x0002,
            ],
            [
                'name' => 'word/media/volume.txt',
                'data' => 'volume label style package part',
                'compressionMethod' => 0,
                'externalAttributes' => 0x81a40029,
                'internalAttributes' => 0x0003,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'compressionMethod' => 0,
                'externalAttributes' => 0x41ed0010,
            ],
        ]);

        $zipManifest = $package->packageManifestPreflight();
        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $rawSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($package->bytes());

        $entryByName = [];
        foreach ($summary['entries'] as $entry) {
            $entryByName[$entry['entryName']] = $entry;
        }

        $t->same('zip-package-dos-attribute-provenance-v1', $zipManifest['dosAttributeProvenanceVersion']);
        $t->same(4, $zipManifest['dosAttributeEntryCount']);
        $t->same(true, $zipManifest['hasDosAttributeEntries']);
        $t->same(3, $zipManifest['hiddenSystemOrVolumeLabelEntryCount']);
        $t->same(true, $zipManifest['hasHiddenSystemOrVolumeLabelEntries']);
        $t->same([
            'archive' => 3,
            'directory' => 1,
            'hidden' => 1,
            'read-only' => 1,
            'system' => 1,
            'volume-label' => 1,
        ], $zipManifest['dosAttributeNameCounts']);
        $t->same(['word/document.xml'], $zipManifest['entryNamesByDosAttributeIssue']['dos-hidden-attribute']);
        $t->same(['word/media/cover.png'], $zipManifest['entryNamesByDosAttributeIssue']['dos-system-attribute']);
        $t->same(['word/media/volume.txt'], $zipManifest['entryNamesByDosAttributeIssue']['dos-volume-label-attribute']);
        $t->same(4, $zipManifest['dosAttributeSummaryCount']);

        $t->same('zip-package-internal-attribute-provenance-v1', $zipManifest['internalAttributeProvenanceVersion']);
        $t->same(3, $zipManifest['internalAttributeEntryCount']);
        $t->same(true, $zipManifest['hasInternalAttributeEntries']);
        $t->same(2, $zipManifest['textInternalAttributeEntryCount']);
        $t->same(2, $zipManifest['unknownInternalAttributeEntryCount']);
        $t->same([
            'apparently-text' => 2,
            'unknown-0x0002' => 2,
        ], $zipManifest['internalAttributeNameCounts']);
        $t->same(['word/document.xml', 'word/media/volume.txt'], $zipManifest['entryNamesByInternalAttributeIssue']['internal-text-attribute']);
        $t->same(['word/media/cover.png', 'word/media/volume.txt'], $zipManifest['entryNamesByInternalAttributeIssue']['unknown-internal-file-attribute-bits']);
        $t->same(3, $zipManifest['internalAttributeSummaryCount']);

        $t->same('zip-opc-attribute-provenance-v1', $summary['zipAttributeProvenanceVersion']);
        $t->same($summary['dosAttributeNameCounts'], $rawSummary['dosAttributeNameCounts']);
        $t->same($summary['entryNamesByDosAttributeName'], $rawSummary['entryNamesByDosAttributeName']);
        $t->same($summary['dosAttributeIssueCounts'], $rawSummary['dosAttributeIssueCounts']);
        $t->same($summary['entryNamesByDosAttributeIssue'], $rawSummary['entryNamesByDosAttributeIssue']);
        $t->same($summary['dosAttributeSummaries'], $rawSummary['dosAttributeSummaries']);
        $t->same($summary['internalAttributeNameCounts'], $rawSummary['internalAttributeNameCounts']);
        $t->same($summary['entryNamesByInternalAttributeName'], $rawSummary['entryNamesByInternalAttributeName']);
        $t->same($summary['internalAttributeIssueCounts'], $rawSummary['internalAttributeIssueCounts']);
        $t->same($summary['entryNamesByInternalAttributeIssue'], $rawSummary['entryNamesByInternalAttributeIssue']);
        $t->same($summary['internalAttributeSummaries'], $rawSummary['internalAttributeSummaries']);

        $t->same(['hidden', 'archive'], $entryByName['word/document.xml']['dosAttributeNames']);
        $t->same(['dos-hidden-attribute'], $entryByName['word/document.xml']['dosAttributeIssues']);
        $t->same(['apparently-text'], $entryByName['word/document.xml']['internalAttributeNames']);
        $t->same(['internal-text-attribute'], $entryByName['word/document.xml']['internalAttributeIssues']);
        $t->same(['system', 'archive'], $entryByName['word/media/cover.png']['dosAttributeNames']);
        $t->same(['unknown-0x0002'], $entryByName['word/media/cover.png']['internalAttributeNames']);
        $t->same(['read-only', 'volume-label', 'archive'], $entryByName['word/media/volume.txt']['dosAttributeNames']);
        $t->same(['apparently-text', 'unknown-0x0002'], $entryByName['word/media/volume.txt']['internalAttributeNames']);
        $t->same(['directory'], $entryByName['word/media/']['dosAttributeNames']);

        $t->same(['archive', 'hidden'], $summary['dosAttributeNamesByRole']['xml-part']);
        $t->same(['archive', 'system'], $summary['dosAttributeNamesByRole']['media']);
        $t->same(['archive', 'read-only', 'volume-label'], $summary['dosAttributeNamesByRole']['binary-part']);
        $t->same(['directory'], $summary['dosAttributeNamesByRole']['directory']);
        $t->same(['apparently-text'], $summary['internalAttributeNamesByHandoffKind']['xml']);
        $t->same(['unknown-0x0002'], $summary['internalAttributeNamesByHandoffKind']['media']);
        $t->same(['apparently-text', 'unknown-0x0002'], $summary['internalAttributeNamesByHandoffKind']['binary']);
    },
];
