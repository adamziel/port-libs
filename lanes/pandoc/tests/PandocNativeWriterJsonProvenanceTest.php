<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;

return [
    'writes pandoc json when child raw format and attr sidecars need preservation' => static function (TestRunner $t): void {
        $formatNative = ['t' => 'Format', 'c' => 'html5', 'reviewQueue' => 'raw-format-source'];
        $attrNative = ['t' => 'Attr', 'c' => [['code-id', ['php'], [['data-source', 'native-json']]]], 'reviewQueue' => 'attr-source'];
        $document = new AstNode('document', [], [
            new AstNode('code_block', [
                'id' => 'code-id',
                'classes' => ['php'],
                'attributes' => ['data-source' => 'native-json'],
                'attrNative' => $attrNative,
                'text' => 'echo "ok";',
            ]),
            new AstNode('paragraph', [], [
                new AstNode('raw_html_inline', [
                    'format' => 'html5',
                    'formatNative' => $formatNative,
                    'text' => '<span data-review="raw">ok</span>',
                    'html' => '<span data-review="raw">ok</span>',
                ]),
            ]),
        ]);

        $decoded = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same([1, 23, 1], $decoded['pandoc-api-version']);
        $t->same($attrNative, $decoded['blocks'][0]['c'][0]);
        $t->same($formatNative, $decoded['blocks'][1]['c'][0]['c'][0]);
    },
    'regenerates stale attr sidecars while retaining unchanged raw format sidecars' => static function (TestRunner $t): void {
        $formatNative = ['t' => 'Format', 'c' => 'html5', 'reviewQueue' => 'raw-format-source'];
        $attrNative = ['t' => 'Attr', 'c' => [['code-id', ['php'], [['data-source', 'native-json']]]], 'reviewQueue' => 'attr-source'];
        $document = new AstNode('document', [], [
            new AstNode('code_block', [
                'id' => 'edited-code-id',
                'classes' => ['php'],
                'attributes' => ['data-source' => 'native-json'],
                'attrNative' => $attrNative,
                'text' => 'echo "edited";',
            ]),
            new AstNode('paragraph', [], [
                new AstNode('raw_html_inline', [
                    'format' => 'html5',
                    'formatNative' => $formatNative,
                    'text' => '<span data-review="raw">edited</span>',
                    'html' => '<span data-review="raw">edited</span>',
                ]),
            ]),
        ]);

        $decoded = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same([
            't' => 'Attr',
            'c' => [['edited-code-id', ['php'], [['data-source', 'native-json']]]],
            'reviewQueue' => 'attr-source',
        ], $decoded['blocks'][0]['c'][0]);
        $t->same($formatNative, $decoded['blocks'][1]['c'][0]['c'][0]);
        $t->same('<span data-review="raw">edited</span>', $decoded['blocks'][1]['c'][0]['c'][1]);
    },
    'keeps plain sidecar-free documents on textual native output' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Plain native output']),
            ]),
        ]);

        $native = (new NativeWriter())->write($document);

        $t->contains('Para [ Str "Plain" , Space , Str "native" , Space , Str "output" ]', $native);
        $t->throws(JsonException::class, static fn (): mixed => json_decode($native, true, 512, JSON_THROW_ON_ERROR));
    },
    'round trips null block through textual native output' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('horizontal_rule'),
            new AstNode('null_block'),
        ]);

        $native = (new NativeWriter())->write($document);
        $roundTrip = (new NativeReader())->read($native);

        $t->same("[ HorizontalRule\n, Null\n]", $native);
        $t->same(['horizontal_rule', 'null_block'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
    },
];
