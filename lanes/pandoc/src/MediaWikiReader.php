<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MediaWikiReader
{
    /** @var list<string> */
    private array $lines = [];

    private int $index = 0;

    public function read(string $text): AstNode
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $this->lines = explode("\n", $normalized);
        $this->index = 0;

        return new AstNode('document', [
            'sourceFormat' => 'mediawiki',
            'mediawiki' => [
                'reader' => self::class,
                'readerScope' => 'bounded-mediawiki-reader-core-block-inline-semantics',
                'sourceBytes' => strlen($text),
                'upstreamEvidence' => [
                    'source' => 'Pandoc MediaWiki reader behavior probes and documented MediaWiki core syntax',
                    'readerUnitGroups' => [
                        'headings',
                        'paragraphs',
                        'bold italic and code-like inline markup',
                        'internal and external links',
                        'images',
                        'bullet ordered and definition lists',
                        'preformatted blocks',
                        'simple tables',
                    ],
                    'fixtureStatus' => 'Initial native PHP reader slice; full template, parser-function, transclusion, role, and table attribute parity remains open.',
                ],
            ],
        ], $this->parseBlocks());
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlocks(): array
    {
        $blocks = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            $trimmed = trim($line);
            if ($trimmed === '') {
                ++$this->index;
                continue;
            }

            if (str_starts_with($trimmed, '{|')) {
                $blocks[] = $this->parseTable();
                continue;
            }
            if (preg_match('/^={1,6}\\s*.*?\\s*={1,6}$/u', $trimmed) === 1) {
                $blocks[] = $this->parseHeading($trimmed);
                continue;
            }
            if ($trimmed === '----') {
                ++$this->index;
                $blocks[] = new AstNode('horizontal_rule');
                continue;
            }
            if (str_starts_with($line, ' ')) {
                $blocks[] = $this->parsePreBlock();
                continue;
            }
            if ($this->isListLine($trimmed)) {
                $blocks[] = $this->parseListBlock();
                continue;
            }
            if ($this->isDefinitionListLine($trimmed)) {
                $blocks[] = $this->parseDefinitionList();
                continue;
            }

            $blocks[] = $this->parseParagraph();
        }

        return $blocks;
    }

    private function parseHeading(string $trimmed): AstNode
    {
        ++$this->index;
        if (preg_match('/^(={1,6})\\s*(.*?)\\s*\\1$/u', $trimmed, $match) === 1) {
            $level = strlen($match[1]);
            $inlines = $this->parseInlines($match[2]);

            return new AstNode('heading', [
                'level' => $level,
                'text' => $this->plainInlineText($inlines),
            ], $inlines);
        }

        return $this->paragraphFromText($trimmed);
    }

    private function parseParagraph(): AstNode
    {
        $parts = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            $trimmed = trim($line);
            if ($trimmed === '' || ($parts !== [] && $this->isBlockStart($line))) {
                break;
            }
            $parts[] = $trimmed;
            ++$this->index;
        }

        return $this->paragraphFromText(implode(' ', $parts));
    }

    private function isBlockStart(string $line): bool
    {
        $trimmed = trim($line);

        return str_starts_with($trimmed, '{|')
            || preg_match('/^={1,6}\\s*.*?\\s*={1,6}$/u', $trimmed) === 1
            || $trimmed === '----'
            || str_starts_with($line, ' ')
            || $this->isListLine($trimmed)
            || $this->isDefinitionListLine($trimmed);
    }

    private function parsePreBlock(): AstNode
    {
        $body = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            if (!str_starts_with($line, ' ')) {
                break;
            }
            $body[] = substr($line, 1);
            ++$this->index;
        }

        return new AstNode('code_block', [
            'text' => implode("\n", $body),
            'classes' => [],
        ]);
    }

    private function isListLine(string $trimmed): bool
    {
        return preg_match('/^[*#]+\\s*\\S/u', $trimmed) === 1;
    }

    private function parseListBlock(): AstNode
    {
        $entries = [];
        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            if (preg_match('/^([*#]+)\\s*(.*)$/u', $line, $match) !== 1) {
                break;
            }
            $entries[] = [
                'marker' => $match[1],
                'text' => trim($match[2]),
            ];
            ++$this->index;
        }

        $cursor = 0;
        $style = str_starts_with($entries[0]['marker'] ?? '*', '#') ? 'ordered' : 'bullet';

        return $this->buildListAt($entries, $cursor, 1, $style);
    }

    /**
     * @param list<array{marker:string, text:string}> $entries
     */
    private function buildListAt(array $entries, int &$cursor, int $level, string $style): AstNode
    {
        $items = [];
        while ($cursor < count($entries)) {
            $entry = $entries[$cursor];
            $entryLevel = strlen($entry['marker']);
            $entryStyle = str_ends_with($entry['marker'], '#') ? 'ordered' : 'bullet';
            if ($entryLevel < $level) {
                break;
            }
            if ($entryLevel > $level) {
                if ($items === []) {
                    ++$cursor;
                    continue;
                }
                $last = array_pop($items);
                $items[] = new AstNode('list_item', ['loose' => false], array_merge(
                    $last->children,
                    [$this->buildListAt($entries, $cursor, $level + 1, $entryStyle)]
                ));
                continue;
            }
            if ($entryStyle !== $style) {
                break;
            }

            $inlines = $this->parseInlines($entry['text']);
            $items[] = new AstNode('list_item', ['loose' => false], [
                new AstNode('plain', ['text' => $this->plainInlineText($inlines)], $inlines),
            ]);
            ++$cursor;
        }

        if ($style === 'ordered') {
            return new AstNode('ordered_list', [
                'start' => 1,
                'style' => 'default',
                'delimiter' => 'default',
                'loose' => false,
            ], $items);
        }

        return new AstNode('bullet_list', ['loose' => false], $items);
    }

    private function isDefinitionListLine(string $trimmed): bool
    {
        return preg_match('/^[;:]\\s*\\S/u', $trimmed) === 1;
    }

    private function parseDefinitionList(): AstNode
    {
        $items = [];
        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            if (!$this->isDefinitionListLine($line)) {
                break;
            }
            if (str_starts_with($line, ';')) {
                [$term, $definition] = $this->splitDefinitionTerm(substr($line, 1));
                ++$this->index;
                if ($definition === '' && isset($this->lines[$this->index]) && preg_match('/^:\\s*(.*)$/u', trim($this->lines[$this->index]), $match) === 1) {
                    $definition = trim($match[1]);
                    ++$this->index;
                }
                $items[] = $this->definitionItem(trim($term), trim($definition));
                continue;
            }

            preg_match('/^:\\s*(.*)$/u', $line, $match);
            ++$this->index;
            $items[] = $this->definitionItem('', trim((string) ($match[1] ?? '')));
        }

        return new AstNode('definition_list', [], $items);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitDefinitionTerm(string $source): array
    {
        if (preg_match('/^(.*?)\\s+:\\s+(.*)$/u', $source, $match) === 1) {
            return [$match[1], $match[2]];
        }

        return [$source, ''];
    }

    private function definitionItem(string $term, string $definition): AstNode
    {
        $termInlines = $this->parseInlines($term);
        $definitionInlines = $this->parseInlines($definition);

        return new AstNode('definition_item', ['term' => $this->plainInlineText($termInlines)], [
            new AstNode('definition_term', ['text' => $this->plainInlineText($termInlines)], $termInlines),
            new AstNode('definition', [], [
                new AstNode('paragraph', ['text' => $this->plainInlineText($definitionInlines)], $definitionInlines),
            ]),
        ]);
    }

    private function parseTable(): AstNode
    {
        ++$this->index;
        $caption = '';
        $header = [];
        $rows = [];

        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            ++$this->index;
            if ($line === '|}') {
                break;
            }
            if ($line === '|-' || $line === '') {
                continue;
            }
            if (str_starts_with($line, '|+')) {
                $caption = trim(substr($line, 2));
                continue;
            }
            if (str_starts_with($line, '!')) {
                $cells = $this->splitTableCells(substr($line, 1), '!!');
                if ($header === []) {
                    $header = $cells;
                } else {
                    $rows[] = array_map(static fn (string $text): array => ['header' => true, 'text' => $text], $cells);
                }
                continue;
            }
            if (str_starts_with($line, '|')) {
                $rows[] = array_map(static fn (string $text): array => ['header' => false, 'text' => $text], $this->splitTableCells(substr($line, 1), '||'));
            }
        }

        $bodyRows = [];
        foreach ($rows as $row) {
            $bodyRows[] = array_map(static fn (array $cell): string => (string) $cell['text'], $row);
        }
        $columnCount = max(count($header), ...array_map('count', $bodyRows ?: [[]]));

        return new AstNode('table', [
            'caption' => $caption,
            'alignments' => array_fill(0, $columnCount, 'default'),
            'widths' => array_fill(0, $columnCount, null),
            'nativeColumnCount' => $columnCount,
        ], [
            new AstNode('table_head', [], $header === [] ? [] : [$this->tableRow($header, true)]),
            new AstNode('table_body', [], array_map(fn (array $row): AstNode => $this->tableRow($row, false), $bodyRows)),
            new AstNode('table_foot'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function splitTableCells(string $source, string $separator): array
    {
        return array_values(array_map(
            fn (string $cell): string => $this->stripCellAttributes(trim($cell)),
            explode($separator, $source)
        ));
    }

    private function stripCellAttributes(string $cell): string
    {
        if (str_contains($cell, '|') && preg_match('/^[A-Za-z][A-Za-z0-9_:\\-]*(?:\\s*=|\\s|$)/u', $cell) === 1) {
            [, $cell] = array_pad(explode('|', $cell, 2), 2, '');
        }

        return trim($cell);
    }

    /**
     * @param list<string> $cells
     */
    private function tableRow(array $cells, bool $header): AstNode
    {
        return new AstNode('table_row', ['header' => $header], array_map(
            fn (string $cell): AstNode => new AstNode('table_cell', ['header' => $header, 'text' => $this->plainInlineText($this->parseInlines($cell))], [
                new AstNode('plain', [], $this->parseInlines($cell)),
            ]),
            $cells
        ));
    }

    private function paragraphFromText(string $text): AstNode
    {
        $inlines = $this->parseInlines($text);

        return new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $inlines);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $text): array
    {
        $nodes = [];
        $buffer = '';
        $length = strlen($text);
        for ($offset = 0; $offset < $length; ++$offset) {
            $tail = substr($text, $offset);

            if (str_starts_with($tail, '[[')) {
                $end = strpos($text, ']]', $offset + 2);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = $this->parseInternalLink(substr($text, $offset + 2, $end - $offset - 2));
                    $offset = $end + 1;
                    continue;
                }
            }

            if (str_starts_with($tail, '[') && preg_match('/^\\[(https?:\\/\\/[^\\s\\]]+)(?:\\s+([^\\]]+))?\\]/u', $tail, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $label = trim((string) ($match[2] ?? $match[1]));
                $nodes[] = new AstNode('link', ['url' => $match[1], 'title' => ''], $this->parseInlines($label));
                $offset += strlen($match[0]) - 1;
                continue;
            }

            $styled = $this->parseStyleAt($text, $offset);
            if ($styled instanceof AstNode) {
                $this->flushText($nodes, $buffer);
                $nodes[] = $this->withoutPrivateAttrs($styled);
                $offset = (int) $styled->attr('_endOffset');
                continue;
            }

            if (preg_match('/^https?:\\/\\/[^\\s<]+/u', $tail, $match) === 1) {
                $url = rtrim($match[0], '.,;:)');
                $trailing = substr($match[0], strlen($url));
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('link', ['url' => $url, 'title' => ''], [new AstNode('text', ['text' => $url])]);
                $buffer .= $trailing;
                $offset += strlen($match[0]) - 1;
                continue;
            }

            if ($text[$offset] === '&' && preg_match('/^&([A-Za-z][A-Za-z0-9]+|#[0-9]+|#x[0-9A-Fa-f]+);/u', $tail, $match) === 1) {
                $buffer .= html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $offset += strlen($match[0]) - 1;
                continue;
            }

            $buffer .= $text[$offset];
        }
        $this->flushText($nodes, $buffer);

        return $nodes === [] ? [new AstNode('text', ['text' => ''])] : $nodes;
    }

    private function parseInternalLink(string $source): AstNode
    {
        $parts = array_map('trim', explode('|', $source));
        $target = array_shift($parts) ?? '';
        $lowerTarget = strtolower($target);
        if (str_starts_with($lowerTarget, 'file:') || str_starts_with($lowerTarget, 'image:')) {
            $label = '';
            $classes = [];
            foreach ($parts as $part) {
                $lower = strtolower($part);
                if (in_array($lower, ['thumb', 'thumbnail', 'frame', 'frameless'], true)) {
                    $classes[] = $lower;
                    continue;
                }
                if (!str_contains($part, '=') && $label === '') {
                    $label = $part;
                }
            }
            $url = preg_replace('/^(?:file|image):/i', '', $target) ?? $target;

            return new AstNode('image', [
                'url' => trim($url),
                'title' => '',
                'alt' => $label,
                'classes' => $classes,
            ], $this->textInlines($label));
        }

        $label = $parts[0] ?? str_replace('_', ' ', $target);
        $url = str_starts_with($target, '#') ? $target : str_replace(' ', '_', $target);

        return new AstNode('link', ['url' => $url, 'title' => ''], $this->parseInlines($label));
    }

    private function parseStyleAt(string $text, int $offset): ?AstNode
    {
        foreach ([
            "'''''" => ['strong', 'emph'],
            "'''" => ['strong'],
            "''" => ['emph'],
            '<code>' => ['code'],
        ] as $marker => $types) {
            if (!str_starts_with(substr($text, $offset), $marker)) {
                continue;
            }
            $closing = $marker === '<code>' ? '</code>' : $marker;
            $start = $offset + strlen($marker);
            $end = strpos($text, $closing, $start);
            if ($end === false) {
                continue;
            }
            $inner = substr($text, $start, $end - $start);
            $node = in_array('code', $types, true)
                ? new AstNode('code', ['text' => $inner, '_endOffset' => $end + strlen($closing) - 1])
                : $this->wrapStyles($this->parseInlines($inner), $types, $end + strlen($closing) - 1);

            return $node;
        }

        return null;
    }

    /**
     * @param list<AstNode> $children
     * @param list<string> $types
     */
    private function wrapStyles(array $children, array $types, int $endOffset): AstNode
    {
        $type = array_shift($types) ?? 'span';
        $node = $types === []
            ? new AstNode($type, ['_endOffset' => $endOffset], $children)
            : new AstNode($type, ['_endOffset' => $endOffset], [$this->wrapStyles($children, $types, $endOffset)]);

        return $node;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function flushText(array &$nodes, string &$buffer): void
    {
        if ($buffer === '') {
            return;
        }
        $nodes[] = new AstNode('text', ['text' => $buffer]);
        $buffer = '';
    }

    private function withoutPrivateAttrs(AstNode $node): AstNode
    {
        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            if (!str_starts_with((string) $key, '_')) {
                $attrs[$key] = $value;
            }
        }

        return new AstNode($node->type, $attrs, array_map(
            fn (AstNode $child): AstNode => $this->withoutPrivateAttrs($child),
            $node->children
        ));
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text' || $node->type === 'code') {
                $text .= (string) $node->attr('text', '');
            } else {
                $text .= $this->plainInlineText($node->children);
            }
        }

        return $text;
    }
}
