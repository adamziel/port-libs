<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'preserves textual native nullary inline constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Str "Alpha", Space, SoftBreak, Str "Beta", LineBreak, Str "Gamma" ] ]
NATIVE;
        $expectedInlines = [
            ['t' => 'Str', 'c' => 'Alpha'],
            ['t' => 'Space'],
            ['t' => 'SoftBreak'],
            ['t' => 'Str', 'c' => 'Beta'],
            ['t' => 'LineBreak'],
            ['t' => 'Str', 'c' => 'Gamma'],
        ];

        $nativeDocument = (new NativeReader())->read($native);
        $paragraph = $nativeDocument->children[0];
        $children = $paragraph->children;
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same(['text', 'text', 'softbreak', 'text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $children));
        $t->same(['Space'], $children[1]->attr('nativeInlineConstructors'));
        $t->same([['t' => 'Space']], $children[1]->attr('nativeInlineParts'));
        $t->same('SoftBreak', $children[2]->attr('constructor'));
        $t->same(['t' => 'SoftBreak'], $children[2]->attr('native'));
        $t->same('LineBreak', $children[4]->attr('constructor'));
        $t->same(['t' => 'LineBreak'], $children[4]->attr('native'));
        $t->same($expectedInlines, $jsonPacket['blocks'][0]['c']);
        $t->same($expectedInlines, $nativePacket['blocks'][0]['c']);
        $t->same(
            '[ Para [ Str "Alpha" , Space , SoftBreak , Str "Beta" , LineBreak , Str "Gamma" ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($nativeDocument)
        );
    },
    'preserves textual native raw format constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ RawBlock (Format "markdown+raw_tex") "$x$"
, Para [ RawInline (Format "latex") "\\alpha", Space, RawInline (Format "html5") "<span>ok</span>" ]
]
NATIVE;
        $markdownFormat = ['t' => 'Format', 'c' => 'markdown+raw_tex'];
        $texFormat = ['t' => 'Format', 'c' => 'latex'];
        $htmlFormat = ['t' => 'Format', 'c' => 'html5'];
        $markdownNative = ['t' => 'RawBlock', 'c' => [$markdownFormat, '$x$']];
        $texNative = ['t' => 'RawInline', 'c' => [$texFormat, '\\alpha']];
        $htmlNative = ['t' => 'RawInline', 'c' => [$htmlFormat, '<span>ok</span>']];

        $nativeDocument = (new NativeReader())->read($native);
        $rawBlock = $nativeDocument->children[0];
        $paragraph = $nativeDocument->children[1];
        $texInline = $paragraph->children[0];
        $htmlInline = $paragraph->children[2];
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same('raw_markdown', $rawBlock->type);
        $t->same('RawBlock', $rawBlock->attr('constructor'));
        $t->same($markdownNative, $rawBlock->attr('native'));
        $t->same('Format', $rawBlock->attr('formatConstructor'));
        $t->same($markdownFormat, $rawBlock->attr('formatNative'));
        $t->same('raw_tex_inline', $texInline->type);
        $t->same('RawInline', $texInline->attr('constructor'));
        $t->same($texNative, $texInline->attr('native'));
        $t->same('Format', $texInline->attr('formatConstructor'));
        $t->same($texFormat, $texInline->attr('formatNative'));
        $t->same('raw_html_inline', $htmlInline->type);
        $t->same('RawInline', $htmlInline->attr('constructor'));
        $t->same($htmlNative, $htmlInline->attr('native'));
        $t->same('Format', $htmlInline->attr('formatConstructor'));
        $t->same($htmlFormat, $htmlInline->attr('formatNative'));
        $t->same($markdownFormat, $jsonPacket['blocks'][0]['c'][0]);
        $t->same($texFormat, $jsonPacket['blocks'][1]['c'][0]['c'][0]);
        $t->same($htmlFormat, $jsonPacket['blocks'][1]['c'][2]['c'][0]);
        $t->same($jsonPacket['blocks'], $nativePacket['blocks']);
        $t->same(
            '[ RawBlock (Format "markdown+raw_tex") "$x$"' . "\n"
            . ', Para [ RawInline (Format "latex") "\\\\alpha" , Space , RawInline (Format "html5") "<span>ok</span>" ]' . "\n"
            . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($nativeDocument)
        );

        $staleBlockNative = $markdownNative;
        $staleBlockNative['reviewQueue'] = 'textual-raw-block-source';
        $staleInlineNative = $texNative;
        $staleInlineNative['reviewQueue'] = 'textual-raw-inline-source';
        $editedDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], [
            new AstNode('raw_markdown', array_replace($rawBlock->attrs, [
                'text' => '$y$',
                'markdown' => '$y$',
                'native' => $staleBlockNative,
            ])),
            new AstNode('paragraph', [], [
                new AstNode('raw_tex_inline', array_replace($texInline->attrs, [
                    'text' => '\\beta',
                    'tex' => '\\beta',
                    'native' => $staleInlineNative,
                ])),
            ]),
        ]);

        foreach ([
            'json' => (new PandocJsonWriter())->toArray($editedDocument),
            'native' => json_decode((new NativeWriter())->write($editedDocument), true, 512, JSON_THROW_ON_ERROR),
        ] as $writer => $editedPacket) {
            $t->same(['t' => 'RawBlock', 'c' => [$markdownFormat, '$y$']], $editedPacket['blocks'][0], "{$writer} regenerates edited textual raw block");
            $t->same(['t' => 'RawInline', 'c' => [$texFormat, '\\beta']], $editedPacket['blocks'][1]['c'][0], "{$writer} regenerates edited textual raw inline");
            $t->same(false, array_key_exists('reviewQueue', $editedPacket['blocks'][0]), "{$writer} drops stale raw block sidecar");
            $t->same(false, array_key_exists('reviewQueue', $editedPacket['blocks'][1]['c'][0]), "{$writer} drops stale raw inline sidecar");
        }
    },
    'preserves textual native quote and math enum constructor provenance' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Quoted SingleQuote [ Str "quoted" ], Space, Math InlineMath "x + 1", Space, Math DisplayMath "y = 2" ] ]
NATIVE;
        $singleQuote = ['t' => 'SingleQuote'];
        $inlineMath = ['t' => 'InlineMath'];
        $displayMath = ['t' => 'DisplayMath'];

        $nativeDocument = (new NativeReader())->read($native);
        $paragraph = $nativeDocument->children[0];
        $quoted = $paragraph->children[0];
        $inline = $paragraph->children[2];
        $display = $paragraph->children[4];
        $jsonDocument = new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $nativeDocument->children);
        $jsonPacket = (new PandocJsonWriter())->toArray($jsonDocument);
        $nativePacket = json_decode((new NativeWriter())->write($jsonDocument), true, 512, JSON_THROW_ON_ERROR);

        $t->same('quoted', $quoted->type);
        $t->same('single', $quoted->attr('kind'));
        $t->same('SingleQuote', $quoted->attr('quoteTypeConstructor'));
        $t->same($singleQuote, $quoted->attr('quoteTypeNative'));
        $t->same('math', $inline->type);
        $t->same(false, $inline->attr('display'));
        $t->same('InlineMath', $inline->attr('mathTypeConstructor'));
        $t->same($inlineMath, $inline->attr('mathTypeNative'));
        $t->same('math', $display->type);
        $t->same(true, $display->attr('display'));
        $t->same('DisplayMath', $display->attr('mathTypeConstructor'));
        $t->same($displayMath, $display->attr('mathTypeNative'));
        $t->same($singleQuote, $jsonPacket['blocks'][0]['c'][0]['c'][0]);
        $t->same($inlineMath, $jsonPacket['blocks'][0]['c'][2]['c'][0]);
        $t->same($displayMath, $jsonPacket['blocks'][0]['c'][4]['c'][0]);
        $t->same($jsonPacket['blocks'], $nativePacket['blocks']);
        $t->same(
            '[ Para [ Quoted SingleQuote [ Str "quoted" ] , Space , Math InlineMath "x + 1" , Space , Math DisplayMath "y = 2" ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($nativeDocument)
        );
    },
];
