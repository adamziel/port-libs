<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

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

$findInline = static function (AstNode $node, callable $predicate) use (&$findInline): AstNode {
    if ($predicate($node)) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findInline($child, $predicate);
        if ($match->type !== 'missing') {
            return $match;
        }
    }

    return new AstNode('missing');
};

$read = static function (?string $format, string $markdown): AstNode {
    $options = $format === null ? [] : ['format' => $format];

    return (new MarkdownReader($options))->read($markdown);
};

$featureProbes = [
    'strikeout' => [
        'markdown' => 'Before ~~gone~~ after.',
        'literal' => 'Before ~~gone~~ after.',
        'match' => static fn (AstNode $node): bool => $node->type === 'strikeout',
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same('gone', $inlineText($node));
        },
    ],
    'mark' => [
        'markdown' => 'Before ==flag== after.',
        'literal' => 'Before ==flag== after.',
        'match' => static fn (AstNode $node): bool => $node->type === 'span' && $node->attr('classes') === ['mark'],
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same(['mark'], $node->attr('classes'));
            $t->same('flag', $inlineText($node));
        },
    ],
    'superscript' => [
        'markdown' => 'H^2^ packet',
        'literal' => 'H^2^ packet',
        'match' => static fn (AstNode $node): bool => $node->type === 'superscript',
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same('2', $inlineText($node));
        },
    ],
    'subscript' => [
        'markdown' => 'H~2~ packet',
        'literal' => 'H~2~ packet',
        'match' => static fn (AstNode $node): bool => $node->type === 'subscript',
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same('2', $inlineText($node));
        },
    ],
    'emoji' => [
        'markdown' => 'Ready, :rocket: now.',
        'literal' => 'Ready, :rocket: now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'span' && ($node->attr('attributes')['data-emoji'] ?? null) === 'rocket',
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same(['emoji'], $node->attr('classes'));
            $t->same("\u{1F680}", $inlineText($node));
        },
    ],
    'citation' => [
        'markdown' => '@doe2026 says yes.',
        'literal' => '@doe2026 says yes.',
        'match' => static fn (AstNode $node): bool => $node->type === 'citation',
        'assert' => static function (TestRunner $t, AstNode $node): void {
            $t->same('doe2026', $node->attr('id'));
            $t->same('author_in_text', $node->attr('mode'));
        },
    ],
    'wikilink' => [
        'markdown' => 'See [[Label|/target]] now.',
        'literal' => 'See [[Label|/target]] now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'link' && $node->attr('classes') === ['wikilink'],
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same('/target', $node->attr('url'));
            $t->same('Label', $inlineText($node));
        },
    ],
    'dollar math' => [
        'markdown' => 'Math $x+1$ done.',
        'literal' => 'Math $x+1$ done.',
        'match' => static fn (AstNode $node): bool => $node->type === 'math',
        'assert' => static function (TestRunner $t, AstNode $node): void {
            $t->same('x+1', $node->attr('text'));
            $t->same(false, $node->attr('display'));
        },
    ],
    'raw tex' => [
        'markdown' => 'TeX \\textbf{raw} done.',
        'literal' => 'TeX \\textbf{raw} done.',
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_tex',
        'assert' => static function (TestRunner $t, AstNode $node): void {
            $t->same('\\textbf{raw}', $node->attr('tex'));
            $t->same('textbf', $node->attr('command'));
        },
    ],
    'bare uri' => [
        'markdown' => 'Visit http://example.test/docs now.',
        'literal' => 'Visit http://example.test/docs now.',
        'literalByFormat' => [
            'markdown_mmd' => '',
            'markdown-mmd' => '',
            'markdown+mmd' => '',
            'markdown+multimarkdown' => '',
        ],
        'match' => static fn (AstNode $node): bool => $node->type === 'link' && $node->attr('classes') === ['uri'],
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same('http://example.test/docs', $node->attr('url'));
            $t->same('http://example.test/docs', $inlineText($node));
        },
    ],
    'bracketed span' => [
        'markdown' => 'See [marked]{.review data-x=1} now.',
        'literal' => 'See [marked]{.review data-x=1} now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'span' && $node->attr('classes') === ['review'],
        'assert' => static function (TestRunner $t, AstNode $node) use ($inlineText): void {
            $t->same(['review'], $node->attr('classes'));
            $t->same(['data-x' => '1'], $node->attr('attributes'));
            $t->same('marked', $inlineText($node));
        },
    ],
    'raw inline attribute' => [
        'markdown' => 'Before `<b>x</b>`{=html} after.',
        'literal' => 'Before <b>x</b>{=html} after.',
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_html_inline',
        'assert' => static function (TestRunner $t, AstNode $node): void {
            $t->same('html', $node->attr('format'));
            $t->same('<b>x</b>', $node->attr('text'));
            $t->same('<b>x</b>', $node->attr('html'));
        },
    ],
    'inline code attributes' => [
        'markdown' => 'Use `code`{.source data-kind=fixture} now.',
        'literal' => 'Use code{.source data-kind=fixture} now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'code' && $node->attr('classes') === ['source'],
        'assert' => static function (TestRunner $t, AstNode $node): void {
            $t->same('code', $node->attr('text'));
            $t->same(['source'], $node->attr('classes'));
            $t->same(['data-kind' => 'fixture'], $node->attr('attributes'));
        },
    ],
];

$assertPresent = static function (TestRunner $t, ?string $format, string $feature) use ($featureProbes, $findInline, $read): void {
    $probe = $featureProbes[$feature];
    $document = $read($format, $probe['markdown']);
    $match = $findInline($document, $probe['match']);
    $label = ($format ?? 'default') . ' enables ' . $feature;

    $t->true($match->type !== 'missing', $label);
    if ($match->type === 'missing') {
        return;
    }

    $probe['assert']($t, $match);
};

$assertAbsent = static function (TestRunner $t, ?string $format, string $feature) use ($featureProbes, $findInline, $inlineText, $read): void {
    $probe = $featureProbes[$feature];
    $document = $read($format, $probe['markdown']);
    $match = $findInline($document, $probe['match']);
    $paragraph = $document->children[0] ?? new AstNode('missing');
    $label = ($format ?? 'default') . ' disables ' . $feature;
    $literalByFormat = is_array($probe['literalByFormat'] ?? null) ? $probe['literalByFormat'] : [];
    $literal = $literalByFormat[$format ?? 'default'] ?? $probe['literal'];

    $t->same('missing', $match->type, $label);
    $t->same($literal, $inlineText($paragraph), $label . ' literal text');
};

$allFeatureFormats = [
    'default' => ['format' => null, 'disabled' => ['mark', 'wikilink', 'bare uri']],
    'markdown' => ['format' => 'markdown', 'disabled' => ['mark', 'wikilink', 'bare uri']],
    'pandoc' => ['format' => 'pandoc', 'disabled' => ['mark', 'wikilink', 'bare uri']],
    'commonmark_x' => ['format' => 'commonmark_x', 'disabled' => ['mark', 'citation', 'wikilink', 'raw tex', 'bare uri']],
    'commonmark-x' => ['format' => 'commonmark-x', 'disabled' => ['mark', 'citation', 'wikilink', 'raw tex', 'bare uri']],
];

$strictFormats = ['markdown_strict', 'markdown-strict', 'markdown+strict', 'commonmark'];
$gfmFormats = ['gfm', 'markdown+github'];
$githubMarkdownFormats = ['markdown_github', 'markdown-github'];
$gfmEnabled = ['strikeout', 'emoji', 'dollar math', 'bare uri'];
$gfmDisabled = array_values(array_diff(array_keys($featureProbes), $gfmEnabled));
$githubMarkdownEnabled = ['strikeout', 'emoji'];
$githubMarkdownDisabled = array_values(array_diff(array_keys($featureProbes), $githubMarkdownEnabled));
$phpExtraFormats = ['markdown_phpextra', 'markdown-php-extra', 'markdown+php_extra', 'markdown+php-extra', 'markdown+phpextra'];
$phpExtraEnabled = [];
$phpExtraDisabled = array_values(array_diff(array_keys($featureProbes), $phpExtraEnabled));
$mmdFormats = ['markdown_mmd', 'markdown-mmd', 'markdown+mmd', 'markdown+multimarkdown'];
$mmdEnabled = ['superscript', 'subscript', 'dollar math', 'raw inline attribute'];
$mmdDisabled = array_values(array_diff(array_keys($featureProbes), $mmdEnabled));
$rawTexAliasCases = [
    'commonmark raw_latex suffix leaves raw tex literal' => [
        'options' => ['format' => 'commonmark+raw_latex'],
        'expected' => false,
    ],
    'commonmark latex_macros suffix leaves raw tex literal' => [
        'options' => ['format' => 'commonmark+latex_macros'],
        'expected' => false,
    ],
    'commonmark raw_latex extension list leaves raw tex literal' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['+raw_latex']],
        'expected' => false,
    ],
    'commonmark latex_macros extension list leaves raw tex literal' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['+latex_macros']],
        'expected' => false,
    ],
    'markdown raw_latex suffix disables raw tex' => [
        'options' => ['format' => 'markdown-raw_latex'],
        'expected' => false,
    ],
    'markdown raw_latex extension list disables raw tex' => [
        'options' => ['format' => 'markdown', 'extensions' => ['-raw_latex']],
        'expected' => false,
    ],
];

return [
    'maps upstream pandoc markdown flavor profile default extension set' =>
        static function (TestRunner $t) use ($allFeatureFormats, $featureProbes, $assertAbsent, $assertPresent): void {
            $mapped = 0;
            foreach ($allFeatureFormats as $profile) {
                $format = $profile['format'];
                foreach (array_keys($featureProbes) as $feature) {
                    if (in_array($feature, $profile['disabled'], true)) {
                        $assertAbsent($t, $format, $feature);
                    } else {
                        $assertPresent($t, $format, $feature);
                    }
                    $mapped++;
                }
            }

            $t->same(65, $mapped);
        },
    'maps upstream strict and commonmark flavor profiles with pandoc extensions literal' =>
        static function (TestRunner $t) use ($strictFormats, $featureProbes, $assertAbsent): void {
            $mapped = 0;
            foreach ($strictFormats as $format) {
                foreach (array_keys($featureProbes) as $feature) {
                    $assertAbsent($t, $format, $feature);
                    $mapped++;
                }
            }

            $t->same(52, $mapped);
        },
    'maps upstream gfm and github markdown flavor profile extension split' =>
        static function (
            TestRunner $t
        ) use (
            $gfmFormats,
            $gfmEnabled,
            $gfmDisabled,
            $githubMarkdownFormats,
            $githubMarkdownEnabled,
            $githubMarkdownDisabled,
            $assertPresent,
            $assertAbsent
        ): void {
            $mapped = 0;
            foreach ($gfmFormats as $format) {
                foreach ($gfmEnabled as $feature) {
                    $assertPresent($t, $format, $feature);
                    $mapped++;
                }
                foreach ($gfmDisabled as $feature) {
                    $assertAbsent($t, $format, $feature);
                    $mapped++;
                }
            }
            foreach ($githubMarkdownFormats as $format) {
                foreach ($githubMarkdownEnabled as $feature) {
                    $assertPresent($t, $format, $feature);
                    $mapped++;
                }
                foreach ($githubMarkdownDisabled as $feature) {
                    $assertAbsent($t, $format, $feature);
                    $mapped++;
                }
            }

            $t->same(52, $mapped);
        },
    'maps upstream php extra and multimarkdown flavor profile extension split' =>
        static function (TestRunner $t) use ($phpExtraFormats, $phpExtraEnabled, $phpExtraDisabled, $mmdFormats, $mmdEnabled, $mmdDisabled, $assertPresent, $assertAbsent): void {
            $mapped = 0;
            foreach ($phpExtraFormats as $format) {
                foreach ($phpExtraEnabled as $feature) {
                    $assertPresent($t, $format, $feature);
                    $mapped++;
                }
                foreach ($phpExtraDisabled as $feature) {
                    $assertAbsent($t, $format, $feature);
                    $mapped++;
                }
            }
            foreach ($mmdFormats as $format) {
                foreach ($mmdEnabled as $feature) {
                    $assertPresent($t, $format, $feature);
                    $mapped++;
                }
                foreach ($mmdDisabled as $feature) {
                    $assertAbsent($t, $format, $feature);
                    $mapped++;
                }
            }

            $t->same(117, $mapped);
        },
    'maps upstream markdown format suffix extension overrides' =>
        static function (TestRunner $t) use ($featureProbes, $assertPresent, $assertAbsent): void {
            $enabledFormat = 'commonmark+emoji+strikeout+mark+superscript+subscript+citations+wikilinks+tex_math_dollars+raw_tex+bare_uri_autolinks+bracketed_spans+raw_attribute+inline_attributes';
            $disabledFormat = 'markdown-emoji-strikeout-subscript-raw_tex';
            $mapped = 0;

            foreach (array_keys($featureProbes) as $feature) {
                if ($feature === 'raw tex') {
                    $assertAbsent($t, $enabledFormat, $feature);
                } else {
                    $assertPresent($t, $enabledFormat, $feature);
                }
                $mapped++;
            }

            foreach (['emoji', 'strikeout', 'raw tex'] as $feature) {
                $assertAbsent($t, $disabledFormat, $feature);
                $mapped++;
            }

            $t->same(16, $mapped);
        },
    'maps upstream markdown raw tex extension aliases' =>
        static function (TestRunner $t) use ($rawTexAliasCases, $findInline, $inlineText): void {
            foreach ($rawTexAliasCases as $label => $case) {
                $document = (new MarkdownReader($case['options']))->read('TeX \textbf{alias} done.');
                $match = $findInline($document, static fn (AstNode $node): bool => $node->type === 'raw_tex');

                if ($case['expected']) {
                    $t->same('raw_tex', $match->type, $label);
                    $t->same('\textbf{alias}', $match->attr('tex'), $label . ' tex payload');
                    $t->same('textbf', $match->attr('command'), $label . ' command');
                    continue;
                }

                $paragraph = $document->children[0] ?? new AstNode('missing');
                $t->same('missing', $match->type, $label);
                $t->same('paragraph', $paragraph->type, $label . ' paragraph');
                $t->same('TeX \textbf{alias} done.', $inlineText($paragraph), $label . ' literal text');
            }
        },
    'records upstream markdown flavor profile surge mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(308, 65 + 52 + 52 + 117 + 16 + 6);
        },
];
