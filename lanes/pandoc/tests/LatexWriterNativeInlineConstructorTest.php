<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\NativeReader;

return [
    'renders native json inline formatting constructors as latex commands' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Review'],
                        ['t' => 'Space'],
                        ['t' => 'Underline', 'c' => [
                            ['t' => 'Str', 'c' => 'required'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Strikeout', 'c' => [
                            ['t' => 'Str', 'c' => 'stale'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Superscript', 'c' => [
                            ['t' => 'Str', 'c' => '2'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Subscript', 'c' => [
                            ['t' => 'Str', 'c' => 'n'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'SmallCaps', 'c' => [
                            ['t' => 'Str', 'c' => 'caps'],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Quoted', 'c' => [
                            ['t' => 'SingleQuote'],
                            [
                                ['t' => 'Str', 'c' => 'quoted'],
                            ],
                        ]],
                        ['t' => 'Space'],
                        ['t' => 'Quoted', 'c' => [
                            ['t' => 'DoubleQuote'],
                            [
                                ['t' => 'Str', 'c' => 'source'],
                            ],
                        ]],
                    ],
                ],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $paragraph = $document->children[0];

        $t->same('Underline', $paragraph->children[1]->attr('constructor'));
        $t->same('Strikeout', $paragraph->children[3]->attr('constructor'));
        $t->same(
            "Review \\underline{required} \\sout{stale} \\textsuperscript{2} \\textsubscript{n} \\textsc{caps} `quoted' ``source''",
            (new LatexWriter())->write($document)
        );
    },
    'keeps generic underline and strikeout shorthand commands' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Review ']),
                new AstNode('underline', [], [
                    new AstNode('text', ['text' => 'under']),
                ]),
                new AstNode('text', ['text' => ' ']),
                new AstNode('strikeout', [], [
                    new AstNode('text', ['text' => 'old']),
                ]),
            ]),
        ]);

        $t->same('Review \ul{under} \st{old}', (new LatexWriter())->write($document));
    },
];
