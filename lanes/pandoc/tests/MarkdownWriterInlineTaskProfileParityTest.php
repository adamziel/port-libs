<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$note = static fn (array $attrs, array $children = []): AstNode => new AstNode('note', $attrs, $children);
$citation = static fn (array $attrs): AstNode => new AstNode('citation', $attrs);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);

$cases = [
    'commonmark footnotes fall back to inline html without queued definitions' => [
        'options' => ['format' => 'commonmark'],
        'document' => $document([
            $paragraph([
                $text('Note'),
                $note(['label' => 'profile'], [$paragraph([$text('Body')])]),
            ]),
        ]),
        'expected' => 'Note<span class="footnote">Body</span>',
    ],
    'commonmark plus footnotes keeps markdown syntax' => [
        'options' => ['format' => 'commonmark+footnotes'],
        'document' => $document([
            $paragraph([
                $text('Note'),
                $note(['label' => 'profile'], [$paragraph([$text('Body')])]),
            ]),
        ]),
        'expected' => "Note[^profile]\n\n[^profile]: Body",
    ],
    'gfm citations fall back to data-cites html' => [
        'options' => ['format' => 'gfm'],
        'document' => $document([$paragraph([$citation(['id' => 'doe2026'])])]),
        'expected' => '<span class="citation" data-cites="doe2026">[@doe2026]</span>',
    ],
    'gfm plus citations keeps markdown citation syntax' => [
        'options' => ['format' => 'gfm+citations'],
        'document' => $document([$paragraph([$citation(['id' => 'doe2026'])])]),
        'expected' => '[@doe2026]',
    ],
    'markdown mmd minus citations falls back to html' => [
        'options' => ['format' => 'markdown_mmd-citations'],
        'document' => $document([$paragraph([$citation(['id' => 'doe2026', 'mode' => 'author_in_text', 'locatorLabel' => 'page', 'locatorValue' => '9'])])]),
        'expected' => '<span class="citation" data-cites="doe2026">@doe2026, p. 9</span>',
    ],
    'commonmark task lists fall back to checkbox html' => [
        'options' => ['format' => 'commonmark'],
        'document' => $document([$bulletList([
            $listItem([$text('Todo')], ['taskChecked' => false]),
        ])]),
        'expected' => '<ul class="task-list"><li><input type="checkbox" />Todo</li></ul>',
    ],
    'commonmark plus task lists keeps markdown task syntax' => [
        'options' => ['format' => 'commonmark+task_lists'],
        'document' => $document([$bulletList([
            $listItem([$text('Todo')], ['taskChecked' => false]),
        ])]),
        'expected' => '- [ ] Todo',
    ],
    'gfm minus task lists falls back to checkbox html' => [
        'options' => ['format' => 'gfm-task_lists'],
        'document' => $document([$orderedList([
            $listItem([$text('Done')], ['taskChecked' => true]),
        ])]),
        'expected' => '<ol><li><input type="checkbox" checked="" />Done</li></ol>',
    ],
    'explicit html ordered list preserves source type start and item value' => [
        'options' => [],
        'document' => $document([$orderedList([
            $listItem([$text('seven')], ['number' => 7]),
        ], ['markdownListFormat' => 'html', 'style' => 'upper_alpha', 'start' => 3])]),
        'expected' => '<ol start="3" type="A"><li value="7">seven</li></ol>',
    ],
];

$tests = [
    'records markdown writer inline task profile parity case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(9, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps markdown writer inline task profile parity ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
