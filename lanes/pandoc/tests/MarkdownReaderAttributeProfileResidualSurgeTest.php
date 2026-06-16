<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return trim($text);
};

$read = static function (?string $format, string $markdown): AstNode {
    $options = $format === null ? [] : ['format' => $format];

    return (new MarkdownReader($options))->read($markdown);
};

$profiles = [
    'default markdown' => ['format' => null, 'enabled' => true],
    'markdown' => ['format' => 'markdown', 'enabled' => true],
    'commonmark x' => ['format' => 'commonmark_x', 'enabled' => true],
    'commonmark literal' => ['format' => 'commonmark', 'enabled' => false],
    'gfm literal' => ['format' => 'gfm', 'enabled' => false],
    'commonmark extensions' => [
        'format' => 'commonmark+header_attributes+fenced_divs+bracketed_spans+raw_attribute',
        'enabled' => true,
    ],
    'gfm extensions' => [
        'format' => 'gfm+header_attributes+fenced_divs+bracketed_spans+raw_attribute',
        'enabled' => true,
    ],
];

$attributeVariants = [
    'simple tuple' => [
        'source' => '{#packet .review data-kind=alpha}',
        'literal' => '{#packet .review data-kind=alpha}',
        'id' => 'packet',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'alpha'],
        'raw' => '{=html}',
    ],
    'escaped tuple' => [
        'source' => '{#packet\ space .review\ class data-kind="A &amp; B"}',
        'literal' => '{#packet\ space .review\ class data-kind="A & B"}',
        'id' => 'packet space',
        'classes' => ['review class'],
        'attributes' => ['data-kind' => 'A & B'],
        'raw' => '{#raw-packet .review =html data-kind="A &amp; B"}',
    ],
    'reordered tuple' => [
        'source' => '{.review #packet-two title=\'Review packet\' data-phase=beta}',
        'literal' => '{.review #packet-two title=\'Review packet\' data-phase=beta}',
        'id' => 'packet-two',
        'classes' => ['review'],
        'attributes' => ['title' => 'Review packet', 'data-phase' => 'beta'],
        'raw' => '{.raw data-phase=beta =html}',
    ],
];

$assertAttributeNode = static function (TestRunner $t, AstNode $node, array $variant, string $label): void {
    $t->same($variant['id'], $node->attr('id', ''), $label . ' id');
    $t->same($variant['classes'], $node->attr('classes', []), $label . ' classes');
    $t->same($variant['attributes'], $node->attr('attributes', []), $label . ' attributes');
};

$assertLiteralAttributeText = static function (TestRunner $t, AstNode $node, string $source, string $label) use ($inlineText): void {
    $t->same([], $node->attr('classes', []), $label . ' classes stay empty');
    $t->same([], $node->attr('attributes', []), $label . ' attributes stay empty');
    $t->contains($source, $inlineText($node), $label . ' source remains literal');
};

$sites = [
    'atx heading attributes' => static function (
        TestRunner $t,
        ?string $format,
        bool $enabled,
        array $variant
    ) use ($assertAttributeNode, $assertLiteralAttributeText, $read): void {
        $document = $read($format, '# Profile Heading ' . $variant['source']);
        $heading = $document->children[0] ?? new AstNode('missing');

        $t->same('heading', $heading->type);
        if ($enabled) {
            $t->same('Profile Heading', $heading->attr('text'));
            $assertAttributeNode($t, $heading, $variant, 'atx heading');
            return;
        }

        $t->same('Profile Heading ' . $variant['source'], $heading->attr('text'));
        $assertLiteralAttributeText($t, $heading, $variant['literal'], 'atx heading');
    },
    'setext heading attributes' => static function (
        TestRunner $t,
        ?string $format,
        bool $enabled,
        array $variant
    ) use ($assertAttributeNode, $assertLiteralAttributeText, $read): void {
        $document = $read($format, 'Profile Setext ' . $variant['source'] . "\n---");
        $heading = $document->children[0] ?? new AstNode('missing');

        $t->same('heading', $heading->type);
        if ($enabled) {
            $t->same('Profile Setext', $heading->attr('text'));
            $assertAttributeNode($t, $heading, $variant, 'setext heading');
            return;
        }

        $t->same('Profile Setext ' . $variant['source'], $heading->attr('text'));
        $assertLiteralAttributeText($t, $heading, $variant['literal'], 'setext heading');
    },
    'bracketed span attributes' => static function (
        TestRunner $t,
        ?string $format,
        bool $enabled,
        array $variant
    ) use ($assertAttributeNode, $inlineText, $read): void {
        $document = $read($format, 'Before [Profile Span]' . $variant['source'] . ' after.');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $span = $paragraph->children[1] ?? new AstNode('missing');

        if ($enabled) {
            $t->same('span', $span->type);
            $t->same('Profile Span', $inlineText($span));
            $assertAttributeNode($t, $span, $variant, 'span');
            return;
        }

        $t->same('paragraph', $paragraph->type);
        $t->same('Before [Profile Span]' . $variant['literal'] . ' after.', $inlineText($paragraph));
    },
    'fenced div attributes' => static function (
        TestRunner $t,
        ?string $format,
        bool $enabled,
        array $variant
    ) use ($assertAttributeNode, $inlineText, $read): void {
        $document = $read($format, "::: " . $variant['source'] . "\nProfile div body.\n:::");
        $block = $document->children[0] ?? new AstNode('missing');

        if ($enabled) {
            $t->same('div', $block->type);
            $assertAttributeNode($t, $block, $variant, 'fenced div');
            $t->same('Profile div body.', $inlineText($block));
            return;
        }

        $t->true($block->type !== 'div', 'disabled fenced div should not produce a div');
        $t->contains('::: ' . $variant['literal'], $inlineText($block), 'disabled fenced div opening stays literal');
    },
    'fenced div class shortcut' => static function (
        TestRunner $t,
        ?string $format,
        bool $enabled,
        array $variant
    ) use ($inlineText, $read): void {
        $class = str_replace(' ', '-', $variant['classes'][0]);
        $document = $read($format, ":::" . ' ' . $class . "\nProfile shortcut body.\n:::");
        $block = $document->children[0] ?? new AstNode('missing');

        if ($enabled) {
            $t->same('div', $block->type);
            $t->same([$class], $block->attr('classes', []));
            $t->same('Profile shortcut body.', $inlineText($block));
            return;
        }

        $t->true($block->type !== 'div', 'disabled fenced div shortcut should not produce a div');
        $t->contains('::: ' . $class, $inlineText($block), 'disabled fenced div shortcut stays literal');
    },
    'raw fenced block attribute' => static function (
        TestRunner $t,
        ?string $format,
        bool $enabled,
        array $variant
    ) use ($read): void {
        $document = $read($format, "```" . $variant['raw'] . "\n<strong>raw profile</strong>\n```");
        $block = $document->children[0] ?? new AstNode('missing');

        if ($enabled) {
            $t->same('raw_block', $block->type);
            $t->same('html', $block->attr('format'));
            $t->same('<strong>raw profile</strong>', $block->attr('text'));
            return;
        }

        $t->same('code_block', $block->type);
        $t->same($variant['raw'], $block->attr('info'));
        $t->same('<strong>raw profile</strong>', $block->attr('text'));
    },
];

$tests = [];
foreach ($profiles as $profileName => $profile) {
    foreach ($attributeVariants as $variantName => $variant) {
        foreach ($sites as $siteName => $site) {
            $tests['maps upstream markdown reader attribute profile residual '
                . $profileName . ' ' . $variantName . ' ' . $siteName] =
                static function (TestRunner $t) use ($site, $profile, $variant): void {
                    $site($t, $profile['format'], $profile['enabled'], $variant);
                };
        }
    }
}

$tests['records markdown reader attribute profile residual mapped-case count'] =
    static function (TestRunner $t) use ($profiles, $attributeVariants, $sites): void {
        $t->same(126, count($profiles) * count($attributeVariants) * count($sites));
    };

return $tests;
