<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
$paragraphNodes = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$heading = static fn (string $value, int $level = 1, array $attrs = []): AstNode => new AstNode(
    'heading',
    array_replace(['level' => $level], $attrs),
    [$text($value)]
);
$codeBlock = static fn (string $value, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_replace(['text' => $value], $attrs)
);
$rawBlock = static fn (string $type, string $value, string $attr = 'text'): AstNode => new AstNode($type, [$attr => $value]);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$textItem = static fn (string $value, array $attrs = []): AstNode => $listItem([$text($value)], $attrs);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$definition = static fn (array $children, array $attrs = []): AstNode => new AstNode('definition', $attrs, $children);
$definitionItem = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([$term], $definitions)
);
$definitionTerm = static fn (array $children): AstNode => new AstNode('definition_term', [], $children);
$definitionList = static fn (array $items): AstNode => new AstNode('definition_list', [], $items);
$line = static fn (string $value = ''): AstNode => $value === ''
    ? new AstNode('line')
    : new AstNode('line', [], [$text($value)]);
$lineBlock = static fn (array $lines): AstNode => new AstNode('line_block', [], $lines);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$div = static fn (array $children = [], array $attrs = []): AstNode => new AstNode('div', $attrs, $children);

$cases = [
    'atx heading level one' => [
        $document([$heading('Import packet')]),
        '# Import packet',
    ],
    'atx heading clamps deep levels' => [
        $document([$heading('Deep packet', 8)]),
        '###### Deep packet',
    ],
    'setext heading level one' => [
        $document([$heading('Import packet')]),
        "Import packet\n=============",
        ['setextHeadings' => true],
    ],
    'setext heading level two' => [
        $document([$heading('Review queue', 2)]),
        "Review queue\n------------",
        ['setextHeadings' => true],
    ],
    'heading attributes remain pandoc tuple' => [
        $document([$heading('Import packet', 3, [
            'id' => 'import',
            'classes' => ['review'],
            'attributes' => ['data-kind' => 'packet'],
        ])]),
        '### Import packet {#import .review data-kind="packet"}',
    ],
    'plain block emits paragraph text' => [
        $document([$plain('Plain handoff')]),
        'Plain handoff',
    ],
    'paragraph preserves soft and hard breaks' => [
        $document([$paragraphNodes([
            $text('Source'),
            new AstNode('softbreak'),
            $text('continues'),
            new AstNode('linebreak'),
            $text('now'),
        ])]),
        "Source\ncontinues\\\nnow",
    ],
    'paragraph softbreak space option' => [
        $document([$paragraphNodes([
            $text('Source'),
            new AstNode('softbreak'),
            $text('continues'),
        ])]),
        'Source continues',
        ['softBreak' => 'space'],
    ],
    'blockquote paragraph' => [
        $document([$blockquote([$paragraph('Quoted review')])]),
        '> Quoted review',
    ],
    'blockquote nested blockquote' => [
        $document([$blockquote([
            $paragraph('Outer'),
            $blockquote([$paragraph('Inner')]),
        ])]),
        "> Outer\n>\n> > Inner",
    ],
    'blockquote list body' => [
        $document([$blockquote([
            $paragraph('Quote'),
            $bulletList([$textItem('point')]),
        ])]),
        "> Quote\n>\n> - point",
    ],
    'blockquote code body' => [
        $document([$blockquote([$codeBlock('echo quote')])]),
        '>     echo quote',
    ],
    'horizontal rule block' => [
        $document([new AstNode('horizontal_rule')]),
        '* * *',
    ],
    'raw html block' => [
        $document([$rawBlock('raw_html', "<section>\nbody\n</section>")]),
        "<section>\nbody\n</section>",
    ],
    'raw markdown block' => [
        $document([$rawBlock('raw_markdown', "::: note\nbody\n:::")]),
        "::: note\nbody\n:::",
    ],
    'raw tex block' => [
        $document([$rawBlock('raw_tex', "\\begin{note}\nbody\n\\end{note}")]),
        "\\begin{note}\nbody\n\\end{note}",
    ],
    'raw block markdown format' => [
        $document([new AstNode('raw_block', [
            'format' => 'markdown',
            'text' => "::: review\nbody\n:::",
        ])]),
        "::: review\nbody\n:::",
    ],
    'raw block latex format' => [
        $document([new AstNode('raw_block', [
            'format' => 'latex',
            'text' => "\\begin{review}\nbody\n\\end{review}",
        ])]),
        "\\begin{review}\nbody\n\\end{review}",
    ],
    'indented code single line' => [
        $document([$codeBlock('echo alpha')]),
        '    echo alpha',
    ],
    'indented code multi line' => [
        $document([$codeBlock("echo alpha\necho beta")]),
        "    echo alpha\n    echo beta",
    ],
    'indented code blank line' => [
        $document([$codeBlock("alpha\n\nbeta")]),
        "    alpha\n    \n    beta",
    ],
    'fenced code language shorthand' => [
        $document([$codeBlock('echo alpha', ['classes' => ['php']])]),
        "```php\necho alpha\n```",
    ],
    'fenced code tilde language shorthand' => [
        $document([$codeBlock('echo alpha', ['classes' => ['bash']])]),
        "~~~bash\necho alpha\n~~~",
        ['fencedCodeBlockStyle' => 'tilde'],
    ],
    'fenced code lengthens backtick run' => [
        $document([$codeBlock("alpha\n```\nbeta", ['classes' => ['php']])]),
        "````php\nalpha\n```\nbeta\n````",
    ],
    'fenced code lengthens tilde run' => [
        $document([$codeBlock('alpha ~~~ beta', ['classes' => ['text']])]),
        "~~~~text\nalpha ~~~ beta\n~~~~",
        ['fencedCodeBlockStyle' => 'tilde'],
    ],
    'fenced code keeps id and class tuple' => [
        $document([$codeBlock('echo alpha', [
            'id' => 'src',
            'classes' => ['php'],
        ])]),
        "```{#src .php}\necho alpha\n```",
    ],
    'fenced code keeps multi class tuple' => [
        $document([$codeBlock('echo alpha', [
            'classes' => ['php', 'numberLines'],
        ])]),
        "```{.php .numberLines}\necho alpha\n```",
    ],
    'fenced code keeps key value tuple' => [
        $document([$codeBlock('echo alpha', [
            'classes' => ['php'],
            'attributes' => ['data-kind' => 'fixture'],
        ])]),
        "```{.php data-kind=\"fixture\"}\necho alpha\n```",
    ],
    'fenced code keeps id class and attribute tuple' => [
        $document([$codeBlock('echo alpha', [
            'id' => 'src',
            'classes' => ['php', 'numberLines'],
            'attributes' => ['data-kind' => 'fixture'],
        ])]),
        "```{#src .php .numberLines data-kind=\"fixture\"}\necho alpha\n```",
    ],
    'bullet list dash marker' => [
        $document([$bulletList([$textItem('alpha'), $textItem('beta')])]),
        "- alpha\n- beta",
    ],
    'bullet list plus marker option' => [
        $document([$bulletList([$textItem('alpha'), $textItem('beta')])]),
        "+ alpha\n+ beta",
        ['bulletListMarker' => 'plus'],
    ],
    'bullet list star marker option' => [
        $document([$bulletList([$textItem('alpha'), $textItem('beta')])]),
        "* alpha\n* beta",
        ['bulletListMarker' => 'star'],
    ],
    'nested bullet list indentation' => [
        $document([$bulletList([
            $listItem([
                $text('alpha'),
                $bulletList([$textItem('beta')]),
            ]),
        ])]),
        "- alpha\n  - beta",
    ],
    'bullet task list states' => [
        $document([$bulletList([
            $textItem('todo', ['taskChecked' => false]),
            $textItem('done', ['taskChecked' => true]),
        ])]),
        "- [ ] todo\n- [x] done",
    ],
    'bullet item second paragraph continuation' => [
        $document([$bulletList([
            $listItem([$paragraph('alpha'), $paragraph('beta')]),
        ])]),
        "- alpha\n\n  beta",
    ],
    'bullet item nested code continuation' => [
        $document([$bulletList([
            $listItem([$text('alpha'), $codeBlock('echo alpha')]),
        ])]),
        "- alpha\n      echo alpha",
    ],
    'bullet loose list spacing' => [
        $document([$bulletList([$textItem('one'), $textItem('two')], ['loose' => true])]),
        "- one\n\n- two",
    ],
    'bullet item loose spacing' => [
        $document([$bulletList([
            $textItem('one'),
            $textItem('two', ['loose' => true]),
            $textItem('three'),
        ])]),
        "- one\n\n- two\n- three",
    ],
    'ordered decimal list' => [
        $document([$orderedList([$textItem('one'), $textItem('two')])]),
        "1.  one\n2.  two",
    ],
    'ordered decimal start offset' => [
        $document([$orderedList([$textItem('three'), $textItem('four')], ['start' => 3])]),
        "3.  three\n4.  four",
    ],
    'ordered one paren delimiter' => [
        $document([$orderedList([$textItem('one'), $textItem('two')], ['delimiter' => 'one_paren'])]),
        "1)  one\n2)  two",
    ],
    'ordered two parens delimiter' => [
        $document([$orderedList([$textItem('one')], ['delimiter' => 'two_parens'])]),
        '(1) one',
    ],
    'ordered lower alpha rollover' => [
        $document([$orderedList([$textItem('alpha')], ['style' => 'lower_alpha', 'start' => 27])]),
        'aa. alpha',
    ],
    'ordered upper alpha rollover' => [
        $document([$orderedList([$textItem('upper')], [
            'style' => 'upper_alpha',
            'delimiter' => 'one_paren',
            'start' => 28,
        ])]),
        'AB) upper',
    ],
    'ordered lower roman start' => [
        $document([$orderedList([$textItem('four')], ['style' => 'lower_roman', 'start' => 4])]),
        'iv. four',
    ],
    'ordered upper roman two parens' => [
        $document([$orderedList([$textItem('nine')], [
            'style' => 'upper_roman',
            'delimiter' => 'two_parens',
            'start' => 9,
        ])]),
        '(IX) nine',
    ],
    'inline code inside list item' => [
        $document([$bulletList([
            $listItem([new AstNode('code', ['text' => 'wp cli'])]),
        ])]),
        '- `wp cli`',
    ],
    'top level list then indented code separator' => [
        $document([
            $bulletList([$textItem('alpha')]),
            $codeBlock('echo alpha'),
        ]),
        "- alpha\n\n<!-- -->\n\n    echo alpha",
    ],
    'same ordered list separator' => [
        $document([
            $orderedList([$textItem('one')]),
            $orderedList([$textItem('two')]),
        ]),
        "1.  one\n\n<!-- -->\n\n1.  two",
    ],
    'different ordered style avoids separator' => [
        $document([
            $orderedList([$textItem('one')]),
            $orderedList([$textItem('alpha')], ['style' => 'lower_alpha']),
        ]),
        "1.  one\n\na.  alpha",
    ],
    'definition list simple item' => [
        $document([$definitionList([
            $definitionItem($definitionTerm([$text('Term')]), [
                $definition([$paragraph('Definition')]),
            ]),
        ])]),
        "Term\n:   Definition",
    ],
    'definition list term line break' => [
        $document([$definitionList([
            $definitionItem($definitionTerm([
                $text('Primary'),
                new AstNode('linebreak'),
                $text('Alias'),
            ]), [
                $definition([$paragraph('Definition')]),
            ]),
        ])]),
        "Primary\nAlias\n:   Definition",
    ],
    'definition list two paragraph body' => [
        $document([$definitionList([
            $definitionItem($definitionTerm([$text('Term')]), [
                $definition([$paragraph('First'), $paragraph('Second')]),
            ]),
        ])]),
        "Term\n:   First\n\n    Second",
    ],
    'line block preserves empty line' => [
        $document([$lineBlock([$line('one'), $line(), $line('two')])]),
        "| one\n|\n| two",
    ],
    'line block single line' => [
        $document([$lineBlock([$line('single')])]),
        '| single',
    ],
    'div block without attributes' => [
        $document([$div([$paragraph('Body')])]),
        ":::\nBody\n:::",
    ],
    'div block with class attributes' => [
        $document([$div([$paragraph('Body')], ['classes' => ['review']])]),
        "::: {.review}\nBody\n:::",
    ],
    'div block lengthens colon fence' => [
        $document([$div([$paragraph('alpha ::: beta')])]),
        "::::\nalpha \\::: beta\n::::",
    ],
    'empty div block' => [
        $document([$div()]),
        ":::\n:::",
    ],
    'alert warning div block' => [
        $document([$div([$paragraph('Body')], ['classes' => ['warning']])]),
        "> [!WARNING]\n> Body",
    ],
    'alert note ignores title child' => [
        $document([$div([
            $div([$paragraph('Title')], ['classes' => ['title']]),
            $paragraph('Body'),
        ], ['classes' => ['note']])]),
        "> [!NOTE]\n> Body",
    ],
];

$tests = [];
foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer block list code surge ' . $label] =
        static function (TestRunner $t) use ($case): void {
            [$doc, $expected, $options] = [$case[0], $case[1], $case[2] ?? []];
            $t->same($expected, (new MarkdownWriter($options))->write($doc));
        };
}

$roundTripCases = [
    'code language shorthand parses to code class' => [
        $document([$codeBlock('echo alpha', ['classes' => ['php']])]),
        static function (TestRunner $t, AstNode $roundTrip): void {
            $block = $roundTrip->children[0] ?? null;
            $t->same('code_block', $block?->type);
            $t->same(['php'], $block?->attr('classes'));
        },
    ],
    'tilde code language shorthand parses to code class' => [
        $document([$codeBlock('echo alpha', ['classes' => ['bash']])]),
        static function (TestRunner $t, AstNode $roundTrip): void {
            $block = $roundTrip->children[0] ?? null;
            $t->same('code_block', $block?->type);
            $t->same(['bash'], $block?->attr('classes'));
        },
        ['fencedCodeBlockStyle' => 'tilde'],
    ],
    'task list states survive writer reader handoff' => [
        $document([$bulletList([
            $textItem('todo', ['taskChecked' => false]),
            $textItem('done', ['taskChecked' => true]),
        ])]),
        static function (TestRunner $t, AstNode $roundTrip): void {
            $items = $roundTrip->children[0]->children ?? [];
            $t->same(false, $items[0]->attr('taskChecked'));
            $t->same(true, $items[1]->attr('taskChecked'));
        },
    ],
    'ordered start offset survives writer reader handoff' => [
        $document([$orderedList([$textItem('three')], ['start' => 3])]),
        static function (TestRunner $t, AstNode $roundTrip): void {
            $list = $roundTrip->children[0] ?? null;
            $t->same('ordered_list', $list?->type);
            $t->same('decimal', $list?->attr('style'));
            $t->same(3, $list?->attr('start'));
        },
    ],
];

foreach ($roundTripCases as $label => $case) {
    $tests['maps upstream markdown writer block list code surge roundtrip ' . $label] =
        static function (TestRunner $t) use ($case): void {
            [$doc, $assert, $options] = [$case[0], $case[1], $case[2] ?? []];
            $markdown = (new MarkdownWriter($options))->write($doc);
            $roundTrip = (new MarkdownReader())->read($markdown);
            $assert($t, $roundTrip);
        };
}

return $tests;
