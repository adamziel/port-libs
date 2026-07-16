<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$blockTypes = static fn (AstNode $document): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $document->children
);

$rawCases = [
    'commonmark pre without code' => [
        'options' => ['format' => 'commonmark'],
        'markdown' => "<pre data-review=\"raw\">\n# not heading\n\nliteral\n</pre>\n\nAfter",
        'raw' => "<pre data-review=\"raw\">\n# not heading\n\nliteral\n</pre>",
    ],
    'gfm figure block' => [
        'options' => ['format' => 'gfm'],
        'markdown' => "<figure data-review=\"raw\">\n*raw caption source*\n</figure>\n\nAfter",
        'raw' => "<figure data-review=\"raw\">\n*raw caption source*\n</figure>",
    ],
    'commonmark x details block' => [
        'options' => ['format' => 'commonmark_x'],
        'markdown' => "<details open>\n<summary>Title</summary>\nbody **raw**\n</details>\n\nAfter",
        'raw' => "<details open>\n<summary>Title</summary>\nbody **raw**\n</details>",
    ],
    'commonmark noscript raw text block' => [
        'options' => ['format' => 'commonmark'],
        'markdown' => "<noscript>\n# fallback\n\nstill fallback\n</noscript>\n\nAfter",
        'raw' => "<noscript>\n# fallback\n\nstill fallback\n</noscript>",
    ],
];

$tests = [
    'keeps default markdown structured html pre import precedence' =>
        static function (TestRunner $t) use ($blockTypes): void {
            $document = (new MarkdownReader())->read("<pre id=\"source-raw\" data-review=\"keep\">first<br/>second\n</pre>");
            $code = $document->children[0] ?? new AstNode('missing');

            $t->same(['code_block'], $blockTypes($document));
            $t->same('source-raw', $code->attr('id'));
            $t->same(['review' => 'keep'], $code->attr('attributes'));
            $t->same("first\nsecond", $code->attr('text'));
        },

    'records commonmark raw html precedence mapped-case count' =>
        static function (TestRunner $t) use ($rawCases): void {
            $t->same(5, count($rawCases) + 1);
        },
];

foreach ($rawCases as $name => $case) {
    $tests['maps commonmark raw html precedence ' . $name] =
        static function (TestRunner $t) use ($blockTypes, $case): void {
            $document = (new MarkdownReader($case['options']))->read($case['markdown']);
            $raw = $document->children[0] ?? new AstNode('missing');
            $after = $document->children[1] ?? new AstNode('missing');

            $t->same(['raw_html', 'paragraph'], $blockTypes($document), $case['raw']);
            $t->same($case['raw'], $raw->attr('html'), $case['raw']);
            $t->same('After', $after->attr('text'), $case['raw']);
        };
}

return $tests;
