<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$emph = static fn (string $value): AstNode => new AstNode('emph', [], [$text($value)]);
$strong = static fn (string $value): AstNode => new AstNode('strong', [], [$text($value)]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$rawHtml = static fn (string $html): AstNode => new AstNode('raw_html', ['html' => $html]);
$rawBlock = static fn (array $attrs): AstNode => new AstNode('raw_block', $attrs);

$fixture = static fn (): string => rtrim(
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-command-details-summary.md'),
    "\r\n"
);

$detailsSummaryBlocks = static function (callable $raw) use ($emph, $paragraph, $strong, $text): array {
    return [
        $raw('<details class="migration-review" data-source="classic">'),
        $raw('<summary>Show imported source notes</summary>'),
        $paragraph([
            $text('details para 1 with '),
            $emph('emphasis'),
            $text('.'),
        ]),
        $paragraph([
            $text('details para 2 with '),
            $strong('strong'),
            $text(' context.'),
        ]),
        $raw('</details>'),
    ];
};

$fixtureCases = [
    'markdown raw html blocks match upstream details summary fixture' => [
        'document' => $document($detailsSummaryBlocks($rawHtml)),
        'options' => ['format' => 'markdown'],
    ],
    'gfm raw html blocks match upstream details summary fixture' => [
        'document' => $document($detailsSummaryBlocks($rawHtml)),
        'options' => ['format' => 'gfm'],
    ],
    'commonmark raw_block format_name html aliases match details summary fixture' => [
        'document' => $document($detailsSummaryBlocks(
            static fn (string $html): AstNode => $rawBlock(['format_name' => 'html', 'raw' => $html])
        )),
        'options' => ['format' => 'commonmark'],
    ],
    'markdown raw_block raw_format html5 aliases match details summary fixture' => [
        'document' => $document($detailsSummaryBlocks(
            static fn (string $html): AstNode => $rawBlock(['raw_format' => 'html5', 'value' => $html])
        )),
        'options' => ['format' => 'markdown'],
    ],
];

$tests = [];

foreach ($fixtureCases as $label => $case) {
    $tests['maps upstream markdown writer details summary fixture completion ' . $label] =
        static function (TestRunner $t) use ($case, $fixture): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($fixture(), $markdown);
        };
}

$tests['keeps non-summary raw html before paragraph on normal block boundary'] =
    static function (TestRunner $t) use ($document, $paragraph, $rawHtml, $text): void {
        $markdown = (new MarkdownWriter(['format' => 'markdown']))->write($document([
            $rawHtml('<aside>Review note</aside>'),
            $paragraph([$text('After raw aside.')]),
        ]));

        $t->same("<aside>Review note</aside>\n\nAfter raw aside.", $markdown);
    };

$tests['records markdown writer details summary fixture completion mapped case count'] =
    static function (TestRunner $t) use ($fixtureCases): void {
        $t->same(4, count($fixtureCases));
    };

return $tests;
