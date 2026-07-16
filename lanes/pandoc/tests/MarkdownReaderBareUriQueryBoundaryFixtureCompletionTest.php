<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-bare-uri-query-boundaries.md'
);

$hyphenFixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-bare-uri-query-hyphen-boundaries.md'
);

$portPathFixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-bare-uri-port-path-boundaries.md'
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

$inlineTypes = static fn (AstNode $node): array =>
    array_map(static fn (AstNode $child): string => $child->type, $node->children);

return [
    'maps selected upstream markdown bare URI query boundary fixture' =>
        static function (TestRunner $t) use ($fixture, $collectLinks, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($fixture());
            $links = $collectLinks($document);
            $native = (new NativeWriter())->write($document);

            $t->same(3, count($document->children));
            $t->same(3, count($links));
            $t->same(['text', 'link', 'text'], $inlineTypes($document->children[0] ?? new AstNode('missing')));
            $t->same('Try this query: ', ($document->children[0]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('.', ($document->children[0]->children[2] ?? new AstNode('missing'))->attr('text'));

            $t->same('http://google.com?search=fish&time=hour', $links[0]->attr('url'));
            $t->same('http://google.com?search=fish&time=hour', ($links[0]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same(['uri'], $links[0]->attr('classes'));
            $t->same('http://en.wikipedia.org/wiki/Sprite_(computer_graphics)', $links[1]->attr('url'));
            $t->same('http://en.wikipedia.org/wiki/Sprite_(computer_graphics)', ($links[1]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same(['uri'], $links[1]->attr('classes'));
            $t->same('http://foo.example.com/controller/action?parm=value&p2=v2#anchor123', $links[2]->attr('url'));
            $t->same('http://foo.example.com/controller/action?parm=value&p2=v2#anchor123', ($links[2]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same(['uri'], $links[2]->attr('classes'));
            $t->contains('Str "."', $native);
            $t->contains('http://foo.example.com/controller/action?parm=value&p2=v2#anchor123', $native);
        },

    'records selected upstream markdown bare URI query boundary fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(3, count($cases));
            $t->same('Try this query: http://google.com?search=fish&time=hour.', $cases[0]);
            $t->same('http://en.wikipedia.org/wiki/Sprite_(computer_graphics)', $cases[1]);
            $t->same('http://foo.example.com/controller/action?parm=value&p2=v2#anchor123', $cases[2]);
        },

    'maps selected upstream markdown bare URI query hyphen boundary fixture' =>
        static function (TestRunner $t) use ($hyphenFixture, $collectLinks): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($hyphenFixture());
            $links = $collectLinks($document);
            $native = (new NativeWriter())->write($document);

            $t->same(2, count($document->children));
            $t->same(2, count($links));
            $t->same('https://example.org/?anchor=lala-', $links[0]->attr('url'));
            $t->same('https://example.org/?anchor=lala-', ($links[0]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same(['uri'], $links[0]->attr('classes'));
            $t->same('https://example.org/?anchor=-lala', $links[1]->attr('url'));
            $t->same('https://example.org/?anchor=-lala', ($links[1]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same(['uri'], $links[1]->attr('classes'));
            $t->contains('https://example.org/?anchor=lala-', $native);
            $t->contains('https://example.org/?anchor=-lala', $native);
        },

    'maps selected upstream markdown bare URI port path boundary fixture' =>
        static function (TestRunner $t) use ($portPathFixture, $collectLinks): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($portPathFixture());
            $links = $collectLinks($document);
            $native = (new NativeWriter())->write($document);

            $t->same(2, count($document->children));
            $t->same(2, count($links));
            $t->same('http://www.rubyonrails.com:80', $links[0]->attr('url'));
            $t->same('http://www.rubyonrails.com:80', ($links[0]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same(['uri'], $links[0]->attr('classes'));
            $t->same('http://foo.example.com:3000/controller/action+pack', $links[1]->attr('url'));
            $t->same('http://foo.example.com:3000/controller/action+pack', ($links[1]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same(['uri'], $links[1]->attr('classes'));
            $t->contains('http://www.rubyonrails.com:80', $native);
            $t->contains('http://foo.example.com:3000/controller/action+pack', $native);
        },
];
