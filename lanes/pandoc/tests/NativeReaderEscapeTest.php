<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocJsonReader;
use PortLibs\Pandoc\PandocJsonWriter;

return [
    'reads haskell named character escapes in pandoc native strings' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Str "alpha\ESC\&omega" , Space , Str "unit\US\&separator" ] ]
NATIVE;

        $document = (new NativeReader())->read($native);
        $paragraph = $document->children[0];

        $t->same('alpha' . chr(27) . 'omega', $paragraph->children[0]->attr('text'));
        $t->same('unit' . chr(31) . 'separator', $paragraph->children[2]->attr('text'));
        $t->same(
            '[ Para [ Str "alpha\27omega" , Space , Str "unit\31separator" ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($document)
        );
    },
    'reads haskell single letter control escapes in pandoc native strings' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Plain [ Str "\a\b\f\v" ] ]
NATIVE;

        $document = (new NativeReader())->read($native);

        $t->same(chr(7) . chr(8) . chr(12) . chr(11), $document->children[0]->children[0]->attr('text'));
    },
    'preserves textual native Space constructors through shared ast writers' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ Para [ Str "left" , Space , Emph [ Str "middle" , Space , Str "right" ] , Space , Str "tail" ] ]
NATIVE;

        $document = (new NativeReader())->read($native);
        $paragraph = $document->children[0];
        $emphasis = $paragraph->children[2];
        $json = (new PandocJsonWriter())->toArray(new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
            'meta' => [],
        ], $document->children));
        $roundTrip = (new PandocJsonReader())->readPacket($json);

        $t->same(['text', 'space', 'emph', 'space', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $paragraph->children
        ));
        $t->same(['text', 'space', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $emphasis->children
        ));
        $t->same('left middle right tail', $paragraph->attr('text'));
        $t->same(['Str', 'Space', 'Emph', 'Space', 'Str'], array_map(
            static fn (array $inline): string => $inline['t'],
            $json['blocks'][0]['c']
        ));
        $t->same(['Str', 'Space', 'Str'], array_map(
            static fn (array $inline): string => $inline['t'],
            $json['blocks'][0]['c'][2]['c']
        ));
        $t->same('left *middle right* tail', (new MarkdownWriter())->write($document));
        $t->same(
            '[ Para [ Str "left" , Space , Emph [ Str "middle" , Space , Str "right" ] , Space , Str "tail" ]' . "\n" . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($document)
        );
        $t->same(['text', 'space', 'emph', 'space', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $roundTrip->children[0]->children
        ));
    },
    'classifies textual native tex-family raw constructors through shared ast writers' => static function (TestRunner $t): void {
        $native = <<<'NATIVE'
[ RawBlock (Format "latex") "\\begin{review}body\\end{review}"
, Para [ Str "Before" , Space , RawInline (Format "context") "\\startformula x \\stopformula" , Space , RawInline (Format "tex+macros") "\\alpha" ] ]
NATIVE;

        $document = (new NativeReader())->read($native);
        $rawBlock = $document->children[0];
        $paragraph = $document->children[1];
        $contextInline = $paragraph->children[2];
        $texMacroInline = $paragraph->children[4];
        $json = (new PandocJsonWriter())->toArray(new AstNode('document', [
            'pandocApiVersion' => [1, 23, 1],
        ], $document->children));

        $t->same('raw_tex', $rawBlock->type);
        $t->same('latex', $rawBlock->attr('format'));
        $t->same('\\begin{review}body\\end{review}', $rawBlock->attr('tex'));
        $t->same('raw_tex_inline', $contextInline->type);
        $t->same('context', $contextInline->attr('format'));
        $t->same('\\startformula x \\stopformula', $contextInline->attr('tex'));
        $t->same('raw_tex_inline', $texMacroInline->type);
        $t->same('tex+macros', $texMacroInline->attr('format'));
        $t->same('\\alpha', $texMacroInline->attr('tex'));
        $t->same(['t' => 'RawBlock', 'c' => ['latex', '\\begin{review}body\\end{review}']], $json['blocks'][0]);
        $t->same(['t' => 'RawInline', 'c' => ['context', '\\startformula x \\stopformula']], $json['blocks'][1]['c'][2]);
        $t->same(['t' => 'RawInline', 'c' => ['tex+macros', '\\alpha']], $json['blocks'][1]['c'][4]);
        $t->same(
            '[ RawBlock (Format "latex") "\\\\begin{review}body\\\\end{review}"' . "\n"
            . ', Para [ Str "Before" , Space , RawInline (Format "context") "\\\\startformula x \\\\stopformula" , Space , RawInline (Format "tex+macros") "\\\\alpha" ]' . "\n"
            . ']',
            (new NativeWriter(['blocksOnly' => true]))->write($document)
        );
    },
];
