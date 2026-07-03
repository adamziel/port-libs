<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

return [
    'maps selected upstream markdown escaped line break fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-escaped-line-break.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same("alpha\nbeta", $paragraph->attr('text'));
            $t->same(
                ['text', 'linebreak', 'text'],
                array_map(static fn (AstNode $node): string => $node->type, $paragraph->children)
            );
            $t->same('alpha', ($paragraph->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('beta', ($paragraph->children[2] ?? new AstNode('missing'))->attr('text'));
        },

    'records upstream markdown escaped line break fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-escaped-line-break.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same("alpha\\\nbeta", $cases[0]);
        },
];
