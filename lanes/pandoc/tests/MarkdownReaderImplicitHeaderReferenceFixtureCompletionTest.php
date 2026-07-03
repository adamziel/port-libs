<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps selected upstream markdown implicit header reference fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-implicit-header-references.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $heading = $document->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(4, count($document->children));
            $t->same('heading', $heading->type);
            $t->same(1, $heading->attr('level'));
            $t->same('Foo bar', $heading->attr('text'));
            $t->same('foo-bar', $heading->attr('id'));
            $t->same([], $heading->attr('classes', []));
            $t->same([], $heading->attr('attributes', []));

            foreach (['foo bar', 'foo bar ', ' foo bar'] as $offset => $labelText) {
                $paragraph = $document->children[$offset + 1] ?? new AstNode('missing');
                $link = $paragraph->children[0] ?? new AstNode('missing');
                $linkText = $link->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, $labelText);
                $t->same($labelText, $paragraph->attr('text'), $labelText);
                $t->same('link', $link->type, $labelText);
                $t->same('#foo-bar', $link->attr('url'), $labelText);
                $t->same($labelText, $linkText->attr('text'), $labelText);
            }

            $t->contains('<h1 id="foo-bar">Foo bar</h1>', $blocks);
            $t->contains('<a href="#foo-bar">foo bar</a>', $blocks);
            $t->contains('<a href="#foo-bar">foo bar </a>', $blocks);
            $t->contains('<a href="#foo-bar"> foo bar</a>', $blocks);
        },

    'records upstream markdown implicit header reference fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-implicit-header-references.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(3, count($cases));
            $t->same("# Foo bar #\n[foo bar]", $cases[0]);
            $t->same('[foo bar ]', $cases[1]);
            $t->same('[ foo bar]', $cases[2]);
        },
];
