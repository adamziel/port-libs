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
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$readFirstBlock = static function (string $markdown, array $options = []): AstNode {
    $document = (new MarkdownReader($options))->read($markdown);

    return $document->children[0] ?? new AstNode('missing');
};

$escapedReferenceDelimiterCases = [];
$namedReferences = [
    'amp',
    'copy',
    'reg',
    'euro',
    'nbsp',
    'mdash',
    'hellip',
    'Auml',
    'notin',
    'lt',
    'gt',
    'quot',
    'apos',
];
foreach ($namedReferences as $name) {
    $escapedReferenceDelimiterCases['escaped ampersand named ' . $name] = [
        'markdown' => 'Review \&' . $name . '; source.',
        'expected' => 'Review &' . $name . '; source.',
    ];
    $escapedReferenceDelimiterCases['escaped semicolon named ' . $name] = [
        'markdown' => 'Review &' . $name . '\; source.',
        'expected' => 'Review &' . $name . '; source.',
    ];
}

$decimalReferences = [
    '0',
    '65',
    '160',
    '169',
    '955',
    '8212',
    '8230',
    '128512',
    '1114111',
    '55296',
];
foreach ($decimalReferences as $digits) {
    $escapedReferenceDelimiterCases['escaped ampersand decimal ' . $digits] = [
        'markdown' => 'Review \&#' . $digits . '; source.',
        'expected' => 'Review &#' . $digits . '; source.',
    ];
    $escapedReferenceDelimiterCases['escaped hash decimal ' . $digits] = [
        'markdown' => 'Review &\#' . $digits . '; source.',
        'expected' => 'Review &#' . $digits . '; source.',
    ];
    $escapedReferenceDelimiterCases['escaped semicolon decimal ' . $digits] = [
        'markdown' => 'Review &#' . $digits . '\; source.',
        'expected' => 'Review &#' . $digits . '; source.',
    ];
}

$hexReferences = [
    'x0',
    'x41',
    'xA0',
    'xA9',
    'x3bb',
    'X3BB',
    'x2014',
    'x2026',
    'x1F600',
    'xD800',
];
foreach ($hexReferences as $digits) {
    $escapedReferenceDelimiterCases['escaped ampersand hex ' . $digits] = [
        'markdown' => 'Review \&#' . $digits . '; source.',
        'expected' => 'Review &#' . $digits . '; source.',
    ];
    $escapedReferenceDelimiterCases['escaped hash hex ' . $digits] = [
        'markdown' => 'Review &\#' . $digits . '; source.',
        'expected' => 'Review &#' . $digits . '; source.',
    ];
    $escapedReferenceDelimiterCases['escaped semicolon hex ' . $digits] = [
        'markdown' => 'Review &#' . $digits . '\; source.',
        'expected' => 'Review &#' . $digits . '; source.',
    ];
}

$smartProfileCases = [
    'default paragraph smart enabled' => [
        'markdown' => '\&amp; -- ...',
        'options' => [],
        'block' => 'paragraph',
        'expected' => '&amp; – …',
    ],
    'commonmark paragraph smart disabled' => [
        'markdown' => '\&amp; -- ...',
        'options' => ['format' => 'commonmark'],
        'block' => 'paragraph',
        'expected' => '&amp; -- ...',
    ],
    'commonmark paragraph smart override enabled' => [
        'markdown' => '\&amp; -- ...',
        'options' => ['format' => 'commonmark+smart'],
        'block' => 'paragraph',
        'expected' => '&amp; – …',
    ],
    'gfm paragraph smart disabled' => [
        'markdown' => '\&amp; -- ...',
        'options' => ['format' => 'gfm'],
        'block' => 'paragraph',
        'expected' => '&amp; -- ...',
    ],
    'gfm paragraph smart override enabled' => [
        'markdown' => '\&amp; -- ...',
        'options' => ['format' => 'gfm+smart'],
        'block' => 'paragraph',
        'expected' => '&amp; – …',
    ],
    'markdown paragraph smart override disabled' => [
        'markdown' => '\&amp; -- ...',
        'options' => ['format' => 'markdown-smart'],
        'block' => 'paragraph',
        'expected' => '&amp; -- ...',
    ],
    'default heading smart enabled' => [
        'markdown' => '# \&#955; -- ...',
        'options' => [],
        'block' => 'heading',
        'expected' => '&#955; – …',
    ],
    'commonmark heading smart disabled' => [
        'markdown' => '# \&#955; -- ...',
        'options' => ['format' => 'commonmark'],
        'block' => 'heading',
        'expected' => '&#955; -- ...',
    ],
    'commonmark heading smart override enabled' => [
        'markdown' => '# \&#955; -- ...',
        'options' => ['format' => 'commonmark+smart'],
        'block' => 'heading',
        'expected' => '&#955; – …',
    ],
    'gfm heading smart disabled' => [
        'markdown' => '# \&#955; -- ...',
        'options' => ['format' => 'gfm'],
        'block' => 'heading',
        'expected' => '&#955; -- ...',
    ],
    'gfm heading smart override enabled' => [
        'markdown' => '# \&#955; -- ...',
        'options' => ['format' => 'gfm+smart'],
        'block' => 'heading',
        'expected' => '&#955; – …',
    ],
    'markdown heading smart override disabled' => [
        'markdown' => '# \&#955; -- ...',
        'options' => ['format' => 'markdown-smart'],
        'block' => 'heading',
        'expected' => '&#955; -- ...',
    ],
];

return [
    'maps upstream markdown escaped entity delimiter character-reference cases' =>
        static function (TestRunner $t) use ($escapedReferenceDelimiterCases, $inlineText, $readFirstBlock): void {
            $mapped = 0;
            foreach ($escapedReferenceDelimiterCases as $label => $case) {
                $block = $readFirstBlock($case['markdown']);

                $t->same('paragraph', $block->type, $label);
                $t->same($case['expected'], $block->attr('text'), $label);
                $t->same($case['expected'], $inlineText($block), $label);
                $mapped++;
            }

            $t->same(86, $mapped);
        },
    'keeps escaped entity delimiters profile-stable around smart punctuation' =>
        static function (TestRunner $t) use ($inlineText, $readFirstBlock, $smartProfileCases): void {
            $mapped = 0;
            foreach ($smartProfileCases as $label => $case) {
                $block = $readFirstBlock($case['markdown'], $case['options']);

                $t->same($case['block'], $block->type, $label);
                if ($block->type === 'paragraph') {
                    $t->same($case['expected'], $block->attr('text'), $label);
                }
                $t->same($case['expected'], $inlineText($block), $label);
                $mapped++;
            }

            $t->same(12, $mapped);
        },
    'records upstream markdown escaped entity delimiter surge mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(98, 86 + 12);
        },
];
