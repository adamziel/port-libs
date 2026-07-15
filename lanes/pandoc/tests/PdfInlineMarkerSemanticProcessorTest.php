<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfInlineMarkerSemanticProcessor;

$record = static function (
    string $text,
    float $x1,
    float $y1,
    float $x2,
    float $y2,
    float $fontSize,
    int $order,
    int $page = 1,
    int $stream = 1
): array {
    return [
        'text' => $text,
        'layout' => [
            'page' => $page,
            'sourceStream' => $stream,
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
            'fontSize' => $fontSize,
            'sourceOrderStart' => $order,
            'sourceOrderEnd' => $order,
        ],
    ];
};

return [
    'inline marker processor reattaches a detached numeric superscript by geometry' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $records = $processor->process([
            $record('A sentence ends here.', 80.0, 394.0, 142.0, 407.0, 10.0, 20),
            $record('1', 138.0, 399.0, 145.0, 408.0, 7.0, 21),
            $record('1 Introduction', 70.0, 372.0, 156.0, 387.0, 12.0, 22),
        ]);

        $t->same(['A sentence ends here.1', '1 Introduction'], array_column($records, 'text'));
        $t->same(1, $records[0]['layout']['sourcePdfInlineMarkerCount']);
        $t->same(true, $records[0]['layout']['sourcePdfProtectedSemanticContent']);
        $t->same(21, $records[0]['layout']['sourceOrderEnd']);
        $t->same(1, $processor->markerCount());
    },

    'inline marker processor supports typographic footnote symbols without lexical context' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $records = $processor->process([
            $record('Contributors', 80.0, 700.0, 145.0, 713.0, 10.0, 1),
            $record('†', 144.0, 705.0, 150.0, 714.0, 7.0, 2),
        ]);

        $t->same('Contributors†', $records[0]['text']);
        $t->same(1, $processor->markerCount());
    },

    'inline marker processor preserves full-size section numbers as separate records' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $records = [
            $record('Prior paragraph.', 80.0, 430.0, 160.0, 443.0, 10.0, 1),
            $record('1', 80.0, 400.0, 90.0, 415.0, 12.0, 2),
            $record('Introduction', 94.0, 400.0, 170.0, 415.0, 12.0, 3),
        ];

        $t->same($records, $processor->process($records));
        $t->same(0, $processor->markerCount());
    },

    'inline marker processor preserves distant footnote-body labels' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $records = [
            $record('Body prose above the notes.', 80.0, 180.0, 230.0, 193.0, 10.0, 1),
            $record('1', 84.0, 112.0, 90.0, 120.0, 6.0, 2),
            $record('The code and models are public.', 94.0, 112.0, 260.0, 120.0, 6.0, 3),
        ];

        $t->same($records, $processor->process($records));
        $t->same(0, $processor->markerCount());
    },

    'inline marker processor does not cross pages or content streams' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $records = [
            $record('Page ending', 80.0, 394.0, 142.0, 407.0, 10.0, 1, 1, 1),
            $record('2', 140.0, 399.0, 147.0, 408.0, 7.0, 2, 2, 1),
            $record('Stream ending', 80.0, 300.0, 142.0, 313.0, 10.0, 3, 2, 1),
            $record('*', 140.0, 305.0, 147.0, 314.0, 7.0, 4, 2, 2),
        ];

        $t->same($records, $processor->process($records));
        $t->same(0, $processor->markerCount());
    },

    'inline marker processor is idempotent after attachment' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $once = $processor->process([
            $record('Result', 80.0, 394.0, 142.0, 407.0, 10.0, 1),
            $record('a', 140.0, 399.0, 147.0, 408.0, 7.0, 2),
        ]);
        $twice = $processor->process($once);

        $t->same($once, $twice);
        $t->same(0, $processor->markerCount());
    },
];
