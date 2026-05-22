<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownReader
{
    public function read(string $markdown): AstNode
    {
        $blocks = [];
        $paragraph = [];
        $lines = preg_split('/\R/', trim($markdown)) ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $this->flushParagraph($paragraph, $blocks);
                $blocks[] = new AstNode('heading', ['level' => strlen($m[1]), 'text' => trim($m[2])]);
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
                $this->flushParagraph($paragraph, $blocks);
                $blocks[] = new AstNode('list_item', ['text' => trim($m[1])]);
                continue;
            }
            if (trim($line) === '') {
                $this->flushParagraph($paragraph, $blocks);
                continue;
            }
            $paragraph[] = trim($line);
        }
        $this->flushParagraph($paragraph, $blocks);

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
        $blocks[] = new AstNode('paragraph', ['text' => implode(' ', $paragraph)]);
        $paragraph = [];
    }
}

