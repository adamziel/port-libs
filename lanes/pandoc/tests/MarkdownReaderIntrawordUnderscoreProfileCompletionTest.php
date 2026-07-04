<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

/**
 * @return list<string>
 */
$describeInlines = static function (array $nodes) use (&$describeInlines): array {
    $described = [];
    foreach ($nodes as $node) {
        if ($node->type === 'text') {
            $described[] = 'text:' . $node->attr('text', '');
            continue;
        }

        if ($node->type === 'emph' || $node->type === 'strong') {
            $described[] = $node->type . '(' . implode('|', $describeInlines($node->children)) . ')';
            continue;
        }

        $described[] = $node->type;
    }

    return $described;
};

$cases = [
    'markdown default keeps intraword underscore literal' => [
        'options' => ['format' => 'markdown'],
        'source' => 'foo_bar_baz',
        'expected' => ['text:foo_bar_baz'],
    ],
    'markdown plus intraword underscores keeps literal' => [
        'options' => ['format' => 'markdown+intraword_underscores'],
        'source' => 'foo_bar_baz',
        'expected' => ['text:foo_bar_baz'],
    ],
    'markdown minus intraword underscores opens emphasis' => [
        'options' => ['format' => 'markdown-intraword_underscores'],
        'source' => 'foo_bar_baz',
        'expected' => ['text:foo', 'emph(text:bar)', 'text:baz'],
    ],
    'markdown minus intraword underscores opens strong' => [
        'options' => ['format' => 'markdown-intraword_underscores'],
        'source' => 'foo__bar__baz',
        'expected' => ['text:foo', 'strong(text:bar)', 'text:baz'],
    ],
    'markdown strict default parses intraword emphasis' => [
        'options' => ['format' => 'markdown_strict'],
        'source' => 'foo_bar_baz',
        'expected' => ['text:foo', 'emph(text:bar)', 'text:baz'],
    ],
    'markdown strict plus intraword underscores keeps literal' => [
        'options' => ['format' => 'markdown_strict+intraword_underscores'],
        'source' => 'foo_bar_baz',
        'expected' => ['text:foo_bar_baz'],
    ],
    'markdown strict plus intraword underscores keeps strong literal' => [
        'options' => ['format' => 'markdown_strict+intraword_underscores'],
        'source' => 'foo__bar__baz',
        'expected' => ['text:foo__bar__baz'],
    ],
    'markdown mmd minus intraword underscores opens emphasis' => [
        'options' => ['format' => 'markdown_mmd-intraword_underscores'],
        'source' => 'foo_bar_baz',
        'expected' => ['text:foo', 'emph(text:bar)', 'text:baz'],
    ],
    'markdown php extra minus intraword underscores opens emphasis' => [
        'options' => ['format' => 'markdown_phpextra-intraword_underscores'],
        'source' => 'foo_bar_baz',
        'expected' => ['text:foo', 'emph(text:bar)', 'text:baz'],
    ],
    'extension option map disables intraword underscore guard' => [
        'options' => ['format' => 'markdown', 'extensions' => ['intraword_underscores' => false]],
        'source' => 'foo_bar_baz',
        'expected' => ['text:foo', 'emph(text:bar)', 'text:baz'],
    ],
    'extension option list enables strict intraword underscore guard' => [
        'options' => ['format' => 'markdown_strict', 'extensions' => ['+intraword_underscores']],
        'source' => 'foo_bar_baz',
        'expected' => ['text:foo_bar_baz'],
    ],
];

return [
    'maps pandoc markdown intraword underscore extension profiles' =>
        static function (TestRunner $t) use ($cases, $describeInlines): void {
            foreach ($cases as $label => $case) {
                $document = (new MarkdownReader($case['options']))->read($case['source']);
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, $label);
                $t->same($case['expected'], $describeInlines($paragraph->children), $label);
            }
        },

    'records pandoc markdown intraword underscore profile mapped-case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(11, count($cases));
        },
];
