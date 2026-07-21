<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PdfReader;

return [
    'ordinary source boundaries do not load the misordered diacritic repair helper' => static function (
        TestRunner $t
    ): void {
        $helperClass = 'PortLibs\\Pandoc\\PdfMisorderedDiacriticBoundaryRepair';
        $t->same(false, class_exists($helperClass, false));

        $reader = new PdfReader();
        $repairsFor = (function (array $items, array $blocks): array {
            return $this->pdfSourceMisorderedDiacriticBoundaryRepairs($items, $blocks);
        })->bindTo($reader, PdfReader::class);
        $t->true($repairsFor instanceof \Closure);

        $items = [
            ['id' => 'ordinary-1', 'page' => 1, 'stream' => 1, 'text' => 'Ordinary'],
            ['id' => 'ordinary-2', 'page' => 1, 'stream' => 1, 'text' => ' source'],
            ['id' => 'ordinary-3', 'page' => 1, 'stream' => 1, 'text' => ' text'],
        ];
        $blocks = [new AstNode('paragraph', ['text' => 'Ordinary source text'])];

        $t->same([], $repairsFor($items, $blocks));
        $t->same(false, class_exists($helperClass, false));
    },
];
