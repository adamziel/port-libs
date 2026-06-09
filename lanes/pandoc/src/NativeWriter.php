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
            'meta' => $this->metadata($document->attr('meta', [])),
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
    private function metadata(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            throw new \InvalidArgumentException('Pandoc native metadata must be an array');
        }

        $native = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Pandoc native metadata keys must be strings');
            }
            $native[$key] = $this->metaValue($value);
        }

        return $native;
    }

    private function metaValue(mixed $value): mixed
    {
        if (!is_array($value) || !is_string($value['t'] ?? null)) {
            throw new \InvalidArgumentException('Pandoc native metadata values must be tagged constructors');
        }

        return $value;
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
            $native = $child->attr('native');
            if (is_array($native) && is_string($native['t'] ?? null)) {
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
                $this->tableSection($this->firstTableSection($node, 'table_head') ?? new AstNode('table_head')),
                array_map(fn (AstNode $body): array => $this->tableBody($body), $this->tableSections($node, 'table_body')),
                $this->tableSection($this->firstTableSection($node, 'table_foot') ?? new AstNode('table_foot')),
            ],
        ];
    }

    /**
     * @return array{0:list<array<string, mixed>>|null, 1:list<array<string, mixed>>}
     */
    private function tableCaption(AstNode $node): array
    {
        return [
            $this->shortCaption($node),
            $this->longCaptionBlocks($node),
        ];
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
                ['t' => $this->tableAlignmentConstructor($alignment)],
                is_int($width) || is_float($width) ? ['t' => 'ColWidth', 'c' => (float) $width] : ['t' => 'ColWidthDefault'],
            ];
        }

        return $specs;
    }

    /**
     * @return array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:list<array<int, mixed>>}
     */
    private function tableSection(AstNode $section): array
    {
        return [
            $this->attrTuple($section),
            $this->tableRows($section->children),
        ];
    }

    /**
     * @return array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:int, 2:list<array<int, mixed>>, 3:list<array<int, mixed>>}
     */
    private function tableBody(AstNode $body): array
    {
        $headRows = $body->attr('headRows', []);

        return [
            $this->attrTuple($body),
            max(0, (int) $body->attr('rowHeadColumns', 0)),
            is_array($headRows) ? $this->tableRows(array_values($headRows)) : [],
            $this->tableRows($body->children),
        ];
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:list<array<int, mixed>>}>
     */
    private function tableRows(array $rows): array
    {
        $encoded = [];
        foreach ($rows as $row) {
            if (!$row instanceof AstNode || $row->type !== 'table_row') {
                continue;
            }

            $encoded[] = [
                $this->attrTuple($row),
                $this->tableCells($row->children),
            ];
        }

        return $encoded;
    }

    /**
     * @param list<AstNode> $cells
     * @return list<array{0:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}, 1:array{t:string}, 2:int, 3:int, 4:list<array<string, mixed>>}>
     */
    private function tableCells(array $cells): array
    {
        $encoded = [];
        foreach ($cells as $cell) {
            if (!$cell instanceof AstNode || $cell->type !== 'table_cell') {
                continue;
            }

            $encoded[] = [
                $this->attrTuple($cell),
                ['t' => $this->tableAlignmentConstructor((string) $cell->attr('align', 'default'))],
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
     * @return list<array<string, mixed>>
     */
    private function childrenAsBlocks(AstNode $node): array
    {
        if ($node->children === []) {
            return [];
        }

        if ($this->allInlineNodes($node->children)) {
            return [['t' => 'Plain', 'c' => $this->inlines($node->children)]];
        }

        return $this->blocks($node->children);
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
        $native = $node->attr('native');
        if (is_array($native) && is_string($native['t'] ?? null)) {
            return [$native];
        }

        return match ($node->type) {
            'text' => $this->textInlines((string) $node->attr('text', '')),
            'space' => [['t' => 'Space']],
            'softbreak' => [['t' => 'SoftBreak']],
            'linebreak' => [['t' => 'LineBreak']],
            'emph' => [['t' => 'Emph', 'c' => $this->inlines($node->children)]],
            'strong' => [['t' => 'Strong', 'c' => $this->inlines($node->children)]],
            'code' => [[
                't' => 'Code',
                'c' => [$this->attrTuple($node), (string) $node->attr('text', '')],
            ]],
            'link' => [[
                't' => 'Link',
                'c' => [
                    $this->attrTuple($node),
                    $this->inlines($node->children),
                    [(string) $node->attr('url', ''), (string) $node->attr('title', '')],
                ],
            ]],
            default => $node->children === []
                ? throw new \InvalidArgumentException('Native writer cannot emit unsupported shared AST inline nodes')
                : $this->inlines($node->children),
        };
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
     * @return array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}
     */
    private function attrTuple(AstNode $node): array
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
            'code',
            'link',
        ], true);
    }
}
