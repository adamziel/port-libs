<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$tableFixtures = [
    'pipe' => [
        'markdown' => implode("\n", [
            '| Metric | Value |',
            '|:-------|------:|',
            '| Queue  | 12    |',
        ]),
        'extension' => 'pipe_tables',
        'expected' => ['Metric', 'Value', 'Queue', '12'],
        'alignments' => ['left', 'right'],
    ],
    'simple' => [
        'markdown' => implode("\n", [
            'Metric  Value',
            '------  -----',
            'Queue   12',
        ]),
        'extension' => 'simple_tables',
        'expected' => ['Metric', 'Value', 'Queue', '12'],
        'alignments' => ['default', 'default'],
    ],
    'grid' => [
        'markdown' => implode("\n", [
            '+--------+-------+',
            '| Metric | Value |',
            '+========+=======+',
            '| Queue  | 12    |',
            '+--------+-------+',
        ]),
        'extension' => 'grid_tables',
        'expected' => ['Metric', 'Value', 'Queue', '12'],
        'alignments' => ['default', 'default'],
    ],
];

$multilineFixture = [
    'markdown' => implode("\n", [
        '--------------------------',
        'Metric            Value',
        '----------------  --------',
        'Queue             12',
        '                  ready',
        '--------------------------',
    ]),
    'extension' => 'multiline_tables',
    'expected' => ['Metric', 'Value', 'Queue', "12\nready"],
    'alignments' => ['left', 'left'],
];

$captionedTable = static function (string $table, string $position, string $caption): string {
    return $position === 'before-table'
        ? $caption . "\n\n" . $table
        : $table . "\n\n" . $caption;
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

$plainInlineText = null;
$plainInlineText = static function (array $nodes) use (&$plainInlineText): string {
    $text = '';
    foreach ($nodes as $node) {
        if (!$node instanceof AstNode) {
            continue;
        }
        if ($node->type === 'text' || $node->type === 'code') {
            $text .= (string) $node->attr('text', '');
            continue;
        }
        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            $text .= "\n";
            continue;
        }

        $text .= $plainInlineText($node->children);
    }

    return $text;
};

$cellText = static function (AstNode $table, string $section, int $row, int $column) use ($plainInlineText): string {
    $sectionNode = $section === 'head' ? $table->children[0] : $table->children[1];
    $rowNode = $sectionNode->children[$row] ?? new AstNode('missing');
    $cell = $rowNode->children[$column] ?? new AstNode('missing');

    return $plainInlineText($cell->children);
};

$assertTableParsed = static function (
    TestRunner $t,
    AstNode $document,
    array $fixture,
    string $position,
    string $marker,
    string $caption,
    ?string $expectedCaption = null,
    ?string $expectedCaptionSourcePosition = null,
    ?string $expectedCaptionSourceMarker = null
) use ($firstTable, $cellText): void {
    $table = $firstTable($document);
    $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
    $blocks = (new WordPressBlockWriter())->write($document);
    [$head0, $head1, $body0, $body1] = $fixture['expected'];
    $expectedCaption ??= $caption;
    $expectedCaptionSourcePosition = func_num_args() >= 8 ? $expectedCaptionSourcePosition : $position;
    $expectedCaptionSourceMarker = func_num_args() >= 9 ? $expectedCaptionSourceMarker : $marker;
    $expectedCaptionPlacement = $expectedCaptionSourcePosition === null
        ? ''
        : ($expectedCaptionSourcePosition === 'before-table' ? 'before-table' : 'after-table');

    $t->same('table', $table->type);
    $t->same($fixture['alignments'], $table->attr('alignments'));
    $t->same($expectedCaption, $table->attr('caption'));
    $t->same($expectedCaptionSourcePosition, $table->attr('captionSource')['position'] ?? null);
    $t->same($expectedCaptionSourceMarker, $table->attr('captionSource')['marker'] ?? null);
    $t->same($expectedCaptionPlacement, $packet['summary']['captionPlacement'] ?? null);
    $t->same($head0, $cellText($table, 'head', 0, 0));
    $t->same($head1, $cellText($table, 'head', 0, 1));
    $t->same($body0, $cellText($table, 'body', 0, 0));
    $t->same($body1, $cellText($table, 'body', 0, 1));
    if ($expectedCaption !== '') {
        $t->contains('<figcaption', $blocks);
    }
};

$assertTableDisabled = static function (TestRunner $t, AstNode $document, string $sourceNeedle) use ($firstTable): void {
    $table = $firstTable($document);

    $t->same('missing', $table->type);
};

$profileCases = [
    'default enables all table syntaxes' => [
        'options' => [],
        'enabled' => ['pipe', 'simple', 'grid', 'multiline'],
    ],
    'markdown enables all table syntaxes' => [
        'options' => ['format' => 'markdown'],
        'enabled' => ['pipe', 'simple', 'grid', 'multiline'],
    ],
    'commonmark x enables pipe table syntax only' => [
        'options' => ['format' => 'commonmark_x'],
        'enabled' => ['pipe'],
    ],
    'commonmark disables table syntaxes' => [
        'options' => ['format' => 'commonmark'],
        'enabled' => [],
    ],
    'strict disables table syntaxes' => [
        'options' => ['format' => 'markdown_strict'],
        'enabled' => [],
    ],
    'gfm enables pipe tables only' => [
        'options' => ['format' => 'gfm'],
        'enabled' => ['pipe'],
    ],
    'multimarkdown enables pipe tables only' => [
        'options' => ['format' => 'markdown_mmd'],
        'enabled' => ['pipe'],
    ],
    'php extra enables pipe tables only' => [
        'options' => ['format' => 'markdown_phpextra'],
        'enabled' => ['pipe'],
    ],
    'github markdown enables pipe tables only' => [
        'options' => ['format' => 'markdown_github'],
        'enabled' => ['pipe'],
    ],
    'commonmark suffix enables pipe only' => [
        'options' => ['format' => 'commonmark+pipe_tables'],
        'enabled' => ['pipe'],
    ],
    'commonmark suffix enables simple only' => [
        'options' => ['format' => 'commonmark+simple_tables'],
        'enabled' => ['simple'],
    ],
    'commonmark suffix enables grid only' => [
        'options' => ['format' => 'commonmark+grid_tables'],
        'enabled' => ['grid'],
    ],
    'commonmark suffix enables multiline only' => [
        'options' => ['format' => 'commonmark+multiline_tables'],
        'enabled' => ['multiline'],
    ],
    'strict suffix enables pipe and grid' => [
        'options' => ['format' => 'markdown_strict+pipe_tables+grid_tables'],
        'enabled' => ['pipe', 'grid'],
    ],
    'markdown suffix disables pipe only' => [
        'options' => ['format' => 'markdown-pipe_tables'],
        'enabled' => ['simple', 'grid', 'multiline'],
    ],
    'markdown suffix disables simple only' => [
        'options' => ['format' => 'markdown-simple_tables'],
        'enabled' => ['pipe', 'grid', 'multiline'],
    ],
    'markdown suffix disables grid only' => [
        'options' => ['format' => 'markdown-grid_tables'],
        'enabled' => ['pipe', 'simple', 'multiline'],
    ],
    'markdown suffix disables multiline only' => [
        'options' => ['format' => 'markdown-multiline_tables'],
        'enabled' => ['pipe', 'simple', 'grid'],
    ],
    'associative enables pipe on commonmark' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['pipe_tables' => true]],
        'enabled' => ['pipe'],
    ],
    'associative enables simple on commonmark' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['simple_tables' => true]],
        'enabled' => ['simple'],
    ],
    'associative enables grid on commonmark' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['grid_tables' => true]],
        'enabled' => ['grid'],
    ],
    'associative enables multiline on commonmark' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['multiline_tables' => true]],
        'enabled' => ['multiline'],
    ],
    'token list enables pipe and simple on strict' => [
        'options' => ['format' => 'markdown_strict', 'extensions' => ['+pipe_tables', '+simple_tables']],
        'enabled' => ['pipe', 'simple'],
    ],
    'string list enables grid and multiline on strict' => [
        'options' => ['format' => 'markdown_strict', 'extensions' => '+grid_tables +multiline_tables'],
        'enabled' => ['grid', 'multiline'],
    ],
    'comma list disables pipe and grid on markdown' => [
        'options' => ['extensions' => '-pipe_tables,-grid_tables'],
        'enabled' => ['simple', 'multiline'],
    ],
    'alias table enables pipe on commonmark' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['tables' => true]],
        'enabled' => ['pipe'],
    ],
    'alias pipe table enables pipe on commonmark' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['pipe_table' => true]],
        'enabled' => ['pipe'],
    ],
    'alias simple table enables simple on commonmark' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['simple_table' => true]],
        'enabled' => ['simple'],
    ],
    'alias grid table enables grid on commonmark' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['grid_table' => true]],
        'enabled' => ['grid'],
    ],
    'alias multiline table enables multiline on commonmark' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['multiline_table' => true]],
        'enabled' => ['multiline'],
    ],
    'boolean strings disable pipe and simple' => [
        'options' => ['extensions' => ['pipe_tables' => 'false', 'simple_tables' => '0']],
        'enabled' => ['grid', 'multiline'],
    ],
    'boolean strings enable grid and multiline on commonmark' => [
        'options' => ['format' => 'commonmark', 'extensions' => ['grid_tables' => 'true', 'multiline_tables' => 'yes']],
        'enabled' => ['grid', 'multiline'],
    ],
];

$tests = [];
$caseCount = 0;

foreach ($profileCases as $profileName => $profile) {
    foreach ($tableFixtures as $tableName => $fixture) {
        foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
            $caseCount++;
            $enabled = in_array($tableName, $profile['enabled'], true);
            $caseId = str_pad((string) $caseCount, 3, '0', STR_PAD_LEFT);
            $caption = "Table profile caption {$caseId}";
            $markdown = $captionedTable($fixture['markdown'], $position, "{$marker} {$caption}");
            $mmdTopLevelMetadataCaption = $profileName === 'multimarkdown enables pipe tables only' && $position === 'before-table';
            $expectedCaption = $mmdTopLevelMetadataCaption ? '' : $caption;
            $expectedCaptionSourcePosition = $mmdTopLevelMetadataCaption ? null : $position;
            $expectedCaptionSourceMarker = $mmdTopLevelMetadataCaption ? null : $marker;

            $tests["maps upstream markdown reader table profile {$caseId} {$profileName} {$tableName} {$position}"] =
                static function (TestRunner $t) use ($profile, $markdown, $enabled, $fixture, $position, $marker, $caption, $expectedCaption, $expectedCaptionSourcePosition, $expectedCaptionSourceMarker, $assertTableParsed, $assertTableDisabled): void {
                    $document = (new MarkdownReader($profile['options']))->read($markdown);

                    if ($enabled) {
                        $assertTableParsed(
                            $t,
                            $document,
                            $fixture,
                            $position,
                            $marker,
                            $caption,
                            $expectedCaption,
                            $expectedCaptionSourcePosition,
                            $expectedCaptionSourceMarker
                        );
                        return;
                    }

                    $assertTableDisabled($t, $document, $fixture['expected'][0]);
                };
        }
    }
}

$multilineProfileCases = [
    'default enables multiline table syntax' => [
        'options' => [],
        'enabled' => true,
    ],
    'commonmark disables multiline table syntax' => [
        'options' => ['format' => 'commonmark'],
        'enabled' => false,
    ],
    'commonmark x disables multiline table syntax' => [
        'options' => ['format' => 'commonmark_x'],
        'enabled' => false,
    ],
    'commonmark suffix enables multiline table syntax' => [
        'options' => ['format' => 'commonmark+multiline_tables'],
        'enabled' => true,
    ],
    'strict configured enables multiline table syntax' => [
        'options' => ['format' => 'markdown_strict', 'extensions' => ['+multiline_tables']],
        'enabled' => true,
    ],
    'multimarkdown disables multiline table syntax' => [
        'options' => ['format' => 'markdown_mmd'],
        'enabled' => false,
    ],
    'php extra disables multiline table syntax' => [
        'options' => ['format' => 'markdown_phpextra'],
        'enabled' => false,
    ],
    'markdown configured disables multiline table syntax' => [
        'options' => ['extensions' => ['multiline_tables' => false, 'simple_tables' => false]],
        'enabled' => false,
    ],
];

foreach ($multilineProfileCases as $profileName => $profile) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        $caseCount++;
        $caseId = str_pad((string) $caseCount, 3, '0', STR_PAD_LEFT);
        $caption = "Table profile caption {$caseId}";
        $markdown = $captionedTable($multilineFixture['markdown'], $position, "{$marker} {$caption}");

        $tests["maps upstream markdown reader multiline table profile {$caseId} {$profileName} {$position}"] =
            static function (TestRunner $t) use ($profile, $markdown, $multilineFixture, $position, $marker, $caption, $assertTableParsed, $assertTableDisabled): void {
                $document = (new MarkdownReader($profile['options']))->read($markdown);

                if ($profile['enabled']) {
                    $assertTableParsed($t, $document, $multilineFixture, $position, $marker, $caption);
                    return;
                }

                $assertTableDisabled($t, $document, $multilineFixture['expected'][0]);
            };
    }
}

$tests['maps upstream markdown reader commonmark x grid table default fixture as paragraph'] =
    static function (TestRunner $t) use ($firstTable, $plainInlineText): void {
        $fixtureName = 'upstream-markdown-z-commonmark-x-grid-table-default.md';
        $markdown = file_get_contents(dirname(__DIR__) . '/fixtures/' . $fixtureName);
        if (!is_string($markdown)) {
            throw new RuntimeException("Unable to read {$fixtureName}");
        }

        $commonmark = (new MarkdownReader(['format' => 'commonmark_x']))->read($markdown);
        $paragraph = $commonmark->children[0] ?? new AstNode('missing');

        $t->same('missing', $firstTable($commonmark)->type);
        $t->same('paragraph', $paragraph->type);
        $t->same(
            "+\u{2014}+\u{2014}+\n| A | B |\n+===+===+\n| 1 | 2 |\n+\u{2014}+\u{2014}+",
            $plainInlineText($paragraph->children)
        );
        $t->same('table', $firstTable((new MarkdownReader(['format' => 'markdown']))->read($markdown))->type);
    };

$tests['imports selected upstream markdown multiline table fixtures'] =
    static function (TestRunner $t) use ($firstTable, $cellText): void {
        $root = dirname(__DIR__) . '/fixtures';
        $fixtures = [
            'upstream-markdown-multiline-table-caption.md' => [
                'caption' => "Here's the caption. It may span multiple lines.",
                'alignments' => ['center', 'left', 'right', 'left'],
                'headRows' => 1,
            ],
            'upstream-markdown-multiline-table-no-caption.md' => [
                'caption' => '',
                'alignments' => ['center', 'left', 'right', 'left'],
                'headRows' => 1,
            ],
            'upstream-markdown-multiline-table-no-header.md' => [
                'caption' => '',
                'alignments' => ['center', 'left', 'right', 'default'],
                'headRows' => 0,
            ],
        ];

        foreach ($fixtures as $fixtureName => $expected) {
            $nativeFixtureName = substr($fixtureName, 0, -3) . '.native';
            $markdown = file_get_contents($root . '/' . $fixtureName);
            if (!is_string($markdown)) {
                throw new RuntimeException("Unable to read {$fixtureName}");
            }

            $t->true(is_file($root . '/' . $nativeFixtureName), "{$nativeFixtureName} must be checked in");
            $document = (new MarkdownReader())->read($markdown);
            $table = $firstTable($document);

            $t->same('table', $table->type, $fixtureName);
            $t->same($expected['caption'], $table->attr('caption'), $fixtureName);
            $t->same($expected['alignments'], $table->attr('alignments'), $fixtureName);
            $t->same($expected['headRows'], count($table->children[0]->children), $fixtureName);
            $t->same('First', $cellText($table, 'body', 0, 0), $fixtureName);
            $t->same('row', $cellText($table, 'body', 0, 1), $fixtureName);
            $t->same('12.0', $cellText($table, 'body', 0, 2), $fixtureName);
            $t->same("Example of a row that spans\nmultiple lines.", $cellText($table, 'body', 0, 3), $fixtureName);
            $t->same("Here\u{2019}s another one. Note\nthe blank line between\nrows.", $cellText($table, 'body', 1, 3), $fixtureName);
        }
    };

$tests['records upstream markdown reader table profile surge mapped-case count'] =
    static function (TestRunner $t) use ($caseCount): void {
        $t->same(208, $caseCount);
    };

return $tests;
