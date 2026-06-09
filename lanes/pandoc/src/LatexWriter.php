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
            'heading' => $this->renderHeading($node),
            'blockquote' => $this->renderBlockQuote($node),
            'div' => $this->renderBlockGroup($node->children),
            'code_block' => $this->renderCodeBlock($node),
            'horizontal_rule' => [
                '\begin{center}',
                '\rule{0.5\linewidth}{0.5pt}',
                '\end{center}',
            ],
            'bullet_list' => $this->renderList($node, false),
            'ordered_list' => $this->renderList($node, true),
            'raw_tex', 'raw_block' => $this->renderRawTexBlock($node),
            default => [],
        };
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<string>
     */
    private function renderBlockGroup(array $nodes): array
    {
        $lines = [];
        foreach ($nodes as $node) {
            $block = $this->renderBlock($node);
            if ($block === []) {
                continue;
            }

            if ($lines !== []) {
                $lines[] = '';
            }
            array_push($lines, ...$block);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderHeading(AstNode $node): array
    {
        $commands = [
            1 => 'section',
            2 => 'subsection',
            3 => 'subsubsection',
            4 => 'paragraph',
            5 => 'subparagraph',
            6 => 'subparagraph',
        ];
        $level = max(1, min(6, (int) $node->attr('level', 1)));

        return ['\\' . $commands[$level] . '{' . $this->renderInlines($node->children) . '}'];
    }

    /**
     * @return list<string>
     */
    private function renderBlockQuote(AstNode $node): array
    {
        return [
            '\begin{quote}',
            ...$this->renderBlockGroup($node->children),
            '\end{quote}',
        ];
    }

    /**
     * @return list<string>
     */
    private function renderCodeBlock(AstNode $node): array
    {
        return [
            '\begin{verbatim}',
            ...explode("\n", (string) $node->attr('text', '')),
            '\end{verbatim}',
        ];
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
                'space' => ' ',
                'softbreak', 'linebreak' => "\n",
                'emph' => $this->renderCommand('emph', $this->renderInlines($node->children)),
                'strong' => $this->renderCommand('textbf', $this->renderInlines($node->children)),
                'strikeout' => $this->renderCommand('sout', $this->renderInlines($node->children)),
                'superscript' => $this->renderCommand('textsuperscript', $this->renderInlines($node->children)),
                'subscript' => $this->renderCommand('textsubscript', $this->renderInlines($node->children)),
                'small_caps' => $this->renderCommand('textsc', $this->renderInlines($node->children)),
                'underline' => $this->renderCommand('underline', $this->renderInlines($node->children)),
                'span' => $this->renderInlines($node->children),
                'quoted' => $this->renderQuoted($node),
                'code' => $this->renderCommand('texttt', $this->escapeText((string) $node->attr('text', ''))),
                'link' => $this->renderLink($node),
                'image' => $this->renderImage($node),
                'citation' => (string) $node->attr('text', $this->renderInlines($node->children)),
                'citation_group' => (string) $node->attr('text', $this->renderInlines($node->children)),
                'note' => $this->renderNote($node),
                'math' => $this->mathConverter()->latexFor($node),
                'raw_tex' => (string) $node->attr('tex', $node->attr('text', '')),
                'raw_inline' => $this->renderRawInline($node),
                default => $this->renderInlines($node->children),
            };
        }

        return $text;
    }

    private function renderCommand(string $command, string $content): string
    {
        return '\\' . $command . '{' . $content . '}';
    }

    private function renderQuoted(AstNode $node): string
    {
        $text = $this->renderInlines($node->children);

        return (string) $node->attr('kind', '') === 'single'
            ? '`' . $text . '\''
            : '``' . $text . '\'\'';
    }

    private function renderLink(AstNode $node): string
    {
        $label = $this->renderInlines($node->children);
        $url = (string) $node->attr('url', '');
        if ($url === '') {
            return $label;
        }

        return '\href{' . $this->escapeText($url) . '}{' . ($label === '' ? $this->escapeText($url) : $label) . '}';
    }

    private function renderImage(AstNode $node): string
    {
        $url = (string) $node->attr('url', '');
        if ($url === '') {
            return '';
        }

        $alt = $this->renderInlines($node->children);
        $options = $alt === '' ? '' : '[alt={' . $alt . '}]';

        return '\includegraphics' . $options . '{' . $this->escapeText($url) . '}';
    }

    private function renderNote(AstNode $node): string
    {
        $note = implode("\n", $this->renderBlockGroup($node->children));

        return $note === '' ? '' : '\footnote{' . $note . '}';
    }

    private function renderRawInline(AstNode $node): string
    {
        $format = strtolower((string) $node->attr('format', ''));
        if (!in_array($format, ['tex', 'latex', 'context'], true)) {
            return '';
        }

        return (string) $node->attr('text', '');
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
            'space',
            'emph',
            'strong',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'underline',
            'span',
            'quoted',
            'softbreak',
            'linebreak',
            'code',
            'link',
            'image',
            'math',
            'citation',
            'citation_group',
            'note',
            'raw_tex',
            'raw_inline',
        ], true);
    }
}
