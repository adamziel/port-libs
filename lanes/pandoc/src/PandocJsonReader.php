<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocJsonReader
{
    public function read(string $json): AstNode
    {
        try {
            $packet = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Invalid Pandoc JSON packet: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($packet) || array_is_list($packet)) {
            throw new \InvalidArgumentException('Pandoc JSON packet must be an object');
        }

        return $this->readPacket($packet);
    }

    /**
     * @param array<string, mixed> $packet
     */
    public function readPacket(array $packet): AstNode
    {
        $blocks = $packet['blocks'] ?? null;
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new \InvalidArgumentException('Pandoc JSON packet must contain a blocks array');
        }

        $attrs = [];
        if (isset($packet['pandoc-api-version'])) {
            $attrs['pandocApiVersion'] = $this->readApiVersion($packet['pandoc-api-version']);
        }

        $meta = $packet['meta'] ?? [];
        if (!is_array($meta) || ($meta !== [] && array_is_list($meta))) {
            throw new \InvalidArgumentException('Pandoc JSON meta must be an object');
        }
        if ($meta !== []) {
            $attrs['meta'] = $this->readMetaMap($meta);
        }

        return new AstNode('document', $attrs, array_map(fn (mixed $block): AstNode => $this->readBlock($block), $blocks));
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

    private function readMetaValue(mixed $value): mixed
    {
        [$tag, $content] = $this->tagged($value, 'meta value');

        return match ($tag) {
            'MetaString' => is_string($content) ? $content : throw new \InvalidArgumentException('MetaString content must be a string'),
            'MetaBool' => is_bool($content) ? $content : throw new \InvalidArgumentException('MetaBool content must be a boolean'),
            'MetaInlines' => ['type' => 'inlines', 'children' => $this->readInlines($this->listContent($content, 'MetaInlines'))],
            'MetaBlocks' => ['type' => 'blocks', 'children' => $this->readBlocks($this->listContent($content, 'MetaBlocks'))],
            'MetaList' => ['type' => 'list', 'items' => array_map(fn (mixed $item): mixed => $this->readMetaValue($item), $this->listContent($content, 'MetaList'))],
            'MetaMap' => ['type' => 'map', 'items' => $this->readMetaMap($this->objectContent($content, 'MetaMap'))],
            default => throw new \InvalidArgumentException("Unsupported Pandoc meta constructor: {$tag}"),
        };
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

        return match ($tag) {
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
            'Div' => $this->readDivBlock($content),
            default => throw new \InvalidArgumentException("Unsupported Pandoc block constructor: {$tag}"),
        };
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
            'style' => $this->readListStyle($listAttributes[1]),
            'delimiter' => $this->readListDelimiter($listAttributes[2]),
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

        return match ($tag) {
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
            default => throw new \InvalidArgumentException("Unsupported Pandoc inline constructor: {$tag}"),
        };
    }

    private function readQuotedInline(mixed $content): AstNode
    {
        $tuple = $this->tuple($content, 2, 'Quoted');

        return new AstNode('quoted', [
            'kind' => $this->readQuoteType($tuple[0]),
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

        return new AstNode('math', [
            'display' => $this->enumTag($tuple[0], 'Math type') === 'DisplayMath',
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

        return new AstNode('citation', $attrs, [
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

        return new AstNode($type, array_merge($this->readAttrTuple($tuple[0]), [
            'url' => $target[0],
            'title' => $target[1],
        ]), $this->readInlines($this->listContent($tuple[1], ucfirst($type) . ' label')));
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

    private function readListStyle(mixed $value): string
    {
        return match ($this->enumTag($value, 'list style')) {
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

    private function readListDelimiter(mixed $value): string
    {
        return match ($this->enumTag($value, 'list delimiter')) {
            'DefaultDelim' => 'default',
            'Period' => 'period',
            'OneParen' => 'one_paren',
            'TwoParens' => 'two_parens',
            default => throw new \InvalidArgumentException('Unsupported Pandoc list delimiter'),
        };
    }

    private function readQuoteType(mixed $value): string
    {
        return match ($this->enumTag($value, 'quote type')) {
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

    private function isMarkdownRawFormat(string $format): bool
    {
        $baseFormat = str_replace('-', '+', $format);
        $baseFormat = explode('+', $baseFormat, 2)[0];

        return $baseFormat === 'markdown' || $format === 'commonmark' || str_starts_with($format, 'gfm');
    }
}
