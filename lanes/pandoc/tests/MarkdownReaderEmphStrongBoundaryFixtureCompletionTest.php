<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

return [
    'maps selected upstream markdown emph and strong boundary fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-emph-strong-boundaries.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $native = (new NativeWriter())->write($document);

            $t->same(4, count($document->children));

            $nested = $document->children[0] ?? new AstNode('missing');
            $emph = $nested->children[0] ?? new AstNode('missing');
            $t->same('paragraph', $nested->type);
            $t->same('emph', $emph->type);
            $t->same(
                ['strong', 'text', 'strong', 'text'],
                array_map(static fn (AstNode $node): string => $node->type, $emph->children)
            );
            $t->same('a', ($emph->children[0]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('b ', ($emph->children[1] ?? new AstNode('missing'))->attr('text'));
            $t->same('c', ($emph->children[2]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('d', ($emph->children[3] ?? new AstNode('missing'))->attr('text'));

            $alternating = $document->children[1] ?? new AstNode('missing');
            $t->same(
                ['emph', 'text', 'strong', 'text', 'softbreak', 'emph', 'text', 'strong', 'text'],
                array_map(static fn (AstNode $node): string => $node->type, $alternating->children)
            );
            $t->same('xxx', ($alternating->children[0]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('emph', ($alternating->children[2]->children[0] ?? new AstNode('missing'))->type);
            $t->same('xxx', ($alternating->children[2]->children[0]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('softbreak', ($alternating->children[4] ?? new AstNode('missing'))->type);
            $t->same('emph', ($alternating->children[7]->children[0] ?? new AstNode('missing'))->type);

            $spaced = $document->children[2] ?? new AstNode('missing');
            $spacedEmph = $spaced->children[0] ?? new AstNode('missing');
            $t->same('emph', $spacedEmph->type);
            $t->same(
                ['text', 'strong', 'text'],
                array_map(static fn (AstNode $node): string => $node->type, $spacedEmph->children)
            );
            $t->same('x ', ($spacedEmph->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('xx', ($spacedEmph->children[1]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same(' x', ($spacedEmph->children[2] ?? new AstNode('missing'))->attr('text'));

            $underscore = $document->children[3] ?? new AstNode('missing');
            $underscoreEmph = $underscore->children[0] ?? new AstNode('missing');
            $t->same('emph', $underscoreEmph->type);
            $t->same('foot_ball', ($underscoreEmph->children[0] ?? new AstNode('missing'))->attr('text'));

            $t->contains('Strong [ Str "a" ]', $native);
            $t->contains('Strong [ Emph [ Str "xxx" ] ]', $native);
            $t->contains('Emph [ Str "foot_ball" ]', $native);
        },

    'records upstream markdown emph and strong boundary fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-emph-strong-boundaries.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(4, count($cases));
            $t->same('***a**b **c**d*', $cases[0]);
            $t->same("*xxx* ***xxx*** xxx\n*xxx* ***xxx*** xxx", $cases[1]);
            $t->same('*x **xx** x*', $cases[2]);
            $t->same('_foot_ball_', $cases[3]);
        },
];
