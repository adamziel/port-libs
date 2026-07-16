<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

return [
    'maps selected upstream markdown unbalanced bracket fixture as literal text' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-unbalanced-brackets.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $text = $paragraph->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same('[[[[[[[[[[[[hi', $paragraph->attr('text'));
            $t->same(1, count($paragraph->children));
            $t->same('text', $text->type);
            $t->same('[[[[[[[[[[[[hi', $text->attr('text'));
        },

    'records upstream markdown unbalanced bracket fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-unbalanced-brackets.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('[[[[[[[[[[[[hi', $cases[0]);
        },
];
