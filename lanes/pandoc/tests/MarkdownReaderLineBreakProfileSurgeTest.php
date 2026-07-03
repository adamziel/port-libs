<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'raw_tex') {
        return (string) $node->attr('tex', '');
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

$read = static function (array|string|null $options, string $markdown): AstNode {
    if (is_array($options)) {
        return (new MarkdownReader($options))->read($markdown);
    }

    return (new MarkdownReader($options === null ? [] : ['format' => $options]))->read($markdown);
};

$featureProbes = [
    'strikeout' => [
        'markdown' => 'Before ~~gone~~ after.',
        'literal' => 'Before ~~gone~~ after.',
        'match' => static fn (AstNode $node): bool => $node->type === 'strikeout',
    ],
    'mark' => [
        'markdown' => 'Before ==flag== after.',
        'literal' => 'Before ==flag== after.',
        'match' => static fn (AstNode $node): bool => $node->type === 'span' && $node->attr('classes') === ['mark'],
    ],
    'emoji' => [
        'markdown' => 'Emoji :rocket: ready.',
        'literal' => 'Emoji :rocket: ready.',
        'match' => static fn (AstNode $node): bool => $node->type === 'span' && ($node->attr('attributes')['data-emoji'] ?? null) === 'rocket',
    ],
    'citation' => [
        'markdown' => '@doe2026 says yes.',
        'literal' => '@doe2026 says yes.',
        'match' => static fn (AstNode $node): bool => $node->type === 'citation',
    ],
    'inline footnote' => [
        'markdown' => 'Before ^[inline note] after.',
        'literal' => 'Before ^[inline note] after.',
        'match' => static fn (AstNode $node): bool => $node->type === 'note',
    ],
    'dollar math' => [
        'markdown' => 'Math $x+1$ done.',
        'literal' => 'Math $x+1$ done.',
        'match' => static fn (AstNode $node): bool => $node->type === 'math',
    ],
    'raw tex' => [
        'markdown' => 'TeX \\textbf{raw} done.',
        'literal' => 'TeX \\textbf{raw} done.',
        'match' => static fn (AstNode $node): bool => $node->type === 'raw_tex',
    ],
    'bare uri' => [
        'markdown' => 'Visit www.example.test/docs now.',
        'literal' => 'Visit www.example.test/docs now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'link' && $node->attr('classes') === ['uri'],
    ],
    'bracketed span' => [
        'markdown' => 'See [marked]{.review data-x=1} now.',
        'literal' => 'See [marked]{.review data-x=1} now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'span' && $node->attr('classes') === ['review'],
    ],
    'inline code attributes' => [
        'markdown' => 'Use `code`{.source data-kind=fixture} now.',
        'literal' => 'Use code{.source data-kind=fixture} now.',
        'match' => static fn (AstNode $node): bool => $node->type === 'code' && $node->attr('classes') === ['source'],
    ],
];

$allFeatures = array_fill_keys(array_keys($featureProbes), true);
$disabledFeatures = array_fill_keys(array_keys($featureProbes), false);

$aliasExpectations = [
    'markdown-github' => array_replace($disabledFeatures, [
        'strikeout' => true,
        'emoji' => true,
        'bare uri' => true,
    ]),
    'markdown-php-extra' => array_replace($disabledFeatures, [
        'bracketed span' => true,
    ]),
    'markdown-strict' => $disabledFeatures,
    'markdown-mmd' => array_replace($disabledFeatures, [
        'citation' => true,
        'dollar math' => true,
        'bracketed span' => true,
    ]),
    'commonmark-x' => array_replace($allFeatures, [
        'inline footnote' => false,
        'raw tex' => false,
    ]),
];

$lineBreakFormats = [
    'markdown',
    'pandoc',
    'commonmark',
    'commonmark_x',
    'commonmark-x',
    'gfm',
    'markdown_github',
    'markdown-github',
    'markdown_mmd',
    'markdown_phpextra',
    'markdown_strict',
];

$lineBreakCaseCount = count($lineBreakFormats) * 6;
$aliasCaseCount = count($aliasExpectations) * count($featureProbes);

return [
    'maps upstream markdown hyphenated reader format aliases to canonical extension profiles' =>
        static function (TestRunner $t) use ($aliasExpectations, $featureProbes, $findInline, $inlineText, $read): void {
            $mapped = 0;

            foreach ($aliasExpectations as $format => $expectations) {
                foreach ($expectations as $feature => $enabled) {
                    $probe = $featureProbes[$feature];
                    $document = $read($format, $probe['markdown']);
                    $match = $findInline($document, $probe['match']);
                    $paragraph = $document->children[0] ?? new AstNode('missing');
                    $label = "{$format} canonical alias {$feature}";

                    if ($enabled) {
                        $t->true($match->type !== 'missing', $label);
                    } else {
                        $t->same('missing', $match->type, $label);
                        $t->same($probe['literal'], $inlineText($paragraph), $label . ' literal text');
                    }
                    $mapped++;
                }
            }

            $t->same(50, $mapped);
        },
    'maps upstream markdown reader hard line break format extension overrides' =>
        static function (TestRunner $t) use ($lineBreakFormats, $read): void {
            $mapped = 0;

            foreach ($lineBreakFormats as $format) {
                $document = $read($format . '+hard_line_breaks', "alpha\nbeta");
                $paragraph = $document->children[0] ?? new AstNode('missing');
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same(['text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $format);
                $t->same("alpha\nbeta", $paragraph->attr('text'), $format);
                $t->same("alpha\\\nbeta", (new MarkdownWriter())->write($document), $format);
                $t->contains('<p>alpha<br/>beta</p>', $blocks, $format);
                $mapped++;
            }

            $t->same(11, $mapped);
        },
    'maps upstream markdown reader hard line break extensions option overrides' =>
        static function (TestRunner $t) use ($lineBreakFormats, $read): void {
            $mapped = 0;

            foreach ($lineBreakFormats as $format) {
                $document = $read(['format' => $format, 'extensions' => ['+hard-line-breaks']], "alpha\nbeta");
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same(['text', 'linebreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $format);
                $t->same("alpha\nbeta", $paragraph->attr('text'), $format);
                $mapped++;
            }

            $t->same(11, $mapped);
        },
    'maps upstream markdown reader ignore line break format extension overrides' =>
        static function (TestRunner $t) use ($lineBreakFormats, $read): void {
            $mapped = 0;

            foreach ($lineBreakFormats as $format) {
                $document = $read($format . '+ignore_line_breaks', "alpha\nbeta");
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $format);
                $t->same('alphabeta', $paragraph->attr('text'), $format);
                $t->same('alphabeta', (new MarkdownWriter())->write($document), $format);
                $mapped++;
            }

            $t->same(11, $mapped);
        },
    'maps upstream markdown reader ignore line break extensions option overrides' =>
        static function (TestRunner $t) use ($lineBreakFormats, $read): void {
            $mapped = 0;

            foreach ($lineBreakFormats as $format) {
                $document = $read(['format' => $format, 'extensions' => ['ignore-line-breaks' => true]], "alpha\nbeta");
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $format);
                $t->same('alphabeta', $paragraph->attr('text'), $format);
                $mapped++;
            }

            $t->same(11, $mapped);
        },
    'maps upstream markdown reader east asian line break format extension overrides' =>
        static function (TestRunner $t) use ($lineBreakFormats, $read): void {
            $mapped = 0;

            foreach ($lineBreakFormats as $format) {
                $document = $read($format . '+east_asian_line_breaks', "東\n京 source\n\nA\nB");
                $joined = $document->children[0] ?? new AstNode('missing');
                $latin = $document->children[1] ?? new AstNode('missing');

                $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $joined->children), $format);
                $t->same('東京 source', $joined->attr('text'), $format);
                $t->same(['text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $latin->children), $format);
                $t->same('A B', $latin->attr('text'), $format);
                $mapped++;
            }

            $t->same(11, $mapped);
        },
    'maps upstream markdown reader default softbreak profile remains unchanged' =>
        static function (TestRunner $t) use ($lineBreakFormats, $read): void {
            $mapped = 0;

            foreach ($lineBreakFormats as $format) {
                $document = $read($format, "alpha\nbeta");
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same(['text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $format);
                $t->same('alpha beta', $paragraph->attr('text'), $format);
                $mapped++;
            }

            $t->same(11, $mapped);
        },
    'records upstream markdown reader line break profile mapped-case count' =>
        static function (TestRunner $t) use ($aliasCaseCount, $lineBreakCaseCount): void {
            $t->same(116, $aliasCaseCount + $lineBreakCaseCount);
        },
];
