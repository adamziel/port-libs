<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$tests = [];

$tests['preserves native div plain block boundaries in wordpress handoff'] =
    static function (TestRunner $t): void {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [
                ['t' => 'Div', 'c' => [
                    ['boundary-div', [], [['data-source', 'native-boundary']]],
                    [
                        ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'First plain']]],
                        ['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Second plain']]],
                        ['t' => 'RawBlock', 'c' => ['html', '<section data-review="raw-boundary">Raw block</section>']],
                        ['t' => 'Para', 'c' => [['t' => 'Str', 'c' => 'After raw boundary']]],
                    ],
                ]],
            ],
        ];

        foreach ([
            'json' => (new PandocJsonReader())->readPacket($packet),
            'native' => (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR)),
        ] as $source => $document) {
            $div = $document->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('div', $div->type, "{$source} div constructor hydrates");
            $t->same(['plain', 'plain', 'raw_html', 'paragraph'], array_map(
                static fn (AstNode $child): string => $child->type,
                $div->children
            ), "{$source} div child block boundaries");
            $t->contains(
                '<div id="boundary-div" data-source="native-boundary"><p>First plain</p><p>Second plain</p><section data-review="raw-boundary">Raw block</section><p>After raw boundary</p></div>',
                $blocks,
                "{$source} wordpress preserves plain block boundaries"
            );
            $t->true(!str_contains($blocks, 'First plainSecond plain'), "{$source} wordpress does not merge adjacent plain blocks");
        }
    };

return $tests;

