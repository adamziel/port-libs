<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$tableFixtures = [
    'pipe' => [
        'markdown' => implode("\n", [
            '| Item | Count |',
            '|:-----|------:|',
            '| Posts | 42 |',
        ]),
        'alignments' => ['left', 'right'],
        'header' => 'Item',
        'body' => '42',
    ],
    'simple' => [
        'markdown' => implode("\n", [
            'Item    Count',
            '------  -----',
            'Posts   42',
        ]),
        'alignments' => ['left', 'default'],
        'header' => 'Item',
        'body' => '42',
    ],
    'grid' => [
        'markdown' => implode("\n", [
            '+-------+-------+',
            '| Item  | Count |',
            '+=======+=======+',
            '| Posts | 42    |',
            '+-------+-------+',
        ]),
        'alignments' => ['default', 'default'],
        'header' => 'Item',
        'body' => '42',
    ],
];

$blockVariants = [
    'paragraph link' => [
        'lines' => static fn (string $id): array => ['Block **caption** ' . $id . ' with [link](/caption-' . $id . ' "Caption link")'],
        'type' => 'paragraph',
        'caption' => static fn (string $id): string => 'Block caption ' . $id . ' with link',
    ],
    'heading' => [
        'lines' => static fn (string $id): array => ['### Block caption ' . $id],
        'type' => 'heading',
        'caption' => static fn (string $id): string => 'Block caption ' . $id,
    ],
    'bullet list' => [
        'lines' => static fn (string $id): array => ['- Block **caption** ' . $id, '- Link [review](/review-' . $id . ')'],
        'type' => 'bullet_list',
        'caption' => static fn (string $id): string => 'Block caption ' . $id . ' Link review',
    ],
    'ordered list' => [
        'lines' => static fn (string $id): array => ['1. First `caption` ' . $id, '2. Second item'],
        'type' => 'ordered_list',
        'caption' => static fn (string $id): string => 'First caption ' . $id . ' Second item',
    ],
    'blockquote' => [
        'lines' => static fn (string $id): array => ['> Quoted *caption* ' . $id],
        'type' => 'blockquote',
        'caption' => static fn (string $id): string => 'Quoted caption ' . $id,
    ],
    'indented code' => [
        'lines' => static fn (string $id): array => ['    caption_' . $id . '();'],
        'type' => 'code_block',
        'caption' => static fn (string $id): string => 'caption_' . $id . '();',
    ],
    'html div' => [
        'lines' => static fn (string $id): array => ['<div data-caption="' . $id . '">HTML <em>caption</em> ' . $id . '</div>'],
        'type' => 'div',
        'caption' => static fn (string $id): string => 'HTML caption ' . $id,
    ],
    'line block' => [
        'lines' => static fn (string $id): array => ['| Line caption ' . $id, '| second line'],
        'type' => 'line_block',
        'caption' => static fn (string $id): string => 'Line caption ' . $id . ' second line',
    ],
    'fenced div' => [
        'lines' => static fn (string $id): array => ['::: caption-review', 'Div **caption** ' . $id, ':::'],
        'type' => 'div',
        'caption' => static fn (string $id): string => 'Div caption ' . $id,
    ],
];

$captionedMarkdown = static function (string $table, string $position, array $captionLines): string {
    $caption = "Table:\n" . implode("\n", array_map(static fn (string $line): string => '  ' . $line, $captionLines));

    return $position === 'before-table'
        ? $caption . "\n\n" . $table
        : $table . "\n\n" . $caption;
};

$firstTable = static function (AstNode $document): AstNode {
    foreach ($document->children as $node) {
        if ($node->type === 'table') {
            return $node;
        }
    }

    return new AstNode('missing');
};

$assertTableShape = static function (TestRunner $t, AstNode $table, array $fixture): void {
    $t->same('table', $table->type);
    $t->same($fixture['alignments'], $table->attr('alignments'));
    $t->same($fixture['header'], $table->children[0]->children[0]->children[0]->attr('text'));
    $t->same($fixture['body'], $table->children[1]->children[0]->children[1]->attr('text'));
};

$tests = [];
$cases = [];
$caseNumber = 1;
foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table', 'after-table'] as $position) {
        foreach ($blockVariants as $variantName => $variant) {
            $caseId = str_pad((string) $caseNumber, 3, '0', STR_PAD_LEFT);
            $cases[] = [
                'id' => $caseId,
                'name' => sprintf(
                    'maps upstream markdown reader table figure caption completion block caption %s %s %s',
                    $tableName,
                    $position,
                    $variantName
                ),
                'fixture' => $fixture,
                'tableName' => $tableName,
                'position' => $position,
                'lines' => $variant['lines']($caseId),
                'blockType' => $variant['type'],
                'caption' => $variant['caption']($caseId),
            ];
            $caseNumber++;
        }
    }
}

foreach ($cases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use ($case, $captionedMarkdown, $firstTable, $assertTableShape): void {
        $document = (new MarkdownReader())->read($captionedMarkdown($case['fixture']['markdown'], $case['position'], $case['lines']));
        $table = $firstTable($document);
        $captionBlocks = $table->attr('captionBlocks', []);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write($document);

        $assertTableShape($t, $table, $case['fixture']);
        $t->same($case['caption'], $table->attr('caption'), $case['id'] . ' caption plain text');
        $t->same(true, is_array($captionBlocks) && count($captionBlocks) === 1, $case['id'] . ' caption block recorded');
        $t->same($case['blockType'], $captionBlocks[0]->type ?? null, $case['id'] . ' caption block type');
        $t->same('captionBlocks', $packet['captions']['long']['source'] ?? null, $case['id'] . ' table geometry caption source');
        $t->same(1, $packet['captions']['long']['blockCount'] ?? null, $case['id'] . ' table geometry block count');
        $t->same([$case['blockType']], $packet['summary']['captionBlockTypes'] ?? null, $case['id'] . ' table geometry block summary');
        $t->same($case['position'], $table->attr('captionSource')['position'] ?? null, $case['id'] . ' caption source position');
        $t->same($case['position'] === 'before-table' ? 'top' : 'bottom', $table->attr('captionSource')['captionSide'] ?? null, $case['id'] . ' caption side');
        $t->contains('<figcaption', $blocks);
        $t->contains(explode(' ', $case['caption'])[0], $blocks);
    };
}

$tests['records upstream markdown reader table figure caption completion mapped-case count'] = static function (TestRunner $t) use ($cases): void {
    $t->same(54, count($cases));
};

return $tests;
