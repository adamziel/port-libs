<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownWriter
{
    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Markdown writer expects a document node');
        }

        $blocks = [];
        foreach ($document->children as $node) {
            $lines = $this->renderBlock($node, 0);
            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @return list<string>
     */
    private function renderBlock(AstNode $node, int $indent): array
    {
        return match ($node->type) {
            'paragraph', 'plain' => [str_repeat(' ', $indent) . $this->renderInlines($node->children)],
            'heading' => [str_repeat(' ', $indent) . str_repeat('#', (int) $node->attr('level', 1)) . ' ' . $this->renderInlines($node->children)],
            'bullet_list' => $this->renderList($node, false, $indent),
            'ordered_list' => $this->renderList($node, true, $indent),
            'code_block' => $this->renderCodeBlock($node, $indent),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function renderList(AstNode $node, bool $ordered, int $indent): array
    {
        $lines = [];
        $start = (int) $node->attr('start', 1);
        $index = 0;

        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }

            $marker = $ordered ? $this->orderedListMarker($node, $start + $index) : '- ';
            array_push($lines, ...$this->renderListItem($item, $marker, $indent));
            $index++;
        }

        return $lines;
    }

    private function orderedListMarker(AstNode $node, int $number): string
    {
        $number = max(1, $number);
        $style = (string) $node->attr('style', 'decimal');
        $delimiter = (string) $node->attr('delimiter', 'period');
        $label = match ($style) {
            'lower_alpha' => chr(ord('a') + (($number - 1) % 26)),
            'upper_alpha' => chr(ord('A') + (($number - 1) % 26)),
            'lower_roman' => strtolower($this->romanNumeral($number)),
            'upper_roman' => $this->romanNumeral($number),
            default => (string) $number,
        };

        $marker = match ($delimiter) {
            'one_paren' => $label . ')',
            'two_parens' => '(' . $label . ')',
            default => $label . '.',
        };

        if (strlen($marker) < 3) {
            $marker .= str_repeat(' ', 3 - strlen($marker));
        }

        return $marker . ' ';
    }

    private function romanNumeral(int $number): string
    {
        $number = max(1, $number);
        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];
        $roman = '';
        foreach ($map as $value => $glyph) {
            while ($number >= $value) {
                $roman .= $glyph;
                $number -= $value;
            }
        }

        return $roman;
    }

    /**
     * @return list<string>
     */
    private function renderListItem(AstNode $item, string $marker, int $indent): array
    {
        $prefix = str_repeat(' ', $indent) . $marker;
        $task = $item->attr('taskChecked', null);
        if (is_bool($task)) {
            $prefix .= $task ? '[x] ' : '[ ] ';
        }

        $inlineChildren = [];
        $lines = [];
        $hasFirstLine = false;

        foreach ($item->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlineChildren[] = $child;
                continue;
            }

            if ($inlineChildren !== [] || !$hasFirstLine) {
                $lines[] = rtrim($prefix . $this->renderInlines($inlineChildren));
                $inlineChildren = [];
                $hasFirstLine = true;
            }

            if ($child->type === 'paragraph') {
                if ($lines !== [] && end($lines) !== '') {
                    $lines[] = '';
                }
                $lines[] = str_repeat(' ', $indent + 2) . $this->renderInlines($child->children);
                continue;
            }

            foreach ($this->renderBlock($child, $indent + 2) as $nestedLine) {
                $lines[] = $nestedLine;
            }
        }

        if ($inlineChildren !== [] || !$hasFirstLine) {
            $lines[] = rtrim($prefix . $this->renderInlines($inlineChildren));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderCodeBlock(AstNode $node, int $indent): array
    {
        $lines = [];
        $prefix = str_repeat(' ', $indent + 4);
        foreach (explode("\n", (string) $node->attr('text', '')) as $line) {
            $lines[] = $prefix . $line;
        }

        return $lines;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= $this->renderInline($node);
        }

        return $text;
    }

    private function renderInline(AstNode $node): string
    {
        return match ($node->type) {
            'text' => (string) $node->attr('text', ''),
            'softbreak' => "\n",
            'linebreak' => "\\\n",
            'code' => '`' . str_replace('`', '\\`', (string) $node->attr('text', '')) . '`',
            'emph' => '*' . $this->renderInlines($node->children) . '*',
            'strong' => '**' . $this->renderInlines($node->children) . '**',
            'link' => '[' . $this->renderInlines($node->children) . '](' . (string) $node->attr('url', '') . ')',
            default => $this->renderInlines($node->children),
        };
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
            'link',
        ], true);
    }
}
