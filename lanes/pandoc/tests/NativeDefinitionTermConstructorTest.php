<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'coalesces native definition term text while preserving following code constructors' => static function (TestRunner $t): void {
        $nativeBlocks = [[
            't' => 'DefinitionList',
            'c' => [[
                [
                    ['t' => 'Str', 'c' => 'source'],
                    ['t' => 'Space'],
                    ['t' => 'Code', 'c' => [
                        ['', ['term-code'], [['data-term', 'source']]],
                        'packet',
                    ]],
                ],
                [[
                    ['t' => 'Plain', 'c' => [
                        ['t' => 'Str', 'c' => 'definition'],
                    ]],
                ]],
            ]],
        ]];
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => $nativeBlocks,
        ];

        $document = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        $term = $document->children[0]->children[0]->children[0];
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same(['text', 'code'], array_map(static fn (AstNode $node): string => $node->type, $term->children));
        $t->same('source ', $term->children[0]->attr('text'));
        $t->same(['Str', 'Space'], $term->children[0]->attr('nativeInlineConstructors'));
        $t->same(['term-code'], $term->children[1]->attr('classes'));
        $t->same('packet', $term->children[1]->attr('text'));
        $t->same($nativeBlocks, $roundTrip['blocks']);
    },
    'splits generated definition term fallback text into native inline constructors' => static function (TestRunner $t): void {
        $document = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [
            new AstNode('definition_list', [], [
                new AstNode('definition_item', [], [
                    new AstNode('definition_term', ['text' => 'Generated term']),
                    new AstNode('definition', [], [
                        new AstNode('plain', [], [new AstNode('text', ['text' => 'body'])]),
                    ]),
                ]),
            ]),
        ]);

        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $termInlines = $native['blocks'][0]['c'][0][0];
        $roundTrip = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $term = $roundTrip->children[0]->children[0]->children[0];

        $t->same(['Str', 'Space', 'Str'], array_map(static fn (array $inline): string => $inline['t'], $termInlines));
        $t->same('Generated', $termInlines[0]['c']);
        $t->same('term', $termInlines[2]['c']);
        $t->same('Generated term', $term->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $term->children));
        $t->same(['Str', 'Space', 'Str'], $term->children[0]->attr('nativeInlineConstructors'));
    },
    'renders json native definition term nodes through html and native writers' => static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [[
                't' => 'DefinitionList',
                'c' => [[
                    [
                        ['t' => 'Str', 'c' => 'Cello'],
                        ['t' => 'LineBreak'],
                        ['t' => 'Str', 'c' => 'Violoncello'],
                    ],
                    [[
                        ['t' => 'Para', 'c' => [
                            ['t' => 'Str', 'c' => 'Low-voiced'],
                            ['t' => 'Space'],
                            ['t' => 'Str', 'c' => 'instrument.'],
                        ]],
                    ]],
                ]],
            ]],
        ];

        $documents = [
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ];

        foreach ($documents as $source => $document) {
            $term = $document->children[0]->children[0]->children[0];
            $wordpress = (new WordPressBlockWriter())->write($document);
            $nativeText = (new NativeWriter(['blocksOnly' => true]))->write($document);

            $t->same('definition_term', $term->type, "{$source} term node");
            $t->same(['text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $term->children), "{$source} term inline nodes");
            $t->contains('<dl><dt>Cello<br/>Violoncello</dt><dd>Low-voiced instrument.</dd></dl>', $wordpress, "{$source} wordpress definition term");
            $t->contains('[ Str "Cello" , LineBreak , Str "Violoncello" ]', $nativeText, "{$source} native definition term");
        }
    },
];
