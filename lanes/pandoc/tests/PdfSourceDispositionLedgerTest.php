<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PdfSourceDispositionLedger;

$paragraph = static fn (string $text): AstNode => new AstNode('paragraph', [], [
    new AstNode('text', ['text' => $text]),
]);
$orderedList = static fn (string $text, int $start = 1): AstNode => new AstNode(
    'ordered_list',
    ['start' => $start],
    [new AstNode('list_item', [], [$paragraph($text)])]
);
$semanticMarkerProof = static fn (
    string $marker,
    string $anchorId,
    string $itemSignificant,
    int $ordinal = 1
): array => [
    'version' => 1,
    'method' => 'exact-standalone-list-marker-to-item',
    'listType' => 'ordered',
    'markerOrdinal' => $ordinal,
    'markerDigest' => hash('sha256', $marker),
    'anchorSourceOccurrenceId' => $anchorId,
    'itemProjectionDigest' => hash('sha256', $itemSignificant),
];

return [
    'pdf source disposition ledger ignores Unicode whitespace-only source occurrences' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems([
            ['id' => 'figure-space', 'page' => 1, 'stream' => 1, 'text' => "\u{2007}"],
            ['id' => 'body', 'page' => 1, 'stream' => 1, 'text' => 'Body text remains.'],
        ], [$paragraph('Body text remains.')]);

        $t->same(1, $ledger['sourceOccurrenceCount']);
        $t->same(1, $ledger['resolvedOccurrenceCount']);
        $t->same(0, $ledger['unresolvedOccurrenceCount']);
        $t->same(true, $ledger['allOccurrencesResolved']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);
    },

    'pdf source disposition ledger does not let duplicate source lines claim one output occurrence' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems([
            ['id' => 'line-a', 'page' => 1, 'stream' => 1, 'text' => 'Repeated source line.'],
            ['id' => 'line-b', 'page' => 1, 'stream' => 2, 'text' => 'Repeated source line.'],
        ], [$paragraph('Repeated source line.')]);

        $t->same(2, $ledger['sourceOccurrenceCount']);
        $t->same(1, $ledger['resolvedOccurrenceCount']);
        $t->same(1, $ledger['unresolvedOccurrenceCount']);
        $t->same(['emitted' => 1, 'unresolved' => 1], $ledger['dispositionCounts']);
        $t->same('line-b', $ledger['unresolvedOccurrenceSample'][0]['id']);
        $t->same(false, $ledger['allOccurrencesResolved']);
    },

    'pdf source disposition ledger resolves evidenced running furniture without emitting it' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $source = [
            ['id' => 'body', 'page' => 1, 'stream' => 1, 'text' => 'Body text remains.'],
            ['id' => 'footer', 'page' => 1, 'stream' => 2, 'text' => 'Repeated footer'],
        ];
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            [$paragraph('Body text remains.')],
            [
                'footer' => [
                    'disposition' => 'running-furniture',
                    'reason' => 'Same edge slot and normalized text recur on three source pages.',
                    'evidence' => ['pages' => [1, 2, 3], 'edge' => 'bottom'],
                ],
            ]
        );

        $t->same(true, $ledger['allOccurrencesResolved']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);
        $t->same(0, $ledger['unresolvedOccurrenceCount']);
        $t->same(1, $ledger['dispositionCounts']['emitted']);
        $t->same(1, $ledger['dispositionCounts']['running-furniture']);
        $t->same('footer', $ledger['evidencedSuppressionSample'][0]['id']);
        $t->same([1, 2, 3], $ledger['evidencedSuppressionSample'][0]['evidence']['pages']);
    },

    'pdf source disposition ledger requires a reason for destructive dispositions' => static function (
        TestRunner $t
    ): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => PdfSourceDispositionLedger::fromSourceLineItems(
                [['id' => 'hidden', 'page' => 1, 'stream' => 1, 'text' => 'Hidden text']],
                [],
                ['hidden' => 'artifact']
            )
        );
    },

    'pdf source disposition ledger keeps character changes unresolved' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems([
            ['id' => 'formula', 'page' => 2, 'stream' => 1, 'text' => 'a² + 8 = 12'],
        ], [$paragraph('a2 + 8 = 12')]);

        $t->same(1, $ledger['unresolvedOccurrenceCount']);
        $t->same('formula', $ledger['unresolvedOccurrenceSample'][0]['id']);
        $t->same(2, $ledger['unresolvedOccurrenceSample'][0]['page']);
        $t->same(false, $ledger['orderedSignificantCharactersPreserved']);
        $t->true($ledger['sourceSignificantCharacterDigest'] !== $ledger['emittedSignificantCharacterDigest']);
    },

    'pdf source disposition ledger requires significant characters to remain in source order' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems([
            ['id' => 'line-a', 'page' => 1, 'text' => 'Alpha,'],
            ['id' => 'line-b', 'page' => 1, 'text' => 'Beta!'],
        ], [$paragraph('Beta! Alpha,')]);

        $t->same(false, $ledger['orderedSignificantCharactersPreserved']);
        $t->same(strlen('Alpha,Beta!'), $ledger['sourceSignificantCharacterBytes']);
    },

    'pdf source disposition ledger does not let one local order disposition bless an unrelated permutation' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            [
                ['id' => 'alpha', 'page' => 1, 'text' => 'alpha'],
                ['id' => 'bravo', 'page' => 1, 'text' => 'bravo'],
                ['id' => 'charlie', 'page' => 1, 'text' => 'charlie'],
            ],
            [$paragraph('alpha charlie bravo')],
            [
                'alpha' => [
                    'disposition' => 'boundary-repair',
                    'reason' => 'Only this source occurrence has local geometry evidence.',
                    'allowOrderChange' => true,
                    'evidence' => [
                        'hypothesis' => 'independent-columns',
                        'bounds' => ['x1' => 10.0, 'y1' => 10.0, 'x2' => 100.0, 'y2' => 20.0],
                        'sourceBounds' => ['x1' => 12.0, 'y1' => 12.0, 'x2' => 30.0, 'y2' => 18.0],
                    ],
                ],
            ]
        );

        $t->same(true, $ledger['allOccurrencesResolved']);
        $t->same(false, $ledger['orderedSignificantCharactersPreserved']);
        $t->same('mismatch', $ledger['orderedSignificantCharacterBasis']);
        $t->same(1, $ledger['evidencedOrderChangeOccurrenceCount']);
    },

    'pdf source disposition ledger accepts an exact geometry-evidenced reorder with semantic delimiters' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $source = [
            ['id' => 'row-one', 'page' => 1, 'text' => 'ALFA: Left one. BETA: Right one.'],
            ['id' => 'row-two', 'page' => 1, 'text' => 'BETA: Left two. ALFA: Right two.'],
        ];
        $dispositions = [
            'row-one' => [
                'disposition' => 'semantic-structure',
                'reason' => 'High-margin independent columns represent cue delimiters as structure.',
                'textProjection' => 'ALFA Left one. BETA Right one.',
                'allowOrderChange' => true,
                'evidence' => [
                    'hypothesis' => 'independent-columns',
                    'margin' => 0.31,
                    'bounds' => ['x1' => 60.0, 'y1' => 600.0, 'x2' => 540.0, 'y2' => 740.0],
                    'sourceBounds' => ['x1' => 72.0, 'y1' => 700.0, 'x2' => 480.0, 'y2' => 716.0],
                ],
            ],
            'row-two' => [
                'disposition' => 'semantic-structure',
                'reason' => 'High-margin independent columns represent cue delimiters as structure.',
                'textProjection' => 'BETA Left two. ALFA Right two.',
                'allowOrderChange' => true,
                'evidence' => [
                    'hypothesis' => 'independent-columns',
                    'margin' => 0.31,
                    'bounds' => ['x1' => 60.0, 'y1' => 600.0, 'x2' => 540.0, 'y2' => 740.0],
                    'sourceBounds' => ['x1' => 72.0, 'y1' => 680.0, 'x2' => 480.0, 'y2' => 696.0],
                ],
            ],
        ];
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems($source, [
            $paragraph('ALFA Left one. BETA Left two. BETA Right one. ALFA Right two.'),
        ], $dispositions);

        $t->same(true, $ledger['allOccurrencesResolved']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);
        $t->same('evidenced-layout-reorder', $ledger['orderedSignificantCharacterBasis']);
        $t->same(2, $ledger['evidencedOrderChangeOccurrenceCount']);
        $t->same(1, $ledger['evidencedOrderChangeScopeCount']);
        $t->same('region-bounded-inventory', $ledger['orderProofStrength']);
        $t->same(0, $ledger['unclaimedEmittedSignificantCharacterCount']);
        $t->true($ledger['sourceSignificantCharacterDigest'] !== $ledger['emittedSignificantCharacterDigest']);
    },

    'pdf source disposition ledger enforces an exact mapped occurrence order proof' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $source = [
            ['id' => 'row-one', 'page' => 1, 'text' => 'Left one. Right one.'],
            ['id' => 'row-two', 'page' => 1, 'text' => 'Left two. Right two.'],
        ];
        $proof = [
            'scopeId' => 'page-1-columns-1',
            'sourceOccurrenceIds' => ['row-one', 'row-two'],
            'emittedTextProjection' => 'Left one. Left two. Right one. Right two.',
        ];
        $dispositions = [
            'row-one' => [
                'disposition' => 'boundary-repair',
                'reason' => 'The exact source occurrence set maps to the selected column-major output.',
                'allowOrderChange' => true,
                'evidence' => [
                    'hypothesis' => 'independent-columns',
                    'bounds' => ['x1' => 60.0, 'y1' => 600.0, 'x2' => 540.0, 'y2' => 740.0],
                    'sourceBounds' => ['x1' => 72.0, 'y1' => 700.0, 'x2' => 480.0, 'y2' => 716.0],
                ],
                'orderProof' => $proof,
            ],
            'row-two' => [
                'disposition' => 'boundary-repair',
                'reason' => 'The exact source occurrence set maps to the selected column-major output.',
                'allowOrderChange' => true,
                'evidence' => [
                    'hypothesis' => 'independent-columns',
                    'bounds' => ['x1' => 60.0, 'y1' => 600.0, 'x2' => 540.0, 'y2' => 740.0],
                    'sourceBounds' => ['x1' => 72.0, 'y1' => 680.0, 'x2' => 480.0, 'y2' => 696.0],
                ],
                'orderProof' => $proof,
            ],
        ];

        $valid = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            [$paragraph('Left one. Left two. Right one. Right two.')],
            $dispositions
        );
        $invalid = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            [$paragraph('Left two. Left one. Right one. Right two.')],
            $dispositions
        );

        $t->same(true, $valid['orderedSignificantCharactersPreserved']);
        $t->same('mapped-occurrence-exact', $valid['orderProofStrength']);
        $t->same(1, $valid['evidencedOrderChangeScopeCount']);
        $t->same(false, $invalid['orderedSignificantCharactersPreserved']);
        $t->same('mapped-order-segment-mismatch', $invalid['orderProofFailureReason']);
    },

    'pdf source disposition ledger rejects an evidenced projection that changes a character' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            [['id' => 'changed', 'page' => 1, 'text' => 'ALFA: Exact source.']],
            [$paragraph('ALFA Altered source.')],
            ['changed' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The colon would be represented structurally.',
                'textProjection' => 'ALFA Exact source.',
                'allowOrderChange' => true,
            ]]
        );

        $t->same(1, $ledger['unresolvedOccurrenceCount']);
        $t->same(false, $ledger['orderedSignificantCharactersPreserved']);
        $t->same('semantic-structure', $ledger['unresolvedOccurrenceSample'][0]['evidence']['requestedDisposition']);
    },

    'pdf source disposition digest and generated ids are stable' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $source = [['page' => 3, 'stream' => 7, 'text' => 'Stable source occurrence.']];
        $first = PdfSourceDispositionLedger::fromSourceLineItems($source, [$paragraph('Stable source occurrence.')]);
        $second = PdfSourceDispositionLedger::fromSourceLineItems($source, [$paragraph('Stable source occurrence.')]);

        $t->same($first['dispositionDigest'], $second['dispositionDigest']);
        $t->same($first, $second);
    },

    'pdf source disposition binding emits exact source node edges and confines a mapped page reorder' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $source = [
            ['id' => 'page-1-row-1', 'page' => 1, 'text' => 'LEFT one. RIGHT one.'],
            ['id' => 'page-1-row-2', 'page' => 1, 'text' => 'LEFT two. RIGHT two.'],
            ['id' => 'page-2-alpha', 'page' => 2, 'text' => 'Alpha page two.'],
            ['id' => 'page-2-beta', 'page' => 2, 'text' => 'Beta page two.'],
        ];
        $proof = [
            'scopeId' => 'page-1-columns',
            'sourceOccurrenceIds' => ['page-1-row-1', 'page-1-row-2'],
            'emittedTextProjection' => 'LEFT one. LEFT two. RIGHT one. RIGHT two.',
        ];
        $dispositions = [];
        foreach (['page-1-row-1', 'page-1-row-2'] as $id) {
            $dispositions[$id] = [
                'disposition' => 'boundary-repair',
                'reason' => 'The exact page-one source rows map to the selected column-major output.',
                'allowOrderChange' => true,
                'evidence' => [
                    'hypothesis' => 'independent-columns',
                    'bounds' => ['x1' => 60.0, 'y1' => 600.0, 'x2' => 540.0, 'y2' => 740.0],
                    'sourceBounds' => ['x1' => 72.0, 'y1' => 680.0, 'x2' => 480.0, 'y2' => 716.0],
                ],
                'orderProof' => $proof,
            ];
        }

        $validBlocks = [
            $paragraph('LEFT one.'),
            $paragraph('LEFT two.'),
            $paragraph('RIGHT one.'),
            $paragraph('RIGHT two.'),
            $paragraph('Alpha page two.'),
            $paragraph('Beta page two.'),
        ];
        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            $validBlocks,
            $dispositions
        );
        $valid = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $binding['blocks'],
            $binding['explicitDispositions']
        );

        $t->same(true, $binding['complete']);
        $t->same(true, $valid['sourceEdgeMappingComplete']);
        $t->same(4, $valid['sourceEdgeCount']);
        $t->same('mapped-occurrence-exact', $valid['orderProofStrength']);
        $t->same(
            ['page-1-row-1'],
            $binding['blocks'][0]->attr('sourceLineIds')
        );
        $t->true(str_starts_with((string) $binding['blocks'][0]->attr('sourceNodeId'), 'pdf-source-node-'));
        $t->same(
            [['sourceLineId' => 'page-1-row-1', 'startByte' => 0, 'endByte' => strlen('LEFTone.')]],
            $binding['blocks'][0]->attr('sourceLineEdges')
        );
        $t->true(str_starts_with(
            (string) $binding['blocks'][0]->children[0]->attr('sourceNodeId'),
            'pdf-source-inline-'
        ));
        $t->true(!str_contains(json_encode($valid['sourceEdges']) ?: '', 'LEFT one'));

        $wrongPageTwo = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            [
                ...array_slice($validBlocks, 0, 4),
                $paragraph('Beta page two.'),
                $paragraph('Alpha page two.'),
            ],
            $dispositions
        );
        $invalid = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $wrongPageTwo['blocks'],
            $wrongPageTwo['explicitDispositions']
        );

        $t->same(false, $wrongPageTwo['complete']);
        $t->same(false, $invalid['sourceEdgeMappingComplete']);
        $t->same(false, $invalid['orderedSignificantCharactersPreserved']);
        $t->same('non-authorized-order-segment-mismatch', $invalid['orderProofFailureReason']);
    },

    'pdf source disposition binding validates exact interleaved source subranges' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $source = [
            ['id' => 'row-one', 'page' => 1, 'text' => 'Left one. Right one.'],
            ['id' => 'row-two', 'page' => 1, 'text' => 'Left two. Right two.'],
        ];
        $leftOneEnd = strlen('Leftone.');
        $leftTwoEnd = strlen('Lefttwo.');
        $rowOneEnd = strlen('Leftone.Rightone.');
        $rowTwoEnd = strlen('Lefttwo.Righttwo.');
        $proof = [
            'scopeId' => 'page-1-exact-positioned-ranges',
            'sourceOccurrenceIds' => ['row-one', 'row-two'],
            'emittedTextProjection' => 'Left one. Left two. Right one. Right two.',
            'emittedSourceRanges' => [
                ['sourceOccurrenceId' => 'row-one', 'sourceStart' => 0, 'sourceEnd' => $leftOneEnd],
                ['sourceOccurrenceId' => 'row-two', 'sourceStart' => 0, 'sourceEnd' => $leftTwoEnd],
                ['sourceOccurrenceId' => 'row-one', 'sourceStart' => $leftOneEnd, 'sourceEnd' => $rowOneEnd],
                ['sourceOccurrenceId' => 'row-two', 'sourceStart' => $leftTwoEnd, 'sourceEnd' => $rowTwoEnd],
            ],
        ];
        $dispositions = [];
        foreach (['row-one', 'row-two'] as $id) {
            $dispositions[$id] = [
                'disposition' => 'boundary-repair',
                'reason' => 'Exact positioned ranges map this source row into two visual columns.',
                'allowOrderChange' => true,
                'evidence' => [
                    'hypothesis' => 'page-local-geometry-order',
                    'bounds' => ['x1' => 60.0, 'y1' => 600.0, 'x2' => 540.0, 'y2' => 740.0],
                    'sourceBounds' => ['x1' => 72.0, 'y1' => 680.0, 'x2' => 480.0, 'y2' => 716.0],
                ],
                'orderProof' => $proof,
            ];
        }
        $blocks = [
            $paragraph('Left one.'),
            $paragraph('Left two.'),
            $paragraph('Right one.'),
            $paragraph('Right two.'),
        ];

        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            $blocks,
            $dispositions
        );
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $binding['blocks'],
            $binding['explicitDispositions']
        );

        $t->same(true, $binding['complete']);
        $t->same(true, $ledger['sourceEdgeMappingComplete']);
        $t->same('mapped-occurrence-exact', $ledger['orderProofStrength']);
        $t->same(
            [['sourceLineId' => 'row-one', 'startByte' => 0, 'endByte' => $leftOneEnd]],
            $binding['blocks'][0]->attr('sourceLineEdges')
        );
        $t->same(
            [['sourceLineId' => 'row-one', 'startByte' => $leftOneEnd, 'endByte' => $rowOneEnd]],
            $binding['blocks'][2]->attr('sourceLineEdges')
        );

        $sameInventoryWrongOrder = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            [
                $paragraph('Left two.'),
                $paragraph('Left one.'),
                $paragraph('Right one.'),
                $paragraph('Right two.'),
            ],
            $dispositions
        );
        $t->same(false, $sameInventoryWrongOrder['complete']);
        $t->same(
            'projected-source-stream-does-not-equal-final-output',
            $sameInventoryWrongOrder['failureReason']
        );

        $incompleteProof = $proof;
        array_pop($incompleteProof['emittedSourceRanges']);
        $incompleteDispositions = $dispositions;
        foreach ($incompleteDispositions as &$disposition) {
            $disposition['orderProof'] = $incompleteProof;
        }
        unset($disposition);
        $incomplete = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            $blocks,
            $incompleteDispositions
        );
        $t->same(false, $incomplete['complete']);
        $t->same('authorized-order-scope-has-ambiguous-output-mapping', $incomplete['failureReason']);
    },

    'pdf source disposition binding canonicalizes dense table provenance at exact cells' => static function (
        TestRunner $t
    ): void {
        $cell = static fn (string $text): AstNode => new AstNode(
            'table_cell',
            [],
            $text === '' ? [] : [new AstNode('plain', [], [new AstNode('text', ['text' => $text])])]
        );
        $table = new AstNode('table', [], [
            new AstNode('table_head'),
            new AstNode('table_body', [], [
                new AstNode('table_row', [], [
                    $cell('Alpha '),
                    $cell('Beta'),
                    $cell(''),
                    $cell('Gamma'),
                ]),
            ]),
            new AstNode('table_foot'),
        ]);
        $source = [
            ['id' => 'split', 'page' => 1, 'stream' => 1, 'text' => 'Alpha Beta'],
            ['id' => 'tail', 'page' => 1, 'stream' => 2, 'text' => 'Gamma'],
        ];

        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput($source, [$table]);
        $boundTable = $binding['blocks'][0];
        $boundCells = $boundTable->children()[1]->children()[0]->children();
        $splitEnd = strlen('AlphaBeta');

        $t->same(true, $binding['complete']);
        $t->same(null, $binding['failureReason']);
        $t->same([
            ['sourceLineId' => 'split', 'startByte' => 0, 'endByte' => $splitEnd],
            ['sourceLineId' => 'tail', 'startByte' => 0, 'endByte' => strlen('Gamma')],
        ], $boundTable->attr('sourceLineEdges'));
        $t->same(
            [['sourceLineId' => 'split', 'startByte' => 0, 'endByte' => strlen('Alpha')]],
            $boundCells[0]->attr('sourceLineEdges')
        );
        $t->same(
            [['sourceLineId' => 'split', 'startByte' => strlen('Alpha'), 'endByte' => $splitEnd]],
            $boundCells[1]->attr('sourceLineEdges')
        );
        $t->same([], $boundCells[2]->attr('sourceLineEdges', []));
        $t->same(
            [['sourceLineId' => 'tail', 'startByte' => 0, 'endByte' => strlen('Gamma')]],
            $boundCells[3]->attr('sourceLineEdges')
        );

        $decoratedTypes = [];
        $visit = static function (AstNode $node) use (&$visit, &$decoratedTypes): void {
            if (is_string($node->attr('sourceNodeId'))) {
                $decoratedTypes[] = $node->type;
            }
            foreach ($node->children() as $child) {
                $visit($child);
            }
        };
        $visit($boundTable);
        $t->same(['table', 'table_cell', 'table_cell', 'table_cell'], $decoratedTypes);

        $tableId = $boundTable->attr('sourceNodeId');
        $cellIds = [
            $boundCells[0]->attr('sourceNodeId'),
            $boundCells[1]->attr('sourceNodeId'),
            $boundCells[3]->attr('sourceNodeId'),
        ];
        foreach ([$tableId, ...$cellIds] as $index => $nodeId) {
            $prefix = $index === 0 ? 'pdf-source-node-' : 'pdf-source-inline-';
            $t->true(is_string($nodeId) && str_starts_with($nodeId, $prefix));
        }
        $t->same([$tableId], $binding['explicitDispositions']['split']['sourceMapping']['destinationNodeIds']);
        $t->same(
            [$cellIds[0], $cellIds[1]],
            $binding['explicitDispositions']['split']['sourceMapping']['destinationInlineIds']
        );
        $t->same([$tableId], $binding['explicitDispositions']['tail']['sourceMapping']['destinationNodeIds']);
        $t->same(
            [$cellIds[2]],
            $binding['explicitDispositions']['tail']['sourceMapping']['destinationInlineIds']
        );

        $repeat = PdfSourceDispositionLedger::bindSourceLineItemsToOutput($source, [$table]);
        $repeatTable = $repeat['blocks'][0];
        $repeatCells = $repeatTable->children()[1]->children()[0]->children();
        $t->same($tableId, $repeatTable->attr('sourceNodeId'));
        $t->same($cellIds, [
            $repeatCells[0]->attr('sourceNodeId'),
            $repeatCells[1]->attr('sourceNodeId'),
            $repeatCells[3]->attr('sourceNodeId'),
        ]);
        $t->same(
            $binding['explicitDispositions']['split']['sourceMapping'],
            $repeat['explicitDispositions']['split']['sourceMapping']
        );
        $t->same(
            $binding['explicitDispositions']['tail']['sourceMapping'],
            $repeat['explicitDispositions']['tail']['sourceMapping']
        );

        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $binding['blocks'],
            $binding['explicitDispositions']
        );
        $t->same(true, $ledger['allOccurrencesResolved']);
        $t->same(true, $ledger['sourceEdgeMappingComplete']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);
        $t->same(0, $ledger['unclaimedEmittedSignificantCharacterCount']);
    },

    'pdf source disposition binding rejects Unicode code points spliced across occurrences' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $source = [
            ['id' => 'unicode-a', 'page' => 1, 'text' => 'é'],
            ['id' => 'unicode-b', 'page' => 1, 'text' => 'é'],
        ];
        $proof = [
            'scopeId' => 'unicode-byte-splice',
            'sourceOccurrenceIds' => ['unicode-a', 'unicode-b'],
            'emittedTextProjection' => 'éé',
            'emittedSourceRanges' => [
                ['sourceOccurrenceId' => 'unicode-a', 'sourceStart' => 0, 'sourceEnd' => 1],
                ['sourceOccurrenceId' => 'unicode-b', 'sourceStart' => 1, 'sourceEnd' => 2],
                ['sourceOccurrenceId' => 'unicode-b', 'sourceStart' => 0, 'sourceEnd' => 1],
                ['sourceOccurrenceId' => 'unicode-a', 'sourceStart' => 1, 'sourceEnd' => 2],
            ],
        ];
        $dispositions = [];
        foreach (['unicode-a', 'unicode-b'] as $id) {
            $dispositions[$id] = [
                'disposition' => 'boundary-repair',
                'reason' => 'Exact positioned ranges map this Unicode source occurrence.',
                'allowOrderChange' => true,
                'evidence' => [
                    'hypothesis' => 'page-local-geometry-order',
                    'bounds' => ['x1' => 60.0, 'y1' => 600.0, 'x2' => 540.0, 'y2' => 740.0],
                    'sourceBounds' => ['x1' => 72.0, 'y1' => 680.0, 'x2' => 480.0, 'y2' => 716.0],
                ],
                'orderProof' => $proof,
            ];
        }

        $spliced = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            [$paragraph('éé')],
            $dispositions
        );
        $t->same(false, $spliced['complete']);
        $t->same('authorized-order-scope-has-ambiguous-output-mapping', $spliced['failureReason']);

        $wholeCodePoints = $proof;
        $wholeCodePoints['scopeId'] = 'unicode-code-point-ranges';
        $wholeCodePoints['emittedSourceRanges'] = [
            ['sourceOccurrenceId' => 'unicode-a', 'sourceStart' => 0, 'sourceEnd' => 2],
            ['sourceOccurrenceId' => 'unicode-b', 'sourceStart' => 0, 'sourceEnd' => 2],
        ];
        foreach ($dispositions as &$disposition) {
            $disposition['orderProof'] = $wholeCodePoints;
        }
        unset($disposition);
        $valid = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            [$paragraph('éé')],
            $dispositions
        );
        $t->same(true, $valid['complete']);
        $t->same(null, $valid['failureReason']);
    },

    'pdf source disposition binding maps one exact scope around an unchanged page marker' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $source = [
            ['id' => 'intro', 'page' => 1, 'text' => 'Full width introduction.'],
            ['id' => 'page-marker', 'page' => 1, 'text' => '143'],
            ['id' => 'row-one', 'page' => 1, 'text' => 'LEFT one. RIGHT one.'],
            ['id' => 'row-two', 'page' => 1, 'text' => 'LEFT two. RIGHT two.'],
        ];
        $proof = [
            'scopeId' => 'page-1-columns-around-marker',
            'sourceOccurrenceIds' => ['row-one', 'row-two'],
            'emittedTextProjection' => 'LEFT one. LEFT two. RIGHT one. RIGHT two.',
        ];
        $dispositions = [];
        foreach (['row-one', 'row-two'] as $id) {
            $dispositions[$id] = [
                'disposition' => 'boundary-repair',
                'reason' => 'The exact row maps to the selected independent-column output.',
                'allowOrderChange' => true,
                'evidence' => [
                    'hypothesis' => 'independent-columns',
                    'bounds' => ['x1' => 60.0, 'y1' => 600.0, 'x2' => 540.0, 'y2' => 740.0],
                    'sourceBounds' => ['x1' => 72.0, 'y1' => 680.0, 'x2' => 480.0, 'y2' => 716.0],
                ],
                'orderProof' => $proof,
            ];
        }
        $blocks = [
            $paragraph('Full width introduction.'),
            $paragraph('LEFT one.'),
            $paragraph('LEFT two.'),
            $paragraph('143'),
            $paragraph('RIGHT one.'),
            $paragraph('RIGHT two.'),
        ];

        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            $blocks,
            $dispositions
        );
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $binding['blocks'],
            $binding['explicitDispositions']
        );

        $t->same(true, $binding['complete']);
        $t->same(true, $ledger['sourceEdgeMappingComplete']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);
        $t->same('mapped-occurrence-exact', $ledger['orderProofStrength']);
        $t->same(['page-marker'], $binding['blocks'][3]->attr('sourceLineIds'));
        $t->same(
            [['sourceLineId' => 'page-marker', 'startByte' => 0, 'endByte' => 3]],
            $binding['blocks'][3]->attr('sourceLineEdges')
        );
    },

    'pdf source disposition binding permits a textless visual block between exact text destinations' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $source = [
            ['id' => 'before-visual', 'page' => 1, 'text' => 'Before visual.'],
            ['id' => 'after-visual', 'page' => 1, 'text' => 'After visual.'],
        ];
        $visual = new AstNode('image', [
            'src' => 'media/pdf/image-17.png',
            'pdfVisualId' => 'pdf-visual-page-1-paint-2',
        ]);
        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            [$paragraph('Before visual.'), $visual, $paragraph('After visual.')]
        );
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $binding['blocks'],
            $binding['explicitDispositions']
        );

        $t->same(true, $binding['complete']);
        $t->same(true, $ledger['sourceEdgeMappingComplete']);
        $t->same(['before-visual'], $binding['blocks'][0]->attr('sourceLineIds'));
        $t->same(null, $binding['blocks'][1]->attr('sourceNodeId'));
        $t->same('pdf-visual-page-1-paint-2', $binding['blocks'][1]->attr('pdfVisualId'));
        $t->same(['after-visual'], $binding['blocks'][2]->attr('sourceLineIds'));
    },

    'pdf source disposition binding maps an exact standalone list marker without a byte edge' => static function (
        TestRunner $t
    ) use ($orderedList, $semanticMarkerProof): void {
        $source = [
            ['id' => 'marker', 'page' => 2, 'stream' => 7, 'text' => '1.'],
            ['id' => 'anchor', 'page' => 2, 'stream' => 7, 'text' => 'informa-'],
            ['id' => 'continuation', 'page' => 2, 'stream' => 7, 'text' => 'tion needed.'],
        ];
        $dispositions = [
            'marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The standalone marker is represented by one exact ordered-list item.',
                'textProjection' => '',
                'semanticStructureProof' => $semanticMarkerProof(
                    '1.',
                    'anchor',
                    'informationneeded.'
                ),
            ],
            'anchor' => [
                'disposition' => 'boundary-repair',
                'reason' => 'The terminal source hyphen is a split-word boundary.',
                'textProjection' => 'informa',
                'evidence' => [
                    'wrappedHyphenBoundaryRepair' => [
                        'method' => 'exact-directional-source-wrapped-hyphen-boundary',
                        'page' => 2,
                        'stream' => 7,
                        'sourceOccurrenceIds' => [
                            'preceding' => 'anchor',
                            'following' => 'continuation',
                        ],
                        'originalDigest' => hash('sha256', 'informa-'),
                        'projectedDigest' => hash('sha256', 'informa'),
                        'followingOriginalDigest' => hash('sha256', 'tion needed.'),
                        'suppressionKind' => 'discretionary-hard-hyphen',
                    ],
                ],
            ],
        ];

        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            [$orderedList('information needed.')],
            $dispositions
        );
        $t->same(true, $binding['complete']);
        $t->same(null, $binding['failureReason']);

        $list = $binding['blocks'][0];
        $item = $list->children()[0];
        $markerMapping = $binding['explicitDispositions']['marker']['sourceMapping'];
        $t->same('output', $markerMapping['status']);
        $t->same('exact-semantic-list-marker', $markerMapping['mappingMode']);
        $t->same([$list->attr('sourceNodeId')], $markerMapping['destinationNodeIds']);
        $t->same([$item->attr('sourceNodeId')], $markerMapping['destinationInlineIds']);
        $t->same(null, $markerMapping['scopeId']);

        $edgeIds = [];
        $visit = static function (AstNode $node) use (&$visit, &$edgeIds): void {
            foreach ($node->attr('sourceLineEdges', []) as $edge) {
                if (is_array($edge) && is_string($edge['sourceLineId'] ?? null)) {
                    $edgeIds[$edge['sourceLineId']] = true;
                }
            }
            foreach ($node->children() as $child) {
                $visit($child);
            }
        };
        $visit($list);
        $t->same(false, isset($edgeIds['marker']));
        $t->same(true, isset($edgeIds['anchor']));
        $t->same(true, isset($edgeIds['continuation']));

        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $binding['blocks'],
            $binding['explicitDispositions']
        );
        $t->same(true, $ledger['allOccurrencesResolved']);
        $t->same(true, $ledger['sourceEdgeMappingComplete']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);
        $t->same(0, $ledger['unclaimedEmittedTokenCount']);
        $t->same(0, $ledger['unclaimedEmittedSignificantCharacterCount']);
        $t->same(1, $ledger['dispositionCounts']['semantic-structure']);
        $markerEdges = array_values(array_filter(
            $ledger['sourceEdges'],
            static fn (array $edge): bool => $edge['sourceOccurrenceId'] === 'marker'
        ));
        $t->same(1, count($markerEdges));
        $t->same('output', $markerEdges[0]['target']);
        $t->same('exact-semantic-list-marker', $markerEdges[0]['mappingMode']);
    },

    'pdf source disposition binding rejects unproved or mismatched structural list markers' => static function (
        TestRunner $t
    ) use ($paragraph, $orderedList, $semanticMarkerProof): void {
        $source = [
            ['id' => 'marker', 'page' => 1, 'stream' => 3, 'text' => '1.'],
            ['id' => 'anchor', 'page' => 1, 'stream' => 3, 'text' => 'Actual item.'],
        ];
        $blocks = [$orderedList('Actual item.')];
        $dispositions = [
            'marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The marker claims one ordered-list structure.',
                'textProjection' => '',
                'semanticStructureProof' => $semanticMarkerProof('1.', 'anchor', 'Actualitem.'),
            ],
        ];

        $missingProof = $dispositions;
        unset($missingProof['marker']['semanticStructureProof']);
        $whitespaceProjection = $dispositions;
        $whitespaceProjection['marker']['textProjection'] = " \n\t";
        $wrongMarkerDigest = $dispositions;
        $wrongMarkerDigest['marker']['semanticStructureProof']['markerDigest'] = str_repeat('0', 64);
        $wrongListType = $dispositions;
        $wrongListType['marker']['semanticStructureProof']['listType'] = 'bullet';
        $wrongListType['marker']['semanticStructureProof']['markerOrdinal'] = null;
        $wrongOrdinal = $dispositions;
        $wrongOrdinal['marker']['semanticStructureProof']['markerOrdinal'] = 2;
        $wrongStreamSource = $source;
        $wrongStreamSource[1]['stream'] = 4;
        $wrongItemDigest = $dispositions;
        $wrongItemDigest['marker']['semanticStructureProof']['itemProjectionDigest'] = str_repeat('0', 64);
        $nestedList = new AstNode('blockquote', [], $blocks);
        $oneCharacterSource = [
            ['id' => 'marker', 'page' => 1, 'stream' => 3, 'text' => '1.'],
            ['id' => 'anchor', 'page' => 1, 'stream' => 3, 'text' => 'A'],
            ['id' => 'continuation', 'page' => 1, 'stream' => 3, 'text' => 'nything at all.'],
        ];
        $oneCharacterDispositions = [
            'marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The marker claims one ordered-list structure.',
                'textProjection' => '',
                'semanticStructureProof' => $semanticMarkerProof(
                    '1.',
                    'anchor',
                    'Anythingatall.'
                ),
            ],
        ];
        $duplicateContinuationSource = [
            ['id' => 'marker', 'page' => 1, 'stream' => 6, 'text' => '1.'],
            ['id' => 'anchor', 'page' => 1, 'stream' => 6, 'text' => 'informa-'],
            ['id' => 'following', 'page' => 1, 'stream' => 6, 'text' => 'tion'],
            ['id' => 'duplicate', 'page' => 1, 'stream' => 6, 'text' => 'tion'],
        ];
        $duplicateOrderProof = [
            'scopeId' => 'duplicate-continuation-swap',
            'sourceOccurrenceIds' => ['anchor', 'following', 'duplicate'],
            'emittedTextProjection' => 'information tion',
            'emittedSourceOccurrenceIds' => ['anchor', 'duplicate', 'following'],
        ];
        $duplicateScopeDisposition = static fn (): array => [
            'disposition' => 'boundary-repair',
            'reason' => 'The explicit order proof identifies each duplicate continuation occurrence.',
            'allowOrderChange' => true,
            'evidence' => [
                'hypothesis' => 'page-local-geometry-order',
                'bounds' => ['x1' => 50.0, 'y1' => 500.0, 'x2' => 550.0, 'y2' => 750.0],
                'sourceBounds' => ['x1' => 70.0, 'y1' => 600.0, 'x2' => 500.0, 'y2' => 700.0],
            ],
        ];
        $duplicateContinuationDispositions = [
            'marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The marker claims one ordered-list structure.',
                'textProjection' => '',
                'semanticStructureProof' => $semanticMarkerProof('1.', 'anchor', 'information'),
            ],
            'anchor' => $duplicateScopeDisposition() + [
                'textProjection' => 'informa',
                'orderProof' => $duplicateOrderProof,
            ],
            'following' => $duplicateScopeDisposition() + ['orderProof' => $duplicateOrderProof],
            'duplicate' => $duplicateScopeDisposition() + ['orderProof' => $duplicateOrderProof],
        ];
        $duplicateContinuationDispositions['anchor']['evidence']['wrappedHyphenBoundaryRepair'] = [
            'method' => 'exact-directional-source-wrapped-hyphen-boundary',
            'page' => 1,
            'stream' => 6,
            'sourceOccurrenceIds' => ['preceding' => 'anchor', 'following' => 'following'],
            'originalDigest' => hash('sha256', 'informa-'),
            'projectedDigest' => hash('sha256', 'informa'),
            'followingOriginalDigest' => hash('sha256', 'tion'),
            'suppressionKind' => 'discretionary-hard-hyphen',
        ];
        $swappedTextDispositions = $duplicateContinuationDispositions;
        unset($swappedTextDispositions['marker']);
        $swappedTextBinding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            array_slice($duplicateContinuationSource, 1),
            [$orderedList('information'), $paragraph('tion')],
            $swappedTextDispositions
        );
        $t->same(true, $swappedTextBinding['complete']);
        $t->same(
            ['anchor', 'duplicate'],
            $swappedTextBinding['blocks'][0]->children()[0]->attr('sourceLineIds')
        );

        $cases = [
            'missing proof' => [
                $source,
                $blocks,
                $missingProof,
                'empty-output-projection-has-no-exact-structural-target',
            ],
            'whitespace marker projection' => [
                $source,
                $blocks,
                $whitespaceProjection,
                'empty-output-projection-has-no-exact-structural-target',
            ],
            'wrong marker digest' => [
                $source,
                $blocks,
                $wrongMarkerDigest,
                'semantic-list-marker-proof-does-not-match-source-marker',
            ],
            'wrong list type' => [
                $source,
                $blocks,
                $wrongListType,
                'semantic-list-marker-proof-does-not-match-source-marker',
            ],
            'wrong ordinal' => [
                $source,
                $blocks,
                $wrongOrdinal,
                'semantic-list-marker-proof-does-not-match-source-marker',
            ],
            'different anchor stream' => [
                $wrongStreamSource,
                $blocks,
                $dispositions,
                'semantic-list-marker-anchor-is-not-the-next-same-stream-occurrence',
            ],
            'wrong item digest' => [
                $source,
                $blocks,
                $wrongItemDigest,
                'semantic-list-marker-has-no-unique-structural-target',
            ],
            'paragraph instead of list' => [
                $source,
                [$paragraph('Actual item.')],
                $dispositions,
                'semantic-list-marker-has-no-unique-structural-target',
            ],
            'nested list instead of top-level list' => [
                $source,
                [$nestedList],
                $dispositions,
                'semantic-list-marker-has-no-unique-structural-target',
            ],
            'unproved one-character item prefix' => [
                $oneCharacterSource,
                [$orderedList('Anything at all.')],
                $oneCharacterDispositions,
                'semantic-list-marker-has-no-unique-structural-target',
            ],
            'duplicate continuation occurrence swapped by an order scope' => [
                $duplicateContinuationSource,
                [$orderedList('information'), $paragraph('tion')],
                $duplicateContinuationDispositions,
                'semantic-list-marker-has-no-unique-structural-target',
            ],
        ];

        foreach ($cases as $name => [$caseSource, $caseBlocks, $caseDispositions, $failureReason]) {
            $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
                $caseSource,
                $caseBlocks,
                $caseDispositions
            );
            $t->same(false, $binding['complete'], $name);
            $t->same($failureReason, $binding['failureReason'], $name);
            $t->same(
                'unresolved',
                $binding['explicitDispositions']['marker']['sourceMapping']['status'],
                $name
            );
        }
    },

    'pdf source disposition binding maps only exact two-occurrence ordinary list wraps' => static function (
        TestRunner $t
    ) use ($paragraph, $orderedList, $semanticMarkerProof): void {
        $source = [
            ['id' => 'ordinary-marker', 'page' => 3, 'stream' => 11, 'text' => '2.'],
            ['id' => 'ordinary-anchor', 'page' => 3, 'stream' => 11, 'text' => 'Complete Step 2 if you'],
            ['id' => 'ordinary-following', 'page' => 3, 'stream' => 11, 'text' => 'hold more than one job.'],
        ];
        $dispositions = [
            'ordinary-marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The standalone marker is represented by one exact wrapped list item.',
                'textProjection' => '',
                'semanticStructureProof' => $semanticMarkerProof(
                    '2.',
                    'ordinary-anchor',
                    'CompleteStep2ifyouholdmorethanonejob.',
                    2
                ),
            ],
        ];
        $blocks = [$orderedList('Complete Step 2 if you hold more than one job.', 2)];
        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            $blocks,
            $dispositions
        );
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $binding['blocks'],
            $binding['explicitDispositions']
        );

        $t->same(true, $binding['complete']);
        $t->same(null, $binding['failureReason']);
        $t->same(
            'exact-semantic-list-marker',
            $binding['explicitDispositions']['ordinary-marker']['sourceMapping']['mappingMode']
        );
        $t->same(
            ['ordinary-anchor', 'ordinary-following'],
            $binding['blocks'][0]->children()[0]->attr('sourceLineIds')
        );
        $t->same(true, $ledger['allOccurrencesResolved']);
        $t->same(true, $ledger['sourceEdgeMappingComplete']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);

        foreach ([
            'missing boundary space' => 'Complete Step 2 if youhold more than one job.',
            'duplicate boundary space' => 'Complete Step 2 if you  hold more than one job.',
        ] as $name => $text) {
            $invalidSpace = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
                $source,
                [$orderedList($text, 2)],
                $dispositions
            );
            $t->same(false, $invalidSpace['complete'], $name);
            $t->same(
                'semantic-list-marker-has-no-unique-structural-target',
                $invalidSpace['failureReason'],
                $name
            );
        }

        $scopeDisposition = static fn (array $proof): array => [
            'disposition' => 'boundary-repair',
            'reason' => 'The exact occurrence order is declared for this adversarial scope.',
            'allowOrderChange' => true,
            'evidence' => [
                'hypothesis' => 'page-local-geometry-order',
                'bounds' => ['x1' => 50.0, 'y1' => 500.0, 'x2' => 550.0, 'y2' => 750.0],
                'sourceBounds' => ['x1' => 70.0, 'y1' => 600.0, 'x2' => 500.0, 'y2' => 700.0],
            ],
            'orderProof' => $proof,
        ];

        $noFollowingSource = [
            ['id' => 'tail-before-marker', 'page' => 3, 'stream' => 12, 'text' => 'Tail text.'],
            ['id' => 'no-following-marker', 'page' => 3, 'stream' => 12, 'text' => '2.'],
            ['id' => 'last-anchor', 'page' => 3, 'stream' => 12, 'text' => 'Lead'],
        ];
        $noFollowingProof = [
            'scopeId' => 'anchor-without-following-occurrence',
            'sourceOccurrenceIds' => ['tail-before-marker', 'last-anchor'],
            'emittedTextProjection' => 'Lead Tail text.',
            'emittedSourceOccurrenceIds' => ['last-anchor', 'tail-before-marker'],
        ];
        $noFollowingDispositions = [
            'tail-before-marker' => $scopeDisposition($noFollowingProof),
            'no-following-marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The marker claims a wrapped item without a following source occurrence.',
                'textProjection' => '',
                'semanticStructureProof' => $semanticMarkerProof(
                    '2.',
                    'last-anchor',
                    'LeadTailtext.',
                    2
                ),
            ],
            'last-anchor' => $scopeDisposition($noFollowingProof),
        ];
        $noFollowing = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $noFollowingSource,
            [$orderedList('Lead Tail text.', 2)],
            $noFollowingDispositions
        );
        $t->same(false, $noFollowing['complete']);
        $t->same(
            'semantic-list-marker-has-no-unique-structural-target',
            $noFollowing['failureReason']
        );

        $wrongOccurrenceSource = [
            ['id' => 'wrong-occurrence-marker', 'page' => 3, 'stream' => 13, 'text' => '2.'],
            ['id' => 'swap-anchor', 'page' => 3, 'stream' => 13, 'text' => 'Lead'],
            ['id' => 'expected-following', 'page' => 3, 'stream' => 13, 'text' => 'continuation.'],
            ['id' => 'duplicate-following', 'page' => 3, 'stream' => 13, 'text' => 'continuation.'],
        ];
        $wrongOccurrenceProof = [
            'scopeId' => 'ordinary-wrap-occurrence-swap',
            'sourceOccurrenceIds' => ['swap-anchor', 'expected-following', 'duplicate-following'],
            'emittedTextProjection' => 'Lead continuation. continuation.',
            'emittedSourceOccurrenceIds' => [
                'swap-anchor',
                'duplicate-following',
                'expected-following',
            ],
        ];
        $wrongOccurrenceDispositions = [
            'wrong-occurrence-marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The marker claims the immediate continuation occurrence.',
                'textProjection' => '',
                'semanticStructureProof' => $semanticMarkerProof(
                    '2.',
                    'swap-anchor',
                    'Leadcontinuation.',
                    2
                ),
            ],
            'swap-anchor' => $scopeDisposition($wrongOccurrenceProof),
            'expected-following' => $scopeDisposition($wrongOccurrenceProof),
            'duplicate-following' => $scopeDisposition($wrongOccurrenceProof),
        ];
        $wrongOccurrenceTextDispositions = $wrongOccurrenceDispositions;
        unset($wrongOccurrenceTextDispositions['wrong-occurrence-marker']);
        $wrongOccurrenceTextBinding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            array_slice($wrongOccurrenceSource, 1),
            [$orderedList('Lead continuation.', 2), $paragraph('continuation.')],
            $wrongOccurrenceTextDispositions
        );
        $t->same(true, $wrongOccurrenceTextBinding['complete']);
        $t->same(
            ['swap-anchor', 'duplicate-following'],
            $wrongOccurrenceTextBinding['blocks'][0]->children()[0]->attr('sourceLineIds')
        );
        $wrongOccurrence = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $wrongOccurrenceSource,
            [$orderedList('Lead continuation.', 2), $paragraph('continuation.')],
            $wrongOccurrenceDispositions
        );
        $t->same(false, $wrongOccurrence['complete']);
        $t->same(
            'semantic-list-marker-has-no-unique-structural-target',
            $wrongOccurrence['failureReason']
        );
    },

    'pdf source disposition binding validates every occurrence in an extended list anchor' => static function (
        TestRunner $t
    ) use ($orderedList): void {
        $source = [
            ['id' => 'marker', 'page' => 5, 'stream' => 9, 'text' => '1.'],
            ['id' => 'anchor', 'page' => 5, 'stream' => 9, 'text' => 'Underlying'],
            ['id' => 'second', 'page' => 5, 'stream' => 9, 'text' => 'considerations:'],
            ['id' => 'third', 'page' => 5, 'stream' => 9, 'text' => 'employee records.'],
        ];
        $projection = 'Underlyingconsiderations:employeerecords.';
        $dispositions = [
            'marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'Three exact source occurrences identify one structural item.',
                'textProjection' => '',
                'semanticStructureProof' => [
                    'version' => 2,
                    'method' => 'exact-standalone-list-marker-to-item',
                    'listType' => 'ordered',
                    'markerOrdinal' => 1,
                    'markerDigest' => hash('sha256', '1.'),
                    'anchorSourceOccurrenceId' => 'anchor',
                    'anchorSourceOccurrenceIds' => ['anchor', 'second', 'third'],
                    'anchorProjectionDigest' => hash('sha256', $projection),
                    'itemProjectionDigest' => hash('sha256', $projection),
                ],
            ],
        ];
        $blocks = [$orderedList('Underlying considerations: employee records.')];
        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            $blocks,
            $dispositions
        );
        $t->same(true, $binding['complete']);
        $t->same(null, $binding['failureReason']);
        $t->same(
            ['anchor', 'second', 'third'],
            $binding['blocks'][0]->children()[0]->attr('sourceLineIds')
        );

        $wrongDigest = $dispositions;
        $wrongDigest['marker']['semanticStructureProof']['anchorProjectionDigest'] = str_repeat('0', 64);
        $wrongDigestBinding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            $blocks,
            $wrongDigest
        );
        $t->same(false, $wrongDigestBinding['complete']);
        $t->same(
            'semantic-list-marker-extended-anchor-proof-does-not-match-source',
            $wrongDigestBinding['failureReason']
        );

        $wrongOrder = $dispositions;
        $wrongOrder['marker']['semanticStructureProof']['anchorSourceOccurrenceIds'] = [
            'anchor',
            'third',
            'second',
        ];
        $wrongOrderBinding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            $blocks,
            $wrongOrder
        );
        $t->same(false, $wrongOrderBinding['complete']);
        $t->same(
            'semantic-list-marker-extended-anchor-is-not-consecutive',
            $wrongOrderBinding['failureReason']
        );

        $wrongStream = $source;
        $wrongStream[3]['stream'] = 10;
        $wrongStreamBinding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $wrongStream,
            $blocks,
            $dispositions
        );
        $t->same(false, $wrongStreamBinding['complete']);
        $t->same(
            'semantic-list-marker-extended-anchor-proof-does-not-match-source',
            $wrongStreamBinding['failureReason']
        );
    },

    'pdf source disposition binding keeps several ordinary markers neutral in one page order scope' => static function (
        TestRunner $t
    ) use ($paragraph, $semanticMarkerProof): void {
        $source = [
            ['id' => 'marker-three', 'page' => 3, 'stream' => 21, 'text' => '3.'],
            ['id' => 'three-anchor', 'page' => 3, 'stream' => 21, 'text' => 'Third item'],
            ['id' => 'three-following', 'page' => 3, 'stream' => 21, 'text' => 'continues.'],
            ['id' => 'marker-two', 'page' => 3, 'stream' => 21, 'text' => '2.'],
            ['id' => 'two-anchor', 'page' => 3, 'stream' => 21, 'text' => 'Second item'],
            ['id' => 'two-following', 'page' => 3, 'stream' => 21, 'text' => 'continues.'],
        ];
        $proof = [
            'scopeId' => 'page-3-list-order',
            'sourceOccurrenceIds' => [
                'three-anchor',
                'three-following',
                'two-anchor',
                'two-following',
            ],
            'emittedTextProjection' => 'Second item continues. Third item continues.',
            'emittedSourceOccurrenceIds' => [
                'two-anchor',
                'two-following',
                'three-anchor',
                'three-following',
            ],
        ];
        $scopeDisposition = static fn (): array => [
            'disposition' => 'boundary-repair',
            'reason' => 'The exact page-local list scope follows reconstructed geometry order.',
            'allowOrderChange' => true,
            'evidence' => [
                'hypothesis' => 'page-local-geometry-order',
                'bounds' => ['x1' => 50.0, 'y1' => 500.0, 'x2' => 550.0, 'y2' => 750.0],
                'sourceBounds' => ['x1' => 70.0, 'y1' => 600.0, 'x2' => 500.0, 'y2' => 700.0],
            ],
        ];
        $dispositions = [
            'marker-three' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The marker maps to the third final list item.',
                'textProjection' => '',
                'semanticStructureProof' => $semanticMarkerProof(
                    '3.',
                    'three-anchor',
                    'Thirditemcontinues.',
                    3
                ),
            ],
            'marker-two' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The marker maps to the second final list item.',
                'textProjection' => '',
                'semanticStructureProof' => $semanticMarkerProof(
                    '2.',
                    'two-anchor',
                    'Seconditemcontinues.',
                    2
                ),
            ],
        ];
        foreach ([
            'three-anchor',
            'three-following',
            'two-anchor',
            'two-following',
        ] as $id) {
            $dispositions[$id] = $scopeDisposition() + ['orderProof' => $proof];
        }
        $list = new AstNode('ordered_list', ['start' => 2], [
            new AstNode('list_item', [], [$paragraph('Second item continues.')]),
            new AstNode('list_item', [], [$paragraph('Third item continues.')]),
        ]);

        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            [$list],
            $dispositions
        );
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $binding['blocks'],
            $binding['explicitDispositions']
        );

        $t->same(true, $binding['complete']);
        $t->same(null, $binding['failureReason']);
        $t->same(
            'exact-semantic-list-marker',
            $binding['explicitDispositions']['marker-two']['sourceMapping']['mappingMode']
        );
        $t->same(
            'exact-semantic-list-marker',
            $binding['explicitDispositions']['marker-three']['sourceMapping']['mappingMode']
        );
        $t->same(true, $ledger['sourceEdgeMappingComplete']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);
        $t->same('mapped-occurrence-exact', $ledger['orderProofStrength']);
        $t->same(4, $ledger['evidencedOrderChangeOccurrenceCount']);
        $t->same(1, $ledger['evidencedOrderChangeScopeCount']);
    },

    'pdf source disposition ledger rejects forged marker mappings and recursive marker byte edges' => static function (
        TestRunner $t
    ) use ($orderedList, $semanticMarkerProof): void {
        $source = [
            ['id' => 'marker', 'page' => 1, 'stream' => 5, 'text' => '1.'],
            ['id' => 'anchor', 'page' => 1, 'stream' => 5, 'text' => 'Actual item.'],
        ];
        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            [$orderedList('Actual item.')],
            [
                'marker' => [
                    'disposition' => 'semantic-structure',
                    'reason' => 'The marker claims one ordered-list structure.',
                    'textProjection' => '',
                    'semanticStructureProof' => $semanticMarkerProof('1.', 'anchor', 'Actualitem.'),
                ],
            ]
        );
        $t->same(true, $binding['complete']);

        $forgedDispositions = $binding['explicitDispositions'];
        $forgedDispositions['marker']['sourceMapping']['destinationInlineIds'] = ['forged-item'];
        $forgedLedger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $binding['blocks'],
            $forgedDispositions
        );
        $t->same(1, $forgedLedger['unresolvedOccurrenceCount']);
        $t->same(false, $forgedLedger['sourceEdgeMappingComplete']);
        $t->same('marker', $forgedLedger['unresolvedOccurrenceSample'][0]['id']);

        $list = $binding['blocks'][0];
        $item = $list->children()[0];
        $itemAttrs = $item->baseAttrs();
        $itemAttrs['sourceLineIds'][] = 'marker';
        $itemAttrs['sourceLineEdges'][] = [
            'sourceLineId' => 'marker',
            'startByte' => 0,
            'endByte' => 0,
        ];
        $tamperedItem = new AstNode('list_item', $itemAttrs, $item->children());
        $tamperedList = new AstNode('ordered_list', $list->baseAttrs(), [$tamperedItem]);
        $edgeLedger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            [$tamperedList],
            $binding['explicitDispositions']
        );
        $t->same(1, $edgeLedger['unresolvedOccurrenceCount']);
        $t->same(false, $edgeLedger['sourceEdgeMappingComplete']);
        $t->same('marker', $edgeLedger['unresolvedOccurrenceSample'][0]['id']);
    },

    'pdf source disposition marker stays neutral inside one mapped order scope' => static function (
        TestRunner $t
    ) use ($paragraph, $orderedList, $semanticMarkerProof): void {
        $source = [
            ['id' => 'row', 'page' => 1, 'stream' => 9, 'text' => 'Row text.'],
            ['id' => 'marker', 'page' => 1, 'stream' => 9, 'text' => '1.'],
            ['id' => 'anchor', 'page' => 1, 'stream' => 9, 'text' => 'Anchor item.'],
        ];
        $orderProof = [
            'scopeId' => 'list-item-around-source-marker',
            'sourceOccurrenceIds' => ['row', 'anchor'],
            'emittedTextProjection' => 'Anchor item. Row text.',
            'emittedSourceOccurrenceIds' => ['anchor', 'row'],
        ];
        $scopeDisposition = static fn (array $proof): array => [
            'disposition' => 'boundary-repair',
            'reason' => 'The exact page-local scope follows the reconstructed reading order.',
            'allowOrderChange' => true,
            'evidence' => [
                'hypothesis' => 'page-local-geometry-order',
                'bounds' => ['x1' => 50.0, 'y1' => 500.0, 'x2' => 550.0, 'y2' => 750.0],
                'sourceBounds' => ['x1' => 70.0, 'y1' => 600.0, 'x2' => 500.0, 'y2' => 700.0],
            ],
            'orderProof' => $proof,
        ];
        $dispositions = [
            'row' => $scopeDisposition($orderProof),
            'marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The standalone marker is represented by one exact ordered-list item.',
                'textProjection' => '',
                'semanticStructureProof' => $semanticMarkerProof('1.', 'anchor', 'Anchoritem.'),
            ],
            'anchor' => $scopeDisposition($orderProof),
        ];

        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            [$orderedList('Anchor item.'), $paragraph('Row text.')],
            $dispositions
        );
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems(
            $source,
            $binding['blocks'],
            $binding['explicitDispositions']
        );

        $t->same(true, $binding['complete']);
        $t->same(null, $binding['failureReason']);
        $t->same(
            'exact-semantic-list-marker',
            $binding['explicitDispositions']['marker']['sourceMapping']['mappingMode']
        );
        $t->same(true, $ledger['sourceEdgeMappingComplete']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);
        $t->same('mapped-occurrence-exact', $ledger['orderProofStrength']);
        $t->same(2, $ledger['evidencedOrderChangeOccurrenceCount']);
        $t->same(1, $ledger['evidencedOrderChangeScopeCount']);
    },
];
