<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownWriter
{
    /** @var list<array{number:int, node:AstNode}> */
    private array $notes = [];

    /** @var list<array{label:string, url:string, title:string, attrs:array<string, mixed>}> */
    private array $references = [];

    /** @var array<string, int> */
    private array $referenceLabelUses = [];

    /** @var array<string, bool> */
    private array $referenceUsedLabels = [];

    /** @var array<string, string> */
    private array $referenceTargetLabels = [];

    private int $nextNoteNumber = 1;

    private int $lastReferenceIndex = 0;

    /**
     * @param array{setextHeadings?: bool, referenceLinks?: bool, referenceLocation?: string, bulletListMarker?: string, softBreak?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Markdown writer expects a document node');
        }

        $this->notes = [];
        $this->references = [];
        $this->referenceLabelUses = [];
        $this->referenceUsedLabels = [];
        $this->referenceTargetLabels = [];
        $this->nextNoteNumber = 1;
        $this->lastReferenceIndex = 0;

        $blocks = [];
        foreach ($document->children as $index => $node) {
            if ($this->referenceLocation() === 'end_of_section' && $node->type === 'heading' && $index > 0) {
                $this->appendPendingDefinitions($blocks);
            }

            if ($node->type === 'code_block' && $index > 0 && $this->isListBlock($document->children[$index - 1])) {
                $blocks[] = '<!-- -->';
            }

            $lines = $this->renderBlock($node, 0);
            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }

            if ($this->referenceLocation() === 'end_of_block') {
                $this->appendPendingDefinitions($blocks);
            }
        }
        $this->appendPendingDefinitions($blocks);

        return implode("\n\n", $blocks);
    }

    /**
     * @return list<string>
     */
    private function renderBlock(AstNode $node, int $indent): array
    {
        return match ($node->type) {
            'paragraph', 'plain' => [str_repeat(' ', $indent) . $this->renderInlines($node->children)],
            'heading' => $this->renderHeading($node, $indent),
            'figure' => $this->renderFigure($node, $indent),
            'bullet_list' => $this->renderList($node, false, $indent),
            'ordered_list' => $this->renderList($node, true, $indent),
            'definition_list' => $this->renderDefinitionList($node, $indent),
            'line_block' => $this->renderLineBlock($node, $indent),
            'blockquote' => $this->renderBlockQuote($node, $indent),
            'div' => $this->renderDivBlock($node, $indent),
            'code_block' => $this->renderCodeBlock($node, $indent),
            'table' => $this->renderTable($node, $indent),
            'horizontal_rule' => [str_repeat(' ', $indent) . '* * *'],
            'raw_tex', 'raw_markdown', 'raw_block' => $this->renderRawBlock($node, $indent),
            'raw_html' => array_map(
                static fn (string $line): string => str_repeat(' ', $indent) . $line,
                explode("\n", (string) $node->attr('html', ''))
            ),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function renderHeading(AstNode $node, int $indent): array
    {
        $level = max(1, min(6, (int) $node->attr('level', 1)));
        $text = $this->renderInlines($node->children);
        $attrs = $this->renderLinkAttributes($node);
        if ($attrs !== '') {
            $text .= ' ' . $attrs;
        }
        $prefix = str_repeat(' ', $indent);

        if ($indent === 0 && (bool) ($this->options['setextHeadings'] ?? false) && ($level === 1 || $level === 2)) {
            return [
                $text,
                str_repeat($level === 1 ? '=' : '-', max(1, strlen($text))),
            ];
        }

        return [$prefix . str_repeat('#', $level) . ' ' . $text];
    }

    /**
     * @return list<string>
     */
    private function renderFigure(AstNode $node, int $indent): array
    {
        foreach ($node->children as $child) {
            if ($child->type === 'image') {
                return [str_repeat(' ', $indent) . $this->renderImage($this->imageWithFigureAttrs($node, $child), [])];
            }
        }

        $body = $this->renderBlockCollection($node->children);

        return $body === '' ? [] : explode("\n", $body);
    }

    /**
     * @return list<string>
     */
    private function renderLineBlock(AstNode $node, int $indent): array
    {
        $prefix = str_repeat(' ', $indent) . '|';
        $lines = [];

        foreach ($node->children as $line) {
            if ($line->type !== 'line') {
                continue;
            }

            $content = $line->children === []
                ? (string) $line->attr('text', '')
                : $this->renderInlines($line->children);
            $content = str_replace("\xC2\xA0", ' ', $content);
            $lines[] = rtrim($prefix . ($content === '' ? '' : ' ' . $content));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionList(AstNode $node, int $indent): array
    {
        $lines = [];
        $prefix = str_repeat(' ', $indent);

        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item' || $item->children === []) {
                continue;
            }

            $term = $item->children[0];
            $termMarkdown = $term->type === 'definition_term'
                ? $this->renderInlines($term->children)
                : $this->renderInlines([$term]);

            if ($lines !== [] && end($lines) !== '') {
                $lines[] = '';
            }
            $lines[] = $prefix . $termMarkdown;

            foreach (array_slice($item->children, 1) as $definition) {
                if ($definition->type !== 'definition') {
                    continue;
                }

                $definitionLines = $this->renderDefinitionBody($definition, $indent);
                if ($definitionLines !== []) {
                    array_push($lines, ...$definitionLines);
                }
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionBody(AstNode $definition, int $indent): array
    {
        $body = $this->renderBlockCollection($definition->children);
        $markerPrefix = str_repeat(' ', $indent) . ':   ';
        $continuationPrefix = str_repeat(' ', $indent + 4);

        if ($body === '') {
            return [rtrim($markerPrefix)];
        }

        $bodyLines = explode("\n", $body);
        $first = array_shift($bodyLines);
        $lines = [$markerPrefix . (string) $first];

        foreach ($bodyLines as $line) {
            $lines[] = $line === '' ? '' : $continuationPrefix . $line;
        }

        if ((bool) $definition->attr('loose', false)) {
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderList(AstNode $node, bool $ordered, int $indent): array
    {
        $lines = [];
        $start = (int) $node->attr('start', 1);
        $index = 0;

        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }

            $marker = $ordered ? $this->orderedListMarker($node, $start + $index) : $this->bulletListMarker();
            array_push($lines, ...$this->renderListItem($item, $marker, $indent));
            $index++;
        }

        return $lines;
    }

    private function orderedListMarker(AstNode $node, int $number): string
    {
        $style = (string) $node->attr('style', 'decimal');
        $delimiter = (string) $node->attr('delimiter', 'period');
        $label = match ($style) {
            'lower_alpha' => $this->alphaListLabel(max(1, $number), false),
            'upper_alpha' => $this->alphaListLabel(max(1, $number), true),
            'lower_roman' => strtolower($this->romanNumeral(max(1, $number))),
            'upper_roman' => $this->romanNumeral(max(1, $number)),
            default => (string) max(0, $number),
        };

        $marker = match ($delimiter) {
            'one_paren' => $label . ')',
            'two_parens' => '(' . $label . ')',
            default => $label . '.',
        };

        if (strlen($marker) < 3) {
            $marker .= str_repeat(' ', 3 - strlen($marker));
        }

        return $marker . ' ';
    }

    private function bulletListMarker(): string
    {
        return match ((string) ($this->options['bulletListMarker'] ?? 'dash')) {
            'plus' => '+ ',
            'star' => '* ',
            default => '- ',
        };
    }

    private function alphaListLabel(int $number, bool $upper): string
    {
        $number = max(1, $number);
        $label = '';
        while ($number > 0) {
            $number--;
            $label = chr(ord('a') + ($number % 26)) . $label;
            $number = intdiv($number, 26);
        }

        return $upper ? strtoupper($label) : $label;
    }

    private function romanNumeral(int $number): string
    {
        $number = max(1, $number);
        if ($number >= 4000) {
            return '?';
        }

        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];
        $roman = '';
        foreach ($map as $value => $glyph) {
            while ($number >= $value) {
                $roman .= $glyph;
                $number -= $value;
            }
        }

        return $roman;
    }

    /**
     * @return list<string>
     */
    private function renderListItem(AstNode $item, string $marker, int $indent): array
    {
        $prefix = str_repeat(' ', $indent) . $marker;
        $continuationIndent = $indent + strlen($marker);
        $task = $item->attr('taskChecked', null);
        if (is_bool($task)) {
            $prefix .= $task ? '[x] ' : '[ ] ';
            $continuationIndent += 4;
        }

        $inlineChildren = [];
        $lines = [];
        $hasFirstLine = false;

        foreach ($item->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlineChildren[] = $child;
                continue;
            }

            if ($inlineChildren !== [] || !$hasFirstLine) {
                $lines[] = rtrim($prefix . $this->renderInlines($inlineChildren));
                $inlineChildren = [];
                $hasFirstLine = true;
            }

            if ($child->type === 'paragraph') {
                if (count($lines) === 1 && rtrim($lines[0]) === rtrim($prefix)) {
                    $lines = [];
                    $lines = $this->appendInlineListItemLines(
                        $lines,
                        $prefix,
                        $continuationIndent,
                        $this->renderInlines($child->children)
                    );
                    continue;
                }

                if ($lines !== [] && end($lines) !== '') {
                    $lines[] = '';
                }
                foreach (explode("\n", $this->renderInlines($child->children)) as $line) {
                    $lines[] = str_repeat(' ', $continuationIndent) . $line;
                }
                continue;
            }

            foreach ($this->renderBlock($child, $indent + 2) as $nestedLine) {
                $lines[] = $nestedLine;
            }
        }

        if ($inlineChildren !== [] || !$hasFirstLine) {
            $lines = $this->appendInlineListItemLines(
                $lines,
                $prefix,
                $continuationIndent,
                $this->renderInlines($inlineChildren)
            );
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function appendInlineListItemLines(array $lines, string $prefix, int $continuationIndent, string $markdown): array
    {
        $inlineLines = explode("\n", $markdown);
        $first = array_shift($inlineLines);

        $lines[] = rtrim($prefix . (string) $first);
        foreach ($inlineLines as $line) {
            $lines[] = str_repeat(' ', $continuationIndent) . $line;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderTable(AstNode $node, int $indent): array
    {
        $headRows = [];
        $bodyRows = [];
        foreach ($node->children as $child) {
            if ($child->type === 'table_head') {
                foreach ($child->children as $row) {
                    if ($row->type === 'table_row') {
                        $headRows[] = $row;
                    }
                }
                continue;
            }

            if ($child->type === 'table_body') {
                $headRows = array_merge($headRows, $this->tableBodyHeadRows($child));
                foreach ($child->children as $row) {
                    if ($row->type === 'table_row') {
                        $bodyRows[] = $row;
                    }
                }
                continue;
            }

            if ($child->type === 'table_foot') {
                foreach ($child->children as $row) {
                    if ($row->type === 'table_row') {
                        $bodyRows[] = $row;
                    }
                }
            }
        }

        if ($headRows === [] && $bodyRows === []) {
            return [];
        }

        $columnCount = $this->tableColumnCount($headRows, $bodyRows);
        if ($columnCount === 0) {
            return [];
        }

        if ($headRows === []) {
            $headRows[] = new AstNode('table_row', ['header' => true], array_fill(0, $columnCount, new AstNode('table_cell')));
        }

        $expandedRows = $this->expandTableRows([...$headRows, ...$bodyRows], $columnCount);
        $expandedHeadRows = array_slice($expandedRows, 0, count($headRows));
        $expandedBodyRows = array_slice($expandedRows, count($headRows));
        $renderedRows = [...$expandedHeadRows, ...$expandedBodyRows];
        $widths = $this->tableColumnWidths($renderedRows, $node->attr('widths', []), $columnCount);
        $alignments = $this->tableAlignments($node, $columnCount);
        $prefix = str_repeat(' ', $indent);
        $lines = [];

        foreach ($expandedHeadRows as $row) {
            $lines[] = $prefix . $this->renderPipeTableRow($row, $widths, $alignments);
        }
        $lines[] = $prefix . $this->renderPipeTableDelimiter($widths, $alignments);
        foreach ($expandedBodyRows as $row) {
            $lines[] = $prefix . $this->renderPipeTableRow($row, $widths, $alignments);
        }

        $caption = $this->renderTableCaption($node);
        if ($caption !== '') {
            $lines[] = '';
            $lines[] = $prefix . ': ' . $caption;
        }

        return $lines;
    }

    /**
     * @return list<AstNode>
     */
    private function tableBodyHeadRows(AstNode $body): array
    {
        $rows = $body->attr('headRows', []);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static fn (mixed $row): bool => $row instanceof AstNode && $row->type === 'table_row'));
    }

    /**
     * @param list<AstNode> $headRows
     * @param list<AstNode> $bodyRows
     */
    private function tableColumnCount(array $headRows, array $bodyRows): int
    {
        $count = 0;
        foreach ([...$headRows, ...$bodyRows] as $row) {
            $rowColumns = 0;
            foreach ($row->children as $cell) {
                $rowColumns += max(1, (int) $cell->attr('colspan', 1));
            }
            $count = max($count, $rowColumns);
        }

        return $count;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<list<string>>
     */
    private function expandTableRows(array $rows, int $columnCount): array
    {
        $expandedRows = [];
        $rowspans = array_fill(0, $columnCount, 0);

        foreach ($rows as $row) {
            $cells = [];
            $column = 0;

            foreach ($row->children as $cell) {
                while ($column < $columnCount && $rowspans[$column] > 0) {
                    $cells[] = '';
                    $rowspans[$column]--;
                    $column++;
                }

                if ($column >= $columnCount) {
                    break;
                }

                $colspan = max(1, min($columnCount - $column, (int) $cell->attr('colspan', 1)));
                $rowspan = max(1, (int) $cell->attr('rowspan', 1));
                $cells[] = $this->renderTableCell($cell);
                if ($rowspan > 1) {
                    $rowspans[$column] = max($rowspans[$column], $rowspan - 1);
                }
                $column++;

                for ($covered = 1; $covered < $colspan && $column < $columnCount; $covered++, $column++) {
                    $cells[] = '';
                    if ($rowspan > 1) {
                        $rowspans[$column] = max($rowspans[$column], $rowspan - 1);
                    }
                }
            }

            while ($column < $columnCount) {
                if ($rowspans[$column] > 0) {
                    $rowspans[$column]--;
                }
                $cells[] = '';
                $column++;
            }

            $expandedRows[] = $cells;
        }

        return $expandedRows;
    }

    private function renderTableCell(AstNode $cell): string
    {
        if ($cell->children === []) {
            return $this->escapeText((string) $cell->attr('text', ''));
        }

        $hasOnlyInlines = true;
        foreach ($cell->children as $child) {
            if (!$this->isInlineNode($child)) {
                $hasOnlyInlines = false;
                break;
            }
        }

        $markdown = $hasOnlyInlines ? $this->renderInlines($cell->children) : $this->renderBlockCollection($cell->children);
        if (!$hasOnlyInlines) {
            $markdown = $this->escapeTableCellPipes($markdown);
        }
        $markdown = str_replace("\\\r\n", "<br />", $markdown);
        $markdown = str_replace("\\\n", "<br />", $markdown);

        return str_replace(["\r\n", "\r", "\n"], [' ', ' ', '<br />'], trim($markdown));
    }

    private function escapeTableCellPipes(string $markdown): string
    {
        return preg_replace('/(?<!\\\\)\|/', '\\\\|', $markdown) ?? $markdown;
    }

    /**
     * @param list<list<string>> $rows
     * @param mixed $relativeWidths
     * @return list<int>
     */
    private function tableColumnWidths(array $rows, mixed $relativeWidths, int $columnCount): array
    {
        $widths = array_fill(0, $columnCount, 3);
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index], strlen($cell));
            }
        }

        if (is_array($relativeWidths)) {
            foreach (array_values($relativeWidths) as $index => $width) {
                if ($index < $columnCount && is_numeric($width) && (float) $width > 0.0) {
                    $widths[$index] = max($widths[$index], (int) ceil((float) $width * 40));
                }
            }
        }

        return $widths;
    }

    /**
     * @return list<string>
     */
    private function tableAlignments(AstNode $node, int $columnCount): array
    {
        $alignments = $node->attr('alignments', []);
        if (!is_array($alignments)) {
            $alignments = [];
        }

        $normalized = [];
        for ($index = 0; $index < $columnCount; $index++) {
            $alignment = (string) ($alignments[$index] ?? 'default');
            $normalized[] = in_array($alignment, ['left', 'right', 'center'], true) ? $alignment : 'default';
        }

        return $normalized;
    }

    /**
     * @param list<string> $cells
     * @param list<int> $widths
     * @param list<string> $alignments
     */
    private function renderPipeTableRow(array $cells, array $widths, array $alignments): string
    {
        $parts = [];
        foreach ($cells as $index => $cell) {
            $parts[] = ' ' . $this->padTableCell($cell, $widths[$index], $alignments[$index]) . ' ';
        }

        return '|' . implode('|', $parts) . '|';
    }

    private function padTableCell(string $cell, int $width, string $alignment): string
    {
        $padding = max(0, $width - strlen($cell));

        return match ($alignment) {
            'right' => str_repeat(' ', $padding) . $cell,
            'center' => str_repeat(' ', intdiv($padding, 2)) . $cell . str_repeat(' ', $padding - intdiv($padding, 2)),
            default => $cell . str_repeat(' ', $padding),
        };
    }

    /**
     * @param list<int> $widths
     * @param list<string> $alignments
     */
    private function renderPipeTableDelimiter(array $widths, array $alignments): string
    {
        $parts = [];
        foreach ($widths as $index => $width) {
            $dashCount = max(3, $width);
            $parts[] = match ($alignments[$index]) {
                'left' => ':' . str_repeat('-', $dashCount - 1),
                'right' => str_repeat('-', $dashCount - 1) . ':',
                'center' => ':' . str_repeat('-', max(1, $dashCount - 2)) . ':',
                default => str_repeat('-', $dashCount),
            };
        }

        return '|' . implode('|', $parts) . '|';
    }

    private function renderTableCaption(AstNode $node): string
    {
        $captionInlines = $node->attr('captionInlines', []);
        $caption = '';
        if (is_array($captionInlines) && $captionInlines !== []) {
            $caption = $this->renderInlines($captionInlines);
        } else {
            $caption = $this->escapeText((string) $node->attr('caption', ''));
        }

        $shortCaptionInlines = $node->attr('shortCaptionInlines', []);
        if (is_array($shortCaptionInlines) && $shortCaptionInlines !== []) {
            $shortCaption = '[' . $this->renderInlines($shortCaptionInlines) . ']';

            return $caption === '' ? $shortCaption : $shortCaption . ' ' . $caption;
        }

        $shortCaption = (string) $node->attr('shortCaption', '');
        if ($shortCaption !== '') {
            $shortCaption = '[' . $this->escapeText($shortCaption) . ']';

            return $caption === '' ? $shortCaption : $shortCaption . ' ' . $caption;
        }

        return $caption;
    }

    /**
     * @return list<string>
     */
    private function renderBlockQuote(AstNode $node, int $indent): array
    {
        $body = $this->renderBlockCollection($node->children);
        $prefix = str_repeat(' ', $indent) . '>';
        if ($body === '') {
            return [$prefix];
        }

        $lines = [];
        foreach (explode("\n", $body) as $line) {
            $lines[] = $line === '' ? $prefix : $prefix . ' ' . $line;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderCodeBlock(AstNode $node, int $indent): array
    {
        $attrs = $this->renderLinkAttributes($node);
        if ($attrs !== '') {
            return $this->renderFencedCodeBlock($node, $attrs, $indent);
        }

        $lines = [];
        $prefix = str_repeat(' ', $indent + 4);
        foreach (explode("\n", (string) $node->attr('text', '')) as $line) {
            $lines[] = $prefix . $line;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderFencedCodeBlock(AstNode $node, string $attrs, int $indent): array
    {
        $prefix = str_repeat(' ', $indent);
        $text = (string) $node->attr('text', '');
        $fence = str_repeat('`', max(3, $this->longestBacktickRun($text) + 1));

        return [
            $prefix . $fence . $attrs,
            ...array_map(static fn (string $line): string => $prefix . $line, explode("\n", $text)),
            $prefix . $fence,
        ];
    }

    /**
     * @return list<string>
     */
    private function renderDivBlock(AstNode $node, int $indent): array
    {
        $attrs = $this->renderLinkAttributes($node);
        $prefix = str_repeat(' ', $indent);
        $body = $this->renderBlockCollection($node->children);
        $fenceLength = max(3, $this->longestColonRun($body) + 1);
        $fence = str_repeat(':', $fenceLength);
        $opening = rtrim($prefix . $fence . ($attrs === '' ? '' : ' ' . $attrs));
        $closing = $prefix . $fence;

        if ($body === '') {
            return [$opening, $closing];
        }

        return [
            $opening,
            ...array_map(static fn (string $line): string => $prefix . $line, explode("\n", $body)),
            $closing,
        ];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $index => $node) {
            $text .= $this->renderInline($node, array_slice($nodes, $index + 1));
        }

        return $text;
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderInline(AstNode $node, array $following = []): string
    {
        return match ($node->type) {
            'text' => $this->escapeText((string) $node->attr('text', '')),
            'space' => ' ',
            'softbreak' => $this->softBreakMarkdown(),
            'linebreak' => "\\\n",
            'code' => $this->renderCode($node),
            'emph' => $this->delimitInlineContent('*', '*', $this->renderInlines($node->children)),
            'strong' => $this->delimitInlineContent('**', '**', $this->renderInlines($node->children)),
            'strikeout' => $this->delimitInlineContent('~~', '~~', $this->renderInlines($node->children)),
            'superscript' => $this->delimitScriptContent('^', $this->renderInlines($node->children)),
            'subscript' => $this->delimitScriptContent('~', $this->renderInlines($node->children)),
            'small_caps' => $this->renderSmallCaps($node),
            'underline' => $this->renderUnderline($node),
            'span' => $this->renderSpan($node),
            'quoted' => $this->renderQuoted($node),
            'link' => $this->renderLink($node, $following),
            'image' => $this->renderImage($node, $following),
            'math' => $this->renderMath($node),
            'citation' => (string) $node->attr('rendered', $node->attr('text', $this->renderInlines($node->children))),
            'citation_group' => (string) $node->attr('rendered', $node->attr('text', $this->renderInlines($node->children))),
            'raw_tex' => (string) $node->attr('tex', $node->attr('text', '')),
            'raw_inline', 'raw_markdown', 'raw_html_inline' => $this->renderRawInline($node),
            'note' => $this->renderNoteReference($node),
            default => $this->renderInlines($node->children),
        };
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderLink(AstNode $node, array $following): string
    {
        if ($this->canRenderAutolink($node)) {
            return '<' . $this->autolinkText($node) . '>';
        }

        if ((bool) ($this->options['referenceLinks'] ?? false)) {
            return $this->renderReferenceLink($node, $following);
        }

        $title = (string) $node->attr('title', '');
        $titleMarkdown = $title === '' ? '' : ' "' . $this->escapeLinkTitle($title) . '"';

        return '[' . $this->renderInlines($node->children) . ']('
            . $this->renderLinkDestination((string) $node->attr('url', ''))
            . $titleMarkdown
            . ')'
            . $this->renderLinkAttributes($node);
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderImage(AstNode $node, array $following): string
    {
        return '!' . $this->renderLink(
            new AstNode('link', $this->imageLinkAttrs($node), $this->imageLabelNodesForLink($node)),
            $following
        );
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderReferenceLink(AstNode $node, array $following): string
    {
        $labelText = $this->renderInlines($node->children);
        $plainLabel = $this->normalizeReferenceLabelText($this->plainInlineText($node->children));
        $referenceLabel = $this->registerReference(
            $plainLabel,
            (string) $node->attr('url', ''),
            (string) $node->attr('title', ''),
            $this->linkAttrTuple($node)
        );

        $shortcutable = $referenceLabel === $plainLabel && $this->canUseShortcutReference($following);
        if ($shortcutable) {
            return '[' . $labelText . ']';
        }

        $suffix = $referenceLabel === $plainLabel ? '[]' : '[' . $referenceLabel . ']';

        return '[' . $labelText . ']' . $suffix;
    }

    private function renderNoteReference(AstNode $node): string
    {
        $number = $this->nextNoteNumber++;
        $this->notes[] = [
            'number' => $number,
            'node' => $node,
        ];

        return '[^' . $number . ']';
    }

    private function renderCode(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');
        $delimiter = str_repeat('`', max(1, $this->longestBacktickRun($text) + 1));
        if (str_contains($text, '`') || str_starts_with($text, ' ') || str_ends_with($text, ' ')) {
            $text = ' ' . $text . ' ';
        }

        return $delimiter . $text . $delimiter . $this->renderLinkAttributes($node);
    }

    private function renderSpan(AstNode $node): string
    {
        $content = $this->renderInlines($node->children);
        $attrs = $this->renderLinkAttributes($node);

        return $attrs === '' ? $content : '[' . $content . ']' . $attrs;
    }

    private function renderSmallCaps(AstNode $node): string
    {
        $attrs = $this->linkAttrTuple($node);
        array_unshift($attrs['classes'], 'smallcaps');

        return '[' . $this->renderInlines($node->children) . ']' . $this->renderAttributesTuple($attrs);
    }

    private function renderUnderline(AstNode $node): string
    {
        $attrs = $this->linkAttrTuple($node);
        array_unshift($attrs['classes'], 'underline');

        return '[' . $this->renderInlines($node->children) . ']' . $this->renderAttributesTuple($attrs);
    }

    private function renderQuoted(AstNode $node): string
    {
        if ((string) $node->attr('kind', 'double') === 'single') {
            return "\u{2018}" . $this->renderInlines($node->children) . "\u{2019}";
        }

        return "\u{201C}" . $this->renderInlines($node->children) . "\u{201D}";
    }

    private function renderMath(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');
        if ($node->attr('display') === true) {
            return '$$' . $text . '$$';
        }

        return '$' . $text . '$';
    }

    private function softBreakMarkdown(): string
    {
        return (string) ($this->options['softBreak'] ?? 'preserve') === 'space' ? ' ' : "\n";
    }

    /**
     * @return list<string>
     */
    private function renderRawBlock(AstNode $node, int $indent): array
    {
        $format = strtolower((string) $node->attr('format', ''));
        if ($node->type === 'raw_markdown' || $this->isMarkdownRawFormat($format)) {
            $text = (string) $node->attr('text', $node->attr('markdown', ''));
        } elseif ($node->type === 'raw_tex' || in_array($format, ['tex', 'latex', 'context'], true)) {
            $text = (string) $node->attr('text', $node->attr('tex', ''));
        } else {
            return [];
        }

        return array_map(
            static fn (string $line): string => str_repeat(' ', $indent) . $line,
            explode("\n", $text)
        );
    }

    private function renderRawInline(AstNode $node): string
    {
        $format = strtolower((string) $node->attr('format', ''));
        if ($node->type === 'raw_markdown' || $this->isMarkdownRawFormat($format)) {
            return (string) $node->attr('text', $node->attr('markdown', ''));
        }

        if (in_array($format, ['tex', 'latex', 'context'], true)) {
            return (string) $node->attr('text', $node->attr('tex', ''));
        }

        return '';
    }

    private function isMarkdownRawFormat(string $format): bool
    {
        $baseFormat = str_replace('-', '+', $format);
        $baseFormat = explode('+', $baseFormat, 2)[0];

        return in_array($format, [
            'markdown',
            'markdown_strict',
            'markdown_phpextra',
            'markdown_mmd',
            'pandoc',
            'commonmark',
            'commonmark_x',
            'gfm',
        ], true) || in_array($baseFormat, [
            'markdown',
            'markdown_strict',
            'markdown_phpextra',
            'markdown_mmd',
            'pandoc',
            'commonmark',
            'commonmark_x',
            'gfm',
        ], true);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function registerReference(string $suggestedLabel, string $url, string $title, array $attrs): string
    {
        $targetKey = $url . "\0" . $title . "\0" . $this->attributeSignature($attrs);
        if (isset($this->referenceTargetLabels[$targetKey])) {
            return $this->referenceTargetLabels[$targetKey];
        }

        $label = $this->normalizeReferenceLabelText($suggestedLabel);
        if ($this->requiresGeneratedReferenceLabel($label)) {
            $actualLabel = $this->nextGeneratedReferenceLabel();
        } else {
            $key = strtolower($label);
            $use = $this->referenceLabelUses[$key] ?? 0;
            $this->referenceLabelUses[$key] = $use + 1;
            $actualLabel = $use === 0 && !isset($this->referenceUsedLabels[$key])
                ? $label
                : $this->nextGeneratedReferenceLabel();
        }

        $this->referenceUsedLabels[strtolower($actualLabel)] = true;
        $this->referenceTargetLabels[$targetKey] = $actualLabel;
        $this->references[] = [
            'label' => $actualLabel,
            'url' => $url,
            'title' => $title,
            'attrs' => $attrs,
        ];

        return $actualLabel;
    }

    private function requiresGeneratedReferenceLabel(string $label): bool
    {
        return $label === ''
            || strlen($label) > 999
            || str_contains($label, '[')
            || str_contains($label, ']');
    }

    private function nextGeneratedReferenceLabel(): string
    {
        do {
            $this->lastReferenceIndex++;
            $candidate = (string) $this->lastReferenceIndex;
        } while (isset($this->referenceUsedLabels[strtolower($candidate)]));

        return $candidate;
    }

    /**
     * @param list<AstNode> $following
     */
    private function canUseShortcutReference(array $following): bool
    {
        $next = $following[0] ?? null;
        if ($next === null) {
            return true;
        }

        if ($next->type === 'link' || $next->type === 'citation' || $next->type === 'citation_group') {
            return false;
        }

        if ($next->type === 'softbreak' || $next->type === 'linebreak') {
            return $this->canUseShortcutReferenceAfterWhitespace(array_slice($following, 1));
        }

        if ($next->type === 'raw_inline' || $next->type === 'raw_markdown' || $next->type === 'raw_html_inline') {
            return !$this->startsWithReferenceSuffixConflict((string) $next->attr(
                'text',
                $next->attr('markdown', $next->attr('html', ''))
            ));
        }

        if ($next->type !== 'text') {
            return true;
        }

        $text = (string) $next->attr('text', '');
        if ($text === '') {
            return $this->canUseShortcutReference(array_slice($following, 1));
        }

        if ($this->startsWithReferenceSuffixConflict($text)) {
            return false;
        }

        $withoutLeadingSpace = ltrim($text, " \t\r\n");
        if ($withoutLeadingSpace !== $text) {
            if ($withoutLeadingSpace !== '') {
                return !str_starts_with($withoutLeadingSpace, '[');
            }

            return $this->canUseShortcutReferenceAfterWhitespace(array_slice($following, 1));
        }

        return true;
    }

    /**
     * @param list<AstNode> $following
     */
    private function canUseShortcutReferenceAfterWhitespace(array $following): bool
    {
        $next = $following[0] ?? null;
        if ($next === null) {
            return true;
        }

        if ($next->type === 'link' || $next->type === 'citation' || $next->type === 'citation_group') {
            return false;
        }

        if ($next->type === 'text') {
            $text = (string) $next->attr('text', '');

            return $text === '' || !str_starts_with(ltrim($text, " \t\r\n"), '[');
        }

        if ($next->type === 'raw_inline' || $next->type === 'raw_markdown' || $next->type === 'raw_html_inline') {
            $raw = (string) $next->attr('text', $next->attr('markdown', $next->attr('html', '')));

            return !str_starts_with(ltrim($raw, " \t\r\n"), '[');
        }

        return true;
    }

    private function startsWithReferenceSuffixConflict(string $text): bool
    {
        return str_starts_with($text, '[')
            || str_starts_with($text, '(')
            || str_starts_with($text, ':')
            || str_starts_with($text, ' [');
    }

    private function delimitInlineContent(string $opener, string $closer, string $content): string
    {
        if ($content === '') {
            return '';
        }

        $leading = '';
        if (preg_match('/^\s+/u', $content, $match) === 1) {
            $leading = $match[0];
            $content = substr($content, strlen($leading));
        }

        $trailing = '';
        if (preg_match('/\s+$/u', $content, $match) === 1) {
            $trailing = $match[0];
            $content = substr($content, 0, strlen($content) - strlen($trailing));
        }

        return $leading . $opener . $content . $closer . $trailing;
    }

    private function delimitScriptContent(string $delimiter, string $content): string
    {
        $delimited = $this->delimitInlineContent($delimiter, $delimiter, str_replace(' ', '\\ ', $content));

        return str_replace("\xC2\xA0", '\\ ', $delimited);
    }

    private function escapeText(string $text): string
    {
        $escaped = '';
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            $tail = substr($text, $i);

            if ($i === 0 && $char === '#' && $this->startsWithAtxHeadingMarker($text)) {
                $escaped .= '\\#';
                continue;
            }

            if ($i === 0 && $this->startsWithListMarker($text)) {
                $escaped .= '\\' . $char;
                continue;
            }

            if ($i === 0 && $char === '@' && isset($text[$i + 1]) && preg_match('/[A-Za-z0-9_{]/', $text[$i + 1]) === 1) {
                $escaped .= '\\@';
                continue;
            }

            if (str_starts_with($tail, '...')) {
                $escaped .= '\\...';
                $i += 2;
                continue;
            }

            if (str_starts_with($tail, '--')) {
                $escaped .= '\\--';
                $i++;
                continue;
            }

            if (str_starts_with($tail, ':::' )) {
                $colonRun = strspn($tail, ':');
                $escaped .= '\\' . str_repeat(':', $colonRun);
                $i += $colonRun - 1;
                continue;
            }

            if (str_starts_with($tail, '![')) {
                $escaped .= '\\![';
                $i++;
                continue;
            }

            if (str_starts_with($tail, '~~')) {
                $escaped .= '\\~~';
                $i++;
                continue;
            }

            if ($char === '&' && preg_match('/^&(?:#[0-9]+|#x[0-9A-Fa-f]+|[A-Za-z][A-Za-z0-9]+);/', $tail) === 1) {
                $escaped .= '\\&';
                continue;
            }

            if ($char === '\\') {
                $escaped .= '\\\\';
                continue;
            }

            if ($char === '_' && $this->isIntrawordUnderscore($text, $i)) {
                $escaped .= '_';
                continue;
            }

            $escaped .= match ($char) {
                '[', ']', '`', '*', '_', '|', '^', '~', '$', '\'', '"' => '\\' . $char,
                '>', '<' => '\\' . $char,
                default => $char,
            };
        }

        return $escaped;
    }

    private function longestColonRun(string $text): int
    {
        if (preg_match_all('/:+/', $text, $matches) !== 1) {
            return 0;
        }

        return max(array_map('strlen', $matches[0]));
    }

    private function longestBacktickRun(string $text): int
    {
        if (preg_match_all('/`+/', $text, $matches) < 1) {
            return 0;
        }

        return max(array_map('strlen', $matches[0]));
    }

    private function startsWithAtxHeadingMarker(string $text): bool
    {
        $offset = strspn($text, '#');

        return $offset > 0 && ($offset === strlen($text) || $text[$offset] === ' ' || $text[$offset] === "\t");
    }

    private function startsWithListMarker(string $text): bool
    {
        return preg_match('/^(?:[0-9]+[.)]|[*+-])(?:[ \t]|$)/', $text) === 1;
    }

    private function isIntrawordUnderscore(string $text, int $offset): bool
    {
        $previous = $text[$offset - 1] ?? '';
        $next = $text[$offset + 1] ?? '';

        return $previous !== ''
            && $next !== ''
            && preg_match('/[A-Za-z0-9]/', $previous) === 1
            && preg_match('/[A-Za-z0-9]/', $next) === 1;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function normalizeReferenceLabelText(string $label): string
    {
        return trim(preg_replace('/\s+/', ' ', $label) ?? $label);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderBlockCollection(array $nodes): string
    {
        $blocks = [];
        foreach ($nodes as $node) {
            $lines = $this->renderBlock($node, 0);
            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param list<string> $blocks
     */
    private function appendPendingDefinitions(array &$blocks): void
    {
        foreach ($this->pendingDefinitionBlocks() as $definitionBlock) {
            if ($definitionBlock !== '') {
                $blocks[] = $definitionBlock;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function pendingDefinitionBlocks(): array
    {
        $blocks = [];
        while ($this->notes !== [] || $this->references !== []) {
            $notes = $this->notes;
            $references = $this->references;
            $this->notes = [];
            $this->references = [];

            foreach ($notes as $note) {
                $blocks[] = $this->renderNoteDefinition($note['number'], $note['node']);
            }

            $referenceDefinitions = [];
            foreach ($references as $reference) {
                $referenceDefinitions[] = $this->renderReferenceDefinition($reference);
            }
            if ($referenceDefinitions !== []) {
                $blocks[] = implode("\n", $referenceDefinitions);
            }
        }

        return $blocks;
    }

    private function renderNoteDefinition(int $number, AstNode $node): string
    {
        $body = $this->renderBlockCollection($node->children);
        if ($body === '') {
            return '[^' . $number . ']:';
        }

        $lines = explode("\n", $body);
        $first = array_shift($lines);
        $rendered = '[^' . $number . ']: ' . $first;
        foreach ($lines as $line) {
            $rendered .= "\n" . ($line === '' ? '' : '    ' . $line);
        }

        return $rendered;
    }

    /**
     * @param array{label:string, url:string, title:string, attrs:array<string, mixed>} $reference
     */
    private function renderReferenceDefinition(array $reference): string
    {
        $title = $reference['title'] === ''
            ? ''
            : ' "' . $this->escapeLinkTitle($reference['title']) . '"';
        $attrs = $this->renderAttributesTuple($reference['attrs']);

        return '  [' . $reference['label'] . ']: '
            . $this->renderLinkDestination($reference['url'])
            . $title
            . ($attrs === '' ? '' : ' ' . $attrs);
    }

    private function canRenderAutolink(AstNode $node): bool
    {
        $url = (string) $node->attr('url', '');
        if (!$this->isUriLike($url)) {
            return false;
        }

        $attrs = $this->linkAttrTuple($node);
        $classes = $attrs['classes'];
        if (
            $attrs['id'] !== ''
            || $attrs['attributes'] !== []
            || ($classes !== [] && $classes !== ['uri'] && $classes !== ['email'])
        ) {
            return false;
        }

        if (count($node->children) !== 1 || $node->children[0]->type !== 'text') {
            return false;
        }

        $label = (string) $node->children[0]->attr('text', '');
        $suffix = $this->autolinkText($node);

        return $label === $suffix || $this->escapeUri($label) === $suffix;
    }

    private function autolinkText(AstNode $node): string
    {
        $url = (string) $node->attr('url', '');

        return str_starts_with($url, 'mailto:') ? substr($url, 7) : $url;
    }

    /**
     * @return list<AstNode>
     */
    private function imageLabelNodesForLink(AstNode $node): array
    {
        $labelNodes = $node->children;
        if ($labelNodes === []) {
            $alt = (string) $node->attr('alt', '');
            if ($alt !== '') {
                $labelNodes = [new AstNode('text', ['text' => $alt])];
            }
        }

        $url = (string) $node->attr('url', '');
        if ($labelNodes === [] || (count($labelNodes) === 1 && $labelNodes[0]->type === 'text' && $labelNodes[0]->attr('text', '') === $url)) {
            return [new AstNode('text', ['text' => ''])];
        }

        return $labelNodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function imageLinkAttrs(AstNode $node): array
    {
        $attrs = [
            'url' => (string) $node->attr('url', ''),
            'title' => (string) $node->attr('title', ''),
        ];

        foreach (['id', 'classes', 'attributes'] as $name) {
            if (array_key_exists($name, $node->attrs)) {
                $attrs[$name] = $node->attrs[$name];
            }
        }

        $alt = (string) $node->attr('alt', '');
        if ($alt !== '') {
            $labelText = $this->plainInlineText($this->imageLabelNodesForLink($node));
            $attributes = $attrs['attributes'] ?? [];
            if (!is_array($attributes)) {
                $attributes = [];
            }
            if ($labelText !== '' && $labelText !== $alt && !array_key_exists('alt', $attributes)) {
                $attrs['attributes'] = ['alt' => $alt] + $attributes;
            }
        }

        return $attrs;
    }

    private function imageWithFigureAttrs(AstNode $figure, AstNode $image): AstNode
    {
        $attrs = $image->attrs;

        foreach (['id', 'classes'] as $name) {
            if (!array_key_exists($name, $attrs) && array_key_exists($name, $figure->attrs)) {
                $attrs[$name] = $figure->attrs[$name];
            }
        }

        $imageAttributes = $attrs['attributes'] ?? [];
        if (!is_array($imageAttributes)) {
            $imageAttributes = [];
        }

        $figureAttributes = $figure->attr('attributes', []);
        if (is_array($figureAttributes) && $figureAttributes !== []) {
            $attrs['attributes'] = $imageAttributes + $figureAttributes;
        } elseif ($imageAttributes !== []) {
            $attrs['attributes'] = $imageAttributes;
        }

        $caption = (string) $figure->attr('caption', '');
        if ($caption !== '' && $image->children === []) {
            return new AstNode('image', $attrs, [new AstNode('text', ['text' => $caption])]);
        }

        return new AstNode('image', $attrs, $image->children);
    }

    private function isUriLike(string $url): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $url) === 1;
    }

    private function escapeUri(string $url): string
    {
        return preg_replace_callback(
            '/[^A-Za-z0-9\\-._~:\\/?#\\[\\]@!$&\'()*+,;=%]/u',
            static fn (array $match): string => implode('', array_map(
                static fn (string $byte): string => sprintf('%%%02X', ord($byte)),
                str_split($match[0])
            )),
            $url
        ) ?? $url;
    }

    private function renderLinkDestination(string $url): string
    {
        if (!$this->linkDestinationNeedsAngles($url)) {
            return $url;
        }

        return '<' . str_replace(['\\', '<', '>'], ['\\\\', '\\<', '\\>'], $url) . '>';
    }

    private function linkDestinationNeedsAngles(string $url): bool
    {
        return $url === ''
            || preg_match('/[\s\x00-\x1F\x7F<>()]/u', $url) === 1;
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function linkAttrTuple(AstNode $node): array
    {
        $id = (string) $node->attr('id', '');
        $classes = $node->attr('classes', []);
        if (!is_array($classes)) {
            $classes = [];
        }
        $classes = array_values(array_filter(
            array_map(static fn (mixed $class): string => (string) $class, $classes),
            static fn (string $class): bool => $class !== ''
        ));

        $attributes = $node->attr('attributes', []);
        if (!is_array($attributes)) {
            $attributes = [];
        }
        $attributes = array_filter(
            array_map(static fn (mixed $value): string => (string) $value, $attributes),
            static fn (string $value): bool => $value !== ''
        );

        return [
            'id' => $id,
            'classes' => $classes,
            'attributes' => $attributes,
        ];
    }

    private function renderLinkAttributes(AstNode $node): string
    {
        return $this->renderAttributesTuple($this->linkAttrTuple($node));
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderAttributesTuple(array $attrs): string
    {
        $parts = [];
        if ($attrs['id'] !== '') {
            $parts[] = '#' . $this->escapeAttributeToken($attrs['id']);
        }
        foreach ($attrs['classes'] as $class) {
            $parts[] = '.' . $this->escapeAttributeToken($class);
        }
        foreach ($attrs['attributes'] as $name => $value) {
            $parts[] = $this->escapeAttributeToken((string) $name)
                . '="'
                . $this->escapeAttributeToken($value)
                . '"';
        }

        return $parts === [] ? '' : '{' . implode(' ', $parts) . '}';
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function attributeSignature(array $attrs): string
    {
        return json_encode($attrs, JSON_THROW_ON_ERROR);
    }

    private function escapeAttributeToken(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function escapeLinkTitle(string $title): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $title);
    }

    private function referenceLocation(): string
    {
        $location = (string) ($this->options['referenceLocation'] ?? 'end_of_document');

        return in_array($location, ['end_of_document', 'end_of_block', 'end_of_section'], true)
            ? $location
            : 'end_of_document';
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'space',
            'emph',
            'strong',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'underline',
            'span',
            'quoted',
            'softbreak',
            'linebreak',
            'code',
            'link',
            'image',
            'math',
            'citation',
            'citation_group',
            'raw_tex',
            'raw_inline',
            'raw_markdown',
            'raw_html_inline',
            'note',
        ], true);
    }

    private function isListBlock(AstNode $node): bool
    {
        return $node->type === 'bullet_list' || $node->type === 'ordered_list' || $node->type === 'definition_list';
    }
}
