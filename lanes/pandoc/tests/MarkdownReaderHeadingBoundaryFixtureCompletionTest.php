<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

return [
    'maps selected upstream markdown heading boundary fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-heading-boundaries.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);

            $t->same(4, count($document->children));

            $first = $document->children[0] ?? new AstNode('missing');
            $bracketed = $document->children[1] ?? new AstNode('missing');
            $setextFirst = $document->children[2] ?? new AstNode('missing');
            $setextSpaced = $document->children[3] ?? new AstNode('missing');

            $t->same('heading', $first->type);
            $t->same(1, $first->attr('level'));
            $t->same('Header', $first->attr('text'));
            $t->same('header', $first->attr('id'));

            $t->same('heading', $bracketed->type);
            $t->same(1, $bracketed->attr('level'));
            $t->same('[hi]', $bracketed->attr('text'));
            $t->same('hi', $bracketed->attr('id'));

            $t->same('heading', $setextFirst->type);
            $t->same(1, $setextFirst->attr('level'));
            $t->same('Foo bar', $setextFirst->attr('text'));
            $t->same('foo-bar', $setextFirst->attr('id'));

            $t->same('heading', $setextSpaced->type);
            $t->same(1, $setextSpaced->attr('level'));
            $t->same('Foo bar 2', $setextSpaced->attr('text'));
            $t->same('foo-bar-2', $setextSpaced->attr('id'));
        },

    'records selected upstream markdown heading boundary fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-heading-boundaries.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(4, count($cases));
            $t->same('# Header', $cases[0]);
            $t->same('# [hi]', $cases[1]);
            $t->same("Foo bar\n=", $cases[2]);
            $t->same(" Foo bar 2 \n=", $cases[3]);
        },

    'maps selected upstream markdown atx heading trim fixture' =>
        static function (TestRunner $t): void {
            $root = dirname(__DIR__) . '/fixtures';
            $source = (string) file_get_contents($root . '/upstream-markdown-atx-heading-trim.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);

            $t->true(is_file($root . '/upstream-markdown-atx-heading-trim.native'));
            $t->same(2, count($document->children));

            $first = $document->children[0] ?? new AstNode('missing');
            $second = $document->children[1] ?? new AstNode('missing');

            $t->same('heading', $first->type);
            $t->same(1, $first->attr('level'));
            $t->same('Foo bar', $first->attr('text'));
            $t->same('foo-bar', $first->attr('id'));

            $t->same('heading', $second->type);
            $t->same(1, $second->attr('level'));
            $t->same('Foo bar with #', $second->attr('text'));
            $t->same('foo-bar-with', $second->attr('id'));
        },
];
