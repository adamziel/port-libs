<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);
$link = static fn (string $url): AstNode => new AstNode('link', ['url' => $url], [$text($url)]);

$collectLinks = null;
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

$cases = [
    'pandoc markdown keeps nonstandard scheme explicit' => [
        'options' => ['format' => 'markdown'],
        'expected' => '[foo:bar](foo:bar)',
    ],
    'pandoc markdown extension enables commonmark autolink predicate' => [
        'options' => ['format' => 'markdown+commonmark_autolinks'],
        'expected' => '<foo:bar>',
    ],
    'pandoc markdown option enables commonmark autolink predicate' => [
        'options' => ['format' => 'markdown', 'extensions' => ['+commonmark_autolinks']],
        'expected' => '<foo:bar>',
    ],
    'commonmark keeps nonstandard scheme compact' => [
        'options' => ['format' => 'commonmark'],
        'expected' => '<foo:bar>',
    ],
    'commonmark_x keeps nonstandard scheme compact' => [
        'options' => ['format' => 'commonmark_x'],
        'expected' => '<foo:bar>',
    ],
    'gfm keeps nonstandard scheme compact' => [
        'options' => ['format' => 'gfm'],
        'expected' => '<foo:bar>',
    ],
];

$tests = [
    'records markdown writer commonmark autolink parity completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(6, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer commonmark autolink parity completion ' . $label] =
        static function (TestRunner $t) use ($case, $collectLinks, $document, $link): void {
            $markdown = (new MarkdownWriter($case['options']))->write($document([$link('foo:bar')]));

            $t->same($case['expected'], $markdown);

            $roundTrip = (new MarkdownReader($case['options']))->read($markdown);
            $links = $collectLinks($roundTrip);
            $node = $links[0] ?? new AstNode('missing');

            $t->same('link', $node->type);
            $t->same('foo:bar', $node->attr('url'));
        };
}

return $tests;
