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

        return is_array($meta) && !array_is_list($meta) ? $meta : [];
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
            'link' => ['t' => 'Link', 'c' => [$this->attrTuple($node), $this->writeInlines($node->children), [(string) $node->attr('url', ''), (string) $node->attr('title', '')]]],
            'image' => ['t' => 'Image', 'c' => [$this->attrTuple($node), $this->writeInlines($node->children), [(string) $node->attr('url', ''), (string) $node->attr('title', '')]]],
            'note' => ['t' => 'Note', 'c' => $this->writeBlocks($node->children)],
            'span' => ['t' => 'Span', 'c' => [$this->attrTuple($node), $this->writeInlines($node->children)]],
            default => throw new \InvalidArgumentException("Unsupported AST inline node for Pandoc JSON: {$node->type}"),
        };
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
            'link',
            'image',
            'note',
            'span',
        ], true);
    }
}
