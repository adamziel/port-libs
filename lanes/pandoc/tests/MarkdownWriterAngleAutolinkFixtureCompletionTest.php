<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$link = static fn (string $url, string $label, array $attrs = []): AstNode =>
    new AstNode('link', ['url' => $url] + $attrs, [$text($label)]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);

$fixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-angle-autolinks.md');

$collectLinks = null;
$collectLinks = static function (AstNode $node) use (&$collectLinks): array {
    $links = $node->type === 'link' ? [$node] : [];

    foreach ($node->children as $child) {
        array_push($links, ...$collectLinks($child));
    }

    return $links;
};

$cases = [
    'extended URI scheme autolink' => [
        'source' => '<web+demo:review-packet>',
        'document' => $document([
            $link('web+demo:review-packet', 'web+demo:review-packet', ['classes' => ['uri']]),
        ]),
        'options' => ['format' => 'commonmark'],
        'expected' => '<web+demo:review-packet>',
        'urls' => ['web+demo:review-packet'],
    ],
    'URN autolink' => [
        'source' => '<urn:source:review:packet>',
        'document' => $document([
            $link('urn:source:review:packet', 'urn:source:review:packet', ['classes' => ['uri']]),
        ]),
        'expected' => '<urn:source:review:packet>',
        'urls' => ['urn:source:review:packet'],
    ],
    'mailto URI autolink keeps scheme' => [
        'source' => '<mailto:editor@example.test>',
        'document' => $document([
            $link('mailto:editor@example.test', 'mailto:editor@example.test', ['classes' => ['uri']]),
        ]),
        'expected' => '<mailto:editor@example.test>',
        'urls' => ['mailto:editor@example.test'],
    ],
    'email autolink strips mailto target' => [
        'source' => '<review@example-domain.test>',
        'document' => $document([
            $link('mailto:review@example-domain.test', 'review@example-domain.test', ['classes' => ['email']]),
        ]),
        'expected' => '<review@example-domain.test>',
        'urls' => ['mailto:review@example-domain.test'],
    ],
    'invalid angle text and bare www fallback' => [
        'source' => '<bad https://example.test/a> and www.outside.test',
        'document' => $document([
            $text('<bad https://example.test/a> and '),
            $link('http://www.outside.test', 'www.outside.test', ['classes' => ['uri']]),
        ]),
        'expected' => '\\<bad https://example.test/a\\> and [www.outside.test](http://www.outside.test){.uri}',
        'urls' => ['http://www.outside.test'],
    ],
];

$tests = [
    'records markdown writer angle autolink fixture completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(5, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer angle autolink fixture completion ' . $label] =
        static function (TestRunner $t) use ($case, $collectLinks, $fixture, $label): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['document']);

            $t->contains($case['source'], $fixture(), $label . ' source fixture line');
            $t->same($case['expected'], $markdown, $label . ' markdown');

            $roundTrip = (new MarkdownReader())->read($markdown);
            $links = $collectLinks($roundTrip);
            $t->same($case['urls'], array_map(
                static fn (AstNode $node): string => (string) $node->attr('url', ''),
                $links
            ), $label . ' round-trip links');
        };
}

return $tests;
