<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class NativeWriter
{
    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Native writer expects a document node');
        }

        $native = [
            'pandoc-api-version' => $this->apiVersion($document->attr('pandocApiVersion', [1, 23, 1])),
            'meta' => $this->metadata($document->attr('meta', []), $this->metaNativeValues($document)),
            'blocks' => $this->blocks($document->children),
        ];

        return json_encode(
            $native,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";
    }

    /**
     * @return list<int>
     */
    private function apiVersion(mixed $version): array
    {
        if (!is_array($version)) {
            throw new \InvalidArgumentException('Pandoc native API version must be an array');
        }

        $parts = [];
        foreach ($version as $part) {
            if (!is_int($part)) {
                throw new \InvalidArgumentException('Pandoc native API version parts must be integers');
            }
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private function metaNativeValues(AstNode $document): array
    {
        $values = $document->attr('metaNativeValues', []);

        return is_array($values) && !array_is_list($values) ? $values : [];
    }

    /**
     * @param array<string, mixed> $nativeValues
     * @return array<string, mixed>
     */
    private function metadata(mixed $metadata, array $nativeValues = []): array
    {
        if (!is_array($metadata)) {
            throw new \InvalidArgumentException('Pandoc native metadata must be an array');
        }

        $metadata = $this->normalizeStandardMeta($metadata);
        $encoded = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Pandoc native metadata keys must be strings');
            }
            $sourceNative = $nativeValues[$key] ?? null;
            $encoded[$key] = $this->canReuseMetaNativeValue($value, $sourceNative)
                ? $sourceNative
                : $this->metaValue($value);
        }

        return $encoded;
    }

    private function canReuseMetaNativeValue(mixed $value, mixed $sourceNative): bool
    {
        if (
            !is_array($sourceNative)
            || array_is_list($sourceNative)
            || !$this->isTaggedMetaValue($sourceNative)
            || $this->hasLegacyMetaMapWrapper($sourceNative)
        ) {
            return false;
        }

        try {
            $document = (new PandocJsonReader())->readPacket([
                'pandoc-api-version' => [1, 23, 1],
                'meta' => ['__value' => $sourceNative],
                'blocks' => [],
            ]);
        } catch (\Throwable) {
            return false;
        }

        $meta = $document->attr('meta', []);

        $currentValue = $this->metaComparisonValue($value);

        return is_array($meta)
            && array_key_exists('__value', $meta)
            && $this->comparisonValue($currentValue) === $this->comparisonValue($meta['__value']);
    }

    private function metaComparisonValue(mixed $value): mixed
    {
        if (!is_array($value) || array_is_list($value) || !$this->isTaggedMetaValue($value)) {
            return $value;
        }

        try {
            $document = (new PandocJsonReader())->readPacket([
                'pandoc-api-version' => [1, 23, 1],
                'meta' => ['__value' => $value],
                'blocks' => [],
            ]);
        } catch (\Throwable) {
            return $value;
        }

        $meta = $document->attr('meta', []);

        return is_array($meta) && array_key_exists('__value', $meta) ? $meta['__value'] : $value;
    }

    private function hasLegacyMetaMapWrapper(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (!array_is_list($value) && ($value['t'] ?? null) === 'MetaMap') {
            $content = $value['c'] ?? null;
            if (
                is_array($content)
                && !array_is_list($content)
                && count($content) === 1
                && array_key_exists('unMeta', $content)
                && !$this->isTaggedObject($content['unMeta'])
            ) {
                return true;
            }
        }

        foreach ($value as $item) {
            if ($this->hasLegacyMetaMapWrapper($item)) {
                return true;
            }
        }

        return false;
    }

    private function isTaggedObject(mixed $value): bool
    {
        return is_array($value) && !array_is_list($value) && isset($value['t']) && is_string($value['t']);
    }

    private function metaValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return ['t' => 'MetaBool', 'c' => $value];
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return ['t' => 'MetaString', 'c' => (string) $value];
        }

        if ($value === null) {
            return ['t' => 'MetaString', 'c' => ''];
        }

        if ($value instanceof AstNode) {
            return $this->isInlineNode($value)
                ? ['t' => 'MetaInlines', 'c' => $this->inlines([$value])]
                : ['t' => 'MetaBlocks', 'c' => [$this->block($value)]];
        }

        if (is_array($value)) {
            if ($this->isTaggedMetaValue($value)) {
                return $value;
            }

            if (isset($value['type']) && is_string($value['type'])) {
                return $this->typedMetaValue($value);
            }

            if (array_is_list($value)) {
                if ($this->allAstNodes($value)) {
                    $nodes = array_values($value);
                    $inline = $nodes === [] || $this->allInlineNodes($nodes);

                    return [
                        't' => $inline ? 'MetaInlines' : 'MetaBlocks',
                        'c' => $inline ? $this->inlines($nodes) : $this->blocks($nodes),
                    ];
                }

                return ['t' => 'MetaList', 'c' => array_map(fn (mixed $item): mixed => $this->metaValue($item), $value)];
            }

            return ['t' => 'MetaMap', 'c' => $this->metadata($value)];
        }

        throw new \InvalidArgumentException('Pandoc native metadata values must be JSON-compatible values or tagged constructors');
    }

    /**
     * @param array<array-key, mixed> $meta
     * @return array<array-key, mixed>
     */
    private function normalizeStandardMeta(array $meta): array
    {
        $normalized = [];
        foreach ($meta as $key => $value) {
            $field = (string) $key;
            if (in_array($field, ['titleInlines', 'authorInlines', 'authors', 'dateInlines'], true)) {
                continue;
            }
            $normalized[$key] = $value;
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
                $items[] = ['type' => 'inlines', 'children' => $this->textInlineNodes((string) $item)];
            }

            return $items === [] ? null : $items;
        }

        if ($this->isTextScalar($value)) {
            return [['type' => 'inlines', 'children' => $this->textInlineNodes((string) $value)]];
        }

        return null;
    }

    private function isTextScalar(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_float($value);
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
    private function typedMetaValue(array $value): array
    {
        return match ($value['type']) {
            'inlines' => ['t' => 'MetaInlines', 'c' => $this->inlines($this->metaChildren($value))],
            'blocks' => ['t' => 'MetaBlocks', 'c' => $this->blocks($this->metaChildren($value))],
            'list' => ['t' => 'MetaList', 'c' => array_map(fn (mixed $item): mixed => $this->metaValue($item), is_array($value['items'] ?? null) && array_is_list($value['items']) ? $value['items'] : [])],
            'map' => ['t' => 'MetaMap', 'c' => $this->metadata(is_array($value['items'] ?? null) && !array_is_list($value['items']) ? $value['items'] : [])],
            default => ['t' => 'MetaString', 'c' => ''],
        };
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
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function blocks(array $children): array
    {
        $blocks = [];
        foreach ($children as $child) {
            if (!$child instanceof AstNode) {
                throw new \InvalidArgumentException('Native writer children must be AST nodes');
            }
            $native = $this->nativePayload($child);
            if ($native !== null && ($child->type === 'native_block' || $this->canReuseNativeBlockPayload($child, $native))) {
                $blocks[] = $native;
                continue;
            }

            $blocks[] = $this->block($child);
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    private function block(AstNode $node): array
    {
        return match ($node->type) {
            'paragraph' => ['t' => 'Para', 'c' => $this->inlines($node->children)],
            'plain' => ['t' => 'Plain', 'c' => $this->inlines($node->children)],
            'heading' => ['t' => 'Header', 'c' => [
                (int) $node->attr('level', 1),
                $this->attrTuple($node),
                $this->inlines($this->inlineChildrenOrText($node)),
            ]],
            'code_block' => ['t' => 'CodeBlock', 'c' => [$this->attrTuple($node), (string) $node->attr('text', '')]],
            'raw_html', 'raw_tex', 'raw_markdown', 'raw_block' => ['t' => 'RawBlock', 'c' => [$this->rawFormat($node), $this->rawText($node)]],
            'blockquote' => ['t' => 'BlockQuote', 'c' => $this->blocks($node->children)],
            'ordered_list' => ['t' => 'OrderedList', 'c' => [
                [
                    (int) $node->attr('start', 1),
                    $this->enumFromNative($node, 'listStyleNative', $this->listStyleConstructor((string) $node->attr('style', 'default'))),
                    $this->enumFromNative($node, 'listDelimiterNative', $this->listDelimiterConstructor((string) $node->attr('delimiter', 'default'))),
                ],
                $this->listItems($node->children),
            ]],
            'bullet_list' => ['t' => 'BulletList', 'c' => $this->listItems($node->children)],
            'definition_list' => ['t' => 'DefinitionList', 'c' => $this->definitionItems($node->children)],
            'line_block' => ['t' => 'LineBlock', 'c' => array_map(
                fn (AstNode $line): array => $this->inlines($this->inlineChildrenOrText($line)),
                $node->children
            )],
            'horizontal_rule' => ['t' => 'HorizontalRule'],
            'null_block' => ['t' => 'Null'],
            'div' => ['t' => 'Div', 'c' => [$this->attrTuple($node), $this->blocks($node->children)]],
            'figure' => ['t' => 'Figure', 'c' => [$this->attrTuple($node), $this->tableCaption($node), $this->figureBlocks($node)]],
            'table' => $this->tableBlock($node),
            default => throw new \InvalidArgumentException('Native writer can only emit native constructors or supported shared AST blocks'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function tableBlock(AstNode $node): array
    {
        return [
            't' => 'Table',
            'c' => [
                $this->attrTuple($node),
                $this->tableCaption($node),
                $this->tableColumnSpecs($node),
                $this->tableSection($this->firstTableSection($node, 'table_head') ?? new AstNode('table_head'), 'TableHead'),
                array_map(fn (AstNode $body): array => $this->tableBody($body), $this->tableSections($node, 'table_body')),
                $this->tableSection($this->firstTableSection($node, 'table_foot') ?? new AstNode('table_foot'), 'TableFoot'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|array{0:list<array<string, mixed>>|null, 1:list<array<string, mixed>>}
     */
    private function tableCaption(AstNode $node): array
    {
        $caption = [
            $this->shortCaption($node),
            $this->longCaptionBlocks($node),
        ];

        return $this->reusableCaptionNative($node, $caption) ?? $caption;
    }

    /**
     * @param array{0:list<array<string, mixed>>|null, 1:list<array<string, mixed>>} $caption
     * @return array<string, mixed>|null
     */
    private function reusableCaptionNative(AstNode $node, array $caption): ?array
    {
        $native = $node->attr('captionNative');
        if (!is_array($native) || array_is_list($native) || ($native['t'] ?? null) !== 'Caption') {
            return null;
        }

        $content = $native['c'] ?? null;
        if (!is_array($content) || !array_is_list($content) || count($content) !== 2) {
            return null;
        }

        $short = $this->normalizeCaptionShortNative($content[0]);
        if (!($short['valid'] ?? false)) {
            return null;
        }

        if ([$short['value'], $content[1]] === $caption) {
            return $native;
        }

        return [
            't' => 'Caption',
            'c' => [
                $this->captionShortNative($content[0], $caption[0]),
                $caption[1],
            ],
        ];
    }

    /**
     * @return array{valid:bool, value:list<array<string, mixed>>|null}
     */
    private function normalizeCaptionShortNative(mixed $shortCaption): array
    {
        if (is_array($shortCaption) && !array_is_list($shortCaption) && is_string($shortCaption['t'] ?? null)) {
            if ($shortCaption['t'] === 'Nothing') {
                return ['valid' => true, 'value' => null];
            }
            if ($shortCaption['t'] === 'Just') {
                $shortCaption = $shortCaption['c'] ?? null;
            }
        }

        if ($shortCaption === null) {
            return ['valid' => true, 'value' => null];
        }

        if (is_array($shortCaption) && !array_is_list($shortCaption) && ($shortCaption['t'] ?? null) === 'ShortCaption') {
            $shortCaption = $shortCaption['c'] ?? [];
        }

        if (is_array($shortCaption) && array_is_list($shortCaption) && count($shortCaption) === 1 && is_array($shortCaption[0]) && array_is_list($shortCaption[0])) {
            $shortCaption = $shortCaption[0];
        }

        return is_array($shortCaption) && array_is_list($shortCaption)
            ? ['valid' => true, 'value' => $shortCaption]
            : ['valid' => false, 'value' => null];
    }

    /**
     * @param list<array<string, mixed>>|null $generatedShort
     */
    private function captionShortNative(mixed $sourceShort, ?array $generatedShort): mixed
    {
        $normalized = $this->normalizeCaptionShortNative($sourceShort);
        if (($normalized['valid'] ?? false) && $normalized['value'] === $generatedShort) {
            return $sourceShort;
        }

        if (is_array($sourceShort) && !array_is_list($sourceShort) && is_string($sourceShort['t'] ?? null)) {
            if ($sourceShort['t'] === 'Nothing') {
                return $generatedShort === null ? $sourceShort : ['t' => 'Just', 'c' => ['t' => 'ShortCaption', 'c' => [$generatedShort]]];
            }

            if ($sourceShort['t'] === 'Just') {
                return $generatedShort === null
                    ? ['t' => 'Nothing']
                    : ['t' => 'Just', 'c' => $this->shortCaptionNativeContent($sourceShort['c'] ?? null, $generatedShort)];
            }

            if ($sourceShort['t'] === 'ShortCaption') {
                return $generatedShort === null ? null : ['t' => 'ShortCaption', 'c' => [$generatedShort]];
            }
        }

        return $generatedShort;
    }

    /**
     * @param list<array<string, mixed>> $generatedShort
     */
    private function shortCaptionNativeContent(mixed $sourceShort, array $generatedShort): mixed
    {
        if (is_array($sourceShort) && !array_is_list($sourceShort) && ($sourceShort['t'] ?? null) === 'ShortCaption') {
            return ['t' => 'ShortCaption', 'c' => [$generatedShort]];
        }

        return $generatedShort;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function shortCaption(AstNode $node): ?array
    {
        $inlines = $node->attr('shortCaptionInlines', []);
        if ($inlines !== [] && is_array($inlines) && $this->allAstNodes($inlines) && $this->allInlineNodes($inlines)) {
            return $this->inlines(array_values($inlines));
        }

        $blockInlines = $this->shortCaptionBlockInlines($node->attr('shortCaptionBlocks', []));
        if ($blockInlines !== []) {
            return $blockInlines;
        }

        $text = trim((string) $node->attr('shortCaption', ''));

        return $text === '' ? null : $this->textInlines($text);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shortCaptionBlockInlines(mixed $blocks): array
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

            $blockInlines = $this->inlines($block->children);
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
    private function longCaptionBlocks(AstNode $node): array
    {
        $captionBlocks = $node->attr('captionBlocks', []);
        if ($captionBlocks !== [] && is_array($captionBlocks) && $this->allAstNodes($captionBlocks)) {
            return $this->blocks(array_values($captionBlocks));
        }

        $captionInlines = $node->attr('captionInlines', []);
        if ($captionInlines !== [] && is_array($captionInlines) && $this->allAstNodes($captionInlines) && $this->allInlineNodes($captionInlines)) {
            return [['t' => 'Plain', 'c' => $this->inlines(array_values($captionInlines))]];
        }

        $text = trim((string) $node->attr('caption', ''));

        return $text === '' ? [] : [['t' => 'Plain', 'c' => $this->textInlines($text)]];
    }

    /**
     * @return list<array{0:array{t:string}, 1:array<string, mixed>}>
     */
    private function tableColumnSpecs(AstNode $node): array
    {
        $alignments = $node->attr('alignments', []);
        $widths = $node->attr('widths', []);
        $alignmentNatives = $node->attr('alignmentNatives', []);
        $columnWidthNatives = $node->attr('columnWidthNatives', []);
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
                $this->taggedNativeAt($alignmentNatives, $index, $this->tableAlignmentConstructor($alignment))
                    ?? ['t' => $this->tableAlignmentConstructor($alignment)],
                $this->columnWidthNativeAt($columnWidthNatives, $index, $width)
                    ?? (is_int($width) || is_float($width) ? ['t' => 'ColWidth', 'c' => (float) $width] : ['t' => 'ColWidthDefault']),
            ];
        }

        return $specs;
    }

    /**
     * @return array<string, mixed>|array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:list<array<int, mixed>>}
     */
    private function tableSection(AstNode $section, string $constructor): array
    {
        $payload = [
            $this->attrTuple($section),
            $this->tableRows($section->children),
        ];

        return $this->reusableTaggedTableHelperNative($section, $constructor, $payload) ?? $payload;
    }

    /**
     * @return array<string, mixed>|array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:array{t:string, c:int}, 2:list<array<int, mixed>>, 3:list<array<int, mixed>>}
     */
    private function tableBody(AstNode $body): array
    {
        $headRows = $body->attr('headRows', []);

        $payload = [
            $this->attrTuple($body),
            $this->integerConstructorNative($body->attr('rowHeadColumnsNative'), 'RowHeadColumns', max(0, (int) $body->attr('rowHeadColumns', 0)))
                ?? ['t' => 'RowHeadColumns', 'c' => max(0, (int) $body->attr('rowHeadColumns', 0))],
            is_array($headRows) ? $this->tableRows(array_values($headRows)) : [],
            $this->tableRows($body->children),
        ];

        return $this->reusableTaggedTableHelperNative($body, 'TableBody', $payload) ?? $payload;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array<string, mixed>|array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:list<array<int, mixed>>}>
     */
    private function tableRows(array $rows): array
    {
        $encoded = [];
        foreach ($rows as $row) {
            if (!$row instanceof AstNode || $row->type !== 'table_row') {
                continue;
            }

            $payload = [
                $this->attrTuple($row),
                $this->tableCells($row->children),
            ];
            $encoded[] = $this->reusableTaggedTableHelperNative($row, 'Row', $payload) ?? $payload;
        }

        return $encoded;
    }

    /**
     * @param list<AstNode> $cells
     * @return list<array<string, mixed>|array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:array{t:string}, 2:array{t:string, c:int}, 3:array{t:string, c:int}, 4:list<array<string, mixed>>}>
     */
    private function tableCells(array $cells): array
    {
        $encoded = [];
        foreach ($cells as $cell) {
            if (!$cell instanceof AstNode || $cell->type !== 'table_cell') {
                continue;
            }

            $payload = [
                $this->attrTuple($cell),
                $this->taggedNative($cell->attr('alignmentNative'), $this->tableAlignmentConstructor((string) $cell->attr('align', 'default')))
                    ?? ['t' => $this->tableAlignmentConstructor((string) $cell->attr('align', 'default'))],
                $this->integerConstructorNative($cell->attr('rowSpanNative'), 'RowSpan', max(1, (int) $cell->attr('rowspan', 1)))
                    ?? ['t' => 'RowSpan', 'c' => max(1, (int) $cell->attr('rowspan', 1))],
                $this->integerConstructorNative($cell->attr('colSpanNative'), 'ColSpan', max(1, (int) $cell->attr('colspan', 1)))
                    ?? ['t' => 'ColSpan', 'c' => max(1, (int) $cell->attr('colspan', 1))],
                $this->childrenAsBlocks($cell),
            ];
            $encoded[] = $this->reusableTaggedTableHelperNative($cell, 'Cell', $payload) ?? $payload;
        }

        return $encoded;
    }

    /**
     * @param list<mixed> $payload
     * @return array<string, mixed>|null
     */
    private function reusableTaggedTableHelperNative(AstNode $node, string $constructor, array $payload): ?array
    {
        $native = $node->attr('native');
        if (!is_array($native) || array_is_list($native) || ($native['t'] ?? null) !== $constructor) {
            return null;
        }

        return ($native['c'] ?? null) === $payload ? $native : null;
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
     * @return list<array<string, mixed>>
     */
    private function childrenAsBlocks(AstNode $node): array
    {
        if ($node->children === []) {
            $text = trim((string) $node->attr('text', ''));

            return $text === '' ? [] : [['t' => 'Plain', 'c' => $this->textInlines($text)]];
        }

        if ($this->allInlineNodes($node->children)) {
            return [['t' => 'Plain', 'c' => $this->inlines($node->children)]];
        }

        return $this->blocks($node->children);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function figureBlocks(AstNode $node): array
    {
        $blocks = [];
        $inlines = [];
        foreach ($node->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlines[] = $child;
                continue;
            }

            if ($inlines !== []) {
                $blocks[] = ['t' => 'Plain', 'c' => $this->inlines($inlines)];
                $inlines = [];
            }
            $blocks[] = $this->blocks([$child])[0];
        }

        if ($inlines !== []) {
            $blocks[] = ['t' => 'Plain', 'c' => $this->inlines($inlines)];
        }

        return $blocks;
    }

    /**
     * @param list<AstNode> $items
     * @return list<list<array<string, mixed>>>
     */
    private function listItems(array $items): array
    {
        $encoded = [];
        foreach ($items as $item) {
            if (!$item instanceof AstNode || $item->type !== 'list_item') {
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
    private function definitionItems(array $items): array
    {
        $encoded = [];
        foreach ($items as $item) {
            if (!$item instanceof AstNode || $item->type !== 'definition_item') {
                continue;
            }

            $term = $item->children[0] ?? new AstNode('text', ['text' => (string) $item->attr('term', '')]);
            if (!$term instanceof AstNode) {
                $term = new AstNode('text', ['text' => (string) $item->attr('term', '')]);
            }
            $termInlines = $this->isInlineNode($term) ? [$term] : $this->inlineChildrenOrText($term);
            $definitions = [];
            foreach (array_slice($item->children, 1) as $definition) {
                if ($definition instanceof AstNode && $definition->type === 'definition') {
                    $definitions[] = $this->childrenAsBlocks($definition);
                }
            }

            $encoded[] = [$this->inlines($termInlines), $definitions];
        }

        return $encoded;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array<string, mixed>>
     */
    private function inlines(array $nodes): array
    {
        $inlines = [];
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                throw new \InvalidArgumentException('Native writer inline children must be AST nodes');
            }

            array_push($inlines, ...$this->inline($node));
        }

        return $inlines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inline(AstNode $node): array
    {
        $native = $this->nativePayload($node);
        if ($native !== null && ($node->type === 'native_inline' || $this->canReuseNativeInlinePayload($node, $native))) {
            return [$native];
        }

        return match ($node->type) {
            'text' => $this->nativeTextInlineParts($node) ?? $this->textInlines((string) $node->attr('text', '')),
            'space' => [['t' => 'Space']],
            'softbreak' => [['t' => 'SoftBreak']],
            'linebreak' => [['t' => 'LineBreak']],
            'emph' => [['t' => 'Emph', 'c' => $this->inlines($node->children)]],
            'strong' => [['t' => 'Strong', 'c' => $this->inlines($node->children)]],
            'underline' => [['t' => 'Underline', 'c' => $this->inlines($node->children)]],
            'strikeout' => [['t' => 'Strikeout', 'c' => $this->inlines($node->children)]],
            'superscript' => [['t' => 'Superscript', 'c' => $this->inlines($node->children)]],
            'subscript' => [['t' => 'Subscript', 'c' => $this->inlines($node->children)]],
            'small_caps' => [['t' => 'SmallCaps', 'c' => $this->inlines($node->children)]],
            'quoted' => [[
                't' => 'Quoted',
                'c' => [
                    $this->enumFromNative($node, 'quoteTypeNative', $node->attr('kind') === 'single' ? 'SingleQuote' : 'DoubleQuote'),
                    $this->inlines($node->children),
                ],
            ]],
            'code' => [[
                't' => 'Code',
                'c' => [$this->attrTuple($node), (string) $node->attr('text', '')],
            ]],
            'math' => [[
                't' => 'Math',
                'c' => [
                    $this->enumFromNative($node, 'mathTypeNative', $node->attr('display') === true ? 'DisplayMath' : 'InlineMath'),
                    (string) $node->attr('text', ''),
                ],
            ]],
            'raw_html_inline', 'raw_tex', 'raw_markdown', 'raw_inline' => [[
                't' => 'RawInline',
                'c' => [$this->rawFormat($node), $this->rawText($node)],
            ]],
            'citation' => [$this->citeInline([$node], $this->citationSourceInlines($node))],
            'citation_group' => [$this->citeInline($this->citationGroupChildren($node), $this->citationSourceInlines($node))],
            'link' => [[
                't' => 'Link',
                'c' => [
                    $this->attrTuple($node),
                    $this->inlines($node->children),
                    [(string) $node->attr('url', ''), (string) $node->attr('title', '')],
                ],
            ]],
            'image' => [[
                't' => 'Image',
                'c' => [
                    $this->attrTuple($node),
                    $this->inlines($this->imageLabelInlines($node)),
                    [(string) $node->attr('url', ''), (string) $node->attr('title', '')],
                ],
            ]],
            'note' => [[
                't' => 'Note',
                'c' => $this->blocks($node->children),
            ]],
            'span' => [[
                't' => 'Span',
                'c' => [$this->attrTuple($node), $this->inlines($node->children)],
            ]],
            default => $node->children === []
                ? throw new \InvalidArgumentException('Native writer cannot emit unsupported shared AST inline nodes')
                : $this->inlines($node->children),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nativePayload(AstNode $node): ?array
    {
        $native = $node->attr('native');
        if (!is_array($native) || array_is_list($native) || !is_string($native['t'] ?? null) || $native['t'] === '') {
            return null;
        }

        return $native;
    }

    /**
     * @param array<string, mixed> $native
     */
    private function canReuseNativeBlockPayload(AstNode $node, array $native): bool
    {
        foreach ($this->blockPayloadReaders($native) as $freshNode) {
            if ($freshNode instanceof AstNode && $this->nodesMatchForNativeReuse($node, $freshNode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $native
     * @return list<AstNode|null>
     */
    private function blockPayloadReaders(array $native): array
    {
        $packet = [
            'pandoc-api-version' => [1, 23, 1],
            'meta' => [],
            'blocks' => [$native],
        ];

        $nodes = [];
        try {
            $nodes[] = (new PandocJsonReader())->readPacket($packet)->children[0] ?? null;
        } catch (\Throwable) {
        }

        try {
            $nodes[] = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR))->children[0] ?? null;
        } catch (\Throwable) {
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $native
     */
    private function canReuseNativeInlinePayload(AstNode $node, array $native): bool
    {
        try {
            $packet = [
                'pandoc-api-version' => [1, 23, 1],
                'meta' => [],
                'blocks' => [
                    ['t' => 'Plain', 'c' => [$native]],
                ],
            ];
            $jsonDocument = (new PandocJsonReader())->readPacket($packet);
            $freshNode = $jsonDocument->children[0]->children[0] ?? null;
            if ($freshNode instanceof AstNode && $this->nodesMatchForNativeReuse($node, $freshNode)) {
                return true;
            }

            $document = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            return false;
        }

        $freshNode = $document->children[0]->children[0] ?? null;

        return $freshNode instanceof AstNode && $this->nodesMatchForNativeReuse($node, $freshNode);
    }

    private function nodesMatchForNativeReuse(AstNode $left, AstNode $right): bool
    {
        return $this->comparisonNode($left) === $this->comparisonNode($right);
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function comparisonNode(AstNode $node): array
    {
        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            if (in_array($key, ['native', 'constructor', 'attrConstructor', 'attrNative'], true)) {
                continue;
            }
            $attrs[$key] = $this->comparisonValue($value);
        }
        ksort($attrs);

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => array_map(fn (AstNode $child): array => $this->comparisonNode($child), $node->children),
        ];
    }

    private function comparisonValue(mixed $value): mixed
    {
        if ($value instanceof AstNode) {
            return $this->comparisonNode($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->comparisonValue($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $this->comparisonValue($item);
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @return list<AstNode>
     */
    private function imageLabelInlines(AstNode $node): array
    {
        if ($node->children !== []) {
            return $node->children;
        }

        return $this->textInlineNodes((string) $node->attr('alt', ''));
    }

    /**
     * @param list<AstNode> $citations
     * @param list<AstNode> $sourceInlines
     * @return array<string, mixed>
     */
    private function citeInline(array $citations, array $sourceInlines): array
    {
        if ($citations === []) {
            throw new \InvalidArgumentException('Native writer citation group must contain at least one citation');
        }

        return [
            't' => 'Cite',
            'c' => [
                array_map(fn (AstNode $citation): array => $this->citationRecord($citation), $citations),
                $this->inlines($sourceInlines),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function citationRecord(AstNode $citation): array
    {
        if ($citation->type !== 'citation') {
            throw new \InvalidArgumentException('Native writer citation group entries must be citation AST nodes');
        }

        $id = (string) $citation->attr('id', '');
        if ($id === '') {
            throw new \InvalidArgumentException('Native writer citation nodes must contain an id');
        }

        $record = [
            'citationId' => $id,
            'citationPrefix' => $this->inlines($this->citationAffixInlines($citation, 'prefix')),
            'citationSuffix' => $this->inlines($this->citationSuffixInlines($citation)),
            'citationMode' => $this->enumFromNative($citation, 'citationModeNative', $this->citationModeConstructor((string) $citation->attr('mode', 'normal'))),
            'citationNoteNum' => (int) $citation->attr('citationNoteNum', 0),
            'citationHash' => (int) $citation->attr('citationHash', 0),
        ];

        return $this->reusableCitationNative($citation, $record) ?? $record;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>|null
     */
    private function reusableCitationNative(AstNode $citation, array $record): ?array
    {
        $native = $citation->attr('citationNative');
        if (!is_array($native) || array_is_list($native)) {
            return null;
        }

        foreach (['citationId', 'citationPrefix', 'citationSuffix', 'citationMode', 'citationNoteNum', 'citationHash'] as $key) {
            if (!array_key_exists($key, $native) || $native[$key] !== $record[$key]) {
                return null;
            }
        }

        return $native;
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
            return $this->textInlineNodes(trim((string) $value));
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

        return $this->textInlineNodes($text);
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
                throw new \InvalidArgumentException('Native writer citation group entries must be citation AST nodes');
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
     * @return list<array<string, mixed>>
     */
    private function textInlines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/([ \t]+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            throw new \RuntimeException('Unable to split native inline text');
        }

        $inlines = [];
        foreach ($parts as $part) {
            if (preg_match('/^[ \t]+$/', $part) === 1) {
                $inlines[] = ['t' => 'Space'];
                continue;
            }

            $inlines[] = ['t' => 'Str', 'c' => $part];
        }

        return $inlines;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function nativeTextInlineParts(AstNode $node): ?array
    {
        $parts = $node->attr('nativeInlineParts', []);
        if (!is_array($parts) || !array_is_list($parts) || $parts === []) {
            return null;
        }

        $text = '';
        $normalized = [];
        foreach ($parts as $part) {
            if (!is_array($part) || array_is_list($part) || !is_string($part['t'] ?? null)) {
                return null;
            }

            if ($part['t'] === 'Str') {
                if (!is_string($part['c'] ?? null)) {
                    return null;
                }
                $text .= $part['c'];
                $normalized[] = $part;
                continue;
            }

            if ($part['t'] === 'Space') {
                $text .= ' ';
                $normalized[] = $part;
                continue;
            }

            return null;
        }

        return $text === (string) $node->attr('text', '') ? $normalized : null;
    }

    /**
     * @return list<AstNode>
     */
    private function textInlineNodes(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $nodes = [];
        foreach ($this->textInlines($text) as $inline) {
            if (($inline['t'] ?? '') === 'Space') {
                $nodes[] = new AstNode('space');
                continue;
            }

            $nodes[] = new AstNode('text', ['text' => (string) ($inline['c'] ?? '')]);
        }

        return $nodes;
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
        $native = $this->reusableAttrNative($node);

        return $native ?? $this->generatedAttrTuple($node);
    }

    /**
     * @return array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}
     */
    private function generatedAttrTuple(AstNode $node): array
    {
        $id = (string) $node->attr('id', '');
        $classes = [];
        $rawClasses = $node->attr('classes', []);
        if (is_array($rawClasses)) {
            foreach ($rawClasses as $class) {
                if (is_string($class) && $class !== '') {
                    $classes[] = $class;
                }
            }
        }

        $attributes = [];
        $rawAttributes = $node->attr('attributes', []);
        if (is_array($rawAttributes)) {
            foreach ($rawAttributes as $key => $value) {
                if (is_string($key) && is_scalar($value)) {
                    $attributes[] = [$key, (string) $value];
                }
            }
        }

        return [$id, $classes, $attributes];
    }

    /**
     * @return array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}|null
     */
    private function reusableAttrNative(AstNode $node): ?array
    {
        $native = $this->validAttrTuple($node->attr('attrNative'));
        if ($native === null) {
            return null;
        }

        return $this->normalizedAttrTuple($native) === $this->normalizedAttrTuple($this->generatedAttrTuple($node))
            ? $native
            : null;
    }

    /**
     * @return array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}|null
     */
    private function validAttrTuple(mixed $value): ?array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) !== 3 || !is_string($value[0])) {
            return null;
        }

        if (!is_array($value[1]) || !array_is_list($value[1])) {
            return null;
        }

        $classes = [];
        foreach ($value[1] as $class) {
            if (!is_string($class)) {
                return null;
            }
            $classes[] = $class;
        }

        if (!is_array($value[2]) || !array_is_list($value[2])) {
            return null;
        }

        $attributes = [];
        foreach ($value[2] as $pair) {
            if (!is_array($pair) || !array_is_list($pair) || count($pair) !== 2 || !is_string($pair[0]) || !is_string($pair[1])) {
                return null;
            }
            $attributes[] = [$pair[0], $pair[1]];
        }

        return [$value[0], $classes, $attributes];
    }

    /**
     * @param array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>} $tuple
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function normalizedAttrTuple(array $tuple): array
    {
        $attributes = [];
        foreach ($tuple[2] as $pair) {
            $attributes[$pair[0]] = $pair[1];
        }

        return [
            'id' => $tuple[0],
            'classes' => $tuple[1],
            'attributes' => $attributes,
        ];
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

    /**
     * @return array<string, mixed>
     */
    private function enumFromNative(AstNode $node, string $nativeAttr, string $constructor): array
    {
        return $this->taggedNative($node->attr($nativeAttr), $constructor) ?? ['t' => $constructor];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function taggedNative(mixed $native, string $constructor): ?array
    {
        if (!is_array($native) || array_is_list($native) || ($native['t'] ?? null) !== $constructor) {
            return null;
        }

        return $native;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function taggedNativeAt(mixed $natives, int $index, string $constructor): ?array
    {
        if (!is_array($natives) || !array_is_list($natives) || !array_key_exists($index, $natives)) {
            return null;
        }

        return $this->taggedNative($natives[$index], $constructor);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function columnWidthNativeAt(mixed $natives, int $index, mixed $width): ?array
    {
        $constructor = is_int($width) || is_float($width) ? 'ColWidth' : 'ColWidthDefault';
        $native = $this->taggedNativeAt($natives, $index, $constructor);
        if ($native === null) {
            return null;
        }

        if ($constructor === 'ColWidthDefault') {
            return $native;
        }

        $content = $native['c'] ?? null;
        if (is_array($content) && array_is_list($content) && count($content) === 1) {
            $content = $content[0];
        }
        if (!is_int($content) && !is_float($content)) {
            return null;
        }

        return abs((float) $content - (float) $width) < 0.000000000001 ? $native : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function integerConstructorNative(mixed $native, string $constructor, int $integer): ?array
    {
        $tagged = $this->taggedNative($native, $constructor);
        if ($tagged === null) {
            return null;
        }

        $content = $tagged['c'] ?? null;
        if (is_array($content) && array_is_list($content) && count($content) === 1) {
            $content = $content[0];
        }

        return is_int($content) && $content === $integer ? $tagged : null;
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
            if (!$node instanceof AstNode || !$this->isInlineNode($node)) {
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
            'image',
            'note',
            'citation',
            'citation_group',
            'link',
            'image',
            'note',
            'span',
        ], true);
    }
}
