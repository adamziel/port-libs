<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$listItemText = static function (AstNode $item) use ($inlineText): string {
    $parts = [];
    foreach ($item->children as $child) {
        if ($child->type === 'bullet_list' || $child->type === 'ordered_list') {
            continue;
        }

        $parts[] = trim($inlineText($child));
    }

    return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
};

$nodeSummary = static function (AstNode $node) use ($inlineText, $listItemText): array {
    $summary = ['type' => $node->type];
    if ($node->type === 'paragraph') {
        $text = trim($inlineText($node));
        $summary['text'] = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    } elseif ($node->type === 'heading') {
        $summary['level'] = $node->attr('level');
        $summary['text'] = (string) $node->attr('text', '');
    } elseif ($node->type === 'code_block') {
        $summary['text'] = (string) $node->attr('text', '');
        $classes = $node->attr('classes', []);
        if ($classes !== []) {
            $summary['classes'] = $classes;
        }
    } elseif ($node->type === 'bullet_list' || $node->type === 'ordered_list') {
        $summary['items'] = array_map($listItemText, $node->children);
        if ($node->type === 'ordered_list') {
            $summary['start'] = $node->attr('start');
            $summary['style'] = $node->attr('style');
            $summary['delimiter'] = $node->attr('delimiter');
        }
    } elseif ($node->type === 'raw_html') {
        $summary['html'] = (string) $node->attr('html', '');
    }

    return $summary;
};

$bodyCases = [
    'paragraph continuation' => [
        'source' => "Opening\n    continuation",
        'summary' => [
            ['type' => 'paragraph', 'text' => 'Opening continuation'],
        ],
    ],
    'empty first line paragraph continuation' => [
        'source' => "\n    First\n    continuation",
        'summary' => [
            ['type' => 'paragraph', 'text' => 'First continuation'],
        ],
    ],
    'tab continuation' => [
        'source' => "Opening\n\tTabbed continuation",
        'summary' => [
            ['type' => 'paragraph', 'text' => 'Opening Tabbed continuation'],
        ],
    ],
    'quoted marker paragraph continuation' => [
        'source' => "Opening\n    > quoted continuation",
        'summary' => [
            ['type' => 'paragraph', 'text' => 'Opening > quoted continuation'],
        ],
    ],
    'heading continuation' => [
        'source' => "Opening\n    ## Nested heading",
        'summary' => [
            ['type' => 'paragraph', 'text' => 'Opening'],
            ['type' => 'heading', 'level' => 2, 'text' => 'Nested heading'],
        ],
    ],
    'bullet list continuation' => [
        'source' => "Opening\n    - bullet item",
        'summary' => [
            ['type' => 'paragraph', 'text' => 'Opening - bullet item'],
        ],
    ],
    'ordered list continuation' => [
        'source' => "Opening\n    1. ordered item",
        'summary' => [
            ['type' => 'paragraph', 'text' => 'Opening 1. ordered item'],
        ],
    ],
    'fenced code continuation' => [
        'source' => "Opening\n    ``` php\n    echo 1;\n    ```",
        'summary' => [
            ['type' => 'paragraph', 'text' => 'Opening'],
            ['type' => 'code_block', 'text' => 'echo 1;', 'classes' => ['php']],
        ],
    ],
    'indented code continuation' => [
        'source' => "Opening\n        code line",
        'summary' => [
            ['type' => 'paragraph', 'text' => 'Opening code line'],
        ],
    ],
    'raw html continuation' => [
        'source' => "Opening\n    <section>\n    raw\n    </section>",
        'summary' => [
            ['type' => 'paragraph', 'text' => 'Opening'],
            ['type' => 'raw_html', 'html' => "<section>\nraw\n</section>"],
        ],
    ],
];

$labelCases = [
    'dash label' => 'source-note',
    'underscore label' => 'source_note',
    'upper label' => 'SourceNote',
    'numeric label' => 'note42',
    'dotted label' => 'source.note',
];

$tests = [];
$caseNumber = 0;
foreach ($labelCases as $labelName => $labelRoot) {
    foreach ($bodyCases as $bodyName => $case) {
        $caseNumber++;
        $label = $labelRoot . '-' . str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT);
        $tests['maps upstream markdown footnote continuation surge ' . str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT) . ' ' . $labelName . ' ' . $bodyName] =
            static function (TestRunner $t) use ($case, $label, $nodeSummary): void {
                $source = $case['source'];
                $definition = '[^' . $label . ']:' . (str_starts_with($source, "\n") ? '' : ' ') . $source;
                $markdown = 'Review note source[^' . $label . '].' . "\n\n" . $definition;
                $document = (new MarkdownReader())->read($markdown);
                $paragraph = $document->children[0] ?? new AstNode('missing');
                $note = null;
                foreach ($paragraph->children as $child) {
                    if ($child->type === 'note') {
                        $note = $child;
                        break;
                    }
                }

                $t->same(1, count($document->children), $label . ' should remove footnote definition from document body');
                $t->true($note instanceof AstNode, $label . ' should resolve to a note node');
                if (!$note instanceof AstNode) {
                    return;
                }

                $t->same($label, $note->attr('label'), $label . ' source label');
                $t->same($case['summary'], array_map($nodeSummary, $note->children), $label . ' normalized note body');

                $roundTrip = (new MarkdownWriter())->write($document);
                $t->contains('[^' . $label . ']', $roundTrip, $label . ' writer keeps source label');
            };
    }
}

$tests['records upstream markdown footnote continuation surge mapped-case count'] =
    static function (TestRunner $t) use ($caseNumber): void {
        $t->same(50, $caseNumber);
    };

return $tests;
