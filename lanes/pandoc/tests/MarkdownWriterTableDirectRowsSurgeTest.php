<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (AstNode $node): string => (new MarkdownWriter())->write($document([$node]));

$cell = static function (array|string $children, array $attrs = []) use ($text): AstNode {
    if (is_string($children)) {
        return new AstNode('table_cell', array_merge(['text' => $children], $attrs), [$text($children)]);
    }

    return new AstNode('table_cell', $attrs, $children);
};

$withCellAttrs = static fn (AstNode $node, array $attrs): AstNode => new AstNode(
    $node->type,
    array_merge($node->attrs, $attrs),
    $node->children
);

$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);
$head = static fn (array $rows): AstNode => new AstNode('table_head', [], $rows);
$body = static fn (array $rows): AstNode => new AstNode('table_body', [], $rows);
$foot = static fn (array $rows): AstNode => new AstNode('table_foot', [], $rows);
$htmlTable = static function (array $children, array $attrs = []): AstNode {
    return new AstNode('table', array_merge(['markdownTableFormat' => 'html'], $attrs), $children);
};

$alignedAttrs = ['alignments' => ['left', 'right']];
$labelCell = static fn (string $slug) => 'Label ' . $slug;

$cellPayloads = [
    'escaped text payload' => [
        'cell' => static fn (string $slug): AstNode => $cell('A < B & ' . $slug),
        'expected' => static fn (string $slug): string => 'A &lt; B &amp; ' . $slug,
    ],
    'strong inline payload' => [
        'cell' => static fn (string $slug): AstNode => $cell([$text('Strong '), new AstNode('strong', [], [$text($slug)])]),
        'expected' => static fn (string $slug): string => 'Strong <strong>' . $slug . '</strong>',
    ],
    'attributed span payload' => [
        'cell' => static fn (string $slug): AstNode => $cell([
            new AstNode('span', ['classes' => ['review'], 'attributes' => ['data-id' => $slug]], [$text('Span')]),
        ]),
        'expected' => static fn (string $slug): string => '<span class="review" data-id="' . $slug . '">Span</span>',
    ],
    'link payload' => [
        'cell' => static fn (string $slug): AstNode => $cell([
            new AstNode('link', ['url' => 'https://example.test/' . $slug . '?x=1&y=2'], [$text('Link')]),
        ]),
        'expected' => static fn (string $slug): string => '<a href="https://example.test/' . $slug . '?x=1&amp;y=2">Link</a>',
    ],
    'code inline payload' => [
        'cell' => static fn (string $slug): AstNode => $cell([
            new AstNode('code', ['text' => 'code <' . $slug . '> & value']),
        ]),
        'expected' => static fn (string $slug): string => '<code>code &lt;' . $slug . '&gt; &amp; value</code>',
    ],
    'paragraph block payload' => [
        'cell' => static fn (string $slug): AstNode => $cell([$paragraph([$text('Paragraph ' . $slug)])]),
        'expected' => static fn (string $slug): string => '<p>Paragraph ' . $slug . '</p>',
    ],
    'list block payload' => [
        'cell' => static fn (string $slug): AstNode => $cell([
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [$paragraph([$text('Item ' . $slug)])]),
            ]),
        ]),
        'expected' => static fn (string $slug): string => '<ul><li><p>Item ' . $slug . '</p></li></ul>',
    ],
    'raw html block payload' => [
        'cell' => static fn (string $slug): AstNode => $cell([
            new AstNode('raw_html', ['html' => '<aside data-id="' . $slug . '">Raw</aside>']),
        ]),
        'expected' => static fn (string $slug): string => '<aside data-id="' . $slug . '">Raw</aside>',
    ],
];

$rowFragment = static function (string $label, string $content, bool $aligned = true): string {
    if (!$aligned) {
        return '<tr><td>' . $label . '</td><td>' . $content . '</td></tr>';
    }

    return '<tr><td style="text-align:left">' . $label . '</td><td style="text-align:right">' . $content . '</td></tr>';
};

$directRowFamilies = [
    'without declared columns' => [
        'table' => static fn (AstNode $valueCell, string $slug): AstNode => $htmlTable([
            $row([$cell($labelCell($slug)), $valueCell]),
        ]),
        'expected' => static fn (string $slug, string $content): string => $rowFragment($labelCell($slug), $content, false),
    ],
    'with alignment metadata' => [
        'table' => static fn (AstNode $valueCell, string $slug): AstNode => $htmlTable([
            $row([$cell($labelCell($slug)), $valueCell]),
        ], $alignedAttrs),
        'expected' => static fn (string $slug, string $content): string => $rowFragment($labelCell($slug), $content),
    ],
    'after explicit head section' => [
        'table' => static fn (AstNode $valueCell, string $slug): AstNode => $htmlTable([
            $head([$row([$cell('Metric'), $cell('Value')])]),
            $row([$cell($labelCell($slug)), $valueCell]),
        ], $alignedAttrs),
        'expected' => static fn (string $slug, string $content): string => $rowFragment($labelCell($slug), $content),
    ],
    'after explicit body section' => [
        'table' => static fn (AstNode $valueCell, string $slug): AstNode => $htmlTable([
            $body([$row([$cell('Existing'), $cell('0')])]),
            $row([$cell($labelCell($slug)), $valueCell]),
        ], $alignedAttrs),
        'expected' => static fn (string $slug, string $content): string => $rowFragment($labelCell($slug), $content),
    ],
    'before explicit foot section' => [
        'table' => static fn (AstNode $valueCell, string $slug): AstNode => $htmlTable([
            $row([$cell($labelCell($slug)), $valueCell]),
            $foot([$row([$cell('Total'), $cell('1')])]),
        ], $alignedAttrs),
        'expected' => static fn (string $slug, string $content): string => $rowFragment($labelCell($slug), $content),
    ],
    'with caption context' => [
        'table' => static fn (AstNode $valueCell, string $slug): AstNode => $htmlTable([
            $row([$cell($labelCell($slug)), $valueCell]),
        ], array_merge($alignedAttrs, ['caption' => 'Direct caption ' . $slug])),
        'expected' => static fn (string $slug, string $content): string => '<caption>Direct caption ' . $slug . '</caption>' . "\n"
            . '  <tbody>' . "\n"
            . '    ' . $rowFragment($labelCell($slug), $content),
    ],
    'with source row attributes' => [
        'table' => static fn (AstNode $valueCell, string $slug): AstNode => $htmlTable([
            $row(
                [$cell($labelCell($slug)), $valueCell],
                ['id' => 'row-' . $slug, 'classes' => ['surge-row'], 'attributes' => ['data-kind' => 'direct']]
            ),
        ], $alignedAttrs),
        'expected' => static fn (string $slug, string $content): string => '<tr id="row-' . $slug . '" class="surge-row" data-kind="direct">'
            . '<td style="text-align:left">' . $labelCell($slug) . '</td>'
            . '<td style="text-align:right">' . $content . '</td></tr>',
    ],
    'with source cell attributes' => [
        'table' => static fn (AstNode $valueCell, string $slug): AstNode => $htmlTable([
            $row([$cell($labelCell($slug)), $withCellAttrs($valueCell, [
                'id' => 'value-' . $slug,
                'classes' => ['surge-value'],
                'attributes' => ['data-kind' => 'value'],
            ])]),
        ], $alignedAttrs),
        'expected' => static fn (string $slug, string $content): string => '<tr><td style="text-align:left">' . $labelCell($slug) . '</td>'
            . '<td id="value-' . $slug . '" class="surge-value" data-kind="value" style="text-align:right">'
            . $content
            . '</td></tr>',
    ],
];

$tests = [];
$familyIndex = 0;
foreach ($directRowFamilies as $familyLabel => $family) {
    $payloadIndex = 0;
    foreach ($cellPayloads as $payloadLabel => $payload) {
        $slug = 'dr' . $familyIndex . '-' . $payloadIndex;
        $tests["maps upstream markdown writer html direct table row {$familyLabel} {$payloadLabel}"] =
            static function (TestRunner $t) use ($family, $payload, $slug, $writeDocument): void {
                $markdown = $writeDocument($family['table']($payload['cell']($slug), $slug));

                $t->contains($family['expected']($slug, $payload['expected']($slug)), $markdown);
            };
        $payloadIndex++;
    }
    $familyIndex++;
}

return $tests;
