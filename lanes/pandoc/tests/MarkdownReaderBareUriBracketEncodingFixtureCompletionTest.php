<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzz-bare-uri-bracket-encoding.md'
);

$collectLinks = static function (AstNode $node) use (&$collectLinks): array {
    $links = [];
    if ($node->type === 'link') {
        $links[] = $node;
    }

    foreach ($node->children as $child) {
        array_push($links, ...$collectLinks($child));
    }

    return $links;
};

return [
    'maps selected upstream markdown bare URI bracket encoding fixture' =>
        static function (TestRunner $t) use ($fixture, $collectLinks): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($fixture());
            $links = $collectLinks($document);
            $native = (new NativeWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(2, count($links));
            $t->same('http://en.wikipedia.org/wiki/Sprite_%5Bcomputer_graphics%5D', $links[0]->attr('url'));
            $t->same('http://en.wikipedia.org/wiki/Sprite_[computer_graphics]', $links[0]->children[0]->attr('text'));
            $t->same(['uri'], $links[0]->attr('classes'));
            $t->same('http://en.wikipedia.org/wiki/Sprite_%7Bcomputer_graphics%7D', $links[1]->attr('url'));
            $t->same('http://en.wikipedia.org/wiki/Sprite_{computer_graphics}', $links[1]->children[0]->attr('text'));
            $t->same(['uri'], $links[1]->attr('classes'));
            $t->contains('Sprite_%5Bcomputer_graphics%5D', $native);
            $t->contains('Sprite_%7Bcomputer_graphics%7D', $native);
            $t->contains('href="http://en.wikipedia.org/wiki/Sprite_%5Bcomputer_graphics%5D"', $blocks);
            $t->contains('href="http://en.wikipedia.org/wiki/Sprite_%7Bcomputer_graphics%7D"', $blocks);
        },

    'records selected upstream markdown bare URI bracket encoding fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(2, count($cases));
            $t->same('http://en.wikipedia.org/wiki/Sprite_[computer_graphics]', $cases[0]);
            $t->same('http://en.wikipedia.org/wiki/Sprite_{computer_graphics}', $cases[1]);
        },
];
