<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzz-intraword-underscore-profile.md'
);

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

    'maps pandoc markdown intraword underscore profile fixture' =>
        static function (TestRunner $t) use ($fixture, $describeInlines): void {
            $document = (new MarkdownReader(['format' => 'markdown-intraword_underscores']))->read($fixture);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same([
                'text:foo',
                'emph(text:bar)',
                'text:baz and foo',
                'strong(text:bar)',
                'text:baz',
            ], $describeInlines($paragraph->children));
            $t->contains('Emph [ Str "bar" ]', $native);
            $t->contains('Strong [ Str "bar" ]', $native);
        },

    'records pandoc markdown intraword underscore profile mapped-case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(11, count($cases));
        },
];
