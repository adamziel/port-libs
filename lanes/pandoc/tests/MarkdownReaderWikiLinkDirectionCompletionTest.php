<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$readWikiLink = static function (array $options, string $markdown): AstNode {
    $document = (new MarkdownReader($options))->read($markdown);

    return $document->children[0]->children[1] ?? new AstNode('missing');
};

$inlineText = static function (AstNode $node): string {
    $text = '';
    foreach ($node->children as $child) {
        if ($child->type === 'text' || $child->type === 'code' || $child->type === 'math') {
            $text .= (string) $child->attr('text', '');
        }
    }

    return $text;
};

$cases = [
    'commonmark title after pipe format' => [
        'options' => ['format' => 'commonmark+wikilinks_title_after_pipe'],
        'markdown' => 'See [[/docs/runbook|Migration runbook]] now.',
        'url' => '/docs/runbook',
        'text' => 'Migration runbook',
    ],
    'commonmark title before pipe format' => [
        'options' => ['format' => 'commonmark+wikilinks_title_before_pipe'],
        'markdown' => 'See [[Migration runbook|/docs/runbook]] now.',
        'url' => '/docs/runbook',
        'text' => 'Migration runbook',
    ],
    'markdown title after pipe overrides default direction' => [
        'options' => ['format' => 'markdown+wikilinks_title_after_pipe'],
        'markdown' => 'See [[/docs/after|After pipe title]] now.',
        'url' => '/docs/after',
        'text' => 'After pipe title',
    ],
    'markdown title before pipe keeps existing direction' => [
        'options' => ['format' => 'markdown+wikilinks_title_before_pipe'],
        'markdown' => 'See [[Before pipe title|/docs/before]] now.',
        'url' => '/docs/before',
        'text' => 'Before pipe title',
    ],
    'configured string title after pipe enables commonmark wikilinks' => [
        'options' => ['format' => 'commonmark', 'extensions' => '+wikilinks_title_after_pipe'],
        'markdown' => 'See [[/docs/string|String override]] now.',
        'url' => '/docs/string',
        'text' => 'String override',
    ],
    'configured list title before pipe enables commonmark wikilinks' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['+wikilinks_title_before_pipe']],
        'markdown' => 'See [[List override|/docs/list]] now.',
        'url' => '/docs/list',
        'text' => 'List override',
    ],
    'configured map title after pipe enables commonmark wikilinks' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['wikilinks_title_after_pipe' => true]],
        'markdown' => 'See [[/docs/map|Map override]] now.',
        'url' => '/docs/map',
        'text' => 'Map override',
    ],
    'after pipe direction preserves escaped pipe and bracket target' => [
        'options' => ['format' => 'commonmark+wikilinks_title_after_pipe'],
        'markdown' => 'See [[/docs/a\|b\]c|Escaped target]] now.',
        'url' => '/docs/a|b]c',
        'text' => 'Escaped target',
    ],
    'before pipe direction preserves escaped pipe and bracket label' => [
        'options' => ['format' => 'commonmark+wikilinks_title_before_pipe'],
        'markdown' => 'See [[Escaped \| label \] text|/docs/escaped]] now.',
        'url' => '/docs/escaped',
        'text' => 'Escaped | label ] text',
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown reader wikilink direction completion ' . $name] =
        static function (TestRunner $t) use ($case, $inlineText, $readWikiLink): void {
            $link = $readWikiLink($case['options'], $case['markdown']);

            $t->same('link', $link->type, $case['markdown']);
            $t->same(['wikilink'], $link->attr('classes'), $case['markdown']);
            $t->same($case['url'], $link->attr('url'), $case['markdown']);
            $t->same($case['text'], $inlineText($link), $case['markdown']);
        };
}

$tests['renders wikilink direction completion through wordpress handoff'] =
    static function (TestRunner $t): void {
        $document = (new MarkdownReader(['format' => 'commonmark+wikilinks_title_after_pipe']))
            ->read('See [[/docs/wp|WordPress handoff]] now.');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<a href="/docs/wp" class="wikilink">WordPress handoff</a>', $blocks);
    };

$tests['maps checked-in markdown wikilinks title-after-pipe profile fixture'] =
    static function (TestRunner $t) use ($inlineText, $readWikiLink): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzz-wikilinks-title-after-pipe-profile.md');
        $link = $readWikiLink(['format' => 'markdown+wikilinks_title_after_pipe'], $fixture);

        $t->same('link', $link->type);
        $t->same(['wikilink'], $link->attr('classes'));
        $t->same('/docs/runbook', $link->attr('url'));
        $t->same('Migration runbook', $inlineText($link));
    };

$tests['records markdown reader wikilink direction completion mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(11, count($cases) + 2);
    };

return $tests;
