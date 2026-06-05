<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownReader
{
    private const MARKDOWN_ESCAPABLE_ASCII_PUNCTUATION = "!\"#$%&'()*+,-./:;<=>?@[\\]^_`{|}~";

    /** @var array<string, array{url:string, title:string}> */
    private array $referenceLinks = [];

    /** @var array<string, string> */
    private array $footnoteDefinitions = [];

    /** @var array<string, int> */
    private array $exampleReferences = [];

    /** @var array<int, int> */
    private array $exampleNumbersByLine = [];

    /** @var array<string, array{arity:int, template:string}> */
    private array $rawTexMacros = [];

    /** @var array<string, mixed> */
    private array $yamlMetadataAnchors = [];

    private bool $resolveFootnoteReferences = true;

    /**
     * @param array{literateHaskell?: bool} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function readBytes(string $bytes, ?string $encoding = null, ?string $normalizationForm = null): AstNode
    {
        $decoded = UnicodeText::decodeBytes($bytes, $encoding, $normalizationForm);
        $document = $this->read($decoded['text']);
        $attrs = array_replace($document->attrs, [
            'sourceEncoding' => [
                'encoding' => $decoded['encoding'],
                'bom' => $decoded['bom'],
                'repairs' => $decoded['repairs'],
            ],
        ]);
        if (($decoded['lineEndings']['conversions'] ?? 0) > 0) {
            $attrs['sourceLineEndings'] = $decoded['lineEndings'];
        }
        if (isset($decoded['normalization'])) {
            $attrs['sourceNormalization'] = $decoded['normalization'];
        }

        return new AstNode(
            'document',
            $attrs,
            $document->children
        );
    }

    public function read(string $markdown): AstNode
    {
        $blocks = [];
        $paragraph = [];
        $listStack = [];
        $lines = preg_split('/\R/u', rtrim($markdown, "\r\n")) ?: [];
        $previousReferenceLinks = $this->referenceLinks;
        $previousFootnoteDefinitions = $this->footnoteDefinitions;
        $previousExampleReferences = $this->exampleReferences;
        $previousExampleNumbersByLine = $this->exampleNumbersByLine;
        $previousRawTexMacros = $this->rawTexMacros;
        $documentAttrs = [];
        [$lines, $yamlMetadata] = $this->extractYamlMetadataBlocks($lines);
        [$lines, $titleBlock] = $this->extractTitleBlock($lines);
        [$lines, $references, $footnotes] = $this->extractReferenceDefinitions($lines);
        $lines = $this->splitMixedHtmlFlowLines($lines);
        [$exampleReferences, $exampleNumbersByLine] = $this->collectNumberedExampleReferences($lines);
        [$markdownHeadingIds, $implicitHeadingReferences] = $this->collectMarkdownHeadingReferences($lines);
        $this->referenceLinks = array_replace($previousReferenceLinks, $implicitHeadingReferences, $references);
        $this->footnoteDefinitions = array_replace($previousFootnoteDefinitions, $footnotes);
        $this->exampleReferences = array_replace($previousExampleReferences, $exampleReferences);
        $this->exampleNumbersByLine = $exampleNumbersByLine;
        if ($yamlMetadata !== null) {
            $documentAttrs = array_replace_recursive($documentAttrs, $this->buildYamlMetadataAttrs($yamlMetadata));
        }
        if ($titleBlock !== null) {
            $documentAttrs = array_replace_recursive($documentAttrs, $this->buildTitleBlockAttrs($titleBlock));
        }

        for ($index = 0, $count = count($lines); $index < $count; $index++) {
            $line = $lines[$index];
            $codeBlock = $this->tryReadFencedCodeBlock($lines, $index);
            if ($codeBlock !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = $codeBlock;
                continue;
            }
            $literateHaskellCodeBlock = $paragraph === [] && $listStack === [] ? $this->tryReadLiterateHaskellCodeBlock($lines, $index) : null;
            if ($literateHaskellCodeBlock !== null) {
                $blocks[] = $literateHaskellCodeBlock;
                continue;
            }
            $blockQuote = $paragraph === [] && $listStack === [] ? $this->tryReadBlockQuote($lines, $index) : null;
            if ($blockQuote !== null) {
                $blocks[] = $blockQuote;
                continue;
            }
            $divBlock = $paragraph === [] && $listStack === [] ? $this->tryReadDivBlock($lines, $index) : null;
            if ($divBlock !== null) {
                $blocks[] = $divBlock;
                continue;
            }
            $docBookTable = $paragraph === [] && $listStack === [] ? $this->tryReadDocBookTableBlock($lines, $index) : null;
            if ($docBookTable !== null) {
                $blocks[] = $docBookTable;
                continue;
            }
            $htmlDocument = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlDocumentBlock($lines, $index) : null;
            if ($htmlDocument !== null) {
                $documentAttrs = array_replace($documentAttrs, $htmlDocument->attrs);
                array_push($blocks, ...$htmlDocument->children);
                continue;
            }
            if ($paragraph === [] && $listStack === [] && $this->tryReadEmptyHtmlTableBlock($lines, $index)) {
                continue;
            }
            $nestedHtmlDocumentTable = $paragraph === [] && $listStack === [] ? $this->tryReadStructuredHtmlDocumentTableBlock($lines, $index) : null;
            if ($nestedHtmlDocumentTable !== null) {
                $blocks[] = $nestedHtmlDocumentTable;
                continue;
            }
            $nestedHtmlTable = $paragraph === [] && $listStack === [] ? $this->tryReadStructuredHtmlTableBlock($lines, $index) : null;
            if ($nestedHtmlTable !== null) {
                $blocks[] = $nestedHtmlTable;
                continue;
            }
            $htmlCodeBlock = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlPreCodeBlock($lines, $index) : null;
            if ($htmlCodeBlock !== null) {
                $blocks[] = $htmlCodeBlock;
                continue;
            }
            $htmlBlockQuote = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlBlockQuoteBlock($lines, $index) : null;
            if ($htmlBlockQuote !== null) {
                $blocks[] = $htmlBlockQuote;
                continue;
            }
            $htmlHeading = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlHeadingBlock($lines, $index) : null;
            if ($htmlHeading !== null) {
                $blocks[] = $htmlHeading;
                continue;
            }
            $htmlList = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlListBlock($lines, $index) : null;
            if ($htmlList !== null) {
                $blocks[] = $htmlList;
                continue;
            }
            $htmlDefinitionList = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlDefinitionListBlock($lines, $index) : null;
            if ($htmlDefinitionList !== null) {
                $blocks[] = $htmlDefinitionList;
                continue;
            }
            $htmlInlineFragment = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlInlineFragmentBlock($lines, $index) : null;
            if ($htmlInlineFragment !== null) {
                $blocks[] = $htmlInlineFragment;
                continue;
            }
            $htmlParagraph = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlParagraphBlock($lines, $index) : null;
            if ($htmlParagraph !== null) {
                $blocks[] = $htmlParagraph;
                continue;
            }
            $htmlHorizontalRule = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlHorizontalRuleBlock($lines, $index) : null;
            if ($htmlHorizontalRule !== null) {
                $blocks[] = $htmlHorizontalRule;
                continue;
            }
            $rawHtmlContainer = $paragraph === [] && $listStack === [] ? $this->tryReadRawHtmlSingleLineContainerBlock($lines, $index) : null;
            if ($rawHtmlContainer !== null) {
                array_push($blocks, ...$rawHtmlContainer);
                continue;
            }
            $rawHtmlBlock = $paragraph === [] && $listStack === [] ? $this->tryReadRawHtmlBlock($lines, $index) : null;
            if ($rawHtmlBlock !== null) {
                $blocks[] = $rawHtmlBlock;
                continue;
            }
            $latexTableBlock = $paragraph === [] && $listStack === [] ? $this->tryReadLatexTableBlock($lines, $index) : null;
            if ($latexTableBlock !== null) {
                $blocks[] = $latexTableBlock;
                continue;
            }
            $rawTexBlock = $paragraph === [] && $listStack === [] ? $this->tryReadRawTexBlock($lines, $index) : null;
            if ($rawTexBlock !== null) {
                $blocks[] = $rawTexBlock;
                continue;
            }
            $gridTable = $this->tryReadGridTable($lines, $index);
            if ($gridTable !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = $gridTable;
                continue;
            }
            $simpleTable = $this->tryReadSimpleTable($lines, $index);
            if ($simpleTable !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = $simpleTable;
                continue;
            }
            $pipeTable = $this->tryReadPipeTable($lines, $index);
            if ($pipeTable !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = $pipeTable;
                continue;
            }
            if ($this->isHorizontalRule($line)) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = new AstNode('horizontal_rule');
                continue;
            }
            $lineBlock = $paragraph === [] && $listStack === [] ? $this->tryReadLineBlock($lines, $index) : null;
            if ($lineBlock !== null) {
                $blocks[] = $lineBlock;
                continue;
            }
            $setextHeading = $paragraph === [] && $listStack === [] ? $this->tryParseSetextMarkdownHeading($lines, $index) : null;
            if ($setextHeading !== null) {
                $text = $setextHeading['text'];
                $attrs = [
                    'level' => $setextHeading['level'],
                    'text' => $text,
                    'id' => $markdownHeadingIds[$index] ?? $setextHeading['id'] ?? '',
                ];
                if ($setextHeading['classes'] !== []) {
                    $attrs['classes'] = $setextHeading['classes'];
                }
                if ($setextHeading['attributes'] !== []) {
                    $attrs['attributes'] = $setextHeading['attributes'];
                }
                $blocks[] = new AstNode(
                    'heading',
                    $attrs,
                    $this->parseInlines($text)
                );
                $index++;
                continue;
            }
            $markdownHeading = $this->tryParseMarkdownHeading($line);
            if ($markdownHeading !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $text = $markdownHeading['text'];
                $attrs = [
                    'level' => $markdownHeading['level'],
                    'text' => $text,
                    'id' => $markdownHeadingIds[$index] ?? $markdownHeading['id'] ?? '',
                ];
                if ($markdownHeading['classes'] !== []) {
                    $attrs['classes'] = $markdownHeading['classes'];
                }
                if ($markdownHeading['attributes'] !== []) {
                    $attrs['attributes'] = $markdownHeading['attributes'];
                }
                $blocks[] = new AstNode(
                    'heading',
                    $attrs,
                    $this->parseInlines($text)
                );
                continue;
            }
            $listBlock = $paragraph === [] ? $this->tryReadListBlock($lines, $index) : null;
            if ($listBlock !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = $listBlock;
                continue;
            }
            $indentedCodeBlock = $listStack === [] ? $this->tryReadIndentedCodeBlock($lines, $index) : null;
            if ($indentedCodeBlock !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $blocks[] = $indentedCodeBlock;
                continue;
            }
            $definitionList = $this->tryReadDefinitionList($lines, $index);
            if ($definitionList !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = $definitionList;
                continue;
            }
            if (trim($line) === '') {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                continue;
            }
            $this->flushListStack($listStack, $blocks);
            $paragraph[] = trim($line);
        }
        $this->flushParagraph($paragraph, $blocks);
        $this->flushListStack($listStack, $blocks);

        $document = new AstNode('document', $documentAttrs, $blocks);
        $this->referenceLinks = $previousReferenceLinks;
        $this->footnoteDefinitions = $previousFootnoteDefinitions;
        $this->exampleReferences = $previousExampleReferences;
        $this->exampleNumbersByLine = $previousExampleNumbersByLine;
        $this->rawTexMacros = $previousRawTexMacros;

        return $document;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>, 1:array<string, mixed>|null}
     */
    private function extractYamlMetadataBlocks(array $lines): array
    {
        $bodyLines = [];
        $metadata = [];
        $hasMetadata = false;
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $fencedCodeEnd = $this->yamlMetadataFencedCodeBlockEnd($lines, $index);
            if ($fencedCodeEnd !== null) {
                for (; $index <= $fencedCodeEnd; $index++) {
                    $bodyLines[] = $lines[$index];
                }
                $index--;
                continue;
            }

            $block = $this->tryReadYamlMetadataBlock($lines, $index);
            if ($block !== null) {
                $metadata = array_replace($metadata, $block['metadata']);
                $hasMetadata = true;
                $index = $block['end'];
                continue;
            }

            $bodyLines[] = $lines[$index];
        }

        return [$bodyLines, $hasMetadata ? $metadata : null];
    }

    /**
     * @param list<string> $lines
     */
    private function yamlMetadataFencedCodeBlockEnd(array $lines, int $start): ?int
    {
        $line = $lines[$start] ?? '';
        if (preg_match('/^( {0,3})(`{3,}|~{3,})[ \t]*(.*)$/', $line, $m) !== 1) {
            return null;
        }

        $fence = $m[2];
        $fenceChar = $fence[0];
        $info = trim($m[3]);
        if ($fenceChar === '`' && str_contains($info, '`')) {
            return null;
        }

        $count = count($lines);
        for ($cursor = $start + 1; $cursor < $count; $cursor++) {
            if ($this->isClosingCodeFence($lines[$cursor], $fenceChar, strlen($fence))) {
                return $cursor;
            }
        }

        return $count - 1;
    }

    /**
     * @param list<string> $lines
     * @return array{end:int, metadata:array<string, mixed>}|null
     */
    private function tryReadYamlMetadataBlock(array $lines, int $start): ?array
    {
        if (preg_match('/^---[ \t]*$/', $lines[$start] ?? '') !== 1) {
            return null;
        }

        if ($start > 0 && trim($lines[$start - 1]) !== '') {
            return null;
        }

        if (!isset($lines[$start + 1]) || trim($lines[$start + 1]) === '') {
            return null;
        }

        $yamlLines = [];
        $count = count($lines);
        for ($cursor = $start + 1; $cursor < $count; $cursor++) {
            if (preg_match('/^(?:---|\.\.\.)[ \t]*$/', $lines[$cursor]) === 1) {
                $previousYamlMetadataAnchors = $this->yamlMetadataAnchors;
                $this->yamlMetadataAnchors = [];
                try {
                    $metadata = $this->parseYamlMetadataLines($yamlLines);
                } finally {
                    $this->yamlMetadataAnchors = $previousYamlMetadataAnchors;
                }
                if ($metadata === []) {
                    return null;
                }

                return ['end' => $cursor, 'metadata' => $metadata];
            }

            $yamlLines[] = $lines[$cursor];
        }

        return null;
    }

    /**
     * @param list<string> $lines
     * @return array<string, mixed>
     */
    private function parseYamlMetadataLines(array $lines): array
    {
        $jsonMetadata = $this->parseYamlJsonMetadataDocument($lines);
        if ($jsonMetadata !== null) {
            return $jsonMetadata;
        }

        $metadata = [];
        $count = count($lines);
        for ($index = 0; $index < $count;) {
            $line = $lines[$index];
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $index++;
                continue;
            }

            $explicitMapping = $this->parseYamlExplicitMappingPair($lines, $index);
            if ($explicitMapping !== null) {
                [$key, $sourceValue, $children, $nextIndex] = $explicitMapping;
                [$value] = $this->parseYamlMetadataValue($sourceValue, $children);
                if ($key === '<<') {
                    $metadata = $this->mergeYamlMapValue($metadata, $value);
                } else {
                    $metadata[$key] = $value;
                }

                $index = $nextIndex;
                continue;
            }

            $mapping = $this->parseYamlMappingLine($trimmed);
            if ($this->countIndentColumns($line) > 0 || $mapping === null) {
                $index++;
                continue;
            }

            [$key, $sourceValue] = $mapping;
            [$children, $nextIndex] = $this->collectYamlChildLines($lines, $index + 1);
            [$value] = $this->parseYamlMetadataValue($sourceValue, $children);
            if ($key === '<<') {
                $metadata = $this->mergeYamlMapValue($metadata, $value);
            } else {
                $metadata[$key] = $value;
            }

            $index = $nextIndex;
        }

        return $metadata;
    }

    /**
     * @param list<string> $lines
     * @return array{0:string, 1:string, 2:list<string>, 3:int}|null
     */
    private function parseYamlExplicitMappingPair(array $lines, int $start): ?array
    {
        $line = $lines[$start] ?? '';
        if ($this->countIndentColumns($line) > 0) {
            return null;
        }

        $trimmed = trim($line);
        if (!$this->isYamlExplicitMappingKeyLine($trimmed)) {
            return null;
        }

        $keySource = trim(substr($trimmed, 1));
        $cursor = $start + 1;
        $keyValue = null;
        if ($keySource === '') {
            $keyLines = [];
            $count = count($lines);
            while ($cursor < $count && $this->parseYamlExplicitMappingValueLine(trim($lines[$cursor])) === null) {
                $keyLines[] = $lines[$cursor];
                $cursor++;
            }

            if ($keyLines === [] || $cursor >= $count) {
                return null;
            }

            $keyValue = $this->parseYamlIndentedValue($keyLines);
        } else {
            $keyValue = $this->parseYamlScalarValue($keySource);
            while (
                isset($lines[$cursor])
                && (trim($lines[$cursor]) === '' || str_starts_with(trim($lines[$cursor]), '#'))
            ) {
                $cursor++;
            }
        }

        if (!isset($lines[$cursor])) {
            return null;
        }

        $sourceValue = $this->parseYamlExplicitMappingValueLine(trim($lines[$cursor]));
        if ($sourceValue === null) {
            return null;
        }

        $key = $this->normalizeYamlExplicitMappingKey($keyValue);
        if ($key === null || $key === '') {
            return null;
        }

        [$children, $nextIndex] = $this->collectYamlChildLines($lines, $cursor + 1);

        return [$key, $sourceValue, $children, $nextIndex];
    }

    private function isYamlExplicitMappingKeyLine(string $trimmed): bool
    {
        return $trimmed === '?' || preg_match('/^\?[ \t]+.+$/', $trimmed) === 1;
    }

    private function parseYamlExplicitMappingValueLine(string $trimmed): ?string
    {
        if (preg_match('/^:(?:[ \t]*(.*))?$/', $trimmed, $m) !== 1) {
            return null;
        }

        return rtrim($m[1] ?? '');
    }

    private function normalizeYamlExplicitMappingKey(mixed $key): ?string
    {
        if (is_string($key)) {
            return $key;
        }

        if (is_int($key) || is_float($key)) {
            return (string) $key;
        }

        if (is_bool($key)) {
            return $key ? 'true' : 'false';
        }

        if (is_array($key)) {
            if (!$this->isYamlAssociativeArray($key)) {
                return $this->normalizeYamlExplicitSequenceMappingKey($key);
            }

            return $this->normalizeYamlExplicitMapMappingKey($key);
        }

        return null;
    }

    private function normalizeYamlExplicitMapMappingKey(array $key): ?string
    {
        if (!$this->isYamlAssociativeArray($key)) {
            return $this->normalizeYamlExplicitSequenceMappingKey($key);
        }

        $items = [];
        foreach ($key as $mapKey => $value) {
            $normalizedKey = $this->formatYamlExplicitMapMappingKeyName($mapKey);
            $normalizedValue = $this->formatYamlExplicitSequenceMappingKeyItem($value);
            if ($normalizedKey === null || $normalizedValue === null) {
                return null;
            }

            $items[] = $normalizedKey . ': ' . $normalizedValue;
        }

        return '{' . implode(', ', $items) . '}';
    }

    private function formatYamlExplicitMapMappingKeyName(int|string $key): ?string
    {
        if (is_int($key)) {
            return (string) $key;
        }

        if (preg_match('/^[A-Za-z0-9_.-]+$/', $key) === 1) {
            return $key;
        }

        $encoded = json_encode($key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : null;
    }

    private function normalizeYamlExplicitSequenceMappingKey(array $key): ?string
    {
        if (!array_is_list($key)) {
            return null;
        }

        $items = [];
        foreach ($key as $item) {
            $normalized = $this->formatYamlExplicitSequenceMappingKeyItem($item);
            if ($normalized === null) {
                return null;
            }

            $items[] = $normalized;
        }

        return '[' . implode(', ', $items) . ']';
    }

    private function formatYamlExplicitSequenceMappingKeyItem(mixed $item): ?string
    {
        if (is_string($item)) {
            if (preg_match('/^[A-Za-z0-9_.:-]+$/', $item) === 1) {
                return $item;
            }

            $encoded = json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return is_string($encoded) ? $encoded : null;
        }

        if (is_int($item) || is_float($item)) {
            return (string) $item;
        }

        if (is_bool($item)) {
            return $item ? 'true' : 'false';
        }

        if ($item === null) {
            return 'null';
        }

        if (is_array($item)) {
            if (!$this->isYamlAssociativeArray($item)) {
                return $this->normalizeYamlExplicitSequenceMappingKey($item);
            }

            return $this->normalizeYamlExplicitMapMappingKey($item);
        }

        return null;
    }

    /**
     * @param list<string> $children
     * @return array{0:mixed, 1:string|null}
     */
    private function parseYamlMetadataValue(string $sourceValue, array $children): array
    {
        [$sourceValue, $anchorName, $tags] = $this->parseYamlValueDirectives($sourceValue);
        if ($this->yamlHasExplicitOrderedPairsTag($tags)) {
            $value = $this->parseYamlExplicitOrderedPairsValue($sourceValue, $children);
            $this->rememberYamlAnchor($anchorName, $value);

            return [$value, $anchorName];
        }

        if ($this->yamlHasExplicitTag($tags, 'set')) {
            $value = $this->parseYamlExplicitSetValue($sourceValue, $children);
            $this->rememberYamlAnchor($anchorName, $value);

            return [$value, $anchorName];
        }

        $blockScalarHeader = $this->parseYamlBlockScalarHeader($sourceValue);
        $multilineFlow = $this->parseYamlMultilineFlowCollection($sourceValue, $children);
        $tagsApplied = false;

        if ($multilineFlow !== null) {
            $value = $multilineFlow;
        } elseif ($blockScalarHeader !== null) {
            $value = $this->parseYamlBlockScalar(
                $children,
                $blockScalarHeader['style'],
                $blockScalarHeader['chomp'],
                $blockScalarHeader['indent']
            );
        } elseif ($sourceValue === '') {
            $value = $this->parseYamlIndentedValue($children);
        } else {
            $multiline = $this->parseYamlMultilineDoubleQuotedScalar($sourceValue, $children)
                ?? $this->parseYamlMultilineSingleQuotedScalar($sourceValue, $children);
            if ($multiline !== null) {
                $value = $multiline;
            } else {
                $value = $this->parseYamlScalarValueFromDirectives($sourceValue, null, $tags);
                $tagsApplied = true;
            }
        }

        if (!$tagsApplied) {
            $value = $this->applyYamlExplicitScalarTagToParsedValue($value, $tags);
        }
        $this->rememberYamlAnchor($anchorName, $value);

        return [$value, $anchorName];
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>, 1:int}
     */
    private function collectYamlChildLines(array $lines, int $start): array
    {
        $children = [];
        $count = count($lines);
        for ($index = $start; $index < $count; $index++) {
            $line = $lines[$index];
            if (
                trim($line) !== ''
                && $this->countIndentColumns($line) === 0
                && (
                    $this->parseYamlMappingLine(trim($line)) !== null
                    || $this->isYamlExplicitMappingKeyLine(trim($line))
                )
            ) {
                break;
            }

            $children[] = $line;
        }

        return [$children, $index];
    }

    /**
     * @return array{style:string, chomp:string|null, indent:int|null}|null
     */
    private function parseYamlBlockScalarHeader(string $value): ?array
    {
        $value = $this->stripYamlTrailingComment(trim($value));
        if ($value === '' || ($value[0] !== '|' && $value[0] !== '>')) {
            return null;
        }

        $chomp = null;
        $indent = null;
        $rest = substr($value, 1);
        $length = strlen($rest);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $rest[$offset];
            if (($char === '+' || $char === '-') && $chomp === null) {
                $chomp = $char;
                continue;
            }

            if ($char >= '1' && $char <= '9' && $indent === null) {
                $indent = (int) $char;
                continue;
            }

            return null;
        }

        return [
            'style' => $value[0],
            'chomp' => $chomp,
            'indent' => $indent,
        ];
    }

    /**
     * @param list<string> $lines
     */
    private function parseYamlBlockScalar(array $lines, string $style, ?string $chomp = null, ?int $indent = null): string
    {
        $normalized = $indent === null ? $this->stripYamlCommonIndent($lines) : $this->stripYamlExplicitIndent($lines, $indent);
        $rawText = implode("\n", $normalized);
        if ($style === '|') {
            return $this->applyYamlBlockScalarChomp($rawText, $chomp);
        }

        $trailingNewlines = preg_match('/\n+$/', $rawText, $m) === 1 ? $m[0] : '';
        $foldedText = $this->foldYamlBlockScalarText(rtrim($rawText, "\n"));
        return $chomp === '+' ? $foldedText . $trailingNewlines : $foldedText;
    }

    private function foldYamlBlockScalarText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $lines = explode("\n", $text);
        $folded = rtrim((string) array_shift($lines), " \t");
        $previousBlank = $folded === '';
        $previousMoreIndented = $folded !== '' && preg_match('/^[ \t]/', $folded) === 1;

        foreach ($lines as $line) {
            $blank = trim($line) === '';
            $moreIndented = !$blank && preg_match('/^[ \t]/', $line) === 1;

            if ($blank || $previousBlank || $previousMoreIndented || $moreIndented) {
                $folded = rtrim($folded, " \t") . "\n" . rtrim($line, " \t");
            } else {
                $folded = rtrim($folded, " \t") . ' ' . trim($line);
            }

            $previousBlank = $blank;
            $previousMoreIndented = $moreIndented;
        }

        return $folded;
    }

    private function applyYamlBlockScalarChomp(string $text, ?string $chomp): string
    {
        if ($chomp === '+') {
            return $text;
        }

        return rtrim($text, "\n");
    }

    /**
     * @param list<string> $lines
     * @return mixed
     */
    private function parseYamlIndentedValue(array $lines): mixed
    {
        $normalized = $this->stripYamlCommonIndent($lines);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
        }

        if ($normalized === []) {
            return null;
        }

        if (preg_match('/^-[ \t]?(.*)$/', $normalized[0]) === 1) {
            return $this->parseYamlSequence($normalized);
        }

        if ($this->startsWithYamlMapping($normalized)) {
            return $this->parseYamlMetadataLines($normalized);
        }

        if (count($normalized) === 1) {
            return $this->parseYamlScalarValue(trim($normalized[0]));
        }

        return rtrim(implode("\n", $normalized));
    }

    /**
     * @param list<string> $lines
     * @return list<mixed>
     */
    private function parseYamlSequence(array $lines): array
    {
        $items = [];
        $count = count($lines);
        for ($index = 0; $index < $count;) {
            $line = $lines[$index];
            if (trim($line) === '') {
                $index++;
                continue;
            }

            if (preg_match('/^-[ \t]?(.*)$/', $line, $m) !== 1) {
                $index++;
                continue;
            }

            $sourceValue = rtrim($m[1]);
            [$sourceValue, $anchorName, $tags] = $this->parseYamlValueDirectives($sourceValue);
            $children = [];
            $index++;
            while ($index < $count && preg_match('/^-[ \t]?/', $lines[$index]) !== 1) {
                $children[] = $lines[$index];
                $index++;
            }

            if ($this->yamlHasExplicitOrderedPairsTag($tags)) {
                $value = $this->parseYamlExplicitOrderedPairsValue($sourceValue, $children);
                $this->rememberYamlAnchor($anchorName, $value);
                $items[] = $value;
                continue;
            }

            if ($this->yamlHasExplicitTag($tags, 'set')) {
                $value = $this->parseYamlExplicitSetValue($sourceValue, $children);
                $this->rememberYamlAnchor($anchorName, $value);
                $items[] = $value;
                continue;
            }

            $blockScalarHeader = $this->parseYamlBlockScalarHeader($sourceValue);
            $multilineFlow = $this->parseYamlMultilineFlowCollection($sourceValue, $children);
            if ($multilineFlow !== null) {
                $multilineFlow = $this->applyYamlExplicitScalarTagToParsedValue($multilineFlow, $tags);
                $this->rememberYamlAnchor($anchorName, $multilineFlow);
                $items[] = $multilineFlow;
                continue;
            }

            if ($blockScalarHeader !== null) {
                $value = $this->parseYamlBlockScalar(
                    $children,
                    $blockScalarHeader['style'],
                    $blockScalarHeader['chomp'],
                    $blockScalarHeader['indent']
                );
                $value = $this->applyYamlExplicitScalarTagToParsedValue($value, $tags);
                $this->rememberYamlAnchor($anchorName, $value);
                $items[] = $value;
                continue;
            }

            $multiline = $this->parseYamlMultilineDoubleQuotedScalar($sourceValue, $children)
                ?? $this->parseYamlMultilineSingleQuotedScalar($sourceValue, $children);
            if ($multiline !== null) {
                $multiline = $this->applyYamlExplicitScalarTagToParsedValue($multiline, $tags);
                $this->rememberYamlAnchor($anchorName, $multiline);
                $items[] = $multiline;
                continue;
            }

            if ($sourceValue === '') {
                $value = $this->parseYamlIndentedValue($children);
                $value = $this->applyYamlExplicitScalarTagToParsedValue($value, $tags);
                $this->rememberYamlAnchor($anchorName, $value);
                $items[] = $value;
                continue;
            }

            $childLines = $children === [] ? [] : $this->stripYamlCommonIndent($children);
            if (preg_match('/^-[ \t]+/', $sourceValue) === 1) {
                $value = $this->parseYamlSequence(array_merge([$sourceValue], $childLines));
                $this->rememberYamlAnchor($anchorName, $value);
                $items[] = $value;
                continue;
            }

            if ($this->isYamlCompactSequenceMappingSource($sourceValue)) {
                $value = $this->parseYamlMetadataLines(array_merge([$sourceValue], $childLines));
                $this->rememberYamlAnchor($anchorName, $value);
                $items[] = $value;
                continue;
            }

            if ($childLines !== [] && $this->isYamlExplicitMappingKeyLine(trim($sourceValue))) {
                $value = $this->parseYamlMetadataLines(array_merge([$sourceValue], $childLines));
                $this->rememberYamlAnchor($anchorName, $value);
                $items[] = $value;
                continue;
            }

            $value = $this->parseYamlScalarValueFromDirectives($sourceValue, null, $tags);
            $this->rememberYamlAnchor($anchorName, $value);
            $items[] = $value;
        }

        return $items;
    }

    private function isYamlCompactSequenceMappingSource(string $sourceValue): bool
    {
        $sourceValue = trim($sourceValue);
        if ($sourceValue === '') {
            return false;
        }

        return $this->parseYamlMappingLine($sourceValue) !== null;
    }

    /**
     * @param list<string> $lines
     */
    private function startsWithYamlMapping(array $lines): bool
    {
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            return $this->parseYamlMappingLine($trimmed) !== null || $this->isYamlExplicitMappingKeyLine($trimmed);
        }

        return false;
    }

    /**
     * @param list<string> $lines
     * @return array<string, mixed>|null
     */
    private function parseYamlJsonMetadataDocument(array $lines): ?array
    {
        $source = trim(implode("\n", $lines));
        if ($source === '' || $source[0] !== '{' || !str_ends_with($source, '}')) {
            return null;
        }

        try {
            $decoded = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private function parseYamlMappingLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (preg_match('/^(<<):(?:[ \t]*(.*))?$/', $line, $m) === 1) {
            return [$m[1], rtrim($m[2] ?? '')];
        }

        if ($line[0] === '"' || $line[0] === "'") {
            $quote = $line[0];
            $length = strlen($line);
            for ($offset = 1; $offset < $length; $offset++) {
                $char = $line[$offset];
                if ($quote === "'" && $char === "'" && ($line[$offset + 1] ?? '') === "'") {
                    $offset++;
                    continue;
                }
                if ($char === $quote && ($quote === "'" || $line[$offset - 1] !== '\\')) {
                    $afterKey = ltrim(substr($line, $offset + 1));
                    if ($afterKey === '' || $afterKey[0] !== ':') {
                        return null;
                    }

                    return [
                        $this->unquoteYamlScalar(substr($line, 0, $offset + 1)),
                        ltrim(substr($afterKey, 1)),
                    ];
                }
            }

            return null;
        }

        return $this->splitYamlPlainMappingLine($line);
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private function splitYamlPlainMappingLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || $line[0] === '?' || $line[0] === '-' || $line[0] === '[' || $line[0] === '{') {
            return null;
        }

        $quote = null;
        $squareDepth = 0;
        $curlyDepth = 0;
        $length = strlen($line);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $line[$offset];
            if ($quote !== null) {
                if ($quote === "'" && $char === "'" && ($line[$offset + 1] ?? '') === "'") {
                    $offset++;
                    continue;
                }

                if ($char === $quote && ($quote === "'" || $line[$offset - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '[') {
                $squareDepth++;
                continue;
            }

            if ($char === ']') {
                $squareDepth = max(0, $squareDepth - 1);
                continue;
            }

            if ($char === '{') {
                $curlyDepth++;
                continue;
            }

            if ($char === '}') {
                $curlyDepth = max(0, $curlyDepth - 1);
                continue;
            }

            if ($char !== ':' || $squareDepth !== 0 || $curlyDepth !== 0) {
                continue;
            }

            $afterColon = substr($line, $offset + 1);
            if ($afterColon !== '' && preg_match('/^[ \t]/', $afterColon) !== 1) {
                continue;
            }

            $key = rtrim(substr($line, 0, $offset));
            if ($key === '') {
                return null;
            }

            return [$key, rtrim(ltrim($afterColon))];
        }

        return null;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function stripYamlCommonIndent(array $lines): array
    {
        $expanded = array_map(fn (string $line): string => $this->expandTabsToSpaces($line), $lines);
        $minIndent = null;
        foreach ($expanded as $line) {
            if (trim($line) === '') {
                continue;
            }
            $indent = strspn($line, ' ');
            $minIndent = $minIndent === null ? $indent : min($minIndent, $indent);
        }

        if ($minIndent === null || $minIndent === 0) {
            return $expanded;
        }

        return array_map(
            static fn (string $line): string => trim($line) === '' ? '' : substr($line, $minIndent),
            $expanded
        );
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function stripYamlExplicitIndent(array $lines, int $indent): array
    {
        $expanded = array_map(fn (string $line): string => $this->expandTabsToSpaces($line), $lines);

        return array_map(
            static fn (string $line): string => trim($line) === '' ? '' : substr($line, min($indent, strspn($line, ' '))),
            $expanded
        );
    }

    private function stripYamlTrailingComment(string $value): string
    {
        $quote = null;
        $squareDepth = 0;
        $curlyDepth = 0;
        $length = strlen($value);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $value[$offset];
            if ($quote !== null) {
                if ($quote === "'" && $char === "'" && ($value[$offset + 1] ?? '') === "'") {
                    $offset++;
                    continue;
                }
                if ($char === $quote && ($quote === "'" || $value[$offset - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '[') {
                $squareDepth++;
                continue;
            }

            if ($char === ']') {
                $squareDepth = max(0, $squareDepth - 1);
                continue;
            }

            if ($char === '{') {
                $curlyDepth++;
                continue;
            }

            if ($char === '}') {
                $curlyDepth = max(0, $curlyDepth - 1);
                continue;
            }

            if (
                $char === '#'
                && $squareDepth === 0
                && $curlyDepth === 0
                && ($offset === 0 || ctype_space($value[$offset - 1]))
            ) {
                return rtrim(substr($value, 0, $offset));
            }
        }

        return $value;
    }

    /**
     * @return mixed
     */
    private function parseYamlScalarValue(string $value): mixed
    {
        [$value, $anchorName, $tags] = $this->parseYamlValueDirectives($value);
        $parsed = $this->parseYamlScalarValueFromDirectives($value, $anchorName, $tags);

        return $parsed;
    }

    /**
     * @param list<string> $tags
     * @return mixed
     */
    private function parseYamlScalarValueFromDirectives(string $value, ?string $anchorName, array $tags): mixed
    {
        $value = trim($this->stripYamlTrailingComment($value));
        if ($value === '') {
            $parsed = null;
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if ($this->isYamlAliasScalar($value)) {
            $parsed = $this->yamlAliasValue(substr($value, 1));
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if ($this->yamlHasExplicitOrderedPairsTag($tags)) {
            $parsed = $this->parseYamlExplicitOrderedPairsValue($value, []);
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if ($this->yamlHasExplicitTag($tags, 'set')) {
            $parsed = $this->parseYamlExplicitSetValue($value, []);
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        $explicitScalarTag = $this->yamlExplicitScalarTag($tags);
        if ($explicitScalarTag !== null) {
            $parsed = $this->parseYamlExplicitTaggedScalar($value, $explicitScalarTag);
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if ($value[0] === '[' && str_ends_with($value, ']')) {
            $parsed = array_map(
                fn (string $item): mixed => $this->parseYamlScalarValue($item),
                $this->splitYamlInlineList(substr($value, 1, -1))
            );
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if ($value[0] === '{' && str_ends_with($value, '}')) {
            $parsed = $this->parseYamlInlineMap(substr($value, 1, -1));
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'"))) {
            $parsed = $this->unquoteYamlScalar($value);
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        $parsed = match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null', '~' => null,
            default => is_numeric($value) ? $this->parseYamlNumericScalar($value) : $value,
        };
        $this->rememberYamlAnchor($anchorName, $parsed);

        return $parsed;
    }

    /**
     * @return list<string>
     */
    private function splitYamlInlineList(string $source): array
    {
        return $this->splitYamlFlowItems($source);
    }

    /**
     * @param list<string> $continuationLines
     */
    private function parseYamlMultilineFlowCollection(string $sourceValue, array $continuationLines): mixed
    {
        $sourceValue = ltrim($sourceValue);
        if (
            $continuationLines === []
            || $sourceValue === ''
            || ($sourceValue[0] !== '[' && $sourceValue[0] !== '{')
        ) {
            return null;
        }

        $candidate = $this->stripYamlTrailingComment(
            trim($this->stripYamlFlowComments($sourceValue . "\n" . implode("\n", $continuationLines)))
        );
        if ($candidate === '') {
            return null;
        }

        $closing = $sourceValue[0] === '[' ? ']' : '}';
        if (!str_ends_with(rtrim($candidate), $closing) || !$this->isBalancedYamlFlowCollection($candidate)) {
            return null;
        }

        return $this->parseYamlScalarValue($candidate);
    }

    private function stripYamlFlowComments(string $source): string
    {
        $clean = '';
        $quote = null;
        $length = strlen($source);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($quote !== null) {
                $clean .= $char;
                if ($quote === "'" && $char === "'" && ($source[$offset + 1] ?? '') === "'") {
                    $offset++;
                    $clean .= $source[$offset];
                    continue;
                }

                if ($char === $quote && ($quote === "'" || $source[$offset - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $clean .= $char;
                continue;
            }

            if ($char === '#' && ($offset === 0 || ctype_space($source[$offset - 1]))) {
                $clean = rtrim($clean, " \t");
                while ($offset < $length && $source[$offset] !== "\n") {
                    $offset++;
                }
                if ($offset < $length) {
                    $clean .= "\n";
                }
                continue;
            }

            $clean .= $char;
        }

        return $clean;
    }

    private function isBalancedYamlFlowCollection(string $source): bool
    {
        $quote = null;
        $squareDepth = 0;
        $curlyDepth = 0;
        $length = strlen($source);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($quote !== null) {
                if ($quote === "'" && $char === "'" && ($source[$offset + 1] ?? '') === "'") {
                    $offset++;
                    continue;
                }
                if ($char === $quote && ($quote === "'" || $source[$offset - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '[') {
                $squareDepth++;
                continue;
            }

            if ($char === ']') {
                $squareDepth--;
                if ($squareDepth < 0) {
                    return false;
                }
                continue;
            }

            if ($char === '{') {
                $curlyDepth++;
                continue;
            }

            if ($char === '}') {
                $curlyDepth--;
                if ($curlyDepth < 0) {
                    return false;
                }
            }
        }

        return $quote === null && $squareDepth === 0 && $curlyDepth === 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYamlInlineMap(string $source): array
    {
        $map = [];
        foreach ($this->splitYamlFlowItems($source) as $item) {
            $mapping = $this->splitYamlFlowMappingItem($item);
            if ($mapping === null) {
                continue;
            }

            [$key, $value] = $mapping;
            $key = $this->normalizeYamlFlowKey($key);
            if ($key === '') {
                continue;
            }

            $value = $this->parseYamlScalarValue($value);
            if ($key === '<<') {
                $map = $this->mergeYamlMapValue($map, $value);
            } else {
                $map[$key] = $value;
            }
        }

        return $map;
    }

    /**
     * @param list<string> $children
     * @return array<string, null>
     */
    private function parseYamlExplicitSetValue(string $sourceValue, array $children): array
    {
        $sourceValue = ltrim($sourceValue);
        if ($sourceValue !== '') {
            $candidate = $children === []
                ? trim($this->stripYamlTrailingComment($sourceValue))
                : $this->stripYamlTrailingComment(
                    trim($this->stripYamlFlowComments($sourceValue . "\n" . implode("\n", $children)))
                );

            if ($candidate !== '' && $candidate[0] === '{' && str_ends_with(rtrim($candidate), '}') && $this->isBalancedYamlFlowCollection($candidate)) {
                return $this->parseYamlFlowSet(substr(rtrim($candidate), 1, -1));
            }

            return [];
        }

        $normalized = $this->stripYamlCommonIndent($children);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
        }

        if ($normalized !== []) {
            $first = trim($normalized[0]);
            $candidate = $this->stripYamlTrailingComment(
                trim($this->stripYamlFlowComments(implode("\n", $normalized)))
            );
            if ($first !== '' && $first[0] === '{' && str_ends_with(rtrim($candidate), '}') && $this->isBalancedYamlFlowCollection($candidate)) {
                return $this->parseYamlFlowSet(substr(rtrim($candidate), 1, -1));
            }
        }

        return $this->parseYamlBlockSet($normalized);
    }

    /**
     * @return array<string, null>
     */
    private function parseYamlFlowSet(string $source): array
    {
        $set = [];
        foreach ($this->splitYamlFlowItems($source) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }

            if ($item[0] === '?') {
                $item = trim(substr($item, 1));
            }

            if ($item === '') {
                continue;
            }

            $key = $this->normalizeYamlExplicitMappingKey($this->parseYamlScalarValue($item));
            if ($key === null || $key === '') {
                continue;
            }

            $set[$key] = null;
        }

        return $set;
    }

    /**
     * @param list<string> $lines
     * @return array<string, null>
     */
    private function parseYamlBlockSet(array $lines): array
    {
        $set = [];
        $count = count($lines);
        for ($index = 0; $index < $count; $index++) {
            $trimmed = trim($this->stripYamlTrailingComment($lines[$index]));
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if ($this->parseYamlExplicitMappingValueLine($trimmed) !== null) {
                continue;
            }

            if ($this->isYamlExplicitMappingKeyLine($trimmed)) {
                $keySource = trim(substr($trimmed, 1));
                if ($keySource === '') {
                    $keyLines = [];
                    for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                        $candidate = trim($lines[$cursor]);
                        if ($this->isYamlExplicitMappingKeyLine($candidate) || $this->parseYamlExplicitMappingValueLine($candidate) !== null) {
                            break;
                        }
                        $keyLines[] = $lines[$cursor];
                    }
                    $keyValue = $keyLines === [] ? null : $this->parseYamlIndentedValue($keyLines);
                } else {
                    $keyValue = $this->parseYamlScalarValue($keySource);
                }
            } else {
                $keyValue = $this->parseYamlScalarValue($trimmed);
            }

            $key = $this->normalizeYamlExplicitMappingKey($keyValue ?? null);
            if ($key === null || $key === '') {
                continue;
            }

            $set[$key] = null;
        }

        return $set;
    }

    /**
     * @param list<string> $children
     * @return list<array{key:string, value:mixed}>
     */
    private function parseYamlExplicitOrderedPairsValue(string $sourceValue, array $children): array
    {
        $sourceValue = ltrim($sourceValue);
        if ($sourceValue !== '') {
            $candidate = $children === []
                ? trim($this->stripYamlTrailingComment($sourceValue))
                : $this->stripYamlTrailingComment(
                    trim($this->stripYamlFlowComments($sourceValue . "\n" . implode("\n", $children)))
                );

            $flowPairs = $this->parseYamlExplicitOrderedPairsFlowCandidate($candidate);
            if ($flowPairs !== null) {
                return $flowPairs;
            }

            return [];
        }

        $normalized = $this->stripYamlCommonIndent($children);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
        }

        if ($normalized !== []) {
            $candidate = $this->stripYamlTrailingComment(
                trim($this->stripYamlFlowComments(implode("\n", $normalized)))
            );
            $flowPairs = $this->parseYamlExplicitOrderedPairsFlowCandidate($candidate);
            if ($flowPairs !== null) {
                return $flowPairs;
            }
        }

        return $this->yamlOrderedPairsFromSequence($this->parseYamlSequence($normalized));
    }

    /**
     * @return list<array{key:string, value:mixed}>|null
     */
    private function parseYamlExplicitOrderedPairsFlowCandidate(string $candidate): ?array
    {
        $candidate = trim($candidate);
        if ($candidate === '' || $candidate[0] !== '[' || !str_ends_with(rtrim($candidate), ']')) {
            return null;
        }

        if (!$this->isBalancedYamlFlowCollection($candidate)) {
            return null;
        }

        $source = substr(rtrim($candidate), 1, -1);
        $items = array_map(
            fn (string $item): mixed => $this->parseYamlScalarValue($item),
            $this->splitYamlFlowItems($source)
        );

        return $this->yamlOrderedPairsFromSequence($items);
    }

    /**
     * @param list<mixed> $items
     * @return list<array{key:string, value:mixed}>
     */
    private function yamlOrderedPairsFromSequence(array $items): array
    {
        $pairs = [];
        foreach ($items as $item) {
            if ($this->isYamlAssociativeArray($item)) {
                foreach ($item as $key => $value) {
                    $pairKey = (string) $key;
                    if ($pairKey === '') {
                        continue;
                    }

                    $pairs[] = [
                        'key' => $pairKey,
                        'value' => $this->cloneYamlMetadataValue($value),
                    ];
                }
                continue;
            }

            $pairKey = $this->normalizeYamlExplicitMappingKey($item);
            if ($pairKey === null || $pairKey === '') {
                continue;
            }

            $pairs[] = [
                'key' => $pairKey,
                'value' => null,
            ];
        }

        return $pairs;
    }

    /**
     * @return list<string>
     */
    private function splitYamlFlowItems(string $source): array
    {
        $items = [];
        $buffer = '';
        $quote = null;
        $squareDepth = 0;
        $curlyDepth = 0;
        $length = strlen($source);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($quote !== null) {
                $buffer .= $char;
                if ($quote === "'" && $char === "'" && ($source[$offset + 1] ?? '') === "'") {
                    $offset++;
                    $buffer .= $source[$offset];
                    continue;
                }
                if ($char === $quote && ($quote === "'" || $source[$offset - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '[') {
                $squareDepth++;
                $buffer .= $char;
                continue;
            }

            if ($char === ']') {
                $squareDepth = max(0, $squareDepth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === '{') {
                $curlyDepth++;
                $buffer .= $char;
                continue;
            }

            if ($char === '}') {
                $curlyDepth = max(0, $curlyDepth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === ',' && $squareDepth === 0 && $curlyDepth === 0) {
                $items[] = trim($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $items[] = trim($buffer);
        }

        return $items;
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private function splitYamlFlowMappingItem(string $item): ?array
    {
        $quote = null;
        $squareDepth = 0;
        $curlyDepth = 0;
        $length = strlen($item);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $item[$offset];
            if ($quote !== null) {
                if ($quote === "'" && $char === "'" && ($item[$offset + 1] ?? '') === "'") {
                    $offset++;
                    continue;
                }
                if ($char === $quote && ($quote === "'" || $item[$offset - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '[') {
                $squareDepth++;
                continue;
            }

            if ($char === ']') {
                $squareDepth = max(0, $squareDepth - 1);
                continue;
            }

            if ($char === '{') {
                $curlyDepth++;
                continue;
            }

            if ($char === '}') {
                $curlyDepth = max(0, $curlyDepth - 1);
                continue;
            }

            if ($char === ':' && $squareDepth === 0 && $curlyDepth === 0) {
                $key = trim(substr($item, 0, $offset));
                if ($key === '') {
                    return null;
                }

                return [$key, trim(substr($item, $offset + 1))];
            }
        }

        return null;
    }

    private function normalizeYamlFlowKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        if (preg_match('/^\?[ \t]+(.+)$/s', $key, $m) === 1) {
            $normalized = $this->normalizeYamlExplicitMappingKey($this->parseYamlScalarValue(trim($m[1])));

            return $normalized ?? '';
        }

        if (($key[0] === '"' && str_ends_with($key, '"')) || ($key[0] === "'" && str_ends_with($key, "'"))) {
            return $this->unquoteYamlScalar($key);
        }

        return $key;
    }

    /**
     * @param list<string> $continuationLines
     */
    private function parseYamlMultilineDoubleQuotedScalar(string $sourceValue, array $continuationLines): ?string
    {
        $sourceValue = ltrim($sourceValue);
        if ($continuationLines === [] || $sourceValue === '' || $sourceValue[0] !== '"') {
            return null;
        }

        if ($this->extractYamlDoubleQuotedInner($sourceValue) !== null) {
            return null;
        }

        $raw = $sourceValue . "\n" . implode("\n", $continuationLines);
        $inner = $this->extractYamlDoubleQuotedInner($raw);
        if ($inner === null) {
            return null;
        }

        return $this->decodeYamlDoubleQuotedScalar($this->foldYamlDoubleQuotedContinuationLines($inner));
    }

    /**
     * @param list<string> $continuationLines
     */
    private function parseYamlMultilineSingleQuotedScalar(string $sourceValue, array $continuationLines): ?string
    {
        $sourceValue = ltrim($sourceValue);
        if ($continuationLines === [] || $sourceValue === '' || $sourceValue[0] !== "'") {
            return null;
        }

        if ($this->extractYamlSingleQuotedInner($sourceValue) !== null) {
            return null;
        }

        $raw = $sourceValue . "\n" . implode("\n", $continuationLines);
        $inner = $this->extractYamlSingleQuotedInner($raw);
        if ($inner === null) {
            return null;
        }

        return str_replace("''", "'", $this->foldYamlSingleQuotedContinuationLines($inner));
    }

    private function extractYamlDoubleQuotedInner(string $source): ?string
    {
        $source = ltrim($source);
        if ($source === '' || $source[0] !== '"') {
            return null;
        }

        $length = strlen($source);
        for ($offset = 1; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($char === '\\') {
                $offset++;
                continue;
            }

            if ($char === '"') {
                return substr($source, 1, $offset - 1);
            }
        }

        return null;
    }

    private function extractYamlSingleQuotedInner(string $source): ?string
    {
        $source = ltrim($source);
        if ($source === '' || $source[0] !== "'") {
            return null;
        }

        $length = strlen($source);
        for ($offset = 1; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($char === "'" && ($source[$offset + 1] ?? '') === "'") {
                $offset++;
                continue;
            }

            if ($char === "'") {
                return substr($source, 1, $offset - 1);
            }
        }

        return null;
    }

    private function foldYamlDoubleQuotedContinuationLines(string $inner): string
    {
        return $this->foldYamlQuotedContinuationLines($inner, true);
    }

    private function foldYamlSingleQuotedContinuationLines(string $inner): string
    {
        return $this->foldYamlQuotedContinuationLines($inner, false);
    }

    private function foldYamlQuotedContinuationLines(string $inner, bool $allowEscapedContinuation): string
    {
        if (!str_contains($inner, "\n")) {
            return $inner;
        }

        $lines = explode("\n", $inner);
        $folded = (string) array_shift($lines);
        foreach ($lines as $line) {
            $content = ltrim($line, " \t");
            if ($allowEscapedContinuation && str_ends_with($folded, '\\')) {
                $folded = substr($folded, 0, -1) . $content;
                continue;
            }

            if (trim($line) === '') {
                $folded = rtrim($folded, " \t") . "\n";
                continue;
            }

            $folded = str_ends_with($folded, "\n")
                ? $folded . $content
                : rtrim($folded, " \t") . ' ' . $content;
        }

        return $folded;
    }

    private function unquoteYamlScalar(string $value): string
    {
        $quote = $value[0];
        $inner = substr($value, 1, -1);
        if ($quote === "'") {
            return str_replace("''", "'", $this->foldYamlSingleQuotedContinuationLines($inner));
        }

        return $this->decodeYamlDoubleQuotedScalar($this->foldYamlDoubleQuotedContinuationLines($inner));
    }

    private function decodeYamlDoubleQuotedScalar(string $inner): string
    {
        $decoded = '';
        $length = strlen($inner);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $inner[$offset];
            if ($char !== '\\' || $offset === $length - 1) {
                $decoded .= $char;
                continue;
            }

            $escape = $inner[++$offset];
            $mapped = match ($escape) {
                '0' => "\0",
                'a' => "\x07",
                'b' => "\x08",
                't' => "\t",
                'n' => "\n",
                'v' => "\x0b",
                'f' => "\f",
                'r' => "\r",
                'e' => "\x1b",
                ' ' => ' ',
                '"' => '"',
                '/' => '/',
                '\\' => '\\',
                'N' => $this->yamlCodepointToUtf8(0x85),
                '_' => $this->yamlCodepointToUtf8(0xA0),
                'L' => $this->yamlCodepointToUtf8(0x2028),
                'P' => $this->yamlCodepointToUtf8(0x2029),
                default => null,
            };

            if ($mapped !== null) {
                $decoded .= $mapped;
                continue;
            }

            if ($escape === 'x' || $escape === 'u' || $escape === 'U') {
                $unicode = $this->decodeYamlHexEscape($inner, $offset, $length, $escape);
                if ($unicode !== null) {
                    $decoded .= $unicode;
                    continue;
                }
            }

            $decoded .= '\\' . $escape;
        }

        return $decoded;
    }

    private function decodeYamlHexEscape(string $inner, int &$offset, int $length, string $escape): ?string
    {
        $digits = match ($escape) {
            'x' => 2,
            'u' => 4,
            'U' => 8,
            default => 0,
        };

        if ($digits === 0 || $offset + $digits >= $length) {
            return null;
        }

        $hex = substr($inner, $offset + 1, $digits);
        if (!ctype_xdigit($hex)) {
            return null;
        }

        $codepoint = hexdec($hex);
        $utf8 = $this->yamlCodepointToUtf8($codepoint);
        if ($utf8 === null) {
            return null;
        }

        $offset += $digits;

        return $utf8;
    }

    private function yamlCodepointToUtf8(int $codepoint): ?string
    {
        if ($codepoint < 0 || $codepoint > 0x10FFFF || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF)) {
            return null;
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

    private function parseYamlNumericScalar(string $value): int|float
    {
        return str_contains($value, '.') || stripos($value, 'e') !== false ? (float) $value : (int) $value;
    }

    /**
     * @return array{0:string, 1:string|null, 2:list<string>}
     */
    private function parseYamlValueDirectives(string $value): array
    {
        $value = ltrim($value);
        $anchorName = null;
        $tags = [];

        while ($value !== '') {
            if (preg_match('/^&([A-Za-z0-9_.-]+)(?=$|[ \t])/', $value, $m) === 1) {
                $anchorName = $m[1];
                $value = ltrim(substr($value, strlen($m[0])));
                continue;
            }

            if (preg_match('/^!<([^>]+)>(?=$|[ \t])/', $value, $m) === 1) {
                $tags[] = $m[1];
                $value = ltrim(substr($value, strlen($m[0])));
                continue;
            }

            if (preg_match('/^!(?=$|[ \t])/', $value) === 1) {
                $tags[] = '!';
                $value = ltrim(substr($value, 1));
                continue;
            }

            if (preg_match('/^(!{1,2}[A-Za-z0-9_.:\/-]+)(?=$|[ \t])/', $value, $m) === 1) {
                $tags[] = $m[1];
                $value = ltrim(substr($value, strlen($m[0])));
                continue;
            }

            break;
        }

        return [$value, $anchorName, $tags];
    }

    private function yamlExplicitScalarTag(array $tags): ?string
    {
        foreach ($tags as $tag) {
            $normalized = $this->normalizeYamlTag($tag);

            if (in_array($normalized, ['str', 'int', 'float', 'bool', 'null', 'timestamp', 'binary'], true)) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param list<string> $tags
     */
    private function yamlHasExplicitTag(array $tags, string $expected): bool
    {
        foreach ($tags as $tag) {
            if ($this->normalizeYamlTag($tag) === $expected) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $tags
     */
    private function yamlHasExplicitOrderedPairsTag(array $tags): bool
    {
        return $this->yamlHasExplicitTag($tags, 'omap') || $this->yamlHasExplicitTag($tags, 'pairs');
    }

    private function normalizeYamlTag(string $tag): string
    {
        $normalized = strtolower($tag);
        if (str_starts_with($normalized, 'tag:yaml.org,2002:')) {
            return substr($normalized, strlen('tag:yaml.org,2002:'));
        }

        if (str_starts_with($normalized, '!!')) {
            return substr($normalized, 2);
        }

        if (str_starts_with($normalized, '!')) {
            return substr($normalized, 1);
        }

        return $normalized;
    }

    private function parseYamlExplicitTaggedScalar(string $value, string $tag): mixed
    {
        $scalar = (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))
            ? $this->unquoteYamlScalar($value)
            : $value;

        return match ($tag) {
            'str' => $scalar,
            'int' => $this->parseYamlExplicitIntegerScalar($scalar),
            'float' => $this->parseYamlExplicitFloatScalar($scalar),
            'bool' => $this->parseYamlExplicitBooleanScalar($scalar),
            'null' => null,
            'timestamp' => $this->parseYamlExplicitTimestampScalar($scalar),
            'binary' => $this->parseYamlExplicitBinaryScalar($scalar),
            default => $scalar,
        };
    }

    /**
     * @param list<string> $tags
     */
    private function applyYamlExplicitScalarTagToParsedValue(mixed $value, array $tags): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $tag = $this->yamlExplicitScalarTag($tags);
        if ($tag === null) {
            return $value;
        }

        return $this->parseYamlExplicitTaggedScalar($value, $tag);
    }

    private function parseYamlExplicitIntegerScalar(string $value): int|string
    {
        $normalized = str_replace('_', '', trim($value));
        if ($normalized === '') {
            return $value;
        }

        $sign = 1;
        if ($normalized[0] === '+' || $normalized[0] === '-') {
            $sign = $normalized[0] === '-' ? -1 : 1;
            $normalized = substr($normalized, 1);
        }

        if ($normalized === '') {
            return $value;
        }

        $base = 10;
        $digits = $normalized;
        if (preg_match('/^0b([01]+)$/i', $normalized, $m) === 1) {
            $base = 2;
            $digits = $m[1];
        } elseif (preg_match('/^0o([0-7]+)$/i', $normalized, $m) === 1) {
            $base = 8;
            $digits = $m[1];
        } elseif (preg_match('/^0x([0-9a-f]+)$/i', $normalized, $m) === 1) {
            $base = 16;
            $digits = $m[1];
        } elseif (preg_match('/^0[0-7]+$/', $normalized) === 1) {
            $base = 8;
        } elseif (preg_match('/^[0-9]+$/', $normalized) !== 1) {
            return $value;
        }

        return $sign * intval($digits, $base);
    }

    private function parseYamlExplicitFloatScalar(string $value): float|string
    {
        $normalized = str_replace('_', '', trim($value));
        if (preg_match('/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/', $normalized) !== 1) {
            return $value;
        }

        return (float) $normalized;
    }

    private function parseYamlExplicitBooleanScalar(string $value): bool|string
    {
        return match (strtolower(trim($value))) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }

    private function parseYamlExplicitTimestampScalar(string $value): string
    {
        $scalar = trim($value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $scalar, $m) === 1) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            if (!checkdate($month, $day, $year)) {
                return $value;
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        if (
            preg_match(
                '/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[Tt]|[ \t]+)(\d{1,2}):(\d{2}):(\d{2})(\.[0-9]+)?(?:[ \t]*(Z|z|[+-]\d{1,2}(?::?\d{2})?))?$/',
                $scalar,
                $m
            ) !== 1
        ) {
            return $value;
        }

        $year = (int) $m[1];
        $month = (int) $m[2];
        $day = (int) $m[3];
        $hour = (int) $m[4];
        $minute = (int) $m[5];
        $second = (int) $m[6];
        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            return $value;
        }

        $offset = $this->normalizeYamlTimestampOffset($m[8] ?? '');
        if ($offset === null) {
            return $value;
        }

        return sprintf('%04d-%02d-%02dT%02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second)
            . ($m[7] ?? '')
            . $offset;
    }

    private function normalizeYamlTimestampOffset(string $offset): ?string
    {
        $offset = trim($offset);
        if ($offset === '') {
            return '';
        }

        if (strcasecmp($offset, 'z') === 0) {
            return 'Z';
        }

        if (preg_match('/^([+-])(\d{1,2})(?::?(\d{2}))?$/', $offset, $m) !== 1) {
            return null;
        }

        $hour = (int) $m[2];
        $minute = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0;
        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return $m[1] . sprintf('%02d:%02d', $hour, $minute);
    }

    private function parseYamlExplicitBinaryScalar(string $value): string
    {
        $compact = preg_replace('/\s+/', '', $value) ?? $value;
        $decoded = base64_decode($compact, true);

        return $decoded === false ? $value : $decoded;
    }

    private function isYamlAliasScalar(string $value): bool
    {
        return preg_match('/^\*[A-Za-z0-9_.-]+$/', $value) === 1;
    }

    private function yamlAliasValue(string $anchorName): mixed
    {
        if (!array_key_exists($anchorName, $this->yamlMetadataAnchors)) {
            return '*' . $anchorName;
        }

        return $this->cloneYamlMetadataValue($this->yamlMetadataAnchors[$anchorName]);
    }

    private function rememberYamlAnchor(?string $anchorName, mixed $value): void
    {
        if ($anchorName === null || $anchorName === '') {
            return;
        }

        $this->yamlMetadataAnchors[$anchorName] = $this->cloneYamlMetadataValue($value);
    }

    private function cloneYamlMetadataValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $copy = [];
        foreach ($value as $key => $item) {
            $copy[$key] = $this->cloneYamlMetadataValue($item);
        }

        return $copy;
    }

    /**
     * @param array<string, mixed> $current
     * @return array<string, mixed>
     */
    private function mergeYamlMapValue(array $current, mixed $mergeValue): array
    {
        $merged = [];
        if ($this->isYamlAssociativeArray($mergeValue)) {
            $merged = $mergeValue;
        } elseif (is_array($mergeValue)) {
            foreach (array_reverse($mergeValue) as $item) {
                if ($this->isYamlAssociativeArray($item)) {
                    $merged = array_replace($merged, $item);
                }
            }
        }

        return array_replace($merged, $current);
    }

    private function isYamlAssociativeArray(mixed $value): bool
    {
        return is_array($value) && $value !== [] && !array_is_list($value);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function buildYamlMetadataAttrs(array $metadata): array
    {
        $meta = [];
        foreach ($metadata as $key => $value) {
            if (str_ends_with($key, '_')) {
                continue;
            }

            if ($key === 'title') {
                $lines = $this->metadataLinesFromYamlValue($value);
                if ($this->metadataPlainText($lines) !== '') {
                    $meta['title'] = $this->metadataPlainText($lines);
                    $meta['titleInlines'] = $this->metadataInlines($lines);
                }
                continue;
            }

            if ($key === 'author' || $key === 'authors') {
                $authors = $this->yamlMetadataAuthors($value);
                if ($authors !== []) {
                    $meta['author'] = $authors;
                    $meta['authors'] = $authors;
                    $meta['authorInlines'] = array_map(
                        fn (string $author): array => $this->parseInlines($author),
                        $authors
                    );
                    continue;
                }

                if (is_array($value) && $value !== []) {
                    $meta['author'] = $value;
                    $meta['authors'] = $value;
                }
                continue;
            }

            if ($key === 'date') {
                $lines = $this->metadataLinesFromYamlValue($value);
                if ($this->metadataPlainText($lines) !== '') {
                    $meta['date'] = $this->metadataPlainText($lines);
                    $meta['dateInlines'] = $this->metadataInlines($lines);
                }
                continue;
            }

            $meta[$key] = $value;
        }

        return $meta === [] ? [] : ['meta' => $meta];
    }

    /**
     * @return list<string>
     */
    private function metadataLinesFromYamlValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $value);
        }

        return is_scalar($value) ? explode("\n", (string) $value) : [];
    }

    /**
     * @return list<string>
     */
    private function yamlMetadataAuthors(mixed $value): array
    {
        if (is_array($value)) {
            $authors = [];
            foreach ($value as $item) {
                if (is_scalar($item) && trim((string) $item) !== '') {
                    $authors[] = trim((string) $item);
                }
            }

            return $authors;
        }

        return $this->metadataAuthors($this->metadataLinesFromYamlValue($value));
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>, 1:array{title:list<string>, author:list<string>, date:list<string>}|null}
     */
    private function extractTitleBlock(array $lines): array
    {
        if (($lines[0] ?? '') === '' || preg_match('/^%[ \t]?(.*)$/', $lines[0]) !== 1) {
            return [$lines, null];
        }

        $fields = [
            'title' => [],
            'author' => [],
            'date' => [],
        ];
        $fieldNames = array_keys($fields);
        $cursor = 0;
        $count = count($lines);

        foreach ($fieldNames as $fieldName) {
            if ($cursor >= $count || preg_match('/^%[ \t]?(.*)$/', $lines[$cursor], $m) !== 1) {
                break;
            }

            $fieldLines = [rtrim($m[1])];
            $cursor++;
            while ($cursor < $count && trim($lines[$cursor]) !== '' && preg_match('/^[ \t]+(.*)$/', $lines[$cursor], $continuation) === 1) {
                $fieldLines[] = rtrim($continuation[1]);
                $cursor++;
            }

            $fields[$fieldName] = $fieldLines;
        }

        while ($cursor < $count && trim($lines[$cursor]) === '') {
            $cursor++;
        }

        return [array_slice($lines, $cursor), $fields];
    }

    /**
     * @param array{title:list<string>, author:list<string>, date:list<string>} $titleBlock
     * @return array<string, mixed>
     */
    private function buildTitleBlockAttrs(array $titleBlock): array
    {
        $meta = [];
        if ($this->metadataPlainText($titleBlock['title']) !== '') {
            $meta['title'] = $this->metadataPlainText($titleBlock['title']);
            $meta['titleInlines'] = $this->metadataInlines($titleBlock['title']);
        }

        $authors = $this->metadataAuthors($titleBlock['author']);
        if ($authors !== []) {
            $meta['author'] = $authors;
            $meta['authors'] = $authors;
            $meta['authorInlines'] = array_map(
                fn (string $author): array => $this->parseInlines($author),
                $authors
            );
        }

        if ($this->metadataPlainText($titleBlock['date']) !== '') {
            $meta['date'] = $this->metadataPlainText($titleBlock['date']);
            $meta['dateInlines'] = $this->metadataInlines($titleBlock['date']);
        }

        return $meta === [] ? [] : ['meta' => $meta];
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>
     */
    private function metadataInlines(array $lines): array
    {
        return $this->parseInlines(implode("\n", $lines));
    }

    /**
     * @param list<string> $lines
     */
    private function metadataPlainText(array $lines): string
    {
        return trim(preg_replace('/[ \t]*\n[ \t]*/', ' ', implode("\n", $lines)) ?? '');
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function metadataAuthors(array $lines): array
    {
        $authors = [];
        foreach ($lines as $line) {
            foreach (explode(';', $line) as $author) {
                $author = trim($author);
                if ($author !== '') {
                    $authors[] = $author;
                }
            }
        }

        return $authors;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>, 1:array<string, array{url:string, title:string}>, 2:array<string, string>}
     */
    private function extractReferenceDefinitions(array $lines): array
    {
        $content = [];
        $references = [];
        $footnotes = [];
        $fenceChar = null;
        $fenceLength = 0;
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $line = $lines[$index];
            if ($fenceChar !== null) {
                $content[] = $line;
                if ($this->isClosingCodeFence($line, $fenceChar, $fenceLength)) {
                    $fenceChar = null;
                    $fenceLength = 0;
                }
                continue;
            }

            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $fence) === 1) {
                $fenceChar = $fence[1][0];
                $fenceLength = strlen($fence[1]);
                $content[] = $line;
                continue;
            }

            $expanded = $this->expandTabsToSpaces($line);
            $footnote = $this->tryParseFootnoteDefinitionStart($expanded);
            if ($footnote !== null) {
                [$body, $nextIndex] = $this->collectFootnoteDefinitionBody($lines, $index, $footnote['content']);
                $footnotes[$this->normalizeReferenceLabel($footnote['label'])] = implode("\n", $body);
                $index = $nextIndex - 1;
                continue;
            }

            $reference = $this->tryParseReferenceDefinitionStart($expanded);
            if ($reference !== null) {
                [$targetSource, $nextIndex] = $this->collectReferenceDefinitionTarget($lines, $index, $reference['content']);
                $target = $this->parseLinkDestinationAndTitle($targetSource);
                if ($target !== null) {
                    $references[$this->normalizeReferenceLabel($reference['label'])] = [
                        'url' => $target['url'],
                        'title' => $target['title'],
                    ];
                    $index = $nextIndex - 1;
                    continue;
                }
            }

            $content[] = $line;
        }

        return [$content, $references, $footnotes];
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function splitMixedHtmlFlowLines(array $lines): array
    {
        $normalized = [];
        $fenceChar = null;
        $fenceLength = 0;

        foreach ($lines as $line) {
            if ($fenceChar !== null) {
                $normalized[] = $line;
                if ($this->isClosingCodeFence($line, $fenceChar, $fenceLength)) {
                    $fenceChar = null;
                    $fenceLength = 0;
                }
                continue;
            }

            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $fence) === 1) {
                $fenceChar = $fence[1][0];
                $fenceLength = strlen($fence[1]);
                $normalized[] = $line;
                continue;
            }

            array_push($normalized, ...$this->splitMixedHtmlFlowLine($line));
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function splitMixedHtmlFlowLine(string $line): array
    {
        if ($this->countIndentColumns($line) > 3) {
            return [$line];
        }

        if (preg_match('/^[ \t]{0,3}(?:[-*+]|\d{1,9}[.)]|#[.)]|\([A-Za-z0-9]+\)|[A-Za-z]+[.)])[ \t]+/', $line) === 1) {
            return [$line];
        }

        if (preg_match('/^[ \t]*</', $line) === 1) {
            return [$line];
        }

        if (preg_match('/^([ \t]*[^<\r\n]*\S[^<\r\n]*)(<(?:p|blockquote|h[1-6]|ul|ol|dl|pre|table|div|hr)\b.*)$/i', $line, $m) !== 1) {
            return [$line];
        }

        $prefix = trim($m[1]);
        $suffix = $m[2];
        if ($prefix === '') {
            return [$line];
        }

        return ['<p>' . $prefix . '</p>', $suffix];
    }

    private function normalizeReferenceLabel(string $label): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $label) ?? $label));
    }

    /**
     * @param list<string> $lines
     * @return array{0:array<string, int>, 1:array<int, int>}
     */
    private function collectNumberedExampleReferences(array $lines): array
    {
        $references = [];
        $numbersByLine = [];
        $nextNumber = 1;
        $fenceChar = null;
        $fenceLength = 0;

        foreach ($lines as $index => $line) {
            if ($fenceChar !== null) {
                if ($this->isClosingCodeFence($line, $fenceChar, $fenceLength)) {
                    $fenceChar = null;
                    $fenceLength = 0;
                }
                continue;
            }

            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $fence) === 1) {
                $fenceChar = $fence[1][0];
                $fenceLength = strlen($fence[1]);
                continue;
            }

            $marker = $this->matchNumberedExampleMarker($line);
            if ($marker === null || $marker['indent'] > 3) {
                continue;
            }

            $numbersByLine[$index] = $nextNumber;
            if ($marker['label'] !== '') {
                $references[$marker['label']] = $nextNumber;
            }
            $nextNumber++;
        }

        return [$references, $numbersByLine];
    }

    /**
     * @param list<string> $lines
     * @return array{0:array<int, string>, 1:array<string, array{url:string, title:string}>}
     */
    private function collectMarkdownHeadingReferences(array $lines): array
    {
        $idsByLine = [];
        $references = [];
        $usedIds = [];

        for ($index = 0, $count = count($lines); $index < $count; $index++) {
            $line = $lines[$index];
            $heading = $this->tryParseMarkdownHeading($line);
            $setext = false;
            if ($heading === null) {
                $heading = $this->tryParseSetextMarkdownHeading($lines, $index);
                $setext = $heading !== null;
            }
            if ($heading === null) {
                continue;
            }

            $id = $heading['id'] ?? $this->uniqueMarkdownHeadingId(
                $this->slugifyMarkdownHeading($heading['text']),
                $usedIds
            );
            if (isset($heading['id'])) {
                $usedIds[$heading['id']] = ($usedIds[$heading['id']] ?? 0) + 1;
            }

            $idsByLine[$index] = $id;
            $label = $this->normalizeReferenceLabel($this->plainMarkdownHeadingText($heading['text']));
            if ($label !== '' && !isset($references[$label])) {
                $references[$label] = ['url' => '#' . $id, 'title' => ''];
            }

            if ($setext) {
                $index++;
            }
        }

        return [$idsByLine, $references];
    }

    /**
     * @return array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>}|null
     */
    private function tryParseMarkdownHeading(string $line): ?array
    {
        if (preg_match('/^ {0,3}(#{1,6})[ \t]+(.+)$/', $line, $m) !== 1) {
            return null;
        }

        $text = $this->stripClosingAtxHeadingFence(trim($m[2]));

        return $this->buildMarkdownHeading(strlen($m[1]), $text);
    }

    /**
     * @param list<string> $lines
     * @return array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>}|null
     */
    private function tryParseSetextMarkdownHeading(array $lines, int $index): ?array
    {
        if (!isset($lines[$index + 1])) {
            return null;
        }

        $line = $this->expandTabsToSpaces($lines[$index]);
        if ($this->countIndentColumns($line) > 3) {
            return null;
        }

        $text = trim($line);
        if ($text === '' || $this->tryParseMarkdownHeading($line) !== null) {
            return null;
        }

        $marker = $this->expandTabsToSpaces($lines[$index + 1]);
        if (preg_match('/^ {0,3}(=+|-+)[ \t]*$/', $marker, $m) !== 1) {
            return null;
        }

        return $this->buildMarkdownHeading($m[1][0] === '=' ? 1 : 2, $text);
    }

    private function stripClosingAtxHeadingFence(string $text): string
    {
        return rtrim(preg_replace('/[ \t]+#+[ \t]*$/', '', $text) ?? $text);
    }

    /**
     * @return array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>}
     */
    private function buildMarkdownHeading(int $level, string $text): array
    {
        $id = null;
        $classes = [];
        $attributes = [];

        if (preg_match('/^(.*?)[ \t]*\{([^{}]+)\}[ \t]*$/', $text, $attrs) === 1) {
            $text = rtrim($attrs[1]);
            [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec($attrs[2]);
        }

        $heading = [
            'level' => $level,
            'text' => $text,
            'classes' => $classes,
            'attributes' => $attributes,
        ];
        if ($id !== null && $id !== '') {
            $heading['id'] = $id;
        }

        return $heading;
    }

    /**
     * @return array{0:string|null, 1:list<string>, 2:array<string, string>}
     */
    private function parseMarkdownAttributeSpec(string $source): array
    {
        $id = null;
        $classes = [];
        $attributes = [];
        preg_match_all('/(?:^|\s)(#[^\s]+|\.[^\s]+|[A-Za-z_:][A-Za-z0-9_.:-]*=(?:"[^"]*"|\'[^\']*\'|[^\s]+))/', $source, $matches);

        foreach ($matches[1] as $token) {
            if ($token[0] === '#') {
                $id = substr($token, 1);
                continue;
            }
            if ($token[0] === '.') {
                $classes[] = substr($token, 1);
                continue;
            }

            [$name, $value] = explode('=', $token, 2);
            $value = trim($value);
            if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            $attributes[$name] = $this->decodeHtmlEntities($this->unescapeLinkComponent($value));
        }

        return [$id, $classes, $attributes];
    }

    /**
     * @param array<string, int> $usedIds
     */
    private function uniqueMarkdownHeadingId(string $base, array &$usedIds): string
    {
        $base = $base === '' ? 'section' : $base;
        $count = $usedIds[$base] ?? 0;
        $usedIds[$base] = $count + 1;

        return $count === 0 ? $base : $base . '-' . $count;
    }

    private function slugifyMarkdownHeading(string $text): string
    {
        $plain = $this->plainMarkdownHeadingText($text);
        $plain = function_exists('mb_strtolower') ? mb_strtolower($plain, 'UTF-8') : strtolower($plain);
        $plain = str_replace(["'", "\u{2019}"], '', $plain);
        $slug = preg_replace('/[^\pL\pN]+/u', '-', $plain) ?? $plain;

        return trim($slug, '-');
    }

    private function plainMarkdownHeadingText(string $text): string
    {
        $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/`+([^`]*)`+/', '$1', $text) ?? $text;
        $text = str_replace(['*', '_', '~', '^'], '', $text);

        return $this->decodeHtmlEntities(trim($text));
    }

    /**
     * @return array{label:string, url:string, title:string}|null
     */
    private function tryParseReferenceDefinition(string $line): ?array
    {
        $reference = $this->tryParseReferenceDefinitionStart($line);
        if ($reference === null) {
            return null;
        }

        $target = $this->parseLinkDestinationAndTitle($reference['content']);
        if ($target === null) {
            return null;
        }

        return [
            'label' => $reference['label'],
            'url' => $target['url'],
            'title' => $target['title'],
        ];
    }

    /**
     * @return array{label:string, content:string}|null
     */
    private function tryParseReferenceDefinitionStart(string $line): ?array
    {
        if (preg_match('/^ {0,3}\[(?!\^)([^\]\r\n]+)\]:[ \t]*(.*)$/', $line, $m) !== 1) {
            return null;
        }

        return [
            'label' => $m[1],
            'content' => rtrim($m[2]),
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{0:string, 1:int}
     */
    private function collectReferenceDefinitionTarget(array $lines, int $index, string $firstLine): array
    {
        $target = trim($firstLine);
        $cursor = $index + 1;
        $count = count($lines);

        if ($target === '') {
            if ($cursor < $count && trim($lines[$cursor]) !== '') {
                $target = trim($this->expandTabsToSpaces($lines[$cursor]));
                $cursor++;
            }
        }

        if ($cursor < $count) {
            $candidate = trim($this->expandTabsToSpaces($lines[$cursor]));
            if ($this->parseLinkTitle($candidate) !== null) {
                $target .= ' ' . $candidate;
                $cursor++;
            }
        }

        return [$target, $cursor];
    }

    /**
     * @return array{label:string, content:string}|null
     */
    private function tryParseFootnoteDefinitionStart(string $line): ?array
    {
        if (preg_match('/^ {0,3}\[\^([^\]\s]+)\]:[ \t]*(.*)$/', $line, $m) !== 1) {
            return null;
        }

        return [
            'label' => $m[1],
            'content' => rtrim($m[2]),
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>, 1:int}
     */
    private function collectFootnoteDefinitionBody(array $lines, int $index, string $firstLine): array
    {
        $body = [];
        if ($firstLine !== '') {
            $body[] = $firstLine;
        }

        $cursor = $index + 1;
        $count = count($lines);
        $afterBlank = false;
        $insideIndentedBlock = false;

        while ($cursor < $count) {
            $line = $lines[$cursor];
            $expanded = $this->expandTabsToSpaces($line);
            if (trim($expanded) === '') {
                $nextNonBlank = $this->nextNonBlankLineIndex($lines, $cursor + 1);
                if (
                    $nextNonBlank !== null
                    && $this->isFootnoteIndentedContinuation($lines[$nextNonBlank])
                ) {
                    $body[] = '';
                    $afterBlank = true;
                    $insideIndentedBlock = false;
                    $cursor++;
                    continue;
                }

                $cursor = $nextNonBlank ?? $count;
                break;
            }

            if ($this->tryParseFootnoteDefinitionStart($expanded) !== null || $this->tryParseReferenceDefinition($expanded) !== null) {
                break;
            }

            if ($afterBlank && !$insideIndentedBlock) {
                if (!$this->isFootnoteIndentedContinuation($line)) {
                    break;
                }

                $body[] = rtrim($this->stripIndentColumns($line, 4));
                $insideIndentedBlock = true;
                $cursor++;
                continue;
            }

            if ($afterBlank && $this->isFootnoteIndentedContinuation($line)) {
                $body[] = rtrim($this->stripIndentColumns($line, 4));
                $cursor++;
                continue;
            }

            $body[] = rtrim($line);
            $cursor++;
        }

        while ($body !== [] && end($body) === '') {
            array_pop($body);
        }

        return [$body, $cursor];
    }

    /**
     * @param list<string> $lines
     */
    private function nextNonBlankLineIndex(array $lines, int $cursor): ?int
    {
        $count = count($lines);
        while ($cursor < $count) {
            if (trim($lines[$cursor]) !== '') {
                return $cursor;
            }
            $cursor++;
        }

        return null;
    }

    private function isFootnoteIndentedContinuation(string $line): bool
    {
        return $this->countIndentColumns($line) >= 4;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadFencedCodeBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^( {0,3})(`{3,}|~{3,})[ \t]*(.*)$/', $line, $m) !== 1) {
            return null;
        }

        $indent = strlen($m[1]);
        $fence = $m[2];
        $fenceChar = $fence[0];
        $fenceLength = strlen($fence);
        $info = trim($m[3]);
        if ($fenceChar === '`' && str_contains($info, '`')) {
            return null;
        }

        $content = [];
        $cursor = $index + 1;
        $count = count($lines);
        while ($cursor < $count) {
            if ($this->isClosingCodeFence($lines[$cursor], $fenceChar, $fenceLength)) {
                $attrs = $this->parseCodeInfo($info);
                $attrs['text'] = implode("\n", $content);
                if ($info !== '') {
                    $attrs['info'] = $info;
                }
                $index = $cursor;

                return new AstNode('code_block', $attrs);
            }

            $content[] = $this->stripFenceContentIndent($lines[$cursor], $indent);
            $cursor++;
        }

        $attrs = $this->parseCodeInfo($info);
        $attrs['text'] = implode("\n", $content);
        if ($info !== '') {
            $attrs['info'] = $info;
        }
        $index = $cursor - 1;

        return new AstNode('code_block', $attrs);
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadLiterateHaskellCodeBlock(array $lines, int &$index): ?AstNode
    {
        if (($this->options['literateHaskell'] ?? false) !== true) {
            return null;
        }

        $first = $this->tryParseLiterateHaskellCodeLine($lines[$index] ?? '');
        if ($first === null) {
            return null;
        }

        $marker = $first['marker'];
        $content = [$first['text']];
        $cursor = $index + 1;
        $count = count($lines);
        while ($cursor < $count) {
            $next = $this->tryParseLiterateHaskellCodeLine($lines[$cursor]);
            if ($next === null || $next['marker'] !== $marker) {
                break;
            }

            $content[] = $next['text'];
            $cursor++;
        }

        $index = $cursor - 1;

        return new AstNode('code_block', [
            'classes' => $marker === '>' ? ['haskell', 'literate'] : ['haskell'],
            'attributes' => [],
            'text' => implode("\n", $content),
        ]);
    }

    /**
     * @return array{marker: string, text: string}|null
     */
    private function tryParseLiterateHaskellCodeLine(string $line): ?array
    {
        $expanded = $this->expandTabsToSpaces($line);
        if (preg_match('/^([<>])(?: (.*)|)$/', $expanded, $m) !== 1) {
            return null;
        }

        return [
            'marker' => $m[1],
            'text' => $m[2] ?? '',
        ];
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadBlockQuote(array $lines, int &$index): ?AstNode
    {
        if (!$this->isBlockQuoteLine($lines[$index] ?? '')) {
            return null;
        }

        $content = [];
        $cursor = $index;
        $count = count($lines);
        while ($cursor < $count && $this->isBlockQuoteLine($lines[$cursor])) {
            $content[] = $this->stripBlockQuoteMarker($lines[$cursor]);
            $cursor++;
        }

        $index = $cursor - 1;
        $inner = $this->read(implode("\n", $content));

        return new AstNode('blockquote', [], $inner->children);
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadLineBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}\|(.*)$/', $this->expandTabsToSpaces($line), $m) !== 1) {
            return null;
        }

        $lineNodes = [];
        $currentText = null;
        $cursor = $index;
        $count = count($lines);

        while ($cursor < $count) {
            $expanded = $this->expandTabsToSpaces($lines[$cursor]);
            if (preg_match('/^ {0,3}\|(.*)$/', $expanded, $marker) === 1) {
                if ($currentText !== null) {
                    $lineNodes[] = $this->buildLineBlockLine($currentText);
                }
                $currentText = $this->normalizeLineBlockMarkerContent($marker[1]);
                $cursor++;
                continue;
            }

            if ($currentText !== null && trim($expanded) !== '' && preg_match('/^ +\S/', $expanded) === 1) {
                $currentText .= ' ' . trim($expanded);
                $cursor++;
                continue;
            }

            break;
        }

        if ($currentText !== null) {
            $lineNodes[] = $this->buildLineBlockLine($currentText);
        }

        if ($lineNodes === []) {
            return null;
        }

        $index = $cursor - 1;

        return new AstNode('line_block', [], $lineNodes);
    }

    private function normalizeLineBlockMarkerContent(string $content): string
    {
        if (($content[0] ?? '') === ' ') {
            $content = substr($content, 1);
        }

        $leading = strspn($content, ' ');
        if ($leading === 0) {
            return rtrim($content);
        }

        return str_repeat("\xC2\xA0", $leading) . rtrim(substr($content, $leading));
    }

    private function buildLineBlockLine(string $text): AstNode
    {
        return new AstNode('line', ['text' => $text], $text === '' ? [] : $this->parseInlines($text));
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadDivBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<div(?:\s+[^>]*)?>/i', $line, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $content = [];
        $depth = 1;
        $openingIndex = $index;
        $cursor = $index;
        $count = count($lines);
        $firstLineOffset = $m[0][1] + strlen($m[0][0]);

        while ($cursor < $count) {
            $segment = $cursor === $index ? substr($lines[$cursor], $firstLineOffset) : $lines[$cursor];
            $lineContent = '';
            $offset = 0;
            while (true) {
                $nextOpen = $this->findHtmlTag($segment, 'div', $offset, false);
                $nextClose = $this->findHtmlTag($segment, 'div', $offset, true);

                if ($nextOpen === null && $nextClose === null) {
                    $lineContent .= substr($segment, $offset);
                    break;
                }

                if ($nextOpen !== null && ($nextClose === null || $nextOpen['offset'] < $nextClose['offset'])) {
                    $depth++;
                    $lineContent .= substr($segment, $offset, $nextOpen['offset'] + $nextOpen['length'] - $offset);
                    $offset = $nextOpen['offset'] + $nextOpen['length'];
                    continue;
                }

                if ($nextClose === null) {
                    break;
                }

                $depth--;
                if ($depth === 0) {
                    $lineContent .= substr($segment, $offset, $nextClose['offset'] - $offset);
                    $content[] = $lineContent;
                    $closedOnOpeningLine = $cursor === $openingIndex;
                    $index = $cursor;

                    return $this->buildDivBlock($content, $closedOnOpeningLine);
                }

                $lineContent .= substr($segment, $offset, $nextClose['offset'] + $nextClose['length'] - $offset);
                $offset = $nextClose['offset'] + $nextClose['length'];
            }

            $content[] = $lineContent;
            $cursor++;
        }

        return null;
    }

    /**
     * @return array{offset:int, length:int}|null
     */
    private function findHtmlTag(string $line, string $tag, int $offset, bool $closing): ?array
    {
        $pattern = $closing
            ? '/<\/' . preg_quote($tag, '/') . '\s*>/i'
            : '/<' . preg_quote($tag, '/') . '(?:\s+[^>]*)?>/i';

        if (preg_match($pattern, $line, $m, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            return null;
        }

        return ['offset' => $m[0][1], 'length' => strlen($m[0][0])];
    }

    /**
     * @param list<string> $content
     */
    private function buildDivBlock(array $content, bool $closedOnOpeningLine): AstNode
    {
        while ($content !== [] && trim($content[0]) === '') {
            array_shift($content);
        }
        while ($content !== [] && trim($content[array_key_last($content)]) === '') {
            array_pop($content);
        }

        if (
            $closedOnOpeningLine
            && count($content) === 1
            && trim($content[0]) !== ''
            && stripos($content[0], '<div') === false
        ) {
            $text = trim($content[0]);

            return new AstNode('div', [], [
                new AstNode('plain', ['text' => $text], $this->parseInlines($text)),
            ]);
        }

        $inner = $this->read(implode("\n", $content));

        return new AstNode('div', [], $inner->children);
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadRawHtmlBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<!--/', $line) === 1) {
            return $this->readHtmlCommentBlock($lines, $index);
        }

        if (preg_match('/^ {0,3}<(script|style)(?:\s+[^>]*)?>/i', $line, $m) === 1) {
            return $this->readRawHtmlUntilClosingTag($lines, $index, strtolower($m[1]));
        }

        if (preg_match('/^ {0,3}<table(?:\s+[^>]*)?>/i', $line) === 1) {
            return $this->readRawHtmlUntilClosingTag($lines, $index, 'table', true);
        }

        if (preg_match('/^ {0,3}<hr(?:\s+[^>]*)?\/?>[ \t]*$/i', $line) === 1) {
            return new AstNode('raw_html', ['html' => trim($line)]);
        }

        return null;
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>|null
     */
    private function tryReadRawHtmlSingleLineContainerBlock(array $lines, int &$index): ?array
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}(<(del|button)(?:\s+[^>]*)?>)(.*)(<\/\2\s*>)[ \t]*$/iu', $line, $m) !== 1) {
            return null;
        }

        $blocks = [
            new AstNode('raw_html', ['html' => $m[1]]),
        ];

        if ($m[3] !== '') {
            $inlines = $this->parseInlines($m[3]);
            $blocks[] = new AstNode('plain', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
        }

        $blocks[] = new AstNode('raw_html', ['html' => $m[4]]);

        return $blocks;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadLatexTableBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}\\\\begin\{table\}(?:\[[^\]\r\n]*\])?[ \t]*$/', $line) !== 1) {
            return null;
        }

        $content = [];
        $cursor = $index + 1;
        $count = count($lines);
        while ($cursor < $count) {
            if (preg_match('/^ {0,3}\\\\end\{table\}[ \t]*$/', $lines[$cursor]) === 1) {
                $table = $this->parseLatexTableEnvironment($content);
                if ($table === null) {
                    return null;
                }

                $index = $cursor;

                return $table;
            }

            $content[] = rtrim($lines[$cursor]);
            $cursor++;
        }

        return null;
    }

    /**
     * @param list<string> $content
     */
    private function parseLatexTableEnvironment(array $content): ?AstNode
    {
        $caption = '';
        $shortCaption = '';
        $tabularStart = null;
        $alignments = [];

        foreach ($content as $lineIndex => $line) {
            if ($caption === '' && preg_match('/\\\\caption(?:\[((?:\\\\.|[^\]\\\\])*)\])?\{((?:\\\\.|[^{}\\\\])*)\}/', $line, $captionMatch) === 1) {
                $shortCaption = isset($captionMatch[1]) ? $this->normalizeLatexText($captionMatch[1]) : '';
                $caption = $this->normalizeLatexText($captionMatch[2]);
                continue;
            }

            if (preg_match('/\\\\begin\{tabular\}\{([^}]*)\}/', $line, $tabularMatch) === 1) {
                $tabularStart = $lineIndex + 1;
                $alignments = $this->parseLatexTabularAlignments($tabularMatch[1]);
                break;
            }
        }

        if ($tabularStart === null) {
            return null;
        }

        $rows = [];
        for ($cursor = $tabularStart, $count = count($content); $cursor < $count; $cursor++) {
            $line = trim($content[$cursor]);
            if (preg_match('/\\\\end\{tabular\}/', $line) === 1) {
                break;
            }

            foreach ($this->parseLatexTabularRows($line) as $row) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return null;
        }

        $columnCount = max(count($alignments), count($rows[0]));
        while (count($alignments) < $columnCount) {
            $alignments[] = 'default';
        }

        $bodyRows = [];
        foreach ($rows as $row) {
            $bodyRows[] = $this->buildTableRow($this->normalizePipeTableRow($row, $columnCount), false);
        }

        $attrs = [
            'caption' => $caption,
            'alignments' => $alignments,
        ];
        if ($caption !== '') {
            $attrs['captionInlines'] = $this->parseInlines($caption);
        }
        if ($shortCaption !== '') {
            $attrs['shortCaption'] = $shortCaption;
            $attrs['shortCaptionInlines'] = $this->parseInlines($shortCaption);
        }

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, [
            new AstNode('table_head'),
            new AstNode('table_body', [], $bodyRows),
        ]));
    }

    /**
     * @return list<string>
     */
    private function parseLatexTabularAlignments(string $spec): array
    {
        $alignments = [];
        $length = strlen($spec);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $spec[$offset];
            if ($char === 'l') {
                $alignments[] = 'left';
                continue;
            }
            if ($char === 'r') {
                $alignments[] = 'right';
                continue;
            }
            if ($char === 'c') {
                $alignments[] = 'center';
                continue;
            }
        }

        return $alignments;
    }

    /**
     * @return list<list<string>>
     */
    private function parseLatexTabularRows(string $line): array
    {
        $line = trim(str_replace('\hline', '', $line));
        if ($line === '') {
            return [];
        }

        $rows = [];
        foreach (preg_split('/\\\\\\\\/', $line) ?: [] as $rowText) {
            $rowText = trim($rowText);
            if ($rowText === '') {
                continue;
            }

            $rows[] = array_map(
                fn (string $cell): string => $this->normalizeLatexText($cell),
                $this->splitLatexTabularCells($rowText)
            );
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function splitLatexTabularCells(string $row): array
    {
        $cells = [];
        $cell = '';
        $length = strlen($row);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($row[$offset] === '\\') {
                $cell .= $row[$offset];
                if ($offset + 1 < $length) {
                    $offset++;
                    $cell .= $row[$offset];
                }
                continue;
            }

            if ($row[$offset] === '&') {
                $cells[] = $cell;
                $cell = '';
                continue;
            }

            $cell .= $row[$offset];
        }
        $cells[] = $cell;

        return $cells;
    }

    private function normalizeLatexText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\\\\([#$%&_{}])/', '$1', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadDocBookTableBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<informaltable(?:\s+[^>]*)?>/i', $line) !== 1) {
            return null;
        }

        $content = [];
        $cursor = $index;
        $count = count($lines);
        while ($cursor < $count) {
            $content[] = trim($lines[$cursor]);
            if (preg_match('/<\/informaltable\s*>/i', $lines[$cursor]) === 1) {
                $table = $this->parseDocBookInformalTable(implode("\n", $content));
                if ($table === null) {
                    return null;
                }

                $index = $cursor;

                return $table;
            }
            $cursor++;
        }

        return null;
    }

    private function parseDocBookInformalTable(string $xml): ?AstNode
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$dom->documentElement instanceof \DOMElement) {
            return null;
        }

        $root = $dom->documentElement;
        if (strtolower($root->localName) !== 'informaltable') {
            return null;
        }

        $tgroup = $this->firstDescendantElement($root, 'tgroup');
        $sectionRoot = $tgroup instanceof \DOMElement ? $tgroup : $root;
        $thead = $this->firstChildElement($sectionRoot, 'thead');
        $tbody = $this->firstChildElement($sectionRoot, 'tbody');
        $tfoot = $this->firstChildElement($sectionRoot, 'tfoot');
        if (!$thead instanceof \DOMElement && !$tbody instanceof \DOMElement && !$tfoot instanceof \DOMElement) {
            return null;
        }

        [$columnNames, $widths] = $tgroup instanceof \DOMElement
            ? $this->readDocBookColumnSpecs($tgroup)
            : [[], null];

        $maxColumns = count($columnNames);
        $headRows = $thead instanceof \DOMElement
            ? $this->readDocBookTableRows($thead, $columnNames, true, $maxColumns)
            : [];
        $bodyRows = $tbody instanceof \DOMElement
            ? $this->readDocBookTableRows($tbody, $columnNames, false, $maxColumns)
            : [];
        $footRows = $tfoot instanceof \DOMElement
            ? $this->readDocBookTableRows($tfoot, $columnNames, false, $maxColumns)
            : [];

        if ($headRows === [] && $bodyRows === [] && $footRows === []) {
            return null;
        }

        if ($maxColumns === 0) {
            foreach ([...$headRows, ...$bodyRows, ...$footRows] as $row) {
                $maxColumns = max($maxColumns, count($row->children));
            }
        }

        $attrs = [
            'caption' => '',
            'alignments' => array_fill(0, $maxColumns, 'default'),
        ];
        if ($widths !== null) {
            $attrs['widths'] = $widths;
        }

        $children = [
            new AstNode('table_head', [], $headRows),
            new AstNode('table_body', [], $bodyRows),
        ];
        if ($footRows !== []) {
            $children[] = new AstNode('table_foot', [], $footRows);
        }

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $children));
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadStructuredHtmlDocumentTableBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}(?:<!doctype\s+html\b|<html\b)/i', $line) !== 1) {
            return null;
        }

        $content = [];
        $count = count($lines);
        for ($cursor = $index; $cursor < $count; $cursor++) {
            $content[] = $this->normalizeRawHtmlLine($lines[$cursor]);
            if (preg_match('/<\/html\s*>/i', $lines[$cursor]) === 1) {
                $table = $this->parseStructuredHtmlTable(implode("\n", $content));
                if ($table === null) {
                    return null;
                }

                $index = $cursor;

                return $table;
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlDocumentBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}(?:<!doctype\s+html\b|<html\b)/i', $line) !== 1) {
            return null;
        }

        $content = [];
        $count = count($lines);
        for ($cursor = $index; $cursor < $count; $cursor++) {
            $content[] = $this->normalizeRawHtmlLine($lines[$cursor]);
            if (preg_match('/<\/html\s*>/i', $lines[$cursor]) === 1) {
                $document = $this->parseHtmlDocument(implode("\n", $content));
                if ($document === null) {
                    return null;
                }

                $index = $cursor;

                return $document;
            }
        }

        return null;
    }

    private function parseHtmlDocument(string $html): ?AstNode
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        return new AstNode('document', $this->htmlDocumentAttrs($dom), $this->parseHtmlBlockChildren($body));
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlDocumentAttrs(\DOMDocument $dom): array
    {
        $meta = [];
        $titles = $dom->getElementsByTagName('title');
        $title = $titles->item(0);
        if ($title instanceof \DOMElement) {
            $text = trim(preg_replace('/\s+/', ' ', $title->textContent) ?? $title->textContent);
            if ($text !== '') {
                $meta['title'] = $text;
            }
        }

        foreach ($dom->getElementsByTagName('meta') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $name = strtolower(trim($node->getAttribute('name')));
            if ($name === '') {
                continue;
            }

            $content = trim($node->getAttribute('content'));
            if ($content !== '') {
                $meta[$name] = $content;
            }
        }

        return $meta === [] ? [] : ['meta' => $meta];
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadStructuredHtmlTableBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<table(?:\s+[^>]*)?>/i', $line) !== 1) {
            return null;
        }

        $balanced = $this->collectBalancedHtmlTableBlock($lines, $index);
        if ($balanced === null) {
            return null;
        }

        [$html, $endIndex] = $balanced;
        $table = $this->parseStructuredHtmlTable($html);
        if ($table === null) {
            return null;
        }

        $index = $endIndex;

        return $table;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadEmptyHtmlTableBlock(array $lines, int &$index): bool
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<table(?:\s+[^>]*)?>/i', $line) !== 1) {
            return false;
        }

        $balanced = $this->collectBalancedHtmlTableBlock($lines, $index);
        if ($balanced === null) {
            return false;
        }

        [$html, $endIndex] = $balanced;
        if (!$this->htmlTableBlockIsEmpty($html)) {
            return false;
        }

        $index = $endIndex;

        return true;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlParagraphBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<p(?:\s+[^>]*)?>/i', $line) !== 1) {
            return null;
        }

        $collected = $this->collectHtmlParagraphBlock($lines, $index);
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $paragraph = $this->parseHtmlParagraphElement($html);
        if ($paragraph === null) {
            return null;
        }

        $index = $endIndex;

        return $paragraph;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlInlineFragmentBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<br\b[^>]*>/i', $line) !== 1) {
            return null;
        }

        return $this->parseHtmlInlineFragmentParagraph(trim($line));
    }

    private function parseHtmlInlineFragmentParagraph(string $html): ?AstNode
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $blocks = $this->parseHtmlBlockChildren($body);
        if (count($blocks) !== 1 || $blocks[0]->type !== 'paragraph') {
            return null;
        }

        return $blocks[0];
    }

    /**
     * @param list<string> $lines
     * @return array{0:string, 1:int}|null
     */
    private function collectHtmlParagraphBlock(array $lines, int $index): ?array
    {
        $content = [];
        $count = count($lines);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $line = $this->normalizeRawHtmlLine($lines[$cursor]);
            if ($cursor > $index && $this->htmlLineStartsImplicitParagraphClose($line)) {
                return [implode("\n", $content), $cursor - 1];
            }

            $content[] = $line;
            if (preg_match('/<\/p\s*>/i', $line) === 1) {
                return [implode("\n", $content), $cursor];
            }
        }

        if ($content === []) {
            return null;
        }

        return [implode("\n", $content), $count - 1];
    }

    private function htmlLineStartsImplicitParagraphClose(string $line): bool
    {
        return preg_match(
            '/^ {0,3}<(?:p|h[1-6]|ul|ol|dl|blockquote|pre|table|div|hr)\b/i',
            $line
        ) === 1;
    }

    /**
     * @param list<string> $lines
     * @return array{0:string, 1:int}|null
     */
    private function collectHtmlBlockUntilClosingTag(array $lines, int $index, string $tag): ?array
    {
        $content = [];
        $count = count($lines);
        $closingPattern = '/<\/' . preg_quote($tag, '/') . '\s*>/i';

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $line = $this->normalizeRawHtmlLine($lines[$cursor]);
            $content[] = $line;
            if (preg_match($closingPattern, $line) === 1) {
                return [implode("\n", $content), $cursor];
            }
        }

        return null;
    }

    private function parseHtmlParagraphElement(string $html): ?AstNode
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $paragraph = $this->firstChildElement($body, 'p');
        if (!$paragraph instanceof \DOMElement) {
            return null;
        }

        return $this->buildHtmlParagraphNode($paragraph);
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlHorizontalRuleBlock(array $lines, int &$index): ?AstNode
    {
        $line = $this->normalizeRawHtmlLine($lines[$index] ?? '');
        if (preg_match('/^ {0,3}<hr\s*\/>[ \t]*$/i', $line) !== 1) {
            return null;
        }

        return new AstNode('horizontal_rule');
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlBlockQuoteBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<blockquote(?:\s+[^>]*)?>/i', $line) !== 1) {
            return null;
        }

        $collected = $this->collectBalancedHtmlElementBlock($lines, $index, 'blockquote');
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $quote = $this->parseHtmlBlockQuoteBlock($html);
        if ($quote === null) {
            return null;
        }

        $index = $endIndex;

        return $quote;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlHeadingBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<h([1-6])\b/i', $line, $m) !== 1) {
            return null;
        }

        $level = (int) $m[1];
        $collected = $this->collectHtmlBlockUntilClosingTag($lines, $index, 'h' . $level);
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $heading = $this->parseHtmlHeadingElement($html, $level);
        if ($heading === null) {
            return null;
        }

        $index = $endIndex;

        return $heading;
    }

    private function parseHtmlHeadingElement(string $html, int $level): ?AstNode
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $heading = $this->firstChildElement($body, 'h' . $level);
        if (!$heading instanceof \DOMElement) {
            return null;
        }

        return $this->buildHtmlHeadingNode($heading, $level);
    }

    private function buildHtmlHeadingNode(\DOMElement $heading, int $level): AstNode
    {
        $children = $this->parseHtmlInlineChildren($heading);
        $text = trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($children)) ?? '');
        $attrs = array_merge($this->htmlElementPandocAttrs($heading), [
            'level' => $level,
            'text' => $text,
        ]);
        if (!isset($attrs['id'])) {
            $identifier = $this->htmlHeadingIdentifier($text);
            if ($identifier !== '') {
                $attrs['id'] = $identifier;
            }
        }

        return new AstNode('heading', $attrs, $children);
    }

    private function htmlHeadingIdentifier(string $text): string
    {
        $identifier = strtolower($text);
        $identifier = preg_replace('/[^a-z0-9]+/', '-', $identifier) ?? '';

        return trim($identifier, '-');
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlListBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<(ol|ul)\b/i', $line, $m) !== 1) {
            return null;
        }

        $tag = strtolower($m[1]);
        $collected = $this->collectBalancedHtmlElementBlock($lines, $index, $tag);
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $list = $this->parseHtmlListBlock($html, $tag);
        if ($list === null) {
            return null;
        }

        $index = $endIndex;

        return $list;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlDefinitionListBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<dl\b/i', $line) !== 1) {
            return null;
        }

        $collected = $this->collectBalancedHtmlElementBlock($lines, $index, 'dl');
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $list = $this->parseHtmlDefinitionListBlock($html);
        if ($list === null) {
            return null;
        }

        $index = $endIndex;

        return $list;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlPreCodeBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<pre(?:\s+[^>]*)?>/i', $line) !== 1) {
            return null;
        }

        $collected = $this->collectHtmlBlockUntilClosingTag($lines, $index, 'pre');
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $codeBlock = $this->parseHtmlPreCodeBlock($html);
        if ($codeBlock === null) {
            return null;
        }

        $index = $endIndex;

        return $codeBlock;
    }

    private function parseHtmlPreCodeBlock(string $html): ?AstNode
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $pre = $this->firstChildElement($body, 'pre');
        if (!$pre instanceof \DOMElement) {
            return null;
        }

        return $this->buildHtmlPreCodeBlock($pre);
    }

    private function buildHtmlPreCodeBlock(\DOMElement $pre): ?AstNode
    {
        $code = $this->firstChildElement($pre, 'code');
        if (!$code instanceof \DOMElement) {
            return null;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $code->textContent);
        if (str_ends_with($text, "\n")) {
            $text = substr($text, 0, -1);
        }

        return new AstNode('code_block', [
            'classes' => $this->htmlCodeBlockClasses($code),
            'attributes' => [],
            'text' => $text,
        ]);
    }

    private function parseHtmlBlockQuoteBlock(string $html): ?AstNode
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $quote = $this->firstChildElement($body, 'blockquote');
        if (!$quote instanceof \DOMElement) {
            return null;
        }

        return $this->buildHtmlBlockQuoteNode($quote);
    }

    private function parseHtmlListBlock(string $html, string $tag): ?AstNode
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $list = $this->firstChildElement($body, $tag);
        if (!$list instanceof \DOMElement) {
            return null;
        }

        return $this->parseHtmlListElement($list);
    }

    private function parseHtmlDefinitionListBlock(string $html): ?AstNode
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $list = $this->firstChildElement($body, 'dl');
        if (!$list instanceof \DOMElement) {
            return null;
        }

        return $this->parseHtmlDefinitionListElement($list);
    }

    private function buildHtmlBlockQuoteNode(\DOMElement $quote): AstNode
    {
        return new AstNode('blockquote', $this->htmlElementPandocAttrs($quote), $this->parseHtmlBlockChildren($quote));
    }

    /**
     * @return list<AstNode>
     */
    private function parseHtmlBlockChildren(\DOMElement $element): array
    {
        $blocks = [];
        $inlines = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isHtmlBlockElement($child)) {
                $this->flushHtmlInlineParagraph($inlines, $blocks);
                $block = $this->parseHtmlBlockElement($child);
                if ($block instanceof AstNode) {
                    $blocks[] = $block;
                }
                continue;
            }

            $this->appendHtmlInlineNodes($inlines, $this->parseHtmlInlineNode($child));
        }

        $this->flushHtmlInlineParagraph($inlines, $blocks);

        return $blocks;
    }

    private function isHtmlBlockElement(\DOMElement $element): bool
    {
        return in_array(strtolower($element->localName), [
            'blockquote',
            'div',
            'dl',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'hr',
            'ol',
            'p',
            'pre',
            'table',
            'ul',
        ], true);
    }

    private function parseHtmlBlockElement(\DOMElement $element): ?AstNode
    {
        $name = strtolower($element->localName);
        if ($name === 'p') {
            return $this->buildHtmlParagraphNode($element);
        }
        if ($name === 'pre') {
            return $this->buildHtmlPreCodeBlock($element);
        }
        if ($name === 'blockquote') {
            return $this->buildHtmlBlockQuoteNode($element);
        }
        if ($name === 'ol' || $name === 'ul') {
            return $this->parseHtmlListElement($element);
        }
        if ($name === 'dl') {
            return $this->parseHtmlDefinitionListElement($element);
        }
        if ($name === 'table') {
            return $this->parseHtmlTableElement($element);
        }
        if ($name === 'div') {
            return new AstNode('div', $this->htmlElementPandocAttrs($element), $this->parseHtmlBlockChildren($element));
        }
        if ($name === 'hr') {
            return new AstNode('horizontal_rule');
        }
        if (preg_match('/^h([1-6])$/', $name, $m) === 1) {
            return $this->buildHtmlHeadingNode($element, (int) $m[1]);
        }

        $children = $this->parseHtmlInlineChildren($element);
        if ($children === []) {
            return null;
        }

        return new AstNode(
            'paragraph',
            ['text' => $this->plainTextFromInlines($children)],
            $children
        );
    }

    private function buildHtmlParagraphNode(\DOMElement $paragraph): AstNode
    {
        $children = $this->parseHtmlInlineChildren($paragraph);

        return new AstNode(
            'paragraph',
            ['text' => $this->plainTextFromInlines($children)],
            $children
        );
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $blocks
     */
    private function flushHtmlInlineParagraph(array &$inlines, array &$blocks): void
    {
        $text = $this->plainTextFromInlines($inlines);
        $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($normalized !== '' || $this->htmlInlineParagraphHasLineBreak($inlines)) {
            $blocks[] = new AstNode('paragraph', ['text' => $normalized], $inlines);
        }

        $inlines = [];
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function htmlInlineParagraphHasLineBreak(array $inlines): bool
    {
        foreach ($inlines as $inline) {
            if ($inline->type === 'linebreak') {
                return true;
            }
        }

        return false;
    }

    private function parseHtmlListElement(\DOMElement $list): AstNode
    {
        $ordered = strtolower($list->localName) === 'ol';
        $items = [];
        $loose = false;
        foreach ($this->childElements($list, 'li') as $itemElement) {
            $children = $this->parseHtmlListItemChildren($itemElement);
            $itemLoose = $this->htmlListItemIsLoose($children);
            $loose = $loose || $itemLoose;
            $items[] = new AstNode(
                'list_item',
                [
                    'text' => trim(preg_replace('/\s+/', ' ', $itemElement->textContent) ?? $itemElement->textContent),
                    'loose' => $itemLoose,
                ],
                $children
            );
        }

        $attrs = ['loose' => $loose];
        if ($ordered) {
            $attrs = array_merge($attrs, $this->htmlOrderedListAttrs($list));
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    private function parseHtmlDefinitionListElement(\DOMElement $list): AstNode
    {
        $items = [];
        $termInlines = [];
        $termTexts = [];
        $definitions = [];

        foreach ($list->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->localName);
            if ($name === 'dt') {
                if ($termInlines !== [] && $definitions !== []) {
                    $this->flushHtmlDefinitionItem($items, $termInlines, $termTexts, $definitions);
                } elseif ($termInlines !== []) {
                    $termInlines[] = new AstNode('linebreak');
                }

                $inlines = $this->parseHtmlInlineChildren($child);
                array_push($termInlines, ...$inlines);
                $termTexts[] = trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($inlines)) ?? '');
                continue;
            }

            if ($name === 'dd' && $termInlines !== []) {
                $definitions[] = $this->buildHtmlDefinitionNode($child);
            }
        }

        $this->flushHtmlDefinitionItem($items, $termInlines, $termTexts, $definitions);

        return new AstNode('definition_list', $this->htmlElementPandocAttrs($list), $items);
    }

    /**
     * @param list<AstNode> $items
     * @param list<AstNode> $termInlines
     * @param list<string> $termTexts
     * @param list<AstNode> $definitions
     */
    private function flushHtmlDefinitionItem(
        array &$items,
        array &$termInlines,
        array &$termTexts,
        array &$definitions
    ): void {
        if ($termInlines === []) {
            $termTexts = [];
            $definitions = [];
            return;
        }

        $termText = implode("\n", $termTexts);
        $term = new AstNode('term', ['text' => $termText], $termInlines);
        $items[] = new AstNode('definition_item', ['term' => $termText], array_merge([$term], $definitions));
        $termInlines = [];
        $termTexts = [];
        $definitions = [];
    }

    private function buildHtmlDefinitionNode(\DOMElement $definition): AstNode
    {
        return new AstNode('definition', ['loose' => false], $this->parseHtmlBlockChildren($definition));
    }

    /**
     * @return list<AstNode>
     */
    private function parseHtmlListItemChildren(\DOMElement $item): array
    {
        $children = [];
        $inlines = [];
        foreach ($item->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isHtmlBlockElement($child)) {
                $this->flushHtmlListItemInlines($inlines, $children);
                $block = $this->parseHtmlBlockElement($child);
                if ($block instanceof AstNode) {
                    $children[] = $block;
                }
                continue;
            }

            $this->appendHtmlInlineNodes($inlines, $this->parseHtmlInlineNode($child));
        }

        $this->flushHtmlListItemInlines($inlines, $children);

        return $children;
    }

    /**
     * @param list<AstNode> $children
     */
    private function htmlListItemIsLoose(array $children): bool
    {
        foreach ($children as $child) {
            if ($child->type === 'paragraph') {
                return true;
            }

            if (!$this->htmlListChildIsInline($child) && !in_array($child->type, ['bullet_list', 'ordered_list'], true)) {
                return true;
            }
        }

        return false;
    }

    private function htmlListChildIsInline(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'emph',
            'strong',
            'small_caps',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'softbreak',
            'linebreak',
            'span',
            'quoted',
            'math',
            'raw_tex',
            'code',
            'link',
            'image',
            'note',
        ], true);
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $children
     */
    private function flushHtmlListItemInlines(array &$inlines, array &$children): void
    {
        $text = $this->plainTextFromInlines($inlines);
        if (trim(preg_replace('/\s+/', ' ', $text) ?? $text) !== '') {
            array_push($children, ...$inlines);
        }

        $inlines = [];
    }

    /**
     * @return array{start:int, style:string, delimiter:string}
     */
    private function htmlOrderedListAttrs(\DOMElement $list): array
    {
        $start = 1;
        $rawStart = trim($list->getAttribute('start'));
        if (preg_match('/^\d+$/', $rawStart) === 1 && (int) $rawStart > 0) {
            $start = (int) $rawStart;
        }

        return [
            'start' => $start,
            'style' => $this->htmlOrderedListStyle($list),
            'delimiter' => 'default',
        ];
    }

    private function htmlOrderedListStyle(\DOMElement $list): string
    {
        $type = trim($list->getAttribute('type'));
        if ($type === 'a') {
            return 'lower_alpha';
        }
        if ($type === 'A') {
            return 'upper_alpha';
        }
        if ($type === 'i') {
            return 'lower_roman';
        }
        if ($type === 'I') {
            return 'upper_roman';
        }

        foreach (preg_split('/\s+/', strtolower(trim($list->getAttribute('class'))), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
            $mapped = $this->htmlOrderedListStyleName($class);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        foreach (preg_split('/;/', strtolower($list->getAttribute('style'))) ?: [] as $declaration) {
            if (preg_match('/^\s*list-style(?:-type)?\s*:\s*([a-z-]+)\b/', $declaration, $m) !== 1) {
                continue;
            }

            $mapped = $this->htmlOrderedListStyleName($m[1]);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return 'default';
    }

    private function htmlOrderedListStyleName(string $style): ?string
    {
        return match ($style) {
            'decimal' => 'decimal',
            'lower-alpha' => 'lower_alpha',
            'upper-alpha' => 'upper_alpha',
            'lower-roman' => 'lower_roman',
            'upper-roman' => 'upper_roman',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function htmlCodeBlockClasses(\DOMElement $code): array
    {
        $classes = [];
        foreach (preg_split('/\s+/', trim($code->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
            $class = trim($class);
            if ($class === '') {
                continue;
            }

            if (str_starts_with($class, 'language-')) {
                $class = substr($class, strlen('language-'));
            }

            if ($class !== '') {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @param list<string> $lines
     * @return array{0:string, 1:int}|null
     */
    private function collectBalancedHtmlTableBlock(array $lines, int $index): ?array
    {
        $content = [];
        $depth = 0;
        $started = false;
        $count = count($lines);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $line = $this->normalizeRawHtmlLine($lines[$cursor]);
            $content[] = $line;

            preg_match_all('/<\/?table\b[^>]*>/i', $line, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as $match) {
                $tag = strtolower($match[0]);
                if (str_starts_with($tag, '</table')) {
                    $depth--;
                    if ($started && $depth === 0) {
                        return [implode("\n", $content), $cursor];
                    }
                    continue;
                }

                $started = true;
                $depth++;
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     * @return array{0:string, 1:int}|null
     */
    private function collectBalancedHtmlElementBlock(array $lines, int $index, string $tag): ?array
    {
        $content = [];
        $count = count($lines);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $line = $this->normalizeRawHtmlLine($lines[$cursor]);
            $content[] = $line;
            [$started, $depth] = $this->htmlElementBalance(implode("\n", $content), $tag);
            if ($started && $depth === 0) {
                return [implode("\n", $content), $cursor];
            }
        }

        return null;
    }

    /**
     * @return array{0:bool, 1:int}
     */
    private function htmlElementBalance(string $html, string $tag): array
    {
        $depth = 0;
        $started = false;
        $pattern = '/<\/?' . preg_quote($tag, '/') . '\b[^>]*>/i';

        preg_match_all($pattern, $html, $matches);
        foreach ($matches[0] as $matchedTag) {
            $matchedTag = strtolower(trim($matchedTag));
            if (str_starts_with($matchedTag, '</')) {
                if ($started) {
                    $depth--;
                }
                continue;
            }

            $started = true;
            if (!str_ends_with(rtrim($matchedTag), '/>')) {
                $depth++;
            }
        }

        return [$started, $depth];
    }

    private function htmlTableBlockIsEmpty(string $html): bool
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return false;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return false;
        }

        $table = $this->firstDescendantElement($body, 'table');
        if (!$table instanceof \DOMElement) {
            return false;
        }

        if (trim($this->directHtmlTableCaptionText($table)) !== '') {
            return false;
        }

        foreach (['td', 'th'] as $name) {
            foreach ($table->getElementsByTagName($name) as $cell) {
                if ($cell instanceof \DOMElement) {
                    return false;
                }
            }
        }

        return true;
    }

    private function directHtmlTableCaptionText(\DOMElement $table): string
    {
        foreach ($this->childElements($table, 'caption') as $caption) {
            return $caption->textContent;
        }

        return '';
    }

    private function parseStructuredHtmlTable(string $html): ?AstNode
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $source = preg_match('/^\s*(?:<!doctype\s+html\b|<html\b)/i', $html) === 1
            ? '<?xml encoding="UTF-8">' . $html
            : '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>';
        $loaded = $dom->loadHTML(
            $source,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $table = $this->firstDescendantElement($body, 'table');
        if (
            !$table instanceof \DOMElement
            || (!$this->htmlTableContainsNestedTable($table) && !$this->htmlTableHasStructuredBoundary($table))
        ) {
            return null;
        }

        return $this->parseHtmlTableElement($table);
    }

    private function htmlTableContainsNestedTable(\DOMElement $table): bool
    {
        foreach ($table->getElementsByTagName('table') as $nested) {
            if ($nested instanceof \DOMElement && !$nested->isSameNode($table)) {
                return true;
            }
        }

        return false;
    }

    private function htmlTableHasStructuredBoundary(\DOMElement $table): bool
    {
        foreach (['caption', 'colgroup', 'thead', 'tfoot'] as $name) {
            if ($this->firstChildElement($table, $name) instanceof \DOMElement) {
                return true;
            }
        }

        return $this->htmlTableHasHeaderCells($table)
            || $this->htmlTableHasSpans($table)
            || $this->htmlTableHasPlainBodyRows($table);
    }

    private function htmlTableHasHeaderCells(\DOMElement $table): bool
    {
        foreach ($table->getElementsByTagName('th') as $cell) {
            if ($cell instanceof \DOMElement) {
                return true;
            }
        }

        return false;
    }

    private function htmlTableHasSpans(\DOMElement $table): bool
    {
        foreach (['td', 'th'] as $name) {
            foreach ($table->getElementsByTagName($name) as $cell) {
                if (!$cell instanceof \DOMElement) {
                    continue;
                }

                if (
                    $this->positiveHtmlSpan($cell->getAttribute('colspan')) > 1
                    || $this->htmlRowspan($cell->getAttribute('rowspan')) !== 1
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function htmlTableHasPlainBodyRows(\DOMElement $table): bool
    {
        $sections = $this->childElements($table, 'tbody');
        if ($sections === []) {
            $sections = [$table];
        }

        $rowCount = 0;
        foreach ($sections as $section) {
            foreach ($this->childElements($section, 'tr') as $row) {
                $cellCount = 0;
                foreach ($row->childNodes as $child) {
                    if (!$child instanceof \DOMElement) {
                        continue;
                    }

                    if (strtolower($child->localName) !== 'td' || !$this->htmlTableCellIsPlainScalar($child)) {
                        return false;
                    }

                    $cellCount++;
                }

                if ($cellCount > 0) {
                    $rowCount++;
                }
            }
        }

        return $rowCount > 0;
    }

    private function htmlTableCellIsPlainScalar(\DOMElement $cell): bool
    {
        foreach ($cell->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                continue;
            }

            if ($child instanceof \DOMComment) {
                continue;
            }

            return false;
        }

        $text = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? $cell->textContent);
        if ($text === '') {
            return false;
        }

        return preg_match('/[*_`~\[\]\\\\$]/', $text) !== 1;
    }

    private function parseHtmlTableElement(\DOMElement $table): AstNode
    {
        $maxColumns = 0;
        $caption = $this->firstChildElement($table, 'caption');
        $thead = $this->firstChildElement($table, 'thead');
        $tfoot = $this->firstChildElement($table, 'tfoot');
        $bodySections = $this->childElements($table, 'tbody');

        $headRows = $thead instanceof \DOMElement ? $this->readHtmlTableRows($thead, true, $maxColumns) : [];
        $bodyNodes = [];
        if ($bodySections !== []) {
            foreach ($bodySections as $tbody) {
                $rows = $this->readHtmlTableRows($tbody, false, $maxColumns);
                [$bodyHeadRows, $bodyRows] = $this->splitHtmlTableBodyRows($rows);
                $bodyNodes[] = new AstNode(
                    'table_body',
                    array_merge($this->htmlElementPandocAttrs($tbody), $this->htmlTableBodyAttrs($bodyRows, $bodyHeadRows)),
                    $bodyRows
                );
            }
        } else {
            $bodyRows = $this->readHtmlTableRows($table, false, $maxColumns);
            if ($headRows === [] && $this->firstHtmlTableRowIsHeader($bodyRows)) {
                $headRow = array_shift($bodyRows);
                if ($headRow instanceof AstNode) {
                    $headRows[] = $this->markHtmlTableRowAsHeader($headRow);
                }
            }
            [$bodyHeadRows, $bodyRows] = $this->splitHtmlTableBodyRows($bodyRows);
            $bodyNodes[] = new AstNode('table_body', $this->htmlTableBodyAttrs($bodyRows, $bodyHeadRows), $bodyRows);
        }
        $footRows = $tfoot instanceof \DOMElement ? $this->readHtmlTableRows($tfoot, false, $maxColumns) : [];

        $captionInlines = $caption instanceof \DOMElement ? $this->parseHtmlInlineChildren($caption) : [];
        $columnMetadata = $this->readHtmlTableColumnMetadata($table, $maxColumns);
        $alignments = $columnMetadata['alignments'];
        if ($alignments === null) {
            $alignments = array_fill(0, $maxColumns, 'default');
        }

        $attrs = array_merge($this->htmlElementPandocAttrs($table), [
            'caption' => $captionInlines === [] ? '' : trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($captionInlines)) ?? ''),
            'alignments' => $alignments,
        ]);
        if ($columnMetadata['sources'] !== null) {
            $attrs['columnSources'] = $columnMetadata['sources'];
        }
        if ($columnMetadata['diagnostics'] !== []) {
            $attrs['columnDiagnostics'] = $columnMetadata['diagnostics'];
        }
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }

        $widths = $columnMetadata['widths'];
        if ($widths !== null) {
            $attrs['widths'] = $widths;
        } elseif ($maxColumns > 0) {
            $attrs['widths'] = array_fill(0, $maxColumns, 1 / $maxColumns);
        }

        $headAttrs = $thead instanceof \DOMElement ? $this->htmlElementPandocAttrs($thead) : [];
        $footAttrs = $tfoot instanceof \DOMElement ? $this->htmlElementPandocAttrs($tfoot) : [];

        $children = [
            new AstNode('table_head', $headAttrs, $headRows),
            ...$bodyNodes,
        ];
        if ($footRows !== []) {
            $children[] = new AstNode('table_foot', $footAttrs, $footRows);
        }

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $children));
    }

    /**
     * @param list<AstNode> $rows
     */
    private function firstHtmlTableRowIsHeader(array $rows): bool
    {
        $first = $rows[0] ?? null;

        return $first instanceof AstNode && $this->htmlTableRowIsAllHeaders($first);
    }

    /**
     * @param list<AstNode> $rows
     * @return array{0:list<AstNode>, 1:list<AstNode>}
     */
    private function splitHtmlTableBodyRows(array $rows): array
    {
        $headRows = [];
        while ($rows !== [] && $this->htmlTableRowIsAllHeaders($rows[0])) {
            $headRow = array_shift($rows);
            if ($headRow instanceof AstNode) {
                $headRows[] = $this->markHtmlTableRowAsHeader($headRow);
            }
        }

        return [$headRows, $rows];
    }

    private function htmlTableRowIsAllHeaders(AstNode $row): bool
    {
        if ($row->children === []) {
            return false;
        }

        foreach ($row->children as $cell) {
            if ($cell->type !== 'table_cell' || $cell->attr('header') !== true) {
                return false;
            }
        }

        return true;
    }

    private function markHtmlTableRowAsHeader(AstNode $row): AstNode
    {
        return new AstNode(
            $row->type,
            array_merge($row->attrs, ['header' => true]),
            $row->children
        );
    }

    /**
     * @param list<AstNode> $rows
     * @param list<AstNode> $headRows
     * @return array<string, mixed>
     */
    private function htmlTableBodyAttrs(array $rows, array $headRows = []): array
    {
        $rowHeadColumns = $this->countHtmlTableRowHeadColumns($rows);

        $attrs = $rowHeadColumns > 0 ? ['rowHeadColumns' => $rowHeadColumns] : [];
        if ($headRows !== []) {
            $attrs['headRows'] = $headRows;
            $attrs['headRowCount'] = count($headRows);
        }

        return $attrs;
    }

    /**
     * @param list<AstNode> $rows
     */
    private function countHtmlTableRowHeadColumns(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $minimum = null;
        foreach ($rows as $row) {
            $count = 0;
            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell' || $cell->attr('header') !== true) {
                    break;
                }
                $count++;
            }

            $minimum = $minimum === null ? $count : min($minimum, $count);
        }

        return $minimum ?? 0;
    }

    /**
     * @return array{alignments:?list<string>, widths:?list<?float>, sources:?list<array<string, mixed>>, diagnostics:list<array<string, mixed>>}
     */
    private function readHtmlTableColumnMetadata(\DOMElement $table, int $maxColumns): array
    {
        $colgroups = $this->childElements($table, 'colgroup');
        if ($colgroups === []) {
            return ['alignments' => null, 'widths' => null, 'sources' => null, 'diagnostics' => []];
        }

        $widths = [];
        $alignments = [];
        $sources = [];
        $hasAlignment = false;
        $hasWidth = false;
        $hasCompleteWidths = true;
        foreach ($colgroups as $colgroupIndex => $colgroup) {
            $cols = $this->childElements($colgroup, 'col');
            if ($cols === []) {
                $span = $this->positiveHtmlSpan($colgroup->getAttribute('span'));
                $alignment = $this->normalizeHtmlColumnAlignment($colgroup);
                $width = $this->htmlColumnWidthPercent($colgroup);
                for ($index = 0; $index < $span; $index++) {
                    $column = count($alignments);
                    $alignments[] = $alignment;
                    $widths[] = $width;
                    $sources[] = $this->htmlColumnSourceRecord($colgroup, $colgroupIndex, null, null, $column, $span, $index, $alignment, $width);
                    $hasAlignment = $hasAlignment || $alignment !== 'default';
                    $hasWidth = $hasWidth || $width !== null;
                    $hasCompleteWidths = $hasCompleteWidths && $width !== null;
                }
                continue;
            }

            foreach ($cols as $colIndex => $col) {
                $span = $this->positiveHtmlSpan($col->getAttribute('span'));
                $alignment = $this->normalizeHtmlColumnAlignment($col, $colgroup);
                $width = $this->htmlColumnWidthPercent($col);
                for ($index = 0; $index < $span; $index++) {
                    $column = count($alignments);
                    $alignments[] = $alignment;
                    $widths[] = $width;
                    $sources[] = $this->htmlColumnSourceRecord($colgroup, $colgroupIndex, $col, $colIndex, $column, $span, $index, $alignment, $width);
                    $hasAlignment = $hasAlignment || $alignment !== 'default';
                    $hasWidth = $hasWidth || $width !== null;
                    $hasCompleteWidths = $hasCompleteWidths && $width !== null;
                }
            }
        }

        if ($alignments === []) {
            return ['alignments' => null, 'widths' => null, 'sources' => null, 'diagnostics' => []];
        }

        $sourceColumnCount = count($alignments);
        $diagnostics = [];
        if ($maxColumns > 0 && $sourceColumnCount !== $maxColumns) {
            $diagnostics[] = $this->htmlColumnCountMismatchDiagnostic($sourceColumnCount, $maxColumns);
        }

        $targetColumnCount = $maxColumns > 0 ? max($maxColumns, $sourceColumnCount) : $sourceColumnCount;
        while (count($alignments) < $targetColumnCount) {
            $alignments[] = 'default';
            $widths[] = null;
        }

        return [
            'alignments' => $hasAlignment ? $alignments : null,
            'widths' => $hasWidth && ($hasCompleteWidths || $diagnostics !== []) ? array_values($widths) : null,
            'sources' => $sources,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlColumnCountMismatchDiagnostic(int $sourceColumnCount, int $tableColumnCount): array
    {
        $diagnostic = [
            'code' => $sourceColumnCount < $tableColumnCount
                ? 'html-colgroup-underdeclares-columns'
                : 'html-colgroup-overdeclares-columns',
            'source' => 'html-colgroup',
            'sourceColumns' => $sourceColumnCount,
            'tableColumns' => $tableColumnCount,
        ];

        if ($sourceColumnCount < $tableColumnCount) {
            $diagnostic['missingColumns'] = range($sourceColumnCount, $tableColumnCount - 1);
        } else {
            $diagnostic['extraColumns'] = range($tableColumnCount, $sourceColumnCount - 1);
        }

        return $diagnostic;
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlColumnSourceRecord(
        \DOMElement $colgroup,
        int $colgroupIndex,
        ?\DOMElement $col,
        ?int $colIndex,
        int $column,
        int $sourceSpan,
        int $spanOffset,
        string $alignment,
        ?float $width
    ): array {
        $record = [
            'kind' => $col instanceof \DOMElement ? 'col' : 'colgroup',
            'column' => $column,
            'colgroupIndex' => $colgroupIndex,
            'sourceSpan' => $sourceSpan,
            'spanOffset' => $spanOffset,
            'alignment' => $alignment,
            'width' => $width,
        ];

        $colgroupAttributes = $this->htmlColumnSourceAttributes($colgroup);
        if ($colgroupAttributes !== []) {
            $record['colgroupAttributes'] = $colgroupAttributes;
        }

        if ($col instanceof \DOMElement) {
            $record['colIndex'] = $colIndex ?? 0;
            $colAttributes = $this->htmlColumnSourceAttributes($col);
            if ($colAttributes !== []) {
                $record['colAttributes'] = $colAttributes;
            }
        }

        return $record;
    }

    /**
     * @return array{htmlAttributes?:array<string, string>}
     */
    private function htmlColumnSourceAttributes(\DOMElement $element): array
    {
        $htmlAttributes = [];
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $name = strtolower(trim($attribute->name));
            if ($name === '') {
                continue;
            }

            $htmlAttributes[$name] = trim($attribute->value);
        }

        if ($htmlAttributes === []) {
            return [];
        }

        ksort($htmlAttributes);

        return ['htmlAttributes' => $htmlAttributes];
    }

    private function htmlColumnWidthPercent(\DOMElement $col): ?float
    {
        $style = strtolower($col->getAttribute('style'));
        if (preg_match('/width\s*:\s*([0-9]+(?:\.[0-9]+)?)\s*%/', $style, $m) === 1) {
            return (float) $m[1] / 100;
        }

        $width = trim($col->getAttribute('width'));
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*%$/', $width, $m) === 1) {
            return (float) $m[1] / 100;
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function readHtmlTableRows(\DOMElement $section, bool $header, int &$maxColumns): array
    {
        $rows = [];
        foreach ($this->childElements($section, 'tr') as $rowElement) {
            $cells = [];
            $rowColumns = 0;
            foreach ($rowElement->childNodes as $child) {
                if (!$child instanceof \DOMElement || !in_array(strtolower($child->localName), ['td', 'th'], true)) {
                    continue;
                }

                $cell = $this->buildHtmlTableCell($child, $header || strtolower($child->localName) === 'th');
                $cells[] = $cell;
                $rowColumns += max(1, (int) $cell->attr('colspan', 1));
            }

            if ($cells === []) {
                continue;
            }

            $maxColumns = max($maxColumns, $rowColumns);
            $rows[] = new AstNode(
                'table_row',
                array_merge($this->htmlElementPandocAttrs($rowElement), ['header' => $header]),
                $cells
            );
        }

        if ($rows !== []) {
            $maxColumns = max($maxColumns, TableGeometry::columnCountForRows($rows));
        }

        return $rows;
    }

    private function buildHtmlTableCell(\DOMElement $cell, bool $header): AstNode
    {
        $children = $this->parseHtmlTableCellChildren($cell);
        $attrs = array_merge($this->htmlElementPandocAttrs($cell, ['align', 'colspan', 'rowspan']), [
            'text' => trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? $cell->textContent),
            'header' => $header,
        ]);

        $colspan = $this->positiveHtmlSpan($cell->getAttribute('colspan'));
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }

        $rowspan = $this->htmlRowspan($cell->getAttribute('rowspan'));
        if ($rowspan === 0 || $rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }

        $alignment = $this->normalizeHtmlTableAlignment($cell);
        if ($alignment !== 'default') {
            $attrs['align'] = $alignment;
        }

        return new AstNode('table_cell', $attrs, $children);
    }

    /**
     * @param list<string> $skip
     * @return array<string, mixed>
     */
    private function htmlElementPandocAttrs(\DOMElement $element, array $skip = []): array
    {
        $id = '';
        $classes = [];
        $attributes = [];
        $htmlAttributes = [];

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $name = strtolower($attribute->name);
            if (in_array($name, $skip, true)) {
                continue;
            }

            $value = trim($attribute->value);
            if ($name === 'id') {
                $id = $value;
                if ($value !== '') {
                    $htmlAttributes['id'] = $value;
                }
                continue;
            }

            if ($name === 'class') {
                $classes = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if ($classes !== []) {
                    $htmlAttributes['class'] = implode(' ', $classes);
                }
                continue;
            }

            if ($name === 'style') {
                $value = $this->htmlTableNonAlignmentStyle($value);
                if ($value === '') {
                    continue;
                }
            }

            $key = str_starts_with($name, 'data-') ? substr($name, 5) : $name;
            if ($key === '') {
                continue;
            }

            $attributes[$key] = $value;
            $htmlAttributes[$name] = $value;
        }

        $attrs = [];
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    private function htmlTableNonAlignmentStyle(string $style): string
    {
        $kept = [];
        foreach (preg_split('/;/', $style) ?: [] as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '' || preg_match('/^text-align\s*:/i', $declaration) === 1) {
                continue;
            }

            $kept[] = $declaration;
        }

        return implode('; ', $kept);
    }

    private function positiveHtmlSpan(string $value): int
    {
        $value = trim($value);

        return preg_match('/^\d+$/', $value) === 1 ? max(1, (int) $value) : 1;
    }

    private function htmlRowspan(string $value): int
    {
        $value = trim($value);
        if (preg_match('/^\d+$/', $value) !== 1) {
            return 1;
        }

        $span = (int) $value;

        return $span === 0 ? 0 : max(1, $span);
    }

    private function normalizeHtmlTableAlignment(\DOMElement $cell): string
    {
        return $this->normalizeHtmlColumnAlignment($cell);
    }

    private function normalizeHtmlColumnAlignment(\DOMElement $element, ?\DOMElement $fallback = null): string
    {
        $alignment = $this->normalizeHtmlElementAlignment($element);
        if ($alignment !== 'default') {
            return $alignment;
        }

        return $fallback instanceof \DOMElement ? $this->normalizeHtmlElementAlignment($fallback) : 'default';
    }

    private function normalizeHtmlElementAlignment(\DOMElement $element): string
    {
        $align = strtolower(trim($element->getAttribute('align')));
        if (in_array($align, ['left', 'right', 'center'], true)) {
            return $align;
        }

        $style = strtolower($element->getAttribute('style'));
        if (preg_match('/text-align\s*:\s*(left|right|center)\b/', $style, $m) === 1) {
            return $m[1];
        }

        return 'default';
    }

    /**
     * @return list<AstNode>
     */
    private function parseHtmlTableCellChildren(\DOMElement $cell): array
    {
        $children = [];
        foreach ($cell->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'table') {
                $children[] = $this->parseHtmlTableElement($child);
                continue;
            }
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'p') {
                $inlines = $this->parseHtmlInlineChildren($child);
                if ($inlines !== []) {
                    $children[] = new AstNode(
                        'paragraph',
                        ['text' => trim(preg_replace('/\s+/', ' ', $child->textContent) ?? $child->textContent)],
                        $inlines
                    );
                }
                continue;
            }

            array_push($children, ...$this->parseHtmlInlineNode($child));
        }

        return $children;
    }

    /**
     * @return list<AstNode>
     */
    private function parseHtmlInlineNode(\DOMNode $node): array
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            $raw = $node->wholeText;
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }

            $text = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
            if (preg_match('/^\s/u', $raw) === 1) {
                $text = ' ' . $text;
            }
            if (preg_match('/\s$/u', $raw) === 1) {
                $text .= ' ';
            }

            return [new AstNode('text', ['text' => $text])];
        }

        if (!$node instanceof \DOMElement) {
            return [];
        }

        $name = strtolower($node->localName);
        $children = $this->parseHtmlInlineChildren($node);

        if (in_array($name, ['strong', 'b'], true)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('strong', [], $children);
        }
        if (in_array($name, ['em', 'i'], true)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('emph', [], $children);
        }
        if ($name === 'sup') {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('superscript', [], $children);
        }
        if ($name === 'sub') {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('subscript', [], $children);
        }
        if ($name === 'span' && $this->htmlElementHasSmallCapsStyle($node)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('small_caps', [], $children);
        }
        if (in_array($name, ['u', 'ins'], true)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('underline', [], $children);
        }
        if (in_array($name, ['s', 'strike', 'del'], true)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('strikeout', [], $children);
        }
        if (in_array($name, ['code', 'kbd', 'samp'], true)) {
            return [new AstNode('code', ['text' => trim(preg_replace('/\s+/', ' ', $node->textContent) ?? $node->textContent)])];
        }
        if ($name === 'q') {
            $quotedChildren = $children;
            $attrs = $this->htmlElementPandocAttrs($node);
            if ($attrs !== []) {
                $quotedChildren = [new AstNode('span', $attrs, $children)];
            }

            return [new AstNode('quoted', ['kind' => 'double'], $quotedChildren)];
        }
        if ($name === 'span') {
            $attrs = $this->htmlElementPandocAttrs($node);
            if ($attrs !== []) {
                return [new AstNode('span', $attrs, $children)];
            }

            return $children;
        }
        if ($name === 'a') {
            return [new AstNode('link', [
                'url' => $node->getAttribute('href'),
                'title' => $node->getAttribute('title'),
            ], $children)];
        }
        if ($name === 'img') {
            return [$this->buildHtmlImageNode($node)];
        }
        if ($name === 'br') {
            return [new AstNode('linebreak')];
        }

        return $children;
    }

    /**
     * @param array<string, mixed> $attrs
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function wrapHtmlInlineWithBoundaryWhitespace(string $type, array $attrs, array $children): array
    {
        $prefix = '';
        $suffix = '';
        $firstKey = array_key_first($children);
        if ($firstKey !== null) {
            $first = $children[$firstKey];
            if ($first->type === 'text') {
                $text = (string) $first->attr('text', '');
                if (preg_match('/^\s/u', $text) === 1) {
                    $prefix = ' ';
                    $text = ltrim($text);
                    if ($text === '') {
                        unset($children[$firstKey]);
                    } else {
                        $children[$firstKey] = new AstNode('text', ['text' => $text]);
                    }
                }
            }
        }

        $lastKey = array_key_last($children);
        if ($lastKey !== null) {
            $last = $children[$lastKey];
            if ($last->type === 'text') {
                $text = (string) $last->attr('text', '');
                if (preg_match('/\s$/u', $text) === 1) {
                    $suffix = ' ';
                    $text = rtrim($text);
                    if ($text === '') {
                        unset($children[$lastKey]);
                    } else {
                        $children[$lastKey] = new AstNode('text', ['text' => $text]);
                    }
                }
            }
        }

        $wrapped = [];
        if ($prefix !== '') {
            $wrapped[] = new AstNode('text', ['text' => $prefix]);
        }

        $wrapped[] = new AstNode($type, $attrs, array_values($children));

        if ($suffix !== '') {
            $wrapped[] = new AstNode('text', ['text' => $suffix]);
        }

        return $wrapped;
    }

    private function buildHtmlImageNode(\DOMElement $image): AstNode
    {
        $alt = $image->getAttribute('alt');
        $attrs = [
            'url' => $image->getAttribute('src'),
            'alt' => $alt,
        ];
        $title = $image->getAttribute('title');
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        $children = $alt === '' ? [] : [new AstNode('text', ['text' => $alt])];

        return new AstNode('image', $attrs, $children);
    }

    private function htmlElementHasSmallCapsStyle(\DOMElement $element): bool
    {
        $style = strtolower($element->getAttribute('style'));
        if ($style === '') {
            return false;
        }

        return preg_match('/(?:^|;)\s*font-variant\s*:\s*small-caps\b/', $style) === 1
            || preg_match('/(?:^|;)\s*font-variant-caps\s*:\s*small-caps\b/', $style) === 1;
    }

    /**
     * @return list<AstNode>
     */
    private function parseHtmlInlineChildren(\DOMElement $element): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            $this->appendHtmlInlineNodes($children, $this->parseHtmlInlineNode($child));
        }

        return $children;
    }

    /**
     * @param list<AstNode> $children
     * @param list<AstNode> $nodes
     */
    private function appendHtmlInlineNodes(array &$children, array $nodes): void
    {
        foreach ($nodes as $node) {
            $lastKey = array_key_last($children);
            $last = $lastKey === null ? null : $children[$lastKey];
            if ($last instanceof AstNode && $last->type === 'linebreak' && $node->type === 'text') {
                $text = ltrim((string) $node->attr('text', ''));
                if ($text === '') {
                    continue;
                }

                $node = new AstNode('text', array_merge($node->attrs, ['text' => $text]), $node->children);
            }

            $children[] = $node;
        }
    }

    /**
     * @param list<string> $columnNames
     * @return list<AstNode>
     */
    private function readDocBookTableRows(\DOMElement $section, array $columnNames, bool $header, int &$maxColumns): array
    {
        $rows = [];
        foreach ($this->childElements($section, 'row') as $rowElement) {
            $cells = [];
            $rowColumns = 0;
            foreach ($this->childElements($rowElement, 'entry') as $entry) {
                $cell = $this->buildDocBookTableCell($entry, $columnNames, $header);
                $cells[] = $cell;
                $rowColumns += max(1, (int) $cell->attr('colspan', 1));
            }

            if ($cells !== []) {
                $rows[] = new AstNode('table_row', ['header' => $header], $cells);
                $maxColumns = max($maxColumns, $rowColumns);
            }
        }

        return $rows;
    }

    /**
     * @return array{0:list<string>, 1:list<float>|null}
     */
    private function readDocBookColumnSpecs(\DOMElement $tgroup): array
    {
        $names = [];
        $widthParts = [];
        foreach ($this->childElements($tgroup, 'colspec') as $index => $colspec) {
            $name = trim($colspec->getAttribute('colname'));
            $names[] = $name !== '' ? $name : 'col_' . ($index + 1);

            $width = trim($colspec->getAttribute('colwidth'));
            $widthParts[] = preg_match('/^([0-9]+(?:\.[0-9]+)?)\*$/', $width, $m) === 1
                ? (float) $m[1]
                : null;
        }

        $widths = null;
        if ($widthParts !== [] && !in_array(null, $widthParts, true)) {
            $total = array_sum($widthParts);
            if ($total > 0.0) {
                $widths = array_map(
                    static fn (float $width): float => $width / $total,
                    $widthParts
                );
            }
        }

        return [$names, $widths];
    }

    /**
     * @param list<string> $columnNames
     */
    private function buildDocBookTableCell(\DOMElement $entry, array $columnNames, bool $header): AstNode
    {
        $children = $this->parseDocBookInlineNodes($entry);
        $attrs = [
            'text' => $this->plainTextFromInlines($children),
            'header' => $header,
        ];

        $align = $this->normalizeDocBookAlignment($entry->getAttribute('align'));
        if ($align !== 'default') {
            $attrs['align'] = $align;
        }

        $colSpan = $this->docBookColumnSpan($entry, $columnNames);
        if ($colSpan > 1) {
            $attrs['colspan'] = $colSpan;
        }

        $moreRows = trim($entry->getAttribute('morerows'));
        if (preg_match('/^\d+$/', $moreRows) === 1 && (int) $moreRows > 0) {
            $attrs['rowspan'] = (int) $moreRows + 1;
        }

        return new AstNode('table_cell', $attrs, $children);
    }

    /**
     * @param list<string> $columnNames
     */
    private function docBookColumnSpan(\DOMElement $entry, array $columnNames): int
    {
        $startName = trim($entry->getAttribute('namest'));
        $endName = trim($entry->getAttribute('nameend'));
        if ($startName === '' || $endName === '') {
            return 1;
        }

        $start = array_search($startName, $columnNames, true);
        $end = array_search($endName, $columnNames, true);
        if (!is_int($start) || !is_int($end) || $end < $start) {
            return 1;
        }

        return $end - $start + 1;
    }

    private function normalizeDocBookAlignment(string $alignment): string
    {
        return match (strtolower(trim($alignment))) {
            'left' => 'left',
            'right' => 'right',
            'center' => 'center',
            default => 'default',
        };
    }

    /**
     * @return list<AstNode>
     */
    private function parseDocBookInlineNodes(\DOMNode $node): array
    {
        $nodes = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $this->appendDocBookTextNode($nodes, $child->wholeText);
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->localName);
            $children = $this->parseDocBookInlineNodes($child);
            if ($name === 'emphasis') {
                $role = strtolower(trim($child->getAttribute('role')));
                $nodes[] = new AstNode($role === 'strong' || $role === 'bold' ? 'strong' : 'emph', [], $children);
                continue;
            }

            if (in_array($name, ['code', 'command', 'filename', 'literal'], true)) {
                $nodes[] = new AstNode('code', ['text' => trim(preg_replace('/\s+/', ' ', $child->textContent) ?? $child->textContent)]);
                continue;
            }

            if ($name === 'ulink' || $name === 'link') {
                $url = $child->getAttribute('url') ?: $child->getAttribute('href');
                $nodes[] = new AstNode('link', ['url' => $url, 'title' => ''], $children);
                continue;
            }

            array_push($nodes, ...$children);
        }

        return $nodes;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function appendDocBookTextNode(array &$nodes, string $text): void
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $text]);
    }

    private function firstDescendantElement(\DOMElement $root, string $name): ?\DOMElement
    {
        foreach ($root->getElementsByTagName($name) as $element) {
            if ($element instanceof \DOMElement) {
                return $element;
            }
        }

        return null;
    }

    private function firstChildElement(\DOMElement $root, string $name): ?\DOMElement
    {
        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === $name) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElements(\DOMElement $root, string $name): array
    {
        $children = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === $name) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadRawTexBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        $macro = $this->tryReadRawTexMacroDefinition($line);
        if ($macro !== null) {
            $this->rawTexMacros[$macro['name']] = [
                'arity' => $macro['arity'],
                'template' => $macro['template'],
            ];

            return new AstNode('raw_tex', [
                'tex' => trim($line),
                'command' => $macro['command'],
            ]);
        }

        if (preg_match('/^ {0,3}\\\\placeformula\s+\\\\startformula(?:\s.*)?$/', $line) === 1) {
            return new AstNode('raw_tex', [
                'tex' => trim($line),
                'environment' => 'context-formula',
            ]);
        }

        $contextBlock = $this->tryReadContextStartStopBlock($lines, $index);
        if ($contextBlock !== null) {
            return $contextBlock;
        }

        if (preg_match('/^ {0,3}\\\\begin\{([^}\s]+)\}/', $line, $m) !== 1) {
            return null;
        }

        $environment = $m[1];
        $content = [];
        $cursor = $index;
        $count = count($lines);
        $closingPattern = '/\\\\end\{' . preg_quote($environment, '/') . '\}/';

        while ($cursor < $count) {
            $content[] = rtrim($lines[$cursor]);
            if (preg_match($closingPattern, $lines[$cursor]) === 1) {
                $index = $cursor;

                return new AstNode('raw_tex', [
                    'tex' => implode("\n", $content),
                    'environment' => $environment,
                ]);
            }
            $cursor++;
        }

        return null;
    }

    /**
     * @return array{command:string, name:string, arity:int, template:string}|null
     */
    private function tryReadRawTexMacroDefinition(string $line): ?array
    {
        if (
            preg_match(
                '/^ {0,3}\\\\((?:re)?newcommand|providecommand)\{\\\\([A-Za-z]+)\}(?:\[(\d+)])?(?:\[[^\]\r\n]*])?\{((?:\\\\.|[^{}])*)\}[ \t]*$/',
                $line,
                $m
            ) !== 1
        ) {
            return null;
        }

        return [
            'command' => $m[1],
            'name' => $m[2],
            'arity' => isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0,
            'template' => $m[4],
        ];
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadContextStartStopBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}\\\\start\[([^\]\r\n]+)]\s*$/', $line, $m) !== 1) {
            return null;
        }

        $environment = $m[1];
        $content = [];
        $depth = 0;
        $cursor = $index;
        $count = count($lines);
        $startPattern = '/^ {0,3}\\\\start\[' . preg_quote($environment, '/') . ']\s*$/';
        $stopPattern = '/^ {0,3}\\\\stop\[' . preg_quote($environment, '/') . ']\s*$/';

        while ($cursor < $count) {
            $current = rtrim($lines[$cursor]);
            if (preg_match($startPattern, $current) === 1) {
                $depth++;
            }

            $content[] = $current;

            if (preg_match($stopPattern, $current) === 1) {
                $depth--;
                if ($depth === 0) {
                    $index = $cursor;

                    return new AstNode('raw_tex', [
                        'tex' => implode("\n", $content),
                        'environment' => 'context:' . $environment,
                    ]);
                }
            }

            $cursor++;
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadGridTable(array $lines, int &$index): ?AstNode
    {
        $rectangular = $this->tryReadRectangularGridTable($lines, $index);
        if ($rectangular instanceof AstNode) {
            return $rectangular;
        }

        return $this->tryReadSpannedGridTable($lines, $index);
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadRectangularGridTable(array $lines, int &$index): ?AstNode
    {
        $firstBoundary = $this->parseGridTableBoundary($lines[$index] ?? '');
        if ($firstBoundary === null) {
            return null;
        }

        $cursor = $index + 1;
        $count = count($lines);
        $columnCount = count($firstBoundary['widths']);
        $alignments = $firstBoundary['alignments'];
        $widths = $firstBoundary['widths'];
        $headerRow = null;
        $bodyRows = [];
        $sawHeader = false;

        while ($cursor < $count) {
            $sectionLines = [];
            while ($cursor < $count && $this->isGridTableContentLine($lines[$cursor])) {
                $sectionLines[] = $lines[$cursor];
                $cursor++;
            }

            if ($sectionLines === [] || $cursor >= $count) {
                return null;
            }

            $boundary = $this->parseGridTableBoundary($lines[$cursor]);
            if ($boundary === null || count($boundary['widths']) !== $columnCount) {
                return null;
            }

            $cellLines = $this->splitGridTableSectionCellLines($sectionLines, $columnCount);
            if ($cellLines === null) {
                return null;
            }

            if (!$sawHeader && $boundary['header']) {
                $headerRow = $this->buildGridTableRow($cellLines, true);
                $alignments = $boundary['alignments'];
                $widths = $boundary['widths'];
                $sawHeader = true;
                $cursor++;
                continue;
            }

            $bodyRows[] = $this->buildGridTableRow($cellLines, false);
            $cursor++;
            if ($cursor < $count && $this->isGridTableContentLine($lines[$cursor])) {
                continue;
            }

            [$caption, $next] = $this->readTableCaption($lines, $cursor);
            $index = $next - 1;

            return $this->buildGridTable($headerRow, $bodyRows, $alignments, $caption, $widths);
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadSpannedGridTable(array $lines, int &$index): ?AstNode
    {
        $tableLines = $this->collectSpannedGridTableLines($lines, $index);
        if ($tableLines === null || !$this->gridTableLinesContainSpans($tableLines)) {
            return null;
        }

        $positions = $this->spannedGridTableColumnPositions($tableLines);
        if (count($positions) < 3) {
            return null;
        }

        $events = $this->spannedGridTableEvents($tableLines, $positions);
        if (count($events) < 2 || $events[0]['line'] !== 0) {
            return null;
        }

        $lastEvent = $events[count($events) - 1];
        if ($lastEvent['line'] !== count($tableLines) - 1) {
            return null;
        }

        $headerBoundaryIndex = null;
        $alignments = array_fill(0, count($positions) - 1, 'default');
        foreach ($events as $eventIndex => $event) {
            if ($eventIndex === 0 || !$this->spannedGridEventCoversAllColumns($event)) {
                continue;
            }

            if (in_array('header', $event['markers'], true)) {
                $headerBoundaryIndex = $eventIndex;
                $alignments = $this->spannedGridTableAlignments($tableLines[$event['line']], $positions);
                break;
            }
        }

        if ($headerBoundaryIndex === null) {
            return null;
        }

        $headerRows = [];
        $bodyRows = [];
        $covered = [];
        $rowBandCount = count($events) - 1;
        $columnCount = count($positions) - 1;
        for ($rowIndex = 0; $rowIndex < $rowBandCount; $rowIndex++) {
            $isHeader = $rowIndex < $headerBoundaryIndex;
            $rowCells = [];
            for ($columnIndex = 0; $columnIndex < $columnCount; $columnIndex++) {
                if (($covered[$rowIndex][$columnIndex] ?? false) === true) {
                    continue;
                }

                if (!$events[$rowIndex]['coverage'][$columnIndex]) {
                    return null;
                }

                $colspan = $this->spannedGridTableCellColspan($tableLines[$events[$rowIndex]['line']], $positions, $columnIndex);
                $rowspan = $this->spannedGridTableCellRowspan($events, $rowIndex, $columnIndex, $colspan);
                for ($coveredRow = $rowIndex + 1; $coveredRow < $rowIndex + $rowspan; $coveredRow++) {
                    for ($coveredColumn = $columnIndex; $coveredColumn < $columnIndex + $colspan; $coveredColumn++) {
                        $covered[$coveredRow][$coveredColumn] = true;
                    }
                }

                $cellLines = $this->spannedGridTableCellLines($tableLines, $events, $positions, $rowIndex, $columnIndex, $rowspan, $colspan);
                $rowCells[] = $this->buildGridTableCell($cellLines, $isHeader, $rowspan, $colspan);
                $columnIndex += $colspan - 1;
            }

            $row = new AstNode('table_row', ['header' => $isHeader], $rowCells);
            if ($isHeader) {
                $headerRows[] = $row;
            } else {
                $bodyRows[] = $row;
            }
        }

        $cursor = $index + count($tableLines);
        [$caption, $next] = $this->readTableCaption($lines, $cursor);
        $index = $next - 1;

        return $this->buildGridTableRows($headerRows, $bodyRows, $alignments, $caption, $this->spannedGridTableWidths($positions));
    }

    /**
     * @return array{alignments:list<string>, widths:list<float>, header:bool}|null
     */
    private function parseGridTableBoundary(string $line): ?array
    {
        $line = rtrim($this->expandTabsToSpaces($line));
        if (preg_match('/^ {0,3}(\+[-=:]+(?:\+[-=:]+)+\+)$/', $line, $m) !== 1) {
            return null;
        }

        $parts = explode('+', substr($m[1], 1, -1));
        if ($parts === []) {
            return null;
        }

        $header = false;
        $alignments = [];
        $widths = [];
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^:?[-=]+:?$/', $part) !== 1) {
                return null;
            }

            $usesHeaderMarker = str_contains($part, '=');
            $usesBodyMarker = str_contains($part, '-');
            if ($usesHeaderMarker && $usesBodyMarker) {
                return null;
            }
            $header = $header || $usesHeaderMarker;

            $left = str_starts_with($part, ':');
            $right = str_ends_with($part, ':');
            $alignments[] = match (true) {
                $left && $right => 'center',
                $left => 'left',
                $right => 'right',
                default => 'default',
            };
            $widths[] = (strlen($part) + 1) / 72;
        }

        if ($header) {
            foreach ($parts as $part) {
                if (!str_contains($part, '=')) {
                    return null;
                }
            }
        }

        return [
            'alignments' => $alignments,
            'widths' => $widths,
            'header' => $header,
        ];
    }

    /**
     * @param list<string> $lines
     * @return list<string>|null
     */
    private function collectSpannedGridTableLines(array $lines, int $index): ?array
    {
        if ($this->parseGridTableBoundary($lines[$index] ?? '') === null) {
            return null;
        }

        $tableLines = [];
        $cursor = $index;
        $count = count($lines);
        while ($cursor < $count) {
            $line = $this->normalizeSpannedGridTableLine($lines[$cursor]);
            if (!$this->isSpannedGridTableLine($line)) {
                break;
            }

            $tableLines[] = $line;
            $cursor++;
        }

        return count($tableLines) >= 3 ? $tableLines : null;
    }

    private function normalizeSpannedGridTableLine(string $line): string
    {
        $line = rtrim($this->expandTabsToSpaces($line));

        return preg_replace('/^ {0,3}/', '', $line, 1) ?? $line;
    }

    private function isSpannedGridTableLine(string $line): bool
    {
        if ($line === '') {
            return false;
        }

        if ($line[0] === '+') {
            return preg_match('/^\+(?:[-=:]+\+)+$/', $line) === 1;
        }

        return $line[0] === '|' && preg_match('/[|+]\s*$/', $line) === 1;
    }

    /**
     * @param list<string> $lines
     */
    private function gridTableLinesContainSpans(array $lines): bool
    {
        $positionSets = [];
        foreach ($lines as $line) {
            if ($line[0] === '|' && preg_match('/\+[-=:]+\+/', $line) === 1) {
                return true;
            }

            $positions = $this->spannedGridTableHorizontalPlusPositions($line);
            if ($positions !== []) {
                $positionSets[implode(',', $positions)] = true;
            }
        }

        return count($positionSets) > 1;
    }

    /**
     * @param list<string> $lines
     * @return list<int>
     */
    private function spannedGridTableColumnPositions(array $lines): array
    {
        $positions = [];
        foreach ($lines as $line) {
            foreach ($this->spannedGridTableHorizontalPlusPositions($line) as $position) {
                $positions[$position] = true;
            }
        }

        $positions = array_keys($positions);
        sort($positions, SORT_NUMERIC);

        return array_values($positions);
    }

    /**
     * @return list<int>
     */
    private function spannedGridTableHorizontalPlusPositions(string $line): array
    {
        $positions = [];
        $chars = $this->unicodeChars($line);
        $count = count($chars);
        for ($offset = 0; $offset < $count; $offset++) {
            if ($chars[$offset] !== '+') {
                continue;
            }

            $before = $chars[$offset - 1] ?? '';
            $after = $chars[$offset + 1] ?? '';
            if ($this->isGridHorizontalChar($before) || $this->isGridHorizontalChar($after)) {
                $positions[] = $offset;
            }
        }

        return $positions;
    }

    /**
     * @param list<string> $lines
     * @param list<int> $positions
     * @return list<array{line:int, coverage:list<bool>, markers:list<string>}>
     */
    private function spannedGridTableEvents(array $lines, array $positions): array
    {
        $events = [];
        foreach ($lines as $lineIndex => $line) {
            $coverage = [];
            $markers = [];
            $sawCoverage = false;
            for ($columnIndex = 0; $columnIndex < count($positions) - 1; $columnIndex++) {
                $marker = $this->spannedGridTableBoundaryMarker($line, $positions[$columnIndex], $positions[$columnIndex + 1]);
                $coverage[] = $marker !== null;
                $markers[] = $marker ?? 'none';
                $sawCoverage = $sawCoverage || $marker !== null;
            }

            if ($sawCoverage) {
                $events[] = [
                    'line' => $lineIndex,
                    'coverage' => $coverage,
                    'markers' => $markers,
                ];
            }
        }

        return $events;
    }

    private function spannedGridTableBoundaryMarker(string $line, int $start, int $end): ?string
    {
        if ($end <= $start + 1) {
            return null;
        }

        $inner = $this->spannedGridTableInnerSegment($line, $start, $end);
        if ($inner === '' || preg_match('/^[-=:]+$/', $inner) !== 1 || preg_match('/[-=]/', $inner) !== 1) {
            return null;
        }

        return str_contains($inner, '=') ? 'header' : 'body';
    }

    private function isGridHorizontalChar(string $char): bool
    {
        return $char === '-' || $char === '=' || $char === ':';
    }

    /**
     * @param array{line:int, coverage:list<bool>, markers:list<string>} $event
     */
    private function spannedGridEventCoversAllColumns(array $event): bool
    {
        foreach ($event['coverage'] as $covered) {
            if (!$covered) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<int> $positions
     * @return list<string>
     */
    private function spannedGridTableAlignments(string $line, array $positions): array
    {
        $alignments = [];
        for ($columnIndex = 0; $columnIndex < count($positions) - 1; $columnIndex++) {
            $part = $this->spannedGridTableInnerSegment($line, $positions[$columnIndex], $positions[$columnIndex + 1]);
            $left = str_starts_with($part, ':');
            $right = str_ends_with($part, ':');
            $alignments[] = match (true) {
                $left && $right => 'center',
                $left => 'left',
                $right => 'right',
                default => 'default',
            };
        }

        return $alignments;
    }

    /**
     * @param list<int> $positions
     */
    private function spannedGridTableCellColspan(string $eventLine, array $positions, int $columnIndex): int
    {
        $chars = $this->unicodeChars($eventLine);
        $columnCount = count($positions) - 1;
        $colspan = 1;
        while (
            $columnIndex + $colspan < $columnCount
            && ($chars[$positions[$columnIndex + $colspan]] ?? '') !== '+'
        ) {
            $colspan++;
        }

        return $colspan;
    }

    /**
     * @param list<array{line:int, coverage:list<bool>, markers:list<string>}> $events
     */
    private function spannedGridTableCellRowspan(array $events, int $rowIndex, int $columnIndex, int $colspan): int
    {
        $rowspan = 1;
        while ($rowIndex + $rowspan < count($events) - 1) {
            $boundary = $events[$rowIndex + $rowspan];
            $covered = true;
            for ($column = $columnIndex; $column < $columnIndex + $colspan; $column++) {
                if (!$boundary['coverage'][$column]) {
                    $covered = false;
                    break;
                }
            }

            if ($covered) {
                break;
            }

            $rowspan++;
        }

        return $rowspan;
    }

    /**
     * @param list<string> $tableLines
     * @param list<array{line:int, coverage:list<bool>, markers:list<string>}> $events
     * @param list<int> $positions
     * @return list<string>
     */
    private function spannedGridTableCellLines(array $tableLines, array $events, array $positions, int $rowIndex, int $columnIndex, int $rowspan, int $colspan): array
    {
        $lines = [];
        $top = $events[$rowIndex]['line'];
        $bottom = $events[$rowIndex + $rowspan]['line'];
        $start = $positions[$columnIndex];
        $end = $positions[$columnIndex + $colspan];
        for ($lineIndex = $top + 1; $lineIndex < $bottom; $lineIndex++) {
            $lines[] = $this->spannedGridTableSlice($tableLines[$lineIndex], $start, $end);
        }

        return $lines;
    }

    private function spannedGridTableSlice(string $line, int $start, int $end): string
    {
        $chars = $this->unicodeChars($line);
        $slice = '';
        for ($offset = $start + 1; $offset < $end; $offset++) {
            $slice .= $chars[$offset] ?? ' ';
        }

        return $slice;
    }

    private function spannedGridTableInnerSegment(string $line, int $start, int $end): string
    {
        $chars = $this->unicodeChars($line);
        $segment = '';
        for ($offset = $start + 1; $offset < $end; $offset++) {
            $segment .= $chars[$offset] ?? ' ';
        }

        return $segment;
    }

    /**
     * @param list<int> $positions
     * @return list<float>
     */
    private function spannedGridTableWidths(array $positions): array
    {
        $widths = [];
        for ($index = 0; $index < count($positions) - 1; $index++) {
            $widths[] = ($positions[$index + 1] - $positions[$index]) / 72;
        }

        return $widths;
    }

    /**
     * @return list<string>
     */
    private function unicodeChars(string $line): array
    {
        $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);

        return $chars === false ? str_split($line) : $chars;
    }

    private function isGridTableContentLine(string $line): bool
    {
        return preg_match('/^ {0,3}\|.*\|\s*$/', $this->expandTabsToSpaces($line)) === 1;
    }

    /**
     * @param list<string> $lines
     * @return list<list<string>>|null
     */
    private function splitGridTableSectionCellLines(array $lines, int $columnCount): ?array
    {
        $cells = array_fill(0, $columnCount, []);
        foreach ($lines as $line) {
            $parts = $this->splitGridTableContentLine($line, $columnCount);
            if ($parts === null) {
                return null;
            }

            foreach ($parts as $cellIndex => $part) {
                $cells[$cellIndex][] = $part;
            }
        }

        return $cells;
    }

    /**
     * @return list<string>|null
     */
    private function splitGridTableContentLine(string $line, int $columnCount): ?array
    {
        $line = rtrim($this->expandTabsToSpaces($line));
        $line = preg_replace('/^ {0,3}/', '', $line, 1) ?? $line;
        if ($line === '' || $line[0] !== '|' || $line[strlen($line) - 1] !== '|') {
            return null;
        }

        $parts = explode('|', substr($line, 1, -1));
        if (count($parts) !== $columnCount) {
            return null;
        }

        return array_map(static fn (string $part): string => trim($part), $parts);
    }

    /**
     * @param list<string> $lines
     */
    private function mergeGridTableCellLines(array $lines): string
    {
        $cell = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cell = $cell === '' ? $line : $cell . "\n" . $line;
        }

        return $cell;
    }

    /**
     * @param list<string> $lines
     */
    private function gridTableCellNeedsBlockParsing(array $lines): bool
    {
        $sawContent = false;
        $sawBlankAfterContent = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($sawContent) {
                    $sawBlankAfterContent = true;
                }
                continue;
            }

            if (preg_match('/^(?:#{1,6}\s+|[-*+]\s+|\d{1,9}[.)]\s+)/', $line) === 1) {
                return true;
            }

            if ($sawBlankAfterContent) {
                return true;
            }

            $sawContent = true;
        }

        return false;
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>
     */
    private function readGridTableCellBlocks(array $lines): array
    {
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim($lines[count($lines) - 1]) === '') {
            array_pop($lines);
        }

        if ($lines === []) {
            return [];
        }

        return $this->read(implode("\n", $lines))->children;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadSimpleTable(array $lines, int &$index): ?AstNode
    {
        if (!isset($lines[$index + 1])) {
            return null;
        }

        $multilineTable = $this->tryReadMultilineSimpleTableWithHeader($lines, $index);
        if ($multilineTable !== null) {
            return $multilineTable;
        }

        $headerColumns = $this->parseSimpleTableDelimiter($lines[$index + 1]);
        if ($headerColumns !== null) {
            return $this->readSimpleTableWithHeader($lines, $index, $headerColumns);
        }

        $bodyColumns = $this->parseSimpleTableDelimiter($lines[$index]);
        if ($bodyColumns !== null) {
            return $this->readSimpleTableWithoutHeader($lines, $index, $bodyColumns);
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadMultilineSimpleTableWithHeader(array $lines, int &$index): ?AstNode
    {
        if (!$this->isSimpleTableBoundary($lines[$index] ?? '')) {
            return null;
        }

        $cursor = $index + 1;
        $count = count($lines);
        $headerLines = [];
        $columns = null;
        while ($cursor < $count) {
            if (trim($lines[$cursor]) === '' || $this->isSimpleTableBoundary($lines[$cursor])) {
                return null;
            }

            $columns = $this->parseSimpleTableDelimiter($lines[$cursor]);
            if ($columns !== null) {
                break;
            }

            $headerLines[] = $lines[$cursor];
            $cursor++;
        }

        if ($columns === null || $headerLines === []) {
            return null;
        }

        [$bodyRows, $closingIndex] = $this->readSimpleTableRowsUntilBoundary($lines, $cursor + 1, $columns);
        if ($closingIndex === null || $bodyRows === []) {
            return null;
        }

        [$caption, $next] = $this->readTableCaption($lines, $closingIndex + 1);
        $index = $next - 1;

        return $this->buildSimpleTable(
            $this->mergeSimpleTableLines($headerLines, $columns),
            $bodyRows,
            $this->detectMultilineSimpleTableAlignments($headerLines, $columns),
            $caption,
            $this->detectSimpleTableWidths($columns)
        );
    }

    /**
     * @param list<string> $lines
     * @param list<array{start:int, length:int}> $columns
     */
    private function readSimpleTableWithHeader(array $lines, int &$index, array $columns): ?AstNode
    {
        $headerLine = $lines[$index];
        if (trim($headerLine) === '' || $this->parseSimpleTableDelimiter($headerLine) !== null) {
            return null;
        }

        $cursor = $index + 2;
        $bodyRows = [];
        $count = count($lines);
        while ($cursor < $count && trim($lines[$cursor]) !== '') {
            if ($this->parseSimpleTableDelimiter($lines[$cursor]) !== null) {
                break;
            }

            $bodyRows[] = $this->splitSimpleTableLine($lines[$cursor], $columns);
            $cursor++;
        }

        if ($bodyRows === []) {
            return null;
        }

        [$caption, $next] = $this->readTableCaption($lines, $cursor);
        $index = $next - 1;

        return $this->buildSimpleTable(
            $this->splitSimpleTableLine($headerLine, $columns),
            $bodyRows,
            $this->detectSimpleTableAlignments($headerLine, $columns),
            $caption
        );
    }

    /**
     * @param list<string> $lines
     * @param list<array{start:int, length:int}> $columns
     */
    private function readSimpleTableWithoutHeader(array $lines, int &$index, array $columns): ?AstNode
    {
        [$bodyRows, $closingIndex] = $this->readSimpleTableRowsUntilColumnDelimiter($lines, $index + 1, $columns);
        if ($closingIndex === null || $bodyRows === []) {
            return null;
        }

        $alignments = $this->detectSimpleTableAlignments($lines[$index + 1], $columns);
        [$caption, $next] = $this->readTableCaption($lines, $closingIndex + 1);
        $index = $next - 1;

        return $this->buildSimpleTable(
            null,
            $bodyRows,
            $alignments,
            $caption,
            $this->detectSimpleTableWidths($columns)
        );
    }

    /**
     * @return list<array{start:int, length:int}>|null
     */
    private function parseSimpleTableDelimiter(string $line): ?array
    {
        if (preg_match('/^[ \t-]+$/', $line) !== 1) {
            return null;
        }

        preg_match_all('/-+/', $line, $matches, PREG_OFFSET_CAPTURE);
        $columns = [];
        foreach ($matches[0] as $match) {
            $marker = $match[0];
            if (strlen($marker) < 3) {
                return null;
            }

            $columns[] = [
                'start' => (int) $match[1],
                'length' => strlen($marker),
            ];
        }

        return count($columns) >= 2 ? $columns : null;
    }

    private function isSimpleTableBoundary(string $line): bool
    {
        return preg_match('/^[ \t]*-{3,}[ \t]*$/', $line) === 1;
    }

    /**
     * @param list<string> $lines
     * @param list<array{start:int, length:int}> $columns
     * @return array{0:list<list<string>>, 1:int|null}
     */
    private function readSimpleTableRowsUntilBoundary(array $lines, int $cursor, array $columns): array
    {
        $rows = [];
        $current = null;
        $count = count($lines);

        while ($cursor < $count) {
            if ($this->isSimpleTableBoundary($lines[$cursor])) {
                $this->flushSimpleTableRow($current, $rows);

                return [$rows, $cursor];
            }

            $this->appendSimpleTableBodyLine($lines[$cursor], $columns, $current, $rows);
            $cursor++;
        }

        return [$rows, null];
    }

    /**
     * @param list<string> $lines
     * @param list<array{start:int, length:int}> $columns
     * @return array{0:list<list<string>>, 1:int|null}
     */
    private function readSimpleTableRowsUntilColumnDelimiter(array $lines, int $cursor, array $columns): array
    {
        $rows = [];
        $current = null;
        $count = count($lines);

        while ($cursor < $count) {
            $closingColumns = $this->parseSimpleTableDelimiter($lines[$cursor]);
            if ($closingColumns !== null && count($closingColumns) === count($columns)) {
                $this->flushSimpleTableRow($current, $rows);

                return [$rows, $cursor];
            }

            $this->appendSimpleTableBodyLine($lines[$cursor], $columns, $current, $rows);
            $cursor++;
        }

        return [$rows, null];
    }

    /**
     * @param list<array{start:int, length:int}> $columns
     * @param list<string>|null $current
     * @param list<list<string>> $rows
     */
    private function appendSimpleTableBodyLine(string $line, array $columns, ?array &$current, array &$rows): void
    {
        if (trim($line) === '') {
            $this->flushSimpleTableRow($current, $rows);
            return;
        }

        $cells = $this->splitSimpleTableLine($line, $columns);
        $firstNonEmpty = $this->firstNonEmptyCellIndex($cells);
        if ($firstNonEmpty === null) {
            return;
        }

        if ($current !== null && $firstNonEmpty === 0) {
            $this->flushSimpleTableRow($current, $rows);
        }

        $current ??= array_fill(0, count($columns), '');
        $this->mergeSimpleTableCellsInto($current, $cells);
    }

    /**
     * @param list<string>|null $current
     * @param list<list<string>> $rows
     */
    private function flushSimpleTableRow(?array &$current, array &$rows): void
    {
        if ($current === null) {
            return;
        }

        $rows[] = $current;
        $current = null;
    }

    /**
     * @param list<string> $cells
     */
    private function firstNonEmptyCellIndex(array $cells): ?int
    {
        foreach ($cells as $index => $cell) {
            if (trim($cell) !== '') {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<string> $current
     * @param list<string> $cells
     */
    private function mergeSimpleTableCellsInto(array &$current, array $cells): void
    {
        foreach ($cells as $index => $cell) {
            $cell = trim($cell);
            if ($cell === '') {
                continue;
            }

            $current[$index] = $current[$index] === '' ? $cell : $current[$index] . "\n" . $cell;
        }
    }

    /**
     * @param list<string> $lines
     * @param list<array{start:int, length:int}> $columns
     * @return list<string>
     */
    private function mergeSimpleTableLines(array $lines, array $columns): array
    {
        $merged = array_fill(0, count($columns), '');
        foreach ($lines as $line) {
            $this->mergeSimpleTableCellsInto($merged, $this->splitSimpleTableLine($line, $columns));
        }

        return $merged;
    }

    /**
     * @param list<array{start:int, length:int}> $columns
     * @return list<string>
     */
    private function splitSimpleTableLine(string $line, array $columns): array
    {
        $cells = [];
        $lineLength = strlen($line);
        foreach ($columns as $column) {
            $start = $column['start'];
            $length = $column['length'];
            $cell = $start < $lineLength ? substr($line, $start, $length) : '';
            $cells[] = trim($cell);
        }

        return $cells;
    }

    /**
     * @param array{start:int, length:int} $column
     */
    private function simpleTableSegment(string $line, array $column): string
    {
        $lineLength = strlen($line);
        $start = $column['start'];
        $segment = $start < $lineLength ? substr($line, $start, $column['length']) : '';

        return str_pad($segment, $column['length'], ' ');
    }

    /**
     * @param list<array{start:int, length:int}> $columns
     * @return list<string>
     */
    private function detectSimpleTableAlignments(string $sampleLine, array $columns): array
    {
        $alignments = [];
        $lineLength = strlen($sampleLine);
        foreach ($columns as $column) {
            $start = $column['start'];
            if ($start >= $lineLength) {
                $alignments[] = 'default';
                continue;
            }

            $segment = substr($sampleLine, $start, $column['length']);
            if (trim($segment) === '') {
                $alignments[] = 'default';
                continue;
            }

            $leftPad = strlen($segment) - strlen(ltrim($segment, ' '));
            $rightPad = strlen($segment) - strlen(rtrim($segment, ' '));
            $alignments[] = match (true) {
                $leftPad > 0 && $rightPad === 0 => 'right',
                $leftPad === 0 && $rightPad > 0 => 'left',
                $leftPad > 0 && $rightPad > 0 => 'center',
                default => 'default',
            };
        }

        return $alignments;
    }

    /**
     * @param list<string> $headerLines
     * @param list<array{start:int, length:int}> $columns
     * @return list<string>
     */
    private function detectMultilineSimpleTableAlignments(array $headerLines, array $columns): array
    {
        $alignments = [];
        foreach ($columns as $column) {
            $alignment = 'default';
            foreach ($headerLines as $line) {
                $segment = $this->simpleTableSegment($line, $column);
                if (trim($segment) === '') {
                    continue;
                }

                $leftPad = strlen($segment) - strlen(ltrim($segment, ' '));
                $rightPad = strlen($segment) - strlen(rtrim($segment, ' '));
                $alignment = match (true) {
                    $leftPad > 0 && $rightPad === 0 => 'right',
                    $leftPad === 0 && $rightPad > 0 => 'left',
                    $leftPad > 0 && $rightPad > 0 => 'center',
                    default => 'default',
                };
                break;
            }

            $alignments[] = $alignment;
        }

        return $alignments;
    }

    /**
     * @param list<array{start:int, length:int}> $columns
     * @return list<float>|null
     */
    private function detectSimpleTableWidths(array $columns): ?array
    {
        $maxWidth = 0;
        foreach ($columns as $column) {
            $maxWidth = max($maxWidth, $column['length']);
        }
        if ($maxWidth < 20) {
            return null;
        }

        return array_map(
            static fn (array $column): float => ($column['length'] + 1) / 80,
            $columns
        );
    }

    /**
     * @param list<string>|null $headerCells
     * @param list<list<string>> $bodyRows
     * @param list<string> $alignments
     * @param list<float>|null $widths
     */
    private function buildSimpleTable(?array $headerCells, array $bodyRows, array $alignments, string $caption, ?array $widths = null): AstNode
    {
        $children = [];
        if ($headerCells !== null) {
            $children[] = new AstNode('table_head', [], [
                $this->buildTableRow($headerCells, true),
            ]);
        } else {
            $children[] = new AstNode('table_head');
        }

        $bodyChildren = [];
        foreach ($bodyRows as $row) {
            $bodyChildren[] = $this->buildTableRow($row, false);
        }
        $children[] = new AstNode('table_body', [], $bodyChildren);

        $attrs = [
            'caption' => $caption,
            'alignments' => $alignments,
        ];
        if ($caption !== '') {
            $attrs['captionInlines'] = $this->parseInlines($caption);
        }
        if ($widths !== null) {
            $attrs['widths'] = $widths;
        }

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $children));
    }

    /**
     * @param AstNode|null $headerRow
     * @param list<AstNode> $bodyRows
     * @param list<string> $alignments
     * @param list<float>|null $widths
     */
    private function buildGridTable(?AstNode $headerRow, array $bodyRows, array $alignments, string $caption, ?array $widths): AstNode
    {
        return $this->buildGridTableRows($headerRow instanceof AstNode ? [$headerRow] : [], $bodyRows, $alignments, $caption, $widths);
    }

    /**
     * @param list<AstNode> $headerRows
     * @param list<AstNode> $bodyRows
     * @param list<string> $alignments
     * @param list<float>|null $widths
     */
    private function buildGridTableRows(array $headerRows, array $bodyRows, array $alignments, string $caption, ?array $widths): AstNode
    {
        $children = [];
        if ($headerRows !== []) {
            $children[] = new AstNode('table_head', [], $headerRows);
        } else {
            $children[] = new AstNode('table_head');
        }

        $children[] = new AstNode('table_body', [], $bodyRows);

        $attrs = [
            'caption' => $caption,
            'alignments' => $alignments,
        ];
        if ($caption !== '') {
            $attrs['captionInlines'] = $this->parseInlines($caption);
        }
        if ($widths !== null) {
            $attrs['widths'] = $widths;
        }

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $children));
    }

    /**
     * @param list<string> $cellLines
     */
    private function buildGridTableCell(array $cellLines, bool $header, int $rowspan = 1, int $colspan = 1): AstNode
    {
        $text = $this->mergeGridTableCellLines($cellLines);
        $attrs = [
            'text' => $text,
            'header' => $header,
        ];
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }

        return new AstNode(
            'table_cell',
            $attrs,
            $this->gridTableCellNeedsBlockParsing($cellLines)
                ? $this->readGridTableCellBlocks($cellLines)
                : $this->parseInlines($text)
        );
    }

    /**
     * @param list<list<string>> $cellLinesByColumn
     */
    private function buildGridTableRow(array $cellLinesByColumn, bool $header): AstNode
    {
        $children = [];
        foreach ($cellLinesByColumn as $cellLines) {
            $children[] = $this->buildGridTableCell($cellLines, $header);
        }

        return new AstNode('table_row', ['header' => $header], $children);
    }

    /**
     * @param list<string> $lines
     * @return array{0:string, 1:int}
     */
    private function readTableCaption(array $lines, int $cursor): array
    {
        $count = count($lines);
        $captionCursor = $cursor;
        while ($captionCursor < $count && trim($lines[$captionCursor]) === '') {
            $captionCursor++;
        }

        if ($captionCursor < $count && preg_match('/^ {0,3}:\s*(.*)$/', $lines[$captionCursor], $m) === 1) {
            $caption = [trim($m[1])];
            $next = $captionCursor + 1;
            while (
                $next < $count
                && trim($lines[$next]) !== ''
                && $this->countIndentColumns($lines[$next]) >= 2
                && $this->parseSimpleTableDelimiter($lines[$next]) === null
                && !$this->isSimpleTableBoundary($lines[$next])
            ) {
                $caption[] = trim($lines[$next]);
                $next++;
            }

            return [implode("\n", $caption), $next];
        }

        return ['', $cursor];
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadPipeTable(array $lines, int &$index): ?AstNode
    {
        if (!isset($lines[$index + 1])) {
            return null;
        }

        $headerCells = $this->splitPipeTableRow($lines[$index]);
        if ($headerCells === null) {
            return null;
        }

        $delimiterCells = $this->splitPipeTableRow($lines[$index + 1]);
        if ($delimiterCells === null || count($delimiterCells) !== count($headerCells)) {
            return null;
        }

        $delimiter = $this->parsePipeTableDelimiter($delimiterCells);
        if ($delimiter === null) {
            return null;
        }

        $columnCount = count($delimiter['alignments']);
        $cursor = $index + 2;
        $bodyRows = [];
        $count = count($lines);
        while ($cursor < $count && trim($lines[$cursor]) !== '') {
            $row = $this->splitPipeTableRow($lines[$cursor]);
            if ($row === null) {
                break;
            }

            $bodyRows[] = $this->normalizePipeTableRow($row, $columnCount);
            $cursor++;
        }

        [$caption, $cursor] = $this->readTableCaption($lines, $cursor);

        $headerIsEmpty = true;
        foreach ($headerCells as $cell) {
            if (trim($cell) !== '') {
                $headerIsEmpty = false;
                break;
            }
        }

        $children = [];
        if (!$headerIsEmpty) {
            $children[] = new AstNode('table_head', [], [
                $this->buildTableRow($this->normalizePipeTableRow($headerCells, $columnCount), true),
            ]);
        } else {
            $children[] = new AstNode('table_head');
        }

        $bodyChildren = [];
        foreach ($bodyRows as $row) {
            $bodyChildren[] = $this->buildTableRow($row, false);
        }
        $children[] = new AstNode('table_body', [], $bodyChildren);

        $attrs = [
            'caption' => $caption,
            'alignments' => $delimiter['alignments'],
        ];
        if ($caption !== '') {
            $attrs['captionInlines'] = $this->parseInlines($caption);
        }
        if ($delimiter['widths'] !== null) {
            $attrs['widths'] = $delimiter['widths'];
        }

        $index = $cursor - 1;

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $children));
    }

    /**
     * @return list<string>|null
     */
    private function splitPipeTableRow(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '|')) {
            return null;
        }

        $cells = [];
        $cell = '';
        $codeFenceLength = null;
        $length = strlen($line);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $line[$offset];
            if ($char === '\\') {
                $cell .= $char;
                if ($offset + 1 < $length) {
                    $offset++;
                    $cell .= $line[$offset];
                }
                continue;
            }

            if ($char === '`') {
                $tickCount = $this->countBackticks($line, $offset);
                if ($codeFenceLength === null) {
                    $codeFenceLength = $tickCount;
                } elseif ($tickCount === $codeFenceLength) {
                    $codeFenceLength = null;
                }
                $cell .= str_repeat('`', $tickCount);
                $offset += $tickCount - 1;
                continue;
            }

            if ($char === '|' && $codeFenceLength === null) {
                $cells[] = $cell;
                $cell = '';
                continue;
            }

            $cell .= $char;
        }
        $cells[] = $cell;

        if ($line[0] === '|') {
            array_shift($cells);
        }
        if ($line !== '' && $line[strlen($line) - 1] === '|') {
            array_pop($cells);
        }

        return $cells === [] ? null : $cells;
    }

    /**
     * @param list<string> $cells
     * @return array{alignments:list<string>, widths:list<float>|null}|null
     */
    private function parsePipeTableDelimiter(array $cells): ?array
    {
        $alignments = [];
        $widthParts = [];
        foreach ($cells as $cell) {
            $marker = trim($cell);
            if (preg_match('/^(:?)(-+)(:?)$/', $marker, $m) !== 1) {
                return null;
            }

            $alignments[] = match (true) {
                $m[1] === ':' && $m[3] === ':' => 'center',
                $m[1] === ':' => 'left',
                $m[3] === ':' => 'right',
                default => 'default',
            };
            $widthParts[] = strlen($m[2]);
        }

        $widths = null;
        if (max($widthParts) >= 20) {
            $total = array_sum($widthParts);
            $widths = array_map(
                static fn (int $width): float => $total === 0 ? 0.0 : $width / $total,
                $widthParts
            );
        }

        return [
            'alignments' => $alignments,
            'widths' => $widths,
        ];
    }

    /**
     * @param list<string> $cells
     * @return list<string>
     */
    private function normalizePipeTableRow(array $cells, int $columnCount): array
    {
        $cells = array_slice($cells, 0, $columnCount);
        while (count($cells) < $columnCount) {
            $cells[] = '';
        }

        return array_map(static fn (string $cell): string => trim($cell), $cells);
    }

    /**
     * @param list<string> $cells
     */
    private function buildTableRow(array $cells, bool $header): AstNode
    {
        $children = [];
        foreach ($cells as $cell) {
            $children[] = new AstNode(
                'table_cell',
                ['text' => $cell, 'header' => $header],
                $this->parseInlines($cell)
            );
        }

        return new AstNode('table_row', ['header' => $header], $children);
    }

    /**
     * @param list<string> $lines
     */
    private function readHtmlCommentBlock(array $lines, int &$index): AstNode
    {
        $content = [];
        $cursor = $index;
        $count = count($lines);
        while ($cursor < $count) {
            $content[] = $this->normalizeRawHtmlLine($lines[$cursor]);
            if (str_contains($lines[$cursor], '-->')) {
                break;
            }
            $cursor++;
        }

        $index = min($cursor, $count - 1);

        return new AstNode('raw_html', ['html' => implode("\n", $content)]);
    }

    /**
     * @param list<string> $lines
     */
    private function readRawHtmlUntilClosingTag(array $lines, int &$index, string $tag, bool $interpretTableCells = false): AstNode
    {
        $content = [];
        $cursor = $index;
        $count = count($lines);
        $closingPattern = '/<\/' . preg_quote($tag, '/') . '\s*>/i';

        while ($cursor < $count) {
            $line = $this->normalizeRawHtmlLine($lines[$cursor]);
            $content[] = $interpretTableCells ? $this->renderMarkdownInTableCells($line) : $line;
            if (preg_match($closingPattern, $line) === 1) {
                break;
            }
            $cursor++;
        }

        $index = min($cursor, $count - 1);

        return new AstNode('raw_html', ['html' => implode("\n", $content)]);
    }

    private function normalizeRawHtmlLine(string $line): string
    {
        return rtrim($this->expandTabsToSpaces($line));
    }

    private function renderMarkdownInTableCells(string $line): string
    {
        return preg_replace_callback(
            '/(<t[dh](?:\s+[^>]*)?>)(.*?)(<\/t[dh]>)/i',
            function (array $matches): string {
                return $matches[1] . $this->renderInlineHtml($this->parseInlines($matches[2])) . $matches[3];
            },
            $line
        ) ?? $line;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlineHtml(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= match ($node->type) {
                'text' => $this->escapeHtml((string) $node->attr('text', '')),
                'emph' => '<em>' . $this->renderInlineHtml($node->children) . '</em>',
                'strong' => '<strong>' . $this->renderInlineHtml($node->children) . '</strong>',
                'small_caps' => '<span style="font-variant:small-caps">'
                    . $this->renderInlineHtml($node->children) . '</span>',
                'underline' => '<u>' . $this->renderInlineHtml($node->children) . '</u>',
                'strikeout' => '<del>' . $this->renderInlineHtml($node->children) . '</del>',
                'superscript' => '<sup>' . $this->renderInlineHtml($node->children) . '</sup>',
                'subscript' => '<sub>' . $this->renderInlineHtml($node->children) . '</sub>',
                'softbreak' => "\n",
                'linebreak' => '<br/>',
                'span' => '<span' . $this->renderInlineSpanAttributesHtml($node) . '>'
                    . $this->renderInlineHtml($node->children) . '</span>',
                'quoted' => $this->renderQuotedInlineHtml($node),
                'math' => $this->renderMathInlineHtml($node),
                'raw_tex' => '<span class="pandoc-raw-tex">' . $this->escapeHtml((string) $node->attr('tex', '')) . '</span>',
                'code' => '<code>' . $this->escapeHtml((string) $node->attr('text', '')) . '</code>',
                'link' => '<a' . $this->renderLinkAttributesHtml($node) . '>'
                    . $this->renderInlineHtml($node->children) . '</a>',
                default => $this->renderInlineHtml($node->children),
            };
        }

        return $html;
    }

    private function renderInlineSpanAttributesHtml(AstNode $node): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes) || $htmlAttributes === []) {
            return '';
        }

        $attrs = '';
        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if (!$this->isAllowedInlineHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->escapeHtml((string) $value) . '"';
        }

        return $attrs;
    }

    private function isAllowedInlineHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || in_array($name, ['cite', 'class', 'dir', 'id', 'lang', 'title'], true);
    }

    private function renderLinkAttributesHtml(AstNode $node): string
    {
        $attrs = ' href="' . $this->escapeHtml((string) $node->attr('url', '')) . '"';
        $title = (string) $node->attr('title', '');
        if ($title !== '') {
            $attrs .= ' title="' . $this->escapeHtml($title) . '"';
        }

        return $attrs;
    }

    private function renderMathInlineHtml(AstNode $node): string
    {
        $open = $node->attr('display') === true ? '\\[' : '\\(';
        $close = $node->attr('display') === true ? '\\]' : '\\)';
        $class = $node->attr('display') === true ? 'display' : 'inline';

        return '<span class="math ' . $class . '">'
            . $this->escapeHtml($open . (string) $node->attr('text', '') . $close)
            . '</span>';
    }

    private function renderQuotedInlineHtml(AstNode $node): string
    {
        if ($node->attr('kind') === 'single') {
            return "\u{2018}" . $this->renderInlineHtml($node->children) . "\u{2019}";
        }

        return "\u{201C}" . $this->renderInlineHtml($node->children) . "\u{201D}";
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function isBlockQuoteLine(string $line): bool
    {
        return preg_match('/^ {0,3}>/', $line) === 1;
    }

    private function stripBlockQuoteMarker(string $line): string
    {
        return preg_replace('/^ {0,3}>[ \t]?/', '', $line, 1) ?? $line;
    }

    private function isHorizontalRule(string $line): bool
    {
        return preg_match('/^ {0,3}(?:\*[ \t]*){3,}$/', $line) === 1
            || preg_match('/^ {0,3}(?:-[ \t]*){3,}$/', $line) === 1
            || preg_match('/^ {0,3}(?:_[ \t]*){3,}$/', $line) === 1;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadListBlock(array $lines, int &$index): ?AstNode
    {
        $marker = $this->matchListMarker($lines[$index] ?? '', $index);
        if ($marker === null || $marker['indent'] > 3) {
            return null;
        }

        $result = $this->parseList($lines, $index, $marker);
        if ($result === null) {
            return null;
        }

        $index = $result['next'] - 1;

        return $result['node'];
    }

    /**
     * @param list<string> $lines
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null} $firstMarker
     * @return array{node: AstNode, next: int}|null
     */
    private function parseList(array $lines, int $cursor, array $firstMarker): ?array
    {
        $count = count($lines);
        $items = [];
        $start = null;
        $listLoose = false;
        $baseIndent = $firstMarker['indent'];
        $ordered = $firstMarker['ordered'];
        $style = $firstMarker['style'];
        $delimiter = $firstMarker['delimiter'];

        while ($cursor < $count) {
            $marker = $this->matchListMarker($lines[$cursor], $cursor);
            if (!$this->isSameListMarker($marker, $baseIndent, $ordered, $style, $delimiter)) {
                break;
            }

            $start ??= $marker['start'];
            $item = $this->parseListItem($lines, $cursor, $marker, $ordered, $style, $delimiter);
            $items[] = $item;
            $cursor = $item['next'];
            $listLoose = $listLoose || $item['loose'];

            $blankCursor = $cursor;
            while ($blankCursor < $count && trim($lines[$blankCursor]) === '') {
                $blankCursor++;
            }

            if ($blankCursor > $cursor) {
                $nextMarker = $blankCursor < $count ? $this->matchListMarker($lines[$blankCursor], $blankCursor) : null;
                if ($this->isSameListMarker($nextMarker, $baseIndent, $ordered, $style, $delimiter)) {
                    $listLoose = true;
                    $cursor = $blankCursor;
                    continue;
                }
            }
        }

        if ($items === []) {
            return null;
        }

        $children = [];
        foreach ($items as $item) {
            $children[] = $this->buildListItem($item, $listLoose || $item['loose']);
        }

        $attrs = ['loose' => $listLoose];
        if ($ordered) {
            $attrs['start'] = $start ?? 1;
            $attrs['style'] = $style ?? 'decimal';
            $attrs['delimiter'] = $delimiter ?? 'period';
        } elseif ($this->allListItemsAreTasks($children)) {
            $attrs['taskList'] = true;
        }

        return [
            'node' => new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $children),
            'next' => $cursor,
        ];
    }

    /**
     * @param list<string> $lines
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null} $marker
     * @return array{parts:list<array{type:string, text:string}|AstNode>, next:int, loose:bool, text:string, number:int|null, taskChecked:bool|null}
     */
    private function parseListItem(
        array $lines,
        int $cursor,
        array $marker,
        bool $ordered,
        ?string $style,
        ?string $delimiter
    ): array {
        $count = count($lines);
        $baseIndent = $marker['indent'];
        $contentIndent = $marker['contentIndent'];
        $parts = [];
        $paragraph = [];
        $loose = false;
        $firstText = trim($marker['text']);
        $taskChecked = null;

        if ($firstText !== '' && $this->isListItemBlockHtmlStart($firstText)) {
            return $this->parseBlockHtmlListItem($lines, $cursor, $marker);
        }

        if ($firstText !== '' && $this->isListItemInitialCodeBlock($marker)) {
            [$codeBlock, $cursor] = $this->readListItemInitialCodeBlock($lines, $cursor + 1, $contentIndent, $firstText);
            $parts[] = $codeBlock;
        } elseif ($firstText !== '') {
            $task = $this->stripTaskListMarker($firstText);
            if ($task !== null) {
                $taskChecked = $task['checked'];
                $firstText = $task['text'];
            }
            $paragraph[] = $firstText;
            $cursor++;
        } else {
            $cursor++;
        }

        while ($cursor < $count) {
            $line = $lines[$cursor];
            if (trim($line) === '') {
                $next = $cursor;
                while ($next < $count && trim($lines[$next]) === '') {
                    $next++;
                }

                if ($next >= $count) {
                    break;
                }

                if ($this->isHorizontalRule($lines[$next])) {
                    break;
                }

                $nextMarker = $this->matchListMarker($lines[$next], $next);
                if ($nextMarker !== null && $nextMarker['indent'] <= $baseIndent) {
                    break;
                }

                $nextIndent = $this->countIndentColumns($lines[$next]);
                if ($this->isNestedListMarker($nextMarker, $baseIndent, $contentIndent) || $nextIndent >= $contentIndent) {
                    $this->flushListItemParagraph($paragraph, $parts);
                    $loose = true;
                    $cursor = $next;
                    continue;
                }

                break;
            }

            if ($this->isHorizontalRule($line)) {
                break;
            }

            $lineMarker = $this->matchListMarker($line, $cursor);
            if ($lineMarker !== null) {
                if ($this->isSameListMarker($lineMarker, $baseIndent, $ordered, $style, $delimiter)) {
                    break;
                }

                if (!$this->isNestedListMarker($lineMarker, $baseIndent, $contentIndent)) {
                    break;
                }

                $this->flushListItemParagraph($paragraph, $parts);
                $nested = $this->parseList($lines, $cursor, $lineMarker);
                if ($nested === null) {
                    break;
                }

                $parts[] = $nested['node'];
                $cursor = $nested['next'];
                continue;
            }

            $indent = $this->countIndentColumns($line);
            if ($indent >= $contentIndent) {
                $paragraph[] = trim($this->stripIndentColumns($line, $contentIndent));
                $cursor++;
                continue;
            }

            if ($paragraph !== [] && $this->isLazyListContinuation($line)) {
                $paragraph[] = trim($line);
                $cursor++;
                continue;
            }

            break;
        }

        $this->flushListItemParagraph($paragraph, $parts);

        return [
            'parts' => $parts,
            'next' => $cursor,
            'loose' => $loose,
            'text' => $firstText,
            'number' => $marker['start'],
            'taskChecked' => $taskChecked,
        ];
    }

    /**
     * @param list<string> $lines
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null} $marker
     * @return array{parts:list<array{type:string, text:string}|AstNode>, next:int, loose:bool, text:string, number:int|null, taskChecked:bool|null}
     */
    private function parseBlockHtmlListItem(array $lines, int $cursor, array $marker): array
    {
        $count = count($lines);
        $baseIndent = $marker['indent'];
        $contentIndent = $marker['contentIndent'];
        $content = [trim($marker['text'])];
        $loose = false;
        $cursor++;

        while ($cursor < $count) {
            $line = $lines[$cursor];
            if (trim($line) === '') {
                $next = $cursor;
                while ($next < $count && trim($lines[$next]) === '') {
                    $next++;
                }

                if ($next >= $count) {
                    break;
                }

                $nextMarker = $this->matchListMarker($lines[$next], $next);
                if ($nextMarker !== null && $nextMarker['indent'] <= $baseIndent) {
                    break;
                }

                if ($this->countIndentColumns($lines[$next]) >= $contentIndent) {
                    $content[] = '';
                    $loose = true;
                    $cursor = $next;
                    continue;
                }

                break;
            }

            $lineMarker = $this->matchListMarker($line, $cursor);
            if ($lineMarker !== null && $lineMarker['indent'] <= $baseIndent) {
                break;
            }

            if ($this->countIndentColumns($line) < $contentIndent) {
                break;
            }

            $content[] = rtrim($this->stripIndentColumns($line, $contentIndent));
            $cursor++;
        }

        while ($content !== [] && trim($content[array_key_last($content)]) === '') {
            array_pop($content);
        }

        return [
            'parts' => $this->read(implode("\n", $content))->children,
            'next' => $cursor,
            'loose' => $loose,
            'text' => trim($marker['text']),
            'number' => $marker['start'],
            'taskChecked' => null,
        ];
    }

    private function isListItemBlockHtmlStart(string $text): bool
    {
        return preg_match('/^<(?:div|button)(?:\s+[^>]*)?>/i', $text) === 1;
    }

    /**
     * @param array{padding:int} $marker
     */
    private function isListItemInitialCodeBlock(array $marker): bool
    {
        return $marker['padding'] >= 5;
    }

    /**
     * @param list<string> $lines
     * @return array{0: AstNode, 1: int}
     */
    private function readListItemInitialCodeBlock(array $lines, int $cursor, int $contentIndent, string $firstText): array
    {
        $content = [rtrim($firstText)];
        $count = count($lines);

        while ($cursor < $count) {
            $line = $lines[$cursor];
            if (trim($line) === '') {
                $next = $cursor;
                while ($next < $count && trim($lines[$next]) === '') {
                    $next++;
                }

                if (
                    $next < $count
                    && $this->countIndentColumns($lines[$next]) >= $contentIndent
                    && $this->matchListMarker($lines[$next], $next) === null
                ) {
                    $content[] = '';
                    $cursor++;
                    continue;
                }

                break;
            }

            if ($this->countIndentColumns($line) < $contentIndent) {
                break;
            }

            $content[] = rtrim($this->stripIndentColumns($line, $contentIndent));
            $cursor++;
        }

        while ($content !== [] && end($content) === '') {
            array_pop($content);
        }

        return [
            new AstNode('code_block', [
                'classes' => [],
                'attributes' => [],
                'text' => implode("\n", $content),
            ]),
            $cursor,
        ];
    }

    private function isLazyListContinuation(string $line): bool
    {
        return trim($line) !== ''
            && !$this->isHorizontalRule($line)
            && !$this->isBlockQuoteLine($line)
            && !$this->isDefinitionMarker($line)
            && preg_match('/^(#{1,6})\s+/', $line) !== 1;
    }

    /**
     * @param list<string> $paragraph
     * @param list<array{type:string, text:string}|AstNode> $parts
     */
    private function flushListItemParagraph(array &$paragraph, array &$parts): void
    {
        if ($paragraph === []) {
            return;
        }

        $parts[] = ['type' => 'paragraph', 'text' => implode(' ', $paragraph)];
        $paragraph = [];
    }

    /**
     * @param array{parts:list<array{type:string, text:string}|AstNode>, next:int, loose:bool, text:string, number:int|null, taskChecked:bool|null} $item
     */
    private function buildListItem(array $item, bool $loose): AstNode
    {
        $paragraphCount = 0;
        foreach ($item['parts'] as $part) {
            if (is_array($part) && ($part['type'] ?? null) === 'paragraph') {
                $paragraphCount++;
            }
        }

        $forceParagraphBlocks = $loose || $paragraphCount > 1;
        $children = [];
        foreach ($item['parts'] as $part) {
            if ($part instanceof AstNode) {
                $children[] = $part;
                continue;
            }

            $text = $part['text'];
            if ($forceParagraphBlocks) {
                $children[] = new AstNode('paragraph', ['text' => $text], $this->parseInlines($text));
                continue;
            }

            foreach ($this->parseInlines($text) as $inline) {
                $children[] = $inline;
            }
        }

        $attrs = ['text' => $item['text'], 'loose' => $forceParagraphBlocks];
        if ($item['number'] !== null) {
            $attrs['number'] = $item['number'];
        }
        if ($item['taskChecked'] !== null) {
            $attrs['taskChecked'] = $item['taskChecked'];
        }

        return new AstNode('list_item', $attrs, $children);
    }

    /**
     * @return array{checked:bool, text:string}|null
     */
    private function stripTaskListMarker(string $text): ?array
    {
        if (preg_match('/^\[([ xX])\](?:[ \t]+|$)(.*)$/s', $text, $m) !== 1) {
            return null;
        }

        return [
            'checked' => strtolower($m[1]) === 'x',
            'text' => ltrim($m[2]),
        ];
    }

    /**
     * @param list<AstNode> $children
     */
    private function allListItemsAreTasks(array $children): bool
    {
        if ($children === []) {
            return false;
        }

        foreach ($children as $child) {
            if ($child->type !== 'list_item' || !is_bool($child->attr('taskChecked', null))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null}|null
     */
    private function matchListMarker(string $line, ?int $lineIndex = null): ?array
    {
        if ($this->isHorizontalRule($line)) {
            return null;
        }

        $expanded = $this->expandTabsToSpaces($line);
        $example = $this->matchNumberedExampleMarker($expanded);
        if ($example !== null) {
            return [
                'indent' => $example['indent'],
                'ordered' => true,
                'start' => $lineIndex !== null ? ($this->exampleNumbersByLine[$lineIndex] ?? 1) : 1,
                'text' => $example['text'],
                'contentIndent' => $example['contentIndent'],
                'padding' => $example['padding'],
                'style' => 'example',
                'delimiter' => 'two_parens',
            ];
        }

        if (preg_match('/^( *)([-*+])( +)(.*)$/', $expanded, $m) === 1) {
            return [
                'indent' => strlen($m[1]),
                'ordered' => false,
                'start' => null,
                'text' => $m[4],
                'contentIndent' => strlen($m[1]) + 1 + strlen($m[3]),
                'padding' => strlen($m[3]),
                'style' => null,
                'delimiter' => null,
            ];
        }

        if (preg_match('/^( *)#([.)])( +)(.*)$/', $expanded, $m) === 1) {
            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => 1,
                'text' => $m[4],
                'contentIndent' => strlen($m[1]) + 2 + strlen($m[3]),
                'padding' => strlen($m[3]),
                'style' => 'default',
                'delimiter' => 'default',
            ];
        }

        if (preg_match('/^( *)\(([0-9]{1,9}|[A-Za-z]+)\)( +)(.*)$/', $expanded, $m) === 1) {
            $ordinal = $this->parseOrderedMarkerOrdinal($m[2], 'two_parens', strlen($m[3]), strlen($m[1]));
            if ($ordinal === null) {
                return null;
            }

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => $ordinal['start'],
                'text' => $m[4],
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 2 + strlen($m[3]),
                'padding' => strlen($m[3]),
                'style' => $ordinal['style'],
                'delimiter' => 'two_parens',
            ];
        }

        if (preg_match('/^( *)(\d{1,9})([.)])( +)(.*)$/', $expanded, $m) === 1) {
            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => (int) $m[2],
                'text' => $m[5],
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 1 + strlen($m[4]),
                'padding' => strlen($m[4]),
                'style' => 'decimal',
                'delimiter' => $m[3] === ')' ? 'one_paren' : 'period',
            ];
        }

        if (preg_match('/^( *)([A-Za-z]+)([.)])( +)(.*)$/', $expanded, $m) === 1) {
            $delimiter = $m[3] === ')' ? 'one_paren' : 'period';
            $ordinal = $this->parseOrderedMarkerOrdinal($m[2], $delimiter, strlen($m[4]), strlen($m[1]));
            if ($ordinal === null) {
                return null;
            }

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => $ordinal['start'],
                'text' => $m[5],
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 1 + strlen($m[4]),
                'padding' => strlen($m[4]),
                'style' => $ordinal['style'],
                'delimiter' => $delimiter,
            ];
        }

        return null;
    }

    /**
     * @return array{indent:int, label:string, text:string, contentIndent:int, padding:int}|null
     */
    private function matchNumberedExampleMarker(string $line): ?array
    {
        $expanded = $this->expandTabsToSpaces($line);
        if (preg_match('/^( *)\(@([A-Za-z0-9_-]*)\)( +)(.*)$/', $expanded, $m) !== 1) {
            return null;
        }

        return [
            'indent' => strlen($m[1]),
            'label' => $m[2],
            'text' => $m[4],
            'contentIndent' => strlen($m[1]) + strlen($m[2]) + 3 + strlen($m[3]),
            'padding' => strlen($m[3]),
        ];
    }

    private function isSameListMarker(
        ?array $marker,
        int $baseIndent,
        bool $ordered,
        ?string $style,
        ?string $delimiter
    ): bool {
        return $marker !== null
            && $marker['indent'] === $baseIndent
            && $marker['ordered'] === $ordered
            && $marker['style'] === $style
            && $marker['delimiter'] === $delimiter;
    }

    private function isNestedListMarker(?array $marker, int $baseIndent, int $contentIndent): bool
    {
        return $marker !== null
            && $marker['indent'] > $baseIndent
            && ($marker['indent'] >= $contentIndent || $marker['indent'] - $baseIndent >= 2);
    }

    /**
     * @return array{start:int, style:string}|null
     */
    private function parseOrderedMarkerOrdinal(string $token, string $delimiter, int $spacesAfterMarker, int $indent): ?array
    {
        if (ctype_digit($token)) {
            return ['start' => (int) $token, 'style' => 'decimal'];
        }

        if (!ctype_alpha($token)) {
            return null;
        }

        $roman = $delimiter === 'period' ? $this->romanToInt($token) : null;
        if ($roman !== null && (strlen($token) > 1 || $spacesAfterMarker >= 2 || $indent > 0)) {
            return [
                'start' => $roman,
                'style' => ctype_upper($token) ? 'upper_roman' : 'lower_roman',
            ];
        }

        if (strlen($token) === 1 && ($spacesAfterMarker >= 2 || $indent > 0)) {
            $start = ord(strtolower($token)) - ord('a') + 1;

            return [
                'start' => $start,
                'style' => ctype_upper($token) ? 'upper_alpha' : 'lower_alpha',
            ];
        }

        return null;
    }

    private function romanToInt(string $token): ?int
    {
        $roman = strtoupper($token);
        if (preg_match('/^(?=[MDCLXVI]+$)M{0,4}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/', $roman) !== 1) {
            return null;
        }

        $values = [
            'I' => 1,
            'V' => 5,
            'X' => 10,
            'L' => 50,
            'C' => 100,
            'D' => 500,
            'M' => 1000,
        ];
        $total = 0;
        $previous = 0;
        for ($offset = strlen($roman) - 1; $offset >= 0; $offset--) {
            $value = $values[$roman[$offset]];
            if ($value < $previous) {
                $total -= $value;
            } else {
                $total += $value;
                $previous = $value;
            }
        }

        return $total;
    }

    private function countIndentColumns(string $line): int
    {
        return strspn($this->expandTabsToSpaces($line), ' ');
    }

    private function stripIndentColumns(string $line, int $columns): string
    {
        return substr($this->expandTabsToSpaces($line), $columns);
    }

    private function expandTabsToSpaces(string $line): string
    {
        $expanded = '';
        $column = 0;
        $length = strlen($line);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($line[$offset] === "\t") {
                $spaces = 4 - ($column % 4);
                $expanded .= str_repeat(' ', $spaces);
                $column += $spaces;
                continue;
            }

            $expanded .= $line[$offset];
            $column++;
        }

        return $expanded;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadIndentedCodeBlock(array $lines, int &$index): ?AstNode
    {
        if (!$this->isIndentedCodeLine($lines[$index] ?? '')) {
            return null;
        }

        $content = [];
        $cursor = $index;
        $count = count($lines);
        while ($cursor < $count) {
            $line = $lines[$cursor];
            if ($this->isIndentedCodeLine($line)) {
                $content[] = $this->stripCodeIndent($line);
                $cursor++;
                continue;
            }

            if (trim($line) === '') {
                $content[] = '';
                $cursor++;
                continue;
            }

            break;
        }

        while ($content !== [] && end($content) === '') {
            array_pop($content);
        }

        $index = $cursor - 1;

        return new AstNode('code_block', [
            'classes' => [],
            'attributes' => [],
            'text' => implode("\n", $content),
        ]);
    }

    private function isIndentedCodeLine(string $line): bool
    {
        return str_starts_with($line, '    ') || str_starts_with($line, "\t");
    }

    private function stripCodeIndent(string $line): string
    {
        if (str_starts_with($line, "\t")) {
            return $this->expandTabs(substr($line, 1));
        }

        return $this->expandTabs(substr($line, 4));
    }

    private function expandTabs(string $line): string
    {
        return str_replace("\t", '    ', $line);
    }

    private function isClosingCodeFence(string $line, string $fenceChar, int $fenceLength): bool
    {
        return preg_match('/^ {0,3}' . preg_quote($fenceChar, '/') . '{' . $fenceLength . ',}[ \t]*$/', $line) === 1;
    }

    private function stripFenceContentIndent(string $line, int $indent): string
    {
        if ($indent === 0) {
            return $line;
        }

        $spaces = min($indent, strspn($line, ' '));

        return substr($line, $spaces);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCodeInfo(string $info): array
    {
        $info = trim($info);
        if ($info === '') {
            return ['classes' => [], 'attributes' => []];
        }

        $classes = [];
        $attributes = [];
        $id = null;

        if (str_starts_with($info, '{') && str_ends_with($info, '}')) {
            $inside = trim(substr($info, 1, -1));
            $tokens = preg_split('/\s+/', $inside, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($tokens as $token) {
                if (str_starts_with($token, '.')) {
                    $classes[] = substr($token, 1);
                    continue;
                }
                if (str_starts_with($token, '#')) {
                    $id = substr($token, 1);
                    continue;
                }
                if (str_contains($token, '=')) {
                    [$name, $value] = explode('=', $token, 2);
                    $attributes[$name] = trim($value, "\"'");
                }
            }
        } else {
            $tokens = preg_split('/\s+/', $info, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($tokens !== []) {
                $classes[] = $tokens[0];
            }
        }

        $attrs = ['classes' => $classes, 'attributes' => $attributes];
        if ($id !== null) {
            $attrs['id'] = $id;
        }

        return $attrs;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadDefinitionList(array $lines, int &$index): ?AstNode
    {
        $cursor = $index;
        $count = count($lines);
        $items = [];

        while ($cursor < $count) {
            while ($items !== [] && $cursor < $count && trim($lines[$cursor]) === '') {
                $cursor++;
            }
            if ($cursor >= $count || !$this->canStartDefinitionTerm($lines[$cursor])) {
                break;
            }

            $termText = trim($lines[$cursor]);
            $definitionCursor = $cursor + 1;
            $looseFirstDefinition = false;
            if ($definitionCursor < $count && trim($lines[$definitionCursor]) === '') {
                $looseFirstDefinition = true;
                $definitionCursor++;
            }
            if ($definitionCursor >= $count || !$this->isDefinitionMarker($lines[$definitionCursor])) {
                break;
            }

            $cursor = $definitionCursor;
            $definitions = [];
            $looseDefinition = false;
            $blankBeforeLaterDefinition = false;
            while ($cursor < $count) {
                if (trim($lines[$cursor]) === '') {
                    $next = $cursor + 1;
                    if ($next < $count && $this->isDefinitionMarker($lines[$next])) {
                        $blankBeforeLaterDefinition = true;
                        $cursor = $next;
                        continue;
                    }
                    break;
                }

                if (!$this->isDefinitionMarker($lines[$cursor])) {
                    break;
                }

                $blankBeforeNextDefinition = false;
                $definitions[] = $this->readDefinition($lines, $cursor, $looseDefinition, $blankBeforeNextDefinition);
                $blankBeforeLaterDefinition = $blankBeforeLaterDefinition || $blankBeforeNextDefinition;
                $looseDefinition = false;
            }

            if ($looseFirstDefinition && $definitions !== []) {
                $indexes = $blankBeforeLaterDefinition ? array_keys($definitions) : [array_key_last($definitions)];
                foreach ($indexes as $definitionIndex) {
                    if ($definitionIndex === null) {
                        continue;
                    }
                    $definition = $definitions[$definitionIndex];
                    $definitions[$definitionIndex] = new AstNode(
                        $definition->type,
                        array_merge($definition->attrs, ['loose' => true]),
                        $definition->children
                    );
                }
            }

            $term = new AstNode('term', ['text' => $termText], $this->parseInlines($termText));
            $items[] = new AstNode('definition_item', ['term' => $termText], array_merge([$term], $definitions));
        }

        if ($items === []) {
            return null;
        }

        $index = $cursor - 1;

        return new AstNode('definition_list', [], $items);
    }

    private function canStartDefinitionTerm(string $line): bool
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return false;
        }
        if (preg_match('/^ {0,3}<\/?[A-Za-z][^>]*>/', $line) === 1) {
            return false;
        }

        return !preg_match('/^(#{1,6})\s+|^[-*+]\s+|^\d+[.)]\s+|^\s{0,4}[:~]/', $line);
    }

    private function isDefinitionMarker(string $line): bool
    {
        return $this->matchDefinitionMarker($line) !== null;
    }

    /**
     * @return array{marker:string, content:string}|null
     */
    private function matchDefinitionMarker(string $line): ?array
    {
        if (preg_match('/^\s{0,4}([:~])\s*(.*)$/', $line, $m) !== 1) {
            return null;
        }

        return ['marker' => $m[1], 'content' => $m[2]];
    }

    /**
     * @param list<string> $lines
     */
    private function readDefinition(array $lines, int &$cursor, bool $loose, bool &$blankBeforeNextDefinition): AstNode
    {
        $blankBeforeNextDefinition = false;
        $marker = $this->matchDefinitionMarker($lines[$cursor]);
        $blocks = $marker === null ? [] : $this->parseDefinitionBlocks(trim($marker['content']));
        $cursor++;
        $count = count($lines);

        while ($cursor < $count) {
            $line = $lines[$cursor];
            if (trim($line) === '') {
                $next = $cursor + 1;
                if ($next < $count && $this->isDefinitionMarker($lines[$next])) {
                    $blankBeforeNextDefinition = true;
                    $cursor = $next;
                    break;
                }
                if ($next < $count && $this->isIndentedDefinitionContinuation($lines[$next])) {
                    $cursor = $next;
                    foreach ($this->readDefinitionContinuationBlock($lines, $cursor) as $block) {
                        $blocks[] = $block;
                    }
                    continue;
                }
                break;
            }

            if ($this->isDefinitionMarker($line)) {
                break;
            }

            if ($this->isIndentedDefinitionContinuation($line)) {
                foreach ($this->readDefinitionContinuationBlock($lines, $cursor) as $block) {
                    $blocks[] = $block;
                }
                continue;
            } else {
                $this->appendLazyDefinitionLine($blocks, trim($line));
            }
            $cursor++;
        }

        return new AstNode('definition', ['loose' => $loose], $blocks);
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>
     */
    private function readDefinitionContinuationBlock(array $lines, int &$cursor): array
    {
        $content = [];
        $count = count($lines);

        while ($cursor < $count) {
            $line = $lines[$cursor];
            if (trim($line) === '') {
                $next = $cursor + 1;
                if ($next < $count && $this->isIndentedDefinitionContinuation($lines[$next])) {
                    $content[] = '';
                    $cursor = $next;
                    continue;
                }

                break;
            }

            if (!$this->isIndentedDefinitionContinuation($line)) {
                break;
            }

            $content[] = $this->stripDefinitionContinuationIndent($line);
            $cursor++;
        }

        while ($content !== [] && end($content) === '') {
            array_pop($content);
        }

        if ($content === []) {
            return [];
        }

        return $this->read(implode("\n", $content))->children;
    }

    private function isIndentedDefinitionContinuation(string $line): bool
    {
        return str_starts_with($line, '    ') || str_starts_with($line, "\t");
    }

    private function stripDefinitionContinuationIndent(string $line): string
    {
        if (str_starts_with($line, "\t")) {
            return substr($line, 1);
        }

        return substr($line, 4);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function appendLazyDefinitionLine(array &$blocks, string $text): void
    {
        if ($text === '') {
            return;
        }

        $lastIndex = array_key_last($blocks);
        if ($lastIndex !== null && $blocks[$lastIndex]->type === 'paragraph') {
            $current = (string) $blocks[$lastIndex]->attr('text', '');
            $combined = $current === '' ? $text : $current . ' ' . $text;
            $blocks[$lastIndex] = new AstNode('paragraph', ['text' => $combined], $this->parseInlines($combined));
            return;
        }

        $blocks[] = new AstNode('paragraph', ['text' => $text], $this->parseInlines($text));
    }

    /**
     * @return list<AstNode>
     */
    private function parseDefinitionBlocks(string $content): array
    {
        if ($content === '') {
            return [];
        }

        if (preg_match('/^(?:[-*+]|\d{1,9}[.)]|#\.)\s+/', $content) === 1) {
            return $this->read($content)->children;
        }

        return [new AstNode('paragraph', ['text' => $content], $this->parseInlines($content))];
    }

    /**
     * @param list<string> $paragraph
     * @param list<AstNode> $blocks
     */
    private function flushParagraph(array &$paragraph, array &$blocks): void
    {
        if ($paragraph === []) {
            return;
        }
        $text = $this->joinParagraphLines($paragraph);
        $children = $this->parseInlines($text);
        $plainText = $this->paragraphTextFromInlines($children);
        if (count($children) === 1 && $children[0]->type === 'image') {
            $figureAttrs = $children[0]->attr('figureAttributes', []);
            if (!is_array($figureAttrs)) {
                $figureAttrs = [];
            }
            $figureAttrs['caption'] = (string) $children[0]->attr('caption', $children[0]->attr('alt', ''));
            $blocks[] = new AstNode(
                'figure',
                $figureAttrs,
                [$children[0]]
            );
            $paragraph = [];
            return;
        }

        $blocks[] = new AstNode('paragraph', ['text' => $plainText], $children);
        $paragraph = [];
    }

    /**
     * @param list<string> $paragraph
     */
    private function joinParagraphLines(array $paragraph): string
    {
        return implode("\n", $paragraph);
    }

    /**
     * @param list<array{indent:int, ordered: bool, start: int|null, items: list<AstNode>}> $listStack
     * @param list<AstNode> $blocks
     */
    private function appendListItem(array &$listStack, array &$blocks, bool $ordered, ?int $number, int $indent, string $text): void
    {
        while ($listStack !== [] && $indent < $listStack[array_key_last($listStack)]['indent']) {
            $this->closeLastList($listStack, $blocks);
        }

        if ($listStack !== []) {
            $top = $listStack[array_key_last($listStack)];
            if ($indent === $top['indent'] && $top['ordered'] !== $ordered) {
                $this->closeLastList($listStack, $blocks);
            }
        }

        if ($listStack === [] || $indent > $listStack[array_key_last($listStack)]['indent']) {
            $listStack[] = [
                'indent' => $indent,
                'ordered' => $ordered,
                'start' => $ordered ? $number : null,
                'items' => [],
            ];
        }

        $attrs = ['text' => $text];
        if ($number !== null) {
            $attrs['number'] = $number;
        }

        $lastIndex = array_key_last($listStack);
        $listStack[$lastIndex]['items'][] = new AstNode('list_item', $attrs, $this->parseInlines($text));
    }

    /**
     * @param list<array{indent:int, ordered: bool, start: int|null, items: list<AstNode>}> $listStack
     * @param list<AstNode> $blocks
     */
    private function flushListStack(array &$listStack, array &$blocks): void
    {
        while ($listStack !== []) {
            $this->closeLastList($listStack, $blocks);
        }
    }

    /**
     * @param list<array{indent:int, ordered: bool, start: int|null, items: list<AstNode>}> $listStack
     * @param list<AstNode> $blocks
     */
    private function closeLastList(array &$listStack, array &$blocks): void
    {
        $list = array_pop($listStack);
        if ($list === null) {
            return;
        }

        $attrs = $list['ordered'] ? ['start' => $list['start'] ?? 1] : [];
        $node = new AstNode($list['ordered'] ? 'ordered_list' : 'bullet_list', $attrs, $list['items']);
        if ($listStack === []) {
            $blocks[] = $node;
            return;
        }

        $parentIndex = array_key_last($listStack);
        $itemIndex = array_key_last($listStack[$parentIndex]['items']);
        if ($itemIndex === null) {
            $blocks[] = $node;
            return;
        }

        $item = $listStack[$parentIndex]['items'][$itemIndex];
        $children = $item->children;
        $children[] = $node;
        $listStack[$parentIndex]['items'][$itemIndex] = new AstNode($item->type, $item->attrs, $children);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $text, bool $allowLinks = true, bool $allowBareCitations = true): array
    {
        $nodes = [];
        $buffer = '';
        $length = strlen($text);
        $offset = 0;

        while ($offset < $length) {
            if ($text[$offset] === "\n") {
                $this->flushText($buffer, $nodes);
                $nodes[] = new AstNode('softbreak');
                $offset++;
                continue;
            }

            if (
                $text[$offset] === '\\'
                && ($text[$offset + 1] ?? '') === "\n"
                && !$this->isEscapedInlinePosition($text, $offset)
            ) {
                $this->flushText($buffer, $nodes);
                $nodes[] = new AstNode('linebreak');
                $offset += 2;
                continue;
            }

            if ($text[$offset] === '`') {
                $tickCount = $this->countBackticks($text, $offset);
                $end = $this->findMatchingBacktickRun($text, $offset + $tickCount, $tickCount);
                if ($end !== null && $end > $offset + $tickCount) {
                    $code = substr($text, $offset + $tickCount, $end - $offset - $tickCount);
                    $code = str_replace(["\r\n", "\r", "\n"], ' ', $code);
                    if (strlen($code) >= 2 && $code[0] === ' ' && $code[strlen($code) - 1] === ' ' && trim($code) !== '') {
                        $code = substr($code, 1, -1);
                    }
                    $next = $end + $tickCount;
                    $attrs = ['text' => $code];
                    $attribute = $this->tryParseInlineAttributeSpec($text, $next);
                    $literalAttribute = null;
                    if ($attribute !== null) {
                        $attrs = array_replace($attrs, $attribute['attrs']);
                        $next = $attribute['next'];
                    } else {
                        $literalAttribute = $this->tryParseSpacedInlineAttributeLiteral($text, $next);
                        if ($literalAttribute !== null) {
                            $next = $literalAttribute['next'];
                        }
                    }
                    $this->flushText($buffer, $nodes);
                    $nodes[] = new AstNode('code', $attrs);
                    if ($literalAttribute !== null) {
                        $nodes[] = new AstNode('text', ['text' => $literalAttribute['text']]);
                    }
                    $offset = $next;
                    continue;
                }
            }

            $inlineNote = $this->resolveFootnoteReferences ? $this->tryParseInlineNote($text, $offset) : null;
            if ($inlineNote !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $inlineNote['node'];
                $offset = $inlineNote['next'];
                continue;
            }

            $math = $this->tryParseMath($text, $offset);
            if ($math !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $math['node'];
                $offset = $math['next'];
                continue;
            }

            $rawTex = $this->tryParseRawTexInline($text, $offset);
            if ($rawTex !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $rawTex['node'];
                $offset = $rawTex['next'];
                continue;
            }

            $strikeout = $this->tryParseStrikeout($text, $offset);
            if ($strikeout !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $strikeout['node'];
                $offset = $strikeout['next'];
                continue;
            }

            $script = $this->tryParseScript($text, $offset);
            if ($script !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $script['node'];
                $offset = $script['next'];
                continue;
            }

            $quote = $this->tryParseSmartQuote($text, $offset);
            if ($quote !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $quote['node'];
                $offset = $quote['next'];
                continue;
            }

            $emphasis = $this->tryParseEmphasisDelimiter($text, $offset);
            if ($emphasis !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $emphasis['node'];
                $offset = $emphasis['next'];
                continue;
            }

            $footnoteReference = $this->resolveFootnoteReferences ? $this->tryParseFootnoteReference($text, $offset) : null;
            if ($footnoteReference !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $footnoteReference['node'];
                $offset = $footnoteReference['next'];
                continue;
            }

            $citation = $allowLinks ? $this->tryParseCitation($text, $offset, $allowBareCitations) : null;
            if ($citation !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $citation['node'];
                $offset = $citation['next'];
                continue;
            }

            $wikiLink = $allowLinks ? $this->tryParseWikiLink($text, $offset) : null;
            if ($wikiLink !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $wikiLink['node'];
                $offset = $wikiLink['next'];
                continue;
            }

            $image = $allowLinks ? $this->tryParseImage($text, $offset) : null;
            if ($image !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $image['node'];
                $offset = $image['next'];
                continue;
            }

            $inlineLink = $allowLinks ? $this->tryParseInlineLink($text, $offset) : null;
            if ($inlineLink !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $inlineLink['node'];
                $offset = $inlineLink['next'];
                continue;
            }

            $span = $this->tryParseBracketedSpan($text, $offset);
            if ($span !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $span['node'];
                $offset = $span['next'];
                continue;
            }

            $emoji = $this->tryParseEmojiAlias($text, $offset);
            if ($emoji !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $emoji['node'];
                $offset = $emoji['next'];
                continue;
            }

            $referenceLink = $allowLinks ? $this->tryParseReferenceLink($text, $offset) : null;
            if ($referenceLink !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $referenceLink['node'];
                $offset = $referenceLink['next'];
                continue;
            }

            $autolink = $allowLinks ? $this->tryParseAutolink($text, $offset) : null;
            if ($autolink !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $autolink['node'];
                if (isset($autolink['literalAttribute'])) {
                    $nodes[] = new AstNode('text', ['text' => $autolink['literalAttribute']]);
                }
                $offset = $autolink['next'];
                continue;
            }

            $rawHtmlInline = $allowLinks ? $this->tryParseRawHtmlInline($text, $offset) : null;
            if ($rawHtmlInline !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $rawHtmlInline['node'];
                $offset = $rawHtmlInline['next'];
                continue;
            }

            $bareUriAutolink = $allowLinks ? $this->tryParseBareUriAutolink($text, $offset) : null;
            if ($bareUriAutolink !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $bareUriAutolink['node'];
                $offset = $bareUriAutolink['next'];
                continue;
            }

            $exampleReference = $this->tryParseNumberedExampleReference($text, $offset);
            if ($exampleReference !== null) {
                $buffer .= $exampleReference['text'];
                $offset = $exampleReference['next'];
                continue;
            }

            $replacement = $this->tryReadSmartTextReplacement($text, $offset);
            if ($replacement !== null) {
                $buffer .= $replacement['text'];
                $offset = $replacement['next'];
                continue;
            }

            $escaped = $this->tryReadBackslashEscape($text, $offset);
            if ($escaped !== null) {
                $buffer .= $escaped['text'];
                $offset = $escaped['next'];
                continue;
            }

            $buffer .= $text[$offset];
            $offset++;
        }

        $this->flushText($buffer, $nodes);

        return $nodes;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseInlineNote(string $text, int $offset): ?array
    {
        if (substr($text, $offset, 2) !== '^[' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        $end = $this->findClosingInlineNoteBracket($text, $offset + 2);
        if ($end === null) {
            return null;
        }

        return [
            'node' => new AstNode('note', [], $this->parseFootnoteBlocks(substr($text, $offset + 2, $end - $offset - 2))),
            'next' => $end + 1,
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseFootnoteReference(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '[' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (preg_match('/\G\[\^([^\]\s]+)\]/', $text, $m, 0, $offset) !== 1) {
            return null;
        }

        $definition = $this->footnoteDefinitions[$this->normalizeReferenceLabel($m[1])] ?? null;
        if ($definition === null) {
            return null;
        }

        return [
            'node' => new AstNode('note', ['label' => $m[1]], $this->parseFootnoteBlocks($definition)),
            'next' => $offset + strlen($m[0]),
        ];
    }

    /**
     * @return list<AstNode>
     */
    private function parseFootnoteBlocks(string $markdown): array
    {
        $markdown = trim($markdown, "\r\n");
        if (trim($markdown) === '') {
            return [];
        }

        $previous = $this->resolveFootnoteReferences;
        $this->resolveFootnoteReferences = false;
        try {
            return $this->read($markdown)->children;
        } finally {
            $this->resolveFootnoteReferences = $previous;
        }
    }

    private function findClosingInlineNoteBracket(string $text, int $offset): ?int
    {
        $depth = 0;
        $length = strlen($text);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === '`') {
                $tickCount = $this->countBackticks($text, $cursor);
                $end = $this->findMatchingBacktickRun($text, $cursor + $tickCount, $tickCount);
                if ($end !== null) {
                    $cursor = $end + $tickCount - 1;
                }
                continue;
            }

            if ($text[$cursor] === '[') {
                $depth++;
                continue;
            }

            if ($text[$cursor] !== ']') {
                continue;
            }

            if ($depth === 0) {
                return $cursor;
            }

            $depth--;
        }

        return null;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseCitation(string $text, int $offset, bool $allowBareCitation): ?array
    {
        if ($this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (($text[$offset] ?? '') === '[') {
            if (preg_match('/\G\[@([A-Za-z0-9_:.#\/$%&+?<>~|-]+)\]/u', $text, $m, 0, $offset) === 1) {
                return [
                    'node' => new AstNode(
                        'citation',
                        ['id' => $m[1], 'text' => $m[0], 'mode' => 'normal'],
                        [new AstNode('text', ['text' => $m[0]])]
                    ),
                    'next' => $offset + strlen($m[0]),
                ];
            }

            return $this->tryParseBracketedCitationCluster($text, $offset);
        }

        if (!$allowBareCitation || ($text[$offset] ?? '') !== '@') {
            return null;
        }

        $previous = $offset === 0 ? '' : $text[$offset - 1];
        if ($previous !== '' && preg_match('/[A-Za-z0-9_@.\/-]/', $previous) === 1) {
            return null;
        }

        if (preg_match('/\G@([A-Za-z0-9_:.#\/$%&+?<>~|-]*[A-Za-z0-9_#\/$%&+?<>~|-])/u', $text, $m, 0, $offset) !== 1) {
            return null;
        }

        $next = $offset + strlen($m[0]);
        $citationText = $m[0];
        $attrs = ['id' => $m[1], 'text' => $citationText, 'mode' => 'author_in_text'];
        $suffix = $this->tryParseBareCitationSuffix($text, $next);
        if ($suffix !== null) {
            $next = $suffix['next'];
            $citationText .= $suffix['source'];
            $attrs['text'] = $citationText;
            $attrs['suffix'] = $suffix['label'];
        }

        return [
            'node' => new AstNode(
                'citation',
                $attrs,
                [new AstNode('text', ['text' => $citationText])]
            ),
            'next' => $next,
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseBracketedCitationCluster(string $text, int $offset): ?array
    {
        $label = $this->parseBracketedLabel($text, $offset);
        if ($label === null || !str_contains($label['text'], '@')) {
            return null;
        }

        $nextChar = $text[$label['next']] ?? '';
        $startsWithCitation = preg_match('/^\s*-?@/u', $label['text']) === 1;
        if (($nextChar === '(' || $nextChar === '[') && !$startsWithCitation) {
            return null;
        }

        $items = $this->splitBracketedCitationItems($label['text']);
        if ($items === []) {
            return null;
        }

        $citations = [];
        foreach ($items as $item) {
            $citation = $this->parseBracketedCitationItem($item);
            if ($citation === null) {
                return null;
            }

            $citations[] = $citation;
        }

        $source = substr($text, $offset, $label['next'] - $offset);
        if (count($citations) === 1) {
            $citation = $citations[0];

            return [
                'node' => new AstNode(
                    'citation',
                    [...$citation->attrs, 'text' => $source],
                    [new AstNode('text', ['text' => $source])]
                ),
                'next' => $label['next'],
            ];
        }

        return [
            'node' => new AstNode(
                'citation_group',
                ['text' => $source],
                $citations
            ),
            'next' => $label['next'],
        ];
    }

    /**
     * @return list<string>
     */
    private function splitBracketedCitationItems(string $text): array
    {
        $items = [];
        $start = 0;
        $braceDepth = 0;
        $bracketDepth = 0;
        $length = strlen($text);

        for ($cursor = 0; $cursor < $length; $cursor++) {
            $char = $text[$cursor];
            if ($char === '\\') {
                $cursor++;
                continue;
            }

            if ($char === '{') {
                $braceDepth++;
                continue;
            }

            if ($char === '}' && $braceDepth > 0) {
                $braceDepth--;
                continue;
            }

            if ($char === '[') {
                $bracketDepth++;
                continue;
            }

            if ($char === ']' && $bracketDepth > 0) {
                $bracketDepth--;
                continue;
            }

            if ($char !== ';' || $braceDepth > 0 || $bracketDepth > 0) {
                continue;
            }

            $item = trim(substr($text, $start, $cursor - $start));
            if ($item === '') {
                return [];
            }
            $items[] = $item;
            $start = $cursor + 1;
        }

        $item = trim(substr($text, $start));
        if ($item === '') {
            return [];
        }
        $items[] = $item;

        return $items;
    }

    private function parseBracketedCitationItem(string $item): ?AstNode
    {
        $match = $this->findBracketedCitationToken($item);
        if ($match === null) {
            return null;
        }

        $prefix = trim(substr($item, 0, $match['start']));
        $tail = trim(substr($item, $match['start'] + $match['length']));
        if (str_starts_with($tail, ',')) {
            $tail = trim(substr($tail, 1));
        }

        $attrs = [
            'id' => $match['id'],
            'text' => $item,
            'mode' => $match['suppressAuthor'] ? 'suppress_author' : 'normal',
        ];
        if ($prefix !== '') {
            $attrs['prefix'] = $prefix;
        }
        if ($tail !== '') {
            $locator = $this->normalizeBracketedCitationTail($tail);
            $locatorParts = $this->inferBracketedCitationLocatorParts($locator);
            $attrs['locator'] = $locator;
            $attrs['locatorLabel'] = $locatorParts['label'];
            $attrs['locatorValue'] = $locatorParts['value'];
        }

        return new AstNode('citation', $attrs, [
            new AstNode('text', ['text' => $item]),
        ]);
    }

    /**
     * @return array{start:int, length:int, id:string, suppressAuthor:bool}|null
     */
    private function findBracketedCitationToken(string $item): ?array
    {
        $idPattern = '[A-Za-z0-9_](?:[A-Za-z0-9_]|[:.#\/$%&+?<>~|-](?=[A-Za-z0-9_]))*';
        $pattern = '/(?<![A-Za-z0-9_@.\/-])(-?)@(?:\{([^}\r\n]+)\}|(' . $idPattern . '))/u';
        if (preg_match($pattern, $item, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $raw = $matches[0][0];
        $id = $matches[2][0] !== '' ? $matches[2][0] : $matches[3][0];
        if ($id === '') {
            return null;
        }

        return [
            'start' => $matches[0][1],
            'length' => strlen($raw),
            'id' => $id,
            'suppressAuthor' => $matches[1][0] === '-',
        ];
    }

    private function normalizeBracketedCitationTail(string $tail): string
    {
        $tail = trim($tail);
        if ($tail === '') {
            return '';
        }

        $forced = $this->unwrapForcedCitationLocator($tail);
        if ($forced !== null) {
            return $forced;
        }

        return preg_replace('/\s+/u', ' ', $tail) ?? $tail;
    }

    /**
     * @return array{label:string, value:string}
     */
    private function inferBracketedCitationLocatorParts(string $locator): array
    {
        $locator = trim(preg_replace('/\s+/u', ' ', $locator) ?? $locator);
        if ($locator === '') {
            return ['label' => 'page', 'value' => ''];
        }

        $patterns = [
            'page' => '/^(?:p(?:p)?\.?|pages?)\s+(.+)$/iu',
            'chapter' => '/^(?:chap(?:ters?|s)?\.?|chapter(?:s)?)\s+(.+)$/iu',
            'section' => '/^(?:sec(?:tions?|s)?\.?|section(?:s)?|\x{00A7}\x{00A7}?)\s+(.+)$/iu',
            'paragraph' => '/^(?:para(?:graphs?|s)?\.?|paragraph(?:s)?|\x{00B6}\x{00B6}?)\s+(.+)$/iu',
            'volume' => '/^(?:vol(?:umes?|s)?\.?|volume(?:s)?)\s+(.+)$/iu',
            'number' => '/^(?:no(?:s)?\.?|number(?:s)?)\s+(.+)$/iu',
        ];

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $locator, $match) === 1) {
                return ['label' => $label, 'value' => trim($match[1])];
            }
        }

        return ['label' => 'page', 'value' => $locator];
    }

    private function unwrapForcedCitationLocator(string $tail): ?string
    {
        if ($tail[0] !== '{') {
            return null;
        }

        $depth = 0;
        $length = strlen($tail);
        for ($cursor = 0; $cursor < $length; $cursor++) {
            if ($tail[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($tail[$cursor] === '{') {
                $depth++;
                continue;
            }

            if ($tail[$cursor] !== '}') {
                continue;
            }

            $depth--;
            if ($depth > 0) {
                continue;
            }

            $locator = substr($tail, 1, $cursor - 1);
            $suffix = trim(substr($tail, $cursor + 1));
            $combined = trim($locator . ($suffix === '' ? '' : ' ' . $suffix));

            return preg_replace('/\s+/u', ' ', $combined) ?? $combined;
        }

        return null;
    }

    /**
     * @return array{source:string, label:string, next:int}|null
     */
    private function tryParseBareCitationSuffix(string $text, int $offset): ?array
    {
        if (preg_match('/\G[ \t]+/', $text, $space, 0, $offset) !== 1) {
            return null;
        }

        $label = $this->parseBracketedLabel($text, $offset + strlen($space[0]));
        if ($label === null || $label['text'] === '' || str_starts_with($label['text'], '^')) {
            return null;
        }

        $next = $label['next'];
        $following = $text[$next] ?? '';
        if ($following === '(' || $following === '[') {
            return null;
        }

        if (isset($this->referenceLinks[$this->normalizeReferenceLabel($label['text'])])) {
            return null;
        }

        return [
            'source' => substr($text, $offset, $next - $offset),
            'label' => $label['text'],
            'next' => $next,
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseImage(string $text, int $offset): ?array
    {
        if (substr($text, $offset, 2) !== '![' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        $label = $this->parseBracketedLabel($text, $offset + 1);
        if ($label === null) {
            return null;
        }

        $next = $label['next'];
        if (($text[$next] ?? '') === '(') {
            $target = $this->parseInlineLinkTarget($text, $next);
            if ($target === null) {
                return null;
            }

            [$node, $next] = $this->buildImageNodeWithTrailingAttributes(
                $text,
                $label['text'],
                $target['url'],
                $target['title'],
                $target['next']
            );

            return [
                'node' => $node,
                'next' => $next,
            ];
        }

        $referenceLabel = $label['text'];
        if (($text[$next] ?? '') === '[') {
            $reference = $this->parseBracketedLabel($text, $next);
            if ($reference === null) {
                return null;
            }
            $referenceLabel = $reference['text'] === '' ? $label['text'] : $reference['text'];
            $next = $reference['next'];
        }

        $target = $this->referenceLinks[$this->normalizeReferenceLabel($referenceLabel)] ?? null;
        if ($target === null) {
            return null;
        }

        [$node, $next] = $this->buildImageNodeWithTrailingAttributes(
            $text,
            $label['text'],
            $target['url'],
            $target['title'],
            $next
        );

        return [
            'node' => $node,
            'next' => $next,
        ];
    }

    /**
     * @return array{0: AstNode, 1: int}
     */
    private function buildImageNodeWithTrailingAttributes(
        string $source,
        string $label,
        string $url,
        string $title,
        int $offset
    ): array {
        $attrs = [];
        $attribute = $this->tryParseInlineAttributeSpec($source, $offset);
        if ($attribute !== null) {
            $attrs = $attribute['attrs'];
            $offset = $attribute['next'];
        }

        $alt = null;
        if (isset($attrs['attributes']) && is_array($attrs['attributes']) && array_key_exists('alt', $attrs['attributes'])) {
            $alt = (string) $attrs['attributes']['alt'];
            unset($attrs['attributes']['alt']);
            if ($attrs['attributes'] === []) {
                unset($attrs['attributes']);
            }
        }
        if (isset($attrs['htmlAttributes']) && is_array($attrs['htmlAttributes']) && array_key_exists('alt', $attrs['htmlAttributes'])) {
            unset($attrs['htmlAttributes']['alt']);
            if ($attrs['htmlAttributes'] === []) {
                unset($attrs['htmlAttributes']);
            }
        }

        return [$this->buildImageNode($label, $url, $title, $attrs, $alt), $offset];
    }

    /**
     * @param array<string, mixed> $figureAttributes
     */
    private function buildImageNode(
        string $label,
        string $url,
        string $title,
        array $figureAttributes = [],
        ?string $altOverride = null
    ): AstNode
    {
        $labelInlines = $this->parseLinkLabelInlines($label);
        $caption = $this->plainTextFromInlines($labelInlines);
        $attrs = [
            'url' => $url,
            'alt' => $altOverride ?? $caption,
            'caption' => $caption,
        ];
        if ($title !== '') {
            $attrs['title'] = $title;
        }
        if ($figureAttributes !== []) {
            $attrs['figureAttributes'] = $figureAttributes;
        }

        return new AstNode('image', $attrs, $labelInlines);
    }

    /**
     * @return list<AstNode>
     */
    private function parseLinkLabelInlines(string $label): array
    {
        return $this->parseInlines($label, false);
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseWikiLink(string $text, int $offset): ?array
    {
        if (substr($text, $offset, 2) !== '[[' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        $end = $this->findClosingWikiLink($text, $offset + 2);
        if ($end === null) {
            return null;
        }

        $content = substr($text, $offset + 2, $end - $offset - 2);
        if ($content === '') {
            return null;
        }

        [$url, $label] = $this->splitWikiLinkContent($content);
        $url = $this->decodeHtmlEntities($this->unescapeLinkComponent($url));
        $label = $this->decodeHtmlEntities($this->unescapeLinkComponent($label));

        return [
            'node' => new AstNode(
                'link',
                [
                    'url' => $url,
                    'classes' => ['wikilink'],
                ],
                [new AstNode('text', ['text' => $label])]
            ),
            'next' => $end + 2,
        ];
    }

    private function findClosingWikiLink(string $text, int $offset): ?int
    {
        $length = strlen($text);
        for ($cursor = $offset; $cursor < $length - 1; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === ']' && $text[$cursor + 1] === ']') {
                return $cursor;
            }
        }

        return null;
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function splitWikiLinkContent(string $content): array
    {
        $length = strlen($content);
        for ($cursor = 0; $cursor < $length; $cursor++) {
            if ($content[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($content[$cursor] !== '|') {
                continue;
            }

            $label = trim(substr($content, 0, $cursor));
            $url = trim(substr($content, $cursor + 1));
            if ($label === '' || $url === '') {
                break;
            }

            return [$url, $label];
        }

        $content = trim($content);

        return [$content, $content];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainTextFromInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'raw_tex') {
                $text .= (string) $node->attr('tex', '');
                continue;
            }
            if ($node->type === 'softbreak') {
                $text .= "\n";
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
     * @param list<AstNode> $nodes
     */
    private function paragraphTextFromInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'raw_tex') {
                $text .= (string) $node->attr('tex', '');
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

            $text .= $this->paragraphTextFromInlines($node->children);
        }

        return $text;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseInlineLink(string $text, int $offset): ?array
    {
        $label = $this->parseBracketedLabel($text, $offset);
        if ($label === null || ($text[$label['next']] ?? '') !== '(') {
            return null;
        }

        $target = $this->parseInlineLinkTarget($text, $label['next']);
        if ($target === null) {
            return null;
        }

        $attrs = ['url' => $target['url']];
        if ($target['title'] !== '') {
            $attrs['title'] = $target['title'];
        }

        return [
            'node' => new AstNode('link', $attrs, $this->parseLinkLabelInlines($label['text'])),
            'next' => $target['next'],
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseReferenceLink(string $text, int $offset): ?array
    {
        $label = $this->parseBracketedLabel($text, $offset);
        if ($label === null) {
            return null;
        }

        $next = $label['next'];
        $referenceLabel = $label['text'];
        if (($text[$next] ?? '') === '[') {
            $reference = $this->parseBracketedLabel($text, $next);
            if ($reference === null) {
                return null;
            }

            $referenceLabel = $reference['text'] === '' ? $label['text'] : $reference['text'];
            $next = $reference['next'];
        }

        $target = $this->referenceLinks[$this->normalizeReferenceLabel($referenceLabel)] ?? null;
        if ($target === null) {
            return null;
        }

        $attrs = ['url' => $target['url']];
        if ($target['title'] !== '') {
            $attrs['title'] = $target['title'];
        }

        return [
            'node' => new AstNode('link', $attrs, $this->parseLinkLabelInlines($label['text'])),
            'next' => $next,
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseBracketedSpan(string $text, int $offset): ?array
    {
        $label = $this->parseBracketedLabel($text, $offset);
        if ($label === null || ($text[$label['next']] ?? '') !== '{') {
            return null;
        }

        $end = $this->findUnescapedCharacter($text, '}', $label['next'] + 1);
        if ($end === null) {
            return null;
        }

        [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec(substr($text, $label['next'] + 1, $end - $label['next'] - 1));
        if ($id === null && $classes === [] && $attributes === []) {
            return null;
        }

        return [
            'node' => new AstNode(
                'span',
                $this->markdownAttributeAstAttrs($id, $classes, $attributes),
                $this->parseInlines($label['text'])
            ),
            'next' => $end + 1,
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseEmojiAlias(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== ':' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (preg_match('/\G:([A-Za-z0-9_+-]+):/', $text, $m, 0, $offset) !== 1) {
            return null;
        }

        $emoji = match ($m[1]) {
            'smile' => "\u{1F604}",
            '+1' => "\u{1F44D}",
            default => null,
        };
        if ($emoji === null) {
            return null;
        }

        return [
            'node' => new AstNode(
                'span',
                $this->markdownAttributeAstAttrs(null, ['emoji'], ['data-emoji' => $m[1]]),
                [new AstNode('text', ['text' => $emoji])]
            ),
            'next' => $offset + strlen($m[0]),
        ];
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     * @return array<string, mixed>
     */
    private function markdownAttributeAstAttrs(?string $id, array $classes, array $attributes): array
    {
        $attrs = [];
        $htmlAttributes = [];
        if ($id !== null && $id !== '') {
            $attrs['id'] = $id;
            $htmlAttributes['id'] = $id;
        }
        if ($classes !== []) {
            $attrs['classes'] = $classes;
            $htmlAttributes['class'] = implode(' ', $classes);
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
            foreach ($attributes as $name => $value) {
                $htmlAttributes[$name] = $value;
            }
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    /**
     * @return array{attrs: array<string, mixed>, next: int}|null
     */
    private function tryParseInlineAttributeSpec(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '{') {
            return null;
        }

        $end = $this->findUnescapedCharacter($text, '}', $offset + 1);
        if ($end === null) {
            return null;
        }

        [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec(substr($text, $offset + 1, $end - $offset - 1));
        if ($id === null && $classes === [] && $attributes === []) {
            return null;
        }

        return [
            'attrs' => $this->markdownAttributeAstAttrs($id, $classes, $attributes),
            'next' => $end + 1,
        ];
    }

    /**
     * @return array{text: string, next: int}|null
     */
    private function tryParseSpacedInlineAttributeLiteral(string $text, int $offset): ?array
    {
        if (preg_match('/\G[ \t]+\{/', $text, $space, 0, $offset) !== 1) {
            return null;
        }

        $start = $offset + strlen($space[0]) - 1;
        $end = $this->findUnescapedCharacter($text, '}', $start + 1);
        if ($end === null) {
            return null;
        }

        [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec(substr($text, $start + 1, $end - $start - 1));
        if ($id === null && $classes === [] && $attributes === []) {
            return null;
        }

        return [
            'text' => substr($text, $offset, $end - $offset + 1),
            'next' => $end + 1,
        ];
    }

    /**
     * @return array{node: AstNode, next: int, literalAttribute?: string}|null
     */
    private function tryParseAutolink(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '<' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (preg_match('/\G<((?:https?|ftp):\/\/[^<>\s]+)>/i', $text, $m, 0, $offset) === 1) {
            $url = $this->normalizeLinkDestination($m[1]);
            $next = $offset + strlen($m[0]);
            [$attrs, $next, $literalAttribute] = $this->readTrailingAutolinkAttributes($text, $next, [
                'url' => $url,
                'classes' => ['uri'],
            ]);

            $result = [
                'node' => new AstNode(
                    'link',
                    $attrs,
                    [new AstNode('text', ['text' => $url])]
                ),
                'next' => $next,
            ];
            if ($literalAttribute !== null) {
                $result['literalAttribute'] = $literalAttribute;
            }

            return $result;
        }

        if (
            preg_match(
                '/\G<([^\s<>@]+@[^\s<>@]+\.[^\s<>@]+)>/u',
                $text,
                $m,
                0,
                $offset
            ) === 1
        ) {
            $address = $this->decodeHtmlEntities($this->unescapeLinkComponent($m[1]));
            $next = $offset + strlen($m[0]);
            [$attrs, $next, $literalAttribute] = $this->readTrailingAutolinkAttributes($text, $next, [
                'url' => 'mailto:' . $address,
                'classes' => ['email'],
            ]);

            $result = [
                'node' => new AstNode(
                    'link',
                    $attrs,
                    [new AstNode('text', ['text' => $address])]
                ),
                'next' => $next,
            ];
            if ($literalAttribute !== null) {
                $result['literalAttribute'] = $literalAttribute;
            }

            return $result;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array{0:array<string, mixed>, 1:int, 2:string|null}
     */
    private function readTrailingAutolinkAttributes(string $text, int $offset, array $attrs): array
    {
        $attribute = $this->tryParseInlineAttributeSpec($text, $offset);
        if ($attribute !== null) {
            $merged = array_replace($attrs, $attribute['attrs']);
            if (isset($attrs['classes']) && !array_key_exists('classes', $attribute['attrs'])) {
                unset($merged['classes']);
            }

            return [$merged, $attribute['next'], null];
        }

        $literalAttribute = $this->tryParseSpacedInlineAttributeLiteral($text, $offset);
        if ($literalAttribute !== null) {
            return [$attrs, $literalAttribute['next'], $literalAttribute['text']];
        }

        return [$attrs, $offset, null];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseRawHtmlInline(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '<' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (preg_match('~\G</a\s*>~iu', $text, $close, 0, $offset) === 1) {
            return [
                'node' => new AstNode('raw_html_inline', ['html' => $close[0]]),
                'next' => $offset + strlen($close[0]),
            ];
        }

        if (preg_match('~\G<a(?=\s|>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?>~iu', $text, $open, 0, $offset) === 1) {
            $afterOpen = $offset + strlen($open[0]);
            if (preg_match('~\G</a\s*>~iu', $text, $close, 0, $afterOpen) === 1) {
                return [
                    'node' => new AstNode('raw_html_inline', ['html' => $open[0]]),
                    'next' => $afterOpen,
                ];
            }

            if (preg_match('~</a\s*>~iu', $text, $close, PREG_OFFSET_CAPTURE, $afterOpen) === 1) {
                $end = $close[0][1] + strlen($close[0][0]);

                return [
                    'node' => new AstNode('raw_html_inline', ['html' => substr($text, $offset, $end - $offset)]),
                    'next' => $end,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseBareUriAutolink(string $text, int $offset): ?array
    {
        if ($this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        $previous = $offset === 0 ? '' : $text[$offset - 1];
        if ($previous !== '' && preg_match('/[A-Za-z0-9_@.\/-]/', $previous) === 1) {
            return null;
        }

        if (
            preg_match(
                '~\G(?:(?:https?|git|file)://[^\s<>"\']+|mailto:[^\s<>"\']+|doi:10\.[^\s<>"\']+)~iu',
                $text,
                $m,
                0,
                $offset
            ) !== 1
        ) {
            return null;
        }

        $candidate = $this->trimBareUriAutolinkCandidate($m[0]);
        if ($candidate === '') {
            return null;
        }

        $url = $this->normalizeBareUriDestination($candidate);
        $display = $this->decodeHtmlEntities($this->unescapeLinkComponent($candidate));

        return [
            'node' => new AstNode(
                'link',
                [
                    'url' => $url,
                    'classes' => ['uri'],
                ],
                [new AstNode('text', ['text' => $display])]
            ),
            'next' => $offset + strlen($candidate),
        ];
    }

    private function trimBareUriAutolinkCandidate(string $candidate): string
    {
        do {
            $previous = $candidate;
            $candidate = rtrim($candidate, ".,;:!?");
            foreach ([['(', ')'], ['[', ']'], ['{', '}']] as [$open, $close]) {
                while (
                    str_ends_with($candidate, $close)
                    && substr_count($candidate, $close) > substr_count($candidate, $open)
                ) {
                    $candidate = substr($candidate, 0, -1);
                }
            }
        } while ($candidate !== $previous);

        return $candidate;
    }

    private function normalizeBareUriDestination(string $destination): string
    {
        $destination = $this->normalizeLinkDestination($destination);

        return strtr($destination, [
            '[' => '%5B',
            ']' => '%5D',
            '{' => '%7B',
            '}' => '%7D',
        ]);
    }

    /**
     * @return array{text:string, next:int}|null
     */
    private function tryParseNumberedExampleReference(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '(' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (preg_match('/\G\(@([A-Za-z0-9_-]+)\)/', $text, $m, 0, $offset) !== 1) {
            return null;
        }

        $number = $this->exampleReferences[$m[1]] ?? null;
        if ($number === null) {
            return null;
        }

        return [
            'text' => '(' . $number . ')',
            'next' => $offset + strlen($m[0]),
        ];
    }

    /**
     * @return array{text:string, next:int}|null
     */
    private function parseBracketedLabel(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '[') {
            return null;
        }

        $start = $offset + 1;
        $depth = 0;
        $length = strlen($text);
        for ($cursor = $start; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === '[') {
                $depth++;
                continue;
            }

            if ($text[$cursor] !== ']') {
                continue;
            }

            if ($depth > 0) {
                $depth--;
                continue;
            }

            return [
                'text' => substr($text, $start, $cursor - $start),
                'next' => $cursor + 1,
            ];
        }

        return null;
    }

    /**
     * @return array{url:string, title:string, next:int}|null
     */
    private function parseInlineLinkTarget(string $text, int $openParenOffset): ?array
    {
        if (($text[$openParenOffset] ?? '') !== '(') {
            return null;
        }

        $close = $this->findInlineLinkClosingParen($text, $openParenOffset + 1);
        if ($close === null) {
            return null;
        }

        $target = $this->parseLinkDestinationAndTitle(substr($text, $openParenOffset + 1, $close - $openParenOffset - 1));
        if ($target === null) {
            return null;
        }

        return [
            'url' => $target['url'],
            'title' => $target['title'],
            'next' => $close + 1,
        ];
    }

    private function findInlineLinkClosingParen(string $text, int $offset): ?int
    {
        $length = strlen($text);
        $quote = null;
        $parenDepth = 0;
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($quote !== null) {
                if ($text[$cursor] === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($text[$cursor] === '"' || $text[$cursor] === "'") {
                $quote = $text[$cursor];
                continue;
            }

            if ($text[$cursor] === '(') {
                $parenDepth++;
                continue;
            }

            if ($text[$cursor] === ')') {
                if ($parenDepth > 0) {
                    $parenDepth--;
                    continue;
                }

                return $cursor;
            }
        }

        return null;
    }

    /**
     * @return array{url:string, title:string}|null
     */
    private function parseLinkDestinationAndTitle(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return ['url' => '', 'title' => ''];
        }

        if ($content[0] === '<') {
            [$destination, $rest] = $this->readLinkDestination($content);
            if ($destination === null) {
                return null;
            }

            $title = '';
            $rest = trim($rest);
            if ($rest !== '') {
                $title = $this->parseLinkTitle($rest);
                if ($title === null) {
                    return null;
                }
            }

            return [
                'url' => $this->normalizeLinkDestination($destination),
                'title' => $title,
            ];
        }

        [$destination, $titleSource] = $this->splitBareLinkDestinationAndTitle($content);
        $destination = trim($destination);
        if ($destination === '') {
            return null;
        }

        $title = '';
        if ($titleSource !== null) {
            $title = $this->parseLinkTitle($titleSource);
            if ($title === null) {
                return null;
            }
        }

        return [
            'url' => $this->normalizeLinkDestination($destination),
            'title' => $title,
        ];
    }

    /**
     * @return array{0:string, 1:string|null}
     */
    private function splitBareLinkDestinationAndTitle(string $content): array
    {
        $length = strlen($content);
        for ($cursor = 0; $cursor < $length; $cursor++) {
            if (!ctype_space($content[$cursor])) {
                continue;
            }

            $title = ltrim(substr($content, $cursor + 1));
            if ($title === '' || $this->parseLinkTitle($title) === null) {
                continue;
            }

            $destination = rtrim(substr($content, 0, $cursor));
            if ($destination !== '') {
                return [$destination, $title];
            }
        }

        return [$content, null];
    }

    /**
     * @return array{0:string|null, 1:string}
     */
    private function readLinkDestination(string $content): array
    {
        if ($content[0] === '<') {
            $end = $this->findUnescapedCharacter($content, '>', 1);
            if ($end === null) {
                return [null, ''];
            }

            return [substr($content, 1, $end - 1), substr($content, $end + 1)];
        }

        $length = strlen($content);
        for ($cursor = 0; $cursor < $length; $cursor++) {
            if ($content[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if (ctype_space($content[$cursor])) {
                return [substr($content, 0, $cursor), substr($content, $cursor + 1)];
            }
        }

        return [$content, ''];
    }

    private function findUnescapedCharacter(string $text, string $character, int $offset): ?int
    {
        $length = strlen($text);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === $character) {
                return $cursor;
            }
        }

        return null;
    }

    private function parseLinkTitle(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $open = $text[0];
        $close = match ($open) {
            '"' => '"',
            "'" => "'",
            '(' => ')',
            default => null,
        };
        if ($close === null || !str_ends_with($text, $close)) {
            return null;
        }

        return $this->decodeHtmlEntities($this->unescapeLinkComponent(substr($text, 1, -1)));
    }

    private function unescapeLinkComponent(string $text): string
    {
        $unescaped = '';
        $length = strlen($text);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $text[$offset];
            $next = $text[$offset + 1] ?? '';
            if ($char === '\\' && $this->isMarkdownEscapablePunctuation($next)) {
                $unescaped .= $next;
                $offset++;
                continue;
            }

            $unescaped .= $char;
        }

        return $unescaped;
    }

    private function normalizeLinkDestination(string $destination): string
    {
        $destination = $this->decodeHtmlEntities($this->unescapeLinkComponent($destination));
        $destination = trim(preg_replace('/\s+/', ' ', $destination) ?? $destination);

        return str_replace(' ', '%20', $destination);
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseMath(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '$' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (substr($text, $offset, 2) === '$$') {
            $end = $this->findClosingDisplayMath($text, $offset + 2);
            if ($end === null || $end === $offset + 2) {
                return null;
            }

            return [
                'node' => new AstNode('math', [
                    'text' => $this->expandRawTexMathMacros(trim(substr($text, $offset + 2, $end - $offset - 2))),
                    'display' => true,
                ]),
                'next' => $end + 2,
            ];
        }

        $next = $text[$offset + 1] ?? '';
        if ($next === '' || ctype_space($next)) {
            return null;
        }

        $end = $this->findClosingInlineMath($text, $offset + 1);
        if ($end === null || $end === $offset + 1) {
            return null;
        }

        return [
            'node' => new AstNode('math', [
                'text' => $this->expandRawTexMathMacros(trim(substr($text, $offset + 1, $end - $offset - 1))),
                'display' => false,
            ]),
            'next' => $end + 1,
        ];
    }

    private function findClosingDisplayMath(string $text, int $offset): ?int
    {
        $position = strpos($text, '$$', $offset);
        while ($position !== false) {
            if (!$this->isEscapedInlinePosition($text, $position)) {
                return $position;
            }

            $position = strpos($text, '$$', $position + 2);
        }

        return null;
    }

    private function findClosingInlineMath(string $text, int $offset): ?int
    {
        $length = strlen($text);
        $braceDepth = 0;

        for ($position = $offset; $position < $length; $position++) {
            $char = $text[$position];
            if ($char === '\\') {
                $position++;
                continue;
            }

            if ($char === '{') {
                $braceDepth++;
                continue;
            }

            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                continue;
            }

            if ($char !== '$' || $braceDepth > 0) {
                continue;
            }

            if (
                substr($text, $position, 2) !== '$$'
                && !$this->isInvalidClosingInlineMathDollar($text, $position)
            ) {
                return $position;
            }
        }

        return null;
    }

    private function isInvalidClosingInlineMathDollar(string $text, int $offset): bool
    {
        $previous = $offset > 0 ? $text[$offset - 1] : '';
        $next = $text[$offset + 1] ?? '';

        return $previous === '' || ctype_space($previous) || ctype_digit($next);
    }

    private function expandRawTexMathMacros(string $math): string
    {
        if ($this->rawTexMacros === []) {
            return $math;
        }

        $expanded = $math;
        for ($iteration = 0; $iteration < 5; $iteration++) {
            $next = $this->expandRawTexMathMacrosOnce($expanded);
            if ($next === $expanded) {
                break;
            }
            $expanded = $next;
        }

        return $expanded;
    }

    private function expandRawTexMathMacrosOnce(string $math): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($math);

        while ($offset < $length) {
            if (
                ($math[$offset] ?? '') === '\\'
                && preg_match('/\G\\\\([A-Za-z]+)/', $math, $m, 0, $offset) === 1
                && isset($this->rawTexMacros[$m[1]])
            ) {
                $macro = $this->rawTexMacros[$m[1]];
                $cursor = $offset + strlen($m[0]);
                $args = [];
                for ($argument = 0; $argument < $macro['arity']; $argument++) {
                    $parsed = $this->readTexBraceArgument($math, $cursor);
                    if ($parsed === null) {
                        break;
                    }
                    $args[] = $parsed['value'];
                    $cursor = $parsed['next'];
                }

                if (count($args) === $macro['arity']) {
                    $output .= $this->renderRawTexMacroTemplate($macro['template'], $args);
                    $offset = $cursor;
                    continue;
                }
            }

            $output .= $math[$offset];
            $offset++;
        }

        return $output;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readTexBraceArgument(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '{') {
            return null;
        }

        $depth = 0;
        $length = strlen($text);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === '{') {
                $depth++;
                continue;
            }

            if ($text[$cursor] !== '}') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return [
                    'value' => substr($text, $offset + 1, $cursor - $offset - 1),
                    'next' => $cursor + 1,
                ];
            }
        }

        return null;
    }

    /**
     * @param list<string> $args
     */
    private function renderRawTexMacroTemplate(string $template, array $args): string
    {
        foreach ($args as $index => $argument) {
            $template = str_replace('#' . ($index + 1), $argument, $template);
        }

        return $template;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseRawTexInline(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '\\' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (preg_match('/\G\\\\(stopformula)\b/', $text, $m, 0, $offset) === 1) {
            return [
                'node' => new AstNode('raw_tex', ['tex' => $m[0], 'command' => $m[1]]),
                'next' => $offset + strlen($m[0]),
            ];
        }

        if (preg_match('/\G\\\\([A-Za-z])\b/', $text, $m, 0, $offset) === 1) {
            return [
                'node' => new AstNode('raw_tex', ['tex' => $m[0], 'command' => $m[1]]),
                'next' => $offset + strlen($m[0]),
            ];
        }

        // Pandoc leaves bare environment commands such as "\begin" as text.
        if (preg_match('/\G\\\\(?:begin|end)\b/', $text, $m, 0, $offset) === 1) {
            $next = $text[$offset + strlen($m[0])] ?? '';
            if ($next !== '{' && $next !== '[') {
                return null;
            }
        }

        if (
            preg_match(
                '/\G\\\\([A-Za-z]+)(?:\[[^\]\r\n]*\])?(?:\{[^{}\r\n]*\})+/',
                $text,
                $m,
                0,
                $offset
            ) !== 1
        ) {
            return null;
        }

        return [
            'node' => new AstNode('raw_tex', ['tex' => $m[0], 'command' => $m[1]]),
            'next' => $offset + strlen($m[0]),
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseSmartQuote(string $text, int $offset): ?array
    {
        $delimiter = $text[$offset] ?? '';
        if ($delimiter !== '"' && $delimiter !== "'") {
            return null;
        }

        if (!$this->canOpenSmartQuote($text, $offset, $delimiter)) {
            return null;
        }

        $end = $this->findClosingSmartQuote($text, $offset + 1, $delimiter);
        if ($end === null || $end === $offset + 1) {
            return null;
        }

        return [
            'node' => new AstNode(
                'quoted',
                ['kind' => $delimiter === "'" ? 'single' : 'double'],
                $this->parseInlines(substr($text, $offset + 1, $end - $offset - 1))
            ),
            'next' => $end + 1,
        ];
    }

    private function canOpenSmartQuote(string $text, int $offset, string $delimiter): bool
    {
        if ($this->isEscapedInlinePosition($text, $offset)) {
            return false;
        }

        $next = $text[$offset + 1] ?? '';
        if ($next === '' || ctype_space($next)) {
            return false;
        }

        if ($delimiter === "'" && $this->isApostrophe($text, $offset)) {
            return false;
        }

        return !$this->hasWordCharacterBeforeOffset($text, $offset);
    }

    private function findClosingSmartQuote(string $text, int $offset, string $delimiter): ?int
    {
        $length = strlen($text);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === '`') {
                $tickCount = $this->countBackticks($text, $cursor);
                $end = $this->findMatchingBacktickRun($text, $cursor + $tickCount, $tickCount);
                if ($end !== null) {
                    $cursor = $end + $tickCount - 1;
                }
                continue;
            }

            if (substr($text, $cursor, 2) === '^[' && !$this->isEscapedInlinePosition($text, $cursor)) {
                $end = $this->findClosingInlineNoteBracket($text, $cursor + 2);
                if ($end !== null) {
                    $cursor = $end;
                }
                continue;
            }

            if ($text[$cursor] === $delimiter && $this->canCloseSmartQuote($text, $cursor, $delimiter)) {
                return $cursor;
            }
        }

        return null;
    }

    private function canCloseSmartQuote(string $text, int $offset, string $delimiter): bool
    {
        if ($this->isEscapedInlinePosition($text, $offset)) {
            return false;
        }

        $previous = $offset > 0 ? $text[$offset - 1] : '';
        if ($previous === '' || ctype_space($previous)) {
            return false;
        }

        return !($delimiter === "'" && $this->isApostrophe($text, $offset));
    }

    /**
     * @return array{text:string, next:int}|null
     */
    private function tryReadSmartTextReplacement(string $text, int $offset): ?array
    {
        if (substr($text, $offset, 3) === '...') {
            return ['text' => "\u{2026}", 'next' => $offset + 3];
        }

        if (substr($text, $offset, 3) === '---') {
            return ['text' => "\u{2014}", 'next' => $offset + 3];
        }

        if (substr($text, $offset, 2) === '--') {
            return ['text' => "\u{2013}", 'next' => $offset + 2];
        }

        if (($text[$offset] ?? '') === "'" && $this->isRightSingleQuoteReplacement($text, $offset)) {
            return ['text' => "\u{2019}", 'next' => $offset + 1];
        }

        $unclosedQuote = $this->tryReadUnclosedSmartQuoteReplacement($text, $offset);
        if ($unclosedQuote !== null) {
            return $unclosedQuote;
        }

        return null;
    }

    /**
     * @return array{text:string, next:int}|null
     */
    private function tryReadUnclosedSmartQuoteReplacement(string $text, int $offset): ?array
    {
        $delimiter = $text[$offset] ?? '';
        if ($delimiter !== '"' && $delimiter !== "'") {
            return null;
        }

        if (!$this->canOpenSmartQuote($text, $offset, $delimiter)) {
            return null;
        }

        if ($this->findClosingSmartQuote($text, $offset + 1, $delimiter) !== null) {
            return null;
        }

        return [
            'text' => $delimiter === "'" ? "\u{2018}" : "\u{201C}",
            'next' => $offset + 1,
        ];
    }

    private function isRightSingleQuoteReplacement(string $text, int $offset): bool
    {
        if ($this->isApostrophe($text, $offset)) {
            return true;
        }

        return $this->hasWordCharacterBeforeOffset($text, $offset)
            && !$this->hasWordCharacterAfterOffset($text, $offset);
    }

    private function isApostrophe(string $text, int $offset): bool
    {
        $previousIsWord = $this->hasWordCharacterBeforeOffset($text, $offset);
        if (!$previousIsWord && ($text[$offset - 1] ?? '') === '$') {
            $previousIsWord = $this->hasWordCharacterBeforeOffset($text, $offset - 1);
        }

        return $previousIsWord && $this->hasWordCharacterAfterOffset($text, $offset);
    }

    private function hasWordCharacterBeforeOffset(string $text, int $offset): bool
    {
        if ($offset <= 0) {
            return false;
        }

        return preg_match('/[\pL\pN]\z/u', substr($text, 0, $offset)) === 1;
    }

    private function hasWordCharacterAfterOffset(string $text, int $offset): bool
    {
        if ($offset + 1 >= strlen($text)) {
            return false;
        }

        return preg_match('/\A[\pL\pN]/u', substr($text, $offset + 1)) === 1;
    }

    /**
     * @return array{text:string, next:int}|null
     */
    private function tryReadBackslashEscape(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '\\' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        $next = $text[$offset + 1] ?? '';
        if (!$this->isMarkdownEscapablePunctuation($next)) {
            return null;
        }

        return ['text' => $next, 'next' => $offset + 2];
    }

    private function isMarkdownEscapablePunctuation(string $char): bool
    {
        return $char !== '' && str_contains(self::MARKDOWN_ESCAPABLE_ASCII_PUNCTUATION, $char);
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseStrikeout(string $text, int $offset): ?array
    {
        if (substr($text, $offset, 2) !== '~~') {
            return null;
        }

        $end = strpos($text, '~~', $offset + 2);
        if ($end === false || $end === $offset + 2) {
            return null;
        }

        $inner = substr($text, $offset + 2, $end - $offset - 2);

        return [
            'node' => new AstNode('strikeout', [], $this->parseInlines($inner)),
            'next' => $end + 2,
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseScript(string $text, int $offset): ?array
    {
        $delimiter = $text[$offset] ?? '';
        if ($delimiter !== '^' && $delimiter !== '~') {
            return null;
        }

        if ($delimiter === '~' && ($text[$offset + 1] ?? '') === '~') {
            return null;
        }

        $end = $this->findClosingScriptDelimiter($text, $offset + 1, $delimiter);
        if ($end === null) {
            return $this->tryParseShortScript($text, $offset, $delimiter);
        }
        if ($end === $offset + 1) {
            return null;
        }

        $inner = substr($text, $offset + 1, $end - $offset - 1);
        if ($this->hasUnescapedScriptWhitespace($inner)) {
            return null;
        }

        return [
            'node' => new AstNode(
                $delimiter === '^' ? 'superscript' : 'subscript',
                [],
                $this->parseInlines($this->normalizeScriptContent($inner))
            ),
            'next' => $end + 1,
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseShortScript(string $text, int $offset, string $delimiter): ?array
    {
        $previous = $offset > 0 ? $text[$offset - 1] : '';
        if (!$this->isAsciiAlnum($previous)) {
            return null;
        }

        if (preg_match('/\G\d+/', $text, $m, 0, $offset + 1) !== 1) {
            return null;
        }

        return [
            'node' => new AstNode(
                $delimiter === '^' ? 'superscript' : 'subscript',
                [],
                [new AstNode('text', ['text' => $m[0]])]
            ),
            'next' => $offset + 1 + strlen($m[0]),
        ];
    }

    private function findClosingScriptDelimiter(string $text, int $offset, string $delimiter): ?int
    {
        $position = strpos($text, $delimiter, $offset);
        while ($position !== false) {
            if (!$this->isEscapedInlinePosition($text, $position)) {
                return $position;
            }

            $position = strpos($text, $delimiter, $position + 1);
        }

        return null;
    }

    private function hasUnescapedScriptWhitespace(string $text): bool
    {
        $length = strlen($text);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($text[$offset] === '\\') {
                $offset++;
                continue;
            }
            if (ctype_space($text[$offset])) {
                return true;
            }
        }

        return false;
    }

    private function normalizeScriptContent(string $text): string
    {
        $normalized = '';
        $length = strlen($text);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($text[$offset] === '\\' && ($text[$offset + 1] ?? '') === ' ') {
                $normalized .= "\xC2\xA0";
                $offset++;
                continue;
            }

            $normalized .= $text[$offset];
        }

        return $normalized;
    }

    private function isEscapedInlinePosition(string $text, int $offset): bool
    {
        $slashes = 0;
        for ($cursor = $offset - 1; $cursor >= 0 && $text[$cursor] === '\\'; $cursor--) {
            $slashes++;
        }

        return $slashes % 2 === 1;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseEmphasisDelimiter(string $text, int $offset): ?array
    {
        $char = $text[$offset] ?? '';
        if ($char !== '*' && $char !== '_') {
            return null;
        }

        $runLength = $this->countDelimiterRun($text, $offset, $char);
        $sizes = $runLength >= 3 ? [3, 1, 2] : ($runLength >= 2 ? [2, 1] : [1]);
        foreach ($sizes as $size) {
            if ($runLength < $size || !$this->canOpenInlineDelimiter($text, $offset, $char, $size)) {
                continue;
            }

            $end = $this->findClosingInlineDelimiter($text, $offset + $size, $char, $size);
            if ($end === null || $end <= $offset + $size) {
                continue;
            }

            $inner = $this->parseInlines(substr($text, $offset + $size, $end - $offset - $size), true, false);
            $node = match ($size) {
                3 => new AstNode('strong', [], [new AstNode('emph', [], $inner)]),
                2 => new AstNode('strong', [], $inner),
                default => new AstNode('emph', [], $inner),
            };

            return ['node' => $node, 'next' => $end + $size];
        }

        return null;
    }

    private function countDelimiterRun(string $text, int $offset, string $char): int
    {
        $count = 0;
        $length = strlen($text);
        while ($offset + $count < $length && $text[$offset + $count] === $char) {
            $count++;
        }

        return $count;
    }

    private function findClosingInlineDelimiter(string $text, int $offset, string $char, int $size): ?int
    {
        $needle = str_repeat($char, $size);
        $position = strpos($text, $needle, $offset);
        while ($position !== false) {
            $runLength = $this->countDelimiterRun($text, $position, $char);
            if ($size === 1 && (($text[$position - 1] ?? '') === $char || $runLength > 1)) {
                $position = strpos($text, $needle, $position + 1);
                continue;
            }

            if ($runLength >= $size
                && $this->canCloseInlineDelimiter($text, $position, $char, $size)
            ) {
                return $position;
            }

            $position = strpos($text, $needle, $position + 1);
        }

        return null;
    }

    private function canOpenInlineDelimiter(string $text, int $offset, string $char, int $size): bool
    {
        if ($char !== '_') {
            return true;
        }

        $previous = $offset > 0 ? $text[$offset - 1] : '';
        $nextOffset = $offset + $size;
        $next = $nextOffset < strlen($text) ? $text[$nextOffset] : '';
        if ($this->isAsciiAlnum($previous) && ($next === '{' || $next === '}')) {
            return false;
        }

        if ($this->isIntrawordUnderscoreBoundary($previous, $next)) {
            return false;
        }

        return true;
    }

    private function canCloseInlineDelimiter(string $text, int $offset, string $char, int $size): bool
    {
        if ($char !== '_') {
            return true;
        }

        $previous = $offset > 0 ? $text[$offset - 1] : '';
        $nextOffset = $offset + $size;
        $next = $nextOffset < strlen($text) ? $text[$nextOffset] : '';
        if (($previous === '{' || $previous === '}') && $this->isAsciiAlnum($next)) {
            return false;
        }

        if ($this->isIntrawordUnderscoreBoundary($previous, $next)) {
            return false;
        }

        return true;
    }

    private function isIntrawordUnderscoreBoundary(string $previous, string $next): bool
    {
        return $this->isAsciiAlnum($previous) && $this->isAsciiAlnum($next);
    }

    private function isAsciiAlnum(string $char): bool
    {
        return $char !== '' && preg_match('/[A-Za-z0-9]/', $char) === 1;
    }

    private function countBackticks(string $text, int $offset): int
    {
        $count = 0;
        $length = strlen($text);
        while ($offset + $count < $length && $text[$offset + $count] === '`') {
            $count++;
        }

        return $count;
    }

    private function findMatchingBacktickRun(string $text, int $offset, int $tickCount): ?int
    {
        $needle = str_repeat('`', $tickCount);
        $position = strpos($text, $needle, $offset);
        while ($position !== false) {
            $runLength = $this->countBackticks($text, $position);
            if ($runLength === $tickCount) {
                return $position;
            }

            $position = strpos($text, $needle, $position + $runLength);
        }

        return null;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function flushText(string &$buffer, array &$nodes): void
    {
        if ($buffer === '') {
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $this->decodeHtmlEntities($buffer)]);
        $buffer = '';
    }

    private function decodeHtmlEntities(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
