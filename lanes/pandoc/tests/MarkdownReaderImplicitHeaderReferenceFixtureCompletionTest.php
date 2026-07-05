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

            foreach (['foo bar', 'foo bar', 'foo bar'] as $offset => $labelText) {
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
        },

    'maps selected upstream markdown implicit header reference atx and setext variants' =>
        static function (TestRunner $t): void {
            foreach ([
                'upstream-markdown-implicit-header-reference-atx.md',
                'upstream-markdown-implicit-header-reference-setext.md',
            ] as $fixture) {
                $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $fixture);
                $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
                $heading = $document->children[0] ?? new AstNode('missing');
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same(4, count($document->children), $fixture);
                $t->same('heading', $heading->type, $fixture);
                $t->same('Header', $heading->attr('text'), $fixture);
                $t->same('header', $heading->attr('id'), $fixture);

                for ($index = 1; $index <= 3; $index++) {
                    $paragraph = $document->children[$index] ?? new AstNode('missing');
                    $link = $paragraph->children[0] ?? new AstNode('missing');
                    $linkText = $link->children[0] ?? new AstNode('missing');

                    $t->same('paragraph', $paragraph->type, $fixture . ' paragraph ' . $index);
                    $t->same('header', $paragraph->attr('text'), $fixture . ' paragraph text ' . $index);
                    $t->same('link', $link->type, $fixture . ' link ' . $index);
                    $t->same('#header', $link->attr('url'), $fixture . ' url ' . $index);
                    $t->same('header', $linkText->attr('text'), $fixture . ' link text ' . $index);
                }

                $t->contains('<h1 id="header">Header</h1>', $blocks, $fixture);
                $t->contains('<a href="#header">header</a>', $blocks, $fixture);
            }
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

    'records upstream markdown implicit header reference variant fixture literals' =>
        static function (TestRunner $t): void {
            $atx = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-implicit-header-reference-atx.md');
            $setext = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-implicit-header-reference-setext.md');

            $t->same("# Header\n[header]\n\n[header ]\n\n[ header]", trim($atx));
            $t->same("Header\n=\n\n[header]\n\n[header ]\n\n[ header]", ltrim(rtrim($setext, "\n")));
        },
];
