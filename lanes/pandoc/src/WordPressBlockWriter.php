<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class WordPressBlockWriter
{
    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('WordPress writer expects a document node');
        }

        $blocks = [];
        $pendingList = [];
        foreach ($document->children as $node) {
            if ($node->type !== 'list_item') {
                $this->flushList($pendingList, $blocks);
            }
            if ($node->type === 'heading') {
                $level = (int) $node->attr('level', 2);
                $blocks[] = '<!-- wp:heading {"level":' . $level . '} -->' . "\n" . '<h' . $level . '>' . $this->esc((string) $node->attr('text', '')) . '</h' . $level . '>' . "\n" . '<!-- /wp:heading -->';
            } elseif ($node->type === 'paragraph') {
                $blocks[] = '<!-- wp:paragraph -->' . "\n" . '<p>' . $this->esc((string) $node->attr('text', '')) . '</p>' . "\n" . '<!-- /wp:paragraph -->';
            } elseif ($node->type === 'list_item') {
                $pendingList[] = '<li>' . $this->esc((string) $node->attr('text', '')) . '</li>';
            }
        }
        $this->flushList($pendingList, $blocks);

        return implode("\n\n", $blocks);
    }

    /**
     * @param list<string> $items
     * @param list<string> $blocks
     */
    private function flushList(array &$items, array &$blocks): void
    {
        if ($items === []) {
            return;
        }
        $blocks[] = '<!-- wp:list -->' . "\n" . '<ul>' . implode('', $items) . '</ul>' . "\n" . '<!-- /wp:list -->';
        $items = [];
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

