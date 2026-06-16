<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$rawMarkdown = static fn (string $value): AstNode => new AstNode('raw_markdown', [
    'format' => 'markdown',
    'markdown' => $value,
]);
$rawMarkdownBlock = static fn (string $value): AstNode => new AstNode('raw_block', [
    'format' => 'markdown',
    'text' => $value,
]);
$rawTex = static fn (string $value): AstNode => new AstNode('raw_tex', [
    'format' => 'latex',
    'tex' => $value,
]);
$rawTexBlock = static fn (string $value): AstNode => new AstNode('raw_block', [
    'format' => 'latex',
    'text' => $value,
]);

$payloads = [
    'raw markdown fenced div' => [
        'node' => $rawMarkdown(...),
        'normalized' => "::: {.review}\nbody\n:::",
    ],
    'raw block markdown alias' => [
        'node' => $rawMarkdownBlock(...),
        'normalized' => "::: note\nalias body\n:::",
    ],
    'raw tex environment' => [
        'node' => $rawTex(...),
        'normalized' => "\\begin{review}\nbody\n\\end{review}",
    ],
    'raw block latex alias' => [
        'node' => $rawTexBlock(...),
        'normalized' => "\\begin{note}\nalias body\n\\end{note}",
    ],
];

$lineEndingCases = [
    'crlf fixture payload' => static fn (string $normalized): string => str_replace("\n", "\r\n", $normalized),
    'cr fixture payload' => static fn (string $normalized): string => str_replace("\n", "\r", $normalized),
    'mixed crlf cr fixture payload' => static function (string $normalized): string {
        $lines = explode("\n", $normalized);

        return $lines[0] . "\r\n" . $lines[1] . "\r" . $lines[2];
    },
];

$contextCases = [
    'top level raw block' => static fn (AstNode $node, string $expected): array => [
        'document' => $document([$node]),
        'expected' => $expected,
    ],
    'paragraph raw block paragraph' => static fn (AstNode $node, string $expected): array => [
        'document' => $document([$paragraph('Before'), $node, $paragraph('After')]),
        'expected' => "Before\n\n" . $expected . "\n\nAfter",
    ],
    'blockquote raw block' => static function (AstNode $node, string $expected) use ($blockquote, $document): array {
        $quoted = implode("\n", array_map(
            static fn (string $line): string => '> ' . $line,
            explode("\n", $expected)
        ));

        return [
            'document' => $document([$blockquote([$node])]),
            'expected' => $quoted,
        ];
    },
];

$tests = [];
$caseCount = 0;

foreach ($payloads as $payloadLabel => $payload) {
    foreach ($lineEndingCases as $lineEndingLabel => $lineEndingPayload) {
        foreach ($contextCases as $contextLabel => $context) {
            $caseCount++;
            $tests['maps upstream markdown writer raw block line ending surge '
                . str_pad((string) $caseCount, 2, '0', STR_PAD_LEFT)
                . ' ' . $payloadLabel
                . ' ' . $lineEndingLabel
                . ' ' . $contextLabel] =
                static function (TestRunner $t) use ($context, $lineEndingPayload, $payload): void {
                    $source = $lineEndingPayload($payload['normalized']);
                    $node = $payload['node']($source);
                    $case = $context($node, $payload['normalized']);
                    $markdown = (new MarkdownWriter(['format' => 'markdown']))->write($case['document']);

                    $t->same($case['expected'], $markdown);
                    $t->true(!str_contains($markdown, "\r"), 'Raw block output should normalize CR and CRLF fixture bytes to LF');
                };
        }
    }
}

$tests['records markdown writer raw block line ending surge mapped case count'] =
    static function (TestRunner $t) use ($caseCount): void {
        $t->same(36, $caseCount);
    };

return $tests;
