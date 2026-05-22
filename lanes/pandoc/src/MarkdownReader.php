<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownReader
{
    public function read(string $markdown): AstNode
    {
        $blocks = [];
        $paragraph = [];
        $pendingList = null;
        $lines = preg_split('/\R/', trim($markdown)) ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushList($pendingList, $blocks);
                $text = trim($m[2]);
                $blocks[] = new AstNode(
                    'heading',
                    ['level' => strlen($m[1]), 'text' => $text],
                    $this->parseInlines($text)
                );
                continue;
            }
            if (preg_match('/^[-*+]\s+(.+)$/', $line, $m)) {
                $this->flushParagraph($paragraph, $blocks);
                if ($pendingList !== null && $pendingList['ordered']) {
                    $this->flushList($pendingList, $blocks);
                }
                $this->appendListItem($pendingList, false, null, trim($m[1]));
                continue;
            }
            if (preg_match('/^(\d+)[.)]\s+(.+)$/', $line, $m)) {
                $this->flushParagraph($paragraph, $blocks);
                if ($pendingList !== null && !$pendingList['ordered']) {
                    $this->flushList($pendingList, $blocks);
                }
                $this->appendListItem($pendingList, true, (int) $m[1], trim($m[2]));
                continue;
            }
            if (trim($line) === '') {
                $this->flushParagraph($paragraph, $blocks);
                $this->flushList($pendingList, $blocks);
                continue;
            }
            $this->flushList($pendingList, $blocks);
            $paragraph[] = trim($line);
        }
        $this->flushParagraph($paragraph, $blocks);
        $this->flushList($pendingList, $blocks);

        return new AstNode('document', [], $blocks);
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
     * @param array{ordered: bool, start: int|null, items: list<AstNode>}|null $pendingList
     */
    private function appendListItem(?array &$pendingList, bool $ordered, ?int $number, string $text): void
    {
        if ($pendingList !== null && $pendingList['ordered'] !== $ordered) {
            throw new \LogicException('Cannot mix ordered and bullet list items in one pending list.');
        }

        if ($pendingList === null) {
            $pendingList = [
                'ordered' => $ordered,
                'start' => $ordered ? $number : null,
                'items' => [],
            ];
        }

        $attrs = ['text' => $text];
        if ($number !== null) {
            $attrs['number'] = $number;
        }

        $pendingList['items'][] = new AstNode('list_item', $attrs, $this->parseInlines($text));
    }

    /**
     * @param array{ordered: bool, start: int|null, items: list<AstNode>}|null $pendingList
     * @param list<AstNode> $blocks
     */
    private function flushList(?array &$pendingList, array &$blocks): void
    {
        if ($pendingList === null) {
            return;
        }

        $attrs = $pendingList['ordered'] ? ['start' => $pendingList['start'] ?? 1] : [];
        $blocks[] = new AstNode($pendingList['ordered'] ? 'ordered_list' : 'bullet_list', $attrs, $pendingList['items']);
        $pendingList = null;
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
