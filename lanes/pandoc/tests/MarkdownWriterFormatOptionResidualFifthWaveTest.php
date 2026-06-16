<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$definitionTerm = static fn (string $value): AstNode => new AstNode('definition_term', [], [$text($value)]);
$definition = static fn (string $value): AstNode => new AstNode('definition', [], [$paragraph([$text($value)])]);
$definitionList = static fn (AstNode $term, array $definitions): AstNode => new AstNode('definition_list', [], [
    new AstNode('definition_item', [], array_merge([$term], $definitions)),
]);
$note = static fn (array $attrs, array $children = []): AstNode => new AstNode('note', $attrs, $children);
$citation = static fn (array $attrs): AstNode => new AstNode('citation', $attrs);
$citationGroup = static fn (array $children): AstNode => new AstNode('citation_group', [], $children);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$orderedList = static fn (array $items): AstNode => new AstNode('ordered_list', [], $items);

$profiles = [
    'markdown' => [
        'definition_lists' => true,
        'footnotes' => true,
        'citations' => true,
        'task_lists' => true,
    ],
    'commonmark' => [
        'definition_lists' => false,
        'footnotes' => false,
        'citations' => false,
        'task_lists' => false,
    ],
    'commonmark_x' => [
        'definition_lists' => true,
        'footnotes' => true,
        'citations' => true,
        'task_lists' => true,
    ],
    'gfm' => [
        'definition_lists' => false,
        'footnotes' => false,
        'citations' => false,
        'task_lists' => true,
    ],
    'markdown_github' => [
        'definition_lists' => false,
        'footnotes' => false,
        'citations' => false,
        'task_lists' => true,
    ],
    'markdown_mmd' => [
        'definition_lists' => true,
        'footnotes' => true,
        'citations' => true,
        'task_lists' => false,
    ],
    'markdown_phpextra' => [
        'definition_lists' => true,
        'footnotes' => true,
        'citations' => false,
        'task_lists' => false,
    ],
    'markdown_strict' => [
        'definition_lists' => false,
        'footnotes' => false,
        'citations' => false,
        'task_lists' => false,
    ],
];

$case = static fn (AstNode $doc, string $expected, string $format): array => [
    'document' => $doc,
    'expected' => $expected,
    'options' => ['format' => $format],
];

$cases = [];

foreach ($profiles as $format => $enabled) {
    $definitionSyntax = $enabled['definition_lists'];
    $footnoteSyntax = $enabled['footnotes'];
    $citationSyntax = $enabled['citations'];
    $taskSyntax = $enabled['task_lists'];

    $cases["{$format} simple definition list profile gate"] = $case(
        $document([$definitionList($definitionTerm('Term'), [$definition('Definition')])]),
        $definitionSyntax
            ? "Term\n:   Definition"
            : '<dl><dt>Term</dt><dd><p>Definition</p></dd></dl>',
        $format
    );

    $cases["{$format} multi definition list profile gate"] = $case(
        $document([$definitionList($definitionTerm('Term'), [$definition('First'), $definition('Second')])]),
        $definitionSyntax
            ? "Term\n:   First\n:   Second"
            : '<dl><dt>Term</dt><dd><p>First</p></dd><dd><p>Second</p></dd></dl>',
        $format
    );

    $cases["{$format} labeled footnote profile gate"] = $case(
        $document([$paragraph([
            $text('Note'),
            $note(['label' => 'profile'], [$paragraph([$text('Body')])]),
        ])]),
        $footnoteSyntax
            ? "Note[^profile]\n\n[^profile]: Body"
            : 'Note<span class="footnote">Body</span>',
        $format
    );

    $cases["{$format} empty footnote profile gate"] = $case(
        $document([$paragraph([
            $text('Empty'),
            $note([]),
        ])]),
        $footnoteSyntax
            ? "Empty[^1]\n\n[^1]:"
            : 'Empty<span class="footnote"></span>',
        $format
    );

    $cases["{$format} single citation profile gate"] = $case(
        $document([$paragraph([
            $citation(['id' => 'doe2026']),
        ])]),
        $citationSyntax
            ? '[@doe2026]'
            : '<span class="citation" data-cites="doe2026">[@doe2026]</span>',
        $format
    );

    $cases["{$format} author citation profile gate"] = $case(
        $document([$paragraph([
            $citation(['id' => 'doe2026', 'mode' => 'author_in_text', 'locator' => 'p. 7']),
        ])]),
        $citationSyntax
            ? '@doe2026, p. 7'
            : '<span class="citation" data-cites="doe2026">@doe2026, p. 7</span>',
        $format
    );

    $cases["{$format} citation group profile gate"] = $case(
        $document([$paragraph([
            $citationGroup([
                $citation(['id' => 'doe2026', 'prefix' => 'see']),
                $citation(['id' => 'roe2025', 'mode' => 'suppress_author', 'locator' => 'sec. 2']),
            ]),
        ])]),
        $citationSyntax
            ? '[see @doe2026; -@roe2025, sec. 2]'
            : '<span class="citation" data-cites="doe2026 roe2025">[see @doe2026; -@roe2025, sec. 2]</span>',
        $format
    );

    $cases["{$format} unchecked task profile gate"] = $case(
        $document([$bulletList([
            $listItem([$text('Todo')], ['taskChecked' => false]),
        ])]),
        $taskSyntax
            ? '- [ ] Todo'
            : '<ul class="task-list"><li><input type="checkbox" />Todo</li></ul>',
        $format
    );

    $cases["{$format} checked ordered task profile gate"] = $case(
        $document([$orderedList([
            $listItem([$text('Done')], ['taskChecked' => true]),
        ])]),
        $taskSyntax
            ? '1.  [x] Done'
            : '<ol><li><input type="checkbox" checked="" />Done</li></ol>',
        $format
    );
}

$overrideCases = [
    'commonmark definition lists override keeps syntax' => $case(
        $document([$definitionList($definitionTerm('Term'), [$definition('Definition')])]),
        "Term\n:   Definition",
        'commonmark+definition_lists'
    ),
    'commonmark footnotes override keeps syntax' => $case(
        $document([$paragraph([$text('Note'), $note(['label' => 'cm'], [$paragraph([$text('Body')])])])]),
        "Note[^cm]\n\n[^cm]: Body",
        'commonmark+footnotes'
    ),
    'commonmark citations override keeps syntax' => $case(
        $document([$paragraph([$citation(['id' => 'doe2026'])])]),
        '[@doe2026]',
        'commonmark+citations'
    ),
    'commonmark task lists override keeps syntax' => $case(
        $document([$bulletList([$listItem([$text('Todo')], ['taskChecked' => false])])]),
        '- [ ] Todo',
        'commonmark+task_lists'
    ),
    'markdown definition lists override falls back' => $case(
        $document([$definitionList($definitionTerm('Term'), [$definition('Definition')])]),
        '<dl><dt>Term</dt><dd><p>Definition</p></dd></dl>',
        'markdown-definition_lists'
    ),
    'markdown footnotes override falls back' => $case(
        $document([$paragraph([$text('Note'), $note(['label' => 'md'], [$paragraph([$text('Body')])])])]),
        'Note<span class="footnote">Body</span>',
        'markdown-footnotes'
    ),
    'markdown citations override falls back' => $case(
        $document([$paragraph([$citation(['id' => 'doe2026'])])]),
        '<span class="citation" data-cites="doe2026">[@doe2026]</span>',
        'markdown-citations'
    ),
    'markdown task lists override falls back' => $case(
        $document([$orderedList([$listItem([$text('Done')], ['taskChecked' => true])])]),
        '<ol><li><input type="checkbox" checked="" />Done</li></ol>',
        'markdown-task_lists'
    ),
    'gfm definition lists override keeps syntax' => $case(
        $document([$definitionList($definitionTerm('Term'), [$definition('Definition')])]),
        "Term\n:   Definition",
        'gfm+definition_lists'
    ),
    'gfm footnotes override keeps syntax' => $case(
        $document([$paragraph([$text('Note'), $note(['label' => 'gfm'], [$paragraph([$text('Body')])])])]),
        "Note[^gfm]\n\n[^gfm]: Body",
        'gfm+footnotes'
    ),
    'gfm citations override keeps syntax' => $case(
        $document([$paragraph([$citation(['id' => 'doe2026'])])]),
        '[@doe2026]',
        'gfm+citations'
    ),
    'gfm task lists override falls back' => $case(
        $document([$bulletList([$listItem([$text('Todo')], ['taskChecked' => false])])]),
        '<ul class="task-list"><li><input type="checkbox" />Todo</li></ul>',
        'gfm-task_lists'
    ),
    'markdown phpextra citations override keeps syntax' => $case(
        $document([$paragraph([$citation(['id' => 'doe2026'])])]),
        '[@doe2026]',
        'markdown_phpextra+citations'
    ),
    'markdown mmd citations override falls back' => $case(
        $document([$paragraph([$citation(['id' => 'doe2026'])])]),
        '<span class="citation" data-cites="doe2026">[@doe2026]</span>',
        'markdown_mmd-citations'
    ),
    'markdown strict footnotes override keeps syntax' => $case(
        $document([$paragraph([$text('Note'), $note(['label' => 'strict'], [$paragraph([$text('Body')])])])]),
        "Note[^strict]\n\n[^strict]: Body",
        'markdown_strict+footnotes'
    ),
    'markdown strict task lists override keeps syntax' => $case(
        $document([$bulletList([$listItem([$text('Todo')], ['taskChecked' => false])])]),
        '- [ ] Todo',
        'markdown_strict+task_lists'
    ),
];

foreach ($overrideCases as $label => $item) {
    $cases['extension override ' . $label] = $item;
}

$tests = [
    'records markdown writer format option residual fifth wave mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(88, count($cases));
    },
];

foreach ($cases as $label => $item) {
    $tests['maps upstream markdown writer format option residual fifth wave ' . $label] =
        static function (TestRunner $t) use ($item): void {
            $markdown = (new MarkdownWriter($item['options']))->write($item['document']);

            $t->same($item['expected'], $markdown);
        };
}

return $tests;
