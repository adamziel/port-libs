<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

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

    return trim($text);
};

$tagProfiles = [
    ['tag' => 'div', 'format' => 'commonmark'],
    ['tag' => 'section', 'format' => 'gfm'],
    ['tag' => 'table', 'format' => 'markdown'],
    ['tag' => 'script', 'format' => 'commonmark_x'],
    ['tag' => 'pre', 'format' => 'markdown_github'],
    ['tag' => 'style', 'format' => 'markdown_phpextra'],
    ['tag' => 'textarea', 'format' => 'pandoc'],
    ['tag' => 'svg', 'format' => 'markdown_mmd'],
    ['tag' => 'math', 'format' => 'markdown_strict'],
    ['tag' => 'x-review', 'format' => 'commonmark'],
];

$invalidAttributes = [
    'equals-prefixed attribute token' => '=bad',
    'double-quoted bare token' => '"bad"',
    'single-quoted bare token' => "'bad'",
    'digit-prefixed attribute name' => '9bad',
    'hyphen-prefixed attribute name' => '-bad',
    'missing unquoted attribute value' => 'data=',
    'backtick-delimited attribute value' => 'data=`bad`',
    'unquoted value with equals' => 'data=bad=again',
];

$cases = [];
foreach ($tagProfiles as $tagProfile) {
    foreach ($invalidAttributes as $attributeName => $attributeSource) {
        $caseId = str_pad((string) (count($cases) + 1), 3, '0', STR_PAD_LEFT);
        $line = '<' . $tagProfile['tag'] . ' ' . $attributeSource . '>';
        $cases[$caseId . ' ' . $tagProfile['tag'] . ' rejects ' . $attributeName] = [
            'format' => $tagProfile['format'],
            'line' => $line,
            'markdown' => $line . "\nliteral continuation\n\nAfter",
        ];
    }
}

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream commonmark raw html block fourth wave malformed tag boundary ' . $name] =
        static function (TestRunner $t) use ($case, $plainText): void {
            $document = (new MarkdownReader(['format' => $case['format']]))->read($case['markdown']);
            $first = $document->children[0] ?? new AstNode('missing');
            $after = $document->children[1] ?? new AstNode('missing');
            $types = array_map(static fn (AstNode $node): string => $node->type, $document->children);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('paragraph', $first->type, $case['line']);
            $t->same('paragraph', $after->type, $case['line']);
            $t->same(false, in_array('raw_html', $types, true), $case['line']);
            $t->same('After', $plainText($after), $case['line']);
            $t->same(false, str_contains($blocks, '<!-- wp:html -->'), $case['line']);
        };
}

$tests['records upstream commonmark raw html block fourth wave mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(80, count($cases));
    };

return $tests;
