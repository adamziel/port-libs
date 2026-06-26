<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MediaWikiReader
{
    /** @var list<array{name:string, sortKey:string}> */
    private array $categories = [];

    /** @var list<string> */
    private array $behaviorSwitches = [];

    private int $templateCount = 0;

    private int $tableCount = 0;

    private int $referenceCount = 0;

    private int $galleryCount = 0;

    /** @var array<string, list<AstNode>> */
    private array $namedReferences = [];

    public function read(string $source): AstNode
    {
        $this->categories = [];
        $this->behaviorSwitches = [];
        $this->templateCount = 0;
        $this->tableCount = 0;
        $this->referenceCount = 0;
        $this->galleryCount = 0;
        $this->namedReferences = [];

        $source = $this->normalize($source);
        $source = $this->extractCategories($source);
        $source = $this->extractBehaviorSwitches($source);
        $source = preg_replace('/<!--.*?-->/su', '', $source) ?? $source;

        $blocks = $this->parseBlocks(explode("\n", $source));
        $metadata = [
            'mediawikiCategoryCount' => count($this->categories),
            'mediawikiCategories' => $this->metaList(array_map(
                fn (array $category): array => $this->metaMap([
                    'name' => $category['name'],
                    'sortKey' => $category['sortKey'],
                ]),
                $this->categories
            )),
            'mediawikiTemplateCount' => $this->templateCount,
            'mediawikiTableCount' => $this->tableCount,
            'mediawikiReferenceCount' => $this->referenceCount,
            'mediawikiGalleryCount' => $this->galleryCount,
            'mediawikiBehaviorSwitchCount' => count($this->behaviorSwitches),
            'mediawikiBehaviorSwitches' => $this->metaList($this->behaviorSwitches),
        ];

        return new AstNode('document', ['meta' => $metadata], $blocks);
    }

    public function readMediaWikiFile(string $path): AstNode
    {
        $source = file_get_contents($path);
        if (!is_string($source)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }

        return $this->read($source);
    }

    private function normalize(string $source): string
    {
        $source = preg_replace('/^\xEF\xBB\xBF/', '', $source) ?? $source;

        return str_replace(["\r\n", "\r"], "\n", $source);
    }

    private function extractCategories(string $source): string
    {
        return preg_replace_callback(
            '/\[\[\s*(?:Category|category)\s*:\s*([^\]|]+)(?:\|([^\]]*))?\]\]/u',
            function (array $match): string {
                $name = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($name !== '') {
                    $this->categories[] = [
                        'name' => $name,
                        'sortKey' => isset($match[2]) ? trim(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '',
                    ];
                }

                return '';
            },
            $source
        ) ?? $source;
    }

    private function extractBehaviorSwitches(string $source): string
    {
        return preg_replace_callback(
            '/__([A-Z][A-Z0-9_]+)__/u',
            function (array $match): string {
                $this->behaviorSwitches[] = $match[1];

                return '';
            },
            $source
        ) ?? $source;
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>
     */
    private function parseBlocks(array $lines): array
    {
        $blocks = [];
        $index = 0;
        $count = count($lines);

        while ($index < $count) {
            $line = $lines[$index];
            $trimmed = trim($line);
            if ($trimmed === '') {
                $index++;
                continue;
            }

            if (str_starts_with($trimmed, '{|')) {
                [$tableLines, $index] = $this->collectUntil($lines, $index, '/^\s*\|}\s*$/u');
                $blocks[] = $this->parseTable($tableLines);
                continue;
            }

            if (preg_match('/^<(pre|syntaxhighlight|source|haskell|hask)\b([^>]*)>/iu', $trimmed, $match) === 1) {
                [$block, $index] = $this->parseCodeTagBlock($lines, $index, strtolower($match[1]), $match[2] ?? '');
                $blocks[] = $block;
                continue;
            }

            if (preg_match('/^<blockquote\b[^>]*>/iu', $trimmed) === 1) {
                [$quoteLines, $index] = $this->collectHtmlTagBody($lines, $index, 'blockquote');
                $blocks[] = new AstNode('blockquote', [], $this->parseBlocks($quoteLines));
                continue;
            }

            if (preg_match('/^<gallery\b[^>]*>/iu', $trimmed) === 1) {
                [$galleryLines, $index] = $this->collectHtmlTagBody($lines, $index, 'gallery');
                $blocks[] = $this->parseGallery($galleryLines);
                continue;
            }

            if (preg_match('/^<references\b[^>]*\/?>/iu', $trimmed) === 1) {
                $blocks[] = new AstNode('div', [
                    'classes' => ['mediawiki-references'],
                    'attributes' => ['data-mediawiki-references' => 'placeholder'],
                ]);
                $index++;
                continue;
            }

            if (preg_match('/^(={1,6})\s*(.*?)\s*\1\s*$/u', $line, $match) === 1) {
                $text = trim($match[2]);
                $inlines = $this->parseInlines($text);
                $blocks[] = new AstNode('heading', [
                    'level' => strlen($match[1]),
                    'id' => $this->slugify($this->plainText($inlines)),
                ], $inlines);
                $index++;
                continue;
            }

            if (preg_match('/^-{4,}\s*$/u', $trimmed) === 1) {
                $blocks[] = new AstNode('horizontal_rule');
                $index++;
                continue;
            }

            if (preg_match('/^([*#]+)(.*)$/u', $line) === 1) {
                [$listLines, $index] = $this->collectListLines($lines, $index);
                foreach ($this->parseMediaWikiLists($listLines) as $listBlock) {
                    $blocks[] = $listBlock;
                }
                continue;
            }

            if (preg_match('/^[;:](.*)$/u', $line) === 1) {
                [$definitionLines, $index] = $this->collectDefinitionLines($lines, $index);
                $blocks[] = $this->parseDefinitionList($definitionLines);
                continue;
            }

            if (str_starts_with($line, ' ') && $trimmed !== '') {
                [$codeLines, $index] = $this->collectIndentedCodeLines($lines, $index);
                $blocks[] = new AstNode('code_block', [
                    'text' => implode("\n", array_map(static fn (string $codeLine): string => substr($codeLine, 0, 1) === ' ' ? substr($codeLine, 1) : $codeLine, $codeLines)),
                ]);
                continue;
            }

            if (str_starts_with($trimmed, '{{')) {
                [$templateLines, $index] = $this->collectTemplateBlock($lines, $index);
                $text = implode("\n", $templateLines);
                $this->templateCount++;
                $blocks[] = $this->templateBlock($text);
                continue;
            }

            [$paragraphLines, $index] = $this->collectParagraphLines($lines, $index);
            $paragraph = trim(implode("\n", $paragraphLines));
            if ($paragraph === '') {
                continue;
            }
            $blocks[] = $this->paragraphBlock($paragraph);
        }

        return $blocks;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectUntil(array $lines, int $index, string $terminatorPattern): array
    {
        $collected = [];
        $count = count($lines);
        while ($index < $count) {
            $collected[] = $lines[$index];
            if (preg_match($terminatorPattern, $lines[$index]) === 1) {
                $index++;
                break;
            }
            $index++;
        }

        return [$collected, $index];
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectHtmlTagBody(array $lines, int $index, string $tag): array
    {
        $body = [];
        $count = count($lines);
        $line = $lines[$index];
        $line = preg_replace('/^.*?<' . preg_quote($tag, '/') . '\b[^>]*>/iu', '', $line) ?? '';

        while ($index < $count) {
            if (preg_match('/<\/' . preg_quote($tag, '/') . '\s*>/iu', $line) === 1) {
                $beforeClose = preg_replace('/<\/' . preg_quote($tag, '/') . '\s*>.*$/iu', '', $line) ?? '';
                if ($beforeClose !== '') {
                    $body[] = $beforeClose;
                }
                $index++;
                break;
            }

            $body[] = $line;
            $index++;
            $line = $index < $count ? $lines[$index] : '';
        }

        return [$body, $index];
    }

    /**
     * @param list<string> $lines
     * @return array{0:AstNode,1:int}
     */
    private function parseCodeTagBlock(array $lines, int $index, string $tag, string $attrSource): array
    {
        [$body, $nextIndex] = $this->collectHtmlTagBody($lines, $index, $tag);
        $text = implode("\n", $body);
        $attrs = ['text' => trim($text, "\n")];
        $language = '';
        $htmlAttributes = $this->parseHtmlAttributes($attrSource);
        if (isset($htmlAttributes['lang'])) {
            $language = (string) $htmlAttributes['lang'];
        } elseif (in_array($tag, ['haskell', 'hask'], true)) {
            $language = 'haskell';
        } elseif ($tag !== 'pre') {
            $language = $tag;
        }

        if ($language !== '') {
            $attrs['classes'] = [$this->sanitizeClass($language)];
        }
        if (isset($htmlAttributes['line'])) {
            $attrs['htmlAttributes'] = ['data-mediawiki-line' => (string) $htmlAttributes['line']];
        }
        if (isset($htmlAttributes['start'])) {
            $attrs['htmlAttributes'] = array_replace($attrs['htmlAttributes'] ?? [], ['data-mediawiki-start' => (string) $htmlAttributes['start']]);
        }

        return [new AstNode('code_block', $attrs), $nextIndex];
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectListLines(array $lines, int $index): array
    {
        $collected = [];
        $count = count($lines);
        while ($index < $count && preg_match('/^[*#]+/u', $lines[$index]) === 1) {
            $collected[] = $lines[$index];
            $index++;
        }

        return [$collected, $index];
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectDefinitionLines(array $lines, int $index): array
    {
        $collected = [];
        $count = count($lines);
        while ($index < $count && preg_match('/^[;:]/u', $lines[$index]) === 1) {
            $collected[] = $lines[$index];
            $index++;
        }

        return [$collected, $index];
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectIndentedCodeLines(array $lines, int $index): array
    {
        $collected = [];
        $count = count($lines);
        while ($index < $count && (str_starts_with($lines[$index], ' ') || trim($lines[$index]) === '')) {
            $collected[] = $lines[$index];
            $index++;
        }

        return [$collected, $index];
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectTemplateBlock(array $lines, int $index): array
    {
        $collected = [];
        $depth = 0;
        $count = count($lines);
        while ($index < $count) {
            $line = $lines[$index];
            $collected[] = $line;
            $depth += substr_count($line, '{{');
            $depth -= substr_count($line, '}}');
            $index++;
            if ($depth <= 0) {
                break;
            }
        }

        return [$collected, $index];
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectParagraphLines(array $lines, int $index): array
    {
        $collected = [];
        $count = count($lines);
        while ($index < $count) {
            $line = $lines[$index];
            if (trim($line) === '') {
                break;
            }
            if ($collected !== [] && $this->isBlockStartLine($line)) {
                break;
            }
            $collected[] = $line;
            $index++;
        }

        return [$collected, $index];
    }

    private function isBlockStartLine(string $line): bool
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return true;
        }

        return str_starts_with($trimmed, '{|')
            || preg_match('/^<(pre|syntaxhighlight|source|haskell|hask|blockquote)\b/iu', $trimmed) === 1
            || preg_match('/^<(gallery|references)\b/iu', $trimmed) === 1
            || preg_match('/^(={1,6})\s*(.*?)\s*\1\s*$/u', $line) === 1
            || preg_match('/^-{4,}\s*$/u', $trimmed) === 1
            || preg_match('/^[*#]+/u', $line) === 1
            || preg_match('/^[;:]/u', $line) === 1
            || (str_starts_with($line, ' ') && $trimmed !== '')
            || str_starts_with($trimmed, '{{');
    }

    private function paragraphBlock(string $text): AstNode
    {
        $inlines = $this->parseInlines($text);
        if (count($inlines) === 1 && $inlines[0]->type === 'image') {
            $image = $inlines[0];
            $captionInlines = $image->children;
            $caption = $this->plainText($captionInlines);

            return new AstNode('figure', [
                'caption' => $caption,
                'captionInlines' => $captionInlines,
                'classes' => ['mediawiki-image'],
            ], [$image]);
        }

        return new AstNode('paragraph', ['text' => $this->plainText($inlines)], $inlines);
    }

    /**
     * @param list<string> $lines
     */
    private function parseGallery(array $lines): AstNode
    {
        $this->galleryCount++;
        $figures = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(?:File|Image|Archivo|Media)\s*:\s*(.+)$/iu', $line) === 1) {
                $nodes = $this->parseInternalLink($line);
            } else {
                $nodes = $this->parseInternalLink('File:' . $line);
            }
            foreach ($nodes as $node) {
                if ($node->type !== 'image') {
                    continue;
                }
                $captionInlines = $node->children;
                $figures[] = new AstNode('figure', [
                    'caption' => $this->plainText($captionInlines),
                    'captionInlines' => $captionInlines,
                    'classes' => ['mediawiki-image', 'mediawiki-gallery-item'],
                ], [$node]);
            }
        }

        return new AstNode('div', [
            'classes' => ['mediawiki-gallery'],
            'attributes' => [
                'data-pandoc-source' => 'mediawiki',
                'data-mediawiki-gallery-count' => (string) count($figures),
            ],
        ], $figures);
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>
     */
    private function parseMediaWikiLists(array $lines): array
    {
        $items = [];
        foreach ($lines as $line) {
            if (preg_match('/^([*#]+)\s*(.*)$/u', $line, $match) !== 1) {
                continue;
            }
            $items[] = [
                'markers' => $match[1],
                'text' => $match[2],
            ];
        }

        $index = 0;

        return $this->buildListBlocks($items, $index, 1);
    }

    /**
     * @param list<array{markers:string,text:string}> $items
     * @return list<AstNode>
     */
    private function buildListBlocks(array $items, int &$index, int $level): array
    {
        $blocks = [];
        $count = count($items);
        while ($index < $count) {
            $markers = $items[$index]['markers'];
            $currentLevel = strlen($markers);
            if ($currentLevel < $level) {
                break;
            }
            if ($currentLevel > $level) {
                if ($blocks === []) {
                    $blocks[] = $this->emptyListForMarker($markers[$level - 1] ?? '*');
                }
                $lastBlockIndex = array_key_last($blocks);
                $lastList = $blocks[$lastBlockIndex];
                $children = $lastList->children;
                $lastItemIndex = array_key_last($children);
                if ($lastItemIndex === null) {
                    $children[] = new AstNode('list_item', [], []);
                    $lastItemIndex = array_key_last($children);
                }
                $nested = $this->buildListBlocks($items, $index, $level + 1);
                $lastItem = $children[$lastItemIndex];
                $children[$lastItemIndex] = new AstNode($lastItem->type, $lastItem->attrs, array_merge($lastItem->children, $nested));
                $blocks[$lastBlockIndex] = new AstNode($lastList->type, $lastList->attrs, $children);
                continue;
            }

            $marker = $markers[$level - 1] ?? '*';
            $type = $marker === '#' ? 'ordered_list' : 'bullet_list';
            $listAttrs = $type === 'ordered_list' ? ['start' => 1, 'style' => 'default', 'delimiter' => 'default'] : [];
            if ($blocks === [] || $blocks[array_key_last($blocks)]->type !== $type) {
                $blocks[] = new AstNode($type, $listAttrs, []);
            }

            $lastBlockIndex = array_key_last($blocks);
            $list = $blocks[$lastBlockIndex];
            $children = $list->children;
            $itemInlines = $this->parseInlines($items[$index]['text']);
            $children[] = new AstNode('list_item', [], [
                new AstNode('plain', ['text' => $this->plainText($itemInlines)], $itemInlines),
            ]);
            $blocks[$lastBlockIndex] = new AstNode($list->type, $list->attrs, $children);
            $index++;
        }

        return $blocks;
    }

    private function emptyListForMarker(string $marker): AstNode
    {
        if ($marker === '#') {
            return new AstNode('ordered_list', ['start' => 1, 'style' => 'default', 'delimiter' => 'default'], []);
        }

        return new AstNode('bullet_list', [], []);
    }

    /**
     * @param list<string> $lines
     */
    private function parseDefinitionList(array $lines): AstNode
    {
        $items = [];
        $currentTerm = null;
        $definitions = [];

        foreach ($lines as $line) {
            $marker = substr($line, 0, 1);
            $text = trim(substr($line, 1));
            if ($marker === ';') {
                if ($currentTerm !== null) {
                    $items[] = $this->definitionItem($currentTerm, $definitions);
                }
                $currentTerm = $text;
                $definitions = [];
                continue;
            }

            if ($currentTerm === null) {
                $currentTerm = '';
            }
            $definitions[] = $text;
        }

        if ($currentTerm !== null) {
            $items[] = $this->definitionItem($currentTerm, $definitions);
        }

        return new AstNode('definition_list', [], $items);
    }

    /**
     * @param list<string> $definitions
     */
    private function definitionItem(string $term, array $definitions): AstNode
    {
        $termInlines = $this->parseInlines($term);
        $children = [
            new AstNode('term', ['text' => $this->plainText($termInlines)], $termInlines),
        ];
        foreach ($definitions === [] ? [''] : $definitions as $definition) {
            $definitionInlines = $this->parseInlines($definition);
            $children[] = new AstNode('definition', [], [
                new AstNode('plain', ['text' => $this->plainText($definitionInlines)], $definitionInlines),
            ]);
        }

        return new AstNode('definition_item', [], $children);
    }

    /**
     * @param list<string> $lines
     */
    private function parseTable(array $lines): AstNode
    {
        $this->tableCount++;
        $opening = array_shift($lines) ?? '{|';
        if (($lines !== []) && trim($lines[array_key_last($lines)]) === '|}') {
            array_pop($lines);
        }

        $tableAttrs = $this->tableHtmlAttributes($this->parseHtmlAttributes(trim(substr(trim($opening), 2))));
        $tableAttrs['data-pandoc-source'] = 'mediawiki';
        $caption = '';
        $captionInlines = [];
        $rows = [];
        $currentRow = null;
        $lastCellIndex = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '|+')) {
                $caption = trim(substr($trimmed, 2));
                $captionInlines = $this->parseInlines($caption);
                continue;
            }

            if (str_starts_with($trimmed, '|-')) {
                if ($currentRow !== null) {
                    $rows[] = $this->tableRowNode($currentRow);
                }
                $currentRow = [
                    'attrs' => $this->tableHtmlAttributes($this->parseHtmlAttributes(trim(substr($trimmed, 2)))),
                    'cells' => [],
                ];
                $lastCellIndex = null;
                continue;
            }

            if ($currentRow === null) {
                $currentRow = ['attrs' => [], 'cells' => []];
            }

            if (str_starts_with($trimmed, '!') || str_starts_with($trimmed, '|')) {
                $header = str_starts_with($trimmed, '!');
                $segments = $this->splitTableCellLine(substr($trimmed, 1), $header ? '!!' : '||');
                foreach ($segments as $segment) {
                    $currentRow['cells'][] = $this->parseTableCell($segment, $header);
                    $lastCellIndex = array_key_last($currentRow['cells']);
                }
                continue;
            }

            if ($lastCellIndex !== null) {
                $currentRow['cells'][$lastCellIndex]['text'] .= "\n" . $trimmed;
            }
        }

        if ($currentRow !== null) {
            $rows[] = $this->tableRowNode($currentRow);
        }

        $headRows = [];
        if ($rows !== [] && $this->rowIsAllHeader($rows[0])) {
            $headRows[] = array_shift($rows);
        }

        $children = [];
        if ($headRows !== []) {
            $children[] = new AstNode('table_head', [], $headRows);
        }
        $children[] = new AstNode('table_body', [], $rows);
        $columnCount = $this->tableColumnCount(array_merge($headRows, $rows));

        return new AstNode('table', [
            'caption' => $this->plainText($captionInlines),
            'captionInlines' => $captionInlines,
            'alignments' => array_fill(0, $columnCount, 'default'),
            'htmlAttributes' => $tableAttrs,
        ], $children);
    }

    /**
     * @return list<string>
     */
    private function splitTableCellLine(string $line, string $separator): array
    {
        $parts = preg_split('/\s*' . preg_quote($separator, '/') . '\s*/u', $line);
        if (!is_array($parts) || $parts === []) {
            return [$line];
        }

        return array_map('trim', $parts);
    }

    /**
     * @return array{text:string, header:bool, attrs:array<string,string>, colspan:int, rowspan:int, align:string}
     */
    private function parseTableCell(string $segment, bool $header): array
    {
        $attrs = [];
        $text = trim($segment);
        $pipe = strpos($text, '|');
        if ($pipe !== false) {
            $maybeAttrs = trim(substr($text, 0, $pipe));
            if ($maybeAttrs !== '' && preg_match('/^[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=|\s|$)/u', $maybeAttrs) === 1) {
                $attrs = $this->parseHtmlAttributes($maybeAttrs);
                $text = trim(substr($text, $pipe + 1));
            }
        }

        $colspan = max(1, (int) ($attrs['colspan'] ?? 1));
        $rowspan = max(1, (int) ($attrs['rowspan'] ?? 1));
        $align = strtolower((string) ($attrs['align'] ?? ''));
        if ($align === '' && isset($attrs['style']) && preg_match('/text-align\s*:\s*(left|right|center)/iu', (string) $attrs['style'], $match) === 1) {
            $align = strtolower($match[1]);
        }
        unset($attrs['colspan'], $attrs['rowspan'], $attrs['align']);

        return [
            'text' => $text,
            'header' => $header,
            'attrs' => $this->tableHtmlAttributes($attrs),
            'colspan' => $colspan,
            'rowspan' => $rowspan,
            'align' => in_array($align, ['left', 'right', 'center'], true) ? $align : '',
        ];
    }

    /**
     * @param array{attrs:array<string,string>,cells:list<array{text:string, header:bool, attrs:array<string,string>, colspan:int, rowspan:int, align:string}>} $row
     */
    private function tableRowNode(array $row): AstNode
    {
        return new AstNode('table_row', ['htmlAttributes' => $row['attrs']], array_map(
            function (array $cell): AstNode {
                $inlines = $this->parseInlines($cell['text']);

                return new AstNode('table_cell', [
                    'text' => $this->plainText($inlines),
                    'header' => $cell['header'],
                    'colspan' => $cell['colspan'],
                    'rowspan' => $cell['rowspan'],
                    'align' => $cell['align'],
                    'htmlAttributes' => $cell['attrs'],
                ], [
                    new AstNode('plain', ['text' => $this->plainText($inlines)], $inlines),
                ]);
            },
            $row['cells']
        ));
    }

    private function rowIsAllHeader(AstNode $row): bool
    {
        if ($row->children === []) {
            return false;
        }
        foreach ($row->children as $cell) {
            if ($cell->attr('header') !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<AstNode> $rows
     */
    private function tableColumnCount(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $count = 0;
            foreach ($row->children as $cell) {
                $count += max(1, (int) $cell->attr('colspan', 1));
            }
            $max = max($max, $count);
        }

        return $max;
    }

    /**
     * @param array<string, string> $attrs
     * @return array<string, string>
     */
    private function tableHtmlAttributes(array $attrs): array
    {
        $mapped = [];
        foreach ($attrs as $name => $value) {
            $name = strtolower($name);
            if (str_starts_with($name, 'on')) {
                continue;
            }
            if ($name === 'source') {
                $mapped['data-mediawiki-source'] = $value;
                continue;
            }
            $mapped[$name] = $value;
        }

        return $mapped;
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $text): array
    {
        $text = preg_replace('/<!--.*?-->/su', '', $text) ?? $text;
        $nodes = [];
        $buffer = '';
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            $remaining = substr($text, $offset);

            if ($remaining[0] === "\n") {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('softbreak');
                $offset++;
                continue;
            }

            if (preg_match('/^https?:\/\/[^\s<>\[\]]+/u', $remaining, $match) === 1 || preg_match('/^mailto:[^\s<>\[\]]+/u', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $url = rtrim($match[0], '.,;)');
                $nodes[] = new AstNode('link', ['url' => $url], [new AstNode('text', ['text' => $url])]);
                $offset += strlen($url);
                continue;
            }

            if (str_starts_with($remaining, "'''''")) {
                $end = strpos($text, "'''''", $offset + 5);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $inner = substr($text, $offset + 5, $end - $offset - 5);
                    $nodes[] = new AstNode('strong', [], [
                        new AstNode('emph', [], $this->parseInlines($inner)),
                    ]);
                    $offset = $end + 5;
                    continue;
                }
            }

            if (str_starts_with($remaining, "'''")) {
                $end = strpos($text, "'''", $offset + 3);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $inner = substr($text, $offset + 3, $end - $offset - 3);
                    $nodes[] = new AstNode('strong', [], $this->parseInlines($inner));
                    $offset = $end + 3;
                    continue;
                }
            }

            if (str_starts_with($remaining, "''")) {
                $end = strpos($text, "''", $offset + 2);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $inner = substr($text, $offset + 2, $end - $offset - 2);
                    $nodes[] = new AstNode('emph', [], $this->parseInlines($inner));
                    $offset = $end + 2;
                    continue;
                }
            }

            if (str_starts_with($remaining, '[[')) {
                $end = $this->findInternalLinkEnd($text, $offset);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $content = substr($text, $offset + 2, $end - $offset - 2);
                    foreach ($this->parseInternalLink($content) as $node) {
                        $nodes[] = $node;
                    }
                    $offset = $end + 2;
                    continue;
                }
            }

            if (str_starts_with($remaining, '[http://') || str_starts_with($remaining, '[https://') || str_starts_with($remaining, '[mailto:')) {
                $end = strpos($text, ']', $offset + 1);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = $this->parseExternalLink(substr($text, $offset + 1, $end - $offset - 1));
                    $offset = $end + 1;
                    continue;
                }
            }

            if (str_starts_with($remaining, '{{')) {
                $end = $this->findTemplateInlineEnd($text, $offset);
                if ($end !== null) {
                    $this->flushText($nodes, $buffer);
                    $raw = substr($text, $offset, $end - $offset);
                    $this->templateCount++;
                    $nodes[] = $this->templateInline($raw);
                    $offset = $end;
                    continue;
                }
            }

            if (preg_match('/^<ref\b([^\/>]*)\/>/iu', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $note = $this->referenceNode($match[1], '');
                if ($note instanceof AstNode) {
                    $nodes[] = $note;
                }
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/^<ref\b([^>]*)>(.*?)<\/ref>/isu', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $note = $this->referenceNode($match[1], $match[2]);
                if ($note instanceof AstNode) {
                    $nodes[] = $note;
                }
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/^<br\s*\/?>/iu', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('linebreak');
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/^<nowiki\s*\/>/iu', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/^<nowiki>(.*?)<\/nowiki>/isu', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('text', ['text' => html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/^<math\b[^>]*>(.*?)<\/math>/isu', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('math', ['text' => trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'display' => false]);
                $offset += strlen($match[0]);
                continue;
            }

            $tagNode = $this->parseInlineTag($remaining);
            if ($tagNode !== null) {
                [$node, $consumed] = $tagNode;
                $this->flushText($nodes, $buffer);
                $nodes[] = $node;
                $offset += $consumed;
                continue;
            }

            $buffer .= $text[$offset];
            $offset++;
        }

        $this->flushText($nodes, $buffer);

        return $nodes;
    }

    private function findInternalLinkEnd(string $text, int $offset): int|false
    {
        $index = $offset + 2;
        $length = strlen($text);
        while ($index < $length - 1) {
            if (
                $text[$index] === '['
                && (
                    str_starts_with(substr($text, $index), '[http://')
                    || str_starts_with(substr($text, $index), '[https://')
                    || str_starts_with(substr($text, $index), '[mailto:')
                )
            ) {
                $externalEnd = strpos($text, ']', $index + 1);
                if ($externalEnd === false) {
                    return false;
                }
                $index = $externalEnd + 1;
                continue;
            }

            if ($text[$index] === ']' && $text[$index + 1] === ']') {
                return $index;
            }

            $index++;
        }

        return false;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function flushText(array &$nodes, string &$buffer): void
    {
        if ($buffer === '') {
            return;
        }

        $text = html_entity_decode($buffer, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $buffer = '';
        if ($text === '') {
            return;
        }

        $lastIndex = array_key_last($nodes);
        if ($lastIndex !== null && $nodes[$lastIndex]->type === 'text') {
            $nodes[$lastIndex] = new AstNode('text', ['text' => (string) $nodes[$lastIndex]->attr('text', '') . $text]);
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $text]);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInternalLink(string $content): array
    {
        $parts = array_map('trim', explode('|', $content));
        $target = array_shift($parts) ?? '';
        if ($target === '') {
            return [];
        }

        if (preg_match('/^(?:Category|category)\s*:\s*(.+)$/u', $target, $match) === 1) {
            $this->categories[] = [
                'name' => trim($match[1]),
                'sortKey' => $parts[0] ?? '',
            ];

            return [];
        }

        if (preg_match('/^(?:File|Image|Archivo|Media)\s*:\s*(.+)$/iu', $target, $match) === 1) {
            return [$this->imageNode($match[1], $parts)];
        }

        $label = $parts === [] ? '' : trim(end($parts));
        if ($label === '') {
            $label = $this->defaultLinkLabel($target, $parts !== []);
        }

        $url = $this->wikiTargetUrl($target);

        return [new AstNode('link', [
            'url' => $url,
            'classes' => ['wikilink'],
        ], $this->parseInlines($label))];
    }

    /**
     * @param list<string> $parts
     */
    private function imageNode(string $target, array $parts): AstNode
    {
        $caption = '';
        $attributes = ['data-pandoc-source' => 'mediawiki'];
        $alt = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || in_array(strtolower($part), ['thumb', 'thumbnail', 'frame', 'frameless', 'border', 'left', 'right', 'center', 'none'], true)) {
                if ($part !== '') {
                    $attributes['data-mediawiki-layout'] = strtolower($part);
                }
                continue;
            }
            if (preg_match('/^alt\s*=\s*(.*)$/iu', $part, $match) === 1) {
                $alt = trim($match[1]);
                continue;
            }
            if (preg_match('/^link\s*=\s*(.*)$/iu', $part, $match) === 1) {
                $attributes['data-mediawiki-link'] = trim($match[1]);
                continue;
            }
            if (preg_match('/^class\s*=\s*(.*)$/iu', $part, $match) === 1) {
                $attributes['class'] = trim($match[1]);
                continue;
            }
            if (preg_match('/^page\s*=\s*(\d+)$/iu', $part, $match) === 1) {
                $attributes['data-mediawiki-page'] = $match[1];
                continue;
            }
            if (preg_match('/^(\d+)(?:x(\d+))?px$/iu', $part, $match) === 1) {
                $attributes['width'] = $match[1] . 'px';
                if (isset($match[2]) && $match[2] !== '') {
                    $attributes['height'] = $match[2] . 'px';
                }
                continue;
            }

            $caption = $part;
        }

        $captionInlines = $this->parseInlines($caption);
        $url = str_replace(' ', '_', trim($target));

        return new AstNode('image', [
            'url' => $url,
            'alt' => $alt !== '' ? $alt : $this->plainText($captionInlines),
            'title' => '',
            'attributes' => $attributes,
        ], $captionInlines);
    }

    private function defaultLinkLabel(string $target, bool $hadExplicitPipe): string
    {
        $label = preg_replace('/#.*$/u', '', $target) ?? $target;
        if ($hadExplicitPipe && str_contains($label, ':')) {
            $segments = explode(':', $label);
            $label = end($segments) ?: $label;
        }

        return $label === '' ? $target : $label;
    }

    private function wikiTargetUrl(string $target): string
    {
        $target = trim($target);
        if (str_starts_with($target, '#')) {
            return '#' . str_replace(' ', '_', substr($target, 1));
        }

        return str_replace(' ', '_', $target);
    }

    private function parseExternalLink(string $content): AstNode
    {
        $content = trim($content);
        $parts = preg_split('/\s+/u', $content, 2);
        $url = $parts[0] ?? '';
        $label = trim($parts[1] ?? '');
        if ($label === '') {
            $label = $url;
        }

        return new AstNode('link', ['url' => $url], $this->parseInlines($label));
    }

    private function findTemplateInlineEnd(string $text, int $offset): ?int
    {
        $closing = str_starts_with(substr($text, $offset), '{{{') ? '}}}' : '}}';
        $start = $offset + strlen($closing) - 1;
        $end = strpos($text, $closing, $start);
        if ($end === false) {
            return null;
        }

        return $end + strlen($closing);
    }

    private function templateBlock(string $raw): AstNode
    {
        $template = $this->parseTemplate($raw);
        $items = [];
        foreach ($template['fields'] as $name => $value) {
            $items[] = new AstNode('definition_item', [], [
                new AstNode('term', ['text' => $name], [new AstNode('text', ['text' => $name])]),
                new AstNode('definition', [], [
                    new AstNode('plain', ['text' => $this->plainText($this->parseInlines($value))], $this->parseInlines($value)),
                ]),
            ]);
        }

        return new AstNode('div', [
            'classes' => ['mediawiki-template'],
            'attributes' => array_filter([
                'data-pandoc-source' => 'mediawiki',
                'data-mediawiki-template' => $template['name'],
                'data-mediawiki-parser-function' => $template['parserFunction'] ? 'true' : '',
            ], static fn (string $value): bool => $value !== ''),
        ], [
            new AstNode('paragraph', ['text' => $template['name']], [
                new AstNode('strong', [], [new AstNode('text', ['text' => $template['name']])]),
            ]),
            new AstNode('definition_list', [], $items),
        ]);
    }

    private function templateInline(string $raw): AstNode
    {
        $template = $this->parseTemplate($raw);
        $label = $template['name'];
        if ($template['fields'] !== []) {
            $label .= ': ' . implode('; ', array_map(
                static fn (string $name, string $value): string => $name . '=' . $value,
                array_keys($template['fields']),
                array_values($template['fields'])
            ));
        }

        return new AstNode('span', [
            'classes' => ['mediawiki-template'],
            'htmlAttributes' => array_filter([
                'data-mediawiki-template' => $template['name'],
                'data-mediawiki-parser-function' => $template['parserFunction'] ? 'true' : '',
            ], static fn (string $value): bool => $value !== ''),
        ], $this->parseInlines($label));
    }

    /**
     * @return array{name:string,parserFunction:bool,fields:array<string,string>}
     */
    private function parseTemplate(string $raw): array
    {
        $inner = trim($raw);
        $inner = preg_replace('/^\{\{\{?|\}\}\}?$/u', '', $inner) ?? $inner;
        $parts = array_map('trim', explode('|', $inner));
        $name = array_shift($parts) ?? '';
        $fields = [];
        $position = 1;
        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$field, $value] = array_map('trim', explode('=', $part, 2));
                $fields[$field === '' ? (string) $position : $field] = $value;
            } else {
                $fields[(string) $position] = $part;
            }
            $position++;
        }

        return [
            'name' => $name === '' ? 'template' : $name,
            'parserFunction' => str_starts_with($name, '#'),
            'fields' => $fields,
        ];
    }

    private function referenceNode(string $attrSource, string $body): ?AstNode
    {
        $attrs = $this->parseHtmlAttributes($attrSource);
        $name = trim((string) ($attrs['name'] ?? ''));
        if (trim($body) === '' && $name !== '' && isset($this->namedReferences[$name])) {
            return new AstNode('note', [
                'noteType' => 'mediawiki-reference',
                'id' => $name,
            ], $this->namedReferences[$name]);
        }

        $inlines = $this->parseInlines(trim($body));
        if ($inlines === []) {
            return null;
        }

        $this->referenceCount++;
        $children = [new AstNode('paragraph', ['text' => $this->plainText($inlines)], $inlines)];
        if ($name !== '') {
            $this->namedReferences[$name] = $children;
        }

        return new AstNode('note', [
            'noteType' => 'mediawiki-reference',
            'id' => $name,
        ], $children);
    }

    /**
     * @return array{0:AstNode,1:int}|null
     */
    private function parseInlineTag(string $remaining): ?array
    {
        if (preg_match('/^<(code|tt|hask|kbd|samp)\b[^>]*>(.*?)<\/\1>/isu', $remaining, $match) === 1) {
            return [new AstNode('code', ['text' => html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')]), strlen($match[0])];
        }

        $tagMap = [
            'b' => 'strong',
            'strong' => 'strong',
            'i' => 'emph',
            'em' => 'emph',
            'sup' => 'superscript',
            'sub' => 'subscript',
            'strike' => 'strikeout',
            's' => 'strikeout',
            'del' => 'strikeout',
            'u' => 'underline',
            'ins' => 'underline',
        ];

        foreach ($tagMap as $tag => $type) {
            if (preg_match('/^<' . preg_quote($tag, '/') . '\b[^>]*>(.*?)<\/' . preg_quote($tag, '/') . '>/isu', $remaining, $match) !== 1) {
                continue;
            }

            return [new AstNode($type, [], $this->parseInlines($match[1])), strlen($match[0])];
        }

        if (preg_match('/^<span\b([^>]*)>(.*?)<\/span>/isu', $remaining, $match) === 1) {
            return [
                new AstNode('span', [
                    'htmlAttributes' => $this->parseHtmlAttributes($match[1]),
                ], $this->parseInlines($match[2])),
                strlen($match[0]),
            ];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function parseHtmlAttributes(string $source): array
    {
        $attrs = [];
        preg_match_all('/([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s]+))/u', $source, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            if (str_starts_with($name, 'on')) {
                continue;
            }
            $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);
            $attrs[$name] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $attrs;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                'math' => (string) $node->attr('text', ''),
                'image' => (string) $node->attr('alt', ''),
                'raw_inline' => (string) $node->attr('text', ''),
                default => $this->plainText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function slugify(string $text): string
    {
        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');

        return $slug === '' ? 'section' : $slug;
    }

    private function sanitizeClass(string $class): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $class) ?? '';
    }

    /**
     * @param array<string, mixed> $items
     * @return array{type:string,value:array<string,mixed>}
     */
    private function metaMap(array $items): array
    {
        $mapped = [];
        foreach ($items as $key => $value) {
            $mapped[$key] = is_string($value) ? $this->metaInlines($value) : $value;
        }

        return ['type' => 'MetaMap', 'value' => $mapped];
    }

    /**
     * @param list<mixed> $items
     * @return array{type:string,value:list<mixed>}
     */
    private function metaList(array $items): array
    {
        return ['type' => 'MetaList', 'value' => array_map(
            fn (mixed $item): mixed => is_string($item) ? $this->metaInlines($item) : $item,
            $items
        )];
    }

    /**
     * @return array{type:string,value:list<AstNode>}
     */
    private function metaInlines(string $text): array
    {
        return ['type' => 'MetaInlines', 'value' => [new AstNode('text', ['text' => $text])]];
    }
}
