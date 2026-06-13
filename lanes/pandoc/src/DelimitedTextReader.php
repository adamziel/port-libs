<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextReader
{
    /**
     * @param array{header?:bool} $options
     */
    public function readCsv(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'csv', $options);
    }

    /**
     * @param array{header?:bool} $options
     */
    public function readTsv(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'tsv', $options);
    }

    /**
     * @param array{header?:bool} $options
     */
    public function read(string $text, string $format = 'csv', array $options = []): AstNode
    {
        $format = strtolower(trim($format));
        $delimiter = match ($format) {
            'csv' => ',',
            'tsv' => "\t",
            default => throw new \InvalidArgumentException("Unsupported delimited text format: {$format}"),
        };
        $hasHeader = $this->headerOption($options);

        $rows = $this->parseRows($this->stripBom($text), $delimiter);
        if ($rows === []) {
            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $this->reviewPacket($format, $delimiter, [], [], $hasHeader),
            ]);
        }

        $widths = array_map('count', $rows);
        $columnCount = max($widths);
        $sourceHeader = $hasHeader ? array_shift($rows) : null;
        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = $this->tableRow($row, false, $columnCount);
        }

        $headRows = [];
        if ($sourceHeader !== null) {
            $headRows[] = $this->tableRow($sourceHeader, true, $columnCount);
        }

        $table = TableGeometry::withReviewPacket(new AstNode('table', [
            'sourceFormat' => $format,
            'alignments' => array_fill(0, $columnCount, 'default'),
            'delimitedText' => $this->reviewPacket($format, $delimiter, $sourceHeader === null ? $rows : [$sourceHeader, ...$rows], $widths, $hasHeader),
            'columnNames' => $sourceHeader === null ? $this->generatedColumnLabels($columnCount) : $this->normalizedRow($sourceHeader, $columnCount),
        ], [
            new AstNode('table_head', [], $headRows),
            new AstNode('table_body', [], $tableRows),
        ]));

        return new AstNode('document', [
            'sourceFormat' => $format,
            'delimitedText' => $table->attr('delimitedText'),
        ], [$table]);
    }

    /**
     * @param array{header?:bool} $options
     */
    private function headerOption(array $options): bool
    {
        if (!array_key_exists('header', $options)) {
            return true;
        }

        if (!is_bool($options['header'])) {
            throw new \InvalidArgumentException('Delimited text header option must be a boolean');
        }

        return $options['header'];
    }

    /**
     * @return list<list<string>>
     */
    private function parseRows(string $text, string $delimiter): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open in-memory CSV stream');
        }

        fwrite($stream, $text);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $rows[] = array_map(
                static fn (?string $value): string => $value ?? '',
                $row
            );
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @param list<string> $row
     */
    private function tableRow(array $row, bool $header, int $columnCount): AstNode
    {
        $cells = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $text = $row[$column] ?? '';
            $cells[] = new AstNode('table_cell', [
                'header' => $header,
                'text' => $text,
                'sourceColumn' => $column,
            ], $text === '' ? [] : [new AstNode('plain', [], [new AstNode('text', ['text' => $text])])]);
        }

        return new AstNode('table_row', ['header' => $header], $cells);
    }

    /**
     * @param list<list<string>> $rows
     * @param list<int> $widths
     * @return array<string, mixed>
     */
    private function reviewPacket(string $format, string $delimiter, array $rows, array $widths, bool $hasHeader): array
    {
        $rowCount = count($rows);
        $columnCount = $widths === [] ? 0 : max($widths);
        $raggedRows = [];
        foreach ($widths as $index => $width) {
            if ($width !== $columnCount) {
                $raggedRows[] = $index;
            }
        }

        return [
            'format' => $format,
            'delimiter' => $delimiter === "\t" ? 'tab' : $delimiter,
            'headerRow' => $hasHeader && $rowCount > 0,
            'headerOption' => $hasHeader ? 'first-row' : 'none',
            'headerSource' => $hasHeader && $rowCount > 0 ? 'source-row-0' : 'generated',
            'rowCount' => $rowCount,
            'bodyRowCount' => $hasHeader ? max(0, $rowCount - 1) : $rowCount,
            'columnCount' => $columnCount,
            'columnNames' => $hasHeader && $rows !== []
                ? $this->normalizedRow($rows[0], $columnCount)
                : $this->generatedColumnLabels($columnCount),
            'fieldCount' => array_sum($widths),
            'minFieldCount' => $widths === [] ? 0 : min($widths),
            'maxFieldCount' => $columnCount,
            'raggedRowCount' => count($raggedRows),
            'raggedRows' => $raggedRows,
            'diagnostics' => $hasHeader ? [] : [[
                'code' => 'delimited-text-header-disabled',
                'severity' => 'info',
                'message' => 'No source header row was consumed; generated column labels are review metadata only.',
            ]],
            'upstreamEvidence' => [
                'denominator' => 2,
                'fixtures' => [
                    'test/command/01.csv',
                    'test/command/3533-rst-csv-tables.csv',
                ],
                'source' => 'lanes/pandoc/src/UpstreamRunnerDependencyAudit.php static extra-source inventory',
            ],
        ];
    }

    private function stripBom(string $text): string
    {
        return str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
    }

    /**
     * @return list<string>
     */
    private function generatedColumnLabels(int $columnCount): array
    {
        $labels = [];
        for ($index = 1; $index <= $columnCount; $index++) {
            $labels[] = 'column' . $index;
        }

        return $labels;
    }

    /**
     * @param list<string> $row
     * @return list<string>
     */
    private function normalizedRow(array $row, int $columnCount): array
    {
        $normalized = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $normalized[] = $row[$column] ?? '';
        }

        return $normalized;
    }
}
