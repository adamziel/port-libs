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

$infoCases = [
    'php info string' => [
        $document([$codeBlock('echo alpha', ['info' => 'php'])]),
        "``` php\necho alpha\n```",
    ],
    'python info string' => [
        $document([$codeBlock('print("alpha")', ['info' => 'python'])]),
        "``` python\nprint(\"alpha\")\n```",
    ],
    'language with start attribute' => [
        $document([$codeBlock('echo alpha', ['info' => 'php startFrom=7'])]),
        "``` php startFrom=7\necho alpha\n```",
    ],
    'language with number lines flag' => [
        $document([$codeBlock('echo alpha', ['info' => 'php numberLines'])]),
        "``` php numberLines\necho alpha\n```",
    ],
    'raw brace attribute info' => [
        $document([$codeBlock('echo alpha', ['info' => '{.php .numberLines startFrom="4"}'])]),
        "``` {.php .numberLines startFrom=\"4\"}\necho alpha\n```",
    ],
    'tab and newline info normalization' => [
        $document([$codeBlock('echo alpha', ['info' => " php\tstartFrom=4\nnumberLines "])]),
        "``` php startFrom=4 numberLines\necho alpha\n```",
    ],
    'carriage return info normalization' => [
        $document([$codeBlock('echo alpha', ['info' => "php\r\ncaption=review"])]),
        "``` php caption=review\necho alpha\n```",
    ],
    'trimmed info string' => [
        $document([$codeBlock('echo alpha', ['info' => '   bash   '])]),
        "``` bash\necho alpha\n```",
    ],
    'backtick info switches to tilde fence' => [
        $document([$codeBlock('echo alpha', ['info' => 'lang`token'])]),
        "~~~ lang`token\necho alpha\n~~~",
    ],
    'double backtick info switches to tilde fence' => [
        $document([$codeBlock('echo alpha', ['info' => 'lang``token'])]),
        "~~~ lang``token\necho alpha\n~~~",
    ],
    'tilde fence option honors info' => [
        $document([$codeBlock('echo alpha', ['info' => 'php'])]),
        "~~~ php\necho alpha\n~~~",
        ['fencedCodeBlockStyle' => 'tilde'],
    ],
    'tilde fence option lengthens payload run' => [
        $document([$codeBlock("~~~\nbody", ['info' => 'text'])]),
        "~~~~ text\n~~~\nbody\n~~~~",
        ['fencedCodeBlockStyle' => 'tilde'],
    ],
    'backtick fence lengthens payload run' => [
        $document([$codeBlock("```\nbody", ['info' => 'text'])]),
        "```` text\n```\nbody\n````",
    ],
    'backtick info with tilde payload lengthens tilde fence' => [
        $document([$codeBlock("~~~\nbody", ['info' => 'lang`token'])]),
        "~~~~ lang`token\n~~~\nbody\n~~~~",
    ],
    'punctuated c plus plus info' => [
        $document([$codeBlock('int main() {}', ['info' => 'c++#snippet'])]),
        "``` c++#snippet\nint main() {}\n```",
    ],
    'double quoted info attribute' => [
        $document([$codeBlock('echo alpha', ['info' => 'php caption="Demo"'])]),
        "``` php caption=\"Demo\"\necho alpha\n```",
    ],
    'single quoted info attribute' => [
        $document([$codeBlock('echo alpha', ['info' => "bash title='Demo'"])]),
        "``` bash title='Demo'\necho alpha\n```",
    ],
    'integer info value' => [
        $document([$codeBlock('echo alpha', ['info' => 42])]),
        "``` 42\necho alpha\n```",
    ],
    'zero info value' => [
        $document([$codeBlock('echo alpha', ['info' => 0])]),
        "``` 0\necho alpha\n```",
    ],
    'float info value' => [
        $document([$codeBlock('echo alpha', ['info' => 3.5])]),
        "``` 3.5\necho alpha\n```",
    ],
    'true info value' => [
        $document([$codeBlock('echo alpha', ['info' => true])]),
        "``` 1\necho alpha\n```",
    ],
    'false info value stays indented' => [
        $document([$codeBlock('echo alpha', ['info' => false])]),
        '    echo alpha',
    ],
    'null info value stays indented' => [
        $document([$codeBlock('echo alpha', ['info' => null])]),
        '    echo alpha',
    ],
    'array info value stays indented' => [
        $document([$codeBlock('echo alpha', ['info' => ['php']])]),
        '    echo alpha',
    ],
    'empty info string stays indented' => [
        $document([$codeBlock('echo alpha', ['info' => ''])]),
        '    echo alpha',
    ],
    'blank info string stays indented' => [
        $document([$codeBlock('echo alpha', ['info' => " \t\n "])]),
        '    echo alpha',
    ],
    'empty code block with info remains fenced' => [
        $document([$codeBlock('', ['info' => 'text'])]),
        "``` text\n\n```",
    ],
    'blank line payload with info remains fenced' => [
        $document([$codeBlock("alpha\n\nbeta", ['info' => 'text'])]),
        "``` text\nalpha\n\nbeta\n```",
    ],
    'trailing spaces payload with info remains fenced' => [
        $document([$codeBlock('alpha  ', ['info' => 'text'])]),
        "``` text\nalpha  \n```",
    ],
    'blockquote info code block' => [
        $document([$blockquote([$codeBlock('echo quote', ['info' => 'php'])])]),
        "> ``` php\n> echo quote\n> ```",
    ],
    'class shorthand overrides legacy info' => [
        $document([$codeBlock('echo alpha', ['info' => 'legacy php', 'classes' => ['php']])]),
        "```php\necho alpha\n```",
    ],
    'tilde class shorthand overrides legacy info' => [
        $document([$codeBlock('echo alpha', ['info' => 'legacy bash', 'classes' => ['bash']])]),
        "~~~bash\necho alpha\n~~~",
        ['fencedCodeBlockStyle' => 'tilde'],
    ],
    'id and class tuple overrides legacy info' => [
        $document([$codeBlock('echo alpha', [
            'info' => 'legacy php',
            'id' => 'src',
            'classes' => ['php'],
        ])]),
        "```{#src .php}\necho alpha\n```",
    ],
    'multi class tuple overrides legacy info' => [
        $document([$codeBlock('echo alpha', [
            'info' => 'legacy php',
            'classes' => ['php', 'numberLines'],
        ])]),
        "```{.php .numberLines}\necho alpha\n```",
    ],
    'key value tuple overrides legacy info' => [
        $document([$codeBlock('echo alpha', [
            'info' => 'legacy php',
            'classes' => ['php'],
            'attributes' => ['data-kind' => 'fixture'],
        ])]),
        "```{.php data-kind=\"fixture\"}\necho alpha\n```",
    ],
    'id only tuple overrides legacy info' => [
        $document([$codeBlock('echo alpha', [
            'info' => 'legacy php',
            'id' => 'src',
        ])]),
        "```{#src}\necho alpha\n```",
    ],
    'raw attribute extension info' => [
        $document([$codeBlock('echo alpha', ['info' => 'markdown+raw_attribute'])]),
        "``` markdown+raw_attribute\necho alpha\n```",
    ],
    'html raw tex info' => [
        $document([$codeBlock('<span>alpha</span>', ['info' => 'html+raw_tex'])]),
        "``` html+raw_tex\n<span>alpha</span>\n```",
    ],
    'commonmark extension info' => [
        $document([$codeBlock('echo alpha', ['info' => 'commonmark_x'])]),
        "``` commonmark_x\necho alpha\n```",
    ],
    'gfm task list info' => [
        $document([$codeBlock('- [x] done', ['info' => 'gfm task_lists'])]),
        "``` gfm task_lists\n- [x] done\n```",
    ],
    'raw attribute tuple info string' => [
        $document([$codeBlock('echo alpha', ['info' => '{.numberLines startFrom="10"}'])]),
        "``` {.numberLines startFrom=\"10\"}\necho alpha\n```",
    ],
    'command argument info string' => [
        $document([$codeBlock('wp post list', ['info' => 'bash --login --noprofile'])]),
        "``` bash --login --noprofile\nwp post list\n```",
    ],
    'mermaid info whitespace collapse' => [
        $document([$codeBlock('graph TD; A-->B;', ['info' => 'mermaid   diagram'])]),
        "``` mermaid diagram\ngraph TD; A-->B;\n```",
    ],
    'graphviz dot info' => [
        $document([$codeBlock('digraph { a -> b }', ['info' => 'graphviz dot'])]),
        "``` graphviz dot\ndigraph { a -> b }\n```",
    ],
    'json lines info' => [
        $document([$codeBlock('{"a":1}', ['info' => 'json lines'])]),
        "``` json lines\n{\"a\":1}\n```",
    ],
    'yaml metadata info' => [
        $document([$codeBlock('title: Alpha', ['info' => 'yaml metadata'])]),
        "``` yaml metadata\ntitle: Alpha\n```",
    ],
    'haskell literate info' => [
        $document([$codeBlock('main = pure ()', ['info' => 'haskell literate'])]),
        "``` haskell literate\nmain = pure ()\n```",
    ],
    'ipynb cell info' => [
        $document([$codeBlock('display(value)', ['info' => 'ipynb cell=code'])]),
        "``` ipynb cell=code\ndisplay(value)\n```",
    ],
    'custom slash info' => [
        $document([$codeBlock('payload', ['info' => 'custom/raw'])]),
        "``` custom/raw\npayload\n```",
    ],
    'latex lhs info' => [
        $document([$codeBlock('\\begin{code}', ['info' => 'latex+lhs'])]),
        "``` latex+lhs\n\\begin{code}\n```",
    ],
];

foreach ($infoCases as $label => $case) {
    $tests['maps upstream markdown writer code info metadata surge ' . $label] =
        static function (TestRunner $t) use ($case): void {
            [$doc, $expected, $options] = [$case[0], $case[1], $case[2] ?? []];
            $t->same($expected, (new MarkdownWriter($options))->write($doc));
        };
}

return $tests;
