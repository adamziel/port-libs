<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

return [
    'maps selected upstream markdown lhs inverse bird html fixture through format extension' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-lhs-inverse-bird-html.md');
            $document = (new MarkdownReader(['format' => 'markdown+lhs']))->read($source);

            $t->same(
                ['code_block', 'code_block', 'div'],
                array_map(static fn (AstNode $node): string => $node->type, $document->children)
            );
            $t->same(['haskell', 'literate'], $document->children[0]->attr('classes'));
            $t->same('a', $document->children[0]->attr('text'));
            $t->same(['haskell'], $document->children[1]->attr('classes'));
            $t->same('b', $document->children[1]->attr('text'));
            $t->same([], $document->children[2]->children);
        },

    'maps selected upstream markdown lhs fixture through literate haskell alias' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-lhs-inverse-bird-html.md');
            $document = (new MarkdownReader(['format' => 'markdown+literate_haskell']))->read($source);

            $t->same('code_block', $document->children[0]->type ?? 'missing');
            $t->same(['haskell', 'literate'], $document->children[0]->attr('classes'));
        },

    'keeps selected upstream markdown lhs fixture behind extension gate' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-lhs-inverse-bird-html.md');
            $document = (new MarkdownReader())->read($source);

            $t->same('blockquote', $document->children[0]->type ?? 'missing');
        },
];
