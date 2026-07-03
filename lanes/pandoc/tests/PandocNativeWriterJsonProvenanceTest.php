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

        $t->same(['edited-code-id', ['php'], [['data-source', 'native-json']]], $decoded['blocks'][0]['c'][0]);
        $t->same(false, array_key_exists('reviewQueue', $decoded['blocks'][0]['c'][0]));
        $t->same($formatNative, $decoded['blocks'][1]['c'][0]['c'][0]);
        $t->same('<span data-review="raw">edited</span>', $decoded['blocks'][1]['c'][0]['c'][1]);
    },
    'writes pandoc json when generated note labels need sidecar preservation' => static function (TestRunner $t): void {
        $noteBlocks = [
            new AstNode('plain', [], [
                new AstNode('text', ['text' => 'Labelled']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'note']),
            ]),
        ];
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Review']),
                new AstNode('space'),
                new AstNode('note', ['label' => 'editor-note'], $noteBlocks),
                new AstNode('space'),
                new AstNode('note', ['noteLabel' => 'alias-note'], $noteBlocks),
                new AstNode('space'),
                new AstNode('note', ['label' => 'invalid label'], $noteBlocks),
            ]),
        ]);

        $decoded = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $notes = array_values(array_filter(
            $decoded['blocks'][0]['c'],
            static fn (mixed $inline): bool => is_array($inline) && ($inline['t'] ?? null) === 'Note'
        ));

        $t->same([1, 23, 1], $decoded['pandoc-api-version']);
        $t->same('editor-note', $notes[0]['noteLabel'] ?? null);
        $t->same('alias-note', $notes[1]['noteLabel'] ?? null);
        $t->same(false, array_key_exists('noteLabel', $notes[2]));
    },
    'writes pandoc json for metadata raw family documents without changing raw-only text mode' => static function (TestRunner $t): void {
        $rawOnly = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('raw_html_inline', [
                    'format' => 'html5',
                    'text' => '<span data-review="raw">ok</span>',
                    'html' => '<span data-review="raw">ok</span>',
                ]),
            ]),
        ]);
        $withMeta = new AstNode('document', [
            'meta' => [
                'review' => [
                    'format' => 'html5',
                    'family' => 'html',
                ],
            ],
        ], $rawOnly->children);

        $nativeText = (new NativeWriter())->write($rawOnly);
        $decoded = json_decode((new NativeWriter())->write($withMeta), true, 512, JSON_THROW_ON_ERROR);

        $t->contains('RawInline (Format "html5") "<span data-review=\\"raw\\">ok</span>"', $nativeText);
        $t->throws(JsonException::class, static fn (): mixed => json_decode($nativeText, true, 512, JSON_THROW_ON_ERROR));
        $t->same([1, 23, 1], $decoded['pandoc-api-version']);
        $t->same(['t' => 'RawInline', 'c' => ['html5', '<span data-review="raw">ok</span>']], $decoded['blocks'][0]['c'][0]);
        $t->same('MetaMap', $decoded['meta']['review']['t']);
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
