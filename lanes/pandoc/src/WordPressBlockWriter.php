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
                $blocks[] = '<!-- wp:heading {"level":' . $level . '} -->'
                    . "\n" . '<h' . $level . '>' . $this->renderInlines($node) . '</h' . $level . '>'
                    . "\n" . '<!-- /wp:heading -->';
            } elseif ($node->type === 'paragraph') {
                $blocks[] = '<!-- wp:paragraph -->'
                    . "\n" . '<p>' . $this->renderInlines($node) . '</p>'
                    . "\n" . '<!-- /wp:paragraph -->';
            } elseif ($node->type === 'bullet_list') {
                $blocks[] = $this->renderList($node, false);
            } elseif ($node->type === 'ordered_list') {
                $blocks[] = $this->renderList($node, true);
            } elseif ($node->type === 'list_item') {
                $pendingList[] = '<li>' . $this->renderInlines($node) . '</li>';
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

    private function renderList(AstNode $node, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $start = (int) $node->attr('start', 1);
        $comment = '<!-- wp:list -->';
        $tagAttrs = '';
        if ($ordered) {
            $attrs = ['ordered' => true];
            if ($start > 1) {
                $attrs['start'] = $start;
                $tagAttrs = ' start="' . $start . '"';
            }
            $comment = '<!-- wp:list ' . json_encode($attrs, JSON_THROW_ON_ERROR) . ' -->';
        }
        $items = [];

        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }
            $items[] = $this->renderListItem($item);
        }

        return $comment
            . "\n" . '<' . $tag . $tagAttrs . '>' . implode('', $items) . '</' . $tag . '>'
            . "\n" . '<!-- /wp:list -->';
    }

    private function renderListHtml(AstNode $node, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $start = (int) $node->attr('start', 1);
        $tagAttrs = $ordered && $start > 1 ? ' start="' . $start . '"' : '';
        $items = [];
        foreach ($node->children as $item) {
            if ($item->type === 'list_item') {
                $items[] = $this->renderListItem($item);
            }
        }

        return '<' . $tag . $tagAttrs . '>' . implode('', $items) . '</' . $tag . '>';
    }

    private function renderListItem(AstNode $item): string
    {
        $html = '';
        foreach ($item->children as $child) {
            if ($child->type === 'bullet_list') {
                $html .= $this->renderListHtml($child, false);
                continue;
            }
            if ($child->type === 'ordered_list') {
                $html .= $this->renderListHtml($child, true);
                continue;
            }

            $html .= $this->renderInlineNode($child);
        }

        return '<li>' . $html . '</li>';
    }

    private function renderInlines(AstNode $node): string
    {
        if ($node->children === []) {
            return $this->esc((string) $node->attr('text', ''));
        }

        $html = '';
        foreach ($node->children as $child) {
            $html .= $this->renderInlineNode($child);
        }

        return $html;
    }

    private function renderInlineNode(AstNode $node): string
    {
        return match ($node->type) {
            'text' => $this->esc((string) $node->attr('text', '')),
            'emph' => '<em>' . $this->renderInlines($node) . '</em>',
            'strong' => '<strong>' . $this->renderInlines($node) . '</strong>',
            'code' => '<code>' . $this->esc((string) $node->attr('text', '')) . '</code>',
            'link' => '<a href="' . $this->esc((string) $node->attr('url', '')) . '">' . $this->renderInlines($node) . '</a>',
            default => $this->renderInlines($node),
        };
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
