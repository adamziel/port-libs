<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownReader
{
    public function read(string $markdown): AstNode
    {
        $blocks = [];
        $paragraph = [];
        $listStack = [];
        $lines = preg_split('/\R/', rtrim($markdown, "\r\n")) ?: [];

        for ($index = 0, $count = count($lines); $index < $count; $index++) {
            $line = $lines[$index];
            $codeBlock = $this->tryReadFencedCodeBlock($lines, $index);
            if ($codeBlock !== null) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = $codeBlock;
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
            $rawHtmlBlock = $paragraph === [] && $listStack === [] ? $this->tryReadRawHtmlBlock($lines, $index) : null;
            if ($rawHtmlBlock !== null) {
                $blocks[] = $rawHtmlBlock;
                continue;
            }
            if ($this->isHorizontalRule($line)) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $blocks[] = new AstNode('horizontal_rule');
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushListStack($listStack, $blocks);
                $text = trim($m[2]);
                $blocks[] = new AstNode(
                    'heading',
                    ['level' => strlen($m[1]), 'text' => $text],
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

        return new AstNode('document', [], $blocks);
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
                'strikeout' => '<del>' . $this->renderInlineHtml($node->children) . '</del>',
                'superscript' => '<sup>' . $this->renderInlineHtml($node->children) . '</sup>',
                'subscript' => '<sub>' . $this->renderInlineHtml($node->children) . '</sub>',
                'code' => '<code>' . $this->escapeHtml((string) $node->attr('text', '')) . '</code>',
                'link' => '<a href="' . $this->escapeHtml((string) $node->attr('url', '')) . '">'
                    . $this->renderInlineHtml($node->children) . '</a>',
                default => $this->renderInlineHtml($node->children),
            };
        }

        return $html;
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
        $marker = $this->matchListMarker($lines[$index] ?? '');
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
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, style:string|null, delimiter:string|null} $firstMarker
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
            $marker = $this->matchListMarker($lines[$cursor]);
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
                $nextMarker = $blankCursor < $count ? $this->matchListMarker($lines[$blankCursor]) : null;
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
        }

        return [
            'node' => new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $children),
            'next' => $cursor,
        ];
    }

    /**
     * @param list<string> $lines
     * @param array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, style:string|null, delimiter:string|null} $marker
     * @return array{parts:list<array{type:string, text:string}|AstNode>, next:int, loose:bool, text:string, number:int|null}
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

        if ($firstText !== '') {
            $paragraph[] = $firstText;
        }
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

                if ($this->isHorizontalRule($lines[$next])) {
                    break;
                }

                $nextMarker = $this->matchListMarker($lines[$next]);
                if ($nextMarker !== null && $nextMarker['indent'] <= $baseIndent) {
                    break;
                }

                $nextIndent = $this->countIndentColumns($lines[$next]);
                if (($nextMarker !== null && $nextMarker['indent'] > $baseIndent) || $nextIndent >= $contentIndent) {
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

            $lineMarker = $this->matchListMarker($line);
            if ($lineMarker !== null) {
                if ($this->isSameListMarker($lineMarker, $baseIndent, $ordered, $style, $delimiter)) {
                    break;
                }

                if ($lineMarker['indent'] <= $baseIndent) {
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
     * @param array{parts:list<array{type:string, text:string}|AstNode>, next:int, loose:bool, text:string, number:int|null} $item
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

        return new AstNode('list_item', $attrs, $children);
    }

    /**
     * @return array{indent:int, ordered:bool, start:int|null, text:string, contentIndent:int, style:string|null, delimiter:string|null}|null
     */
    private function matchListMarker(string $line): ?array
    {
        if ($this->isHorizontalRule($line)) {
            return null;
        }

        $expanded = $this->expandTabsToSpaces($line);
        if (preg_match('/^( *)([-*+])( +)(.*)$/', $expanded, $m) === 1) {
            return [
                'indent' => strlen($m[1]),
                'ordered' => false,
                'start' => null,
                'text' => $m[4],
                'contentIndent' => strlen($m[1]) + 1 + strlen($m[3]),
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
                'style' => 'default',
                'delimiter' => 'default',
            ];
        }

        if (preg_match('/^( *)\(([0-9]{1,9}|[A-Za-z]+)\)( +)(.*)$/', $expanded, $m) === 1) {
            $ordinal = $this->parseOrderedMarkerOrdinal($m[2], 'two_parens', strlen($m[3]));
            if ($ordinal === null) {
                return null;
            }

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => $ordinal['start'],
                'text' => $m[4],
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 2 + strlen($m[3]),
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
                'style' => 'decimal',
                'delimiter' => $m[3] === ')' ? 'one_paren' : 'period',
            ];
        }

        if (preg_match('/^( *)([A-Za-z]+)([.)])( +)(.*)$/', $expanded, $m) === 1) {
            $delimiter = $m[3] === ')' ? 'one_paren' : 'period';
            $ordinal = $this->parseOrderedMarkerOrdinal($m[2], $delimiter, strlen($m[4]));
            if ($ordinal === null) {
                return null;
            }

            return [
                'indent' => strlen($m[1]),
                'ordered' => true,
                'start' => $ordinal['start'],
                'text' => $m[5],
                'contentIndent' => strlen($m[1]) + strlen($m[2]) + 1 + strlen($m[4]),
                'style' => $ordinal['style'],
                'delimiter' => $delimiter,
            ];
        }

        return null;
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

    /**
     * @return array{start:int, style:string}|null
     */
    private function parseOrderedMarkerOrdinal(string $token, string $delimiter, int $spacesAfterMarker): ?array
    {
        if (ctype_digit($token)) {
            return ['start' => (int) $token, 'style' => 'decimal'];
        }

        if (!ctype_alpha($token)) {
            return null;
        }

        $roman = $delimiter === 'period' ? $this->romanToInt($token) : null;
        if ($roman !== null && (strlen($token) > 1 || $spacesAfterMarker >= 2)) {
            return [
                'start' => $roman,
                'style' => ctype_upper($token) ? 'upper_roman' : 'lower_roman',
            ];
        }

        if (strlen($token) === 1 && $spacesAfterMarker >= 2) {
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
        $text = implode(' ', $paragraph);
        $blocks[] = new AstNode('paragraph', ['text' => $text], $this->parseInlines($text));
        $paragraph = [];
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
    private function parseInlines(string $text): array
    {
        $nodes = [];
        $buffer = '';
        $length = strlen($text);
        $offset = 0;

        while ($offset < $length) {
            if ($text[$offset] === '`') {
                $tickCount = $this->countBackticks($text, $offset);
                $end = $this->findMatchingBacktickRun($text, $offset + $tickCount, $tickCount);
                if ($end !== null && $end > $offset + $tickCount) {
                    $code = substr($text, $offset + $tickCount, $end - $offset - $tickCount);
                    $code = str_replace(["\r\n", "\r", "\n"], ' ', $code);
                    if (strlen($code) >= 2 && $code[0] === ' ' && $code[strlen($code) - 1] === ' ' && trim($code) !== '') {
                        $code = substr($code, 1, -1);
                    }
                    $this->flushText($buffer, $nodes);
                    $nodes[] = new AstNode('code', ['text' => $code]);
                    $offset = $end + $tickCount;
                    continue;
                }
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

            $emphasis = $this->tryParseEmphasisDelimiter($text, $offset);
            if ($emphasis !== null) {
                $this->flushText($buffer, $nodes);
                $nodes[] = $emphasis['node'];
                $offset = $emphasis['next'];
                continue;
            }

            if ($text[$offset] === '[' && preg_match('/\G\[([^\]\[]+)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', $text, $m, 0, $offset)) {
                $this->flushText($buffer, $nodes);
                $attrs = ['url' => $m[2]];
                if (isset($m[3])) {
                    $attrs['title'] = $m[3];
                }
                $nodes[] = new AstNode('link', $attrs, $this->parseInlines($m[1]));
                $offset += strlen($m[0]);
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
        if ($end === null || $end === $offset + 1) {
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
        foreach ([3, 2, 1] as $size) {
            if ($runLength < $size || !$this->canOpenInlineDelimiter($text, $offset, $char, $size)) {
                continue;
            }

            $end = $this->findClosingInlineDelimiter($text, $offset + $size, $char, $size);
            if ($end === null || $end <= $offset + $size) {
                continue;
            }

            $inner = $this->parseInlines(substr($text, $offset + $size, $end - $offset - $size));
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
            if ($this->countDelimiterRun($text, $position, $char) >= $size
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

        return !$this->isAsciiAlnum($previous) || !$this->isAsciiAlnum($next);
    }

    private function canCloseInlineDelimiter(string $text, int $offset, string $char, int $size): bool
    {
        if ($char !== '_') {
            return true;
        }

        $previous = $offset > 0 ? $text[$offset - 1] : '';
        $nextOffset = $offset + $size;
        $next = $nextOffset < strlen($text) ? $text[$nextOffset] : '';

        return !$this->isAsciiAlnum($previous) || !$this->isAsciiAlnum($next);
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

        $nodes[] = new AstNode('text', ['text' => $buffer]);
        $buffer = '';
    }
}
