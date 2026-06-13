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
    private function renderBlock(AstNode $node, int $listDepth = 0): array
    {
        $lines = match ($node->type) {
            'paragraph', 'plain' => [$this->renderInlines($node->children)],
            'heading' => $this->renderHeading($node),
            'figure' => $this->renderFigure($node),
            'blockquote' => $this->renderBlockQuote($node),
            'div' => $this->renderBlockGroup($node->children, $listDepth),
            'code_block' => $this->renderCodeBlock($node),
            'table' => $this->renderTable($node),
            'horizontal_rule' => [
                '\begin{center}',
                '\rule{0.5\linewidth}{0.5pt}',
                '\end{center}',
            ],
            'line_block' => $this->renderLineBlock($node),
            'bullet_list' => $this->renderList($node, false, $listDepth),
            'ordered_list' => $this->renderList($node, true, $listDepth),
            'definition_list' => $this->renderDefinitionList($node),
            'raw_tex', 'raw_block' => $this->renderRawTexBlock($node),
            'raw_html', 'raw_markdown', 'native_block', 'unsupported_command' => $this->renderUnsupportedCommandBlock($node),
            default => [],
        };

        return $this->withBlockAnchor($node, $lines);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<string>
     */
    private function renderBlockGroup(array $nodes, int $listDepth = 0): array
    {
        $lines = [];
        foreach ($nodes as $node) {
            $block = $this->renderBlock($node, $listDepth);
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

        $anchor = $this->nodeAnchorName($node);

        return [
            '\\' . $commands[$level] . '{' . $this->renderInlines($node->children) . '}'
                . ($anchor === '' ? '' : '\label{' . $anchor . '}'),
        ];
    }

    /**
     * @return list<string>
     */
    private function renderFigure(AstNode $node): array
    {
        $placement = $this->latexPlacement($node);
        $lines = ['\begin{figure}' . ($placement === '' ? '' : '[' . $placement . ']')];
        $hasImage = false;
        foreach ($node->children as $child) {
            if ($child->type === 'image') {
                if (!$hasImage) {
                    $lines[] = '\centering';
                    $hasImage = true;
                }
                $lines[] = $this->renderImage($child);
                continue;
            }

            array_push($lines, ...$this->renderBlock($child));
        }

        $caption = $this->renderCaptionCommand($node);
        if ($caption !== '') {
            $lines[] = $caption;
        }
        $lines[] = '\end{figure}';

        return $lines;
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
    private function renderLineBlock(AstNode $node): array
    {
        $lines = ['\begin{flushleft}'];
        $lineNodes = array_values(array_filter(
            $node->children,
            static fn (AstNode $child): bool => $child->type === 'line'
        ));
        foreach ($lineNodes as $index => $line) {
            $suffix = $index < count($lineNodes) - 1 ? '\\\\' : '';
            $lines[] = $this->renderLineBlockLine($line) . $suffix;
        }
        $lines[] = '\end{flushleft}';

        return $lines;
    }

    private function renderLineBlockLine(AstNode $node): string
    {
        if ($node->children !== []) {
            return $this->renderInlines($node->children);
        }

        return $this->escapeText((string) $node->attr('text', ''));
    }

    /**
     * @return list<string>
     */
    private function renderList(AstNode $node, bool $ordered, int $listDepth): array
    {
        $lines = [$ordered ? '\begin{enumerate}' : '\begin{itemize}'];
        if ($ordered) {
            array_push($lines, ...$this->orderedListSetupLines($node, $listDepth));
        }

        foreach ($node->children as $item) {
            if ($item->type === 'list_item') {
                array_push($lines, ...$this->renderListItem($item, $listDepth));
            }
        }
        $lines[] = $ordered ? '\end{enumerate}' : '\end{itemize}';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderListItem(AstNode $item, int $listDepth): array
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
                array_push($lines, ...$this->renderBlock($child, $listDepth + 1));
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function orderedListSetupLines(AstNode $node, int $listDepth): array
    {
        $counter = $this->orderedListCounter($listDepth);
        $lines = [];

        $label = $this->orderedListLabel($node, $counter);
        if ($label !== null) {
            $lines[] = '\renewcommand{\label' . $counter . '}{' . $label . '}';
        }

        $start = $node->attr('start', null);
        if (is_int($start) && $start !== 1) {
            $lines[] = '\setcounter{' . $counter . '}{' . ($start - 1) . '}';
        }

        return $lines;
    }

    private function orderedListCounter(int $listDepth): string
    {
        return ['enumi', 'enumii', 'enumiii', 'enumiv'][min(3, max(0, $listDepth))];
    }

    private function orderedListLabel(AstNode $node, string $counter): ?string
    {
        $style = (string) $node->attr('style', 'decimal');
        $delimiter = (string) $node->attr('delimiter', 'period');
        if (($style === '' || $style === 'default' || $style === 'decimal')
            && ($delimiter === '' || $delimiter === 'default' || $delimiter === 'period')
        ) {
            return null;
        }

        $number = match ($style) {
            'lower_alpha' => '\alph{' . $counter . '}',
            'upper_alpha' => '\Alph{' . $counter . '}',
            'lower_roman' => '\roman{' . $counter . '}',
            'upper_roman' => '\Roman{' . $counter . '}',
            default => '\arabic{' . $counter . '}',
        };

        return match ($delimiter) {
            'one_paren' => $number . ')',
            'two_parens' => '(' . $number . ')',
            default => $number . '.',
        };
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
     * @return list<string>
     */
    private function renderTable(AstNode $node): array
    {
        $columnCount = TableGeometry::columnCount($node);
        if ($columnCount === 0) {
            return [];
        }

        $alignments = $this->tableColumnAlignments($node, $columnCount);
        $lines = ['\begin{longtable}{' . implode('', $alignments) . '}'];
        $caption = $this->renderCaptionCommand($node);
        if ($caption !== '') {
            $lines[] = $caption . '\\\\';
        }

        $rowGroups = $this->tableRowGroups($node);
        $hasFooter = $rowGroups['foot'] !== [];
        if ($rowGroups['head'] !== []) {
            $headLines = [
                '\hline',
                ...$this->renderTableRows($rowGroups['head'], $columnCount, $alignments),
                '\hline',
            ];

            array_push($lines, ...$headLines);
            if ($hasFooter) {
                $lines[] = '\endfirsthead';
                array_push($lines, ...$headLines);
                $lines[] = '\endhead';
            }
        } elseif ($hasFooter) {
            $lines[] = '\endfirsthead';
            $lines[] = '\endhead';
        }

        if ($hasFooter) {
            $footLines = [
                '\hline',
                ...$this->renderTableRows($rowGroups['foot'], $columnCount, $alignments),
                '\hline',
            ];
            array_push($lines, ...$footLines);
            $lines[] = '\endfoot';
            array_push($lines, ...$footLines);
            $lines[] = '\endlastfoot';
        }

        foreach ($rowGroups['bodies'] as $bodyGroup) {
            if ($bodyGroup['head'] !== []) {
                $this->appendTableRule($lines);
                array_push($lines, ...$this->renderTableRows($bodyGroup['head'], $columnCount, $alignments));
                $this->appendTableRule($lines);
            }

            array_push($lines, ...$this->renderTableRows($bodyGroup['rows'], $columnCount, $alignments));
        }

        $lines[] = '\end{longtable}';

        return $lines;
    }

    /**
     * @return array{head:list<AstNode>,body:list<AstNode>,bodies:list<array{head:list<AstNode>,rows:list<AstNode>}>,foot:list<AstNode>}
     */
    private function tableRowGroups(AstNode $node): array
    {
        $groups = [
            'head' => [],
            'body' => [],
            'bodies' => [],
            'foot' => [],
        ];

        foreach ($node->children as $section) {
            if ($section->type === 'table_head') {
                array_push($groups['head'], ...$this->tableRows($section));
                continue;
            }

            if ($section->type === 'table_body') {
                $bodyGroup = [
                    'head' => [],
                    'rows' => $this->tableRows($section),
                ];
                $headRows = $section->attr('headRows', []);
                if (is_array($headRows)) {
                    foreach ($headRows as $row) {
                        if ($row instanceof AstNode && $row->type === 'table_row') {
                            $bodyGroup['head'][] = $row;
                        }
                    }
                }

                array_push($groups['body'], ...$bodyGroup['rows']);
                $groups['bodies'][] = $bodyGroup;
                continue;
            }

            if ($section->type === 'table_foot') {
                array_push($groups['foot'], ...$this->tableRows($section));
            }
        }

        return $groups;
    }

    /**
     * @param list<string> $lines
     */
    private function appendTableRule(array &$lines): void
    {
        if (($lines[array_key_last($lines)] ?? null) !== '\hline') {
            $lines[] = '\hline';
        }
    }

    /**
     * @return list<AstNode>
     */
    private function tableRows(AstNode $section): array
    {
        $rows = [];
        foreach ($section->children as $row) {
            if ($row->type === 'table_row') {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<AstNode> $rows
     * @param list<string> $alignments
     * @return list<string>
     */
    private function renderTableRows(array $rows, int $columnCount, array $alignments): array
    {
        $lines = [];
        foreach (TableGeometry::layoutRows($rows, $columnCount) as $layoutRow) {
            $cells = [];
            $visualColumn = 0;
            foreach ($layoutRow['cells'] as $layoutCell) {
                $column = (int) $layoutCell['column'];
                while ($visualColumn < $column) {
                    $cells[] = '';
                    $visualColumn++;
                }

                $colspan = max(1, (int) $layoutCell['colspan']);
                $cell = $this->renderTableCell($layoutCell['node']);
                if ($colspan > 1) {
                    $alignment = $this->latexAlignment((string) $layoutCell['node']->attr('align', $alignments[$column] ?? 'left'));
                    $cell = '\multicolumn{' . $colspan . '}{' . $alignment . '}{' . $cell . '}';
                }
                $cells[] = $cell;
                $visualColumn += $colspan;
            }

            while ($visualColumn < $columnCount) {
                $cells[] = '';
                $visualColumn++;
            }

            $lines[] = implode(' & ', $cells) . '\\\\';
        }

        return $lines;
    }

    private function renderTableCell(AstNode $cell): string
    {
        if ($cell->children === []) {
            return $this->escapeText((string) $cell->attr('text', ''));
        }

        $onlyInlines = true;
        foreach ($cell->children as $child) {
            if (!$this->isInlineNode($child)) {
                $onlyInlines = false;
                break;
            }
        }

        if ($onlyInlines) {
            return $this->renderInlines($cell->children);
        }

        return str_replace(
            ["\r\n", "\r", "\n"],
            [' ', ' ', ' \par '],
            trim(implode("\n", $this->renderBlockGroup($cell->children)))
        );
    }

    /**
     * @return list<string>
     */
    private function tableColumnAlignments(AstNode $node, int $columnCount): array
    {
        $rawAlignments = $node->attr('alignments', []);
        $alignments = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $alignments[] = $this->latexAlignment(
                is_array($rawAlignments) ? (string) ($rawAlignments[$column] ?? 'left') : 'left'
            );
        }

        return $alignments;
    }

    private function latexAlignment(string $alignment): string
    {
        return match ($alignment) {
            'right', 'r' => 'r',
            'center', 'centre', 'c' => 'c',
            default => 'l',
        };
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionList(AstNode $node): array
    {
        $lines = ['\begin{description}'];
        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            array_push($lines, ...$this->renderDefinitionItem($item));
        }
        $lines[] = '\end{description}';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionItem(AstNode $item): array
    {
        $term = '';
        $definitions = [];
        foreach ($item->children as $child) {
            if ($child->type === 'definition_term' || $child->type === 'term') {
                $term = $child->children === []
                    ? $this->escapeText((string) $child->attr('text', ''))
                    : $this->renderInlines($child->children);
                continue;
            }

            if ($child->type === 'definition') {
                $definitions[] = $child;
            }
        }

        if ($term === '') {
            $term = $this->escapeText((string) $item->attr('term', ''));
        }

        $lines = [$term === '' ? '\item' : '\item[{' . $term . '}]'];
        $hasDefinitionBody = false;
        foreach ($definitions as $definition) {
            $definitionLines = $this->renderBlockGroup($definition->children);
            if ($definitionLines === []) {
                continue;
            }

            if ($hasDefinitionBody) {
                $lines[] = '';
            }
            $firstLine = array_shift($definitionLines);
            if ($hasDefinitionBody) {
                $lines[] = $firstLine;
            } else {
                $lines[count($lines) - 1] .= ' ' . $firstLine;
            }
            array_push($lines, ...$definitionLines);
            $hasDefinitionBody = true;
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
            $text .= match ($node->type) {
                'text' => $this->escapeText((string) $node->attr('text', '')),
                'space' => ' ',
                'softbreak' => "\n",
                'linebreak' => '\\\\' . "\n",
                'emph' => $this->renderInlineCommand($node, 'emph'),
                'strong' => $this->renderInlineCommand($node, 'textbf'),
                'strikeout' => $this->renderInlineCommand($node, 'sout'),
                'superscript' => $this->renderInlineCommand($node, 'textsuperscript'),
                'subscript' => $this->renderInlineCommand($node, 'textsubscript'),
                'small_caps' => $this->renderInlineCommand($node, 'textsc'),
                'underline' => $this->renderInlineCommand($node, 'underline'),
                'span' => $this->renderSpan($node),
                'quoted' => $this->renderQuoted($node),
                'code' => $this->wrapInlineAnchor($node, $this->renderCommand('texttt', $this->escapeText((string) $node->attr('text', '')))),
                'link' => $this->renderLink($node),
                'image' => $this->renderImage($node),
                'citation' => (string) $node->attr('text', $this->renderInlines($node->children)),
                'citation_group' => (string) $node->attr('text', $this->renderInlines($node->children)),
                'note' => $this->renderNote($node),
                'math' => $this->wrapInlineAnchor($node, $this->mathConverter()->latexFor($node)),
                'raw_tex' => (string) $node->attr('tex', $node->attr('text', '')),
                'raw_html_inline', 'raw_markdown', 'native_inline', 'unsupported_command' => $this->renderUnsupportedCommandInline($node),
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

    private function renderInlineCommand(AstNode $node, string $command): string
    {
        return $this->wrapInlineAnchor($node, $this->renderCommand($command, $this->renderInlines($node->children)));
    }

    private function wrapInlineAnchor(AstNode $node, string $latex): string
    {
        $anchor = $this->nodeAnchorName($node);
        if ($anchor === '') {
            return $latex;
        }

        return '\protect\hypertarget{' . $anchor . '}{' . $latex . '}';
    }

    private function renderQuoted(AstNode $node): string
    {
        $text = $this->renderInlines($node->children);

        return (string) $node->attr('kind', '') === 'single'
            ? '`' . $text . '\''
            : '``' . $text . '\'\'';
    }

    private function renderSpan(AstNode $node): string
    {
        $text = $this->renderInlines($node->children);
        if ($this->isMarkSpan($node)) {
            return $this->renderCommand('hl', $text);
        }

        $anchor = $this->nodeAnchorName($node);
        if ($anchor === '') {
            return $text;
        }

        return '\protect\hypertarget{' . $anchor . '}{' . $text . '}';
    }

    private function isMarkSpan(AstNode $node): bool
    {
        return (string) $node->attr('id', '') === ''
            && $node->attr('classes', []) === ['mark']
            && $node->attr('attributes', []) === [];
    }

    private function renderLink(AstNode $node): string
    {
        $label = $this->renderInlines($node->children);
        $url = (string) $node->attr('url', '');
        if ($url === '') {
            return $label;
        }

        if ($url[0] === '#') {
            $target = $this->latexAnchorName(substr($url, 1));
            if ($target !== '') {
                return '\hyperlink{' . $target . '}{' . ($label === '' ? $this->escapeText($url) : $label) . '}';
            }
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

    private function renderCaption(AstNode $node): string
    {
        $captionInlines = $node->attr('captionInlines', []);
        if (is_array($captionInlines) && $captionInlines !== [] && $this->allAstNodes($captionInlines)) {
            return $this->renderInlines(array_values($captionInlines));
        }

        $captionBlocks = $this->renderCaptionBlocks($node->attr('captionBlocks', []));
        if ($captionBlocks !== '') {
            return $captionBlocks;
        }

        return $this->escapeText((string) $node->attr('caption', ''));
    }

    private function renderCaptionCommand(AstNode $node): string
    {
        $caption = $this->renderCaption($node);
        if ($caption === '') {
            return '';
        }

        $shortCaption = $this->renderShortCaption($node);

        return '\caption' . ($shortCaption === '' ? '' : '[' . $shortCaption . ']') . '{' . $caption . '}';
    }

    private function renderShortCaption(AstNode $node): string
    {
        $shortCaptionInlines = $node->attr('shortCaptionInlines', []);
        if (is_array($shortCaptionInlines) && $shortCaptionInlines !== [] && $this->allAstNodes($shortCaptionInlines)) {
            return $this->renderInlines(array_values($shortCaptionInlines));
        }

        $shortCaptionBlocks = $this->renderCaptionBlocks($node->attr('shortCaptionBlocks', []));
        if ($shortCaptionBlocks !== '') {
            return $shortCaptionBlocks;
        }

        return $this->escapeText((string) $node->attr('shortCaption', ''));
    }

    private function renderCaptionBlocks(mixed $blocks): string
    {
        if (!is_array($blocks) || $blocks === [] || !$this->allAstNodes($blocks)) {
            return '';
        }

        return str_replace(
            ["\r\n", "\r", "\n"],
            [' ', ' ', ' '],
            trim(implode("\n", $this->renderBlockGroup(array_values($blocks))))
        );
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
            return $this->renderUnsupportedCommandInline($node);
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
            return $this->renderUnsupportedCommandBlock($node);
        }

        return explode("\n", (string) $node->attr('tex', $node->attr('text', '')));
    }

    /**
     * @return list<string>
     */
    private function renderUnsupportedCommandBlock(AstNode $node): array
    {
        $lines = [
            '\begin{quote}',
            $this->renderCommand('texttt', $this->unsupportedCommandLabel($node, 'block')),
        ];

        $children = $this->renderUnsupportedCommandBlockChildren($node->children);
        if ($children !== []) {
            $lines[] = '';
            array_push($lines, ...$children);
        }

        $lines[] = '\end{quote}';

        return $lines;
    }

    /**
     * @param list<AstNode> $children
     * @return list<string>
     */
    private function renderUnsupportedCommandBlockChildren(array $children): array
    {
        $lines = [];
        $inlineRun = [];
        foreach ($children as $child) {
            if ($this->isInlineNode($child)) {
                $inlineRun[] = $child;
                continue;
            }

            if ($inlineRun !== []) {
                $inline = $this->renderInlines($inlineRun);
                if ($inline !== '') {
                    if ($lines !== []) {
                        $lines[] = '';
                    }
                    $lines[] = $inline;
                }
                $inlineRun = [];
            }

            $block = $this->renderBlock($child);
            if ($block === []) {
                continue;
            }

            if ($lines !== []) {
                $lines[] = '';
            }
            array_push($lines, ...$block);
        }

        if ($inlineRun !== []) {
            $inline = $this->renderInlines($inlineRun);
            if ($inline !== '') {
                if ($lines !== []) {
                    $lines[] = '';
                }
                $lines[] = $inline;
            }
        }

        return $lines;
    }

    private function renderUnsupportedCommandInline(AstNode $node): string
    {
        $label = $this->renderCommand('texttt', $this->unsupportedCommandLabel($node, 'inline'));
        $children = $this->renderInlines($node->children);

        return $children === '' ? $label : $label . ' ' . $children;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function withBlockAnchor(AstNode $node, array $lines): array
    {
        $anchor = $this->nodeAnchorName($node);
        if ($anchor === '' || $lines === []) {
            return $lines;
        }

        return [
            '\hypertarget{' . $anchor . '}{%',
            ...$lines,
            '}',
        ];
    }

    private function unsupportedCommandLabel(AstNode $node, string $scope): string
    {
        $label = '[unsupported ' . $scope . ' command: ' . $this->unsupportedCommandName($node);
        $detail = $this->unsupportedCommandDetail($node);
        if ($detail !== '') {
            $label .= ' - ' . $detail;
        }

        return $this->escapeText($label . ']');
    }

    private function unsupportedCommandName(AstNode $node): string
    {
        $command = $node->attr('command', null);
        if (is_string($command) && trim($command) !== '') {
            return trim($command);
        }

        $constructor = $node->attr('constructor', null);
        if (is_string($constructor) && trim($constructor) !== '') {
            return trim($constructor);
        }

        $format = $node->attr('format', null);
        if (is_string($format) && trim($format) !== '') {
            return 'raw ' . strtolower(trim($format));
        }

        return $node->type;
    }

    private function unsupportedCommandDetail(AstNode $node): string
    {
        $details = [];
        foreach (['reason', 'message', 'text', 'html', 'markdown', 'tex'] as $attr) {
            $detail = $node->attr($attr, null);
            if (is_string($detail) && trim($detail) !== '') {
                $details[] = $detail;
                break;
            }
        }

        foreach ([
            'arguments' => 'arguments',
            'args' => 'arguments',
            'options' => 'options',
            'attributes' => 'attributes',
        ] as $attr => $label) {
            $detail = $node->attr($attr, null);
            if (!is_array($detail) || $detail === []) {
                continue;
            }

            $summary = $this->summarizeUnsupportedCommandValue($detail);
            if ($summary !== '') {
                $details[] = $label . ': ' . $summary;
            }
        }

        return $details === [] ? '' : $this->summarizeUnsupportedCommandDetail(implode('; ', $details));
    }

    private function summarizeUnsupportedCommandDetail(string $detail): string
    {
        $summary = preg_replace('/\s+/', ' ', trim($detail)) ?? trim($detail);
        if (strlen($summary) <= 160) {
            return $summary;
        }

        return substr($summary, 0, 157) . '...';
    }

    private function summarizeUnsupportedCommandValue(mixed $value, int $depth = 0): string
    {
        if ($value instanceof AstNode) {
            $text = $value->attr('text', $value->attr('tex', null));
            if (is_scalar($text) && trim((string) $text) !== '') {
                return $value->type . '(' . $this->summarizeUnsupportedCommandDetail((string) $text) . ')';
            }

            if ($value->children !== [] && $depth < 2) {
                $children = $this->summarizeUnsupportedCommandValue($value->children, $depth + 1);
                return $children === '' ? $value->type : $value->type . '(' . $children . ')';
            }

            return $value->type;
        }

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return $this->summarizeUnsupportedCommandDetail((string) $value);
        }

        if (!is_array($value) || $value === []) {
            return '';
        }

        $parts = [];
        if (array_is_list($value)) {
            foreach (array_slice($value, 0, 4) as $item) {
                $summary = $this->summarizeUnsupportedCommandValue($item, $depth + 1);
                if ($summary !== '') {
                    $parts[] = $summary;
                }
            }
        } else {
            ksort($value, SORT_STRING);
            foreach (array_slice($value, 0, 5, true) as $key => $item) {
                $summary = $this->summarizeUnsupportedCommandValue($item, $depth + 1);
                if ($summary !== '') {
                    $parts[] = (string) $key . '=' . $summary;
                }
            }
        }

        if (count($parts) < count($value)) {
            $parts[] = '...';
        }

        return implode(', ', $parts);
    }

    private function nodeAnchorName(AstNode $node): string
    {
        foreach (['id', 'identifier'] as $name) {
            $value = $node->attr($name, null);
            if (is_scalar($value)) {
                $anchor = $this->latexAnchorName((string) $value);
                if ($anchor !== '') {
                    return $anchor;
                }
            }
        }

        foreach (['attributes', 'htmlAttributes'] as $name) {
            $attributes = $node->attr($name, []);
            if (!is_array($attributes) || !array_key_exists('id', $attributes) || !is_scalar($attributes['id'])) {
                continue;
            }

            $anchor = $this->latexAnchorName((string) $attributes['id']);
            if ($anchor !== '') {
                return $anchor;
            }
        }

        return '';
    }

    private function latexAnchorName(string $identifier): string
    {
        $anchor = preg_replace('/[^A-Za-z0-9:._-]+/', '-', trim($identifier)) ?? '';

        return trim($anchor, '-');
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
            'raw_html_inline',
            'raw_markdown',
            'raw_inline',
            'native_inline',
            'unsupported_command',
        ], true);
    }

    /**
     * @param list<mixed> $nodes
     */
    private function allAstNodes(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    private function latexPlacement(AstNode $node): string
    {
        $attributes = $node->attr('attributes', []);
        $placement = is_array($attributes) ? (string) ($attributes['latex-placement'] ?? '') : '';
        if ($placement === '') {
            $placement = (string) $node->attr('latex-placement', $node->attr('latexPlacement', ''));
        }

        return preg_replace('/[^A-Za-z!*]/', '', $placement) ?? '';
    }
}
