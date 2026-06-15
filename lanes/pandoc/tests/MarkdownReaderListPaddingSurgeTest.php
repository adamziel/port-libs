<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
};

$listItemText = static function (AstNode $item) use ($plainText): string {
    $parts = [];
    foreach ($item->children as $child) {
        if ($child->type === 'code_block') {
            continue;
        }

        $parts[] = $plainText($child);
    }

    return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
};

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$markerCases = [
    'dash bullet' => ['marker' => '-', 'next' => '- next', 'list' => 'bullet_list'],
    'plus bullet' => ['marker' => '+', 'next' => '+ next', 'list' => 'bullet_list'],
    'star bullet' => ['marker' => '*', 'next' => '* next', 'list' => 'bullet_list'],
    'decimal period' => ['marker' => '1.', 'next' => '2. next', 'list' => 'ordered_list', 'start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
    'decimal paren' => ['marker' => '1)', 'next' => '2) next', 'list' => 'ordered_list', 'start' => 1, 'style' => 'decimal', 'delimiter' => 'one_paren'],
    'parenthesized decimal' => ['marker' => '(1)', 'next' => '(2) next', 'list' => 'ordered_list', 'start' => 1, 'style' => 'decimal', 'delimiter' => 'two_parens'],
    'default ordered' => ['marker' => '#.', 'next' => '#. next', 'list' => 'ordered_list', 'start' => 1, 'style' => 'default', 'delimiter' => 'default'],
    'upper alpha period' => ['marker' => 'A.', 'next' => 'B.  next', 'list' => 'ordered_list', 'start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'period'],
    'lower alpha paren' => ['marker' => 'a)', 'next' => 'b)  next', 'list' => 'ordered_list', 'start' => 1, 'style' => 'lower_alpha', 'delimiter' => 'one_paren'],
    'upper roman period' => ['marker' => 'IV.', 'next' => 'V.  next', 'list' => 'ordered_list', 'start' => 4, 'style' => 'upper_roman', 'delimiter' => 'period'],
];

$contentIndent = static fn (array $case): string => str_repeat(' ', strlen($case['marker']) + 1);

$assertListShape = static function (TestRunner $t, AstNode $list, array $case, callable $listItemText): void {
    $t->same($case['list'], $list->type);
    $t->same(2, count($list->children));
    $t->same('next', $listItemText($list->children[1]));

    foreach (['start', 'style', 'delimiter'] as $attr) {
        if (array_key_exists($attr, $case)) {
            $t->same($case[$attr], $list->attr($attr), $attr);
        }
    }
};

$tests = [];

foreach ($markerCases as $label => $case) {
    $tests["maps commonmark list padding surge {$label} code then paragraph"] =
        static function (TestRunner $t) use ($case, $contentIndent, $assertListShape, $listItemText, $childTypes): void {
            $document = (new MarkdownReader())->read(
                $case['marker'] . str_repeat(' ', 5) . 'code' . "\n"
                . $contentIndent($case) . 'paragraph' . "\n"
                . $case['next']
            );
            $list = $document->children[0] ?? new AstNode('missing');
            $first = $list->children[0] ?? new AstNode('missing');

            $assertListShape($t, $list, $case, $listItemText);
            $t->same(['code_block', 'text'], $childTypes($first));
            $t->same('code', $first->children[0]->attr('text'));
            $t->same('paragraph', $first->children[1]->attr('text'));
        };

    $tests["maps commonmark list padding surge {$label} code then blockquote"] =
        static function (TestRunner $t) use ($case, $contentIndent, $assertListShape, $listItemText, $plainText, $childTypes): void {
            $document = (new MarkdownReader())->read(
                $case['marker'] . str_repeat(' ', 5) . 'code' . "\n"
                . $contentIndent($case) . '> quoted' . "\n"
                . $case['next']
            );
            $list = $document->children[0] ?? new AstNode('missing');
            $first = $list->children[0] ?? new AstNode('missing');
            $quote = $first->children[1] ?? new AstNode('missing');

            $assertListShape($t, $list, $case, $listItemText);
            $t->same(['code_block', 'blockquote'], $childTypes($first));
            $t->same('code', $first->children[0]->attr('text'));
            $t->same('quoted', $plainText($quote));
        };

    $tests["maps commonmark list padding surge {$label} code continuation marker line"] =
        static function (TestRunner $t) use ($case, $contentIndent, $assertListShape, $listItemText, $childTypes): void {
            $document = (new MarkdownReader())->read(
                $case['marker'] . str_repeat(' ', 5) . 'code' . "\n"
                . $contentIndent($case) . '    - code marker' . "\n"
                . $case['next']
            );
            $list = $document->children[0] ?? new AstNode('missing');
            $first = $list->children[0] ?? new AstNode('missing');

            $assertListShape($t, $list, $case, $listItemText);
            $t->same(['code_block'], $childTypes($first));
            $t->same("code\n- code marker", $first->children[0]->attr('text'));
        };

    $tests["maps commonmark list padding surge {$label} six-space code padding"] =
        static function (TestRunner $t) use ($case, $contentIndent, $assertListShape, $listItemText, $childTypes): void {
            $document = (new MarkdownReader())->read(
                $case['marker'] . str_repeat(' ', 6) . 'code' . "\n"
                . $contentIndent($case) . 'paragraph' . "\n"
                . $case['next']
            );
            $list = $document->children[0] ?? new AstNode('missing');
            $first = $list->children[0] ?? new AstNode('missing');

            $assertListShape($t, $list, $case, $listItemText);
            $t->same(['code_block', 'text'], $childTypes($first));
            $t->same(' code', $first->children[0]->attr('text'));
            $t->same('paragraph', $first->children[1]->attr('text'));
        };

    $tests["maps commonmark list padding surge {$label} loose paragraph after code"] =
        static function (TestRunner $t) use ($case, $contentIndent, $assertListShape, $listItemText, $childTypes): void {
            $document = (new MarkdownReader())->read(
                $case['marker'] . str_repeat(' ', 5) . 'code' . "\n\n"
                . $contentIndent($case) . 'paragraph' . "\n"
                . $case['next']
            );
            $list = $document->children[0] ?? new AstNode('missing');
            $first = $list->children[0] ?? new AstNode('missing');

            $assertListShape($t, $list, $case, $listItemText);
            $t->same(true, (bool) $list->attr('loose'));
            $t->same(true, (bool) $first->attr('loose'));
            $t->same(['code_block', 'paragraph'], $childTypes($first));
            $t->same('code', $first->children[0]->attr('text'));
            $t->same('paragraph', $first->children[1]->attr('text'));
        };
}

$tests['records commonmark list padding surge mapped-case count'] = static function (TestRunner $t) use ($markerCases): void {
    $t->same(50, count($markerCases) * 5);
};

return $tests;
