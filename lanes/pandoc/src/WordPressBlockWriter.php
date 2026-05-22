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
            } elseif ($node->type === 'plain') {
                $blocks[] = '<!-- wp:paragraph -->'
                    . "\n" . '<p>' . $this->renderInlines($node) . '</p>'
                    . "\n" . '<!-- /wp:paragraph -->';
            } elseif ($node->type === 'bullet_list') {
                $blocks[] = $this->renderList($node, false);
            } elseif ($node->type === 'ordered_list') {
                $blocks[] = $this->renderList($node, true);
            } elseif ($node->type === 'definition_list') {
                $blocks[] = $this->renderDefinitionList($node);
            } elseif ($node->type === 'raw_html') {
                $blocks[] = $this->renderRawHtmlBlock($node);
            } elseif ($node->type === 'raw_tex') {
                $blocks[] = $this->renderRawTexBlock($node);
            } elseif ($node->type === 'code_block') {
                $blocks[] = $this->renderCodeBlock($node);
            } elseif ($node->type === 'blockquote') {
                $blocks[] = $this->renderBlockQuote($node);
            } elseif ($node->type === 'div') {
                $blocks[] = $this->renderDivBlock($node);
            } elseif ($node->type === 'horizontal_rule') {
                $blocks[] = $this->renderHorizontalRule();
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
        $paragraphCount = 0;
        foreach ($item->children as $child) {
            if ($child->type === 'paragraph') {
                $paragraphCount++;
            }
        }
        $wrapParagraphs = (bool) $item->attr('loose', false) || $paragraphCount > 1;

        foreach ($item->children as $child) {
            if ($child->type === 'bullet_list') {
                $html .= $this->renderListHtml($child, false);
                continue;
            }
            if ($child->type === 'ordered_list') {
                $html .= $this->renderListHtml($child, true);
                continue;
            }
            if ($child->type === 'paragraph') {
                $rendered = $this->renderInlines($child);
                $html .= $wrapParagraphs ? '<p>' . $rendered . '</p>' : $rendered;
                continue;
            }

            $html .= $this->renderInlineNode($child);
        }

        return '<li>' . $html . '</li>';
    }

    private function renderDefinitionList(AstNode $node): string
    {
        return '<!-- wp:html -->' . "\n" . $this->renderDefinitionListHtml($node) . "\n" . '<!-- /wp:html -->';
    }

    private function renderRawHtmlBlock(AstNode $node): string
    {
        return '<!-- wp:html -->'
            . "\n" . (string) $node->attr('html', '')
            . "\n" . '<!-- /wp:html -->';
    }

    private function renderRawTexBlock(AstNode $node): string
    {
        return '<!-- wp:code -->'
            . "\n" . $this->renderRawTexBlockHtml($node)
            . "\n" . '<!-- /wp:code -->';
    }

    private function renderDefinitionListHtml(AstNode $node): string
    {
        $html = '<dl>';
        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            $children = $item->children;
            $term = array_shift($children);
            if (!$term instanceof AstNode || $term->type !== 'term') {
                $term = new AstNode('term', ['text' => (string) $item->attr('term', '')]);
            }
            $html .= '<dt>' . $this->renderInlines($term) . '</dt>';

            foreach ($children as $definition) {
                if ($definition->type !== 'definition') {
                    continue;
                }
                $html .= '<dd>' . $this->renderDefinitionBlocks($definition) . '</dd>';
            }
        }
        $html .= '</dl>';

        return $html;
    }

    private function renderCodeBlock(AstNode $node): string
    {
        return '<!-- wp:code -->'
            . "\n" . $this->renderCodeBlockHtml($node)
            . "\n" . '<!-- /wp:code -->';
    }

    private function renderCodeBlockHtml(AstNode $node): string
    {
        $classes = $node->attr('classes', []);
        $language = is_array($classes) && isset($classes[0]) ? $this->sanitizeCodeClass((string) $classes[0]) : '';
        $codeAttrs = $language === '' ? '' : ' class="language-' . $this->esc($language) . '"';

        return '<pre class="wp-block-code"><code' . $codeAttrs . '>' . $this->esc((string) $node->attr('text', '')) . '</code></pre>';
    }

    private function renderRawTexBlockHtml(AstNode $node): string
    {
        return '<pre class="wp-block-code"><code class="language-tex">'
            . $this->esc((string) $node->attr('tex', ''))
            . '</code></pre>';
    }

    private function renderBlockQuote(AstNode $node): string
    {
        return '<!-- wp:quote -->'
            . "\n" . '<blockquote class="wp-block-quote">' . $this->renderBlocksAsHtml($node->children) . '</blockquote>'
            . "\n" . '<!-- /wp:quote -->';
    }

    private function renderDivBlock(AstNode $node): string
    {
        return '<!-- wp:html -->'
            . "\n" . '<div>' . $this->renderBlocksAsHtml($node->children) . '</div>'
            . "\n" . '<!-- /wp:html -->';
    }

    private function renderHorizontalRule(): string
    {
        return '<!-- wp:separator -->'
            . "\n" . '<hr class="wp-block-separator has-alpha-channel-opacity"/>'
            . "\n" . '<!-- /wp:separator -->';
    }

    private function sanitizeCodeClass(string $class): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $class) ?? '';
    }

    private function renderDefinitionBlocks(AstNode $definition): string
    {
        $html = '';
        $paragraphCount = 0;
        foreach ($definition->children as $child) {
            if ($child->type === 'paragraph') {
                $paragraphCount++;
            }
        }
        $wrapParagraphs = (bool) $definition->attr('loose', false) || $paragraphCount > 1;

        foreach ($definition->children as $child) {
            if ($child->type === 'bullet_list') {
                $html .= $this->renderListHtml($child, false);
                continue;
            }
            if ($child->type === 'ordered_list') {
                $html .= $this->renderListHtml($child, true);
                continue;
            }
            if ($child->type === 'paragraph') {
                $rendered = $this->renderInlines($child);
                $html .= $wrapParagraphs ? '<p>' . $rendered . '</p>' : $rendered;
                continue;
            }
            if ($child->type === 'raw_html') {
                $html .= (string) $child->attr('html', '');
                continue;
            }
            if ($child->type === 'raw_tex') {
                $html .= $this->renderRawTexBlockHtml($child);
                continue;
            }

            $html .= $this->renderInlineNode($child);
        }

        return $html;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderBlocksAsHtml(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if ($block->type === 'paragraph') {
                $html .= '<p>' . $this->renderInlines($block) . '</p>';
                continue;
            }
            if ($block->type === 'plain') {
                $html .= $this->renderInlines($block);
                continue;
            }
            if ($block->type === 'heading') {
                $level = (int) $block->attr('level', 2);
                $html .= '<h' . $level . '>' . $this->renderInlines($block) . '</h' . $level . '>';
                continue;
            }
            if ($block->type === 'bullet_list') {
                $html .= $this->renderListHtml($block, false);
                continue;
            }
            if ($block->type === 'ordered_list') {
                $html .= $this->renderListHtml($block, true);
                continue;
            }
            if ($block->type === 'definition_list') {
                $html .= $this->renderDefinitionListHtml($block);
                continue;
            }
            if ($block->type === 'code_block') {
                $html .= $this->renderCodeBlockHtml($block);
                continue;
            }
            if ($block->type === 'blockquote') {
                $html .= '<blockquote>' . $this->renderBlocksAsHtml($block->children) . '</blockquote>';
                continue;
            }
            if ($block->type === 'horizontal_rule') {
                $html .= '<hr/>';
                continue;
            }
            if ($block->type === 'raw_html') {
                $html .= (string) $block->attr('html', '');
                continue;
            }
            if ($block->type === 'raw_tex') {
                $html .= $this->renderRawTexBlockHtml($block);
                continue;
            }
            if ($block->type === 'div') {
                $html .= '<div>' . $this->renderBlocksAsHtml($block->children) . '</div>';
            }
        }

        return $html;
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
            'strikeout' => '<del>' . $this->renderInlines($node) . '</del>',
            'superscript' => '<sup>' . $this->renderInlines($node) . '</sup>',
            'subscript' => '<sub>' . $this->renderInlines($node) . '</sub>',
            'quoted' => $this->renderQuotedInline($node),
            'math' => $this->renderMathInline($node),
            'raw_tex' => '<span class="pandoc-raw-tex">' . $this->esc((string) $node->attr('tex', '')) . '</span>',
            'code' => '<code>' . $this->esc((string) $node->attr('text', '')) . '</code>',
            'link' => '<a' . $this->renderLinkAttrs($node) . '>' . $this->renderInlines($node) . '</a>',
            default => $this->renderInlines($node),
        };
    }

    private function renderLinkAttrs(AstNode $node): string
    {
        $attrs = ' href="' . $this->esc((string) $node->attr('url', '')) . '"';
        $title = (string) $node->attr('title', '');
        if ($title !== '') {
            $attrs .= ' title="' . $this->esc($title) . '"';
        }

        return $attrs;
    }

    private function renderMathInline(AstNode $node): string
    {
        $open = $node->attr('display') === true ? '\\[' : '\\(';
        $close = $node->attr('display') === true ? '\\]' : '\\)';
        $class = $node->attr('display') === true ? 'display' : 'inline';

        return '<span class="math ' . $class . '">'
            . $this->esc($open . (string) $node->attr('text', '') . $close)
            . '</span>';
    }

    private function renderQuotedInline(AstNode $node): string
    {
        if ($node->attr('kind') === 'single') {
            return "\u{2018}" . $this->renderInlines($node) . "\u{2019}";
        }

        return "\u{201C}" . $this->renderInlines($node) . "\u{201D}";
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
