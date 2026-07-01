<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownWriter
{
    private const MAX_TEMPLATE_PARTIAL_DEPTH = 50;
    private const TEMPLATE_BREAKABLE_SPACE = "\x1F";
    private const TEMPLATE_BLOCK_KEY = "\0pandoc_plain_template_block";

    /** @var list<array{label:string, node:AstNode}> */
    private array $notes = [];

    /** @var list<array{label:string, url:string, title:string, attrs:array<string, mixed>}> */
    private array $references = [];

    /** @var array<string, int> */
    private array $noteLabelUses = [];

    /** @var array<string, bool> */
    private array $noteUsedLabels = [];

    /** @var array<string, int> */
    private array $referenceLabelUses = [];

    /** @var array<string, bool> */
    private array $referenceUsedLabels = [];

    /** @var array<string, string> */
    private array $referenceTargetLabels = [];

    /** @var array<string, int> */
    private array $headingAutoIdUses = [];

    private int $nextNoteNumber = 1;

    private int $lastReferenceIndex = 0;

    private bool $escapeInlineSpaces = false;

    /** @var list<string> */
    private array $plainTemplatePartialStack = [];

    /**
     * @param array{variant?: string, setextHeadings?: bool, referenceLinks?: bool, referenceLocation?: string, linkAttributes?: bool, headerAttributes?: bool, fencedCodeBlocks?: bool, backtickCodeBlocks?: bool, fencedCodeAttributes?: bool, definitionLists?: bool, lineBlocks?: bool, bracketedSpans?: bool, nativeSpans?: bool, fencedDivs?: bool, nativeDivs?: bool, implicitFigures?: bool, markdownInHtmlBlocks?: bool, markdownAttribute?: bool, rawAttribute?: bool, rawHtml?: bool, rawTex?: bool, simpleTables?: bool, pipeTables?: bool, multilineTables?: bool, gridTables?: bool, tableCaptions?: bool, columns?: int, tabStop?: int, wrap?: string, strikeout?: bool, superscript?: bool, subscript?: bool, preferAscii?: bool, smart?: bool, escapedLineBreaks?: bool, hardLineBreaks?: bool, wikilinksTitleAfterPipe?: bool, wikilinksTitleBeforePipe?: bool, gutenberg?: bool, template?: bool|string, templatePath?: string, standalone?: bool, tableOfContents?: bool, toc?: bool, tocDepth?: int, numberSections?: bool, variables?: array<string, mixed>, partials?: array<string, string>, headerIncludes?: mixed, includeBefore?: mixed, includeAfter?: mixed, opmlNoteMarkdown?: bool} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Markdown writer expects a document node');
        }

        $this->notes = [];
        $this->references = [];
        $this->noteLabelUses = [];
        $this->noteUsedLabels = [];
        $this->referenceLabelUses = [];
        $this->referenceUsedLabels = [];
        $this->referenceTargetLabels = [];
        $this->headingAutoIdUses = [];
        $this->nextNoteNumber = 1;
        $this->lastReferenceIndex = 0;
        $this->escapeInlineSpaces = false;
        $this->plainTemplatePartialStack = [];

        $customPlainTemplate = $this->customPlainTemplateSource();
        if ($customPlainTemplate !== null) {
            return $this->renderCustomPlainTemplate($document, $customPlainTemplate);
        }

        $blocks = [];
        $titleBlock = $this->renderPlainTemplateTitleBlock($document);
        if ($titleBlock !== '') {
            $this->appendBlockEntry($blocks, $titleBlock);
        }
        foreach ($this->renderPlainTemplateVariableBlocks($document, 'header-includes') as $block) {
            $this->appendBlockEntry($blocks, $block);
        }
        foreach ($this->renderPlainTemplateVariableBlocks($document, 'include-before') as $block) {
            $this->appendBlockEntry($blocks, $block);
        }
        $tableOfContents = $this->renderStandaloneTableOfContents($document);
        if ($tableOfContents !== '') {
            $this->appendBlockEntry($blocks, $tableOfContents);
        }

        $bodyOverride = $this->renderPlainTemplateBodyOverride($document);
        if ($bodyOverride !== null) {
            $this->appendBlockEntry($blocks, $bodyOverride);
        } else {
            foreach ($document->children as $index => $node) {
                if ($this->referenceLocation() === 'end_of_section' && $node->type === 'heading' && $index > 0) {
                    $this->appendPendingDefinitionEntries($blocks);
                }

                if (
                    $index > 0
                    && $this->needsAdjacentListBlockSeparator($document->children[$index - 1], $node)
                ) {
                    $this->appendBlockEntry($blocks, $this->listSeparatorBlock());
                }

                if (
                    $node->type === 'code_block'
                    && $index > 0
                    && $this->isListBlock($document->children[$index - 1])
                    && $this->codeBlockRendersIndented($node)
                ) {
                    $this->appendBlockEntry($blocks, '<!-- -->');
                }

                $lines = $this->renderBlock($node, 0);
                if ($lines !== []) {
                    $this->appendBlockEntry($blocks, implode("\n", $lines), $node);
                }

                if ($this->referenceLocation() === 'end_of_block') {
                    $this->appendPendingDefinitionEntries($blocks);
                }
            }
            $this->appendPendingDefinitionEntries($blocks);
        }
        foreach ($this->renderPlainTemplateVariableBlocks($document, 'include-after') as $block) {
            $this->appendBlockEntry($blocks, $block);
        }

        return $this->joinBlockEntries($blocks);
    }

    /**
     * @return list<string>
     */
    private function renderBlock(AstNode $node, int $indent): array
    {
        return match ($node->type) {
            'paragraph', 'plain' => $this->renderPlainOrParagraphBlock($node, $indent),
            'heading' => $this->renderHeading($node, $indent),
            'figure' => $this->renderFigure($node, $indent),
            'bullet_list' => $this->renderList($node, false, $indent),
            'ordered_list' => $this->renderList($node, true, $indent),
            'definition_list' => $this->renderDefinitionList($node, $indent),
            'line_block' => $this->renderLineBlock($node, $indent),
            'blockquote' => $this->renderBlockQuote($node, $indent),
            'code_block' => $this->renderCodeBlock($node, $indent),
            'horizontal_rule' => $this->renderHorizontalRule($indent),
            'table' => $this->renderTable($node, $indent),
            'raw_html', 'raw_tex', 'raw_block', 'raw_markdown' => $this->renderRawBlock($node, $indent),
            'div' => $this->renderDiv($node, $indent),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function renderPlainOrParagraphBlock(AstNode $node, int $indent): array
    {
        $contents = $this->renderBlockInlines($node->children, true);
        if ($this->opmlNoteMarkdownEnabled() && $this->writerWrapText() === 'auto') {
            return $this->prefixLines(
                $this->wrapOpmlMarkdownLines($contents, max(1, $this->writerColumns() - $indent)),
                $indent
            );
        }

        return [str_repeat(' ', $indent) . $contents];
    }

    /**
     * @return list<string>
     */
    private function renderHeading(AstNode $node, int $indent): array
    {
        if ($this->isPlainTextVariant()) {
            return $this->renderPlainTextHeading($node, $indent);
        }

        $level = max(1, min(6, (int) $node->attr('level', 1)));
        $text = $this->renderHeadingText($node);
        $plainText = $node->children === [] ? (string) $node->attr('text', '') : $this->plainInlineText($node->children);
        $autoId = $this->uniqueHeadingAutoIdentifier($plainText);
        $attrs = $this->renderHeadingAttributes($node, $autoId);
        $prefix = str_repeat(' ', $indent);

        if ($indent === 0 && (bool) ($this->options['setextHeadings'] ?? false) && ($level === 1 || $level === 2)) {
            $headingText = $text . ($attrs === '' ? '' : ' ' . $attrs);

            return [
                $headingText,
                str_repeat($level === 1 ? '=' : '-', max(1, strlen($text))),
            ];
        }

        return [$prefix . str_repeat('#', $level) . ' ' . $text . ($attrs === '' ? '' : ' ' . $attrs)];
    }

    /**
     * @return list<string>
     */
    private function renderPlainTextHeading(AstNode $node, int $indent): array
    {
        $text = $node->children === []
            ? $this->renderPlainText((string) $node->attr('text', ''))
            : $this->renderPlainInlines($node->children);
        $text = trim(preg_replace('/[ \t]*\R[ \t]*/u', ' ', $text) ?? $text);

        return [str_repeat(' ', $indent) . $text];
    }

    private function renderPlainTemplateTitleBlock(AstNode $document): string
    {
        if (!$this->isPlainTextVariant() || !$this->templateEnabled()) {
            return '';
        }

        $titleblockOverride = $this->renderPlainTemplateContextOverride($document, 'titleblock');
        if ($titleblockOverride !== null) {
            return $titleblockOverride;
        }

        $meta = $document->attr('meta', []);
        if (!is_array($meta) || $meta === []) {
            return '';
        }

        $title = $this->renderPlainMetaInlines($meta['titleInlines'] ?? null, (string) ($meta['title'] ?? ''));
        $authors = $this->renderPlainMetaAuthors($meta);
        $date = $this->renderPlainMetaInlines($meta['dateInlines'] ?? null, (string) ($meta['date'] ?? ''));
        if ($title === '' && $authors === [] && $date === '') {
            return '';
        }

        return implode("\n", [
            $title,
            implode('; ', $authors),
            $date,
        ]);
    }

    private function renderPlainTemplateBodyOverride(AstNode $document): ?string
    {
        if (!$this->isPlainTextVariant() || !$this->templateEnabled()) {
            return null;
        }

        return $this->renderPlainTemplateContextOverride($document, 'body');
    }

    private function renderCustomPlainTemplate(AstNode $document, string $template): string
    {
        $this->assertPlainTemplateCompiles($template, (string) ($this->options['templatePath'] ?? ''));

        $bodyOverride = $this->renderPlainTemplateBodyOverride($document);
        $body = $bodyOverride ?? $this->renderPlainTemplateAutomaticBody($document);
        $titleBlock = $this->renderPlainTemplateTitleBlock($document);
        $tableOfContents = $this->renderPlainTemplateTableOfContents($document);
        $context = $this->plainTemplateContextMap($document, $body, $titleBlock, $tableOfContents);

        return trim($this->renderPlainTemplateOutput($this->renderPlainTemplateString($template, $context)), "\r\n");
    }

    /**
     * @param list<string> $stack
     * @param list<string> $partialStack
     */
    private function assertPlainTemplateCompiles(
        string $template,
        string $path = '',
        int $partialDepth = 1,
        array $stack = [],
        array $partialStack = []
    ): void {
        $offset = 0;
        while (true) {
            $token = $this->nextPlainTemplateToken($template, $offset);
            $nextDollar = strpos($template, '$', $offset);
            if ($token === null) {
                if ($nextDollar !== false) {
                    $this->throwPlainTemplateUnclosedDollarDiagnostic($template, $path, $nextDollar);
                }

                return;
            }

            if ($nextDollar !== false && $nextDollar < $token['start']) {
                $this->throwPlainTemplateUnclosedDollarDiagnostic($template, $path, $nextDollar);
            }

            $offset = $token['end'];
            if ($token['kind'] !== 'command') {
                continue;
            }

            $this->assertPlainTemplateCommandSyntax($template, $path, $token);
            $command = $this->parsePlainTemplateCommand($token['content']);
            if ($command === null) {
                $this->throwPlainTemplateCompileError(
                    $template,
                    $path,
                    $token['end'] - 1,
                    '"' . substr($template, $token['end'] - 1, 1) . '"',
                    'letter or digit or "()"'
                );
            }

            if ($command['kind'] === 'if' || $command['kind'] === 'for') {
                $stack[] = $command['kind'];
                continue;
            }

            if ($command['kind'] === 'endif' || $command['kind'] === 'endfor') {
                $expected = $command['kind'] === 'endif' ? 'if' : 'for';
                if (end($stack) === $expected) {
                    array_pop($stack);
                }
                continue;
            }

            if ($command['kind'] === 'sep' && !in_array('for', $stack, true)) {
                $this->throwPlainTemplateCompileError(
                    $template,
                    $path,
                    $token['end'] - 1,
                    '"' . substr($template, $token['end'] - 1, 1) . '"',
                    'letter or digit or "()"'
                );
            }

            if (($command['kind'] === 'else' || $command['kind'] === 'elseif') && !in_array('if', $stack, true)) {
                $this->throwPlainTemplateCompileError(
                    $template,
                    $path,
                    $token['end'] - 1,
                    '"' . substr($template, $token['end'] - 1, 1) . '"',
                    'letter or digit or "()"'
                );
            }

            if ($command['kind'] === 'partial' || $command['kind'] === 'applied_partial') {
                $partialName = $command['partial'];
                $partial = $this->plainTemplatePartialSource($partialName);
                if ($partial === null || $partialDepth > self::MAX_TEMPLATE_PARTIAL_DEPTH) {
                    continue;
                }

                if (in_array($partialName, $partialStack, true)) {
                    continue;
                }

                $partialStack[] = $partialName;
                $this->assertPlainTemplateCompiles(
                    $partial,
                    $this->plainTemplatePartialDiagnosticPath($partialName, $path),
                    $partialDepth + 1,
                    [],
                    $partialStack
                );
                array_pop($partialStack);
            }
        }
    }

    /**
     * @param array{kind:string, start:int, end:int, source:string, content:string} $token
     */
    private function assertPlainTemplateCommandSyntax(string $template, string $path, array $token): void
    {
        $contentOffset = $token['start'] + (str_starts_with($token['source'], '${') ? 2 : 1);
        $raw = $token['content'];
        $leading = strlen($raw) - strlen(ltrim($raw, " \t"));
        $content = trim($raw);
        $trimmedOffset = $contentOffset + $leading;

        if (
            preg_match('/^(if|elseif|for)\(/', $content) === 1
            && preg_match('/^(if|elseif|for)\(.*\)$/s', $content) !== 1
        ) {
            $this->throwPlainTemplateCompileError(
                $template,
                $path,
                $token['end'] - 1,
                '"' . substr($template, $token['end'] - 1, 1) . '"',
                '".", "/" or ")"'
            );
        }

        $expression = null;
        $expressionOffset = $trimmedOffset;
        if (preg_match('/^(if|elseif|for)\(\s*(.*?)\s*\)$/s', $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            $expression = $match[2][0];
            $expressionOffset = $trimmedOffset + $match[2][1];
        } else {
            [$body] = $this->plainTemplateSplitTrailingSeparator($content);
            if (preg_match('/^(.*?)\s*:\s*[A-Za-z][A-Za-z0-9_.-]*\(\)(.*)$/s', $body, $match, PREG_OFFSET_CAPTURE) === 1) {
                $this->assertPlainTemplatePipeExpressionSyntax($match[1][0], $trimmedOffset + $match[1][1], $template, $path, $token);
                $suffix = $match[2][0];
                if ($suffix !== '') {
                    $this->assertPlainTemplatePipeExpressionSyntax($suffix, $trimmedOffset + $match[2][1], $template, $path, $token);
                }

                return;
            }

            if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]*\(\)(.*)$/s', $body, $match, PREG_OFFSET_CAPTURE) === 1) {
                $suffix = $match[1][0];
                if ($suffix !== '') {
                    $this->assertPlainTemplatePipeExpressionSyntax($suffix, $trimmedOffset + $match[1][1], $template, $path, $token);
                }

                return;
            }

            $expression = $body;
        }

        $this->assertPlainTemplatePipeExpressionSyntax($expression, $expressionOffset, $template, $path, $token);
    }

    /**
     * @param array{kind:string, start:int, end:int, source:string, content:string} $token
     */
    private function assertPlainTemplatePipeExpressionSyntax(
        string $expression,
        int $expressionOffset,
        string $template,
        string $path,
        array $token
    ): void {
        $parts = $this->plainTemplateSplitPipeExpressionParts($expression);
        if ($parts === null || $parts === [] || count($parts) === 1) {
            return;
        }

        foreach (array_slice($parts, 1) as $part) {
            $pipe = $part['value'];
            if ($pipe === '' || $this->plainTemplatePipeSpec($pipe) !== null) {
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)(.*)$/s', $pipe, $match) !== 1) {
                $this->throwPlainTemplateCompileError(
                    $template,
                    $path,
                    $token['end'] - 1,
                    '"' . substr($template, $token['end'] - 1, 1) . '"',
                    'letter, letter or digit or "()"'
                );
            }

            $name = $match[1];
            $args = $match[2];
            if (!in_array($name, $this->plainTemplateKnownPipeNames(), true)) {
                $this->throwPlainTemplateCompileError(
                    $template,
                    $path,
                    $token['end'] - 1,
                    '"' . substr($template, $token['end'] - 1, 1) . '"',
                    'letter, letter or digit or "()"',
                    'Unknown pipe ' . $name
                );
            }

            if (in_array($name, ['left', 'right', 'center'], true)) {
                if (trim($args) === '') {
                    $this->throwPlainTemplateCompileError(
                        $template,
                        $path,
                        $token['end'] - 1,
                        '"' . substr($template, $token['end'] - 1, 1) . '"',
                        'letter, integer parameter for pipe, letter or digit or "()"'
                    );
                }

                $spaces = strlen($args) - strlen(ltrim($args));
                $argumentOffset = $expressionOffset + $part['start'] + strlen($name) + $spaces;
                $unexpected = substr($template, $argumentOffset, 1);
                if ($unexpected !== '' && !ctype_digit($unexpected)) {
                    $this->throwPlainTemplateCompileError(
                        $template,
                        $path,
                        $argumentOffset,
                        '"' . $unexpected . '"',
                        'integer parameter for pipe'
                    );
                }
            }
        }
    }

    private function throwPlainTemplateUnclosedDollarDiagnostic(string $template, string $path, int $dollarOffset): void
    {
        $cursor = $dollarOffset + 1;
        $length = strlen($template);
        if ($cursor < $length && preg_match('/[A-Za-z]/', $template[$cursor]) === 1) {
            $cursor++;
            while (
                $cursor < $length
                && preg_match('/[A-Za-z0-9_.-]/', $template[$cursor]) === 1
            ) {
                $cursor++;
            }
            while ($cursor < $length && ($template[$cursor] === ' ' || $template[$cursor] === "\t")) {
                $cursor++;
            }
        }

        $unexpected = $cursor < $length ? '"' . $template[$cursor] . '"' : 'end of input';
        $this->throwPlainTemplateCompileError($template, $path, min($cursor, max(0, $length - 1)), $unexpected, '"$"');
    }

    private function throwPlainTemplateCompileError(
        string $template,
        string $path,
        int $offset,
        string $unexpected,
        string $expecting,
        ?string $extra = null
    ): never {
        [$line, $column] = $this->plainTemplateLineColumn($template, $offset);
        $prefix = $path === ''
            ? "(line {$line}, column {$column}):"
            : "\"{$path}\" (line {$line}, column {$column}):";
        $message = $prefix . "\nunexpected " . $unexpected . "\nexpecting " . $expecting;
        if ($extra !== null) {
            $message .= "\n" . $extra;
        }

        throw new \InvalidArgumentException($message);
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function plainTemplateLineColumn(string $template, int $offset): array
    {
        $offset = max(0, min($offset, max(0, strlen($template) - 1)));
        $prefix = substr($template, 0, $offset);
        $line = substr_count(str_replace("\r\n", "\n", $prefix), "\n") + 1;
        $lineFeed = strrpos($prefix, "\n");
        $carriageReturn = strrpos($prefix, "\r");
        $lineStart = max($lineFeed === false ? -1 : $lineFeed, $carriageReturn === false ? -1 : $carriageReturn) + 1;

        return [$line, $offset - $lineStart + 1];
    }

    private function plainTemplatePartialDiagnosticPath(string $partialName, string $currentPath): string
    {
        $path = str_contains($partialName, '.') ? $partialName : $partialName . '.txt';
        if ($currentPath === '' || str_contains($partialName, '/') || str_contains($partialName, '\\')) {
            return $path;
        }

        $directory = dirname($currentPath);
        return $directory === '.' ? $path : $directory . '/' . $path;
    }

    private function renderPlainTemplateAutomaticBody(AstNode $document): string
    {
        $blocks = [];
        foreach ($document->children as $index => $node) {
            if ($this->referenceLocation() === 'end_of_section' && $node->type === 'heading' && $index > 0) {
                $this->appendPendingDefinitionEntries($blocks);
            }

            if (
                $index > 0
                && $this->needsAdjacentListBlockSeparator($document->children[$index - 1], $node)
            ) {
                $this->appendBlockEntry($blocks, $this->listSeparatorBlock());
            }

            if (
                $node->type === 'code_block'
                && $index > 0
                && $this->isListBlock($document->children[$index - 1])
                && $this->codeBlockRendersIndented($node)
            ) {
                $this->appendBlockEntry($blocks, '<!-- -->');
            }

            $lines = $this->renderBlock($node, 0);
            if ($lines !== []) {
                $this->appendBlockEntry($blocks, implode("\n", $lines), $node);
            }

            if ($this->referenceLocation() === 'end_of_block') {
                $this->appendPendingDefinitionEntries($blocks);
            }
        }
        $this->appendPendingDefinitionEntries($blocks);

        return $this->joinBlockEntries($blocks);
    }

    /**
     * @return array<string, mixed>
     */
    private function plainTemplateContextMap(AstNode $document, string $body, string $titleBlock, string $tableOfContents): array
    {
        $meta = $document->attr('meta', []);
        $context = is_array($meta) ? $meta : [];

        foreach ($this->plainTemplateOptionContextValues() as $key => $value) {
            $context[$key] = $value;
        }

        $variables = $this->options['variables'] ?? [];
        if (is_array($variables)) {
            foreach ($variables as $key => $value) {
                $context[(string) $key] = $value;
            }
        }

        $context['meta-json'] = $this->plainTemplateMetaJson($meta);

        if (!$this->plainTemplateContextHas($context, 'titleblock') && $titleBlock !== '') {
            $context['titleblock'] = $titleBlock;
        }

        if (!$this->plainTemplateContextHas($context, 'table-of-contents') && $tableOfContents !== '') {
            $context['table-of-contents'] = $tableOfContents;
        }

        if (!$this->plainTemplateContextHas($context, 'toc') && $tableOfContents !== '') {
            $context['toc'] = $tableOfContents;
        }

        if (!$this->plainTemplateContextHas($context, 'body')) {
            $context['body'] = $body;
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function plainTemplateOptionContextValues(): array
    {
        $context = [];
        foreach ([
            'body',
            'titleblock',
            'header-includes',
            'headerIncludes',
            'include-before',
            'includeBefore',
            'include-after',
            'includeAfter',
        ] as $key) {
            if (array_key_exists($key, $this->options)) {
                $context[$key] = $this->options[$key];
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function plainTemplateContextHas(array $context, string $name): bool
    {
        [$found] = $this->plainTemplateContextLookup($context, $name);

        return $found;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{0:bool, 1:mixed}
     */
    private function plainTemplateContextLookup(array $context, string $name): array
    {
        $camelName = $this->templateVariableCamelName($name);
        foreach ([$name, $camelName] as $key) {
            if (array_key_exists($key, $context)) {
                return [true, $context[$key]];
            }
        }

        if (str_contains($name, '.')) {
            $prefixLookup = $this->plainTemplateDottedPrefixContextLookup($context, $name);
            if ($prefixLookup[0]) {
                return $prefixLookup;
            }

            return $this->plainTemplateDottedContextLookup($context, explode('.', $name));
        }

        return [false, null];
    }

    /**
     * @return array{0:bool, 1:mixed}
     */
    private function plainTemplateDottedPrefixContextLookup(array $context, string $name): array
    {
        $segments = explode('.', $name);
        for ($length = count($segments) - 1; $length > 0; $length--) {
            $prefix = implode('.', array_slice($segments, 0, $length));
            $camelPrefix = $this->templateVariableCamelName($prefix);
            foreach ([$prefix, $camelPrefix] as $key) {
                if (!array_key_exists($key, $context)) {
                    continue;
                }

                return $this->plainTemplateDottedContextLookup(
                    $context[$key],
                    array_slice($segments, $length)
                );
            }
        }

        return [false, null];
    }

    /**
     * @param list<string> $segments
     * @return array{0:bool, 1:mixed}
     */
    private function plainTemplateDottedContextLookup(mixed $value, array $segments): array
    {
        foreach ($segments as $segment) {
            if (!is_array($value)) {
                return [false, null];
            }

            $camelSegment = $this->templateVariableCamelName($segment);
            $found = false;
            foreach ([$segment, $camelSegment] as $key) {
                if (array_key_exists($key, $value)) {
                    $value = $value[$key];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return [false, null];
            }
        }

        return [true, $value];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderPlainTemplateString(string $template, array $context, int $partialDepth = 0): string
    {
        $rendered = '';
        $lineParts = [];
        $offset = 0;
        $breakableSpaces = false;
        $nesting = null;
        $suppressLeadingLineBreak = false;

        while (($token = $this->nextPlainTemplateToken($template, $offset)) !== null) {
            $literalSegment = substr($template, $offset, $token['start'] - $offset);
            $offset = $token['end'];
            if ($suppressLeadingLineBreak) {
                $literalSegment = $this->plainTemplateRemoveLeadingLineBreak($literalSegment);
                $suppressLeadingLineBreak = false;
            }

            if ($token['kind'] === 'literal') {
                $this->appendPlainTemplateRenderable($rendered, $lineParts, $this->applyPlainTemplateNestingToLiteral(
                    $this->renderPlainTemplateLiteralSegment($literalSegment, $breakableSpaces),
                    $nesting
                ));
                $literalDollar = $this->applyPlainTemplateNestingToValue('$', $nesting);
                $this->appendPlainTemplateRenderable($rendered, $lineParts, $literalDollar);
                $suppressLeadingLineBreak = $this->plainTemplateRenderedValueEndsWithLineBreak($literalDollar);
                continue;
            }

            if ($token['kind'] === 'comment') {
                $this->appendPlainTemplateRenderable($rendered, $lineParts, $this->applyPlainTemplateNestingToLiteral(
                    $this->renderPlainTemplateLiteralSegment($literalSegment, $breakableSpaces),
                    $nesting
                ));
                continue;
            }

            $command = $this->parsePlainTemplateCommand($token['content']);
            $tokenLinePrefixColumn = $this->plainTemplateTokenLinePrefixColumn($template, $token);
            if (
                $nesting !== null
                && $tokenLinePrefixColumn !== null
                && $tokenLinePrefixColumn < $nesting['sourceColumn']
            ) {
                $nesting = null;
            }
            if (
                $command !== null
                && ($command['kind'] === 'if' || $command['kind'] === 'for')
                && $this->plainTemplateCommandStartsOwnLine($template, $token)
            ) {
                $literalSegment = $this->removePlainTemplateStandaloneIndent($literalSegment);
            }
            $this->appendPlainTemplateRenderable($rendered, $lineParts, $this->applyPlainTemplateNestingToLiteral(
                $this->renderPlainTemplateLiteralSegment($literalSegment, $breakableSpaces),
                $nesting
            ));
            if ($command === null) {
                $this->appendPlainTemplateRenderable($rendered, $lineParts, $token['source']);
                continue;
            }

            if ($command['kind'] === 'breakable_space') {
                $breakableSpaces = !$breakableSpaces;
                continue;
            }

            if ($command['kind'] === 'nest') {
                $nesting = [
                    'indent' => str_repeat(' ', $this->plainTemplateCurrentLineDisplayWidth(
                        $this->plainTemplateRenderedOutput($rendered, $lineParts, false)
                    )),
                    'sourceColumn' => $this->plainTemplateSourceColumn($template, $token['start']),
                ];
                continue;
            }

            if ($command['kind'] === 'variable') {
                [$found, $value] = $this->plainTemplateContextLookup($context, $command['name']);
                $pipes = $command['pipes'] ?? [];
                if ($found || $this->plainTemplatePipesContainAlignment($pipes)) {
                    $value = $this->applyPlainTemplatePipes($value, $command['pipes'] ?? []);
                }
                $output = $found || $this->plainTemplateIsBlock($value)
                    ? $this->renderPlainTemplateInterpolatedValue($value, $command['separator'] ?? null)
                    : '';
                $automaticNesting = $nesting ?? $this->plainTemplateAutomaticNesting(
                    $template,
                    $token,
                    $this->plainTemplateRenderedOutput($rendered, $lineParts, false)
                );
                $nestedOutput = $this->plainTemplateIsBlock($output)
                    ? $output
                    : $this->applyPlainTemplateNestingToValue($output, $automaticNesting);
                $this->appendPlainTemplateRenderable($rendered, $lineParts, $nestedOutput);
                if (!$this->plainTemplateRenderableIsEmpty($nestedOutput)) {
                    $suppressLeadingLineBreak = $this->plainTemplateRenderedValueEndsWithLineBreak($nestedOutput);
                }
                continue;
            }

            if ($command['kind'] === 'partial') {
                $partial = $this->renderPlainTemplatePartial($command['partial'], $context, $partialDepth);
                if ($partial !== null) {
                    $partial = $this->renderPlainTemplateVariableValue(
                        $this->applyPlainTemplatePipes($partial, $command['pipes'] ?? [])
                    );
                }
                $partialOutput = $partial ?? $token['source'];
                $automaticNesting = $nesting ?? $this->plainTemplateAutomaticNesting(
                    $template,
                    $token,
                    $this->plainTemplateRenderedOutput($rendered, $lineParts, false)
                );
                $nestedOutput = $this->plainTemplateIsBlock($partialOutput)
                    ? $partialOutput
                    : $this->applyPlainTemplateNestingToValue($partialOutput, $automaticNesting);
                $this->appendPlainTemplateRenderable($rendered, $lineParts, $nestedOutput);
                if (!$this->plainTemplateRenderableIsEmpty($nestedOutput)) {
                    $suppressLeadingLineBreak = $this->plainTemplateRenderedValueEndsWithLineBreak($nestedOutput);
                }
                continue;
            }

            if ($command['kind'] === 'applied_partial') {
                $partial = $this->renderPlainTemplateAppliedPartial(
                    $command['name'],
                    $command['namePipes'] ?? [],
                    $command['partial'],
                    $command['separator'] ?? null,
                    $context,
                    $partialDepth
                );
                if ($partial !== null) {
                    $partial = $this->renderPlainTemplateVariableValue(
                        $this->applyPlainTemplatePipes($partial, $command['pipes'] ?? [])
                    );
                }
                $partialOutput = $partial ?? $token['source'];
                $automaticNesting = $nesting ?? $this->plainTemplateAutomaticNesting(
                    $template,
                    $token,
                    $this->plainTemplateRenderedOutput($rendered, $lineParts, false)
                );
                $nestedOutput = $this->plainTemplateIsBlock($partialOutput)
                    ? $partialOutput
                    : $this->applyPlainTemplateNestingToValue($partialOutput, $automaticNesting);
                $this->appendPlainTemplateRenderable($rendered, $lineParts, $nestedOutput);
                if (
                    !$this->plainTemplateRenderableIsEmpty($nestedOutput)
                    && !$this->plainTemplateRenderedValueEndsWithLineBreak($nestedOutput)
                ) {
                    $suppressLeadingLineBreak = false;
                }
                continue;
            }

            if ($command['kind'] === 'if' || $command['kind'] === 'for') {
                $block = $this->extractPlainTemplateBlock($template, $offset, $command['kind']);
                if ($block === null) {
                    $this->appendPlainTemplateRenderable(
                        $rendered,
                        $lineParts,
                        $this->applyPlainTemplateNestingToValue($token['source'], $nesting)
                    );
                    continue;
                }

                $output = $command['kind'] === 'if'
                    ? $this->renderPlainTemplateConditional($command['name'], $block['body'], $context, $partialDepth, $command['pipes'] ?? [], $block['multiline'])
                    : $this->renderPlainTemplateLoop($command['name'], $block['body'], $context, $partialDepth, $command['pipes'] ?? []);
                $nestedOutput = $this->applyPlainTemplateNestingToValue($output, $nesting, $block['multiline']);
                $this->appendPlainTemplateRenderable($rendered, $lineParts, $nestedOutput);
                if ($nestedOutput !== '' && !$block['multiline']) {
                    $suppressLeadingLineBreak = $this->plainTemplateRenderedValueEndsWithLineBreak($nestedOutput);
                }
                $offset = $block['offset'];
                continue;
            }

            $nestedOutput = $this->applyPlainTemplateNestingToValue($token['source'], $nesting);
            $this->appendPlainTemplateRenderable($rendered, $lineParts, $nestedOutput);
            if ($nestedOutput !== '') {
                $suppressLeadingLineBreak = $this->plainTemplateRenderedValueEndsWithLineBreak($nestedOutput);
            }
        }

        $tail = substr($template, $offset);
        if ($suppressLeadingLineBreak) {
            $tail = $this->plainTemplateRemoveLeadingLineBreak($tail);
        }

        $this->appendPlainTemplateRenderable($rendered, $lineParts, $this->applyPlainTemplateNestingToLiteral(
            $this->renderPlainTemplateLiteralSegment($tail, $breakableSpaces),
            $nesting
        ));

        return $this->plainTemplateRenderedOutput($rendered, $lineParts);
    }

    /**
     * @param list<array<string, mixed>|string> $lineParts
     */
    private function appendPlainTemplateRenderable(string &$rendered, array &$lineParts, mixed $value): void
    {
        if ($this->plainTemplateIsBlock($value)) {
            $lineParts[] = $value;
            return;
        }

        $this->appendPlainTemplateText($rendered, $lineParts, (string) $value);
    }

    /**
     * @param list<array<string, mixed>|string> $lineParts
     */
    private function appendPlainTemplateText(string &$rendered, array &$lineParts, string $text): void
    {
        if ($text === '') {
            return;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $offset = 0;
        while (($lineBreak = strpos($text, "\n", $offset)) !== false) {
            $segment = substr($text, $offset, $lineBreak - $offset);
            if ($segment !== '') {
                $lineParts[] = $segment;
            }
            $rendered .= $this->renderPlainTemplateLineParts($lineParts) . "\n";
            $lineParts = [];
            $offset = $lineBreak + 1;
        }

        $tail = substr($text, $offset);
        if ($tail !== '') {
            $lineParts[] = $tail;
        }
    }

    /**
     * @param list<array<string, mixed>|string> $lineParts
     */
    private function plainTemplateRenderedOutput(string $rendered, array $lineParts, bool $trimBlockTrailingSpaces = true): string
    {
        if ($lineParts === []) {
            return $rendered;
        }

        return $rendered . $this->renderPlainTemplateLineParts($lineParts, $trimBlockTrailingSpaces);
    }

    /**
     * @param list<array<string, mixed>|string> $lineParts
     */
    private function renderPlainTemplateLineParts(array $lineParts, bool $trimBlockTrailingSpaces = true): string
    {
        if ($lineParts === []) {
            return '';
        }

        $height = 1;
        $hasBlock = false;
        foreach ($lineParts as $part) {
            if ($this->plainTemplateIsBlock($part)) {
                $hasBlock = true;
                $height = max($height, count($part['lines']));
            }
        }

        $lines = array_fill(0, $height, '');
        foreach ($lineParts as $part) {
            if ($this->plainTemplateIsBlock($part)) {
                for ($index = 0; $index < $height; $index++) {
                    $lines[$index] .= $part['lines'][$index] ?? $part['fillLine'];
                }
                continue;
            }

            $text = (string) $part;
            $fill = str_repeat(' ', $this->markdownDisplayWidth(str_replace(self::TEMPLATE_BREAKABLE_SPACE, ' ', $text)));
            for ($index = 0; $index < $height; $index++) {
                $lines[$index] .= $index === 0 ? $text : $fill;
            }
        }

        if ($hasBlock && $trimBlockTrailingSpaces) {
            $lines = array_map(static fn (string $line): string => rtrim($line, " \t"), $lines);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function plainTemplateBlock(array $lines, string $fillLine): array
    {
        return [
            self::TEMPLATE_BLOCK_KEY => true,
            'lines' => $lines === [] ? [''] : array_values($lines),
            'fillLine' => $fillLine,
        ];
    }

    private function plainTemplateIsBlock(mixed $value): bool
    {
        return is_array($value) && ($value[self::TEMPLATE_BLOCK_KEY] ?? false) === true;
    }

    private function plainTemplateRenderableIsEmpty(mixed $value): bool
    {
        if ($this->plainTemplateIsBlock($value)) {
            foreach ($value['lines'] as $line) {
                if ($line !== '') {
                    return false;
                }
            }

            return $value['fillLine'] === '';
        }

        return $value === '';
    }

    private function plainTemplateRenderedValueEndsWithLineBreak(mixed $text): bool
    {
        if ($this->plainTemplateIsBlock($text)) {
            return false;
        }

        return $text !== '' && in_array(substr($text, -1), ["\n", "\r"], true);
    }

    private function plainTemplateRemoveLeadingLineBreak(string $text): string
    {
        if (str_starts_with($text, "\r\n")) {
            return substr($text, 2);
        }

        if ($text !== '' && in_array($text[0], ["\n", "\r"], true)) {
            return substr($text, 1);
        }

        return $text;
    }

    /**
     * @param array{start:int, end:int} $token
     */
    private function plainTemplateTokenLinePrefixColumn(string $template, array $token): ?int
    {
        $prefix = substr($template, 0, $token['start']);
        $lineFeed = strrpos($prefix, "\n");
        $carriageReturn = strrpos($prefix, "\r");
        $lineStart = max($lineFeed === false ? -1 : $lineFeed, $carriageReturn === false ? -1 : $carriageReturn) + 1;
        $linePrefix = substr($template, $lineStart, $token['start'] - $lineStart);
        if (preg_match('/^[ \t]*$/', $linePrefix) !== 1) {
            return null;
        }

        return strlen(str_replace("\t", ' ', $linePrefix));
    }

    /**
     * @param array{start:int, end:int} $token
     */
    private function plainTemplateCommandStartsOwnLine(string $template, array $token): bool
    {
        if ($this->plainTemplateLineBreakLengthAt($template, $token['end']) === 0) {
            return false;
        }

        return $this->plainTemplateTokenLinePrefixColumn($template, $token) !== null;
    }

    private function removePlainTemplateStandaloneIndent(string $segment): string
    {
        return preg_replace('/[ \t]+$/', '', $segment) ?? $segment;
    }

    private function renderPlainTemplateLiteralSegment(string $segment, bool $breakableSpaces): string
    {
        if (!$breakableSpaces || $segment === '') {
            return $segment;
        }

        return preg_replace('/[ \t\r\n]+/u', self::TEMPLATE_BREAKABLE_SPACE, $segment) ?? $segment;
    }

    private function renderPlainTemplateOutput(string $text): string
    {
        if (!str_contains($text, self::TEMPLATE_BREAKABLE_SPACE)) {
            return $text;
        }

        if ($this->writerWrapText() !== 'auto') {
            return str_replace(self::TEMPLATE_BREAKABLE_SPACE, ' ', $text);
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        foreach ($lines as $index => $line) {
            $lines[$index] = $this->wrapPlainTemplateBreakableLine($line);
        }

        return implode("\n", $lines);
    }

    private function wrapPlainTemplateBreakableLine(string $line): string
    {
        if (!str_contains($line, self::TEMPLATE_BREAKABLE_SPACE)) {
            return $line;
        }

        $columns = $this->writerColumns();
        $parts = explode(self::TEMPLATE_BREAKABLE_SPACE, $line);
        $current = array_shift($parts) ?? '';
        $wrapped = [];

        foreach ($parts as $part) {
            if ($current === '') {
                $current = $part;
                continue;
            }

            $candidate = $current . ' ' . $part;
            if ($part !== '' && $this->markdownDisplayWidth($candidate) > $columns) {
                $wrapped[] = $current;
                $current = $part;
                continue;
            }

            $current = $candidate;
        }

        $wrapped[] = $current;

        return implode("\n", $wrapped);
    }

    /**
     * @param array{indent:string, sourceColumn:int}|null $nesting
     */
    private function applyPlainTemplateNestingToValue(string $text, ?array $nesting, bool $stripSourceIndent = false): string
    {
        if ($nesting === null || $text === '' || !strpbrk($text, "\r\n")) {
            return $text;
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        foreach ($lines as $index => $line) {
            if ($line === '' || ($index === 0 && !$stripSourceIndent)) {
                continue;
            }

            if ($stripSourceIndent) {
                $line = $this->stripPlainTemplateSourceIndent($line, $nesting['sourceColumn']);
                if ($line === '') {
                    $lines[$index] = '';
                    continue;
                }
            }

            $lines[$index] = $nesting['indent'] . $line;
        }

        return implode("\n", $lines);
    }

    private function stripPlainTemplateSourceIndent(string $line, int $sourceColumn): string
    {
        if ($sourceColumn <= 0) {
            return $line;
        }

        $consumed = $this->plainTemplateAlignedIndentLength($line, 0, $sourceColumn);
        if ($consumed === null) {
            return $line;
        }

        return substr($line, $consumed);
    }

    /**
     * @param array{indent:string, sourceColumn:int}|null $nesting
     */
    private function applyPlainTemplateNestingToLiteral(string $text, ?array &$nesting): string
    {
        if ($nesting === null || $text === '' || !strpbrk($text, "\r\n")) {
            return $text;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $result = '';
        $offset = 0;
        $length = strlen($text);
        while (($newline = strpos($text, "\n", $offset)) !== false) {
            $result .= substr($text, $offset, $newline - $offset + 1);
            $lineOffset = $newline + 1;
            $lineEnd = strpos($text, "\n", $lineOffset);
            $lineEnd = $lineEnd === false ? $length : $lineEnd;
            $line = substr($text, $lineOffset, $lineEnd - $lineOffset);
            if ($line === '' || preg_match('/^[ \t]+$/', $line) === 1) {
                $offset = $lineEnd;
                continue;
            }

            $consumed = $this->plainTemplateAlignedIndentLength($text, $lineOffset, $nesting['sourceColumn']);
            if ($consumed === null) {
                $nesting = null;
                return $result . substr($text, $lineOffset);
            }

            $result .= $nesting['indent'];
            $offset = $lineOffset + $consumed;
            if ($offset > $length) {
                break;
            }
        }

        return $result . substr($text, $offset);
    }

    private function plainTemplateCurrentLineDisplayWidth(string $rendered): int
    {
        $rendered = str_replace(["\r\n", "\r", self::TEMPLATE_BREAKABLE_SPACE], ["\n", "\n", ' '], $rendered);
        $lineStart = strrpos($rendered, "\n");
        $line = $lineStart === false ? $rendered : substr($rendered, $lineStart + 1);

        return $this->markdownDisplayWidth($line);
    }

    private function plainTemplateSourceColumn(string $template, int $offset): int
    {
        $prefix = substr($template, 0, $offset);
        $lastLineFeed = strrpos($prefix, "\n");
        $lastCarriageReturn = strrpos($prefix, "\r");
        $lineStart = max($lastLineFeed === false ? -1 : $lastLineFeed, $lastCarriageReturn === false ? -1 : $lastCarriageReturn) + 1;

        return strlen(str_replace("\t", ' ', substr($template, $lineStart, $offset - $lineStart)));
    }

    private function plainTemplateAlignedIndentLength(string $text, int $offset, int $sourceColumn): ?int
    {
        $column = 0;
        $cursor = $offset;
        $length = strlen($text);
        while ($cursor < $length && $column < $sourceColumn) {
            $char = $text[$cursor];
            if ($char !== ' ' && $char !== "\t") {
                return null;
            }
            $column++;
            $cursor++;
        }

        return $column === $sourceColumn ? $cursor - $offset : null;
    }

    /**
     * @param array{kind:string, start:int, end:int, source:string, content:string} $token
     * @return array{indent:string, sourceColumn:int}|null
     */
    private function plainTemplateAutomaticNesting(string $template, array $token, string $rendered): ?array
    {
        $prefixStart = max(
            strrpos(substr($template, 0, $token['start']), "\n") === false ? -1 : (int) strrpos(substr($template, 0, $token['start']), "\n"),
            strrpos(substr($template, 0, $token['start']), "\r") === false ? -1 : (int) strrpos(substr($template, 0, $token['start']), "\r")
        ) + 1;
        $prefix = substr($template, $prefixStart, $token['start'] - $prefixStart);
        if ($prefix === '' || preg_match('/^[ \t]+$/', $prefix) !== 1) {
            return null;
        }

        $lineEnd = strcspn($template, "\r\n", $token['end']);
        $suffix = substr($template, $token['end'], $lineEnd);
        if (preg_match('/^[ \t]*$/', $suffix) !== 1) {
            return null;
        }

        return [
            'indent' => str_repeat(' ', $this->plainTemplateCurrentLineDisplayWidth($rendered)),
            'sourceColumn' => strlen(str_replace("\t", ' ', $prefix)),
        ];
    }

    /**
     * @param list<string> $pipes
     * @param array<string, mixed> $context
     */
    private function renderPlainTemplateConditional(
        string $name,
        string $body,
        array $context,
        int $partialDepth = 0,
        array $pipes = [],
        bool $multiline = false
    ): string
    {
        [$trueTemplate, $falseTemplate, $elseifExpression] = $this->splitPlainTemplateConditionalBody($body, $multiline);
        [$found, $value] = $this->plainTemplateContextLookup($context, $name);
        if ($found) {
            $value = $this->applyPlainTemplatePipes($value, $pipes);
        }

        if ($found && $this->plainTemplateValueIsTruthy($value)) {
            return $this->renderPlainTemplateString($trueTemplate, $context, $partialDepth);
        }

        if ($elseifExpression !== null) {
            return $this->renderPlainTemplateConditional(
                $elseifExpression['name'],
                $falseTemplate ?? '',
                $context,
                $partialDepth,
                $elseifExpression['pipes'],
                $elseifExpression['multiline']
            );
        }

        return $falseTemplate === null ? '' : $this->renderPlainTemplateString($falseTemplate, $context, $partialDepth);
    }

    /**
     * @param list<string> $pipes
     * @param array<string, mixed> $context
     */
    private function renderPlainTemplateLoop(string $name, string $body, array $context, int $partialDepth = 0, array $pipes = []): string
    {
        [$itemTemplate, $separatorTemplate] = $this->splitPlainTemplateLoopBody($body);
        [$found, $value] = $this->plainTemplateContextLookup($context, $name);
        if (!$found) {
            return '';
        }
        $value = $this->applyPlainTemplatePipes($value, $pipes);

        $items = $this->templateVariableValueList($value);
        $rendered = '';
        foreach ($items as $index => $item) {
            $loopContext = $context;
            $loopContext[$name] = $item;
            $loopContext['it'] = $item;
            $rendered .= $this->renderPlainTemplateString($itemTemplate, $loopContext, $partialDepth);
            if ($separatorTemplate !== null && $index < count($items) - 1) {
                $rendered .= $this->renderPlainTemplateString($separatorTemplate, $loopContext, $partialDepth);
            }
        }

        return $rendered;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderPlainTemplatePartial(string $name, array $context, int $partialDepth, bool $stripAllFinalLineBreaks = true): ?string
    {
        if ($partialDepth > self::MAX_TEMPLATE_PARTIAL_DEPTH) {
            return '(loop)';
        }

        $partial = $this->plainTemplatePartialSource($name);
        if ($partial === null) {
            return null;
        }

        if (in_array($name, $this->plainTemplatePartialStack, true)) {
            return '(loop)';
        }

        $this->plainTemplatePartialStack[] = $name;
        try {
            $rendered = $this->renderPlainTemplateString($partial, $context, $partialDepth + 1);

            return $stripAllFinalLineBreaks
                ? rtrim($rendered, "\r\n")
                : $this->plainTemplateRemoveFinalLineBreak($rendered);
        } finally {
            array_pop($this->plainTemplatePartialStack);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderPlainTemplateAppliedPartial(
        string $name,
        array $namePipes,
        string $partialName,
        ?string $separator,
        array $context,
        int $partialDepth
    ): ?string {
        $partial = $this->plainTemplatePartialSource($partialName);
        if ($partial === null) {
            return null;
        }

        [$found, $value] = $this->plainTemplateContextLookup($context, $name);
        if (!$found) {
            return '';
        }
        $value = $this->applyPlainTemplatePipes($value, $namePipes);

        $rendered = [];
        foreach ($this->templateVariableValueList($value) as $item) {
            $itemContext = $context;
            $itemContext[$name] = $item;
            $itemContext['it'] = $item;
            $rendered[] = $this->renderPlainTemplatePartial($partialName, $itemContext, $partialDepth, false) ?? '';
        }

        return implode($separator ?? '', $rendered);
    }

    private function plainTemplatePartialSource(string $name): ?string
    {
        $partials = $this->options['partials'] ?? [];
        if (!is_array($partials)) {
            return null;
        }

        $candidates = [$name];
        if (!str_contains($name, '.')) {
            $candidates[] = $name . '.txt';
            $candidates[] = $name . '.plain';
        }

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $partials) && is_string($partials[$candidate])) {
                return $partials[$candidate];
            }
        }

        return null;
    }

    /**
     * @return array{body:string, offset:int, multiline:bool}|null
     */
    private function extractPlainTemplateBlock(string $template, int $offset, string $kind): ?array
    {
        $stack = [$kind];
        $cursor = $offset;
        $openingLineBreakLength = $this->plainTemplateLineBreakLengthAt($template, $offset);
        $bodyOffset = $openingLineBreakLength > 0 ? $offset + $openingLineBreakLength : $offset;

        while (($token = $this->nextPlainTemplateToken($template, $cursor)) !== null) {
            $command = $token['kind'] === 'command' ? $this->parsePlainTemplateCommand($token['content']) : null;
            if ($command !== null && ($command['kind'] === 'if' || $command['kind'] === 'for')) {
                $stack[] = $command['kind'];
            } elseif ($command !== null && ($command['kind'] === 'endif' || $command['kind'] === 'endfor')) {
                $endKind = $command['kind'] === 'endif' ? 'if' : 'for';
                if (end($stack) === $endKind) {
                    array_pop($stack);
                    if ($stack === []) {
                        $nextOffset = $token['end'];
                        if ($openingLineBreakLength > 0) {
                            $closingLineBreakLength = $this->plainTemplateLineBreakLengthAt($template, $nextOffset);
                            if ($closingLineBreakLength > 0) {
                                $nextOffset += $closingLineBreakLength;
                            }
                        }

                        return [
                            'body' => substr($template, $bodyOffset, $token['start'] - $bodyOffset),
                            'offset' => $nextOffset,
                            'multiline' => $openingLineBreakLength > 0,
                        ];
                    }
                }
            }

            $cursor = $token['end'];
        }

        return null;
    }

    private function plainTemplateLineBreakLengthAt(string $template, int $offset): int
    {
        return match (substr($template, $offset, 2)) {
            "\r\n" => 2,
            default => in_array($template[$offset] ?? '', ["\n", "\r"], true) ? 1 : 0,
        };
    }

    private function plainTemplateRemoveFinalLineBreak(string $text): string
    {
        if (str_ends_with($text, "\r\n")) {
            return substr($text, 0, -2);
        }

        if (str_ends_with($text, "\n") || str_ends_with($text, "\r")) {
            return substr($text, 0, -1);
        }

        return $text;
    }

    /**
     * @return array{0:string, 1:?string}
     */
    private function splitPlainTemplateLoopBody(string $body): array
    {
        $cursor = 0;
        $depth = 0;
        while (($token = $this->nextPlainTemplateToken($body, $cursor)) !== null) {
            $command = $token['kind'] === 'command' ? $this->parsePlainTemplateCommand($token['content']) : null;
            if ($command === null) {
                $cursor = $token['end'];
                continue;
            }

            if ($depth === 0 && $command['kind'] === 'sep') {
                return [
                    substr($body, 0, $token['start']),
                    substr($body, $token['end']),
                ];
            }

            if ($command['kind'] === 'if' || $command['kind'] === 'for') {
                $depth++;
            } elseif (($command['kind'] === 'endif' || $command['kind'] === 'endfor') && $depth > 0) {
                $depth--;
            }

            $cursor = $token['end'];
        }

        return [$body, null];
    }

    /**
     * @return array{0:string, 1:?string, 2:?array{name:string, pipes:list<string>, multiline:bool}}
     */
    private function splitPlainTemplateConditionalBody(string $body, bool $multiline): array
    {
        $cursor = 0;
        $depth = 0;
        while (($token = $this->nextPlainTemplateToken($body, $cursor)) !== null) {
            $command = $token['kind'] === 'command' ? $this->parsePlainTemplateCommand($token['content']) : null;
            if ($command === null) {
                $cursor = $token['end'];
                continue;
            }

            if ($depth === 0 && $command['kind'] === 'else') {
                return [
                    substr($body, 0, $token['start']),
                    substr($body, $this->plainTemplateBranchBodyOffset($body, $token['end'], $multiline)),
                    null,
                ];
            }

            if ($depth === 0 && $command['kind'] === 'elseif') {
                $branchOffset = $this->plainTemplateBranchBodyOffset($body, $token['end'], true);

                return [
                    substr($body, 0, $token['start']),
                    substr($body, $branchOffset),
                    [
                        'name' => $command['name'],
                        'pipes' => $command['pipes'] ?? [],
                        'multiline' => $branchOffset > $token['end'],
                    ],
                ];
            }

            if ($command['kind'] === 'if' || $command['kind'] === 'for') {
                $depth++;
            } elseif (($command['kind'] === 'endif' || $command['kind'] === 'endfor') && $depth > 0) {
                $depth--;
            }

            $cursor = $token['end'];
        }

        return [$body, null, null];
    }

    private function plainTemplateBranchBodyOffset(string $template, int $offset, bool $swallowLineBreak): int
    {
        if (!$swallowLineBreak) {
            return $offset;
        }

        return $offset + $this->plainTemplateLineBreakLengthAt($template, $offset);
    }

    /**
     * @return array{kind:string, start:int, end:int, source:string, content:string}|null
     */
    private function nextPlainTemplateToken(string $template, int $offset): ?array
    {
        $length = strlen($template);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($template[$cursor] !== '$') {
                continue;
            }

            if (substr($template, $cursor, 3) === '$--') {
                $lineEnd = $length;
                foreach (["\n", "\r"] as $lineBreak) {
                    $candidate = strpos($template, $lineBreak, $cursor + 3);
                    if ($candidate !== false) {
                        $lineEnd = min($lineEnd, $candidate);
                    }
                }

                return [
                    'kind' => 'comment',
                    'start' => $cursor,
                    'end' => $lineEnd,
                    'source' => substr($template, $cursor, $lineEnd - $cursor),
                    'content' => '',
                ];
            }

            if (substr($template, $cursor, 2) === '$$') {
                return [
                    'kind' => 'literal',
                    'start' => $cursor,
                    'end' => $cursor + 2,
                    'source' => '$$',
                    'content' => '',
                ];
            }

            if (substr($template, $cursor, 2) === '${') {
                $end = strpos($template, '}', $cursor + 2);
                if ($end === false) {
                    continue;
                }

                return [
                    'kind' => 'command',
                    'start' => $cursor,
                    'end' => $end + 1,
                    'source' => substr($template, $cursor, $end + 1 - $cursor),
                    'content' => substr($template, $cursor + 2, $end - $cursor - 2),
                ];
            }

            $end = strpos($template, '$', $cursor + 1);
            if ($end === false) {
                continue;
            }

            return [
                'kind' => 'command',
                'start' => $cursor,
                'end' => $end + 1,
                'source' => substr($template, $cursor, $end + 1 - $cursor),
                'content' => substr($template, $cursor + 1, $end - $cursor - 1),
            ];
        }

        return null;
    }

    /**
     * @return array{kind:string, name?:string, namePipes?:list<string>, partial?:string, separator?:string|null, pipes?:list<string>}|null
     */
    private function parsePlainTemplateCommand(string $content): ?array
    {
        $content = trim($content);
        $variableName = '[A-Za-z][A-Za-z0-9_.-]*';
        $reserved = ['if', 'else', 'elseif', 'endif', 'for', 'sep', 'endfor'];
        if ($content === '~') {
            return ['kind' => 'breakable_space'];
        }
        if ($content === '^') {
            return ['kind' => 'nest'];
        }
        if (preg_match('/^if\\(\\s*(.*?)\\s*\\)$/s', $content, $match) === 1) {
            $expression = $this->parsePlainTemplateExpression($match[1]);

            return $expression === null ? null : [
                'kind' => 'if',
                'name' => $expression['name'],
                'pipes' => $expression['pipes'],
            ];
        }
        if (preg_match('/^elseif\\(\\s*(.*?)\\s*\\)$/s', $content, $match) === 1) {
            $expression = $this->parsePlainTemplateExpression($match[1]);

            return $expression === null ? null : [
                'kind' => 'elseif',
                'name' => $expression['name'],
                'pipes' => $expression['pipes'],
            ];
        }
        if (preg_match('/^for\\(\\s*(.*?)\\s*\\)$/s', $content, $match) === 1) {
            $expression = $this->parsePlainTemplateExpression($match[1]);

            return $expression === null ? null : [
                'kind' => 'for',
                'name' => $expression['name'],
                'pipes' => $expression['pipes'],
            ];
        }
        if (in_array($content, ['else', 'endif', 'sep', 'endfor'], true)) {
            return ['kind' => $content];
        }

        [$body, $separatorValue] = $this->plainTemplateSplitTrailingSeparator($content);
        if (preg_match('/^(.*?)\\s*:\\s*(' . $variableName . ')\\(\\)(.*)$/s', $body, $match) === 1) {
            $expression = $this->parsePlainTemplateExpression($match[1]);
            $pipes = $this->plainTemplateParsePipeSuffix($match[3]);
            if ($expression === null || $pipes === null) {
                return null;
            }

            return [
                'kind' => 'applied_partial',
                'name' => $expression['name'],
                'namePipes' => $expression['pipes'],
                'partial' => $match[2],
                'separator' => $separatorValue,
                'pipes' => $pipes,
            ];
        }
        if (
            preg_match('/^(' . $variableName . ')\\(\\)(.*)$/s', $body, $match) === 1
            && !in_array($match[1], $reserved, true)
        ) {
            $pipes = $this->plainTemplateParsePipeSuffix($match[2]);
            if ($pipes === null) {
                return null;
            }

            return [
                'kind' => 'partial',
                'partial' => $match[1],
                'separator' => $separatorValue,
                'pipes' => $pipes,
            ];
        }
        $expression = $this->parsePlainTemplateExpression($body);
        if ($expression !== null && !in_array($expression['name'], $reserved, true)) {

            return [
                'kind' => 'variable',
                'name' => $expression['name'],
                'separator' => $separatorValue,
                'pipes' => $expression['pipes'],
            ];
        }

        return null;
    }

    /**
     * @return array{name:string, pipes:list<string>}|null
     */
    private function parsePlainTemplateExpression(string $expression): ?array
    {
        $parts = $this->plainTemplateSplitPipeExpression(trim($expression));
        if ($parts === null || $parts === [] || $parts[0] === '') {
            return null;
        }

        $variableName = '/^[A-Za-z][A-Za-z0-9_.-]*$/';
        if (preg_match($variableName, $parts[0]) !== 1) {
            return null;
        }

        $pipes = [];
        foreach (array_slice($parts, 1) as $pipe) {
            if (!$this->plainTemplatePipeIsValid($pipe)) {
                return null;
            }
            $pipes[] = $pipe;
        }

        return [
            'name' => $parts[0],
            'pipes' => $pipes,
        ];
    }

    /**
     * @return list<string>|null
     */
    private function plainTemplateParsePipeSuffix(string $suffix): ?array
    {
        $suffix = trim($suffix);
        if ($suffix === '') {
            return [];
        }

        if (!str_starts_with($suffix, '/')) {
            return null;
        }

        $parts = $this->plainTemplateSplitPipeExpression($suffix);
        if ($parts === null || $parts === [] || $parts[0] !== '') {
            return null;
        }

        $pipes = [];
        foreach (array_slice($parts, 1) as $pipe) {
            if ($pipe === '') {
                return null;
            }
            if (!$this->plainTemplatePipeIsValid($pipe)) {
                return null;
            }
            $pipes[] = $pipe;
        }

        return $pipes;
    }

    /**
     * @return array{0:string, 1:?string}
     */
    private function plainTemplateSplitTrailingSeparator(string $content): array
    {
        $content = rtrim($content);
        if ($content === '' || !str_ends_with($content, ']')) {
            return [$content, null];
        }

        $start = $this->plainTemplateTrailingSeparatorStart($content);
        if ($start === null) {
            return [$content, null];
        }

        return [
            rtrim(substr($content, 0, $start)),
            substr($content, $start + 1, strlen($content) - $start - 2),
        ];
    }

    private function plainTemplateTrailingSeparatorStart(string $content): ?int
    {
        $inQuote = false;
        $escaped = false;
        $start = null;
        $length = strlen($content);
        for ($index = 0; $index < $length; $index++) {
            $char = $content[$index];
            if ($inQuote) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === '"') {
                    $inQuote = false;
                }
                continue;
            }

            if ($char === '"') {
                $inQuote = true;
                continue;
            }

            if ($char === '[') {
                $start = $index;
            }
        }

        return $inQuote ? null : $start;
    }

    /**
     * @return list<string>|null
     */
    private function plainTemplateSplitPipeExpression(string $expression): ?array
    {
        $parts = $this->plainTemplateSplitPipeExpressionParts($expression);
        if ($parts === null) {
            return null;
        }

        return array_map(static fn (array $part): string => $part['value'], $parts);
    }

    /**
     * @return list<array{value:string, start:int}>|null
     */
    private function plainTemplateSplitPipeExpressionParts(string $expression): ?array
    {
        $parts = [];
        $start = 0;
        $inQuote = false;
        $escaped = false;
        $length = strlen($expression);
        for ($index = 0; $index < $length; $index++) {
            $char = $expression[$index];
            if ($inQuote) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === '"') {
                    $inQuote = false;
                }
                continue;
            }

            if ($char === '"') {
                $inQuote = true;
                continue;
            }

            if ($char === '/') {
                $raw = substr($expression, $start, $index - $start);
                $parts[] = [
                    'value' => trim($raw),
                    'start' => $start + strlen($raw) - strlen(ltrim($raw)),
                ];
                $start = $index + 1;
            }
        }

        if ($inQuote) {
            return null;
        }

        $raw = substr($expression, $start);
        $parts[] = [
            'value' => trim($raw),
            'start' => $start + strlen($raw) - strlen(ltrim($raw)),
        ];

        return $parts;
    }

    private function plainTemplatePipeIsValid(string $pipe): bool
    {
        return $this->plainTemplatePipeSpec($pipe) !== null;
    }

    /**
     * @return array{name:string, args:array<string, mixed>}|null
     */
    private function plainTemplatePipeSpec(string $pipe): ?array
    {
        $pipe = trim($pipe);
        if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)(?:\\s+(.*))?$/s', $pipe, $match) !== 1) {
            return null;
        }

        $name = $match[1];
        $argsSource = trim($match[2] ?? '');
        if (!in_array($name, $this->plainTemplateKnownPipeNames(), true)) {
            return null;
        }

        if ($argsSource === '') {
            if (in_array($name, ['left', 'right', 'center'], true)) {
                return null;
            }

            return [
                'name' => $name,
                'args' => [],
            ];
        }

        if (!in_array($name, ['left', 'right', 'center'], true)) {
            return null;
        }

        $args = $this->plainTemplateAlignmentPipeArguments($argsSource);
        if ($args === null) {
            return null;
        }

        return [
            'name' => $name,
            'args' => $args,
        ];
    }

    /**
     * @return list<string>
     */
    private function plainTemplateKnownPipeNames(): array
    {
        return [
            'pairs',
            'uppercase',
            'lowercase',
            'length',
            'alpha',
            'roman',
            'reverse',
            'first',
            'rest',
            'last',
            'allbutlast',
            'chomp',
            'nowrap',
            'left',
            'right',
            'center',
        ];
    }

    /**
     * @return array{width:int, leftBorder:string, rightBorder:string}|null
     */
    private function plainTemplateAlignmentPipeArguments(string $source): ?array
    {
        $tokens = $this->plainTemplatePipeArgumentTokens($source);
        if ($tokens === null || count($tokens) < 1 || count($tokens) > 3) {
            return null;
        }

        $width = $tokens[0];
        if ($width['quoted'] || !ctype_digit($width['value']) || (int) $width['value'] <= 0) {
            return null;
        }

        $leftBorder = '';
        $rightBorder = '';
        if (isset($tokens[1])) {
            if (!$tokens[1]['quoted']) {
                return null;
            }
            $leftBorder = $tokens[1]['value'];
        }
        if (isset($tokens[2])) {
            if (!$tokens[2]['quoted']) {
                return null;
            }
            $rightBorder = $tokens[2]['value'];
        }

        return [
            'width' => (int) $width['value'],
            'leftBorder' => $leftBorder,
            'rightBorder' => $rightBorder,
        ];
    }

    /**
     * @return list<array{value:string, quoted:bool}>|null
     */
    private function plainTemplatePipeArgumentTokens(string $source): ?array
    {
        $tokens = [];
        $length = strlen($source);
        $index = 0;
        while ($index < $length) {
            while ($index < $length && ctype_space($source[$index])) {
                $index++;
            }
            if ($index >= $length) {
                break;
            }

            if ($source[$index] === '"') {
                $index++;
                $value = '';
                $closed = false;
                while ($index < $length) {
                    $char = $source[$index];
                    if ($char === '\\') {
                        $index++;
                        if ($index >= $length) {
                            return null;
                        }
                        $value .= $source[$index];
                        $index++;
                        continue;
                    }
                    if ($char === '"') {
                        $closed = true;
                        $index++;
                        break;
                    }
                    $value .= $char;
                    $index++;
                }

                if (!$closed) {
                    return null;
                }

                if ($index < $length && !ctype_space($source[$index])) {
                    return null;
                }

                $tokens[] = [
                    'value' => $value,
                    'quoted' => true,
                ];
                continue;
            }

            $start = $index;
            while ($index < $length && !ctype_space($source[$index])) {
                if ($source[$index] === '"') {
                    return null;
                }
                $index++;
            }

            $tokens[] = [
                'value' => substr($source, $start, $index - $start),
                'quoted' => false,
            ];
        }

        return $tokens;
    }

    private function renderPlainTemplateInterpolatedValue(mixed $value, ?string $separator): mixed
    {
        if ($separator === null) {
            return $this->renderPlainTemplateVariableValue($value);
        }

        $rendered = [];
        foreach ($this->templateVariableValueList($value) as $item) {
            $rendered[] = $this->plainTemplateRenderableToString($this->renderPlainTemplateVariableValue($item));
        }

        return implode($separator, $rendered);
    }

    /**
     * @param list<string> $pipes
     */
    private function applyPlainTemplatePipes(mixed $value, array $pipes): mixed
    {
        foreach ($pipes as $pipe) {
            $spec = $this->plainTemplatePipeSpec($pipe);
            if ($spec === null) {
                continue;
            }

            $value = match ($spec['name']) {
                'pairs' => $this->plainTemplatePipePairs($value),
                'uppercase' => $this->plainTemplatePipeText($value, fn (string $text): string => mb_strtoupper($text, 'UTF-8')),
                'lowercase' => $this->plainTemplatePipeText($value, fn (string $text): string => mb_strtolower($text, 'UTF-8')),
                'length' => $this->plainTemplatePipeLength($value),
                'reverse' => $this->plainTemplatePipeReverse($value),
                'first' => $this->plainTemplatePipeFirst($value),
                'last' => $this->plainTemplatePipeLast($value),
                'rest' => $this->plainTemplatePipeRest($value),
                'allbutlast' => $this->plainTemplatePipeAllButLast($value),
                'chomp' => $this->plainTemplatePipeChomp($value),
                'nowrap' => $this->plainTemplatePipeNowrap($value),
                'alpha' => $this->plainTemplatePipeAlpha($value),
                'roman' => $this->plainTemplatePipeRoman($value),
                'left' => $this->plainTemplatePipeAlign($value, 'left', $spec['args']),
                'right' => $this->plainTemplatePipeAlign($value, 'right', $spec['args']),
                'center' => $this->plainTemplatePipeAlign($value, 'center', $spec['args']),
                default => $value,
            };
        }

        return $value;
    }

    private function plainTemplatePipeChomp(mixed $value): mixed
    {
        if ($value instanceof AstNode) {
            return rtrim($this->renderPlainTemplateAstValue($value), "\r\n" . self::TEMPLATE_BREAKABLE_SPACE);
        }

        if (is_array($value) && !$this->arrayIsAstNodeList($value)) {
            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[$key] = $this->plainTemplatePipeChomp($item);
            }

            return $mapped;
        }

        return is_string($value) ? rtrim($value, "\r\n" . self::TEMPLATE_BREAKABLE_SPACE) : $value;
    }

    /**
     * @param list<string> $pipes
     */
    private function plainTemplatePipesContainAlignment(array $pipes): bool
    {
        foreach ($pipes as $pipe) {
            $spec = $this->plainTemplatePipeSpec($pipe);
            if ($spec !== null && in_array($spec['name'], ['left', 'right', 'center'], true)) {
                return true;
            }
        }

        return false;
    }

    private function plainTemplatePipeNowrap(mixed $value): mixed
    {
        if ($value instanceof AstNode) {
            return str_replace(self::TEMPLATE_BREAKABLE_SPACE, ' ', $this->renderPlainTemplateAstValue($value));
        }

        if (is_array($value)) {
            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[$key] = $this->plainTemplatePipeNowrap($item);
            }

            return $mapped;
        }

        if (!is_scalar($value) || is_bool($value)) {
            return $value;
        }

        return str_replace(self::TEMPLATE_BREAKABLE_SPACE, ' ', (string) $value);
    }

    private function plainTemplatePipeText(mixed $value, callable $transform): mixed
    {
        if ($value instanceof AstNode) {
            return $transform($this->renderPlainTemplateAstValue($value));
        }

        if (is_array($value)) {
            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[$key] = $this->plainTemplatePipeText($item, $transform);
            }

            return $mapped;
        }

        if ($value === null || is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return $transform((string) $value);
        }

        return $value;
    }

    private function plainTemplatePipeLength(mixed $value): int
    {
        if ($value instanceof AstNode) {
            return mb_strlen($this->renderPlainTemplateAstValue($value), 'UTF-8');
        }

        if (is_array($value)) {
            return $this->arrayIsAstNodeList($value)
                ? mb_strlen($this->renderPlainTemplateAstNodes($value), 'UTF-8')
                : count($value);
        }

        if ($value === null || is_bool($value)) {
            return 0;
        }

        return mb_strlen((string) $value, 'UTF-8');
    }

    /**
     * @return list<array{key:int|string, value:mixed}>
     */
    private function plainTemplatePipePairs(mixed $value): array
    {
        if (!is_array($value) || $this->arrayIsAstNodeList($value)) {
            return [];
        }

        $pairs = [];
        foreach ($value as $key => $item) {
            $pairs[] = [
                'key' => array_is_list($value) ? count($pairs) + 1 : $key,
                'value' => $item,
            ];
        }

        return $pairs;
    }

    private function plainTemplatePipeReverse(mixed $value): mixed
    {
        if (is_string($value)) {
            $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

            return $characters === false ? strrev($value) : implode('', array_reverse($characters));
        }

        if (is_array($value) && !$this->arrayIsAstNodeList($value) && array_is_list($value)) {
            return array_reverse($value);
        }

        return $value;
    }

    private function plainTemplatePipeFirst(mixed $value): mixed
    {
        if (is_array($value) && !$this->arrayIsAstNodeList($value) && array_is_list($value) && $value !== []) {
            return $value[0];
        }

        return $value;
    }

    private function plainTemplatePipeLast(mixed $value): mixed
    {
        if (is_array($value) && !$this->arrayIsAstNodeList($value) && array_is_list($value) && $value !== []) {
            return $value[array_key_last($value)];
        }

        return $value;
    }

    private function plainTemplatePipeRest(mixed $value): mixed
    {
        if (is_array($value) && !$this->arrayIsAstNodeList($value) && array_is_list($value) && $value !== []) {
            return array_slice($value, 1);
        }

        return $value;
    }

    private function plainTemplatePipeAllButLast(mixed $value): mixed
    {
        if (is_array($value) && !$this->arrayIsAstNodeList($value) && array_is_list($value) && $value !== []) {
            return array_slice($value, 0, -1);
        }

        return $value;
    }

    private function plainTemplatePipeAlpha(mixed $value): mixed
    {
        if (is_array($value) && !$this->arrayIsAstNodeList($value)) {
            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[$key] = $this->plainTemplatePipeAlpha($item);
            }

            return $mapped;
        }

        if ($value instanceof AstNode) {
            $value = $this->renderPlainTemplateAstValue($value);
        }

        if (!is_scalar($value) || is_bool($value)) {
            return $value;
        }

        $number = (int) trim((string) $value);
        if ((string) $number !== trim((string) $value) || $number <= 0) {
            return $value;
        }

        $letter = chr(ord('a') + (($number - 1) % 26));

        return $letter;
    }

    private function plainTemplatePipeRoman(mixed $value): mixed
    {
        if (is_array($value) && !$this->arrayIsAstNodeList($value)) {
            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[$key] = $this->plainTemplatePipeRoman($item);
            }

            return $mapped;
        }

        if ($value instanceof AstNode) {
            $value = $this->renderPlainTemplateAstValue($value);
        }

        if (!is_scalar($value) || is_bool($value)) {
            return $value;
        }

        $number = (int) trim((string) $value);
        if ((string) $number !== trim((string) $value) || $number <= 0) {
            return $value;
        }

        $roman = '';
        foreach ([
            1000 => 'm',
            900 => 'cm',
            500 => 'd',
            400 => 'cd',
            100 => 'c',
            90 => 'xc',
            50 => 'l',
            40 => 'xl',
            10 => 'x',
            9 => 'ix',
            5 => 'v',
            4 => 'iv',
            1 => 'i',
        ] as $unit => $symbol) {
            while ($number >= $unit) {
                $roman .= $symbol;
                $number -= $unit;
            }
        }

        return $roman;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function plainTemplatePipeAlign(mixed $value, string $alignment, array $args): mixed
    {
        $width = $args['width'] ?? null;
        if (!is_int($width) || $width <= 0) {
            return $value;
        }

        $leftBorder = (string) ($args['leftBorder'] ?? '');
        $rightBorder = (string) ($args['rightBorder'] ?? '');

        if ($value instanceof AstNode) {
            return $this->plainTemplateAlignText(
                $this->renderPlainTemplateAstValue($value),
                $width,
                $alignment,
                $leftBorder,
                $rightBorder
            );
        }

        if (is_array($value) && $this->arrayIsAstNodeList($value)) {
            return $this->plainTemplateAlignText(
                $this->renderPlainTemplateAstNodes($value),
                $width,
                $alignment,
                $leftBorder,
                $rightBorder
            );
        }

        if ($value === null) {
            return $this->plainTemplateAlignText('', $width, $alignment, $leftBorder, $rightBorder);
        }

        if (!is_scalar($value) || is_bool($value)) {
            return $value;
        }

        return $this->plainTemplateAlignText((string) $value, $width, $alignment, $leftBorder, $rightBorder);
    }

    private function plainTemplateAlignText(
        string $text,
        int $width,
        string $alignment,
        string $leftBorder,
        string $rightBorder
    ): array {
        $rawLines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        $lines = [];
        foreach ($rawLines as $line) {
            $padding = max(0, $width - $this->markdownDisplayWidth($line));
            $leftPadding = 0;
            $rightPadding = 0;

            if ($alignment === 'left') {
                $rightPadding = $padding;
            } elseif ($alignment === 'right') {
                $leftPadding = $padding;
            } else {
                $leftPadding = intdiv($padding, 2);
                $rightPadding = $padding - $leftPadding;
            }

            $lines[] = $leftBorder
                . str_repeat(' ', $leftPadding)
                . $line
                . str_repeat(' ', $rightPadding)
                . $rightBorder;
        }

        return $this->plainTemplateBlock($lines, $leftBorder . str_repeat(' ', $width) . $rightBorder);
    }

    private function plainTemplateRenderableToString(mixed $value): string
    {
        if ($this->plainTemplateIsBlock($value)) {
            return implode("\n", $value['lines']);
        }

        return (string) $value;
    }

    private function plainTemplateValueIsTruthy(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }

        if (is_array($value)) {
            if ($value === []) {
                return false;
            }

            if (!$this->arrayIsAstNodeList($value) && !array_is_list($value)) {
                return true;
            }

            foreach ($this->templateVariableValueList($value) as $item) {
                if ($this->plainTemplateValueIsTruthy($item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_string($value)) {
            return $value !== '';
        }

        return true;
    }

    private function plainTemplateMetaJson(mixed $meta): string
    {
        $json = json_encode(
            is_array($meta) ? $this->plainTemplateMetaJsonValue($meta) : new \stdClass(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($json) ? $json : '{}';
    }

    private function plainTemplateMetaJsonValue(mixed $value): mixed
    {
        if ($value instanceof AstNode) {
            return $this->renderPlainTemplateAstValue($value);
        }

        if (is_array($value)) {
            if ($this->arrayIsAstNodeList($value)) {
                return $this->renderPlainTemplateAstNodes($value);
            }

            if (array_is_list($value)) {
                return array_map(fn (mixed $item): mixed => $this->plainTemplateMetaJsonValue($item), $value);
            }

            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[(string) $key] = $this->plainTemplateMetaJsonValue($item);
            }

            return $mapped;
        }

        return $value;
    }

    private function renderPlainTemplateContextOverride(AstNode $document, string $name): ?string
    {
        [$found, $value] = $this->plainTemplateContextValue($document, $name);
        if (!$found) {
            return null;
        }

        $blocks = [];
        foreach ($this->templateVariableValueList($value) as $item) {
            $rendered = $this->renderPlainTemplateVariableValue($item);
            if ($rendered !== '') {
                $blocks[] = $rendered;
            }
        }

        return implode("\n\n", $blocks);
    }

    private function renderPlainTemplateTableOfContents(AstNode $document): string
    {
        if (
            !$this->isPlainTextVariant()
            || !$this->templateEnabled()
            || !$this->plainTemplateTableOfContentsEnabled()
        ) {
            return '';
        }

        $toc = $this->buildPlainTemplateTableOfContents($document->children);
        if (!$toc instanceof AstNode || $toc->children === []) {
            return '';
        }

        $lines = $this->renderBlock($toc, 0);

        return $lines === [] ? '' : implode("\n", $lines);
    }

    private function renderStandaloneTableOfContents(AstNode $document): string
    {
        if ($this->isPlainTextVariant()) {
            return $this->renderPlainTemplateTableOfContents($document);
        }

        if (
            !$this->templateEnabled()
            || !$this->plainTemplateTableOfContentsEnabled()
        ) {
            return '';
        }

        $toc = $this->buildPlainTemplateTableOfContents($document->children);
        if (!$toc instanceof AstNode || $toc->children === []) {
            return '';
        }

        $lines = $this->renderBlock($toc, 0);

        return $lines === [] ? '' : implode("\n", $lines);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function buildPlainTemplateTableOfContents(array $blocks): ?AstNode
    {
        $headings = $this->plainTemplateTocCandidateHeadings($blocks);
        if ($headings === []) {
            return null;
        }

        $entries = [];
        $counters = [];
        $minimumLevel = min(array_map(
            static fn (AstNode $heading): int => max(1, min(6, (int) $heading->attr('level', 1))),
            $headings
        ));
        $tocDepth = $this->plainTemplateTocDepth();
        $numberSections = $this->plainTemplateNumberSectionsEnabled();
        $tocAutoIdUses = [];

        foreach ($headings as $heading) {
            $level = max(1, min(6, (int) $heading->attr('level', 1)));
            $classes = $this->normalizedStringList($heading->attr('classes', []));
            $explicitNumber = $this->plainTemplateHeadingExplicitNumber($heading);
            $number = '';
            if ($numberSections) {
                $number = $this->plainTemplateHeadingSectionNumber($heading, $level, $minimumLevel, $counters);
            }
            $hasSectionNumber = $number !== '' || (!$numberSections && $explicitNumber !== '');

            if ($level > $tocDepth) {
                continue;
            }

            if (!$hasSectionNumber && in_array('unlisted', $classes, true)) {
                continue;
            }

            $entries[] = [
                'level' => $level,
                'id' => $this->plainTemplateTocHeadingIdentifier($heading, $tocAutoIdUses),
                'number' => $number,
                'inlines' => $this->plainTemplateTocHeadingInlines($heading),
            ];
        }

        if ($entries === []) {
            return null;
        }

        $index = 0;
        $items = $this->plainTemplateTocItemsFromEntries($entries, $index, 0);

        return $items === [] ? null : new AstNode('bullet_list', [], $items);
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function plainTemplateTocCandidateHeadings(array $blocks): array
    {
        $headings = [];
        foreach ($blocks as $block) {
            if ($block->type === 'heading') {
                $headings[] = $block;
                continue;
            }

            if ($block->type === 'div') {
                array_push($headings, ...$this->plainTemplateTocDivHeadings($block));
            }
        }

        return $headings;
    }

    /**
     * @return list<AstNode>
     */
    private function plainTemplateTocDivHeadings(AstNode $div): array
    {
        $headings = $this->plainTemplateTocCandidateHeadings($div->children);
        if ($headings === []) {
            return [];
        }

        $levels = array_map(
            static fn (AstNode $heading): int => max(1, min(6, (int) $heading->attr('level', 1))),
            $headings
        );
        $minimumLevel = min($levels);
        $minimumLevelCount = count(array_filter(
            $levels,
            static fn (int $level): bool => $level === $minimumLevel
        ));

        return $minimumLevelCount === 1 ? $headings : [];
    }

    /**
     * @param array<string, int> $uses
     */
    private function plainTemplateTocHeadingIdentifier(AstNode $heading, array &$uses): string
    {
        $explicitId = (string) $heading->attr('id', '');
        if ($explicitId !== '') {
            return $explicitId;
        }

        $text = $heading->children === []
            ? (string) $heading->attr('text', '')
            : $this->plainInlineText($heading->children);
        $base = $this->autoIdentifier($text);
        $base = $base === '' ? 'section' : $base;
        $count = $uses[$base] ?? 0;
        $uses[$base] = $count + 1;

        return $count === 0 ? $base : $base . '-' . $count;
    }

    /**
     * @param array<int, int> $counters
     */
    private function plainTemplateHeadingSectionNumber(AstNode $heading, int $level, int $minimumLevel, array &$counters): string
    {
        $classes = $this->normalizedStringList($heading->attr('classes', []));
        $explicitNumber = $this->plainTemplateHeadingExplicitNumber($heading);

        if (!in_array('unnumbered', $classes, true)) {
            for ($cursor = $minimumLevel; $cursor <= $level; $cursor++) {
                $counters[$cursor] ??= 0;
            }
            $counters[$level]++;
            foreach (array_keys($counters) as $cursor) {
                if ($cursor > $level) {
                    unset($counters[$cursor]);
                }
            }
        }

        if ($explicitNumber !== '') {
            return $explicitNumber;
        }

        if (in_array('unnumbered', $classes, true)) {
            return '';
        }

        $parts = [];
        for ($cursor = $minimumLevel; $cursor <= $level; $cursor++) {
            $parts[] = (string) ($counters[$cursor] ?? 0);
        }

        return implode('.', $parts);
    }

    private function plainTemplateHeadingExplicitNumber(AstNode $heading): string
    {
        $explicitNumber = (string) $heading->attr('number', '');
        $attributes = $heading->attr('attributes', []);
        if ($explicitNumber === '' && is_array($attributes) && isset($attributes['number'])) {
            $explicitNumber = (string) $attributes['number'];
        }

        return $explicitNumber;
    }

    /**
     * @param list<array{level:int, id:string, number:string, inlines:list<AstNode>}> $entries
     * @return list<AstNode>
     */
    private function plainTemplateTocItemsFromEntries(array $entries, int &$index, int $parentLevel): array
    {
        $items = [];
        $count = count($entries);
        while ($index < $count) {
            $entry = $entries[$index];
            if ($entry['level'] <= $parentLevel) {
                break;
            }

            $index++;
            $children = $this->plainTemplateTocItemsFromEntries($entries, $index, $entry['level']);
            $items[] = $this->plainTemplateTocListItem($entry, $children);
        }

        return $items;
    }

    /**
     * @param array{level:int, id:string, number:string, inlines:list<AstNode>} $entry
     * @param list<AstNode> $children
     */
    private function plainTemplateTocListItem(array $entry, array $children): AstNode
    {
        $label = $entry['inlines'];
        if ($entry['number'] !== '') {
            array_unshift($label, new AstNode('text', ['text' => $entry['number'] . ' ']));
        }

        $id = $entry['id'];
        $itemChildren = [
            new AstNode('link', [
                'id' => $id === '' ? '' : 'toc-' . $id,
                'url' => $id === '' ? '' : '#' . $id,
            ], $label),
        ];

        if ($children !== []) {
            $itemChildren[] = new AstNode('bullet_list', [], $children);
        }

        return new AstNode('list_item', [], $itemChildren);
    }

    /**
     * @return list<AstNode>
     */
    private function plainTemplateTocHeadingInlines(AstNode $heading): array
    {
        $inlines = $heading->children;
        if ($inlines === []) {
            $text = (string) $heading->attr('text', '');
            $inlines = $text === '' ? [] : [new AstNode('text', ['text' => $text])];
        }

        return $this->plainTemplateCleanTocInlines($inlines);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function plainTemplateCleanTocInlines(array $nodes): array
    {
        $cleaned = [];
        foreach ($nodes as $node) {
            if ($node->type === 'note') {
                continue;
            }

            if ($node->type === 'link') {
                array_push($cleaned, ...$this->plainTemplateCleanTocInlines($node->children));
                continue;
            }

            $cleaned[] = new AstNode(
                $node->type,
                $node->attrs,
                $this->plainTemplateCleanTocInlines($node->children)
            );
        }

        return $cleaned;
    }

    /**
     * @return list<string>
     */
    private function normalizedStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => (string) $item, $value),
            static fn (string $item): bool => $item !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private function renderPlainTemplateVariableBlocks(AstNode $document, string $name): array
    {
        if (!$this->isPlainTextVariant() || !$this->templateEnabled()) {
            return [];
        }

        $blocks = [];
        foreach ($this->plainTemplateVariableValues($document, $name) as $value) {
            $rendered = $this->plainTemplateRenderableToString($this->renderPlainTemplateVariableValue($value));
            if ($rendered !== '') {
                $blocks[] = $rendered;
            }
        }

        return $blocks;
    }

    /**
     * @return list<mixed>
     */
    private function plainTemplateVariableValues(AstNode $document, string $name): array
    {
        [$found, $value] = $this->plainTemplateContextValue($document, $name);

        return $found ? $this->templateVariableValueList($value) : [];
    }

    /**
     * @return array{0:bool, 1:mixed}
     */
    private function plainTemplateContextValue(AstNode $document, string $name): array
    {
        $camelName = $this->templateVariableCamelName($name);
        $variables = $this->options['variables'] ?? [];
        if (is_array($variables)) {
            foreach ([$name, $camelName] as $key) {
                if (array_key_exists($key, $variables)) {
                    return [true, $variables[$key]];
                }
            }
        }

        foreach ([$name, $camelName] as $key) {
            if (array_key_exists($key, $this->options)) {
                return [true, $this->options[$key]];
            }
        }

        $meta = $document->attr('meta', []);
        if (is_array($meta)) {
            foreach ([$name, $camelName] as $key) {
                if (array_key_exists($key, $meta)) {
                    return [true, $meta[$key]];
                }
            }
        }

        return [false, null];
    }

    private function templateVariableCamelName(string $name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $name))));
    }

    /**
     * @return list<mixed>
     */
    private function templateVariableValueList(mixed $value): array
    {
        if ($value === null || $value === false) {
            return [];
        }

        if (is_array($value) && !$this->arrayIsAstNodeList($value)) {
            return array_is_list($value) ? $value : [$value];
        }

        return [$value];
    }

    private function renderPlainTemplateVariableValue(mixed $value): mixed
    {
        if ($value instanceof AstNode) {
            return $this->renderPlainTemplateAstValue($value);
        }

        if ($this->plainTemplateIsBlock($value)) {
            return $value;
        }

        if (is_array($value)) {
            if ($this->arrayIsAstNodeList($value)) {
                return $this->renderPlainTemplateAstNodes($value);
            }

            if (!array_is_list($value)) {
                return 'true';
            }

            $parts = [];
            foreach ($value as $item) {
                $rendered = $this->renderPlainTemplateVariableValue($item);
                if (!$this->plainTemplateRenderableIsEmpty($rendered)) {
                    $parts[] = $this->plainTemplateRenderableToString($rendered);
                }
            }

            return implode('', $parts);
        }

        if (is_scalar($value)) {
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            return (string) $value;
        }

        return '';
    }

    private function renderPlainTemplateAstValue(AstNode $node): string
    {
        if ($node->type === 'document') {
            return $this->renderBlockCollection($node->children);
        }

        if ($this->isInlineNode($node)) {
            return $this->renderPlainInlines([$node]);
        }

        return $this->renderBlockCollection([$node]);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderPlainTemplateAstNodes(array $nodes): string
    {
        foreach ($nodes as $node) {
            if (!$this->isInlineNode($node)) {
                return $this->renderBlockCollection($nodes);
            }
        }

        return $this->renderPlainInlines($nodes);
    }

    /**
     * @param array<mixed> $value
     */
    private function arrayIsAstNodeList(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function renderPlainMetaAuthors(array $meta): array
    {
        $authorInlines = $meta['authorInlines'] ?? null;
        if (is_array($authorInlines)) {
            $authors = [];
            foreach ($authorInlines as $author) {
                if (is_array($author)) {
                    $rendered = $this->normalizePlainMetaLine($this->renderPlainInlines($author));
                    if ($rendered !== '') {
                        $authors[] = $rendered;
                    }
                }
            }
            if ($authors !== []) {
                return $authors;
            }
        }

        $authorStrings = $meta['author'] ?? $meta['authors'] ?? [];
        if (!is_array($authorStrings)) {
            return [];
        }

        $authors = [];
        foreach ($authorStrings as $author) {
            $rendered = $this->normalizePlainMetaLine($this->renderPlainText((string) $author));
            if ($rendered !== '') {
                $authors[] = $rendered;
            }
        }

        return $authors;
    }

    private function renderPlainMetaInlines(mixed $nodes, string $fallback): string
    {
        if (is_array($nodes)) {
            return $this->normalizePlainMetaLine($this->renderPlainInlines($nodes));
        }

        return $this->normalizePlainMetaLine($this->renderPlainText($fallback));
    }

    private function normalizePlainMetaLine(string $value): string
    {
        return trim(preg_replace('/[ \t]*\R[ \t]*/u', ' ', $value) ?? $value);
    }

    private function renderHeadingText(AstNode $node): string
    {
        $text = $node->children === []
            ? $this->escapeText((string) $node->attr('text', ''), [])
            : $this->renderInlines($node->children);

        return trim(preg_replace('/[ \t]*\R[ \t]*/u', ' ', $text) ?? $text);
    }

    private function renderHeadingAttributes(AstNode $node, string $autoId): string
    {
        if (!(bool) ($this->options['headerAttributes'] ?? true)) {
            return '';
        }

        $attrs = $this->linkAttrTuple($node);
        if ($this->isNullAttrTuple($attrs)) {
            return '';
        }

        if ($attrs['id'] !== '' && $attrs['classes'] === [] && $attrs['attributes'] === []) {
            if ($autoId !== '' && $attrs['id'] === $autoId) {
                return '';
            }
        }

        return $this->renderAttributesTuple($attrs);
    }

    private function uniqueHeadingAutoIdentifier(string $text): string
    {
        $base = $this->autoIdentifier($text);
        $base = $base === '' ? 'section' : $base;
        $count = $this->headingAutoIdUses[$base] ?? 0;
        $this->headingAutoIdUses[$base] = $count + 1;

        return $count === 0 ? $base : $base . '-' . $count;
    }

    private function autoIdentifier(string $text): string
    {
        $plain = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $plain = str_replace(["'", "\u{2019}"], '', $plain);
        $identifier = preg_replace('/[^\pL\pN]+/u', '-', $plain) ?? $plain;

        return trim($identifier, '-');
    }

    /**
     * @return list<string>
     */
    private function renderFigure(AstNode $node, int $indent): array
    {
        if ($this->isPlainTextVariant()) {
            return $this->renderPlainTextFigure($node, $indent);
        }

        $image = $this->singleImageFigureChild($node);
        if ($image instanceof AstNode && $this->implicitFiguresEnabled()) {
            $implicitImage = $this->implicitFigureImage($node, $image);
            if (
                $implicitImage instanceof AstNode
                && ($this->linkAttributesEnabled() || $this->isNullAttrTuple($this->imageAttrTuple($implicitImage)))
            ) {
                return [str_repeat(' ', $indent) . $this->renderImage($implicitImage, [])];
            }
        }

        if ($this->rawHtmlEnabled()) {
            return $this->renderRawHtmlFigure($node, $indent);
        }

        if ($this->fencedDivsEnabled() || $this->nativeDivsEnabled() || !$this->implicitFiguresEnabled()) {
            return $this->renderBlock($this->figureAsDiv($node), $indent);
        }

        $body = $this->renderBlockCollection($this->figureBlockChildren($node));

        return $body === '' ? [] : $this->prefixLines(explode("\n", $body), $indent);
    }

    /**
     * @return list<string>
     */
    private function renderPlainTextFigure(AstNode $node, int $indent): array
    {
        $image = $this->singleImageFigureChild($node);
        if ($image instanceof AstNode && $this->implicitFiguresEnabled()) {
            $implicitImage = $this->implicitFigureImage($node, $image);
            if ($implicitImage instanceof AstNode) {
                return [str_repeat(' ', $indent) . $this->renderPlainImage($implicitImage)];
            }
        }

        $body = $this->renderBlockCollection($this->figureBlockChildren($node));

        return $body === '' ? [] : $this->prefixLines(explode("\n", $body), $indent);
    }

    /**
     * @return list<string>
     */
    private function renderTable(AstNode $node, int $indent): array
    {
        $hasColRowSpans = $this->tableHasColRowSpans($node);
        $hasFooter = $this->tableHasFooter($node);
        $hasSimpleCells = $this->tableHasSimpleCells($node);
        $hasWidthHints = $this->tableWidths($node) !== [];
        $hasBodyHeadRows = $this->tableHasBodyHeadRows($node);
        $headRowCount = count($this->tableHeadRows($node));
        $isSimpleTable = $hasSimpleCells
            && !$hasColRowSpans
            && !$hasFooter
            && !$hasWidthHints
            && $headRowCount <= 1
            && !$hasBodyHeadRows;
        $canUsePipeTable = $this->pipeTablesEnabled()
            && $hasSimpleCells
            && count($this->tableHeadRows($node)) <= 1
            && !$hasBodyHeadRows;
        $columnCount = $this->tableLogicalColumnCount($node);
        $hasBlockCells = $this->tableHasBlockCells($node);
        $canUseMultilineTable = $this->multilineTablesEnabled()
            && !$hasColRowSpans
            && !$hasFooter
            && !$hasBlockCells
            && count($this->tableHeadRows($node)) <= 1
            && !$hasBodyHeadRows;
        $canUseGridTable = $this->gridTablesEnabled()
            && ($hasColRowSpans || $hasBlockCells || $hasFooter || $this->writerColumns() >= 8 * $columnCount);

        if ($isSimpleTable && $this->simpleTablesEnabled()) {
            return $this->prefixLines($this->renderSimpleTable($node, $columnCount), $indent);
        }

        if ($isSimpleTable && $this->pipeTablesEnabled()) {
            return $this->prefixLines($this->renderPipeTable($node), $indent);
        }

        if ($canUseMultilineTable) {
            return $this->prefixLines($this->renderMultilineTable($node, $columnCount), $indent);
        }

        if ($canUsePipeTable && !$hasColRowSpans && !$hasFooter) {
            return $this->prefixLines($this->renderPipeTable($node), $indent);
        }

        if ($canUseGridTable) {
            return $this->prefixLines($this->renderGridTable($node, $columnCount), $indent);
        }

        if ($this->rawHtmlEnabled()) {
            return $this->prefixLines($this->renderRawHtmlTableLines($node), $indent);
        }

        if ($canUsePipeTable && $hasColRowSpans && !$hasFooter) {
            return $this->prefixLines($this->renderPipeTable($node), $indent);
        }

        return $this->prefixLines($this->renderTablePlaceholder($node), $indent);
    }

    /**
     * @return list<string>
     */
    private function renderSimpleTable(AstNode $table, int $columnCount): array
    {
        $headRows = $this->tableHeadRows($table);
        $bodyRows = $this->tableBodyRows($table, false);
        $headless = $headRows === [];
        $header = $headless
            ? array_fill(0, $columnCount, '')
            : $this->padTableRow($this->renderSimpleTableRowCells($headRows[0]), $columnCount);
        $rows = array_map(
            fn (AstNode $row): array => $this->padTableRow($this->renderSimpleTableRowCells($row), $columnCount),
            $bodyRows
        );
        $widths = $this->simpleTableColumnWidths($header, $rows);
        $alignments = $this->tableAlignments($table, $columnCount);
        $rule = $this->renderSimpleTableRule($widths);
        $lines = [];

        if ($headless) {
            $lines[] = '  ' . $rule;
        } else {
            $lines[] = '  ' . $this->renderSimpleTableRow($header, $widths, $alignments);
            $lines[] = '  ' . $rule;
        }

        foreach ($rows as $row) {
            $lines[] = '  ' . $this->renderSimpleTableRow($row, $widths, $alignments);
        }

        if ($headless) {
            $lines[] = '  ' . $rule;
        }

        $caption = $this->renderIndentedPandocTableCaptionLines($table);
        if ($caption !== []) {
            $lines[] = '';
            array_push($lines, ...$caption);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderSimpleTableRowCells(AstNode $row): array
    {
        $cells = [];
        foreach ($row->children as $cell) {
            if ($cell->type !== 'table_cell') {
                continue;
            }

            $cells[] = $this->renderSimpleTableCell($cell);
        }

        return $cells;
    }

    private function renderSimpleTableCell(AstNode $cell): string
    {
        if ($cell->children === []) {
            $text = $this->renderBlockInlines([new AstNode('text', ['text' => (string) $cell->attr('text', '')])]);
        } else {
            $text = $this->renderBlockInlines($cell->children);
        }

        return trim(preg_replace('/[ \t]*\R[ \t]*/u', ' ', $text) ?? $text);
    }

    /**
     * @param list<string> $header
     * @param list<list<string>> $rows
     * @return list<int>
     */
    private function simpleTableColumnWidths(array $header, array $rows): array
    {
        $widths = array_fill(0, count($header), 2);
        foreach (array_merge([$header], $rows) as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 2, $this->markdownDisplayWidth($cell) + 2);
            }
        }

        return $widths;
    }

    /**
     * @param list<string> $cells
     * @param list<int> $widths
     * @param list<string> $alignments
     */
    private function renderSimpleTableRow(array $cells, array $widths, array $alignments): string
    {
        $rendered = [];
        foreach ($widths as $index => $width) {
            $rendered[] = $this->alignSimpleTableCell($cells[$index] ?? '', $width, $alignments[$index] ?? 'default');
        }

        return rtrim(implode(' ', $rendered));
    }

    private function alignSimpleTableCell(string $cell, int $width, string $alignment): string
    {
        $length = $this->markdownDisplayWidth($cell);
        $padding = max(0, $width - $length);

        if ($alignment === 'right') {
            return str_repeat(' ', $padding) . $cell;
        }

        if ($alignment === 'center') {
            $left = intdiv($padding, 2);
            $right = $padding - $left;

            return str_repeat(' ', $left) . $cell . str_repeat(' ', $right);
        }

        return $cell . str_repeat(' ', $padding);
    }

    /**
     * @param list<int> $widths
     */
    private function renderSimpleTableRule(array $widths): string
    {
        return implode(' ', array_map(static fn (int $width): string => str_repeat('-', $width), $widths));
    }

    /**
     * @return list<string>
     */
    private function renderIndentedPandocTableCaptionLines(AstNode $table): array
    {
        $caption = $this->renderTableCaptionMarkdown($table);
        if ($caption === '') {
            return [];
        }

        return array_map(
            static fn (string $line): string => '  ' . $line,
            $this->wrapTableCaptionMarkdownLines(($this->tableCaptionsEnabled() ? ': ' : '') . $caption, 2)
        );
    }

    /**
     * @return list<string>
     */
    private function renderPipeTable(AstNode $table): array
    {
        $headRows = $this->tableHeadRows($table);
        $bodyRows = $this->tableBodyRows($table, false);
        $headless = $headRows === [];
        $header = $headless ? [] : $this->renderPipeTableRowCells($headRows[0]);
        $rows = array_map(fn (AstNode $row): array => $this->renderPipeTableRowCells($row), $bodyRows);
        $columnCount = $this->tableColumnCount($table, $header, $rows);
        $alignments = $this->tableAlignments($table, $columnCount);
        $header = $this->padTableRow($headless ? [] : $header, $columnCount);
        if ($headless) {
            $header = array_fill(0, $columnCount, '');
        }
        $rows = array_map(fn (array $row): array => $this->padTableRow($row, $columnCount), $rows);
        $contentWidths = $this->pipeTableContentWidths($header, $rows);
        $delimiterWidths = $this->pipeTableDelimiterWidths($table, $contentWidths);
        $padCells = array_sum($contentWidths) <= $this->writerColumns();

        $lines = [
            $this->renderPipeTableRow($header, $alignments, $contentWidths, $padCells),
            $this->renderPipeTableDelimiter($alignments, $delimiterWidths),
        ];
        foreach ($rows as $row) {
            $lines[] = $this->renderPipeTableRow($row, $alignments, $contentWidths, $padCells);
        }

        $captionLines = $this->renderTableCaptionMarkdownLines($table);
        if ($captionLines !== []) {
            $lines[] = '';
            array_push($lines, ...$captionLines);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function firstTableHeadRowCells(AstNode $table): array
    {
        $rows = $this->tableHeadRows($table);
        if ($rows === []) {
            return [];
        }

        return $this->renderPipeTableRowCells($rows[0]);
    }

    /**
     * @return list<string>
     */
    private function renderGridTable(AstNode $table, int $columnCount): array
    {
        $headRows = $this->gridRowsFromTableRows($this->tableHeadRows($table), $columnCount);
        $bodyRows = $this->gridRowsFromTableRows($this->tableBodyRows($table, true), $columnCount);
        $footRows = $this->gridRowsFromTableRows($this->tableFootRows($table), $columnCount);
        $widths = $this->gridTableColumnWidths($table, $columnCount, [...$headRows, ...$bodyRows, ...$footRows]);
        $alignments = $this->tableAlignments($table, $columnCount);
        $this->applyGridTableWidths($headRows, $widths);
        $this->applyGridTableWidths($bodyRows, $widths);
        $this->applyGridTableWidths($footRows, $widths);
        $this->applyGridPartBorders($headRows, 'single', 'double_header');
        $this->applyGridPartBorders($bodyRows, $headRows === [] ? 'single_header' : 'single', 'single');
        $this->applyGridPartBorders($footRows, 'double', 'double');

        $lines = $this->renderGridTableRows([...$headRows, ...$bodyRows, ...$footRows], $alignments);

        $captionLines = $this->renderTableCaptionMarkdownLines($table);
        if ($captionLines !== []) {
            $lines[] = '';
            array_push($lines, ...$captionLines);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderMultilineTable(AstNode $table, int $columnCount): array
    {
        $headRows = $this->multilineRowsFromTableRows($this->tableHeadRows($table), $columnCount);
        $bodyRows = $this->multilineRowsFromTableRows($this->tableBodyRows($table, false), $columnCount);
        $widths = $this->multilineTableColumnWidths($table, $columnCount, [...$headRows, ...$bodyRows]);
        $alignments = $this->tableAlignments($table, $columnCount);
        $lines = [];
        $headless = $headRows === [];

        if ($headless) {
            $lines[] = '  ' . $this->renderMultilineTableColumnRule($widths);
        } else {
            $lines[] = '  ' . $this->renderMultilineTableFullRule($widths);
            foreach ($headRows as $row) {
                array_push($lines, ...$this->renderMultilineTableRow($row, $widths, $alignments));
            }
            $lines[] = '  ' . $this->renderMultilineTableColumnRule($widths);
        }

        foreach ($bodyRows as $index => $row) {
            if ($index > 0) {
                $lines[] = '';
            }
            array_push($lines, ...$this->renderMultilineTableRow($row, $widths, $alignments));
        }

        if (count($bodyRows) === 1) {
            $lines[] = '';
        }

        $lines[] = '  ' . ($headless
            ? $this->renderMultilineTableColumnRule($widths)
            : $this->renderMultilineTableFullRule($widths));

        $caption = $this->renderTableCaptionMarkdown($table);
        if ($caption !== '') {
            $lines[] = '';
            array_push($lines, ...$this->renderIndentedPandocTableCaptionLines($table));
        }

        return $lines;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<list<list<string>>>
     */
    private function multilineRowsFromTableRows(array $rows, int $columnCount): array
    {
        $rendered = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row->children as $cell) {
                if ($cell->type === 'table_cell') {
                    $cells[] = $this->renderMultilineTableCellLines($cell);
                }
            }
            while (count($cells) < $columnCount) {
                $cells[] = [''];
            }
            $rendered[] = array_slice($cells, 0, $columnCount);
        }

        return $rendered;
    }

    /**
     * @return list<string>
     */
    private function renderMultilineTableCellLines(AstNode $cell): array
    {
        $text = $cell->children === []
            ? $this->renderBlockInlines([new AstNode('text', ['text' => (string) $cell->attr('text', '')])])
            : $this->renderBlockInlines($cell->children);
        $lines = preg_split('/\R/u', $text);
        if ($lines === false || $lines === []) {
            return [''];
        }

        return array_map(static fn (string $line): string => rtrim($line), $lines);
    }

    /**
     * @param list<list<list<string>>> $rows
     * @return list<int>
     */
    private function multilineTableColumnWidths(AstNode $table, int $columnCount, array $rows): array
    {
        $widths = array_fill(0, $columnCount, 0);
        $tableWidths = $this->tableWidths($table);
        $columns = $this->writerColumns();
        $hasRelativeWidths = $tableWidths !== [];

        for ($index = 0; $index < $columnCount; $index++) {
            if (isset($tableWidths[$index]) && $tableWidths[$index] > 0.0) {
                $widths[$index] = max(1, (int) floor(($columns - 1) * $tableWidths[$index]));
            }
        }

        foreach ($rows as $row) {
            foreach ($row as $index => $cellLines) {
                foreach ($cellLines as $line) {
                    $lineWidth = $hasRelativeWidths
                        ? $this->relativeMultilineTableLineWidth($line)
                        : $this->markdownDisplayWidth($line);
                    $widths[$index] = max($widths[$index] ?? 0, $lineWidth);
                }
            }
        }

        return array_map(static fn (int $width): int => max(3, $width), $widths);
    }

    /**
     * @param list<int> $widths
     */
    private function renderMultilineTableFullRule(array $widths): string
    {
        return str_repeat('-', max(1, array_sum($widths) + max(0, count($widths) - 1)));
    }

    /**
     * @param list<int> $widths
     */
    private function renderMultilineTableColumnRule(array $widths): string
    {
        return implode(' ', array_map(static fn (int $width): string => str_repeat('-', $width), $widths));
    }

    /**
     * @param list<list<string>> $row
     * @param list<int> $widths
     * @param list<string> $alignments
     * @return list<string>
     */
    private function renderMultilineTableRow(array $row, array $widths, array $alignments): array
    {
        $wrappedRow = [];
        foreach ($widths as $column => $width) {
            $wrappedRow[$column] = $this->wrapMultilineTableCellLines($row[$column] ?? [''], $width);
        }

        $height = 1;
        foreach ($wrappedRow as $cellLines) {
            $height = max($height, count($cellLines));
        }

        $lines = [];
        for ($lineIndex = 0; $lineIndex < $height; $lineIndex++) {
            $cells = [];
            foreach ($widths as $column => $width) {
                $cells[] = $this->alignMultilineTableCellLine(
                    $wrappedRow[$column][$lineIndex] ?? '',
                    $width,
                    $alignments[$column] ?? 'default'
                );
            }
            $lines[] = '  ' . implode(' ', $cells);
        }

        return $lines;
    }

    private function alignMultilineTableCellLine(string $line, int $width, string $alignment): string
    {
        $length = $this->markdownDisplayWidth($line);
        $padding = max(0, $width - $length);

        if ($alignment === 'right') {
            return str_repeat(' ', $padding) . $line;
        }

        if ($alignment === 'center') {
            $left = intdiv($padding, 2);
            $right = $padding - $left;

            return str_repeat(' ', $left) . $line . str_repeat(' ', $right);
        }

        return $line . str_repeat(' ', $padding);
    }

    private function minimumUnbreakableTableLineWidth(string $line): int
    {
        $words = preg_split('/\s+/u', trim($line));
        if ($words === false || $words === []) {
            return 0;
        }

        $max = 0;
        foreach ($words as $word) {
            $max = max($max, $this->markdownDisplayWidth($word));
        }

        return $max + 2;
    }

    private function relativeMultilineTableLineWidth(string $line): int
    {
        return $this->writerWrapText() === 'auto'
            ? $this->minimumUnbreakableTableLineWidth($line)
            : $this->markdownDisplayWidth($line) + 2;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function wrapMultilineTableCellLines(array $lines, int $width): array
    {
        $wrapped = [];
        foreach ($lines as $line) {
            array_push($wrapped, ...$this->wrapMultilineTableLine($line, $width));
        }

        return $wrapped === [] ? [''] : $wrapped;
    }

    /**
     * @return list<string>
     */
    private function wrapMultilineTableLine(string $line, int $width): array
    {
        if ($line === '' || $this->markdownDisplayWidth($line) <= $width) {
            return [$line];
        }

        $words = preg_split('/\s+/u', trim($line));
        if ($words === false || $words === []) {
            return [$line];
        }

        $lines = [];
        $current = '';
        foreach ($words as $word) {
            if ($current === '') {
                $current = $word;
                continue;
            }

            if ($this->markdownDisplayWidth($current . ' ' . $word) <= $width) {
                $current .= ' ' . $word;
                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<list<array{kind:string, col:int, colspan:int, rowspan:int, lines:list<string>, align:string, top:string, bottom:string, width:int}>>
     */
    private function gridRowsFromTableRows(array $rows, int $columnCount): array
    {
        $gridRows = [];
        $activeRowspans = [];
        foreach ($rows as $row) {
            $cells = [];
            $sourceCells = array_values(array_filter(
                $row->children,
                static fn (AstNode $cell): bool => $cell->type === 'table_cell'
            ));
            $sourceIndex = 0;

            for ($column = 0; $column < $columnCount;) {
                if (isset($activeRowspans[$column])) {
                    $span = $activeRowspans[$column];
                    $colspan = min((int) $span['colspan'], max(1, $columnCount - $column));
                    $remaining = (int) $span['remaining'];
                    $cells[] = $this->gridTableCell($column, $colspan, $remaining, [''], 'default', 'dummy');
                    if ($remaining > 1) {
                        $activeRowspans[$column]['remaining'] = $remaining - 1;
                    } else {
                        unset($activeRowspans[$column]);
                    }
                    $column += $colspan;
                    continue;
                }

                $cell = $sourceCells[$sourceIndex] ?? null;
                if (!$cell instanceof AstNode) {
                    $cells[] = $this->gridTableCell($column, 1, 1, [''], 'default', 'dummy');
                    $column++;
                    continue;
                }

                $sourceIndex++;
                $colspan = min(max(1, (int) $cell->attr('colspan', 1)), max(1, $columnCount - $column));
                $rowspan = max(1, (int) $cell->attr('rowspan', 1));
                $align = (string) $cell->attr('align', 'default');
                if (!in_array($align, ['left', 'right', 'center'], true)) {
                    $align = 'default';
                }

                $cells[] = $this->gridTableCell(
                    $column,
                    $colspan,
                    $rowspan,
                    $this->renderGridTableCellLines($cell),
                    $align,
                    'cell'
                );
                if ($rowspan > 1) {
                    $activeRowspans[$column] = [
                        'colspan' => $colspan,
                        'remaining' => $rowspan - 1,
                    ];
                }
                $column += $colspan;
            }
            $gridRows[] = $cells;
        }

        return $gridRows;
    }

    /**
     * @param list<string> $lines
     * @return array{kind:string, col:int, colspan:int, rowspan:int, lines:list<string>, align:string, top:string, bottom:string, width:int}
     */
    private function gridTableCell(int $column, int $colspan, int $rowspan, array $lines, string $align, string $kind): array
    {
        return [
            'kind' => $kind,
            'col' => $column,
            'colspan' => max(1, $colspan),
            'rowspan' => max(1, $rowspan),
            'lines' => $lines === [] ? [''] : $lines,
            'align' => $align,
            'top' => $kind === 'dummy' ? 'none' : 'single',
            'bottom' => $rowspan > 1 || $kind === 'dummy' ? 'none' : 'single',
            'width' => 1,
        ];
    }

    /**
     * @return list<string>
     */
    private function renderGridTableCellLines(AstNode $cell): array
    {
        if ($cell->children === []) {
            $text = $this->renderBlockInlines([new AstNode('text', ['text' => (string) $cell->attr('text', '')])]);
        } elseif ($this->cellHasOnlyInlineChildren($cell)) {
            $text = $this->renderBlockInlines($cell->children);
        } else {
            $text = $this->renderBlockCollection($this->tableCellChildrenAsBlocks($cell));
        }

        $lines = preg_split('/\R/u', $text);
        if ($lines === false || $lines === []) {
            return [''];
        }

        return array_map(static fn (string $line): string => rtrim($line), $lines);
    }

    /**
     * @return list<AstNode>
     */
    private function tableCellChildrenAsBlocks(AstNode $cell): array
    {
        $blocks = [];
        $inlineRun = [];
        foreach ($cell->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlineRun[] = $child;
                continue;
            }

            if ($inlineRun !== []) {
                $blocks[] = new AstNode('plain', [], $inlineRun);
                $inlineRun = [];
            }
            $blocks[] = $child;
        }

        if ($inlineRun !== []) {
            $blocks[] = new AstNode('plain', [], $inlineRun);
        }

        return $blocks;
    }

    /**
     * @param list<list<array{col:int, colspan:int, lines:list<string>}>> $rows
     * @return list<int>
     */
    private function gridTableColumnWidths(AstNode $table, int $columnCount, array $rows): array
    {
        $widths = array_fill(0, $columnCount, 1);
        $tableWidths = $this->tableWidths($table);
        $columns = $this->writerColumns();

        for ($index = 0; $index < $columnCount; $index++) {
            if (isset($tableWidths[$index]) && $tableWidths[$index] > 0.0) {
                $widths[$index] = max($widths[$index], (int) floor($tableWidths[$index] * $columns) - 3);
            }
        }

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $colspan = max(1, (int) $cell['colspan']);
                $column = max(0, (int) $cell['col']);
                $maxContentWidth = 0;
                foreach ($cell['lines'] as $line) {
                    $maxContentWidth = max($maxContentWidth, $this->markdownDisplayWidth($line));
                }

                if ($colspan === 1) {
                    $widths[$column] = max($widths[$column] ?? 3, $maxContentWidth);
                    continue;
                }

                $available = $this->gridTableCellWidthFromColumns($widths, $column, $colspan);
                $extra = max(0, $maxContentWidth - $available);
                for ($offset = 0; $extra > 0 && $offset < $colspan; $offset = ($offset + 1) % $colspan) {
                    $target = $column + $offset;
                    if (!array_key_exists($target, $widths)) {
                        break;
                    }
                    $widths[$target]++;
                    $extra--;
                }
            }
        }

        return array_map(static fn (int $width): int => max(1, $width), $widths);
    }

    private function gridTableCellWidthFromColumns(array $widths, int $column, int $colspan): int
    {
        return array_sum(array_slice($widths, $column, $colspan)) + (3 * max(0, $colspan - 1));
    }

    /**
     * @param list<int> $widths
     * @param list<list<array{col:int, colspan:int, width:int}>> $rows
     */
    private function applyGridTableWidths(array &$rows, array $widths): void
    {
        foreach ($rows as &$row) {
            foreach ($row as &$cell) {
                $cell['width'] = $this->gridTableCellWidthFromColumns(
                    $widths,
                    (int) $cell['col'],
                    (int) $cell['colspan']
                );
            }
        }
        unset($row, $cell);
    }

    /**
     * @param list<list<array{top:string, bottom:string}>> $rows
     */
    private function applyGridPartBorders(array &$rows, string $topStyle, string $bottomStyle): void
    {
        if ($rows === []) {
            return;
        }

        foreach ($rows[0] as &$cell) {
            $cell['top'] = $topStyle;
        }
        unset($cell);

        $last = array_key_last($rows);
        if ($last === null) {
            return;
        }

        foreach ($rows[$last] as &$cell) {
            $cell['bottom'] = $bottomStyle;
        }
        unset($cell);
    }

    /**
     * @param list<list<array{lines:list<string>, width:int, top:string, bottom:string, align:string}>> $rows
     * @param list<string> $alignments
     * @return list<string>
     */
    private function renderGridTableRows(array $rows, array $alignments): array
    {
        if ($rows === []) {
            return [];
        }

        $lines = [$this->renderGridTableBorder($rows[0], 'top', $alignments)];
        foreach ($rows as $index => $row) {
            array_push($lines, ...$this->renderGridTableRow($row));
            $bottom = $this->renderGridTableBorder($row, 'bottom', $alignments);
            $next = $rows[$index + 1] ?? null;
            $lines[] = $next === null
                ? $bottom
                : $this->combineGridBorders($bottom, $this->renderGridTableBorder($next, 'top', $alignments));
        }

        return $lines;
    }

    /**
     * @param list<array{col:int, colspan:int, width:int, align:string, top:string, bottom:string}> $row
     * @param list<string> $alignments
     */
    private function renderGridTableBorder(array $row, string $edge, array $alignments): string
    {
        $border = '';
        $previousStyle = 'none';
        foreach ($row as $cell) {
            $style = (string) ($cell[$edge] ?? 'single');
            $border .= ($style === 'none' && $previousStyle === 'none') ? '|' : '+';
            $border .= $this->renderGridTableBorderSection($cell, $style, $alignments);
            $previousStyle = $style;
        }

        return $border . ($previousStyle === 'none' ? '|' : '+');
    }

    /**
     * @param array{col:int, colspan:int, width:int, align:string} $cell
     * @param list<string> $alignments
     */
    private function renderGridTableBorderSection(array $cell, string $style, array $alignments): string
    {
        $lineChar = match ($style) {
            'double', 'double_header' => '=',
            'none' => ' ',
            default => '-',
        };
        $left = $lineChar;
        $right = $lineChar;
        if ($style === 'single_header' || $style === 'double_header') {
            $alignment = $cell['align'] !== 'default'
                ? $cell['align']
                : ($alignments[(int) $cell['col']] ?? 'default');
            if ($alignment === 'left' || $alignment === 'center') {
                $left = ':';
            }
            if ($alignment === 'right' || $alignment === 'center') {
                $right = ':';
            }
        }

        return $left . str_repeat($lineChar, max(1, (int) $cell['width'])) . $right;
    }

    /**
     * @param list<array{lines:list<string>, width:int}> $row
     * @return list<string>
     */
    private function renderGridTableRow(array $row): array
    {
        $height = 1;
        foreach ($row as $cell) {
            $height = max($height, count($cell['lines']));
        }

        $lines = [];
        for ($lineIndex = 0; $lineIndex < $height; $lineIndex++) {
            $cells = [];
            foreach ($row as $cell) {
                $cellLine = $cell['lines'][$lineIndex] ?? '';
                $cells[] = ' ' . $cellLine . str_repeat(' ', max(0, (int) $cell['width'] - $this->markdownDisplayWidth($cellLine))) . ' ';
            }
            $lines[] = '|' . implode('|', $cells) . '|';
        }

        return $lines;
    }

    private function combineGridBorders(string $first, string $second): string
    {
        if ($first === '') {
            return $second;
        }

        $length = min(strlen($first), strlen($second));
        $combined = '';
        for ($index = 0; $index < $length; $index++) {
            $combined .= $this->combineGridBorderChars($first[$index], $second[$index]);
        }

        return $combined . substr($first, $length) . substr($second, $length);
    }

    private function combineGridBorderChars(string $first, string $second): string
    {
        if ($first === '+' || $second === '+') {
            return '+';
        }
        if ($first === ':' || $second === ':') {
            return ':';
        }
        if (($first === '|' && ($second === '-' || $second === '=')) || ($second === '|' && ($first === '-' || $first === '='))) {
            return '+';
        }
        if ($first === '=' || $second === '=') {
            return '=';
        }
        if ($first === ' ') {
            return $second;
        }

        return $first;
    }

    /**
     * @return list<string>
     */
    private function renderPipeTableRowCells(AstNode $row): array
    {
        $cells = [];
        foreach ($row->children as $cell) {
            if ($cell->type === 'table_cell') {
                $cells[] = $this->renderPipeTableCell($cell);
            }
        }

        return $cells;
    }

    private function renderPipeTableCell(AstNode $cell): string
    {
        if ($cell->children === []) {
            $text = $this->renderBlockInlines([new AstNode('text', ['text' => (string) $cell->attr('text', '')])]);
        } else {
            $text = $this->renderBlockInlines($cell->children);
        }

        return preg_replace('/[ \t]*\R[ \t]*/u', '<br />', trim($text)) ?? trim($text);
    }

    /**
     * @param list<string> $header
     * @param list<list<string>> $rows
     */
    private function tableColumnCount(AstNode $table, array $header, array $rows): int
    {
        $count = max($this->tableLogicalColumnCount($table), count($header), count($this->tableAlignments($table, 0)), count($this->tableWidths($table)));
        foreach ($rows as $row) {
            $count = max($count, count($row));
        }

        return max(1, $count);
    }

    private function tableLogicalColumnCount(AstNode $table): int
    {
        $count = max(count($this->tableAlignments($table, 0)), count($this->tableWidths($table)), 1);
        foreach ([$this->tableHeadRows($table), $this->tableBodyRows($table, true), $this->tableFootRows($table)] as $rows) {
            $count = max($count, $this->tableRowsLogicalColumnCount($rows));
        }

        return max(1, $count);
    }

    /**
     * @param list<AstNode> $rows
     */
    private function tableRowsLogicalColumnCount(array $rows): int
    {
        $max = 0;
        $activeRowspans = [];
        foreach ($rows as $row) {
            $sourceCells = array_values(array_filter(
                $row->children,
                static fn (AstNode $cell): bool => $cell->type === 'table_cell'
            ));
            $sourceIndex = 0;
            $column = 0;
            while ($sourceIndex < count($sourceCells) || $activeRowspans !== []) {
                if (isset($activeRowspans[$column])) {
                    $span = $activeRowspans[$column];
                    $colspan = max(1, (int) $span['colspan']);
                    $remaining = (int) $span['remaining'];
                    if ($remaining > 1) {
                        $activeRowspans[$column]['remaining'] = $remaining - 1;
                    } else {
                        unset($activeRowspans[$column]);
                    }
                    $column += $colspan;
                    $max = max($max, $column);
                    continue;
                }

                $cell = $sourceCells[$sourceIndex] ?? null;
                if ($cell instanceof AstNode) {
                    $sourceIndex++;
                    $colspan = max(1, (int) $cell->attr('colspan', 1));
                    $rowspan = max(1, (int) $cell->attr('rowspan', 1));
                    if ($rowspan > 1) {
                        $activeRowspans[$column] = [
                            'colspan' => $colspan,
                            'remaining' => $rowspan - 1,
                        ];
                    }
                    $column += $colspan;
                    $max = max($max, $column);
                    continue;
                }

                $nextActiveColumn = $this->nextActiveGridColumn($activeRowspans, $column);
                if ($nextActiveColumn === null) {
                    break;
                }
                $column = $nextActiveColumn;
            }
        }

        return $max;
    }

    /**
     * @param array<int, array{colspan:int, remaining:int}> $activeRowspans
     */
    private function nextActiveGridColumn(array $activeRowspans, int $after): ?int
    {
        $columns = array_filter(array_keys($activeRowspans), static fn (int $column): bool => $column >= $after);
        if ($columns === []) {
            return null;
        }

        return min($columns);
    }

    /**
     * @param list<string> $row
     * @return list<string>
     */
    private function padTableRow(array $row, int $columnCount): array
    {
        $row = array_slice($row, 0, $columnCount);
        while (count($row) < $columnCount) {
            $row[] = '';
        }

        return $row;
    }

    /**
     * @param list<string> $header
     * @param list<list<string>> $rows
     * @return list<int>
     */
    private function pipeTableContentWidths(array $header, array $rows): array
    {
        $widths = array_fill(0, count($header), 3);
        foreach (array_merge([$header], $rows) as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 3, $this->markdownDisplayWidth($cell));
            }
        }

        return $widths;
    }

    /**
     * @param list<int> $contentWidths
     * @return list<int>
     */
    private function pipeTableDelimiterWidths(AstNode $table, array $contentWidths): array
    {
        $columnCount = count($contentWidths);
        $writerColumns = $this->writerColumns();
        $widthHints = $this->tableWidths($table);

        if ($widthHints !== [] && array_sum($contentWidths) + $columnCount + 1 > $writerColumns) {
            $available = max(0, $writerColumns - ($columnCount + 1));
            $widths = [];
            for ($index = 0; $index < $columnCount; $index++) {
                $widths[] = max(0, (int) floor(($widthHints[$index] ?? 0.0) * $available));
            }

            return $widths;
        }

        if (array_sum($contentWidths) <= $writerColumns) {
            return $contentWidths;
        }

        return array_fill(0, $columnCount, 2);
    }

    /**
     * @param list<string> $cells
     * @param list<string> $alignments
     * @param list<int> $widths
     */
    private function renderPipeTableRow(array $cells, array $alignments, array $widths, bool $padCells): string
    {
        $rendered = [];
        foreach ($cells as $index => $cell) {
            $rendered[] = $padCells
                ? $this->padPipeTableCell($cell, $widths[$index] ?? 3, $alignments[$index] ?? 'default')
                : ' ' . $cell . ' ';
        }

        return '|' . implode('|', $rendered) . '|';
    }

    private function padPipeTableCell(string $cell, int $width, string $alignment): string
    {
        $length = $this->markdownDisplayWidth($cell);
        $padding = max(0, $width - $length);

        if ($alignment === 'right') {
            return ' ' . str_repeat(' ', $padding) . $cell . ' ';
        }

        if ($alignment === 'center') {
            $left = intdiv($padding, 2);
            $right = $padding - $left;

            return ' ' . str_repeat(' ', $left) . $cell . str_repeat(' ', $right) . ' ';
        }

        return ' ' . $cell . str_repeat(' ', $padding) . ' ';
    }

    /**
     * @param list<string> $alignments
     * @param list<int> $widths
     */
    private function renderPipeTableDelimiter(array $alignments, array $widths): string
    {
        $cells = [];
        foreach ($widths as $index => $width) {
            $cells[] = match ($alignments[$index] ?? 'default') {
                'left' => ':' . str_repeat('-', $width + 1),
                'right' => str_repeat('-', $width + 1) . ':',
                'center' => ':' . str_repeat('-', $width) . ':',
                default => str_repeat('-', $width + 2),
            };
        }

        return '|' . implode('|', $cells) . '|';
    }

    /**
     * @return list<string>
     */
    private function renderTableCaptionMarkdownLines(AstNode $table): array
    {
        $caption = $this->renderTableCaptionMarkdown($table);
        if ($caption === '') {
            return [];
        }

        return $this->wrapTableCaptionMarkdownLines(($this->tableCaptionsEnabled() ? ': ' : '') . $caption, 0);
    }

    /**
     * @return list<string>
     */
    private function wrapTableCaptionMarkdownLines(string $caption, int $indent): array
    {
        $lines = preg_split('/\R/u', $caption);
        if ($lines === false) {
            return [$caption];
        }

        if ($this->writerWrapText() !== 'auto') {
            return $lines;
        }

        $wrapped = [];
        $columns = max(1, $this->writerColumns() - $indent);
        foreach ($lines as $line) {
            if ($line === '' || $this->markdownDisplayWidth($line) <= $columns) {
                $wrapped[] = $line;
                continue;
            }

            array_push($wrapped, ...$this->wrapTableCaptionMarkdownLine($line, $columns));
        }

        return $wrapped;
    }

    /**
     * @return list<string>
     */
    private function wrapTableCaptionMarkdownLine(string $line, int $columns): array
    {
        $tokens = $this->tableCaptionMarkdownWrapTokens($line);
        if ($tokens === []) {
            return [$line];
        }

        $lines = [];
        $current = '';
        foreach ($tokens as $token) {
            if ($current === '') {
                $current = $token;
                continue;
            }

            $candidate = $current . ' ' . $token;
            if (
                $this->markdownDisplayWidth($candidate) > $columns
                && !$this->isMarkdownAttributeToken($token)
            ) {
                $lines[] = $current;
                $current = $token;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [$line] : $lines;
    }

    /**
     * @return list<string>
     */
    private function tableCaptionMarkdownWrapTokens(string $line): array
    {
        $chars = preg_split('//u', trim($line), -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $tokens = [];
        $current = '';
        $backtickRun = 0;
        $bracketDepth = 0;
        $parenDepth = 0;
        $braceDepth = 0;

        foreach ($chars as $char) {
            if ($backtickRun > 0) {
                $current .= $char;
                if ($char === '`') {
                    $backtickRun = 0;
                }
                continue;
            }

            if ($char === '`') {
                $current .= $char;
                $backtickRun = 1;
                continue;
            }

            if (preg_match('/\s/u', $char) === 1 && $bracketDepth === 0 && $parenDepth === 0 && $braceDepth === 0) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }

            $current .= $char;
            if ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '{') {
                $braceDepth++;
            } elseif ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
            }
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    private function isMarkdownAttributeToken(string $token): bool
    {
        return str_starts_with($token, '{') && str_ends_with($token, '}');
    }

    private function renderTableCaptionMarkdown(AstNode $table): string
    {
        $inlines = $this->tableCaptionInlines($table);
        $shortCaption = $this->tableShortCaptionInlines($table);
        if ($inlines === [] && $shortCaption === []) {
            return '';
        }

        $caption = $inlines === [] ? '' : $this->renderBlockInlines($inlines);
        if ($shortCaption !== []) {
            $caption = '[' . $this->renderBlockInlines($shortCaption) . ']' . ($caption === '' ? '' : ' ' . $caption);
        }
        $attrs = $this->renderAttributesTuple($this->linkAttrTuple($table));

        return $caption . ($attrs === '' ? '' : ' ' . $attrs);
    }

    /**
     * @return list<string>
     */
    private function renderTablePlaceholder(AstNode $table): array
    {
        $lines = ['[TABLE]'];
        $captionLines = $this->renderTableCaptionMarkdownLines($table);
        if ($captionLines !== []) {
            $lines[] = '';
            array_push($lines, ...$captionLines);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderRawHtmlTableLines(AstNode $table): array
    {
        $lines = ['<table' . $this->renderNodeHtmlAttributes($table) . '>'];
        $caption = $this->tableCaptionInlines($table);
        if ($caption !== []) {
            $lines[] = '<caption' . $this->renderRawHtmlTableCaptionAttrs($table) . '>' . $this->renderRawHtmlTableCaptionHtml($table, $caption) . '</caption>';
        }

        $colgroup = $this->renderHtmlTableColgroup($table);
        if ($colgroup !== '') {
            $lines[] = $colgroup;
        }

        $headRows = $this->tableHeadRows($table);
        if ($headRows !== []) {
            $head = $this->tableSection($table, 'table_head');
            $lines[] = '<thead' . ($head instanceof AstNode ? $this->renderNodeHtmlAttributes($head) : '') . '>';
            foreach ($headRows as $row) {
                $lines[] = $this->renderRawHtmlTableRow($row, $table, true);
            }
            $lines[] = '</thead>';
        }

        $bodies = $this->tableBodies($table);
        if ($bodies === []) {
            $bodies = [new AstNode('table_body')];
        }
        foreach ($bodies as $body) {
            $lines[] = '<tbody' . $this->renderNodeHtmlAttributes($body) . '>';
            foreach ($this->tableBodyHeadRows($body) as $row) {
                $lines[] = $this->renderRawHtmlTableRow($row, $table, true);
            }
            $rowHeadColumns = max(0, (int) $body->attr('rowHeadColumns', 0));
            foreach ($body->children as $row) {
                if ($row->type === 'table_row') {
                    $lines[] = $this->renderRawHtmlTableRow($row, $table, false, $rowHeadColumns);
                }
            }
            $lines[] = '</tbody>';
        }

        $footRows = $this->tableFootRows($table);
        if ($footRows !== []) {
            $foot = $this->tableSection($table, 'table_foot');
            $lines[] = '<tfoot' . ($foot instanceof AstNode ? $this->renderNodeHtmlAttributes($foot) : '') . '>';
            foreach ($footRows as $row) {
                $lines[] = $this->renderRawHtmlTableRow($row, $table, false);
            }
            $lines[] = '</tfoot>';
        }
        $lines[] = '</table>';

        return $lines;
    }

    /**
     * @param list<AstNode> $caption
     */
    private function renderRawHtmlTableCaptionHtml(AstNode $table, array $caption): string
    {
        $blocks = $table->attr('captionBlocks', []);
        if (is_array($blocks) && $blocks !== []) {
            foreach ($blocks as $block) {
                if (!$block instanceof AstNode) {
                    return $this->renderHtmlInlines($caption);
                }
            }

            return $this->renderBlocksAsHtmlFragments($blocks);
        }

        return $this->renderHtmlInlines($caption);
    }

    private function renderRawHtmlTableCaptionAttrs(AstNode $table): string
    {
        $shortCaption = trim($this->plainInlineText($this->tableShortCaptionInlines($table)));

        return $shortCaption === '' ? '' : ' data-pandoc-short-caption="' . $this->escapeHtml($shortCaption) . '"';
    }

    private function renderRawHtmlTableRow(AstNode $row, AstNode $table, bool $header, int $rowHeadColumns = 0): string
    {
        $html = '<tr' . $this->renderNodeHtmlAttributes($row) . '>';
        $logicalColumn = 0;
        foreach ($row->children as $cell) {
            if ($cell->type !== 'table_cell') {
                continue;
            }

            $colspan = max(1, (int) $cell->attr('colspan', 1));
            $rowHeader = !$header && $logicalColumn < $rowHeadColumns && $logicalColumn + $colspan <= $rowHeadColumns;
            $tag = $header || $cell->attr('header') === true || $rowHeader ? 'th' : 'td';
            $html .= '<' . $tag . $this->renderRawHtmlTableCellAttributes($table, $logicalColumn, $cell, $rowHeader) . '>'
                . $this->renderRawHtmlTableCellContent($cell)
                . '</' . $tag . '>';
            $logicalColumn += $colspan;
        }

        return $html . '</tr>';
    }

    private function renderRawHtmlTableCellContent(AstNode $cell): string
    {
        if ($cell->children === []) {
            return $this->escapeHtml((string) $cell->attr('text', ''));
        }

        $hasBlockChildren = false;
        foreach ($cell->children as $child) {
            if (!$this->isInlineNode($child)) {
                $hasBlockChildren = true;
                break;
            }
        }

        return $hasBlockChildren
            ? $this->renderBlocksAsHtmlFragments($cell->children)
            : $this->renderHtmlInlines($cell->children);
    }

    private function renderRawHtmlTableCellAttributes(AstNode $table, int $column, AstNode $cell, bool $rowHeader = false): string
    {
        $attrs = $this->renderNodeHtmlAttributes($cell, ['style']);
        $colspan = (int) $cell->attr('colspan', 1);
        if ($colspan > 1) {
            $attrs .= ' colspan="' . $colspan . '"';
        }

        $rowspan = (int) $cell->attr('rowspan', 1);
        if ($rowspan > 1) {
            $attrs .= ' rowspan="' . $rowspan . '"';
        }

        if ($rowHeader && !str_contains(strtolower($attrs), ' scope=')) {
            $attrs .= ' scope="row"';
        }

        $styles = [];
        $sourceStyle = $this->nodeHtmlStyle($cell);
        if ($sourceStyle !== '') {
            $styles[] = rtrim($sourceStyle, ';');
        }

        $alignment = (string) $cell->attr('align', '');
        $alignments = $this->tableAlignments($table, 0);
        if ($alignment === '') {
            $alignment = $alignments[$column] ?? 'default';
        }
        if (
            in_array($alignment, ['left', 'right', 'center'], true)
            && preg_match('/(?:^|;)\s*text-align\s*:/i', $sourceStyle) !== 1
        ) {
            $styles[] = 'text-align:' . $alignment;
        }

        if ($styles !== []) {
            $attrs .= ' style="' . $this->escapeHtml(implode('; ', $styles)) . '"';
        }

        return $attrs;
    }

    private function renderHtmlTableColgroup(AstNode $table): string
    {
        $widths = $this->tableWidths($table);
        if ($widths === []) {
            return '';
        }

        $cols = [];
        foreach ($widths as $width) {
            if ($width <= 0.0) {
                return '';
            }

            $cols[] = '<col style="width:' . $this->escapeHtml($this->formatTablePercent($width)) . '" />';
        }

        return '<colgroup>' . implode('', $cols) . '</colgroup>';
    }

    private function formatTablePercent(float $width): string
    {
        $formatted = rtrim(rtrim(number_format($width * 100, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . '%';
    }

    /**
     * @return list<AstNode>
     */
    private function tableHeadRows(AstNode $table): array
    {
        $head = $this->tableSection($table, 'table_head');

        return $head instanceof AstNode ? $this->tableRowsFromChildren($head->children) : [];
    }

    /**
     * @return list<AstNode>
     */
    private function tableFootRows(AstNode $table): array
    {
        $foot = $this->tableSection($table, 'table_foot');

        return $foot instanceof AstNode ? $this->tableRowsFromChildren($foot->children) : [];
    }

    /**
     * @return list<AstNode>
     */
    private function tableBodies(AstNode $table): array
    {
        $bodies = [];
        foreach ($table->children as $child) {
            if ($child->type === 'table_body') {
                $bodies[] = $child;
            }
        }

        return $bodies;
    }

    /**
     * @return list<AstNode>
     */
    private function tableBodyRows(AstNode $table, bool $includeHeadRows): array
    {
        $rows = [];
        foreach ($this->tableBodies($table) as $body) {
            if ($includeHeadRows) {
                array_push($rows, ...$this->tableBodyHeadRows($body));
            }
            array_push($rows, ...$this->tableRowsFromChildren($body->children));
        }

        return $rows;
    }

    /**
     * @return list<AstNode>
     */
    private function tableBodyHeadRows(AstNode $body): array
    {
        $headRows = $body->attr('headRows', []);
        if (!is_array($headRows)) {
            return [];
        }

        return array_values(array_filter($headRows, static fn (mixed $row): bool => $row instanceof AstNode && $row->type === 'table_row'));
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function tableRowsFromChildren(array $children): array
    {
        return array_values(array_filter($children, static fn (AstNode $node): bool => $node->type === 'table_row'));
    }

    private function tableSection(AstNode $table, string $type): ?AstNode
    {
        foreach ($table->children as $child) {
            if ($child->type === $type) {
                return $child;
            }
        }

        return null;
    }

    private function tableHasFooter(AstNode $table): bool
    {
        return $this->tableFootRows($table) !== [];
    }

    private function tableHasBodyHeadRows(AstNode $table): bool
    {
        foreach ($this->tableBodies($table) as $body) {
            if ($this->tableBodyHeadRows($body) !== []) {
                return true;
            }
        }

        return false;
    }

    private function tableHasColRowSpans(AstNode $table): bool
    {
        foreach ($this->tableAllRows($table) as $row) {
            foreach ($row->children as $cell) {
                if ($cell->type === 'table_cell' && ((int) $cell->attr('rowspan', 1) > 1 || (int) $cell->attr('colspan', 1) > 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function tableHasSimpleCells(AstNode $table): bool
    {
        foreach ($this->tableAllRows($table) as $row) {
            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }

                if (!$this->cellHasOnlyInlineChildren($cell)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function tableHasBlockCells(AstNode $table): bool
    {
        foreach ($this->tableAllRows($table) as $row) {
            foreach ($row->children as $cell) {
                if ($cell->type === 'table_cell' && !$this->cellHasOnlyInlineChildren($cell)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function cellHasOnlyInlineChildren(AstNode $cell): bool
    {
        foreach ($cell->children as $child) {
            if (!$this->isInlineNode($child)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<AstNode>
     */
    private function tableAllRows(AstNode $table): array
    {
        return [
            ...$this->tableHeadRows($table),
            ...$this->tableBodyRows($table, true),
            ...$this->tableFootRows($table),
        ];
    }

    /**
     * @return list<string>
     */
    private function tableAlignments(AstNode $table, int $columnCount): array
    {
        $alignments = $table->attr('alignments', []);
        if (!is_array($alignments)) {
            $alignments = [];
        }

        $normalized = [];
        foreach ($alignments as $alignment) {
            $normalized[] = in_array($alignment, ['left', 'right', 'center'], true) ? (string) $alignment : 'default';
        }

        while ($columnCount > 0 && count($normalized) < $columnCount) {
            $normalized[] = 'default';
        }

        return $columnCount > 0 ? array_slice($normalized, 0, $columnCount) : $normalized;
    }

    /**
     * @return list<float>
     */
    private function tableWidths(AstNode $table): array
    {
        $widths = $table->attr('widths', []);
        if (!is_array($widths)) {
            return [];
        }

        $normalized = array_values(array_map(
            static fn (mixed $width): float => is_numeric($width) ? max(0.0, (float) $width) : 0.0,
            $widths
        ));

        foreach ($normalized as $width) {
            if ($width > 0.0) {
                return $normalized;
            }
        }

        return [];
    }

    /**
     * @return list<AstNode>
     */
    private function tableCaptionInlines(AstNode $table): array
    {
        $captionInlines = $table->attr('captionInlines', []);
        if (is_array($captionInlines)) {
            $nodes = [];
            foreach ($captionInlines as $inline) {
                if ($inline instanceof AstNode) {
                    $nodes[] = $inline;
                }
            }
            if ($nodes !== []) {
                return $nodes;
            }
        }

        $captionBlocks = $table->attr('captionBlocks', []);
        $blockInlines = $this->tablePlainCaptionBlockInlines($captionBlocks);
        if ($blockInlines !== []) {
            return $blockInlines;
        }

        $caption = (string) $table->attr('caption', '');

        return $caption === '' ? [] : [new AstNode('text', ['text' => $caption])];
    }

    /**
     * @return list<AstNode>
     */
    private function tableShortCaptionInlines(AstNode $table): array
    {
        $captionInlines = $table->attr('shortCaptionInlines', []);
        if (is_array($captionInlines)) {
            $nodes = [];
            foreach ($captionInlines as $inline) {
                if ($inline instanceof AstNode) {
                    $nodes[] = $inline;
                }
            }
            if ($nodes !== []) {
                return $nodes;
            }
        }

        $captionBlocks = $table->attr('shortCaptionBlocks', []);
        $blockInlines = $this->tablePlainCaptionBlockInlines($captionBlocks);
        if ($blockInlines !== []) {
            return $blockInlines;
        }

        $caption = (string) $table->attr('shortCaption', '');

        return $caption === '' ? [] : [new AstNode('text', ['text' => $caption])];
    }

    /**
     * @return list<AstNode>
     */
    private function tablePlainCaptionBlockInlines(mixed $blocks): array
    {
        if (!is_array($blocks)) {
            return [];
        }

        $nodes = [];
        foreach ($blocks as $block) {
            if (!$block instanceof AstNode || !in_array($block->type, ['plain', 'paragraph'], true)) {
                return [];
            }

            if ($nodes !== []) {
                $nodes[] = new AstNode('softbreak');
            }
            array_push($nodes, ...$block->children);
        }

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private function renderList(AstNode $node, bool $ordered, int $indent): array
    {
        $lines = [];
        $start = (int) $node->attr('start', 1);
        $index = 0;
        $itemIndex = 0;
        $items = array_values(array_filter(
            $node->children,
            static fn (AstNode $item): bool => $item->type === 'list_item'
        ));
        $renderedItemCount = count(array_filter(
            $items,
            fn (AstNode $item): bool => !$this->listItemIsHeader($item)
        ));

        foreach ($items as $item) {
            if ($this->listItemIsHeader($item)) {
                if ($lines !== [] && end($lines) !== '') {
                    $lines[] = '';
                }
                array_push($lines, ...$this->renderListHeader($item, $indent));
                if (end($lines) !== '') {
                    $lines[] = '';
                }
                continue;
            }
            $marker = $ordered
                ? $this->orderedListMarker($node, $item, $start + $index, $itemIndex)
                : $this->bulletListMarker();
            array_push($lines, ...$this->renderListItem($item, $marker, $indent));
            if (
                $itemIndex < $renderedItemCount - 1
                && $this->listNeedsBlankBetweenItems($node, $this->nextRenderableListItem($items, $itemIndex + 1))
                && end($lines) !== ''
            ) {
                $lines[] = '';
            }
            $index++;
            $itemIndex++;
        }

        return $lines;
    }

    private function listItemIsHeader(AstNode $item): bool
    {
        return $item->attr('listHeader') === true;
    }

    /**
     * @param list<AstNode> $items
     */
    private function nextRenderableListItem(array $items, int $renderedOffset): AstNode
    {
        $seen = 0;
        foreach ($items as $item) {
            if ($this->listItemIsHeader($item)) {
                continue;
            }
            if ($seen === $renderedOffset) {
                return $item;
            }
            $seen++;
        }

        return end($items) ?: new AstNode('list_item');
    }

    /**
     * @return list<string>
     */
    private function renderListHeader(AstNode $item, int $indent): array
    {
        $attrs = $item->attrs;
        unset($attrs['listHeader']);

        return $this->renderBlock(new AstNode('div', $attrs, $item->children), $indent);
    }

    private function bulletListMarker(): string
    {
        $marker = strtolower(str_replace(['_', '-'], '', (string) ($this->options['bulletListMarker'] ?? 'dash')));

        return match ($marker) {
            'plus', '+' => '+ ',
            'star', 'asterisk', '*' => '* ',
            default => '- ',
        };
    }

    private function orderedListMarker(AstNode $node, AstNode $item, int $number, int $itemIndex): string
    {
        $style = $this->orderedListStyle($node);
        $delimiter = $this->orderedListDelimiter($node, $style);
        if ($style === 'default' && $this->readerDefaultOrderedListUsesDecimalMarkers($node, $item)) {
            $style = 'decimal';
            $delimiter = 'period';
        }

        if ($style === 'example') {
            return '(@' . $this->numberedExampleLabel($node, $item, $itemIndex) . ') ';
        }

        if ($style === 'default') {
            return ($delimiter === 'one_paren' ? '#)' : '#.') . '  ';
        }

        $number = $style === 'decimal' ? max(0, $number) : max(1, $number);
        $label = match ($style) {
            'lower_alpha' => $this->alphabeticListLabel($number, false),
            'upper_alpha' => $this->alphabeticListLabel($number, true),
            'lower_roman' => strtolower($this->romanNumeral($number)),
            'upper_roman' => $this->romanNumeral($number),
            default => (string) $number,
        };

        $marker = match ($delimiter) {
            'one_paren' => $label . ')',
            'two_parens' => '(' . $label . ')',
            default => $label . '.',
        };

        if (strlen($marker) < 3) {
            $marker .= str_repeat(' ', 3 - strlen($marker));
        }

        return $marker . ' ';
    }

    private function readerDefaultOrderedListUsesDecimalMarkers(AstNode $node, AstNode $item): bool
    {
        return $node->attr('sourceFormat') === 'html' || array_key_exists('text', $item->attrs);
    }

    private function orderedListStyle(AstNode $node): string
    {
        $style = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $node->attr('style', 'decimal'))) ?? '';
        $style = match ($style) {
            'loweralpha' => 'lower_alpha',
            'upperalpha' => 'upper_alpha',
            'lowerroman' => 'lower_roman',
            'upperroman' => 'upper_roman',
            'default', 'defaultstyle' => 'default',
            'example' => 'example',
            'decimal' => 'decimal',
            default => 'decimal',
        };

        if ($style === 'example') {
            return $this->numberedExampleListsEnabled() ? 'example' : 'decimal';
        }

        if ($style !== 'decimal' && !$this->fancyOrderedListMarkersEnabled()) {
            return 'decimal';
        }

        return $style;
    }

    private function orderedListDelimiter(AstNode $node, string $style): string
    {
        if ($style === 'example') {
            return 'two_parens';
        }

        $delimiter = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $node->attr('delimiter', 'period'))) ?? '';
        $delimiter = match ($delimiter) {
            'oneparen' => 'one_paren',
            'twoparens' => 'two_parens',
            'period', 'default', 'defaultdelim', '' => 'period',
            default => 'period',
        };

        if (!$this->fancyOrderedListMarkersEnabled() && $delimiter === 'two_parens') {
            return 'period';
        }

        return $delimiter;
    }

    private function fancyOrderedListMarkersEnabled(): bool
    {
        if (array_key_exists('fancyLists', $this->options)) {
            return (bool) $this->options['fancyLists'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? null;
        $overrides = MarkdownFormatProfile::markdownExtensionOverrides($format);
        if (array_key_exists('fancy_lists', $overrides)) {
            return $overrides['fancy_lists'];
        }

        return $this->writerVariant() === 'markdown';
    }

    private function numberedExampleListsEnabled(): bool
    {
        if (array_key_exists('exampleLists', $this->options)) {
            return (bool) $this->options['exampleLists'];
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? null;
        $overrides = MarkdownFormatProfile::markdownExtensionOverrides($format);
        if (array_key_exists('example_lists', $overrides)) {
            return $overrides['example_lists'];
        }

        return $this->writerVariant() === 'markdown';
    }

    private function alphabeticListLabel(int $number, bool $uppercase): string
    {
        $number = max(1, $number);
        $label = '';
        while ($number > 0) {
            $number--;
            $label = chr(ord('a') + ($number % 26)) . $label;
            $number = intdiv($number, 26);
        }

        return $uppercase ? strtoupper($label) : $label;
    }

    private function numberedExampleLabel(AstNode $node, AstNode $item, int $itemIndex): string
    {
        foreach ($this->numberedExampleLabelCandidates($node, $item, $itemIndex) as $candidate) {
            if ($this->isSafeNumberedExampleLabel($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function numberedExampleLabelCandidates(AstNode $node, AstNode $item, int $itemIndex): array
    {
        $candidates = [];
        foreach (['exampleLabel', 'label'] as $name) {
            $value = $item->attr($name, null);
            if (is_scalar($value)) {
                $candidates[] = (string) $value;
            }
        }

        $attributes = $item->attr('attributes', []);
        if (is_array($attributes) && isset($attributes['data-example-label']) && is_scalar($attributes['data-example-label'])) {
            $candidates[] = (string) $attributes['data-example-label'];
        }

        $labels = $node->attr('exampleLabels', []);
        if (is_array($labels) && isset($labels[$itemIndex]) && is_scalar($labels[$itemIndex])) {
            $candidates[] = (string) $labels[$itemIndex];
        }

        if ($itemIndex === 0) {
            $value = $node->attr('exampleLabel', null);
            if (is_scalar($value)) {
                $candidates[] = (string) $value;
            }
        }

        return $candidates;
    }

    private function isSafeNumberedExampleLabel(string $label): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]*$/', $label) === 1;
    }

    private function romanNumeral(int $number): string
    {
        $number = max(1, $number);
        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];
        $roman = '';
        foreach ($map as $value => $glyph) {
            while ($number >= $value) {
                $roman .= $glyph;
                $number -= $value;
            }
        }

        return $roman;
    }

    private function listItemIsTight(AstNode $item): bool
    {
        foreach ($item->children as $child) {
            if ($child->type === 'paragraph') {
                return false;
            }
        }

        return true;
    }

    private function listNeedsBlankBetweenItems(AstNode $list, AstNode $nextItem): bool
    {
        if ((bool) $list->attr('loose', false) || (bool) $nextItem->attr('loose', false)) {
            return true;
        }

        return $this->opmlNoteMarkdownEnabled() && !$this->listItemIsTight($nextItem);
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionList(AstNode $node, int $indent): array
    {
        return $this->prefixLines(
            $this->definitionListsEnabled()
                ? $this->renderNativeDefinitionList($node, $indent)
                : $this->renderDefinitionListWithoutMarkers($node),
            $indent
        );
    }

    /**
     * @return list<string>
     */
    private function renderNativeDefinitionList(AstNode $node, int $indent): array
    {
        $lines = [];
        $leadingChars = $this->isPlainTextVariant() || $this->opmlNoteMarkdownEnabled()
            ? 2
            : $this->definitionListLeadingChars();
        $marker = $this->isPlainTextVariant()
            ? str_repeat(' ', $leadingChars)
            : ':' . str_repeat(' ', max(1, $leadingChars - 1));

        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            if ($lines !== []) {
                $lines[] = '';
            }

            $lines[] = $this->renderDefinitionTerm($item);
            $definitions = $this->definitionItemDefinitions($item);
            $tight = $this->definitionItemIsTight($definitions) || $this->definitionListIsCompact($node);
            if (!$tight && $indent === 0) {
                $lines[] = '';
            }

            foreach ($definitions as $index => $definition) {
                if ($index > 0 && !$tight) {
                    $lines[] = '';
                }

                array_push(
                    $lines,
                    ...$this->renderDefinitionBodyWithMarker($definition, $marker, $leadingChars)
                );
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionListWithoutMarkers(AstNode $node): array
    {
        $lines = [];
        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            if ($lines !== []) {
                $lines[] = '';
            }

            $lines[] = $this->renderDefinitionTerm($item) . '  ';
            foreach ($this->definitionItemDefinitions($item) as $index => $definition) {
                if ($index > 0) {
                    $lines[] = '';
                }
                array_push($lines, ...$this->renderDefinitionBodyLines($definition));
            }
        }

        return $lines;
    }

    private function needsAdjacentListBlockSeparator(AstNode $previous, AstNode $current): bool
    {
        if ($previous->type !== $current->type) {
            return false;
        }

        if ($current->type === 'ordered_list') {
            $previousStyle = $this->orderedListStyle($previous);
            $currentStyle = $this->orderedListStyle($current);
            if (
                in_array($previousStyle, ['default', 'example'], true)
                || in_array($currentStyle, ['default', 'example'], true)
            ) {
                return $previousStyle === $currentStyle;
            }

            return true;
        }

        return in_array($current->type, ['bullet_list', 'definition_list'], true);
    }

    private function listSeparatorBlock(): string
    {
        if ($this->isPlainTextVariant()) {
            return '';
        }

        return $this->rawHtmlEnabled() ? '<!-- -->' : '&nbsp;';
    }

    private function renderDefinitionTerm(AstNode $item): string
    {
        $term = $item->children[0] ?? null;
        $text = $term instanceof AstNode && $term->children !== []
            ? $this->renderBlockInlines($term->children)
            : (string) $item->attr('term', $term instanceof AstNode ? $term->attr('text', '') : '');

        return trim(preg_replace('/[ \t]*\R[ \t]*/u', ' ', $text) ?? $text);
    }

    /**
     * @return list<AstNode>
     */
    private function definitionItemDefinitions(AstNode $item): array
    {
        $definitions = [];
        foreach ($item->children as $child) {
            if ($child->type === 'definition') {
                $definitions[] = $child;
            }
        }

        return $definitions;
    }

    /**
     * @param list<AstNode> $definitions
     */
    private function definitionItemIsTight(array $definitions): bool
    {
        $first = $definitions[0]->children[0] ?? null;

        return $first instanceof AstNode && $first->type === 'plain';
    }

    private function definitionListIsCompact(AstNode $node): bool
    {
        $classes = $node->attr('classes', []);
        if (!is_array($classes)) {
            return false;
        }

        return in_array('pandoc-csl-bibliography', $classes, true)
            || in_array('pandoc-csl-shorthand-list', $classes, true);
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionBodyWithMarker(AstNode $definition, string $marker, int $leadingChars): array
    {
        $bodyLines = $this->renderDefinitionBodyLines($definition);
        if ($bodyLines === []) {
            return [rtrim($marker)];
        }

        $first = array_shift($bodyLines);
        $lines = [$marker . (string) $first];
        $continuation = str_repeat(' ', $leadingChars);
        foreach ($bodyLines as $line) {
            $lines[] = $line === '' ? '' : $continuation . $line;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionBodyLines(AstNode $definition): array
    {
        $body = $this->renderBlockCollection($definition->children);
        if ($body === '') {
            return [];
        }

        return explode("\n", $body);
    }

    /**
     * @return list<string>
     */
    private function renderListItem(AstNode $item, string $marker, int $indent): array
    {
        $prefix = str_repeat(' ', $indent) . $marker;
        $continuationIndent = $indent + strlen($marker);
        $task = $item->attr('taskChecked', null);
        if (is_bool($task)) {
            $prefix .= $task ? '[x] ' : '[ ] ';
        }

        $inlineChildren = [];
        $lines = [];
        $hasFirstLine = false;

        foreach ($item->children as $childIndex => $child) {
            if ($this->isInlineNode($child)) {
                $inlineChildren[] = $child;
                continue;
            }

            $blockBeforeFencedDiv = $this->blockChildBeforeFencedDiv($item->children, $childIndex);

            if ($inlineChildren !== [] || !$hasFirstLine) {
                if ($this->opmlNoteMarkdownEnabled() && $this->writerWrapText() === 'auto') {
                    $this->appendOpmlWrappedMarkdownLines(
                        $lines,
                        $prefix,
                        str_repeat(' ', $continuationIndent),
                        $this->renderListItemInlines($inlineChildren)
                    );
                } else {
                    $lines[] = rtrim($prefix . $this->renderListItemInlines($inlineChildren));
                }
                $inlineChildren = [];
                $hasFirstLine = true;
            }

            if ($this->needsGfmDetailsListBoundary($item->children, $childIndex) && $lines !== [] && end($lines) !== '') {
                $lines[] = '';
            }

            if (
                $child->type === 'paragraph'
                || ($child->type === 'plain' && !$this->plainBeforeDisabledDiv($item->children, $childIndex))
                || $blockBeforeFencedDiv
            ) {
                if (count($lines) === 1 && rtrim($lines[0]) === rtrim($prefix)) {
                    if ($this->opmlNoteMarkdownEnabled() && $this->writerWrapText() === 'auto') {
                        array_pop($lines);
                        $this->appendOpmlWrappedMarkdownLines(
                            $lines,
                            $prefix,
                            str_repeat(' ', $continuationIndent),
                            $this->renderListItemInlines($child->children, true)
                        );
                    } else {
                        $this->replaceListItemFirstLine($lines, $prefix, $this->renderListItemInlines($child->children, true), $continuationIndent);
                    }
                    if (
                        $blockBeforeFencedDiv
                        && $this->listItemNeedsFencedDivBoundary($item->children, $childIndex)
                        && end($lines) !== ''
                    ) {
                        $lines[] = '';
                    }
                    continue;
                }

                if ($lines !== [] && end($lines) !== '') {
                    $lines[] = '';
                }
                if ($this->opmlNoteMarkdownEnabled() && $this->writerWrapText() === 'auto') {
                    $this->appendOpmlWrappedMarkdownLines(
                        $lines,
                        str_repeat(' ', $continuationIndent),
                        str_repeat(' ', $continuationIndent),
                        $this->renderListItemInlines($child->children, true)
                    );
                } else {
                    foreach (explode("\n", $this->renderListItemInlines($child->children, true)) as $line) {
                        $lines[] = str_repeat(' ', $continuationIndent) . $line;
                    }
                }
                if (
                    $blockBeforeFencedDiv
                    && $this->listItemNeedsFencedDivBoundary($item->children, $childIndex)
                    && end($lines) !== ''
                ) {
                    $lines[] = '';
                }
                continue;
            }

            if (
                $childIndex === 0
                && $inlineChildren === []
                && $child->type === 'code_block'
                && $this->codeBlockRendersIndented($child)
                && count($lines) === 1
                && rtrim($lines[0]) === rtrim($prefix)
            ) {
                $lines = $this->renderInitialIndentedCodeListItem($prefix, $child);
                continue;
            }

            $previous = $item->children[$childIndex - 1] ?? null;
            if (
                !is_bool($task)
                && $this->isListBlock($child)
                && $previous instanceof AstNode
                && $previous->type === 'paragraph'
                && $lines !== []
                && end($lines) !== ''
            ) {
                $lines[] = '';
            }

            $blockIndent = $this->listItemUsesReaderNestedListIndent($item, $child)
                ? $indent + 2
                : $continuationIndent;
            foreach ($this->renderBlock($child, $blockIndent) as $nestedLine) {
                $lines[] = $nestedLine;
            }
        }

        if ($inlineChildren !== [] || !$hasFirstLine) {
            if ($this->opmlNoteMarkdownEnabled() && $this->writerWrapText() === 'auto') {
                $this->appendOpmlWrappedMarkdownLines(
                    $lines,
                    $prefix,
                    str_repeat(' ', $continuationIndent),
                    $this->renderListItemInlines($inlineChildren)
                );
            } else {
                $this->replaceListItemFirstLine($lines, $prefix, $this->renderListItemInlines($inlineChildren), $continuationIndent);
            }
        }

        return $lines;
    }

    /**
     * @param list<AstNode> $children
     */
    private function listItemNeedsFencedDivBoundary(array $children, int $index): bool
    {
        $block = $children[$index] ?? null;
        $next = $children[$index + 1] ?? null;
        if (!$block instanceof AstNode || !$next instanceof AstNode || $next->type !== 'div') {
            return false;
        }

        if ($block->type === 'paragraph') {
            return true;
        }

        $attributes = $next->attr('attributes', []);

        return is_array($attributes) && $attributes !== [];
    }

    private function listItemUsesReaderNestedListIndent(AstNode $item, AstNode $child): bool
    {
        return $this->isListBlock($child) && array_key_exists('text', $item->attrs);
    }

    /**
     * @param list<AstNode> $children
     */
    private function plainBeforeDisabledDiv(array $children, int $index): bool
    {
        $child = $children[$index] ?? null;
        $next = $children[$index + 1] ?? null;

        return $child instanceof AstNode
            && $child->type === 'plain'
            && $next instanceof AstNode
            && $next->type === 'div'
            && !$this->fencedDivsEnabled()
            && !$this->nativeDivsEnabled();
    }

    private function replaceListItemFirstLine(array &$lines, string $prefix, string $content, int $continuationIndent): void
    {
        if ($lines !== [] && rtrim((string) end($lines)) === rtrim($prefix)) {
            array_pop($lines);
        }

        $contentLines = explode("\n", $content);
        $first = array_shift($contentLines);
        $lines[] = rtrim($prefix . (string) $first);
        foreach ($contentLines as $line) {
            $lines[] = str_repeat(' ', $continuationIndent) . $line;
        }
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderListItemInlines(array $nodes, bool $escapeInitialPlainMarker = false): string
    {
        if ($this->isPlainTextVariant()) {
            return $this->renderPlainInlines($nodes);
        }

        $text = '';
        foreach ($nodes as $index => $node) {
            $text .= $node->type === 'softbreak'
                ? $this->renderListItemSoftBreak()
                : $this->renderInline($node, array_slice($nodes, $index + 1), $escapeInitialPlainMarker && $index === 0);
            $next = $nodes[$index + 1] ?? null;
            if ($node->type === 'math' && $next instanceof AstNode && $next->type === 'text') {
                $nextText = (string) $next->attr('text', '');
                if ($nextText !== '' && preg_match('/^\d/', $nextText) === 1) {
                    $text .= '<!-- -->';
                }
            }
        }

        return $text;
    }

    private function renderListItemSoftBreak(): string
    {
        $softBreak = strtolower(str_replace(['_', '-'], '', (string) ($this->options['softBreak'] ?? '')));

        return $softBreak === 'space' ? ' ' : "\n";
    }

    /**
     * @return list<string>
     */
    private function renderInitialIndentedCodeListItem(string $prefix, AstNode $node): array
    {
        $linePrefix = rtrim($prefix) . str_repeat(' ', 5);
        $lines = [];
        foreach (explode("\n", (string) $node->attr('text', '')) as $line) {
            $lines[] = $linePrefix . $line;
        }

        return $lines;
    }

    /**
     * @param list<AstNode> $children
     */
    private function needsGfmDetailsListBoundary(array $children, int $index): bool
    {
        if ($this->writerVariant() !== 'gfm') {
            return false;
        }

        $previous = $children[$index - 1] ?? null;
        $current = $children[$index] ?? null;
        if (!$previous instanceof AstNode || !$current instanceof AstNode) {
            return false;
        }

        return ($this->isRawHtmlDetailsOpeningBlock($previous) && $this->isListBlock($current))
            || ($this->isListBlock($previous) && $this->isRawHtmlDetailsClosingBlock($current));
    }

    private function isRawHtmlDetailsOpeningBlock(AstNode $node): bool
    {
        [$format, $text] = $this->rawBlockFormatAndText($node);

        return $this->isRawHtmlFormat($format)
            && preg_match('/^\s*<details\b[^>]*>\s*$/i', $text) === 1;
    }

    private function isRawHtmlDetailsClosingBlock(AstNode $node): bool
    {
        [$format, $text] = $this->rawBlockFormatAndText($node);

        return $this->isRawHtmlFormat($format)
            && preg_match('/^\s*<\/details\s*>\s*$/i', $text) === 1;
    }

    /**
     * @param list<AstNode> $children
     */
    private function blockChildBeforeFencedDiv(array $children, int $index): bool
    {
        $child = $children[$index] ?? null;
        $next = $children[$index + 1] ?? null;

        return $this->fencedDivsEnabled()
            && $child instanceof AstNode
            && in_array($child->type, ['plain', 'paragraph'], true)
            && $next instanceof AstNode
            && $next->type === 'div';
    }

    /**
     * @return list<string>
     */
    private function renderBlockQuote(AstNode $node, int $indent): array
    {
        if ($this->isPlainTextVariant()) {
            return $this->renderPlainTextBlockQuote($node, $indent);
        }

        $body = $this->renderBlockCollection($node->children);
        $prefix = str_repeat(' ', $indent) . '>';
        if ($body === '') {
            return [$prefix];
        }

        $lines = [];
        foreach (explode("\n", $body) as $line) {
            $lines[] = $line === '' ? $prefix : $prefix . ' ' . $line;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderPlainTextBlockQuote(AstNode $node, int $indent): array
    {
        $body = $this->renderBlockCollection($node->children);
        $prefix = str_repeat(' ', $indent + 2);
        if ($body === '') {
            return [$prefix];
        }

        $lines = [];
        foreach (explode("\n", $body) as $line) {
            $lines[] = $prefix . $line;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderLineBlock(AstNode $node, int $indent): array
    {
        if ($this->isPlainTextVariant()) {
            return $this->renderPlainTextLineBlock($node, $indent);
        }

        if (!$this->lineBlocksEnabled()) {
            $inlines = $this->lineBlockAsParagraphInlines($node);

            return $inlines === [] ? [] : $this->renderBlock(new AstNode('paragraph', [], $inlines), $indent);
        }

        $prefix = str_repeat(' ', $indent);
        $lines = [];
        foreach ($node->children as $lineNode) {
            if ($lineNode->type !== 'line') {
                continue;
            }

            $renderedLine = $this->renderInlines($this->lineBlockLineInlines($lineNode));
            if ($renderedLine === '') {
                $lines[] = $prefix . '|';
                continue;
            }

            $parts = explode("\n", $renderedLine);
            $first = array_shift($parts);
            $lines[] = $prefix . '| ' . (string) $first;
            foreach ($parts as $part) {
                $lines[] = $prefix . '  ' . $part;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderPlainTextLineBlock(AstNode $node, int $indent): array
    {
        $prefix = str_repeat(' ', $indent);
        $lines = [];
        foreach ($node->children as $lineNode) {
            if ($lineNode->type !== 'line') {
                continue;
            }

            $renderedLine = $this->renderPlainInlines($this->lineBlockLineInlines($lineNode));
            if ($renderedLine === '') {
                $lines[] = $prefix === '' ? '' : rtrim($prefix);
                continue;
            }

            foreach (explode("\n", $renderedLine) as $line) {
                $lines[] = $prefix . $line;
            }
        }

        return $lines;
    }

    /**
     * @return list<AstNode>
     */
    private function lineBlockAsParagraphInlines(AstNode $node): array
    {
        $inlines = [];
        foreach ($node->children as $lineNode) {
            if ($lineNode->type !== 'line') {
                continue;
            }

            if ($inlines !== []) {
                $inlines[] = new AstNode('linebreak');
            }

            array_push($inlines, ...$this->lineBlockLineInlines($lineNode));
        }

        return $inlines;
    }

    /**
     * @return list<AstNode>
     */
    private function lineBlockLineInlines(AstNode $lineNode): array
    {
        if ($lineNode->children !== []) {
            return $lineNode->children;
        }

        $text = (string) $lineNode->attr('text', '');

        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @return list<string>
     */
    private function renderCodeBlock(AstNode $node, int $indent): array
    {
        if (!$this->codeBlockRendersIndented($node)) {
            return $this->prefixLines($this->renderFencedCodeBlock($node, $indent), $indent);
        }

        $lines = [];
        $prefix = str_repeat(' ', $indent + 4);
        foreach (explode("\n", (string) $node->attr('text', '')) as $line) {
            $lines[] = $this->opmlNoteMarkdownEnabled() && $line === '' ? '' : $prefix . $line;
        }

        return $lines;
    }

    private function codeBlockRendersIndented(AstNode $node): bool
    {
        if ($this->isNullAttrTuple($this->linkAttrTuple($node))) {
            return true;
        }

        return !$this->backtickCodeBlocksEnabled() && !$this->fencedCodeBlocksEnabled();
    }

    /**
     * @return list<string>
     */
    private function renderFencedCodeBlock(AstNode $node, int $indent): array
    {
        $text = (string) $node->attr('text', '');
        $fenceChar = $this->backtickCodeBlocksEnabled() ? '`' : '~';
        $fence = str_repeat($fenceChar, $this->codeFenceLength($text, $fenceChar));
        $attrs = $this->renderCodeBlockInfoString($node);
        $infoSeparator = $attrs === '' || $indent > 0 || str_starts_with($attrs, '{') ? '' : ' ';
        $lines = [$fence . $infoSeparator . $attrs];
        array_push($lines, ...explode("\n", $text));
        $lines[] = $fence;

        return $lines;
    }

    private function codeFenceLength(string $text, string $fenceChar): int
    {
        $length = 3;
        foreach (explode("\n", $text) as $line) {
            $trimmed = trim($line);
            if (
                strlen($trimmed) >= 3
                && strspn($trimmed, $fenceChar) === strlen($trimmed)
            ) {
                $length = max($length, strlen($trimmed) + 1);
            }
        }

        return $length;
    }

    private function renderCodeBlockInfoString(AstNode $node): string
    {
        $attrs = $this->linkAttrTuple($node);
        if ($this->fencedCodeAttributesEnabled()) {
            return $this->renderCodeBlockClassOrAttributes($attrs);
        }

        return $this->languageFromClasses($attrs['classes']);
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderCodeBlockClassOrAttributes(array $attrs): string
    {
        if ($attrs['id'] === '' && count($attrs['classes']) === 1 && $attrs['attributes'] === []) {
            return $this->escapeAttributeToken($attrs['classes'][0]);
        }

        return $this->renderAttributesTuple($attrs);
    }

    /**
     * @param list<string> $classes
     */
    private function languageFromClasses(array $classes): string
    {
        foreach ($classes as $class) {
            if (str_starts_with($class, 'language-') && $class !== 'language-') {
                return $this->escapeAttributeToken(substr($class, 9));
            }
        }

        foreach ($classes as $class) {
            if ($class !== 'sourceCode') {
                return $this->escapeAttributeToken($class);
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function renderRawBlock(AstNode $node, int $indent): array
    {
        [$format, $text] = $this->rawBlockFormatAndText($node);

        if ($this->isPlainTextVariant()) {
            return $format === 'plain' ? $this->indentedLines($text, $indent) : [];
        }

        if ($this->isCommonMarkVariant()) {
            if ($this->isRawMarkdownFormat($format)) {
                return $this->indentedLines($text, $indent);
            }

            if ($this->isRawHtmlFormat($format)) {
                return $this->indentedLines($this->removeBlankLinesInRawHtml($text), $indent);
            }
        }

        if (!$this->isCommonMarkVariant() && ($format === '' || $this->isRawMarkdownFormat($format))) {
            return $this->indentedLines($text, $indent);
        }

        if ($this->isRawHtmlFormat($format)) {
            if ($this->markdownAttributeEnabled()) {
                return $this->indentedLines($this->addMarkdownAttributeToRawHtml($text), $indent);
            }

            if ($this->rawHtmlEnabled()) {
                return $this->indentedLines($text, $indent);
            }
        }

        if (in_array($format, ['latex', 'tex'], true) && $this->rawTexEnabled()) {
            return $this->indentedLines($text, $indent);
        }

        if ($this->rawAttributeEnabled()) {
            return $this->indentedLines(
                '```{=' . $format . "}\n" . rtrim($text, "\n") . "\n```",
                $indent
            );
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function renderHorizontalRule(int $indent): array
    {
        $marker = $this->isPlainTextVariant() || $this->opmlNoteMarkdownEnabled()
            ? str_repeat('-', $this->writerColumns())
            : '* * *';

        return [str_repeat(' ', $indent) . $marker];
    }

    /**
     * @return list<string>
     */
    private function renderDiv(AstNode $node, int $indent): array
    {
        $classes = $node->attr('classes', []);
        if (
            is_array($classes)
            && ($classes[0] ?? null) === 'sourceCode'
            && count($node->children) === 1
            && $node->children[0]->type === 'code_block'
        ) {
            $codeClasses = $node->children[0]->attr('classes', []);
            if (is_array($codeClasses) && ($codeClasses[0] ?? null) === 'sourceCode') {
                return $this->renderBlock($node->children[0], $indent);
            }
        }

        $body = $this->renderBlockCollection($node->children);
        $attrs = $this->linkAttrTuple($node);
        if ($this->fencedDivsEnabled()) {
            $divNestingLevel = $this->computeDivNestingLevel($node->children);
            if ($this->opmlNoteMarkdownEnabled() && $this->directDivChildCount($node->children) > 1) {
                $divNestingLevel++;
            }
            $fence = str_repeat(':', 3 + $divNestingLevel);
            $attrText = $this->renderAttributesTuple($attrs);
            if ($attrText === '' && $this->opmlNoteMarkdownEnabled()) {
                $attrText = '{}';
            }
            $lines = [$fence . ($attrText === '' ? '' : ' ' . $attrText)];
            if ($body !== '') {
                array_push($lines, ...explode("\n", $body));
            }
            $lines[] = $fence;

            return $this->prefixLines($lines, $indent);
        }

        if ($this->nativeDivsEnabled() || ($this->rawHtmlEnabled() && $this->markdownInHtmlBlocksEnabled())) {
            $lines = ['<div' . $this->renderPandocHtmlAttributes($attrs) . '>'];
            if ($body !== '') {
                array_push($lines, ...explode("\n", $body));
            }
            $lines[] = '</div>';

            return $this->prefixLines($lines, $indent);
        }

        if ($this->rawHtmlEnabled() && $this->markdownAttributeEnabled()) {
            $attrs['attributes'] = ['markdown' => '1'] + $attrs['attributes'];
            $lines = ['<div' . $this->renderPandocHtmlAttributes($attrs) . '>'];
            if ($body !== '') {
                array_push($lines, ...explode("\n", $body));
            }
            $lines[] = '</div>';

            return $this->prefixLines($lines, $indent);
        }

        return $body === '' ? [] : $this->prefixLines(explode("\n", $body), $indent);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderBlockInlines(array $nodes, bool $escapeInitialPlainMarker = false): string
    {
        return $this->isPlainTextVariant()
            ? $this->renderPlainInlines($nodes)
            : $this->renderInlines($nodes, $escapeInitialPlainMarker);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlines(array $nodes, bool $escapeInitialPlainMarker = false): string
    {
        $text = '';
        foreach ($nodes as $index => $node) {
            $text .= $this->renderInline($node, array_slice($nodes, $index + 1), $escapeInitialPlainMarker && $index === 0);
            $next = $nodes[$index + 1] ?? null;
            if ($node->type === 'math' && $next instanceof AstNode && $next->type === 'text') {
                $nextText = (string) $next->attr('text', '');
                if ($nextText !== '' && preg_match('/^\d/', $nextText) === 1) {
                    $text .= '<!-- -->';
                }
            }
        }

        return $text;
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderInline(AstNode $node, array $following = [], bool $escapeInitialPlainMarker = false): string
    {
        return match ($node->type) {
            'text' => $this->escapeText(
                (string) $node->attr('text', ''),
                $following,
                $escapeInitialPlainMarker,
                (bool) $node->attr('preserveSmartPunctuation', false)
            ),
            'softbreak' => $this->renderSoftBreak($following),
            'space' => ' ',
            'linebreak' => $this->renderLineBreak(),
            'code' => $this->renderCode($node),
            'emph' => $this->renderEmph($node),
            'strong' => $this->delimitInlineContent('**', '**', $this->renderInlines($node->children)),
            'mark' => $this->renderMark($node),
            'underline' => $this->renderUnderline($node),
            'small_caps' => $this->renderSmallCaps($node),
            'strikeout' => $this->renderStrikeout($node),
            'superscript' => $this->renderScript('superscript', '^', 'sup', $node),
            'subscript' => $this->renderScript('subscript', '~', 'sub', $node),
            'span' => $this->renderSpan($node),
            'quoted' => $this->renderQuoted($node),
            'link' => $this->renderLink($node, $following),
            'image' => $this->renderImage($node, $following),
            'citation', 'citation_group' => $this->renderCitation($node),
            'math' => $this->renderMath($node),
            'raw_tex', 'raw_tex_inline' => $this->renderRawInline($node),
            'raw_inline', 'raw_markdown', 'raw_html_inline' => $this->renderRawInline($node),
            'note' => $this->renderNoteReference($node),
            default => $this->renderInlines($node->children),
        };
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderPlainInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= $this->renderPlainInline($node);
        }

        return $text;
    }

    private function renderPlainInline(AstNode $node): string
    {
        return match ($node->type) {
            'text' => $this->renderPlainText(
                (string) $node->attr('text', ''),
                (bool) $node->attr('preserveSmartPunctuation', false)
            ),
            'softbreak' => $this->writerWrapText() === 'preserve' ? "\n" : ' ',
            'space' => ' ',
            'linebreak' => "\n",
            'code' => (string) $node->attr('text', ''),
            'emph' => $this->renderPlainEmph($node),
            'strong' => $this->renderPlainStrong($node),
            'mark', 'underline', 'strikeout', 'superscript', 'subscript', 'span', 'citation' => $this->renderPlainInlines($node->children),
            'small_caps' => $this->renderPlainInlines($this->capitalizeInlineText($node->children)),
            'quoted' => $this->renderPlainQuoted($node),
            'link' => $this->renderPlainLink($node),
            'image' => $this->renderPlainImage($node),
            'math' => trim((string) $node->attr('text', '')),
            'raw_tex', 'raw_inline', 'raw_markdown', 'raw_html_inline' => $this->renderPlainRawInline($node),
            'note' => $this->renderPlainNoteReference($node),
            default => $this->renderPlainInlines($node->children),
        };
    }

    private function renderPlainEmph(AstNode $node): string
    {
        if ($node->children === []) {
            return '';
        }

        if (count($node->children) === 1 && $node->children[0]->type === 'emph') {
            return $this->renderPlainInlines($node->children[0]->children);
        }

        $content = $this->renderPlainInlines($node->children);

        return $this->gutenbergEnabled() ? '_' . $content . '_' : $content;
    }

    private function renderPlainStrong(AstNode $node): string
    {
        if ($node->children === []) {
            return '';
        }

        $children = $this->gutenbergEnabled()
            ? $this->capitalizeInlineText($node->children)
            : $node->children;

        return $this->renderPlainInlines($children);
    }

    private function renderPlainText(string $text, bool $preserveSmartPunctuation = false): string
    {
        if ($this->smartEnabled() && !$preserveSmartPunctuation) {
            $text = $this->unsmartifyText($text);
        }

        return $this->writerPreferAscii()
            ? $this->toHtml5Entities($text)
            : $text;
    }

    private function renderPlainQuoted(AstNode $node): string
    {
        $content = $this->renderPlainInlines($node->children);
        if ($this->smartEnabled()) {
            $quote = $node->attr('kind') === 'single' ? "'" : '"';

            return $quote . $content . $quote;
        }

        if ($node->attr('kind') === 'single') {
            return ($this->writerPreferAscii() ? '&lsquo;' : "\u{2018}") . $content . ($this->writerPreferAscii() ? '&rsquo;' : "\u{2019}");
        }

        return ($this->writerPreferAscii() ? '&ldquo;' : "\u{201C}") . $content . ($this->writerPreferAscii() ? '&rdquo;' : "\u{201D}");
    }

    private function renderPlainLink(AstNode $node): string
    {
        $autolinkText = $this->autolinkRenderText($node);
        if ($autolinkText !== null) {
            return $autolinkText;
        }

        return $this->renderPlainInlines($node->children);
    }

    private function renderPlainImage(AstNode $node): string
    {
        $link = new AstNode(
            'link',
            $this->imageLinkAttrs($node),
            $this->imageLabelNodesForLink($node)
        );

        return '[' . $this->renderPlainLink($link) . ']';
    }

    private function renderPlainRawInline(AstNode $node): string
    {
        [$format, $text] = $this->rawInlineFormatAndText($node);

        return $format === 'plain' ? $text : '';
    }

    private function renderCode(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');
        $longestTickRun = 0;
        if (preg_match_all('/`+/', $text, $matches) !== false) {
            foreach ($matches[0] as $run) {
                $longestTickRun = max($longestTickRun, strlen($run));
            }
        }

        $marker = str_repeat('`', $longestTickRun + 1);
        $spacer = $longestTickRun === 0 ? '' : ' ';

        return $marker
            . $spacer
            . $text
            . $spacer
            . $marker
            . $this->renderAttributesTuple($this->linkAttrTuple($node));
    }

    private function renderLineBreak(): string
    {
        if ((bool) ($this->options['hardLineBreaks'] ?? false)) {
            return "\n";
        }

        if ($this->isCommonMarkVariant()) {
            return "\\\n";
        }

        if ((bool) ($this->options['escapedLineBreaks'] ?? true)) {
            return "\\\n";
        }

        return "  \n";
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderSoftBreak(array $following = []): string
    {
        $next = $following[0] ?? null;
        if (
            $this->opmlNoteMarkdownEnabled()
            && $next instanceof AstNode
            && $next->type === 'math'
            && (bool) $next->attr('display', false)
        ) {
            return "\n";
        }

        return $this->writerWrapText() === 'preserve' ? "\n" : ' ';
    }

    /**
     * @return list<string>
     */
    private function wrapOpmlMarkdownLines(string $text, int $columns): array
    {
        $lines = preg_split('/\R/u', $text);
        if ($lines === false) {
            return [$text];
        }

        $wrapped = [];
        foreach ($lines as $line) {
            if ($line === '' || $this->markdownDisplayWidth($line) <= $columns) {
                $wrapped[] = $line;
                continue;
            }

            array_push($wrapped, ...$this->wrapOpmlMarkdownLine($line, $columns));
        }

        return $wrapped;
    }

    /**
     * @return list<string>
     */
    private function wrapOpmlMarkdownLine(string $line, int $columns): array
    {
        $tokens = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || $tokens === []) {
            return [$line];
        }

        $lines = [];
        $current = '';
        foreach ($tokens as $token) {
            if ($current === '') {
                $current = $token;
                continue;
            }

            $candidate = $current . ' ' . $token;
            if ($this->markdownDisplayWidth($candidate) > $columns) {
                $lines[] = $current;
                $current = $token;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [$line] : $lines;
    }

    private function appendOpmlWrappedMarkdownLines(array &$lines, string $firstPrefix, string $continuationPrefix, string $text): void
    {
        $sourceLines = preg_split('/\R/u', $text);
        if ($sourceLines === false) {
            $sourceLines = [$text];
        }

        $isFirstOutputLine = true;
        foreach ($sourceLines as $sourceLine) {
            $prefix = $isFirstOutputLine ? $firstPrefix : $continuationPrefix;
            $width = max(1, $this->writerColumns() - $this->markdownDisplayWidth($prefix));
            $wrapped = $sourceLine === '' ? [''] : $this->wrapOpmlMarkdownLines($sourceLine, $width);
            foreach ($wrapped as $line) {
                $lines[] = rtrim(($isFirstOutputLine ? $firstPrefix : $continuationPrefix) . $line);
                $isFirstOutputLine = false;
            }
        }
    }

    private function renderEmph(AstNode $node): string
    {
        if (count($node->children) === 1 && $node->children[0]->type === 'emph') {
            return $this->renderInlines($node->children[0]->children);
        }

        return $this->delimitInlineContent('*', '*', $this->renderInlines($node->children));
    }

    private function renderScript(string $kind, string $delimiter, string $htmlTag, AstNode $node): string
    {
        if ($node->children === []) {
            return '';
        }

        if (!$this->isNullAttrTuple($this->linkAttrTuple($node))) {
            return $this->renderSpan($this->semanticInlineSpan($node, $kind));
        }

        $previous = $this->escapeInlineSpaces;
        $this->escapeInlineSpaces = true;
        try {
            $content = $this->renderInlines($node->children);
            if ($content === '') {
                return '';
            }

            if ($this->scriptEnabled($kind)) {
                return $this->delimitInlineContent($delimiter, $delimiter, $content);
            }

            if ($this->rawHtmlEnabled()) {
                return '<' . $htmlTag . '>' . $content . '</' . $htmlTag . '>';
            }

            if (!$this->writerPreferAscii()) {
                $converted = $this->unicodeScriptInlineNodes($node->children, $kind);
                if ($converted !== null) {
                    return $this->renderInlines($converted);
                }
            }

            $unicode = $this->unicodeScriptText($content, 'superscript');
            if ($unicode !== null) {
                return $unicode;
            }

            return ($kind === 'superscript' ? '^' : '_') . '(' . $content . ')';
        } finally {
            $this->escapeInlineSpaces = $previous;
        }
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>|null
     */
    private function unicodeScriptInlineNodes(array $nodes, string $kind): ?array
    {
        $converted = [];
        foreach ($nodes as $node) {
            $convertedNode = $this->unicodeScriptInlineNode($node, $kind);
            if ($convertedNode === null) {
                return null;
            }
            $converted[] = $convertedNode;
        }

        return $converted;
    }

    private function unicodeScriptInlineNode(AstNode $node, string $kind): ?AstNode
    {
        if ($node->type === 'text') {
            $text = $this->unicodeScriptText((string) $node->attr('text', ''), $kind);

            return $text === null ? null : new AstNode('text', ['text' => $text]);
        }

        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            return $node;
        }

        if ($node->type === 'span') {
            $children = $this->unicodeScriptInlineNodes($node->children, $kind);

            return $children === null ? null : new AstNode('span', $node->attrs, $children);
        }

        return null;
    }

    private function unicodeScriptText(string $text, string $kind): ?string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return null;
        }

        $converted = '';
        foreach ($chars as $char) {
            $scriptChar = $kind === 'subscript'
                ? $this->unicodeSubscriptChar($char)
                : $this->unicodeSuperscriptChar($char);
            if ($scriptChar === null) {
                return null;
            }
            $converted .= $scriptChar;
        }

        return $converted;
    }

    private function unicodeSuperscriptChar(string $char): ?string
    {
        $map = [
            '0' => "\u{2070}",
            '1' => "\u{00B9}",
            '2' => "\u{00B2}",
            '3' => "\u{00B3}",
            '4' => "\u{2074}",
            '5' => "\u{2075}",
            '6' => "\u{2076}",
            '7' => "\u{2077}",
            '8' => "\u{2078}",
            '9' => "\u{2079}",
            '+' => "\u{207A}",
            '-' => "\u{207B}",
            "\u{2212}" => "\u{207B}",
            '=' => "\u{207C}",
            '(' => "\u{207D}",
            ')' => "\u{207E}",
        ];

        return $map[$char] ?? (preg_match('/^\s$/u', $char) === 1 ? $char : null);
    }

    private function unicodeSubscriptChar(string $char): ?string
    {
        $map = [
            '0' => "\u{2080}",
            '1' => "\u{2081}",
            '2' => "\u{2082}",
            '3' => "\u{2083}",
            '4' => "\u{2084}",
            '5' => "\u{2085}",
            '6' => "\u{2086}",
            '7' => "\u{2087}",
            '8' => "\u{2088}",
            '9' => "\u{2089}",
            '+' => "\u{208A}",
            '-' => "\u{208B}",
            '=' => "\u{208C}",
            '(' => "\u{208D}",
            ')' => "\u{208E}",
        ];

        return $map[$char] ?? (preg_match('/^\s$/u', $char) === 1 ? $char : null);
    }

    private function renderStrikeout(AstNode $node): string
    {
        $content = $this->renderInlines($node->children);
        if ($content === '') {
            return '';
        }

        if (!$this->isNullAttrTuple($this->linkAttrTuple($node))) {
            return $this->renderSpan($this->semanticInlineSpan($node, 'strikeout'));
        }

        if ($this->strikeoutEnabled()) {
            return $this->delimitInlineContent('~~', '~~', $content);
        }

        if ($this->rawHtmlEnabled()) {
            return '<s>' . $content . '</s>';
        }

        return $content;
    }

    private function renderUnderline(AstNode $node): string
    {
        if ($node->children === []) {
            return '';
        }

        $span = $this->semanticInlineSpan($node, 'underline');

        if ($this->bracketedSpansEnabled() || $this->nativeSpansEnabled()) {
            return $this->renderSpan($span);
        }

        if ($this->rawHtmlEnabled()) {
            return '<u>' . $this->renderInlines($node->children) . '</u>';
        }

        return $this->delimitInlineContent('*', '*', $this->renderInlines($node->children));
    }

    private function renderSmallCaps(AstNode $node): string
    {
        if ($this->rawHtmlEnabled() || $this->nativeSpansEnabled()) {
            return $this->renderSpan($this->semanticInlineSpan($node, 'smallcaps'));
        }

        return $this->renderInlines($this->capitalizeInlineText($node->children));
    }

    private function semanticInlineSpan(AstNode $node, string $class): AstNode
    {
        $attrs = $this->linkAttrTuple($node);
        $attrs['classes'] = array_values(array_unique(array_merge([$class], $attrs['classes'])));

        return new AstNode('span', $attrs, $node->children);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function capitalizeInlineText(array $nodes): array
    {
        $capitalized = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text') {
                $attrs = $node->attrs;
                $attrs['text'] = mb_strtoupper((string) ($attrs['text'] ?? ''), 'UTF-8');
                $capitalized[] = new AstNode($node->type, $attrs, $node->children);
                continue;
            }

            $capitalized[] = new AstNode($node->type, $node->attrs, $this->capitalizeInlineText($node->children));
        }

        return $capitalized;
    }

    private function renderQuoted(AstNode $node): string
    {
        $content = $this->renderInlines($node->children);
        if ($this->smartEnabled()) {
            $delimiter = $node->attr('kind') === 'single' ? "'" : '"';

            return $delimiter . $content . $delimiter;
        }

        if ($node->attr('kind') === 'single') {
            $left = $this->writerPreferAscii() ? '&lsquo;' : "\u{2018}";
            $right = $this->writerPreferAscii() ? '&rsquo;' : "\u{2019}";
        } else {
            $left = $this->writerPreferAscii() ? '&ldquo;' : "\u{201C}";
            $right = $this->writerPreferAscii() ? '&rdquo;' : "\u{201D}";
        }

        return $left . $content . $right;
    }

    private function renderCitation(AstNode $node): string
    {
        $rendered = $node->attr('rendered', null);
        if (is_scalar($rendered) && (string) $rendered !== '') {
            return (string) $rendered;
        }

        $citations = $this->citationEntries($node);
        if ($citations === []) {
            return $node->attr('text', null) !== null
                ? (string) $node->attr('text', '')
                : $this->renderInlines($node->children);
        }

        $first = $citations[0];
        if ($this->citationMode($first) === 'author_in_text') {
            $locator = $this->renderCitationAffix($first['locator'] ?? []);
            $suffix = $this->renderCitationAffix($first['suffix'] ?? []);
            $rest = $this->renderCitationEntries(array_slice($citations, 1));
            $inside = $suffix;
            if ($inside !== '' && $rest !== '') {
                $inside .= ';';
            }
            if ($rest !== '') {
                $inside = $this->joinInlinePartsWithSpace($inside, $rest);
            }

            return '@' . $this->renderCitationKey((string) ($first['id'] ?? ''))
                . ($locator === '' ? '' : $this->renderCitationLocatorSuffix($locator))
                . ($inside === '' ? '' : ' [' . $inside . ']');
        }

        return '[' . $this->renderCitationEntries($citations) . ']';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function citationEntries(AstNode $node): array
    {
        if ($node->type === 'citation_group') {
            $entries = [];
            foreach ($node->children as $child) {
                if ($child->type === 'citation') {
                    $entries[] = $this->citationEntryFromNode($child);
                }
            }

            return $entries;
        }

        $citations = $node->attr('citations', null);
        if (is_array($citations) && $citations !== []) {
            $entries = [];
            foreach ($citations as $citation) {
                if ($citation instanceof AstNode) {
                    $entries[] = $this->citationEntryFromNode($citation);
                } elseif (is_array($citation)) {
                    $entries[] = $citation;
                }
            }

            return $entries;
        }

        if (array_key_exists('id', $node->attrs)) {
            return [$this->citationEntryFromNode($node)];
        }

        $id = (string) $node->attr('id', '');

        return $id === '' ? [] : [$this->citationEntryFromNode($node)];
    }

    /**
     * @return array<string, mixed>
     */
    private function citationEntryFromNode(AstNode $node): array
    {
        return [
            'id' => (string) $node->attr('id', ''),
            'mode' => (string) $node->attr('mode', 'normal'),
            'prefix' => $node->attr('prefix', []),
            'locator' => $node->attr('locator', []),
            'suffix' => $node->attr('suffix', []),
        ];
    }

    /**
     * @param list<array<string, mixed>> $citations
     */
    private function renderCitationEntries(array $citations): string
    {
        $rendered = [];
        foreach ($citations as $citation) {
            $entry = $this->renderCitationEntry($citation);
            if ($entry !== '') {
                $rendered[] = $entry;
            }
        }

        return implode('; ', $rendered);
    }

    /**
     * @param array<string, mixed> $citation
     */
    private function renderCitationEntry(array $citation): string
    {
        $prefix = $this->renderCitationAffix($citation['prefix'] ?? []);
        $locator = $this->renderCitationAffix($citation['locator'] ?? []);
        $suffix = $this->renderCitationAffix($citation['suffix'] ?? []);
        $key = ($this->citationMode($citation) === 'suppress_author' ? '-' : '')
            . '@'
            . $this->renderCitationKey((string) ($citation['id'] ?? ''));

        if ($locator !== '') {
            $key .= $this->renderCitationLocatorSuffix($locator);
        }
        if ($suffix !== '') {
            $first = mb_substr($suffix, 0, 1, 'UTF-8');
            $key .= ($first === ' ' || in_array($first, [',', ';', ']', '@'], true))
                ? $suffix
                : ' ' . $suffix;
        }

        return $this->joinInlinePartsWithSpace($prefix, $key);
    }

    /**
     * @param array<string, mixed> $citation
     */
    private function citationMode(array $citation): string
    {
        $mode = strtolower((string) ($citation['mode'] ?? 'normal'));

        return match ($mode) {
            'authorintext', 'author_in_text', 'author-in-text' => 'author_in_text',
            'suppressauthor', 'suppress_author', 'suppress-author' => 'suppress_author',
            default => 'normal',
        };
    }

    private function renderCitationAffix(mixed $value): string
    {
        if (is_string($value)) {
            return $this->renderInlines([new AstNode('text', ['text' => $value])]);
        }

        if (is_array($value)) {
            $nodes = [];
            foreach ($value as $inline) {
                if ($inline instanceof AstNode) {
                    $nodes[] = $inline;
                } elseif (is_string($inline)) {
                    $nodes[] = new AstNode('text', ['text' => $inline]);
                }
            }

            return $this->renderInlines($nodes);
        }

        return '';
    }

    private function renderCitationKey(string $id): string
    {
        return preg_match('/^[A-Za-z0-9_:.#\/$%&+?<>~|-]*[A-Za-z0-9_#\/$%&+?<>~|-]$/u', $id) === 1
            ? $id
            : '{' . $this->escapeBracedCitationKey($id) . '}';
    }

    private function renderCitationLocatorSuffix(string $locator): string
    {
        $first = mb_substr($locator, 0, 1, 'UTF-8');

        return in_array($first, [',', ';'], true) ? $locator : ', ' . $locator;
    }

    private function escapeBracedCitationKey(string $id): string
    {
        return strtr($id, [
            '\\' => '\\\\',
            '[' => '\\[',
            ']' => '\\]',
            '}' => '\\}',
        ]);
    }

    private function joinInlinePartsWithSpace(string $left, string $right): string
    {
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }

        return $left . ' ' . $right;
    }

    private function renderMath(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');
        if ((bool) $node->attr('display', false)) {
            return '$$' . $text . '$$';
        }

        return '$' . trim($text) . '$';
    }

    private function renderRawAttributeInline(AstNode $node): string
    {
        $format = (string) $node->attr('format', '');
        $text = (string) $node->attr('text', '');
        if ($format === '') {
            return $text;
        }

        $longestTickRun = 0;
        if (preg_match_all('/`+/', $text, $matches) !== false) {
            foreach ($matches[0] as $run) {
                $longestTickRun = max($longestTickRun, strlen($run));
            }
        }
        $marker = str_repeat('`', $longestTickRun + 1);

        return $marker . $text . $marker . '{=' . $format . '}';
    }

    private function renderRawInline(AstNode $node): string
    {
        [$format, $text] = $this->rawInlineFormatAndText($node);

        if ($format === '') {
            return $text;
        }

        if ($this->isCommonMarkVariant()) {
            if ($this->isRawMarkdownFormat($format)) {
                return $text;
            }
        } elseif ($this->isRawMarkdownFormat($format)) {
            return $text;
        }

        if ($this->rawAttributeEnabled()) {
            return $this->renderRawAttributeInline(new AstNode($node->type, [
                'format' => $format,
                'text' => $text,
            ]));
        }

        if ($this->isRawHtmlFormat($format) && $this->rawHtmlEnabled()) {
            return $text;
        }

        if (in_array($format, ['latex', 'tex'], true) && $this->rawTexEnabled()) {
            return $text;
        }

        return '';
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function rawInlineFormatAndText(AstNode $node): array
    {
        $format = $this->rawNodeFormat($node);

        if ($node->type === 'raw_tex' || $node->type === 'raw_tex_inline') {
            return [$format === '' ? 'tex' : $format, $this->rawNodeText($node, ['text', 'tex', 'literal', 'content', 'value', 'raw'])];
        }

        if ($node->type === 'raw_html_inline') {
            return [$format === '' ? 'html' : $format, $this->rawNodeText($node, ['text', 'html', 'content', 'literal', 'value', 'raw'])];
        }

        if ($node->type === 'raw_markdown') {
            return [$format === '' ? 'markdown' : $format, $this->rawNodeText($node, ['text', 'markdown', 'value', 'literal', 'content', 'raw'])];
        }

        return [$format, $this->rawNodeText($node, ['text', 'markdown', 'html', 'content', 'literal', 'value', 'raw'])];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function rawBlockFormatAndText(AstNode $node): array
    {
        $format = $this->rawNodeFormat($node);

        if ($node->type === 'raw_html') {
            return [$format === '' ? 'html' : $format, $this->rawNodeText($node, ['text', 'html', 'raw', 'content', 'literal', 'value'])];
        }

        if ($node->type === 'raw_tex') {
            return [$format === '' ? 'tex' : $format, $this->rawNodeText($node, ['text', 'tex', 'raw', 'content', 'literal', 'value'])];
        }

        if ($node->type === 'raw_markdown') {
            return [$format === '' ? 'markdown' : $format, $this->rawNodeText($node, ['text', 'markdown', 'raw', 'content', 'literal', 'value'])];
        }

        return [$format, $this->rawNodeText($node, ['text', 'markdown', 'html', 'tex', 'raw', 'content', 'literal', 'value'])];
    }

    private function rawNodeFormat(AstNode $node): string
    {
        foreach (['format', 'raw_format', 'format_name'] as $name) {
            $format = $node->attr($name, null);
            if (is_string($format) && $format !== '') {
                return $format;
            }
        }

        return '';
    }

    /**
     * @param list<string> $names
     */
    private function rawNodeText(AstNode $node, array $names): string
    {
        foreach ($names as $name) {
            $text = $node->attr($name, null);
            if (is_string($text)) {
                return $text;
            }
        }

        return '';
    }

    private function isRawHtmlFormat(string $format): bool
    {
        $normalized = strtolower(str_replace('-', '+', $format));
        $baseFormat = explode('+', $normalized, 2)[0];

        return in_array($normalized, ['html', 'html4', 'html5', 'xhtml'], true)
            || in_array($baseFormat, ['html', 'html4', 'html5', 'xhtml'], true);
    }

    private function isRawMarkdownFormat(string $format): bool
    {
        return MarkdownFormatProfile::canonicalMarkdownFormat($format) !== null;
    }

    private function renderMark(AstNode $node): string
    {
        return '==' . $this->renderInlines($node->children) . '==';
    }

    private function renderSpan(AstNode $node): string
    {
        $attrTuple = $this->linkAttrTuple($node);
        $classes = $attrTuple['classes'];
        $attributes = $attrTuple['attributes'];
        if (
            $attrTuple['id'] === ''
            && in_array($classes, [['mark'], ['marked'], ['highlighted']], true)
            && $attributes === []
        ) {
            return $this->renderMark($node);
        }

        if (
            $attrTuple['id'] === ''
            && $classes === ['emoji']
            && isset($attributes['data-emoji'])
            && count($node->children) === 1
            && $node->children[0]->type === 'text'
        ) {
            return ':' . (string) $attributes['data-emoji'] . ':';
        }
        if (
            $attrTuple['id'] === ''
            && $classes === ['gemoji']
            && isset($attributes['shortcode'])
            && is_string($attributes['shortcode'])
            && preg_match('/^:[A-Za-z0-9_+-]+:$/D', $attributes['shortcode']) === 1
            && count($node->children) === 1
            && $node->children[0]->type === 'text'
            && $this->emojiShortcodesEnabled()
        ) {
            return $attributes['shortcode'];
        }

        $attrs = $this->renderAttributesTuple($attrTuple);
        $content = $this->renderInlines($node->children);

        if ($attrs === '') {
            return $content;
        }

        if ($this->bracketedSpansEnabled()) {
            return '[' . $content . ']' . $attrs;
        }

        if ($this->rawHtmlEnabled() || $this->nativeSpansEnabled()) {
            return $this->renderRawHtmlSpan($attrTuple, $content);
        }

        return $content;
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderLink(AstNode $node, array $following): string
    {
        $autolinkText = $this->autolinkRenderText($node);
        if ($autolinkText !== null) {
            return '<' . $autolinkText . '>';
        }

        $wikilink = $this->renderWikiLink($node);
        if ($wikilink !== null) {
            return $wikilink;
        }

        if ((bool) ($this->options['referenceLinks'] ?? false)) {
            return $this->renderReferenceLink($node, $following);
        }

        if ($this->shouldRenderRawHtmlFallback($this->linkAttrTuple($node))) {
            return $this->renderRawHtmlLink($node);
        }

        $title = $this->linkTitle($node);
        $titleMarkdown = $title === '' ? '' : ' "' . $this->escapeLinkTitle($title) . '"';
        $attrTuple = $this->linkAttrTuple($node);
        $destination = in_array('wikilink', $attrTuple['classes'], true)
            ? $this->linkUrl($node)
            : $this->renderLinkDestination($this->linkUrl($node));

        return '[' . $this->renderInlines($node->children) . ']('
            . $destination
            . $titleMarkdown
            . ')'
            . $this->renderLinkAttributes($node);
    }

    private function renderWikiLink(AstNode $node): ?string
    {
        $position = $this->wikiLinkTitlePosition();
        if ($position === '' || !$this->isRenderableWikiLink($node)) {
            return null;
        }

        $target = $this->linkUrl($node);
        $label = $this->plainInlineText($node->children);
        if ($target === '' || $label === '') {
            return null;
        }

        $targetComponent = $this->escapeWikiLinkComponent($target);
        if ($label === $target) {
            return '[[' . $targetComponent . ']]';
        }

        $labelComponent = $this->escapeWikiLinkComponent($label);

        return $position === 'before'
            ? '[[' . $labelComponent . '|' . $targetComponent . ']]'
            : '[[' . $targetComponent . '|' . $labelComponent . ']]';
    }

    private function wikiLinkTitlePosition(): string
    {
        $variant = (string) ($this->options['variant'] ?? '');
        $overrides = $this->markdownExtensionOverrides();
        if (
            (bool) ($this->options['wikilinksTitleBeforePipe'] ?? false)
            || str_contains($variant, 'wikilinks_title_before_pipe')
            || ($overrides['wikilinks_title_before_pipe'] ?? null) === true
        ) {
            return 'before';
        }

        if (
            (bool) ($this->options['wikilinksTitleAfterPipe'] ?? false)
            || str_contains($variant, 'wikilinks_title_after_pipe')
            || ($overrides['wikilinks_title_after_pipe'] ?? null) === true
        ) {
            return 'after';
        }

        if (($overrides['wikilinks'] ?? null) === false) {
            return '';
        }

        if (($overrides['wikilinks'] ?? null) === true) {
            return 'before';
        }

        if (
            array_key_exists('wikilinks_title_before_pipe', $overrides)
            || array_key_exists('wikilinks_title_after_pipe', $overrides)
        ) {
            return '';
        }

        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $canonical = MarkdownFormatProfile::canonicalFormat($format);
        if (in_array($canonical, ['markdown', 'commonmark_x'], true)) {
            return 'before';
        }

        return '';
    }

    private function isRenderableWikiLink(AstNode $node): bool
    {
        if ($this->linkTitle($node) !== '') {
            return false;
        }

        $attrs = $this->linkAttrTuple($node);
        if ($attrs['id'] !== '' || $attrs['attributes'] !== []) {
            return false;
        }

        $classes = $attrs['classes'];
        if (!in_array('wikilink', $classes, true)) {
            return false;
        }

        if (array_values(array_diff($classes, ['wikilink'])) !== []) {
            return false;
        }

        foreach ($node->children as $child) {
            if (!in_array($child->type, ['text', 'code', 'softbreak', 'linebreak'], true)) {
                return false;
            }
        }

        return true;
    }

    private function escapeWikiLinkComponent(string $value): string
    {
        return $this->escapeHtml(strtr($value, [
            '\\' => '\\\\',
            ']' => '\\]',
            '|' => '\\|',
        ]));
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderImage(AstNode $node, array $following): string
    {
        if ($this->shouldRenderRawHtmlFallback($this->imageAttrTuple($node))) {
            return $this->renderRawHtmlImage($node);
        }

        return '!' . $this->renderLink(
            new AstNode('link', $this->imageLinkAttrs($node), $this->imageLabelNodesForLink($node)),
            $following
        );
    }

    private function linkUrl(AstNode $node): string
    {
        return $this->targetUrlFromAttrs($node->attrs, [
            'url',
            'href',
            'uri',
            'src',
            'targetUrl',
            'destinationUrl',
            'sourceUrl',
            'linkUrl',
        ]);
    }

    private function linkTitle(AstNode $node): string
    {
        return $this->targetTitleFromAttrs($node->attrs, [
            'title',
            'targetTitle',
            'destinationTitle',
            'sourceTitle',
            'linkTitle',
            'titleText',
            'tooltip',
        ]);
    }

    private function imageUrl(AstNode $node): string
    {
        return $this->targetUrlFromAttrs($node->attrs, [
            'url',
            'src',
            'href',
            'uri',
            'imageUrl',
            'sourceUrl',
            'targetUrl',
            'destinationUrl',
        ]);
    }

    private function imageTitle(AstNode $node): string
    {
        return $this->targetTitleFromAttrs($node->attrs, [
            'title',
            'imageTitle',
            'sourceTitle',
            'targetTitle',
            'destinationTitle',
            'titleText',
            'tooltip',
        ]);
    }

    private function imageAltText(AstNode $node): string
    {
        return $this->firstStringAttr($node->attrs, ['alt', 'altText', 'alternateText', 'description']);
    }

    /**
     * @param array<string, mixed> $attrs
     * @param list<string> $names
     */
    private function targetUrlFromAttrs(array $attrs, array $names): string
    {
        $direct = $this->firstStringAttr($attrs, $names);
        if ($direct !== '') {
            return $direct;
        }

        foreach (['target', 'destination'] as $name) {
            if (!array_key_exists($name, $attrs)) {
                continue;
            }

            $target = $attrs[$name];
            if (is_string($target) || is_numeric($target)) {
                return (string) $target;
            }

            if (is_array($target)) {
                if (array_key_exists(0, $target) && (is_string($target[0]) || is_numeric($target[0]))) {
                    return (string) $target[0];
                }

                $nested = $this->firstStringAttr($target, $names);
                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $attrs
     * @param list<string> $names
     */
    private function targetTitleFromAttrs(array $attrs, array $names): string
    {
        $direct = $this->firstStringAttr($attrs, $names);
        if ($direct !== '') {
            return $direct;
        }

        foreach (['target', 'destination'] as $name) {
            $target = $attrs[$name] ?? null;
            if (!is_array($target)) {
                continue;
            }

            if (array_key_exists(1, $target) && (is_string($target[1]) || is_numeric($target[1]))) {
                return (string) $target[1];
            }

            $nested = $this->firstStringAttr($target, $names);
            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $attrs
     * @param list<string> $names
     */
    private function firstStringAttr(array $attrs, array $names): string
    {
        foreach ($names as $name) {
            $value = $attrs[$name] ?? null;
            if (is_string($value) || is_numeric($value)) {
                $value = (string) $value;
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @param list<AstNode> $following
     */
    private function renderReferenceLink(AstNode $node, array $following): string
    {
        $labelText = $this->renderInlines($node->children);
        $plainLabel = $this->normalizeReferenceLabelText($this->plainInlineText($node->children));
        $referenceLabel = $this->registerReference(
            $plainLabel,
            $this->linkUrl($node),
            $this->linkTitle($node),
            $this->linkAttrTuple($node)
        );

        $shortcutable = $referenceLabel === $plainLabel && $this->canUseShortcutReference($following);
        if ($shortcutable) {
            return '[' . $labelText . ']';
        }

        $suffix = $referenceLabel === $plainLabel ? '[]' : '[' . $referenceLabel . ']';

        return '[' . $labelText . ']' . $suffix;
    }

    private function renderNoteReference(AstNode $node): string
    {
        $label = $this->registerNote($node);

        return '[^' . $label . ']';
    }

    private function renderPlainNoteReference(AstNode $node): string
    {
        return '[' . $this->registerNote($node) . ']';
    }

    private function registerNote(AstNode $node): string
    {
        $label = $this->actualNoteLabel($this->preferredNoteLabel($node));
        $this->notes[] = [
            'label' => $label,
            'node' => $node,
        ];

        return $label;
    }

    private function preferredNoteLabel(AstNode $node): string
    {
        $label = $node->attr('label', $node->attr('noteLabel', $node->attr('identifier', '')));
        if (!is_scalar($label)) {
            return '';
        }

        return trim((string) $label);
    }

    private function actualNoteLabel(string $preferredLabel): string
    {
        if ($this->requiresGeneratedNoteLabel($preferredLabel)) {
            return $this->nextGeneratedNoteLabel();
        }

        $key = strtolower($preferredLabel);
        $use = $this->noteLabelUses[$key] ?? 0;
        $this->noteLabelUses[$key] = $use + 1;
        if ($use === 0 && !isset($this->noteUsedLabels[$key])) {
            $this->noteUsedLabels[$key] = true;

            return $preferredLabel;
        }

        $suffix = $use + 1;
        do {
            $candidate = $preferredLabel . '-' . $suffix;
            $candidateKey = strtolower($candidate);
            $suffix++;
        } while (isset($this->noteUsedLabels[$candidateKey]));

        $this->noteUsedLabels[$candidateKey] = true;

        return $candidate;
    }

    private function requiresGeneratedNoteLabel(string $label): bool
    {
        return $label === ''
            || preg_match('/\s/u', $label) === 1
            || str_contains($label, '[')
            || str_contains($label, ']');
    }

    private function nextGeneratedNoteLabel(): string
    {
        do {
            $candidate = (string) $this->nextNoteNumber++;
            $candidateKey = strtolower($candidate);
        } while (isset($this->noteUsedLabels[$candidateKey]));

        $this->noteUsedLabels[$candidateKey] = true;

        return $candidate;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function registerReference(string $suggestedLabel, string $url, string $title, array $attrs): string
    {
        $targetKey = $url . "\0" . $title . "\0" . $this->attributeSignature($attrs);
        if (isset($this->referenceTargetLabels[$targetKey])) {
            return $this->referenceTargetLabels[$targetKey];
        }

        $label = $this->normalizeReferenceLabelText($suggestedLabel);
        if ($this->requiresGeneratedReferenceLabel($label)) {
            $actualLabel = $this->nextGeneratedReferenceLabel();
        } else {
            $key = strtolower($label);
            $use = $this->referenceLabelUses[$key] ?? 0;
            $this->referenceLabelUses[$key] = $use + 1;
            $actualLabel = $use === 0 && !isset($this->referenceUsedLabels[$key])
                ? $label
                : $this->nextGeneratedReferenceLabel();
        }

        $this->referenceUsedLabels[strtolower($actualLabel)] = true;
        $this->referenceTargetLabels[$targetKey] = $actualLabel;
        $this->references[] = [
            'label' => $actualLabel,
            'url' => $url,
            'title' => $title,
            'attrs' => $attrs,
        ];

        return $actualLabel;
    }

    private function requiresGeneratedReferenceLabel(string $label): bool
    {
        return $label === ''
            || strlen($label) > 999
            || str_contains($label, '[')
            || str_contains($label, ']');
    }

    private function nextGeneratedReferenceLabel(): string
    {
        do {
            $this->lastReferenceIndex++;
            $candidate = (string) $this->lastReferenceIndex;
        } while (isset($this->referenceUsedLabels[strtolower($candidate)]));

        return $candidate;
    }

    /**
     * @param list<AstNode> $following
     */
    private function canUseShortcutReference(array $following): bool
    {
        $next = $following[0] ?? null;
        if ($next === null) {
            return true;
        }

        if ($next->type === 'link' || $next->type === 'citation') {
            return false;
        }

        if ($next->type === 'space' || $next->type === 'softbreak' || $next->type === 'linebreak') {
            return $this->canUseShortcutReferenceAfterWhitespace(array_slice($following, 1));
        }

        if ($next->type === 'raw_inline' || $next->type === 'raw_markdown' || $next->type === 'raw_html_inline') {
            return !$this->startsWithReferenceSuffixConflict((string) $next->attr(
                'text',
                $next->attr('markdown', $next->attr('html', ''))
            ));
        }

        if ($next->type !== 'text') {
            return true;
        }

        $text = (string) $next->attr('text', '');
        if ($text === '') {
            return $this->canUseShortcutReference(array_slice($following, 1));
        }

        if ($this->startsWithReferenceSuffixConflict($text)) {
            return false;
        }

        $withoutLeadingSpace = ltrim($text, " \t\r\n");
        if ($withoutLeadingSpace !== $text) {
            if ($withoutLeadingSpace !== '') {
                return !$this->startsWithReferenceSuffixConflict($withoutLeadingSpace);
            }

            return $this->canUseShortcutReferenceAfterWhitespace(array_slice($following, 1));
        }

        return true;
    }

    /**
     * @param list<AstNode> $following
     */
    private function canUseShortcutReferenceAfterWhitespace(array $following): bool
    {
        $next = $following[0] ?? null;
        if ($next === null) {
            return true;
        }

        if ($next->type === 'link' || $next->type === 'citation') {
            return false;
        }

        if ($next->type === 'space' || $next->type === 'softbreak' || $next->type === 'linebreak') {
            return $this->canUseShortcutReferenceAfterWhitespace(array_slice($following, 1));
        }

        if ($next->type === 'text') {
            $text = (string) $next->attr('text', '');

            return $text === '' || !$this->startsWithReferenceSuffixConflict($text);
        }

        if ($next->type === 'raw_inline' || $next->type === 'raw_markdown' || $next->type === 'raw_html_inline') {
            $raw = (string) $next->attr('text', $next->attr('markdown', $next->attr('html', '')));

            return !$this->startsWithReferenceSuffixConflict($raw);
        }

        return true;
    }

    private function startsWithReferenceSuffixConflict(string $text): bool
    {
        $trimmed = ltrim($text, " \t\r\n");

        return str_starts_with($trimmed, '[')
            || str_starts_with($trimmed, '{')
            || str_starts_with($trimmed, '(')
            || str_starts_with($trimmed, ':');
    }

    private function delimitInlineContent(string $opener, string $closer, string $content): string
    {
        if ($content === '') {
            return '';
        }

        $leading = '';
        if (preg_match('/^\s+/u', $content, $match) === 1) {
            $leading = $match[0];
            $content = substr($content, strlen($leading));
        }

        $trailing = '';
        if (preg_match('/\s+$/u', $content, $match) === 1) {
            $trailing = $match[0];
            $content = substr($content, 0, strlen($content) - strlen($trailing));
        }

        return $leading . $opener . $content . $closer . $trailing;
    }

    /**
     * @param list<AstNode> $following
     */
    private function escapeText(string $text, array $following = [], bool $escapeInitialPlainMarker = false, bool $preserveSmartPunctuation = false): string
    {
        $escaped = '';
        $length = strlen($text);
        $plainMarkerPrefixLength = $escapeInitialPlainMarker
            ? $this->initialPlainMarkerPrefixLength($text, $following)
            : 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            $tail = substr($text, $i);

            if ($i < $plainMarkerPrefixLength && str_contains('.()+-%', $char)) {
                $escaped .= '\\' . $char;
                continue;
            }

            if ($i === 0 && $char === '#' && $this->startsWithAtxHeadingMarker($text)) {
                $escaped .= '\\#';
                continue;
            }

            if ($i === 0 && $char === '@' && isset($text[$i + 1]) && preg_match('/[A-Za-z0-9_{]/', $text[$i + 1]) === 1) {
                $escaped .= '\\@';
                continue;
            }

            if ($this->smartEnabled() && str_starts_with($tail, '...')) {
                $escaped .= '\\...';
                $i += 2;
                continue;
            }

            if ($this->smartEnabled() && str_starts_with($tail, '--')) {
                $escaped .= '\\--';
                $i++;
                continue;
            }

            if (str_starts_with($tail, '==')) {
                $escaped .= '\\==';
                $i++;
                continue;
            }

            if (str_starts_with($tail, ':::' )) {
                $colonRun = strspn($tail, ':');
                $escaped .= '\\' . str_repeat(':', $colonRun);
                $i += $colonRun - 1;
                continue;
            }

            if (str_starts_with($tail, '![')) {
                $escaped .= '\\![';
                $i++;
                continue;
            }

            if (str_starts_with($tail, '~~')) {
                $escaped .= '\\~~';
                $i++;
                continue;
            }

            if ($char === '!' && $i === $length - 1 && $this->nextInlineStartsBracketed($following)) {
                $escaped .= '\\!';
                continue;
            }

            if ($char === '&' && preg_match('/^&(?:#[0-9]+|#x[0-9A-Fa-f]+|[A-Za-z][A-Za-z0-9]+);/', $tail) === 1) {
                $escaped .= '\\&';
                continue;
            }

            if ($char === '\\') {
                $escaped .= '\\\\';
                continue;
            }

            if ($char === ' ' && $this->escapeInlineSpaces) {
                $escaped .= '\\ ';
                continue;
            }

            if ($char === '_' && $this->isIntrawordUnderscore($text, $i)) {
                $escaped .= '_';
                continue;
            }

            if (($char === '\'' || $char === '"') && $this->smartEnabled()) {
                $escaped .= '\\' . $char;
                continue;
            }

            $escaped .= match ($char) {
                '[', ']', '`', '*', '_', '|', '^', '~', '$' => '\\' . $char,
                '>', '<' => '\\' . $char,
                default => $char,
            };
        }

        if ($this->smartEnabled() && !$preserveSmartPunctuation) {
            $escaped = $this->unsmartifyText($escaped);
        }

        return $this->writerPreferAscii()
            ? $this->toHtml5Entities($escaped)
            : $escaped;
    }

    /**
     * @param list<AstNode> $following
     */
    private function initialPlainMarkerPrefixLength(string $text, array $following): int
    {
        if ($text === '') {
            return 0;
        }

        if (($text[0] === '+' || $text[0] === '-') && $this->plainMarkerIsBoundary($text, 1, $following)) {
            return 1;
        }

        if ($text[0] === '%' && $this->plainMarkerIsBoundary($text, 1, $following)) {
            return 1;
        }

        if (preg_match('/^#([.)])(?=$|\s)/', $text) === 1) {
            return 2;
        }

        if (preg_match('/^\(([0-9]{1,9}|[A-Za-z]+)\)(?=$|\s)/', $text, $match) === 1) {
            return $this->writerOrderedMarkerOrdinal($match[1], 'two_parens', $this->plainMarkerSpacesAfter($text, strlen($match[0]))) === null
                ? 0
                : strlen($match[0]);
        }

        if (preg_match('/^(\d{1,9})([.)])(?=$|\s)/', $text, $match) === 1) {
            return strlen($match[0]);
        }

        if (preg_match('/^([A-Za-z]+)([.)])(?=$|\s)/', $text, $match) === 1) {
            $delimiter = $match[2] === ')' ? 'one_paren' : 'period';

            return $this->writerOrderedMarkerOrdinal($match[1], $delimiter, $this->plainMarkerSpacesAfter($text, strlen($match[0]))) === null
                ? 0
                : strlen($match[0]);
        }

        return 0;
    }

    /**
     * @param list<AstNode> $following
     */
    private function plainMarkerIsBoundary(string $text, int $offset, array $following): bool
    {
        if (!isset($text[$offset])) {
            return $following === [] || $this->followingInlineStartsWithSpace($following);
        }

        return preg_match('/\s/u', $text[$offset]) === 1;
    }

    /**
     * @param list<AstNode> $following
     */
    private function followingInlineStartsWithSpace(array $following): bool
    {
        $first = $following[0] ?? null;
        if (!$first instanceof AstNode) {
            return false;
        }

        if ($first->type === 'softbreak') {
            return true;
        }

        return $first->type === 'text'
            && preg_match('/^\s/u', (string) $first->attr('text', '')) === 1;
    }

    private function plainMarkerSpacesAfter(string $text, int $offset): int
    {
        if (preg_match('/^\s+/u', substr($text, $offset), $match) !== 1) {
            return 0;
        }

        return strlen($match[0]);
    }

    /**
     * @return array{start:int, style:string}|null
     */
    private function writerOrderedMarkerOrdinal(string $token, string $delimiter, int $spacesAfterMarker): ?array
    {
        if (ctype_digit($token)) {
            return ['start' => (int) $token, 'style' => 'decimal'];
        }

        if (!ctype_alpha($token)) {
            return null;
        }

        $roman = $delimiter === 'period' ? $this->romanToInteger($token) : null;
        if ($roman !== null && (strlen($token) > 1 || $spacesAfterMarker >= 2)) {
            return [
                'start' => $roman,
                'style' => ctype_upper($token) ? 'upper_roman' : 'lower_roman',
            ];
        }

        if (strlen($token) === 1 && ($delimiter !== 'period' || $spacesAfterMarker >= 2)) {
            return [
                'start' => ord(strtolower($token)) - ord('a') + 1,
                'style' => ctype_upper($token) ? 'upper_alpha' : 'lower_alpha',
            ];
        }

        return null;
    }

    private function romanToInteger(string $token): ?int
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

        return $total > 0 ? $total : null;
    }

    private function unsmartifyText(string $text): string
    {
        return strtr($text, [
            "\u{2019}" => "'",
            "\u{2026}" => '...',
            "\u{2013}" => '--',
            "\u{2014}" => '---',
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{2018}" => "'",
        ]);
    }

    private function toHtml5Entities(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return $text;
        }

        $encoded = '';
        foreach ($chars as $char) {
            if (strlen($char) === 1 && ord($char) < 0x80) {
                $encoded .= $char;
                continue;
            }

            $entity = $this->html5EntityName($char);
            if ($entity !== null) {
                $encoded .= '&' . $entity . ';';
                continue;
            }

            $codepoint = $this->unicodeCodepoint($char);
            $encoded .= $codepoint === null ? $char : '&#' . $codepoint . ';';
        }

        return $encoded;
    }

    private function html5EntityName(string $char): ?string
    {
        $pandocHtml5Names = [
            "\u{00A7}" => 'sect',
            "\u{00A9}" => 'COPY',
            "\u{00B2}" => 'sup2',
            "\u{00D7}" => 'times',
            "\u{00E9}" => 'eacute',
            "\u{00F6}" => 'ouml',
            "\u{00C0}" => 'Agrave',
            "\u{00C9}" => 'Eacute',
            "\u{00CE}" => 'Icirc',
            "\u{03B1}" => 'alpha',
            "\u{03C9}" => 'omega',
            "\u{2013}" => 'ndash',
            "\u{2014}" => 'mdash',
            "\u{2018}" => 'lsquo',
            "\u{2019}" => 'rsquo',
            "\u{201C}" => 'ldquo',
            "\u{201D}" => 'rdquo',
            "\u{2026}" => 'mldr',
            "\u{2122}" => 'TRADE',
            "\u{2190}" => 'larr',
            "\u{2192}" => 'rarr',
            "\u{2208}" => 'in',
            "\u{2260}" => 'ne',
            "\u{2264}" => 'le',
            "\u{2265}" => 'ge',
            "\u{27E8}" => 'lang',
            "\u{27E9}" => 'rang',
        ];

        if (isset($pandocHtml5Names[$char])) {
            return $pandocHtml5Names[$char];
        }

        $entity = htmlentities($char, ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false);
        if ($entity !== $char && preg_match('/^&([A-Za-z][A-Za-z0-9]+);$/', $entity, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private function unicodeCodepoint(string $char): ?int
    {
        if (function_exists('mb_ord')) {
            return mb_ord($char, 'UTF-8');
        }

        $encoded = mb_convert_encoding($char, 'UCS-4BE', 'UTF-8');
        $unpacked = unpack('N', $encoded);

        return is_array($unpacked) ? $unpacked[1] : null;
    }

    private function markdownDisplayWidth(string $text): int
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return strlen($text);
        }

        $width = 0;
        foreach ($chars as $char) {
            $codepoint = $this->unicodeCodepoint($char);
            $width += $codepoint === null ? strlen($char) : $this->unicodeDisplayCellWidth($codepoint);
        }

        return $width;
    }

    private function unicodeDisplayCellWidth(int $codepoint): int
    {
        if (
            $codepoint === 0
            || $codepoint < 32
            || ($codepoint >= 0x7F && $codepoint < 0xA0)
            || $this->isZeroWidthCodepoint($codepoint)
        ) {
            return 0;
        }

        return $this->isWideCodepoint($codepoint) ? 2 : 1;
    }

    private function isZeroWidthCodepoint(int $codepoint): bool
    {
        return ($codepoint >= 0x0300 && $codepoint <= 0x036F)
            || ($codepoint >= 0x0483 && $codepoint <= 0x0489)
            || ($codepoint >= 0x0591 && $codepoint <= 0x05BD)
            || $codepoint === 0x05BF
            || ($codepoint >= 0x05C1 && $codepoint <= 0x05C2)
            || ($codepoint >= 0x05C4 && $codepoint <= 0x05C5)
            || $codepoint === 0x05C7
            || ($codepoint >= 0x0610 && $codepoint <= 0x061A)
            || ($codepoint >= 0x064B && $codepoint <= 0x065F)
            || $codepoint === 0x0670
            || ($codepoint >= 0x06D6 && $codepoint <= 0x06DC)
            || ($codepoint >= 0x06DF && $codepoint <= 0x06E4)
            || ($codepoint >= 0x06E7 && $codepoint <= 0x06E8)
            || ($codepoint >= 0x06EA && $codepoint <= 0x06ED)
            || ($codepoint >= 0x0711 && $codepoint <= 0x0711)
            || ($codepoint >= 0x0730 && $codepoint <= 0x074A)
            || ($codepoint >= 0x07A6 && $codepoint <= 0x07B0)
            || ($codepoint >= 0x07EB && $codepoint <= 0x07F3)
            || ($codepoint >= 0x0816 && $codepoint <= 0x0819)
            || ($codepoint >= 0x081B && $codepoint <= 0x0823)
            || ($codepoint >= 0x0825 && $codepoint <= 0x0827)
            || ($codepoint >= 0x0829 && $codepoint <= 0x082D)
            || ($codepoint >= 0x0859 && $codepoint <= 0x085B)
            || ($codepoint >= 0x08D3 && $codepoint <= 0x08FF)
            || ($codepoint >= 0x200B && $codepoint <= 0x200F)
            || ($codepoint >= 0x202A && $codepoint <= 0x202E)
            || ($codepoint >= 0x2060 && $codepoint <= 0x206F)
            || ($codepoint >= 0xFE00 && $codepoint <= 0xFE0F)
            || $codepoint === 0xFEFF
            || ($codepoint >= 0xE0100 && $codepoint <= 0xE01EF);
    }

    private function isWideCodepoint(int $codepoint): bool
    {
        return ($codepoint >= 0x1100 && $codepoint <= 0x115F)
            || $codepoint === 0x2329
            || $codepoint === 0x232A
            || ($codepoint >= 0x2E80 && $codepoint <= 0xA4CF && $codepoint !== 0x303F)
            || ($codepoint >= 0xAC00 && $codepoint <= 0xD7A3)
            || ($codepoint >= 0xF900 && $codepoint <= 0xFAFF)
            || ($codepoint >= 0xFE10 && $codepoint <= 0xFE19)
            || ($codepoint >= 0xFE30 && $codepoint <= 0xFE6F)
            || ($codepoint >= 0xFF00 && $codepoint <= 0xFF60)
            || ($codepoint >= 0xFFE0 && $codepoint <= 0xFFE6)
            || ($codepoint >= 0x1F300 && $codepoint <= 0x1FAFF)
            || ($codepoint >= 0x20000 && $codepoint <= 0x3FFFD);
    }

    /**
     * @param list<AstNode> $following
     */
    private function nextInlineStartsBracketed(array $following): bool
    {
        $next = $following[0] ?? null;

        return $next instanceof AstNode && ($next->type === 'link' || $next->type === 'span');
    }

    private function startsWithAtxHeadingMarker(string $text): bool
    {
        $offset = strspn($text, '#');

        return $offset > 0 && ($offset === strlen($text) || $text[$offset] === ' ' || $text[$offset] === "\t");
    }

    private function isIntrawordUnderscore(string $text, int $offset): bool
    {
        $previous = $text[$offset - 1] ?? '';
        $next = $text[$offset + 1] ?? '';

        return $previous !== ''
            && $next !== ''
            && preg_match('/[A-Za-z0-9]/', $previous) === 1
            && preg_match('/[A-Za-z0-9]/', $next) === 1;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function normalizeReferenceLabelText(string $label): string
    {
        return trim(preg_replace('/\s+/', ' ', $label) ?? $label);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderBlockCollection(array $nodes): string
    {
        $blocks = [];
        foreach ($nodes as $index => $node) {
            if (
                $index > 0
                && $this->needsAdjacentListBlockSeparator($nodes[$index - 1], $node)
            ) {
                $this->appendBlockEntry($blocks, $this->listSeparatorBlock());
            }

            if (
                $node->type === 'code_block'
                && $index > 0
                && $this->isListBlock($nodes[$index - 1])
                && $this->codeBlockRendersIndented($node)
            ) {
                $this->appendBlockEntry($blocks, '<!-- -->');
            }

            $lines = $this->renderBlock($node, 0);
            if ($lines !== []) {
                $this->appendBlockEntry($blocks, implode("\n", $lines), $node);
            }
        }

        return $this->joinBlockEntries($blocks);
    }

    /**
     * @param list<array{node:AstNode|null, text:string}> $blocks
     */
    private function appendPendingDefinitionEntries(array &$blocks): void
    {
        foreach ($this->pendingDefinitionBlocks() as $definitionBlock) {
            if ($definitionBlock !== '') {
                $this->appendBlockEntry($blocks, $definitionBlock);
            }
        }
    }

    /**
     * @param list<array{node:AstNode|null, text:string}> $blocks
     */
    private function appendBlockEntry(array &$blocks, string $text, ?AstNode $node = null): void
    {
        if ($text === '') {
            return;
        }

        $blocks[] = [
            'node' => $node,
            'text' => $text,
        ];
    }

    /**
     * @param list<array{node:AstNode|null, text:string}> $blocks
     */
    private function joinBlockEntries(array $blocks): string
    {
        $output = '';
        $previousNode = null;
        foreach ($blocks as $entry) {
            $text = $entry['text'];
            $node = $entry['node'];
            if ($output === '') {
                $output = $text;
            } else {
                $output .= $this->blockEntrySeparator($previousNode, $node) . $text;
            }
            $previousNode = $node;
        }

        return $output;
    }

    private function blockEntrySeparator(?AstNode $previous, ?AstNode $current): string
    {
        if ($previous === null || $current === null) {
            return "\n\n";
        }

        if ($previous->type === 'plain' && $this->isRawBlockNode($current)) {
            return "\n";
        }

        if (
            $this->isRawHtmlSummaryElementBlock($previous)
            && ($current->type === 'paragraph' || $current->type === 'plain')
        ) {
            return "\n";
        }

        if ($this->isRawBlockNode($previous) && ($current->type === 'plain' || $this->isRawBlockNode($current))) {
            return "\n";
        }

        return "\n\n";
    }

    private function isRawBlockNode(AstNode $node): bool
    {
        return in_array($node->type, ['raw_html', 'raw_tex', 'raw_block', 'raw_markdown'], true);
    }

    private function isRawHtmlSummaryElementBlock(AstNode $node): bool
    {
        if (!$this->isRawBlockNode($node)) {
            return false;
        }

        [$format, $text] = $this->rawBlockFormatAndText($node);

        return $this->isRawHtmlFormat($format)
            && preg_match('/^\s*<summary\b[^>]*>.*<\/summary\s*>\s*$/is', $text) === 1;
    }

    /**
     * @return list<string>
     */
    private function pendingDefinitionBlocks(): array
    {
        $blocks = [];
        while ($this->notes !== [] || $this->references !== []) {
            $notes = $this->notes;
            $references = $this->references;
            $this->notes = [];
            $this->references = [];

            foreach ($notes as $note) {
                $blocks[] = $this->renderNoteDefinition($note['label'], $note['node']);
            }

            $referenceDefinitions = [];
            foreach ($references as $reference) {
                $referenceDefinitions[] = $this->renderReferenceDefinition($reference);
            }
            if ($referenceDefinitions !== []) {
                $blocks[] = implode("\n", $referenceDefinitions);
            }
        }

        return $blocks;
    }

    private function renderNoteDefinition(string $label, AstNode $node): string
    {
        if ($this->isPlainTextVariant()) {
            return $this->renderPlainNoteDefinition($label, $node);
        }

        if ($this->opmlNoteMarkdownEnabled() && $this->writerWrapText() === 'auto') {
            return $this->renderOpmlNoteDefinition($label, $node);
        }

        $body = $this->renderBlockCollection($node->children);
        if ($body === '') {
            return '[^' . $label . ']:';
        }

        $lines = explode("\n", $body);
        $first = array_shift($lines);
        $rendered = '[^' . $label . ']: ' . $first;
        foreach ($lines as $line) {
            $rendered .= "\n" . ($line === '' ? '' : '    ' . $line);
        }

        return $rendered;
    }

    private function renderOpmlNoteDefinition(string $label, AstNode $node): string
    {
        if ($node->children === []) {
            return '[^' . $label . ']:';
        }

        $blocks = [];
        foreach ($node->children as $index => $child) {
            if ($index > 0) {
                $blocks[] = '';
            }

            if (in_array($child->type, ['paragraph', 'plain'], true)) {
                $lines = [];
                $this->appendOpmlWrappedMarkdownLines(
                    $lines,
                    $index === 0 ? '[^' . $label . ']: ' : '    ',
                    '    ',
                    $this->renderBlockInlines($child->children, true)
                );
                array_push($blocks, ...$lines);
                continue;
            }

            if ($index === 0) {
                $blocks[] = '[^' . $label . ']:';
            }
            array_push($blocks, ...$this->renderBlock($child, 4));
        }

        return implode("\n", $blocks);
    }

    private function renderPlainNoteDefinition(string $label, AstNode $node): string
    {
        $marker = '[' . $label . ']';
        $body = $this->renderBlockCollection($node->children);
        if ($body === '') {
            return $marker;
        }

        $firstBlock = $node->children[0] ?? null;
        if (!$firstBlock instanceof AstNode || !in_array($firstBlock->type, ['paragraph', 'plain'], true)) {
            return $marker . "\n" . $body;
        }

        $lines = explode("\n", $body);
        $first = array_shift($lines);
        $rendered = $marker . ' ' . (string) $first;
        foreach ($lines as $line) {
            $rendered .= "\n" . $line;
        }

        return $rendered;
    }

    /**
     * @param array{label:string, url:string, title:string, attrs:array<string, mixed>} $reference
     */
    private function renderReferenceDefinition(array $reference): string
    {
        $title = $reference['title'] === ''
            ? ''
            : ' "' . $this->escapeLinkTitle($reference['title']) . '"';
        $attrs = $this->linkAttributesEnabled()
            ? $this->renderAttributesTuple($reference['attrs'])
            : '';

        return '  [' . $reference['label'] . ']: '
            . $this->renderLinkDestination($reference['url'])
            . $title
            . ($attrs === '' ? '' : ' ' . $attrs);
    }

    private function autolinkRenderText(AstNode $node): ?string
    {
        $url = $this->linkUrl($node);
        if (!$this->isUriLike($url)) {
            return null;
        }

        if ($this->linkTitle($node) !== '') {
            return null;
        }

        $attrs = $this->linkAttrTuple($node);
        $classes = $attrs['classes'];
        if (
            $attrs['id'] !== ''
            || $attrs['attributes'] !== []
            || ($classes !== [] && $classes !== ['uri'] && $classes !== ['email'])
        ) {
            return null;
        }

        if (count($node->children) !== 1 || $node->children[0]->type !== 'text') {
            return null;
        }

        $label = (string) $node->children[0]->attr('text', '');
        $candidates = [$this->autolinkText($node)];
        if (str_starts_with($url, 'mailto:')) {
            $candidates[] = $url;
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (preg_match('/[\s<>]/u', $candidate) === 1) {
                continue;
            }

            if ($label === $candidate || $this->escapeUri($label) === $candidate) {
                return $candidate;
            }
        }

        return null;
    }

    private function autolinkText(AstNode $node): string
    {
        $url = $this->linkUrl($node);

        return str_starts_with($url, 'mailto:') ? substr($url, 7) : $url;
    }

    /**
     * @return list<AstNode>
     */
    private function imageLabelNodesForLink(AstNode $node): array
    {
        $labelNodes = $node->children;
        if ($labelNodes === []) {
            $alt = $this->imageAltText($node);
            if ($alt !== '') {
                $labelNodes = [new AstNode('text', ['text' => $alt])];
            }
        }

        $url = $this->imageUrl($node);
        if ($labelNodes === [] || (count($labelNodes) === 1 && $labelNodes[0]->type === 'text' && $labelNodes[0]->attr('text', '') === $url)) {
            return [new AstNode('text', ['text' => ''])];
        }

        return $labelNodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function imageLinkAttrs(AstNode $node): array
    {
        $attrs = $node->attrs;
        $attrs['url'] = $this->imageUrl($node);
        $attrs['title'] = $this->imageTitle($node);

        $alt = $this->imageAltText($node);
        if ($alt !== '') {
            $labelText = $this->plainInlineText($this->imageLabelNodesForLink($node));
            $attributes = $attrs['attributes'] ?? [];
            if (!is_array($attributes)) {
                $attributes = [];
            }
            if ($labelText !== '' && $labelText !== $alt && !array_key_exists('alt', $attributes)) {
                $attrs['attributes'] = ['alt' => $alt] + $attributes;
            }
        }

        return $attrs;
    }

    private function singleImageFigureChild(AstNode $figure): ?AstNode
    {
        if (count($figure->children) !== 1) {
            return null;
        }

        $child = $figure->children[0];
        if ($child->type === 'image') {
            return $child;
        }

        if (
            ($child->type === 'plain' || $child->type === 'paragraph')
            && count($child->children) === 1
            && $child->children[0]->type === 'image'
        ) {
            return $child->children[0];
        }

        return null;
    }

    private function implicitFigureImage(AstNode $figure, AstNode $image): ?AstNode
    {
        $attrs = $image->attrs;
        $classes = $figure->attr('classes', []);
        $figureAttributes = $figure->attr('attributes', []);
        if (
            (is_array($classes) && $classes !== [])
            || (is_array($figureAttributes) && $figureAttributes !== [])
            || (string) $image->attr('id', '') !== ''
        ) {
            return null;
        }

        $figureId = (string) $figure->attr('id', '');
        if ($figureId !== '') {
            $attrs['id'] = $figureId;
        }

        $title = (string) ($attrs['title'] ?? '');
        if (str_starts_with($title, 'fig:')) {
            $attrs['title'] = substr($title, 4);
        }

        $imageAttributes = $attrs['attributes'] ?? [];
        if (!is_array($imageAttributes)) {
            $imageAttributes = [];
        }

        $captionInlines = $this->figureCaptionInlines($figure);
        $caption = trim($this->plainInlineText($captionInlines));
        if ($captionInlines === []) {
            $alt = $this->plainInlineText($image->children);
            if ($alt === '') {
                $alt = (string) ($attrs['alt'] ?? '');
            }
            if ($alt !== '' && !array_key_exists('alt', $imageAttributes)) {
                $imageAttributes = ['alt' => $alt] + $imageAttributes;
            }
            unset($attrs['alt']);
        }
        if (array_key_exists('alt', $imageAttributes) && ($imageAttributes['alt'] === '' || $imageAttributes['alt'] === $caption)) {
            unset($imageAttributes['alt']);
        }
        if ($imageAttributes !== []) {
            $attrs['attributes'] = $imageAttributes;
        } else {
            unset($attrs['attributes']);
        }

        return new AstNode('image', $attrs, $captionInlines);
    }

    private function figureAsDiv(AstNode $figure): AstNode
    {
        $classes = $figure->attr('classes', []);
        if (!is_array($classes)) {
            $classes = [];
        }
        $classes = array_values(array_unique(array_merge(['figure'], array_map(
            static fn (mixed $class): string => (string) $class,
            $classes
        ))));

        $attrs = $figure->attrs;
        $attrs['classes'] = array_values(array_filter($classes, static fn (string $class): bool => $class !== ''));
        unset($attrs['caption'], $attrs['captionInlines'], $attrs['shortCaption'], $attrs['shortCaptionInlines']);

        $children = $this->figureBlockChildren($figure);
        $captionInlines = $this->figureCaptionInlines($figure);
        if ($captionInlines !== []) {
            $captionAttrs = ['classes' => ['caption']];
            $shortCaption = (string) $figure->attr('shortCaption', '');
            if ($shortCaption !== '') {
                $captionAttrs['attributes'] = ['short-caption' => $shortCaption];
            }
            $children[] = new AstNode('div', $captionAttrs, [
                new AstNode('plain', [], $captionInlines),
            ]);
        }

        return new AstNode('div', $attrs, $children);
    }

    /**
     * @return list<string>
     */
    private function renderRawHtmlFigure(AstNode $figure, int $indent): array
    {
        $lines = ['<figure' . $this->renderPandocHtmlAttributes($this->linkAttrTuple($figure)) . '>'];
        foreach ($this->figureBlockChildren($figure) as $child) {
            $html = $this->renderBlockAsHtml($child);
            if ($html !== '') {
                array_push($lines, ...explode("\n", $html));
            }
        }

        $captionInlines = $this->figureCaptionInlines($figure);
        if ($captionInlines !== []) {
            $ariaHidden = $this->captionMatchesSingleImageAlt($figure, $captionInlines)
                ? ' aria-hidden="true"'
                : '';
            $lines[] = '<figcaption' . $ariaHidden . '>' . $this->renderHtmlInlines($captionInlines) . '</figcaption>';
        }
        $lines[] = '</figure>';

        return $this->prefixLines($lines, $indent);
    }

    private function renderBlockAsHtml(AstNode $node): string
    {
        if ($node->type === 'paragraph' || $node->type === 'plain') {
            return '<p>' . $this->renderHtmlInlines($node->children) . '</p>';
        }

        if ($node->type === 'image') {
            return '<p>' . $this->renderRawHtmlImage($node) . '</p>';
        }

        if ($node->type === 'code_block') {
            return '<pre><code>' . $this->escapeHtml((string) $node->attr('text', '')) . '</code></pre>';
        }

        if ($node->type === 'raw_html') {
            return (string) $node->attr('text', $node->attr('html', ''));
        }

        if ($node->type === 'blockquote') {
            $contents = $this->renderBlocksAsHtmlFragments($node->children);

            return '<blockquote>' . $contents . '</blockquote>';
        }

        if ($node->type === 'div') {
            $contents = $this->renderBlocksAsHtmlFragments($node->children);

            return '<div' . $this->renderPandocHtmlAttributes($this->linkAttrTuple($node)) . '>' . $contents . '</div>';
        }

        if ($node->type === 'table') {
            return implode("\n", $this->renderRawHtmlTableLines($node));
        }

        return '';
    }

    /**
     * @return list<AstNode>
     */
    private function figureBlockChildren(AstNode $figure): array
    {
        $children = [];
        foreach ($figure->children as $child) {
            $children[] = $child->type === 'image'
                ? new AstNode('plain', [], [$child])
                : $child;
        }

        return $children;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderBlocksAsHtmlFragments(array $nodes): string
    {
        $fragments = [];
        foreach ($nodes as $node) {
            $html = $this->renderBlockAsHtml($node);
            if ($html !== '') {
                $fragments[] = $html;
            }
        }

        return implode('', $fragments);
    }

    /**
     * @return list<AstNode>
     */
    private function figureCaptionInlines(AstNode $figure): array
    {
        $inlines = $figure->attr('captionInlines', null);
        if (is_array($inlines)) {
            $nodes = [];
            foreach ($inlines as $inline) {
                if ($inline instanceof AstNode) {
                    $nodes[] = $inline;
                }
            }
            if ($nodes !== []) {
                return $nodes;
            }
        }

        $caption = (string) $figure->attr('caption', '');

        return $caption === '' ? [] : [new AstNode('text', ['text' => $caption])];
    }

    /**
     * @param list<AstNode> $captionInlines
     */
    private function captionMatchesSingleImageAlt(AstNode $figure, array $captionInlines): bool
    {
        $image = $this->singleImageFigureChild($figure);
        if (!$image instanceof AstNode) {
            return false;
        }

        $attrs = $this->imageAttrTuple($image);
        $alt = $attrs['attributes']['alt'] ?? null;
        if ($alt === null) {
            $alt = $this->plainInlineText($this->imageLabelNodesForLink($image));
        }

        return $alt !== '' && $alt === $this->plainInlineText($captionInlines);
    }

    private function isUriLike(string $url): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $url) === 1;
    }

    private function escapeUri(string $url): string
    {
        return preg_replace_callback(
            '/[^A-Za-z0-9\\-._~:\\/?#\\[\\]@!$&\'()*+,;=%]/u',
            static fn (array $match): string => implode('', array_map(
                static fn (string $byte): string => sprintf('%%%02X', ord($byte)),
                str_split($match[0])
            )),
            $url
        ) ?? $url;
    }

    private function renderLinkDestination(string $url): string
    {
        if ($url === '' && $this->opmlNoteMarkdownEnabled()) {
            return '';
        }

        $url = $this->escapeLinkDestinationControlCharacters($url);
        if ($this->linkDestinationNeedsAngles($url)) {
            return '<' . str_replace(['<', '>'], ['\\<', '\\>'], $url) . '>';
        }

        return str_replace(['\\', '(', ')', '|'], ['\\\\', '\\(', '\\)', '\\|'], $url);
    }

    private function escapeLinkDestinationControlCharacters(string $url): string
    {
        return preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            static fn (array $match): string => sprintf('%%%02X', ord($match[0])),
            $url
        ) ?? $url;
    }

    private function linkDestinationNeedsAngles(string $url): bool
    {
        return $url === ''
            || preg_match('/[ <>()]/u', $url) === 1;
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function linkAttrTuple(AstNode $node): array
    {
        $id = $this->normalizeAttributeTokenValue($this->firstStringAttr($node->attrs, ['id', 'identifier']));
        $classes = $this->attributeClasses($node->attrs);
        $attributes = $this->attributeKeyValues($node->attrs);

        return [
            'id' => $id,
            'classes' => $classes,
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @return list<string>
     */
    private function attributeClasses(array $attrs): array
    {
        $classes = [];
        foreach (['classes', 'class', 'className'] as $name) {
            if (!array_key_exists($name, $attrs)) {
                continue;
            }

            $value = $attrs[$name];
            $items = is_array($value)
                ? $value
                : (preg_split('/\s+/u', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: []);
            foreach ($items as $item) {
                if (!is_string($item) && !is_numeric($item)) {
                    continue;
                }

                $class = $this->normalizeAttributeTokenValue((string) $item);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, string>
     */
    private function attributeKeyValues(array $attrs): array
    {
        $attributes = [];
        foreach (['attributes', 'keyvals', 'keyValues'] as $name) {
            $this->appendAttributeKeyValues($attributes, $attrs[$name] ?? []);
        }

        return $attributes;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendAttributeKeyValues(array &$attributes, mixed $source): void
    {
        if (!is_array($source)) {
            return;
        }

        foreach ($source as $key => $value) {
            if (is_array($value)) {
                $name = $value['key'] ?? $value['name'] ?? $value[0] ?? null;
                $attributeValue = $value['value'] ?? $value[1] ?? '';
            } else {
                $name = is_string($key) ? $key : null;
                $attributeValue = $value;
            }

            if (!is_string($name) && !is_numeric($name)) {
                continue;
            }

            if (!is_string($attributeValue) && !is_numeric($attributeValue) && !is_bool($attributeValue)) {
                continue;
            }

            $name = $this->normalizeAttributeTokenValue((string) $name);
            if ($name === '') {
                continue;
            }

            $attributes[$name] = $this->normalizeAttributeValue((string) $attributeValue);
        }
    }

    private function normalizeAttributeTokenValue(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeAttributeValue(string $value): string
    {
        return preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? $value;
    }

    private function renderLinkAttributes(AstNode $node): string
    {
        if (!$this->linkAttributesEnabled()) {
            return '';
        }

        return $this->renderAttributesTuple($this->linkAttrTuple($node));
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function shouldRenderRawHtmlFallback(array $attrs): bool
    {
        return !$this->linkAttributesEnabled()
            && $this->rawHtmlEnabled()
            && !$this->isNullAttrTuple($attrs);
    }

    private function linkAttributesEnabled(): bool
    {
        return (bool) ($this->options['linkAttributes'] ?? true);
    }

    private function fencedCodeBlocksEnabled(): bool
    {
        return (bool) ($this->options['fencedCodeBlocks'] ?? true);
    }

    private function backtickCodeBlocksEnabled(): bool
    {
        return (bool) ($this->options['backtickCodeBlocks'] ?? true);
    }

    private function fencedCodeAttributesEnabled(): bool
    {
        return (bool) ($this->options['fencedCodeAttributes'] ?? true);
    }

    private function definitionListsEnabled(): bool
    {
        return (bool) ($this->options['definitionLists'] ?? true);
    }

    private function lineBlocksEnabled(): bool
    {
        if (array_key_exists('lineBlocks', $this->options)) {
            return (bool) $this->options['lineBlocks'];
        }

        $override = $this->markdownExtensionOverride('line_blocks');
        if ($override !== null) {
            return $override;
        }

        return !$this->isCommonMarkVariant();
    }

    private function rawHtmlEnabled(): bool
    {
        return MarkdownFormatProfile::rawHtmlEnabled($this->options, true);
    }

    private function rawAttributeEnabled(): bool
    {
        return MarkdownFormatProfile::rawAttributeEnabled($this->options, true);
    }

    private function rawTexEnabled(): bool
    {
        return MarkdownFormatProfile::rawTexEnabled($this->options, true);
    }

    private function opmlNoteMarkdownEnabled(): bool
    {
        return (bool) ($this->options['opmlNoteMarkdown'] ?? false);
    }

    private function simpleTablesEnabled(): bool
    {
        return (bool) ($this->options['simpleTables'] ?? true);
    }

    private function pipeTablesEnabled(): bool
    {
        return (bool) ($this->options['pipeTables'] ?? true);
    }

    private function multilineTablesEnabled(): bool
    {
        return (bool) ($this->options['multilineTables'] ?? true);
    }

    private function gridTablesEnabled(): bool
    {
        return (bool) ($this->options['gridTables'] ?? true);
    }

    private function tableCaptionsEnabled(): bool
    {
        if ($this->isCommonMarkVariant()) {
            return false;
        }

        return (bool) ($this->options['tableCaptions'] ?? true);
    }

    private function writerVariant(): string
    {
        $format = $this->options['variant'] ?? $this->options['format'] ?? 'markdown';

        return strtolower(str_replace('_', '-', MarkdownFormatProfile::canonicalFormat($format)));
    }

    private function isCommonMarkVariant(): bool
    {
        return in_array($this->writerVariant(), ['commonmark', 'commonmark-x', 'gfm'], true);
    }

    private function isPlainTextVariant(): bool
    {
        return in_array($this->writerVariant(), ['plain', 'plain-text', 'plaintext'], true);
    }

    private function writerColumns(): int
    {
        return max(1, (int) ($this->options['columns'] ?? 72));
    }

    private function definitionListLeadingChars(): int
    {
        return max(2, (int) ($this->options['tabStop'] ?? 4));
    }

    private function writerWrapText(): string
    {
        if ((bool) ($this->options['hardLineBreaks'] ?? false)) {
            return 'none';
        }

        $wrap = strtolower(str_replace('_', '-', (string) ($this->options['wrap'] ?? 'auto')));

        return match ($wrap) {
            'none', 'wrap-none' => 'none',
            'preserve', 'wrap-preserve' => 'preserve',
            default => 'auto',
        };
    }

    private function strikeoutEnabled(): bool
    {
        return (bool) ($this->options['strikeout'] ?? true);
    }

    private function scriptEnabled(string $kind): bool
    {
        return (bool) ($this->options[$kind] ?? true);
    }

    private function writerPreferAscii(): bool
    {
        return (bool) ($this->options['preferAscii'] ?? false);
    }

    private function gutenbergEnabled(): bool
    {
        return (bool) ($this->options['gutenberg'] ?? false);
    }

    private function templateEnabled(): bool
    {
        if (array_key_exists('template', $this->options)) {
            return (bool) $this->options['template'];
        }

        return (bool) ($this->options['standalone'] ?? false);
    }

    private function customPlainTemplateSource(): ?string
    {
        if (!$this->isPlainTextVariant()) {
            return null;
        }

        $template = $this->options['template'] ?? null;
        if (!is_string($template) || $template === '') {
            return null;
        }

        return $template;
    }

    private function plainTemplateTableOfContentsEnabled(): bool
    {
        foreach (['tableOfContents', 'table-of-contents', 'toc'] as $key) {
            if (array_key_exists($key, $this->options)) {
                return (bool) $this->options[$key];
            }
        }

        return false;
    }

    private function plainTemplateTocDepth(): int
    {
        foreach (['tocDepth', 'toc-depth'] as $key) {
            if (array_key_exists($key, $this->options)) {
                return max(1, (int) $this->options[$key]);
            }
        }

        return 3;
    }

    private function plainTemplateNumberSectionsEnabled(): bool
    {
        foreach (['numberSections', 'number-sections'] as $key) {
            if (array_key_exists($key, $this->options)) {
                return (bool) $this->options[$key];
            }
        }

        return false;
    }

    private function smartEnabled(): bool
    {
        return (bool) ($this->options['smart'] ?? true);
    }

    private function emojiShortcodesEnabled(): bool
    {
        return $this->markdownExtensionOverride('emoji_shortcodes') === true;
    }

    private function markdownExtensionOverride(string $extension): ?bool
    {
        return $this->markdownExtensionOverrides()[$extension] ?? null;
    }

    /**
     * @return array<string, bool>
     */
    private function markdownExtensionOverrides(): array
    {
        return MarkdownFormatProfile::markdownExtensionOverrides($this->markdownFormatWithExtensionOption());
    }

    private function markdownFormatWithExtensionOption(): string
    {
        $format = $this->options['format'] ?? $this->options['variant'] ?? 'markdown';
        $format = is_scalar($format) ? (string) $format : 'markdown';
        $extensionSuffix = $this->markdownExtensionOptionSuffix($this->options['extensions'] ?? '');
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

    private function bracketedSpansEnabled(): bool
    {
        return (bool) ($this->options['bracketedSpans'] ?? true);
    }

    private function nativeSpansEnabled(): bool
    {
        return (bool) ($this->options['nativeSpans'] ?? false);
    }

    private function fencedDivsEnabled(): bool
    {
        return (bool) ($this->options['fencedDivs'] ?? true);
    }

    private function nativeDivsEnabled(): bool
    {
        return (bool) ($this->options['nativeDivs'] ?? true);
    }

    private function implicitFiguresEnabled(): bool
    {
        return (bool) ($this->options['implicitFigures'] ?? true);
    }

    private function markdownInHtmlBlocksEnabled(): bool
    {
        return (bool) ($this->options['markdownInHtmlBlocks'] ?? true);
    }

    private function markdownAttributeEnabled(): bool
    {
        return (bool) ($this->options['markdownAttribute'] ?? false);
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function isNullAttrTuple(array $attrs): bool
    {
        return $attrs['id'] === '' && $attrs['classes'] === [] && $attrs['attributes'] === [];
    }

    private function renderRawHtmlLink(AstNode $node): string
    {
        $attributes = [
            ['href', $this->linkUrl($node)],
        ];
        $title = $this->linkTitle($node);
        if ($title !== '') {
            $attributes[] = ['title', $title];
        }

        array_push($attributes, ...$this->htmlAttributesFromAttrTuple($this->linkAttrTuple($node)));

        return '<a'
            . $this->renderHtmlAttributes($attributes)
            . '>'
            . $this->renderHtmlInlines($node->children, true)
            . '</a>';
    }

    private function renderRawHtmlImage(AstNode $node): string
    {
        $attributes = [
            ['src', $this->imageUrl($node)],
        ];
        $title = $this->imageTitle($node);
        if ($title !== '') {
            $attributes[] = ['title', $title];
        }

        $attrTuple = $this->imageAttrTuple($node);
        array_push($attributes, ...$this->htmlAttributesFromAttrTuple($attrTuple));

        if (!array_key_exists('alt', $attrTuple['attributes'])) {
            $alt = $this->plainInlineText($this->imageLabelNodesForLink($node));
            if ($alt !== '') {
                $attributes[] = ['alt', $alt];
            }
        }

        return '<img' . $this->renderHtmlAttributes($attributes) . ' />';
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderRawHtmlSpan(array $attrs, string $content): string
    {
        return '<span'
            . $this->renderPandocHtmlAttributes($attrs)
            . '>'
            . $content
            . '</span>';
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderPandocHtmlAttributes(array $attrs): string
    {
        $attributes = [];
        if ($attrs['id'] !== '') {
            $attributes[] = ['id', $attrs['id']];
        }
        if ($attrs['classes'] !== []) {
            $attributes[] = ['class', implode(' ', $attrs['classes'])];
        }
        foreach ($attrs['attributes'] as $name => $value) {
            $attributes[] = [(string) $name, $value];
        }

        $rendered = '';
        foreach ($attributes as [$name, $value]) {
            if ($name === '') {
                continue;
            }

            $rendered .= ' ' . $name . '="' . $this->escapeHtml($value) . '"';
        }

        return $rendered;
    }

    /**
     * @param list<string> $skip
     */
    private function renderNodeHtmlAttributes(AstNode $node, array $skip = []): string
    {
        $attributes = [];
        $seen = [];
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }

        $id = (string) ($htmlAttributes['id'] ?? $node->attr('id', ''));
        if ($id !== '' && !in_array('id', $skip, true)) {
            $attributes[] = ['id', $id];
            $seen['id'] = true;
        }

        $class = (string) ($htmlAttributes['class'] ?? '');
        if ($class === '') {
            $classes = $node->attr('classes', []);
            if (is_array($classes)) {
                $classes = array_values(array_filter(
                    array_map(static fn (mixed $class): string => (string) $class, $classes),
                    static fn (string $class): bool => $class !== ''
                ));
                $class = implode(' ', $classes);
            }
        }
        if ($class !== '' && !in_array('class', $skip, true)) {
            $attributes[] = ['class', $class];
            $seen['class'] = true;
        }

        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'id' || $name === 'class' || in_array($name, $skip, true)) {
                continue;
            }
            $attributes[] = [$name, (string) $value];
            $seen[$name] = true;
        }

        $tuple = $this->linkAttrTuple($node);
        foreach ($tuple['attributes'] as $name => $value) {
            $name = $this->htmlAttributeName((string) $name);
            $key = strtolower($name);
            if (isset($seen[$key]) || in_array($key, $skip, true)) {
                continue;
            }

            $attributes[] = [$name, $value];
            $seen[$key] = true;
        }

        return $this->renderHtmlAttributes($attributes);
    }

    private function nodeHtmlStyle(AstNode $node): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (is_array($htmlAttributes) && isset($htmlAttributes['style'])) {
            return trim((string) $htmlAttributes['style']);
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes) && isset($attributes['style'])) {
            return trim((string) $attributes['style']);
        }

        return '';
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function imageAttrTuple(AstNode $node): array
    {
        return $this->linkAttrTuple(new AstNode('image', $this->imageLinkAttrs($node)));
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     * @return list<array{0:string, 1:string}>
     */
    private function htmlAttributesFromAttrTuple(array $attrs): array
    {
        $htmlAttributes = [];
        if ($attrs['id'] !== '') {
            $htmlAttributes[] = ['id', $attrs['id']];
        }

        $classes = array_values(array_unique($attrs['classes']));
        if ($classes !== []) {
            $htmlAttributes[] = ['class', implode(' ', $classes)];
        }

        foreach ($attrs['attributes'] as $name => $value) {
            $htmlAttributes[] = [$this->htmlAttributeName((string) $name), $value];
        }

        return $htmlAttributes;
    }

    /**
     * @param list<array{0:string, 1:string}> $attributes
     */
    private function renderHtmlAttributes(array $attributes): string
    {
        $rendered = '';
        foreach ($attributes as [$name, $value]) {
            if (preg_match('/^[A-Za-z_:][A-Za-z0-9_.:-]*$/', $name) !== 1) {
                continue;
            }

            $rendered .= ' ' . $name . '="' . $this->escapeHtml($value) . '"';
        }

        return $rendered;
    }

    private function addMarkdownAttributeToRawHtml(string $html): string
    {
        if (preg_match('/<([A-Za-z][A-Za-z0-9:-]*)([^<>]*?)(\/?)>/', $html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return $html;
        }

        $attributeText = $matches[2][0];
        if (preg_match('/(?:^|\s)markdown\s*=/i', $attributeText) === 1) {
            return $html;
        }

        $insertAt = $matches[2][1] + strlen($attributeText);

        return substr($html, 0, $insertAt) . ' markdown="1"' . substr($html, $insertAt);
    }

    private function removeBlankLinesInRawHtml(string $html): string
    {
        $output = '';
        $afterNewline = false;
        $length = strlen($html);

        for ($index = 0; $index < $length; $index++) {
            $char = $html[$index];
            if ($char === "\n") {
                if ($afterNewline) {
                    $output .= '&#10;';
                    $afterNewline = false;
                } else {
                    $output .= "\n";
                    $afterNewline = true;
                }
                continue;
            }

            $output .= $char;
            if (!ctype_space($char)) {
                $afterNewline = false;
            }
        }

        return $output;
    }

    private function htmlAttributeName(string $name): string
    {
        $lower = strtolower($name);
        if (
            str_starts_with($lower, 'data-')
            || str_starts_with($lower, 'aria-')
            || str_contains($name, ':')
            || in_array($lower, $this->standardHtmlAttributeNames(), true)
        ) {
            return $name;
        }

        return 'data-' . $name;
    }

    /**
     * @return list<string>
     */
    private function standardHtmlAttributeNames(): array
    {
        return [
            'abbr',
            'accesskey',
            'alt',
            'async',
            'autofocus',
            'autoplay',
            'bgcolor',
            'border',
            'checked',
            'cite',
            'class',
            'cols',
            'colspan',
            'content',
            'contenteditable',
            'controls',
            'coords',
            'datetime',
            'defer',
            'dir',
            'disabled',
            'download',
            'draggable',
            'for',
            'headers',
            'height',
            'hidden',
            'href',
            'hreflang',
            'id',
            'label',
            'lang',
            'loop',
            'max',
            'maxlength',
            'media',
            'method',
            'min',
            'multiple',
            'name',
            'pattern',
            'placeholder',
            'poster',
            'preload',
            'readonly',
            'rel',
            'required',
            'reversed',
            'role',
            'rowspan',
            'scope',
            'selected',
            'shape',
            'size',
            'span',
            'spellcheck',
            'src',
            'start',
            'step',
            'style',
            'tabindex',
            'target',
            'title',
            'translate',
            'type',
            'value',
            'width',
            'wrap',
        ];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderHtmlInlines(array $nodes, bool $insideLink = false): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= $this->renderHtmlInline($node, $insideLink);
        }

        return $html;
    }

    private function renderHtmlInline(AstNode $node, bool $insideLink): string
    {
        return match ($node->type) {
            'text' => $this->escapeHtml((string) $node->attr('text', '')),
            'softbreak' => "\n",
            'linebreak' => '<br />',
            'code' => '<code'
                . $this->renderHtmlAttributes($this->htmlAttributesFromAttrTuple($this->linkAttrTuple($node)))
                . '>'
                . $this->escapeHtml((string) $node->attr('text', ''))
                . '</code>',
            'emph' => '<em>' . $this->renderHtmlInlines($node->children, $insideLink) . '</em>',
            'strong' => '<strong>' . $this->renderHtmlInlines($node->children, $insideLink) . '</strong>',
            'mark' => '<mark>' . $this->renderHtmlInlines($node->children, $insideLink) . '</mark>',
            'underline' => '<u>' . $this->renderHtmlInlines($node->children, $insideLink) . '</u>',
            'small_caps' => '<span class="smallcaps">' . $this->renderHtmlInlines($node->children, $insideLink) . '</span>',
            'strikeout' => '<del>' . $this->renderHtmlInlines($node->children, $insideLink) . '</del>',
            'superscript' => '<sup>' . $this->renderHtmlInlines($node->children, $insideLink) . '</sup>',
            'subscript' => '<sub>' . $this->renderHtmlInlines($node->children, $insideLink) . '</sub>',
            'span' => '<span'
                . $this->renderHtmlAttributes($this->htmlAttributesFromAttrTuple($this->linkAttrTuple($node)))
                . '>'
                . $this->renderHtmlInlines($node->children, $insideLink)
                . '</span>',
            'quoted' => $this->renderHtmlQuotedInline($node, $insideLink),
            'link' => $insideLink ? $this->renderHtmlInlines($node->children, true) : $this->renderRawHtmlLink($node),
            'image' => $this->renderRawHtmlImage($node),
            'math' => '<span class="math ' . ($node->attr('display') === true ? 'display' : 'inline') . '">'
                . $this->escapeHtml((string) $node->attr('text', ''))
                . '</span>',
            'raw_html_inline' => (string) $node->attr('html', ''),
            'raw_tex' => $this->escapeHtml((string) $node->attr('tex', '')),
            'raw_inline', 'raw_markdown' => $this->escapeHtml((string) $node->attr('text', $node->attr('markdown', ''))),
            'citation' => $this->escapeHtml((string) $node->attr('text', $this->plainInlineText($node->children))),
            default => $this->renderHtmlInlines($node->children, $insideLink),
        };
    }

    private function renderHtmlQuotedInline(AstNode $node, bool $insideLink): string
    {
        $left = $node->attr('kind') === 'single' ? "\u{2018}" : "\u{201C}";
        $right = $node->attr('kind') === 'single' ? "\u{2019}" : "\u{201D}";

        return $left . $this->renderHtmlInlines($node->children, $insideLink) . $right;
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderAttributesTuple(array $attrs): string
    {
        $parts = [];
        if ($attrs['id'] !== '') {
            $parts[] = '#' . $this->escapeAttributeToken($attrs['id']);
        }
        foreach ($attrs['classes'] as $class) {
            $parts[] = '.' . $this->escapeAttributeToken($class);
        }
        foreach ($attrs['attributes'] as $name => $value) {
            $parts[] = $this->escapeAttributeToken((string) $name)
                . '="'
                . $this->escapeAttributeValue($value)
                . '"';
        }

        return $parts === [] ? '' : '{' . implode(' ', $parts) . '}';
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function attributeSignature(array $attrs): string
    {
        return json_encode($attrs, JSON_THROW_ON_ERROR);
    }

    private function escapeAttributeToken(string $value): string
    {
        $value = $this->normalizeAttributeTokenValue($value);

        return str_replace(
            ['\\', '"', ' ', '{', '}', '(', ')', '='],
            ['\\\\', '\\"', '\\ ', '\\{', '\\}', '\\(', '\\)', '\\='],
            $value
        );
    }

    private function escapeAttributeValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $this->normalizeAttributeValue($value));
    }

    private function escapeLinkTitle(string $title): string
    {
        $title = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $title) ?? $title;

        if ($this->opmlNoteMarkdownEnabled()) {
            return str_replace('\\', '\\\\', $title);
        }

        return str_replace(['\\', '"'], ['\\\\', '\\"'], $title);
    }

    private function referenceLocation(): string
    {
        $location = (string) ($this->options['referenceLocation'] ?? 'end_of_document');

        return in_array($location, ['end_of_document', 'end_of_block', 'end_of_section'], true)
            ? $location
            : 'end_of_document';
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'emph',
            'strong',
            'mark',
            'underline',
            'small_caps',
            'strikeout',
            'superscript',
            'subscript',
            'span',
            'quoted',
            'softbreak',
            'linebreak',
            'code',
            'link',
            'image',
            'citation',
            'math',
            'raw_tex',
            'raw_inline',
            'raw_markdown',
            'raw_html_inline',
            'note',
        ], true);
    }

    private function isListBlock(AstNode $node): bool
    {
        return $node->type === 'bullet_list' || $node->type === 'ordered_list' || $node->type === 'definition_list';
    }

    /**
     * @return list<string>
     */
    private function indentedLines(string $text, int $indent): array
    {
        return $this->prefixLines(explode("\n", $text), $indent);
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function prefixLines(array $lines, int $indent): array
    {
        $prefix = str_repeat(' ', $indent);

        return array_map(static fn (string $line): string => $prefix . $line, $lines);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function computeDivNestingLevel(array $nodes): int
    {
        $level = 0;
        foreach ($nodes as $node) {
            if ($node->type === 'div') {
                $level = max($level, 1 + $this->computeDivNestingLevel($node->children));
            }
        }

        return $level;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function directDivChildCount(array $nodes): int
    {
        $count = 0;
        foreach ($nodes as $node) {
            if ($node->type === 'div') {
                $count++;
            }
        }

        return $count;
    }
}
