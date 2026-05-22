<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableScorer;

return [
    'splits upstream benchmark tables into pipe cells' => static function (TestRunner $t): void {
        $scorer = new TableScorer();

        $t->same(
            [
                ['AB', 'C'],
                ['D', 'EF'],
            ],
            $scorer->splitToCells("  A  B|C\n\nD|E  F  ")
        );
    },
    'aligns a reference row against the best hypothesis row' => static function (TestRunner $t): void {
        $alignment = (new TableScorer())->alignRows(
            [
                ['Item', 'Wrong'],
                ['Name', 'Count'],
            ],
            ['Name', 'Count', 'Notes']
        );

        $t->same([1.0, 1.0, 0.0], $alignment);
    },
    'scores exact markdown tables as perfect benchmark matches' => static function (TestRunner $t): void {
        $table = "| Product | Count |\n| Apples | 10 |\n| Pears | 8 |";

        $t->same(1.0, (new TableScorer())->scoreTable($table, $table));
    },
    'scores WordPress imported tables over the upstream verifier threshold' => static function (TestRunner $t): void {
        $reference = "| Block | Status |\n| Intro | Published |\n| Media | Draft |";
        $hypothesis = "| Block | Status |\n| Intro | Published |\n| Media | Draff |";
        $score = (new TableScorer())->scoreTable($hypothesis, $reference);

        $t->true($score > 0.98, 'Expected OCR-noisy WordPress table score above 0.98, got ' . $score);
        $t->true($score >= 0.70, 'Expected upstream table verifier threshold to pass.');
    },
    'rejects empty table scoring inputs' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new TableScorer())->scoreTable('', ''));
    },
];
