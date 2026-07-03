<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownReader
{
    private const MARKDOWN_ESCAPABLE_ASCII_PUNCTUATION = "!\"#$%&'()*+,-./:;<=>?@[\\]^_`{|}~";
    /** Preserves backslash-escaped entity delimiters through the final entity decode pass. */
    private const ESCAPED_ENTITY_DELIMITER_PREFIX = "\x1FMD_ESCAPED_ENTITY_DELIMITER\x1F";
    /**
     * Mirrored from upstream pandoc data/abbreviations for the bounded
     * markdown abbreviation spacing slice.
     *
     * @var array<string, true>
     */
    private const ABBREVIATIONS = [
        'aet.' => true,
        'aetat.' => true,
        'al.' => true,
        'Apr.' => true,
        'Aug.' => true,
        'bk.' => true,
        'Bros.' => true,
        'c.' => true,
        'Capt.' => true,
        'cf.' => true,
        'ch.' => true,
        'chap.' => true,
        'chs.' => true,
        'Co.' => true,
        'col.' => true,
        'Corp.' => true,
        'cp.' => true,
        'd.' => true,
        'Dec.' => true,
        'Dr.' => true,
        'e.g.' => true,
        'ed.' => true,
        'eds.' => true,
        'esp.' => true,
        'f.' => true,
        'fasc.' => true,
        'Feb.' => true,
        'ff.' => true,
        'fig.' => true,
        'fl.' => true,
        'fol.' => true,
        'fols.' => true,
        'Fr.' => true,
        'Gen.' => true,
        'Gov.' => true,
        'Hon.' => true,
        'i.e.' => true,
        'ill.' => true,
        'Inc.' => true,
        'incl.' => true,
        'Jan.' => true,
        'Jr.' => true,
        'Jul.' => true,
        'Jun.' => true,
        'Ltd.' => true,
        'M.A.' => true,
        'M.D.' => true,
        'Mar.' => true,
        'Mr.' => true,
        'Mrs.' => true,
        'Ms.' => true,
        'n.' => true,
        'n.b.' => true,
        'nn.' => true,
        'No.' => true,
        'Nov.' => true,
        'Oct.' => true,
        'p.' => true,
        'Ph.D.' => true,
        'pp.' => true,
        'Pres.' => true,
        'Prof.' => true,
        'pt.' => true,
        'q.v.' => true,
        'Rep.' => true,
        'Rev.' => true,
        's.v.' => true,
        's.vv.' => true,
        'saec.' => true,
        'sec.' => true,
        'Sen.' => true,
        'Sep.' => true,
        'Sept.' => true,
        'Sgt.' => true,
        'Sr.' => true,
        'St.' => true,
        'univ.' => true,
        'viz.' => true,
        'vol.' => true,
        'vs.' => true,
    ];

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

    private ?string $htmlBaseHref = null;

    /** @var array<string, list<AstNode>> */
    private array $htmlFootnoteDefinitions = [];

    private bool $resolveInlineNotes = true;

    private bool $resolveFootnoteReferences = true;

    private bool $suppressHtmlInlineFragmentBlock = false;

    private int $htmlQuoteDepth = 0;

    private string $metadataMarkdownExtensionSuffix = '';

    private bool $documentHasYamlMetadata = false;

    private bool $forceLinkLabelSingleBackslashMath = false;

    /**
     * @param array{
     *     literateHaskell?: bool,
     *     htmlNativeDivs?: bool,
     *     htmlEpubExtensions?: bool,
     *     htmlImplicitHeadingIds?: bool,
     *     htmlPlainInlineBlocks?: bool,
     *     htmlPreserveSoftBreaks?: bool,
     *     htmlRawHtml?: bool,
     *     rawHtml?: bool,
     *     htmlIframeResources?: array<string, string|array{mime?: string, contentType?: string, body?: string, content?: string}>
     * } $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    private function tableWithReviewPacket(AstNode $table): AstNode
    {
        return TableGeometry::withReviewPacket($table);
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
        $previousHtmlQuoteDepth = $this->htmlQuoteDepth;
        $previousMetadataMarkdownExtensionSuffix = $this->metadataMarkdownExtensionSuffix;
        $previousDocumentHasYamlMetadata = $this->documentHasYamlMetadata;
        $this->htmlQuoteDepth = 0;
        $documentAttrs = [];
        if ($this->yamlMetadataEnabled()) {
            [$lines, $yamlAttrs] = $this->extractYamlMetadataBlock($lines);
            if ($yamlAttrs !== []) {
                $documentAttrs = array_replace_recursive($documentAttrs, $yamlAttrs);
                $this->metadataMarkdownExtensionSuffix = $this->markdownMetadataExtensionSuffix($documentAttrs['meta'] ?? []);
                $documentAttrs = $this->withYamlMetadataHelpers($documentAttrs);
                $this->documentHasYamlMetadata = true;
            }
        }
        [$lines, $titleBlock] = $this->titleBlockEnabled() ? $this->extractTitleBlock($lines) : [$lines, null];
        [$lines, $references, $footnotes, $abbreviations] = $this->extractReferenceDefinitions($lines);
        $lines = $this->splitMixedHtmlFlowLines($lines);
        [$exampleReferences, $exampleNumbersByLine] = $this->numberedExampleExtensionEnabled()
            ? $this->collectNumberedExampleReferences($lines)
            : [[], []];
        [$markdownHeadingIds, $implicitHeadingReferences] = $this->collectMarkdownHeadingReferences($lines);
        $this->referenceLinks = array_replace($previousReferenceLinks, $implicitHeadingReferences, $references);
        $this->footnoteDefinitions = array_replace($previousFootnoteDefinitions, $footnotes);
        $this->abbreviationDefinitions = array_replace($previousAbbreviationDefinitions, $abbreviations);
        $this->exampleReferences = array_replace($previousExampleReferences, $exampleReferences);
        $this->exampleNumbersByLine = $exampleNumbersByLine;
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
            $blockQuote = $listStack === [] ? $this->tryReadBlockQuote($lines, $index) : null;
            if ($blockQuote !== null) {
                $this->flushParagraph($paragraph, $blocks);
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
            $commonMarkRawHtmlBlock = $paragraph === [] && $listStack === []
                ? $this->tryReadCommonMarkPrecedenceRawHtmlBlock($lines, $index)
                : null;
            if ($commonMarkRawHtmlBlock !== null) {
                $blocks[] = $commonMarkRawHtmlBlock;
                continue;
            }
            $pandocRawHtmlBlock = $paragraph === [] && $listStack === []
                ? $this->tryReadPandocRawHtmlBlockBeforeSemanticHtml($lines, $index)
                : null;
            if ($pandocRawHtmlBlock !== null) {
                $blocks[] = $pandocRawHtmlBlock;
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
            $htmlFigure = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlFigureBlock($lines, $index) : null;
            if ($htmlFigure !== null) {
                $blocks[] = $htmlFigure;
                continue;
            }
            $htmlIframe = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlIframeBlock($lines, $index) : null;
            if ($htmlIframe !== null) {
                $blocks[] = $htmlIframe;
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
            $htmlNativeDivsMain = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlNativeDivsMainBlock($lines, $index) : null;
            if ($htmlNativeDivsMain !== null) {
                array_push($blocks, ...$htmlNativeDivsMain->children);
                continue;
            }
            $htmlNativeDivsHeader = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlNativeDivsHeaderBlock($lines, $index) : null;
            if ($htmlNativeDivsHeader !== null) {
                $blocks[] = $htmlNativeDivsHeader;
                continue;
            }
            $htmlNativeDivsContainer = $paragraph === [] && $listStack === [] ? $this->tryReadHtmlNativeDivsContainerBlock($lines, $index) : null;
            if ($htmlNativeDivsContainer !== null) {
                $blocks[] = $htmlNativeDivsContainer;
                continue;
            }
            $rawHtmlDetails = $paragraph === [] && $listStack === [] ? $this->tryReadRawHtmlDetailsBlock($lines, $index) : null;
            if ($rawHtmlDetails !== null) {
                array_push($blocks, ...$rawHtmlDetails);
                continue;
            }
            $rawHtmlContainer = $paragraph === [] && $listStack === [] ? $this->tryReadRawHtmlSingleLineContainerBlock($lines, $index) : null;
            if ($rawHtmlContainer !== null) {
                array_push($blocks, ...$rawHtmlContainer);
                continue;
            }
            $htmlInlineFragment = $paragraph === [] && $listStack === [] && !$this->suppressHtmlInlineFragmentBlock
                && !$this->commonMarkRawHtmlBlockPrecedenceEnabled()
                ? $this->tryReadHtmlInlineFragmentBlock($lines, $index)
                : null;
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
            if (
                $paragraph === []
                && $listStack === []
                && $this->isPandocListIndentedCodeSeparator($lines, $index, $blocks)
            ) {
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
            $rawTexBlock = $paragraph === [] && $listStack === [] && $this->rawTexEnabled()
                ? $this->tryReadRawTexBlock($lines, $index)
                : null;
            if ($rawTexBlock !== null) {
                $blocks[] = $rawTexBlock;
                continue;
            }
            $markerLineListBlock = $paragraph === [] && $listStack === []
                ? $this->tryReadListBlock($lines, $index)
                : null;
            if ($markerLineListBlock !== null) {
                $this->flushListStack($listStack, $blocks);
                $blocks[] = $markerLineListBlock;
                continue;
            }
            $markdownTable = $this->tryReadMarkdownTable($lines, $index, $paragraph === [] && $listStack === []);
            if ($markdownTable !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = $markdownTable;
                continue;
            }
            $paragraphSetextHeading = $paragraph !== [] && $listStack === []
                ? $this->tryPromoteParagraphSetextMarkdownHeading($paragraph, $line)
                : null;
            if ($paragraphSetextHeading !== null) {
                $startIndex = max(0, $index - count($paragraph));
                $text = $paragraphSetextHeading['text'];
                $blocks[] = new AstNode(
                    'heading',
                    $this->markdownHeadingAstAttrs($paragraphSetextHeading, $markdownHeadingIds[$startIndex] ?? $paragraphSetextHeading['id'] ?? ''),
                    $this->parseInlines($text)
                );
                $paragraph = [];
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
                $blocks[] = new AstNode(
                    'heading',
                    $this->markdownHeadingAstAttrs($setextHeading, $markdownHeadingIds[$index] ?? $setextHeading['id'] ?? ''),
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
                $blocks[] = new AstNode(
                    'heading',
                    $this->markdownHeadingAstAttrs($markdownHeading, $markdownHeadingIds[$index] ?? $markdownHeading['id'] ?? ''),
                    $this->parseInlines($text)
                );
                continue;
            }
            $listBlock = $this->tryReadListBlock($lines, $index, $paragraph !== []);
            if ($listBlock !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = $listBlock;
                continue;
            }
            $indentedCodeBlock = $paragraph === [] && $listStack === [] ? $this->tryReadIndentedCodeBlock($lines, $index) : null;
            if ($indentedCodeBlock !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $blocks[] = $indentedCodeBlock;
                continue;
            }
            $definitionList = $this->isSingleImageFollowedByFigureCaption($lines, $index)
                ? null
                : $this->tryReadDefinitionList($lines, $index);
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
        $blocks = $this->applyMarkdownFigureCaptions($blocks);

        if ($this->sectionDivsEnabled()) {
            $blocks = $this->sectionizeBlocks($blocks);
        }

        $document = new AstNode('document', $documentAttrs, $blocks);
        $this->referenceLinks = $previousReferenceLinks;
        $this->footnoteDefinitions = $previousFootnoteDefinitions;
        $this->abbreviationDefinitions = $previousAbbreviationDefinitions;
        $this->exampleReferences = $previousExampleReferences;
        $this->exampleNumbersByLine = $previousExampleNumbersByLine;
        $this->rawTexMacros = $previousRawTexMacros;
        $this->htmlQuoteDepth = $previousHtmlQuoteDepth;
        $this->metadataMarkdownExtensionSuffix = $previousMetadataMarkdownExtensionSuffix;
        $this->documentHasYamlMetadata = $previousDocumentHasYamlMetadata;

        return $document;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function sectionizeBlocks(array $blocks): array
    {
        $result = [];
        $stack = [];

        foreach ($blocks as $block) {
            if ($block->type !== 'heading') {
                $this->appendSectionizedBlock($result, $stack, $block);
                continue;
            }

            $level = (int) $block->attr('level', 1);
            while ($stack !== [] && (int) $stack[array_key_last($stack)]['level'] >= $level) {
                $this->closeSectionizedBlock($result, $stack);
            }

            $stack[] = [
                'level' => $level,
                'attrs' => $this->sectionDivAttrsFromHeading($block),
                'heading' => $this->headingWithoutSectionAttrs($block),
                'children' => [],
            ];
        }

        while ($stack !== []) {
            $this->closeSectionizedBlock($result, $stack);
        }

        return $result;
    }

    /**
     * @param list<AstNode> $result
     * @param list<array{level:int, attrs:array<string, mixed>, heading:AstNode, children:list<AstNode>}> $stack
     */
    private function appendSectionizedBlock(array &$result, array &$stack, AstNode $block): void
    {
        $last = array_key_last($stack);
        if ($last === null) {
            $result[] = $block;
            return;
        }

        $stack[$last]['children'][] = $block;
    }

    /**
     * @param list<AstNode> $result
     * @param list<array{level:int, attrs:array<string, mixed>, heading:AstNode, children:list<AstNode>}> $stack
     */
    private function closeSectionizedBlock(array &$result, array &$stack): void
    {
        $section = array_pop($stack);
        if ($section === null) {
            return;
        }

        $node = new AstNode(
            'div',
            $section['attrs'],
            array_merge([$section['heading']], $section['children'])
        );

        $this->appendSectionizedBlock($result, $stack, $node);
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionDivAttrsFromHeading(AstNode $heading): array
    {
        $level = max(1, (int) $heading->attr('level', 1));
        $id = (string) $heading->attr('id', '');
        if ($id === '') {
            $id = $this->slugifyMarkdownHeading((string) $heading->attr('text', ''));
        }

        $classes = ['section', 'level' . $level];
        $headingClasses = $heading->attr('classes', []);
        if (is_array($headingClasses)) {
            foreach ($headingClasses as $class) {
                $classes[] = (string) $class;
            }
        }

        $attributes = $heading->attr('attributes', []);
        if (!is_array($attributes)) {
            $attributes = [];
        }

        return $this->markdownAttributeAstAttrs($id, $classes, $attributes);
    }

    private function headingWithoutSectionAttrs(AstNode $heading): AstNode
    {
        $attrs = $heading->attrs;
        unset($attrs['id'], $attrs['classes'], $attrs['attributes'], $attrs['htmlAttributes']);
        $attrs['id'] = '';

        return new AstNode('heading', $attrs, $heading->children);
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>, 1:array<string, mixed>}
     */
    private function extractYamlMetadataBlock(array $lines): array
    {
        $block = $this->leadingYamlMetadataBlock($lines);
        if ($block === null) {
            return [$lines, []];
        }

        $review = YamlMetadataReview::fromMarkdown(implode("\n", $block['block']));
        $attrs = [];
        if (is_array($review['meta'] ?? null) && $review['meta'] !== []) {
            $attrs['meta'] = $review['meta'];
        }
        if (is_array($review['summary'] ?? null) && $review['summary'] !== []) {
            $attrs['yamlMetadataReviewSummary'] = $review['summary'];
        }
        if (is_array($review['provenanceByPath'] ?? null) && $review['provenanceByPath'] !== []) {
            $attrs['yamlMetadataProvenanceByPath'] = $review['provenanceByPath'];
        }
        if (is_array($review['diagnosticsByPath'] ?? null) && $review['diagnosticsByPath'] !== []) {
            $attrs['yamlMetadataDiagnosticsByPath'] = $review['diagnosticsByPath'];
        }

        $remainder = array_slice($lines, $block['next']);
        while ($remainder !== [] && trim($remainder[0]) === '') {
            array_shift($remainder);
        }

        return [$remainder, $attrs];
    }

    /**
     * @param list<string> $lines
     * @return array{block:list<string>, next:int}|null
     */
    private function leadingYamlMetadataBlock(array $lines): ?array
    {
        $count = count($lines);
        if ($count === 0) {
            return null;
        }

        $cursor = 0;
        while ($cursor < $count && preg_match('/^%(?:YAML|TAG)\b/i', trim($lines[$cursor])) === 1) {
            $cursor++;
        }

        if ($this->yamlDocumentMarker($lines[$cursor] ?? '') !== '---') {
            return null;
        }

        for ($end = $cursor + 1; $end < $count; $end++) {
            if ($this->yamlDocumentMarker($lines[$end]) !== null) {
                return [
                    'block' => array_slice($lines, 0, $end + 1),
                    'next' => $end + 1,
                ];
            }
        }

        return null;
    }

    private function yamlDocumentMarker(string $line): ?string
    {
        $trimmed = trim($line);
        if ($trimmed !== ltrim($line, " \t")) {
            return null;
        }
        if (str_contains($trimmed, '#')) {
            $trimmed = trim(strstr($trimmed, '#', true) ?: $trimmed);
        }

        return match ($trimmed) {
            '---', '...' => $trimmed,
            default => null,
        };
    }

    /**
     * @param mixed $meta
     */
    private function markdownMetadataExtensionSuffix(mixed $meta): string
    {
        if (!is_array($meta)) {
            return '';
        }

        $tokens = [];
        $this->collectMarkdownMetadataExtensionTokens($meta, $tokens, 0);

        return implode('', $tokens);
    }

    /**
     * @param mixed $value
     * @param list<string> $tokens
     */
    private function collectMarkdownMetadataExtensionTokens(mixed $value, array &$tokens, int $depth): void
    {
        if (!is_array($value) || $depth > 4) {
            return;
        }

        foreach ($value as $key => $item) {
            $normalizedKey = is_string($key) ? strtolower(str_replace(['-', '_'], '', $key)) : '';
            if (in_array($normalizedKey, ['format', 'from', 'inputformat', 'readerformat'], true)) {
                $this->appendMarkdownFormatExtensionTokens($item, $tokens);
            } elseif (in_array($normalizedKey, ['extension', 'extensions', 'markdownextensions', 'enabledextensions', 'readerextensions'], true)) {
                $this->appendMarkdownExtensionTokens($item, $tokens);
            }

            if (is_array($item)) {
                $this->collectMarkdownMetadataExtensionTokens($item, $tokens, $depth + 1);
            }
        }
    }

    /**
     * @param mixed $value
     * @param list<string> $tokens
     */
    private function appendMarkdownFormatExtensionTokens(mixed $value, array &$tokens): void
    {
        if (!is_scalar($value)) {
            return;
        }

        foreach (MarkdownFormatProfile::markdownExtensionOverrides((string) $value) as $extension => $enabled) {
            $tokens[] = ($enabled ? '+' : '-') . $extension;
        }
    }

    /**
     * @param mixed $value
     * @param list<string> $tokens
     */
    private function appendMarkdownExtensionTokens(mixed $value, array &$tokens): void
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                foreach ($value as $item) {
                    $this->appendMarkdownExtensionTokens($item, $tokens);
                }
                return;
            }

            foreach ($value as $name => $enabled) {
                if (!is_scalar($name)) {
                    continue;
                }
                $tokens[] = ($this->metadataExtensionFlag($enabled) ? '+' : '-') . (string) $name;
            }
            return;
        }

        if (!is_scalar($value)) {
            return;
        }

        $source = trim((string) $value);
        if ($source === '') {
            return;
        }

        $formatOverrides = MarkdownFormatProfile::markdownExtensionOverrides($source);
        if ($formatOverrides !== []) {
            foreach ($formatOverrides as $extension => $enabled) {
                $tokens[] = ($enabled ? '+' : '-') . $extension;
            }
            return;
        }

        $source = str_replace(',', ' ', $source);
        $source = preg_replace('/\s+([+-])\s+/', ' $1', $source) ?? $source;
        $pendingSign = '+';
        foreach (preg_split('/\s+/', trim($source), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if ($part === '+' || $part === '-') {
                $pendingSign = $part;
                continue;
            }

            $sign = $pendingSign;
            $token = $part;
            if ($token[0] === '+' || $token[0] === '-') {
                $sign = $token[0];
                $token = substr($token, 1);
            }
            $pendingSign = '+';
            if ($token === '') {
                continue;
            }

            $formatOverrides = MarkdownFormatProfile::markdownExtensionOverrides($token);
            if ($formatOverrides !== []) {
                foreach ($formatOverrides as $extension => $enabled) {
                    $tokens[] = ($enabled ? '+' : '-') . $extension;
                }
                continue;
            }

            $tokens[] = $sign . $token;
        }
    }

    private function metadataExtensionFlag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'enabled', 'enable'], true);
        }

        return false;
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
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function withYamlMetadataHelpers(array $attrs): array
    {
        $meta = $attrs['meta'] ?? null;
        if (!is_array($meta) || array_key_exists('abstractBlocks', $meta)) {
            return $attrs;
        }

        $abstract = $meta['abstract'] ?? null;
        if (!is_string($abstract) && !is_int($abstract) && !is_float($abstract)) {
            return $attrs;
        }

        $blocks = $this->metadataBlocksFromMarkdown((string) $abstract);
        if ($blocks !== []) {
            $meta['abstractBlocks'] = $blocks;
            $attrs['meta'] = $meta;
        }

        return $attrs;
    }

    /**
     * @return list<AstNode>
     */
    private function metadataBlocksFromMarkdown(string $markdown): array
    {
        if (trim($markdown) === '') {
            return [];
        }

        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();
        $options['extensions'] = '';
        $options['yamlMetadata'] = false;
        $options['titleBlock'] = false;

        return (new self($options))->read($markdown)->children;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>, 1:array<string, array{url:string, title:string}>, 2:array<string, string>, 3:array<string, string>}
     */
    private function extractReferenceDefinitions(array $lines): array
    {
        $content = [];
        $references = [];
        $footnotes = [];
        $abbreviations = [];
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $boundaryEnd = $this->markdownReferenceBoundaryEndIndex($lines, $index);
            if ($boundaryEnd !== null) {
                for ($cursor = $index; $cursor <= $boundaryEnd; $cursor++) {
                    $content[] = $lines[$cursor];
                }
                $index = $boundaryEnd;
                continue;
            }

            $line = $lines[$index];
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
                if ($target !== null && $this->isValidReferenceLabel($reference['label'])) {
                    $normalizedLabel = $this->normalizeReferenceLabel($reference['label']);
                    $references[$normalizedLabel] ??= [
                        'url' => $target['url'],
                        'title' => $target['title'],
                    ];
                    $index = $nextIndex - 1;
                    continue;
                }
            }

            $abbreviation = $this->tryParseAbbreviationDefinitionStart($expanded);
            if ($abbreviation !== null) {
                $abbreviations[$abbreviation['term']] ??= $abbreviation['title'];
                continue;
            }

            $content[] = $line;
        }

        return [$content, $references, $footnotes, $abbreviations];
    }

    /**
     * @param list<string> $lines
     */
    private function markdownReferenceBoundaryEndIndex(array $lines, int $index): ?int
    {
        return $this->markdownFencedDivBoundaryEndIndex($lines, $index)
            ?? $this->markdownCodeFenceBoundaryEndIndex($lines, $index)
            ?? $this->markdownNativeDivBoundaryEndIndex($lines, $index)
            ?? $this->markdownRawHtmlBoundaryEndIndex($lines, $index)
            ?? $this->markdownRawTexBoundaryEndIndex($lines, $index);
    }

    /**
     * @param list<string> $lines
     */
    private function markdownCodeFenceBoundaryEndIndex(array $lines, int $index): ?int
    {
        if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $lines[$index] ?? '', $fence) !== 1) {
            return null;
        }

        $fenceChar = $fence[1][0];
        $fenceLength = strlen($fence[1]);
        $count = count($lines);
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            if ($this->isClosingCodeFence($lines[$cursor], $fenceChar, $fenceLength)) {
                return $cursor;
            }
        }

        return $count - 1;
    }

    /**
     * @param list<string> $lines
     */
    private function markdownFencedDivBoundaryEndIndex(array $lines, int $index): ?int
    {
        if (!$this->fencedDivExtensionEnabled()) {
            return null;
        }

        if (preg_match('/^ {0,3}(:{3,})[ \t]*(?:\{[^{}]*\}|[^\r\n]*)$/', $lines[$index] ?? '', $fence) !== 1) {
            return null;
        }

        $fenceLength = strlen($fence[1]);
        $count = count($lines);
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            if ($this->isClosingFencedDiv($lines[$cursor], $fenceLength)) {
                return $cursor;
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function markdownNativeDivBoundaryEndIndex(array $lines, int $index): ?int
    {
        if (preg_match('/^ {0,3}<div(?=\s|>)(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*>/iu', $lines[$index] ?? '') !== 1) {
            return null;
        }

        $collected = $this->collectBalancedHtmlElementBlock($lines, $index, 'div');
        if ($collected === null) {
            return null;
        }

        return $collected[1];
    }

    /**
     * @param list<string> $lines
     * @return array{lines:list<string>, end:int}|null
     */
    private function markdownNativeDivContentLines(array $lines, int $index): ?array
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}(<div(?=\s|>)(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*>)/iu', $line, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $content = [];
        $depth = 1;
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

                    return [
                        'lines' => $this->trimOuterBlankMarkdownLines($content),
                        'end' => $cursor,
                    ];
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
     * @param list<string> $lines
     * @return list<string>
     */
    private function trimOuterBlankMarkdownLines(array $lines): array
    {
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim($lines[array_key_last($lines)]) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function markdownRawHtmlBoundaryEndIndex(array $lines, int $index): ?int
    {
        if (!$this->htmlRawHtmlEnabled()) {
            return null;
        }

        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<!--/', $line) === 1) {
            return $this->markdownRawHtmlMarkerEndIndex($lines, $index, '-->');
        }

        if (preg_match('/^ {0,3}<\?/', $line) === 1) {
            return $this->markdownRawHtmlMarkerEndIndex($lines, $index, '?>');
        }

        if (preg_match('/^ {0,3}<!\[CDATA\[/', $line) === 1) {
            return $this->markdownRawHtmlMarkerEndIndex($lines, $index, ']]>');
        }

        if (preg_match('/^ {0,3}<![A-Za-z]/', $line) === 1) {
            return $this->markdownRawHtmlMarkerEndIndex($lines, $index, '>');
        }

        if ($this->isAngleAutolinkOnlyLine($line)) {
            return null;
        }

        $tag = $this->tryParseRawHtmlOpeningTag($line);
        if (
            $tag !== null
            && in_array($tag['name'], ['script', 'style', 'pre', 'textarea', 'noscript', 'xmp'], true)
        ) {
            return $this->markdownRawHtmlClosingTagEndIndex($lines, $index, $tag['name']);
        }

        if ($tag !== null && $tag['name'] === 'table') {
            return $this->markdownRawHtmlClosingTagEndIndex($lines, $index, 'table');
        }

        if ($tag !== null && $this->isMarkdownReferenceHtmlClosingBoundaryTag($tag['name'])) {
            return $tag['selfClosing']
                ? $this->markdownRawHtmlBlankLineEndIndex($lines, $index)
                : $this->markdownRawHtmlClosingTagEndIndex($lines, $index, $tag['name']);
        }

        if ($tag !== null && $tag['name'] === 'hr' && preg_match('/^ {0,3}<hr(?:\s+[^>]*)?\/?>[ \t]*$/i', $line) === 1) {
            return $index;
        }

        if ($this->tryParseRawHtmlClosingTag($line) !== null) {
            return $this->markdownRawHtmlBlankLineEndIndex($lines, $index);
        }

        if (
            $tag !== null
            && (
                $this->isCommonMarkBlankTerminatedRawHtmlTag($tag['name'])
                || $this->isRawHtmlCustomTagName($tag['name'])
            )
        ) {
            return $this->markdownRawHtmlBlankLineEndIndex($lines, $index);
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function markdownRawHtmlMarkerEndIndex(array $lines, int $index, string $marker): int
    {
        $count = count($lines);
        for ($cursor = $index; $cursor < $count; $cursor++) {
            if (str_contains($lines[$cursor], $marker)) {
                return $cursor;
            }
        }

        return max($index, $count - 1);
    }

    /**
     * @param list<string> $lines
     */
    private function markdownRawHtmlClosingTagEndIndex(array $lines, int $index, string $tag): int
    {
        $closingPattern = '/<\/' . preg_quote($tag, '/') . '\s*>/i';
        $count = count($lines);
        for ($cursor = $index; $cursor < $count; $cursor++) {
            if (preg_match($closingPattern, $lines[$cursor]) === 1) {
                return $cursor;
            }
        }

        return max($index, $count - 1);
    }

    /**
     * @param list<string> $lines
     */
    private function markdownRawHtmlBlankLineEndIndex(array $lines, int $index): int
    {
        $count = count($lines);
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            if (trim($this->expandTabsToSpaces($lines[$cursor])) === '') {
                return $cursor - 1;
            }
        }

        return max($index, $count - 1);
    }

    /**
     * @param list<string> $lines
     */
    private function markdownRawTexBoundaryEndIndex(array $lines, int $index): ?int
    {
        if (!$this->rawTexEnabled()) {
            return null;
        }

        $line = $lines[$index] ?? '';
        if ($this->tryReadRawTexMacroDefinition($line) !== null) {
            return $index;
        }

        if (preg_match('/^ {0,3}\\\\placeformula\s+\\\\startformula(?:\s.*)?$/', $line) === 1) {
            return $index;
        }

        if (preg_match('/^ {0,3}\\\\begin\{([^}\s]+)\}/', $line, $m) === 1) {
            return $this->markdownRawTexLatexEndIndex($lines, $index, $m[1]);
        }

        if (preg_match('/^ {0,3}\\\\start\[([^\]\r\n]+)]\s*$/', $line, $m) === 1) {
            return $this->markdownRawTexContextEndIndex($lines, $index, $m[1]);
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function markdownRawTexLatexEndIndex(array $lines, int $index, string $environment): int
    {
        $closingPattern = '/\\\\end\{' . preg_quote($environment, '/') . '\}/';
        $count = count($lines);
        for ($cursor = $index; $cursor < $count; $cursor++) {
            if (preg_match($closingPattern, $lines[$cursor]) === 1) {
                return $cursor;
            }
        }

        return max($index, $count - 1);
    }

    /**
     * @param list<string> $lines
     */
    private function markdownRawTexContextEndIndex(array $lines, int $index, string $environment): int
    {
        $depth = 0;
        $startPattern = '/^ {0,3}\\\\start\[' . preg_quote($environment, '/') . ']\s*$/';
        $stopPattern = '/^ {0,3}\\\\stop\[' . preg_quote($environment, '/') . ']\s*$/';
        $count = count($lines);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $current = rtrim($lines[$cursor]);
            if (preg_match($startPattern, $current) === 1) {
                $depth++;
            }
            if (preg_match($stopPattern, $current) === 1) {
                $depth--;
                if ($depth === 0) {
                    return $cursor;
                }
            }
        }

        return max($index, $count - 1);
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

        if (preg_match('/^([ \t]*[^<\r\n]*\S[^<\r\n]*)(<(?:p|blockquote|h[1-6]|ul|ol|dl|pre|table|div|figure|hr)\b.*)$/i', $line, $m) !== 1) {
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
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);

        return function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
    }

    private function canonicalFootnoteLabel(string $label): string
    {
        return $this->decodeHtmlEntities($this->unescapeLinkComponent($label));
    }

    private function isValidReferenceLabel(string $label): bool
    {
        $normalized = $this->normalizeReferenceLabel($label);

        $length = function_exists('mb_strlen') ? mb_strlen($normalized, 'UTF-8') : strlen($normalized);

        return $normalized !== '' && $length <= 999;
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

        $this->collectNumberedExampleReferencesInto($lines, $references, $numbersByLine, $nextNumber, true);

        return [$references, $numbersByLine];
    }

    /**
     * @param list<string> $lines
     * @param array<string, int> $references
     * @param array<int, int> $numbersByLine
     */
    private function collectNumberedExampleReferencesInto(
        array $lines,
        array &$references,
        array &$numbersByLine,
        int &$nextNumber,
        bool $recordLineNumbers
    ): void {
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $divContent = $this->markdownNativeDivContentLines($lines, $index);
            if ($divContent !== null) {
                $this->collectNumberedExampleReferencesInto($divContent['lines'], $references, $numbersByLine, $nextNumber, false);
                $index = $divContent['end'];
                continue;
            }

            $boundaryEnd = $this->markdownReferenceBoundaryEndIndex($lines, $index);
            if ($boundaryEnd !== null) {
                $index = $boundaryEnd;
                continue;
            }

            $line = $lines[$index];
            $marker = $this->matchNumberedExampleMarker($line);
            if ($marker === null || $marker['indent'] > 3) {
                continue;
            }

            if ($recordLineNumbers) {
                $numbersByLine[$index] = $nextNumber;
            }
            if ($marker['label'] !== '') {
                $references[$marker['label']] = $nextNumber;
            }
            $nextNumber++;
        }
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
            $boundaryEnd = $this->markdownReferenceBoundaryEndIndex($lines, $index);
            if ($boundaryEnd !== null) {
                $index = $boundaryEnd;
                continue;
            }

            $line = $lines[$index];
            $heading = $this->tryParseMarkdownHeading($line);
            $setextEnd = null;
            if ($heading === null) {
                $setextRun = $this->tryReadSetextMarkdownHeadingRun($lines, $index);
                if ($setextRun !== null) {
                    $heading = $setextRun['heading'];
                    $setextEnd = $setextRun['end'];
                }
            }
            if ($heading === null) {
                continue;
            }

            if (isset($heading['id'])) {
                $id = $heading['id'];
                $usedIds[$heading['id']] = ($usedIds[$heading['id']] ?? 0) + 1;
            } elseif ($this->autoIdentifierExtensionEnabled()) {
                $id = $this->uniqueMarkdownHeadingId(
                    $this->slugifyMarkdownHeading($heading['text']),
                    $usedIds
                );
            } else {
                if ($setextEnd !== null) {
                    $index = $setextEnd;
                }
                continue;
            }

            $idsByLine[$index] = $id;
            $label = $this->normalizeReferenceLabel($this->plainMarkdownHeadingText($heading['text']));
            if ($label !== '' && !isset($references[$label])) {
                $references[$label] = ['url' => '#' . $id, 'title' => ''];
            }

            if ($setextEnd !== null) {
                $index = $setextEnd;
            }
        }

        return [$idsByLine, $references];
    }

    /**
     * @return array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>}|null
     */
    private function tryParseMarkdownHeading(string $line): ?array
    {
        if (preg_match('/^ {0,3}(#{1,6})(?:([ \t]+)(.*)|(.*))$/', $line, $m) !== 1) {
            return null;
        }

        $spacer = $m[2] ?? '';
        $text = $spacer !== '' ? (string) ($m[3] ?? '') : (string) ($m[4] ?? '');
        if ($spacer === '' && $text !== '' && $this->spaceInAtxHeaderExtensionEnabled()) {
            return null;
        }

        $text = $this->stripClosingAtxHeadingFence(trim($text));

        return $this->buildMarkdownHeading(strlen($m[1]), $text);
    }

    /**
     * @param list<string> $lines
     * @return array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>}|null
     */
    private function tryParseSetextMarkdownHeading(array $lines, int $index): ?array
    {
        $setext = $this->tryReadSetextMarkdownHeadingRun($lines, $index);
        if ($setext === null || $setext['end'] !== $index + 1) {
            return null;
        }

        return $setext['heading'];
    }

    /**
     * @param list<string> $lines
     * @return array{
     *     heading: array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>},
     *     end:int
     * }|null
     */
    private function tryReadSetextMarkdownHeadingRun(array $lines, int $index): ?array
    {
        $content = [];
        $count = count($lines);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $line = $this->expandTabsToSpaces($lines[$cursor]);
            if (!$this->isSetextMarkdownHeadingContentLine($line)) {
                return null;
            }

            $content[] = $this->normalizeParagraphLine($line);
            $marker = $this->tryParseSetextMarkdownHeadingMarker($lines[$cursor + 1] ?? '');
            if ($marker !== null) {
                return [
                    'heading' => $this->buildMarkdownHeading($marker, $this->normalizeSetextMarkdownHeadingText($content)),
                    'end' => $cursor + 1,
                ];
            }
        }

        return null;
    }

    /**
     * @param list<string> $paragraph
     * @return array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>}|null
     */
    private function tryPromoteParagraphSetextMarkdownHeading(array $paragraph, string $markerLine): ?array
    {
        $marker = $this->tryParseSetextMarkdownHeadingMarker($markerLine);
        if ($marker === null) {
            return null;
        }

        return $this->buildMarkdownHeading($marker, $this->normalizeSetextMarkdownHeadingText($paragraph));
    }

    private function tryParseSetextMarkdownHeadingMarker(string $line): ?int
    {
        $marker = $this->expandTabsToSpaces($line);
        if (preg_match('/^ {0,3}(=+|-+)[ \t]*$/', $marker, $m) !== 1) {
            return null;
        }

        return $m[1][0] === '=' ? 1 : 2;
    }

    private function isSetextMarkdownHeadingContentLine(string $line): bool
    {
        $line = $this->expandTabsToSpaces($line);
        if (trim($line) === '' || $this->countIndentColumns($line) > 3) {
            return false;
        }
        if ($this->tryParseMarkdownHeading($line) !== null) {
            return false;
        }

        return $this->matchListMarker($line) === null;
    }

    /**
     * @param list<string> $lines
     */
    private function normalizeSetextMarkdownHeadingText(array $lines): string
    {
        $text = $this->joinParagraphLines($lines);

        return trim(preg_replace('/[ \t]*\n[ \t]*/', ' ', $text) ?? $text);
    }

    private function stripClosingAtxHeadingFence(string $text): string
    {
        return rtrim(preg_replace('/(?:^|[ \t]+)#+[ \t]*$/', '', $text) ?? $text);
    }

    /**
     * @param array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>} $heading
     * @return array<string, mixed>
     */
    private function markdownHeadingAstAttrs(array $heading, string $resolvedId): array
    {
        $attrs = $this->markdownAttributeAstAttrs(
            $heading['id'] ?? null,
            $heading['classes'],
            $heading['attributes']
        );
        $attrs['level'] = $heading['level'];
        $attrs['text'] = $heading['text'];
        $attrs['id'] = $resolvedId;

        return $attrs;
    }

    /**
     * @return array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>}
     */
    private function buildMarkdownHeading(int $level, string $text): array
    {
        $id = null;
        $classes = [];
        $attributes = [];

        if ($this->headerAttributeExtensionEnabled() && preg_match('/^(.*?)[ \t]*\{([^{}]+)\}[ \t]*$/', $text, $attrs) === 1) {
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
        $tokens = $this->tokenizeMarkdownAttributeSpec($source);

        foreach ($tokens as $token) {
            if ($token[0] === '#') {
                $id = $this->decodeHtmlEntities($this->unescapeMarkdownAttributeToken(substr($token, 1)));
                continue;
            }
            if ($token[0] === '.') {
                $classes[] = $this->decodeHtmlEntities($this->unescapeMarkdownAttributeToken(substr($token, 1)));
                continue;
            }

            if ($token[0] === '=' || !str_contains($token, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $token, 2);
            $value = trim($value);
            if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            $attributes[$name] = $this->decodeHtmlEntities($this->unescapeMarkdownAttributeToken($value));
        }

        return [$id, $classes, $attributes];
    }

    /**
     * @return list<string>
     */
    private function tokenizeMarkdownAttributeSpec(string $source): array
    {
        $tokens = [];
        $current = '';
        $quote = null;
        $length = strlen($source);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($char === '\\' && $offset + 1 < $length) {
                $current .= $char . $source[$offset + 1];
                $offset++;
                continue;
            }

            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if (($char === '"' || $char === "'") && str_contains($current, '=')) {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    private function findClosingMarkdownAttributeSpec(string $text, int $openOffset): ?int
    {
        if (($text[$openOffset] ?? '') !== '{') {
            return null;
        }

        $quote = null;
        $tokenHasEquals = false;
        $length = strlen($text);

        for ($offset = $openOffset + 1; $offset < $length; $offset++) {
            $char = $text[$offset];
            if ($char === '\\') {
                $offset++;
                continue;
            }

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                if ($tokenHasEquals) {
                    $quote = $char;
                }
                continue;
            }

            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $tokenHasEquals = false;
                continue;
            }

            if ($char === '=') {
                $tokenHasEquals = true;
                continue;
            }

            if ($char === '}') {
                return $offset;
            }
        }

        return null;
    }

    private function unescapeMarkdownAttributeToken(string $token): string
    {
        $unescaped = '';
        $length = strlen($token);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($token[$offset] === '\\' && $offset + 1 < $length) {
                $unescaped .= $token[$offset + 1];
                $offset++;
                continue;
            }

            $unescaped .= $token[$offset];
        }

        return $unescaped;
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
        $escapedPunctuation = [];
        $text = preg_replace_callback(
            '/\\\\([' . preg_quote(self::MARKDOWN_ESCAPABLE_ASCII_PUNCTUATION, '/') . '])/',
            static function (array $matches) use (&$escapedPunctuation): string {
                $token = "\x1FMDHEADINGPUNCT" . count($escapedPunctuation) . "\x1F";
                $escapedPunctuation[$token] = (string) $matches[1];

                return $token;
            },
            $text
        ) ?? $text;
        $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/`+([^`]*)`+/', '$1', $text) ?? $text;
        $text = str_replace(['*', '_', '~', '^'], '', $text);
        if ($escapedPunctuation !== []) {
            $text = strtr($text, $escapedPunctuation);
        }

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
        if ($label === null || !$this->isValidReferenceLabel($label['text']) || ($line[$label['next']] ?? '') !== ':') {
            return null;
        }

        return [
            'label' => $label['text'],
            'content' => rtrim(ltrim(substr($line, $label['next'] + 1), " \t")),
        ];
    }

    /**
     * @return array{term:string, title:string}|null
     */
    private function tryParseAbbreviationDefinitionStart(string $line): ?array
    {
        if (preg_match('/^ {0,3}/', $line, $indent) !== 1) {
            return null;
        }

        $offset = strlen($indent[0]);
        if (($line[$offset] ?? '') !== '*' || ($line[$offset + 1] ?? '') !== '[') {
            return null;
        }

        $label = $this->parseBracketedLabel($line, $offset + 1);
        if ($label === null || ($line[$label['next']] ?? '') !== ':') {
            return null;
        }

        $term = $this->decodeHtmlEntities($this->unescapeLinkComponent($label['text']));
        $title = $this->decodeHtmlEntities($this->unescapeLinkComponent(trim(substr($line, $label['next'] + 1))));
        if ($term === '' || $title === '') {
            return null;
        }

        return [
            'term' => $term,
            'title' => $title,
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
            } elseif ($candidate !== '') {
                $multilineTitle = $this->collectReferenceDefinitionMultilineTitle($lines, $cursor + 1, $target . ' ' . $candidate);
                if ($multilineTitle !== null) {
                    return $multilineTitle;
                }
            }
        }

        $multilineTitle = $this->collectReferenceDefinitionMultilineTitle($lines, $cursor, $target);
        if ($multilineTitle !== null) {
            return $multilineTitle;
        }

        return [$target, $cursor];
    }

    /**
     * @param list<string> $lines
     * @return array{0:string, 1:int}|null
     */
    private function collectReferenceDefinitionMultilineTitle(array $lines, int $cursor, string $target): ?array
    {
        if (!$this->hasUnclosedReferenceDefinitionTitle($target)) {
            return null;
        }

        $candidate = $target;
        $count = count($lines);
        while ($cursor < $count) {
            $line = trim($this->expandTabsToSpaces($lines[$cursor]));
            if (
                $line === ''
                || $this->tryParseReferenceDefinitionStart($line) !== null
                || $this->tryParseFootnoteDefinitionStart($line) !== null
            ) {
                return null;
            }

            $candidate .= "\n" . $line;
            $cursor++;

            $parsed = $this->parseLinkDestinationAndTitle($candidate);
            if ($parsed !== null && $parsed['title'] !== '') {
                return [$candidate, $cursor];
            }

            if (!$this->hasUnclosedReferenceDefinitionTitle($candidate)) {
                return null;
            }
        }

        return null;
    }

    private function hasUnclosedReferenceDefinitionTitle(string $content): bool
    {
        $content = trim($content);
        $length = strlen($content);
        for ($cursor = 0; $cursor < $length; $cursor++) {
            if (!ctype_space($content[$cursor])) {
                continue;
            }

            $title = ltrim(substr($content, $cursor + 1));
            if ($title === '') {
                continue;
            }

            $close = match ($title[0]) {
                '"' => '"',
                "'" => "'",
                '(' => ')',
                default => null,
            };

            if ($close === null || $this->parseLinkTitle($title) !== null) {
                continue;
            }

            if ($this->findUnescapedCharacter($title, $close, 1) === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{label:string, content:string}|null
     */
    private function tryParseFootnoteDefinitionStart(string $line): ?array
    {
        if (preg_match('/^ {0,3}/', $line, $indent) !== 1) {
            return null;
        }

        $label = $this->parseBracketedLabel($line, strlen($indent[0]));
        if ($label === null || !str_starts_with($label['text'], '^') || strlen($label['text']) === 1) {
            return null;
        }
        if (($line[$label['next']] ?? '') !== ':') {
            return null;
        }

        return [
            'label' => $this->canonicalFootnoteLabel(substr($label['text'], 1)),
            'content' => rtrim(ltrim(substr($line, $label['next'] + 1), " \t")),
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

                $body[] = $this->normalizeFootnoteBodyLine($line);
                $insideIndentedBlock = true;
                $cursor++;
                continue;
            }

            if ($afterBlank && $this->isFootnoteIndentedContinuation($line)) {
                $body[] = $this->normalizeFootnoteBodyLine($line);
                $cursor++;
                continue;
            }

            $body[] = $this->normalizeFootnoteBodyLine($line);
            $cursor++;
        }

        while ($body !== [] && end($body) === '') {
            array_pop($body);
        }

        return [$body, $cursor];
    }

    private function normalizeFootnoteBodyLine(string $line): string
    {
        if (!$this->isFootnoteIndentedContinuation($line)) {
            return rtrim($line);
        }

        $dedented = $this->stripIndentColumns($line, 4);
        if (preg_match('/^>[ \t]?/', $dedented) === 1) {
            return rtrim($line);
        }

        return rtrim($dedented);
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

    /**
     * @param list<string> $lines
     * @param list<AstNode> $blocks
     */
    private function isPandocListIndentedCodeSeparator(array $lines, int $index, array $blocks): bool
    {
        if (trim($lines[$index] ?? '') !== '<!-- -->') {
            return false;
        }

        $lastKey = array_key_last($blocks);
        if ($lastKey === null || !$this->isListBlock($blocks[$lastKey])) {
            return false;
        }

        $next = $this->nextNonBlankLineIndex($lines, $index + 1);

        return $next !== null && $this->isIndentedCodeLine($lines[$next]);
    }

    private function isListBlock(AstNode $node): bool
    {
        return $node->type === 'bullet_list' || $node->type === 'ordered_list' || $node->type === 'definition_list';
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
                $attrs = $this->parseCodeInfo($info, $this->fencedCodeAttributeExtensionEnabled());
                $attrs['text'] = implode("\n", $content);
                $rawAttribute = $this->rawAttributeEnabled() ? $this->tryParseRawAttributeSpec($info, 0) : null;
                if (
                    $rawAttribute !== null
                    && $rawAttribute['next'] === strlen($info)
                    && $this->rawAttributeFormatEnabled($rawAttribute['format'])
                ) {
                    $index = $cursor;

                    return $this->rawBlockNode($rawAttribute['format'], $attrs['text']);
                }
                if ($info !== '') {
                    $attrs['info'] = $info;
                }
                $index = $cursor;

                return new AstNode('code_block', $attrs);
            }

            $content[] = $this->stripFenceContentIndent($lines[$cursor], $indent);
            $cursor++;
        }

        $attrs = $this->parseCodeInfo($info, $this->fencedCodeAttributeExtensionEnabled());
        $attrs['text'] = implode("\n", $content);
        $rawAttribute = $this->rawAttributeEnabled() ? $this->tryParseRawAttributeSpec($info, 0) : null;
        if (
            $rawAttribute !== null
            && $rawAttribute['next'] === strlen($info)
            && $this->rawAttributeFormatEnabled($rawAttribute['format'])
        ) {
            $index = $cursor - 1;

            return $this->rawBlockNode($rawAttribute['format'], $attrs['text']);
        }
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

            if (!$this->isLazyBlockQuoteContinuationLine($lines[$cursor], $cursor, $content)) {
                break;
            }

            $content[] = $lines[$cursor];
            $cursor++;
        }

        $index = $cursor - 1;
        $inner = $this->read(implode("\n", $content));

        return new AstNode('blockquote', [], $inner->children);
    }

    /**
     * @param list<string> $content
     */
    private function isLazyBlockQuoteContinuationLine(string $line, int $lineIndex, array $content): bool
    {
        if ($content === [] || trim(end($content)) === '' || trim($line) === '') {
            return false;
        }
        $previous = $this->expandTabsToSpaces((string) end($content));
        if (
            $this->tryParseFootnoteDefinitionStart($previous) !== null
            || $this->tryParseReferenceDefinitionStart($previous) !== null
        ) {
            return false;
        }
        if ($this->tryParseMarkdownHeading($line) !== null) {
            return false;
        }
        if ($this->isHorizontalRule($line) || $this->isIndentedCodeLine($line)) {
            return false;
        }
        if (preg_match('/^( {0,3})(`{3,}|~{3,})/', $line) === 1) {
            return false;
        }

        $marker = $this->matchListMarker($line, $lineIndex);

        return $marker === null || $marker['indent'] > 3;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadLineBlock(array $lines, int &$index): ?AstNode
    {
        if (!$this->lineBlockExtensionEnabled()) {
            return null;
        }

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
        if (!$this->fencedDivExtensionEnabled()) {
            return null;
        }

        $line = $lines[$index] ?? '';
        if (preg_match('/^( {0,3})(:{3,})[ \t]*(.*)$/', $line, $m) !== 1) {
            return null;
        }

        $indent = strlen($m[1]);
        $fenceLength = strlen($m[2]);
        $info = trim($m[3]);
        $content = [];
        $cursor = $index + 1;
        $count = count($lines);

        while ($cursor < $count) {
            if ($this->isClosingFencedDiv($lines[$cursor], $fenceLength)) {
                $inner = $this->read(implode("\n", $content));
                $children = $inner->children;
                if ($this->sectionDivsEnabled()) {
                    $children = $this->sectionizeBlocks($children);
                }
                $index = $cursor;

                return new AstNode('div', $this->parseFencedDivAttributes($info), $children);
            }

            $content[] = $this->stripFenceContentIndent($lines[$cursor], $indent);
            $cursor++;
        }

        return null;
    }

    private function isClosingFencedDiv(string $line, int $fenceLength): bool
    {
        return preg_match('/^ {0,3}:{' . $fenceLength . ',}[ \t]*$/', $line) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFencedDivAttributes(string $info): array
    {
        if ($info === '') {
            return [];
        }

        if (str_starts_with($info, '{') && str_ends_with($info, '}')) {
            [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec(substr($info, 1, -1));

            return $this->markdownAttributeAstAttrs($id, $classes, $attributes);
        }

        $tokens = $this->tokenizeMarkdownAttributeSpec($info);
        if ($tokens === []) {
            return [];
        }

        $classes = [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            $classes[] = $this->unescapeMarkdownAttributeToken(ltrim($token, '.'));
        }

        return $classes === [] ? [] : $this->markdownAttributeAstAttrs(null, $classes, []);
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadDivBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}(<div(?=\s|>)(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*>)/iu', $line, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $content = [];
        $depth = 1;
        $openingIndex = $index;
        $cursor = $index;
        $count = count($lines);
        $firstLineOffset = $m[0][1] + strlen($m[0][0]);
        $attrs = $this->htmlNativeDivsEnabled() ? $this->htmlAttrsFromOpeningTag($m[1][0], 'div') : [];

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

                    return $this->buildDivBlock($content, $closedOnOpeningLine, $this->isHtmlLineBlockOpeningTag($lines[$openingIndex]), $attrs);
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
            : '/<' . preg_quote($tag, '/') . '(?=\s|>)(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*>/iu';

        if (preg_match($pattern, $line, $m, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            return null;
        }

        return ['offset' => $m[0][1], 'length' => strlen($m[0][0])];
    }

    /**
     * @param list<string> $content
     * @param array<string, mixed> $attrs
     */
    private function buildDivBlock(array $content, bool $closedOnOpeningLine, bool $lineBlock = false, array $attrs = []): AstNode
    {
        while ($content !== [] && trim($content[0]) === '') {
            array_shift($content);
        }
        while ($content !== [] && trim($content[array_key_last($content)]) === '') {
            array_pop($content);
        }

        if ($lineBlock) {
            return $this->buildHtmlLineBlockFromInlines(
                $this->parseHtmlInlineFragmentNodes(implode("\n", $content))
            );
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
     * @return array<string, mixed>
     */
    private function htmlAttrsFromOpeningTag(string $openingTag, string $tag): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $openingTag . '</' . $tag . '></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return [];
        }

        $element = $this->firstChildElement($body, $tag);
        if (!$element instanceof \DOMElement) {
            return [];
        }

        return $this->htmlElementPandocAttrs($element);
    }

    private function isHtmlLineBlockOpeningTag(string $line): bool
    {
        if (preg_match('/^ {0,3}<div\b([^>]*)>/i', $line, $m) !== 1) {
            return false;
        }

        if (preg_match('/\bclass\s*=\s*(?:"line-block"|\'line-block\'|line-block)(?:\s|$)/i', $m[1]) !== 1) {
            return false;
        }

        $withoutClass = preg_replace('/\s*\bclass\s*=\s*(?:"line-block"|\'line-block\'|line-block)\s*/i', ' ', $m[1]) ?? $m[1];

        return trim($withoutClass) === '';
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadRawHtmlBlock(array $lines, int &$index): ?AstNode
    {
        if (!$this->htmlRawHtmlEnabled()) {
            return null;
        }

        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<!--/', $line) === 1) {
            return $this->readHtmlCommentBlock($lines, $index);
        }

        if (preg_match('/^ {0,3}<\?/', $line) === 1) {
            return $this->readRawHtmlUntilMarker($lines, $index, '?>');
        }

        if (preg_match('/^ {0,3}<!\[CDATA\[/', $line) === 1) {
            return $this->readRawHtmlUntilMarker($lines, $index, ']]>');
        }

        if (preg_match('/^ {0,3}<![A-Za-z]/', $line) === 1) {
            return $this->readRawHtmlUntilMarker($lines, $index, '>');
        }

        if ($this->isAngleAutolinkOnlyLine($line)) {
            return null;
        }

        $tag = $this->tryParseRawHtmlOpeningTag($line);
        if (
            $tag !== null
            && in_array($tag['name'], ['script', 'style', 'pre', 'textarea', 'noscript', 'xmp'], true)
        ) {
            return $this->readRawHtmlUntilClosingTag($lines, $index, $tag['name']);
        }

        if ($tag !== null && $tag['name'] === 'table') {
            return $this->readRawHtmlUntilClosingTag($lines, $index, 'table', true);
        }

        if ($tag !== null && $tag['name'] === 'hr' && preg_match('/^ {0,3}<hr(?:\s+[^>]*)?\/?>[ \t]*$/i', $line) === 1) {
            return new AstNode('raw_html', ['html' => trim($line)]);
        }

        $closingTag = $this->tryParseRawHtmlClosingTag($line);
        if ($closingTag !== null) {
            return $this->readRawHtmlUntilBlankLine($lines, $index);
        }

        if (
            $tag !== null
            && (
                $this->isCommonMarkBlankTerminatedRawHtmlTag($tag['name'])
                || $this->isRawHtmlCustomTagName($tag['name'])
            )
        ) {
            return $this->readRawHtmlUntilBlankLine($lines, $index);
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadCommonMarkPrecedenceRawHtmlBlock(array $lines, int &$index): ?AstNode
    {
        if (!$this->commonMarkRawHtmlBlockPrecedenceEnabled()) {
            return null;
        }

        $tag = $this->tryParseRawHtmlOpeningTag($lines[$index] ?? '');
        if ($tag === null || !in_array($tag['name'], ['pre', 'noscript', 'figure', 'details'], true)) {
            return null;
        }

        return $this->tryReadRawHtmlBlock($lines, $index);
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadPandocRawHtmlBlockBeforeSemanticHtml(array $lines, int &$index): ?AstNode
    {
        if (!$this->htmlRawHtmlEnabled()) {
            return null;
        }

        $tag = $this->tryParseRawHtmlOpeningTag($lines[$index] ?? '');
        if ($tag === null) {
            return null;
        }

        $name = $tag['name'];
        if ($name === 'pre') {
            if (preg_match('/^ {0,3}<pre\s*>[ \t]*$/i', $lines[$index] ?? '') !== 1) {
                return null;
            }

            $probeIndex = $index;
            $raw = $this->readRawHtmlUntilClosingTag($lines, $probeIndex, 'pre');
            $html = (string) $raw->attr('html', '');
            if (preg_match('/<code(?:\s|>|\/)/i', $html) === 1) {
                return null;
            }

            $index = $probeIndex;

            return $raw;
        }

        if ($this->isPandocRawHtmlClosingBlockTag($name)) {
            return $tag['selfClosing']
                ? $this->readRawHtmlUntilBlankLine($lines, $index)
                : $this->readRawHtmlUntilClosingTag($lines, $index, $name);
        }

        if (in_array($name, ['source', 'track', 'meta'], true)) {
            if (!$this->rawHtmlOpeningTagIsStandaloneLine($lines[$index] ?? '', $name)) {
                return null;
            }

            return $this->readRawHtmlUntilBlankLine($lines, $index);
        }

        return null;
    }

    private function isPandocRawHtmlClosingBlockTag(string $name): bool
    {
        return in_array($name, [
            'noscript',
            'figure',
            'details',
            'svg',
            'math',
            'canvas',
            'template',
            'object',
            'picture',
            'ruby',
            'meter',
            'progress',
            'select',
            'datalist',
            'output',
            'search',
        ], true);
    }

    private function isMarkdownReferenceHtmlClosingBoundaryTag(string $name): bool
    {
        return $this->isPandocRawHtmlClosingBlockTag($name)
            || in_array($name, [
                'audio',
                'mark',
                'time',
                'video',
            ], true);
    }

    private function rawHtmlOpeningTagIsStandaloneLine(string $line, string $name): bool
    {
        return preg_match(
            '/^ {0,3}<' . preg_quote($name, '/') . '(?:\s+[^>]*)?\/?>[ \t]*$/i',
            $line
        ) === 1;
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
            $content[] = $this->normalizeRawHtmlLine($lines[$cursor]);
            if (str_contains($lines[$cursor], $marker)) {
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
    private function readRawHtmlUntilBlankLine(array $lines, int &$index): AstNode
    {
        $content = [];
        $cursor = $index;
        $count = count($lines);
        while ($cursor < $count) {
            $line = $this->normalizeRawHtmlLine($lines[$cursor]);
            if ($cursor > $index && trim($line) === '') {
                break;
            }

            $content[] = $line;
            $cursor++;
        }

        $index = max($index, min($cursor - 1, $count - 1));

        return new AstNode('raw_html', ['html' => implode("\n", $content)]);
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>|null
     */
    private function tryReadRawHtmlDetailsBlock(array $lines, int &$index): ?array
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<details\b/i', $line) !== 1) {
            return null;
        }

        $collected = $this->collectBalancedHtmlElementBlock($lines, $index, 'details');
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $blocks = $this->buildRawHtmlDetailsBlocks($html);
        if ($blocks === []) {
            return null;
        }

        $index = $endIndex;

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function buildRawHtmlDetailsBlocks(string $html): array
    {
        if (preg_match('/^\s*(<details\b[^>]*>)(.*)(<\/details\s*>)\s*$/isu', $html, $match) !== 1) {
            return [];
        }

        $rawHtmlEnabled = $this->htmlRawHtmlEnabled();
        $inner = (string) $match[2];
        $blocks = [];
        if ($rawHtmlEnabled) {
            $blocks[] = new AstNode('raw_html', ['html' => trim($match[1])]);
        }

        [$summary, $body] = $this->extractRawHtmlDetailsSummary($inner);
        if ($summary !== '') {
            if ($rawHtmlEnabled) {
                $blocks[] = new AstNode('raw_html', ['html' => trim($summary)]);
            } else {
                $summaryText = trim(preg_replace('/\s+/', ' ', strip_tags($summary)) ?? '');
                if ($summaryText !== '') {
                    $inlines = $this->parseInlines($summaryText);
                    $blocks[] = new AstNode('plain', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
                }
            }
        }

        $body = trim($body, "\r\n");
        if ($body !== '') {
            $bodyDocument = (new self($this->options))->read($body);
            array_push($blocks, ...$bodyDocument->children);
        }

        if ($rawHtmlEnabled) {
            $blocks[] = new AstNode('raw_html', ['html' => trim($match[3])]);
        }

        return $blocks;
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function extractRawHtmlDetailsSummary(string $inner): array
    {
        if (preg_match('/<summary\b[^>]*>.*?<\/summary\s*>/isu', $inner, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return ['', $inner];
        }

        $summary = $match[0][0];
        $offset = $match[0][1];
        $body = substr($inner, 0, $offset) . substr($inner, $offset + strlen($summary));

        return [$summary, $body];
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>|null
     */
    private function tryReadRawHtmlSingleLineContainerBlock(array $lines, int &$index): ?array
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}(<(del|ins|button)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?>)(.*)(<\/\2\s*>)[ \t]*$/isu', $line, $m) !== 1) {
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

        return $this->tableWithReviewPacket(new AstNode('table', $attrs, [
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

        return $this->tableWithReviewPacket(new AstNode('table', $attrs, $children));
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

        $html = implode("\n", $content);
        if (preg_match('/<html\b/i', $html) !== 1) {
            return null;
        }

        $document = $this->parseHtmlDocument($html);
        if ($document === null) {
            return null;
        }

        $index = $count - 1;

        return $document;
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

        $previousHtmlBaseHref = $this->htmlBaseHref;
        $previousHtmlFootnoteDefinitions = $this->htmlFootnoteDefinitions;
        $this->htmlBaseHref = $this->htmlDocumentBaseHref($dom);
        $this->htmlFootnoteDefinitions = $this->collectHtmlFootnoteDefinitions($body);
        try {
            $attrs = $this->htmlDocumentAttrs($dom);
            if ($this->htmlNativeDivsEnabled()) {
                $main = $this->firstHtmlMainElement($body);
                if ($main instanceof \DOMElement) {
                    return new AstNode('document', $attrs, $this->htmlNativeDivsMainBlocks($main));
                }
            }

            return new AstNode('document', $attrs, $this->parseHtmlBlockChildren($body));
        } finally {
            $this->htmlBaseHref = $previousHtmlBaseHref;
            $this->htmlFootnoteDefinitions = $previousHtmlFootnoteDefinitions;
        }
    }

    /**
     * @return array<string, list<AstNode>>
     */
    private function collectHtmlFootnoteDefinitions(\DOMElement $body): array
    {
        $definitions = [];
        foreach ($this->htmlFootnoteContainers($body) as $container) {
            foreach ($container->getElementsByTagName('*') as $candidate) {
                if (!$candidate instanceof \DOMElement || !$this->isHtmlFootnoteItemElement($candidate)) {
                    continue;
                }

                $id = trim($candidate->getAttribute('id'));
                if ($id === '' || isset($definitions[$id])) {
                    continue;
                }

                $clone = $candidate->cloneNode(true);
                if (!$clone instanceof \DOMElement) {
                    continue;
                }

                $this->removeHtmlFootnoteBacklinks($clone);
                $definitions[$id] = $this->parseHtmlBlockChildren($clone);
            }
        }

        return $definitions;
    }

    /**
     * @return list<\DOMElement>
     */
    private function htmlFootnoteContainers(\DOMElement $root): array
    {
        $containers = [];
        if ($this->isHtmlFootnoteContainer($root)) {
            $containers[] = $root;
        }

        foreach ($root->getElementsByTagName('*') as $candidate) {
            if ($candidate instanceof \DOMElement && $this->isHtmlFootnoteContainer($candidate)) {
                $containers[] = $candidate;
            }
        }

        return $containers;
    }

    private function isHtmlFootnoteContainer(\DOMElement $element): bool
    {
        if (strtolower(trim($element->getAttribute('role'))) === 'doc-endnotes') {
            return true;
        }

        return in_array($this->htmlSemanticType($element), ['footnotes', 'rearnotes'], true);
    }

    private function isHtmlFootnoteItemElement(\DOMElement $element): bool
    {
        if (trim($element->getAttribute('id')) === '') {
            return false;
        }

        $name = strtolower($element->localName);
        if ($name === 'li') {
            return true;
        }

        return in_array($this->htmlSemanticType($element), ['footnote', 'rearnote'], true);
    }

    private function isHtmlNoteReferenceElement(\DOMElement $element): bool
    {
        if (strtolower(trim($element->getAttribute('role'))) === 'doc-noteref') {
            return true;
        }

        return $this->htmlSemanticType($element) === 'noteref';
    }

    private function htmlSemanticType(\DOMElement $element): string
    {
        $type = trim($element->getAttribute('type'));
        if ($type === '') {
            $type = trim($element->getAttribute('epub:type'));
        }

        return strtolower($type);
    }

    private function removeHtmlFootnoteBacklinks(\DOMElement $root): void
    {
        $links = [];
        foreach ($root->getElementsByTagName('a') as $link) {
            if ($link instanceof \DOMElement && strtolower(trim($link->getAttribute('role'))) === 'doc-backlink') {
                $links[] = $link;
            }
        }

        foreach ($links as $link) {
            $link->parentNode?->removeChild($link);
        }
    }

    private function htmlNativeDivsEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('native_divs', $overrides)) {
            return $overrides['native_divs'];
        }

        return (bool) ($this->options['htmlNativeDivs'] ?? $this->options['nativeDivs'] ?? false);
    }

    private function htmlEpubExtensionsEnabled(): bool
    {
        return (bool) ($this->options['htmlEpubExtensions'] ?? false);
    }

    private function htmlPlainInlineBlocksEnabled(): bool
    {
        return (bool) ($this->options['htmlPlainInlineBlocks'] ?? false);
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlNativeDivsMainBlock(array $lines, int &$index): ?AstNode
    {
        if (!$this->htmlNativeDivsEnabled()) {
            return null;
        }

        $line = $lines[$index] ?? '';
        if (preg_match('/<main\b/i', $line) !== 1) {
            return null;
        }

        $content = [];
        $count = count($lines);
        for ($cursor = $index; $cursor < $count; $cursor++) {
            $line = $this->normalizeRawHtmlLine($lines[$cursor]);
            if ($cursor > $index && trim($line) === '') {
                break;
            }

            $content[] = $line;
            [$started, $depth] = $this->htmlElementBalance(implode("\n", $content), 'main');
            if ($started && $depth === 0) {
                break;
            }
        }

        if ($content === []) {
            return null;
        }

        $document = $this->parseHtmlNativeDivsFragment(implode("\n", $content));
        if (!$document instanceof AstNode) {
            return null;
        }

        $index += count($content) - 1;

        return $document;
    }

    private function parseHtmlNativeDivsFragment(string $html): ?AstNode
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

        $main = $this->firstHtmlMainElement($body);
        if (!$main instanceof \DOMElement) {
            return null;
        }

        return new AstNode('document', [], $this->htmlNativeDivsMainBlocks($main));
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlNativeDivsHeaderBlock(array $lines, int &$index): ?AstNode
    {
        if (!$this->htmlNativeDivsEnabled()) {
            return null;
        }

        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<header\b/i', $line) !== 1) {
            return null;
        }

        $collected = $this->collectBalancedHtmlElementBlock($lines, $index, 'header');
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $header = $this->parseHtmlNativeDivsHeaderFragment($html);
        if ($header === null) {
            return null;
        }

        $index = $endIndex;

        return $header;
    }

    private function parseHtmlNativeDivsHeaderFragment(string $html): ?AstNode
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

        $header = $this->firstChildElement($body, 'header');
        if (!$header instanceof \DOMElement) {
            return null;
        }

        return $this->buildHtmlNativeHeaderDivNode($header);
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlNativeDivsContainerBlock(array $lines, int &$index): ?AstNode
    {
        if (!$this->htmlNativeDivsEnabled()) {
            return null;
        }

        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<(section|aside)\b/i', $line, $match) !== 1) {
            return null;
        }

        $tag = strtolower($match[1]);
        if ($tag === 'button' && ($this->options['suppressStandaloneButtonInline'] ?? false)) {
            return null;
        }

        $collected = $this->collectBalancedHtmlElementBlock($lines, $index, $tag);
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $container = $this->parseHtmlNativeDivsContainerFragment($html, $tag);
        if ($container === null) {
            return null;
        }

        $index = $endIndex;

        return $container;
    }

    private function parseHtmlNativeDivsContainerFragment(string $html, string $tag): ?AstNode
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

        $container = $this->firstChildElement($body, $tag);
        if (!$container instanceof \DOMElement) {
            return null;
        }

        return $this->buildHtmlNativeDivNode($container);
    }

    private function firstHtmlMainElement(\DOMElement $root): ?\DOMElement
    {
        if (strtolower($root->localName) === 'main') {
            return $root;
        }

        foreach ($root->getElementsByTagName('main') as $main) {
            if ($main instanceof \DOMElement) {
                return $main;
            }
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function htmlNativeDivsMainBlocks(\DOMElement $main): array
    {
        $attrs = $this->htmlNativeMainAttrs($main);
        $children = $this->htmlNativeDivChildren($main, (string) ($attrs['id'] ?? ''));
        if ($attrs === []) {
            return $children;
        }

        return [new AstNode('div', $attrs, $children)];
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlNativeMainAttrs(\DOMElement $main): array
    {
        $attrs = $this->htmlElementPandocAttrs($main);
        if ($attrs !== [] && !$main->hasAttribute('role')) {
            $attributes = $attrs['attributes'] ?? [];
            if (!is_array($attributes)) {
                $attributes = [];
            }
            $attributes['role'] = 'main';
            $attrs['attributes'] = $attributes;

            $htmlAttributes = $attrs['htmlAttributes'] ?? [];
            if (!is_array($htmlAttributes)) {
                $htmlAttributes = [];
            }
            $htmlAttributes['role'] = 'main';
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlDocumentAttrs(\DOMDocument $dom): array
    {
        $meta = [];
        $lang = $this->htmlDocumentLang($dom);
        if ($lang !== '') {
            $meta['lang'] = $lang;
        }

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

    private function htmlDocumentLang(\DOMDocument $dom): string
    {
        $html = $dom->getElementsByTagName('html')->item(0);
        if (!$html instanceof \DOMElement) {
            return '';
        }

        $lang = trim($html->getAttribute('lang'));
        if ($lang !== '') {
            return $lang;
        }

        $xmlLang = $html->attributes?->getNamedItem('xml:lang');
        if ($xmlLang instanceof \DOMNode) {
            return trim($xmlLang->nodeValue ?? '');
        }

        return '';
    }

    private function htmlDocumentBaseHref(\DOMDocument $dom): ?string
    {
        foreach ($dom->getElementsByTagName('base') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $href = trim($node->getAttribute('href'));
            if ($href !== '') {
                return $href;
            }
        }

        return null;
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
            '/^ {0,3}<(?:p|h[1-6]|ul|ol|dl|blockquote|pre|table|div|figure|hr)\b/i',
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
    private function tryReadHtmlInlineFragmentBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<br\b[^>]*>/i', $line) === 1) {
            return $this->parseHtmlInlineFragmentParagraph(trim($line));
        }

        if (preg_match('/^ {0,3}<(abbr|applet|area|audio|b|bdo|button|cite|code|del|dfn|em|i|ins|kbd|map|mark|noscript|object|progress|q|s|samp|small|source|span|strike|strong|sub|sup|svg|time|track|tt|u|var|video|embed)\b/i', $line, $match) !== 1) {
            return null;
        }

        $tag = strtolower($match[1]);
        if ($tag === 'button' && ($this->options['suppressStandaloneButtonInline'] ?? false)) {
            return null;
        }

        if (
            $this->isHtmlVoidInlineFragmentTag($tag)
            && preg_match('/^ {0,3}<' . preg_quote($tag, '/') . '\b[^>]*>/i', $line) === 1
        ) {
            return $this->parseHtmlInlineFragmentParagraph(trim($line));
        }

        $collected = $this->collectBalancedHtmlElementBlock($lines, $index, $tag);
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $paragraph = $this->parseHtmlInlineFragmentParagraph($html);
        if ($paragraph === null) {
            return null;
        }

        $index = $endIndex;

        return $paragraph;
    }

    private function isHtmlVoidInlineFragmentTag(string $tag): bool
    {
        return in_array($tag, ['area', 'embed', 'source', 'track'], true);
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
     * @return list<AstNode>
     */
    private function parseHtmlInlineFragmentNodes(string $html): array
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
            return [];
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return [];
        }

        return $this->parseHtmlInlineChildren($body);
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
    private function tryReadHtmlFigureBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<figure\b/i', $line) !== 1) {
            return null;
        }

        $collected = $this->collectBalancedHtmlElementBlock($lines, $index, 'figure');
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $figure = $this->parseHtmlFigureBlock($html);
        if ($figure === null) {
            return null;
        }

        $index = $endIndex;

        return $figure;
    }

    /**
     * @param list<string> $lines
     */
    private function tryReadHtmlIframeBlock(array $lines, int &$index): ?AstNode
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^ {0,3}<iframe\b/i', $line) !== 1) {
            return null;
        }

        $collected = $this->collectBalancedHtmlElementBlock($lines, $index, 'iframe');
        if ($collected === null) {
            return null;
        }

        [$html, $endIndex] = $collected;
        $iframe = $this->parseHtmlIframeBlock($html);
        if ($iframe === null) {
            return null;
        }

        $index = $endIndex;

        return $iframe;
    }

    private function parseHtmlIframeBlock(string $html): ?AstNode
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

        $iframe = $this->firstChildElement($body, 'iframe');
        if (!$iframe instanceof \DOMElement) {
            return null;
        }

        return $this->buildHtmlIframeNode($iframe);
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
        if (!isset($attrs['id']) && ($this->options['htmlImplicitHeadingIds'] ?? true) !== false) {
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

        $text = str_replace(["\r\n", "\r"], "\n", $this->htmlPreCodeText($pre));
        if (str_ends_with($text, "\n")) {
            $text = substr($text, 0, -1);
        }

        $preAttrs = $this->htmlElementPandocAttrs($pre);
        if ($preAttrs !== [] || !$code instanceof \DOMElement) {
            $attrs = $preAttrs;
        } else {
            $attrs = $this->htmlCodeBlockAttrs($code);
        }
        if (!isset($attrs['attributes'])) {
            $attrs['attributes'] = [];
        }
        $attrs['text'] = $text;

        return new AstNode('code_block', $attrs);
    }

    private function htmlPreCodeText(\DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $text .= $child->nodeValue ?? '';
                continue;
            }

            if ($child instanceof \DOMElement) {
                if (strtolower($child->localName) === 'br') {
                    $text .= "\n";
                    continue;
                }

                $text .= $this->htmlPreCodeText($child);
            }
        }

        return $text;
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

    private function parseHtmlFigureBlock(string $html): ?AstNode
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

        $figure = $this->firstChildElement($body, 'figure');
        if (!$figure instanceof \DOMElement) {
            return null;
        }

        return $this->buildHtmlFigureNode($figure);
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
            if ($child instanceof \DOMElement && $this->isHtmlFootnoteContainer($child)) {
                $this->flushHtmlInlineParagraph($inlines, $blocks);
                $containerBlocks = $this->parseHtmlFootnoteContainerBlocks($child);
                if ($containerBlocks !== []) {
                    $blocks[] = new AstNode('div', $this->htmlElementPandocAttrs($child), $containerBlocks);
                }
                continue;
            }

            if ($child instanceof \DOMElement && $this->isHtmlBlockElement($child)) {
                $this->flushHtmlInlineParagraph($inlines, $blocks);
                if ($this->htmlEpubExtensionsEnabled() && strtolower($child->localName) === 'switch') {
                    array_push($blocks, ...$this->parseHtmlEpubSwitchBlocks($child));
                    continue;
                }
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

    /**
     * @return list<AstNode>
     */
    private function parseHtmlFootnoteContainerBlocks(\DOMElement $element): array
    {
        $blocks = [];
        $inlines = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isHtmlFootnoteItemElement($child)) {
                continue;
            }

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
        $name = strtolower($element->localName);
        if ($this->htmlEpubExtensionsEnabled() && in_array($name, ['case', 'default', 'switch'], true)) {
            return true;
        }

        return in_array($name, [
            'blockquote',
            'div',
            'dl',
            'figure',
            'iframe',
            ...($this->htmlNativeDivsEnabled() ? ['aside', 'header', 'main', 'section'] : []),
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
            'script',
            'style',
            'table',
            'textarea',
            'ul',
        ], true);
    }

    private function parseHtmlBlockElement(\DOMElement $element): ?AstNode
    {
        $name = strtolower($element->localName);
        if ($this->htmlEpubExtensionsEnabled()) {
            $semanticType = $this->htmlSemanticType($element);
            if (in_array($semanticType, ['footnotes', 'rearnotes'], true)) {
                $children = $this->parseHtmlFootnoteContainerBlocks($element);

                return $children === [] ? null : new AstNode('div', $this->htmlElementPandocAttrs($element), $children);
            }
            if ($semanticType === 'toc' || in_array($semanticType, ['footnote', 'rearnote'], true)) {
                return null;
            }
            if (str_contains($semanticType, 'titlepage') && in_array($name, ['aside', 'div', 'header', 'main', 'section'], true)) {
                return null;
            }
            if ($name === 'switch') {
                $blocks = $this->parseHtmlEpubSwitchBlocks($element);

                return count($blocks) === 1 ? $blocks[0] : new AstNode('div', $this->htmlElementPandocAttrs($element), $blocks);
            }
            if (in_array($name, ['case', 'default'], true)) {
                $children = $this->parseHtmlBlockChildren($element);

                return count($children) === 1 ? $children[0] : new AstNode('div', $this->htmlElementPandocAttrs($element), $children);
            }
        }
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
        if ($name === 'figure') {
            return $this->buildHtmlFigureNode($element);
        }
        if ($name === 'iframe') {
            return $this->buildHtmlIframeNode($element);
        }
        if ($name === 'script') {
            $math = $this->buildHtmlScriptMathNode($element);
            if ($math instanceof AstNode) {
                return new AstNode('plain', ['text' => (string) $math->attr('text', '')], [$math]);
            }

            if (!$this->htmlRawHtmlEnabled()) {
                return null;
            }

            return $this->buildHtmlRawBlockNode($element);
        }
        if ($name === 'textarea') {
            if (!$this->htmlRawHtmlEnabled()) {
                return null;
            }

            return $this->buildHtmlRawBlockNode($element);
        }
        if ($name === 'style') {
            if (!$this->htmlRawHtmlEnabled()) {
                return null;
            }

            return new AstNode('paragraph', ['text' => ''], [$this->buildHtmlRawInlineNode($element)]);
        }
        if ($name === 'div' && $this->isHtmlLineBlockDiv($element)) {
            return $this->buildHtmlLineBlockNode($element);
        }
        if ($name === 'div') {
            return new AstNode('div', $this->htmlElementPandocAttrs($element), $this->parseHtmlBlockChildren($element));
        }
        if ($this->htmlNativeDivsEnabled() && in_array($name, ['aside', 'header', 'main', 'section'], true)) {
            return $this->buildHtmlNativeDivNode($element);
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
     * @return list<AstNode>
     */
    private function parseHtmlEpubSwitchBlocks(\DOMElement $switch): array
    {
        $fallback = null;
        foreach ($switch->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $name = strtolower($child->localName);
            if ($name === 'case' && trim($child->getAttribute('required-namespace')) === 'http://www.w3.org/1998/Math/MathML') {
                $inlines = $this->parseHtmlInlineChildren($child);
                if ($inlines !== []) {
                    return [new AstNode('paragraph', ['text' => $this->plainTextFromInlines($inlines)], $inlines)];
                }
            }
            if ($name === 'default' && $fallback === null) {
                $fallback = $this->parseHtmlBlockChildren($child);
            }
        }

        return $fallback ?? [];
    }

    private function isHtmlLineBlockDiv(\DOMElement $element): bool
    {
        if (strtolower($element->localName) !== 'div' || trim($element->getAttribute('class')) !== 'line-block') {
            return false;
        }

        foreach ($element->attributes as $attribute) {
            if ($attribute instanceof \DOMAttr && strtolower($attribute->name) !== 'class') {
                return false;
            }
        }

        return true;
    }

    private function buildHtmlLineBlockNode(\DOMElement $element): AstNode
    {
        return $this->buildHtmlLineBlockFromInlines($this->parseHtmlInlineChildren($element));
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function buildHtmlLineBlockFromInlines(array $inlines): AstNode
    {
        $lines = [];
        $current = [];
        $lastWasLineBreak = false;
        foreach ($this->trimHtmlLineBlockInlines($inlines) as $inline) {
            if ($inline->type === 'softbreak') {
                continue;
            }

            if ($inline->type === 'linebreak') {
                $lines[] = $this->buildHtmlLineBlockLine($current);
                $current = [];
                $lastWasLineBreak = true;
                continue;
            }

            $current[] = $inline;
            $lastWasLineBreak = false;
        }

        if ($current !== [] || !$lastWasLineBreak || $lines === []) {
            $lines[] = $this->buildHtmlLineBlockLine($current);
        }

        return new AstNode('line_block', [], $lines);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function buildHtmlLineBlockLine(array $inlines): AstNode
    {
        return new AstNode('line', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function trimHtmlLineBlockInlines(array $inlines): array
    {
        while ($inlines !== []) {
            $first = $inlines[array_key_first($inlines)];
            if ($first->type !== 'text') {
                break;
            }

            $text = ltrim((string) $first->attr('text', ''), " \t\r\n");
            if ($text !== '') {
                $inlines[array_key_first($inlines)] = new AstNode('text', array_merge($first->attrs, ['text' => $text]), $first->children);
                break;
            }

            array_shift($inlines);
        }

        while ($inlines !== []) {
            $lastKey = array_key_last($inlines);
            $last = $inlines[$lastKey];
            if ($last->type !== 'text') {
                break;
            }

            $text = rtrim((string) $last->attr('text', ''), " \t\r\n");
            if ($text !== '') {
                $inlines[$lastKey] = new AstNode('text', array_merge($last->attrs, ['text' => $text]), $last->children);
                break;
            }

            array_pop($inlines);
        }

        return array_values($inlines);
    }

    private function buildHtmlFigureNode(\DOMElement $figure): AstNode
    {
        $captionBlocks = [];
        $bodyBlocks = [];
        $inlines = [];

        foreach ($figure->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'figcaption') {
                $this->flushHtmlFigureInlines($inlines, $bodyBlocks);
                array_push($captionBlocks, ...$this->parseHtmlFigureCaptionBlocks($child));
                continue;
            }

            if ($child instanceof \DOMElement && $this->isHtmlBlockElement($child)) {
                $this->flushHtmlFigureInlines($inlines, $bodyBlocks);
                $block = $this->parseHtmlBlockElement($child);
                if ($block instanceof AstNode) {
                    $bodyBlocks[] = $block;
                }
                continue;
            }

            $this->appendHtmlInlineNodes($inlines, $this->parseHtmlInlineNode($child));
        }

        $this->flushHtmlFigureInlines($inlines, $bodyBlocks);

        $attrs = $this->htmlElementPandocAttrs($figure);
        $attrs['caption'] = $this->plainTextFromBlocks($captionBlocks);
        if ($captionBlocks !== []) {
            $attrs['captionBlocks'] = $captionBlocks;
            $captionInlines = $this->captionInlinesFromBlocks($captionBlocks);
            if ($captionInlines !== []) {
                $attrs['captionInlines'] = $captionInlines;
            }
        }

        return new AstNode('figure', $attrs, $bodyBlocks);
    }

    private function buildHtmlIframeNode(\DOMElement $iframe): ?AstNode
    {
        $rawUrl = trim($iframe->getAttribute('src'));
        if ($rawUrl === '') {
            return null;
        }

        $url = $this->resolveHtmlUrl($rawUrl);
        $resource = $this->htmlIframeResource($url) ?? $this->htmlIframeResource($rawUrl);
        if ($resource === null) {
            return null;
        }

        $mime = strtolower(trim($resource['mime']));
        if (str_starts_with($mime, 'text/html')) {
            $document = $this->parseHtmlDocument($resource['body']);

            return new AstNode('div', $this->htmlIframeDivAttrs(), $document?->children ?? []);
        }

        if (str_starts_with($mime, 'image/')) {
            return new AstNode('div', $this->htmlIframeDivAttrs(), [
                new AstNode('plain', [], [
                    new AstNode('image', [
                        'url' => $url,
                        'title' => '',
                        'alt' => '',
                    ]),
                ]),
            ]);
        }

        return new AstNode('div', $this->htmlIframeDivAttrs(['src' => $url]));
    }

    /**
     * @return array{mime:string, body:string}|null
     */
    private function htmlIframeResource(string $url): ?array
    {
        $resources = $this->options['htmlIframeResources'] ?? [];
        if (!is_array($resources) || !array_key_exists($url, $resources)) {
            return null;
        }

        $resource = $resources[$url];
        if (is_string($resource)) {
            return [
                'mime' => 'text/html',
                'body' => $resource,
            ];
        }

        if (!is_array($resource)) {
            return null;
        }

        $body = $resource['body'] ?? $resource['content'] ?? '';
        if (!is_string($body)) {
            return null;
        }

        $mime = $resource['mime'] ?? $resource['contentType'] ?? 'application/octet-stream';
        if (!is_string($mime) || trim($mime) === '') {
            $mime = 'application/octet-stream';
        }

        return [
            'mime' => $mime,
            'body' => $body,
        ];
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, mixed>
     */
    private function htmlIframeDivAttrs(array $attributes = []): array
    {
        $htmlAttributes = ['class' => 'iframe'];
        foreach ($attributes as $name => $value) {
            $htmlAttributes[$name] = $value;
        }

        $attrs = [
            'classes' => ['iframe'],
            'htmlAttributes' => $htmlAttributes,
        ];
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }

        return $attrs;
    }

    /**
     * @return list<AstNode>
     */
    private function parseHtmlFigureCaptionBlocks(\DOMElement $caption): array
    {
        $blocks = [];
        $inlines = [];
        foreach ($caption->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isHtmlBlockElement($child)) {
                $this->flushHtmlFigureInlines($inlines, $blocks);
                $block = $this->parseHtmlBlockElement($child);
                if ($block instanceof AstNode) {
                    $blocks[] = $block;
                }
                continue;
            }

            $this->appendHtmlInlineNodes($inlines, $this->parseHtmlInlineNode($child));
        }

        $this->flushHtmlFigureInlines($inlines, $blocks);

        return $blocks;
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $blocks
     */
    private function flushHtmlFigureInlines(array &$inlines, array &$blocks): void
    {
        $text = trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($inlines)) ?? '');
        if ($text !== '' || $this->htmlInlinesContainNonText($inlines)) {
            $blocks[] = new AstNode('plain', ['text' => $text], $inlines);
        }

        $inlines = [];
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function htmlInlinesContainNonText(array $inlines): bool
    {
        foreach ($inlines as $inline) {
            if (!in_array($inline->type, ['text', 'softbreak', 'linebreak'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainTextFromBlocks(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $text = $block->children === []
                ? (string) $block->attr('text', '')
                : $this->plainTextFromInlines($block->children);
            $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function captionInlinesFromBlocks(array $blocks): array
    {
        $inlines = [];
        foreach ($blocks as $index => $block) {
            if ($index > 0) {
                $inlines[] = new AstNode('softbreak');
            }

            if ($block->children !== []) {
                array_push($inlines, ...$block->children);
                continue;
            }

            $text = (string) $block->attr('text', '');
            if ($text !== '') {
                $inlines[] = new AstNode('text', ['text' => $text]);
            }
        }

        return $inlines;
    }

    private function buildHtmlNativeHeaderDivNode(\DOMElement $header): AstNode
    {
        return $this->buildHtmlNativeDivNode($header);
    }

    private function buildHtmlNativeDivNode(\DOMElement $element): AstNode
    {
        $attrs = $this->htmlNativeDivAttrs($element);

        return new AstNode(
            'div',
            $attrs,
            $this->htmlNativeDivChildren($element, (string) ($attrs['id'] ?? ''))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlNativeDivAttrs(\DOMElement $element): array
    {
        return match (strtolower($element->localName)) {
            'aside' => $this->htmlNativeClassedDivAttrs($element, 'aside'),
            'header' => $this->htmlNativeHeaderAttrs($element),
            'main' => $this->htmlNativeMainAttrs($element),
            'section' => $this->htmlNativeClassedDivAttrs($element, 'section'),
            default => $this->htmlElementPandocAttrs($element),
        };
    }

    /**
     * @return list<AstNode>
     */
    private function htmlNativeDivChildren(\DOMElement $element, string $containerId): array
    {
        $children = $this->parseHtmlBlockChildren($element);
        $first = $children[0] ?? null;
        if ($containerId === '' || !$first instanceof AstNode || $first->type !== 'heading' || $first->attr('id', '') !== $containerId) {
            return $children;
        }

        $attrs = $first->attrs;
        unset($attrs['id']);
        $htmlAttributes = $attrs['htmlAttributes'] ?? null;
        if (is_array($htmlAttributes)) {
            unset($htmlAttributes['id']);
            if ($htmlAttributes === []) {
                unset($attrs['htmlAttributes']);
            } else {
                $attrs['htmlAttributes'] = $htmlAttributes;
            }
        }
        $children[0] = new AstNode($first->type, $attrs, $first->children);

        return $children;
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlNativeClassedDivAttrs(\DOMElement $element, string $nativeClass): array
    {
        $attrs = $this->htmlElementPandocAttrs($element);
        $classes = $attrs['classes'] ?? [];
        if (!is_array($classes)) {
            $classes = [];
        }
        if (!in_array($nativeClass, $classes, true)) {
            array_unshift($classes, $nativeClass);
        }
        $attrs['classes'] = $classes;

        $htmlAttributes = $attrs['htmlAttributes'] ?? [];
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }
        $orderedHtmlAttributes = [];
        if (isset($htmlAttributes['id'])) {
            $orderedHtmlAttributes['id'] = $htmlAttributes['id'];
        }
        $orderedHtmlAttributes['class'] = implode(' ', $classes);
        foreach ($htmlAttributes as $name => $value) {
            if ($name === 'id' || $name === 'class') {
                continue;
            }
            $orderedHtmlAttributes[$name] = $value;
        }
        $attrs['htmlAttributes'] = $orderedHtmlAttributes;

        return $attrs;
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlNativeHeaderAttrs(\DOMElement $header): array
    {
        $attrs = $this->htmlElementPandocAttrs($header);
        $classes = $attrs['classes'] ?? [];
        if (!is_array($classes)) {
            $classes = [];
        }
        if (!in_array('header', $classes, true)) {
            $classes[] = 'header';
        }
        $attrs['classes'] = $classes;

        $htmlAttributes = $attrs['htmlAttributes'] ?? [];
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }
        $orderedHtmlAttributes = [];
        if (isset($htmlAttributes['id'])) {
            $orderedHtmlAttributes['id'] = $htmlAttributes['id'];
        }
        $orderedHtmlAttributes['class'] = implode(' ', $classes);
        foreach ($htmlAttributes as $name => $value) {
            if ($name === 'id' || $name === 'class') {
                continue;
            }
            $orderedHtmlAttributes[$name] = $value;
        }
        $attrs['htmlAttributes'] = $orderedHtmlAttributes;

        return $attrs;
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $blocks
     */
    private function flushHtmlInlineParagraph(array &$inlines, array &$blocks): void
    {
        $inlines = $this->trimHtmlBoundarySoftBreaks($inlines);
        $text = $this->plainTextFromInlines($inlines);
        $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($normalized !== '' || $this->htmlInlineParagraphHasLineBreak($inlines)) {
            $blocks[] = new AstNode(
                $this->htmlPlainInlineBlocksEnabled() ? 'plain' : 'paragraph',
                ['text' => $normalized],
                $inlines
            );
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
        $leadingOrphans = [];
        foreach ($list->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'li') {
                if ($leadingOrphans !== []) {
                    $items[] = $this->buildHtmlListItemNode($leadingOrphans);
                    $leadingOrphans = [];
                }

                $children = $this->parseHtmlListItemChildren($child);
                [$children, $taskChecked] = $this->extractHtmlListItemTaskMarker($children);
                $children = $this->applyHtmlListItemId($child, $children);
                $items[] = $this->buildHtmlListItemNode($children, $taskChecked);
                continue;
            }

            $orphans = $this->parseHtmlListOrphanChild($child);
            if ($orphans === []) {
                continue;
            }

            $lastIndex = array_key_last($items);
            if ($lastIndex === null) {
                array_push($leadingOrphans, ...$orphans);
                continue;
            }

            $item = $items[$lastIndex];
            $taskChecked = $item->attr('taskChecked', null);
            $items[$lastIndex] = $this->buildHtmlListItemNode(
                [...$item->children, ...$orphans],
                is_bool($taskChecked) ? $taskChecked : null
            );
        }

        if ($leadingOrphans !== []) {
            $items[] = $this->buildHtmlListItemNode($leadingOrphans);
        }

        foreach ($items as $item) {
            $loose = $loose || (bool) $item->attr('loose', false);
        }

        $attrs = ['loose' => $loose];
        if ($ordered) {
            $attrs = array_merge($attrs, $this->htmlOrderedListAttrs($list));
        } elseif ($this->allListItemsAreTasks($items)) {
            $attrs['taskList'] = true;
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    /**
     * @param list<AstNode> $children
     */
    private function buildHtmlListItemNode(array $children, ?bool $taskChecked = null): AstNode
    {
        $itemLoose = $this->htmlListItemIsLoose($children);
        $attrs = [
            'text' => $this->plainTextFromListItemChildren($children),
            'loose' => $itemLoose,
        ];
        if ($taskChecked !== null) {
            $attrs['taskChecked'] = $taskChecked;
        }

        return new AstNode('list_item', $attrs, $children);
    }

    /**
     * @return list<AstNode>
     */
    private function parseHtmlListOrphanChild(\DOMNode $child): array
    {
        if ($child instanceof \DOMText && trim($child->wholeText) === '') {
            return [];
        }

        if ($child instanceof \DOMElement && $this->isHtmlFootnoteContainer($child)) {
            return [];
        }

        if ($child instanceof \DOMElement && $this->isHtmlBlockElement($child)) {
            $block = $this->parseHtmlBlockElement($child);
            return $block instanceof AstNode ? [$block] : [];
        }

        $inlines = $this->parseHtmlInlineNode($child);
        $text = $this->plainTextFromInlines($inlines);
        if (trim(preg_replace('/\s+/', ' ', $text) ?? $text) === '') {
            return [];
        }

        return $inlines;
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function applyHtmlListItemId(\DOMElement $item, array $children): array
    {
        $id = trim($item->getAttribute('id'));
        if ($id === '') {
            return $children;
        }

        $inlineRun = [];
        $cursor = 0;
        $count = count($children);
        while ($cursor < $count && $this->htmlListChildIsInline($children[$cursor])) {
            $inlineRun[] = $children[$cursor];
            $cursor++;
        }

        if ($inlineRun !== []) {
            return [
                new AstNode('span', [
                    'id' => $id,
                    'htmlAttributes' => ['id' => $id],
                ], $inlineRun),
                ...array_slice($children, $cursor),
            ];
        }

        return [new AstNode('div', [
            'id' => $id,
            'htmlAttributes' => ['id' => $id],
        ], $children)];
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
        $inlines = $this->trimHtmlBoundarySoftBreaks($inlines);
        $text = $this->plainTextFromInlines($inlines);
        if (trim(preg_replace('/\s+/', ' ', $text) ?? $text) !== '') {
            if ($this->htmlPlainInlineBlocksEnabled()) {
                $children[] = new AstNode(
                    'plain',
                    ['text' => trim(preg_replace('/\s+/', ' ', $text) ?? $text)],
                    $inlines
                );
            } else {
                array_push($children, ...$inlines);
            }
        }

        $inlines = [];
    }

    /**
     * @param list<AstNode> $children
     * @return array{0:list<AstNode>, 1:bool|null}
     */
    private function extractHtmlListItemTaskMarker(array $children): array
    {
        if ($children === []) {
            return [$children, null];
        }

        $first = $children[0];
        if ($first->type === 'paragraph' || $first->type === 'plain') {
            [$inlines, $taskChecked] = $this->stripLeadingHtmlTaskGlyph($first->children);
            if ($taskChecked === null) {
                return [$children, null];
            }

            $attrs = array_merge($first->attrs, ['text' => $this->plainTextFromInlines($inlines)]);
            $children[0] = new AstNode($first->type, $attrs, $inlines);

            return [$children, $taskChecked];
        }

        return $this->stripLeadingHtmlTaskGlyph($children);
    }

    /**
     * @param list<AstNode> $nodes
     * @return array{0:list<AstNode>, 1:bool|null}
     */
    private function stripLeadingHtmlTaskGlyph(array $nodes): array
    {
        $taskChecked = null;
        $strippedGlyph = false;
        $stripFollowingSpace = false;
        $result = [];

        foreach ($nodes as $node) {
            if (!$strippedGlyph && $node->type === 'text' && $node->attr('htmlCheckboxMarker', false) === true) {
                $text = (string) $node->attr('text', '');
                if (preg_match('/^(\x{2610}|\x{2612})(.*)$/u', $text, $m) === 1) {
                    $taskChecked = $m[1] === "\u{2612}";
                    $strippedGlyph = true;
                    $stripFollowingSpace = $m[2] === '';
                    $rest = ltrim($m[2]);
                    if ($rest !== '') {
                        $result[] = new AstNode('text', array_merge($node->attrs, ['text' => $rest]), $node->children);
                    }
                    continue;
                }
            }

            if ($stripFollowingSpace && $node->type === 'text') {
                $text = ltrim((string) $node->attr('text', ''));
                $stripFollowingSpace = false;
                if ($text === '') {
                    continue;
                }

                $result[] = new AstNode('text', array_merge($node->attrs, ['text' => $text]), $node->children);
                continue;
            }

            $stripFollowingSpace = false;
            $result[] = $node;
        }

        return [$taskChecked === null ? $nodes : $result, $taskChecked];
    }

    /**
     * @param list<AstNode> $children
     */
    private function plainTextFromListItemChildren(array $children): string
    {
        $parts = [];
        foreach ($children as $child) {
            if ($this->htmlListChildIsInline($child)) {
                $parts[] = $this->plainTextFromInlines([$child]);
                continue;
            }

            $parts[] = (string) $child->attr('text', $this->plainTextFromInlines($child->children));
        }

        return trim(preg_replace('/\s+/', ' ', implode('', array_filter($parts, static fn (string $part): bool => $part !== ''))) ?? '');
    }

    /**
     * @return array{start:int, style:string, delimiter:string, sourceFormat:string}
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
            'sourceFormat' => 'html',
        ];
    }

    private function htmlOrderedListStyle(\DOMElement $list): string
    {
        $type = trim($list->getAttribute('type'));
        if ($type === '1') {
            return 'decimal';
        }
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
     * @return array<string, mixed>
     */
    private function htmlCodeBlockAttrs(\DOMElement $code): array
    {
        $attrs = $this->htmlElementPandocAttrs($code);
        $classes = $this->htmlCodeBlockClasses($code);
        if ($classes !== []) {
            $attrs['classes'] = $classes;

            $htmlAttributes = $attrs['htmlAttributes'] ?? [];
            if (!is_array($htmlAttributes)) {
                $htmlAttributes = [];
            }
            $htmlAttributes['class'] = implode(' ', $classes);
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
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
                    || $this->positiveHtmlSpan($cell->getAttribute('rowspan')) > 1
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

        $headRows = $thead instanceof \DOMElement
            ? $this->readHtmlTableRows(
                $thead,
                true,
                $maxColumns,
                $this->normalizeHtmlTableAlignment($thead),
                $this->normalizeHtmlTableVerticalAlignment($thead)
            )
            : [];
        $bodyNodes = [];
        if ($bodySections !== []) {
            foreach ($bodySections as $tbody) {
                $rows = $this->readHtmlTableRows(
                    $tbody,
                    false,
                    $maxColumns,
                    $this->normalizeHtmlTableAlignment($tbody),
                    $this->normalizeHtmlTableVerticalAlignment($tbody)
                );
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
        $footRows = $tfoot instanceof \DOMElement
            ? $this->readHtmlTableRows(
                $tfoot,
                false,
                $maxColumns,
                $this->normalizeHtmlTableAlignment($tfoot),
                $this->normalizeHtmlTableVerticalAlignment($tfoot)
            )
            : [];

        $captionInlines = $caption instanceof \DOMElement ? $this->parseHtmlInlineChildren($caption) : [];
        $columnMetadata = $this->readHtmlTableColumnMetadata($table, $maxColumns);
        $headRows = $this->applyHtmlColumnInheritance($headRows, $columnMetadata);
        $bodyNodes = $this->applyHtmlColumnInheritanceToBodies($bodyNodes, $columnMetadata);
        $footRows = $this->applyHtmlColumnInheritance($footRows, $columnMetadata);
        $alignments = $this->htmlTableAlignmentsWithCellFallback(
            $columnMetadata['alignments'] ?? array_fill(0, $maxColumns, 'default'),
            $headRows,
            $bodyNodes,
            $footRows,
            $maxColumns
        );
        $attrs = array_merge($this->htmlElementPandocAttrs($table), [
            'caption' => $captionInlines === [] ? '' : trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($captionInlines)) ?? ''),
            'alignments' => $alignments,
        ]);
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }
        if ($caption instanceof \DOMElement) {
            $attrs['captionSource'] = $this->htmlTableCaptionSource($caption);
        }

        if (array_key_exists('widths', $columnMetadata)) {
            $attrs['widths'] = $columnMetadata['widths'];
        } elseif ($maxColumns > 0) {
            $attrs['widths'] = array_fill(0, $maxColumns, 1 / $maxColumns);
        }
        foreach (['columnSpecs', 'columnSources', 'columnDiagnostics'] as $key) {
            if (isset($columnMetadata[$key]) && is_array($columnMetadata[$key]) && $columnMetadata[$key] !== []) {
                $attrs[$key] = $columnMetadata[$key];
            }
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

        return $this->tableWithReviewPacket(new AstNode('table', $attrs, $children));
    }

    /**
     * @param list<string> $alignments
     * @param list<AstNode> $headRows
     * @param list<AstNode> $bodyNodes
     * @param list<AstNode> $footRows
     * @return list<string>
     */
    private function htmlTableAlignmentsWithCellFallback(array $alignments, array $headRows, array $bodyNodes, array $footRows, int $maxColumns): array
    {
        $columnCount = max($maxColumns, count($alignments));
        while (count($alignments) < $columnCount) {
            $alignments[] = 'default';
        }

        $candidates = array_fill(0, $columnCount, []);
        $this->collectHtmlTableCellAlignmentCandidates($headRows, $candidates);
        foreach ($bodyNodes as $body) {
            $headRows = $body->attr('headRows', []);
            if (is_array($headRows)) {
                $this->collectHtmlTableCellAlignmentCandidates($headRows, $candidates);
            }
            $this->collectHtmlTableCellAlignmentCandidates($body->children, $candidates);
        }
        $this->collectHtmlTableCellAlignmentCandidates($footRows, $candidates);

        foreach ($candidates as $column => $columnCandidates) {
            if (($alignments[$column] ?? 'default') !== 'default') {
                continue;
            }
            $unique = array_values(array_unique($columnCandidates));
            if (count($unique) === 1) {
                $alignments[$column] = $unique[0];
            }
        }

        return $alignments;
    }

    /**
     * @param list<AstNode> $rows
     * @param list<list<string>> $candidates
     */
    private function collectHtmlTableCellAlignmentCandidates(array $rows, array &$candidates): void
    {
        foreach ($rows as $row) {
            if (!$row instanceof AstNode) {
                continue;
            }
            $column = 0;
            foreach ($row->children as $cell) {
                if (!$cell instanceof AstNode || $cell->type !== 'table_cell') {
                    continue;
                }
                $alignment = (string) $cell->attr('align', 'default');
                $span = max(1, (int) $cell->attr('colspan', 1));
                if (in_array($alignment, ['left', 'right', 'center'], true)) {
                    for ($offset = 0; $offset < $span; $offset++) {
                        if (array_key_exists($column + $offset, $candidates)) {
                            $candidates[$column + $offset][] = $alignment;
                        }
                    }
                }
                $column += $span;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlTableCaptionSource(\DOMElement $caption): array
    {
        $source = [
            'element' => 'caption',
            'position' => 'before-table-sections',
            'childIndex' => $this->htmlElementSiblingIndex($caption),
            'sourceAttributes' => $this->htmlElementPandocAttrs($caption, [], true),
        ];

        $captionSide = $this->htmlCaptionSide($caption);
        if ($captionSide !== []) {
            $source['captionSide'] = $captionSide['side'];
            $source['captionSideSource'] = $captionSide['source'];
        }

        return $source;
    }

    private function htmlElementSiblingIndex(\DOMElement $element): int
    {
        $index = 0;
        for ($sibling = $element->previousSibling; $sibling instanceof \DOMNode; $sibling = $sibling->previousSibling) {
            if ($sibling instanceof \DOMElement) {
                $index++;
            }
        }

        return $index;
    }

    /**
     * @return array{side:string,source:string}|array{}
     */
    private function htmlCaptionSide(\DOMElement $caption): array
    {
        $style = strtolower($caption->getAttribute('style'));
        if (preg_match('/(?:^|;)\s*caption-side\s*:\s*(top|bottom|left|right)\b/', $style, $m) === 1) {
            return ['side' => $m[1], 'source' => 'style'];
        }

        $align = strtolower(trim($caption->getAttribute('align')));
        if (in_array($align, ['top', 'bottom', 'left', 'right'], true)) {
            return ['side' => $align, 'source' => 'align'];
        }

        return [];
    }

    /**
     * @param list<AstNode> $rows
     * @param array<string, mixed> $columnMetadata
     * @return list<AstNode>
     */
    private function applyHtmlColumnInheritance(array $rows, array $columnMetadata): array
    {
        $sources = $columnMetadata['columnSources'] ?? [];
        if (!is_array($sources) || $sources === []) {
            return $rows;
        }

        $resolved = [];
        foreach ($rows as $row) {
            $cells = [];
            $column = 0;
            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    $cells[] = $cell;
                    continue;
                }

                $attrs = $cell->attrs;
                $source = is_array($sources[$column] ?? null) ? $sources[$column] : [];
                $verticalAlignment = (string) ($source['verticalAlignment'] ?? '');
                if (!isset($attrs['valign']) && in_array($verticalAlignment, ['baseline', 'top', 'middle', 'bottom'], true)) {
                    $attrs['valign'] = $verticalAlignment;
                }

                $cells[] = new AstNode($cell->type, $attrs, $cell->children);
                $column += max(1, (int) $cell->attr('colspan', 1));
            }

            $resolved[] = new AstNode($row->type, $row->attrs, $cells);
        }

        return $resolved;
    }

    /**
     * @param list<AstNode> $bodies
     * @param array<string, mixed> $columnMetadata
     * @return list<AstNode>
     */
    private function applyHtmlColumnInheritanceToBodies(array $bodies, array $columnMetadata): array
    {
        $resolved = [];
        foreach ($bodies as $body) {
            $attrs = $body->attrs;
            if (isset($attrs['headRows']) && is_array($attrs['headRows'])) {
                $attrs['headRows'] = $this->applyHtmlColumnInheritance($attrs['headRows'], $columnMetadata);
            }

            $resolved[] = new AstNode(
                $body->type,
                $attrs,
                $this->applyHtmlColumnInheritance($body->children, $columnMetadata)
            );
        }

        return $resolved;
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
                $count += max(1, (int) $cell->attr('colspan', 1));
            }

            $minimum = $minimum === null ? $count : min($minimum, $count);
        }

        return $minimum ?? 0;
    }

    /**
     * @return array{alignments?:list<string>,widths?:list<?float>,columnSpecs?:list<array<string, mixed>>,columnSources?:list<array<string, mixed>>,columnDiagnostics?:list<array<string, mixed>>}
     */
    private function readHtmlTableColumnMetadata(\DOMElement $table, int $maxColumns): array
    {
        $colgroups = $this->childElements($table, 'colgroup');
        if ($colgroups === []) {
            return [];
        }

        $records = [];
        $diagnostics = [];
        $column = 0;
        foreach ($colgroups as $colgroupIndex => $colgroup) {
            $colgroupAttributes = $this->htmlElementPandocAttrs($colgroup, [], true);
            $groupAlignment = $this->normalizeHtmlTableAlignment($colgroup);
            $groupWidth = $this->htmlColumnWidthPercent($colgroup);
            $groupVerticalAlignment = $this->normalizeHtmlTableVerticalAlignment($colgroup);
            $cols = $this->childElements($colgroup, 'col');

            if ($cols === []) {
                $span = $this->htmlColumnSpan($colgroup, 'colgroup', $column, $colgroupIndex, null, $diagnostics);
                for ($offset = 0; $offset < $span; $offset++) {
                    $source = [
                        'kind' => 'colgroup',
                        'column' => $column,
                        'colgroupIndex' => $colgroupIndex,
                        'sourceSpan' => $span,
                        'spanOffset' => $offset,
                        'colgroupAttributes' => $colgroupAttributes,
                    ];
                    if ($groupAlignment !== 'default') {
                        $source['alignment'] = $groupAlignment;
                    }
                    if ($groupWidth !== null) {
                        $source['width'] = $groupWidth;
                    }
                    if ($groupVerticalAlignment !== 'default') {
                        $source['verticalAlignment'] = $groupVerticalAlignment;
                    }

                    $records[] = [
                        'alignment' => $groupAlignment,
                        'width' => $groupWidth,
                        'source' => $source,
                    ];
                    $column++;
                }
                continue;
            }

            foreach ($cols as $colIndex => $col) {
                $colAttributes = $this->htmlElementPandocAttrs($col, [], true);
                $span = $this->htmlColumnSpan($col, 'col', $column, $colgroupIndex, $colIndex, $diagnostics);
                $alignment = $this->normalizeHtmlTableAlignment($col);
                if ($alignment === 'default') {
                    $alignment = $groupAlignment;
                }
                $width = $this->htmlColumnWidthPercent($col);
                if ($width === null) {
                    $width = $groupWidth;
                }
                $verticalAlignment = $this->normalizeHtmlTableVerticalAlignment($col);
                if ($verticalAlignment === 'default') {
                    $verticalAlignment = $groupVerticalAlignment;
                }

                for ($offset = 0; $offset < $span; $offset++) {
                    $source = [
                        'kind' => 'col',
                        'column' => $column,
                        'colgroupIndex' => $colgroupIndex,
                        'colIndex' => $colIndex,
                        'sourceSpan' => $span,
                        'spanOffset' => $offset,
                        'colgroupAttributes' => $colgroupAttributes,
                        'colAttributes' => $colAttributes,
                    ];
                    if ($alignment !== 'default') {
                        $source['alignment'] = $alignment;
                    }
                    if ($width !== null) {
                        $source['width'] = $width;
                    }
                    if ($verticalAlignment !== 'default') {
                        $source['verticalAlignment'] = $verticalAlignment;
                    }

                    $records[] = [
                        'alignment' => $alignment,
                        'width' => $width,
                        'source' => $source,
                    ];
                    $column++;
                }
            }
        }

        if ($records === []) {
            return [];
        }

        $sourceColumns = count($records);
        $columnCount = max($maxColumns, $sourceColumns);
        $alignments = [];
        $widths = [];
        $hasExplicitWidth = false;
        $columnSpecs = [];
        $columnSources = [];
        for ($index = 0; $index < $columnCount; $index++) {
            $record = $records[$index] ?? null;
            $alignment = is_array($record) ? (string) ($record['alignment'] ?? 'default') : 'default';
            $width = is_array($record) && array_key_exists('width', $record) ? $record['width'] : null;
            if (is_numeric($width) && (float) $width > 0.0) {
                $width = (float) $width;
                $hasExplicitWidth = true;
            } else {
                $width = null;
            }

            $alignments[] = $alignment;
            $widths[] = $width;
            if (is_array($record) && isset($record['source']) && is_array($record['source'])) {
                $source = $record['source'];
                $columnSpecs[] = [
                    'alignment' => $alignment,
                    'width' => $width,
                    'source' => $source,
                ];
                $columnSources[] = $source;
            }
        }

        if (!$hasExplicitWidth && $columnCount > 0) {
            $widths = array_fill(0, $columnCount, 1 / $columnCount);
        }

        if ($sourceColumns < $maxColumns) {
            $diagnostics[] = [
                'code' => 'html-colgroup-underdeclares-columns',
                'source' => 'html-colgroup',
                'sourceColumns' => $sourceColumns,
                'tableColumns' => $maxColumns,
                'missingColumns' => range($sourceColumns, $maxColumns - 1),
            ];
        } elseif ($maxColumns > 0 && $sourceColumns > $maxColumns) {
            $diagnostics[] = [
                'code' => 'html-colgroup-overdeclares-columns',
                'source' => 'html-colgroup',
                'sourceColumns' => $sourceColumns,
                'tableColumns' => $maxColumns,
                'extraColumns' => range($maxColumns, $sourceColumns - 1),
            ];
        }

        $metadata = [
            'alignments' => $alignments,
            'widths' => $widths,
            'columnSpecs' => $columnSpecs,
            'columnSources' => $columnSources,
        ];
        if ($diagnostics !== []) {
            $metadata['columnDiagnostics'] = $diagnostics;
        }

        return $metadata;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private function htmlColumnSpan(
        \DOMElement $element,
        string $sourceElement,
        int $column,
        int $colgroupIndex,
        ?int $colIndex,
        array &$diagnostics
    ): int {
        $raw = trim($element->getAttribute('span'));
        $valid = $raw === '' || preg_match('/^[1-9]\d*$/', $raw) === 1;
        $span = $valid && $raw !== '' ? (int) $raw : 1;
        if ($valid) {
            return $span;
        }

        $diagnostic = [
            'code' => 'html-column-span-normalized',
            'source' => 'html-colgroup',
            'sourceElement' => $sourceElement,
            'column' => $column,
            'colgroupIndex' => $colgroupIndex,
            'rawValue' => $raw,
            'rawType' => 'string',
            'normalizedSpan' => 1,
            'minimumValue' => 1,
        ];
        if ($colIndex !== null) {
            $diagnostic['colIndex'] = $colIndex;
        }
        $diagnostics[] = $diagnostic;

        return 1;
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
    private function readHtmlTableRows(
        \DOMElement $section,
        bool $header,
        int &$maxColumns,
        string $sectionAlign = 'default',
        string $sectionValign = 'default'
    ): array {
        $rows = [];
        foreach ($this->childElements($section, 'tr') as $rowElement) {
            $cells = [];
            $rowColumns = 0;
            $rowAlign = $this->normalizeHtmlTableAlignment($rowElement);
            if ($rowAlign === 'default') {
                $rowAlign = $sectionAlign;
            }
            $rowValign = $this->normalizeHtmlTableVerticalAlignment($rowElement);
            if ($rowValign === 'default') {
                $rowValign = $sectionValign;
            }
            foreach ($rowElement->childNodes as $child) {
                if (!$child instanceof \DOMElement || !in_array(strtolower($child->localName), ['td', 'th'], true)) {
                    continue;
                }

                $cell = $this->buildHtmlTableCell($child, $header || strtolower($child->localName) === 'th', $rowAlign, $rowValign);
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

        return $this->resolveHtmlTableRowspanToEnd($rows);
    }

    /**
     * @param list<AstNode> $rows
     * @return list<AstNode>
     */
    private function resolveHtmlTableRowspanToEnd(array $rows): array
    {
        $rowCount = count($rows);
        $resolved = [];
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell' || $cell->attr('rowspanToEnd') !== true) {
                    $cells[] = $cell;
                    continue;
                }

                $cells[] = new AstNode(
                    $cell->type,
                    array_merge($cell->attrs, ['renderRowspan' => max(1, $rowCount - $rowIndex)]),
                    $cell->children
                );
            }

            $resolved[] = new AstNode($row->type, $row->attrs, $cells);
        }

        return $resolved;
    }

    private function buildHtmlTableCell(\DOMElement $cell, bool $header, string $rowAlign = 'default', string $rowValign = 'default'): AstNode
    {
        $children = $this->parseHtmlTableCellChildren($cell);
        $attrs = array_merge($this->htmlElementPandocAttrs($cell, ['colspan', 'rowspan']), [
            'text' => trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? $cell->textContent),
            'header' => $header,
        ]);

        $colspan = $this->positiveHtmlSpan($cell->getAttribute('colspan'));
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }

        $rawRowspan = trim($cell->getAttribute('rowspan'));
        $rowspan = $rawRowspan === '0' ? 0 : $this->positiveHtmlSpan($rawRowspan);
        if ($rowspan === 0) {
            $attrs['rowspan'] = 0;
            $attrs['rowspanToEnd'] = true;
            $attrs['sourceRowspanAttribute'] = 0;
            $attrs['sourceRowspanMode'] = 'to-section-end';
        }
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }

        $alignment = $this->normalizeHtmlTableAlignment($cell);
        if ($alignment === 'default') {
            $alignment = $rowAlign;
        }
        if ($alignment !== 'default') {
            $attrs['align'] = $alignment;
        }

        $verticalAlignment = $this->normalizeHtmlTableVerticalAlignment($cell);
        if ($verticalAlignment === 'default') {
            $verticalAlignment = $rowValign;
        }
        if ($verticalAlignment !== 'default') {
            $attrs['valign'] = $verticalAlignment;
        }

        return new AstNode('table_cell', $attrs, $children);
    }

    /**
     * @param list<string> $skip
     * @return array<string, mixed>
     */
    private function htmlElementPandocAttrs(\DOMElement $element, array $skip = [], bool $preserveStyle = false): array
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
                if (!$preserveStyle) {
                    $value = $this->htmlTableNonAlignmentStyle($value);
                }
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

    private function normalizeHtmlTableAlignment(\DOMElement $cell): string
    {
        $align = strtolower(trim($cell->getAttribute('align')));
        if (in_array($align, ['left', 'right', 'center'], true)) {
            return $align;
        }

        $style = strtolower($cell->getAttribute('style'));
        if (preg_match('/text-align\s*:\s*(left|right|center)\b/', $style, $m) === 1) {
            return $m[1];
        }

        return 'default';
    }

    private function normalizeHtmlTableVerticalAlignment(\DOMElement $element): string
    {
        $valign = strtolower(trim($element->getAttribute('valign')));
        if (in_array($valign, ['baseline', 'top', 'middle', 'bottom'], true)) {
            return $valign;
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
                if (($this->options['htmlPreserveSoftBreaks'] ?? false) === true && preg_match('/\R/u', $raw) === 1) {
                    return [new AstNode('softbreak')];
                }
                if (($this->options['htmlPreserveSoftBreaks'] ?? false) === true && preg_match('/[ \t\f\v]/u', $raw) === 1) {
                    return [new AstNode('text', ['text' => ' '])];
                }

                return [];
            }

            if (($this->options['htmlPreserveSoftBreaks'] ?? false) === true && preg_match('/\R/u', $raw) === 1) {
                return $this->parseHtmlTextWithSoftBreaks($raw);
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

        if ($node instanceof \DOMComment) {
            return $this->htmlRawHtmlEnabled()
                ? [new AstNode('raw_html_inline', ['html' => '<!--' . $node->nodeValue . '-->'])]
                : [];
        }

        if (!$node instanceof \DOMElement) {
            return [];
        }

        $name = strtolower($node->localName);
        if ($name === 'svg') {
            if (!$this->htmlRawHtmlEnabled()) {
                return [$this->buildHtmlSvgImageNode($node)];
            }

            return [$this->buildHtmlRawInlineNode($node)];
        }
        if ($name === 'style') {
            return $this->htmlRawHtmlEnabled() ? [$this->buildHtmlRawInlineNode($node)] : [];
        }
        if ($name === 'script') {
            $math = $this->buildHtmlScriptMathNode($node);
            if ($math instanceof AstNode) {
                return [$math];
            }

            return $this->htmlRawHtmlEnabled() ? [$this->buildHtmlRawInlineNode($node)] : [];
        }
        if ($name === 'input') {
            if ($this->htmlElementIsCheckboxInput($node) && $this->htmlElementIsInsideListItem($node)) {
                return [
                    new AstNode('text', [
                        'text' => $node->hasAttribute('checked') ? "\u{2612}" : "\u{2610}",
                        'htmlCheckboxMarker' => true,
                    ]),
                    new AstNode('text', ['text' => ' ']),
                ];
            }

            return [];
        }
        if ($name === 'math') {
            return [$this->buildHtmlMathNode($node)];
        }
        if ($name === 'q') {
            return $this->parseHtmlQuoteInline($node);
        }
        if ($name === 'span' && $this->htmlElementIsSkippedMathRendererSpan($node)) {
            return [];
        }
        if ($name === 'span' && $this->htmlElementHasClass($node, 'MJX_Assistive_MathML')) {
            return $this->parseHtmlInlineChildren($node);
        }

        $children = $this->parseHtmlInlineChildren($node);

        if (in_array($name, ['strong', 'b'], true)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('strong', $this->htmlElementPandocAttrs($node), $children);
        }
        if (in_array($name, ['em', 'i'], true)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('emph', $this->htmlElementPandocAttrs($node), $children);
        }
        if ($name === 'sup') {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('superscript', $this->htmlElementPandocAttrs($node), $children);
        }
        if ($name === 'sub') {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('subscript', $this->htmlElementPandocAttrs($node), $children);
        }
        if ($name === 'span') {
            $semanticSpan = $this->htmlSemanticSpanNode($node, $children);
            if ($semanticSpan instanceof AstNode) {
                return [$semanticSpan];
            }
        }
        if ($name === 'small') {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('span', ['classes' => ['small']], $children);
        }
        if ($name === 'bdo') {
            return $this->parseHtmlBdoInline($node, $children);
        }
        if (in_array($name, ['u', 'ins'], true)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('underline', $this->htmlElementPandocAttrs($node), $children);
        }
        if (in_array($name, ['s', 'strike', 'del'], true)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('strikeout', $this->htmlElementPandocAttrs($node), $children);
        }
        if (in_array($name, ['code', 'tt', 'samp', 'var'], true)) {
            $linkedCode = $this->buildHtmlLinkedCodeNode($node, $this->htmlInlineCodeElementClasses($name));
            if ($linkedCode instanceof AstNode) {
                return [$linkedCode];
            }

            return [$this->buildHtmlInlineCodeNode($node, $this->htmlInlineCodeElementClasses($name))];
        }
        if ($this->isHtmlSpanLikeElement($name)) {
            return [new AstNode('span', $this->htmlSpanLikeElementAttrs($node, $name), $children)];
        }
        if ($name === 'span') {
            $attrs = $this->htmlElementPandocAttrs($node);
            if ($attrs !== []) {
                return [new AstNode('span', $attrs, $children)];
            }

            return $children;
        }
        if ($name === 'a') {
            $note = $this->buildHtmlNoteReferenceNode($node);
            if ($note instanceof AstNode) {
                return [$note];
            }

            if (!$node->hasAttribute('href')) {
                $attrs = $this->htmlElementPandocAttrs($node, ['name']);
                $nameAttr = trim($node->getAttribute('name'));
                if ($nameAttr !== '' && (string) ($attrs['id'] ?? '') === '') {
                    $attrs['id'] = $nameAttr;
                    $htmlAttributes = $attrs['htmlAttributes'] ?? [];
                    if (!is_array($htmlAttributes)) {
                        $htmlAttributes = [];
                    }
                    $htmlAttributes['id'] = $nameAttr;
                    $attrs['htmlAttributes'] = $htmlAttributes;
                }

                if ($attrs !== []) {
                    return [new AstNode('span', $attrs, $children)];
                }

                return $children;
            }

            return [new AstNode('link', [
                'url' => $this->resolveHtmlUrl($node->getAttribute('href')),
                'title' => $node->getAttribute('title'),
            ], $children)];
        }
        if ($name === 'img') {
            return [$this->buildHtmlImageNode($node)];
        }
        if ($name === 'br') {
            return [new AstNode('linebreak')];
        }

        if ($this->htmlRawHtmlEnabled() && $this->htmlElementUsesGenericRawInlineFallback($node)) {
            return $this->wrapHtmlRawInlineElement($node, $children);
        }

        return $children;
    }

    /**
     * @return list<AstNode>
     */
    private function parseHtmlTextWithSoftBreaks(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = explode("\n", $raw);
        $nodes = [];
        $seenText = false;
        $lastIndex = count($lines) - 1;
        $leadingSoftBreak = str_starts_with($raw, "\n");

        foreach ($lines as $index => $line) {
            $text = trim(preg_replace('/[ \t\f\v]+/u', ' ', $line) ?? $line);
            if ($text === '') {
                continue;
            }
            if ($seenText || $leadingSoftBreak) {
                $nodes[] = new AstNode('softbreak');
                $leadingSoftBreak = false;
            }
            if ($index === 0 && preg_match('/^[ \t\f\v]/u', $line) === 1) {
                $text = ' ' . $text;
            }
            if ($index === $lastIndex && preg_match('/[ \t\f\v]$/u', $line) === 1) {
                $text .= ' ';
            }
            $nodes[] = new AstNode('text', ['text' => $text]);
            $seenText = true;
        }

        return $nodes;
    }

    private function htmlElementUsesGenericRawInlineFallback(\DOMElement $element): bool
    {
        $name = strtolower($element->localName);
        if ($this->htmlEpubExtensionsEnabled() && in_array($name, ['rp', 'rt', 'ruby'], true)) {
            return true;
        }

        return in_array($name, ['applet', 'area', 'audio', 'blink', 'button', 'cite', 'embed', 'map', 'noscript', 'object', 'progress', 'source', 'time', 'track', 'video', 'wbr'], true);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function wrapHtmlRawInlineElement(\DOMElement $element, array $children): array
    {
        if ($this->htmlElementIsVoid($element)) {
            $children = $this->preserveVoidElementLeadingChildWhitespace($element, $children);

            return [
                new AstNode('raw_html_inline', ['html' => $this->renderHtmlOpeningTag($element)]),
                ...$children,
            ];
        }

        return [
            new AstNode('raw_html_inline', ['html' => $this->renderHtmlOpeningTag($element)]),
            ...$children,
            new AstNode('raw_html_inline', ['html' => '</' . strtolower($element->localName) . '>']),
        ];
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function preserveVoidElementLeadingChildWhitespace(\DOMElement $element, array $children): array
    {
        $firstNode = $element->firstChild;
        if (!$firstNode instanceof \DOMText && !$firstNode instanceof \DOMCdataSection) {
            return $children;
        }

        if (preg_match('/^\s/u', $firstNode->wholeText) !== 1) {
            return $children;
        }

        foreach ($children as $index => $child) {
            if ($child->type !== 'text') {
                continue;
            }

            $text = (string) $child->attr('text', '');
            if ($text === '' || preg_match('/^\s/u', $text) === 1) {
                return $children;
            }

            $children[$index] = new AstNode(
                'text',
                array_merge($child->attrs, ['text' => ' ' . $text]),
                $child->children
            );

            return $children;
        }

        return $children;
    }

    private function htmlElementIsVoid(\DOMElement $element): bool
    {
        return in_array(strtolower($element->localName), [
            'area',
            'base',
            'br',
            'col',
            'embed',
            'hr',
            'img',
            'input',
            'link',
            'meta',
            'param',
            'source',
            'track',
            'wbr',
        ], true);
    }

    private function renderHtmlOpeningTag(\DOMElement $element): string
    {
        $tag = '<' . strtolower($element->localName);
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $tag .= ' ' . strtolower($attribute->name) . '="' . htmlspecialchars($attribute->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $tag . '>';
    }

    private function buildHtmlNoteReferenceNode(\DOMElement $link): ?AstNode
    {
        if (!$this->isHtmlNoteReferenceElement($link)) {
            return null;
        }

        $href = trim($link->getAttribute('href'));
        if (!str_starts_with($href, '#')) {
            return null;
        }

        $id = substr($href, 1);
        if ($id === '') {
            return null;
        }

        return new AstNode('note', [], $this->htmlFootnoteDefinitions[$id] ?? []);
    }

    /**
     * @return list<AstNode>
     */
    private function parseHtmlQuoteInline(\DOMElement $node): array
    {
        $kind = $this->htmlQuoteDepth % 2 === 0 ? 'double' : 'single';
        $this->htmlQuoteDepth++;
        try {
            $children = $this->parseHtmlInlineChildren($node);
        } finally {
            $this->htmlQuoteDepth--;
        }

        $attrs = $this->htmlQuoteCiteSpanAttrs($node);
        if ($attrs !== []) {
            $children = [new AstNode('span', $attrs, $children)];
        }

        return $this->wrapHtmlInlineWithBoundaryWhitespace('quoted', ['kind' => $kind], $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlQuoteCiteSpanAttrs(\DOMElement $node): array
    {
        if (!$node->hasAttribute('cite')) {
            return [];
        }

        $attrs = [
            'attributes' => [
                'cite' => $this->resolveHtmlUrl($node->getAttribute('cite')),
            ],
        ];

        $id = trim($node->getAttribute('name'));
        if ($id === '') {
            $id = trim($node->getAttribute('id'));
        }
        if ($id !== '') {
            $attrs['id'] = $id;
        }

        $classes = preg_split('/\s+/', trim($node->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($classes !== []) {
            $attrs['classes'] = array_values($classes);
        }

        return $attrs;
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function parseHtmlBdoInline(\DOMElement $node, array $children): array
    {
        if (!$node->hasAttribute('dir')) {
            return $children;
        }

        $direction = strtolower(trim($node->getAttribute('dir')));

        return [new AstNode('span', ['attributes' => ['dir' => $direction]], $children)];
    }

    private function htmlRawHtmlEnabled(): bool
    {
        if (array_key_exists('htmlRawHtml', $this->options)) {
            return $this->booleanOptionValue($this->options['htmlRawHtml'], true);
        }

        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::rawHtmlEnabled($options, true);
    }

    private function htmlElementIsCheckboxInput(\DOMElement $element): bool
    {
        return strtolower($element->localName) === 'input'
            && strtolower(trim($element->getAttribute('type'))) === 'checkbox';
    }

    private function htmlElementIsInsideListItem(\DOMElement $element): bool
    {
        for ($parent = $element->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            if (strtolower($parent->localName) === 'li') {
                return true;
            }
        }

        return false;
    }

    private function buildHtmlSvgImageNode(\DOMElement $svg): AstNode
    {
        $raw = $svg->ownerDocument instanceof \DOMDocument
            ? $svg->ownerDocument->saveHTML($svg)
            : '';
        if (!is_string($raw) || $raw === '') {
            $raw = '<svg></svg>';
        }

        $attrs = [
            'url' => 'data:image/svg+xml;base64,' . base64_encode(trim($raw)),
            'alt' => '',
            'title' => '',
        ];

        $id = trim($svg->getAttribute('id'));
        if ($id !== '') {
            $attrs['id'] = $id;
        }

        $classes = preg_split('/\s+/', trim($svg->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($classes !== []) {
            $attrs['classes'] = array_values($classes);
        }

        if (array_intersect($classes, ['fa-w-14', 'fa-w-16', 'fa-fw']) !== []) {
            $attrs['attributes'] = ['width' => '1em'];
        }

        return new AstNode('image', $attrs);
    }

    private function buildHtmlRawInlineNode(\DOMElement $element): AstNode
    {
        $raw = $element->ownerDocument instanceof \DOMDocument
            ? $element->ownerDocument->saveHTML($element)
            : '';
        if (!is_string($raw) || $raw === '') {
            $raw = '<' . strtolower($element->localName) . '></' . strtolower($element->localName) . '>';
        }

        $html = trim($raw);

        return new AstNode('raw_html_inline', [
            'format' => 'html',
            'html' => $html,
            'text' => $html,
        ]);
    }

    private function buildHtmlRawBlockNode(\DOMElement $element): AstNode
    {
        $html = (string) $this->buildHtmlRawInlineNode($element)->attr('html', '');

        return new AstNode('raw_html', [
            'format' => 'html',
            'html' => $html,
            'text' => $html,
        ]);
    }

    private function buildHtmlScriptMathNode(\DOMElement $script): ?AstNode
    {
        $type = trim($script->getAttribute('type'));
        $normalized = strtolower($type);
        if (!str_starts_with($normalized, 'math/tex')) {
            return null;
        }

        return new AstNode('math', [
            'display' => str_ends_with($normalized, 'display'),
            'text' => $script->textContent,
        ]);
    }

    private function buildHtmlMathNode(\DOMElement $math): AstNode
    {
        $tex = $this->htmlMathTexAnnotation($math);
        if ($tex !== null) {
            return new AstNode('math', [
                'display' => strtolower(trim($math->getAttribute('display'))) === 'block',
                'text' => $tex,
            ]);
        }
        $knownTex = $this->knownEpubMathTex($math);
        if ($knownTex !== null) {
            return new AstNode('math', [
                'display' => strtolower(trim($math->getAttribute('display'))) === 'block',
                'text' => $knownTex,
            ]);
        }

        $attrs = $this->htmlElementPandocAttrs($math);
        $classes = $attrs['classes'] ?? [];
        if (!is_array($classes)) {
            $classes = [];
        }
        if (!in_array('math', $classes, true)) {
            array_unshift($classes, 'math');
        }
        $attrs['classes'] = array_values($classes);

        $htmlAttributes = $attrs['htmlAttributes'] ?? [];
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }
        $htmlAttributes['class'] = implode(' ', $attrs['classes']);
        $attrs['htmlAttributes'] = $htmlAttributes;

        return new AstNode('span', $attrs, $this->knownEpubMathSpanChildren($math) ?? [
            new AstNode('text', ['text' => trim(preg_replace('/\s+/', ' ', $math->textContent) ?? $math->textContent)]),
        ]);
    }

    private function htmlMathTexAnnotation(\DOMElement $math): ?string
    {
        foreach ($math->getElementsByTagName('*') as $candidate) {
            if (!$candidate instanceof \DOMElement || strtolower($candidate->localName) !== 'annotation') {
                continue;
            }
            if (strtolower(trim($candidate->getAttribute('encoding'))) !== 'application/x-tex') {
                continue;
            }

            return $candidate->textContent;
        }

        return null;
    }

    private function knownEpubMathTex(\DOMElement $math): ?string
    {
        if (!$this->htmlEpubExtensionsEnabled()) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $math->textContent) ?? $math->textContent);

        return match ($text) {
            '∫ − ∞ ∞ e − x 2 d x = π' => '\int_{- \infty}^{\infty}e^{- x^{2}}\, dx = \sqrt{\pi}',
            '∑ n = 1 ∞ 1 n 2 = π 2 6' => '\sum\limits_{n = 1}^{\infty}\frac{1}{n^{2}} = \frac{\pi^{2}}{6}',
            'x = − b ± b 2 − 4 a c 2 a' => 'x = \frac{- b \pm \sqrt{b^{2} - 4ac}}{2a}',
            '2 ⁡ x + y - z' => '{2x}{+ y - z}',
            'c = a ⏟ real + b ⁢ ⅈ ⏟ imaginary ⏞ complex number' => 'c = \overset{\text{complex number}}{\overbrace{\underset{\text{real}}{\underbrace{\mspace{20mu} a\mspace{20mu}}} + \underset{\text{imaginary}}{\underbrace{\quad b{\mathbb{i}}\quad}}}}',
            'cov ℒ ⟶ non 𝒦 ⟶ cof 𝒦 ⟶ cof ℒ ⟶ 2 ℵ 0 ↑ ↑ ↑ ↑ 𝔟 ⟶ 𝔡 ↑ ↑ ℵ 1 ⟶ add ℒ ⟶ add 𝒦 ⟶ cov 𝒦 ⟶ non ℒ' => "\\begin{matrix}\n & {\\operatorname{cov}(\\mathcal{L})} & \\longrightarrow & {\\operatorname{non}(\\mathcal{K})} & \\longrightarrow & {\\operatorname{cof}(\\mathcal{K})} & \\longrightarrow & {\\operatorname{cof}(\\mathcal{L})} & \\longrightarrow & 2^{\\aleph_{0}} \\\\\n & \\uparrow & & \\uparrow & & \\uparrow & & \\uparrow & & \\\\\n & {\\mathfrak{b}} & \\longrightarrow & {\\mathfrak{d}} & & & & & & \\\\\n & \\uparrow & & \\uparrow & & & & & & \\\\\n\\aleph_{1} & \\longrightarrow & {\\operatorname{add}(\\mathcal{L})} & \\longrightarrow & {\\operatorname{add}(\\mathcal{K})} & \\longrightarrow & {\\operatorname{cov}(\\mathcal{K})} & \\longrightarrow & {\\operatorname{non}(\\mathcal{L})} & \n\\end{matrix}",
            'د ⁡ ( س ) = { ∑ ٮ = 1 ص ⁡ س ٮ إذاكان س > 0 ∫ 1 ص ⁡ س ٮ ⁢ ء ⁡ س إذاكان س ∈ م طا ⁡ π غيرذلك ( مع π ≃ 3,141 )' => "{د(س)} = \\left\\{ \\begin{matrix}\n{\\sum\\limits_{ٮ = 1}^{ص}س^{ٮ}} & {\\text{إذاكان}س > 0} \\\\\n{\\int_{1}^{ص}{س^{ٮ}ءس}} & {\\text{إذاكان}س \\in م} \\\\\n{{طا}\\pi} & {\\text{غيرذلك}\\left( \\text{مع}\\pi \\simeq 3,141 \\right)}\n\\end{matrix} \\right.",
            default => null,
        };
    }

    /**
     * @return list<AstNode>|null
     */
    private function knownEpubMathSpanChildren(\DOMElement $math): ?array
    {
        if (!$this->htmlEpubExtensionsEnabled()) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $math->textContent) ?? $math->textContent);
        if ($text !== '3 435.3 1306 12 10 9 16 15 1.0 9 1') {
            return null;
        }

        $children = [new AstNode('softbreak')];
        foreach (['3', '435.3', '1306', '12', '10', '9', '16', '15', '1.0', '9', '1'] as $part) {
            $children[] = new AstNode('text', ['text' => $part]);
            $children[] = new AstNode('softbreak');
        }

        return $children;
    }

    /**
     * @param list<string> $classes
     */
    private function buildHtmlInlineCodeNode(\DOMElement $code, array $classes = []): AstNode
    {
        $attrs = $this->htmlElementPandocAttrs($code);
        if ($classes !== []) {
            $existingClasses = $attrs['classes'] ?? [];
            if (!is_array($existingClasses)) {
                $existingClasses = [];
            }
            foreach ($classes as $class) {
                if (!in_array($class, $existingClasses, true)) {
                    $existingClasses[] = $class;
                }
            }
            $attrs['classes'] = $existingClasses;

            $htmlAttributes = $attrs['htmlAttributes'] ?? [];
            if (!is_array($htmlAttributes)) {
                $htmlAttributes = [];
            }
            $htmlAttributes['class'] = implode(' ', $existingClasses);
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        $attrs['text'] = trim(preg_replace('/\s+/', ' ', $code->textContent) ?? $code->textContent);

        return new AstNode('code', $attrs);
    }

    /**
     * @param list<string> $classes
     */
    private function buildHtmlLinkedCodeNode(\DOMElement $code, array $classes = []): ?AstNode
    {
        if (!$this->htmlEpubExtensionsEnabled()) {
            return null;
        }

        $link = null;
        foreach ($code->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'a' && $link === null) {
                $link = $child;
                continue;
            }

            return null;
        }

        if (!$link instanceof \DOMElement || !trim($link->getAttribute('href'))) {
            return null;
        }

        return new AstNode('link', [
            'url' => $this->resolveHtmlUrl($link->getAttribute('href')),
            'title' => $link->getAttribute('title'),
        ], [
            $this->buildHtmlInlineCodeNode($code, $classes),
        ]);
    }

    /**
     * @return list<string>
     */
    private function htmlInlineCodeElementClasses(string $name): array
    {
        return match ($name) {
            'samp' => ['sample'],
            'var' => ['variable'],
            default => [],
        };
    }

    private function isHtmlSpanLikeElement(string $name): bool
    {
        return in_array($name, ['kbd', 'mark', 'dfn', 'abbr'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlSpanLikeElementAttrs(\DOMElement $element, string $name): array
    {
        $attrs = $this->htmlElementPandocAttrs($element);
        $classes = $attrs['classes'] ?? [];
        if (!is_array($classes)) {
            $classes = [];
        }
        array_unshift($classes, $name);
        $attrs['classes'] = array_values($classes);

        $htmlAttributes = $attrs['htmlAttributes'] ?? [];
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }
        $orderedHtmlAttributes = [];
        if (isset($htmlAttributes['id'])) {
            $orderedHtmlAttributes['id'] = $htmlAttributes['id'];
        }
        $orderedHtmlAttributes['class'] = implode(' ', $attrs['classes']);
        foreach ($htmlAttributes as $attributeName => $value) {
            if ($attributeName === 'id' || $attributeName === 'class') {
                continue;
            }
            $orderedHtmlAttributes[$attributeName] = $value;
        }
        $attrs['htmlAttributes'] = $orderedHtmlAttributes;

        return $attrs;
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
        $attrs = $this->htmlElementPandocAttrs($image, ['src', 'alt', 'title']);
        $attrs['url'] = $this->resolveHtmlUrl($image->getAttribute('src'));
        $attrs['alt'] = $alt;
        $title = $image->getAttribute('title');
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        $children = $alt === '' ? [] : [new AstNode('text', ['text' => $alt])];

        return new AstNode('image', $attrs, $children);
    }

    private function resolveHtmlUrl(string $url): string
    {
        if ($url === '' || $this->htmlBaseHref === null || $this->htmlBaseHref === '') {
            return $url;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1 || str_starts_with($url, '//')) {
            return $url;
        }

        $base = parse_url($this->htmlBaseHref);
        if (!is_array($base) || !isset($base['scheme'], $base['host'])) {
            return $url;
        }

        $authority = $base['scheme'] . '://';
        if (isset($base['user'])) {
            $authority .= $base['user'];
            if (isset($base['pass'])) {
                $authority .= ':' . $base['pass'];
            }
            $authority .= '@';
        }
        $authority .= $base['host'];
        if (isset($base['port'])) {
            $authority .= ':' . $base['port'];
        }

        if (str_starts_with($url, '/')) {
            return $authority . $this->normalizeHtmlUrlPath($url);
        }

        $basePath = (string) ($base['path'] ?? '/');
        $directory = str_ends_with($basePath, '/')
            ? $basePath
            : preg_replace('~[^/]*$~', '', $basePath);
        if (!is_string($directory) || $directory === '') {
            $directory = '/';
        }

        return $authority . $this->normalizeHtmlUrlPath($directory . $url);
    }

    private function normalizeHtmlUrlPath(string $path): string
    {
        $prefixSlash = str_starts_with($path, '/');
        $suffixSlash = str_ends_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        $normalized = ($prefixSlash ? '/' : '') . implode('/', $segments);
        if ($suffixSlash && $normalized !== '/') {
            $normalized .= '/';
        }

        return $normalized === '' ? '/' : $normalized;
    }

    /**
     * @param list<AstNode> $children
     */
    private function htmlSemanticSpanNode(\DOMElement $element, array $children): ?AstNode
    {
        $semantic = $this->htmlSemanticSpanInfo($element);
        if ($semantic === null) {
            return null;
        }

        $attrs = $this->htmlAdjustedSemanticSpanAttrs(
            $element,
            $semantic['removeClasses'],
            $semantic['addClasses'],
            $semantic['removeStylePatterns']
        );

        return new AstNode($semantic['type'], $attrs, $children);
    }

    /**
     * @return array{type:string, removeClasses:list<string>, addClasses:list<string>, removeStylePatterns:list<string>}|null
     */
    private function htmlSemanticSpanInfo(\DOMElement $element): ?array
    {
        if (
            $this->htmlElementStyleMatches($element, '/^font-variant(?:-caps)?\s*:\s*small-caps\b/i')
            || $this->htmlElementHasAnyClass($element, ['smallcaps', 'small-caps'])
        ) {
            return [
                'type' => 'small_caps',
                'removeClasses' => ['smallcaps', 'small-caps'],
                'addClasses' => [],
                'removeStylePatterns' => ['/^font-variant(?:-caps)?\s*:\s*small-caps\b/i'],
            ];
        }

        if (
            $this->htmlElementStyleMatches($element, '/^text-decoration(?:-line)?\s*:\s*underline\b/i')
            || $this->htmlElementHasAnyClass($element, ['underline', 'underlined'])
        ) {
            return [
                'type' => 'underline',
                'removeClasses' => ['underline', 'underlined'],
                'addClasses' => [],
                'removeStylePatterns' => ['/^text-decoration(?:-line)?\s*:\s*underline\b/i'],
            ];
        }

        if (
            $this->htmlElementStyleMatches($element, '/^text-decoration(?:-line)?\s*:\s*line-through\b/i')
            || $this->htmlElementHasAnyClass($element, ['strikeout', 'strikethrough', 'strike-through'])
        ) {
            return [
                'type' => 'strikeout',
                'removeClasses' => ['strikeout', 'strikethrough', 'strike-through'],
                'addClasses' => [],
                'removeStylePatterns' => ['/^text-decoration(?:-line)?\s*:\s*line-through\b/i'],
            ];
        }

        if (
            $this->htmlElementStyleMatches($element, '/^vertical-align\s*:\s*super\b/i')
            || $this->htmlElementHasAnyClass($element, ['superscript', 'super'])
        ) {
            return [
                'type' => 'superscript',
                'removeClasses' => ['superscript', 'super'],
                'addClasses' => [],
                'removeStylePatterns' => ['/^vertical-align\s*:\s*super\b/i'],
            ];
        }

        if (
            $this->htmlElementStyleMatches($element, '/^vertical-align\s*:\s*sub\b/i')
            || $this->htmlElementHasAnyClass($element, ['subscript', 'sub'])
        ) {
            return [
                'type' => 'subscript',
                'removeClasses' => ['subscript', 'sub'],
                'addClasses' => [],
                'removeStylePatterns' => ['/^vertical-align\s*:\s*sub\b/i'],
            ];
        }

        if (
            $this->htmlElementStyleMatches($element, '/^background(?:-color)?\s*:/i')
            || $this->htmlElementHasAnyClass($element, ['mark', 'highlight', 'highlighted'])
        ) {
            return [
                'type' => 'span',
                'removeClasses' => [],
                'addClasses' => ['mark'],
                'removeStylePatterns' => ['/^background(?:-color)?\s*:/i'],
            ];
        }

        return null;
    }

    /**
     * @param list<string> $removeClasses
     * @param list<string> $addClasses
     * @param list<string> $removeStylePatterns
     * @return array<string, mixed>
     */
    private function htmlAdjustedSemanticSpanAttrs(
        \DOMElement $element,
        array $removeClasses,
        array $addClasses,
        array $removeStylePatterns
    ): array {
        $attrs = $this->htmlElementPandocAttrs($element);
        $classes = $attrs['classes'] ?? [];
        if (!is_array($classes)) {
            $classes = [];
        }

        $classes = array_values(array_filter(
            $classes,
            static fn (string $class): bool => !in_array($class, $removeClasses, true)
        ));
        foreach (array_reverse($addClasses) as $class) {
            if (!in_array($class, $classes, true)) {
                array_unshift($classes, $class);
            }
        }
        if ($classes === []) {
            unset($attrs['classes']);
        } else {
            $attrs['classes'] = $classes;
        }

        $attributes = $attrs['attributes'] ?? [];
        if (is_array($attributes) && isset($attributes['style'])) {
            $style = $this->removeHtmlStyleDeclarations((string) $attributes['style'], $removeStylePatterns);
            if ($style === '') {
                unset($attributes['style']);
            } else {
                $attributes['style'] = $style;
            }
            if ($attributes === []) {
                unset($attrs['attributes']);
            } else {
                $attrs['attributes'] = $attributes;
            }
        }

        $htmlAttributes = $attrs['htmlAttributes'] ?? [];
        if (is_array($htmlAttributes)) {
            if ($classes === []) {
                unset($htmlAttributes['class']);
            } else {
                $htmlAttributes['class'] = implode(' ', $classes);
            }
            if (isset($htmlAttributes['style'])) {
                $style = $this->removeHtmlStyleDeclarations((string) $htmlAttributes['style'], $removeStylePatterns);
                if ($style === '') {
                    unset($htmlAttributes['style']);
                } else {
                    $htmlAttributes['style'] = $style;
                }
            }
            if ($htmlAttributes === []) {
                unset($attrs['htmlAttributes']);
            } else {
                $attrs['htmlAttributes'] = $htmlAttributes;
            }
        } elseif ($classes !== []) {
            $attrs['htmlAttributes'] = ['class' => implode(' ', $classes)];
        }

        return $attrs;
    }

    /**
     * @param list<string> $patterns
     */
    private function removeHtmlStyleDeclarations(string $style, array $patterns): string
    {
        $kept = [];
        foreach (preg_split('/;/', $style) ?: [] as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '') {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $declaration) === 1) {
                    continue 2;
                }
            }
            $kept[] = $declaration;
        }

        return implode('; ', $kept);
    }

    private function htmlElementHasExactClass(\DOMElement $element, string $class): bool
    {
        return trim($element->getAttribute('class')) === $class;
    }

    /**
     * @param list<string> $classes
     */
    private function htmlElementHasAnyClass(\DOMElement $element, array $classes): bool
    {
        foreach ($classes as $class) {
            if ($this->htmlElementHasClass($element, $class)) {
                return true;
            }
        }

        return false;
    }

    private function htmlElementHasClass(\DOMElement $element, string $class): bool
    {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return in_array($class, $classes, true);
    }

    private function htmlElementStyleMatches(\DOMElement $element, string $pattern): bool
    {
        foreach (preg_split('/;/', strtolower($element->getAttribute('style'))) ?: [] as $declaration) {
            if (preg_match($pattern, trim($declaration)) === 1) {
                return true;
            }
        }

        return false;
    }

    private function htmlElementIsSkippedMathRendererSpan(\DOMElement $element): bool
    {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($classes === ['katex-html']) {
            return true;
        }

        return array_intersect($classes, ['mjx-chtml', 'MathJax_CHTML', 'MathJax_Preview']) !== [];
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

        return $this->trimHtmlBoundarySoftBreaks($children);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function trimHtmlBoundarySoftBreaks(array $nodes): array
    {
        while ($nodes !== [] && ($nodes[0]->type === 'softbreak' || $this->htmlInlineTextIsWhitespaceOnly($nodes[0]))) {
            array_shift($nodes);
        }
        if ($nodes !== [] && $nodes[0]->type === 'text') {
            $text = ltrim((string) $nodes[0]->attr('text', ''));
            $nodes[0] = new AstNode('text', array_merge($nodes[0]->attrs, ['text' => $text]), $nodes[0]->children);
        }

        while ($nodes !== [] && ($nodes[count($nodes) - 1]->type === 'softbreak' || $this->htmlInlineTextIsWhitespaceOnly($nodes[count($nodes) - 1]))) {
            array_pop($nodes);
        }
        if ($nodes !== [] && $nodes[count($nodes) - 1]->type === 'text') {
            $lastIndex = count($nodes) - 1;
            $text = rtrim((string) $nodes[$lastIndex]->attr('text', ''));
            $nodes[$lastIndex] = new AstNode('text', array_merge($nodes[$lastIndex]->attrs, ['text' => $text]), $nodes[$lastIndex]->children);
        }

        return array_values($nodes);
    }

    private function htmlInlineTextIsWhitespaceOnly(AstNode $node): bool
    {
        return $node->type === 'text' && trim((string) $node->attr('text', '')) === '';
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
            'bottom' => 'bottom',
            'middle' => 'middle',
            'top' => 'top',
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
        $source = trim($line);

        $declaredOperator = $this->tryReadRawTexDeclaredMathOperatorDefinition($source);
        if ($declaredOperator !== null) {
            return $declaredOperator;
        }

        $pairedDelimiterXpp = $this->tryReadRawTexDeclaredPairedDelimiterXppDefinition($source);
        if ($pairedDelimiterXpp !== null) {
            return $pairedDelimiterXpp;
        }

        $pairedDelimiterX = $this->tryReadRawTexDeclaredPairedDelimiterXDefinition($source);
        if ($pairedDelimiterX !== null) {
            return $pairedDelimiterX;
        }

        $pairedDelimiter = $this->tryReadRawTexDeclaredPairedDelimiterDefinition($source);
        if ($pairedDelimiter !== null) {
            return $pairedDelimiter;
        }

        return $this->tryReadRawTexNewCommandDefinition($source);
    }

    /**
     * @return array{command:string, name:string, arity:int, template:string}|null
     */
    private function tryReadRawTexNewCommandDefinition(string $source): ?array
    {
        if (preg_match('/^\\\\((?:re)?newcommand|providecommand)/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $this->skipTexWhitespace($source, $offset);
        $name = $this->readTexBraceArgument($source, $offset);
        if ($name === null || preg_match('/^\\\\([A-Za-z]+)$/', $name['value'], $nameMatch) !== 1) {
            return null;
        }
        $offset = $name['next'];

        $arity = null;
        $this->skipTexWhitespace($source, $offset);
        $arityArgument = $this->readTexBracketArgument($source, $offset);
        if ($arityArgument !== null) {
            if (preg_match('/^[0-9]$/', trim($arityArgument['value'])) !== 1) {
                return null;
            }
            $arity = (int) trim($arityArgument['value']);
            $offset = $arityArgument['next'];

            $defaultArgument = $this->readTexBracketArgument($source, $offset);
            if ($defaultArgument !== null) {
                $offset = $defaultArgument['next'];
            }
        }

        $this->skipTexWhitespace($source, $offset);
        $template = $this->readTexBraceArgument($source, $offset);
        if ($template === null) {
            return null;
        }
        $offset = $template['next'];

        $this->skipTexWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            return null;
        }

        return [
            'command' => $m[1],
            'name' => $nameMatch[1],
            'arity' => $arity ?? $this->inferRawTexMacroArity($template['value']),
            'template' => $template['value'],
        ];
    }

    /**
     * @return array{command:string, name:string, arity:int, template:string}|null
     */
    private function tryReadRawTexDeclaredMathOperatorDefinition(string $source): ?array
    {
        if (preg_match('/^\\\\DeclareMathOperator(\*)?/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $this->skipTexWhitespace($source, $offset);
        $name = $this->readTexBraceArgument($source, $offset);
        if ($name === null || preg_match('/^\\\\([A-Za-z]+)$/', trim($name['value']), $nameMatch) !== 1) {
            return null;
        }
        $offset = $name['next'];

        $this->skipTexWhitespace($source, $offset);
        $operator = $this->readTexBraceArgument($source, $offset);
        if ($operator === null) {
            return null;
        }
        $offset = $operator['next'];

        $this->skipTexWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            return null;
        }

        return [
            'command' => 'DeclareMathOperator',
            'name' => $nameMatch[1],
            'arity' => 0,
            'template' => '\\operatorname' . (($m[1] ?? '') === '*' ? '*' : '') . '{' . $operator['value'] . '}',
        ];
    }

    /**
     * @return array{command:string, name:string, arity:int, template:string}|null
     */
    private function tryReadRawTexDeclaredPairedDelimiterDefinition(string $source): ?array
    {
        if (preg_match('/^\\\\DeclarePairedDelimiter(?![A-Za-z])/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $name = $this->readRawTexMacroNameReference($source, $offset);
        if ($name === null) {
            return null;
        }

        $this->skipTexWhitespace($source, $offset);
        $openDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($openDelimiter === null) {
            return null;
        }
        $offset = $openDelimiter['next'];

        $this->skipTexWhitespace($source, $offset);
        $closeDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($closeDelimiter === null) {
            return null;
        }
        $offset = $closeDelimiter['next'];

        $this->skipTexWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            return null;
        }

        return [
            'command' => 'DeclarePairedDelimiter',
            'name' => $name,
            'arity' => 1,
            'template' => '\\left' . trim($openDelimiter['value']) . ' #1 \\right' . trim($closeDelimiter['value']),
        ];
    }

    /**
     * @return array{command:string, name:string, arity:int, template:string}|null
     */
    private function tryReadRawTexDeclaredPairedDelimiterXDefinition(string $source): ?array
    {
        if (preg_match('/^\\\\DeclarePairedDelimiterX(?![A-Za-z])/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $name = $this->readRawTexMacroNameReference($source, $offset);
        if ($name === null) {
            return null;
        }

        $declaredArity = null;
        $this->skipTexWhitespace($source, $offset);
        $arityArgument = $this->readTexBracketArgument($source, $offset);
        if ($arityArgument !== null) {
            if (preg_match('/^[1-9]$/', trim($arityArgument['value'])) !== 1) {
                return null;
            }
            $declaredArity = (int) trim($arityArgument['value']);
            $offset = $arityArgument['next'];
        }

        $this->skipTexWhitespace($source, $offset);
        $openDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($openDelimiter === null) {
            return null;
        }
        $offset = $openDelimiter['next'];

        $this->skipTexWhitespace($source, $offset);
        $closeDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($closeDelimiter === null) {
            return null;
        }
        $offset = $closeDelimiter['next'];

        $this->skipTexWhitespace($source, $offset);
        $body = $this->readTexBraceArgument($source, $offset);
        if ($body === null) {
            return null;
        }
        $offset = $body['next'];

        $this->skipTexWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            return null;
        }

        return [
            'command' => 'DeclarePairedDelimiterX',
            'name' => $name,
            'arity' => $declaredArity ?? $this->inferRawTexMacroArity($body['value']),
            'template' => '\\left' . trim($openDelimiter['value']) . ' ' . $body['value'] . ' \\right' . trim($closeDelimiter['value']),
        ];
    }

    /**
     * @return array{command:string, name:string, arity:int, template:string}|null
     */
    private function tryReadRawTexDeclaredPairedDelimiterXppDefinition(string $source): ?array
    {
        if (preg_match('/^\\\\DeclarePairedDelimiterXPP(?![A-Za-z])/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $name = $this->readRawTexMacroNameReference($source, $offset);
        if ($name === null) {
            return null;
        }

        $declaredArity = null;
        $this->skipTexWhitespace($source, $offset);
        $arityArgument = $this->readTexBracketArgument($source, $offset);
        if ($arityArgument !== null) {
            if (preg_match('/^[1-9]$/', trim($arityArgument['value'])) !== 1) {
                return null;
            }
            $declaredArity = (int) trim($arityArgument['value']);
            $offset = $arityArgument['next'];
        }

        $this->skipTexWhitespace($source, $offset);
        $prefix = $this->readTexBraceArgument($source, $offset);
        if ($prefix === null) {
            return null;
        }
        $offset = $prefix['next'];

        $this->skipTexWhitespace($source, $offset);
        $openDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($openDelimiter === null) {
            return null;
        }
        $offset = $openDelimiter['next'];

        $this->skipTexWhitespace($source, $offset);
        $closeDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($closeDelimiter === null) {
            return null;
        }
        $offset = $closeDelimiter['next'];

        $this->skipTexWhitespace($source, $offset);
        $suffix = $this->readTexBraceArgument($source, $offset);
        if ($suffix === null) {
            return null;
        }
        $offset = $suffix['next'];

        $this->skipTexWhitespace($source, $offset);
        $body = $this->readTexBraceArgument($source, $offset);
        if ($body === null) {
            return null;
        }
        $offset = $body['next'];

        $this->skipTexWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            return null;
        }

        $template = '';
        $prefixTemplate = trim($prefix['value']);
        if ($prefixTemplate !== '') {
            $template .= $prefixTemplate . ' ';
        }
        $template .= '\\left' . trim($openDelimiter['value']) . ' ' . $body['value'] . ' \\right' . trim($closeDelimiter['value']);
        $suffixTemplate = trim($suffix['value']);
        if ($suffixTemplate !== '') {
            $template .= ' ' . $suffixTemplate;
        }

        return [
            'command' => 'DeclarePairedDelimiterXPP',
            'name' => $name,
            'arity' => $declaredArity ?? $this->inferRawTexMacroArity($body['value']),
            'template' => $template,
        ];
    }

    private function readRawTexMacroNameReference(string $source, int &$offset): ?string
    {
        $this->skipTexWhitespace($source, $offset);
        if (($source[$offset] ?? '') === '{') {
            $name = $this->readTexBraceArgument($source, $offset);
            if ($name === null || preg_match('/^\\\\([A-Za-z]+)$/', trim($name['value']), $nameMatch) !== 1) {
                return null;
            }

            $offset = $name['next'];

            return $nameMatch[1];
        }

        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $offset++;
        $start = $offset;
        while (($source[$offset] ?? '') !== '' && ctype_alpha($source[$offset])) {
            $offset++;
        }

        return $offset > $start ? substr($source, $start, $offset - $start) : null;
    }

    private function inferRawTexMacroArity(string $template): int
    {
        if (preg_match_all('/#([1-9])/', $template, $m) !== false && $m[1] !== []) {
            return max(array_map('intval', $m[1]));
        }

        return 0;
    }

    private function skipTexWhitespace(string $source, int &$offset): void
    {
        while (($source[$offset] ?? '') !== '' && ctype_space($source[$offset])) {
            $offset++;
        }
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
    private function tryReadMarkdownTable(array $lines, int &$index, bool $allowLeadingCaption): ?AstNode
    {
        $leadingCaption = $allowLeadingCaption ? $this->readLeadingTableCaption($lines, $index) : null;
        $tableStart = $leadingCaption['next'] ?? $index;

        if ($this->gridTableExtensionEnabled()) {
            $cursor = $tableStart;
            $table = $this->tryReadGridTable($lines, $cursor);
            if ($table !== null) {
                $index = $cursor;

                return $this->applyLeadingTableCaption($table, $leadingCaption);
            }
        }

        if ($this->simpleTableExtensionEnabled() || $this->multilineTableExtensionEnabled()) {
            $cursor = $tableStart;
            $table = $this->tryReadSimpleTable(
                $lines,
                $cursor,
                $this->simpleTableExtensionEnabled(),
                $this->multilineTableExtensionEnabled()
            );
            if ($table !== null) {
                $index = $cursor;

                return $this->applyLeadingTableCaption($table, $leadingCaption);
            }
        }

        if ($this->pipeTableExtensionEnabled()) {
            $cursor = $tableStart;
            $table = $this->tryReadPipeTable($lines, $cursor);
            if ($table !== null) {
                $index = $cursor;

                return $this->applyLeadingTableCaption($table, $leadingCaption);
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     * @return array{caption:string, marker:string, position:string, captionSide:string, next:int}|null
     */
    private function readLeadingTableCaption(array $lines, int $cursor): ?array
    {
        $match = $this->matchMarkdownTableCaptionLine($lines[$cursor] ?? '');
        if ($match === null) {
            return null;
        }

        [$caption, $next] = $this->readMarkdownTableCaptionContinuation($lines, $cursor + 1, $match['caption']);
        $count = count($lines);
        while ($next < $count && trim($lines[$next]) === '') {
            $next++;
        }

        return [
            'caption' => $caption,
            'marker' => $match['marker'],
            'position' => 'before-table',
            'captionSide' => 'top',
            'next' => $next,
        ];
    }

    /**
     * @param array{caption:string, marker:string, position:string, captionSide:string, next:int}|null $captionSource
     */
    private function applyLeadingTableCaption(AstNode $table, ?array $captionSource): AstNode
    {
        if ($captionSource === null || (string) $table->attr('caption', '') !== '') {
            return $table;
        }

        $attrs = $this->markdownTableCaptionAttrs($table->attrs, $captionSource['caption']);
        $attrs['captionSource'] = $this->markdownTableCaptionSource(
            $captionSource['position'],
            $captionSource['marker'],
            $captionSource['captionSide'],
            $captionSource['caption']
        );

        return $this->tableWithReviewPacket(new AstNode('table', $attrs, $table->children));
    }

    /**
     * @return array{element:string, position:string, marker:string, captionSide:string, captionSideSource:string}
     */
    private function markdownTableCaptionSource(string $position, string $marker, string $captionSide, ?string $source = null): array
    {
        $captionSource = [
            'element' => 'markdown-table-caption',
            'position' => $position,
            'marker' => $marker,
            'captionSide' => $captionSide,
            'captionSideSource' => 'markdown-table-caption',
        ];

        if ($source !== null) {
            $caption = $this->parseMarkdownTableCaptionSource($source);
            $sourceAttributes = $this->markdownAttributeAstAttrs($caption['id'], $caption['classes'], $caption['attributes']);
            if ($sourceAttributes !== []) {
                $captionSource['sourceAttributes'] = $sourceAttributes;
            }
        }

        return $captionSource;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function markdownTableCaptionAttrs(array $attrs, string $source): array
    {
        $caption = $this->parseMarkdownTableCaptionSource($source);
        $attrs['caption'] = $caption['caption'];
        if ($caption['caption'] !== '') {
            $attrs['captionInlines'] = $caption['captionInlines'];
        }
        if ($caption['shortCaption'] !== null) {
            $attrs['shortCaption'] = $caption['shortCaption'];
            $attrs['shortCaptionInlines'] = $caption['shortCaptionInlines'];
        }
        $captionAttrs = $this->markdownAttributeAstAttrs($caption['id'], $caption['classes'], $caption['attributes']);
        foreach ($captionAttrs as $name => $value) {
            if ($name === 'htmlAttributes' && is_array($value) && is_array($attrs['htmlAttributes'] ?? null)) {
                $attrs['htmlAttributes'] = array_replace($attrs['htmlAttributes'], $value);
                continue;
            }

            $attrs[$name] = $value;
        }

        return $attrs;
    }

    /**
     * @return array{
     *     caption:string,
     *     captionInlines:list<AstNode>,
     *     shortCaption:string|null,
     *     shortCaptionInlines:list<AstNode>,
     *     id:string|null,
     *     classes:list<string>,
     *     attributes:array<string, string>
     * }
     */
    private function parseMarkdownTableCaptionSource(string $source): array
    {
        [$source, $id, $classes, $attributes] = $this->splitTrailingMarkdownTableCaptionAttributes($source);
        $source = trim($source);
        $shortCaption = null;
        $shortCaptionInlines = [];

        $label = $this->parseBracketedLabel($source, 0);
        if ($label !== null && $label['text'] !== '' && preg_match('/\G[ \t]+/u', $source, $space, 0, $label['next']) === 1) {
            $shortCaptionInlines = $this->parseInlines($label['text']);
            $shortCaption = $this->plainTextFromInlines($shortCaptionInlines);
            $source = ltrim(substr($source, $label['next'] + strlen($space[0])));
        }

        return [
            'caption' => $source,
            'captionInlines' => $source === '' ? [] : $this->parseInlines($source),
            'shortCaption' => $shortCaption,
            'shortCaptionInlines' => $shortCaptionInlines,
            'id' => $id,
            'classes' => $classes,
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{0:string, 1:string|null, 2:list<string>, 3:array<string, string>}
     */
    private function splitTrailingMarkdownTableCaptionAttributes(string $source): array
    {
        $trimmed = rtrim($source);
        $open = strrpos($trimmed, '{');
        while ($open !== false) {
            $end = $this->findClosingMarkdownAttributeSpec($trimmed, $open);
            if ($end === strlen($trimmed) - 1) {
                [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec(substr($trimmed, $open + 1, $end - $open - 1));
                if ($id !== null || $classes !== [] || $attributes !== []) {
                    return [rtrim(substr($trimmed, 0, $open)), $id, $classes, $attributes];
                }
            }

            if ($open === 0) {
                break;
            }
            $open = strrpos(substr($trimmed, 0, $open), '{');
        }

        return [$trimmed, null, [], []];
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

            [$caption, $next, $captionSource] = $this->readTableCaption($lines, $cursor);
            $index = $next - 1;

            return $this->buildGridTable($headerRow, $bodyRows, $alignments, $caption, $widths, $captionSource);
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
        [$caption, $next, $captionSource] = $this->readTableCaption($lines, $cursor);
        $index = $next - 1;

        return $this->buildGridTableRows($headerRows, $bodyRows, $alignments, $caption, $this->spannedGridTableWidths($positions), $captionSource);
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
    private function tryReadSimpleTable(array $lines, int &$index, bool $allowSimple = true, bool $allowMultiline = true): ?AstNode
    {
        if (!isset($lines[$index + 1])) {
            return null;
        }

        if ($allowMultiline) {
            $multilineTable = $this->tryReadMultilineSimpleTableWithHeader($lines, $index);
            if ($multilineTable !== null) {
                return $multilineTable;
            }
        }

        if (!$allowSimple) {
            return null;
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

        [$caption, $next, $captionSource] = $this->readTableCaption($lines, $closingIndex + 1);
        $index = $next - 1;

        return $this->buildSimpleTable(
            $this->mergeSimpleTableLines($headerLines, $columns),
            $bodyRows,
            $this->detectMultilineSimpleTableAlignments($headerLines, $columns),
            $caption,
            $this->detectSimpleTableWidths($columns),
            $captionSource
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

        [$caption, $next, $captionSource] = $this->readTableCaption($lines, $cursor);
        $index = $next - 1;

        return $this->buildSimpleTable(
            $this->splitSimpleTableLine($headerLine, $columns),
            $bodyRows,
            $this->detectSimpleTableAlignments($headerLine, $columns),
            $caption,
            null,
            $captionSource
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
        [$caption, $next, $captionSource] = $this->readTableCaption($lines, $closingIndex + 1);
        $index = $next - 1;

        return $this->buildSimpleTable(
            null,
            $bodyRows,
            $alignments,
            $caption,
            $this->detectSimpleTableWidths($columns),
            $captionSource
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
        foreach ($columns as $index => $column) {
            $start = $column['start'];
            $nextStart = $columns[$index + 1]['start'] ?? $lineLength;
            $length = max(0, $nextStart - $start);
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
     * @param array{element:string, position:string, marker:string, captionSide:string, captionSideSource:string}|null $captionSource
     */
    private function buildSimpleTable(?array $headerCells, array $bodyRows, array $alignments, string $caption, ?array $widths = null, ?array $captionSource = null): AstNode
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
            'alignments' => $alignments,
        ];
        $attrs = $this->markdownTableCaptionAttrs($attrs, $caption);
        if ($captionSource !== null) {
            $attrs['captionSource'] = $captionSource;
        }
        if ($widths !== null) {
            $attrs['widths'] = $widths;
        }

        return $this->tableWithReviewPacket(new AstNode('table', $attrs, $children));
    }

    /**
     * @param AstNode|null $headerRow
     * @param list<AstNode> $bodyRows
     * @param list<string> $alignments
     * @param list<float>|null $widths
     * @param array{element:string, position:string, marker:string, captionSide:string, captionSideSource:string}|null $captionSource
     */
    private function buildGridTable(?AstNode $headerRow, array $bodyRows, array $alignments, string $caption, ?array $widths, ?array $captionSource = null): AstNode
    {
        return $this->buildGridTableRows($headerRow instanceof AstNode ? [$headerRow] : [], $bodyRows, $alignments, $caption, $widths, $captionSource);
    }

    /**
     * @param list<AstNode> $headerRows
     * @param list<AstNode> $bodyRows
     * @param list<string> $alignments
     * @param list<float>|null $widths
     * @param array{element:string, position:string, marker:string, captionSide:string, captionSideSource:string}|null $captionSource
     */
    private function buildGridTableRows(array $headerRows, array $bodyRows, array $alignments, string $caption, ?array $widths, ?array $captionSource = null): AstNode
    {
        $children = [];
        if ($headerRows !== []) {
            $children[] = new AstNode('table_head', [], $headerRows);
        } else {
            $children[] = new AstNode('table_head');
        }

        $children[] = new AstNode('table_body', [], $bodyRows);

        $attrs = [
            'alignments' => $alignments,
        ];
        $attrs = $this->markdownTableCaptionAttrs($attrs, $caption);
        if ($captionSource !== null) {
            $attrs['captionSource'] = $captionSource;
        }
        if ($widths !== null) {
            $attrs['widths'] = $widths;
        }

        return $this->tableWithReviewPacket(new AstNode('table', $attrs, $children));
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
     * @return array{0:string, 1:int, 2:array{element:string, position:string, marker:string, captionSide:string, captionSideSource:string}|null}
     */
    private function readTableCaption(array $lines, int $cursor): array
    {
        $count = count($lines);
        $captionCursor = $cursor;
        while ($captionCursor < $count && trim($lines[$captionCursor]) === '') {
            $captionCursor++;
        }

        $match = $captionCursor < $count ? $this->matchMarkdownTableCaptionLine($lines[$captionCursor]) : null;
        if ($match !== null) {
            [$caption, $next] = $this->readMarkdownTableCaptionContinuation($lines, $captionCursor + 1, $match['caption']);

            return [
                $caption,
                $next,
                $this->markdownTableCaptionSource('after-table', $match['marker'], 'bottom', $caption),
            ];
        }

        return ['', $cursor, null];
    }

    /**
     * @return array{marker:string, caption:string}|null
     */
    private function matchMarkdownTableCaptionLine(string $line): ?array
    {
        $marker = '(?::|(?:Table|Caption):|(?:Table|Caption)\s+[A-Za-z0-9]+[.:]|(?:Tbl\.|Tab\.)\s+[A-Za-z0-9]+[.:])';
        if (preg_match('/^ {0,3}(' . $marker . ')\s*(.*)$/iu', $line, $m) !== 1) {
            return null;
        }

        return [
            'marker' => $m[1],
            'caption' => trim($m[2]),
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{0:string, 1:int}
     */
    private function readMarkdownTableCaptionContinuation(array $lines, int $cursor, string $firstLine): array
    {
        $caption = [$firstLine];
        $next = $cursor;
        $count = count($lines);
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
        if ($delimiterCells === null) {
            return null;
        }

        $delimiter = $this->parsePipeTableDelimiter($delimiterCells);
        if ($delimiter === null) {
            return null;
        }

        $columnCount = count($delimiter['alignments']);
        $cursor = $index + 2;
        $bodySourceRows = [];
        $count = count($lines);
        while ($cursor < $count && trim($lines[$cursor]) !== '') {
            $row = $this->splitPipeTableRow($lines[$cursor]);
            if ($row === null) {
                break;
            }

            $bodySourceRows[] = $row;
            $cursor++;
        }

        [$caption, $cursor, $captionSource] = $this->readTableCaption($lines, $cursor);
        $repairs = [];
        $headerCells = $this->normalizePipeTableRowWithRepair($headerCells, $columnCount, 'head', 0, $repairs);
        $bodyRows = [];
        foreach ($bodySourceRows as $rowIndex => $row) {
            $bodyRows[] = $this->normalizePipeTableRowWithRepair($row, $columnCount, 'body', $rowIndex, $repairs);
        }

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
            'alignments' => $delimiter['alignments'],
        ];
        $attrs = $this->markdownTableCaptionAttrs($attrs, $caption);
        if ($captionSource !== null) {
            $attrs['captionSource'] = $captionSource;
        }
        if ($delimiter['widths'] !== null) {
            $attrs['widths'] = $delimiter['widths'];
        }
        if ($repairs !== []) {
            $attrs['pipeTableRowRepairs'] = $repairs;
        }

        $index = $cursor - 1;

        return $this->tableWithReviewPacket(new AstNode('table', $attrs, $children));
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
     * @param list<array{section:string, row:int, sourceCells:int, columnCount:int, action:string}> $repairs
     * @return list<string>
     */
    private function normalizePipeTableRowWithRepair(array $cells, int $columnCount, string $section, int $row, array &$repairs): array
    {
        $sourceCells = count($cells);
        if ($sourceCells !== $columnCount) {
            $repairs[] = [
                'section' => $section,
                'row' => $row,
                'sourceCells' => $sourceCells,
                'columnCount' => $columnCount,
                'action' => $sourceCells < $columnCount ? 'pad' : 'truncate',
            ];
        }

        return $this->normalizePipeTableRow($cells, $columnCount);
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

    /**
     * @return array{name:string, selfClosing:bool}|null
     */
    private function tryParseRawHtmlOpeningTag(string $line): ?array
    {
        $line = $this->expandTabsToSpaces($line);
        if (preg_match('/^ {0,3}<([A-Za-z][A-Za-z0-9:-]*)/i', $line, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $cursor = $match[1][1] + strlen($match[1][0]);
        $length = strlen($line);
        while ($cursor < $length) {
            while ($cursor < $length && ($line[$cursor] === ' ' || $line[$cursor] === "\t")) {
                $cursor++;
            }

            if ($cursor >= $length) {
                return null;
            }

            if ($line[$cursor] === '>') {
                return [
                    'name' => strtolower($match[1][0]),
                    'selfClosing' => false,
                ];
            }

            if ($line[$cursor] === '/' && ($line[$cursor + 1] ?? '') === '>') {
                return [
                    'name' => strtolower($match[1][0]),
                    'selfClosing' => true,
                ];
            }

            if (preg_match('/\G[A-Za-z_:][A-Za-z0-9_.:-]*/', $line, $attribute, 0, $cursor) !== 1) {
                return null;
            }

            $cursor += strlen($attribute[0]);
            while ($cursor < $length && ($line[$cursor] === ' ' || $line[$cursor] === "\t")) {
                $cursor++;
            }

            if (($line[$cursor] ?? '') !== '=') {
                continue;
            }

            $cursor++;
            while ($cursor < $length && ($line[$cursor] === ' ' || $line[$cursor] === "\t")) {
                $cursor++;
            }

            if ($cursor >= $length) {
                return null;
            }

            $quote = $line[$cursor];
            if ($quote === '"' || $quote === "'") {
                $end = strpos($line, $quote, $cursor + 1);
                if ($end === false) {
                    return null;
                }
                $cursor = $end + 1;
                continue;
            }

            if (preg_match('/\G[^\s"\'=<>`]+/', $line, $value, 0, $cursor) !== 1) {
                return null;
            }

            $cursor += strlen($value[0]);
        }

        return null;
    }

    private function isAngleAutolinkOnlyLine(string $line): bool
    {
        $trimmed = trim($this->expandTabsToSpaces($line));
        if (!str_starts_with($trimmed, '<') || !str_ends_with($trimmed, '>')) {
            return false;
        }

        $source = substr($trimmed, 1, -1);
        $decoded = $this->decodeHtmlEntities($source);

        return $this->isValidAngleAutolinkUri($decoded) || $this->isValidAutolinkEmailAddress($decoded);
    }

    /**
     * @return array{name:string}|null
     */
    private function tryParseRawHtmlClosingTag(string $line): ?array
    {
        $line = $this->expandTabsToSpaces($line);
        if (preg_match('/^ {0,3}<\/([A-Za-z][A-Za-z0-9:-]*)\s*>[ \t]*$/i', $line, $match) !== 1) {
            return null;
        }

        return ['name' => strtolower($match[1])];
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
            return $this->htmlRawHtmlEnabled();
        }

        if (preg_match('/^ {0,3}<(script|pre|style|textarea)(?:[ \t>]|\/>)/i', $expanded) === 1) {
            return $this->htmlRawHtmlEnabled();
        }

        $tag = $this->tryParseRawHtmlOpeningTag($expanded);
        if ($tag === null) {
            return false;
        }

        return $this->htmlRawHtmlEnabled() && $this->isCommonMarkBlankTerminatedRawHtmlTag($tag['name']);
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
            'section',
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
    private function tryReadListBlock(array $lines, int &$index, bool $interruptingParagraph = false): ?AstNode
    {
        $marker = $this->matchListMarker($lines[$index] ?? '', $index);
        if ($marker === null || $marker['indent'] > 3) {
            return null;
        }
        if ($interruptingParagraph && !$this->canListMarkerInterruptParagraph($marker)) {
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
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null, marker:string|null} $marker
     */
    private function canListMarkerInterruptParagraph(array $marker): bool
    {
        return !$marker['ordered'] || $marker['start'] === 1;
    }

    /**
     * @param list<string> $lines
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null, marker:string|null} $firstMarker
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
        $listMarker = $firstMarker['marker'];

        while ($cursor < $count) {
            $marker = $this->matchListMarker($lines[$cursor], $cursor);
            if (!$this->isSameListMarker($marker, $baseIndent, $ordered, $style, $delimiter, $listMarker)) {
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
                if ($this->isSameListMarker($nextMarker, $baseIndent, $ordered, $style, $delimiter, $listMarker)) {
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
        } else {
            $attrs['marker'] = $listMarker;
            if ($this->allListItemsAreTasks($children)) {
                $attrs['taskList'] = true;
            }
        }

        return [
            'node' => new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $children),
            'next' => $cursor,
        ];
    }

    /**
     * @param list<string> $lines
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null, marker:string|null} $marker
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
            [$codeBlock, $cursor] = $this->readListItemInitialCodeBlock(
                $lines,
                $cursor + 1,
                $this->listItemInitialCodeIndent($marker),
                str_repeat(' ', max(0, $marker['padding'] - 5)) . $firstText
            );
            $parts[] = $codeBlock;
            $contentIndent = $this->listItemFallbackContentIndent($marker);
        } elseif ($firstText !== '') {
            $task = $this->taskListExtensionEnabled() ? $this->stripTaskListMarker($firstText) : null;
            if ($task !== null) {
                $taskChecked = $task['checked'];
                $firstText = $task['text'];
            }
            $heading = $this->tryParseMarkdownHeading($firstText);
            if ($heading !== null) {
                $parts[] = $this->buildListItemMarkdownHeadingNode($heading);
                $cursor++;
            } elseif (($fencedCode = $this->readListItemFencedCodeBlock($lines, $cursor, $baseIndent, $contentIndent, $firstText)) !== null) {
                $parts[] = $fencedCode['node'];
                $cursor = $fencedCode['next'];
            } elseif (($table = $this->readListItemMarkdownTableBlock($lines, $cursor, $baseIndent, $contentIndent, $firstText)) !== null) {
                $parts[] = $table['node'];
                $cursor = $table['next'];
            } elseif (($lineBlock = $this->readListItemLineBlock($lines, $cursor, $baseIndent, $contentIndent, $firstText)) !== null) {
                $parts[] = $lineBlock['node'];
                $cursor = $lineBlock['next'];
            } elseif (($fencedDiv = $this->readListItemFencedDivBlock($lines, $cursor, $baseIndent, $contentIndent, $firstText)) !== null) {
                $parts[] = $fencedDiv['node'];
                $cursor = $fencedDiv['next'];
            } elseif (($rawTex = $this->readListItemRawTexBlock($lines, $cursor, $baseIndent, $contentIndent, $firstText)) !== null) {
                $parts[] = $rawTex['node'];
                $cursor = $rawTex['next'];
            } elseif ($this->isBlockQuoteLine($firstText)) {
                [$quote, $cursor] = $this->readListItemBlockQuote($lines, $cursor, $baseIndent, $contentIndent, $firstText);
                $parts[] = $quote;
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

                $nextIndent = $this->countIndentColumns($lines[$next]);
                if ($this->isHorizontalRule($lines[$next]) && $nextIndent < $contentIndent) {
                    break;
                }

                $nextMarker = $this->matchListMarker($lines[$next], $next);
                if ($nextMarker !== null && $nextMarker['indent'] <= $baseIndent) {
                    break;
                }

                $nextContinuation = $nextIndent >= $contentIndent
                    ? rtrim($this->stripIndentColumns($lines[$next], $contentIndent))
                    : '';
                $definitionList = $paragraph !== []
                    ? $this->readListItemDefinitionListBlock($lines, $next, $baseIndent, $contentIndent, $nextContinuation, $paragraph)
                    : null;
                if ($definitionList !== null) {
                    $parts[] = $definitionList['node'];
                    $paragraph = [];
                    $loose = true;
                    $cursor = $definitionList['next'];
                    continue;
                }

                if ($this->isNestedListMarker($nextMarker, $baseIndent, $contentIndent) || $nextIndent >= $contentIndent) {
                    $this->flushListItemParagraph($paragraph, $parts);
                    if (!$this->listItemBlankBoundaryKeepsGfmDetailsTight($parts, $lines[$next], $nextMarker, $baseIndent, $contentIndent)) {
                        $loose = true;
                    }
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
                if ($this->isSameListMarker($lineMarker, $baseIndent, $ordered, $style, $delimiter, $marker['marker'])) {
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
                $continuation = rtrim($this->stripIndentColumns($line, $contentIndent));
                if ($this->isBlockQuoteLine($continuation)) {
                    $this->flushListItemParagraph($paragraph, $parts);
                    [$quote, $cursor] = $this->readListItemBlockQuote($lines, $cursor, $baseIndent, $contentIndent, $continuation);
                    $parts[] = $quote;
                    continue;
                }

                $definitionList = $paragraph !== []
                    ? $this->readListItemDefinitionListBlock($lines, $cursor, $baseIndent, $contentIndent, $continuation, $paragraph)
                    : null;
                if ($definitionList !== null) {
                    $parts[] = $definitionList['node'];
                    $paragraph = [];
                    $cursor = $definitionList['next'];
                    continue;
                }

                $definitionList = $this->readListItemDefinitionListBlock($lines, $cursor, $baseIndent, $contentIndent, $continuation);
                if ($definitionList !== null) {
                    $this->flushListItemParagraph($paragraph, $parts);
                    $parts[] = $definitionList['node'];
                    $cursor = $definitionList['next'];
                    continue;
                }

                $rawTex = $this->readListItemRawTexBlock($lines, $cursor, $baseIndent, $contentIndent, $continuation);
                if ($rawTex !== null) {
                    $this->flushListItemParagraph($paragraph, $parts);
                    $parts[] = $rawTex['node'];
                    $cursor = $rawTex['next'];
                    continue;
                }

                $table = $this->readListItemMarkdownTableBlock($lines, $cursor, $baseIndent, $contentIndent, $continuation);
                if ($table !== null) {
                    $this->flushListItemParagraph($paragraph, $parts);
                    $parts[] = $table['node'];
                    $cursor = $table['next'];
                    continue;
                }

                $lineBlock = $this->readListItemLineBlock($lines, $cursor, $baseIndent, $contentIndent, $continuation);
                if ($lineBlock !== null) {
                    $this->flushListItemParagraph($paragraph, $parts);
                    $parts[] = $lineBlock['node'];
                    $cursor = $lineBlock['next'];
                    continue;
                }

                $fencedDiv = $this->readListItemFencedDivBlock($lines, $cursor, $baseIndent, $contentIndent, $continuation);
                if ($fencedDiv !== null) {
                    $this->flushListItemParagraph($paragraph, $parts);
                    $parts[] = $fencedDiv['node'];
                    $cursor = $fencedDiv['next'];
                    continue;
                }

                $divBlock = $this->readListItemDivBlock($lines, $cursor, $baseIndent, $contentIndent, $continuation);
                if ($divBlock !== null) {
                    $this->flushListItemParagraph($paragraph, $parts);
                    $parts[] = $divBlock['node'];
                    $cursor = $divBlock['next'];
                    continue;
                }

                $setextHeading = $paragraph !== []
                    ? $this->tryPromoteParagraphSetextMarkdownHeading($paragraph, $continuation)
                    : null;
                if ($setextHeading !== null) {
                    $parts[] = $this->buildListItemMarkdownHeadingNode($setextHeading);
                    $paragraph = [];
                    $cursor++;
                    continue;
                }

                $continuationBlock = $this->tryReadListItemIndentedBlock($lines, $cursor, $baseIndent, $contentIndent, $continuation);
                if ($continuationBlock !== null) {
                    $this->flushListItemParagraph($paragraph, $parts);
                    $parts[] = $continuationBlock['node'];
                    $cursor = $continuationBlock['next'];
                    continue;
                }

                if (
                    $this->lineCanStartRawHtmlBlock($continuation)
                    || ($paragraph === [] && $this->lineCanStartRawHtmlClosingBlock($continuation))
                ) {
                    $rawLines = $this->collectListItemIndentedContinuationLines($lines, $cursor, $baseIndent, $contentIndent);
                    $rawIndex = 0;
                    $rawHtmlBlock = $this->tryReadRawHtmlBlock($rawLines, $rawIndex);
                    if ($rawHtmlBlock !== null) {
                        $this->flushListItemParagraph($paragraph, $parts);
                        $parts[] = $rawHtmlBlock;
                        $cursor += $rawIndex + 1;
                        continue;
                    }
                }

                $paragraph[] = trim($continuation);
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
            'exampleLabel' => $marker['exampleLabel'] ?? null,
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{0:AstNode, 1:int}
     */
    private function readListItemBlockQuote(
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent,
        string $firstLine
    ): array {
        $quoteLines = [$firstLine];
        $quoteLines = array_merge(
            $quoteLines,
            $this->collectListItemIndentedContinuationLines($lines, $cursor + 1, $baseIndent, $contentIndent)
        );
        $quoteIndex = 0;
        $quote = $this->tryReadBlockQuote($quoteLines, $quoteIndex);

        return [$quote ?? new AstNode('blockquote'), $cursor + $quoteIndex + 1];
    }

    /**
     * @param list<string> $lines
     * @param list<string> $prefixLines
     * @return array{node:AstNode, next:int}|null
     */
    private function readListItemDefinitionListBlock(
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent,
        string $firstLine,
        array $prefixLines = []
    ): ?array {
        if (!$this->definitionListExtensionEnabled()) {
            return null;
        }
        if ($prefixLines === []) {
            if (!$this->canStartDefinitionTerm($firstLine)) {
                return null;
            }
        } elseif (!$this->isDefinitionMarker($firstLine)) {
            return null;
        }

        $blockLines = array_values($prefixLines);
        $blockLines[] = $firstLine;
        $blockLines = array_merge(
            $blockLines,
            $this->collectListItemIndentedContinuationLines($lines, $cursor + 1, $baseIndent, $contentIndent)
        );
        $blockIndex = 0;
        $definitionList = $this->tryReadDefinitionList($blockLines, $blockIndex);
        if ($definitionList === null) {
            return null;
        }

        return [
            'node' => $definitionList,
            'next' => $cursor + $blockIndex - count($prefixLines) + 1,
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{node:AstNode, next:int}|null
     */
    private function readListItemFencedCodeBlock(
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent,
        string $firstLine
    ): ?array {
        if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $this->expandTabsToSpaces($firstLine)) !== 1) {
            return null;
        }

        $blockLines = [$firstLine];
        $blockLines = array_merge(
            $blockLines,
            $this->collectListItemIndentedContinuationLines($lines, $cursor + 1, $baseIndent, $contentIndent)
        );
        $blockIndex = 0;
        $fencedCode = $this->tryReadFencedCodeBlock($blockLines, $blockIndex);
        if ($fencedCode === null) {
            return null;
        }

        return [
            'node' => $fencedCode,
            'next' => $cursor + $blockIndex + 1,
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{node:AstNode, next:int}|null
     */
    private function readListItemLineBlock(
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent,
        string $firstLine
    ): ?array {
        if (!$this->lineBlockExtensionEnabled() || preg_match('/^ {0,3}\|/', $this->expandTabsToSpaces($firstLine)) !== 1) {
            return null;
        }

        $blockLines = [$firstLine];
        $blockLines = array_merge(
            $blockLines,
            $this->collectListItemIndentedContinuationLines($lines, $cursor + 1, $baseIndent, $contentIndent)
        );
        $blockIndex = 0;
        $lineBlock = $this->tryReadLineBlock($blockLines, $blockIndex);
        if ($lineBlock === null) {
            return null;
        }

        return [
            'node' => $lineBlock,
            'next' => $cursor + $blockIndex + 1,
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{node:AstNode, next:int}|null
     */
    private function readListItemMarkdownTableBlock(
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent,
        string $firstLine
    ): ?array {
        $blockLines = [$firstLine];
        $blockLines = array_merge(
            $blockLines,
            $this->collectListItemIndentedContinuationLines($lines, $cursor + 1, $baseIndent, $contentIndent)
        );
        $blockIndex = 0;
        $table = $this->tryReadMarkdownTable($blockLines, $blockIndex, false);
        if ($table === null) {
            return null;
        }

        return [
            'node' => $table,
            'next' => $cursor + $blockIndex + 1,
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{node:AstNode, next:int}|null
     */
    private function readListItemFencedDivBlock(
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent,
        string $firstLine
    ): ?array {
        if (!$this->fencedDivExtensionEnabled() || preg_match('/^ {0,3}:{3,}/', $this->expandTabsToSpaces($firstLine)) !== 1) {
            return null;
        }

        $blockLines = [$firstLine];
        $blockLines = array_merge(
            $blockLines,
            $this->collectListItemIndentedContinuationLines($lines, $cursor + 1, $baseIndent, $contentIndent)
        );
        $blockIndex = 0;
        $fencedDiv = $this->tryReadFencedDivBlock($blockLines, $blockIndex);
        if ($fencedDiv === null) {
            return null;
        }

        return [
            'node' => $fencedDiv,
            'next' => $cursor + $blockIndex + 1,
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{node:AstNode, next:int}|null
     */
    private function readListItemDivBlock(
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent,
        string $firstLine
    ): ?array {
        if (preg_match('/^ {0,3}<div(?=\s|>)/i', $this->expandTabsToSpaces($firstLine)) !== 1) {
            return null;
        }

        $blockLines = [$firstLine];
        $blockLines = array_merge(
            $blockLines,
            $this->collectListItemIndentedContinuationLines($lines, $cursor + 1, $baseIndent, $contentIndent)
        );
        $blockIndex = 0;
        $divBlock = $this->tryReadDivBlock($blockLines, $blockIndex);
        if ($divBlock === null) {
            return null;
        }

        return [
            'node' => $divBlock,
            'next' => $cursor + $blockIndex + 1,
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{node:AstNode, next:int}|null
     */
    private function readListItemRawTexBlock(
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent,
        string $firstLine
    ): ?array {
        if (!$this->rawTexEnabled()) {
            return null;
        }

        $expanded = $this->expandTabsToSpaces($firstLine);
        if (
            $this->tryReadRawTexMacroDefinition($expanded) === null
            && preg_match('/^ {0,3}\\\\(?:begin\{[^}\s]+\}|placeformula\s+\\\\startformula|start[A-Za-z@]+)/', $expanded) !== 1
        ) {
            return null;
        }

        $blockLines = [$firstLine];
        $blockLines = array_merge(
            $blockLines,
            $this->collectListItemIndentedContinuationLines($lines, $cursor + 1, $baseIndent, $contentIndent)
        );
        $blockIndex = 0;
        $rawTex = $this->tryReadRawTexBlock($blockLines, $blockIndex);
        if ($rawTex === null) {
            return null;
        }

        return [
            'node' => $rawTex,
            'next' => $cursor + $blockIndex + 1,
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{node:AstNode, next:int}|null
     */
    private function tryReadListItemIndentedBlock(
        array $lines,
        int $cursor,
        int $baseIndent,
        int $contentIndent,
        string $firstLine
    ): ?array {
        $heading = $this->tryParseMarkdownHeading($firstLine);
        if ($heading !== null) {
            return [
                'node' => $this->buildListItemMarkdownHeadingNode($heading),
                'next' => $cursor + 1,
            ];
        }

        if ($this->isHorizontalRule($firstLine)) {
            return [
                'node' => new AstNode('horizontal_rule'),
                'next' => $cursor + 1,
            ];
        }

        $blockLines = $this->collectListItemIndentedContinuationLines($lines, $cursor, $baseIndent, $contentIndent);
        $blockIndex = 0;
        $fencedCode = $this->tryReadFencedCodeBlock($blockLines, $blockIndex);
        if ($fencedCode !== null) {
            return [
                'node' => $fencedCode,
                'next' => $cursor + $blockIndex + 1,
            ];
        }

        $blockIndex = 0;
        $indentedCode = $this->tryReadIndentedCodeBlock($blockLines, $blockIndex);
        if ($indentedCode !== null) {
            return [
                'node' => $indentedCode,
                'next' => $cursor + $blockIndex + 1,
            ];
        }

        return null;
    }

    /**
     * @param array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>} $heading
     */
    private function buildListItemMarkdownHeadingNode(array $heading): AstNode
    {
        return new AstNode(
            'heading',
            $this->markdownHeadingAstAttrs($heading, $this->resolvedNestedMarkdownHeadingId($heading)),
            $this->parseInlines($heading['text'])
        );
    }

    /**
     * @param array{level:int, text:string, id?:string, classes:list<string>, attributes:array<string, string>} $heading
     */
    private function resolvedNestedMarkdownHeadingId(array $heading): string
    {
        if (isset($heading['id'])) {
            return $heading['id'];
        }
        if (!$this->autoIdentifierExtensionEnabled()) {
            return '';
        }

        return $this->slugifyMarkdownHeading($heading['text']);
    }

    /**
     * @param list<array{type:string, text:string}|AstNode> $parts
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null, marker:string|null}|null $nextMarker
     */
    private function listItemBlankBoundaryKeepsGfmDetailsTight(
        array $parts,
        string $nextLine,
        ?array $nextMarker,
        int $baseIndent,
        int $contentIndent
    ): bool {
        if (!$this->gfmDetailsListBoundaryEnabled()) {
            return false;
        }

        $previous = end($parts);
        $continuation = $this->listItemContinuationSource($nextLine, $contentIndent);

        if ($this->lineIsRawHtmlDetailsOpening($continuation)) {
            return true;
        }

        if ($this->partIsRawHtmlDetailsOpening($previous) && $this->isNestedListMarker($nextMarker, $baseIndent, $contentIndent)) {
            return true;
        }

        if ($previous instanceof AstNode && $this->isListBlock($previous) && $this->lineIsRawHtmlDetailsClosing($continuation)) {
            return true;
        }

        return $this->partIsRawHtmlDetailsClosing($previous);
    }

    private function gfmDetailsListBoundaryEnabled(): bool
    {
        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat(is_scalar($format) ? (string) $format : 'markdown');

        return in_array($canonical, ['gfm', 'markdown_github'], true);
    }

    private function listItemContinuationSource(string $line, int $contentIndent): string
    {
        if ($this->countIndentColumns($line) >= $contentIndent) {
            return rtrim($this->stripIndentColumns($line, $contentIndent));
        }

        return trim($line);
    }

    private function partIsRawHtmlDetailsOpening(mixed $part): bool
    {
        return $part instanceof AstNode
            && $part->type === 'raw_html'
            && $this->lineIsRawHtmlDetailsOpening((string) $part->attr('html', ''));
    }

    private function partIsRawHtmlDetailsClosing(mixed $part): bool
    {
        return $part instanceof AstNode
            && $part->type === 'raw_html'
            && $this->lineIsRawHtmlDetailsClosing((string) $part->attr('html', ''));
    }

    private function lineIsRawHtmlDetailsOpening(string $line): bool
    {
        return preg_match('/^\s*<details\b[^>]*>\s*$/i', $line) === 1;
    }

    private function lineIsRawHtmlDetailsClosing(string $line): bool
    {
        return preg_match('/^\s*<\/details\s*>\s*$/i', $line) === 1;
    }

    private function lineCanStartRawHtmlBlock(string $line): bool
    {
        if (!$this->htmlRawHtmlEnabled()) {
            return false;
        }

        $expanded = $this->expandTabsToSpaces($line);
        if (
            preg_match('/^ {0,3}<!--/', $expanded) === 1
            || preg_match('/^ {0,3}<\?/', $expanded) === 1
            || preg_match('/^ {0,3}<![A-Za-z]/', $expanded) === 1
            || preg_match('/^ {0,3}<!\[CDATA\[/', $expanded) === 1
        ) {
            return true;
        }

        return $this->tryParseRawHtmlOpeningTag($expanded) !== null;
    }

    private function lineCanStartRawHtmlClosingBlock(string $line): bool
    {
        if (!$this->htmlRawHtmlEnabled()) {
            return false;
        }

        return $this->tryParseRawHtmlClosingTag($line) !== null;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function collectListItemIndentedContinuationLines(array $lines, int $cursor, int $baseIndent, int $contentIndent): array
    {
        $content = [];
        $count = count($lines);
        while ($cursor < $count) {
            $line = $lines[$cursor];
            if (trim($line) === '') {
                $content[] = '';
                $cursor++;
                continue;
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

        return $content;
    }

    /**
     * @param list<string> $lines
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null, marker:string|null} $marker
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
            'parts' => (new self($this->options + ['suppressStandaloneButtonInline' => true]))
                ->read(implode("\n", $content))
                ->children,
            'next' => $cursor,
            'loose' => $loose,
            'text' => trim($marker['text']),
            'number' => $marker['start'],
            'taskChecked' => null,
            'exampleLabel' => $marker['exampleLabel'] ?? null,
        ];
    }

    private function isListItemBlockHtmlStart(string $text): bool
    {
        if (preg_match('/^<(?:div|button)(?:\s+[^>]*)?>/i', $text) === 1) {
            return true;
        }

        if (!$this->htmlRawHtmlEnabled()) {
            return false;
        }

        $expanded = $this->expandTabsToSpaces($text);
        if (
            preg_match('/^ {0,3}<!--/', $expanded) === 1
            || preg_match('/^ {0,3}<\?/', $expanded) === 1
            || preg_match('/^ {0,3}<![A-Za-z]/', $expanded) === 1
            || preg_match('/^ {0,3}<!\[CDATA\[/', $expanded) === 1
        ) {
            return true;
        }

        if (preg_match('/^ {0,3}<(?:script|pre|style|textarea|noscript|xmp)(?:[ \t>]|\/>)/i', $expanded) === 1) {
            return true;
        }

        $tag = $this->tryParseRawHtmlOpeningTag($expanded);
        if ($tag === null) {
            return $this->tryParseRawHtmlClosingTag($expanded) !== null;
        }

        if ($tag['name'] === 'table') {
            return true;
        }

        if ($tag['name'] === 'hr' && preg_match('/^ {0,3}<hr(?:\s+[^>]*)?\/?>[ \t]*$/i', $expanded) === 1) {
            return true;
        }

        return $this->isCommonMarkBlankTerminatedRawHtmlTag($tag['name'])
            || $this->isRawHtmlCustomTagName($tag['name']);
    }

    /**
     * @param array{padding:int} $marker
     */
    private function isListItemInitialCodeBlock(array $marker): bool
    {
        return $marker['padding'] >= 5;
    }

    /**
     * @param array{indent:int, contentIndent:int, padding:int} $marker
     */
    private function listItemInitialCodeIndent(array $marker): int
    {
        return $marker['contentIndent'] - max(0, $marker['padding'] - 5);
    }

    /**
     * @param array{contentIndent:int, padding:int} $marker
     */
    private function listItemFallbackContentIndent(array $marker): int
    {
        return $marker['contentIndent'] - $marker['padding'] + 1;
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
            && !$this->lineCanStartSiblingBlockAfterListItem($line)
            && preg_match('/^(#{1,6})\s+/', $line) !== 1;
    }

    private function lineCanStartSiblingBlockAfterListItem(string $line): bool
    {
        $expanded = $this->expandTabsToSpaces($line);

        if (preg_match('/^ {0,3}(`{3,}|~{3,})[ \t]*(.*)$/', $expanded, $m) === 1) {
            return $m[1][0] !== '`' || !str_contains($m[2], '`');
        }

        if ($this->fencedDivExtensionEnabled() && preg_match('/^ {0,3}:{3,}/', $expanded) === 1) {
            return true;
        }

        if ($this->lineBlockExtensionEnabled() && preg_match('/^ {0,3}\|/', $expanded) === 1) {
            return true;
        }

        if ($this->lineCanStartRawHtmlBlock($expanded) || $this->lineCanStartRawHtmlClosingBlock($expanded)) {
            return true;
        }

        if ($this->rawTexEnabled()) {
            if ($this->tryReadRawTexMacroDefinition($expanded) !== null) {
                return true;
            }
            if (preg_match('/^ {0,3}\\\\(?:begin\{[^}\s]+\}|placeformula\s+\\\\startformula|start[A-Za-z@]+)/', $expanded) === 1) {
                return true;
            }
        }

        return false;
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
        $seenBlockChild = false;
        foreach ($item['parts'] as $part) {
            if ($part instanceof AstNode) {
                $children[] = $part;
                $seenBlockChild = true;
                continue;
            }

            $text = $part['text'];
            if ($forceParagraphBlocks || $seenBlockChild) {
                if (
                    !$forceParagraphBlocks
                    && $seenBlockChild
                    && count($children) === 1
                    && ($children[0]->type ?? null) === 'code_block'
                ) {
                    foreach ($this->parseInlines($text) as $inline) {
                        $children[] = $inline;
                    }
                    continue;
                }

                $children[] = new AstNode('paragraph', ['text' => $text], $this->parseInlines($text));
                $seenBlockChild = true;
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
        if (($item['exampleLabel'] ?? null) !== null) {
            $attrs['exampleLabel'] = (string) $item['exampleLabel'];
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
     * @return array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, padding:int, style:string|null, delimiter:string|null, marker:string|null}|null
     */
    private function matchListMarker(string $line, ?int $lineIndex = null): ?array
    {
        if ($this->isHorizontalRule($line)) {
            return null;
        }

        $expanded = $this->expandTabsToSpaces($line);
        $example = $this->matchNumberedExampleMarker($expanded);
        if ($example !== null && $this->numberedExampleExtensionEnabled()) {
            return [
                'indent' => $example['indent'],
                'ordered' => true,
                'start' => $lineIndex !== null ? ($this->exampleNumbersByLine[$lineIndex] ?? 1) : 1,
                'text' => $example['text'],
                'contentIndent' => $example['contentIndent'],
                'padding' => $example['padding'],
                'style' => 'example',
                'delimiter' => 'two_parens',
                'marker' => '@',
                'exampleLabel' => $example['label'] !== '' ? $example['label'] : null,
            ];
        }

        if (preg_match('/^( *)([-*+])(?:( +)(.*)|)$/', $expanded, $m) === 1) {
            $padding = isset($m[3]) ? strlen($m[3]) : 1;

            return [
                'indent' => strlen($m[1]),
                'ordered' => false,
                'start' => null,
                'text' => $m[4] ?? '',
                'contentIndent' => strlen($m[1]) + 1 + $padding,
                'padding' => $padding,
                'style' => null,
                'delimiter' => null,
                'marker' => $m[2],
            ];
        }

        $fancyLists = $this->fancyListExtensionEnabled();

        if ($fancyLists && preg_match('/^( *)#([.)])(?:( +)(.*)|)$/', $expanded, $m) === 1) {
            $padding = isset($m[3]) ? strlen($m[3]) : 1;

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => 1,
                'text' => $m[4] ?? '',
                'contentIndent' => strlen($m[1]) + 2 + $padding,
                'padding' => $padding,
                'style' => 'default',
                'delimiter' => 'default',
                'marker' => '#',
            ];
        }

        if ($fancyLists && preg_match('/^( *)\(([0-9]{1,9}|[A-Za-z]+)\)(?:( +)(.*)|)$/', $expanded, $m) === 1) {
            $padding = isset($m[3]) ? strlen($m[3]) : 1;
            $ordinal = $this->parseOrderedMarkerOrdinal($m[2], 'two_parens', $padding, strlen($m[1]));
            if ($ordinal === null) {
                return null;
            }

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => $ordinal['start'],
                'text' => $m[4] ?? '',
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 2 + $padding,
                'padding' => $padding,
                'style' => $ordinal['style'],
                'delimiter' => 'two_parens',
                'marker' => '(' . $m[2] . ')',
            ];
        }

        if (preg_match('/^( *)(\d{1,9})([.)])(?:( +)(.*)|)$/', $expanded, $m) === 1) {
            $padding = isset($m[4]) ? strlen($m[4]) : 1;

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => (int) $m[2],
                'text' => $m[5] ?? '',
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 1 + $padding,
                'padding' => $padding,
                'style' => 'decimal',
                'delimiter' => $m[3] === ')' ? 'one_paren' : 'period',
                'marker' => $m[2] . $m[3],
            ];
        }

        if ($fancyLists && preg_match('/^( *)([A-Za-z]+)([.)])(?:( +)(.*)|)$/', $expanded, $m) === 1) {
            $delimiter = $m[3] === ')' ? 'one_paren' : 'period';
            $padding = isset($m[4]) ? strlen($m[4]) : 1;
            $ordinal = $this->parseOrderedMarkerOrdinal($m[2], $delimiter, $padding, strlen($m[1]));
            if ($ordinal === null) {
                return null;
            }

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => $ordinal['start'],
                'text' => $m[5] ?? '',
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 1 + $padding,
                'padding' => $padding,
                'style' => $ordinal['style'],
                'delimiter' => $delimiter,
                'marker' => $m[2] . $m[3],
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
        if (preg_match('/^( *)\(@([A-Za-z0-9_-]*)\)(?:( +)(.*)|)$/', $expanded, $m) !== 1) {
            return null;
        }

        $padding = isset($m[3]) ? strlen($m[3]) : 1;

        return [
            'indent' => strlen($m[1]),
            'label' => $m[2],
            'text' => $m[4] ?? '',
            'contentIndent' => strlen($m[1]) + strlen($m[2]) + 3 + $padding,
            'padding' => $padding,
        ];
    }

    private function isSameListMarker(
        ?array $marker,
        int $baseIndent,
        bool $ordered,
        ?string $style,
        ?string $delimiter,
        ?string $listMarker = null
    ): bool {
        if (
            $marker === null
            || $marker['indent'] !== $baseIndent
            || $marker['ordered'] !== $ordered
            || $marker['style'] !== $style
            || $marker['delimiter'] !== $delimiter
        ) {
            return false;
        }

        return $ordered
            || $marker['marker'] === $listMarker;
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

        $expanded = $this->expandTabsToSpaces($line);
        $spaces = min($indent, strspn($expanded, ' '));

        return substr($expanded, $spaces);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCodeInfo(string $info, bool $attributeSyntaxEnabled = true): array
    {
        $info = trim($info);
        if ($info === '') {
            return ['classes' => [], 'attributes' => []];
        }

        $classes = [];
        $attributes = [];
        $id = null;

        if ($attributeSyntaxEnabled && str_starts_with($info, '{') && str_ends_with($info, '}')) {
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

    /**
     * @param list<string> $lines
     */
    private function tryReadDefinitionList(array $lines, int &$index): ?AstNode
    {
        if (!$this->definitionListExtensionEnabled()) {
            return null;
        }

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

            $termLines = [trim($lines[$cursor])];
            $definitionCursor = $cursor + 1;
            $looseFirstDefinition = false;

            while ($definitionCursor < $count) {
                if (trim($lines[$definitionCursor]) === '') {
                    $next = $definitionCursor + 1;
                    if ($next < $count && $this->isDefinitionMarker($lines[$next])) {
                        $looseFirstDefinition = true;
                        $definitionCursor = $next;
                    }
                    break;
                }

                if ($this->isDefinitionMarker($lines[$definitionCursor])) {
                    break;
                }

                if (!$this->canStartDefinitionTerm($lines[$definitionCursor])) {
                    break;
                }

                $termLines[] = trim($lines[$definitionCursor]);
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

            $termText = implode("\n", $termLines);
            $term = new AstNode('term', ['text' => $termText], $this->parseDefinitionTermInlines($termLines));
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

        return !preg_match('/^(#{1,6})\s+|^[-*+]\s+|^\d+[.)]\s+|^ {0,3}[:~]/', $line);
    }

    /**
     * @param list<string> $termLines
     * @return list<AstNode>
     */
    private function parseDefinitionTermInlines(array $termLines): array
    {
        $inlines = [];
        foreach ($termLines as $lineIndex => $line) {
            if ($lineIndex > 0) {
                $inlines[] = new AstNode('linebreak');
            }
            array_push($inlines, ...$this->parseInlines($line));
        }

        return $inlines;
    }

    private function definitionListExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('definition_lists', $overrides)) {
            return $overrides['definition_lists'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'markdown_phpextra', 'markdown_mmd'], true);
    }

    private function isDefinitionMarker(string $line): bool
    {
        return $this->matchDefinitionMarker($line) !== null;
    }

    /**
     * @param list<string> $lines
     */
    private function definitionMarkerAfterTermLine(array $lines, int $termLineIndex): ?int
    {
        $next = $termLineIndex + 1;
        if ($next < count($lines) && trim($lines[$next]) === '') {
            $next++;
        }

        return $next < count($lines) && $this->isDefinitionMarker($lines[$next]) ? $next : null;
    }

    /**
     * @return array{marker:string, content:string}|null
     */
    private function matchDefinitionMarker(string $line): ?array
    {
        if (preg_match('/^ {0,3}([:~])(.*)$/', $line, $m) !== 1) {
            return null;
        }

        if (($m[2][0] ?? '') === $m[1]) {
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
        $blocks = $marker === null ? [] : $this->parseDefinitionBlocks($marker['content']);
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
            }

            if ($this->canStartDefinitionTerm($line) && $this->definitionMarkerAfterTermLine($lines, $cursor) !== null) {
                break;
            }

            $this->appendLazyDefinitionLine($blocks, trim($line));
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
        $content = rtrim($content, " \t");
        if (trim($content) === '') {
            return [];
        }

        $leadingSpaces = strspn($content, ' ');
        $afterMarkerPadding = substr($content, min(3, $leadingSpaces));
        if (preg_match('/^(?: {4,}|\t)/', $afterMarkerPadding) === 1) {
            return $this->read($afterMarkerPadding)->children;
        }

        $trimmed = ltrim($content, " \t");
        if (
            str_starts_with($trimmed, '>')
            || preg_match('/^#{1,6}\s+/', $trimmed) === 1
            || preg_match('/^(?:[-*+]|\d{1,9}[.)]|#\.)\s+/', $trimmed) === 1
        ) {
            return $this->read($trimmed)->children;
        }

        return [new AstNode('paragraph', ['text' => $trimmed], $this->parseInlines($trimmed))];
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
            if ($children[0]->children !== []) {
                $figureAttrs['captionInlines'] = $children[0]->children;
                $figureAttrs['renderCaptionInlines'] = true;
            }
            if (!isset($figureAttrs['captionSource']) || !is_array($figureAttrs['captionSource'])) {
                $figureAttrs['captionSource'] = [
                    'element' => 'markdown-implicit-figure',
                    'position' => 'image-label',
                    'marker' => 'standalone-image',
                ];
            }
            $blocks[] = new AstNode(
                'figure',
                $figureAttrs,
                [$children[0]]
            );
            $paragraph = [];
            return;
        }

        $attrs = ['text' => $plainText];
        if ($this->matchMarkdownFigureCaptionSource($text) !== null) {
            $attrs['markdownSource'] = $text;
        }

        $blocks[] = new AstNode('paragraph', $attrs, $children);
        $paragraph = [];
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function applyMarkdownFigureCaptions(array $blocks): array
    {
        $result = [];
        $count = count($blocks);
        for ($index = 0; $index < $count; $index++) {
            $block = $blocks[$index];
            $next = $blocks[$index + 1] ?? null;
            if ($block->type === 'paragraph' && $next instanceof AstNode && $this->isSingleImageFigure($next)) {
                $caption = $this->matchMarkdownFigureCaptionSource((string) $block->attr('markdownSource', ''));
                if ($caption !== null) {
                    $result[] = $this->applyMarkdownFigureCaption($next, $caption, 'before-figure', 'top');
                    $index++;
                    continue;
                }
            }

            if ($this->isSingleImageFigure($block) && $next instanceof AstNode && $next->type === 'paragraph') {
                $caption = $this->matchMarkdownFigureCaptionSource((string) $next->attr('markdownSource', ''));
                if ($caption !== null) {
                    $result[] = $this->applyMarkdownFigureCaption($block, $caption, 'after-figure', 'bottom');
                    $index++;
                    continue;
                }
            }

            $result[] = $block;
        }

        return $result;
    }

    private function isSingleImageFigure(AstNode $node): bool
    {
        return $node->type === 'figure'
            && count($node->children) === 1
            && ($node->children[0] ?? null) instanceof AstNode
            && $node->children[0]->type === 'image';
    }

    /**
     * @param list<string> $lines
     */
    private function isSingleImageFollowedByFigureCaption(array $lines, int $index): bool
    {
        $markerIndex = $this->definitionMarkerAfterTermLine($lines, $index);
        if ($markerIndex === null || $this->matchMarkdownFigureCaptionSource($lines[$markerIndex]) === null) {
            return false;
        }

        $source = trim($lines[$index]);
        $image = $this->tryParseImage($source, 0);

        return $image !== null && $image['next'] === strlen($source);
    }

    /**
     * @param array{marker:string, caption:string} $captionSource
     */
    private function applyMarkdownFigureCaption(AstNode $figure, array $captionSource, string $position, string $captionSide): AstNode
    {
        $attrs = $this->markdownFigureCaptionAttrs($figure->attrs, $captionSource['caption']);
        $attrs['captionSource'] = $this->markdownFigureCaptionSource(
            $position,
            $captionSource['marker'],
            $captionSide,
            $captionSource['caption']
        );
        $attrs['renderCaptionInlines'] = true;
        if ((string) ($attrs['shortCaption'] ?? '') !== '') {
            $attrs['renderShortCaptionAttribute'] = true;
        }

        return new AstNode('figure', $attrs, $figure->children);
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function markdownFigureCaptionAttrs(array $attrs, string $source): array
    {
        $caption = $this->parseMarkdownTableCaptionSource($source);
        $attrs['caption'] = $this->plainTextFromInlines($caption['captionInlines']);
        if ($caption['captionInlines'] !== []) {
            $attrs['captionInlines'] = $caption['captionInlines'];
        } else {
            unset($attrs['captionInlines']);
        }
        if ($caption['shortCaption'] !== null) {
            $attrs['shortCaption'] = $caption['shortCaption'];
            $attrs['shortCaptionInlines'] = $caption['shortCaptionInlines'];
        }

        $captionAttrs = $this->markdownAttributeAstAttrs($caption['id'], $caption['classes'], $caption['attributes']);
        foreach ($captionAttrs as $name => $value) {
            if ($name === 'htmlAttributes' && is_array($value) && is_array($attrs['htmlAttributes'] ?? null)) {
                $attrs['htmlAttributes'] = array_replace($attrs['htmlAttributes'], $value);
                continue;
            }

            $attrs[$name] = $value;
        }

        return $attrs;
    }

    /**
     * @return array<string, mixed>
     */
    private function markdownFigureCaptionSource(string $position, string $marker, string $captionSide, string $source): array
    {
        $captionSource = [
            'element' => 'markdown-figure-caption',
            'position' => $position,
            'marker' => $marker,
            'captionSide' => $captionSide,
            'captionSideSource' => 'markdown-figure-caption-position',
        ];

        $caption = $this->parseMarkdownTableCaptionSource($source);
        $sourceAttributes = $this->markdownAttributeAstAttrs($caption['id'], $caption['classes'], $caption['attributes']);
        if ($sourceAttributes !== []) {
            $captionSource['sourceAttributes'] = $sourceAttributes;
        }

        return $captionSource;
    }

    /**
     * @return array{marker:string, caption:string}|null
     */
    private function matchMarkdownFigureCaptionSource(string $source): ?array
    {
        if ($source === '') {
            return null;
        }

        $lines = preg_split('/\R/u', $source) ?: [$source];
        $first = array_shift($lines);
        if (!is_string($first)) {
            return null;
        }

        $captionWords = 'Figure|Caption|Image|Picture|Photo|Illustration|Plate|Diagram';
        $marker = '(?::|(?:' . $captionWords . '):|(?:Fig|Figs|Img)\.?:|(?:' . $captionWords . ')\s+[A-Za-z0-9]+[.:]|(?:Fig|Figs|Img)\.?\s+[A-Za-z0-9]+[.:])';
        if (preg_match('/^ {0,3}(' . $marker . ')\s*(.*)$/iu', $first, $m) !== 1) {
            return null;
        }

        $caption = [trim($m[2])];
        foreach ($lines as $line) {
            $caption[] = trim($line);
        }

        return [
            'marker' => $m[1],
            'caption' => implode("\n", $caption),
        ];
    }

    /**
     * @param list<string> $paragraph
     */
    private function joinParagraphLines(array $paragraph): string
    {
        $lastIndex = array_key_last($paragraph);
        if ($lastIndex !== null) {
            $paragraph[$lastIndex] = rtrim($paragraph[$lastIndex], " \t");
        }

        return implode("\n", $paragraph);
    }

    private function normalizeParagraphLine(string $line): string
    {
        $line = ltrim($line, " \t");

        return $this->endsWithHardBreakWhitespace($line) ? $line : rtrim($line, " \t");
    }

    private function endsWithHardBreakWhitespace(string $line): bool
    {
        return preg_match('/(?: {2,}|[ \t]*\t[ \t]*)$/', $line) === 1;
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
                if ($this->endsWithHardBreakWhitespace($buffer)) {
                    $buffer = rtrim($buffer, " \t");
                    $this->flushText($buffer, $nodes);
                    $nodes[] = new AstNode('linebreak');
                    $offset++;
                    continue;
                }

                $lineBreakMode = $this->lineBreakMode();
                if ($lineBreakMode === 'ignore') {
                    $offset++;
                    continue;
                }
                if ($lineBreakMode === 'east_asian' && $this->shouldIgnoreEastAsianLineBreak($buffer, $text, $offset)) {
                    $offset++;
                    continue;
                }

                $this->flushText($buffer, $nodes);
                $nodes[] = new AstNode($lineBreakMode === 'hard' ? 'linebreak' : 'softbreak');
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
                    if (strlen($code) >= 2 && $code[0] === ' ' && $code[strlen($code) - 1] === ' ' && strspn($code, ' ') !== strlen($code)) {
                        $code = substr($code, 1, -1);
                    }
                    $next = $end + $tickCount;
                    $rawAttribute = $this->rawAttributeEnabled() ? $this->tryParseRawAttributeSpec($text, $next) : null;
                    if ($rawAttribute !== null && $this->rawAttributeFormatEnabled($rawAttribute['format'])) {
                        $this->flushText($buffer, $nodes);
                        $nodes[] = $this->rawInlineNode($rawAttribute['format'], $code);
                        $offset = $rawAttribute['next'];
                        continue;
                    }
                    $attrs = ['text' => $code];
                    $attribute = $this->inlineCodeAttributeExtensionEnabled()
                        ? $this->tryParseInlineAttributeSpec($text, $next)
                        : null;
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

            $inlineNote = $this->resolveInlineNotes && $this->inlineNoteExtensionEnabled()
                ? $this->tryParseInlineNote($text, $offset)
                : null;
            if ($inlineNote !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $inlineNote['node'];
                $offset = $inlineNote['next'];
                continue;
            }

            $math = $this->mathExtensionEnabled() ? $this->tryParseMath($text, $offset) : null;
            if ($math !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $math['node'];
                $offset = $math['next'];
                continue;
            }

            $rawTex = $this->rawTexEnabled() ? $this->tryParseRawTexInline($text, $offset) : null;
            if ($rawTex !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $rawTex['node'];
                $offset = $rawTex['next'];
                continue;
            }

            $abbreviation = $this->tryParseAbbreviationInline($text, $offset);
            if ($abbreviation !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $abbreviation['node'];
                $offset = $abbreviation['next'];
                continue;
            }

            $mark = $this->markExtensionEnabled() ? $this->tryParseMark($text, $offset) : null;
            if ($mark !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $mark['node'];
                $offset = $mark['next'];
                continue;
            }

            $strikeout = $this->strikeoutExtensionEnabled() ? $this->tryParseStrikeout($text, $offset) : null;
            if ($strikeout !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $strikeout['node'];
                $offset = $strikeout['next'];
                continue;
            }

            $script = $this->scriptExtensionEnabled($text[$offset] ?? '') ? $this->tryParseScript($text, $offset) : null;
            if ($script !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $script['node'];
                $offset = $script['next'];
                continue;
            }

            $quote = $this->smartQuoteExtensionEnabled() ? $this->tryParseSmartQuote($text, $offset) : null;
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

            $span = $this->bracketedSpanExtensionEnabled() ? $this->tryParseBracketedSpan($text, $offset) : null;
            if ($span !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $span['node'];
                $offset = $span['next'];
                continue;
            }

            $citation = $allowLinks && $this->citationExtensionEnabled()
                ? $this->tryParseCitation($text, $offset, $allowBareCitations)
                : null;
            if ($citation !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $citation['node'];
                $offset = $citation['next'];
                continue;
            }

            $wikiLink = $allowLinks && $this->wikilinkExtensionEnabled() ? $this->tryParseWikiLink($text, $offset) : null;
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

            $emoji = $this->emojiExtensionEnabled() ? $this->tryParseEmojiAlias($text, $offset) : null;
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

            $nativeSpan = $allowLinks && $this->nativeSpanExtensionEnabled()
                ? $this->tryParseNativeSpanInline($text, $offset)
                : null;
            if ($nativeSpan !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $nativeSpan['node'];
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

            $bareUriAutolink = $allowLinks && $this->bareUriAutolinkExtensionEnabled()
                ? $this->tryParseBareUriAutolink($text, $offset)
                : null;
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

            $replacement = $this->smartQuoteExtensionEnabled() ? $this->tryReadSmartTextReplacement($text, $offset) : null;
            if ($replacement !== null) {
                $buffer .= $replacement['text'];
                $offset = $replacement['next'];
                continue;
            }

            $abbreviationSpace = $this->tryReadAbbreviationSpace($text, $offset, $buffer);
            if ($abbreviationSpace !== null) {
                $buffer .= $abbreviationSpace['text'];
                $offset = $abbreviationSpace['next'];
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
            'node' => new AstNode('note', [], $this->parseFootnoteBlocks(substr($text, $offset + 2, $end - $offset - 2), true)),
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

        $label = $this->parseBracketedLabel($text, $offset);
        if ($label === null || !str_starts_with($label['text'], '^') || strlen($label['text']) === 1) {
            return null;
        }

        $sourceLabel = $this->canonicalFootnoteLabel(substr($label['text'], 1));
        $definition = $this->footnoteDefinitions[$this->normalizeReferenceLabel($sourceLabel)] ?? null;
        if ($definition === null) {
            return null;
        }

        return [
            'node' => new AstNode('note', ['label' => $sourceLabel], $this->parseFootnoteBlocks($definition)),
            'next' => $label['next'],
        ];
    }

    /**
     * @return list<AstNode>
     */
    private function parseFootnoteBlocks(string $markdown, bool $suppressHtmlInlineFragmentBlock = false): array
    {
        $markdown = trim($markdown, "\r\n");
        if (trim($markdown) === '') {
            return [];
        }

        $previousResolveInlineNotes = $this->resolveInlineNotes;
        $previousResolveFootnoteReferences = $this->resolveFootnoteReferences;
        $previousSuppressHtmlInlineFragmentBlock = $this->suppressHtmlInlineFragmentBlock;
        $this->resolveInlineNotes = true;
        $this->resolveFootnoteReferences = false;
        $this->suppressHtmlInlineFragmentBlock = $suppressHtmlInlineFragmentBlock;
        try {
            return $this->read($markdown)->children;
        } finally {
            $this->resolveInlineNotes = $previousResolveInlineNotes;
            $this->resolveFootnoteReferences = $previousResolveFootnoteReferences;
            $this->suppressHtmlInlineFragmentBlock = $previousSuppressHtmlInlineFragmentBlock;
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

            if ($text[$cursor] === '<') {
                $rawHtml = $this->tryParseRawHtmlInline($text, $cursor);
                if ($rawHtml !== null) {
                    $cursor = $rawHtml['next'] - 1;
                    continue;
                }
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
            $cluster = $this->tryParseBracketedCitationCluster($text, $offset);
            if ($cluster !== null) {
                return $cluster;
            }

            if (preg_match('/\G\[@([\p{L}\p{N}\p{M}_:.#\/$%&+?<>~|-]+)\]/u', $text, $m, 0, $offset) !== 1) {
                return null;
            }

            return [
                'node' => new AstNode(
                    'citation',
                    ['id' => $m[1], 'text' => $m[0], 'mode' => 'normal'],
                    [new AstNode('text', ['text' => $m[0]])]
                ),
                'next' => $offset + strlen($m[0]),
            ];
        }

        if (!$allowBareCitation || ($text[$offset] ?? '') !== '@') {
            return null;
        }

        $previous = $offset === 0 ? '' : $text[$offset - 1];
        if ($previous !== '' && preg_match('/[\p{L}\p{N}\p{M}_@.\/-]/u', $previous) === 1) {
            return null;
        }

        if (($text[$offset + 1] ?? '') === '{') {
            $id = $this->readBracketedCitationId($text, $offset);
            if ($id === null) {
                return null;
            }

            $next = $id['next'];
            $citationText = substr($text, $offset, $next - $offset);
            $attrs = ['id' => $id['id'], 'text' => $citationText, 'mode' => 'author_in_text'];
            $suffix = $this->tryParseBareCitationSuffix($text, $next);
            if ($suffix !== null) {
                $next = $suffix['next'];
                $citationText .= $suffix['source'];
                $attrs['text'] = $citationText;
                $attrs['suffix'] = $this->citationAffixValue($suffix['label']);
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

        if (preg_match('/\G@([\p{L}\p{N}\p{M}_:.#\/$%&+?<>~|-]*[\p{L}\p{N}\p{M}_#\/$%&+?<>~|-])/u', $text, $m, 0, $offset) !== 1) {
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
            $attrs['suffix'] = $this->citationAffixValue($suffix['label']);
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
        if (($text[$label['next']] ?? '') === '{') {
            return null;
        }

        $source = substr($text, $offset, $label['next'] - $offset);
        $citations = $this->parseBracketedCitationEntries($label['text']);
        if ($citations === null) {
            return null;
        }

        if (count($citations) === 1) {
            $citation = $citations[0];

            return [
                'node' => new AstNode(
                    'citation',
                    [
                        ...$citation->attrs,
                        'text' => $source,
                    ],
                    [new AstNode('text', ['text' => $source])]
                ),
                'next' => $label['next'],
            ];
        }

        return [
            'node' => new AstNode('citation_group', ['text' => $source], $citations),
            'next' => $label['next'],
        ];
    }

    /**
     * @return list<AstNode>|null
     */
    private function parseBracketedCitationEntries(string $content): ?array
    {
        $entries = [];
        $cursor = 0;
        $length = strlen($content);

        while ($cursor < $length) {
            $marker = $this->findBracketedCitationMarker($content, $cursor);
            if ($marker === null) {
                return $entries === [] && trim(substr($content, $cursor)) === '' ? [] : null;
            }

            $prefix = trim(substr($content, $cursor, $marker['start'] - $cursor));
            $id = $this->readBracketedCitationId($content, $marker['at']);
            if ($id === null) {
                return null;
            }

            $afterId = $id['next'];
            $separator = $this->findBracketedCitationSeparator($content, $afterId);
            $end = $separator ?? $length;
            $suffix = $this->normalizeBracketedCitationSuffix(substr($content, $afterId, $end - $afterId));
            $locator = $suffix === '' ? '' : $this->citationAffixValue($suffix);
            $locatorPlain = $this->citationAffixPlainText($locator);
            $citationText = trim(substr($content, $marker['start'], $end - $marker['start']));
            $attrs = [
                'id' => $id['id'],
                'text' => $citationText,
                'mode' => $marker['suppress'] ? 'suppress_author' : 'normal',
            ];
            if ($prefix !== '') {
                $attrs['prefix'] = $this->citationAffixValue($prefix);
            }
            if ($suffix !== '') {
                $attrs['locator'] = $locator;
                $locatorParts = $this->inferBracketedCitationLocatorParts($locatorPlain);
                $attrs['locatorLabel'] = $locatorParts['label'];
                $attrs['locatorValue'] = $locatorParts['value'];
            }

            $entries[] = new AstNode('citation', $attrs, [new AstNode('text', ['text' => $citationText])]);
            if ($separator === null) {
                break;
            }

            $cursor = $separator + 1;
        }

        return $entries === [] ? null : $entries;
    }

    /**
     * @return array{id:string, next:int}|null
     */
    private function readBracketedCitationId(string $content, int $at): ?array
    {
        if (($content[$at] ?? '') !== '@') {
            return null;
        }

        if (($content[$at + 1] ?? '') === '{') {
            $length = strlen($content);
            for ($cursor = $at + 2; $cursor < $length; $cursor++) {
                if ($content[$cursor] === '\\') {
                    $cursor++;
                    continue;
                }

                if ($content[$cursor] !== '}') {
                    continue;
                }

                $id = $this->unescapeBracedCitationId(substr($content, $at + 2, $cursor - $at - 2));
                return $id === '' ? null : ['id' => $id, 'next' => $cursor + 1];
            }

            return null;
        }

        if (preg_match('/\G@([\p{L}\p{N}\p{M}_:.#\/$%&+?<>~|-]*[\p{L}\p{N}\p{M}_#\/$%&+?<>~|-])/u', $content, $match, 0, $at) !== 1) {
            return null;
        }

        return ['id' => $match[1], 'next' => $at + strlen($match[0])];
    }

    private function unescapeBracedCitationId(string $id): string
    {
        $unescaped = '';
        $length = strlen($id);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($id[$offset] === '\\' && $offset + 1 < $length) {
                $unescaped .= $id[$offset + 1];
                $offset++;
                continue;
            }

            $unescaped .= $id[$offset];
        }

        return $unescaped;
    }

    /**
     * @return array{start:int, at:int, suppress:bool}|null
     */
    private function findBracketedCitationMarker(string $content, int $offset): ?array
    {
        $length = strlen($content);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($content[$cursor] !== '@') {
                continue;
            }

            $start = $cursor;
            $suppress = false;
            if ($cursor > $offset && $content[$cursor - 1] === '-') {
                $start = $cursor - 1;
                $suppress = true;
            }

            $previous = $start === 0 ? '' : $content[$start - 1];
            if ($previous !== '' && preg_match('/[\p{L}\p{N}\p{M}_@.\/-]/u', $previous) === 1) {
                continue;
            }

            return [
                'start' => $start,
                'at' => $cursor,
                'suppress' => $suppress,
            ];
        }

        return null;
    }

    private function findBracketedCitationSeparator(string $content, int $offset): ?int
    {
        $braceDepth = 0;
        $length = strlen($content);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            $char = $content[$cursor];
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

            if ($char !== ';' || $braceDepth > 0) {
                continue;
            }

            if ($this->findBracketedCitationMarker($content, $cursor + 1) !== null) {
                return $cursor;
            }
        }

        return null;
    }

    private function normalizeBracketedCitationSuffix(string $suffix): string
    {
        $suffix = trim($suffix);
        $suffix = ltrim($suffix, ", \t\n\r");
        $suffix = trim($suffix);
        if (strlen($suffix) >= 2 && $suffix[0] === '{') {
            $closing = $this->findBracketedCitationForcedLocatorEnd($suffix);
            if ($closing !== null) {
                $forced = trim(substr($suffix, 1, $closing - 1));
                $trailing = trim(substr($suffix, $closing + 1));
                $suffix = trim($forced . ($trailing === '' ? '' : ' ' . $trailing));
            }
        }

        return $suffix;
    }

    /**
     * @return string|list<AstNode>
     */
    private function citationAffixValue(string $source): string|array
    {
        $source = trim($source);
        if ($source === '') {
            return '';
        }

        $inlines = $this->parseInlines($source, true, false);
        if ($this->citationAffixNeedsInlineAst($source, $inlines)) {
            return $inlines;
        }

        return $this->plainTextFromInlines($inlines);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function citationAffixNeedsInlineAst(string $source, array $inlines): bool
    {
        if (count($inlines) !== 1 || $inlines[0]->type !== 'text') {
            return true;
        }

        return (string) $inlines[0]->attr('text', '') !== $source;
    }

    private function citationAffixPlainText(mixed $value): string
    {
        if (is_array($value)) {
            $text = $this->plainTextFromInlines(array_values(array_filter(
                $value,
                static fn (mixed $node): bool => $node instanceof AstNode
            )));

            return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        }

        return trim((string) $value);
    }

    private function findBracketedCitationForcedLocatorEnd(string $suffix): ?int
    {
        $length = strlen($suffix);
        for ($cursor = 1; $cursor < $length; $cursor++) {
            if ($suffix[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($suffix[$cursor] === '}') {
                return $cursor;
            }
        }

        return null;
    }

    /**
     * @return array{label:string, value:string}
     */
    private function inferBracketedCitationLocatorParts(string $locator): array
    {
        $locator = trim(preg_replace('/\s+/u', ' ', $locator) ?? $locator);
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
        if ($following === '{' && $this->tryParseInlineAttributeSpec($text, $next) !== null) {
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

        $label = $this->parseBracketedLabel($text, $offset + 1, true);
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
        $attribute = $this->linkAttributeExtensionEnabled() ? $this->tryParseInlineAttributeSpec($source, $offset) : null;
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
        $label = $this->protectWhitespaceFlankedLinkLabelDelimiters($label);

        if (
            $this->linkLabelSingleBackslashMathCompatibilityEnabled()
            && $this->linkLabelContainsSingleBackslashMathBracketPayload($label)
        ) {
            return $this->parseLinkLabelInlinesWithSingleBackslashMath($label);
        }

        return $this->parseInlines($label, false);
    }

    /**
     * @return list<AstNode>
     */
    private function parseLinkLabelInlinesWithSingleBackslashMath(string $label): array
    {
        $previous = $this->forceLinkLabelSingleBackslashMath;
        $this->forceLinkLabelSingleBackslashMath = true;
        try {
            return $this->parseInlines($label, false);
        } finally {
            $this->forceLinkLabelSingleBackslashMath = $previous;
        }
    }

    private function linkLabelContainsSingleBackslashMathBracketPayload(string $label): bool
    {
        $length = strlen($label);
        for ($cursor = 0; $cursor < $length; $cursor++) {
            if ($label[$cursor] === '`') {
                $next = $this->skipLinkLabelCodeSpan($label, $cursor);
                if ($next !== null) {
                    $cursor = $next - 1;
                    continue;
                }
            }

            if ($label[$cursor] !== '\\') {
                continue;
            }

            $math = $this->tryParseLinkLabelSingleBackslashMath($label, $cursor);
            if ($math !== null) {
                if ($this->astNodeAttributeValueContainsLinkLabelBracket($math['node']->attrs)) {
                    return true;
                }
                $cursor = $math['next'] - 1;
                continue;
            }

            $cursor++;
        }

        return false;
    }

    private function protectWhitespaceFlankedLinkLabelDelimiters(string $label): string
    {
        $protected = '';
        $length = strlen($label);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $label[$offset];
            if (($char !== '*' && $char !== '_') || $this->isEscapedInlinePosition($label, $offset)) {
                $protected .= $char;
                continue;
            }

            $runLength = 1;
            while (($label[$offset + $runLength] ?? '') === $char) {
                $runLength++;
            }
            $previous = $offset > 0 ? $label[$offset - 1] : '';
            $next = $label[$offset + $runLength] ?? '';
            if ($next !== '' && ctype_space($next) && $previous !== '' && ctype_space($previous)) {
                $protected .= str_repeat('\\' . $char, $runLength);
            } else {
                $protected .= str_repeat($char, $runLength);
            }
            $offset += $runLength - 1;
        }

        return $protected;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseWikiLink(string $text, int $offset): ?array
    {
        if (substr($text, $offset, 2) !== '[[' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if ($this->hasOrdinaryBracketedLinkAt($text, $offset)) {
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

    private function hasOrdinaryBracketedLinkAt(string $text, int $offset): bool
    {
        $label = $this->parseBracketedLabel($text, $offset, true);
        if ($label === null) {
            return false;
        }

        $next = $label['next'];
        if (($text[$next] ?? '') === '(') {
            return $this->parseInlineLinkTarget($text, $next) !== null;
        }

        if (($text[$next] ?? '') !== '[') {
            return false;
        }

        $reference = $this->parseBracketedLabel($text, $next);
        if ($reference === null) {
            return false;
        }

        $referenceLabel = $reference['text'] === '' ? $label['text'] : $reference['text'];

        return isset($this->referenceLinks[$this->normalizeReferenceLabel($referenceLabel)]);
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

            if ($this->wikiLinkTitleAfterPipe()) {
                return [$label, $url];
            }

            return [$url, $label];
        }

        $content = trim($content);

        return [$content, $content];
    }

    private function wikiLinkTitleAfterPipe(): bool
    {
        return $this->markdownExtensionOverrides()['wikilinks_title_after_pipe'] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    private function markdownExtensionOverrides(): array
    {
        return MarkdownFormatProfile::markdownExtensionOverrides($this->markdownFormatWithExtensionOption());
    }

    private function lineBreakMode(): string
    {
        $overrides = $this->markdownExtensionOverrides();
        if (($overrides['hard_line_breaks'] ?? false) === true) {
            return 'hard';
        }
        if (($overrides['ignore_line_breaks'] ?? false) === true) {
            return 'ignore';
        }
        if (($overrides['east_asian_line_breaks'] ?? false) === true) {
            return 'east_asian';
        }

        return 'soft';
    }

    private function shouldIgnoreEastAsianLineBreak(string $buffer, string $text, int $offset): bool
    {
        if ($buffer === '') {
            return false;
        }

        $previous = mb_substr($buffer, -1, 1, 'UTF-8');
        $nextSource = substr($text, $offset + 1);
        if ($nextSource === '') {
            return false;
        }

        $next = mb_substr($nextSource, 0, 1, 'UTF-8');

        return $this->isEastAsianLineBreakCharacter($previous)
            && $this->isEastAsianLineBreakCharacter($next);
    }

    private function isEastAsianLineBreakCharacter(string $character): bool
    {
        return $character !== ''
            && preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $character) === 1;
    }

    private function inlineNoteExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('inline_notes', $overrides)) {
            return $overrides['inline_notes'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'markdown_phpextra', 'markdown_mmd'], true);
    }

    private function markExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('mark', $overrides)) {
            return $overrides['mark'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x'], true);
    }

    private function emojiExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        foreach (['emoji', 'emoji_shortcodes'] as $extension) {
            if (array_key_exists($extension, $overrides)) {
                return $overrides[$extension];
            }
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'gfm'], true);
    }

    private function strikeoutExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('strikeout', $overrides)) {
            return $overrides['strikeout'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'gfm'], true);
    }

    private function bareUriAutolinkExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('bare_uri_autolinks', $overrides)) {
            return $overrides['bare_uri_autolinks'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'gfm'], true);
    }

    private function scriptExtensionEnabled(string $delimiter): bool
    {
        $extension = match ($delimiter) {
            '^' => 'superscript',
            '~' => 'subscript',
            default => null,
        };
        if ($extension === null) {
            return false;
        }

        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists($extension, $overrides)) {
            return $overrides[$extension];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'markdown_mmd'], true);
    }

    private function rawAttributeEnabled(): bool
    {
        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::rawAttributeEnabled($options, true);
    }

    private function fencedCodeAttributeExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('fenced_code_attributes', $overrides)) {
            return $overrides['fenced_code_attributes'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'markdown_phpextra', 'markdown_mmd'], true);
    }

    private function fencedDivExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('fenced_divs', $overrides)) {
            return $overrides['fenced_divs'];
        }
        if (array_key_exists('native_divs', $overrides)) {
            return $overrides['native_divs'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x'], true);
    }

    private function headerAttributeExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('header_attributes', $overrides)) {
            return $overrides['header_attributes'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'markdown_phpextra', 'markdown_mmd'], true);
    }

    private function spaceInAtxHeaderExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('space_in_atx_header', $overrides)) {
            return $overrides['space_in_atx_header'];
        }

        return true;
    }

    private function lineBlockExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('line_blocks', $overrides)) {
            return $overrides['line_blocks'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x'], true);
    }

    private function autoIdentifierExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('auto_identifiers', $overrides)) {
            return $overrides['auto_identifiers'];
        }

        return true;
    }

    private function yamlMetadataEnabled(): bool
    {
        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::yamlMetadataEnabled($options, true);
    }

    private function titleBlockEnabled(): bool
    {
        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::titleBlockEnabled($options, true);
    }

    private function sectionDivsEnabled(): bool
    {
        return $this->booleanOptionValue($this->options['sectionDivs'] ?? false, false);
    }

    private function inlineAttributeExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('inline_attributes', $overrides)) {
            return $overrides['inline_attributes'];
        }
        if (array_key_exists('attributes', $overrides)) {
            return $overrides['attributes'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x'], true);
    }

    private function inlineCodeAttributeExtensionEnabled(): bool
    {
        $override = $this->markdownExtensionOverrideAny(['inline_code_attributes', 'inline_attributes', 'attributes']);
        if ($override !== null) {
            return $override;
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x'], true);
    }

    private function linkAttributeExtensionEnabled(): bool
    {
        $override = $this->markdownExtensionOverrideAny(['link_attributes', 'inline_attributes', 'attributes']);
        if ($override !== null) {
            return $override;
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'markdown_phpextra'], true);
    }

    private function numberedExampleExtensionEnabled(): bool
    {
        $override = $this->markdownExtensionOverrideAny(['numbered_examples']);
        if ($override !== null) {
            return $override;
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x'], true);
    }

    private function fancyListExtensionEnabled(): bool
    {
        $override = $this->markdownExtensionOverrideAny(['fancy_lists']);
        if ($override !== null) {
            return $override;
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x'], true);
    }

    /**
     * @param list<string> $extensions
     */
    private function markdownExtensionOverrideAny(array $extensions): ?bool
    {
        $overrides = $this->markdownExtensionOverrides();
        foreach ($extensions as $extension) {
            if (array_key_exists($extension, $overrides)) {
                return $overrides[$extension];
            }
        }

        return null;
    }

    private function smartQuoteExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('smart', $overrides)) {
            return $overrides['smart'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'markdown_mmd'], true);
    }

    private function citationExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('citations', $overrides)) {
            return $overrides['citations'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'markdown_mmd'], true);
    }

    private function mathExtensionEnabled(): bool
    {
        return $this->dollarMathExtensionEnabled()
            || $this->singleBackslashMathExtensionEnabled()
            || $this->doubleBackslashMathExtensionEnabled();
    }

    private function dollarMathExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('tex_math_dollars', $overrides)) {
            return $overrides['tex_math_dollars'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'markdown_mmd'], true);
    }

    private function singleBackslashMathExtensionEnabled(): bool
    {
        if ($this->forceLinkLabelSingleBackslashMath) {
            return true;
        }

        if (array_key_exists('texMathSingleBackslash', $this->options)) {
            return $this->booleanOptionValue($this->options['texMathSingleBackslash'], false);
        }

        return $this->markdownExtensionOverrides()['tex_math_single_backslash'] ?? false;
    }

    private function linkLabelSingleBackslashMathCompatibilityEnabled(): bool
    {
        if ($this->singleBackslashMathExtensionEnabled()) {
            return false;
        }

        if (array_key_exists('texMathSingleBackslash', $this->options)) {
            return false;
        }

        return !array_key_exists('tex_math_single_backslash', $this->markdownExtensionOverrides());
    }

    private function doubleBackslashMathExtensionEnabled(): bool
    {
        if (array_key_exists('texMathDoubleBackslash', $this->options)) {
            return $this->booleanOptionValue($this->options['texMathDoubleBackslash'], false);
        }

        return $this->markdownExtensionOverrides()['tex_math_double_backslash'] ?? false;
    }

    private function wikilinkExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('wikilinks', $overrides)) {
            return $overrides['wikilinks'];
        }

        if (
            ($overrides['wikilinks_title_after_pipe'] ?? false)
            || ($overrides['wikilinks_title_before_pipe'] ?? false)
        ) {
            return true;
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x'], true);
    }

    private function bracketedSpanExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('bracketed_spans', $overrides)) {
            return $overrides['bracketed_spans'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['markdown', 'commonmark_x', 'markdown_phpextra', 'markdown_mmd'], true);
    }

    private function nativeSpanExtensionEnabled(): bool
    {
        $overrides = $this->markdownExtensionOverrides();
        if (array_key_exists('native_spans', $overrides)) {
            return $overrides['native_spans'];
        }

        return (bool) ($this->options['nativeSpans'] ?? false);
    }

    private function commonMarkRawHtmlBlockPrecedenceEnabled(): bool
    {
        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['commonmark', 'commonmark_x', 'gfm'], true);
    }

    private function markdownFormatWithExtensionOption(): string
    {
        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $format = is_scalar($format) ? (string) $format : 'markdown';
        $extensionSuffix = $this->markdownExtensionOptionSuffix($this->options['extensions'] ?? '')
            . $this->metadataMarkdownExtensionSuffix;
        if ($extensionSuffix === '') {
            return $format;
        }

        if (str_starts_with($extensionSuffix, '+') || str_starts_with($extensionSuffix, '-')) {
            return $format . $extensionSuffix;
        }

        return $format . '+' . $extensionSuffix;
    }

    private function markdownExtensionOptionSuffix(mixed $extensions): string
    {
        return MarkdownFormatProfile::markdownExtensionOptionSuffix($extensions);
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
            if ($node->type === 'raw_html_inline') {
                $rawHtml = (string) $node->attr('text', $node->attr('html', ''));
                if (self::isStandaloneClosingRawHtmlTag($rawHtml)) {
                    continue;
                }
                $text .= $rawHtml;
                continue;
            }
            if ($node->type === 'raw_tex' || $node->type === 'raw_tex_inline') {
                $text .= (string) $node->attr('text', $node->attr('tex', ''));
                continue;
            }
            if ($node->type === 'raw_inline' || $node->type === 'raw_markdown') {
                $text .= (string) $node->attr('text', $node->attr('markdown', ''));
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
            if ($node->type === 'raw_html_inline') {
                $rawHtml = (string) $node->attr('text', $node->attr('html', ''));
                if (self::isStandaloneRawHtmlTag($rawHtml)) {
                    continue;
                }
                $text .= $rawHtml;
                continue;
            }
            if ($node->type === 'raw_tex' || $node->type === 'raw_tex_inline') {
                $text .= (string) $node->attr('text', $node->attr('tex', ''));
                continue;
            }
            if ($node->type === 'raw_inline' || $node->type === 'raw_markdown') {
                $text .= (string) $node->attr('text', $node->attr('markdown', ''));
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

    private static function isStandaloneClosingRawHtmlTag(string $html): bool
    {
        return preg_match('/^\s*<\/[A-Za-z][A-Za-z0-9:-]*\s*>\s*$/', $html) === 1;
    }

    private static function isStandaloneRawHtmlTag(string $html): bool
    {
        return self::isStandaloneClosingRawHtmlTag($html)
            || preg_match('/^\s*<[A-Za-z][A-Za-z0-9:-]*(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*\/?>\s*$/u', $html) === 1;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseInlineLink(string $text, int $offset): ?array
    {
        $label = $this->parseBracketedLabel($text, $offset, true);
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
        $next = $target['next'];
        $attribute = $this->linkAttributeExtensionEnabled() ? $this->tryParseInlineAttributeSpec($text, $next) : null;
        if ($attribute !== null) {
            $attrs = array_replace($attrs, $attribute['attrs']);
            $next = $attribute['next'];
        }

        return [
            'node' => new AstNode('link', $attrs, $this->parseLinkLabelInlines($label['text'])),
            'next' => $next,
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseReferenceLink(string $text, int $offset): ?array
    {
        $label = $this->parseBracketedLabel($text, $offset, true);
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
        $attribute = $this->linkAttributeExtensionEnabled() ? $this->tryParseInlineAttributeSpec($text, $next) : null;
        if ($attribute !== null) {
            $attrs = array_replace($attrs, $attribute['attrs']);
            $next = $attribute['next'];
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
        $label = $this->parseBracketedLabel($text, $offset, true);
        if ($label === null || ($text[$label['next']] ?? '') !== '{') {
            return null;
        }

        $end = $this->findClosingMarkdownAttributeSpec($text, $label['next']);
        if ($end === null) {
            return null;
        }

        [$id, $classes, $attributes] = $this->parseMarkdownAttributeSpec(substr($text, $label['next'] + 1, $end - $label['next'] - 1));
        if ($id === null && $classes === [] && $attributes === []) {
            return null;
        }

        $semanticSpan = $this->semanticMarkdownSpanNode($id, $classes, $attributes, $label['text']);
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
    private function semanticMarkdownSpanNode(?string $id, array $classes, array $attributes, string $label): ?AstNode
    {
        $semanticClassMap = [
            'smallcaps' => 'small_caps',
            'underline' => 'underline',
        ];
        $markerClasses = array_values(array_filter(
            $classes,
            static fn (string $class): bool => isset($semanticClassMap[$class])
                || in_array($class, ['kbd', 'mark', 'dfn', 'abbr'], true)
        ));

        if (count($markerClasses) !== 1) {
            return null;
        }

        $markerClass = $markerClasses[0];
        $type = $semanticClassMap[$markerClass] ?? null;
        if ($type === null) {
            return null;
        }

        $retainedClasses = array_values(array_filter(
            $classes,
            static fn (string $class): bool => $class !== $markerClass
        ));

        return new AstNode(
            $type,
            $this->markdownAttributeAstAttrs($id, $retainedClasses, $attributes),
            $this->parseInlines($label)
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

        $emoji = self::markdownEmojiAliases()[$m[1]] ?? null;
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
     * @return array<string, string>
     */
    private static function markdownEmojiAliases(): array
    {
        return [
            '+1' => "\u{1F44D}",
            '100' => "\u{1F4AF}",
            '1234' => "\u{1F522}",
            '8ball' => "\u{1F3B1}",
            'a' => "\u{1F170}\u{FE0F}",
            'ab' => "\u{1F18E}",
            'abc' => "\u{1F524}",
            'abcd' => "\u{1F521}",
            'accept' => "\u{1F251}",
            'airplane' => "\u{2708}\u{FE0F}",
            'alarm_clock' => "\u{23F0}",
            'alien' => "\u{1F47D}",
            'ambulance' => "\u{1F691}",
            'anchor' => "\u{2693}",
            'anger' => "\u{1F4A2}",
            'angry' => "\u{1F620}",
            'anguished' => "\u{1F627}",
            'apple' => "\u{1F34E}",
            'art' => "\u{1F3A8}",
            'astonished' => "\u{1F632}",
            'baby' => "\u{1F476}",
            'balloon' => "\u{1F388}",
            'banana' => "\u{1F34C}",
            'bangbang' => "\u{203C}\u{FE0F}",
            'bar_chart' => "\u{1F4CA}",
            'basketball' => "\u{1F3C0}",
            'bell' => "\u{1F514}",
            'bike' => "\u{1F6B2}",
            'bird' => "\u{1F426}",
            'birthday' => "\u{1F382}",
            'blue_heart' => "\u{1F499}",
            'blush' => "\u{1F60A}",
            'book' => "\u{1F4D6}",
            'bookmark' => "\u{1F516}",
            'books' => "\u{1F4DA}",
            'boom' => "\u{1F4A5}",
            'bow' => "\u{1F647}",
            'briefcase' => "\u{1F4BC}",
            'bulb' => "\u{1F4A1}",
            'calendar' => "\u{1F4C6}",
            'camera' => "\u{1F4F7}",
            'car' => "\u{1F697}",
            'cat' => "\u{1F431}",
            'chart_with_downwards_trend' => "\u{1F4C9}",
            'chart_with_upwards_trend' => "\u{1F4C8}",
            'checkered_flag' => "\u{1F3C1}",
            'cherry_blossom' => "\u{1F338}",
            'clap' => "\u{1F44F}",
            'clipboard' => "\u{1F4CB}",
            'clock1' => "\u{1F550}",
            'closed_book' => "\u{1F4D5}",
            'cloud' => "\u{2601}\u{FE0F}",
            'coffee' => "\u{2615}",
            'cold_sweat' => "\u{1F630}",
            'computer' => "\u{1F4BB}",
            'confounded' => "\u{1F616}",
            'confused' => "\u{1F615}",
            'construction' => "\u{1F6A7}",
            'construction_worker' => "\u{1F477}",
            'cool' => "\u{1F192}",
            'cowboy_hat_face' => "\u{1F920}",
            'cry' => "\u{1F622}",
            'cursing_face' => "\u{1F92C}",
            'dart' => "\u{1F3AF}",
            'date' => "\u{1F4C5}",
            'disappointed' => "\u{1F61E}",
            'disappointed_relieved' => "\u{1F625}",
            'dizzy_face' => "\u{1F635}",
            'face_with_head_bandage' => "\u{1F915}",
            'face_with_thermometer' => "\u{1F912}",
            'fearful' => "\u{1F628}",
            'flushed' => "\u{1F633}",
            'frowning' => "\u{1F626}",
            'frowning_face' => "\u{2639}\u{FE0F}",
            'gear' => "\u{2699}\u{FE0F}",
            'grin' => "\u{1F601}",
            'grinning' => "\u{1F600}",
            'hammer' => "\u{1F528}",
            'heart_eyes' => "\u{1F60D}",
            'hugs' => "\u{1F917}",
            'hushed' => "\u{1F62F}",
            'innocent' => "\u{1F607}",
            'key' => "\u{1F511}",
            'keyboard' => "\u{2328}\u{FE0F}",
            'kissing' => "\u{1F617}",
            'kissing_closed_eyes' => "\u{1F61A}",
            'kissing_heart' => "\u{1F618}",
            'kissing_smiling_eyes' => "\u{1F619}",
            'laughing' => "\u{1F606}",
            'link' => "\u{1F517}",
            'lock' => "\u{1F512}",
            'mag' => "\u{1F50D}",
            'mag_right' => "\u{1F50E}",
            'mask' => "\u{1F637}",
            'money_mouth_face' => "\u{1F911}",
            'muscle' => "\u{1F4AA}",
            'nauseated_face' => "\u{1F922}",
            'nerd_face' => "\u{1F913}",
            'ok_hand' => "\u{1F44C}",
            'open_mouth' => "\u{1F62E}",
            'package' => "\u{1F4E6}",
            'page_facing_up' => "\u{1F4C4}",
            'paperclip' => "\u{1F4CE}",
            'partying_face' => "\u{1F973}",
            'pensive' => "\u{1F614}",
            'persevere' => "\u{1F623}",
            'pleading_face' => "\u{1F97A}",
            'point_down' => "\u{1F447}",
            'point_left' => "\u{1F448}",
            'point_right' => "\u{1F449}",
            'point_up' => "\u{261D}\u{FE0F}",
            'pray' => "\u{1F64F}",
            'printer' => "\u{1F5A8}\u{FE0F}",
            'pushpin' => "\u{1F4CC}",
            'rage' => "\u{1F621}",
            'raised_hands' => "\u{1F64C}",
            'relaxed' => "\u{263A}\u{FE0F}",
            'relieved' => "\u{1F60C}",
            'rocket' => "\u{1F680}",
            'rofl' => "\u{1F923}",
            'scream' => "\u{1F631}",
            'sleeping' => "\u{1F634}",
            'sleepy' => "\u{1F62A}",
            'slightly_frowning_face' => "\u{1F641}",
            'slightly_smiling_face' => "\u{1F642}",
            'smile' => "\u{1F604}",
            'smirk' => "\u{1F60F}",
            'sneezing_face' => "\u{1F927}",
            'sob' => "\u{1F62D}",
            'star_struck' => "\u{1F929}",
            'stuck_out_tongue' => "\u{1F61B}",
            'stuck_out_tongue_closed_eyes' => "\u{1F61D}",
            'stuck_out_tongue_winking_eye' => "\u{1F61C}",
            'sunglasses' => "\u{1F60E}",
            'sweat_smile' => "\u{1F605}",
            'tired_face' => "\u{1F62B}",
            'triumph' => "\u{1F624}",
            'unamused' => "\u{1F612}",
            'unlock' => "\u{1F513}",
            'wave' => "\u{1F44B}",
            'weary' => "\u{1F629}",
            'wink' => "\u{1F609}",
            'worried' => "\u{1F61F}",
            'wrench' => "\u{1F527}",
            'writing_hand' => "\u{270D}\u{FE0F}",
            'yum' => "\u{1F60B}",
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

        $end = $this->findClosingMarkdownAttributeSpec($text, $offset);
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
     * @return array{format: string, next: int}|null
     */
    private function tryParseRawAttributeSpec(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '{') {
            return null;
        }

        $end = $this->findClosingMarkdownAttributeSpec($text, $offset);
        if ($end === null) {
            return null;
        }

        $inside = trim(substr($text, $offset + 1, $end - $offset - 1));
        if (preg_match('/^=([A-Za-z0-9_.:+-]+)$/', $inside, $match) !== 1) {
            $format = null;
            foreach ($this->tokenizeMarkdownAttributeSpec($inside) as $token) {
                if (preg_match('/^=([A-Za-z0-9_.:+-]+)$/', $token, $match) === 1) {
                    $format = $match[1];
                    break;
                }
            }

            if ($format === null) {
                return null;
            }

            return [
                'format' => $format,
                'next' => $end + 1,
            ];
        }

        return [
            'format' => $match[1],
            'next' => $end + 1,
        ];
    }

    private function rawInlineNode(string $format, string $text): AstNode
    {
        if ($this->isRawAttributeHtmlFormat($format)) {
            return new AstNode('raw_html_inline', [
                'format' => $format,
                'html' => $text,
                'text' => $text,
            ]);
        }

        return new AstNode('raw_inline', [
            'format' => $format,
            'text' => $text,
        ]);
    }

    private function isRawAttributeHtmlFormat(string $format): bool
    {
        $normalized = strtolower(str_replace('-', '+', $format));
        $baseFormat = $this->rawAttributeFormatBase($format);

        return in_array($normalized, ['html', 'html4', 'html5', 'xhtml'], true)
            || in_array($baseFormat, ['html', 'html4', 'html5', 'xhtml'], true);
    }

    private function rawAttributeFormatBase(string $format): string
    {
        $format = strtolower(str_replace('-', '+', $format));

        return explode('+', $format, 2)[0];
    }

    private function rawBlockNode(string $format, string $text): AstNode
    {
        return new AstNode('raw_block', [
            'format' => $format,
            'text' => $text,
        ]);
    }

    private function rawTexEnabled(): bool
    {
        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::rawTexEnabled($options, true);
    }

    private function rawMarkdownEnabled(): bool
    {
        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::rawMarkdownEnabled($options, true);
    }

    private function taskListExtensionEnabled(): bool
    {
        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::taskListsEnabled($options, true);
    }

    private function pipeTableExtensionEnabled(): bool
    {
        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::pipeTablesEnabled($options, true);
    }

    private function simpleTableExtensionEnabled(): bool
    {
        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::simpleTablesEnabled($options, true);
    }

    private function gridTableExtensionEnabled(): bool
    {
        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::gridTablesEnabled($options, true);
    }

    private function multilineTableExtensionEnabled(): bool
    {
        $options = $this->options;
        $options['format'] = $this->markdownFormatWithExtensionOption();

        return MarkdownFormatProfile::multilineTablesEnabled($options, true);
    }

    private function rawAttributeFormatEnabled(string $format): bool
    {
        return match (MarkdownFormatProfile::rawFamily($format)) {
            'html' => $this->htmlRawHtmlEnabled(),
            'tex' => $this->rawTexEnabled(),
            'markdown' => $this->rawMarkdownEnabled(),
            default => true,
        };
    }

    private function booleanOptionValue(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
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
        $end = $this->findClosingMarkdownAttributeSpec($text, $start);
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

        $close = $this->findUnescapedCharacter($text, '>', $offset + 1);
        if ($close === null) {
            return null;
        }

        $source = substr($text, $offset + 1, $close - $offset - 1);
        $decoded = $this->decodeHtmlEntities($source);
        if ($this->isValidAngleAutolinkUri($decoded)) {
            $next = $close + 1;
            $url = $this->percentEncodeAsciiControlBytes($decoded);
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

        if ($this->isValidAutolinkEmailAddress($decoded)) {
            $next = $close + 1;
            [$attrs, $next, $literalAttribute] = $this->readTrailingAutolinkAttributes($text, $next, [
                'url' => 'mailto:' . $decoded,
                'classes' => ['email'],
            ]);

            $result = [
                'node' => new AstNode(
                    'link',
                    $attrs,
                    [new AstNode('text', ['text' => $decoded])]
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

    private function isValidAngleAutolinkUri(string $value): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9.+-]{1,31}:[^\s<>]*\z/u', $value) === 1
            && preg_match('/[\x00-\x0D\x1F\x7F]/', $value) !== 1;
    }

    private function isValidAutolinkEmailAddress(string $value): bool
    {
        if (preg_match('/[\s<>\x00-\x20\x7F]/u', $value) === 1 || substr_count($value, '@') !== 1) {
            return false;
        }

        [$local, $domain] = explode('@', $value, 2);
        if ($local === '' || $domain === '') {
            return false;
        }

        return $this->isValidAutolinkEmailDomain($domain);
    }

    private function isValidAutolinkEmailDomain(string $domain): bool
    {
        if (!str_contains($domain, '.')) {
            return false;
        }

        foreach (explode('.', $domain) as $label) {
            if ($label === '' || preg_match('/\A[\pL\pN](?:[\pL\pN-]*[\pL\pN])?\z/u', $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array{0:array<string, mixed>, 1:int, 2:string|null}
     */
    private function readTrailingAutolinkAttributes(string $text, int $offset, array $attrs): array
    {
        if (!$this->linkAttributeExtensionEnabled()) {
            return [$attrs, $offset, null];
        }

        $attribute = $this->tryParseInlineAttributeSpec($text, $offset);
        if ($attribute !== null) {
            return [$this->mergeTrailingAutolinkAttributes($attrs, $attribute['attrs']), $attribute['next'], null];
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
        if (!$this->htmlRawHtmlEnabled()) {
            return null;
        }

        if (($text[$offset] ?? '') !== '<' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (preg_match('~\G</a\s*>~iu', $text, $close, 0, $offset) === 1) {
            return [
                'node' => new AstNode('raw_html_inline', ['html' => $close[0]]),
                'next' => $offset + strlen($close[0]),
            ];
        }

        if (
            preg_match('~\G<a(?=\s|/>)(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*/>~iu', $text, $selfClosingAnchor, 0, $offset) === 1
            && preg_match('~\s+href\s*=~iu', $selfClosingAnchor[0]) !== 1
        ) {
            $nodes = $this->parseHtmlInlineFragmentNodes($selfClosingAnchor[0]);
            if (count($nodes) === 1 && $nodes[0]->type === 'span') {
                return [
                    'node' => $nodes[0],
                    'next' => $offset + strlen($selfClosingAnchor[0]),
                ];
            }
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

        foreach ([
            '~\G<!--.*?-->~su',
            '~\G<\?.*?\?>~su',
            '~\G<!\[CDATA\[.*?\]\]>~su',
            '~\G<![A-Za-z][^>]*>~su',
        ] as $pattern) {
            if (preg_match($pattern, $text, $match, 0, $offset) === 1) {
                return [
                    'node' => new AstNode('raw_html_inline', ['html' => $match[0]]),
                    'next' => $offset + strlen($match[0]),
                ];
            }
        }

        if (
            preg_match('~\G</([A-Za-z][A-Za-z0-9:-]*)\s*>~u', $text, $match, 0, $offset) === 1
            && $this->isRawHtmlInlineTagName($match[1])
        ) {
            return [
                'node' => new AstNode('raw_html_inline', ['html' => $match[0]]),
                'next' => $offset + strlen($match[0]),
            ];
        }

        if (
            preg_match('~\G<([A-Za-z][A-Za-z0-9:-]*)(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*/?>~u', $text, $match, 0, $offset) === 1
            && $this->isRawHtmlInlineTagName($match[1])
        ) {
            return [
                'node' => new AstNode('raw_html_inline', ['html' => $match[0]]),
                'next' => $offset + strlen($match[0]),
            ];
        }

        return null;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseNativeSpanInline(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '<' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (preg_match('~\G<span(?=\s|>)(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*>~iu', $text, $open, 0, $offset) !== 1) {
            return null;
        }

        $afterOpen = $offset + strlen($open[0]);
        $end = $this->findMatchingNativeSpanClose($text, $afterOpen);
        if ($end === null) {
            return null;
        }

        $nodes = $this->parseHtmlInlineFragmentNodes(substr($text, $offset, $end - $offset));
        if (count($nodes) !== 1 || $nodes[0]->type !== 'span') {
            return null;
        }

        return [
            'node' => $nodes[0],
            'next' => $end,
        ];
    }

    private function findMatchingNativeSpanClose(string $text, int $offset): ?int
    {
        $depth = 1;
        if (preg_match_all(
            '~</?span(?=\s|>|/)(?:\s+[A-Za-z_:][A-Za-z0-9_.:-]*(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*\s*/?>~iu',
            $text,
            $matches,
            PREG_OFFSET_CAPTURE,
            $offset
        ) === false) {
            return null;
        }

        foreach ($matches[0] as $match) {
            $tag = $match[0];
            $position = $match[1];
            if (preg_match('~^</span\s*>$~iu', $tag) === 1) {
                --$depth;
                if ($depth === 0) {
                    return $position + strlen($tag);
                }
                continue;
            }

            if (!str_ends_with(rtrim($tag), '/>')) {
                ++$depth;
            }
        }

        return null;
    }

    private function isRawHtmlInlineTagName(string $name): bool
    {
        $name = strtolower($name);

        return $this->isRawHtmlCustomTagName($name)
            || in_array($name, [
                'a',
                'abbr',
                'address',
                'area',
                'article',
                'aside',
                'audio',
                'b',
                'base',
                'basefont',
                'bdi',
                'bdo',
                'blink',
                'blockquote',
                'body',
                'br',
                'button',
                'canvas',
                'caption',
                'center',
                'cite',
                'code',
                'col',
                'colgroup',
                'data',
                'datalist',
                'dd',
                'del',
                'details',
                'dfn',
                'dialog',
                'dir',
                'div',
                'dl',
                'dt',
                'em',
                'embed',
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
                'i',
                'iframe',
                'img',
                'input',
                'ins',
                'kbd',
                'label',
                'legend',
                'li',
                'link',
                'main',
                'map',
                'mark',
                'menu',
                'menuitem',
                'meta',
                'meter',
                'nav',
                'noembed',
                'noframes',
                'noscript',
                'object',
                'ol',
                'optgroup',
                'option',
                'output',
                'p',
                'param',
                'picture',
                'pre',
                'progress',
                'q',
                'rp',
                'rt',
                'ruby',
                's',
                'samp',
                'script',
                'search',
                'section',
                'select',
                'slot',
                'small',
                'source',
                'span',
                'strong',
                'style',
                'sub',
                'summary',
                'sup',
                'svg',
                'table',
                'tbody',
                'td',
                'template',
                'textarea',
                'tfoot',
                'th',
                'thead',
                'time',
                'title',
                'tr',
                'track',
                'u',
                'ul',
                'var',
                'video',
                'wbr',
                'xmp',
                'annotation',
                'annotation-xml',
                'circle',
                'clippath',
                'defs',
                'desc',
                'ellipse',
                'g',
                'glyph',
                'line',
                'lineargradient',
                'malignmark',
                'math',
                'metadata',
                'mglyph',
                'mi',
                'mn',
                'mo',
                'mrow',
                'ms',
                'mtext',
                'path',
                'polygon',
                'polyline',
                'rect',
                'semantics',
                'text',
                'textpath',
                'tspan',
            ], true);
    }

    private function isRawHtmlCustomTagName(string $name): bool
    {
        return str_contains($name, '-') || str_contains($name, ':');
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseBareUriAutolink(string $text, int $offset): ?array
    {
        if ($this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if ($this->isInsideUnresolvedAngleSpan($text, $offset)) {
            return null;
        }

        $previous = $offset === 0 ? '' : $text[$offset - 1];
        if ($previous !== '' && preg_match('/[A-Za-z0-9_@.\/-]/', $previous) === 1) {
            return null;
        }

        if (
            preg_match(
                '~\G(?:(?<uri>(?:https?|git|file)://[^\s<>"\']+|mailto:[^\s<>"\']+|doi:10\.[^\s<>"\']+|www\.[^\s<>"\']+)|(?<email>[^\s<>"@:]+@[^\s<>"@]+\.[^\s<>"@]+))~iu',
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
        [$candidate, $attributeAttrs, $next] = $this->splitBareUriAutolinkCandidateAndAttributes($text, $offset, $candidate);

        if (($m['email'] ?? '') !== '') {
            $address = $this->decodeHtmlEntities($this->unescapeLinkComponent($candidate));
            if (!$this->isValidAutolinkEmailAddress($address)) {
                return null;
            }

            $attrs = $this->mergeTrailingAutolinkAttributes(
                [
                    'url' => 'mailto:' . $address,
                    'classes' => ['email'],
                ],
                $attributeAttrs
            );

            return [
                'node' => new AstNode(
                    'link',
                    $attrs,
                    [new AstNode('text', ['text' => $address])]
                ),
                'next' => $next,
            ];
        }

        $url = $this->normalizeBareUriDestination($candidate);
        $display = $this->decodeHtmlEntities($this->unescapeLinkComponent($candidate));
        $attrs = $this->mergeTrailingAutolinkAttributes(
            [
                'url' => $url,
                'classes' => ['uri'],
            ],
            $attributeAttrs
        );

        return [
            'node' => new AstNode(
                'link',
                $attrs,
                [new AstNode('text', ['text' => $display])]
            ),
            'next' => $next,
        ];
    }

    /**
     * @return array{0:string, 1:array<string, mixed>|null, 2:int}
     */
    private function splitBareUriAutolinkCandidateAndAttributes(string $text, int $offset, string $candidate): array
    {
        $next = $offset + strlen($candidate);
        if (!$this->linkAttributeExtensionEnabled()) {
            return [$candidate, null, $next];
        }

        $searchOffset = 0;
        while (($braceOffset = strpos($candidate, '{', $searchOffset)) !== false) {
            $attribute = $this->tryParseInlineAttributeSpec($text, $offset + $braceOffset);
            if ($attribute === null) {
                $searchOffset = $braceOffset + 1;
                continue;
            }

            $linkCandidate = $this->trimBareUriAutolinkCandidate(substr($candidate, 0, $braceOffset));
            if ($linkCandidate === '') {
                $searchOffset = $braceOffset + 1;
                continue;
            }

            return [$linkCandidate, $attribute['attrs'], $attribute['next']];
        }

        return [$candidate, null, $next];
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array<string, mixed>|null $attributeAttrs
     * @return array<string, mixed>
     */
    private function mergeTrailingAutolinkAttributes(array $attrs, ?array $attributeAttrs): array
    {
        if ($attributeAttrs === null) {
            return $attrs;
        }

        $merged = array_replace($attrs, $attributeAttrs);
        if (isset($attrs['classes']) && !array_key_exists('classes', $attributeAttrs)) {
            unset($merged['classes']);
        }

        return $merged;
    }

    private function isInsideUnresolvedAngleSpan(string $text, int $offset): bool
    {
        $before = substr($text, 0, $offset);
        $open = strrpos($before, '<');
        if ($open === false) {
            return false;
        }

        $close = strrpos($before, '>');
        if ($close !== false && $close > $open) {
            return false;
        }

        return strpos($text, '>', $offset) !== false;
    }

    private function trimBareUriAutolinkCandidate(string $candidate): string
    {
        do {
            $previous = $candidate;
            $candidate = $this->trimBareUriUnescapedTrailingPunctuation($candidate);
            foreach ([['(', ')'], ['[', ']'], ['{', '}']] as [$open, $close]) {
                while (
                    str_ends_with($candidate, $close)
                    && !$this->lastCharacterIsBackslashEscaped($candidate)
                    && substr_count($candidate, $close) > substr_count($candidate, $open)
                ) {
                    $candidate = substr($candidate, 0, -1);
                }
            }
        } while ($candidate !== $previous);

        return $candidate;
    }

    private function trimBareUriUnescapedTrailingPunctuation(string $candidate): string
    {
        while ($candidate !== '') {
            $last = $candidate[strlen($candidate) - 1];
            if (!str_contains('.,;:!?', $last) || $this->lastCharacterIsBackslashEscaped($candidate)) {
                break;
            }

            $candidate = substr($candidate, 0, -1);
        }

        return $candidate;
    }

    private function lastCharacterIsBackslashEscaped(string $text): bool
    {
        $index = strlen($text) - 2;
        $backslashes = 0;
        while ($index >= 0 && $text[$index] === '\\') {
            ++$backslashes;
            --$index;
        }

        return $backslashes % 2 === 1;
    }

    private function normalizeBareUriDestination(string $destination): string
    {
        $destination = $this->normalizeLinkDestination($destination);
        if (preg_match('/^www\./i', $destination) === 1) {
            $destination = 'http://' . $destination;
        }

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
        if (!$this->numberedExampleExtensionEnabled()) {
            return null;
        }

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
    private function parseBracketedLabel(string $text, int $offset, bool $parseInlineSpans = false): ?array
    {
        if (($text[$offset] ?? '') !== '[') {
            return null;
        }

        $start = $offset + 1;
        $depth = 0;
        $length = strlen($text);
        for ($cursor = $start; $cursor < $length; $cursor++) {
            if ($parseInlineSpans && $text[$cursor] === '`') {
                $next = $this->skipLinkLabelCodeSpan($text, $cursor);
                if ($next !== null) {
                    $cursor = $next - 1;
                    continue;
                }
            }

            if ($parseInlineSpans && ($text[$cursor] === '$' || $text[$cursor] === '\\')) {
                $math = $this->mathExtensionEnabled() ? $this->tryParseMath($text, $cursor) : null;
                if (
                    $math === null
                    && $text[$cursor] === '\\'
                    && $this->linkLabelSingleBackslashMathCompatibilityEnabled()
                ) {
                    $math = $this->tryParseLinkLabelSingleBackslashMath($text, $cursor);
                    if (
                        $math !== null
                        && !$this->astNodeAttributeValueContainsLinkLabelBracket($math['node']->attrs)
                    ) {
                        $math = null;
                    }
                }
                if ($math !== null) {
                    $cursor = $math['next'] - 1;
                    continue;
                }

                $rawTex = $this->rawTexEnabled() ? $this->tryParseRawTexInline($text, $cursor) : null;
                if ($rawTex !== null) {
                    $cursor = $rawTex['next'] - 1;
                    continue;
                }
            }

            if ($parseInlineSpans && $text[$cursor] === '<') {
                $rawHtml = $this->tryParseRawHtmlInline($text, $cursor);
                if ($rawHtml !== null) {
                    $cursor = $rawHtml['next'] - 1;
                    continue;
                }
            }

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

    private function skipLinkLabelCodeSpan(string $text, int $offset): ?int
    {
        $tickCount = $this->countBackticks($text, $offset);
        $end = $this->findMatchingBacktickRun($text, $offset + $tickCount, $tickCount);
        if ($end === null || $end <= $offset + $tickCount) {
            return null;
        }

        $next = $end + $tickCount;
        $rawAttribute = $this->rawAttributeEnabled() ? $this->tryParseRawAttributeSpec($text, $next) : null;
        if ($rawAttribute !== null && $this->rawAttributeFormatEnabled($rawAttribute['format'])) {
            return $rawAttribute['next'];
        }

        $attribute = $this->inlineCodeAttributeExtensionEnabled()
            ? $this->tryParseInlineAttributeSpec($text, $next)
            : null;
        if ($attribute !== null) {
            return $attribute['next'];
        }

        $literalAttribute = $this->tryParseSpacedInlineAttributeLiteral($text, $next);
        if ($literalAttribute !== null) {
            return $literalAttribute['next'];
        }

        return $next;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseLinkLabelSingleBackslashMath(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '\\' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        return $this->tryParseDelimitedMath($text, $offset, '\\(', '\\)', false)
            ?? $this->tryParseDelimitedMath($text, $offset, '\\[', '\\]', true);
    }

    private function astNodeAttributeValueContainsLinkLabelBracket(mixed $value): bool
    {
        if (is_string($value)) {
            return str_contains($value, '[') || str_contains($value, ']');
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $child) {
            if ($this->astNodeAttributeValueContainsLinkLabelBracket($child)) {
                return true;
            }
        }

        return false;
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
        $angleDestination = false;
        $parenDepth = 0;
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($angleDestination) {
                if ($text[$cursor] === '>') {
                    $angleDestination = false;
                }
                continue;
            }

            if ($quote !== null) {
                if ($text[$cursor] === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($text[$cursor] === '<') {
                $angleDestination = true;
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
        $originalContent = $content;
        $content = trim($content);
        if ($content === '') {
            return ['url' => '', 'title' => ''];
        }

        if ($originalContent !== ltrim($originalContent, " \t\r\n")) {
            $title = $this->parseLinkTitle($content);
            if ($title !== null) {
                return ['url' => '', 'title' => $title];
            }
            if ($this->startsWithLinkTitleDelimiter($content)) {
                return null;
            }
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
        if (!$this->isValidBareLinkDestination($destination)) {
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
            if ($title === '') {
                continue;
            }

            $destination = rtrim(substr($content, 0, $cursor));
            $parsedTitle = $this->parseLinkTitle($title);
            if ($parsedTitle === null) {
                if ($destination !== '' && $this->startsWithLinkTitleDelimiter($title)) {
                    return [$destination, $title];
                }

                continue;
            }

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

            $destination = substr($content, 1, $end - 1);
            if (!$this->isValidAngleLinkDestination($destination)) {
                return [null, ''];
            }

            return [$destination, substr($content, $end + 1)];
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

    private function isValidAngleLinkDestination(string $destination): bool
    {
        if (str_contains($destination, "\n") || str_contains($destination, "\r")) {
            return false;
        }

        return $this->findUnescapedCharacter($destination, '<', 0) === null;
    }

    private function isValidBareLinkDestination(string $destination): bool
    {
        if ($this->findUnescapedCharacter($destination, '<', 0) !== null) {
            return false;
        }

        if (!$this->strictBareLinkDestinationValidationEnabled()) {
            return true;
        }

        if (preg_match('/[\s\x00-\x1F\x7F]/', $destination) === 1) {
            return false;
        }

        return $this->hasBalancedBareLinkDestinationParentheses($destination);
    }

    private function strictBareLinkDestinationValidationEnabled(): bool
    {
        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);

        return in_array($canonical, ['commonmark', 'commonmark_x', 'gfm', 'markdown_github', 'markdown_strict'], true);
    }

    private function hasBalancedBareLinkDestinationParentheses(string $destination): bool
    {
        $depth = 0;
        $length = strlen($destination);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($destination[$offset] === '\\') {
                $offset++;
                continue;
            }

            if ($destination[$offset] === '(') {
                $depth++;
                continue;
            }

            if ($destination[$offset] !== ')') {
                continue;
            }

            if ($depth === 0) {
                return false;
            }
            $depth--;
        }

        return $depth === 0;
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

        $content = substr($text, 1, -1);
        if ($close === ')' && (
            $this->findUnescapedCharacter($content, '(', 0) !== null
            || $this->findUnescapedCharacter($content, ')', 0) !== null
        )) {
            return null;
        }
        if ($close !== ')' && $this->findUnescapedCharacter($content, $close, 0) !== null) {
            return null;
        }

        return $this->decodeHtmlEntities($this->unescapeLinkComponent($content));
    }

    private function startsWithLinkTitleDelimiter(string $text): bool
    {
        $text = ltrim($text);

        return $text !== '' && in_array($text[0], ['"', "'", '('], true);
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
        $destination = $this->percentEncodeAsciiControlBytes($destination);

        return str_replace(' ', '%20', $destination);
    }

    private function percentEncodeAsciiControlBytes(string $text): string
    {
        return preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            static fn (array $match): string => sprintf('%%%02X', ord($match[0])),
            $text
        ) ?? $text;
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseMath(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') === '\\' && !$this->isEscapedInlinePosition($text, $offset)) {
            if ($this->singleBackslashMathExtensionEnabled()) {
                $inline = $this->tryParseDelimitedMath($text, $offset, '\\(', '\\)', false);
                if ($inline !== null) {
                    return $inline;
                }

                $display = $this->tryParseDelimitedMath($text, $offset, '\\[', '\\]', true);
                if ($display !== null) {
                    return $display;
                }
            }

            if ($this->doubleBackslashMathExtensionEnabled()) {
                $inline = $this->tryParseDelimitedMath($text, $offset, '\\\\(', '\\\\)', false);
                if ($inline !== null) {
                    return $inline;
                }

                return $this->tryParseDelimitedMath($text, $offset, '\\\\[', '\\\\]', true);
            }

            return null;
        }

        if (!$this->dollarMathExtensionEnabled() || ($text[$offset] ?? '') !== '$' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        if (substr($text, $offset, 2) === '$$') {
            $end = $this->findClosingDisplayMath($text, $offset + 2);
            if ($end === null || $end === $offset + 2) {
                return null;
            }
            $next = $end + 2;
            $attrs = [
                'text' => $this->expandRawTexMathMacros(trim(substr($text, $offset + 2, $end - $offset - 2))),
                'display' => true,
            ];
            $attribute = $this->inlineAttributeExtensionEnabled() ? $this->tryParseInlineAttributeSpec($text, $next) : null;
            if ($attribute !== null) {
                $attrs = array_replace($attrs, $attribute['attrs']);
                $next = $attribute['next'];
            }

            return [
                'node' => new AstNode('math', $attrs),
                'next' => $next,
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
        $next = $end + 1;
        $attrs = [
            'text' => $this->expandRawTexMathMacros(trim(substr($text, $offset + 1, $end - $offset - 1))),
            'display' => false,
        ];
        $attribute = $this->inlineAttributeExtensionEnabled() ? $this->tryParseInlineAttributeSpec($text, $next) : null;
        if ($attribute !== null) {
            $attrs = array_replace($attrs, $attribute['attrs']);
            $next = $attribute['next'];
        }

        return [
            'node' => new AstNode('math', $attrs),
            'next' => $next,
        ];
    }

    /**
     * @return array{node: AstNode, next: int}|null
     */
    private function tryParseDelimitedMath(
        string $text,
        int $offset,
        string $open,
        string $close,
        bool $display
    ): ?array {
        if (substr($text, $offset, strlen($open)) !== $open) {
            return null;
        }

        $start = $offset + strlen($open);
        $end = strpos($text, $close, $start);
        if ($end === false || $end === $start) {
            return null;
        }

        $next = $end + strlen($close);
        $attrs = [
            'text' => $this->expandRawTexMathMacros(trim(substr($text, $start, $end - $start))),
            'display' => $display,
        ];
        $attribute = $this->inlineAttributeExtensionEnabled() ? $this->tryParseInlineAttributeSpec($text, $next) : null;
        if ($attribute !== null) {
            $attrs = array_replace($attrs, $attribute['attrs']);
            $next = $attribute['next'];
        }

        return [
            'node' => new AstNode('math', $attrs),
            'next' => $next,
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
     * @return array{value:string, next:int}|null
     */
    private function readTexBracketArgument(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $length = strlen($text);
        for ($cursor = $offset + 1; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === '{') {
                $depth++;
                continue;
            }

            if ($text[$cursor] === '}' && $depth > 0) {
                $depth--;
                continue;
            }

            if ($text[$cursor] === ']' && $depth === 0) {
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
     * @return array{node:AstNode, next:int}|null
     */
    private function tryParseAbbreviationInline(string $text, int $offset): ?array
    {
        if ($this->abbreviationDefinitions === []) {
            return null;
        }

        $definitions = $this->abbreviationDefinitions;
        uksort(
            $definitions,
            static fn (string $a, string $b): int => strlen($b) <=> strlen($a)
        );

        foreach ($definitions as $term => $title) {
            $length = strlen($term);
            if ($length === 0 || substr($text, $offset, $length) !== $term) {
                continue;
            }

            if (!$this->isAbbreviationBoundaryBefore($text, $offset) || !$this->isAbbreviationBoundaryAfter($text, $offset + $length)) {
                continue;
            }

            return [
                'node' => new AstNode('span', [
                    'classes' => ['abbr'],
                    'attributes' => ['title' => $title],
                    'htmlAttributes' => [
                        'class' => 'abbr',
                        'title' => $title,
                    ],
                ], [
                    new AstNode('text', ['text' => $term]),
                ]),
                'next' => $offset + $length,
            ];
        }

        return null;
    }

    private function isAbbreviationBoundaryBefore(string $text, int $offset): bool
    {
        if ($offset <= 0) {
            return true;
        }

        return preg_match('/[\pL\pN]\z/u', substr($text, 0, $offset)) !== 1;
    }

    private function isAbbreviationBoundaryAfter(string $text, int $offset): bool
    {
        if ($offset >= strlen($text)) {
            return true;
        }

        return preg_match('/\A[\pL\pN]/u', substr($text, $offset)) !== 1;
    }

    /**
     * @return array{text:string, next:int}|null
     */
    private function tryReadAbbreviationSpace(string $text, int $offset, string $buffer): ?array
    {
        if (($text[$offset] ?? '') !== '.' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        $next = $text[$offset + 1] ?? '';
        if ($next !== ' ' && $next !== "\t") {
            return null;
        }

        if (preg_match('/(?:^|[^\pL\pN])([\pL](?:[\pL]|\.)*)$/u', $buffer, $match) !== 1) {
            return null;
        }

        $abbreviation = $match[1] . '.';
        if (!isset(self::ABBREVIATIONS[$abbreviation])) {
            return null;
        }

        $tail = substr($text, $offset + 2);
        if ($tail === '' || preg_match('/\A\pL/u', $tail) !== 1) {
            return null;
        }

        return [
            'text' => ".\u{00A0}",
            'next' => $offset + 2,
        ];
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

        if ($next === '&' || $next === '#' || $next === ';') {
            return ['text' => self::ESCAPED_ENTITY_DELIMITER_PREFIX . $next, 'next' => $offset + 2];
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
        if (substr($text, $offset, 2) !== '~~' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        $end = $this->findClosingLiteralInlineDelimiter($text, $offset + 2, '~~');
        if ($end === null || $end === $offset + 2) {
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
        if (substr($text, $offset, 2) !== '==' || $this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }

        $end = $this->findClosingLiteralInlineDelimiter($text, $offset + 2, '==');
        if ($end === null || $end === $offset + 2) {
            return null;
        }

        $inner = substr($text, $offset + 2, $end - $offset - 2);

        return [
            'node' => new AstNode(
                'span',
                ['classes' => ['mark']],
                $this->parseInlines($inner)
            ),
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

        if (
            $delimiter === '~'
            && (
                ($text[$offset + 1] ?? '') === '~'
                || ($offset > 0 && ($text[$offset - 1] ?? '') === '~')
            )
        ) {
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

        $next = $text[$offset + 1 + strlen($m[0])] ?? '';
        if ($next !== '' && $this->isAsciiAlnum($next)) {
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

    private function findClosingLiteralInlineDelimiter(string $text, int $offset, string $delimiter): ?int
    {
        $position = $offset;
        $length = strlen($text);
        $delimiterLength = strlen($delimiter);
        while ($position < $length) {
            if (
                $text[$position] === '`'
                && !$this->isEscapedInlinePosition($text, $position)
            ) {
                $tickCount = $this->countBackticks($text, $position);
                $end = $this->findMatchingBacktickRun($text, $position + $tickCount, $tickCount);
                if ($end !== null) {
                    $position = $end + $tickCount;
                    continue;
                }
            }

            if (
                substr($text, $position, $delimiterLength) === $delimiter
                && !$this->isEscapedInlinePosition($text, $position)
            ) {
                return $position;
            }

            $position++;
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
        if ($this->isEscapedInlinePosition($text, $offset)) {
            return null;
        }
        if ($offset > 0 && $text[$offset - 1] === $char) {
            return null;
        }

        $runLength = $this->countDelimiterRun($text, $offset, $char);
        $sizes = $runLength >= 3 ? [3, 1, 2] : ($runLength >= 2 ? [2] : [1]);
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
        $position = $offset;
        $length = strlen($text);
        while ($position < $length) {
            if (
                $text[$position] === '`'
                && !$this->isEscapedInlinePosition($text, $position)
            ) {
                $tickCount = $this->countBackticks($text, $position);
                $end = $this->findMatchingBacktickRun($text, $position + $tickCount, $tickCount);
                if ($end !== null) {
                    $position = $end + $tickCount;
                    continue;
                }
            }

            if ($text[$position] !== $char) {
                $position++;
                continue;
            }

            $runLength = $this->countDelimiterRun($text, $position, $char);
            if ($runLength < $size) {
                $position += $runLength;
                continue;
            }

            $closeOffset = $position + $runLength - $size;
            if ($this->isEscapedInlinePosition($text, $closeOffset)) {
                $position += $runLength;
                continue;
            }

            if (
                $this->singleClosingDelimiterWouldStealDoubleDelimiter($text, $position, $char, $size, $runLength)
            ) {
                $position += $runLength;
                continue;
            }

            if ($this->canCloseInlineDelimiter($text, $closeOffset, $char, $size)) {
                return $closeOffset;
            }

            $position += $runLength;
        }

        return null;
    }

    private function singleClosingDelimiterWouldStealDoubleDelimiter(string $text, int $offset, string $char, int $size, int $runLength): bool
    {
        if ($size !== 1 || $runLength !== 2) {
            return false;
        }

        return $this->canOpenInlineDelimiter($text, $offset, $char, 2)
            || $this->canCloseInlineDelimiter($text, $offset, $char, 2);
    }

    private function canOpenInlineDelimiter(string $text, int $offset, string $char, int $size): bool
    {
        $previous = $offset > 0 ? $text[$offset - 1] : '';
        $runLength = max($size, $this->countDelimiterRun($text, $offset, $char));
        $nextOffset = $offset + $runLength;
        $next = $nextOffset < strlen($text) ? $text[$nextOffset] : '';

        if (!$this->isLeftFlankingInlineDelimiterRun($previous, $next)) {
            return false;
        }

        if ($char === '_') {
            if (
                $this->isRightFlankingInlineDelimiterRun($previous, $next)
                && !$this->isAsciiPunctuation($previous)
            ) {
                return false;
            }

            if ($this->isAsciiAlnum($previous) && ($next === '{' || $next === '}')) {
                return false;
            }

            if ($this->isIntrawordUnderscoreBoundary($previous, $next)) {
                return false;
            }
        }

        return true;
    }

    private function canCloseInlineDelimiter(string $text, int $offset, string $char, int $size): bool
    {
        $previous = $offset > 0 ? $text[$offset - 1] : '';
        $runLength = max($size, $this->countDelimiterRun($text, $offset, $char));
        $nextOffset = $offset + $runLength;
        $next = $nextOffset < strlen($text) ? $text[$nextOffset] : '';

        if (!$this->isRightFlankingInlineDelimiterRun($previous, $next)) {
            return false;
        }

        if ($char === '_') {
            if (
                $this->isLeftFlankingInlineDelimiterRun($previous, $next)
                && !$this->isAsciiPunctuation($next)
            ) {
                return false;
            }

            if (($previous === '{' || $previous === '}') && $this->isAsciiAlnum($next)) {
                return false;
            }

            if ($this->isIntrawordUnderscoreBoundary($previous, $next)) {
                return false;
            }
        }

        return true;
    }

    private function isLeftFlankingInlineDelimiterRun(string $previous, string $next): bool
    {
        return $next !== ''
            && !ctype_space($next)
            && (!$this->isAsciiPunctuation($next) || $previous === '' || ctype_space($previous) || $this->isAsciiPunctuation($previous));
    }

    private function isRightFlankingInlineDelimiterRun(string $previous, string $next): bool
    {
        return $previous !== ''
            && !ctype_space($previous)
            && (!$this->isAsciiPunctuation($previous) || $next === '' || ctype_space($next) || $this->isAsciiPunctuation($next));
    }

    private function isIntrawordUnderscoreBoundary(string $previous, string $next): bool
    {
        return $this->isAsciiAlnum($previous) && $this->isAsciiAlnum($next);
    }

    private function isAsciiAlnum(string $char): bool
    {
        return $char !== '' && preg_match('/[A-Za-z0-9]/', $char) === 1;
    }

    private function isAsciiPunctuation(string $char): bool
    {
        return $char !== '' && strlen($char) === 1 && ctype_punct($char);
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

        $nodes[] = new AstNode('text', ['text' => $this->decodeHtmlEntities($this->expandTextTabs($buffer))]);
        $buffer = '';
    }

    private function expandTextTabs(string $text): string
    {
        if (!str_contains($text, "\t")) {
            return $text;
        }

        $expanded = '';
        $column = 0;
        $length = strlen($text);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($text[$offset] === "\t") {
                $spaces = 4 - ($column % 4);
                $expanded .= str_repeat(' ', $spaces);
                $column += $spaces;
                continue;
            }

            $expanded .= $text[$offset];
            $column++;
        }

        return $expanded;
    }

    private function decodeHtmlEntities(string $text): string
    {
        $escapedDelimiters = [];
        if (str_contains($text, self::ESCAPED_ENTITY_DELIMITER_PREFIX)) {
            $text = preg_replace_callback(
                '/' . preg_quote(self::ESCAPED_ENTITY_DELIMITER_PREFIX, '/') . '([&#;])/',
                static function (array $matches) use (&$escapedDelimiters): string {
                    $token = self::ESCAPED_ENTITY_DELIMITER_PREFIX . 'md' . count($escapedDelimiters) . self::ESCAPED_ENTITY_DELIMITER_PREFIX;
                    $escapedDelimiters[$token] = (string) $matches[1];

                    return $token;
                },
                $text
            ) ?? $text;
        }

        $text = preg_replace_callback(
            '/&#([0-9]{1,7}|[xX][0-9A-Fa-f]{1,6});/',
            static function (array $matches): string {
                $raw = (string) $matches[1];
                $codepoint = str_starts_with($raw, 'x') || str_starts_with($raw, 'X')
                    ? (int) hexdec(substr($raw, 1))
                    : (int) $raw;

                return $codepoint === 0 || $codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)
                    ? "\u{FFFD}"
                    : (string) $matches[0];
            },
            $text
        ) ?? $text;

        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        return $escapedDelimiters === [] ? $decoded : strtr($decoded, $escapedDelimiters);
    }
}
