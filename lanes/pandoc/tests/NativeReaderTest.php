<?php

declare(strict_types=1);

use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

return [
    'round trips pandoc native json metadata constructors without loss' => static function (TestRunner $t): void {
        $native = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [
                'title' => [
                    't' => 'MetaInlines',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Quarterly'],
                        ['t' => 'Space'],
                        ['t' => 'Str', 'c' => 'Review'],
                    ],
                ],
                'draft' => ['t' => 'MetaBool', 'c' => false],
                'tags' => [
                    't' => 'MetaList',
                    'c' => [
                        ['t' => 'MetaString', 'c' => 'wp-import'],
                        ['t' => 'MetaString', 'c' => 'native-ast'],
                    ],
                ],
                'review' => [
                    't' => 'MetaMap',
                    'c' => [
                        'source' => ['t' => 'MetaString', 'c' => 'native-json'],
                        'priority' => ['t' => 'MetaString', 'c' => 'high'],
                    ],
                ],
            ],
            'blocks' => [
                [
                    't' => 'Para',
                    'c' => [
                        ['t' => 'Str', 'c' => 'Body'],
                    ],
                ],
            ],
        ];

        $document = (new NativeReader())->read(json_encode($native, JSON_THROW_ON_ERROR));
        $roundTrip = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('document', $document->type);
        $t->same('pandoc-json', $document->attr('nativeFormat'));
        $t->same($native['pandoc-api-version'], $document->attr('pandocApiVersion'));
        $t->same($native['meta'], $document->attr('meta'));
        $t->same('Para', $document->children[0]->attr('constructor'));
        $t->same($native['meta'], $roundTrip['meta']);
        $t->same($native['blocks'], $roundTrip['blocks']);
        $t->same($native, $roundTrip);
    },
    'round trips markdown paragraph inlines through pandoc native ast json' => static function (TestRunner $t): void {
        $markdown = "Native *AST* **roundtrip** with `code` and [link](https://example.test/source)\nnext line.";
        $document = (new MarkdownReader())->read($markdown);

        $nativeJson = (new NativeWriter())->write($document);
        $native = json_decode($nativeJson, true, 512, JSON_THROW_ON_ERROR);
        $nativeInlineTypes = array_map(
            static fn (array $inline): string => $inline['t'],
            $native['blocks'][0]['c']
        );

        $roundTrip = (new NativeReader())->read($nativeJson);
        $roundTripMarkdown = (new MarkdownWriter())->write($roundTrip);

        $t->same('Para', $native['blocks'][0]['t']);
        $t->same([
            'Str',
            'Space',
            'Emph',
            'Space',
            'Strong',
            'Space',
            'Str',
            'Space',
            'Code',
            'Space',
            'Str',
            'Space',
            'Link',
            'SoftBreak',
            'Str',
            'Space',
            'Str',
        ], $nativeInlineTypes);
        $t->same('paragraph', $roundTrip->children[0]->type);
        $t->same('Native AST roundtrip with code and link next line.', $roundTrip->children[0]->attr('text'));
        $t->same($markdown, $roundTripMarkdown);
    },
];
