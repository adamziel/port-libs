<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$makePositionedLine = static function (array $cells, array $starts): string {
    $line = '';
    foreach ($cells as $index => $cell) {
        $start = $starts[$index];
        if (strlen($line) < $start) {
            $line .= str_repeat(' ', $start - strlen($line));
        }
        $line .= $cell;
    }

    return rtrim($line);
};

$makeDelimiterLine = static function (array $starts, array $widths): string {
    $line = '';
    foreach ($starts as $index => $start) {
        if (strlen($line) < $start) {
            $line .= str_repeat(' ', $start - strlen($line));
        }
        $line .= str_repeat('-', $widths[$index]);
    }

    return rtrim($line);
};

$firstTable = null;
$firstTable = static function (AstNode $node) use (&$firstTable): AstNode {
    if ($node->type === 'table') {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $firstTable($child);
        if ($match->type === 'table') {
            return $match;
        }
    }

    return new AstNode('missing');
};

$cellText = static function (AstNode $table, string $section, int $row, int $column): string {
    $sectionNode = $section === 'head' ? $table->children[0] : $table->children[1];
    $rowNode = $sectionNode->children[$row] ?? new AstNode('missing');
    $cell = $rowNode->children[$column] ?? new AstNode('missing');

    return (string) $cell->attr('text', '');
};

$layouts = [
    'two-column-narrow' => [
        'starts' => [0, 32],
        'widths' => [3, 3],
    ],
    'two-column-indented' => [
        'starts' => [2, 38],
        'widths' => [4, 4],
    ],
    'three-column-narrow' => [
        'starts' => [0, 30, 60],
        'widths' => [3, 4, 3],
    ],
    'three-column-indented' => [
        'starts' => [1, 34, 68],
        'widths' => [5, 3, 6],
    ],
];

$fixtures = [
    'alpha' => ['alpha first header', 'alpha second header', 'alpha third header', 'alpha body keeps full', 'alpha side keeps full', 'alpha tail keeps full'],
    'bravo' => ['bravo first header', 'bravo second header', 'bravo third header', 'bravo body keeps full', 'bravo side keeps full', 'bravo tail keeps full'],
    'charlie' => ['charlie first head', 'charlie second head', 'charlie third head', 'charlie body extends', 'charlie side extends', 'charlie tail extends'],
    'delta' => ['delta first header', 'delta second header', 'delta third header', 'delta body remains', 'delta side remains', 'delta tail remains'],
    'echo' => ['echo first header', 'echo second header', 'echo third header', 'echo body travels', 'echo side travels', 'echo tail travels'],
    'foxtrot' => ['foxtrot first head', 'foxtrot second head', 'foxtrot third head', 'foxtrot body expands', 'foxtrot side expands', 'foxtrot tail expands'],
    'golf' => ['golf first header', 'golf second header', 'golf third header', 'golf body survives', 'golf side survives', 'golf tail survives'],
    'hotel' => ['hotel first header', 'hotel second header', 'hotel third header', 'hotel body persists', 'hotel side persists', 'hotel tail persists'],
    'india' => ['india first header', 'india second header', 'india third header', 'india body retains', 'india side retains', 'india tail retains'],
    'juliet' => ['juliet first header', 'juliet second header', 'juliet third header', 'juliet body complete', 'juliet side complete', 'juliet tail complete'],
];

$cases = [];
foreach ($fixtures as $fixtureName => $fixture) {
    foreach ($layouts as $layoutName => $layout) {
        $columnCount = count($layout['starts']);
        $headers = array_slice($fixture, 0, $columnCount);
        $firstRow = array_slice($fixture, 3, $columnCount);
        $secondRow = array_map(
            static fn (string $cell): string => $cell . ' again',
            $firstRow
        );
        $delimiter = $makeDelimiterLine($layout['starts'], $layout['widths']);

        $cases["{$fixtureName} {$layoutName} headered"] = [
            'markdown' => implode("\n", [
                $makePositionedLine($headers, $layout['starts']),
                $delimiter,
                $makePositionedLine($firstRow, $layout['starts']),
            ]),
            'headers' => $headers,
            'rows' => [$firstRow],
        ];

        $cases["{$fixtureName} {$layoutName} headerless"] = [
            'markdown' => implode("\n", [
                $delimiter,
                $makePositionedLine($firstRow, $layout['starts']),
                $makePositionedLine($secondRow, $layout['starts']),
                $delimiter,
            ]),
            'headers' => null,
            'rows' => [$firstRow, $secondRow],
        ];
    }
}

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown reader simple table full column span ' . $name] =
        static function (TestRunner $t) use ($case, $firstTable, $cellText): void {
            $document = (new MarkdownReader())->read($case['markdown']);
            $table = $firstTable($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('table', $table->type);
            $t->same(count($case['rows'][0]), count($table->attr('alignments', [])));

            if ($case['headers'] === null) {
                $t->same([], $table->children[0]->children);
            } else {
                foreach ($case['headers'] as $column => $expected) {
                    $t->same($expected, $cellText($table, 'head', 0, $column));
                    $t->contains($expected, $blocks);
                }
            }

            foreach ($case['rows'] as $row => $cells) {
                foreach ($cells as $column => $expected) {
                    $t->same($expected, $cellText($table, 'body', $row, $column));
                    $t->contains($expected, $blocks);
                }
            }
        };
}

$tests['records upstream markdown reader simple table full-column span surge mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(80, count($cases));
    };

return $tests;
