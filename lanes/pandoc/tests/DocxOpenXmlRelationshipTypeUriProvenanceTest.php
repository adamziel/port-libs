<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship Type URI provenance for package review handoff' => static function (TestRunner $t): void {
        $officeDocumentRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
        $coreRel = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
        $customReviewRel = 'http://example.test/package-review/2026/relationships/review-feed';
        $archiveRel = 'urn:example:docx:relationships:archive';
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $headerRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header';
        $hyperlinkRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';

        $document = (new DocxOpenXmlReader())->readPackage(docx_relationship_type_uri_provenance_fixture_parts());
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $relationshipTypes = $package['relationshipTypes'];
        $relationships = $package['relationshipParts']['word/_rels/document.xml.rels']['relationships'];
        $namespaces = [];
        foreach ($summary['relationshipTypeUriNamespaces'] as $namespace) {
            $namespaces[$namespace['namespace']] = $namespace;
        }

        $t->same(7, $summary['relationshipTypeUriDeclarationCount']);
        $t->same(0, $summary['relationshipTypeMissingDeclarationCount']);
        $t->same(['absolute-uri' => 7], $summary['relationshipTypeUriKindCounts']);
        $t->same(['http' => 6, 'urn' => 1], $summary['relationshipTypeUriSchemeCounts']);
        $t->same([
            '(none)' => 1,
            'example.test' => 1,
            'schemas.openxmlformats.org' => 5,
        ], $summary['relationshipTypeUriHostCounts']);
        $t->same([
            'example/docx/relationships' => 1,
            'officeDocument/2006/relationships' => 4,
            'package-review/2026/relationships' => 1,
            'package/2006/relationships/metadata' => 1,
        ], $summary['relationshipTypeUriPathPrefixCounts']);
        $t->same([
            'archive' => 1,
            'core-properties' => 1,
            'header' => 1,
            'hyperlink' => 1,
            'image' => 1,
            'officeDocument' => 1,
            'review-feed' => 1,
        ], $summary['relationshipTypeUriLeafCounts']);
        $t->same([
            'http://example.test/package-review/2026/relationships' => 1,
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships' => 4,
            'http://schemas.openxmlformats.org/package/2006/relationships/metadata' => 1,
            'urn:example/docx/relationships' => 1,
        ], $summary['relationshipTypeUriNamespaceCounts']);

        $officeNamespace = $namespaces['http://schemas.openxmlformats.org/officeDocument/2006/relationships'];
        $t->same(4, $officeNamespace['relationshipCount']);
        $t->same([
            'header' => 1,
            'hyperlink' => 1,
            'image' => 1,
            'officeDocument' => 1,
        ], $officeNamespace['leafCounts']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $officeNamespace['relationshipParts']);

        $t->same('absolute-uri', $relationshipTypes[$imageRel]['typeUriKind']);
        $t->same('http', $relationshipTypes[$imageRel]['typeUriScheme']);
        $t->same('schemas.openxmlformats.org', $relationshipTypes[$imageRel]['typeUriHost']);
        $t->same('/officeDocument/2006/relationships/image', $relationshipTypes[$imageRel]['typeUriPath']);
        $t->same(['officeDocument', '2006', 'relationships', 'image'], $relationshipTypes[$imageRel]['typeUriPathSegments']);
        $t->same('officeDocument/2006/relationships', $relationshipTypes[$imageRel]['typeUriPathPrefix']);
        $t->same('image', $relationshipTypes[$imageRel]['typeUriLeaf']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships', $relationshipTypes[$imageRel]['typeUriNamespace']);

        $t->same('urn', $relationshipTypes[$archiveRel]['typeUriScheme']);
        $t->same(null, $relationshipTypes[$archiveRel]['typeUriHost']);
        $t->same('example:docx:relationships:archive', $relationshipTypes[$archiveRel]['typeUriPath']);
        $t->same(['example', 'docx', 'relationships', 'archive'], $relationshipTypes[$archiveRel]['typeUriPathSegments']);
        $t->same('example/docx/relationships', $relationshipTypes[$archiveRel]['typeUriPathPrefix']);
        $t->same('urn:example/docx/relationships', $relationshipTypes[$archiveRel]['typeUriNamespace']);

        $t->same('example.test', $relationshipTypes[$customReviewRel]['typeUriHost']);
        $t->same('package-review/2026/relationships', $relationshipTypes[$customReviewRel]['typeUriPathPrefix']);
        $t->same('review-feed', $relationshipTypes[$customReviewRel]['typeUriLeaf']);
        $t->same('http://example.test/package-review/2026/relationships', $relationshipTypes[$customReviewRel]['typeUriNamespace']);

        $t->same($officeDocumentRel, $relationshipTypes[$officeDocumentRel]['type']);
        $t->same($coreRel, $relationshipTypes[$coreRel]['type']);
        $t->same($headerRel, $relationshipTypes[$headerRel]['type']);
        $t->same($hyperlinkRel, $relationshipTypes[$hyperlinkRel]['type']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships', $relationships['rImage']['typeUriNamespace']);
        $t->same('image', $relationships['rImage']['typeUriLeaf']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_type_uri_provenance_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rReviewFeed" Type="http://example.test/package-review/2026/relationships/review-feed" Target="custom/review-feed.xml"/>
  <Relationship Id="rArchive" Type="urn:example:docx:relationships:archive" Target="docProps/archive.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>
  <Relationship Id="rExternalLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source" TargetMode="External"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship type URI provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/header1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p><w:r><w:t>Header</w:t></w:r></w:p>
</w:hdr>
XML,
        'docProps/core.xml' => '<coreProperties/>',
        'docProps/archive.xml' => '<archive/>',
        'custom/review-feed.xml' => '<review-feed/>',
        'word/media/review.png' => 'image bytes',
    ];
}
