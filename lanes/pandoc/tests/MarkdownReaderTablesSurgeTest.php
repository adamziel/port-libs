<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
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

$captionedMarkdown = static function (string $table, string $position, string $marker, string $caption): string {
    $captionLine = $marker . ($caption === '' ? '' : ' ' . $caption);

    return $position === 'before-table'
        ? $captionLine . "\n\n" . $table
        : $table . "\n\n" . $captionLine;
};

$firstTable = static function (AstNode $document): AstNode {
    foreach ($document->children as $node) {
        if ($node->type === 'table') {
            return $node;
        }
    }

    return new AstNode('missing');
};

$inlineTypes = static fn (array $nodes): array => array_values(array_map(
    static fn (AstNode $node): string => $node->type,
    $nodes
));

$assertTableShape = static function (TestRunner $t, AstNode $table, array $fixture): void {
    $t->same('table', $table->type);
    $t->same($fixture['alignments'], $table->attr('alignments'));
    $t->same($fixture['header'], $table->children[0]->children[0]->children[0]->attr('text'));
    $t->same($fixture['body'], $table->children[1]->children[0]->children[1]->attr('text'));
};

$tests = [];
$caseNumber = 1;

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        foreach (['plain' => 'Short label', 'formatted' => 'Short **label**'] as $style => $short) {
            $number = $caseNumber++;
            $tests["maps upstream markdown reader table short caption {$tableName} {$position} {$style}"] =
                static function (TestRunner $t) use ($captionedMarkdown, $firstTable, $inlineTypes, $assertTableShape, $fixture, $position, $marker, $short, $style, $number): void {
                    $caption = "[{$short} {$number}] Long **caption** {$number}";
                    $document = (new MarkdownReader())->read($captionedMarkdown($fixture['markdown'], $position, $marker, $caption));
                    $table = $firstTable($document);
                    $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

                    $assertTableShape($t, $table, $fixture);
                    $t->same("Long **caption** {$number}", $table->attr('caption'));
                    $t->same("Long caption {$number}", $packet['captions']['long']['text'] ?? null);
                    $t->same("Short label {$number}", $table->attr('shortCaption'));
                    $t->same($style === 'formatted' ? ['text', 'strong', 'text'] : ['text'], $inlineTypes($table->attr('shortCaptionInlines')));
                    $t->same(true, $packet['summary']['hasShortCaption'] ?? null);
                    $t->same($position, $table->attr('captionSource')['position'] ?? null);
                };
        }
    }
}

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table' => 'Caption:', 'after-table' => ':'] as $position => $marker) {
        foreach (['data' => 'data-source', 'lang' => 'lang'] as $style => $attributeName) {
            $number = $caseNumber++;
            $id = "tbl-{$tableName}-{$position}-{$style}";
            $tests["maps upstream markdown reader table caption attributes {$tableName} {$position} {$style}"] =
                static function (TestRunner $t) use ($captionedMarkdown, $firstTable, $assertTableShape, $fixture, $position, $marker, $tableName, $attributeName, $number, $id): void {
                    $caption = $attributeName === 'data-source'
                        ? "Attributed caption {$number} {#{$id} .review .{$tableName} data-source=\"batch-{$number}\" title=\"Review &amp; table\"}"
                        : "Attributed caption {$number} {#{$id} .review .{$tableName} lang=\"en-US\" title=\"{$tableName} table\"}";
                    $document = (new MarkdownReader())->read($captionedMarkdown($fixture['markdown'], $position, $marker, $caption));
                    $table = $firstTable($document);
                    $markdown = (new MarkdownWriter())->write(new AstNode('document', [], [$table]));

                    $assertTableShape($t, $table, $fixture);
                    $t->same("Attributed caption {$number}", $table->attr('caption'));
                    $t->same($id, $table->attr('id'));
                    $t->same(['review', $tableName], $table->attr('classes'));
                    $t->same($attributeName === 'data-source' ? "batch-{$number}" : 'en-US', $table->attr('attributes')[$attributeName] ?? null);
                    $t->contains("#{$id}", $markdown);
                    $t->contains('.review', $markdown);
                };
        }
    }
}

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table', 'after-table'] as $position) {
        foreach (['Table:', 'Caption:', ':'] as $marker) {
            $number = $caseNumber++;
            $tests["maps upstream markdown reader table caption source {$tableName} {$position} {$marker}"] =
                static function (TestRunner $t) use ($captionedMarkdown, $firstTable, $assertTableShape, $fixture, $position, $marker, $number): void {
                    $document = (new MarkdownReader())->read($captionedMarkdown($fixture['markdown'], $position, $marker, "Source marker caption {$number}"));
                    $table = $firstTable($document);
                    $source = $table->attr('captionSource');
                    $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

                    $assertTableShape($t, $table, $fixture);
                    $t->same("Source marker caption {$number}", $table->attr('caption'));
                    $t->same('markdown-table-caption', $source['element'] ?? null);
                    $t->same($position, $source['position'] ?? null);
                    $t->same($marker, $source['marker'] ?? null);
                    $t->same($position === 'before-table' ? 'top' : 'bottom', $source['captionSide'] ?? null);
                    $t->same($position === 'before-table' ? 'before-table' : 'after-table', $packet['summary']['captionPlacement'] ?? null);
                };
        }
    }
}

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        foreach (['link-continuation', 'code-continuation'] as $style) {
            $number = $caseNumber++;
            $tests["maps upstream markdown reader multiline table caption {$tableName} {$position} {$style}"] =
                static function (TestRunner $t) use ($captionedMarkdown, $firstTable, $inlineTypes, $assertTableShape, $fixture, $position, $marker, $style, $number): void {
                    $continuation = $style === 'link-continuation'
                        ? "  continuation with [review link](/review-{$number})"
                        : "  continuation with `code {$number}`";
                    $caption = "Multiline **caption** {$number}\n{$continuation}";
                    $document = (new MarkdownReader())->read($captionedMarkdown($fixture['markdown'], $position, $marker, $caption));
                    $table = $firstTable($document);
                    $types = $inlineTypes($table->attr('captionInlines'));
                    $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

                    $assertTableShape($t, $table, $fixture);
                    $t->contains("Multiline **caption** {$number}\ncontinuation", $table->attr('caption'));
                    $t->contains('strong', implode(',', $types));
                    $t->contains($style === 'link-continuation' ? 'link' : 'code', implode(',', $types));
                    $t->same(true, $packet['summary']['hasCaption'] ?? null);
                    $t->same($position === 'before-table' ? 'top' : 'bottom', $packet['summary']['captionSide'] ?? null);
                };
        }
    }
}

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        $number = $caseNumber++;
        $tests["maps upstream markdown reader table caption wordpress handoff {$tableName} {$position}"] =
            static function (TestRunner $t) use ($captionedMarkdown, $firstTable, $fixture, $position, $marker, $tableName, $number): void {
                $caption = "[Short {$number}] Handoff **caption** {$number} {#handoff-{$tableName}-{$position} .handoff data-source=\"surge\"}";
                $document = (new MarkdownReader())->read($captionedMarkdown($fixture['markdown'], $position, $marker, $caption));
                $table = $firstTable($document);
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same("Short {$number}", $table->attr('shortCaption'));
                $t->contains('data-pandoc-short-caption="Short ' . $number . '"', $blocks);
                $t->contains('Handoff <strong>caption</strong> ' . $number, $blocks);
                $t->contains('data-source="surge"', $blocks);
            };
    }
}

$blockCellPayloads = [
    'paragraph bullet list' => static function (string $caseId): array {
        return [
            'lines' => ['Intro ' . $caseId, '- item ' . $caseId, '- done ' . $caseId],
            'types' => ['paragraph', 'bullet_list'],
            'html' => '<ul><li>item ' . $caseId . '</li><li>done ' . $caseId . '</li></ul>',
        ];
    },
    'ordered list' => static function (string $caseId): array {
        return [
            'lines' => ['1. first ' . $caseId, '2. second ' . $caseId],
            'types' => ['ordered_list'],
            'html' => '<ol><li>first ' . $caseId . '</li><li>second ' . $caseId . '</li></ol>',
        ];
    },
    'numbered example list' => static function (string $caseId): array {
        return [
            'lines' => ['#. first ' . $caseId, '#. second ' . $caseId],
            'types' => ['ordered_list'],
            'html' => '<ol><li>first ' . $caseId . '</li><li>second ' . $caseId . '</li></ol>',
        ];
    },
    'heading block' => static function (string $caseId): array {
        return [
            'lines' => ['### Head ' . $caseId],
            'types' => ['heading'],
            'html' => '<h3 id="head-' . $caseId . '">Head ' . $caseId . '</h3>',
        ];
    },
    'blockquote block' => static function (string $caseId): array {
        return [
            'lines' => ['> quoted ' . $caseId],
            'types' => ['blockquote'],
            'html' => '<blockquote><p>quoted ' . $caseId . '</p></blockquote>',
        ];
    },
    'fenced code block' => static function (string $caseId): array {
        return [
            'lines' => ['```', 'code ' . $caseId, '```'],
            'types' => ['code_block'],
            'html' => '<code>code ' . $caseId . '</code>',
        ];
    },
    'definition list block' => static function (string $caseId): array {
        return [
            'lines' => ['Term ' . $caseId, ': detail ' . $caseId],
            'types' => ['definition_list'],
            'html' => '<dl><dt>Term ' . $caseId . '</dt><dd>detail ' . $caseId . '</dd></dl>',
        ];
    },
    'line block' => static function (string $caseId): array {
        return [
            'lines' => ['| first ' . $caseId, '| second ' . $caseId],
            'types' => ['line_block'],
            'html' => 'first ' . $caseId . '<br/>second ' . $caseId,
        ];
    },
    'horizontal rule block' => static function (string $caseId): array {
        return [
            'lines' => ['***'],
            'types' => ['horizontal_rule'],
            'html' => '<hr',
        ];
    },
    'fenced div block' => static function (string $caseId): array {
        return [
            'lines' => ['::: {.note}', 'Div ' . $caseId, ':::'],
            'types' => ['div'],
            'html' => '<div class="note"><p>Div ' . $caseId . '</p></div>',
        ];
    },
];

$simpleBlockTable = static function (string $shape, array $payloadLines): string {
    $firstPayload = array_shift($payloadLines);
    $body = [str_pad('Alpha', 10) . $firstPayload];
    foreach ($payloadLines as $line) {
        $body[] = str_repeat(' ', 10) . $line;
    }

    $header = str_pad('Item', 10) . 'Notes';
    $delimiter = '--------  ------------------------------------------------';
    $boundary = str_repeat('-', 62);

    return match ($shape) {
        'with-header' => implode("\n", [$header, $delimiter, ...$body]),
        'without-header' => implode("\n", [$delimiter, ...$body, $delimiter]),
        'multiline-header' => implode("\n", [$boundary, $header, $delimiter, ...$body, $boundary]),
    };
};

$secondBodyCell = static function (AstNode $table): AstNode {
    $body = null;
    foreach ($table->children as $child) {
        if ($child->type === 'table_body') {
            $body = $child;
            break;
        }
    }

    return $body?->children[0]?->children[1] ?? new AstNode('missing');
};

$blockCellCaseCount = 0;
foreach (['with-header', 'without-header', 'multiline-header'] as $shape) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        foreach ($blockCellPayloads as $label => $payloadBuilder) {
            $blockCellCaseCount++;
            $caseId = str_pad((string) $blockCellCaseCount, 3, '0', STR_PAD_LEFT);
            $tests["maps upstream markdown reader simple table block cell {$shape} {$position} {$label}"] =
                static function (TestRunner $t) use ($payloadBuilder, $simpleBlockTable, $captionedMarkdown, $firstTable, $secondBodyCell, $shape, $position, $marker, $caseId): void {
                    $payload = $payloadBuilder($caseId);
                    $caption = "[Cell {$caseId}] Block cell caption {$caseId} {#block-cell-{$caseId} .block-cell data-case=\"{$caseId}\"}";
                    $markdown = $captionedMarkdown($simpleBlockTable($shape, $payload['lines']), $position, $marker, $caption);
                    $document = (new MarkdownReader())->read($markdown);
                    $table = $firstTable($document);
                    $cell = $secondBodyCell($table);
                    $blocks = (new WordPressBlockWriter())->write($document);
                    $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

                    $t->same('table_cell', $cell->type);
                    $t->same($payload['types'], array_map(static fn (AstNode $node): string => $node->type, $cell->children));
                    $t->same("Block cell caption {$caseId}", $table->attr('caption'));
                    $t->same("Cell {$caseId}", $table->attr('shortCaption'));
                    $t->same('block-cell-' . $caseId, $table->attr('id'));
                    $t->same(['block-cell'], $table->attr('classes'));
                    $t->same($caseId, $table->attr('attributes')['data-case'] ?? null);
                    $t->same($position === 'before-table' ? 'before-table' : 'after-table', $packet['summary']['captionPlacement'] ?? null);
                    $t->contains($payload['html'], $blocks);
                    $t->contains('data-pandoc-short-caption="Cell ' . $caseId . '"', $blocks);
                };
        }
    }
}

$tests['records upstream markdown reader simple table block cell mapped-case count'] = static function (TestRunner $t) use ($blockCellCaseCount): void {
    $t->same(60, $blockCellCaseCount);
};

return $tests;
