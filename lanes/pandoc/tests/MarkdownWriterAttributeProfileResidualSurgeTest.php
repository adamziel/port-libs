<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$heading = static fn (string $value, array $attrs): AstNode => new AstNode(
    'heading',
    ['level' => 2] + $attrs,
    [$text($value)]
);
$div = static fn (string $value, array $attrs): AstNode => new AstNode(
    'div',
    $attrs,
    [$paragraph([$text($value)])]
);
$span = static fn (string $value, array $attrs): AstNode => new AstNode('span', $attrs, [$text($value)]);
$codeBlock = static fn (string $value, array $attrs): AstNode => new AstNode(
    'code_block',
    ['text' => $value] + $attrs
);
$code = static fn (string $value, array $attrs): AstNode => new AstNode('code', ['text' => $value] + $attrs);

$profiles = [
    'commonmark raw html disabled' => [
        'options' => ['format' => 'commonmark-raw_html'],
        'nativeAttributes' => false,
    ],
    'gfm raw html disabled' => [
        'options' => ['format' => 'gfm-raw_html'],
        'nativeAttributes' => false,
    ],
    'markdown raw html and native attributes disabled' => [
        'options' => [
            'format' => 'markdown-raw_html-header_attributes-bracketed_spans-fenced_divs-fenced_code_attributes-inline_code_attributes',
        ],
        'nativeAttributes' => false,
    ],
    'commonmark raw html disabled native attributes enabled' => [
        'options' => [
            'format' => 'commonmark-raw_html+header_attributes+bracketed_spans+fenced_divs+fenced_code_attributes+inline_code_attributes',
        ],
        'nativeAttributes' => true,
    ],
];

$variants = [
    'id class data alpha' => [
        'headingText' => 'Review alpha',
        'headingAttrs' => ['id' => 'heading-alpha', 'classes' => ['review', 'packet'], 'attributes' => ['data-kind' => 'alpha']],
        'headingTuple' => '{#heading-alpha .review .packet data-kind="alpha"}',
        'divText' => 'Body alpha',
        'divAttrs' => ['id' => 'div-alpha', 'classes' => ['review', 'packet'], 'attributes' => ['data-kind' => 'alpha']],
        'divTuple' => '{#div-alpha .review .packet data-kind="alpha"}',
        'spanText' => 'Marked alpha',
        'spanAttrs' => ['id' => 'span-alpha', 'classes' => ['review', 'packet'], 'attributes' => ['data-kind' => 'alpha']],
        'spanTuple' => '{#span-alpha .review .packet data-kind="alpha"}',
        'codeText' => 'echo "alpha";',
        'codeAttrs' => ['id' => 'code-alpha', 'classes' => ['php', 'review'], 'attributes' => ['data-kind' => 'alpha']],
        'codeTuple' => '{#code-alpha .php .review data-kind="alpha"}',
        'codeInfo' => 'php',
        'inlineCodeText' => 'source-alpha',
        'inlineCodeAttrs' => ['id' => 'inline-alpha', 'classes' => ['php', 'review'], 'attributes' => ['data-kind' => 'alpha']],
        'inlineCodeTuple' => '{#inline-alpha .php .review data-kind="alpha"}',
    ],
    'alias tuple beta' => [
        'headingText' => 'Review beta',
        'headingAttrs' => ['identifier' => 'heading-beta', 'class' => 'review beta', 'keyvals' => [['data-phase', 'beta']]],
        'headingTuple' => '{#heading-beta .review .beta data-phase="beta"}',
        'divText' => 'Body beta',
        'divAttrs' => ['identifier' => 'div-beta', 'class' => 'review beta', 'keyvals' => [['data-phase', 'beta']]],
        'divTuple' => '{#div-beta .review .beta data-phase="beta"}',
        'spanText' => 'Marked beta',
        'spanAttrs' => ['identifier' => 'span-beta', 'class' => 'review beta', 'keyvals' => [['data-phase', 'beta']]],
        'spanTuple' => '{#span-beta .review .beta data-phase="beta"}',
        'codeText' => 'echo "beta";',
        'codeAttrs' => ['identifier' => 'code-beta', 'class' => 'php beta', 'keyvals' => [['data-phase', 'beta']]],
        'codeTuple' => '{#code-beta .php .beta data-phase="beta"}',
        'codeInfo' => 'php',
        'inlineCodeText' => 'source-beta',
        'inlineCodeAttrs' => ['identifier' => 'inline-beta', 'class' => 'php beta', 'keyvals' => [['data-phase', 'beta']]],
        'inlineCodeTuple' => '{#inline-beta .php .beta data-phase="beta"}',
    ],
    'html source gamma' => [
        'headingText' => 'Review gamma',
        'headingAttrs' => ['htmlAttributes' => ['id' => 'heading-gamma', 'class' => 'legacy source', 'data-origin' => 'html'], 'classes' => ['review'], 'attributes' => ['data-kind' => 'gamma']],
        'headingTuple' => '{#heading-gamma .legacy .source .review data-origin="html" data-kind="gamma"}',
        'divText' => 'Body gamma',
        'divAttrs' => ['htmlAttributes' => ['id' => 'div-gamma', 'class' => 'legacy source', 'data-origin' => 'html'], 'classes' => ['review'], 'attributes' => ['data-kind' => 'gamma']],
        'divTuple' => '{#div-gamma .legacy .source .review data-origin="html" data-kind="gamma"}',
        'spanText' => 'Marked gamma',
        'spanAttrs' => ['htmlAttributes' => ['id' => 'span-gamma', 'class' => 'legacy source', 'data-origin' => 'html'], 'classes' => ['review'], 'attributes' => ['data-kind' => 'gamma']],
        'spanTuple' => '{#span-gamma .legacy .source .review data-origin="html" data-kind="gamma"}',
        'codeText' => 'echo "gamma";',
        'codeAttrs' => ['htmlAttributes' => ['id' => 'code-gamma', 'class' => 'php legacy', 'data-origin' => 'html'], 'classes' => ['review'], 'attributes' => ['data-kind' => 'gamma']],
        'codeTuple' => '{#code-gamma .php .legacy .review data-origin="html" data-kind="gamma"}',
        'codeInfo' => 'php',
        'inlineCodeText' => 'source-gamma',
        'inlineCodeAttrs' => ['htmlAttributes' => ['id' => 'inline-gamma', 'class' => 'php legacy', 'data-origin' => 'html'], 'classes' => ['review'], 'attributes' => ['data-kind' => 'gamma']],
        'inlineCodeTuple' => '{#inline-gamma .php .legacy .review data-origin="html" data-kind="gamma"}',
    ],
    'language title delta' => [
        'headingText' => 'Review delta',
        'headingAttrs' => ['id' => 'heading-delta', 'classes' => ['localized'], 'attributes' => ['data-kind' => 'delta'], 'dir' => 'rtl', 'lang' => 'ar', 'title' => 'Delta packet'],
        'headingTuple' => '{#heading-delta .localized data-kind="delta" dir="rtl" lang="ar" title="Delta packet"}',
        'divText' => 'Body delta',
        'divAttrs' => ['id' => 'div-delta', 'classes' => ['localized'], 'attributes' => ['data-kind' => 'delta'], 'dir' => 'rtl', 'lang' => 'ar', 'title' => 'Delta packet'],
        'divTuple' => '{#div-delta .localized data-kind="delta" dir="rtl" lang="ar" title="Delta packet"}',
        'spanText' => 'Marked delta',
        'spanAttrs' => ['id' => 'span-delta', 'classes' => ['localized'], 'attributes' => ['data-kind' => 'delta'], 'dir' => 'rtl', 'lang' => 'ar', 'title' => 'Delta packet'],
        'spanTuple' => '{#span-delta .localized data-kind="delta" dir="rtl" lang="ar" title="Delta packet"}',
        'codeText' => 'echo "delta";',
        'codeAttrs' => ['id' => 'code-delta', 'classes' => ['php'], 'attributes' => ['data-kind' => 'delta'], 'dir' => 'rtl', 'lang' => 'ar', 'title' => 'Delta packet'],
        'codeTuple' => '{#code-delta .php data-kind="delta" dir="rtl" lang="ar" title="Delta packet"}',
        'codeInfo' => 'php',
        'inlineCodeText' => 'source-delta',
        'inlineCodeAttrs' => ['id' => 'inline-delta', 'classes' => ['php'], 'attributes' => ['data-kind' => 'delta'], 'dir' => 'rtl', 'lang' => 'ar', 'title' => 'Delta packet'],
        'inlineCodeTuple' => '{#inline-delta .php data-kind="delta" dir="rtl" lang="ar" title="Delta packet"}',
    ],
];

$cases = [];
foreach ($profiles as $profileName => $profile) {
    foreach ($variants as $variantName => $variant) {
        $native = $profile['nativeAttributes'];
        $options = $profile['options'];

        $cases[$profileName . ' heading ' . $variantName] = [
            'document' => $document([$heading($variant['headingText'], $variant['headingAttrs'])]),
            'options' => $options,
            'expected' => '## ' . $variant['headingText'] . ($native ? ' ' . $variant['headingTuple'] : ''),
        ];
        $cases[$profileName . ' div ' . $variantName] = [
            'document' => $document([$div($variant['divText'], $variant['divAttrs'])]),
            'options' => $options,
            'expected' => $native
                ? '::: ' . $variant['divTuple'] . "\n" . $variant['divText'] . "\n:::"
                : $variant['divText'],
        ];
        $cases[$profileName . ' span ' . $variantName] = [
            'document' => $document([$paragraph([$span($variant['spanText'], $variant['spanAttrs'])])]),
            'options' => $options,
            'expected' => $native
                ? '[' . $variant['spanText'] . ']' . $variant['spanTuple']
                : $variant['spanText'],
        ];
        $cases[$profileName . ' code block ' . $variantName] = [
            'document' => $document([$codeBlock($variant['codeText'], $variant['codeAttrs'])]),
            'options' => $options,
            'expected' => $native
                ? '```' . $variant['codeTuple'] . "\n" . $variant['codeText'] . "\n```"
                : '```' . $variant['codeInfo'] . "\n" . $variant['codeText'] . "\n```",
        ];
        $cases[$profileName . ' inline code ' . $variantName] = [
            'document' => $document([$paragraph([$code($variant['inlineCodeText'], $variant['inlineCodeAttrs'])])]),
            'options' => $options,
            'expected' => $native
                ? '`' . $variant['inlineCodeText'] . '`' . $variant['inlineCodeTuple']
                : '`' . $variant['inlineCodeText'] . '`',
        ];
    }
}

$tests = [
    'records markdown writer attribute profile residual mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(80, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer attribute profile residual ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($case['expected'], $markdown);
            if (str_contains((string) ($case['options']['format'] ?? ''), '-raw_html')) {
                $t->true(!str_contains($markdown, '<h2'), 'raw-html-disabled heading fallback must not emit HTML');
                $t->true(!str_contains($markdown, '<div'), 'raw-html-disabled div fallback must not emit HTML');
                $t->true(!str_contains($markdown, '<span'), 'raw-html-disabled span fallback must not emit HTML');
                $t->true(!str_contains($markdown, '<pre'), 'raw-html-disabled code fallback must not emit HTML');
                $t->true(!str_contains($markdown, '<code'), 'raw-html-disabled inline code fallback must not emit HTML');
            }
        };
}

return $tests;
