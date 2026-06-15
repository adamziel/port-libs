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

return $tests;
