<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownReader
{
    private const MARKDOWN_ESCAPABLE_ASCII_PUNCTUATION = "!\"#$%&'()*+,-./:;<=>?@[\\]^_`{|}~";
    private const SUPPORTED_YAML_METADATA_VERSIONS = ['1.1', '1.2'];
    private const YAML_TAG_SUFFIX_PATTERN = '[A-Za-z0-9_.:\/\?#@!$&()*+;=%~-]+';

    /** @var array<string, array{url:string, title:string}> */
    private array $referenceLinks = [];

    /** @var array<string, string> */
    private array $footnoteDefinitions = [];

    /** @var array<string, string> */
    private array $abbreviationDefinitions = [];

    /** @var array<string, int> */
    private array $exampleReferences = [];

    /** @var array<int, int> */
    private array $exampleNumbersByLine = [];

    /** @var array<string, array{arity:int, template:string}> */
    private array $rawTexMacros = [];

    /** @var array<string, mixed> */
    private array $yamlMetadataAnchors = [];

    /** @var list<array<string, string>> */
    private array $yamlMetadataDiagnostics = [];

    /** @var list<string> */
    private array $yamlMetadataDiagnosticPath = [];

    private ?int $yamlMetadataCurrentSourceLine = null;

    /** @var list<array<string, string>> */
    private array $yamlMetadataTagProvenance = [];

    /** @var list<array<string, string>> */
    private array $yamlMetadataDirectiveProvenance = [];

    /** @var list<array<string, string>> */
    private array $yamlMetadataCommentProvenance = [];

    /** @var list<array<string, string>> */
    private array $yamlMetadataAnchorProvenance = [];

    /** @var list<array<string, string>> */
    private array $yamlMetadataAliasProvenance = [];

    /** @var list<array<string, string>> */
    private array $yamlMetadataMergeProvenance = [];

    /** @var list<array<string, string>> */
    private array $yamlMetadataScalarProvenance = [];

    private bool $yamlMetadataRecordScalarProvenance = true;

    /** @var list<array<string, string>> */
    private array $yamlMetadataCollectionProvenance = [];

    private bool $yamlMetadataRecordCollectionProvenance = true;

    private bool $yamlMetadataInvalid = false;

    /** @var array<string, string> */
    private array $yamlMetadataTagHandles = [
        '!!' => 'tag:yaml.org,2002:',
    ];

    private string $yamlMetadataSchemaVersion = '1.1';

    private bool $resolveFootnoteReferences = true;

    private int $sectionDivsSuppressionDepth = 0;

    private bool $nativeSpanInlineEnabled = false;

    /**
     * @param array{literateHaskell?: bool, yamlMetadata?: bool, texMathDoubleBackslash?: bool, eastAsianLineBreaks?: bool, sectionDivs?: bool, nativeSpans?: bool} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function readBytes(string $bytes, ?string $encoding = null, ?string $normalizationForm = null): AstNode
    {
        $decoded = UnicodeText::decodeBytes($bytes, $encoding, $normalizationForm);
        $document = $this->read($decoded['text']);
        $sourceEncoding = [
            'encoding' => $decoded['encoding'],
            'bom' => $decoded['bom'],
            'repairs' => $decoded['repairs'],
        ];
        if (($decoded['diagnostics'] ?? []) !== []) {
            $sourceEncoding['diagnostics'] = $decoded['diagnostics'];
        }
        $attrs = array_replace($document->attrs, [
            'sourceEncoding' => $sourceEncoding,
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
        $previousAbbreviationDefinitions = $this->abbreviationDefinitions;
        $previousExampleReferences = $this->exampleReferences;
        $previousExampleNumbersByLine = $this->exampleNumbersByLine;
        $previousRawTexMacros = $this->rawTexMacros;
        $previousNativeSpanInlineEnabled = $this->nativeSpanInlineEnabled;
        $documentAttrs = [];
        $yamlMetadata = null;
        if (($this->options['yamlMetadata'] ?? true) !== false) {
            [$lines, $yamlMetadata] = $this->extractYamlMetadataBlocks($lines);
        }
        $this->nativeSpanInlineEnabled = ($this->options['nativeSpans'] ?? false) === true
            || $this->metadataEnablesNativeSpans($yamlMetadata);
        [$lines, $titleBlock] = $this->extractTitleBlock($lines);
        [$lines, $abbreviations] = $this->extractAbbreviationDefinitions($lines);
        [$lines, $references, $footnotes] = $this->extractReferenceDefinitions($lines);
        $lines = $this->splitMixedHtmlFlowLines($lines);
        [$exampleReferences, $exampleNumbersByLine] = $this->collectNumberedExampleReferences($lines);
        [$markdownHeadingIds, $implicitHeadingReferences] = $this->collectMarkdownHeadingReferences($lines);
        $this->referenceLinks = array_replace($previousReferenceLinks, $implicitHeadingReferences, $references);
        $this->footnoteDefinitions = array_replace($previousFootnoteDefinitions, $footnotes);
        $this->abbreviationDefinitions = $this->sortedAbbreviationDefinitions(array_replace($previousAbbreviationDefinitions, $abbreviations));
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
            $fencedDivBlock = $paragraph === [] && $listStack === [] ? $this->tryReadFencedDivBlock($lines, $index) : null;
            if ($fencedDivBlock !== null) {
                $blocks[] = $fencedDivBlock;
                continue;
            }
            $divBlock = $paragraph === [] && $listStack === [] ? $this->tryReadDivBlock($lines, $index) : null;
            if ($divBlock !== null) {
                $blocks[] = $divBlock;
                continue;
            }
            $docBookList = $paragraph === [] && $listStack === [] ? $this->tryReadDocBookListBlock($lines, $index) : null;
            if ($docBookList !== null) {
                $blocks[] = $docBookList;
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
            if (
                $paragraph !== []
                && $listStack === []
                && $this->isCommonMarkParagraphInterruptingRawHtmlBlockStart($line)
            ) {
                $this->flushParagraph($paragraph, $blocks);
                $rawHtmlBlock = $this->tryReadRawHtmlBlock($lines, $index);
                if ($rawHtmlBlock !== null) {
                    $blocks[] = $rawHtmlBlock;
                    continue;
                }
            }
            if ($paragraph === [] && $listStack === [] && $this->trySkipEmptyHtmlCommentSeparator($lines, $index, $blocks)) {
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
            $tableWithLeadingCaption = $paragraph === [] && $listStack === [] ? $this->tryReadTableWithLeadingCaption($lines, $index) : null;
            if ($tableWithLeadingCaption !== null) {
                $blocks[] = $tableWithLeadingCaption;
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
                $index = $setextHeading['endIndex'];
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
            $listBlock = ($paragraph === [] || $listStack === [])
                ? $this->tryReadListBlock($lines, $index, $paragraph !== [])
                : null;
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
            $paragraph[] = $this->normalizeParagraphLine($line);
        }
        $this->flushParagraph($paragraph, $blocks);
        $this->flushListStack($listStack, $blocks);

        if ($this->sectionDivsEnabled()) {
            $blocks = $this->sectionizeMarkdownHeadingBlocks($blocks);
        }

        $document = new AstNode('document', $documentAttrs, $blocks);
        $this->referenceLinks = $previousReferenceLinks;
        $this->footnoteDefinitions = $previousFootnoteDefinitions;
        $this->abbreviationDefinitions = $previousAbbreviationDefinitions;
        $this->exampleReferences = $previousExampleReferences;
        $this->exampleNumbersByLine = $previousExampleNumbersByLine;
        $this->rawTexMacros = $previousRawTexMacros;
        $this->nativeSpanInlineEnabled = $previousNativeSpanInlineEnabled;

        return $document;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function metadataEnablesNativeSpans(?array $metadata): bool
    {
        if ($metadata === null) {
            return false;
        }

        return $this->metadataExtensionValueEnablesNativeSpans($metadata['extension'] ?? null)
            || (
                isset($metadata['review']) && is_array($metadata['review'])
                && $this->metadataExtensionValueEnablesNativeSpans($metadata['review']['extension'] ?? null)
            );
    }

    private function metadataExtensionValueEnablesNativeSpans(mixed $extension): bool
    {
        if (is_string($extension)) {
            return trim($extension) === 'native_spans';
        }

        if (is_array($extension)) {
            foreach ($extension as $value) {
                if ($this->metadataExtensionValueEnablesNativeSpans($value)) {
                    return true;
                }
            }
        }

        return false;
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
        $previousYamlMetadataEndedWithDocumentEnd = false;
        $yamlMetadataDocumentIndex = 0;

        for ($index = 0; $index < $count; $index++) {
            $implicitBlock = $index === 0 ? $this->tryReadImplicitYamlMetadataBlock($lines) : null;
            if ($implicitBlock !== null) {
                $yamlMetadataDocumentIndex++;
                $implicitBlock['metadata']['__yamlMetadataStreamProvenance'][] = $this->yamlMetadataStreamProvenanceEntry(
                    $implicitBlock,
                    $yamlMetadataDocumentIndex,
                    'implicit',
                    0
                );
                $metadata = $this->mergeYamlMetadataBlock($metadata, $implicitBlock['metadata']);
                $hasMetadata = true;
                $previousYamlMetadataEndedWithDocumentEnd = ($implicitBlock['endMarker'] ?? null) === '...';
                $index = $implicitBlock['end'];
                continue;
            }

            $fencedCodeEnd = $this->yamlMetadataFencedCodeBlockEnd($lines, $index);
            if ($fencedCodeEnd !== null) {
                $previousYamlMetadataEndedWithDocumentEnd = false;
                for (; $index <= $fencedCodeEnd; $index++) {
                    $bodyLines[] = $lines[$index];
                }
                $index--;
                continue;
            }

            $block = $this->tryReadYamlMetadataBlock($lines, $index, $previousYamlMetadataEndedWithDocumentEnd);
            if ($block !== null) {
                $yamlMetadataDocumentIndex++;
                $block['metadata']['__yamlMetadataStreamProvenance'][] = $this->yamlMetadataStreamProvenanceEntry(
                    $block,
                    $yamlMetadataDocumentIndex,
                    'explicit',
                    $index
                );
                $metadata = $this->mergeYamlMetadataBlock($metadata, $block['metadata']);
                $hasMetadata = true;
                $previousYamlMetadataEndedWithDocumentEnd = ($block['endMarker'] ?? null) === '...';
                $index = $block['end'];
                continue;
            }

            $previousYamlMetadataEndedWithDocumentEnd = false;
            $bodyLines[] = $lines[$index];
        }

        return [$bodyLines, $hasMetadata ? $metadata : null];
    }

    /**
     * @param array{end:int, endMarker:string, metadata:array<string, mixed>} $block
     * @return array<string, string>
     */
    private function yamlMetadataStreamProvenanceEntry(
        array $block,
        int $documentIndex,
        string $source,
        int $startIndex
    ): array {
        $fields = [];
        foreach (array_keys($block['metadata']) as $field) {
            $fieldName = (string) $field;
            if (str_starts_with($fieldName, '__yamlMetadata')) {
                continue;
            }

            $fields[] = $fieldName;
        }

        $fieldsJson = json_encode(array_values($fields), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($fieldsJson)) {
            $fieldsJson = '[]';
        }

        return [
            'type' => 'yaml-document',
            'documentIndex' => (string) $documentIndex,
            'source' => $source,
            'openingMarker' => $source === 'implicit' ? 'omitted' : '---',
            'endMarker' => $block['endMarker'],
            'startLine' => (string) ($startIndex + 1),
            'contentStartLine' => (string) ($source === 'implicit' ? $startIndex + 1 : $startIndex + 2),
            'endLine' => (string) ($block['end'] + 1),
            'fieldCount' => (string) count($fields),
            'fields' => $fieldsJson,
        ];
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
     * @return array{end:int, endMarker:string, metadata:array<string, mixed>}|null
     */
    private function tryReadImplicitYamlMetadataBlock(array $lines): ?array
    {
        $firstLine = $lines[0] ?? null;
        if ($firstLine === null || !$this->canStartImplicitYamlMetadataBlock(trim($firstLine))) {
            return null;
        }

        $yamlLines = [];
        $count = count($lines);
        for ($cursor = 0; $cursor < $count; $cursor++) {
            $marker = $this->yamlMetadataDocumentMarker($lines[$cursor]);
            if ($cursor > 0 && $marker !== null) {
                if ($marker === '---' && $this->isYamlDirectiveDocumentStartMarker($yamlLines)) {
                    $yamlLines[] = $lines[$cursor];
                    continue;
                }

                $metadata = $this->parseIsolatedYamlMetadataBlock($yamlLines);
                if ($metadata === []) {
                    return null;
                }

                $metadata = $this->mergeYamlDocumentMarkerCommentProvenance(
                    $metadata,
                    [],
                    [
                        $this->yamlDocumentMarkerCommentProvenanceEntry(
                            $lines[$cursor],
                            'closing',
                            $cursor + 1
                        ),
                    ]
                );

                return ['end' => $cursor, 'endMarker' => $marker, 'metadata' => $metadata];
            }

            $yamlLines[] = $lines[$cursor];
        }

        return null;
    }

    private function canStartImplicitYamlMetadataBlock(string $trimmed): bool
    {
        if ($trimmed === '' || str_starts_with($trimmed, '#') || $trimmed === '---') {
            return false;
        }

        $directive = trim($this->stripYamlTrailingComment($trimmed));
        if (
            preg_match('/^%YAML[ \t]+\d+(?:\.\d+)?$/i', $directive) === 1
            || preg_match('/^%TAG[ \t]+(!|!!|![A-Za-z0-9_.-]+!)[ \t]+\S+$/', $directive) === 1
            || $this->parseYamlReservedDirective($directive) !== null
        ) {
            return true;
        }

        if ($trimmed[0] === '{') {
            return true;
        }

        return $this->parseYamlMappingLine($trimmed) !== null || $this->isYamlExplicitMappingKeyLine($trimmed);
    }

    /**
     * @param list<string> $lines
     * @return array{end:int, endMarker:string, metadata:array<string, mixed>}|null
     */
    private function tryReadYamlMetadataBlock(
        array $lines,
        int $start,
        bool $allowAdjacentStreamDocument = false
    ): ?array
    {
        if ($this->yamlMetadataDocumentMarker($lines[$start] ?? '') !== '---') {
            return null;
        }

        if (
            $start > 0
            && trim($lines[$start - 1]) !== ''
            && !($allowAdjacentStreamDocument && $this->yamlMetadataDocumentMarker($lines[$start - 1]) === '...')
        ) {
            return null;
        }

        if (!isset($lines[$start + 1]) || trim($lines[$start + 1]) === '') {
            return null;
        }

        $yamlLines = [];
        $count = count($lines);
        for ($cursor = $start + 1; $cursor < $count; $cursor++) {
            $marker = $this->yamlMetadataDocumentMarker($lines[$cursor]);
            if ($marker !== null) {
                if ($marker === '---' && $this->isYamlDirectiveDocumentStartMarker($yamlLines)) {
                    $yamlLines[] = $lines[$cursor];
                    continue;
                }

                $metadata = $this->parseIsolatedYamlMetadataBlock($yamlLines, $start + 2);
                if ($metadata === []) {
                    return null;
                }

                $metadata = $this->mergeYamlDocumentMarkerCommentProvenance(
                    $metadata,
                    [
                        $this->yamlDocumentMarkerCommentProvenanceEntry(
                            $lines[$start],
                            'opening',
                            $start + 1
                        ),
                    ],
                    [
                        $this->yamlDocumentMarkerCommentProvenanceEntry(
                            $lines[$cursor],
                            'closing',
                            $cursor + 1
                        ),
                    ]
                );

                return ['end' => $cursor, 'endMarker' => $marker, 'metadata' => $metadata];
            }

            $yamlLines[] = $lines[$cursor];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array<string, string>|null> $prefixEntries
     * @param list<array<string, string>|null> $suffixEntries
     * @return array<string, mixed>
     */
    private function mergeYamlDocumentMarkerCommentProvenance(
        array $metadata,
        array $prefixEntries,
        array $suffixEntries
    ): array {
        $prefixEntries = array_values(array_filter($prefixEntries));
        $suffixEntries = array_values(array_filter($suffixEntries));
        if ($prefixEntries === [] && $suffixEntries === []) {
            return $metadata;
        }

        $comments = $this->yamlMetadataCommentProvenanceList($metadata['__yamlMetadataCommentProvenance'] ?? []);
        $metadata['__yamlMetadataCommentProvenance'] = array_merge($prefixEntries, $comments, $suffixEntries);

        return $metadata;
    }

    /**
     * @return array<string, string>|null
     */
    private function yamlDocumentMarkerCommentProvenanceEntry(
        string $line,
        string $markerRole,
        ?int $sourceLine = null
    ): ?array {
        [$source, $comment] = $this->splitYamlTrailingComment($line);
        if ($comment === null || $comment === '') {
            return null;
        }

        $marker = trim($source);
        if ($marker !== '---' && $marker !== '...') {
            return null;
        }

        $entry = [
            'type' => 'yaml-comment',
            'context' => 'document-marker',
            'comment' => $comment,
            'path' => '',
            'marker' => $marker,
            'markerRole' => $markerRole,
        ];
        if ($sourceLine !== null) {
            $entry['sourceLine'] = (string) $sourceLine;
        }

        return $entry;
    }

    /**
     * @param list<string> $yamlLines
     * @return array<string, mixed>
     */
    private function parseIsolatedYamlMetadataBlock(array $yamlLines, int $startLine = 1): array
    {
        $previousYamlMetadataAnchors = $this->yamlMetadataAnchors;
        $previousYamlMetadataDiagnostics = $this->yamlMetadataDiagnostics;
        $previousYamlMetadataDiagnosticPath = $this->yamlMetadataDiagnosticPath;
        $previousYamlMetadataCurrentSourceLine = $this->yamlMetadataCurrentSourceLine;
        $previousYamlMetadataTagProvenance = $this->yamlMetadataTagProvenance;
        $previousYamlMetadataDirectiveProvenance = $this->yamlMetadataDirectiveProvenance;
        $previousYamlMetadataCommentProvenance = $this->yamlMetadataCommentProvenance;
        $previousYamlMetadataAnchorProvenance = $this->yamlMetadataAnchorProvenance;
        $previousYamlMetadataAliasProvenance = $this->yamlMetadataAliasProvenance;
        $previousYamlMetadataMergeProvenance = $this->yamlMetadataMergeProvenance;
        $previousYamlMetadataScalarProvenance = $this->yamlMetadataScalarProvenance;
        $previousYamlMetadataCollectionProvenance = $this->yamlMetadataCollectionProvenance;
        $previousYamlMetadataInvalid = $this->yamlMetadataInvalid;
        $previousYamlMetadataTagHandles = $this->yamlMetadataTagHandles;
        $previousYamlMetadataSchemaVersion = $this->yamlMetadataSchemaVersion;
        $this->yamlMetadataAnchors = [];
        $this->yamlMetadataDiagnostics = [];
        $this->yamlMetadataDiagnosticPath = [];
        $this->yamlMetadataCurrentSourceLine = null;
        $this->yamlMetadataTagProvenance = [];
        $this->yamlMetadataDirectiveProvenance = [];
        $this->yamlMetadataCommentProvenance = [];
        $this->yamlMetadataAnchorProvenance = [];
        $this->yamlMetadataAliasProvenance = [];
        $this->yamlMetadataMergeProvenance = [];
        $this->yamlMetadataScalarProvenance = [];
        $this->yamlMetadataCollectionProvenance = [];
        $this->yamlMetadataInvalid = false;
        $this->yamlMetadataTagHandles = ['!!' => 'tag:yaml.org,2002:'];
        $this->yamlMetadataSchemaVersion = '1.1';
        try {
            if ($this->yamlMetadataHasLeadingTabIndentation($yamlLines)) {
                return [];
            }

            $metadata = $this->parseYamlMetadataLines(
                $yamlLines,
                true,
                $this->yamlMetadataLineNumbers(count($yamlLines), $startLine)
            );
            if ($this->yamlMetadataInvalid) {
                return [];
            }
            if ($this->yamlMetadataDiagnostics !== []) {
                $metadata['__yamlMetadataDiagnostics'] = $this->yamlMetadataDiagnostics;
            }
            if ($this->yamlMetadataTagProvenance !== []) {
                $metadata['__yamlMetadataTagProvenance'] = $this->yamlMetadataTagProvenance;
            }
            if ($this->yamlMetadataDirectiveProvenance !== []) {
                $metadata['__yamlMetadataDirectiveProvenance'] = $this->yamlMetadataDirectiveProvenance;
            }
            if ($this->yamlMetadataCommentProvenance !== []) {
                $metadata['__yamlMetadataCommentProvenance'] = $this->yamlMetadataCommentProvenance;
            }
            if ($this->yamlMetadataAnchorProvenance !== []) {
                $metadata['__yamlMetadataAnchorProvenance'] = $this->yamlMetadataAnchorProvenance;
            }
            if ($this->yamlMetadataAliasProvenance !== []) {
                $metadata['__yamlMetadataAliasProvenance'] = $this->yamlMetadataAliasProvenance;
            }
            if ($this->yamlMetadataMergeProvenance !== []) {
                $metadata['__yamlMetadataMergeProvenance'] = $this->yamlMetadataMergeProvenance;
            }
            if ($this->yamlMetadataScalarProvenance !== []) {
                $metadata['__yamlMetadataScalarProvenance'] = $this->yamlMetadataScalarProvenance;
            }
            if ($this->yamlMetadataCollectionProvenance !== []) {
                $metadata['__yamlMetadataCollectionProvenance'] = $this->yamlMetadataCollectionProvenance;
            }

            return $metadata;
        } finally {
            $this->yamlMetadataAnchors = $previousYamlMetadataAnchors;
            $this->yamlMetadataDiagnostics = $previousYamlMetadataDiagnostics;
            $this->yamlMetadataDiagnosticPath = $previousYamlMetadataDiagnosticPath;
            $this->yamlMetadataCurrentSourceLine = $previousYamlMetadataCurrentSourceLine;
            $this->yamlMetadataTagProvenance = $previousYamlMetadataTagProvenance;
            $this->yamlMetadataDirectiveProvenance = $previousYamlMetadataDirectiveProvenance;
            $this->yamlMetadataCommentProvenance = $previousYamlMetadataCommentProvenance;
            $this->yamlMetadataAnchorProvenance = $previousYamlMetadataAnchorProvenance;
            $this->yamlMetadataAliasProvenance = $previousYamlMetadataAliasProvenance;
            $this->yamlMetadataMergeProvenance = $previousYamlMetadataMergeProvenance;
            $this->yamlMetadataScalarProvenance = $previousYamlMetadataScalarProvenance;
            $this->yamlMetadataCollectionProvenance = $previousYamlMetadataCollectionProvenance;
            $this->yamlMetadataInvalid = $previousYamlMetadataInvalid;
            $this->yamlMetadataTagHandles = $previousYamlMetadataTagHandles;
            $this->yamlMetadataSchemaVersion = $previousYamlMetadataSchemaVersion;
        }
    }

    /**
     * @param list<string> $yamlLines
     */
    private function yamlMetadataHasLeadingTabIndentation(array $yamlLines): bool
    {
        foreach ($yamlLines as $line) {
            if ($line !== '' && $line[0] === "\t" && trim($line) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $next
     * @return array<string, mixed>
     */
    private function mergeYamlMetadataBlock(array $current, array $next): array
    {
        $currentDiagnostics = $this->yamlMetadataDiagnosticList($current['__yamlMetadataDiagnostics'] ?? []);
        $nextDiagnostics = $this->yamlMetadataDiagnosticList($next['__yamlMetadataDiagnostics'] ?? []);
        $currentTags = $this->yamlMetadataTagProvenanceList($current['__yamlMetadataTagProvenance'] ?? []);
        $nextTags = $this->yamlMetadataTagProvenanceList($next['__yamlMetadataTagProvenance'] ?? []);
        $currentDirectives = $this->yamlMetadataDirectiveProvenanceList($current['__yamlMetadataDirectiveProvenance'] ?? []);
        $nextDirectives = $this->yamlMetadataDirectiveProvenanceList($next['__yamlMetadataDirectiveProvenance'] ?? []);
        $currentComments = $this->yamlMetadataCommentProvenanceList($current['__yamlMetadataCommentProvenance'] ?? []);
        $nextComments = $this->yamlMetadataCommentProvenanceList($next['__yamlMetadataCommentProvenance'] ?? []);
        $currentAnchors = $this->yamlMetadataAnchorProvenanceList($current['__yamlMetadataAnchorProvenance'] ?? []);
        $nextAnchors = $this->yamlMetadataAnchorProvenanceList($next['__yamlMetadataAnchorProvenance'] ?? []);
        $currentAliases = $this->yamlMetadataAliasProvenanceList($current['__yamlMetadataAliasProvenance'] ?? []);
        $nextAliases = $this->yamlMetadataAliasProvenanceList($next['__yamlMetadataAliasProvenance'] ?? []);
        $currentMerges = $this->yamlMetadataMergeProvenanceList($current['__yamlMetadataMergeProvenance'] ?? []);
        $nextMerges = $this->yamlMetadataMergeProvenanceList($next['__yamlMetadataMergeProvenance'] ?? []);
        $currentScalars = $this->yamlMetadataScalarProvenanceList($current['__yamlMetadataScalarProvenance'] ?? []);
        $nextScalars = $this->yamlMetadataScalarProvenanceList($next['__yamlMetadataScalarProvenance'] ?? []);
        $currentCollections = $this->yamlMetadataCollectionProvenanceList($current['__yamlMetadataCollectionProvenance'] ?? []);
        $nextCollections = $this->yamlMetadataCollectionProvenanceList($next['__yamlMetadataCollectionProvenance'] ?? []);
        $currentStreams = $this->yamlMetadataStreamProvenanceList($current['__yamlMetadataStreamProvenance'] ?? []);
        $nextStreams = $this->yamlMetadataStreamProvenanceList($next['__yamlMetadataStreamProvenance'] ?? []);
        $currentFieldQuoteMap = $this->yamlMetadataFieldQuoteMap($current['__yamlMetadataFieldQuoteMap'] ?? []);
        $nextFieldQuoteMap = $this->yamlMetadataFieldQuoteMap($next['__yamlMetadataFieldQuoteMap'] ?? []);
        $streamOverrideDiagnostics = $this->yamlMetadataStreamOverrideDiagnostics(
            $this->yamlMetadataUserFields($current),
            $this->yamlMetadataUserFields($next),
            $currentStreams,
            $nextStreams
        );
        unset(
            $current['__yamlMetadataDiagnostics'],
            $next['__yamlMetadataDiagnostics'],
            $current['__yamlMetadataTagProvenance'],
            $next['__yamlMetadataTagProvenance'],
            $current['__yamlMetadataDirectiveProvenance'],
            $next['__yamlMetadataDirectiveProvenance'],
            $current['__yamlMetadataCommentProvenance'],
            $next['__yamlMetadataCommentProvenance'],
            $current['__yamlMetadataAnchorProvenance'],
            $next['__yamlMetadataAnchorProvenance'],
            $current['__yamlMetadataAliasProvenance'],
            $next['__yamlMetadataAliasProvenance'],
            $current['__yamlMetadataMergeProvenance'],
            $next['__yamlMetadataMergeProvenance'],
            $current['__yamlMetadataScalarProvenance'],
            $next['__yamlMetadataScalarProvenance'],
            $current['__yamlMetadataCollectionProvenance'],
            $next['__yamlMetadataCollectionProvenance'],
            $current['__yamlMetadataStreamProvenance'],
            $next['__yamlMetadataStreamProvenance'],
            $current['__yamlMetadataFieldQuoteMap'],
            $next['__yamlMetadataFieldQuoteMap']
        );

        $merged = array_replace($current, $next);
        $diagnostics = array_merge($currentDiagnostics, $nextDiagnostics, $streamOverrideDiagnostics);
        if ($diagnostics !== []) {
            $merged['__yamlMetadataDiagnostics'] = $diagnostics;
        }
        $tagProvenance = array_merge($currentTags, $nextTags);
        if ($tagProvenance !== []) {
            $merged['__yamlMetadataTagProvenance'] = $tagProvenance;
        }
        $directiveProvenance = array_merge($currentDirectives, $nextDirectives);
        if ($directiveProvenance !== []) {
            $merged['__yamlMetadataDirectiveProvenance'] = $directiveProvenance;
        }
        $commentProvenance = array_merge($currentComments, $nextComments);
        if ($commentProvenance !== []) {
            $merged['__yamlMetadataCommentProvenance'] = $commentProvenance;
        }
        $anchorProvenance = array_merge($currentAnchors, $nextAnchors);
        if ($anchorProvenance !== []) {
            $merged['__yamlMetadataAnchorProvenance'] = $anchorProvenance;
        }
        $aliasProvenance = array_merge($currentAliases, $nextAliases);
        if ($aliasProvenance !== []) {
            $merged['__yamlMetadataAliasProvenance'] = $aliasProvenance;
        }
        $mergeProvenance = array_merge($currentMerges, $nextMerges);
        if ($mergeProvenance !== []) {
            $merged['__yamlMetadataMergeProvenance'] = $mergeProvenance;
        }
        $scalarProvenance = array_merge($currentScalars, $nextScalars);
        if ($scalarProvenance !== []) {
            $merged['__yamlMetadataScalarProvenance'] = $scalarProvenance;
        }
        $collectionProvenance = array_merge($currentCollections, $nextCollections);
        if ($collectionProvenance !== []) {
            $merged['__yamlMetadataCollectionProvenance'] = $collectionProvenance;
        }
        $streamProvenance = array_merge($currentStreams, $nextStreams);
        if ($streamProvenance !== []) {
            $merged['__yamlMetadataStreamProvenance'] = $streamProvenance;
        }
        $fieldQuoteMap = array_replace($currentFieldQuoteMap, $nextFieldQuoteMap);
        if ($fieldQuoteMap !== []) {
            $merged['__yamlMetadataFieldQuoteMap'] = $fieldQuoteMap;
        }

        return $merged;
    }

    /**
     * @param array<string, true> $currentFields
     * @param array<string, true> $nextFields
     * @param list<array<string, string>> $currentStreams
     * @param list<array<string, string>> $nextStreams
     * @return list<array<string, string>>
     */
    private function yamlMetadataStreamOverrideDiagnostics(
        array $currentFields,
        array $nextFields,
        array $currentStreams,
        array $nextStreams
    ): array {
        if ($currentFields === [] || $nextFields === [] || $currentStreams === [] || $nextStreams === []) {
            return [];
        }

        $diagnostics = [];
        foreach (array_keys($nextFields) as $field) {
            if (!array_key_exists($field, $currentFields)) {
                continue;
            }

            $currentStream = $this->yamlMetadataLatestStreamForField($currentStreams, $field);
            $nextStream = $this->yamlMetadataLatestStreamForField($nextStreams, $field);
            if ($currentStream === null || $nextStream === null) {
                continue;
            }

            $diagnostic = [
                'type' => 'yaml-stream',
                'reason' => 'stream-field-overridden',
                'field' => $field,
                'path' => $this->yamlMetadataTopLevelPath($field),
                'previousDocumentIndex' => $currentStream['documentIndex'] ?? '',
                'documentIndex' => $nextStream['documentIndex'] ?? '',
                'previousSource' => $currentStream['source'] ?? '',
                'source' => $nextStream['source'] ?? '',
            ];
            foreach ([
                'startLine' => 'previousStartLine',
                'endLine' => 'previousEndLine',
            ] as $streamKey => $diagnosticKey) {
                if (($currentStream[$streamKey] ?? '') !== '') {
                    $diagnostic[$diagnosticKey] = $currentStream[$streamKey];
                }
            }
            foreach (['startLine', 'endLine'] as $streamKey) {
                if (($nextStream[$streamKey] ?? '') !== '') {
                    $diagnostic[$streamKey] = $nextStream[$streamKey];
                }
            }

            $diagnostics[] = $diagnostic;
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, true>
     */
    private function yamlMetadataUserFields(array $metadata): array
    {
        $fields = [];
        foreach (array_keys($metadata) as $field) {
            $fieldName = (string) $field;
            if ($fieldName === '' || str_starts_with($fieldName, '__yamlMetadata')) {
                continue;
            }

            $fields[$fieldName] = true;
        }

        return $fields;
    }

    /**
     * @param list<array<string, string>> $streams
     * @return array<string, string>|null
     */
    private function yamlMetadataLatestStreamForField(array $streams, string $field): ?array
    {
        for ($index = count($streams) - 1; $index >= 0; $index--) {
            if ($this->yamlMetadataStreamHasField($streams[$index], $field)) {
                return $streams[$index];
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $stream
     */
    private function yamlMetadataStreamHasField(array $stream, string $field): bool
    {
        $decoded = json_decode($stream['fields'] ?? '[]', true);
        if (!is_array($decoded)) {
            return false;
        }

        foreach ($decoded as $candidate) {
            if ((string) $candidate === $field) {
                return true;
            }
        }

        return false;
    }

    private function yamlMetadataTopLevelPath(string $field): string
    {
        return '/' . str_replace(['~', '/'], ['~0', '~1'], $field);
    }

    private function recordYamlDuplicateKeyDiagnostic(int|string $key): void
    {
        $field = (string) $key;
        $diagnostic = [
            'type' => 'yaml-duplicate-key',
            'reason' => 'duplicate-key',
            'field' => $field,
        ];
        $path = $this->yamlMetadataPathWithSegment($field);
        if ($path !== '') {
            $diagnostic['path'] = $path;
        }

        $diagnostic += $this->yamlMetadataSourceLineAttrs();
        $this->yamlMetadataDiagnostics[] = $diagnostic;
    }

    /**
     * @return list<array<string, string>>
     */
    private function yamlMetadataDiagnosticList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $diagnostics = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $diagnostics[] = array_filter(
                    $item,
                    static fn (mixed $entry): bool => is_string($entry)
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, string>>
     */
    private function yamlMetadataTagProvenanceList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $provenance = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $provenance[] = array_filter(
                    $item,
                    static fn (mixed $entry): bool => is_string($entry)
                );
            }
        }

        return $provenance;
    }

    /**
     * @return list<array<string, string>>
     */
    private function yamlMetadataDirectiveProvenanceList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $provenance = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $provenance[] = array_filter(
                    $item,
                    static fn (mixed $entry): bool => is_string($entry)
                );
            }
        }

        return $provenance;
    }

    /**
     * @return list<array<string, string>>
     */
    private function yamlMetadataCommentProvenanceList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $provenance = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $provenance[] = array_filter(
                    $item,
                    static fn (mixed $entry): bool => is_string($entry)
                );
            }
        }

        return $provenance;
    }

    /**
     * @return list<array<string, string>>
     */
    private function yamlMetadataAnchorProvenanceList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $provenance = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $provenance[] = array_filter(
                    $item,
                    static fn (mixed $entry): bool => is_string($entry)
                );
            }
        }

        return $provenance;
    }

    /**
     * @return list<array<string, string>>
     */
    private function yamlMetadataAliasProvenanceList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $provenance = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $provenance[] = array_filter(
                    $item,
                    static fn (mixed $entry): bool => is_string($entry)
                );
            }
        }

        return $provenance;
    }

    /**
     * @return list<array<string, string>>
     */
    private function yamlMetadataMergeProvenanceList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $provenance = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $provenance[] = array_filter(
                    $item,
                    static fn (mixed $entry): bool => is_string($entry)
                );
            }
        }

        return $provenance;
    }

    /**
     * @return list<array<string, string>>
     */
    private function yamlMetadataScalarProvenanceList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $provenance = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $provenance[] = array_filter(
                    $item,
                    static fn (mixed $entry): bool => is_string($entry)
                );
            }
        }

        return $provenance;
    }

    /**
     * @return list<array<string, string>>
     */
    private function yamlMetadataCollectionProvenanceList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $provenance = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $provenance[] = array_filter(
                    $item,
                    static fn (mixed $entry): bool => is_string($entry)
                );
            }
        }

        return $provenance;
    }

    /**
     * @return list<array<string, string>>
     */
    private function yamlMetadataStreamProvenanceList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $provenance = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $provenance[] = array_filter(
                    $item,
                    static fn (mixed $entry): bool => is_string($entry)
                );
            }
        }

        return $provenance;
    }

    /**
     * @return array<string, bool>
     */
    private function yamlMetadataFieldQuoteMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $field => $quoted) {
            if (is_string($field) || is_int($field)) {
                $map[(string) $field] = $quoted === true;
            }
        }

        return $map;
    }

    private function withYamlMetadataPathSegment(int|string $segment, callable $callback): mixed
    {
        $this->yamlMetadataDiagnosticPath[] = (string) $segment;
        try {
            return $callback();
        } finally {
            array_pop($this->yamlMetadataDiagnosticPath);
        }
    }

    private function withYamlMetadataSourceLine(?int $sourceLine, callable $callback): mixed
    {
        $previous = $this->yamlMetadataCurrentSourceLine;
        $this->yamlMetadataCurrentSourceLine = $sourceLine;
        try {
            return $callback();
        } finally {
            $this->yamlMetadataCurrentSourceLine = $previous;
        }
    }

    private function withYamlMetadataScalarProvenanceRecording(bool $record, callable $callback): mixed
    {
        $previous = $this->yamlMetadataRecordScalarProvenance;
        $this->yamlMetadataRecordScalarProvenance = $record;
        try {
            return $callback();
        } finally {
            $this->yamlMetadataRecordScalarProvenance = $previous;
        }
    }

    private function withYamlMetadataCollectionProvenanceRecording(bool $record, callable $callback): mixed
    {
        $previous = $this->yamlMetadataRecordCollectionProvenance;
        $this->yamlMetadataRecordCollectionProvenance = $record;
        try {
            return $callback();
        } finally {
            $this->yamlMetadataRecordCollectionProvenance = $previous;
        }
    }

    private function parseYamlScalarKeyValue(string $value): mixed
    {
        return $this->withYamlMetadataScalarProvenanceRecording(
            false,
            fn (): mixed => $this->withYamlMetadataCollectionProvenanceRecording(
                false,
                fn (): mixed => $this->parseYamlScalarValue($value)
            )
        );
    }

    /**
     * @return array{0:string, 1:bool}
     */
    private function normalizeYamlPlainMappingKeyDirectives(string $key, bool $quotedKey): array
    {
        if ($quotedKey || !$this->yamlPlainMappingKeyMayStartWithDirective($key)) {
            return [$key, $quotedKey];
        }

        $tagStart = count($this->yamlMetadataTagProvenance);
        $anchorStart = count($this->yamlMetadataAnchorProvenance);
        $aliasStart = count($this->yamlMetadataAliasProvenance);
        [$source, $anchorName, $tags] = $this->parseYamlValueDirectives($key);
        if ($source === $key || trim($source) === '') {
            array_splice($this->yamlMetadataTagProvenance, $tagStart);
            array_splice($this->yamlMetadataAnchorProvenance, $anchorStart);
            array_splice($this->yamlMetadataAliasProvenance, $aliasStart);

            return [$key, false];
        }

        $value = $this->withYamlMetadataScalarProvenanceRecording(
            false,
            fn (): mixed => $this->withYamlMetadataCollectionProvenanceRecording(
                false,
                fn (): mixed => $this->parseYamlScalarValueFromDirectives($source, $anchorName, $tags)
            )
        );
        $normalized = $this->normalizeYamlExplicitMappingKey($value);
        if ($normalized === null || $normalized === '') {
            array_splice($this->yamlMetadataTagProvenance, $tagStart);
            array_splice($this->yamlMetadataAnchorProvenance, $anchorStart);
            array_splice($this->yamlMetadataAliasProvenance, $aliasStart);

            return [$key, false];
        }

        $this->retargetYamlTagProvenanceFrom($tagStart, $normalized);
        $this->retargetYamlAnchorProvenanceFrom($anchorStart, $normalized);
        $this->retargetYamlAliasProvenanceFrom($aliasStart, $normalized);

        return [$normalized, $this->yamlExplicitKeySourceStartsQuoted($source)];
    }

    private function yamlPlainMappingKeyMayStartWithDirective(string $key): bool
    {
        $key = ltrim($key);

        return $key !== '' && ($key[0] === '!' || $key[0] === '&');
    }

    /**
     * @return list<int|null>
     */
    private function yamlMetadataLineNumbers(int $count, int $startLine = 1): array
    {
        if ($count <= 0) {
            return [];
        }

        return range($startLine, $startLine + $count - 1);
    }

    /**
     * @return array{sourceLine?:string}
     */
    private function yamlMetadataSourceLineAttrs(): array
    {
        if ($this->yamlMetadataCurrentSourceLine === null) {
            return [];
        }

        return ['sourceLine' => (string) $this->yamlMetadataCurrentSourceLine];
    }

    private function yamlMetadataSourceLineWithOffset(?int $sourceLine, int $lineOffset): ?int
    {
        $base = $sourceLine ?? $this->yamlMetadataCurrentSourceLine;
        if ($base === null) {
            return null;
        }

        return $base + $lineOffset;
    }

    private function currentYamlMetadataDiagnosticPath(): ?string
    {
        if ($this->yamlMetadataDiagnosticPath === []) {
            return null;
        }

        return '/' . implode('/', array_map(
            static fn (string $segment): string => str_replace(['~', '/'], ['~0', '~1'], $segment),
            $this->yamlMetadataDiagnosticPath
        ));
    }

    private function yamlMetadataPathWithSegment(int|string $segment): string
    {
        $this->yamlMetadataDiagnosticPath[] = (string) $segment;
        try {
            return $this->currentYamlMetadataDiagnosticPath() ?? '';
        } finally {
            array_pop($this->yamlMetadataDiagnosticPath);
        }
    }

    private function retargetYamlTagProvenanceFrom(int $start, int|string $segment): void
    {
        $count = count($this->yamlMetadataTagProvenance);
        if ($start >= $count) {
            return;
        }

        $path = $this->yamlMetadataPathWithSegment($segment);
        for ($index = $start; $index < $count; $index++) {
            $this->yamlMetadataTagProvenance[$index]['path'] = $path;
        }
    }

    private function retargetYamlAnchorProvenanceFrom(int $start, int|string $segment): void
    {
        $count = count($this->yamlMetadataAnchorProvenance);
        if ($start >= $count) {
            return;
        }

        $path = $this->yamlMetadataPathWithSegment($segment);
        for ($index = $start; $index < $count; $index++) {
            $this->yamlMetadataAnchorProvenance[$index]['path'] = $path;
        }
    }

    private function retargetYamlAliasProvenanceFrom(int $start, int|string $segment): void
    {
        $count = count($this->yamlMetadataAliasProvenance);
        if ($start >= $count) {
            return;
        }

        $path = $this->yamlMetadataPathWithSegment($segment);
        for ($index = $start; $index < $count; $index++) {
            $this->yamlMetadataAliasProvenance[$index]['path'] = $path;
        }
    }

    private function recordYamlStandaloneCommentProvenance(string $trimmed): void
    {
        if (!str_starts_with($trimmed, '#')) {
            return;
        }

        $comment = ltrim(substr($trimmed, 1));
        if ($comment === '') {
            return;
        }

        $this->yamlMetadataCommentProvenance[] = [
            'type' => 'yaml-comment',
            'context' => 'standalone',
            'comment' => $comment,
            'path' => $this->currentYamlMetadataDiagnosticPath() ?? '',
        ] + $this->yamlMetadataSourceLineAttrs();
    }

    private function recordYamlTrailingCommentProvenance(string $source, int|string $segment): void
    {
        [, $comment] = $this->splitYamlTrailingComment($source);
        if ($comment === null || $comment === '') {
            return;
        }

        $this->yamlMetadataCommentProvenance[] = [
            'type' => 'yaml-comment',
            'context' => 'trailing',
            'comment' => $comment,
            'path' => $this->yamlMetadataPathWithSegment($segment),
        ] + $this->yamlMetadataSourceLineAttrs();
    }

    private function recordYamlDocumentMarkerCommentProvenance(string $line, string $markerRole): void
    {
        $entry = $this->yamlDocumentMarkerCommentProvenanceEntry(
            $line,
            $markerRole,
            $this->yamlMetadataCurrentSourceLine
        );
        if ($entry === null) {
            return;
        }

        $this->yamlMetadataCommentProvenance[] = $entry;
    }

    /**
     * @param list<string> $lines
     * @param list<int|null>|null $sourceLines
     * @return array<string, mixed>
     */
    private function parseYamlMetadataLines(
        array $lines,
        bool $topLevel = true,
        ?array $sourceLines = null,
        ?string $explicitCollectionTag = null
    ): array
    {
        $sourceLines ??= array_fill(0, count($lines), null);
        [$lines, $sourceLines] = $this->consumeYamlMetadataDocumentPreamble($lines, $sourceLines);
        $jsonMetadata = $this->parseYamlJsonMetadataDocument($lines);
        if ($jsonMetadata !== null) {
            if ($topLevel) {
                $jsonMetadata['__yamlMetadataFieldQuoteMap'] = array_fill_keys(
                    array_map(static fn (int|string $field): string => (string) $field, array_keys($jsonMetadata)),
                    true
                );
            }

            return $jsonMetadata;
        }

        $flowMetadata = $this->parseYamlFlowMetadataDocument($lines, $sourceLines);
        if ($flowMetadata !== null) {
            $metadata = $flowMetadata['metadata'];
            if ($topLevel && $flowMetadata['fieldQuoteMap'] !== []) {
                $metadata['__yamlMetadataFieldQuoteMap'] = $flowMetadata['fieldQuoteMap'];
            }

            return $metadata;
        }

        $metadata = [];
        $fieldQuoteMap = [];
        $seenKeys = [];
        $count = count($lines);
        for ($index = 0; $index < $count;) {
            $line = $lines[$index];
            $this->yamlMetadataCurrentSourceLine = $sourceLines[$index] ?? null;
            $trimmed = trim($line);
            if ($trimmed === '') {
                $index++;
                continue;
            }
            if (str_starts_with($trimmed, '#')) {
                $this->recordYamlStandaloneCommentProvenance($trimmed);
                $index++;
                continue;
            }

            if ($this->isYamlDirectiveLine($trimmed)) {
                $this->recordYamlDirectiveAfterDocumentContentDiagnostic($trimmed);
                $index++;
                continue;
            }

            $invalidExplicitKeyBlockScalar = $this->skipInvalidYamlExplicitKeyBlockScalarPair($lines, $index, $sourceLines);
            if ($invalidExplicitKeyBlockScalar !== null) {
                $index = $invalidExplicitKeyBlockScalar;
                continue;
            }

            $explicitMapping = $this->parseYamlExplicitMappingPair($lines, $index, $sourceLines);
            if ($explicitMapping !== null) {
                [$key, $sourceValue, $children, $childrenSourceLines, $nextIndex, $quotedKey] = $explicitMapping;
                if (!$this->isYamlMetadataMergeKey($key)) {
                    $this->recordYamlTrailingCommentProvenance($sourceValue, $key);
                }
                [$value] = $this->withYamlMetadataPathSegment(
                    (string) $key,
                    fn (): array => $this->parseYamlMetadataValue($sourceValue, $children, $childrenSourceLines)
                );
                $this->yamlMetadataCurrentSourceLine = $sourceLines[$index] ?? null;
                if ($this->isYamlMetadataMergeKey($key)) {
                    $metadata = $this->mergeYamlMapValue($metadata, $value);
                } else {
                    if (array_key_exists($key, $seenKeys)) {
                        $this->recordYamlDuplicateKeyDiagnostic($key);
                    }
                    $seenKeys[$key] = true;
                    $metadata[$key] = $value;
                    if ($topLevel) {
                        $fieldQuoteMap[(string) $key] = $quotedKey;
                    }
                }

                $index = $nextIndex;
                continue;
            }

            $explicitNullMapping = $this->parseYamlExplicitNullMappingPair($lines, $index, $sourceLines);
            if ($explicitNullMapping !== null) {
                [$key, $nextIndex, $quotedKey] = $explicitNullMapping;
                if (array_key_exists($key, $seenKeys)) {
                    $this->recordYamlDuplicateKeyDiagnostic($key);
                }
                $seenKeys[$key] = true;
                $metadata[$key] = null;
                if ($topLevel) {
                    $fieldQuoteMap[(string) $key] = $quotedKey;
                }

                $index = $nextIndex;
                continue;
            }

            $mapping = $this->parseYamlMappingLine($trimmed);
            if ($this->countIndentColumns($line) > 0 || $mapping === null) {
                $index++;
                continue;
            }

            [$key, $sourceValue, $quotedKey] = $mapping;
            [$key, $quotedKey] = $this->normalizeYamlPlainMappingKeyDirectives($key, $quotedKey);
            if (!$this->isYamlMetadataMergeKey($key)) {
                $this->recordYamlTrailingCommentProvenance($sourceValue, $key);
            }
            [$children, $nextIndex, $childrenSourceLines] = $this->collectYamlChildLines($lines, $index + 1, $sourceLines);
            [$value] = $this->withYamlMetadataPathSegment(
                (string) $key,
                fn (): array => $this->parseYamlMetadataValue($sourceValue, $children, $childrenSourceLines)
            );
            $this->yamlMetadataCurrentSourceLine = $sourceLines[$index] ?? null;
            if ($this->isYamlMetadataMergeKey($key)) {
                $metadata = $this->mergeYamlMapValue($metadata, $value);
            } else {
                if (array_key_exists($key, $seenKeys)) {
                    $this->recordYamlDuplicateKeyDiagnostic($key);
                }
                $seenKeys[$key] = true;
                $metadata[$key] = $value;
                if ($topLevel) {
                    $fieldQuoteMap[(string) $key] = $quotedKey;
                }
            }

            $index = $nextIndex;
        }

        $this->recordYamlCollectionProvenance(
            'mapping',
            'block',
            count($metadata),
            null,
            $sourceLines,
            $explicitCollectionTag === 'map' ? 'map' : null,
            $lines
        );

        if ($topLevel && $fieldQuoteMap !== []) {
            $metadata['__yamlMetadataFieldQuoteMap'] = $fieldQuoteMap;
        }

        return $metadata;
    }

    /**
     * @param list<string> $yamlLines
     */
    private function isYamlDirectiveDocumentStartMarker(array $yamlLines): bool
    {
        $sawDirective = false;
        foreach ($yamlLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if ($this->isYamlDirectiveLine($trimmed)) {
                $sawDirective = true;
                continue;
            }

            return false;
        }

        return $sawDirective;
    }

    /**
     * @param list<string> $lines
     * @param list<int|null> $sourceLines
     * @return array{0:list<string>, 1:list<int|null>}
     */
    private function consumeYamlMetadataDocumentPreamble(array $lines, array $sourceLines): array
    {
        $count = count($lines);
        $index = 0;
        $sawDirective = false;

        while ($index < $count) {
            $trimmed = trim($lines[$index]);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $index++;
                continue;
            }

            if ($this->withYamlMetadataSourceLine(
                $sourceLines[$index] ?? null,
                fn (): bool => $this->parseYamlDirectiveLine($trimmed)
            )) {
                $sawDirective = true;
                $index++;
                continue;
            }

            break;
        }

        if (!$sawDirective) {
            return [$lines, $sourceLines];
        }

        while ($index < $count) {
            $trimmed = trim($lines[$index]);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $index++;
                continue;
            }

            break;
        }

        if ($this->yamlMetadataDocumentMarker($lines[$index] ?? '') === '---') {
            $this->withYamlMetadataSourceLine(
                $sourceLines[$index] ?? null,
                fn (): mixed => $this->recordYamlDocumentMarkerCommentProvenance($lines[$index], 'document-start')
            );
            $index++;
        }

        return [array_slice($lines, $index), array_slice($sourceLines, $index)];
    }

    private function yamlMetadataDocumentMarker(string $line): ?string
    {
        $marker = $this->stripYamlTrailingComment($line);
        if ($marker !== ltrim($marker, " \t")) {
            return null;
        }

        $marker = trim($marker);

        return match ($marker) {
            '---', '...' => $marker,
            default => null,
        };
    }

    private function isYamlDirectiveLine(string $trimmed): bool
    {
        $directive = trim($this->stripYamlTrailingComment($trimmed));

        return preg_match('/^%YAML[ \t]+\d+(?:\.\d+)?$/i', $directive) === 1
            || preg_match('/^%TAG(?:[ \t]|$)/i', $directive) === 1
            || $this->parseYamlReservedDirective($directive) !== null;
    }

    private function parseYamlDirectiveLine(string $trimmed): bool
    {
        $directive = trim($this->stripYamlTrailingComment($trimmed));
        if ($directive === '' || $directive[0] !== '%') {
            return false;
        }

        if (preg_match('/^%YAML[ \t]+(\d+(?:\.\d+)?)$/i', $directive, $m) === 1) {
            $this->recordYamlVersionDirective($m[1]);
            return true;
        }

        if (
            preg_match('/^%TAG[ \t]+(!|!!|![A-Za-z0-9_.-]+!)[ \t]+(\S+)$/', $directive, $m) === 1
            && $m[2] !== ''
        ) {
            $this->yamlMetadataTagHandles[$m[1]] = $m[2];
            $this->recordYamlTagDirective($m[1], $m[2]);
            return true;
        }

        if (preg_match('/^%TAG(?:[ \t]|$)/i', $directive) === 1) {
            $this->recordYamlInvalidTagDirective($directive);
            return true;
        }

        $reservedDirective = $this->parseYamlReservedDirective($directive);
        if ($reservedDirective !== null) {
            $this->recordYamlReservedDirective($reservedDirective['name'], $reservedDirective['parameters']);
            return true;
        }

        return false;
    }

    /**
     * @return array{name:string, parameters:list<string>}|null
     */
    private function parseYamlReservedDirective(string $directive): ?array
    {
        if (preg_match('/^%([A-Za-z][A-Za-z0-9_-]*)(?:[ \t]+(.+))?$/', $directive, $m) !== 1) {
            return null;
        }

        $name = $m[1];
        if (in_array(strtoupper($name), ['YAML', 'TAG'], true)) {
            return null;
        }

        $parameterSource = trim((string) ($m[2] ?? ''));
        $parameters = $parameterSource === ''
            ? []
            : preg_split('/[ \t]+/', $parameterSource, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parameters)) {
            $parameters = [];
        }

        return ['name' => $name, 'parameters' => array_values($parameters)];
    }

    private function recordYamlVersionDirective(string $version): void
    {
        $supported = in_array($version, self::SUPPORTED_YAML_METADATA_VERSIONS, true);
        $this->yamlMetadataDirectiveProvenance[] = [
            'type' => 'yaml-directive',
            'directive' => 'YAML',
            'version' => $version,
            'supported' => $supported ? 'true' : 'false',
        ] + $this->yamlMetadataSourceLineAttrs();
        if ($supported) {
            $this->yamlMetadataSchemaVersion = $version;
            return;
        }

        $this->yamlMetadataDiagnostics[] = [
            'type' => 'yaml-directive',
            'reason' => 'unsupported-yaml-version',
            'directive' => 'YAML',
            'version' => $version,
            'supportedVersions' => implode(',', self::SUPPORTED_YAML_METADATA_VERSIONS),
        ] + $this->yamlMetadataSourceLineAttrs();
    }

    private function recordYamlTagDirective(string $handle, string $prefix): void
    {
        $this->yamlMetadataDirectiveProvenance[] = [
            'type' => 'yaml-directive',
            'directive' => 'TAG',
            'handle' => $handle,
            'prefix' => $prefix,
        ] + $this->yamlMetadataSourceLineAttrs();
    }

    /**
     * @param list<string> $parameters
     */
    private function recordYamlReservedDirective(string $name, array $parameters): void
    {
        $this->yamlMetadataDirectiveProvenance[] = [
            'type' => 'yaml-directive',
            'directive' => $name,
            'reserved' => 'true',
            'parameters' => implode(' ', $parameters),
            'parameterCount' => (string) count($parameters),
        ] + $this->yamlMetadataSourceLineAttrs();
    }

    private function recordYamlInvalidTagDirective(string $directive): void
    {
        $this->yamlMetadataDiagnostics[] = [
            'type' => 'yaml-directive',
            'reason' => 'invalid-tag-directive',
            'directive' => 'TAG',
            'source' => $directive,
            'expected' => '%TAG <handle> <prefix>',
        ] + $this->yamlMetadataSourceLineAttrs();
    }

    private function recordYamlDirectiveAfterDocumentContentDiagnostic(string $trimmed): void
    {
        $directive = trim($this->stripYamlTrailingComment($trimmed));
        if (preg_match('/^%YAML(?:[ \t]|$)/i', $directive) === 1) {
            $name = 'YAML';
        } elseif (preg_match('/^%TAG(?:[ \t]|$)/i', $directive) === 1) {
            $name = 'TAG';
        } else {
            $reservedDirective = $this->parseYamlReservedDirective($directive);
            $name = $reservedDirective['name'] ?? 'reserved';
        }
        $this->yamlMetadataDiagnostics[] = [
            'type' => 'yaml-directive',
            'reason' => 'directive-after-document-content',
            'directive' => $name,
            'source' => $directive,
            'expected' => 'YAML directives must precede document content',
        ] + $this->yamlMetadataSourceLineAttrs();
    }

    /**
     * @param list<string> $lines
     * @param list<int|null>|null $sourceLines
     */
    private function skipInvalidYamlExplicitKeyBlockScalarPair(array $lines, int $start, ?array $sourceLines = null): ?int
    {
        $line = $lines[$start] ?? '';
        $startIndent = $this->countIndentColumns($line);
        if ($startIndent > 0) {
            return null;
        }

        $trimmed = trim($line);
        if (!$this->isYamlExplicitMappingKeyLine($trimmed)) {
            return null;
        }

        $keySource = trim(substr($trimmed, 1));
        $cursor = $start + 1;
        $count = count($lines);
        $keyLines = [];
        $keySourceLines = [];
        while ($cursor < $count && !$this->isYamlExplicitMappingValueLineAtIndent($lines[$cursor], $startIndent)) {
            $keyLines[] = $lines[$cursor];
            $keySourceLines[] = $sourceLines[$cursor] ?? null;
            $cursor++;
        }

        if ($cursor >= $count) {
            return null;
        }

        $headerSourceLine = $sourceLines[$start] ?? null;
        if ($keySource !== '') {
            $header = $this->parseYamlBlockScalarHeader($keySource);
            if ($header === null) {
                return null;
            }

            $source = $keySource . ($keyLines === [] ? '' : "\n" . implode("\n", $keyLines));
            $contentLines = $keyLines;
            $contentSourceLines = $keySourceLines;
        } else {
            $normalized = $this->stripYamlCommonIndent($keyLines);
            $firstContentIndex = $this->firstYamlContentLineIndex($normalized);
            if ($firstContentIndex === null) {
                return null;
            }

            $headerSource = trim($normalized[$firstContentIndex]);
            $header = $this->parseYamlBlockScalarHeader($headerSource);
            if ($header === null) {
                return null;
            }

            $source = trim(implode("\n", $normalized));
            $headerSourceLine = $keySourceLines[$firstContentIndex] ?? null;
            $contentLines = array_slice($normalized, $firstContentIndex + 1);
            $contentSourceLines = array_slice($keySourceLines, $firstContentIndex + 1);
        }

        $invalidIndent = $this->invalidYamlBlockScalarIndentation($contentLines, $header['indent'], $contentSourceLines);
        if ($invalidIndent === null) {
            return null;
        }

        $this->withYamlMetadataSourceLine(
            $headerSourceLine,
            fn (): mixed => $this->recordYamlInvalidExplicitKeyBlockScalarIndentationDiagnostic(
                $source,
                $header,
                $invalidIndent
            )
        );

        [, $nextIndex] = $this->collectYamlChildLines($lines, $cursor + 1, $sourceLines);

        return $nextIndex;
    }

    /**
     * @param list<string> $lines
     * @param list<int|null>|null $sourceLines
     * @return array{0:string, 1:string, 2:list<string>, 3:list<int|null>, 4:int, 5:bool}|null
     */
    private function parseYamlExplicitMappingPair(array $lines, int $start, ?array $sourceLines = null): ?array
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
        $quotedKey = false;
        $keyTagStart = count($this->yamlMetadataTagProvenance);
        $keyAnchorStart = count($this->yamlMetadataAnchorProvenance);
        $keyAliasStart = count($this->yamlMetadataAliasProvenance);
        $keyProvenanceSource = null;
        $keyProvenanceSourceLine = $sourceLines[$start] ?? null;
        $keyProvenanceContentSourceLines = [];
        $separatorComments = [];
        $startIndent = $this->countIndentColumns($line);
        if ($keySource === '') {
            $keyLines = [];
            $keySourceLines = [];
            $count = count($lines);
            while (
                $cursor < $count
                && !$this->isYamlExplicitMappingValueLineAtIndent($lines[$cursor], $startIndent)
            ) {
                $keyLines[] = $lines[$cursor];
                $keySourceLines[] = $sourceLines[$cursor] ?? null;
                $cursor++;
            }

            if ($keyLines === [] || $cursor >= $count) {
                return null;
            }

            $quotedKey = $this->yamlExplicitKeyLinesStartQuoted($keyLines);
            [$keyProvenanceSource, $keyProvenanceSourceLine, $keyProvenanceContentSourceLines] = $this->yamlExplicitKeyScalarProvenanceSourceFromLines($keyLines, $keySourceLines);
            $keyValue = $this->withYamlMetadataScalarProvenanceRecording(
                false,
                fn (): mixed => $this->withYamlMetadataCollectionProvenanceRecording(
                    false,
                    fn (): mixed => $this->parseYamlIndentedValue($keyLines, $keySourceLines)
                )
            );
        } else {
            $quotedKey = $this->yamlExplicitKeySourceStartsQuoted($keySource);
            $keyProvenanceSource = $keySource;
            $keyBlockScalarHeader = $this->parseYamlBlockScalarHeader($keySource);
            if ($keyBlockScalarHeader !== null) {
                $keyLines = [];
                $keySourceLines = [];
                $count = count($lines);
                while (
                    $cursor < $count
                    && !$this->isYamlExplicitMappingValueLineAtIndent($lines[$cursor], $startIndent)
                ) {
                    $keyLines[] = $lines[$cursor];
                    $keySourceLines[] = $sourceLines[$cursor] ?? null;
                    $cursor++;
                }
                $keyProvenanceSource = $keySource . ($keyLines === [] ? '' : "\n" . implode("\n", $keyLines));
                $keyProvenanceContentSourceLines = $keySourceLines;
                if (!$this->yamlBlockScalarIndentationIsValid($keyLines, $keyBlockScalarHeader['indent'])) {
                    return null;
                }
                $keyValue = $this->parseYamlBlockScalar(
                    $keyLines,
                    $keyBlockScalarHeader['style'],
                    $keyBlockScalarHeader['chomp'],
                    $keyBlockScalarHeader['indent']
                );
            } else {
                $keyValue = $this->parseYamlScalarKeyValue($keySource);
                while (
                    isset($lines[$cursor])
                    && (trim($lines[$cursor]) === '' || str_starts_with(trim($lines[$cursor]), '#'))
                ) {
                    $candidate = trim($lines[$cursor]);
                    if (str_starts_with($candidate, '#')) {
                        $separatorComments[] = [$candidate, $sourceLines[$cursor] ?? null];
                    }
                    $cursor++;
                }
            }
        }

        if (!isset($lines[$cursor])) {
            array_splice($this->yamlMetadataTagProvenance, $keyTagStart);
            array_splice($this->yamlMetadataAliasProvenance, $keyAliasStart);
            return null;
        }

        $sourceValue = $this->parseYamlExplicitMappingValueLine(trim($lines[$cursor]));
        if ($sourceValue === null) {
            array_splice($this->yamlMetadataTagProvenance, $keyTagStart);
            array_splice($this->yamlMetadataAliasProvenance, $keyAliasStart);
            return null;
        }

        $key = $this->normalizeYamlExplicitMappingKey($keyValue);
        if ($key === null || $key === '') {
            array_splice($this->yamlMetadataTagProvenance, $keyTagStart);
            array_splice($this->yamlMetadataAliasProvenance, $keyAliasStart);
            return null;
        }

        $this->retargetYamlTagProvenanceFrom($keyTagStart, $key);
        $this->retargetYamlAnchorProvenanceFrom($keyAnchorStart, $key);
        $this->retargetYamlAliasProvenanceFrom($keyAliasStart, $key);
        $this->recordYamlExplicitKeyScalarProvenance(
            $keyProvenanceSource,
            $key,
            'block',
            $keyProvenanceSourceLine,
            $keyProvenanceContentSourceLines
        );
        $this->recordYamlExplicitKeyCollectionProvenance(
            $keyProvenanceSource,
            $keyValue,
            $key,
            'block',
            $keyProvenanceSourceLine,
            $keyProvenanceContentSourceLines
        );
        foreach ($separatorComments as [$comment, $commentSourceLine]) {
            $this->withYamlMetadataPathSegment(
                $key,
                fn (): mixed => $this->withYamlMetadataSourceLine(
                    $commentSourceLine,
                    fn (): mixed => $this->recordYamlStandaloneCommentProvenance($comment)
                )
            );
        }
        [$children, $nextIndex, $childrenSourceLines] = $this->collectYamlChildLines($lines, $cursor + 1, $sourceLines);

        return [$key, $sourceValue, $children, $childrenSourceLines, $nextIndex, $quotedKey];
    }

    /**
     * @param list<string> $lines
     * @param list<int|null>|null $sourceLines
     * @return array{0:string, 1:int, 2:bool}|null
     */
    private function parseYamlExplicitNullMappingPair(array $lines, int $start, ?array $sourceLines = null): ?array
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
        $keyValue = null;
        $quotedKey = false;
        $keyTagStart = count($this->yamlMetadataTagProvenance);
        $keyAnchorStart = count($this->yamlMetadataAnchorProvenance);
        $keyAliasStart = count($this->yamlMetadataAliasProvenance);
        $keyProvenanceSource = null;
        $keyProvenanceSourceLine = $sourceLines[$start] ?? null;
        $keyProvenanceContentSourceLines = [];
        $cursor = $start + 1;

        $startIndent = $this->countIndentColumns($line);
        if ($keySource === '') {
            $keyLines = [];
            $keySourceLines = [];
            $count = count($lines);
            while ($cursor < $count) {
                $candidate = trim($lines[$cursor]);
                if ($this->isYamlExplicitMappingValueLineAtIndent($lines[$cursor], $startIndent)) {
                    return null;
                }

                if (
                    $candidate !== ''
                    && $this->countIndentColumns($lines[$cursor]) === 0
                    && (
                        $this->parseYamlMappingLine($candidate) !== null
                        || $this->isYamlExplicitMappingKeyLine($candidate)
                    )
                ) {
                    break;
                }

                $keyLines[] = $lines[$cursor];
                $keySourceLines[] = $sourceLines[$cursor] ?? null;
                $cursor++;
            }

            if ($keyLines === []) {
                return null;
            }

            $quotedKey = $this->yamlExplicitKeyLinesStartQuoted($keyLines);
            [$keyProvenanceSource, $keyProvenanceSourceLine, $keyProvenanceContentSourceLines] = $this->yamlExplicitKeyScalarProvenanceSourceFromLines($keyLines, $keySourceLines);
            $keyValue = $this->withYamlMetadataScalarProvenanceRecording(
                false,
                fn (): mixed => $this->withYamlMetadataCollectionProvenanceRecording(
                    false,
                    fn (): mixed => $this->parseYamlIndentedValue($keyLines, $keySourceLines)
                )
            );
        } else {
            $quotedKey = $this->yamlExplicitKeySourceStartsQuoted($keySource);
            $keyProvenanceSource = $keySource;
            $keyBlockScalarHeader = $this->parseYamlBlockScalarHeader($keySource);
            if ($keyBlockScalarHeader !== null) {
                $keyLines = [];
                $keySourceLines = [];
                $count = count($lines);
                while ($cursor < $count) {
                    $candidate = trim($lines[$cursor]);
                    if ($this->isYamlExplicitMappingValueLineAtIndent($lines[$cursor], $startIndent)) {
                        return null;
                    }

                    if (
                        $candidate !== ''
                        && $this->countIndentColumns($lines[$cursor]) === 0
                        && (
                            $this->parseYamlMappingLine($candidate) !== null
                            || $this->isYamlExplicitMappingKeyLine($candidate)
                        )
                    ) {
                        break;
                    }

                    $keyLines[] = $lines[$cursor];
                    $keySourceLines[] = $sourceLines[$cursor] ?? null;
                    $cursor++;
                }
                $keyProvenanceSource = $keySource . ($keyLines === [] ? '' : "\n" . implode("\n", $keyLines));
                $keyProvenanceContentSourceLines = $keySourceLines;
                if (!$this->yamlBlockScalarIndentationIsValid($keyLines, $keyBlockScalarHeader['indent'])) {
                    return null;
                }
                $keyValue = $this->parseYamlBlockScalar(
                    $keyLines,
                    $keyBlockScalarHeader['style'],
                    $keyBlockScalarHeader['chomp'],
                    $keyBlockScalarHeader['indent']
                );
            } else {
                $keyValue = $this->parseYamlScalarKeyValue($keySource);
            }
        }

        $key = $this->normalizeYamlExplicitMappingKey($keyValue);
        if ($key === null || $key === '') {
            array_splice($this->yamlMetadataTagProvenance, $keyTagStart);
            array_splice($this->yamlMetadataAliasProvenance, $keyAliasStart);
            return null;
        }

        $this->retargetYamlTagProvenanceFrom($keyTagStart, $key);
        $this->retargetYamlAnchorProvenanceFrom($keyAnchorStart, $key);
        $this->retargetYamlAliasProvenanceFrom($keyAliasStart, $key);
        $this->recordYamlExplicitKeyScalarProvenance(
            $keyProvenanceSource,
            $key,
            'block',
            $keyProvenanceSourceLine,
            $keyProvenanceContentSourceLines
        );
        $this->recordYamlExplicitKeyCollectionProvenance(
            $keyProvenanceSource,
            $keyValue,
            $key,
            'block-null',
            $keyProvenanceSourceLine,
            $keyProvenanceContentSourceLines
        );

        return [$key, $cursor, $quotedKey];
    }

    /**
     * @param list<string> $keyLines
     */
    private function yamlExplicitKeyLinesStartQuoted(array $keyLines): bool
    {
        foreach ($this->stripYamlCommonIndent($keyLines) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            return $this->yamlExplicitKeySourceStartsQuoted($trimmed);
        }

        return false;
    }

    private function yamlExplicitKeySourceStartsQuoted(string $source): bool
    {
        $source = ltrim($source);
        if ($source !== '' && $this->yamlPlainMappingKeyMayStartWithDirective($source)) {
            [$source] = $this->parseYamlValueDirectives($source, false);
            $source = ltrim($source);
        }

        return $source !== '' && ($source[0] === '"' || $source[0] === "'");
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

    private function isYamlExplicitMappingValueLineAtIndent(string $line, int $indent): bool
    {
        return $this->countIndentColumns($line) <= $indent
            && $this->parseYamlExplicitMappingValueLine(trim($line)) !== null;
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
     * @param list<int|null>|null $childrenSourceLines
     * @return array{0:mixed, 1:string|null}
     */
    private function parseYamlMetadataValue(string $sourceValue, array $children, ?array $childrenSourceLines = null): array
    {
        [$sourceValue, $anchorName, $tags] = $this->parseYamlValueDirectives($sourceValue);
        $sourceValue = $this->stripYamlTrailingComment($sourceValue);
        $orderedPairsTag = $this->yamlExplicitOrderedPairsTag($tags);
        $collectionTag = $this->yamlExplicitCoreCollectionTag($tags);
        if ($orderedPairsTag !== null) {
            $value = $this->parseYamlExplicitOrderedPairsValue($sourceValue, $children, $childrenSourceLines, $orderedPairsTag);
            $this->rememberYamlAnchor($anchorName, $value);

            return [$value, $anchorName];
        }

        if ($this->yamlHasExplicitTag($tags, 'set')) {
            $value = $this->parseYamlExplicitSetValue($sourceValue, $children, $childrenSourceLines, 'set');
            $this->rememberYamlAnchor($anchorName, $value);

            return [$value, $anchorName];
        }

        $blockScalarHeader = $this->parseYamlBlockScalarHeader($sourceValue);
        $multilineFlow = $this->parseYamlMultilineFlowCollection($sourceValue, $children, $childrenSourceLines, $collectionTag);
        $tagsApplied = false;

        if ($multilineFlow !== null) {
            $value = $multilineFlow;
        } elseif ($blockScalarHeader !== null) {
            if (!$this->yamlBlockScalarIndentationIsValid($children, $blockScalarHeader['indent'])) {
                $this->yamlMetadataInvalid = true;
                $value = null;
            } else {
                $this->recordYamlBlockScalarProvenance(
                    $blockScalarHeader,
                    $this->yamlMetadataCurrentSourceLine,
                    $childrenSourceLines ?? []
                );
                $value = $this->parseYamlBlockScalar(
                    $children,
                    $blockScalarHeader['style'],
                    $blockScalarHeader['chomp'],
                    $blockScalarHeader['indent']
                );
            }
        } elseif ($sourceValue === '') {
            [$handledExplicitScalarChild, $explicitScalarChildValue] = $this->parseYamlExplicitScalarMappingChildValue(
                $children,
                $childrenSourceLines ?? [],
                $tags
            );
            if ($handledExplicitScalarChild) {
                $value = $explicitScalarChildValue;
                $tagsApplied = true;
            } else {
                if ($this->isYamlPlainMultilineScalar($children)) {
                    $this->recordYamlPlainScalarProvenance(
                        $this->yamlMetadataCurrentSourceLine,
                        $childrenSourceLines ?? [],
                        false
                    );
                }
                $value = $this->parseYamlIndentedValue($children, $childrenSourceLines, $collectionTag);
            }
        } else {
            $multiline = $this->parseYamlMultilineDoubleQuotedScalar($sourceValue, $children);
            $quotedStyle = $multiline !== null ? 'double-quoted' : null;
            if ($multiline === null) {
                $multiline = $this->parseYamlMultilineSingleQuotedScalar($sourceValue, $children);
                $quotedStyle = $multiline !== null ? 'single-quoted' : null;
            }
            if ($multiline !== null) {
                $this->recordYamlQuotedScalarProvenance(
                    $this->yamlQuotedScalarSource($sourceValue, $children),
                    $quotedStyle ?? 'quoted',
                    $this->yamlMetadataCurrentSourceLine,
                    $childrenSourceLines ?? [],
                    $this->yamlExplicitScalarTag($tags)
                );
                $value = $multiline;
            } elseif ($this->isYamlPlainMultilineScalar($children)) {
                $this->recordYamlPlainScalarProvenance(
                    $this->yamlMetadataCurrentSourceLine,
                    $childrenSourceLines ?? [],
                    true
                );
                $value = $this->parseYamlPlainMultilineScalar(
                    array_merge([$sourceValue], $this->stripYamlCommonIndent($children))
                );
            } else {
                $value = $this->parseYamlScalarValueFromDirectives($sourceValue, $anchorName, $tags);
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
     * @param list<int|null>|null $sourceLines
     * @return array{0:list<string>, 1:int, 2:list<int|null>}
     */
    private function collectYamlChildLines(array $lines, int $start, ?array $sourceLines = null): array
    {
        $children = [];
        $childSourceLines = [];
        $count = count($lines);
        for ($index = $start; $index < $count; $index++) {
            $line = $lines[$index];
            if (
                trim($line) !== ''
                && $this->countIndentColumns($line) === 0
                && (
                    $this->parseYamlMappingLine(trim($line)) !== null
                    || $this->isYamlExplicitMappingKeyLine(trim($line))
                    || $this->isYamlDirectiveLine(trim($line))
                )
            ) {
                break;
            }

            $children[] = $line;
            $childSourceLines[] = $sourceLines[$index] ?? null;
        }

        return [$children, $index, $childSourceLines];
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
        if ($chomp === '+') {
            return $foldedText . $trailingNewlines;
        }

        return $this->applyYamlBlockScalarChomp($foldedText, $chomp);
    }

    /**
     * @param list<string> $lines
     */
    private function yamlBlockScalarIndentationIsValid(array $lines, ?int $indent): bool
    {
        return $this->invalidYamlBlockScalarIndentation($lines, $indent) === null;
    }

    /**
     * @param list<string> $lines
     * @param list<int|null> $sourceLines
     * @return array{requiredIndent:int, actualIndent:int, line:string, contentLine:string, contentSourceLine?:string}|null
     */
    private function invalidYamlBlockScalarIndentation(array $lines, ?int $indent, array $sourceLines = []): ?array
    {
        $requiredIndent = $indent ?? 1;
        foreach ($lines as $index => $line) {
            $expanded = $this->expandTabsToSpaces($line);
            if (trim($expanded) === '') {
                continue;
            }

            $actualIndent = strspn($expanded, ' ');
            if ($actualIndent < $requiredIndent) {
                $invalid = [
                    'requiredIndent' => $requiredIndent,
                    'actualIndent' => $actualIndent,
                    'line' => $expanded,
                    'contentLine' => trim($expanded),
                ];
                $sourceLine = $sourceLines[$index] ?? null;
                if ($sourceLine !== null) {
                    $invalid['contentSourceLine'] = (string) $sourceLine;
                }

                return $invalid;
            }
        }

        return null;
    }

    /**
     * @param array{style:string, chomp:string|null, indent:int|null} $header
     * @param array{requiredIndent:int, actualIndent:int, line:string, contentLine:string, contentSourceLine?:string} $invalidIndent
     */
    private function recordYamlInvalidExplicitKeyBlockScalarIndentationDiagnostic(
        string $source,
        array $header,
        array $invalidIndent
    ): void {
        $diagnostic = [
            'type' => 'yaml-explicit-key',
            'reason' => 'invalid-block-scalar-indentation',
            'syntax' => 'block',
            'indicator' => $header['style'],
            'source' => $source,
            'contentLine' => $invalidIndent['contentLine'],
            'requiredIndent' => (string) $invalidIndent['requiredIndent'],
            'actualIndent' => (string) $invalidIndent['actualIndent'],
            'expected' => 'block scalar explicit key content must be indented',
        ];
        if ($header['chomp'] !== null) {
            $diagnostic['chomp'] = $header['chomp'];
        }
        if ($header['indent'] !== null) {
            $diagnostic['explicitIndent'] = (string) $header['indent'];
        }
        if (isset($invalidIndent['contentSourceLine'])) {
            $diagnostic['contentSourceLine'] = $invalidIndent['contentSourceLine'];
        }
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $diagnostic['parentPath'] = $path;
        }

        $this->yamlMetadataDiagnostics[] = $diagnostic + $this->yamlMetadataSourceLineAttrs();
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

        $trimmed = rtrim($text, "\n");
        if ($chomp === '-' || $trimmed === '') {
            return $trimmed;
        }

        return $trimmed . "\n";
    }

    /**
     * @param array{style:string, chomp:string|null, indent:int|null} $header
     * @param list<int|null> $contentSourceLines
     */
    private function recordYamlBlockScalarProvenance(array $header, ?int $sourceLine, array $contentSourceLines): void
    {
        if (!$this->yamlMetadataRecordScalarProvenance) {
            return;
        }

        $entry = [
            'type' => 'yaml-block-scalar',
            'style' => $header['style'] === '|' ? 'literal' : 'folded',
            'indicator' => $header['style'],
            'chomp' => match ($header['chomp']) {
                '+' => 'keep',
                '-' => 'strip',
                default => 'clip',
            },
            'contentLineCount' => (string) count($contentSourceLines),
        ];
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $entry['path'] = $path;
        }
        if ($sourceLine !== null) {
            $entry['sourceLine'] = (string) $sourceLine;
        }

        $sourceLineNumbers = array_values(array_filter(
            $contentSourceLines,
            static fn (?int $line): bool => $line !== null
        ));
        if ($sourceLineNumbers !== []) {
            $entry['contentStartLine'] = (string) $sourceLineNumbers[0];
            $entry['contentEndLine'] = (string) $sourceLineNumbers[count($sourceLineNumbers) - 1];
        }
        if ($header['indent'] !== null) {
            $entry['explicitIndent'] = (string) $header['indent'];
        }

        $this->yamlMetadataScalarProvenance[] = $entry;
    }

    /**
     * @param list<int|null> $continuationSourceLines
     */
    private function recordYamlPlainScalarProvenance(
        ?int $sourceLine,
        array $continuationSourceLines,
        bool $sourceLineIsContent
    ): void {
        $contentSourceLines = $sourceLineIsContent
            ? array_merge([$sourceLine], $continuationSourceLines)
            : $continuationSourceLines;

        $entry = [
            'type' => 'yaml-plain-scalar',
            'style' => 'plain',
            'contentLineCount' => (string) count($contentSourceLines),
        ];
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $entry['path'] = $path;
        }
        if ($sourceLine !== null) {
            $entry['sourceLine'] = (string) $sourceLine;
        }

        $sourceLineNumbers = array_values(array_filter(
            $contentSourceLines,
            static fn (?int $line): bool => $line !== null
        ));
        if ($sourceLineNumbers !== []) {
            $entry['contentStartLine'] = (string) $sourceLineNumbers[0];
            $entry['contentEndLine'] = (string) $sourceLineNumbers[count($sourceLineNumbers) - 1];
        }

        $this->yamlMetadataScalarProvenance[] = $entry;
    }

    /**
     * @param list<string> $lines
     * @param list<int|null> $sourceLines
     * @return array{0:string|null, 1:int|null, 2:list<int|null>}
     */
    private function yamlExplicitKeyScalarProvenanceSourceFromLines(array $lines, array $sourceLines): array
    {
        $normalized = $this->stripYamlCommonIndent($lines);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
            array_shift($sourceLines);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
            array_pop($sourceLines);
        }

        if ($normalized === []) {
            return [null, null, []];
        }

        $sourceLine = null;
        foreach ($sourceLines as $line) {
            if ($line !== null) {
                $sourceLine = $line;
                break;
            }
        }

        return [trim(implode("\n", $normalized)), $sourceLine, $sourceLines];
    }

    /**
     * @param list<int|null> $sourceLines
     */
    private function recordYamlExplicitKeyScalarProvenance(
        ?string $source,
        string $normalizedKey,
        string $syntax,
        ?int $sourceLine = null,
        array $sourceLines = []
    ): void {
        if (!$this->yamlMetadataRecordScalarProvenance || $source === null) {
            return;
        }

        $source = trim($this->stripYamlTrailingComment($source));
        if ($source === '') {
            return;
        }

        if (preg_match('/^\?[ \t]+(.+)$/s', $source, $m) === 1) {
            $source = trim($m[1]);
        }

        [$scalarSource, , $tags] = $this->parseYamlValueDirectives($source, false);
        $scalarSource = trim($this->stripYamlTrailingComment($scalarSource));
        if (!$this->yamlExplicitKeyProvenanceLooksScalar($scalarSource)) {
            return;
        }

        $sourceLine ??= $this->yamlMetadataCurrentSourceLine;
        $sourceLineCount = max(1, substr_count($source, "\n") + 1);
        $blockScalarAttrs = $this->yamlExplicitKeyBlockScalarProvenanceAttrs(
            $scalarSource,
            $sourceLine,
            $sourceLines
        );
        $entry = [
            'type' => 'yaml-explicit-key-scalar',
            'style' => $blockScalarAttrs['style'] ?? $this->yamlQuotedScalarStyle($scalarSource) ?? 'plain',
            'source' => $source,
            'sourceLineCount' => (string) $sourceLineCount,
            'multiline' => $sourceLineCount === 1 ? 'false' : 'true',
            'syntax' => $syntax,
            'key' => $normalizedKey,
            'path' => $this->yamlMetadataPathWithSegment($normalizedKey),
        ];
        if ($blockScalarAttrs !== []) {
            $entry += $blockScalarAttrs;
        }
        if ($scalarSource !== $source) {
            $entry['scalarSource'] = $scalarSource;
        }

        $explicitTag = $this->yamlExplicitScalarTag($tags);
        if ($explicitTag !== null) {
            $entry['explicitTag'] = $explicitTag;
        }
        if ($sourceLine !== null) {
            $entry['sourceLine'] = (string) $sourceLine;
        }

        if ($blockScalarAttrs === []) {
            $sourceLineNumbers = array_values(array_filter(
                array_merge([$sourceLine], $sourceLines),
                static fn (?int $line): bool => $line !== null
            ));
            if ($sourceLineNumbers !== []) {
                $entry['contentStartLine'] = (string) $sourceLineNumbers[0];
                $entry['contentEndLine'] = (string) $sourceLineNumbers[count($sourceLineNumbers) - 1];
            }
        }

        $this->yamlMetadataScalarProvenance[] = $entry;
    }

    /**
     * @param list<int|null> $sourceLines
     */
    private function recordYamlExplicitKeyCollectionProvenance(
        ?string $source,
        mixed $keyValue,
        string $normalizedKey,
        string $syntax,
        ?int $sourceLine = null,
        array $sourceLines = []
    ): void {
        if (!$this->yamlMetadataRecordCollectionProvenance || $source === null || !is_array($keyValue)) {
            return;
        }

        $source = trim($this->stripYamlTrailingComment($source));
        if ($source === '') {
            return;
        }

        if (preg_match('/^\?[ \t]+(.+)$/s', $source, $m) === 1) {
            $source = trim($m[1]);
        }

        [$collectionSource, , $tags] = $this->parseYamlValueDirectives($source, false);
        $collectionSource = trim($this->stripYamlTrailingComment($collectionSource));
        if ($collectionSource === '') {
            return;
        }

        $sourceLine ??= $this->yamlMetadataCurrentSourceLine;
        $sourceLineCount = max(1, substr_count($source, "\n") + 1);
        $style = (
            str_starts_with($collectionSource, '[')
            || str_starts_with($collectionSource, '{')
        ) ? 'flow' : 'block';

        $entry = [
            'type' => 'yaml-explicit-key-collection',
            'kind' => array_is_list($keyValue) ? 'sequence' : 'mapping',
            'style' => $style,
            'memberCount' => (string) count($keyValue),
            'source' => $source,
            'sourceLineCount' => (string) $sourceLineCount,
            'multiline' => $sourceLineCount === 1 ? 'false' : 'true',
            'syntax' => $syntax,
            'key' => $normalizedKey,
            'path' => $this->yamlMetadataPathWithSegment($normalizedKey),
        ];
        if ($collectionSource !== $source) {
            $entry['collectionSource'] = $collectionSource;
        }

        $explicitTag = $this->yamlExplicitCoreCollectionTag($tags);
        if ($explicitTag !== null) {
            $entry['explicitTag'] = $explicitTag;
        }
        if ($sourceLine !== null) {
            $entry['sourceLine'] = (string) $sourceLine;
        }

        $sourceLineNumbers = array_values(array_filter(
            array_merge([$sourceLine], $sourceLines),
            static fn (?int $line): bool => $line !== null
        ));
        if ($sourceLineNumbers !== []) {
            $entry['contentStartLine'] = (string) $sourceLineNumbers[0];
            $entry['contentEndLine'] = (string) $sourceLineNumbers[count($sourceLineNumbers) - 1];
        }

        $this->yamlMetadataCollectionProvenance[] = $entry;
    }

    /**
     * @param list<int|null> $sourceLines
     * @return array{style?:string, indicator?:string, chomp?:string, contentLineCount?:string, contentStartLine?:string, contentEndLine?:string, explicitIndent?:string}
     */
    private function yamlExplicitKeyBlockScalarProvenanceAttrs(string $source, ?int $sourceLine, array $sourceLines): array
    {
        $lines = explode("\n", $source);
        $headerLine = trim((string) array_shift($lines));
        $header = $this->parseYamlBlockScalarHeader($headerLine);
        if ($header === null) {
            return [];
        }

        $attrs = [
            'style' => $header['style'] === '|' ? 'literal' : 'folded',
            'indicator' => $header['style'],
            'chomp' => match ($header['chomp']) {
                '+' => 'keep',
                '-' => 'strip',
                default => 'clip',
            },
            'contentLineCount' => (string) count($lines),
        ];
        if ($header['indent'] !== null) {
            $attrs['explicitIndent'] = (string) $header['indent'];
        }

        $contentSourceLines = $sourceLines;
        if ($contentSourceLines !== [] && $sourceLine !== null && $contentSourceLines[0] === $sourceLine) {
            array_shift($contentSourceLines);
        }

        $sourceLineNumbers = array_values(array_filter(
            $contentSourceLines,
            static fn (?int $line): bool => $line !== null
        ));
        if ($sourceLineNumbers !== []) {
            $attrs['contentStartLine'] = (string) $sourceLineNumbers[0];
            $attrs['contentEndLine'] = (string) $sourceLineNumbers[count($sourceLineNumbers) - 1];
        }

        return $attrs;
    }

    private function yamlExplicitKeyProvenanceLooksScalar(string $source): bool
    {
        if ($source === '') {
            return false;
        }

        if ($source[0] === '[' || $source[0] === '{' || preg_match('/^-[ \t]/', $source) === 1) {
            return false;
        }

        if ($this->isYamlExplicitMappingKeyLine($source) || $this->parseYamlMappingLine($source) !== null) {
            return false;
        }

        return true;
    }

    /**
     * @param list<int|null> $continuationSourceLines
     */
    private function recordYamlQuotedScalarProvenance(
        string $source,
        string $style,
        ?int $sourceLine = null,
        array $continuationSourceLines = [],
        ?string $explicitTag = null
    ): void {
        if (!$this->yamlMetadataRecordScalarProvenance) {
            return;
        }

        $sourceLine ??= $this->yamlMetadataCurrentSourceLine;
        $sourceLineCount = max(1, substr_count($source, "\n") + 1);
        $entry = [
            'type' => 'yaml-quoted-scalar',
            'style' => $style,
            'source' => $source,
            'sourceLineCount' => (string) $sourceLineCount,
            'multiline' => $sourceLineCount === 1 ? 'false' : 'true',
        ];
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $entry['path'] = $path;
        }
        if ($sourceLine !== null) {
            $entry['sourceLine'] = (string) $sourceLine;
        }
        if ($explicitTag !== null) {
            $entry['explicitTag'] = $explicitTag;
        }

        $sourceLineNumbers = array_values(array_filter(
            array_merge([$sourceLine], $continuationSourceLines),
            static fn (?int $line): bool => $line !== null
        ));
        if ($sourceLineNumbers !== []) {
            $entry['contentStartLine'] = (string) $sourceLineNumbers[0];
            $entry['contentEndLine'] = (string) $sourceLineNumbers[count($sourceLineNumbers) - 1];
        }

        $this->yamlMetadataScalarProvenance[] = $entry;
    }

    private function recordYamlTypedScalarProvenance(string $source, string $scalarType, mixed $value, ?string $explicitTag = null): void
    {
        if (!$this->yamlMetadataRecordScalarProvenance) {
            return;
        }

        $kind = $this->yamlMetadataValueKind($value);
        if (
            ($scalarType === 'boolean' && $kind !== 'boolean')
            || ($scalarType === 'null' && $kind !== 'null')
            || ($scalarType === 'number' && $kind !== 'number')
            || ($scalarType === 'binary' && ($kind !== 'scalar' || !$this->isYamlValidBinaryScalarSource($source)))
            || ($scalarType === 'timestamp' && $kind !== 'scalar')
        ) {
            return;
        }
        if ($scalarType === 'timestamp') {
            $timestampSource = (($source[0] === '"' && str_ends_with($source, '"')) || ($source[0] === "'" && str_ends_with($source, "'")))
                ? $this->unquoteYamlScalar($source)
                : $source;
            if ($this->parseYamlTimestampScalar($timestampSource) === null) {
                return;
            }
        }

        $entry = [
            'type' => 'yaml-typed-scalar',
            'style' => 'plain',
            'scalarType' => $scalarType,
            'valueKind' => $kind,
            'source' => $source,
        ];
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $entry['path'] = $path;
        }
        if ($explicitTag !== null) {
            $entry['explicitTag'] = $explicitTag;
        }

        $entry += $this->yamlMetadataSourceLineAttrs();
        $this->yamlMetadataScalarProvenance[] = $entry;
    }

    /**
     * @param list<int|null> $contentSourceLines
     */
    private function recordYamlCollectionProvenance(
        string $kind,
        string $style,
        int $memberCount,
        ?int $sourceLine = null,
        array $contentSourceLines = [],
        ?string $explicitTag = null,
        ?array $contentLines = null
    ): void {
        if (
            !$this->yamlMetadataRecordCollectionProvenance
            || ($memberCount < 1 && ($explicitTag === null || $explicitTag === ''))
        ) {
            return;
        }

        $sourceLineNumbers = array_values(array_filter(
            $contentSourceLines,
            static fn (?int $line): bool => $line !== null
        ));
        if ($sourceLine === null && $sourceLineNumbers !== []) {
            $sourceLine = $sourceLineNumbers[0];
        } elseif ($sourceLine === null) {
            $sourceLine = $this->yamlMetadataCurrentSourceLine;
        }

        $entry = [
            'type' => 'yaml-collection',
            'kind' => $kind,
            'style' => $style,
            'memberCount' => (string) $memberCount,
        ];
        if ($explicitTag !== null && $explicitTag !== '') {
            $entry['explicitTag'] = $explicitTag;
        }
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $entry['path'] = $path;
        }
        if ($sourceLine !== null) {
            $entry['sourceLine'] = (string) $sourceLine;
        }
        if ($sourceLineNumbers !== []) {
            $entry['contentStartLine'] = (string) $sourceLineNumbers[0];
            $entry['contentEndLine'] = (string) $sourceLineNumbers[count($sourceLineNumbers) - 1];
        }
        if ($style === 'block' && $contentLines !== null) {
            $entry += $this->yamlMetadataCollectionMemberLineAttrs($contentLines, $contentSourceLines);
        }

        $this->yamlMetadataCollectionProvenance[] = $entry;
    }

    /**
     * @param list<string> $contentLines
     * @param list<int|null> $contentSourceLines
     * @return array{memberStartLine?:string, memberEndLine?:string}
     */
    private function yamlMetadataCollectionMemberLineAttrs(array $contentLines, array $contentSourceLines): array
    {
        $memberSourceLines = [];
        foreach ($contentLines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || $this->isYamlDirectiveLine($trimmed)) {
                continue;
            }

            $sourceLine = $contentSourceLines[$index] ?? null;
            if ($sourceLine !== null) {
                $memberSourceLines[] = $sourceLine;
            }
        }

        if ($memberSourceLines === []) {
            return [];
        }

        return [
            'memberStartLine' => (string) $memberSourceLines[0],
            'memberEndLine' => (string) $memberSourceLines[count($memberSourceLines) - 1],
        ];
    }

    private function yamlExplicitScalarProvenanceType(string $tag): ?string
    {
        return match ($tag) {
            'bool' => 'boolean',
            'null' => 'null',
            'int', 'float' => 'number',
            'timestamp' => 'timestamp',
            'binary' => 'binary',
            default => null,
        };
    }

    /**
     * @param list<string> $lines
     * @param list<int|null>|null $sourceLines
     * @return mixed
     */
    private function parseYamlIndentedValue(
        array $lines,
        ?array $sourceLines = null,
        ?string $explicitCollectionTag = null
    ): mixed
    {
        $sourceLines ??= array_fill(0, count($lines), null);
        $normalized = $this->stripYamlCommonIndent($lines);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
            array_shift($sourceLines);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
            array_pop($sourceLines);
        }

        if ($normalized === []) {
            return null;
        }

        $firstContentIndex = $this->firstYamlContentLineIndex($normalized);
        $firstContentLine = $firstContentIndex === null ? null : trim($normalized[$firstContentIndex]);
        if ($firstContentIndex !== null && $firstContentLine !== null) {
            $blockScalarHeader = $this->parseYamlBlockScalarHeader($firstContentLine);
            if ($blockScalarHeader !== null) {
                $contentLines = array_slice($normalized, $firstContentIndex + 1);
                $contentSourceLines = array_slice($sourceLines, $firstContentIndex + 1);
                if (!$this->yamlBlockScalarIndentationIsValid($contentLines, $blockScalarHeader['indent'])) {
                    $this->yamlMetadataInvalid = true;

                    return null;
                }

                $headerSourceLine = $sourceLines[$firstContentIndex] ?? null;
                $this->withYamlMetadataSourceLine(
                    $headerSourceLine,
                    fn (): mixed => $this->recordYamlBlockScalarProvenance(
                        $blockScalarHeader,
                        $headerSourceLine,
                        $contentSourceLines
                    )
                );

                return $this->parseYamlBlockScalar(
                    $contentLines,
                    $blockScalarHeader['style'],
                    $blockScalarHeader['chomp'],
                    $blockScalarHeader['indent']
                );
            }
        }

        if ($firstContentLine !== null && preg_match('/^-[ \t]?(.*)$/', $firstContentLine) === 1) {
            return $this->parseYamlSequence(
                $normalized,
                $sourceLines,
                $explicitCollectionTag === 'seq' ? 'seq' : null
            );
        }

        if ($this->startsWithYamlMapping($normalized)) {
            return $this->parseYamlMetadataLines(
                $normalized,
                false,
                $sourceLines,
                $explicitCollectionTag === 'map' ? 'map' : null
            );
        }

        if (count($normalized) === 1) {
            return $this->withYamlMetadataSourceLine(
                $sourceLines[0] ?? null,
                fn (): mixed => $this->parseYamlScalarValue(trim($normalized[0]))
            );
        }

        return $this->parseYamlPlainMultilineScalar($normalized);
    }

    /**
     * @param list<string> $lines
     */
    private function isYamlPlainMultilineScalar(array $lines): bool
    {
        $normalized = $this->stripYamlCommonIndent($lines);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
        }

        if ($normalized === []) {
            return false;
        }

        $first = trim($normalized[0]);
        if ($first === '' || preg_match('/^-[ \t]?(.*)$/', $first) === 1) {
            return false;
        }

        return !$this->startsWithYamlMapping($normalized);
    }

    /**
     * @param list<string> $lines
     */
    private function parseYamlPlainMultilineScalar(array $lines): string
    {
        $normalized = $this->stripYamlCommonIndent($lines);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
        }

        if ($normalized === []) {
            return '';
        }

        $folded = rtrim((string) array_shift($normalized), " \t");
        $previousMoreIndented = $folded !== '' && preg_match('/^[ \t]/', $folded) === 1;

        foreach ($normalized as $line) {
            if (trim($line) === '') {
                $folded = rtrim($folded, " \t") . "\n";
                $previousMoreIndented = false;
                continue;
            }

            $moreIndented = preg_match('/^[ \t]/', $line) === 1;
            $content = $moreIndented ? rtrim($line, " \t") : trim($line);
            if (str_ends_with($folded, "\n")) {
                $folded .= $content;
            } elseif ($previousMoreIndented || $moreIndented) {
                $folded = rtrim($folded, " \t") . "\n" . $content;
            } else {
                $folded = rtrim($folded, " \t") . ' ' . $content;
            }

            $previousMoreIndented = $moreIndented;
        }

        return $folded;
    }

    /**
     * @param list<string> $lines
     * @param list<int|null>|null $sourceLines
     * @return list<mixed>
     */
    private function parseYamlSequence(
        array $lines,
        ?array $sourceLines = null,
        ?string $explicitCollectionTag = null
    ): array
    {
        $sourceLines ??= array_fill(0, count($lines), null);
        $items = [];
        $count = count($lines);
        $pendingStandaloneComments = [];
        $recordPendingStandaloneComments = function (?string $itemPath = null, int|string|null $field = null) use (&$pendingStandaloneComments): void {
            if ($pendingStandaloneComments === []) {
                return;
            }

            $record = function () use (&$pendingStandaloneComments): void {
                foreach ($pendingStandaloneComments as [$comment, $sourceLine]) {
                    $this->withYamlMetadataSourceLine(
                        $sourceLine,
                        fn (): mixed => $this->recordYamlStandaloneCommentProvenance($comment)
                    );
                }
                $pendingStandaloneComments = [];
            };

            if ($itemPath !== null && $field !== null) {
                $this->withYamlMetadataPathSegment(
                    $itemPath,
                    fn (): mixed => $this->withYamlMetadataPathSegment((string) $field, $record)
                );
                return;
            }

            $record();
        };
        $nextSequenceItemIsExplicitMappingKey = function (int $commentIndex) use ($lines, $count): bool {
            for ($cursor = $commentIndex + 1; $cursor < $count; $cursor++) {
                $trimmed = trim($lines[$cursor]);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }

                if (preg_match('/^-[ \t]?(.*)$/', $lines[$cursor], $m) !== 1) {
                    return false;
                }

                return $this->isYamlExplicitMappingKeyLine(trim($this->stripYamlTrailingComment(rtrim($m[1]))));
            }

            return false;
        };
        for ($index = 0; $index < $count;) {
            $line = $lines[$index];
            if (trim($line) === '') {
                $index++;
                continue;
            }

            if (str_starts_with(trim($line), '#')) {
                if ($nextSequenceItemIsExplicitMappingKey($index)) {
                    $pendingStandaloneComments[] = [trim($line), $sourceLines[$index] ?? null];
                } else {
                    $this->withYamlMetadataSourceLine(
                        $sourceLines[$index] ?? null,
                        fn (): mixed => $this->recordYamlStandaloneCommentProvenance(trim($line))
                    );
                }
                $index++;
                continue;
            }

            if (preg_match('/^-[ \t]?(.*)$/', $line, $m) !== 1) {
                $recordPendingStandaloneComments();
                $index++;
                continue;
            }

            $sourceValue = rtrim($m[1]);
            $sourceLine = $sourceLines[$index] ?? null;
            $itemPath = (string) count($items);
            [$sourceValue, $anchorName, $tags] = $this->withYamlMetadataPathSegment(
                $itemPath,
                fn (): array => $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    fn (): array => $this->parseYamlValueDirectives($sourceValue)
                )
            );
            $sourceValueWithComment = $sourceValue;
            $sourceValue = $this->stripYamlTrailingComment($sourceValue);
            $children = [];
            $childrenSourceLines = [];
            $index++;
            while ($index < $count && preg_match('/^-[ \t]?/', $lines[$index]) !== 1) {
                if (
                    str_starts_with(trim($lines[$index]), '#')
                    && $this->countIndentColumns($lines[$index]) === 0
                ) {
                    break;
                }

                $children[] = $lines[$index];
                $childrenSourceLines[] = $sourceLines[$index] ?? null;
                $index++;
            }

            $orderedPairsTag = $this->yamlExplicitOrderedPairsTag($tags);
            if ($orderedPairsTag !== null) {
                $value = $this->withYamlMetadataPathSegment(
                    $itemPath,
                    fn (): array => $this->withYamlMetadataSourceLine(
                        $sourceLine,
                        fn (): array => $this->parseYamlExplicitOrderedPairsValue($sourceValue, $children, $childrenSourceLines, $orderedPairsTag)
                    )
                );
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    function () use ($anchorName, $value): void {
                        $this->rememberYamlAnchor($anchorName, $value);
                    }
                );
                $recordPendingStandaloneComments();
                $items[] = $value;
                continue;
            }

            if ($this->yamlHasExplicitTag($tags, 'set')) {
                $value = $this->withYamlMetadataPathSegment(
                    $itemPath,
                    fn (): array => $this->withYamlMetadataSourceLine(
                        $sourceLine,
                        fn (): array => $this->parseYamlExplicitSetValue($sourceValue, $children, $childrenSourceLines, 'set')
                    )
                );
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    function () use ($anchorName, $value): void {
                        $this->rememberYamlAnchor($anchorName, $value);
                    }
                );
                $recordPendingStandaloneComments();
                $items[] = $value;
                continue;
            }

            $collectionTag = $this->yamlExplicitCoreCollectionTag($tags);
            $blockScalarHeader = $this->parseYamlBlockScalarHeader($sourceValue);
            $multilineFlow = $this->withYamlMetadataPathSegment(
                $itemPath,
                fn (): mixed => $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    fn (): mixed => $this->parseYamlMultilineFlowCollection($sourceValue, $children, $childrenSourceLines, $collectionTag)
                )
            );
            if ($multilineFlow !== null) {
                $multilineFlow = $this->applyYamlExplicitScalarTagToSequenceItemValue(
                    $multilineFlow,
                    $tags,
                    $itemPath,
                    $sourceLine
                );
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    function () use ($anchorName, $multilineFlow): void {
                        $this->rememberYamlAnchor($anchorName, $multilineFlow);
                    }
                );
                $recordPendingStandaloneComments();
                $items[] = $multilineFlow;
                continue;
            }

            if ($blockScalarHeader !== null) {
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    fn (): mixed => $this->recordYamlTrailingCommentProvenance($sourceValueWithComment, $itemPath)
                );
                if (!$this->yamlBlockScalarIndentationIsValid($children, $blockScalarHeader['indent'])) {
                    $this->yamlMetadataInvalid = true;
                    $value = null;
                } else {
                    $this->withYamlMetadataPathSegment(
                        $itemPath,
                        fn (): mixed => $this->withYamlMetadataSourceLine(
                            $sourceLine,
                            fn (): mixed => $this->recordYamlBlockScalarProvenance(
                                $blockScalarHeader,
                                $sourceLine,
                                $childrenSourceLines
                            )
                        )
                    );
                    $value = $this->parseYamlBlockScalar(
                        $children,
                        $blockScalarHeader['style'],
                        $blockScalarHeader['chomp'],
                        $blockScalarHeader['indent']
                    );
                }
                $value = $this->applyYamlExplicitScalarTagToSequenceItemValue($value, $tags, $itemPath, $sourceLine);
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    function () use ($anchorName, $value): void {
                        $this->rememberYamlAnchor($anchorName, $value);
                    }
                );
                $recordPendingStandaloneComments();
                $items[] = $value;
                continue;
            }

            $multiline = $this->parseYamlMultilineDoubleQuotedScalar($sourceValue, $children);
            $quotedStyle = $multiline !== null ? 'double-quoted' : null;
            if ($multiline === null) {
                $multiline = $this->parseYamlMultilineSingleQuotedScalar($sourceValue, $children);
                $quotedStyle = $multiline !== null ? 'single-quoted' : null;
            }
            if ($multiline !== null) {
                $this->withYamlMetadataPathSegment(
                    $itemPath,
                    fn (): mixed => $this->withYamlMetadataSourceLine(
                        $sourceLine,
                        fn (): mixed => $this->recordYamlQuotedScalarProvenance(
                            $this->yamlQuotedScalarSource($sourceValue, $children),
                            $quotedStyle ?? 'quoted',
                            $sourceLine,
                            $childrenSourceLines,
                            $this->yamlExplicitScalarTag($tags)
                        )
                    )
                );
                $multiline = $this->applyYamlExplicitScalarTagToSequenceItemValue(
                    $multiline,
                    $tags,
                    $itemPath,
                    $sourceLine
                );
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    function () use ($anchorName, $multiline): void {
                        $this->rememberYamlAnchor($anchorName, $multiline);
                    }
                );
                $recordPendingStandaloneComments();
                $items[] = $multiline;
                continue;
            }

            if ($sourceValue === '') {
                [$handledExplicitScalarChild, $explicitScalarChildValue] = $this->parseYamlExplicitScalarSequenceChildValue(
                    $children,
                    $childrenSourceLines,
                    $tags,
                    $itemPath,
                    $sourceLine
                );
                if ($handledExplicitScalarChild) {
                    $this->withYamlMetadataSourceLine(
                        $sourceLine,
                        function () use ($anchorName, $explicitScalarChildValue): void {
                            $this->rememberYamlAnchor($anchorName, $explicitScalarChildValue);
                        }
                    );
                    $recordPendingStandaloneComments();
                    $items[] = $explicitScalarChildValue;
                    continue;
                }

                $value = $this->withYamlMetadataPathSegment(
                    $itemPath,
                    fn (): mixed => $this->parseYamlIndentedValue($children, $childrenSourceLines, $collectionTag)
                );
                $value = $this->applyYamlExplicitScalarTagToSequenceItemValue($value, $tags, $itemPath, $sourceLine);
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    function () use ($anchorName, $value): void {
                        $this->rememberYamlAnchor($anchorName, $value);
                    }
                );
                $recordPendingStandaloneComments();
                $items[] = $value;
                continue;
            }

            $childLines = $children === [] ? [] : $this->stripYamlCommonIndent($children);
            if (preg_match('/^-[ \t]+/', $sourceValue) === 1) {
                $value = $this->withYamlMetadataPathSegment(
                    $itemPath,
                    fn (): array => $this->parseYamlSequence(
                        array_merge([$sourceValue], $childLines),
                        array_merge([$sourceLine], $childrenSourceLines),
                        $collectionTag === 'seq' ? 'seq' : null
                    )
                );
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    function () use ($anchorName, $value): void {
                        $this->rememberYamlAnchor($anchorName, $value);
                    }
                );
                $recordPendingStandaloneComments();
                $items[] = $value;
                continue;
            }

            if ($this->isYamlCompactSequenceMappingSource($sourceValueWithComment)) {
                $value = $this->withYamlMetadataPathSegment(
                    $itemPath,
                    fn (): array => $this->parseYamlMetadataLines(
                        array_merge([$sourceValueWithComment], $childLines),
                        false,
                        array_merge([$sourceLine], $childrenSourceLines),
                        $collectionTag === 'map' ? 'map' : null
                    )
                );
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    function () use ($anchorName, $value): void {
                        $this->rememberYamlAnchor($anchorName, $value);
                    }
                );
                $recordPendingStandaloneComments();
                $items[] = $value;
                continue;
            }

            if (
                ($childLines !== [] || trim($sourceValue) !== '?')
                && $this->isYamlExplicitMappingKeyLine(trim($sourceValue))
            ) {
                $value = $this->withYamlMetadataPathSegment(
                    $itemPath,
                    fn (): array => $this->parseYamlMetadataLines(
                        array_merge([$sourceValue], $childLines),
                        false,
                        array_merge([$sourceLine], $childrenSourceLines),
                        $collectionTag === 'map' ? 'map' : null
                    )
                );
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    function () use ($anchorName, $value): void {
                        $this->rememberYamlAnchor($anchorName, $value);
                    }
                );
                $recordPendingStandaloneComments($itemPath, is_array($value) && $value !== [] ? array_key_first($value) : null);
                $items[] = $value;
                continue;
            }

            if ($this->isYamlPlainMultilineScalar($childLines)) {
                $this->withYamlMetadataPathSegment(
                    $itemPath,
                    fn (): mixed => $this->withYamlMetadataSourceLine(
                        $sourceLine,
                        fn (): mixed => $this->recordYamlPlainScalarProvenance(
                            $sourceLine,
                            $childrenSourceLines,
                            true
                        )
                    )
                );
                $value = $this->parseYamlPlainMultilineScalar(array_merge([$sourceValue], $childLines));
                $value = $this->applyYamlExplicitScalarTagToSequenceItemValue($value, $tags, $itemPath, $sourceLine);
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    function () use ($anchorName, $value): void {
                        $this->rememberYamlAnchor($anchorName, $value);
                    }
                );
                $recordPendingStandaloneComments();
                $items[] = $value;
                continue;
            }

            $value = $this->withYamlMetadataPathSegment(
                $itemPath,
                fn (): mixed => $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    fn (): mixed => $this->parseYamlScalarValueFromDirectives($sourceValue, $anchorName, $tags)
                )
            );
            $this->withYamlMetadataSourceLine(
                $sourceLine,
                function () use ($anchorName, $value): void {
                    $this->rememberYamlAnchor($anchorName, $value);
                }
            );
            $recordPendingStandaloneComments();
            $items[] = $value;
        }

        $recordPendingStandaloneComments();

        $this->recordYamlCollectionProvenance(
            'sequence',
            'block',
            count($items),
            null,
            $sourceLines,
            $explicitCollectionTag === 'seq' ? 'seq' : null,
            $lines
        );

        return $items;
    }

    /**
     * @param list<string> $lines
     */
    private function firstYamlContentLine(array $lines): ?string
    {
        $index = $this->firstYamlContentLineIndex($lines);

        return $index === null ? null : trim($lines[$index]);
    }

    /**
     * @param list<string> $lines
     */
    private function firstYamlContentLineIndex(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            return $index;
        }

        return null;
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
     * @return array{0:string, 1:string, 2:bool}|null
     */
    private function parseYamlMappingLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (preg_match('/^(<<):(?:[ \t]*(.*))?$/', $line, $m) === 1) {
            return [$m[1], rtrim($m[2] ?? ''), false];
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
                        true,
                    ];
                }
            }

            return null;
        }

        return $this->splitYamlPlainMappingLine($line);
    }

    /**
     * @return array{0:string, 1:string, 2:bool}|null
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

            return [$key, rtrim(ltrim($afterColon)), false];
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
        return $this->splitYamlTrailingComment($value)[0];
    }

    /**
     * @return array{0:string, 1:string|null}
     */
    private function splitYamlTrailingComment(string $value): array
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
                return [
                    rtrim(substr($value, 0, $offset)),
                    ltrim(substr($value, $offset + 1)),
                ];
            }
        }

        return [$value, null];
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
        $collectionTag = $this->yamlExplicitCoreCollectionTag($tags);
        if ($value === '') {
            $parsed = null;
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if ($this->isYamlAliasScalar($value)) {
            $parsed = $this->yamlAliasValue(substr($value, 1), $anchorName);
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        $orderedPairsTag = $this->yamlExplicitOrderedPairsTag($tags);
        if ($orderedPairsTag !== null) {
            $parsed = $this->parseYamlExplicitOrderedPairsValue($value, [], null, $orderedPairsTag);
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if ($this->yamlHasExplicitTag($tags, 'set')) {
            $parsed = $this->parseYamlExplicitSetValue($value, [], null, 'set');
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        $explicitScalarTag = $this->yamlExplicitScalarTag($tags);
        if ($explicitScalarTag !== null) {
            $parsed = $this->parseYamlExplicitTaggedScalar($value, $explicitScalarTag);
            $provenanceType = $this->yamlExplicitScalarProvenanceType($explicitScalarTag);
            if ($provenanceType !== null) {
                $this->recordYamlTypedScalarProvenance($value, $provenanceType, $parsed, $explicitScalarTag);
            }
            $quotedStyle = $this->yamlQuotedScalarStyle($value);
            if ($quotedStyle !== null) {
                $this->recordYamlQuotedScalarProvenance($value, $quotedStyle, null, [], $explicitScalarTag);
            }
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if ($value[0] === '[' && str_ends_with($value, ']')) {
            $parsed = [];
            foreach ($this->splitYamlInlineListWithLineOffsets(substr($value, 1, -1)) as $flowItem) {
                $parsed[] = $this->withYamlMetadataPathSegment(
                    (string) count($parsed),
                    fn (): mixed => $this->withYamlMetadataSourceLine(
                        $this->yamlMetadataSourceLineWithOffset(null, $flowItem['lineOffset']),
                        fn (): mixed => $this->parseYamlScalarValue($flowItem['item'])
                    )
                );
            }
            $this->recordYamlCollectionProvenance(
                'sequence',
                'flow',
                count($parsed),
                null,
                [],
                $collectionTag === 'seq' ? 'seq' : null
            );
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if ($value[0] === '{' && str_ends_with($value, '}')) {
            $parsed = $this->parseYamlInlineMap(
                substr($value, 1, -1),
                $this->yamlMetadataCurrentSourceLine,
                $collectionTag === 'map' ? 'map' : null
            );
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        if (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'"))) {
            $quotedStyle = $this->yamlQuotedScalarStyle($value);
            if ($quotedStyle !== null) {
                $this->recordYamlQuotedScalarProvenance($value, $quotedStyle);
            }
            $parsed = $this->unquoteYamlScalar($value);
            $this->rememberYamlAnchor($anchorName, $parsed);
            return $parsed;
        }

        $boolean = $this->parseYamlBooleanScalar($value);
        $timestamp = $this->parseYamlPlainTimestampScalar($value);
        $numeric = $this->parseYamlPlainNumericScalar($value);
        if ($boolean !== null) {
            $parsed = $boolean;
            $this->recordYamlTypedScalarProvenance($value, 'boolean', $parsed);
        } elseif (strtolower($value) === 'null' || $value === '~') {
            $parsed = null;
            $this->recordYamlTypedScalarProvenance($value, 'null', $parsed);
        } elseif ($timestamp !== null) {
            $parsed = $timestamp;
            $this->recordYamlTypedScalarProvenance($value, 'timestamp', $parsed);
        } elseif ($numeric !== null) {
            $parsed = $numeric;
            $this->recordYamlTypedScalarProvenance($value, 'number', $parsed);
        } else {
            $parsed = $value;
        }
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
     * @return list<array{item:string, lineOffset:int}>
     */
    private function splitYamlInlineListWithLineOffsets(string $source): array
    {
        return $this->splitYamlFlowItemsWithLineOffsets($source);
    }

    /**
     * @param list<string> $continuationLines
     */
    private function parseYamlMultilineFlowCollection(
        string $sourceValue,
        array $continuationLines,
        ?array $continuationSourceLines = null,
        ?string $explicitCollectionTag = null
    ): mixed
    {
        $sourceValue = ltrim($sourceValue);
        if (
            $continuationLines === []
            || $sourceValue === ''
            || ($sourceValue[0] !== '[' && $sourceValue[0] !== '{')
        ) {
            return null;
        }

        $rawSource = $sourceValue . "\n" . implode("\n", $continuationLines);
        $candidate = $this->stripYamlTrailingComment(
            trim($this->stripYamlFlowComments($rawSource))
        );
        if ($candidate === '') {
            return null;
        }

        $closing = $sourceValue[0] === '[' ? ']' : '}';
        if (!str_ends_with(rtrim($candidate), $closing) || !$this->isBalancedYamlFlowCollection($candidate)) {
            $this->yamlMetadataInvalid = true;
            return null;
        }

        $this->recordYamlFlowCommentProvenance(
            $rawSource,
            array_merge([$this->yamlMetadataCurrentSourceLine], $continuationSourceLines ?? [])
        );

        if ($candidate[0] === '[') {
            $parsed = [];
            foreach ($this->splitYamlInlineListWithLineOffsets(substr(rtrim($candidate), 1, -1)) as $flowItem) {
                $parsed[] = $this->withYamlMetadataPathSegment(
                    (string) count($parsed),
                    fn (): mixed => $this->withYamlMetadataSourceLine(
                        $this->yamlMetadataSourceLineWithOffset($this->yamlMetadataCurrentSourceLine, $flowItem['lineOffset']),
                        fn (): mixed => $this->parseYamlScalarValue($flowItem['item'])
                    )
                );
            }
            $this->recordYamlCollectionProvenance(
                'sequence',
                'flow',
                count($parsed),
                null,
                array_merge([$this->yamlMetadataCurrentSourceLine], $continuationSourceLines ?? []),
                $explicitCollectionTag === 'seq' ? 'seq' : null
            );

            return $parsed;
        }

        return $this->parseYamlInlineMap(
            substr(rtrim($candidate), 1, -1),
            $this->yamlMetadataCurrentSourceLine,
            $explicitCollectionTag === 'map' ? 'map' : null
        );
    }

    /**
     * @param list<int|null> $sourceLines
     */
    private function recordYamlFlowCommentProvenance(string $source, array $sourceLines = []): void
    {
        $quote = null;
        $inVerbatimTag = false;
        $lineIndex = 0;
        $length = strlen($source);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($char === "\n") {
                $lineIndex++;
                continue;
            }

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

            if ($inVerbatimTag) {
                if ($char === '>') {
                    $inVerbatimTag = false;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($this->isYamlVerbatimTagStart($source, $offset)) {
                $inVerbatimTag = true;
                continue;
            }

            if ($char !== '#' || ($offset !== 0 && !ctype_space($source[$offset - 1]))) {
                continue;
            }

            $lineEnd = strpos($source, "\n", $offset);
            if ($lineEnd === false) {
                $lineEnd = $length;
            }
            $comment = trim(substr($source, $offset + 1, $lineEnd - $offset - 1));
            if ($comment !== '') {
                $entry = [
                    'type' => 'yaml-comment',
                    'context' => 'flow',
                    'comment' => $comment,
                    'path' => $this->currentYamlMetadataDiagnosticPath() ?? '',
                ];
                $sourceLine = $sourceLines[$lineIndex] ?? null;
                if ($sourceLine !== null) {
                    $entry['sourceLine'] = (string) $sourceLine;
                }
                $this->yamlMetadataCommentProvenance[] = $entry;
            }

            $offset = $lineEnd - 1;
        }
    }

    private function stripYamlFlowComments(string $source): string
    {
        $clean = '';
        $quote = null;
        $inVerbatimTag = false;
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

            if ($inVerbatimTag) {
                $clean .= $char;
                if ($char === '>') {
                    $inVerbatimTag = false;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $clean .= $char;
                continue;
            }

            if ($this->isYamlVerbatimTagStart($source, $offset)) {
                $inVerbatimTag = true;
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
        $inVerbatimTag = false;
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

            if ($inVerbatimTag) {
                if ($char === '>') {
                    $inVerbatimTag = false;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($this->isYamlVerbatimTagStart($source, $offset)) {
                $inVerbatimTag = true;
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

        return $quote === null && !$inVerbatimTag && $squareDepth === 0 && $curlyDepth === 0;
    }

    private function isYamlVerbatimTagStart(string $source, int $offset): bool
    {
        return ($source[$offset] ?? '') === '!' && ($source[$offset + 1] ?? '') === '<';
    }

    /**
     * @param list<string> $lines
     * @param list<int|null>|null $sourceLines
     * @return array{metadata:array<string, mixed>, fieldQuoteMap:array<string, bool>}|null
     */
    private function parseYamlFlowMetadataDocument(array $lines, ?array $sourceLines = null): ?array
    {
        $source = trim(implode("\n", $lines));
        if ($source === '' || $source[0] !== '{') {
            return null;
        }

        $candidate = $this->stripYamlTrailingComment(trim($this->stripYamlFlowComments($source)));
        if ($candidate === '' || $candidate[0] !== '{' || !str_ends_with(rtrim($candidate), '}')) {
            return null;
        }

        if (!$this->isBalancedYamlFlowCollection($candidate)) {
            return null;
        }

        $sourceLine = null;
        foreach ($sourceLines ?? [] as $line) {
            if ($line !== null) {
                $sourceLine = $line;
                break;
            }
        }
        $parsed = $this->withYamlMetadataSourceLine(
            $sourceLine,
            fn (): array => $this->parseYamlInlineMapWithFieldQuoteMap(substr(rtrim($candidate), 1, -1), $sourceLine)
        );
        if ($parsed['metadata'] === []) {
            return null;
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYamlInlineMap(
        string $source,
        ?int $sourceLine = null,
        ?string $explicitCollectionTag = null
    ): array
    {
        return $this->parseYamlInlineMapWithFieldQuoteMap($source, $sourceLine, $explicitCollectionTag)['metadata'];
    }

    /**
     * @return array{metadata:array<string, mixed>, fieldQuoteMap:array<string, bool>}
     */
    private function parseYamlInlineMapWithFieldQuoteMap(
        string $source,
        ?int $sourceLine = null,
        ?string $explicitCollectionTag = null
    ): array
    {
        $map = [];
        $fieldQuoteMap = [];
        $seenKeys = [];
        foreach ($this->splitYamlFlowItemsWithLineOffsets($source) as $flowItem) {
            $item = $flowItem['item'];
            $itemSourceLine = $this->yamlMetadataSourceLineWithOffset($sourceLine, $flowItem['lineOffset']);
            $this->withYamlMetadataSourceLine(
                $itemSourceLine,
                function () use ($item, $itemSourceLine, &$map, &$fieldQuoteMap, &$seenKeys): void {
                    $mapping = $this->splitYamlFlowMappingItem($item);
                    if ($mapping === null) {
                        $keyTagStart = count($this->yamlMetadataTagProvenance);
                        $keyAnchorStart = count($this->yamlMetadataAnchorProvenance);
                        $keyAliasStart = count($this->yamlMetadataAliasProvenance);
                        [$key, $keyValue] = $this->normalizeYamlFlowKeyOnlyItemWithValue($item);
                        if ($key !== '') {
                            $this->retargetYamlTagProvenanceFrom($keyTagStart, $key);
                            $this->retargetYamlAnchorProvenanceFrom($keyAnchorStart, $key);
                            $this->retargetYamlAliasProvenanceFrom($keyAliasStart, $key);
                            if ($this->isYamlExplicitMappingKeyLine(trim($item))) {
                                $this->recordYamlExplicitKeyScalarProvenance($item, $key, 'flow-null', $itemSourceLine);
                                $this->recordYamlExplicitKeyCollectionProvenance($item, $keyValue, $key, 'flow-null', $itemSourceLine);
                            }
                            if (array_key_exists($key, $seenKeys)) {
                                $this->recordYamlDuplicateKeyDiagnostic($key);
                            }
                            $this->recordYamlFlowNullKeyDiagnostic($key, $item);
                            $seenKeys[$key] = true;
                            $map[$key] = null;
                            if ($this->isYamlQuotedFlowKey($item)) {
                                $fieldQuoteMap[(string) $key] = true;
                            }
                        }

                        return;
                    }

                    [$sourceKey, $value] = $mapping;
                    $keyTagStart = count($this->yamlMetadataTagProvenance);
                    $keyAnchorStart = count($this->yamlMetadataAnchorProvenance);
                    $keyAliasStart = count($this->yamlMetadataAliasProvenance);
                    [$key, $quotedKey, $keyValue] = $this->normalizeYamlFlowKeyWithQuote($sourceKey);
                    if ($key === '') {
                        return;
                    }

                    $this->retargetYamlTagProvenanceFrom($keyTagStart, $key);
                    $this->retargetYamlAnchorProvenanceFrom($keyAnchorStart, $key);
                    $this->retargetYamlAliasProvenanceFrom($keyAliasStart, $key);
                    if ($this->isYamlExplicitMappingKeyLine(trim($sourceKey))) {
                        $this->recordYamlExplicitKeyScalarProvenance($sourceKey, $key, 'flow', $itemSourceLine);
                        $this->recordYamlExplicitKeyCollectionProvenance($sourceKey, $keyValue, $key, 'flow', $itemSourceLine);
                    }
                    $value = $this->withYamlMetadataPathSegment(
                        (string) $key,
                        fn (): mixed => $this->parseYamlScalarValue($value)
                    );
                    if ($this->isYamlMetadataMergeKey($key)) {
                        $map = $this->mergeYamlMapValue($map, $value);
                    } else {
                        if (array_key_exists($key, $seenKeys)) {
                            $this->recordYamlDuplicateKeyDiagnostic($key);
                        }
                        $seenKeys[$key] = true;
                        $map[$key] = $value;
                        if ($quotedKey) {
                            $fieldQuoteMap[(string) $key] = true;
                        }
                    }
                }
            );
        }

        $this->recordYamlCollectionProvenance(
            'mapping',
            'flow',
            count($map),
            null,
            [],
            $explicitCollectionTag === 'map' ? 'map' : null
        );

        return ['metadata' => $map, 'fieldQuoteMap' => $fieldQuoteMap];
    }

    private function recordYamlFlowNullKeyDiagnostic(string $key, string $source): void
    {
        $trimmed = trim($source);
        $syntax = preg_match('/^\?[ \t]+/s', $trimmed) === 1 ? 'explicit' : 'implicit';
        $diagnostic = [
            'type' => 'yaml-flow-key',
            'reason' => 'flow-key-only-null',
            'syntax' => $syntax,
            'field' => $key,
            'path' => $this->yamlMetadataPathWithSegment($key),
            'source' => $trimmed,
        ] + $this->yamlMetadataSourceLineAttrs();

        $this->yamlMetadataDiagnostics[] = $diagnostic;
    }

    /**
     * @param list<string> $children
     * @param list<int|null>|null $childrenSourceLines
     * @return array<string, null>
     */
    private function parseYamlExplicitSetValue(
        string $sourceValue,
        array $children,
        ?array $childrenSourceLines = null,
        string $explicitTag = 'set'
    ): array
    {
        $sourceValue = ltrim($sourceValue);
        if ($sourceValue !== '') {
            $candidate = $children === []
                ? trim($this->stripYamlTrailingComment($sourceValue))
                : $this->stripYamlTrailingComment(
                    trim($this->stripYamlFlowComments($sourceValue . "\n" . implode("\n", $children)))
                );

            if ($candidate !== '' && $candidate[0] === '{' && str_ends_with(rtrim($candidate), '}') && $this->isBalancedYamlFlowCollection($candidate)) {
                $set = $this->parseYamlFlowSet(substr(rtrim($candidate), 1, -1), $this->yamlMetadataCurrentSourceLine);
                $this->recordYamlCollectionProvenance('mapping', 'flow', count($set), null, $childrenSourceLines ?? [], $explicitTag);

                return $set;
            }

            return [];
        }

        $childrenSourceLines ??= array_fill(0, count($children), null);
        $normalized = $this->stripYamlCommonIndent($children);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
            array_shift($childrenSourceLines);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
            array_pop($childrenSourceLines);
        }

        if ($normalized !== []) {
            $first = trim($normalized[0]);
            $candidate = $this->stripYamlTrailingComment(
                trim($this->stripYamlFlowComments(implode("\n", $normalized)))
            );
            if ($first !== '' && $first[0] === '{' && str_ends_with(rtrim($candidate), '}') && $this->isBalancedYamlFlowCollection($candidate)) {
                $set = $this->parseYamlFlowSet(substr(rtrim($candidate), 1, -1), $this->yamlMetadataCurrentSourceLine);
                $this->recordYamlCollectionProvenance('mapping', 'flow', count($set), null, $childrenSourceLines, $explicitTag);

                return $set;
            }
        }

        $set = $this->parseYamlBlockSet($normalized, $childrenSourceLines);
        $this->recordYamlCollectionProvenance('mapping', 'block', count($set), null, $childrenSourceLines, $explicitTag, $normalized);

        return $set;
    }

    /**
     * @return array<string, null>
     */
    private function parseYamlFlowSet(string $source, ?int $sourceLine = null): array
    {
        $set = [];
        foreach ($this->splitYamlFlowItemsWithLineOffsets($source) as $flowItem) {
            $item = trim($flowItem['item']);
            $itemSourceLine = $this->yamlMetadataSourceLineWithOffset($sourceLine, $flowItem['lineOffset']);
            $this->withYamlMetadataSourceLine(
                $itemSourceLine,
                function () use ($item, $itemSourceLine, &$set): void {
                    if ($item === '') {
                        return;
                    }

                    if ($item[0] === '?') {
                        $item = trim(substr($item, 1));
                    }

                    if ($item === '') {
                        return;
                    }

                    $keyTagStart = count($this->yamlMetadataTagProvenance);
                    $keyAnchorStart = count($this->yamlMetadataAnchorProvenance);
                    $keyAliasStart = count($this->yamlMetadataAliasProvenance);
                    $keyValue = $this->parseYamlScalarKeyValue($item);
                    $key = $this->normalizeYamlExplicitMappingKey($keyValue);
                    if ($key === null || $key === '') {
                        return;
                    }

                    $this->retargetYamlTagProvenanceFrom($keyTagStart, $key);
                    $this->retargetYamlAnchorProvenanceFrom($keyAnchorStart, $key);
                    $this->retargetYamlAliasProvenanceFrom($keyAliasStart, $key);
                    if ($this->isYamlExplicitMappingKeyLine($item)) {
                        $this->recordYamlExplicitKeyScalarProvenance($item, $key, 'set', $itemSourceLine);
                        $this->recordYamlExplicitKeyCollectionProvenance($item, $keyValue, $key, 'set', $itemSourceLine);
                    }
                    if (array_key_exists($key, $set)) {
                        $this->recordYamlDuplicateKeyDiagnostic($key);
                    }
                    $set[$key] = null;
                }
            );
        }

        return $set;
    }

    /**
     * @param list<string> $lines
     * @param list<int|null>|null $sourceLines
     * @return array<string, null>
     */
    private function parseYamlBlockSet(array $lines, ?array $sourceLines = null): array
    {
        $sourceLines ??= array_fill(0, count($lines), null);
        $set = [];
        $count = count($lines);
        for ($index = 0; $index < $count; $index++) {
            $sourceLine = $sourceLines[$index] ?? null;
            $trimmed = trim($this->stripYamlTrailingComment($lines[$index]));
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if ($this->parseYamlExplicitMappingValueLine($trimmed) !== null) {
                continue;
            }

            $keyTagStart = count($this->yamlMetadataTagProvenance);
            $keyAnchorStart = count($this->yamlMetadataAnchorProvenance);
            $keyAliasStart = count($this->yamlMetadataAliasProvenance);
            $keyProvenanceSource = null;
            $keyProvenanceSourceLine = $sourceLine;
            $keyProvenanceContentSourceLines = [];
            if ($this->isYamlExplicitMappingKeyLine($trimmed)) {
                $keySource = trim(substr($trimmed, 1));
                if ($keySource === '') {
                    $keyLines = [];
                    $keySourceLines = [];
                    $startIndent = $this->countIndentColumns($lines[$index]);
                    for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                        $candidate = trim($lines[$cursor]);
                        if (
                            $this->countIndentColumns($lines[$cursor]) <= $startIndent
                            && (
                                $this->isYamlExplicitMappingKeyLine($candidate)
                                || $this->parseYamlExplicitMappingValueLine($candidate) !== null
                            )
                        ) {
                            break;
                        }
                        $keyLines[] = $lines[$cursor];
                        $keySourceLines[] = $sourceLines[$cursor] ?? null;
                    }
                    $index = max($index, $cursor - 1);
                    [$keyProvenanceSource, $keyProvenanceSourceLine, $keyProvenanceContentSourceLines] = $this->yamlExplicitKeyScalarProvenanceSourceFromLines($keyLines, $keySourceLines);
                    $keyValue = $keyLines === []
                        ? null
                        : $this->withYamlMetadataCollectionProvenanceRecording(
                            false,
                            fn (): mixed => $this->parseYamlIndentedValue($keyLines, $keySourceLines)
                        );
                } else {
                    $keyProvenanceSource = $keySource;
                    $keyValue = $this->withYamlMetadataSourceLine(
                        $sourceLine,
                        fn (): mixed => $this->parseYamlScalarKeyValue($keySource)
                    );
                }
            } else {
                $keyValue = $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    fn (): mixed => $this->parseYamlScalarKeyValue($trimmed)
                );
            }

            $key = $this->normalizeYamlExplicitMappingKey($keyValue ?? null);
            if ($key === null || $key === '') {
                continue;
            }

            $this->retargetYamlTagProvenanceFrom($keyTagStart, $key);
            $this->retargetYamlAnchorProvenanceFrom($keyAnchorStart, $key);
            $this->retargetYamlAliasProvenanceFrom($keyAliasStart, $key);
            if ($keyProvenanceSource !== null) {
                $this->recordYamlExplicitKeyScalarProvenance(
                    $keyProvenanceSource,
                    $key,
                    'set',
                    $keyProvenanceSourceLine,
                    $keyProvenanceContentSourceLines
                );
                $this->recordYamlExplicitKeyCollectionProvenance(
                    $keyProvenanceSource,
                    $keyValue,
                    $key,
                    'set',
                    $keyProvenanceSourceLine,
                    $keyProvenanceContentSourceLines
                );
            }
            if (array_key_exists($key, $set)) {
                $this->withYamlMetadataSourceLine(
                    $sourceLine,
                    fn (): mixed => $this->recordYamlDuplicateKeyDiagnostic($key)
                );
            }
            $set[$key] = null;
        }

        return $set;
    }

    /**
     * @param list<string> $children
     * @param list<int|null>|null $childrenSourceLines
     * @return list<array{key:string, value:mixed}>
     */
    private function parseYamlExplicitOrderedPairsValue(
        string $sourceValue,
        array $children,
        ?array $childrenSourceLines = null,
        string $explicitTag = 'pairs'
    ): array
    {
        $sourceValue = ltrim($sourceValue);
        if ($sourceValue !== '') {
            $candidate = $children === []
                ? trim($this->stripYamlTrailingComment($sourceValue))
                : $this->stripYamlTrailingComment(
                    trim($this->stripYamlFlowComments($sourceValue . "\n" . implode("\n", $children)))
                );

            $flowPairs = $this->parseYamlExplicitOrderedPairsFlowCandidate(
                $candidate,
                $this->yamlMetadataCurrentSourceLine,
                $explicitTag
            );
            if ($flowPairs !== null) {
                $this->recordYamlCollectionProvenance('sequence', 'flow', count($flowPairs), null, $childrenSourceLines ?? [], $explicitTag);

                return $flowPairs;
            }

            return [];
        }

        $childrenSourceLines ??= array_fill(0, count($children), null);
        $normalized = $this->stripYamlCommonIndent($children);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
            array_shift($childrenSourceLines);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
            array_pop($childrenSourceLines);
        }

        if ($normalized !== []) {
            $candidate = $this->stripYamlTrailingComment(
                trim($this->stripYamlFlowComments(implode("\n", $normalized)))
            );
            $flowPairs = $this->parseYamlExplicitOrderedPairsFlowCandidate(
                $candidate,
                $this->yamlMetadataCurrentSourceLine,
                $explicitTag
            );
            if ($flowPairs !== null) {
                $this->recordYamlCollectionProvenance('sequence', 'flow', count($flowPairs), null, $childrenSourceLines, $explicitTag);

                return $flowPairs;
            }
        }

        $pairs = $this->yamlOrderedPairsFromSequence(
            $this->parseYamlSequence($normalized, $childrenSourceLines),
            $explicitTag,
            $this->yamlBlockSequenceItemSourceLines($normalized, $childrenSourceLines)
        );
        $this->recordYamlCollectionProvenance('sequence', 'block', count($pairs), null, $childrenSourceLines, $explicitTag, $normalized);

        return $pairs;
    }

    /**
     * @return list<array{key:string, value:mixed}>|null
     */
    private function parseYamlExplicitOrderedPairsFlowCandidate(
        string $candidate,
        ?int $sourceLine = null,
        string $explicitTag = 'pairs'
    ): ?array
    {
        $candidate = trim($candidate);
        if ($candidate === '' || $candidate[0] !== '[' || !str_ends_with(rtrim($candidate), ']')) {
            return null;
        }

        if (!$this->isBalancedYamlFlowCollection($candidate)) {
            return null;
        }

        $source = substr(rtrim($candidate), 1, -1);
        $items = [];
        $itemSourceLines = [];
        foreach ($this->splitYamlFlowItemsWithLineOffsets($source) as $flowItem) {
            $itemSourceLine = $this->yamlMetadataSourceLineWithOffset($sourceLine, $flowItem['lineOffset']);
            $items[] = $this->withYamlMetadataSourceLine(
                $itemSourceLine,
                fn (): mixed => $this->parseYamlScalarValue($flowItem['item'])
            );
            $itemSourceLines[] = $itemSourceLine;
        }

        return $this->yamlOrderedPairsFromSequence($items, $explicitTag, $itemSourceLines);
    }

    /**
     * @param list<string> $lines
     * @param list<int|null> $sourceLines
     * @return list<int|null>
     */
    private function yamlBlockSequenceItemSourceLines(array $lines, array $sourceLines): array
    {
        $itemSourceLines = [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^-[ \t]?/', $line) === 1) {
                $itemSourceLines[] = $sourceLines[$index] ?? null;
            }
        }

        return $itemSourceLines;
    }

    /**
     * @param list<mixed> $items
     * @param list<int|null> $itemSourceLines
     * @return list<array{key:string, value:mixed}>
     */
    private function yamlOrderedPairsFromSequence(
        array $items,
        string $explicitTag = 'pairs',
        array $itemSourceLines = []
    ): array
    {
        $pairs = [];
        foreach ($items as $index => $item) {
            if ($this->isYamlAssociativeArray($item)) {
                if (count($item) !== 1) {
                    $this->recordYamlInvalidOrderedPairMemberDiagnostic(
                        $item,
                        $explicitTag,
                        $index,
                        $itemSourceLines[$index] ?? null,
                        count($item)
                    );
                }

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

            $this->recordYamlInvalidOrderedPairMemberDiagnostic(
                $item,
                $explicitTag,
                $index,
                $itemSourceLines[$index] ?? null
            );
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

    private function recordYamlInvalidOrderedPairMemberDiagnostic(
        mixed $value,
        string $explicitTag,
        int $pairIndex,
        ?int $sourceLine,
        ?int $memberCount = null
    ): void
    {
        $diagnostic = [
            'type' => 'yaml-ordered-pair',
            'reason' => 'invalid-ordered-pair-member',
            'path' => $this->yamlMetadataPathWithSegment($pairIndex),
            'explicitTag' => $explicitTag,
            'pairIndex' => (string) $pairIndex,
            'valueKind' => $this->yamlMetadataValueKind($value),
            'expected' => 'single-pair mapping',
        ];
        if ($memberCount !== null) {
            $diagnostic['memberCount'] = (string) $memberCount;
        }
        if ($sourceLine !== null) {
            $diagnostic['sourceLine'] = (string) $sourceLine;
        } else {
            $diagnostic += $this->yamlMetadataSourceLineAttrs();
        }

        $this->yamlMetadataDiagnostics[] = $diagnostic;
    }

    /**
     * @return list<string>
     */
    private function splitYamlFlowItems(string $source): array
    {
        return array_map(
            static fn (array $item): string => $item['item'],
            $this->splitYamlFlowItemsWithLineOffsets($source)
        );
    }

    /**
     * @return list<array{item:string, lineOffset:int}>
     */
    private function splitYamlFlowItemsWithLineOffsets(string $source): array
    {
        $items = [];
        $buffer = '';
        $quote = null;
        $inVerbatimTag = false;
        $squareDepth = 0;
        $curlyDepth = 0;
        $lineOffset = 0;
        $itemStartLineOffset = null;
        $length = strlen($source);
        $markItemStart = static function (string $char) use (&$itemStartLineOffset, &$lineOffset): void {
            if ($itemStartLineOffset === null && !ctype_space($char)) {
                $itemStartLineOffset = $lineOffset;
            }
        };
        $append = static function (string $char) use (&$buffer, &$lineOffset): void {
            $buffer .= $char;
            if ($char === "\n") {
                $lineOffset++;
            }
        };
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($quote !== null) {
                $markItemStart($char);
                $append($char);
                if ($quote === "'" && $char === "'" && ($source[$offset + 1] ?? '') === "'") {
                    $offset++;
                    $markItemStart($source[$offset]);
                    $append($source[$offset]);
                    continue;
                }
                if ($char === $quote && ($quote === "'" || $source[$offset - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($inVerbatimTag) {
                $markItemStart($char);
                $append($char);
                if ($char === '>') {
                    $inVerbatimTag = false;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $markItemStart($char);
                $append($char);
                continue;
            }

            if ($this->isYamlVerbatimTagStart($source, $offset)) {
                $inVerbatimTag = true;
                $markItemStart($char);
                $append($char);
                continue;
            }

            if ($char === '[') {
                $squareDepth++;
                $markItemStart($char);
                $append($char);
                continue;
            }

            if ($char === ']') {
                $squareDepth = max(0, $squareDepth - 1);
                $markItemStart($char);
                $append($char);
                continue;
            }

            if ($char === '{') {
                $curlyDepth++;
                $markItemStart($char);
                $append($char);
                continue;
            }

            if ($char === '}') {
                $curlyDepth = max(0, $curlyDepth - 1);
                $markItemStart($char);
                $append($char);
                continue;
            }

            if ($char === ',' && $squareDepth === 0 && $curlyDepth === 0) {
                $items[] = [
                    'item' => trim($buffer),
                    'lineOffset' => $itemStartLineOffset ?? $lineOffset,
                ];
                $buffer = '';
                $itemStartLineOffset = null;
                continue;
            }

            $markItemStart($char);
            $append($char);
        }

        if (trim($buffer) !== '') {
            $items[] = [
                'item' => trim($buffer),
                'lineOffset' => $itemStartLineOffset ?? $lineOffset,
            ];
        }

        return $items;
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private function splitYamlFlowMappingItem(string $item): ?array
    {
        $quote = null;
        $inVerbatimTag = false;
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

            if ($inVerbatimTag) {
                if ($char === '>') {
                    $inVerbatimTag = false;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($this->isYamlVerbatimTagStart($item, $offset)) {
                $inVerbatimTag = true;
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

                $afterColon = substr($item, $offset + 1);
                if ($afterColon !== '' && preg_match('/^[ \t]/', $afterColon) !== 1 && !$this->isYamlQuotedFlowKey($key)) {
                    continue;
                }

                return [$key, trim($afterColon)];
            }
        }

        return null;
    }

    private function isYamlQuotedFlowKey(string $key): bool
    {
        $key = trim($key);
        if (preg_match('/^\?[ \t]+(.+)$/s', $key, $m) === 1) {
            $key = trim($m[1]);
        }

        return $this->yamlExplicitKeySourceStartsQuoted($key);
    }

    private function normalizeYamlFlowKeyOnlyItem(string $item): string
    {
        $normalized = $this->normalizeYamlFlowKeyOnlyItemWithValue($item)[0];

        return $normalized;
    }

    /**
     * @return array{0:string, 1:mixed}
     */
    private function normalizeYamlFlowKeyOnlyItemWithValue(string $item): array
    {
        $value = $this->parseYamlFlowKeyOnlyItemValue($item);
        if ($value === null) {
            return ['', null];
        }

        $normalized = $this->normalizeYamlExplicitMappingKey($value);

        return [$normalized ?? '', $value];
    }

    private function parseYamlFlowKeyOnlyItemValue(string $item): mixed
    {
        $item = trim($item);
        if ($item === '') {
            return null;
        }

        if (preg_match('/^\?[ \t]+(.+)$/s', $item, $m) === 1) {
            $item = trim($m[1]);
        }

        return $this->parseYamlScalarKeyValue($item);
    }

    private function normalizeYamlFlowKey(string $key): string
    {
        return $this->normalizeYamlFlowKeyWithQuote($key)[0];
    }

    /**
     * @return array{0:string, 1:bool, 2:mixed}
     */
    private function normalizeYamlFlowKeyWithQuote(string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            return ['', false, null];
        }

        if (preg_match('/^\?[ \t]+(.+)$/s', $key, $m) === 1) {
            $value = $this->parseYamlScalarKeyValue(trim($m[1]));
            $normalized = $this->normalizeYamlExplicitMappingKey($value);

            return [$normalized ?? '', $this->isYamlQuotedFlowKey($key), $value];
        }

        if (($key[0] === '"' && str_ends_with($key, '"')) || ($key[0] === "'" && str_ends_with($key, "'"))) {
            $value = $this->unquoteYamlScalar($key);

            return [$value, true, $value];
        }

        if ($this->yamlPlainMappingKeyMayStartWithDirective($key)) {
            [$normalized, $quoted] = $this->normalizeYamlPlainMappingKeyDirectives($key, false);

            return [$normalized, $quoted, $normalized];
        }

        return [$key, false, $key];
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

        return $this->decodeYamlDoubleQuotedScalar(
            $this->foldYamlDoubleQuotedContinuationLines($inner),
            $raw
        );
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

    /**
     * @param list<string> $continuationLines
     */
    private function yamlQuotedScalarSource(string $sourceValue, array $continuationLines = []): string
    {
        if ($continuationLines === []) {
            return $sourceValue;
        }

        return $sourceValue . "\n" . implode("\n", $continuationLines);
    }

    private function yamlQuotedScalarStyle(string $source): ?string
    {
        $source = ltrim($source);
        if ($source === '') {
            return null;
        }

        if ($source[0] === '"' && str_ends_with($source, '"') && $this->extractYamlDoubleQuotedInner($source) !== null) {
            return 'double-quoted';
        }

        if ($source[0] === "'" && str_ends_with($source, "'") && $this->extractYamlSingleQuotedInner($source) !== null) {
            return 'single-quoted';
        }

        return null;
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

        return $this->decodeYamlDoubleQuotedScalar(
            $this->foldYamlDoubleQuotedContinuationLines($inner),
            $value
        );
    }

    private function decodeYamlDoubleQuotedScalar(string $inner, ?string $source = null): string
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

                $this->recordYamlInvalidDoubleQuotedEscapeDiagnostic(
                    $this->yamlDoubleQuotedHexEscapeSequence($inner, $offset, $length, $escape),
                    $source
                );
            } else {
                $this->recordYamlInvalidDoubleQuotedEscapeDiagnostic('\\' . $escape, $source);
            }

            $decoded .= '\\' . $escape;
        }

        return $decoded;
    }

    private function yamlDoubleQuotedHexEscapeSequence(string $inner, int $escapeOffset, int $length, string $escape): string
    {
        $digits = match ($escape) {
            'x' => 2,
            'u' => 4,
            'U' => 8,
            default => 0,
        };
        $start = max(0, $escapeOffset - 1);
        $sequenceLength = min($digits + 2, $length - $start);

        return substr($inner, $start, $sequenceLength);
    }

    private function recordYamlInvalidDoubleQuotedEscapeDiagnostic(string $escapeSequence, ?string $source): void
    {
        $diagnostic = [
            'type' => 'yaml-scalar',
            'reason' => 'invalid-double-quoted-escape',
            'escape' => $escapeSequence,
            'expected' => 'valid YAML double-quoted escape',
        ];
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $diagnostic['path'] = $path;
        }
        if ($source !== null) {
            $diagnostic['source'] = $source;
        }

        $this->yamlMetadataDiagnostics[] = $diagnostic + $this->yamlMetadataSourceLineAttrs();
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

    private function parseYamlPlainNumericScalar(string $value): int|float|null
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if ($this->yamlMetadataSchemaVersion === '1.2') {
            if (str_contains($trimmed, ':')) {
                return null;
            }

            $integer = $this->parseYaml12PlainIntegerScalar($trimmed);
            if ($integer !== null) {
                return $integer;
            }

            $float = $this->parseYamlExplicitFloatScalar($trimmed);
            if (is_float($float)) {
                return $float;
            }

            return null;
        }

        $integer = $this->parseYamlExplicitIntegerScalar($trimmed);
        if (is_int($integer)) {
            return $integer;
        }

        $float = $this->parseYamlExplicitFloatScalar($trimmed);
        if (is_float($float)) {
            return $float;
        }

        return null;
    }

    private function parseYaml12PlainIntegerScalar(string $value): ?int
    {
        $normalized = str_replace('_', '', trim($value));
        if ($normalized === '') {
            return null;
        }

        $sign = 1;
        if ($normalized[0] === '+' || $normalized[0] === '-') {
            $sign = $normalized[0] === '-' ? -1 : 1;
            $normalized = substr($normalized, 1);
        }

        if ($normalized === '') {
            return null;
        }

        $base = 10;
        $digits = $normalized;
        if (preg_match('/^0o([0-7]+)$/i', $normalized, $m) === 1) {
            $base = 8;
            $digits = $m[1];
        } elseif (preg_match('/^0x([0-9a-f]+)$/i', $normalized, $m) === 1) {
            $base = 16;
            $digits = $m[1];
        } elseif (preg_match('/^[0-9]+$/', $normalized) !== 1) {
            return null;
        }

        return $sign * intval($digits, $base);
    }

    /**
     * @return array{0:string, 1:string|null, 2:list<string>}
     */
    private function parseYamlValueDirectives(string $value, bool $recordProvenance = true): array
    {
        $value = ltrim($value);
        $anchorName = null;
        $tags = [];

        while ($value !== '') {
            if (preg_match('/^&([^\s\[\]\{\},]+)(?=$|[ \t])/', $value, $m) === 1) {
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

            if (preg_match('/^(![A-Za-z0-9_.-]*!)(' . self::YAML_TAG_SUFFIX_PATTERN . ')(?=$|[ \t])/', $value, $m) === 1) {
                if ($recordProvenance && !array_key_exists($m[1], $this->yamlMetadataTagHandles)) {
                    $this->recordYamlUndefinedTagHandleDiagnostic($m[1], $m[2], $m[0]);
                }
                $tags[] = $this->expandYamlTagHandle($m[1], $m[2], $m[0]);
                $value = ltrim(substr($value, strlen($m[0])));
                continue;
            }

            if (
                array_key_exists('!', $this->yamlMetadataTagHandles)
                && preg_match('/^!(' . self::YAML_TAG_SUFFIX_PATTERN . ')(?=$|[ \t])/', $value, $m) === 1
            ) {
                $tags[] = $this->expandYamlTagHandle('!', $m[1], $m[0]);
                $value = ltrim(substr($value, strlen($m[0])));
                continue;
            }

            if (preg_match('/^(!{1,2}' . self::YAML_TAG_SUFFIX_PATTERN . ')(?=$|[ \t])/', $value, $m) === 1) {
                $tags[] = $m[1];
                $value = ltrim(substr($value, strlen($m[0])));
                continue;
            }

            break;
        }

        if ($recordProvenance) {
            if ($anchorName !== null && $anchorName !== '') {
                $this->recordYamlMetadataAnchorProvenance($anchorName, null, 'node');
            }
            foreach ($tags as $tag) {
                $this->recordYamlMetadataTagProvenance($tag);
            }
        }

        return [$value, $anchorName, $tags];
    }

    private function recordYamlUndefinedTagHandleDiagnostic(string $handle, string $suffix, string $sourceTag): void
    {
        $diagnostic = [
            'type' => 'yaml-tag',
            'reason' => 'undefined-tag-handle',
            'handle' => $handle,
            'suffix' => $suffix,
            'sourceTag' => $sourceTag,
            'expected' => 'declared %TAG handle',
        ];
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $diagnostic['path'] = $path;
        }

        $this->yamlMetadataDiagnostics[] = $diagnostic + $this->yamlMetadataSourceLineAttrs();
    }

    private function expandYamlTagHandle(string $handle, string $suffix, string $sourceTag): string
    {
        if (!array_key_exists($handle, $this->yamlMetadataTagHandles)) {
            return $sourceTag;
        }

        return $this->yamlMetadataTagHandles[$handle] . $suffix;
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
        return $this->yamlExplicitOrderedPairsTag($tags) !== null;
    }

    /**
     * @param list<string> $tags
     */
    private function yamlExplicitOrderedPairsTag(array $tags): ?string
    {
        foreach ($tags as $tag) {
            $normalized = $this->normalizeYamlTag($tag);
            if ($normalized === 'omap' || $normalized === 'pairs') {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param list<string> $tags
     */
    private function yamlExplicitCoreCollectionTag(array $tags): ?string
    {
        foreach ($tags as $tag) {
            $normalized = $this->normalizeYamlTag($tag);
            if ($normalized === 'map' || $normalized === 'seq') {
                return $normalized;
            }
        }

        return null;
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

    private function recordYamlMetadataTagProvenance(string $tag): void
    {
        $normalized = $this->normalizeYamlTag($tag);
        if ($tag === '!' || in_array($normalized, ['str', 'int', 'float', 'bool', 'null', 'timestamp', 'binary', 'merge', 'set', 'omap', 'pairs', 'seq', 'map'], true)) {
            return;
        }

        $provenance = [
            'type' => 'yaml-tag',
            'tag' => str_starts_with($tag, '!') ? $tag : '!<' . $tag . '>',
            'normalizedTag' => $normalized,
            'kind' => str_starts_with($tag, '!') ? 'local' : 'verbatim',
        ];
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $provenance['path'] = $path;
        }
        $provenance += $this->yamlMetadataSourceLineAttrs();

        $this->yamlMetadataTagProvenance[] = $provenance;
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

        $parsed = $this->parseYamlExplicitTaggedScalar($value, $tag);
        $provenanceType = $this->yamlExplicitScalarProvenanceType($tag);
        if ($provenanceType !== null) {
            $this->recordYamlTypedScalarProvenance($value, $provenanceType, $parsed, $tag);
        }

        return $parsed;
    }

    /**
     * @param list<string> $tags
     */
    private function applyYamlExplicitScalarTagToSequenceItemValue(
        mixed $value,
        array $tags,
        string $itemPath,
        ?int $sourceLine
    ): mixed {
        return $this->withYamlMetadataPathSegment(
            $itemPath,
            fn (): mixed => $this->withYamlMetadataSourceLine(
                $sourceLine,
                fn (): mixed => $this->applyYamlExplicitScalarTagToParsedValue($value, $tags)
            )
        );
    }

    /**
     * @param list<string> $children
     * @param list<int|null> $childrenSourceLines
     * @param list<string> $tags
     * @return array{0:bool, 1:mixed}
     */
    private function parseYamlExplicitScalarSequenceChildValue(
        array $children,
        array $childrenSourceLines,
        array $tags,
        string $itemPath,
        ?int $sourceLine
    ): array {
        if ($this->yamlExplicitScalarTag($tags) === null) {
            return [false, null];
        }

        $normalized = $this->stripYamlCommonIndent($children);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
            array_shift($childrenSourceLines);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
            array_pop($childrenSourceLines);
        }

        if ($normalized === [] || preg_match('/^-[ \t]?(.*)$/', $normalized[0]) === 1 || $this->startsWithYamlMapping($normalized)) {
            return [false, null];
        }

        $source = count($normalized) === 1
            ? trim($this->stripYamlTrailingComment($normalized[0]))
            : $this->parseYamlPlainMultilineScalar($normalized);
        $childSourceLine = $childrenSourceLines[0] ?? null;
        $quotedStyle = count($normalized) === 1 ? $this->yamlQuotedScalarStyle($source) : null;
        if ($quotedStyle !== null) {
            $this->withYamlMetadataPathSegment(
                $itemPath,
                fn (): mixed => $this->withYamlMetadataSourceLine(
                    $childSourceLine,
                    fn (): mixed => $this->recordYamlQuotedScalarProvenance(
                        $source,
                        $quotedStyle,
                        $childSourceLine,
                        [],
                        $this->yamlExplicitScalarTag($tags)
                    )
                )
            );
        }

        return [
            true,
            $this->applyYamlExplicitScalarTagToSequenceItemValue($source, $tags, $itemPath, $sourceLine),
        ];
    }

    /**
     * @param list<string> $children
     * @param list<int|null> $childrenSourceLines
     * @param list<string> $tags
     * @return array{0:bool, 1:mixed}
     */
    private function parseYamlExplicitScalarMappingChildValue(
        array $children,
        array $childrenSourceLines,
        array $tags
    ): array {
        if ($this->yamlExplicitScalarTag($tags) === null) {
            return [false, null];
        }

        $normalized = $this->stripYamlCommonIndent($children);
        while ($normalized !== [] && trim($normalized[0]) === '') {
            array_shift($normalized);
            array_shift($childrenSourceLines);
        }
        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
            array_pop($childrenSourceLines);
        }

        if ($normalized === [] || preg_match('/^-[ \t]?(.*)$/', $normalized[0]) === 1 || $this->startsWithYamlMapping($normalized)) {
            return [false, null];
        }

        $source = count($normalized) === 1
            ? trim($this->stripYamlTrailingComment($normalized[0]))
            : $this->parseYamlPlainMultilineScalar($normalized);
        $childSourceLine = $childrenSourceLines[0] ?? null;
        $quotedStyle = count($normalized) === 1 ? $this->yamlQuotedScalarStyle($source) : null;
        if ($quotedStyle !== null) {
            $this->withYamlMetadataSourceLine(
                $childSourceLine,
                fn (): mixed => $this->recordYamlQuotedScalarProvenance(
                    $source,
                    $quotedStyle,
                    $childSourceLine,
                    [],
                    $this->yamlExplicitScalarTag($tags)
                )
            );
        }

        return [true, $this->applyYamlExplicitScalarTagToParsedValue($source, $tags)];
    }

    private function parseYamlExplicitIntegerScalar(string $value): int|string
    {
        $normalized = str_replace('_', '', trim($value));
        if ($normalized === '') {
            return $value;
        }

        $sexagesimal = $this->parseYamlSexagesimalIntegerScalar($normalized);
        if ($sexagesimal !== null) {
            return $sexagesimal;
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

    private function parseYamlSexagesimalIntegerScalar(string $normalized): ?int
    {
        if (!str_contains($normalized, ':')) {
            return null;
        }

        $sign = 1;
        if ($normalized[0] === '+' || $normalized[0] === '-') {
            $sign = $normalized[0] === '-' ? -1 : 1;
            $normalized = substr($normalized, 1);
        }

        if ($normalized === '' || !str_contains($normalized, ':')) {
            return null;
        }

        $parts = explode(':', $normalized);
        if (count($parts) < 2) {
            return null;
        }

        $value = 0;
        foreach ($parts as $index => $part) {
            if ($part === '' || preg_match('/^\d+$/', $part) !== 1) {
                return null;
            }

            $component = (int) $part;
            if ($index > 0 && $component > 59) {
                return null;
            }

            if ($value > intdiv(PHP_INT_MAX - $component, 60)) {
                return null;
            }

            $value = ($value * 60) + $component;
        }

        return $sign * $value;
    }

    private function parseYamlExplicitFloatScalar(string $value): float|string
    {
        $normalized = str_replace('_', '', trim($value));
        $lower = strtolower($normalized);
        if ($lower === '.inf' || $lower === '+.inf') {
            return INF;
        }
        if ($lower === '-.inf') {
            return -INF;
        }
        if ($lower === '.nan' || $lower === '+.nan' || $lower === '-.nan') {
            return NAN;
        }

        $sexagesimal = $this->parseYamlSexagesimalFloatScalar($normalized);
        if ($sexagesimal !== null) {
            return $sexagesimal;
        }

        if (preg_match('/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/', $normalized) !== 1) {
            return $value;
        }

        return (float) $normalized;
    }

    private function parseYamlSexagesimalFloatScalar(string $normalized): ?float
    {
        if (!str_contains($normalized, ':')) {
            return null;
        }

        $sign = 1.0;
        if ($normalized[0] === '+' || $normalized[0] === '-') {
            $sign = $normalized[0] === '-' ? -1.0 : 1.0;
            $normalized = substr($normalized, 1);
        }

        if ($normalized === '' || !str_contains($normalized, ':')) {
            return null;
        }

        $parts = explode(':', $normalized);
        if (count($parts) < 2) {
            return null;
        }

        $value = 0.0;
        $lastIndex = count($parts) - 1;
        foreach ($parts as $index => $part) {
            if ($part === '') {
                return null;
            }

            if ($index === $lastIndex) {
                if (preg_match('/^\d+(?:\.\d+)?$/', $part) !== 1) {
                    return null;
                }

                $component = (float) $part;
            } else {
                if (preg_match('/^\d+$/', $part) !== 1) {
                    return null;
                }

                $component = (float) ((int) $part);
            }

            if ($index > 0 && $component >= 60.0) {
                return null;
            }

            $value = ($value * 60.0) + $component;
        }

        return $sign * $value;
    }

    private function parseYamlExplicitBooleanScalar(string $value): bool|string
    {
        return $this->parseYamlLegacyBooleanScalar($value) ?? $value;
    }

    private function parseYamlBooleanScalar(string $value): ?bool
    {
        $normalized = strtolower(trim($value));
        if ($this->yamlMetadataSchemaVersion === '1.2') {
            return match ($normalized) {
                'true' => true,
                'false' => false,
                default => null,
            };
        }

        return $this->parseYamlLegacyBooleanScalar($value);
    }

    private function parseYamlLegacyBooleanScalar(string $value): ?bool
    {
        return match (strtolower(trim($value))) {
            'y', 'yes', 'true', 'on' => true,
            'n', 'no', 'false', 'off' => false,
            default => null,
        };
    }

    private function parseYamlExplicitTimestampScalar(string $value): string
    {
        return $this->parseYamlTimestampScalar($value) ?? $value;
    }

    private function parseYamlPlainTimestampScalar(string $value): ?string
    {
        return $this->parseYamlTimestampScalar($value);
    }

    private function parseYamlTimestampScalar(string $value): ?string
    {
        $scalar = trim($value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $scalar, $m) === 1) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            if (!checkdate($month, $day, $year)) {
                return null;
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
            return null;
        }

        $year = (int) $m[1];
        $month = (int) $m[2];
        $day = (int) $m[3];
        $hour = (int) $m[4];
        $minute = (int) $m[5];
        $second = (int) $m[6];
        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        $offset = $this->normalizeYamlTimestampOffset($m[8] ?? '');
        if ($offset === null) {
            return null;
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
        if ($decoded === false) {
            $this->recordYamlInvalidBinaryScalarDiagnostic($value);

            return $value;
        }

        return $decoded;
    }

    private function isYamlValidBinaryScalarSource(string $source): bool
    {
        $source = trim($source);
        if ($source !== '' && (($source[0] === '"' && str_ends_with($source, '"')) || ($source[0] === "'" && str_ends_with($source, "'")))) {
            $source = $this->unquoteYamlScalar($source);
        }

        $compact = preg_replace('/\s+/', '', $source) ?? $source;

        return base64_decode($compact, true) !== false;
    }

    private function recordYamlInvalidBinaryScalarDiagnostic(string $source): void
    {
        $diagnostic = [
            'type' => 'yaml-scalar',
            'reason' => 'invalid-binary-scalar',
            'source' => $source,
            'expected' => 'valid base64 for !!binary',
        ];
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $diagnostic['path'] = $path;
        }

        $this->yamlMetadataDiagnostics[] = $diagnostic + $this->yamlMetadataSourceLineAttrs();
    }

    private function isYamlAliasScalar(string $value): bool
    {
        return preg_match('/^\*[^\s\[\]\{\},]+$/', $value) === 1;
    }

    private function yamlAliasValue(string $aliasName, ?string $currentAnchorName): mixed
    {
        if (!array_key_exists($aliasName, $this->yamlMetadataAnchors)) {
            $this->recordYamlAliasProvenance($aliasName, false, 'scalar');
            $this->recordYamlAliasDiagnostic(
                $aliasName,
                $currentAnchorName,
                $currentAnchorName === $aliasName ? 'self-reference' : 'unresolved-alias'
            );

            return '*' . $aliasName;
        }

        $value = $this->cloneYamlMetadataValue($this->yamlMetadataAnchors[$aliasName]);
        $resolvedAliasName = is_string($value) && $this->isYamlAliasScalar($value)
            ? substr($value, 1)
            : null;
        $this->recordYamlAliasProvenance(
            $aliasName,
            true,
            $this->yamlMetadataValueKind($value),
            $resolvedAliasName
        );
        if (is_string($value) && $this->isYamlAliasScalar($value)) {
            $this->recordYamlAliasDiagnostic(
                $aliasName,
                $currentAnchorName,
                $currentAnchorName !== null && $resolvedAliasName === $currentAnchorName
                    ? 'alias-cycle'
                    : 'chained-unresolved-alias',
                $resolvedAliasName
            );
        }

        return $value;
    }

    private function recordYamlAliasProvenance(
        string $aliasName,
        bool $resolved,
        string $valueKind,
        ?string $resolvedAliasName = null
    ): void {
        $provenance = [
            'type' => 'yaml-alias',
            'alias' => '*' . $aliasName,
            'anchor' => $aliasName,
            'resolved' => $resolved ? 'true' : 'false',
            'valueKind' => $valueKind,
        ];
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $provenance['path'] = $path;
        }
        if ($resolvedAliasName !== null && $resolvedAliasName !== '') {
            $provenance['resolvedAlias'] = '*' . $resolvedAliasName;
        }
        $provenance += $this->yamlMetadataSourceLineAttrs();

        $this->yamlMetadataAliasProvenance[] = $provenance;
    }

    private function recordYamlAliasDiagnostic(
        string $aliasName,
        ?string $currentAnchorName,
        string $reason,
        ?string $resolvedAliasName = null
    ): void {
        $diagnostic = [
            'type' => 'yaml-alias',
            'reason' => $reason,
            'alias' => '*' . $aliasName,
            'anchor' => $aliasName,
        ];
        if ($currentAnchorName !== null && $currentAnchorName !== '') {
            $diagnostic['definedAnchor'] = $currentAnchorName;
        }
        $path = $this->currentYamlMetadataDiagnosticPath();
        if ($path !== null) {
            $diagnostic['path'] = $path;
        }
        if ($resolvedAliasName !== null && $resolvedAliasName !== '') {
            $diagnostic['resolvedAlias'] = '*' . $resolvedAliasName;
        }
        $diagnostic += $this->yamlMetadataSourceLineAttrs();

        $this->yamlMetadataDiagnostics[] = $diagnostic;
    }

    private function rememberYamlAnchor(?string $anchorName, mixed $value): void
    {
        if ($anchorName === null || $anchorName === '') {
            return;
        }

        $this->recordYamlMetadataAnchorProvenance($anchorName, $value);
        $this->yamlMetadataAnchors[$anchorName] = $this->cloneYamlMetadataValue($value);
    }

    private function recordYamlMetadataAnchorProvenance(string $anchorName, mixed $value = null, ?string $kind = null): void
    {
        $path = $this->currentYamlMetadataDiagnosticPath() ?? '';
        $kind ??= $this->yamlMetadataValueKind($value);
        foreach ($this->yamlMetadataAnchorProvenance as $index => $entry) {
            if (($entry['name'] ?? '') === $anchorName && ($entry['path'] ?? '') === $path) {
                $this->yamlMetadataAnchorProvenance[$index]['kind'] = $kind;
                $this->yamlMetadataAnchorProvenance[$index] += $this->yamlMetadataSourceLineAttrs();

                return;
            }
        }
        if ($kind !== 'node') {
            foreach ($this->yamlMetadataAnchorProvenance as $index => $entry) {
                if (($entry['name'] ?? '') === $anchorName && ($entry['kind'] ?? '') === 'node') {
                    $this->yamlMetadataAnchorProvenance[$index]['kind'] = $kind;
                    $this->yamlMetadataAnchorProvenance[$index] += $this->yamlMetadataSourceLineAttrs();

                    return;
                }
            }
        }

        $provenance = [
            'type' => 'yaml-anchor',
            'anchor' => '&' . $anchorName,
            'name' => $anchorName,
            'kind' => $kind,
        ];
        if ($path !== '') {
            $provenance['path'] = $path;
        }
        $provenance += $this->yamlMetadataSourceLineAttrs();

        $this->yamlMetadataAnchorProvenance[] = $provenance;
    }

    private function yamlMetadataValueKind(mixed $value): string
    {
        if (is_array($value)) {
            return array_is_list($value) ? 'sequence' : 'mapping';
        }

        return match (get_debug_type($value)) {
            'null' => 'null',
            'bool' => 'boolean',
            'int', 'float' => 'number',
            default => 'scalar',
        };
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
        [$validMergeSourceCount, $invalidMergeSourceCount] = $this->yamlMetadataMergeSourceCounts($mergeValue);
        $this->recordYamlMergeProvenance($mergeValue, $validMergeSourceCount, $invalidMergeSourceCount);

        $merged = [];
        if ($this->isYamlAssociativeArray($mergeValue)) {
            $merged = $mergeValue;
        } elseif (is_array($mergeValue)) {
            $this->recordYamlMergeSequenceShadowDiagnostics($mergeValue);
            $this->recordYamlInvalidMergeSequenceValueDiagnostics($mergeValue);
            foreach (array_reverse($mergeValue) as $item) {
                if ($this->isYamlAssociativeArray($item)) {
                    $merged = array_replace($merged, $item);
                }
            }
        } else {
            $this->recordYamlInvalidMergeValueDiagnostic($mergeValue);
        }

        return array_replace($merged, $current);
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function yamlMetadataMergeSourceCounts(mixed $mergeValue): array
    {
        if ($this->isYamlAssociativeArray($mergeValue)) {
            return [1, 0];
        }

        if (!is_array($mergeValue)) {
            return [0, 1];
        }

        $valid = 0;
        $invalid = 0;
        foreach ($mergeValue as $item) {
            if ($this->isYamlAssociativeArray($item)) {
                $valid++;
                continue;
            }

            $invalid++;
        }

        return [$valid, $invalid];
    }

    private function recordYamlMergeProvenance(mixed $mergeValue, int $validMergeSourceCount, int $invalidMergeSourceCount): void
    {
        $provenance = [
            'type' => 'yaml-merge',
            'reason' => 'merge-source',
            'path' => $this->yamlMetadataPathWithSegment('<<'),
            'valueKind' => $this->yamlMetadataValueKind($mergeValue),
            'mergeSourceCount' => (string) $validMergeSourceCount,
            'invalidMergeSourceCount' => (string) $invalidMergeSourceCount,
            'policy' => $validMergeSourceCount === 0
                ? 'invalid'
                : ($invalidMergeSourceCount === 0 ? 'applied' : 'partial'),
        ];
        $provenance += $this->yamlMetadataSourceLineAttrs();

        $this->yamlMetadataMergeProvenance[] = $provenance;
    }

    /**
     * @param array<int, mixed> $mergeValue
     */
    private function recordYamlInvalidMergeSequenceValueDiagnostics(array $mergeValue): void
    {
        foreach ($mergeValue as $index => $item) {
            if ($this->isYamlAssociativeArray($item)) {
                continue;
            }

            $this->recordYamlInvalidMergeValueDiagnostic($item, $index);
        }
    }

    private function recordYamlInvalidMergeValueDiagnostic(mixed $value, int|string|null $mergeIndex = null): void
    {
        $diagnostic = [
            'type' => 'yaml-merge',
            'reason' => 'invalid-merge-value',
            'path' => $this->yamlMetadataPathWithSegment('<<'),
            'valueKind' => $this->yamlMetadataValueKind($value),
            'expected' => 'mapping or sequence of mappings',
        ];
        if ($mergeIndex !== null) {
            $diagnostic['mergeIndex'] = (string) $mergeIndex;
        }
        $diagnostic += $this->yamlMetadataSourceLineAttrs();

        $this->yamlMetadataDiagnostics[] = $diagnostic;
    }

    /**
     * @param array<int, mixed> $mergeValue
     */
    private function recordYamlMergeSequenceShadowDiagnostics(array $mergeValue): void
    {
        $seen = [];
        foreach ($mergeValue as $index => $item) {
            if (!$this->isYamlAssociativeArray($item)) {
                continue;
            }

            foreach ($item as $field => $_) {
                if (array_key_exists($field, $seen)) {
                    $this->recordYamlMergeSequenceShadowDiagnostic($field, $index, $seen[$field]);
                    continue;
                }

                $seen[$field] = $index;
            }
        }
    }

    private function recordYamlMergeSequenceShadowDiagnostic(int|string $field, int|string $mergeIndex, int|string $shadowingMergeIndex): void
    {
        $diagnostic = [
            'type' => 'yaml-merge',
            'reason' => 'merge-sequence-shadowed-key',
            'field' => (string) $field,
            'path' => $this->yamlMetadataPathWithSegment($field),
            'mergeIndex' => (string) $mergeIndex,
            'shadowedByMergeIndex' => (string) $shadowingMergeIndex,
        ];
        $diagnostic += $this->yamlMetadataSourceLineAttrs();
        $this->yamlMetadataDiagnostics[] = $diagnostic;
    }

    private function isYamlMetadataMergeKey(int|string $key): bool
    {
        $source = trim((string) $key);
        if ($source === '<<') {
            return true;
        }

        if ($source === '' || $source[0] !== '!') {
            return false;
        }

        [$value, , $tags] = $this->parseYamlValueDirectives($source, false);

        return trim($value) === '<<' && $this->yamlHasExplicitTag($tags, 'merge');
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
        $diagnostics = [];
        $tagProvenance = [];
        $directiveProvenance = [];
        $commentProvenance = [];
        $anchorProvenance = [];
        $aliasProvenance = [];
        $mergeProvenance = [];
        $scalarProvenance = [];
        $collectionProvenance = [];
        $streamProvenance = [];
        $fieldQuoteMap = $this->yamlMetadataFieldQuoteMap($metadata['__yamlMetadataFieldQuoteMap'] ?? []);
        foreach ($metadata as $key => $value) {
            $fieldName = (string) $key;
            if ($fieldName === '__yamlMetadataDiagnostics') {
                $diagnostics = array_merge($diagnostics, $this->yamlMetadataDiagnosticList($value));
                continue;
            }
            if ($fieldName === '__yamlMetadataTagProvenance') {
                $tagProvenance = array_merge($tagProvenance, $this->yamlMetadataTagProvenanceList($value));
                continue;
            }
            if ($fieldName === '__yamlMetadataDirectiveProvenance') {
                $directiveProvenance = array_merge($directiveProvenance, $this->yamlMetadataDirectiveProvenanceList($value));
                continue;
            }
            if ($fieldName === '__yamlMetadataCommentProvenance') {
                $commentProvenance = array_merge($commentProvenance, $this->yamlMetadataCommentProvenanceList($value));
                continue;
            }
            if ($fieldName === '__yamlMetadataAnchorProvenance') {
                $anchorProvenance = array_merge($anchorProvenance, $this->yamlMetadataAnchorProvenanceList($value));
                continue;
            }
            if ($fieldName === '__yamlMetadataAliasProvenance') {
                $aliasProvenance = array_merge($aliasProvenance, $this->yamlMetadataAliasProvenanceList($value));
                continue;
            }
            if ($fieldName === '__yamlMetadataMergeProvenance') {
                $mergeProvenance = array_merge($mergeProvenance, $this->yamlMetadataMergeProvenanceList($value));
                continue;
            }
            if ($fieldName === '__yamlMetadataScalarProvenance') {
                $scalarProvenance = array_merge($scalarProvenance, $this->yamlMetadataScalarProvenanceList($value));
                continue;
            }
            if ($fieldName === '__yamlMetadataCollectionProvenance') {
                $collectionProvenance = array_merge($collectionProvenance, $this->yamlMetadataCollectionProvenanceList($value));
                continue;
            }
            if ($fieldName === '__yamlMetadataStreamProvenance') {
                $streamProvenance = array_merge($streamProvenance, $this->yamlMetadataStreamProvenanceList($value));
                continue;
            }
            if ($fieldName === '__yamlMetadataFieldQuoteMap') {
                continue;
            }

            if (str_ends_with($fieldName, '_')) {
                continue;
            }

            $ambiguousFieldType = ($fieldQuoteMap[$fieldName] ?? false)
                ? null
                : $this->yamlAmbiguousMetadataFieldNameType($fieldName);
            if ($ambiguousFieldType !== null) {
                $diagnostics[] = [
                    'type' => 'yaml-field-name',
                    'reason' => 'ambiguous-field-name',
                    'field' => $fieldName,
                    'interpretedAs' => $ambiguousFieldType,
                ];
                continue;
            }

            if ($fieldName === 'title') {
                $lines = $this->metadataLinesFromYamlValue($value);
                if ($this->metadataPlainText($lines) !== '') {
                    $meta['title'] = $this->metadataPlainText($lines);
                    $meta['titleInlines'] = $this->metadataInlines($lines);
                }
                continue;
            }

            if ($fieldName === 'author' || $fieldName === 'authors') {
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

            if ($fieldName === 'date') {
                $lines = $this->metadataLinesFromYamlValue($value);
                if ($this->metadataPlainText($lines) !== '') {
                    $meta['date'] = $this->metadataPlainText($lines);
                    $meta['dateInlines'] = $this->metadataInlines($lines);
                }
                continue;
            }

            if ($fieldName === 'abstract') {
                $meta['abstract'] = $value;
                $abstractBlocks = $this->metadataBlocksFromYamlValue($value);
                if ($abstractBlocks !== []) {
                    $meta['abstractBlocks'] = $abstractBlocks;
                }
                continue;
            }

            $meta[$fieldName] = $value;
        }

        $attrs = $meta === [] ? [] : ['meta' => $meta];
        if ($diagnostics !== []) {
            $attrs['yamlMetadataDiagnostics'] = $diagnostics;
        }
        if ($tagProvenance !== []) {
            $attrs['yamlMetadataTagProvenance'] = $tagProvenance;
        }
        if ($directiveProvenance !== []) {
            $attrs['yamlMetadataDirectiveProvenance'] = $directiveProvenance;
        }
        if ($commentProvenance !== []) {
            $attrs['yamlMetadataCommentProvenance'] = $commentProvenance;
        }
        if ($anchorProvenance !== []) {
            $attrs['yamlMetadataAnchorProvenance'] = $anchorProvenance;
        }
        if ($aliasProvenance !== []) {
            $attrs['yamlMetadataAliasProvenance'] = $aliasProvenance;
        }
        if ($mergeProvenance !== []) {
            $attrs['yamlMetadataMergeProvenance'] = $mergeProvenance;
        }
        if ($scalarProvenance !== []) {
            $attrs['yamlMetadataScalarProvenance'] = $scalarProvenance;
        }
        if ($collectionProvenance !== []) {
            $attrs['yamlMetadataCollectionProvenance'] = $collectionProvenance;
        }
        if ($streamProvenance !== []) {
            $attrs['yamlMetadataStreamProvenance'] = $streamProvenance;
        }
        if (
            $meta !== []
            || $diagnostics !== []
            || $tagProvenance !== []
            || $directiveProvenance !== []
            || $commentProvenance !== []
            || $anchorProvenance !== []
            || $aliasProvenance !== []
            || $mergeProvenance !== []
            || $scalarProvenance !== []
            || $collectionProvenance !== []
            || $streamProvenance !== []
        ) {
            $attrs['yamlMetadataReviewSummary'] = $this->yamlMetadataReviewSummary(
                $meta,
                $diagnostics,
                $tagProvenance,
                $directiveProvenance,
                $commentProvenance,
                $anchorProvenance,
                $aliasProvenance,
                $mergeProvenance,
                $scalarProvenance,
                $collectionProvenance,
                $streamProvenance
            );
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<array<string, string>> $diagnostics
     * @param list<array<string, string>> $tagProvenance
     * @param list<array<string, string>> $directiveProvenance
     * @param list<array<string, string>> $commentProvenance
     * @param list<array<string, string>> $anchorProvenance
     * @param list<array<string, string>> $aliasProvenance
     * @param list<array<string, string>> $mergeProvenance
     * @param list<array<string, string>> $scalarProvenance
     * @param list<array<string, string>> $collectionProvenance
     * @param list<array<string, string>> $streamProvenance
     * @return array<string, mixed>
     */
    private function yamlMetadataReviewSummary(
        array $meta,
        array $diagnostics,
        array $tagProvenance,
        array $directiveProvenance,
        array $commentProvenance,
        array $anchorProvenance,
        array $aliasProvenance,
        array $mergeProvenance,
        array $scalarProvenance,
        array $collectionProvenance,
        array $streamProvenance
    ): array {
        $fields = [];
        foreach (array_keys($meta) as $field) {
            $fieldName = (string) $field;
            if (
                $fieldName === ''
                || str_ends_with($fieldName, 'Inlines')
                || $fieldName === 'abstractBlocks'
            ) {
                continue;
            }

            $fields[] = $fieldName;
        }

        $diagnosticReasons = [];
        $diagnosticTypes = [];
        $overriddenFields = [];
        foreach ($diagnostics as $diagnostic) {
            $reason = (string) ($diagnostic['reason'] ?? '');
            if ($reason !== '') {
                $diagnosticReasons[$reason] = ($diagnosticReasons[$reason] ?? 0) + 1;
            }

            $type = (string) ($diagnostic['type'] ?? '');
            if ($type !== '') {
                $diagnosticTypes[$type] = ($diagnosticTypes[$type] ?? 0) + 1;
            }

            if ($reason === 'stream-field-overridden') {
                $field = (string) ($diagnostic['field'] ?? '');
                if ($field !== '' && !in_array($field, $overriddenFields, true)) {
                    $overriddenFields[] = $field;
                }
            }
        }

        $documentSources = [];
        $sourceStartLine = null;
        $sourceEndLine = null;
        foreach ($streamProvenance as $stream) {
            $source = (string) ($stream['source'] ?? '');
            if ($source !== '') {
                $documentSources[] = $source;
            }

            $startLine = (string) ($stream['startLine'] ?? '');
            if ($startLine !== '' && ctype_digit($startLine)) {
                $line = (int) $startLine;
                $sourceStartLine = $sourceStartLine === null ? $line : min($sourceStartLine, $line);
            }

            $endLine = (string) ($stream['endLine'] ?? '');
            if ($endLine !== '' && ctype_digit($endLine)) {
                $line = (int) $endLine;
                $sourceEndLine = $sourceEndLine === null ? $line : max($sourceEndLine, $line);
            }
        }

        $summary = [
            'type' => 'yaml-metadata-review',
            'reviewStatus' => $diagnostics === [] ? 'clean' : 'needs-review',
            'fieldCount' => count($fields),
            'fields' => $fields,
            'documentCount' => count($streamProvenance),
            'documentSources' => $documentSources,
            'diagnosticCount' => count($diagnostics),
            'diagnosticReasons' => $diagnosticReasons,
            'diagnosticTypes' => $diagnosticTypes,
            'overriddenFields' => $overriddenFields,
            'tagCount' => count($tagProvenance),
            'directiveCount' => count($directiveProvenance),
            'commentCount' => count($commentProvenance),
            'anchorCount' => count($anchorProvenance),
            'aliasCount' => count($aliasProvenance),
            'mergeCount' => count($mergeProvenance),
            'scalarCount' => count($scalarProvenance),
            'collectionCount' => count($collectionProvenance),
            'streamCount' => count($streamProvenance),
        ];

        if ($sourceStartLine !== null) {
            $summary['sourceStartLine'] = (string) $sourceStartLine;
        }
        if ($sourceEndLine !== null) {
            $summary['sourceEndLine'] = (string) $sourceEndLine;
        }

        return $summary;
    }

    private function yamlAmbiguousMetadataFieldNameType(string $fieldName): ?string
    {
        $normalized = trim($fieldName);
        if ($normalized === '') {
            return null;
        }

        if (in_array(strtolower($normalized), ['true', 'false', 'yes', 'no', 'on', 'off'], true)) {
            return 'bool';
        }

        if (
            is_numeric($normalized)
            || preg_match('/^[+-]?(?:0x[0-9a-f_]+|0o[0-7_]+|0b[01_]+)$/i', $normalized) === 1
            || preg_match('/^[+-]?(?:\.(?:inf|nan)|(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?)$/i', $normalized) === 1
        ) {
            return 'number';
        }

        return null;
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
     * @return list<AstNode>
     */
    private function metadataBlocksFromYamlValue(mixed $value): array
    {
        $markdown = $this->metadataMarkdownSourceFromYamlValue($value);
        if ($markdown === '') {
            return [];
        }

        $reader = new self(array_replace($this->options, ['yamlMetadata' => false]));

        return $reader->read($markdown)->children;
    }

    private function metadataMarkdownSourceFromYamlValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        $lines = $this->metadataLinesFromYamlValue($value);

        return trim(implode("\n", $lines));
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

            if ($this->isAbbreviationDefinitionLine($expanded)) {
                continue;
            }

            $reference = $this->tryParseReferenceDefinitionStart($expanded);
            if ($reference !== null) {
                [$targetSource, $nextIndex] = $this->collectReferenceDefinitionTarget($lines, $index, $reference['content']);
                $target = $this->parseLinkDestinationAndTitle($targetSource);
                if ($target !== null) {
                    $label = $this->normalizeReferenceLabel($reference['label']);
                    if (!isset($references[$label])) {
                        $references[$label] = [
                            'url' => $target['url'],
                            'title' => $target['title'],
                        ];
                    }
                    $index = $nextIndex - 1;
                    continue;
                }
            }

            $content[] = $line;
        }

        return [$content, $references, $footnotes];
    }

    private function isAbbreviationDefinitionLine(string $line): bool
    {
        return preg_match('/^ {0,3}\*\[[^\]\r\n]+\]:[ \t]*(.*)$/', $line) === 1;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>, 1:array<string, string>}
     */
    private function extractAbbreviationDefinitions(array $lines): array
    {
        $content = [];
        $abbreviations = [];
        $fenceChar = null;
        $fenceLength = 0;

        foreach ($lines as $line) {
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

            if (preg_match('/^ {0,3}\*\[([^\]\r\n]+)\]:[ \t]*(\S.*)?$/u', $line, $m) === 1) {
                $term = trim($this->decodeHtmlEntities($m[1]));
                $title = trim($this->decodeHtmlEntities($m[2] ?? ''));
                if ($term !== '' && $title !== '') {
                    $abbreviations[$term] = $title;
                    continue;
                }
            }

            $content[] = $line;
        }

        return [$content, $this->sortedAbbreviationDefinitions($abbreviations)];
    }

    /**
     * @param array<string, string> $definitions
     * @return array<string, string>
     */
    private function sortedAbbreviationDefinitions(array $definitions): array
    {
        uksort($definitions, static fn (string $left, string $right): int => strlen($right) <=> strlen($left) ?: strcmp($left, $right));

        return $definitions;
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
        $label = $this->decodeHtmlEntities($this->unescapeLinkComponent($label));

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
                $index = $heading['endIndex'];
            }
        }

        return [$idsByLine, $references];
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function sectionizeMarkdownHeadingBlocks(array $blocks): array
    {
        $index = 0;

        return $this->sectionizeMarkdownHeadingLevel($blocks, $index, 0);
    }

    private function sectionDivsEnabled(): bool
    {
        return ($this->options['sectionDivs'] ?? false) === true
            && $this->sectionDivsSuppressionDepth === 0;
    }

    private function readMarkdownWithSectionDivsSuppressed(string $markdown): AstNode
    {
        $this->sectionDivsSuppressionDepth++;
        try {
            return $this->read($markdown);
        } finally {
            $this->sectionDivsSuppressionDepth--;
        }
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function sectionizeMarkdownHeadingLevel(array $blocks, int &$index, int $parentLevel): array
    {
        $sectioned = [];
        $count = count($blocks);

        while ($index < $count) {
            $node = $blocks[$index];
            if ($node->type !== 'heading') {
                $sectioned[] = $node;
                $index++;
                continue;
            }

            $level = max(1, min(6, (int) $node->attr('level', 1)));
            if ($level <= $parentLevel) {
                break;
            }

            $index++;
            $children = [$this->markdownSectionHeading($node)];
            while ($index < $count) {
                $next = $blocks[$index];
                if ($next->type === 'heading') {
                    $nextLevel = max(1, min(6, (int) $next->attr('level', 1)));
                    if ($nextLevel <= $level) {
                        break;
                    }

                    array_push($children, ...$this->sectionizeMarkdownHeadingLevel($blocks, $index, $level));
                    continue;
                }

                $children[] = $next;
                $index++;
            }

            $sectioned[] = new AstNode('div', $this->markdownSectionAttrs($node, $level), $children);
        }

        return $sectioned;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function sectionizeMarkdownExplicitSectionChildren(array $blocks, int $parentLevel): array
    {
        $sectioned = [];
        $index = 0;
        $count = count($blocks);

        while ($index < $count) {
            $node = $blocks[$index];
            if ($node->type === 'heading') {
                $level = max(1, min(6, (int) $node->attr('level', 1)));
                if ($level > $parentLevel) {
                    array_push($sectioned, ...$this->sectionizeMarkdownHeadingLevel($blocks, $index, $parentLevel));
                    continue;
                }

                $sectioned[] = $node;
                $index++;
                continue;
            }

            if ($node->type === 'div') {
                $sectionLevel = $this->markdownSectionLevel($node->attrs);
                $node = new AstNode(
                    'div',
                    $node->attrs,
                    $this->sectionizeMarkdownExplicitSectionChildren(
                        $node->children,
                        $sectionLevel ?? $parentLevel
                    )
                );
            }

            $sectioned[] = $node;
            $index++;
        }

        return $sectioned;
    }

    private function markdownSectionHeading(AstNode $heading): AstNode
    {
        $attrs = $heading->attrs;
        unset($attrs['id'], $attrs['classes'], $attrs['attributes'], $attrs['htmlAttributes']);

        return new AstNode('heading', $attrs, $heading->children);
    }

    /**
     * @return array<string, mixed>
     */
    private function markdownSectionAttrs(AstNode $heading, int $level): array
    {
        $id = $heading->attr('id', '');
        $classes = ['section', 'level' . $level];
        $sourceClasses = $heading->attr('classes', []);
        if (is_array($sourceClasses)) {
            foreach ($sourceClasses as $class) {
                $class = (string) $class;
                if ($class !== '' && !in_array($class, $classes, true)) {
                    $classes[] = $class;
                }
            }
        }

        $attributes = $heading->attr('attributes', []);
        if (!is_array($attributes)) {
            $attributes = [];
        }

        return $this->markdownAttributeAstAttrs(
            is_string($id) && $id !== '' ? $id : null,
            $classes,
            array_filter(
                array_map(static fn (mixed $value): string => (string) $value, $attributes),
                static fn (string $value): bool => $value !== ''
            )
        );
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function markdownSectionLevel(array $attrs): ?int
    {
        $classes = $attrs['classes'] ?? [];
        if (!is_array($classes) || !in_array('section', $classes, true)) {
            return null;
        }

        foreach ($classes as $class) {
            if (is_string($class) && preg_match('/^level([1-6])$/', $class, $m) === 1) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * @return array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>}|null
     */
    private function tryParseMarkdownHeading(string $line): ?array
    {
        if (preg_match('/^ {0,3}(#{1,6})(?:[ \t]+(.*)|[ \t]*)$/', $line, $m) !== 1) {
            return null;
        }

        $text = $this->stripClosingAtxHeadingFence(trim($m[2] ?? ''));

        return $this->buildMarkdownHeading(strlen($m[1]), $text, true);
    }

    /**
     * @param list<string> $lines
     * @return array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>, endIndex:int}|null
     */
    private function tryParseSetextMarkdownHeading(array $lines, int $index): ?array
    {
        if (!isset($lines[$index + 1])) {
            return null;
        }

        $headingLines = [];
        $cursor = $index;
        $count = count($lines);
        while ($cursor < $count) {
            $line = $this->expandTabsToSpaces($lines[$cursor]);
            if (!$this->canBeSetextHeadingContentLine($line)) {
                return null;
            }

            $headingLines[] = trim($line);
            $markerIndex = $cursor + 1;
            if ($markerIndex < $count) {
                $marker = $this->expandTabsToSpaces($lines[$markerIndex]);
                if (preg_match('/^ {0,3}(=+|-+)[ \t]*$/', $marker, $m) === 1) {
                    $heading = $this->buildMarkdownHeading(
                        $m[1][0] === '=' ? 1 : 2,
                        implode(' ', $headingLines)
                    );
                    $heading['endIndex'] = $markerIndex;

                    return $heading;
                }
            }

            $cursor++;
        }

        return null;
    }

    private function canBeSetextHeadingContentLine(string $line): bool
    {
        if ($this->countIndentColumns($line) > 3) {
            return false;
        }

        $text = trim($line);
        if ($text === '' || $this->tryParseMarkdownHeading($line) !== null || $this->isHorizontalRule($line)) {
            return false;
        }
        $listMarker = $this->matchListMarker($line);
        if ($listMarker !== null && trim($listMarker['text']) === '') {
            return false;
        }

        if (preg_match('/^ {0,3}[A-Za-z0-9_.-]+:\s*[>|](?:[+-]?[0-9]*)?\s*$/', $line) === 1) {
            return false;
        }

        $marker = $this->matchListMarker($line);
        if ($marker !== null && $marker['indent'] <= 3) {
            return false;
        }

        return preg_match('/^ {0,3}(?:`{3,}|~{3,}|>|<\/?[A-Za-z]|<!--|<\?|<!)/', $line) !== 1;
    }

    private function stripClosingAtxHeadingFence(string $text): string
    {
        if (preg_match('/^#+$/', $text) === 1) {
            return '';
        }

        return rtrim(preg_replace('/[ \t]+#+[ \t]*$/', '', $text) ?? $text);
    }

    /**
     * @return array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>}
     */
    private function buildMarkdownHeading(int $level, string $text, bool $stripClosingAtxFence = false): array
    {
        $id = null;
        $classes = [];
        $attributes = [];

        if (preg_match('/^(.*?)[ \t]*\{([^{}]+)\}[ \t]*$/', $text, $attrs) === 1) {
            $text = rtrim($attrs[1]);
            [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec($attrs[2]);
            if ($stripClosingAtxFence) {
                $text = $this->stripClosingAtxHeadingFence($text);
            }
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

        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            while ($offset < $length && ctype_space($source[$offset])) {
                $offset++;
            }
            if ($offset >= $length) {
                break;
            }

            $char = $source[$offset];
            if ($char !== '#' && $char !== '.') {
                $attribute = $this->readMarkdownKeyValueAttribute($source, $offset);
                if ($attribute !== null) {
                    $attributes[$attribute['name']] = $this->decodeHtmlEntities($this->unescapeLinkComponent($attribute['value']));
                    continue;
                }
            }

            $token = $this->readMarkdownAttributeToken($source, $offset);
            if (strlen($token) < 2) {
                continue;
            }

            if ($token[0] === '#') {
                $id = substr($token, 1);
                continue;
            }
            if ($token[0] === '.') {
                $classes[] = substr($token, 1);
            }
        }

        return [$id, $classes, $attributes];
    }

    private function readMarkdownAttributeToken(string $source, int &$offset): string
    {
        $start = $offset;
        $length = strlen($source);
        while ($offset < $length && !ctype_space($source[$offset])) {
            $offset++;
        }

        return substr($source, $start, $offset - $start);
    }

    /**
     * @return array{name:string, value:string}|null
     */
    private function readMarkdownKeyValueAttribute(string $source, int &$offset): ?array
    {
        $start = $offset;
        $length = strlen($source);
        if (preg_match('/^[A-Za-z_:][A-Za-z0-9_.:-]*/', substr($source, $offset), $m) !== 1) {
            return null;
        }

        $name = $m[0];
        $offset += strlen($name);
        if (($source[$offset] ?? '') !== '=') {
            $offset = $start;
            return null;
        }

        $offset++;
        if ($offset >= $length) {
            $offset = $start;
            return null;
        }

        $quote = $source[$offset];
        if ($quote === '"' || $quote === "'") {
            $value = $this->readMarkdownQuotedAttributeValue($source, $offset, $quote);
            if ($value === null) {
                $offset = $start;
                return null;
            }

            return ['name' => $name, 'value' => $value];
        }

        $valueStart = $offset;
        while ($offset < $length && !ctype_space($source[$offset])) {
            $offset++;
        }
        if ($offset === $valueStart) {
            $offset = $start;
            return null;
        }

        return ['name' => $name, 'value' => substr($source, $valueStart, $offset - $valueStart)];
    }

    private function readMarkdownQuotedAttributeValue(string $source, int &$offset, string $quote): ?string
    {
        $offset++;
        $start = $offset;
        $length = strlen($source);
        while ($offset < $length) {
            if ($source[$offset] === $quote && !$this->isEscapedMarkdownAttributeQuote($source, $offset)) {
                $value = substr($source, $start, $offset - $start);
                $offset++;

                return $value;
            }

            $offset++;
        }

        return null;
    }

    private function isEscapedMarkdownAttributeQuote(string $source, int $offset): bool
    {
        $slashes = 0;
        for ($index = $offset - 1; $index >= 0 && $source[$index] === '\\'; $index--) {
            $slashes++;
        }

        return $slashes % 2 === 1;
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
        if (preg_match('/^ {0,3}/', $line, $indent) !== 1) {
            return null;
        }

        $offset = strlen($indent[0]);
        if (substr($line, $offset, 2) === '[^') {
            return null;
        }

        $label = $this->parseBracketedLabel($line, $offset);
        if ($label === null || $label['text'] === '' || ($line[$label['next']] ?? '') !== ':') {
            return null;
        }

        return [
            'label' => $label['text'],
            'content' => rtrim(substr($line, $label['next'] + 1)),
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
                $rawFormat = $this->rawFormatAttributeSpec($info);
                if ($rawFormat !== null) {
                    $index = $cursor;

                    return new AstNode('raw_block', [
                        'format' => $rawFormat,
                        'text' => implode("\n", $content),
                    ]);
                }

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

        $rawFormat = $this->rawFormatAttributeSpec($info);
        if ($rawFormat !== null) {
            $index = $cursor - 1;

            return new AstNode('raw_block', [
                'format' => $rawFormat,
                'text' => implode("\n", $content),
            ]);
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
        while ($cursor < $count) {
            if ($this->isBlockQuoteLine($lines[$cursor])) {
                $content[] = $this->stripBlockQuoteMarker($lines[$cursor]);
                $cursor++;
                continue;
            }

            if (!$this->isLazyBlockQuoteContinuationLine($lines, $cursor, $content)) {
                break;
            }

            $content[] = $lines[$cursor];
            $cursor++;
        }

        $index = $cursor - 1;

        $alert = $this->buildAlertBlockQuote($content);
        if ($alert !== null) {
            return $alert;
        }

        $inner = $this->read(implode("\n", $content));

        return new AstNode('blockquote', [], $inner->children);
    }

    /**
     * @param list<string> $content
     */
    private function buildAlertBlockQuote(array $content): ?AstNode
    {
        $first = $content[0] ?? null;
        if ($first === null || preg_match('/^\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*$/i', $first, $m) !== 1) {
            return null;
        }

        $type = strtolower($m[1]);
        $bodyLines = array_slice($content, 1);
        while ($bodyLines !== [] && trim($bodyLines[0]) === '') {
            array_shift($bodyLines);
        }

        $title = ucfirst($type);
        $children = [
            new AstNode('div', ['classes' => ['title']], [
                new AstNode('paragraph', ['text' => $title], [new AstNode('text', ['text' => $title])]),
            ]),
        ];
        if ($bodyLines !== []) {
            $body = $this->read(implode("\n", $bodyLines));
            array_push($children, ...$body->children);
        }

        return new AstNode('div', [
            'classes' => [$type],
            'htmlAttributes' => ['class' => $type],
        ], $children);
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
    private function tryReadFencedDivBlock(array $lines, int &$index): ?AstNode
    {
        $opening = $this->matchFencedDivOpening($lines[$index] ?? '');
        if ($opening === null) {
            return null;
        }

        $content = [];
        $count = count($lines);
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            if ($this->isFencedDivClosing($lines[$cursor], $opening['length'])) {
                $index = $cursor;
                $sectionLevel = $this->sectionDivsEnabled() ? $this->markdownSectionLevel($opening['attrs']) : null;
                if ($content === []) {
                    $inner = [];
                } elseif ($sectionLevel !== null) {
                    $inner = $this->sectionizeMarkdownExplicitSectionChildren(
                        $this->readMarkdownWithSectionDivsSuppressed(implode("\n", $content))->children,
                        $sectionLevel
                    );
                } else {
                    $inner = $this->read(implode("\n", $content))->children;
                }

                return new AstNode('div', $opening['attrs'], $inner);
            }

            $content[] = $lines[$cursor];
        }

        return null;
    }

    /**
     * @return array{length:int, attrs:array<string, mixed>}|null
     */
    private function matchFencedDivOpening(string $line): ?array
    {
        $line = $this->expandTabsToSpaces($line);
        if (preg_match('/^ {0,3}(:{3,})(?:[ \t]+(.*?))?[ \t]*$/', $line, $m) !== 1) {
            return null;
        }

        $attrs = $this->parseFencedDivAttributes(trim($m[2] ?? ''));
        if ($attrs === null) {
            return null;
        }

        return [
            'length' => strlen($m[1]),
            'attrs' => $attrs,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseFencedDivAttributes(string $info): ?array
    {
        if ($info === '') {
            return [];
        }

        if ($info[0] === '{' && str_ends_with($info, '}')) {
            [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec(substr($info, 1, -1));

            return $this->markdownAttributeAstAttrs($id, $classes, $attributes);
        }

        $classes = preg_split('/[ \t]+/', $info, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($classes as $class) {
            if (preg_match('/^[A-Za-z0-9_-]+$/', $class) !== 1) {
                return null;
            }
        }

        return $classes === [] ? [] : $this->markdownAttributeAstAttrs(null, $classes, []);
    }

    private function isFencedDivClosing(string $line, int $openingLength): bool
    {
        $line = $this->expandTabsToSpaces($line);
        if (preg_match('/^ {0,3}(:{3,})[ \t]*$/', $line, $m) !== 1) {
            return false;
        }

        return strlen($m[1]) >= $openingLength;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadDivBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        $opening = $this->readHtmlDivOpeningTag($line);
        if ($opening === null) {
            return null;
        }

        $content = [];
        $depth = 1;
        $openingIndex = $index;
        $cursor = $index;
        $count = count($lines);
        $firstLineOffset = $opening['offset'] + $opening['length'];

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

                    return $this->buildDivBlock($content, $closedOnOpeningLine, $opening['attrs']);
                }

                $lineContent .= substr($segment, $offset, $nextClose['offset'] + $nextClose['length'] - $offset);
                $offset = $nextClose['offset'] + $nextClose['length'];
            }

            $content[] = $lineContent;
            $cursor++;
        }

        if ($this->divBlockContentIsBlank($content)) {
            $index = max($openingIndex, $count - 1);

            return new AstNode('div', $opening['attrs']);
        }

        return null;
    }

    /**
     * @return array{offset:int, length:int, attrs:array<string, mixed>}|null
     */
    private function readHtmlDivOpeningTag(string $line): ?array
    {
        if (preg_match('/^ {0,3}</', $line, $start, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $start[0][1];
        $source = $this->readRawHtmlInlineTagSource($line, $offset);
        if ($source === null || preg_match('~^<div(?:\s|/?>)~i', $source) !== 1) {
            return null;
        }

        return [
            'offset' => $offset,
            'length' => strlen($source),
            'attrs' => $this->htmlDivOpeningTagAttrs($source),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlDivOpeningTagAttrs(string $source): array
    {
        $body = XmlHtml5Dom::parseHtmlFragmentBody($source . '</div>');
        if (!$body instanceof \DOMElement) {
            return [];
        }

        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'div') {
                return $this->htmlElementPandocAttrs($child);
            }
        }

        return [];
    }

    /**
     * @param list<string> $content
     */
    private function divBlockContentIsBlank(array $content): bool
    {
        foreach ($content as $line) {
            if (trim($line) !== '') {
                return false;
            }
        }

        return true;
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
    private function buildDivBlock(array $content, bool $closedOnOpeningLine, array $attrs = []): AstNode
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

            return new AstNode('div', $attrs, [
                new AstNode('plain', ['text' => $text], $this->parseInlines($text)),
            ]);
        }

        $inner = $this->read(implode("\n", $content));

        return new AstNode('div', $attrs, $inner->children);
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

        if (preg_match('/^ {0,3}<\?/', $line) === 1) {
            return $this->readRawHtmlUntilMarker($lines, $index, '?>');
        }

        if (preg_match('/^ {0,3}<![A-Za-z]/', $line) === 1) {
            return $this->readRawHtmlUntilMarker($lines, $index, '>');
        }

        if (preg_match('/^ {0,3}<!\[CDATA\[/', $line) === 1) {
            return $this->readRawHtmlUntilMarker($lines, $index, ']]>');
        }

        if (preg_match('~^ {0,3}<(script|pre|style|textarea)(?=\s|>|/>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?\s*/?>~iu', $line, $m) === 1) {
            return $this->readRawHtmlUntilClosingTag($lines, $index, strtolower($m[1]));
        }

        if (preg_match('~^ {0,3}(?:</([A-Za-z][A-Za-z0-9-]*)\s*>|<([A-Za-z][A-Za-z0-9-]*)(?=\s|>|/>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?\s*/?>)~iu', $line, $m) === 1) {
            $tag = strtolower((string) ($m[1] !== '' ? $m[1] : ($m[2] ?? '')));
            if ($this->isCommonMarkBlankTerminatedRawHtmlTag($tag)) {
                return $this->readRawHtmlUntilBlankLine($lines, $index, $tag === 'table');
            }
        }

        if (preg_match('~^ {0,3}<(abbr|address|animate|animateMotion|animateTransform|annotation|annotation-xml|article|aside|audio|bdi|bdo|button|canvas|circle|cite|clipPath|data|datalist|defs|desc|details|dfn|dialog|ellipse|feBlend|feComposite|feDropShadow|feFlood|feGaussianBlur|feOffset|fieldset|figcaption|figure|filter|footer|foreignObject|form|g|header|hgroup|iframe|image|label|legend|line|linearGradient|main|maligngroup|malignmark|map|marker|mark|mask|math|maction|metadata|menclose|merror|menu|meter|mfenced|mfrac|mglyph|mi|mlabeledtr|mlongdiv|mmultiscripts|mn|mo|mover|mpadded|mpath|mphantom|mprescripts|mroot|mrow|ms|mscarries|mscarry|msgroup|msline|mspace|msqrt|msrow|mstack|mstyle|msub|msubsup|msup|mtable|mtd|mtext|mtr|munder|munderover|nav|none|object|optgroup|option|output|path|pattern|picture|polygon|polyline|pre|progress|radialGradient|rect|rp|rt|ruby|script|search|section|semantics|set|slot|small|stop|style|select|summary|svg|switch|symbol|template|text|textarea|textPath|time|tspan|use|var|video|view)(?=\s|>|/>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?\s*/?>~iu', $line, $m) === 1) {
            return $this->readRawHtmlUntilClosingTag($lines, $index, strtolower($m[1]));
        }

        if (preg_match('~^ {0,3}<table(?=\s|>|/>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?\s*/?>~iu', $line) === 1) {
            return $this->readRawHtmlUntilClosingTag($lines, $index, 'table', true);
        }

        if (preg_match('~^ {0,3}<(?:area|base|col|embed|hr|input|link|meta|param|source|track|wbr)(?=\s|>|/>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?\s*/?>[ \t]*$~iu', $line) === 1) {
            return new AstNode('raw_html', ['html' => trim($line)]);
        }

        if ($this->isCommonMarkGenericRawHtmlBlockStart($line)) {
            return $this->readRawHtmlUntilBlankLine($lines, $index);
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
    private function tryReadDocBookListBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<(?:(?:[A-Za-z_][A-Za-z0-9_.-]*):)?(itemizedlist|orderedlist|variablelist)\b[^>]*>/i', $line, $m) !== 1) {
            return null;
        }

        $balanced = $this->collectBalancedDocBookElementBlock($lines, $index, strtolower($m[1]));
        if ($balanced === null) {
            return null;
        }

        [$xml, $end] = $balanced;
        $list = $this->parseDocBookListBlock($xml);
        if ($list === null) {
            return null;
        }

        $index = $end;

        return $list;
    }

    /**
     * @param list<string> $lines
     * @return array{0:string, 1:int}|null
     */
    private function collectBalancedDocBookElementBlock(array $lines, int $index, string $tag): ?array
    {
        $content = [];
        $depth = 0;
        $started = false;
        $count = count($lines);
        $pattern = '/<\/?(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . preg_quote($tag, '/') . '\b[^>]*>/i';

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $line = rtrim($lines[$cursor]);
            $content[] = $line;

            preg_match_all($pattern, $line, $matches);
            foreach ($matches[0] as $matchedTag) {
                $trimmedTag = strtolower(trim($matchedTag));
                if (str_starts_with($trimmedTag, '</')) {
                    if ($started) {
                        $depth--;
                    }
                } else {
                    $started = true;
                    if (!str_ends_with(rtrim($trimmedTag), '/>')) {
                        $depth++;
                    }
                }

                if ($started && $depth === 0) {
                    return [implode("\n", $content), $cursor];
                }
            }
        }

        return null;
    }

    private function parseDocBookListBlock(string $xml): ?AstNode
    {
        try {
            $dom = XmlHtml5Dom::parseXmlDocument($xml, 'DocBook list XML');
        } catch (\InvalidArgumentException) {
            return null;
        }

        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return null;
        }

        $diagnostics = [];
        $name = strtolower($root->localName);
        $list = match ($name) {
            'itemizedlist' => $this->parseDocBookItemizedListElement($root, $diagnostics),
            'orderedlist' => $this->parseDocBookOrderedListElement($root, $diagnostics),
            'variablelist' => $this->parseDocBookVariableListElement($root, $diagnostics),
            default => null,
        };
        if ($list === null) {
            return null;
        }

        if ($diagnostics !== []) {
            $list = new AstNode(
                $list->type,
                array_merge($list->attrs, ['docbookListDiagnostics' => $diagnostics]),
                $list->children
            );
        }

        return $list;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private function parseDocBookItemizedListElement(\DOMElement $list, array &$diagnostics): ?AstNode
    {
        $items = [];
        $loose = false;
        $title = null;
        foreach ($list->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->localName);
            if ($name === 'title') {
                $title = trim(preg_replace('/\s+/', ' ', $child->textContent) ?? $child->textContent);
                continue;
            }

            if ($name !== 'listitem') {
                $diagnostics[] = $this->docBookListDiagnostic('docbook-list-child-unsupported', $child, count($items));
                continue;
            }

            $item = $this->parseDocBookListItemElement($child, count($items) + 1, $diagnostics);
            $items[] = $item;
            $loose = $loose || (bool) $item->attr('loose', false);
        }

        if ($items === [] && $title === null) {
            return null;
        }

        return new AstNode('bullet_list', [
            'loose' => $loose,
            'docbookListMetadata' => $this->docBookListMetadata($list, 'itemizedlist', count($items), $title),
        ], $items);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private function parseDocBookOrderedListElement(\DOMElement $list, array &$diagnostics): ?AstNode
    {
        $items = [];
        $loose = false;
        $title = null;
        foreach ($list->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->localName);
            if ($name === 'title') {
                $title = trim(preg_replace('/\s+/', ' ', $child->textContent) ?? $child->textContent);
                continue;
            }

            if ($name !== 'listitem') {
                $diagnostics[] = $this->docBookListDiagnostic('docbook-list-child-unsupported', $child, count($items));
                continue;
            }

            $item = $this->parseDocBookListItemElement($child, count($items) + 1, $diagnostics);
            $items[] = $item;
            $loose = $loose || (bool) $item->attr('loose', false);
        }

        if ($items === [] && $title === null) {
            return null;
        }

        [$style, $styleDiagnostic] = $this->docBookOrderedListStyle($list);
        if ($styleDiagnostic !== null) {
            $diagnostics[] = $styleDiagnostic;
        }

        return new AstNode('ordered_list', [
            'loose' => $loose,
            'start' => $this->docBookOrderedListStart($list),
            'style' => $style,
            'delimiter' => 'default',
            'docbookListMetadata' => $this->docBookListMetadata($list, 'orderedlist', count($items), $title),
        ], $items);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private function parseDocBookVariableListElement(\DOMElement $list, array &$diagnostics): ?AstNode
    {
        $items = [];
        $title = null;
        foreach ($list->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->localName);
            if ($name === 'title') {
                $title = trim(preg_replace('/\s+/', ' ', $child->textContent) ?? $child->textContent);
                continue;
            }

            if ($name !== 'varlistentry') {
                $diagnostics[] = $this->docBookListDiagnostic('docbook-variablelist-child-unsupported', $child, count($items));
                continue;
            }

            $item = $this->parseDocBookVariableListEntryElement($child, count($items) + 1, $diagnostics);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        if ($items === [] && $title === null) {
            return null;
        }

        return new AstNode('definition_list', [
            'docbookListMetadata' => $this->docBookListMetadata($list, 'variablelist', count($items), $title),
        ], $items);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private function parseDocBookVariableListEntryElement(\DOMElement $entry, int $ordinal, array &$diagnostics): ?AstNode
    {
        $termInlines = [];
        $termTexts = [];
        $definitions = [];
        foreach ($entry->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->localName);
            if ($name === 'term') {
                if ($termInlines !== []) {
                    $termInlines[] = new AstNode('linebreak');
                }
                $inlines = $this->parseDocBookInlineNodes($child);
                array_push($termInlines, ...$inlines);
                $termTexts[] = trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($inlines)) ?? '');
                continue;
            }

            if ($name === 'listitem') {
                $item = $this->parseDocBookListItemElement($child, count($definitions) + 1, $diagnostics);
                $definitions[] = new AstNode(
                    'definition',
                    [
                        'loose' => (bool) $item->attr('loose', false),
                        'docbookListItemMetadata' => $item->attr('docbookListItemMetadata', []),
                    ],
                    $this->docBookDefinitionChildren($item->children)
                );
                continue;
            }

            $diagnostics[] = $this->docBookListDiagnostic('docbook-varlistentry-child-unsupported', $child, $ordinal);
        }

        if ($termInlines === []) {
            $diagnostics[] = [
                'code' => 'docbook-varlistentry-term-missing',
                'source' => 'docbook-variablelist',
                'entryOrdinal' => $ordinal,
            ];

            return null;
        }

        if ($definitions === []) {
            $diagnostics[] = [
                'code' => 'docbook-varlistentry-definition-missing',
                'source' => 'docbook-variablelist',
                'entryOrdinal' => $ordinal,
            ];
        }

        $termText = implode("\n", $termTexts);
        $term = new AstNode('term', ['text' => $termText], $termInlines);

        return new AstNode('definition_item', [
            'term' => $termText,
            'docbookListItemMetadata' => [
                'source' => 'docbook',
                'element' => 'varlistentry',
                'ordinal' => $ordinal,
                'attributes' => $this->docBookElementAttributes($entry),
            ],
        ], array_merge([$term], $definitions));
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function docBookDefinitionChildren(array $children): array
    {
        if ($children === []) {
            return [];
        }

        if ($this->docBookListChildrenAreInline($children)) {
            return [new AstNode('paragraph', ['text' => $this->plainTextFromInlines($children)], $children)];
        }

        return $children;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private function parseDocBookListItemElement(\DOMElement $item, int $ordinal, array &$diagnostics): AstNode
    {
        $children = [];
        $inlines = [];
        $itemDiagnostics = [];
        foreach ($item->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $this->appendDocBookTextNode($inlines, $child->wholeText);
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->localName);
            if (in_array($name, ['para', 'simpara'], true)) {
                $this->flushDocBookListItemInlines($inlines, $children);
                $paragraphInlines = $this->parseDocBookInlineNodes($child);
                $children[] = new AstNode(
                    'paragraph',
                    ['text' => trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($paragraphInlines)) ?? '')],
                    $paragraphInlines
                );
                continue;
            }

            if ($name === 'itemizedlist') {
                $this->flushDocBookListItemInlines($inlines, $children);
                $nested = $this->parseDocBookItemizedListElement($child, $diagnostics);
                if ($nested !== null) {
                    $children[] = $nested;
                }
                continue;
            }

            if ($name === 'orderedlist') {
                $this->flushDocBookListItemInlines($inlines, $children);
                $nested = $this->parseDocBookOrderedListElement($child, $diagnostics);
                if ($nested !== null) {
                    $children[] = $nested;
                }
                continue;
            }

            if ($name === 'variablelist') {
                $this->flushDocBookListItemInlines($inlines, $children);
                $nested = $this->parseDocBookVariableListElement($child, $diagnostics);
                if ($nested !== null) {
                    $children[] = $nested;
                }
                continue;
            }

            $diagnostic = $this->docBookListDiagnostic('docbook-listitem-child-unsupported', $child, $ordinal);
            $diagnostics[] = $diagnostic;
            $itemDiagnostics[] = $diagnostic;
            $fallbackInlines = $this->parseDocBookInlineNodes($child);
            if ($fallbackInlines !== []) {
                $this->flushDocBookListItemInlines($inlines, $children);
                $children[] = new AstNode(
                    'paragraph',
                    ['text' => trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($fallbackInlines)) ?? '')],
                    $fallbackInlines
                );
            }
        }

        $this->flushDocBookListItemInlines($inlines, $children);
        $loose = $this->docBookListItemIsLoose($children);
        $attrs = [
            'text' => trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($this->docBookFlattenListItemTextNodes($children))) ?? ''),
            'loose' => $loose,
            'docbookListItemMetadata' => [
                'source' => 'docbook',
                'element' => 'listitem',
                'ordinal' => $ordinal,
                'attributes' => $this->docBookElementAttributes($item),
            ],
        ];
        if ($itemDiagnostics !== []) {
            $attrs['docbookListItemDiagnostics'] = $itemDiagnostics;
        }

        return new AstNode('list_item', $attrs, $children);
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $children
     */
    private function flushDocBookListItemInlines(array &$inlines, array &$children): void
    {
        $text = trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($inlines)) ?? '');
        if ($text !== '') {
            array_push($children, ...$inlines);
        }

        $inlines = [];
    }

    /**
     * @param list<AstNode> $children
     */
    private function docBookListItemIsLoose(array $children): bool
    {
        foreach ($children as $child) {
            if (!$this->docBookListChildIsInline($child) && !in_array($child->type, ['bullet_list', 'ordered_list'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $children
     */
    private function docBookListChildrenAreInline(array $children): bool
    {
        foreach ($children as $child) {
            if (!$this->docBookListChildIsInline($child)) {
                return false;
            }
        }

        return true;
    }

    private function docBookListChildIsInline(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'emph',
            'strong',
            'code',
            'link',
            'softbreak',
            'linebreak',
        ], true);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function docBookFlattenListItemTextNodes(array $children): array
    {
        $inlines = [];
        foreach ($children as $child) {
            if ($this->docBookListChildIsInline($child)) {
                $inlines[] = $child;
                continue;
            }
            if (in_array($child->type, ['paragraph', 'plain', 'term'], true)) {
                array_push($inlines, ...$child->children);
            }
        }

        return $inlines;
    }

    /**
     * @return array{0:string, 1:array<string, mixed>|null}
     */
    private function docBookOrderedListStyle(\DOMElement $list): array
    {
        $numeration = strtolower(trim($list->getAttribute('numeration')));

        return match ($numeration) {
            '', 'arabic' => ['decimal', null],
            'loweralpha' => ['lower_alpha', null],
            'upperalpha' => ['upper_alpha', null],
            'lowerroman' => ['lower_roman', null],
            'upperroman' => ['upper_roman', null],
            default => ['default', [
                'code' => 'docbook-orderedlist-numeration-unsupported',
                'source' => 'docbook-orderedlist',
                'attribute' => 'numeration',
                'rawValue' => $numeration,
            ]],
        };
    }

    private function docBookOrderedListStart(\DOMElement $list): int
    {
        foreach (['startingnumber', 'start'] as $attribute) {
            $value = trim($list->getAttribute($attribute));
            if (preg_match('/^\d+$/', $value) === 1 && (int) $value > 0) {
                return (int) $value;
            }
        }

        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function docBookListMetadata(\DOMElement $list, string $kind, int $itemCount, ?string $title): array
    {
        $metadata = [
            'source' => 'docbook',
            'element' => $kind,
            'itemCount' => $itemCount,
            'attributes' => $this->docBookElementAttributes($list),
        ];
        if ($title !== null && $title !== '') {
            $metadata['title'] = $title;
        }

        return $metadata;
    }

    /**
     * @return array<string, string>
     */
    private function docBookElementAttributes(\DOMElement $element): array
    {
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $prefix = (string) $attribute->prefix;
            $name = $prefix !== ''
                ? strtolower($prefix . ':' . $attribute->localName)
                : strtolower($attribute->name);
            $value = trim(preg_replace('/\s+/', ' ', $attribute->value) ?? $attribute->value);
            if ($name === '' || $value === '') {
                continue;
            }

            $attributes[$name] = $value;
        }

        ksort($attributes);

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function docBookListDiagnostic(string $code, \DOMElement $element, int $ordinal): array
    {
        return [
            'code' => $code,
            'source' => 'docbook-list',
            'element' => strtolower($element->localName),
            'ordinal' => $ordinal,
            'attributes' => $this->docBookElementAttributes($element),
            'text' => trim(preg_replace('/\s+/', ' ', $element->textContent) ?? $element->textContent),
        ];
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
        try {
            $dom = XmlHtml5Dom::parseXmlDocument($xml, 'DocBook informal table XML');
        } catch (\InvalidArgumentException) {
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
        $dom = XmlHtml5Dom::parseHtmlDocument($html);
        if (!$dom instanceof \DOMDocument) {
            return null;
        }

        $body = XmlHtml5Dom::htmlBody($dom);
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

        $microdata = $this->htmlDocumentMicrodataItems($dom);
        if ($microdata !== []) {
            $meta['microdata'] = $microdata;
        }

        return $meta === [] ? [] : ['meta' => $meta];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function htmlDocumentMicrodataItems(\DOMDocument $dom): array
    {
        $items = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !$element->hasAttribute('itemscope')) {
                continue;
            }
            if ($element->hasAttribute('itemprop')) {
                continue;
            }

            $items[] = $this->readHtmlMicrodataItem($element);
        }

        return $items;
    }

    /**
     * @param array<string, true> $seen
     * @return array<string, mixed>
     */
    private function readHtmlMicrodataItem(\DOMElement $item, array $seen = []): array
    {
        $record = [
            'element' => strtolower($item->localName),
        ];
        $key = $this->htmlMicrodataElementKey($item);
        if (isset($seen[$key])) {
            $record['cycle'] = true;

            return $record;
        }
        $seen[$key] = true;

        $types = $this->htmlMicrodataUrlTokens($item->getAttribute('itemtype'));
        if ($types !== []) {
            $record['types'] = $types;
        }

        $id = $this->htmlMicrodataUrlValue($item->getAttribute('itemid'));
        if ($id !== null) {
            $record['id'] = $id;
        }

        $refs = $this->htmlMicrodataTermTokens($item->getAttribute('itemref'));
        if ($refs !== []) {
            $record['refs'] = $refs;
            $record['refCount'] = count($refs);
        }

        $properties = $this->htmlMicrodataItemProperties($item, $seen);
        if ($refs !== []) {
            $itemRef = $this->htmlMicrodataItemRefProperties($item, $refs, $seen);
            if ($itemRef['resolved'] !== []) {
                $record['resolvedRefs'] = $itemRef['resolved'];
                $record['resolvedRefCount'] = count($itemRef['resolved']);
            }
            if ($itemRef['missing'] !== []) {
                $record['missingRefs'] = $itemRef['missing'];
                $record['missingRefCount'] = count($itemRef['missing']);
            }
            if ($itemRef['properties'] !== []) {
                $properties = $this->mergeHtmlMicrodataProperties($properties, $itemRef['properties']);
            }
        }

        if ($properties !== []) {
            $record['properties'] = $properties;
            $summary = $this->htmlMicrodataPropertySummary($properties);
            $record['propertyCount'] = $summary['propertyCount'];
            if ($summary['valueCount'] > 0) {
                $record['valueCount'] = $summary['valueCount'];
            }
            if ($summary['nestedItemCount'] > 0) {
                $record['nestedItemCount'] = $summary['nestedItemCount'];
            }
            if ($summary['repeatedProperties'] !== []) {
                $record['repeatedProperties'] = $summary['repeatedProperties'];
                $record['repeatedPropertyCount'] = count($summary['repeatedProperties']);
            }
        }

        return $record;
    }

    /**
     * @param array<string, true> $seen
     * @return array<string, list<mixed>>
     */
    private function htmlMicrodataItemProperties(\DOMElement $item, array $seen): array
    {
        $properties = [];
        foreach ($item->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !$element->hasAttribute('itemprop')) {
                continue;
            }

            $owner = $this->nearestHtmlMicrodataAncestorItem($element);
            if (!$owner instanceof \DOMElement || !$owner->isSameNode($item)) {
                continue;
            }

            $propertyNames = $this->htmlMicrodataTermTokens($element->getAttribute('itemprop'));
            if ($propertyNames === []) {
                continue;
            }

            $value = $this->htmlMicrodataPropertyValue($element, $seen);
            if ($value === null) {
                continue;
            }

            foreach ($propertyNames as $name) {
                $properties[$name] ??= [];
                $properties[$name][] = $value;
            }
        }

        return $properties;
    }

    /**
     * @param list<string> $refs
     * @param array<string, true> $seen
     * @return array{resolved:list<string>,missing:list<string>,properties:array<string, list<mixed>>}
     */
    private function htmlMicrodataItemRefProperties(\DOMElement $item, array $refs, array $seen): array
    {
        $resolved = [];
        $missing = [];
        $properties = [];
        $document = $item->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return [
                'resolved' => [],
                'missing' => $refs,
                'properties' => [],
            ];
        }

        foreach ($refs as $ref) {
            $element = $this->htmlElementById($document, $ref);
            if (!$element instanceof \DOMElement) {
                $missing[] = $ref;
                continue;
            }

            $resolved[] = $ref;
            if ($this->isHtmlElementDescendantOrSame($element, $item)) {
                continue;
            }

            $properties = $this->mergeHtmlMicrodataProperties(
                $properties,
                $this->htmlMicrodataReferencedProperties($element, $seen)
            );
        }

        return [
            'resolved' => $resolved,
            'missing' => $missing,
            'properties' => $properties,
        ];
    }

    /**
     * @param array<string, true> $seen
     * @return array<string, list<mixed>>
     */
    private function htmlMicrodataReferencedProperties(\DOMElement $root, array $seen): array
    {
        $properties = [];
        foreach ($this->htmlElementAndDescendants($root) as $element) {
            if (!$element->hasAttribute('itemprop')) {
                continue;
            }
            if (!$element->isSameNode($root)) {
                $owner = $this->nearestHtmlMicrodataAncestorItem($element);
                if ($owner instanceof \DOMElement && $this->isHtmlElementDescendantOrSame($owner, $root)) {
                    continue;
                }
            }

            $propertyNames = $this->htmlMicrodataTermTokens($element->getAttribute('itemprop'));
            if ($propertyNames === []) {
                continue;
            }

            $value = $this->htmlMicrodataPropertyValue($element, $seen);
            if ($value === null) {
                continue;
            }

            foreach ($propertyNames as $name) {
                $properties[$name] ??= [];
                $properties[$name][] = $value;
            }
        }

        return $properties;
    }

    /**
     * @param array<string, list<mixed>> $properties
     * @param array<string, list<mixed>> $extra
     * @return array<string, list<mixed>>
     */
    private function mergeHtmlMicrodataProperties(array $properties, array $extra): array
    {
        foreach ($extra as $name => $values) {
            $properties[$name] ??= [];
            foreach ($values as $value) {
                $properties[$name][] = $value;
            }
        }

        return $properties;
    }

    private function nearestHtmlMicrodataAncestorItem(\DOMElement $element): ?\DOMElement
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if ($parent->hasAttribute('itemscope')) {
                return $parent;
            }

            $parent = $parent->parentNode;
        }

        return null;
    }

    /**
     * @param array<string, true> $seen
     * @return array<string, mixed>|string|null
     */
    private function htmlMicrodataPropertyValue(\DOMElement $element, array $seen): mixed
    {
        if ($element->hasAttribute('itemscope')) {
            return $this->readHtmlMicrodataItem($element, $seen);
        }

        return $this->htmlMicrodataScalarValue($element);
    }

    private function htmlMicrodataScalarValue(\DOMElement $element): ?string
    {
        $name = strtolower($element->localName);
        $value = match ($name) {
            'a', 'area', 'link' => $this->htmlMicrodataUrlValue($element->getAttribute('href')),
            'audio', 'embed', 'iframe', 'img', 'source', 'track', 'video' => $this->htmlMicrodataUrlValue($element->getAttribute('src')),
            'object' => $this->htmlMicrodataUrlValue($element->getAttribute('data')),
            'data', 'meter' => $this->cleanHtmlMetadataValue($element->getAttribute('value')),
            'meta' => $this->cleanHtmlMetadataValue($element->getAttribute('content')),
            'time' => $element->hasAttribute('datetime')
                ? $this->cleanHtmlMetadataValue($element->getAttribute('datetime'))
                : $this->cleanHtmlMetadataValue(Html5Dom::normalizedText($element)),
            default => $this->cleanHtmlMetadataValue(Html5Dom::normalizedText($element)),
        };

        if ($value === null || strlen($value) > 512) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, list<mixed>> $properties
     * @return array{propertyCount:int, valueCount:int, nestedItemCount:int, repeatedProperties:list<string>}
     */
    private function htmlMicrodataPropertySummary(array $properties): array
    {
        $summary = [
            'propertyCount' => 0,
            'valueCount' => 0,
            'nestedItemCount' => 0,
            'repeatedProperties' => [],
        ];

        foreach ($properties as $name => $values) {
            if (count($values) > 1) {
                $summary['repeatedProperties'][] = $name;
            }
            foreach ($values as $value) {
                ++$summary['propertyCount'];
                if (is_array($value)) {
                    ++$summary['nestedItemCount'];
                } else {
                    ++$summary['valueCount'];
                }
            }
        }

        return $summary;
    }

    private function htmlMicrodataElementKey(\DOMElement $element): string
    {
        $path = $element->getNodePath();
        if (is_string($path) && $path !== '') {
            return $path;
        }

        return 'node-' . spl_object_id($element);
    }

    /**
     * @return list<\DOMElement>
     */
    private function htmlElementAndDescendants(\DOMElement $root): array
    {
        $elements = [$root];
        foreach ($root->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private function htmlElementById(\DOMDocument $document, string $id): ?\DOMElement
    {
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->getAttribute('id') === $id) {
                return $element;
            }
        }

        return null;
    }

    private function isHtmlElementDescendantOrSame(\DOMElement $element, \DOMElement $ancestor): bool
    {
        $current = $element;
        while ($current instanceof \DOMElement) {
            if ($current->isSameNode($ancestor)) {
                return true;
            }

            $current = $current->parentNode;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function htmlMicrodataUrlTokens(string $value): array
    {
        $tokens = [];
        foreach ($this->htmlMetadataTokens($value) as $token) {
            if (!$this->isSafeHtmlMicrodataUrlToken($token) || in_array($token, $tokens, true)) {
                continue;
            }

            $tokens[] = $token;
        }

        return $tokens;
    }

    private function htmlMicrodataUrlValue(string $value): ?string
    {
        $value = $this->cleanHtmlMetadataValue($value);
        if ($value === null || !$this->isSafeHtmlMicrodataUrlToken($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function htmlMicrodataTermTokens(string $value): array
    {
        $tokens = [];
        foreach ($this->htmlMetadataTokens($value) as $token) {
            if (!$this->isSafeHtmlMicrodataTermToken($token) || in_array($token, $tokens, true)) {
                continue;
            }

            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private function htmlMetadataTokens(string $value): array
    {
        $cleaned = $this->cleanHtmlMetadataValue($value);
        if ($cleaned === null) {
            return [];
        }

        $tokens = preg_split('/[\x00-\x20]+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens)) {
            return [];
        }

        return array_values(array_map(static fn (string $token): string => trim($token), $tokens));
    }

    private function cleanHtmlMetadataValue(string $value): ?string
    {
        $cleaned = str_replace("\0", '', $value);
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        return $cleaned === '' ? null : $cleaned;
    }

    private function isSafeHtmlMicrodataUrlToken(string $token): bool
    {
        if ($token === '' || strlen($token) > 512 || preg_match('/[<>{}`]/', $token) === 1) {
            return false;
        }

        return !$this->hasUnsafeHtmlMicrodataScheme($token);
    }

    private function isSafeHtmlMicrodataTermToken(string $token): bool
    {
        if ($token === '' || preg_match('/[<>{}`]/', $token) === 1 || $this->hasUnsafeHtmlMicrodataScheme($token)) {
            return false;
        }

        if ($this->isSafeHtmlMicrodataUrlToken($token) && str_contains($token, '://')) {
            return true;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $token) === 1 && !str_contains($token, '://')) {
            return preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*:[A-Za-z0-9_.-]+$/', $token) === 1;
        }

        return preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/', $token) === 1;
    }

    private function hasUnsafeHtmlMicrodataScheme(string $value): bool
    {
        $compact = strtolower(preg_replace('/[\x00-\x20]+/', '', $value) ?? $value);

        return preg_match('/(?:javascript|vbscript|data):/', $compact) === 1;
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
        $body = XmlHtml5Dom::parseHtmlFragmentBody($html);
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
            if ($cursor > $index && trim($line) === '') {
                return null;
            }
            if ($cursor > $index && $this->htmlLineStartsImplicitParagraphClose($line)) {
                return [implode("\n", $content), $cursor - 1];
            }

            $content[] = $line;
            if (preg_match('/<\/p\s*>/i', $line) === 1) {
                return [implode("\n", $content), $cursor];
            }
        }

        return null;
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
        $body = XmlHtml5Dom::parseHtmlFragmentBody($html);
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
        $body = XmlHtml5Dom::parseHtmlFragmentBody($html);
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
        $body = XmlHtml5Dom::parseHtmlFragmentBody($html);
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
        $body = XmlHtml5Dom::parseHtmlFragmentBody($html);
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
        $body = XmlHtml5Dom::parseHtmlFragmentBody($html);
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
        $body = XmlHtml5Dom::parseHtmlFragmentBody($html);
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
                array_merge($this->htmlElementPandocAttrs($itemElement), [
                    'text' => trim(preg_replace('/\s+/', ' ', $itemElement->textContent) ?? $itemElement->textContent),
                    'loose' => $itemLoose,
                ]),
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
        $body = XmlHtml5Dom::parseHtmlFragmentBody($html);
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
        $body = preg_match('/^\s*(?:<!doctype\s+html\b|<html\b)/i', $html) === 1
            ? XmlHtml5Dom::parseHtmlDocumentBody($html)
            : XmlHtml5Dom::parseHtmlFragmentBody($html);
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

        $headRows = $thead instanceof \DOMElement ? $this->readHtmlTableRows($thead, true, $maxColumns, $table) : [];
        $bodyNodes = [];
        if ($bodySections !== []) {
            foreach ($bodySections as $tbody) {
                $rows = $this->readHtmlTableRows($tbody, false, $maxColumns, $table);
                [$bodyHeadRows, $bodyRows] = $this->splitHtmlTableBodyRows($rows);
                $bodyNodes[] = new AstNode(
                    'table_body',
                    array_merge($this->htmlElementPandocAttrs($tbody), $this->htmlTableBodyAttrs($bodyRows, $bodyHeadRows)),
                    $bodyRows
                );
            }
        } else {
            $bodyRows = $this->readHtmlTableRows($table, false, $maxColumns, $table);
            if ($headRows === [] && $this->firstHtmlTableRowIsHeader($bodyRows)) {
                $headRow = array_shift($bodyRows);
                if ($headRow instanceof AstNode) {
                    $headRows[] = $this->markHtmlTableRowAsHeader($headRow);
                }
            }
            [$bodyHeadRows, $bodyRows] = $this->splitHtmlTableBodyRows($bodyRows);
            $bodyNodes[] = new AstNode('table_body', $this->htmlTableBodyAttrs($bodyRows, $bodyHeadRows), $bodyRows);
        }
        $footRows = $tfoot instanceof \DOMElement ? $this->readHtmlTableRows($tfoot, false, $maxColumns, $table) : [];

        $captionInlines = $caption instanceof \DOMElement ? $this->parseHtmlInlineChildren($caption) : [];
        $columnMetadata = $this->readHtmlTableColumnMetadata($table, $maxColumns);
        if ($columnMetadata['verticalAlignments'] !== null) {
            $headRows = $this->applyHtmlColumnVerticalAlignmentsToRows($headRows, $columnMetadata['verticalAlignments']);
            $bodyNodes = $this->applyHtmlColumnVerticalAlignmentsToBodies($bodyNodes, $columnMetadata['verticalAlignments']);
            $footRows = $this->applyHtmlColumnVerticalAlignmentsToRows($footRows, $columnMetadata['verticalAlignments']);
        }
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
        if ($caption instanceof \DOMElement) {
            $captionSource = $this->htmlTableCaptionSource($table, $caption);
            if ($captionSource !== []) {
                $attrs['captionSource'] = $captionSource;
            }
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
     * @return array<string, mixed>
     */
    private function htmlTableCaptionSource(\DOMElement $table, \DOMElement $caption): array
    {
        $record = [
            'element' => 'caption',
        ];

        $childIndex = $this->htmlTableCaptionChildIndex($table, $caption);
        if ($childIndex !== null) {
            $record['childIndex'] = $childIndex;
            $record['position'] = $this->htmlTableCaptionPosition($table, $childIndex);
        }

        $captionSide = $this->htmlTableCaptionSideRecord($caption);
        if ($captionSide !== []) {
            $record['captionSide'] = $captionSide['side'];
            $record['captionSideSource'] = $captionSide['source'];
        }

        $sourceAttributes = $this->htmlElementPandocAttrs($caption);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }

        return $record;
    }

    private function htmlTableCaptionChildIndex(\DOMElement $table, \DOMElement $caption): ?int
    {
        $index = 0;
        foreach ($table->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->isSameNode($caption)) {
                return $index;
            }

            $index++;
        }

        return null;
    }

    private function htmlTableCaptionPosition(\DOMElement $table, int $captionIndex): string
    {
        $firstContentIndex = null;
        $lastContentIndex = null;
        $index = 0;
        foreach ($table->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->localName);
            if (in_array($name, ['thead', 'tbody', 'tfoot', 'tr'], true)) {
                $firstContentIndex ??= $index;
                $lastContentIndex = $index;
            }

            $index++;
        }

        if ($firstContentIndex === null || $captionIndex < $firstContentIndex) {
            return 'before-table-sections';
        }

        if ($lastContentIndex !== null && $captionIndex > $lastContentIndex) {
            return 'after-table-sections';
        }

        return 'between-table-sections';
    }

    /**
     * @return array{side:string, source:string}|array{}
     */
    private function htmlTableCaptionSideRecord(\DOMElement $caption): array
    {
        $style = $caption->getAttribute('style');
        if ($style !== '' && preg_match('/(?:^|;)\s*caption-side\s*:\s*([a-z-]+)/i', $style, $match) === 1) {
            return [
                'side' => strtolower($match[1]),
                'source' => 'style',
            ];
        }

        $align = strtolower(trim($caption->getAttribute('align')));
        if (in_array($align, ['top', 'bottom', 'left', 'right'], true)) {
            return [
                'side' => $align,
                'source' => 'align',
            ];
        }

        return [];
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
                $colspan = $cell->attr('colspan', 1);
                $visualSpan = is_int($colspan) || (is_string($colspan) && ctype_digit($colspan))
                    ? max(1, (int) $colspan)
                    : 1;
                $count += $visualSpan;
            }

            $minimum = $minimum === null ? $count : min($minimum, $count);
        }

        return $minimum ?? 0;
    }

    /**
     * @return array{alignments:?list<string>, widths:?list<?float>, verticalAlignments:?list<string>, sources:?list<array<string, mixed>>, diagnostics:list<array<string, mixed>>}
     */
    private function readHtmlTableColumnMetadata(\DOMElement $table, int $maxColumns): array
    {
        $colgroups = $this->childElements($table, 'colgroup');
        if ($colgroups === []) {
            return ['alignments' => null, 'widths' => null, 'verticalAlignments' => null, 'sources' => null, 'diagnostics' => []];
        }

        $widths = [];
        $alignments = [];
        $verticalAlignments = [];
        $sources = [];
        $diagnostics = [];
        $hasAlignment = false;
        $hasVerticalAlignment = false;
        $hasWidth = false;
        $hasCompleteWidths = true;
        foreach ($colgroups as $colgroupIndex => $colgroup) {
            $cols = $this->childElements($colgroup, 'col');
            if ($cols === []) {
                $span = $this->positiveHtmlSpan($colgroup->getAttribute('span'));
                $spanDiagnostic = $this->htmlColumnSpanNormalizationDiagnostic(
                    $colgroup,
                    'colgroup',
                    $colgroupIndex,
                    null,
                    count($alignments),
                    $span
                );
                if ($spanDiagnostic !== []) {
                    $diagnostics[] = $spanDiagnostic;
                }
                $alignment = $this->normalizeHtmlColumnAlignment($colgroup);
                $verticalAlignment = $this->normalizeHtmlColumnVerticalAlignment($colgroup);
                $width = $this->htmlColumnWidthPercent($colgroup);
                for ($index = 0; $index < $span; $index++) {
                    $column = count($alignments);
                    $alignments[] = $alignment;
                    $verticalAlignments[] = $verticalAlignment;
                    $widths[] = $width;
                    $sources[] = $this->htmlColumnSourceRecord($colgroup, $colgroupIndex, null, null, $column, $span, $index, $alignment, $verticalAlignment, $width);
                    $hasAlignment = $hasAlignment || $alignment !== 'default';
                    $hasVerticalAlignment = $hasVerticalAlignment || $verticalAlignment !== 'default';
                    $hasWidth = $hasWidth || $width !== null;
                    $hasCompleteWidths = $hasCompleteWidths && $width !== null;
                }
                continue;
            }

            foreach ($cols as $colIndex => $col) {
                $span = $this->positiveHtmlSpan($col->getAttribute('span'));
                $spanDiagnostic = $this->htmlColumnSpanNormalizationDiagnostic(
                    $col,
                    'col',
                    $colgroupIndex,
                    $colIndex,
                    count($alignments),
                    $span
                );
                if ($spanDiagnostic !== []) {
                    $diagnostics[] = $spanDiagnostic;
                }
                $alignment = $this->normalizeHtmlColumnAlignment($col, $colgroup);
                $verticalAlignment = $this->normalizeHtmlColumnVerticalAlignment($col, $colgroup);
                $width = $this->htmlColumnWidthPercent($col);
                for ($index = 0; $index < $span; $index++) {
                    $column = count($alignments);
                    $alignments[] = $alignment;
                    $verticalAlignments[] = $verticalAlignment;
                    $widths[] = $width;
                    $sources[] = $this->htmlColumnSourceRecord($colgroup, $colgroupIndex, $col, $colIndex, $column, $span, $index, $alignment, $verticalAlignment, $width);
                    $hasAlignment = $hasAlignment || $alignment !== 'default';
                    $hasVerticalAlignment = $hasVerticalAlignment || $verticalAlignment !== 'default';
                    $hasWidth = $hasWidth || $width !== null;
                    $hasCompleteWidths = $hasCompleteWidths && $width !== null;
                }
            }
        }

        if ($alignments === []) {
            return ['alignments' => null, 'widths' => null, 'verticalAlignments' => null, 'sources' => null, 'diagnostics' => []];
        }

        $sourceColumnCount = count($alignments);
        $hasColumnCountMismatch = false;
        if ($maxColumns > 0 && $sourceColumnCount !== $maxColumns) {
            $diagnostics[] = $this->htmlColumnCountMismatchDiagnostic($sourceColumnCount, $maxColumns);
            $hasColumnCountMismatch = true;
        }

        $targetColumnCount = $maxColumns > 0 ? max($maxColumns, $sourceColumnCount) : $sourceColumnCount;
        while (count($alignments) < $targetColumnCount) {
            $alignments[] = 'default';
            $verticalAlignments[] = 'default';
            $widths[] = null;
        }

        return [
            'alignments' => $hasAlignment ? $alignments : null,
            'widths' => $hasWidth && ($hasCompleteWidths || $hasColumnCountMismatch) ? array_values($widths) : null,
            'verticalAlignments' => $hasVerticalAlignment ? $verticalAlignments : null,
            'sources' => $sources,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlColumnSpanNormalizationDiagnostic(
        \DOMElement $element,
        string $sourceElement,
        int $colgroupIndex,
        ?int $colIndex,
        int $column,
        int $normalizedSpan
    ): array {
        if (!$element->hasAttribute('span')) {
            return [];
        }

        $rawSpan = trim($element->getAttribute('span'));
        if (preg_match('/^\d+$/', $rawSpan) === 1 && (int) $rawSpan >= 1) {
            return [];
        }

        $diagnostic = [
            'code' => 'html-column-span-normalized',
            'source' => 'html-colgroup',
            'sourceElement' => $sourceElement,
            'attribute' => 'span',
            'rawType' => 'string',
            'rawValue' => $rawSpan,
            'normalizedSpan' => $normalizedSpan,
            'minimumValue' => 1,
            'column' => $column,
            'colgroupIndex' => $colgroupIndex,
        ];
        if ($colIndex !== null) {
            $diagnostic['colIndex'] = $colIndex;
        }

        return $diagnostic;
    }

    /**
     * @param list<AstNode> $bodies
     * @param list<string> $verticalAlignments
     * @return list<AstNode>
     */
    private function applyHtmlColumnVerticalAlignmentsToBodies(array $bodies, array $verticalAlignments): array
    {
        $updated = [];
        foreach ($bodies as $body) {
            $headRows = [];
            $rawHeadRows = $body->attr('headRows', []);
            if (is_array($rawHeadRows)) {
                foreach ($rawHeadRows as $row) {
                    if ($row instanceof AstNode && $row->type === 'table_row') {
                        $headRows[] = $row;
                    }
                }
            }

            $rows = $this->applyHtmlColumnVerticalAlignmentsToRows(
                [...$headRows, ...$body->children],
                $verticalAlignments
            );
            $attrs = $body->attrs;
            if ($headRows !== []) {
                $attrs['headRows'] = array_slice($rows, 0, count($headRows));
            }

            $updated[] = new AstNode(
                $body->type,
                $attrs,
                array_slice($rows, count($headRows))
            );
        }

        return $updated;
    }

    /**
     * @param list<AstNode> $rows
     * @param list<string> $verticalAlignments
     * @return list<AstNode>
     */
    private function applyHtmlColumnVerticalAlignmentsToRows(array $rows, array $verticalAlignments): array
    {
        if ($rows === [] || $verticalAlignments === []) {
            return $rows;
        }

        $columnCount = max(count($verticalAlignments), TableGeometry::columnCountForRows($rows));
        $layoutRows = TableGeometry::layoutRows($rows, $columnCount);
        $updatedRows = [];
        foreach ($layoutRows as $rowIndex => $layoutRow) {
            $updates = [];
            foreach ($layoutRow['cells'] as $layoutCell) {
                $verticalAlignment = (string) ($verticalAlignments[(int) $layoutCell['column']] ?? 'default');
                if ($verticalAlignment === 'default') {
                    continue;
                }

                $cell = $layoutCell['node'];
                if ((string) $cell->attr('valign', '') !== '') {
                    continue;
                }

                $updates[(int) $layoutCell['sourceCell']] = new AstNode(
                    $cell->type,
                    array_replace($cell->attrs, ['valign' => $verticalAlignment]),
                    $cell->children
                );
            }

            if ($updates === []) {
                $updatedRows[] = $rows[$rowIndex] ?? $layoutRow['row'];
                continue;
            }

            $row = $rows[$rowIndex] ?? $layoutRow['row'];
            $children = [];
            $sourceCell = 0;
            foreach ($row->children as $child) {
                if ($child->type !== 'table_cell') {
                    $children[] = $child;
                    continue;
                }

                $children[] = $updates[$sourceCell] ?? $child;
                $sourceCell++;
            }

            $updatedRows[] = new AstNode($row->type, $row->attrs, $children);
        }

        return $updatedRows;
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
        string $verticalAlignment,
        ?float $width
    ): array {
        $record = [
            'kind' => $col instanceof \DOMElement ? 'col' : 'colgroup',
            'column' => $column,
            'colgroupIndex' => $colgroupIndex,
            'sourceSpan' => $sourceSpan,
            'spanOffset' => $spanOffset,
            'alignment' => $alignment,
            'verticalAlignment' => $verticalAlignment,
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
    private function readHtmlTableRows(\DOMElement $section, bool $header, int &$maxColumns, ?\DOMElement $table = null): array
    {
        $rows = [];
        foreach ($this->childElements($section, 'tr') as $rowElement) {
            $cells = [];
            $rowColumns = 0;
            $alignmentFallbacks = [$rowElement];
            if ($section !== $rowElement) {
                $alignmentFallbacks[] = $section;
            }
            if ($table instanceof \DOMElement && $table !== $section) {
                $alignmentFallbacks[] = $table;
            }
            foreach ($rowElement->childNodes as $child) {
                if (!$child instanceof \DOMElement || !in_array(strtolower($child->localName), ['td', 'th'], true)) {
                    continue;
                }

                $cell = $this->buildHtmlTableCell($child, $header || strtolower($child->localName) === 'th', ...$alignmentFallbacks);
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

    private function buildHtmlTableCell(\DOMElement $cell, bool $header, \DOMElement ...$alignmentFallbacks): AstNode
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

        $alignment = $this->normalizeHtmlTableAlignment($cell, ...$alignmentFallbacks);
        if ($alignment !== 'default') {
            $attrs['align'] = $alignment;
        }

        $verticalAlignment = $this->normalizeHtmlTableVerticalAlignment($cell, ...$alignmentFallbacks);
        if ($verticalAlignment !== 'default') {
            $attrs['valign'] = $verticalAlignment;
        }

        $attrs = $this->htmlTableCellCharAlignmentAttrs($cell, $attrs);

        return new AstNode('table_cell', $attrs, $children);
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function htmlTableCellCharAlignmentAttrs(\DOMElement $cell, array $attrs): array
    {
        if (strtolower(trim($cell->getAttribute('align'))) !== 'char') {
            return $attrs;
        }

        foreach (['attributes', 'htmlAttributes'] as $key) {
            $values = isset($attrs[$key]) && is_array($attrs[$key]) ? $attrs[$key] : [];
            $values['align'] = 'char';
            $attrs[$key] = $values;
        }

        return $attrs;
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

    private function normalizeHtmlTableAlignment(\DOMElement $cell, \DOMElement ...$fallbacks): string
    {
        $alignment = $this->normalizeHtmlElementAlignment($cell);
        if ($alignment !== 'default') {
            return $alignment;
        }

        foreach ($fallbacks as $fallback) {
            $alignment = $this->normalizeHtmlElementAlignment($fallback);
            if ($alignment !== 'default') {
                return $alignment;
            }
        }

        return 'default';
    }

    private function normalizeHtmlColumnAlignment(\DOMElement $element, ?\DOMElement $fallback = null): string
    {
        $alignment = $this->normalizeHtmlElementAlignment($element);
        if ($alignment !== 'default') {
            return $alignment;
        }

        return $fallback instanceof \DOMElement ? $this->normalizeHtmlElementAlignment($fallback) : 'default';
    }

    private function normalizeHtmlColumnVerticalAlignment(\DOMElement $element, ?\DOMElement $fallback = null): string
    {
        $alignment = $this->normalizeHtmlElementVerticalAlignment($element);
        if ($alignment !== 'default') {
            return $alignment;
        }

        return $fallback instanceof \DOMElement ? $this->normalizeHtmlElementVerticalAlignment($fallback) : 'default';
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

    private function normalizeHtmlTableVerticalAlignment(\DOMElement $cell, \DOMElement ...$fallbacks): string
    {
        $alignment = $this->normalizeHtmlElementVerticalAlignment($cell);
        if ($alignment !== 'default') {
            return $alignment;
        }

        foreach ($fallbacks as $fallback) {
            $alignment = $this->normalizeHtmlElementVerticalAlignment($fallback);
            if ($alignment !== 'default') {
                return $alignment;
            }
        }

        return 'default';
    }

    private function normalizeHtmlElementVerticalAlignment(\DOMElement $element): string
    {
        $align = strtolower(trim($element->getAttribute('valign')));
        if (in_array($align, ['baseline', 'top', 'middle', 'bottom'], true)) {
            return $align;
        }
        if ($align === 'center') {
            return 'middle';
        }

        $style = strtolower($element->getAttribute('style'));
        if (preg_match('/vertical-align\s*:\s*(baseline|top|middle|bottom)\b/', $style, $m) === 1) {
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
        if (in_array($name, ['abbr', 'address', 'animate', 'animatemotion', 'animatetransform', 'annotation', 'annotation-xml', 'area', 'audio', 'base', 'bdi', 'bdo', 'button', 'canvas', 'circle', 'cite', 'clippath', 'data', 'datalist', 'defs', 'desc', 'details', 'dfn', 'dialog', 'ellipse', 'embed', 'feblend', 'fecomposite', 'fedropshadow', 'feflood', 'fegaussianblur', 'feoffset', 'fieldset', 'filter', 'foreignobject', 'form', 'g', 'hgroup', 'iframe', 'image', 'input', 'label', 'legend', 'line', 'lineargradient', 'link', 'maligngroup', 'malignmark', 'map', 'marker', 'mark', 'mask', 'math', 'maction', 'metadata', 'menclose', 'merror', 'menu', 'meta', 'meter', 'mfenced', 'mfrac', 'mglyph', 'mi', 'mlabeledtr', 'mlongdiv', 'mmultiscripts', 'mn', 'mo', 'mover', 'mpadded', 'mpath', 'mphantom', 'mprescripts', 'mroot', 'mrow', 'ms', 'mscarries', 'mscarry', 'msgroup', 'msline', 'mspace', 'msqrt', 'msrow', 'mstack', 'mstyle', 'msub', 'msubsup', 'msup', 'mtable', 'mtd', 'mtext', 'mtr', 'munder', 'munderover', 'none', 'object', 'optgroup', 'option', 'output', 'param', 'path', 'pattern', 'picture', 'polygon', 'polyline', 'progress', 'radialgradient', 'rect', 'rp', 'rt', 'ruby', 'search', 'select', 'semantics', 'set', 'slot', 'small', 'source', 'stop', 'summary', 'svg', 'switch', 'symbol', 'template', 'text', 'textarea', 'textpath', 'time', 'track', 'tspan', 'use', 'var', 'video', 'view', 'wbr'], true)) {
            return [new AstNode('raw_html_inline', ['html' => XmlHtml5Dom::serializeHtmlFragment($node)])];
        }

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

        $valign = $this->normalizeDocBookVerticalAlignment($entry->getAttribute('valign'));
        if ($valign !== 'default') {
            $attrs['valign'] = $valign;
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

    private function normalizeDocBookVerticalAlignment(string $alignment): string
    {
        return match (strtolower(trim($alignment))) {
            'baseline' => 'baseline',
            'top' => 'top',
            'middle', 'center' => 'middle',
            'bottom' => 'bottom',
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
        $balanced = $this->tryReadBalancedRawTexMacroDefinition($line);
        if ($balanced !== null) {
            return $balanced;
        }

        if (
            preg_match(
                '/^ {0,3}\\\\(DeclareMathOperator)(\*)?\{\\\\([A-Za-z]+)\}\{((?:\\\\.|[^{}])*)\}[ \t]*$/',
                $line,
                $m
            ) === 1
        ) {
            return [
                'command' => $m[1],
                'name' => $m[3],
                'arity' => 0,
                'template' => '\\operatorname' . (($m[2] ?? '') === '*' ? '*' : '') . '{' . $m[4] . '}',
            ];
        }

        if (
            preg_match(
                '/^ {0,3}\\\\(DeclarePairedDelimiterXPP)(?:\{\\\\([A-Za-z]+)\}|\\\\([A-Za-z]+))(?:\[(\d+)])?\{((?:\\\\.|[^{}])*)\}\{((?:\\\\.|[^{}])*)\}\{((?:\\\\.|[^{}])*)\}\{((?:\\\\.|[^{}])*)\}\{((?:\\\\.|[^{}])*)\}[ \t]*$/',
                $line,
                $m
            ) === 1
        ) {
            $name = ($m[2] ?? '') !== '' ? $m[2] : $m[3];
            $prefix = trim($m[5]);
            $suffix = trim($m[8]);
            $template = '';
            if ($prefix !== '') {
                $template .= $prefix . ' ';
            }
            $template .= '\\left' . trim($m[6]) . ' ' . trim($m[9]) . ' \\right' . trim($m[7]);
            if ($suffix !== '') {
                $template .= ' ' . $suffix;
            }

            return [
                'command' => $m[1],
                'name' => $name,
                'arity' => isset($m[4]) && $m[4] !== '' ? (int) $m[4] : $this->inferRawTexMacroArity($m[9]),
                'template' => $template,
            ];
        }

        if (
            preg_match(
                '/^ {0,3}\\\\(DeclarePairedDelimiterX)(?:\{\\\\([A-Za-z]+)\}|\\\\([A-Za-z]+))(?:\[(\d+)])?\{((?:\\\\.|[^{}])*)\}\{((?:\\\\.|[^{}])*)\}\{((?:\\\\.|[^{}])*)\}[ \t]*$/',
                $line,
                $m
            ) === 1
        ) {
            $name = ($m[2] ?? '') !== '' ? $m[2] : $m[3];

            return [
                'command' => $m[1],
                'name' => $name,
                'arity' => isset($m[4]) && $m[4] !== '' ? (int) $m[4] : $this->inferRawTexMacroArity($m[7]),
                'template' => '\\left' . trim($m[5]) . ' ' . trim($m[7]) . ' \\right' . trim($m[6]),
            ];
        }

        if (
            preg_match(
                '/^ {0,3}\\\\(DeclarePairedDelimiter)(?:\{\\\\([A-Za-z]+)\}|\\\\([A-Za-z]+))\{((?:\\\\.|[^{}])*)\}\{((?:\\\\.|[^{}])*)\}[ \t]*$/',
                $line,
                $m
            ) === 1
        ) {
            $name = ($m[2] ?? '') !== '' ? $m[2] : $m[3];

            return [
                'command' => $m[1],
                'name' => $name,
                'arity' => 1,
                'template' => '\\left' . trim($m[4]) . ' #1 \\right' . trim($m[5]),
            ];
        }

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
     * @return array{command:string, name:string, arity:int, template:string}|null
     */
    private function tryReadBalancedRawTexMacroDefinition(string $line): ?array
    {
        if (preg_match('/^ {0,3}\\\\/', $line) !== 1) {
            return null;
        }

        $source = trim($line);
        $offset = 1;
        $command = $this->readRawTexCommandName($source, $offset);
        if ($command === '') {
            return null;
        }

        if ($command === 'DeclareMathOperator') {
            $starred = false;
            if (($source[$offset] ?? '') === '*') {
                $starred = true;
                $offset++;
            }

            $name = $this->readRawTexMacroNameReference($source, $offset);
            if ($name === null) {
                return null;
            }
            $offset = $name['next'];

            $body = $this->readTexBraceArgument($source, $offset);
            if ($body === null) {
                return null;
            }
            $offset = $body['next'];

            return $this->finishRawTexMacroDefinition($source, $offset, [
                'command' => $command,
                'name' => $name['name'],
                'arity' => 0,
                'template' => '\\operatorname' . ($starred ? '*' : '') . '{' . $body['value'] . '}',
            ]);
        }

        if ($command === 'DeclarePairedDelimiterXPP') {
            $name = $this->readRawTexMacroNameReference($source, $offset);
            if ($name === null) {
                return null;
            }
            $offset = $name['next'];

            $arity = null;
            $parsedArity = $this->readRawTexBracketArgument($source, $offset);
            if ($parsedArity !== null) {
                if (ctype_digit(trim($parsedArity['value'])) === false) {
                    return null;
                }
                $arity = (int) trim($parsedArity['value']);
                $offset = $parsedArity['next'];
            }

            $prefix = $this->readTexBraceArgument($source, $offset);
            if ($prefix === null) {
                return null;
            }
            $left = $this->readTexBraceArgument($source, $prefix['next']);
            if ($left === null) {
                return null;
            }
            $right = $this->readTexBraceArgument($source, $left['next']);
            if ($right === null) {
                return null;
            }
            $suffix = $this->readTexBraceArgument($source, $right['next']);
            if ($suffix === null) {
                return null;
            }
            $templateBody = $this->readTexBraceArgument($source, $suffix['next']);
            if ($templateBody === null) {
                return null;
            }

            $template = '';
            $prefixValue = trim($prefix['value']);
            $suffixValue = trim($suffix['value']);
            if ($prefixValue !== '') {
                $template .= $prefixValue . ' ';
            }
            $template .= '\\left' . trim($left['value']) . ' ' . trim($templateBody['value']) . ' \\right' . trim($right['value']);
            if ($suffixValue !== '') {
                $template .= ' ' . $suffixValue;
            }

            return $this->finishRawTexMacroDefinition($source, $templateBody['next'], [
                'command' => $command,
                'name' => $name['name'],
                'arity' => $arity ?? $this->inferRawTexMacroArity($templateBody['value']),
                'template' => $template,
            ]);
        }

        if ($command === 'DeclarePairedDelimiterX') {
            $name = $this->readRawTexMacroNameReference($source, $offset);
            if ($name === null) {
                return null;
            }
            $offset = $name['next'];

            $arity = null;
            $parsedArity = $this->readRawTexBracketArgument($source, $offset);
            if ($parsedArity !== null) {
                if (ctype_digit(trim($parsedArity['value'])) === false) {
                    return null;
                }
                $arity = (int) trim($parsedArity['value']);
                $offset = $parsedArity['next'];
            }

            $left = $this->readTexBraceArgument($source, $offset);
            if ($left === null) {
                return null;
            }
            $right = $this->readTexBraceArgument($source, $left['next']);
            if ($right === null) {
                return null;
            }
            $templateBody = $this->readTexBraceArgument($source, $right['next']);
            if ($templateBody === null) {
                return null;
            }

            return $this->finishRawTexMacroDefinition($source, $templateBody['next'], [
                'command' => $command,
                'name' => $name['name'],
                'arity' => $arity ?? $this->inferRawTexMacroArity($templateBody['value']),
                'template' => '\\left' . trim($left['value']) . ' ' . trim($templateBody['value']) . ' \\right' . trim($right['value']),
            ]);
        }

        if ($command === 'DeclarePairedDelimiter') {
            $name = $this->readRawTexMacroNameReference($source, $offset);
            if ($name === null) {
                return null;
            }
            $offset = $name['next'];

            $left = $this->readTexBraceArgument($source, $offset);
            if ($left === null) {
                return null;
            }
            $right = $this->readTexBraceArgument($source, $left['next']);
            if ($right === null) {
                return null;
            }

            return $this->finishRawTexMacroDefinition($source, $right['next'], [
                'command' => $command,
                'name' => $name['name'],
                'arity' => 1,
                'template' => '\\left' . trim($left['value']) . ' #1 \\right' . trim($right['value']),
            ]);
        }

        if (!in_array($command, ['newcommand', 'renewcommand', 'providecommand'], true)) {
            return null;
        }

        $name = $this->readRawTexMacroNameReference($source, $offset);
        if ($name === null) {
            return null;
        }
        $offset = $name['next'];

        $arity = 0;
        $firstOptional = $this->readRawTexBracketArgument($source, $offset);
        if ($firstOptional !== null) {
            $firstValue = trim($firstOptional['value']);
            $offset = $firstOptional['next'];
            if ($firstValue !== '' && ctype_digit($firstValue)) {
                $arity = (int) $firstValue;
                $defaultOptional = $this->readRawTexBracketArgument($source, $offset);
                if ($defaultOptional !== null) {
                    $offset = $defaultOptional['next'];
                }
            }
        }

        $template = $this->readTexBraceArgument($source, $offset);
        if ($template === null) {
            return null;
        }

        return $this->finishRawTexMacroDefinition($source, $template['next'], [
            'command' => $command,
            'name' => $name['name'],
            'arity' => $arity,
            'template' => $template['value'],
        ]);
    }

    private function readRawTexCommandName(string $source, int &$offset): string
    {
        $length = strlen($source);
        $start = $offset;
        while ($offset < $length && ctype_alpha($source[$offset])) {
            $offset++;
        }

        return substr($source, $start, $offset - $start);
    }

    /**
     * @return array{name:string, next:int}|null
     */
    private function readRawTexMacroNameReference(string $source, int $offset): ?array
    {
        $offset = $this->skipRawTexSpaces($source, $offset);
        if (($source[$offset] ?? '') === '{' && ($source[$offset + 1] ?? '') === '\\') {
            $cursor = $offset + 2;
            $name = $this->readRawTexCommandName($source, $cursor);
            if ($name === '' || ($source[$cursor] ?? '') !== '}') {
                return null;
            }

            return [
                'name' => $name,
                'next' => $cursor + 1,
            ];
        }

        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $cursor = $offset + 1;
        $name = $this->readRawTexCommandName($source, $cursor);
        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'next' => $cursor,
        ];
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readRawTexBracketArgument(string $source, int $offset): ?array
    {
        $offset = $this->skipRawTexSpaces($source, $offset);
        if (($source[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $length = strlen($source);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($source[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($source[$cursor] === '[') {
                $depth++;
                continue;
            }

            if ($source[$cursor] !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return [
                    'value' => substr($source, $offset + 1, $cursor - $offset - 1),
                    'next' => $cursor + 1,
                ];
            }
        }

        return null;
    }

    /**
     * @param array{command:string, name:string, arity:int, template:string} $definition
     * @return array{command:string, name:string, arity:int, template:string}|null
     */
    private function finishRawTexMacroDefinition(string $source, int $offset, array $definition): ?array
    {
        $offset = $this->skipRawTexSpaces($source, $offset);
        if ($offset !== strlen($source)) {
            return null;
        }

        return $definition;
    }

    private function skipRawTexSpaces(string $source, int $offset): int
    {
        $length = strlen($source);
        while ($offset < $length && ($source[$offset] === ' ' || $source[$offset] === "\t")) {
            $offset++;
        }

        return $offset;
    }

    private function inferRawTexMacroArity(string $template): int
    {
        if (preg_match_all('/#([1-9])/', $template, $m) !== false && $m[1] !== []) {
            return max(array_map('intval', $m[1]));
        }

        return 0;
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
     * @param array<string, mixed>|string $caption
     * @param list<float>|null $widths
     */
    private function buildSimpleTable(?array $headerCells, array $bodyRows, array $alignments, array|string $caption, ?array $widths = null): AstNode
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

        $attrs = array_replace(
            ['alignments' => $alignments],
            $this->tableCaptionAttrs($caption)
        );
        if ($widths !== null) {
            $attrs['widths'] = $widths;
        }

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $children));
    }

    /**
     * @param AstNode|null $headerRow
     * @param list<AstNode> $bodyRows
     * @param list<string> $alignments
     * @param array<string, mixed>|string $caption
     * @param list<float>|null $widths
     */
    private function buildGridTable(?AstNode $headerRow, array $bodyRows, array $alignments, array|string $caption, ?array $widths): AstNode
    {
        return $this->buildGridTableRows($headerRow instanceof AstNode ? [$headerRow] : [], $bodyRows, $alignments, $caption, $widths);
    }

    /**
     * @param list<AstNode> $headerRows
     * @param list<AstNode> $bodyRows
     * @param list<string> $alignments
     * @param array<string, mixed>|string $caption
     * @param list<float>|null $widths
     */
    private function buildGridTableRows(array $headerRows, array $bodyRows, array $alignments, array|string $caption, ?array $widths): AstNode
    {
        $children = [];
        if ($headerRows !== []) {
            $children[] = new AstNode('table_head', [], $headerRows);
        } else {
            $children[] = new AstNode('table_head');
        }

        $children[] = new AstNode('table_body', [], $bodyRows);

        $attrs = array_replace(
            ['alignments' => $alignments],
            $this->tableCaptionAttrs($caption)
        );
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
     * @return array{0:array<string, mixed>, 1:int}
     */
    private function readTableCaption(array $lines, int $cursor): array
    {
        $count = count($lines);
        $captionCursor = $cursor;
        while ($captionCursor < $count && trim($lines[$captionCursor]) === '') {
            $captionCursor++;
        }

        $captionLine = $captionCursor < $count ? $this->matchTableCaptionLine($lines[$captionCursor]) : null;
        if ($captionLine !== null) {
            $caption = [trim($captionLine['text'])];
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

            return [$this->buildTableCaptionRecord(implode("\n", $caption), 'after-table', $captionLine['marker']), $next];
        }

        return [$this->emptyTableCaptionRecord(), $cursor];
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadTableWithLeadingCaption(array $lines, int &$index): ?AstNode
    {
        $caption = $this->readLeadingTableCaption($lines, $index);
        if ($caption === null) {
            return null;
        }

        $tableIndex = $caption['tableStart'];
        $table = $this->tryReadGridTable($lines, $tableIndex);
        if ($table === null) {
            $tableIndex = $caption['tableStart'];
            $table = $this->tryReadSimpleTable($lines, $tableIndex);
        }
        if ($table === null) {
            $tableIndex = $caption['tableStart'];
            $table = $this->tryReadPipeTable($lines, $tableIndex);
        }
        if ($table === null) {
            return null;
        }

        $index = $tableIndex;

        return $this->tableWithCaption($table, $caption['caption']);
    }

    /**
     * @param list<string> $lines
     * @return array{caption:array<string, mixed>, tableStart:int}|null
     */
    private function readLeadingTableCaption(array $lines, int $index): ?array
    {
        $captionLine = $this->matchTableCaptionLine($lines[$index] ?? '');
        if ($captionLine === null) {
            return null;
        }

        $caption = [trim($captionLine['text'])];
        $cursor = $index + 1;
        $count = count($lines);
        while (
            $cursor < $count
            && trim($lines[$cursor]) !== ''
            && $this->countIndentColumns($lines[$cursor]) >= 2
            && $this->parseSimpleTableDelimiter($lines[$cursor]) === null
            && !$this->isSimpleTableBoundary($lines[$cursor])
        ) {
            $caption[] = trim($lines[$cursor]);
            $cursor++;
        }

        while ($cursor < $count && trim($lines[$cursor]) === '') {
            $cursor++;
        }

        if ($cursor >= $count) {
            return null;
        }

        return [
            'caption' => $this->buildTableCaptionRecord(implode("\n", $caption), 'before-table', $captionLine['marker']),
            'tableStart' => $cursor,
        ];
    }

    /**
     * @return array{marker:string, text:string}|null
     */
    private function matchTableCaptionLine(string $line): ?array
    {
        if (preg_match('/^ {0,3}((?:Table|Caption):|:)\s*(.*)$/iu', $line, $m) !== 1) {
            return null;
        }

        return [
            'marker' => $m[1],
            'text' => $m[2],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTableCaptionRecord(): array
    {
        return ['caption' => ''];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTableCaptionRecord(string $source, string $position, string $marker): array
    {
        [$caption, $tableAttrs] = $this->splitTableCaptionAttributes(trim($source));
        [$shortCaption, $longCaption] = $this->splitTableShortCaption($caption);

        $record = [
            'caption' => $longCaption,
            'captionSource' => [
                'element' => 'markdown-table-caption',
                'position' => $position,
                'marker' => $marker,
                'captionSide' => $position === 'before-table' ? 'top' : 'bottom',
                'captionSideSource' => 'markdown-table-caption-position',
            ],
        ];
        if ($longCaption !== '') {
            $record['captionInlines'] = $this->parseInlines($longCaption);
        }
        if ($shortCaption !== null && $shortCaption !== '') {
            $record['shortCaption'] = $this->plainTextFromInlines($this->parseInlines($shortCaption));
            $record['shortCaptionInlines'] = $this->parseInlines($shortCaption);
        }

        return array_replace($record, $tableAttrs);
    }

    /**
     * @return array{0:string, 1:array<string, mixed>}
     */
    private function splitTableCaptionAttributes(string $caption): array
    {
        if (preg_match('/^([\s\S]*?)[ \t]*\{([^{}\r\n]+)\}[ \t]*$/u', $caption, $match) !== 1) {
            return [$caption, []];
        }

        [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec($match[2]);
        if ($id === null && $classes === [] && $attributes === []) {
            return [$caption, []];
        }

        return [
            rtrim($match[1]),
            $this->markdownAttributeAstAttrs($id, $classes, $attributes),
        ];
    }

    /**
     * @return array{0:string|null, 1:string}
     */
    private function splitTableShortCaption(string $caption): array
    {
        $caption = trim($caption);
        if ($caption === '' || $caption[0] !== '[') {
            return [null, $caption];
        }

        $end = $this->findClosingTableShortCaptionBracket($caption);
        if ($end === null) {
            return [null, $caption];
        }

        $remainder = trim(substr($caption, $end + 1));
        if ($remainder === '') {
            return [null, $caption];
        }

        return [substr($caption, 1, $end - 1), $remainder];
    }

    private function findClosingTableShortCaptionBracket(string $caption): ?int
    {
        $depth = 0;
        $length = strlen($caption);
        for ($offset = 1; $offset < $length; $offset++) {
            $char = $caption[$offset];
            if ($char === '\\') {
                $offset++;
                continue;
            }

            if ($char === '[') {
                $depth++;
                continue;
            }

            if ($char !== ']') {
                continue;
            }

            if ($depth > 0) {
                $depth--;
                continue;
            }

            return $offset;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|string $caption
     */
    private function tableWithCaption(AstNode $table, array|string $caption): AstNode
    {
        if ($table->type !== 'table') {
            return $table;
        }

        $attrs = array_replace($table->attrs, $this->tableCaptionAttrs($caption));

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $table->children));
    }

    /**
     * @param array<string, mixed>|string $caption
     * @return array<string, mixed>
     */
    private function tableCaptionAttrs(array|string $caption): array
    {
        if (is_string($caption)) {
            $caption = $caption === ''
                ? $this->emptyTableCaptionRecord()
                : $this->buildTableCaptionRecord($caption, 'after-table', ':');
        }

        $text = (string) ($caption['caption'] ?? '');
        $attrs = ['caption' => $text];
        if ($text !== '') {
            $inlines = $caption['captionInlines'] ?? $this->parseInlines($text);
            if (is_array($inlines)) {
                $attrs['captionInlines'] = $inlines;
            }
        }

        foreach (['shortCaption', 'shortCaptionInlines', 'captionSource', 'id', 'classes', 'attributes', 'htmlAttributes'] as $name) {
            if (array_key_exists($name, $caption)) {
                $attrs[$name] = $caption[$name];
            }
        }

        return $attrs;
    }

    /**
     * @return array{0:string, 1:array<string, mixed>}
     */
    private function extractTrailingTableCaptionAttributes(string $captionSpec): array
    {
        $captionSpec = rtrim($captionSpec);
        if ($captionSpec === '' || !str_ends_with($captionSpec, '}')) {
            return [$captionSpec, []];
        }

        $attributeStart = null;
        $searchOffset = 0;
        while (($candidate = $this->findUnescapedCharacter($captionSpec, '{', $searchOffset)) !== null) {
            $attributeStart = $candidate;
            $searchOffset = $candidate + 1;
        }
        if ($attributeStart === null) {
            return [$captionSpec, []];
        }

        $prefix = substr($captionSpec, 0, $attributeStart);
        if ($prefix !== '' && !ctype_space($prefix[strlen($prefix) - 1])) {
            return [$captionSpec, []];
        }

        $source = substr($captionSpec, $attributeStart + 1, -1);
        [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec($source);
        if ($id === null && $classes === [] && $attributes === []) {
            return [$captionSpec, []];
        }

        return [
            rtrim($prefix),
            $this->markdownAttributeAstAttrs($id, $classes, $attributes),
        ];
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

        $attrs = array_replace(
            ['alignments' => $delimiter['alignments']],
            $this->tableCaptionAttrs($caption)
        );
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
    private function trySkipEmptyHtmlCommentSeparator(array $lines, int $index, array $blocks): bool
    {
        $line = trim($lines[$index] ?? '');
        if (preg_match('/^<!--\s*-->$/', $line) !== 1) {
            return false;
        }

        $previous = $blocks[array_key_last($blocks)] ?? null;
        if (!$previous instanceof AstNode || !$this->isMarkdownListBlock($previous)) {
            return false;
        }

        $cursor = $index + 1;
        $count = count($lines);
        while ($cursor < $count && trim($lines[$cursor]) === '') {
            $cursor++;
        }

        if ($cursor >= $count) {
            return false;
        }

        $nextMarker = $this->matchListMarker($lines[$cursor], $cursor);

        return ($nextMarker !== null && $nextMarker['indent'] <= 3)
            || $this->isIndentedCodeLine($lines[$cursor])
            || $this->canStartDefinitionListAt($lines, $cursor);
    }

    private function isMarkdownListBlock(AstNode $node): bool
    {
        return $node->type === 'bullet_list'
            || $node->type === 'ordered_list'
            || $node->type === 'definition_list';
    }

    /**
     * @param list<string> $lines
     */
    private function canStartDefinitionListAt(array $lines, int $index): bool
    {
        if (!$this->canStartDefinitionTerm($lines[$index] ?? '')) {
            return false;
        }

        $cursor = $index + 1;
        if ($cursor < count($lines) && trim($lines[$cursor]) === '') {
            $cursor++;
        }

        return $cursor < count($lines) && $this->isDefinitionMarker($lines[$cursor]);
    }

    /**
     * @param list<string> $lines
     */
    private function readRawHtmlUntilMarker(array $lines, int &$index, string $marker): AstNode
    {
        $content = [];
        $cursor = $index;
        $count = count($lines);
        while ($cursor < $count) {
            $line = $this->normalizeRawHtmlLine($lines[$cursor]);
            $content[] = $line;
            if (str_contains($line, $marker)) {
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
    private function readRawHtmlUntilBlankLine(array $lines, int &$index, bool $interpretTableCells = false): AstNode
    {
        $content = [];
        $cursor = $index;
        $count = count($lines);

        while ($cursor < $count) {
            if (trim($this->expandTabsToSpaces($lines[$cursor])) === '') {
                break;
            }

            $line = $this->normalizeRawHtmlLine($lines[$cursor]);
            $content[] = $interpretTableCells ? $this->renderMarkdownInTableCells($line) : $line;
            $cursor++;
        }

        $index = min(max($index, $cursor - 1), $count - 1);

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
            if (
                preg_match($closingPattern, $line) === 1
                || (count($content) === 1 && $this->rawHtmlLineSelfClosesTag($line, $tag))
            ) {
                break;
            }
            $cursor++;
        }

        $index = min($cursor, $count - 1);

        return new AstNode('raw_html', ['html' => implode("\n", $content)]);
    }

    private function isCommonMarkBlankTerminatedRawHtmlTag(string $tag): bool
    {
        return in_array($tag, [
            'address',
            'article',
            'aside',
            'base',
            'basefont',
            'blockquote',
            'body',
            'caption',
            'center',
            'col',
            'colgroup',
            'dd',
            'details',
            'dialog',
            'dir',
            'div',
            'dl',
            'dt',
            'fieldset',
            'figcaption',
            'figure',
            'footer',
            'form',
            'frame',
            'frameset',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'head',
            'header',
            'hr',
            'html',
            'iframe',
            'legend',
            'li',
            'link',
            'main',
            'menu',
            'menuitem',
            'nav',
            'noframes',
            'ol',
            'optgroup',
            'option',
            'p',
            'param',
            'search',
            'section',
            'source',
            'summary',
            'table',
            'tbody',
            'td',
            'tfoot',
            'th',
            'thead',
            'title',
            'tr',
            'track',
            'ul',
        ], true);
    }

    private function isCommonMarkGenericRawHtmlBlockStart(string $line): bool
    {
        $expanded = $this->expandTabsToSpaces($line);
        if (
            preg_match('/^ {0,3}<\/?([A-Za-z][A-Za-z0-9-]*)/', $expanded, $m) === 1
            && strtolower($m[1]) === 'informaltable'
        ) {
            return false;
        }

        return preg_match(
            '~^ {0,3}(?:<[A-Za-z][A-Za-z0-9-]*(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?\s*/?>|</[A-Za-z][A-Za-z0-9-]*\s*>)[ \t]*$~u',
            $expanded
        ) === 1;
    }

    private function rawHtmlLineSelfClosesTag(string $line, string $tag): bool
    {
        return preg_match('~^ {0,3}<' . preg_quote($tag, '~') . '(?=\s|/>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?\s*/>[ \t]*$~iu', $line) === 1;
    }

    private function isCommonMarkParagraphInterruptingRawHtmlBlockStart(string $line): bool
    {
        $expanded = $this->expandTabsToSpaces($line);
        if (
            preg_match('/^ {0,3}<!--/', $expanded) === 1
            || preg_match('/^ {0,3}<\?/', $expanded) === 1
            || preg_match('/^ {0,3}<![A-Za-z]/', $expanded) === 1
            || preg_match('/^ {0,3}<!\[CDATA\[/', $expanded) === 1
        ) {
            return true;
        }

        if (preg_match('~^ {0,3}<(script|pre|style|textarea)(?=\s|>|/>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?\s*/?>~iu', $expanded) === 1) {
            return true;
        }

        if (preg_match('~^ {0,3}(?:</([A-Za-z][A-Za-z0-9-]*)\s*>|<([A-Za-z][A-Za-z0-9-]*)(?=\s|>|/>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?\s*/?>)~iu', $expanded, $m) !== 1) {
            return false;
        }

        $tag = strtolower((string) ($m[1] !== '' ? $m[1] : ($m[2] ?? '')));

        return $this->isCommonMarkBlankTerminatedRawHtmlTag($tag);
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

    /**
     * @param list<string> $lines
     * @param list<string> $content
     */
    private function isLazyBlockQuoteContinuationLine(array $lines, int $index, array $content): bool
    {
        $line = $lines[$index] ?? '';
        if (trim($line) === '') {
            return false;
        }

        $previous = $this->lastNonBlankBlockQuoteContentLine($content);
        if ($previous === null || !$this->canLineContinueBlockQuoteParagraphLazily($previous)) {
            return false;
        }

        return $this->canLineContinueBlockQuoteParagraphLazily($line);
    }

    /**
     * @param list<string> $content
     */
    private function lastNonBlankBlockQuoteContentLine(array $content): ?string
    {
        for ($index = count($content) - 1; $index >= 0; $index--) {
            if (trim($content[$index]) !== '') {
                return $content[$index];
            }
        }

        return null;
    }

    private function canLineContinueBlockQuoteParagraphLazily(string $line): bool
    {
        $expanded = $this->expandTabsToSpaces($line);
        if (trim($expanded) === '' || $this->countIndentColumns($expanded) >= 4) {
            return false;
        }

        if (
            preg_match('/^ {0,3}(?:#{1,6}\s+|`{3,}|~{3,}|:{3,})/', $expanded) === 1
            || preg_match('/^ {0,3}(?:<!--|<\?|<!|<\/?[A-Za-z])/', $expanded) === 1
            || preg_match('/^ {0,3}[:~]\s+/', $expanded) === 1
            || preg_match('/^ {0,3}\|/', $expanded) === 1
            || $this->isHorizontalRule($expanded)
        ) {
            return false;
        }

        $marker = $this->matchListMarker($expanded);

        return $marker === null || $marker['indent'] > 3;
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
    private function tryReadListBlock(array $lines, int &$index, bool $interruptsParagraph = false): ?AstNode
    {
        $marker = $this->matchListMarker($lines[$index] ?? '', $index);
        if ($marker === null || $marker['indent'] > 3) {
            return null;
        }
        if ($interruptsParagraph && !$this->canListMarkerInterruptParagraph($marker)) {
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
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null} $marker
     */
    private function canListMarkerInterruptParagraph(array $marker): bool
    {
        if (trim($marker['text']) === '') {
            return false;
        }

        return !$marker['ordered'] || $marker['start'] === 1;
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
            if ($this->shouldParseListItemOpeningTextAsBlocks($firstText, $lines, $cursor + 1, $baseIndent, $contentIndent)) {
                [$blockParts, $cursor, $blockLoose] = $this->readListItemIndentedBlocks(
                    $lines,
                    $cursor + 1,
                    $baseIndent,
                    $contentIndent,
                    [$firstText]
                );
                array_push($parts, ...$blockParts);
                $loose = $loose || $blockLoose;
            } else {
                $paragraph[] = $firstText;
                $cursor++;
            }
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

                if ($this->isHorizontalRule($lines[$next]) && $this->countIndentColumns($lines[$next]) < $contentIndent) {
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

            if ($this->isHorizontalRule($line) && $this->countIndentColumns($line) < $contentIndent) {
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
                $stripped = rtrim($this->stripIndentColumns($line, $contentIndent));
                if ($this->shouldParseListItemIndentedLineAsBlocks($stripped, $paragraph, $lines, $cursor, $baseIndent, $contentIndent)) {
                    $seedLines = [];
                    $continuesDefinitionList = $paragraph !== []
                        && $this->isDefinitionMarker($stripped)
                        && !$this->isListItemBlockStartLine($stripped);
                    if ($paragraph !== [] && $continuesDefinitionList) {
                        $seedLines = $paragraph;
                        $paragraph = [];
                    } else {
                        $this->flushListItemParagraph($paragraph, $parts);
                    }

                    [$blockParts, $cursor, $blockLoose] = $this->readListItemIndentedBlocks(
                        $lines,
                        $cursor,
                        $baseIndent,
                        $contentIndent,
                        $seedLines
                    );
                    array_push($parts, ...$blockParts);
                    $loose = $loose || $blockLoose;
                    continue;
                }

                $paragraph[] = trim($stripped);
                $cursor++;
                continue;
            }

            if ($this->isHorizontalRule($line)) {
                break;
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
     */
    private function shouldParseListItemOpeningTextAsBlocks(
        string $text,
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent
    ): bool {
        return $this->isListItemBlockStartLine($text)
            || $this->canStartListItemDefinitionBlock($text, $lines, $cursor, $baseIndent, $contentIndent);
    }

    /**
     * @param list<string> $paragraph
     * @param list<string> $lines
     */
    private function shouldParseListItemIndentedLineAsBlocks(
        string $line,
        array $paragraph,
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent
    ): bool {
        if ($this->isListItemBlockStartLine($line)) {
            return true;
        }

        return ($paragraph !== [] && $this->isDefinitionMarker($line))
            || ($paragraph === [] && $this->canStartListItemDefinitionBlock($line, $lines, $cursor + 1, $baseIndent, $contentIndent));
    }

    private function isListItemBlockStartLine(string $line): bool
    {
        if (trim($line) === '') {
            return false;
        }

        if (
            $this->tryParseMarkdownHeading($line) !== null
            || $this->isHorizontalRule($line)
            || $this->isBlockQuoteLine($line)
            || $this->isIndentedCodeLine($line)
            || $this->matchFencedDivOpening($line) !== null
            || $this->isFencedDivClosing($line, 3)
            || $this->isCommonMarkParagraphInterruptingRawHtmlBlockStart($line)
            || $this->isCommonMarkGenericRawHtmlBlockStart($line)
        ) {
            return true;
        }

        return preg_match('/^ {0,3}(?:`{3,}|~{3,})/', $line) === 1
            || preg_match('/^ {0,3}\|/', $line) === 1
            || preg_match('/^ {0,3}\+[=-]/', $line) === 1;
    }

    /**
     * @param list<string> $lines
     */
    private function canStartListItemDefinitionBlock(
        string $termLine,
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent
    ): bool {
        if (!$this->canStartDefinitionTerm($termLine)) {
            return false;
        }

        $count = count($lines);
        while ($cursor < $count && trim($lines[$cursor]) === '') {
            $cursor++;
        }

        if ($cursor >= $count) {
            return false;
        }

        $stripped = $this->stripListItemContentLine($lines[$cursor], $cursor, $baseIndent, $contentIndent);

        return $stripped !== null
            && $this->isDefinitionMarker($stripped)
            && !$this->isListItemBlockStartLine($stripped);
    }

    /**
     * @param list<string> $lines
     * @param list<string> $seedLines
     * @return array{0:list<AstNode>, 1:int, 2:bool}
     */
    private function readListItemIndentedBlocks(
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent,
        array $seedLines = []
    ): array {
        $content = $seedLines;
        $count = count($lines);
        $loose = false;

        while ($cursor < $count) {
            $line = $lines[$cursor];
            if (trim($line) === '') {
                if (!$this->hasListItemContinuationAfterBlank($lines, $cursor + 1, $baseIndent, $contentIndent)) {
                    break;
                }

                $content[] = '';
                $cursor++;
                $loose = true;
                continue;
            }

            $stripped = $this->stripListItemContentLine($line, $cursor, $baseIndent, $contentIndent);
            if ($stripped === null) {
                break;
            }

            $content[] = $stripped;
            $cursor++;
        }

        while ($content !== [] && trim($content[array_key_last($content)]) === '') {
            array_pop($content);
        }

        if ($content === []) {
            return [[], $cursor, $loose];
        }

        return [$this->read(implode("\n", $content))->children, $cursor, $loose];
    }

    /**
     * @param list<string> $lines
     */
    private function hasListItemContinuationAfterBlank(array $lines, int $cursor, int $baseIndent, int $contentIndent): bool
    {
        $count = count($lines);
        while ($cursor < $count && trim($lines[$cursor]) === '') {
            $cursor++;
        }

        return $cursor < $count
            && $this->stripListItemContentLine($lines[$cursor], $cursor, $baseIndent, $contentIndent) !== null;
    }

    private function stripListItemContentLine(string $line, int $lineIndex, int $baseIndent, int $contentIndent): ?string
    {
        $marker = $this->matchListMarker($line, $lineIndex);
        if ($marker !== null) {
            if ($marker['indent'] <= $baseIndent) {
                return null;
            }

            if ($this->isNestedListMarker($marker, $baseIndent, $contentIndent)) {
                return rtrim($this->stripIndentColumns($line, $baseIndent));
            }
        }

        if ($this->countIndentColumns($line) < $contentIndent) {
            return null;
        }

        return rtrim($this->stripIndentColumns($line, $contentIndent));
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
        $lines = [$text];
        $index = 0;

        return $this->tryReadRawHtmlBlock($lines, $index) !== null;
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

        if (preg_match('/^( *)([-*+])(?: +(.*)|$)/', $expanded, $m) === 1) {
            $padding = isset($m[3]) ? strlen($m[0]) - strlen($m[1]) - 1 - strlen($m[3]) : 1;
            $text = $m[3] ?? '';

            return [
                'indent' => strlen($m[1]),
                'ordered' => false,
                'start' => null,
                'text' => $text,
                'contentIndent' => strlen($m[1]) + 1 + $padding,
                'padding' => $padding,
                'style' => null,
                'delimiter' => null,
            ];
        }

        if (preg_match('/^( *)#([.)])(?: +(.*)|$)/', $expanded, $m) === 1) {
            $padding = isset($m[3]) ? strlen($m[0]) - strlen($m[1]) - 2 - strlen($m[3]) : 1;
            $text = $m[3] ?? '';

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => 1,
                'text' => $text,
                'contentIndent' => strlen($m[1]) + 2 + $padding,
                'padding' => $padding,
                'style' => 'default',
                'delimiter' => 'default',
            ];
        }

        if (preg_match('/^( *)\(([0-9]{1,9}|[A-Za-z]+)\)(?: +(.*)|$)/', $expanded, $m) === 1) {
            $padding = isset($m[3]) ? strlen($m[0]) - strlen($m[1]) - strlen($m[2]) - 2 - strlen($m[3]) : 0;
            $text = $m[3] ?? '';
            $ordinal = $this->parseOrderedMarkerOrdinal($m[2], 'two_parens', $padding, strlen($m[1]));
            if ($ordinal === null) {
                return null;
            }

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => $ordinal['start'],
                'text' => $text,
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 2 + max(1, $padding),
                'padding' => max(1, $padding),
                'style' => $ordinal['style'],
                'delimiter' => 'two_parens',
            ];
        }

        if (preg_match('/^( *)(\d{1,9})([.)])(?: +(.*)|$)/', $expanded, $m) === 1) {
            $padding = isset($m[4]) ? strlen($m[0]) - strlen($m[1]) - strlen($m[2]) - 1 - strlen($m[4]) : 1;
            $text = $m[4] ?? '';

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => (int) $m[2],
                'text' => $text,
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 1 + $padding,
                'padding' => $padding,
                'style' => 'decimal',
                'delimiter' => $m[3] === ')' ? 'one_paren' : 'period',
            ];
        }

        if (preg_match('/^( *)([A-Za-z]+)([.)])(?: +(.*)|$)/', $expanded, $m) === 1) {
            $delimiter = $m[3] === ')' ? 'one_paren' : 'period';
            $padding = isset($m[4]) ? strlen($m[0]) - strlen($m[1]) - strlen($m[2]) - 1 - strlen($m[4]) : 0;
            $text = $m[4] ?? '';
            $ordinal = $this->parseOrderedMarkerOrdinal($m[2], $delimiter, $padding, strlen($m[1]));
            if ($ordinal === null) {
                return null;
            }

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => $ordinal['start'],
                'text' => $text,
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 1 + max(1, $padding),
                'padding' => max(1, $padding),
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
        if (preg_match('/^( *)\(@([A-Za-z0-9_-]*)\)(?: +(.*)|$)/', $expanded, $m) !== 1) {
            return null;
        }
        $padding = isset($m[3]) ? strlen($m[0]) - strlen($m[1]) - strlen($m[2]) - 3 - strlen($m[3]) : 1;

        return [
            'indent' => strlen($m[1]),
            'label' => $m[2],
            'text' => $m[3] ?? '',
            'contentIndent' => strlen($m[1]) + strlen($m[2]) + 3 + $padding,
            'padding' => $padding,
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
        return $this->countIndentColumns($line) >= 4;
    }

    private function stripCodeIndent(string $line): string
    {
        return $this->stripIndentColumns($line, 4);
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
            [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec($inside);
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

    private function rawFormatAttributeSpec(string $source): ?string
    {
        $source = trim($source);
        if (preg_match('/^\{=([A-Za-z][A-Za-z0-9_.+-]*)\}$/', $source, $m) !== 1) {
            return null;
        }

        return strtolower($m[1]);
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

            $termLines = [];
            $definitionCursor = $cursor;
            while ($definitionCursor < $count && $this->canStartDefinitionTerm($lines[$definitionCursor])) {
                $termLines[] = trim($lines[$definitionCursor]);
                $definitionCursor++;
            }

            if ($termLines === []) {
                break;
            }

            $termText = implode("\n", $termLines);
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

            $term = new AstNode('term', ['text' => $termText], $this->definitionTermInlines($termLines));
            $items[] = new AstNode('definition_item', ['term' => $termText], array_merge([$term], $definitions));
        }

        if ($items === []) {
            return null;
        }

        $index = $cursor - 1;

        return new AstNode('definition_list', [], $items);
    }

    /**
     * @param list<string> $terms
     * @return list<AstNode>
     */
    private function definitionTermInlines(array $terms): array
    {
        $inlines = [];
        foreach ($terms as $index => $term) {
            if ($index > 0) {
                $inlines[] = new AstNode('linebreak');
            }
            array_push($inlines, ...$this->parseInlines($term));
        }

        return $inlines;
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
        if ($this->matchFencedDivOpening($line) !== null || $this->isFencedDivClosing($line, 3)) {
            return null;
        }

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

        if (preg_match('/^(?:[-*+]|\d{1,9}[.)]|#\.|>)\s+/', $content) === 1) {
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

    private function normalizeParagraphLine(string $line): string
    {
        $trimmed = trim($line);

        return preg_match('/ {2,}\z/', $line) === 1 ? $trimmed . '  ' : $trimmed;
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
                if (preg_match('/ {2,}\z/', $buffer) === 1) {
                    $buffer = rtrim($buffer, ' ');
                    $this->flushText($buffer, $nodes);
                    $nodes[] = new AstNode('linebreak');
                    $offset++;
                    continue;
                }

                if ($this->shouldSuppressEastAsianSoftBreak($buffer, $nodes, $text, $offset)) {
                    $offset++;
                    continue;
                }

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
                    $rawAttribute = $this->tryParseRawInlineAttributeSpec($text, $next);
                    if ($rawAttribute !== null) {
                        $this->flushText($buffer, $nodes);
                        $nodes[] = new AstNode('raw_inline', [
                            'format' => $rawAttribute['format'],
                            'text' => $code,
                        ]);
                        $offset = $rawAttribute['next'];
                        continue;
                    }

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
                $attribute = $this->tryParseInlineAttributeSpec($text, $math['next']);
                if ($attribute !== null) {
                    $math['node'] = new AstNode(
                        'math',
                        array_replace($math['node']->attrs, $attribute['attrs'])
                    );
                    $math['next'] = $attribute['next'];
                }
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

            $mark = $this->tryParseMark($text, $offset);
            if ($mark !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $mark['node'];
                $offset = $mark['next'];
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

            $nativeSpan = ($allowLinks && $this->nativeSpanInlineEnabled) ? $this->tryParseNativeSpanInline($text, $offset) : null;
            if ($nativeSpan !== null) {
                $this->flushText($buffer, $nodes);
                array_push($nodes, ...$nativeSpan['nodes']);
                $offset = $nativeSpan['next'];
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

            $abbreviation = $this->tryParseAbbreviation($text, $offset);
            if ($abbreviation !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $abbreviation['node'];
                $offset = $abbreviation['next'];
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
            'article-locator' => '/^(?:art(?:icles?|s)?\.?|article(?:s)?)\s+(.+)$/iu',
            'appendix' => '/^(?:app(?:endices|endixes|s)?\.?|appendix|appendices|appendixes)\s+(.+)$/iu',
            'book' => '/^(?:bks?\.?|books?)\s+(.+)$/iu',
            'canon' => '/^(?:cc?\.?|canons?)\s+(.+)$/iu',
            'chapter' => '/^(?:chap(?:ters?|s)?\.?|chapter(?:s)?)\s+(.+)$/iu',
            'column' => '/^(?:col(?:umns?|s)?\.?|column(?:s)?)\s+(.+)$/iu',
            'elocation' => '/^(?:e-?loc(?:ations?|s)?\.?|elocations?|e-locations?)\s+(.+)$/iu',
            'equation' => '/^(?:eq(?:uations?|s)?\.?|equation(?:s)?)\s+(.+)$/iu',
            'figure' => '/^(?:fig(?:ures?|s)?\.?|figure(?:s)?)\s+(.+)$/iu',
            'folio' => '/^(?:fol(?:ios?|s)?\.?|folio(?:s)?)\s+(.+)$/iu',
            'line' => '/^(?:l(?:ines?|s)?\.?|line(?:s)?)\s+(.+)$/iu',
            'note' => '/^(?:n(?:otes?|s)?\.?|note(?:s)?)\s+(.+)$/iu',
            'opus' => '/^(?:opp?\.?|opus|opera)\s+(.+)$/iu',
            'part' => '/^(?:pts?\.?|part(?:s)?)\s+(.+)$/iu',
            'rule' => '/^(?:rr?\.?|rule(?:s)?)\s+(.+)$/iu',
            'section' => '/^(?:sec(?:tions?|s)?\.?|section(?:s)?|\x{00A7}\x{00A7}?)\s+(.+)$/iu',
            'paragraph' => '/^(?:para(?:graphs?|s)?\.?|paragraph(?:s)?|\x{00B6}\x{00B6}?)\s+(.+)$/iu',
            'sub-verbo' => '/^(?:s\.?\s*vv?\.?|sub[-\s]?verb[aois]+|sub-verbo|sub-verbum)\s+(.+)$/iu',
            'supplement' => '/^(?:supp(?:lements?|s)?\.?|supplement(?:s)?)\s+(.+)$/iu',
            'table' => '/^(?:tbl(?:s)?\.?|table(?:s)?)\s+(.+)$/iu',
            'timestamp' => '/^(?:timestamps?|ts\.?)\s+(.+)$/iu',
            'title' => '/^(?:tit(?:les?|s)?\.?|title(?:s)?)\s+(.+)$/iu',
            'verse' => '/^(?:v(?:erses?|s)?\.?|verse(?:s)?)\s+(.+)$/iu',
            'volume' => '/^(?:vol(?:umes?|s)?\.?|volume(?:s)?)\s+(.+)$/iu',
            'issue' => '/^(?:iss(?:ues?|s)?\.?|issue(?:s)?)\s+(.+)$/iu',
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

        [$node, $next] = $this->buildLinkNodeWithTrailingAttributes(
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

        [$node, $next] = $this->buildLinkNodeWithTrailingAttributes(
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
    private function buildLinkNodeWithTrailingAttributes(
        string $source,
        string $label,
        string $url,
        string $title,
        int $offset
    ): array {
        $attrs = ['url' => $url];
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        $attribute = $this->tryParseInlineAttributeSpec($source, $offset);
        if ($attribute !== null) {
            $attrs = array_replace($attrs, $attribute['attrs']);
            $offset = $attribute['next'];
        }

        return [new AstNode('link', $attrs, $this->parseLinkLabelInlines($label)), $offset];
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

        $semanticSpan = $this->markdownSemanticSpanNode($id, $classes, $attributes, $label['text']);
        if ($semanticSpan !== null) {
            return [
                'node' => $semanticSpan,
                'next' => $end + 1,
            ];
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
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function markdownSemanticSpanNode(?string $id, array $classes, array $attributes, string $content): ?AstNode
    {
        $semanticClass = $classes[0] ?? null;
        $type = match ($semanticClass) {
            'smallcaps' => 'small_caps',
            'underline' => 'underline',
            'strikeout' => 'strikeout',
            'superscript' => 'superscript',
            'subscript' => 'subscript',
            default => null,
        };
        if ($type === null) {
            return null;
        }

        return new AstNode(
            $type,
            $this->markdownAttributeAstAttrs($id, array_slice($classes, 1), $attributes),
            $this->parseInlines($content)
        );
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

        $alias = $m[1];
        $emoji = MarkdownEmojiAliases::glyphForAlias($alias);
        if ($emoji === null) {
            return null;
        }

        return [
            'node' => new AstNode(
                'span',
                $this->markdownAttributeAstAttrs(null, ['emoji'], ['data-emoji' => $alias]),
                [new AstNode('text', ['text' => $emoji])]
            ),
            'next' => $offset + strlen($m[0]),
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseAbbreviation(string $text, int $offset): ?array
    {
        if ($this->abbreviationDefinitions === [] || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        foreach ($this->abbreviationDefinitions as $term => $title) {
            $length = strlen($term);
            if ($length === 0 || substr($text, $offset, $length) !== $term) {
                continue;
            }

            if (!$this->abbreviationBoundaryMatches($text, $offset, $term)) {
                continue;
            }

            return [
                'node' => new AstNode(
                    'span',
                    $this->markdownAttributeAstAttrs(null, ['abbr'], ['title' => $title]),
                    [new AstNode('text', ['text' => $term])]
                ),
                'next' => $offset + $length,
            ];
        }

        return null;
    }

    private function abbreviationBoundaryMatches(string $text, int $offset, string $term): bool
    {
        $length = strlen($term);
        if (preg_match('/\A[\pL\pN]/u', $term) === 1 && $this->hasWordCharacterBeforeOffset($text, $offset)) {
            return false;
        }

        if (
            preg_match('/[\pL\pN]\z/u', $term) === 1
            && preg_match('/\A[\pL\pN]/u', substr($text, $offset + $length)) === 1
        ) {
            return false;
        }

        return true;
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
     * @return array{format:string, next:int}|null
     */
    private function tryParseRawInlineAttributeSpec(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '{') {
            return null;
        }

        $end = $this->findUnescapedCharacter($text, '}', $offset + 1);
        if ($end === null) {
            return null;
        }

        $format = $this->rawFormatAttributeSpec(substr($text, $offset, $end - $offset + 1));
        if ($format === null) {
            return null;
        }

        return [
            'format' => $format,
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

        if (preg_match('/\G<([A-Za-z][A-Za-z0-9.+-]{1,31}:[^<>\s]*)>/u', $text, $m, 0, $offset) === 1) {
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
     * @return array{nodes:list<AstNode>, next:int}|null
     */
    private function tryParseNativeSpanInline(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '<' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (preg_match('~\G<span(?=\s|>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?>~iu', $text, $m, 0, $offset) !== 1) {
            return null;
        }

        $end = $this->findClosingNativeSpanInline($text, $offset + strlen($m[0]));
        if ($end === null) {
            return null;
        }

        $body = XmlHtml5Dom::parseHtmlFragmentBody(substr($text, $offset, $end - $offset));
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $nodes = $this->parseHtmlInlineChildren($body);
        if ($nodes === []) {
            return null;
        }

        return [
            'nodes' => $nodes,
            'next' => $end,
        ];
    }

    private function findClosingNativeSpanInline(string $text, int $offset): ?int
    {
        $depth = 1;
        $cursor = $offset;
        $length = strlen($text);
        while ($cursor < $length) {
            $tag = $this->findNativeSpanInlineTag($text, $cursor);
            if ($tag === null) {
                return null;
            }

            if ($tag['closing']) {
                $depth--;
                if ($depth === 0) {
                    return $tag['end'];
                }
            } elseif (!$tag['selfClosing']) {
                $depth++;
            }

            $cursor = $tag['end'];
        }

        return null;
    }

    /**
     * @return array{closing:bool, selfClosing:bool, end:int}|null
     */
    private function findNativeSpanInlineTag(string $text, int $offset): ?array
    {
        if (
            preg_match(
                '~</span\s*>|<span(?=\s|>)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?\s*/?>~iu',
                $text,
                $m,
                PREG_OFFSET_CAPTURE,
                $offset
            ) !== 1
        ) {
            return null;
        }

        $raw = $m[0][0];

        return [
            'closing' => str_starts_with(strtolower($raw), '</'),
            'selfClosing' => preg_match('~/\s*>\z~', $raw) === 1,
            'end' => $m[0][1] + strlen($raw),
        ];
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

        $special = $this->tryParseSpecialRawHtmlInline($text, $offset);
        if ($special !== null) {
            return $special;
        }

        $tag = $this->tryParseRawHtmlInlineTag($text, $offset);
        if ($tag !== null) {
            return $tag;
        }

        return null;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseSpecialRawHtmlInline(string $text, int $offset): ?array
    {
        $markers = [
            '<!--' => '-->',
            '<![CDATA[' => ']]>',
            '<?' => '?>',
        ];
        foreach ($markers as $open => $close) {
            if (substr($text, $offset, strlen($open)) !== $open) {
                continue;
            }

            $end = strpos($text, $close, $offset + strlen($open));
            if ($end === false) {
                return null;
            }

            $html = substr($text, $offset, $end + strlen($close) - $offset);

            return [
                'node' => new AstNode('raw_html_inline', ['html' => $html]),
                'next' => $offset + strlen($html),
            ];
        }

        if (preg_match('/\G<![A-Za-z]/', $text, $m, 0, $offset) !== 1) {
            return null;
        }

        $end = strpos($text, '>', $offset + 2);
        if ($end === false) {
            return null;
        }

        $html = substr($text, $offset, $end - $offset + 1);
        if (str_contains($html, "\n") || str_contains($html, "\r")) {
            return null;
        }

        return [
            'node' => new AstNode('raw_html_inline', ['html' => $html]),
            'next' => $end + 1,
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseRawHtmlInlineTag(string $text, int $offset): ?array
    {
        $html = $this->readRawHtmlInlineTagSource($text, $offset);
        if ($html === null || !$this->isRawHtmlInlineTagSource($html)) {
            return null;
        }

        return [
            'node' => new AstNode('raw_html_inline', ['html' => $html]),
            'next' => $offset + strlen($html),
        ];
    }

    private function readRawHtmlInlineTagSource(string $text, int $offset): ?string
    {
        if (($text[$offset] ?? '') !== '<') {
            return null;
        }

        $quote = null;
        $length = strlen($text);
        for ($cursor = $offset + 1; $cursor < $length; $cursor++) {
            $char = $text[$cursor];
            if ($char === "\n" || $char === "\r") {
                return null;
            }

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '>') {
                return substr($text, $offset, $cursor - $offset + 1);
            }
        }

        return null;
    }

    private function isRawHtmlInlineTagSource(string $html): bool
    {
        if (preg_match('~^</([A-Za-z][A-Za-z0-9-]*)[ \t]*>$~', $html, $closing) === 1) {
            return $this->isKnownRawHtmlInlineTagName($closing[1]);
        }

        if (
            preg_match(
                '~^<([A-Za-z][A-Za-z0-9-]*)(?:[ \t]+[A-Za-z_:][A-Za-z0-9_.:-]*(?:[ \t]*=[ \t]*(?:"[^"]*"|\'[^\']*\'|[^ \t"\'=<>`]+))?)*[ \t]*/?>$~u',
                $html,
                $opening
            ) !== 1
        ) {
            return false;
        }

        return $this->isKnownRawHtmlInlineTagName($opening[1]);
    }

    private function isKnownRawHtmlInlineTagName(string $name): bool
    {
        return in_array(strtolower($name), [
            'a', 'abbr', 'address', 'area', 'article', 'aside', 'audio',
            'b', 'base', 'bdi', 'bdo', 'blockquote', 'br', 'button',
            'canvas', 'circle', 'cite', 'clippath', 'code', 'col', 'data',
            'datalist', 'defs', 'del', 'desc', 'details', 'dfn', 'dialog',
            'div', 'ellipse', 'em', 'embed', 'feblend', 'fecomposite',
            'fedropshadow', 'feflood', 'fegaussianblur', 'feoffset',
            'fieldset', 'figcaption', 'figure', 'filter', 'footer',
            'foreignobject', 'form', 'g', 'h1', 'h2', 'h3', 'h4', 'h5',
            'h6', 'header', 'hgroup', 'hr', 'i', 'iframe', 'image', 'img',
            'input', 'ins', 'kbd', 'label', 'legend', 'li', 'line', 'link',
            'lineargradient', 'main', 'map', 'marker', 'mark', 'mask',
            'math', 'menu', 'meta', 'metadata', 'meter', 'mi', 'mn', 'mo', 'mrow', 'msqrt', 'msub',
            'msup', 'mtable', 'mtd', 'mtext', 'mtr', 'nav', 'object', 'ol',
            'optgroup', 'option', 'output', 'p', 'param', 'path', 'pattern',
            'picture', 'polygon', 'polyline', 'pre', 'progress', 'q',
            'radialgradient', 'rect', 'rp', 'rt', 'ruby', 's', 'samp',
            'script', 'search', 'section', 'select', 'semantics', 'set',
            'slot', 'small', 'source', 'span', 'stop', 'strong', 'style',
            'sub', 'summary', 'sup', 'svg', 'switch', 'symbol', 'table',
            'tbody', 'td', 'template', 'text', 'textarea', 'textpath',
            'tfoot', 'th', 'thead', 'time', 'tr', 'track', 'tspan', 'u',
            'ul', 'use', 'var', 'video', 'view', 'wbr',
        ], true);
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
            ) === 1
        ) {
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

        if (preg_match('~\Gwww\.[^\s<>"\']+~iu', $text, $m, 0, $offset) === 1) {
            $candidate = $this->trimBareUriAutolinkCandidate($m[0]);
            if (strlen($candidate) <= 4) {
                return null;
            }

            $display = $this->decodeHtmlEntities($this->unescapeLinkComponent($candidate));

            return [
                'node' => new AstNode(
                    'link',
                    [
                        'url' => 'http://' . $this->normalizeBareUriDestination($candidate),
                        'classes' => ['uri'],
                    ],
                    [new AstNode('text', ['text' => $display])]
                ),
                'next' => $offset + strlen($candidate),
            ];
        }

        if (
            preg_match(
                '~\G[A-Za-z0-9.!#$%&\'*+/=?^_`{|}\~-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+~u',
                $text,
                $m,
                0,
                $offset
            ) === 1
        ) {
            $candidate = $this->trimBareUriAutolinkCandidate($m[0]);
            if ($candidate === '') {
                return null;
            }

            $address = $this->decodeHtmlEntities($this->unescapeLinkComponent($candidate));

            return [
                'node' => new AstNode(
                    'link',
                    [
                        'url' => 'mailto:' . $address,
                        'classes' => ['email'],
                    ],
                    [new AstNode('text', ['text' => $address])]
                ),
                'next' => $offset + strlen($candidate),
            ];
        }

        return null;
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
        if (($this->options['texMathDoubleBackslash'] ?? false) === true) {
            $doubleBackslashMath = $this->tryParseDoubleBackslashMath($text, $offset);
            if ($doubleBackslashMath !== null) {
                return $doubleBackslashMath;
            }
        }

        $singleBackslashMath = $this->tryParseSingleBackslashMath($text, $offset);
        if ($singleBackslashMath !== null) {
            return $singleBackslashMath;
        }

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

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseDoubleBackslashMath(string $text, int $offset): ?array
    {
        if (substr($text, $offset, 2) !== '\\\\' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        $opener = $text[$offset + 2] ?? '';
        $closer = match ($opener) {
            '(' => ')',
            '[' => ']',
            default => null,
        };
        if ($closer === null) {
            return null;
        }

        $end = $this->findClosingDoubleBackslashMath($text, $offset + 3, $closer);
        if ($end === null || $end === $offset + 3) {
            return null;
        }

        return [
            'node' => new AstNode('math', [
                'text' => $this->expandRawTexMathMacros(trim(substr($text, $offset + 3, $end - $offset - 3))),
                'display' => $opener === '[',
            ]),
            'next' => $end + 3,
        ];
    }

    private function findClosingDoubleBackslashMath(string $text, int $offset, string $closer): ?int
    {
        $needle = '\\\\' . $closer;
        $position = strpos($text, $needle, $offset);
        while ($position !== false) {
            if (!$this->isEscapedInlinePosition($text, $position)) {
                return $position;
            }

            $position = strpos($text, $needle, $position + 3);
        }

        return null;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseSingleBackslashMath(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '\\' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        $opener = $text[$offset + 1] ?? '';
        $closer = match ($opener) {
            '(' => ')',
            '[' => ']',
            default => null,
        };
        if ($closer === null) {
            return null;
        }

        $end = $this->findClosingSingleBackslashMath($text, $offset + 2, $closer);
        if ($end === null || $end === $offset + 2) {
            return null;
        }

        return [
            'node' => new AstNode('math', [
                'text' => $this->expandRawTexMathMacros(trim(substr($text, $offset + 2, $end - $offset - 2))),
                'display' => $opener === '[',
            ]),
            'next' => $end + 2,
        ];
    }

    private function findClosingSingleBackslashMath(string $text, int $offset, string $closer): ?int
    {
        $needle = '\\' . $closer;
        $position = strpos($text, $needle, $offset);
        while ($position !== false) {
            if (!$this->isEscapedInlinePosition($text, $position)) {
                return $position;
            }

            $position = strpos($text, $needle, $position + 2);
        }

        return null;
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
    private function tryParseMark(string $text, int $offset): ?array
    {
        if (
            substr($text, $offset, 2) !== '=='
            || ($text[$offset + 2] ?? '') === '='
            || ctype_space($text[$offset + 2] ?? '')
            || $this->isEscapedInlinePosition($text, $offset)
        ) {
            return null;
        }

        $end = $this->findClosingMarkDelimiter($text, $offset + 2);
        if ($end === null || $end === $offset + 2) {
            return null;
        }

        $inner = trim(substr($text, $offset + 2, $end - $offset - 2));
        if ($inner === '') {
            return null;
        }

        return [
            'node' => new AstNode(
                'span',
                ['classes' => ['mark']],
                $this->parseInlines($inner)
            ),
            'next' => $end + 2,
        ];
    }

    private function findClosingMarkDelimiter(string $text, int $offset): ?int
    {
        $position = strpos($text, '==', $offset);
        while ($position !== false) {
            if (!$this->isEscapedInlinePosition($text, $position)) {
                return $position;
            }

            $position = strpos($text, '==', $position + 2);
        }

        return null;
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

    /**
     * @param list<AstNode> $nodes
     */
    private function shouldSuppressEastAsianSoftBreak(string $buffer, array $nodes, string $text, int $newlineOffset): bool
    {
        if (($this->options['eastAsianLineBreaks'] ?? false) !== true) {
            return false;
        }

        $before = $this->lastInlineCharacterForEastAsianBreak($buffer, $nodes);
        $after = $this->firstUtf8Character(substr($text, $newlineOffset + 1));
        if ($before === null || $after === null) {
            return false;
        }

        return $this->isEastAsianLineBreakCharacter($before)
            && $this->isEastAsianLineBreakCharacter($after);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function lastInlineCharacterForEastAsianBreak(string $buffer, array $nodes): ?string
    {
        $fromBuffer = $this->lastUtf8Character($buffer);
        if ($fromBuffer !== null) {
            return $fromBuffer;
        }

        for ($index = count($nodes) - 1; $index >= 0; $index--) {
            $node = $nodes[$index];
            if ($node->type === 'text') {
                $char = $this->lastUtf8Character((string) $node->attr('text', ''));
                if ($char !== null) {
                    return $char;
                }
                continue;
            }

            if ($node->type === 'code') {
                $char = $this->lastUtf8Character((string) $node->attr('text', ''));
                if ($char !== null) {
                    return $char;
                }
                continue;
            }

            if (in_array(
                $node->type,
                ['emph', 'strong', 'span', 'small_caps', 'underline', 'strikeout', 'superscript', 'subscript', 'quoted', 'link'],
                true
            )) {
                $char = $this->lastInlineCharacterForEastAsianBreak('', $node->children);
                if ($char !== null) {
                    return $char;
                }
            }
        }

        return null;
    }

    private function firstUtf8Character(string $text): ?string
    {
        return preg_match('/\A./us', $text, $m) === 1 ? $m[0] : null;
    }

    private function lastUtf8Character(string $text): ?string
    {
        return preg_match('/.\z/us', $text, $m) === 1 ? $m[0] : null;
    }

    private function isEastAsianLineBreakCharacter(string $char): bool
    {
        return preg_match('/\A[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]\z/u', $char) === 1;
    }

    private function decodeHtmlEntities(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        return preg_replace_callback(
            '/&#(?:([0-9]{1,7})|[xX]([0-9A-Fa-f]{1,6}));/',
            function (array $matches): string {
                $codePoint = ($matches[1] ?? '') !== ''
                    ? (int) $matches[1]
                    : hexdec((string) $matches[2]);

                return $this->commonMarkCharacterReference($codePoint);
            },
            $text
        ) ?? $text;
    }

    private function commonMarkCharacterReference(int $codePoint): string
    {
        if (
            $codePoint === 0
            || $codePoint > 0x10FFFF
            || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)
        ) {
            return "\u{FFFD}";
        }

        return $this->utf8FromCodePoint($codePoint);
    }

    private function utf8FromCodePoint(int $codePoint): string
    {
        if ($codePoint <= 0x7F) {
            return chr($codePoint);
        }

        if ($codePoint <= 0x7FF) {
            return chr(0xC0 | ($codePoint >> 6))
                . chr(0x80 | ($codePoint & 0x3F));
        }

        if ($codePoint <= 0xFFFF) {
            return chr(0xE0 | ($codePoint >> 12))
                . chr(0x80 | (($codePoint >> 6) & 0x3F))
                . chr(0x80 | ($codePoint & 0x3F));
        }

        return chr(0xF0 | ($codePoint >> 18))
            . chr(0x80 | (($codePoint >> 12) & 0x3F))
            . chr(0x80 | (($codePoint >> 6) & 0x3F))
            . chr(0x80 | ($codePoint & 0x3F));
    }
}
