<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
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

$containsInlineType = static function (AstNode $node, string $type) use (&$containsInlineType): bool {
    if ($node->type === $type) {
        return true;
    }

    foreach ($node->children as $child) {
        if ($containsInlineType($child, $type)) {
            return true;
        }
    }

    return false;
};

$casePrefixes = [
    'H',
    'CO',
    'Ca',
    'Fe',
    'var',
    'x',
    'R2',
    'A9',
];
$caseSuffixes = [
    'O',
    'n',
    'alpha',
    'beta2',
    '2nd',
];
$boundaryCases = [];
foreach ($casePrefixes as $prefix) {
    foreach ($caseSuffixes as $suffix) {
        $boundaryCases[] = [
            'type' => 'subscript',
            'source' => $prefix . '~2' . $suffix . ' stays literal.',
        ];
        $boundaryCases[] = [
            'type' => 'superscript',
            'source' => $prefix . '^2' . $suffix . ' stays literal.',
        ];
    }
}

$validBoundaryCases = [
    ['type' => 'subscript', 'source' => 'H~2 O', 'plain' => 'H2 O'],
    ['type' => 'subscript', 'source' => 'H~2.O', 'plain' => 'H2.O'],
    ['type' => 'subscript', 'source' => 'H~2,O', 'plain' => 'H2,O'],
    ['type' => 'subscript', 'source' => 'H~2)O', 'plain' => 'H2)O'],
    ['type' => 'subscript', 'source' => 'H~2', 'plain' => 'H2'],
    ['type' => 'subscript', 'source' => 'H~2*flammable*', 'plain' => 'H2flammable'],
    ['type' => 'superscript', 'source' => 'x^2 n', 'plain' => 'x2 n'],
    ['type' => 'superscript', 'source' => 'x^2.n', 'plain' => 'x2.n'],
    ['type' => 'superscript', 'source' => 'x^2,n', 'plain' => 'x2,n'],
    ['type' => 'superscript', 'source' => 'x^2)n', 'plain' => 'x2)n'],
    ['type' => 'superscript', 'source' => 'x^2', 'plain' => 'x2'],
    ['type' => 'superscript', 'source' => 'x^2*raised*', 'plain' => 'x2raised'],
    ['type' => 'subscript', 'source' => 'H~2~O', 'plain' => 'H2O'],
    ['type' => 'superscript', 'source' => 'x^2^n', 'plain' => 'x2n'],
];

return [
    'keeps upstream markdown reader short script forms literal without extension opt in' =>
        static function (TestRunner $t) use ($inlineText, $containsInlineType): void {
            $reader = new MarkdownReader(['format' => 'markdown']);
            $cases = [
                ['type' => 'subscript', 'source' => 'H~2O and water.'],
                ['type' => 'superscript', 'source' => 'X^2 and area.'],
            ];

            foreach ($cases as $index => $case) {
                $paragraph = $reader->read($case['source'])->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, 'literal case ' . $index);
                $t->same($case['source'], $inlineText($paragraph), 'literal case ' . $index . ' source text');
                $t->same(false, $containsInlineType($paragraph, $case['type']), 'literal case ' . $index . ' should not parse short script');
            }
        },
    'maps upstream markdown reader short script alphanumeric boundary cases' =>
        static function (TestRunner $t) use ($boundaryCases, $inlineText, $containsInlineType): void {
            $reader = new MarkdownReader();
            $mapped = 0;

            foreach ($boundaryCases as $index => $case) {
                $document = $reader->read($case['source']);
                $paragraph = $document->children[0] ?? new AstNode('missing');
                $markdown = (new MarkdownWriter())->write($document);
                $roundTrip = $reader->read($markdown)->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, 'case ' . $index);
                $t->same($case['source'], $inlineText($paragraph), 'case ' . $index . ' source text');
                $t->same(false, $containsInlineType($paragraph, $case['type']), 'case ' . $index . ' should not parse short script');
                $t->same($case['source'], $inlineText($roundTrip), 'case ' . $index . ' writer round trip text');
                $t->same(false, $containsInlineType($roundTrip, $case['type']), 'case ' . $index . ' writer round trip type');
                $mapped++;
            }

            $t->same(80, $mapped);
        },
    'preserves upstream markdown reader short script delimiter boundaries' =>
        static function (TestRunner $t) use ($validBoundaryCases, $inlineText, $containsInlineType): void {
            $reader = new MarkdownReader(['format' => 'markdown+short_subsuperscripts']);

            foreach ($validBoundaryCases as $index => $case) {
                $paragraph = $reader->read($case['source'])->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, 'valid case ' . $index);
                $t->same(true, $containsInlineType($paragraph, $case['type']), 'valid case ' . $index . ' should parse short script');
                $t->same($case['plain'], $inlineText($paragraph), 'valid case ' . $index . ' plain text');
            }
        },
    'records upstream markdown reader short script boundary surge mapped-case count' =>
        static function (TestRunner $t) use ($boundaryCases): void {
            $t->same(80, count($boundaryCases));
        },
];
