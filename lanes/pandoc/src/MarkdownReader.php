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
            if (preg_match('/^(\s*)[-*+]\s+(.+)$/', $line, $m)) {
                $this->flushParagraph($paragraph, $blocks);
                $this->appendListItem($listStack, $blocks, false, null, strlen($m[1]), trim($m[2]));
                continue;
            }
            if (preg_match('/^(\s*)(\d+)[.)]\s+(.+)$/', $line, $m)) {
                $this->flushParagraph($paragraph, $blocks);
                $this->appendListItem($listStack, $blocks, true, (int) $m[2], strlen($m[1]), trim($m[3]));
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
        if (preg_match('/^ {0,3}<div(?:\s+[^>]*)?>[ \t]*(.*)$/i', $line, $m) !== 1) {
            return null;
        }

        $content = [];
        if ($this->appendDivContentUntilClose($content, $m[1])) {
            $inner = $this->read(implode("\n", $content));

            return new AstNode('div', [], $inner->children);
        }

        $cursor = $index + 1;
        $count = count($lines);
        while ($cursor < $count) {
            if ($this->appendDivContentUntilClose($content, $lines[$cursor])) {
                $index = $cursor;
                $inner = $this->read(implode("\n", $content));

                return new AstNode('div', [], $inner->children);
            }
            $cursor++;
        }

        return null;
    }

    /**
     * @param list<string> $content
     */
    private function appendDivContentUntilClose(array &$content, string $line): bool
    {
        $closing = stripos($line, '</div>');
        if ($closing !== false) {
            $beforeClose = substr($line, 0, $closing);
            if ($beforeClose !== '') {
                $content[] = $beforeClose;
            }

            return true;
        }

        $content[] = $line;

        return false;
    }

    private function isBlockQuoteLine(string $line): bool
    {
        return preg_match('/^ {0,3}>/', $line) === 1;
    }

    private function stripBlockQuoteMarker(string $line): string
    {
        return preg_replace('/^ {0,3}>[ \t]?/', '', $line, 1) ?? $line;
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
            while ($cursor < $count) {
                if (trim($lines[$cursor]) === '') {
                    $next = $cursor + 1;
                    if ($next < $count && $this->isDefinitionMarker($lines[$next])) {
                        $cursor = $next;
                        continue;
                    }
                    break;
                }

                if (!$this->isDefinitionMarker($lines[$cursor])) {
                    break;
                }

                $definitions[] = $this->readDefinition($lines, $cursor, $looseDefinition);
                $looseDefinition = false;
            }

            if ($looseFirstDefinition && $definitions !== []) {
                $lastDefinitionIndex = array_key_last($definitions);
                $definition = $definitions[$lastDefinitionIndex];
                $definitions[$lastDefinitionIndex] = new AstNode(
                    $definition->type,
                    array_merge($definition->attrs, ['loose' => true]),
                    $definition->children
                );
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

        return !preg_match('/^(#{1,6})\s+|^[-*+]\s+|^\d+[.)]\s+|^\s{0,4}:/', $line);
    }

    private function isDefinitionMarker(string $line): bool
    {
        return preg_match('/^\s{0,4}:\s*(.*)$/', $line) === 1;
    }

    /**
     * @param list<string> $lines
     */
    private function readDefinition(array $lines, int &$cursor, bool $loose): AstNode
    {
        preg_match('/^\s{0,4}:\s*(.*)$/', $lines[$cursor], $m);
        $blocks = $this->parseDefinitionBlocks(trim($m[1] ?? ''));
        $cursor++;
        $count = count($lines);

        while ($cursor < $count) {
            $line = $lines[$cursor];
            if (trim($line) === '') {
                $next = $cursor + 1;
                if ($next < $count && $this->isDefinitionMarker($lines[$next])) {
                    $cursor = $next;
                    break;
                }
                if ($next < $count && $this->isIndentedDefinitionContinuation($lines[$next])) {
                    $cursor = $next;
                    $this->appendDefinitionParagraph($blocks, trim($this->stripDefinitionContinuationIndent($lines[$cursor])));
                    $cursor++;
                    continue;
                }
                break;
            }

            if ($this->isDefinitionMarker($line)) {
                break;
            }

            if ($this->isIndentedDefinitionContinuation($line)) {
                $this->appendDefinitionParagraph($blocks, trim($this->stripDefinitionContinuationIndent($line)));
            } else {
                $this->appendLazyDefinitionLine($blocks, trim($line));
            }
            $cursor++;
        }

        return new AstNode('definition', ['loose' => $loose], $blocks);
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
    private function appendDefinitionParagraph(array &$blocks, string $text): void
    {
        if ($text === '') {
            return;
        }

        foreach ($this->parseDefinitionBlocks($text) as $block) {
            $blocks[] = $block;
        }
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
        if (preg_match('/^[-*+]\s+(.+)$/', $content, $m)) {
            $text = trim($m[1]);

            return [
                new AstNode('bullet_list', [], [
                    new AstNode('list_item', ['text' => $text], $this->parseInlines($text)),
                ]),
            ];
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

            if (substr($text, $offset, 2) === '**') {
                $end = strpos($text, '**', $offset + 2);
                if ($end !== false && $end > $offset + 2) {
                    $this->flushText($buffer, $nodes);
                    $nodes[] = new AstNode('strong', [], $this->parseInlines(substr($text, $offset + 2, $end - $offset - 2)));
                    $offset = $end + 2;
                    continue;
                }
            }

            if ($text[$offset] === '*') {
                $end = strpos($text, '*', $offset + 1);
                if ($end !== false && $end > $offset + 1) {
                    $this->flushText($buffer, $nodes);
                    $nodes[] = new AstNode('emph', [], $this->parseInlines(substr($text, $offset + 1, $end - $offset - 1)));
                    $offset = $end + 1;
                    continue;
                }
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
