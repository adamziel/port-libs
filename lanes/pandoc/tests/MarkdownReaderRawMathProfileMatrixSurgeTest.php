<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$findFirstMatching = null;
$findFirstMatching = static function (AstNode $node, callable $predicate) use (&$findFirstMatching): AstNode {
    if ($predicate($node)) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstMatching($child, $predicate);
        if ($match->type !== 'missing') {
            return $match;
        }
    }

    return new AstNode('missing');
};

$inlineText = null;
$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'raw_tex') {
        return (string) $node->attr('tex', '');
    }
    if ($node->type === 'raw_inline') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'raw_html_inline') {
        return (string) $node->attr('text', $node->attr('html', ''));
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$read = static function (string $format, array $options, string $markdown): AstNode {
    return (new MarkdownReader(array_replace(['format' => $format], $options)))->read($markdown);
};

$rawProfileCases = [
    'raw html inline' => [
        'markdown' => 'Lead <span data-raw="inline">raw</span> trail',
        'enabledExtensions' => ['raw_html' => true],
        'disabledExtensions' => ['raw_html' => false],
        'enabledOptions' => ['rawHtml' => true],
        'disabledOptions' => ['rawHtml' => false],
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_html_inline'
            && $node->attr('html') === '<span data-raw="inline">',
    ],
    'raw html block' => [
        'markdown' => '<section data-raw="block">' . "\n" . 'raw' . "\n" . '</section>',
        'enabledExtensions' => ['raw_html' => true],
        'disabledExtensions' => ['raw_html' => false],
        'enabledOptions' => ['rawHtml' => true],
        'disabledOptions' => ['rawHtml' => false],
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_html'
            && str_contains((string) $node->attr('html', ''), '<section data-raw="block">'),
    ],
    'raw tex inline' => [
        'markdown' => 'Lead \textbf{raw} trail',
        'enabledExtensions' => ['raw_tex' => true],
        'disabledExtensions' => ['raw_tex' => false],
        'enabledOptions' => ['rawTex' => true],
        'disabledOptions' => ['rawTex' => false],
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_tex'
            && $node->attr('command') === 'textbf'
            && $node->attr('tex') === '\textbf{raw}',
    ],
    'raw tex block' => [
        'markdown' => '\begin{center}' . "\n" . 'raw' . "\n" . '\end{center}',
        'enabledExtensions' => ['raw_tex' => true],
        'disabledExtensions' => ['raw_tex' => false],
        'enabledOptions' => ['rawTex' => true],
        'disabledOptions' => ['rawTex' => false],
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_tex'
            && $node->attr('environment') === 'center',
    ],
    'raw attribute inline html' => [
        'markdown' => 'Lead `<b>raw</b>`{=html} trail',
        'enabledExtensions' => ['raw_attribute' => true, 'raw_html' => true],
        'disabledExtensions' => ['raw_attribute' => false, 'raw_html' => true],
        'enabledOptions' => ['rawAttribute' => true, 'rawHtml' => true],
        'disabledOptions' => ['rawAttribute' => false, 'rawHtml' => true],
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_html_inline'
            && $node->attr('format') === 'html'
            && $node->attr('text') === '<b>raw</b>'
            && $node->attr('html') === '<b>raw</b>',
    ],
    'raw attribute fenced html' => [
        'markdown' => '```{=html}' . "\n" . '<section>raw</section>' . "\n" . '```',
        'enabledExtensions' => ['raw_attribute' => true, 'raw_html' => true],
        'disabledExtensions' => ['raw_attribute' => true, 'raw_html' => false],
        'enabledOptions' => ['rawAttribute' => true, 'rawHtml' => true],
        'disabledOptions' => ['rawAttribute' => true, 'rawHtml' => false],
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_block'
            && $node->attr('format') === 'html'
            && $node->attr('text') === '<section>raw</section>',
    ],
    'raw attribute fenced markdown' => [
        'markdown' => '```{=markdown}' . "\n" . '**raw**' . "\n" . '```',
        'enabledExtensions' => ['raw_attribute' => true, 'raw_markdown' => true],
        'disabledExtensions' => ['raw_attribute' => true, 'raw_markdown' => false],
        'enabledOptions' => ['rawAttribute' => true, 'rawMarkdown' => true],
        'disabledOptions' => ['rawAttribute' => true, 'rawMarkdown' => false],
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_block'
            && $node->attr('format') === 'markdown'
            && $node->attr('text') === '**raw**',
    ],
    'raw attribute fenced tex' => [
        'markdown' => '```{=latex}' . "\n" . '\textbf{raw}' . "\n" . '```',
        'enabledExtensions' => ['raw_attribute' => true, 'raw_tex' => true],
        'disabledExtensions' => ['raw_attribute' => true, 'raw_tex' => false],
        'enabledOptions' => ['rawAttribute' => true, 'rawTex' => true],
        'disabledOptions' => ['rawAttribute' => true, 'rawTex' => false],
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_block'
            && $node->attr('format') === 'latex'
            && $node->attr('text') === '\textbf{raw}',
    ],
];

$profileFormats = ['markdown', 'commonmark', 'gfm'];

$assertRawEnabled = static function (
    TestRunner $t,
    AstNode $document,
    callable $match,
    string $label
) use ($findFirstMatching): void {
    $node = $findFirstMatching($document, $match);
    $t->true($node->type !== 'missing', $label);
};

$assertRawDisabled = static function (
    TestRunner $t,
    AstNode $document,
    callable $match,
    string $label
) use ($findFirstMatching): void {
    $node = $findFirstMatching($document, $match);
    $t->same('missing', $node->type, $label);
};

$mathCases = [
    'dollar inline attributes' => [
        'markdown' => 'Lead $x_{1}+y${#math-inline .review data-kind="inline"} trail',
        'options' => [],
        'text' => 'x_{1}+y',
        'display' => false,
        'id' => 'math-inline',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'inline'],
    ],
    'dollar display attributes' => [
        'markdown' => 'Lead $$x + y$${#math-display .review data-kind="display"} trail',
        'options' => [],
        'text' => 'x + y',
        'display' => true,
        'id' => 'math-display',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'display'],
    ],
    'dollar display multiline' => [
        'markdown' => '$$' . "\n" . 'x + y' . "\n" . '$${#math-block .review data-kind="block"}',
        'options' => [],
        'text' => 'x + y',
        'display' => true,
        'id' => 'math-block',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'block'],
    ],
    'single backslash inline' => [
        'markdown' => 'Lead \(x+y\){#math-single .review data-kind="single"} trail',
        'options' => [],
        'text' => 'x+y',
        'display' => false,
        'id' => 'math-single',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'single'],
    ],
    'single backslash display' => [
        'markdown' => 'Lead \[x+y\]{#math-single-display .review data-kind="single-display"} trail',
        'options' => [],
        'text' => 'x+y',
        'display' => true,
        'id' => 'math-single-display',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'single-display'],
    ],
    'double backslash inline' => [
        'markdown' => 'Lead \\\\(x+y\\\\){#math-double .review data-kind="double"} trail',
        'options' => ['texMathDoubleBackslash' => true],
        'text' => 'x+y',
        'display' => false,
        'id' => 'math-double',
        'classes' => ['review'],
        'attributes' => ['data-kind' => 'double'],
    ],
];

$mathExtensions = [
    'tex_math_dollars' => true,
    'tex_math_single_backslash' => true,
    'tex_math_double_backslash' => true,
    'inline_attributes' => true,
];

return [
    'maps upstream markdown raw extension option matrix across reader profiles' =>
        static function (TestRunner $t) use (
            $assertRawDisabled,
            $assertRawEnabled,
            $profileFormats,
            $rawProfileCases,
            $read
        ): void {
            $mapped = 0;

            foreach ($profileFormats as $format) {
                foreach ($rawProfileCases as $name => $case) {
                    $enabled = $read($format, ['extensions' => $case['enabledExtensions']], $case['markdown']);
                    $assertRawEnabled($t, $enabled, $case['match'], "{$format} extensions enable {$name}");
                    $mapped++;

                    $disabled = $read($format, ['extensions' => $case['disabledExtensions']], $case['markdown']);
                    $assertRawDisabled($t, $disabled, $case['match'], "{$format} extensions disable {$name}");
                    $mapped++;
                }
            }

            $t->same(48, $mapped);
        },
    'maps upstream markdown raw explicit option matrix across reader profiles' =>
        static function (TestRunner $t) use (
            $assertRawDisabled,
            $assertRawEnabled,
            $profileFormats,
            $rawProfileCases,
            $read
        ): void {
            $mapped = 0;

            foreach ($profileFormats as $format) {
                foreach ($rawProfileCases as $name => $case) {
                    $enabled = $read($format, $case['enabledOptions'], $case['markdown']);
                    $assertRawEnabled($t, $enabled, $case['match'], "{$format} explicit options enable {$name}");
                    $mapped++;

                    $disabled = $read($format, $case['disabledOptions'], $case['markdown']);
                    $assertRawDisabled($t, $disabled, $case['match'], "{$format} explicit options disable {$name}");
                    $mapped++;
                }
            }

            $t->same(48, $mapped);
        },
    'maps upstream markdown math delimiter and attribute profile matrix' =>
        static function (TestRunner $t) use (
            $findFirstMatching,
            $inlineText,
            $mathCases,
            $mathExtensions,
            $profileFormats,
            $read
        ): void {
            $mapped = 0;

            foreach ($profileFormats as $format) {
                foreach ($mathCases as $name => $case) {
                    $document = $read($format, array_replace($case['options'], ['extensions' => $mathExtensions]), $case['markdown']);
                    $math = $findFirstMatching($document, static fn (AstNode $node): bool => $node->type === 'math');
                    $paragraph = $document->children[0] ?? new AstNode('missing');
                    $label = "{$format} math {$name}";

                    $t->same('math', $math->type, $label . ' node');
                    $t->same($case['text'], $math->attr('text'), $label . ' text');
                    $t->same($case['display'], $math->attr('display'), $label . ' display');
                    $t->same($case['id'], $math->attr('id'), $label . ' id');
                    $t->same($case['classes'], $math->attr('classes'), $label . ' classes');
                    $t->same($case['attributes'], $math->attr('attributes'), $label . ' attributes');
                    $t->contains($case['text'], $inlineText($paragraph), $label . ' paragraph text');
                    $mapped++;
                }
            }

            $t->same(18, $mapped);
        },
    'records upstream markdown raw math profile matrix mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(114, 48 + 48 + 18);
        },
];
