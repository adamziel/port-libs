<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextReader
{
    public function readCsv(string $text): AstNode
    {
        return $this->read($text, 'csv');
    }

    public function readTsv(string $text): AstNode
    {
        return $this->read($text, 'tsv');
    }

    public function read(string $text, string $format = 'csv'): AstNode
    {
        $format = strtolower(trim($format));
        $delimiter = match ($format) {
            'csv' => ',',
            'tsv' => "\t",
            default => throw new \InvalidArgumentException("Unsupported delimited text format: {$format}"),
        };

        $rows = $this->parseRows($this->stripBom($text), $delimiter);
        if ($rows === []) {
            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $this->reviewPacket($format, $delimiter, [], []),
            ]);
        }

        $widths = array_map('count', $rows);
        $columnCount = max($widths);
        $header = array_shift($rows) ?? [];
        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = $this->tableRow($row, false, $columnCount);
        }

        $table = TableGeometry::withReviewPacket(new AstNode('table', [
            'sourceFormat' => $format,
            'alignments' => array_fill(0, $columnCount, 'default'),
            'delimitedText' => $this->reviewPacket($format, $delimiter, [$header, ...$rows], $widths),
        ], [
            new AstNode('table_head', [], [
                $this->tableRow($header, true, $columnCount),
            ]),
            new AstNode('table_body', [], $tableRows),
        ]));

        return new AstNode('document', [
            'sourceFormat' => $format,
            'delimitedText' => $table->attr('delimitedText'),
        ], [$table]);
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
    private function reviewPacket(string $format, string $delimiter, array $rows, array $widths): array
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
            'headerRow' => $rowCount > 0,
            'rowCount' => $rowCount,
            'bodyRowCount' => max(0, $rowCount - 1),
            'columnCount' => $columnCount,
            'fieldCount' => array_sum($widths),
            'minFieldCount' => $widths === [] ? 0 : min($widths),
            'maxFieldCount' => $columnCount,
            'raggedRowCount' => count($raggedRows),
            'raggedRows' => $raggedRows,
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
}
