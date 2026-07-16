<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

/**
 * @return list<string>
 */
$rawHtmlInlines = static function (AstNode $paragraph): array {
    $raw = [];
    foreach ($paragraph->children as $child) {
        if ($child->type === 'raw_html_inline') {
            $raw[] = (string) $child->attr('html', '');
        }
    }

    return $raw;
};

$customTags = [
    'x-review',
    'x-import',
    'x-source-map',
    'x-media-card',
    'x-caption-link',
    'x-audit-note',
    'x-footnote-ref',
    'x-runbook-step',
    'x-legacy-widget',
    'x-custom-shortcode',
    'x-math-inline',
    'x-svg-fragment',
    'x-html-note',
    'x-wordpress-block',
    'x-pullquote',
    'x-reviewer-chip',
    'x-manifest-entry',
    'x-resource-link',
    'x-callout-title',
    'x-table-cell',
    'x-nav-breadcrumb',
    'x-archive-ref',
    'x-bibliography-key',
    'x-glossary-term',
    'x-annotation-span',
];

$cases = [];
foreach ($customTags as $index => $tag) {
    $caseId = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
    $label = 'packet ' . $caseId;

    $opening = '<' . $tag . ' data-case="' . $caseId . '" data-source=\'a > b\'>';
    $closing = '</' . $tag . '>';
    $cases[$caseId . ' paired ' . $tag] = [
        'source' => $opening . $label . $closing,
        'raw' => [$opening, $closing],
    ];

    $selfClosing = '<' . $tag . ' data-case=' . $caseId . ' review-flag />';
    $cases[$caseId . ' self closing ' . $tag] = [
        'source' => $selfClosing,
        'raw' => [$selfClosing],
    ];
}

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream commonmark inline generic html tag ' . $name] =
        static function (TestRunner $t) use ($case, $rawHtmlInlines): void {
            $document = (new MarkdownReader())->read('Lead ' . $case['source'] . ' trail');
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('paragraph', $paragraph->type);
            $t->same($case['raw'], $rawHtmlInlines($paragraph));

            foreach ($case['raw'] as $rawHtml) {
                $t->contains($rawHtml, $blocks);
            }
        };
}

$tests['keeps invalid inline generic html tag syntax as text'] =
    static function (TestRunner $t) use ($rawHtmlInlines): void {
        $document = (new MarkdownReader())->read('Lead <1-review data-case="bad"> and <x-review data=`bad`> stay literal.');
        $paragraph = $document->children[0] ?? new AstNode('missing');

        $t->same('paragraph', $paragraph->type);
        $t->same([], $rawHtmlInlines($paragraph));
        $t->same('Lead <1-review data-case=bad> and <x-review data=bad> stay literal.', $paragraph->attr('text'));
    };

$tests['records upstream commonmark inline generic html surge mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(50, count($cases));
    };

return $tests;
