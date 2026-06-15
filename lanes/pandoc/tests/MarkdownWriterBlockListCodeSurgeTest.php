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
$document = static fn (AstNode ...$blocks): AstNode => new AstNode('document', [], $blocks);

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
        'expected' => "#.  one\n\n#)  two",
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

return $tests;
