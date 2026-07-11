<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Bounded, non-executing LaTeX reader for document-oriented source files.
 *
 * This deliberately parses a useful publishing subset rather than attempting
 * to implement TeX expansion, package loading, or a layout engine.
 */
final class LatexReader
{
    private const DEFAULT_MAX_SOURCE_BYTES = 16_777_216;
    private const DEFAULT_MAX_INCLUDE_DEPTH = 8;
    private const DEFAULT_MAX_INCLUDED_BYTES = 16_777_216;

    /** @var array<string, array{arity:int, template:string}> */
    private array $macros = [];

    /** @var array<string, array{id:string, display:string, kind:string}> */
    private array $labels = [];

    /** @var array<string, true> */
    private array $diagnosticSet = [];

    /** @var list<string> */
    private array $diagnostics = [];

    /** @var list<string> */
    private array $includedFiles = [];

    /** @var list<array{path:string, bytes:string}> */
    private array $bibliographyFiles = [];

    /** @var list<AstNode> */
    private array $abstractBlocks = [];

    /** @var list<AstNode> */
    private array $preambleRawBlocks = [];

    /** @var array{title:string, author:list<string>, date:string, documentClass:string, packages:list<string>} */
    private array $preambleMetadata = [
        'title' => '',
        'author' => [],
        'date' => '',
        'documentClass' => '',
        'packages' => [],
    ];

    private ?string $sourceDirectory = null;
    private ?string $includeRootDirectory = null;
    private int $includedBytes = 0;
    private bool $hasChapter = false;
    private bool $bibliographyRequested = false;
    private string $bibliographyStyle = '';

    /** @var array<string, int> */
    private array $referenceCounters = [
        'figure' => 0,
        'table' => 0,
        'equation' => 0,
    ];

    /**
     * @param array{
     *     sourcePath?: string,
     *     sourceDirectory?: string,
     *     resourceBasePath?: string,
     *     maxSourceBytes?: int,
     *     maxIncludeDepth?: int,
     *     maxIncludedBytes?: int,
     *     resolveIncludes?: bool,
     *     resolveBibliography?: bool
     * } $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $source): AstNode
    {
        $this->resetState();
        $sourceBytes = strlen($source);
        if ($sourceBytes > $this->maxSourceBytes()) {
            throw new \InvalidArgumentException('LaTeX input exceeds the configured source-byte limit.');
        }

        $this->sourceDirectory = $this->configuredSourceDirectory();
        $this->includeRootDirectory = $this->sourceDirectory;
        $source = $this->stripUtf8Bom($source);
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $source = $this->stripCommentsOutsideLiteralEnvironments($source);
        $this->hasChapter = preg_match('/(?<!\\\\)\\\\chapter\*?\s*(?:\[[^\]]*\])?\s*\{/u', $source) === 1;
        $this->collectLabelIds($source);

        [$preamble, $body, $hasDocumentEnvironment] = $this->splitDocument($source);
        if (!$hasDocumentEnvironment) {
            $this->diagnostic('latex-document-environment-missing');
        }
        $preambleMetadata = $this->parsePreamble($preamble);
        $this->preambleMetadata = $preambleMetadata;
        $blocks = $this->parseBlocks($body, 0);
        if ($this->preambleRawBlocks !== []) {
            $blocks = array_merge($this->preambleRawBlocks, $blocks);
        }

        $meta = [
            'sourceFormat' => 'latex',
            'reader' => self::class,
            'readerScope' => 'bounded-native-latex-reader-semantic-document-import',
        ];
        foreach (['title', 'author', 'date', 'documentClass', 'packages'] as $name) {
            if (($preambleMetadata[$name] ?? null) !== null && $preambleMetadata[$name] !== '' && $preambleMetadata[$name] !== []) {
                $meta[$name] = $preambleMetadata[$name];
            }
        }
        if ($this->abstractBlocks !== []) {
            $meta['abstract'] = $this->plainBlocksText($this->abstractBlocks);
        }

        $document = new AstNode('document', [
            'sourceFormat' => 'latex',
            'meta' => $meta,
            'latex' => [
                'reader' => self::class,
                'readerScope' => 'bounded-native-latex-reader-semantic-document-import',
                'sourceBytes' => $sourceBytes,
                'documentEnvironmentPresent' => $hasDocumentEnvironment,
                'documentClass' => $preambleMetadata['documentClass'] ?? '',
                'packages' => $preambleMetadata['packages'] ?? [],
                'includedFiles' => $this->includedFiles,
                'bibliographyFiles' => array_map(static fn (array $file): string => $file['path'], $this->bibliographyFiles),
                'bibliographyStyle' => $this->bibliographyStyle,
                'diagnostics' => $this->diagnostics,
                'executionPolicy' => 'parse-only-no-tex-engine-package-loading-shell-escape-or-arbitrary-macro-execution',
            ],
        ], $blocks);

        $document = $this->replaceTocPlaceholders($document);
        $document = $this->resolveReferences($document);
        $document = $this->applyResolvedBibliography($document);

        return $this->documentWithFinalDiagnostics($document);
    }

    private function resetState(): void
    {
        $this->macros = [];
        $this->labels = [];
        $this->diagnosticSet = [];
        $this->diagnostics = [];
        $this->includedFiles = [];
        $this->bibliographyFiles = [];
        $this->abstractBlocks = [];
        $this->preambleRawBlocks = [];
        $this->preambleMetadata = [
            'title' => '',
            'author' => [],
            'date' => '',
            'documentClass' => '',
            'packages' => [],
        ];
        $this->sourceDirectory = null;
        $this->includeRootDirectory = null;
        $this->includedBytes = 0;
        $this->hasChapter = false;
        $this->bibliographyRequested = false;
        $this->bibliographyStyle = '';
        $this->referenceCounters = [
            'figure' => 0,
            'table' => 0,
            'equation' => 0,
        ];
    }

    private function maxSourceBytes(): int
    {
        return max(1, (int) ($this->options['maxSourceBytes'] ?? self::DEFAULT_MAX_SOURCE_BYTES));
    }

    private function maxIncludeDepth(): int
    {
        return max(0, (int) ($this->options['maxIncludeDepth'] ?? self::DEFAULT_MAX_INCLUDE_DEPTH));
    }

    private function maxIncludedBytes(): int
    {
        return max(0, (int) ($this->options['maxIncludedBytes'] ?? self::DEFAULT_MAX_INCLUDED_BYTES));
    }

    private function includesEnabled(): bool
    {
        return ($this->options['resolveIncludes'] ?? true) !== false;
    }

    private function bibliographyResolutionEnabled(): bool
    {
        return ($this->options['resolveBibliography'] ?? true) !== false;
    }

    private function configuredSourceDirectory(): ?string
    {
        $candidate = $this->options['sourceDirectory']
            ?? $this->options['resourceBasePath']
            ?? $this->options['sourcePath']
            ?? null;
        if (!is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $candidate = trim($candidate);
        if (!is_dir($candidate)) {
            $candidate = dirname($candidate);
        }
        $resolved = realpath($candidate);

        return is_string($resolved) && is_dir($resolved) ? $resolved : null;
    }

    private function stripUtf8Bom(string $source): string
    {
        return str_starts_with($source, "\xEF\xBB\xBF") ? substr($source, 3) : $source;
    }

    private function stripCommentsOutsideLiteralEnvironments(string $source): string
    {
        $literalEnvironments = ['verbatim' => true, 'verbatim*' => true, 'lstlisting' => true, 'minted' => true];
        $environment = '';
        $result = '';
        $length = strlen($source);
        for ($offset = 0; $offset < $length;) {
            if ($source[$offset] === '\\') {
                $command = $this->commandAt($source, $offset);
                if ($command !== null && ($command['name'] === 'begin' || $command['name'] === 'end')) {
                    $cursor = $command['next'];
                    $group = $this->readRequiredGroup($source, $cursor);
                    if ($group !== null) {
                        $name = strtolower(trim($group['value']));
                        if ($command['name'] === 'begin' && isset($literalEnvironments[$name])) {
                            $environment = $name;
                        } elseif ($command['name'] === 'end' && $environment === $name) {
                            $environment = '';
                        }
                    }
                }
                $result .= $source[$offset];
                ++$offset;
                continue;
            }
            if ($environment === '' && $source[$offset] === '%' && !$this->isEscaped($source, $offset)) {
                while ($offset < $length && $source[$offset] !== "\n") {
                    ++$offset;
                }
                continue;
            }
            $result .= $source[$offset];
            ++$offset;
        }

        return $result;
    }

    /**
     * @return array{0:string, 1:string, 2:bool}
     */
    private function splitDocument(string $source): array
    {
        $begin = $this->findEnvironmentToken($source, 'document', 0, 'begin');
        if ($begin === null) {
            return ['', $source, false];
        }
        $end = $this->findEnvironmentToken($source, 'document', $begin['next'], 'end');
        if ($end === null) {
            $this->diagnostic('latex-document-environment-unclosed');

            return [substr($source, 0, $begin['offset']), substr($source, $begin['next']), true];
        }

        return [
            substr($source, 0, $begin['offset']),
            substr($source, $begin['next'], $end['offset'] - $begin['next']),
            true,
        ];
    }

    /**
     * @return array{offset:int, next:int}|null
     */
    private function findEnvironmentToken(string $source, string $environment, int $offset, string $kind): ?array
    {
        $length = strlen($source);
        while ($offset < $length) {
            $next = strpos($source, '\\', $offset);
            if ($next === false) {
                return null;
            }
            $command = $this->commandAt($source, $next);
            if ($command === null || $command['name'] !== $kind) {
                $offset = $next + 1;
                continue;
            }
            $cursor = $command['next'];
            $group = $this->readRequiredGroup($source, $cursor);
            if ($group !== null && strtolower(trim($group['value'])) === strtolower($environment)) {
                return ['offset' => $next, 'next' => $group['next']];
            }
            $offset = $next + 1;
        }

        return null;
    }

    /**
     * @return array{title:string, author:list<string>, date:string, documentClass:string, packages:list<string>}
     */
    private function parsePreamble(string $preamble): array
    {
        $metadata = [
            'title' => '',
            'author' => [],
            'date' => '',
            'documentClass' => '',
            'packages' => [],
        ];
        $length = strlen($preamble);
        for ($offset = 0; $offset < $length;) {
            if ($preamble[$offset] !== '\\') {
                ++$offset;
                continue;
            }
            $command = $this->commandAt($preamble, $offset);
            if ($command === null) {
                ++$offset;
                continue;
            }
            $cursor = $command['next'];
            if (in_array($command['name'], ['newcommand', 'renewcommand', 'providecommand'], true)) {
                $definition = $this->readMacroDefinition($preamble, $offset, $command);
                if ($definition !== null) {
                    $this->registerMacro($definition);
                    $offset = $definition['next'];
                    continue;
                }
                $rawDefinition = $this->readRawMacroDefinition($preamble, $offset, $command);
                if ($rawDefinition !== null) {
                    $this->preambleRawBlocks[] = new AstNode('raw_tex', [
                        'tex' => $rawDefinition['source'],
                        'command' => $command['name'],
                        'preamble' => true,
                    ]);
                    $offset = $rawDefinition['next'];
                    continue;
                }
            }
            if ($command['name'] === 'documentclass') {
                $arguments = $this->readCommandArguments($preamble, $cursor, 1);
                if ($arguments !== null) {
                    $metadata['documentClass'] = $this->plainInlineText($this->parseInlines($arguments['groups'][0]));
                    $offset = $arguments['next'];
                    continue;
                }
            }
            if ($command['name'] === 'usepackage') {
                $arguments = $this->readCommandArguments($preamble, $cursor, 1);
                if ($arguments !== null) {
                    foreach (preg_split('/\s*,\s*/u', $arguments['groups'][0]) ?: [] as $package) {
                        $package = trim($package);
                        if ($package !== '') {
                            $metadata['packages'][] = $package;
                        }
                    }
                    $offset = $arguments['next'];
                    continue;
                }
            }
            if (in_array($command['name'], ['title', 'author', 'date'], true)) {
                $arguments = $this->readCommandArguments($preamble, $cursor, 1);
                if ($arguments !== null) {
                    if ($command['name'] === 'author') {
                        foreach (preg_split('/\\\\and\b/u', $arguments['groups'][0]) ?: [] as $authorSource) {
                            $author = trim($this->plainInlineText($this->parseInlines($authorSource)));
                            if ($author !== '') {
                                $metadata['author'][] = $author;
                            }
                        }
                    } else {
                        $metadata[$command['name']] = trim($this->plainInlineText($this->parseInlines($arguments['groups'][0])));
                    }
                    $offset = $arguments['next'];
                    continue;
                }
            }
            $offset = max($offset + 1, $cursor);
        }

        $metadata['packages'] = array_values(array_unique($metadata['packages']));

        return $metadata;
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlocks(string $source, int $includeDepth): array
    {
        $blocks = [];
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            $this->skipBlockWhitespace($source, $offset);
            if ($offset >= $length) {
                break;
            }
            if ($source[$offset] === '\\') {
                $command = $this->commandAt($source, $offset);
                if ($command !== null) {
                    $handled = $this->parseBlockCommand($source, $offset, $command, $blocks, $includeDepth);
                    if ($handled) {
                        continue;
                    }
                }
            }
            $paragraph = $this->readParagraph($source, $offset);
            if ($paragraph === '') {
                // An unrecognized command at a block boundary still needs to
                // advance so malformed source cannot stall the reader.
                $paragraph = $source[$offset] ?? '';
                ++$offset;
            }
            $inlines = $this->parseInlines($paragraph);
            if ($this->plainInlineText($inlines) !== '' || $this->containsNonTextInline($inlines)) {
                $blocks[] = new AstNode('paragraph', [
                    'text' => $this->plainInlineText($inlines),
                ], $inlines);
            }
        }

        return $blocks;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function parseBlockCommand(string $source, int &$offset, array $command, array &$blocks, int $includeDepth): bool
    {
        $name = $command['name'];
        $cursor = $command['next'];
        if (isset($this->headingLevels()[$name])) {
            $heading = $this->parseHeadingCommand($source, $offset, $command);
            if ($heading !== null) {
                $blocks[] = $heading['node'];
                $offset = $heading['next'];

                return true;
            }
        }
        if ($name === 'label') {
            $label = $this->readRequiredGroup($source, $cursor);
            if ($label !== null) {
                $this->applyLabelToPreviousBlock($blocks, $label['value']);
                $offset = $label['next'];

                return true;
            }
        }
        if (in_array($name, ['newcommand', 'renewcommand', 'providecommand'], true)) {
            $definition = $this->readMacroDefinition($source, $offset, $command);
            if ($definition !== null) {
                $this->registerMacro($definition);
                $offset = $definition['next'];

                return true;
            }
            $rawDefinition = $this->readRawMacroDefinition($source, $offset, $command);
            if ($rawDefinition !== null) {
                $blocks[] = new AstNode('raw_tex', [
                    'tex' => $rawDefinition['source'],
                    'command' => $name,
                ]);
                $offset = $rawDefinition['next'];

                return true;
            }
        }
        if ($name === 'maketitle') {
            $blocks[] = $this->titleBlock();
            $offset = $cursor;

            return true;
        }
        if ($name === 'tableofcontents') {
            $blocks[] = new AstNode('div', [
                'classes' => ['latex-table-of-contents'],
                'latexTocPlaceholder' => true,
            ]);
            $offset = $cursor;

            return true;
        }
        if (in_array($name, ['input', 'include'], true)) {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument === null) {
                return false;
            }
            $offset = $argument['next'];
            array_push($blocks, ...$this->readIncludedBlocks($argument['value'], $includeDepth, $name));

            return true;
        }
        if (in_array($name, ['bibliography', 'addbibresource'], true)) {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument === null) {
                return false;
            }
            $this->bibliographyRequested = true;
            $this->loadBibliographyResources($argument['value']);
            $offset = $argument['next'];

            return true;
        }
        if ($name === 'printbibliography') {
            $this->bibliographyRequested = true;
            $offset = $this->skipOptionalArguments($source, $cursor);

            return true;
        }
        if ($name === 'bibliographystyle') {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                $this->bibliographyStyle = trim($argument['value']);
                $offset = $argument['next'];

                return true;
            }
        }
        if ($name === 'par') {
            $offset = $cursor;

            return true;
        }
        if ($name === '[') {
            $end = $this->findUnescapedDelimiter($source, '\\]', $cursor);
            if ($end !== null) {
                $blocks[] = $this->displayMathBlock(substr($source, $cursor, $end - $cursor), 'displaymath');
                $offset = $end + 2;

                return true;
            }
        }
        if (in_array($name, ['newpage', 'clearpage', 'pagebreak', 'clear doublepage', 'cleardoublepage'], true)) {
            $offset = $cursor;

            return true;
        }
        if ($name === 'begin') {
            $environment = $this->readEnvironmentAt($source, $offset, $command);
            if ($environment === null) {
                return false;
            }
            $offset = $environment['next'];
            array_push($blocks, ...$this->blocksFromEnvironment($environment, $includeDepth));

            return true;
        }

        return false;
    }

    /**
     * @return array{node:AstNode, next:int}|null
     */
    private function parseHeadingCommand(string $source, int $offset, array $command): ?array
    {
        $cursor = $command['next'];
        $arguments = $this->readCommandArguments($source, $cursor, 1);
        if ($arguments === null) {
            return null;
        }
        $titleInlines = $this->parseInlines($arguments['groups'][0]);
        $title = $this->plainInlineText($titleInlines);
        $after = $arguments['next'];
        $label = $this->readFollowingLabel($source, $after);
        if ($label !== null) {
            $after = $label['next'];
        }
        $id = $label === null ? $this->headingId($title) : $this->registerLabel($label['value'], $title, 'heading');

        return [
            'node' => new AstNode('heading', [
                'level' => $this->headingLevel($command['name']),
                'id' => $id,
                'text' => $title,
                'latexCommand' => $command['name'],
                'latexStarred' => $command['starred'],
            ], $titleInlines),
            'next' => $after,
        ];
    }

    /**
     * @return array{environment:string, content:string, raw:string, options:list<string>, next:int}|null
     */
    private function readEnvironmentAt(string $source, int $offset, array $command): ?array
    {
        $cursor = $command['next'];
        $nameGroup = $this->readRequiredGroup($source, $cursor);
        if ($nameGroup === null) {
            return null;
        }
        $environment = strtolower(trim($nameGroup['value']));
        if ($environment === '') {
            return null;
        }
        $options = [];
        $bodyStart = $nameGroup['next'];
        while (true) {
            $option = $this->readOptionalGroup($source, $bodyStart);
            if ($option === null) {
                break;
            }
            $options[] = $option['value'];
            $bodyStart = $option['next'];
        }

        $literal = in_array($environment, ['verbatim', 'verbatim*', 'lstlisting', 'minted'], true);
        if ($literal) {
            $closing = '\\end{' . $environment . '}';
            $endOffset = strpos($source, $closing, $bodyStart);
            if ($endOffset === false) {
                $this->diagnostic('latex-environment-unclosed:' . $environment);

                return [
                    'environment' => $environment,
                    'content' => substr($source, $bodyStart),
                    'raw' => substr($source, $offset),
                    'options' => $options,
                    'next' => strlen($source),
                ];
            }

            return [
                'environment' => $environment,
                'content' => substr($source, $bodyStart, $endOffset - $bodyStart),
                'raw' => substr($source, $offset, $endOffset + strlen($closing) - $offset),
                'options' => $options,
                'next' => $endOffset + strlen($closing),
            ];
        }

        $depth = 1;
        $scan = $bodyStart;
        $length = strlen($source);
        while ($scan < $length) {
            $next = strpos($source, '\\', $scan);
            if ($next === false) {
                break;
            }
            $candidate = $this->commandAt($source, $next);
            if ($candidate === null || !in_array($candidate['name'], ['begin', 'end'], true)) {
                $scan = $next + 1;
                continue;
            }
            $candidateCursor = $candidate['next'];
            $candidateName = $this->readRequiredGroup($source, $candidateCursor);
            if ($candidateName === null || strtolower(trim($candidateName['value'])) !== $environment) {
                $scan = $next + 1;
                continue;
            }
            if ($candidate['name'] === 'begin') {
                ++$depth;
                $scan = $candidateName['next'];
                continue;
            }
            --$depth;
            if ($depth === 0) {
                return [
                    'environment' => $environment,
                    'content' => substr($source, $bodyStart, $next - $bodyStart),
                    'raw' => substr($source, $offset, $candidateName['next'] - $offset),
                    'options' => $options,
                    'next' => $candidateName['next'],
                ];
            }
            $scan = $candidateName['next'];
        }

        $this->diagnostic('latex-environment-unclosed:' . $environment);

        return [
            'environment' => $environment,
            'content' => substr($source, $bodyStart),
            'raw' => substr($source, $offset),
            'options' => $options,
            'next' => strlen($source),
        ];
    }

    /**
     * @param array{environment:string, content:string, raw:string, options:list<string>, next:int} $environment
     * @return list<AstNode>
     */
    private function blocksFromEnvironment(array $environment, int $includeDepth): array
    {
        $name = $environment['environment'];
        $content = $environment['content'];
        if ($name === 'document') {
            return $this->parseBlocks($content, $includeDepth);
        }
        if ($name === 'abstract') {
            $blocks = $this->parseBlocks($content, $includeDepth);
            $this->abstractBlocks = $blocks;

            return [new AstNode('div', ['classes' => ['latex-abstract']], array_merge([
                new AstNode('heading', [
                    'level' => 2,
                    'id' => 'abstract',
                    'text' => 'Abstract',
                ], [new AstNode('text', ['text' => 'Abstract'])]),
            ], $blocks))];
        }
        if (in_array($name, ['itemize', 'enumerate', 'description'], true)) {
            return [$this->listFromEnvironment($name, $content, $includeDepth)];
        }
        if (in_array($name, ['quote', 'quotation'], true)) {
            return [new AstNode('blockquote', [], $this->parseBlocks($content, $includeDepth))];
        }
        if ($name === 'verse') {
            return [$this->lineBlockFromVerse($content)];
        }
        if (in_array($name, ['verbatim', 'verbatim*', 'lstlisting', 'minted'], true)) {
            return [$this->codeBlockFromEnvironment($environment)];
        }
        if (in_array($name, ['equation', 'equation*', 'align', 'align*', 'aligned', 'gather', 'gather*', 'multline', 'multline*', 'displaymath'], true)) {
            return [$this->displayMathBlock($content, $name)];
        }
        if (in_array($name, ['figure', 'figure*'], true)) {
            return [$this->figureFromEnvironment($content)];
        }
        if (in_array($name, ['table', 'table*'], true)) {
            $table = $this->tableFromContainerEnvironment($content);
            if ($table instanceof AstNode) {
                return [$table];
            }
        }
        if (in_array($name, ['tabular', 'tabular*', 'tabularx', 'longtable'], true)) {
            $table = $this->tableFromTabularEnvironment($name, $content, $environment['options']);
            if ($table instanceof AstNode) {
                return [$table];
            }
        }
        if ($name === 'thebibliography') {
            return $this->manualBibliographyBlocks($content);
        }
        if (in_array($name, ['center', 'flushleft', 'flushright', 'minipage'], true)) {
            return [new AstNode('div', ['classes' => ['latex-' . $name]], $this->parseBlocks($content, $includeDepth))];
        }

        $this->diagnostic('latex-unsupported-environment:' . $name);

        return [new AstNode('raw_tex', [
            'tex' => $environment['raw'],
            'environment' => $name,
        ])];
    }

    /**
     * @return array<string, int>
     */
    private function headingLevels(): array
    {
        $sectionLevel = $this->hasChapter ? 2 : 1;

        return [
            'part' => 1,
            'chapter' => 1,
            'section' => $sectionLevel,
            'subsection' => min(6, $sectionLevel + 1),
            'subsubsection' => min(6, $sectionLevel + 2),
            'paragraph' => min(6, $sectionLevel + 3),
            'subparagraph' => min(6, $sectionLevel + 4),
        ];
    }

    private function headingLevel(string $command): int
    {
        return $this->headingLevels()[$command] ?? 2;
    }

    private function headingId(string $title): string
    {
        $base = $this->slug($title);
        if ($base === '') {
            $base = 'section';
        }
        $id = $base;
        $suffix = 2;
        $taken = array_map(static fn (array $label): string => $label['id'], $this->labels);
        while (in_array($id, $taken, true)) {
            $id = $base . '-' . $suffix;
            ++$suffix;
        }

        return $id;
    }

    private function collectLabelIds(string $source): void
    {
        if (preg_match_all('/(?<!\\\\)\\\\label\s*\{((?:\\\\.|[^{}\\\\])*)\}/u', $source, $matches) !== 1) {
            return;
        }
        foreach ($matches[1] as $raw) {
            $label = $this->cleanLabel((string) $raw);
            if ($label === '' || isset($this->labels[$label])) {
                continue;
            }
            $base = 'latex-' . $this->slug($label);
            if ($base === 'latex-') {
                $base = 'latex-anchor';
            }
            $id = $base;
            $suffix = 2;
            while (in_array($id, array_map(static fn (array $entry): string => $entry['id'], $this->labels), true)) {
                $id = $base . '-' . $suffix;
                ++$suffix;
            }
            $this->labels[$label] = [
                'id' => $id,
                'display' => $label,
                'kind' => 'unknown',
            ];
        }
    }

    private function registerLabel(string $raw, string $display, string $kind): string
    {
        $label = $this->cleanLabel($raw);
        if ($label === '') {
            return $this->headingId($display);
        }
        if (!isset($this->labels[$label])) {
            $this->collectLabelIds('\\label{' . $label . '}');
        }
        $entry = $this->labels[$label] ?? [
            'id' => 'latex-' . ($this->slug($label) ?: 'anchor'),
            'display' => $label,
            'kind' => 'unknown',
        ];
        $entry['display'] = $display === '' ? $entry['display'] : $display;
        $entry['kind'] = $kind;
        $this->labels[$label] = $entry;

        return $entry['id'];
    }

    private function nextReferenceNumber(string $kind): int
    {
        if (!isset($this->referenceCounters[$kind])) {
            $this->referenceCounters[$kind] = 0;
        }

        ++$this->referenceCounters[$kind];

        return $this->referenceCounters[$kind];
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function applyLabelToPreviousBlock(array &$blocks, string $rawLabel): void
    {
        $key = $this->cleanLabel($rawLabel);
        if ($key === '') {
            return;
        }
        $lastIndex = array_key_last($blocks);
        if ($lastIndex === null) {
            $this->diagnostic('latex-orphan-label:' . $key);

            return;
        }
        $last = $blocks[$lastIndex];
        $referenceNumber = $last->attr('latexReferenceNumber');
        $display = is_int($referenceNumber) || (is_string($referenceNumber) && ctype_digit($referenceNumber))
            ? (string) $referenceNumber
            : trim((string) $last->attr('text', ''));
        if ($display === '') {
            $display = $this->plainBlocksText($last->children);
        }
        $kind = (string) $last->attr('latexReferenceKind', $last->type);
        $id = $this->registerLabel($key, $display, $kind);
        $attrs = array_replace($last->attrs, [
            'id' => $id,
            'latexLabel' => $key,
        ]);
        $blocks[$lastIndex] = new AstNode($last->type, $attrs, $last->children);
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readFollowingLabel(string $source, int $offset): ?array
    {
        $cursor = $offset;
        $this->skipWhitespace($source, $cursor);
        $command = $this->commandAt($source, $cursor);
        if ($command === null || $command['name'] !== 'label') {
            return null;
        }
        $next = $command['next'];

        return $this->readRequiredGroup($source, $next);
    }

    private function titleBlock(): AstNode
    {
        $children = [];
        $title = trim($this->preambleMetadata['title']);
        if ($title !== '') {
            $inlines = $this->parseInlines($title);
            $children[] = new AstNode('heading', [
                'level' => 1,
                'id' => 'title',
                'text' => $this->plainInlineText($inlines),
                'latexCommand' => 'maketitle',
            ], $inlines);
        }
        foreach ($this->preambleMetadata['author'] as $author) {
            $inlines = $this->parseInlines($author);
            $children[] = new AstNode('paragraph', [
                'classes' => ['latex-author'],
                'text' => $this->plainInlineText($inlines),
            ], $inlines);
        }
        $date = trim($this->preambleMetadata['date']);
        if ($date !== '') {
            $inlines = $this->parseInlines($date);
            $children[] = new AstNode('paragraph', [
                'classes' => ['latex-date'],
                'text' => $this->plainInlineText($inlines),
            ], $inlines);
        }
        if ($children === []) {
            $this->diagnostic('latex-maketitle-without-preamble-metadata');

            return new AstNode('div', ['classes' => ['latex-title-block']]);
        }

        return new AstNode('div', ['classes' => ['latex-title-block']], $children);
    }

    private function listFromEnvironment(string $environment, string $content, int $includeDepth): AstNode
    {
        $items = $this->splitListItems($content);
        if ($environment === 'description') {
            $definitions = [];
            foreach ($items as $item) {
                $termInlines = $this->parseInlines($item['label'] === '' ? 'Item' : $item['label']);
                $itemBlocks = $this->parseBlocks($item['content'], $includeDepth);
                if ($itemBlocks === []) {
                    $itemBlocks = [new AstNode('paragraph', ['text' => ''], [])];
                }
                $definitions[] = new AstNode('definition_item', [
                    'term' => $this->plainInlineText($termInlines),
                ], [
                    new AstNode('definition_term', ['text' => $this->plainInlineText($termInlines)], $termInlines),
                    new AstNode('definition', [], $itemBlocks),
                ]);
            }

            return new AstNode('definition_list', ['classes' => ['latex-description']], $definitions);
        }

        $listItems = [];
        foreach ($items as $item) {
            $itemBlocks = $this->parseBlocks($item['content'], $includeDepth);
            if ($itemBlocks === []) {
                $itemBlocks = [new AstNode('plain', ['text' => ''], [])];
            }
            $listItems[] = new AstNode('list_item', ['loose' => $this->blocksNeedLooseList($itemBlocks)], $itemBlocks);
        }
        if ($environment === 'enumerate') {
            return new AstNode('ordered_list', [
                'start' => 1,
                'style' => 'default',
                'delimiter' => 'default',
                'loose' => $this->listItemsNeedLooseList($listItems),
            ], $listItems);
        }

        return new AstNode('bullet_list', [
            'loose' => $this->listItemsNeedLooseList($listItems),
        ], $listItems);
    }

    /**
     * @return list<array{label:string, content:string}>
     */
    private function splitListItems(string $content): array
    {
        $items = [];
        $offsets = [];
        $depth = 0;
        $length = strlen($content);
        for ($offset = 0; $offset < $length; ++$offset) {
            $char = $content[$offset];
            if ($char === '\\') {
                $command = $this->commandAt($content, $offset);
                if ($command !== null && $command['name'] === 'item' && $depth === 0) {
                    $offsets[] = ['offset' => $offset, 'next' => $command['next']];
                }
                ++$offset;
                continue;
            }
            if ($char === '{' && !$this->isEscaped($content, $offset)) {
                ++$depth;
            } elseif ($char === '}' && !$this->isEscaped($content, $offset) && $depth > 0) {
                --$depth;
            }
        }
        foreach ($offsets as $index => $itemOffset) {
            $cursor = $itemOffset['next'];
            $label = '';
            $optional = $this->readOptionalGroup($content, $cursor);
            if ($optional !== null) {
                $label = $optional['value'];
                $cursor = $optional['next'];
            }
            $end = $offsets[$index + 1]['offset'] ?? $length;
            $items[] = [
                'label' => $label,
                'content' => trim(substr($content, $cursor, $end - $cursor)),
            ];
        }
        if ($items === [] && trim($content) !== '') {
            $items[] = ['label' => '', 'content' => trim($content)];
        }

        return $items;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function blocksNeedLooseList(array $blocks): bool
    {
        return count($blocks) > 1 || (($blocks[0]->type ?? '') !== 'plain');
    }

    /**
     * @param list<AstNode> $items
     */
    private function listItemsNeedLooseList(array $items): bool
    {
        foreach ($items as $item) {
            if ($item->attr('loose') === true) {
                return true;
            }
        }

        return false;
    }

    private function lineBlockFromVerse(string $content): AstNode
    {
        $lines = [];
        foreach (preg_split('/\R/u', trim($content)) ?: [] as $line) {
            $lines[] = new AstNode('line', [], $this->parseInlines($line));
        }

        return new AstNode('line_block', ['classes' => ['latex-verse']], $lines);
    }

    /**
     * @param array{environment:string, content:string, raw:string, options:list<string>, next:int} $environment
     */
    private function codeBlockFromEnvironment(array $environment): AstNode
    {
        $language = '';
        if ($environment['environment'] === 'minted') {
            $language = trim((string) ($environment['options'][0] ?? ''));
        }
        if ($environment['environment'] === 'lstlisting') {
            foreach ($environment['options'] as $option) {
                if (preg_match('/(?:^|,)\s*language\s*=\s*([^,]+)/iu', $option, $match) === 1) {
                    $language = trim($match[1]);
                    break;
                }
            }
        }
        $classes = ['latex-' . $environment['environment']];
        if ($language !== '') {
            $classes[] = strtolower($language);
        }

        return new AstNode('code_block', [
            'text' => trim($environment['content'], "\n"),
            'classes' => $classes,
        ]);
    }

    private function displayMathBlock(string $content, string $environment): AstNode
    {
        $label = $this->firstLabel($content);
        $math = trim($this->stripMathLabels($content));
        $numbered = $this->numberedMathEnvironment($environment);
        $number = $numbered ? $this->nextReferenceNumber('equation') : null;
        $attrs = [
            'classes' => ['latex-display-math'],
            'text' => $math,
            'latexEnvironment' => $environment,
        ];
        if ($number !== null) {
            $attrs['latexReferenceKind'] = 'equation';
            $attrs['latexReferenceNumber'] = $number;
        }
        if ($label !== '') {
            $attrs['id'] = $this->registerLabel($label, $number === null ? '' : (string) $number, 'equation');
            $attrs['latexLabel'] = $this->cleanLabel($label);
        }

        return new AstNode('paragraph', $attrs, [new AstNode('math', [
            'display' => true,
            'text' => $math,
            'latexEnvironment' => $environment,
        ])]);
    }

    private function numberedMathEnvironment(string $environment): bool
    {
        return !str_ends_with($environment, '*')
            && !in_array($environment, ['aligned', 'displaymath'], true);
    }

    private function figureFromEnvironment(string $content): AstNode
    {
        $image = $this->firstIncludeGraphics($content);
        $caption = $this->firstCaption($content);
        $label = $this->firstLabel($content);
        $number = $this->nextReferenceNumber('figure');
        $captionInlines = $this->parseInlines($caption['text']);
        $attrs = [
            'caption' => $this->plainInlineText($captionInlines),
            'captionInlines' => $captionInlines,
            'classes' => ['latex-figure'],
            'latexReferenceKind' => 'figure',
            'latexReferenceNumber' => $number,
        ];
        if ($caption['short'] !== '') {
            $attrs['shortCaption'] = $caption['short'];
            $attrs['shortCaptionInlines'] = $this->parseInlines($caption['short']);
        }
        if ($label !== '') {
            $attrs['id'] = $this->registerLabel($label, (string) $number, 'figure');
            $attrs['latexLabel'] = $this->cleanLabel($label);
        }
        if ($image === null) {
            $this->diagnostic('latex-figure-without-includegraphics');

            return new AstNode('figure', $attrs, [new AstNode('raw_tex', ['tex' => $content])]);
        }
        $imageAttrs = [
            'url' => $image['url'],
            'alt' => $caption['text'],
            'title' => '',
            'latexOptions' => $image['options'],
        ];
        foreach ($this->imageDimensionsFromOptions($image['options']) as $name => $value) {
            $imageAttrs[$name] = $value;
        }

        return new AstNode('figure', $attrs, [new AstNode('image', $imageAttrs, $captionInlines)]);
    }

    private function tableFromContainerEnvironment(string $content): ?AstNode
    {
        $caption = $this->firstCaption($content);
        $label = $this->firstLabel($content);
        $tabular = $this->firstNestedTabularEnvironment($content);
        if ($tabular === null) {
            $this->diagnostic('latex-table-without-tabular');

            return null;
        }
        $table = $this->tableFromTabularEnvironment($tabular['environment'], $tabular['content'], $tabular['options']);
        if (!$table instanceof AstNode) {
            return null;
        }
        $attrs = $table->attrs;
        $number = (int) ($attrs['latexReferenceNumber'] ?? $this->nextReferenceNumber('table'));
        $attrs['caption'] = $caption['text'];
        $attrs['captionInlines'] = $this->parseInlines($caption['text']);
        $attrs['latexReferenceKind'] = 'table';
        $attrs['latexReferenceNumber'] = $number;
        if ($caption['short'] !== '') {
            $attrs['shortCaption'] = $caption['short'];
            $attrs['shortCaptionInlines'] = $this->parseInlines($caption['short']);
        }
        if ($label !== '') {
            $attrs['id'] = $this->registerLabel($label, (string) $number, 'table');
            $attrs['latexLabel'] = $this->cleanLabel($label);
        }

        return new AstNode('table', $attrs, $table->children);
    }

    /**
     * @param list<string> $options
     */
    private function tableFromTabularEnvironment(string $environment, string $content, array $options): ?AstNode
    {
        [$content, $preambleArguments] = $this->tabularPreambleArguments($content, $environment);
        $alignmentSpec = $environment === 'tabularx'
            ? (string) ($preambleArguments[1] ?? $preambleArguments[0] ?? $options[1] ?? $options[0] ?? '')
            : (string) ($preambleArguments[0] ?? $options[0] ?? '');
        $alignments = $this->tabularAlignments($alignmentSpec);
        $rows = $this->tabularRows($content);
        if ($rows === []) {
            $this->diagnostic('latex-empty-table:' . $environment);

            return null;
        }
        $headerCount = $this->tabularHeaderRowCount($content, $rows);
        $maxColumns = max(count($alignments), ...array_map(static fn (array $row): int => $row['columnCount'], $rows));
        while (count($alignments) < $maxColumns) {
            $alignments[] = 'default';
        }
        $headRows = [];
        $bodyRows = [];
        foreach ($rows as $index => $row) {
            $isHeader = $index < $headerCount;
            $cells = [];
            foreach ($row['cells'] as $cell) {
                $attrs = [
                    'text' => $this->plainInlineText($this->parseInlines($cell['text'])),
                    'header' => $isHeader,
                ];
                if ($cell['colspan'] > 1) {
                    $attrs['colspan'] = $cell['colspan'];
                }
                if ($cell['rowspan'] > 1) {
                    $attrs['rowspan'] = $cell['rowspan'];
                }
                if ($cell['align'] !== 'default') {
                    $attrs['align'] = $cell['align'];
                }
                $cells[] = new AstNode('table_cell', $attrs, $this->parseInlines($cell['text']));
            }
            $tableRow = new AstNode('table_row', ['header' => $isHeader], $cells);
            if ($isHeader) {
                $headRows[] = $tableRow;
            } else {
                $bodyRows[] = $tableRow;
            }
        }

        return new AstNode('table', [
            'alignments' => $alignments,
            'classes' => ['latex-' . $environment],
            'latexReferenceKind' => 'table',
            'latexReferenceNumber' => $this->nextReferenceNumber('table'),
        ], [
            new AstNode('table_head', [], $headRows),
            new AstNode('table_body', [], $bodyRows),
        ]);
    }

    /**
     * @return array{0:string, 1:list<string>}
     */
    private function tabularPreambleArguments(string $content, string $environment): array
    {
        $cursor = 0;
        $arguments = [];
        $required = $environment === 'tabularx' ? 2 : 1;
        while (count($arguments) < $required) {
            $group = $this->readRequiredGroup($content, $cursor);
            if ($group === null) {
                break;
            }
            $arguments[] = $group['value'];
            $cursor = $group['next'];
        }

        return [substr($content, $cursor), $arguments];
    }

    /**
     * @return array{environment:string, content:string, options:list<string>}|null
     */
    private function firstNestedTabularEnvironment(string $source): ?array
    {
        $length = strlen($source);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($source[$offset] !== '\\') {
                continue;
            }
            $command = $this->commandAt($source, $offset);
            if ($command === null || $command['name'] !== 'begin') {
                continue;
            }
            $environment = $this->readEnvironmentAt($source, $offset, $command);
            if ($environment === null) {
                continue;
            }
            if (in_array($environment['environment'], ['tabular', 'tabular*', 'tabularx', 'longtable'], true)) {
                return [
                    'environment' => $environment['environment'],
                    'content' => $environment['content'],
                    'options' => $environment['options'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{url:string, options:string}|null
     */
    private function firstIncludeGraphics(string $source): ?array
    {
        $length = strlen($source);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($source[$offset] !== '\\') {
                continue;
            }
            $command = $this->commandAt($source, $offset);
            if ($command === null || $command['name'] !== 'includegraphics') {
                continue;
            }
            $cursor = $command['next'];
            $option = $this->readOptionalGroup($source, $cursor);
            if ($option !== null) {
                $cursor = $option['next'];
            }
            $path = $this->readRequiredGroup($source, $cursor);
            if ($path === null) {
                continue;
            }
            $url = $this->normalizeImageReference($path['value']);
            if ($url === '') {
                $this->diagnostic('latex-includegraphics-invalid-path');

                return null;
            }

            return [
                'url' => $url,
                'options' => $option['value'] ?? '',
            ];
        }

        return null;
    }

    /**
     * @return array{text:string, short:string}
     */
    private function firstCaption(string $source): array
    {
        $length = strlen($source);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($source[$offset] !== '\\') {
                continue;
            }
            $command = $this->commandAt($source, $offset);
            if ($command === null || $command['name'] !== 'caption') {
                continue;
            }
            $cursor = $command['next'];
            $short = $this->readOptionalGroup($source, $cursor);
            if ($short !== null) {
                $cursor = $short['next'];
            }
            $caption = $this->readRequiredGroup($source, $cursor);
            if ($caption === null) {
                continue;
            }

            return [
                'text' => trim($this->plainInlineText($this->parseInlines($caption['value']))),
                'short' => $short === null ? '' : trim($this->plainInlineText($this->parseInlines($short['value']))),
            ];
        }

        return ['text' => '', 'short' => ''];
    }

    private function firstLabel(string $source): string
    {
        $length = strlen($source);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($source[$offset] !== '\\') {
                continue;
            }
            $command = $this->commandAt($source, $offset);
            if ($command === null || $command['name'] !== 'label') {
                continue;
            }
            $cursor = $command['next'];
            $label = $this->readRequiredGroup($source, $cursor);
            if ($label !== null) {
                return $label['value'];
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function imageDimensionsFromOptions(string $options): array
    {
        $attrs = [];
        foreach (preg_split('/\s*,\s*/u', $options) ?: [] as $option) {
            if (preg_match('/^(width|height)\s*=\s*([0-9]+(?:\.[0-9]+)?)\\\\(?:textwidth|linewidth|columnwidth)$/iu', trim($option), $match) === 1) {
                $attrs[strtolower($match[1])] = rtrim(rtrim(number_format((float) $match[2] * 100, 3, '.', ''), '0'), '.') . '%';
                continue;
            }
            if (preg_match('/^(width|height)\s*=\s*([0-9]+(?:\.[0-9]+)?(?:px|pt|pc|in|cm|mm|em|rem|%))$/iu', trim($option), $match) === 1) {
                $attrs[strtolower($match[1])] = $match[2];
            }
        }

        return $attrs;
    }

    private function normalizeImageReference(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_contains($path, "\0") || preg_match('/^[a-z][a-z0-9+.-]*:/iu', $path) === 1) {
            return '';
        }
        $path = ltrim($path, '/');
        $path = preg_replace('#^(?:\./)+#', '', $path) ?? $path;
        if ($path === '' || in_array('..', explode('/', $path), true)) {
            return '';
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    private function tabularAlignments(string $specification): array
    {
        $alignments = [];
        $length = strlen($specification);
        for ($offset = 0; $offset < $length; ++$offset) {
            $char = strtolower($specification[$offset]);
            if ($char === 'l') {
                $alignments[] = 'left';
                continue;
            }
            if ($char === 'c' || $char === 'x') {
                $alignments[] = 'center';
                continue;
            }
            if ($char === 'r') {
                $alignments[] = 'right';
                continue;
            }
            if (in_array($char, ['p', 'm', 'b'], true)) {
                $alignments[] = 'left';
            }
        }

        return $alignments;
    }

    /**
     * @return list<array{cells:list<array{text:string, colspan:int, rowspan:int, align:string}>, columnCount:int}>
     */
    private function tabularRows(string $content): array
    {
        $content = preg_replace('/\\\\(?:hline|toprule|midrule|bottomrule|addlinespace)\b(?:\s*\[[^\]]*\])?/u', "\n", $content) ?? $content;
        $content = preg_replace('/\\\\cline\s*\{[^}]*\}/u', "\n", $content) ?? $content;
        $content = preg_replace('/\\\\(?:endfirsthead|endhead|endfoot|endlastfoot)\b/u', "\n", $content) ?? $content;
        $rows = [];
        foreach ($this->splitTopLevel($content, '\\\\') as $rawRow) {
            $rawRow = trim($rawRow);
            if ($rawRow === '') {
                continue;
            }
            $rawRow = preg_replace('/\\\\(?:caption|label)\s*(?:\[[^\]]*\])?\s*\{(?:\\\\.|[^{}])*\}/us', '', $rawRow) ?? $rawRow;
            if (trim($rawRow) === '') {
                continue;
            }
            $cells = [];
            $columnCount = 0;
            foreach ($this->splitTopLevel($rawRow, '&') as $rawCell) {
                $cell = $this->tabularCell($rawCell);
                $cells[] = $cell;
                $columnCount += max(1, $cell['colspan']);
            }
            if ($cells !== []) {
                $rows[] = ['cells' => $cells, 'columnCount' => $columnCount];
            }
        }

        return $rows;
    }

    /**
     * @return array{text:string, colspan:int, rowspan:int, align:string}
     */
    private function tabularCell(string $source): array
    {
        $source = trim($source);
        $cell = [
            'text' => $source,
            'colspan' => 1,
            'rowspan' => 1,
            'align' => 'default',
        ];
        if (preg_match('/^\\\\multicolumn\s*\{(\d+)\}\s*\{([^}]*)\}\s*\{(.*)\}$/us', $source, $match) === 1) {
            $cell['colspan'] = max(1, (int) $match[1]);
            $cell['align'] = $this->alignmentFromSpec($match[2]);
            $cell['text'] = trim($match[3]);
        }
        if (preg_match('/^\\\\multirow\s*\{(\d+)\}\s*\{[^}]*\}\s*\{(.*)\}$/us', $cell['text'], $match) === 1) {
            $cell['rowspan'] = max(1, (int) $match[1]);
            $cell['text'] = trim($match[2]);
        }

        return $cell;
    }

    private function alignmentFromSpec(string $specification): string
    {
        $specification = strtolower($specification);
        if (str_contains($specification, 'r')) {
            return 'right';
        }
        if (str_contains($specification, 'c')) {
            return 'center';
        }
        if (str_contains($specification, 'l') || preg_match('/[pmb]/', $specification) === 1) {
            return 'left';
        }

        return 'default';
    }

    /**
     * @param list<array{cells:list<array{text:string, colspan:int, rowspan:int, align:string}>, columnCount:int}> $rows
     */
    private function tabularHeaderRowCount(string $content, array $rows): int
    {
        if (str_contains($content, '\\endfirsthead')) {
            $before = substr($content, 0, strpos($content, '\\endfirsthead'));

            return min(count($rows), count($this->tabularRows($before)));
        }
        $midrule = strpos($content, '\\midrule');
        if ($midrule === false) {
            return 0;
        }
        $before = substr($content, 0, $midrule);

        return min(count($rows), count($this->tabularRows($before)));
    }

    /**
     * Split by a literal top-level delimiter, keeping nested braces intact.
     *
     * @return list<string>
     */
    private function splitTopLevel(string $source, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $length = strlen($source);
        $delimiterLength = strlen($delimiter);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($depth === 0 && substr($source, $offset, $delimiterLength) === $delimiter) {
                $parts[] = substr($source, $start, $offset - $start);
                $offset += $delimiterLength - 1;
                $start = $offset + 1;
                continue;
            }
            if ($source[$offset] === '\\') {
                ++$offset;
                continue;
            }
            if ($source[$offset] === '{') {
                ++$depth;
                continue;
            }
            if ($source[$offset] === '}' && $depth > 0) {
                --$depth;
                continue;
            }
        }
        $parts[] = substr($source, $start);

        return $parts;
    }

    /**
     * @return list<AstNode>
     */
    private function manualBibliographyBlocks(string $content): array
    {
        $entries = [];
        $matches = [];
        if (preg_match_all('/\\\\bibitem(?:\[[^\]]*\])?\s*\{([^}]*)\}/u', $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return [new AstNode('raw_tex', ['tex' => $content, 'environment' => 'thebibliography'])];
        }
        foreach ($matches[0] as $index => $match) {
            $start = $match[1];
            $after = $start + strlen($match[0]);
            $next = $matches[0][$index + 1][1] ?? strlen($content);
            $id = $this->cleanLabel((string) ($matches[1][$index][0] ?? ''));
            $text = trim($this->plainInlineText($this->parseInlines(substr($content, $after, $next - $after))));
            $term = $id === '' ? 'Reference ' . ($index + 1) : $id;
            $termInlines = $this->parseInlines($term);
            $valueInlines = $this->parseInlines($text);
            $entries[] = new AstNode('definition_item', ['term' => $term], [
                new AstNode('definition_term', ['text' => $term], $termInlines),
                new AstNode('definition', [], [
                    new AstNode('paragraph', ['text' => $this->plainInlineText($valueInlines)], $valueInlines),
                ]),
            ]);
        }

        return $entries === [] ? [] : [new AstNode('definition_list', ['classes' => ['latex-bibliography']], $entries)];
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $source, int $macroDepth = 0): array
    {
        $nodes = [];
        $text = '';
        $length = strlen($source);
        for ($offset = 0; $offset < $length;) {
            $char = $source[$offset];
            if ($char === '$' && !$this->isEscaped($source, $offset)) {
                $display = ($source[$offset + 1] ?? '') === '$';
                $delimiter = $display ? '$$' : '$';
                $end = $this->findUnescapedDelimiter($source, $delimiter, $offset + strlen($delimiter));
                if ($end !== null) {
                    $this->appendText($nodes, $text);
                    $math = trim(substr($source, $offset + strlen($delimiter), $end - $offset - strlen($delimiter)));
                    $nodes[] = new AstNode('math', ['display' => $display, 'text' => $math]);
                    $offset = $end + strlen($delimiter);
                    continue;
                }
            }
            if ($char === '\\') {
                $command = $this->commandAt($source, $offset);
                if ($command !== null) {
                    $parsed = $this->parseInlineCommand($source, $offset, $command, $macroDepth);
                    if ($parsed !== null) {
                        $this->appendText($nodes, $text);
                        foreach ($parsed['nodes'] as $node) {
                            $nodes[] = $node;
                        }
                        $offset = $parsed['next'];
                        continue;
                    }
                }
            }
            if ($char === '{' && !$this->isEscaped($source, $offset)) {
                $group = $this->readBalancedGroup($source, $offset, '{', '}');
                if ($group !== null) {
                    $this->appendText($nodes, $text);
                    foreach ($this->parseInlines($group['value'], $macroDepth) as $node) {
                        $nodes[] = $node;
                    }
                    $offset = $group['next'];
                    continue;
                }
            }
            if ($char === '~') {
                $text .= ' ';
                ++$offset;
                continue;
            }
            if (ctype_space($char)) {
                $text .= ' ';
                while ($offset < $length && ctype_space($source[$offset])) {
                    ++$offset;
                }
                continue;
            }
            $text .= $char;
            ++$offset;
        }
        $this->appendText($nodes, $text);

        return $nodes;
    }

    /**
     * @return array{nodes:list<AstNode>, next:int}|null
     */
    private function parseInlineCommand(string $source, int $offset, array $command, int $macroDepth): ?array
    {
        $name = $command['name'];
        $cursor = $command['next'];
        if ($name === '[') {
            $end = $this->findUnescapedDelimiter($source, '\\]', $cursor);
            if ($end !== null) {
                return [
                    'nodes' => [new AstNode('math', [
                        'display' => true,
                        'text' => trim(substr($source, $cursor, $end - $cursor)),
                    ])],
                    'next' => $end + 2,
                ];
            }
        }
        if ($name === '(') {
            $end = $this->findUnescapedDelimiter($source, '\\)', $cursor);
            if ($end !== null) {
                return [
                    'nodes' => [new AstNode('math', [
                        'display' => false,
                        'text' => trim(substr($source, $cursor, $end - $cursor)),
                    ])],
                    'next' => $end + 2,
                ];
            }
        }
        if ($name === 'verb') {
            return $this->parseVerbInline($source, $offset, $cursor);
        }
        if (in_array($name, ['\\', 'newline', 'linebreak'], true)) {
            return ['nodes' => [new AstNode('linebreak')], 'next' => $cursor];
        }
        if (in_array($name, [' ', ',', ';', ':', '!', 'quad', 'qquad'], true)) {
            return ['nodes' => [new AstNode('space')], 'next' => $cursor];
        }
        if (in_array($name, ['%', '_', '#', '$', '&', '{', '}'], true)) {
            return ['nodes' => [new AstNode('text', ['text' => $name])], 'next' => $cursor];
        }
        if (isset($this->macros[$name])) {
            return $this->expandInlineMacro($source, $offset, $cursor, $name, $macroDepth);
        }
        $formatCommands = [
            'textbf' => 'strong',
            'mathbf' => 'strong',
            'textit' => 'emph',
            'textsl' => 'emph',
            'emph' => 'emph',
            'textsc' => 'small_caps',
            'underline' => 'underline',
            'uline' => 'underline',
            'sout' => 'strikeout',
            'st' => 'strikeout',
            'textsuperscript' => 'superscript',
            'textsubscript' => 'subscript',
        ];
        if (isset($formatCommands[$name])) {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                return [
                    'nodes' => [new AstNode($formatCommands[$name], [], $this->parseInlines($argument['value'], $macroDepth))],
                    'next' => $argument['next'],
                ];
            }
        }
        if (in_array($name, ['texttt', 'verbinline'], true)) {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                return [
                    'nodes' => [new AstNode('code', ['text' => $this->stripSimpleTexEscapes($argument['value'])])],
                    'next' => $argument['next'],
                ];
            }
        }
        if ($name === 'ensuremath') {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                return [
                    'nodes' => [new AstNode('math', ['display' => false, 'text' => trim($argument['value'])])],
                    'next' => $argument['next'],
                ];
            }
        }
        if ($name === 'href') {
            $url = $this->readRequiredGroup($source, $cursor);
            if ($url !== null) {
                $textCursor = $url['next'];
                $label = $this->readRequiredGroup($source, $textCursor);
                if ($label !== null) {
                    return [
                        'nodes' => [new AstNode('link', [
                            'url' => trim($url['value']),
                            'title' => '',
                        ], $this->parseInlines($label['value'], $macroDepth))],
                        'next' => $label['next'],
                    ];
                }
            }
        }
        if ($name === 'url') {
            $url = $this->readRequiredGroup($source, $cursor);
            if ($url !== null) {
                $value = trim($url['value']);

                return [
                    'nodes' => [new AstNode('link', ['url' => $value, 'title' => ''], [new AstNode('text', ['text' => $value])])],
                    'next' => $url['next'],
                ];
            }
        }
        if ($name === 'hyperref') {
            $target = $this->readOptionalGroup($source, $cursor);
            if ($target !== null) {
                $labelCursor = $target['next'];
                $label = $this->readRequiredGroup($source, $labelCursor);
                if ($label !== null) {
                    $key = $this->cleanLabel($target['value']);

                    return [
                        'nodes' => [new AstNode('link', [
                            'url' => '#' . $this->labelId($key),
                            'title' => '',
                            'latexReferenceKey' => $key,
                        ], $this->parseInlines($label['value'], $macroDepth))],
                        'next' => $label['next'],
                    ];
                }
            }
        }
        if (in_array($name, ['ref', 'pageref', 'autoref', 'nameref'], true)) {
            $target = $this->readRequiredGroup($source, $cursor);
            if ($target !== null) {
                $key = $this->cleanLabel($target['value']);

                return [
                    'nodes' => [new AstNode('link', [
                        'url' => '#' . $this->labelId($key),
                        'title' => '',
                        'latexReferenceKey' => $key,
                        'latexReferenceCommand' => $name,
                    ], [new AstNode('text', ['text' => $key])])],
                    'next' => $target['next'],
                ];
            }
        }
        if ($name === 'footnote') {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                $blocks = $this->parseBlocks($argument['value'], 0);
                if ($blocks === []) {
                    $inlines = $this->parseInlines($argument['value'], $macroDepth);
                    $blocks = [new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $inlines)];
                }

                return [
                    'nodes' => [new AstNode('note', [], $blocks)],
                    'next' => $argument['next'],
                ];
            }
        }
        if (in_array($name, ['cite', 'citep', 'citet', 'citealp', 'citealt', 'citeauthor', 'citeyear', 'citeyearpar', 'parencite', 'textcite', 'autocite'], true)) {
            $citation = $this->parseCitationCommand($source, $offset, $command);
            if ($citation !== null) {
                return $citation;
            }
        }
        if ($name === 'includegraphics') {
            $option = $this->readOptionalGroup($source, $cursor);
            if ($option !== null) {
                $cursor = $option['next'];
            }
            $path = $this->readRequiredGroup($source, $cursor);
            if ($path !== null) {
                $url = $this->normalizeImageReference($path['value']);
                if ($url !== '') {
                    $attrs = [
                        'url' => $url,
                        'alt' => '',
                        'title' => '',
                        'latexOptions' => $option['value'] ?? '',
                    ];
                    foreach ($this->imageDimensionsFromOptions($option['value'] ?? '') as $attribute => $value) {
                        $attrs[$attribute] = $value;
                    }

                    return ['nodes' => [new AstNode('image', $attrs)], 'next' => $path['next']];
                }
            }
        }
        $namedSymbols = [
            'latex' => 'LaTeX',
            'tex' => 'TeX',
            'and' => ' ',
            'ldots' => '...',
            'dots' => '...',
            'textbackslash' => '\\',
            'textasciitilde' => '~',
            'textasciicircum' => '^',
        ];
        if (isset($namedSymbols[$name])) {
            return ['nodes' => [new AstNode('text', ['text' => $namedSymbols[$name]])], 'next' => $cursor];
        }
        if ($name === 'label') {
            $label = $this->readRequiredGroup($source, $cursor);
            if ($label !== null) {
                $this->registerLabel($label['value'], '', 'inline');

                return ['nodes' => [], 'next' => $label['next']];
            }
        }
        if (in_array($name, ['newcommand', 'renewcommand', 'providecommand'], true)) {
            $definition = $this->readMacroDefinition($source, $offset, $command);
            if ($definition !== null) {
                $this->registerMacro($definition);

                return ['nodes' => [], 'next' => $definition['next']];
            }
            $rawDefinition = $this->readRawMacroDefinition($source, $offset, $command);
            if ($rawDefinition !== null) {
                return [
                    'nodes' => [new AstNode('raw_tex_inline', [
                        'tex' => $rawDefinition['source'],
                        'command' => $name,
                    ])],
                    'next' => $rawDefinition['next'],
                ];
            }
        }

        $raw = $this->readRawInlineCommand($source, $offset, $command);
        $this->diagnostic('latex-unsupported-command:' . ($name === '' ? 'unknown' : $name));

        return [
            'nodes' => [new AstNode('raw_tex_inline', [
                'tex' => $raw['source'],
                'command' => $name,
            ])],
            'next' => $raw['next'],
        ];
    }

    /**
     * @return array{nodes:list<AstNode>, next:int}|null
     */
    private function parseVerbInline(string $source, int $offset, int $cursor): ?array
    {
        if (($source[$cursor] ?? '') === '*') {
            ++$cursor;
        }
        $delimiter = $source[$cursor] ?? '';
        if ($delimiter === '' || ctype_space($delimiter)) {
            return null;
        }
        $end = strpos($source, $delimiter, $cursor + 1);
        if ($end === false) {
            return null;
        }

        return [
            'nodes' => [new AstNode('code', ['text' => substr($source, $cursor + 1, $end - $cursor - 1)])],
            'next' => $end + 1,
        ];
    }

    /**
     * @return array{nodes:list<AstNode>, next:int}|null
     */
    private function expandInlineMacro(string $source, int $offset, int $cursor, string $name, int $macroDepth): ?array
    {
        if ($macroDepth >= 8) {
            $this->diagnostic('latex-macro-expansion-depth:' . $name);

            return null;
        }
        $definition = $this->macros[$name];
        $arguments = [];
        for ($index = 0; $index < $definition['arity']; ++$index) {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument === null) {
                return null;
            }
            $arguments[] = $argument['value'];
            $cursor = $argument['next'];
        }
        $expanded = $definition['template'];
        foreach ($arguments as $index => $argument) {
            $expanded = str_replace('#' . ($index + 1), $argument, $expanded);
        }

        return [
            'nodes' => $this->parseInlines($expanded, $macroDepth + 1),
            'next' => $cursor,
        ];
    }

    /**
     * @return array{nodes:list<AstNode>, next:int}|null
     */
    private function parseCitationCommand(string $source, int $offset, array $command): ?array
    {
        $cursor = $command['next'];
        $options = [];
        while (($option = $this->readOptionalGroup($source, $cursor)) !== null) {
            $options[] = $option['value'];
            $cursor = $option['next'];
        }
        $keys = $this->readRequiredGroup($source, $cursor);
        if ($keys === null) {
            return null;
        }
        $ids = array_values(array_filter(array_map([$this, 'cleanLabel'], preg_split('/\s*,\s*/u', $keys['value']) ?: []), static fn (string $id): bool => $id !== ''));
        if ($ids === []) {
            return null;
        }
        $sourceText = substr($source, $offset, $keys['next'] - $offset);
        $mode = in_array($command['name'], ['citet', 'citealt', 'textcite', 'citeauthor'], true)
            ? 'author_in_text'
            : (in_array($command['name'], ['citeyear', 'citeyearpar'], true) ? 'suppress_author' : 'normal');
        $entries = [];
        foreach ($ids as $id) {
            $attrs = [
                'id' => $id,
                'text' => $sourceText,
                'mode' => $mode,
                'sourceFormat' => 'latex',
                'sourceCommand' => $command['name'],
            ];
            if ($options !== []) {
                $suffix = trim((string) $options[array_key_last($options)]);
                if ($suffix !== '') {
                    $attrs['suffix'] = $suffix;
                }
                if (count($options) > 1 && trim($options[0]) !== '') {
                    $attrs['prefix'] = trim($options[0]);
                }
            }
            $entries[] = new AstNode('citation', $attrs, [new AstNode('text', ['text' => $sourceText])]);
        }
        if (count($entries) === 1) {
            return ['nodes' => [$entries[0]], 'next' => $keys['next']];
        }

        return [
            'nodes' => [new AstNode('citation_group', ['text' => $sourceText, 'sourceFormat' => 'latex'], $entries)],
            'next' => $keys['next'],
        ];
    }

    /**
     * @return array{source:string, next:int}
     */
    private function readRawInlineCommand(string $source, int $offset, array $command): array
    {
        $cursor = $command['next'];
        $limit = 2;
        while ($limit > 0) {
            $next = $cursor;
            $optional = $this->readOptionalGroup($source, $next);
            if ($optional !== null) {
                $cursor = $optional['next'];
                --$limit;
                continue;
            }
            $required = $this->readRequiredGroup($source, $next);
            if ($required !== null) {
                $cursor = $required['next'];
                --$limit;
                continue;
            }
            break;
        }

        return ['source' => substr($source, $offset, $cursor - $offset), 'next' => $cursor];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function appendText(array &$nodes, string &$text): void
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if ($text === '') {
            return;
        }
        $lastIndex = array_key_last($nodes);
        $last = $lastIndex === null ? null : $nodes[$lastIndex];
        if ($last instanceof AstNode && $last->type === 'text') {
            $nodes[$lastIndex] = new AstNode('text', ['text' => (string) $last->attr('text', '') . $text]);
        } else {
            $nodes[] = new AstNode('text', ['text' => $text]);
        }
        $text = '';
    }

    private function stripSimpleTexEscapes(string $text): string
    {
        return strtr($text, [
            '\\%' => '%',
            '\\_' => '_',
            '\\#' => '#',
            '\\$' => '$',
            '\\&' => '&',
            '\\{' => '{',
            '\\}' => '}',
            '\\textbackslash' => '\\',
        ]);
    }

    private function stripMathLabels(string $text): string
    {
        return preg_replace('/\\\\label\s*\{(?:\\\\.|[^{}])*\}/u', '', $text) ?? $text;
    }

    private function containsNonTextInline(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!in_array($node->type, ['text', 'space', 'softbreak'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (in_array($node->type, ['text', 'code', 'math'], true)) {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
                $text .= ' ';
                continue;
            }
            if ($node->type === 'image') {
                $text .= (string) $node->attr('alt', '');
                continue;
            }
            $text .= $this->plainInlineText($node->children);
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return list<AstNode>
     */
    private function readIncludedBlocks(string $rawPath, int $depth, string $command = 'input'): array
    {
        $source = '\\' . $command . '{' . $rawPath . '}';
        if (!$this->includesEnabled()) {
            $this->diagnostic('latex-include-disabled:' . $this->diagnosticPath($rawPath));

            return [new AstNode('raw_tex', ['tex' => $source, 'command' => $command])];
        }
        if ($depth >= $this->maxIncludeDepth()) {
            $this->diagnostic('latex-include-depth-limit:' . $this->diagnosticPath($rawPath));

            return [new AstNode('raw_tex', ['tex' => $source, 'command' => $command])];
        }
        $file = $this->readLocalSourceFile($rawPath, ['tex']);
        if ($file === null) {
            return [new AstNode('raw_tex', ['tex' => $source, 'command' => $command])];
        }
        if ($this->includedBytes + strlen($file['bytes']) > $this->maxIncludedBytes()) {
            $this->diagnostic('latex-include-byte-limit:' . $this->diagnosticPath($rawPath));

            return [new AstNode('raw_tex', ['tex' => $source, 'command' => $command])];
        }
        $this->includedBytes += strlen($file['bytes']);
        $this->includedFiles[] = $file['relative'];

        return $this->parseBlocks(
            $this->stripCommentsOutsideLiteralEnvironments(str_replace(["\r\n", "\r"], "\n", $file['bytes'])),
            $depth + 1
        );
    }

    private function loadBibliographyResources(string $rawPaths): void
    {
        foreach (preg_split('/\s*,\s*/u', $rawPaths) ?: [] as $rawPath) {
            $rawPath = trim($rawPath);
            if ($rawPath === '') {
                continue;
            }
            $file = $this->readLocalSourceFile($rawPath, ['bib']);
            if ($file === null) {
                continue;
            }
            foreach ($this->bibliographyFiles as $existing) {
                if ($existing['path'] === $file['relative']) {
                    continue 2;
                }
            }
            $this->bibliographyFiles[] = [
                'path' => $file['relative'],
                'bytes' => $file['bytes'],
            ];
        }
    }

    /**
     * @param list<string> $allowedExtensions
     * @return array{path:string, relative:string, bytes:string}|null
     */
    private function readLocalSourceFile(string $rawPath, array $allowedExtensions): ?array
    {
        if ($this->includeRootDirectory === null) {
            $this->diagnostic('latex-local-source-unavailable:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $path = trim(str_replace('\\', '/', $rawPath));
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[a-z][a-z0-9+.-]*:/iu', $path) === 1) {
            $this->diagnostic('latex-local-source-invalid:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $path = preg_replace('#^(?:\./)+#', '', $path) ?? $path;
        $segments = explode('/', $path);
        if ($path === '' || in_array('..', $segments, true)) {
            $this->diagnostic('latex-local-source-outside-root:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === '') {
            $path .= '.' . $allowedExtensions[0];
            $extension = $allowedExtensions[0];
        }
        if (!in_array($extension, $allowedExtensions, true)) {
            $this->diagnostic('latex-local-source-extension:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $candidate = $this->includeRootDirectory . DIRECTORY_SEPARATOR . $path;
        $resolved = realpath($candidate);
        $prefix = rtrim($this->includeRootDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_string($resolved) || !is_file($resolved) || !str_starts_with($resolved, $prefix)) {
            $this->diagnostic('latex-local-source-missing:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $bytes = file_get_contents($resolved);
        if (!is_string($bytes)) {
            $this->diagnostic('latex-local-source-unreadable:' . $this->diagnosticPath($rawPath));

            return null;
        }
        if (strlen($bytes) > $this->maxIncludedBytes()) {
            $this->diagnostic('latex-local-source-too-large:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $relative = ltrim(str_replace('\\', '/', substr($resolved, strlen($this->includeRootDirectory))), '/');

        return ['path' => $resolved, 'relative' => $relative, 'bytes' => $bytes];
    }

    /**
     * @return array{name:string, arity:int, template:string, next:int}|null
     */
    private function readMacroDefinition(string $source, int $offset, array $command): ?array
    {
        $cursor = $command['next'];
        if (($source[$cursor] ?? '') === '*') {
            ++$cursor;
        }
        $macro = $this->readMacroTarget($source, $cursor);
        if ($macro === null) {
            return null;
        }
        $name = trim($macro['value']);
        if (preg_match('/^\\\\([A-Za-z@]+)$/', $name, $match) !== 1) {
            $this->diagnostic('latex-macro-name-unsupported');

            return null;
        }
        $cursor = $macro['next'];
        $arity = 0;
        $arityOption = $this->readOptionalGroup($source, $cursor);
        if ($arityOption !== null) {
            if (preg_match('/^\d+$/', trim($arityOption['value'])) !== 1) {
                $this->diagnostic('latex-macro-optional-default-unsupported:' . $match[1]);

                return null;
            }
            $arity = (int) trim($arityOption['value']);
            $cursor = $arityOption['next'];
        }
        $body = $this->readRequiredGroup($source, $cursor);
        if ($body === null || $arity > 4) {
            $this->diagnostic('latex-macro-arity-unsupported:' . $match[1]);

            return null;
        }
        if (preg_match('/\\\\(?:input|include|write|openout|read|catcode|csname|expandafter|directlua|shellescape)\b/iu', $body['value']) === 1) {
            $this->diagnostic('latex-macro-unsafe-body:' . $match[1]);

            return null;
        }

        return [
            'name' => strtolower($match[1]),
            'arity' => $arity,
            'template' => $body['value'],
            'next' => $body['next'],
        ];
    }

    /**
     * Consume a macro declaration without interpreting it, so unsupported
     * declarations remain intact in the imported document.
     *
     * @return array{source:string, next:int}|null
     */
    private function readRawMacroDefinition(string $source, int $offset, array $command): ?array
    {
        $cursor = $command['next'];
        if (($source[$cursor] ?? '') === '*') {
            ++$cursor;
        }
        $macro = $this->readMacroTarget($source, $cursor);
        if ($macro === null) {
            return null;
        }
        while (($option = $this->readOptionalGroup($source, $cursor)) !== null) {
            $cursor = $option['next'];
        }
        $body = $this->readRequiredGroup($source, $cursor);
        if ($body === null) {
            return null;
        }

        return [
            'source' => substr($source, $offset, $body['next'] - $offset),
            'next' => $body['next'],
        ];
    }

    /**
     * Read either the braced or standard unbraced command target accepted by
     * LaTeX macro declarations.
     *
     * @return array{value:string, next:int}|null
     */
    private function readMacroTarget(string $source, int &$offset): ?array
    {
        $this->skipWhitespace($source, $offset);
        $group = $this->readBalancedGroup($source, $offset, '{', '}');
        if ($group !== null) {
            $offset = $group['next'];

            return $group;
        }
        $command = $this->commandAt($source, $offset);
        if ($command === null || preg_match('/^[A-Za-z@]+$/', $command['name']) !== 1) {
            return null;
        }
        $offset = $command['next'];

        return [
            'value' => '\\' . $command['name'],
            'next' => $offset,
        ];
    }

    /**
     * @param array{name:string, arity:int, template:string, next:int} $definition
     */
    private function registerMacro(array $definition): void
    {
        $this->macros[$definition['name']] = [
            'arity' => $definition['arity'],
            'template' => $definition['template'],
        ];
    }

    /**
     * @return array{name:string, starred:bool, next:int}|null
     */
    private function commandAt(string $source, int $offset): ?array
    {
        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }
        $cursor = $offset + 1;
        $first = $source[$cursor] ?? '';
        if ($first === '') {
            return null;
        }
        if (preg_match('/[A-Za-z@]/', $first) === 1) {
            $start = $cursor;
            while (($char = $source[$cursor] ?? '') !== '' && preg_match('/[A-Za-z@]/', $char) === 1) {
                ++$cursor;
            }
            $name = strtolower(substr($source, $start, $cursor - $start));
        } else {
            $name = $first;
            ++$cursor;
        }
        $starred = false;
        if (($source[$cursor] ?? '') === '*') {
            $starred = true;
            ++$cursor;
        }

        return ['name' => $name, 'starred' => $starred, 'next' => $cursor];
    }

    /**
     * @return array{options:list<string>, groups:list<string>, next:int}|null
     */
    private function readCommandArguments(string $source, int $offset, int $requiredGroups): ?array
    {
        $cursor = $offset;
        $options = [];
        while (($optional = $this->readOptionalGroup($source, $cursor)) !== null) {
            $options[] = $optional['value'];
            $cursor = $optional['next'];
        }
        $groups = [];
        for ($index = 0; $index < $requiredGroups; ++$index) {
            $group = $this->readRequiredGroup($source, $cursor);
            if ($group === null) {
                return null;
            }
            $groups[] = $group['value'];
            $cursor = $group['next'];
        }

        return ['options' => $options, 'groups' => $groups, 'next' => $cursor];
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readRequiredGroup(string $source, int &$offset): ?array
    {
        $this->skipWhitespace($source, $offset);
        $group = $this->readBalancedGroup($source, $offset, '{', '}');
        if ($group !== null) {
            $offset = $group['next'];
        }

        return $group;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readOptionalGroup(string $source, int &$offset): ?array
    {
        $this->skipWhitespace($source, $offset);
        $group = $this->readBalancedGroup($source, $offset, '[', ']');
        if ($group !== null) {
            $offset = $group['next'];
        }

        return $group;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readBalancedGroup(string $source, int $offset, string $open, string $close): ?array
    {
        if (($source[$offset] ?? '') !== $open) {
            return null;
        }
        $depth = 1;
        $cursor = $offset + 1;
        $start = $cursor;
        $length = strlen($source);
        while ($cursor < $length) {
            if ($source[$cursor] === '\\') {
                $cursor += 2;
                continue;
            }
            if ($source[$cursor] === $open) {
                ++$depth;
            } elseif ($source[$cursor] === $close) {
                --$depth;
                if ($depth === 0) {
                    return [
                        'value' => substr($source, $start, $cursor - $start),
                        'next' => $cursor + 1,
                    ];
                }
            }
            ++$cursor;
        }

        return null;
    }

    private function skipOptionalArguments(string $source, int $offset): int
    {
        $cursor = $offset;
        while (($group = $this->readOptionalGroup($source, $cursor)) !== null) {
            $cursor = $group['next'];
        }

        return $cursor;
    }

    private function skipWhitespace(string $source, int &$offset): void
    {
        $length = strlen($source);
        while ($offset < $length && ctype_space($source[$offset])) {
            ++$offset;
        }
    }

    private function skipBlockWhitespace(string $source, int &$offset): void
    {
        $this->skipWhitespace($source, $offset);
    }

    private function readParagraph(string $source, int &$offset): string
    {
        $start = $offset;
        $cursor = $offset;
        $braceDepth = 0;
        $length = strlen($source);
        while ($cursor < $length) {
            $char = $source[$cursor];
            if ($char === '\\' && !$this->isEscaped($source, $cursor) && $braceDepth === 0) {
                $command = $this->commandAt($source, $cursor);
                if ($command !== null && $this->isBlockCommand($command['name'])) {
                    break;
                }
            }
            if ($char === "\n" && $braceDepth === 0 && $this->blankLineAt($source, $cursor)) {
                break;
            }
            if ($char === '{' && !$this->isEscaped($source, $cursor)) {
                ++$braceDepth;
            } elseif ($char === '}' && !$this->isEscaped($source, $cursor) && $braceDepth > 0) {
                --$braceDepth;
            }
            ++$cursor;
        }
        $offset = $cursor;

        return trim(substr($source, $start, $cursor - $start));
    }

    private function isBlockCommand(string $name): bool
    {
        return isset($this->headingLevels()[$name])
            || in_array($name, [
                'begin', 'end', 'label', 'newcommand', 'renewcommand', 'providecommand',
                'maketitle', 'tableofcontents', 'input', 'include', 'bibliography',
                'addbibresource', 'printbibliography', 'bibliographystyle', 'par',
                'newpage', 'clearpage', 'cleardoublepage', 'pagebreak',
            ], true);
    }

    private function blankLineAt(string $source, int $offset): bool
    {
        if (($source[$offset] ?? '') !== "\n") {
            return false;
        }
        $cursor = $offset + 1;
        $length = strlen($source);
        while ($cursor < $length && ($source[$cursor] === ' ' || $source[$cursor] === "\t")) {
            ++$cursor;
        }

        return ($source[$cursor] ?? '') === "\n";
    }

    private function findUnescapedDelimiter(string $source, string $delimiter, int $offset): ?int
    {
        while (($candidate = strpos($source, $delimiter, $offset)) !== false) {
            if (!$this->isEscaped($source, $candidate)) {
                return $candidate;
            }
            $offset = $candidate + 1;
        }

        return null;
    }

    private function isEscaped(string $source, int $offset): bool
    {
        $slashes = 0;
        for ($cursor = $offset - 1; $cursor >= 0 && $source[$cursor] === '\\'; --$cursor) {
            ++$slashes;
        }

        return ($slashes % 2) === 1;
    }

    private function cleanLabel(string $label): string
    {
        $label = trim($this->stripSimpleTexEscapes($label));

        return preg_replace('/\s+/u', ' ', $label) ?? $label;
    }

    private function labelId(string $label): string
    {
        $label = $this->cleanLabel($label);
        if ($label !== '' && isset($this->labels[$label])) {
            return $this->labels[$label]['id'];
        }

        return 'latex-' . ($this->slug($label) ?: 'reference');
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}_.:-]+/u', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value;
    }

    private function diagnosticPath(string $path): string
    {
        $path = trim(str_replace(['\\', '/'], '-', $path));
        $path = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $path) ?? $path;

        return trim($path, '-') === '' ? 'unnamed' : trim($path, '-');
    }

    private function diagnostic(string $code): void
    {
        if ($code === '' || isset($this->diagnosticSet[$code])) {
            return;
        }
        $this->diagnosticSet[$code] = true;
        $this->diagnostics[] = $code;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlocksText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if (in_array($block->type, ['paragraph', 'plain', 'heading', 'term', 'definition_term', 'line'], true)) {
                $text = $this->plainInlineText($block->children);
                if ($text !== '') {
                    $parts[] = $text;
                }
                continue;
            }
            $childText = $this->plainBlocksText($block->children);
            if ($childText !== '') {
                $parts[] = $childText;
            }
        }

        return trim(implode(' ', $parts));
    }

    private function replaceTocPlaceholders(AstNode $document): AstNode
    {
        $headings = [];
        $this->collectHeadings($document, $headings);

        return $this->mapNode($document, function (AstNode $node) use ($headings): AstNode {
            if ($node->type !== 'div' || $node->attr('latexTocPlaceholder') !== true) {
                return $node;
            }

            return $this->tocBlock($headings);
        });
    }

    /**
     * @param list<array{id:string, text:string, level:int}> $headings
     */
    private function collectHeadings(AstNode $node, array &$headings): void
    {
        if ($node->type === 'heading' && $node->attr('latexCommand') !== 'maketitle') {
            $id = trim((string) $node->attr('id', ''));
            $text = trim((string) $node->attr('text', $this->plainInlineText($node->children)));
            if ($id !== '' && $text !== '' && $id !== 'abstract') {
                $headings[] = [
                    'id' => $id,
                    'text' => $text,
                    'level' => max(1, min(6, (int) $node->attr('level', 1))),
                ];
            }
        }
        foreach ($node->children as $child) {
            $this->collectHeadings($child, $headings);
        }
    }

    /**
     * @param list<array{id:string, text:string, level:int}> $headings
     */
    private function tocBlock(array $headings): AstNode
    {
        $items = [];
        foreach ($headings as $heading) {
            $items[] = new AstNode('list_item', ['loose' => false], [
                new AstNode('plain', ['text' => $heading['text']], [
                    new AstNode('link', ['url' => '#' . $heading['id'], 'title' => ''], [
                        new AstNode('text', ['text' => $heading['text']]),
                    ]),
                ]),
            ]);
        }
        $children = [new AstNode('heading', [
            'level' => 2,
            'id' => 'table-of-contents',
            'text' => 'Contents',
        ], [new AstNode('text', ['text' => 'Contents'])])];
        if ($items !== []) {
            $children[] = new AstNode('bullet_list', ['loose' => false, 'classes' => ['latex-toc-list']], $items);
        }

        return new AstNode('div', [
            'id' => 'table-of-contents',
            'classes' => ['latex-table-of-contents'],
        ], $children);
    }

    private function resolveReferences(AstNode $document): AstNode
    {
        return $this->mapNode($document, function (AstNode $node): AstNode {
            if ($node->type !== 'link') {
                return $node;
            }
            $key = $node->attr('latexReferenceKey');
            if (!is_string($key) || $key === '') {
                return $node;
            }
            $entry = $this->labels[$key] ?? null;
            if ($entry === null) {
                $this->diagnostic('latex-unresolved-reference:' . $this->diagnosticPath($key));

                return $node;
            }
            $display = $entry['display'] === '' ? $key : $entry['display'];
            $command = (string) $node->attr('latexReferenceCommand', 'ref');
            if ($command === 'autoref' && in_array($entry['kind'], ['figure', 'table', 'equation'], true)) {
                $display = ucfirst($entry['kind']) . ' ' . $display;
            }
            if ($command === 'pageref') {
                $display = 'page ' . $display;
            }

            return new AstNode('link', array_replace($node->attrs, [
                'url' => '#' . $entry['id'],
                'title' => '',
            ]), [new AstNode('text', ['text' => $display])]);
        });
    }

    private function applyResolvedBibliography(AstNode $document): AstNode
    {
        if (!$this->bibliographyRequested || $this->bibliographyFiles === []) {
            if ($this->bibliographyRequested && $this->bibliographyFiles === []) {
                $this->diagnostic('latex-bibliography-unresolved');
            }

            return $document;
        }
        if (!$this->bibliographyResolutionEnabled()) {
            $this->diagnostic('latex-bibliography-resolution-disabled');

            return $document;
        }
        $items = [];
        foreach ($this->bibliographyFiles as $file) {
            try {
                array_push($items, ...CitationCslProcessor::bibtexItems($file['bytes']));
            } catch (\Throwable) {
                $this->diagnostic('latex-bibliography-invalid:' . $this->diagnosticPath($file['path']));
            }
        }
        if ($items === []) {
            $this->diagnostic('latex-bibliography-empty');

            return $document;
        }
        $processor = CitationCslProcessor::fromItems($items);
        $document = $processor->apply($document);
        $document = $processor->appendBibliography($document);
        $attrs = $document->attrs;
        $attrs['cslItems'] = CitationCslProcessor::normalizeItems($items);
        $attrs['cslItemCount'] = count($attrs['cslItems']);
        $latex = is_array($attrs['latex'] ?? null) ? $attrs['latex'] : [];
        $latex['bibliographyResolved'] = true;
        $latex['bibliographyItemCount'] = $attrs['cslItemCount'];
        $attrs['latex'] = $latex;

        return new AstNode('document', $attrs, $document->children);
    }

    private function documentWithFinalDiagnostics(AstNode $document): AstNode
    {
        $attrs = $document->attrs;
        $latex = is_array($attrs['latex'] ?? null) ? $attrs['latex'] : [];
        $latex['diagnostics'] = $this->diagnostics;
        $attrs['latex'] = $latex;
        $meta = is_array($attrs['meta'] ?? null) ? $attrs['meta'] : [];
        if ($this->diagnostics !== []) {
            $meta['latexDiagnostics'] = $this->diagnostics;
        }
        $attrs['meta'] = $meta;

        return new AstNode('document', $attrs, $document->children);
    }

    private function mapNode(AstNode $node, callable $mapper): AstNode
    {
        $children = [];
        foreach ($node->children as $child) {
            $children[] = $this->mapNode($child, $mapper);
        }
        $node = new AstNode($node->type, $node->attrs, $children);

        return $mapper($node);
    }
}
