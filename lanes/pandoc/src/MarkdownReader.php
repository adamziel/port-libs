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
        $lines = preg_split('/\R/', trim($markdown)) ?: [];

        for ($index = 0, $count = count($lines); $index < $count; $index++) {
            $line = $lines[$index];
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
            $looseDefinition = $looseFirstDefinition;
            while ($cursor < $count) {
                if (trim($lines[$cursor]) === '') {
                    $next = $cursor + 1;
                    if ($next < $count && $this->isDefinitionMarker($lines[$next])) {
                        $looseDefinition = true;
                        $cursor = $next;
                        continue;
                    }
                    break;
                }

                if (!preg_match('/^\s{0,4}:\s*(.*)$/', $lines[$cursor], $m)) {
                    break;
                }

                $content = trim($m[1]);
                $definitions[] = new AstNode(
                    'definition',
                    ['loose' => $looseDefinition],
                    $this->parseDefinitionBlocks($content)
                );
                $looseDefinition = false;
                $cursor++;
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

        return !preg_match('/^(#{1,6})\s+|^[-*+]\s+|^\d+[.)]\s+|^\s{0,4}:/', $line);
    }

    private function isDefinitionMarker(string $line): bool
    {
        return preg_match('/^\s{0,4}:\s*(.*)$/', $line) === 1;
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
                $end = strpos($text, '`', $offset + 1);
                if ($end !== false && $end > $offset + 1) {
                    $this->flushText($buffer, $nodes);
                    $nodes[] = new AstNode('code', ['text' => substr($text, $offset + 1, $end - $offset - 1)]);
                    $offset = $end + 1;
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
