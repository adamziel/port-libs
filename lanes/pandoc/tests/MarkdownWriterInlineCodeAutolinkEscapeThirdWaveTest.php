<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$link = static fn (string $url, string $label = 'packet'): AstNode => new AstNode('link', ['url' => $url], [$text($label)]);
$image = static fn (string $url, string $alt = 'packet', array $attrs = [], array $children = []): AstNode => new AstNode(
    'image',
    ['url' => $url, 'alt' => $alt] + $attrs,
    $children
);
$writeParagraph = static fn (array $children, array $options = []): string => (new MarkdownWriter($options))->write(
    $document([$paragraph($children)])
);
$firstInline = static function (string $markdown): ?AstNode {
    $roundTrip = (new MarkdownReader())->read($markdown);

    return $roundTrip->children[0]->children[0] ?? null;
};
$escapedDestination = static fn (string $url): string => '<' . str_replace(
    ["\\", '<', '>', '"', "'"],
    ["\\\\", '\\<', '\\>', '\\"', "\\'"],
    $url
) . '>';

$tests = [];
$mappedCaseCount = 0;

for ($width = 1; $width <= 12; $width++) {
    $source = str_repeat(' ', $width);
    $mappedCaseCount++;

    $tests["maps upstream markdown writer code span all-space width {$width}"] =
        static function (TestRunner $t) use ($code, $firstInline, $source, $writeParagraph): void {
            $markdown = $writeParagraph([$code($source)]);

            $t->same('`' . $source . '`', $markdown);

            $node = $firstInline($markdown);
            $t->true($node instanceof AstNode && $node->type === 'code', 'Expected code span after round-trip');
            if ($node instanceof AstNode) {
                $t->same($source, $node->attr('text'));
            }
        };
}

$backslashDestinationChars = [
    'exclamation' => '!',
    'hash' => '#',
    'dollar' => '$',
    'percent' => '%',
    'ampersand' => '&',
    'asterisk' => '*',
    'plus' => '+',
    'comma' => ',',
    'dash' => '-',
    'dot' => '.',
    'slash' => '/',
    'colon' => ':',
    'semicolon' => ';',
    'equals' => '=',
    'question' => '?',
    'at' => '@',
    'left bracket' => '[',
    'right bracket' => ']',
    'underscore' => '_',
    'backtick' => '`',
    'left brace' => '{',
    'pipe' => '|',
    'right brace' => '}',
    'tilde' => '~',
];

foreach ($backslashDestinationChars as $label => $char) {
    $url = '/review\\' . $char . 'packet';
    $destination = $escapedDestination($url);
    $caseLabel = str_replace(' ', '-', $label);

    $mappedCaseCount++;
    $tests["maps upstream markdown writer backslash link destination {$caseLabel}"] =
        static function (TestRunner $t) use ($destination, $firstInline, $link, $url, $writeParagraph): void {
            $markdown = $writeParagraph([$link($url)]);

            $t->same('[packet](' . $destination . ')', $markdown);

            $node = $firstInline($markdown);
            $t->true($node instanceof AstNode && $node->type === 'link', 'Expected link after round-trip');
            if ($node instanceof AstNode) {
                $t->same($url, $node->attr('url'));
            }
        };

    $mappedCaseCount++;
    $tests["maps upstream markdown writer backslash image destination {$caseLabel}"] =
        static function (TestRunner $t) use ($destination, $firstInline, $image, $url, $writeParagraph): void {
            $markdown = $writeParagraph([$image($url)]);

            $t->same('![packet](' . $destination . ')', $markdown);

            $node = $firstInline($markdown);
            $t->true($node instanceof AstNode && $node->type === 'image', 'Expected image after round-trip');
            if ($node instanceof AstNode) {
                $t->same($url, $node->attr('url'));
                $t->same('packet', $node->attr('alt'));
            }
        };

    $mappedCaseCount++;
    $tests["maps upstream markdown writer backslash reference destination {$caseLabel}"] =
        static function (TestRunner $t) use ($destination, $firstInline, $link, $url, $writeParagraph): void {
            $markdown = $writeParagraph([$link($url)], ['referenceLinks' => true]);

            $t->same("[packet]\n\n  [packet]: " . $destination, $markdown);

            $node = $firstInline($markdown);
            $t->true($node instanceof AstNode && $node->type === 'link', 'Expected reference link after round-trip');
            if ($node instanceof AstNode) {
                $t->same($url, $node->attr('url'));
            }
        };
}

$mailtoImageCases = [
    'simple mailto alt' => [
        'node' => $image('mailto:reviewer@example.test', 'reviewer@example.test'),
        'expected' => '![reviewer@example.test](mailto:reviewer@example.test)',
        'url' => 'mailto:reviewer@example.test',
        'alt' => 'reviewer@example.test',
    ],
    'plus tag mailto alt' => [
        'node' => $image('mailto:reviewer+tag@example.test', 'reviewer+tag@example.test'),
        'expected' => '![reviewer+tag@example.test](mailto:reviewer+tag@example.test)',
        'url' => 'mailto:reviewer+tag@example.test',
        'alt' => 'reviewer+tag@example.test',
    ],
    'mailto child label' => [
        'node' => $image('mailto:editor@example.test', '', [], [$text('editor@example.test')]),
        'expected' => '![editor@example.test](mailto:editor@example.test)',
        'url' => 'mailto:editor@example.test',
        'alt' => 'editor@example.test',
    ],
    'mailto email class' => [
        'node' => $image('mailto:reviewer@example.test', 'reviewer@example.test', ['classes' => ['email']]),
        'expected' => '![reviewer@example.test](mailto:reviewer@example.test){.email}',
        'url' => 'mailto:reviewer@example.test',
        'alt' => 'reviewer@example.test',
    ],
    'mailto reference image' => [
        'node' => $image('mailto:reviewer@example.test', 'reviewer@example.test'),
        'expected' => "![reviewer@example.test]\n\n  [reviewer@example.test]: mailto:reviewer@example.test",
        'url' => 'mailto:reviewer@example.test',
        'alt' => 'reviewer@example.test',
        'options' => ['referenceLinks' => true],
    ],
];

foreach ($mailtoImageCases as $label => $case) {
    $mappedCaseCount++;

    $tests["maps upstream markdown writer mailto image avoids autolink {$label}"] =
        static function (TestRunner $t) use ($case, $firstInline, $writeParagraph): void {
            $markdown = $writeParagraph([$case['node']], $case['options'] ?? []);

            $t->same($case['expected'], $markdown);

            $node = $firstInline($markdown);
            $t->true($node instanceof AstNode && $node->type === 'image', 'Expected image after round-trip');
            if ($node instanceof AstNode) {
                $t->same($case['url'], $node->attr('url'));
                $t->same($case['alt'], $node->attr('alt'));
            }
        };
}

$tests['records markdown writer inline code autolink escape third wave mapped case count'] =
    static function (TestRunner $t) use ($mappedCaseCount): void {
        $t->same(89, $mappedCaseCount);
    };

return $tests;
