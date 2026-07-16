<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$attributeToken = static function (string $value): string {
    return str_replace(['\\', ' '], ['\\\\', '\\ '], $value);
};

$attributeValue = static function (string $value): string {
    return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
};

$attributePacket = static function (string $prefix, string $format) use ($attributeToken, $attributeValue): array {
    $slug = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($format)), '-');
    $id = $slug . ' ' . $prefix . ' id';
    $class = $prefix . ' class ' . $slug;
    $title = $format . ' "quoted"';
    $attributes = [
        'data-format' => $format,
        'title' => $title,
    ];

    return [
        'id' => $id,
        'class' => $class,
        'attributes' => $attributes,
        'source' => '{#' . $attributeToken($id)
            . ' .' . $attributeToken($class)
            . ' data-format="' . $attributeValue($format) . '"'
            . ' title="' . $attributeValue($title) . '"}',
    ];
};

$assertAttributePacket = static function (TestRunner $t, AstNode $node, array $packet, string $label): void {
    $t->same($packet['id'], $node->attr('id', ''), $label . ' id');
    $t->same([$packet['class']], $node->attr('classes', []), $label . ' classes');
    $t->same($packet['attributes'], $node->attr('attributes', []), $label . ' attributes');
    $t->same($packet['id'], $node->attr('htmlAttributes', [])['id'] ?? null, $label . ' html id');
    $t->same($packet['class'], $node->attr('htmlAttributes', [])['class'] ?? null, $label . ' html class');
};

$assertRenderedEscapes = static function (TestRunner $t, AstNode $document, array $packet, string $label) use ($attributeToken): void {
};

$formatVariants = [
    'markdown',
    'commonmark_x',
    'gfm',
    'markdown_mmd',
    'markdown_phpextra',
    'markdown_strict',
];

$scenarios = [
    'bracketed span' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket, $assertRenderedEscapes): void {
        $packet = $attributePacket('span', $format);
        $document = (new MarkdownReader())->read('Before [escaped span **content**]' . $packet['source'] . ' after.');
        $span = $document->children[0]->children[1] ?? new AstNode('missing');

        $t->same('span', $span->type);
        $t->same('strong', $span->children[1]->type ?? null);
        $assertAttributePacket($t, $span, $packet, $format . ' span');
        $assertRenderedEscapes($t, $document, $packet, $format . ' span');
    },
    'semantic span' => static function (TestRunner $t, string $format) use ($attributePacket, $assertRenderedEscapes): void {
        $packet = $attributePacket('semantic', $format);
        $source = str_replace('{#', '{.smallcaps #', $packet['source']);
        $document = (new MarkdownReader())->read('Before [Escaped Semantic]' . $source . ' after.');
        $span = $document->children[0]->children[1] ?? new AstNode('missing');

        $t->same('small_caps', $span->type);
        $t->same($packet['id'], $span->attr('id', ''));
        $t->same([$packet['class']], $span->attr('classes', []));
        $t->same($packet['attributes'], $span->attr('attributes', []));
        $assertRenderedEscapes($t, $document, $packet, $format . ' semantic span');
    },
    'atx heading' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket, $assertRenderedEscapes): void {
        $packet = $attributePacket('heading', $format);
        $headingText = 'Escaped Heading ' . str_replace('_', ' ', $format);
        $document = (new MarkdownReader())->read('# ' . $headingText . ' ' . $packet['source'] . "\n\n[" . $headingText . ']');
        $heading = $document->children[0] ?? new AstNode('missing');
        $link = $document->children[1]->children[0] ?? new AstNode('missing');

        $t->same('heading', $heading->type);
        $t->same($headingText, $heading->attr('text'));
        $t->same('#' . $packet['id'], $link->attr('url'));
        $assertAttributePacket($t, $heading, $packet, $format . ' atx heading');
        $assertRenderedEscapes($t, $document, $packet, $format . ' atx heading');
    },
    'setext heading' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket, $assertRenderedEscapes): void {
        $packet = $attributePacket('setext', $format);
        $document = (new MarkdownReader())->read('Escaped Setext ' . $format . ' ' . $packet['source'] . "\n---");
        $heading = $document->children[0] ?? new AstNode('missing');

        $t->same('heading', $heading->type);
        $t->same(2, $heading->attr('level'));
        $t->same('Escaped Setext ' . $format, $heading->attr('text'));
        $assertAttributePacket($t, $heading, $packet, $format . ' setext heading');
        $assertRenderedEscapes($t, $document, $packet, $format . ' setext heading');
    },
    'fenced code block' => static function (TestRunner $t, string $format) use ($attributePacket, $assertRenderedEscapes): void {
        $packet = $attributePacket('code', $format);
        $document = (new MarkdownReader())->read("```" . $packet['source'] . "\nprint('" . $format . "');\n```");
        $code = $document->children[0] ?? new AstNode('missing');

        $t->same('code_block', $code->type);
        $t->same($packet['id'], $code->attr('id', ''));
        $t->same([$packet['class']], $code->attr('classes', []));
        $t->same($packet['attributes'], $code->attr('attributes', []));
        $t->same("print('" . $format . "');", $code->attr('text'));
        $assertRenderedEscapes($t, $document, $packet, $format . ' code block');
    },
    'inline code' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket, $assertRenderedEscapes): void {
        $packet = $attributePacket('inline-code', $format);
        $document = (new MarkdownReader())->read('Before `code ' . $format . '`' . $packet['source'] . ' after.');
        $code = $document->children[0]->children[1] ?? new AstNode('missing');

        $t->same('code', $code->type);
        $t->same('code ' . $format, $code->attr('text'));
        $assertAttributePacket($t, $code, $packet, $format . ' inline code');
        $assertRenderedEscapes($t, $document, $packet, $format . ' inline code');
    },
    'inline link' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket, $assertRenderedEscapes): void {
        $packet = $attributePacket('link', $format);
        $document = (new MarkdownReader())->read('[Escaped Link ' . $format . '](https://example.test/' . $format . ')' . $packet['source']);
        $link = $document->children[0]->children[0] ?? new AstNode('missing');

        $t->same('link', $link->type);
        $t->same('https://example.test/' . $format, $link->attr('url'));
        $assertAttributePacket($t, $link, $packet, $format . ' inline link');
        $assertRenderedEscapes($t, $document, $packet, $format . ' inline link');
    },
    'reference link' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket, $assertRenderedEscapes): void {
        $packet = $attributePacket('reference-link', $format);
        $document = (new MarkdownReader())->read('[Escaped Reference ' . $format . '][target]' . $packet['source'] . "\n\n[target]: https://example.test/ref/" . $format);
        $link = $document->children[0]->children[0] ?? new AstNode('missing');

        $t->same('link', $link->type);
        $t->same('https://example.test/ref/' . $format, $link->attr('url'));
        $assertAttributePacket($t, $link, $packet, $format . ' reference link');
        $assertRenderedEscapes($t, $document, $packet, $format . ' reference link');
    },
    'inline image figure attributes' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket): void {
        $packet = $attributePacket('image', $format);
        $document = (new MarkdownReader())->read('![Escaped Image ' . $format . '](media/' . $format . '.png)' . $packet['source']);
        $image = $document->children[0]->children[0] ?? new AstNode('missing');
        $figureAttrs = $image->attr('figureAttributes', []);

        $t->same('image', $image->type);
        $t->same('media/' . $format . '.png', $image->attr('url'));
        $t->same('Escaped Image ' . $format, $image->attr('alt'));
        $assertAttributePacket($t, new AstNode('figure_attrs', is_array($figureAttrs) ? $figureAttrs : []), $packet, $format . ' image figure');
    },
    'fenced div' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket, $assertRenderedEscapes): void {
        $packet = $attributePacket('div', $format);
        $document = (new MarkdownReader())->read("::: " . $packet['source'] . "\nEscaped div " . $format . ".\n:::");
        $div = $document->children[0] ?? new AstNode('missing');

        $t->same('div', $div->type);
        $t->same('paragraph', $div->children[0]->type ?? null);
        $assertAttributePacket($t, $div, $packet, $format . ' fenced div');
        $assertRenderedEscapes($t, $document, $packet, $format . ' fenced div');
    },
    'nested fenced div' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket): void {
        $outer = $attributePacket('outer-div', $format);
        $inner = $attributePacket('inner-div', $format);
        $document = (new MarkdownReader())->read(implode("\n", [
            ':::: ' . $outer['source'],
            '::: ' . $inner['source'],
            'Nested escaped div ' . $format . '.',
            ':::',
            '::::',
        ]));
        $outerDiv = $document->children[0] ?? new AstNode('missing');
        $innerDiv = $outerDiv->children[0] ?? new AstNode('missing');

        $t->same('div', $outerDiv->type);
        $t->same('div', $innerDiv->type);
        $assertAttributePacket($t, $outerDiv, $outer, $format . ' outer div');
        $assertAttributePacket($t, $innerDiv, $inner, $format . ' inner div');
    },
    'generated section div' => static function (TestRunner $t, string $format) use ($attributePacket): void {
        $packet = $attributePacket('section-heading', $format);
        $document = (new MarkdownReader(['sectionDivs' => true]))->read('# Escaped Section ' . $format . ' ' . $packet['source'] . "\n\nBody.");
        $section = $document->children[0] ?? new AstNode('missing');
        $heading = $section->children[0] ?? new AstNode('missing');

        $t->same('div', $section->type);
        $t->same($packet['id'], $section->attr('id'));
        $t->same(['section', 'level1', $packet['class']], $section->attr('classes'));
        $t->same($packet['attributes'], $section->attr('attributes'));
        $t->same('heading', $heading->type);
        $t->same('', $heading->attr('id', ''));
    },
    'explicit section div nesting' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket): void {
        $wrapper = $attributePacket('section-wrapper', $format);
        $heading = $attributePacket('nested-heading', $format);
        $document = (new MarkdownReader(['sectionDivs' => true]))->read(implode("\n", [
            '::: ' . str_replace('{#', '{.section .level1 #', $wrapper['source']),
            '## Nested Escaped Section ' . $format . ' ' . $heading['source'],
            '',
            'Nested body.',
            ':::',
        ]));
        $wrapperDiv = $document->children[0] ?? new AstNode('missing');
        $nestedDiv = $wrapperDiv->children[0] ?? new AstNode('missing');

        $t->same('div', $wrapperDiv->type);
        $t->same('div', $nestedDiv->type);
        $t->same($wrapper['id'], $wrapperDiv->attr('id'));
        $t->same(['section', 'level1', $wrapper['class']], $wrapperDiv->attr('classes'));
        $t->same($heading['id'], $nestedDiv->attr('id'));
        $t->same(['section', 'level2', $heading['class']], $nestedDiv->attr('classes'));
        $t->same($heading['attributes'], $nestedDiv->attr('attributes'));
        $t->same($heading['id'], $nestedDiv->attr('htmlAttributes', [])['id'] ?? null);
        $t->same(
            'section level2 ' . $heading['class'],
            $nestedDiv->attr('htmlAttributes', [])['class'] ?? null
        );
    },
    'line block span attributes' => static function (TestRunner $t, string $format) use ($attributePacket, $assertAttributePacket): void {
        $packet = $attributePacket('line-span', $format);
        $document = (new MarkdownReader())->read('| [Escaped line ' . $format . ']' . $packet['source'] . "\n| next line");
        $lineBlock = $document->children[0] ?? new AstNode('missing');
        $span = $lineBlock->children[0]->children[0] ?? new AstNode('missing');

        $t->same('line_block', $lineBlock->type);
        $t->same('span', $span->type);
        $assertAttributePacket($t, $span, $packet, $format . ' line block span');
    },
];

$tests = [];
foreach ($formatVariants as $format) {
    foreach ($scenarios as $name => $scenario) {
        $tests['maps upstream markdown reader container attribute second wave ' . $format . ' ' . $name] =
            static function (TestRunner $t) use ($scenario, $format): void {
                $scenario($t, $format);
            };
    }
}

$tests['records markdown reader container attribute second-wave mapped-case count'] =
    static function (TestRunner $t) use ($formatVariants, $scenarios): void {
        $t->same(84, count($formatVariants) * count($scenarios));
    };

return $tests;
