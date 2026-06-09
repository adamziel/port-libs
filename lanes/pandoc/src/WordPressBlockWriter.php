<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class WordPressBlockWriter
{
    private const TABLE_CELL_SCOPES = ['col', 'row', 'colgroup', 'rowgroup'];

    /** @var list<AstNode> */
    private array $footnotes = [];

    private bool $highlightCodeBlocks;

    private string $highlightStyle;

    private bool $preserveTableMissingCells;

    private bool $preserveTableCoveredSlots;

    /**
     * @param array{highlightCodeBlocks?: bool, highlightStyle?: string, preserveTableMissingCells?: bool, preserveTableCoveredSlots?: bool} $options
     */
    public function __construct(array $options = [])
    {
        $this->highlightCodeBlocks = ($options['highlightCodeBlocks'] ?? false) === true;
        $this->highlightStyle = (string) ($options['highlightStyle'] ?? 'pygments');
        $this->preserveTableMissingCells = ($options['preserveTableMissingCells'] ?? false) === true;
        $this->preserveTableCoveredSlots = ($options['preserveTableCoveredSlots'] ?? false) === true;
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
        } else {
            $tagAttrs = $this->renderUnorderedListTagAttrs($node);
        }
        $items = [];
        $headers = [];

        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }
            if ($item->attr('listHeader') === true) {
                $headers[] = $this->renderListHeaderHtml($item);
                continue;
            }
            $items[] = $this->renderListItem($item);
        }

        $headerBlock = $headers === []
            ? ''
            : '<!-- wp:html -->' . "\n" . implode('', $headers) . "\n" . '<!-- /wp:html -->' . "\n\n";

        return $headerBlock . $comment
            . "\n" . '<' . $tag . $tagAttrs . '>' . implode('', $items) . '</' . $tag . '>'
            . "\n" . '<!-- /wp:list -->';
    }

    private function renderListHtml(AstNode $node, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $tagAttrs = $ordered ? $this->renderOrderedListTagAttrs($node) : $this->renderUnorderedListTagAttrs($node);
        $items = [];
        $headers = [];
        foreach ($node->children as $item) {
            if ($item->type === 'list_item') {
                if ($item->attr('listHeader') === true) {
                    $headers[] = $this->renderListHeaderHtml($item);
                    continue;
                }
                $items[] = $this->renderListItem($item);
            }
        }

        return implode('', $headers) . '<' . $tag . $tagAttrs . '>' . implode('', $items) . '</' . $tag . '>';
    }

    private function renderUnorderedListTagAttrs(AstNode $node): string
    {
        $attrs = $node->attr('taskList') === true ? ' class="task-list"' : '';

        return $attrs . $this->renderOdfListDataAttrs($node);
    }

    private function renderListHeaderHtml(AstNode $item): string
    {
        return '<div' . $this->renderInlineSpanAttrs($item) . '>' . $this->renderBlocksAsHtml($item->children) . '</div>';
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

        return $attrs . $this->renderOdfListDataAttrs($node);
    }

    private function renderOdfListDataAttrs(AstNode $node): string
    {
        $attrs = '';
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            return $attrs;
        }

        foreach ($htmlAttributes as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $name = (string) $name;
            if (!str_starts_with($name, 'data-odf-list-')) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
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
        $html = '<dl' . $this->renderDefinitionListAttrs($node) . '>';
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

            $cslDisplayParts = $item->attr('cslDisplayParts', []);
            if (is_array($cslDisplayParts) && $cslDisplayParts !== []) {
                $html .= '<dd>' . $this->renderCslDisplayPartsHtml($cslDisplayParts) . '</dd>';
                continue;
            }

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

    private function renderDefinitionListAttrs(AstNode $node): string
    {
        $attrs = '';
        $classes = $node->attr('classes', []);
        $normalizedClasses = [];
        if (is_array($classes)) {
            foreach ($classes as $class) {
                if (!is_scalar($class)) {
                    continue;
                }

                $class = trim((string) $class);
                if ($class !== '') {
                    $normalizedClasses[] = $class;
                }
            }

            if ($normalizedClasses !== []) {
                $attrs .= ' class="' . $this->esc(implode(' ', array_values(array_unique($normalizedClasses)))) . '"';
            }
        }

        if (!in_array('pandoc-csl-bibliography', $normalizedClasses, true)) {
            return $attrs;
        }

        if ($node->attr('hangingIndent') === true) {
            $attrs .= ' data-csl-hanging-indent="true"';
        }

        foreach ([
            'entrySpacing' => 'data-csl-entry-spacing',
            'lineSpacing' => 'data-csl-line-spacing',
            'secondFieldAlign' => 'data-csl-second-field-align',
        ] as $attr => $htmlAttr) {
            $value = $node->attr($attr, null);
            if (!is_scalar($value) || (string) $value === '') {
                continue;
            }

            $attrs .= ' ' . $htmlAttr . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    /**
     * @param list<array{display?:mixed, text?:mixed, formatting?:mixed}> $parts
     */
    private function renderCslDisplayPartsHtml(array $parts): string
    {
        $html = '<div class="csl-entry">';
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }

            $display = strtolower(trim((string) ($part['display'] ?? '')));
            $class = match ($display) {
                'left-margin' => 'csl-left-margin',
                'right-inline' => 'csl-right-inline',
                'indent' => 'csl-indent',
                'block' => 'csl-block',
                default => '',
            };
            $text = (string) ($part['text'] ?? '');
            if ($class === '' || $text === '') {
                continue;
            }

            [$formatClasses, $style] = $this->cslFormattingAttrs($part['formatting'] ?? []);
            $classes = implode(' ', array_merge([$class], $formatClasses));
            $attrs = ' class="' . $this->esc($classes) . '"';
            if ($style !== '') {
                $attrs .= ' style="' . $this->esc($style) . '"';
            }

            $html .= '<div' . $attrs . '>' . $this->esc($text) . '</div>';
        }

        return $html . '</div>';
    }

    /**
     * @return array{0:list<string>, 1:string}
     */
    private function cslFormattingAttrs(mixed $formatting): array
    {
        if (!is_array($formatting)) {
            return [[], ''];
        }

        $classes = [];
        $styles = [];
        $fontStyle = (string) ($formatting['fontStyle'] ?? '');
        if (in_array($fontStyle, ['italic', 'oblique'], true)) {
            $classes[] = 'csl-font-style-' . $fontStyle;
            $styles[] = 'font-style:' . $fontStyle;
        }

        $fontVariant = (string) ($formatting['fontVariant'] ?? '');
        if ($fontVariant === 'small-caps') {
            $classes[] = 'csl-font-variant-small-caps';
            $styles[] = 'font-variant:small-caps';
        }

        $fontWeight = (string) ($formatting['fontWeight'] ?? '');
        if ($fontWeight === 'bold') {
            $classes[] = 'csl-font-weight-bold';
            $styles[] = 'font-weight:bold';
        } elseif ($fontWeight === 'light') {
            $classes[] = 'csl-font-weight-light';
            $styles[] = 'font-weight:300';
        }

        $textDecoration = (string) ($formatting['textDecoration'] ?? '');
        if ($textDecoration === 'underline') {
            $classes[] = 'csl-text-decoration-underline';
            $styles[] = 'text-decoration:underline';
        }

        $verticalAlign = (string) ($formatting['verticalAlign'] ?? '');
        if (in_array($verticalAlign, ['sup', 'sub'], true)) {
            $classes[] = 'csl-vertical-align-' . $verticalAlign;
            $styles[] = 'vertical-align:' . ($verticalAlign === 'sup' ? 'super' : 'sub');
        }

        return [$classes, implode(';', $styles)];
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
        $accessibilityAttrs = $this->tableAccessibilityAttrs($node);
        $captionHtml = $this->renderTableCaptionHtml($node);
        $captionPlacement = $this->tableCaptionPlacement($node);
        $html = $captionHtml !== '' && $captionPlacement === 'top' ? $captionHtml : '';
        $html .= '<table' . $this->renderTableElementAttrs($node) . '>' . $this->renderTableColgroup($node);
        if ($head instanceof AstNode && $head->children !== []) {
            $html .= '<thead' . $this->renderStoredHtmlAttrs($head, true, []) . '>';
            $html .= $this->renderTableRows($this->tableRowEntries($head, true), $node, $columnCount, 'head', $accessibilityAttrs);
            $html .= '</thead>';
        }

        if ($bodies === []) {
            $bodies[] = new AstNode('table_body');
        }
        foreach ($bodies as $bodyIndex => $body) {
            $section = 'body' . ($bodyIndex === 0 ? '' : (string) $bodyIndex);
            $html .= '<tbody' . $this->renderStoredHtmlAttrs($body, true, []) . '>';
            $html .= $this->renderTableRows($this->tableBodyRowEntries($body, $columnCount), $node, $columnCount, $section, $accessibilityAttrs);
            $html .= '</tbody>';
        }
        if ($foot instanceof AstNode && $foot->children !== []) {
            $html .= '<tfoot' . $this->renderStoredHtmlAttrs($foot, true, []) . '>';
            $html .= $this->renderTableRows($this->tableRowEntries($foot, false), $node, $columnCount, 'foot', $accessibilityAttrs);
            $html .= '</tfoot>';
        }
        $html .= '</table>';

        if ($captionHtml !== '' && $captionPlacement !== 'top') {
            $html .= $captionHtml;
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
        return $this->renderStoredTableElementAttrs($node)
            . $this->renderLegacyTableAlignmentAttrs($node)
            . $this->renderLegacyTableFrameAttrs($node)
            . $this->renderLegacyTableSpacingAttrs($node);
    }

    private function renderStoredTableElementAttrs(AstNode $node): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }
        $htmlAttributes = $this->mergedStoredHtmlAttributes($node, $htmlAttributes);

        $attrs = '';
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

        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if (
                $name === 'id'
                || $name === 'class'
                || in_array($name, ['align', 'border', 'cellpadding', 'cellspacing', 'frame', 'rules'], true)
                || !$this->isAllowedTableHtmlAttr($name)
            ) {
                continue;
            }

            if ($name === 'bgcolor') {
                $value = $this->legacyTableBackgroundColorValue((string) $value);
            } elseif ($name === 'style') {
                $value = $this->legacyTableStyleValue((string) $value);
            } else {
                $value = $this->allowedTableHtmlAttrValue($name, $value);
            }
            if ($value === null || $value === '') {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $attrs;
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

    private function tableHasCaption(AstNode $node): bool
    {
        return (string) $node->attr('caption', '') !== '' || $this->tableCaptionBlocks($node) !== [];
    }

    private function renderTableCaptionHtml(AstNode $node): string
    {
        if (!$this->tableHasCaption($node)) {
            return '';
        }

        return '<figcaption' . $this->renderTableCaptionAttrs($node) . '>' . $this->renderTableCaptionContent($node) . '</figcaption>';
    }

    private function tableCaptionPlacement(AstNode $node): string
    {
        $captionSource = $node->attr('captionSource', []);
        if (!is_array($captionSource)) {
            return 'bottom';
        }

        $captionSide = strtolower(trim((string) ($captionSource['captionSide'] ?? '')));

        return $captionSide === 'top' ? 'top' : 'bottom';
    }

    private function renderTableCaptionContent(AstNode $node): string
    {
        $captionBlocks = $this->tableCaptionBlocks($node);
        if ($captionBlocks !== []) {
            return $this->renderBlocksAsHtml($captionBlocks);
        }

        return $this->renderCaptionInlines($node);
    }

    private function renderTableCaptionAttrs(AstNode $node): string
    {
        $captionSource = $node->attr('captionSource', []);
        $sourceAttributes = is_array($captionSource) && is_array($captionSource['sourceAttributes'] ?? null)
            ? $captionSource['sourceAttributes']
            : [];
        $htmlAttributes = is_array($sourceAttributes['htmlAttributes'] ?? null) ? $sourceAttributes['htmlAttributes'] : [];
        $attributes = is_array($sourceAttributes['attributes'] ?? null) ? $sourceAttributes['attributes'] : [];

        $merged = [];
        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower(trim((string) $name));
            if ($name === '' || !is_scalar($value)) {
                continue;
            }

            $merged[$name] = (string) $value;
        }
        foreach ($attributes as $name => $value) {
            $name = strtolower(trim((string) $name));
            if ($name === '' || !is_scalar($value) || array_key_exists($name, $merged)) {
                continue;
            }

            $merged[$name] = (string) $value;
        }

        if (isset($sourceAttributes['id']) && is_scalar($sourceAttributes['id'])) {
            $id = trim((string) $sourceAttributes['id']);
            if ($id !== '') {
                $merged['id'] = $id;
            }
        }

        $classes = ['wp-element-caption'];
        if (isset($sourceAttributes['classes']) && is_array($sourceAttributes['classes'])) {
            foreach ($sourceAttributes['classes'] as $class) {
                if (!is_scalar($class)) {
                    continue;
                }

                $class = trim((string) $class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        } elseif (isset($merged['class'])) {
            foreach (preg_split('/\s+/', trim((string) $merged['class']), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                $classes[] = $class;
            }
        }

        $attrs = '';
        if (isset($merged['id']) && trim((string) $merged['id']) !== '') {
            $attrs .= ' id="' . $this->esc((string) $merged['id']) . '"';
        }
        $attrs .= ' class="' . $this->esc(implode(' ', array_values(array_unique($classes)))) . '"';

        foreach ($merged as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'id' || $name === 'class' || !$this->isAllowedTableHtmlAttr($name)) {
                continue;
            }

            $value = $this->allowedTableHtmlAttrValue($name, $value);
            if ($value === null) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $attrs;
    }

    /**
     * @return list<AstNode>
     */
    private function tableCaptionBlocks(AstNode $node): array
    {
        $captionBlocks = $node->attr('captionBlocks', []);
        if (!is_array($captionBlocks)) {
            return [];
        }

        return array_values(array_filter($captionBlocks, static fn (mixed $block): bool => $block instanceof AstNode));
    }

    private function renderTableColgroup(AstNode $node): string
    {
        $widths = $node->attr('widths', null);
        if (!is_array($widths) || $widths === []) {
            return '';
        }

        $specs = TableGeometry::columnSpecs($node, count($widths));
        $cols = [];
        $hasColumnSources = false;
        foreach ($specs as $spec) {
            $width = $spec['width'];
            if ($width === null) {
                return '';
            }

            $hasColumnSources = $hasColumnSources || is_array($spec['source'] ?? null);
            $cols[] = '<col style="width:' . $this->esc($this->formatTableWidth($width)) . '"/>';
        }

        if ($hasColumnSources && $this->tableColumnSpecsHaveCompleteSources($specs)) {
            return $this->renderSourceTableColgroups($specs);
        }

        return '<colgroup>' . implode('', $cols) . '</colgroup>';
    }

    /**
     * @param list<array<string, mixed>> $specs
     */
    private function tableColumnSpecsHaveCompleteSources(array $specs): bool
    {
        foreach ($specs as $spec) {
            if (!is_array($spec['source'] ?? null)) {
                return false;
            }
        }

        return $specs !== [];
    }

    /**
     * @param list<array<string, mixed>> $specs
     */
    private function renderSourceTableColgroups(array $specs): string
    {
        $html = '';
        $currentGroupKey = null;

        foreach ($specs as $spec) {
            $source = is_array($spec['source'] ?? null) ? $spec['source'] : [];
            $groupKey = $this->tableColumnSourceColgroupKey($source);
            if ($groupKey !== $currentGroupKey) {
                if ($currentGroupKey !== null) {
                    $html .= '</colgroup>';
                }

                $colgroupAttributes = is_array($source['colgroupAttributes'] ?? null)
                    ? $source['colgroupAttributes']
                    : [];
                $html .= '<colgroup'
                    . $this->renderSourceColumnDecimalAlignmentAttr($colgroupAttributes)
                    . $this->renderSourceAttributeSummaryAttrs($colgroupAttributes, true, ['align', 'span', 'style', 'valign', 'width'])
                    . '>';
                $currentGroupKey = $groupKey;
            }

            $colAttributes = is_array($source['colAttributes'] ?? null) ? $source['colAttributes'] : [];
            $attrs = $this->renderSourceColumnDecimalAlignmentAttr($colAttributes)
                . $this->renderSourceAttributeSummaryAttrs($colAttributes, false, ['align', 'span', 'style', 'width']);
            $width = isset($spec['width']) && is_numeric($spec['width']) ? (float) $spec['width'] : 0.0;
            $styles = ['width:' . $this->formatTableWidth($width)];
            $backgroundColor = $this->sourceColumnBackgroundColor($source);
            if ($backgroundColor !== '') {
                $styles[] = 'background-color:' . $backgroundColor;
            }
            array_push($styles, ...$this->sourceColumnBorderStyles($source));
            $attrs .= ' style="' . $this->esc(implode('; ', $styles)) . '"';
            $html .= '<col' . $attrs . '/>';
        }

        return $currentGroupKey === null ? '' : $html . '</colgroup>';
    }

    /**
     * @param array<string, mixed> $source
     */
    private function sourceColumnBackgroundColor(array $source): string
    {
        $colAttributes = is_array($source['colAttributes'] ?? null) ? $source['colAttributes'] : [];
        $color = $this->sourceAttributeBackgroundColor($colAttributes);
        if ($color !== '') {
            return $color;
        }

        $colgroupAttributes = is_array($source['colgroupAttributes'] ?? null) ? $source['colgroupAttributes'] : [];

        return $this->sourceAttributeBackgroundColor($colgroupAttributes);
    }

    /**
     * @param array<string, mixed> $source
     * @return list<string>
     */
    private function sourceColumnBorderStyles(array $source): array
    {
        $colAttributes = is_array($source['colAttributes'] ?? null) ? $source['colAttributes'] : [];
        $styles = $this->sourceAttributeBorderStyles($colAttributes);
        if ($styles !== []) {
            return $styles;
        }

        $colgroupAttributes = is_array($source['colgroupAttributes'] ?? null) ? $source['colgroupAttributes'] : [];

        return $this->sourceAttributeBorderStyles($colgroupAttributes);
    }

    /**
     * @param array<string, mixed> $sourceAttributes
     * @return list<string>
     */
    private function sourceAttributeBorderStyles(array $sourceAttributes): array
    {
        $attributes = $this->sourceAttributeSummaryMap($sourceAttributes);
        $style = (string) ($attributes['style'] ?? '');
        if ($style === '') {
            return [];
        }

        $declarations = [];
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $name = strtolower(trim($name));
            $value = trim($value);
            if ($name === '' || $value === '') {
                continue;
            }

            if ($name === 'border-color') {
                $color = $this->legacyTableBackgroundColorValue($value);
                if ($color !== '') {
                    $declarations[$name] = $name . ':' . $color;
                }
                continue;
            }

            if ($name === 'border-style') {
                $borderStyle = $this->legacyTableBorderStyleValue($value);
                if ($borderStyle !== '') {
                    $declarations[$name] = $name . ':' . $borderStyle;
                }
                continue;
            }

            if ($name === 'border-width') {
                $width = $this->legacyTableBorderWidthValue($value);
                if ($width !== '') {
                    $declarations[$name] = $name . ':' . $width;
                }
                continue;
            }

            if (preg_match('/^border-(top|right|bottom|left)$/', $name) === 1) {
                $border = $this->legacyTableBorderShorthandValue($value);
                if ($border !== '') {
                    $declarations[$name] = $name . ':' . $border;
                }
                continue;
            }

            if (preg_match('/^border-(top|right|bottom|left)-(color|style|width)$/', $name, $match) !== 1) {
                continue;
            }

            $normalized = match ($match[2]) {
                'color' => $this->legacyTableBackgroundColorValue($value),
                'style' => $this->legacyTableBorderStyleValue($value),
                'width' => $this->legacyTableBorderSingleWidthValue($value),
            };
            if ($normalized !== '') {
                $declarations[$name] = $name . ':' . $normalized;
            }
        }

        return array_values($declarations);
    }

    /**
     * @param array<string, mixed> $sourceAttributes
     */
    private function sourceAttributeBackgroundColor(array $sourceAttributes): string
    {
        $attributes = $this->sourceAttributeSummaryMap($sourceAttributes);
        $style = (string) ($attributes['style'] ?? '');
        if ($style !== '') {
            foreach (explode(';', $style) as $declaration) {
                [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
                if (strtolower(trim($name)) !== 'background-color') {
                    continue;
                }

                $color = $this->legacyTableBackgroundColorValue($value);
                if ($color !== '') {
                    return $color;
                }
            }
        }

        return $this->legacyTableBackgroundColorValue((string) ($attributes['bgcolor'] ?? ''));
    }

    /**
     * @param array<string, mixed> $sourceAttributes
     */
    private function renderSourceColumnDecimalAlignmentAttr(array $sourceAttributes): string
    {
        $attributes = $this->sourceAttributeSummaryMap($sourceAttributes);
        if (strtolower(trim((string) ($attributes['align'] ?? ''))) !== 'char') {
            return '';
        }

        return ' align="char"';
    }

    /**
     * @param array<string, mixed> $source
     */
    private function tableColumnSourceColgroupKey(array $source): string
    {
        if (isset($source['colgroupIndex']) && is_numeric($source['colgroupIndex'])) {
            return 'index:' . (int) $source['colgroupIndex'];
        }

        return 'attributes:' . json_encode($source['colgroupAttributes'] ?? [], JSON_UNESCAPED_SLASHES);
    }

    private function renderLegacyTableFrameAttrs(AstNode $node): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }
        $attributes = $this->mergedStoredHtmlAttributes($node, $htmlAttributes);

        $attrs = '';
        if (array_key_exists('border', $attributes)) {
            $border = $this->legacyTableBorderValue((string) $attributes['border']);
            if ($border !== '') {
                $attrs .= ' border="' . $this->esc($border) . '"';
            }
        }

        $frame = $this->legacyTableFrameValue((string) ($attributes['frame'] ?? ''));
        if ($frame !== '') {
            $attrs .= ' frame="' . $this->esc($frame) . '"';
        }

        $rules = $this->legacyTableRulesValue((string) ($attributes['rules'] ?? ''));
        if ($rules !== '') {
            $attrs .= ' rules="' . $this->esc($rules) . '"';
        }

        return $attrs;
    }

    private function renderLegacyTableAlignmentAttrs(AstNode $node): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }
        $attributes = $this->mergedStoredHtmlAttributes($node, $htmlAttributes);

        $alignment = $this->legacyTableAlignmentValue((string) ($attributes['align'] ?? ''));
        if ($alignment === '') {
            return '';
        }

        return ' align="' . $this->esc($alignment) . '"';
    }

    private function legacyTableAlignmentValue(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['left', 'right', 'center'], true) ? $value : '';
    }

    private function legacyTableBorderValue(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'border') {
            return '1';
        }

        return preg_match('/^\d{1,3}$/', $value) === 1 ? $value : '';
    }

    private function legacyTableFrameValue(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['void', 'above', 'below', 'hsides', 'lhs', 'rhs', 'vsides', 'box', 'border'], true)
            ? $value
            : '';
    }

    private function legacyTableRulesValue(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['none', 'groups', 'rows', 'cols', 'all'], true) ? $value : '';
    }

    private function renderLegacyTableSpacingAttrs(AstNode $node): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }
        $attributes = $this->mergedStoredHtmlAttributes($node, $htmlAttributes);

        $attrs = '';
        foreach (['cellpadding', 'cellspacing'] as $name) {
            if (!array_key_exists($name, $attributes)) {
                continue;
            }

            $value = $this->legacyTableSpacingValue((string) $attributes[$name]);
            if ($value !== '') {
                $attrs .= ' ' . $name . '="' . $this->esc($value) . '"';
            }
        }

        return $attrs;
    }

    private function legacyTableSpacingValue(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{1,3}$/', $value) === 1 ? $value : '';
    }

    /**
     * @param array<string, mixed> $sourceAttributes
     * @param list<string> $skip
     */
    private function renderSourceAttributeSummaryAttrs(array $sourceAttributes, bool $includeIdentity, array $skip): string
    {
        $attributes = $this->sourceAttributeSummaryMap($sourceAttributes);
        if ($attributes === []) {
            return '';
        }

        $attrs = '';
        if ($includeIdentity) {
            $id = trim((string) ($attributes['id'] ?? ''));
            if ($id !== '') {
                $attrs .= ' id="' . $this->esc($id) . '"';
            }

            $class = trim((string) ($attributes['class'] ?? ''));
            if ($class !== '') {
                $attrs .= ' class="' . $this->esc($class) . '"';
            }
        }

        foreach ($attributes as $name => $value) {
            $name = strtolower(trim((string) $name));
            if (
                $name === ''
                || $name === 'id'
                || $name === 'class'
                || in_array($name, $skip, true)
                || !$this->isAllowedTableHtmlAttr($name)
            ) {
                continue;
            }

            $value = $this->allowedTableHtmlAttrValue($name, $value);
            if ($value === null) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $sourceAttributes
     * @return array<string, string>
     */
    private function sourceAttributeSummaryMap(array $sourceAttributes): array
    {
        $merged = [];
        foreach (['htmlAttributes', 'attributes'] as $attributeKey) {
            $attributes = $sourceAttributes[$attributeKey] ?? [];
            if (!is_array($attributes)) {
                continue;
            }

            foreach ($attributes as $name => $value) {
                $name = strtolower(trim((string) $name));
                if ($name === '' || !is_scalar($value) || array_key_exists($name, $merged)) {
                    continue;
                }

                $merged[$name] = (string) $value;
            }
        }

        if (isset($sourceAttributes['id']) && is_scalar($sourceAttributes['id'])) {
            $id = trim((string) $sourceAttributes['id']);
            if ($id !== '') {
                $merged['id'] = $id;
            }
        }

        if (isset($sourceAttributes['classes']) && is_array($sourceAttributes['classes'])) {
            $classes = [];
            foreach ($sourceAttributes['classes'] as $class) {
                if (!is_scalar($class)) {
                    continue;
                }

                $class = trim((string) $class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }

            if ($classes !== []) {
                $merged['class'] = implode(' ', array_values(array_unique($classes)));
            }
        }

        ksort($merged);

        return $merged;
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
     * @return array<string, array{id?:string,scope?:string,headers?:list<string>}>
     */
    private function tableAccessibilityAttrs(AstNode $table): array
    {
        $enabled = $table->attr('accessibilityHeaders', false);
        $prefix = trim((string) $table->attr('accessibilityIdPrefix', ''));
        if ($enabled !== true && $prefix === '' && !$this->tableHasAutoHeaderScope($table)) {
            return [];
        }

        if ($prefix === '') {
            $htmlAttributes = $table->attr('htmlAttributes', []);
            if (is_array($htmlAttributes) && isset($htmlAttributes['id'])) {
                $prefix = (string) $htmlAttributes['id'];
            }
        }

        if ($prefix === '') {
            $prefix = (string) $table->attr('id', 'pandoc-table');
        }

        return TableGeometry::accessibilityAttributes($table, $prefix);
    }

    private function tableHasAutoHeaderScope(AstNode $table): bool
    {
        foreach ($table->children as $section) {
            if (!in_array($section->type, ['table_head', 'table_body', 'table_foot'], true)) {
                continue;
            }

            foreach ($section->children as $row) {
                if ($row->type !== 'table_row') {
                    continue;
                }

                foreach ($row->children as $cell) {
                    if ($cell->type === 'table_cell' && $this->sourceTableCellRawScope($cell) === 'auto') {
                        return true;
                    }
                }
            }
        }

        return false;
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
    private function renderTableRows(array $rowEntries, AstNode $table, int $columnCount, string $section, array $accessibilityAttrs): string
    {
        $rows = [];
        foreach ($rowEntries as $entry) {
            $rows[] = $entry['row'];
        }

        $gridRows = $this->preserveTableMissingCells || $this->preserveTableCoveredSlots
            ? TableGeometry::sectionGrid($rows, $columnCount)
            : [];
        $html = '';
        foreach (TableGeometry::layoutRows($rows, $columnCount) as $index => $layoutRow) {
            $html .= $this->renderTableRow(
                $layoutRow,
                $table,
                $columnCount,
                (bool) ($rowEntries[$index]['header'] ?? false),
                (int) ($rowEntries[$index]['rowHeadColumns'] ?? 0),
                $section,
                $index,
                $accessibilityAttrs,
                $gridRows[$index] ?? []
            );
        }

        return $html;
    }

    /**
     * @param array{row:AstNode,cells:list<array{node:AstNode,column:int,colspan:int,rowspan:int}>} $layoutRow
     * @param list<array<string, mixed>> $gridSlots
     */
    private function renderTableRow(
        array $layoutRow,
        AstNode $table,
        int $columnCount,
        bool $header,
        int $rowHeadColumns,
        string $section,
        int $rowIndex,
        array $accessibilityAttrs,
        array $gridSlots
    ): string
    {
        $row = $layoutRow['row'];
        $html = '<tr' . $this->renderStoredHtmlAttrs($row, true, []) . '>';
        $visualColumn = 0;
        foreach ($layoutRow['cells'] as $layoutCell) {
            $cell = $layoutCell['node'];
            $anchorColumn = (int) $layoutCell['column'];
            $html .= $this->renderMissingTableCells($gridSlots, $visualColumn, $anchorColumn);
            $accessibilityKey = TableGeometry::accessibilityKey(
                $section,
                $rowIndex,
                (int) ($layoutCell['sourceCell'] ?? 0),
                (int) ($layoutCell['sourceColumn'] ?? 0)
            );
            $attrs = $this->renderTableCellAttrs(
                $table,
                $layoutCell['column'],
                $layoutCell['colspan'],
                $layoutCell['rowspan'],
                $cell,
                $accessibilityAttrs[$accessibilityKey] ?? [],
                $this->coveredSlotReplayRecords($gridSlots[$anchorColumn] ?? null)
            );
            $tag = TableGeometry::isHeaderCell($header, $rowHeadColumns, $layoutCell['column'], $cell) ? 'th' : 'td';
            $html .= '<' . $tag . $attrs . '>' . $this->renderTableCellContent($cell) . '</' . $tag . '>';
            $visualColumn = max($visualColumn, $anchorColumn + max(1, (int) $layoutCell['colspan']));
        }

        $html .= $this->renderMissingTableCells($gridSlots, $visualColumn, $columnCount);

        return $html . '</tr>';
    }

    /**
     * @param list<array<string, mixed>> $gridSlots
     */
    private function renderMissingTableCells(array $gridSlots, int $startColumn, int $endColumn): string
    {
        if (!$this->preserveTableMissingCells || $gridSlots === []) {
            return '';
        }

        $html = '';
        for ($column = max(0, $startColumn); $column < $endColumn; $column++) {
            $slot = $gridSlots[$column] ?? null;
            if (!is_array($slot) || ($slot['kind'] ?? null) !== 'missing') {
                continue;
            }

            $row = isset($slot['row']) && is_numeric($slot['row']) ? (int) $slot['row'] : 0;
            $slotColumn = isset($slot['column']) && is_numeric($slot['column']) ? (int) $slot['column'] : $column;
            $html .= '<td data-pandoc-missing-cell="true"'
                . ' data-pandoc-missing-row="' . $this->esc((string) $row) . '"'
                . ' data-pandoc-missing-column="' . $this->esc((string) $slotColumn) . '"'
                . ' aria-hidden="true"></td>';
        }

        return $html;
    }

    /**
     * @return list<array{row:int,column:int,covering:string}>
     */
    private function coveredSlotReplayRecords(mixed $slot): array
    {
        if (!$this->preserveTableCoveredSlots || !is_array($slot) || ($slot['kind'] ?? null) !== 'cell') {
            return [];
        }

        $records = [];
        $occupiedSlots = is_array($slot['occupiedSlots'] ?? null) ? $slot['occupiedSlots'] : [];
        foreach ($occupiedSlots as $occupiedSlot) {
            if (!is_array($occupiedSlot)) {
                continue;
            }

            $covering = (string) ($occupiedSlot['covering'] ?? '');
            if (!in_array($covering, ['colspan', 'rowspan', 'rowspan-colspan'], true)) {
                continue;
            }

            $records[] = [
                'row' => isset($occupiedSlot['row']) && is_numeric($occupiedSlot['row']) ? (int) $occupiedSlot['row'] : 0,
                'column' => isset($occupiedSlot['column']) && is_numeric($occupiedSlot['column']) ? (int) $occupiedSlot['column'] : 0,
                'covering' => $covering,
            ];
        }

        return $records;
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

    /**
     * @param list<array{row:int,column:int,covering:string}> $coveredSlots
     */
    private function renderTableCellAttrs(AstNode $table, int $column, int $colspan, int $rowspan, AstNode $cell, array $accessibilityAttrs = [], array $coveredSlots = []): string
    {
        $attrs = $this->renderComputedTableAccessibilityAttrs($cell, $accessibilityAttrs);
        $attrs .= $this->renderSourceCellDecimalAlignmentAttr($cell);
        $attrs .= $this->renderStoredHtmlAttrs($cell, true, ['style']);
        $attrs .= $this->renderCoveredTableSlotAttrs($coveredSlots);
        if ($colspan > 1) {
            $attrs .= ' colspan="' . $colspan . '"';
        }

        if ($rowspan > 1) {
            $attrs .= ' rowspan="' . $rowspan . '"';
        }

        $alignment = TableGeometry::cellAlignment($table, $column, $cell);
        $verticalAlignment = TableGeometry::cellVerticalAlignment($cell);

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

        if (
            in_array($verticalAlignment, ['baseline', 'top', 'middle', 'bottom'], true)
            && !$this->sourceTableCellHasVerticalAlignment($cell, $sourceStyle)
        ) {
            $styles[] = 'vertical-align:' . $verticalAlignment;
        }

        if ($styles !== []) {
            $attrs .= ' style="' . $this->esc(implode('; ', $styles)) . '"';
        }

        return $attrs;
    }

    /**
     * @param list<array{row:int,column:int,covering:string}> $coveredSlots
     */
    private function renderCoveredTableSlotAttrs(array $coveredSlots): string
    {
        if ($coveredSlots === []) {
            return '';
        }

        $tokens = [];
        foreach ($coveredSlots as $slot) {
            $tokens[] = max(0, (int) $slot['row'])
                . ':' . max(0, (int) $slot['column'])
                . ':' . $slot['covering'];
        }

        return ' data-pandoc-span-anchor="true"'
            . ' data-pandoc-covered-slot-count="' . count($tokens) . '"'
            . ' data-pandoc-covered-slots="' . $this->esc(implode(';', $tokens)) . '"';
    }

    private function renderSourceCellDecimalAlignmentAttr(AstNode $cell): string
    {
        $htmlAttributes = $cell->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }

        $attributes = $this->mergedStoredHtmlAttributes($cell, $htmlAttributes);
        $alignment = strtolower(trim((string) ($attributes['align'] ?? '')));

        return $alignment === 'char' ? ' align="char"' : '';
    }

    private function sourceTableCellHasVerticalAlignment(AstNode $cell, string $sourceStyle): bool
    {
        if (preg_match('/(?:^|;)\s*vertical-align\s*:/i', $sourceStyle) === 1) {
            return true;
        }

        $htmlAttributes = $cell->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }

        $htmlAttributes = $this->mergedStoredHtmlAttributes($cell, $htmlAttributes);

        return isset($htmlAttributes['valign']) && trim((string) $htmlAttributes['valign']) !== '';
    }

    /**
     * @param array{id?:string,scope?:string,headers?:list<string>} $accessibilityAttrs
     */
    private function renderComputedTableAccessibilityAttrs(AstNode $cell, array $accessibilityAttrs): string
    {
        if ($accessibilityAttrs === []) {
            return '';
        }

        $sourceAttrs = $cell->attr('htmlAttributes', []);
        if (!is_array($sourceAttrs)) {
            $sourceAttrs = [];
        }
        $lowerSourceAttrs = $this->mergedStoredHtmlAttributes($cell, $sourceAttrs);
        $attrs = '';

        $id = (string) ($accessibilityAttrs['id'] ?? '');
        if ($id !== '' && $this->sourceHtmlId($cell) === '') {
            $attrs .= ' id="' . $this->esc($id) . '"';
        }

        $scope = (string) ($accessibilityAttrs['scope'] ?? '');
        if ($scope !== '' && $this->sourceTableCellScope($cell) === '') {
            $attrs .= ' scope="' . $this->esc($scope) . '"';
        }

        $headers = $accessibilityAttrs['headers'] ?? [];
        if (is_array($headers) && $headers !== [] && !isset($lowerSourceAttrs['headers'])) {
            $attrs .= ' headers="' . $this->esc(implode(' ', array_map(static fn (mixed $value): string => (string) $value, $headers))) . '"';
        }

        return $attrs;
    }

    private function sourceHtmlId(AstNode $node): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }

        $htmlAttributes = $this->mergedStoredHtmlAttributes($node, $htmlAttributes);
        if (isset($htmlAttributes['id'])) {
            $id = trim((string) $htmlAttributes['id']);
            if ($id !== '') {
                return $id;
            }
        }

        return trim((string) $node->attr('id', ''));
    }

    private function sourceTableCellScope(AstNode $cell): string
    {
        $scope = $this->sourceTableCellRawScope($cell);

        return in_array($scope, self::TABLE_CELL_SCOPES, true) ? $scope : '';
    }

    private function sourceTableCellRawScope(AstNode $cell): string
    {
        $htmlAttributes = $cell->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }

        $htmlAttributes = $this->mergedStoredHtmlAttributes($cell, $htmlAttributes);
        return strtolower(trim((string) ($htmlAttributes['scope'] ?? '')));
    }

    /**
     * @param list<string> $skip
     */
    private function renderStoredHtmlAttrs(AstNode $node, bool $includeIdentity, array $skip): string
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            $htmlAttributes = [];
        }
        $htmlAttributes = $this->mergedStoredHtmlAttributes($node, $htmlAttributes);

        if ($htmlAttributes === [] && !$includeIdentity) {
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

            $value = $this->allowedTableHtmlAttrValue($name, $value);
            if ($value === null) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return $attrs;
    }

    /**
     * @param array<string|int, mixed> $htmlAttributes
     *
     * @return array<string, mixed>
     */
    private function mergedStoredHtmlAttributes(AstNode $node, array $htmlAttributes): array
    {
        $merged = [];
        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower(trim((string) $name));
            if ($name === '' || !is_scalar($value)) {
                continue;
            }

            $merged[$name] = $value;
        }

        $attributes = $node->attr('attributes', []);
        if (!is_array($attributes)) {
            return $merged;
        }

        foreach ($attributes as $name => $value) {
            $name = strtolower(trim((string) $name));
            if ($name === '' || !is_scalar($value) || array_key_exists($name, $merged)) {
                continue;
            }

            $merged[$name] = $value;
        }

        return $merged;
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
            || in_array($name, ['abbr', 'axis', 'bgcolor', 'char', 'charoff', 'dir', 'headers', 'height', 'lang', 'nowrap', 'scope', 'style', 'summary', 'title', 'translate', 'valign', 'width', 'xml:lang'], true);
    }

    private function allowedTableHtmlAttrValue(string $name, mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = (string) $value;
        if ($name === 'width' || $name === 'height') {
            return $this->allowedTableDimensionValue($value);
        }

        if ($name === 'nowrap') {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['false', '0', 'no', 'off'], true) ? null : 'nowrap';
        }

        if ($name === 'scope') {
            $scope = strtolower(trim($value));

            return in_array($scope, self::TABLE_CELL_SCOPES, true) ? $scope : null;
        }

        if ($name === 'headers') {
            $tokens = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $headers = [];
            foreach ($tokens as $token) {
                $token = trim((string) $token);
                if ($token !== '') {
                    $headers[] = $token;
                }
            }
            $headers = array_values(array_unique($headers));

            return $headers === [] ? null : implode(' ', $headers);
        }

        if ($name === 'lang' || $name === 'xml:lang') {
            return $this->allowedTableLanguageValue($value);
        }

        if ($name === 'translate') {
            return $this->allowedTableTranslateValue($value);
        }

        if ($name !== 'dir') {
            return $value;
        }

        $direction = strtolower(trim($value));

        return in_array($direction, ['ltr', 'rtl', 'auto'], true) ? $direction : null;
    }

    private function allowedTableLanguageValue(string $value): ?string
    {
        $tag = trim($value);
        if ($tag === '' || strlen($tag) > 64 || preg_match('/\s/u', $tag) === 1) {
            return null;
        }

        if (preg_match('/^[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*$/', $tag) !== 1) {
            return null;
        }

        $parts = explode('-', $tag);
        if (count($parts) === 1 && strlen($parts[0]) === 1) {
            return null;
        }

        $normalized = [];
        foreach ($parts as $index => $part) {
            if ($index === 0 || strtolower($parts[0]) === 'x') {
                $normalized[] = strtolower($part);
                continue;
            }

            if (preg_match('/^[A-Za-z]{4}$/', $part) === 1) {
                $normalized[] = ucfirst(strtolower($part));
                continue;
            }

            if (preg_match('/^[A-Za-z]{2}$/', $part) === 1) {
                $normalized[] = strtoupper($part);
                continue;
            }

            $normalized[] = strtolower($part);
        }

        return implode('-', $normalized);
    }

    private function allowedTableTranslateValue(string $value): ?string
    {
        $state = strtolower(trim($value));
        if ($state === '') {
            return 'yes';
        }

        return in_array($state, ['yes', 'no'], true) ? $state : null;
    }

    private function legacyTableStyleValue(string $style): string
    {
        $declarations = [];
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $name = strtolower(trim($name));
            $value = trim($value);
            if ($name === '' || $value === '') {
                continue;
            }

            if ($name === 'background-color') {
                $color = $this->legacyTableBackgroundColorValue($value);
                if ($color !== '') {
                    $declarations[] = 'background-color:' . $color;
                }
                continue;
            }

            if ($name === 'width' || $name === 'height') {
                $dimension = $this->legacyTableCssDimensionValue($value);
                if ($dimension !== '') {
                    $declarations[] = $name . ':' . $dimension;
                }
                continue;
            }

            if (in_array($name, ['margin-left', 'margin-right', 'margin-inline-start', 'margin-inline-end'], true)) {
                $margin = $this->legacyTableCssLengthValue($value, true, true);
                if ($margin !== '') {
                    $declarations[] = $name . ':' . $margin;
                }
                continue;
            }

            if ($name === 'table-layout') {
                $layout = strtolower($value);
                if (in_array($layout, ['auto', 'fixed'], true)) {
                    $declarations[] = 'table-layout:' . $layout;
                }
                continue;
            }

            if ($name === 'border-collapse') {
                $collapse = strtolower($value);
                if (in_array($collapse, ['collapse', 'separate'], true)) {
                    $declarations[] = 'border-collapse:' . $collapse;
                }
                continue;
            }

            if ($name === 'border-color') {
                $color = $this->legacyTableBackgroundColorValue($value);
                if ($color !== '') {
                    $declarations[] = 'border-color:' . $color;
                }
                continue;
            }

            if ($name === 'border-style') {
                $style = $this->legacyTableBorderStyleValue($value);
                if ($style !== '') {
                    $declarations[] = 'border-style:' . $style;
                }
                continue;
            }

            if ($name === 'border-width') {
                $width = $this->legacyTableBorderWidthValue($value);
                if ($width !== '') {
                    $declarations[] = 'border-width:' . $width;
                }
            }
        }

        return implode('; ', $declarations);
    }

    private function legacyTableBorderStyleValue(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'], true)
            ? $value
            : '';
    }

    private function legacyTableBorderWidthValue(string $value): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === [] || count($tokens) > 4) {
            return '';
        }

        $normalized = [];
        foreach ($tokens as $token) {
            if (in_array($token, ['thin', 'medium', 'thick'], true)) {
                $normalized[] = $token;
                continue;
            }

            $length = $this->legacyTableCssLengthValue($token, false, false);
            if ($length === '' || str_ends_with($length, '%')) {
                return '';
            }

            $normalized[] = $length;
        }

        return implode(' ', $normalized);
    }

    private function legacyTableBorderSingleWidthValue(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/\\s/', $value) === 1) {
            return '';
        }

        return $this->legacyTableBorderWidthValue($value);
    }

    private function legacyTableBorderShorthandValue(string $value): string
    {
        $tokens = $this->cssValueTokens($value);
        if ($tokens === []) {
            return '';
        }

        $parts = [
            'width' => '',
            'style' => '',
            'color' => '',
        ];
        foreach ($tokens as $token) {
            $width = $this->legacyTableBorderSingleWidthValue($token);
            if ($width !== '' && $parts['width'] === '') {
                $parts['width'] = $width;
                continue;
            }

            $style = $this->legacyTableBorderStyleValue($token);
            if ($style !== '' && $parts['style'] === '') {
                $parts['style'] = $style;
                continue;
            }

            $color = $this->legacyTableBackgroundColorValue($token);
            if ($color !== '' && $parts['color'] === '') {
                $parts['color'] = $color;
                continue;
            }

            return '';
        }

        return implode(' ', array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * @return list<string>
     */
    private function cssValueTokens(string $value): array
    {
        $tokens = [];
        $token = '';
        $depth = 0;
        $length = strlen($value);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $value[$offset];
            if ($char === '(') {
                $depth++;
                $token .= $char;
                continue;
            }

            if ($char === ')' && $depth > 0) {
                $depth--;
                $token .= $char;
                continue;
            }

            if ($depth === 0 && ctype_space($char)) {
                if ($token !== '') {
                    $tokens[] = $token;
                    $token = '';
                }
                continue;
            }

            $token .= $char;
        }

        if ($token !== '') {
            $tokens[] = $token;
        }

        return $tokens;
    }

    private function legacyTableBackgroundColorValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $match) === 1) {
            return '#' . strtolower($match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2]);
        }

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $value, $match) === 1) {
            return '#' . strtolower($match[1]);
        }

        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $value, $match) === 1) {
            $channels = [(int) $match[1], (int) $match[2], (int) $match[3]];
            foreach ($channels as $channel) {
                if ($channel < 0 || $channel > 255) {
                    return '';
                }
            }

            return 'rgb(' . implode(', ', array_map(static fn (int $channel): string => (string) $channel, $channels)) . ')';
        }

        $name = strtolower($value);
        return in_array($name, [
            'aqua',
            'black',
            'blue',
            'fuchsia',
            'gray',
            'green',
            'grey',
            'lime',
            'maroon',
            'navy',
            'olive',
            'orange',
            'purple',
            'red',
            'silver',
            'teal',
            'transparent',
            'white',
            'yellow',
        ], true) ? $name : '';
    }

    private function legacyTableCssDimensionValue(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^(\d+(?:\.\d+)?)%$/', $value, $match) === 1) {
            $number = (float) $match[1];
            if ($number > 0.0 && $number <= 100.0) {
                return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.') . '%';
            }

            return '';
        }

        return $this->legacyTableCssLengthValue($value, false, false);
    }

    private function legacyTableCssLengthValue(string $value, bool $allowAuto, bool $allowNegative): string
    {
        $value = trim($value);
        if ($allowAuto && strtolower($value) === 'auto') {
            return 'auto';
        }

        if (preg_match('/^(-?\d+(?:\.\d+)?)(px|pt|pc|in|cm|mm|em|rem|%)$/i', $value, $match) !== 1) {
            return '';
        }

        $number = (float) $match[1];
        if ((!$allowNegative && $number < 0.0) || abs($number) > 10000.0) {
            return '';
        }

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.') . strtolower($match[2]);
    }

    private function allowedTableDimensionValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if (preg_match('/^[1-9]\d{0,3}$/', $value) === 1) {
            return (string) (int) $value;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*%$/', $value, $match) !== 1) {
            return null;
        }

        $width = (float) $match[1];
        if ($width <= 0.0 || $width > 100.0) {
            return null;
        }

        $formatted = rtrim(rtrim(number_format($width, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . '%';
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
        if (is_array($attributes)) {
            foreach ($attributes as $name => $value) {
                $name = strtolower(trim((string) $name));
                if (
                    !preg_match('/\Adata-(?:docx-caption|odf-frame-caption)-[a-z0-9._:-]+\z/', $name)
                    || !is_scalar($value)
                ) {
                    continue;
                }

                $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
            }
        }
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
        foreach ($this->imageHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
            if (in_array($name, ['src', 'alt', 'title'], true) || !$this->isAllowedImageHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return '<img' . $attrs . '/>';
    }

    /**
     * @return array<string, mixed>
     */
    private function imageHtmlAttributes(AstNode $node): array
    {
        $attributes = $this->inlineHtmlAttributes($node);
        foreach (['width', 'height'] as $name) {
            $value = $node->attr($name, '');
            if (is_string($value) && $value !== '' && !isset($attributes[$name])) {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }

    private function isAllowedImageHtmlAttr(string $name): bool
    {
        return $name === 'width' || $name === 'height' || $this->isAllowedInlineHtmlAttr($name);
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
            'space' => ' ',
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
            'citation' => $this->renderCitationInline($node),
            'citation_group' => $this->renderCitationInline($node),
            default => $this->renderInlines($node),
        };
    }

    private function renderCitationInline(AstNode $node): string
    {
        $parts = $node->attr('cslInlineParts', []);
        if (is_array($parts) && $parts !== []) {
            $html = $this->renderCslInlinePartsHtml($parts);
            if ($html !== '') {
                return $html;
            }
        }

        return $this->esc((string) $node->attr('rendered', $node->attr('text', '')));
    }

    /**
     * @param list<mixed> $parts
     */
    private function renderCslInlinePartsHtml(array $parts): string
    {
        $html = '';
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }

            $text = (string) ($part['text'] ?? '');
            if ($text === '') {
                continue;
            }

            [$classes, $style] = $this->cslFormattingAttrs($part['formatting'] ?? []);
            if ($classes === [] && $style === '') {
                $html .= $this->esc($text);
                continue;
            }

            $attrs = '';
            if ($classes !== []) {
                $attrs .= ' class="' . $this->esc(implode(' ', $classes)) . '"';
            }
            if ($style !== '') {
                $attrs .= ' style="' . $this->esc($style) . '"';
            }

            $html .= '<span' . $attrs . '>' . $this->esc($text) . '</span>';
        }

        return $html;
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
