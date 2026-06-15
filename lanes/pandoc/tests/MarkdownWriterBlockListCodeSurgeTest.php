<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$listItemText = static function (AstNode $item) use ($inlineText): string {
    $parts = [];
    foreach ($item->children as $child) {
        if ($child->type === 'bullet_list' || $child->type === 'ordered_list' || $child->type === 'definition_list') {
            continue;
        }
        $parts[] = trim($inlineText($child));
    }

    return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
};

$tests = [];

$blankMarkerCases = [
    '01 dash marker owns following indented text' => [
        'markdown' => "-\n  carried paragraph",
        'type' => 'bullet_list',
        'items' => ['carried paragraph'],
    ],
    '02 plus marker owns following indented text' => [
        'markdown' => "+\n  plus continuation",
        'type' => 'bullet_list',
        'items' => ['plus continuation'],
    ],
    '03 star marker owns following indented text' => [
        'markdown' => "*\n  star continuation",
        'type' => 'bullet_list',
        'items' => ['star continuation'],
    ],
    '04 one-space indented blank bullet owns continuation' => [
        'markdown' => " -\n   one-space continuation",
        'type' => 'bullet_list',
        'items' => ['one-space continuation'],
    ],
    '05 two-space indented blank bullet owns continuation' => [
        'markdown' => "  -\n    two-space continuation",
        'type' => 'bullet_list',
        'items' => ['two-space continuation'],
    ],
    '06 three-space indented blank bullet owns continuation' => [
        'markdown' => "   -\n     three-space continuation",
        'type' => 'bullet_list',
        'items' => ['three-space continuation'],
    ],
    '07 blank bullet stays loose across blank continuation' => [
        'markdown' => "-\n\n  after blank",
        'type' => 'bullet_list',
        'items' => ['after blank'],
        'loose' => true,
    ],
    '08 blank bullet owns nested bullet list' => [
        'markdown' => "-\n  - nested bullet",
        'type' => 'bullet_list',
        'items' => [''],
        'nested' => ['type' => 'bullet_list', 'items' => ['nested bullet']],
    ],
    '09 blank bullet owns nested decimal list' => [
        'markdown' => "-\n  1. nested ordered",
        'type' => 'bullet_list',
        'items' => [''],
        'nested' => ['type' => 'ordered_list', 'items' => ['nested ordered'], 'start' => 1],
    ],
    '10 blank bullet owns nested numbered example list' => [
        'markdown' => "-\n  (@) nested example",
        'type' => 'bullet_list',
        'items' => [''],
        'nested' => ['type' => 'ordered_list', 'items' => ['nested example'], 'style' => 'example'],
    ],
    '11 decimal blank marker owns following indented text' => [
        'markdown' => "1.\n   decimal continuation",
        'type' => 'ordered_list',
        'items' => ['decimal continuation'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '12 paren decimal blank marker owns following text' => [
        'markdown' => "1)\n   paren continuation",
        'type' => 'ordered_list',
        'items' => ['paren continuation'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'one_paren',
    ],
    '13 zero padded decimal blank marker owns following text' => [
        'markdown' => "01.\n    zero padded continuation",
        'type' => 'ordered_list',
        'items' => ['zero padded continuation'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    '14 pandoc default period blank marker owns continuation' => [
        'markdown' => "#.\n   default continuation",
        'type' => 'ordered_list',
        'items' => ['default continuation'],
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
    ],
    '15 pandoc default paren blank marker owns continuation' => [
        'markdown' => "#)\n   default paren continuation",
        'type' => 'ordered_list',
        'items' => ['default paren continuation'],
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
    ],
    '16 numbered example blank marker owns continuation' => [
        'markdown' => "(@)\n    example continuation",
        'type' => 'ordered_list',
        'items' => ['example continuation'],
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
    ],
    '17 labeled numbered example blank marker owns continuation' => [
        'markdown' => "(@review)\n          labeled example continuation",
        'type' => 'ordered_list',
        'items' => ['labeled example continuation'],
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
    ],
    '18 parenthesized decimal blank marker owns continuation' => [
        'markdown' => "(1)\n    parenthesized continuation",
        'type' => 'ordered_list',
        'items' => ['parenthesized continuation'],
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'two_parens',
    ],
    '19 parenthesized decimal start two blank marker' => [
        'markdown' => "(2)\n    start two continuation",
        'type' => 'ordered_list',
        'items' => ['start two continuation'],
        'start' => 2,
        'style' => 'decimal',
        'delimiter' => 'two_parens',
    ],
    '20 upper alpha blank marker with pandoc spacing' => [
        'markdown' => "A.  \n    upper alpha continuation",
        'type' => 'ordered_list',
        'items' => ['upper alpha continuation'],
        'start' => 1,
        'style' => 'upper_alpha',
        'delimiter' => 'period',
    ],
    '21 lower alpha paren blank marker with pandoc spacing' => [
        'markdown' => "a)  \n    lower alpha continuation",
        'type' => 'ordered_list',
        'items' => ['lower alpha continuation'],
        'start' => 1,
        'style' => 'lower_alpha',
        'delimiter' => 'one_paren',
    ],
    '22 upper roman blank marker with pandoc spacing' => [
        'markdown' => "I.  \n    upper roman continuation",
        'type' => 'ordered_list',
        'items' => ['upper roman continuation'],
        'start' => 1,
        'style' => 'upper_roman',
        'delimiter' => 'period',
    ],
    '23 lower roman multi-letter blank marker owns continuation' => [
        'markdown' => "iv.\n    lower roman continuation",
        'type' => 'ordered_list',
        'items' => ['lower roman continuation'],
        'start' => 4,
        'style' => 'lower_roman',
        'delimiter' => 'period',
    ],
    '24 consecutive empty bullet markers keep two items' => [
        'markdown' => "-\n-\n  second carried",
        'type' => 'bullet_list',
        'items' => ['', 'second carried'],
    ],
    '25 consecutive empty decimal markers keep two items' => [
        'markdown' => "1.\n2.\n   second carried",
        'type' => 'ordered_list',
        'items' => ['', 'second carried'],
        'start' => 1,
        'style' => 'decimal',
    ],
    '26 blank between empty bullet markers makes list loose' => [
        'markdown' => "-\n\n-\n  second carried",
        'type' => 'bullet_list',
        'items' => ['', 'second carried'],
        'loose' => true,
    ],
    '27 blank marker joins two continuation lines' => [
        'markdown' => "-\n\n  paragraph one\n  paragraph two",
        'type' => 'bullet_list',
        'items' => ['paragraph one paragraph two'],
        'loose' => true,
    ],
    '28 blank marker before hash continuation keeps text in item' => [
        'markdown' => "-\n  # Not a heading here",
        'type' => 'bullet_list',
        'items' => ['Not a heading here'],
    ],
    '29 blank ordered marker owns nested bullet list' => [
        'markdown' => "1.\n   - nested bullet",
        'type' => 'ordered_list',
        'items' => [''],
        'nested' => ['type' => 'bullet_list', 'items' => ['nested bullet']],
        'start' => 1,
    ],
    '30 blank ordered marker owns nested ordered list' => [
        'markdown' => "1.\n   1. nested ordered",
        'type' => 'ordered_list',
        'items' => [''],
        'nested' => ['type' => 'ordered_list', 'items' => ['nested ordered'], 'start' => 1],
        'start' => 1,
    ],
];

foreach ($blankMarkerCases as $name => $case) {
    $tests['maps upstream markdown eol list marker block surge ' . $name] = static function (TestRunner $t) use ($case, $listItemText): void {
        $document = (new MarkdownReader())->read($case['markdown']);
        $list = $document->children[0];

        $t->same($case['type'], $list->type);
        $t->same((bool) ($case['loose'] ?? false), (bool) $list->attr('loose'));
        $t->same($case['items'], array_map($listItemText, $list->children));

        foreach (['start', 'style', 'delimiter'] as $attr) {
            if (array_key_exists($attr, $case)) {
                $t->same($case[$attr], $list->attr($attr));
            }
        }

        if (isset($case['nested'])) {
            $nestedCase = $case['nested'];
            $nested = null;
            foreach ($list->children[0]->children as $child) {
                if ($child->type === $nestedCase['type']) {
                    $nested = $child;
                    break;
                }
            }

            $t->true($nested instanceof AstNode, 'Expected nested list under blank-marker item');
            if ($nested instanceof AstNode) {
                $t->same($nestedCase['type'], $nested->type);
                $t->same($nestedCase['items'], array_map($listItemText, $nested->children));
                foreach (['start', 'style', 'delimiter'] as $attr) {
                    if (array_key_exists($attr, $nestedCase)) {
                        $t->same($nestedCase[$attr], $nested->attr($attr));
                    }
                }
            }
        }
    };
}

$tabCodeCases = [
    '01 one-space-tab indented code' => [" \tcode", 'code'],
    '02 two-space-tab indented code' => ["  \tcode", 'code'],
    '03 three-space-tab indented code' => ["   \tcode", 'code'],
    '04 leading tab indented code' => ["\tcode", 'code'],
    '05 one-space-tab preserves extra spaces' => [" \t  code", '  code'],
    '06 two-space-tab preserves extra spaces' => ["  \t  code", '  code'],
    '07 three-space-tab preserves extra spaces' => ["   \t  code", '  code'],
    '08 mixed tab indented code lines stay one block' => [" \tcode\n \tmore", "code\nmore"],
    '09 mixed tab indented code keeps internal blank' => ["  \tcode\n\n  \tmore", "code\n\nmore"],
    '10 tab-expanded bullet-looking line stays code' => [" \t- not list", '- not list'],
    '11 tab-expanded ordered-looking line stays code' => [" \t1. not ordered", '1. not ordered'],
    '12 tab-expanded fence-looking line stays code' => [" \t``` not fence", '``` not fence'],
    '13 paragraph before tab-expanded code' => ["Paragraph\n\n \tcode", 'code', ['paragraph', 'code_block']],
    '14 paragraph after tab-expanded code' => [" \tcode\nAfter", 'code', ['code_block', 'paragraph']],
    '15 three-space continuation after code starts paragraph' => [" \tcode\n   continuation", 'code', ['code_block', 'paragraph']],
    '16 four-space continuation remains code' => [" \tcode\n    four space", "code\nfour space"],
    '17 double leading tabs preserve one code indent' => ["\t\tdeep", '    deep'],
    '18 spaces before double tabs preserve one code indent' => ["  \t\tdeep", '    deep'],
    '19 three spaces before double tabs preserve one code indent' => ["   \t\tdeep", '    deep'],
    '20 trailing blank before paragraph is trimmed from code' => [" \tcode\n\nparagraph", 'code', ['code_block', 'paragraph']],
];

foreach ($tabCodeCases as $name => $case) {
    $tests['maps upstream markdown tab-expanded indented code surge ' . $name] = static function (TestRunner $t) use ($case): void {
        [$markdown, $expectedText] = $case;
        $document = (new MarkdownReader())->read($markdown);
        $expectedBlockTypes = $case[2] ?? ['code_block'];

        $t->same($expectedBlockTypes, array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $code = null;
        foreach ($document->children as $child) {
            if ($child->type === 'code_block') {
                $code = $child;
                break;
            }
        }

        $t->true($code instanceof AstNode, 'Expected a tab-expanded indented code block');
        if ($code instanceof AstNode) {
            $t->same($expectedText, $code->attr('text'));
            $t->same([], $code->attr('classes'));
            $t->same([], $code->attr('attributes'));
        }
    };
}

$codeBlock = static fn (string $text, array $attrs = []): AstNode => new AstNode('code_block', array_merge(['text' => $text], $attrs));
$document = static function (...$blocks): AstNode {
    if (count($blocks) === 1 && is_array($blocks[0])) {
        $blocks = $blocks[0];
    }

    return new AstNode('document', [], $blocks);
};
$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$heading = static fn (string $value): AstNode => new AstNode('heading', ['level' => 1], [$text($value)]);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$textItem = static fn (string $value, array $attrs = []): AstNode => $listItem([$text($value)], $attrs);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$definition = static fn (array $children, array $attrs = []): AstNode => new AstNode('definition', $attrs, $children);
$definitionTerm = static fn (array $children, array $attrs = []): AstNode => new AstNode('definition_term', $attrs, $children);
$definitionItem = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([$term], $definitions)
);
$line = static fn (string $value = ''): AstNode => $value === ''
    ? new AstNode('line')
    : new AstNode('line', [], [$text($value)]);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$div = static fn (array $children = [], array $attrs = []): AstNode => new AstNode('div', $attrs, $children);
$textNode = $text;
$paragraphNode = $paragraph;
$listItemNode = $listItem;
$bulletListNode = $bulletList;
$orderedListNode = $orderedList;
$definitionNode = static fn (array $children): AstNode => $definition($children);
$definitionTermNode = static fn (string $text): AstNode => $definitionTerm([$textNode($text)]);
$definitionItemNode = $definitionItem;
$definitionListNode = static fn (string $term, string $definition): AstNode => new AstNode('definition_list', [], [
    $definitionItemNode($definitionTermNode($term), [$definitionNode([$paragraphNode($definition)])]),
]);

$writerCases = [
    '01 plain code can be emitted as backtick fence' => [
        'document' => $document($codeBlock('echo 1;')),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\necho 1;\n```",
        'text' => 'echo 1;',
    ],
    '02 plain code can be emitted as tilde fence' => [
        'document' => $document($codeBlock('echo 2;')),
        'options' => ['fencedCodeBlocks' => true, 'fencedCodeBlockStyle' => 'tilde'],
        'expected' => "~~~\necho 2;\n~~~",
        'text' => 'echo 2;',
    ],
    '03 empty plain code can be emitted as fence' => [
        'document' => $document($codeBlock('')),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\n\n```",
        'text' => '',
    ],
    '04 multiline plain code can be emitted as fence' => [
        'document' => $document($codeBlock("alpha\nbeta")),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\nalpha\nbeta\n```",
        'text' => "alpha\nbeta",
    ],
    '05 backtick fence grows past literal triple run' => [
        'document' => $document($codeBlock('contains ``` fence')),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "````\ncontains ``` fence\n````",
        'text' => 'contains ``` fence',
    ],
    '06 backtick fence grows past literal four run' => [
        'document' => $document($codeBlock('contains ```` fence')),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "`````\ncontains ```` fence\n`````",
        'text' => 'contains ```` fence',
    ],
    '07 tilde fence grows past literal triple run' => [
        'document' => $document($codeBlock('contains ~~~ fence')),
        'options' => ['fencedCodeBlocks' => true, 'fencedCodeBlockStyle' => 'tilde'],
        'expected' => "~~~~\ncontains ~~~ fence\n~~~~",
        'text' => 'contains ~~~ fence',
    ],
    '08 fenced plain code preserves leading spaces' => [
        'document' => $document($codeBlock('    literal indent')),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\n    literal indent\n```",
        'text' => '    literal indent',
    ],
    '09 fenced plain code preserves blank middle line' => [
        'document' => $document($codeBlock("before\n\nafter")),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\nbefore\n\nafter\n```",
        'text' => "before\n\nafter",
    ],
    '10 fenced plain code preserves html-looking text' => [
        'document' => $document($codeBlock('<div>literal</div>')),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\n<div>literal</div>\n```",
        'text' => '<div>literal</div>',
    ],
    '11 fenced plain code preserves dollar and backslash text' => [
        'document' => $document($codeBlock('echo $value \\ $other;')),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\necho \$value \\ \$other;\n```",
        'text' => 'echo $value \\ $other;',
    ],
    '12 attributed code still emits fenced attributes without option' => [
        'document' => $document($codeBlock('echo 3;', ['classes' => ['php']])),
        'options' => [],
        'expected' => "```php\necho 3;\n```",
        'text' => 'echo 3;',
        'classes' => ['php'],
    ],
    '13 attributed code keeps id class and data attribute' => [
        'document' => $document($codeBlock('echo 4;', ['id' => 'snippet', 'classes' => ['php', 'numberLines'], 'attributes' => ['data-caption' => 'Review']])),
        'options' => [],
        'expected' => "```{#snippet .php .numberLines data-caption=\"Review\"}\necho 4;\n```",
        'text' => 'echo 4;',
        'classes' => ['php', 'numberLines'],
    ],
    '14 attributed code fence grows past backtick run' => [
        'document' => $document($codeBlock('literal ``` run', ['classes' => ['markdown']])),
        'options' => [],
        'expected' => "````markdown\nliteral ``` run\n````",
        'text' => 'literal ``` run',
        'classes' => ['markdown'],
    ],
    '15 tilde option applies to attributed code' => [
        'document' => $document($codeBlock('echo 5;', ['classes' => ['php']])),
        'options' => ['fencedCodeBlockStyle' => 'tilde'],
        'expected' => "~~~php\necho 5;\n~~~",
        'text' => 'echo 5;',
        'classes' => ['php'],
    ],
    '16 top-level plain code stays indented without fence option' => [
        'document' => $document($codeBlock('plain')),
        'options' => [],
        'expected' => '    plain',
        'text' => 'plain',
    ],
    '17 fenced option handles code that looks like list marker' => [
        'document' => $document($codeBlock("- not a list\n1. not ordered")),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\n- not a list\n1. not ordered\n```",
        'text' => "- not a list\n1. not ordered",
    ],
    '18 fenced option handles code that looks like yaml marker' => [
        'document' => $document($codeBlock("---\ntitle: no metadata")),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\n---\ntitle: no metadata\n```",
        'text' => "---\ntitle: no metadata",
    ],
    '19 fenced option handles code that looks like block quote' => [
        'document' => $document($codeBlock('> quoted literally')),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\n> quoted literally\n```",
        'text' => '> quoted literally',
    ],
    '20 fenced option handles code that looks like heading' => [
        'document' => $document($codeBlock('# literal heading')),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "```\n# literal heading\n```",
        'text' => '# literal heading',
    ],
];

foreach ($writerCases as $name => $case) {
    $tests['maps upstream markdown writer fenced block code surge ' . $name] = static function (TestRunner $t) use ($case): void {
        $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

        $t->same($case['expected'], $markdown);

        $roundTrip = (new MarkdownReader())->read($markdown);
        $code = $roundTrip->children[0];
        $t->same('code_block', $code->type);
        $t->same($case['text'], $code->attr('text'));
        if (array_key_exists('classes', $case)) {
            $t->same($case['classes'], $code->attr('classes'));
        }
    };
}

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$heading = static fn (string $value): AstNode => new AstNode('heading', ['level' => 1], [$text($value)]);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$textItem = static fn (string $value, array $attrs = []): AstNode => $listItem([$text($value)], $attrs);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$div = static fn (array $children = [], array $attrs = []): AstNode => new AstNode('div', $attrs, $children);

$findFirst = static function (AstNode $node, string $type) use (&$findFirst): ?AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $found = $findFirst($child, $type);
        if ($found instanceof AstNode) {
            return $found;
        }
    }

    return null;
};

$pandocListStyleCases = [
    '01 default period marker simple list' => [
        'document' => $document($orderedList([$textItem('alpha'), $textItem('beta')], ['style' => 'default'])),
        'expected' => "#.  alpha\n#.  beta",
        'listAttrs' => ['style' => 'default', 'delimiter' => 'default'],
    ],
    '02 default one paren marker simple list' => [
        'document' => $document($orderedList([$textItem('alpha'), $textItem('beta')], ['style' => 'default', 'delimiter' => 'one_paren'])),
        'expected' => "#)  alpha\n#)  beta",
        'listAttrs' => ['style' => 'default', 'delimiter' => 'default'],
    ],
    '03 default marker task item open' => [
        'document' => $document($orderedList([$textItem('todo', ['taskChecked' => false])], ['style' => 'default'])),
        'expected' => '#.  [ ] todo',
        'taskChecked' => false,
    ],
    '04 default marker task item checked' => [
        'document' => $document($orderedList([$textItem('done', ['taskChecked' => true])], ['style' => 'default'])),
        'expected' => '#.  [x] done',
        'taskChecked' => true,
    ],
    '05 default marker second paragraph continuation' => [
        'document' => $document($orderedList([$listItem([$paragraph('alpha'), $paragraph('beta')])], ['style' => 'default'])),
        'expected' => "#.  alpha\n\n    beta",
    ],
    '06 default marker loose list spacing' => [
        'document' => $document($orderedList([$textItem('one'), $textItem('two')], ['style' => 'default', 'loose' => true])),
        'expected' => "#.  one\n\n#.  two",
    ],
    '07 default marker nested bullet list' => [
        'document' => $document($orderedList([$listItem([$text('alpha'), $bulletList([$textItem('beta')])])], ['style' => 'default'])),
        'expected' => "#.  alpha\n    - beta",
    ],
    '08 default marker nested decimal list' => [
        'document' => $document($orderedList([$listItem([$text('alpha'), $orderedList([$textItem('beta')])])], ['style' => 'default'])),
        'expected' => "#.  alpha\n    1.  beta",
    ],
    '09 default marker nested blockquote' => [
        'document' => $document($orderedList([$listItem([$text('alpha'), $blockquote([$paragraph('quote')])])], ['style' => 'default'])),
        'expected' => "#.  alpha\n    > quote",
    ],
    '10 default marker code after text' => [
        'document' => $document($orderedList([$listItem([$text('alpha'), $codeBlock('echo alpha')])], ['style' => 'default'])),
        'expected' => "#.  alpha\n        echo alpha",
    ],
    '11 default marker starts code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')])], ['style' => 'default'])),
        'expected' => '#.     echo alpha',
        'codeText' => 'echo alpha',
    ],
    '12 default marker starts multiline code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock("echo alpha\necho beta")])], ['style' => 'default'])),
        'expected' => "#.     echo alpha\n       echo beta",
        'codeText' => "echo alpha\necho beta",
    ],
    '13 default marker code item followed by paragraph' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha'), $paragraph('after')])], ['style' => 'default'])),
        'expected' => "#.     echo alpha\n\n    after",
    ],
    '14 default paren marker starts code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')])], ['style' => 'default', 'delimiter' => 'one_paren'])),
        'expected' => '#)     echo alpha',
        'codeText' => 'echo alpha',
    ],
    '15 adjacent default lists keep html separator' => [
        'document' => $document(
            $orderedList([$textItem('one')], ['style' => 'default']),
            $orderedList([$textItem('two')], ['style' => 'default'])
        ),
        'expected' => "#.  one\n\n<!-- -->\n\n#.  two",
    ],
    '16 default period then paren lists stay distinct' => [
        'document' => $document(
            $orderedList([$textItem('one')], ['style' => 'default']),
            $orderedList([$textItem('two')], ['style' => 'default', 'delimiter' => 'one_paren'])
        ),
        'expected' => "#.  one\n\n<!-- -->\n\n#)  two",
    ],
    '17 default marker fenced option keeps nested fence' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')])], ['style' => 'default'])),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "#.\n    ```\n    echo alpha\n    ```",
    ],
    '18 default marker attributed code keeps nested fence' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha', ['classes' => ['php']])])], ['style' => 'default'])),
        'expected' => "#.\n    ```php\n    echo alpha\n    ```",
    ],
    '19 default marker nested heading starts block' => [
        'document' => $document($orderedList([$listItem([$heading('Heading')])], ['style' => 'default'])),
        'expected' => "#.\n    # Heading",
    ],
    '20 default marker nested horizontal rule starts block' => [
        'document' => $document($orderedList([$listItem([new AstNode('horizontal_rule')])], ['style' => 'default'])),
        'expected' => "#.\n    * * *",
    ],
    '21 example marker simple list' => [
        'document' => $document($orderedList([$textItem('alpha'), $textItem('beta')], ['style' => 'example'])),
        'expected' => "(@) alpha\n(@) beta",
        'listAttrs' => ['style' => 'example', 'delimiter' => 'two_parens'],
    ],
    '22 example marker list label fallback' => [
        'document' => $document($orderedList([$textItem('alpha')], ['style' => 'example', 'exampleLabel' => 'review'])),
        'expected' => '(@review) alpha',
    ],
    '23 example marker item labels' => [
        'document' => $document($orderedList([
            $textItem('alpha', ['exampleLabel' => 'first']),
            $textItem('beta', ['exampleLabel' => 'second']),
        ], ['style' => 'example'])),
        'expected' => "(@first) alpha\n(@second) beta",
    ],
    '24 example marker second paragraph continuation' => [
        'document' => $document($orderedList([$listItem([$paragraph('alpha'), $paragraph('beta')])], ['style' => 'example'])),
        'expected' => "(@) alpha\n\n    beta",
    ],
    '25 example marker loose list spacing' => [
        'document' => $document($orderedList([$textItem('one'), $textItem('two')], ['style' => 'example', 'loose' => true])),
        'expected' => "(@) one\n\n(@) two",
    ],
    '26 example marker nested bullet list' => [
        'document' => $document($orderedList([$listItem([$text('alpha'), $bulletList([$textItem('beta')])])], ['style' => 'example'])),
        'expected' => "(@) alpha\n    - beta",
    ],
    '27 example marker nested default list' => [
        'document' => $document($orderedList([$listItem([$text('alpha'), $orderedList([$textItem('beta')], ['style' => 'default'])])], ['style' => 'example'])),
        'expected' => "(@) alpha\n    #.  beta",
    ],
    '28 example marker starts code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')])], ['style' => 'example'])),
        'expected' => '(@)     echo alpha',
        'codeText' => 'echo alpha',
    ],
    '29 example labeled marker starts code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')], ['exampleLabel' => 'review'])], ['style' => 'example'])),
        'expected' => '(@review)     echo alpha',
        'codeText' => 'echo alpha',
    ],
    '30 example marker code after text' => [
        'document' => $document($orderedList([$listItem([$text('alpha'), $codeBlock('echo alpha')])], ['style' => 'example'])),
        'expected' => "(@) alpha\n        echo alpha",
    ],
    '31 example marker task item checked' => [
        'document' => $document($orderedList([$textItem('done', ['taskChecked' => true])], ['style' => 'example'])),
        'expected' => '(@) [x] done',
        'taskChecked' => true,
    ],
    '32 example marker nested blockquote' => [
        'document' => $document($orderedList([$listItem([$text('alpha'), $blockquote([$paragraph('quote')])])], ['style' => 'example'])),
        'expected' => "(@) alpha\n    > quote",
    ],
    '33 example marker nested div block' => [
        'document' => $document($orderedList([$listItem([$text('alpha'), $div([$paragraph('body')], ['classes' => ['review']])])], ['style' => 'example'])),
        'expected' => "(@) alpha\n    ::: {.review}\n    body\n    :::",
    ],
    '34 adjacent example lists keep html separator' => [
        'document' => $document(
            $orderedList([$textItem('one')], ['style' => 'example']),
            $orderedList([$textItem('two')], ['style' => 'example'])
        ),
        'expected' => "(@) one\n\n<!-- -->\n\n(@) two",
    ],
    '35 decimal then example lists stay distinct' => [
        'document' => $document(
            $orderedList([$textItem('one')]),
            $orderedList([$textItem('two')], ['style' => 'example'])
        ),
        'expected' => "1.  one\n\n(@) two",
    ],
    '36 bullet marker starts code item' => [
        'document' => $document($bulletList([$listItem([$codeBlock('echo alpha')])])),
        'expected' => '-     echo alpha',
        'codeText' => 'echo alpha',
    ],
    '37 bullet marker starts multiline code item' => [
        'document' => $document($bulletList([$listItem([$codeBlock("echo alpha\necho beta")])])),
        'expected' => "-     echo alpha\n      echo beta",
        'codeText' => "echo alpha\necho beta",
    ],
    '38 bullet marker starts code item with internal blank' => [
        'document' => $document($bulletList([$listItem([$codeBlock("before\n\nafter")])])),
        'expected' => "-     before\n      \n      after",
        'codeText' => "before\n\nafter",
    ],
    '39 bullet marker code item followed by paragraph' => [
        'document' => $document($bulletList([$listItem([$codeBlock('echo alpha'), $paragraph('after')])])),
        'expected' => "-     echo alpha\n\n  after",
    ],
    '40 bullet marker code item followed by nested bullet' => [
        'document' => $document($bulletList([$listItem([$codeBlock('echo alpha'), $bulletList([$textItem('next')])])])),
        'expected' => "-     echo alpha\n  - next",
    ],
    '41 bullet marker attributed code keeps nested fence' => [
        'document' => $document($bulletList([$listItem([$codeBlock('echo alpha', ['classes' => ['php']])])])),
        'expected' => "-\n  ```php\n  echo alpha\n  ```",
    ],
    '42 decimal marker starts code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')])])),
        'expected' => '1.     echo alpha',
        'codeText' => 'echo alpha',
    ],
    '43 decimal marker starts multiline code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock("echo alpha\necho beta")])])),
        'expected' => "1.     echo alpha\n       echo beta",
        'codeText' => "echo alpha\necho beta",
    ],
    '44 decimal start offset marker starts code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')])], ['start' => 9])),
        'expected' => '9.     echo alpha',
        'codeText' => 'echo alpha',
    ],
    '45 wide decimal marker starts code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')])], ['start' => 10])),
        'expected' => '10.     echo alpha',
        'codeText' => 'echo alpha',
    ],
    '46 lower alpha marker starts code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')])], ['style' => 'lower_alpha'])),
        'expected' => 'a.     echo alpha',
        'codeText' => 'echo alpha',
    ],
    '47 lower roman marker starts code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')])], ['style' => 'lower_roman', 'start' => 4])),
        'expected' => 'iv.     echo alpha',
        'codeText' => 'echo alpha',
    ],
    '48 bullet marker fenced option keeps nested fence' => [
        'document' => $document($bulletList([$listItem([$codeBlock('echo alpha')])])),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "-\n  ```\n  echo alpha\n  ```",
    ],
    '49 decimal marker fenced option keeps nested fence' => [
        'document' => $document($orderedList([$listItem([$codeBlock('echo alpha')])])),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "1.\n    ```\n    echo alpha\n    ```",
    ],
    '50 example labeled marker starts multiline code item' => [
        'document' => $document($orderedList([$listItem([$codeBlock("echo alpha\necho beta")], ['exampleLabel' => 'review'])], ['style' => 'example'])),
        'expected' => "(@review)     echo alpha\n              echo beta",
        'codeText' => "echo alpha\necho beta",
    ],
];

foreach ($pandocListStyleCases as $name => $case) {
    $tests['maps upstream markdown writer pandoc list style code surge ' . $name] =
        static function (TestRunner $t) use ($case, $findFirst): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['document']);

            $t->same($case['expected'], $markdown);

            if (isset($case['listAttrs']) || array_key_exists('taskChecked', $case) || isset($case['codeText'])) {
                $roundTrip = (new MarkdownReader())->read($markdown);
                $list = $roundTrip->children[0] ?? null;
                $t->true($list instanceof AstNode, 'Expected a round-tripped list block');
                if ($list instanceof AstNode && isset($case['listAttrs'])) {
                    foreach ($case['listAttrs'] as $attr => $expected) {
                        $t->same($expected, $list->attr($attr));
                    }
                }
                if ($list instanceof AstNode && array_key_exists('taskChecked', $case)) {
                    $item = $list->children[0] ?? null;
                    $t->same($case['taskChecked'], $item instanceof AstNode ? $item->attr('taskChecked') : null);
                }
                if (isset($case['codeText'])) {
                    $code = $findFirst($roundTrip, 'code_block');
                    $t->true($code instanceof AstNode, 'Expected a round-tripped code block');
                    if ($code instanceof AstNode) {
                        $t->same($case['codeText'], $code->attr('text'));
                    }
                }
            }
        };
}

$collectOrderedListStyles = null;
$collectOrderedListStyles = static function (AstNode $node) use (&$collectOrderedListStyles): array {
    $styles = $node->type === 'ordered_list' ? [(string) $node->attr('style', 'decimal')] : [];
    foreach ($node->children as $child) {
        $styles = array_merge($styles, $collectOrderedListStyles($child));
    }

    return $styles;
};

$pandocOrderedMarkerCases = [
    '01 default period single item' => [
        $document($orderedList([$textItem('alpha')], ['style' => 'default'])),
        '#.  alpha',
        ['default'],
    ],
    '02 default paren single item' => [
        $document($orderedList([$textItem('alpha')], ['style' => 'default', 'delimiter' => 'one_paren'])),
        '#)  alpha',
        ['default'],
    ],
    '03 default delimiter attribute stays period marker' => [
        $document($orderedList([$textItem('alpha')], ['style' => 'default', 'delimiter' => 'default'])),
        '#.  alpha',
        ['default'],
    ],
    '04 default two item list' => [
        $document($orderedList([$textItem('alpha'), $textItem('beta')], ['style' => 'default'])),
        "#.  alpha\n#.  beta",
        ['default'],
    ],
    '05 default ignores numeric start offset' => [
        $document($orderedList([$textItem('alpha')], ['style' => 'default', 'start' => 7])),
        '#.  alpha',
        ['default'],
    ],
    '06 default paragraph continuation' => [
        $document($orderedList([$listItem([$paragraph('alpha'), $paragraph('beta')])], ['style' => 'default'])),
        "#.  alpha\n\n    beta",
        ['default'],
    ],
    '07 default loose list spacing' => [
        $document($orderedList([$textItem('one'), $textItem('two')], ['style' => 'default', 'loose' => true])),
        "#.  one\n\n#.  two",
        ['default'],
    ],
    '08 default unchecked task item' => [
        $document($orderedList([$textItem('todo', ['taskChecked' => false])], ['style' => 'default'])),
        '#.  [ ] todo',
        ['default'],
    ],
    '09 default checked task item' => [
        $document($orderedList([$textItem('done', ['taskChecked' => true])], ['style' => 'default'])),
        '#.  [x] done',
        ['default'],
    ],
    '10 default nested bullet list' => [
        $document($orderedList([$listItem([$text('alpha'), $bulletList([$textItem('beta')])])], ['style' => 'default'])),
        "#.  alpha\n    - beta",
        ['default'],
    ],
    '11 default nested decimal list' => [
        $document($orderedList([$listItem([$text('alpha'), $orderedList([$textItem('beta')])])], ['style' => 'default'])),
        "#.  alpha\n    1.  beta",
        ['default', 'decimal'],
    ],
    '12 default nested default list' => [
        $document($orderedList([$listItem([$text('alpha'), $orderedList([$textItem('beta')], ['style' => 'default'])])], ['style' => 'default'])),
        "#.  alpha\n    #.  beta",
        ['default', 'default'],
    ],
    '13 default nested example list' => [
        $document($orderedList([$listItem([$text('alpha'), $orderedList([$textItem('beta')], ['style' => 'example'])])], ['style' => 'default'])),
        "#.  alpha\n    (@) beta",
        ['default', 'example'],
    ],
    '14 default indented code continuation' => [
        $document($orderedList([$listItem([$text('alpha'), $codeBlock('code')])], ['style' => 'default'])),
        "#.  alpha\n        code",
        ['default'],
    ],
    '15 default fenced code continuation' => [
        $document($orderedList([$listItem([$text('alpha'), $codeBlock('echo', ['classes' => ['php']])])], ['style' => 'default'])),
        "#.  alpha\n    ```php\n    echo\n    ```",
        ['default'],
    ],
    '16 default blockquote continuation' => [
        $document($orderedList([$listItem([$text('alpha'), $blockquote([$paragraph('quote')])])], ['style' => 'default'])),
        "#.  alpha\n    > quote",
        ['default'],
    ],
    '17 default followed by decimal needs no separator' => [
        $document(
            $orderedList([$textItem('default')], ['style' => 'default']),
            $orderedList([$textItem('decimal')])
        ),
        "#.  default\n\n1.  decimal",
        ['default', 'decimal'],
    ],
    '18 decimal followed by default needs no separator' => [
        $document(
            $orderedList([$textItem('decimal')]),
            $orderedList([$textItem('default')], ['style' => 'default'])
        ),
        "1.  decimal\n\n#.  default",
        ['decimal', 'default'],
    ],
    '19 adjacent default lists keep html separator' => [
        $document(
            $orderedList([$textItem('one')], ['style' => 'default']),
            $orderedList([$textItem('two')], ['style' => 'default'])
        ),
        "#.  one\n\n<!-- -->\n\n#.  two",
        ['default', 'default'],
    ],
    '20 default paren followed by default keeps separator' => [
        $document(
            $orderedList([$textItem('one')], ['style' => 'default', 'delimiter' => 'one_paren']),
            $orderedList([$textItem('two')], ['style' => 'default'])
        ),
        "#)  one\n\n<!-- -->\n\n#.  two",
        ['default', 'default'],
    ],
    '21 bullet followed by default ordered list' => [
        $document(
            $bulletList([$textItem('bullet')]),
            $orderedList([$textItem('default')], ['style' => 'default'])
        ),
        "- bullet\n\n#.  default",
        ['default'],
    ],
    '22 default list inside bullet item' => [
        $document($bulletList([$listItem([$text('outer'), $orderedList([$textItem('inner')], ['style' => 'default'])])])),
        "- outer\n  #.  inner",
        ['default'],
    ],
    '23 example single item' => [
        $document($orderedList([$textItem('alpha')], ['style' => 'example'])),
        '(@) alpha',
        ['example'],
    ],
    '24 example two item list' => [
        $document($orderedList([$textItem('alpha'), $textItem('beta')], ['style' => 'example'])),
        "(@) alpha\n(@) beta",
        ['example'],
    ],
    '25 example labeled item attribute' => [
        $document($orderedList([$textItem('alpha', ['exampleLabel' => 'review'])], ['style' => 'example'])),
        '(@review) alpha',
        ['example'],
    ],
    '26 example list label attribute' => [
        $document($orderedList([$textItem('alpha')], ['style' => 'example', 'exampleLabels' => ['review']])),
        '(@review) alpha',
        ['example'],
    ],
    '27 example sparse list label attribute' => [
        $document($orderedList([$textItem('alpha'), $textItem('beta')], ['style' => 'example', 'exampleLabels' => [1 => 'second']])),
        "(@) alpha\n(@second) beta",
        ['example'],
    ],
    '28 example unsafe label falls back to unlabeled marker' => [
        $document($orderedList([$textItem('alpha', ['exampleLabel' => 'bad label'])], ['style' => 'example'])),
        '(@) alpha',
        ['example'],
    ],
    '29 example checked task item' => [
        $document($orderedList([$textItem('done', ['taskChecked' => true])], ['style' => 'example'])),
        '(@) [x] done',
        ['example'],
    ],
    '30 example unchecked task item' => [
        $document($orderedList([$textItem('todo', ['taskChecked' => false])], ['style' => 'example'])),
        '(@) [ ] todo',
        ['example'],
    ],
    '31 example loose list spacing' => [
        $document($orderedList([$textItem('one'), $textItem('two')], ['style' => 'example', 'loose' => true])),
        "(@) one\n\n(@) two",
        ['example'],
    ],
    '32 example paragraph continuation' => [
        $document($orderedList([$listItem([$paragraph('alpha'), $paragraph('beta')])], ['style' => 'example'])),
        "(@) alpha\n\n    beta",
        ['example'],
    ],
    '33 example nested bullet list' => [
        $document($orderedList([$listItem([$text('alpha'), $bulletList([$textItem('beta')])])], ['style' => 'example'])),
        "(@) alpha\n    - beta",
        ['example'],
    ],
    '34 example nested decimal list' => [
        $document($orderedList([$listItem([$text('alpha'), $orderedList([$textItem('beta')])])], ['style' => 'example'])),
        "(@) alpha\n    1.  beta",
        ['example', 'decimal'],
    ],
    '35 example nested default list' => [
        $document($orderedList([$listItem([$text('alpha'), $orderedList([$textItem('beta')], ['style' => 'default'])])], ['style' => 'example'])),
        "(@) alpha\n    #.  beta",
        ['example', 'default'],
    ],
    '36 example nested labeled example list' => [
        $document($orderedList([$listItem([$text('alpha'), $orderedList([$textItem('beta', ['exampleLabel' => 'inner'])], ['style' => 'example'])])], ['style' => 'example'])),
        "(@) alpha\n    (@inner) beta",
        ['example', 'example'],
    ],
    '37 example indented code continuation' => [
        $document($orderedList([$listItem([$text('alpha'), $codeBlock('code')])], ['style' => 'example'])),
        "(@) alpha\n        code",
        ['example'],
    ],
    '38 labeled example indented code continuation' => [
        $document($orderedList([$listItem([$text('alpha'), $codeBlock('code')], ['exampleLabel' => 'review'])], ['style' => 'example'])),
        '(@review) alpha' . "\n" . str_repeat(' ', 14) . 'code',
        ['example'],
    ],
    '39 example fenced code continuation' => [
        $document($orderedList([$listItem([$text('alpha'), $codeBlock('echo', ['classes' => ['php']])])], ['style' => 'example'])),
        "(@) alpha\n    ```php\n    echo\n    ```",
        ['example'],
    ],
    '40 example blockquote continuation' => [
        $document($orderedList([$listItem([$text('alpha'), $blockquote([$paragraph('quote')])])], ['style' => 'example'])),
        "(@) alpha\n    > quote",
        ['example'],
    ],
    '41 adjacent example lists keep separator' => [
        $document(
            $orderedList([$textItem('one')], ['style' => 'example']),
            $orderedList([$textItem('two')], ['style' => 'example'])
        ),
        "(@) one\n\n<!-- -->\n\n(@) two",
        ['example', 'example'],
    ],
    '42 example followed by default needs no separator' => [
        $document(
            $orderedList([$textItem('example')], ['style' => 'example']),
            $orderedList([$textItem('default')], ['style' => 'default'])
        ),
        "(@) example\n\n#.  default",
        ['example', 'default'],
    ],
    '43 default followed by example needs no separator' => [
        $document(
            $orderedList([$textItem('default')], ['style' => 'default']),
            $orderedList([$textItem('example')], ['style' => 'example'])
        ),
        "#.  default\n\n(@) example",
        ['default', 'example'],
    ],
    '44 example list inside bullet item' => [
        $document($bulletList([$listItem([$text('outer'), $orderedList([$textItem('inner')], ['style' => 'example'])])])),
        "- outer\n  (@) inner",
        ['example'],
    ],
    '45 labeled example list inside bullet item' => [
        $document($bulletList([$listItem([$text('outer'), $orderedList([$textItem('inner', ['exampleLabel' => 'review'])], ['style' => 'example'])])])),
        "- outer\n  (@review) inner",
        ['example'],
    ],
    '46 example list inside decimal item' => [
        $document($orderedList([$listItem([$text('outer'), $orderedList([$textItem('inner')], ['style' => 'example'])])])),
        "1.  outer\n    (@) inner",
        ['decimal', 'example'],
    ],
    '47 default list inside example item' => [
        $document($orderedList([$listItem([$text('outer'), $orderedList([$textItem('inner')], ['style' => 'default'])])], ['style' => 'example'])),
        "(@) outer\n    #.  inner",
        ['example', 'default'],
    ],
    '48 labeled example list inside default item' => [
        $document($orderedList([$listItem([$text('outer'), $orderedList([$textItem('inner', ['exampleLabel' => 'review'])], ['style' => 'example'])])], ['style' => 'default'])),
        "#.  outer\n    (@review) inner",
        ['default', 'example'],
    ],
    '49 default list inside blockquote' => [
        $document($blockquote([$orderedList([$textItem('quote')], ['style' => 'default'])])),
        '> #.  quote',
        ['default'],
    ],
    '50 example list inside blockquote' => [
        $document($blockquote([$orderedList([$textItem('quote')], ['style' => 'example'])])),
        '> (@) quote',
        ['example'],
    ],
];

foreach ($pandocOrderedMarkerCases as $name => $case) {
    $tests['maps upstream markdown writer pandoc ordered marker completion ' . $name] =
        static function (TestRunner $t) use ($case, $collectOrderedListStyles): void {
            [$documentNode, $expected, $styles] = $case;
            $markdown = (new MarkdownWriter())->write($documentNode);

            $t->same($expected, $markdown);
            $roundTrip = (new MarkdownReader())->read($markdown);
            $t->same($styles, $collectOrderedListStyles($roundTrip), $markdown);
        };
}

$strong = static fn (string $value): AstNode => new AstNode('strong', [], [$text($value)]);
$emph = static fn (string $value): AstNode => new AstNode('emph', [], [$text($value)]);
$span = static fn (array $children, array $attrs = []): AstNode => new AstNode('span', $attrs, $children);
$link = static fn (string $label, string $url, array $attrs = []): AstNode => new AstNode('link', array_merge(['url' => $url], $attrs), [$text($label)]);
$image = static fn (string $alt, string $url, array $attrs = []): AstNode => new AstNode('image', array_merge(['url' => $url, 'alt' => $alt], $attrs), [$text($alt)]);

$htmlFallbackCases = [
    '01 bullet list id class data auto fallback' => [
        $document([new AstNode('bullet_list', ['id' => 'review', 'classes' => ['queue'], 'attributes' => ['data-source' => 'batch']], [
            $listItem([$text('alpha')], ['classes' => ['ready']]),
        ])]),
        '<ul id="review" class="queue" data-source="batch"><li class="ready">alpha</li></ul>',
    ],
    '02 bullet list html attributes merge classes' => [
        $document([new AstNode('bullet_list', ['classes' => ['source'], 'htmlAttributes' => ['class' => 'from-html', 'data-pandoc-writer' => 'html']], [
            $listItem([$text('alpha')]),
        ])]),
        '<ul class="from-html source" data-pandoc-writer="html"><li>alpha</li></ul>',
    ],
    '03 bullet list data writer attribute requests fallback' => [
        $document([new AstNode('bullet_list', ['htmlAttributes' => ['data-pandoc-writer' => 'html']], [
            $listItem([$text('alpha')]),
            $listItem([$text('beta')]),
        ])]),
        '<ul data-pandoc-writer="html"><li>alpha</li><li>beta</li></ul>',
    ],
    '04 bullet list sanitizer drops unsafe event attributes' => [
        $document([new AstNode('bullet_list', ['attributes' => ['onclick' => 'alert(1)', 'data-safe' => 'yes']], [
            $listItem([$text('safe')]),
        ])]),
        '<ul data-safe="yes"><li>safe</li></ul>',
    ],
    '05 bullet item id is preserved by auto fallback' => [
        $document([$bulletList([$listItem([$text('alpha')], ['id' => 'item-alpha'])])]),
        '<ul><li id="item-alpha">alpha</li></ul>',
    ],
    '06 bullet item class and data attributes are preserved' => [
        $document([$bulletList([$listItem([$text('alpha')], ['classes' => ['done'], 'attributes' => ['data-step' => '1']])])]),
        '<ul><li class="done" data-step="1">alpha</li></ul>',
    ],
    '07 bullet item html attributes merge classes' => [
        $document([$bulletList([$listItem([$text('alpha')], ['classes' => ['source'], 'htmlAttributes' => ['class' => 'from-html', 'data-state' => 'ready']])])]),
        '<ul><li class="from-html source" data-state="ready">alpha</li></ul>',
    ],
    '08 bullet item sanitizer drops unsafe event attributes' => [
        $document([$bulletList([$listItem([$text('alpha')], ['attributes' => ['onclick' => 'bad()', 'data-ok' => '1']])])]),
        '<ul><li data-ok="1">alpha</li></ul>',
    ],
    '09 explicit html bullet list keeps nested bullet attrs' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([
                $text('parent'),
                new AstNode('bullet_list', ['id' => 'child-list'], [$listItem([$text('child')])]),
            ]),
        ])]),
        '<ul><li>parent<ul id="child-list"><li>child</li></ul></li></ul>',
    ],
    '10 explicit html bullet list keeps nested ordered attrs' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([
                $text('parent'),
                new AstNode('ordered_list', ['style' => 'upper_alpha', 'start' => 2], [$listItem([$text('child')])]),
            ]),
        ])]),
        '<ul><li>parent<ol start="2" type="A"><li>child</li></ol></li></ul>',
    ],
    '11 explicit html bullet item paragraph block' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([$paragraph('alpha')]),
        ])]),
        '<ul><li><p>alpha</p></li></ul>',
    ],
    '12 explicit html bullet item two paragraphs' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([$paragraph('alpha'), $paragraph('beta')]),
        ])]),
        '<ul><li><p>alpha</p><p>beta</p></li></ul>',
    ],
    '13 explicit html bullet item blockquote' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([$blockquote([$paragraph('quoted')])]),
        ])]),
        '<ul><li><blockquote><p>quoted</p></blockquote></li></ul>',
    ],
    '14 explicit html bullet item code block' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([$codeBlock('echo alpha', ['classes' => ['php']])]),
        ])]),
        '<ul><li><pre><code class="php">echo alpha</code></pre></li></ul>',
    ],
    '15 explicit html bullet item div block' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([$div([$paragraph('note')], ['id' => 'note', 'classes' => ['callout']])]),
        ])]),
        '<ul><li><div id="note" class="callout"><p>note</p></div></li></ul>',
    ],
    '16 explicit html bullet item raw html block' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([new AstNode('raw_html', ['html' => '<aside>raw</aside>'])]),
        ])]),
        '<ul><li><aside>raw</aside></li></ul>',
    ],
    '17 explicit html bullet item rich inline children' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([$text('one '), $strong('strong'), $text(' and '), $emph('em')]),
        ])]),
        '<ul><li>one <strong>strong</strong> and <em>em</em></li></ul>',
    ],
    '18 explicit html task list unchecked item' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html', 'taskList' => true], [
            $listItem([$text('todo')], ['taskChecked' => false]),
        ])]),
        '<ul class="task-list"><li><input type="checkbox" />todo</li></ul>',
    ],
    '19 explicit html task list checked item' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html', 'taskList' => true], [
            $listItem([$text('done')], ['taskChecked' => true]),
        ])]),
        '<ul class="task-list"><li><input type="checkbox" checked="" />done</li></ul>',
    ],
    '20 explicit html task list mixed items' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([$text('todo')], ['taskChecked' => false]),
            $listItem([$text('plain')]),
            $listItem([$text('done')], ['taskChecked' => true]),
        ])]),
        '<ul class="task-list"><li><input type="checkbox" />todo</li><li>plain</li><li><input type="checkbox" checked="" />done</li></ul>',
    ],
    '21 explicit html task list preserves source class' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html', 'classes' => ['review'], 'taskList' => true], [
            $listItem([$text('todo')], ['taskChecked' => false]),
        ])]),
        '<ul class="task-list review"><li><input type="checkbox" />todo</li></ul>',
    ],
    '22 ordered list explicit html start attribute' => [
        $document([new AstNode('ordered_list', ['markdownListFormat' => 'html', 'start' => 3], [$listItem([$text('three')])])]),
        '<ol start="3"><li>three</li></ol>',
    ],
    '23 ordered list explicit html lower alpha type' => [
        $document([new AstNode('ordered_list', ['markdownListFormat' => 'html', 'style' => 'lower_alpha'], [$listItem([$text('alpha')])])]),
        '<ol type="a"><li>alpha</li></ol>',
    ],
    '24 ordered list explicit html upper alpha type' => [
        $document([new AstNode('ordered_list', ['markdownListFormat' => 'html', 'style' => 'upper_alpha'], [$listItem([$text('alpha')])])]),
        '<ol type="A"><li>alpha</li></ol>',
    ],
    '25 ordered list explicit html lower roman type' => [
        $document([new AstNode('ordered_list', ['markdownListFormat' => 'html', 'style' => 'lower_roman'], [$listItem([$text('roman')])])]),
        '<ol type="i"><li>roman</li></ol>',
    ],
    '26 ordered list explicit html upper roman start type' => [
        $document([new AstNode('ordered_list', ['markdownListFormat' => 'html', 'style' => 'upper_roman', 'start' => 4], [$listItem([$text('four')])])]),
        '<ol start="4" type="I"><li>four</li></ol>',
    ],
    '27 ordered item number becomes html value' => [
        $document([new AstNode('ordered_list', ['markdownListFormat' => 'html'], [
            $listItem([$text('seven')], ['number' => 7]),
        ])]),
        '<ol><li value="7">seven</li></ol>',
    ],
    '28 ordered list id class data attributes' => [
        $document([new AstNode('ordered_list', ['id' => 'steps', 'classes' => ['review'], 'attributes' => ['data-kind' => 'audit']], [
            $listItem([$text('one')]),
        ])]),
        '<ol id="steps" class="review" data-kind="audit"><li>one</li></ol>',
    ],
    '29 ordered html type attribute is not overwritten' => [
        $document([new AstNode('ordered_list', ['markdownListFormat' => 'html', 'style' => 'lower_roman', 'htmlAttributes' => ['type' => 'A']], [
            $listItem([$text('alpha')]),
        ])]),
        '<ol type="A"><li>alpha</li></ol>',
    ],
    '30 ordered item unsafe event attribute is dropped' => [
        $document([new AstNode('ordered_list', ['markdownListFormat' => 'html'], [
            $listItem([$text('safe')], ['attributes' => ['onclick' => 'bad()', 'data-ok' => '1']]),
        ])]),
        '<ol><li data-ok="1">safe</li></ol>',
    ],
    '31 definition list explicit html simple item' => [
        $document([new AstNode('definition_list', ['markdownListFormat' => 'html'], [
            $definitionItem($definitionTerm([$text('Term')]), [$definition([$paragraph('Definition')])]),
        ])]),
        '<dl><dt>Term</dt><dd><p>Definition</p></dd></dl>',
    ],
    '32 definition list explicit html preserves id' => [
        $document([new AstNode('definition_list', ['markdownListFormat' => 'html', 'id' => 'glossary'], [
            $definitionItem($definitionTerm([$text('Term')]), [$definition([$paragraph('Definition')])]),
        ])]),
        '<dl id="glossary"><dt>Term</dt><dd><p>Definition</p></dd></dl>',
    ],
    '33 definition term attributes are preserved' => [
        $document([new AstNode('definition_list', ['markdownListFormat' => 'html'], [
            $definitionItem(new AstNode('definition_term', ['id' => 'term', 'classes' => ['primary']], [$text('Term')]), [$definition([$paragraph('Definition')])]),
        ])]),
        '<dl><dt id="term" class="primary">Term</dt><dd><p>Definition</p></dd></dl>',
    ],
    '34 definition body attributes are preserved' => [
        $document([new AstNode('definition_list', ['markdownListFormat' => 'html'], [
            $definitionItem($definitionTerm([$text('Term')]), [new AstNode('definition', ['classes' => ['meaning'], 'attributes' => ['data-source' => 'batch']], [$paragraph('Definition')])]),
        ])]),
        '<dl><dt>Term</dt><dd class="meaning" data-source="batch"><p>Definition</p></dd></dl>',
    ],
    '35 definition list multiple definitions' => [
        $document([new AstNode('definition_list', ['markdownListFormat' => 'html'], [
            $definitionItem($definitionTerm([$text('Term')]), [$definition([$paragraph('First')]), $definition([$paragraph('Second')])]),
        ])]),
        '<dl><dt>Term</dt><dd><p>First</p></dd><dd><p>Second</p></dd></dl>',
    ],
    '36 definition list multiple items' => [
        $document([new AstNode('definition_list', ['markdownListFormat' => 'html'], [
            $definitionItem($definitionTerm([$text('One')]), [$definition([$paragraph('First')])]),
            $definitionItem($definitionTerm([$text('Two')]), [$definition([$paragraph('Second')])]),
        ])]),
        '<dl><dt>One</dt><dd><p>First</p></dd><dt>Two</dt><dd><p>Second</p></dd></dl>',
    ],
    '37 definition term linebreak becomes html break' => [
        $document([new AstNode('definition_list', ['markdownListFormat' => 'html'], [
            $definitionItem($definitionTerm([$text('Primary'), new AstNode('linebreak'), $text('Alias')]), [$definition([$paragraph('Definition')])]),
        ])]),
        '<dl><dt>Primary<br />Alias</dt><dd><p>Definition</p></dd></dl>',
    ],
    '38 definition body code block renders html code' => [
        $document([new AstNode('definition_list', ['markdownListFormat' => 'html'], [
            $definitionItem($definitionTerm([$text('Command')]), [$definition([$codeBlock('wp import')])]),
        ])]),
        '<dl><dt>Command</dt><dd><pre><code>wp import</code></pre></dd></dl>',
    ],
    '39 line block explicit html two lines' => [
        $document([new AstNode('line_block', ['markdownBlockFormat' => 'html'], [$line('one'), $line('two')])]),
        "<div class=\"line-block\">one<br />\ntwo</div>",
    ],
    '40 line block attributes merge line block class' => [
        $document([new AstNode('line_block', ['markdownBlockFormat' => 'html', 'classes' => ['review'], 'attributes' => ['data-kind' => 'verse']], [$line('one')])]),
        '<div class="line-block review" data-kind="verse">one</div>',
    ],
    '41 line block empty line stays explicit break' => [
        $document([new AstNode('line_block', ['markdownBlockFormat' => 'html'], [$line('one'), $line(), $line('two')])]),
        "<div class=\"line-block\">one<br />\n<br />\ntwo</div>",
    ],
    '42 blockquote explicit html preserves attrs' => [
        $document([new AstNode('blockquote', ['markdownBlockFormat' => 'html', 'classes' => ['review'], 'attributes' => ['data-source' => 'quote']], [$paragraph('quoted')])]),
        '<blockquote class="review" data-source="quote"><p>quoted</p></blockquote>',
    ],
    '43 code block explicit html preserves class' => [
        $document([$codeBlock('echo alpha', ['markdownBlockFormat' => 'html', 'classes' => ['php']])]),
        '<pre><code class="php">echo alpha</code></pre>',
    ],
    '44 code block explicit html sanitizes attrs' => [
        $document([$codeBlock('echo alpha', ['markdownCodeBlockFormat' => 'html', 'attributes' => ['onclick' => 'bad()', 'data-safe' => '1']])]),
        '<pre><code data-safe="1">echo alpha</code></pre>',
    ],
    '45 div explicit html preserves attrs' => [
        $document([new AstNode('div', ['markdownBlockFormat' => 'html', 'id' => 'note', 'classes' => ['callout']], [$paragraph('Body')])]),
        '<div id="note" class="callout"><p>Body</p></div>',
    ],
    '46 heading explicit html preserves attrs' => [
        $document([new AstNode('heading', ['markdownBlockFormat' => 'html', 'level' => 2, 'id' => 'review'], [$text('Review')])]),
        '<h2 id="review">Review</h2>',
    ],
    '47 paragraph explicit html escapes text' => [
        $document([new AstNode('paragraph', ['markdownBlockFormat' => 'html'], [$text('<review>')])]),
        '<p>&lt;review&gt;</p>',
    ],
    '48 horizontal rule explicit html' => [
        $document([new AstNode('horizontal_rule', ['markdownBlockFormat' => 'html'])]),
        '<hr />',
    ],
    '49 bullet item link inline renders html link' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([$link('packet', 'https://example.test/packet')]),
        ])]),
        '<ul><li><a href="https://example.test/packet">packet</a></li></ul>',
    ],
    '50 bullet item image inline renders html image' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([$image('alt', '/media.png')]),
        ])]),
        '<ul><li><img src="/media.png" alt="alt" /></li></ul>',
    ],
    '51 bullet item span inline preserves attrs' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([$span([$text('marked')], ['classes' => ['mark'], 'attributes' => ['data-state' => 'new']])]),
        ])]),
        '<ul><li><span class="mark" data-state="new">marked</span></li></ul>',
    ],
    '52 explicit html list deduplicates classes' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html', 'classes' => ['review'], 'htmlAttributes' => ['class' => 'review queue']], [
            $listItem([$text('alpha')]),
        ])]),
        '<ul class="review queue"><li>alpha</li></ul>',
    ],
    '53 definition list html attributes are preserved' => [
        $document([new AstNode('definition_list', ['markdownListFormat' => 'html', 'htmlAttributes' => ['class' => 'glossary', 'data-source' => 'batch']], [
            $definitionItem($definitionTerm([$text('Term')]), [$definition([$paragraph('Definition')])]),
        ])]),
        '<dl class="glossary" data-source="batch"><dt>Term</dt><dd><p>Definition</p></dd></dl>',
    ],
    '54 line block text is escaped in html fallback' => [
        $document([new AstNode('line_block', ['markdownBlockFormat' => 'html'], [new AstNode('line', ['text' => '<tag>'])])]),
        '<div class="line-block">&lt;tag&gt;</div>',
    ],
    '55 bullet item raw html inline is preserved' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html'], [
            $listItem([new AstNode('raw_html_inline', ['html' => '<span>raw</span>'])]),
        ])]),
        '<ul><li><span>raw</span></li></ul>',
    ],
    '56 blockquote explicit html nested list' => [
        $document([new AstNode('blockquote', ['markdownBlockFormat' => 'html'], [
            $bulletList([$listItem([$text('point')])]),
        ])]),
        '<blockquote><ul><li>point</li></ul></blockquote>',
    ],
    '57 ordered explicit html nested blockquote' => [
        $document([new AstNode('ordered_list', ['markdownListFormat' => 'html'], [
            $listItem([$blockquote([$paragraph('quoted')])]),
        ])]),
        '<ol><li><blockquote><p>quoted</p></blockquote></li></ol>',
    ],
    '58 code block explicit html escapes content' => [
        $document([$codeBlock('<script>alert(1)</script>', ['markdownBlockFormat' => 'html'])]),
        '<pre><code>&lt;script&gt;alert(1)&lt;/script&gt;</code></pre>',
    ],
    '59 explicit html list preserves aria attrs' => [
        $document([new AstNode('bullet_list', ['markdownListFormat' => 'html', 'attributes' => ['aria-label' => 'Review queue']], [
            $listItem([$text('alpha')]),
        ])]),
        '<ul aria-label="Review queue"><li>alpha</li></ul>',
    ],
    '60 explicit html ordered list preserves item title' => [
        $document([new AstNode('ordered_list', ['markdownListFormat' => 'html'], [
            $listItem([$text('alpha')], ['attributes' => ['title' => 'First step']]),
        ])]),
        '<ol><li title="First step">alpha</li></ol>',
    ],
];

foreach ($htmlFallbackCases as $name => $case) {
    $tests['maps upstream markdown writer html block list code fallback surge ' . $name] =
        static function (TestRunner $t) use ($case): void {
            $t->same($case[1], (new MarkdownWriter())->write($case[0]));
        };
}

$tests['maps upstream markdown writer adjacent top-level code blocks remain separate'] =
    static function (TestRunner $t) use ($document, $codeBlock): void {
        $markdown = (new MarkdownWriter())->write($document(
            $codeBlock('first top-level literal'),
            $codeBlock('second top-level literal')
        ));

        $t->same("    first top-level literal\n\n<!-- -->\n\n    second top-level literal", $markdown);

        $roundTrip = (new MarkdownReader())->read($markdown);
        $t->same('code_block', $roundTrip->children[0]->type ?? 'missing');
        $t->same('first top-level literal', $roundTrip->children[0]->attr('text'));
        $t->same('raw_html', $roundTrip->children[1]->type ?? 'missing');
        $t->same('code_block', $roundTrip->children[2]->type ?? 'missing');
        $t->same('second top-level literal', $roundTrip->children[2]->attr('text'));
    };

$blockSignatures = static function (AstNode $item): array {
    $signatures = [];
    foreach ($item->children as $child) {
        if ($child->type === 'raw_html' || $child->type === 'paragraph' || $child->type === 'text') {
            continue;
        }

        if ($child->type === 'code_block') {
            $signatures[] = 'code_block:' . $child->attr('text', '');
            continue;
        }

        if ($child->type === 'bullet_list' || $child->type === 'ordered_list' || $child->type === 'definition_list') {
            $signatures[] = $child->type . ':' . count($child->children);
        }
    }

    return $signatures;
};

$outerListFamilies = [
    'dash bullet outer' => [
        'document' => static fn (array $children): AstNode => $document($bulletListNode([
            $listItemNode(array_merge([$textNode('outer item')], $children)),
        ])),
        'options' => [],
    ],
    'plus bullet outer' => [
        'document' => static fn (array $children): AstNode => $document($bulletListNode([
            $listItemNode(array_merge([$textNode('outer item')], $children)),
        ])),
        'options' => ['bulletListMarker' => 'plus'],
    ],
    'star bullet outer' => [
        'document' => static fn (array $children): AstNode => $document($bulletListNode([
            $listItemNode(array_merge([$textNode('outer item')], $children)),
        ])),
        'options' => ['bulletListMarker' => 'star'],
    ],
    'decimal ordered outer' => [
        'document' => static fn (array $children): AstNode => $document($orderedListNode([
            $listItemNode(array_merge([$textNode('outer item')], $children)),
        ])),
        'options' => [],
    ],
    'lower alpha ordered outer' => [
        'document' => static fn (array $children): AstNode => $document($orderedListNode([
            $listItemNode(array_merge([$textNode('outer item')], $children)),
        ], ['style' => 'lower_alpha'])),
        'options' => [],
    ],
    'upper roman ordered outer' => [
        'document' => static fn (array $children): AstNode => $document($orderedListNode([
            $listItemNode(array_merge([$textNode('outer item')], $children)),
        ], ['style' => 'upper_roman', 'start' => 4])),
        'options' => [],
    ],
    'default ordered outer' => [
        'document' => static fn (array $children): AstNode => $document($orderedListNode([
            $listItemNode(array_merge([$textNode('outer item')], $children)),
        ], ['style' => 'default'])),
        'options' => [],
    ],
    'example ordered outer' => [
        'document' => static fn (array $children): AstNode => $document($orderedListNode([
            $listItemNode(array_merge([$textNode('outer item')], $children)),
        ], ['style' => 'example'])),
        'options' => [],
    ],
];

$adjacentBlockCases = [
    'keeps adjacent bullet lists separate' => [
        'children' => static fn (): array => [
            $bulletListNode([$listItemNode([$textNode('first bullet child')])]),
            $bulletListNode([$listItemNode([$textNode('second bullet child')])]),
        ],
        'expected' => ['bullet_list:1', 'bullet_list:1'],
    ],
    'keeps adjacent decimal ordered lists separate' => [
        'children' => static fn (): array => [
            $orderedListNode([$listItemNode([$textNode('first ordered child')])]),
            $orderedListNode([$listItemNode([$textNode('second ordered child')])]),
        ],
        'expected' => ['ordered_list:1', 'ordered_list:1'],
    ],
    'keeps adjacent lower alpha ordered lists separate' => [
        'children' => static fn (): array => [
            $orderedListNode([$listItemNode([$textNode('first alpha child')])], ['style' => 'lower_alpha']),
            $orderedListNode([$listItemNode([$textNode('second alpha child')])], ['style' => 'lower_alpha']),
        ],
        'expected' => ['ordered_list:1', 'ordered_list:1'],
    ],
    'keeps adjacent definition lists separate' => [
        'children' => static fn (): array => [
            $definitionListNode('First term', 'first definition'),
            $definitionListNode('Second term', 'second definition'),
        ],
        'expected' => ['definition_list:1', 'definition_list:1'],
    ],
    'keeps bullet list before indented code separate' => [
        'children' => static fn (): array => [
            $bulletListNode([$listItemNode([$textNode('bullet before code')])]),
            $codeBlock('literal code after bullet'),
        ],
        'expected' => ['bullet_list:1', 'code_block:literal code after bullet'],
    ],
    'keeps ordered list before indented code separate' => [
        'children' => static fn (): array => [
            $orderedListNode([$listItemNode([$textNode('ordered before code')])]),
            $codeBlock('literal code after ordered'),
        ],
        'expected' => ['ordered_list:1', 'code_block:literal code after ordered'],
    ],
    'keeps definition list before indented code separate' => [
        'children' => static fn (): array => [
            $definitionListNode('Code term', 'definition before code'),
            $codeBlock('literal code after definition'),
        ],
        'expected' => ['definition_list:1', 'code_block:literal code after definition'],
    ],
    'keeps adjacent indented code blocks separate' => [
        'children' => static fn (): array => [
            $codeBlock('first literal code'),
            $codeBlock('second literal code'),
        ],
        'expected' => ['code_block:first literal code', 'code_block:second literal code'],
    ],
];

foreach ($outerListFamilies as $outerName => $outer) {
    foreach ($adjacentBlockCases as $caseName => $case) {
        $tests['maps upstream markdown writer nested block separator surge ' . $outerName . ' ' . $caseName] =
            static function (TestRunner $t) use ($outer, $case, $blockSignatures): void {
                $markdown = (new MarkdownWriter($outer['options']))->write($outer['document']($case['children']()));

                $t->same(1, substr_count($markdown, '<!-- -->'), $markdown);

                $roundTrip = (new MarkdownReader())->read($markdown);
                $list = $roundTrip->children[0] ?? new AstNode('missing');
                $item = $list->children[0] ?? new AstNode('missing');

                $t->same($case['expected'], $blockSignatures($item), $markdown);
            };
    }
}

$definitionTerm = static fn (array $children): AstNode => new AstNode('definition_term', [], $children);
$definition = static fn (array $children, array $attrs = []): AstNode => new AstNode('definition', $attrs, $children);
$definitionItem = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([$term], $definitions)
);
$definitionList = static fn (array $items): AstNode => new AstNode('definition_list', [], $items);
$definitionDocument = static function (array $definitionChildren, string $termText = 'Term') use (
    $definition,
    $definitionItem,
    $definitionList,
    $definitionTerm,
    $document,
    $text
): AstNode {
    return $document($definitionList([
        $definitionItem($definitionTerm([$text($termText)]), [
            $definition($definitionChildren),
        ]),
    ]));
};
$definitionExpected = static function (string $body, string $termText = 'Term'): string {
    $lines = [$termText, ':', ''];
    foreach (explode("\n", $body) as $line) {
        $lines[] = $line === '' ? '' : '    ' . $line;
    }

    return implode("\n", $lines);
};
$firstDefinitionChildren = static function (AstNode $document): array {
    $definition = $document->children[0]->children[0]->children[1] ?? null;

    return $definition instanceof AstNode ? $definition->children : [];
};
$line = static fn (string $value = ''): AstNode => $value === ''
    ? new AstNode('line')
    : new AstNode('line', [], [$text($value)]);
$lineBlock = static fn (array $lines): AstNode => new AstNode('line_block', [], $lines);
$definitionHeading = static fn (string $value, int $level = 1, array $attrs = []): AstNode => new AstNode(
    'heading',
    array_replace(['level' => $level], $attrs),
    [$text($value)]
);
$textCell = static fn (string $value): AstNode => new AstNode('table_cell', ['text' => $value], [$text($value)]);
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
$table = static function (array $headers, array $values, array $alignments = []) use ($row, $textCell): AstNode {
    return new AstNode('table', ['alignments' => $alignments], [
        new AstNode('table_head', [], [
            $row(array_map($textCell, $headers)),
        ]),
        new AstNode('table_body', [], [
            $row(array_map($textCell, $values)),
        ]),
    ]);
};
$documentBody = static fn (AstNode $block): AstNode => new AstNode('document', [], [$block]);

$definitionCodeCases = [
    '01 plain code starts definition body' => [
        'children' => [$codeBlock('echo alpha')],
        'body' => '    echo alpha',
        'types' => ['code_block'],
        'codeText' => 'echo alpha',
    ],
    '02 multiline code starts definition body' => [
        'children' => [$codeBlock("echo alpha\necho beta")],
        'body' => "    echo alpha\n    echo beta",
        'types' => ['code_block'],
        'codeText' => "echo alpha\necho beta",
    ],
    '03 code with internal blank starts definition body' => [
        'children' => [$codeBlock("before\n\nafter")],
        'body' => "    before\n    \n    after",
        'types' => ['code_block'],
        'codeText' => "before\n\nafter",
    ],
    '04 code preserves bullet-looking text' => [
        'children' => [$codeBlock("- not a list\n+ still code")],
        'body' => "    - not a list\n    + still code",
        'types' => ['code_block'],
        'codeText' => "- not a list\n+ still code",
    ],
    '05 code preserves ordered-looking text' => [
        'children' => [$codeBlock("1. not ordered\n#. not default")],
        'body' => "    1. not ordered\n    #. not default",
        'types' => ['code_block'],
        'codeText' => "1. not ordered\n#. not default",
    ],
    '06 code preserves heading-looking text' => [
        'children' => [$codeBlock("# not heading\n## still code")],
        'body' => "    # not heading\n    ## still code",
        'types' => ['code_block'],
        'codeText' => "# not heading\n## still code",
    ],
    '07 code preserves blockquote-looking text' => [
        'children' => [$codeBlock("> quoted literally")],
        'body' => '    > quoted literally',
        'types' => ['code_block'],
        'codeText' => '> quoted literally',
    ],
    '08 code preserves leading spaces' => [
        'children' => [$codeBlock('  literal indent')],
        'body' => '      literal indent',
        'types' => ['code_block'],
        'codeText' => '  literal indent',
    ],
    '09 code preserves backtick fence text' => [
        'children' => [$codeBlock("alpha\n```\nbeta")],
        'body' => "    alpha\n    ```\n    beta",
        'types' => ['code_block'],
        'codeText' => "alpha\n```\nbeta",
    ],
    '10 code preserves dollar and backslash text' => [
        'children' => [$codeBlock('echo $value \\ $other;')],
        'body' => '    echo $value \ $other;',
        'types' => ['code_block'],
        'codeText' => 'echo $value \\ $other;',
    ],
    '11 fenced option starts plain code body' => [
        'children' => [$codeBlock('echo fenced')],
        'body' => "```\necho fenced\n```",
        'types' => ['code_block'],
        'codeText' => 'echo fenced',
        'options' => ['fencedCodeBlocks' => true],
    ],
    '12 fenced option grows backtick code body' => [
        'children' => [$codeBlock('contains ``` fence')],
        'body' => "````\ncontains ``` fence\n````",
        'types' => ['code_block'],
        'codeText' => 'contains ``` fence',
        'options' => ['fencedCodeBlocks' => true],
    ],
    '13 language class starts fenced code body' => [
        'children' => [$codeBlock('echo php', ['classes' => ['php']])],
        'body' => "```php\necho php\n```",
        'types' => ['code_block'],
        'codeText' => 'echo php',
        'classes' => ['php'],
    ],
    '14 id and language start fenced code body' => [
        'children' => [$codeBlock('echo id', ['id' => 'snippet', 'classes' => ['php']])],
        'body' => "```{#snippet .php}\necho id\n```",
        'types' => ['code_block'],
        'codeText' => 'echo id',
        'classes' => ['php'],
    ],
    '15 multi class and attribute start fenced code body' => [
        'children' => [$codeBlock('echo attrs', ['classes' => ['php', 'numberLines'], 'attributes' => ['data-kind' => 'fixture']])],
        'body' => "```{.php .numberLines data-kind=\"fixture\"}\necho attrs\n```",
        'types' => ['code_block'],
        'codeText' => 'echo attrs',
        'classes' => ['php', 'numberLines'],
    ],
    '16 info string starts fenced code body' => [
        'children' => [$codeBlock('echo info', ['info' => 'php startFrom=7'])],
        'body' => "``` php startFrom=7\necho info\n```",
        'types' => ['code_block'],
        'codeText' => 'echo info',
    ],
    '17 braced info string starts fenced code body' => [
        'children' => [$codeBlock('echo info', ['info' => '{.php .numberLines startFrom="4"}'])],
        'body' => "``` {.php .numberLines startFrom=\"4\"}\necho info\n```",
        'types' => ['code_block'],
        'codeText' => 'echo info',
    ],
    '18 fenced option applies to attributed code body' => [
        'children' => [$codeBlock('echo fenced php', ['classes' => ['php']])],
        'body' => "```php\necho fenced php\n```",
        'types' => ['code_block'],
        'codeText' => 'echo fenced php',
        'classes' => ['php'],
        'options' => ['fencedCodeBlocks' => true],
    ],
    '19 code body followed by paragraph keeps both blocks' => [
        'children' => [$codeBlock('echo alpha'), $paragraph('after')],
        'body' => "    echo alpha\n\nafter",
        'types' => ['code_block', 'paragraph'],
        'codeText' => 'echo alpha',
    ],
    '20 code body followed by heading keeps both blocks' => [
        'children' => [$codeBlock('echo alpha'), $definitionHeading('After', 2)],
        'body' => "    echo alpha\n\n## After",
        'types' => ['code_block', 'heading'],
        'codeText' => 'echo alpha',
    ],
];

foreach ($definitionCodeCases as $name => $case) {
    $tests['maps upstream markdown writer definition body leading code surge ' . $name] =
        static function (TestRunner $t) use ($case, $definitionDocument, $definitionExpected, $firstDefinitionChildren): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($definitionDocument($case['children']));
            $t->same($definitionExpected($case['body']), $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $children = $firstDefinitionChildren($roundTrip);
            $t->same($case['types'], array_map(static fn (AstNode $node): string => $node->type, $children));

            $code = $children[0] ?? null;
            $t->true($code instanceof AstNode && $code->type === 'code_block', 'Expected leading definition code block');
            if ($code instanceof AstNode) {
                $t->same($case['codeText'], $code->attr('text'));
                if (isset($case['classes'])) {
                    $t->same($case['classes'], $code->attr('classes'));
                }
            }
        };
}

$definitionHeadingCases = [];
for ($level = 1; $level <= 6; $level++) {
    $definitionHeadingCases['0' . $level . ' heading level ' . $level . ' starts definition body'] = [
        'children' => [$definitionHeading('Definition heading ' . $level, $level)],
        'body' => str_repeat('#', $level) . ' Definition heading ' . $level,
        'level' => $level,
        'text' => 'Definition heading ' . $level,
    ];
}
$definitionHeadingCases += [
    '07 heading attributes start definition body' => [
        'children' => [$definitionHeading('Attributed heading', 3, ['id' => 'def-head', 'classes' => ['review'], 'attributes' => ['data-kind' => 'definition']])],
        'body' => '### Attributed heading {#def-head .review data-kind="definition"}',
        'level' => 3,
        'text' => 'Attributed heading',
    ],
    '08 heading after code stays heading' => [
        'children' => [$definitionHeading('First heading', 2), $paragraph('after heading')],
        'body' => "## First heading\n\nafter heading",
        'level' => 2,
        'text' => 'First heading',
        'types' => ['heading', 'paragraph'],
    ],
    '09 heading with ordered-marker text is escaped' => [
        'children' => [$definitionHeading('1. literal marker', 2)],
        'body' => '## 1\. literal marker',
        'level' => 2,
        'text' => '1\. literal marker',
    ],
    '10 heading with hash text is escaped' => [
        'children' => [$definitionHeading('# literal hash', 2)],
        'body' => '## \# literal hash',
        'level' => 2,
        'text' => '\# literal hash',
    ],
];

foreach ($definitionHeadingCases as $name => $case) {
    $tests['maps upstream markdown writer definition body leading heading surge ' . $name] =
        static function (TestRunner $t) use ($case, $definitionDocument, $definitionExpected, $firstDefinitionChildren): void {
            $markdown = (new MarkdownWriter())->write($definitionDocument($case['children']));
            $t->same($definitionExpected($case['body']), $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $children = $firstDefinitionChildren($roundTrip);
            $t->same($case['types'] ?? ['heading'], array_map(static fn (AstNode $node): string => $node->type, $children));

            $heading = $children[0] ?? null;
            $t->true($heading instanceof AstNode && $heading->type === 'heading', 'Expected leading definition heading');
            if ($heading instanceof AstNode) {
                $t->same($case['level'], $heading->attr('level'));
                $t->same($case['text'], $heading->attr('text'));
            }
        };
}

$definitionLineBlockCases = [
    '01 one line block starts definition body' => [
        'children' => [$lineBlock([$line('alpha')])],
        'body' => '| alpha',
        'lines' => ['alpha'],
    ],
    '02 empty line is preserved in leading line block' => [
        'children' => [$lineBlock([$line('alpha'), $line(), $line('beta')])],
        'body' => "| alpha\n|\n| beta",
        'lines' => ['alpha', '', 'beta'],
    ],
    '03 three line verse starts definition body' => [
        'children' => [$lineBlock([$line('roses'), $line('violets'), $line('review')])],
        'body' => "| roses\n| violets\n| review",
        'lines' => ['roses', 'violets', 'review'],
    ],
    '04 list-looking line remains line block text' => [
        'children' => [$lineBlock([$line('- not list'), $line('1. not ordered')])],
        'body' => "| \\- not list\n| 1\\. not ordered",
        'lines' => ['\\- not list', '1\\. not ordered'],
    ],
    '05 heading-looking line remains line block text' => [
        'children' => [$lineBlock([$line('# not heading')])],
        'body' => '| \# not heading',
        'lines' => ['\\# not heading'],
    ],
    '06 line block followed by paragraph keeps both blocks' => [
        'children' => [$lineBlock([$line('alpha')]), $paragraph('after')],
        'body' => "| alpha\n\nafter",
        'lines' => ['alpha'],
        'types' => ['line_block', 'paragraph'],
    ],
    '07 line block followed by code keeps both blocks' => [
        'children' => [$lineBlock([$line('alpha')]), $codeBlock('echo after')],
        'body' => "| alpha\n\n    echo after",
        'lines' => ['alpha'],
        'types' => ['line_block', 'code_block'],
    ],
    '08 soft line text keeps definition marker escaped' => [
        'children' => [$lineBlock([$line(': not definition'), $line('~ not alternate')])],
        'body' => "| \\: not definition\n| \\~ not alternate",
        'lines' => ['\\: not definition', '\\~ not alternate'],
    ],
    '09 unicode line block starts definition body' => [
        'children' => [$lineBlock([$line('naive cafe'), $line('resume review')])],
        'body' => "| naive cafe\n| resume review",
        'lines' => ['naive cafe', 'resume review'],
    ],
    '10 trailing empty line survives leading line block' => [
        'children' => [$lineBlock([$line('alpha'), $line()])],
        'body' => "| alpha\n|",
        'lines' => ['alpha', ''],
    ],
];

foreach ($definitionLineBlockCases as $name => $case) {
    $tests['maps upstream markdown writer definition body leading line block surge ' . $name] =
        static function (TestRunner $t) use ($case, $definitionDocument, $definitionExpected, $firstDefinitionChildren): void {
            $markdown = (new MarkdownWriter())->write($definitionDocument($case['children']));
            $t->same($definitionExpected($case['body']), $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $children = $firstDefinitionChildren($roundTrip);
            $t->same($case['types'] ?? ['line_block'], array_map(static fn (AstNode $node): string => $node->type, $children));

            $lineBlock = $children[0] ?? null;
            $t->true($lineBlock instanceof AstNode && $lineBlock->type === 'line_block', 'Expected leading definition line block');
            if ($lineBlock instanceof AstNode) {
                $t->same($case['lines'], array_map(static fn (AstNode $line): string => (string) $line->attr('text', ''), $lineBlock->children));
            }
        };
}

$definitionTableCases = [
    '01 two column default table starts definition body' => $table(['Metric', 'Value'], ['Probe', '12'], ['left', 'right']),
    '02 one column left table starts definition body' => $table(['Name'], ['Alpha'], ['left']),
    '03 one column right table starts definition body' => $table(['Count'], ['7'], ['right']),
    '04 one column center table starts definition body' => $table(['State'], ['Ready'], ['center']),
    '05 one column default alignment table starts definition body' => $table(['Field'], ['Value'], ['default']),
    '06 wide text table starts definition body' => $table(['Column', 'Description'], ['alpha', 'definition table'], ['left', 'left']),
    '07 escaped pipe table starts definition body' => $table(['Metric', 'Value'], ['Pipe', 'A | B'], ['left', 'left']),
    '08 escaped marker table starts definition body' => $table(['Marker'], ['1. literal'], ['left']),
    '09 hash text table starts definition body' => $table(['Heading'], ['# literal'], ['left']),
    '10 three column table starts definition body' => $table(['A', 'B', 'C'], ['1', '2', '3'], ['left', 'center', 'right']),
];

foreach ($definitionTableCases as $name => $tableNode) {
    $tests['maps upstream markdown writer definition body leading table surge ' . $name] =
        static function (TestRunner $t) use ($tableNode, $definitionDocument, $definitionExpected, $firstDefinitionChildren, $documentBody): void {
            $body = (new MarkdownWriter())->write($documentBody($tableNode));
            $markdown = (new MarkdownWriter())->write($definitionDocument([$tableNode]));
            $t->same($definitionExpected($body), $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $children = $firstDefinitionChildren($roundTrip);
            $t->same(['table'], array_map(static fn (AstNode $node): string => $node->type, $children));
        };
}

return $tests;
