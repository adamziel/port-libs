<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'raw_html_inline') {
        return (string) $node->attr('html', '');
    }
    if ($node->type === 'softbreak') {
        return ' ';
    }
    if ($node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$hasInlineType = static function (AstNode $node, string $type) use (&$hasInlineType): bool {
    if ($node->type === $type) {
        return true;
    }

    foreach ($node->children as $child) {
        if ($hasInlineType($child, $type)) {
            return true;
        }
    }

    return false;
};

$tests = [];

$containerCases = [
    'emphasis trailing spaces hardbreak' => ['*foo  ' . "\n" . 'bar*', 'emph'],
    'emphasis backslash hardbreak' => ['*foo\\' . "\n" . 'bar*', 'emph'],
    'link trailing spaces hardbreak' => ['[foo  ' . "\n" . 'bar](/target)', 'link'],
    'link backslash hardbreak' => ['[foo\\' . "\n" . 'bar](/target)', 'link'],
];

foreach ($containerCases as $name => [$markdown, $type]) {
    $tests['maps commonmark hard line break inside inline container ' . $name] =
        static function (TestRunner $t) use ($inlineText, $inlineTypes, $markdown, $type): void {
            $paragraph = (new MarkdownReader(['format' => 'commonmark']))->read($markdown)->children[0] ?? new AstNode('missing');
            $container = $paragraph->children[0] ?? new AstNode('missing');

            $t->same('paragraph', $paragraph->type, $markdown);
            $t->same($type, $container->type, $markdown);
            $t->same(['text', 'linebreak', 'text'], $inlineTypes($container), $markdown);
            $t->same("foo\nbar", $inlineText($container), $markdown);
            if ($type === 'link') {
                $t->same('/target', $container->attr('url'), $markdown);
            }
        };
}

$codeSpanCases = [
    'code span trailing spaces stay literal' => ['`code  ' . "\n" . 'span`', 'code   span'],
    'code span backslash stays literal' => ['`code\\' . "\n" . 'span`', 'code\\ span'],
];

foreach ($codeSpanCases as $name => [$markdown, $expected]) {
    $tests['keeps commonmark hard line break marker literal inside code span ' . $name] =
        static function (TestRunner $t) use ($hasInlineType, $markdown, $expected): void {
            $paragraph = (new MarkdownReader(['format' => 'commonmark']))->read($markdown)->children[0] ?? new AstNode('missing');
            $code = $paragraph->children[0] ?? new AstNode('missing');

            $t->same('code', $code->type, $markdown);
            $t->same($expected, $code->attr('text'), $markdown);
            $t->same(false, $hasInlineType($code, 'linebreak'), $markdown);
        };
}

$htmlTagCases = [
    'html tag trailing spaces stay soft' => ['<a href="foo  ' . "\n" . 'bar">', ['format' => 'commonmark']],
    'html tag backslash stays literal' => ['<a href="foo\\' . "\n" . 'bar">', ['format' => 'commonmark']],
    'html tag hard_line_breaks profile still suppresses hardbreak' => ['<a href="foo' . "\n" . 'bar">', ['format' => 'commonmark+hard_line_breaks']],
];

foreach ($htmlTagCases as $name => [$markdown, $options]) {
    $tests['keeps commonmark hard line break marker literal inside html tag ' . $name] =
        static function (TestRunner $t) use ($hasInlineType, $inlineText, $markdown, $options): void {
            $paragraph = (new MarkdownReader($options))->read($markdown)->children[0] ?? new AstNode('missing');

            $t->same('paragraph', $paragraph->type, $markdown);
            $t->same(false, $hasInlineType($paragraph, 'linebreak'), $markdown);
            $t->contains('<a href="foo', $inlineText($paragraph), $markdown);
            $t->contains('bar">', $inlineText($paragraph), $markdown);
        };
}

$blockEndCases = [
    'paragraph backslash end stays literal' => ['foo\\', 'paragraph', 'foo\\'],
    'atx heading backslash end stays literal' => ['### foo\\', 'heading', 'foo\\'],
    'setext heading backslash end stays literal' => ["foo\\\n----", 'heading', 'foo\\'],
];

foreach ($blockEndCases as $name => [$markdown, $type, $expectedText]) {
    $tests['keeps commonmark hard line break marker literal at block end ' . $name] =
        static function (TestRunner $t) use ($hasInlineType, $markdown, $type, $expectedText): void {
            $node = (new MarkdownReader(['format' => 'commonmark']))->read($markdown)->children[0] ?? new AstNode('missing');

            $t->same($type, $node->type, $markdown);
            $t->same($expectedText, $node->attr('text'), $markdown);
            $t->same(false, $hasInlineType($node, 'linebreak'), $markdown);
        };
}

$tests['records commonmark hard line break boundary mapped-case count'] =
    static function (TestRunner $t) use ($containerCases, $codeSpanCases, $htmlTagCases, $blockEndCases): void {
        $t->same(12, count($containerCases) + count($codeSpanCases) + count($htmlTagCases) + count($blockEndCases));
    };

return $tests;
