<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use InvalidArgumentException;

/**
 * Account for each source text occurrence after PDF semantic inference.
 *
 * PdfTextFidelityLedger intentionally answers aggregate conservation
 * questions. This ledger answers the complementary occurrence question:
 * did every individual source line become output, receive an evidenced
 * suppression/replacement disposition, or remain unresolved?
 *
 * Automatic matching is deliberately conservative. It consumes emitted
 * token and significant-character inventories once, so duplicated source
 * lines cannot all claim the same emitted occurrence. Destructive or visual
 * replacement dispositions must be supplied explicitly with a reason.
 */
final class PdfSourceDispositionLedger
{
    private const SAMPLE_LIMIT = 32;
    private const ORDER_MATCH_CANDIDATE_LIMIT = 256;
    private const SOURCE_BINDING_STATE_LIMIT = 100000;
    private const SEMANTIC_STRUCTURE_PROOF_METHOD = 'exact-standalone-list-marker-to-item';
    private const SEMANTIC_STRUCTURE_MAPPING_MODE = 'exact-semantic-list-marker';

    /** @var array<string, true> */
    private const OUTPUT_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
    ];

    /** @var array<string, true> */
    private const ALLOWED_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
        'actual-text' => true,
        'visual-replacement' => true,
        'artifact' => true,
        'duplicate' => true,
        'running-furniture' => true,
        'original-placeholder' => true,
        'unresolved' => true,
    ];

    /** @var array<string, true> */
    private const EXPLICIT_REASON_REQUIRED = [
        'boundary-repair' => true,
        'semantic-structure' => true,
        'actual-text' => true,
        'visual-replacement' => true,
        'artifact' => true,
        'duplicate' => true,
        'running-furniture' => true,
        'original-placeholder' => true,
    ];

    /**
     * Validate a page-local order scope whose source occurrences may each
     * contribute several separated token ranges after column reconstruction.
     * The same bounded solver used by the binding path must find exactly one
     * complete interleaving; spelling inventory alone is never sufficient.
     *
     * @param array<string,string> $sourceOccurrenceProjections
     */
    public static function hasUniqueTokenInterleavingOrderProof(
        array $sourceOccurrenceProjections,
        string $emittedProjection
    ): bool {
        if ($sourceOccurrenceProjections === []) {
            return false;
        }
        $records = [];
        foreach ($sourceOccurrenceProjections as $id => $projection) {
            if (!is_string($id) || $id === '' || !is_string($projection)) {
                return false;
            }
            $significant = self::significantText($projection);
            if ($significant === '') {
                return false;
            }
            $records[] = [
                'id' => $id,
                'significant' => $significant,
            ];
        }
        $emitted = self::significantText($emittedProjection);
        if ($emitted === '') {
            return false;
        }
        $unit = [
            'records' => $records,
            'projection' => $emitted,
            'start' => 0,
            'end' => strlen($emitted),
            'scopeId' => 'validation',
        ];

        return self::uniqueScopeInterleavingRanges($unit) !== null;
    }

    /**
     * Bind the final AST to stable source occurrences before calculating the
     * disposition ledger. The alignment is exact after insignificant PDF
     * whitespace is removed. A named order-proof scope may interleave its
     * source occurrences, but only when there is exactly one token-level
     * interleaving which produces the declared emitted projection.
     *
     * Destination IDs are hashes of source IDs and numeric spans; neither the
     * node attributes nor the public edge graph contains source text. When an
     * alignment is not unique, no destination is guessed and the affected
     * occurrence remains unresolved.
     *
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string,array<string,mixed>|string> $explicitDispositions
     * @return array{blocks:list<AstNode>,explicitDispositions:array<string,array<string,mixed>|string>,complete:bool,failureReason:?string}
     */
    public static function bindSourceLineItemsToOutput(
        array $sourceLineItems,
        array $blocks,
        array $explicitDispositions = []
    ): array {
        $records = self::sourceBindingRecords($sourceLineItems, $explicitDispositions);
        $projection = self::sourceBindingProjection($records);
        $output = self::sourceBindingOutput($blocks);
        $complete = $projection['failureReason'] === null;
        $failureReason = $projection['failureReason'];

        $ranges = [];
        if ($complete) {
            if (hash_equals($projection['text'], $output['text'])) {
                $ranges = self::directSourceBindingRanges($projection['units']);
                if ($ranges === null) {
                    $complete = false;
                    $failureReason = 'authorized-order-scope-has-ambiguous-output-mapping';
                }
            } else {
                $ranges = self::uniqueInterleavedSourceBindingRanges(
                    $projection['units'],
                    $output['text']
                );
                if ($ranges === null) {
                    $complete = false;
                    $failureReason = 'projected-source-stream-does-not-equal-final-output';
                }
            }
        }

        $decoratedBlocks = $blocks;
        $mappingBySourceId = [];
        if ($complete) {
            $decoration = self::sourceBindingDecoration($blocks, $output, $ranges);
            if ($decoration === null) {
                $complete = false;
                $failureReason = 'final-output-node-spans-could-not-be-bound-exactly';
            } else {
                $decoratedBlocks = $decoration['blocks'];
                $mappingBySourceId = $decoration['mappingBySourceId'];
                unset($decoration, $projection, $output, $ranges);
                $structural = self::sourceBindingSemanticStructureMappings(
                    $records,
                    $decoratedBlocks,
                    $mappingBySourceId
                );
                if ($structural['failureReason'] !== null) {
                    $complete = false;
                    $failureReason = $structural['failureReason'];
                    $decoratedBlocks = $blocks;
                    $mappingBySourceId = [];
                } else {
                    $mappingBySourceId = array_replace(
                        $mappingBySourceId,
                        $structural['mappingBySourceId']
                    );
                    unset($structural, $blocks);
                }
            }
        }

        $boundDispositions = $explicitDispositions;
        foreach ($records as $record) {
            $id = $record['id'];
            $original = $explicitDispositions[$id] ?? null;
            $bound = is_array($original)
                ? $original
                : (is_string($original) ? ['disposition' => $original] : []);
            $disposition = $record['disposition'];
            if (!isset(self::OUTPUT_DISPOSITIONS[$disposition])) {
                $bound['disposition'] = $disposition;
                $bound['sourceMapping'] = [
                    'status' => $disposition === 'unresolved' ? 'unresolved' : 'disposition',
                    'mappingMode' => $disposition === 'unresolved'
                        ? 'unresolved'
                        : 'explicit-disposition',
                ];
                $boundDispositions[$id] = $bound;
                continue;
            }

            $mapping = $mappingBySourceId[$id] ?? null;
            if ($complete && is_array($mapping)) {
                $bound['disposition'] = $disposition;
                $bound['sourceMapping'] = [
                    'status' => 'output',
                    'mappingMode' => $mapping['mappingMode'],
                    'destinationNodeIds' => $mapping['destinationNodeIds'],
                    'destinationInlineIds' => $mapping['destinationInlineIds'],
                    'scopeId' => $mapping['scopeId'],
                ];
                $boundDispositions[$id] = $bound;
                continue;
            }

            // Keep a valid mapped order proof in place so the ledger can name
            // the exact unauthorized segment. Ordinary occurrences without a
            // destination are explicitly unresolved rather than falling back
            // to the global character inventory.
            if (($record['orderProof'] ?? null) === null) {
                $bound = [
                    'disposition' => 'unresolved',
                    'reason' => 'No unique exact source-to-output destination mapping was available.',
                    'evidence' => [
                        'method' => 'exact-source-output-binding',
                        'failureReason' => $failureReason,
                        'requestedDisposition' => $disposition,
                    ],
                    'sourceMapping' => [
                        'status' => 'unresolved',
                        'mappingMode' => 'unresolved',
                    ],
                ];
            } else {
                $bound['sourceMapping'] = [
                    'status' => 'unresolved',
                    'mappingMode' => 'unresolved',
                    'scopeId' => $record['orderProof']['scopeId'],
                ];
            }
            $boundDispositions[$id] = $bound;
        }

        return [
            'blocks' => $decoratedBlocks,
            'explicitDispositions' => $boundDispositions,
            'complete' => $complete,
            'failureReason' => $failureReason,
        ];
    }

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param list<AstNode> $blocks
     * @param array<string, array{disposition:string,reason?:string,evidence?:array<string,mixed>,textProjection?:string,allowOrderChange?:bool,orderProof?:array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string}}|string> $explicitDispositions
     * @return array<string,mixed>
     */
    public static function fromSourceLineItems(
        array $sourceLineItems,
        array $blocks,
        array $explicitDispositions = []
    ): array {
        // Walk the AST independently for inventory and ordered-character
        // hashing instead of retaining a second copy of every emitted text
        // chunk. Large PDFs commonly contain tens of thousands of nodes.
        $emitted = self::inventoryFromChunks(self::textChunksFromNodes($blocks));
        $emittedSignificant = self::significantCharacterSummary(self::textChunksFromNodes($blocks));
        $tokenCounts = $emitted['tokens'];
        $characterCounts = $emitted['characters'];
        unset($emitted);
        $bindingRecords = self::sourceBindingRecords($sourceLineItems, $explicitDispositions);
        $structureSensitiveIds = [];
        foreach ($bindingRecords as $bindingRecord) {
            if (is_array($bindingRecord['semanticStructureProof'] ?? null)
                || (isset(self::OUTPUT_DISPOSITIONS[$bindingRecord['disposition'] ?? ''])
                    && (string) ($bindingRecord['significant'] ?? '') === '')) {
                $structureSensitiveIds[(string) $bindingRecord['id']] = true;
            }
        }
        $structuralValidation = self::validatedExplicitSemanticStructureMappings(
            $bindingRecords,
            $blocks
        );
        $validStructuralIds = $structuralValidation['sourceOccurrenceIds'];
        $exactMappedOutputCoverage = self::hasExactMappedOutputCoverage(
            $bindingRecords,
            $blocks,
            $validStructuralIds,
            $emittedSignificant['bytes']
        );
        if ($exactMappedOutputCoverage) {
            // The occurrence edges cover every emitted significant byte and
            // every projected source byte exactly. Token boundaries may still
            // move when adjacent PDF fragments become one word, so the edge
            // graph—not a second spelling inventory—is the correct claim on
            // the emitted output in this path.
            $tokenCounts = [];
            $characterCounts = [];
        }
        $sourceSignificantDigest = hash_init('sha256');
        $sourceSignificantCharacterBytes = 0;
        $counts = [];
        $pageCounts = [];
        $unresolvedSample = [];
        $suppressedSample = [];
        $occurrenceCount = 0;
        $evidencedOrderChangeOccurrenceCount = 0;
        $rejectedOrderChangeOccurrenceCount = 0;
        $evidencedOrderChangeScopeKeys = [];
        $orderProofRequested = self::hasRequestedOrderChange($explicitDispositions);
        $orderProofSegments = [];
        $currentOrderProofSegment = null;
        $mappedScopeInventoryStatus = [];
        $digest = hash_init('sha256');
        $resolvedDispositions = [];

        foreach ($bindingRecords as $record) {
            $text = trim((string) ($record['sourceText'] ?? ''));
            $page = max(1, (int) ($record['page'] ?? 1));
            $id = (string) ($record['id'] ?? '');
            $explicit = ($record['hasExplicitDisposition'] ?? false) === true
                ? $record
                : null;
            if ($explicit === null) {
                $inventory = self::inventoryFromChunks([$text]);
                $matched = self::canConsume($tokenCounts, $inventory['tokens'])
                    && self::canConsume($characterCounts, $inventory['characters']);
                $disposition = $matched ? 'emitted' : 'unresolved';
                $reason = $matched
                    ? 'The emitted AST contains one unclaimed character-equivalent occurrence.'
                    : 'No unclaimed character-equivalent emitted occurrence or explicit disposition was available.';
                $evidence = ['method' => 'conservative-inventory-consumption'];
                if ($matched) {
                    self::consume($tokenCounts, $inventory['tokens']);
                    self::consume($characterCounts, $inventory['characters']);
                }
            } else {
                $disposition = $explicit['disposition'];
                $reason = $explicit['reason'];
                $evidence = $explicit['evidence'];
                $accountingText = $explicit['textProjection'] ?? $text;
                if (isset(self::EXPLICIT_REASON_REQUIRED[$disposition]) && $reason === '') {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' requires evidence for disposition ' . $disposition . '.'
                    );
                }
                if (isset($structureSensitiveIds[$id]) && !isset($validStructuralIds[$id])) {
                    $requestedDisposition = $disposition;
                    $disposition = 'unresolved';
                    $reason = 'The semantic list marker did not map to one exact live list-item structure.';
                    $evidence = [
                        'method' => 'exact-semantic-list-marker-binding',
                        'failureReason' => $structuralValidation['failureReason'],
                        'requestedDisposition' => $requestedDisposition,
                    ];
                } elseif (in_array($disposition, ['emitted', 'boundary-repair', 'semantic-structure'], true)) {
                    if ($exactMappedOutputCoverage) {
                        $matchedProjection = true;
                    } else {
                        $mappedScopeId = ($explicit['allowOrderChange'] ?? false) === true
                        && is_array($explicit['orderProof'] ?? null)
                        && ($explicit['sourceMapping']['status'] ?? null) === 'output'
                        && ($explicit['sourceMapping']['mappingMode'] ?? null) === 'exact-authorized-scope'
                            ? (string) ($explicit['orderProof']['scopeId'] ?? '')
                            : '';
                        if ($mappedScopeId !== '') {
                            if (!array_key_exists($mappedScopeId, $mappedScopeInventoryStatus)) {
                                $inventory = self::inventoryFromChunks([
                                    (string) $explicit['orderProof']['emittedTextProjection'],
                                ]);
                                $mappedScopeInventoryStatus[$mappedScopeId] = self::canConsume(
                                    $tokenCounts,
                                    $inventory['tokens']
                                ) && self::canConsume($characterCounts, $inventory['characters']);
                                if ($mappedScopeInventoryStatus[$mappedScopeId]) {
                                    self::consume($tokenCounts, $inventory['tokens']);
                                    self::consume($characterCounts, $inventory['characters']);
                                }
                            }
                            $matchedProjection = $mappedScopeInventoryStatus[$mappedScopeId];
                        } else {
                            $inventory = self::inventoryFromChunks([$accountingText]);
                            $matchedProjection = self::canConsume($tokenCounts, $inventory['tokens'])
                                && self::canConsume($characterCounts, $inventory['characters']);
                            if ($matchedProjection) {
                                self::consume($tokenCounts, $inventory['tokens']);
                                self::consume($characterCounts, $inventory['characters']);
                            }
                        }
                    }
                    if (!$matchedProjection) {
                        $disposition = 'unresolved';
                        $reason = 'The evidenced text projection could not be reconciled with one unclaimed emitted occurrence.';
                        $evidence['requestedDisposition'] = $explicit['disposition'];
                    }
                }
            }

            $occurrenceCount++;
            $orderAccountingText = null;
            if (!isset(self::EXPLICIT_REASON_REQUIRED[$disposition])) {
                $orderAccountingText = $disposition === 'unresolved' || $explicit === null
                    ? $text
                    : ($explicit['textProjection'] ?? $text);
            } elseif (in_array($disposition, ['boundary-repair', 'semantic-structure'], true)) {
                $orderAccountingText = $explicit['textProjection'] ?? $text;
            }
            if (isset($validStructuralIds[$id])) {
                // Its significant bytes are represented by list structure,
                // not by an empty textual segment which could split an exact
                // mapped order scope on either side.
                $orderAccountingText = null;
            }
            if ($orderAccountingText !== null) {
                $sourceSignificantCharacterBytes += self::updateSignificantCharacterDigest(
                    $sourceSignificantDigest,
                    $orderAccountingText
                );
                if ($orderProofRequested) {
                    $orderScope = null;
                    if ($explicit !== null
                        && $disposition !== 'unresolved'
                        && $explicit['allowOrderChange']) {
                        $orderScope = self::localOrderChangeScope($explicit, $id, $page);
                        if ($orderScope === null) {
                            $rejectedOrderChangeOccurrenceCount++;
                        } else {
                            $evidencedOrderChangeOccurrenceCount++;
                            $evidencedOrderChangeScopeKeys[$orderScope['key']] = true;
                        }
                    }
                    self::appendOrderProofSegment(
                        $orderProofSegments,
                        $currentOrderProofSegment,
                        $orderAccountingText,
                        $id,
                        $orderScope
                    );
                }
            }
            $counts[$disposition] = ($counts[$disposition] ?? 0) + 1;
            $pageCounts[$page][$disposition] = ($pageCounts[$page][$disposition] ?? 0) + 1;
            hash_update($digest, $id . "\0" . $disposition . "\0" . $reason . "\n");
            $sample = [
                'id' => $id,
                'page' => $page,
                'text' => self::sampleText($text),
                'disposition' => $disposition,
                'reason' => $reason,
            ];
            if ($evidence !== []) {
                $sample['evidence'] = $evidence;
            }
            if ($disposition === 'unresolved' && count($unresolvedSample) < self::SAMPLE_LIMIT) {
                $unresolvedSample[] = $sample;
            } elseif (isset(self::EXPLICIT_REASON_REQUIRED[$disposition])
                && count($suppressedSample) < self::SAMPLE_LIMIT) {
                $suppressedSample[] = $sample;
            }
            $resolvedDispositions[] = $disposition;
        }

        ksort($counts);
        ksort($pageCounts, SORT_NUMERIC);
        foreach ($pageCounts as &$pageSummary) {
            ksort($pageSummary);
        }
        unset($pageSummary);
        if ($orderProofRequested) {
            self::flushOrderProofSegment($orderProofSegments, $currentOrderProofSegment);
        }
        $unresolvedCount = (int) ($counts['unresolved'] ?? 0);
        $sourceSignificantCharacterDigest = hash_final($sourceSignificantDigest);
        $emittedSignificantCharacterDigest = $emittedSignificant['digest'];
        $exactOrderedSignificantCharactersPreserved = $sourceSignificantCharacterBytes === $emittedSignificant['bytes']
            && hash_equals($sourceSignificantCharacterDigest, $emittedSignificantCharacterDigest);
        $remainingTokenCount = array_sum($tokenCounts);
        $remainingCharacterCount = array_sum($characterCounts);
        $localOrderProof = $orderProofRequested && !$exactOrderedSignificantCharactersPreserved
            ? self::proveLocalOrderSegments($orderProofSegments, $blocks)
            : [
                'preserved' => false,
                'strength' => $exactOrderedSignificantCharactersPreserved ? 'source-order-exact' : 'not-requested',
                'failureReason' => null,
            ];
        $evidencedOrderChangePreserved = !$exactOrderedSignificantCharactersPreserved
            && $unresolvedCount === 0
            && $evidencedOrderChangeOccurrenceCount > 0
            && $rejectedOrderChangeOccurrenceCount === 0
            && $remainingTokenCount === 0
            && $remainingCharacterCount === 0
            && $sourceSignificantCharacterBytes === $emittedSignificant['bytes']
            && $localOrderProof['preserved'];
        $orderedSignificantCharactersPreserved = $exactOrderedSignificantCharactersPreserved
            || $evidencedOrderChangePreserved;

        // Public source edges repeat each normalized occurrence's destination
        // vectors. Building them during accounting retains the complete,
        // proof-rich binding-record inventory beside a steadily growing second
        // graph. Finish every check which needs those records first, then
        // replace them one at a time with their public edge representation.
        // This preserves edge order and identity while bounding the overlap on
        // dense PDFs with tens of thousands of source occurrences.
        unset(
            $structureSensitiveIds,
            $structuralValidation,
            $validStructuralIds,
            $orderProofSegments,
            $currentOrderProofSegment,
            $mappedScopeInventoryStatus,
            $tokenCounts,
            $characterCounts,
            $record,
            $explicit,
            $inventory,
            $sample,
            $evidence
        );
        $sourceEdges = $bindingRecords;
        unset($bindingRecords);
        $sourceEdgeMappingComplete = true;
        $sourceEdgeCount = count($sourceEdges);
        for ($recordIndex = 0; $recordIndex < $sourceEdgeCount; $recordIndex++) {
            $bindingRecord = $sourceEdges[$recordIndex];
            $sourceEdge = self::sourceEdgeForOccurrence(
                (string) ($bindingRecord['id'] ?? ''),
                max(1, (int) ($bindingRecord['page'] ?? 1)),
                $resolvedDispositions[$recordIndex] ?? 'unresolved',
                is_array($bindingRecord['sourceMapping'] ?? null)
                    ? $bindingRecord['sourceMapping']
                    : null
            );
            $sourceEdges[$recordIndex] = $sourceEdge;
            if (($sourceEdge['target'] ?? null) === 'unresolved') {
                $sourceEdgeMappingComplete = false;
            }
            unset($bindingRecord, $sourceEdge, $resolvedDispositions[$recordIndex]);
        }
        unset($resolvedDispositions);

        return [
            'version' => 2,
            'sourceOccurrenceCount' => $occurrenceCount,
            'dispositionedOccurrenceCount' => array_sum($counts),
            'resolvedOccurrenceCount' => max(0, $occurrenceCount - $unresolvedCount),
            'unresolvedOccurrenceCount' => $unresolvedCount,
            'allOccurrencesDispositioned' => array_sum($counts) === $occurrenceCount,
            'allOccurrencesResolved' => $unresolvedCount === 0,
            'orderedSignificantCharactersPreserved' => $orderedSignificantCharactersPreserved,
            'orderedSignificantCharacterBasis' => $exactOrderedSignificantCharactersPreserved
                ? 'source-order-exact'
                : ($evidencedOrderChangePreserved ? 'evidenced-layout-reorder' : 'mismatch'),
            'evidencedOrderChangeOccurrenceCount' => $evidencedOrderChangeOccurrenceCount,
            'rejectedOrderChangeOccurrenceCount' => $rejectedOrderChangeOccurrenceCount,
            'evidencedOrderChangeScopeCount' => count($evidencedOrderChangeScopeKeys),
            'orderProofStrength' => $exactOrderedSignificantCharactersPreserved
                ? 'source-order-exact'
                : ($evidencedOrderChangePreserved ? $localOrderProof['strength'] : 'mismatch'),
            'orderProofFailureReason' => $evidencedOrderChangePreserved
                ? null
                : $localOrderProof['failureReason'],
            'unclaimedEmittedTokenCount' => $remainingTokenCount,
            'unclaimedEmittedSignificantCharacterCount' => $remainingCharacterCount,
            'sourceSignificantCharacterBytes' => $sourceSignificantCharacterBytes,
            'emittedSignificantCharacterBytes' => $emittedSignificant['bytes'],
            'sourceSignificantCharacterDigest' => $sourceSignificantCharacterDigest,
            'emittedSignificantCharacterDigest' => $emittedSignificantCharacterDigest,
            'dispositionCounts' => $counts,
            'pageDispositionCounts' => $pageCounts,
            'unresolvedOccurrenceSample' => $unresolvedSample,
            'evidencedSuppressionSample' => $suppressedSample,
            'sourceEdges' => $sourceEdges,
            'sourceEdgeCount' => count($sourceEdges),
            'sourceEdgeMappingComplete' => $sourceEdgeMappingComplete
                && count($sourceEdges) === $occurrenceCount,
            'sourceEdgeDigest' => self::sourceEdgeDigest($sourceEdges),
            'dispositionDigest' => hash_final($digest),
        ];
    }

    /**
     * Verify the public top-level edge graph before allowing it to account for
     * token-boundary repairs. Every output contributor must name real
     * destination nodes, its source byte spans must cover its exact projected
     * text once, and the aggregate edge bytes must equal the final AST text.
     *
     * @param list<array<string,mixed>> $bindingRecords
     * @param list<AstNode> $blocks
     * @param array<string,true> $validStructuralIds
     */
    private static function hasExactMappedOutputCoverage(
        array $bindingRecords,
        array $blocks,
        array $validStructuralIds,
        int $emittedSignificantBytes
    ): bool {
        $expectedLengths = [];
        $expectedNodes = [];
        foreach ($bindingRecords as $record) {
            $id = (string) ($record['id'] ?? '');
            $explicit = ($record['hasExplicitDisposition'] ?? false) === true
                ? $record
                : null;
            if ($explicit === null || $explicit['disposition'] === 'unresolved') {
                return false;
            }
            if (!isset(self::OUTPUT_DISPOSITIONS[$explicit['disposition']])) {
                if (($explicit['sourceMapping']['status'] ?? null) !== 'disposition') {
                    return false;
                }
                continue;
            }
            $mapping = $explicit['sourceMapping'];
            if (!is_array($mapping)
                || ($mapping['status'] ?? null) !== 'output'
                || ($mapping['destinationNodeIds'] ?? []) === []) {
                return false;
            }
            $length = strlen((string) ($record['significant'] ?? ''));
            if (isset($validStructuralIds[$id])) {
                if ($length !== 0
                    || ($mapping['mappingMode'] ?? null) !== self::SEMANTIC_STRUCTURE_MAPPING_MODE) {
                    return false;
                }
                continue;
            }
            if (!in_array(
                $mapping['mappingMode'] ?? null,
                ['exact-sequence', 'exact-authorized-scope'],
                true
            ) || $length < 1) {
                return false;
            }
            $expectedLengths[$id] = $length;
            $nodes = $mapping['destinationNodeIds'];
            sort($nodes, SORT_STRING);
            $expectedNodes[$id] = $nodes;
        }
        if ($expectedLengths === []) {
            return $emittedSignificantBytes === 0;
        }

        $ranges = [];
        $actualNodes = [];
        $edgeBytes = 0;
        foreach ($blocks as $block) {
            $nodeId = $block->attr('sourceNodeId');
            $edges = $block->attr('sourceLineEdges', []);
            if (!is_array($edges) || $edges === []) {
                continue;
            }
            if (!is_string($nodeId) || $nodeId === '') {
                return false;
            }
            foreach ($edges as $edge) {
                if (!is_array($edge)) {
                    return false;
                }
                $sourceId = is_string($edge['sourceLineId'] ?? null)
                    ? $edge['sourceLineId']
                    : '';
                $start = is_int($edge['startByte'] ?? null) ? $edge['startByte'] : -1;
                $end = is_int($edge['endByte'] ?? null) ? $edge['endByte'] : -1;
                if (!isset($expectedLengths[$sourceId])
                    || $start < 0
                    || $end <= $start
                    || $end > $expectedLengths[$sourceId]
                    || !in_array($nodeId, $expectedNodes[$sourceId], true)) {
                    return false;
                }
                $ranges[$sourceId][] = ['start' => $start, 'end' => $end];
                $actualNodes[$sourceId][$nodeId] = true;
                $edgeBytes += $end - $start;
            }
        }
        if ($edgeBytes !== $emittedSignificantBytes) {
            return false;
        }

        foreach ($expectedLengths as $sourceId => $length) {
            $sourceRanges = $ranges[$sourceId] ?? [];
            usort($sourceRanges, static fn (array $left, array $right): int =>
                ($left['start'] <=> $right['start']) ?: ($left['end'] <=> $right['end'])
            );
            $cursor = 0;
            foreach ($sourceRanges as $range) {
                if ($range['start'] !== $cursor) {
                    return false;
                }
                $cursor = $range['end'];
            }
            $nodes = array_keys($actualNodes[$sourceId] ?? []);
            sort($nodes, SORT_STRING);
            if ($cursor !== $length || $nodes !== $expectedNodes[$sourceId]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Re-derive semantic marker destinations from the live decorated AST and
     * compare them with the public mapping. This prevents callers from making
     * an empty inventory look resolved by forging output node IDs.
     *
     * @param list<array<string,mixed>> $records
     * @param list<AstNode> $blocks
     * @return array{sourceOccurrenceIds:array<string,true>,failureReason:?string}
     */
    private static function validatedExplicitSemanticStructureMappings(
        array $records,
        array $blocks
    ): array {
        $sensitiveIds = [];
        $textMappings = [];
        $normalizedById = [];
        foreach ($records as $record) {
            $id = (string) ($record['id'] ?? '');
            if (($record['hasExplicitDisposition'] ?? false) !== true) {
                continue;
            }
            $mapping = $record['sourceMapping'] ?? null;
            if (is_array($mapping) && ($mapping['status'] ?? null) === 'output') {
                $textMappings[$id] = [
                    'destinationNodeIds' => $mapping['destinationNodeIds'],
                    'destinationInlineIds' => $mapping['destinationInlineIds'],
                    'mappingMode' => $mapping['mappingMode'],
                    'scopeId' => $mapping['scopeId'],
                ];
            }
            if (is_array($record['semanticStructureProof'] ?? null)
                || (isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? ''])
                    && (string) ($record['significant'] ?? '') === '')) {
                $sensitiveIds[$id] = true;
                $normalizedById[$id] = $mapping;
            }
        }
        if ($sensitiveIds === []) {
            return ['sourceOccurrenceIds' => [], 'failureReason' => null];
        }

        $derived = self::sourceBindingSemanticStructureMappings($records, $blocks, $textMappings);
        if ($derived['failureReason'] !== null) {
            return ['sourceOccurrenceIds' => [], 'failureReason' => $derived['failureReason']];
        }
        foreach (array_keys($sensitiveIds) as $id) {
            foreach ($blocks as $block) {
                if (self::sourceBindingNodeHasSourceLineEdge($block, $id)) {
                    return [
                        'sourceOccurrenceIds' => [],
                        'failureReason' => 'semantic-list-marker-has-text-byte-edge',
                    ];
                }
            }
            $expected = $derived['mappingBySourceId'][$id] ?? null;
            $actual = $normalizedById[$id] ?? null;
            if (!is_array($expected)
                || !is_array($actual)
                || ($actual['status'] ?? null) !== 'output'
                || ($actual['mappingMode'] ?? null) !== self::SEMANTIC_STRUCTURE_MAPPING_MODE
                || ($actual['scopeId'] ?? null) !== null) {
                return [
                    'sourceOccurrenceIds' => [],
                    'failureReason' => 'semantic-list-marker-public-mapping-does-not-match-live-structure',
                ];
            }
            $expectedNodes = $expected['destinationNodeIds'];
            $actualNodes = $actual['destinationNodeIds'];
            $expectedInlineIds = $expected['destinationInlineIds'];
            $actualInlineIds = $actual['destinationInlineIds'];
            sort($expectedNodes, SORT_STRING);
            sort($actualNodes, SORT_STRING);
            sort($expectedInlineIds, SORT_STRING);
            sort($actualInlineIds, SORT_STRING);
            if ($expectedNodes !== $actualNodes || $expectedInlineIds !== $actualInlineIds) {
                return [
                    'sourceOccurrenceIds' => [],
                    'failureReason' => 'semantic-list-marker-public-mapping-does-not-match-live-structure',
                ];
            }
        }

        return ['sourceOccurrenceIds' => $sensitiveIds, 'failureReason' => null];
    }

    /**
     * @param array<string,mixed>|string|null $value
     * @return array{disposition:string,reason:string,evidence:array<string,mixed>,textProjection:?string,allowOrderChange:bool,orderProof:?array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string},semanticStructureProof:?array{version:int,method:string,listType:string,markerOrdinal:?int,markerDigest:string,anchorSourceOccurrenceId:string,itemProjectionDigest:string},sourceMapping:?array<string,mixed>}|null
     */
    private static function normalizedExplicitDisposition(array|string|null $value, string $id): ?array
    {
        if ($value === null) {
            return null;
        }
        $record = is_string($value) ? ['disposition' => $value] : $value;
        $disposition = is_string($record['disposition'] ?? null) ? $record['disposition'] : '';
        if (!isset(self::ALLOWED_DISPOSITIONS[$disposition])) {
            throw new InvalidArgumentException('Unknown PDF source disposition for ' . $id . '.');
        }

        return [
            'disposition' => $disposition,
            'reason' => is_string($record['reason'] ?? null) ? trim($record['reason']) : '',
            'evidence' => is_array($record['evidence'] ?? null) ? $record['evidence'] : [],
            'textProjection' => is_string($record['textProjection'] ?? null) ? $record['textProjection'] : null,
            'allowOrderChange' => ($record['allowOrderChange'] ?? false) === true,
            'orderProof' => self::normalizedExplicitOrderProof($record['orderProof'] ?? null, $id),
            'semanticStructureProof' => self::normalizedSemanticStructureProof(
                $record['semanticStructureProof'] ?? null,
                $id
            ),
            'sourceMapping' => self::normalizedSourceMapping($record['sourceMapping'] ?? null, $id),
        ];
    }

    /**
     * @return array{version:int,method:string,listType:string,markerOrdinal:?int,markerDigest:string,anchorSourceOccurrenceId:string,itemProjectionDigest:string,anchorSourceOccurrenceIds?:list<string>,anchorProjectionDigest?:string}|null
     */
    private static function normalizedSemanticStructureProof(mixed $value, string $id): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $id . ' has an invalid semantic-structure proof.'
            );
        }
        $version = $value['version'] ?? null;
        $method = is_string($value['method'] ?? null) ? $value['method'] : '';
        $listType = is_string($value['listType'] ?? null) ? $value['listType'] : '';
        $markerOrdinal = $value['markerOrdinal'] ?? null;
        $markerDigest = is_string($value['markerDigest'] ?? null) ? $value['markerDigest'] : '';
        $anchorId = is_string($value['anchorSourceOccurrenceId'] ?? null)
            ? $value['anchorSourceOccurrenceId']
            : '';
        $itemDigest = is_string($value['itemProjectionDigest'] ?? null)
            ? $value['itemProjectionDigest']
            : '';
        $anchorIds = $value['anchorSourceOccurrenceIds'] ?? null;
        $anchorProjectionDigest = is_string($value['anchorProjectionDigest'] ?? null)
            ? $value['anchorProjectionDigest']
            : '';
        $ordinalIsValid = $listType === 'ordered'
            ? is_int($markerOrdinal) && $markerOrdinal >= 1
            : $markerOrdinal === null;
        $extendedAnchorIsValid = $version === 2
            && is_array($anchorIds)
            && array_is_list($anchorIds)
            && count($anchorIds) >= 3
            && count($anchorIds) <= 16
            && ($anchorIds[0] ?? null) === $anchorId
            && count(array_unique($anchorIds, SORT_STRING)) === count($anchorIds)
            && array_reduce(
                $anchorIds,
                static fn (bool $valid, mixed $candidate): bool =>
                    $valid && is_string($candidate) && $candidate !== '',
                true
            )
            && preg_match('/^[a-f0-9]{64}$/D', $anchorProjectionDigest) === 1;
        if (!in_array($version, [1, 2], true)
            || $method !== self::SEMANTIC_STRUCTURE_PROOF_METHOD
            || !in_array($listType, ['ordered', 'bullet'], true)
            || !$ordinalIsValid
            || preg_match('/^[a-f0-9]{64}$/D', $markerDigest) !== 1
            || $anchorId === ''
            || $anchorId === $id
            || preg_match('/^[a-f0-9]{64}$/D', $itemDigest) !== 1
            || ($version === 2 && !$extendedAnchorIsValid)
            || ($version === 1 && ($anchorIds !== null || $anchorProjectionDigest !== ''))) {
            throw new InvalidArgumentException(
                'PDF source occurrence ' . $id . ' has an incomplete semantic-structure proof.'
            );
        }

        $normalized = [
            'version' => $version,
            'method' => self::SEMANTIC_STRUCTURE_PROOF_METHOD,
            'listType' => $listType,
            'markerOrdinal' => $markerOrdinal,
            'markerDigest' => $markerDigest,
            'anchorSourceOccurrenceId' => $anchorId,
            'itemProjectionDigest' => $itemDigest,
        ];
        if ($version === 2) {
            $normalized['anchorSourceOccurrenceIds'] = $anchorIds;
            $normalized['anchorProjectionDigest'] = $anchorProjectionDigest;
        }

        return $normalized;
    }

    /** @return array<string,mixed>|null */
    private static function normalizedSourceMapping(mixed $value, string $id): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an invalid source mapping.');
        }
        $status = is_string($value['status'] ?? null) ? $value['status'] : '';
        if (!in_array($status, ['output', 'disposition', 'unresolved'], true)) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an invalid mapping status.');
        }
        $nodeIds = self::normalizedDestinationIds($value['destinationNodeIds'] ?? [], $id);
        $inlineIds = self::normalizedDestinationIds($value['destinationInlineIds'] ?? [], $id);
        if ($status === 'output' && $nodeIds === []) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has no mapped output node.');
        }
        if ($status !== 'output' && ($nodeIds !== [] || $inlineIds !== [])) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' maps a disposition to output nodes.');
        }

        return [
            'status' => $status,
            'mappingMode' => is_string($value['mappingMode'] ?? null) && $value['mappingMode'] !== ''
                ? $value['mappingMode']
                : 'unresolved',
            'destinationNodeIds' => $nodeIds,
            'destinationInlineIds' => $inlineIds,
            'scopeId' => is_string($value['scopeId'] ?? null) && $value['scopeId'] !== ''
                ? $value['scopeId']
                : null,
        ];
    }

    /** @return list<string> */
    private static function normalizedDestinationIds(mixed $value, string $sourceId): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('PDF source occurrence ' . $sourceId . ' has invalid destinations.');
        }
        $isList = array_is_list($value);
        $ids = [];
        foreach ($value as $id) {
            if (!is_string($id) || $id === '' || isset($ids[$id])) {
                throw new InvalidArgumentException('PDF source occurrence ' . $sourceId . ' has invalid destinations.');
            }
            $ids[$id] = true;
        }

        // Bound reader mappings already carry canonical lists. Preserve their
        // copy-on-write storage after validation instead of rebuilding the
        // same potentially long destination vector for every occurrence.
        return $isList ? $value : array_keys($ids);
    }

    /**
     * @param list<array<string,mixed>|string> $sourceLineItems
     * @param array<string,array<string,mixed>|string> $explicitDispositions
     * @return list<array<string,mixed>>
     */
    private static function sourceBindingRecords(array $sourceLineItems, array $explicitDispositions): array
    {
        $records = [];
        $seen = [];
        // One page-local order proof is deliberately attached to every
        // occurrence in its scope. Normalization rebuilds its nested source
        // ID/range arrays, so retaining that fresh copy per occurrence turns
        // a valid N-occurrence page proof into O(N^2) live memory. Intern only
        // strictly identical normalized proofs; a conflicting reuse of a
        // scope ID remains distinct and is rejected by the existing checks.
        $normalizedOrderProofsByScopeId = [];
        $normalizedOrderProofKeysByScopeId = [];
        foreach ($sourceLineItems as $index => $item) {
            $record = is_array($item) ? $item : ['text' => (string) $item];
            $sourceText = is_string($record['text'] ?? null) ? $record['text'] : '';
            $text = trim($sourceText);
            $sourceSignificant = self::significantText($sourceText);
            if ($sourceSignificant === '') {
                // Match the ledger's actual Unicode significance policy.
                // ASCII trim() does not recognize PDF alignment blanks such
                // as U+2007 FIGURE SPACE, but they carry no bindable text.
                continue;
            }
            $id = self::sourceOccurrenceId($record, $index, $text);
            if (isset($seen[$id])) {
                throw new InvalidArgumentException('Duplicate PDF source occurrence ID ' . $id . '.');
            }
            $seen[$id] = true;
            $explicit = self::normalizedExplicitDisposition($explicitDispositions[$id] ?? null, $id);
            $orderProofKey = null;
            if (is_array($explicit) && is_array($explicit['orderProof'] ?? null)) {
                $normalizedOrderProof = $explicit['orderProof'];
                $scopeId = (string) ($normalizedOrderProof['scopeId'] ?? '');
                if (isset($normalizedOrderProofsByScopeId[$scopeId])
                    && $normalizedOrderProofsByScopeId[$scopeId] === $normalizedOrderProof) {
                    $explicit['orderProof'] = $normalizedOrderProofsByScopeId[$scopeId];
                    $orderProofKey = $normalizedOrderProofKeysByScopeId[$scopeId];
                } elseif (!isset($normalizedOrderProofsByScopeId[$scopeId])) {
                    $normalizedOrderProofsByScopeId[$scopeId] = $normalizedOrderProof;
                    $encodedOrderProof = json_encode(
                        $normalizedOrderProof,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                    $orderProofKey = hash(
                        'sha256',
                        is_string($encodedOrderProof)
                            ? $encodedOrderProof
                            : serialize($normalizedOrderProof)
                    );
                    $normalizedOrderProofKeysByScopeId[$scopeId] = $orderProofKey;
                } else {
                    $encodedOrderProof = json_encode(
                        $normalizedOrderProof,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                    $orderProofKey = hash(
                        'sha256',
                        is_string($encodedOrderProof)
                            ? $encodedOrderProof
                            : serialize($normalizedOrderProof)
                    );
                }
            }
            $disposition = $explicit['disposition'] ?? 'emitted';
            $projectionText = isset(self::OUTPUT_DISPOSITIONS[$disposition])
                ? ($explicit['textProjection'] ?? $sourceText)
                : '';
            $records[] = [
                'id' => $id,
                'sourceIndex' => $index,
                'page' => max(1, (int) ($record['page'] ?? 1)),
                'stream' => isset($record['stream']) && is_numeric($record['stream'])
                    ? (int) $record['stream']
                    : null,
                'disposition' => $disposition,
                'hasExplicitDisposition' => $explicit !== null,
                'reason' => $explicit['reason'] ?? '',
                'sourceText' => $sourceText,
                'projectionText' => $projectionText,
                'textProjection' => $explicit['textProjection'] ?? null,
                'significant' => self::significantText($projectionText),
                'sourceSignificant' => $sourceSignificant,
                'evidence' => $explicit['evidence'] ?? [],
                'allowOrderChange' => ($explicit['allowOrderChange'] ?? false) === true,
                'orderProof' => $explicit['orderProof'] ?? null,
                'orderProofKey' => $orderProofKey,
                'semanticStructureProof' => $explicit['semanticStructureProof'] ?? null,
                'sourceMapping' => $explicit['sourceMapping'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * An empty output projection is valid only for a standalone list marker
     * which names its immediately following source occurrence as the complete
     * text anchor for one final list item. This check is intentionally local;
     * the live AST target is validated after ordinary byte decoration.
     *
     * @param array<string,mixed> $record
     * @param array<string,mixed>|null $next
     */
    private static function sourceBindingStructuralMarkerRecordFailureReason(
        array $record,
        ?array $next
    ): ?string {
        $proof = $record['semanticStructureProof'] ?? null;
        if (($record['disposition'] ?? null) !== 'semantic-structure'
            || (string) ($record['significant'] ?? '') !== ''
            || ($record['textProjection'] ?? null) !== ''
            || !is_array($proof)
            || ($record['allowOrderChange'] ?? false) === true
            || is_array($record['orderProof'] ?? null)) {
            return 'empty-output-projection-has-no-exact-structural-target';
        }

        $sourceSignificant = (string) ($record['sourceSignificant'] ?? '');
        $listType = (string) ($proof['listType'] ?? '');
        $markerOrdinal = $proof['markerOrdinal'] ?? null;
        if ($listType === 'ordered') {
            if (preg_match('/^([0-9]+)[.)]$/D', $sourceSignificant, $match) !== 1
                || !is_int($markerOrdinal)
                || (int) $match[1] !== $markerOrdinal) {
                return 'semantic-list-marker-proof-does-not-match-source-marker';
            }
        } elseif ($listType === 'bullet') {
            if (preg_match('/^(?:\*|\x{2022}|\x{25CF}|\x{25AA}|\x{25A0}|\x{2043})$/uD', $sourceSignificant) !== 1
                || $markerOrdinal !== null) {
                return 'semantic-list-marker-proof-does-not-match-source-marker';
            }
        } else {
            return 'semantic-list-marker-proof-does-not-match-source-marker';
        }
        if (!hash_equals((string) ($proof['markerDigest'] ?? ''), hash('sha256', $sourceSignificant))) {
            return 'semantic-list-marker-proof-does-not-match-source-marker';
        }

        if (!is_array($next)
            || ($next['id'] ?? null) !== ($proof['anchorSourceOccurrenceId'] ?? null)
            || !isset(self::OUTPUT_DISPOSITIONS[$next['disposition'] ?? ''])
            || (string) ($next['significant'] ?? '') === ''
            || is_array($next['semanticStructureProof'] ?? null)
            || !is_int($record['stream'] ?? null)
            || !is_int($next['stream'] ?? null)
            || $record['stream'] < 1
            || $next['stream'] !== $record['stream']
            || (int) ($next['page'] ?? 0) !== (int) ($record['page'] ?? 0)) {
            return 'semantic-list-marker-anchor-is-not-the-next-same-stream-occurrence';
        }
        return null;
    }

    /**
     * @param list<array<string,mixed>> $records
     * @return array{text:string,units:list<array<string,mixed>>,failureReason:?string}
     */
    private static function sourceBindingProjection(array $records): array
    {
        foreach ($records as $index => $record) {
            if (($record['disposition'] ?? null) === 'unresolved') {
                return ['text' => '', 'units' => [], 'failureReason' => 'source-occurrence-is-unresolved'];
            }
            $outputDisposition = isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? '']);
            $structuralProof = $record['semanticStructureProof'] ?? null;
            if (($outputDisposition && (string) ($record['significant'] ?? '') === '')
                || is_array($structuralProof)) {
                $failureReason = self::sourceBindingStructuralMarkerRecordFailureReason(
                    $record,
                    $records[$index + 1] ?? null
                );
                if ($failureReason !== null) {
                    return ['text' => '', 'units' => [], 'failureReason' => $failureReason];
                }
            }
        }
        $contributors = array_values(array_filter(
            $records,
            static fn (array $record): bool => isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? ''])
                && (string) ($record['significant'] ?? '') !== ''
        ));
        $text = '';
        $units = [];
        $index = 0;
        while ($index < count($contributors)) {
            $record = $contributors[$index];
            $proof = ($record['allowOrderChange'] ?? false) === true
                ? ($record['orderProof'] ?? null)
                : null;
            if (($record['allowOrderChange'] ?? false) === true && !is_array($proof)) {
                return [
                    'text' => '',
                    'units' => [],
                    'failureReason' => 'authorized-order-change-has-no-exact-proof',
                ];
            }
            if (!is_array($proof)) {
                $start = strlen($text);
                $text .= (string) $record['significant'];
                $units[] = [
                    'records' => [$record],
                    'projection' => (string) $record['significant'],
                    'start' => $start,
                    'end' => strlen($text),
                    'scopeId' => null,
                ];
                $index++;
                continue;
            }

            $scopeIds = $proof['sourceOccurrenceIds'];
            $scopeRecords = array_slice($contributors, $index, count($scopeIds));
            if (array_column($scopeRecords, 'id') !== $scopeIds) {
                return [
                    'text' => '',
                    'units' => [],
                    'failureReason' => 'mapped-order-proof-source-occurrences-are-not-contiguous',
                ];
            }
            foreach ($scopeRecords as $scopeRecord) {
                if (($scopeRecord['allowOrderChange'] ?? false) !== true
                    || ($scopeRecord['orderProof'] ?? null) !== $proof) {
                    return [
                        'text' => '',
                        'units' => [],
                        'failureReason' => 'mapped-order-proof-is-not-identical-across-scope',
                    ];
                }
            }
            $scopeProjection = self::significantText($proof['emittedTextProjection']);
            $sourceCharacters = [];
            $sourceBytes = 0;
            foreach ($scopeRecords as $scopeRecord) {
                $sourceBytes += self::updateSignificantCharacterInventory(
                    $sourceCharacters,
                    (string) $scopeRecord['significant']
                );
            }
            $emittedCharacters = [];
            $emittedBytes = self::updateSignificantCharacterInventory($emittedCharacters, $scopeProjection);
            ksort($sourceCharacters);
            ksort($emittedCharacters);
            if ($sourceBytes !== $emittedBytes || $sourceCharacters !== $emittedCharacters) {
                return [
                    'text' => '',
                    'units' => [],
                    'failureReason' => 'mapped-order-proof-does-not-conserve-source-characters',
                ];
            }
            $start = strlen($text);
            $text .= $scopeProjection;
            $units[] = [
                'records' => $scopeRecords,
                'projection' => $scopeProjection,
                'start' => $start,
                'end' => strlen($text),
                'scopeId' => $proof['scopeId'],
            ];
            $index += count($scopeIds);
        }

        return ['text' => $text, 'units' => $units, 'failureReason' => null];
    }

    /**
     * @param list<AstNode> $blocks
     * @return array{text:string,leaves:list<array<string,mixed>>,blocks:list<array<string,mixed>>}
     */
    private static function sourceBindingOutput(array $blocks): array
    {
        $text = '';
        $leaves = [];
        $blockRanges = [];
        foreach ($blocks as $blockIndex => $block) {
            $start = strlen($text);
            self::appendSourceBindingNodeText(
                $block,
                (string) $blockIndex,
                $blockIndex,
                $text,
                $leaves
            );
            $blockRanges[] = [
                'index' => $blockIndex,
                'start' => $start,
                'end' => strlen($text),
            ];
        }

        return ['text' => $text, 'leaves' => $leaves, 'blocks' => $blockRanges];
    }

    /** @param list<array<string,mixed>> $leaves */
    private static function appendSourceBindingNodeText(
        AstNode $node,
        string $path,
        int $blockIndex,
        string &$text,
        array &$leaves
    ): void {
        if ($node->type === 'text' || $node->type === 'code_block') {
            $significant = self::significantText((string) $node->attr('text', ''));
            if ($significant === '') {
                return;
            }
            $start = strlen($text);
            $text .= $significant;
            $leaves[] = [
                'path' => $path,
                'blockIndex' => $blockIndex,
                'start' => $start,
                'end' => strlen($text),
            ];

            return;
        }
        foreach ($node->children() as $childIndex => $child) {
            self::appendSourceBindingNodeText(
                $child,
                $path . '.' . $childIndex,
                $blockIndex,
                $text,
                $leaves
            );
        }
    }

    /**
     * @param list<array<string,mixed>> $units
     * @return list<array<string,mixed>>|null
     */
    private static function directSourceBindingRanges(array $units): ?array
    {
        $ranges = [];
        foreach ($units as $unit) {
            if (($unit['scopeId'] ?? null) === null) {
                $record = $unit['records'][0] ?? null;
                if (!is_array($record) || (string) ($record['significant'] ?? '') === '') {
                    return null;
                }
                $ranges[] = [
                    'sourceOccurrenceId' => $record['id'],
                    'sourceStart' => 0,
                    'sourceEnd' => strlen($record['significant']),
                    'outputStart' => $unit['start'],
                    'outputEnd' => $unit['end'],
                    'mappingMode' => 'exact-sequence',
                    'scopeId' => null,
                ];
                continue;
            }

            $scopeRanges = self::uniqueScopeInterleavingRanges($unit);
            if ($scopeRanges === null) {
                return null;
            }
            array_push($ranges, ...$scopeRanges);
        }

        return $ranges;
    }

    /**
     * Bind one mapped order scope even when unchanged occurrences split its
     * emitted projection. The unchanged occurrences must have one exact,
     * source-ordered placement, and removing them must produce exactly the
     * scope projection. A scope token that crosses an insertion is not
     * assigned a guessed discontiguous destination.
     *
     * @param list<array<string,mixed>> $units
     * @return list<array<string,mixed>>|null
     */
    private static function uniqueInterleavedSourceBindingRanges(array $units, string $output): ?array
    {
        $scopeIndexes = [];
        $exactTexts = [];
        $exactUnitIndexes = [];
        foreach ($units as $unitIndex => $unit) {
            if (($unit['scopeId'] ?? null) !== null) {
                $scopeIndexes[] = $unitIndex;
                continue;
            }
            $projection = (string) ($unit['projection'] ?? '');
            if ($projection === '') {
                return null;
            }
            $exactTexts[] = $projection;
            $exactUnitIndexes[] = $unitIndex;
        }
        if (count($scopeIndexes) !== 1 || $exactTexts === []) {
            return null;
        }

        $scopeIndex = $scopeIndexes[0];
        $scopeUnit = $units[$scopeIndex];
        $scopeProjection = (string) ($scopeUnit['projection'] ?? '');
        $layout = self::uniqueInterleavedExactProjectionLayout(
            $output,
            $exactTexts,
            $scopeProjection
        );
        if ($layout === null) {
            return null;
        }

        $ranges = [];
        foreach ($layout['exactRanges'] as $exactIndex => $outputRange) {
            $unit = $units[$exactUnitIndexes[$exactIndex]];
            $record = $unit['records'][0] ?? null;
            if (!is_array($record)) {
                return null;
            }
            $ranges[] = [
                'sourceOccurrenceId' => $record['id'],
                'sourceStart' => 0,
                'sourceEnd' => strlen((string) $record['significant']),
                'outputStart' => $outputRange['start'],
                'outputEnd' => $outputRange['end'],
                'mappingMode' => 'exact-sequence',
                'scopeId' => null,
            ];
        }

        $scopeUnit['start'] = 0;
        $scopeUnit['end'] = strlen($scopeProjection);
        $localScopeRanges = self::uniqueScopeInterleavingRanges($scopeUnit);
        if ($localScopeRanges === null) {
            return null;
        }
        foreach ($localScopeRanges as $localRange) {
            $translated = null;
            foreach ($layout['scopeSpans'] as $scopeSpan) {
                if ($localRange['outputStart'] < $scopeSpan['projectionStart']
                    || $localRange['outputEnd'] > $scopeSpan['projectionEnd']) {
                    continue;
                }
                $translated = $localRange;
                $translated['outputStart'] = $scopeSpan['outputStart']
                    + $localRange['outputStart'] - $scopeSpan['projectionStart'];
                $translated['outputEnd'] = $scopeSpan['outputStart']
                    + $localRange['outputEnd'] - $scopeSpan['projectionStart'];
                break;
            }
            if ($translated === null) {
                return null;
            }
            $ranges[] = $translated;
        }

        return $ranges;
    }

    /**
     * Find the sole placement of unchanged exact occurrences for which the
     * intervening output, concatenated byte-for-byte, equals a mapped scope's
     * declared projection.
     *
     * @param list<string> $exactTexts
     * @return array{exactRanges:list<array{start:int,end:int}>,scopeSpans:list<array{projectionStart:int,projectionEnd:int,outputStart:int,outputEnd:int}>}|null
     */
    private static function uniqueInterleavedExactProjectionLayout(
        string $output,
        array $exactTexts,
        string $scopeProjection
    ): ?array {
        $solutionCount = 0;
        $solution = null;
        $candidateCount = 0;
        $limitExceeded = false;
        $search = function (
            int $exactIndex,
            int $outputCursor,
            int $scopeCursor,
            array $exactRanges,
            array $scopeSpans
        ) use (
            &$search,
            &$solutionCount,
            &$solution,
            &$candidateCount,
            &$limitExceeded,
            $output,
            $exactTexts,
            $scopeProjection
        ): void {
            if ($solutionCount > 1 || $limitExceeded) {
                return;
            }
            if ($exactIndex >= count($exactTexts)) {
                $tail = substr($output, $outputCursor);
                $tailLength = strlen($tail);
                if ($scopeCursor + $tailLength !== strlen($scopeProjection)
                    || !hash_equals(substr($scopeProjection, $scopeCursor), $tail)) {
                    return;
                }
                if ($tailLength > 0) {
                    $scopeSpans[] = [
                        'projectionStart' => $scopeCursor,
                        'projectionEnd' => $scopeCursor + $tailLength,
                        'outputStart' => $outputCursor,
                        'outputEnd' => strlen($output),
                    ];
                }
                $solutionCount++;
                $solution = [
                    'exactRanges' => $exactRanges,
                    'scopeSpans' => $scopeSpans,
                ];

                return;
            }

            $exact = $exactTexts[$exactIndex];
            if ($exact === '') {
                $limitExceeded = true;

                return;
            }
            $searchOffset = $outputCursor;
            while (($found = strpos($output, $exact, $searchOffset)) !== false) {
                $candidateCount++;
                if ($candidateCount > self::ORDER_MATCH_CANDIDATE_LIMIT) {
                    $limitExceeded = true;

                    return;
                }
                $gap = substr($output, $outputCursor, $found - $outputCursor);
                $gapLength = strlen($gap);
                if ($scopeCursor + $gapLength <= strlen($scopeProjection)
                    && hash_equals(substr($scopeProjection, $scopeCursor, $gapLength), $gap)) {
                    $nextExactRanges = $exactRanges;
                    $nextExactRanges[] = [
                        'start' => $found,
                        'end' => $found + strlen($exact),
                    ];
                    $nextScopeSpans = $scopeSpans;
                    if ($gapLength > 0) {
                        $nextScopeSpans[] = [
                            'projectionStart' => $scopeCursor,
                            'projectionEnd' => $scopeCursor + $gapLength,
                            'outputStart' => $outputCursor,
                            'outputEnd' => $found,
                        ];
                    }
                    $search(
                        $exactIndex + 1,
                        $found + strlen($exact),
                        $scopeCursor + $gapLength,
                        $nextExactRanges,
                        $nextScopeSpans
                    );
                }
                $searchOffset = $found + 1;
            }
        };
        $search(0, 0, 0, [], []);

        return !$limitExceeded && $solutionCount === 1 ? $solution : null;
    }

    /**
     * @param array<string,mixed> $unit
     * @return list<array<string,mixed>>|null
     */
    private static function uniqueScopeInterleavingRanges(array $unit): ?array
    {
        $declaredEmittedRanges = $unit['records'][0]['orderProof']['emittedSourceRanges'] ?? null;
        if (is_array($declaredEmittedRanges)) {
            return self::declaredScopeSourceRanges($unit, $declaredEmittedRanges);
        }

        $declaredEmittedIds = $unit['records'][0]['orderProof']['emittedSourceOccurrenceIds'] ?? null;
        if (is_array($declaredEmittedIds)) {
            return self::declaredScopeOccurrenceRanges($unit, $declaredEmittedIds);
        }

        $sourceTokens = [];
        foreach ($unit['records'] as $record) {
            $tokens = self::sourceBindingTokens((string) $record['significant']);
            if ($tokens === []) {
                return null;
            }
            $sourceTokens[] = $tokens;
        }
        $outputTokens = self::sourceBindingTokens((string) $unit['projection']);
        $sourceTokenCount = array_sum(array_map('count', $sourceTokens));
        if ($outputTokens === [] || count($outputTokens) !== $sourceTokenCount) {
            return null;
        }

        $memo = [];
        $choices = [];
        $stateCount = 0;
        $solve = function (array $positions) use (
            &$solve,
            &$memo,
            &$choices,
            &$stateCount,
            $sourceTokens,
            $outputTokens
        ): int {
            $key = implode(',', $positions);
            if (isset($memo[$key])) {
                return $memo[$key];
            }
            $stateCount++;
            if ($stateCount > self::SOURCE_BINDING_STATE_LIMIT) {
                return $memo[$key] = 2;
            }
            $outputIndex = array_sum($positions);
            if ($outputIndex === count($outputTokens)) {
                foreach ($positions as $sourceIndex => $position) {
                    if ($position !== count($sourceTokens[$sourceIndex])) {
                        return $memo[$key] = 0;
                    }
                }

                return $memo[$key] = 1;
            }

            $count = 0;
            $choice = null;
            foreach ($positions as $sourceIndex => $position) {
                if (!isset($sourceTokens[$sourceIndex][$position])
                    || $sourceTokens[$sourceIndex][$position]['text'] !== $outputTokens[$outputIndex]['text']) {
                    continue;
                }
                $next = $positions;
                $next[$sourceIndex]++;
                $branchCount = $solve($next);
                if ($branchCount < 1) {
                    continue;
                }
                if ($count === 0 && $branchCount === 1) {
                    $choice = $sourceIndex;
                } else {
                    $choice = null;
                }
                $count = min(2, $count + $branchCount);
                if ($count > 1) {
                    break;
                }
            }
            if ($count === 1 && $choice !== null) {
                $choices[$key] = $choice;
            }

            return $memo[$key] = $count;
        };

        $positions = array_fill(0, count($sourceTokens), 0);
        if ($solve($positions) !== 1) {
            return null;
        }
        $ranges = [];
        for ($outputIndex = 0; $outputIndex < count($outputTokens); $outputIndex++) {
            $key = implode(',', $positions);
            $sourceIndex = $choices[$key] ?? null;
            if (!is_int($sourceIndex)) {
                return null;
            }
            $sourceToken = $sourceTokens[$sourceIndex][$positions[$sourceIndex]];
            $outputToken = $outputTokens[$outputIndex];
            $record = $unit['records'][$sourceIndex];
            $ranges[] = [
                'sourceOccurrenceId' => $record['id'],
                'sourceStart' => $sourceToken['start'],
                'sourceEnd' => $sourceToken['end'],
                'outputStart' => $unit['start'] + $outputToken['start'],
                'outputEnd' => $unit['start'] + $outputToken['end'],
                'mappingMode' => 'exact-authorized-scope',
                'scopeId' => $unit['scopeId'],
            ];
            $positions[$sourceIndex]++;
        }

        return $ranges;
    }

    /**
     * A positioned-text producer can retain a finer proof than whole source
     * occurrences: the exact occurrence-local byte ranges painted into each
     * final visual line. Consume that declaration only when it is a complete,
     * non-overlapping partition of every source projection in the scope and
     * its emitted-order concatenation equals the declared output byte for
     * byte. Repeated spellings and equal character inventories therefore
     * cannot authorize a different range order.
     *
     * @param array<string,mixed> $unit
     * @param list<array{sourceOccurrenceId:string,sourceStart:int,sourceEnd:int}> $declaredRanges
     * @return list<array<string,mixed>>|null
     */
    private static function declaredScopeSourceRanges(
        array $unit,
        array $declaredRanges
    ): ?array {
        $recordsById = [];
        foreach ($unit['records'] as $record) {
            $id = is_string($record['id'] ?? null) ? $record['id'] : '';
            if ($id === '' || isset($recordsById[$id])) {
                return null;
            }
            $recordsById[$id] = $record;
        }
        if ($declaredRanges === [] || $recordsById === []) {
            return null;
        }

        $cursor = (int) ($unit['start'] ?? 0);
        $projection = '';
        $coverageById = [];
        $ranges = [];
        foreach ($declaredRanges as $declaredRange) {
            if (!is_array($declaredRange)) {
                return null;
            }
            $id = is_string($declaredRange['sourceOccurrenceId'] ?? null)
                ? $declaredRange['sourceOccurrenceId']
                : '';
            $sourceStart = is_int($declaredRange['sourceStart'] ?? null)
                ? $declaredRange['sourceStart']
                : null;
            $sourceEnd = is_int($declaredRange['sourceEnd'] ?? null)
                ? $declaredRange['sourceEnd']
                : null;
            $record = $recordsById[$id] ?? null;
            $significant = is_array($record)
                ? (string) ($record['significant'] ?? '')
                : '';
            if ($significant === ''
                || $sourceStart === null
                || $sourceEnd === null
                || $sourceStart < 0
                || $sourceEnd <= $sourceStart
                || $sourceEnd > strlen($significant)
                || !self::isWholeUtf8CodePointRange($significant, $sourceStart, $sourceEnd)) {
                return null;
            }
            $text = substr($significant, $sourceStart, $sourceEnd - $sourceStart);
            if ($text === '') {
                return null;
            }
            $outputEnd = $cursor + strlen($text);
            $ranges[] = [
                'sourceOccurrenceId' => $id,
                'sourceStart' => $sourceStart,
                'sourceEnd' => $sourceEnd,
                'outputStart' => $cursor,
                'outputEnd' => $outputEnd,
                'mappingMode' => 'exact-authorized-scope',
                'scopeId' => $unit['scopeId'],
            ];
            $coverageById[$id][] = ['start' => $sourceStart, 'end' => $sourceEnd];
            $projection .= $text;
            $cursor = $outputEnd;
        }

        foreach ($recordsById as $id => $record) {
            $coverage = $coverageById[$id] ?? [];
            usort($coverage, static fn (array $left, array $right): int =>
                ($left['start'] <=> $right['start']) ?: ($left['end'] <=> $right['end'])
            );
            $sourceCursor = 0;
            foreach ($coverage as $range) {
                if ($range['start'] !== $sourceCursor || $range['end'] <= $range['start']) {
                    return null;
                }
                $sourceCursor = $range['end'];
            }
            if ($sourceCursor !== strlen((string) ($record['significant'] ?? ''))) {
                return null;
            }
        }

        return hash_equals((string) ($unit['projection'] ?? ''), $projection)
            && $cursor === (int) ($unit['end'] ?? 0)
                ? $ranges
                : null;
    }

    private static function isWholeUtf8CodePointRange(string $text, int $start, int $end): bool
    {
        $length = strlen($text);
        if ($start < 0
            || $end <= $start
            || $end > $length
            || preg_match('//u', $text) !== 1) {
            return false;
        }

        // UTF-8 continuation bytes always begin with 10xxxxxx. For valid
        // UTF-8, every other byte (plus the terminal offset) is a code-point
        // boundary. Rejecting interior offsets prevents one emitted character
        // from being assembled out of byte fragments belonging to different
        // source occurrences.
        $startsOnBoundary = $start === 0 || (ord($text[$start]) & 0xC0) !== 0x80;
        $endsOnBoundary = $end === $length || (ord($text[$end]) & 0xC0) !== 0x80;

        return $startsOnBoundary && $endsOnBoundary;
    }

    /**
     * A producer which retained occurrence-local geometry may declare the
     * exact emitted occurrence sequence. Validate both the source-set
     * permutation and the byte-for-byte projection before using it; this is
     * what makes adjacent numeric cells and repeated spellings bindable
     * without guessing token boundaries.
     *
     * @param array<string,mixed> $unit
     * @param list<string> $emittedSourceIds
     * @return list<array<string,mixed>>|null
     */
    private static function declaredScopeOccurrenceRanges(
        array $unit,
        array $emittedSourceIds
    ): ?array {
        $recordsById = [];
        foreach ($unit['records'] as $record) {
            $id = is_string($record['id'] ?? null) ? $record['id'] : '';
            if ($id === '' || isset($recordsById[$id])) {
                return null;
            }
            $recordsById[$id] = $record;
        }
        $declared = array_keys($recordsById);
        $emitted = $emittedSourceIds;
        $declaredSorted = $declared;
        $emittedSorted = $emitted;
        sort($declaredSorted, SORT_STRING);
        sort($emittedSorted, SORT_STRING);
        if ($emittedSorted !== $declaredSorted) {
            return null;
        }

        $cursor = (int) ($unit['start'] ?? 0);
        $ranges = [];
        $projection = '';
        foreach ($emitted as $id) {
            $record = $recordsById[$id] ?? null;
            $significant = is_array($record)
                ? (string) ($record['significant'] ?? '')
                : '';
            if ($significant === '') {
                return null;
            }
            $end = $cursor + strlen($significant);
            $ranges[] = [
                'sourceOccurrenceId' => $id,
                'sourceStart' => 0,
                'sourceEnd' => strlen($significant),
                'outputStart' => $cursor,
                'outputEnd' => $end,
                'mappingMode' => 'exact-authorized-scope',
                'scopeId' => $unit['scopeId'],
            ];
            $projection .= $significant;
            $cursor = $end;
        }

        return hash_equals((string) ($unit['projection'] ?? ''), $projection)
            && $cursor === (int) ($unit['end'] ?? 0)
                ? $ranges
                : null;
    }

    /** @return list<array{text:string,start:int,end:int}> */
    private static function sourceBindingTokens(string $significant): array
    {
        if ($significant === '') {
            return [];
        }
        $matched = preg_match_all(
            '/[\p{L}\p{M}\p{N}]+|./us',
            $significant,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        if ($matched === false || $matched === 0) {
            return [];
        }
        $tokens = [];
        $covered = '';
        foreach ($matches[0] as [$token, $offset]) {
            $token = (string) $token;
            $tokens[] = [
                'text' => $token,
                'start' => (int) $offset,
                'end' => (int) $offset + strlen($token),
            ];
            $covered .= $token;
        }

        return hash_equals($significant, $covered) ? $tokens : [];
    }

    /**
     * @param list<AstNode> $blocks
     * @param array{text:string,leaves:list<array<string,mixed>>,blocks:list<array<string,mixed>>} $output
     * @param list<array<string,mixed>> $ranges
     * @return array{blocks:list<AstNode>,mappingBySourceId:array<string,array<string,mixed>>}|null
     */
    private static function sourceBindingDecoration(array $blocks, array $output, array $ranges): ?array
    {
        usort($ranges, static fn (array $left, array $right): int =>
            ((int) $left['outputStart'] <=> (int) $right['outputStart'])
                ?: ((int) $left['outputEnd'] <=> (int) $right['outputEnd'])
        );
        $cursor = 0;
        foreach ($ranges as $range) {
            if ((int) $range['outputStart'] !== $cursor
                || (int) $range['outputEnd'] <= (int) $range['outputStart']) {
                return null;
            }
            $cursor = (int) $range['outputEnd'];
        }
        if ($cursor !== strlen($output['text'])) {
            return null;
        }

        $blockEdges = self::sourceBindingIntersectionsForDestinations(
            $ranges,
            $output['blocks'],
            'index'
        );
        $leafEdges = self::sourceBindingIntersectionsForDestinations(
            $ranges,
            $output['leaves'],
            'path'
        );
        foreach ($blockEdges as &$edges) {
            $edges = is_array($edges) ? self::normalizedSourceBindingEdges($edges) : [];
        }
        unset($edges);
        foreach ($leafEdges as &$edges) {
            $edges = is_array($edges) ? self::normalizedSourceBindingEdges($edges) : [];
        }
        unset($edges);

        foreach ($blocks as $blockIndex => $block) {
            $edges = $blockEdges[$blockIndex] ?? [];
            $blockRange = $output['blocks'][$blockIndex] ?? null;
            $hasSignificantText = is_array($blockRange)
                && (int) ($blockRange['end'] ?? 0) > (int) ($blockRange['start'] ?? 0);
            if ($hasSignificantText && $edges === []) {
                return null;
            }
            // Media/placeholders and other genuinely textless AST blocks are
            // not destinations for a text-line occurrence. They retain their
            // own visual/structural provenance and must not make an otherwise
            // exact source-to-text edge graph incomplete.
            if (!$hasSignificantText) {
                continue;
            }
        }

        $mappingBySourceId = [];
        $decorated = [];
        foreach ($blocks as $blockIndex => $block) {
            $edges = $blockEdges[$blockIndex] ?? [];
            $publicBlockEdges = self::publicSourceBindingEdges($edges);
            $identity = json_encode(
                ['type' => $block->type, 'sourceLineEdges' => $publicBlockEdges],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $topNodeId = $publicBlockEdges === []
                ? ''
                : 'pdf-source-node-' . substr(hash(
                    'sha256',
                    is_string($identity) ? $identity : serialize($edges)
                ), 0, 32);
            // The public top-level edge list and the mapping entries below are
            // the only remaining owners needed for this block. Drop its
            // larger private intersection bucket before recursively
            // decorating a dense descendant tree.
            unset($blockEdges[$blockIndex]);
            $inlineIdsBySource = [];
            $decorated[] = $block->type === 'table'
                && self::sourceBindingTableTextLeavesAreCellScoped($block)
                ? self::decoratedCompactTableSourceBindingNode(
                    $block,
                    (string) $blockIndex,
                    $topNodeId,
                    $publicBlockEdges,
                    $leafEdges,
                    $inlineIdsBySource
                )
                : self::decoratedSourceBindingNode(
                    $block,
                    (string) $blockIndex,
                    true,
                    $topNodeId,
                    $publicBlockEdges,
                    $leafEdges,
                    $inlineIdsBySource
                );
            foreach ($edges as $edge) {
                $sourceId = $edge['sourceLineId'];
                if (!isset($mappingBySourceId[$sourceId])) {
                    $mappingBySourceId[$sourceId] = [
                        'destinationNodeIds' => [],
                        'destinationInlineIds' => [],
                        'mappingMode' => 'exact-sequence',
                        'scopeId' => null,
                        '_scopeIdConflict' => false,
                    ];
                }
                $lastNodeIndex = array_key_last($mappingBySourceId[$sourceId]['destinationNodeIds']);
                if ($lastNodeIndex === null
                    || $mappingBySourceId[$sourceId]['destinationNodeIds'][$lastNodeIndex] !== $topNodeId) {
                    $mappingBySourceId[$sourceId]['destinationNodeIds'][] = $topNodeId;
                }
                if (($edge['mappingMode'] ?? null) === 'exact-authorized-scope') {
                    $mappingBySourceId[$sourceId]['mappingMode'] = 'exact-authorized-scope';
                }
                if (is_string($edge['scopeId'] ?? null) && $edge['scopeId'] !== '') {
                    $scopeId = $edge['scopeId'];
                    if ($mappingBySourceId[$sourceId]['_scopeIdConflict'] !== true) {
                        $currentScopeId = $mappingBySourceId[$sourceId]['scopeId'];
                        if ($currentScopeId === null) {
                            $mappingBySourceId[$sourceId]['scopeId'] = $scopeId;
                        } elseif ($currentScopeId !== $scopeId) {
                            $mappingBySourceId[$sourceId]['scopeId'] = null;
                            $mappingBySourceId[$sourceId]['_scopeIdConflict'] = true;
                        }
                    }
                }
            }
            foreach ($inlineIdsBySource as $sourceId => $inlineIds) {
                foreach ($inlineIds as $inlineId) {
                    $mappingBySourceId[$sourceId]['destinationInlineIds'][] = $inlineId;
                }
            }
            unset($edges, $publicBlockEdges, $inlineIdsBySource);
        }
        // The decorated AST and occurrence mapping now own the public edge
        // data. Release the larger private intersection graphs before
        // compacting mapping sets; dense table PDFs otherwise retain both
        // representations at the highest-memory point of a successful bind.
        unset($blockEdges, $leafEdges, $inlineIdsBySource);

        foreach ($mappingBySourceId as &$mapping) {
            unset($mapping['_scopeIdConflict']);
        }
        unset($mapping);

        return ['blocks' => $decorated, 'mappingBySourceId' => $mappingBySourceId];
    }

    /**
     * Bind a source-only list marker to the already decorated list structure
     * whose visible body begins with its immediate source anchor. Marker
     * characters are represented by the list/list_item relationship, so they
     * deliberately receive no zero-length sourceLineEdges entry.
     *
     * @param list<array<string,mixed>> $records
     * @param list<AstNode> $blocks
     * @param array<string,array<string,mixed>> $textMappingsBySourceId
     * @return array{mappingBySourceId:array<string,array<string,mixed>>,failureReason:?string}
     */
    private static function sourceBindingSemanticStructureMappings(
        array $records,
        array $blocks,
        array $textMappingsBySourceId
    ): array {
        $structuralRecords = array_values(array_filter(
            $records,
            static fn (array $record): bool => is_array($record['semanticStructureProof'] ?? null)
                || (isset(self::OUTPUT_DISPOSITIONS[$record['disposition'] ?? ''])
                    && (string) ($record['significant'] ?? '') === '')
        ));
        if ($structuralRecords === []) {
            return ['mappingBySourceId' => [], 'failureReason' => null];
        }

        $targets = [];
        foreach ($blocks as $blockIndex => $block) {
            $listType = match ($block->type) {
                'ordered_list' => 'ordered',
                'bullet_list' => 'bullet',
                default => null,
            };
            if ($listType === null) {
                continue;
            }
            $listNodeId = $block->attr('sourceNodeId');
            if (!is_string($listNodeId) || $listNodeId === '') {
                continue;
            }
            $start = $listType === 'ordered' ? $block->attr('start', 1) : null;
            if ($listType === 'ordered' && (!is_int($start) || $start < 1)) {
                continue;
            }
            foreach ($block->children() as $itemIndex => $item) {
                if ($item->type !== 'list_item') {
                    continue;
                }
                $itemNodeId = $item->attr('sourceNodeId');
                $itemSignificant = self::sourceBindingNodeSignificantText($item);
                $itemVisible = trim(self::sourceBindingNodeVisibleText($item));
                $itemEdges = $item->attr('sourceLineEdges', []);
                if (!is_string($itemNodeId)
                    || $itemNodeId === ''
                    || $itemSignificant === ''
                    || $itemVisible === ''
                    || !is_array($itemEdges)
                    || $itemEdges === []) {
                    continue;
                }
                $targets[] = [
                    'blockIndex' => $blockIndex,
                    'listType' => $listType,
                    'ordinal' => $listType === 'ordered' ? $start + $itemIndex : null,
                    'listNodeId' => $listNodeId,
                    'itemNodeId' => $itemNodeId,
                    'itemSignificant' => $itemSignificant,
                    'itemVisible' => $itemVisible,
                    'itemProjectionDigest' => hash('sha256', $itemSignificant),
                    'itemEdges' => $itemEdges,
                ];
            }
        }

        $recordIndexById = [];
        foreach ($records as $recordIndex => $record) {
            $recordIndexById[(string) ($record['id'] ?? '')] = $recordIndex;
        }
        $claimedItems = [];
        $mappings = [];
        foreach ($structuralRecords as $record) {
            $id = (string) ($record['id'] ?? '');
            $recordIndex = $recordIndexById[$id] ?? null;
            $next = is_int($recordIndex) ? ($records[$recordIndex + 1] ?? null) : null;
            $failureReason = self::sourceBindingStructuralMarkerRecordFailureReason($record, $next);
            if ($failureReason !== null) {
                return ['mappingBySourceId' => [], 'failureReason' => $failureReason];
            }
            $proof = $record['semanticStructureProof'];
            $anchorId = (string) $proof['anchorSourceOccurrenceId'];
            $anchorRecords = is_int($recordIndex)
                ? [$records[$recordIndex + 1] ?? null]
                : [];
            if (($proof['version'] ?? null) === 2) {
                $anchorIds = $proof['anchorSourceOccurrenceIds'] ?? [];
                $anchorRecords = is_int($recordIndex) && is_array($anchorIds)
                    ? array_slice($records, $recordIndex + 1, count($anchorIds))
                    : [];
                if (array_column($anchorRecords, 'id') !== $anchorIds) {
                    return [
                        'mappingBySourceId' => [],
                        'failureReason' => 'semantic-list-marker-extended-anchor-is-not-consecutive',
                    ];
                }
            }
            $anchorSequence = ($proof['version'] ?? null) === 2
                ? self::sourceBindingExactOrdinaryAnchorSequence($record, $anchorRecords)
                : null;
            if (($proof['version'] ?? null) === 2
                && (!is_array($anchorSequence)
                    || !hash_equals(
                        (string) ($proof['anchorProjectionDigest'] ?? ''),
                        hash('sha256', $anchorSequence['significant'])
                    ))) {
                return [
                    'mappingBySourceId' => [],
                    'failureReason' => 'semantic-list-marker-extended-anchor-proof-does-not-match-source',
                ];
            }
            $anchorSignificant = ($proof['version'] ?? null) === 2
                ? (string) ($anchorSequence['significant'] ?? '')
                : (string) ($next['significant'] ?? '');
            $following = is_int($recordIndex) ? ($records[$recordIndex + 2] ?? null) : null;
            $anchorMapping = $textMappingsBySourceId[$anchorId] ?? null;
            if (!is_array($anchorMapping)) {
                return [
                    'mappingBySourceId' => [],
                    'failureReason' => 'semantic-list-marker-anchor-has-no-exact-text-mapping',
                ];
            }

            $candidates = [];
            foreach ($targets as $target) {
                if ($target['listType'] !== $proof['listType']
                    || $target['ordinal'] !== $proof['markerOrdinal']
                    || !hash_equals($target['itemProjectionDigest'], $proof['itemProjectionDigest'])
                    || (($proof['version'] ?? null) === 2
                        ? (!is_array($anchorSequence)
                            || !str_starts_with(
                                $target['itemSignificant'],
                                $anchorSequence['significant']
                            )
                            || !str_starts_with(
                                self::sourceBindingComparableVisibleText($target['itemVisible']),
                                $anchorSequence['visible']
                            ))
                        : !self::sourceBindingAnchorMatchesListItem(
                            $next,
                            $following,
                            $target['itemSignificant'],
                            $target['itemVisible']
                        ))) {
                    continue;
                }
                $sourcePrefixes = [];
                $allAnchorMappingsReachTarget = true;
                foreach ($anchorRecords as $anchorRecordIndex => $anchorRecord) {
                    if (!is_array($anchorRecord)) {
                        $allAnchorMappingsReachTarget = false;
                        break;
                    }
                    $sourceOccurrenceId = (string) ($anchorRecord['id'] ?? '');
                    $mapping = $textMappingsBySourceId[$sourceOccurrenceId] ?? null;
                    if (!is_array($mapping)
                        || !in_array(
                            $target['listNodeId'],
                            $mapping['destinationNodeIds'] ?? [],
                            true
                        )
                        || !in_array(
                            $target['itemNodeId'],
                            $mapping['destinationInlineIds'] ?? [],
                            true
                        )) {
                        $allAnchorMappingsReachTarget = false;
                        break;
                    }
                    $sourcePrefixes[] = [
                        'sourceOccurrenceId' => $sourceOccurrenceId,
                        'length' => strlen((string) ($anchorRecord['significant'] ?? '')),
                    ];
                }
                if (($proof['version'] ?? null) === 1
                    && !hash_equals($target['itemSignificant'], $anchorSignificant)) {
                    $followingMapping = is_array($following)
                        ? ($textMappingsBySourceId[(string) ($following['id'] ?? '')] ?? null)
                        : null;
                    if (!is_array($following)
                        || !is_array($followingMapping)
                        || !in_array(
                            $target['listNodeId'],
                            $followingMapping['destinationNodeIds'] ?? [],
                            true
                        )
                        || !in_array(
                            $target['itemNodeId'],
                            $followingMapping['destinationInlineIds'] ?? [],
                            true
                        )) {
                        $allAnchorMappingsReachTarget = false;
                    } else {
                        $sourcePrefixes[] = [
                            'sourceOccurrenceId' => (string) ($following['id'] ?? ''),
                            'length' => strlen((string) ($following['significant'] ?? '')),
                        ];
                    }
                }
                if (!$allAnchorMappingsReachTarget
                    || !self::sourceBindingItemStartsWithSourceRanges(
                    $target['itemEdges'],
                    $sourcePrefixes
                )) {
                    continue;
                }
                $candidates[] = $target;
            }
            if (count($candidates) !== 1) {
                return [
                    'mappingBySourceId' => [],
                    'failureReason' => 'semantic-list-marker-has-no-unique-structural-target',
                ];
            }
            $target = $candidates[0];
            if (isset($claimedItems[$target['itemNodeId']])) {
                return [
                    'mappingBySourceId' => [],
                    'failureReason' => 'semantic-list-marker-structural-target-is-reused',
                ];
            }
            $claimedItems[$target['itemNodeId']] = true;
            $mappings[$id] = [
                'destinationNodeIds' => [$target['listNodeId']],
                'destinationInlineIds' => [$target['itemNodeId']],
                'mappingMode' => self::SEMANTIC_STRUCTURE_MAPPING_MODE,
                'scopeId' => null,
            ];
        }

        return ['mappingBySourceId' => $mappings, 'failureReason' => null];
    }

    /**
     * @param list<array<string,mixed>> $edges
     * @param list<array{sourceOccurrenceId:string,length:int}> $prefixes
     */
    private static function sourceBindingItemStartsWithSourceRanges(
        array $edges,
        array $prefixes
    ): bool {
        $edgeIndex = 0;
        foreach ($prefixes as $prefix) {
            $sourceId = $prefix['sourceOccurrenceId'];
            $length = $prefix['length'];
            if ($sourceId === '' || $length < 1) {
                return false;
            }
            $cursor = 0;
            while ($cursor < $length) {
                $edge = $edges[$edgeIndex] ?? null;
                if (!is_array($edge)
                    || ($edge['sourceLineId'] ?? null) !== $sourceId
                    || ($edge['startByte'] ?? null) !== $cursor
                    || !is_int($edge['endByte'] ?? null)
                    || $edge['endByte'] <= $cursor
                    || $edge['endByte'] > $length) {
                    return false;
                }
                $cursor = $edge['endByte'];
                $edgeIndex++;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $marker
     * @param list<array<string,mixed>|null> $anchors
     * @return array{significant:string,visible:string}|null
     */
    private static function sourceBindingExactOrdinaryAnchorSequence(
        array $marker,
        array $anchors
    ): ?array {
        if (count($anchors) < 3 || count($anchors) > 16) {
            return null;
        }
        $page = $marker['page'] ?? null;
        $stream = $marker['stream'] ?? null;
        if (!is_int($page) || !is_int($stream) || $page < 1 || $stream < 1) {
            return null;
        }

        $significant = '';
        $visible = '';
        $seen = [];
        foreach ($anchors as $index => $anchor) {
            if (!is_array($anchor)) {
                return null;
            }
            $sourceId = (string) ($anchor['id'] ?? '');
            $sourceText = (string) ($anchor['sourceText'] ?? '');
            $projectionText = (string) ($anchor['projectionText'] ?? '');
            $recordSignificant = (string) ($anchor['significant'] ?? '');
            $recordVisible = self::sourceBindingComparableVisibleText($projectionText);
            if ($sourceId === ''
                || isset($seen[$sourceId])
                || ($anchor['page'] ?? null) !== $page
                || ($anchor['stream'] ?? null) !== $stream
                || !isset(self::OUTPUT_DISPOSITIONS[$anchor['disposition'] ?? ''])
                || is_array($anchor['semanticStructureProof'] ?? null)
                || $recordSignificant === ''
                || $recordVisible === ''
                || !hash_equals($sourceText, $projectionText)) {
                return null;
            }
            $seen[$sourceId] = true;
            if ($index > 0) {
                $visible .= preg_match('/^[,.;:!?\)\]\}]/u', ltrim($projectionText)) === 1
                    ? ''
                    : ' ';
            }
            $significant .= $recordSignificant;
            $visible .= $recordVisible;
        }

        return ['significant' => $significant, 'visible' => $visible];
    }

    private static function sourceBindingComparableVisibleText(string $text): string
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return trim(preg_replace('/[\s\p{Cc}\p{Cf}]+/u', ' ', $text) ?? '');
    }

    /**
     * Ordinary anchors may cover the complete final list item or its first
     * visible wrapped line. The latter requires the exact visible source
     * adjacency: normally one space, or no separator before closing
     * punctuation. A wrapped-hyphen repair remains a distinct no-space path
     * with exact directional evidence.
     *
     * @param array<string,mixed> $anchor
     * @param array<string,mixed>|null $following
     */
    private static function sourceBindingAnchorMatchesListItem(
        array $anchor,
        ?array $following,
        string $itemSignificant,
        string $itemVisible
    ): bool {
        $anchorSignificant = (string) ($anchor['significant'] ?? '');
        if ($anchorSignificant === '') {
            return false;
        }
        if (hash_equals($itemSignificant, $anchorSignificant)) {
            return true;
        }
        if (!str_starts_with($itemSignificant, $anchorSignificant) || !is_array($following)) {
            return false;
        }

        $followingSignificant = (string) ($following['significant'] ?? '');
        $page = $anchor['page'] ?? null;
        $stream = $anchor['stream'] ?? null;
        if (!is_int($page)
            || !is_int($stream)
            || $stream < 1
            || ($following['page'] ?? null) !== $page
            || ($following['stream'] ?? null) !== $stream
            || !isset(self::OUTPUT_DISPOSITIONS[$following['disposition'] ?? ''])
            || $followingSignificant === ''
            || !str_starts_with(
                $itemSignificant,
                $anchorSignificant . $followingSignificant
            )) {
            return false;
        }

        $sourceText = (string) ($anchor['sourceText'] ?? '');
        $projectionText = (string) ($anchor['projectionText'] ?? '');
        $followingText = (string) ($following['sourceText'] ?? '');
        $anchorVisible = trim($projectionText);
        $followingVisible = trim((string) ($following['projectionText'] ?? ''));
        if ($anchorVisible === '' || $followingVisible === '') {
            return false;
        }

        $anchorEvidence = is_array($anchor['evidence'] ?? null) ? $anchor['evidence'] : [];
        if (array_key_exists('wrappedHyphenBoundaryRepair', $anchorEvidence)) {
            $evidence = $anchorEvidence['wrappedHyphenBoundaryRepair'];
            $sourceIds = is_array($evidence) ? ($evidence['sourceOccurrenceIds'] ?? null) : null;
            if (!is_array($evidence)
                || ($evidence['method'] ?? null) !== 'exact-directional-source-wrapped-hyphen-boundary'
                || !is_array($sourceIds)
                || ($sourceIds['preceding'] ?? null) !== ($anchor['id'] ?? null)
                || ($sourceIds['following'] ?? null) !== ($following['id'] ?? null)
                || ($evidence['page'] ?? null) !== $page
                || ($evidence['stream'] ?? null) !== $stream
                || !self::sourceBindingHasExactWrappedHyphenProjection(
                    $sourceText,
                    $projectionText,
                    (string) ($evidence['suppressionKind'] ?? '')
                )
                || !is_string($evidence['originalDigest'] ?? null)
                || !hash_equals(hash('sha256', $sourceText), $evidence['originalDigest'])
                || !is_string($evidence['projectedDigest'] ?? null)
                || !hash_equals(hash('sha256', $projectionText), $evidence['projectedDigest'])
                || !is_string($evidence['followingOriginalDigest'] ?? null)
                || !hash_equals(hash('sha256', $followingText), $evidence['followingOriginalDigest'])) {
                return false;
            }

            return str_starts_with($itemVisible, $anchorVisible . $followingVisible);
        }

        $visibleSeparator = preg_match(
            '/^[,.;:!?\)\]\}]/u',
            ltrim((string) ($following['projectionText'] ?? ''))
        ) === 1 ? '' : ' ';

        return str_starts_with(
            $itemVisible,
            $anchorVisible . $visibleSeparator . $followingVisible
        );
    }

    private static function sourceBindingHasExactWrappedHyphenProjection(
        string $sourceText,
        string $projectionText,
        string $suppressionKind
    ): bool {
        if (preg_match('/(?:\x{00AD}|[-\x{2010}\x{2011}])$/u', $sourceText, $match) !== 1) {
            return false;
        }
        $separator = $match[0];
        $prefix = substr($sourceText, 0, -strlen($separator));

        return match ($suppressionKind) {
            'discretionary-hard-hyphen' => $separator !== "\u{00AD}"
                && hash_equals($prefix, $projectionText),
            'discretionary-soft-hyphen' => $separator === "\u{00AD}"
                && hash_equals($prefix, $projectionText),
            'semantic-soft-hyphen' => $separator === "\u{00AD}"
                && hash_equals($prefix . '-', $projectionText),
            default => false,
        };
    }

    private static function sourceBindingNodeSignificantText(AstNode $node): string
    {
        if ($node->type === 'text' || $node->type === 'code_block') {
            return self::significantText((string) $node->attr('text', ''));
        }
        $text = '';
        foreach ($node->children() as $child) {
            $text .= self::sourceBindingNodeSignificantText($child);
        }

        return $text;
    }

    private static function sourceBindingNodeVisibleText(AstNode $node): string
    {
        if ($node->type === 'text' || $node->type === 'code_block') {
            return (string) $node->attr('text', '');
        }
        $text = '';
        foreach ($node->children() as $child) {
            $text .= self::sourceBindingNodeVisibleText($child);
        }

        return $text;
    }

    private static function sourceBindingNodeHasSourceLineEdge(AstNode $node, string $sourceId): bool
    {
        $edges = $node->attr('sourceLineEdges', []);
        if (is_array($edges)) {
            foreach ($edges as $edge) {
                if (is_array($edge) && ($edge['sourceLineId'] ?? null) === $sourceId) {
                    return true;
                }
            }
        }
        foreach ($node->children() as $child) {
            if (self::sourceBindingNodeHasSourceLineEdge($child, $sourceId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>>|null $edges
     * @param array<string,mixed> $range
     * @param array<string,mixed> $destinationRange
     */
    private static function appendSourceBindingIntersection(
        ?array &$edges,
        array $range,
        array $destinationRange
    ): void {
        $start = max((int) $range['outputStart'], (int) $destinationRange['start']);
        $end = min((int) $range['outputEnd'], (int) $destinationRange['end']);
        if ($end <= $start) {
            return;
        }
        $edges ??= [];
        $sourceStart = (int) $range['sourceStart'] + ($start - (int) $range['outputStart']);
        $edges[] = [
            'sourceLineId' => $range['sourceOccurrenceId'],
            'startByte' => $sourceStart,
            'endByte' => $sourceStart + ($end - $start),
            'outputStart' => $start,
            'outputEnd' => $end,
            'mappingMode' => $range['mappingMode'],
            'scopeId' => $range['scopeId'],
        ];
    }

    /**
     * Intersect two output-ordered range lists without rescanning every AST
     * destination for every source span. A source span may cross adjacent
     * destinations, so retain the current source index until its end has been
     * consumed by a later destination.
     *
     * @param list<array<string,mixed>> $ranges
     * @param list<array<string,mixed>> $destinations
     * @return array<int|string,list<array<string,mixed>>>
     */
    private static function sourceBindingIntersectionsForDestinations(
        array $ranges,
        array $destinations,
        string $destinationKey
    ): array {
        $edgesByDestination = [];
        $rangeIndex = 0;
        $rangeCount = count($ranges);
        foreach ($destinations as $destination) {
            $destinationStart = (int) ($destination['start'] ?? 0);
            $destinationEnd = (int) ($destination['end'] ?? 0);
            $key = $destination[$destinationKey] ?? null;
            if ((!is_int($key) && !is_string($key))
                || $destinationEnd <= $destinationStart) {
                continue;
            }
            while ($rangeIndex < $rangeCount
                && (int) $ranges[$rangeIndex]['outputEnd'] <= $destinationStart) {
                $rangeIndex++;
            }
            $candidateIndex = $rangeIndex;
            while ($candidateIndex < $rangeCount
                && (int) $ranges[$candidateIndex]['outputStart'] < $destinationEnd) {
                self::appendSourceBindingIntersection(
                    $edgesByDestination[$key],
                    $ranges[$candidateIndex],
                    $destination
                );
                if ((int) $ranges[$candidateIndex]['outputEnd'] > $destinationEnd) {
                    break;
                }
                $candidateIndex++;
            }
            $rangeIndex = $candidateIndex;
        }

        return $edgesByDestination;
    }

    /** @param list<array<string,mixed>> $edges @return list<array<string,mixed>> */
    private static function normalizedSourceBindingEdges(array $edges): array
    {
        usort($edges, static fn (array $left, array $right): int =>
            ((int) $left['outputStart'] <=> (int) $right['outputStart'])
                ?: ((int) $left['outputEnd'] <=> (int) $right['outputEnd'])
        );
        $normalized = [];
        foreach ($edges as $edge) {
            $lastIndex = array_key_last($normalized);
            $last = $lastIndex === null ? null : $normalized[$lastIndex];
            if (is_array($last)
                && $last['sourceLineId'] === $edge['sourceLineId']
                && $last['mappingMode'] === $edge['mappingMode']
                && $last['scopeId'] === $edge['scopeId']
                && $last['endByte'] === $edge['startByte']
                && $last['outputEnd'] === $edge['outputStart']) {
                $normalized[$lastIndex]['endByte'] = $edge['endByte'];
                $normalized[$lastIndex]['outputEnd'] = $edge['outputEnd'];
                continue;
            }
            $normalized[] = $edge;
        }

        return $normalized;
    }

    /** @param list<array<string,mixed>> $edges @return list<array{sourceLineId:string,startByte:int,endByte:int}> */
    private static function publicSourceBindingEdges(array $edges): array
    {
        return array_map(
            static fn (array $edge): array => [
                'sourceLineId' => (string) $edge['sourceLineId'],
                'startByte' => (int) $edge['startByte'],
                'endByte' => (int) $edge['endByte'],
            ],
            $edges
        );
    }

    /**
     * @param list<array{sourceLineId:string,startByte:int,endByte:int}> $topBlockEdges
     * @param array<string,list<array<string,mixed>>> $leafEdges
     * @param array<string,list<string>> $inlineIdsBySource
     */
    private static function decoratedSourceBindingNode(
        AstNode $node,
        string $path,
        bool $topLevel,
        string $topNodeId,
        array $topBlockEdges,
        array &$leafEdges,
        array &$inlineIdsBySource
    ): AstNode {
        $children = [];
        $edges = [];
        $edgesArePublic = false;
        if ($node->type === 'text' || $node->type === 'code_block') {
            $edges = $leafEdges[$path] ?? [];
            unset($leafEdges[$path]);
        } else {
            $uniquePublicEdges = [];
            foreach ($node->children() as $childIndex => $child) {
                $childPath = $path . '.' . $childIndex;
                $decoratedChild = self::decoratedSourceBindingNode(
                    $child,
                    $childPath,
                    false,
                    $topNodeId,
                    $topBlockEdges,
                    $leafEdges,
                    $inlineIdsBySource
                );
                $children[] = $decoratedChild;
                if (!$topLevel) {
                    foreach ($decoratedChild->attr('sourceLineEdges', []) as $edge) {
                        if (!is_array($edge)) {
                            continue;
                        }
                        // Child edges are already the immutable public shape.
                        // Reuse those inner arrays and deduplicate by their
                        // exact occurrence span instead of expanding each one
                        // back to the larger private intersection shape.
                        $key = $edge['sourceLineId'] . ':' . $edge['startByte'] . ':' . $edge['endByte'];
                        $uniquePublicEdges[$key] = $edge;
                    }
                }
            }
            if (!$topLevel) {
                $edges = array_values($uniquePublicEdges);
                $edgesArePublic = true;
            }
        }
        if ($topLevel) {
            $edges = $topBlockEdges;
            $edgesArePublic = true;
        }

        $attrs = self::sourceBindingAttrsWithoutDecoration($node);
        if ($edges !== []) {
            $publicEdges = $edgesArePublic ? $edges : self::publicSourceBindingEdges($edges);
            $sourceLineIds = self::sourceBindingSourceLineIds($publicEdges);
            $nodeId = $topLevel
                ? $topNodeId
                : 'pdf-source-inline-' . substr(hash(
                    'sha256',
                    $topNodeId . "\0" . $path . "\0" . serialize($publicEdges)
                ), 0, 32);
            $attrs['sourceNodeId'] = $nodeId;
            $attrs['sourceLineIds'] = $sourceLineIds;
            $attrs['sourceLineEdges'] = $publicEdges;
            if (!$topLevel) {
                foreach ($sourceLineIds as $sourceId) {
                    $inlineIdsBySource[$sourceId][] = $nodeId;
                }
            }
        }

        return new AstNode($node->type, $attrs, $children);
    }

    /**
     * Dense PDF tables can contain tens of thousands of text leaves. Retaining
     * the same exact edge at text, formatting, plain, row, section, cell, and
     * table levels multiplies immutable provenance without adding a distinct
     * semantic destination. Use the table and each nonempty table cell as the
     * canonical provenance boundaries; lists and every non-table tree keep
     * their full decoration so semantic list-marker binding is unchanged.
     *
     * @param list<array{sourceLineId:string,startByte:int,endByte:int}> $topBlockEdges
     * @param array<string,list<array<string,mixed>>> $leafEdges
     * @param array<string,list<string>> $inlineIdsBySource
     */
    private static function decoratedCompactTableSourceBindingNode(
        AstNode $node,
        string $path,
        string $topNodeId,
        array $topBlockEdges,
        array &$leafEdges,
        array &$inlineIdsBySource
    ): AstNode {
        $children = [];
        foreach ($node->children() as $childIndex => $child) {
            $children[] = self::decoratedCompactTableStructureNode(
                $child,
                $path . '.' . $childIndex,
                $topNodeId,
                $leafEdges,
                $inlineIdsBySource
            );
        }

        $attrs = self::sourceBindingAttrsWithoutDecoration($node);
        if ($topBlockEdges !== []) {
            $attrs['sourceNodeId'] = $topNodeId;
            $attrs['sourceLineIds'] = self::sourceBindingSourceLineIds($topBlockEdges);
            $attrs['sourceLineEdges'] = $topBlockEdges;
        }

        return new AstNode($node->type, $attrs, $children);
    }

    /**
     * Rebuild table structure without redundant provenance on section and row
     * wrappers. A cell consumes all exact leaf edges in its own subtree.
     *
     * @param array<string,list<array<string,mixed>>> $leafEdges
     * @param array<string,list<string>> $inlineIdsBySource
     */
    private static function decoratedCompactTableStructureNode(
        AstNode $node,
        string $path,
        string $topNodeId,
        array &$leafEdges,
        array &$inlineIdsBySource
    ): AstNode {
        if ($node->type === 'table_cell') {
            return self::decoratedCompactTableCell(
                $node,
                $path,
                $topNodeId,
                $leafEdges,
                $inlineIdsBySource
            );
        }

        $children = [];
        foreach ($node->children() as $childIndex => $child) {
            $children[] = self::decoratedCompactTableStructureNode(
                $child,
                $path . '.' . $childIndex,
                $topNodeId,
                $leafEdges,
                $inlineIdsBySource
            );
        }

        return new AstNode(
            $node->type,
            self::sourceBindingAttrsWithoutDecoration($node),
            $children
        );
    }

    /**
     * @param array<string,list<array<string,mixed>>> $leafEdges
     * @param array<string,list<string>> $inlineIdsBySource
     */
    private static function decoratedCompactTableCell(
        AstNode $node,
        string $path,
        string $topNodeId,
        array &$leafEdges,
        array &$inlineIdsBySource
    ): AstNode {
        $uniquePublicEdges = [];
        $children = [];
        foreach ($node->children() as $childIndex => $child) {
            $children[] = self::decoratedCompactTableCellContentNode(
                $child,
                $path . '.' . $childIndex,
                $leafEdges,
                $uniquePublicEdges
            );
        }

        $attrs = self::sourceBindingAttrsWithoutDecoration($node);
        $publicEdges = array_values($uniquePublicEdges);
        if ($publicEdges !== []) {
            $sourceLineIds = self::sourceBindingSourceLineIds($publicEdges);
            $nodeId = 'pdf-source-inline-' . substr(hash(
                'sha256',
                $topNodeId . "\0" . $path . "\0" . serialize($publicEdges)
            ), 0, 32);
            $attrs['sourceNodeId'] = $nodeId;
            $attrs['sourceLineIds'] = $sourceLineIds;
            $attrs['sourceLineEdges'] = $publicEdges;
            foreach ($sourceLineIds as $sourceId) {
                $inlineIdsBySource[$sourceId][] = $nodeId;
            }
        }

        return new AstNode($node->type, $attrs, $children);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $leafEdges
     * @param array<string,array{sourceLineId:string,startByte:int,endByte:int}> $uniquePublicEdges
     */
    private static function decoratedCompactTableCellContentNode(
        AstNode $node,
        string $path,
        array &$leafEdges,
        array &$uniquePublicEdges
    ): AstNode {
        $children = [];
        if ($node->type === 'text' || $node->type === 'code_block') {
            $edges = $leafEdges[$path] ?? [];
            unset($leafEdges[$path]);
            foreach (self::publicSourceBindingEdges($edges) as $edge) {
                $key = $edge['sourceLineId'] . ':' . $edge['startByte'] . ':' . $edge['endByte'];
                $uniquePublicEdges[$key] = $edge;
            }
        } else {
            foreach ($node->children() as $childIndex => $child) {
                $children[] = self::decoratedCompactTableCellContentNode(
                    $child,
                    $path . '.' . $childIndex,
                    $leafEdges,
                    $uniquePublicEdges
                );
            }
        }

        return new AstNode(
            $node->type,
            self::sourceBindingAttrsWithoutDecoration($node),
            $children
        );
    }

    private static function sourceBindingTableTextLeavesAreCellScoped(
        AstNode $node,
        bool $insideCell = false
    ): bool {
        $insideCell = $insideCell || $node->type === 'table_cell';
        if ($node->type === 'text' || $node->type === 'code_block') {
            return $insideCell || self::significantText((string) $node->attr('text', '')) === '';
        }
        foreach ($node->children() as $child) {
            if (!self::sourceBindingTableTextLeavesAreCellScoped($child, $insideCell)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,mixed> */
    private static function sourceBindingAttrsWithoutDecoration(AstNode $node): array
    {
        $attrs = $node->attrs;
        foreach (['sourceNodeId', 'sourceLineIds', 'sourceLineEdges'] as $key) {
            unset($attrs[$key]);
        }

        return $attrs;
    }

    /**
     * @param list<array{sourceLineId:string,startByte:int,endByte:int}> $publicEdges
     * @return list<string>
     */
    private static function sourceBindingSourceLineIds(array $publicEdges): array
    {
        $sourceIds = [];
        foreach ($publicEdges as $edge) {
            $sourceIds[$edge['sourceLineId']] = true;
        }

        return array_keys($sourceIds);
    }

    /** @return array<string,mixed> */
    private static function sourceEdgeForOccurrence(
        string $sourceId,
        int $page,
        string $disposition,
        ?array $mapping
    ): array {
        $status = is_array($mapping) ? ($mapping['status'] ?? 'unresolved') : 'unresolved';
        $outputDisposition = isset(self::OUTPUT_DISPOSITIONS[$disposition]);
        $target = $disposition === 'unresolved'
            ? 'unresolved'
            : ($outputDisposition && $status === 'output'
                ? 'output'
                : (!$outputDisposition && $status === 'disposition' ? 'disposition' : 'unresolved'));
        $nodeIds = $target === 'output' ? ($mapping['destinationNodeIds'] ?? []) : [];
        $inlineIds = $target === 'output' ? ($mapping['destinationInlineIds'] ?? []) : [];
        $identity = [
            'sourceOccurrenceId' => $sourceId,
            'page' => $page,
            'disposition' => $disposition,
            'target' => $target,
            'mappingMode' => $mapping['mappingMode'] ?? 'unmapped',
            'destinationNodeIds' => $nodeIds,
            'destinationInlineIds' => $inlineIds,
            'orderScopeId' => $mapping['scopeId'] ?? null,
        ];
        $encoded = json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ['id' => 'pdf-source-edge-' . substr(hash(
            'sha256',
            is_string($encoded) ? $encoded : serialize($identity)
        ), 0, 32)] + $identity;
    }

    /** @param list<array<string,mixed>> $edges */
    private static function sourceEdgeDigest(array $edges): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $digest = hash_init('sha256');
        hash_update($digest, '[');
        foreach ($edges as $index => $edge) {
            $encoded = json_encode($edge, $flags);
            if (!is_string($encoded)) {
                // Match the prior whole-list fallback exactly for malformed
                // UTF-8 or another value JSON cannot represent.
                return hash('sha256', serialize($edges));
            }
            if ($index > 0) {
                hash_update($digest, ',');
            }
            hash_update($digest, $encoded);
        }
        hash_update($digest, ']');

        return hash_final($digest);
    }

    /**
     * An optional exact mapping is deliberately part of the disposition API
     * even though PdfReader currently supplies only page/region geometry.
     * Once emitted nodes retain source IDs, the reader can name the exact
     * occurrence set and expected emitted projection here. Until then the
     * fallback proof below is explicitly only region-bounded.
     *
     * @return array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string,emittedSourceOccurrenceIds?:list<string>,emittedSourceRanges?:list<array{sourceOccurrenceId:string,sourceStart:int,sourceEnd:int}>}|null
     */
    private static function normalizedExplicitOrderProof(mixed $value, string $id): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an invalid order proof.');
        }
        $scopeId = is_string($value['scopeId'] ?? null) ? trim($value['scopeId']) : '';
        $projection = is_string($value['emittedTextProjection'] ?? null)
            ? $value['emittedTextProjection']
            : null;
        $sourceIds = [];
        foreach (is_array($value['sourceOccurrenceIds'] ?? null) ? $value['sourceOccurrenceIds'] : [] as $sourceId) {
            if (!is_string($sourceId) || $sourceId === '' || isset($sourceIds[$sourceId])) {
                throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an invalid order-proof source set.');
            }
            $sourceIds[$sourceId] = true;
        }
        if ($scopeId === '' || $projection === null || $sourceIds === [] || !isset($sourceIds[$id])) {
            throw new InvalidArgumentException('PDF source occurrence ' . $id . ' has an incomplete order proof.');
        }

        $emittedSourceIds = null;
        if (array_key_exists('emittedSourceOccurrenceIds', $value)) {
            $emittedSourceIds = [];
            foreach (is_array($value['emittedSourceOccurrenceIds'] ?? null)
                ? $value['emittedSourceOccurrenceIds']
                : [] as $sourceId) {
                if (!is_string($sourceId) || $sourceId === '' || isset($emittedSourceIds[$sourceId])) {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' has an invalid emitted order-proof source set.'
                    );
                }
                $emittedSourceIds[$sourceId] = true;
            }
            $declared = array_keys($sourceIds);
            $emitted = array_keys($emittedSourceIds);
            $declaredSorted = $declared;
            $emittedSorted = $emitted;
            sort($declaredSorted, SORT_STRING);
            sort($emittedSorted, SORT_STRING);
            if ($emitted === [] || $emittedSorted !== $declaredSorted) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has a non-conserving emitted order-proof source set.'
                );
            }
        }

        $emittedSourceRanges = null;
        if (array_key_exists('emittedSourceRanges', $value)) {
            if (is_array($emittedSourceIds)) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has competing emitted order-proof mappings.'
                );
            }
            $emittedSourceRanges = [];
            foreach (is_array($value['emittedSourceRanges'] ?? null)
                ? $value['emittedSourceRanges']
                : [] as $range) {
                $sourceId = is_array($range) && is_string($range['sourceOccurrenceId'] ?? null)
                    ? $range['sourceOccurrenceId']
                    : '';
                $sourceStart = is_array($range) && is_int($range['sourceStart'] ?? null)
                    ? $range['sourceStart']
                    : null;
                $sourceEnd = is_array($range) && is_int($range['sourceEnd'] ?? null)
                    ? $range['sourceEnd']
                    : null;
                if ($sourceId === ''
                    || !isset($sourceIds[$sourceId])
                    || $sourceStart === null
                    || $sourceEnd === null
                    || $sourceStart < 0
                    || $sourceEnd <= $sourceStart) {
                    throw new InvalidArgumentException(
                        'PDF source occurrence ' . $id . ' has an invalid emitted order-proof source range.'
                    );
                }
                $emittedSourceRanges[] = [
                    'sourceOccurrenceId' => $sourceId,
                    'sourceStart' => $sourceStart,
                    'sourceEnd' => $sourceEnd,
                ];
            }
            if ($emittedSourceRanges === []) {
                throw new InvalidArgumentException(
                    'PDF source occurrence ' . $id . ' has an empty emitted order-proof source range set.'
                );
            }
        }

        $proof = [
            'scopeId' => $scopeId,
            'sourceOccurrenceIds' => array_keys($sourceIds),
            'emittedTextProjection' => $projection,
        ];
        if (is_array($emittedSourceIds)) {
            $proof['emittedSourceOccurrenceIds'] = array_keys($emittedSourceIds);
        }
        if (is_array($emittedSourceRanges)) {
            $proof['emittedSourceRanges'] = $emittedSourceRanges;
        }

        return $proof;
    }

    /** @param array<string,mixed> $explicitDispositions */
    private static function hasRequestedOrderChange(array $explicitDispositions): bool
    {
        foreach ($explicitDispositions as $disposition) {
            if (is_array($disposition) && ($disposition['allowOrderChange'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{disposition:string,reason:string,evidence:array<string,mixed>,textProjection:?string,allowOrderChange:bool,orderProof:?array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string}} $explicit
     * @return array{key:string,mode:string,orderProof:?array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string}}|null
     */
    private static function localOrderChangeScope(array $explicit, string $id, int $page): ?array
    {
        $evidence = $explicit['evidence'];
        $hypothesis = is_string($evidence['hypothesis'] ?? null) ? trim($evidence['hypothesis']) : '';
        $bounds = self::normalizedBounds($evidence['bounds'] ?? null);
        $sourceBounds = self::normalizedBounds($evidence['sourceBounds'] ?? null);
        if ($hypothesis === ''
            || $bounds === null
            || $sourceBounds === null
            || !self::boundsIntersect($bounds, $sourceBounds)) {
            return null;
        }
        $featureDigest = is_string($evidence['featureDigest'] ?? null)
            ? $evidence['featureDigest']
            : '';
        $scope = [
            'page' => $page,
            'hypothesis' => $hypothesis,
            'bounds' => $bounds,
            'featureDigest' => $featureDigest,
        ];
        $scopeJson = json_encode($scope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $scopeKey = hash('sha256', is_string($scopeJson) ? $scopeJson : $id);
        $orderProof = $explicit['orderProof'];
        if ($orderProof !== null) {
            // The exact proof, not the local detector that first authorized a
            // contributor, defines one mapped reorder scope. A page proof can
            // legitimately cover full-width boundary prose and a nested
            // independent-column region, whose hypotheses/bounds differ.
            // Retain the geometry checks above as the authorization gate, but
            // group only identical proofs on the same physical page. Including
            // the page prevents a reused/malformed proof from coalescing two
            // unrelated page-local scopes.
            $proofKey = is_string($explicit['orderProofKey'] ?? null)
                ? $explicit['orderProofKey']
                : '';
            if ($proofKey === '') {
                $proofJson = json_encode(
                    $orderProof,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
                $proofKey = hash(
                    'sha256',
                    is_string($proofJson) ? $proofJson : serialize($orderProof)
                );
            }
            $scopeKey = hash(
                'sha256',
                $page . "\0" . $orderProof['scopeId'] . "\0" . $proofKey
            );
        }

        return [
            'key' => $scopeKey,
            'mode' => $orderProof === null ? 'region-bounded-inventory' : 'mapped-occurrence-exact',
            'orderProof' => $orderProof,
        ];
    }

    /** @return array{x1:float,y1:float,x2:float,y2:float}|null */
    private static function normalizedBounds(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        foreach (['x1', 'y1', 'x2', 'y2'] as $key) {
            if (!is_numeric($value[$key] ?? null) || !is_finite((float) $value[$key])) {
                return null;
            }
        }

        return [
            'x1' => round(min((float) $value['x1'], (float) $value['x2']), 4),
            'y1' => round(min((float) $value['y1'], (float) $value['y2']), 4),
            'x2' => round(max((float) $value['x1'], (float) $value['x2']), 4),
            'y2' => round(max((float) $value['y1'], (float) $value['y2']), 4),
        ];
    }

    /**
     * @param array{x1:float,y1:float,x2:float,y2:float} $left
     * @param array{x1:float,y1:float,x2:float,y2:float} $right
     */
    private static function boundsIntersect(array $left, array $right): bool
    {
        return max($left['x1'], $right['x1']) <= min($left['x2'], $right['x2'])
            && max($left['y1'], $right['y1']) <= min($left['y2'], $right['y2']);
    }

    /**
     * @param list<array<string,mixed>|string> $segments
     * @param array<string,mixed>|null $current
     * @param array{key:string,mode:string,orderProof:?array{scopeId:string,sourceOccurrenceIds:list<string>,emittedTextProjection:string}}|null $scope
     */
    private static function appendOrderProofSegment(
        array &$segments,
        ?array &$current,
        string $text,
        string $sourceOccurrenceId,
        ?array $scope
    ): void {
        $kind = $scope === null ? 'exact' : $scope['mode'];
        $scopeKey = $scope['key'] ?? '';
        if ($kind === 'exact') {
            self::flushOrderProofSegment($segments, $current);
            $segments[] = self::significantText($text);

            return;
        }
        if (is_array($current)
            && (($current['kind'] ?? null) !== $kind
                || ($current['scopeKey'] ?? '') !== $scopeKey)) {
            self::flushOrderProofSegment($segments, $current);
        }
        if ($current === null) {
            $current = [
                'kind' => $kind,
                'scopeKey' => $scopeKey,
                'bytes' => 0,
                'digestContext' => null,
                'characters' => [],
                'sourceOccurrenceIds' => [],
                'orderProof' => $scope['orderProof'] ?? null,
            ];
        }
        $current['bytes'] += self::updateSignificantCharacterInventory($current['characters'], $text);
        $current['sourceOccurrenceIds'][] = $sourceOccurrenceId;
    }

    /**
     * @param list<array<string,mixed>|string> $segments
     * @param array<string,mixed>|null $current
     */
    private static function flushOrderProofSegment(array &$segments, ?array &$current): void
    {
        if ($current === null) {
            return;
        }
        if ($current['kind'] === 'exact') {
            $current['digest'] = hash_final($current['digestContext']);
        } else {
            ksort($current['characters']);
            if ($current['kind'] === 'mapped-occurrence-exact') {
                $proof = $current['orderProof'];
                if (!is_array($proof)
                    || $proof['sourceOccurrenceIds'] !== $current['sourceOccurrenceIds']) {
                    $current['invalidReason'] = 'mapped-order-proof-source-occurrences-do-not-match-scope';
                } else {
                    $expectedSummary = self::significantCharacterSummary([$proof['emittedTextProjection']]);
                    $expectedCharacters = [];
                    self::updateSignificantCharacterInventory(
                        $expectedCharacters,
                        $proof['emittedTextProjection']
                    );
                    ksort($expectedCharacters);
                    if ($expectedSummary['bytes'] !== $current['bytes']
                        || $expectedCharacters !== $current['characters']) {
                        $current['invalidReason'] = 'mapped-order-proof-does-not-conserve-source-characters';
                    } else {
                        $current['digest'] = $expectedSummary['digest'];
                        $current['mappedProjection'] = self::significantText(
                            $proof['emittedTextProjection']
                        );
                    }
                }
            }
        }
        unset($current['digestContext'], $current['orderProof']);
        $segments[] = $current;
        $current = null;
    }

    /**
     * @param list<array<string,mixed>|string> $segments
     * @param list<AstNode> $blocks
     * @return array{preserved:bool,strength:string,failureReason:?string}
     */
    private static function proveLocalOrderSegments(array $segments, array $blocks): array
    {
        $sequential = self::proveSequentialLocalOrderSegments(
            $segments,
            self::textChunksFromNodes($blocks)
        );
        if ($sequential['preserved']) {
            return $sequential;
        }
        $mappedInterleaved = self::proveOneMappedRegionAroundOrderedOccurrences(
            $segments,
            self::textChunksFromNodes($blocks)
        );
        if ($mappedInterleaved['preserved']) {
            return $mappedInterleaved;
        }
        $interleaved = self::proveOneRegionAroundOrderedOccurrences(
            $segments,
            self::textChunksFromNodes($blocks)
        );

        return $interleaved['preserved'] ? $interleaved : $sequential;
    }

    /**
     * @param list<array<string,mixed>|string> $segments
     * @param iterable<string> $emittedChunks
     * @return array{preserved:bool,strength:string,failureReason:?string}
     */
    private static function proveSequentialLocalOrderSegments(array $segments, iterable $emittedChunks): array
    {
        $characters = self::significantCharactersFromChunks($emittedChunks);
        $characters->rewind();
        $strength = 'mapped-occurrence-exact';
        foreach ($segments as $segment) {
            if (is_string($segment)) {
                $segment = [
                    'kind' => 'exact',
                    'bytes' => strlen($segment),
                    'digest' => hash('sha256', $segment),
                ];
            }
            if (is_string($segment['invalidReason'] ?? null)) {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => $segment['invalidReason'],
                ];
            }
            $expectedBytes = max(0, (int) ($segment['bytes'] ?? 0));
            $consumedBytes = 0;
            $digest = hash_init('sha256');
            $inventory = [];
            while ($consumedBytes < $expectedBytes) {
                if (!$characters->valid()) {
                    return [
                        'preserved' => false,
                        'strength' => 'mismatch',
                        'failureReason' => 'emitted-significant-characters-ended-before-order-segment',
                    ];
                }
                $character = (string) $characters->current();
                $consumedBytes += strlen($character);
                if ($consumedBytes > $expectedBytes) {
                    return [
                        'preserved' => false,
                        'strength' => 'mismatch',
                        'failureReason' => 'emitted-significant-character-crossed-order-segment-boundary',
                    ];
                }
                if ($segment['kind'] === 'region-bounded-inventory') {
                    $inventory[$character] = ($inventory[$character] ?? 0) + 1;
                    $strength = 'region-bounded-inventory';
                } else {
                    hash_update($digest, $character);
                }
                $characters->next();
            }
            if ($segment['kind'] === 'region-bounded-inventory') {
                ksort($inventory);
                if ($inventory !== ($segment['characters'] ?? [])) {
                    return [
                        'preserved' => false,
                        'strength' => 'mismatch',
                        'failureReason' => 'region-bounded-order-segment-character-mismatch',
                    ];
                }
            } else {
                $actualDigest = hash_final($digest);
                if (!is_string($segment['digest'] ?? null)
                    || !hash_equals($segment['digest'], $actualDigest)) {
                    return [
                        'preserved' => false,
                        'strength' => 'mismatch',
                        'failureReason' => $segment['kind'] === 'exact'
                            ? 'non-authorized-order-segment-mismatch'
                            : 'mapped-order-segment-mismatch',
                    ];
                }
            }
        }
        if ($characters->valid()) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'unclaimed-significant-characters-remain-after-order-segments',
            ];
        }

        return ['preserved' => true, 'strength' => $strength, 'failureReason' => null];
    }

    /**
     * One exact mapped scope may be split by unchanged occurrences, such as a
     * page marker between two independent-column flows. The unchanged texts
     * retain their exact relative order, and their sole valid removal must
     * leave precisely the proof's emitted projection.
     *
     * @param list<array<string,mixed>|string> $segments
     * @param iterable<string> $emittedChunks
     * @return array{preserved:bool,strength:string,failureReason:?string}
     */
    private static function proveOneMappedRegionAroundOrderedOccurrences(
        array $segments,
        iterable $emittedChunks
    ): array {
        $scopeKeys = [];
        $mappedProjection = null;
        $exactOccurrences = [];
        foreach ($segments as $segment) {
            if (is_string($segment)) {
                $exactOccurrences[] = $segment;
                continue;
            }
            if ($segment['kind'] === 'exact') {
                $exactOccurrences[] = (string) ($segment['text'] ?? '');
                continue;
            }
            if ($segment['kind'] !== 'mapped-occurrence-exact'
                || is_string($segment['invalidReason'] ?? null)
                || $mappedProjection !== null) {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => 'interleaved-order-proof-requires-one-mapped-scope',
                ];
            }
            $scopeKey = is_string($segment['scopeKey'] ?? null) ? $segment['scopeKey'] : '';
            $projection = $segment['mappedProjection'] ?? null;
            if ($scopeKey === '' || !is_string($projection) || $projection === '') {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => 'interleaved-mapped-order-proof-is-incomplete',
                ];
            }
            $scopeKeys[$scopeKey] = true;
            $mappedProjection = $projection;
        }
        if (count($scopeKeys) !== 1 || $mappedProjection === null || $exactOccurrences === []) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'interleaved-order-proof-requires-one-mapped-scope',
            ];
        }

        $emitted = '';
        foreach ($emittedChunks as $chunk) {
            if (is_string($chunk) && $chunk !== '') {
                $emitted .= self::significantText($chunk);
            }
        }
        if (self::uniqueInterleavedExactProjectionLayout(
            $emitted,
            $exactOccurrences,
            $mappedProjection
        ) === null) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'mapped-order-segment-interleaving-mismatch',
            ];
        }

        return [
            'preserved' => true,
            'strength' => 'mapped-occurrence-exact',
            'failureReason' => null,
        ];
    }

    /**
     * Native text lines can contain a full visual row while an independent-
     * column output emits the left flow, a full-width atomic item, and then
     * the right flow. In that case one authorized region is non-contiguous in
     * emitted text. Permit only that region's exact character inventory to
     * fill gaps around non-authorized source occurrences; those occurrences
     * must still match internally and in their original relative order.
     *
     * This is intentionally limited to one geometry scope. Multiple regions
     * require the explicit mapped-occurrence proof accepted by the API.
     *
     * @param list<array<string,mixed>|string> $segments
     * @param iterable<string> $emittedChunks
     * @return array{preserved:bool,strength:string,failureReason:?string}
     */
    private static function proveOneRegionAroundOrderedOccurrences(array $segments, iterable $emittedChunks): array
    {
        $scopeKeys = [];
        $authorizedCharacters = [];
        $exactOccurrences = [];
        foreach ($segments as $segment) {
            if (is_string($segment)) {
                $exactOccurrences[] = $segment;
                continue;
            }
            if ($segment['kind'] === 'exact') {
                $exactOccurrences[] = (string) ($segment['text'] ?? '');
                continue;
            }
            if ($segment['kind'] !== 'region-bounded-inventory'
                || is_string($segment['invalidReason'] ?? null)) {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => 'interleaved-order-proof-requires-one-unmapped-region',
                ];
            }
            $scopeKey = is_string($segment['scopeKey'] ?? null) ? $segment['scopeKey'] : '';
            if ($scopeKey === '') {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => 'interleaved-order-proof-has-no-region-scope',
                ];
            }
            $scopeKeys[$scopeKey] = true;
            foreach ($segment['characters'] ?? [] as $character => $count) {
                $authorizedCharacters[$character] = ($authorizedCharacters[$character] ?? 0) + (int) $count;
            }
        }
        if (count($scopeKeys) !== 1 || $authorizedCharacters === []) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'interleaved-order-proof-requires-one-region-scope',
            ];
        }

        $emitted = '';
        foreach ($emittedChunks as $chunk) {
            if (is_string($chunk) && $chunk !== '') {
                $emitted .= self::significantText($chunk);
            }
        }
        $cursor = 0;
        foreach ($exactOccurrences as $exact) {
            if ($exact === '') {
                continue;
            }
            $searchOffset = $cursor;
            $matched = false;
            $candidateCount = 0;
            while (($found = strpos($emitted, $exact, $searchOffset)) !== false) {
                $candidateCount++;
                if ($candidateCount > self::ORDER_MATCH_CANDIDATE_LIMIT) {
                    return [
                        'preserved' => false,
                        'strength' => 'mismatch',
                        'failureReason' => 'non-authorized-occurrence-match-candidate-limit',
                    ];
                }
                $gap = substr($emitted, $cursor, $found - $cursor);
                $gapCharacters = [];
                self::updateSignificantCharacterInventory($gapCharacters, $gap);
                if (self::canConsume($authorizedCharacters, $gapCharacters)) {
                    self::consume($authorizedCharacters, $gapCharacters);
                    $cursor = $found + strlen($exact);
                    $matched = true;
                    break;
                }
                $searchOffset = $found + 1;
            }
            if (!$matched) {
                return [
                    'preserved' => false,
                    'strength' => 'mismatch',
                    'failureReason' => 'non-authorized-occurrence-order-mismatch',
                ];
            }
        }
        $tailCharacters = [];
        self::updateSignificantCharacterInventory($tailCharacters, substr($emitted, $cursor));
        if (!self::canConsume($authorizedCharacters, $tailCharacters)) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'region-bounded-order-tail-character-mismatch',
            ];
        }
        self::consume($authorizedCharacters, $tailCharacters);
        if ($authorizedCharacters !== []) {
            return [
                'preserved' => false,
                'strength' => 'mismatch',
                'failureReason' => 'region-bounded-order-characters-remain-unclaimed',
            ];
        }

        return [
            'preserved' => true,
            'strength' => 'region-bounded-ordered-occurrences',
            'failureReason' => null,
        ];
    }

    /** @param array<string,mixed> $record */
    private static function sourceOccurrenceId(array $record, int $index, string $text): string
    {
        if (is_string($record['id'] ?? null) && $record['id'] !== '') {
            return $record['id'];
        }
        $identity = json_encode([
            'page' => max(1, (int) ($record['page'] ?? 1)),
            'stream' => max(1, (int) ($record['stream'] ?? $index + 1)),
            'index' => $index,
            'text' => $text,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'line-' . substr(hash('sha256', is_string($identity) ? $identity : $text), 0, 24);
    }

    /**
     * @param iterable<string> $chunks
     * @return array{tokens:array<string,int>,characters:array<string,int>}
     */
    private static function inventoryFromChunks(iterable $chunks): array
    {
        $tokens = [];
        $characters = [];
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $normalized = self::normalizeText($chunk);
            $matched = preg_match_all(
                "/[\p{L}\p{M}\p{N}]+(?:['\x{2019}][\p{L}\p{M}\p{N}]+)*/u",
                $normalized,
                $tokenMatches
            );
            if ($matched !== false) {
                foreach ($tokenMatches[0] as $token) {
                    $tokens[$token] = ($tokens[$token] ?? 0) + 1;
                }
            }
            $offset = 0;
            $length = strlen($normalized);
            while ($offset < $length) {
                $found = preg_match('/[^\s\p{Cc}\p{Cf}]/u', $normalized, $match, PREG_OFFSET_CAPTURE, $offset);
                if ($found !== 1) {
                    break;
                }
                $character = (string) $match[0][0];
                $byteOffset = (int) $match[0][1];
                $characters[$character] = ($characters[$character] ?? 0) + 1;
                $offset = $byteOffset + strlen($character);
            }
        }

        return compact('tokens', 'characters');
    }

    private static function normalizeText(string $text): string
    {
        $text = str_replace(["\u{00AD}", "\u{2060}"], '', $text);
        if (class_exists('Normalizer')) {
            // Canonical normalization joins equivalent combining sequences,
            // but deliberately keeps compatibility characters such as ²,
            // ﬀ, and circled digits distinct. An occurrence disposition is
            // allowed to prove boundary/order changes, never substitutions.
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    /** @param iterable<string> $chunks @return array{bytes:int,digest:string} */
    private static function significantCharacterSummary(iterable $chunks): array
    {
        $digest = hash_init('sha256');
        $bytes = 0;
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $bytes += self::updateSignificantCharacterDigest($digest, $chunk);
        }

        return ['bytes' => $bytes, 'digest' => hash_final($digest)];
    }

    /** @param \HashContext $digest */
    private static function updateSignificantCharacterDigest(object $digest, string $chunk): int
    {
        $significant = self::significantText($chunk);
        hash_update($digest, $significant);

        return strlen($significant);
    }

    private static function significantText(string $chunk): string
    {
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($chunk, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $chunk = $normalized;
            }
        }

        return preg_replace('/[\s\p{Cc}\p{Cf}]+/u', '', $chunk) ?? $chunk;
    }

    /** @param array<string,int> $characters */
    private static function updateSignificantCharacterInventory(array &$characters, string $chunk): int
    {
        $significant = self::significantText($chunk);
        $offset = 0;
        $length = strlen($significant);
        while ($offset < $length) {
            $found = preg_match('/./us', $significant, $match, PREG_OFFSET_CAPTURE, $offset);
            if ($found !== 1) {
                // Invalid UTF-8 is already a fidelity mismatch elsewhere. It
                // must not turn into an order authorization here.
                $character = $significant[$offset];
                $characters[$character] = ($characters[$character] ?? 0) + 1;
                $offset++;
                continue;
            }
            $character = (string) $match[0][0];
            $byteOffset = (int) $match[0][1];
            $characters[$character] = ($characters[$character] ?? 0) + 1;
            $offset = $byteOffset + strlen($character);
        }

        return $length;
    }

    /** @param iterable<string> $chunks @return \Generator<int,string> */
    private static function significantCharactersFromChunks(iterable $chunks): \Generator
    {
        foreach ($chunks as $chunk) {
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $significant = self::significantText($chunk);
            $offset = 0;
            $length = strlen($significant);
            while ($offset < $length) {
                $found = preg_match('/./us', $significant, $match, PREG_OFFSET_CAPTURE, $offset);
                if ($found !== 1) {
                    yield $significant[$offset];
                    $offset++;
                    continue;
                }
                $character = (string) $match[0][0];
                $byteOffset = (int) $match[0][1];
                yield $character;
                $offset = $byteOffset + strlen($character);
            }
        }
    }

    /** @param array<string,int> $available @param array<string,int> $needed */
    private static function canConsume(array $available, array $needed): bool
    {
        foreach ($needed as $value => $count) {
            if (($available[$value] ?? 0) < $count) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,int> $available @param array<string,int> $needed */
    private static function consume(array &$available, array $needed): void
    {
        foreach ($needed as $value => $count) {
            $remaining = ($available[$value] ?? 0) - $count;
            if ($remaining > 0) {
                $available[$value] = $remaining;
            } else {
                unset($available[$value]);
            }
        }
    }

    /** @param list<AstNode> $nodes @return iterable<string> */
    private static function textChunksFromNodes(array $nodes): iterable
    {
        foreach ($nodes as $node) {
            if ($node instanceof AstNode) {
                yield from self::textChunksFromNode($node);
            }
        }
    }

    /** @return iterable<string> */
    private static function textChunksFromNode(AstNode $node): iterable
    {
        if ($node->type === 'text') {
            $text = (string) $node->attr('text', '');
            if ($text !== '') {
                yield $text;
            }

            return;
        }
        // CodeBlock is a Pandoc leaf whose payload is stored in its `text`
        // attribute rather than in child Text nodes. Ignoring that payload
        // makes a verbatim emitted listing look wholly unresolved and also
        // invalidates the ordered-character proof. Keep this deliberately
        // type-specific: arbitrary container `text` attributes can be
        // derived summaries of children and would otherwise be double-counted.
        if ($node->type === 'code_block') {
            $text = (string) $node->attr('text', '');
            if ($text !== '') {
                yield $text;
            }

            return;
        }
        foreach ($node->children() as $child) {
            yield from self::textChunksFromNode($child);
        }
    }

    private static function sampleText(string $text): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 160, 'UTF-8');
        }

        return substr($text, 0, 160);
    }
}
