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

        $meta = $this->meta($document);
        $metaProvenance = $this->metaConstructorProvenance($document);

        return [
            'pandoc-api-version' => $this->apiVersion($document),
            'meta' => $this->writeMetaMap($meta, $this->metaNativeValues($document), $metaProvenance),
            'blocks' => $this->writeBlocks($document->children),
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
     * @return array<string, mixed>
     */
    private function metaNativeValues(AstNode $document): array
    {
        $values = $document->attr('metaNativeValues', []);

        return is_array($values) && !array_is_list($values) ? $values : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function metaConstructorProvenance(AstNode $document): array
    {
        $provenance = $document->attr('metaConstructorProvenance', []);

        return is_array($provenance) && !array_is_list($provenance) ? $provenance : [];
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
     * @param array<string, mixed> $nativeValues
     * @param array<string, mixed> $provenance
     * @param list<string> $path
     * @return array<string, mixed>
     */
    private function writeMetaMap(array $meta, array $nativeValues = [], array $provenance = [], array $path = []): array
    {
        $mapped = [];
        foreach ($meta as $key => $value) {
            $field = (string) $key;
            $fieldPath = [...$path, $field];
            $sourceNative = $nativeValues[$field] ?? $this->metaNativeFromProvenance($provenance, $fieldPath);
            $mapped[$field] = $this->canReuseMetaNativeValue($value, $sourceNative)
                ? $sourceNative
                : $this->writeMetaValue($value, $provenance, $fieldPath);
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $provenance
     * @param list<string> $path
     */
    private function metaNativeFromProvenance(array $provenance, array $path): mixed
    {
        $entry = $provenance[$this->metaProvenancePath($path)] ?? null;

        return is_array($entry) && array_key_exists('native', $entry) ? $entry['native'] : null;
    }

    /**
     * @param list<string> $path
     */
    private function metaProvenancePath(array $path): string
    {
        if ($path === []) {
            return '/';
        }

        return '/' . implode('/', array_map(
            static fn (string $part): string => strtr($part, ['~' => '~0', '/' => '~1']),
            $path
        ));
    }

    /**
     * @param array<string, mixed> $provenance
     * @param list<string> $path
     * @return array<string, mixed>
     */
    private function writeMetaValue(mixed $value, array $provenance = [], array $path = []): array
    {
        $sourceNative = $this->metaNativeFromProvenance($provenance, $path);
        if ($this->canReuseMetaNativeValue($value, $sourceNative)) {
            return $sourceNative;
        }

        if (is_bool($value)) {
            return ['t' => 'MetaBool', 'c' => $value];
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return ['t' => 'MetaString', 'c' => (string) $value];
        }

        if ($value instanceof AstNode) {
            return $this->isInlineNode($value)
                ? ['t' => 'MetaInlines', 'c' => $this->writeInlines([$value])]
                : ['t' => 'MetaBlocks', 'c' => $this->writeBlocks([$value])];
        }

        if (is_array($value)) {
            if ($this->isTaggedMetaValue($value)) {
                return $this->writeCompatibleMetaValue($value);
            }

            if (isset($value['type']) && is_string($value['type'])) {
                return $this->writeTypedMetaValue($value, $provenance, $path);
            }

            if (array_is_list($value)) {
                if ($this->allAstNodes($value)) {
                    $nodes = array_values($value);
                    $inline = $nodes === [] || $this->allInlineNodes($nodes);

                    return [
                        't' => $inline ? 'MetaInlines' : 'MetaBlocks',
                        'c' => $inline ? $this->writeInlines($nodes) : $this->writeBlocks($nodes),
                    ];
                }

                $items = [];
                foreach ($value as $index => $item) {
                    $items[] = $this->writeMetaValue($item, $provenance, [...$path, (string) $index]);
                }

                return ['t' => 'MetaList', 'c' => $items];
            }

            return ['t' => 'MetaMap', 'c' => $this->writeMetaMap($value, [], $provenance, $path)];
        }

        return ['t' => 'MetaString', 'c' => (string) $value];
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, mixed> $provenance
     * @param list<string> $path
     * @return array<string, mixed>
     */
    private function writeTypedMetaValue(array $value, array $provenance = [], array $path = []): array
    {
        $items = is_array($value['items'] ?? null) && array_is_list($value['items']) ? $value['items'] : [];

        return match ($value['type']) {
            'inlines' => ['t' => 'MetaInlines', 'c' => $this->writeInlines($this->metaChildren($value))],
            'blocks' => ['t' => 'MetaBlocks', 'c' => $this->writeBlocks($this->metaChildren($value))],
            'list' => ['t' => 'MetaList', 'c' => array_map(fn (mixed $item, int $index): array => $this->writeMetaValue($item, $provenance, [...$path, (string) $index]), $items, array_keys($items))],
            'map' => ['t' => 'MetaMap', 'c' => $this->writeMetaMap(is_array($value['items'] ?? null) && !array_is_list($value['items']) ? $value['items'] : [], [], $provenance, $path)],
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

        return $this->canReuseMetaNativeValue($meta['__value'], $value)
            ? $value
            : $this->writeMetaValue($meta['__value']);
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
                'pandoc-api-version' => self::DEFAULT_API_VERSION,
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
                'pandoc-api-version' => self::DEFAULT_API_VERSION,
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
            'heading' => ['t' => 'Header', 'c' => [(int) $node->attr('level', 1), $this->attrTuple($node), $this->writeInlines($this->inlineChildrenOrText($node))]],
            'code_block' => ['t' => 'CodeBlock', 'c' => [$this->attrTuple($node), (string) $node->attr('text', '')]],
            'raw_html', 'raw_tex', 'raw_markdown', 'raw_block' => ['t' => 'RawBlock', 'c' => [$this->rawFormatPayload($node), $this->rawText($node)]],
            'blockquote' => ['t' => 'BlockQuote', 'c' => $this->childrenAsBlocks($node)],
            'ordered_list' => ['t' => 'OrderedList', 'c' => [
                [
                    (int) $node->attr('start', 1),
                    $this->enumFromNative($node, 'listStyleNative', $this->listStyleConstructor((string) $node->attr('style', 'default'))),
                    $this->enumFromNative($node, 'listDelimiterNative', $this->listDelimiterConstructor((string) $node->attr('delimiter', 'default'))),
                ],
                $this->writeListItems($node->children),
            ]],
            'bullet_list' => ['t' => 'BulletList', 'c' => $this->writeListItems($node->children)],
            'definition_list' => ['t' => 'DefinitionList', 'c' => $this->writeDefinitionItems($node->children)],
            'line_block' => ['t' => 'LineBlock', 'c' => $this->writeLineBlockLines($node->children)],
            'horizontal_rule' => ['t' => 'HorizontalRule'],
            'null_block' => ['t' => 'Null'],
            'div' => ['t' => 'Div', 'c' => [$this->attrTuple($node), $this->childrenAsBlocks($node)]],
            'figure' => ['t' => 'Figure', 'c' => [$this->attrTuple($node), $this->writeTableCaption($node), $this->writeFigureBlocks($node)]],
            'table' => $this->writeTableBlock($node),
            'native_block' => $this->nativeTaggedConstructor($node, 'block'),
            default => throw new \InvalidArgumentException("Unsupported AST block node for Pandoc JSON: {$node->type}"),
        };
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<array<string, mixed>>
     */
    private function writeBlocks(array $blocks): array
    {
        $encoded = [];
        foreach ($blocks as $block) {
            $native = $this->nativePayload($block);
            if ($native !== null && ($block->type === 'native_block' || $this->canReuseCurrentNativeBlockPayload($block, $native))) {
                $encoded[] = $native;
                continue;
            }

            $encoded[] = $this->writeBlock($block);
        }

        return $encoded;
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
            $blocks = $this->withTaskListSidecar($this->childrenAsBlocks($item), $item);
            $encoded[] = $this->reusableBlockListPayload($item->attr('listItemNative'), $blocks) ?? $blocks;
        }

        return $encoded;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    private function withTaskListSidecar(array $blocks, AstNode $item): array
    {
        $taskChecked = $item->attr('taskChecked', null);
        if (!is_bool($taskChecked)) {
            return $blocks;
        }

        if ($blocks === []) {
            $blocks[] = ['t' => 'Plain', 'c' => []];
        }

        $blocks[0]['taskChecked'] = $taskChecked;

        return $blocks;
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
            $encodedTerm = $this->writeInlines($termInlines);
            $termNative = $term instanceof AstNode
                ? $this->reusableInlineListPayload($term->attr('definitionTermNative'), $encodedTerm)
                : null;
            $termNative ??= $this->reusableInlineListPayload($item->attr('definitionTermNative'), $encodedTerm);

            $definitions = [];
            foreach (array_slice($item->children, 1) as $definition) {
                if ($definition->type === 'definition') {
                    $blocks = $this->childrenAsBlocks($definition);
                    $definitions[] = $this->reusableBlockListPayload($definition->attr('definitionNative'), $blocks) ?? $blocks;
                }
            }
            $definitions = $this->reusableNestedBlockListPayload($item->attr('definitionDefinitionsNative'), $definitions) ?? $definitions;
            $encoded[] = [$termNative ?? $encodedTerm, $definitions];
        }

        return $encoded;
    }

    /**
     * @param list<AstNode> $lines
     * @return list<list<array<string, mixed>>>
     */
    private function writeLineBlockLines(array $lines): array
    {
        $encoded = [];
        foreach ($lines as $line) {
            $inlines = $this->writeInlines($this->inlineChildrenOrText($line));
            $encoded[] = $this->reusableInlineListPayload($line->attr('lineNative'), $inlines) ?? $inlines;
        }

        return $encoded;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>|null
     */
    private function reusableBlockListPayload(mixed $native, array $blocks): ?array
    {
        return is_array($native) && array_is_list($native) && $native === $blocks ? $native : null;
    }

    /**
     * @param list<list<array<string, mixed>>> $definitions
     * @return list<list<array<string, mixed>>>|null
     */
    private function reusableNestedBlockListPayload(mixed $native, array $definitions): ?array
    {
        return is_array($native) && array_is_list($native) && $native === $definitions ? $native : null;
    }

    /**
     * @param list<array<string, mixed>> $inlines
     * @return list<array<string, mixed>>|null
     */
    private function reusableInlineListPayload(mixed $native, array $inlines): ?array
    {
        return is_array($native) && array_is_list($native) && $native === $inlines ? $native : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function childrenAsBlocks(AstNode $node): array
    {
        if ($node->children === []) {
            $inlines = $this->inlineChildrenOrText($node);

            return $inlines === [] ? [] : [['t' => 'Plain', 'c' => $this->writeInlines($inlines)]];
        }

        return $this->mixedChildrenAsBlocks($node->children);
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function mixedChildrenAsBlocks(array $children): array
    {
        if ($this->allInlineNodes($children)) {
            return [['t' => 'Plain', 'c' => $this->writeInlines($children)]];
        }

        $blocks = [];
        $inlines = [];
        foreach ($children as $child) {
            if ($this->isInlineNode($child)) {
                $inlines[] = $child;
                continue;
            }

            if ($inlines !== []) {
                $blocks[] = ['t' => 'Plain', 'c' => $this->writeInlines($inlines)];
                $inlines = [];
            }
            array_push($blocks, ...$this->writeBlocks([$child]));
        }

        if ($inlines !== []) {
            $blocks[] = ['t' => 'Plain', 'c' => $this->writeInlines($inlines)];
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function writeFigureBlocks(AstNode $node): array
    {
        return $this->mixedChildrenAsBlocks($node->children);
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
                $this->writeTableSection($this->firstTableSection($node, 'table_head') ?? new AstNode('table_head'), 'TableHead'),
                array_map(fn (AstNode $body): array => $this->writeTableBody($body), $this->tableSections($node, 'table_body')),
                $this->writeTableSection($this->firstTableSection($node, 'table_foot') ?? new AstNode('table_foot'), 'TableFoot'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function writeTableCaption(AstNode $node): array
    {
        $caption = [
            $this->writeShortCaption($node),
            $this->writeLongCaptionBlocks($node),
        ];

        return $this->reusableCaptionNative($node, $caption) ?? [
            't' => 'Caption',
            'c' => [
                $this->writeShortCaptionMaybe($caption[0]),
                $caption[1],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>>|null $shortCaption
     * @return array<string, mixed>
     */
    private function writeShortCaptionMaybe(?array $shortCaption): array
    {
        if ($shortCaption === null) {
            return ['t' => 'Nothing'];
        }

        return [
            't' => 'Just',
            'c' => [
                't' => 'ShortCaption',
                'c' => [$shortCaption],
            ],
        ];
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

        if (
            is_array($shortCaption)
            && array_is_list($shortCaption)
            && count($shortCaption) === 1
            && is_array($shortCaption[0])
            && !array_is_list($shortCaption[0])
            && ($shortCaption[0]['t'] ?? null) === 'ShortCaption'
        ) {
            $shortCaption = $shortCaption[0]['c'] ?? [];
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
        if (
            is_array($sourceShort)
            && array_is_list($sourceShort)
            && count($sourceShort) === 1
            && is_array($sourceShort[0])
            && !array_is_list($sourceShort[0])
            && ($sourceShort[0]['t'] ?? null) === 'ShortCaption'
        ) {
            return [['t' => 'ShortCaption', 'c' => [$generatedShort]]];
        }

        if (is_array($sourceShort) && !array_is_list($sourceShort) && ($sourceShort['t'] ?? null) === 'ShortCaption') {
            return ['t' => 'ShortCaption', 'c' => [$generatedShort]];
        }

        return $generatedShort;
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
            return $this->mixedChildrenAsBlocks(array_values($captionBlocks));
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
            $alignmentConstructor = $this->tableAlignmentConstructor($alignment);
            $specs[] = [
                $this->taggedNativeAt($alignmentNatives, $index, $alignmentConstructor) ?? $this->enum($alignmentConstructor),
                $this->columnWidthNativeAt($columnWidthNatives, $index, $width)
                    ?? (is_int($width) || is_float($width) ? ['t' => 'ColWidth', 'c' => (float) $width] : ['t' => 'ColWidthDefault']),
            ];
        }

        return $specs;
    }

    /**
     * @return array<string, mixed>|array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:list<array<string, mixed>|array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:list<array<int, mixed>>}>}
     */
    private function writeTableSection(AstNode $section, string $constructor): array
    {
        $payload = [
            $this->attrTuple($section),
            $this->writeTableRows($section->children),
        ];

        return $this->reusableTaggedTableHelperNative($section, $constructor, $payload) ?? $payload;
    }

    /**
     * @return array<string, mixed>|array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:array{t:string, c:int}, 2:list<array<int, mixed>>, 3:list<array<int, mixed>>}
     */
    private function writeTableBody(AstNode $body): array
    {
        $headRows = $body->attr('headRows', []);

        $payload = [
            $this->attrTuple($body),
            $this->integerConstructorNative($body->attr('rowHeadColumnsNative'), 'RowHeadColumns', max(0, (int) $body->attr('rowHeadColumns', 0)))
                ?? ['t' => 'RowHeadColumns', 'c' => max(0, (int) $body->attr('rowHeadColumns', 0))],
            is_array($headRows) ? $this->writeTableRows(array_values($headRows)) : [],
            $this->writeTableRows($body->children),
        ];

        return $this->reusableTaggedTableHelperNative($body, 'TableBody', $payload) ?? $payload;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array<string, mixed>|array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:list<array<int, mixed>>}>
     */
    private function writeTableRows(array $rows): array
    {
        $encoded = [];
        foreach ($rows as $row) {
            if (!$row instanceof AstNode || $row->type !== 'table_row') {
                continue;
            }

            $payload = [
                $this->attrTuple($row),
                $this->writeTableCells($row->children),
            ];
            $encoded[] = $this->reusableTaggedTableHelperNative($row, 'Row', $payload) ?? $payload;
        }

        return $encoded;
    }

    /**
     * @param list<AstNode> $cells
     * @return list<array<string, mixed>|array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:array{t:string}, 2:array{t:string, c:int}, 3:array{t:string, c:int}, 4:list<array<string, mixed>>}>
     */
    private function writeTableCells(array $cells): array
    {
        $encoded = [];
        foreach ($cells as $cell) {
            if (!$cell instanceof AstNode || $cell->type !== 'table_cell') {
                continue;
            }

            $alignmentConstructor = $this->tableAlignmentConstructor((string) $cell->attr('align', 'default'));
            $rowspan = max(1, (int) $cell->attr('rowspan', 1));
            $colspan = max(1, (int) $cell->attr('colspan', 1));
            $payload = [
                $this->attrTuple($cell),
                $this->taggedNative($cell->attr('alignmentNative'), $alignmentConstructor) ?? $this->enum($alignmentConstructor),
                $this->integerConstructorNative($cell->attr('rowSpanNative'), 'RowSpan', $rowspan) ?? ['t' => 'RowSpan', 'c' => $rowspan],
                $this->integerConstructorNative($cell->attr('colSpanNative'), 'ColSpan', $colspan) ?? ['t' => 'ColSpan', 'c' => $colspan],
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
     * @param list<AstNode> $nodes
     * @return list<array<string, mixed>>
     */
    private function writeInlines(array $nodes): array
    {
        $inlines = [];
        foreach ($nodes as $node) {
            $nativeParts = $node->type === 'text' ? $this->nativeTextInlineParts($node) : null;
            if ($nativeParts !== null) {
                array_push($inlines, ...$nativeParts);
                continue;
            }

            $native = $this->nativePayload($node);
            if ($native !== null && ($node->type === 'native_inline' || $this->canReuseCurrentNativeInlinePayload($node, $native))) {
                $inlines[] = $native;
                continue;
            }

            $inlines[] = $this->writeInline($node);
        }

        return $inlines;
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
            'quoted' => ['t' => 'Quoted', 'c' => [
                $this->enumFromNative($node, 'quoteTypeNative', $node->attr('kind') === 'single' ? 'SingleQuote' : 'DoubleQuote'),
                $this->writeInlines($node->children),
            ]],
            'code' => ['t' => 'Code', 'c' => [$this->attrTuple($node), (string) $node->attr('text', '')]],
            'math' => ['t' => 'Math', 'c' => [
                $this->enumFromNative($node, 'mathTypeNative', $node->attr('display') === true ? 'DisplayMath' : 'InlineMath'),
                (string) $node->attr('text', ''),
            ]],
            'raw_html_inline', 'raw_tex', 'raw_markdown', 'raw_inline' => ['t' => 'RawInline', 'c' => [$this->rawFormatPayload($node), $this->rawText($node)]],
            'citation' => $this->writeCiteInline([$node], $this->citationSourceInlines($node)),
            'citation_group' => $this->writeCiteInline($this->citationGroupChildren($node), $this->citationSourceInlines($node)),
            'link' => ['t' => 'Link', 'c' => [$this->attrTuple($node), $this->writeInlines($node->children), $this->targetTuple($node)]],
            'image' => ['t' => 'Image', 'c' => [$this->attrTuple($node), $this->writeInlines($this->imageLabelInlines($node)), $this->targetTuple($node)]],
            'note' => $this->noteInline($node),
            'span' => ['t' => 'Span', 'c' => [$this->attrTuple($node), $this->writeInlines($node->children)]],
            'native_inline' => $this->nativeTaggedConstructor($node, 'inline'),
            default => throw new \InvalidArgumentException("Unsupported AST inline node for Pandoc JSON: {$node->type}"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function nativeTaggedConstructor(AstNode $node, string $context): array
    {
        $native = $this->nativePayload($node);
        if ($native === null) {
            throw new \InvalidArgumentException("Pandoc JSON {$context} native fallback node must carry a tagged native constructor");
        }

        return $native;
    }

    /**
     * @return array<string, mixed>
     */
    private function noteInline(AstNode $node): array
    {
        $note = [
            't' => 'Note',
            'c' => $this->childrenAsBlocks($node),
        ];
        $label = $this->sourceNoteLabel($node);
        if ($label !== null) {
            $note['noteLabel'] = $label;
        }

        return $note;
    }

    private function sourceNoteLabel(AstNode $node): ?string
    {
        $label = trim((string) $node->attr('label', ''));
        if ($label === '' || preg_match('/[\]\s]/u', $label) === 1) {
            return null;
        }

        return $label;
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
    private function canReuseCurrentNativeBlockPayload(AstNode $node, array $native): bool
    {
        if (!$this->isCurrentNativeBlockPayload($native)) {
            return false;
        }

        foreach ($this->blockPayloadReaders($native) as $freshNode) {
            if ($freshNode instanceof AstNode && $this->nodesMatchForNativeReuse($node, $freshNode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $native
     */
    private function canReuseCurrentNativeInlinePayload(AstNode $node, array $native): bool
    {
        if ($this->hasNonCurrentNativeInlinePayload($native) || !$this->isCurrentNativeInlinePayload($native)) {
            return false;
        }

        foreach ($this->inlinePayloadReaders($native) as $freshNode) {
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
            'pandoc-api-version' => self::DEFAULT_API_VERSION,
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
     * @return list<AstNode|null>
     */
    private function inlinePayloadReaders(array $native): array
    {
        $packet = [
            'pandoc-api-version' => self::DEFAULT_API_VERSION,
            'meta' => [],
            'blocks' => [
                ['t' => 'Plain', 'c' => [$native]],
            ],
        ];

        $nodes = [];
        try {
            $nodes[] = (new PandocJsonReader())->readPacket($packet)->children[0]->children[0] ?? null;
        } catch (\Throwable) {
        }

        try {
            $nodes[] = (new NativeReader())->read(json_encode($packet, JSON_THROW_ON_ERROR))->children[0]->children[0] ?? null;
        } catch (\Throwable) {
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $native
     */
    private function isCurrentNativeBlockPayload(array $native): bool
    {
        $tag = $native['t'];
        if ($this->hasNonCurrentNativeInlinePayload($native)) {
            return false;
        }

        if ($this->hasNonCurrentNullaryNativeBlockPayload($native)) {
            return false;
        }

        if ($tag === 'Plain' || $tag === 'Para') {
            $content = $native['c'] ?? null;

            return is_array($content) && array_is_list($content) && !$this->hasLegacyTargetInlinePayload($content);
        }
        if ($tag === 'Figure' || $tag === 'Table') {
            $content = $native['c'] ?? null;

            return is_array($content)
                && array_is_list($content)
                && count($content) === ($tag === 'Figure' ? 3 : 6);
        }

        return in_array($tag, [
            'Header',
            'CodeBlock',
            'RawBlock',
            'BlockQuote',
            'OrderedList',
            'BulletList',
            'DefinitionList',
            'LineBlock',
            'HorizontalRule',
            'Null',
            'Div',
        ], true);
    }

    private function hasNonCurrentNullaryNativeBlockPayload(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (
            !array_is_list($value)
            && is_string($value['t'] ?? null)
            && in_array($value['t'], ['HorizontalRule', 'Null'], true)
            && array_key_exists('c', $value)
        ) {
            return true;
        }

        foreach ($value as $item) {
            if ($this->hasNonCurrentNullaryNativeBlockPayload($item)) {
                return true;
            }
        }

        return false;
    }

    private function hasLegacyTargetInlinePayload(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (!array_is_list($value) && (($value['t'] ?? null) === 'Link' || ($value['t'] ?? null) === 'Image')) {
            $content = $value['c'] ?? null;

            return is_array($content) && array_is_list($content) && count($content) === 2;
        }

        foreach ($value as $item) {
            if ($this->hasLegacyTargetInlinePayload($item)) {
                return true;
            }
        }

        return false;
    }

    private function hasNonCurrentNativeInlinePayload(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (
            !array_is_list($value)
            && is_string($value['t'] ?? null)
            && $this->isNullaryNativeHelperConstructor($value['t'])
            && array_key_exists('c', $value)
            && $value['c'] !== []
        ) {
            return true;
        }

        if (
            !array_is_list($value)
            && is_string($value['t'] ?? null)
            && $this->isNativeInlineConstructorTag($value['t'])
            && !$this->isCurrentNativeInlinePayload($value)
        ) {
            return true;
        }

        foreach ($value as $item) {
            if ($this->hasNonCurrentNativeInlinePayload($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $native
     */
    private function isCurrentNativeInlinePayload(array $native): bool
    {
        $tag = $native['t'];

        if (in_array($tag, ['Space', 'SoftBreak', 'LineBreak'], true)) {
            return !array_key_exists('c', $native);
        }

        if ($tag === 'Str') {
            return is_string($native['c'] ?? null);
        }

        $content = $native['c'] ?? null;
        if (!is_array($content) || !array_is_list($content)) {
            return false;
        }

        return match ($tag) {
            'Emph',
            'Strong',
            'Underline',
            'Strikeout',
            'Superscript',
            'Subscript',
            'SmallCaps',
            'Note' => true,
            'Quoted',
            'Code',
            'Math',
            'RawInline',
            'Cite',
            'Span' => count($content) === 2,
            'Link',
            'Image' => count($content) === 3,
            default => false,
        };
    }

    private function isNativeInlineConstructorTag(string $tag): bool
    {
        return in_array($tag, [
            'Str',
            'Space',
            'SoftBreak',
            'LineBreak',
            'Emph',
            'Strong',
            'Underline',
            'Strikeout',
            'Superscript',
            'Subscript',
            'SmallCaps',
            'Quoted',
            'Code',
            'Math',
            'RawInline',
            'Cite',
            'Link',
            'Image',
            'Note',
            'Span',
        ], true);
    }

    private function isNullaryNativeHelperConstructor(string $constructor): bool
    {
        return in_array($constructor, [
            'SingleQuote',
            'DoubleQuote',
            'InlineMath',
            'DisplayMath',
            'NormalCitation',
            'AuthorInText',
            'SuppressAuthor',
            'DefaultStyle',
            'Decimal',
            'Example',
            'LowerRoman',
            'UpperRoman',
            'LowerAlpha',
            'UpperAlpha',
            'DefaultDelim',
            'Period',
            'OneParen',
            'TwoParens',
            'AlignLeft',
            'AlignRight',
            'AlignCenter',
            'AlignDefault',
            'ColWidthDefault',
        ], true);
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
     * @return array<string, mixed>
     */
    private function enumFromNative(AstNode $node, string $nativeAttr, string $constructor): array
    {
        return $this->taggedNative($node->attr($nativeAttr), $constructor) ?? $this->enum($constructor);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function taggedNative(mixed $native, string $constructor): ?array
    {
        if (!is_array($native) || array_is_list($native) || ($native['t'] ?? null) !== $constructor) {
            return null;
        }

        if ($this->isNullaryNativeHelperConstructor($constructor) && array_key_exists('c', $native) && $native['c'] !== []) {
            unset($native['c']);
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

    /**
     * @return list<mixed>
     */
    private function targetTuple(AstNode $node): array
    {
        $url = (string) $node->attr('url', '');
        $title = (string) $node->attr('title', '');
        $native = $node->attr('targetNative');
        if (
            is_array($native)
            && array_is_list($native)
            && count($native) >= 2
            && $native[0] === $url
            && $native[1] === $title
        ) {
            return $native;
        }

        return [$url, $title];
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
     * @return array<string, mixed>
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

        $record = [
            'citationId' => $id,
            'citationPrefix' => $this->writeInlines($this->citationAffixInlines($citation, 'prefix')),
            'citationSuffix' => $this->writeInlines($this->citationSuffixInlines($citation)),
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

        $sourceInlines = $node->attr('citationSourceInlines', []);
        if (
            $node->type === 'citation_group'
            && is_array($sourceInlines)
            && $sourceInlines !== []
            && $this->allAstNodes($sourceInlines)
            && $this->allInlineNodes($sourceInlines)
        ) {
            return array_values($sourceInlines);
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
                if (array_key_exists('c', $part)) {
                    return null;
                }
                $text .= ' ';
                $normalized[] = $part;
                continue;
            }

            return null;
        }

        return $text === (string) $node->attr('text', '') ? $normalized : null;
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
     * @return array<string, mixed>|array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}
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
        $classes = $node->attr('classes', []);
        $attributes = $node->attr('attributes', []);

        return [
            (string) $node->attr('id', ''),
            is_array($classes) ? array_values(array_map(static fn (mixed $class): string => (string) $class, $classes)) : [],
            $this->keyValuePairs(is_array($attributes) ? $attributes : []),
        ];
    }

    /**
     * @return array<string, mixed>|array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}|null
     */
    private function reusableAttrNative(AstNode $node): ?array
    {
        $native = $node->attr('attrNative');
        $tuple = $this->validAttrTuple($native);
        if ($tuple !== null) {
            return $this->normalizedAttrTuple($tuple) === $this->normalizedAttrTuple($this->generatedAttrTuple($node))
                ? $native
                : null;
        }

        $tagged = $this->taggedNative($native, 'Attr');
        if ($tagged === null) {
            return null;
        }

        $content = $this->validAttrTuplePrefix($tagged['c'] ?? null);
        if ($content === null) {
            return null;
        }

        return $this->normalizedAttrTuple($content) === $this->normalizedAttrTuple($this->generatedAttrTuple($node))
            ? $tagged
            : null;
    }

    /**
     * @return array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}|null
     */
    private function validAttrTuplePrefix(mixed $value): ?array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) < 3) {
            return null;
        }

        return $this->validAttrTuple(array_slice($value, 0, 3));
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

    private function rawFormatPayload(AstNode $node): mixed
    {
        $format = $this->rawFormat($node);
        $native = $this->taggedNative($node->attr('formatNative'), 'Format');
        if ($native === null) {
            return $format;
        }

        $content = $native['c'] ?? null;
        if (is_array($content) && array_is_list($content) && count($content) === 1) {
            $content = $content[0];
        }

        return $content === $format ? $native : $format;
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
            'native_inline',
        ], true);
    }
}
