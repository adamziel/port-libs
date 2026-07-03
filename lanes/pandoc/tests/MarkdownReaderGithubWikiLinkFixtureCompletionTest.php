<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$firstLink = static function (AstNode $document, int $paragraph): AstNode {
    return $document->children[$paragraph]->children[0] ?? new AstNode('missing');
};

$cases = [
    ['https://example.org', 'https://example.org'],
    ['https://example.org', 'title'],
    ['random string', 'title'],
    ['Name of page', 'Name of page'],
    ['Name of ]page', 'Name of ]page'],
    ['https://example.org', 't`i*t_le'],
];

return [
    'maps upstream markdown github wikilink fixture title-before-pipe cases' =>
        static function (TestRunner $t) use ($cases, $firstLink): void {
            $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-github-wikilinks.md');
            $document = (new MarkdownReader(['format' => 'markdown_github+wikilinks_title_before_pipe']))->read($fixture);

            $t->same(count($cases), count($document->children));
            foreach ($cases as $index => [$url, $label]) {
                $paragraph = $document->children[$index] ?? new AstNode('missing');
                $link = $firstLink($document, $index);

                $t->same('paragraph', $paragraph->type, $label);
                $t->same(['link'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $label);
                $t->same('link', $link->type, $label);
                $t->same(['wikilink'], $link->attr('classes'), $label);
                $t->same($url, $link->attr('url'), $label);
                $t->same($label, $link->children[0]->attr('text'), $label);
            }
        },

    'renders upstream markdown github wikilink fixture through wordpress handoff' =>
        static function (TestRunner $t): void {
            $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-github-wikilinks.md');
            $document = (new MarkdownReader(['format' => 'markdown_github+wikilinks_title_before_pipe']))->read($fixture);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->contains('<a href="https://example.org" class="wikilink">https://example.org</a>', $blocks);
            $t->contains('<a href="https://example.org" class="wikilink">title</a>', $blocks);
            $t->contains('<a href="random string" class="wikilink">title</a>', $blocks);
            $t->contains('<a href="Name of ]page" class="wikilink">Name of ]page</a>', $blocks);
            $t->contains('<a href="https://example.org" class="wikilink">t`i*t_le</a>', $blocks);
        },

    'records upstream markdown github wikilink fixture mapped-case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(6, count($cases));
        },
];
