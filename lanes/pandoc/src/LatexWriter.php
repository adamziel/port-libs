<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class LatexWriter
{
    public function __construct(private readonly ?MathTexConverter $mathConverter = null)
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('LaTeX writer expects a document node');
        }

        $blocks = [];
        foreach ($document->children as $node) {
            $lines = $this->renderBlock($node);
            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @return list<string>
     */
    private function renderBlock(AstNode $node): array
    {
        return match ($node->type) {
            'paragraph', 'plain' => [$this->renderInlines($node->children)],
            'bullet_list' => $this->renderList($node, false),
            'ordered_list' => $this->renderList($node, true),
            'raw_tex', 'raw_block' => $this->renderRawTexBlock($node),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function renderList(AstNode $node, bool $ordered): array
    {
        $lines = [$ordered ? '\begin{enumerate}' : '\begin{itemize}'];
        foreach ($node->children as $item) {
            if ($item->type === 'list_item') {
                array_push($lines, ...$this->renderListItem($item));
            }
        }
        $lines[] = $ordered ? '\end{enumerate}' : '\end{itemize}';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderListItem(AstNode $item): array
    {
        $task = $item->attr('taskChecked', null);
        if (is_bool($task)) {
            $lines = [$task ? '\item[$\boxtimes$]' : '\item[$\square$]'];
        } else {
            $lines = ['\item'];
        }

        $paragraphs = $this->listItemParagraphs($item);
        foreach ($paragraphs as $index => $paragraph) {
            if ($index > 0) {
                $lines[] = '';
            }
            $lines[] = '  ' . $paragraph;
        }

        foreach ($item->children as $child) {
            if ($child->type === 'bullet_list' || $child->type === 'ordered_list') {
                array_push($lines, ...$this->renderBlock($child));
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function listItemParagraphs(AstNode $item): array
    {
        $paragraphs = [];
        $inlineChildren = [];
        foreach ($item->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlineChildren[] = $child;
                continue;
            }

            if ($child->type === 'paragraph' || $child->type === 'plain') {
                $paragraphs[] = $this->renderInlines($child->children);
            }
        }

        if ($inlineChildren !== []) {
            array_unshift($paragraphs, $this->renderInlines($inlineChildren));
        }

        return $paragraphs;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text' => $this->escapeText((string) $node->attr('text', '')),
                'softbreak', 'linebreak' => "\n",
                'emph', 'strong' => $this->renderInlines($node->children),
                'code' => $this->escapeText((string) $node->attr('text', '')),
                'math' => $this->mathConverter()->latexFor($node),
                'raw_tex' => (string) $node->attr('tex', $node->attr('text', '')),
                default => $this->renderInlines($node->children),
            };
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private function renderRawTexBlock(AstNode $node): array
    {
        $format = strtolower((string) $node->attr('format', ''));
        if ($node->type !== 'raw_tex' && !in_array($format, ['tex', 'latex', 'context'], true)) {
            return [];
        }

        return explode("\n", (string) $node->attr('tex', $node->attr('text', '')));
    }

    private function mathConverter(): MathTexConverter
    {
        return $this->mathConverter ?? new MathTexConverter();
    }

    private function escapeText(string $text): string
    {
        return strtr($text, [
            '\\' => '\textbackslash{}',
            '{' => '\{',
            '}' => '\}',
            '$' => '\$',
            '&' => '\&',
            '%' => '\%',
            '#' => '\#',
            '_' => '\_',
        ]);
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'emph',
            'strong',
            'softbreak',
            'linebreak',
            'code',
            'math',
            'raw_tex',
        ], true);
    }
}
