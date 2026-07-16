<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzz-bare-uri-scheme-boundaries.md'
);

$unicodePathFixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-bare-uri-unicode-path.md'
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

$paragraphText = static function (AstNode $node) use (&$paragraphText): string {
    if ($node->type === 'text') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'space' || $node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $paragraphText($child);
    }

    return $text;
};

return [
    'maps selected upstream markdown bare URI scheme boundary fixture' =>
        static function (TestRunner $t) use ($fixture, $collectLinks, $paragraphText): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($fixture());
            $links = $collectLinks($document);
            $native = (new NativeWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(4, count($document->children));
            $t->same(3, count($links));
            $t->same('HTTPS://GOOGLE.COM', $links[0]->attr('url'));
            $t->same('HTTPS://GOOGLE.COM', $links[0]->children[0]->attr('text'));
            $t->same(['uri'], $links[0]->attr('classes'));
            $t->same('doi:10.1000/182', $links[1]->attr('url'));
            $t->same('doi:10.1000/182', $links[1]->children[0]->attr('text'));
            $t->same(['uri'], $links[1]->attr('classes'));
            $t->same('mailto:someone@somedomain.com', $links[2]->attr('url'));
            $t->same('mailto:someone@somedomain.com', $links[2]->children[0]->attr('text'));
            $t->same(['uri'], $links[2]->attr('classes'));
            $t->same('Use http: this is not a link!', $paragraphText($document->children[3] ?? new AstNode('missing')));
            $t->contains('Str ","', $native);
            $t->contains('Str "."', $native);
            $t->contains('href="HTTPS://GOOGLE.COM"', $blocks);
            $t->contains('href="doi:10.1000/182"', $blocks);
            $t->contains('href="mailto:someone@somedomain.com"', $blocks);
        },

    'maps upstream markdown bare URI unicode path fixture' =>
        static function (TestRunner $t) use ($unicodePathFixture, $collectLinks): void {
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($unicodePathFixture());
            $links = $collectLinks($document);
            $blocks = (new WordPressBlockWriter())->write($document);
            $expected = 'http://el.wikipedia.org/wiki/Τεχνολογία';

            $t->same(1, count($document->children));
            $t->same(1, count($links));
            $t->same($expected, $links[0]->attr('url'));
            $t->same($expected, $links[0]->children[0]->attr('text'));
            $t->same(['uri'], $links[0]->attr('classes'));
            $t->same(',', ($document->children[0]->children[1] ?? new AstNode('missing'))->attr('text'));
            $t->contains('href="' . $expected . '"', $blocks);
        },

    'records upstream markdown bare URI unicode path fixture literal' =>
        static function (TestRunner $t) use ($unicodePathFixture): void {
            $t->same('http://el.wikipedia.org/wiki/Τεχνολογία,', trim($unicodePathFixture()));
        },

    'records selected upstream markdown bare URI scheme boundary fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(4, count($cases));
            $t->same('HTTPS://GOOGLE.COM,', $cases[0]);
            $t->same('doi:10.1000/182,', $cases[1]);
            $t->same('mailto:someone@somedomain.com.', $cases[2]);
            $t->same('Use http: this is not a link!', $cases[3]);
        },
];
