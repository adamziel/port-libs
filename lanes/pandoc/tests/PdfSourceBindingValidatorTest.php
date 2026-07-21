<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PdfSourceBindingValidator;
use PortLibs\Pandoc\PdfSourceDispositionLedger;
use PortLibs\Pandoc\PdfSourceSemanticBindingValidator;
use PortLibs\Pandoc\PdfSourceSemanticReceiptBindingValidator;
use PortLibs\Pandoc\PdfSourceTokenInterleavingValidator;

$paragraph = static fn (string $text): AstNode => new AstNode('paragraph', [], [
    new AstNode('text', ['text' => $text]),
]);

$validate = static function (array $source, array $blocks, array $explicit = []): array {
    return PdfSourceBindingValidator::validateSourceLineItemsToOutput(
        $source,
        $blocks,
        $explicit
    );
};

return [
    'pdf token interleaving helper preserves unique and ambiguous solver decisions' => static function (
        TestRunner $t
    ): void {
        $unique = [
            'source-a' => 'alpha+gamma;',
            'source-b' => 'beta-delta!',
        ];
        $ambiguous = [
            'source-a' => 'x+',
            'source-b' => 'x+',
        ];

        $t->same(true, PdfSourceTokenInterleavingValidator::hasUniqueTokenInterleavingOrderProof(
            $unique,
            'alpha+beta-gamma;delta!'
        ));
        $t->same(false, PdfSourceTokenInterleavingValidator::hasUniqueTokenInterleavingOrderProof(
            $ambiguous,
            'x+x+'
        ));
        $t->same(true, PdfSourceBindingValidator::hasUniqueTokenInterleavingOrderProof(
            $unique,
            'alpha+beta-gamma;delta!'
        ));
        $t->same(false, PdfSourceBindingValidator::hasUniqueTokenInterleavingOrderProof(
            $ambiguous,
            'x+x+'
        ));
    },

    'pdf source binding validation does not autoload the disposition ledger' => static function (
        TestRunner $t
    ) use ($paragraph, $validate): void {
        $t->same(false, class_exists(PdfSourceDispositionLedger::class, false));
        $t->same(false, class_exists(PdfSourceSemanticBindingValidator::class, false));
        $t->same(false, class_exists(PdfSourceSemanticReceiptBindingValidator::class, false));

        $validation = $validate(
            [['id' => 'line-a', 'page' => 1, 'text' => 'Alpha beta.']],
            [$paragraph('Alpha beta.')]
        );

        $t->same(true, $validation['complete'] ?? null);
        $t->same(null, $validation['failureReason'] ?? null);
        $t->same(true, class_exists(PdfSourceBindingValidator::class, false));
        $t->same(false, class_exists(PdfSourceSemanticBindingValidator::class, false));
        $t->same(false, class_exists(PdfSourceSemanticReceiptBindingValidator::class, false));
        $t->same(false, class_exists(PdfSourceDispositionLedger::class, false));

        $semanticSource = [
            ['id' => 'ordered-marker', 'page' => 2, 'stream' => 7, 'text' => '1.'],
            ['id' => 'ordered-anchor', 'page' => 2, 'stream' => 7, 'text' => 'Ordered item.'],
            ['id' => 'bullet-marker', 'page' => 2, 'stream' => 7, 'text' => "\u{2022}"],
            ['id' => 'bullet-anchor', 'page' => 2, 'stream' => 7, 'text' => 'Bullet item.'],
        ];
        $semanticBlocks = [
            new AstNode('ordered_list', ['start' => 1], [
                new AstNode('list_item', [], [$paragraph('Ordered item.')]),
            ]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [$paragraph('Bullet item.')]),
            ]),
        ];
        $semanticExplicit = [
            'ordered-marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The ordered marker is represented by list structure.',
                'textProjection' => '',
                'semanticStructureProof' => [
                    'version' => 1,
                    'method' => 'exact-standalone-list-marker-to-item',
                    'listType' => 'ordered',
                    'markerOrdinal' => 1,
                    'markerDigest' => hash('sha256', '1.'),
                    'anchorSourceOccurrenceId' => 'ordered-anchor',
                    'itemProjectionDigest' => hash('sha256', 'Ordereditem.'),
                ],
            ],
            'bullet-marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The bullet marker is represented by list structure.',
                'textProjection' => '',
                'semanticStructureProof' => [
                    'version' => 1,
                    'method' => 'exact-standalone-list-marker-to-item',
                    'listType' => 'bullet',
                    'markerOrdinal' => null,
                    'markerDigest' => hash('sha256', "\u{2022}"),
                    'anchorSourceOccurrenceId' => 'bullet-anchor',
                    'itemProjectionDigest' => hash('sha256', 'Bulletitem.'),
                ],
            ],
        ];
        $semanticValidation = $validate(
            $semanticSource,
            $semanticBlocks,
            $semanticExplicit
        );

        $t->same(true, $semanticValidation['complete'] ?? null);
        $t->same(null, $semanticValidation['failureReason'] ?? null);
        $t->same(true, class_exists(PdfSourceSemanticBindingValidator::class, false));
        $t->same(false, class_exists(PdfSourceSemanticReceiptBindingValidator::class, false));
        $t->same(false, class_exists(PdfSourceDispositionLedger::class, false));
    },

    'semantic structure planning preserves ordered and unsorted range parity' => static function (
        TestRunner $t
    ) use ($paragraph): void {
        $itemSignificant = 'Actualitem.Continued.';
        $proof = [
            'version' => 1,
            'method' => 'exact-standalone-list-marker-to-item',
            'listType' => 'ordered',
            'markerOrdinal' => 1,
            'markerDigest' => hash('sha256', '1.'),
            'anchorSourceOccurrenceId' => 'anchor',
            'itemProjectionDigest' => hash('sha256', $itemSignificant),
        ];
        $record = static fn (
            string $id,
            string $sourceText,
            string $projectionText,
            string $disposition = 'emitted',
            ?array $semanticProof = null
        ): array => [
            'id' => $id,
            'page' => 1,
            'stream' => 3,
            'disposition' => $disposition,
            'sourceText' => $sourceText,
            'projectionText' => $projectionText,
            'textProjection' => $disposition === 'semantic-structure' ? '' : null,
            'significant' => preg_replace('/\s+/u', '', $projectionText) ?? $projectionText,
            'sourceSignificant' => preg_replace('/\s+/u', '', $sourceText) ?? $sourceText,
            'evidence' => [],
            'allowOrderChange' => false,
            'orderProof' => null,
            'semanticStructureProof' => $semanticProof,
        ];
        $records = [
            $record('marker', '1.', '', 'semantic-structure', $proof),
            $record('anchor', 'Actual item.', 'Actual item.'),
            $record('following', 'Continued.', 'Continued.'),
        ];
        $blocks = [new AstNode('ordered_list', ['start' => 1], [
            new AstNode('list_item', [], [$paragraph('Actual item. Continued.')]),
        ])];
        $anchorEnd = strlen('Actualitem.');
        $ranges = [
            [
                'sourceOccurrenceId' => 'anchor',
                'sourceStart' => 0,
                'sourceEnd' => $anchorEnd,
                'outputStart' => 0,
                'outputEnd' => $anchorEnd,
            ],
            [
                'sourceOccurrenceId' => 'following',
                'sourceStart' => 0,
                'sourceEnd' => strlen('Continued.'),
                'outputStart' => $anchorEnd,
                'outputEnd' => strlen($itemSignificant),
            ],
        ];

        $ordered = PdfSourceSemanticBindingValidator::sourceBindingSemanticStructurePlan(
            $records,
            $blocks,
            $ranges,
            []
        );
        $unsorted = PdfSourceSemanticBindingValidator::sourceBindingSemanticStructurePlan(
            $records,
            $blocks,
            array_reverse($ranges),
            []
        );

        $t->same([
            'targetsBySourceId' => ['marker' => ['blockIndex' => 0, 'itemIndex' => 0]],
            'failureReason' => null,
        ], $ordered);
        $t->same($ordered, $unsorted);
    },

    'pdf source binding validator matches ledger decisions for exact and reordered ranges' => static function (
        TestRunner $t
    ) use ($paragraph, $validate): void {
        $source = [
            ['id' => 'row-one', 'page' => 1, 'text' => 'Left one. Right one.'],
            ['id' => 'row-two', 'page' => 1, 'text' => 'Left two. Right two.'],
        ];
        $leftOneEnd = strlen('Leftone.');
        $leftTwoEnd = strlen('Lefttwo.');
        $proof = [
            'scopeId' => 'page-1-exact-positioned-ranges',
            'sourceOccurrenceIds' => ['row-one', 'row-two'],
            'emittedTextProjection' => 'Left one. Left two. Right one. Right two.',
            'emittedSourceRanges' => [
                ['sourceOccurrenceId' => 'row-one', 'sourceStart' => 0, 'sourceEnd' => $leftOneEnd],
                ['sourceOccurrenceId' => 'row-two', 'sourceStart' => 0, 'sourceEnd' => $leftTwoEnd],
                [
                    'sourceOccurrenceId' => 'row-one',
                    'sourceStart' => $leftOneEnd,
                    'sourceEnd' => strlen('Leftone.Rightone.'),
                ],
                [
                    'sourceOccurrenceId' => 'row-two',
                    'sourceStart' => $leftTwoEnd,
                    'sourceEnd' => strlen('Lefttwo.Righttwo.'),
                ],
            ],
        ];
        $explicit = [];
        foreach (['row-one', 'row-two'] as $id) {
            $explicit[$id] = [
                'disposition' => 'boundary-repair',
                'reason' => 'Exact positioned ranges map this row into two columns.',
                'allowOrderChange' => true,
                'orderProof' => $proof,
            ];
        }
        $cases = [
            [
                [$paragraph('Left one.'), $paragraph('Left two.'), $paragraph('Right one.'), $paragraph('Right two.')],
                $explicit,
            ],
            [
                [$paragraph('Left two.'), $paragraph('Left one.'), $paragraph('Right one.'), $paragraph('Right two.')],
                $explicit,
            ],
            [
                [$paragraph('Left one.'), $paragraph('Right one.')],
                [],
            ],
        ];

        foreach ($cases as [$blocks, $dispositions]) {
            $validation = $validate($source, $blocks, $dispositions);
            $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
                $source,
                $blocks,
                $dispositions
            );
            $t->same($binding['complete'], $validation['complete']);
            $t->same($binding['failureReason'], $validation['failureReason']);
        }
    },

    'pdf source binding validator preserves exact semantic marker validation' => static function (
        TestRunner $t
    ) use ($paragraph, $validate): void {
        $source = [
            ['id' => 'marker', 'page' => 1, 'stream' => 3, 'text' => '1.'],
            ['id' => 'anchor', 'page' => 1, 'stream' => 3, 'text' => 'Actual item.'],
        ];
        $blocks = [new AstNode('ordered_list', ['start' => 1], [
            new AstNode('list_item', [], [$paragraph('Actual item.')]),
        ])];
        $explicit = [
            'marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The standalone marker is represented by this list item.',
                'textProjection' => '',
                'semanticStructureProof' => [
                    'version' => 1,
                    'method' => 'exact-standalone-list-marker-to-item',
                    'listType' => 'ordered',
                    'markerOrdinal' => 1,
                    'markerDigest' => hash('sha256', '1.'),
                    'anchorSourceOccurrenceId' => 'anchor',
                    'itemProjectionDigest' => hash('sha256', 'Actualitem.'),
                ],
            ],
        ];

        $validation = $validate($source, $blocks, $explicit);
        $binding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
            $source,
            $blocks,
            $explicit
        );

        $t->same($binding['complete'], $validation['complete']);
        $t->same($binding['failureReason'], $validation['failureReason']);

        $compare = static function (
            array $caseSource,
            array $caseBlocks,
            array $caseExplicit,
            string $label
        ) use ($t, $validate): void {
            $caseValidation = $validate($caseSource, $caseBlocks, $caseExplicit);
            $caseBinding = PdfSourceDispositionLedger::bindSourceLineItemsToOutput(
                $caseSource,
                $caseBlocks,
                $caseExplicit
            );
            $t->same($caseBinding['complete'], $caseValidation['complete'], $label);
            $t->same(
                $caseBinding['failureReason'],
                $caseValidation['failureReason'],
                $label
            );
        };

        $wrongDigest = $explicit;
        $wrongDigest['marker']['semanticStructureProof']['markerDigest'] = str_repeat('0', 64);
        $compare($source, $blocks, $wrongDigest, 'tampered ordered marker digest');

        $wrongOrdinal = $explicit;
        $wrongOrdinal['marker']['semanticStructureProof']['markerOrdinal'] = 2;
        $compare($source, $blocks, $wrongOrdinal, 'tampered ordered ordinal');

        $compare(
            $source,
            [new AstNode('blockquote', [], $blocks)],
            $explicit,
            'nested ordered target'
        );

        $bulletSource = [
            ['id' => 'bullet-marker', 'page' => 3, 'stream' => 8, 'text' => "\u{2022}"],
            ['id' => 'bullet-anchor', 'page' => 3, 'stream' => 8, 'text' => 'Bullet body.'],
        ];
        $bulletBlocks = [new AstNode('bullet_list', [], [
            new AstNode('list_item', [], [$paragraph('Bullet body.')]),
        ])];
        $bulletExplicit = [
            'bullet-marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'The bullet marker is represented by this list item.',
                'textProjection' => '',
                'semanticStructureProof' => [
                    'version' => 1,
                    'method' => 'exact-standalone-list-marker-to-item',
                    'listType' => 'bullet',
                    'markerOrdinal' => null,
                    'markerDigest' => hash('sha256', "\u{2022}"),
                    'anchorSourceOccurrenceId' => 'bullet-anchor',
                    'itemProjectionDigest' => hash('sha256', 'Bulletbody.'),
                ],
            ],
        ];
        $compare($bulletSource, $bulletBlocks, $bulletExplicit, 'valid bullet marker');
        $wrongBulletType = $bulletExplicit;
        $wrongBulletType['bullet-marker']['semanticStructureProof']['listType'] = 'ordered';
        $wrongBulletType['bullet-marker']['semanticStructureProof']['markerOrdinal'] = 1;
        $compare($bulletSource, $bulletBlocks, $wrongBulletType, 'tampered bullet type');

        $extendedSource = [
            ['id' => 'extended-marker', 'page' => 5, 'stream' => 9, 'text' => '1.'],
            ['id' => 'extended-anchor', 'page' => 5, 'stream' => 9, 'text' => 'Underlying'],
            ['id' => 'extended-second', 'page' => 5, 'stream' => 9, 'text' => 'considerations:'],
            ['id' => 'extended-third', 'page' => 5, 'stream' => 9, 'text' => 'employee records.'],
        ];
        $extendedProjection = 'Underlyingconsiderations:employeerecords.';
        $extendedBlocks = [new AstNode('ordered_list', ['start' => 1], [
            new AstNode('list_item', [], [
                $paragraph('Underlying considerations: employee records.'),
            ]),
        ])];
        $extendedExplicit = [
            'extended-marker' => [
                'disposition' => 'semantic-structure',
                'reason' => 'Three exact source occurrences identify one structural item.',
                'textProjection' => '',
                'semanticStructureProof' => [
                    'version' => 2,
                    'method' => 'exact-standalone-list-marker-to-item',
                    'listType' => 'ordered',
                    'markerOrdinal' => 1,
                    'markerDigest' => hash('sha256', '1.'),
                    'anchorSourceOccurrenceId' => 'extended-anchor',
                    'anchorSourceOccurrenceIds' => [
                        'extended-anchor',
                        'extended-second',
                        'extended-third',
                    ],
                    'anchorProjectionDigest' => hash('sha256', $extendedProjection),
                    'itemProjectionDigest' => hash('sha256', $extendedProjection),
                ],
            ],
        ];
        $compare(
            $extendedSource,
            $extendedBlocks,
            $extendedExplicit,
            'valid version-2 ordered marker'
        );
        $t->same(
            false,
            class_exists(PdfSourceSemanticReceiptBindingValidator::class, false)
        );
        $wrongExtendedDigest = $extendedExplicit;
        $wrongExtendedDigest['extended-marker']['semanticStructureProof']['anchorProjectionDigest'] =
            str_repeat('0', 64);
        $compare(
            $extendedSource,
            $extendedBlocks,
            $wrongExtendedDigest,
            'tampered version-2 anchor digest'
        );

        $invalidReceipt = $extendedExplicit;
        $invalidReceipt['extended-marker']['semanticStructureProof'][
            'presentationRepairReceipt'
        ] = [];
        $invalidReceiptValidation = $validate(
            $extendedSource,
            $extendedBlocks,
            $invalidReceipt
        );
        $t->same(false, $invalidReceiptValidation['complete'] ?? null);
        $t->same(
            'semantic-list-marker-extended-anchor-proof-does-not-match-source',
            $invalidReceiptValidation['failureReason'] ?? null
        );
        $t->same(
            true,
            class_exists(PdfSourceSemanticReceiptBindingValidator::class, false)
        );
    },
];
