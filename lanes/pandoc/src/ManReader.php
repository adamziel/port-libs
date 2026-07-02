<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ManReader
{
    /** @var list<string> */
    private array $lines = [];

    private int $index = 0;

    public function read(string $roff): AstNode
    {
        $this->lines = $this->logicalLines(str_replace(["\r\n", "\r"], "\n", $roff));
        $this->index = 0;

        return new AstNode('document', [
            'sourceFormat' => 'man',
            'man' => [
                'reader' => self::class,
                'readerScope' => 'pinned-pandoc-man-reader-unit-semantics',
                'sourceBytes' => strlen($roff),
                'upstreamEvidence' => [
                    'source' => 'Pandoc 4f5226df Text.Pandoc.Readers.Man and test/Tests/Readers/Man.hs',
                    'readerUnitGroups' => [
                        'macros',
                        'escapes',
                        'lists',
                        'code blocks',
                        'tables',
                    ],
                    'fixtureStatus' => 'Tests.Readers.Man is an inline Haskell builder assertion suite, not a fixture-pair suite.',
                ],
            ],
        ], $this->parseBlocks());
    }

    /**
     * @return list<string>
     */
    private function logicalLines(string $text): array
    {
        $physical = explode("\n", $text);
        $logical = [];
        $buffer = '';
        foreach ($physical as $line) {
            if ($buffer !== '') {
                $line = $buffer . $line;
                $buffer = '';
            }
            if ($line !== '' && str_ends_with($line, '\\')) {
                $buffer = substr($line, 0, -1);
                continue;
            }
            $logical[] = $line;
        }
        if ($buffer !== '') {
            $logical[] = $buffer;
        }

        return $logical;
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlocks(?string $terminator = null): array
    {
        $blocks = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($terminator !== null && $trimmed === $terminator) {
                ++$this->index;
                break;
            }
            if ($trimmed === '' || $this->isCommentLine($trimmed)) {
                ++$this->index;
                continue;
            }

            $macro = $this->macroLine($line);
            if ($macro !== null) {
                [$name, $rawArgs] = $macro;
                if ($name === 'RE') {
                    if ($terminator === '.RE') {
                        ++$this->index;
                    }
                    break;
                }
                if ($name === 'SH' || $name === 'SS') {
                    ++$this->index;
                    $blocks[] = new AstNode('heading', [
                        'level' => $name === 'SH' ? 1 : 2,
                    ], $this->parseInlines($this->argsText($this->parseArgs($rawArgs))));
                    continue;
                }
                if ($name === 'IP') {
                    $blocks[] = $this->parseListBlock();
                    continue;
                }
                if ($name === 'RS') {
                    ++$this->index;
                    array_push($blocks, ...$this->parseBlocks('.RE'));
                    continue;
                }
                if ($name === 'nf') {
                    $blocks[] = $this->parseCodeBlock();
                    continue;
                }
                if ($name === 'TS') {
                    $blocks[] = $this->parseTable();
                    continue;
                }
                if (in_array($name, ['B', 'I', 'BI', 'BR'], true)) {
                    ++$this->index;
                    $blocks[] = new AstNode('paragraph', [], $this->inlineMacroInlines($name, $rawArgs));
                    continue;
                }
            }

            $blocks[] = $this->parseParagraph();
        }

        return $blocks;
    }

    private function parseParagraph(): AstNode
    {
        $parts = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '') {
                break;
            }
            if ($parts !== [] && $this->isBlockStart($line)) {
                break;
            }
            if (!$this->isCommentLine($trimmed)) {
                $parts[] = $this->stripInlineComment($line);
            }
            ++$this->index;
        }

        return new AstNode('paragraph', [], $this->parseInlines(implode('', $parts)));
    }

    private function isBlockStart(string $line): bool
    {
        $macro = $this->macroLine($line);
        if ($macro === null) {
            return false;
        }

        return in_array($macro[0], ['SH', 'SS', 'IP', 'RS', 'RE', 'nf', 'TS', 'B', 'I', 'BI', 'BR'], true);
    }

    private function isCommentLine(string $trimmed): bool
    {
        return str_starts_with($trimmed, '."') || str_starts_with($trimmed, '.\\"');
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private function macroLine(string $line): ?array
    {
        if (preg_match('/^\\.([A-Za-z][A-Za-z0-9]*)(?:\\s+(.*))?$/', $line, $match) !== 1) {
            return null;
        }

        return [(string) $match[1], (string) ($match[2] ?? '')];
    }

    /**
     * @return list<string>
     */
    private function inlineMacroInlines(string $name, string $rawArgs): array
    {
        $args = $this->parseArgs($rawArgs);
        if ($args === [] && $this->index < count($this->lines) && !$this->isBlockStart($this->lines[$this->index])) {
            $args = [$this->stripInlineComment($this->lines[$this->index])];
            ++$this->index;
        }

        if ($name === 'B') {
            return [new AstNode('strong', [], $this->parseInlines($this->argsText($args)))];
        }
        if ($name === 'I') {
            return [new AstNode('emph', [], $this->parseInlines($this->argsText($args)))];
        }

        $inlines = [];
        foreach (array_values($args) as $offset => $arg) {
            $children = $this->parseInlines($arg);
            if ($name === 'BI') {
                $inlines[] = $offset % 2 === 0
                    ? new AstNode('strong', [], $children)
                    : new AstNode('emph', [], $children);
                continue;
            }
            if ($name === 'BR') {
                if ($offset % 2 === 0) {
                    $inlines[] = new AstNode('strong', [], $children);
                } else {
                    array_push($inlines, ...$children);
                }
            }
        }

        return $inlines;
    }

    /**
     * @return list<string>
     */
    private function parseArgs(string $raw): array
    {
        $args = [];
        $length = strlen($raw);
        $offset = 0;
        while ($offset < $length) {
            while ($offset < $length && ctype_space($raw[$offset])) {
                ++$offset;
            }
            if ($offset >= $length) {
                break;
            }
            if ($raw[$offset] === '"') {
                ++$offset;
                $arg = '';
                while ($offset < $length) {
                    if ($raw[$offset] === '"') {
                        if ($offset + 1 < $length && $raw[$offset + 1] === '"') {
                            $arg .= '"';
                            $offset += 2;
                            continue;
                        }
                        ++$offset;
                        break;
                    }
                    $arg .= $raw[$offset];
                    ++$offset;
                }
                $args[] = $arg;
                continue;
            }

            $start = $offset;
            while ($offset < $length && !ctype_space($raw[$offset])) {
                ++$offset;
            }
            $args[] = substr($raw, $start, $offset - $start);
        }

        return $args;
    }

    /**
     * @param list<string> $args
     */
    private function argsText(array $args): string
    {
        return implode(' ', $args);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $text): array
    {
        $segments = [];
        $style = 'normal';
        $buffer = '';
        $source = $this->stripInlineComment($text);
        $length = strlen($source);

        $flush = static function () use (&$segments, &$style, &$buffer): void {
            if ($buffer === '') {
                return;
            }
            $segments[] = ['style' => $style, 'text' => $buffer];
            $buffer = '';
        };

        for ($i = 0; $i < $length; ++$i) {
            $char = $source[$i];
            if ($char !== '\\') {
                $buffer .= $char;
                continue;
            }

            if ($i + 1 < $length && $source[$i + 1] === 'f') {
                $newStyle = $this->fontStyleEscape($source, $i);
                if ($newStyle !== null) {
                    $flush();
                    $style = $newStyle;
                    continue;
                }
            }

            $buffer .= $this->escapeText($source, $i);
        }
        $flush();

        return $this->segmentsToInlines($segments);
    }

    private function stripInlineComment(string $text): string
    {
        $quote = strpos($text, '\\"');
        $hash = strpos($text, '\\#');
        $positions = array_values(array_filter([$quote, $hash], static fn (int|false $position): bool => $position !== false));
        if ($positions === []) {
            return $text;
        }

        return rtrim(substr($text, 0, min($positions)));
    }

    private function fontStyleEscape(string $source, int &$offset): ?string
    {
        $length = strlen($source);
        $style = null;
        if ($offset + 3 < $length && $source[$offset + 2] === '[') {
            $end = strpos($source, ']', $offset + 3);
            if ($end === false) {
                return null;
            }
            $font = substr($source, $offset + 3, $end - $offset - 3);
            $offset = $end;
        } elseif ($offset + 2 < $length) {
            $font = $source[$offset + 2];
            $offset += 2;
        } else {
            return null;
        }

        $style = match ($font) {
            'I' => 'italic',
            'B' => 'bold',
            'BI', 'IB' => 'bolditalic',
            'R', 'P' => 'normal',
            default => null,
        };

        return $style;
    }

    private function escapeText(string $source, int &$offset): string
    {
        $length = strlen($source);
        if ($offset + 1 >= $length) {
            return '';
        }

        $next = $source[$offset + 1];
        if ($next === '[') {
            $end = strpos($source, ']', $offset + 2);
            if ($end === false) {
                ++$offset;
                return '[';
            }
            $name = substr($source, $offset + 2, $end - $offset - 2);
            $offset = $end;

            return $this->glyph($name);
        }
        if ($next === '(' && $offset + 3 < $length) {
            $name = substr($source, $offset + 2, 2);
            $offset += 3;

            return $this->glyph($name);
        }
        if ($next === '*' && $offset + 4 < $length && $source[$offset + 2] === '(') {
            $name = substr($source, $offset + 3, 2);
            $offset += 4;

            return match ($name) {
                'lq' => "\u{201C}",
                'rq' => "\u{201D}",
                default => $name,
            };
        }
        if ($next === '*' && $offset + 3 < $length) {
            $name = substr($source, $offset + 2, 2);
            $offset += 3;

            return match ($name) {
                'lq' => "\u{201C}",
                'rq' => "\u{201D}",
                default => $name,
            };
        }

        ++$offset;

        return match ($next) {
            '-' => '-',
            ' ' => ' ',
            '\\' => '\\',
            '%' => '',
            ':' => '',
            '0' => "\u{2007}",
            'e' => '\\',
            '`' => '`',
            '^' => "\u{200A}",
            '|' => "\u{2006}",
            '\'' => '\'',
            't' => '',
            default => $next,
        };
    }

    private function glyph(string $name): string
    {
        if (preg_match('/^u([0-9A-Fa-f]{4,6})(?:_u([0-9A-Fa-f]{4,6}))*$/', $name) === 1) {
            $chars = [];
            if (preg_match_all('/u([0-9A-Fa-f]{4,6})/', $name, $matches) !== false) {
                foreach ($matches[1] as $hex) {
                    $chars[] = mb_chr((int) hexdec((string) $hex), 'UTF-8');
                }
            }
            $text = implode('', $chars);

            return class_exists(\Normalizer::class) ? \Normalizer::normalize($text, \Normalizer::FORM_C) : $text;
        }

        return match ($name) {
            'bu' => "\u{2022}",
            'lq' => "\u{201C}",
            'rq' => "\u{201D}",
            'em' => "\u{2014}",
            'en' => "\u{2013}",
            'oA' => "\u{00C5}",
            '~O' => "\u{00D5}",
            'Do' => '$',
            'Ye' => "\u{00A5}",
            'product' => "\u{220F}",
            'ul' => '_',
            default => $name,
        };
    }

    /**
     * @param list<array{style:string, text:string}> $segments
     * @return list<AstNode>
     */
    private function segmentsToInlines(array $segments): array
    {
        $inlines = [];
        $index = 0;
        while ($index < count($segments)) {
            $segment = $segments[$index];
            $style = $segment['style'];
            if ($style === 'normal') {
                $inlines[] = $this->textNode($segment['text']);
                ++$index;
                continue;
            }
            if ($style === 'bold') {
                $children = [];
                while ($index < count($segments) && $segments[$index]['style'] === 'bold') {
                    $children[] = $this->textNode($segments[$index]['text']);
                    ++$index;
                }
                $inlines[] = new AstNode('strong', [], $children);
                continue;
            }

            $children = [];
            while ($index < count($segments) && in_array($segments[$index]['style'], ['italic', 'bolditalic'], true)) {
                if ($segments[$index]['style'] === 'bolditalic') {
                    $children[] = new AstNode('strong', [], [$this->textNode($segments[$index]['text'])]);
                } else {
                    $children[] = $this->textNode($segments[$index]['text']);
                }
                ++$index;
            }
            $inlines[] = new AstNode('emph', [], $children);
        }

        return $this->coalesceTextNodes($inlines);
    }

    private function textNode(string $text): AstNode
    {
        return new AstNode('text', ['text' => $text]);
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $inlines): array
    {
        $coalesced = [];
        foreach ($inlines as $inline) {
            $last = $coalesced[count($coalesced) - 1] ?? null;
            if ($inline->type === 'text' && $last instanceof AstNode && $last->type === 'text') {
                array_pop($coalesced);
                $coalesced[] = new AstNode('text', [
                    'text' => (string) $last->attr('text', '') . (string) $inline->attr('text', ''),
                ]);
                continue;
            }
            $coalesced[] = $inline;
        }

        return $coalesced;
    }

    private function parseListBlock(): AstNode
    {
        $first = $this->currentIpDescriptor();
        $items = [];
        while ($this->index < count($this->lines)) {
            $descriptor = $this->currentIpDescriptor();
            if ($descriptor === null || !$this->sameListKind($first, $descriptor)) {
                break;
            }
            ++$this->index;
            $items[] = $this->parseListItem();
        }

        if ($first['kind'] === 'bullet') {
            return new AstNode('bullet_list', ['loose' => false], $items);
        }

        return new AstNode('ordered_list', [
            'start' => $first['start'],
            'style' => $first['style'],
            'delimiter' => $first['delimiter'],
            'loose' => false,
        ], $items);
    }

    /**
     * @return array{kind:string, start:int, style:string, delimiter:string}|null
     */
    private function currentIpDescriptor(): ?array
    {
        $macro = $this->macroLine($this->lines[$this->index] ?? '');
        if ($macro === null || $macro[0] !== 'IP') {
            return null;
        }

        $args = $this->parseArgs($macro[1]);
        $marker = $args[0] ?? '';
        $markerText = $this->plainInlineText($this->parseInlines($marker));
        if ($markerText === "\u{2022}") {
            return ['kind' => 'bullet', 'start' => 1, 'style' => 'default', 'delimiter' => 'default'];
        }
        if (preg_match('/^([0-9]+)$/', $markerText, $match) === 1) {
            return ['kind' => 'ordered', 'start' => (int) $match[1], 'style' => 'decimal', 'delimiter' => 'default'];
        }
        if (preg_match('/^([A-Z])\\)$/', $markerText, $match) === 1) {
            return [
                'kind' => 'ordered',
                'start' => ord((string) $match[1]) - ord('A') + 1,
                'style' => 'upper_alpha',
                'delimiter' => 'one_paren',
            ];
        }

        return ['kind' => 'bullet', 'start' => 1, 'style' => 'default', 'delimiter' => 'default'];
    }

    /**
     * @param array{kind:string, start:int, style:string, delimiter:string} $left
     * @param array{kind:string, start:int, style:string, delimiter:string} $right
     */
    private function sameListKind(array $left, array $right): bool
    {
        return $left['kind'] === $right['kind']
            && $left['style'] === $right['style']
            && $left['delimiter'] === $right['delimiter'];
    }

    private function parseListItem(): AstNode
    {
        $children = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '') {
                ++$this->index;
                break;
            }
            $macro = $this->macroLine($line);
            if ($macro !== null && $macro[0] === 'IP') {
                break;
            }
            if ($macro !== null && $macro[0] === 'RE') {
                break;
            }
            if ($macro !== null && $macro[0] === 'RS') {
                ++$this->index;
                array_push($children, ...$this->parseBlocks('.RE'));
                continue;
            }
            if ($macro !== null && $macro[0] === 'nf') {
                $children[] = $this->parseCodeBlock();
                continue;
            }

            array_push($children, ...$this->parseInlines($line));
            ++$this->index;
        }

        return new AstNode('list_item', [
            'text' => $this->plainInlineText(array_values(array_filter($children, fn (AstNode $node): bool => $this->isInlineNode($node)))),
            'loose' => false,
        ], $children);
    }

    private function parseCodeBlock(): AstNode
    {
        ++$this->index;
        $lines = [];
        while ($this->index < count($this->lines)) {
            $macro = $this->macroLine($this->lines[$this->index]);
            if ($macro !== null && $macro[0] === 'fi') {
                ++$this->index;
                break;
            }
            $lines[] = $this->lines[$this->index];
            ++$this->index;
        }

        return new AstNode('code_block', ['text' => implode("\n", $lines)]);
    }

    private function parseTable(): AstNode
    {
        ++$this->index;
        $alignments = [];
        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            ++$this->index;
            if ($line === '' || str_ends_with($line, ';')) {
                continue;
            }
            if (str_ends_with($line, '.')) {
                $format = rtrim(substr($line, 0, -1));
                $alignments = $this->tableAlignments($format);
                break;
            }
        }

        $rows = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $macro = $this->macroLine($line);
            if ($macro !== null && $macro[0] === 'TE') {
                ++$this->index;
                break;
            }

            if (trim($line) === 'T{') {
                ++$this->index;
                $cellLines = [];
                while ($this->index < count($this->lines) && trim($this->lines[$this->index]) !== 'T}') {
                    $cellLines[] = trim($this->lines[$this->index]);
                    ++$this->index;
                }
                if ($this->index < count($this->lines) && trim($this->lines[$this->index]) === 'T}') {
                    ++$this->index;
                }
                $rows[] = [$this->collapseSpaces(implode(' ', $cellLines))];
                continue;
            }

            $rows[] = array_map(fn (string $cell): string => $this->collapseSpaces($cell), explode("\t", $line));
            ++$this->index;
        }

        $rowNodes = array_map(fn (array $row): AstNode => $this->tableRow($row), $rows);

        return new AstNode('table', [
            'alignments' => $alignments,
            'widths' => array_fill(0, count($alignments), null),
            'nativeColumnCount' => count($alignments),
        ], [
            new AstNode('table_head'),
            new AstNode('table_body', [], $rowNodes),
            new AstNode('table_foot'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function tableAlignments(string $format): array
    {
        $alignments = [];
        foreach (preg_split('/\\s+/', trim($format)) ?: [] as $token) {
            $alignments[] = match (strtolower($token[0] ?? '')) {
                'l' => 'left',
                'r' => 'right',
                'c' => 'center',
                default => 'default',
            };
        }

        return $alignments;
    }

    /**
     * @param list<string> $cells
     */
    private function tableRow(array $cells): AstNode
    {
        return new AstNode('table_row', ['header' => false], array_map(
            fn (string $cell): AstNode => new AstNode('table_cell', ['text' => $cell], [
                new AstNode('plain', [], $this->parseInlines($cell)),
            ]),
            $cells
        ));
    }

    private function collapseSpaces(string $text): string
    {
        return trim(preg_replace('/\\s+/u', ' ', $text) ?? $text);
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
                'space', 'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return $text;
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'space',
            'softbreak',
            'linebreak',
            'emph',
            'strong',
            'strikeout',
            'superscript',
            'subscript',
            'underline',
            'small_caps',
            'code',
            'link',
            'image',
            'note',
            'quoted',
            'math',
            'citation',
            'raw_html_inline',
            'raw_tex',
            'raw_tex_inline',
            'raw_markdown',
            'raw_inline',
            'span',
        ], true);
    }
}
