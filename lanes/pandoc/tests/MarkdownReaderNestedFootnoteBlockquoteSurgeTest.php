<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$collectNotes = null;
$collectNotes = static function (AstNode $node) use (&$collectNotes): array {
    $notes = $node->type === 'note' ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($notes, ...$collectNotes($child));
    }

    return $notes;
};

$nodeText = null;
$nodeText = static function (AstNode $node) use (&$nodeText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }
    if (($node->children ?? []) === [] && is_scalar($node->attr('text', null))) {
        return (string) $node->attr('text');
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= ' ' . $nodeText($child);
    }

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
};

$labels = [
    'dash label' => 'nested-a-01',
    'underscore label' => 'nested_a_02',
    'dotted label' => 'nested.a.03',
    'numeric label' => 'nested42',
    'uppercase label' => 'NestedA05',
];

$nestedBodies = [
    'plain nested note' => [
        'source' => '^[inner plain note]',
        'text' => 'inner plain note',
    ],
    'strong nested note' => [
        'source' => '^[inner **strong** note]',
        'text' => 'inner strong note',
    ],
    'code nested note' => [
        'source' => '^[inner `code` note]',
        'text' => 'inner code note',
    ],
    'balanced bracket nested note' => [
        'source' => '^[inner [balanced] note]',
        'text' => 'inner [balanced] note',
    ],
    'escaped bracket nested note' => [
        'source' => '^[inner escaped \] bracket]',
        'text' => 'inner escaped ] bracket',
    ],
];

$contexts = [
    'same-line definition body' => static function (string $label, string $nestedSource): array {
        return [
            'markdown' => 'Host note[^' . $label . '].' . "\n\n"
                . '[^' . $label . ']: Outer ' . $nestedSource . ' closes.',
            'shape' => 'single-paragraph',
            'outerTypes' => ['paragraph'],
        ];
    },
    'indented continuation lazy paragraph' => static function (string $label, string $nestedSource): array {
        return [
            'markdown' => 'Host note[^' . $label . '].' . "\n\n"
                . '[^' . $label . ']: Outer starts.' . "\n\n"
                . '    Continuation ' . $nestedSource . ' closes.' . "\n"
                . 'lazy continuation ' . $label,
            'shape' => 'single-paragraph',
            'outerTypes' => ['paragraph', 'paragraph'],
        ];
    },
    'blockquote definition boundary' => static function (string $label, string $nestedSource): array {
        return [
            'markdown' => '> Quote note[^' . $label . '].' . "\n"
                . '>' . "\n"
                . '> [^' . $label . ']: Outer ' . $nestedSource . ' closes.' . "\n"
                . 'outside ' . $label,
            'shape' => 'blockquote-outside-paragraph',
            'outsideText' => 'outside ' . $label,
            'outerTypes' => ['paragraph'],
        ];
    },
];

$tests = [];
$caseNumber = 0;
foreach ($labels as $labelName => $label) {
    foreach ($nestedBodies as $nestedName => $nested) {
        foreach ($contexts as $contextName => $buildContext) {
            $caseNumber++;
            $case = $buildContext($label, $nested['source']);
            $tests['maps upstream markdown reader nested footnote blockquote surge '
                . str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT)
                . ' ' . $labelName . ' ' . $nestedName . ' ' . $contextName] =
                static function (TestRunner $t) use ($case, $label, $nested, $collectNotes, $nodeText): void {
                    $document = (new MarkdownReader())->read($case['markdown']);
                    $notes = $collectNotes($document);
                    $outer = null;
                    foreach ($notes as $note) {
                        if ($note->attr('label') === $label) {
                            $outer = $note;
                            break;
                        }
                    }

                    $t->true($outer instanceof AstNode, $label . ' resolves the labelled outer note');
                    if (!$outer instanceof AstNode) {
                        return;
                    }

                    $nestedNotes = array_values(array_filter(
                        $collectNotes($outer),
                        static fn (AstNode $note): bool => $note !== $outer
                    ));
                    $t->same(1, count($nestedNotes), $label . ' resolves one nested inline note inside the outer note');
                    $t->same($nested['text'], $nodeText($nestedNotes[0] ?? new AstNode('missing')), $label . ' nested note text');
                    $t->same($case['outerTypes'], array_map(static fn (AstNode $node): string => $node->type, $outer->children));

                    if ($case['shape'] === 'single-paragraph') {
                        $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
                        return;
                    }

                    $t->same(['blockquote', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
                    $t->same($case['outsideText'], $nodeText($document->children[1] ?? new AstNode('missing')));
                };
        }
    }
}

$tests['records markdown reader nested footnote blockquote surge mapped-case count'] =
    static function (TestRunner $t) use ($caseNumber): void {
        $t->same(75, $caseNumber);
    };

return $tests;
