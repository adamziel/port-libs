<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\FontStyleCleaner;

return [
    'marks upstream bold and italic spans from font names and weights' => static function (TestRunner $t): void {
        $cleaner = new FontStyleCleaner();
        $blocks = [
            [
                'type' => 'Text',
                'lines' => [
                    [
                        'spans' => [
                            ['text' => 'Normal ', 'font' => 'Helvetica', 'font_weight' => 400],
                            ['text' => 'font bold', 'font' => 'Helvetica-Bold', 'font_weight' => 400],
                            ['text' => ' font italic', 'font' => 'Times-Italic', 'font_weight' => 400],
                            ['text' => ' heavy', 'font' => 'Serif', 'font_weight' => 700],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'Title',
                'lines' => [
                    [
                        'spans' => [
                            ['text' => 'Weighted title', 'font' => 'Title-Italic', 'font_weight' => 700],
                        ],
                    ],
                ],
            ],
        ];

        $marked = $cleaner->markBoldItalicSpans($blocks);

        $t->true(!($marked[0]['lines'][0]['spans'][0]['bold'] ?? false));
        $t->true($marked[0]['lines'][0]['spans'][1]['bold'] ?? false);
        $t->true($marked[0]['lines'][0]['spans'][2]['italic'] ?? false);
        $t->true($marked[0]['lines'][0]['spans'][3]['bold'] ?? false);
        $t->true($marked[1]['lines'][0]['spans'][0]['bold'] ?? false);
        $t->true(!($marked[1]['lines'][0]['spans'][0]['italic'] ?? false), 'Heading font-name style pass should be skipped like upstream.');
    },
    'skips font weight pass when upstream has no non-heading font stats' => static function (TestRunner $t): void {
        $cleaner = new FontStyleCleaner();
        $blocks = [
            [
                'type' => 'Title',
                'lines' => [
                    [
                        'spans' => [
                            ['text' => 'Heading only', 'font' => 'Title-BoldItalic', 'font_weight' => 700],
                        ],
                    ],
                ],
            ],
        ];

        $marked = $cleaner->markBoldItalicSpans($blocks);

        $t->true(!($marked[0]['lines'][0]['spans'][0]['bold'] ?? false));
        $t->true(!($marked[0]['lines'][0]['spans'][0]['italic'] ?? false));
    },
    'wraps middle styled spans with upstream markdown emphasis markers' => static function (TestRunner $t): void {
        $cleaner = new FontStyleCleaner();
        $line = [
            ['text' => 'Keep ', 'font' => 'Helvetica', 'font_weight' => 400],
            ['text' => 'media library', 'font' => 'Helvetica-Bold', 'font_weight' => 400],
            ['text' => ' captions ', 'font' => 'Helvetica', 'font_weight' => 400],
            ['text' => 'reviewable', 'font' => 'Helvetica-Oblique', 'font_weight' => 400, 'italic' => true],
            ['text' => ' during import.', 'font' => 'Helvetica', 'font_weight' => 400],
        ];
        $marked = $cleaner->markBoldItalicSpans([['type' => 'Text', 'lines' => [['spans' => $line]]]]);

        $t->same(
            'Keep **media library** captions *reviewable* during import.',
            $cleaner->mergeStyledLine($marked[0]['lines'][0]['spans'])
        );
    },
    'preserves upstream first and last span emphasis boundaries' => static function (TestRunner $t): void {
        $cleaner = new FontStyleCleaner();

        $t->same(
            'Bold start with normal text Bold end',
            $cleaner->mergeStyledLine([
                ['text' => 'Bold start', 'bold' => true],
                ['text' => ' with normal text ', 'bold' => false],
                ['text' => 'Bold end', 'bold' => true],
            ])
        );
    },
];
