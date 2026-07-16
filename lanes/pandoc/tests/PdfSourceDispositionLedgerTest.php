<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PdfSourceDispositionLedger;

$paragraph = static fn (string $text): AstNode => new AstNode('paragraph', [], [
    new AstNode('text', ['text' => $text]),
]);

return [
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
                'evidence' => ['hypothesis' => 'independent-columns', 'margin' => 0.31],
            ],
            'row-two' => [
                'disposition' => 'semantic-structure',
                'reason' => 'High-margin independent columns represent cue delimiters as structure.',
                'textProjection' => 'BETA Left two. ALFA Right two.',
                'allowOrderChange' => true,
                'evidence' => ['hypothesis' => 'independent-columns', 'margin' => 0.31],
            ],
        ];
        $ledger = PdfSourceDispositionLedger::fromSourceLineItems($source, [
            $paragraph('ALFA Left one. BETA Left two. BETA Right one. ALFA Right two.'),
        ], $dispositions);

        $t->same(true, $ledger['allOccurrencesResolved']);
        $t->same(true, $ledger['orderedSignificantCharactersPreserved']);
        $t->same('evidenced-layout-reorder', $ledger['orderedSignificantCharacterBasis']);
        $t->same(2, $ledger['evidencedOrderChangeOccurrenceCount']);
        $t->same(0, $ledger['unclaimedEmittedSignificantCharacterCount']);
        $t->true($ledger['sourceSignificantCharacterDigest'] !== $ledger['emittedSignificantCharacterDigest']);
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
];
