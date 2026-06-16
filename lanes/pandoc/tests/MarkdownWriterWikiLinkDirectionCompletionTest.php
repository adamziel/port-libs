<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);
$wikiLink = static fn (string $label, string $url): AstNode => new AstNode(
    'link',
    ['url' => $url, 'classes' => ['wikilink']],
    [$text($label)]
);

$firstLink = static function (AstNode $document): AstNode {
    return $document->children[0]->children[0] ?? new AstNode('missing');
};

$cases = [
    'commonmark title after pipe format' => [
        'options' => ['format' => 'commonmark+wikilinks_title_after_pipe'],
        'label' => 'Migration runbook',
        'url' => '/docs/runbook',
        'expected' => '[[/docs/runbook|Migration runbook]]',
        'readerOptions' => ['format' => 'commonmark+wikilinks_title_after_pipe'],
    ],
    'commonmark title before pipe format' => [
        'options' => ['format' => 'commonmark+wikilinks_title_before_pipe'],
        'label' => 'Migration runbook',
        'url' => '/docs/runbook',
        'expected' => '[[Migration runbook|/docs/runbook]]',
        'readerOptions' => ['format' => 'commonmark+wikilinks_title_before_pipe'],
    ],
    'singular title after pipe alias enables commonmark wikilinks' => [
        'options' => ['format' => 'commonmark', 'extensions' => '+wikilink_title_after_pipe'],
        'label' => 'Alias runbook',
        'url' => '/docs/alias',
        'expected' => '[[/docs/alias|Alias runbook]]',
        'readerOptions' => ['format' => 'commonmark+wikilinks_title_after_pipe'],
    ],
    'wiki link alias enables gfm compact target' => [
        'options' => ['format' => 'gfm', 'extensions' => ['+wiki_link']],
        'label' => 'Release packet',
        'url' => 'Release packet',
        'expected' => '[[Release packet]]',
        'readerOptions' => ['format' => 'gfm', 'extensions' => ['+wiki_link']],
    ],
    'title after pipe escapes target and title components' => [
        'options' => ['format' => 'markdown+wikilinks_title_after_pipe'],
        'label' => 'Title | bracket ]',
        'url' => '/docs/a|b]c',
        'expected' => '[[/docs/a\|b\]c|Title \| bracket \]]]',
        'readerOptions' => ['format' => 'markdown+wikilinks_title_after_pipe'],
    ],
    'disabled direction extension falls back to explicit link' => [
        'options' => ['format' => 'markdown-wikilinks_title_after_pipe'],
        'label' => 'Runbook',
        'url' => '/docs/runbook',
        'expected' => '[Runbook](/docs/runbook){.wikilink}',
        'readerOptions' => null,
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown writer wikilink direction completion ' . $name] =
        static function (TestRunner $t) use ($case, $document, $firstLink, $wikiLink): void {
            $markdown = (new MarkdownWriter($case['options']))->write($document([
                $wikiLink($case['label'], $case['url']),
            ]));

            $t->same($case['expected'], $markdown);
            if ($case['readerOptions'] === null) {
                return;
            }

            $roundTrip = (new MarkdownReader($case['readerOptions']))->read($markdown);
            $link = $firstLink($roundTrip);

            $t->same('link', $link->type, $case['expected']);
            $t->same(['wikilink'], $link->attr('classes'), $case['expected']);
            $t->same($case['url'], $link->attr('url'), $case['expected']);
            $t->same($case['label'], $link->children[0]->attr('text'), $case['expected']);
            $t->same($case['expected'], (new MarkdownWriter($case['options']))->write($roundTrip));
        };
}

$tests['records markdown writer wikilink direction completion mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(6, count($cases));
    };

return $tests;
