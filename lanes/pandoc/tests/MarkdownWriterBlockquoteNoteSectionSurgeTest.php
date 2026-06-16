<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$heading = static fn (string $value): AstNode => new AstNode('heading', ['level' => 2], [$text($value)]);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$codeBlock = static fn (string $value): AstNode => new AstNode('code_block', ['text' => $value]);
$note = static fn (string $label, array $children): AstNode => new AstNode('note', ['label' => $label], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$bodyCases = [
    'single paragraph body' => static function (string $label) use ($text, $paragraph): array {
        return [
            'children' => [
                $paragraph([$text('Single quoted note body ' . $label . '.')]),
            ],
            'expected' => [
                '> [^' . $label . ']: Single quoted note body ' . $label . '.',
            ],
        ];
    },
    'multi paragraph body' => static function (string $label) use ($text, $paragraph): array {
        return [
            'children' => [
                $paragraph([$text('First quoted note paragraph ' . $label . '.')]),
                $paragraph([$text('Second quoted note paragraph ' . $label . '.')]),
            ],
            'expected' => [
                '> [^' . $label . ']: First quoted note paragraph ' . $label . '.',
                '>',
                '>     Second quoted note paragraph ' . $label . '.',
            ],
        ];
    },
    'nested blockquote body' => static function (string $label) use ($text, $paragraph, $blockquote): array {
        return [
            'children' => [
                $paragraph([$text('Outer note paragraph ' . $label . '.')]),
                $blockquote([
                    $paragraph([$text('Nested note quote ' . $label . '.')]),
                ]),
            ],
            'expected' => [
                '> [^' . $label . ']: Outer note paragraph ' . $label . '.',
                '>',
                '>     > Nested note quote ' . $label . '.',
            ],
        ];
    },
    'leading code block body' => static function (string $label) use ($codeBlock): array {
        return [
            'children' => [
                $codeBlock('alpha ' . $label . "\n" . 'beta ' . $label),
            ],
            'expected' => [
                '> [^' . $label . ']:',
                '>         alpha ' . $label,
                '>         beta ' . $label,
            ],
        ];
    },
    'nested note reference body' => static function (string $label) use ($text, $paragraph, $note): array {
        $innerLabel = $label . '-inner';

        return [
            'children' => [
                $paragraph([
                    $text('Outer nested note body '),
                    $note($innerLabel, [
                        $paragraph([$text('Inner nested body ' . $label . '.')]),
                    ]),
                    $text(' closes.'),
                ]),
            ],
            'expected' => [
                '> [^' . $label . ']: Outer nested note body [^' . $innerLabel . '] closes.',
                '>',
                '> [^' . $innerLabel . ']: Inner nested body ' . $label . '.',
            ],
        ];
    },
];

$contexts = [
    'root quote section' => static function (AstNode $noteNode, string $label) use ($text, $paragraph, $heading, $blockquote, $document): array {
        return [
            'document' => $document([
                $blockquote([
                    $heading('Quote section ' . $label),
                    $paragraph([$text('Quoted note '), $noteNode, $text(' before the next quote heading.')]),
                    $heading('Next quote section ' . $label),
                    $paragraph([$text('Next section remains quoted.')]),
                ]),
            ]),
            'nextHeading' => '> ## Next quote section ' . $label,
        ];
    },
    'intro then quote section' => static function (AstNode $noteNode, string $label) use ($text, $paragraph, $heading, $blockquote, $document): array {
        return [
            'document' => $document([
                $blockquote([
                    $paragraph([$text('Intro quote paragraph stays before section.')]),
                    $heading('Quote section ' . $label),
                    $paragraph([$text('Quoted note '), $noteNode, $text(' before the next quote heading.')]),
                    $heading('Next quote section ' . $label),
                    $paragraph([$text('Next section remains quoted.')]),
                ]),
            ]),
            'nextHeading' => '> ## Next quote section ' . $label,
        ];
    },
    'outer pending note before quote' => static function (AstNode $noteNode, string $label) use ($text, $paragraph, $heading, $blockquote, $note, $document): array {
        return [
            'document' => $document([
                $paragraph([
                    $text('Lead note stays outside the quoted section '),
                    $note('lead-' . $label, [
                        $paragraph([$text('Lead note body ' . $label . '.')]),
                    ]),
                    $text('.'),
                ]),
                $blockquote([
                    $heading('Quote section ' . $label),
                    $paragraph([$text('Quoted note '), $noteNode, $text(' before the next quote heading.')]),
                    $heading('Next quote section ' . $label),
                    $paragraph([$text('Next section remains quoted.')]),
                ]),
            ]),
            'nextHeading' => '> ## Next quote section ' . $label,
            'outerDefinition' => '[^lead-' . $label . ']: Lead note body ' . $label . '.',
        ];
    },
    'nested quote after section' => static function (AstNode $noteNode, string $label) use ($text, $paragraph, $heading, $blockquote, $document): array {
        return [
            'document' => $document([
                $blockquote([
                    $heading('Quote section ' . $label),
                    $paragraph([$text('Quoted note '), $noteNode, $text(' before the next quote heading.')]),
                    $heading('Next quote section ' . $label),
                    $blockquote([
                        $paragraph([$text('Nested quote stays in the second section.')]),
                    ]),
                ]),
            ]),
            'nextHeading' => '> ## Next quote section ' . $label,
        ];
    },
    'trailing quote paragraph section' => static function (AstNode $noteNode, string $label) use ($text, $paragraph, $heading, $blockquote, $document): array {
        return [
            'document' => $document([
                $blockquote([
                    $heading('Quote section ' . $label),
                    $paragraph([$text('Quoted note '), $noteNode, $text(' before the next quote heading.')]),
                    $paragraph([$text('Additional quoted paragraph remains in the first section.')]),
                    $heading('Next quote section ' . $label),
                    $paragraph([$text('Next section remains quoted.')]),
                ]),
            ]),
            'nextHeading' => '> ## Next quote section ' . $label,
        ];
    },
];

$profiles = [
    'markdown' => [],
    'commonmark' => ['format' => 'commonmark+footnotes'],
    'gfm' => ['format' => 'gfm+footnotes'],
];

$tests = [];
$caseNumber = 0;

foreach ($contexts as $contextName => $contextFactory) {
    foreach ($bodyCases as $bodyName => $bodyFactory) {
        foreach ($profiles as $profileName => $profileOptions) {
            $caseNumber++;
            $label = 'quote-note-' . str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT);
            $body = $bodyFactory($label);
            $noteNode = $note($label, $body['children']);
            $context = $contextFactory($noteNode, $label);
            $options = ['referenceLocation' => 'end_of_section'] + $profileOptions;

            $tests['maps upstream markdown writer blockquote note section surge '
                . str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT)
                . ' ' . $contextName . ' ' . $bodyName . ' ' . $profileName] =
                static function (TestRunner $t) use ($context, $body, $label, $options): void {
                    $markdown = (new MarkdownWriter($options))->write($context['document']);
                    $expectedDefinition = implode("\n", $body['expected']);

                    $t->contains($expectedDefinition, $markdown);
                    $t->true(
                        strpos($markdown, '> [^' . $label . ']:') < strpos($markdown, $context['nextHeading']),
                        'Quoted note definition should flush before the next heading in the same blockquote'
                    );
                    $t->true(
                        !str_contains($markdown, "\n\n[^" . $label . ']:'),
                        'Quoted note definition should not leak outside the blockquote'
                    );
                    $t->true(!str_contains($markdown, '<blockquote'), 'Plain blockquote should remain markdown in this profile');

                    if (isset($context['outerDefinition'])) {
                        $t->contains("\n\n" . $context['outerDefinition'], $markdown);
                        $t->true(
                            strpos($markdown, $context['outerDefinition']) > strpos($markdown, $context['nextHeading']),
                            'Outer pending note should stay outside the blockquote-local section flush'
                        );
                    }
                };
        }
    }
}

$tests['records markdown writer blockquote note section surge mapped-case count'] =
    static function (TestRunner $t) use ($caseNumber): void {
        $t->same(75, $caseNumber);
    };

return $tests;
