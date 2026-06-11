<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocJsonReader
{
    private const META_CONSTRUCTORS = [
        'MetaString',
        'MetaBool',
        'MetaInlines',
        'MetaBlocks',
        'MetaList',
        'MetaMap',
    ];

    public function read(string $json): AstNode
    {
        try {
            $packet = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Invalid Pandoc JSON packet: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($packet)) {
            throw new \InvalidArgumentException('Pandoc JSON packet must be an object');
        }

        return $this->readPacket($packet);
    }

    /**
     * @param array<array-key, mixed> $packet
     */
    public function readPacket(array $packet): AstNode
    {
        $legacyTuplePacket = array_is_list($packet);
        $packet = $this->normalizePacket($packet);
        $blocks = $packet['blocks'] ?? null;
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new \InvalidArgumentException('Pandoc JSON packet must contain a blocks array');
        }

        $attrs = [];
        $apiVersion = null;
        if (isset($packet['pandoc-api-version'])) {
            $apiVersion = $this->readApiVersion($packet['pandoc-api-version']);
            $attrs['pandocApiVersion'] = $apiVersion;
        }

        $meta = $this->normalizeMeta($packet['meta'] ?? [], $apiVersion, $legacyTuplePacket);
        if ($meta !== []) {
            $attrs['meta'] = $this->withStandardMetaHelpers($this->readMetaMap($meta));
        }

        return new AstNode('document', $attrs, array_map(fn (mixed $block): AstNode => $this->readBlock($block), $blocks));
    }

    /**
     * @param array<array-key, mixed> $packet
     * @return array<string, mixed>
     */
    private function normalizePacket(array $packet): array
    {
        if (!array_is_list($packet)) {
            return $packet;
        }

        if (count($packet) !== 2) {
            throw new \InvalidArgumentException('Pandoc JSON packet must be an object or legacy [meta, blocks] tuple');
        }

        return [
            'meta' => $packet[0],
            'blocks' => $packet[1],
        ];
    }

    /**
     * @param list<int>|null $apiVersion
     * @return array<string, mixed>
     */
    private function normalizeMeta(mixed $meta, ?array $apiVersion, bool $legacyTuplePacket): array
    {
        if ($this->looksLikeMetaConstructor($meta)) {
            [$tag, $content] = $this->tagged($meta, 'meta');
            if ($tag !== 'MetaMap') {
                throw new \InvalidArgumentException('Pandoc JSON meta constructor must be MetaMap');
            }

            return $this->metaMapContent($content);
        }

        if (!is_array($meta) || ($meta !== [] && array_is_list($meta))) {
            throw new \InvalidArgumentException('Pandoc JSON meta must be an object');
        }

        if ($this->taggedMetaConstructor($meta) === 'MetaMap') {
            return $this->objectContent($meta['c'] ?? null, 'Pandoc JSON meta MetaMap');
        }

        if (
            count($meta) === 1
            && array_key_exists('unMeta', $meta)
            && !$this->isTaggedObject($meta['unMeta'])
            && $this->shouldUnwrapLegacyMetaEnvelope($apiVersion, $legacyTuplePacket)
        ) {
            $unMeta = $meta['unMeta'];
            if (!is_array($unMeta) || ($unMeta !== [] && array_is_list($unMeta))) {
                throw new \InvalidArgumentException('Pandoc JSON meta.unMeta must be an object');
            }

            return $unMeta;
        }

        if (count($meta) === 1 && array_key_exists('unMeta', $meta) && $this->taggedMetaConstructor($meta['unMeta']) === 'MetaMap') {
            $unMeta = $meta['unMeta'];

            return $this->objectContent($unMeta['c'] ?? null, 'Pandoc JSON meta.unMeta MetaMap');
        }

        return $meta;
    }

    /**
     * @param list<int>|null $apiVersion
     */
    private function shouldUnwrapLegacyMetaEnvelope(?array $apiVersion, bool $legacyTuplePacket): bool
    {
        if ($legacyTuplePacket || $apiVersion === null) {
            return true;
        }

        return ($apiVersion[0] ?? 0) === 1 && ($apiVersion[1] ?? 0) <= 17;
    }

    private function isTaggedObject(mixed $value): bool
    {
        return is_array($value) && !array_is_list($value) && isset($value['t']) && is_string($value['t']);
    }

    private function taggedMetaConstructor(mixed $value): ?string
    {
        if (!is_array($value) || array_is_list($value) || !isset($value['t']) || !is_string($value['t'])) {
            return null;
        }

        return in_array($value['t'], self::META_CONSTRUCTORS, true) ? $value['t'] : null;
    }

    /**
     * @return list<int>
     */
    private function readApiVersion(mixed $version): array
    {
        if (!is_array($version) || !array_is_list($version) || $version === []) {
            throw new \InvalidArgumentException('pandoc-api-version must be a non-empty integer array');
        }

        return array_map(static function (mixed $part): int {
            if (!is_int($part)) {
                throw new \InvalidArgumentException('pandoc-api-version entries must be integers');
            }

            return $part;
        }, $version);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function readMetaMap(array $meta): array
    {
        $mapped = [];
        foreach ($meta as $key => $value) {
            $mapped[$key] = $this->readMetaValue($value);
        }

        return $mapped;
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
        if (!is_array($value) || ($value['type'] ?? null) !== 'inlines') {
            return null;
        }

        $children = $value['children'] ?? null;
        if (!is_array($children) || !array_is_list($children)) {
            return null;
        }

        foreach ($children as $child) {
            if (!$child instanceof AstNode) {
                return null;
            }
        }

        return $children;
    }

    /**
     * @return list<list<AstNode>>|null
     */
    private function metaListInlineChildren(mixed $value): ?array
    {
        if (!is_array($value) || ($value['type'] ?? null) !== 'list') {
            return null;
        }

        $items = $value['items'] ?? null;
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

    private function readMetaValue(mixed $value): mixed
    {
        if (!$this->looksLikeMetaConstructor($value)) {
            return $this->readPlainMetaValue($value);
        }

        [$tag, $content] = $this->tagged($value, 'meta value');

        return match ($tag) {
            'MetaString' => is_string($content) ? $content : throw new \InvalidArgumentException('MetaString content must be a string'),
            'MetaBool' => is_bool($content) ? $content : throw new \InvalidArgumentException('MetaBool content must be a boolean'),
            'MetaInlines' => ['type' => 'inlines', 'children' => $this->readInlines($this->listContent($content, 'MetaInlines'))],
            'MetaBlocks' => ['type' => 'blocks', 'children' => $this->readBlocks($this->listContent($content, 'MetaBlocks'))],
            'MetaList' => ['type' => 'list', 'items' => array_map(fn (mixed $item): mixed => $this->readMetaValue($item), $this->listContent($content, 'MetaList'))],
            'MetaMap' => ['type' => 'map', 'items' => $this->readMetaMap($this->metaMapContent($content))],
            default => throw new \InvalidArgumentException("Unsupported Pandoc meta constructor: {$tag}"),
        };
    }

    private function looksLikeMetaConstructor(mixed $value): bool
    {
        return is_array($value)
            && !array_is_list($value)
            && isset($value['t'])
            && is_string($value['t'])
            && in_array($value['t'], self::META_CONSTRUCTORS, true);
    }

    private function readPlainMetaValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'type' => 'list',
                    'items' => array_map(fn (mixed $item): mixed => $this->readMetaValue($item), $value),
                ];
            }

            return [
                'type' => 'map',
                'items' => $this->readMetaMap($value),
            ];
        }

        throw new \InvalidArgumentException('Pandoc meta value must be a tagged object or JSON-compatible scalar, list, or object');
    }

    /**
     * @return array<string, mixed>
     */
    private function metaMapContent(mixed $content): array
    {
        $map = $this->objectContent($content, 'MetaMap');
        if (count($map) !== 1 || !array_key_exists('unMeta', $map) || $this->isTaggedObject($map['unMeta'])) {
            return $map;
        }

        $unMeta = $map['unMeta'];
        if (!is_array($unMeta) || ($unMeta !== [] && array_is_list($unMeta))) {
            throw new \InvalidArgumentException('MetaMap legacy unMeta content must be an object');
        }

        return $unMeta;
    }

    /**
     * @param list<mixed> $blocks
     * @return list<AstNode>
     */
    private function readBlocks(array $blocks): array
    {
        return array_map(fn (mixed $block): AstNode => $this->readBlock($block), $blocks);
    }

    private function readBlock(mixed $value): AstNode
    {
        [$tag, $content] = $this->tagged($value, 'block');

        $node = match ($tag) {
            'Plain' => new AstNode('plain', [], $this->readInlines($this->listContent($content, 'Plain'))),
            'Para' => new AstNode('paragraph', [], $this->readInlines($this->listContent($content, 'Para'))),
            'Header' => $this->readHeaderBlock($content),
            'CodeBlock' => $this->readCodeBlock($content),
            'RawBlock' => $this->readRawBlock($content),
            'BlockQuote' => new AstNode('blockquote', [], $this->readBlocks($this->listContent($content, 'BlockQuote'))),
            'OrderedList' => $this->readOrderedList($content),
            'BulletList' => $this->readBulletList($content),
            'DefinitionList' => $this->readDefinitionList($content),
            'LineBlock' => $this->readLineBlock($content),
            'HorizontalRule' => new AstNode('horizontal_rule'),
            'Null' => new AstNode('null_block'),
            'Div' => $this->readDivBlock($content),
            'Figure' => $this->readFigureBlock($content),
            'Table' => $this->readTableBlock($content),
            default => $this->nativeFallbackNode('native_block', $tag, $value),
        };

        return $this->withConstructorPayload($node, $tag, $value);
    }

    private function readHeaderBlock(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 3, 'Header');
        if (!is_int($tuple[0])) {
            throw new \InvalidArgumentException('Header level must be an integer');
        }

        return new AstNode('heading', array_merge(
            ['level' => $tuple[0]],
            $this->readAttrTuple($tuple[1])
        ), $this->readInlines($this->listContent($tuple[2], 'Header inlines')));
    }

    private function readCodeBlock(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'CodeBlock');
        if (!is_string($tuple[1])) {
            throw new \InvalidArgumentException('CodeBlock text must be a string');
        }

        return new AstNode('code_block', array_merge($this->readAttrTuple($tuple[0]), ['text' => $tuple[1]]));
    }

    private function readRawBlock(mixed $content): AstNode
    {
        [$format, $text] = $this->formatTextTuple($content, 'RawBlock');
        $attrs = ['format' => $format, 'text' => $text];
        $normalized = strtolower($format);

        if ($normalized === 'html') {
            return new AstNode('raw_html', array_merge($attrs, ['html' => $text]));
        }

        if (in_array($normalized, ['tex', 'latex', 'context'], true)) {
            return new AstNode('raw_tex', array_merge($attrs, ['tex' => $text]));
        }

        if ($this->isMarkdownRawFormat($normalized)) {
            return new AstNode('raw_markdown', array_merge($attrs, ['markdown' => $text]));
        }

        return new AstNode('raw_block', $attrs);
    }

    private function readOrderedList(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'OrderedList');
        $listAttributes = $this->tuple($tuple[0], 3, 'OrderedList attributes');
        if (!is_int($listAttributes[0])) {
            throw new \InvalidArgumentException('OrderedList start number must be an integer');
        }

        return new AstNode('ordered_list', [
            'start' => $listAttributes[0],
            'style' => $this->listStyleFromConstructor($this->enumTag($listAttributes[1], 'list style')),
            'delimiter' => $this->listDelimiterFromConstructor($this->enumTag($listAttributes[2], 'list delimiter')),
            'listStyleConstructor' => $this->enumTag($listAttributes[1], 'list style'),
            'listDelimiterConstructor' => $this->enumTag($listAttributes[2], 'list delimiter'),
        ], $this->readListItems($this->listContent($tuple[1], 'OrderedList items')));
    }

    private function readBulletList(mixed $content): AstNode
    {
        return new AstNode('bullet_list', [], $this->readListItems($this->listContent($content, 'BulletList')));
    }

    /**
     * @param list<mixed> $items
     * @return list<AstNode>
     */
    private function readListItems(array $items): array
    {
        return array_map(
            fn (mixed $item): AstNode => new AstNode('list_item', [], $this->readBlocks($this->listContent($item, 'list item'))),
            $items
        );
    }

    private function readDefinitionList(mixed $content): AstNode
    {
        $items = [];
        foreach ($this->listContent($content, 'DefinitionList') as $item) {
            $tuple = $this->tuple($item, 2, 'DefinitionList item');
            $definitions = [];
            foreach ($this->listContent($tuple[1], 'DefinitionList definitions') as $definition) {
                $definitions[] = new AstNode('definition', [], $this->readBlocks($this->listContent($definition, 'definition blocks')));
            }

            $term = new AstNode('definition_term', [], $this->readInlines($this->listContent($tuple[0], 'definition term')));
            $items[] = new AstNode('definition_item', [], [$term, ...$definitions]);
        }

        return new AstNode('definition_list', [], $items);
    }

    private function readLineBlock(mixed $content): AstNode
    {
        $lines = [];
        foreach ($this->listContent($content, 'LineBlock') as $line) {
            $inlines = $this->readInlines($this->listContent($line, 'LineBlock line'));
            $lines[] = new AstNode('line', ['text' => $this->plainText($inlines)], $inlines);
        }

        return new AstNode('line_block', [], $lines);
    }

    private function readDivBlock(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Div');

        return new AstNode('div', $this->readAttrTuple($tuple[0]), $this->readBlocks($this->listContent($tuple[1], 'Div blocks')));
    }

    private function readFigureBlock(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 3, 'Figure');
        $attrs = array_merge(
            $this->readAttrTuple($tuple[0]),
            $this->readCaptionAttrs($tuple[1], 'Figure')
        );

        return new AstNode('figure', $attrs, $this->figureChildren($this->readBlocks($this->listContent($tuple[2], 'Figure blocks'))));
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

    private function readTableBlock(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 6, 'Table');
        $attrs = array_merge(
            $this->readAttrTuple($tuple[0]),
            $this->readTableCaptionAttrs($tuple[1]),
            $this->readTableColumnSpecAttrs($tuple[2])
        );

        $children = [];
        $head = $this->readTableSection($tuple[3], 'TableHead', 'table_head');
        if ($this->tableSectionHasContent($head)) {
            $children[] = $head;
        }

        foreach ($this->listContent($tuple[4], 'Table bodies') as $body) {
            $bodyNode = $this->readTableBody($body);
            if ($this->tableSectionHasContent($bodyNode)) {
                $children[] = $bodyNode;
            }
        }

        $foot = $this->readTableSection($tuple[5], 'TableFoot', 'table_foot');
        if ($this->tableSectionHasContent($foot)) {
            $children[] = $foot;
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function readTableCaptionAttrs(mixed $caption): array
    {
        return $this->readCaptionAttrs($caption, 'Table');
    }

    /**
     * @return array<string, mixed>
     */
    private function readCaptionAttrs(mixed $caption, string $context): array
    {
        $content = $this->constructorContent($caption, 'Caption', "{$context} caption", false);
        $tuple = $this->tuple($content, 2, "{$context} caption");
        $attrs = $this->captionConstructorAttrs($caption);

        $shortCaption = $this->readShortCaption($tuple[0], $context);
        $attrs = array_replace($attrs, $shortCaption['attrs']);
        $shortCaptionInlines = $shortCaption['children'];
        if ($shortCaptionInlines !== []) {
            $attrs['shortCaptionInlines'] = $shortCaptionInlines;
            $attrs['shortCaption'] = trim($this->plainText($shortCaptionInlines));
        }

        $captionBlocks = $this->readBlocks($this->listContent($tuple[1], "{$context} caption blocks"));
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
    private function readShortCaption(mixed $shortCaption, string $context): array
    {
        $attrs = [];
        if ($this->isTaggedConstructor($shortCaption, 'Just') || $this->isTaggedConstructor($shortCaption, 'Nothing')) {
            $attrs['shortCaptionMaybeConstructor'] = $shortCaption['t'];
            $attrs['shortCaptionMaybeNative'] = $shortCaption;
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

        $content = $this->constructorContent($shortCaption, 'ShortCaption', "{$context} short caption", false);
        if (is_array($content) && array_is_list($content) && count($content) === 1 && is_array($content[0]) && array_is_list($content[0])) {
            $content = $content[0];
        }

        return [
            'children' => $this->readInlines($this->listContent($content, "{$context} short caption")),
            'attrs' => $attrs,
        ];
    }

    private function unwrapMaybeConstructor(mixed $value): mixed
    {
        if (!is_array($value) || !isset($value['t']) || !is_string($value['t'])) {
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

    private function isTaggedConstructor(mixed $value, string $constructor): bool
    {
        return $this->isTaggedObject($value) && $value['t'] === $constructor;
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
    private function readTableColumnSpecAttrs(mixed $colSpecs): array
    {
        $alignments = [];
        $widths = [];
        $alignmentConstructors = [];
        $columnWidthConstructors = [];
        foreach ($this->listContent($colSpecs, 'Table column specs') as $colSpec) {
            $tuple = $this->tuple($colSpec, 2, 'Table column spec');
            $alignmentConstructor = $this->enumTag($tuple[0], 'table alignment');
            $columnWidthConstructor = $this->tableColumnWidthConstructor($tuple[1]);
            $alignments[] = $this->tableAlignmentFromConstructor($alignmentConstructor);
            $widths[] = $this->readTableColumnWidth($tuple[1]);
            $alignmentConstructors[] = $alignmentConstructor;
            $columnWidthConstructors[] = $columnWidthConstructor;
        }

        if ($alignments === []) {
            return [];
        }

        return [
            'alignments' => $alignments,
            'widths' => $widths,
            'alignmentConstructors' => $alignmentConstructors,
            'columnWidthConstructors' => $columnWidthConstructors,
        ];
    }

    private function readTableSection(mixed $section, string $constructor, string $type): AstNode
    {
        $content = $this->constructorContent($section, $constructor, $constructor, false);
        $tuple = $this->tuple($content, 2, $constructor);

        return $this->withConstructorPayload(
            new AstNode($type, $this->readAttrTuple($tuple[0]), $this->readTableRows($tuple[1])),
            $constructor,
            $section
        );
    }

    private function readTableBody(mixed $body): AstNode
    {
        $content = $this->constructorContent($body, 'TableBody', 'TableBody', false);
        $tuple = $this->tuple($content, 4, 'TableBody');
        $attrs = $this->readAttrTuple($tuple[0]);

        $rowHeadColumns = $this->readTaggedInteger($tuple[1], 'RowHeadColumns', 'TableBody rowHeadColumns');
        if ($rowHeadColumns > 0) {
            $attrs['rowHeadColumns'] = $rowHeadColumns;
        }
        $attrs['rowHeadColumnsConstructor'] = 'RowHeadColumns';

        $headRows = $this->readTableRows($tuple[2]);
        if ($headRows !== []) {
            $attrs['headRows'] = $headRows;
        }

        return $this->withConstructorPayload(
            new AstNode('table_body', $attrs, $this->readTableRows($tuple[3])),
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
    private function readTableRows(mixed $rows): array
    {
        $nodes = [];
        foreach ($this->listContent($rows, 'Table rows') as $row) {
            $content = $this->constructorContent($row, 'Row', 'Table row', false);
            $tuple = $this->tuple($content, 2, 'Table row');
            $nodes[] = $this->withConstructorPayload(
                new AstNode('table_row', $this->readAttrTuple($tuple[0]), $this->readTableCells($tuple[1])),
                'Row',
                $row
            );
        }

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function readTableCells(mixed $cells): array
    {
        $nodes = [];
        foreach ($this->listContent($cells, 'Table cells') as $cell) {
            $nodes[] = $this->readTableCell($cell);
        }

        return $nodes;
    }

    private function readTableCell(mixed $cell): AstNode
    {
        $content = $this->constructorContent($cell, 'Cell', 'Table cell', false);
        $tuple = $this->tuple($content, 5, 'Table cell');
        $attrs = $this->readAttrTuple($tuple[0]);

        $alignmentConstructor = $this->enumTag($tuple[1], 'table alignment');
        $alignment = $this->tableAlignmentFromConstructor($alignmentConstructor);
        if ($alignment !== 'default') {
            $attrs['align'] = $alignment;
        }
        $attrs['alignmentConstructor'] = $alignmentConstructor;

        $rowspan = $this->readTaggedInteger($tuple[2], 'RowSpan', 'Table cell rowspan');
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }
        $attrs['rowSpanConstructor'] = 'RowSpan';

        $colspan = $this->readTaggedInteger($tuple[3], 'ColSpan', 'Table cell colspan');
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }
        $attrs['colSpanConstructor'] = 'ColSpan';

        $blocks = $this->readBlocks($this->listContent($tuple[4], 'Table cell blocks'));
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
     * @param list<mixed> $inlines
     * @return list<AstNode>
     */
    private function readInlines(array $inlines): array
    {
        return array_map(fn (mixed $inline): AstNode => $this->readInline($inline), $inlines);
    }

    private function readInline(mixed $value): AstNode
    {
        [$tag, $content] = $this->tagged($value, 'inline');

        $node = match ($tag) {
            'Str' => is_string($content) ? new AstNode('text', ['text' => $content]) : throw new \InvalidArgumentException('Str content must be a string'),
            'Space' => new AstNode('space'),
            'SoftBreak' => new AstNode('softbreak'),
            'LineBreak' => new AstNode('linebreak'),
            'Emph' => new AstNode('emph', [], $this->readInlines($this->listContent($content, 'Emph'))),
            'Strong' => new AstNode('strong', [], $this->readInlines($this->listContent($content, 'Strong'))),
            'Underline' => new AstNode('underline', [], $this->readInlines($this->listContent($content, 'Underline'))),
            'Strikeout' => new AstNode('strikeout', [], $this->readInlines($this->listContent($content, 'Strikeout'))),
            'Superscript' => new AstNode('superscript', [], $this->readInlines($this->listContent($content, 'Superscript'))),
            'Subscript' => new AstNode('subscript', [], $this->readInlines($this->listContent($content, 'Subscript'))),
            'SmallCaps' => new AstNode('small_caps', [], $this->readInlines($this->listContent($content, 'SmallCaps'))),
            'Quoted' => $this->readQuotedInline($content),
            'Code' => $this->readCodeInline($content),
            'Math' => $this->readMathInline($content),
            'RawInline' => $this->readRawInline($content),
            'Cite' => $this->readCiteInline($content),
            'Link' => $this->readTargetInline('link', $content),
            'Image' => $this->readTargetInline('image', $content),
            'Note' => new AstNode('note', [], $this->readBlocks($this->listContent($content, 'Note'))),
            'Span' => $this->readSpanInline($content),
            default => $this->nativeFallbackNode('native_inline', $tag, $value),
        };

        return $this->withConstructorPayload($node, $tag, $value);
    }

    private function withConstructorPayload(AstNode $node, string $constructor, mixed $native): AstNode
    {
        return new AstNode(
            $node->type,
            array_replace(['constructor' => $constructor, 'native' => $native], $node->attrs),
            $node->children
        );
    }

    private function nativeFallbackNode(string $type, string $constructor, mixed $native): AstNode
    {
        if (!is_array($native) || array_is_list($native)) {
            throw new \InvalidArgumentException("Pandoc {$type} fallback must carry a tagged native constructor");
        }

        return new AstNode($type, [
            'constructor' => $constructor,
            'native' => $native,
        ]);
    }

    private function readQuotedInline(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Quoted');

        return new AstNode('quoted', [
            'kind' => $this->quoteTypeFromConstructor($this->enumTag($tuple[0], 'quote type')),
            'quoteTypeConstructor' => $this->enumTag($tuple[0], 'quote type'),
        ], $this->readInlines($this->listContent($tuple[1], 'Quoted inlines')));
    }

    private function readCodeInline(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Code');
        if (!is_string($tuple[1])) {
            throw new \InvalidArgumentException('Code text must be a string');
        }

        return new AstNode('code', array_merge($this->readAttrTuple($tuple[0]), ['text' => $tuple[1]]));
    }

    private function readMathInline(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Math');
        if (!is_string($tuple[1])) {
            throw new \InvalidArgumentException('Math text must be a string');
        }

        $mathTypeConstructor = $this->enumTag($tuple[0], 'Math type');

        return new AstNode('math', [
            'display' => $mathTypeConstructor === 'DisplayMath',
            'mathTypeConstructor' => $mathTypeConstructor,
            'text' => $tuple[1],
        ]);
    }

    private function readRawInline(mixed $content): AstNode
    {
        [$format, $text] = $this->formatTextTuple($content, 'RawInline');
        $attrs = ['format' => $format, 'text' => $text];
        $normalized = strtolower($format);

        if ($normalized === 'html') {
            return new AstNode('raw_html_inline', array_merge($attrs, ['html' => $text]));
        }

        if (in_array($normalized, ['tex', 'latex', 'context'], true)) {
            return new AstNode('raw_tex', array_merge($attrs, ['tex' => $text]));
        }

        if ($this->isMarkdownRawFormat($normalized)) {
            return new AstNode('raw_markdown', array_merge($attrs, ['markdown' => $text]));
        }

        return new AstNode('raw_inline', $attrs);
    }

    private function readCiteInline(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Cite');
        $records = $this->listContent($tuple[0], 'Cite citation records');
        if ($records === []) {
            throw new \InvalidArgumentException('Cite must contain at least one citation record');
        }

        $sourceInlines = $this->readInlines($this->listContent($tuple[1], 'Cite source inlines'));
        $sourceText = $this->plainText($sourceInlines);
        $citations = array_map(fn (mixed $record): AstNode => $this->readCitationRecord($record), $records);
        if (count($citations) === 1) {
            $attrs = $citations[0]->attrs;
            if ($sourceText !== '') {
                $attrs['text'] = $sourceText;
            }

            return new AstNode('citation', $attrs, $sourceInlines);
        }

        return new AstNode(
            'citation_group',
            $sourceText === '' ? [] : ['text' => $sourceText],
            $citations
        );
    }

    private function readCitationRecord(mixed $record): AstNode
    {
        if (!is_array($record) || array_is_list($record)) {
            throw new \InvalidArgumentException('Cite citation record must be an object');
        }

        $id = $record['citationId'] ?? null;
        if (!is_string($id) || trim($id) === '') {
            throw new \InvalidArgumentException('Cite citation record must contain a non-empty citationId');
        }

        $prefix = $this->readInlines($this->listContent($record['citationPrefix'] ?? [], 'Cite citationPrefix'));
        $suffix = $this->readInlines($this->listContent($record['citationSuffix'] ?? [], 'Cite citationSuffix'));
        $mode = $this->readCitationMode($record['citationMode'] ?? ['t' => 'NormalCitation']);
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
                throw new \InvalidArgumentException('Cite citationNoteNum must be an integer');
            }
            $attrs['citationNoteNum'] = $record['citationNoteNum'];
        }
        if (array_key_exists('citationHash', $record)) {
            if (!is_int($record['citationHash'])) {
                throw new \InvalidArgumentException('Cite citationHash must be an integer');
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

    private function readCitationMode(mixed $value): string
    {
        return match ($this->enumTag($value, 'citation mode')) {
            'NormalCitation' => 'normal',
            'AuthorInText' => 'author_in_text',
            'SuppressAuthor' => 'suppress_author',
            default => throw new \InvalidArgumentException('Unsupported Pandoc citation mode'),
        };
    }

    /**
     * @param list<AstNode> $prefix
     * @param list<AstNode> $suffix
     */
    private function citationRecordSourceText(string $id, string $mode, array $prefix, array $suffix): string
    {
        $prefixText = $this->plainText($prefix);
        $suffixText = $this->plainText($suffix);
        $token = ($mode === 'suppress_author' ? '-@' : '@') . $id;
        $text = $prefixText === '' ? $token : $prefixText . ' ' . $token;

        return $suffixText === '' ? $text : $text . ', ' . $suffixText;
    }

    private function readTargetInline(string $type, mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 3, ucfirst($type));
        $target = $this->tuple($tuple[2], 2, ucfirst($type) . ' target');
        if (!is_string($target[0]) || !is_string($target[1])) {
            throw new \InvalidArgumentException(ucfirst($type) . ' target entries must be strings');
        }

        $label = $this->readInlines($this->listContent($tuple[1], ucfirst($type) . ' label'));
        $attrs = array_merge($this->readAttrTuple($tuple[0]), [
            'url' => $target[0],
            'title' => $target[1],
        ]);
        if ($type === 'image') {
            $alt = trim($this->plainText($label));
            if ($alt !== '') {
                $attrs['alt'] = $alt;
            }
        }

        return new AstNode($type, $attrs, $label);
    }

    private function readSpanInline(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Span');

        return new AstNode('span', $this->readAttrTuple($tuple[0]), $this->readInlines($this->listContent($tuple[1], 'Span inlines')));
    }

    /**
     * @return array<string, mixed>
     */
    private function readAttrTuple(mixed $value): array
    {
        $tuple = $this->tuple($value, 3, 'Attr');
        if (!is_string($tuple[0])) {
            throw new \InvalidArgumentException('Attr identifier must be a string');
        }

        $classes = $this->listContent($tuple[1], 'Attr classes');
        $attributes = $this->listContent($tuple[2], 'Attr key-values');
        $mappedAttributes = [];
        foreach ($attributes as $attribute) {
            $keyValue = $this->tuple($attribute, 2, 'Attr key-value');
            if (!is_string($keyValue[0]) || !is_string($keyValue[1])) {
                throw new \InvalidArgumentException('Attr key-value entries must be strings');
            }
            $mappedAttributes[$keyValue[0]] = $keyValue[1];
        }

        $attrs = [];
        if ($tuple[0] !== '') {
            $attrs['id'] = $tuple[0];
        }
        if ($classes !== []) {
            $attrs['classes'] = array_map(static fn (mixed $class): string => (string) $class, $classes);
        }
        if ($mappedAttributes !== []) {
            $attrs['attributes'] = $mappedAttributes;
        }

        return $attrs;
    }

    /**
     * @return array{string, string}
     */
    private function formatTextTuple(mixed $content, string $context): array
    {
        $tuple = $this->tuple($content, 2, $context);
        if (!is_string($tuple[0]) || !is_string($tuple[1])) {
            throw new \InvalidArgumentException("{$context} content must be [format, text]");
        }

        return [$tuple[0], $tuple[1]];
    }

    /**
     * @return array{string, mixed}
     */
    private function tagged(mixed $value, string $context): array
    {
        if (!is_array($value) || !isset($value['t']) || !is_string($value['t'])) {
            throw new \InvalidArgumentException("Pandoc {$context} must be a tagged object");
        }

        return [$value['t'], $value['c'] ?? null];
    }

    private function enumTag(mixed $value, string $context): string
    {
        if (is_string($value)) {
            return $value;
        }

        [$tag] = $this->tagged($value, $context);

        return $tag;
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
     * @return array<string, mixed>
     */
    private function objectContent(mixed $value, string $context): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \InvalidArgumentException("{$context} content must be an object");
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
            default => throw new \InvalidArgumentException('Unsupported Pandoc list style'),
        };
    }

    private function listDelimiterFromConstructor(string $constructor): string
    {
        return match ($constructor) {
            'DefaultDelim' => 'default',
            'Period' => 'period',
            'OneParen' => 'one_paren',
            'TwoParens' => 'two_parens',
            default => throw new \InvalidArgumentException('Unsupported Pandoc list delimiter'),
        };
    }

    private function tableAlignmentFromConstructor(string $constructor): string
    {
        return match ($constructor) {
            'AlignLeft' => 'left',
            'AlignRight' => 'right',
            'AlignCenter' => 'center',
            'AlignDefault' => 'default',
            default => throw new \InvalidArgumentException('Unsupported Pandoc table alignment'),
        };
    }

    private function tableColumnWidthConstructor(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return 'ColWidth';
        }

        return $this->enumTag($value, 'table column width');
    }

    private function readTableColumnWidth(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $tag = $this->enumTag($value, 'table column width');
        if ($tag === 'ColWidthDefault') {
            return null;
        }
        if ($tag !== 'ColWidth') {
            throw new \InvalidArgumentException('Unsupported Pandoc table column width');
        }

        $content = $this->constructorContent($value, 'ColWidth', 'table column width');
        if (is_array($content) && array_is_list($content) && count($content) === 1) {
            $content = $content[0];
        }
        if (!is_int($content) && !is_float($content)) {
            throw new \InvalidArgumentException('ColWidth content must be numeric');
        }

        return (float) $content;
    }

    private function readTaggedInteger(mixed $value, string $tag, string $context): int
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

    private function constructorContent(mixed $value, string $tag, string $context, bool $requireTag = true): mixed
    {
        if (!is_array($value) || !isset($value['t']) || !is_string($value['t'])) {
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
            default => throw new \InvalidArgumentException('Unsupported Pandoc quote type'),
        };
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code', 'math' => (string) $node->attr('text', ''),
                'space', 'softbreak', 'linebreak' => ' ',
                default => $this->plainText($node->children),
            };
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
            $text = trim($this->plainText($block->children));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode("\n", $parts);
    }

    private function isMarkdownRawFormat(string $format): bool
    {
        $baseFormat = str_replace('-', '+', $format);
        $baseFormat = explode('+', $baseFormat, 2)[0];

        return $baseFormat === 'markdown' || $format === 'commonmark' || str_starts_with($format, 'gfm');
    }
}
