<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class RstReader
{
    /** @var array<string, string> */
    private array $references = [];

    /** @var array<string, list<AstNode>> */
    private array $substitutions = [];

    /** @var array<string, list<AstNode>> */
    private array $footnoteDefinitions = [];

    /** @var array<string, list<AstNode>> */
    private array $citationDefinitions = [];

    /** @var array<string, int> */
    private array $headingLevels = [];

    private int $directiveCount = 0;

    private int $fieldListCount = 0;

    private int $tableCount = 0;

    private int $codeBlockCount = 0;

    private ?string $defaultCodeLanguage = null;

    public function read(string $source): AstNode
    {
        $this->references = [];
        $this->substitutions = [];
        $this->footnoteDefinitions = [];
        $this->citationDefinitions = [];
        $this->headingLevels = [];
        $this->directiveCount = 0;
        $this->fieldListCount = 0;
        $this->tableCount = 0;
        $this->codeBlockCount = 0;
        $this->defaultCodeLanguage = null;

        $source = $this->normalize($source);
        $source = $this->extractSubstitutionDefinitions($source);
        $source = $this->extractFootnoteAndCitationDefinitions($source);
        $source = $this->extractReferenceDefinitions($source);
        $blocks = $this->parseBlocks(explode("\n", $source));

        return new AstNode('document', [
            'meta' => [
                'rstReferenceCount' => count($this->references),
                'rstReferences' => $this->metaMap($this->references),
                'rstSubstitutionCount' => count($this->substitutions),
                'rstFootnoteDefinitionCount' => count($this->footnoteDefinitions),
                'rstCitationDefinitionCount' => count($this->citationDefinitions),
                'rstDirectiveCount' => $this->directiveCount,
                'rstFieldListCount' => $this->fieldListCount,
                'rstTableCount' => $this->tableCount,
                'rstCodeBlockCount' => $this->codeBlockCount,
            ],
        ], $blocks);
    }

    public function readRstFile(string $path): AstNode
    {
        $source = file_get_contents($path);
        if (!is_string($source)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }

        return $this->read($source);
    }

    private function normalize(string $source): string
    {
        $source = preg_replace('/^\xEF\xBB\xBF/', '', $source) ?? $source;

        return str_replace(["\r\n", "\r"], "\n", $source);
    }

    private function extractReferenceDefinitions(string $source): string
    {
        return preg_replace_callback(
            '/^\s*\.\.\s+_([^:]+):\s*(\S.*?)\s*$/um',
            function (array $match): string {
                $this->references[$this->referenceKey($match[1])] = trim($match[2]);

                return '';
            },
            $source
        ) ?? $source;
    }

    private function extractSubstitutionDefinitions(string $source): string
    {
        return preg_replace_callback(
            '/^\s*\.\.\s+\|([^|]+)\|\s+(replace|image)::\s*(.*?)\s*$/um',
            function (array $match): string {
                $key = $this->referenceKey($match[1]);
                $kind = strtolower($match[2]);
                $value = trim($match[3]);
                if ($kind === 'image') {
                    $this->substitutions[$key] = [new AstNode('image', [
                        'url' => $value,
                        'alt' => trim($match[1]),
                        'attributes' => ['data-pandoc-source' => 'rst-substitution'],
                    ])];
                } else {
                    $this->substitutions[$key] = $this->parseInlines($value);
                }

                return '';
            },
            $source
        ) ?? $source;
    }

    private function extractFootnoteAndCitationDefinitions(string $source): string
    {
        $lines = explode("\n", $source);
        $kept = [];
        $count = count($lines);
        for ($index = 0; $index < $count; $index++) {
            $line = $lines[$index];
            if (preg_match('/^\s*\.\.\s+\[([^\]]+)\]\s+(.*)$/u', $line, $match) !== 1) {
                $kept[] = $line;
                continue;
            }

            $label = trim($match[1]);
            $body = [trim($match[2])];
            while ($index + 1 < $count && (trim($lines[$index + 1]) === '' || $this->indentWidth($lines[$index + 1]) > 0)) {
                $index++;
                $body[] = trim($lines[$index]);
            }

            $text = trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($body, static fn (string $part): bool => $part !== ''))) ?? '');
            $inlines = $this->parseInlines($text);
            $blocks = [new AstNode('paragraph', ['text' => $this->plainText($inlines)], $inlines)];
            if (str_starts_with($label, '#')) {
                $this->footnoteDefinitions[$this->referenceKey($label)] = $blocks;
            } else {
                $this->citationDefinitions[$this->referenceKey($label)] = $blocks;
            }
        }

        return implode("\n", $kept);
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>
     */
    private function parseBlocks(array $lines): array
    {
        $blocks = [];
        $index = 0;
        $count = count($lines);

        while ($index < $count) {
            $line = $lines[$index];
            $trimmed = trim($line);
            if ($trimmed === '') {
                $index++;
                continue;
            }

            if ($this->isAdornLine($trimmed) && $index + 2 < $count && trim($lines[$index + 1]) !== '' && $this->isMatchingAdornLine(trim($lines[$index + 2]), $trimmed)) {
                $blocks[] = $this->headingBlock(trim($lines[$index + 1]), $trimmed[0]);
                $index += 3;
                continue;
            }

            if ($index + 1 < $count && $this->isAdornLine(trim($lines[$index + 1])) && strlen(trim($lines[$index + 1])) >= strlen($trimmed)) {
                $blocks[] = $this->headingBlock($trimmed, trim($lines[$index + 1])[0]);
                $index += 2;
                continue;
            }

            if (preg_match('/^\s*\.\.\s+([A-Za-z0-9_-]+)::\s*(.*)$/u', $line, $match) === 1) {
                [$directiveBlocks, $index] = $this->parseDirective($lines, $index, strtolower($match[1]), trim($match[2]));
                foreach ($directiveBlocks as $block) {
                    $blocks[] = $block;
                }
                continue;
            }

            if (preg_match('/^\s*\.\.\s*$/u', $line) === 1) {
                [, $index] = $this->collectIndentedBlock($lines, $index + 1, true);
                continue;
            }

            if ($this->isFieldListStart($line)) {
                [$fieldLines, $index] = $this->collectFieldListLines($lines, $index);
                $blocks[] = $this->parseFieldList($fieldLines);
                continue;
            }

            if ($trimmed === '::') {
                [$codeLines, $index] = $this->collectIndentedBlock($lines, $index + 1, true);
                $blocks[] = $this->codeBlock($codeLines, $this->defaultCodeLanguage);
                continue;
            }

            if ($this->isGridTableStart($trimmed)) {
                [$tableLines, $index] = $this->collectGridTableLines($lines, $index);
                $blocks[] = $this->parseGridTable($tableLines);
                continue;
            }

            if ($this->isSimpleTableBoundary($trimmed)) {
                [$tableLines, $index] = $this->collectSimpleTableLines($lines, $index);
                $blocks[] = $this->parseSimpleTable($tableLines);
                continue;
            }

            if ($this->isHorizontalRule($trimmed)) {
                $blocks[] = new AstNode('horizontal_rule');
                $index++;
                continue;
            }

            if ($this->isListStart($line)) {
                [$listLines, $index] = $this->collectListLines($lines, $index);
                foreach ($this->parseListBlocks($listLines) as $list) {
                    $blocks[] = $list;
                }
                continue;
            }

            if ($this->indentWidth($line) >= 2) {
                [$quoteLines, $index] = $this->collectIndentedBlock($lines, $index, false);
                $blocks[] = new AstNode('blockquote', [], $this->parseBlocks($quoteLines));
                continue;
            }

            [$paragraphLines, $index] = $this->collectParagraphLines($lines, $index);
            $paragraph = trim(implode("\n", $paragraphLines));
            if ($paragraph === '') {
                continue;
            }

            if (str_ends_with($paragraph, '::') && $index < $count) {
                $visible = rtrim(substr($paragraph, 0, -1));
                if ($visible !== '') {
                    $blocks[] = $this->paragraphBlock($visible);
                }
                [$codeLines, $index] = $this->collectIndentedBlock($lines, $index, true);
                if ($codeLines !== []) {
                    $blocks[] = $this->codeBlock($codeLines, $this->defaultCodeLanguage);
                }
                continue;
            }

            $blocks[] = $this->paragraphBlock($paragraph);
        }

        return $blocks;
    }

    private function headingBlock(string $text, string $adornment): AstNode
    {
        $level = $this->headingLevel($adornment);
        $inlines = $this->parseInlines($text);

        return new AstNode('heading', [
            'level' => $level,
            'id' => $this->slugify($this->plainText($inlines)),
        ], $inlines);
    }

    private function headingLevel(string $adornment): int
    {
        if (!isset($this->headingLevels[$adornment])) {
            $this->headingLevels[$adornment] = min(6, count($this->headingLevels) + 1);
        }

        return $this->headingLevels[$adornment];
    }

    private function isAdornLine(string $line): bool
    {
        return strlen($line) >= 3 && preg_match('/^([!"#$%&\'()*+,\-.\/:;<=>?@\[\\\\\]^_`{|}~])\1+$/u', $line) === 1;
    }

    private function isMatchingAdornLine(string $line, string $opening): bool
    {
        return $this->isAdornLine($line) && $line[0] === $opening[0];
    }

    private function isHorizontalRule(string $line): bool
    {
        return strlen($line) >= 4 && preg_match('/^([*\-=~`#])\1+$/u', $line) === 1;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<AstNode>,1:int}
     */
    private function parseDirective(array $lines, int $index, string $name, string $argument): array
    {
        $this->directiveCount++;
        [$bodyLines, $nextIndex] = $this->collectIndentedBlock($lines, $index + 1, true);
        [$options, $body] = $this->splitDirectiveOptions($bodyLines);

        if ($name === 'highlight') {
            $this->defaultCodeLanguage = $argument === '' ? null : $this->sanitizeClass($argument);

            return [[], $nextIndex];
        }

        if (in_array($name, ['code', 'code-block', 'sourcecode'], true)) {
            $language = $argument !== '' ? $argument : $this->defaultCodeLanguage;
            $classes = [];
            if ($language !== null && $language !== '') {
                $classes[] = $this->sanitizeClass($language);
            }
            if (isset($options['number-lines'])) {
                $classes[] = 'numberLines';
            }
            foreach (preg_split('/\s+/', trim($options['class'] ?? '')) ?: [] as $class) {
                if ($class !== '') {
                    $classes[] = $this->sanitizeClass($class);
                }
            }
            $attrs = [];
            if (isset($options['number-lines']) && trim($options['number-lines']) !== '') {
                $attrs['data-rst-start-from'] = trim($options['number-lines']);
            }

            return [[
                $this->codeBlock($body, null, $classes, $attrs),
            ], $nextIndex];
        }

        if (in_array($name, ['image', 'figure'], true)) {
            $captionLines = array_values(array_filter($body, static fn (string $line): bool => trim($line) !== ''));
            $caption = $name === 'figure' && $captionLines !== [] ? trim(implode(' ', $captionLines)) : (string) ($options['alt'] ?? '');
            $captionInlines = $this->parseInlines($caption);
            $attributes = ['data-pandoc-source' => 'rst'];
            foreach (['width', 'height'] as $dimension) {
                if (isset($options[$dimension]) && trim($options[$dimension]) !== '') {
                    $attributes[$dimension] = trim($options[$dimension]);
                }
            }
            $classes = ['rst-image'];
            foreach (preg_split('/\s+/', trim($options['class'] ?? '')) ?: [] as $class) {
                if ($class !== '') {
                    $classes[] = $this->sanitizeClass($class);
                }
            }
            $image = new AstNode('image', [
                'url' => $argument,
                'alt' => $this->plainText($captionInlines),
                'attributes' => $attributes,
            ], $captionInlines);

            return [[new AstNode('figure', [
                'caption' => $this->plainText($captionInlines),
                'captionInlines' => $captionInlines,
                'classes' => $classes,
            ], [$image])], $nextIndex];
        }

        if ($name === 'table') {
            $table = $this->parseTableFromBody($body, $argument);

            return [[$table], $nextIndex];
        }

        if (in_array($name, ['note', 'warning', 'important', 'tip', 'caution', 'attention', 'danger', 'error', 'hint'], true)) {
            $content = $body;
            if ($argument !== '') {
                array_unshift($content, $argument, '');
            }
            $title = ucfirst($name);

            return [[new AstNode('div', [
                'classes' => ['admonition', 'admonition-' . $this->sanitizeClass($name)],
                'attributes' => ['data-rst-directive' => $name],
            ], array_merge([
                new AstNode('paragraph', ['text' => $title], [
                    new AstNode('strong', [], [new AstNode('text', ['text' => $title])]),
                ]),
            ], $this->parseBlocks($content)))], $nextIndex];
        }

        if ($name === 'raw') {
            return [[new AstNode('raw_block', ['format' => $argument === '' ? 'rst' : $argument, 'text' => implode("\n", $body)])], $nextIndex];
        }

        if ($name === 'class') {
            return [[new AstNode('div', [
                'classes' => array_values(array_filter(array_map(fn (string $class): string => $this->sanitizeClass($class), preg_split('/\s+/', $argument) ?: []))),
            ], $this->parseBlocks($body))], $nextIndex];
        }

        return [[new AstNode('raw_block', [
            'format' => 'rst',
            'text' => '.. ' . $name . '::' . ($argument === '' ? '' : ' ' . $argument) . "\n" . implode("\n", $bodyLines),
        ])], $nextIndex];
    }

    /**
     * @param list<string> $lines
     * @return array{0:array<string,string>,1:list<string>}
     */
    private function splitDirectiveOptions(array $lines): array
    {
        $options = [];
        $body = [];
        $inOptions = true;
        foreach ($lines as $line) {
            if ($inOptions && trim($line) === '') {
                $inOptions = false;
                continue;
            }
            if ($inOptions && preg_match('/^:([A-Za-z0-9_-]+):\s*(.*)$/u', trim($line), $match) === 1) {
                $options[strtolower($match[1])] = trim($match[2]);
                continue;
            }
            $inOptions = false;
            $body[] = $line;
        }

        return [$options, $body];
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectIndentedBlock(array $lines, int $index, bool $skipLeadingBlank): array
    {
        $count = count($lines);
        if ($skipLeadingBlank) {
            while ($index < $count && trim($lines[$index]) === '') {
                $index++;
            }
        }

        $collected = [];
        while ($index < $count) {
            $line = $lines[$index];
            if (trim($line) === '') {
                $collected[] = '';
                $index++;
                continue;
            }
            if ($this->indentWidth($line) === 0) {
                break;
            }
            $collected[] = $line;
            $index++;
        }

        return [$this->outdentLines($collected), $index];
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function outdentLines(array $lines): array
    {
        $minIndent = null;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $indent = $this->indentWidth($line);
            $minIndent = $minIndent === null ? $indent : min($minIndent, $indent);
        }
        $minIndent ??= 0;

        return array_map(static fn (string $line): string => trim($line) === '' ? '' : substr($line, min(strlen($line), $minIndent)), $lines);
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectParagraphLines(array $lines, int $index): array
    {
        $collected = [];
        $count = count($lines);
        while ($index < $count) {
            $line = $lines[$index];
            if (trim($line) === '') {
                break;
            }
            if ($collected !== [] && $this->isBlockStartLine($lines, $index)) {
                break;
            }
            $collected[] = $line;
            $index++;
        }

        return [$collected, $index];
    }

    /**
     * @param list<string> $lines
     */
    private function isBlockStartLine(array $lines, int $index): bool
    {
        $line = $lines[$index];
        $trimmed = trim($line);
        if ($trimmed === '') {
            return true;
        }

        return preg_match('/^\s*\.\.\s+([A-Za-z0-9_-]+)::/u', $line) === 1
            || preg_match('/^\s*\.\.\s*$/u', $line) === 1
            || $this->isFieldListStart($line)
            || $trimmed === '::'
            || $this->isGridTableStart($trimmed)
            || $this->isSimpleTableBoundary($trimmed)
            || $this->isListStart($line)
            || $this->indentWidth($line) >= 2
            || ($index + 1 < count($lines) && $this->isAdornLine(trim($lines[$index + 1])));
    }

    private function paragraphBlock(string $text): AstNode
    {
        $inlines = $this->parseInlines($text);

        return new AstNode('paragraph', ['text' => $this->plainText($inlines)], $inlines);
    }

    private function isFieldListStart(string $line): bool
    {
        return preg_match('/^:([^:]+):\s*(.*)$/u', $line) === 1;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectFieldListLines(array $lines, int $index): array
    {
        $collected = [];
        $count = count($lines);
        while ($index < $count) {
            $line = $lines[$index];
            if ($this->isFieldListStart($line)) {
                $collected[] = $line;
                $index++;
                while ($index < $count && (trim($lines[$index]) === '' || $this->indentWidth($lines[$index]) > 0) && !$this->isFieldListStart($lines[$index])) {
                    $collected[] = $lines[$index];
                    $index++;
                }
                continue;
            }
            break;
        }

        return [$collected, $index];
    }

    /**
     * @param list<string> $lines
     */
    private function parseFieldList(array $lines): AstNode
    {
        $this->fieldListCount++;
        $items = [];
        $current = null;
        foreach ($lines as $line) {
            if (preg_match('/^:([^:]+):\s*(.*)$/u', $line, $match) === 1) {
                if ($current !== null) {
                    $items[] = $this->fieldItem($current['term'], $current['body']);
                }
                $current = ['term' => trim($match[1]), 'body' => [trim($match[2])]];
                continue;
            }

            if ($current !== null) {
                $current['body'][] = trim($line);
            }
        }
        if ($current !== null) {
            $items[] = $this->fieldItem($current['term'], $current['body']);
        }

        return new AstNode('definition_list', ['classes' => ['rst-field-list']], $items);
    }

    /**
     * @param list<string> $body
     */
    private function fieldItem(string $term, array $body): AstNode
    {
        $termInlines = $this->parseInlines($term);
        $text = trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($body, static fn (string $line): bool => trim($line) !== ''))) ?? '');
        $bodyInlines = $this->parseInlines($text);

        return new AstNode('definition_item', [], [
            new AstNode('term', ['text' => $this->plainText($termInlines)], $termInlines),
            new AstNode('definition', [], [
                new AstNode('plain', ['text' => $this->plainText($bodyInlines)], $bodyInlines),
            ]),
        ]);
    }

    private function isListStart(string $line): bool
    {
        return preg_match('/^\s*(?:[*+\-]\s+|\d+[.)]\s+|\(\d+\)\s+)/u', $line) === 1;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectListLines(array $lines, int $index): array
    {
        $collected = [];
        $count = count($lines);
        while ($index < $count) {
            if (trim($lines[$index]) === '') {
                $collected[] = $lines[$index];
                $index++;
                continue;
            }
            if (!$this->isListStart($lines[$index])) {
                break;
            }
            $collected[] = $lines[$index];
            $index++;
        }

        return [$collected, $index];
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>
     */
    private function parseListBlocks(array $lines): array
    {
        $blocks = [];
        $currentType = null;
        $currentAttrs = [];
        $items = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^\s*([*+\-])\s+(.*)$/u', $line, $match) === 1) {
                $type = 'bullet_list';
                $attrs = [];
                $text = $match[2];
            } elseif (preg_match('/^\s*(?:\((\d+)\)|(\d+)[.)])\s+(.*)$/u', $line, $match) === 1) {
                $type = 'ordered_list';
                $attrs = ['start' => (int) ($match[1] !== '' ? $match[1] : $match[2]), 'style' => 'decimal', 'delimiter' => 'period'];
                $text = $match[3];
            } else {
                continue;
            }

            if ($currentType !== null && $currentType !== $type) {
                $blocks[] = new AstNode($currentType, $currentAttrs, $items);
                $items = [];
            }
            $currentType = $type;
            if ($items === []) {
                $currentAttrs = $attrs;
            }
            $inlines = $this->parseInlines($text);
            $items[] = new AstNode('list_item', [], [
                new AstNode('plain', ['text' => $this->plainText($inlines)], $inlines),
            ]);
        }

        if ($currentType !== null) {
            $blocks[] = new AstNode($currentType, $currentAttrs, $items);
        }

        return $blocks;
    }

    private function isGridTableStart(string $line): bool
    {
        return preg_match('/^\+(?:[-=]+\\+)+$/u', $line) === 1;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectGridTableLines(array $lines, int $index): array
    {
        $collected = [];
        $count = count($lines);
        while ($index < $count && (trim($lines[$index]) === '' || str_starts_with(trim($lines[$index]), '+') || str_starts_with(trim($lines[$index]), '|'))) {
            if (trim($lines[$index]) === '' && $collected !== []) {
                break;
            }
            $collected[] = $lines[$index];
            $index++;
        }

        return [$collected, $index];
    }

    /**
     * @param list<string> $lines
     */
    private function parseGridTable(array $lines, string $caption = ''): AstNode
    {
        $this->tableCount++;
        $rows = [];
        $headerRows = [];
        $current = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (str_starts_with($trimmed, '+')) {
                $isHeaderSeparator = str_contains($trimmed, '=');
                if ($current !== []) {
                    $row = $this->gridRowFromLines($current);
                    if ($isHeaderSeparator) {
                        $headerRows[] = $row;
                    } else {
                        $rows[] = $row;
                    }
                    $current = [];
                }
                continue;
            }
            if (str_starts_with($trimmed, '|')) {
                $current[] = $trimmed;
            }
        }

        $captionInlines = $this->parseInlines($caption);
        $children = [];
        if ($headerRows !== []) {
            $children[] = new AstNode('table_head', [], $headerRows);
        }
        $children[] = new AstNode('table_body', [], $rows);
        $columnCount = $this->tableColumnCount(array_merge($headerRows, $rows));

        return new AstNode('table', [
            'caption' => $this->plainText($captionInlines),
            'captionInlines' => $captionInlines,
            'alignments' => array_fill(0, $columnCount, 'default'),
            'htmlAttributes' => ['data-pandoc-source' => 'rst'],
        ], $children);
    }

    /**
     * @param list<string> $lines
     */
    private function gridRowFromLines(array $lines): AstNode
    {
        $columns = [];
        foreach ($lines as $line) {
            $parts = array_slice(explode('|', $line), 1, -1);
            foreach ($parts as $index => $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $columns[$index][] = $part;
            }
        }

        return new AstNode('table_row', [], array_map(
            fn (array $parts): AstNode => $this->tableCell(implode(' ', $parts), false),
            $columns
        ));
    }

    private function isSimpleTableBoundary(string $line): bool
    {
        return preg_match('/^(?:=+\s+)+=+$/u', $line) === 1;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>,1:int}
     */
    private function collectSimpleTableLines(array $lines, int $index): array
    {
        $collected = [];
        $boundaryCount = 0;
        $count = count($lines);
        while ($index < $count) {
            $trimmed = trim($lines[$index]);
            if ($trimmed === '') {
                break;
            }
            $collected[] = $lines[$index];
            if ($this->isSimpleTableBoundary($trimmed)) {
                $boundaryCount++;
                if ($boundaryCount >= 3) {
                    $index++;
                    break;
                }
            }
            $index++;
        }

        return [$collected, $index];
    }

    /**
     * @param list<string> $lines
     */
    private function parseSimpleTable(array $lines, string $caption = ''): AstNode
    {
        $this->tableCount++;
        $rows = [];
        $headRows = [];
        $bodyRows = [];
        foreach ($lines as $line) {
            if ($this->isSimpleTableBoundary(trim($line))) {
                $rows[] = ['boundary' => true, 'cells' => []];
                continue;
            }
            $rows[] = ['boundary' => false, 'cells' => preg_split('/\s{2,}/u', trim($line)) ?: []];
        }

        $seenBoundary = 0;
        foreach ($rows as $row) {
            if ($row['boundary']) {
                $seenBoundary++;
                continue;
            }
            $node = new AstNode('table_row', [], array_map(fn (string $cell): AstNode => $this->tableCell($cell, $seenBoundary === 1), $row['cells']));
            if ($seenBoundary === 1) {
                $headRows[] = $node;
            } else {
                $bodyRows[] = $node;
            }
        }

        if (count(array_filter($rows, static fn (array $row): bool => $row['boundary'])) < 3) {
            $bodyRows = array_merge($headRows, $bodyRows);
            $headRows = [];
        }

        $captionInlines = $this->parseInlines($caption);
        $children = [];
        if ($headRows !== []) {
            $children[] = new AstNode('table_head', [], $headRows);
        }
        $children[] = new AstNode('table_body', [], $bodyRows);
        $columnCount = $this->tableColumnCount(array_merge($headRows, $bodyRows));

        return new AstNode('table', [
            'caption' => $this->plainText($captionInlines),
            'captionInlines' => $captionInlines,
            'alignments' => array_fill(0, $columnCount, 'default'),
            'htmlAttributes' => ['data-pandoc-source' => 'rst'],
        ], $children);
    }

    /**
     * @param list<string> $body
     */
    private function parseTableFromBody(array $body, string $caption): AstNode
    {
        $trimmedBody = array_values(array_filter($body, static fn (string $line): bool => trim($line) !== ''));
        if ($trimmedBody !== [] && $this->isGridTableStart(trim($trimmedBody[0]))) {
            return $this->parseGridTable($trimmedBody, $caption);
        }
        if ($trimmedBody !== [] && $this->isSimpleTableBoundary(trim($trimmedBody[0]))) {
            return $this->parseSimpleTable($trimmedBody, $caption);
        }

        $this->tableCount++;
        $captionInlines = $this->parseInlines($caption);

        return new AstNode('table', [
            'caption' => $this->plainText($captionInlines),
            'captionInlines' => $captionInlines,
            'alignments' => [],
            'htmlAttributes' => ['data-pandoc-source' => 'rst'],
        ], [new AstNode('table_body')]);
    }

    private function tableCell(string $text, bool $header): AstNode
    {
        $inlines = $this->parseInlines($text);

        return new AstNode('table_cell', [
            'text' => $this->plainText($inlines),
            'header' => $header,
        ], [
            new AstNode('plain', ['text' => $this->plainText($inlines)], $inlines),
        ]);
    }

    /**
     * @param list<AstNode> $rows
     */
    private function tableColumnCount(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, count($row->children));
        }

        return $max;
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $htmlAttributes
     */
    private function codeBlock(array $lines, ?string $language = null, array $classes = [], array $htmlAttributes = []): AstNode
    {
        $this->codeBlockCount++;
        if ($language !== null && $language !== '') {
            array_unshift($classes, $this->sanitizeClass($language));
        }

        return new AstNode('code_block', [
            'text' => trim(implode("\n", $lines), "\n"),
            'classes' => array_values(array_filter($classes)),
            'htmlAttributes' => $htmlAttributes,
        ]);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $text): array
    {
        $nodes = [];
        $buffer = '';
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            $remaining = substr($text, $offset);

            if ($remaining[0] === "\n") {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('softbreak');
                $offset++;
                continue;
            }

            if ($remaining[0] === '\\' && strlen($remaining) > 1) {
                $buffer .= $remaining[1];
                $offset += 2;
                continue;
            }

            if (preg_match('/^https?:\/\/[^\s<>()]+/u', $remaining, $match) === 1 || preg_match('/^mailto:[^\s<>()]+/u', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $url = rtrim($match[0], '.,;)');
                $nodes[] = new AstNode('link', ['url' => $url], [new AstNode('text', ['text' => $url])]);
                $offset += strlen($url);
                continue;
            }

            if (preg_match('/^`([^`<]+?)\s*<([^>]+)>`__?/u', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('link', ['url' => trim($match[2])], $this->parseInlines(trim($match[1])));
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/^`([^`]+)`__?/u', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $label = trim($match[1]);
                $url = $this->references[$this->referenceKey($label)] ?? '';
                $nodes[] = $url === ''
                    ? new AstNode('text', ['text' => $label])
                    : new AstNode('link', ['url' => $url], $this->parseInlines($label));
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/^:([A-Za-z0-9_-]+):`([^`]+)`/u', $remaining, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $role = strtolower($match[1]);
                $inner = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $nodes[] = in_array($role, ['code', 'literal'], true)
                    ? new AstNode('code', ['text' => $inner, 'classes' => [$role]])
                    : new AstNode('span', ['classes' => [$this->sanitizeClass($role)]], [new AstNode('text', ['text' => $inner])]);
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/^\|([^|]+)\|/u', $remaining, $match) === 1) {
                $key = $this->referenceKey($match[1]);
                if (isset($this->substitutions[$key])) {
                    $this->flushText($nodes, $buffer);
                    array_push($nodes, ...$this->substitutions[$key]);
                    $offset += strlen($match[0]);
                    continue;
                }
            }

            if (preg_match('/^\[([^\]]+)\]_/u', $remaining, $match) === 1) {
                $key = $this->referenceKey($match[1]);
                $definition = str_starts_with($match[1], '#')
                    ? ($this->footnoteDefinitions[$key] ?? null)
                    : ($this->citationDefinitions[$key] ?? null);
                if (is_array($definition)) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = new AstNode('note', [
                        'id' => $match[1],
                        'noteType' => str_starts_with($match[1], '#') ? 'rst-footnote' : 'rst-citation',
                    ], $definition);
                    $offset += strlen($match[0]);
                    continue;
                }
            }

            if (str_starts_with($remaining, '``')) {
                $end = strpos($text, '``', $offset + 2);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = new AstNode('code', ['text' => html_entity_decode(substr($text, $offset + 2, $end - $offset - 2), ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
                    $offset = $end + 2;
                    continue;
                }
            }

            if (str_starts_with($remaining, '**')) {
                $end = strpos($text, '**', $offset + 2);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = new AstNode('strong', [], $this->parseInlines(substr($text, $offset + 2, $end - $offset - 2)));
                    $offset = $end + 2;
                    continue;
                }
            }

            if (str_starts_with($remaining, '*')) {
                $end = strpos($text, '*', $offset + 1);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = new AstNode('emph', [], $this->parseInlines(substr($text, $offset + 1, $end - $offset - 1)));
                    $offset = $end + 1;
                    continue;
                }
            }

            if (preg_match('/^([A-Za-z0-9][A-Za-z0-9_.:+-]*)_/u', $remaining, $match) === 1) {
                $key = $this->referenceKey($match[1]);
                if (isset($this->references[$key])) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = new AstNode('link', ['url' => $this->references[$key]], [new AstNode('text', ['text' => $match[1]])]);
                    $offset += strlen($match[0]);
                    continue;
                }
            }

            $buffer .= $text[$offset];
            $offset++;
        }

        $this->flushText($nodes, $buffer);

        return $nodes;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function flushText(array &$nodes, string &$buffer): void
    {
        if ($buffer === '') {
            return;
        }
        $text = html_entity_decode($buffer, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $buffer = '';
        if ($text === '') {
            return;
        }
        $lastIndex = array_key_last($nodes);
        if ($lastIndex !== null && $nodes[$lastIndex]->type === 'text') {
            $nodes[$lastIndex] = new AstNode('text', ['text' => (string) $nodes[$lastIndex]->attr('text', '') . $text]);
            return;
        }
        $nodes[] = new AstNode('text', ['text' => $text]);
    }

    private function indentWidth(string $line): int
    {
        return strlen($line) - strlen(ltrim($line, " \t"));
    }

    private function referenceKey(string $text): string
    {
        return strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }

    private function sanitizeClass(string $class): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $class) ?? '';
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                'image' => (string) $node->attr('alt', ''),
                default => $this->plainText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function slugify(string $text): string
    {
        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');

        return $slug === '' ? 'section' : $slug;
    }

    /**
     * @param array<string, string> $items
     * @return array{type:string,value:array<string,mixed>}
     */
    private function metaMap(array $items): array
    {
        $mapped = [];
        foreach ($items as $key => $value) {
            $mapped[$key] = ['type' => 'MetaInlines', 'value' => [new AstNode('text', ['text' => $value])]];
        }

        return ['type' => 'MetaMap', 'value' => $mapped];
    }
}
