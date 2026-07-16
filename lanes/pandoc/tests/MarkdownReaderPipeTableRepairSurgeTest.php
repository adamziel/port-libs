<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$cell = static fn (string $source, ?string $text = null): array => [
    'source' => $source,
    'text' => $text ?? $source,
];

$firstTable = null;
$firstTable = static function (AstNode $node) use (&$firstTable): AstNode {
    if ($node->type === 'table') {
        return $node;
    }

    foreach ($node->children as $child) {
        $table = $firstTable($child);
        if ($table->type === 'table') {
            return $table;
        }
    }

    return new AstNode('missing');
};

$inlinePlainText = null;
$inlinePlainText = static function (array $nodes) use (&$inlinePlainText): string {
    $text = '';
    foreach ($nodes as $node) {
        if (!$node instanceof AstNode) {
            continue;
        }

        if ($node->type === 'text' || $node->type === 'code') {
            $text .= (string) $node->attr('text');
            continue;
        }

        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            $text .= "\n";
            continue;
        }

        $text .= $inlinePlainText($node->children);
    }

    return $text;
};

$tableCellText = static function (AstNode $table, string $section, int $row, int $column) use ($inlinePlainText): string {
    $sectionNode = $section === 'head' ? $table->children[0] : $table->children[1];
    $rowNode = $sectionNode->children[$row] ?? new AstNode('missing');
    $cellNode = $rowNode->children[$column] ?? new AstNode('missing');

    return $inlinePlainText($cellNode->children);
};

$pipeLine = static function (array $cells, bool $sides): string {
    $sourceCells = array_map(static fn (array $cell): string => $cell['source'], $cells);
    $line = implode(' | ', $sourceCells);

    return $sides ? '| ' . $line . ' |' : $line;
};

$delimiterLine = static function (bool $sides): string {
    $line = ':----- | -----: | :-----:';

    return $sides ? '| ' . $line . ' |' : $line;
};

$captionedTable = static function (string $table, string $position, string $caption): string {
    return $position === 'before-table'
        ? $caption . "\n\n" . $table
        : $table . "\n\n" . $caption;
};

$fixtures = [
    'alpha' => [
        'headers' => [$cell('Alpha'), $cell('Beta'), $cell('Gamma'), $cell('Alpha overflow')],
        'body' => [$cell('One'), $cell('Two'), $cell('Three'), $cell('One overflow')],
    ],
    'bravo escaped' => [
        'headers' => [$cell('A \| B', 'A | B'), $cell('Metric'), $cell('State'), $cell('Ignored \| H', 'Ignored | H')],
        'body' => [$cell('literal \| pipe', 'literal | pipe'), $cell('42'), $cell('ready'), $cell('drop \| body', 'drop | body')],
    ],
    'charlie code' => [
        'headers' => [$cell('Code'), $cell('Value'), $cell('Notes'), $cell('Extra code')],
        'body' => [$cell('`left|right`', 'left|right'), $cell('7'), $cell('kept'), $cell('`drop|body`', 'drop|body')],
    ],
    'delta spans' => [
        'headers' => [$cell('Delta **strong**', 'Delta strong'), $cell('Center'), $cell('Tail'), $cell('Drop strong')],
        'body' => [$cell('body *emph*', 'body emph'), $cell('middle'), $cell('tail'), $cell('drop')],
    ],
    'echo links' => [
        'headers' => [$cell('Docs'), $cell('[Queue](/queue)', 'Queue'), $cell('Status'), $cell('Drop link')],
        'body' => [$cell('review'), $cell('[Open](/open)', 'Open'), $cell('done'), $cell('unused')],
    ],
    'foxtrot entities' => [
        'headers' => [$cell('A &amp; B', 'A & B'), $cell('Count'), $cell('Owner'), $cell('Ignored')],
        'body' => [$cell('Tom &amp; Jerry', 'Tom & Jerry'), $cell('5'), $cell('editor'), $cell('drop')],
    ],
    'golf padded' => [
        'headers' => [$cell('Golf'), $cell('Handicap'), $cell('Course'), $cell('Spare')],
        'body' => [$cell('links'), $cell('12'), $cell('north'), $cell('spare')],
    ],
    'hotel numbers' => [
        'headers' => [$cell('Hotel'), $cell('Rooms'), $cell('Open'), $cell('Extra')],
        'body' => [$cell('north'), $cell('18'), $cell('yes'), $cell('drop')],
    ],
    'india punctuation' => [
        'headers' => [$cell('India'), $cell('A/B'), $cell('C-D'), $cell('E/F')],
        'body' => [$cell('x/y'), $cell('c-d'), $cell('e/f'), $cell('g/h')],
    ],
    'juliet mixed' => [
        'headers' => [$cell('Juliet'), $cell('Pipe \| head', 'Pipe | head'), $cell('`code|head`', 'code|head'), $cell('Drop')],
        'body' => [$cell('row'), $cell('Pipe \| body', 'Pipe | body'), $cell('`code|body`', 'code|body'), $cell('Drop body')],
    ],
];

$repairModes = [
    'short-header-full-body' => static function (array $fixture): array {
        return [
            'headers' => array_slice($fixture['headers'], 0, 2),
            'body' => array_slice($fixture['body'], 0, 3),
            'expectedHeaders' => [$fixture['headers'][0]['text'], $fixture['headers'][1]['text'], ''],
            'expectedBody' => [$fixture['body'][0]['text'], $fixture['body'][1]['text'], $fixture['body'][2]['text']],
            'repairs' => [
                ['section' => 'head', 'row' => 0, 'sourceCells' => 2, 'columnCount' => 3, 'action' => 'pad'],
            ],
        ];
    },
    'long-header-full-body' => static function (array $fixture): array {
        return [
            'headers' => array_slice($fixture['headers'], 0, 4),
            'body' => array_slice($fixture['body'], 0, 3),
            'expectedHeaders' => [$fixture['headers'][0]['text'], $fixture['headers'][1]['text'], $fixture['headers'][2]['text']],
            'expectedBody' => [$fixture['body'][0]['text'], $fixture['body'][1]['text'], $fixture['body'][2]['text']],
            'repairs' => [
                ['section' => 'head', 'row' => 0, 'sourceCells' => 4, 'columnCount' => 3, 'action' => 'truncate'],
            ],
        ];
    },
    'short-header-short-body' => static function (array $fixture): array {
        return [
            'headers' => array_slice($fixture['headers'], 0, 2),
            'body' => array_slice($fixture['body'], 0, 2),
            'expectedHeaders' => [$fixture['headers'][0]['text'], $fixture['headers'][1]['text'], ''],
            'expectedBody' => [$fixture['body'][0]['text'], $fixture['body'][1]['text'], ''],
            'repairs' => [
                ['section' => 'head', 'row' => 0, 'sourceCells' => 2, 'columnCount' => 3, 'action' => 'pad'],
                ['section' => 'body', 'row' => 0, 'sourceCells' => 2, 'columnCount' => 3, 'action' => 'pad'],
            ],
        ];
    },
    'long-header-long-body' => static function (array $fixture): array {
        return [
            'headers' => array_slice($fixture['headers'], 0, 4),
            'body' => array_slice($fixture['body'], 0, 4),
            'expectedHeaders' => [$fixture['headers'][0]['text'], $fixture['headers'][1]['text'], $fixture['headers'][2]['text']],
            'expectedBody' => [$fixture['body'][0]['text'], $fixture['body'][1]['text'], $fixture['body'][2]['text']],
            'repairs' => [
                ['section' => 'head', 'row' => 0, 'sourceCells' => 4, 'columnCount' => 3, 'action' => 'truncate'],
                ['section' => 'body', 'row' => 0, 'sourceCells' => 4, 'columnCount' => 3, 'action' => 'truncate'],
            ],
        ];
    },
];

$tests = [];
$caseCount = 0;
$fixtureIndex = 0;
foreach ($fixtures as $fixtureName => $fixture) {
    $modeIndex = 0;
    foreach ($repairModes as $modeName => $modeBuilder) {
        foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
            $caseCount++;
            $caseId = str_pad((string) $caseCount, 2, '0', STR_PAD_LEFT);
            $mode = $modeBuilder($fixture);
            $sides = (($fixtureIndex + $modeIndex + ($position === 'before-table' ? 0 : 1)) % 2) === 0;
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($fixtureName . '-' . $modeName . '-' . $position)) ?? '';
            $slug = trim($slug, '-');
            $id = 'tbl-pipe-repair-' . $caseId;
            $captionText = "Pipe repair caption {$caseId}";
            $captionLine = "{$marker} {$captionText} {#{$id} .pipe-repair .{$slug} data-mode=\"{$modeName}\"}";
            $markdown = $captionedTable(implode("\n", [
                $pipeLine($mode['headers'], $sides),
                $delimiterLine($sides),
                $pipeLine($mode['body'], $sides),
            ]), $position, $captionLine);

            $tests["maps upstream markdown reader pipe table row repair {$caseId} {$fixtureName} {$modeName} {$position}"] =
                static function (TestRunner $t) use (
                    $firstTable,
                    $tableCellText,
                    $markdown,
                    $mode,
                    $position,
                    $marker,
                    $id,
                    $slug,
                    $captionText,
                    $modeName
                ): void {
                    $document = (new MarkdownReader())->read($markdown);
                    $table = $firstTable($document);
                    $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
                    $blocks = (new WordPressBlockWriter())->write($document);

                    $t->same('table', $table->type);
                    $t->same(['left', 'right', 'center'], $table->attr('alignments'));
                    $t->same($captionText, $table->attr('caption'));
                    $t->same($id, $table->attr('id'));
                    $t->same(['pipe-repair', $slug], $table->attr('classes'));
                    $t->same($modeName, $table->attr('attributes')['data-mode'] ?? null);
                    $t->same($mode['repairs'], $table->attr('pipeTableRowRepairs', []));
                    $t->same('markdown-table-caption', $table->attr('captionSource')['element'] ?? null);
                    $t->same($position, $table->attr('captionSource')['position'] ?? null);
                    $t->same($marker, $table->attr('captionSource')['marker'] ?? null);
                    $t->same(true, $packet['summary']['completeRectangle'] ?? null);
                    $t->same($position === 'before-table' ? 'before-table' : 'after-table', $packet['summary']['captionPlacement'] ?? null);

                    foreach ($mode['expectedHeaders'] as $column => $expected) {
                        $t->same($expected, $tableCellText($table, 'head', 0, $column));
                    }
                    foreach ($mode['expectedBody'] as $column => $expected) {
                        $t->same($expected, $tableCellText($table, 'body', 0, $column));
                    }
                    $t->contains('<figcaption', $blocks);
                    $t->contains($captionText, $blocks);
                    $t->contains('data-mode="' . $modeName . '"', $blocks);
                };
        }
        $modeIndex++;
    }
    $fixtureIndex++;
}

$tests['records upstream markdown reader pipe table repair surge mapped-case count'] =
    static function (TestRunner $t) use ($caseCount): void {
        $t->same(80, $caseCount);
    };

return $tests;
