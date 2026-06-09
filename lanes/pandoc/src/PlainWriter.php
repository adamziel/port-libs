<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PlainWriter
{
    /**
     * @param array{columns?: int, wrap?: string, ambiguousWidth?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        return $this->writeWithDiagnostics($document)['text'];
    }

    /**
     * @return array{
     *   text:string,
     *   diagnostics:array{
     *     writer:string,
     *     wrapMode:string,
     *     columns:int,
     *     blockCount:int,
     *     wrappedBlockCount:int,
     *     outputLineCount:int,
     *     maxOutputDisplayWidth:int,
     *     hardBreakCount:int,
     *     softBreakOpportunityCount:int,
     *     protectedSeparatorCount:int,
     *     lineEndingNormalizationCount:int,
     *     blocks:list<array{
     *       blockIndex:int,
     *       blockType:string,
     *       sourceLineCount:int,
     *       outputLineCount:int,
     *       maxSourceDisplayWidth:int,
     *       maxOutputDisplayWidth:int,
     *       wrapped:bool,
     *       protectedSeparatorCount:int,
     *       lineEndingNormalizationCount:int
     *     }>
     *   }
     * }
     */
    public function writeWithDiagnostics(AstNode $document): array
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Plain writer expects a document node');
        }

        $columns = $this->columns();
        $wrapMode = $this->wrapMode();
        $ambiguousWidth = (string) ($this->options['ambiguousWidth'] ?? 'narrow');
        $blocks = [];
        $blockDiagnostics = [];
        $wrappedBlockCount = 0;
        $outputLineCount = 0;
        $maxOutputDisplayWidth = 0;
        $hardBreakCount = 0;
        $softBreakOpportunityCount = 0;
        $protectedSeparatorCount = 0;
        $lineEndingNormalizationCount = 0;

        foreach ($document->children as $index => $node) {
            if (!$node instanceof AstNode) {
                throw new \InvalidArgumentException('Plain writer document children must be AST nodes');
            }

            $source = $this->renderBlock($node);
            if ($source === '') {
                continue;
            }

            [$source, $lineEndings] = UnicodeText::normalizeLineEndings($source);
            $sourceLines = explode("\n", $source);
            $wrappedLines = $this->wrapLines($source, $columns, $wrapMode, $ambiguousWidth);
            $output = implode("\n", $wrappedLines);
            $blocks[] = $output;

            $sourceLineCount = count($sourceLines);
            $wrappedLineCount = count($wrappedLines);
            $wrapped = $wrappedLineCount > $sourceLineCount;
            if ($wrapped) {
                ++$wrappedBlockCount;
            }

            $sourceMax = $this->maxDisplayWidth($sourceLines, $ambiguousWidth);
            $outputMax = $this->maxDisplayWidth($wrappedLines, $ambiguousWidth);
            $maxOutputDisplayWidth = max($maxOutputDisplayWidth, $outputMax);
            $outputLineCount += $this->nonEmptyLineCount($wrappedLines);

            $opportunities = UnicodeText::lineBreakOpportunities($source, $ambiguousWidth);
            $hardBreakCount += $opportunities['hardBreakCount'];
            $softBreakOpportunityCount += $opportunities['softBreakCount'];
            $protectedSeparatorCount += $opportunities['protectedSeparatorCount'];
            $lineEndingNormalizationCount += $lineEndings['conversions'];

            $blockDiagnostics[] = [
                'blockIndex' => $index,
                'blockType' => $node->type,
                'sourceLineCount' => $sourceLineCount,
                'outputLineCount' => $wrappedLineCount,
                'maxSourceDisplayWidth' => $sourceMax,
                'maxOutputDisplayWidth' => $outputMax,
                'wrapped' => $wrapped,
                'protectedSeparatorCount' => $opportunities['protectedSeparatorCount'],
                'lineEndingNormalizationCount' => $lineEndings['conversions'],
            ];
        }

        return [
            'text' => implode("\n\n", $blocks),
            'diagnostics' => [
                'writer' => 'plain',
                'wrapMode' => $wrapMode,
                'columns' => $columns,
                'blockCount' => count($blocks),
                'wrappedBlockCount' => $wrappedBlockCount,
                'outputLineCount' => $outputLineCount,
                'maxOutputDisplayWidth' => $maxOutputDisplayWidth,
                'hardBreakCount' => $hardBreakCount,
                'softBreakOpportunityCount' => $softBreakOpportunityCount,
                'protectedSeparatorCount' => $protectedSeparatorCount,
                'lineEndingNormalizationCount' => $lineEndingNormalizationCount,
                'blocks' => $blockDiagnostics,
            ],
        ];
    }

    private function columns(): int
    {
        $columns = $this->options['columns'] ?? 72;

        return is_int($columns) ? max(0, $columns) : 72;
    }

    private function wrapMode(): string
    {
        $mode = strtolower((string) ($this->options['wrap'] ?? 'auto'));

        return in_array($mode, ['auto', 'none', 'preserve'], true) ? $mode : 'auto';
    }

    /**
     * @return list<string>
     */
    private function wrapLines(string $text, int $columns, string $wrapMode, string $ambiguousWidth): array
    {
        if ($wrapMode !== 'auto' || $columns <= 0) {
            return explode("\n", $text);
        }

        return UnicodeText::wrapByDisplayWidth($text, $columns, '', $ambiguousWidth);
    }

    private function renderBlock(AstNode $node): string
    {
        return match ($node->type) {
            'paragraph', 'plain', 'heading' => $this->renderInlines($node->children),
            'blockquote', 'div' => $this->renderBlockCollection($node->children),
            'bullet_list' => $this->renderList($node, false),
            'ordered_list' => $this->renderList($node, true),
            'line_block' => $this->renderLineBlock($node),
            'code_block' => (string) $node->attr('text', ''),
            'raw_markdown', 'raw_tex', 'raw_block' => (string) $node->attr('text', $node->attr('markdown', $node->attr('tex', ''))),
            'horizontal_rule' => '',
            default => $node->children === []
                ? (string) $node->attr('text', '')
                : $this->renderBlockCollection($node->children),
        };
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderBlockCollection(array $blocks): string
    {
        $rendered = [];
        foreach ($blocks as $block) {
            if (!$block instanceof AstNode) {
                continue;
            }

            $text = $this->isInlineNode($block)
                ? $this->renderInlines([$block])
                : $this->renderBlock($block);
            if ($text !== '') {
                $rendered[] = $text;
            }
        }

        return implode("\n", $rendered);
    }

    private function renderList(AstNode $node, bool $ordered): string
    {
        $lines = [];
        $number = (int) $node->attr('start', 1);
        foreach ($node->children as $item) {
            if (!$item instanceof AstNode || $item->type !== 'list_item') {
                continue;
            }

            $marker = $ordered ? $number . '. ' : '- ';
            $number++;
            $text = $this->renderBlockCollection($item->children);
            $itemLines = explode("\n", $text === '' ? (string) $item->attr('text', '') : $text);
            $first = array_shift($itemLines);
            $lines[] = $marker . (string) $first;
            foreach ($itemLines as $line) {
                $lines[] = str_repeat(' ', strlen($marker)) . $line;
            }
        }

        return implode("\n", $lines);
    }

    private function renderLineBlock(AstNode $node): string
    {
        $lines = [];
        foreach ($node->children as $line) {
            if (!$line instanceof AstNode || $line->type !== 'line') {
                continue;
            }

            $lines[] = $line->children === []
                ? (string) $line->attr('text', '')
                : $this->renderInlines($line->children);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }

            $text .= $this->renderInline($node);
        }

        return $text;
    }

    private function renderInline(AstNode $node): string
    {
        return match ($node->type) {
            'text' => (string) $node->attr('text', ''),
            'space', 'softbreak' => ' ',
            'linebreak' => "\n",
            'code' => (string) $node->attr('text', ''),
            'math' => (string) $node->attr('text', ''),
            'link' => $this->renderLink($node),
            'image' => $this->renderImage($node),
            'citation', 'citation_group' => (string) $node->attr('rendered', $node->attr('text', $this->renderInlines($node->children))),
            'raw_inline', 'raw_markdown', 'raw_tex', 'raw_html_inline' => (string) $node->attr('text', $node->attr('markdown', $node->attr('tex', $node->attr('html', '')))),
            'note' => $this->renderBlockCollection($node->children),
            default => $this->renderInlines($node->children),
        };
    }

    private function renderLink(AstNode $node): string
    {
        $label = $this->renderInlines($node->children);

        return $label === '' ? (string) $node->attr('url', '') : $label;
    }

    private function renderImage(AstNode $node): string
    {
        $alt = $this->renderInlines($node->children);

        return $alt === '' ? (string) $node->attr('alt', '') : $alt;
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'space',
            'softbreak',
            'linebreak',
            'code',
            'math',
            'link',
            'image',
            'citation',
            'citation_group',
            'raw_inline',
            'raw_markdown',
            'raw_tex',
            'raw_html_inline',
            'note',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'small_caps',
            'span',
            'quoted',
        ], true);
    }

    /**
     * @param list<string> $lines
     */
    private function maxDisplayWidth(array $lines, string $ambiguousWidth): int
    {
        $max = 0;
        foreach ($lines as $line) {
            $max = max($max, UnicodeText::displayWidth($line, $ambiguousWidth));
        }

        return $max;
    }

    /**
     * @param list<string> $lines
     */
    private function nonEmptyLineCount(array $lines): int
    {
        $count = 0;
        foreach ($lines as $line) {
            if ($line !== '') {
                ++$count;
            }
        }

        return $count;
    }
}
