<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$findFirstNodeOfType = static function (AstNode $node, string $type) use (&$findFirstNodeOfType): ?AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $found = $findFirstNodeOfType($child, $type);
        if ($found !== null) {
            return $found;
        }
    }

    return null;
};

$readFirstNodeOfType = static function (string $markdown, string $type) use ($findFirstNodeOfType): AstNode {
    $document = (new MarkdownReader())->read($markdown);
    $node = $findFirstNodeOfType($document, $type);

    return $node ?? new AstNode('missing');
};

$inlineTypes = static fn (array $nodes): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $nodes
);

$inlineText = static function (array $nodes) use (&$inlineText): string {
    $text = '';
    foreach ($nodes as $node) {
        if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
            $text .= (string) $node->attr('text', '');
            continue;
        }
        if ($node->type === 'raw_inline') {
            $text .= (string) $node->attr('text', '');
            continue;
        }
        if ($node->type === 'raw_html_inline') {
            $text .= (string) $node->attr('text', $node->attr('html', ''));
            continue;
        }
        if ($node->type === 'raw_tex') {
            $text .= (string) $node->attr('tex', '');
            continue;
        }
        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            $text .= "\n";
            continue;
        }

        $text .= $inlineText($node->children);
    }

    return $text;
};

$assertInlineResidualNode = static function (
    TestRunner $t,
    AstNode $node,
    array $case,
    string $name
) use ($inlineText, $inlineTypes): void {
    $t->same($case['kind'], $node->type, $name . ' node type');
    $t->same($case['url'], $node->attr('url'), $name . ' url');
    $t->same($case['title'] ?? null, $node->attr('title'), $name . ' title');
    $t->same($case['types'], $inlineTypes($node->children), $name . ' inline types');
    $t->same($case['text'], $inlineText($node->children), $name . ' inline text');

    foreach ($case['childAttrs'] ?? [] as $index => $expectedAttrs) {
        $child = $node->children[$index] ?? new AstNode('missing');
        foreach ($expectedAttrs as $attr => $expected) {
            $t->same($expected, $child->attr($attr), $name . ' child ' . $index . ' ' . $attr);
        }
    }

    if ($case['kind'] !== 'image') {
        return;
    }

    $t->same($case['text'], $node->attr('caption'), $name . ' image caption');
    $t->same($case['text'], $node->attr('alt'), $name . ' image alt');
};

$cases = [
    'direct code attr quoted close bracket' => [
        'kind' => 'link',
        'markdown' => '[`x`{data-close="]"}](/code-attr-close "Code &amp; title")',
        'url' => '/code-attr-close',
        'title' => 'Code & title',
        'types' => ['code'],
        'text' => 'x',
        'childAttrs' => [
            0 => [
                'attributes' => ['data-close' => ']'],
                'htmlAttributes' => ['data-close' => ']'],
            ],
        ],
    ],
    'direct code attr quoted open bracket' => [
        'kind' => 'link',
        'markdown' => '[`x`{data-open="["}](/code-attr-open)',
        'url' => '/code-attr-open',
        'types' => ['code'],
        'text' => 'x',
        'childAttrs' => [
            0 => [
                'attributes' => ['data-open' => '['],
                'htmlAttributes' => ['data-open' => '['],
            ],
        ],
    ],
    'direct code attr escaped id class bracket' => [
        'kind' => 'link',
        'markdown' => '[`x`{#id\] .class\] data-open="["}](/code-attr-escaped)',
        'url' => '/code-attr-escaped',
        'types' => ['code'],
        'text' => 'x',
        'childAttrs' => [
            0 => [
                'id' => 'id]',
                'classes' => ['class]'],
                'attributes' => ['data-open' => '['],
                'htmlAttributes' => ['id' => 'id]', 'class' => 'class]', 'data-open' => '['],
            ],
        ],
    ],
    'direct code attr entity bracket' => [
        'kind' => 'link',
        'markdown' => '[`x`{data-close="&#93;"}](/code-attr-entity)',
        'url' => '/code-attr-entity',
        'types' => ['code'],
        'text' => 'x',
        'childAttrs' => [
            0 => [
                'attributes' => ['data-close' => ']'],
                'htmlAttributes' => ['data-close' => ']'],
            ],
        ],
    ],
    'direct code spaced attr literal close bracket' => [
        'kind' => 'link',
        'markdown' => '[`x` {data-close="]"}](/code-spaced-literal)',
        'url' => '/code-spaced-literal',
        'types' => ['code', 'text'],
        'text' => 'x {data-close="]"}',
    ],
    'direct raw inline html bracket text' => [
        'kind' => 'link',
        'markdown' => '[`<span data-x="]">x</span>`{=html}](/raw-html "Raw &amp; title")',
        'url' => '/raw-html',
        'title' => 'Raw & title',
        'types' => ['raw_html_inline'],
        'text' => '<span data-x="]">x</span>',
        'childAttrs' => [
            0 => [
                'format' => 'html',
                'text' => '<span data-x="]">x</span>',
                'html' => '<span data-x="]">x</span>',
            ],
        ],
    ],
    'direct raw inline markdown bracket text' => [
        'kind' => 'link',
        'markdown' => '[`**a]b**`{=markdown}](/raw-markdown)',
        'url' => '/raw-markdown',
        'types' => ['raw_inline'],
        'text' => '**a]b**',
        'childAttrs' => [
            0 => ['format' => 'markdown', 'text' => '**a]b**'],
        ],
    ],
    'direct dollar math bracket text' => [
        'kind' => 'link',
        'markdown' => '[$a]b$](/math-dollar)',
        'url' => '/math-dollar',
        'types' => ['math'],
        'text' => 'a]b',
        'childAttrs' => [
            0 => ['display' => false, 'text' => 'a]b'],
        ],
    ],
    'direct dollar math attr close bracket' => [
        'kind' => 'link',
        'markdown' => '[$a]b${data-close="]"}](/math-attr)',
        'url' => '/math-attr',
        'types' => ['math'],
        'text' => 'a]b',
        'childAttrs' => [
            0 => [
                'display' => false,
                'text' => 'a]b',
                'attributes' => ['data-close' => ']'],
                'htmlAttributes' => ['data-close' => ']'],
            ],
        ],
    ],
    'direct single backslash math bracket text' => [
        'kind' => 'link',
        'markdown' => '[\(a]b\)](/math-single-backslash)',
        'url' => '/math-single-backslash',
        'types' => ['math'],
        'text' => 'a]b',
        'childAttrs' => [
            0 => ['display' => false, 'text' => 'a]b'],
        ],
    ],
    'direct raw tex brace bracket text' => [
        'kind' => 'link',
        'markdown' => '[\textbf{a]b}](/raw-tex)',
        'url' => '/raw-tex',
        'types' => ['raw_tex'],
        'text' => '\textbf{a]b}',
        'childAttrs' => [
            0 => ['tex' => '\textbf{a]b}', 'command' => 'textbf'],
        ],
    ],
    'reference code attr quoted close bracket' => [
        'kind' => 'link',
        'markdown' => "[`x`{data-close=\"]\"}][ref-code]\n\n[ref-code]: /ref-code \"Ref &amp; title\"",
        'url' => '/ref-code',
        'title' => 'Ref & title',
        'types' => ['code'],
        'text' => 'x',
        'childAttrs' => [
            0 => [
                'attributes' => ['data-close' => ']'],
                'htmlAttributes' => ['data-close' => ']'],
            ],
        ],
    ],
    'reference raw inline html bracket text' => [
        'kind' => 'link',
        'markdown' => "[`<b>x]</b>`{=html}][ref-raw]\n\n[ref-raw]: /ref-raw",
        'url' => '/ref-raw',
        'types' => ['raw_html_inline'],
        'text' => '<b>x]</b>',
        'childAttrs' => [
            0 => [
                'format' => 'html',
                'text' => '<b>x]</b>',
                'html' => '<b>x]</b>',
            ],
        ],
    ],
    'reference dollar math attr close bracket' => [
        'kind' => 'link',
        'markdown' => '[$a]b${data-close="]"}][ref-math]' . "\n\n" . '[ref-math]: /ref-math',
        'url' => '/ref-math',
        'types' => ['math'],
        'text' => 'a]b',
        'childAttrs' => [
            0 => [
                'display' => false,
                'text' => 'a]b',
                'attributes' => ['data-close' => ']'],
                'htmlAttributes' => ['data-close' => ']'],
            ],
        ],
    ],
    'reference raw tex brace bracket text' => [
        'kind' => 'link',
        'markdown' => '[\textbf{a]b}][ref-tex]' . "\n\n" . '[ref-tex]: /ref-tex',
        'url' => '/ref-tex',
        'types' => ['raw_tex'],
        'text' => '\textbf{a]b}',
        'childAttrs' => [
            0 => ['tex' => '\textbf{a]b}', 'command' => 'textbf'],
        ],
    ],
    'image code attr quoted close bracket' => [
        'kind' => 'image',
        'markdown' => 'Lead ![`x`{data-close="]"}](img-code.png "Image &amp; title") trail',
        'url' => 'img-code.png',
        'title' => 'Image & title',
        'types' => ['code'],
        'text' => 'x',
        'childAttrs' => [
            0 => [
                'attributes' => ['data-close' => ']'],
                'htmlAttributes' => ['data-close' => ']'],
            ],
        ],
    ],
    'image raw inline html bracket text' => [
        'kind' => 'image',
        'markdown' => 'Lead ![`<b>x]</b>`{=html}](img-raw.png) trail',
        'url' => 'img-raw.png',
        'types' => ['raw_html_inline'],
        'text' => '<b>x]</b>',
        'childAttrs' => [
            0 => [
                'format' => 'html',
                'text' => '<b>x]</b>',
                'html' => '<b>x]</b>',
            ],
        ],
    ],
    'image dollar math bracket text' => [
        'kind' => 'image',
        'markdown' => 'Lead ![$a]b$](img-math.png) trail',
        'url' => 'img-math.png',
        'types' => ['math'],
        'text' => 'a]b',
        'childAttrs' => [
            0 => ['display' => false, 'text' => 'a]b'],
        ],
    ],
    'image raw tex brace bracket text' => [
        'kind' => 'image',
        'markdown' => 'Lead ![\textbf{a]b}](img-tex.png) trail',
        'url' => 'img-tex.png',
        'types' => ['raw_tex'],
        'text' => '\textbf{a]b}',
        'childAttrs' => [
            0 => ['tex' => '\textbf{a]b}', 'command' => 'textbf'],
        ],
    ],
];

$tests = [];

$tests['maps upstream markdown inline label residuals across code attrs raw math and tex'] =
    static function (TestRunner $t) use ($cases, $readFirstNodeOfType, $assertInlineResidualNode): void {
        $mapped = 0;
        foreach ($cases as $name => $case) {
            $node = $readFirstNodeOfType($case['markdown'], $case['kind']);
            $assertInlineResidualNode($t, $node, $case, $name);
            $mapped++;
        }

        $t->same(19, $mapped);
    };

$tests['projects raw inline text through markdown reader paragraph and image summaries'] =
    static function (TestRunner $t) use ($readFirstNodeOfType): void {
        $paragraph = $readFirstNodeOfType('Before `<b>x]</b>`{=html} after.', 'paragraph');
        $t->same('Before <b>x]</b> after.', $paragraph->attr('text'));

        $image = $readFirstNodeOfType('Lead ![`<b>x]</b>`{=html}](img.png) trail', 'image');
        $t->same('<b>x]</b>', $image->attr('caption'));
        $t->same('<b>x]</b>', $image->attr('alt'));
    };

$tests['records markdown inline raw label residual mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(21, count($cases) + 2);
    };

return $tests;
