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
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
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

return $tests;
