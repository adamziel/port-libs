<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$cell = static function (array|string $children, array $attrs = []) use ($text): AstNode {
    if (is_string($children)) {
        return new AstNode('table_cell', array_merge(['text' => $children], $attrs), [$text($children)]);
    }

    return new AstNode('table_cell', $attrs, $children);
};
$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
$head = static fn (array $rows): AstNode => new AstNode('table_head', [], $rows);
$body = static fn (array $rows): AstNode => new AstNode('table_body', [], $rows);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (AstNode $node): string => (new MarkdownWriter(['htmlTableAutoFallback' => true]))->write($document([$node]));
$table = static fn (array $sections, array $attrs = []): AstNode => new AstNode('table', $attrs + ['alignments' => ['left', 'right']], $sections);
$twoColumnTable = static fn (AstNode $valueCell, array $attrs = []): AstNode => $table([
    $head([$row([$cell('Metric'), $cell('Value')])]),
    $body([$row([$cell('Probe'), $valueCell])]),
], $attrs);

$tableAttributeCase = static fn (string $name, string $value, string $expected): array => [
    'table' => static fn () => $twoColumnTable($cell('Ready'), ['htmlAttributes' => [$name => $value]]),
    'contains' => [$expected],
];
$cellAttributeCase = static fn (string $name, string $value, string $expected): array => [
    'table' => static fn () => $twoColumnTable($cell('Ready', ['attributes' => [$name => $value]])),
    'contains' => [$expected],
];
$captionAttributeCase = static fn (string $name, string $value, string $expected): array => [
    'table' => static fn () => $twoColumnTable($cell('Ready'), [
        'caption' => 'Caption',
        'captionSource' => ['sourceAttributes' => ['attributes' => [$name => $value]]],
    ]),
    'contains' => [$expected, '<caption'],
];
$spanAttributeCase = static fn (string $name, string $value, string $expected): array => [
    'table' => static fn () => $twoColumnTable($cell([
        new AstNode('span', ['htmlAttributes' => [$name => $value]], [$text('Span')]),
    ])),
    'contains' => [$expected],
];
$linkAttributeCase = static fn (string $name, string $value, string $expected): array => [
    'table' => static fn () => $twoColumnTable($cell([
        new AstNode('link', ['url' => 'https://example.test', 'htmlAttributes' => [$name => $value]], [$text('Example')]),
    ])),
    'contains' => [$expected],
];
$imageAttributeCase = static fn (string $name, string $value, string $expected): array => [
    'table' => static fn () => $twoColumnTable($cell([
        new AstNode('image', ['url' => 'media/a.png', 'alt' => 'Alt', 'htmlAttributes' => [$name => $value]]),
    ])),
    'contains' => [$expected],
];

$attributeCases = [
    'table accesskey attribute' => $tableAttributeCase('accesskey', 'r', 'accesskey="r"'),
    'table contenteditable attribute' => $tableAttributeCase('contenteditable', 'true', 'contenteditable="true"'),
    'table draggable attribute' => $tableAttributeCase('draggable', 'false', 'draggable="false"'),
    'table hidden attribute' => $tableAttributeCase('hidden', 'hidden', 'hidden="hidden"'),
    'table itemprop attribute' => $tableAttributeCase('itemprop', 'reviewTable', 'itemprop="reviewTable"'),
    'table itemscope attribute' => $tableAttributeCase('itemscope', 'itemscope', 'itemscope="itemscope"'),
    'table itemtype attribute' => $tableAttributeCase('itemtype', 'https://schema.org/Table', 'itemtype="https://schema.org/Table"'),
    'table itemid attribute' => $tableAttributeCase('itemid', '#table-1', 'itemid="#table-1"'),
    'table itemref attribute' => $tableAttributeCase('itemref', 'summary details', 'itemref="summary details"'),
    'table popover attribute' => $tableAttributeCase('popover', 'manual', 'popover="manual"'),
    'table slot attribute' => $tableAttributeCase('slot', 'review', 'slot="review"'),
    'table spellcheck attribute' => $tableAttributeCase('spellcheck', 'false', 'spellcheck="false"'),
    'table tabindex attribute' => $tableAttributeCase('tabindex', '0', 'tabindex="0"'),
    'table translate attribute' => $tableAttributeCase('translate', 'no', 'translate="no"'),
    'table border attribute' => $tableAttributeCase('border', '1', 'border="1"'),
    'table cellpadding attribute' => $tableAttributeCase('cellpadding', '4', 'cellpadding="4"'),
    'table cellspacing attribute' => $tableAttributeCase('cellspacing', '0', 'cellspacing="0"'),
    'table frame attribute' => $tableAttributeCase('frame', 'hsides', 'frame="hsides"'),
    'table rules attribute' => $tableAttributeCase('rules', 'groups', 'rules="groups"'),
    'cell axis attribute' => $cellAttributeCase('axis', 'category', 'axis="category"'),
    'cell char attribute' => $cellAttributeCase('char', '.', 'char="."'),
    'cell charoff attribute' => $cellAttributeCase('charoff', '2', 'charoff="2"'),
    'cell name attribute' => $cellAttributeCase('name', 'value-cell', 'name="value-cell"'),
    'cell value attribute' => $cellAttributeCase('value', '42', 'value="42"'),
    'cell type attribute' => $cellAttributeCase('type', 'number', 'type="number"'),
    'cell hidden attribute' => $cellAttributeCase('hidden', 'hidden', 'hidden="hidden"'),
    'cell tabindex attribute' => $cellAttributeCase('tabindex', '1', 'tabindex="1"'),
    'caption accesskey attribute' => $captionAttributeCase('accesskey', 'c', 'accesskey="c"'),
    'caption itemprop attribute' => $captionAttributeCase('itemprop', 'caption', 'itemprop="caption"'),
    'caption translate attribute' => $captionAttributeCase('translate', 'no', 'translate="no"'),
    'caption hidden attribute' => $captionAttributeCase('hidden', 'hidden', 'hidden="hidden"'),
    'span accesskey attribute' => $spanAttributeCase('accesskey', 's', '<span accesskey="s">Span</span>'),
    'span contenteditable attribute' => $spanAttributeCase('contenteditable', 'true', '<span contenteditable="true">Span</span>'),
    'span draggable attribute' => $spanAttributeCase('draggable', 'true', '<span draggable="true">Span</span>'),
    'span hidden attribute' => $spanAttributeCase('hidden', 'hidden', '<span hidden="hidden">Span</span>'),
    'span itemprop attribute' => $spanAttributeCase('itemprop', 'label', '<span itemprop="label">Span</span>'),
    'span slot attribute' => $spanAttributeCase('slot', 'inline', '<span slot="inline">Span</span>'),
    'span spellcheck attribute' => $spanAttributeCase('spellcheck', 'false', '<span spellcheck="false">Span</span>'),
    'span tabindex attribute' => $spanAttributeCase('tabindex', '2', '<span tabindex="2">Span</span>'),
    'span translate attribute' => $spanAttributeCase('translate', 'no', '<span translate="no">Span</span>'),
    'link download attribute' => $linkAttributeCase('download', 'report.md', 'download="report.md"'),
    'link hreflang attribute' => $linkAttributeCase('hreflang', 'en', 'hreflang="en"'),
    'link referrerpolicy attribute' => $linkAttributeCase('referrerpolicy', 'no-referrer', 'referrerpolicy="no-referrer"'),
    'link target attribute' => $linkAttributeCase('target', '_blank', 'target="_blank"'),
    'link type attribute' => $linkAttributeCase('type', 'text/html', 'type="text/html"'),
    'image crossorigin attribute' => $imageAttributeCase('crossorigin', 'anonymous', 'crossorigin="anonymous"'),
    'image decoding attribute' => $imageAttributeCase('decoding', 'async', 'decoding="async"'),
    'image fetchpriority attribute' => $imageAttributeCase('fetchpriority', 'high', 'fetchpriority="high"'),
    'image srcset attribute' => $imageAttributeCase('srcset', 'media/a.png 1x, media/a@2x.png 2x', 'srcset="media/a.png 1x, media/a@2x.png 2x"'),
    'image usemap attribute' => $imageAttributeCase('usemap', '#map', 'usemap="#map"'),
];

$tests = [];
foreach ($attributeCases as $label => $case) {
    $tests["maps upstream markdown writer html table safe attribute {$label}"] =
        static function (TestRunner $t) use ($case, $writeDocument): void {
            $markdown = $writeDocument($case['table']());

            $t->contains('<table', $markdown);
            foreach ($case['contains'] as $expected) {
                $t->contains($expected, $markdown);
            }
        };
}

return $tests;
