<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class WordPressBlockWriter
{
    /** @var list<AstNode> */
    private array $footnotes = [];

    private bool $highlightCodeBlocks;

    private string $highlightStyle;

    /**
     * @param array{highlightCodeBlocks?: bool, highlightStyle?: string} $options
     */
    public function __construct(array $options = [])
    {
        $this->highlightCodeBlocks = ($options['highlightCodeBlocks'] ?? false) === true;
        $this->highlightStyle = (string) ($options['highlightStyle'] ?? 'pygments');
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('WordPress writer expects a document node');
        }

        $previousFootnotes = $this->footnotes;
        $this->footnotes = [];
        $blocks = [];
        $pendingList = [];
        foreach ($document->children as $node) {
            if ($node->type !== 'list_item') {
                $this->flushList($pendingList, $blocks);
            }
            if ($node->type === 'heading') {
                $level = (int) $node->attr('level', 2);
                $blocks[] = '<!-- wp:heading {"level":' . $level . '} -->'
                    . "\n" . '<h' . $level . $this->renderHeadingAttrs($node) . '>' . $this->renderInlines($node) . '</h' . $level . '>'
                    . "\n" . '<!-- /wp:heading -->';
            } elseif ($node->type === 'paragraph') {
                $blocks[] = $this->renderParagraphBlock($node);
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
            } elseif ($node->type === 'table') {
                $blocks[] = $this->renderTable($node);
            } elseif ($node->type === 'raw_html') {
                $blocks[] = $this->renderRawHtmlBlock($node);
            } elseif ($node->type === 'raw_tex') {
                $blocks[] = $this->renderRawTexBlock($node);
            } elseif ($node->type === 'code_block') {
                $blocks[] = $this->renderCodeBlock($node);
            } elseif ($node->type === 'figure') {
                $blocks[] = $this->renderFigureBlock($node);
            } elseif ($node->type === 'blockquote') {
                $blocks[] = $this->renderBlockQuote($node);
            } elseif ($node->type === 'line_block') {
                $blocks[] = $this->renderLineBlockBlock($node);
            } elseif ($node->type === 'div') {
                $blocks[] = $this->renderDivBlock($node);
            } elseif ($node->type === 'horizontal_rule') {
                $blocks[] = $this->renderHorizontalRule();
            } elseif ($node->type === 'list_item') {
                $pendingList[] = '<li>' . $this->renderInlines($node) . '</li>';
            }
        }
        $this->flushList($pendingList, $blocks);
        if ($this->footnotes !== []) {
            $blocks[] = $this->renderFootnotesBlock();
        }

        $output = implode("\n\n", $blocks);
        $this->footnotes = $previousFootnotes;

        return $output;
    }

    private function renderParagraphBlock(AstNode $node): string
    {
        if (count($node->children) === 1 && $node->children[0]->type === 'image') {
            return $this->renderFigureBlock(new AstNode(
                'figure',
                ['caption' => (string) $node->children[0]->attr('alt', '')],
                [$node->children[0]]
            ));
        }

        return '<!-- wp:paragraph -->'
            . "\n" . '<p>' . $this->renderInlines($node) . '</p>'
            . "\n" . '<!-- /wp:paragraph -->';
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
            }
            $tagAttrs = $this->renderOrderedListTagAttrs($node);
            $comment = '<!-- wp:list ' . json_encode($attrs, JSON_THROW_ON_ERROR) . ' -->';
        } elseif ($node->attr('taskList') === true) {
            $tagAttrs = ' class="task-list"';
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
        $tagAttrs = $ordered ? $this->renderOrderedListTagAttrs($node) : ($node->attr('taskList') === true ? ' class="task-list"' : '');
        $items = [];
        foreach ($node->children as $item) {
            if ($item->type === 'list_item') {
                $items[] = $this->renderListItem($item);
            }
        }

        return '<' . $tag . $tagAttrs . '>' . implode('', $items) . '</' . $tag . '>';
    }

    private function renderOrderedListTagAttrs(AstNode $node): string
    {
        $attrs = '';
        $start = (int) $node->attr('start', 1);
        if ($start > 1) {
            $attrs .= ' start="' . $start . '"';
        }

        $type = $this->orderedListHtmlType((string) $node->attr('style', 'default'));
        if ($type !== '') {
            $attrs .= ' type="' . $this->esc($type) . '"';
        }

        return $attrs;
    }

    private function renderHeadingAttrs(AstNode $node): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (is_array($htmlAttributes) && $htmlAttributes !== []) {
            $attrs = '';
            $id = (string) ($htmlAttributes['id'] ?? $node->attr('id', ''));
            if ($id !== '') {
                $attrs .= ' id="' . $this->esc($id) . '"';
            }

            $class = (string) ($htmlAttributes['class'] ?? '');
            if ($class !== '') {
                $attrs .= ' class="' . $this->esc($class) . '"';
            }

            return $attrs;
        }

        $id = (string) $node->attr('id', '');
        $attrs = $id === '' ? '' : ' id="' . $this->esc($id) . '"';
        $classes = $node->attr('classes', []);
        if (is_array($classes) && $classes !== []) {
            $class = implode(' ', array_map(static fn (mixed $value): string => (string) $value, $classes));
            if ($class !== '') {
                $attrs .= ' class="' . $this->esc($class) . '"';
            }
        }

        return $attrs;
    }

    private function orderedListHtmlType(string $style): string
    {
        return match ($style) {
            'lower_alpha' => 'a',
            'upper_alpha' => 'A',
            'lower_roman' => 'i',
            'upper_roman' => 'I',
            default => '',
        };
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
        $taskChecked = $item->attr('taskChecked', null);
        $taskPending = is_bool($taskChecked);
        $children = $item->children;

        for ($index = 0, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child->type === 'bullet_list') {
                $html .= $this->renderListHtml($child, false);
                continue;
            }
            if ($child->type === 'ordered_list') {
                $html .= $this->renderListHtml($child, true);
                continue;
            }
            if (!$this->isInlineNode($child) && $child->type !== 'paragraph') {
                $html .= $this->renderBlocksAsHtml([$child]);
                continue;
            }
            if ($child->type === 'paragraph') {
                $rendered = $this->renderInlines($child);
                if ($taskPending) {
                    $rendered = $this->renderTaskListLabel($taskChecked, $rendered);
                    $taskPending = false;
                }
                $html .= $wrapParagraphs ? '<p>' . $rendered . '</p>' : $rendered;
                continue;
            }

            if ($taskPending) {
                $inlineHtml = '';
                while ($index < $count && $this->isInlineNode($children[$index])) {
                    $inlineHtml .= $this->renderInlineNode($children[$index]);
                    $index++;
                }
                $index--;
                $html .= $this->renderTaskListLabel($taskChecked, $inlineHtml);
                $taskPending = false;
                continue;
            }

            $html .= $this->renderInlineNode($child);
        }

        return '<li>' . $html . '</li>';
    }

    private function renderTaskListLabel(bool $checked, string $html): string
    {
        $checkbox = '<input type="checkbox"' . ($checked ? ' checked=""' : '') . ' />';

        return '<label>' . $checkbox . $html . '</label>';
    }

    private function renderDefinitionList(AstNode $node): string
    {
        return '<!-- wp:html -->' . "\n" . $this->renderDefinitionListHtml($node) . "\n" . '<!-- /wp:html -->';
    }

    private function renderTable(AstNode $node): string
    {
        return '<!-- wp:table -->'
            . "\n" . '<figure' . $this->renderTableFigureAttrs($node) . '>' . $this->renderTableHtml($node) . '</figure>'
            . "\n" . '<!-- /wp:table -->';
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

    private function renderTableHtml(AstNode $node): string
    {
        $head = null;
        $bodies = [];
        $foot = null;
        foreach ($node->children as $child) {
            if ($child->type === 'table_head') {
                $head = $child;
                continue;
            }
            if ($child->type === 'table_body') {
                $bodies[] = $child;
                continue;
            }
            if ($child->type === 'table_foot') {
                $foot = $child;
            }
        }

        $columnCount = $this->tableColumnCount($node);
        $html = '<table' . $this->renderTableElementAttrs($node) . '>' . $this->renderTableColgroup($node);
        if ($head instanceof AstNode && $head->children !== []) {
            $html .= '<thead' . $this->renderStoredHtmlAttrs($head, true, []) . '>';
            $html .= $this->renderTableRows($this->tableRowEntries($head, true), $node, $columnCount);
            $html .= '</thead>';
        }

        if ($bodies === []) {
            $bodies[] = new AstNode('table_body');
        }
        foreach ($bodies as $body) {
            $html .= '<tbody' . $this->renderStoredHtmlAttrs($body, true, []) . '>';
            $html .= $this->renderTableRows($this->tableBodyRowEntries($body, $columnCount), $node, $columnCount);
            $html .= '</tbody>';
        }
        if ($foot instanceof AstNode && $foot->children !== []) {
            $html .= '<tfoot' . $this->renderStoredHtmlAttrs($foot, true, []) . '>';
            $html .= $this->renderTableRows($this->tableRowEntries($foot, false), $node, $columnCount);
            $html .= '</tfoot>';
        }
        $html .= '</table>';

        $caption = (string) $node->attr('caption', '');
        if ($caption !== '') {
            $html .= '<figcaption class="wp-element-caption">' . $this->renderCaptionInlines($node) . '</figcaption>';
        }

        return $html;
    }

    private function renderTableFigureAttrs(AstNode $node): string
    {
        $attrs = ' class="wp-block-table"';
        $shortCaption = (string) $node->attr('shortCaption', '');
        if ($shortCaption !== '') {
            $attrs .= ' data-pandoc-short-caption="' . $this->esc($shortCaption) . '"';
        }

        return $attrs;
    }

    private function renderTableElementAttrs(AstNode $node): string
    {
        return $this->renderStoredHtmlAttrs($node, true, []);
    }

    private function renderCaptionInlines(AstNode $node): string
    {
        $inlines = $node->attr('captionInlines', null);
        if (!is_array($inlines)) {
            return $this->esc((string) $node->attr('caption', ''));
        }

        $html = '';
        foreach ($inlines as $inline) {
            if (!$inline instanceof AstNode) {
                return $this->esc((string) $node->attr('caption', ''));
            }

            $html .= $this->renderInlineNode($inline);
        }

        return $html;
    }

    private function renderTableColgroup(AstNode $node): string
    {
        $widths = $node->attr('widths', null);
        if (!is_array($widths) || $widths === []) {
            return '';
        }

        $cols = [];
        foreach (TableGeometry::columnSpecs($node, count($widths)) as $spec) {
            $width = $spec['width'];
            if ($width === null) {
                return '';
            }

            $cols[] = '<col style="width:' . $this->esc($this->formatTableWidth($width)) . '"/>';
        }

        return '<colgroup>' . implode('', $cols) . '</colgroup>';
    }

    private function formatTableWidth(float $width): string
    {
        $formatted = rtrim(rtrim(number_format($width * 100, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . '%';
    }

    private function tableColumnCount(AstNode $table): int
    {
        return TableGeometry::columnCount($table);
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
     * @return list<AstNode>
     */
    private function tableBodyRows(AstNode $body): array
    {
        $rows = [];
        $bodyHeadRows = $body->attr('headRows', []);
        if (is_array($bodyHeadRows)) {
            foreach ($bodyHeadRows as $row) {
                if ($row instanceof AstNode && $row->type === 'table_row') {
                    $rows[] = $row;
                }
            }
        }

        array_push($rows, ...$this->tableRows($body));

        return $rows;
    }

    /**
     * @return list<array{row:AstNode,header:bool,rowHeadColumns:int}>
     */
    private function tableRowEntries(AstNode $section, bool $header): array
    {
        $entries = [];
        foreach ($this->tableRows($section) as $row) {
            $entries[] = [
                'row' => $row,
                'header' => $header,
                'rowHeadColumns' => 0,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{row:AstNode,header:bool,rowHeadColumns:int}>
     */
    private function tableBodyRowEntries(AstNode $body, int $columnCount): array
    {
        $entries = [];
        $rowHeadColumns = TableGeometry::rowHeadColumns($body, $columnCount);
        $bodyHeadRows = $body->attr('headRows', []);
        if (is_array($bodyHeadRows)) {
            foreach ($bodyHeadRows as $row) {
                if ($row instanceof AstNode && $row->type === 'table_row') {
                    $entries[] = [
                        'row' => $row,
                        'header' => true,
                        'rowHeadColumns' => 0,
                    ];
                }
            }
        }

        foreach ($this->tableRows($body) as $row) {
            $entries[] = [
                'row' => $row,
                'header' => false,
                'rowHeadColumns' => $rowHeadColumns,
            ];
        }

        return $entries;
    }

    /**
     * @param list<array{row:AstNode,header:bool,rowHeadColumns:int}> $rowEntries
     */
    private function renderTableRows(array $rowEntries, AstNode $table, int $columnCount): string
    {
        $rows = [];
        foreach ($rowEntries as $entry) {
            $rows[] = $entry['row'];
        }

        $html = '';
        foreach (TableGeometry::layoutRows($rows, $columnCount) as $index => $layoutRow) {
            $html .= $this->renderTableRow(
                $layoutRow,
                $table,
                (bool) ($rowEntries[$index]['header'] ?? false),
                (int) ($rowEntries[$index]['rowHeadColumns'] ?? 0)
            );
        }

        return $html;
    }

    /**
     * @param array{row:AstNode,cells:list<array{node:AstNode,column:int,colspan:int,rowspan:int}>} $layoutRow
     */
    private function renderTableRow(array $layoutRow, AstNode $table, bool $header, int $rowHeadColumns): string
    {
        $row = $layoutRow['row'];
        $html = '<tr' . $this->renderStoredHtmlAttrs($row, true, []) . '>';
        foreach ($layoutRow['cells'] as $layoutCell) {
            $cell = $layoutCell['node'];
            $attrs = $this->renderTableCellAttrs(
                $table,
                $layoutCell['column'],
                $layoutCell['colspan'],
                $layoutCell['rowspan'],
                $cell
            );
            $tag = TableGeometry::isHeaderCell($header, $rowHeadColumns, $layoutCell['column'], $cell) ? 'th' : 'td';
            $html .= '<' . $tag . $attrs . '>' . $this->renderTableCellContent($cell) . '</' . $tag . '>';
        }

        return $html . '</tr>';
    }

    private function renderTableCellContent(AstNode $cell): string
    {
        if ($cell->children === []) {
            return $this->esc((string) $cell->attr('text', ''));
        }

        $html = '';
        foreach ($cell->children as $child) {
            if ($this->isInlineNode($child)) {
                $html .= $this->renderInlineNode($child);
                continue;
            }

            $html .= $this->renderBlocksAsHtml([$child]);
        }

        return $html;
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'emph',
            'strong',
            'small_caps',
            'underline',
            'strikeout',
            'superscript',
            'subscript',
            'softbreak',
            'linebreak',
            'span',
            'quoted',
            'math',
            'raw_tex',
            'code',
            'link',
            'image',
            'note',
            'citation',
            'citation_group',
        ], true);
    }

    private function renderTableCellAttrs(AstNode $table, int $column, int $colspan, int $rowspan, AstNode $cell): string
    {
        $attrs = $this->renderStoredHtmlAttrs($cell, false, ['style']);
        if ($colspan > 1) {
            $attrs .= ' colspan="' . $colspan . '"';
        }

        if ($rowspan > 1) {
            $attrs .= ' rowspan="' . $rowspan . '"';
        }

        $alignment = TableGeometry::cellAlignment($table, $column, $cell);

        $styles = [];
        $sourceStyle = $this->storedHtmlStyle($cell);
        if ($sourceStyle !== '') {
            $styles[] = rtrim($sourceStyle, ';');
        }

        if (
            in_array($alignment, ['left', 'right', 'center'], true)
            && preg_match('/(?:^|;)\s*text-align\s*:/i', $sourceStyle) !== 1
        ) {
            $styles[] = 'text-align:' . $alignment;
        }

        if ($styles !== []) {
            $attrs .= ' style="' . $this->esc(implode('; ', $styles)) . '"';
        }

        return $attrs;
    }

    /**
     * @param list<string> $skip
     */
    private function renderStoredHtmlAttrs(AstNode $node, bool $includeIdentity, array $skip): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes) || $htmlAttributes === []) {
            return '';
        }

        $attrs = '';
        if ($includeIdentity) {
            $id = (string) ($htmlAttributes['id'] ?? $node->attr('id', ''));
            if ($id !== '') {
                $attrs .= ' id="' . $this->esc($id) . '"';
            }

            $class = (string) ($htmlAttributes['class'] ?? '');
            if ($class === '') {
                $classes = $node->attr('classes', []);
                if (is_array($classes) && $classes !== []) {
                    $class = implode(' ', array_map(static fn (mixed $value): string => (string) $value, $classes));
                }
            }
            if ($class !== '') {
                $attrs .= ' class="' . $this->esc($class) . '"';
            }
        }

        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if (
                $name === 'id'
                || $name === 'class'
                || in_array($name, $skip, true)
                || !$this->isAllowedTableHtmlAttr($name)
            ) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    private function storedHtmlStyle(AstNode $node): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (is_array($htmlAttributes) && isset($htmlAttributes['style'])) {
            return trim((string) $htmlAttributes['style']);
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes) && isset($attributes['style'])) {
            return trim((string) $attributes['style']);
        }

        return '';
    }

    private function isAllowedTableHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || in_array($name, ['abbr', 'bgcolor', 'headers', 'scope', 'style', 'title', 'valign'], true);
    }

    private function renderCodeBlock(AstNode $node): string
    {
        if ($this->highlightCodeBlocks) {
            return (new SyntaxHighlighter())->wordpressHtmlBlock($node, $this->highlightStyle);
        }

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

    private function renderFigureBlock(AstNode $node): string
    {
        return '<!-- wp:image -->'
            . "\n" . $this->renderFigureHtml($node)
            . "\n" . '<!-- /wp:image -->';
    }

    private function renderFigureHtml(AstNode $node): string
    {
        $image = null;
        foreach ($node->children as $child) {
            if ($child->type === 'image') {
                $image = $child;
                break;
            }
        }
        if (!$image instanceof AstNode) {
            $image = new AstNode('image', [
                'url' => '',
                'alt' => (string) $node->attr('caption', ''),
            ]);
        }

        $caption = (string) $node->attr('caption', $image->attr('alt', ''));
        $html = '<figure' . $this->renderImageFigureAttrs($node) . '>' . $this->renderImageHtml($image);
        if ($caption !== '') {
            $html .= '<figcaption>' . $this->esc($caption) . '</figcaption>';
        }

        return $html . '</figure>';
    }

    private function renderImageFigureAttrs(AstNode $node): string
    {
        $classes = ['wp-block-image'];
        $extraClasses = $node->attr('classes', []);
        if (is_array($extraClasses)) {
            foreach ($extraClasses as $class) {
                $classes[] = (string) $class;
            }
        }

        $attrs = ' class="' . $this->esc(implode(' ', array_values(array_unique($classes)))) . '"';
        $id = (string) $node->attr('id', '');
        if ($id !== '') {
            $attrs .= ' id="' . $this->esc($id) . '"';
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes) && isset($attributes['latex-placement'])) {
            $attrs .= ' data-pandoc-latex-placement="' . $this->esc((string) $attributes['latex-placement']) . '"';
        }

        return $attrs;
    }

    private function renderImageHtml(AstNode $node): string
    {
        $attrs = ' src="' . $this->esc((string) $node->attr('url', '')) . '"'
            . ' alt="' . $this->esc((string) $node->attr('alt', '')) . '"';
        $title = (string) $node->attr('title', '');
        if ($title !== '') {
            $attrs .= ' title="' . $this->esc($title) . '"';
        }

        return '<img' . $attrs . '/>';
    }

    private function renderBlockQuote(AstNode $node): string
    {
        return '<!-- wp:quote -->'
            . "\n" . '<blockquote class="wp-block-quote">' . $this->renderBlocksAsHtml($node->children) . '</blockquote>'
            . "\n" . '<!-- /wp:quote -->';
    }

    private function renderLineBlockBlock(AstNode $node): string
    {
        return '<!-- wp:paragraph -->'
            . "\n" . $this->renderLineBlockHtml($node)
            . "\n" . '<!-- /wp:paragraph -->';
    }

    private function renderLineBlockHtml(AstNode $node): string
    {
        $lines = [];
        foreach ($node->children as $line) {
            if ($line->type !== 'line') {
                continue;
            }

            $lines[] = $this->renderInlines($line);
        }

        return '<p>' . implode('<br/>', $lines) . '</p>';
    }

    private function renderDivBlock(AstNode $node): string
    {
        return '<!-- wp:html -->'
            . "\n" . '<div' . $this->renderInlineSpanAttrs($node) . '>' . $this->renderBlocksAsHtml($node->children) . '</div>'
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
                $html .= '<h' . $level . $this->renderHeadingAttrs($block) . '>' . $this->renderInlines($block) . '</h' . $level . '>';
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
            if ($block->type === 'table') {
                $html .= $this->renderTableHtml($block);
                continue;
            }
            if ($block->type === 'code_block') {
                $html .= $this->renderCodeBlockHtml($block);
                continue;
            }
            if ($block->type === 'figure') {
                $html .= $this->renderFigureHtml($block);
                continue;
            }
            if ($block->type === 'image') {
                $html .= $this->renderImageHtml($block);
                continue;
            }
            if ($block->type === 'blockquote') {
                $html .= '<blockquote>' . $this->renderBlocksAsHtml($block->children) . '</blockquote>';
                continue;
            }
            if ($block->type === 'line_block') {
                $html .= $this->renderLineBlockHtml($block);
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
                $html .= '<div' . $this->renderInlineSpanAttrs($block) . '>' . $this->renderBlocksAsHtml($block->children) . '</div>';
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
            'small_caps' => '<span style="font-variant:small-caps">' . $this->renderInlines($node) . '</span>',
            'underline' => '<u>' . $this->renderInlines($node) . '</u>',
            'strikeout' => '<del>' . $this->renderInlines($node) . '</del>',
            'superscript' => '<sup>' . $this->renderInlines($node) . '</sup>',
            'subscript' => '<sub>' . $this->renderInlines($node) . '</sub>',
            'softbreak' => "\n",
            'linebreak' => '<br/>',
            'span' => '<span' . $this->renderInlineSpanAttrs($node) . '>' . $this->renderInlines($node) . '</span>',
            'quoted' => $this->renderQuotedInline($node),
            'math' => $this->renderMathInline($node),
            'raw_tex' => '<span class="pandoc-raw-tex">' . $this->esc((string) $node->attr('tex', '')) . '</span>',
            'raw_html_inline' => (string) $node->attr('html', ''),
            'code' => '<code' . $this->renderInlineCodeAttrs($node) . '>' . $this->esc((string) $node->attr('text', '')) . '</code>',
            'link' => '<a' . $this->renderLinkAttrs($node) . '>' . $this->renderInlines($node) . '</a>',
            'image' => $this->renderImageHtml($node),
            'note' => $this->renderNoteReference($node),
            'citation' => $this->esc((string) $node->attr('rendered', $node->attr('text', ''))),
            'citation_group' => $this->esc((string) $node->attr('rendered', $node->attr('text', ''))),
            default => $this->renderInlines($node),
        };
    }

    private function renderNoteReference(AstNode $node): string
    {
        $number = count($this->footnotes) + 1;
        $this->footnotes[] = $node;

        return '<sup id="fnref-' . $number . '"><a href="#fn-' . $number . '" role="doc-noteref">'
            . $number
            . '</a></sup>';
    }

    private function renderFootnotesBlock(): string
    {
        $items = [];
        foreach ($this->footnotes as $index => $note) {
            $number = $index + 1;
            $items[] = '<li id="fn-' . $number . '">'
                . $this->renderBlocksAsHtml($note->children)
                . ' <a href="#fnref-' . $number . '" aria-label="Back to content">Back</a>'
                . '</li>';
        }

        return '<!-- wp:html -->'
            . "\n" . '<section class="footnotes" role="doc-endnotes"><ol>' . implode('', $items) . '</ol></section>'
            . "\n" . '<!-- /wp:html -->';
    }

    private function renderLinkAttrs(AstNode $node): string
    {
        $attrs = ' href="' . $this->esc((string) $node->attr('url', '')) . '"';
        $title = (string) $node->attr('title', '');
        if ($title !== '') {
            $attrs .= ' title="' . $this->esc($title) . '"';
        }

        $htmlAttributes = $this->inlineHtmlAttributes($node);
        if (
            count($htmlAttributes) === 1
            && isset($htmlAttributes['class'])
            && in_array((string) $htmlAttributes['class'], ['uri', 'email'], true)
        ) {
            $htmlAttributes = [];
        }

        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'href' || ($name === 'title' && $title !== '') || !$this->isAllowedInlineHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    private function renderInlineSpanAttrs(AstNode $node): string
    {
        $attrs = '';
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
            if (!$this->isAllowedInlineHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    /**
     * @return array<string, mixed>
     */
    private function inlineHtmlAttributes(AstNode $node): array
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }

        $id = (string) $node->attr('id', '');
        if ($id !== '' && !isset($htmlAttributes['id'])) {
            $htmlAttributes['id'] = $id;
        }

        if (!isset($htmlAttributes['class'])) {
            $classes = $node->attr('classes', []);
            if (is_array($classes) && $classes !== []) {
                $class = implode(' ', array_map(static fn (mixed $value): string => (string) $value, $classes));
                if ($class !== '') {
                    $htmlAttributes['class'] = $class;
                }
            }
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes)) {
            foreach ($attributes as $name => $value) {
                if (!isset($htmlAttributes[$name])) {
                    $htmlAttributes[$name] = $value;
                }
            }
        }

        return $htmlAttributes;
    }

    private function renderInlineCodeAttrs(AstNode $node): string
    {
        return $this->renderInlineSpanAttrs($node);
    }

    private function isAllowedInlineHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || in_array($name, ['cite', 'class', 'dir', 'id', 'lang', 'title'], true);
    }

    private function renderMathInline(AstNode $node): string
    {
        $class = $node->attr('display') === true ? 'display' : 'inline';
        $mathml = $node->attr('mathml', null);
        if (is_string($mathml) && trim($mathml) !== '') {
            return '<span class="math ' . $class . '">' . trim($mathml) . '</span>';
        }

        $open = $node->attr('display') === true ? '\\[' : '\\(';
        $close = $node->attr('display') === true ? '\\]' : '\\)';

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
