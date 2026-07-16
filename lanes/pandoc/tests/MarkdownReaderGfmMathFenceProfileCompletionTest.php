<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

return [
    'maps pandoc gfm tex_math_gfm fenced math fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-gfm-math-fence-profile.md');
            $document = (new MarkdownReader(['format' => 'gfm']))->read($source);
            $mathParagraph = $document->children[0] ?? new AstNode('missing');
            $math = $mathParagraph->children[0] ?? new AstNode('missing');
            $after = $document->children[1] ?? new AstNode('missing');

            $t->same(2, count($document->children));
            $t->same('paragraph', $mathParagraph->type);
            $t->same('math', $math->type);
            $t->same(true, $math->attr('display'));
            $t->same("x+1\n\ny^2", $math->attr('text'));
            $t->same('paragraph', $after->type);
            $t->same('After z+1.', $after->attr('text'));
            $t->same('math', $after->children[1]->type ?? null);
            $t->same(false, $after->children[1]->attr('display'));
        },

    'keeps pandoc gfm fenced math behind tex_math_gfm extension gate' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-gfm-math-fence-profile.md');
            $disabled = (new MarkdownReader(['format' => 'gfm-tex_math_gfm']))->read($source);
            $enabledCommonMark = (new MarkdownReader(['format' => 'commonmark_x+tex_math_gfm']))->read($source);

            $t->same('code_block', ($disabled->children[0] ?? new AstNode('missing'))->type);
            $t->same(['math'], ($disabled->children[0] ?? new AstNode('missing'))->attr('classes'));
            $t->same("x+1\n\ny^2", ($disabled->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('paragraph', ($enabledCommonMark->children[0] ?? new AstNode('missing'))->type);
            $t->same('math', ($enabledCommonMark->children[0]->children[0] ?? new AstNode('missing'))->type);
        },

    'records pandoc gfm tex_math_gfm fenced math fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-gfm-math-fence-profile.md');
            $blocks = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $block): bool => $block !== ''
            ));

            $t->same(3, count($blocks));
            $t->same("```math\nx+1", $blocks[0]);
            $t->same("y^2\n```", $blocks[1]);
            $t->same('After $z+1$.', $blocks[2]);
        },
];
