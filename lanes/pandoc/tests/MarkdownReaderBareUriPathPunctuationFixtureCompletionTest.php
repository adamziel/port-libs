<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-bare-uri-path-punctuation.md'
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
    'maps upstream markdown bare URI path punctuation fixture' =>
        static function (TestRunner $t) use ($fixture, $collectLinks, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris+raw_html']))->read($fixture());
            $links = $collectLinks($document);
            $native = (new NativeWriter())->write($document);
            $expectedUrls = [
                'http://google.com',
                'http://www.rubyonrails.com/contact;new',
                'http://www.rubyonrails.com/contact;new%20with%20spaces',
                'http://manuals.ruby-on-rails.com/read/chapter.need_a-period/103#page281',
            ];

            $t->same(4, count($document->children));
            $t->same(4, count($links));
            $t->same(['text', 'link', 'text'], $inlineTypes($document->children[0] ?? new AstNode('missing')));
            $t->same('(', ($document->children[0]->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same(').', ($document->children[0]->children[2] ?? new AstNode('missing'))->attr('text'));

            foreach ($expectedUrls as $index => $expectedUrl) {
                $link = $links[$index] ?? new AstNode('missing');

                $t->same('link', $link->type, 'link type ' . (string) $index);
                $t->same($expectedUrl, $link->attr('url'), 'url ' . (string) $index);
                $t->same($expectedUrl, ($link->children[0] ?? new AstNode('missing'))->attr('text'), 'display ' . (string) $index);
                $t->same(['uri'], $link->attr('classes'), 'classes ' . (string) $index);
            }

            $t->contains('Str "("', $native);
            $t->contains('Str ")."', $native);
            $t->contains('http://www.rubyonrails.com/contact;new%20with%20spaces', $native);
            $t->contains('chapter.need_a-period/103#page281', $native);
        },

    'records upstream markdown bare URI path punctuation fixture literals' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(4, count($cases));
            $t->same('(http://google.com).', $cases[0]);
            $t->same('http://www.rubyonrails.com/contact;new', $cases[1]);
            $t->same('http://www.rubyonrails.com/contact;new%20with%20spaces', $cases[2]);
            $t->same('http://manuals.ruby-on-rails.com/read/chapter.need_a-period/103#page281', $cases[3]);
        },
];
