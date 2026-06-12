<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class NativeReader
{
    private const META_CONSTRUCTORS = [
        'MetaString',
        'MetaBool',
        'MetaInlines',
        'MetaBlocks',
        'MetaList',
        'MetaMap',
    ];

    public function read(string $nativeJson): AstNode
    {
        $native = json_decode($nativeJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($native)) {
            throw new \InvalidArgumentException('Pandoc native JSON must decode to an object');
        }
        $documentAttrs = $this->documentConstructorAttrs($native);
        $native = $this->normalizeDocument($native);
        $rawMeta = $native['meta'] ?? [];
        $normalizedMeta = $this->normalizeMetadataMap($rawMeta);

        $attrs = array_replace($documentAttrs, [
            'meta' => $this->metadata($normalizedMeta),
            'nativeFormat' => 'pandoc-json',
        ]);
        $attrs = array_replace($attrs, $this->metadataConstructorAttrs($rawMeta, $normalizedMeta));

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
        if ($this->isTaggedConstructor($native, 'Pandoc')) {
            $content = $this->tuple($native['c'] ?? null, 2, 'Pandoc native JSON Pandoc content');

            return [
                'meta' => $content[0],
                'blocks' => $content[1],
            ];
        }

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
     * @param array<array-key, mixed> $native
     * @return array<string, mixed>
     */
    private function documentConstructorAttrs(array $native): array
    {
        if (!$this->isTaggedConstructor($native, 'Pandoc')) {
            return [];
        }

        return [
            'documentConstructor' => 'Pandoc',
            'documentNative' => $native,
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

        return $this->withStandardMetaHelpers($normalized);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function withStandardMetaHelpers(array $meta): array
    {
        $titleInlines = $this->metaInlineChildren($meta['title'] ?? null);
        if ($titleInlines !== null && !array_key_exists('titleInlines', $meta)) {
            $meta['titleInlines'] = $titleInlines;
        }

        $authorInlines = $this->metaListInlineChildren($meta['author'] ?? null);
        if ($authorInlines !== null && !array_key_exists('authorInlines', $meta)) {
            $meta['authorInlines'] = $authorInlines;
        }

        $dateInlines = $this->metaInlineChildren($meta['date'] ?? null);
        if ($dateInlines !== null && !array_key_exists('dateInlines', $meta)) {
            $meta['dateInlines'] = $dateInlines;
        }

        return $meta;
    }

    /**
     * @return list<AstNode>|null
     */
    private function metaInlineChildren(mixed $value): ?array
    {
        if (!$this->isTaggedConstructor($value, 'MetaInlines')) {
            return null;
        }

        try {
            return $this->inlines($value['c'] ?? []);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<list<AstNode>>|null
     */
    private function metaListInlineChildren(mixed $value): ?array
    {
        if (!$this->isTaggedConstructor($value, 'MetaList')) {
            return null;
        }

        $items = $value['c'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            return null;
        }

        $mapped = [];
        foreach ($items as $item) {
            $children = $this->metaInlineChildren($item);
            if ($children === null) {
                return null;
            }
            $mapped[] = $children;
        }

        return $mapped === [] ? null : $mapped;
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

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function metadataConstructorAttrs(mixed $rawMetadata, array $metadata): array
    {
        $attrs = [];
        if ($this->taggedMetaConstructor($rawMetadata) === 'MetaMap') {
            $attrs['metaConstructor'] = 'MetaMap';
            $attrs['metaNative'] = $rawMetadata;
        }

        $constructors = [];
        $nativeValues = [];
        foreach ($metadata as $key => $value) {
            $tree = $this->metaConstructorTree($value);
            if ($tree !== null) {
                $constructors[(string) $key] = $tree;
            }

            if ($this->taggedMetaConstructor($value) !== null) {
                $nativeValues[(string) $key] = $value;
            }
        }

        if ($constructors !== []) {
            $attrs['metaConstructors'] = $constructors;
        }
        if ($nativeValues !== []) {
            $attrs['metaNativeValues'] = $nativeValues;
        }

        return $attrs;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function metaConstructorTree(mixed $value): ?array
    {
        $tag = $this->taggedMetaConstructor($value);
        if ($tag === null) {
            return null;
        }

        $tree = ['_constructor' => $tag];
        if ($tag === 'MetaMap') {
            $items = [];
            foreach ($this->metaMapContent($value['c'] ?? null) as $key => $item) {
                $child = $this->metaConstructorTree($item);
                if ($child !== null) {
                    $items[(string) $key] = $child;
                }
            }
            $tree['items'] = $items;
        } elseif ($tag === 'MetaList') {
            $items = [];
            foreach ($this->listContent($value['c'] ?? null, 'Pandoc native JSON MetaList content') as $index => $item) {
                $child = $this->metaConstructorTree($item);
                if ($child !== null) {
                    $items[(int) $index] = $child;
                }
            }
            $tree['items'] = $items;
        }

        return $tree;
    }

    private function taggedMetaConstructor(mixed $value): ?string
    {
        if (!is_array($value) || array_is_list($value) || !isset($value['t']) || !is_string($value['t'])) {
            return null;
        }

        return in_array($value['t'], self::META_CONSTRUCTORS, true) ? $value['t'] : null;
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
        $tuple = $this->listContent($content, 'Pandoc native JSON Table content');
        if (count($tuple) === 5) {
            return $this->legacyTableBlock($attrs, $tuple);
        }
        if (count($tuple) !== 6) {
            throw new \InvalidArgumentException('Pandoc native JSON Table content must have 6 entries');
        }

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
     * @param list<mixed> $tuple
     */
    private function legacyTableBlock(array $attrs, array $tuple): AstNode
    {
        $captionInlines = $this->inlines($this->listContent($tuple[0], 'Pandoc native JSON legacy Table caption'));
        $attrs = array_replace($attrs, $this->legacyTableColumnAttrs($tuple[1], $tuple[2]));
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
            $attrs['captionBlocks'] = [new AstNode('plain', [], $captionInlines)];
            $attrs['caption'] = trim($this->plainTextFromInlines($captionInlines));
        } else {
            $attrs['caption'] = '';
        }

        $children = [];
        $headCells = $this->legacyTableCells($tuple[3], 'Pandoc native JSON legacy Table header cells');
        if ($headCells !== []) {
            $children[] = new AstNode('table_head', [], [new AstNode('table_row', [], $headCells)]);
        }

        $bodyRows = $this->legacyTableRows($tuple[4]);
        if ($bodyRows !== []) {
            $children[] = new AstNode('table_body', [], $bodyRows);
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyTableColumnAttrs(mixed $alignments, mixed $widths): array
    {
        $alignmentValues = $this->listContent($alignments, 'Pandoc native JSON legacy Table alignments');
        $widthValues = $this->listContent($widths, 'Pandoc native JSON legacy Table widths');
        $alignmentConstructors = [];
        $alignmentNatives = [];
        $mappedAlignments = [];
        foreach ($alignmentValues as $alignment) {
            $constructor = $this->constructorTag($alignment, 'Pandoc native JSON legacy Table alignment');
            $alignmentConstructors[] = $constructor;
            $alignmentNatives[] = $alignment;
            $mappedAlignments[] = $this->tableAlignmentFromConstructor($constructor);
        }

        $mappedWidths = [];
        $columnWidthConstructors = [];
        $columnWidthNatives = [];
        foreach ($widthValues as $width) {
            if (!is_int($width) && !is_float($width)) {
                throw new \InvalidArgumentException('Pandoc native JSON legacy Table widths must be numeric');
            }
            $mappedWidths[] = ((float) $width) === 0.0 ? null : (float) $width;
            $columnWidthConstructors[] = ((float) $width) === 0.0 ? 'ColWidthDefault' : 'ColWidth';
            $columnWidthNatives[] = $width;
        }

        $attrs = [];
        if ($mappedAlignments !== []) {
            $attrs['alignments'] = $mappedAlignments;
            $attrs['alignmentConstructors'] = $alignmentConstructors;
            $attrs['alignmentNatives'] = $alignmentNatives;
        }
        if ($mappedWidths !== []) {
            $attrs['widths'] = $mappedWidths;
            $attrs['columnWidthConstructors'] = $columnWidthConstructors;
            $attrs['columnWidthNatives'] = $columnWidthNatives;
        }

        return $attrs;
    }

    /**
     * @return list<AstNode>
     */
    private function legacyTableRows(mixed $rows): array
    {
        $nodes = [];
        foreach ($this->listContent($rows, 'Pandoc native JSON legacy Table rows') as $row) {
            $nodes[] = new AstNode(
                'table_row',
                [],
                $this->legacyTableCells($row, 'Pandoc native JSON legacy Table row cells')
            );
        }

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function legacyTableCells(mixed $cells, string $context): array
    {
        $nodes = [];
        foreach ($this->listContent($cells, $context) as $cell) {
            $blocks = $this->blockNodes($this->listContent($cell, 'Pandoc native JSON legacy Table cell blocks'));
            $attrs = [];
            $text = $this->plainTextFromBlocks($blocks);
            if ($text !== '') {
                $attrs['text'] = $text;
            }
            $nodes[] = new AstNode('table_cell', $attrs, $this->tableCellChildren($blocks));
        }

        return $nodes;
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

        $listStyleConstructor = $this->constructorTag($listAttributes[1], 'Pandoc native JSON list style');
        $listDelimiterConstructor = $this->constructorTag($listAttributes[2], 'Pandoc native JSON list delimiter');

        return new AstNode('ordered_list', array_replace($attrs, [
            'start' => $listAttributes[0],
            'style' => $this->listStyleFromConstructor($listStyleConstructor),
            'delimiter' => $this->listDelimiterFromConstructor($listDelimiterConstructor),
            'listStyleConstructor' => $listStyleConstructor,
            'listStyleNative' => $listAttributes[1],
            'listDelimiterConstructor' => $listDelimiterConstructor,
            'listDelimiterNative' => $listAttributes[2],
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
                ['listItemNative' => $item],
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
                    ['definitionNative' => $definition],
                    $this->blockNodes($this->listContent($definition, 'Pandoc native JSON definition blocks'))
                );
            }

            $termInlines = $this->inlines($tuple[0]);
            $items[] = new AstNode('definition_item', [
                'definitionItemNative' => $item,
                'definitionTermNative' => $tuple[0],
                'definitionDefinitionsNative' => $tuple[1],
            ], [
                new AstNode('definition_term', [
                    'text' => $this->plainTextFromInlines($termInlines),
                    'definitionTermNative' => $tuple[0],
                ], $termInlines),
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
            $lines[] = new AstNode('line', [
                'text' => $this->plainTextFromInlines($inlines),
                'lineNative' => $line,
            ], $inlines);
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
        $attrs = $this->captionConstructorAttrs($caption);

        $shortCaption = $this->shortCaption($tuple[0], $context);
        $attrs = array_replace($attrs, $shortCaption['attrs']);
        $shortCaptionInlines = $shortCaption['children'];
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
     * @return array<string, mixed>
     */
    private function captionConstructorAttrs(mixed $caption): array
    {
        if ($this->isTaggedConstructor($caption, 'Caption')) {
            return [
                'captionConstructor' => 'Caption',
                'captionNative' => $caption,
            ];
        }

        return [];
    }

    /**
     * @return array{children:list<AstNode>, attrs:array<string, mixed>}
     */
    private function shortCaption(mixed $shortCaption, string $context = 'Table'): array
    {
        $attrs = [];
        if ($this->isTaggedConstructor($shortCaption, 'Just') || $this->isTaggedConstructor($shortCaption, 'Nothing')) {
            $attrs['shortCaptionMaybeConstructor'] = $shortCaption['t'];
            $attrs['shortCaptionMaybeNative'] = $shortCaption;
        }

        if ($shortCaption === null || $shortCaption === []) {
            return ['children' => [], 'attrs' => $attrs];
        }

        $shortCaption = $this->unwrapMaybeConstructor($shortCaption);
        if ($shortCaption === null || $shortCaption === []) {
            return ['children' => [], 'attrs' => $attrs];
        }

        if (
            is_array($shortCaption)
            && array_is_list($shortCaption)
            && count($shortCaption) === 1
            && $this->isTaggedConstructor($shortCaption[0], 'ShortCaption')
        ) {
            $shortCaption = $shortCaption[0];
        }

        if ($this->isTaggedConstructor($shortCaption, 'ShortCaption')) {
            $attrs['shortCaptionConstructor'] = 'ShortCaption';
            $attrs['shortCaptionNative'] = $shortCaption;
        }

        $content = $this->constructorContent($shortCaption, 'ShortCaption', "Pandoc native JSON {$context} short caption", false);
        if (is_array($content) && array_is_list($content) && count($content) === 1 && is_array($content[0]) && array_is_list($content[0])) {
            $content = $content[0];
        }

        return [
            'children' => $this->inlines($content),
            'attrs' => $attrs,
        ];
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
        $alignmentConstructors = [];
        $alignmentNatives = [];
        $columnWidthConstructors = [];
        $columnWidthNatives = [];
        foreach ($this->listContent($colSpecs, 'Pandoc native JSON Table column specs') as $colSpec) {
            $tuple = $this->tuple($colSpec, 2, 'Pandoc native JSON Table column spec');
            $alignmentConstructor = $this->constructorTag($tuple[0], 'Pandoc native JSON table alignment');
            $columnWidthConstructor = $this->tableColumnWidthConstructor($tuple[1]);
            $alignments[] = $this->tableAlignmentFromConstructor($alignmentConstructor);
            $widths[] = $this->tableColumnWidth($tuple[1]);
            $alignmentConstructors[] = $alignmentConstructor;
            $alignmentNatives[] = $tuple[0];
            $columnWidthConstructors[] = $columnWidthConstructor;
            $columnWidthNatives[] = $tuple[1];
        }

        if ($alignments === []) {
            return [];
        }

        return [
            'alignments' => $alignments,
            'widths' => $widths,
            'alignmentConstructors' => $alignmentConstructors,
            'alignmentNatives' => $alignmentNatives,
            'columnWidthConstructors' => $columnWidthConstructors,
            'columnWidthNatives' => $columnWidthNatives,
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
        $attrs['rowHeadColumnsConstructor'] = 'RowHeadColumns';
        $attrs['rowHeadColumnsNative'] = $tuple[1];

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
            if (!in_array($key, ['constructor', 'native', 'attrConstructor', 'attrNative'], true)) {
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

        $alignmentConstructor = $this->constructorTag($tuple[1], 'Pandoc native JSON table alignment');
        $alignment = $this->tableAlignmentFromConstructor($alignmentConstructor);
        if ($alignment !== 'default') {
            $attrs['align'] = $alignment;
        }
        $attrs['alignmentConstructor'] = $alignmentConstructor;
        $attrs['alignmentNative'] = $tuple[1];

        $rowspan = $this->taggedInteger($tuple[2], 'RowSpan', 'Pandoc native JSON RowSpan');
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }
        $attrs['rowSpanConstructor'] = 'RowSpan';
        $attrs['rowSpanNative'] = $tuple[2];

        $colspan = $this->taggedInteger($tuple[3], 'ColSpan', 'Pandoc native JSON ColSpan');
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }
        $attrs['colSpanConstructor'] = 'ColSpan';
        $attrs['colSpanNative'] = $tuple[3];

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

    private function tableAlignmentFromConstructor(string $constructor): string
    {
        return match ($constructor) {
            'AlignLeft' => 'left',
            'AlignRight' => 'right',
            'AlignCenter' => 'center',
            'AlignDefault' => 'default',
            default => throw new \InvalidArgumentException('Unsupported Pandoc native JSON table alignment'),
        };
    }

    private function tableColumnWidthConstructor(mixed $width): string
    {
        if (is_int($width) || is_float($width)) {
            return 'ColWidth';
        }

        return $this->constructorTag($width, 'Pandoc native JSON table column width');
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
        $textNativeParts = [];
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
                $textNativeParts[] = $inline;
                continue;
            }

            if ($inline['t'] === 'Space') {
                $text .= ' ';
                $textNativeParts[] = $inline;
                continue;
            }

            $this->flushText($text, $textNativeParts, $nodes);
            $nodes[] = $this->inline($inline);
        }
        $this->flushText($text, $textNativeParts, $nodes);

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
        $quoteTypeNative = $tuple[0];
        $quoteTypeConstructor = $this->constructorTag($quoteTypeNative, 'Pandoc native JSON quote type');

        return new AstNode('quoted', array_replace($attrs, [
            'kind' => $this->quoteTypeFromConstructor($quoteTypeConstructor),
            'quoteTypeConstructor' => $quoteTypeConstructor,
            'quoteTypeNative' => $quoteTypeNative,
        ]), $this->inlines($tuple[1]));
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

        $mathTypeNative = $tuple[0];
        $mathTypeConstructor = $this->constructorTag($mathTypeNative, 'Pandoc native JSON math type');

        return new AstNode('math', array_replace($attrs, [
            'display' => $this->mathDisplayFromConstructor($mathTypeConstructor),
            'mathTypeConstructor' => $mathTypeConstructor,
            'mathTypeNative' => $mathTypeNative,
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
        $citationModeNative = $record['citationMode'] ?? ['t' => 'NormalCitation'];
        $citationModeConstructor = $this->citationModeConstructor($citationModeNative);
        $mode = $this->citationMode($citationModeConstructor);
        $attrs = [
            'id' => $id,
            'text' => $this->citationRecordSourceText($id, $mode, $prefix, $suffix),
            'mode' => $mode,
            'citationModeConstructor' => $citationModeConstructor,
            'citationModeNative' => $citationModeNative,
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

    private function citationModeConstructor(mixed $mode): string
    {
        return $this->constructorTag($mode, 'Pandoc native JSON citation mode');
    }

    private function citationMode(string $constructor): string
    {
        return match ($constructor) {
            'AuthorInText' => 'author_in_text',
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
        if (!is_array($content) || !array_is_list($content)) {
            throw new \InvalidArgumentException('Pandoc native JSON Link inline content must be a list');
        }

        if (count($content) === 3) {
            $attrs = array_replace($attrs, $this->attrsFromTuple($content[0]));
            $label = $content[1];
            $target = $content[2];
        } elseif (count($content) === 2) {
            $label = $content[0];
            $target = $content[1];
        } else {
            throw new \InvalidArgumentException('Pandoc native JSON Link inline content must contain label and target, optionally preceded by attributes');
        }

        $targetNative = $target;
        $target = $this->constructorContent($target, 'Target', 'Pandoc native JSON Link target', false);
        if (!is_array($target) || !is_string($target[0] ?? null) || !is_string($target[1] ?? null)) {
            throw new \InvalidArgumentException('Pandoc native JSON Link target must contain URL and title strings');
        }

        $attrs = array_replace($attrs, [
            'url' => $target[0],
            'title' => $target[1],
            'targetConstructor' => 'Target',
            'targetNative' => $targetNative,
        ]);

        return new AstNode('link', $attrs, $this->inlines($label));
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function imageInline(array $attrs, mixed $content): AstNode
    {
        if (!is_array($content) || !array_is_list($content)) {
            throw new \InvalidArgumentException('Pandoc native JSON Image inline content must be a list');
        }

        if (count($content) === 3) {
            $attrs = array_replace($attrs, $this->attrsFromTuple($content[0]));
            $labelContent = $content[1];
            $target = $content[2];
        } elseif (count($content) === 2) {
            $labelContent = $content[0];
            $target = $content[1];
        } else {
            throw new \InvalidArgumentException('Pandoc native JSON Image inline content must contain label and target, optionally preceded by attributes');
        }

        $targetNative = $target;
        $target = $this->constructorContent($target, 'Target', 'Pandoc native JSON Image target', false);
        if (!is_array($target) || !is_string($target[0] ?? null) || !is_string($target[1] ?? null)) {
            throw new \InvalidArgumentException('Pandoc native JSON Image target must contain URL and title strings');
        }

        $label = $this->inlines($labelContent);
        $attrs = array_replace($attrs, [
            'url' => $target[0],
            'title' => $target[1],
            'targetConstructor' => 'Target',
            'targetNative' => $targetNative,
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

    private function quoteTypeFromConstructor(string $constructor): string
    {
        return match ($constructor) {
            'SingleQuote' => 'single',
            'DoubleQuote' => 'double',
            default => throw new \InvalidArgumentException('Unsupported Pandoc native JSON quote type'),
        };
    }

    private function mathDisplayFromConstructor(string $constructor): bool
    {
        return match ($constructor) {
            'DisplayMath' => true,
            'InlineMath' => false,
            default => throw new \InvalidArgumentException('Unsupported Pandoc native JSON math type'),
        };
    }

    private function listStyleFromConstructor(string $constructor): string
    {
        return match ($constructor) {
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

    private function listDelimiterFromConstructor(string $constructor): string
    {
        return match ($constructor) {
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
        $native = $attr;
        $attr = $this->constructorContent($attr, 'Attr', 'Pandoc native JSON Attr', false);
        if (!is_array($attr)) {
            return [];
        }

        $attrs = [];
        if (array_is_list($attr) && count($attr) >= 3) {
            $attr = array_slice($attr, 0, 3);
            $attrs['attrConstructor'] = 'Attr';
            $attrs['attrNative'] = $this->isTaggedConstructor($native, 'Attr') ? $native : $attr;
            if (!is_string($attr[0])) {
                throw new \InvalidArgumentException('Pandoc native JSON Attr identifier must be a string');
            }
            if (!is_array($attr[1]) || !array_is_list($attr[1])) {
                throw new \InvalidArgumentException('Pandoc native JSON Attr classes must be a list');
            }
            foreach ($attr[1] as $class) {
                if (!is_string($class)) {
                    throw new \InvalidArgumentException('Pandoc native JSON Attr classes must be strings');
                }
            }
            if (!is_array($attr[2]) || !array_is_list($attr[2])) {
                throw new \InvalidArgumentException('Pandoc native JSON Attr key-values must be a list');
            }
            foreach ($attr[2] as $pair) {
                if (!is_array($pair) || !array_is_list($pair) || count($pair) !== 2 || !is_string($pair[0]) || !is_string($pair[1])) {
                    throw new \InvalidArgumentException('Pandoc native JSON Attr key-value entries must be string pairs');
                }
            }
        }

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
     * @param list<array<string, mixed>> $nativeParts
     * @param list<AstNode> $nodes
     */
    private function flushText(string &$text, array &$nativeParts, array &$nodes): void
    {
        if ($text === '') {
            return;
        }

        $attrs = ['text' => $text];
        if ($nativeParts !== []) {
            $attrs['nativeInlineConstructors'] = array_values(array_map(
                static fn (array $part): string => (string) ($part['t'] ?? ''),
                $nativeParts
            ));
            $attrs['nativeInlineParts'] = $nativeParts;
            if (count($nativeParts) === 1) {
                $attrs['constructor'] = $nativeParts[0]['t'];
                $attrs['native'] = $nativeParts[0];
            }
        }

        $nodes[] = new AstNode('text', $attrs);
        $text = '';
        $nativeParts = [];
    }

    private function isMarkdownRawFormat(string $format): bool
    {
        $baseFormat = str_replace('-', '+', $format);
        $baseFormat = explode('+', $baseFormat, 2)[0];

        return $baseFormat === 'markdown' || $format === 'commonmark' || str_starts_with($format, 'gfm');
    }
}
