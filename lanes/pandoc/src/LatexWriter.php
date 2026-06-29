<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class LatexWriter
{
    private int $orderedListLevel = 0;

    /**
     * @param array{topLevelDivision?: string, writerTopLevelDivision?: string, highlightMethod?: string|bool, writerHighlightMethod?: string|bool} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('LaTeX writer expects a document node');
        }

        $previousOrderedListLevel = $this->orderedListLevel;
        $this->orderedListLevel = 0;

        try {
            $blocks = [];
            foreach ($document->children as $node) {
                $lines = $this->renderBlock($node);
                if ($lines !== []) {
                    $blocks[] = implode("\n", $lines);
                }
            }
        } finally {
            $this->orderedListLevel = $previousOrderedListLevel;
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @return list<string>
     */
    private function renderBlock(AstNode $node): array
    {
        return match ($node->type) {
            'heading' => [$this->renderHeading($node)],
            'paragraph', 'plain' => [$this->renderInlines($node->children, true)],
            'code_block' => $this->renderCodeBlock($node),
            'bullet_list' => $this->renderList($node, false),
            'ordered_list' => $this->renderList($node, true),
            'definition_list' => $this->renderDefinitionList($node),
            'blockquote' => $this->renderBlockQuote($node),
            'horizontal_rule' => ['\begin{center}\rule{0.5\linewidth}{0.5pt}\end{center}'],
            'figure' => $this->renderFigure($node),
            'raw_tex' => $this->renderRawTexBlock($node),
            'raw_block' => $this->renderRawBlock($node),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function renderBlockQuote(AstNode $node): array
    {
        $lines = ['\begin{quote}'];
        foreach ($node->children as $child) {
            $childLines = $this->renderBlock($child);
            if ($childLines === []) {
                continue;
            }
            if (count($lines) > 1) {
                $lines[] = '';
            }
            array_push($lines, ...$childLines);
        }
        $lines[] = '\end{quote}';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderRawTexBlock(AstNode $node): array
    {
        return $this->rawBlockLines((string) $node->attr('tex', $node->attr('text', '')));
    }

    /**
     * @return list<string>
     */
    private function renderRawBlock(AstNode $node): array
    {
        $format = strtolower((string) $node->attr('format', ''));
        if ($format !== 'tex' && $format !== 'latex') {
            return [];
        }

        return $this->rawBlockLines((string) $node->attr('text', ''));
    }

    /**
     * @return list<string>
     */
    private function rawBlockLines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return explode("\n", rtrim($text, "\n"));
    }

    /**
     * @return list<string>
     */
    private function renderCodeBlock(AstNode $node): array
    {
        $text = rtrim((string) $node->attr('text', ''), "\n");

        if ($this->usesListingCodeBlocks()) {
            $id = (string) $node->attr('id', '');
            $label = $id === '' ? '' : '[label=' . $this->escapeListingLabel($id) . ']';

            return [
                '\begin{lstlisting}' . $label,
                $text,
                '\end{lstlisting}',
            ];
        }

        return [
            '\begin{Verbatim}',
            $text,
            '\end{Verbatim}',
        ];
    }

    private function usesListingCodeBlocks(): bool
    {
        $value = $this->options['writerHighlightMethod'] ?? $this->options['highlightMethod'] ?? null;
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(str_replace(['-', '_', ' '], '', (string) $value));

        return in_array($normalized, ['idiomatic', 'idiomatichighlighting', 'listings', 'lstlisting'], true);
    }

    private function escapeListingLabel(string $label): string
    {
        return strtr($label, [
            '\\' => '\textbackslash{}',
            '[' => '{[}',
            ']' => '{]}',
            ',' => '{,}',
        ]);
    }

    /**
     * @return list<string>
     */
    private function renderFigure(AstNode $node): array
    {
        $lines = ['\begin{figure}' . $this->renderFigurePlacement($node)];
        $lines[] = '\centering';

        $image = $this->firstFigureImage($node);
        if ($image instanceof AstNode) {
            $lines[] = $this->renderImage($image);
        }

        $caption = $this->renderCaption($node);
        if ($caption !== '') {
            $lines[] = '\caption{' . $caption . '}';
        }

        $lines[] = '\end{figure}';

        return $lines;
    }

    private function renderFigurePlacement(AstNode $node): string
    {
        $attributes = $node->attr('attributes', []);
        if (!is_array($attributes) || !isset($attributes['latex-placement'])) {
            return '';
        }

        $placement = trim((string) $attributes['latex-placement']);

        return $placement === '' ? '' : '[' . $placement . ']';
    }

    private function firstFigureImage(AstNode $node): ?AstNode
    {
        foreach ($node->children as $child) {
            if ($child->type === 'image') {
                return $child;
            }

            $nested = $this->firstFigureImage($child);
            if ($nested instanceof AstNode) {
                return $nested;
            }
        }

        return null;
    }

    private function renderCaption(AstNode $node): string
    {
        $captionInlines = $node->attr('captionInlines', null);
        if (is_array($captionInlines) && $this->isAstNodeList($captionInlines)) {
            return $this->renderInlines($captionInlines, true);
        }

        return $this->escapeText((string) $node->attr('caption', ''));
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionList(AstNode $node): array
    {
        $lines = ['\begin{description}'];
        if ($this->definitionListIsTight($node)) {
            $lines[] = '\tightlist';
        }

        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            [$term, $definitions] = $this->definitionItemParts($item);
            $termLatex = $this->renderDefinitionTerm($term, $item);
            foreach ($definitions as $definition) {
                $body = $this->renderDefinitionBody($definition);
                $startsWithHeading = $this->definitionStartsWithHeading($definition);
                $lines[] = '\item[' . $termLatex . ']' . ($startsWithHeading ? ' ~ ' : '');
                array_push($lines, ...$body);
            }
        }

        $lines[] = '\end{description}';

        return $lines;
    }

    /**
     * @return array{0:?AstNode, 1:list<AstNode>}
     */
    private function definitionItemParts(AstNode $item): array
    {
        $term = null;
        $definitions = [];
        foreach ($item->children as $child) {
            if ($child->type === 'term') {
                $term = $child;
                continue;
            }

            if ($child->type === 'definition') {
                $definitions[] = $child;
            }
        }

        return [$term, $definitions];
    }

    private function renderDefinitionTerm(?AstNode $term, AstNode $item): string
    {
        if (!$term instanceof AstNode) {
            return $this->escapeText((string) $item->attr('term', ''));
        }

        $children = $term->children;
        $rendered = $children === []
            ? $this->escapeText((string) $term->attr('text', $item->attr('term', '')))
            : $this->renderInlines($children, true);

        return $this->definitionTermNeedsBraces($children) ? '{' . $rendered . '}' : $rendered;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function definitionTermNeedsBraces(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node->type !== 'text') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionBody(AstNode $definition): array
    {
        $lines = [];
        foreach ($definition->children as $index => $child) {
            if ($index > 0) {
                $lines[] = '';
            }
            array_push($lines, ...$this->renderBlock($child));
        }

        return $lines;
    }

    private function definitionStartsWithHeading(AstNode $definition): bool
    {
        $first = $definition->children[0] ?? null;

        return $first instanceof AstNode && $first->type === 'heading';
    }

    private function definitionListIsTight(AstNode $node): bool
    {
        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            [, $definitions] = $this->definitionItemParts($item);
            foreach ($definitions as $definition) {
                if (count($definition->children) !== 1 || ($definition->children[0]->type ?? null) !== 'plain') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function renderList(AstNode $node, bool $ordered): array
    {
        if ($ordered) {
            return $this->renderOrderedList($node);
        }

        $lines = [$ordered ? '\begin{enumerate}' : '\begin{itemize}'];
        if ($this->listIsTight($node)) {
            $lines[] = '\tightlist';
        }
        foreach ($node->children as $item) {
            if ($item->type === 'list_item') {
                array_push($lines, ...$this->renderListItem($item));
            }
        }
        $lines[] = '\end{itemize}';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderOrderedList(AstNode $node): array
    {
        $level = $this->orderedListLevel;
        $this->orderedListLevel++;

        try {
            $lines = ['\begin{enumerate}'];
            $labelDefinition = $this->orderedListLabelDefinition($node, $level);
            if ($labelDefinition !== '') {
                $lines[] = $labelDefinition;
            }

            $resetCounter = $this->orderedListResetCounter($node, $level);
            if ($resetCounter !== '') {
                $lines[] = $resetCounter;
            }

            if ($this->listIsTight($node)) {
                $lines[] = '\tightlist';
            }

            foreach ($node->children as $item) {
                if ($item->type === 'list_item') {
                    array_push($lines, ...$this->renderListItem($item));
                }
            }
            $lines[] = '\end{enumerate}';
        } finally {
            $this->orderedListLevel--;
        }

        return $lines;
    }

    private function orderedListLabelDefinition(AstNode $node, int $level): string
    {
        $style = (string) $node->attr('style', 'default');
        $delimiter = (string) $node->attr('delimiter', 'default');
        if ($style === 'default' && $delimiter === 'default') {
            return '';
        }

        $counter = $this->orderedListCounterName($level);
        if ($counter === null) {
            return '';
        }

        $command = match ($style) {
            'upper_roman' => 'Roman',
            'lower_roman' => 'roman',
            'upper_alpha' => 'Alph',
            'lower_alpha' => 'alph',
            default => 'arabic',
        };
        $label = '\\' . $command . '{' . $counter . '}';
        $label = match ($delimiter) {
            'one_paren' => $label . ')',
            'two_parens' => '(' . $label . ')',
            default => $label . '.',
        };

        return '\def\label' . $counter . '{' . $label . '}';
    }

    private function orderedListResetCounter(AstNode $node, int $level): string
    {
        $start = (int) $node->attr('start', 1);
        if ($start <= 1) {
            return '';
        }

        $counter = $this->orderedListCounterName($level);
        if ($counter === null) {
            return '';
        }

        return '\setcounter{' . $counter . '}{' . ($start - 1) . '}';
    }

    private function orderedListCounterName(int $level): ?string
    {
        return [
            0 => 'enumi',
            1 => 'enumii',
            2 => 'enumiii',
            3 => 'enumiv',
        ][$level] ?? null;
    }

    private function listIsTight(AstNode $node): bool
    {
        foreach ($node->children as $item) {
            if (!$this->listItemIsTight($item)) {
                return false;
            }
        }

        return true;
    }

    private function listItemIsTight(AstNode $item): bool
    {
        if ($item->type !== 'list_item') {
            return false;
        }

        if ($item->children === []) {
            return true;
        }

        $first = $item->children[0];
        if ($first->type === 'plain') {
            return true;
        }

        if (
            count($item->children) === 1
            && ($first->type === 'bullet_list' || $first->type === 'ordered_list')
        ) {
            return $this->listIsTight($first);
        }

        foreach ($item->children as $child) {
            if (!$this->isInlineNode($child)) {
                return false;
            }
        }

        return $item->attr('loose', null) === false;
    }

    /**
     * @return list<string>
     */
    private function renderListItem(AstNode $item): array
    {
        $task = $item->attr('taskChecked', null);
        if (is_bool($task)) {
            $itemMarker = $task ? '\item[$\boxtimes$]' : '\item[$\square$]';
        } else {
            $itemMarker = '\item';
        }
        $lines = [$itemMarker];

        $paragraphs = $this->listItemParagraphs($item);
        foreach ($paragraphs as $index => $paragraph) {
            if ($index > 0) {
                $lines[] = '';
            }
            $lines[] = '  ' . $paragraph;
        }

        foreach ($item->children as $child) {
            if ($child->type === 'bullet_list' || $child->type === 'ordered_list') {
                foreach ($this->renderBlock($child) as $line) {
                    $lines[] = $line === '' ? '' : '  ' . $line;
                }
                continue;
            }

            if ($child->type === 'heading') {
                if ($paragraphs === [] && $lines === [$itemMarker]) {
                    $lines[0] .= ' ~';
                }
                foreach ($this->renderBlock($child) as $line) {
                    $lines[] = '  ' . $line;
                }
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
                $paragraphs[] = $this->renderInlines($child->children, true);
            }
        }

        if ($inlineChildren !== []) {
            array_unshift($paragraphs, $this->renderInlines($inlineChildren, true));
        }

        return $paragraphs;
    }

    private function renderHeading(AstNode $node): string
    {
        $level = max(1, min(6, (int) $node->attr('level', 1)));
        $command = $this->headingCommand($level);

        $needsPdfString = $this->headingNeedsPdfStringFallback($node);
        $heading = $this->renderInlines($node->children, true, $this->headingContainsImage($node));
        if ($needsPdfString) {
            $heading = '\texorpdfstring{' . $heading . '}{' . $this->escapeText($this->plainHeadingText($node->children)) . '}';
        }

        $unnumbered = $this->hasClass($node, 'unnumbered');
        $output = '\\' . $command . ($unnumbered ? '*' : '') . '{' . $heading . '}';

        $id = (string) $node->attr('id', '');
        if ($unnumbered && $id !== '') {
            $output .= '\label{' . $this->escapeLinkTarget($id) . '}';
        }

        if ($unnumbered) {
            $output .= "\n" . '\addcontentsline{toc}{' . $command . '}{' . $this->escapeText($this->plainHeadingText($node->children)) . '}' . "\n";
        }

        return $output;
    }

    private function headingCommand(int $level): string
    {
        $commands = match ($this->topLevelDivision()) {
            'part' => [
                1 => 'part',
                2 => 'chapter',
                3 => 'section',
                4 => 'subsection',
                5 => 'subsubsection',
                6 => 'paragraph',
            ],
            'chapter' => [
                1 => 'chapter',
                2 => 'section',
                3 => 'subsection',
                4 => 'subsubsection',
                5 => 'paragraph',
                6 => 'subparagraph',
            ],
            default => [
                1 => 'section',
                2 => 'subsection',
                3 => 'subsubsection',
                4 => 'paragraph',
                5 => 'subparagraph',
                6 => 'subparagraph',
            ],
        };

        return $commands[max(1, min(6, $level))];
    }

    private function topLevelDivision(): string
    {
        $value = (string) ($this->options['writerTopLevelDivision'] ?? $this->options['topLevelDivision'] ?? 'default');
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $value));

        return match ($normalized) {
            'toplevelpart', 'part' => 'part',
            'toplevelchapter', 'chapter' => 'chapter',
            default => 'section',
        };
    }

    private function headingContainsImage(AstNode $node): bool
    {
        foreach ($node->children as $child) {
            if ($child->type === 'image' || $this->headingContainsImage($child)) {
                return true;
            }
        }

        return false;
    }

    private function headingNeedsPdfStringFallback(AstNode $node): bool
    {
        foreach ($node->children as $child) {
            if ($child->type === 'image' || $child->type === 'note' || $this->headingNeedsPdfStringFallback($child)) {
                return true;
            }
        }

        return false;
    }

    private function hasClass(AstNode $node, string $class): bool
    {
        $classes = $node->attr('classes', []);

        return is_array($classes) && in_array($class, array_map('strval', $classes), true);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlines(array $nodes, bool $escapeText = false, bool $protectImages = false): string
    {
        return $this->renderInlineTokenStream(
            $this->inlineTokens($nodes, $escapeText, $protectImages, [])
        );
    }

    /**
     * @param list<AstNode> $nodes
     * @param list<string> $styles
     * @return list<array{kind:string, text?:string, styles?:list<string>}>
     */
    private function inlineTokens(array $nodes, bool $escapeText, bool $protectImages, array $styles): array
    {
        $tokens = [];
        foreach ($nodes as $node) {
            if (in_array($node->type, ['emph', 'strong', 'underline', 'strikeout'], true)) {
                array_push($tokens, ...$this->inlineTokens(
                    $node->children,
                    $escapeText,
                    $protectImages,
                    array_merge($styles, [$node->type])
                ));
                continue;
            }

            if ($node->type === 'note' && $styles !== [] && $this->noteNeedsStyleSplit($node)) {
                $tokens[] = [
                    'kind' => 'boundary_note',
                    'text' => $this->renderNote($node, true),
                ];
                continue;
            }

            if (
                $node->children !== []
                && !in_array($node->type, ['text', 'softbreak', 'linebreak', 'code', 'math', 'link', 'image', 'note', 'raw_tex', 'raw_tex_inline', 'raw_inline'], true)
            ) {
                array_push($tokens, ...$this->inlineTokens($node->children, $escapeText, $protectImages, $styles));
                continue;
            }

            $text = match ($node->type) {
                'text' => $escapeText
                    ? $this->escapeText((string) $node->attr('text', ''))
                    : (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => "\n",
                'code' => $this->renderCode($node, in_array('strikeout', $styles, true)),
                'math' => $this->renderMath($node),
                'link' => $this->renderLink($node),
                'image' => $this->renderImage($node, $protectImages),
                'note' => $this->renderNote($node),
                'raw_tex', 'raw_tex_inline' => (string) $node->attr('tex', $node->attr('text', '')),
                'raw_inline' => $this->renderRawInline($node),
                default => '',
            };

            if ($text === '') {
                continue;
            }

            $tokens[] = [
                'kind' => 'text',
                'text' => $text,
                'styles' => $styles,
            ];
        }

        return $tokens;
    }

    /**
     * @param list<array{kind:string, text?:string, styles?:list<string>}> $tokens
     */
    private function renderInlineTokenStream(array $tokens): string
    {
        $output = '';
        $segment = [];
        foreach ($tokens as $token) {
            if ($token['kind'] === 'boundary_note') {
                $output .= $this->renderStyledTextSegment($segment);
                $segment = [];
                $output .= (string) ($token['text'] ?? '');
                continue;
            }

            $segment[] = $token;
        }

        return $output . $this->renderStyledTextSegment($segment);
    }

    /**
     * @param list<array{kind:string, text?:string, styles?:list<string>}> $tokens
     */
    private function renderStyledTextSegment(array $tokens): string
    {
        $output = '';
        $openStyles = [];
        foreach ($tokens as $token) {
            $styles = $token['styles'] ?? [];
            $common = $this->commonStylePrefixLength($openStyles, $styles);
            for ($index = count($openStyles) - 1; $index >= $common; $index--) {
                $output .= '}';
            }
            for ($index = $common, $count = count($styles); $index < $count; $index++) {
                $output .= $this->latexStyleOpen($styles[$index]);
            }
            $output .= (string) ($token['text'] ?? '');
            $openStyles = $styles;
        }

        for ($index = count($openStyles) - 1; $index >= 0; $index--) {
            $output .= '}';
        }

        return $output;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private function commonStylePrefixLength(array $left, array $right): int
    {
        $limit = min(count($left), count($right));
        for ($index = 0; $index < $limit; $index++) {
            if ($left[$index] !== $right[$index]) {
                return $index;
            }
        }

        return $limit;
    }

    private function latexStyleOpen(string $style): string
    {
        return match ($style) {
            'strong' => '\textbf{',
            'underline' => '\ul{',
            'strikeout' => '\st{',
            default => '\emph{',
        };
    }

    private function noteNeedsStyleSplit(AstNode $node): bool
    {
        return count($node->children) > 1;
    }

    private function renderNote(AstNode $node, bool $indentContinuationBlocks = false): string
    {
        $blocks = [];
        foreach ($node->children as $child) {
            $lines = $this->renderBlock($child);
            if ($lines !== []) {
                $blocks[] = implode("\n", $lines);
            }
        }
        if ($indentContinuationBlocks) {
            foreach ($blocks as $index => $block) {
                if ($index === 0) {
                    continue;
                }
                $blocks[$index] = $this->indentLatexBlock($block, '  ');
            }
        }

        return '\footnote{' . implode("\n\n", $blocks) . '}';
    }

    private function indentLatexBlock(string $block, string $indent): string
    {
        return implode("\n", array_map(
            static fn (string $line): string => $line === '' ? $line : $indent . $line,
            explode("\n", $block)
        ));
    }

    private function renderLink(AstNode $node): string
    {
        $url = (string) $node->attr('url', $node->attr('href', ''));
        $label = $this->renderInlines($node->children, true);

        if (str_starts_with($url, '#') && strlen($url) > 1) {
            return '\hyperref[' . $this->escapeLinkTarget(substr($url, 1)) . ']{' . $label . '}';
        }

        return '\href{' . $this->escapeUrlArgument($url) . '}{' . $label . '}';
    }

    private function escapeLinkTarget(string $target): string
    {
        return strtr($target, [
            '\\' => '\textbackslash{}',
            '{' => '\{',
            '}' => '\}',
        ]);
    }

    private function escapeUrlArgument(string $url): string
    {
        return strtr($url, [
            '\\' => '\textbackslash{}',
            '{' => '\{',
            '}' => '\}',
        ]);
    }

    private function renderMath(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');

        return $node->attr('display') === true
            ? '\\[' . $text . '\\]'
            : ($this->inlineMathNeedsBackslashDelimiters($text) ? '\\(' . $text . '\\)' : '$' . $text . '$');
    }

    private function inlineMathNeedsBackslashDelimiters(string $text): bool
    {
        return str_contains($text, '|') || str_contains($text, '$') || str_contains($text, "\n");
    }

    private function renderRawInline(AstNode $node): string
    {
        $format = strtolower((string) $node->attr('format', ''));
        if ($format !== 'tex' && $format !== 'latex') {
            return '';
        }

        return (string) $node->attr('text', '');
    }

    private function renderCode(AstNode $node, bool $insideStrikeout = false): string
    {
        $code = $this->renderHighlightedInlineCode($node)
            ?? '\\texttt{' . $this->escapeCodeText((string) $node->attr('text', '')) . '}';

        return $insideStrikeout ? '\mbox{' . $code . '}' : $code;
    }

    private function renderHighlightedInlineCode(AstNode $node): ?string
    {
        if (!$this->usesDefaultInlineHighlighting() || !$this->hasClass($node, 'haskell')) {
            return null;
        }

        $body = '\NormalTok{' . $this->escapeHighlightedInlineCodeText((string) $node->attr('text', '')) . '}';
        $delimiter = $this->highlightedVerbDelimiter($body);

        return '\VERB' . $delimiter . $body . $delimiter;
    }

    private function usesDefaultInlineHighlighting(): bool
    {
        $value = $this->options['writerHighlightMethod'] ?? $this->options['highlightMethod'] ?? null;
        if ($value === null) {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(str_replace(['-', '_', ' '], '', (string) $value));

        return in_array($normalized, ['default', 'defaulthighlighting', 'skylighting'], true);
    }

    private function escapeHighlightedInlineCodeText(string $text): string
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

    private function highlightedVerbDelimiter(string $body): string
    {
        foreach (['|', '!', '+', '/', '@', '~', ':'] as $delimiter) {
            if (!str_contains($body, $delimiter)) {
                return $delimiter;
            }
        }

        return '|';
    }

    private function renderImage(AstNode $node, bool $protect = false): string
    {
        $alt = (string) $node->attr('alt', '');
        if ($alt === '') {
            $alt = $this->plainInlineText($node->children);
        }

        $options = ['keepaspectratio'];
        if ($alt !== '') {
            $options[] = 'alt={' . $this->escapeText($alt) . '}';
        }

        $url = (string) $node->attr('url', $node->attr('src', ''));

        return ($protect ? '\protect' : '') . '\pandocbounded{\includegraphics[' . implode(',', $options) . ']{'
            . $this->escapeImageTarget($url)
            . '}}';
    }

    private function escapeCodeText(string $text): string
    {
        return strtr($text, [
            '\\' => '\\textbackslash{}',
            '{' => '\\{',
            '}' => '\\}',
            '$' => '\\$',
            '&' => '\\&',
            '%' => '\\%',
            '#' => '\\#',
            '_' => '\\_',
            "'" => '\\textquotesingle{}',
            '`' => '\\textasciigrave{}',
        ]);
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

    private function escapeImageTarget(string $target): string
    {
        return strtr($target, [
            '\\' => '\textbackslash{}',
            '{' => '\{',
            '}' => '\}',
        ]);
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
                'image' => (string) $node->attr('alt', $this->plainInlineText($node->children)),
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainHeadingText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'note') {
                continue;
            }

            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                'image' => (string) $node->attr('alt', $this->plainHeadingText($node->children)),
                default => $this->plainHeadingText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param array<mixed> $nodes
     */
    private function isAstNodeList(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'softbreak',
            'linebreak',
            'code',
            'math',
            'link',
            'raw_tex',
            'raw_tex_inline',
            'raw_inline',
        ], true);
    }
}
