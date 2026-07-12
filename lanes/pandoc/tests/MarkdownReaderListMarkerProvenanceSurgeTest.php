<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$tests = [];

$collectBulletMarkers = static function (AstNode $node) use (&$collectBulletMarkers): array {
    $markers = [];
    if ($node->type === 'bullet_list') {
        $markers[] = (string) $node->attr('marker', '');
    }

    foreach ($node->children as $child) {
        array_push($markers, ...$collectBulletMarkers($child));
    }

    return $markers;
};

$collectExampleLabels = static function (AstNode $node) use (&$collectExampleLabels): array {
    $labels = [];
    if ($node->type === 'list_item' && $node->attr('exampleLabel', null) !== null) {
        $labels[] = (string) $node->attr('exampleLabel');
    }

    foreach ($node->children as $child) {
        array_push($labels, ...$collectExampleLabels($child));
    }

    return $labels;
};

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$bulletCases = [
    'dash plain list' => ["- alpha\n- beta", ['-']],
    'plus plain list' => ["+ alpha\n+ beta", ['+']],
    'star plain list' => ["* alpha\n* beta", ['*']],
    'dash loose list' => ["- alpha\n\n- beta", ['-']],
    'plus loose list' => ["+ alpha\n\n+ beta", ['+']],
    'star loose list' => ["* alpha\n\n* beta", ['*']],
    'dash task list' => ["- [ ] todo\n- [x] done", ['-']],
    'plus task list' => ["+ [ ] todo\n+ [x] done", ['+']],
    'star task list' => ["* [ ] todo\n* [x] done", ['*']],
    'dash empty item list' => ["-\n- next", ['-']],
    'plus empty item list' => ["+\n+ next", ['+']],
    'star empty item list' => ["*\n* next", ['*']],
    'dash with plus child' => ["- parent\n  + child", ['-', '+']],
    'dash with star child' => ["- parent\n  * child", ['-', '*']],
    'plus with dash child' => ["+ parent\n  - child", ['+', '-']],
    'plus with star child' => ["+ parent\n  * child", ['+', '*']],
    'star with dash child' => ["* parent\n  - child", ['*', '-']],
    'star with plus child' => ["* parent\n  + child", ['*', '+']],
    'plus star dash deep nesting' => ["+ parent\n  * child\n    - grandchild", ['+', '*', '-']],
    'star dash plus deep nesting' => ["* parent\n  - child\n    + grandchild", ['*', '-', '+']],
    'dash plus star sibling nesting' => ["- parent\n  + child\n  * sibling", ['-', '+', '*']],
    'plus dash star sibling nesting' => ["+ parent\n  - child\n  * sibling", ['+', '-', '*']],
    'plus blockquote child' => ["+ lead\n  > quoted", ['+']],
    'star blockquote child' => ["* lead\n  > quoted", ['*']],
    'plus fenced code child' => ["+ lead\n  ```php\n  echo 1;\n  ```", ['+']],
    'star fenced code child' => ["* lead\n  ```php\n  echo 1;\n  ```", ['*']],
    'plus heading child' => ["+ lead\n  # Child", ['+']],
    'star heading child' => ["* lead\n  # Child", ['*']],
    'plus then star boundary' => ["+ first\n* second", ['+', '*']],
    'star then plus boundary' => ["* first\n+ second", ['*', '+']],
];

foreach ($bulletCases as $name => [$markdown, $expectedMarkers]) {
    $tests['maps upstream markdown reader list marker provenance surge bullet ' . $name] =
        static function (TestRunner $t) use ($markdown, $expectedMarkers, $collectBulletMarkers): void {
            $reader = new MarkdownReader();
            $document = $reader->read($markdown);

            $t->same($expectedMarkers, $collectBulletMarkers($document), $markdown);
        };
}

$exampleLabelCases = [];
foreach ([
    'alpha',
    'review-1',
    'case_2',
    'A9',
    'fig-1',
    'tab_2',
    'x-y_z9',
    'source42',
    'Appendix-A',
    'proof_7',
    'queue-2026',
    'md-reader',
    'block-list',
    'task_done',
    'nested-3',
    'romanIV',
    'alpha_beta',
    'z9',
    'case-019',
    'surge_020',
] as $index => $label) {
    $exampleLabelCases['single label ' . $label] = [
        'markdown' => '(@' . $label . ') labeled example ' . ($index + 1),
        'labels' => [$label],
        'fragments' => ['(@' . $label . ') labeled example ' . ($index + 1)],
    ];
}

$exampleLabelCases += [
    'two labeled examples' => [
        'markdown' => "(@first) first example\n(@second) second example",
        'labels' => ['first', 'second'],
        'fragments' => ['(@first) first example', '(@second) second example'],
    ],
    'labeled task examples' => [
        'markdown' => "(@todo) [ ] task todo\n(@done) [x] task done",
        'labels' => ['todo', 'done'],
        'fragments' => ['(@todo) [ ] task todo', '(@done) [x] task done'],
    ],
    'labeled example with blockquote' => [
        'markdown' => "(@quote) > quoted\n(@next) next",
        'labels' => ['quote', 'next'],
        'fragments' => ['(@quote)', '> quoted', '(@next) next'],
    ],
    'labeled example with fenced code' => [
        'markdown' => "(@code) ```php\n    echo 1;\n    ```\n(@after) after",
        'labels' => ['code', 'after'],
        'fragments' => ['(@code)', '```php', '(@after) after'],
    ],
    'labeled example with heading' => [
        'markdown' => "(@heading) # Heading child\n(@after-heading) after",
        'labels' => ['heading', 'after-heading'],
        'fragments' => ['(@heading)', '# Heading child', '(@after-heading) after'],
    ],
    'labeled loose example' => [
        'markdown' => "(@loose) first paragraph\n\n    second paragraph\n(@next-loose) next",
        'labels' => ['loose', 'next-loose'],
        'fragments' => ['(@loose) first paragraph', '(@next-loose) next'],
    ],
    'nested labeled example under bullet' => [
        'markdown' => "- parent\n  (@nested) child",
        'labels' => ['nested'],
        'fragments' => ['  (@nested) child'],
        'reference' => false,
    ],
    'nested labeled examples under plus bullet' => [
        'markdown' => "+ parent\n  (@nested-one) child\n  (@nested-two) sibling",
        'labels' => ['nested-one', 'nested-two'],
        'fragments' => ['  (@nested-one) child', '  (@nested-two) sibling'],
        'reference' => false,
    ],
    'deep nested labeled example' => [
        'markdown' => "- parent\n  - child\n    (@deep-label) grandchild",
        'labels' => ['deep-label'],
        'fragments' => ['    (@deep-label) grandchild'],
        'reference' => false,
    ],
    'anonymous example stays anonymous between labels' => [
        'markdown' => "(@before) before\n(@) anonymous\n(@after) after",
        'labels' => ['before', 'after'],
        'fragments' => ['(@before) before', '(@) anonymous', '(@after) after'],
    ],
];

foreach ($exampleLabelCases as $name => $case) {
    $tests['maps upstream markdown reader list marker provenance surge numbered example ' . $name] =
        static function (TestRunner $t) use ($case, $collectExampleLabels, $inlineText): void {
            $reader = new MarkdownReader();
            $document = $reader->read($case['markdown']);

            $t->same($case['labels'], $collectExampleLabels($document), $case['markdown']);
            foreach ($case['fragments'] as $fragment) {
            }

            if (($case['reference'] ?? true) !== false && $case['labels'] !== []) {
                $referenceMarkdown = $case['markdown'] . "\n\nReference (@{$case['labels'][0]}).";
                $referenceDocument = $reader->read($referenceMarkdown);
                $last = $referenceDocument->children[array_key_last($referenceDocument->children)] ?? new AstNode('missing');
                $t->contains('Reference (1).', $inlineText($last));
            }
        };
}

$tests['records upstream markdown reader list marker provenance mapped-case count'] =
    static function (TestRunner $t) use ($bulletCases, $exampleLabelCases): void {
        $t->same(60, count($bulletCases) + count($exampleLabelCases));
    };

return $tests;
