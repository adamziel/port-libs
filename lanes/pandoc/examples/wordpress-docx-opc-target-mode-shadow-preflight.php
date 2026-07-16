<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\OpcRelationships;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
XML;

$rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

$documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdExternalLocalImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-image.PNG" TargetMode="External"/>
  <Relationship Id="rIdExternalLocalCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml#packet" TargetMode="External"/>
  <Relationship Id="rIdExternalAbsolutePackagePath" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="/word/styles.xml" TargetMode="External"/>
  <Relationship Id="rIdExternalRemoteRelative" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="../review/source.html#packet" TargetMode="External"/>
</Relationships>
XML;

$graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
    ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
    ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
    ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
    ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
]));

$targetModeShadowPreflight = [];
foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
    if (!$target['external']) {
        continue;
    }

    $targetModeShadowPreflight[$target['id']] = [
        'id' => $target['id'],
        'target' => $target['target'],
        'kind' => $target['externalTargetKind'],
        'requiresBaseUri' => $target['externalTargetRequiresBaseUri'],
        'rewriteBasePart' => $target['externalTargetRewriteBasePart'],
        'rewriteReason' => $target['externalTargetRewriteReason'],
        'valid' => $target['valid'],
        'issues' => $target['issues'],
    ];
}

$shadowedTargets = array_values(array_filter(
    $targetModeShadowPreflight,
    static fn (array $target): bool => in_array('external-target-matches-package-part', $target['issues'], true),
));

$summary = [
    'targetModeShadowPreflight' => $targetModeShadowPreflight,
    'wordpressImport' => [
        'reviewStatus' => $shadowedTargets === [] ? 'target-modes-ready' : 'target-mode-review',
        'externalPackagePartShadowCount' => count($shadowedTargets),
        'externalPackagePartShadows' => $shadowedTargets,
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['wordpressImport']['reviewStatus'] ?? null) !== 'target-mode-review') {
        throw new RuntimeException('OPC target-mode shadow preflight did not request review');
    }

    if (($summary['wordpressImport']['externalPackagePartShadowCount'] ?? null) !== 3) {
        throw new RuntimeException('OPC target-mode shadow preflight did not count local package-part shadows');
    }

    if (($summary['targetModeShadowPreflight']['rIdExternalLocalImage']['issues'] ?? null) !== ['external-target-matches-package-part']) {
        throw new RuntimeException('OPC target-mode shadow preflight missed the local image shadow');
    }

    if (($summary['targetModeShadowPreflight']['rIdExternalAbsolutePackagePath']['issues'] ?? null) !== ['external-target-matches-package-part']) {
        throw new RuntimeException('OPC target-mode shadow preflight missed the absolute package-path shadow');
    }

    if (($summary['targetModeShadowPreflight']['rIdExternalRemoteRelative']['valid'] ?? null) !== true) {
        throw new RuntimeException('OPC target-mode shadow preflight rejected a remote relative external target');
    }

    echo "wordpress-docx-opc-target-mode-shadow-preflight self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
