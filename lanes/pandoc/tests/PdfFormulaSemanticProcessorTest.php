<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfFormulaSemanticProcessor;

$layout = static fn (
    int $page,
    float $x1,
    float $y1,
    float $x2,
    float $y2,
    float $fontSize = 10.0,
    array $extra = []
): array => array_replace(compact('page', 'x1', 'y1', 'x2', 'y2', 'fontSize'), $extra);

return [
    'formula processor conserves split base superscript and operator atoms' => static function (TestRunner $t) use ($layout): void {
        $processor = new PdfFormulaSemanticProcessor();
        $records = $processor->process([
            ['text' => 'a', 'layout' => $layout(2, 278.0, 467.0, 288.0, 480.0, 10.0, ['positionedTextCandidate' => 'a2 + 8 = 12'])],
            ['text' => '2', 'layout' => $layout(2, 284.0, 472.0, 292.0, 481.0, 7.0)],
            ['text' => '+ 8 = 12', 'layout' => $layout(2, 290.0, 467.0, 334.0, 480.0)],
        ]);

        $t->same(1, count($records));
        $t->same('a2 + 8 = 12', $records[0]['text']);
        $t->same('formula', $records[0]['layout']['sourcePdfRegionRole']);
        $t->same(1, $processor->regionCount());
    },

    'formula processor recognizes a compact equality without equation-specific values' => static function (TestRunner $t) use ($layout): void {
        $processor = new PdfFormulaSemanticProcessor();
        $records = $processor->process([
            ['text' => 'q = 47', 'layout' => $layout(1, 100.0, 300.0, 145.0, 312.0)],
        ]);

        $t->same('formula', $records[0]['layout']['sourcePdfRegionRole']);
        $t->same('q = 47', $records[0]['text']);
    },

    'formula processor does not promote ordinary assignment prose' => static function (TestRunner $t) use ($layout): void {
        $processor = new PdfFormulaSemanticProcessor();
        $records = $processor->process([
            ['text' => 'status = ready', 'layout' => $layout(1, 100.0, 300.0, 190.0, 312.0)],
        ]);

        $t->same(0, $processor->regionCount());
        $t->same(null, $records[0]['layout']['sourcePdfRegionRole'] ?? null);
    },

    'formula processor does not split a programming loop header' => static function (TestRunner $t) use ($layout): void {
        $processor = new PdfFormulaSemanticProcessor();
        $records = $processor->process([
            ['text' => 'for (var k = i + i; i < 100; k += i)', 'layout' => $layout(1, 100.0, 300.0, 340.0, 312.0)],
        ]);

        $t->same(0, $processor->regionCount());
        $t->same(null, $records[0]['layout']['sourcePdfRegionRole'] ?? null);
    },

    'formula processor rejects a positioned candidate that changes source characters' => static function (TestRunner $t) use ($layout): void {
        $processor = new PdfFormulaSemanticProcessor();
        $records = $processor->process([
            ['text' => 'a', 'layout' => $layout(1, 10.0, 20.0, 20.0, 32.0, 10.0, ['positionedTextCandidate' => 'a2 + 8 = 13'])],
            ['text' => '2', 'layout' => $layout(1, 18.0, 24.0, 24.0, 32.0, 7.0)],
            ['text' => 'plus eight equals twelve', 'layout' => $layout(1, 25.0, 20.0, 150.0, 32.0)],
        ]);

        $t->same(0, $processor->regionCount());
        $t->same(3, count($records));
    },

    'formula processor never joins formula-looking atoms across pages' => static function (TestRunner $t) use ($layout): void {
        $processor = new PdfFormulaSemanticProcessor();
        $records = $processor->process([
            ['text' => 'x', 'layout' => $layout(1, 10.0, 20.0, 20.0, 32.0)],
            ['text' => '= 9', 'layout' => $layout(2, 20.0, 20.0, 45.0, 32.0)],
        ]);

        $t->same(0, $processor->regionCount());
        $t->same(2, count($records));
    },

    'formula source reconciliation requires exact characters and local source order' => static function (TestRunner $t) use ($layout): void {
        $processor = new PdfFormulaSemanticProcessor();
        $source = [
            ['page' => 2, 'stream' => 4, 'text' => 'k'],
            ['page' => 2, 'stream' => 4, 'text' => '3'],
            ['page' => 2, 'stream' => 4, 'text' => '+ 5 = 19'],
        ];
        $positioned = $layout(2, 200.0, 400.0, 260.0, 414.0, 10.0, [
            'text' => 'k3 + 5 = 19',
            'sourceStream' => 4,
            'sourceOrderStart' => 80,
            'sourceOrderEnd' => 85,
        ]);

        $t->same(true, $processor->sourceFragmentsMatchPositionedFormula($source, [0, 1, 2], $positioned));
        $t->same(false, $processor->sourceFragmentsMatchPositionedFormula($source, [0, 2], $positioned));
        $wrongPage = $positioned;
        $wrongPage['page'] = 3;
        $t->same(false, $processor->sourceFragmentsMatchPositionedFormula($source, [0, 1, 2], $wrongPage));
        $wrongStream = $positioned;
        $wrongStream['sourceStream'] = 5;
        $t->same(false, $processor->sourceFragmentsMatchPositionedFormula($source, [0, 1, 2], $wrongStream));
        $positioned['text'] = 'k3 + 5 = 20';
        $t->same(false, $processor->sourceFragmentsMatchPositionedFormula($source, [0, 1, 2], $positioned));
    },
];
