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
        $native = $this->normalizeDocument($native);

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
     * @param array<mixed> $native
     * @return array<string, mixed>
     */
    private function normalizeDocument(array $native): array
    {
        if (!array_is_list($native)) {
            return $native;
        }

        if (count($native) !== 2) {
            throw new \InvalidArgumentException('Legacy Pandoc native JSON must contain metadata and blocks');
        }

        $metadata = $native[0];
        if (!is_array($metadata) || array_is_list($metadata)) {
            throw new \InvalidArgumentException('Legacy Pandoc native JSON metadata must be an object');
        }

        $meta = $metadata['unMeta'] ?? null;
        if (!is_array($meta) || ($meta !== [] && array_is_list($meta))) {
            throw new \InvalidArgumentException('Legacy Pandoc native JSON metadata must contain an unMeta object');
        }

        return [
            'meta' => $meta,
            'blocks' => $native[1],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(mixed $metadata): array
    {
        $metadata = $this->normalizeMetadataMap($metadata);

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

    /**
     * @return array<string, mixed>
     */
    private function normalizeMetadataMap(mixed $metadata): array
    {
        if ($this->isTaggedConstructor($metadata, 'MetaMap')) {
            return $this->metaMapContent($metadata['c'] ?? null);
        }

        if (!is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
            throw new \InvalidArgumentException('Pandoc native JSON meta must be an object');
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function metaMapContent(mixed $content): array
    {
        if (!is_array($content) || ($content !== [] && array_is_list($content))) {
            throw new \InvalidArgumentException('Pandoc native JSON MetaMap content must be an object');
        }

        if (count($content) === 1 && array_key_exists('unMeta', $content) && !$this->isTaggedObject($content['unMeta'])) {
            $unMeta = $content['unMeta'];
            if (!is_array($unMeta) || ($unMeta !== [] && array_is_list($unMeta))) {
                throw new \InvalidArgumentException('Pandoc native JSON MetaMap legacy unMeta content must be an object');
            }

            return $unMeta;
        }

        return $content;
    }

    private function isTaggedConstructor(mixed $value, string $constructor): bool
    {
        return $this->isTaggedObject($value) && $value['t'] === $constructor;
    }

    private function isTaggedObject(mixed $value): bool
    {
        return is_array($value) && !array_is_list($value) && isset($value['t']) && is_string($value['t']);
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
            'Header' => $this->headerBlock($attrs, $block['c'] ?? []),
            'CodeBlock' => $this->codeBlock($attrs, $block['c'] ?? []),
            'RawBlock' => $this->rawBlock($attrs, $block['c'] ?? []),
            'BlockQuote' => new AstNode('blockquote', $attrs, $this->blockNodes($block['c'] ?? [])),
            'OrderedList' => $this->orderedList($attrs, $block['c'] ?? []),
            'BulletList' => new AstNode(
                'bullet_list',
                $attrs,
                $this->listItems($block['c'] ?? [], 'Pandoc native JSON BulletList items')
            ),
            'DefinitionList' => $this->definitionList($attrs, $block['c'] ?? []),
            'LineBlock' => $this->lineBlock($attrs, $block['c'] ?? []),
            'HorizontalRule' => new AstNode('horizontal_rule', $attrs),
            'Null' => new AstNode('null_block', $attrs),
            'Div' => $this->divBlock($attrs, $block['c'] ?? []),
            'Figure' => $this->figureBlock($attrs, $block['c'] ?? []),
            'Table' => $this->tableBlock($attrs, $block['c'] ?? []),
            default => new AstNode('native_block', $attrs),
        };
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function figureBlock(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 3, 'Pandoc native JSON Figure content');
        $attrs = array_replace(
            $attrs,
            $this->attrsFromTuple($tuple[0]),
            $this->captionAttrs($tuple[1], 'Figure')
        );

        return new AstNode('figure', $attrs, $this->figureChildren($this->blockNodes($tuple[2])));
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function figureChildren(array $blocks): array
    {
        if (
            count($blocks) === 1
            && in_array($blocks[0]->type, ['plain', 'paragraph'], true)
            && count($blocks[0]->children) === 1
            && $blocks[0]->children[0]->type === 'image'
        ) {
            return [$blocks[0]->children[0]];
        }

        return $blocks;
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
     * @param array<string, mixed> $attrs
     */
    private function headerBlock(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 3, 'Pandoc native JSON Header content');
        if (!is_int($tuple[0])) {
            throw new \InvalidArgumentException('Pandoc native JSON Header level must be an integer');
        }

        $children = $this->inlines($tuple[2]);
        $attrs = array_replace($attrs, $this->attrsFromTuple($tuple[1]), [
            'level' => $tuple[0],
            'text' => $this->plainTextFromInlines($children),
        ]);

        return new AstNode('heading', $attrs, $children);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function codeBlock(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Pandoc native JSON CodeBlock content');
        if (!is_string($tuple[1])) {
            throw new \InvalidArgumentException('Pandoc native JSON CodeBlock text must be a string');
        }

        return new AstNode('code_block', array_replace($attrs, $this->attrsFromTuple($tuple[0]), [
            'text' => $tuple[1],
        ]));
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function orderedList(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Pandoc native JSON OrderedList content');
        $listAttributes = $this->tuple($tuple[0], 3, 'Pandoc native JSON OrderedList attributes');
        if (!is_int($listAttributes[0])) {
            throw new \InvalidArgumentException('Pandoc native JSON OrderedList start number must be an integer');
        }

        return new AstNode('ordered_list', array_replace($attrs, [
            'start' => $listAttributes[0],
            'style' => $this->listStyle($listAttributes[1]),
            'delimiter' => $this->listDelimiter($listAttributes[2]),
        ]), $this->listItems($tuple[1], 'Pandoc native JSON OrderedList items'));
    }

    /**
     * @return list<AstNode>
     */
    private function listItems(mixed $items, string $context): array
    {
        return array_map(
            fn (mixed $item): AstNode => new AstNode(
                'list_item',
                [],
                $this->blockNodes($this->listContent($item, 'Pandoc native JSON list item'))
            ),
            $this->listContent($items, $context)
        );
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function definitionList(array $attrs, mixed $content): AstNode
    {
        $items = [];
        foreach ($this->listContent($content, 'Pandoc native JSON DefinitionList items') as $item) {
            $tuple = $this->tuple($item, 2, 'Pandoc native JSON DefinitionList item');
            $definitions = [];
            foreach ($this->listContent($tuple[1], 'Pandoc native JSON DefinitionList definitions') as $definition) {
                $definitions[] = new AstNode(
                    'definition',
                    [],
                    $this->blockNodes($this->listContent($definition, 'Pandoc native JSON definition blocks'))
                );
            }

            $termInlines = $this->inlines($tuple[0]);
            $items[] = new AstNode('definition_item', [], [
                new AstNode('definition_term', ['text' => $this->plainTextFromInlines($termInlines)], $termInlines),
                ...$definitions,
            ]);
        }

        return new AstNode('definition_list', $attrs, $items);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function lineBlock(array $attrs, mixed $content): AstNode
    {
        $lines = [];
        foreach ($this->listContent($content, 'Pandoc native JSON LineBlock lines') as $line) {
            $inlines = $this->inlines($line);
            $lines[] = new AstNode('line', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
        }

        return new AstNode('line_block', $attrs, $lines);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function divBlock(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Pandoc native JSON Div content');

        return new AstNode(
            'div',
            array_replace($attrs, $this->attrsFromTuple($tuple[0])),
            $this->blockNodes($tuple[1])
        );
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function rawBlock(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Pandoc native JSON RawBlock content');
        if (!is_string($tuple[0]) || !is_string($tuple[1])) {
            throw new \InvalidArgumentException('Pandoc native JSON RawBlock content must contain format and text strings');
        }

        $format = $tuple[0];
        $text = $tuple[1];
        $attrs = array_replace($attrs, [
            'format' => $format,
            'text' => $text,
        ]);

        $normalizedFormat = strtolower($format);
        if ($this->isMarkdownRawFormat($normalizedFormat)) {
            return new AstNode('raw_markdown', array_replace($attrs, ['markdown' => $text]));
        }

        return match ($normalizedFormat) {
            'tex', 'latex', 'context' => new AstNode('raw_tex', array_replace($attrs, ['tex' => $text])),
            'html' => new AstNode('raw_html', array_replace($attrs, ['html' => $text])),
            default => new AstNode('raw_block', $attrs),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function tableCaptionAttrs(mixed $caption): array
    {
        return $this->captionAttrs($caption, 'Table');
    }

    /**
     * @return array<string, mixed>
     */
    private function captionAttrs(mixed $caption, string $context): array
    {
        $content = $this->constructorContent($caption, 'Caption', "Pandoc native JSON {$context} caption", false);
        $tuple = $this->tuple($content, 2, "Pandoc native JSON {$context} caption");
        $attrs = [];

        $shortCaptionInlines = $this->shortCaptionInlines($tuple[0], $context);
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
    private function shortCaptionInlines(mixed $shortCaption, string $context = 'Table'): array
    {
        if ($shortCaption === null || $shortCaption === []) {
            return [];
        }

        $shortCaption = $this->unwrapMaybeConstructor($shortCaption);
        if (
            is_array($shortCaption)
            && array_is_list($shortCaption)
            && count($shortCaption) === 1
            && is_array($shortCaption[0])
            && ($shortCaption[0]['t'] ?? null) === 'ShortCaption'
        ) {
            $shortCaption = $shortCaption[0];
        }
        $content = $this->constructorContent($shortCaption, 'ShortCaption', "Pandoc native JSON {$context} short caption", false);
        if (is_array($content) && array_is_list($content) && count($content) === 1 && is_array($content[0]) && array_is_list($content[0])) {
            $content = $content[0];
        }

        return $this->inlines($content);
    }

    private function unwrapMaybeConstructor(mixed $value): mixed
    {
        if (!is_array($value) || !is_string($value['t'] ?? null)) {
            return $value;
        }

        if ($value['t'] === 'Just') {
            $content = $value['c'] ?? null;
            if (is_array($content) && array_is_list($content) && count($content) === 1) {
                return $content[0];
            }

            return $content;
        }

        if ($value['t'] === 'Nothing') {
            return [];
        }

        return $value;
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

        return $this->withConstructorPayload(
            new AstNode($type, $this->attrsFromTuple($tuple[0]), $this->tableRows($tuple[1])),
            $constructor,
            $section
        );
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

        return $this->withConstructorPayload(
            new AstNode('table_body', $attrs, $this->tableRows($tuple[3])),
            'TableBody',
            $body
        );
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

        return $this->hasContentAttrs($section);
    }

    private function hasContentAttrs(AstNode $node): bool
    {
        foreach ($node->attrs as $key => $_value) {
            if (!in_array($key, ['constructor', 'native'], true)) {
                return true;
            }
        }

        return false;
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
            $nodes[] = $this->withConstructorPayload(
                new AstNode('table_row', $this->attrsFromTuple($tuple[0]), $this->tableCells($tuple[1])),
                'Row',
                $row
            );
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

        return $this->withConstructorPayload(
            new AstNode('table_cell', $attrs, $this->tableCellChildren($blocks)),
            'Cell',
            $cell
        );
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
            'Underline' => new AstNode('underline', $attrs, $this->inlines($inline['c'] ?? [])),
            'Strikeout' => new AstNode('strikeout', $attrs, $this->inlines($inline['c'] ?? [])),
            'Superscript' => new AstNode('superscript', $attrs, $this->inlines($inline['c'] ?? [])),
            'Subscript' => new AstNode('subscript', $attrs, $this->inlines($inline['c'] ?? [])),
            'SmallCaps' => new AstNode('small_caps', $attrs, $this->inlines($inline['c'] ?? [])),
            'Quoted' => $this->quotedInline($attrs, $inline['c'] ?? []),
            'Code' => $this->codeInline($attrs, $inline['c'] ?? []),
            'Math' => $this->mathInline($attrs, $inline['c'] ?? []),
            'RawInline' => $this->rawInline($attrs, $inline['c'] ?? []),
            'Cite' => $this->citeInline($attrs, $inline['c'] ?? []),
            'Link' => $this->linkInline($attrs, $inline['c'] ?? []),
            'Image' => $this->imageInline($attrs, $inline['c'] ?? []),
            'Note' => new AstNode('note', $attrs, $this->blockNodes($inline['c'] ?? [])),
            'Span' => $this->spanInline($attrs, $inline['c'] ?? []),
            default => new AstNode('native_inline', $attrs),
        };
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function quotedInline(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Pandoc native JSON Quoted inline content');
        $kind = match ($this->constructorTag($tuple[0], 'Pandoc native JSON quote type')) {
            'SingleQuote' => 'single',
            'DoubleQuote' => 'double',
            default => throw new \InvalidArgumentException('Unsupported Pandoc native JSON quote type'),
        };

        return new AstNode('quoted', array_replace($attrs, ['kind' => $kind]), $this->inlines($tuple[1]));
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
    private function mathInline(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Pandoc native JSON Math inline content');
        if (!is_string($tuple[1])) {
            throw new \InvalidArgumentException('Pandoc native JSON Math inline content must contain text');
        }

        $display = match ($this->constructorTag($tuple[0], 'Pandoc native JSON math type')) {
            'DisplayMath' => true,
            'InlineMath' => false,
            default => throw new \InvalidArgumentException('Unsupported Pandoc native JSON math type'),
        };

        return new AstNode('math', array_replace($attrs, [
            'display' => $display,
            'text' => $tuple[1],
        ]));
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function rawInline(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Pandoc native JSON RawInline content');
        if (!is_string($tuple[0]) || !is_string($tuple[1])) {
            throw new \InvalidArgumentException('Pandoc native JSON RawInline content must contain format and text strings');
        }

        $format = $tuple[0];
        $text = $tuple[1];
        $attrs = array_replace($attrs, [
            'format' => $format,
            'text' => $text,
        ]);

        $normalizedFormat = strtolower($format);
        if ($this->isMarkdownRawFormat($normalizedFormat)) {
            return new AstNode('raw_markdown', array_replace($attrs, ['markdown' => $text]));
        }

        return match ($normalizedFormat) {
            'tex', 'latex', 'context' => new AstNode('raw_tex', array_replace($attrs, ['tex' => $text])),
            'html' => new AstNode('raw_html_inline', array_replace($attrs, ['html' => $text])),
            default => new AstNode('raw_inline', $attrs),
        };
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function citeInline(array $attrs, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Pandoc native JSON Cite inline content');
        $records = $this->listContent($tuple[0], 'Pandoc native JSON Cite citation records');
        if ($records === []) {
            throw new \InvalidArgumentException('Pandoc native JSON Cite inline must contain at least one citation record');
        }

        $sourceInlines = $this->inlines($tuple[1]);
        $sourceText = trim($this->plainTextFromInlines($sourceInlines));
        $citations = array_map(fn (mixed $record): AstNode => $this->citationRecord($record), $records);
        if (count($citations) === 1) {
            $citationAttrs = array_replace($attrs, $citations[0]->attrs);
            if ($sourceText !== '') {
                $citationAttrs['text'] = $sourceText;
            }

            return new AstNode('citation', $citationAttrs, $sourceInlines);
        }

        if ($sourceText !== '') {
            $attrs['text'] = $sourceText;
        }

        return new AstNode('citation_group', $attrs, $citations);
    }

    private function citationRecord(mixed $record): AstNode
    {
        if (!is_array($record) || array_is_list($record)) {
            throw new \InvalidArgumentException('Pandoc native JSON Cite citation record must be an object');
        }

        $id = $record['citationId'] ?? null;
        if (!is_string($id) || trim($id) === '') {
            throw new \InvalidArgumentException('Pandoc native JSON Cite citation record must contain a non-empty citationId');
        }

        $prefix = $this->inlines($record['citationPrefix'] ?? []);
        $suffix = $this->inlines($record['citationSuffix'] ?? []);
        $mode = $this->citationMode($record['citationMode'] ?? ['t' => 'NormalCitation']);
        $attrs = [
            'id' => $id,
            'text' => $this->citationRecordSourceText($id, $mode, $prefix, $suffix),
            'mode' => $mode,
        ];
        if ($prefix !== []) {
            $attrs['prefix'] = $prefix;
        }
        if ($suffix !== []) {
            $attrs['suffix'] = $suffix;
        }

        if (array_key_exists('citationNoteNum', $record)) {
            if (!is_int($record['citationNoteNum'])) {
                throw new \InvalidArgumentException('Pandoc native JSON Cite citationNoteNum must be an integer');
            }
            $attrs['citationNoteNum'] = $record['citationNoteNum'];
        }
        if (array_key_exists('citationHash', $record)) {
            if (!is_int($record['citationHash'])) {
                throw new \InvalidArgumentException('Pandoc native JSON Cite citationHash must be an integer');
            }
            $attrs['citationHash'] = $record['citationHash'];
        }

        return new AstNode('citation', array_replace([
            'citationConstructor' => 'Citation',
            'citationNative' => $record,
        ], $attrs), [
            new AstNode('text', ['text' => $attrs['text']]),
        ]);
    }

    private function withConstructorPayload(AstNode $node, string $constructor, mixed $native): AstNode
    {
        return new AstNode(
            $node->type,
            array_replace(['constructor' => $constructor, 'native' => $native], $node->attrs),
            $node->children
        );
    }

    private function citationMode(mixed $mode): string
    {
        return match ($this->constructorTag($mode, 'Pandoc native JSON citation mode')) {
            'AuthorInText' => 'author_in_text',
            'NarrativeCitation' => 'narrative',
            'SuppressAuthor' => 'suppress_author',
            'NormalCitation' => 'normal',
            default => throw new \InvalidArgumentException('Unsupported Pandoc native JSON citation mode'),
        };
    }

    /**
     * @param list<AstNode> $prefix
     * @param list<AstNode> $suffix
     */
    private function citationRecordSourceText(string $id, string $mode, array $prefix, array $suffix): string
    {
        $prefixText = trim($this->plainTextFromInlines($prefix));
        $suffixText = trim($this->plainTextFromInlines($suffix));
        $token = ($mode === 'suppress_author' ? '-@' : '@') . $id;
        $text = $prefixText === '' ? $token : $prefixText . ' ' . $token;

        return $suffixText === '' ? $text : $text . ', ' . $suffixText;
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
    private function imageInline(array $attrs, mixed $content): AstNode
    {
        if (!is_array($content) || !isset($content[0], $content[1], $content[2])) {
            throw new \InvalidArgumentException('Pandoc native JSON Image inline content must contain attributes, label, and target');
        }

        $target = $content[2];
        if (!is_array($target) || !is_string($target[0] ?? null) || !is_string($target[1] ?? null)) {
            throw new \InvalidArgumentException('Pandoc native JSON Image target must contain URL and title strings');
        }

        $label = $this->inlines($content[1]);
        $attrs = array_replace($attrs, $this->attrsFromTuple($content[0]), [
            'url' => $target[0],
            'title' => $target[1],
        ]);
        $alt = trim($this->plainTextFromInlines($label));
        if ($alt !== '') {
            $attrs['alt'] = $alt;
        }

        return new AstNode('image', $attrs, $label);
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

    private function listStyle(mixed $style): string
    {
        return match ($this->constructorTag($style, 'Pandoc native JSON list style')) {
            'DefaultStyle' => 'default',
            'Decimal' => 'decimal',
            'Example' => 'example',
            'LowerRoman' => 'lower_roman',
            'UpperRoman' => 'upper_roman',
            'LowerAlpha' => 'lower_alpha',
            'UpperAlpha' => 'upper_alpha',
            default => throw new \InvalidArgumentException('Unsupported Pandoc native JSON list style'),
        };
    }

    private function listDelimiter(mixed $delimiter): string
    {
        return match ($this->constructorTag($delimiter, 'Pandoc native JSON list delimiter')) {
            'DefaultDelim' => 'default',
            'Period' => 'period',
            'OneParen' => 'one_paren',
            'TwoParens' => 'two_parens',
            default => throw new \InvalidArgumentException('Unsupported Pandoc native JSON list delimiter'),
        };
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

    private function isMarkdownRawFormat(string $format): bool
    {
        $baseFormat = str_replace('-', '+', $format);
        $baseFormat = explode('+', $baseFormat, 2)[0];

        return $baseFormat === 'markdown' || $format === 'commonmark' || str_starts_with($format, 'gfm');
    }
}
