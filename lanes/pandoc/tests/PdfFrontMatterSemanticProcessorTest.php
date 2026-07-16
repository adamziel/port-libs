<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfFrontMatterSemanticProcessor;

$record = static function (
    string $text,
    int $order,
    float $y,
    float $fontSize,
    int $page = 1
): array {
    return [
        'text' => $text,
        'layout' => [
            'page' => $page,
            'x1' => 80.0,
            'y1' => $y,
            'x2' => 520.0,
            'y2' => $y + $fontSize,
            'fontSize' => $fontSize,
            'sourceOrderStart' => $order,
            'sourceOrderEnd' => $order,
        ],
    ];
};

return [
    'front matter processor restores title credits and summary before body columns' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfFrontMatterSemanticProcessor();
        $records = $processor->process([
            $record('A General Study of Layered Documents', 0, 760.0, 18.0),
            $record('Researcher One · Researcher Two', 10, 720.0, 11.0),
            $record('Example Institute', 20, 700.0, 11.0),
            $record('Résumé', 30, 650.0, 13.0),
            $record('1 Overview', 100, 500.0, 12.0),
            $record('This compact account starts here and', 31, 628.0, 10.0),
            $record('continues on its next visual baseline.', 40, 616.0, 10.0),
        ]);

        $t->same([
            'A General Study of Layered Documents',
            'Researcher One · Researcher Two',
            'Example Institute',
            'Résumé',
            'This compact account starts here and',
            'continues on its next visual baseline.',
            '1 Overview',
        ], array_column($records, 'text'));
        $t->same('title', $records[0]['layout']['sourcePdfFrontMatterRole']);
        $t->same('credits', $records[1]['layout']['sourcePdfFrontMatterRole']);
        $t->same('summary-heading', $records[3]['layout']['sourcePdfFrontMatterRole']);
        $t->same('summary-body', $records[4]['layout']['sourcePdfFrontMatterRole']);
        $t->same(true, $records[0]['layout']['sourcePdfDisplayHeading']);
        $t->same(true, $records[3]['layout']['sourcePdfDisplayHeading']);
        $t->same(true, $records[4]['layout']['forceBlockBreakBefore']);
        $t->same(6, $processor->recordCount());
    },

    'front matter processor is idempotent after classification' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfFrontMatterSemanticProcessor();
        $once = $processor->process([
            $record('A Sufficiently Large Document Title', 0, 760.0, 18.0),
            $record('Person One and Person Two', 10, 720.0, 11.0),
            $record('Summary', 20, 670.0, 13.0),
            $record('Summary prose continues here.', 21, 648.0, 10.0),
            $record('1 Details', 100, 500.0, 12.0),
        ]);
        $twice = $processor->process($once);

        $t->same($once, $twice);
        $t->same(4, $processor->recordCount());
    },

    'front matter processor requires a section boundary' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfFrontMatterSemanticProcessor();
        $records = [
            $record('A Sufficiently Large Document Title', 0, 760.0, 18.0),
            $record('Person One and Person Two', 10, 720.0, 11.0),
            $record('Ordinary prose follows without a section label.', 20, 680.0, 10.0),
        ];

        $t->same($records, $processor->process($records));
        $t->same(0, $processor->recordCount());
    },

    'front matter processor requires visual title contrast' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfFrontMatterSemanticProcessor();
        $records = [
            $record('An Ordinary Body Sentence Before The Section', 0, 600.0, 10.0),
            $record('Another ordinary sentence in the same flow.', 10, 585.0, 10.0),
            $record('1 Details', 20, 560.0, 10.0),
        ];

        $t->same($records, $processor->process($records));
        $t->same(0, $processor->recordCount());
    },
];
