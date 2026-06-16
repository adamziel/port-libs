<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$note = static fn (string $label, array $children): AstNode => new AstNode('note', ['label' => $label], $children);
$heading = static fn (string $value, int $level): AstNode => new AstNode('heading', ['level' => $level], [$text($value)]);
$listItem = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$line = static fn (string $value = ''): AstNode => $value === ''
    ? new AstNode('line')
    : new AstNode('line', [], [$text($value)]);
$lineBlock = static fn (array $lines): AstNode => new AstNode('line_block', [], $lines);
$definitionTerm = static fn (string $value): AstNode => new AstNode('definition_term', [], [$text($value)]);
$definition = static fn (array $children): AstNode => new AstNode('definition', [], $children);
$definitionItem = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([$term], $definitions)
);
$definitionList = static fn (array $items): AstNode => new AstNode('definition_list', [], $items);
$image = static fn (string $alt, string $url): AstNode => new AstNode(
    'image',
    ['url' => $url, 'title' => ''],
    [$text($alt)]
);
$figure = static fn (AstNode $image): AstNode => new AstNode('figure', [], [$image]);
$tableCell = static fn (string $value): AstNode => new AstNode('table_cell', ['text' => $value], [$text($value)]);
$tableRow = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
$table = static function () use ($tableCell, $tableRow): AstNode {
    return new AstNode('table', ['alignments' => ['left', 'right']], [
        new AstNode('table_head', [], [
            $tableRow([$tableCell('Metric'), $tableCell('Value')]),
        ]),
        new AstNode('table_body', [], [
            $tableRow([$tableCell('Probe'), $tableCell('12')]),
        ]),
    ]);
};
$div = static fn (array $children): AstNode => new AstNode('div', [], $children);
$codeBlock = static fn (string $value, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_replace(['text' => $value], $attrs)
);

$indentNoteBody = static function (string $body): string {
    return implode("\n", array_map(
        static fn (string $line): string => $line === '' ? '' : '    ' . $line,
        explode("\n", $body)
    ));
};

$findFirstNote = null;
$findFirstNote = static function (AstNode $node) use (&$findFirstNote): ?AstNode {
    if ($node->type === 'note') {
        return $node;
    }

    foreach ($node->children as $child) {
        $note = $findFirstNote($child);
        if ($note instanceof AstNode) {
            return $note;
        }
    }

    return null;
};

$bodyCases = [];
for ($level = 1; $level <= 6; $level++) {
    $bodyCases['heading level ' . $level] = [
        'node' => $heading('Note heading ' . $level, $level),
        'body' => str_repeat('#', $level) . ' Note heading ' . $level,
        'firstType' => 'heading',
    ];
}

$bodyCases += [
    'bullet list body' => [
        'node' => $bulletList([$listItem([$text('bullet body')])]),
        'body' => '- bullet body',
        'firstType' => 'bullet_list',
        'detached' => false,
    ],
    'ordered list body' => [
        'node' => $orderedList([$listItem([$text('ordered body')])], ['start' => 3]),
        'body' => '3.  ordered body',
        'firstType' => 'ordered_list',
        'detached' => false,
    ],
    'blockquote body' => [
        'node' => $blockquote([$paragraph([$text('quoted body')])]),
        'body' => '> quoted body',
        'firstType' => 'blockquote',
    ],
    'line block body' => [
        'node' => $lineBlock([$line('verse one'), $line('verse two')]),
        'body' => "| verse one\n| verse two",
        'firstType' => 'line_block',
    ],
    'definition list body' => [
        'node' => $definitionList([
            $definitionItem($definitionTerm('Term'), [$definition([$paragraph([$text('Definition')])])]),
        ]),
        'body' => "Term\n:   Definition",
        'firstType' => 'definition_list',
    ],
    'pipe table body' => [
        'node' => $table(),
        'body' => "| Metric | Value |\n|:-----|----:|\n| Probe  |    12 |",
        'firstType' => 'table',
    ],
    'horizontal rule body' => [
        'node' => new AstNode('horizontal_rule'),
        'body' => '* * *',
        'firstType' => 'horizontal_rule',
    ],
    'fenced div body' => [
        'node' => $div([$paragraph([$text('Div body')])]),
        'body' => ":::\nDiv body\n:::",
        'firstType' => 'div',
    ],
    'raw html body' => [
        'node' => new AstNode('raw_html', ['html' => "<section>\nraw body\n</section>"]),
        'body' => "<section>\nraw body\n</section>",
        'firstType' => 'raw_html',
    ],
    'fenced code body' => [
        'node' => $codeBlock('echo note', ['classes' => ['php']]),
        'body' => "```php\necho note\n```",
        'firstType' => 'code_block',
    ],
    'figure body' => [
        'node' => $figure($image('Figure alt', 'figures/note.png')),
        'body' => '![Figure alt](figures/note.png)',
        'firstType' => 'figure',
    ],
];

$contexts = [
    'middle sentence' => ['prefix' => 'Before ', 'suffix' => ' after.'],
    'leading note' => ['prefix' => '', 'suffix' => ' starts the paragraph.'],
    'trailing note' => ['prefix' => 'Paragraph ends with ', 'suffix' => ''],
    'punctuation boundary' => ['prefix' => 'Open(', 'suffix' => ') close.'],
    'adjacent text' => ['prefix' => 'Alpha', 'suffix' => 'Beta'],
];

$tests = [];
$caseNumber = 0;

foreach ($bodyCases as $bodyName => $bodyCase) {
    foreach ($contexts as $contextName => $context) {
        $caseNumber++;
        $label = 'block-harvest-' . str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT);
        $inlines = [];
        if ($context['prefix'] !== '') {
            $inlines[] = $text($context['prefix']);
        }
        $inlines[] = $note($label, [$bodyCase['node']]);
        if ($context['suffix'] !== '') {
            $inlines[] = $text($context['suffix']);
        }

        $definitionPrefix = "\n\n[^" . $label . ']:';
        if (($bodyCase['detached'] ?? true) === false) {
            $lines = explode("\n", $bodyCase['body']);
            $first = array_shift($lines);
            $expected = $context['prefix'] . '[^' . $label . ']' . $context['suffix']
                . $definitionPrefix . ' ' . $first;
            foreach ($lines as $line) {
                $expected .= "\n" . ($line === '' ? '' : '    ' . $line);
            }
        } else {
            $expected = $context['prefix'] . '[^' . $label . ']' . $context['suffix']
                . $definitionPrefix . "\n"
                . $indentNoteBody($bodyCase['body']);
        }
        $input = $document([$paragraph($inlines)]);

        $tests['maps upstream markdown writer note definition block harvest '
            . str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT)
            . ' ' . $bodyName . ' ' . $contextName] =
            static function (TestRunner $t) use ($input, $expected, $findFirstNote, $bodyCase): void {
                $markdown = (new MarkdownWriter())->write($input);

                $t->same($expected, $markdown);

                $roundTrip = (new MarkdownReader())->read($markdown);
                $roundTripNote = $findFirstNote($roundTrip);
                $t->true($roundTripNote instanceof AstNode, 'Expected note after Markdown writer/reader round trip');
                if (!$roundTripNote instanceof AstNode) {
                    return;
                }

                $firstBlock = $roundTripNote->children[0] ?? new AstNode('missing');
                $t->same($bodyCase['firstType'], $firstBlock->type);
            };
    }
}

$tests['records markdown writer note definition block harvest mapped-case count'] =
    static function (TestRunner $t) use ($caseNumber): void {
        $t->same(85, $caseNumber);
    };

return $tests;
