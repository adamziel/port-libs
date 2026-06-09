<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class NativeReader
{
    public function read(string $nativeJson): AstNode
    {
        $native = json_decode($nativeJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($native)) {
            throw new \InvalidArgumentException('Pandoc native JSON must decode to an object');
        }

        $attrs = [
            'meta' => $this->metadata($native['meta'] ?? []),
            'nativeFormat' => 'pandoc-json',
        ];

        if (isset($native['pandoc-api-version'])) {
            $attrs['pandocApiVersion'] = $this->apiVersion($native['pandoc-api-version']);
        }

        $children = [];
        foreach ($this->blocks($native['blocks'] ?? []) as $block) {
            $children[] = $this->block($block);
        }

        return new AstNode('document', $attrs, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            throw new \InvalidArgumentException('Pandoc native JSON meta must be an object');
        }

        $normalized = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Pandoc native JSON meta keys must be strings');
            }
            $normalized[$key] = $this->metaValue($value);
        }

        return $normalized;
    }

    private function metaValue(mixed $value): mixed
    {
        if (!is_array($value) || !is_string($value['t'] ?? null)) {
            throw new \InvalidArgumentException('Pandoc native JSON meta values must be tagged constructors');
        }

        return $value;
    }

    /**
     * @return list<int>
     */
    private function apiVersion(mixed $version): array
    {
        if (!is_array($version)) {
            throw new \InvalidArgumentException('Pandoc native JSON API version must be an array');
        }

        $parts = [];
        foreach ($version as $part) {
            if (!is_int($part)) {
                throw new \InvalidArgumentException('Pandoc native JSON API version parts must be integers');
            }
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(mixed $blocks): array
    {
        if (!is_array($blocks)) {
            throw new \InvalidArgumentException('Pandoc native JSON blocks must be an array');
        }

        $normalized = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || !is_string($block['t'] ?? null)) {
                throw new \InvalidArgumentException('Pandoc native JSON blocks must be tagged constructors');
            }
            $normalized[] = $block;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function block(array $block): AstNode
    {
        $attrs = [
            'constructor' => $block['t'],
            'native' => $block,
        ];

        return match ($block['t']) {
            'Para' => $this->inlineBlock('paragraph', $attrs, $block['c'] ?? []),
            'Plain' => $this->inlineBlock('plain', $attrs, $block['c'] ?? []),
            'Table' => $this->tableBlock($attrs, $block['c'] ?? []),
            default => new AstNode('native_block', $attrs),
        };
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function tableBlock(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 6, 'Pandoc native JSON Table content');
        $attrs = array_replace(
            $attrs,
            $this->attrsFromTuple($tuple[0]),
            $this->tableCaptionAttrs($tuple[1]),
            $this->tableColumnSpecAttrs($tuple[2])
        );

        $children = [];
        $head = $this->tableSection($tuple[3], 'TableHead', 'table_head');
        if ($this->tableSectionHasContent($head)) {
            $children[] = $head;
        }

        foreach ($this->listContent($tuple[4], 'Pandoc native JSON Table bodies') as $body) {
            $bodyNode = $this->tableBody($body);
            if ($this->tableSectionHasContent($bodyNode)) {
                $children[] = $bodyNode;
            }
        }

        $foot = $this->tableSection($tuple[5], 'TableFoot', 'table_foot');
        if ($this->tableSectionHasContent($foot)) {
            $children[] = $foot;
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableCaptionAttrs(mixed $caption): array
    {
        $content = $this->constructorContent($caption, 'Caption', 'Pandoc native JSON Table caption', false);
        $tuple = $this->tuple($content, 2, 'Pandoc native JSON Table caption');
        $attrs = [];

        $shortCaptionInlines = $this->shortCaptionInlines($tuple[0]);
        if ($shortCaptionInlines !== []) {
            $attrs['shortCaptionInlines'] = $shortCaptionInlines;
            $attrs['shortCaption'] = $this->plainTextFromInlines($shortCaptionInlines);
        }

        $captionBlocks = $this->blockNodes($tuple[1]);
        if ($captionBlocks === []) {
            $attrs['caption'] = '';

            return $attrs;
        }

        $attrs['captionBlocks'] = $captionBlocks;
        $attrs['caption'] = $this->plainTextFromBlocks($captionBlocks);

        $captionInlines = $this->singleInlineCaptionBlock($captionBlocks);
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }

        return $attrs;
    }

    /**
     * @return list<AstNode>
     */
    private function shortCaptionInlines(mixed $shortCaption): array
    {
        if ($shortCaption === null || $shortCaption === []) {
            return [];
        }

        $content = $this->constructorContent($shortCaption, 'ShortCaption', 'Pandoc native JSON Table short caption', false);

        return $this->inlines($content);
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function singleInlineCaptionBlock(array $blocks): array
    {
        if (count($blocks) !== 1) {
            return [];
        }

        $block = $blocks[0];
        if (!in_array($block->type, ['plain', 'paragraph'], true)) {
            return [];
        }

        return $block->children;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableColumnSpecAttrs(mixed $colSpecs): array
    {
        $alignments = [];
        $widths = [];
        foreach ($this->listContent($colSpecs, 'Pandoc native JSON Table column specs') as $colSpec) {
            $tuple = $this->tuple($colSpec, 2, 'Pandoc native JSON Table column spec');
            $alignments[] = $this->tableAlignment($tuple[0]);
            $widths[] = $this->tableColumnWidth($tuple[1]);
        }

        if ($alignments === []) {
            return [];
        }

        return [
            'alignments' => $alignments,
            'widths' => $widths,
        ];
    }

    private function tableSection(mixed $section, string $constructor, string $type): AstNode
    {
        $content = $this->constructorContent($section, $constructor, "Pandoc native JSON {$constructor}", false);
        $tuple = $this->tuple($content, 2, "Pandoc native JSON {$constructor}");

        return new AstNode($type, $this->attrsFromTuple($tuple[0]), $this->tableRows($tuple[1]));
    }

    private function tableBody(mixed $body): AstNode
    {
        $content = $this->constructorContent($body, 'TableBody', 'Pandoc native JSON TableBody', false);
        $tuple = $this->tuple($content, 4, 'Pandoc native JSON TableBody');
        $attrs = $this->attrsFromTuple($tuple[0]);

        $rowHeadColumns = $this->taggedInteger($tuple[1], 'RowHeadColumns', 'Pandoc native JSON RowHeadColumns');
        if ($rowHeadColumns > 0) {
            $attrs['rowHeadColumns'] = $rowHeadColumns;
        }

        $headRows = $this->tableRows($tuple[2]);
        if ($headRows !== []) {
            $attrs['headRows'] = $headRows;
        }

        return new AstNode('table_body', $attrs, $this->tableRows($tuple[3]));
    }

    private function tableSectionHasContent(AstNode $section): bool
    {
        if ($section->children !== []) {
            return true;
        }

        if ($section->type === 'table_body') {
            $headRows = $section->attr('headRows', []);
            if (is_array($headRows) && $headRows !== []) {
                return true;
            }
        }

        return $section->attrs !== [];
    }

    /**
     * @return list<AstNode>
     */
    private function tableRows(mixed $rows): array
    {
        $nodes = [];
        foreach ($this->listContent($rows, 'Pandoc native JSON table rows') as $row) {
            $content = $this->constructorContent($row, 'Row', 'Pandoc native JSON Row', false);
            $tuple = $this->tuple($content, 2, 'Pandoc native JSON Row');
            $nodes[] = new AstNode('table_row', $this->attrsFromTuple($tuple[0]), $this->tableCells($tuple[1]));
        }

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function tableCells(mixed $cells): array
    {
        $nodes = [];
        foreach ($this->listContent($cells, 'Pandoc native JSON table cells') as $cell) {
            $nodes[] = $this->tableCell($cell);
        }

        return $nodes;
    }

    private function tableCell(mixed $cell): AstNode
    {
        $content = $this->constructorContent($cell, 'Cell', 'Pandoc native JSON Cell', false);
        $tuple = $this->tuple($content, 5, 'Pandoc native JSON Cell');
        $attrs = $this->attrsFromTuple($tuple[0]);

        $alignment = $this->tableAlignment($tuple[1]);
        if ($alignment !== 'default') {
            $attrs['align'] = $alignment;
        }

        $rowspan = $this->taggedInteger($tuple[2], 'RowSpan', 'Pandoc native JSON RowSpan');
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }

        $colspan = $this->taggedInteger($tuple[3], 'ColSpan', 'Pandoc native JSON ColSpan');
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }

        $blocks = $this->blockNodes($tuple[4]);
        $text = $this->plainTextFromBlocks($blocks);
        if ($text !== '') {
            $attrs['text'] = $text;
        }

        return new AstNode('table_cell', $attrs, $this->tableCellChildren($blocks));
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function tableCellChildren(array $blocks): array
    {
        if (count($blocks) === 1 && in_array($blocks[0]->type, ['plain', 'paragraph'], true)) {
            return $blocks[0]->children;
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function blockNodes(mixed $blocks): array
    {
        $nodes = [];
        foreach ($this->blocks($blocks) as $block) {
            $nodes[] = $this->block($block);
        }

        return $nodes;
    }

    private function tableAlignment(mixed $alignment): string
    {
        return match ($this->constructorTag($alignment, 'Pandoc native JSON table alignment')) {
            'AlignLeft' => 'left',
            'AlignRight' => 'right',
            'AlignCenter' => 'center',
            'AlignDefault' => 'default',
            default => throw new \InvalidArgumentException('Unsupported Pandoc native JSON table alignment'),
        };
    }

    private function tableColumnWidth(mixed $width): ?float
    {
        if (is_int($width) || is_float($width)) {
            return (float) $width;
        }

        $tag = $this->constructorTag($width, 'Pandoc native JSON table column width');
        if ($tag === 'ColWidthDefault') {
            return null;
        }

        if ($tag !== 'ColWidth') {
            throw new \InvalidArgumentException('Unsupported Pandoc native JSON table column width');
        }

        $content = $this->constructorContent($width, 'ColWidth', 'Pandoc native JSON table column width');
        if (is_array($content) && array_is_list($content) && count($content) === 1) {
            $content = $content[0];
        }
        if (!is_int($content) && !is_float($content)) {
            throw new \InvalidArgumentException('Pandoc native JSON ColWidth must be numeric');
        }

        return (float) $content;
    }

    private function taggedInteger(mixed $value, string $tag, string $context): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_array($value) && array_is_list($value) && count($value) === 1 && is_int($value[0])) {
            return $value[0];
        }

        $content = $this->constructorContent($value, $tag, $context);
        if (is_array($content) && array_is_list($content) && count($content) === 1) {
            $content = $content[0];
        }
        if (!is_int($content)) {
            throw new \InvalidArgumentException("{$context} must contain an integer");
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function inlineBlock(string $type, array $attrs, mixed $nativeInlines): AstNode
    {
        $children = $this->inlines($nativeInlines);
        $attrs['text'] = $this->plainTextFromInlines($children);

        return new AstNode($type, $attrs, $children);
    }

    /**
     * @return list<AstNode>
     */
    private function inlines(mixed $nativeInlines): array
    {
        if (!is_array($nativeInlines)) {
            throw new \InvalidArgumentException('Pandoc native JSON inlines must be an array');
        }

        $nodes = [];
        $text = '';
        foreach ($nativeInlines as $inline) {
            if (!is_array($inline) || !is_string($inline['t'] ?? null)) {
                throw new \InvalidArgumentException('Pandoc native JSON inlines must be tagged constructors');
            }

            if ($inline['t'] === 'Str') {
                $content = $inline['c'] ?? '';
                if (!is_string($content)) {
                    throw new \InvalidArgumentException('Pandoc native JSON Str inline content must be a string');
                }
                $text .= $content;
                continue;
            }

            if ($inline['t'] === 'Space') {
                $text .= ' ';
                continue;
            }

            $this->flushText($text, $nodes);
            $nodes[] = $this->inline($inline);
        }
        $this->flushText($text, $nodes);

        return $nodes;
    }

    /**
     * @param array<string, mixed> $inline
     */
    private function inline(array $inline): AstNode
    {
        $attrs = [
            'constructor' => $inline['t'],
            'native' => $inline,
        ];

        return match ($inline['t']) {
            'SoftBreak' => new AstNode('softbreak', $attrs),
            'LineBreak' => new AstNode('linebreak', $attrs),
            'Emph' => new AstNode('emph', $attrs, $this->inlines($inline['c'] ?? [])),
            'Strong' => new AstNode('strong', $attrs, $this->inlines($inline['c'] ?? [])),
            'Code' => $this->codeInline($attrs, $inline['c'] ?? []),
            'Link' => $this->linkInline($attrs, $inline['c'] ?? []),
            'Span' => $this->spanInline($attrs, $inline['c'] ?? []),
            default => new AstNode('native_inline', $attrs),
        };
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function codeInline(array $attrs, mixed $content): AstNode
    {
        if (is_array($content) && isset($content[0], $content[1]) && is_string($content[1])) {
            $attrs = array_replace($attrs, $this->attrsFromTuple($content[0]));
            $attrs['text'] = $content[1];

            return new AstNode('code', $attrs);
        }

        throw new \InvalidArgumentException('Pandoc native JSON Code inline content must contain attributes and text');
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function linkInline(array $attrs, mixed $content): AstNode
    {
        if (!is_array($content) || !isset($content[0], $content[1], $content[2])) {
            throw new \InvalidArgumentException('Pandoc native JSON Link inline content must contain attributes, label, and target');
        }

        $target = $content[2];
        if (!is_array($target) || !is_string($target[0] ?? null) || !is_string($target[1] ?? null)) {
            throw new \InvalidArgumentException('Pandoc native JSON Link target must contain URL and title strings');
        }

        $attrs = array_replace($attrs, $this->attrsFromTuple($content[0]), [
            'url' => $target[0],
            'title' => $target[1],
        ]);

        return new AstNode('link', $attrs, $this->inlines($content[1]));
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function spanInline(array $attrs, mixed $content): AstNode
    {
        if (!is_array($content) || !isset($content[0], $content[1])) {
            throw new \InvalidArgumentException('Pandoc native JSON Span inline content must contain attributes and inlines');
        }

        $attrs = array_replace($attrs, $this->attrsFromTuple($content[0]));

        return new AstNode('span', $attrs, $this->inlines($content[1]));
    }

    /**
     * @return list<mixed>
     */
    private function listContent(mixed $value, string $context): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("{$context} content must be a list");
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function tuple(mixed $value, int $size, string $context): array
    {
        $tuple = $this->listContent($value, $context);
        if (count($tuple) !== $size) {
            throw new \InvalidArgumentException("{$context} must have {$size} entries");
        }

        return $tuple;
    }

    private function constructorTag(mixed $value, string $context): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value) || !is_string($value['t'] ?? null)) {
            throw new \InvalidArgumentException("{$context} must be a tagged constructor");
        }

        return $value['t'];
    }

    private function constructorContent(mixed $value, string $tag, string $context, bool $requireTag = true): mixed
    {
        if (!is_array($value) || !is_string($value['t'] ?? null)) {
            if ($requireTag) {
                throw new \InvalidArgumentException("{$context} must be a {$tag} constructor");
            }

            return $value;
        }

        if ($value['t'] !== $tag) {
            throw new \InvalidArgumentException("{$context} must be a {$tag} constructor");
        }

        return $value['c'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function attrsFromTuple(mixed $attr): array
    {
        if (!is_array($attr)) {
            return [];
        }

        $attrs = [];
        if (is_string($attr[0] ?? null) && $attr[0] !== '') {
            $attrs['id'] = $attr[0];
        }

        if (is_array($attr[1] ?? null)) {
            $classes = [];
            foreach ($attr[1] as $class) {
                if (is_string($class) && $class !== '') {
                    $classes[] = $class;
                }
            }
            if ($classes !== []) {
                $attrs['classes'] = $classes;
            }
        }

        if (is_array($attr[2] ?? null)) {
            $attributes = [];
            foreach ($attr[2] as $pair) {
                if (is_array($pair) && is_string($pair[0] ?? null) && is_string($pair[1] ?? null)) {
                    $attributes[$pair[0]] = $pair[1];
                }
            }
            if ($attributes !== []) {
                $attrs['attributes'] = $attributes;
            }
        }

        return $attrs;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainTextFromInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text' || $node->type === 'code') {
                $text .= (string) $node->attr('text', '');
                continue;
            }

            if ($node->type === 'softbreak') {
                $text .= ' ';
                continue;
            }

            if ($node->type === 'linebreak') {
                $text .= "\n";
                continue;
            }

            $text .= $this->plainTextFromInlines($node->children);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainTextFromBlocks(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $text = trim($this->plainTextFromInlines($block->children));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function flushText(string &$text, array &$nodes): void
    {
        if ($text === '') {
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $text]);
        $text = '';
    }
}
