<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownWriter
{
    /** @var list<array{number:int, node:AstNode}> */
    private array $notes = [];

    /** @var list<array{label:string, url:string, title:string}> */
    private array $references = [];

    /** @var array<string, int> */
    private array $referenceLabelUses = [];

    private int $nextNoteNumber = 1;

    /**
     * @param array{setextHeadings?: bool, referenceLinks?: bool, referenceLocation?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Markdown writer expects a document node');
        }

        $this->notes = [];
        $this->references = [];
        $this->referenceLabelUses = [];
        $this->nextNoteNumber = 1;

        $blocks = [];
        foreach ($document->children as $index => $node) {
            if ($this->referenceLocation() === 'end_of_section' && $node->type === 'heading' && $index > 0) {
                $this->appendPendingDefinitions($blocks);
            }

            $lines = $this->renderBlock($node, 0);
            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }

            if ($this->referenceLocation() === 'end_of_block') {
                $this->appendPendingDefinitions($blocks);
            }
        }
        $this->appendPendingDefinitions($blocks);

        return implode("\n\n", $blocks);
    }

    /**
     * @return list<string>
     */
    private function renderBlock(AstNode $node, int $indent): array
    {
        return match ($node->type) {
            'paragraph', 'plain' => [str_repeat(' ', $indent) . $this->renderInlines($node->children)],
            'heading' => $this->renderHeading($node, $indent),
            'bullet_list' => $this->renderList($node, false, $indent),
            'ordered_list' => $this->renderList($node, true, $indent),
            'blockquote' => $this->renderBlockQuote($node, $indent),
            'code_block' => $this->renderCodeBlock($node, $indent),
            'horizontal_rule' => [str_repeat(' ', $indent) . '* * *'],
            'raw_html' => array_map(
                static fn (string $line): string => str_repeat(' ', $indent) . $line,
                explode("\n", (string) $node->attr('html', ''))
            ),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function renderHeading(AstNode $node, int $indent): array
    {
        $level = max(1, min(6, (int) $node->attr('level', 1)));
        $text = $this->renderInlines($node->children);
        $prefix = str_repeat(' ', $indent);

        if ($indent === 0 && (bool) ($this->options['setextHeadings'] ?? false) && ($level === 1 || $level === 2)) {
            return [
                $text,
                str_repeat($level === 1 ? '=' : '-', max(1, strlen($text))),
            ];
        }

        return [$prefix . str_repeat('#', $level) . ' ' . $text];
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
    private function renderBlockQuote(AstNode $node, int $indent): array
    {
        $body = $this->renderBlockCollection($node->children);
        $prefix = str_repeat(' ', $indent) . '>';
        if ($body === '') {
            return [$prefix];
        }

        $lines = [];
        foreach (explode("\n", $body) as $line) {
            $lines[] = $line === '' ? $prefix : $prefix . ' ' . $line;
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
        foreach ($nodes as $index => $node) {
            $text .= $this->renderInline($node, $nodes[$index + 1] ?? null);
        }

        return $text;
    }

    private function renderInline(AstNode $node, ?AstNode $next = null): string
    {
        return match ($node->type) {
            'text' => (string) $node->attr('text', ''),
            'softbreak' => "\n",
            'linebreak' => "\\\n",
            'code' => '`' . str_replace('`', '\\`', (string) $node->attr('text', '')) . '`',
            'emph' => '*' . $this->renderInlines($node->children) . '*',
            'strong' => '**' . $this->renderInlines($node->children) . '**',
            'link' => $this->renderLink($node, $next),
            'note' => $this->renderNoteReference($node),
            default => $this->renderInlines($node->children),
        };
    }

    private function renderLink(AstNode $node, ?AstNode $next): string
    {
        if ((bool) ($this->options['referenceLinks'] ?? false)) {
            return $this->renderReferenceLink($node, $next);
        }

        return '[' . $this->renderInlines($node->children) . '](' . (string) $node->attr('url', '') . ')';
    }

    private function renderReferenceLink(AstNode $node, ?AstNode $next): string
    {
        $labelText = $this->renderInlines($node->children);
        $plainLabel = $this->normalizeReferenceLabelText($this->plainInlineText($node->children));
        $referenceLabel = $this->registerReference(
            $plainLabel,
            (string) $node->attr('url', ''),
            (string) $node->attr('title', '')
        );

        $shortcutable = $referenceLabel === $plainLabel && $this->canUseShortcutReference($next);
        if ($shortcutable) {
            return '[' . $labelText . ']';
        }

        $suffix = $referenceLabel === $plainLabel ? '[]' : '[' . $referenceLabel . ']';

        return '[' . $labelText . ']' . $suffix;
    }

    private function renderNoteReference(AstNode $node): string
    {
        $number = $this->nextNoteNumber++;
        $this->notes[] = [
            'number' => $number,
            'node' => $node,
        ];

        return '[^' . $number . ']';
    }

    private function registerReference(string $suggestedLabel, string $url, string $title): string
    {
        $label = $this->normalizeReferenceLabelText($suggestedLabel);
        if ($label === '') {
            $label = 'link';
        }

        $key = strtolower($label);
        $use = $this->referenceLabelUses[$key] ?? 0;
        $this->referenceLabelUses[$key] = $use + 1;

        $actualLabel = $use === 0 ? $label : (string) $use;
        $this->references[] = [
            'label' => $actualLabel,
            'url' => $url,
            'title' => $title,
        ];

        return $actualLabel;
    }

    private function canUseShortcutReference(?AstNode $next): bool
    {
        if ($next === null) {
            return true;
        }

        if ($next->type === 'link' || $next->type === 'citation') {
            return false;
        }

        if ($next->type !== 'text') {
            return true;
        }

        return !str_starts_with((string) $next->attr('text', ''), '[');
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function normalizeReferenceLabelText(string $label): string
    {
        return trim(preg_replace('/\s+/', ' ', $label) ?? $label);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderBlockCollection(array $nodes): string
    {
        $blocks = [];
        foreach ($nodes as $node) {
            $lines = $this->renderBlock($node, 0);
            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param list<string> $blocks
     */
    private function appendPendingDefinitions(array &$blocks): void
    {
        foreach ($this->pendingDefinitionBlocks() as $definitionBlock) {
            if ($definitionBlock !== '') {
                $blocks[] = $definitionBlock;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function pendingDefinitionBlocks(): array
    {
        $blocks = [];
        while ($this->notes !== [] || $this->references !== []) {
            $notes = $this->notes;
            $references = $this->references;
            $this->notes = [];
            $this->references = [];

            foreach ($notes as $note) {
                $blocks[] = $this->renderNoteDefinition($note['number'], $note['node']);
            }

            foreach ($references as $reference) {
                $blocks[] = $this->renderReferenceDefinition($reference);
            }
        }

        return $blocks;
    }

    private function renderNoteDefinition(int $number, AstNode $node): string
    {
        $body = $this->renderBlockCollection($node->children);
        if ($body === '') {
            return '[^' . $number . ']:';
        }

        $lines = explode("\n", $body);
        $first = array_shift($lines);
        $rendered = '[^' . $number . ']: ' . $first;
        foreach ($lines as $line) {
            $rendered .= "\n" . ($line === '' ? '' : '    ' . $line);
        }

        return $rendered;
    }

    /**
     * @param array{label:string, url:string, title:string} $reference
     */
    private function renderReferenceDefinition(array $reference): string
    {
        $title = $reference['title'] === ''
            ? ''
            : ' "' . str_replace(['\\', '"'], ['\\\\', '\\"'], $reference['title']) . '"';

        return '  [' . $reference['label'] . ']: ' . $reference['url'] . $title;
    }

    private function referenceLocation(): string
    {
        $location = (string) ($this->options['referenceLocation'] ?? 'end_of_document');

        return in_array($location, ['end_of_document', 'end_of_block', 'end_of_section'], true)
            ? $location
            : 'end_of_document';
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
            'note',
        ], true);
    }
}
