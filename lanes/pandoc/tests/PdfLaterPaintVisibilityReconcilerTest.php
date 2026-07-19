<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PdfLaterPaintVisibilityReconciler;

$digest = static function (mixed $value, bool $preserveZeroFraction = false): string {
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if ($preserveZeroFraction) {
        $flags |= JSON_PRESERVE_ZERO_FRACTION;
    }
    $encoded = json_encode($value, $flags);

    return hash('sha256', is_string($encoded) ? $encoded : serialize($value));
};

$sourceEdge = static function (array $identity) use ($digest): array {
    return ['id' => 'pdf-source-edge-' . substr($digest($identity), 0, 32)] + $identity;
};

$sourceNode = static function (
    string $type,
    string $text,
    array $edges
) use ($digest): AstNode {
    $sourceIds = [];
    foreach ($edges as $edge) {
        if (!in_array($edge['sourceLineId'], $sourceIds, true)) {
            $sourceIds[] = $edge['sourceLineId'];
        }
    }
    $nodeId = 'pdf-source-node-' . substr($digest([
        'type' => $type,
        'sourceLineEdges' => $edges,
    ]), 0, 32);

    return new AstNode($type, [
        'text' => $text,
        'sourceNodeId' => $nodeId,
        'sourceLineIds' => $sourceIds,
        'sourceLineEdges' => $edges,
    ], [new AstNode('text', ['text' => $text])]);
};

$resignRisk = static function (array $risk) use ($digest): array {
    unset($risk['id'], $risk['riskDigest']);
    $riskDigest = $digest($risk, true);

    return ['id' => 'pdf-later-paint-risk-' . substr($riskDigest, 0, 32)]
        + $risk
        + ['riskDigest' => $riskDigest];
};

$resignProof = static function (array $proof) use ($digest): array {
    unset($proof['proofDigest']);

    return $proof + ['proofDigest' => $digest($proof, true)];
};

$resignEvidence = static function (array $evidence) use ($digest): array {
    unset($evidence['proofDigest']);

    return $evidence + ['proofDigest' => $digest($evidence, true)];
};

$resignVisibility = static function (array $visibility) use ($digest): array {
    $risks = is_array($visibility['laterPaintRisks'] ?? null)
        ? $visibility['laterPaintRisks']
        : [];
    $visibility['laterPaintRisksDigest'] = $digest($risks, true);

    return $visibility;
};

$fixture = static function () use (
    $digest,
    $sourceEdge,
    $sourceNode,
    $resignRisk,
    $resignProof,
    $resignEvidence
): array {
    $sourceSha256 = hash('sha256', 'synthetic-later-paint-source');
    $artifactId = 'source-artifact';
    $counterpartId = 'source-counterpart';
    $artifactText = 'aves Lives';
    $artifactProjection = 'avesLives';
    $counterpartText = 'Hand Hygiene Saves Lives';
    $counterpartProjection = 'HandHygieneSavesLives';
    $sourceLineItems = [
        ['id' => $artifactId, 'text' => $artifactText, 'page' => 1, 'stream' => 1],
        ['id' => $counterpartId, 'text' => $counterpartText, 'page' => 1, 'stream' => 1],
    ];

    $risk = $resignRisk([
        'version' => 1,
        'sourceSha256' => $sourceSha256,
        'page' => 1,
        'sourceOccurrenceIndex' => 0,
        'sourceStream' => 1,
        'sourceRange' => ['start' => 0, 'end' => strlen($artifactProjection)],
        'sourceProjectionDigest' => hash('sha256', $artifactProjection),
        'textOperation' => 5,
        'textBounds' => ['x1' => 0.0, 'y1' => 0.0, 'x2' => 10.0, 'y2' => 10.0],
        'paintOperation' => 10,
        'paintStream' => 1,
        'paintOperator' => 'Do',
        'paintResource' => 'Im7',
        'paintObject' => 77,
        'paintSubtype' => 'Image',
        'paintBounds' => ['x1' => 5.0, 'y1' => 0.0, 'x2' => 15.0, 'y2' => 10.0],
    ]);
    $visibility = [
        'policy' => 'visible-painted-text-v1',
        'complete' => false,
        'unresolvedRuns' => 0,
        'unresolvedReasons' => ['later-paint-occlusion'],
        'unresolvedReasonCounts' => ['later-paint-occlusion' => 1],
        'unresolvedOcclusionRiskRuns' => 1,
        'laterPaintRisks' => [$risk],
        'laterPaintRiskCount' => 1,
        'laterPaintRiskRecordedCount' => 1,
        'laterPaintRiskUnboundCount' => 0,
        'laterPaintRiskTruncatedCount' => 0,
        'laterPaintRisksDigest' => $digest([$risk], true),
        'pages' => [[
            'page' => 1,
            'unresolvedRuns' => 0,
            'unresolvedOcclusionRiskRuns' => 1,
        ]],
    ];

    $counterpartEdge = [
        'sourceLineId' => $counterpartId,
        'startByte' => 0,
        'endByte' => strlen($counterpartProjection),
    ];
    $block = $sourceNode('paragraph', $counterpartText, [$counterpartEdge]);
    $destinationNodeId = $block->attr('sourceNodeId');

    $counterpartEvidence = [
        'sourceOccurrenceId' => $counterpartId,
        'sourceIndex' => 1,
        'sourceLocalIndex' => 1,
        'page' => 1,
        'stream' => 1,
        'projectionDigest' => hash('sha256', $counterpartProjection),
        'matchedTokenOffset' => 2,
        'matchedTokenDigest' => hash('sha256', 'Saves' . "\0" . 'Lives'),
    ];
    $artifactEvidence = $resignEvidence([
        'version' => 1,
        'method' => 'whole-source-clipped-display-artifact',
        'sourceSha256' => $sourceSha256,
        'sourceOccurrenceId' => $artifactId,
        'sourceIndex' => 0,
        'sourceLocalIndex' => 0,
        'page' => 1,
        'stream' => 1,
        'range' => [
            'sourceIndex' => 0,
            'sourceStart' => 0,
            'sourceEnd' => strlen($artifactProjection),
        ],
        'significantBytes' => strlen($artifactProjection),
        'projectionDigest' => hash('sha256', $artifactProjection),
        'sourceBounds' => ['x1' => 0.0, 'y1' => 0.0, 'x2' => 10.0, 'y2' => 10.0],
        'layoutBounds' => ['x1' => 0.0, 'y1' => 0.0, 'x2' => 10.0, 'y2' => 10.0],
        'pageMedianFontSize' => 10.0,
        'fontSize' => 32.0,
        'fontSizeRatio' => 3.2,
        'wholeOccurrenceProofDigest' => hash('sha256', 'whole-occurrence-proof'),
        'completeDisplayCounterpart' => $counterpartEvidence,
    ]);
    $boundDispositions = [
        $artifactId => [
            'disposition' => 'artifact',
            'reason' => 'The complete source occurrence is a clipped display artifact.',
            'evidence' => $artifactEvidence,
            'sourceMapping' => [
                'status' => 'disposition',
                'mappingMode' => 'explicit-disposition',
            ],
        ],
        $counterpartId => [
            'disposition' => 'boundary-repair',
            'reason' => 'The complete counterpart is bound to final output.',
            'sourceMapping' => [
                'status' => 'output',
                'mappingMode' => 'exact-authorized-scope',
                'destinationNodeIds' => [$destinationNodeId],
                'destinationInlineIds' => [],
                'scopeId' => 'synthetic-scope',
            ],
        ],
    ];

    $sourceEdges = [
        $sourceEdge([
            'sourceOccurrenceId' => $artifactId,
            'page' => 1,
            'disposition' => 'artifact',
            'target' => 'disposition',
            'mappingMode' => 'explicit-disposition',
            'destinationNodeIds' => [],
            'destinationInlineIds' => [],
            'orderScopeId' => null,
        ]),
        $sourceEdge([
            'sourceOccurrenceId' => $counterpartId,
            'page' => 1,
            'disposition' => 'boundary-repair',
            'target' => 'output',
            'mappingMode' => 'exact-authorized-scope',
            'destinationNodeIds' => [$destinationNodeId],
            'destinationInlineIds' => [],
            'orderScopeId' => 'synthetic-scope',
        ]),
    ];
    $sourceDisposition = [
        'version' => 2,
        'sourceOccurrenceCount' => count($sourceEdges),
        'sourceEdges' => $sourceEdges,
        'sourceEdgeCount' => count($sourceEdges),
        'sourceEdgeMappingComplete' => true,
        'sourceEdgeDigest' => $digest($sourceEdges),
    ];

    $placement = [
        'id' => 'pdf-image-p1-n3-o77',
        'kind' => 'image-xobject',
        'page' => 1,
        'contentStream' => 1,
        'paintOrder' => 3,
        'object' => 77,
        'resource' => 'Im7',
        'bbox' => $risk['paintBounds'],
        'visible' => true,
        'placementEligible' => true,
    ];
    $placementDigest = $digest([
        'id' => $placement['id'],
        'kind' => $placement['kind'],
        'page' => $placement['page'],
        'contentStream' => $placement['contentStream'],
        'paintOrder' => $placement['paintOrder'],
        'object' => $placement['object'],
        'resource' => $placement['resource'],
        'bbox' => $placement['bbox'],
    ], true);
    $mediaProof = $resignProof([
        'version' => 2,
        'method' => 'whole-source-clipped-display-artifact-media-anchor',
        'sourceSha256' => $sourceSha256,
        'page' => 1,
        'artifactSourceOccurrenceId' => $artifactId,
        'artifactProjectionDigest' => hash('sha256', $artifactProjection),
        'counterpartSourceOccurrenceId' => $counterpartId,
        'counterpartProjectionDigest' => hash('sha256', $counterpartProjection),
        'counterpartDestinationNodeId' => $destinationNodeId,
        'laterPaintRiskId' => $risk['id'],
        'laterPaintRiskDigest' => $risk['riskDigest'],
        'laterPaintOperation' => $risk['paintOperation'],
        'laterPaintOperator' => $risk['paintOperator'],
        'laterPaintResource' => $risk['paintResource'],
        'laterPaintObject' => $risk['paintObject'],
        'laterPaintPlacementId' => $placement['id'],
        'laterPaintPlacementDigest' => $placementDigest,
        'artifactProofDigest' => $artifactEvidence['proofDigest'],
    ]);

    return [
        'sourceSha256' => $sourceSha256,
        'visibility' => $visibility,
        'sourceLineItems' => $sourceLineItems,
        'sourceDisposition' => $sourceDisposition,
        'boundDispositions' => $boundDispositions,
        'mediaProofs' => [$mediaProof],
        'mediaProofTruncatedCount' => 0,
        'imagePlacements' => [$placement],
        'blocks' => [$block],
    ];
};

$reconcile = static fn (array $case): array => PdfLaterPaintVisibilityReconciler::reconcile(
    $case['sourceSha256'],
    $case['visibility'],
    $case['sourceLineItems'],
    $case['sourceDisposition'],
    $case['boundDispositions'],
    $case['mediaProofs'],
    $case['mediaProofTruncatedCount'],
    $case['imagePlacements'],
    $case['blocks']
);

return [
    'later paint visibility reconciler clears only the exact signed graph' => static function (
        TestRunner $t
    ) use ($fixture, $reconcile): void {
        $result = $reconcile($fixture());

        $t->same(false, $result['rawComplete']);
        $t->same(true, $result['complete']);
        $t->same(true, $result['reconciled']);
        $t->same(1, $result['riskCount']);
        $t->same(1, $result['reconciledRiskCount']);
        $t->same(0, $result['unresolvedRiskCount']);
        $t->same(null, $result['failureReason']);
        $t->true(preg_match('/^[a-f0-9]{64}$/D', $result['proofDigest']) === 1);
    },

    'later paint visibility reconciler rejects duplicate and truncated inventories' => static function (
        TestRunner $t
    ) use ($fixture, $reconcile, $resignVisibility): void {
        $duplicateRisk = $fixture();
        $risk = $duplicateRisk['visibility']['laterPaintRisks'][0];
        $duplicateRisk['visibility']['laterPaintRisks'][] = $risk;
        $duplicateRisk['visibility']['laterPaintRiskCount'] = 2;
        $duplicateRisk['visibility']['laterPaintRiskRecordedCount'] = 2;
        $duplicateRisk['visibility']['unresolvedOcclusionRiskRuns'] = 2;
        $duplicateRisk['visibility']['unresolvedReasonCounts']['later-paint-occlusion'] = 2;
        $duplicateRisk['visibility']['pages'][0]['unresolvedOcclusionRiskRuns'] = 2;
        $duplicateRisk['visibility'] = $resignVisibility($duplicateRisk['visibility']);
        $t->same(false, $reconcile($duplicateRisk)['complete']);

        $duplicateProof = $fixture();
        $duplicateProof['mediaProofs'][] = $duplicateProof['mediaProofs'][0];
        $t->same(false, $reconcile($duplicateProof)['complete']);

        $riskTruncated = $fixture();
        $riskTruncated['visibility']['laterPaintRiskTruncatedCount'] = 1;
        $t->same(false, $reconcile($riskTruncated)['complete']);

        $mediaTruncated = $fixture();
        $mediaTruncated['mediaProofTruncatedCount'] = 1;
        $t->same(false, $reconcile($mediaTruncated)['complete']);
    },

    'later paint visibility reconciler rejects resigned source and proof tampering' => static function (
        TestRunner $t
    ) use ($fixture, $reconcile, $resignRisk, $resignVisibility, $resignProof): void {
        $riskTamper = $fixture();
        $risk = $riskTamper['visibility']['laterPaintRisks'][0];
        $risk['sourceSha256'] = hash('sha256', 'different-source');
        $risk = $resignRisk($risk);
        $riskTamper['visibility']['laterPaintRisks'] = [$risk];
        $riskTamper['visibility'] = $resignVisibility($riskTamper['visibility']);
        $t->same(false, $reconcile($riskTamper)['complete']);

        $proofTamper = $fixture();
        $proof = $proofTamper['mediaProofs'][0];
        $proof['laterPaintOperation']++;
        $proofTamper['mediaProofs'] = [$resignProof($proof)];
        $t->same(false, $reconcile($proofTamper)['complete']);

        $digestTamper = $fixture();
        $digestTamper['visibility']['laterPaintRisksDigest'] = str_repeat('0', 64);
        $t->same(false, $reconcile($digestTamper)['complete']);
    },

    'later paint visibility reconciler rejects wrong exact paint and placement' => static function (
        TestRunner $t
    ) use ($fixture, $reconcile, $resignProof): void {
        $wrongResource = $fixture();
        $proof = $wrongResource['mediaProofs'][0];
        $proof['laterPaintResource'] = 'Im8';
        $wrongResource['mediaProofs'] = [$resignProof($proof)];
        $t->same(false, $reconcile($wrongResource)['complete']);

        $wrongObject = $fixture();
        $proof = $wrongObject['mediaProofs'][0];
        $proof['laterPaintObject'] = 78;
        $wrongObject['mediaProofs'] = [$resignProof($proof)];
        $t->same(false, $reconcile($wrongObject)['complete']);

        $wrongPlacement = $fixture();
        $proof = $wrongPlacement['mediaProofs'][0];
        $proof['laterPaintPlacementId'] = 'pdf-image-p1-n4-o77';
        $wrongPlacement['mediaProofs'] = [$resignProof($proof)];
        $t->same(false, $reconcile($wrongPlacement)['complete']);

        $ineligiblePlacement = $fixture();
        $ineligiblePlacement['imagePlacements'][0]['placementEligible'] = false;
        $t->same(false, $reconcile($ineligiblePlacement)['complete']);
    },

    'later paint visibility reconciler rejects missing duplicate forged and nested live edges' => static function (
        TestRunner $t
    ) use ($fixture, $reconcile): void {
        $missing = $fixture();
        $missing['blocks'] = [];
        $t->same(false, $reconcile($missing)['complete']);

        $duplicate = $fixture();
        $duplicate['blocks'][] = $duplicate['blocks'][0];
        $t->same(false, $reconcile($duplicate)['complete']);

        $forgedIds = $fixture();
        $block = $forgedIds['blocks'][0];
        $attrs = $block->attrs;
        $attrs['sourceLineIds'][] = 'forged-source';
        $forgedIds['blocks'] = [new AstNode($block->type, $attrs, $block->children)];
        $t->same(false, $reconcile($forgedIds)['complete']);

        $forgedEdges = $fixture();
        $block = $forgedEdges['blocks'][0];
        $attrs = $block->attrs;
        $attrs['sourceLineEdges'][0]['endByte']--;
        $forgedEdges['blocks'] = [new AstNode($block->type, $attrs, $block->children)];
        $t->same(false, $reconcile($forgedEdges)['complete']);

        $nested = $fixture();
        $nested['blocks'] = [new AstNode('div', [], $nested['blocks'])];
        $t->same(false, $reconcile($nested)['complete']);
    },
];
