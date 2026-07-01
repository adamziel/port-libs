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

    /**
     * @var array<string, int>
     */
    private const NAMED_CHARACTER_ESCAPES = [
        'NUL' => 0,
        'SOH' => 1,
        'STX' => 2,
        'ETX' => 3,
        'EOT' => 4,
        'ENQ' => 5,
        'ACK' => 6,
        'BEL' => 7,
        'DLE' => 16,
        'DC1' => 17,
        'DC2' => 18,
        'DC3' => 19,
        'DC4' => 20,
        'NAK' => 21,
        'SYN' => 22,
        'ETB' => 23,
        'CAN' => 24,
        'SUB' => 26,
        'ESC' => 27,
        'DEL' => 127,
        'BS' => 8,
        'HT' => 9,
        'LF' => 10,
        'VT' => 11,
        'FF' => 12,
        'CR' => 13,
        'SO' => 14,
        'SI' => 15,
        'EM' => 25,
        'FS' => 28,
        'GS' => 29,
        'RS' => 30,
        'US' => 31,
        'SP' => 32,
    ];

    /** @var list<array{type:string, value:string}> */
    private array $tokens = [];

    private int $position = 0;

    public function read(string $native): AstNode
    {
        $jsonDocument = $this->readJsonNativeAst($native);
        if ($jsonDocument instanceof AstNode) {
            return $jsonDocument;
        }

        $this->tokens = $this->tokenize($native);
        $this->position = 0;

        if ($this->acceptIdentifier('Pandoc')) {
            $wrappedMeta = $this->acceptSymbol('(');
            $meta = $this->parseMeta();
            if ($wrappedMeta) {
                $this->expectSymbol(')');
            }
            $blocks = $this->parseBlockList();
            $this->expectEnd();

            $attrs = ['nativeFormat' => 'pandoc-native-text'];
            if ($meta !== []) {
                $attrs['meta'] = $meta;
            }

            return new AstNode('document', $attrs, $blocks);
        }

        $blocks = $this->parseBlockList();
        $this->expectEnd();

        return new AstNode('document', ['nativeFormat' => 'pandoc-native-text'], $blocks);
    }

    private function readJsonNativeAst(string $native): ?AstNode
    {
        $trimmed = ltrim($native);
        if ($trimmed === '' || !in_array($trimmed[0], ['{', '['], true)) {
            return null;
        }
        if ($trimmed[0] === '[' && !$this->looksLikeJsonNativeArrayPacket($trimmed)) {
            return null;
        }

        try {
            $packet = json_decode($native, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Invalid Pandoc native JSON packet: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($packet)) {
            throw new \InvalidArgumentException('Pandoc native JSON packet must be an object or legacy tuple');
        }

        $rawMeta = $this->jsonPacketMeta($packet);
        $this->validateNativeJsonMetadataConstructors($rawMeta);

        $document = $this->normalizeJsonNativeNode((new PandocJsonReader())->readPacket($packet));
        $attrs = $document->attrs;
        $attrs['nativeFormat'] = 'pandoc-json';

        $normalizedMeta = $document->attr('meta', []);
        if (!is_array($normalizedMeta) || array_is_list($normalizedMeta)) {
            $normalizedMeta = [];
        }

        $nativeMeta = $this->nativeJsonMetadata($rawMeta, $normalizedMeta);
        if ($nativeMeta !== []) {
            $attrs['meta'] = $nativeMeta;
        } else {
            unset($attrs['meta']);
        }

        return new AstNode('document', $attrs, $document->children);
    }

    private function looksLikeJsonNativeArrayPacket(string $trimmed): bool
    {
        $length = strlen($trimmed);
        $offset = 1;
        while ($offset < $length && ctype_space($trimmed[$offset])) {
            $offset++;
        }

        return $offset < $length && in_array($trimmed[$offset], ['{', '['], true);
    }

    private function normalizeJsonNativeNode(AstNode $node): AstNode
    {
        $children = array_map(fn (AstNode $child): AstNode => $this->normalizeJsonNativeNode($child), $node->children);
        if ($this->hasJsonNativeInlineChildren($node->type)) {
            $children = $this->coalesceJsonNativeInlineText($children);
        }

        return new AstNode($node->type, $this->normalizeJsonNativeAttrs($node->attrs), $children);
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function normalizeJsonNativeAttrs(array $attrs): array
    {
        foreach (['prefix', 'suffix', 'citationSourceInlines', 'captionInlines', 'shortCaptionInlines'] as $key) {
            if (isset($attrs[$key]) && is_array($attrs[$key]) && array_is_list($attrs[$key]) && $this->allAstNodes($attrs[$key])) {
                $attrs[$key] = $this->coalesceJsonNativeInlineText(array_map(
                    fn (AstNode $child): AstNode => $this->normalizeJsonNativeNode($child),
                    $attrs[$key]
                ));
            }
        }

        foreach (['captionBlocks', 'shortCaptionBlocks'] as $key) {
            if (isset($attrs[$key]) && is_array($attrs[$key]) && array_is_list($attrs[$key]) && $this->allAstNodes($attrs[$key])) {
                $attrs[$key] = array_map(fn (AstNode $child): AstNode => $this->normalizeJsonNativeNode($child), $attrs[$key]);
            }
        }

        return $attrs;
    }

    private function hasJsonNativeInlineChildren(string $type): bool
    {
        return in_array($type, [
            'plain',
            'paragraph',
            'heading',
            'definition_term',
            'term',
            'line',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'quoted',
            'link',
            'image',
            'span',
            'citation',
        ], true);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function coalesceJsonNativeInlineText(array $children): array
    {
        $coalesced = [];
        $text = '';
        $parts = [];
        $constructors = [];
        $hasTextRun = false;

        $flush = function () use (&$coalesced, &$text, &$parts, &$constructors, &$hasTextRun): void {
            if (!$hasTextRun) {
                return;
            }

            $attrs = ['text' => $text];
            if ($constructors !== []) {
                $attrs['nativeInlineConstructors'] = $constructors;
            }
            if ($parts !== []) {
                $attrs['nativeInlineParts'] = $parts;
                if (count($parts) === 1 && is_array($parts[0]) && !array_is_list($parts[0]) && ($parts[0]['t'] ?? null) === 'Str') {
                    $attrs['constructor'] = 'Str';
                    $attrs['native'] = $parts[0];
                }
            }
            $coalesced[] = new AstNode('text', $attrs);
            $text = '';
            $parts = [];
            $constructors = [];
            $hasTextRun = false;
        };

        foreach ($children as $child) {
            if (in_array($child->type, ['text', 'space'], true)) {
                $hasTextRun = true;
                $text .= $child->type === 'space' ? ' ' : (string) $child->attr('text', '');
                $constructor = $child->attr('constructor', $child->type === 'space' ? 'Space' : 'Str');
                if (is_string($constructor) && $constructor !== '') {
                    $constructors[] = $constructor;
                }
                $native = $child->attr('native');
                if (is_array($native)) {
                    $parts[] = $native;
                }
                continue;
            }

            $flush();
            $coalesced[] = $child;
        }

        $flush();

        return $coalesced;
    }

    /**
     * @param array<mixed> $values
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

    private function jsonPacketMeta(array $packet): mixed
    {
        if ($this->isJsonTaggedConstructor($packet, 'Pandoc')) {
            $content = $this->singleWrappedJsonTuple($packet['c'] ?? null, 2, 'Pandoc');

            return $content[0];
        }

        if (array_is_list($packet)) {
            if (count($packet) !== 2) {
                throw new \InvalidArgumentException('Pandoc native JSON packet must be an object or legacy [meta, blocks] tuple');
            }

            return $packet[0];
        }

        return $packet['meta'] ?? [];
    }

    /**
     * @param array<string, mixed> $normalizedMeta
     * @return array<string, mixed>
     */
    private function nativeJsonMetadata(mixed $rawMeta, array $normalizedMeta): array
    {
        $rawMap = $this->nativeJsonMetadataMap($rawMeta);
        if ($rawMap === null || !$this->containsMetaConstructor($rawMap)) {
            return $normalizedMeta;
        }

        foreach (['titleInlines', 'authorInlines', 'dateInlines'] as $helper) {
            if (array_key_exists($helper, $normalizedMeta)) {
                $rawMap[$helper] = $this->normalizeJsonNativeMetaHelper($helper, $normalizedMeta[$helper]);
            }
        }

        return $rawMap;
    }

    private function normalizeJsonNativeMetaHelper(string $helper, mixed $value): mixed
    {
        if ($helper === 'authorInlines' && is_array($value) && array_is_list($value)) {
            return array_map(function (mixed $author): mixed {
                if (!is_array($author) || !array_is_list($author) || !$this->allAstNodes($author)) {
                    return $author;
                }

                return $this->coalesceJsonNativeInlineText(array_map(
                    fn (AstNode $child): AstNode => $this->normalizeJsonNativeNode($child),
                    $author
                ));
            }, $value);
        }

        if (is_array($value) && array_is_list($value) && $this->allAstNodes($value)) {
            return $this->coalesceJsonNativeInlineText(array_map(
                fn (AstNode $child): AstNode => $this->normalizeJsonNativeNode($child),
                $value
            ));
        }

        return $value;
    }

    private function validateNativeJsonMetadataConstructors(mixed $rawMeta): void
    {
        $this->validateNativeJsonMetaValue($rawMeta, true);
    }

    private function validateNativeJsonMetaValue(mixed $value, bool $topLevel = false): void
    {
        if ($topLevel && $this->isJsonTaggedConstructor($value, 'Meta')) {
            $this->validateNativeJsonMetaValue($this->singleWrappedJsonValue($value['c'] ?? null), true);

            return;
        }

        if ($this->looksLikeNativeJsonMetaConstructor($value)) {
            $constructor = $value['t'];
            if (!in_array($constructor, self::META_CONSTRUCTORS, true)) {
                throw new \InvalidArgumentException("Unsupported Pandoc native metadata constructor: {$constructor}");
            }

            if ($constructor === 'MetaList') {
                $content = $this->nativeJsonMetaListContent($value['c'] ?? []);
                if (!is_array($content) || ($content !== [] && !array_is_list($content))) {
                    throw new \InvalidArgumentException('Pandoc native JSON MetaList content must be a list');
                }
                foreach ($content as $item) {
                    $this->validateNativeJsonMetaValue($item);
                }
            }

            if ($constructor === 'MetaMap') {
                foreach ($this->nativeJsonMetaMapContent($value['c'] ?? []) as $item) {
                    $this->validateNativeJsonMetaValue($item);
                }
            }

            return;
        }

        if (!is_array($value)) {
            return;
        }

        if (
            $topLevel
            && count($value) === 1
            && array_key_exists('unMeta', $value)
            && is_array($value['unMeta'])
            && !array_is_list($value['unMeta'])
        ) {
            $this->validateNativeJsonMetaValue($value['unMeta'], true);

            return;
        }

        foreach ($value as $item) {
            $this->validateNativeJsonMetaValue($item);
        }
    }

    private function looksLikeNativeJsonMetaConstructor(mixed $value): bool
    {
        return $this->isJsonTaggedConstructor($value) && array_key_exists('c', $value);
    }

    private function nativeJsonMetaListContent(mixed $content): mixed
    {
        if (
            is_array($content)
            && array_is_list($content)
            && count($content) === 1
            && is_array($content[0])
            && array_is_list($content[0])
        ) {
            return $content[0];
        }

        return $content;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nativeJsonMetadataMap(mixed $rawMeta): ?array
    {
        if ($this->isJsonTaggedMetaConstructor($rawMeta, 'MetaMap')) {
            return $this->nativeJsonMetaMapContent($rawMeta['c'] ?? []);
        }

        if ($this->isJsonTaggedConstructor($rawMeta, 'Meta')) {
            $content = $this->singleWrappedJsonValue($rawMeta['c'] ?? null);
            if ($this->isJsonTaggedMetaConstructor($content, 'MetaMap')) {
                return $this->nativeJsonMetaMapContent($content['c'] ?? []);
            }

            if (is_array($content) && !array_is_list($content) && count($content) === 1 && array_key_exists('unMeta', $content)) {
                $unMeta = $content['unMeta'];
                if ($this->isJsonTaggedMetaConstructor($unMeta, 'MetaMap')) {
                    return $this->nativeJsonMetaMapContent($unMeta['c'] ?? []);
                }
                if (is_array($unMeta) && !array_is_list($unMeta) && !$this->isJsonTaggedConstructor($unMeta)) {
                    return $unMeta;
                }
            }

            return null;
        }

        if (!is_array($rawMeta) || ($rawMeta !== [] && array_is_list($rawMeta))) {
            return null;
        }

        if (count($rawMeta) === 1 && array_key_exists('unMeta', $rawMeta) && !$this->isJsonTaggedConstructor($rawMeta['unMeta'])) {
            $unMeta = $rawMeta['unMeta'];
            if (!is_array($unMeta) || ($unMeta !== [] && array_is_list($unMeta))) {
                throw new \InvalidArgumentException('Pandoc native JSON meta.unMeta must be an object');
            }

            return $unMeta;
        }

        if (count($rawMeta) === 1 && array_key_exists('unMeta', $rawMeta) && $this->isJsonTaggedMetaConstructor($rawMeta['unMeta'], 'MetaMap')) {
            return $this->nativeJsonMetaMapContent($rawMeta['unMeta']['c'] ?? []);
        }

        return $rawMeta;
    }

    /**
     * @return array<string, mixed>
     */
    private function nativeJsonMetaMapContent(mixed $content): array
    {
        $content = $this->singleWrappedJsonValue($content);
        if (!is_array($content) || ($content !== [] && array_is_list($content))) {
            throw new \InvalidArgumentException('Pandoc native JSON MetaMap content must be an object');
        }

        if (count($content) === 1 && array_key_exists('unMeta', $content) && !$this->isJsonTaggedConstructor($content['unMeta'])) {
            $unMeta = $content['unMeta'];
            if (!is_array($unMeta) || ($unMeta !== [] && array_is_list($unMeta))) {
                throw new \InvalidArgumentException('Pandoc native JSON MetaMap unMeta content must be an object');
            }

            return $unMeta;
        }

        return $content;
    }

    private function containsMetaConstructor(mixed $value): bool
    {
        if ($this->isJsonTaggedConstructor($value) && in_array($value['t'], self::META_CONSTRUCTORS, true)) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsMetaConstructor($item)) {
                return true;
            }
        }

        return false;
    }

    private function isJsonTaggedMetaConstructor(mixed $value, ?string $constructor = null): bool
    {
        if (!$this->isJsonTaggedConstructor($value, $constructor)) {
            return false;
        }

        return in_array($value['t'], self::META_CONSTRUCTORS, true);
    }

    private function isJsonTaggedConstructor(mixed $value, ?string $constructor = null): bool
    {
        return is_array($value)
            && !array_is_list($value)
            && isset($value['t'])
            && is_string($value['t'])
            && ($constructor === null || $value['t'] === $constructor);
    }

    /**
     * @return list<mixed>
     */
    private function singleWrappedJsonTuple(mixed $content, int $size, string $context): array
    {
        $content = $this->singleWrappedJsonValue($content);
        if (!is_array($content) || !array_is_list($content) || count($content) !== $size) {
            throw new \InvalidArgumentException("Pandoc native JSON {$context} content must be a {$size}-item tuple");
        }

        return $content;
    }

    private function singleWrappedJsonValue(mixed $content): mixed
    {
        while (is_array($content) && array_is_list($content) && count($content) === 1) {
            $content = $content[0];
        }

        return $content;
    }

    /**
     * @return list<array{type:string, value:string}>
     */
    private function tokenize(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        $offset = 0;

        while ($offset < $length) {
            $char = $source[$offset];
            if (ctype_space($char)) {
                $offset++;
                continue;
            }

            if (str_contains('[](){},=', $char)) {
                $tokens[] = ['type' => 'symbol', 'value' => $char];
                $offset++;
                continue;
            }

            if ($char === '"') {
                [$value, $offset] = $this->readStringToken($source, $offset);
                $tokens[] = ['type' => 'string', 'value' => $value];
                continue;
            }

            if ($char === '-' || $char === '.' || ctype_digit($char)) {
                $rest = substr($source, $offset);
                if (preg_match('/^-?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', $rest, $match) === 1) {
                    $tokens[] = ['type' => 'number', 'value' => $match[0]];
                    $offset += strlen($match[0]);
                    continue;
                }
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_\']*/', substr($source, $offset), $match) === 1) {
                $tokens[] = ['type' => 'identifier', 'value' => $match[0]];
                $offset += strlen($match[0]);
                continue;
            }

            throw new \InvalidArgumentException('Unexpected Native token near offset ' . $offset);
        }

        return $tokens;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private function readStringToken(string $source, int $offset): array
    {
        $offset++;
        $value = '';
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '"') {
                return [$value, $offset + 1];
            }

            if ($char !== '\\') {
                $value .= $char;
                $offset++;
                continue;
            }

            $offset++;
            if ($offset >= $length) {
                throw new \InvalidArgumentException('Unterminated escape in Native string');
            }

            $escaped = $source[$offset];
            if (ctype_digit($escaped)) {
                $digits = '';
                while ($offset < $length && ctype_digit($source[$offset])) {
                    $digits .= $source[$offset];
                    $offset++;
                }
                $value .= $this->codepointToUtf8((int) $digits);
                continue;
            }

            $namedEscape = $this->readNamedCharacterEscape($source, $offset);
            if ($namedEscape !== null) {
                [$codepoint, $offset] = $namedEscape;
                $value .= $this->codepointToUtf8($codepoint);
                continue;
            }

            $value .= match ($escaped) {
                'a' => "\x07",
                'b' => "\x08",
                'f' => "\x0c",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'v' => "\x0b",
                '&' => '',
                '\\' => '\\',
                '"' => '"',
                default => $escaped,
            };
            $offset++;
        }

        throw new \InvalidArgumentException('Unterminated Native string');
    }

    /**
     * @return array{0:int, 1:int}|null
     */
    private function readNamedCharacterEscape(string $source, int $offset): ?array
    {
        foreach (self::NAMED_CHARACTER_ESCAPES as $name => $codepoint) {
            $length = strlen($name);
            if (substr($source, $offset, $length) === $name) {
                return [$codepoint, $offset + $length];
            }
        }

        return null;
    }

    private function parseMeta(): array
    {
        $this->expectIdentifier('Meta');
        $this->expectSymbol('{');
        $this->expectIdentifier('unMeta');
        $this->expectSymbol('=');
        $this->expectIdentifier('fromList');
        $entries = $this->parsePairList(fn (): mixed => $this->parseMetaValue());
        $this->expectSymbol('}');

        $meta = [];
        foreach ($entries as $key => $value) {
            if ($key === 'title' && $this->isTypedValue($value, 'MetaInlines')) {
                $meta['titleInlines'] = $value['value'];
                $meta['title'] = $this->plainInlineText($value['value']);
                continue;
            }

            if ($key === 'author' && $this->isTypedValue($value, 'MetaList')) {
                $authorInlines = [];
                $authors = [];
                foreach ($value['value'] as $author) {
                    if ($this->isTypedValue($author, 'MetaInlines')) {
                        $authorInlines[] = $author['value'];
                        $authors[] = $this->plainInlineText($author['value']);
                    }
                }
                if ($authorInlines !== []) {
                    $meta['authorInlines'] = $authorInlines;
                    $meta['author'] = $authors;
                    continue;
                }
            }

            if ($key === 'date' && $this->isTypedValue($value, 'MetaInlines')) {
                $meta['dateInlines'] = $value['value'];
                $meta['date'] = $this->plainInlineText($value['value']);
                continue;
            }

            $meta[$key] = $value;
        }

        return $meta;
    }

    private function parseMetaValue(): mixed
    {
        $type = $this->expectAnyIdentifier();

        return match ($type) {
            'MetaInlines' => ['type' => 'MetaInlines', 'value' => $this->parseInlineList()],
            'MetaBlocks' => ['type' => 'MetaBlocks', 'value' => $this->parseBlockList()],
            'MetaList' => ['type' => 'MetaList', 'value' => $this->parseList(fn (): mixed => $this->parseMetaValue())],
            'MetaMap' => $this->parseMetaMap(),
            'MetaBool' => $this->parseBool(),
            'MetaString' => $this->expectString(),
            default => throw new \InvalidArgumentException("Unsupported Native meta value '{$type}'"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parseMetaMap(): array
    {
        $this->expectSymbol('(');
        $this->expectIdentifier('fromList');
        $entries = $this->parsePairList(fn (): mixed => $this->parseMetaValue());
        $this->expectSymbol(')');

        return $entries;
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlockList(): array
    {
        return $this->parseList(fn (): AstNode => $this->parseBlock());
    }

    private function parseBlock(): AstNode
    {
        $type = $this->expectAnyIdentifier();

        return match ($type) {
            'Plain' => new AstNode('plain', ['text' => $this->plainInlineText($inlines = $this->parseInlineList())], $inlines),
            'Para' => new AstNode('paragraph', ['text' => $this->plainInlineText($inlines = $this->parseInlineList())], $inlines),
            'Header' => $this->parseHeader(),
            'HorizontalRule' => $this->parseNullaryBlock('horizontal_rule', 'HorizontalRule'),
            'Null' => $this->parseNullaryBlock('null_block', 'Null'),
            'CodeBlock' => $this->parseCodeBlock(),
            'BlockQuote' => new AstNode('blockquote', [], $this->parseBlockList()),
            'BulletList' => $this->parseBulletList(),
            'OrderedList' => $this->parseOrderedList(),
            'DefinitionList' => $this->parseDefinitionList(),
            'LineBlock' => $this->parseLineBlock(),
            'Figure' => $this->parseFigure(),
            'Table' => $this->parseTable(),
            'RawBlock' => $this->parseRawBlock(),
            'Div' => new AstNode('div', $this->parseAttrTuple(), $this->parseBlockList()),
            default => throw new \InvalidArgumentException("Unsupported Native block '{$type}'"),
        };
    }

    private function parseHeader(): AstNode
    {
        $level = max(1, min(6, (int) $this->expectNumber()));
        $attrs = $this->parseAttrTuple();
        $inlines = $this->parseInlineList();
        $attrs['level'] = $level;
        $attrs['text'] = $this->plainInlineText($inlines);

        return new AstNode('heading', $attrs, $inlines);
    }

    private function parseCodeBlock(): AstNode
    {
        $attrs = $this->parseAttrTuple();
        $attrs['text'] = $this->expectString();

        return new AstNode('code_block', $attrs);
    }

    private function parseNullaryBlock(string $type, string $constructor): AstNode
    {
        return new AstNode($type, [
            'constructor' => $constructor,
            'native' => ['t' => $constructor],
        ]);
    }

    private function parseBulletList(): AstNode
    {
        $items = $this->parseList(fn (): AstNode => new AstNode('list_item', [], $this->parseBlockList()));

        return new AstNode('bullet_list', [], $items);
    }

    private function parseOrderedList(): AstNode
    {
        $attrs = $this->parseOrderedListAttrs();
        $items = $this->parseList(fn (): AstNode => new AstNode('list_item', [], $this->parseBlockList()));

        return new AstNode('ordered_list', $attrs, $items);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseOrderedListAttrs(): array
    {
        $this->expectSymbol('(');
        $start = (int) $this->expectNumber();
        $this->expectSymbol(',');
        $style = $this->parseOrderedListStyle($this->expectAnyIdentifier());
        $this->expectSymbol(',');
        $delimiter = $this->parseOrderedListDelimiter($this->expectAnyIdentifier());
        $this->expectSymbol(')');

        return [
            'start' => $start,
            'style' => $style,
            'delimiter' => $delimiter,
        ];
    }

    private function parseDefinitionList(): AstNode
    {
        $items = $this->parseList(function (): AstNode {
            $this->expectSymbol('(');
            $termInlines = $this->parseInlineList();
            $this->expectSymbol(',');
            $definitions = $this->parseList(fn (): AstNode => new AstNode('definition', [], $this->parseBlockList()));
            $this->expectSymbol(')');

            return new AstNode('definition_item', ['term' => $this->plainInlineText($termInlines)], array_merge([
                new AstNode('term', ['text' => $this->plainInlineText($termInlines)], $termInlines),
            ], $definitions));
        });

        return new AstNode('definition_list', [], $items);
    }

    private function parseLineBlock(): AstNode
    {
        $lines = $this->parseList(function (): AstNode {
            $inlines = $this->parseInlineList();

            return new AstNode('line', ['text' => $this->plainInlineText($inlines)], $inlines);
        });

        return new AstNode('line_block', [], $lines);
    }

    private function parseFigure(): AstNode
    {
        $attrs = $this->parseAttrTuple();
        $attrs = array_replace($attrs, $this->parseCaptionAttrs());

        return new AstNode('figure', $attrs, $this->figureChildrenFromNativeBlocks($this->parseBlockList()));
    }

    private function parseTable(): AstNode
    {
        $attrs = $this->parseAttrTuple();
        $attrs = array_replace($attrs, $this->parseCaptionAttrs());
        [$alignments, $widths] = $this->parseTableColSpecs();
        if ($alignments !== []) {
            $attrs['alignments'] = $alignments;
        }
        if ($widths !== []) {
            $attrs['widths'] = $widths;
        }

        $children = [$this->parseTableHead()];
        array_push($children, ...$this->parseTableBodies());
        $children[] = $this->parseTableFoot();

        return new AstNode('table', $attrs, $children);
    }

    private function parseRawBlock(): AstNode
    {
        [$format, $formatNative] = $this->parseFormatTuple();
        $text = $this->expectString();
        $native = ['t' => 'RawBlock', 'c' => [$formatNative, $text]];
        $attrs = [
            'format' => $format,
            'text' => $text,
            'constructor' => 'RawBlock',
            'native' => $native,
            'formatConstructor' => 'Format',
            'formatNative' => $formatNative,
        ];

        if ($this->isHtmlRawFormat($format)) {
            return new AstNode('raw_html', $attrs + ['html' => $text]);
        }

        if ($this->isTexRawFormat($format)) {
            return new AstNode('raw_tex', $attrs + ['tex' => $text]);
        }

        if ($this->isMarkdownRawFormat($format)) {
            return new AstNode('raw_markdown', $attrs + ['markdown' => $text]);
        }

        return new AstNode('raw_block', $attrs);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlineList(): array
    {
        return $this->parseList(fn (): AstNode => $this->parseInline());
    }

    private function parseInline(): AstNode
    {
        $type = $this->expectAnyIdentifier();

        return match ($type) {
            'Str' => $this->parseStrInline(),
            'Space' => $this->parseSpaceInline(),
            'SoftBreak' => $this->parseNullaryInline('softbreak', 'SoftBreak'),
            'LineBreak' => $this->parseNullaryInline('linebreak', 'LineBreak'),
            'Emph' => new AstNode('emph', [], $this->parseInlineList()),
            'Strong' => new AstNode('strong', [], $this->parseInlineList()),
            'Strikeout' => new AstNode('strikeout', [], $this->parseInlineList()),
            'Superscript' => new AstNode('superscript', [], $this->parseInlineList()),
            'Subscript' => new AstNode('subscript', [], $this->parseInlineList()),
            'Underline' => new AstNode('underline', [], $this->parseInlineList()),
            'SmallCaps' => new AstNode('small_caps', [], $this->parseInlineList()),
            'Code' => $this->parseCodeInline(),
            'Link' => $this->parseLinkInline(),
            'Image' => $this->parseImageInline(),
            'Note' => new AstNode('note', [], $this->parseBlockList()),
            'Quoted' => $this->parseQuotedInline(),
            'Math' => $this->parseMathInline(),
            'Cite' => $this->parseCitationInline(),
            'RawInline' => $this->parseRawInline(),
            'Span' => $this->parseSpanInline(),
            default => throw new \InvalidArgumentException("Unsupported Native inline '{$type}'"),
        };
    }

    private function parseStrInline(): AstNode
    {
        $text = $this->expectString();
        $native = ['t' => 'Str', 'c' => $text];

        return new AstNode('text', [
            'text' => $text,
            'constructor' => 'Str',
            'native' => $native,
            'nativeInlineConstructors' => ['Str'],
            'nativeInlineParts' => [$native],
        ]);
    }

    private function parseSpaceInline(): AstNode
    {
        $native = ['t' => 'Space'];

        return new AstNode('text', [
            'text' => ' ',
            'nativeInlineConstructors' => ['Space'],
            'nativeInlineParts' => [$native],
        ]);
    }

    private function parseNullaryInline(string $type, string $constructor): AstNode
    {
        return new AstNode($type, [
            'constructor' => $constructor,
            'native' => ['t' => $constructor],
        ]);
    }

    private function parseCodeInline(): AstNode
    {
        [$attrs, $attrNative] = $this->parseAttrTuplePayload();
        $text = $this->expectString();
        $attrs['text'] = $text;
        $attrs['constructor'] = 'Code';
        $attrs['native'] = ['t' => 'Code', 'c' => [$attrNative, $text]];

        return new AstNode('code', $attrs);
    }

    private function parseLinkInline(): AstNode
    {
        [$attrs, $attrNative] = $this->parseAttrTuplePayload();
        $inlines = $this->parseInlineList();
        [$url, $title, $targetNative] = $this->parseTargetTuplePayload();
        $attrs['url'] = $url;
        $attrs['title'] = $title;
        $attrs['targetNative'] = $targetNative;
        $inlineNative = $this->nativeInlineListPayload($inlines);
        if ($inlineNative !== null) {
            $attrs['constructor'] = 'Link';
            $attrs['native'] = ['t' => 'Link', 'c' => [$attrNative, $inlineNative, $targetNative]];
        }

        return new AstNode('link', $attrs, $inlines);
    }

    private function parseImageInline(): AstNode
    {
        [$attrs, $attrNative] = $this->parseAttrTuplePayload();
        $inlines = $this->parseInlineList();
        [$url, $title, $targetNative] = $this->parseTargetTuplePayload();
        $attrs['url'] = $url;
        $attrs['title'] = $title;
        $attrs['targetNative'] = $targetNative;
        $attrs['alt'] = $this->plainInlineText($inlines);
        $inlineNative = $this->nativeInlineListPayload($inlines);
        if ($inlineNative !== null) {
            $attrs['constructor'] = 'Image';
            $attrs['native'] = ['t' => 'Image', 'c' => [$attrNative, $inlineNative, $targetNative]];
        }

        return new AstNode('image', $attrs, $inlines);
    }

    private function parseQuotedInline(): AstNode
    {
        $constructor = $this->expectAnyIdentifier();
        $kind = $constructor === 'SingleQuote' ? 'single' : 'double';

        return new AstNode('quoted', [
            'kind' => $kind,
            'quoteTypeConstructor' => $constructor,
            'quoteTypeNative' => ['t' => $constructor],
        ], $this->parseInlineList());
    }

    private function parseMathInline(): AstNode
    {
        $constructor = $this->expectAnyIdentifier();
        $display = $constructor === 'DisplayMath';

        return new AstNode('math', [
            'display' => $display,
            'mathTypeConstructor' => $constructor,
            'mathTypeNative' => ['t' => $constructor],
            'text' => $this->expectString(),
        ]);
    }

    private function parseCitationInline(): AstNode
    {
        $citations = $this->parseList(fn (): AstNode => $this->parseCitationRecord());
        if ($citations === []) {
            throw new \InvalidArgumentException('Cite must contain at least one citation record');
        }

        $display = $this->parseInlineList();
        $sourceText = $this->plainInlineText($display);

        if (count($citations) === 1) {
            $attrs = $citations[0]->attrs;
            if ($sourceText !== '') {
                $attrs['text'] = $sourceText;
            }

            return new AstNode('citation', $attrs, $display);
        }

        $attrs = [];
        if ($sourceText !== '') {
            $attrs['text'] = $sourceText;
        }
        if ($display !== []) {
            $attrs['citationSourceInlines'] = $display;
        }

        return new AstNode('citation_group', $attrs, $citations);
    }

    private function parseCitationRecord(): AstNode
    {
        $this->expectIdentifier('Citation');
        $this->expectSymbol('{');
        $fields = [];

        while (!$this->acceptSymbol('}')) {
            $name = $this->expectAnyIdentifier();
            $this->expectSymbol('=');
            $fields[$name] = match ($name) {
                'citationId' => $this->expectString(),
                'citationPrefix', 'citationSuffix' => $this->parseInlineList(),
                'citationMode' => $this->expectAnyIdentifier(),
                'citationNoteNum', 'citationHash' => (int) $this->expectNumber(),
                default => throw new \InvalidArgumentException("Unsupported Native citation field '{$name}'"),
            };
            $this->acceptSymbol(',');
        }

        $id = (string) ($fields['citationId'] ?? '');
        if (trim($id) === '') {
            throw new \InvalidArgumentException('Cite citation record must contain a non-empty citationId');
        }

        $prefix = $fields['citationPrefix'] ?? [];
        $suffix = $fields['citationSuffix'] ?? [];
        $modeConstructor = (string) ($fields['citationMode'] ?? 'NormalCitation');
        $mode = $this->parseCitationMode($modeConstructor);
        $noteNum = (int) ($fields['citationNoteNum'] ?? 1);
        $hash = (int) ($fields['citationHash'] ?? 0);
        $text = $this->citationRecordSourceText($id, $mode, $prefix, $suffix);

        $attrs = [
            'id' => $id,
            'text' => $text,
            'mode' => $mode,
            'citationModeConstructor' => $modeConstructor,
            'citationModeNative' => ['t' => $modeConstructor],
            'citationNoteNum' => $noteNum,
            'citationHash' => $hash,
            'noteNum' => $noteNum,
            'hash' => $hash,
        ];
        if ($prefix !== []) {
            $attrs['prefix'] = $prefix;
            $attrs['citationPrefixNative'] = $prefix;
        }
        if ($suffix !== []) {
            $attrs['suffix'] = $suffix;
            $attrs['citationSuffixNative'] = $suffix;
        }

        return new AstNode('citation', $attrs, [
            new AstNode('text', ['text' => $text]),
        ]);
    }

    /**
     * @param list<AstNode> $prefix
     * @param list<AstNode> $suffix
     */
    private function citationRecordSourceText(string $id, string $mode, array $prefix, array $suffix): string
    {
        $prefixText = $this->plainInlineText($prefix);
        $suffixText = $this->plainInlineText($suffix);
        $token = ($mode === 'suppress_author' ? '-@' : '@') . $id;
        $text = $prefixText === '' ? $token : $prefixText . ' ' . $token;

        return $suffixText === '' ? $text : $text . ', ' . $suffixText;
    }

    private function parseRawInline(): AstNode
    {
        [$format, $formatNative] = $this->parseFormatTuple();
        $text = $this->expectString();
        $native = ['t' => 'RawInline', 'c' => [$formatNative, $text]];
        $attrs = [
            'format' => $format,
            'text' => $text,
            'constructor' => 'RawInline',
            'native' => $native,
            'formatConstructor' => 'Format',
            'formatNative' => $formatNative,
        ];

        if ($this->isHtmlRawFormat($format)) {
            return new AstNode('raw_html_inline', $attrs + ['html' => $text]);
        }

        if ($this->isTexRawFormat($format)) {
            return new AstNode('raw_tex_inline', $attrs + ['tex' => $text]);
        }

        if ($this->isMarkdownRawFormat($format)) {
            return new AstNode('raw_markdown', $attrs + ['markdown' => $text]);
        }

        return new AstNode('raw_inline', $attrs);
    }

    private function parseSpanInline(): AstNode
    {
        [$attrs, $attrNative] = $this->parseAttrTuplePayload();
        $inlines = $this->parseInlineList();
        $inlineNative = $this->nativeInlineListPayload($inlines);
        if ($inlineNative !== null) {
            $attrs['constructor'] = 'Span';
            $attrs['native'] = ['t' => 'Span', 'c' => [$attrNative, $inlineNative]];
        }

        return new AstNode('span', $attrs, $inlines);
    }

    private function isMarkdownRawFormat(string $format): bool
    {
        $normalized = $this->rawFormatBase($format);

        return in_array($normalized, [
            'markdown',
            'markdown_strict',
            'markdown_phpextra',
            'markdown_github',
            'markdown_mmd',
            'pandoc',
            'commonmark',
            'commonmark_x',
            'gfm',
        ], true);
    }

    private function isHtmlRawFormat(string $format): bool
    {
        $normalized = strtolower(str_replace('-', '+', $format));
        $baseFormat = $this->rawFormatBase($format);

        return in_array($normalized, ['html', 'html4', 'html5', 'xhtml'], true)
            || in_array($baseFormat, ['html', 'html4', 'html5', 'xhtml'], true);
    }

    private function isTexRawFormat(string $format): bool
    {
        $baseFormat = $this->rawFormatBase($format);

        return in_array($baseFormat, ['tex', 'latex', 'context'], true);
    }

    private function rawFormatBase(string $format): string
    {
        $format = strtolower($format);
        $format = str_replace('-', '+', $format);

        return explode('+', $format, 2)[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAttrTuple(): array
    {
        $attrs = $this->parseAttrTuplePayload()[0];
        unset($attrs['attrConstructor'], $attrs['attrNative']);

        return $attrs;
    }

    /**
     * @return array{0:array<string, mixed>, 1:array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}}
     */
    private function parseAttrTuplePayload(): array
    {
        $this->expectSymbol('(');
        $id = $this->expectString();
        $this->expectSymbol(',');
        $classes = $this->parseList(fn (): string => $this->expectString());
        $this->expectSymbol(',');
        [$attributes, $attributePairs] = $this->parseAttrPairList();
        $this->expectSymbol(')');

        $native = [$id, $classes, $attributePairs];
        $attrs = [
            'attrConstructor' => 'Attr',
            'attrNative' => $native,
        ];
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }

        return [$attrs, $native];
    }

    /**
     * @return array{0:array<string, string>, 1:list<array{0:string, 1:string}>}
     */
    private function parseAttrPairList(): array
    {
        $this->expectSymbol('[');
        $attributes = [];
        $pairs = [];
        if ($this->acceptSymbol(']')) {
            return [$attributes, $pairs];
        }

        do {
            $this->expectSymbol('(');
            $key = $this->expectString();
            $this->expectSymbol(',');
            $value = $this->expectString();
            $this->expectSymbol(')');
            $attributes[$key] = $value;
            $pairs[] = [$key, $value];
        } while ($this->acceptSymbol(','));

        $this->expectSymbol(']');

        return [$attributes, $pairs];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCaptionAttrs(): array
    {
        $this->expectSymbol('(');
        $this->expectIdentifier('Caption');
        $attrs = [];

        if ($this->acceptIdentifier('Nothing')) {
            // No short caption.
        } else {
            $this->expectSymbol('(');
            $this->expectIdentifier('Just');
            $attrs['shortCaptionInlines'] = $this->parseShortCaptionInlines();
            $this->expectSymbol(')');
            $attrs['shortCaption'] = $this->plainInlineText($attrs['shortCaptionInlines']);
        }

        $captionBlocks = $this->parseBlockList();
        if ($captionBlocks !== []) {
            $attrs['captionBlocks'] = $captionBlocks;
            $attrs['caption'] = $this->plainBlockText($captionBlocks);
            $captionInlines = $this->captionInlinesFromBlocks($captionBlocks);
            if ($captionInlines !== []) {
                $attrs['captionInlines'] = $captionInlines;
            }
        }
        $this->expectSymbol(')');

        return $attrs;
    }

    /**
     * @return list<AstNode>
     */
    private function parseShortCaptionInlines(): array
    {
        if (!$this->acceptSymbol('(')) {
            return $this->parseInlineList();
        }

        $this->expectIdentifier('ShortCaption');
        $inlines = $this->parseInlineList();
        $this->expectSymbol(')');

        return $inlines;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function captionInlinesFromBlocks(array $blocks): array
    {
        if (count($blocks) !== 1) {
            return [];
        }

        $block = $blocks[0];
        if (!in_array($block->type, ['paragraph', 'plain'], true)) {
            return [];
        }

        return $block->children;
    }

    /**
     * @return array{0:list<string>, 1:list<float>}
     */
    private function parseTableColSpecs(): array
    {
        $specs = $this->parseList(function (): array {
            $this->expectSymbol('(');
            $alignment = $this->parseAlignment($this->expectAnyIdentifier());
            $this->expectSymbol(',');
            $width = $this->parseColumnWidth();
            $this->expectSymbol(')');

            return [$alignment, $width];
        });

        $alignments = [];
        $widths = [];
        foreach ($specs as [$alignment, $width]) {
            $alignments[] = $alignment;
            $widths[] = $width;
        }

        return [$alignments, $widths];
    }

    private function parseColumnWidth(): float
    {
        $type = $this->expectAnyIdentifier();
        if ($type === 'ColWidthDefault') {
            return 0.0;
        }
        if ($type !== 'ColWidth') {
            throw new \InvalidArgumentException("Unsupported Native column width '{$type}'");
        }

        return (float) $this->expectNumber();
    }

    private function parseTableHead(): AstNode
    {
        $wrapped = $this->acceptSymbol('(');
        $this->expectIdentifier('TableHead');

        $head = new AstNode('table_head', $this->parseAttrTuple(), $this->parseTableRows());
        if ($wrapped) {
            $this->expectSymbol(')');
        }

        return $head;
    }

    /**
     * @return list<AstNode>
     */
    private function parseTableBodies(): array
    {
        return $this->parseList(function (): AstNode {
            $wrapped = $this->acceptSymbol('(');
            $this->expectIdentifier('TableBody');
            $attrs = $this->parseAttrTuple();
            $this->expectSymbol('(');
            $this->expectIdentifier('RowHeadColumns');
            $attrs['rowHeadColumns'] = (int) $this->expectNumber();
            $this->expectSymbol(')');
            $headRows = $this->parseTableRows();
            if ($headRows !== []) {
                $attrs['headRows'] = $headRows;
            }

            $body = new AstNode('table_body', $attrs, $this->parseTableRows());
            if ($wrapped) {
                $this->expectSymbol(')');
            }

            return $body;
        });
    }

    private function parseTableFoot(): AstNode
    {
        $wrapped = $this->acceptSymbol('(');
        $this->expectIdentifier('TableFoot');

        $foot = new AstNode('table_foot', $this->parseAttrTuple(), $this->parseTableRows());
        if ($wrapped) {
            $this->expectSymbol(')');
        }

        return $foot;
    }

    /**
     * @return list<AstNode>
     */
    private function parseTableRows(): array
    {
        return $this->parseList(fn (): AstNode => $this->parseTableRow());
    }

    private function parseTableRow(): AstNode
    {
        $this->expectIdentifier('Row');
        $attrs = $this->parseAttrTuple();
        $cells = $this->parseList(fn (): AstNode => $this->parseTableCell());

        return new AstNode('table_row', $attrs, $cells);
    }

    private function parseTableCell(): AstNode
    {
        $this->expectIdentifier('Cell');
        $attrs = $this->parseAttrTuple();
        $alignment = $this->parseAlignment($this->expectAnyIdentifier());
        if ($alignment !== 'default') {
            $attrs['align'] = $alignment;
        }
        $this->expectSymbol('(');
        $this->expectIdentifier('RowSpan');
        $rowspan = (int) $this->expectNumber();
        $this->expectSymbol(')');
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }
        $this->expectSymbol('(');
        $this->expectIdentifier('ColSpan');
        $colspan = (int) $this->expectNumber();
        $this->expectSymbol(')');
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }

        return new AstNode('table_cell', $attrs, $this->parseBlockList());
    }

    /**
     * @return array{0:string, 1:array{t:string, c:string}}
     */
    private function parseFormatTuple(): array
    {
        $this->expectSymbol('(');
        $this->expectIdentifier('Format');
        $format = $this->expectString();
        $this->expectSymbol(')');

        return [$format, ['t' => 'Format', 'c' => $format]];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function parseTargetTuple(): array
    {
        $payload = $this->parseTargetTuplePayload();

        return [$payload[0], $payload[1]];
    }

    /**
     * @return array{0:string, 1:string, 2:array{0:string, 1:string}}
     */
    private function parseTargetTuplePayload(): array
    {
        $this->expectSymbol('(');
        $url = $this->expectString();
        $this->expectSymbol(',');
        $title = $this->expectString();
        $this->expectSymbol(')');

        return [$url, $title, [$url, $title]];
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<array<string, mixed>>|null
     */
    private function nativeInlineListPayload(array $inlines): ?array
    {
        $payload = [];
        foreach ($inlines as $inline) {
            $parts = $this->nativeInlinePayloads($inline);
            if ($parts === null) {
                return null;
            }
            array_push($payload, ...$parts);
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function nativeInlinePayloads(AstNode $inline): ?array
    {
        $parts = $inline->attr('nativeInlineParts', []);
        if (is_array($parts) && array_is_list($parts) && $parts !== []) {
            foreach ($parts as $part) {
                if (!is_array($part) || array_is_list($part) || !is_string($part['t'] ?? null)) {
                    return null;
                }
            }

            return $parts;
        }

        $native = $inline->attr('native');
        if (is_array($native) && !array_is_list($native) && is_string($native['t'] ?? null)) {
            return [$native];
        }

        return match ($inline->type) {
            'space' => [['t' => 'Space']],
            'softbreak' => [['t' => 'SoftBreak']],
            'linebreak' => [['t' => 'LineBreak']],
            default => null,
        };
    }

    private function parseBool(): bool
    {
        $value = $this->expectAnyIdentifier();
        if ($value === 'True') {
            return true;
        }
        if ($value === 'False') {
            return false;
        }

        throw new \InvalidArgumentException("Expected Native boolean, got '{$value}'");
    }

    /**
     * @template T
     * @param callable(): T $parser
     * @return list<T>
     */
    private function parseList(callable $parser): array
    {
        $this->expectSymbol('[');
        $items = [];
        if ($this->acceptSymbol(']')) {
            return $items;
        }

        do {
            $items[] = $parser();
        } while ($this->acceptSymbol(','));

        $this->expectSymbol(']');

        return $items;
    }

    /**
     * @template T
     * @param callable(): T $valueParser
     * @return array<string, T>
     */
    private function parsePairList(callable $valueParser): array
    {
        $this->expectSymbol('[');
        $pairs = [];
        if ($this->acceptSymbol(']')) {
            return $pairs;
        }

        do {
            $this->expectSymbol('(');
            $key = $this->expectString();
            $this->expectSymbol(',');
            $pairs[$key] = $valueParser();
            $this->expectSymbol(')');
        } while ($this->acceptSymbol(','));

        $this->expectSymbol(']');

        return $pairs;
    }

    private function parseOrderedListStyle(string $style): string
    {
        return match ($style) {
            'DefaultStyle' => 'default',
            'Decimal' => 'decimal',
            'LowerAlpha' => 'lower_alpha',
            'UpperAlpha' => 'upper_alpha',
            'LowerRoman' => 'lower_roman',
            'UpperRoman' => 'upper_roman',
            'Example' => 'example',
            default => 'default',
        };
    }

    private function parseOrderedListDelimiter(string $delimiter): string
    {
        return match ($delimiter) {
            'DefaultDelim' => 'default',
            'Period' => 'period',
            'OneParen' => 'one_paren',
            'TwoParens' => 'two_parens',
            default => 'period',
        };
    }

    private function parseAlignment(string $alignment): string
    {
        return match ($alignment) {
            'AlignLeft' => 'left',
            'AlignRight' => 'right',
            'AlignCenter' => 'center',
            default => 'default',
        };
    }

    private function parseCitationMode(string $mode): string
    {
        return match ($mode) {
            'AuthorInText' => 'author_in_text',
            'SuppressAuthor' => 'suppress_author',
            default => 'normal',
        };
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function figureChildrenFromNativeBlocks(array $blocks): array
    {
        $children = [];
        foreach ($blocks as $block) {
            if ($block->type === 'plain' && count($block->children) === 1 && $block->children[0]->type === 'image') {
                $children[] = $block->children[0];
                continue;
            }

            $children[] = $block;
        }

        return $children;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if ($block->children !== []) {
                $parts[] = $this->plainInlineText($block->children);
            } else {
                $parts[] = (string) $block->attr('text', '');
            }
        }

        return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainInlineText(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            $text .= match ($inline->type) {
                'text', 'code' => (string) $inline->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($inline->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function isTypedValue(mixed $value, string $type): bool
    {
        return is_array($value)
            && ($value['type'] ?? null) === $type
            && array_key_exists('value', $value);
    }

    private function codepointToUtf8(int $codepoint): string
    {
        if (function_exists('mb_chr')) {
            return mb_chr($codepoint, 'UTF-8');
        }

        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7FF) {
            return chr(0xC0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3F));
        }
        if ($codepoint <= 0xFFFF) {
            return chr(0xE0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F));
        }

        return chr(0xF0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3F))
            . chr(0x80 | (($codepoint >> 6) & 0x3F))
            . chr(0x80 | ($codepoint & 0x3F));
    }

    private function acceptIdentifier(string $identifier): bool
    {
        $token = $this->peek();
        if ($token !== null && $token['type'] === 'identifier' && $token['value'] === $identifier) {
            $this->position++;

            return true;
        }

        return false;
    }

    private function expectIdentifier(string $identifier): void
    {
        $actual = $this->expectAnyIdentifier();
        if ($actual !== $identifier) {
            throw new \InvalidArgumentException("Expected Native identifier '{$identifier}', got '{$actual}'");
        }
    }

    private function expectAnyIdentifier(): string
    {
        $token = $this->next();
        if ($token['type'] !== 'identifier') {
            throw new \InvalidArgumentException("Expected Native identifier, got '{$token['value']}'");
        }

        return $token['value'];
    }

    private function acceptSymbol(string $symbol): bool
    {
        $token = $this->peek();
        if ($token !== null && $token['type'] === 'symbol' && $token['value'] === $symbol) {
            $this->position++;

            return true;
        }

        return false;
    }

    private function expectSymbol(string $symbol): void
    {
        $token = $this->next();
        if ($token['type'] !== 'symbol' || $token['value'] !== $symbol) {
            throw new \InvalidArgumentException("Expected Native symbol '{$symbol}', got '{$token['value']}'");
        }
    }

    private function expectString(): string
    {
        $token = $this->next();
        if ($token['type'] !== 'string') {
            throw new \InvalidArgumentException("Expected Native string, got '{$token['value']}'");
        }

        return $token['value'];
    }

    private function expectNumber(): string
    {
        $token = $this->next();
        if ($token['type'] !== 'number') {
            throw new \InvalidArgumentException("Expected Native number, got '{$token['value']}'");
        }

        return $token['value'];
    }

    private function expectEnd(): void
    {
        if ($this->position !== count($this->tokens)) {
            $token = $this->tokens[$this->position];
            throw new \InvalidArgumentException("Unexpected Native token '{$token['value']}' at end of input");
        }
    }

    /**
     * @return array{type:string, value:string}|null
     */
    private function peek(): ?array
    {
        return $this->tokens[$this->position] ?? null;
    }

    /**
     * @return array{type:string, value:string}
     */
    private function next(): array
    {
        $token = $this->tokens[$this->position] ?? null;
        if ($token === null) {
            throw new \InvalidArgumentException('Unexpected end of Native input');
        }

        $this->position++;

        return $token;
    }
}
