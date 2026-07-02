<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownReader
{
    private const MARKDOWN_ESCAPABLE_ASCII_PUNCTUATION = "!\"#$%&'()*+,-./:;<=>?@[\\]^_`{|}~";
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

    /** @var array<string, int> */
    private array $exampleReferences = [];

    /** @var array<int, int> */
    private array $exampleNumbersByLine = [];

    /** @var array<string, array{arity:int, template:string}> */
    private array $rawTexMacros = [];

    private ?string $htmlBaseHref = null;

    /** @var array<string, list<AstNode>> */
    private array $htmlFootnoteDefinitions = [];

    private bool $resolveFootnoteReferences = true;

    private int $htmlQuoteDepth = 0;

    /**
     * @param array{
     *     literateHaskell?: bool,
     *     format?: string,
     *     extensions?: list<string>|array<string, bool>,
     *     commonmarkAutolinks?: bool,
     *     commonmark_autolinks?: bool,
     *     htmlNativeDivs?: bool,
     *     htmlRawHtml?: bool,
     *     rawHtml?: bool,
     *     htmlIframeResources?: array<string, string|array{mime?: string, contentType?: string, body?: string, content?: string}>
     * } $options
     */
    public function __construct(private readonly array $options = [])
    {
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
        $previousHtmlQuoteDepth = $this->htmlQuoteDepth;
        $this->htmlQuoteDepth = 0;
        $documentAttrs = [];
        [$lines, $titleBlock] = $this->extractTitleBlock($lines);
        [$lines, $references, $footnotes] = $this->extractReferenceDefinitions($lines);
        $lines = $this->splitMixedHtmlFlowLines($lines);
        [$exampleReferences, $exampleNumbersByLine] = $this->collectNumberedExampleReferences($lines);
        [$markdownHeadingIds, $implicitHeadingReferences] = $this->collectMarkdownHeadingReferences($lines);
        $this->referenceLinks = array_replace($previousReferenceLinks, $implicitHeadingReferences, $references);
        $this->footnoteDefinitions = array_replace($previousFootnoteDefinitions, $footnotes);
        $this->exampleReferences = array_replace($previousExampleReferences, $exampleReferences);
        $this->exampleNumbersByLine = $exampleNumbersByLine;
        if ($titleBlock !== null) {
            $documentAttrs = array_replace_recursive($documentAttrs, $this->buildTitleBlockAttrs($titleBlock));
        }

        for ($index = 0, $count = count($lines); $index < $count; $index++) {
            $line = $lines[$index];
            if ($paragraph === [] && $listStack === [] && $this->isNeutralWriterSeparatorLine($line)) {
                continue;
            }

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
        $this->htmlQuoteDepth = $previousHtmlQuoteDepth;

        return $document;
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
                    $referenceKey = $this->normalizeReferenceLabel($reference['label']);
                    if (!isset($references[$referenceKey])) {
                        $references[$referenceKey] = [
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

        return mb_strtolower($label, 'UTF-8');
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
        if (preg_match('/^ {0,3}/', $line, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        if (substr($line, $offset, 2) === '[^') {
            return null;
        }

        $label = $this->parseBracketedLabel($line, $offset);
        if (
            $label === null
            || $label['text'] === ''
            || !$this->referenceLabelWithinLimit($label['text'])
            || ($line[$label['next']] ?? '') !== ':'
        ) {
            return null;
        }

        return [
            'label' => $label['text'],
            'content' => rtrim(substr($line, $label['next'] + 1)),
        ];
    }

    private function referenceLabelWithinLimit(string $label): bool
    {
        return mb_strlen($this->normalizeReferenceLabel($label), 'UTF-8') <= 999;
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

        if ($this->referenceTargetCouldHaveMultilineTitle($target)) {
            while ($cursor < $count && trim($lines[$cursor]) !== '') {
                $target .= "\n" . trim($this->expandTabsToSpaces($lines[$cursor]));
                $cursor++;
                if ($this->parseLinkDestinationAndTitle($target) !== null) {
                    return [$target, $cursor];
                }
            }
        }

        if ($cursor < $count) {
            $candidate = trim($this->expandTabsToSpaces($lines[$cursor]));
            if ($this->parseLinkTitle($candidate) !== null) {
                $target .= ' ' . $candidate;
                $cursor++;
            } elseif ($this->startsLinkTitle($candidate)) {
                $title = $candidate;
                $titleCursor = $cursor + 1;
                while ($titleCursor < $count && trim($lines[$titleCursor]) !== '') {
                    $title .= "\n" . trim($this->expandTabsToSpaces($lines[$titleCursor]));
                    $titleCursor++;
                    if ($this->parseLinkTitle($title) !== null) {
                        $target .= ' ' . $title;
                        $cursor = $titleCursor;
                        break;
                    }
                }
            }
        }

        return [$target, $cursor];
    }

    private function referenceTargetCouldHaveMultilineTitle(string $target): bool
    {
        $target = trim($target);
        if ($target === '') {
            return false;
        }

        if ($target[0] === '<') {
            [$destination, $rest] = $this->readLinkDestination($target);
            if ($destination === null) {
                return false;
            }

            $rest = trim($rest);

            return $this->startsLinkTitle($rest) && $this->parseLinkTitle($rest) === null;
        }

        $length = strlen($target);
        for ($cursor = 0; $cursor < $length; $cursor++) {
            if (!ctype_space($target[$cursor])) {
                continue;
            }

            $suffix = ltrim(substr($target, $cursor + 1));
            if ($this->startsLinkTitle($suffix)) {
                return $this->parseLinkTitle($suffix) === null;
            }
        }

        return false;
    }

    private function startsLinkTitle(string $text): bool
    {
        $text = ltrim($text);

        return $text !== '' && in_array($text[0], ['"', "'", '('], true);
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
                $rawAttribute = $this->tryParseRawAttributeSpec($info, 0);
                if ($rawAttribute !== null && $rawAttribute['next'] === strlen($info)) {
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

        $attrs = $this->parseCodeInfo($info);
        $attrs['text'] = implode("\n", $content);
        $rawAttribute = $this->tryParseRawAttributeSpec($info, 0);
        if ($rawAttribute !== null && $rawAttribute['next'] === strlen($info)) {
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

                    return $this->buildDivBlock($content, $closedOnOpeningLine, $this->isHtmlLineBlockOpeningTag($lines[$openingIndex]));
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
    private function buildDivBlock(array $content, bool $closedOnOpeningLine, bool $lineBlock = false): AstNode
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

            return new AstNode('div', [], [
                new AstNode('plain', ['text' => $text], $this->parseInlines($text)),
            ]);
        }

        $inner = $this->read(implode("\n", $content));

        return new AstNode('div', [], $inner->children);
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

        if ($this->commonmarkAutolinksEnabled() && $this->startsWithCommonmarkUriAutolink($line)) {
            return null;
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

    private function startsWithCommonmarkUriAutolink(string $line): bool
    {
        return preg_match('/^ {0,3}<[A-Za-z][A-Za-z0-9+.-]{1,31}:[^<>\s]*>/', $line) === 1;
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

        return new AstNode('table', $attrs, [
            new AstNode('table_head'),
            new AstNode('table_body', [], $bodyRows),
        ]);
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

        return new AstNode('table', $attrs, $children);
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
        return (bool) ($this->options['htmlNativeDivs'] ?? false);
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
        if ($this->prefersCommonMarkRawHtmlBlocks()) {
            return null;
        }

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
        return in_array(strtolower($element->localName), [
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

            return $this->buildHtmlRawBlockNode($element);
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
        $text = $this->plainTextFromInlines($inlines);
        if (trim(preg_replace('/\s+/', ' ', $text) ?? $text) !== '') {
            array_push($children, ...$inlines);
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

        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''))) ?? '');
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
        $attrs = array_merge($this->htmlElementPandocAttrs($table), [
            'caption' => $captionInlines === [] ? '' : trim(preg_replace('/\s+/', ' ', $this->plainTextFromInlines($captionInlines)) ?? ''),
            'alignments' => array_fill(0, $maxColumns, 'default'),
        ]);
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }

        $widths = $this->readHtmlTableColumnWidths($table, $maxColumns);
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

        return new AstNode('table', $attrs, $children);
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
     * @return list<float>|null
     */
    private function readHtmlTableColumnWidths(\DOMElement $table, int $maxColumns): ?array
    {
        $colgroup = $this->firstChildElement($table, 'colgroup');
        if (!$colgroup instanceof \DOMElement) {
            return null;
        }

        $widths = [];
        foreach ($this->childElements($colgroup, 'col') as $col) {
            $width = $this->htmlColumnWidthPercent($col);
            if ($width === null) {
                return null;
            }

            $widths[] = $width;
        }

        if ($widths === [] || ($maxColumns > 0 && count($widths) !== $maxColumns)) {
            return null;
        }

        return $widths;
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

        $rowspan = $this->positiveHtmlSpan($cell->getAttribute('rowspan'));
        if ($rowspan > 1) {
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
        if ($name === 'span' && $this->htmlElementIsSmallCapsSpan($node)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('small_caps', [], $children);
        }
        if ($name === 'span' && $this->htmlElementHasExactClass($node, 'strikeout')) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('strikeout', [], $children);
        }
        if ($name === 'small') {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('span', ['classes' => ['small']], $children);
        }
        if ($name === 'bdo') {
            return $this->parseHtmlBdoInline($node, $children);
        }
        if (in_array($name, ['u', 'ins'], true)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('underline', [], $children);
        }
        if (in_array($name, ['s', 'strike', 'del'], true)) {
            return $this->wrapHtmlInlineWithBoundaryWhitespace('strikeout', [], $children);
        }
        if (in_array($name, ['code', 'tt', 'samp', 'var'], true)) {
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

    private function htmlElementUsesGenericRawInlineFallback(\DOMElement $element): bool
    {
        return in_array(strtolower($element->localName), ['applet', 'area', 'audio', 'blink', 'button', 'cite', 'embed', 'map', 'noscript', 'object', 'progress', 'source', 'time', 'track', 'video', 'wbr'], true);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function wrapHtmlRawInlineElement(\DOMElement $element, array $children): array
    {
        if ($this->htmlElementIsVoid($element)) {
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
        return (bool) ($this->options['htmlRawHtml'] ?? $this->options['rawHtml'] ?? true);
    }

    private function prefersCommonMarkRawHtmlBlocks(): bool
    {
        $format = MarkdownFormatProfile::canonicalFormat($this->options['format'] ?? 'markdown');

        return in_array($format, ['commonmark', 'commonmark_x', 'gfm'], true);
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

        return new AstNode('raw_html_inline', ['html' => trim($raw)]);
    }

    private function buildHtmlRawBlockNode(\DOMElement $element): AstNode
    {
        return new AstNode('raw_html', ['html' => (string) $this->buildHtmlRawInlineNode($element)->attr('html', '')]);
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

        return new AstNode('span', $attrs, [
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

    private function htmlElementHasSmallCapsStyle(\DOMElement $element): bool
    {
        $style = strtolower($element->getAttribute('style'));
        if ($style === '') {
            return false;
        }

        return preg_match('/(?:^|;)\s*font-variant\s*:\s*small-caps\b/', $style) === 1
            || preg_match('/(?:^|;)\s*font-variant-caps\s*:\s*small-caps\b/', $style) === 1;
    }

    private function htmlElementIsSmallCapsSpan(\DOMElement $element): bool
    {
        return $this->htmlElementHasSmallCapsStyle($element)
            || $this->htmlElementHasClass($element, 'smallcaps');
    }

    private function htmlElementHasExactClass(\DOMElement $element, string $class): bool
    {
        return trim($element->getAttribute('class')) === $class;
    }

    private function htmlElementHasClass(\DOMElement $element, string $class): bool
    {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return in_array($class, $classes, true);
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

        return new AstNode('table', $attrs, $children);
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

        return new AstNode('table', $attrs, $children);
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

        return new AstNode('table', $attrs, $children);
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

    private function isNeutralWriterSeparatorLine(string $line): bool
    {
        return preg_match('/^ {0,3}<!-- -->[ \t]*$/', $line) === 1;
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
            'search',
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
                $continuation = rtrim($this->stripIndentColumns($line, $contentIndent));
                if ($this->lineCanStartRawHtmlBlock($continuation)) {
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
        ];
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
            'parts' => (new self($this->options + ['suppressStandaloneButtonInline' => true]))
                ->read(implode("\n", $content))
                ->children,
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
                    $rawAttribute = $this->tryParseRawAttributeSpec($text, $next);
                    if ($rawAttribute !== null) {
                        $this->flushText($buffer, $nodes);
                        $nodes[] = $this->rawInlineNode($rawAttribute['format'], $code);
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
            $cluster = $this->tryParseBracketedCitationCluster($text, $offset);
            if ($cluster !== null) {
                return $cluster;
            }

            if (preg_match('/\G\[@([A-Za-z0-9_:.#\/$%&+?<>~|-]+)\]/u', $text, $m, 0, $offset) !== 1) {
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
            $citationText = trim(substr($content, $marker['start'], $end - $marker['start']));
            $attrs = [
                'id' => $id['id'],
                'text' => $citationText,
                'mode' => $marker['suppress'] ? 'suppress_author' : 'normal',
            ];
            if ($prefix !== '') {
                $attrs['prefix'] = $prefix;
            }
            if ($suffix !== '') {
                $attrs['locator'] = $suffix;
                $locatorParts = $this->inferBracketedCitationLocatorParts($suffix);
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

                $id = substr($content, $at + 2, $cursor - $at - 2);
                return $id === '' ? null : ['id' => $id, 'next' => $cursor + 1];
            }

            return null;
        }

        if (preg_match('/\G@([A-Za-z0-9_:.#\/$%&+?<>~|-]*[A-Za-z0-9_#\/$%&+?<>~|-])/u', $content, $match, 0, $at) !== 1) {
            return null;
        }

        return ['id' => $match[1], 'next' => $at + strlen($match[0])];
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
            if ($previous !== '' && preg_match('/[A-Za-z0-9_@.\/-]/', $previous) === 1) {
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
     * @return array{format: string, next: int}|null
     */
    private function tryParseRawAttributeSpec(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '{') {
            return null;
        }

        $end = $this->findUnescapedCharacter($text, '}', $offset + 1);
        if ($end === null) {
            return null;
        }

        $inside = trim(substr($text, $offset + 1, $end - $offset - 1));
        if (preg_match('/^=([A-Za-z0-9_.:+-]+)$/', $inside, $match) !== 1) {
            return null;
        }

        return [
            'format' => $match[1],
            'next' => $end + 1,
        ];
    }

    private function rawInlineNode(string $format, string $text): AstNode
    {
        $normalized = strtolower($format);
        if (in_array($normalized, ['html', 'html4', 'html5'], true)) {
            return new AstNode('raw_html_inline', [
                'format' => $format,
                'html' => $text,
                'text' => $text,
            ]);
        }
        if ($normalized === 'tex') {
            return new AstNode('raw_tex_inline', [
                'format' => $format,
                'tex' => $text,
                'text' => $text,
            ]);
        }

        return new AstNode('raw_inline', [
            'format' => $format,
            'text' => $text,
        ]);
    }

    private function rawBlockNode(string $format, string $text): AstNode
    {
        $normalized = strtolower($format);
        if (in_array($normalized, ['html', 'html4', 'html5'], true)) {
            return new AstNode('raw_html', [
                'format' => $format,
                'html' => $text,
                'text' => $text,
            ]);
        }
        if ($normalized === 'tex') {
            return new AstNode('raw_tex', [
                'format' => $format,
                'tex' => $text,
                'text' => $text,
            ]);
        }

        return new AstNode('raw_block', [
            'format' => $format,
            'text' => $text,
        ]);
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

        if (
            $this->commonmarkAutolinksEnabled()
            && preg_match('/\G<([A-Za-z][A-Za-z0-9+.-]{1,31}:[^<>\s]*)>/u', $text, $m, 0, $offset) === 1
        ) {
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

    private function commonmarkAutolinksEnabled(): bool
    {
        foreach (['commonmarkAutolinks', 'commonmark_autolinks'] as $key) {
            if (array_key_exists($key, $this->options)) {
                return $this->boolOption($this->options[$key], false);
            }
        }

        $format = $this->options['format'] ?? 'markdown';
        $enabled = in_array(MarkdownFormatProfile::canonicalFormat($format), ['commonmark', 'commonmark_x', 'gfm'], true);
        $formatOverrides = MarkdownFormatProfile::markdownExtensionOverrides($format);
        if (array_key_exists('commonmark_autolinks', $formatOverrides)) {
            $enabled = $formatOverrides['commonmark_autolinks'];
        }

        return $this->optionsExtensionOverride('commonmark_autolinks', $enabled);
    }

    private function optionsExtensionOverride(string $extension, bool $default): bool
    {
        $extensions = $this->options['extensions'] ?? null;
        if (!is_array($extensions)) {
            return $default;
        }

        $enabled = $default;
        foreach ($extensions as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $normalized = strtolower(trim($value));
                if (preg_match('/^([+-])' . preg_quote($extension, '/') . '$/', $normalized, $match) === 1) {
                    $enabled = $match[1] === '+';
                }
                continue;
            }

            if (strtolower((string) $key) === $extension) {
                $enabled = $this->boolOption($value, $enabled);
            }
        }

        return $enabled;
    }

    private function boolOption(mixed $value, bool $default): bool
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
