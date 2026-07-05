<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$findFirst = null;
$findFirst = static function (AstNode $node, string $type) use (&$findFirst): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirst($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$plainText = null;
$plainText = static function (AstNode $node) use (&$plainText): string {
    if (in_array($node->type, ['text', 'code', 'math'], true)) {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$mergeOptions = static fn (array $base, array $extra): array => array_replace($base, $extra);

$profileCases = [
    [
        'name' => 'default reader profile',
        'options' => [],
        'enabled' => ['fenced_divs' => true, 'header_attributes' => true, 'fenced_code_attributes' => true, 'line_blocks' => true],
    ],
    [
        'name' => 'markdown profile',
        'options' => ['format' => 'markdown'],
        'enabled' => ['fenced_divs' => true, 'header_attributes' => true, 'fenced_code_attributes' => true, 'line_blocks' => true],
    ],
    [
        'name' => 'pandoc profile',
        'options' => ['format' => 'pandoc'],
        'enabled' => ['fenced_divs' => true, 'header_attributes' => true, 'fenced_code_attributes' => true, 'line_blocks' => true],
    ],
    [
        'name' => 'commonmark x profile',
        'options' => ['format' => 'commonmark_x'],
        'enabled' => ['fenced_divs' => true, 'header_attributes' => true, 'fenced_code_attributes' => true, 'line_blocks' => false],
    ],
    [
        'name' => 'commonmark format suffix enables containers',
        'options' => ['format' => 'commonmark+fenced_divs+header_attributes+fenced_code_attributes+line_blocks'],
        'enabled' => ['fenced_divs' => true, 'header_attributes' => true, 'fenced_code_attributes' => true, 'line_blocks' => true],
    ],
    [
        'name' => 'gfm format suffix enables containers',
        'options' => ['format' => 'gfm+fenced_divs+header_attributes+fenced_code_attributes+line_blocks'],
        'enabled' => ['fenced_divs' => true, 'header_attributes' => true, 'fenced_code_attributes' => true, 'line_blocks' => true],
    ],
    [
        'name' => 'commonmark string extensions enable containers',
        'options' => ['format' => 'commonmark', 'extensions' => '+fenced_divs +header_attributes +fenced_code_attributes +line_blocks'],
        'enabled' => ['fenced_divs' => true, 'header_attributes' => true, 'fenced_code_attributes' => true, 'line_blocks' => true],
    ],
    [
        'name' => 'gfm associative extensions enable containers',
        'options' => [
            'format' => 'gfm',
            'extensions' => [
                'native_divs' => true,
                'header_attributes' => true,
                'fenced_code_attributes' => true,
                'line_blocks' => true,
            ],
        ],
        'enabled' => ['fenced_divs' => true, 'header_attributes' => true, 'fenced_code_attributes' => true, 'line_blocks' => true],
    ],
    [
        'name' => 'commonmark profile leaves containers literal',
        'options' => ['format' => 'commonmark'],
        'enabled' => ['fenced_divs' => false, 'header_attributes' => false, 'fenced_code_attributes' => false, 'line_blocks' => false],
    ],
    [
        'name' => 'gfm profile leaves containers literal',
        'options' => ['format' => 'gfm'],
        'enabled' => ['fenced_divs' => false, 'header_attributes' => false, 'fenced_code_attributes' => false, 'line_blocks' => false],
    ],
    [
        'name' => 'markdown strict profile leaves containers literal',
        'options' => ['format' => 'markdown_strict'],
        'enabled' => ['fenced_divs' => false, 'header_attributes' => false, 'fenced_code_attributes' => false, 'line_blocks' => false],
    ],
    [
        'name' => 'markdown extension tokens disable containers',
        'options' => ['format' => 'markdown', 'extensions' => ['-fenced_divs', '-header_attributes', '-fenced_code_attributes', '-line_blocks']],
        'enabled' => ['fenced_divs' => false, 'header_attributes' => false, 'fenced_code_attributes' => false, 'line_blocks' => false],
    ],
    [
        'name' => 'multimarkdown keeps attributes not fenced divs',
        'options' => ['format' => 'markdown_mmd'],
        'enabled' => ['fenced_divs' => false, 'header_attributes' => true, 'fenced_code_attributes' => true, 'line_blocks' => false],
    ],
    [
        'name' => 'php extra keeps attributes not fenced divs',
        'options' => ['format' => 'markdown_phpextra'],
        'enabled' => ['fenced_divs' => false, 'header_attributes' => true, 'fenced_code_attributes' => true, 'line_blocks' => false],
    ],
];

$featureCases = [
    'fenced div attributes' => [
        'extension' => 'fenced_divs',
        'assertEnabled' => static function (TestRunner $t, array $options) use ($findFirst): void {
            $document = (new MarkdownReader($options))->read("::: {#box .review data-kind=\"div\"}\nBody **strong**.\n:::");
            $div = $document->children[0] ?? new AstNode('missing');

            $t->same('div', $div->type);
            $t->same('box', $div->attr('id'));
            $t->same(['review'], $div->attr('classes'));
            $t->same(['data-kind' => 'div'], $div->attr('attributes'));
            $t->same('strong', $findFirst($div, 'strong')->children[0]->attr('text'));
        },
        'assertDisabled' => static function (TestRunner $t, array $options) use ($findFirst, $plainText): void {
            $document = (new MarkdownReader($options))->read("::: {#box .review data-kind=\"div\"}\nBody **strong**.\n:::");

            $t->same('missing', $findFirst($document, 'div')->type);
            $t->contains('Body strong.', $plainText($document));
        },
    ],
    'nested fenced div attributes' => [
        'extension' => 'fenced_divs',
        'assertEnabled' => static function (TestRunner $t, array $options): void {
            $document = (new MarkdownReader($options))->read(":::: {.outer data-layer=\"1\"}\n::: {.inner data-layer=\"2\"}\nNested body.\n:::\n::::");
            $outer = $document->children[0] ?? new AstNode('missing');
            $inner = $outer->children[0] ?? new AstNode('missing');

            $t->same('div', $outer->type);
            $t->same(['outer'], $outer->attr('classes'));
            $t->same(['data-layer' => '1'], $outer->attr('attributes'));
            $t->same('div', $inner->type);
            $t->same(['inner'], $inner->attr('classes'));
            $t->same(['data-layer' => '2'], $inner->attr('attributes'));
        },
        'assertDisabled' => static function (TestRunner $t, array $options) use ($findFirst, $plainText): void {
            $document = (new MarkdownReader($options))->read(":::: {.outer data-layer=\"1\"}\n::: {.inner data-layer=\"2\"}\nNested body.\n:::\n::::");

            $t->same('missing', $findFirst($document, 'div')->type);
            $t->contains('Nested body.', $plainText($document));
        },
    ],
    'section fenced div attributes' => [
        'extension' => 'fenced_divs',
        'assertEnabled' => static function (TestRunner $t, array $options): void {
            $document = (new MarkdownReader(array_replace($options, ['sectionDivs' => true])))->read(implode("\n", [
                '::: {#outer .section .level1 data-kind="section"}',
                '## Child {#child .inside data-kind="heading"}',
                '',
                'Body.',
                ':::',
            ]));
            $outer = $document->children[0] ?? new AstNode('missing');
            $childSection = $outer->children[0] ?? new AstNode('missing');
            $heading = $childSection->children[0] ?? new AstNode('missing');

            $t->same('div', $outer->type);
            $t->same('outer', $outer->attr('id'));
            $t->same(['section', 'level1'], $outer->attr('classes'));
            $t->same('div', $childSection->type);
            $t->same('child', $childSection->attr('id'));
            $t->same(['section', 'level2', 'inside'], $childSection->attr('classes'));
            $t->same('heading', $heading->type);
            $t->same('', $heading->attr('id', ''));
        },
        'assertDisabled' => static function (TestRunner $t, array $options) use ($findFirst, $plainText): void {
            $document = (new MarkdownReader(array_replace($options, ['sectionDivs' => true])))->read(implode("\n", [
                '::: {#outer .section .level1 data-kind="section"}',
                '## Child {#child .inside data-kind="heading"}',
                '',
                'Body.',
                ':::',
            ]));
            $firstDiv = $findFirst($document, 'div');

            if ($firstDiv->type === 'div') {
                $t->true($firstDiv->attr('id', '') !== 'outer');
                $t->true(!in_array('level1', $firstDiv->attr('classes', []), true));
            }
            $t->contains('Child', $plainText($document));
        },
    ],
    'atx heading attributes' => [
        'extension' => 'header_attributes',
        'assertEnabled' => static function (TestRunner $t, array $options): void {
            $heading = (new MarkdownReader($options))->read('## Review {#review .packet data-kind="heading"}')->children[0] ?? new AstNode('missing');

            $t->same('heading', $heading->type);
            $t->same('Review', $heading->attr('text'));
            $t->same('review', $heading->attr('id'));
            $t->same(['packet'], $heading->attr('classes'));
            $t->same(['data-kind' => 'heading'], $heading->attr('attributes'));
        },
        'assertDisabled' => static function (TestRunner $t, array $options): void {
            $heading = (new MarkdownReader($options))->read('## Review {#review .packet data-kind="heading"}')->children[0] ?? new AstNode('missing');

            $t->same('heading', $heading->type);
            $t->contains('{#review .packet data-kind="heading"}', $heading->attr('text'));
            $t->true($heading->attr('id', '') !== 'review');
            $t->same([], $heading->attr('classes', []));
            $t->same([], $heading->attr('attributes', []));
        },
    ],
    'setext heading attributes' => [
        'extension' => 'header_attributes',
        'assertEnabled' => static function (TestRunner $t, array $options): void {
            $heading = (new MarkdownReader($options))->read("Setext Review {#setext .packet data-kind=\"setext\"}\n---")->children[0] ?? new AstNode('missing');

            $t->same('heading', $heading->type);
            $t->same('Setext Review', $heading->attr('text'));
            $t->same('setext', $heading->attr('id'));
            $t->same(['packet'], $heading->attr('classes'));
            $t->same(['data-kind' => 'setext'], $heading->attr('attributes'));
        },
        'assertDisabled' => static function (TestRunner $t, array $options): void {
            $heading = (new MarkdownReader($options))->read("Setext Review {#setext .packet data-kind=\"setext\"}\n---")->children[0] ?? new AstNode('missing');

            $t->same('heading', $heading->type);
            $t->contains('{#setext .packet data-kind="setext"}', $heading->attr('text'));
            $t->true($heading->attr('id', '') !== 'setext');
            $t->same([], $heading->attr('classes', []));
            $t->same([], $heading->attr('attributes', []));
        },
    ],
    'fenced code attributes' => [
        'extension' => 'fenced_code_attributes',
        'assertEnabled' => static function (TestRunner $t, array $options): void {
            $code = (new MarkdownReader($options))->read("``` {#src .php data-kind=\"code\"}\necho 1;\n```")->children[0] ?? new AstNode('missing');

            $t->same('code_block', $code->type);
            $t->same('src', $code->attr('id'));
            $t->same(['php'], $code->attr('classes'));
            $t->same(['data-kind' => 'code'], $code->attr('attributes'));
            $t->same('echo 1;', $code->attr('text'));
        },
        'assertDisabled' => static function (TestRunner $t, array $options): void {
            $code = (new MarkdownReader($options))->read("``` {#src .php data-kind=\"code\"}\necho 1;\n```")->children[0] ?? new AstNode('missing');

            $t->same('code_block', $code->type);
            $t->true($code->attr('id', '') !== 'src');
            $t->same([], $code->attr('attributes', []));
            $t->same('{#src', $code->attr('classes', [])[0] ?? null);
            $t->same('echo 1;', $code->attr('text'));
        },
    ],
    'line block extension' => [
        'extension' => 'line_blocks',
        'assertEnabled' => static function (TestRunner $t, array $options): void {
            $lineBlock = (new MarkdownReader($options))->read("| Alpha\n| Beta")->children[0] ?? new AstNode('missing');

            $t->same('line_block', $lineBlock->type);
            $t->same(['line', 'line'], array_map(static fn (AstNode $node): string => $node->type, $lineBlock->children));
            $t->same('Alpha', $lineBlock->children[0]->attr('text'));
            $t->same('Beta', $lineBlock->children[1]->attr('text'));
        },
        'assertDisabled' => static function (TestRunner $t, array $options) use ($plainText): void {
            $document = (new MarkdownReader($options))->read("| Alpha\n| Beta");
            $first = $document->children[0] ?? new AstNode('missing');

            $t->true($first->type !== 'line_block');
            $t->same("| Alpha\n| Beta", $plainText($document));
        },
    ],
];

$tests = [];

foreach ($profileCases as $profile) {
    foreach ($featureCases as $featureName => $feature) {
        $tests['maps upstream markdown reader container extension profile ' . $profile['name'] . ' ' . $featureName] =
            static function (TestRunner $t) use ($profile, $feature, $mergeOptions): void {
                $options = $mergeOptions($profile['options'], []);
                $enabled = $profile['enabled'][$feature['extension']] ?? false;
                if ($enabled) {
                    $feature['assertEnabled']($t, $options);
                    return;
                }

                $feature['assertDisabled']($t, $options);
            };
    }
}

$tests['records upstream markdown reader container extension profile surge mapped-case count'] =
    static function (TestRunner $t) use ($profileCases, $featureCases): void {
        $t->same(98, count($profileCases) * count($featureCases));
    };

$tests['maps same-level fenced div section as peer section with folded wrapper attributes'] =
    static function (TestRunner $t): void {
        $document = (new MarkdownReader([
            'format' => 'markdown+fenced_divs+native_divs+header_attributes',
            'sectionDivs' => true,
        ]))->read(implode("\n", [
            '# Outer',
            '',
            '::: {.callout data-kind="note"}',
            '# Inner {#inner .inside data-kind="heading"}',
            '',
            'Inside.',
            ':::',
            '',
            'After.',
        ]));
        $outer = $document->children[0] ?? new AstNode('missing');
        $inner = $document->children[1] ?? new AstNode('missing');
        $after = $document->children[2] ?? new AstNode('missing');
        $heading = $inner->children[0] ?? new AstNode('missing');

        $t->same(['div', 'div', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('outer', $outer->attr('id'));
        $t->same(['heading'], array_map(static fn (AstNode $node): string => $node->type, $outer->children));
        $t->same('inner', $inner->attr('id'));
        $t->same(['section', 'level1', 'inside', 'callout'], $inner->attr('classes'));
        $t->same(['data-kind' => 'heading'], $inner->attr('attributes'));
        $t->same('heading', $heading->type);
        $t->same('', $heading->attr('id', ''));
        $t->same('After.', $after->attr('text'));
    };

$tests['keeps explicit-id fenced div wrapper while closing same-level section boundary'] =
    static function (TestRunner $t): void {
        $document = (new MarkdownReader([
            'format' => 'markdown+fenced_divs+native_divs+header_attributes',
            'sectionDivs' => true,
        ]))->read(implode("\n", [
            '# Outer',
            '',
            '::: {#wrap .callout data-kind="note"}',
            '# Inner',
            '',
            'Inside.',
            ':::',
            '',
            'After.',
        ]));
        $outer = $document->children[0] ?? new AstNode('missing');
        $wrapper = $document->children[1] ?? new AstNode('missing');
        $inner = $wrapper->children[0] ?? new AstNode('missing');
        $after = $document->children[2] ?? new AstNode('missing');

        $t->same(['div', 'div', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('outer', $outer->attr('id'));
        $t->same(['heading'], array_map(static fn (AstNode $node): string => $node->type, $outer->children));
        $t->same('wrap', $wrapper->attr('id'));
        $t->same(['callout'], $wrapper->attr('classes'));
        $t->same(['data-kind' => 'note'], $wrapper->attr('attributes'));
        $t->same('inner', $inner->attr('id'));
        $t->same(['section', 'level1'], $inner->attr('classes'));
        $t->same('After.', $after->attr('text'));
    };

$tests['nests lower-level fenced div section and folds class-only wrapper attributes'] =
    static function (TestRunner $t): void {
        $document = (new MarkdownReader([
            'format' => 'markdown+fenced_divs+native_divs+header_attributes',
            'sectionDivs' => true,
        ]))->read(implode("\n", [
            '# Outer',
            '',
            '::: {.callout data-kind="note"}',
            '## Inner',
            '',
            'Inside.',
            ':::',
            '',
            'After.',
        ]));
        $outer = $document->children[0] ?? new AstNode('missing');
        $inner = $outer->children[1] ?? new AstNode('missing');
        $after = $outer->children[2] ?? new AstNode('missing');

        $t->same(['div'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('outer', $outer->attr('id'));
        $t->same(['heading', 'div', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $outer->children));
        $t->same('inner', $inner->attr('id'));
        $t->same(['section', 'level2', 'callout'], $inner->attr('classes'));
        $t->same(['data-kind' => 'note'], $inner->attr('attributes'));
        $t->same('After.', $after->attr('text'));
    };

return $tests;
