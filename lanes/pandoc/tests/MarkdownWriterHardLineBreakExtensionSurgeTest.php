<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$inlineTypes = static function (AstNode $document): array {
    $paragraph = $document->children[0] ?? new AstNode('missing');
    if (!$paragraph instanceof AstNode) {
        return [];
    }

    return array_map(static fn (AstNode $node): string => $node->type, $paragraph->children);
};

$plainText = null;
$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'space' || $node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return trim((string) preg_replace('/\s+/', ' ', $text));
};

$cases = [
    'markdown linebreak writes bare newline' => [
        'document' => $document([$paragraph([$text('Alpha'), $linebreak(), $text('Beta')])]),
        'options' => ['format' => 'markdown+hard_line_breaks'],
        'expected' => "Alpha\nBeta",
        'roundTripFormat' => 'markdown+hard_line_breaks',
        'roundTripTypes' => ['text', 'linebreak', 'text'],
    ],
    'commonmark linebreak writes bare newline' => [
        'document' => $document([$paragraph([$text('Alpha'), $linebreak(), $text('Beta')])]),
        'options' => ['format' => 'commonmark+hard_line_breaks'],
        'expected' => "Alpha\nBeta",
        'roundTripFormat' => 'commonmark+hard_line_breaks',
        'roundTripTypes' => ['text', 'linebreak', 'text'],
    ],
    'gfm linebreak writes bare newline' => [
        'document' => $document([$paragraph([$text('Alpha'), $linebreak(), $text('Beta')])]),
        'options' => ['format' => 'gfm+hard_line_breaks'],
        'expected' => "Alpha\nBeta",
        'roundTripFormat' => 'gfm+hard_line_breaks',
        'roundTripTypes' => ['text', 'linebreak', 'text'],
    ],
    'markdown softbreak writes space' => [
        'document' => $document([$paragraph([$text('Alpha'), $softbreak(), $text('Beta')])]),
        'options' => ['format' => 'markdown+hard_line_breaks'],
        'expected' => 'Alpha Beta',
        'roundTripFormat' => 'markdown+hard_line_breaks',
        'roundTripTypes' => ['text'],
    ],
    'commonmark softbreak writes space' => [
        'document' => $document([$paragraph([$text('Alpha'), $softbreak(), $text('Beta')])]),
        'options' => ['format' => 'commonmark+hard_line_breaks'],
        'expected' => 'Alpha Beta',
        'roundTripFormat' => 'commonmark+hard_line_breaks',
        'roundTripTypes' => ['text'],
    ],
    'gfm softbreak writes space' => [
        'document' => $document([$paragraph([$text('Alpha'), $softbreak(), $text('Beta')])]),
        'options' => ['format' => 'gfm+hard_line_breaks'],
        'expected' => 'Alpha Beta',
        'roundTripFormat' => 'gfm+hard_line_breaks',
        'roundTripTypes' => ['text'],
    ],
    'extensions array enables hard line break writer' => [
        'document' => $document([$paragraph([$text('Alpha'), $linebreak(), $text('Beta')])]),
        'options' => ['format' => 'markdown', 'extensions' => ['hard_line_breaks']],
        'expected' => "Alpha\nBeta",
        'roundTripFormat' => 'markdown+hard_line_breaks',
        'roundTripTypes' => ['text', 'linebreak', 'text'],
    ],
    'extension string alias enables hard line break writer' => [
        'document' => $document([$paragraph([$text('Alpha'), $linebreak(), $text('Beta')])]),
        'options' => ['format' => 'markdown', 'extensions' => '+hard-line-break'],
        'expected' => "Alpha\nBeta",
        'roundTripFormat' => 'markdown+hard_line_breaks',
        'roundTripTypes' => ['text', 'linebreak', 'text'],
    ],
    'later format override disables hard line break writer' => [
        'document' => $document([$paragraph([$text('Alpha'), $linebreak(), $text('Beta')])]),
        'options' => ['format' => 'markdown+hard_line_breaks-hard_line_breaks'],
        'expected' => "Alpha\\\nBeta",
        'roundTripFormat' => 'markdown',
        'roundTripTypes' => ['text', 'linebreak', 'text'],
    ],
    'hard line breaks suppress auto wrapping' => [
        'document' => $document([$paragraph([$text('alpha beta gamma delta epsilon')])]),
        'options' => ['format' => 'markdown+hard_line_breaks', 'wrap' => 'auto', 'columns' => 10],
        'expected' => 'alpha beta gamma delta epsilon',
    ],
    'hard line break newline still escapes marker starts' => [
        'document' => $document([$paragraph([$text('Lead'), $linebreak(), $text('- not a list')])]),
        'options' => ['format' => 'markdown+hard_line_breaks'],
        'expected' => "Lead\n\\- not a list",
        'roundTripFormat' => 'markdown+hard_line_breaks',
        'roundTripTypes' => ['text', 'linebreak', 'text'],
        'plainText' => 'Lead - not a list',
    ],
    'nested emphasis hard line break writes bare newline' => [
        'document' => $document([$paragraph([$emph([$text('Alpha'), $linebreak(), $text('Beta')])])]),
        'options' => ['format' => 'markdown+hard_line_breaks'],
        'expected' => "*Alpha\nBeta*",
    ],
];

$tests = [
    'records markdown writer hard-line-break extension surge mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(12, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer hard-line-break extension surge ' . $label] =
        static function (TestRunner $t) use ($case, $inlineTypes, $plainText): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($case['expected'], $markdown);

            if (isset($case['roundTripFormat'])) {
                $roundTrip = (new MarkdownReader(['format' => $case['roundTripFormat']]))->read($markdown);
                $t->same($case['roundTripTypes'], $inlineTypes($roundTrip));

                if (isset($case['plainText'])) {
                    $t->same($case['plainText'], $plainText($roundTrip));
                }
            }
        };
}

return $tests;
