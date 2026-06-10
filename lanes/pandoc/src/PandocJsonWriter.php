<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocJsonWriter
{
    private const DEFAULT_API_VERSION = [1, 23, 1];

    public function write(AstNode $document): string
    {
        $packet = $this->toArray($document);
        if ($packet['meta'] === []) {
            $packet['meta'] = new \stdClass();
        }

        try {
            return json_encode($packet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Unable to encode Pandoc JSON packet: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(AstNode $document): array
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Pandoc JSON writer expects a document node');
        }

        return [
            'pandoc-api-version' => $this->apiVersion($document),
            'meta' => $this->writeMetaMap($this->meta($document)),
            'blocks' => array_map(fn (AstNode $block): array => $this->writeBlock($block), $document->children),
        ];
    }

    /**
     * @return list<int>
     */
    private function apiVersion(AstNode $document): array
    {
        $version = $document->attr('pandocApiVersion', self::DEFAULT_API_VERSION);
        if (!is_array($version) || !array_is_list($version) || $version === []) {
            return self::DEFAULT_API_VERSION;
        }

        return array_values(array_map(static fn (mixed $part): int => is_int($part) ? $part : 0, $version));
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(AstNode $document): array
    {
        $meta = $document->attr('meta', []);
        if (!is_array($meta) || array_is_list($meta)) {
            return [];
        }

        if (($meta['t'] ?? null) === 'MetaMap') {
            $content = $meta['c'] ?? [];

            return is_array($content) && !array_is_list($content) ? $this->normalizeStandardMeta($content) : [];
        }

        if (($meta['type'] ?? null) === 'map') {
            $items = $meta['items'] ?? [];

            return is_array($items) && !array_is_list($items) ? $this->normalizeStandardMeta($items) : [];
        }

        return $this->normalizeStandardMeta($meta);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function normalizeStandardMeta(array $meta): array
    {
        $normalized = [];
        foreach ($meta as $key => $value) {
            $field = (string) $key;
            if (in_array($field, ['titleInlines', 'authorInlines', 'authors', 'dateInlines'], true)) {
                continue;
            }
            $normalized[$field] = $value;
        }

        $titleInlines = $this->inlineMetaChildren($meta['titleInlines'] ?? null);
        if ($titleInlines !== null) {
            $normalized['title'] = ['type' => 'inlines', 'children' => $titleInlines];
        }

        $authorSource = array_key_exists('authorInlines', $meta)
            ? $meta['authorInlines']
            : (array_key_exists('author', $meta) ? null : ($meta['authors'] ?? null));
        $authorItems = $this->authorMetaItems($authorSource);
        if ($authorItems !== null) {
            $normalized['author'] = ['type' => 'list', 'items' => $authorItems];
        }

        $dateInlines = $this->inlineMetaChildren($meta['dateInlines'] ?? null);
        if ($dateInlines !== null) {
            $normalized['date'] = ['type' => 'inlines', 'children' => $dateInlines];
        }

        return $normalized;
    }

    /**
     * @return list<AstNode>|null
     */
    private function inlineMetaChildren(mixed $value): ?array
    {
        if (!is_array($value) || !array_is_list($value) || !$this->allAstNodes($value)) {
            return null;
        }

        $nodes = array_values($value);

        return $this->allInlineNodes($nodes) ? $nodes : null;
    }

    /**
     * @return list<array{type:string, children:list<AstNode>}>|null
     */
    private function authorMetaItems(mixed $value): ?array
    {
        if (is_array($value) && array_is_list($value)) {
            $items = [];
            foreach ($value as $item) {
                $children = $this->inlineMetaChildren($item);
                if ($children !== null) {
                    $items[] = ['type' => 'inlines', 'children' => $children];
                    continue;
                }

                if (!$this->isTextScalar($item)) {
                    return null;
                }
                $items[] = ['type' => 'inlines', 'children' => $this->textInlines((string) $item)];
            }

            return $items === [] ? null : $items;
        }

        if ($this->isTextScalar($value)) {
            return [['type' => 'inlines', 'children' => $this->textInlines((string) $value)]];
        }

        return null;
    }

    private function isTextScalar(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_float($value);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function writeMetaMap(array $meta): array
    {
        $mapped = [];
        foreach ($meta as $key => $value) {
            $mapped[$key] = $this->writeMetaValue($value);
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function writeMetaValue(mixed $value): array
    {
        if (is_bool($value)) {
            return ['t' => 'MetaBool', 'c' => $value];
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return ['t' => 'MetaString', 'c' => (string) $value];
        }

        if ($value instanceof AstNode) {
            $content = $this->isInlineNode($value) ? $this->writeInline($value) : $this->writeBlock($value);

            return ['t' => $this->isInlineNode($value) ? 'MetaInlines' : 'MetaBlocks', 'c' => [$content]];
        }

        if (is_array($value)) {
            if ($this->isTaggedMetaValue($value)) {
                return $this->writeCompatibleMetaValue($value);
            }

            if (isset($value['type']) && is_string($value['type'])) {
                return $this->writeTypedMetaValue($value);
            }

            if (array_is_list($value)) {
                if ($this->allAstNodes($value)) {
                    $nodes = array_values($value);
                    $inline = $nodes === [] || $this->allInlineNodes($nodes);

                    return [
                        't' => $inline ? 'MetaInlines' : 'MetaBlocks',
                        'c' => array_map(fn (AstNode $node): array => $inline ? $this->writeInline($node) : $this->writeBlock($node), $nodes),
                    ];
                }

                return ['t' => 'MetaList', 'c' => array_map(fn (mixed $item): array => $this->writeMetaValue($item), $value)];
            }

            return ['t' => 'MetaMap', 'c' => $this->writeMetaMap($value)];
        }

        return ['t' => 'MetaString', 'c' => (string) $value];
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function writeTypedMetaValue(array $value): array
    {
        return match ($value['type']) {
            'inlines' => ['t' => 'MetaInlines', 'c' => array_map(fn (AstNode $node): array => $this->writeInline($node), $this->metaChildren($value))],
            'blocks' => ['t' => 'MetaBlocks', 'c' => array_map(fn (AstNode $node): array => $this->writeBlock($node), $this->metaChildren($value))],
            'list' => ['t' => 'MetaList', 'c' => array_map(fn (mixed $item): array => $this->writeMetaValue($item), is_array($value['items'] ?? null) && array_is_list($value['items']) ? $value['items'] : [])],
            'map' => ['t' => 'MetaMap', 'c' => $this->writeMetaMap(is_array($value['items'] ?? null) && !array_is_list($value['items']) ? $value['items'] : [])],
            default => ['t' => 'MetaString', 'c' => ''],
        };
    }

    /**
     * @param array<string, mixed> $value
     */
    private function isTaggedMetaValue(array $value): bool
    {
        return !array_is_list($value)
            && isset($value['t'])
            && is_string($value['t'])
            && in_array($value['t'], [
                'MetaString',
                'MetaBool',
                'MetaInlines',
                'MetaBlocks',
                'MetaList',
                'MetaMap',
            ], true);
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function writeCompatibleMetaValue(array $value): array
    {
        $document = (new PandocJsonReader())->readPacket([
            'meta' => ['__value' => $value],
            'blocks' => [],
        ]);
        $meta = $document->attr('meta', []);
        if (!is_array($meta) || !array_key_exists('__value', $meta)) {
            throw new \InvalidArgumentException('Unable to normalize tagged Pandoc JSON metadata value');
        }

        return $this->writeMetaValue($meta['__value']);
    }

    /**
     * @param array<string, mixed> $value
     * @return list<AstNode>
     */
    private function metaChildren(array $value): array
    {
        $children = $value['children'] ?? [];
        if (!is_array($children) || !array_is_list($children)) {
            return [];
        }

        return array_values(array_filter($children, static fn (mixed $child): bool => $child instanceof AstNode));
    }

    /**
     * @return array<string, mixed>
     */
    private function writeBlock(AstNode $node): array
    {
        return match ($node->type) {
            'plain' => ['t' => 'Plain', 'c' => $this->writeInlines($this->inlineChildrenOrText($node))],
            'paragraph' => ['t' => 'Para', 'c' => $this->writeInlines($this->inlineChildrenOrText($node))],
            'heading' => ['t' => 'Header', 'c' => [(int) $node->attr('level', 1), $this->attrTuple($node), $this->writeInlines($node->children)]],
            'code_block' => ['t' => 'CodeBlock', 'c' => [$this->attrTuple($node), (string) $node->attr('text', '')]],
            'raw_html', 'raw_tex', 'raw_markdown', 'raw_block' => ['t' => 'RawBlock', 'c' => [$this->rawFormat($node), $this->rawText($node)]],
            'blockquote' => ['t' => 'BlockQuote', 'c' => $this->writeBlocks($node->children)],
            'ordered_list' => ['t' => 'OrderedList', 'c' => [
                [(int) $node->attr('start', 1), $this->enum($this->listStyleConstructor((string) $node->attr('style', 'default'))), $this->enum($this->listDelimiterConstructor((string) $node->attr('delimiter', 'default')))],
                $this->writeListItems($node->children),
            ]],
            'bullet_list' => ['t' => 'BulletList', 'c' => $this->writeListItems($node->children)],
            'definition_list' => ['t' => 'DefinitionList', 'c' => $this->writeDefinitionItems($node->children)],
            'line_block' => ['t' => 'LineBlock', 'c' => array_map(fn (AstNode $line): array => $this->writeInlines($this->inlineChildrenOrText($line)), $node->children)],
            'horizontal_rule' => ['t' => 'HorizontalRule'],
            'div' => ['t' => 'Div', 'c' => [$this->attrTuple($node), $this->writeBlocks($node->children)]],
            'figure' => ['t' => 'Figure', 'c' => [$this->attrTuple($node), $this->writeTableCaption($node), $this->writeFigureBlocks($node)]],
            'table' => $this->writeTableBlock($node),
            default => throw new \InvalidArgumentException("Unsupported AST block node for Pandoc JSON: {$node->type}"),
        };
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<array<string, mixed>>
     */
    private function writeBlocks(array $blocks): array
    {
        return array_map(fn (AstNode $block): array => $this->writeBlock($block), $blocks);
    }

    /**
     * @param list<AstNode> $items
     * @return list<list<array<string, mixed>>>
     */
    private function writeListItems(array $items): array
    {
        $encoded = [];
        foreach ($items as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }
            $encoded[] = $this->childrenAsBlocks($item);
        }

        return $encoded;
    }

    /**
     * @param list<AstNode> $items
     * @return list<array{0:list<array<string, mixed>>, 1:list<list<array<string, mixed>>>>>
     */
    private function writeDefinitionItems(array $items): array
    {
        $encoded = [];
        foreach ($items as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            $term = $item->children[0] ?? new AstNode('text', ['text' => (string) $item->attr('term', '')]);
            $termInlines = $this->isInlineNode($term) ? [$term] : $this->inlineChildrenOrText($term);
            $definitions = [];
            foreach (array_slice($item->children, 1) as $definition) {
                if ($definition->type === 'definition') {
                    $definitions[] = $this->childrenAsBlocks($definition);
                }
            }
            $encoded[] = [$this->writeInlines($termInlines), $definitions];
        }

        return $encoded;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function childrenAsBlocks(AstNode $node): array
    {
        if ($node->children === []) {
            return [];
        }

        if ($this->allInlineNodes($node->children)) {
            return [['t' => 'Plain', 'c' => $this->writeInlines($node->children)]];
        }

        return $this->writeBlocks($node->children);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function writeFigureBlocks(AstNode $node): array
    {
        $blocks = [];
        $inlines = [];
        foreach ($node->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlines[] = $child;
                continue;
            }

            if ($inlines !== []) {
                $blocks[] = ['t' => 'Plain', 'c' => $this->writeInlines($inlines)];
                $inlines = [];
            }
            $blocks[] = $this->writeBlock($child);
        }

        if ($inlines !== []) {
            $blocks[] = ['t' => 'Plain', 'c' => $this->writeInlines($inlines)];
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    private function writeTableBlock(AstNode $node): array
    {
        return [
            't' => 'Table',
            'c' => [
                $this->attrTuple($node),
                $this->writeTableCaption($node),
                $this->writeTableColumnSpecs($node),
                $this->writeTableSection($this->firstTableSection($node, 'table_head') ?? new AstNode('table_head')),
                array_map(fn (AstNode $body): array => $this->writeTableBody($body), $this->tableSections($node, 'table_body')),
                $this->writeTableSection($this->firstTableSection($node, 'table_foot') ?? new AstNode('table_foot')),
            ],
        ];
    }

    /**
     * @return array{0:list<array<string, mixed>>|null, 1:list<array<string, mixed>>}
     */
    private function writeTableCaption(AstNode $node): array
    {
        return [
            $this->writeShortCaption($node),
            $this->writeLongCaptionBlocks($node),
        ];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function writeShortCaption(AstNode $node): ?array
    {
        $inlines = $node->attr('shortCaptionInlines', []);
        if ($inlines !== [] && is_array($inlines) && $this->allAstNodes($inlines) && $this->allInlineNodes($inlines)) {
            return $this->writeInlines($inlines);
        }

        $blockInlines = $this->writeShortCaptionBlockInlines($node->attr('shortCaptionBlocks', []));
        if ($blockInlines !== []) {
            return $blockInlines;
        }

        $text = trim((string) $node->attr('shortCaption', ''));

        return $text === '' ? null : $this->writeInlines($this->textInlines($text));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function writeShortCaptionBlockInlines(mixed $blocks): array
    {
        if (!is_array($blocks) || $blocks === []) {
            return [];
        }

        $blocks = array_values($blocks);
        if (!$this->allAstNodes($blocks)) {
            return [];
        }

        $inlines = [];
        foreach ($blocks as $block) {
            if (!$block instanceof AstNode || !in_array($block->type, ['plain', 'paragraph'], true)) {
                return [];
            }
            if (!$this->allInlineNodes($block->children)) {
                return [];
            }

            $blockInlines = $this->writeInlines($block->children);
            if ($blockInlines === []) {
                continue;
            }
            if ($inlines !== []) {
                $inlines[] = ['t' => 'SoftBreak'];
            }
            array_push($inlines, ...$blockInlines);
        }

        return $inlines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function writeLongCaptionBlocks(AstNode $node): array
    {
        $captionBlocks = $node->attr('captionBlocks', []);
        if ($captionBlocks !== [] && is_array($captionBlocks) && $this->allAstNodes($captionBlocks)) {
            return $this->writeBlocks(array_values($captionBlocks));
        }

        $captionInlines = $node->attr('captionInlines', []);
        if ($captionInlines !== [] && is_array($captionInlines) && $this->allAstNodes($captionInlines) && $this->allInlineNodes($captionInlines)) {
            return [['t' => 'Plain', 'c' => $this->writeInlines(array_values($captionInlines))]];
        }

        $text = trim((string) $node->attr('caption', ''));

        return $text === '' ? [] : [['t' => 'Plain', 'c' => $this->writeInlines($this->textInlines($text))]];
    }

    /**
     * @return list<array{0:array{t:string}, 1:array<string, mixed>}>
     */
    private function writeTableColumnSpecs(AstNode $node): array
    {
        $alignments = $node->attr('alignments', []);
        $widths = $node->attr('widths', []);
        $columnCount = max(
            is_array($alignments) ? count($alignments) : 0,
            is_array($widths) ? count($widths) : 0,
            TableGeometry::columnCount($node)
        );
        $specs = [];
        for ($index = 0; $index < $columnCount; $index++) {
            $alignment = is_array($alignments) ? (string) ($alignments[$index] ?? 'default') : 'default';
            $width = is_array($widths) ? ($widths[$index] ?? null) : null;
            $specs[] = [
                $this->enum($this->tableAlignmentConstructor($alignment)),
                is_int($width) || is_float($width) ? ['t' => 'ColWidth', 'c' => (float) $width] : ['t' => 'ColWidthDefault'],
            ];
        }

        return $specs;
    }

    /**
     * @return array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:list<array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:list<array<int, mixed>>}>}
     */
    private function writeTableSection(AstNode $section): array
    {
        return [
            $this->attrTuple($section),
            $this->writeTableRows($section->children),
        ];
    }

    /**
     * @return array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:int, 2:list<array<int, mixed>>, 3:list<array<int, mixed>>}
     */
    private function writeTableBody(AstNode $body): array
    {
        $headRows = $body->attr('headRows', []);

        return [
            $this->attrTuple($body),
            max(0, (int) $body->attr('rowHeadColumns', 0)),
            is_array($headRows) ? $this->writeTableRows(array_values($headRows)) : [],
            $this->writeTableRows($body->children),
        ];
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:list<array<int, mixed>>}>
     */
    private function writeTableRows(array $rows): array
    {
        $encoded = [];
        foreach ($rows as $row) {
            if (!$row instanceof AstNode || $row->type !== 'table_row') {
                continue;
            }

            $encoded[] = [
                $this->attrTuple($row),
                $this->writeTableCells($row->children),
            ];
        }

        return $encoded;
    }

    /**
     * @param list<AstNode> $cells
     * @return list<array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:array{t:string}, 2:int, 3:int, 4:list<array<string, mixed>>}>
     */
    private function writeTableCells(array $cells): array
    {
        $encoded = [];
        foreach ($cells as $cell) {
            if (!$cell instanceof AstNode || $cell->type !== 'table_cell') {
                continue;
            }

            $encoded[] = [
                $this->attrTuple($cell),
                $this->enum($this->tableAlignmentConstructor((string) $cell->attr('align', 'default'))),
                max(1, (int) $cell->attr('rowspan', 1)),
                max(1, (int) $cell->attr('colspan', 1)),
                $this->childrenAsBlocks($cell),
            ];
        }

        return $encoded;
    }

    /**
     * @return list<AstNode>
     */
    private function tableSections(AstNode $node, string $type): array
    {
        $sections = [];
        foreach ($node->children as $child) {
            if ($child->type === $type) {
                $sections[] = $child;
            }
        }

        return $sections;
    }

    private function firstTableSection(AstNode $node, string $type): ?AstNode
    {
        foreach ($node->children as $child) {
            if ($child->type === $type) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array<string, mixed>>
     */
    private function writeInlines(array $nodes): array
    {
        return array_map(fn (AstNode $node): array => $this->writeInline($node), $nodes);
    }

    /**
     * @return array<string, mixed>
     */
    private function writeInline(AstNode $node): array
    {
        return match ($node->type) {
            'text' => ['t' => 'Str', 'c' => (string) $node->attr('text', '')],
            'space' => ['t' => 'Space'],
            'softbreak' => ['t' => 'SoftBreak'],
            'linebreak' => ['t' => 'LineBreak'],
            'emph' => ['t' => 'Emph', 'c' => $this->writeInlines($node->children)],
            'strong' => ['t' => 'Strong', 'c' => $this->writeInlines($node->children)],
            'underline' => ['t' => 'Underline', 'c' => $this->writeInlines($node->children)],
            'strikeout' => ['t' => 'Strikeout', 'c' => $this->writeInlines($node->children)],
            'superscript' => ['t' => 'Superscript', 'c' => $this->writeInlines($node->children)],
            'subscript' => ['t' => 'Subscript', 'c' => $this->writeInlines($node->children)],
            'small_caps' => ['t' => 'SmallCaps', 'c' => $this->writeInlines($node->children)],
            'quoted' => ['t' => 'Quoted', 'c' => [$this->enum($node->attr('kind') === 'single' ? 'SingleQuote' : 'DoubleQuote'), $this->writeInlines($node->children)]],
            'code' => ['t' => 'Code', 'c' => [$this->attrTuple($node), (string) $node->attr('text', '')]],
            'math' => ['t' => 'Math', 'c' => [$this->enum($node->attr('display') === true ? 'DisplayMath' : 'InlineMath'), (string) $node->attr('text', '')]],
            'raw_html_inline', 'raw_tex', 'raw_markdown', 'raw_inline' => ['t' => 'RawInline', 'c' => [$this->rawFormat($node), $this->rawText($node)]],
            'citation' => $this->writeCiteInline([$node], $this->citationSourceInlines($node)),
            'citation_group' => $this->writeCiteInline($this->citationGroupChildren($node), $this->citationSourceInlines($node)),
            'link' => ['t' => 'Link', 'c' => [$this->attrTuple($node), $this->writeInlines($node->children), [(string) $node->attr('url', ''), (string) $node->attr('title', '')]]],
            'image' => ['t' => 'Image', 'c' => [$this->attrTuple($node), $this->writeInlines($this->imageLabelInlines($node)), [(string) $node->attr('url', ''), (string) $node->attr('title', '')]]],
            'note' => ['t' => 'Note', 'c' => $this->writeBlocks($node->children)],
            'span' => ['t' => 'Span', 'c' => [$this->attrTuple($node), $this->writeInlines($node->children)]],
            default => throw new \InvalidArgumentException("Unsupported AST inline node for Pandoc JSON: {$node->type}"),
        };
    }

    /**
     * @return list<AstNode>
     */
    private function imageLabelInlines(AstNode $node): array
    {
        if ($node->children !== []) {
            return $node->children;
        }

        return $this->textInlines((string) $node->attr('alt', ''));
    }

    /**
     * @param list<AstNode> $citations
     * @param list<AstNode> $sourceInlines
     * @return array<string, mixed>
     */
    private function writeCiteInline(array $citations, array $sourceInlines): array
    {
        if ($citations === []) {
            throw new \InvalidArgumentException('Citation group must contain at least one citation');
        }

        return [
            't' => 'Cite',
            'c' => [
                array_map(fn (AstNode $citation): array => $this->writeCitationRecord($citation), $citations),
                $this->writeInlines($sourceInlines),
            ],
        ];
    }

    /**
     * @return array{citationId:string, citationPrefix:list<array<string, mixed>>, citationSuffix:list<array<string, mixed>>, citationMode:array{t:string}, citationNoteNum:int, citationHash:int}
     */
    private function writeCitationRecord(AstNode $citation): array
    {
        if ($citation->type !== 'citation') {
            throw new \InvalidArgumentException('Citation group entries must be citation AST nodes');
        }

        $id = (string) $citation->attr('id', '');
        if ($id === '') {
            throw new \InvalidArgumentException('Citation node must contain an id for Pandoc JSON');
        }

        return [
            'citationId' => $id,
            'citationPrefix' => $this->writeInlines($this->citationAffixInlines($citation, 'prefix')),
            'citationSuffix' => $this->writeInlines($this->citationSuffixInlines($citation)),
            'citationMode' => $this->enum($this->citationModeConstructor((string) $citation->attr('mode', 'normal'))),
            'citationNoteNum' => (int) $citation->attr('citationNoteNum', 0),
            'citationHash' => (int) $citation->attr('citationHash', 0),
        ];
    }

    /**
     * @return list<AstNode>
     */
    private function citationSuffixInlines(AstNode $citation): array
    {
        $suffix = $this->citationAffixInlines($citation, 'suffix');
        if ($suffix !== []) {
            return $suffix;
        }

        return $this->citationAffixInlines($citation, 'locator');
    }

    /**
     * @return list<AstNode>
     */
    private function citationAffixInlines(AstNode $citation, string $name): array
    {
        $value = $citation->attr($name, '');
        if ($value instanceof AstNode) {
            return [$value];
        }

        if (is_array($value) && $this->allAstNodes($value)) {
            return array_values($value);
        }

        if (is_scalar($value)) {
            return $this->textInlines(trim((string) $value));
        }

        return [];
    }

    /**
     * @return list<AstNode>
     */
    private function citationSourceInlines(AstNode $node): array
    {
        if ($node->type === 'citation' && $node->children !== [] && $this->allInlineNodes($node->children)) {
            return $node->children;
        }

        $text = (string) $node->attr('text', '');
        if ($text === '' && $node->type === 'citation_group') {
            $text = '[' . implode('; ', array_map(fn (AstNode $citation): string => $this->citationSourceText($citation), $this->citationGroupChildren($node))) . ']';
        } elseif ($text === '' && $node->type === 'citation') {
            $text = $this->citationSourceText($node);
        }

        return $this->textInlines($text);
    }

    private function citationSourceText(AstNode $citation): string
    {
        $id = (string) $citation->attr('id', '');
        $mode = (string) $citation->attr('mode', 'normal');
        $prefix = $this->plainInlineText($this->citationAffixInlines($citation, 'prefix'));
        $suffix = $this->plainInlineText($this->citationSuffixInlines($citation));
        $token = ($mode === 'suppress_author' ? '-@' : '@') . $id;
        $text = $prefix === '' ? $token : $prefix . ' ' . $token;

        return $suffix === '' ? $text : $text . ', ' . $suffix;
    }

    /**
     * @return list<AstNode>
     */
    private function citationGroupChildren(AstNode $node): array
    {
        $children = [];
        foreach ($node->children as $child) {
            if ($child->type !== 'citation') {
                throw new \InvalidArgumentException('Citation group entries must be citation AST nodes');
            }
            $children[] = $child;
        }

        return $children;
    }

    private function citationModeConstructor(string $mode): string
    {
        return match ($mode) {
            'author_in_text' => 'AuthorInText',
            'suppress_author' => 'SuppressAuthor',
            default => 'NormalCitation',
        };
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [new AstNode('text', ['text' => $text])];
        }

        $inlines = [];
        foreach ($parts as $part) {
            $inlines[] = preg_match('/^\s+$/u', $part) === 1
                ? new AstNode('space')
                : new AstNode('text', ['text' => $part]);
        }

        return $inlines;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code', 'math' => (string) $node->attr('text', ''),
                'space', 'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return list<AstNode>
     */
    private function inlineChildrenOrText(AstNode $node): array
    {
        if ($node->children !== []) {
            return $node->children;
        }

        $text = (string) $node->attr('text', '');

        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @return array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}
     */
    private function attrTuple(AstNode $node): array
    {
        $classes = $node->attr('classes', []);
        $attributes = $node->attr('attributes', []);

        return [
            (string) $node->attr('id', ''),
            is_array($classes) ? array_values(array_map(static fn (mixed $class): string => (string) $class, $classes)) : [],
            $this->keyValuePairs(is_array($attributes) ? $attributes : []),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return list<array{0:string, 1:string}>
     */
    private function keyValuePairs(array $attributes): array
    {
        $pairs = [];
        foreach ($attributes as $key => $value) {
            $pairs[] = [(string) $key, (string) $value];
        }

        return $pairs;
    }

    /**
     * @return array{t:string}
     */
    private function enum(string $constructor): array
    {
        return ['t' => $constructor];
    }

    private function listStyleConstructor(string $style): string
    {
        return match ($style) {
            'decimal' => 'Decimal',
            'example' => 'Example',
            'lower_roman' => 'LowerRoman',
            'upper_roman' => 'UpperRoman',
            'lower_alpha' => 'LowerAlpha',
            'upper_alpha' => 'UpperAlpha',
            default => 'DefaultStyle',
        };
    }

    private function listDelimiterConstructor(string $delimiter): string
    {
        return match ($delimiter) {
            'period' => 'Period',
            'one_paren' => 'OneParen',
            'two_parens' => 'TwoParens',
            default => 'DefaultDelim',
        };
    }

    private function tableAlignmentConstructor(string $alignment): string
    {
        return match ($alignment) {
            'left' => 'AlignLeft',
            'right' => 'AlignRight',
            'center' => 'AlignCenter',
            default => 'AlignDefault',
        };
    }

    private function rawFormat(AstNode $node): string
    {
        $format = (string) $node->attr('format', '');
        if ($format !== '') {
            return $format;
        }

        return match ($node->type) {
            'raw_html', 'raw_html_inline' => 'html',
            'raw_tex' => 'latex',
            'raw_markdown' => 'markdown',
            default => 'plain',
        };
    }

    private function rawText(AstNode $node): string
    {
        return match ($node->type) {
            'raw_html', 'raw_html_inline' => (string) $node->attr('text', $node->attr('html', '')),
            'raw_tex' => (string) $node->attr('text', $node->attr('tex', '')),
            'raw_markdown' => (string) $node->attr('text', $node->attr('markdown', '')),
            default => (string) $node->attr('text', ''),
        };
    }

    /**
     * @param list<mixed> $values
     */
    private function allAstNodes(array $values): bool
    {
        foreach ($values as $value) {
            if (!$value instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function allInlineNodes(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!$this->isInlineNode($node)) {
                return false;
            }
        }

        return true;
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'space',
            'softbreak',
            'linebreak',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'quoted',
            'code',
            'math',
            'raw_html_inline',
            'raw_tex',
            'raw_markdown',
            'raw_inline',
            'citation',
            'citation_group',
            'link',
            'image',
            'note',
            'span',
        ], true);
    }
}
